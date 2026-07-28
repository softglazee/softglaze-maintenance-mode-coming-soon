<?php
/**
 * Built-in subscribe form (front-end).
 *
 * @package SoftGlaze\MMCS
 */

use SoftGlaze\MMCS\Options;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$sg_mmcs_opts   = Options::get();
$sg_mmcs_hp     = ! empty( $sg_mmcs_opts['honeypot_field'] ) ? (string) $sg_mmcs_opts['honeypot_field'] : 'company';
$sg_mmcs_fields = ! empty( $sg_mmcs_opts['form_fields'] ) && is_array( $sg_mmcs_opts['form_fields'] ) ? $sg_mmcs_opts['form_fields'] : [];
?>
<div class="sg-mmcs-subscribe">
    <?php if ( ! empty( $sg_mmcs_opts['subscribe_title'] ) ) : ?>
        <div class="sg-mmcs-subscribe-title"><?php echo esc_html( $sg_mmcs_opts['subscribe_title'] ); ?></div>
    <?php endif; ?>

    <form class="sg-mmcs-form" method="post" action="#">
        <div class="sg-mmcs-fields-wrap">
            <?php foreach ( $sg_mmcs_fields as $sg_mmcs_field ) : ?>
                <?php
                $sg_mmcs_key  = isset( $sg_mmcs_field['key'] ) ? sanitize_key( (string) $sg_mmcs_field['key'] ) : '';
                $sg_mmcs_type = isset( $sg_mmcs_field['type'] ) ? sanitize_key( (string) $sg_mmcs_field['type'] ) : 'text';
                if ( ! in_array( $sg_mmcs_type, [ 'text', 'email', 'tel' ], true ) ) {
                    $sg_mmcs_type = 'text';
                }

                $sg_mmcs_label = isset( $sg_mmcs_field['label'] ) ? (string) $sg_mmcs_field['label'] : '';
                $sg_mmcs_placeholder = isset( $sg_mmcs_field['placeholder'] ) ? (string) $sg_mmcs_field['placeholder'] : '';
                if ( $sg_mmcs_placeholder === '' ) {
                    $sg_mmcs_placeholder = $sg_mmcs_label;
                }

                $sg_mmcs_is_required = ! empty( $sg_mmcs_field['required'] );

                $sg_mmcs_width = ( isset( $sg_mmcs_field['width'] ) && (string) $sg_mmcs_field['width'] === '50' ) ? 'sg-col-50' : 'sg-col-100';
                $sg_mmcs_cls   = 'sg-field-' . $sg_mmcs_key . ' ' . $sg_mmcs_width;
                ?>
                <div class="sg-input-group <?php echo esc_attr( $sg_mmcs_cls ); ?>">
                    <label for="sg-f-<?php echo esc_attr( $sg_mmcs_key ); ?>" class="sg-form-label sg-sr-only">
                        <?php echo esc_html( $sg_mmcs_label ); ?>
                    </label>

                    <input
                        id="sg-f-<?php echo esc_attr( $sg_mmcs_key ); ?>"
                        name="<?php echo esc_attr( $sg_mmcs_key ); ?>"
                        type="<?php echo esc_attr( $sg_mmcs_type ); ?>"
                        placeholder="<?php echo esc_attr( $sg_mmcs_placeholder ); ?>"
                        <?php echo $sg_mmcs_is_required ? 'required="required"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    />
                </div>
            <?php endforeach; ?>
        </div>

        <input class="sg-mmcs-hp" type="text" name="<?php echo esc_attr( $sg_mmcs_hp ); ?>" value="" tabindex="-1" autocomplete="off" />

        <button type="submit"><?php echo esc_html( $sg_mmcs_opts['subscribe_button'] ); ?></button>
    </form>

    <?php if ( ! empty( $sg_mmcs_opts['gdpr_notice'] ) ) : ?>
        <div class="sg-mmcs-gdpr"><?php echo esc_html( $sg_mmcs_opts['gdpr_notice'] ); ?></div>
    <?php endif; ?>

    <div class="sg-mmcs-form-msg" aria-live="polite"></div>
</div>
