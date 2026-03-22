<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function shikkosa_parse_pipe_values( $raw ) {
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

function shikkosa_color_taxonomies() {
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

    $taxes = array_values( array_unique( array_filter( array_map( 'sanitize_key', $taxes ) ) ) );
    return $taxes;
}

function shikkosa_normalize_color_slug( $value ) {
    return sanitize_title( (string) $value );
}

function shikkosa_normalize_size_value( $value ) {
    return strtoupper( trim( (string) $value ) );
}

function shikkosa_find_color_slug_by_name( $value, $taxonomies ) {
    $value = trim( (string) $value );
    if ( '' === $value ) {
        return '';
    }

    foreach ( (array) $taxonomies as $tax ) {
        if ( ! taxonomy_exists( $tax ) ) {
            continue;
        }

        $term = get_term_by( 'name', $value, $tax );
        if ( $term && ! is_wp_error( $term ) && ! empty( $term->slug ) ) {
            return (string) $term->slug;
        }
    }

    return '';
}

function shikkosa_color_palette_map() {
    return array(
        'black'         => '#111111',
        'chernyj'       => '#111111',
        'чёрный'        => '#111111',
        'черный'        => '#111111',
        'white'         => '#f5f5f5',
        'belyj'         => '#f5f5f5',
        'белый'         => '#f5f5f5',
        'pink'          => '#f2b8c6',
        'rozovyj'       => '#f2b8c6',
        'розовый'       => '#f2b8c6',
        'red'           => '#b3263f',
        'krasnyj'       => '#b3263f',
        'красный'       => '#b3263f',
        'yagodno-krasnyj' => '#a3122f',
        'blue'          => '#2e4d8f',
        'sinij'         => '#2e4d8f',
        'синий'         => '#2e4d8f',
        'mint'          => '#9ccdb3',
        'myatnyj'       => '#9ccdb3',
        'мятный'        => '#9ccdb3',
        'yellow'        => '#e4c052',
        'zheltyj'       => '#e4c052',
        'жёлтый'        => '#e4c052',
        'zoloto'        => '#c8a24a',
        'gold'          => '#c8a24a',
        'lilovyj'       => '#8c78ad',
        'лиловый'       => '#8c78ad',
        'purple'        => '#8c78ad',
        'violet'        => '#8c78ad',
        'chocolate'     => '#5a3d2e',
        'shokolad'      => '#5a3d2e',
        'шоколад'       => '#5a3d2e',
        'vino'          => '#5d2230',
        'wine'          => '#5d2230',
        'kapuchino'     => '#a07f67',
        'капучино'      => '#a07f67',
        'beige'         => '#d7c1a3',
        'bezhevyj'      => '#d7c1a3',
        'бежевый'       => '#d7c1a3',
        'ivory'         => '#efe8dc',
        'zhemchuzhnyj'  => '#efe8dc',
        'жемчужный'     => '#efe8dc',
        'papaya'        => '#f3ad7a',
        'papajya'       => '#f3ad7a',
        'папайя'        => '#f3ad7a',
        'chernichnyj'   => '#2f3f73',
        'черничный'     => '#2f3f73',
        'magenta'       => '#c2185b',
        'madzhenta'     => '#c2185b',
        'маджента'      => '#c2185b',
        'yagodno-krasnyj' => '#b0002a',
        'yagodno_krasnyj' => '#b0002a',
        'ягодно-красный'  => '#b0002a',
        'cherno-sinij'  => '#1d2a44',
        'cherno_sinij'  => '#1d2a44',
        'черно-синий'   => '#1d2a44',
        'chernyj-sinij' => '#1d2a44',
        'cherno-krasnyj'=> '#3a0f18',
        'cherno_krasnyj'=> '#3a0f18',
        'черно-красный' => '#3a0f18',
        'fuksiya'       => '#d1007e',
        'fuchsia'       => '#d1007e',
        'фуксия'        => '#d1007e',
        'oranzhevyj'    => '#f39c34',
        'orange'        => '#f39c34',
        'оранжевый'     => '#f39c34',
        'rubin'         => '#9b1b30',
        'рубин'         => '#9b1b30',
        'pudrovyj'      => '#d8b4b6',
        'пудровый'      => '#d8b4b6',
        'ajvori'        => '#efe8dc',
        'айвори'        => '#efe8dc',
        'goluboj'       => '#7bb8e8',
        'голубой'       => '#7bb8e8',
        'shampan-s-zolotom' => '#d7c19a',
        'shampan_s_zolotom' => '#d7c19a',
        'champagne-with-gold' => '#d7c19a',
        'шампань с золотом' => '#d7c19a',
        'serebro-s-zolotom' => '#b8aa8b',
        'serebro_s_zolotom' => '#b8aa8b',
        'серебро с золотом' => '#b8aa8b',
        'rozovoe-zoloto' => '#b76e79',
        'rozovoe_zoloto' => '#b76e79',
        'rose-gold'      => '#b76e79',
        'розовое золото' => '#b76e79',
        'olivkovyj'      => '#6b7b3a',
        'olive'          => '#6b7b3a',
        'оливковый'      => '#6b7b3a',
        'amarone'        => '#5a1f2a',
        'амароне'        => '#5a1f2a',
        'nebesno-goluboj' => '#8ecff0',
        'nebesno_goluboj' => '#8ecff0',
        'sky-blue'       => '#8ecff0',
        'небесно-голубой'=> '#8ecff0',
        'alyj'           => '#d72638',
        'алый'           => '#d72638',
        'rubinovyj'      => '#9b1b30',
        'рубиновый'      => '#9b1b30',
    );
}

function shikkosa_resolve_color_value( $slug, $name = '' ) {
    $slug = trim( (string) $slug );
    $name = trim( (string) $name );

    $palette = shikkosa_color_palette_map();

    $slug_key = sanitize_title( $slug );
    if ( isset( $palette[ $slug_key ] ) ) {
        return $palette[ $slug_key ];
    }

    $name_key = sanitize_title( $name );
    if ( isset( $palette[ $name_key ] ) ) {
        return $palette[ $name_key ];
    }

    return '';
}

function shikkosa_get_term_color_value( $term ) {
    if ( ! ( $term instanceof WP_Term ) ) {
        return '';
    }

    // Manual Woo term field (works without ACF)
    $color = get_term_meta( $term->term_id, 'shk_color_hex', true );
    if ( is_string( $color ) ) {
        $color = trim( $color );
    }
    if ( $color && preg_match( '/^#[0-9a-fA-F]{6}$/', $color ) ) {
        return $color;
    }

    $color = get_term_meta( $term->term_id, 'color', true );
    if ( ! $color && function_exists( 'get_field' ) ) {
        $color = get_field( 'color', $term );
    }
    if ( ! $color && function_exists( 'get_field' ) ) {
        $color = get_field( 'color', $term->taxonomy . '_' . $term->term_id );
    }
    if ( is_array( $color ) && isset( $color['value'] ) ) {
        $color = $color['value'];
    }

    return is_string( $color ) ? trim( $color ) : '';
}

function shikkosa_product_size_color_data( $product_or_id, $size_taxonomies, $color_taxonomies ) {
    $product = is_a( $product_or_id, 'WC_Product' ) ? $product_or_id : wc_get_product( (int) $product_or_id );
    if ( ! $product ) {
        return array(
            'sizes'       => array(),
            'color_slugs' => array(),
            'colors_map'  => array(),
        );
    }

    $product_id = (int) $product->get_id();
    $sizes_for_product = array();
    $colors_for_product = array();
    $colors_map = array();

    $get_term_name_by_slug = function( $tax, $slug ) {
        $term = get_term_by( 'slug', $slug, $tax );
        return ( $term && ! is_wp_error( $term ) ) ? $term->name : $slug;
    };

    if ( $product->is_type( 'variable' ) ) {
        $var_attrs = $product->get_variation_attributes();
        foreach ( $var_attrs as $attr_tax => $values ) {
            if ( in_array( $attr_tax, $size_taxonomies, true ) ) {
                foreach ( (array) $values as $slug ) {
                    $sizes_for_product[] = $get_term_name_by_slug( $attr_tax, $slug );
                }
            }
            if ( in_array( $attr_tax, $color_taxonomies, true ) ) {
                foreach ( (array) $values as $slug ) {
                    $slug = (string) $slug;
                    if ( '' === $slug ) {
                        continue;
                    }
                    $term = get_term_by( 'slug', $slug, $attr_tax );
                    $colors_for_product[] = $slug;
                    $colors_for_product[] = shikkosa_normalize_color_slug( $slug );
                    $colors_map[ $slug ] = ( $term && ! is_wp_error( $term ) ) ? $term->name : $slug;
                }
            }
        }
    }

    $attrs = $product->get_attributes();
    foreach ( $attrs as $attr ) {
        if ( ! is_a( $attr, 'WC_Product_Attribute' ) ) {
            continue;
        }

        $attr_name = (string) $attr->get_name();
        $attr_name_lc = function_exists( 'mb_strtolower' ) ? mb_strtolower( $attr_name ) : strtolower( $attr_name );
        $is_size_attr = in_array( $attr_name, $size_taxonomies, true ) || false !== strpos( $attr_name_lc, 'размер' ) || false !== strpos( $attr_name_lc, 'size' );
        $is_color_attr = in_array( $attr_name, $color_taxonomies, true ) || false !== strpos( $attr_name_lc, 'цвет' ) || false !== strpos( $attr_name_lc, 'color' ) || false !== strpos( $attr_name_lc, 'czvet' );

        if ( $attr->is_taxonomy() ) {
            if ( $is_size_attr ) {
                $names = wc_get_product_terms( $product_id, $attr_name, array( 'fields' => 'names' ) );
                $sizes_for_product = array_merge( $sizes_for_product, (array) $names );
            }
            if ( $is_color_attr ) {
                $terms = wc_get_product_terms( $product_id, $attr_name, array( 'fields' => 'all' ) );
                if ( ! is_wp_error( $terms ) ) {
                    foreach ( (array) $terms as $term ) {
                        if ( ! $term instanceof WP_Term ) {
                            continue;
                        }
                        $colors_for_product[] = $term->slug;
                        $colors_map[ $term->slug ] = $term->name;
                    }
                }
            }
        } else {
            $options = array_map(
                static function( $value ) {
                    return is_scalar( $value ) ? trim( (string) $value ) : '';
                },
                (array) $attr->get_options()
            );
            $options = array_values( array_filter( $options ) );

            if ( $is_size_attr ) {
                $sizes_for_product = array_merge( $sizes_for_product, $options );
            }
            if ( $is_color_attr ) {
                foreach ( $options as $name ) {
                    $slug = shikkosa_normalize_color_slug( $name );
                    if ( '' === $slug ) {
                        continue;
                    }
                    $tax_slug = shikkosa_find_color_slug_by_name( $name, $color_taxonomies );
                    if ( '' !== $tax_slug ) {
                        $slug = $tax_slug;
                    }
                    $colors_for_product[] = $slug;
                    $colors_for_product[] = shikkosa_normalize_color_slug( $slug );
                    $colors_map[ $slug ] = $name;
                }
            }
        }
    }

    $meta_sizes = shikkosa_parse_pipe_values( get_post_meta( $product_id, '_shk_sizes', true ) );
    if ( ! empty( $meta_sizes ) ) {
        $sizes_for_product = array_merge( $sizes_for_product, $meta_sizes );
    }

    $meta_color = trim( (string) get_post_meta( $product_id, '_shk_color', true ) );
    if ( '' !== $meta_color ) {
        $meta_color_slug = shikkosa_find_color_slug_by_name( $meta_color, $color_taxonomies );
        if ( '' === $meta_color_slug ) {
            $meta_color_slug = shikkosa_normalize_color_slug( $meta_color );
        }
        if ( '' !== $meta_color_slug ) {
            $colors_for_product[] = $meta_color_slug;
            $colors_for_product[] = shikkosa_normalize_color_slug( $meta_color_slug );
            $colors_map[ $meta_color_slug ] = $meta_color;
        }
    }

    $sizes_for_product = array_values( array_unique( array_filter( $sizes_for_product ) ) );
    $colors_for_product = array_values( array_unique( array_filter( $colors_for_product ) ) );

    return array(
        'sizes'       => $sizes_for_product,
        'color_slugs' => $colors_for_product,
        'colors_map'  => $colors_map,
    );
}

function shikkosa_catalog_filters_shortcode() {
    if ( ! function_exists( 'wc_get_page_permalink' ) ) {
        return '';
    }

    $shop_url = wc_get_page_permalink( 'shop' );
    $shop_path = wp_parse_url( $shop_url, PHP_URL_PATH );
    $current_path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
    $current_query = array();
    if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
        parse_str( wp_unslash( $_SERVER['QUERY_STRING'] ), $current_query );
    }

    $categories = get_terms( array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
        'orderby'    => 'menu_order',
        'order'      => 'ASC',
    ) );
    if ( is_wp_error( $categories ) ) {
        $categories = array();
    }

    $size_taxonomies = array( 'pa_razmer', 'pa_size' );
    $color_taxonomies = shikkosa_color_taxonomies();

    $size_terms = array();
    foreach ( $size_taxonomies as $tax ) {
        if ( taxonomy_exists( $tax ) ) {
            $terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => true ) );
            if ( ! is_wp_error( $terms ) ) {
                $size_terms = array_merge( $size_terms, $terms );
            }
        }
    }

    $sizes = array();
    foreach ( $size_terms as $term ) {
        $sizes[ $term->name ] = $term->name;
    }
    $size_labels = array_values( $sizes );

    $size_order = array( 'XS','S','M','L','XL','XXL','S/M','L/XL' );
    usort( $size_labels, function( $a, $b ) use ( $size_order ) {
        $ai = array_search( $a, $size_order, true );
        $bi = array_search( $b, $size_order, true );
        if ( $ai !== false && $bi !== false ) {
            return $ai <=> $bi;
        }
        if ( $ai !== false ) return -1;
        if ( $bi !== false ) return 1;
        return strnatcasecmp( $a, $b );
    } );

    $color_terms = array();
    foreach ( $color_taxonomies as $tax ) {
        if ( taxonomy_exists( $tax ) ) {
            $terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => true ) );
            if ( ! is_wp_error( $terms ) ) {
                $color_terms = array_merge( $color_terms, $terms );
            }
        }
    }

    // De-duplicate color terms by slug
    $color_terms_unique = array();
    foreach ( $color_terms as $term ) {
        $color_terms_unique[ $term->slug ] = $term;
    }
    $color_terms = array_values( $color_terms_unique );

    $color_options = array();
    foreach ( $color_terms as $term ) {
        $color_value = shikkosa_get_term_color_value( $term );
        if ( ! $color_value ) {
            $color_value = shikkosa_resolve_color_value( $term->slug, $term->name );
        }
        $color_options[ $term->slug ] = array(
            'slug'  => $term->slug,
            'name'  => $term->name,
            'color' => is_string( $color_value ) ? trim( $color_value ) : '',
        );
    }

    $current_cat = null;
    if ( is_product_category() ) {
        $current_cat = get_queried_object();
        if ( ! ( $current_cat instanceof WP_Term ) ) {
            $current_cat = null;
        }
    }

    $selected_size = isset( $_GET['size'] ) ? sanitize_text_field( wp_unslash( $_GET['size'] ) ) : '';
    $selected_color = isset( $_GET['color'] ) ? sanitize_text_field( wp_unslash( $_GET['color'] ) ) : '';
    $selected_size_norm = shikkosa_normalize_size_value( $selected_size );
    $selected_color_norm = shikkosa_normalize_color_slug( $selected_color );
    $filter_base_url = ( $current_cat instanceof WP_Term )
        ? get_term_link( $current_cat )
        : $shop_url;
    if ( is_wp_error( $filter_base_url ) || ! is_string( $filter_base_url ) || '' === $filter_base_url ) {
        $filter_base_url = $shop_url;
    }

    $product_query_args = array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'fields'         => 'ids',
        'posts_per_page' => -1,
        'no_found_rows'  => true,
    );
    if ( $current_cat instanceof WP_Term ) {
        $product_query_args['tax_query'] = array(
            array(
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => array( $current_cat->term_id ),
            ),
        );
    }
    $product_ids = get_posts( $product_query_args );

    $available_size_names = array();
    $available_color_slugs = array();

    $all_size_names = array();
    foreach ( $product_ids as $pid ) {
        $data = shikkosa_product_size_color_data( (int) $pid, $size_taxonomies, $color_taxonomies );
        $sizes_for_product = (array) $data['sizes'];
        $colors_for_product = (array) $data['color_slugs'];
        $colors_map = (array) $data['colors_map'];
        foreach ( $sizes_for_product as $sz ) {
            $all_size_names[ $sz ] = true;
        }

        $colors_norm = array_values(
            array_unique(
                array_filter(
                    array_map( 'shikkosa_normalize_color_slug', $colors_for_product )
                )
            )
        );
        $sizes_norm = array_values(
            array_unique(
                array_filter(
                    array_map( 'shikkosa_normalize_size_value', $sizes_for_product )
                )
            )
        );

        if ( '' !== $selected_color && ! in_array( $selected_color_norm, $colors_norm, true ) ) {
            $sizes_for_product = array();
        }
        if ( '' !== $selected_size && ! in_array( $selected_size_norm, $sizes_norm, true ) ) {
            $colors_for_product = array();
        }

        foreach ( $sizes_for_product as $name ) {
            $available_size_names[ $name ] = true;
        }
        foreach ( $colors_for_product as $slug ) {
            $available_color_slugs[ $slug ] = true;
            if ( ! isset( $color_options[ $slug ] ) ) {
                $color_options[ $slug ] = array(
                    'slug'  => $slug,
                    'name'  => isset( $colors_map[ $slug ] ) ? $colors_map[ $slug ] : $slug,
                    'color' => shikkosa_resolve_color_value( $slug, isset( $colors_map[ $slug ] ) ? $colors_map[ $slug ] : $slug ),
                );
            }
        }
    }

    if ( ! empty( $all_size_names ) ) {
        foreach ( array_keys( $all_size_names ) as $size_name ) {
            $sizes[ $size_name ] = $size_name;
        }
        $size_labels = array_values( $sizes );
        usort( $size_labels, function( $a, $b ) use ( $size_order ) {
            $ai = array_search( $a, $size_order, true );
            $bi = array_search( $b, $size_order, true );
            if ( $ai !== false && $bi !== false ) {
                return $ai <=> $bi;
            }
            if ( $ai !== false ) return -1;
            if ( $bi !== false ) return 1;
            return strnatcasecmp( $a, $b );
        } );
    }

    if ( empty( $available_size_names ) && ! empty( $size_terms ) ) {
        foreach ( $size_terms as $term ) {
            $available_size_names[ $term->name ] = true;
        }
    }
    if ( empty( $available_size_names ) && ! empty( $all_size_names ) ) {
        foreach ( array_keys( $all_size_names ) as $size_name ) {
            $available_size_names[ $size_name ] = true;
        }
    }
    if ( empty( $available_color_slugs ) && ! empty( $color_options ) ) {
        foreach ( $color_options as $option ) {
            $available_color_slugs[ $option['slug'] ] = true;
        }
    }

    ob_start();
    ?>
    <div class="catalog__filter">
        <div class="catalog__filter_nav catalog-menu">
            <?php foreach ( $categories as $cat ) :
                $href = get_term_link( $cat );
                if ( is_wp_error( $href ) ) continue;
                $is_active = ( $current_path === wp_parse_url( $href, PHP_URL_PATH ) );
                ?>
                <a class="catalog__filter_link underline <?php echo $is_active ? 'active' : ''; ?>" href="<?php echo esc_url( $href ); ?>"><?php echo esc_html( $cat->name ); ?></a>
            <?php endforeach; ?>
        </div>
        <button class="catalog__filter_btn btn-reboot js-catalog-toggleFilter active" type="button">
            <svg class="icon icon__filter">
                <use xlink:href="/build/icon.svg#filter"></use>
            </svg>
            <span>Фильтры</span>
        </button>
        <div class="catalog__filter_item">
            <div class="catalog__filter_label">Сортировать</div>
            <div class="catalog__filter_nav">
                <?php $base_args = $current_query; $base_args['sort'] = 'desc'; ?>
                <a class="catalog__filter_link underline <?php echo ( isset( $current_query['sort'] ) && $current_query['sort'] === 'desc' ) ? 'active' : ''; ?>" href="<?php echo esc_url( add_query_arg( $base_args, $filter_base_url ) ); ?>">Сначала дороже</a>
                <?php $base_args = $current_query; $base_args['sort'] = 'asc'; ?>
                <a class="catalog__filter_link underline <?php echo ( isset( $current_query['sort'] ) && $current_query['sort'] === 'asc' ) ? 'active' : ''; ?>" href="<?php echo esc_url( add_query_arg( $base_args, $filter_base_url ) ); ?>">Сначала дешевле</a>
            </div>
        </div>
        <div class="catalog__filter_item">
            <div class="catalog__filter_label">Доступный размер</div>
            <div class="catalog__filter_nav">
                <?php foreach ( $size_labels as $size_label ) :
                    $is_active = ( isset( $current_query['size'] ) && $current_query['size'] === $size_label );
                    $is_available = ! empty( $available_size_names[ $size_label ] ) || $is_active;
                    $args = $current_query;
                    $args['size'] = $size_label;
                    $url = add_query_arg( $args, $filter_base_url );
                    ?>
                    <a class="catalog__filter_link underline <?php echo $is_active ? 'active' : ''; ?> <?php echo $is_available ? '' : 'is-disabled'; ?>" href="<?php echo esc_url( $url ); ?>" <?php echo $is_available ? '' : 'aria-disabled="true" tabindex="-1"'; ?>><?php echo esc_html( $size_label ); ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="catalog__filter_item">
            <div class="catalog__filter_label">Цвет</div>
            <div class="catalog__filter_nav">
                <?php foreach ( $color_options as $option ) :
                    $slug = (string) $option['slug'];
                    $name = (string) $option['name'];
                    $color = (string) $option['color'];
                    $style = $color ? 'background-color: ' . esc_attr( $color ) . '; border-color: #000000' : '';
                    $is_active = ( isset( $current_query['color'] ) && $current_query['color'] === $slug );
                    $is_available = ! empty( $available_color_slugs[ $slug ] ) || $is_active;
                    $args = $current_query;
                    $args['color'] = $slug;
                    $url = add_query_arg( $args, $filter_base_url );
                    ?>
                    <a class="catalog__filter_link color-link underline <?php echo $is_active ? 'active' : ''; ?> <?php echo $is_available ? '' : 'is-disabled'; ?>" href="<?php echo esc_url( $url ); ?>" <?php echo $is_available ? '' : 'aria-disabled="true" tabindex="-1"'; ?>>
                        <span class="color-marker" style="<?php echo esc_attr( $style ); ?>"></span>
                        <span class="color-name"><?php echo esc_html( $name ); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'shk_catalog_filters', 'shikkosa_catalog_filters_shortcode' );

function shikkosa_catalog_menu_shortcode() {
    if ( ! function_exists( 'wc_get_page_permalink' ) ) {
        return '';
    }

    $shop_url = wc_get_page_permalink( 'shop' );
    $current_path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';

    $categories = get_terms( array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
        'orderby'    => 'menu_order',
        'order'      => 'ASC',
    ) );

    if ( is_wp_error( $categories ) ) {
        $categories = array();
    }

    ob_start();
    ?>
    <div class="offmenu shk-catalog-menu">
        <ul class="elementor-nav-menu elementor-nav-menu--main shk-catalog-menu__list">
            <?php foreach ( $categories as $cat ) :
                $href = get_term_link( $cat );
                if ( is_wp_error( $href ) ) {
                    continue;
                }
                $is_active = ( $current_path === wp_parse_url( $href, PHP_URL_PATH ) );
                ?>
                <li class="menu-item menu-item-product-cat menu-item-<?php echo (int) $cat->term_id; ?> shk-catalog-menu__item <?php echo $is_active ? 'current-menu-item' : ''; ?>">
                    <a class="elementor-item shk-catalog-menu__link" href="<?php echo esc_url( $href ); ?>"><?php echo esc_html( $cat->name ); ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <script>
    (function(){
        if (window.__shkCatalogMenuAdaptiveInit) {
            return;
        }
        window.__shkCatalogMenuAdaptiveInit = true;

        function applyAdaptiveColumns() {
            var isDesktop = window.matchMedia('(min-width: 1025px)').matches;
            var lists = document.querySelectorAll('.shk-catalog-menu:not(.shk-catalog-menu--footer) .shk-catalog-menu__list');

            lists.forEach(function(list){
                if (!isDesktop) {
                    list.style.removeProperty('--shk-menu-cols');
                    return;
                }

                var cols = 2;
                list.style.setProperty('--shk-menu-cols', String(cols));

                var safety = 0;
                while (list.getBoundingClientRect().height > (window.innerHeight * 0.5) && cols < 6 && safety < 6) {
                    cols += 1;
                    safety += 1;
                    list.style.setProperty('--shk-menu-cols', String(cols));
                }
            });
        }

        window.addEventListener('load', applyAdaptiveColumns);
        window.addEventListener('resize', applyAdaptiveColumns);
        document.addEventListener('DOMContentLoaded', applyAdaptiveColumns);
    })();
    </script>
    <?php

    return ob_get_clean();
}
add_shortcode( 'shk_catalog_menu', 'shikkosa_catalog_menu_shortcode' );

function shikkosa_catalog_menu_footer_shortcode() {
    if ( ! function_exists( 'wc_get_page_permalink' ) ) {
        return '';
    }

    $shop_url = wc_get_page_permalink( 'shop' );
    $current_path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';

    $categories = get_terms( array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
        'orderby'    => 'menu_order',
        'order'      => 'ASC',
    ) );

    if ( is_wp_error( $categories ) ) {
        $categories = array();
    }

    $collection_url = home_url( '/collection/' );
    $collection_path = wp_parse_url( $collection_url, PHP_URL_PATH );
    $collection_inserted = false;

    ob_start();
    ?>
    <div class="shk-catalog-menu shk-catalog-menu--footer">
        <ul class="elementor-nav-menu elementor-nav-menu--main shk-catalog-menu__list shk-catalog-menu__list--footer">
            <?php $cat_index = 0; foreach ( $categories as $cat ) :
                $cat_index++;
                $href = get_term_link( $cat );
                if ( is_wp_error( $href ) ) {
                    continue;
                }
                $is_active = ( $current_path === wp_parse_url( $href, PHP_URL_PATH ) );
                ?>
                <li class="menu-item menu-item-product-cat menu-item-<?php echo (int) $cat->term_id; ?> shk-catalog-menu__item <?php echo $is_active ? 'current-menu-item' : ''; ?>">
                    <a class="elementor-item shk-catalog-menu__link" href="<?php echo esc_url( $href ); ?>"><?php echo esc_html( $cat->name ); ?></a>
                </li>
                <?php if ( ! $collection_inserted && 3 === $cat_index ) :
                    $collection_active = ( $current_path === $collection_path );
                    $collection_inserted = true;
                    ?>
                    <li class="menu-item menu-item-collections shk-catalog-menu__item <?php echo $collection_active ? 'current-menu-item' : ''; ?>">
                        <a class="elementor-item shk-catalog-menu__link" href="<?php echo esc_url( $collection_url ); ?>">Коллекции</a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if ( ! $collection_inserted ) :
                $collection_active = ( $current_path === $collection_path );
                ?>
                <li class="menu-item menu-item-collections shk-catalog-menu__item <?php echo $collection_active ? 'current-menu-item' : ''; ?>">
                    <a class="elementor-item shk-catalog-menu__link" href="<?php echo esc_url( $collection_url ); ?>">Коллекции</a>
                </li>
            <?php endif; ?>
        </ul>
    </div>
    <?php

    return ob_get_clean();
}
add_shortcode( 'shk_catalog_menu_footer', 'shikkosa_catalog_menu_footer_shortcode' );

add_filter( 'elementor/widget/text-editor/should_render_shortcode', '__return_true' );
add_filter( 'widget_text', 'do_shortcode' );
add_filter( 'widget_text_content', 'do_shortcode' );

function shikkosa_build_tax_query_from_param( $param_key, $taxonomies ) {
    if ( empty( $_GET[ $param_key ] ) ) {
        return array();
    }

    $raw_value = wp_unslash( $_GET[ $param_key ] );
    $value = sanitize_text_field( $raw_value );
    if ( $value === '' ) {
        return array();
    }

    $clauses = array();
    foreach ( $taxonomies as $tax ) {
        if ( ! taxonomy_exists( $tax ) ) {
            continue;
        }

        $terms = get_terms( array(
            'taxonomy'   => $tax,
            'hide_empty' => true,
            'name'       => $value,
        ) );
        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            $terms = get_terms( array(
                'taxonomy'   => $tax,
                'hide_empty' => true,
                'slug'       => sanitize_title( $value ),
            ) );
        }

        if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
            $term_ids = wp_list_pluck( $terms, 'term_id' );
            $clauses[] = array(
                'taxonomy' => $tax,
                'field'    => 'term_id',
                'terms'    => $term_ids,
                'operator' => 'IN',
            );
        }
    }

    if ( empty( $clauses ) ) {
        return array();
    }

    if ( count( $clauses ) === 1 ) {
        return $clauses[0];
    }

    return array_merge( array( 'relation' => 'OR' ), $clauses );
}

function shikkosa_apply_catalog_filters_to_query( $query ) {
    if ( ! $query || ! is_a( $query, 'WP_Query' ) ) {
        return;
    }

    $size_taxonomies = array( 'pa_razmer', 'pa_size' );
    $color_taxonomies = shikkosa_color_taxonomies();

    $tax_query = array();

    $size_clause = shikkosa_build_tax_query_from_param( 'size', $size_taxonomies );
    if ( ! empty( $size_clause ) ) {
        $tax_query[] = $size_clause;
    }

    $color_clause = shikkosa_build_tax_query_from_param( 'color', $color_taxonomies );
    if ( ! empty( $color_clause ) ) {
        $tax_query[] = $color_clause;
    }

    if ( ! empty( $tax_query ) ) {
        if ( count( $tax_query ) > 1 ) {
            $tax_query = array_merge( array( 'relation' => 'AND' ), $tax_query );
        }
        $query->set( 'tax_query', $tax_query );
    }

    $meta_query = (array) $query->get( 'meta_query' );
    $meta_query[] = array(
        'key'   => '_stock_status',
        'value' => 'instock',
    );
    $query->set( 'meta_query', $meta_query );

    $selected_size = isset( $_GET['size'] ) ? sanitize_text_field( wp_unslash( $_GET['size'] ) ) : '';
    $selected_color = isset( $_GET['color'] ) ? sanitize_text_field( wp_unslash( $_GET['color'] ) ) : '';
    $need_meta_fallback_filter = ( '' !== $selected_size || '' !== $selected_color );

    if ( $need_meta_fallback_filter ) {
        $base_args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
        );

        $existing_tax_query = $query->get( 'tax_query' );
        if ( is_array( $existing_tax_query ) && ! empty( $existing_tax_query ) ) {
            $base_args['tax_query'] = $existing_tax_query;
        }

        $candidate_ids = get_posts( $base_args );
        $matched_ids = array();

        foreach ( $candidate_ids as $pid ) {
            $data = shikkosa_product_size_color_data( (int) $pid, $size_taxonomies, $color_taxonomies );
            $sizes_for_product = (array) $data['sizes'];
            $colors_for_product = (array) $data['color_slugs'];

            $sizes_norm = array_values(
                array_unique(
                    array_filter(
                        array_map( 'shikkosa_normalize_size_value', $sizes_for_product )
                    )
                )
            );
            $colors_norm = array_values(
                array_unique(
                    array_filter(
                        array_map( 'shikkosa_normalize_color_slug', $colors_for_product )
                    )
                )
            );

            $size_ok = ( '' === $selected_size ) || in_array( shikkosa_normalize_size_value( $selected_size ), $sizes_norm, true );
            $color_ok = ( '' === $selected_color ) || in_array( shikkosa_normalize_color_slug( $selected_color ), $colors_norm, true );

            if ( $size_ok && $color_ok ) {
                $matched_ids[] = (int) $pid;
            }
        }

        $query->set( 'post__in', ! empty( $matched_ids ) ? array_values( array_unique( $matched_ids ) ) : array( 0 ) );
    }

    if ( isset( $_GET['sort'] ) ) {
        $sort = sanitize_text_field( wp_unslash( $_GET['sort'] ) );
        if ( $sort === 'asc' || $sort === 'desc' ) {
            $query->set( 'orderby', 'meta_value_num' );
            $query->set( 'meta_key', '_price' );
            $query->set( 'order', strtoupper( $sort ) );
        }
    }
}

add_action( 'pre_get_posts', function( $query ) {
    if ( is_admin() || ! $query->is_main_query() ) {
        return;
    }
    if ( is_shop() || is_product_taxonomy() || is_post_type_archive( 'product' ) ) {
        shikkosa_apply_catalog_filters_to_query( $query );
    }
} );

add_action( 'elementor/query/loop-4016', function( $query ) {
    shikkosa_apply_catalog_filters_to_query( $query );
} );
