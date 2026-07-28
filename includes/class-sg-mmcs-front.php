<?php
namespace SoftGlaze\MMCS;

if ( ! defined( 'ABSPATH' ) ) exit;


class Front {

    /** Internal rendering flags (avoid globals). */
    private static $sg_mmcs_is_rendering = false;
    private static $sg_mmcs_is_custom_page = false;

    /** Runtime overrides for shareable preview links. */
    private static $preview_token_overrides = null;
    private static $preview_token_active = false;

    const COOKIE_BYPASS = 'sg_mmcs_bypass';


    public static function boot() {
        add_action( 'init', [ __CLASS__, 'maybe_handle_preview_token' ], 0 );
        add_action( 'init', [ __CLASS__, 'maybe_handle_bypass_token' ], 0 );
        add_action( 'init', [ __CLASS__, 'maybe_set_bypass_cookie' ], 1 );
        add_action( 'init', [ __CLASS__, 'maybe_handle_password' ], 1 );

        add_action( 'template_redirect', [ __CLASS__, 'maybe_intercept' ], 0 );

        add_action( 'wp_head', [ __CLASS__, 'head_extras' ], 1 );
        add_action( 'wp_body_open', [ __CLASS__, 'body_open_extras' ], 1 );
        add_action( 'wp_footer', [ __CLASS__, 'footer_extras' ], 99 );

        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_shortcode( 'sg_mmcs_subscribe', [ __CLASS__, 'shortcode_subscribe' ] );

        add_action( 'wp_ajax_nopriv_sg_mmcs_subscribe', [ __CLASS__, 'ajax_subscribe' ] );
        add_action( 'wp_ajax_sg_mmcs_subscribe', [ __CLASS__, 'ajax_subscribe' ] );
    }

    public static function enqueue_assets() {
        if ( ! self::$sg_mmcs_is_rendering ) { return; }

        $opts = Options::get();
        self::enqueue_integrations( $opts );

        // Ensure core icon font is available on the front-end (templates use dashicons).
        wp_enqueue_style( 'dashicons' );

        // Always enqueue JS (subscribe form, countdown, access modal). This is safe even when
        // rendering via a theme/page builder template.
        wp_enqueue_script( 'sg-mmcs-public', SG_MMCS_PLUGIN_URL . 'assets/js/public.js', [ 'jquery' ], SG_MMCS_VERSION, true );

        // CSS handling:
        // - For built-in templates, load the full stylesheet.
        // - For custom pages/landing pages rendered via the active theme template, load a small
        //   “lite” stylesheet that only styles plugin components (forms/modal/countdown) to avoid
        //   unexpected interference with page builder layouts.
        if ( self::$sg_mmcs_is_custom_page ) {
            wp_enqueue_style( 'sg-mmcs-public-lite', SG_MMCS_PLUGIN_URL . 'assets/css/public-lite.css', [], SG_MMCS_VERSION );
        } else {
            wp_enqueue_style( 'sg-mmcs-public', SG_MMCS_PLUGIN_URL . 'assets/css/public.css', [], SG_MMCS_VERSION );
        }


        // Dynamic styling for the built‑in template: output CSS variables and optional custom CSS
        // using wp_add_inline_style() (WP.org requirement: no raw <style> tags).
        if ( ! self::$sg_mmcs_is_custom_page ) {
            $inline_css = self::build_inline_css( $opts );
            if ( ! empty( $inline_css ) ) {
                wp_add_inline_style( 'sg-mmcs-public', $inline_css );
            }
        }

        wp_localize_script( 'sg-mmcs-public', 'sg_mmcs_vars', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sg_mmcs_subscribe'),
            'honeypot' => $opts['honeypot_field'],
            'countdown_action' => $opts['countdown_action'], 
            'countdown_redirect' => $opts['countdown_redirect_url'],
        ]);
    }


    /**
     * Build dynamic inline CSS for the built‑in template.
     *
     * This is intentionally injected via wp_add_inline_style() to comply with WP.org guidelines.
     *
     * @param array $opts Plugin options.
     * @return string CSS (no <style> tags).
     */
    private static function build_inline_css( array $opts ) {
        $defaults = Options::defaults();

        // Background image.
        $bg_img = 'none';
        if ( ! empty( $opts['background_image'] ) ) {
            $bg_url = esc_url_raw( (string) $opts['background_image'] );
            if ( ! empty( $bg_url ) ) {
                $bg_img = "url('{$bg_url}')";
            }
        }

        // Font handling.
        $inherit_fonts = ! empty( $opts['inherit_fonts'] );

        // Color handling.
        $inherit_colors = ! empty( $opts['inherit_colors'] );
        $bg_color       = sanitize_hex_color( $opts['background_color'] ?? '' ) ?: ( $defaults['background_color'] ?? '#0b0b0b' );
        $text_color     = sanitize_hex_color( $opts['text_color'] ?? '' ) ?: ( $defaults['text_color'] ?? '#ffffff' );
        $accent_color   = sanitize_hex_color( $opts['accent_color'] ?? '' ) ?: ( $defaults['accent_color'] ?? '#00c2ff' );

        $css  = ":root{\n";
        $css .= "  --sg-bg-img: {$bg_img};\n";

        if ( $inherit_fonts ) {
            $css .= "  --sg-font: inherit;\n";
        } else {
            $css .= "  --sg-font: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;\n";
        }

        if ( $inherit_colors ) {
            $css .= "  --sg-bg: inherit;\n";
            $css .= "  --sg-text: inherit;\n";
            $css .= "  --sg-accent: inherit;\n";
        } else {
            $css .= "  --sg-bg: {$bg_color};\n";
            $css .= "  --sg-text: {$text_color};\n";
            $css .= "  --sg-accent: {$accent_color};\n";
        }
        $css .= "}\n";

        // Force background image on our template body when one is selected.
        if ( ! empty( $opts['background_image'] ) ) {
            $css .= "body.sg-mmcs-body{\n";
            $css .= "  background-image: {$bg_img} !important;\n";
            $css .= "  background-size: cover !important;\n";
            $css .= "  background-position: center !important;\n";
            $css .= "  background-repeat: no-repeat !important;\n";
            $css .= "  background-attachment: fixed !important;\n";
            $css .= "}\n";
        }

        // If inheriting fonts, force it on body as well (avoid theme overrides).
        if ( $inherit_fonts ) {
            $css .= "body.sg-mmcs-body{font-family: inherit !important;}\n";
        }

        // Optional custom CSS (strip tags defensively; do not HTML-escape).
        if ( ! empty( $opts['custom_css'] ) ) {
            $custom = wp_strip_all_tags( (string) $opts['custom_css'] );
            $custom = str_replace( [ '</style', '<style' ], '', $custom );
            $css   .= "\n" . trim( $custom ) . "\n";
        }

        return trim( $css );
    }

    /**
     * Enqueue third‑party integrations in a WP.org compliant way (no raw <script> tags).
     */
    private static function enqueue_integrations( array $opts ) {
        // Google Tag Manager
        if ( ! empty( $opts['gtm_id'] ) ) {
            $gtm_id = sanitize_text_field( $opts['gtm_id'] );
            $src    = add_query_arg( 'id', rawurlencode( $gtm_id ), 'https://www.googletagmanager.com/gtm.js' );

            wp_register_script( 'sg-mmcs-gtm', $src, [], SG_MMCS_VERSION, false );
            wp_enqueue_script( 'sg-mmcs-gtm' );

            $inline = "window.dataLayer=window.dataLayer||[];window.dataLayer.push({'gtm.start':new Date().getTime(),event:'gtm.js'});";
            wp_add_inline_script( 'sg-mmcs-gtm', $inline, 'before' );
        }

        // Google Analytics (GA4)
        if ( ! empty( $opts['ga4_id'] ) ) {
            $ga4_id = sanitize_text_field( $opts['ga4_id'] );
            $src    = add_query_arg( 'id', rawurlencode( $ga4_id ), 'https://www.googletagmanager.com/gtag/js' );

            wp_register_script( 'sg-mmcs-ga4', $src, [], SG_MMCS_VERSION, false );
            wp_enqueue_script( 'sg-mmcs-ga4' );

            $inline = "window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js', new Date());gtag('config','" . esc_js( $ga4_id ) . "');";
            wp_add_inline_script( 'sg-mmcs-ga4', $inline, 'after' );
        }

        // Facebook Pixel
        if ( ! empty( $opts['fb_pixel_id'] ) ) {
            $pixel_id = sanitize_text_field( $opts['fb_pixel_id'] );

            wp_register_script( 'sg-mmcs-fbpixel', 'https://connect.facebook.net/en_US/fbevents.js', [], SG_MMCS_VERSION, false );
            wp_enqueue_script( 'sg-mmcs-fbpixel' );

            $inline = "!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];}(window, document,'script');fbq('init','" . esc_js( $pixel_id ) . "');fbq('track','PageView');";
            wp_add_inline_script( 'sg-mmcs-fbpixel', $inline, 'before' );
        }
    }

    /**
     * Shareable preview token (no login required).
     *
     * phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a signed token mechanism; nonce is not applicable.
     */
    public static function maybe_handle_preview_token() {
        $opts = Options::get();
        if ( empty( $opts['preview_links_enabled'] ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Token in URL; verified via transient.
        if ( ! isset( $_GET['sg_mmcs_preview_token'] ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Token in URL; verified via transient.
        $token = sanitize_text_field( wp_unslash( $_GET['sg_mmcs_preview_token'] ) );
        if ( '' === $token ) {
            return;
        }

        $data = get_transient( 'sg_mmcs_preview_token_' . $token );
        if ( ! is_array( $data ) ) {
            return;
        }

        $expires = isset( $data['expires'] ) ? absint( $data['expires'] ) : 0;
        if ( $expires && time() > $expires ) {
            delete_transient( 'sg_mmcs_preview_token_' . $token );
            return;
        }

        $mode = isset( $data['mode'] ) ? sanitize_key( $data['mode'] ) : '';
        $template = isset( $data['template'] ) ? sanitize_text_field( $data['template'] ) : '';

        if ( ! in_array( $mode, [ 'coming_soon', 'maintenance' ], true ) ) {
            return;
        }

        self::$preview_token_active = true;
        $tmpl_key = ( $mode === 'maintenance' ) ? 'maintenance_template' : 'coming_soon_template';
        self::$preview_token_overrides = [
            'mode'        => $mode,
            $tmpl_key     => (string) $template,
            // Force template rendering for previews.
            'page_source' => 'template',
            'page_id'     => 0,
            'landing_id'  => 0,
            // For previews, always avoid indexing.
            'noindex'     => 1,
            'nofollow'    => 1,
        ];

        // Ensure Options::get() returns the preview configuration for this request.
        Options::set_runtime_overrides( array_merge( [ 'http_status' => '200' ], self::$preview_token_overrides ) );
    }

    /**
     * Temporary bypass link token (sets signed bypass cookie).
     */
    public static function maybe_handle_bypass_token() {
        $opts = Options::get();
        if ( empty( $opts['bypass_links_enabled'] ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Token in URL; verified via transient.
        if ( ! isset( $_GET['sg_mmcs_bypass_token'] ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Token in URL; verified via transient.
        $token = sanitize_text_field( wp_unslash( $_GET['sg_mmcs_bypass_token'] ) );
        if ( '' === $token ) {
            return;
        }

        $data = get_transient( 'sg_mmcs_bypass_token_' . $token );
        if ( ! is_array( $data ) ) {
            return;
        }

        $expires = isset( $data['expires'] ) ? absint( $data['expires'] ) : 0;
        if ( $expires && time() > $expires ) {
            delete_transient( 'sg_mmcs_bypass_token_' . $token );
            return;
        }

        $hours = isset( $data['cookie_hours'] ) ? absint( $data['cookie_hours'] ) : (int) ( $opts['bypass_link_duration_hours'] ?? 8 );
        $hours = max( 1, min( 168, $hours ) );

        self::set_bypass_cookie_for_hours( $hours );

        // One-time token.
        delete_transient( 'sg_mmcs_bypass_token_' . $token );

        // Redirect to remove token from URL.
        $url = remove_query_arg( 'sg_mmcs_bypass_token' );
        wp_safe_redirect( $url );
        exit;
    }

    private static function kses_allowed_html() {
        $allowed = wp_kses_allowed_html( 'post' );
        // Allow common head/script tags for admin‑controlled advanced fields.
        $allowed['meta'] = [
            'name' => true,
            'content' => true,
            'charset' => true,
            'http-equiv' => true,
            'property' => true,
        ];
        $allowed['link'] = [
            'rel' => true,
            'href' => true,
            'type' => true,
            'sizes' => true,
            'media' => true,
        ];
        $allowed['script'] = [
            'src' => true,
            'type' => true,
            'id' => true,
            'async' => true,
            'defer' => true,
            'crossorigin' => true,
            'referrerpolicy' => true,
            'integrity' => true,
            'nonce' => true,
        ];
        $allowed['noscript'] = [];
        $allowed['iframe'] = [
            'src' => true,
            'height' => true,
            'width' => true,
            'style' => true,
            'frameborder' => true,
            'allow' => true,
            'allowfullscreen' => true,
            'loading' => true,
            'referrerpolicy' => true,
        ];
        $allowed['img'] = [
            'src' => true,
            'height' => true,
            'width' => true,
            'style' => true,
            'alt' => true,
            'loading' => true,
            'referrerpolicy' => true,
        ];
        $allowed['style'] = [
            'type' => true,
            'id' => true,
        ];
        return $allowed;
    }

    public static function head_extras() {
        if ( ! self::$sg_mmcs_is_rendering ) { return; }

        $opts = Options::get();

        // 1. Robots
        if ( ! empty($opts['noindex']) ) {
            echo "<meta name=\"robots\" content=\"noindex, nofollow\">\n";
        }

        // 2. Favicon
        if ( ! empty($opts['favicon']) ) {
            echo '<link rel="icon" href="' . esc_url($opts['favicon']) . '">' . "\n";
        }

        // 3. OG Tags
        $title = $opts['site_title'] ?: get_bloginfo('name');
        echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
        if ( ! empty($opts['og_image']) ) {
            echo '<meta property="og:image" content="' . esc_url($opts['og_image']) . '">' . "\n";
        }

        // 4. Site Verification / Meta Tags (Easy Fields)
        if ( ! empty($opts['google_site_verification']) ) {
            echo '<meta name="google-site-verification" content="' . esc_attr($opts['google_site_verification']) . '">' . "\n";
        }
        if ( ! empty($opts['facebook_domain_verification']) ) {
            echo '<meta name="facebook-domain-verification" content="' . esc_attr($opts['facebook_domain_verification']) . '">' . "\n";
        }
        if ( ! empty($opts['bing_site_verification']) ) {
            echo '<meta name="msvalidate.01" content="' . esc_attr($opts['bing_site_verification']) . '">' . "\n";
        }
        if ( ! empty( $opts['custom_meta_tags'] ) ) {
            echo wp_kses( (string) $opts['custom_meta_tags'], self::kses_allowed_html() ) . "\n";
        }

        // Custom Header Scripts (Advanced)
        if ( ! empty( $opts['header_scripts'] ) ) {
            echo wp_kses( (string) $opts['header_scripts'], self::kses_allowed_html() ) . "\n";
        }

        
    }

    public static function body_open_extras() {
        if ( ! self::$sg_mmcs_is_rendering ) { return; }

        $opts = Options::get();

        // Google Tag Manager (noscript) - should appear immediately after <body>
        if ( ! empty( $opts['gtm_id'] ) ) {
            $gtm = sanitize_text_field( $opts['gtm_id'] );
            echo '<noscript><iframe src="' . esc_url( 'https://www.googletagmanager.com/ns.html?id=' . rawurlencode( $gtm ) ) . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n";
        }

        // Optional scripts to inject right after <body> (Advanced)
        if ( ! empty( $opts['body_scripts'] ) ) {
            echo wp_kses( (string) $opts['body_scripts'], self::kses_allowed_html() ) . "\n";
        }
    }

    public static function footer_extras() {
        if ( ! self::$sg_mmcs_is_rendering ) { return; }
        
        $opts = Options::get();

        // Facebook Pixel noscript image (if configured).
        if ( ! empty( $opts['fb_pixel_id'] ) ) {
            $pid = sanitize_text_field( $opts['fb_pixel_id'] );
            echo '<noscript><img height="1" width="1" style="display:none" alt="" src="' . esc_url( 'https://www.facebook.com/tr?id=' . rawurlencode( $pid ) . '&ev=PageView&noscript=1' ) . '"/></noscript>' . "\n";
        }

        if ( ! empty( $opts['footer_scripts'] ) ) {
            echo wp_kses( (string) $opts['footer_scripts'], self::kses_allowed_html() ) . "\n";
        }
    }

    public static function maybe_set_bypass_cookie() {
        $opts = Options::get();
        if ( empty($opts['secret_bypass_key']) ) return;

        $param = $opts['secret_bypass_param'] ?: 'sg_access';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a secret token parameter; nonce is not applicable.
        $val = isset( $_GET[ $param ] ) ? sanitize_text_field( wp_unslash( $_GET[ $param ] ) ) : '';
        if ( ! $val ) return;

        if ( hash_equals( (string)$opts['secret_bypass_key'], (string)$val ) ) {
            self::set_bypass_cookie( 1 );
        }
    }

    public static function maybe_handle_password() {
        $opts = Options::get();
        if ( empty($opts['password_protect']) ) return;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Logout is a simple bypass-cookie clear for this plugin page.
        if ( isset( $_GET['sg_mmcs_logout'] ) ) {
            self::set_bypass_cookie( 0 );
            return;
        }

        if ( isset( $_POST['sg_mmcs_password_nonce'], $_POST['sg_mmcs_password'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified within this block.
            // CSRF protection.
            if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sg_mmcs_password_nonce'] ) ), 'sg_mmcs_password' ) ) {
                return;
            }

            $pwd = sanitize_text_field( wp_unslash( $_POST['sg_mmcs_password'] ) );
            if ( '' === $pwd ) {
                return;
            }

            if ( ! empty( $opts['access_password'] ) && hash_equals( (string) $opts['access_password'], $pwd ) ) {
                self::set_bypass_cookie( 1 );
            } else {
                add_filter( 'sg_mmcs_password_error', function() {
                    return __( 'Incorrect password.', 'softglaze-maintenance-mode-coming-soon' );
                } );
            }
        }
    }

    private static function set_bypass_cookie( $allowed ) {
        if ( $allowed ) {
            self::set_bypass_cookie_for_hours( 24 );
            return;
        }

        // Clear cookie.
        setcookie( self::COOKIE_BYPASS, '0', time() - HOUR_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );
        $_COOKIE[ self::COOKIE_BYPASS ] = '0';
    }

    private static function set_bypass_cookie_for_hours( $hours ) {
        $hours = max( 1, min( 168, absint( $hours ) ) );
        $exp = time() + ( $hours * HOUR_IN_SECONDS );
        $value = self::build_signed_bypass_cookie_value( $exp );

        // CookiePath/Domain are WP constants.
        setcookie( self::COOKIE_BYPASS, $value, $exp, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );
        $_COOKIE[ self::COOKIE_BYPASS ] = $value;
    }

    private static function build_signed_bypass_cookie_value( $expires_ts ) {
        $expires_ts = absint( $expires_ts );
        $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
        $payload = '1|' . $expires_ts;
        $sig = hash_hmac( 'sha256', $payload . '|' . $ua, wp_salt( 'auth' ) );
        return $payload . '|' . $sig;
    }

    private static function bypass_cookie_is_valid( $cookie_value ) {
        $cookie_value = (string) $cookie_value;
        if ( '1' === $cookie_value ) {
            // Backwards compatibility.
            return true;
        }

        if ( 0 !== strpos( $cookie_value, '1|' ) ) {
            return false;
        }

        $parts = explode( '|', $cookie_value );
        if ( 3 !== count( $parts ) ) {
            return false;
        }

        $exp = absint( $parts[1] );
        $sig = (string) $parts[2];
        if ( ! $exp || time() > $exp ) {
            return false;
        }

        $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
        $expected = hash_hmac( 'sha256', '1|' . $exp . '|' . $ua, wp_salt( 'auth' ) );
        return hash_equals( $expected, $sig );
    }

    public static function maybe_intercept() {
        $opts = Options::get();

        if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return;
        }

        $is_admin_preview = is_user_logged_in() && current_user_can( 'manage_options' )
            && isset( $_GET['sg_mmcs_preview'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin-only preview toggle; no state change.
            && (string) sanitize_key( wp_unslash( $_GET['sg_mmcs_preview'] ) ) === '1'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $force_preview = $is_admin_preview || self::$preview_token_active;

        // If mode is off, only intercept when an admin explicitly requests a preview.
        if ( $opts['mode'] === 'off' && ! $force_preview ) {
            return;
        }

        // Admin preview can override mode/template without saving.
        if ( $is_admin_preview ) {
            $overrides = [
                'http_status' => '200',
                'page_source' => 'template',
                'page_id'     => 0,
                'landing_id'  => 0,
            ];

            $preview_mode = '';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin-only preview; no state change.
            if ( isset( $_GET['sg_mmcs_preview_mode'] ) ) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $preview_mode = sanitize_key( wp_unslash( $_GET['sg_mmcs_preview_mode'] ) );
            }

            if ( in_array( $preview_mode, [ 'coming_soon', 'maintenance' ], true ) ) {
                $overrides['mode'] = $preview_mode;
            } else {
                $overrides['mode'] = ( $opts['mode'] === 'off' ) ? 'coming_soon' : $opts['mode'];
            }

            $preview_template = 0;
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin-only preview; no state change.
            if ( isset( $_GET['sg_mmcs_preview_template'] ) ) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $preview_template = absint( wp_unslash( $_GET['sg_mmcs_preview_template'] ) );
            }

            if ( $preview_template > 0 ) {
                if ( $overrides['mode'] === 'maintenance' ) {
                    if ( in_array( (string) $preview_template, [ '1', '2', '3', '4', '5', '6' ], true ) ) {
                        $overrides['maintenance_template'] = (string) $preview_template;
                    }
                } else {
                    if ( in_array( (string) $preview_template, [ '1', '2', '3', '4' ], true ) ) {
                        $overrides['coming_soon_template'] = (string) $preview_template;
                    }
                }
            }

            Options::set_runtime_overrides( $overrides );
            $opts = Options::get();
        }

        if ( ! $force_preview ) {
            $uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
            $path = wp_parse_url( $uri, PHP_URL_PATH );
            $path = $path ? (string) $path : '/';

            if ( self::path_matches_list( $path, $opts['exclude_urls'] ) ) {
                return;
            }

            if ( ! empty( $opts['include_urls_only'] ) && ! self::path_matches_list( $path, $opts['include_urls_only'] ) ) {
                return;
            }

            if ( preg_match( '#^/wp-admin/#', $path ) || $path === '/wp-login.php' ) {
                return;
            }

            if ( ! empty( $opts['woocommerce_safe_mode'] ) && self::is_woocommerce_safe_path( $path, $uri ) ) {
                return;
            }
        }

        if ( ! $force_preview && self::has_bypass_access( $opts ) ) {
            return;
        }


        if ( $opts['http_status'] === '503' ) {
            status_header( 503 );
            if ( ! headers_sent() ) header( 'Retry-After: ' . absint( $opts['retry_after'] ) );
        } else {
            status_header( 200 );
        }

        self::$sg_mmcs_is_rendering = true;

// --- FIX: Setup Post Data for Page/Landing Selection ---

        if ( $opts['page_source'] === 'page' && ! empty($opts['page_id']) ) {
            $page_id = (int) $opts['page_id'];
            $post = get_post( $page_id );
            if ( $post ) {
                // Render using the theme's page template so Elementor/Block Editor/etc behaves
                // exactly as it does on the real page (canvas templates, builder assets, etc).
                self::$sg_mmcs_is_custom_page = true;
                self::setup_virtual_page($post);

                if ( self::render_via_theme_template( $post ) ) {
                    exit;
                }

                // Fallback: minimal wrapper
                self::render_wp_page( $post );
                exit;
            }
        }

        if ( $opts['page_source'] === 'landing' && ! empty($opts['landing_id']) ) {
            $landing_id = (int) $opts['landing_id'];
            if ( Landing::is_valid( $landing_id ) ) {
                $post = get_post( $landing_id );
                if ( $post ) {
                    self::$sg_mmcs_is_custom_page = true;
                    self::setup_virtual_page($post);

                    if ( self::render_via_theme_template( $post ) ) {
                        exit;
                    }

                    self::render_landing( $post );
                    exit;
                }
            }
        }

        $template = $opts['mode'] === 'maintenance' ? 'maintenance.php' : 'coming-soon.php';

        if ( ! empty( $opts['analytics_enabled'] ) ) {
            $is_admin = is_user_logged_in() && current_user_can( 'manage_options' );
            if ( ! $is_admin && ! self::$preview_token_active ) {
                self::analytics_increment( 'view', $opts['mode'] );
            }
        }

        include SG_MMCS_PLUGIN_DIR . 'templates/' . $template;
        exit;
    }

    // Helper to trick WP into thinking we are on a real page
    private static function setup_virtual_page($p) {
        global $wp_query, $post;

        // Core flags
        $wp_query->is_home = false;
        $wp_query->is_archive = false;
        $wp_query->is_category = false;
        $wp_query->is_404 = false;

        $wp_query->is_singular = true;
        $wp_query->is_page = ( $p->post_type === 'page' );
        $wp_query->is_single = ( $p->post_type !== 'page' );

        // Populate query objects
        $wp_query->queried_object = $p;
        $wp_query->queried_object_id = $p->ID;
        $wp_query->post = $p;
        $wp_query->posts = [ $p ];
        $wp_query->post_count = 1;
        $wp_query->found_posts = 1;
        $wp_query->max_num_pages = 1;

        // Ensure global $post is set for template functions (get_page_template, etc)
        $post = $p;

        // This is crucial for builders/blocks
        setup_postdata($p);
    }

    // Attempt to render the selected page using the active theme template.
    // This makes custom-built pages (Gutenberg, Elementor, custom templates, etc) behave normally.
    private static function render_via_theme_template( $post ) {
        $template = '';

        if ( $post->post_type === 'page' ) {
            $template = get_page_template();
        } else {
            // For CPTs (including our Landing CPT), let WP locate the correct single template.
            $template = get_single_template();
        }

        if ( $template && file_exists( $template ) ) {
            include $template;
            return true;
        }

        return false;
    }

    private static function render_wp_page( $post ) {
        ?>
        <!doctype html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html(get_bloginfo('name')); ?></title>
            <?php wp_head(); ?>
        </head>
        <body class="sg-mmcs-body sg-page-view <?php echo esc_attr( 'page-id-' . absint( $post->ID ) ); ?>">
            <?php wp_body_open(); ?>
            <div class="sg-mmcs-page-content">
                <?php 
                    // Apply filters manually if needed; 'the_content' returns HTML.
                    echo apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.
                ?>
            </div>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }

    private static function render_landing( $post ) {
        ?>
        <!doctype html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html($post->post_title); ?></title>
            <?php wp_head(); ?>
        </head>
        <body class="sg-mmcs-body sg-landing-view">
            <?php wp_body_open(); ?>
            <div class="sg-mmcs-page-content">
                <?php
                echo apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.
                ?>
            </div>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }

    private static function has_bypass_access( $opts ) {
        if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) return true;

        $cookie_bypass = isset( $_COOKIE[ self::COOKIE_BYPASS ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_BYPASS ] ) ) : '';
        if ( $cookie_bypass && self::bypass_cookie_is_valid( $cookie_bypass ) ) {
            return true;
        }

        if ( is_user_logged_in() ) {
            if ( ! empty( $opts['bypass_logged_in'] ) ) return true;
            
            $user = wp_get_current_user();
            
            // 1. Check Specific Users Whitelist
            if ( ! empty($opts['bypass_users']) && in_array( $user->ID, (array)$opts['bypass_users'] ) ) {
                return true;
            }

            // 2. Check Roles Whitelist
            foreach ( (array) ( $opts['bypass_roles'] ?? [] ) as $role ) {
                if ( in_array( $role, (array) $user->roles, true ) ) return true;
            }
        }

        $ip = self::get_ip();
        if ( $ip && self::ip_in_allowlist( $ip, $opts['ip_allowlist'] ) ) return true;

        return false;
    }

    private static function get_ip() {
        return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
    }

    private static function ip_in_allowlist( $ip, $list_text ) {
        $lines = preg_split('/\r\n|\r|\n/', (string)$list_text);
        $lines = array_filter(array_map('trim', $lines));
        foreach ($lines as $line) {
            if ( strpos($line, '/') !== false ) {
                if ( self::cidr_match($ip, $line) ) return true;
            } else {
                if ( $ip === $line ) return true;
            }
        }
        return false;
    }

    private static function cidr_match($ip, $cidr) {
        if ( ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ) return false;
        if ( ! preg_match('/^([0-9\.]+)\/(\d{1,2})$/', $cidr, $m) ) return false;
        $subnet = $m[1]; $mask = (int)$m[2];
        if ( $mask < 0 || $mask > 32 ) return false;
        $ip_long = ip2long($ip);
        $subnet_long = ip2long($subnet);
        $mask_long = -1 << (32 - $mask);
        $subnet_long &= $mask_long;
        return ($ip_long & $mask_long) === $subnet_long;
    }

    private static function is_woocommerce_safe_path( $path, $uri ) {
        $path = (string) $path;
        $uri  = (string) $uri;

        // Common WooCommerce pages.
        foreach ( [ '/cart', '/checkout', '/my-account' ] as $prefix ) {
            if ( $path === $prefix || 0 === strpos( $path, $prefix . '/' ) ) {
                return true;
            }
        }

        // WooCommerce AJAX endpoints.
        if ( false !== strpos( $uri, 'wc-ajax=' ) ) {
            return true;
        }

        // WooCommerce API endpoints.
        if ( 0 === strpos( $path, '/wc-api' ) || 0 === strpos( $path, '/wp-json/wc' ) ) {
            return true;
        }

        return false;
    }

    private static function path_matches_list( $path, $list_text ) {
        $lines = preg_split('/\r\n|\r|\n/', (string)$list_text);
        $lines = array_filter(array_map('trim', $lines));
        foreach ($lines as $prefix) {
            if ($prefix === '') continue;
            if ($prefix[0] !== '/') $prefix = '/' . $prefix;
            if ( strpos($path, $prefix) === 0 ) return true;
        }
        return false;
    }

    private static function analytics_default() {
        return [
            'views_total'  => 0,
            'subs_total'   => 0,
            'views_by_day' => [],
            'subs_by_day'  => [],
        ];
    }

    private static function analytics_increment( $type, $mode ) {
        $type = (string) $type;
        $mode = (string) $mode;

        $analytics = get_option( 'sg_mmcs_analytics', [] );
        if ( ! is_array( $analytics ) ) {
            $analytics = [];
        }
        $analytics = array_merge( self::analytics_default(), $analytics );

        $day = wp_date( 'Y-m-d', time(), wp_timezone() );
        if ( 'subscribe' === $type ) {
            $analytics['subs_total'] = absint( $analytics['subs_total'] ) + 1;
            if ( empty( $analytics['subs_by_day'][ $day ] ) ) {
                $analytics['subs_by_day'][ $day ] = 0;
            }
            $analytics['subs_by_day'][ $day ] = absint( $analytics['subs_by_day'][ $day ] ) + 1;
        } else {
            $analytics['views_total'] = absint( $analytics['views_total'] ) + 1;
            if ( empty( $analytics['views_by_day'][ $day ] ) ) {
                $analytics['views_by_day'][ $day ] = 0;
            }
            $analytics['views_by_day'][ $day ] = absint( $analytics['views_by_day'][ $day ] ) + 1;
        }

        // Keep last 60 days to prevent unbounded growth.
        foreach ( [ 'views_by_day', 'subs_by_day' ] as $k ) {
            if ( ! is_array( $analytics[ $k ] ) ) {
                $analytics[ $k ] = [];
                continue;
            }
            ksort( $analytics[ $k ] );
            if ( count( $analytics[ $k ] ) > 60 ) {
                $analytics[ $k ] = array_slice( $analytics[ $k ], -60, null, true );
            }
        }

        update_option( 'sg_mmcs_analytics', $analytics, false );
    }

    public static function shortcode_subscribe( $atts = [] ) {
        ob_start();
        include SG_MMCS_PLUGIN_DIR . 'templates/partials/subscribe-form.php';
        return ob_get_clean();
    }

    public static function ajax_subscribe() {
        check_ajax_referer('sg_mmcs_subscribe', 'nonce');

        $opts = Options::get();

        $hp = $opts['honeypot_field'] ?: 'company';
        $hp_val = isset( $_POST[ $hp ] ) ? sanitize_text_field( wp_unslash( $_POST[ $hp ] ) ) : '';
        if ( ! empty( $hp_val ) ) {
            wp_send_json_error(['message' => __('Spam detected.', 'softglaze-maintenance-mode-coming-soon')], 400);
        }

        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
        $limit = absint($opts['rate_limit_per_ip_per_hour'] ?? 10);
        $key = 'sg_mmcs_rl_' . md5($ip ?: 'unknown');
        $count = (int) get_transient($key);
        if ( $count >= $limit ) {
            wp_send_json_error(['message' => __('Too many attempts. Try again later.', 'softglaze-maintenance-mode-coming-soon')], 429);
        }
        set_transient($key, $count + 1, HOUR_IN_SECONDS);

        $email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        if ( ! is_email($email) ) {
            wp_send_json_error(['message' => __('Please enter a valid email.', 'softglaze-maintenance-mode-coming-soon')], 400);
        }

        $source = esc_url_raw( wp_unslash( $_POST['source_url'] ?? ( wp_get_referer() ?: home_url('/') ) ) );

        // Extract Custom Form Fields
        $meta_data = [ 'mode' => $opts['mode'] ];
        if ( ! empty($opts['form_fields']) && is_array($opts['form_fields']) ) {
            foreach ( $opts['form_fields'] as $field ) {
                $k = $field['key'];
                if ( $k === 'email' ) continue; // Already handled
                
                if ( isset($_POST[$k]) ) {
                    $meta_data[ $k ] = sanitize_text_field( wp_unslash( $_POST[ $k ] ) );
                }
            }
        }

        if ( ! empty($opts['store_subscribers']) ) {
            Subscribers::add($email, [
                'ip' => $ip,
                'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
                'source_url' => $source,
                'meta' => $meta_data,
            ]);
        }

        if ( ! empty($opts['notify_admin']) ) {
            $to = $opts['admin_notify_email'] ?: get_option('admin_email');
            if ( is_email($to) ) {
                $subject = 'New Subscriber';
                $body = "Email: {$email}\n";
                foreach($meta_data as $mk => $mv) {
                    $body .= ucfirst($mk) . ": {$mv}\n";
                }
                $body .= "Source: {$source}\nIP: {$ip}";
                wp_mail( $to, $subject, $body );
            }
        }

        // Webhook
        if ( ! empty($opts['webhook_url']) ) {
            $payload = array_merge([
                'email' => $email,
                'created_at' => current_time('c'),
                'ip' => $ip,
                'source_url' => $source,
            ], $meta_data);

            wp_remote_post( $opts['webhook_url'], [
                'timeout' => 10,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => wp_json_encode($payload),
            ]);
        }

        // Mailchimp
        if ( ! empty($opts['mailchimp_api_key']) && ! empty($opts['mailchimp_list_id']) && ! empty($opts['mailchimp_dc']) ) {
            self::mailchimp_subscribe($email, $meta_data, $opts);
        }

        if ( ! empty( $opts['analytics_enabled'] ) ) {
            $is_admin = is_user_logged_in() && current_user_can( 'manage_options' );
            if ( ! $is_admin ) {
                self::analytics_increment( 'subscribe', $opts['mode'] );
            }
        }

        wp_send_json_success(['message' => __('Thanks! You have been subscribed.', 'softglaze-maintenance-mode-coming-soon')]);
    }

    private static function mailchimp_subscribe($email, $meta, $opts) {
        $dc = $opts['mailchimp_dc'];
        $list = $opts['mailchimp_list_id'];
        $api = $opts['mailchimp_api_key'];

        $subscriber_hash = md5(strtolower($email));
        $url = "https://{$dc}.api.mailchimp.com/3.0/lists/{$list}/members/{$subscriber_hash}";
        
        // Map common merge fields if they exist in meta
        $merge_fields = [];
        if (isset($meta['fname'])) $merge_fields['FNAME'] = $meta['fname'];
        elseif (isset($meta['first_name'])) $merge_fields['FNAME'] = $meta['first_name'];
        elseif (isset($meta['name'])) $merge_fields['FNAME'] = $meta['name']; // Fallback
        
        if (isset($meta['lname'])) $merge_fields['LNAME'] = $meta['lname'];
        elseif (isset($meta['last_name'])) $merge_fields['LNAME'] = $meta['last_name'];
        
        if (isset($meta['phone'])) $merge_fields['PHONE'] = $meta['phone'];
        
        $body = [
            'email_address' => $email,
            'status_if_new' => 'subscribed',
            'status' => 'subscribed',
            'merge_fields' => (object)$merge_fields
        ];

        wp_remote_request( $url, [
            'method' => 'PUT',
            'timeout' => 10,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode('user:' . $api),
            ],
            'body' => wp_json_encode($body),
        ]);
    }
}