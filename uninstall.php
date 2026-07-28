<?php
/**
 * Uninstall cleanup.
 *
 * @package SoftGlaze\MMCS
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$sg_mmcs_opts = get_option( 'sg_mmcs_options', [] );

if ( ! empty( $sg_mmcs_opts['clean_on_uninstall'] ) ) {
    delete_option( 'sg_mmcs_options' );

    global $wpdb;

    $sg_mmcs_table = $wpdb->prefix . 'sg_mmcs_subscribers';

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is fixed and not user input.
    $wpdb->query( "DROP TABLE IF EXISTS `{$sg_mmcs_table}`" );
}
