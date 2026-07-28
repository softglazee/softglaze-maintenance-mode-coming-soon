<?php
namespace SoftGlaze\MMCS;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Page builder compatibility.
 *
 * Ensures the SoftGlaze Landing Pages CPT can be edited with popular builders
 * like Elementor, Beaver Builder, SiteOrigin, Brizy, etc.
 *
 * Note: Many builders automatically support public post types. We also add
 * builder-specific filters when available, without requiring those builders.
 */
class Builders {

    public static function boot() {

        // Elementor (Free/Pro)
        add_filter( 'elementor_cpt_support', [ __CLASS__, 'add_post_type' ] );

        // Beaver Builder
        add_filter( 'fl_builder_post_types', [ __CLASS__, 'add_post_type' ] );

        // SiteOrigin Page Builder
        add_filter( 'siteorigin_panels_post_types', [ __CLASS__, 'add_post_type' ] );

        // Brizy
        add_filter( 'brizy_supported_post_types', [ __CLASS__, 'add_post_type' ] );

        // Remove landing CPT from WP core sitemaps (prevents indexing).
        add_filter( 'wp_sitemaps_post_types', [ __CLASS__, 'remove_from_sitemaps' ] );
    }

    /**
     * Add our landing CPT to a builder-supported post type list.
     *
     * @param array $post_types
     * @return array
     */
    public static function add_post_type( $post_types ) {
        if ( ! is_array( $post_types ) ) {
            $post_types = [];
        }
        if ( ! in_array( Landing::CPT, $post_types, true ) ) {
            $post_types[] = Landing::CPT;
        }
        return $post_types;
    }

    /**
     * Prevent the landing CPT from being included in WordPress sitemaps.
     *
     * @param array $post_types
     * @return array
     */
    public static function remove_from_sitemaps( $post_types ) {
        if ( isset( $post_types[ Landing::CPT ] ) ) {
            unset( $post_types[ Landing::CPT ] );
        }
        return $post_types;
    }
}
