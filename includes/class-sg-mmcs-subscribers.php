<?php
namespace SoftGlaze\MMCS;

if ( ! defined( 'ABSPATH' ) ) exit;

class Subscribers {

    const TABLE = 'sg_mmcs_subscribers';

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }

/**
 * Bust internal object caches for subscriber queries.
 *
 * @return void
 */
private static function bust_cache() {
    $group = 'sg_mmcs';
    wp_cache_delete( 'subscribers_count', $group );
    wp_cache_delete( 'subscribers_rows_200', $group );
    wp_cache_delete( 'subscribers_export_rows', $group );
}

    public static function create_table() {
        global $wpdb;
        $table = self::table_name();
        $charset = $wpdb->get_charset_collate();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is fixed.
        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            email VARCHAR(190) NOT NULL,
            created_at DATETIME NOT NULL,
            ip VARCHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            source_url TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            meta LONGTEXT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY email_unique (email)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function add( $email, $data = [] ) {
        global $wpdb;
        $table = self::table_name();

        $email = sanitize_email( $email );
        if ( ! is_email( $email ) ) return new \WP_Error( 'invalid_email', __( 'Invalid email address.', 'softglaze-maintenance-mode-coming-soon' ) );

        $cache_group = 'sg_mmcs';
        $cache_key   = 'subscriber_id_' . md5( $email );
        $existing    = wp_cache_get( $cache_key, $cache_group );
        if ( false === $existing ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Table name is fixed.
            $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE email=%s", $email ) );
            wp_cache_set( $cache_key, $existing ? (int) $existing : 0, $cache_group, 10 * MINUTE_IN_SECONDS );
        }
        if ( ! empty( $existing ) ) {
            return (int) $existing;
        }

        $ip = isset($data['ip']) ? sanitize_text_field($data['ip']) : '';
        $ua = isset($data['user_agent']) ? substr( sanitize_text_field($data['user_agent']), 0, 250 ) : '';
        $source_url = isset($data['source_url']) ? esc_url_raw($data['source_url']) : '';
        $meta = isset($data['meta']) ? wp_json_encode( $data['meta'] ) : '';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- $wpdb->insert is required for custom table writes.
        $wpdb->insert( $table, [
            'email' => $email,
            'created_at' => current_time('mysql'),
            'ip' => $ip,
            'user_agent' => $ua,
            'source_url' => $source_url,
            'status' => 'active',
            'meta' => $meta,
        ], [ '%s','%s','%s','%s','%s','%s','%s' ] );

        $insert_id = (int) $wpdb->insert_id;
        // Refresh caches.
        wp_cache_set( $cache_key, $insert_id, $cache_group, 10 * MINUTE_IN_SECONDS );
        self::bust_cache();
        return $insert_id;
    }

    public static function export_csv() {
        if ( ! current_user_can('manage_options') ) wp_die('Forbidden');

        global $wpdb;
        $table = self::table_name();

        $cache_group = 'sg_mmcs';
        $cache_key   = 'subscribers_export_rows';
        $rows        = wp_cache_get( $cache_key, $cache_group );
        if ( false === $rows ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Table name is fixed.
            $rows = $wpdb->get_results( "SELECT email, created_at, ip, source_url, status, meta FROM {$table} ORDER BY created_at DESC", ARRAY_A );
            // Short-lived cache (export action) to satisfy Plugin Check caching guidance.
            wp_cache_set( $cache_key, $rows, $cache_group, 30 );
        }

        // Gather dynamic headers from all rows (e.g., 'phone', 'first_name')
        $meta_headers = [];
        foreach ($rows as $r) {
            $m = json_decode($r['meta'], true);
            if (is_array($m)) {
                foreach (array_keys($m) as $key) {
                    if ($key !== 'mode' && !in_array($key, $meta_headers)) {
                        $meta_headers[] = $key;
                    }
                }
            }
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=softglaze-subscribers.csv');

        $out = fopen('php://output', 'w');
        
        // Build Header Row
        $header_row = ['email', 'created_at', 'ip', 'source_url', 'status'];
        foreach ($meta_headers as $mh) {
            $header_row[] = ucfirst($mh); // Capitalize for professional look
        }
        fputcsv($out, $header_row);

        // Build Data Rows
        foreach ($rows as $r) {
            $row_data = [
                $r['email'],
                $r['created_at'],
                $r['ip'],
                $r['source_url'],
                $r['status']
            ];
            
            $m = json_decode($r['meta'], true);
            foreach ($meta_headers as $mh) {
                $row_data[] = isset($m[$mh]) ? $m[$mh] : '';
            }
            
            fputcsv($out, $row_data);
        }
        
        exit;
    }
}