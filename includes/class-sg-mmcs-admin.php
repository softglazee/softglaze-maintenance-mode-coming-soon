<?php
namespace SoftGlaze\MMCS;

if ( ! defined( 'ABSPATH' ) ) exit;

class Admin {

    /**
     * Enqueue admin assets defensively.
     *
     * Some environments alter admin screen hooks in a way that can prevent our
     * admin_enqueue_scripts screen detection from firing as expected. Calling this
     * from render callbacks guarantees the CSS/JS are present when the page loads.
     */
    private static function enqueue_admin_assets_now() {
        // Media + Thickbox (preview modal)
        wp_enqueue_media();
        add_thickbox();
        wp_enqueue_script( 'jquery-ui-datepicker' );

        $admin_css_ver = ( file_exists( SG_MMCS_PLUGIN_DIR . 'assets/css/admin.css' ) )
            ? (string) filemtime( SG_MMCS_PLUGIN_DIR . 'assets/css/admin.css' )
            : SG_MMCS_VERSION;

        if ( ! wp_style_is( 'sg-mmcs-admin', 'enqueued' ) ) {
            wp_enqueue_style( 'sg-mmcs-admin', SG_MMCS_PLUGIN_URL . 'assets/css/admin.css', [], $admin_css_ver );
        }

        $admin_js_ver = ( file_exists( SG_MMCS_PLUGIN_DIR . 'assets/js/admin.js' ) )
            ? (string) filemtime( SG_MMCS_PLUGIN_DIR . 'assets/js/admin.js' )
            : SG_MMCS_VERSION;

        if ( ! wp_script_is( 'sg-mmcs-admin', 'enqueued' ) ) {
            wp_enqueue_script(
                'sg-mmcs-admin',
                SG_MMCS_PLUGIN_URL . 'assets/js/admin.js',
                [ 'jquery', 'jquery-ui-sortable', 'jquery-ui-datepicker', 'thickbox' ],
                $admin_js_ver,
                true
            );
        }

        // Always localize (safe; overwrites the object with fresh values).
        $presets_cs = self::get_presets( 'coming_soon' );
        $presets_mm = self::get_presets( 'maintenance' );

        wp_localize_script( 'sg-mmcs-admin', 'sg_mmcs_data', [
            'presets_cs' => $presets_cs,
            'presets_mm' => $presets_mm,
            'preview_base' => home_url( '/' ),
            'preview_default_width' => 1200,
            'preview_default_height' => 760,
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'ajax_nonce' => wp_create_nonce( 'sg_mmcs_admin_actions' ),
        ] );
    }

    public static function register_menu() {
        add_menu_page(
            __( 'SoftGlaze Coming Soon', 'softglaze-maintenance-mode-coming-soon' ),
            __( 'SoftGlaze Coming Soon', 'softglaze-maintenance-mode-coming-soon' ),
            'manage_options',
            'sg-mmcs',
            [ __CLASS__, 'render_settings_page' ],
            'dashicons-admin-tools',
            58
        );

        add_submenu_page(
            'sg-mmcs',
            __( 'Settings', 'softglaze-maintenance-mode-coming-soon' ),
            __( 'Settings', 'softglaze-maintenance-mode-coming-soon' ),
            'manage_options',
            'sg-mmcs',
            [ __CLASS__, 'render_settings_page' ]
        );

        add_submenu_page(
            'sg-mmcs',
            __( 'Landing Pages', 'softglaze-maintenance-mode-coming-soon' ),
            __( 'Landing Pages', 'softglaze-maintenance-mode-coming-soon' ),
            'edit_pages',
            'edit.php?post_type=' . Landing::CPT
        );

        add_submenu_page(
            'sg-mmcs',
            __( 'Subscribers', 'softglaze-maintenance-mode-coming-soon' ),
            __( 'Subscribers', 'softglaze-maintenance-mode-coming-soon' ),
            'manage_options',
            'sg-mmcs-subscribers',
            [ __CLASS__, 'render_subscribers_page' ]
        );
    }

    public static function register_settings() {
        register_setting( 'sg_mmcs_group', Options::OPTION_KEY, [
            'type' => 'array',
            'sanitize_callback' => [ 'SoftGlaze\MMCS\Options', 'sanitize' ],
            'default' => Options::defaults(),
        ]);
    }

    public static function register_assets() {
        add_action( 'admin_enqueue_scripts', function( $hook ) {
            // Load only on this plugin's admin screens.
            // We detect via multiple signals because hook IDs vary between WP installs/plugins.
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Used only to detect current admin screen.
            $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

            $screen_id = '';
            if ( function_exists( 'get_current_screen' ) ) {
                $screen = get_current_screen();
                if ( $screen && ! empty( $screen->id ) ) {
                    $screen_id = (string) $screen->id;
                }
            }

            $is_plugin_screen = (
                strpos( (string) $hook, 'sg-mmcs' ) !== false ||
                strpos( (string) $page, 'sg-mmcs' ) !== false ||
                strpos( (string) $screen_id, 'sg-mmcs' ) !== false
            );

            if ( ! $is_plugin_screen ) {
                return;
            }
            
            wp_enqueue_media();
            wp_enqueue_script( 'jquery-ui-datepicker' );
            // WordPress core does not ship a jQuery UI theme stylesheet.
            // We intentionally avoid loading any external CDN CSS to remain WP.org compliant.

            // Thickbox is used for template previews.
            add_thickbox();

            $admin_css_ver = ( file_exists( SG_MMCS_PLUGIN_DIR . 'assets/css/admin.css' ) )
                ? (string) filemtime( SG_MMCS_PLUGIN_DIR . 'assets/css/admin.css' )
                : SG_MMCS_VERSION;
            wp_enqueue_style( 'sg-mmcs-admin', SG_MMCS_PLUGIN_URL . 'assets/css/admin.css', [], $admin_css_ver );

            
            // Pass Presets to JS
            $presets_cs = self::get_presets('coming_soon');
            $presets_mm = self::get_presets('maintenance');

            wp_enqueue_script(
                'sg-mmcs-admin',
                SG_MMCS_PLUGIN_URL . 'assets/js/admin.js',
                [ 'jquery', 'jquery-ui-sortable', 'jquery-ui-datepicker', 'thickbox' ],
                ( file_exists( SG_MMCS_PLUGIN_DIR . 'assets/js/admin.js' ) ) ? (string) filemtime( SG_MMCS_PLUGIN_DIR . 'assets/js/admin.js' ) : SG_MMCS_VERSION,
                true
            );
            
            wp_localize_script( 'sg-mmcs-admin', 'sg_mmcs_data', [
                'presets_cs' => $presets_cs,
                'presets_mm' => $presets_mm,
                'preview_base' => home_url( '/' ),
                'preview_default_width' => 1200,
                'preview_default_height' => 760
                ,
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'ajax_nonce' => wp_create_nonce( 'sg_mmcs_admin_actions' ),
            ]);
        });
    }

    /**
     * Register admin-only Ajax endpoints.
     */
    public static function register_ajax() {
        add_action( 'wp_ajax_sg_mmcs_generate_preview_link', [ __CLASS__, 'ajax_generate_preview_link' ] );
        add_action( 'wp_ajax_sg_mmcs_generate_bypass_link', [ __CLASS__, 'ajax_generate_bypass_link' ] );
    }

    public static function ajax_generate_preview_link() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', 'softglaze-maintenance-mode-coming-soon' ) ], 403 );
        }

        check_ajax_referer( 'sg_mmcs_admin_actions', 'nonce' );

        $opts = Options::get();
        if ( empty( $opts['preview_links_enabled'] ) ) {
            wp_send_json_error( [ 'message' => __( 'Preview links are disabled.', 'softglaze-maintenance-mode-coming-soon' ) ], 400 );
        }

        $mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'maintenance';
        $mode = in_array( $mode, [ 'coming_soon', 'maintenance' ], true ) ? $mode : 'maintenance';
        $template = isset( $_POST['template'] ) ? sanitize_text_field( wp_unslash( $_POST['template'] ) ) : '1';

        // Keep templates within known ranges.
        if ( 'coming_soon' === $mode ) {
            $template = in_array( $template, [ '1', '2', '3', '4' ], true ) ? $template : '1';
        } else {
            $template = in_array( $template, [ '1', '2', '3', '4', '5', '6' ], true ) ? $template : '1';
        }

        $expiry_min = isset( $_POST['expiry_minutes'] ) ? absint( wp_unslash( $_POST['expiry_minutes'] ) ) : (int) ( $opts['preview_link_expiry_minutes'] ?? 1440 );
        $expiry_min = max( 5, min( 10080, $expiry_min ) ); // up to 7 days.

        $token = wp_generate_password( 24, false, false );
        $data = [
            'mode' => $mode,
            'template' => $template,
            'created' => time(),
            'expires' => time() + ( $expiry_min * MINUTE_IN_SECONDS ),
            'user' => get_current_user_id(),
        ];

        set_transient( 'sg_mmcs_preview_token_' . $token, $data, $expiry_min * MINUTE_IN_SECONDS );

        $link = add_query_arg( [ 'sg_mmcs_preview_token' => rawurlencode( $token ) ], home_url( '/' ) );

        wp_send_json_success( [
            'link' => esc_url_raw( $link ),
            /* translators: %d: number of minutes */
            'hint' => sprintf( __( 'Valid for %d minutes.', 'softglaze-maintenance-mode-coming-soon' ), $expiry_min ),
        ] );
    }

    public static function ajax_generate_bypass_link() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', 'softglaze-maintenance-mode-coming-soon' ) ], 403 );
        }

        check_ajax_referer( 'sg_mmcs_admin_actions', 'nonce' );

        $opts = Options::get();
        if ( empty( $opts['bypass_links_enabled'] ) ) {
            wp_send_json_error( [ 'message' => __( 'Bypass links are disabled.', 'softglaze-maintenance-mode-coming-soon' ) ], 400 );
        }

        $hours = isset( $_POST['hours'] ) ? absint( wp_unslash( $_POST['hours'] ) ) : (int) ( $opts['bypass_link_duration_hours'] ?? 8 );
        $hours = max( 1, min( 168, $hours ) );

        // Token itself should be short-lived; cookie duration is controlled separately.
        $token_ttl = 30 * MINUTE_IN_SECONDS;
        $token = wp_generate_password( 24, false, false );
        $data = [
            'created' => time(),
            'expires' => time() + $token_ttl,
            'user' => get_current_user_id(),
            'cookie_hours' => $hours,
        ];

        set_transient( 'sg_mmcs_bypass_token_' . $token, $data, $token_ttl );
        $link = add_query_arg( [ 'sg_mmcs_bypass_token' => rawurlencode( $token ) ], home_url( '/' ) );

        wp_send_json_success( [
            'link' => esc_url_raw( $link ),
            /* translators: %d: number of hours */
            'hint' => sprintf( __( 'Cookie lasts %d hours. Link expires in 30 minutes.', 'softglaze-maintenance-mode-coming-soon' ), $hours ),
        ] );
    }

    // Helper: Content Presets
    private static function get_presets($type) {
        if ($type === 'coming_soon') {
            return [
                '1' => ['head' => 'Something Amazing is Coming', 'msg' => 'We are building something extraordinary. Stay tuned for the big reveal!'],
                '2' => ['head' => 'Under Construction', 'msg' => 'Our website is currently under construction. We will be here soon with our new awesome site.'],
                '3' => ['head' => 'We Are Launching Soon', 'msg' => 'Get ready! We are working hard to launch our new website. Subscribe to get notified.'],
                '4' => ['head' => 'Coming Soon', 'msg' => 'We are working on our website. Sign up for our newsletter to stay updated.'],
                '5' => ['head' => 'Get Ready', 'msg' => 'We are almost there. Something cool is coming your way very soon.'],
                '6' => ['head' => 'New Site In Progress', 'msg' => 'We are redesigning our website to give you the best experience. Check back soon!'],
                '7' => ['head' => 'Opening Soon', 'msg' => 'Our digital doors are opening soon. Don\'t miss out!'],
                '8' => ['head' => 'Work In Progress', 'msg' => 'We are busy crafting a new experience for you.'],
                '9' => ['head' => 'Stay Tuned', 'msg' => 'We are doing some maintenance on our site. It won\'t take long.'], // Generic
                '10'=> ['head' => 'Almost Ready', 'msg' => 'Just a few more touches and we are ready to go live!']
            ];
        } else {
            return [
                '1' => ['head' => 'Under Maintenance', 'msg' => 'We are currently performing scheduled maintenance. We will be back shortly.'],
                '2' => ['head' => 'We\'ll Be Right Back', 'msg' => 'Our site is getting a little tune-up. Thanks for your patience!'],
                '3' => ['head' => 'System Upgrade', 'msg' => 'We are upgrading our systems to serve you better. We should be back online soon.'],
                '4' => ['head' => 'Temporarily Unavailable', 'msg' => 'Sorry for the inconvenience. We are performing some necessary updates.'],
                '5' => ['head' => 'Brief Interruption', 'msg' => 'We are fixing a few things. The site will be back up in a few minutes.'],
                '6' => ['head' => 'Website Offline', 'msg' => 'This site is currently offline for maintenance. Please check back later.'],
                '7' => ['head' => 'Down for Maintenance', 'msg' => 'We are improving our website. We apologize for the downtime.'],
                '8' => ['head' => 'Making Improvements', 'msg' => 'We are working hard to improve our website content and speed.'],
                '9' => ['head' => 'Hang Tight!', 'msg' => 'We are doing some quick updates. We won\'t be long.'],
                '10'=> ['head' => 'Scheduled Downtime', 'msg' => 'This is a scheduled maintenance window. Services will resume shortly.']
            ];
        }
    }

    public static function render_settings_page() {
        if ( ! current_user_can('manage_options') ) return;

        // Guarantee admin CSS/JS are present even if another plugin/theme modifies admin hook IDs.
        self::enqueue_admin_assets_now();

        $opts = Options::get();
        $nonce = wp_create_nonce('sg_mmcs_admin');

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- UI state only; does not modify data.
        $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
        $tabs = [
            'general' => __( 'General', 'softglaze-maintenance-mode-coming-soon' ),
            'design' => __( 'Design & Content', 'softglaze-maintenance-mode-coming-soon' ),
            'modules' => __( 'Modules', 'softglaze-maintenance-mode-coming-soon' ),
            'automation' => __( 'Automation & Links', 'softglaze-maintenance-mode-coming-soon' ),
            'access' => __( 'Access Control', 'softglaze-maintenance-mode-coming-soon' ),
            'integrations' => __( 'Integrations', 'softglaze-maintenance-mode-coming-soon' ),
        ];
        if ( ! isset( $tabs[ $tab ] ) ) $tab = 'general';
        
        $mode_label = 'Disabled';
        $mode_class = 'off';
        if ($opts['mode'] === 'coming_soon') { $mode_label = 'Coming Soon (Active)'; $mode_class = 'coming-soon'; }
        if ($opts['mode'] === 'maintenance') { $mode_label = 'Maintenance (Active)'; $mode_class = 'maintenance'; }
        ?>
        <div class="wrap sg-mmcs-wrap">
            
            <div class="sg-mmcs-status-bar">
                <div class="sg-mmcs-status-info">
                    <span class="sg-mmcs-status-dot <?php echo esc_attr($mode_class); ?>"></span>
                    <span class="sg-mmcs-status-text">
                        <strong>Current Status:</strong> <?php echo esc_html($mode_label); ?>
                    </span>
                    <?php if ($opts['mode'] !== 'off'): ?>
                        <span class="sg-mmcs-http-badge">
                            HTTP <?php echo esc_html( $opts['mode'] === 'maintenance' ? '503' : '200' ); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="sg-mmcs-status-actions">
                     <a class="button" href="<?php echo esc_url( home_url('/') ); ?>" target="_blank"><?php esc_html_e('View Live Site', 'softglaze-maintenance-mode-coming-soon'); ?></a>
                     <a class="button button-primary" href="<?php echo esc_url( add_query_arg('sg_mmcs_preview','1', home_url('/') ) ); ?>" target="_blank"><?php esc_html_e('Preview Visitor View', 'softglaze-maintenance-mode-coming-soon'); ?></a>
                </div>
            </div>

            <nav class="sg-mmcs-nav">
                <?php foreach ( $tabs as $key => $label ):
                    $url = add_query_arg( [ 'page' => 'sg-mmcs', 'tab' => $key ], admin_url( 'admin.php' ) );
                    $cls = 'sg-mmcs-nav-item' . ( $tab === $key ? ' active' : '' );
                ?>
                    <a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $cls ); ?>"><?php echo esc_html( $label ); ?></a>
                <?php endforeach; ?>
            </nav>

            <?php settings_errors(); ?>

            <form method="post" action="options.php" class="sg-mmcs-form-layout">
                <?php settings_fields('sg_mmcs_group'); ?>
                <input type="hidden" name="_sg_mmcs_nonce" value="<?php echo esc_attr($nonce); ?>" />

                <div class="sg-mmcs-content">
                    
                    <?php if ( $tab === 'general' ) : ?>
                    <div class="sg-mmcs-card">
                        <div class="sg-mmcs-card-header">
                            <h2><?php esc_html_e('Activation', 'softglaze-maintenance-mode-coming-soon'); ?></h2>
                        </div>
                        <div class="sg-mmcs-card-body">
                            
                            <div class="sg-mmcs-activation-toggle">
                                <div class="sg-mmcs-toggle-row">
                                    <label class="sg-mmcs-radio-card <?php echo $opts['mode'] === 'off' ? 'active' : ''; ?>" data-val="off">
                                        <input type="radio" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[mode]" value="off" <?php checked($opts['mode'], 'off'); ?>>
                                        <div class="sg-content">
                                            <strong>Disabled</strong>
                                            <span>Site is live for everyone.</span>
                                        </div>
                                    </label>

                                    <label class="sg-mmcs-radio-card <?php echo $opts['mode'] === 'coming_soon' ? 'active' : ''; ?>" data-val="coming_soon">
                                        <input type="radio" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[mode]" value="coming_soon" <?php checked($opts['mode'], 'coming_soon'); ?>>
                                        <div class="sg-content">
                                            <strong>Coming Soon</strong>
                                            <span>Returns HTTP 200 OK.</span>
                                        </div>
                                    </label>

                                    <label class="sg-mmcs-radio-card <?php echo $opts['mode'] === 'maintenance' ? 'active' : ''; ?>" data-val="maintenance">
                                        <input type="radio" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[mode]" value="maintenance" <?php checked($opts['mode'], 'maintenance'); ?>>
                                        <div class="sg-content">
                                            <strong>Maintenance</strong>
                                            <span>Returns HTTP 503.</span>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div class="sg-mmcs-info-box">
                                <div class="sg-info-content">
                                    <strong>What is the difference?</strong>
                                    <ul>
                                        <li><strong>HTTP 200 (Coming Soon):</strong> Tells Google "This page is ready to be indexed." Use this when launching a new site.</li>
                                        <li><strong>HTTP 503 (Maintenance):</strong> Tells Google "We are temporarily down, check back later." Use this for short-term updates to preserve SEO rankings.</li>
                                    </ul>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="sg-mmcs-card">
                        <div class="sg-mmcs-card-header">
                            <h2><?php esc_html_e('Page Source', 'softglaze-maintenance-mode-coming-soon'); ?></h2>
                        </div>
                        <div class="sg-mmcs-card-body">
                             <div class="sg-mmcs-field-group">
                                <label class="sg-mmcs-radio-block">
                                    <input type="radio" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[page_source]" value="template" <?php checked($opts['page_source'], 'template'); ?>>
                                    <span>
                                        <strong><?php esc_html_e('Built-in Template (Recommended)', 'softglaze-maintenance-mode-coming-soon'); ?></strong>
                                        <small><?php esc_html_e('Customize the design in the "Design" tab.', 'softglaze-maintenance-mode-coming-soon'); ?></small>
                                    </span>
                                </label>

                                <label class="sg-mmcs-radio-block">
                                    <input type="radio" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[page_source]" value="landing" <?php checked($opts['page_source'], 'landing'); ?>>
                                    <span><strong><?php esc_html_e('SoftGlaze Landing Page (Block Editor)', 'softglaze-maintenance-mode-coming-soon'); ?></strong></span>
                                </label>
                                <div class="sg-mmcs-sub-option">
                                    <?php $landings = get_posts([ 'post_type' => Landing::CPT, 'posts_per_page' => 50 ]); ?>
                                    <select name="<?php echo esc_attr(Options::OPTION_KEY); ?>[landing_id]">
                                        <option value="0"><?php esc_html_e('— Select Landing Page —', 'softglaze-maintenance-mode-coming-soon'); ?></option>
                                        <?php foreach ($landings as $p): ?>
                                            <option value="<?php echo (int)$p->ID; ?>" <?php selected($opts['landing_id'], $p->ID); ?>>
                                                <?php echo esc_html( $p->post_title ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <label class="sg-mmcs-radio-block">
                                    <input type="radio" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[page_source]" value="page" <?php checked($opts['page_source'], 'page'); ?>>
                                    <span><strong><?php esc_html_e('Existing WordPress Page', 'softglaze-maintenance-mode-coming-soon'); ?></strong></span>
                                </label>
                                <div class="sg-mmcs-sub-option">
                                    <?php
                                    wp_dropdown_pages(
                                        [
                                            'name'              => esc_attr( Options::OPTION_KEY . '[page_id]' ),
                                            'show_option_none'  => esc_html__( '— Select Page —', 'softglaze-maintenance-mode-coming-soon' ),
                                            'option_none_value' => 0,
                                            'selected'          => absint( $opts['page_id'] ),
                                        ]
                                    );
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="sg-mmcs-card">
                        <div class="sg-mmcs-card-header">
                            <h2><?php esc_html_e('HTTP & SEO', 'softglaze-maintenance-mode-coming-soon'); ?></h2>
                        </div>
                        <div class="sg-mmcs-card-body">
                            <div class="sg-mmcs-row">
                                <label class="sg-mmcs-field-group">
                                    <span class="sg-mmcs-label"><?php esc_html_e('HTTP Status', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                                    <select name="<?php echo esc_attr(Options::OPTION_KEY); ?>[http_status]">
                                        <option value="200" <?php selected($opts['http_status'], '200'); ?>>200 OK (Indexable)</option>
                                        <option value="503" <?php selected($opts['http_status'], '503'); ?>>503 Service Unavailable (Maintenance)</option>
                                    </select>
                                    <p class="description"><?php esc_html_e('Override the response status code. Recommended: 200 for Coming Soon, 503 for Maintenance.', 'softglaze-maintenance-mode-coming-soon'); ?></p>
                                </label>

                                <label class="sg-mmcs-field-group">
                                    <span class="sg-mmcs-label"><?php esc_html_e('Retry-After (minutes)', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                                    <input type="number" min="1" step="1" class="small-text" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[retry_after]" value="<?php echo esc_attr($opts['retry_after']); ?>">
                                    <p class="description"><?php esc_html_e('Only used when HTTP Status is 503.', 'softglaze-maintenance-mode-coming-soon'); ?></p>
                                </label>
                            </div>

                            <hr>

                            <label class="sg-mmcs-checkbox-lg">
                                <input type="hidden" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[noindex]" value="0">
                                <input type="checkbox" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[noindex]" value="1" <?php checked($opts['noindex'], 1); ?>>
                                <span><?php esc_html_e('Add noindex / nofollow (prevent indexing)', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                            </label>
                            <p class="description"><?php esc_html_e('Useful while building a site. Disable if you want search engines to index your Coming Soon page.', 'softglaze-maintenance-mode-coming-soon'); ?></p>
                        </div>
                    </div>

                    <div class="sg-mmcs-card">
                        <div class="sg-mmcs-card-header">
                            <h2><?php esc_html_e('Uninstall', 'softglaze-maintenance-mode-coming-soon'); ?></h2>
                        </div>
                        <div class="sg-mmcs-card-body">
                            <label class="sg-mmcs-checkbox-lg">
                                <input type="hidden" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[clean_on_uninstall]" value="0">
                                <input type="checkbox" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[clean_on_uninstall]" value="1" <?php checked($opts['clean_on_uninstall'], 1); ?>>
                                <span><?php esc_html_e('Delete plugin data on uninstall', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                            </label>
                            <p class="description"><?php esc_html_e('If enabled, uninstalling will remove all settings and subscriber data.', 'softglaze-maintenance-mode-coming-soon'); ?></p>
                        </div>
                    </div>

                    <div class="sg-mmcs-card">
                        <div class="sg-mmcs-card-header"><h2><?php esc_html_e('Quick Preview Links', 'softglaze-maintenance-mode-coming-soon'); ?></h2></div>
                        <div class="sg-mmcs-card-body">
                            <p class="description"><?php esc_html_e('Generate shareable preview links (no admin login required). Useful for clients/team approval before you apply a template.', 'softglaze-maintenance-mode-coming-soon'); ?></p>

                            <div class="sg-mmcs-toggle-box">
                                <label class="sg-mmcs-checkbox-lg">
                                    <input type="hidden" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[preview_links_enabled]" value="0">
                                    <input type="checkbox" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[preview_links_enabled]" value="1" <?php checked( $opts['preview_links_enabled'], 1 ); ?>>
                                    <span><?php esc_html_e('Enable preview links', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                                </label>
                            </div>

                            <div class="sg-mmcs-row" style="align-items:flex-end; gap:12px;">
                                <label class="sg-mmcs-field-group" style="min-width:180px;">
                                    <span class="sg-mmcs-label"><?php esc_html_e('Mode', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                                    <select class="sg-mmcs-preview-mode">
                                        <option value="coming_soon"><?php esc_html_e('Coming Soon', 'softglaze-maintenance-mode-coming-soon'); ?></option>
                                        <option value="maintenance"><?php esc_html_e('Maintenance', 'softglaze-maintenance-mode-coming-soon'); ?></option>
                                    </select>
                                </label>

                                <label class="sg-mmcs-field-group" style="min-width:180px;">
                                    <span class="sg-mmcs-label"><?php esc_html_e('Template', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                                    <select class="sg-mmcs-preview-template">
                                        <optgroup label="<?php echo esc_attr( __( 'Coming Soon', 'softglaze-maintenance-mode-coming-soon' ) ); ?>">
                                            <option value="1">1</option>
                                            <option value="2">2</option>
                                            <option value="3">3</option>
                                            <option value="4">4</option>
                                        </optgroup>
                                        <optgroup label="<?php echo esc_attr( __( 'Maintenance', 'softglaze-maintenance-mode-coming-soon' ) ); ?>">
                                            <option value="1">1</option>
                                            <option value="2">2</option>
                                            <option value="3">3</option>
                                            <option value="4">4</option>
                                            <option value="5">5</option>
                                            <option value="6">6</option>
                                        </optgroup>
                                    </select>
                                </label>

                                <label class="sg-mmcs-field-group" style="min-width:180px;">
                                    <span class="sg-mmcs-label"><?php esc_html_e('Expiry (minutes)', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                                    <input type="number" min="5" step="1" class="sg-mmcs-preview-expiry" value="<?php echo esc_attr( (int) $opts['preview_link_expiry_minutes'] ); ?>">
                                </label>

                                <button type="button" class="button button-primary sg-mmcs-generate-preview-link" <?php disabled( (int) $opts['preview_links_enabled'], 0 ); ?>><?php esc_html_e('Generate link', 'softglaze-maintenance-mode-coming-soon'); ?></button>
                            </div>

                            <div class="sg-mmcs-row" style="gap:10px; margin-top:10px;">
                                <input type="url" class="large-text sg-mmcs-generated-link" value="" readonly placeholder="<?php echo esc_attr( __( 'Your preview link will appear here…', 'softglaze-maintenance-mode-coming-soon' ) ); ?>">
                                <button type="button" class="button sg-mmcs-copy-link"><?php esc_html_e('Copy', 'softglaze-maintenance-mode-coming-soon'); ?></button>
                            </div>
                            <p class="description sg-mmcs-link-hint" style="margin-top:6px;"></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ( $tab === 'design' ) : ?>
                    <div class="sg-mmcs-card">
                        <div class="sg-mmcs-card-header"><h2><?php esc_html_e('Layout & Templates', 'softglaze-maintenance-mode-coming-soon'); ?></h2></div>
                        <div class="sg-mmcs-card-body">

                            <div class="sg-mmcs-grid-2 sg-mmcs-template-pickers">
                                <?php
                                    $logo_preview = $opts['logo_url'] ?: get_site_icon_url(96);
                                    $bg_img_css = ! empty($opts['background_image']) ? "url('" . esc_url($opts['background_image']) . "')" : 'none';
                                ?>
                                <div>
                                    <h3 class="sg-mmcs-subtitle"><?php esc_html_e('Coming Soon Layout', 'softglaze-maintenance-mode-coming-soon'); ?></h3>
                                    <p class="description" style="margin-top:0;"><?php esc_html_e('Pick a layout by clicking a preview card.', 'softglaze-maintenance-mode-coming-soon'); ?></p>

                                    <div class="sg-tmpl-grid"
                                         data-setting="coming_soon_template"
                                         style="--sg-prev-bg-image: <?php echo esc_attr($bg_img_css); ?>; --sg-prev-bg-color: <?php echo esc_attr($opts['background_color']); ?>; --sg-prev-text: <?php echo esc_attr($opts['text_color']); ?>; --sg-prev-accent: <?php echo esc_attr($opts['accent_color']); ?>;">
                                        <?php
                                            $cs_templates = [
                                                '1' => [ 'Split Screen', 'Modern' ],
                                                '2' => [ 'Minimal Center', 'Clean' ],
                                                '3' => [ 'Hero Overlay', 'Bold' ],
                                                '4' => [ 'Glassmorphism', 'Frosted' ],
                                            ];
                                            foreach ( $cs_templates as $val => $meta ) :
                                                $active = (string)$opts['coming_soon_template'] === (string)$val ? ' active' : '';
                                        ?>
                                            <label class="sg-tmpl-card<?php echo esc_attr($active); ?>" data-template="<?php echo esc_attr($val); ?>">
                                                <input type="radio" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[coming_soon_template]" value="<?php echo esc_attr($val); ?>" <?php checked($opts['coming_soon_template'], (string)$val); ?>>
                                                <div class="sg-tmpl-thumb sg-tmpl-cs sg-tmpl-cs-<?php echo esc_attr($val); ?>">
                                                    <div class="sg-tmpl-actions">
                                                        <button type="button" class="button button-small sg-tmpl-preview-btn" data-preview-mode="coming_soon" data-preview-template="<?php echo esc_attr( $val ); ?>"><?php esc_html_e( 'Preview', 'softglaze-maintenance-mode-coming-soon' ); ?></button>
                                                    </div>
                                                    <div class="sg-tmpl-top">
                                                        <?php if ( $logo_preview ) : ?>
                                                            <span class="sg-tmpl-logo"><img src="<?php echo esc_url($logo_preview); ?>" alt=""></span>
                                                        <?php else : ?>
                                                            <span class="sg-tmpl-logo sg-tmpl-logo-fallback"></span>
                                                        <?php endif; ?>
                                                        <?php
                                                            $features = [];
                                                            if ( ! empty( $opts['show_countdown'] ) ) { $features[] = __( 'Countdown', 'softglaze-maintenance-mode-coming-soon' ); }
                                                            if ( ! empty( $opts['show_subscribe'] ) ) { $features[] = __( 'Form', 'softglaze-maintenance-mode-coming-soon' ); }
                                                            if ( ! empty( $opts['show_social'] ) ) { $features[] = __( 'Social', 'softglaze-maintenance-mode-coming-soon' ); }
                                                        ?>
                                                        <span class="sg-tmpl-features"><?php echo esc_html( implode( ' · ', $features ) ); ?></span>
                                                    </div>
                                                    <div class="sg-tmpl-preview">
                                                        <div class="sg-tmpl-preview-headline">
                                                            <?php echo esc_html( $opts['headline'] ? $opts['headline'] : __( 'We are launching soon', 'softglaze-maintenance-mode-coming-soon' ) ); ?>
                                                        </div>
                                                        <div class="sg-tmpl-preview-message">
                                                            <?php echo esc_html( wp_trim_words( wp_strip_all_tags( (string) $opts['message'] ), 12, '…' ) ); ?>
                                                        </div>
                                                        <?php if ( ! empty( $opts['show_subscribe'] ) ) : ?>
                                                            <div class="sg-tmpl-preview-cta"><?php echo esc_html( $opts['subscribe_button'] ); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="sg-tmpl-meta">
                                                    <strong><?php echo esc_html($meta[0]); ?></strong>
                                                    <span><?php echo esc_html($meta[1]); ?></span>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div>
                                    <h3 class="sg-mmcs-subtitle"><?php esc_html_e('Maintenance Layout', 'softglaze-maintenance-mode-coming-soon'); ?></h3>
                                    <p class="description" style="margin-top:0;"><?php esc_html_e('Pick a layout by clicking a preview card.', 'softglaze-maintenance-mode-coming-soon'); ?></p>

                                    <div class="sg-tmpl-grid"
                                         data-setting="maintenance_template"
                                         style="--sg-prev-bg-image: <?php echo esc_attr($bg_img_css); ?>; --sg-prev-bg-color: <?php echo esc_attr($opts['background_color']); ?>; --sg-prev-text: <?php echo esc_attr($opts['text_color']); ?>; --sg-prev-accent: <?php echo esc_attr($opts['accent_color']); ?>;">
                                        <?php
                                            $mm_templates = [
                                                '1' => [ 'Graphic Left', 'Split' ],
                                                '2' => [ 'Clean Card', 'Centered' ],
                                                '3' => [ 'Dark Mode', 'Neon' ],
                                                '4' => [ 'Simple Text', 'Minimal' ],
                                                '5' => [ 'Gradient Spotlight', 'New' ],
                                                '6' => [ 'Glass Panel', 'New' ],
                                            ];
                                            foreach ( $mm_templates as $val => $meta ) :
                                                $active = (string)$opts['maintenance_template'] === (string)$val ? ' active' : '';
                                        ?>
                                            <label class="sg-tmpl-card<?php echo esc_attr($active); ?>" data-template="<?php echo esc_attr($val); ?>">
                                                <input type="radio" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[maintenance_template]" value="<?php echo esc_attr($val); ?>" <?php checked($opts['maintenance_template'], (string)$val); ?>>
                                                <div class="sg-tmpl-thumb sg-tmpl-mm sg-tmpl-mm-<?php echo esc_attr($val); ?>">
                                                    <div class="sg-tmpl-actions">
                                                        <button type="button" class="button button-small sg-tmpl-preview-btn" data-preview-mode="maintenance" data-preview-template="<?php echo esc_attr( $val ); ?>"><?php esc_html_e( 'Preview', 'softglaze-maintenance-mode-coming-soon' ); ?></button>
                                                    </div>
                                                    <div class="sg-tmpl-top">
                                                        <?php if ( $logo_preview ) : ?>
                                                            <span class="sg-tmpl-logo"><img src="<?php echo esc_url($logo_preview); ?>" alt=""></span>
                                                        <?php else : ?>
                                                            <span class="sg-tmpl-logo sg-tmpl-logo-fallback"></span>
                                                        <?php endif; ?>
                                                        <?php
                                                            $features = [];
                                                            if ( ! empty( $opts['show_countdown'] ) ) { $features[] = __( 'Countdown', 'softglaze-maintenance-mode-coming-soon' ); }
                                                            if ( ! empty( $opts['show_subscribe'] ) ) { $features[] = __( 'Form', 'softglaze-maintenance-mode-coming-soon' ); }
                                                            if ( ! empty( $opts['show_social'] ) ) { $features[] = __( 'Social', 'softglaze-maintenance-mode-coming-soon' ); }
                                                        ?>
                                                        <span class="sg-tmpl-features"><?php echo esc_html( implode( ' · ', $features ) ); ?></span>
                                                    </div>
                                                    <div class="sg-tmpl-preview">
                                                        <div class="sg-tmpl-preview-headline">
                                                            <?php echo esc_html( $opts['headline'] ? $opts['headline'] : __( 'We are doing maintenance', 'softglaze-maintenance-mode-coming-soon' ) ); ?>
                                                        </div>
                                                        <div class="sg-tmpl-preview-message">
                                                            <?php echo esc_html( wp_trim_words( wp_strip_all_tags( (string) $opts['message'] ), 12, '…' ) ); ?>
                                                        </div>
                                                        <?php if ( ! empty( $opts['show_subscribe'] ) ) : ?>
                                                            <div class="sg-tmpl-preview-cta"><?php echo esc_html( $opts['subscribe_button'] ); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="sg-tmpl-meta">
                                                    <strong><?php echo esc_html($meta[0]); ?></strong>
                                                    <span><?php echo esc_html($meta[1]); ?></span>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="sg-mmcs-grid-2">
                        <div class="sg-mmcs-column">
                            <div class="sg-mmcs-card">
                                <div class="sg-mmcs-card-header"><h2><?php esc_html_e('Content', 'softglaze-maintenance-mode-coming-soon'); ?></h2></div>
                                <div class="sg-mmcs-card-body">
                                    <label class="sg-mmcs-field-group">
                                        <span class="sg-mmcs-label"><?php esc_html_e('Load a Preset', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                                        <select id="sg-content-preset" class="large-text" data-mode="<?php echo esc_attr($opts['mode']); ?>">
    <option value=""><?php esc_html_e('— Select a Preset to Auto-fill —', 'softglaze-maintenance-mode-coming-soon'); ?></option>
    <?php
    $mode = $opts['mode'] ?? 'off';
    if ( $mode === 'maintenance' ) : ?>
        <optgroup label="<?php echo esc_attr__('Maintenance', 'softglaze-maintenance-mode-coming-soon'); ?>">
            <?php for ( $i = 1; $i <= 10; $i++ ) : ?>
                <option value="<?php echo esc_attr( 'mm_' . $i ); ?>">
                    <?php // translators: %d: preset number.
                    echo esc_html( sprintf( __( 'Maintenance #%d', 'softglaze-maintenance-mode-coming-soon' ), $i ) ); ?>
                </option>
            <?php endfor; ?>
        </optgroup>
    <?php elseif ( $mode === 'coming_soon' ) : ?>
        <optgroup label="<?php echo esc_attr__('Coming Soon', 'softglaze-maintenance-mode-coming-soon'); ?>">
            <?php for ( $i = 1; $i <= 10; $i++ ) : ?>
                <option value="<?php echo esc_attr( 'cs_' . $i ); ?>">
                    <?php // translators: %d: preset number.
                    echo esc_html( sprintf( __( 'Coming Soon #%d', 'softglaze-maintenance-mode-coming-soon' ), $i ) ); ?>
                </option>
            <?php endfor; ?>
        </optgroup>
    <?php else : ?>
        <optgroup label="<?php echo esc_attr__('Coming Soon', 'softglaze-maintenance-mode-coming-soon'); ?>">
            <?php for ( $i = 1; $i <= 10; $i++ ) : ?>
                <option value="<?php echo esc_attr( 'cs_' . $i ); ?>">
                    <?php // translators: %d: preset number.
                    echo esc_html( sprintf( __( 'Coming Soon #%d', 'softglaze-maintenance-mode-coming-soon' ), $i ) ); ?>
                </option>
            <?php endfor; ?>
        </optgroup>
        <optgroup label="<?php echo esc_attr__('Maintenance', 'softglaze-maintenance-mode-coming-soon'); ?>">
            <?php for ( $i = 1; $i <= 10; $i++ ) : ?>
                <option value="<?php echo esc_attr( 'mm_' . $i ); ?>">
                    <?php // translators: %d: preset number.
                    echo esc_html( sprintf( __( 'Maintenance #%d', 'softglaze-maintenance-mode-coming-soon' ), $i ) ); ?>
                </option>
            <?php endfor; ?>
        </optgroup>
    <?php endif; ?>
</select>
                                    </label>
                                    <hr>

                                    <label class="sg-mmcs-field-group">
                                        <span class="sg-mmcs-label"><?php esc_html_e('Logo URL', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                                        <div class="sg-mmcs-row">
                                            <input type="url" class="large-text sg-img-input" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[logo_url]" value="<?php echo esc_attr($opts['logo_url']); ?>" placeholder="https://...">
                                            <button type="button" class="button sg-upload-btn"><?php esc_html_e('Upload', 'softglaze-maintenance-mode-coming-soon'); ?></button>
                                        </div>
                                        <p class="description"><?php esc_html_e('Leave empty to use your site\'s Customizer Logo or Site Icon.', 'softglaze-maintenance-mode-coming-soon'); ?></p>
                                    </label>

                                    <label class="sg-mmcs-field-group">
                                        <span class="sg-mmcs-label"><?php esc_html_e('Headline', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                                        <input type="text" id="sg-opt-headline" class="large-text" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[headline]" value="<?php echo esc_attr($opts['headline']); ?>">
                                    </label>

                                    <label class="sg-mmcs-field-group">
                                        <span class="sg-mmcs-label"><?php esc_html_e('Message', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                                        <textarea id="sg-opt-message" rows="5" class="large-text" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[message]"><?php echo esc_textarea($opts['message']); ?></textarea>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="sg-mmcs-column">
                            <div class="sg-mmcs-card">
                                <div class="sg-mmcs-card-header"><h2><?php esc_html_e('Appearance', 'softglaze-maintenance-mode-coming-soon'); ?></h2></div>
                                <div class="sg-mmcs-card-body">
                                    <div class="sg-mmcs-toggle-box">
                                        <label class="sg-mmcs-checkbox-lg">
                                            <input type="hidden" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[inherit_fonts]" value="0">
                                            <input type="checkbox" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[inherit_fonts]" value="1" <?php checked($opts['inherit_fonts'], 1); ?>>
                                            <span><?php esc_html_e('Inherit Theme Fonts', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                                        </label>
                                        <p class="description"><?php esc_html_e('Use your site\'s active fonts. Uncheck to use plugin default (Poppins).', 'softglaze-maintenance-mode-coming-soon'); ?></p>
                                    </div>
                                    
                                    <div class="sg-mmcs-toggle-box">
                                        <label class="sg-mmcs-checkbox-lg">
                                            <input type="hidden" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[inherit_colors]" value="0">
                                            <input type="checkbox" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[inherit_colors]" value="1" <?php checked($opts['inherit_colors'], 1); ?>>
                                            <span><?php esc_html_e('Inherit Theme Colors', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                                        </label>
                                        <p class="description"><?php esc_html_e('Use your site\'s global colors. Uncheck to set custom colors below.', 'softglaze-maintenance-mode-coming-soon'); ?></p>
                                    </div>

                                    <hr>

                                    <div class="sg-mmcs-row">
                                        <label class="sg-mmcs-field-group">
                                            <span class="sg-mmcs-label">Background</span>
                                            <input type="color" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[background_color]" value="<?php echo esc_attr($opts['background_color']); ?>">
                                        </label>
                                        <label class="sg-mmcs-field-group">
                                            <span class="sg-mmcs-label">Text</span>
                                            <input type="color" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[text_color]" value="<?php echo esc_attr($opts['text_color']); ?>">
                                        </label>
                                        <label class="sg-mmcs-field-group">
                                            <span class="sg-mmcs-label">Accent</span>
                                            <input type="color" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[accent_color]" value="<?php echo esc_attr($opts['accent_color']); ?>">
                                        </label>
                                    </div>

                                    <label class="sg-mmcs-field-group">
                                        <span class="sg-mmcs-label"><?php esc_html_e('Background Image', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                                        <div class="sg-mmcs-row">
                                            <input type="url" class="large-text sg-img-input" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[background_image]" value="<?php echo esc_attr($opts['background_image']); ?>" placeholder="https://...">
                                            <button type="button" class="button sg-upload-btn"><?php esc_html_e('Select', 'softglaze-maintenance-mode-coming-soon'); ?></button>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ( $tab === 'modules' ) : ?>
                    <div class="sg-mmcs-card">
                        <div class="sg-mmcs-card-header">
                            <h2><?php esc_html_e('Countdown Timer', 'softglaze-maintenance-mode-coming-soon'); ?></h2>
                        </div>
                        <div class="sg-mmcs-card-body">
                            <label class="sg-mmcs-checkbox-lg">
                                <input type="hidden" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[show_countdown]" value="0">
                                <input type="checkbox" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[show_countdown]" value="1" <?php checked($opts['show_countdown'], 1); ?>>
                                <span><?php esc_html_e('Enable Countdown', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                            </label>

                            <div class="sg-mmcs-indent">
                                <label class="sg-mmcs-field-group">
                                    <span class="sg-mmcs-label"><?php esc_html_e('End Date', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                                    <input type="text" class="regular-text sg-datepicker" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[countdown_date]" value="<?php echo esc_attr($opts['countdown_date']); ?>" placeholder="Select Date" autocomplete="off">
                                </label>

                                <div class="sg-mmcs-row">
                                    <label class="sg-mmcs-field-group">
                                        <span class="sg-mmcs-label"><?php esc_html_e('Style', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                                        <select name="<?php echo esc_attr(Options::OPTION_KEY); ?>[countdown_style]">
                                            <option value="simple" <?php selected($opts['countdown_style'], 'simple'); ?>>Simple Text</option>
                                            <option value="boxed" <?php selected($opts['countdown_style'], 'boxed'); ?>>Boxed Cards</option>
                                            <option value="circle" <?php selected($opts['countdown_style'], 'circle'); ?>>Circles</option>
                                            <option value="neon" <?php selected($opts['countdown_style'], 'neon'); ?>>Neon Glow (New)</option>
                                            <option value="glitch" <?php selected($opts['countdown_style'], 'glitch'); ?>>Cyber Glitch (New)</option>
                                            <option value="pill" <?php selected($opts['countdown_style'], 'pill'); ?>>Modern Pill (New)</option>
                                        </select>
                                    </label>
                                    
                                    <label class="sg-mmcs-field-group">
                                        <span class="sg-mmcs-label"><?php esc_html_e('When finished:', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                                        <select name="<?php echo esc_attr(Options::OPTION_KEY); ?>[countdown_action]">
                                            <option value="message" <?php selected($opts['countdown_action'], 'message'); ?>>Show Message</option>
                                            <option value="redirect" <?php selected($opts['countdown_action'], 'redirect'); ?>>Redirect URL</option>
                                            <option value="hide" <?php selected($opts['countdown_action'], 'hide'); ?>>Hide Timer</option>
                                        </select>
                                    </label>
                                </div>

<div class="sg-mmcs-row sg-countdown-finish-fields" style="margin-top:12px;">
    <label class="sg-mmcs-field-group sg-countdown-msg-field">
        <span class="sg-mmcs-label-sm"><?php esc_html_e('Finished Message', 'softglaze-maintenance-mode-coming-soon'); ?></span>
        <input type="text" class="regular-text" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[countdown_finished_message]" value="<?php echo esc_attr($opts['countdown_finished_message']); ?>" placeholder="<?php esc_attr_e('We are live!', 'softglaze-maintenance-mode-coming-soon'); ?>">
        <p class="description"><?php esc_html_e('Shown when the countdown ends (if action = Show Message).', 'softglaze-maintenance-mode-coming-soon'); ?></p>
    </label>

    <label class="sg-mmcs-field-group sg-countdown-redirect-field">
        <span class="sg-mmcs-label-sm"><?php esc_html_e('Redirect URL', 'softglaze-maintenance-mode-coming-soon'); ?></span>
        <input type="url" class="regular-text" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[countdown_redirect_url]" value="<?php echo esc_attr($opts['countdown_redirect_url']); ?>" placeholder="https://yoursite.com/">
        <p class="description"><?php esc_html_e('Used when action = Redirect URL.', 'softglaze-maintenance-mode-coming-soon'); ?></p>
    </label>
</div>
                            </div>
                        </div>
                    </div>

                    <div class="sg-mmcs-card">
                        <div class="sg-mmcs-card-header">
                            <h2><?php esc_html_e('Subscriber Form', 'softglaze-maintenance-mode-coming-soon'); ?></h2>
                        </div>
                        <div class="sg-mmcs-card-body">
                            <label class="sg-mmcs-checkbox-lg">
                                <input type="hidden" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[show_subscribe]" value="0">
                                <input type="checkbox" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[show_subscribe]" value="1" <?php checked($opts['show_subscribe'], 1); ?>>
                                <span><?php esc_html_e('Enable Subscription Form', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                            </label>
                            
                            <div class="sg-mmcs-indent">
                                <label class="sg-mmcs-field-group">
                                    <span class="sg-mmcs-label"><?php esc_html_e( 'Form Type', 'softglaze-maintenance-mode-coming-soon' ); ?></span>
                                    <div class="sg-mmcs-row">
                                        <label><input type="radio" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[form_type]" value="builtin" <?php checked($opts['form_type'], 'builtin'); ?> onclick="jQuery('.sg-form-panel').hide();jQuery('#sg-panel-builtin').show();"> <?php esc_html_e( 'Built-in Builder', 'softglaze-maintenance-mode-coming-soon' ); ?></label>
                                        <label><input type="radio" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[form_type]" value="shortcode" <?php checked($opts['form_type'], 'shortcode'); ?> onclick="jQuery('.sg-form-panel').hide();jQuery('#sg-panel-shortcode').show();"> <?php esc_html_e( 'Shortcode', 'softglaze-maintenance-mode-coming-soon' ); ?></label>
                                    </div>
                                </label>

                                <div id="sg-panel-shortcode" class="sg-form-panel" style="<?php echo esc_attr( $opts['form_type'] === 'shortcode' ? '' : 'display:none;' ); ?>">
                                    <label class="sg-mmcs-field-group">
                                        <span class="sg-mmcs-label"><?php esc_html_e( 'Paste Shortcode', 'softglaze-maintenance-mode-coming-soon' ); ?></span>
                                        <textarea name="<?php echo esc_attr(Options::OPTION_KEY); ?>[form_shortcode]" class="large-text" rows="3" placeholder="[contact-form-7 id='123']"><?php echo esc_textarea($opts['form_shortcode']); ?></textarea>
                                        <p class="description"><?php esc_html_e( 'Supports WPForms, Gravity Forms, Contact Form 7, etc.', 'softglaze-maintenance-mode-coming-soon' ); ?></p>
                                    </label>
                                </div>

                                <div id="sg-panel-builtin" class="sg-form-panel" style="<?php echo esc_attr( $opts['form_type'] === 'builtin' ? '' : 'display:none;' ); ?>">
                                    <div class="sg-mmcs-row">
                                        <label class="sg-mmcs-field-group">
                                            <span class="sg-mmcs-label-sm">Form Title</span>
                                            <input type="text" class="regular-text" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[subscribe_title]" value="<?php echo esc_attr($opts['subscribe_title']); ?>">
                                        </label>
                                        <label class="sg-mmcs-field-group">
                                            <span class="sg-mmcs-label-sm">Button Text</span>
                                            <input type="text" class="regular-text" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[subscribe_button]" value="<?php echo esc_attr($opts['subscribe_button']); ?>">
                                        </label>
                                    </div>

                                    <h3 class="sg-mmcs-subtitle" style="margin-top:20px;"><?php esc_html_e('Form Fields', 'softglaze-maintenance-mode-coming-soon'); ?></h3>
                                    <div class="sg-repeater" id="sg-form-fields">
                                        <?php 
                                        $fields = $opts['form_fields'];
                                        foreach ($fields as $idx => $f): 
                                            // Handle default missing width
                                            $width = isset($f['width']) ? $f['width'] : '100';
                                        ?>
                                        <div class="sg-repeater-item">
                                            <div class="sg-repeater-handle"></div>
                                            <div class="sg-repeater-content">
                                                <div class="sg-mmcs-row">
                                                    <div style="flex:2;">
                                                        <span class="sg-mmcs-label-sm">Label</span>
                                                        <input type="text" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[form_fields][<?php echo esc_attr( $idx ); ?>][label]" value="<?php echo esc_attr($f['label']); ?>" placeholder="Label">
                                                        <span class="sg-mmcs-label-sm" style="margin-top:6px;">Placeholder</span>
                                                        <input type="text" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[form_fields][<?php echo esc_attr( $idx ); ?>][placeholder]" value="<?php echo esc_attr($f['placeholder'] ?? ''); ?>" placeholder="Placeholder text">
                                                    </div>
                                                    <div style="flex:1;">
                                                        <span class="sg-mmcs-label-sm">Type</span>
                                                        <select name="<?php echo esc_attr(Options::OPTION_KEY); ?>[form_fields][<?php echo esc_attr( $idx ); ?>][type]">
                                                            <option value="text" <?php selected($f['type'], 'text'); ?>>Text</option>
                                                            <option value="email" <?php selected($f['type'], 'email'); ?>>Email</option>
                                                            <option value="tel" <?php selected($f['type'], 'tel'); ?>>Phone</option>
                                                        </select>
                                                    </div>
                                                    <div style="flex:1;">
                                                        <span class="sg-mmcs-label-sm">Width</span>
                                                        <select name="<?php echo esc_attr(Options::OPTION_KEY); ?>[form_fields][<?php echo esc_attr( $idx ); ?>][width]">
                                                            <option value="100" <?php selected($width, '100'); ?>>100%</option>
                                                            <option value="50" <?php selected($width, '50'); ?>>50%</option>
                                                        </select>
                                                    </div>
                                                    <div style="flex:1;">
                                                        <span class="sg-mmcs-label-sm">ID/Key</span>
                                                        <input type="text" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[form_fields][<?php echo esc_attr( $idx ); ?>][key]" value="<?php echo esc_attr($f['key']); ?>" placeholder="Field Key" class="small-text">
                                                    </div>
                                                    <div style="width:auto; padding-top:16px;">
                                                        <label>
                                                            <input type="hidden" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[form_fields][<?php echo esc_attr( $idx ); ?>][required]" value="0">
                                                            <input type="checkbox" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[form_fields][<?php echo esc_attr( $idx ); ?>][required]" value="1" <?php checked(!empty($f['required'])); ?>> Req?</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="button" class="button sg-remove-row"></button>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="button sg-add-field-row" style="margin-top:10px;"><?php esc_html_e('+ Add Field', 'softglaze-maintenance-mode-coming-soon'); ?></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="sg-mmcs-card">
                        <div class="sg-mmcs-card-header">
                            <h2><?php esc_html_e('Social Media Icons', 'softglaze-maintenance-mode-coming-soon'); ?></h2>
                        </div>
                        <div class="sg-mmcs-card-body">
                             <label class="sg-mmcs-checkbox-lg">
                                <input type="hidden" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[show_social]" value="0">
                                <input type="checkbox" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[show_social]" value="1" <?php checked($opts['show_social'], 1); ?>>
                                <span><?php esc_html_e('Show Social Icons', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                            </label>

                            <div class="sg-mmcs-indent">
                                <div class="sg-repeater" id="sg-social-icons">
                                    <?php 
                                    $icons = $opts['social_icons'];
                                    foreach ($icons as $idx => $icon): 
                                    ?>
                                    <div class="sg-repeater-item">
                                        <div class="sg-repeater-handle"></div>
                                        <div class="sg-repeater-content">
                                            <div class="sg-mmcs-row">
                                                <select name="<?php echo esc_attr(Options::OPTION_KEY); ?>[social_icons][<?php echo esc_attr( $idx ); ?>][platform]">
                                                    <option value="facebook" <?php selected($icon['platform'], 'facebook'); ?>>Facebook</option>
                                                    <option value="instagram" <?php selected($icon['platform'], 'instagram'); ?>>Instagram</option>
                                                    <option value="x" <?php selected($icon['platform'], 'x'); ?>>X (Twitter)</option>
                                                    <option value="linkedin" <?php selected($icon['platform'], 'linkedin'); ?>>LinkedIn</option>
                                                    <option value="youtube" <?php selected($icon['platform'], 'youtube'); ?>>YouTube</option>
                                                    <option value="tiktok" <?php selected($icon['platform'], 'tiktok'); ?>>TikTok</option>
                                                    <option value="whatsapp" <?php selected($icon['platform'], 'whatsapp'); ?>>WhatsApp</option>
                                                    <option value="telegram" <?php selected($icon['platform'], 'telegram'); ?>>Telegram</option>
                                                    <option value="pinterest" <?php selected($icon['platform'], 'pinterest'); ?>>Pinterest</option>
                                                    <option value="snapchat" <?php selected($icon['platform'], 'snapchat'); ?>>Snapchat</option>
                                                    <option value="email" <?php selected($icon['platform'], 'email'); ?>>Email</option>
                                                </select>
                                                <input type="text" class="large-text" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[social_icons][<?php echo esc_attr( $idx ); ?>][url]" value="<?php echo esc_attr($icon['url']); ?>" placeholder="URL">
                                            </div>
                                        </div>
                                        <button type="button" class="button sg-remove-row"></button>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="button sg-add-social-row" style="margin-top:10px;"><?php esc_html_e('+ Add Icon', 'softglaze-maintenance-mode-coming-soon'); ?></button>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ( $tab === 'automation' ) : ?>
                    <div class="sg-mmcs-card">
                        <div class="sg-mmcs-card-header"><h2><?php esc_html_e('Scheduling', 'softglaze-maintenance-mode-coming-soon'); ?></h2></div>
                        <div class="sg-mmcs-card-body">
                            <p class="description"><?php esc_html_e('Automatically enable Coming Soon or Maintenance during a specific time window (uses WP-Cron).', 'softglaze-maintenance-mode-coming-soon'); ?></p>

                            <label class="sg-mmcs-checkbox-lg">
                                <input type="hidden" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[schedule_enabled]" value="0">
                                <input type="checkbox" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[schedule_enabled]" value="1" <?php checked( (int) $opts['schedule_enabled'], 1 ); ?>>
                                <span><?php esc_html_e('Enable schedule window', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                            </label>

                            <div class="sg-mmcs-indent" style="margin-top:12px;">
                                <div class="sg-mmcs-grid-3">
                                    <label class="sg-mmcs-field-group">
                                        <span class="sg-mmcs-label-sm"><?php esc_html_e('Scheduled mode', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                                        <select name="<?php echo esc_attr(Options::OPTION_KEY); ?>[schedule_mode]">
                                            <option value="maintenance" <?php selected( $opts['schedule_mode'], 'maintenance' ); ?>><?php esc_html_e('Maintenance (503)', 'softglaze-maintenance-mode-coming-soon'); ?></option>
                                            <option value="coming_soon" <?php selected( $opts['schedule_mode'], 'coming_soon' ); ?>><?php esc_html_e('Coming Soon (200)', 'softglaze-maintenance-mode-coming-soon'); ?></option>
                                        </select>
                                    </label>

                                    <?php
                                        $tz = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
                                        $start_local = $opts['schedule_start_ts'] ? wp_date( 'Y-m-d\\TH:i', (int) $opts['schedule_start_ts'], $tz ) : '';
                                        $end_local   = $opts['schedule_end_ts'] ? wp_date( 'Y-m-d\\TH:i', (int) $opts['schedule_end_ts'], $tz ) : '';
                                    ?>
                                    <label class="sg-mmcs-field-group">
                                        <span class="sg-mmcs-label-sm"><?php esc_html_e('Start (site timezone)', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                                        <input type="datetime-local" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[schedule_start]" value="<?php echo esc_attr( $start_local ); ?>">
                                    </label>
                                    <label class="sg-mmcs-field-group">
                                        <span class="sg-mmcs-label-sm"><?php esc_html_e('End (site timezone)', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                                        <input type="datetime-local" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[schedule_end]" value="<?php echo esc_attr( $end_local ); ?>">
                                    </label>
                                </div>

                                <label class="sg-mmcs-field-group" style="max-width:420px;">
                                    <span class="sg-mmcs-label-sm"><?php esc_html_e('After schedule ends, restore mode to', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                                    <select name="<?php echo esc_attr(Options::OPTION_KEY); ?>[schedule_restore_mode]">
                                        <option value="off" <?php selected( $opts['schedule_restore_mode'], 'off' ); ?>><?php esc_html_e('Off (normal site)', 'softglaze-maintenance-mode-coming-soon'); ?></option>
                                        <option value="coming_soon" <?php selected( $opts['schedule_restore_mode'], 'coming_soon' ); ?>><?php esc_html_e('Coming Soon', 'softglaze-maintenance-mode-coming-soon'); ?></option>
                                        <option value="maintenance" <?php selected( $opts['schedule_restore_mode'], 'maintenance' ); ?>><?php esc_html_e('Maintenance', 'softglaze-maintenance-mode-coming-soon'); ?></option>
                                    </select>
                                    <p class="description"><?php esc_html_e('If you enable scheduling while a mode is active, we automatically remember the previous mode so you can restore it later.', 'softglaze-maintenance-mode-coming-soon'); ?></p>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="sg-mmcs-card">
                        <div class="sg-mmcs-card-header"><h2><?php esc_html_e('Links & Rules', 'softglaze-maintenance-mode-coming-soon'); ?></h2></div>
                        <div class="sg-mmcs-card-body">
                            <div class="sg-mmcs-grid-2">
                                <div>
                                    <h3 class="sg-mmcs-subtitle"><?php esc_html_e('Bypass Links', 'softglaze-maintenance-mode-coming-soon'); ?></h3>
                                    <p class="description"><?php esc_html_e('Generate a temporary link that sets an access cookie (ideal for clients, QA and teammates).', 'softglaze-maintenance-mode-coming-soon'); ?></p>

                                    <label class="sg-mmcs-checkbox-lg">
                                        <input type="hidden" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[bypass_links_enabled]" value="0">
                                        <input type="checkbox" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[bypass_links_enabled]" value="1" <?php checked( (int) $opts['bypass_links_enabled'], 1 ); ?>>
                                        <span><?php esc_html_e('Enable bypass links', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                                    </label>

                                    <div class="sg-mmcs-row" style="align-items:flex-end; gap:12px;">
                                        <label class="sg-mmcs-field-group" style="min-width:200px;">
                                            <span class="sg-mmcs-label-sm"><?php esc_html_e('Cookie duration (hours)', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                                            <input type="number" min="1" max="168" step="1" class="sg-mmcs-bypass-hours" value="<?php echo esc_attr( (int) $opts['bypass_link_duration_hours'] ); ?>">
                                        </label>
                                        <button type="button" class="button sg-mmcs-generate-bypass-link" <?php disabled( (int) $opts['bypass_links_enabled'], 0 ); ?>><?php esc_html_e('Generate bypass link', 'softglaze-maintenance-mode-coming-soon'); ?></button>
                                    </div>

                                    <div class="sg-mmcs-row" style="gap:10px; margin-top:10px;">
                                        <input type="url" class="large-text sg-mmcs-bypass-link" value="" readonly placeholder="<?php echo esc_attr( __( 'Your bypass link will appear here…', 'softglaze-maintenance-mode-coming-soon' ) ); ?>">
                                        <button type="button" class="button sg-mmcs-copy-bypass"><?php esc_html_e('Copy', 'softglaze-maintenance-mode-coming-soon'); ?></button>
                                    </div>
                                    <p class="description sg-mmcs-bypass-hint" style="margin-top:6px;"></p>
                                </div>

                                <div>
                                    <h3 class="sg-mmcs-subtitle"><?php esc_html_e('WooCommerce Safe Mode', 'softglaze-maintenance-mode-coming-soon'); ?></h3>
                                    <p class="description"><?php esc_html_e('Skip interception for cart/checkout/account endpoints so transactions are not blocked during Coming Soon or Maintenance.', 'softglaze-maintenance-mode-coming-soon'); ?></p>

                                    <label class="sg-mmcs-checkbox-lg">
                                        <input type="hidden" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[woocommerce_safe_mode]" value="0">
                                        <input type="checkbox" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[woocommerce_safe_mode]" value="1" <?php checked( (int) $opts['woocommerce_safe_mode'], 1 ); ?>>
                                        <span><?php esc_html_e('Enable WooCommerce Safe Mode', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                                    </label>

                                    <hr>

                                    <h3 class="sg-mmcs-subtitle"><?php esc_html_e('Basic Analytics', 'softglaze-maintenance-mode-coming-soon'); ?></h3>
                                    <p class="description"><?php esc_html_e('Track page views and subscription conversions inside WordPress (no external scripts).', 'softglaze-maintenance-mode-coming-soon'); ?></p>
                                    <label class="sg-mmcs-checkbox-lg">
                                        <input type="hidden" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[analytics_enabled]" value="0">
                                        <input type="checkbox" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[analytics_enabled]" value="1" <?php checked( (int) $opts['analytics_enabled'], 1 ); ?>>
                                        <span><?php esc_html_e('Enable built-in analytics', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="sg-mmcs-card">
                        <div class="sg-mmcs-card-header"><h2><?php esc_html_e('Analytics Snapshot', 'softglaze-maintenance-mode-coming-soon'); ?></h2></div>
                        <div class="sg-mmcs-card-body">
                            <?php
                                $a = get_option( 'sg_mmcs_analytics', [] );
                                $views_total = isset( $a['views_total'] ) ? (int) $a['views_total'] : 0;
                                $subs_total  = isset( $a['subs_total'] ) ? (int) $a['subs_total'] : 0;
                                $views_by_day = isset( $a['views_by_day'] ) && is_array( $a['views_by_day'] ) ? $a['views_by_day'] : [];
                                $subs_by_day  = isset( $a['subs_by_day'] ) && is_array( $a['subs_by_day'] ) ? $a['subs_by_day'] : [];
                                $tz = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
                                $days = [];
                                for ( $i = 6; $i >= 0; $i-- ) {
                                    $day = wp_date( 'Y-m-d', time() - ( $i * DAY_IN_SECONDS ), $tz );
                                    $days[] = $day;
                                }
                            ?>
                            <div class="sg-mmcs-grid-3">
                                <div class="sg-mmcs-stat"><div class="sg-mmcs-stat-num"><?php echo esc_html( (string) $views_total ); ?></div><div class="sg-mmcs-stat-label"><?php esc_html_e('Total views', 'softglaze-maintenance-mode-coming-soon'); ?></div></div>
                                <div class="sg-mmcs-stat"><div class="sg-mmcs-stat-num"><?php echo esc_html( (string) $subs_total ); ?></div><div class="sg-mmcs-stat-label"><?php esc_html_e('Total signups', 'softglaze-maintenance-mode-coming-soon'); ?></div></div>
                                <div class="sg-mmcs-stat"><div class="sg-mmcs-stat-num"><?php echo esc_html( $views_total ? (string) ( round( ( $subs_total / max( 1, $views_total ) ) * 100, 2 ) . '%' ) : '0%' ); ?></div><div class="sg-mmcs-stat-label"><?php esc_html_e('Overall conversion', 'softglaze-maintenance-mode-coming-soon'); ?></div></div>
                            </div>

                            <h3 class="sg-mmcs-subtitle" style="margin-top:14px;"><?php esc_html_e('Last 7 days', 'softglaze-maintenance-mode-coming-soon'); ?></h3>
                            <table class="widefat striped">
                                <thead><tr><th><?php esc_html_e('Date', 'softglaze-maintenance-mode-coming-soon'); ?></th><th><?php esc_html_e('Views', 'softglaze-maintenance-mode-coming-soon'); ?></th><th><?php esc_html_e('Signups', 'softglaze-maintenance-mode-coming-soon'); ?></th></tr></thead>
                                <tbody>
                                    <?php foreach ( $days as $d ) :
                                        $v = isset( $views_by_day[ $d ] ) ? (int) $views_by_day[ $d ] : 0;
                                        $s = isset( $subs_by_day[ $d ] ) ? (int) $subs_by_day[ $d ] : 0;
                                    ?>
                                    <tr><td><?php echo esc_html( $d ); ?></td><td><?php echo esc_html( (string) $v ); ?></td><td><?php echo esc_html( (string) $s ); ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ( $tab === 'access' ) : ?>
                    <div class="sg-mmcs-card">
                        <div class="sg-mmcs-card-header"><h2><?php esc_html_e('Access Control', 'softglaze-maintenance-mode-coming-soon'); ?></h2></div>
                        <div class="sg-mmcs-card-body">
                            <label class="sg-mmcs-checkbox-lg">
                                <input type="hidden" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[bypass_logged_in]" value="0">
                                <input type="checkbox" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[bypass_logged_in]" value="1" <?php checked($opts['bypass_logged_in'], 1); ?>>
                                <span><?php esc_html_e('Allow ALL logged-in users to access the site', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                            </label>

                            <div class="sg-mmcs-grid-2" style="margin-top:14px;">
                                <label class="sg-mmcs-field-group">
                                    <span class="sg-mmcs-label-sm"><?php esc_html_e('Notification Email', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                                    <input type="email" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[admin_notify_email]" value="<?php echo esc_attr($opts['admin_notify_email']); ?>" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
                                    <p class="description"><?php esc_html_e('Where subscription notifications should be sent.', 'softglaze-maintenance-mode-coming-soon'); ?></p>
                                </label>
                                <label class="sg-mmcs-field-group">
                                    <span class="sg-mmcs-label-sm"><?php esc_html_e('Webhook URL (Optional)', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                                    <input type="url" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[webhook_url]" value="<?php echo esc_attr($opts['webhook_url']); ?>" placeholder="https://...">
                                    <p class="description"><?php esc_html_e('POST request will be sent on each new subscription.', 'softglaze-maintenance-mode-coming-soon'); ?></p>
                                </label>
                            </div>

                            <hr>
                            
                            <h3 class="sg-mmcs-subtitle">Whitelisted Users & Roles</h3>
                            <div class="sg-mmcs-grid-2">
                                <div>
                                    <span class="sg-mmcs-label">Allowed Roles <button type="button" class="button button-small sg-select-all-roles">Select All</button></span>
                                    <select name="<?php echo esc_attr(Options::OPTION_KEY); ?>[bypass_roles][]" multiple class="large-text sg-select-roles" style="height:150px;">
                                        <?php 
                                        global $wp_roles;
                                        foreach ( $wp_roles->roles as $role_key => $role_data ) {
                                            ?>
                                            <option value="<?php echo esc_attr( $role_key ); ?>" <?php selected( in_array( $role_key, (array) $opts['bypass_roles'], true ), true ); ?>>
                                                <?php echo esc_html( $role_data['name'] ); ?>
                                            </option>
                                            <?php
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div>
                                    <span class="sg-mmcs-label">Specific Users</span>
                                    <select name="<?php echo esc_attr(Options::OPTION_KEY); ?>[bypass_users][]" multiple class="large-text" style="height:150px;">
                                        <?php 
                                        $users = get_users( [ 'number' => 100, 'orderby' => 'display_name' ] );
                                        foreach ( $users as $u ) {
                                            ?>
                                            <option value="<?php echo esc_attr( $u->ID ); ?>" <?php selected( in_array( $u->ID, (array) $opts['bypass_users'], true ), true ); ?>>
                                                <?php echo esc_html( $u->display_name ); ?> (<?php echo esc_html( $u->user_login ); ?>)
                                            </option>
                                            <?php
                                        }
                                        ?>
                                    </select>
                                    <p class="description">Hold CTRL/CMD to select multiple.</p>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <label class="sg-mmcs-field-group">
                                <span class="sg-mmcs-label"><?php esc_html_e('Secret Bypass URL', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                                <div class="sg-mmcs-input-group">
                                    <input type="text" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[secret_bypass_key]" value="<?php echo esc_attr($opts['secret_bypass_key']); ?>" placeholder="Enter a secure key here">
                                </div>
                                <p class="description">
                                    <?php if(!empty($opts['secret_bypass_key'])): ?>
                                        <?php esc_html_e('Share this link with clients/guests:', 'softglaze-maintenance-mode-coming-soon'); ?> <br>
                                        <code><?php echo esc_html( esc_url( add_query_arg( 'sg_access', rawurlencode( (string) $opts['secret_bypass_key'] ), home_url( '/' ) ) ) ); ?></code>
                                    <?php endif; ?>
                                </p>
                            </label>

                            <hr>

                            <label class="sg-mmcs-checkbox-lg">
                                <input type="hidden" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[password_protect]" value="0">
                                <input type="checkbox" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[password_protect]" value="1" <?php checked($opts['password_protect'], 1); ?>>
                                <span><?php esc_html_e('Password Protection', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                            </label>
                            <div class="sg-mmcs-indent">
                                <span class="sg-mmcs-label-sm"><?php esc_html_e('Access Password', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                                <div class="sg-mmcs-row" style="align-items:center;">
                                    <input type="password" class="regular-text sg-password-field" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[access_password]" value="<?php echo esc_attr($opts['access_password']); ?>" placeholder="Set Password" autocomplete="new-password">
                                    <button type="button" class="button sg-toggle-password"><?php esc_html_e('Show', 'softglaze-maintenance-mode-coming-soon'); ?></button>
                                </div>
                                <p class="description"><?php esc_html_e('Visitors can unlock access using this password.', 'softglaze-maintenance-mode-coming-soon'); ?></p>
                            </div>

                             <hr>

                            <label class="sg-mmcs-field-group">
                                <span class="sg-mmcs-label"><?php esc_html_e('IP Allowlist', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                                <textarea name="<?php echo esc_attr(Options::OPTION_KEY); ?>[ip_allowlist]" class="large-text" rows="3" placeholder="Enter IP addresses (one per line)"><?php echo esc_textarea($opts['ip_allowlist']); ?></textarea>
                            </label>

                            <hr>

                            <h3 class="sg-mmcs-subtitle"><?php esc_html_e('URL Rules', 'softglaze-maintenance-mode-coming-soon'); ?></h3>
                            <p class="description" style="margin-top:0;"><?php esc_html_e('One path per line (examples: /wp-admin/, /my-page/). These rules are checked before showing the Coming Soon / Maintenance page.', 'softglaze-maintenance-mode-coming-soon'); ?></p>

                            <div class="sg-mmcs-grid-2">
                                <label class="sg-mmcs-field-group">
                                    <span class="sg-mmcs-label"><?php esc_html_e('Only Intercept These URLs', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                                    <textarea name="<?php echo esc_attr(Options::OPTION_KEY); ?>[include_urls_only]" class="large-text" rows="4" placeholder="/\n/landing/\n/shop/"><?php echo esc_textarea($opts['include_urls_only']); ?></textarea>
                                    <p class="description"><?php esc_html_e('If set, the plugin will ONLY show the page on these URLs.', 'softglaze-maintenance-mode-coming-soon'); ?></p>
                                </label>

                                <label class="sg-mmcs-field-group">
                                    <span class="sg-mmcs-label"><?php esc_html_e('Never Intercept These URLs', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                                    <textarea name="<?php echo esc_attr(Options::OPTION_KEY); ?>[exclude_urls]" class="large-text" rows="4" placeholder="/wp-login.php\n/checkout/\n/cart/"><?php echo esc_textarea($opts['exclude_urls']); ?></textarea>
                                    <p class="description"><?php esc_html_e('If set, these URLs will stay accessible to everyone.', 'softglaze-maintenance-mode-coming-soon'); ?></p>
                                </label>
                            </div>

                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ( $tab === 'integrations' ) : ?>
	                    <div class="sg-mmcs-card">
	                        <div class="sg-mmcs-card-header"><h2><?php esc_html_e('Integrations', 'softglaze-maintenance-mode-coming-soon'); ?></h2></div>
	                        <div class="sg-mmcs-card-body">
	                            <p class="description" style="margin-top:0;">Use the fields below for the most common tracking/verification tags — no code knowledge required. Advanced custom code is still available further down.</p>
	                            <div class="sg-mmcs-grid-3">
	                                <label class="sg-mmcs-field-group">
	                                    <span class="sg-mmcs-label-sm">GA4 Measurement ID</span>
	                                    <input type="text" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[ga4_id]" value="<?php echo esc_attr($opts['ga4_id']); ?>" placeholder="G-XXXXXXXXXX">
	                                    <p class="description">Example: <code>G-XXXXXXXXXX</code></p>
	                                </label>
	                                <label class="sg-mmcs-field-group">
	                                    <span class="sg-mmcs-label-sm">GTM Container ID</span>
	                                    <input type="text" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[gtm_id]" value="<?php echo esc_attr($opts['gtm_id'] ?? ''); ?>" placeholder="GTM-XXXXXXX">
	                                    <p class="description">Example: <code>GTM-XXXXXXX</code></p>
	                                </label>
	                                <label class="sg-mmcs-field-group">
	                                    <span class="sg-mmcs-label-sm">Meta Pixel ID</span>
	                                    <input type="text" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[fb_pixel_id]" value="<?php echo esc_attr($opts['fb_pixel_id']); ?>" placeholder="1234567890">
	                                    <p class="description">Numbers only</p>
	                                </label>
	                            </div>

	                            <h3 style="margin-top:18px;">Site Verification</h3>
	                            <div class="sg-mmcs-grid-3">
	                                <label class="sg-mmcs-field-group">
	                                    <span class="sg-mmcs-label-sm">Google Site Verification</span>
	                                    <input type="text" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[google_site_verification]" value="<?php echo esc_attr($opts['google_site_verification'] ?? ''); ?>" placeholder="verification_token">
	                                    <p class="description">Paste only the <strong>content</strong> value</p>
	                                </label>
	                                <label class="sg-mmcs-field-group">
	                                    <span class="sg-mmcs-label-sm">Facebook Domain Verification</span>
	                                    <input type="text" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[facebook_domain_verification]" value="<?php echo esc_attr($opts['facebook_domain_verification'] ?? ''); ?>" placeholder="domain_verification_token">
	                                    <p class="description">Paste only the <strong>content</strong> value</p>
	                                </label>
	                                <label class="sg-mmcs-field-group">
	                                    <span class="sg-mmcs-label-sm">Bing Site Verification</span>
	                                    <input type="text" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[bing_site_verification]" value="<?php echo esc_attr($opts['bing_site_verification'] ?? ''); ?>" placeholder="verification_token">
	                                    <p class="description">Paste only the <strong>content</strong> value</p>
	                                </label>
	                            </div>

	                            <label class="sg-mmcs-field-group" style="margin-top:14px;">
	                                <span class="sg-mmcs-label">Custom Meta/Link Tags (Optional)</span>
	                                <textarea name="<?php echo esc_attr(Options::OPTION_KEY); ?>[custom_meta_tags]" class="large-text" rows="3" placeholder="&lt;meta ...&gt;\n&lt;link ...&gt;"><?php echo esc_textarea($opts['custom_meta_tags'] ?? ''); ?></textarea>
	                                <p class="description">Only for <code>&lt;meta&gt;</code> and <code>&lt;link&gt;</code> tags. For scripts, use the Advanced section below.</p>
	                            </label>
	                        </div>
	                    </div>

	                    <div class="sg-mmcs-card">
	                        <div class="sg-mmcs-card-header"><h2><?php esc_html_e('Branding & Open Graph', 'softglaze-maintenance-mode-coming-soon'); ?></h2></div>
	                        <div class="sg-mmcs-card-body">
	                            <div class="sg-mmcs-grid-2">
	                                <label class="sg-mmcs-field-group">
	                                    <span class="sg-mmcs-label"><?php esc_html_e('Site Title Override', 'softglaze-maintenance-mode-coming-soon'); ?></span>
	                                    <input type="text" class="large-text" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[site_title]" value="<?php echo esc_attr($opts['site_title']); ?>" placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>">
	                                    <p class="description"><?php esc_html_e('Used for the Coming Soon / Maintenance page title and Open Graph title.', 'softglaze-maintenance-mode-coming-soon'); ?></p>
	                                </label>

	                                <label class="sg-mmcs-field-group">
	                                    <span class="sg-mmcs-label"><?php esc_html_e('Favicon URL', 'softglaze-maintenance-mode-coming-soon'); ?></span>
	                                    <div class="sg-mmcs-row">
	                                        <input type="url" class="large-text sg-img-input" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[favicon]" value="<?php echo esc_attr($opts['favicon']); ?>" placeholder="https://...">
	                                        <button type="button" class="button sg-upload-btn"><?php esc_html_e('Upload', 'softglaze-maintenance-mode-coming-soon'); ?></button>
	                                    </div>
	                                    <p class="description"><?php esc_html_e('Optional. If empty, your site icon will be used.', 'softglaze-maintenance-mode-coming-soon'); ?></p>
	                                </label>
	                            </div>

	                            <label class="sg-mmcs-field-group">
	                                <span class="sg-mmcs-label"><?php esc_html_e('Open Graph Image', 'softglaze-maintenance-mode-coming-soon'); ?></span>
	                                <div class="sg-mmcs-row">
	                                    <input type="url" class="large-text sg-img-input" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[og_image]" value="<?php echo esc_attr($opts['og_image']); ?>" placeholder="https://...">
	                                    <button type="button" class="button sg-upload-btn"><?php esc_html_e('Upload', 'softglaze-maintenance-mode-coming-soon'); ?></button>
	                                </div>
	                                <p class="description"><?php esc_html_e('Used when your page is shared on social media.', 'softglaze-maintenance-mode-coming-soon'); ?></p>
	                            </label>
	                        </div>
	                    </div>



	                    <div class="sg-mmcs-card">
	                        <div class="sg-mmcs-card-header"><h2><?php esc_html_e('Advanced Custom Code', 'softglaze-maintenance-mode-coming-soon'); ?></h2></div>
	                        <div class="sg-mmcs-card-body">
	                            <label class="sg-mmcs-field-group">
	                                <span class="sg-mmcs-label">Head Code</span>
	                                <textarea name="<?php echo esc_attr(Options::OPTION_KEY); ?>[header_scripts]" class="large-text" rows="4" placeholder="&lt;script&gt;...&lt;/script&gt;"><?php echo esc_textarea($opts['header_scripts']); ?></textarea>
	                                <p class="description">Injected in <code>&lt;head&gt;</code> (before <code>&lt;/head&gt;</code>).</p>
	                            </label>
	
	                            <label class="sg-mmcs-field-group">
	                                <span class="sg-mmcs-label">After Body Open Code</span>
	                                <textarea name="<?php echo esc_attr(Options::OPTION_KEY); ?>[body_scripts]" class="large-text" rows="3" placeholder="&lt;noscript&gt;...&lt;/noscript&gt;"><?php echo esc_textarea($opts['body_scripts'] ?? ''); ?></textarea>
	                                <p class="description">Injected right after <code>&lt;body&gt;</code> (via <code>wp_body_open</code>).</p>
	                            </label>
	                            
	                            <label class="sg-mmcs-field-group">
	                                <span class="sg-mmcs-label">Footer Code</span>
	                                <textarea name="<?php echo esc_attr(Options::OPTION_KEY); ?>[footer_scripts]" class="large-text" rows="4" placeholder="&lt;script&gt;...&lt;/script&gt;"><?php echo esc_textarea($opts['footer_scripts']); ?></textarea>
	                                <p class="description">Injected before <code>&lt;/body&gt;</code>.</p>
	                            </label>
	                        </div>
	                    </div>

                    <div class="sg-mmcs-card">
                        <div class="sg-mmcs-card-header"><h2><?php esc_html_e('Data & Email', 'softglaze-maintenance-mode-coming-soon'); ?></h2></div>
                        <div class="sg-mmcs-card-body">
                            <label class="sg-mmcs-checkbox-lg">
                                <input type="hidden" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[store_subscribers]" value="0">
                                <input type="checkbox" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[store_subscribers]" value="1" <?php checked($opts['store_subscribers'], 1); ?>>
                                <span><?php esc_html_e('Save subscribers to local database', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                            </label>
                            
                            <label class="sg-mmcs-checkbox-lg">
                                <input type="hidden" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[notify_admin]" value="0">
                                <input type="checkbox" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[notify_admin]" value="1" <?php checked($opts['notify_admin'], 1); ?>>
                                <span><?php esc_html_e('Send me an email when someone subscribes', 'softglaze-maintenance-mode-coming-soon'); ?></span>
                            </label>
                            
                            <hr>
                            
                            <h3><?php esc_html_e('Mailchimp', 'softglaze-maintenance-mode-coming-soon'); ?></h3>
                            <div class="sg-mmcs-grid-3">
                                <label class="sg-mmcs-field-group">
                                    <span class="sg-mmcs-label-sm">API Key</span>
                                    <input type="text" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[mailchimp_api_key]" value="<?php echo esc_attr($opts['mailchimp_api_key']); ?>">
                                </label>
                                <label class="sg-mmcs-field-group">
                                    <span class="sg-mmcs-label-sm">List ID</span>
                                    <input type="text" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[mailchimp_list_id]" value="<?php echo esc_attr($opts['mailchimp_list_id']); ?>">
                                </label>
                                <label class="sg-mmcs-field-group">
                                    <span class="sg-mmcs-label-sm">Data Center (e.g. us1)</span>
                                    <input type="text" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[mailchimp_dc]" value="<?php echo esc_attr($opts['mailchimp_dc']); ?>">
                                </label>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
                
                <div id="sg-preset-modal" style="display:none;" class="sg-mmcs-modal-overlay">
                    <div class="sg-mmcs-modal">
                        <div class="sg-mmcs-modal-header">
                            <h3><?php esc_html_e('Apply Content Preset?', 'softglaze-maintenance-mode-coming-soon'); ?></h3>
                        </div>
                        
<div class="sg-mmcs-modal-body">
    <p style="margin-top:0;"><?php esc_html_e('This will overwrite your current Headline and Message with the preset below:', 'softglaze-maintenance-mode-coming-soon'); ?></p>
    <div class="sg-preset-preview">
        <div class="sg-preset-preview-label"><?php esc_html_e('Preview', 'softglaze-maintenance-mode-coming-soon'); ?></div>
        <div class="sg-preset-preview-head" id="sg-preset-preview-head"></div>
        <div class="sg-preset-preview-msg" id="sg-preset-preview-msg"></div>
    </div>
    <p class="description" style="margin:12px 0 0;"><?php esc_html_e('Tip: Switch your mode in the General tab to see mode-specific presets.', 'softglaze-maintenance-mode-coming-soon'); ?></p>
</div>
                        <div class="sg-mmcs-modal-footer">
                            <button type="button" class="button sg-modal-cancel"><?php esc_html_e('Cancel', 'softglaze-maintenance-mode-coming-soon'); ?></button>
                            <button type="button" class="button button-primary sg-modal-confirm"><?php esc_html_e('Yes, Apply Preset', 'softglaze-maintenance-mode-coming-soon'); ?></button>
                        </div>
                    </div>
                </div>

                <div class="sg-mmcs-footer">
                    <?php submit_button(__('Save Changes', 'softglaze-maintenance-mode-coming-soon'), 'primary large'); ?>
                </div>
            </form>
        </div>
        <div style="display:none;" id="sg-repeater-templates">
            <div class="sg-repeater-item tmpl-form-field">
                <div class="sg-repeater-handle"></div>
                <div class="sg-repeater-content">
                    <div class="sg-mmcs-row">
                        <div style="flex:2;">
                            <span class="sg-mmcs-label-sm">Label</span>
                            <input type="text" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[form_fields][INDEX][label]" value="" placeholder="Label">
                            <span class="sg-mmcs-label-sm" style="margin-top:6px;">Placeholder</span>
                            <input type="text" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[form_fields][INDEX][placeholder]" value="" placeholder="Placeholder text">
                        </div>
                        <div style="flex:1;">
                            <span class="sg-mmcs-label-sm">Type</span>
                            <select name="<?php echo esc_attr(Options::OPTION_KEY); ?>[form_fields][INDEX][type]">
                                <option value="text">Text</option>
                                <option value="email">Email</option>
                                <option value="tel">Phone</option>
                            </select>
                        </div>
                         <div style="flex:1;">
                            <span class="sg-mmcs-label-sm">Width</span>
                            <select name="<?php echo esc_attr(Options::OPTION_KEY); ?>[form_fields][INDEX][width]">
                                <option value="100">100%</option>
                                <option value="50">50%</option>
                            </select>
                        </div>
                        <div style="flex:1;">
                            <span class="sg-mmcs-label-sm">ID/Key</span>
                            <input type="text" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[form_fields][INDEX][key]" value="" placeholder="Field Key" class="small-text">
                        </div>
                        <div style="width:auto; padding-top:16px;">
                            <label>
                                <input type="hidden" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[form_fields][INDEX][required]" value="0">
                                <input type="checkbox" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[form_fields][INDEX][required]" value="1"> Req?</label>
                        </div>
                    </div>
                </div>
                <button type="button" class="button sg-remove-row"></button>
            </div>
            
            <div class="sg-repeater-item tmpl-social-icon">
                <div class="sg-repeater-handle"></div>
                <div class="sg-repeater-content">
                    <div class="sg-mmcs-row">
                        <select name="<?php echo esc_attr(Options::OPTION_KEY); ?>[social_icons][INDEX][platform]">
                            <option value="facebook">Facebook</option>
                            <option value="instagram">Instagram</option>
                            <option value="x">X (Twitter)</option>
                            <option value="linkedin">LinkedIn</option>
                            <option value="youtube">YouTube</option>
                            <option value="tiktok">TikTok</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="telegram">Telegram</option>
                            <option value="pinterest">Pinterest</option>
                            <option value="snapchat">Snapchat</option>
                            <option value="email">Email</option>
                        </select>
                        <input type="text" class="large-text" name="<?php echo esc_attr(Options::OPTION_KEY); ?>[social_icons][INDEX][url]" value="" placeholder="URL">
                    </div>
                </div>
                <button type="button" class="button sg-remove-row"></button>
            </div>
        </div>
        <?php
    }

    public static function render_subscribers_page() {
        if ( ! current_user_can('manage_options') ) return;

        // Keep the table/buttons styled consistently with the main settings screen.
        self::enqueue_admin_assets_now();

        $export_requested = isset( $_GET['sg_export'] ) && (string) sanitize_key( wp_unslash( $_GET['sg_export'] ) ) === '1';
        $export_nonce     = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        if ( $export_requested && wp_verify_nonce( $export_nonce, 'sg_mmcs_export' ) ) {
            Subscribers::export_csv();
        }

        global $wpdb;
        $table = Subscribers::table_name();

        $cache_group = 'sg_mmcs';

        $count = wp_cache_get( 'subscribers_count', $cache_group );
        if ( false === $count ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal and not user input.
            $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
            wp_cache_set( 'subscribers_count', $count, $cache_group, 5 * MINUTE_IN_SECONDS );
        }

        $rows = wp_cache_get( 'subscribers_rows_200', $cache_group );
        if ( false === $rows ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal and not user input.
            $rows = $wpdb->get_results( "SELECT email, created_at, source_url, status, meta FROM {$table} ORDER BY created_at DESC LIMIT 200", ARRAY_A );
            wp_cache_set( 'subscribers_rows_200', $rows, $cache_group, 5 * MINUTE_IN_SECONDS );
        }

$export_url = wp_nonce_url( admin_url('admin.php?page=sg-mmcs-subscribers&sg_export=1'), 'sg_mmcs_export' );
        ?>
        <div class="wrap sg-mmcs-wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Subscribers', 'softglaze-maintenance-mode-coming-soon'); ?></h1>
            <a href="<?php echo esc_url($export_url); ?>" class="page-title-action"><?php esc_html_e('Export CSV', 'softglaze-maintenance-mode-coming-soon'); ?></a>
            <p>
                <?php
                /* translators: %d: number of subscribers */
                printf( esc_html__( 'Total: %d', 'softglaze-maintenance-mode-coming-soon' ), (int) $count );
                ?>
            </p>
            
            <div class="sg-mmcs-card" style="margin-top:20px; padding:0;">
                <table class="widefat striped" style="border:none;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Email', 'softglaze-maintenance-mode-coming-soon'); ?></th>
                            <th><?php esc_html_e('Details', 'softglaze-maintenance-mode-coming-soon'); ?></th>
                            <th><?php esc_html_e('Date', 'softglaze-maintenance-mode-coming-soon'); ?></th>
                            <th><?php esc_html_e('Status', 'softglaze-maintenance-mode-coming-soon'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="4"><?php esc_html_e('No subscribers found.', 'softglaze-maintenance-mode-coming-soon'); ?></td></tr>
                    <?php else: foreach ($rows as $r): 
                        $meta = json_decode($r['meta'], true);
                        $details = [];
                        if($meta && is_array($meta)) {
                            foreach ( $meta as $k => $v ) {
                                if ( $k === 'mode' ) {
                                    continue;
                                }
                                $details[] = sprintf( '%s: %s', ucfirst( (string) $k ), (string) $v );
                            }
                        }
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html($r['email']); ?></strong></td>
                            <td><?php echo esc_html( implode( ' | ', array_map( 'sanitize_text_field', $details ) ) ); ?></td>
                            <td><?php echo esc_html($r['created_at']); ?></td>
                            <td><span class="sg-mmcs-badge active"><?php echo esc_html($r['status']); ?></span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
}