<?php
namespace SoftGlaze\MMCS;

if ( ! defined( 'ABSPATH' ) ) exit;

class Options {

    const OPTION_KEY = 'sg_mmcs_options';


    /**
     * Runtime overrides used for preview rendering (e.g., admin template preview).
     * Not persisted to the database.
     *
     * @var array|null
     */
    private static $runtime_overrides = null;

    /**
     * Set runtime overrides for Options::get().
     *
     * @param array $overrides Key/value overrides applied on top of stored options.
     */
    public static function set_runtime_overrides( array $overrides ) {
        self::$runtime_overrides = $overrides;
    }

    /**
     * Clear runtime overrides.
     */
    public static function clear_runtime_overrides() {
        self::$runtime_overrides = null;
    }


    public static function defaults() {
        return [
            'mode' => 'off',
            'page_source' => 'template',
            'page_id' => 0,
            'landing_id' => 0,
            'http_status' => '503',
            'retry_after' => 3600,
            
            'site_title' => '',
            'headline' => 'Something amazing is coming.',
            'message' => 'We are working hard to bring you the best experience. Stay tuned!',
            'logo_url' => '', 
            'background_color' => '#ffffff',
            'text_color' => '#1f2937',
            'accent_color' => '#2563eb',
            'background_image' => '',
            
            'inherit_fonts' => 0,
            'inherit_colors' => 0,
            
            'coming_soon_template' => '1',
            'maintenance_template' => '1',

            'custom_css' => '',
            'custom_html' => '',
            'header_scripts' => '',
            'body_scripts' => '',
            'footer_scripts' => '',

            // Easy Integrations
            'gtm_id' => '',
            'google_site_verification' => '',
            'facebook_domain_verification' => '',
            'bing_site_verification' => '',
            'custom_meta_tags' => '',
            
            'noindex' => 1,
            'og_image' => '',
            'favicon' => '',
            'ga4_id' => '',
            'fb_pixel_id' => '',

            'show_countdown' => 0,
            'countdown_date' => '',
            'countdown_style' => 'simple', 
            'countdown_action' => 'message', 
            'countdown_finished_message' => 'We are live!',
            'countdown_redirect_url' => '',
            
            'show_subscribe' => 1,
            'form_type' => 'builtin', 
            'form_shortcode' => '',
            
            'subscribe_title' => 'Get notified when we launch',
            'subscribe_button' => 'Subscribe',
            'gdpr_notice' => 'We respect your privacy. No spam.',
            'form_fields' => [
                [
                    'key' => 'email',
                    'type' => 'email',
                    'label' => 'Email Address',
                    'placeholder' => 'Enter your email',
                    'width' => '100',
                    'required' => 1,
                    'is_default' => 1 
                ]
            ],

            'show_social' => 0,
            'social_icons' => [], 

            'bypass_logged_in' => 0,
            'bypass_roles' => ['administrator'],
            'bypass_users' => [], 
            'ip_allowlist' => '',
            'secret_bypass_key' => '',
            'secret_bypass_param' => 'sg_access',
            'password_protect' => 0,
            'access_password' => '',
            
            'exclude_urls' => "/wp-admin/\n/wp-login.php\n/wp-json/\n/xmlrpc.php\n/favicon.ico\n/robots.txt\n/sitemap.xml\n/sitemap_index.xml",
            'include_urls_only' => '',
            
            'store_subscribers' => 1,
            'notify_admin' => 1,
            'admin_notify_email' => '',
            'webhook_url' => '',
            'mailchimp_api_key' => '',
            'mailchimp_list_id' => '',
            'mailchimp_dc' => '',
            
            'rate_limit_per_ip_per_hour' => 10,
            'honeypot_field' => 'company',
            'clean_on_uninstall' => 0,

            // Premium-style workflow features (WP.org compliant).
            'preview_links_enabled' => 1,
            'preview_link_expiry_minutes' => 1440, // 24h
            'bypass_links_enabled' => 1,
            'bypass_link_duration_hours' => 8,

            // Scheduling / automation
            'schedule_enabled' => 0,
            'schedule_mode' => 'maintenance',
            'schedule_start_ts' => 0,
            'schedule_end_ts' => 0,
            'schedule_restore_mode' => 'off',

            // Rules
            'woocommerce_safe_mode' => 1,

            // Simple built-in analytics
            'analytics_enabled' => 1,
        ];
    }

    /**
     * Parse a datetime-local string (YYYY-mm-ddTHH:MM) or a datetime string (YYYY-mm-dd HH:MM)
     * into a UTC timestamp.
     */
    private static function parse_datetime_to_utc_ts( $value ) {
        $value = trim( (string) $value );
        if ( '' === $value ) {
            return 0;
        }

        // Accept both "2026-01-07T14:30" and "2026-01-07 14:30".
        $value = str_replace( 'T', ' ', $value );
        $tz = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );

        try {
            $dt = new \DateTimeImmutable( $value, $tz );
            return $dt->getTimestamp();
        } catch ( \Exception $e ) {
            return 0;
        }
    }

    public static function get() {
        $opts = get_option( self::OPTION_KEY, [] );
        $defaults = self::defaults();
        $merged = wp_parse_args( is_array( $opts ) ? $opts : [], $defaults );

        // Apply runtime overrides (e.g., admin preview rendering).
        if ( is_array( self::$runtime_overrides ) && ! empty( self::$runtime_overrides ) ) {
            $merged = array_merge( $merged, self::$runtime_overrides );
        }

        if ( ! is_array( $merged['bypass_roles'] ) ) $merged['bypass_roles'] = $defaults['bypass_roles'];
        if ( ! is_array( $merged['bypass_users'] ) ) $merged['bypass_users'] = $defaults['bypass_users'];
        if ( ! is_array( $merged['social_icons'] ) ) $merged['social_icons'] = $defaults['social_icons'];
        if ( ! is_array( $merged['form_fields'] ) ) $merged['form_fields'] = $defaults['form_fields'];

        return $merged;
    }

    public static function sanitize( $new ) {
        $defaults = self::defaults();
        // 1. Get existing DB options to preserve settings from other tabs
        $current = get_option( self::OPTION_KEY, [] );
        if(!is_array($current)) $current = [];

        // 2. Merge: Start with Defaults, overwrite with Current DB, then overwrite with New Input
        // This ensures missing keys (from other tabs) are kept from $current.
        // Boolean checkboxes MUST have hidden '0' inputs in the form for this to work (handled in Admin.php).
        $merged = array_merge( $defaults, $current, (array)$new );

        $out = [];

        $mode = (string) ( $merged['mode'] ?? 'off' );
        $out['mode'] = in_array( $mode, [ 'off', 'coming_soon', 'maintenance' ], true ) ? $mode : 'off';

        $page_source = (string) ( $merged['page_source'] ?? 'template' );
        $out['page_source'] = in_array( $page_source, [ 'template', 'page', 'landing' ], true ) ? $page_source : 'template';

        $out['page_id'] = absint( $merged['page_id'] ?? 0 );
        $out['landing_id'] = absint( $merged['landing_id'] ?? 0 );

        $http_status = (string) ( $merged['http_status'] ?? '503' );
        $out['http_status'] = in_array( $http_status, [ '200', '503' ], true ) ? $http_status : '503';
        $out['retry_after'] = max( 0, absint( $merged['retry_after'] ?? 3600 ) );

        $out['site_title'] = sanitize_text_field( $merged['site_title'] ?? '' );
        $out['headline'] = sanitize_text_field( $merged['headline'] ?? $defaults['headline'] );
        $out['message'] = wp_kses_post( $merged['message'] ?? $defaults['message'] );

// --- MODE-AWARE DEFAULT CONTENT ---
// If the user switches mode in the General tab (where headline/message are not present),
// and they haven't customized the content yet, switch to a sensible default for that mode.
$maintenance_default_headline = 'Under Maintenance';
$maintenance_default_message  = 'We are performing scheduled maintenance. Please check back soon.';

$mode_changed = isset($new['mode']) && ( ($current['mode'] ?? 'off') !== $out['mode'] );
$saving_general_only = $mode_changed && !isset($new['headline']) && !isset($new['message']);
if ( $saving_general_only ) {
    $current_head = isset($current['headline']) ? (string)$current['headline'] : '';
    $current_msg  = isset($current['message']) ? (string)$current['message'] : '';

    if ( $out['mode'] === 'maintenance' ) {
        // If current content is empty or still the Coming Soon defaults, switch to maintenance defaults.
        if ( $current_head === '' || $current_head === (string)$defaults['headline'] ) {
            $out['headline'] = $maintenance_default_headline;
        }
        if ( $current_msg === '' || $current_msg === (string)$defaults['message'] ) {
            $out['message'] = wp_kses_post( wpautop($maintenance_default_message) );
        }
    } elseif ( $out['mode'] === 'coming_soon' ) {
        // If current content is empty or still maintenance defaults, switch back to Coming Soon defaults.
        if ( $current_head === '' || $current_head === $maintenance_default_headline ) {
            $out['headline'] = (string)$defaults['headline'];
        }
        if ( $current_msg === '' || $current_msg === wpautop($maintenance_default_message) || $current_msg === $maintenance_default_message ) {
            $out['message'] = wp_kses_post( $defaults['message'] );
        }
    }
}
        $out['logo_url'] = esc_url_raw( $merged['logo_url'] ?? '' );
        
        $out['background_color'] = sanitize_hex_color( $merged['background_color'] ?? $defaults['background_color'] ) ?: $defaults['background_color'];
        $out['text_color'] = sanitize_hex_color( $merged['text_color'] ?? $defaults['text_color'] ) ?: $defaults['text_color'];
        $out['accent_color'] = sanitize_hex_color( $merged['accent_color'] ?? $defaults['accent_color'] ) ?: $defaults['accent_color'];
        $out['background_image'] = esc_url_raw( $merged['background_image'] ?? '' );

        // Premium workflow toggles
        $out['preview_links_enabled'] = ! empty( $merged['preview_links_enabled'] ) ? 1 : 0;
        $out['preview_link_expiry_minutes'] = max( 5, absint( $merged['preview_link_expiry_minutes'] ?? 1440 ) );
        $out['bypass_links_enabled'] = ! empty( $merged['bypass_links_enabled'] ) ? 1 : 0;
        $out['bypass_link_duration_hours'] = max( 1, min( 168, absint( $merged['bypass_link_duration_hours'] ?? 8 ) ) );

        // Scheduling
        $schedule_enabled_new = ! empty( $merged['schedule_enabled'] ) ? 1 : 0;
        $schedule_enabled_old = ! empty( $current['schedule_enabled'] ?? 0 ) ? 1 : 0;
        $out['schedule_enabled'] = $schedule_enabled_new;
        $sm = (string) ( $merged['schedule_mode'] ?? 'maintenance' );
        $out['schedule_mode'] = in_array( $sm, [ 'coming_soon', 'maintenance' ], true ) ? $sm : 'maintenance';

        // When schedule is enabled for the first time, remember the previous mode so we can restore.
        if ( 1 === $schedule_enabled_new && 0 === $schedule_enabled_old ) {
            $prev = (string) ( $current['mode'] ?? 'off' );
            $out['schedule_restore_mode'] = in_array( $prev, [ 'off', 'coming_soon', 'maintenance' ], true ) ? $prev : 'off';
        } else {
            $restore = (string) ( $merged['schedule_restore_mode'] ?? ( $current['schedule_restore_mode'] ?? 'off' ) );
            $out['schedule_restore_mode'] = in_array( $restore, [ 'off', 'coming_soon', 'maintenance' ], true ) ? $restore : 'off';
        }

        // Accept datetime-local inputs from the form (keys kept stable for backward compatibility).
        $start_ts = self::parse_datetime_to_utc_ts( $merged['schedule_start'] ?? ( $merged['schedule_start_ts'] ?? '' ) );
        $end_ts   = self::parse_datetime_to_utc_ts( $merged['schedule_end'] ?? ( $merged['schedule_end_ts'] ?? '' ) );
        $out['schedule_start_ts'] = $start_ts;
        $out['schedule_end_ts']   = $end_ts;

        // Rules
        $out['woocommerce_safe_mode'] = ! empty( $merged['woocommerce_safe_mode'] ) ? 1 : 0;

        // Analytics
        $out['analytics_enabled'] = ! empty( $merged['analytics_enabled'] ) ? 1 : 0;

        // Since we merged $current, we can rely on value being 1 or 0 (provided by hidden input).
        $out['inherit_fonts'] = ! empty( $merged['inherit_fonts'] ) ? 1 : 0;
        $out['inherit_colors'] = ! empty( $merged['inherit_colors'] ) ? 1 : 0;

        $cs_tmpl = (string)($merged['coming_soon_template'] ?? '1');
        $out['coming_soon_template'] = in_array($cs_tmpl, ['1','2','3','4']) ? $cs_tmpl : '1';

        $mm_tmpl = (string)($merged['maintenance_template'] ?? '1');
        $out['maintenance_template'] = in_array($mm_tmpl, ['1','2','3','4','5','6']) ? $mm_tmpl : '1';

        // Custom Scripts
        if ( current_user_can( 'unfiltered_html' ) ) {
            $out['custom_css'] = $merged['custom_css'] ?? '';
            $out['custom_html'] = $merged['custom_html'] ?? '';
            $out['header_scripts'] = $merged['header_scripts'] ?? '';
            $out['body_scripts'] = $merged['body_scripts'] ?? '';
            $out['footer_scripts'] = $merged['footer_scripts'] ?? '';
        } else {
            $out['custom_css'] = wp_strip_all_tags( $merged['custom_css'] ?? '' );
            $out['custom_html'] = wp_kses_post( $merged['custom_html'] ?? '' );
            $out['header_scripts'] = '';
            $out['body_scripts'] = '';
            $out['footer_scripts'] = '';
        }

        // Easy Integrations
        $out['gtm_id'] = sanitize_text_field( $merged['gtm_id'] ?? '' );
        $out['google_site_verification'] = sanitize_text_field( $merged['google_site_verification'] ?? '' );
        $out['facebook_domain_verification'] = sanitize_text_field( $merged['facebook_domain_verification'] ?? '' );
        $out['bing_site_verification'] = sanitize_text_field( $merged['bing_site_verification'] ?? '' );

        // Allow basic meta/link tags for non-technical users (kept separate from scripts)
        if ( current_user_can( 'unfiltered_html' ) ) {
            $allowed = [
                'meta' => [
                    'name' => true,
                    'content' => true,
                    'property' => true,
                    'charset' => true,
                    'http-equiv' => true,
                ],
                'link' => [
                    'rel' => true,
                    'href' => true,
                    'type' => true,
                    'sizes' => true,
                ],
            ];
            $out['custom_meta_tags'] = wp_kses( $merged['custom_meta_tags'] ?? '', $allowed );
        } else {
            $out['custom_meta_tags'] = '';
        }

        $out['noindex'] = ! empty( $merged['noindex'] ) ? 1 : 0;
        $out['og_image'] = esc_url_raw( $merged['og_image'] ?? '' );
        $out['favicon'] = esc_url_raw( $merged['favicon'] ?? '' );
        $out['ga4_id'] = sanitize_text_field( $merged['ga4_id'] ?? '' );
        $out['fb_pixel_id'] = sanitize_text_field( $merged['fb_pixel_id'] ?? '' );

        $out['show_countdown'] = ! empty( $merged['show_countdown'] ) ? 1 : 0;
        $out['countdown_date'] = sanitize_text_field( $merged['countdown_date'] ?? '' );
        
        $valid_styles = ['simple','boxed','circle','neon','glitch','pill'];
        $out['countdown_style'] = in_array( $merged['countdown_style'] ?? '', $valid_styles ) ? $merged['countdown_style'] : 'simple';
        
        $out['countdown_action'] = sanitize_key( $merged['countdown_action'] ?? 'message' );
        $out['countdown_finished_message'] = sanitize_text_field( $merged['countdown_finished_message'] ?? '' );
        $out['countdown_redirect_url'] = esc_url_raw( $merged['countdown_redirect_url'] ?? '' );

        $out['show_subscribe'] = ! empty( $merged['show_subscribe'] ) ? 1 : 0;
        $out['form_type'] = in_array( $merged['form_type'] ?? 'builtin', ['builtin','shortcode'] ) ? $merged['form_type'] : 'builtin';
        $out['form_shortcode'] = wp_kses_post( $merged['form_shortcode'] ?? '' );

        $out['subscribe_title'] = sanitize_text_field( $merged['subscribe_title'] ?? $defaults['subscribe_title'] );
        $out['subscribe_button'] = sanitize_text_field( $merged['subscribe_button'] ?? $defaults['subscribe_button'] );
        $out['gdpr_notice'] = sanitize_text_field( $merged['gdpr_notice'] ?? $defaults['gdpr_notice'] );

        // Form Fields
        // If we are saving the "Design/Modules" tab, $new['form_fields'] exists.
        // If we are saving "General", $new['form_fields'] is missing, so $merged has $current.
        $form_fields = isset($merged['form_fields']) && is_array($merged['form_fields']) ? $merged['form_fields'] : [];
        $sanitized_fields = [];
        foreach ($form_fields as $f) {
            if (empty($f['key'])) continue;
            
            $width = (string)($f['width'] ?? '100');
            $width = in_array($width, ['50','100']) ? $width : '100';

            $sanitized_fields[] = [
                'key' => sanitize_key($f['key']),
                'type' => sanitize_key($f['type']),
                'label' => sanitize_text_field($f['label']),
                'placeholder' => sanitize_text_field($f['placeholder'] ?? ''),
                'width' => $width,
                'required' => !empty($f['required']) ? 1 : 0,
                'is_default' => !empty($f['is_default']) ? 1 : 0,
            ];
        }
        if (empty($sanitized_fields)) $sanitized_fields = $defaults['form_fields'];
        $out['form_fields'] = $sanitized_fields;

        $out['show_social'] = ! empty( $merged['show_social'] ) ? 1 : 0;
        $social_icons = isset($merged['social_icons']) && is_array($merged['social_icons']) ? $merged['social_icons'] : [];
        $sanitized_social = [];
        foreach ($social_icons as $s) {
            if (empty($s['platform'])) continue;
            $sanitized_social[] = [
                'platform' => sanitize_key($s['platform']),
                'url' => esc_url_raw($s['url']),
            ];
        }
        $out['social_icons'] = $sanitized_social;

        $out['bypass_logged_in'] = ! empty( $merged['bypass_logged_in'] ) ? 1 : 0;
        
        // Roles
        $roles = $merged['bypass_roles'] ?? [];
        $roles = is_array( $roles ) ? array_map( 'sanitize_text_field', $roles ) : [];
        $out['bypass_roles'] = array_values( array_filter( $roles ) );

        // Specific Users
        $users = $merged['bypass_users'] ?? [];
        $users = is_array( $users ) ? array_map( 'absint', $users ) : [];
        $out['bypass_users'] = array_values( array_filter( $users ) );

        $out['ip_allowlist'] = sanitize_textarea_field( $merged['ip_allowlist'] ?? '' );
        $out['secret_bypass_key'] = sanitize_text_field( $merged['secret_bypass_key'] ?? '' );
        $out['secret_bypass_param'] = sanitize_key( $merged['secret_bypass_param'] ?? $defaults['secret_bypass_param'] );
        $out['password_protect'] = ! empty( $merged['password_protect'] ) ? 1 : 0;
        $out['access_password'] = sanitize_text_field( $merged['access_password'] ?? '' );

        $out['exclude_urls'] = sanitize_textarea_field( $merged['exclude_urls'] ?? $defaults['exclude_urls'] );
        $out['include_urls_only'] = sanitize_textarea_field( $merged['include_urls_only'] ?? '' );

        $out['store_subscribers'] = ! empty( $merged['store_subscribers'] ) ? 1 : 0;
        $out['notify_admin'] = ! empty( $merged['notify_admin'] ) ? 1 : 0;
        $out['admin_notify_email'] = sanitize_email( $merged['admin_notify_email'] ?? '' );
        $out['webhook_url'] = esc_url_raw( $merged['webhook_url'] ?? '' );
        $out['mailchimp_api_key'] = sanitize_text_field( $merged['mailchimp_api_key'] ?? '' );
        $out['mailchimp_list_id'] = sanitize_text_field( $merged['mailchimp_list_id'] ?? '' );
        $out['mailchimp_dc'] = sanitize_text_field( $merged['mailchimp_dc'] ?? '' );

        $out['rate_limit_per_ip_per_hour'] = max( 1, absint( $merged['rate_limit_per_ip_per_hour'] ?? $defaults['rate_limit_per_ip_per_hour'] ) );
        $out['honeypot_field'] = sanitize_key( $merged['honeypot_field'] ?? $defaults['honeypot_field'] );
        
        $out['clean_on_uninstall'] = ! empty( $merged['clean_on_uninstall'] ) ? 1 : 0;

        return $out;
    }

    public static function update( $new ) {
        $out = self::sanitize( $new );
        update_option( self::OPTION_KEY, $out, false );
        return $out;
    }
}