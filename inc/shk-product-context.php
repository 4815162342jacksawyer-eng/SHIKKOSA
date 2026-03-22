<?php
/* Ensure Elementor/Woo widgets on single product use the current queried product context */
add_action('wp', function () {
    if (!is_product()) {
        return;
    }

    $product_id = get_queried_object_id();
    if (!$product_id) {
        return;
    }

    global $post, $product;

    $post_obj = get_post($product_id);
    $product_obj = wc_get_product($product_id);

    if ($post_obj instanceof WP_Post) {
        $post = $post_obj;
        setup_postdata($post);
    }

    if ($product_obj instanceof WC_Product) {
        $product = $product_obj;
    }
}, 1);
