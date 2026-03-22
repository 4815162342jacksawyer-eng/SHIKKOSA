<?php
function shikkosa_color_taxonomies_local() {
    $taxes = array( 'pa_czvet', 'pa_color', 'pa_colour' );

    if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
        $attrs = wc_get_attribute_taxonomies();
        if ( is_array( $attrs ) ) {
            foreach ( $attrs as $attr ) {
                if ( ! isset( $attr->attribute_name ) ) {
                    continue;
                }
                $name = (string) $attr->attribute_name;
                $name_lc = function_exists( 'mb_strtolower' ) ? mb_strtolower( $name ) : strtolower( $name );
                if (
                    false !== strpos( $name_lc, 'color' ) ||
                    false !== strpos( $name_lc, 'colour' ) ||
                    false !== strpos( $name_lc, 'czvet' ) ||
                    false !== strpos( $name_lc, 'cvet' ) ||
                    false !== strpos( $name_lc, 'цвет' )
                ) {
                    $taxes[] = wc_attribute_taxonomy_name( $name );
                }
            }
        }
    }

    return array_values( array_unique( array_filter( array_map( 'sanitize_key', $taxes ) ) ) );
}

function shikkosa_parse_price_amount_local( $raw ) {
    $raw = is_scalar( $raw ) ? (string) $raw : '';
    $raw = trim( $raw );
    if ( '' === $raw ) {
        return '';
    }

    $raw = preg_replace( '/[^\d.,]/', '', $raw );
    if ( null === $raw ) {
        return '';
    }
    $raw = str_replace( ',', '.', $raw );
    $raw = trim( $raw );
    if ( '' === $raw || ! is_numeric( $raw ) ) {
        return '';
    }

    return wc_format_decimal( $raw );
}

function shikkosa_normalized_raw_price_pair_local( $product_id ) {
    $product_id = (int) $product_id;
    if ( $product_id <= 0 ) {
        return array( 0.0, 0.0 );
    }

    $regular_raw = get_post_meta( $product_id, '_regular_price', true );
    $sale_raw = get_post_meta( $product_id, '_sale_price', true );

    $regular = (float) shikkosa_parse_price_amount_local( $regular_raw );
    $sale = (float) shikkosa_parse_price_amount_local( $sale_raw );

    if ( $regular > 0 && $sale > 0 ) {
        $high = max( $regular, $sale );
        $low = min( $regular, $sale );
        if ( $low < $high ) {
            return array( (float) $high, (float) $low );
        }
        return array( (float) $high, 0.0 );
    }

    if ( $regular > 0 ) {
        return array( (float) $regular, 0.0 );
    }
    if ( $sale > 0 ) {
        return array( (float) $sale, 0.0 );
    }

    return array( 0.0, 0.0 );
}

function shikkosa_normalized_regular_price_filter_local( $price, $product ) {
    if ( is_admin() && ! wp_doing_ajax() ) {
        return $price;
    }
    if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
        return $price;
    }

    list( $normalized_regular ) = shikkosa_normalized_raw_price_pair_local( (int) $product->get_id() );
    if ( $normalized_regular <= 0 ) {
        return $price;
    }

    return wc_format_decimal( $normalized_regular );
}

function shikkosa_normalized_sale_price_filter_local( $price, $product ) {
    if ( is_admin() && ! wp_doing_ajax() ) {
        return $price;
    }
    if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
        return $price;
    }

    list( $normalized_regular, $normalized_sale ) = shikkosa_normalized_raw_price_pair_local( (int) $product->get_id() );
    if ( $normalized_regular <= 0 || $normalized_sale <= 0 || $normalized_sale >= $normalized_regular ) {
        return '';
    }

    return wc_format_decimal( $normalized_sale );
}

add_filter( 'woocommerce_product_get_regular_price', 'shikkosa_normalized_regular_price_filter_local', 20, 2 );
add_filter( 'woocommerce_product_variation_get_regular_price', 'shikkosa_normalized_regular_price_filter_local', 20, 2 );
add_filter( 'woocommerce_product_get_sale_price', 'shikkosa_normalized_sale_price_filter_local', 20, 2 );
add_filter( 'woocommerce_product_variation_get_sale_price', 'shikkosa_normalized_sale_price_filter_local', 20, 2 );

function shikkosa_get_old_price_meta_keys_local() {
    return array(
        '_shk_price_before_discount',
        'shk_price_before_discount',
        'price_before_discount',
        '_price_before_discount',
        'old_price',
        '_old_price',
    );
}

function shikkosa_read_old_price_from_meta_local( $post_id ) {
    $post_id = (int) $post_id;
    if ( $post_id <= 0 ) {
        return 0.0;
    }

    foreach ( shikkosa_get_old_price_meta_keys_local() as $meta_key ) {
        $raw = get_post_meta( $post_id, $meta_key, true );
        $parsed = (float) shikkosa_parse_price_amount_local( $raw );
        if ( $parsed > 0 ) {
            return $parsed;
        }
    }

    return 0.0;
}

function shikkosa_resolve_current_price_local( $product ) {
    if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
        return 0.0;
    }

    $current_price = (float) $product->get_price();
    if ( $current_price > 0 ) {
        return $current_price;
    }

    if ( $product->is_type( 'variable' ) ) {
        $variation_prices = (array) $product->get_variation_prices( true );
        $active_prices = isset( $variation_prices['price'] ) ? array_map( 'floatval', (array) $variation_prices['price'] ) : array();
        $active_prices = array_values(
            array_filter(
                $active_prices,
                static function ( $value ) {
                    return $value > 0;
                }
            )
        );
        if ( ! empty( $active_prices ) ) {
            return (float) min( $active_prices );
        }
    }

    $sale_price = (float) $product->get_sale_price();
    if ( $sale_price > 0 ) {
        return $sale_price;
    }

    $regular_price = (float) $product->get_regular_price();
    if ( $regular_price > 0 ) {
        return $regular_price;
    }

    return 0.0;
}

function shikkosa_resolve_old_price_local( $product, $current_price = 0.0 ) {
    if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
        return 0.0;
    }

    $current_price = (float) $current_price;
    if ( $current_price <= 0 ) {
        $current_price = (float) shikkosa_resolve_current_price_local( $product );
    }

    $old_price = shikkosa_read_old_price_from_meta_local( (int) $product->get_id() );

    if ( $old_price <= $current_price && $product->is_type( 'variable' ) ) {
        $variation_ids = $product->get_children();
        if ( is_array( $variation_ids ) && ! empty( $variation_ids ) ) {
            $meta_old_prices = array();
            foreach ( $variation_ids as $variation_id ) {
                $variation_id = (int) $variation_id;
                if ( $variation_id <= 0 ) {
                    continue;
                }
                $var_old = (float) shikkosa_read_old_price_from_meta_local( $variation_id );
                if ( $var_old > 0 ) {
                    $meta_old_prices[] = $var_old;
                }
            }
            if ( ! empty( $meta_old_prices ) ) {
                $old_price = max( array_map( 'floatval', $meta_old_prices ) );
            }
        }
    }

    if ( $old_price <= $current_price && $product->is_type( 'variable' ) ) {
        $variation_prices = (array) $product->get_variation_prices( true );
        $regulars = isset( $variation_prices['regular_price'] ) ? (array) $variation_prices['regular_price'] : array();
        $max_regular = ! empty( $regulars ) ? max( array_map( 'floatval', $regulars ) ) : 0.0;
        if ( $max_regular > $current_price ) {
            $old_price = (float) $max_regular;
        }
    }

    return (float) $old_price;
}

function shikkosa_get_price_html_with_old_price_local( $product ) {
    if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
        return '';
    }

    $default_html = $product->get_price_html();
    $current_price = (float) shikkosa_resolve_current_price_local( $product );
    if ( $current_price <= 0 ) {
        return $default_html;
    }

    $old_price = shikkosa_resolve_old_price_local( $product, $current_price );

    if ( $old_price > $current_price ) {
        return '<del>' . wc_price( $old_price ) . '</del> <ins>' . wc_price( $current_price ) . '</ins>';
    }

    return $default_html;
}

add_action( 'woocommerce_product_options_pricing', 'shikkosa_admin_old_price_field_local' );
add_action( 'woocommerce_product_options_general_product_data', 'shikkosa_admin_old_price_field_local' );
function shikkosa_admin_old_price_field_local() {
    static $rendered = false;
    if ( $rendered ) {
        return;
    }
    $rendered = true;

    if ( ! function_exists( 'woocommerce_wp_text_input' ) ) {
        return;
    }

    woocommerce_wp_text_input(
        array(
            'id'          => '_shk_price_before_discount',
            'label'       => 'Старая цена (SHK)',
            'description' => 'Fallback-старая цена для фронта (если regular/sale заданы некорректно).',
            'desc_tip'    => true,
            'type'        => 'text',
            'wrapper_class' => 'show_if_simple',
        )
    );
}

add_filter( 'woocommerce_get_price_html', 'shikkosa_filter_price_html_with_old_price_local', 20, 2 );
add_filter( 'woocommerce_variable_price_html', 'shikkosa_filter_price_html_with_old_price_local', 20, 2 );
add_filter( 'woocommerce_variable_sale_price_html', 'shikkosa_filter_price_html_with_old_price_local', 20, 2 );
function shikkosa_filter_price_html_with_old_price_local( $price_html, $product ) {
    if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
        return $price_html;
    }

    $current_price = (float) shikkosa_resolve_current_price_local( $product );
    if ( $current_price <= 0 ) {
        return $price_html;
    }

    $old_price = shikkosa_resolve_old_price_local( $product, $current_price );
    if ( $old_price > $current_price ) {
        return '<del>' . wc_price( $old_price ) . '</del> <ins>' . wc_price( $current_price ) . '</ins>';
    }

    return $price_html;
}

add_action( 'woocommerce_admin_process_product_object', 'shikkosa_admin_old_price_field_save_local' );
add_action( 'woocommerce_process_product_meta', 'shikkosa_admin_old_price_field_save_legacy_local', 20, 1 );
function shikkosa_admin_old_price_field_save_local( $product ) {
    if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
        return;
    }

    $raw = isset( $_POST['_shk_price_before_discount'] ) ? wp_unslash( $_POST['_shk_price_before_discount'] ) : '';
    $price = shikkosa_parse_price_amount_local( $raw );
    if ( '' === $price ) {
        $product->delete_meta_data( '_shk_price_before_discount' );
        $product->delete_meta_data( 'price_before_discount' );
    } else {
        $product->update_meta_data( '_shk_price_before_discount', $price );
        $product->update_meta_data( 'price_before_discount', $price );
    }

    if ( $product->is_type( 'variable' ) ) {
        shikkosa_sync_parent_old_price_from_variations_local( (int) $product->get_id() );
    }
}

function shikkosa_admin_old_price_field_save_legacy_local( $post_id ) {
    $post_id = (int) $post_id;
    if ( $post_id <= 0 ) {
        return;
    }

    $raw = isset( $_POST['_shk_price_before_discount'] ) ? wp_unslash( $_POST['_shk_price_before_discount'] ) : '';
    $price = shikkosa_parse_price_amount_local( $raw );
    if ( '' === $price ) {
        delete_post_meta( $post_id, '_shk_price_before_discount' );
        delete_post_meta( $post_id, 'price_before_discount' );
    } else {
        update_post_meta( $post_id, '_shk_price_before_discount', $price );
        update_post_meta( $post_id, 'price_before_discount', $price );
    }

    $product = wc_get_product( $post_id );
    if ( $product && $product->is_type( 'variable' ) ) {
        shikkosa_sync_parent_old_price_from_variations_local( $post_id );
    }
}

add_action( 'woocommerce_variation_options_pricing', 'shikkosa_variation_old_price_field_local', 20, 3 );
function shikkosa_variation_old_price_field_local( $loop, $variation_data, $variation ) {
    $variation_id = is_object( $variation ) && isset( $variation->ID ) ? (int) $variation->ID : 0;
    $value = $variation_id > 0 ? (string) get_post_meta( $variation_id, '_shk_price_before_discount', true ) : '';
    ?>
    <p class="form-row form-row-full">
        <label>Старая цена (SHK)</label>
        <input
            type="text"
            class="short"
            name="shk_variation_old_price[<?php echo esc_attr( (string) $loop ); ?>]"
            value="<?php echo esc_attr( $value ); ?>"
            placeholder="Например: 32900"
        />
    </p>
    <?php
}

add_action( 'woocommerce_save_product_variation', 'shikkosa_variation_old_price_field_save_local', 20, 2 );
function shikkosa_variation_old_price_field_save_local( $variation_id, $loop ) {
    $variation_id = (int) $variation_id;
    if ( $variation_id <= 0 ) {
        return;
    }

    $raw = '';
    if ( isset( $_POST['shk_variation_old_price'] ) && isset( $_POST['shk_variation_old_price'][ $loop ] ) ) {
        $raw = wp_unslash( $_POST['shk_variation_old_price'][ $loop ] );
    }

    $price = shikkosa_parse_price_amount_local( $raw );
    if ( '' === $price ) {
        delete_post_meta( $variation_id, '_shk_price_before_discount' );
        delete_post_meta( $variation_id, 'price_before_discount' );
    } else {
        update_post_meta( $variation_id, '_shk_price_before_discount', $price );
        update_post_meta( $variation_id, 'price_before_discount', $price );
    }

    $parent_id = (int) wp_get_post_parent_id( $variation_id );
    if ( $parent_id > 0 ) {
        shikkosa_sync_parent_old_price_from_variations_local( $parent_id );
    }
}

function shikkosa_sync_parent_old_price_from_variations_local( $parent_product_id ) {
    $parent_product_id = (int) $parent_product_id;
    if ( $parent_product_id <= 0 ) {
        return;
    }

    $product = wc_get_product( $parent_product_id );
    if ( ! $product || ! $product->is_type( 'variable' ) ) {
        return;
    }

    $variation_ids = (array) $product->get_children();
    $old_prices = array();
    foreach ( $variation_ids as $variation_id ) {
        $variation_id = (int) $variation_id;
        if ( $variation_id <= 0 ) {
            continue;
        }

        $parsed = (float) shikkosa_read_old_price_from_meta_local( $variation_id );
        if ( $parsed > 0 ) {
            $old_prices[] = $parsed;
        }
    }

    if ( empty( $old_prices ) ) {
        delete_post_meta( $parent_product_id, '_shk_price_before_discount' );
        delete_post_meta( $parent_product_id, 'price_before_discount' );
        return;
    }

    $max_old = wc_format_decimal( max( $old_prices ) );
    update_post_meta( $parent_product_id, '_shk_price_before_discount', $max_old );
    update_post_meta( $parent_product_id, 'price_before_discount', $max_old );
}

function shikkosa_term_color_field_add_local() {
    ?>
    <div class="form-field term-group">
        <label for="shk_color_hex"><?php esc_html_e( 'SHK Color', 'plra-theme-child' ); ?></label>
        <input type="text" id="shk_color_hex" name="shk_color_hex" value="" class="shk-color-picker" placeholder="#000000" />
        <p class="description"><?php esc_html_e( 'Цвет для витрины/карточки (fallback без ACF).', 'plra-theme-child' ); ?></p>
    </div>
    <?php
}

function shikkosa_term_color_field_edit_local( $term ) {
    $value = (string) get_term_meta( $term->term_id, 'shk_color_hex', true );
    ?>
    <tr class="form-field term-group-wrap">
        <th scope="row"><label for="shk_color_hex"><?php esc_html_e( 'SHK Color', 'plra-theme-child' ); ?></label></th>
        <td>
            <input type="text" id="shk_color_hex" name="shk_color_hex" value="<?php echo esc_attr( $value ); ?>" class="shk-color-picker" placeholder="#000000" />
            <p class="description"><?php esc_html_e( 'Цвет для витрины/карточки (fallback без ACF).', 'plra-theme-child' ); ?></p>
        </td>
    </tr>
    <?php
}

function shikkosa_term_color_field_save_local( $term_id ) {
    $raw = isset( $_POST['shk_color_hex'] ) ? wp_unslash( $_POST['shk_color_hex'] ) : '';
    $raw = trim( (string) $raw );
    if ( '' === $raw ) {
        delete_term_meta( $term_id, 'shk_color_hex' );
        return;
    }
    $hex = sanitize_hex_color( $raw );
    if ( $hex ) {
        update_term_meta( $term_id, 'shk_color_hex', $hex );
    }
}

function shikkosa_term_color_field_enqueue_local( $hook_suffix ) {
    if ( 'edit-tags.php' !== $hook_suffix && 'term.php' !== $hook_suffix ) {
        return;
    }
    $taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';
    if ( ! in_array( $taxonomy, shikkosa_color_taxonomies_local(), true ) ) {
        return;
    }

    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_script( 'wp-color-picker' );
    wp_add_inline_script(
        'wp-color-picker',
        'jQuery(function($){$(".shk-color-picker").wpColorPicker();});'
    );
}

foreach ( shikkosa_color_taxonomies_local() as $shk_color_tax ) {
    add_action( $shk_color_tax . '_add_form_fields', 'shikkosa_term_color_field_add_local' );
    add_action( $shk_color_tax . '_edit_form_fields', 'shikkosa_term_color_field_edit_local' );
    add_action( 'created_' . $shk_color_tax, 'shikkosa_term_color_field_save_local' );
    add_action( 'edited_' . $shk_color_tax, 'shikkosa_term_color_field_save_local' );
}
add_action( 'admin_enqueue_scripts', 'shikkosa_term_color_field_enqueue_local' );

function shikkosa_find_product_id_by_source_slug_local( $source_slug ) {
    $normalized = shikkosa_normalize_source_slug_local( $source_slug );
    if ( '' === $normalized ) {
        return 0;
    }

    foreach ( shikkosa_source_slug_variants_local( $normalized ) as $candidate ) {
        $query = new WP_Query(
            array(
                'post_type'      => 'product',
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_query'     => array(
                    array(
                        'key'   => '_shk_source_slug',
                        'value' => $candidate,
                    ),
                ),
            )
        );

        if ( ! empty( $query->posts ) ) {
            return (int) $query->posts[0];
        }

        $candidate_path = (string) wp_parse_url( $candidate, PHP_URL_PATH );
        if ( '' === $candidate_path ) {
            $candidate_path = $candidate;
        }
        $candidate_slug = sanitize_title( basename( untrailingslashit( (string) $candidate_path ) ) );
        if ( '' !== $candidate_slug ) {
            $fallback_query = new WP_Query(
                array(
                    'post_type'      => 'product',
                    'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                    'posts_per_page' => 1,
                    'fields'         => 'ids',
                    'name'           => $candidate_slug,
                )
            );
            if ( ! empty( $fallback_query->posts ) ) {
                return (int) $fallback_query->posts[0];
            }
        }
    }

    return 0;
}

function shikkosa_normalize_source_slug_local( $source_slug ) {
    $source_slug = trim( (string) $source_slug );
    if ( '' === $source_slug ) {
        return '';
    }

    if ( preg_match( '~^https?://~i', $source_slug ) ) {
        $path = (string) wp_parse_url( $source_slug, PHP_URL_PATH );
        $source_slug = '' !== $path ? $path : $source_slug;
    }

    $source_slug = trim( $source_slug );
    if ( '' === $source_slug ) {
        return '';
    }

    if ( '/' !== substr( $source_slug, 0, 1 ) ) {
        $source_slug = '/' . $source_slug;
    }

    $source_slug = preg_replace( '~/{2,}~', '/', $source_slug );
    $source_slug = untrailingslashit( (string) $source_slug );
    return (string) $source_slug;
}

function shikkosa_source_slug_variants_local( $source_slug ) {
    $normalized = shikkosa_normalize_source_slug_local( $source_slug );
    if ( '' === $normalized ) {
        return array();
    }

    $variants = array(
        $normalized,
        trailingslashit( ltrim( $normalized, '/' ) ),
        ltrim( $normalized, '/' ),
        trailingslashit( $normalized ),
        urldecode( $normalized ),
        rawurldecode( $normalized ),
    );

    if ( false === strpos( $normalized, '/product/' ) && false !== strpos( $normalized, '/catalog/' ) ) {
        $variants[] = str_replace( '/catalog/', '/product/', $normalized );
    }
    if ( false === strpos( $normalized, '/catalog/' ) && false !== strpos( $normalized, '/product/' ) ) {
        $variants[] = str_replace( '/product/', '/catalog/', $normalized );
    }

    $variants = array_map(
        static function ( $value ) {
            return shikkosa_normalize_source_slug_local( $value );
        },
        $variants
    );

    return array_values( array_unique( array_filter( $variants ) ) );
}

function shikkosa_find_product_ids_by_color_family_id_local( $family_id ) {
    $family_id = trim( (string) $family_id );
    if ( '' === $family_id ) {
        return array();
    }

    $query = new WP_Query(
        array(
            'post_type'      => 'product',
            'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'   => '_shk_color_family_id',
                    'value' => $family_id,
                ),
            ),
        )
    );

    return ! empty( $query->posts ) ? array_values( array_map( 'intval', $query->posts ) ) : array();
}

function shikkosa_parse_pipe_values_local( $raw ) {
    $raw = is_scalar( $raw ) ? (string) $raw : '';
    if ( '' === trim( $raw ) ) {
        return array();
    }

    $parts = preg_split( '/\s*[|,;]\s*/u', $raw );
    if ( ! is_array( $parts ) ) {
        $parts = array( $raw );
    }

    $out = array();
    foreach ( $parts as $part ) {
        $part = trim( (string) $part );
        if ( '' === $part ) {
            continue;
        }
        $out[] = $part;
    }
    return array_values( array_unique( $out ) );
}

function shikkosa_parse_int_values_local( $raw ) {
    $parts = array();

    if ( is_array( $raw ) ) {
        $parts = $raw;
    } else {
        $raw = is_scalar( $raw ) ? (string) $raw : '';
        if ( '' === trim( $raw ) ) {
            return array();
        }
        $parts = preg_split( '/\s*[|,;]\s*/u', $raw );
        if ( ! is_array( $parts ) ) {
            $parts = array( $raw );
        }
    }

    $out = array();
    foreach ( $parts as $part ) {
        $id = (int) $part;
        if ( $id > 0 ) {
            $out[] = $id;
        }
    }

    return array_values( array_unique( $out ) );
}

function shikkosa_collect_color_family_ids_local( $product_id, $include_self = true ) {
    $product_id = (int) $product_id;
    if ( $product_id <= 0 ) {
        return array();
    }

    $explicit_family_ids = shikkosa_parse_int_values_local( get_post_meta( $product_id, '_shk_color_family_ids', true ) );
    $ids = array_merge( array(), $explicit_family_ids );

    $family_members_raw = (string) get_post_meta( $product_id, '_shk_color_family_members', true );
    $family_slugs = shikkosa_parse_pipe_values_local( $family_members_raw );
    if ( empty( $family_slugs ) ) {
        $related_slugs_raw = (string) get_post_meta( $product_id, '_shk_related_slugs', true );
        $family_slugs = shikkosa_parse_pipe_values_local( $related_slugs_raw );
    }

    foreach ( $family_slugs as $member_slug ) {
        $member_id = shikkosa_find_product_id_by_source_slug_local( $member_slug );
        if ( $member_id > 0 ) {
            $ids[] = (int) $member_id;
        }
    }

    $source_slug = shikkosa_normalize_source_slug_local( (string) get_post_meta( $product_id, '_shk_source_slug', true ) );
    if ( '' === $source_slug ) {
        $post_slug = trim( (string) get_post_field( 'post_name', $product_id ) );
        if ( '' !== $post_slug ) {
            $source_slug = shikkosa_normalize_source_slug_local( '/product/' . $post_slug );
        }
    }
    $family_id = trim( (string) get_post_meta( $product_id, '_shk_color_family_id', true ) );
    if ( '' !== $family_id ) {
        $ids = array_merge( $ids, shikkosa_find_product_ids_by_color_family_id_local( $family_id ) );
    }

    $has_explicit_family = ( ! empty( $explicit_family_ids ) || ! empty( $family_slugs ) || '' !== $family_id );

    // Reverse recovery: when current product has broken/empty family meta,
    // try to find other products that reference its source_slug and inherit their family.
    if ( ! $has_explicit_family && '' !== $source_slug ) {
        $reverse_ids = shikkosa_find_products_referencing_source_slug_local( $source_slug, $product_id );
        foreach ( $reverse_ids as $reverse_id ) {
            $reverse_id = (int) $reverse_id;
            if ( $reverse_id <= 0 ) {
                continue;
            }

            $ids[] = $reverse_id;

            $reverse_family_id = trim( (string) get_post_meta( $reverse_id, '_shk_color_family_id', true ) );
            if ( '' !== $reverse_family_id ) {
                $ids = array_merge( $ids, shikkosa_find_product_ids_by_color_family_id_local( $reverse_family_id ) );
            }

            $reverse_slugs = shikkosa_parse_pipe_values_local( (string) get_post_meta( $reverse_id, '_shk_color_family_members', true ) );
            if ( empty( $reverse_slugs ) ) {
                $reverse_slugs = shikkosa_parse_pipe_values_local( (string) get_post_meta( $reverse_id, '_shk_related_slugs', true ) );
            }

            foreach ( $reverse_slugs as $reverse_slug ) {
                $reverse_member_id = shikkosa_find_product_id_by_source_slug_local( $reverse_slug );
                if ( $reverse_member_id > 0 ) {
                    $ids[] = (int) $reverse_member_id;
                }
            }
        }
    }

    // Final fallback for legacy/broken imports: use stored related IDs as color-family source.
    if ( ! $has_explicit_family && count( array_unique( array_filter( array_map( 'intval', $ids ) ) ) ) <= 1 ) {
        $related_ids_fallback = shikkosa_parse_int_values_local( get_post_meta( $product_id, '_shk_related_ids', true ) );
        if ( ! empty( $related_ids_fallback ) ) {
            $ids = array_merge( $ids, $related_ids_fallback );
        }

        $product_obj = wc_get_product( $product_id );
        if ( $product_obj && method_exists( $product_obj, 'get_upsell_ids' ) ) {
            $upsell_ids = array_values( array_map( 'intval', (array) $product_obj->get_upsell_ids() ) );
            if ( ! empty( $upsell_ids ) ) {
                $ids = array_merge( $ids, $upsell_ids );
            }
        }
    }

    if ( $include_self ) {
        $ids[] = $product_id;
    }

    $ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );

    if ( ! $include_self ) {
        $ids = array_values(
            array_filter(
                $ids,
                static function ( $id ) use ( $product_id ) {
                    return (int) $id !== $product_id;
                }
            )
        );
    }

    return $ids;
}

function shikkosa_dedupe_family_links_by_color_local( array $links ) {
    if ( empty( $links ) ) {
        return array();
    }

    $by_color = array();
    foreach ( $links as $link ) {
        if ( ! is_array( $link ) ) {
            continue;
        }

        $label = trim( (string) ( $link['label'] ?? '' ) );
        $key = sanitize_title( $label );
        if ( '' === $key ) {
            $key = (string) (int) ( $link['product_id'] ?? 0 );
        }

        if ( ! isset( $by_color[ $key ] ) ) {
            $by_color[ $key ] = $link;
            continue;
        }

        // Prefer active item for duplicated color labels.
        if ( ! empty( $link['active'] ) && empty( $by_color[ $key ]['active'] ) ) {
            $by_color[ $key ] = $link;
        }
    }

    return array_values( $by_color );
}

function shikkosa_find_products_referencing_source_slug_local( $source_slug, $exclude_product_id = 0 ) {
    $source_slug = shikkosa_normalize_source_slug_local( $source_slug );
    if ( '' === $source_slug ) {
        return array();
    }

    $ids = array();
    foreach ( shikkosa_source_slug_variants_local( $source_slug ) as $variant ) {
        $query = new WP_Query(
            array(
                'post_type'      => 'product',
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => array(
                    'relation' => 'OR',
                    array(
                        'key'     => '_shk_color_family_members',
                        'value'   => $variant,
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key'     => '_shk_related_slugs',
                        'value'   => $variant,
                        'compare' => 'LIKE',
                    ),
                ),
            )
        );

        if ( ! empty( $query->posts ) ) {
            $ids = array_merge( $ids, array_map( 'intval', $query->posts ) );
        }
    }

    $ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
    $exclude_product_id = (int) $exclude_product_id;
    if ( $exclude_product_id > 0 ) {
        $ids = array_values(
            array_filter(
                $ids,
                static function ( $id ) use ( $exclude_product_id ) {
                    return (int) $id !== $exclude_product_id;
                }
            )
        );
    }

    return $ids;
}

function shikkosa_collect_related_ids_strict_local( $product_id ) {
    $product_id = (int) $product_id;
    if ( $product_id <= 0 ) {
        return array();
    }

    $ids = shikkosa_parse_int_values_local( get_post_meta( $product_id, '_shk_related_ids', true ) );

    $related_slugs = shikkosa_parse_pipe_values_local( (string) get_post_meta( $product_id, '_shk_related_slugs', true ) );
    foreach ( $related_slugs as $slug ) {
        $id = shikkosa_find_product_id_by_source_slug_local( $slug );
        if ( $id > 0 ) {
            $ids[] = (int) $id;
        }
    }

    $ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
    $ids = array_values(
        array_filter(
            $ids,
            static function( $id ) use ( $product_id ) {
                return (int) $id !== $product_id;
            }
        )
    );

    return $ids;
}

function shikkosa_collect_related_ids_local( $product_id, $include_color_family = false ) {
    $ids = shikkosa_collect_related_ids_strict_local( $product_id );
    if ( ! $include_color_family ) {
        return $ids;
    }

    $product_id = (int) $product_id;
    $ids = array_merge( $ids, shikkosa_collect_color_family_ids_local( $product_id, false ) );
    $ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
    $ids = array_values(
        array_filter(
            $ids,
            static function( $id ) use ( $product_id ) {
                return (int) $id !== $product_id;
            }
        )
    );

    return $ids;
}

add_action( 'add_meta_boxes_product', 'shikkosa_register_links_meta_box_local' );
function shikkosa_register_links_meta_box_local() {
    add_meta_box(
        'shikkosa-links-meta',
        'SHIKKOSA: связки товара',
        'shikkosa_render_links_meta_box_local',
        'product',
        'normal',
        'default'
    );
}

function shikkosa_render_links_meta_box_local( $post ) {
    if ( ! current_user_can( 'edit_post', $post->ID ) ) {
        return;
    }

    $related_ids_raw = get_post_meta( $post->ID, '_shk_related_ids', true );
    $related_ids = shikkosa_parse_int_values_local( $related_ids_raw );
    $family_picker_ids = shikkosa_parse_int_values_local( get_post_meta( $post->ID, '_shk_color_family_ids', true ) );
    if ( empty( $family_picker_ids ) ) {
        $family_picker_ids = shikkosa_collect_color_family_ids_local( (int) $post->ID, false );
    }

    wp_nonce_field( 'shikkosa_links_meta_box_save', 'shikkosa_links_meta_box_nonce' );
    ?>
    <table class="form-table" style="margin-top:0;">
        <tr>
            <th scope="row"><label for="shk_color_family_product_ids">Color family товары</label></th>
            <td>
                <select
                    id="shk_color_family_product_ids"
                    name="shk_color_family_product_ids[]"
                    class="wc-product-search"
                    multiple="multiple"
                    style="width: 100%;"
                    data-placeholder="Найти и выбрать товары по названию или SKU"
                    data-action="woocommerce_json_search_products_and_variations"
                    data-exclude="<?php echo esc_attr( (string) (int) $post->ID ); ?>"
                >
                    <?php foreach ( $family_picker_ids as $picked_id ) :
                        $picked_id = (int) $picked_id;
                        if ( $picked_id <= 0 ) {
                            continue;
                        }
                        $picked_product = wc_get_product( $picked_id );
                        $picked_label = $picked_product ? $picked_product->get_formatted_name() : get_the_title( $picked_id );
                        ?>
                        <option value="<?php echo esc_attr( (string) $picked_id ); ?>" selected="selected"><?php echo esc_html( $picked_label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="shk_related_ids_picker">Related товары</label></th>
            <td>
                <select
                    id="shk_related_ids_picker"
                    name="shk_related_ids_picker[]"
                    class="wc-product-search"
                    multiple="multiple"
                    style="width: 100%;"
                    data-placeholder="Найти и выбрать related товары"
                    data-action="woocommerce_json_search_products_and_variations"
                    data-exclude="<?php echo esc_attr( (string) (int) $post->ID ); ?>"
                >
                    <?php foreach ( $related_ids as $picked_id ) :
                        $picked_id = (int) $picked_id;
                        if ( $picked_id <= 0 ) {
                            continue;
                        }
                        $picked_product = wc_get_product( $picked_id );
                        $picked_label = $picked_product ? $picked_product->get_formatted_name() : get_the_title( $picked_id );
                        ?>
                        <option value="<?php echo esc_attr( (string) $picked_id ); ?>" selected="selected"><?php echo esc_html( $picked_label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
    </table>
    <?php
}

add_action( 'admin_enqueue_scripts', 'shikkosa_links_meta_box_admin_assets_local' );
function shikkosa_links_meta_box_admin_assets_local( $hook_suffix ) {
    if ( 'post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix ) {
        return;
    }

    $post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
    if ( $post_id > 0 && 'product' !== get_post_type( $post_id ) ) {
        return;
    }
    if ( $post_id <= 0 ) {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || 'product' !== (string) ( $screen->post_type ?? '' ) ) {
            return;
        }
    }

    if ( function_exists( 'wc_enqueue_js' ) ) {
        wp_enqueue_script( 'wc-enhanced-select' );
        wp_enqueue_style( 'woocommerce_admin_styles' );
    }
}

add_action( 'save_post_product', 'shikkosa_save_links_meta_box_local', 10, 2 );
function shikkosa_save_links_meta_box_local( $post_id, $post ) {
    if ( ! $post instanceof WP_Post ) {
        return;
    }
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    if ( ! isset( $_POST['shikkosa_links_meta_box_nonce'] ) ) {
        return;
    }
    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['shikkosa_links_meta_box_nonce'] ) ), 'shikkosa_links_meta_box_save' ) ) {
        return;
    }

    $related_ids_picker = isset( $_POST['shk_related_ids_picker'] ) ? (array) wp_unslash( $_POST['shk_related_ids_picker'] ) : [];
    $related_ids_picker = array_values( array_unique( array_filter( array_map( 'absint', $related_ids_picker ) ) ) );
    $related_ids = $related_ids_picker;
    $family_picker_ids = isset( $_POST['shk_color_family_product_ids'] ) ? (array) wp_unslash( $_POST['shk_color_family_product_ids'] ) : [];
    $family_picker_ids = array_values( array_unique( array_filter( array_map( 'absint', $family_picker_ids ) ) ) );

    if ( ! empty( $family_picker_ids ) ) {
        $family_group_ids = array_values(
            array_unique(
                array_filter(
                    array_map(
                        'absint',
                        array_merge( [ (int) $post_id ], $family_picker_ids )
                    )
                )
            )
        );
        $family_ids_for_current = array_values(
            array_filter(
                $family_group_ids,
                static function ( $id ) use ( $post_id ) {
                    return (int) $id !== (int) $post_id;
                }
            )
        );

        update_post_meta( $post_id, '_shk_color_family_ids', $family_ids_for_current );
        $family_slugs = array();
        foreach ( $family_ids_for_current as $family_product_id ) {
            $family_product_id = (int) $family_product_id;
            if ( $family_product_id <= 0 || $family_product_id === (int) $post_id ) {
                continue;
            }

            $member_source_slug = trim( (string) get_post_meta( $family_product_id, '_shk_source_slug', true ) );
            if ( '' === $member_source_slug ) {
                $member_post_slug = trim( (string) get_post_field( 'post_name', $family_product_id ) );
                if ( '' !== $member_post_slug ) {
                    $member_source_slug = '/product/' . $member_post_slug;
                }
            }

            if ( '' !== $member_source_slug ) {
                $family_slugs[] = $member_source_slug;
            }
        }

        $family_slugs = array_values( array_unique( array_filter( $family_slugs ) ) );
        if ( ! empty( $family_slugs ) ) {
            update_post_meta( $post_id, '_shk_color_family_members', implode( '|', $family_slugs ) );
        }

        // Keep the whole family consistent: each member points to all other members.
        foreach ( $family_group_ids as $group_member_id ) {
            $group_member_id = (int) $group_member_id;
            if ( $group_member_id <= 0 ) {
                continue;
            }

            $other_ids = array_values(
                array_filter(
                    $family_group_ids,
                    static function ( $id ) use ( $group_member_id ) {
                        return (int) $id !== $group_member_id;
                    }
                )
            );

            if ( empty( $other_ids ) ) {
                delete_post_meta( $group_member_id, '_shk_color_family_ids' );
                delete_post_meta( $group_member_id, '_shk_color_family_members' );
                continue;
            }

            update_post_meta( $group_member_id, '_shk_color_family_ids', $other_ids );

            $other_slugs = [];
            foreach ( $other_ids as $other_id ) {
                $other_id = (int) $other_id;
                if ( $other_id <= 0 ) {
                    continue;
                }
                $other_source_slug = trim( (string) get_post_meta( $other_id, '_shk_source_slug', true ) );
                if ( '' === $other_source_slug ) {
                    $other_post_slug = trim( (string) get_post_field( 'post_name', $other_id ) );
                    if ( '' !== $other_post_slug ) {
                        $other_source_slug = '/product/' . $other_post_slug;
                    }
                }
                if ( '' !== $other_source_slug ) {
                    $other_slugs[] = $other_source_slug;
                }
            }

            $other_slugs = array_values( array_unique( array_filter( $other_slugs ) ) );
            if ( empty( $other_slugs ) ) {
                delete_post_meta( $group_member_id, '_shk_color_family_members' );
            } else {
                update_post_meta( $group_member_id, '_shk_color_family_members', implode( '|', $other_slugs ) );
            }
        }
    } else {
        delete_post_meta( $post_id, '_shk_color_family_ids' );
    }

    $related_slugs = array();
    foreach ( $related_ids as $related_id ) {
        $related_id = (int) $related_id;
        if ( $related_id <= 0 || $related_id === (int) $post_id ) {
            continue;
        }
        $related_source_slug = trim( (string) get_post_meta( $related_id, '_shk_source_slug', true ) );
        if ( '' === $related_source_slug ) {
            $related_post_slug = trim( (string) get_post_field( 'post_name', $related_id ) );
            if ( '' !== $related_post_slug ) {
                $related_source_slug = '/product/' . $related_post_slug;
            }
        }
        if ( '' !== $related_source_slug ) {
            $related_slugs[] = $related_source_slug;
        }
    }
    $related_slugs = array_values( array_unique( array_filter( $related_slugs ) ) );
    if ( empty( $related_slugs ) ) {
        delete_post_meta( $post_id, '_shk_related_slugs' );
    } else {
        update_post_meta( $post_id, '_shk_related_slugs', implode( '|', $related_slugs ) );
    }

    if ( empty( $related_ids ) ) {
        delete_post_meta( $post_id, '_shk_related_ids' );
    } else {
        update_post_meta( $post_id, '_shk_related_ids', $related_ids );
    }

    $resolved_related_ids = shikkosa_collect_related_ids_local( $post_id );
    if ( empty( $resolved_related_ids ) ) {
        delete_post_meta( $post_id, '_shk_related_ids' );
    } else {
        update_post_meta( $post_id, '_shk_related_ids', $resolved_related_ids );
    }

    $product = wc_get_product( $post_id );
    if ( $product && method_exists( $product, 'set_upsell_ids' ) ) {
        $product->set_upsell_ids( $resolved_related_ids );
        $product->save();
    }
}

function shikkosa_collect_available_sizes_for_simple_product_local( $product_id ) {
    $product_id = (int) $product_id;
    if ( $product_id <= 0 ) {
        return array();
    }

    $sizes = array();
    $family_ids = shikkosa_collect_color_family_ids_local( $product_id, true );
    foreach ( $family_ids as $member_id ) {
        foreach ( shikkosa_collect_product_sizes_local( (int) $member_id ) as $member_size ) {
            $member_size = trim( (string) $member_size );
            if ( '' !== $member_size ) {
                $sizes[ shikkosa_normalize_size_local( $member_size ) ] = $member_size;
            }
        }
    }

    if ( empty( $sizes ) ) {
        foreach ( shikkosa_collect_product_sizes_local( $product_id ) as $size_value ) {
            $size_value = trim( (string) $size_value );
            if ( '' !== $size_value ) {
                $sizes[ shikkosa_normalize_size_local( $size_value ) ] = $size_value;
            }
        }
    }

    return array_values( $sizes );
}

function shikkosa_normalize_size_local( $value ) {
    if ( function_exists( 'shikkosa_normalize_size_value' ) ) {
        return shikkosa_normalize_size_value( $value );
    }
    return strtoupper( trim( (string) $value ) );
}

function shikkosa_extract_size_from_attributes_local( $attributes ) {
    if ( ! is_array( $attributes ) ) {
        return '';
    }

    foreach ( $attributes as $key => $value ) {
        $attr_key = strtolower( trim( (string) $key ) );
        $attr_key = preg_replace( '/^attribute_/', '', $attr_key );
        if (
            false !== strpos( $attr_key, 'pa_razmer' ) ||
            false !== strpos( $attr_key, 'razmer' ) ||
            false !== strpos( $attr_key, 'pa_size' ) ||
            false !== strpos( $attr_key, 'size' )
        ) {
            $size = trim( (string) $value );
            if ( '' !== $size ) {
                return $size;
            }
        }
    }

    return '';
}

function shikkosa_compose_effective_sku_local( $base_sku, $size_value ) {
    $base_sku = trim( (string) $base_sku );
    $size_value = trim( (string) $size_value );

    if ( '' === $base_sku ) {
        return '';
    }

    if ( '' === $size_value ) {
        return $base_sku;
    }

    $size_norm = shikkosa_normalize_size_local( $size_value );
    $base_tail = strtoupper( rtrim( $base_sku, "-_ /" ) );
    if ( '' !== $base_tail && preg_match( '/(?:^|[-_\\/])' . preg_quote( $size_norm, '/' ) . '$/u', $base_tail ) ) {
        return $base_sku;
    }

    if ( preg_match( '/[-_\\/]$/', $base_sku ) ) {
        return $base_sku . $size_norm;
    }

    return $base_sku . '-' . $size_norm;
}

function shikkosa_build_cart_item_sku_data_local( $product_id, $variation_id, $attributes ) {
    $product_id = (int) $product_id;
    $variation_id = (int) $variation_id;

    $base_sku = '';
    if ( $variation_id > 0 ) {
        $variation_product = wc_get_product( $variation_id );
        if ( $variation_product ) {
            $base_sku = (string) $variation_product->get_sku();
        }
    }
    if ( '' === trim( $base_sku ) && $product_id > 0 ) {
        $parent_product = wc_get_product( $product_id );
        if ( $parent_product ) {
            $base_sku = (string) $parent_product->get_sku();
        }
    }

    $selected_size = shikkosa_extract_size_from_attributes_local( $attributes );
    $effective_sku = shikkosa_compose_effective_sku_local( $base_sku, $selected_size );

    return array(
        'selected_size' => $selected_size,
        'base_sku'      => trim( (string) $base_sku ),
        'effective_sku' => trim( (string) $effective_sku ),
    );
}

function shikkosa_localize_cart_error_message_local( $message ) {
    $message = trim( wp_strip_all_tags( (string) $message ) );
    if ( '' === $message ) {
        return '';
    }

    $lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $message ) : strtolower( $message );
    $map = array(
        'validation failed'             => 'Не удалось добавить товар в корзину. Проверьте выбранные параметры.',
        'add to cart failed'            => 'Не удалось добавить товар в корзину.',
        'failed add to cart'            => 'Не удалось добавить товар в корзину.',
        'please choose product options' => 'Пожалуйста, выберите параметры товара.',
        'please choose an option'       => 'Пожалуйста, выберите параметры товара.',
        'out of stock'                  => 'Товара нет в наличии.',
        'cannot be purchased'           => 'Этот товар сейчас нельзя купить.',
    );

    foreach ( $map as $needle => $replacement ) {
        if ( false !== strpos( $lower, $needle ) ) {
            return $replacement;
        }
    }

    return $message;
}

function shikkosa_extract_color_from_attributes_local( $attributes ) {
    if ( ! is_array( $attributes ) ) {
        return '';
    }

    foreach ( $attributes as $key => $value ) {
        $attr_key = strtolower( trim( (string) $key ) );
        $attr_key = preg_replace( '/^attribute_/', '', $attr_key );
        if (
            false !== strpos( $attr_key, 'pa_czvet' ) ||
            false !== strpos( $attr_key, 'czvet' ) ||
            false !== strpos( $attr_key, 'cvet' ) ||
            false !== strpos( $attr_key, 'pa_color' ) ||
            false !== strpos( $attr_key, 'color' ) ||
            false !== strpos( $attr_key, 'colour' ) ||
            false !== strpos( $attr_key, 'цвет' )
        ) {
            $color = trim( (string) $value );
            if ( '' !== $color ) {
                return $color;
            }
        }
    }

    return '';
}

function shikkosa_build_cart_item_color_data_local( $product_id, $variation_id, $attributes ) {
    $product_id = (int) $product_id;
    $variation_id = (int) $variation_id;

    $selected_color = shikkosa_extract_color_from_attributes_local( $attributes );

    if ( '' === $selected_color ) {
        $color_source_id = $variation_id > 0 ? $variation_id : $product_id;
        if ( $color_source_id > 0 ) {
            $meta_color = trim( (string) get_post_meta( $color_source_id, '_shk_color', true ) );
            if ( '' !== $meta_color ) {
                $selected_color = $meta_color;
            }
        }
    }

    $color_label = $selected_color;
    $color_hex = '';
    if ( '' !== $selected_color && function_exists( 'shikkosa_resolve_color_local' ) ) {
        $color_hex = trim( (string) shikkosa_resolve_color_local( $selected_color, $selected_color ) );
    }

    return array(
        'selected_color' => trim( (string) $selected_color ),
        'color_label'    => trim( (string) $color_label ),
        'color_hex'      => trim( (string) $color_hex ),
    );
}

function shikkosa_render_live_sku_single_local() {
    if ( ! function_exists( 'is_product' ) || ! is_product() ) {
        return;
    }

    global $product;
    if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
        return;
    }

    $base_sku = trim( (string) $product->get_sku() );
    if ( '' === $base_sku ) {
        return;
    }

    echo '<p class="product-sku shk-live-sku-wrap"><span class="shk-live-sku-label">Артикул: </span><span class="shk-live-sku" data-shk-sku-live="1">' . esc_html( $base_sku ) . '</span></p>';
}
add_action( 'woocommerce_single_product_summary', 'shikkosa_render_live_sku_single_local', 9 );

function shikkosa_size_scale_local() {
    $size_taxonomies = array( 'pa_razmer', 'pa_size' );
    $size_labels = array();

    foreach ( $size_taxonomies as $tax ) {
        if ( ! taxonomy_exists( $tax ) ) {
            continue;
        }
        $terms = get_terms(
            array(
                'taxonomy'   => $tax,
                'hide_empty' => false,
            )
        );
        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            continue;
        }
        foreach ( $terms as $term ) {
            $name = trim( (string) $term->name );
            if ( '' !== $name ) {
                $size_labels[ $name ] = $name;
            }
        }
    }

    $size_labels = array_values( $size_labels );
    if ( empty( $size_labels ) ) {
        return array( 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'S/M', 'M/L', 'L/XL' );
    }

    $priority = array( 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'S/M', 'M/L', 'L/XL' );
    usort(
        $size_labels,
        static function ( $a, $b ) use ( $priority ) {
            $ai = array_search( $a, $priority, true );
            $bi = array_search( $b, $priority, true );
            if ( false !== $ai && false !== $bi ) {
                return $ai <=> $bi;
            }
            if ( false !== $ai ) {
                return -1;
            }
            if ( false !== $bi ) {
                return 1;
            }
            return strnatcasecmp( $a, $b );
        }
    );

    return $size_labels;
}

function shikkosa_extract_size_from_sku_local( $sku ) {
    $sku = trim( (string) $sku );
    if ( '' === $sku ) {
        return '';
    }

    $sizes = shikkosa_size_scale_local();
    if ( empty( $sizes ) ) {
        return '';
    }

    // Prefer longer labels first so "L/XL" is matched before "XL".
    usort(
        $sizes,
        static function ( $a, $b ) {
            return strlen( (string) $b ) <=> strlen( (string) $a );
        }
    );

    $sku_upper = strtoupper( $sku );
    foreach ( $sizes as $size_label ) {
        $size_label = trim( (string) $size_label );
        if ( '' === $size_label ) {
            continue;
        }
        $size_upper = strtoupper( $size_label );
        if ( preg_match( '/(?:^|[-_\\/])' . preg_quote( $size_upper, '/' ) . '$/u', $sku_upper ) ) {
            return $size_label;
        }
    }

    return '';
}

function shikkosa_guess_color_from_sku_local( $sku ) {
    $sku = strtolower( trim( (string) $sku ) );
    if ( '' === $sku ) {
        return '';
    }

    $map = array(
        'wh'   => 'Белый',
        'bl'   => 'Чёрный',
        'bk'   => 'Чёрный',
        'pnk'  => 'Розовый',
        'pink' => 'Розовый',
        'red'  => 'Красный',
        'rd'   => 'Красный',
        'blue' => 'Синий',
        'blu'  => 'Синий',
        'navy' => 'Синий',
        'mint' => 'Мятный',
        'ylw'  => 'Желтый',
        'gold' => 'Золото',
        'iv'   => 'Айвори',
    );

    if ( preg_match_all( '/([a-z]{2,6})/', $sku, $m ) && ! empty( $m[1] ) ) {
        $parts = array_reverse( $m[1] );
        foreach ( $parts as $part ) {
            if ( isset( $map[ $part ] ) ) {
                return $map[ $part ];
            }
        }
    }

    return '';
}

function shikkosa_collect_product_sizes_local( $product_id ) {
    $product_id = (int) $product_id;
    if ( $product_id <= 0 ) {
        return array();
    }

    $sizes = array();
    $product = wc_get_product( $product_id );

    if ( $product ) {
        $attrs = $product->get_attributes();
        foreach ( $attrs as $attribute ) {
            // Do not require "visible" flag here: some imported products keep size
            // attributes hidden in Woo but we still need them for inline selector.
            if ( ! is_a( $attribute, 'WC_Product_Attribute' ) ) {
                continue;
            }
            $attribute_name = (string) $attribute->get_name();
            $normalized_name = function_exists( 'mb_strtolower' ) ? mb_strtolower( $attribute_name ) : strtolower( $attribute_name );
            $is_size_attr = ( false !== strpos( $normalized_name, 'size' ) || false !== strpos( $normalized_name, 'razmer' ) || false !== strpos( $normalized_name, 'размер' ) );
            if ( ! $is_size_attr ) {
                continue;
            }

            if ( $attribute->is_taxonomy() && taxonomy_exists( $attribute_name ) ) {
                $terms = wc_get_product_terms( $product_id, $attribute_name, array( 'fields' => 'all' ) );
                if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                    foreach ( $terms as $term ) {
                        $name = trim( (string) $term->name );
                        if ( '' !== $name ) {
                            $sizes[ $name ] = $name;
                        }
                    }
                }
            } else {
                $raw_options = $attribute->get_options();
                if ( is_array( $raw_options ) ) {
                    foreach ( $raw_options as $raw_option ) {
                        $raw_option = is_scalar( $raw_option ) ? trim( (string) $raw_option ) : '';
                        if ( '' !== $raw_option ) {
                            $sizes[ $raw_option ] = $raw_option;
                        }
                    }
                }
            }
        }
    }

    $meta_sizes = shikkosa_parse_pipe_values_local( get_post_meta( $product_id, '_shk_sizes', true ) );
    foreach ( $meta_sizes as $meta_size ) {
        $meta_size = trim( (string) $meta_size );
        if ( '' !== $meta_size ) {
            $sizes[ $meta_size ] = $meta_size;
        }
    }

    return array_values( $sizes );
}

function shikkosa_collect_product_colors_local( $product_id ) {
    $product_id = (int) $product_id;
    if ( $product_id <= 0 ) {
        return array();
    }

    $colors = array();

    $meta_color = trim( (string) get_post_meta( $product_id, '_shk_color', true ) );
    if ( '' !== $meta_color ) {
        $colors[ $meta_color ] = $meta_color;
    }

    $color_taxes = shikkosa_color_taxonomies_local();
    foreach ( $color_taxes as $tax ) {
        if ( ! taxonomy_exists( $tax ) ) {
            continue;
        }
        $terms = wc_get_product_terms( $product_id, $tax, array( 'fields' => 'all' ) );
        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            continue;
        }
        foreach ( $terms as $term ) {
            $name = trim( (string) $term->name );
            if ( '' !== $name ) {
                $colors[ $name ] = $name;
            }
        }
    }

    return array_values( $colors );
}

function shikkosa_color_palette_map_local() {
    return function_exists( 'shikkosa_color_palette_map' )
        ? shikkosa_color_palette_map()
        : array(
            'black' => '#111111',
            'chernyj' => '#111111',
            'white' => '#f5f5f5',
            'belyj' => '#f5f5f5',
            'pink' => '#f2b8c6',
            'rozovyj' => '#f2b8c6',
            'blue' => '#2e4d8f',
            'sinij' => '#2e4d8f',
            'mint' => '#9ccdb3',
            'myatnyj' => '#9ccdb3',
            'yellow' => '#e4c052',
            'zheltyj' => '#e4c052',
            'zoloto' => '#c8a24a',
        );
}

function shikkosa_color_norm_key_local( $value ) {
    $value = trim( (string) $value );
    if ( '' === $value ) {
        return '';
    }

    $raw = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
    $raw = str_replace( 'ё', 'е', $raw );
    $raw = preg_replace( '/\s+/u', ' ', $raw );
    return trim( $raw );
}

function shikkosa_color_terms_index_local() {
    static $cache = null;
    if ( null !== $cache ) {
        return $cache;
    }

    $cache = array();
    $taxonomies = shikkosa_color_taxonomies_local();

    foreach ( $taxonomies as $tax ) {
        if ( ! taxonomy_exists( $tax ) ) {
            continue;
        }

        $terms = get_terms(
            array(
                'taxonomy'   => $tax,
                'hide_empty' => false,
            )
        );
        if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
            continue;
        }

        foreach ( $terms as $term ) {
            if ( ! ( $term instanceof WP_Term ) ) {
                continue;
            }

            $term_color = '';
            if ( function_exists( 'shikkosa_get_term_color_value' ) ) {
                $term_color = shikkosa_get_term_color_value( $term );
            } else {
                $term_color = get_term_meta( $term->term_id, 'shk_color_hex', true );
                if ( ! $term_color ) {
                    $term_color = get_term_meta( $term->term_id, 'color', true );
                }
                if ( ! $term_color && function_exists( 'get_field' ) ) {
                    $term_color = get_field( 'color', $tax . '_' . $term->term_id );
                }
                if ( ! $term_color && function_exists( 'get_field' ) ) {
                    $term_color = get_field( 'color', $term );
                }
                if ( is_array( $term_color ) && isset( $term_color['value'] ) ) {
                    $term_color = $term_color['value'];
                }
            }
            if ( ! is_string( $term_color ) || '' === trim( (string) $term_color ) ) {
                // Keep behavior aligned with catalog filters:
                // if term has no explicit color meta, use palette fallback by slug/name.
                if ( function_exists( 'shikkosa_resolve_color_value' ) ) {
                    $term_color = shikkosa_resolve_color_value( (string) $term->slug, (string) $term->name );
                }
            }
            if ( ! is_string( $term_color ) || '' === trim( (string) $term_color ) ) {
                continue;
            }

            $color = trim( (string) $term_color );
            $keys = array_filter(
                array_unique(
                    array(
                        sanitize_title( (string) $term->slug ),
                        sanitize_title( (string) $term->name ),
                        shikkosa_color_norm_key_local( $term->name ),
                    )
                )
            );

            foreach ( $keys as $key ) {
                if ( '' !== $key ) {
                    $cache[ $key ] = $color;
                }
            }
        }
    }

    return $cache;
}

function shikkosa_resolve_color_local( $slug, $name = '' ) {
    // Use shared resolver from catalog filters when available.
    if ( function_exists( 'shikkosa_resolve_color_value' ) ) {
        $shared = shikkosa_resolve_color_value( $slug, $name );
        if ( is_string( $shared ) && '' !== trim( $shared ) ) {
            return trim( $shared );
        }
    }

    // Main centralized source for product cards: color from attribute terms/ACF.
    $indexed_terms = shikkosa_color_terms_index_local();
    $indexed_keys = array_filter(
        array_unique(
            array(
                sanitize_title( (string) $slug ),
                sanitize_title( (string) $name ),
                shikkosa_color_norm_key_local( $slug ),
                shikkosa_color_norm_key_local( $name ),
            )
        )
    );
    foreach ( $indexed_keys as $idx_key ) {
        if ( isset( $indexed_terms[ $idx_key ] ) && '' !== trim( (string) $indexed_terms[ $idx_key ] ) ) {
            return trim( (string) $indexed_terms[ $idx_key ] );
        }
    }

    $taxonomies = shikkosa_color_taxonomies_local();
    $candidates = array_filter(
        array_unique(
            array(
                sanitize_title( (string) $slug ),
                sanitize_title( (string) $name ),
            )
        )
    );

    // Reuse shared name->slug mapper if available (same behavior as filters).
    if ( function_exists( 'shikkosa_find_color_slug_by_name' ) && '' !== trim( (string) $name ) ) {
        $mapped_slug = shikkosa_find_color_slug_by_name( $name, $taxonomies );
        if ( is_string( $mapped_slug ) && '' !== trim( $mapped_slug ) ) {
            $candidates[] = sanitize_title( $mapped_slug );
        }
    }
    $candidates = array_values( array_unique( array_filter( $candidates ) ) );

    foreach ( $taxonomies as $tax ) {
        if ( ! taxonomy_exists( $tax ) ) {
            continue;
        }
        if ( '' !== trim( (string) $name ) ) {
            $term_by_name = get_term_by( 'name', (string) $name, $tax );
            if ( $term_by_name && ! is_wp_error( $term_by_name ) ) {
                $term_color = get_term_meta( $term_by_name->term_id, 'color', true );
                if ( ! $term_color && function_exists( 'get_field' ) ) {
                    $term_color = get_field( 'color', $term_by_name );
                }
                if ( ! $term_color && function_exists( 'get_field' ) ) {
                    $term_color = get_field( 'color', $tax . '_' . $term_by_name->term_id );
                }
                if ( is_array( $term_color ) && isset( $term_color['value'] ) ) {
                    $term_color = $term_color['value'];
                }
                if ( is_string( $term_color ) && '' !== trim( $term_color ) ) {
                    return trim( $term_color );
                }
            }
        }
        foreach ( $candidates as $candidate_slug ) {
            $term = get_term_by( 'slug', $candidate_slug, $tax );
            if ( ! ( $term instanceof WP_Term ) ) {
                continue;
            }
            $term_color = get_term_meta( $term->term_id, 'color', true );
            if ( ! $term_color && function_exists( 'get_field' ) ) {
                $term_color = get_field( 'color', $term );
            }
            if ( ! $term_color && function_exists( 'get_field' ) ) {
                $term_color = get_field( 'color', $tax . '_' . $term->term_id );
            }
            if ( is_array( $term_color ) && isset( $term_color['value'] ) ) {
                $term_color = $term_color['value'];
            }
            if ( is_string( $term_color ) && '' !== trim( $term_color ) ) {
                return trim( $term_color );
            }
        }

        if ( '' !== trim( (string) $name ) ) {
            $needle = sanitize_title( (string) $name );
            if ( '' !== $needle ) {
                $terms = get_terms(
                    array(
                        'taxonomy'   => $tax,
                        'hide_empty' => false,
                    )
                );
                if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
                    foreach ( $terms as $term_item ) {
                        if ( ! ( $term_item instanceof WP_Term ) ) {
                            continue;
                        }
                        if ( sanitize_title( (string) $term_item->name ) !== $needle ) {
                            continue;
                        }
                        $term_color = get_term_meta( $term_item->term_id, 'color', true );
                        if ( ! $term_color && function_exists( 'get_field' ) ) {
                            $term_color = get_field( 'color', $tax . '_' . $term_item->term_id );
                        }
                        if ( ! $term_color && function_exists( 'get_field' ) ) {
                            $term_color = get_field( 'color', $term_item );
                        }
                        if ( is_array( $term_color ) && isset( $term_color['value'] ) ) {
                            $term_color = $term_color['value'];
                        }
                        if ( is_string( $term_color ) && '' !== trim( $term_color ) ) {
                            return trim( $term_color );
                        }
                    }
                }
            }
        }
    }

    $palette = shikkosa_color_palette_map_local();
    $try_keys = array_filter(
        array_unique(
            array(
                sanitize_title( (string) $slug ),
                sanitize_title( (string) $name ),
            )
        )
    );
    foreach ( $try_keys as $k ) {
        if ( isset( $palette[ $k ] ) ) {
            return $palette[ $k ];
        }
        // Transliteration variants: -yj / -yy / -iy / -yi
        $alts = array(
            str_replace( array( 'yj', 'yy', 'iy' ), 'yi', $k ),
            str_replace( array( 'yi', 'yy', 'iy' ), 'yj', $k ),
            str_replace( array( 'yi', 'yj', 'iy' ), 'yy', $k ),
            str_replace( array( 'yi', 'yj', 'yy' ), 'iy', $k ),
        );
        foreach ( array_unique( $alts ) as $alt_key ) {
            if ( isset( $palette[ $alt_key ] ) ) {
                return $palette[ $alt_key ];
            }
        }
    }
    return '';
}

function shikkosa_product_attributes_inline() {
    $product_id = is_product() ? get_queried_object_id() : get_the_ID();
    $current_product = wc_get_product( $product_id );

    if ( $current_product && $current_product->is_type( 'variable' ) ) {
        $attributes = $current_product->get_variation_attributes();
        $available_variations = $current_product->get_available_variations();
        $variation_children = (array) $current_product->get_children();
        $fallback_options_map = array();
        $fallback_variations_ui = array();

        foreach ( $variation_children as $variation_id ) {
            $variation_id = (int) $variation_id;
            if ( $variation_id <= 0 ) {
                continue;
            }
            $variation_obj = wc_get_product( $variation_id );
            if ( ! $variation_obj || ! $variation_obj->is_type( 'variation' ) ) {
                continue;
            }

            $raw_attrs = (array) $variation_obj->get_attributes();
            $ui_attrs = array();
            foreach ( $raw_attrs as $raw_key => $raw_value ) {
                $raw_key = (string) $raw_key;
                $raw_value = trim( (string) $raw_value );
                if ( '' === $raw_value ) {
                    continue;
                }
                $ui_key = ( 0 === strpos( $raw_key, 'attribute_' ) ) ? $raw_key : ( 'attribute_' . $raw_key );
                $ui_attrs[ $ui_key ] = $raw_value;
                if ( ! isset( $fallback_options_map[ $ui_key ] ) ) {
                    $fallback_options_map[ $ui_key ] = array();
                }
                $fallback_options_map[ $ui_key ][ $raw_value ] = $raw_value;
            }

            $fallback_variations_ui[] = array(
                'variation_id' => $variation_id,
                'attributes'   => $ui_attrs,
                'is_in_stock'  => $variation_obj->is_in_stock(),
            );
        }

        // Extra fallback: some imported variable products have empty variation
        // attributes, but size is still encoded in variation SKU.
        if ( empty( $fallback_options_map ) && ! empty( $fallback_variations_ui ) ) {
            foreach ( $fallback_variations_ui as $idx => $variation_ui ) {
                $variation_id = isset( $variation_ui['variation_id'] ) ? (int) $variation_ui['variation_id'] : 0;
                if ( $variation_id <= 0 ) {
                    continue;
                }

                $variation_obj = wc_get_product( $variation_id );
                if ( ! $variation_obj ) {
                    continue;
                }

                $size_from_sku = shikkosa_extract_size_from_sku_local( $variation_obj->get_sku() );
                if ( '' === $size_from_sku ) {
                    continue;
                }

                $attr_key = 'attribute_pa_razmer';
                if ( ! isset( $fallback_options_map[ $attr_key ] ) ) {
                    $fallback_options_map[ $attr_key ] = array();
                }
                $fallback_options_map[ $attr_key ][ $size_from_sku ] = $size_from_sku;

                if ( empty( $fallback_variations_ui[ $idx ]['attributes'] ) || ! is_array( $fallback_variations_ui[ $idx ]['attributes'] ) ) {
                    $fallback_variations_ui[ $idx]['attributes'] = array();
                }
                $fallback_variations_ui[ $idx]['attributes'][ $attr_key ] = $size_from_sku;
            }
        }

        // Hard fallback for broken imported variable products:
        // if variation attributes are still empty, at least show sizes from parent meta.
        if ( empty( $fallback_options_map ) ) {
            $meta_sizes_fallback = shikkosa_parse_pipe_values_local( (string) get_post_meta( $current_product->get_id(), '_shk_sizes', true ) );
            if ( ! empty( $meta_sizes_fallback ) ) {
                $fallback_options_map['attribute_pa_razmer'] = array();
                foreach ( $meta_sizes_fallback as $ms ) {
                    $ms = trim( (string) $ms );
                    if ( '' !== $ms ) {
                        $fallback_options_map['attribute_pa_razmer'][ $ms ] = $ms;
                    }
                }
            }

            $fallback_color = '';
            $meta_color = trim( (string) get_post_meta( $current_product->get_id(), '_shk_color', true ) );
            if ( '' !== $meta_color ) {
                $fallback_color = $meta_color;
            } else {
                $fallback_color = shikkosa_guess_color_from_sku_local( $current_product->get_sku() );
            }

            if ( '' !== $fallback_color ) {
                $fallback_options_map['attribute_pa_czvet'] = array( $fallback_color => $fallback_color );
            }
        }

        // Ensure color exists in fallback map even when only size was rebuilt from variations.
        $has_color_attr = (
            isset( $fallback_options_map['attribute_pa_czvet'] ) ||
            isset( $fallback_options_map['attribute_czvet'] ) ||
            isset( $fallback_options_map['attribute_pa_color'] ) ||
            isset( $fallback_options_map['attribute_color'] )
        );
        if ( ! $has_color_attr ) {
            $fallback_color = trim( (string) get_post_meta( $current_product->get_id(), '_shk_color', true ) );
            if ( '' === $fallback_color ) {
                $fallback_color = shikkosa_guess_color_from_sku_local( $current_product->get_sku() );
            }
            if ( '' === $fallback_color && ! empty( $variation_children ) ) {
                foreach ( $variation_children as $variation_id_guess ) {
                    $variation_obj_guess = wc_get_product( (int) $variation_id_guess );
                    if ( ! $variation_obj_guess ) {
                        continue;
                    }
                    $fallback_color = shikkosa_guess_color_from_sku_local( $variation_obj_guess->get_sku() );
                    if ( '' !== $fallback_color ) {
                        break;
                    }
                }
            }
            if ( '' !== $fallback_color ) {
                $fallback_options_map['attribute_pa_czvet'] = array( $fallback_color => $fallback_color );
            }
        }

        // Some donor/imported products store variation options only on child variations.
        // Rebuild/extend product-level attributes from variation children.
        if ( empty( $attributes ) && ! empty( $fallback_options_map ) ) {
            $attributes = array();
            foreach ( $fallback_options_map as $attr_key => $opts_map ) {
                $attributes[ $attr_key ] = array_values( $opts_map );
            }
        } elseif ( ! empty( $fallback_options_map ) ) {
            foreach ( $fallback_options_map as $attr_key => $opts_map ) {
                if ( ! isset( $attributes[ $attr_key ] ) || ! is_array( $attributes[ $attr_key ] ) ) {
                    $attributes[ $attr_key ] = array_values( $opts_map );
                    continue;
                }
                $merged = array_merge( $attributes[ $attr_key ], array_values( $opts_map ) );
                $attributes[ $attr_key ] = array_values( array_unique( array_filter( $merged ) ) );
            }
        }

        // get_available_variations() can return empty attributes for linked-variation setups.
        // If so, use our child-variation payload for UI logic.
        $has_structured_attrs = false;
        foreach ( $available_variations as $v_item ) {
            if ( ! empty( $v_item['attributes'] ) && is_array( $v_item['attributes'] ) ) {
                foreach ( $v_item['attributes'] as $v_attr_val ) {
                    if ( '' !== trim( (string) $v_attr_val ) ) {
                        $has_structured_attrs = true;
                        break 2;
                    }
                }
            }
        }
        if ( ! $has_structured_attrs && ! empty( $fallback_variations_ui ) ) {
            $available_variations = $fallback_variations_ui;
        }

        $in_stock_option_map = array();
        $has_any_in_stock_variation = false;
        $normalize_attr_key = static function( $value ) {
            $value = strtolower( trim( (string) $value ) );
            $value = preg_replace( '/^attribute_/', '', $value );
            return $value;
        };
        $normalize_option_key = static function( $value ) {
            $raw = strtolower( trim( (string) $value ) );
            $slug = sanitize_title( (string) $value );
            $alt = str_replace( '/', '-', $raw );
            return array_values( array_unique( array_filter( array( $raw, $slug, $alt ) ) ) );
        };
        $attr_lookup = array();

        foreach ( $attributes as $attribute_name => $options ) {
            $in_stock_option_map[ $attribute_name ] = array();
            $norm_attr = $normalize_attr_key( $attribute_name );
            if ( ! isset( $attr_lookup[ $norm_attr ] ) ) {
                $attr_lookup[ $norm_attr ] = array();
            }
            $attr_lookup[ $norm_attr ][] = $attribute_name;
            foreach ( (array) $options as $option_value ) {
                foreach ( $normalize_option_key( $option_value ) as $opt_key ) {
                    $in_stock_option_map[ $attribute_name ][ $opt_key ] = false;
                }
            }
        }

        foreach ( $available_variations as $variation ) {
            if ( empty( $variation['is_in_stock'] ) ) {
                continue;
            }
            $has_any_in_stock_variation = true;
            $v_attrs = isset( $variation['attributes'] ) ? (array) $variation['attributes'] : array();
            foreach ( $v_attrs as $v_attr_name => $v_option ) {
                $v_attr_name = (string) $v_attr_name;
                $v_option = (string) $v_option;
                if ( '' === $v_option ) {
                    continue;
                }
                $norm_attr = $normalize_attr_key( $v_attr_name );
                $target_attrs = isset( $attr_lookup[ $norm_attr ] ) ? (array) $attr_lookup[ $norm_attr ] : array( $v_attr_name );
                $option_keys = $normalize_option_key( $v_option );
                foreach ( $target_attrs as $target_attr_name ) {
                    if ( ! isset( $in_stock_option_map[ $target_attr_name ] ) ) {
                        $in_stock_option_map[ $target_attr_name ] = array();
                    }
                    foreach ( $option_keys as $option_key ) {
                        $in_stock_option_map[ $target_attr_name ][ $option_key ] = true;
                    }
                }
            }
        }

        // Safety: avoid UI state when every option becomes disabled
        // because variation availability map is incomplete.
        foreach ( $in_stock_option_map as $attr_key => $option_flags ) {
            $has_enabled = false;
            foreach ( (array) $option_flags as $flag ) {
                if ( $flag ) {
                    $has_enabled = true;
                    break;
                }
            }
            if ( ! $has_enabled ) {
                foreach ( array_keys( (array) $option_flags ) as $opt_key ) {
                    $in_stock_option_map[ $attr_key ][ $opt_key ] = true;
                }
            }
        }

        if ( ! $has_any_in_stock_variation ) {
            foreach ( $in_stock_option_map as $attr_key => $option_flags ) {
                foreach ( array_keys( (array) $option_flags ) as $opt_key ) {
                    $in_stock_option_map[ $attr_key ][ $opt_key ] = true;
                }
            }
        }

        $czvet_options = array();
        if ( isset( $attributes['attribute_pa_czvet'] ) && is_array( $attributes['attribute_pa_czvet'] ) ) {
            $czvet_options = $attributes['attribute_pa_czvet'];
        } elseif ( isset( $attributes['attribute_czvet'] ) && is_array( $attributes['attribute_czvet'] ) ) {
            $czvet_options = $attributes['attribute_czvet'];
        }

        if ( ! empty( $czvet_options ) ) {
            unset( $attributes['attribute_pa_color'] );
            unset( $attributes['attribute_color'] );
        }

        // Cross-product color family for imported "one color per product" setups.
        // Use strict resolver to avoid polluted related/upsell chains.
        $family_ids_var = shikkosa_collect_color_family_ids_local( $current_product->get_id(), true );

        $family_links_var = array();
        foreach ( $family_ids_var as $member_id ) {
            $member_id = (int) $member_id;
            if ( $member_id <= 0 ) {
                continue;
            }

            $member_product = wc_get_product( $member_id );
            if ( ! $member_product ) {
                continue;
            }

            $member_color = trim( (string) get_post_meta( $member_id, '_shk_color', true ) );
            if ( '' === $member_color ) {
                $member_colors = shikkosa_collect_product_colors_local( $member_id );
                if ( ! empty( $member_colors ) ) {
                    $member_color = (string) $member_colors[0];
                }
            }
            if ( '' === $member_color ) {
                $member_color = (string) $member_product->get_name();
            }

            $family_links_var[ $member_id ] = array(
                'product_id' => $member_id,
                'url'        => get_permalink( $member_id ),
                'label'      => $member_color,
                'color'      => shikkosa_resolve_color_local( $member_color, $member_color ),
                'active'     => ( $member_id === (int) $current_product->get_id() ),
            );
        }
        $family_links_var = array_values( $family_links_var );
        $family_links_var = shikkosa_dedupe_family_links_by_color_local( $family_links_var );

        // If cross-product family exists, suppress local variation color rows (they represent only current product).
        if ( count( $family_links_var ) > 1 ) {
            foreach ( array_keys( $attributes ) as $attr_key ) {
                $key_lc = strtolower( (string) $attr_key );
                if (
                    false !== strpos( $key_lc, 'color' ) ||
                    false !== strpos( $key_lc, 'czvet' ) ||
                    false !== strpos( $key_lc, 'cvet' ) ||
                    false !== strpos( $key_lc, 'цвет' )
                ) {
                    unset( $attributes[ $attr_key ] );
                }
            }
        }

        $base_sku = trim( (string) $current_product->get_sku() );
        $out = '<div class="shk-inline-attrs" data-product-id="' . esc_attr( $current_product->get_id() ) . '" data-product-type="variable" data-base-sku="' . esc_attr( $base_sku ) . '">';
        if ( '' !== $base_sku ) {
            $out .= '<p class="product-sku shk-live-sku-wrap"><span class="shk-live-sku-label">Артикул: </span><span class="shk-live-sku" data-shk-sku-live="1">' . esc_html( $base_sku ) . '</span></p>';
        }

        foreach ( $attributes as $attribute_name => $options ) {
            if ( empty( $options ) || ! is_array( $options ) ) {
                continue;
            }

            $is_color_attr = (
                strpos( $attribute_name, 'color' ) !== false ||
                strpos( $attribute_name, 'czvet' ) !== false ||
                strpos( $attribute_name, 'cvet' ) !== false ||
                strpos( $attribute_name, 'цвет' ) !== false
            );
            $is_size_attr = (
                strpos( $attribute_name, 'size' ) !== false ||
                strpos( $attribute_name, 'razmer' ) !== false ||
                strpos( $attribute_name, 'размер' ) !== false
            );
            if ( $is_color_attr ) {
                $attr_label = 'Цвет';
            } elseif ( $is_size_attr ) {
                $attr_label = 'Размер';
            } else {
                $attr_label = wc_attribute_label( $attribute_name );
            }

            $out .= '<div class="attribute-row" data-attribute-name="' . esc_attr( $attribute_name ) . '">';
            $out .= '<span class="attribute-label">' . esc_html( $attr_label ) . ':</span>';

            foreach ( $options as $option ) {
                $taxonomy = str_replace( 'attribute_', '', $attribute_name );
                $display_value = $option;
                $is_option_in_stock = true;
                $option_keys = $normalize_option_key( $option );
                $is_option_in_stock = false;
                foreach ( $option_keys as $option_key ) {
                    if ( ! empty( $in_stock_option_map[ $attribute_name ][ $option_key ] ) ) {
                        $is_option_in_stock = true;
                        break;
                    }
                }

                $taxonomy_candidates = array( $taxonomy );
                if ( strpos( $taxonomy, 'pa_' ) !== 0 ) {
                    $taxonomy_candidates[] = 'pa_' . $taxonomy;
                }

                $display_term = false;
                foreach ( $taxonomy_candidates as $tax ) {
                    if ( taxonomy_exists( $tax ) ) {
                        $display_term = get_term_by( 'slug', $option, $tax );
                        if ( $display_term && ! is_wp_error( $display_term ) ) {
                            $display_value = $display_term->name;
                            break;
                        }
                    }
                }

                if ( $is_color_attr ) {
                    $term = false;
                    foreach ( $taxonomy_candidates as $tax ) {
                        if ( taxonomy_exists( $tax ) ) {
                            $term = get_term_by( 'slug', $option, $tax );
                            if ( ! $term || is_wp_error( $term ) ) {
                                $term = get_term_by( 'name', $display_value, $tax );
                            }
                            if ( $term && ! is_wp_error( $term ) ) {
                                break;
                            }
                        }
                    }

                    if ( ! $term ) {
                        $fallback_color = shikkosa_resolve_color_local( $option, $display_value );
                        $out .= '<button type="button" class="color-swatch shk-attr-option" data-attribute-name="' . esc_attr( $attribute_name ) . '" data-value="' . esc_attr( $option ) . '"><span class="shk-color-dot" style="background:' . esc_attr( $fallback_color ? $fallback_color : '#d9d9d9' ) . ';"></span><span class="shk-color-name">' . esc_html( $display_value ) . '</span></button>';
                        continue;
                    }

                    $term_taxonomy = ! empty( $term->taxonomy ) ? $term->taxonomy : $taxonomy;
                    $term_context  = $term_taxonomy . '_' . $term->term_id;

                    $color = '';
                    if ( function_exists( 'shikkosa_get_term_color_value' ) ) {
                        $color = shikkosa_get_term_color_value( $term );
                    }
                    if ( ! $color ) {
                        $color = get_term_meta( $term->term_id, 'color', true );
                    }
                    if ( ! $color && function_exists( 'get_field' ) ) {
                        $color = get_field( 'color', $term_context );
                    }
                    if ( ! $color && function_exists( 'get_field' ) ) {
                        $color = get_field( 'color', $term );
                    }

                    if ( is_array( $color ) && isset( $color['value'] ) ) {
                        $color = $color['value'];
                    }

                    if ( ! $color ) {
                        if ( function_exists( 'shikkosa_resolve_color_value' ) ) {
                            $color = shikkosa_resolve_color_value( $term->slug, $term->name );
                        }
                    }

                    if ( ! $color ) {
                        $color = shikkosa_resolve_color_local( $term->slug, $term->name );
                    }

                    if ( ! $color ) {
                        $color = shikkosa_resolve_color_local( $option, $display_value );
                    }

                    if ( $color ) {
                        $out .= '<button type="button" class="color-swatch shk-attr-option" data-attribute-name="' . esc_attr( $attribute_name ) . '" data-value="' . esc_attr( $option ) . '" aria-label="' . esc_attr( $display_value ) . '"><span class="shk-color-dot" style="background:' . esc_attr( $color ) . ';"></span><span class="shk-color-name">' . esc_html( $display_value ) . '</span></button>';
                    } else {
                        $out .= '<button type="button" class="color-swatch shk-attr-option" data-attribute-name="' . esc_attr( $attribute_name ) . '" data-value="' . esc_attr( $option ) . '"><span class="shk-color-dot" style="background:#d9d9d9;"></span><span class="shk-color-name">' . esc_html( $display_value ) . '</span></button>';
                    }
                } else {
                    // Hide out-of-stock size options completely: client expects
                    // size with qty=0 not to be shown on storefront.
                    if ( $is_size_attr && ! $is_option_in_stock ) {
                        continue;
                    }
                    $out .= '<button type="button" class="size-swatch shk-attr-option" data-attribute-name="' . esc_attr( $attribute_name ) . '" data-value="' . esc_attr( $option ) . '">' . esc_html( $display_value ) . '</button>';
                }
            }

            $out .= '</div>';
        }

        if ( ! empty( $family_links_var ) ) {
            $out .= '<div class="attribute-row" data-attribute-name="shk_color_family">';
            $out .= '<span class="attribute-label">Цвет:</span>';
            foreach ( $family_links_var as $item ) {
                $classes = 'color-swatch shk-color-link';
                if ( ! empty( $item['active'] ) ) {
                    $classes .= ' is-active';
                }
                $dot_color = ! empty( $item['color'] ) ? $item['color'] : '#d9d9d9';
                $dot_html = '<span class="shk-color-dot" style="background:' . esc_attr( $dot_color ) . ';"></span>';
                $out .= '<a class="' . esc_attr( $classes ) . '" href="' . esc_url( $item['url'] ) . '" data-product-id="' . esc_attr( $item['product_id'] ) . '">' . $dot_html . '<span class="shk-color-name">' . esc_html( $item['label'] ) . '</span></a>';
            }
            $out .= '</div>';
        }

        $light_variations = array();

        foreach ( $available_variations as $variation ) {
            $light_variations[] = array(
                'variation_id' => isset( $variation['variation_id'] ) ? (int) $variation['variation_id'] : 0,
                'attributes'   => isset( $variation['attributes'] ) ? (array) $variation['attributes'] : array(),
                'is_in_stock'  => ! empty( $variation['is_in_stock'] ),
            );
        }

        $default_attributes = (array) $current_product->get_default_attributes();
        $selected_attributes = array();

        foreach ( $attributes as $attribute_name => $opts ) {
            $clean_name = preg_replace( '/^attribute_/', '', $attribute_name );

            if ( isset( $_GET[ $attribute_name ] ) ) {
                $selected_attributes[ $attribute_name ] = wc_clean( wp_unslash( $_GET[ $attribute_name ] ) );
                continue;
            }

            if ( isset( $_GET[ $clean_name ] ) ) {
                $selected_attributes[ $attribute_name ] = wc_clean( wp_unslash( $_GET[ $clean_name ] ) );
                continue;
            }

            if ( isset( $default_attributes[ $clean_name ] ) ) {
                $selected_attributes[ $attribute_name ] = $default_attributes[ $clean_name ];
            }
        }

        $out .= '<script type="application/json" class="shk-variations-json">' . wp_json_encode( $light_variations ) . '</script>';
        $out .= '<script type="application/json" class="shk-selected-attrs-json">' . wp_json_encode( $selected_attributes ) . '</script>';
        $out .= '<div class="shk-inline-price">' . wp_kses_post( shikkosa_get_price_html_with_old_price_local( $current_product ) ) . '</div>';
        $out .= '<button type="button" class="shk-add-to-cart-fallback" style="display:none;" disabled>Выберите параметры</button>';
        $out .= '</div>';
        return $out;
    }

    if ( $current_product && $current_product->is_type( 'simple' ) ) {
        $is_purchasable = $current_product->is_purchasable() && $current_product->is_in_stock();
        $simple_availability = array();
        $source_slug = trim( (string) get_post_meta( $current_product->get_id(), '_shk_source_slug', true ) );
        $product_colors = shikkosa_collect_product_colors_local( $current_product->get_id() );
        $current_color = trim( (string) get_post_meta( $current_product->get_id(), '_shk_color', true ) );
        if ( '' === $current_color && ! empty( $product_colors ) ) {
            $current_color = (string) $product_colors[0];
        }
        $family_ids = shikkosa_collect_color_family_ids_local( $current_product->get_id(), true );
        $family_ids = array_merge(
            $family_ids,
            shikkosa_parse_int_values_local( get_post_meta( $current_product->get_id(), '_shk_color_family_ids', true ) ),
            shikkosa_parse_int_values_local( get_post_meta( $current_product->get_id(), '_shk_related_ids', true ) )
        );
        $upsell_ids = array_values( array_map( 'intval', (array) $current_product->get_upsell_ids() ) );
        if ( ! empty( $upsell_ids ) ) {
            $family_ids = array_merge( $family_ids, $upsell_ids );
        }
        $family_ids[] = (int) $current_product->get_id();
        $family_ids = array_values( array_unique( array_filter( array_map( 'intval', $family_ids ) ) ) );

        $base_sku = trim( (string) $current_product->get_sku() );
        $out  = '<div class="shk-inline-attrs" data-product-id="' . esc_attr( $current_product->get_id() ) . '" data-product-type="simple" data-product-purchasable="' . ( $is_purchasable ? '1' : '0' ) . '" data-base-sku="' . esc_attr( $base_sku ) . '">';
        if ( '' !== $base_sku ) {
            $out .= '<p class="product-sku shk-live-sku-wrap"><span class="shk-live-sku-label">Артикул: </span><span class="shk-live-sku" data-shk-sku-live="1">' . esc_html( $base_sku ) . '</span></p>';
        }

        $current_sizes = shikkosa_collect_product_sizes_local( $current_product->get_id() );
        $current_sizes_norm = array();
        foreach ( $current_sizes as $size_value ) {
            $norm = shikkosa_normalize_size_local( $size_value );
            if ( '' !== $norm ) {
                $current_sizes_norm[ $norm ] = true;
            }
        }

        // Primary source for size chips on simple products is the product's own
        // size attribute/meta. Pulling union from color-family often introduces
        // unrelated sizes when legacy family links are noisy.
        $all_sizes = array_values( $current_sizes );
        if ( empty( $all_sizes ) ) {
            $family_sizes = array();
            foreach ( $family_ids as $member_id ) {
                $member_sizes = shikkosa_collect_product_sizes_local( $member_id );
                foreach ( $member_sizes as $member_size ) {
                    $member_size = trim( (string) $member_size );
                    if ( '' !== $member_size ) {
                        $family_sizes[ $member_size ] = $member_size;
                    }
                }
            }
            $all_sizes = array_values( $family_sizes );
        }
        if ( ! empty( $all_sizes ) ) {
            $order = shikkosa_size_scale_local();
            $rank = array();
            foreach ( $order as $i => $label ) {
                $rank[ shikkosa_normalize_size_local( $label ) ] = (int) $i;
            }

            usort(
                $all_sizes,
                static function ( $a, $b ) use ( $rank ) {
                    $an = shikkosa_normalize_size_local( $a );
                    $bn = shikkosa_normalize_size_local( $b );
                    $ai = isset( $rank[ $an ] ) ? $rank[ $an ] : 9999;
                    $bi = isset( $rank[ $bn ] ) ? $rank[ $bn ] : 9999;
                    if ( $ai !== $bi ) {
                        return $ai <=> $bi;
                    }
                    return strnatcasecmp( (string) $a, (string) $b );
                }
            );

            $row_attr_name = 'attribute_pa_razmer';
            $out .= '<div class="attribute-row" data-attribute-name="' . esc_attr( $row_attr_name ) . '">';
            $out .= '<span class="attribute-label">Размер:</span>';
            foreach ( $all_sizes as $size_option ) {
                $is_available = ! empty( $current_sizes_norm[ shikkosa_normalize_size_local( $size_option ) ] );
                $out .= '<button type="button" class="size-swatch shk-attr-option' . ( $is_available ? '' : ' disabled' ) . '" data-attribute-name="' . esc_attr( $row_attr_name ) . '" data-value="' . esc_attr( $size_option ) . '"' . ( $is_available ? '' : ' disabled' ) . '>' . esc_html( $size_option ) . '</button>';
                $simple_availability[ $row_attr_name . '::' . $size_option ] = (bool) $is_available;
            }
            $out .= '</div>';
        }

        // Color-family links for imported simple products (source-based color variants).
        $family_links = array();
        foreach ( $family_ids as $member_id ) {
            $member_id = (int) $member_id;
            if ( $member_id <= 0 ) {
                continue;
            }
            $member_color = trim( (string) get_post_meta( $member_id, '_shk_color', true ) );
            if ( '' === $member_color ) {
                $member_product = wc_get_product( $member_id );
                $member_color = $member_product ? $member_product->get_name() : (string) $member_id;
            }

            $dot_color = shikkosa_resolve_color_local( $member_color, $member_color );
            $family_links[ $member_id ] = array(
                'product_id' => (int) $member_id,
                'url'        => get_permalink( $member_id ),
                'label'      => $member_color,
                'color'      => $dot_color,
                'active'     => ( (int) $member_id === (int) $current_product->get_id() ),
            );
        }
        $family_links = array_values( $family_links );
        $family_links = shikkosa_dedupe_family_links_by_color_local( $family_links );

        // If color family is empty, still show current product color (single chip).
        if ( empty( $family_links ) && '' !== $current_color ) {
            $family_links[] = array(
                'product_id' => (int) $current_product->get_id(),
                'url'        => get_permalink( $current_product->get_id() ),
                'label'      => $current_color,
                'color'      => shikkosa_resolve_color_local( $current_color, $current_color ),
                'active'     => true,
            );
        }

        if ( ! empty( $family_links ) ) {
            $out .= '<div class="attribute-row" data-attribute-name="shk_color_family">';
            $out .= '<span class="attribute-label">Цвет:</span>';
            foreach ( $family_links as $item ) {
                $classes = 'color-swatch shk-color-link';
                if ( ! empty( $item['active'] ) ) {
                    $classes .= ' is-active';
                }
                $dot_color = ! empty( $item['color'] ) ? $item['color'] : '#d9d9d9';
                $dot_html = '<span class="shk-color-dot" style="background:' . esc_attr( $dot_color ) . ';"></span>';
                $out .= '<a class="' . esc_attr( $classes ) . '" href="' . esc_url( $item['url'] ) . '" data-product-id="' . esc_attr( $item['product_id'] ) . '">' . $dot_html . '<span class="shk-color-name">' . esc_html( $item['label'] ) . '</span></a>';
            }
            $out .= '</div>';
        } elseif ( '' !== $current_color ) {
            $out .= '<div class="attribute-row" data-attribute-name="shk_color_family">';
            $out .= '<span class="attribute-label">Цвет:</span>';
            $single_dot = shikkosa_resolve_color_local( $current_color, $current_color );
            $single_dot_html = '<span class="shk-color-dot" style="background:' . esc_attr( $single_dot ? $single_dot : '#d9d9d9' ) . ';"></span>';
            $out .= '<span class="color-swatch is-active">' . $single_dot_html . '<span class="shk-color-name">' . esc_html( $current_color ) . '</span></span>';
            $out .= '</div>';
        }

        $out .= '<div class="shk-inline-price">' . wp_kses_post( shikkosa_get_price_html_with_old_price_local( $current_product ) ) . '</div>';
        $out .= '<button type="button" class="shk-add-to-cart-fallback" style="display:none;"' . ( $is_purchasable ? '' : ' disabled' ) . '>';
        $out .= $is_purchasable ? 'Добавить в корзину' : 'Нет в наличии';
        $out .= '</button>';
        $out .= '<script type="application/json" class="shk-simple-availability-json">' . wp_json_encode( $simple_availability ) . '</script>';
        $out .= '</div>';

        return $out;
    }

    return '';
}
add_shortcode('shikkosa_product_attributes_inline', 'shikkosa_product_attributes_inline');

add_action( 'wp_ajax_shk_add_to_cart', 'shikkosa_ajax_add_to_cart' );
add_action( 'wp_ajax_nopriv_shk_add_to_cart', 'shikkosa_ajax_add_to_cart' );
function shikkosa_ajax_add_to_cart() {
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        wp_send_json_error( array( 'message' => 'Корзина сейчас недоступна.' ), 500 );
    }

    $product_id   = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
    $variation_id = isset( $_POST['variation_id'] ) ? absint( wp_unslash( $_POST['variation_id'] ) ) : 0;
    $quantity     = isset( $_POST['quantity'] ) ? wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) : 1;

    if ( $quantity < 1 ) {
        $quantity = 1;
    }

    $attributes = array();
    $posted_selected_size = isset( $_POST['selected_size'] ) ? wc_clean( wp_unslash( $_POST['selected_size'] ) ) : '';
    if ( isset( $_POST['attributes'] ) ) {
        $raw_attributes = wp_unslash( $_POST['attributes'] );

        if ( is_string( $raw_attributes ) ) {
            $decoded = json_decode( $raw_attributes, true );
            if ( is_array( $decoded ) ) {
                $raw_attributes = $decoded;
            }
        }

        if ( is_array( $raw_attributes ) ) {
            foreach ( $raw_attributes as $key => $value ) {
                $k = wc_clean( (string) $key );
                $v = wc_clean( (string) $value );
                if ( $k !== '' && $v !== '' ) {
                    $attributes[ $k ] = $v;
                }
            }
        }
    }

    if ( ! $product_id ) {
        wp_send_json_error( array( 'message' => 'Некорректный товар.' ), 400 );
    }

    $product_obj = wc_get_product( $product_id );
    if ( $product_obj && $product_obj->is_type( 'simple' ) ) {
        $available_sizes = shikkosa_collect_available_sizes_for_simple_product_local( $product_id );
        if ( ! empty( $available_sizes ) ) {
            $selected_size_norm = shikkosa_normalize_size_local( $posted_selected_size );
            if ( '' === $selected_size_norm ) {
                wp_send_json_error( array( 'message' => 'Выберите размер перед добавлением в корзину.' ), 400 );
            }

            $available_norm = array_values(
                array_unique(
                    array_filter(
                        array_map(
                            static function ( $size_value ) {
                                return shikkosa_normalize_size_local( $size_value );
                            },
                            $available_sizes
                        )
                    )
                )
            );

            if ( ! in_array( $selected_size_norm, $available_norm, true ) ) {
                wp_send_json_error( array( 'message' => 'Выбран некорректный размер.' ), 400 );
            }
        }
    }

    $passed = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $attributes );
    if ( ! $passed ) {
        $notice_message = '';
        if ( function_exists( 'wc_get_notices' ) ) {
            $error_notices = wc_get_notices( 'error' );
            if ( is_array( $error_notices ) && ! empty( $error_notices ) ) {
                $first = reset( $error_notices );
                if ( is_array( $first ) && ! empty( $first['notice'] ) ) {
                    $notice_message = wp_strip_all_tags( (string) $first['notice'] );
                } elseif ( is_string( $first ) ) {
                    $notice_message = wp_strip_all_tags( $first );
                }
            }
        }
        $notice_message = shikkosa_localize_cart_error_message_local( $notice_message );
        wp_send_json_error(
            array(
                'message' => '' !== trim( $notice_message ) ? $notice_message : 'Не удалось добавить товар в корзину. Проверьте выбранные параметры.',
            ),
            400
        );
    }

    $sku_data = shikkosa_build_cart_item_sku_data_local( $product_id, $variation_id, $attributes );
    if ( '' === trim( (string) $sku_data['selected_size'] ) && '' !== trim( (string) $posted_selected_size ) ) {
        $sku_data['selected_size'] = trim( (string) $posted_selected_size );
        $sku_data['effective_sku'] = shikkosa_compose_effective_sku_local( $sku_data['base_sku'], $sku_data['selected_size'] );
    }
    $cart_item_data = array(
        '_shk_selected_size' => $sku_data['selected_size'],
        '_shk_base_sku'      => $sku_data['base_sku'],
        '_shk_effective_sku' => $sku_data['effective_sku'],
    );
    $color_data = shikkosa_build_cart_item_color_data_local( $product_id, $variation_id, $attributes );
    if ( '' !== $color_data['selected_color'] ) {
        $cart_item_data['_shk_selected_color'] = $color_data['selected_color'];
    }
    if ( '' !== $color_data['color_label'] ) {
        $cart_item_data['_shk_selected_color_label'] = $color_data['color_label'];
    }
    if ( '' !== $color_data['color_hex'] ) {
        $cart_item_data['_shk_selected_color_hex'] = $color_data['color_hex'];
    }

    $added = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $attributes, $cart_item_data );
    if ( ! $added ) {
        $notice_message = '';
        if ( function_exists( 'wc_get_notices' ) ) {
            $error_notices = wc_get_notices( 'error' );
            if ( is_array( $error_notices ) && ! empty( $error_notices ) ) {
                $first = reset( $error_notices );
                if ( is_array( $first ) && ! empty( $first['notice'] ) ) {
                    $notice_message = wp_strip_all_tags( (string) $first['notice'] );
                } elseif ( is_string( $first ) ) {
                    $notice_message = wp_strip_all_tags( $first );
                }
            }
        }
        $notice_message = shikkosa_localize_cart_error_message_local( $notice_message );
        wp_send_json_error(
            array(
                'message' => '' !== trim( $notice_message ) ? $notice_message : 'Не удалось добавить товар в корзину.',
            ),
            400
        );
    }

    wc_clear_notices();

    wp_send_json_success(
        array(
            'cart_hash' => WC()->cart->get_cart_hash(),
            'count'     => WC()->cart->get_cart_contents_count(),
            'sku'       => $sku_data['effective_sku'],
        )
    );
}

add_filter(
    'woocommerce_add_cart_item_data',
    function( $cart_item_data, $product_id, $variation_id ) {
        if ( ! is_array( $cart_item_data ) ) {
            $cart_item_data = array();
        }

        if (
            ! empty( $cart_item_data['_shk_selected_color'] ) &&
            ! empty( $cart_item_data['_shk_selected_color_hex'] )
        ) {
            return $cart_item_data;
        }

        $attributes = array();
        if ( isset( $_POST['variation'] ) && is_array( $_POST['variation'] ) ) {
            foreach ( (array) $_POST['variation'] as $key => $value ) {
                $k = wc_clean( wp_unslash( (string) $key ) );
                $v = wc_clean( wp_unslash( (string) $value ) );
                if ( '' !== $k && '' !== $v ) {
                    $attributes[ $k ] = $v;
                }
            }
        }

        $color_data = shikkosa_build_cart_item_color_data_local( (int) $product_id, (int) $variation_id, $attributes );
        if ( '' !== $color_data['selected_color'] ) {
            $cart_item_data['_shk_selected_color'] = $color_data['selected_color'];
        }
        if ( '' !== $color_data['color_label'] ) {
            $cart_item_data['_shk_selected_color_label'] = $color_data['color_label'];
        }
        if ( '' !== $color_data['color_hex'] ) {
            $cart_item_data['_shk_selected_color_hex'] = $color_data['color_hex'];
        }

        return $cart_item_data;
    },
    20,
    3
);

add_action(
    'woocommerce_cart_loaded_from_session',
    function( $cart ) {
        if ( ! $cart || ! is_a( $cart, 'WC_Cart' ) || empty( $cart->cart_contents ) || ! is_array( $cart->cart_contents ) ) {
            return;
        }

        $updated = false;
        $meta_color_cache = array();
        $hex_cache = array();

        foreach ( $cart->cart_contents as $cart_item_key => $cart_item ) {
            if ( ! is_array( $cart_item ) ) {
                continue;
            }

            $original_selected_color = isset( $cart_item['_shk_selected_color'] ) ? (string) $cart_item['_shk_selected_color'] : '';
            $original_color_label = isset( $cart_item['_shk_selected_color_label'] ) ? (string) $cart_item['_shk_selected_color_label'] : '';
            $original_color_hex = isset( $cart_item['_shk_selected_color_hex'] ) ? (string) $cart_item['_shk_selected_color_hex'] : '';

            $has_color = ! empty( $cart_item['_shk_selected_color'] ) || ! empty( $cart_item['_shk_selected_color_label'] );
            $has_hex   = ! empty( $cart_item['_shk_selected_color_hex'] );
            if ( $has_color && $has_hex ) {
                continue;
            }

            $product_id = ! empty( $cart_item['product_id'] ) ? (int) $cart_item['product_id'] : 0;
            $variation_id = ! empty( $cart_item['variation_id'] ) ? (int) $cart_item['variation_id'] : 0;
            $attributes = isset( $cart_item['variation'] ) && is_array( $cart_item['variation'] ) ? (array) $cart_item['variation'] : array();

            $selected_color = '';
            $color_label = '';
            $color_hex = '';

            if ( ! empty( $cart_item['_shk_selected_color'] ) ) {
                $selected_color = trim( (string) $cart_item['_shk_selected_color'] );
            }
            if ( ! empty( $cart_item['_shk_selected_color_label'] ) ) {
                $color_label = trim( (string) $cart_item['_shk_selected_color_label'] );
            }
            if ( ! empty( $cart_item['_shk_selected_color_hex'] ) ) {
                $color_hex = trim( (string) $cart_item['_shk_selected_color_hex'] );
            }

            if ( '' === $selected_color && '' === $color_label ) {
                $selected_color = shikkosa_extract_color_from_attributes_local( $attributes );
                if ( '' === $selected_color ) {
                    $source_id = $variation_id > 0 ? $variation_id : $product_id;
                    if ( $source_id > 0 ) {
                        if ( isset( $meta_color_cache[ $source_id ] ) ) {
                            $selected_color = $meta_color_cache[ $source_id ];
                        } else {
                            $meta_color = trim( (string) get_post_meta( $source_id, '_shk_color', true ) );
                            $meta_color_cache[ $source_id ] = $meta_color;
                            $selected_color = $meta_color;
                        }
                    }
                }
                $color_label = $selected_color;
            }

            if ( '' === $color_hex ) {
                $hex_key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $selected_color ) : strtolower( $selected_color );
                if ( '' !== $hex_key ) {
                    if ( isset( $hex_cache[ $hex_key ] ) ) {
                        $color_hex = $hex_cache[ $hex_key ];
                    } elseif ( function_exists( 'shikkosa_resolve_color_local' ) ) {
                        $color_hex = trim( (string) shikkosa_resolve_color_local( $selected_color, $selected_color ) );
                        $hex_cache[ $hex_key ] = $color_hex;
                    }
                }
            }

            if ( '' !== $selected_color ) {
                $cart_item['_shk_selected_color'] = $selected_color;
            }
            if ( '' !== $color_label ) {
                $cart_item['_shk_selected_color_label'] = $color_label;
            }
            if ( '' !== $color_hex ) {
                $cart_item['_shk_selected_color_hex'] = $color_hex;
            }

            $new_selected_color = isset( $cart_item['_shk_selected_color'] ) ? (string) $cart_item['_shk_selected_color'] : '';
            $new_color_label = isset( $cart_item['_shk_selected_color_label'] ) ? (string) $cart_item['_shk_selected_color_label'] : '';
            $new_color_hex = isset( $cart_item['_shk_selected_color_hex'] ) ? (string) $cart_item['_shk_selected_color_hex'] : '';

            if (
                $new_selected_color !== $original_selected_color ||
                $new_color_label !== $original_color_label ||
                $new_color_hex !== $original_color_hex
            ) {
                $cart->cart_contents[ $cart_item_key ] = $cart_item;
                $updated = true;
            }
        }

        if ( $updated && method_exists( $cart, 'set_session' ) ) {
            $cart->set_session();
        }
    },
    20
);

function shikkosa_apply_effective_sku_to_cart_item_local( $cart_item ) {
    if ( ! is_array( $cart_item ) ) {
        return $cart_item;
    }
    if ( empty( $cart_item['_shk_effective_sku'] ) || empty( $cart_item['data'] ) ) {
        return $cart_item;
    }

    $effective_sku = trim( (string) $cart_item['_shk_effective_sku'] );
    if ( '' === $effective_sku ) {
        return $cart_item;
    }

    if ( is_object( $cart_item['data'] ) && method_exists( $cart_item['data'], 'set_sku' ) ) {
        $cart_item['data']->set_sku( $effective_sku );
    }

    return $cart_item;
}
add_filter( 'woocommerce_add_cart_item', 'shikkosa_apply_effective_sku_to_cart_item_local', 20 );
add_filter( 'woocommerce_get_cart_item_from_session', 'shikkosa_apply_effective_sku_to_cart_item_local', 20 );

add_filter(
    'woocommerce_get_item_data',
    function( $item_data, $cart_item ) {
        static $resolved_color_cache = array();

        $normalized = array();

        $size = '';
        $color = '';
        $color_hex = '';
        $sku = '';

        if ( ! empty( $cart_item['_shk_selected_size'] ) ) {
            $size = trim( (string) $cart_item['_shk_selected_size'] );
        }
        if ( ! empty( $cart_item['_shk_effective_sku'] ) ) {
            $sku = trim( (string) $cart_item['_shk_effective_sku'] );
        }
        if ( ! empty( $cart_item['_shk_selected_color'] ) ) {
            $color = trim( (string) $cart_item['_shk_selected_color'] );
        } elseif ( ! empty( $cart_item['_shk_selected_color_label'] ) ) {
            $color = trim( (string) $cart_item['_shk_selected_color_label'] );
        }
        if ( ! empty( $cart_item['_shk_selected_color_hex'] ) ) {
            $color_hex = trim( (string) $cart_item['_shk_selected_color_hex'] );
        }

        if ( isset( $cart_item['variation'] ) && is_array( $cart_item['variation'] ) ) {
            foreach ( $cart_item['variation'] as $attr_key => $attr_val ) {
                $key = strtolower( (string) $attr_key );
                $val = trim( (string) $attr_val );
                if ( '' === $val ) {
                    continue;
                }

                if ( '' === $size && ( false !== strpos( $key, 'razmer' ) || false !== strpos( $key, 'size' ) ) ) {
                    $size = $val;
                }
                if ( '' === $color && ( false !== strpos( $key, 'czvet' ) || false !== strpos( $key, 'cvet' ) || false !== strpos( $key, 'color' ) || false !== strpos( $key, 'colour' ) ) ) {
                    $color = $val;
                }
            }
        }

        foreach ( (array) $item_data as $row ) {
            if ( ! is_array( $row ) || empty( $row['name'] ) || empty( $row['value'] ) ) {
                continue;
            }
            $name = trim( wp_strip_all_tags( (string) $row['name'] ) );
            $value = trim( wp_strip_all_tags( (string) $row['value'] ) );
            if ( '' === $value ) {
                continue;
            }

            if ( '' === $size && in_array( mb_strtolower( $name ), array( 'размер', 'size' ), true ) ) {
                $size = $value;
                continue;
            }
            if ( '' === $color && in_array( mb_strtolower( $name ), array( 'цвет', 'color', 'colour' ), true ) ) {
                $color = $value;
                continue;
            }
            if ( '' === $sku && 'артикул' === mb_strtolower( $name ) ) {
                $sku = $value;
                continue;
            }
        }

        if ( '' !== $size ) {
            $normalized[] = array(
                'name'  => 'Размер',
                'value' => $size,
            );
        }

        if ( '' !== $color ) {
            $resolved_color = $color_hex;
            $resolved_cache_key = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $color ) ) : strtolower( trim( (string) $color ) );
            if ( '' === $resolved_color ) {
                if ( isset( $resolved_color_cache[ $resolved_cache_key ] ) ) {
                    $resolved_color = $resolved_color_cache[ $resolved_cache_key ];
                } elseif ( function_exists( 'shikkosa_resolve_color_local' ) ) {
                    $resolved_color = trim( (string) shikkosa_resolve_color_local( $color, $color ) );
                    $resolved_color_cache[ $resolved_cache_key ] = $resolved_color;
                }
            }

            $color_display = esc_html( $color );
            if ( '' !== $resolved_color ) {
                $color_display = '<span class="shk-mini-cart-color-value"><span class="shk-mini-cart-color-dot" style="background:' . esc_attr( $resolved_color ) . ';"></span><span class="shk-mini-cart-color-name">' . esc_html( $color ) . '</span></span>';
            }

            $normalized[] = array(
                'name'    => 'Цвет',
                'value'   => $color,
                'display' => $color_display,
            );
        }

        if ( '' !== $sku ) {
            $normalized[] = array(
                'name'  => 'Артикул',
                'value' => $sku,
            );
        }

        return ! empty( $normalized ) ? $normalized : $item_data;
    },
    20,
    2
);

add_filter(
    'woocommerce_widget_cart_item_quantity',
    function( $html, $cart_item, $cart_item_key ) {
        $product_id = isset( $cart_item['product_id'] ) ? (int) $cart_item['product_id'] : 0;
        $edit_url = $product_id ? get_permalink( $product_id ) : wc_get_cart_url();
        $remove_url = wc_get_cart_remove_url( $cart_item_key );
        $product_sku = '';
        if ( isset( $cart_item['data'] ) && is_object( $cart_item['data'] ) && method_exists( $cart_item['data'], 'get_sku' ) ) {
            $product_sku = (string) $cart_item['data']->get_sku();
        }

        $actions  = '<div class="shk-mini-cart-actions">';
        $actions .= '<a class="shk-mini-cart-edit" href="' . esc_url( $edit_url ) . '">Редактировать</a>';
        $actions .= '<a class="shk-mini-cart-remove elementor_remove_from_cart_button remove_from_cart_button" href="' . esc_url( $remove_url ) . '" aria-label="' . esc_attr__( 'Remove this item', 'woocommerce' ) . '" data-product_id="' . esc_attr( $product_id ) . '" data-cart_item_key="' . esc_attr( $cart_item_key ) . '" data-product_sku="' . esc_attr( $product_sku ) . '">Удалить</a>';
        $actions .= '</div>';

        return $html . $actions;
    },
    20,
    3
);

add_action(
    'woocommerce_checkout_create_order_line_item',
    function( $item, $cart_item_key, $values ) {
        $effective_sku = isset( $values['_shk_effective_sku'] ) ? trim( (string) $values['_shk_effective_sku'] ) : '';
        $selected_size = isset( $values['_shk_selected_size'] ) ? trim( (string) $values['_shk_selected_size'] ) : '';
        $base_sku      = isset( $values['_shk_base_sku'] ) ? trim( (string) $values['_shk_base_sku'] ) : '';

        if ( '' !== $effective_sku ) {
            $item->add_meta_data( 'Артикул', $effective_sku, true );
            $item->add_meta_data( '_shk_effective_sku', $effective_sku, true );
        }
        if ( '' !== $selected_size ) {
            $item->add_meta_data( '_shk_selected_size', $selected_size, true );
        }
        if ( '' !== $base_sku ) {
            $item->add_meta_data( '_shk_base_sku', $base_sku, true );
        }
    },
    20,
    3
);

add_action('template_redirect', function () {
    if ( ! is_product() || ! function_exists( 'wc_get_notices' ) || ! function_exists( 'wc_set_notices' ) ) {
        return;
    }

    $notices = wc_get_notices();
    if ( empty( $notices['success'] ) ) {
        return;
    }

    unset( $notices['success'] );
    wc_set_notices( $notices );
}, 1);

add_action('wp_footer', function(){
    if ( ! is_product() ) {
        return;
    }
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
      var root = document.querySelector('.shk-inline-attrs');
      if (!root) return;

      var variationForm = document.querySelector('form.variations_form');
      var fallbackBtn = root.querySelector('.shk-add-to-cart-fallback');
      var productId = root.getAttribute('data-product-id');
      var productType = (root.getAttribute('data-product-type') || '').toLowerCase();
      var productPurchasable = root.getAttribute('data-product-purchasable') !== '0';
      var baseSku = String(root.getAttribute('data-base-sku') || '').trim();
      var skuTargets = [];
      var variationsData = [];
      var jsonEl = root.querySelector('.shk-variations-json');
      if (jsonEl) {
        try {
          variationsData = JSON.parse(jsonEl.textContent || '[]');
        } catch (e) {
          variationsData = [];
        }
      }

      function normalizeAttrName(name) {
        return String(name || '').replace(/^attribute_/, '').trim().toLowerCase();
      }

      function attrNamesEqual(a, b) {
        return normalizeAttrName(a) === normalizeAttrName(b);
      }

      function setRowActive(attrName, value) {
        root.querySelectorAll('.attribute-row[data-attribute-name]').forEach(function (row) {
          var rowAttr = row.getAttribute('data-attribute-name');
          if (!attrNamesEqual(rowAttr, attrName)) return;

          row.querySelectorAll('.shk-attr-option').forEach(function (el) {
            el.classList.toggle('is-active', norm(el.getAttribute('data-value')) === norm(value));
          });
        });
      }

      function collectSelected() {
        var selected = {};
        root.querySelectorAll('.attribute-row[data-attribute-name]').forEach(function(row){
          var hasOptionButtons = !!row.querySelector('.shk-attr-option');
          if (!hasOptionButtons) return;
          var attrName = row.getAttribute('data-attribute-name');
          var active = row.querySelector('.shk-attr-option.is-active');
          if (attrName && active) {
            selected[attrName] = active.getAttribute('data-value');
          }
        });
        return selected;
      }

      function getSelectedSizeValue(selected) {
        var out = '';
        Object.keys(selected || {}).some(function(attrName){
          var k = normalizeAttrName(attrName);
          if (k.indexOf('razmer') !== -1 || k.indexOf('size') !== -1) {
            out = String(selected[attrName] || '').trim();
            return true;
          }
          return false;
        });
        return out;
      }

      function escapeRegExp(str) {
        return String(str || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
      }

      function buildEffectiveSku(base, sizeValue) {
        var baseSkuVal = String(base || '').trim();
        var size = String(sizeValue || '').trim();
        if (!baseSkuVal) return '';
        if (!size) return baseSkuVal;
        var sizeNorm = size.toUpperCase();
        var baseTail = baseSkuVal.replace(/[-_\\s/]+$/, '').toUpperCase();
        var suffixPattern = new RegExp('(?:^|[-_/])' + sizeNorm.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\$&') + '$');
        if (baseTail && suffixPattern.test(baseTail)) return baseSkuVal;
        return /[-_/]$/.test(baseSkuVal) ? (baseSkuVal + sizeNorm) : (baseSkuVal + '-' + sizeNorm);
      }

      function collectSkuTargets() {
        var summary = document.querySelector('.single-product .summary') || document.querySelector('.product .summary');
        if (!summary) return { direct: [], textNodes: [] };
        var direct = Array.prototype.slice.call(summary.querySelectorAll('.sku, [data-shk-sku-live], .shk-sku-live'));
        var textNodes = [];
        if (baseSku) {
          var walker = document.createTreeWalker(summary, NodeFilter.SHOW_TEXT, null);
          var node;
          while ((node = walker.nextNode())) {
            var raw = String(node.nodeValue || '');
            if (!raw || raw.indexOf(baseSku) === -1) continue;
            textNodes.push({
              node: node,
              original: raw
            });
          }
        }
        return {
          direct: Array.from(new Set(direct)),
          textNodes: textNodes
        };
      }

      function refreshDisplayedSku(selected) {
        if (!baseSku) return;
        // Always update dedicated live-sku nodes first (most reliable path).
        var effective = buildEffectiveSku(baseSku, getSelectedSizeValue(selected || {}));
        if (!effective) return;
        document.querySelectorAll('.shk-live-sku').forEach(function(el){
          el.textContent = effective;
        });

        if (!skuTargets.length || !skuTargets.direct || !skuTargets.textNodes) {
          skuTargets = collectSkuTargets();
        }
        if ((!skuTargets.direct || !skuTargets.direct.length) && (!skuTargets.textNodes || !skuTargets.textNodes.length)) return;
        (skuTargets.direct || []).forEach(function(el){
          if (!el) return;
          el.textContent = effective;
        });
        (skuTargets.textNodes || []).forEach(function(entry){
          if (!entry || !entry.node) return;
          var source = String(entry.original || '');
          var next = source.replace(new RegExp(escapeRegExp(baseSku), 'g'), effective);
          entry.node.nodeValue = next;
        });
      }

      function allSelected(selected) {
        var rowsCount = 0;
        root.querySelectorAll('.attribute-row[data-attribute-name]').forEach(function(row){
          if (row.querySelector('.shk-attr-option')) {
            rowsCount++;
          }
        });
        return Object.keys(selected || {}).length === rowsCount;
      }

      function norm(v) {
        return String(v || '').trim().toLowerCase();
      }

      var hasMatchingVariationSelect = false;
      if (variationForm) {
        root.querySelectorAll('.attribute-row[data-attribute-name]').forEach(function(row){
          var attrName = row.getAttribute('data-attribute-name');
          if (!attrName) return;
          if (variationForm.querySelector('select[name="' + attrName + '"]')) {
            hasMatchingVariationSelect = true;
          }
        });
      }

      var hasStructuredVariationAttrs = variationsData.some(function(v){
        if (!v || !v.attributes) return false;
        return Object.keys(v.attributes).some(function(key){
          return String(v.attributes[key] || '').trim() !== '';
        });
      });

      var nativeFormHasStructuredAttrs = false;
      if (variationForm) {
        var rawVariationJson = variationForm.getAttribute('data-product_variations') || '';
        if (rawVariationJson) {
          try {
            var nativeVariations = JSON.parse(rawVariationJson);
            if (Array.isArray(nativeVariations)) {
              nativeFormHasStructuredAttrs = nativeVariations.some(function(v){
                if (!v || !v.attributes) return false;
                return Object.keys(v.attributes).some(function(key){
                  return String(v.attributes[key] || '').trim() !== '';
                });
              });
            }
          } catch (e) {
            nativeFormHasStructuredAttrs = false;
          }
        }
      }

      var allowNativeVariationForm = !!(variationForm && hasMatchingVariationSelect);
      if (allowNativeVariationForm && productType === 'variable' && !hasStructuredVariationAttrs && !nativeFormHasStructuredAttrs) {
        // Broken donor-imported variable payload: native Woo variation JS marks
        // options as unavailable. Keep our fallback logic as source of truth.
        allowNativeVariationForm = false;
      }

      if (allowNativeVariationForm) {
        function syncDisabledStatesFromSelects() {
          root.querySelectorAll('.attribute-row').forEach(function(row){
            var attrName = row.getAttribute('data-attribute-name');
            if (!attrName) return;

            var select = variationForm.querySelector('select[name="' + attrName + '"]');
            if (!select) return;

            var optionState = {};
            Array.prototype.slice.call(select.options || []).forEach(function(opt){
              if (!opt || !String(opt.value || '').length) return;
              optionState[norm(opt.value)] = !opt.disabled;
            });

            var buttons = Array.prototype.slice.call(row.querySelectorAll('.shk-attr-option'));
            var hasEnabled = false;
            buttons.forEach(function(btn){
              var key = norm(btn.getAttribute('data-value'));
              var enabled = Object.prototype.hasOwnProperty.call(optionState, key) ? !!optionState[key] : true;
              btn.disabled = !enabled;
              btn.classList.toggle('disabled', !enabled);
              if (enabled) hasEnabled = true;
            });

            if (!hasEnabled) {
              buttons.forEach(function(btn){
                btn.disabled = false;
                btn.classList.remove('disabled');
              });
            }
          });
        }

        function syncActiveStatesFromSelects() {
          root.querySelectorAll('.attribute-row').forEach(function(row){
            var attrName = row.getAttribute('data-attribute-name');
            if (!attrName) return;
            var select = variationForm.querySelector('select[name="' + attrName + '"]');
            var currentVal = select ? select.value : '';
            setRowActive(attrName, currentVal);
          });
          syncDisabledStatesFromSelects();
          refreshFallbackButton();
          refreshDisplayedSku(collectSelected());
        }

        root.addEventListener('click', function (e) {
          var btn = e.target.closest('.shk-attr-option');
          if (!btn) return;
          if (btn.disabled) return;

          var attrName = btn.getAttribute('data-attribute-name');
          var value = btn.getAttribute('data-value');
          if (!attrName) return;

          var select = variationForm.querySelector('select[name="' + attrName + '"]');
          if (!select) return;

          select.value = value;
          select.dispatchEvent(new Event('change', { bubbles: true }));
          syncActiveStatesFromSelects();
        });

        variationForm.addEventListener('change', syncActiveStatesFromSelects);
        if (window.jQuery) {
          window.jQuery(variationForm).on('woocommerce_variation_has_changed found_variation reset_data', syncActiveStatesFromSelects);
        }
        syncActiveStatesFromSelects();
        refreshDisplayedSku(collectSelected());
        return;
      }

      // Fallback mode is expected for imported layouts where native Woo form is absent.

      function unlockAllOptionsInFallback() {
        root.querySelectorAll('.attribute-row[data-attribute-name] .shk-attr-option').forEach(function(el){
          el.disabled = false;
          el.classList.remove('disabled');
          el.setAttribute('aria-disabled', 'false');
        });
      }

      var selectedAttrsFromServer = {};
      var selectedJsonEl = root.querySelector('.shk-selected-attrs-json');
      if (selectedJsonEl) {
        try {
          selectedAttrsFromServer = JSON.parse(selectedJsonEl.textContent || '{}');
        } catch (e) {
          selectedAttrsFromServer = {};
        }
      }

      var simpleAvailabilityMap = {};
      var simpleAvailabilityEl = root.querySelector('.shk-simple-availability-json');
      if (simpleAvailabilityEl) {
        try {
          simpleAvailabilityMap = JSON.parse(simpleAvailabilityEl.textContent || '{}') || {};
        } catch (e) {
          simpleAvailabilityMap = {};
        }
      }

      function reapplySimpleAvailabilityFromMap() {
        if (productType !== 'simple') return;
        root.querySelectorAll('.attribute-row[data-attribute-name] .shk-attr-option').forEach(function(el){
          var attrName = String(el.getAttribute('data-attribute-name') || '');
          var value = String(el.getAttribute('data-value') || '');
          var key = attrName + '::' + value;
          var allowed = Object.prototype.hasOwnProperty.call(simpleAvailabilityMap, key) ? !!simpleAvailabilityMap[key] : true;
          el.disabled = !allowed;
          el.classList.toggle('disabled', !allowed);
          el.setAttribute('aria-disabled', allowed ? 'false' : 'true');
        });

        // Color-family links on simple products must stay clickable.
        root.querySelectorAll('.attribute-row[data-attribute-name=\"shk_color_family\"] .shk-color-link').forEach(function(link){
          link.classList.remove('disabled');
          link.setAttribute('aria-disabled', 'false');
          link.style.pointerEvents = 'auto';
        });
      }

      function findVariation(selected) {
        function optionMatches(expected, selectedValue) {
          var a = String(expected || '').trim();
          var b = String(selectedValue || '').trim();
          if (!a || !b) return false;
          if (norm(a) === norm(b)) return true;
          if (norm(a).replace(/-/g, ' ') === norm(b).replace(/-/g, ' ')) return true;

          var as = norm(a).replace(/[^a-z0-9а-яё]+/gi, '-').replace(/^-+|-+$/g, '');
          var bs = norm(b).replace(/[^a-z0-9а-яё]+/gi, '-').replace(/^-+|-+$/g, '');
          if (as && bs && as === bs) return true;

          return false;
        }

        function findSelectedValueByAttr(attrName) {
          var normalized = normalizeAttrName(attrName);
          var found = '';

          Object.keys(selected || {}).some(function(k){
            if (normalizeAttrName(k) === normalized) {
              found = selected[k];
              return true;
            }
            return false;
          });

          return found;
        }

        return variationsData.find(function(v){
          if (!v || !v.attributes || !v.variation_id || v.is_in_stock === false) return false;

          for (var attrKey in v.attributes) {
            if (!Object.prototype.hasOwnProperty.call(v.attributes, attrKey)) continue;

            var expected = v.attributes[attrKey];

            if (String(expected || '') === '') {
              continue;
            }

            var selectedValue = findSelectedValueByAttr(attrKey);
            if (String(selectedValue || '') === '') {
              return false;
            }

            if (!optionMatches(expected, selectedValue)) {
              return false;
            }
          }

          return true;
        });
      }

      function refreshFallbackButton() {
        if (!fallbackBtn) return;

        if (productType === 'simple') {
          fallbackBtn.disabled = !productId || !productPurchasable;
          fallbackBtn.textContent = fallbackBtn.disabled ? 'Нет в наличии' : 'Добавить в корзину';
          refreshDisplayedSku(collectSelected());
          return;
        }

        var selected = collectSelected();
        var complete = allSelected(selected);
        var variation = complete ? findVariation(selected) : null;

        if (!complete) {
          fallbackBtn.disabled = true;
          fallbackBtn.removeAttribute('data-variation-id');
          fallbackBtn.textContent = 'Выберите параметры';
          refreshDisplayedSku(selected);
          return;
        }

        if (variation) {
          fallbackBtn.disabled = false;
          fallbackBtn.setAttribute('data-variation-id', String(variation.variation_id));
          fallbackBtn.textContent = 'Добавить в корзину';
          refreshDisplayedSku(selected);
        } else {
          fallbackBtn.disabled = true;
          fallbackBtn.removeAttribute('data-variation-id');
          fallbackBtn.textContent = 'Нет такой вариации';
          refreshDisplayedSku(selected);
        }
      }

      function ensureNotAllDisabled() {
        root.querySelectorAll('.attribute-row[data-attribute-name]').forEach(function(row){
          var options = Array.prototype.slice.call(row.querySelectorAll('.shk-attr-option'));
          if (!options.length) return;
          var enabled = options.some(function(el){ return !el.disabled && !el.classList.contains('disabled'); });
          if (!enabled) {
            options.forEach(function(el){
              el.disabled = false;
              el.classList.remove('disabled');
            });
          }
        });
      }

      unlockAllOptionsInFallback();
      root.addEventListener('click', function (e) {
        var btn = e.target.closest('.shk-attr-option');
        if (!btn) return;
        if (btn.disabled) return;
        e.preventDefault();

        var attrName = btn.getAttribute('data-attribute-name');
        var value = btn.getAttribute('data-value');
        if (!attrName) return;

        setRowActive(attrName, value);
        if (productType !== 'simple') {
          ensureNotAllDisabled();
        } else {
          reapplySimpleAvailabilityFromMap();
        }
        refreshFallbackButton();

        var isColorAttr = /color|czvet/i.test(attrName);
        if (isColorAttr) {
          var params = new URLSearchParams(window.location.search);
          params.set(attrName, value);
          var newUrl = window.location.pathname + '?' + params.toString();
          if (window.history && window.history.replaceState) {
            window.history.replaceState({}, '', newUrl);
          }
        }
      });

      function applyInitialSelection() {
        Object.keys(selectedAttrsFromServer || {}).forEach(function(attrName){
          setRowActive(attrName, selectedAttrsFromServer[attrName]);
        });
      }

      function autoSelectSingleOptions() {
        root.querySelectorAll('.attribute-row[data-attribute-name]').forEach(function(row){
          var active = row.querySelector('.shk-attr-option.is-active');
          if (active) return;

          var options = row.querySelectorAll('.shk-attr-option');
          if (options.length === 1) {
            options[0].classList.add('is-active');
          }
        });
      }

      function showAddedToast(message) {
        var existing = document.querySelector('.shk-added-toast');
        if (existing) existing.remove();

        var toast = document.createElement('div');
        toast.className = 'shk-added-toast';
        toast.innerHTML = '<span class="shk-added-toast__text">' + (message || 'Товар добавлен в корзину') + '</span><button type="button" class="shk-added-toast__close" aria-label="Закрыть">×</button>';
        document.body.appendChild(toast);

        var removeToast = function(){
          toast.classList.remove('is-visible');
          setTimeout(function(){ if (toast && toast.parentNode) toast.parentNode.removeChild(toast); }, 220);
        };

        requestAnimationFrame(function(){ toast.classList.add('is-visible'); });
        toast.querySelector('.shk-added-toast__close').addEventListener('click', removeToast);
        setTimeout(removeToast, 4000);
      }

      function getAjaxErrorMessage(result, fallback) {
        var msg = '';
        if (result && result.data && typeof result.data.message === 'string') {
          msg = result.data.message;
        } else if (result && typeof result.message === 'string') {
          msg = result.message;
        }
        msg = String(msg || '').trim();
        var lower = msg.toLowerCase();
        if (lower.indexOf('validation failed') !== -1) return 'Не удалось добавить товар в корзину. Проверьте выбранные параметры.';
        if (lower.indexOf('add to cart failed') !== -1 || lower.indexOf('failed add to cart') !== -1) return 'Не удалось добавить товар в корзину.';
        if (lower.indexOf('please choose') !== -1) return 'Пожалуйста, выберите параметры товара.';
        if (lower.indexOf('out of stock') !== -1) return 'Товара нет в наличии.';
        return msg || (fallback || 'Не удалось добавить товар');
      }

      function ensureToastStyles() {
        if (document.getElementById('shk-added-toast-styles')) return;
        var st = document.createElement('style');
        st.id = 'shk-added-toast-styles';
        st.textContent = '.shk-added-toast{position:fixed;left:16px;right:16px;bottom:16px;z-index:99999;background:var(--e-global-color-primary,#111);color:#fff;padding:12px 14px;border-radius:10px;display:flex;align-items:center;justify-content:space-between;gap:12px;opacity:0;transform:translateY(12px);transition:opacity .2s ease,transform .2s ease}.shk-added-toast.is-visible{opacity:1;transform:translateY(0)}.shk-added-toast__close{background:transparent;border:0;color:#fff;font-size:22px;line-height:1;cursor:pointer;padding:0 0 2px 0}.shk-added-toast__text{font-size:14px;line-height:1.3}';
        document.head.appendChild(st);
      }

      var shkAjaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;

      function postShkAddToCart(payload) {
        var fd = new FormData();
        fd.append('action', 'shk_add_to_cart');
        fd.append('product_id', String(payload.product_id || ''));
        fd.append('quantity', String(payload.quantity || 1));

        if (payload.variation_id) {
          fd.append('variation_id', String(payload.variation_id));
        }

        if (payload.attributes && typeof payload.attributes === 'object') {
          fd.append('attributes', JSON.stringify(payload.attributes));
        }
        if (payload.selected_size) {
          fd.append('selected_size', String(payload.selected_size));
        }

        return fetch(shkAjaxUrl, {
          method: 'POST',
          credentials: 'same-origin',
          body: fd
        })
        .then(function(res){ return res.json(); });
      }

      function triggerWooFragmentsRefresh() {
        if (window.jQuery) {
          window.jQuery(document.body).trigger('wc_fragment_refresh');
        }
      }

      if (fallbackBtn) {
        ensureToastStyles();
        fallbackBtn.style.display = 'inline-flex';
        fallbackBtn.addEventListener('click', function(){
          var selected = collectSelected();
          var selectedSize = getSelectedSizeValue(selected);
          var attrsPayload = {};
          Object.keys(selected || {}).forEach(function(key){
            var val = selected[key];
            if (val === null || typeof val === 'undefined' || String(val) === '') return;
            var normalizedKey = String(key).indexOf('attribute_') === 0 ? String(key) : ('attribute_' + String(key));
            attrsPayload[normalizedKey] = String(val);
          });

          if (productType === 'simple') {
            if (!productId || !productPurchasable) {
              showAddedToast('Не удалось добавить товар');
              return;
            }

            fallbackBtn.disabled = true;
            postShkAddToCart({
              product_id: productId,
              quantity: 1,
              attributes: attrsPayload,
              selected_size: selectedSize
            })
            .then(function(result){
              if (result && result.success) {
                showAddedToast('Товар добавлен в корзину');
                triggerWooFragmentsRefresh();
              } else {
                showAddedToast(getAjaxErrorMessage(result, 'Не удалось добавить товар'));
                console.warn('shikkosa simple add_to_cart error', result);
              }
            })
            .catch(function(err){
              showAddedToast('Не удалось добавить товар');
              console.warn('shikkosa simple add_to_cart exception', err);
            })
            .finally(function(){
              fallbackBtn.disabled = false;
              refreshFallbackButton();
            });

            return;
          }

          var variationId = fallbackBtn.getAttribute('data-variation-id');
          if (!variationId || !productId) {
            showAddedToast('Выберите параметры');
            return;
          }

          var matchedVariation = findVariation(selected);
          if (!matchedVariation || !matchedVariation.variation_id) {
            showAddedToast('Не удалось определить вариацию');
            return;
          }

          fallbackBtn.disabled = true;
          postShkAddToCart({
            product_id: productId,
            quantity: 1,
            variation_id: matchedVariation.variation_id,
            attributes: attrsPayload,
            selected_size: selectedSize
          })
          .then(function(result){
            if (result && result.success) {
              showAddedToast('Товар добавлен в корзину');
              triggerWooFragmentsRefresh();
            } else {
              showAddedToast(getAjaxErrorMessage(result, 'Не удалось добавить товар'));
              console.warn('shikkosa add_to_cart error', result, {
                selected: selected,
                matchedVariation: matchedVariation,
                attributes: attrsPayload
              });
            }
          })
          .catch(function(err){
            showAddedToast('Не удалось добавить товар');
            console.warn('shikkosa add_to_cart exception', err);
          })
          .finally(function(){
            fallbackBtn.disabled = false;
            refreshFallbackButton();
          });
        });
      }

      applyInitialSelection();
      if (productType !== 'simple') {
        unlockAllOptionsInFallback();
      }
      if (productType !== 'simple') {
        ensureNotAllDisabled();
      } else {
        reapplySimpleAvailabilityFromMap();
      }
      autoSelectSingleOptions();
      refreshFallbackButton();
      refreshDisplayedSku(collectSelected());

      if (productType === 'simple' && window.MutationObserver) {
        var simpleLock = false;
        var simpleObserver = new MutationObserver(function(){
          if (simpleLock) return;
          simpleLock = true;
          reapplySimpleAvailabilityFromMap();
          setTimeout(function(){ simpleLock = false; }, 0);
        });
        simpleObserver.observe(root, {
          subtree: true,
          attributes: true,
          attributeFilter: ['class', 'disabled']
        });
      }

      if (productType !== 'simple' && window.MutationObserver) {
        var varLock = false;
        var varObserver = new MutationObserver(function(){
          if (varLock) return;
          varLock = true;
          root.querySelectorAll('.attribute-row[data-attribute-name] .shk-attr-option').forEach(function(el){
            if (!el.disabled) {
              el.classList.remove('disabled');
              el.setAttribute('aria-disabled', 'false');
            }
          });
          setTimeout(function(){ varLock = false; }, 0);
        });
        varObserver.observe(root, {
          subtree: true,
          attributes: true,
          attributeFilter: ['class', 'disabled']
        });
      }

      document.querySelectorAll('.woocommerce-notices-wrapper, .woocommerce-message, .woocommerce-error, .woocommerce-info').forEach(function(el){
        el.style.display = 'none';
      });
    });
    </script>
    <?php
}, 110);

add_filter(
    'woocommerce_related_products',
    function( $related_posts, $product_id, $args ) {
        $product_id = (int) $product_id;
        if ( $product_id <= 0 ) {
            return $related_posts;
        }

        $ids = shikkosa_collect_related_ids_local( $product_id );
        if ( empty( $ids ) ) {
            return $related_posts;
        }

        $limit = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : 4;
        if ( $limit > 0 ) {
            $ids = array_slice( $ids, 0, $limit );
        }

        return $ids;
    },
    20,
    3
);
