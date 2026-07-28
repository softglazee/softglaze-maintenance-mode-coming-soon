<?php
use SoftGlaze\MMCS\Options;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$opts = Options::get();
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
?>

<?php if (!empty($logo_url)): ?>
  <img class="sg-mmcs-logo" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
<?php endif; ?>

<h1 class="sg-mmcs-headline"><?php echo esc_html($opts['headline']); ?></h1>
<div class="sg-mmcs-message"><?php echo wp_kses_post( wpautop($opts['message']) ); ?></div>

<?php if (!empty($opts['show_countdown']) && !empty($opts['countdown_date'])): ?>
  <div class="sg-mmcs-countdown" data-date="<?php echo esc_attr($opts['countdown_date']); ?>">
    <div class="sg-mmcs-cd-item"><div class="sg-mmcs-cd-num" data-k="d">0</div><div class="sg-mmcs-cd-lbl">Days</div></div>
    <div class="sg-mmcs-cd-item"><div class="sg-mmcs-cd-num" data-k="h">00</div><div class="sg-mmcs-cd-lbl">Hr</div></div>
    <div class="sg-mmcs-cd-item"><div class="sg-mmcs-cd-num" data-k="m">00</div><div class="sg-mmcs-cd-lbl">Min</div></div>
    <div class="sg-mmcs-cd-item"><div class="sg-mmcs-cd-num" data-k="s">00</div><div class="sg-mmcs-cd-lbl">Sec</div></div>
  </div>
<?php endif; ?>

<?php if (!empty($opts['show_subscribe'])): ?>
  <?php include SG_MMCS_PLUGIN_DIR . 'templates/partials/subscribe-form.php'; ?>
<?php endif; ?>

<?php if (!empty($opts['show_social']) && !empty($opts['social_icons'])): ?>
  <div class="sg-mmcs-social">
    <?php include SG_MMCS_PLUGIN_DIR . 'templates/partials/social-icons.php'; ?>
  </div>
<?php endif; ?>
