<?php
namespace SoftGlaze\MMCS;

if ( ! defined( 'ABSPATH' ) ) exit;

class Landing {

    const CPT = 'sg_landing';

    public static function register_cpt() {
        $labels = [
            'name' => __( 'SoftGlaze Landing Pages', 'softglaze-maintenance-mode-coming-soon' ),
            'singular_name' => __( 'Landing Page', 'softglaze-maintenance-mode-coming-soon' ),
            'add_new' => __( 'Add New', 'softglaze-maintenance-mode-coming-soon' ),
            'add_new_item' => __( 'Add New Landing Page', 'softglaze-maintenance-mode-coming-soon' ),
            'edit_item' => __( 'Edit Landing Page', 'softglaze-maintenance-mode-coming-soon' ),
            'new_item' => __( 'New Landing Page', 'softglaze-maintenance-mode-coming-soon' ),
            'view_item' => __( 'View Landing Page', 'softglaze-maintenance-mode-coming-soon' ),
            'search_items' => __( 'Search Landing Pages', 'softglaze-maintenance-mode-coming-soon' ),
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => false,
            'exclude_from_search' => true,
            'show_in_nav_menus' => false,
            'has_archive' => false,
            'rewrite' => false,
            'query_var' => false,
            'show_in_menu' => false,
            'show_ui' => true,
            'show_in_rest' => true,
            'supports' => [ 'title', 'editor', 'thumbnail', 'revisions', 'custom-fields' ],
            'capability_type' => 'page',
            'map_meta_cap' => true,
            'menu_icon' => 'dashicons-admin-page',
        ];

        register_post_type( self::CPT, $args );
    }

    public static function is_valid( $post_id ) {
        $post = get_post( $post_id );
        return $post && $post->post_type === self::CPT && $post->post_status !== 'trash';
    }
}
