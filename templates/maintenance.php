<?php
use SoftGlaze\MMCS\Options;


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
$opts = Options::get();
$title = $opts['site_title'] ?: get_bloginfo('name');
$tmpl = $opts['maintenance_template'] ?: '1'; // 1=GraphicLeft, 2=Card, 3=Dark, 4=Simple, 5=Gradient Spotlight, 6=Glass Panel

// --- Logic: Auto-Detect Logo ---
$logo_url = $opts['logo_url'];

// 1. If no plugin logo, check Customizer Logo
if ( empty($logo_url) ) {
    $custom_logo_id = get_theme_mod( 'custom_logo' );
    if ( $custom_logo_id ) {
        $logo_url = wp_get_attachment_image_url( $custom_logo_id, 'full' );
    }
}

// 2. If still empty, check Site Icon (Favicon)
if ( empty($logo_url) ) {
    $logo_url = get_site_icon_url( 512 ); // Get high-res if available
}

// Helper to render form based on selection (Built-in vs Shortcode)
$render_form = function() use ($opts) {
    if ( empty($opts['show_subscribe']) ) return;
    
    if ( isset($opts['form_type']) && $opts['form_type'] === 'shortcode' && !empty($opts['form_shortcode']) ) {
        echo '<div class="sg-mmcs-custom-form">';
        echo do_shortcode( wp_kses_post( $opts['form_shortcode'] ) );
        echo '</div>';
    } else {
        include SG_MMCS_PLUGIN_DIR . 'templates/partials/subscribe-form.php';
    }
};

$layout_class = 'sg-layout-' . $tmpl;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo esc_html($title); ?></title>
  <?php wp_head(); ?>
</head>
<body class="sg-mmcs-body sg-maintenance <?php echo esc_attr($layout_class); ?>">
  <?php wp_body_open(); ?>

<?php if ( $tmpl === '1' ) : /* --- LAYOUT 1: GRAPHIC LEFT --- */ ?>
<div class="sg-maint-split">
    <div class="sg-maint-image">
        <div class="sg-maint-overlay"></div>
    </div>
    <div class="sg-maint-content">
        <div class="sg-inner-content">
            <?php if (!empty($logo_url)): ?>
                <img class="sg-logo" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
            <?php endif; ?>

            <h1 class="sg-headline"><?php echo esc_html($opts['headline']); ?></h1>
            <div class="sg-message"><?php echo wp_kses_post( wpautop($opts['message']) ); ?></div>

            <?php $render_form(); ?>

            <?php if (!empty($opts['show_social'])): ?>
                 <div class="sg-social-wrapper">
                    <?php include SG_MMCS_PLUGIN_DIR . 'templates/partials/social-icons.php'; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($opts['password_protect'])): ?>
            <div class="sg-footer-links">
                 <a href="#" class="sg-access-trigger"><?php esc_html_e('Staff Login', 'softglaze-maintenance-mode-coming-soon'); ?></a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php elseif ( $tmpl === '2' ) : /* --- LAYOUT 2: CLEAN CARD --- */ ?>
<div class="sg-maint-card-wrap">
    <div class="sg-maint-card">
        <?php if (!empty($logo_url)): ?>
            <img class="sg-logo" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
        <?php endif; ?>

        <div class="sg-icon-header">
            <span class="dashicons dashicons-hammer"></span>
        </div>

        <h1 class="sg-headline"><?php echo esc_html($opts['headline']); ?></h1>
        <div class="sg-message"><?php echo wp_kses_post( wpautop($opts['message']) ); ?></div>

        <?php $render_form(); ?>

        <?php if (!empty($opts['show_social'])): ?>
             <div class="sg-social-wrapper">
                <?php include SG_MMCS_PLUGIN_DIR . 'templates/partials/social-icons.php'; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php if (!empty($opts['password_protect'])): ?>
        <div class="sg-access-bar">
            <a href="#" class="sg-access-trigger"><?php esc_html_e('Staff Access', 'softglaze-maintenance-mode-coming-soon'); ?></a>
        </div>
    <?php endif; ?>
</div>

<?php elseif ( $tmpl === '3' ) : /* --- LAYOUT 3: DARK MODE --- */ ?>
<div class="sg-maint-dark">
    <div class="sg-maint-dark-inner">
        <?php if (!empty($logo_url)): ?>
            <img class="sg-logo" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
        <?php endif; ?>

        <h1 class="sg-headline"><?php echo esc_html($opts['headline']); ?></h1>
        <div class="sg-message"><?php echo wp_kses_post( wpautop($opts['message']) ); ?></div>
        
        <div class="sg-divider"></div>

        <?php $render_form(); ?>

        <?php if (!empty($opts['show_social'])): ?>
             <div class="sg-social-wrapper">
                <?php include SG_MMCS_PLUGIN_DIR . 'templates/partials/social-icons.php'; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($opts['password_protect'])): ?>
            <div class="sg-footer-links">
                 <a href="#" class="sg-access-trigger"><?php esc_html_e('Protected Access', 'softglaze-maintenance-mode-coming-soon'); ?></a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php elseif ( $tmpl === '4' ) : /* --- LAYOUT 4: SIMPLE TEXT --- */ ?>
<div class="sg-maint-simple">
    <div class="sg-container">
        <?php if (!empty($logo_url)): ?>
            <img class="sg-logo" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
        <?php endif; ?>

        <h1 class="sg-headline"><?php echo esc_html($opts['headline']); ?></h1>
        <div class="sg-message"><?php echo wp_kses_post( wpautop($opts['message']) ); ?></div>

        <div class="sg-form-inline">
            <?php $render_form(); ?>
        </div>

        <?php if (!empty($opts['show_social'])): ?>
             <div class="sg-social-wrapper">
                <?php include SG_MMCS_PLUGIN_DIR . 'templates/partials/social-icons.php'; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($opts['password_protect'])): ?>
            <div class="sg-simple-footer">
                 <a href="#" class="sg-access-trigger"><span class="dashicons dashicons-lock"></span></a>
            </div>
        <?php endif; ?>
    </div>
</div>



<?php elseif ( $tmpl === '5' ) : /* --- LAYOUT 5: GRADIENT SPOTLIGHT --- */ ?>
<div class="sg-maint-spotlight">
    <div class="sg-spotlight-bg"></div>
    <div class="sg-spotlight-card">
        <?php if (!empty($logo_url)): ?>
            <img class="sg-logo" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
        <?php endif; ?>

        <div class="sg-badge">
            <span class="dashicons dashicons-admin-tools"></span>
            <span><?php esc_html_e('Maintenance', 'softglaze-maintenance-mode-coming-soon'); ?></span>
        </div>

        <h1 class="sg-headline"><?php echo esc_html($opts['headline']); ?></h1>
        <div class="sg-message"><?php echo wp_kses_post( wpautop($opts['message']) ); ?></div>

        <?php $render_form(); ?>

        <?php if (!empty($opts['show_social'])): ?>
            <div class="sg-social-wrapper">
                <?php include SG_MMCS_PLUGIN_DIR . 'templates/partials/social-icons.php'; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($opts['password_protect'])): ?>
            <div class="sg-footer-links">
                <a href="#" class="sg-access-trigger"><?php esc_html_e('Staff Login', 'softglaze-maintenance-mode-coming-soon'); ?></a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php elseif ( $tmpl === '6' ) : /* --- LAYOUT 6: MINIMAL GLASS PANEL --- */ ?>
<div class="sg-maint-glass">
    <div class="sg-glass-panel">
        <div class="sg-glass-top">
            <?php if (!empty($logo_url)): ?>
                <img class="sg-logo" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
            <?php endif; ?>
            <span class="sg-glass-dot"></span>
        </div>

        <h1 class="sg-headline"><?php echo esc_html($opts['headline']); ?></h1>
        <div class="sg-message"><?php echo wp_kses_post( wpautop($opts['message']) ); ?></div>

        <?php $render_form(); ?>

        <?php if (!empty($opts['show_social'])): ?>
            <div class="sg-social-wrapper">
                <?php include SG_MMCS_PLUGIN_DIR . 'templates/partials/social-icons.php'; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($opts['password_protect'])): ?>
            <div class="sg-footer-links">
                 <a href="#" class="sg-access-trigger"><?php esc_html_e('Protected Access', 'softglaze-maintenance-mode-coming-soon'); ?></a>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($opts['password_protect'])): ?>
<div class="sg-access-modal" id="sg-access-modal">
    <div class="sg-access-modal-content">
        <button class="sg-modal-close">&times;</button>
        <h3><?php esc_html_e('Restricted Access', 'softglaze-maintenance-mode-coming-soon'); ?></h3>
        <?php $err = apply_filters('sg_mmcs_password_error', ''); ?>
        <?php if ($err): ?><div class="sg-error-msg"><?php echo esc_html($err); ?></div><?php endif; ?>
        <form method="post">
            <?php wp_nonce_field( 'sg_mmcs_password', 'sg_mmcs_password_nonce' ); ?>
            <input type="password" name="sg_mmcs_password" placeholder="<?php echo esc_attr__( 'Enter password', 'softglaze-maintenance-mode-coming-soon' ); ?>" autofocus>
            <button type="submit"><?php esc_html_e( 'Enter', 'softglaze-maintenance-mode-coming-soon' ); ?></button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ( ! empty( $opts['custom_html'] ) ) : ?>
    <div class="sg-custom-html"><?php echo wp_kses_post( (string) $opts['custom_html'] ); ?></div>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
