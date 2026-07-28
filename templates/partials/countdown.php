<?php
/**
 * Countdown widget (front-end).
 *
 * @package SoftGlaze\MMCS
 */

use SoftGlaze\MMCS\Options;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$sg_mmcs_opts  = Options::get();
$sg_mmcs_style = ! empty( $sg_mmcs_opts['countdown_style'] ) ? sanitize_key( (string) $sg_mmcs_opts['countdown_style'] ) : 'simple';
?>
<div class="sg-countdown sg-cd-<?php echo esc_attr( $sg_mmcs_style ); ?>" data-date="<?php echo esc_attr( (string) $sg_mmcs_opts['countdown_date'] ); ?>">
    <?php
    $sg_mmcs_units = [
        'd' => __( 'Days', 'softglaze-maintenance-mode-coming-soon' ),
        'h' => __( 'Hours', 'softglaze-maintenance-mode-coming-soon' ),
        'm' => __( 'Minutes', 'softglaze-maintenance-mode-coming-soon' ),
        's' => __( 'Seconds', 'softglaze-maintenance-mode-coming-soon' ),
    ];

    $sg_mmcs_is_circle = ( $sg_mmcs_style === 'circle' );
    $sg_mmcs_counter   = 0;

    foreach ( $sg_mmcs_units as $sg_mmcs_k => $sg_mmcs_label ) :
        $sg_mmcs_counter++;
        ?>
        <div class="sg-cd-item">
            <div class="sg-cd-val-wrap">
                <span class="sg-cd-num" data-k="<?php echo esc_attr( $sg_mmcs_k ); ?>">00</span>
                <span class="sg-cd-layer sg-layer-1" data-k="<?php echo esc_attr( $sg_mmcs_k ); ?>" aria-hidden="true">00</span>
                <span class="sg-cd-layer sg-layer-2" data-k="<?php echo esc_attr( $sg_mmcs_k ); ?>" aria-hidden="true">00</span>

                <?php if ( $sg_mmcs_is_circle ) : ?>
                    <svg class="sg-cd-ring" viewBox="0 0 100 100" aria-hidden="true" focusable="false">
                        <circle class="sg-cd-bg" cx="50" cy="50" r="45"></circle>
                        <circle class="sg-cd-prog" cx="50" cy="50" r="45" data-k="<?php echo esc_attr( $sg_mmcs_k ); ?>-ring"></circle>
                    </svg>
                <?php endif; ?>
            </div>
            <div class="sg-cd-lbl"><?php echo esc_html( $sg_mmcs_label ); ?></div>
        </div>

        <?php
        // Separators for specific styles.
        if ( $sg_mmcs_counter < 4 && ! in_array( $sg_mmcs_style, [ 'boxed', 'circle', 'pill' ], true ) ) :
            ?>
            <div class="sg-cd-sep">:</div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<div class="sg-countdown-msg" style="display:none;">
    <?php echo esc_html( (string) $sg_mmcs_opts['countdown_finished_message'] ); ?>
</div>
