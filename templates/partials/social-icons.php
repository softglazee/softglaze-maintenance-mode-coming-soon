<?php
/**
 * Social icons (front-end).
 *
 * @package SoftGlaze\MMCS
 */

use SoftGlaze\MMCS\Options;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
$sg_mmcs_opts  = Options::get();
$sg_mmcs_icons = ! empty( $sg_mmcs_opts['social_icons'] ) && is_array( $sg_mmcs_opts['social_icons'] ) ? $sg_mmcs_opts['social_icons'] : [];

if ( ! function_exists( 'sg_mmcs_get_svg_icon' ) ) {
    /**
     * Return SVG inner markup for supported icons.
     *
     * Note: returns markup; caller must kses.
     *
     * @param string $key Platform key.
     * @return string
     */
    function sg_mmcs_get_svg_icon( $key ) {
        $paths = [
            'facebook'  => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>',
            'instagram' => '<rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>',
            'x'         => '<path d="M4 4l11.733 16h4.267l-11.733 -16z"></path><path d="M4 20l6.768 -6.768m2.46 -2.46l6.772 -6.772"></path>',
            'linkedin'  => '<path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path><rect x="2" y="9" width="4" height="12"></rect><circle cx="4" cy="4" r="2"></circle>',
            'youtube'   => '<path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z"></path><polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02"></polygon>',
            'tiktok'    => '<path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"></path>',
            'whatsapp'  => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>',
            'telegram'  => '<line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>',
            'pinterest' => '<path d="M8 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"></path>',
            'snapchat'  => '<path d="M2 2l20 20"></path>',
            'email'     => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline>',
        ];

        $key = sanitize_key( (string) $key );
        return $paths[ $key ] ?? '<circle cx="12" cy="12" r="10"></circle>';
    }
}

$sg_mmcs_allowed_svg = [
    'path'     => [ 'd' => true ],
    'rect'     => [ 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true ],
    'circle'   => [ 'cx' => true, 'cy' => true, 'r' => true ],
    'polygon'  => [ 'points' => true ],
    'polyline' => [ 'points' => true ],
    'line'     => [ 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true ],
];
?>
<div class="sg-social-icons">
    <?php foreach ( $sg_mmcs_icons as $sg_mmcs_icon ) : ?>
        <?php
        $sg_mmcs_platform = isset( $sg_mmcs_icon['platform'] ) ? sanitize_key( (string) $sg_mmcs_icon['platform'] ) : '';
        $sg_mmcs_url      = isset( $sg_mmcs_icon['url'] ) ? (string) $sg_mmcs_icon['url'] : '';
        ?>
        <a
            href="<?php echo esc_url( $sg_mmcs_url ); ?>"
            target="_blank"
            rel="noopener noreferrer"
            class="sg-icon-link sg-icon-<?php echo esc_attr( $sg_mmcs_platform ); ?>"
        >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <?php echo wp_kses( sg_mmcs_get_svg_icon( $sg_mmcs_platform ), $sg_mmcs_allowed_svg ); ?>
            </svg>
        </a>
    <?php endforeach; ?>
</div>
