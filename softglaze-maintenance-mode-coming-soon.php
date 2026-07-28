<?php
/**
 * Plugin Name: SoftGlaze Maintenance Mode & Coming Soon
 * Plugin URI: https://softglaze.com
 * Description: The ultimate Coming Soon & Maintenance Mode plugin. Features 10 vibrant templates, 20+ content presets, neon/glitch countdowns, specific user access controls, and a drag-and-drop form builder.
 * Version: 1.4.3
 * Author: SoftGlaze (Azhar Ali)
 * Author URI: https://softglaze.com/azhar-ali
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: softglaze-maintenance-mode-coming-soon
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Define Constants
define( 'SG_MMCS_VERSION', '1.4.3' );
define( 'SG_MMCS_PLUGIN_FILE', __FILE__ );
define( 'SG_MMCS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SG_MMCS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include Core Classes
require_once SG_MMCS_PLUGIN_DIR . 'includes/class-sg-mmcs-options.php';
require_once SG_MMCS_PLUGIN_DIR . 'includes/class-sg-mmcs-landing.php';
require_once SG_MMCS_PLUGIN_DIR . 'includes/class-sg-mmcs-builders.php';
require_once SG_MMCS_PLUGIN_DIR . 'includes/class-sg-mmcs-admin.php';
require_once SG_MMCS_PLUGIN_DIR . 'includes/class-sg-mmcs-front.php';
require_once SG_MMCS_PLUGIN_DIR . 'includes/class-sg-mmcs-subscribers.php';
require_once SG_MMCS_PLUGIN_DIR . 'includes/class-sg-mmcs-scheduler.php';

// Activation Hook
register_activation_hook( __FILE__, function() {
    \SoftGlaze\MMCS\Subscribers::create_table();
    \SoftGlaze\MMCS\Landing::register_cpt();
    
    // Set default options if not exists
    if ( ! get_option( \SoftGlaze\MMCS\Options::OPTION_KEY ) ) {
        update_option( \SoftGlaze\MMCS\Options::OPTION_KEY, \SoftGlaze\MMCS\Options::defaults() );
    }
    
    // Ensure schedule/automation is registered.
    \SoftGlaze\MMCS\Scheduler::activate();

    flush_rewrite_rules();
});

// Deactivation Hook
register_deactivation_hook( __FILE__, function() {
    \SoftGlaze\MMCS\Scheduler::deactivate();
    flush_rewrite_rules();
});


// Initialize Components
add_action( 'init', function() {
    \SoftGlaze\MMCS\Landing::register_cpt();
    \SoftGlaze\MMCS\Builders::boot();
    \SoftGlaze\MMCS\Scheduler::boot();
    \SoftGlaze\MMCS\Front::boot();
});

// Admin Hooks
if ( is_admin() ) {
    add_action( 'admin_menu', function() {
        \SoftGlaze\MMCS\Admin::register_menu();
    });
    add_action( 'admin_init', function() {
        \SoftGlaze\MMCS\Admin::register_settings();
    });
    add_action( 'init', function() {
        \SoftGlaze\MMCS\Admin::register_assets();
        \SoftGlaze\MMCS\Admin::register_ajax();
    });
}