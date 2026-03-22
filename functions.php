<?php
/**
 * PLRA Theme Child Functions
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_enqueue_scripts', 'plra_theme_child_enqueue_styles', 20 );
function plra_theme_child_enqueue_styles() {
    $child_style_path = get_stylesheet_directory() . '/style.css';
    $child_style_ver = file_exists( $child_style_path ) ? filemtime( $child_style_path ) : wp_get_theme()->get( 'Version' );

    wp_enqueue_style(
        'plra-theme-parent',
        get_template_directory_uri() . '/style.css',
        [],
        wp_get_theme()->parent() ? wp_get_theme()->parent()->get( 'Version' ) : null
    );

    wp_enqueue_style(
        'plra-theme-child',
        get_stylesheet_uri(),
        [ 'plra-theme-parent' ],
        $child_style_ver
    );

    // Кастомные стили держим в style.css дочерней темы.
}

add_action( 'after_setup_theme', 'plra_child_disable_blankslate_notices', 20 );
function plra_child_disable_blankslate_notices() {
    remove_action( 'admin_notices', 'blankslate_notice' );
    remove_action( 'admin_init', 'blankslate_notice_dismissed' );
}

$plra_child_module_files = [
    '/inc/shk-product-context.php',
    '/inc/shk-ui.php',
    '/inc/shk-woo.php',
    '/inc/shk-order-received-redirect.php',
    '/inc/shk-popups.php',
    '/inc/shk-size-modal.php',
    '/inc/shk-catalog-filters.php',
    '/inc/shk-sdek.php',
    '/inc/shk-elementor-hero-video.php',
    '/inc/shk-parser-admin.php',
    '/inc/shk-parser-woo-sync.php',
];

$plra_child_missing_modules = [];

foreach ( $plra_child_module_files as $plra_child_rel_file ) {
    $plra_child_abs_file = get_stylesheet_directory() . $plra_child_rel_file;

    if ( file_exists( $plra_child_abs_file ) ) {
        require_once $plra_child_abs_file;
    } else {
        $plra_child_missing_modules[] = $plra_child_rel_file;
    }
}

if ( ! empty( $plra_child_missing_modules ) ) {
    add_action( 'admin_notices', function () use ( $plra_child_missing_modules ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        echo '<div class="notice notice-warning"><p><strong>PLRA Theme Child:</strong> не найдены модули: ' . esc_html( implode( ', ', $plra_child_missing_modules ) ) . '</p></div>';
    } );
}

add_filter(
    'gettext',
    function( $translated, $text, $domain ) {
        $is_cart_empty_text = in_array(
            trim( (string) $text ),
            array(
                'No products in the cart.',
                'No products in cart.',
            ),
            true
        );

        if ( $is_cart_empty_text && in_array( (string) $domain, array( 'woocommerce', 'elementor-pro', 'elementor' ), true ) ) {
            return 'В корзине пока нет товаров.';
        }
        return $translated;
    },
    20,
    3
);
