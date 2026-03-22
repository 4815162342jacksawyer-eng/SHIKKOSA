<?php
/**
 * WooCommerce: редирект страницы "Заказ принят" на кастомную Elementor-страницу.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'woocommerce_get_checkout_order_received_url', 'plra_child_order_received_redirect_url', 10, 2 );

/**
 * Формирует URL страницы успешного заказа.
 *
 * @param string         $url   Стандартный URL WooCommerce.
 * @param int|WC_Order   $order Заказ или ID заказа.
 *
 * @return string
 */
function plra_child_order_received_redirect_url( $url, $order ) {
    if ( ! function_exists( 'wc_get_order' ) ) {
        return $url;
    }

    $wc_order = is_a( $order, 'WC_Order' ) ? $order : wc_get_order( $order );

    if ( ! $wc_order ) {
        return $url;
    }

    $target_path = apply_filters( 'plra_child_order_received_target_path', '/order-received-new/' );

    return add_query_arg(
        [
            'order_id' => $wc_order->get_id(),
            'key'      => $wc_order->get_order_key(),
        ],
        home_url( $target_path )
    );
}
