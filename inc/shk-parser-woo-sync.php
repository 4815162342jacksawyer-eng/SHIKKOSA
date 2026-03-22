<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function shk_parser_import_job_option_name() {
    return 'shk_parser_import_job';
}

function shk_parser_category_map_option_name() {
    return 'shk_parser_category_map';
}

function shk_parser_existing_sync_option_name() {
    return 'shk_parser_existing_sync_snapshot';
}

function shk_parser_parse_pipe_list( $raw ) {
    if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
        return [];
    }

    $parts = preg_split( '/\s*\|\s*/u', trim( $raw ) );
    $parts = array_filter(
        array_map(
            static function ( $value ) {
                return trim( (string) $value );
            },
            $parts
        )
    );

    return array_values( array_unique( $parts ) );
}

function shk_parser_media_row_source_slug( array $row ) {
    return trim( (string) ( $row['slug'] ?? ( $row['product_slug'] ?? '' ) ) );
}

function shk_parser_media_base_url( array $row ) {
    $source = trim( (string) ( $row['source_url'] ?? '' ) );
    if ( '' !== $source ) {
        $parts = wp_parse_url( $source );
        if ( is_array( $parts ) && ! empty( $parts['scheme'] ) && ! empty( $parts['host'] ) ) {
            $base = $parts['scheme'] . '://' . $parts['host'];
            return rtrim( $base, '/' );
        }
    }

    $fallback = (string) apply_filters( 'shk_parser_media_base_url', 'https://shikkosa.ru' );
    return rtrim( $fallback, '/' );
}

function shk_parser_normalize_media_url( $raw, array $row ) {
    $raw = trim( (string) $raw );
    if ( '' === $raw ) {
        return '';
    }

    if ( 0 === strpos( $raw, '//' ) ) {
        $base = shk_parser_media_base_url( $row );
        $scheme = wp_parse_url( $base, PHP_URL_SCHEME );
        if ( ! $scheme ) {
            $scheme = 'https';
        }
        $raw = $scheme . ':' . $raw;
    }

    if ( ! preg_match( '~^https?://~i', $raw ) ) {
        if ( '/' !== substr( $raw, 0, 1 ) ) {
            $raw = '/' . $raw;
        }
        $base = shk_parser_media_base_url( $row );
        $raw = rtrim( $base, '/' ) . $raw;
    }

    $raw = preg_replace( '/\s+/', '%20', $raw );

    return wp_http_validate_url( $raw ) ? $raw : '';
}

function shk_parser_canonical_media_url( $url ) {
    $url = trim( (string) $url );
    if ( '' === $url ) {
        return '';
    }

    $parts = wp_parse_url( $url );
    if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
        return $url;
    }

    $path = isset( $parts['path'] ) ? (string) $parts['path'] : '/';
    if ( '' === $path ) {
        $path = '/';
    }

    return $parts['scheme'] . '://' . $parts['host'] . $path;
}

function shk_parser_media_source_key( $url ) {
    $url = trim( (string) $url );
    if ( '' === $url ) {
        return '';
    }

    $parts = wp_parse_url( $url );
    if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
        return '';
    }

    $host = strtolower( (string) $parts['host'] );
    $host = preg_replace( '/^www\./', '', $host );
    $path = isset( $parts['path'] ) ? (string) $parts['path'] : '/';
    if ( '' === $path ) {
        $path = '/';
    }

    return $host . $path;
}

function shk_parser_read_csv_assoc( $path ) {
    if ( ! $path || ! file_exists( $path ) ) {
        return [];
    }

    $rows = [];
    $handle = fopen( $path, 'r' );
    if ( ! $handle ) {
        return [];
    }

    $headers = fgetcsv( $handle );
    if ( ! is_array( $headers ) ) {
        fclose( $handle );
        return [];
    }

    $headers = array_map(
        static function ( $header ) {
            $header = (string) $header;
            $header = preg_replace( '/^\xEF\xBB\xBF/', '', $header );
            return trim( $header );
        },
        $headers
    );

    while ( ( $row = fgetcsv( $handle ) ) !== false ) {
        if ( ! is_array( $row ) ) {
            continue;
        }

        $assoc = [];
        foreach ( $headers as $index => $header ) {
            $assoc[ $header ] = isset( $row[ $index ] ) ? (string) $row[ $index ] : '';
        }
        $rows[] = $assoc;
    }

    fclose( $handle );
    return $rows;
}

function shk_parser_get_category_map() {
    $map = get_option( shk_parser_category_map_option_name(), [] );
    return is_array( $map ) ? $map : [];
}

function shk_parser_save_category_map( array $map ) {
    $clean = [];
    foreach ( $map as $donor_slug => $term_id ) {
        $donor_slug = sanitize_text_field( (string) $donor_slug );
        $term_id = (int) $term_id;
        if ( '' === $donor_slug ) {
            continue;
        }
        $clean[ $donor_slug ] = $term_id;
    }
    update_option( shk_parser_category_map_option_name(), $clean, false );
    return $clean;
}

function shk_parser_get_product_cat_choices() {
    $terms = get_terms(
        [
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        ]
    );

    if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
        return [];
    }

    $out = [];
    foreach ( $terms as $term ) {
        $out[] = [
            'term_id' => (int) $term->term_id,
            'name'    => $term->name,
            'slug'    => $term->slug,
        ];
    }

    usort(
        $out,
        static function ( $a, $b ) {
            return strcmp( $a['name'], $b['name'] );
        }
    );

    return $out;
}

function shk_parser_guess_product_cat_term_id( $donor_slug, $donor_name ) {
    $term = get_term_by( 'slug', sanitize_title( $donor_slug ), 'product_cat' );
    if ( $term && ! is_wp_error( $term ) ) {
        return (int) $term->term_id;
    }

    $term = get_term_by( 'name', $donor_name, 'product_cat' );
    if ( $term && ! is_wp_error( $term ) ) {
        return (int) $term->term_id;
    }

    return 0;
}

function shk_parser_auto_map_categories_1to1() {
    $overview = function_exists( 'shk_parser_get_overview_data' )
        ? shk_parser_get_overview_data()
        : [];
    $categories = isset( $overview['categories'] ) && is_array( $overview['categories'] ) ? $overview['categories'] : [];
    $map = shk_parser_get_category_map();

    foreach ( $categories as $category ) {
        $slug = sanitize_text_field( (string) ( $category['slug'] ?? '' ) );
        $name = sanitize_text_field( (string) ( $category['name'] ?? '' ) );
        if ( '' === $slug || ! empty( $map[ $slug ] ) ) {
            continue;
        }

        $term_id = shk_parser_guess_product_cat_term_id( $slug, $name );
        if ( ! $term_id ) {
            $created = wp_insert_term(
                $name ?: $slug,
                'product_cat',
                [
                    'slug' => sanitize_title( basename( $slug ) ),
                ]
            );
            if ( ! is_wp_error( $created ) && ! empty( $created['term_id'] ) ) {
                $term_id = (int) $created['term_id'];
            }
        }

        if ( $term_id ) {
            $map[ $slug ] = $term_id;
        }
    }

    return shk_parser_save_category_map( $map );
}

function shk_parser_get_import_job() {
    $job = get_option( shk_parser_import_job_option_name(), [] );
    return is_array( $job ) ? $job : [];
}

function shk_parser_save_import_job( array $job ) {
    update_option( shk_parser_import_job_option_name(), $job, false );
    return $job;
}

function shk_parser_append_import_log( array &$job, $message ) {
    if ( empty( $job['logs'] ) || ! is_array( $job['logs'] ) ) {
        $job['logs'] = [];
    }
    $job['logs'][] = '[' . current_time( 'mysql' ) . '] ' . $message;
    $job['logs'] = array_slice( $job['logs'], -80 );
}

function shk_parser_get_existing_products_snapshot() {
    $snapshot = get_option( shk_parser_existing_sync_option_name(), [] );
    return is_array( $snapshot ) ? $snapshot : [];
}

function shk_parser_attachment_exists( $attachment_id ) {
    $attachment_id = (int) $attachment_id;
    if ( ! $attachment_id ) {
        return false;
    }

    $post = get_post( $attachment_id );
    return ( $post instanceof WP_Post ) && 'attachment' === $post->post_type;
}

function shk_parser_get_valid_product_media( $product_id ) {
    $product_id = (int) $product_id;
    $featured_id = (int) get_post_thumbnail_id( $product_id );
    if ( $featured_id && ! shk_parser_attachment_exists( $featured_id ) ) {
        $featured_id = 0;
    }

    $gallery_raw = (string) get_post_meta( $product_id, '_product_image_gallery', true );
    $gallery_ids = array_values(
        array_filter(
            array_map(
                'intval',
                preg_split( '/\s*,\s*/', $gallery_raw, -1, PREG_SPLIT_NO_EMPTY )
            )
        )
    );

    $gallery_ids = array_values(
        array_filter(
            $gallery_ids,
            static function ( $attachment_id ) use ( $featured_id ) {
                $attachment_id = (int) $attachment_id;
                if ( $attachment_id <= 0 ) {
                    return false;
                }
                if ( $featured_id && $attachment_id === (int) $featured_id ) {
                    return false;
                }
                return shk_parser_attachment_exists( $attachment_id );
            }
        )
    );

    return [
        'featured_id' => (int) $featured_id,
        'gallery_ids' => array_values( array_unique( array_map( 'intval', $gallery_ids ) ) ),
    ];
}

function shk_parser_get_existing_products_stats() {
    $query = new WP_Query(
        [
            'post_type'      => 'product',
            'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'orderby'        => 'ID',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ]
    );

    $stats = [
        'total'              => 0,
        'bound'              => 0,
        'unbound'            => 0,
        'missing_featured'   => 0,
        'missing_gallery'    => 0,
        'repair_candidates'  => 0,
        'samples'            => [],
        'last_sync_snapshot' => shk_parser_get_existing_products_snapshot(),
    ];

    foreach ( $query->posts as $product_id ) {
        $product_id = (int) $product_id;
        $slug = get_post_field( 'post_name', $product_id );
        $source_slug = trim( (string) get_post_meta( $product_id, '_shk_source_slug', true ) );
        $media = shk_parser_get_valid_product_media( $product_id );
        $featured_id = (int) ( $media['featured_id'] ?? 0 );
        $gallery_ids = isset( $media['gallery_ids'] ) && is_array( $media['gallery_ids'] ) ? $media['gallery_ids'] : [];

        $is_bound = '' !== $source_slug;
        $has_candidate = $is_bound || '' !== $slug;

        $stats['total']++;
        if ( $is_bound ) {
            $stats['bound']++;
        } else {
            $stats['unbound']++;
        }

        if ( ! $featured_id ) {
            $stats['missing_featured']++;
        }
        if ( empty( $gallery_ids ) ) {
            $stats['missing_gallery']++;
        }
        if ( $has_candidate && ( ! $featured_id || empty( $gallery_ids ) ) ) {
            $stats['repair_candidates']++;
        }

        if ( count( $stats['samples'] ) < 30 && ( ! $is_bound || ! $featured_id || empty( $gallery_ids ) ) ) {
            $stats['samples'][] = [
                'product_id'        => $product_id,
                'name'              => get_the_title( $product_id ),
                'slug'              => $slug,
                'source_slug'       => $source_slug,
                'candidate_slug'    => $is_bound ? $source_slug : ( $slug ? '/product/' . $slug : '' ),
                'missing_featured'  => ! $featured_id,
                'missing_gallery'   => empty( $gallery_ids ),
            ];
        }
    }

    return $stats;
}

function shk_parser_bind_existing_products_by_slug() {
    $query = new WP_Query(
        [
            'post_type'      => 'product',
            'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => '_shk_source_slug',
                    'compare' => 'NOT EXISTS',
                ],
            ],
            'no_found_rows'  => true,
        ]
    );

    $bound = 0;
    foreach ( $query->posts as $product_id ) {
        $slug = trim( (string) get_post_field( 'post_name', (int) $product_id ) );
        if ( '' === $slug ) {
            continue;
        }

        $source_slug = '/product/' . $slug;
        update_post_meta( (int) $product_id, '_shk_source_slug', $source_slug );
        update_post_meta( (int) $product_id, '_shk_source_url', 'https://shikkosa.ru' . $source_slug );
        $bound++;
    }

    return $bound;
}

function shk_parser_repair_color_family_links() {
    $query = new WP_Query(
        [
            'post_type'      => 'product',
            'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'orderby'        => 'ID',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ]
    );

    $stats = [
        'scanned'             => 0,
        'source_bound'        => 0,
        'related_updated'     => 0,
        'members_filled'      => 0,
        'upsell_synced'       => 0,
    ];

    foreach ( $query->posts as $product_id ) {
        $product_id = (int) $product_id;
        if ( $product_id <= 0 ) {
            continue;
        }

        $stats['scanned']++;

        $post_slug = trim( (string) get_post_field( 'post_name', $product_id ) );
        $source_slug = trim( (string) get_post_meta( $product_id, '_shk_source_slug', true ) );
        if ( '' === $source_slug && '' !== $post_slug ) {
            $source_slug = '/product/' . $post_slug;
            update_post_meta( $product_id, '_shk_source_slug', $source_slug );
            update_post_meta( $product_id, '_shk_source_url', 'https://shikkosa.ru' . $source_slug );
            $stats['source_bound']++;
        }

        if ( function_exists( 'shikkosa_collect_related_ids_local' ) ) {
            $resolved_related_ids = array_values(
                array_unique(
                    array_filter(
                        array_map( 'intval', (array) shikkosa_collect_related_ids_local( $product_id ) )
                    )
                )
            );

            $old_related_ids = get_post_meta( $product_id, '_shk_related_ids', true );
            $old_related_ids = array_values(
                array_unique(
                    array_filter(
                        array_map(
                            'intval',
                            is_array( $old_related_ids ) ? $old_related_ids : preg_split( '/\s*[|,;]\s*/u', (string) $old_related_ids, -1, PREG_SPLIT_NO_EMPTY )
                        )
                    )
                )
            );

            sort( $resolved_related_ids );
            sort( $old_related_ids );
            if ( $resolved_related_ids !== $old_related_ids ) {
                if ( empty( $resolved_related_ids ) ) {
                    delete_post_meta( $product_id, '_shk_related_ids' );
                } else {
                    update_post_meta( $product_id, '_shk_related_ids', $resolved_related_ids );
                }
                $stats['related_updated']++;
            }

            $product = wc_get_product( $product_id );
            if ( $product && method_exists( $product, 'set_upsell_ids' ) ) {
                $product->set_upsell_ids( $resolved_related_ids );
                $product->save();
                $stats['upsell_synced']++;
            }
        }

        if ( function_exists( 'shikkosa_collect_color_family_ids_local' ) ) {
            $members_raw = trim( (string) get_post_meta( $product_id, '_shk_color_family_members', true ) );
            $family_ids = (array) shikkosa_collect_color_family_ids_local( $product_id, false );

            if ( empty( $family_ids ) ) {
                $family_ids = array_values(
                    array_unique(
                        array_filter(
                            array_map( 'intval', (array) get_post_meta( $product_id, '_shk_related_ids', true ) )
                        )
                    )
                );
            }

            $family_slugs = [];
            foreach ( $family_ids as $family_member_id ) {
                $family_member_id = (int) $family_member_id;
                if ( $family_member_id <= 0 ) {
                    continue;
                }

                $member_source_slug = trim( (string) get_post_meta( $family_member_id, '_shk_source_slug', true ) );
                if ( '' === $member_source_slug ) {
                    $member_post_slug = trim( (string) get_post_field( 'post_name', $family_member_id ) );
                    if ( '' !== $member_post_slug ) {
                        $member_source_slug = '/product/' . $member_post_slug;
                    }
                }

                if ( '' !== $member_source_slug ) {
                    $family_slugs[] = $member_source_slug;
                }
            }

            $family_slugs = array_values( array_unique( array_filter( $family_slugs ) ) );
            $new_members = implode( '|', $family_slugs );

            if ( '' !== $new_members && $new_members !== $members_raw ) {
                update_post_meta( $product_id, '_shk_color_family_members', $new_members );
                $stats['members_filled']++;
            }

            $family_ids = array_values(
                array_unique(
                    array_filter(
                        array_map( 'intval', $family_ids )
                    )
                )
            );
            if ( ! empty( $family_ids ) ) {
                update_post_meta( $product_id, '_shk_color_family_ids', $family_ids );
            } else {
                delete_post_meta( $product_id, '_shk_color_family_ids' );
            }
        }
    }

    return $stats;
}

function shk_parser_collect_existing_product_slugs( $scope = 'all' ) {
    $query = new WP_Query(
        [
            'post_type'      => 'product',
            'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'orderby'        => 'ID',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ]
    );

    $slugs = [];
    $products = [];

    foreach ( $query->posts as $product_id ) {
        $product_id = (int) $product_id;
        $post_slug = trim( (string) get_post_field( 'post_name', $product_id ) );
        $source_slug = trim( (string) get_post_meta( $product_id, '_shk_source_slug', true ) );
        $media = shk_parser_get_valid_product_media( $product_id );
        $featured_id = (int) ( $media['featured_id'] ?? 0 );
        $has_gallery = ! empty( $media['gallery_ids'] );

        $candidate = $source_slug ?: ( $post_slug ? '/product/' . $post_slug : '' );
        if ( '' === $candidate ) {
            continue;
        }

        if ( 'broken_media' === $scope && $featured_id && $has_gallery ) {
            continue;
        }
        if ( 'unbound' === $scope && '' !== $source_slug ) {
            continue;
        }

        $slugs[] = $candidate;
        $products[] = [
            'product_id'  => $product_id,
            'slug'        => $post_slug,
            'source_slug' => $candidate,
        ];
    }

    $slugs = array_values( array_unique( array_filter( $slugs ) ) );

    $snapshot = [
        'scope'       => $scope,
        'product_ids' => array_values( array_map( 'intval', wp_list_pluck( $products, 'product_id' ) ) ),
        'source_slugs'=> $slugs,
        'generated_at'=> current_time( 'mysql' ),
    ];
    update_option( shk_parser_existing_sync_option_name(), $snapshot, false );

    return $slugs;
}

function shk_parser_find_product_id_by_source_slug( $source_slug ) {
    $query = new WP_Query(
        [
            'post_type'      => 'product',
            'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => '_shk_source_slug',
                    'value' => $source_slug,
                ],
            ],
        ]
    );

    if ( ! empty( $query->posts ) ) {
        return (int) $query->posts[0];
    }

    $slug_tail = basename( trim( (string) $source_slug ) );
    if ( $slug_tail ) {
        $existing = get_page_by_path( $slug_tail, OBJECT, 'product' );
        if ( $existing instanceof WP_Post ) {
            return (int) $existing->ID;
        }
    }

    return 0;
}

function shk_parser_build_attributes_from_row( array $row ) {
    $attributes = [];

    $color = trim( (string) ( $row['color'] ?? '' ) );
    if ( '' !== $color ) {
        $attribute = new WC_Product_Attribute();
        $attribute->set_name( 'Цвет' );
        $attribute->set_options( [ $color ] );
        $attribute->set_visible( true );
        $attribute->set_variation( false );
        $attributes[] = $attribute;
    }

    $sizes = shk_parser_parse_pipe_list( (string) ( $row['sizes'] ?? '' ) );
    if ( ! empty( $sizes ) ) {
        $attribute = new WC_Product_Attribute();
        $attribute->set_name( 'Размер' );
        $attribute->set_options( $sizes );
        $attribute->set_visible( true );
        $attribute->set_variation( false );
        $attributes[] = $attribute;
    }

    return $attributes;
}

function shk_parser_resolve_product_term_ids_from_row( array $row ) {
    $map = shk_parser_get_category_map();
    $term_ids = [];
    $slugs = shk_parser_parse_pipe_list( (string) ( $row['category_slugs'] ?? '' ) );
    $names = shk_parser_parse_pipe_list( (string) ( $row['categories'] ?? '' ) );

    foreach ( $slugs as $index => $slug ) {
        $slug = sanitize_text_field( $slug );
        $name = isset( $names[ $index ] ) ? sanitize_text_field( $names[ $index ] ) : '';
        $term_id = isset( $map[ $slug ] ) ? (int) $map[ $slug ] : 0;

        if ( ! $term_id ) {
            $term_id = shk_parser_guess_product_cat_term_id( $slug, $name );
        }

        if ( $term_id ) {
            $term_ids[] = $term_id;
        }
    }

    return array_values( array_unique( array_filter( array_map( 'intval', $term_ids ) ) ) );
}

function shk_parser_resolve_donor_term_ids_from_overview() {
    if ( ! function_exists( 'shk_parser_get_overview_data' ) ) {
        return [];
    }

    $overview = shk_parser_get_overview_data();
    $categories = isset( $overview['categories'] ) && is_array( $overview['categories'] ) ? $overview['categories'] : [];
    if ( empty( $categories ) ) {
        return [];
    }

    $map = shk_parser_get_category_map();
    $term_ids = [];

    foreach ( $categories as $category ) {
        $slug = sanitize_text_field( (string) ( $category['slug'] ?? '' ) );
        $name = sanitize_text_field( (string) ( $category['name'] ?? '' ) );
        if ( '' === $slug ) {
            continue;
        }

        $term_id = isset( $map[ $slug ] ) ? (int) $map[ $slug ] : 0;
        if ( ! $term_id ) {
            $term_id = shk_parser_guess_product_cat_term_id( $slug, $name );
        }
        if ( $term_id ) {
            $term_ids[] = (int) $term_id;
        }
    }

    return array_values( array_unique( array_filter( array_map( 'intval', $term_ids ) ) ) );
}

function shk_parser_import_product_row( array $row, $run_id ) {
    if ( ! class_exists( 'WC_Product_Simple' ) ) {
        return new WP_Error( 'woocommerce_missing', 'WooCommerce недоступен.' );
    }

    $source_slug = trim( (string) ( $row['slug'] ?? '' ) );
    if ( '' === $source_slug ) {
        return new WP_Error( 'missing_slug', 'Пустой source slug.' );
    }

    $product_id = shk_parser_find_product_id_by_source_slug( $source_slug );
    $created = false;

    if ( $product_id ) {
        // Force existing products into simple type in parser flow.
        $product = new WC_Product_Simple( $product_id );
    } else {
        $product = new WC_Product_Simple();
        $created = true;
    }

    $name = trim( (string) ( $row['name'] ?? '' ) );
    if ( '' === $name ) {
        $name = ucwords( str_replace( '-', ' ', basename( $source_slug ) ) );
    }

    $product->set_name( $name );
    $product->set_slug( sanitize_title( basename( (string) ( $row['slug'] ?? '' ) ) ) );
    // 1:1 sync mode: imported donor products should be visible immediately.
    $product->set_status( 'publish' );

    $normalized_prices = shk_parser_normalize_prices_from_row( $row );
    $regular_price = (string) ( $normalized_prices['regular'] ?? '' );
    $sale_price = (string) ( $normalized_prices['sale'] ?? '' );

    if ( '' !== $regular_price ) {
        $product->set_regular_price( $regular_price );
    }

    $product->set_sale_price( $sale_price );

    $sku = trim( (string) ( $row['sku'] ?? '' ) );
    if ( '' !== $sku ) {
        try {
            $existing_sku_id = wc_get_product_id_by_sku( $sku );
            if ( ! $existing_sku_id || (int) $existing_sku_id === (int) $product->get_id() ) {
                $product->set_sku( $sku );
            }
        } catch ( Exception $e ) {
        }
    }

    $description = (string) ( $row['description'] ?? '' );
    $short_description = (string) ( $row['compound'] ?? '' );
    if ( '' !== $description ) {
        $product->set_description( $description );
    }
    if ( '' !== $short_description ) {
        $product->set_short_description( $short_description );
    }

    $term_ids = shk_parser_resolve_product_term_ids_from_row( $row );
    // Always replace category set so stale Woo categories are removed on re-import.
    $product->set_category_ids( $term_ids );

    $attributes = shk_parser_build_attributes_from_row( $row );
    if ( ! empty( $attributes ) ) {
        $product->set_attributes( $attributes );
    }

    $product->set_manage_stock( false );
    $product->set_stock_status( 'instock' );

    $saved_id = $product->save();
    if ( ! $saved_id ) {
        return new WP_Error( 'save_failed', 'Не удалось сохранить товар.' );
    }

    update_post_meta( $saved_id, '_shk_source_slug', $source_slug );
    update_post_meta( $saved_id, '_shk_source_url', (string) ( $row['source_url'] ?? '' ) );
    update_post_meta( $saved_id, '_shk_color_family_id', (string) ( $row['color_family_id'] ?? '' ) );
    update_post_meta( $saved_id, '_shk_color_family_members', (string) ( $row['color_family_members'] ?? '' ) );
    update_post_meta( $saved_id, '_shk_related_slugs', (string) ( $row['related_slugs'] ?? '' ) );
    update_post_meta( $saved_id, '_shk_color', (string) ( $row['color'] ?? '' ) );
    update_post_meta( $saved_id, '_shk_sizes', (string) ( $row['sizes'] ?? '' ) );
    update_post_meta( $saved_id, '_shk_care', (string) ( $row['care'] ?? '' ) );
    update_post_meta( $saved_id, '_shk_import_run_id', $run_id );
    update_post_meta( $saved_id, '_shk_last_category_slugs', (string) ( $row['category_slugs'] ?? '' ) );
    update_post_meta( $saved_id, '_shk_last_categories', (string) ( $row['categories'] ?? '' ) );
    shk_parser_sync_display_price_meta( (int) $saved_id, $regular_price, $sale_price );

    return [
        'product_id' => (int) $saved_id,
        'created'    => $created,
    ];
}

function shk_parser_normalize_prices_from_row( array $row ) {
    $pick_first = static function( array $keys ) use ( $row ) {
        foreach ( $keys as $key ) {
            $key = (string) $key;
            if ( '' === $key ) {
                continue;
            }
            if ( array_key_exists( $key, $row ) ) {
                $value = trim( (string) $row[ $key ] );
                if ( '' !== $value ) {
                    return $value;
                }
            }
        }
        return '';
    };

    $raw_regular_input = $pick_first( [
        'price_before_discount',
        'regular_price',
        'price_regular',
        'old_price',
        'price_old',
        'oldprice',
        'regular',
    ] );
    $raw_sale_input = $pick_first( [
        'price',
        'sale_price',
        'price_sale',
        'current_price',
        'actual_price',
        'sale',
    ] );

    $raw_regular_price = preg_replace( '/[^\d.,]/', '', $raw_regular_input );
    $raw_sale_price = preg_replace( '/[^\d.,]/', '', $raw_sale_input );
    $raw_regular_price = str_replace( ',', '.', (string) $raw_regular_price );
    $raw_sale_price = str_replace( ',', '.', (string) $raw_sale_price );

    $regular_price = '';
    $sale_price = '';
    $swapped = false;

    $regular_float = ( '' !== $raw_regular_price && is_numeric( $raw_regular_price ) ) ? (float) $raw_regular_price : 0.0;
    $sale_float = ( '' !== $raw_sale_price && is_numeric( $raw_sale_price ) ) ? (float) $raw_sale_price : 0.0;

    if ( $regular_float > 0 && $sale_float > 0 ) {
        $swapped = ( $regular_float < $sale_float );
        $high = max( $regular_float, $sale_float );
        $low = min( $regular_float, $sale_float );
        $regular_price = wc_format_decimal( $high );
        if ( $low < $high ) {
            $sale_price = wc_format_decimal( $low );
        }
    } elseif ( $sale_float > 0 ) {
        $regular_price = wc_format_decimal( $sale_float );
    } elseif ( $regular_float > 0 ) {
        $regular_price = wc_format_decimal( $regular_float );
    }

    return [
        'regular' => $regular_price,
        'sale'    => $sale_price,
        'swapped' => $swapped,
    ];
}

function shk_parser_row_meta_value( array $row, $key ) {
    return trim( (string) ( $row[ $key ] ?? '' ) );
}

function shk_parser_parse_price_scalar( $raw ) {
    $raw = preg_replace( '/[^\d.,]/', '', (string) $raw );
    $raw = str_replace( ',', '.', (string) $raw );
    if ( '' === $raw || ! is_numeric( $raw ) ) {
        return 0.0;
    }
    return (float) $raw;
}

function shk_parser_sync_display_price_meta( $product_id, $regular_price_raw = '', $sale_price_raw = '' ) {
    $product_id = (int) $product_id;
    if ( $product_id <= 0 ) {
        return false;
    }

    $regular_source = '' !== (string) $regular_price_raw ? $regular_price_raw : get_post_meta( $product_id, '_regular_price', true );
    $sale_source = '' !== (string) $sale_price_raw ? $sale_price_raw : get_post_meta( $product_id, '_sale_price', true );
    $regular = shk_parser_parse_price_scalar( $regular_source );
    $sale = shk_parser_parse_price_scalar( $sale_source );

    $original = 0.0;
    $current = 0.0;
    if ( $regular > 0 && $sale > 0 ) {
        $original = max( $regular, $sale );
        $current = min( $regular, $sale );
        if ( abs( $original - $current ) < 0.00001 ) {
            $current = 0.0;
        }
    } elseif ( $regular > 0 ) {
        $original = $regular;
    } elseif ( $sale > 0 ) {
        $original = $sale;
    }

    $original_val = $original > 0 ? wc_format_decimal( $original ) : '';
    $current_val = $current > 0 ? wc_format_decimal( $current ) : '';
    $effective_val = '' !== $current_val ? $current_val : $original_val;
    $changed = false;

    $old_original = trim( (string) get_post_meta( $product_id, '_shk_display_price_original', true ) );
    $old_current = trim( (string) get_post_meta( $product_id, '_shk_display_price_sale', true ) );
    $old_effective = trim( (string) get_post_meta( $product_id, '_shk_display_price_current', true ) );

    if ( $old_original !== $original_val ) {
        if ( '' === $original_val ) {
            delete_post_meta( $product_id, '_shk_display_price_original' );
        } else {
            update_post_meta( $product_id, '_shk_display_price_original', $original_val );
        }
        $changed = true;
    }

    if ( $old_current !== $current_val ) {
        if ( '' === $current_val ) {
            delete_post_meta( $product_id, '_shk_display_price_sale' );
        } else {
            update_post_meta( $product_id, '_shk_display_price_sale', $current_val );
        }
        $changed = true;
    }

    if ( $old_effective !== $effective_val ) {
        if ( '' === $effective_val ) {
            delete_post_meta( $product_id, '_shk_display_price_current' );
        } else {
            update_post_meta( $product_id, '_shk_display_price_current', $effective_val );
        }
        $changed = true;
    }

    return $changed;
}

function shk_parser_ensure_product_simple_type( $product_id ) {
    $product_id = (int) $product_id;
    if ( $product_id <= 0 ) {
        return [
            'changed' => false,
            'product' => null,
        ];
    }

    $current = wc_get_product( $product_id );
    if ( ! $current ) {
        return [
            'changed' => false,
            'product' => null,
        ];
    }

    $was_simple = $current->is_type( 'simple' );
    if ( ! $was_simple ) {
        wp_set_object_terms( $product_id, 'simple', 'product_type', false );
    }

    $simple = new WC_Product_Simple( $product_id );
    $simple->set_manage_stock( false );
    $simple->set_stock_status( 'instock' );
    $simple->save();

    return [
        'changed' => ! $was_simple,
        'product' => $simple,
    ];
}

function shk_parser_repair_from_run( $run_id ) {
    $run_id = sanitize_text_field( (string) $run_id );
    if ( '' === $run_id ) {
        return new WP_Error( 'missing_run_id', 'Не передан run_id.' );
    }

    $run = shk_parser_get_run( $run_id );
    if ( empty( $run ) ) {
        return new WP_Error( 'run_not_found', 'Run не найден: ' . $run_id );
    }

    $products_path = $run['files']['products'] ?? '';
    if ( ! $products_path || ! file_exists( $products_path ) ) {
        return new WP_Error( 'products_missing', 'products.csv не найден для run: ' . $run_id );
    }

    $stats = [
        'run_id'                  => $run_id,
        'products_scanned'        => 0,
        'products_matched'        => 0,
        'converted_to_simple'     => 0,
        'prices_updated'          => 0,
        'prices_rewritten'        => 0,
        'prices_swapped_detected' => 0,
        'display_price_meta_sync' => 0,
        'meta_products_filled'    => 0,
        'meta_fields_filled'      => 0,
        'related_products'        => 0,
        'related_updated'         => 0,
        'related_cleared'         => 0,
        'related_slugs_updated'   => 0,
    ];

    $rows = shk_parser_read_csv_assoc( $products_path );
    $product_ids_from_run = [];

    foreach ( $rows as $row ) {
        $source_slug = trim( (string) ( $row['slug'] ?? '' ) );
        if ( '' === $source_slug ) {
            continue;
        }

        $stats['products_scanned']++;
        $product_id = (int) shk_parser_find_product_id_by_source_slug( $source_slug );
        if ( $product_id <= 0 ) {
            continue;
        }

        $stats['products_matched']++;
        $product_ids_from_run[ $product_id ] = true;

        $simple_result = shk_parser_ensure_product_simple_type( $product_id );
        if ( ! empty( $simple_result['changed'] ) ) {
            $stats['converted_to_simple']++;
        }
        $product = isset( $simple_result['product'] ) ? $simple_result['product'] : null;
        if ( ! $product ) {
            continue;
        }

        $meta_filled_for_product = false;
        $source_url = shk_parser_row_meta_value( $row, 'source_url' );
        if ( '' === $source_url && '' !== $source_slug ) {
            $source_url = 'https://shikkosa.ru' . $source_slug;
        }
        $meta_fill_map = [
            '_shk_source_slug'         => $source_slug,
            '_shk_source_url'          => $source_url,
            '_shk_color_family_id'     => shk_parser_row_meta_value( $row, 'color_family_id' ),
            '_shk_color_family_members'=> shk_parser_row_meta_value( $row, 'color_family_members' ),
            '_shk_color'               => shk_parser_row_meta_value( $row, 'color' ),
            '_shk_sizes'               => shk_parser_row_meta_value( $row, 'sizes' ),
            '_shk_care'                => shk_parser_row_meta_value( $row, 'care' ),
        ];

        foreach ( $meta_fill_map as $meta_key => $meta_value ) {
            if ( '' === $meta_value ) {
                continue;
            }
            $existing_value = trim( (string) get_post_meta( $product_id, $meta_key, true ) );
            if ( '' !== $existing_value ) {
                continue;
            }
            update_post_meta( $product_id, $meta_key, $meta_value );
            $stats['meta_fields_filled']++;
            $meta_filled_for_product = true;
        }
        update_post_meta( $product_id, '_shk_import_run_id', $run_id );
        if ( $meta_filled_for_product ) {
            $stats['meta_products_filled']++;
        }

        $normalized_prices = shk_parser_normalize_prices_from_row( $row );
        $regular_price = (string) ( $normalized_prices['regular'] ?? '' );
        $sale_price = (string) ( $normalized_prices['sale'] ?? '' );
        if ( ! empty( $normalized_prices['swapped'] ) ) {
            $stats['prices_swapped_detected']++;
        }

        if ( '' === $regular_price && '' === $sale_price ) {
            // Fallback for already imported rows with missing CSV prices:
            // if DB raw pair is inverted (regular < sale), swap physically.
            $raw_regular = shk_parser_parse_price_scalar( get_post_meta( $product_id, '_regular_price', true ) );
            $raw_sale = shk_parser_parse_price_scalar( get_post_meta( $product_id, '_sale_price', true ) );
            if ( $raw_regular > 0 && $raw_sale > 0 && $raw_regular < $raw_sale ) {
                $regular_price = wc_format_decimal( $raw_sale );
                $sale_price = wc_format_decimal( $raw_regular );
                $stats['prices_swapped_detected']++;
            } else {
                continue;
            }
        }

        $old_regular = shk_parser_parse_price_scalar( get_post_meta( $product_id, '_regular_price', true ) );
        $old_sale = shk_parser_parse_price_scalar( get_post_meta( $product_id, '_sale_price', true ) );
        $new_regular = ( '' !== $regular_price ) ? (float) $regular_price : 0.0;
        $new_sale = ( '' !== $sale_price ) ? (float) $sale_price : 0.0;

        $effective_price = ( '' !== $sale_price ) ? $sale_price : $regular_price;
        if ( '' === $effective_price ) {
            $effective_price = wc_format_decimal( $new_regular );
        }

        $is_price_changed = !( abs( $old_regular - $new_regular ) < 0.00001 && abs( $old_sale - $new_sale ) < 0.00001 );

        update_post_meta( $product_id, '_regular_price', $regular_price );
        update_post_meta( $product_id, '_sale_price', $sale_price );
        update_post_meta( $product_id, '_price', $effective_price );
        if ( shk_parser_sync_display_price_meta( $product_id, $regular_price, $sale_price ) ) {
            $stats['display_price_meta_sync']++;
        }

        $product->set_regular_price( $regular_price );
        $product->set_sale_price( $sale_price );
        if ( method_exists( $product, 'set_price' ) ) {
            $product->set_price( $effective_price );
        }
        $product->save();

        if ( function_exists( 'wc_delete_product_transients' ) ) {
            wc_delete_product_transients( $product_id );
        }

        $stats['prices_rewritten']++;
        if ( $is_price_changed ) {
            $stats['prices_updated']++;
        }
    }

    $related_bucket = [];
    $related_slug_bucket = [];
    $related_path = $run['files']['related'] ?? '';
    if ( $related_path && file_exists( $related_path ) ) {
        $related_rows = shk_parser_read_csv_assoc( $related_path );
        foreach ( $related_rows as $row ) {
            $product_slug = trim( (string) ( $row['product_slug'] ?? '' ) );
            $related_slug = trim( (string) ( $row['related_slug'] ?? '' ) );
            if ( '' === $product_slug || '' === $related_slug ) {
                continue;
            }

            $product_id = (int) shk_parser_find_product_id_by_source_slug( $product_slug );
            $related_id = (int) shk_parser_find_product_id_by_source_slug( $related_slug );
            if ( $product_id <= 0 || $related_id <= 0 || $product_id === $related_id ) {
                continue;
            }

            if ( ! isset( $related_bucket[ $product_id ] ) ) {
                $related_bucket[ $product_id ] = [];
            }
            $related_bucket[ $product_id ][] = $related_id;

            if ( ! isset( $related_slug_bucket[ $product_id ] ) ) {
                $related_slug_bucket[ $product_id ] = [];
            }
            $related_slug_bucket[ $product_id ][] = $related_slug;
        }
    }

    foreach ( array_keys( $product_ids_from_run ) as $product_id ) {
        $product_id = (int) $product_id;
        if ( $product_id <= 0 ) {
            continue;
        }

        $stats['related_products']++;
        $new_related_ids = isset( $related_bucket[ $product_id ] ) ? (array) $related_bucket[ $product_id ] : [];
        $new_related_ids = array_values( array_unique( array_filter( array_map( 'intval', $new_related_ids ) ) ) );
        sort( $new_related_ids );

        $old_related_ids = shikkosa_parse_int_values_local( get_post_meta( $product_id, '_shk_related_ids', true ) );
        sort( $old_related_ids );

        if ( $old_related_ids !== $new_related_ids ) {
            if ( empty( $new_related_ids ) ) {
                delete_post_meta( $product_id, '_shk_related_ids' );
                $stats['related_cleared']++;
            } else {
                update_post_meta( $product_id, '_shk_related_ids', $new_related_ids );
            }
            $stats['related_updated']++;
        }

        $new_related_slugs = isset( $related_slug_bucket[ $product_id ] ) ? (array) $related_slug_bucket[ $product_id ] : [];
        $new_related_slugs = array_values( array_unique( array_filter( array_map( 'trim', $new_related_slugs ) ) ) );
        $old_related_slugs = shikkosa_parse_pipe_values_local( (string) get_post_meta( $product_id, '_shk_related_slugs', true ) );
        sort( $new_related_slugs );
        sort( $old_related_slugs );
        if ( $old_related_slugs !== $new_related_slugs ) {
            if ( empty( $new_related_slugs ) ) {
                delete_post_meta( $product_id, '_shk_related_slugs' );
            } else {
                update_post_meta( $product_id, '_shk_related_slugs', implode( '|', $new_related_slugs ) );
            }
            $stats['related_slugs_updated']++;
        }

        $product = wc_get_product( $product_id );
        if ( $product && method_exists( $product, 'set_upsell_ids' ) ) {
            $product->set_upsell_ids( $new_related_ids );
            $product->save();
        }
    }

    return $stats;
}

function shk_parser_finalize_products_1to1( $run_id, array &$job ) {
    $run = shk_parser_get_run( $run_id );
    $products_path = $run['files']['products'] ?? '';
    if ( ! $products_path || ! file_exists( $products_path ) ) {
        shk_parser_append_import_log( $job, 'products.csv недоступен, 1:1-финализация пропущена.' );
        return;
    }

    $rows = shk_parser_read_csv_assoc( $products_path );
    $donor_slugs = [];
    $keep_term_ids = shk_parser_resolve_donor_term_ids_from_overview();

    foreach ( $rows as $row ) {
        $slug = trim( (string) ( $row['slug'] ?? '' ) );
        if ( '' !== $slug ) {
            $donor_slugs[] = $slug;
        }
    }

    // Fallback to run rows if overview is unavailable.
    if ( empty( $keep_term_ids ) ) {
        foreach ( $rows as $row ) {
            $keep_term_ids = array_merge( $keep_term_ids, shk_parser_resolve_product_term_ids_from_row( $row ) );
        }
        shk_parser_append_import_log( $job, 'Overview недоступен: категории для 1:1 взяты из products.csv текущего run.' );
    }

    $donor_slugs = array_values( array_unique( array_filter( $donor_slugs ) ) );
    $keep_term_ids = array_values( array_unique( array_filter( array_map( 'intval', $keep_term_ids ) ) ) );
    $donor_slug_lookup = array_fill_keys( $donor_slugs, true );

    $query = new WP_Query(
        [
            'post_type'      => 'product',
            'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => '_shk_source_slug',
                    'compare' => 'EXISTS',
                ],
            ],
            'no_found_rows'  => true,
        ]
    );

    $trashed = 0;
    foreach ( $query->posts as $product_id ) {
        $product_id = (int) $product_id;
        $source_slug = trim( (string) get_post_meta( $product_id, '_shk_source_slug', true ) );
        if ( '' === $source_slug ) {
            continue;
        }

        if ( ! isset( $donor_slug_lookup[ $source_slug ] ) ) {
            if ( wp_trash_post( $product_id ) ) {
                $trashed++;
            }
        }
    }

    $deleted_terms = 0;
    $default_product_cat = (int) get_option( 'default_product_cat', 0 );
    if ( ! empty( $keep_term_ids ) ) {
        $terms = get_terms(
            [
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
            ]
        );
        if ( is_array( $terms ) ) {
            foreach ( $terms as $term ) {
                $term_id = (int) $term->term_id;
                if ( $term_id === $default_product_cat ) {
                    continue;
                }
                if ( in_array( $term_id, $keep_term_ids, true ) ) {
                    continue;
                }
                $deleted = wp_delete_term( $term_id, 'product_cat' );
                if ( ! is_wp_error( $deleted ) && $deleted ) {
                    $deleted_terms++;
                }
            }
        }
    } else {
        shk_parser_append_import_log( $job, 'Категории 1:1 не очищались: нет валидных mapped term_id для товаров run.' );
    }

    shk_parser_append_import_log(
        $job,
        sprintf(
            '1:1 финализация: удалено в корзину товаров %d, удалено категорий Woo %d.',
            (int) $trashed,
            (int) $deleted_terms
        )
    );
}

function shk_parser_find_attachment_id_by_source_url( $url ) {
    $canonical_url = shk_parser_canonical_media_url( $url );
    $source_key = shk_parser_media_source_key( $url );

    $query = new WP_Query(
        [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => '_shk_source_url',
                    'value' => $url,
                ],
            ],
        ]
    );

    if ( ! empty( $query->posts ) ) {
        $found_id = (int) $query->posts[0];
        if ( '' !== $source_key ) {
            update_post_meta( $found_id, '_shk_source_key', $source_key );
        }
        return $found_id;
    }

    if ( '' !== $canonical_url ) {
        $query = new WP_Query(
            [
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_query'     => [
                    [
                        'key'   => '_shk_source_url_canonical',
                        'value' => $canonical_url,
                    ],
                ],
            ]
        );

        if ( ! empty( $query->posts ) ) {
            $found_id = (int) $query->posts[0];
            if ( '' !== $source_key ) {
                update_post_meta( $found_id, '_shk_source_key', $source_key );
            }
            return $found_id;
        }
    }

    if ( '' !== $source_key ) {
        $query = new WP_Query(
            [
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_query'     => [
                    [
                        'key'   => '_shk_source_key',
                        'value' => $source_key,
                    ],
                ],
            ]
        );

        if ( ! empty( $query->posts ) ) {
            return (int) $query->posts[0];
        }

        // Backward-compat: attachments imported before _shk_source_key existed.
        $path = (string) wp_parse_url( $url, PHP_URL_PATH );
        $basename = $path ? wp_basename( $path ) : '';
        if ( '' !== $basename ) {
            $legacy_query = new WP_Query(
                [
                    'post_type'      => 'attachment',
                    'post_status'    => 'inherit',
                    'posts_per_page' => 50,
                    'fields'         => 'ids',
                    'meta_query'     => [
                        [
                            'key'     => '_shk_source_url',
                            'value'   => $basename,
                            'compare' => 'LIKE',
                        ],
                    ],
                ]
            );

            if ( ! empty( $legacy_query->posts ) ) {
                foreach ( $legacy_query->posts as $legacy_id ) {
                    $legacy_url = (string) get_post_meta( (int) $legacy_id, '_shk_source_url', true );
                    if ( '' === $legacy_url ) {
                        continue;
                    }
                    if ( $source_key === shk_parser_media_source_key( $legacy_url ) ) {
                        update_post_meta( (int) $legacy_id, '_shk_source_key', $source_key );
                        return (int) $legacy_id;
                    }
                }
            }
        }
    }

    return 0;
}

function shk_parser_sideload_attachment( $url, $product_id ) {
    $existing_id = shk_parser_find_attachment_id_by_source_url( $url );
    if ( $existing_id ) {
        return $existing_id;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $tmp = download_url( $url, 45 );
    if ( is_wp_error( $tmp ) ) {
        return $tmp;
    }

    $file_name = wp_basename( parse_url( $url, PHP_URL_PATH ) ?: 'image.jpg' );
    if ( '' === $file_name ) {
        $file_name = 'image.jpg';
    }

    $file_array = [
        'name'     => sanitize_file_name( $file_name ),
        'tmp_name' => $tmp,
    ];

    $attachment_id = media_handle_sideload( $file_array, $product_id );
    if ( is_wp_error( $attachment_id ) ) {
        @unlink( $tmp );
        return $attachment_id;
    }

    update_post_meta( $attachment_id, '_shk_source_url', $url );
    update_post_meta( $attachment_id, '_shk_source_url_canonical', shk_parser_canonical_media_url( $url ) );
    update_post_meta( $attachment_id, '_shk_source_key', shk_parser_media_source_key( $url ) );
    return (int) $attachment_id;
}

function shk_parser_get_product_gallery_ids( $product_id ) {
    $gallery_raw = (string) get_post_meta( (int) $product_id, '_product_image_gallery', true );
    return array_values(
        array_filter(
            array_map(
                'intval',
                preg_split( '/\s*,\s*/', $gallery_raw, -1, PREG_SPLIT_NO_EMPTY )
            )
        )
    );
}

function shk_parser_is_attachment_used_elsewhere( $attachment_id, $exclude_product_id = 0 ) {
    global $wpdb;

    $attachment_id = (int) $attachment_id;
    $exclude_product_id = (int) $exclude_product_id;
    if ( ! $attachment_id ) {
        return false;
    }

    $post_types = [ 'product', 'product_variation' ];
    $in_clause = "'" . implode( "','", array_map( 'esc_sql', $post_types ) ) . "'";

    $featured_count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(1)
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = '_thumbnail_id'
              AND pm.meta_value = %d
              AND p.post_type IN ($in_clause)
              AND p.ID <> %d",
            $attachment_id,
            $exclude_product_id
        )
    );
    if ( $featured_count > 0 ) {
        return true;
    }

    $gallery_count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(1)
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = '_product_image_gallery'
              AND FIND_IN_SET(%d, pm.meta_value)
              AND p.post_type IN ($in_clause)
              AND p.ID <> %d",
            $attachment_id,
            $exclude_product_id
        )
    );

    return $gallery_count > 0;
}

function shk_parser_delete_stale_attachment_if_unused( $attachment_id, $product_id ) {
    $attachment_id = (int) $attachment_id;
    $product_id = (int) $product_id;
    if ( ! $attachment_id ) {
        return false;
    }

    $attachment_post = get_post( $attachment_id );
    if ( ! ( $attachment_post instanceof WP_Post ) || 'attachment' !== $attachment_post->post_type ) {
        return false;
    }

    if ( shk_parser_is_attachment_used_elsewhere( $attachment_id, $product_id ) ) {
        return false;
    }

    return (bool) wp_delete_attachment( $attachment_id, true );
}

function shk_parser_collect_products_for_media_cleanup() {
    $query = new WP_Query(
        [
            'post_type'      => 'product',
            'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ]
    );

    return array_values( array_map( 'intval', (array) $query->posts ) );
}

function shk_parser_cleanup_product_media_duplicates( $product_id ) {
    $product_id = (int) $product_id;
    if ( ! $product_id ) {
        return [
            'changed'             => false,
            'links_removed'       => 0,
            'attachments_deleted' => 0,
        ];
    }

    $media = shk_parser_get_valid_product_media( $product_id );
    $featured_id = (int) ( $media['featured_id'] ?? 0 );
    $gallery_ids = isset( $media['gallery_ids'] ) && is_array( $media['gallery_ids'] )
        ? array_values( array_map( 'intval', $media['gallery_ids'] ) )
        : [];
    $ordered_media_ids = array_values( array_filter( array_merge( [ $featured_id ], $gallery_ids ) ) );

    if ( empty( $ordered_media_ids ) ) {
        return [
            'changed'             => false,
            'links_removed'       => 0,
            'attachments_deleted' => 0,
        ];
    }

    $key_to_keep_id = [];
    $keep_profiles = [];
    $keep_ordered_ids = [];
    $duplicate_ids = [];
    $duplicate_to_keep = [];

    foreach ( $ordered_media_ids as $attachment_id ) {
        $attachment_id = (int) $attachment_id;
        if ( ! $attachment_id ) {
            continue;
        }

        $profile = shk_parser_attachment_dedupe_profile( $attachment_id );
        $keys = (array) ( $profile['keys'] ?? [] );
        $matched = false;
        $matched_keep_id = 0;
        foreach ( $keys as $key ) {
            if ( isset( $key_to_keep_id[ $key ] ) ) {
                $matched = true;
                $matched_keep_id = (int) $key_to_keep_id[ $key ];
                break;
            }
        }

        if ( ! $matched ) {
            foreach ( $keep_profiles as $keep_profile ) {
                if ( shk_parser_attachment_profiles_similar( $profile, $keep_profile ) ) {
                    $matched = true;
                    $matched_keep_id = (int) ( $keep_profile['attachment_id'] ?? 0 );
                    break;
                }
            }
        }

        if ( $matched ) {
            $duplicate_ids[] = $attachment_id;
            if ( $matched_keep_id ) {
                $duplicate_to_keep[ $attachment_id ] = $matched_keep_id;
            }
            continue;
        }

        foreach ( $keys as $key ) {
            $key_to_keep_id[ $key ] = $attachment_id;
        }
        $profile['attachment_id'] = $attachment_id;
        $keep_profiles[] = $profile;
        $keep_ordered_ids[] = $attachment_id;
    }

    $new_featured_id = $featured_id;
    if ( $featured_id ) {
        $featured_match_id = (int) ( $duplicate_to_keep[ $featured_id ] ?? 0 );
        if ( ! $featured_match_id ) {
            foreach ( shk_parser_attachment_dedupe_keys( $featured_id ) as $featured_key ) {
                if ( isset( $key_to_keep_id[ $featured_key ] ) ) {
                    $featured_match_id = (int) $key_to_keep_id[ $featured_key ];
                    break;
                }
            }
        }
        if ( $featured_match_id ) {
            $new_featured_id = $featured_match_id;
        } elseif ( ! in_array( $featured_id, $keep_ordered_ids, true ) ) {
            $new_featured_id = 0;
        }
    }

    $new_gallery_ids = $keep_ordered_ids;
    if ( $new_featured_id ) {
        $new_gallery_ids = array_values( array_filter( $new_gallery_ids, static function ( $id ) use ( $new_featured_id ) {
            return (int) $id !== (int) $new_featured_id;
        } ) );
    }

    // Safety net: never leave a product without media if it had media before cleanup.
    if ( ! $new_featured_id && empty( $new_gallery_ids ) && ! empty( $ordered_media_ids ) ) {
        $fallback_featured_id = (int) $ordered_media_ids[0];
        if ( $fallback_featured_id > 0 ) {
            $new_featured_id = $fallback_featured_id;
            $new_gallery_ids = array_values(
                array_filter(
                    array_map( 'intval', $new_gallery_ids ),
                    static function ( $id ) use ( $new_featured_id ) {
                        return (int) $id !== (int) $new_featured_id;
                    }
                )
            );
        }
    }

    $changed = false;
    if ( $new_featured_id !== $featured_id ) {
        $changed = true;
        if ( $new_featured_id ) {
            set_post_thumbnail( $product_id, $new_featured_id );
        } else {
            delete_post_thumbnail( $product_id );
        }
    }

    if ( $new_gallery_ids !== $gallery_ids ) {
        $changed = true;
        update_post_meta( $product_id, '_product_image_gallery', implode( ',', $new_gallery_ids ) );
    }

    // Re-check current state after updating featured/gallery.
    // If WP did not apply links as expected, protect attachments from deletion.
    $current_media = shk_parser_get_valid_product_media( $product_id );
    $current_featured_id = (int) ( $current_media['featured_id'] ?? 0 );
    $current_gallery_ids = isset( $current_media['gallery_ids'] ) && is_array( $current_media['gallery_ids'] )
        ? array_values( array_map( 'intval', $current_media['gallery_ids'] ) )
        : [];
    $current_media_ids = array_values(
        array_unique(
            array_filter(
                array_merge( [ $current_featured_id ], $current_gallery_ids )
            )
        )
    );

    $attachments_deleted = 0;
    $duplicate_ids = array_values( array_unique( array_map( 'intval', $duplicate_ids ) ) );
    if ( ! empty( $current_media_ids ) ) {
        $duplicate_ids = array_values( array_diff( $duplicate_ids, $current_media_ids ) );
    } else {
        // Safety net: if product ended up without valid media links, do not delete files.
        $duplicate_ids = [];
    }
    foreach ( $duplicate_ids as $duplicate_id ) {
        if ( ! $duplicate_id ) {
            continue;
        }
        if ( shk_parser_delete_stale_attachment_if_unused( $duplicate_id, $product_id ) ) {
            $attachments_deleted++;
        }
    }

    return [
        'changed'             => $changed,
        'links_removed'       => max( 0, count( $ordered_media_ids ) - count( $keep_ordered_ids ) ),
        'attachments_deleted' => $attachments_deleted,
    ];
}

function shk_parser_attachment_dedupe_keys( $attachment_id ) {
    static $id_cache = [];
    static $path_hash_cache = [];
    static $path_ahash_cache = [];

    $attachment_id = (int) $attachment_id;
    if ( ! $attachment_id ) {
        return [];
    }

    if ( isset( $id_cache[ $attachment_id ] ) ) {
        return (array) $id_cache[ $attachment_id ];
    }

    $keys = [];
    $file_path = get_attached_file( $attachment_id );
    if ( is_string( $file_path ) && '' !== $file_path && file_exists( $file_path ) ) {
        $file_hash = '';
        if ( isset( $path_hash_cache[ $file_path ] ) ) {
            $file_hash = (string) $path_hash_cache[ $file_path ];
        } else {
            $file_hash = (string) md5_file( $file_path );
            $path_hash_cache[ $file_path ] = $file_hash;
        }

        if ( '' !== $file_hash ) {
            $keys[] = 'md5:' . $file_hash;
        }

        $ahash = '';
        if ( isset( $path_ahash_cache[ $file_path ] ) ) {
            $ahash = (string) $path_ahash_cache[ $file_path ];
        } else {
            $ahash = shk_parser_image_ahash( $file_path );
            $path_ahash_cache[ $file_path ] = $ahash;
        }
        if ( '' !== $ahash ) {
            $keys[] = 'ahash:' . $ahash;
        }

        $basename = (string) wp_basename( $file_path );
        $basename_lc = strtolower( $basename );
        $name_no_ext = preg_replace( '/\.[^.]+$/', '', $basename_lc );
        $name_norm = preg_replace( '/-(\d+x\d+|scaled|rotated|e\d+|\d+)$/', '', (string) $name_no_ext );
        if ( '' !== $name_norm ) {
            $meta = wp_get_attachment_metadata( $attachment_id );
            $w = isset( $meta['width'] ) ? (int) $meta['width'] : 0;
            $h = isset( $meta['height'] ) ? (int) $meta['height'] : 0;
            $keys[] = 'name_dim:' . $name_norm . ':' . $w . 'x' . $h;
        }

        $filesize = (int) filesize( $file_path );
        if ( '' !== $basename_lc && $filesize > 0 ) {
            $keys[] = 'file:' . $basename_lc . ':' . $filesize;
        }
    }

    $source_key = trim( (string) get_post_meta( $attachment_id, '_shk_source_key', true ) );
    if ( '' === $source_key ) {
        $source_url = trim( (string) get_post_meta( $attachment_id, '_shk_source_url', true ) );
        if ( '' !== $source_url ) {
            $source_key = shk_parser_media_source_key( $source_url );
            if ( '' !== $source_key ) {
                update_post_meta( $attachment_id, '_shk_source_key', $source_key );
            }
        }
    }
    if ( '' !== $source_key ) {
        $keys[] = 'src:' . $source_key;
    }

    if ( empty( $keys ) ) {
        $keys[] = 'id:' . $attachment_id;
    }

    $keys = array_values( array_unique( array_filter( array_map( 'strval', $keys ) ) ) );
    $id_cache[ $attachment_id ] = $keys;
    return $keys;
}

function shk_parser_attachment_dedupe_profile( $attachment_id ) {
    $attachment_id = (int) $attachment_id;
    if ( ! $attachment_id ) {
        return [
            'attachment_id' => 0,
            'keys'          => [],
            'ahash'         => '',
            'width'         => 0,
            'height'        => 0,
        ];
    }

    $keys = shk_parser_attachment_dedupe_keys( $attachment_id );
    $ahash = '';
    foreach ( $keys as $key ) {
        if ( 0 === strpos( $key, 'ahash:' ) ) {
            $ahash = substr( $key, 6 );
            break;
        }
    }

    $meta = wp_get_attachment_metadata( $attachment_id );
    $width = isset( $meta['width'] ) ? (int) $meta['width'] : 0;
    $height = isset( $meta['height'] ) ? (int) $meta['height'] : 0;

    if ( ( ! $width || ! $height ) && function_exists( 'get_attached_file' ) ) {
        $file_path = get_attached_file( $attachment_id );
        if ( is_string( $file_path ) && '' !== $file_path && file_exists( $file_path ) ) {
            $img_size = @getimagesize( $file_path );
            if ( is_array( $img_size ) ) {
                $width = $width ?: (int) ( $img_size[0] ?? 0 );
                $height = $height ?: (int) ( $img_size[1] ?? 0 );
            }
        }
    }

    return [
        'attachment_id' => $attachment_id,
        'keys'          => array_values( array_unique( array_filter( array_map( 'strval', (array) $keys ) ) ) ),
        'ahash'         => (string) $ahash,
        'width'         => max( 0, (int) $width ),
        'height'        => max( 0, (int) $height ),
    ];
}

function shk_parser_ahash_hamming_distance( $left_hash, $right_hash ) {
    $left_hash = strtolower( trim( (string) $left_hash ) );
    $right_hash = strtolower( trim( (string) $right_hash ) );
    if ( '' === $left_hash || '' === $right_hash || strlen( $left_hash ) !== strlen( $right_hash ) ) {
        return 64;
    }

    static $bit_counts = null;
    if ( null === $bit_counts ) {
        $bit_counts = [
            0, 1, 1, 2, 1, 2, 2, 3, 1, 2, 2, 3, 2, 3, 3, 4,
        ];
    }

    $distance = 0;
    $length = strlen( $left_hash );
    for ( $i = 0; $i < $length; $i++ ) {
        $l = hexdec( $left_hash[ $i ] );
        $r = hexdec( $right_hash[ $i ] );
        $distance += (int) $bit_counts[ $l ^ $r ];
    }

    return (int) $distance;
}

function shk_parser_attachment_profiles_similar( array $left, array $right ) {
    $left_keys = array_values( array_filter( array_map( 'strval', (array) ( $left['keys'] ?? [] ) ) ) );
    $right_keys = array_values( array_filter( array_map( 'strval', (array) ( $right['keys'] ?? [] ) ) ) );
    if ( ! empty( array_intersect( $left_keys, $right_keys ) ) ) {
        return true;
    }

    $left_ahash = (string) ( $left['ahash'] ?? '' );
    $right_ahash = (string) ( $right['ahash'] ?? '' );
    if ( '' === $left_ahash || '' === $right_ahash ) {
        return false;
    }

    $distance = shk_parser_ahash_hamming_distance( $left_ahash, $right_ahash );
    if ( $distance <= 3 ) {
        return true;
    }

    if ( $distance <= 8 ) {
        $lw = max( 0, (int) ( $left['width'] ?? 0 ) );
        $lh = max( 0, (int) ( $left['height'] ?? 0 ) );
        $rw = max( 0, (int) ( $right['width'] ?? 0 ) );
        $rh = max( 0, (int) ( $right['height'] ?? 0 ) );

        if ( $lw > 0 && $lh > 0 && $rw > 0 && $rh > 0 ) {
            $l_ratio = $lw / $lh;
            $r_ratio = $rw / $rh;
            $ratio_delta = abs( $l_ratio - $r_ratio );
            if ( $ratio_delta <= 0.03 ) {
                return true;
            }
        }
    }

    return false;
}

function shk_parser_image_ahash( $file_path ) {
    if ( ! is_string( $file_path ) || '' === $file_path || ! file_exists( $file_path ) ) {
        return '';
    }
    if ( ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagecopyresampled' ) ) {
        return '';
    }

    $image = null;
    $mime = function_exists( 'mime_content_type' ) ? (string) mime_content_type( $file_path ) : '';
    if ( 'image/jpeg' === $mime || 'image/jpg' === $mime ) {
        if ( function_exists( 'imagecreatefromjpeg' ) ) {
            $image = @imagecreatefromjpeg( $file_path );
        }
    } elseif ( 'image/png' === $mime ) {
        if ( function_exists( 'imagecreatefrompng' ) ) {
            $image = @imagecreatefrompng( $file_path );
        }
    } elseif ( 'image/webp' === $mime ) {
        if ( function_exists( 'imagecreatefromwebp' ) ) {
            $image = @imagecreatefromwebp( $file_path );
        }
    } elseif ( 'image/gif' === $mime ) {
        if ( function_exists( 'imagecreatefromgif' ) ) {
            $image = @imagecreatefromgif( $file_path );
        }
    } elseif ( function_exists( 'imagecreatefromstring' ) ) {
        $raw = @file_get_contents( $file_path );
        if ( false !== $raw ) {
            $image = @imagecreatefromstring( $raw );
        }
    }

    if ( ! $image ) {
        return '';
    }

    $thumb = imagecreatetruecolor( 8, 8 );
    if ( ! $thumb ) {
        imagedestroy( $image );
        return '';
    }

    imagecopyresampled(
        $thumb,
        $image,
        0,
        0,
        0,
        0,
        8,
        8,
        imagesx( $image ),
        imagesy( $image )
    );

    $values = [];
    $sum = 0;
    for ( $y = 0; $y < 8; $y++ ) {
        for ( $x = 0; $x < 8; $x++ ) {
            $rgb = imagecolorat( $thumb, $x, $y );
            $r = ( $rgb >> 16 ) & 0xFF;
            $g = ( $rgb >> 8 ) & 0xFF;
            $b = $rgb & 0xFF;
            $gray = (int) round( 0.299 * $r + 0.587 * $g + 0.114 * $b );
            $values[] = $gray;
            $sum += $gray;
        }
    }

    $avg = $sum / 64;
    $bits = '';
    foreach ( $values as $value ) {
        $bits .= ( $value >= $avg ) ? '1' : '0';
    }

    $hex = '';
    for ( $i = 0; $i < 64; $i += 4 ) {
        $hex .= dechex( bindec( substr( $bits, $i, 4 ) ) );
    }

    imagedestroy( $thumb );
    imagedestroy( $image );

    return $hex;
}

function shk_parser_attachment_dedupe_key( $attachment_id ) {
    $keys = shk_parser_attachment_dedupe_keys( $attachment_id );
    if ( empty( $keys ) ) {
        return '';
    }
    foreach ( $keys as $key ) {
        if ( 0 === strpos( $key, 'md5:' ) || 0 === strpos( $key, 'src:' ) ) {
            return $key;
        }
    }
    return (string) $keys[0];
}

function shk_parser_import_media_row( array $row, $run_id ) {
    $source_slug = trim( (string) ( $row['slug'] ?? ( $row['product_slug'] ?? '' ) ) );
    if ( '' === $source_slug ) {
        return new WP_Error( 'missing_slug', 'Пустой slug для медиа.' );
    }

    $product_id = shk_parser_find_product_id_by_source_slug( $source_slug );
    if ( ! $product_id ) {
        return new WP_Error( 'product_missing', 'Сначала импортируйте карточки: ' . $source_slug );
    }

    // Support two input schemas:
    // 1) products.csv row: slug + photos/videos (pipe-separated)
    // 2) media.csv row: product_slug + type + url (single media item)
    $is_single_media_item_row = isset( $row['type'] ) || isset( $row['url'] );
    if ( $is_single_media_item_row ) {
        $media_type = strtolower( trim( (string) ( $row['type'] ?? '' ) ) );
        $single_url = shk_parser_normalize_media_url( (string) ( $row['url'] ?? '' ), $row );
        $photo_urls = ( 'photo' === $media_type && '' !== $single_url ) ? [ $single_url ] : [];
        $video_urls = ( 'video' === $media_type && '' !== $single_url ) ? [ $single_url ] : [];
    } else {
        $photo_urls = shk_parser_parse_pipe_list( (string) ( $row['photos'] ?? '' ) );
        $video_urls = shk_parser_parse_pipe_list( (string) ( $row['videos'] ?? '' ) );
    }

    $photo_urls = array_values(
        array_filter(
            array_map(
                static function ( $url ) use ( $row ) {
                    return shk_parser_normalize_media_url( $url, $row );
                },
                $photo_urls
            )
        )
    );

    $video_urls = array_values(
        array_filter(
            array_map(
                static function ( $url ) use ( $row ) {
                    return shk_parser_normalize_media_url( $url, $row );
                },
                $video_urls
            )
        )
    );

    $attachment_ids = [];
    $failed_urls = [];
    foreach ( $photo_urls as $url ) {
        $attachment_id = shk_parser_sideload_attachment( $url, $product_id );
        if ( is_wp_error( $attachment_id ) ) {
            $failed_urls[] = $url . ' (' . $attachment_id->get_error_message() . ')';
            continue;
        }
        if ( $attachment_id ) {
            $attachment_ids[] = (int) $attachment_id;
        }
    }

    $attachment_ids = array_values( array_unique( $attachment_ids ) );
    if ( empty( $attachment_ids ) && ! empty( $failed_urls ) ) {
        return new WP_Error( 'media_failed', 'Не удалось загрузить медиа. Пример: ' . $failed_urls[0] );
    }

    $deleted_stale = 0;

    if ( $is_single_media_item_row ) {
        // Single-item rows (media.csv): do safe incremental merge, no destructive replace.
        if ( ! empty( $attachment_ids ) ) {
            $current_featured = (int) get_post_thumbnail_id( $product_id );
            $current_gallery_ids = shk_parser_get_product_gallery_ids( $product_id );

            if ( ! $current_featured ) {
                set_post_thumbnail( $product_id, (int) $attachment_ids[0] );
                $to_gallery = array_slice( $attachment_ids, 1 );
            } else {
                $to_gallery = $attachment_ids;
                if ( $current_featured === (int) $attachment_ids[0] ) {
                    $to_gallery = array_slice( $attachment_ids, 1 );
                }
            }

            if ( ! empty( $to_gallery ) ) {
                $merged_gallery = array_values( array_unique( array_merge( $current_gallery_ids, array_map( 'intval', $to_gallery ) ) ) );
                update_post_meta( $product_id, '_product_image_gallery', implode( ',', $merged_gallery ) );
            }
        }
    } else {
        // Full-row media payload (products.csv): strict 1:1 replacement.
        $current_featured = (int) get_post_thumbnail_id( $product_id );
        $current_gallery_ids = shk_parser_get_product_gallery_ids( $product_id );
        $current_media_ids = array_values( array_unique( array_filter( array_merge( [ $current_featured ], $current_gallery_ids ) ) ) );

        $desired_featured = ! empty( $attachment_ids ) ? (int) $attachment_ids[0] : 0;
        $desired_gallery_ids = ! empty( $attachment_ids ) ? array_values( array_map( 'intval', array_slice( $attachment_ids, 1 ) ) ) : [];
        $desired_media_ids = array_values( array_unique( array_filter( array_merge( [ $desired_featured ], $desired_gallery_ids ) ) ) );

        if ( $desired_featured ) {
            set_post_thumbnail( $product_id, $desired_featured );
        } else {
            delete_post_thumbnail( $product_id );
        }

        update_post_meta( $product_id, '_product_image_gallery', implode( ',', $desired_gallery_ids ) );

        $stale_ids = array_values( array_diff( $current_media_ids, $desired_media_ids ) );
        foreach ( $stale_ids as $stale_id ) {
            if ( shk_parser_delete_stale_attachment_if_unused( (int) $stale_id, (int) $product_id ) ) {
                $deleted_stale++;
            }
        }
    }

    update_post_meta( $product_id, '_shk_photo_urls', $photo_urls );
    update_post_meta( $product_id, '_shk_video_urls', $video_urls );
    update_post_meta( $product_id, '_shk_media_import_run_id', $run_id );

    return [
        'product_id'      => (int) $product_id,
        'attachment_ids'  => $attachment_ids,
        'video_urls'      => $video_urls,
        'stale_removed'   => $deleted_stale,
    ];
}

function shk_parser_finalize_related_links( $run_id, array &$job ) {
    $run = shk_parser_get_run( $run_id );
    $related_path = $run['files']['related'] ?? '';

    if ( ! $related_path || ! file_exists( $related_path ) ) {
        shk_parser_append_import_log( $job, 'related.csv не найден, финализация related пропущена.' );
        return;
    }

    $rows = shk_parser_read_csv_assoc( $related_path );
    $bucket = [];

    foreach ( $rows as $row ) {
        $product_slug = trim( (string) ( $row['product_slug'] ?? '' ) );
        $related_slug = trim( (string) ( $row['related_slug'] ?? '' ) );
        if ( '' === $product_slug || '' === $related_slug ) {
            continue;
        }

        $product_id = shk_parser_find_product_id_by_source_slug( $product_slug );
        $related_id = shk_parser_find_product_id_by_source_slug( $related_slug );

        if ( ! $product_id || ! $related_id || $product_id === $related_id ) {
            continue;
        }

        if ( empty( $bucket[ $product_id ] ) ) {
            $bucket[ $product_id ] = [];
        }
        $bucket[ $product_id ][] = (int) $related_id;
    }

    foreach ( $bucket as $product_id => $related_ids ) {
        $related_ids = array_values( array_unique( array_filter( array_map( 'intval', $related_ids ) ) ) );
        update_post_meta( $product_id, '_shk_related_ids', $related_ids );

        $product = wc_get_product( $product_id );
        if ( $product && method_exists( $product, 'set_upsell_ids' ) ) {
            $product->set_upsell_ids( $related_ids );
            $product->save();
        }
    }

    shk_parser_append_import_log( $job, 'Related-связи синхронизированы: ' . count( $bucket ) . ' товаров.' );
}

function shk_parser_prepare_remote_run_for_import( $run_id, $type ) {
    $run = shk_parser_get_run( $run_id );
    if ( ! empty( $run ) && 'local' === ( $run['source'] ?? '' ) ) {
        return true;
    }

    if ( ! function_exists( 'shk_parser_remote_enabled' ) || ! shk_parser_remote_enabled() ) {
        return true;
    }

    $required = [ 'summary', 'products' ];
    if ( 'products' === $type ) {
        $required[] = 'related';
    } elseif ( 'media' === $type ) {
        $required[] = 'media';
    }

    foreach ( $required as $name ) {
        $result = shk_parser_remote_download_run_file( $run_id, $name );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
    }

    return true;
}

function shk_parser_start_import_job( $type, $run_id, array $args = [] ) {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return new WP_Error( 'woocommerce_missing', 'WooCommerce не активирован.' );
    }

    $parser_job = shk_parser_get_current_job();
    if ( ! empty( $parser_job ) && 'running' === ( $parser_job['status'] ?? '' ) ) {
        return new WP_Error( 'parser_job_running', 'Сначала дождитесь завершения текущего парсинга.' );
    }

    $existing = shk_parser_get_import_job();
    if ( ! empty( $existing ) && 'running' === ( $existing['status'] ?? '' ) ) {
        return new WP_Error( 'import_job_running', 'Сейчас уже выполняется другая Woo-задача импорта.' );
    }

    $rows = [];
    $run = [];
    $import_path = '';
    $cleanup_product_ids = [];
    $target_source_slugs = array_values(
        array_unique(
            array_filter(
                array_map(
                    static function ( $slug ) {
                        return trim( (string) $slug );
                    },
                    (array) ( $args['target_source_slugs'] ?? [] )
                )
            )
        )
    );

    if ( 'media_cleanup' === $type ) {
        $cleanup_product_ids = shk_parser_collect_products_for_media_cleanup();
        $rows = $cleanup_product_ids;
    } else {
        $prepared = shk_parser_prepare_remote_run_for_import( $run_id, $type );
        if ( is_wp_error( $prepared ) ) {
            return $prepared;
        }

        $run = shk_parser_get_run( $run_id );
        if ( empty( $run ) ) {
            return new WP_Error( 'run_missing', 'Run не найден.' );
        }

        $import_path = 'media' === $type
            ? ( $run['files']['media'] ?? '' )
            : ( $run['files']['products'] ?? '' );

        if ( ! $import_path || ! file_exists( $import_path ) ) {
            if ( 'media' === $type ) {
                return new WP_Error( 'media_missing', 'media.csv не найден для выбранного run.' );
            }
            return new WP_Error( 'products_missing', 'products.csv не найден для выбранного run.' );
        }

        $rows = shk_parser_read_csv_assoc( $import_path );

        if ( 'media' === $type && ! empty( $target_source_slugs ) ) {
            $lookup = array_fill_keys( $target_source_slugs, true );
            $rows = array_values(
                array_filter(
                    $rows,
                    static function ( $row ) use ( $lookup ) {
                        if ( ! is_array( $row ) ) {
                            return false;
                        }
                        $source_slug = shk_parser_media_row_source_slug( $row );
                        return '' !== $source_slug && isset( $lookup[ $source_slug ] );
                    }
                )
            );
        }
    }

    $job_title = (string) ( $args['title'] ?? '' );
    if ( '' === $job_title ) {
        $job_title = 'products' === $type
            ? 'Импорт карточек Woo'
            : ( 'media' === $type ? 'Догрузка медиа Woo' : 'Очистка дублей медиа Woo' );
    }

    $job = [
        'job_id'      => wp_generate_uuid4(),
        'lock_token'  => wp_generate_uuid4(),
        'title'       => $job_title,
        'type'        => $type,
        'run_id'      => $run_id,
        'status'      => 'running',
        'offset'      => 0,
        'processed'   => 0,
        'total'       => count( $rows ),
        'created'     => 0,
        'updated'     => 0,
        'failed'      => 0,
        'media_rows_ok'      => 0,
        'media_photos_added' => 0,
        'media_videos_linked'=> 0,
        'media_stale_removed'=> 0,
        'cleanup_products_changed' => 0,
        'cleanup_links_removed'    => 0,
        'cleanup_attachments_deleted' => 0,
        'product_ids'  => 'media_cleanup' === $type ? $cleanup_product_ids : [],
        'target_source_slugs' => ( 'media' === $type && ! empty( $target_source_slugs ) ) ? $target_source_slugs : [],
        'started_at'  => current_time( 'mysql' ),
        'finished_at' => '',
        'logs'        => [],
    ];

    shk_parser_append_import_log( $job, 'Задача запущена для run ' . $run_id );
    if ( 'media' === $type && ! empty( $target_source_slugs ) ) {
        shk_parser_append_import_log(
            $job,
            sprintf(
                'Режим только проблемных: выбрано source_slug %d, строк media.csv к обработке %d.',
                count( $target_source_slugs ),
                count( $rows )
            )
        );
    }
    return shk_parser_save_import_job( $job );
}

function shk_parser_process_import_job_chunk( $limit = 15 ) {
    $job = shk_parser_get_import_job();
    if ( empty( $job ) || 'running' !== ( $job['status'] ?? '' ) ) {
        return $job;
    }
    $job_id = (string) ( $job['job_id'] ?? '' );
    $job_lock_token = (string) ( $job['lock_token'] ?? $job_id );

    if ( 'media_cleanup' === ( $job['type'] ?? '' ) ) {
        if ( ! isset( $job['cleanup_products_changed'] ) ) {
            $job['cleanup_products_changed'] = 0;
        }
        if ( ! isset( $job['cleanup_links_removed'] ) ) {
            $job['cleanup_links_removed'] = 0;
        }
        if ( ! isset( $job['cleanup_attachments_deleted'] ) ) {
            $job['cleanup_attachments_deleted'] = 0;
        }

        $product_ids = array_values( array_map( 'intval', (array) ( $job['product_ids'] ?? [] ) ) );
        $slice = array_slice( $product_ids, (int) $job['offset'], (int) $limit );

        foreach ( $slice as $product_id ) {
            $latest_before_row = shk_parser_get_import_job();
            $latest_before_row_token = (string) ( $latest_before_row['lock_token'] ?? ( $latest_before_row['job_id'] ?? '' ) );
            if ( empty( $latest_before_row ) || 'running' !== ( $latest_before_row['status'] ?? '' ) || $latest_before_row_token !== $job_lock_token ) {
                return $latest_before_row;
            }

            try {
                $result = shk_parser_cleanup_product_media_duplicates( (int) $product_id );
                if ( ! empty( $result['changed'] ) ) {
                    $job['cleanup_products_changed']++;
                }
                $job['cleanup_links_removed'] += (int) ( $result['links_removed'] ?? 0 );
                $job['cleanup_attachments_deleted'] += (int) ( $result['attachments_deleted'] ?? 0 );
            } catch ( Throwable $e ) {
                $job['failed']++;
                shk_parser_append_import_log( $job, 'Ошибка cleanup товара #' . (int) $product_id . ': ' . $e->getMessage() );
            }

            $job['processed']++;
            $job['offset']++;
        }

        if ( (int) $job['offset'] >= (int) $job['total'] ) {
            $job['status'] = 'done';
            $job['finished_at'] = current_time( 'mysql' );
            shk_parser_append_import_log(
                $job,
                sprintf(
                    'Cleanup-итоги: обработано товаров %d, изменено %d, убрано дублей-ссылок %d, удалено вложений %d.',
                    (int) ( $job['processed'] ?? 0 ),
                    (int) ( $job['cleanup_products_changed'] ?? 0 ),
                    (int) ( $job['cleanup_links_removed'] ?? 0 ),
                    (int) ( $job['cleanup_attachments_deleted'] ?? 0 )
                )
            );
            shk_parser_append_import_log( $job, 'Задача завершена.' );
        }

        $latest = shk_parser_get_import_job();
        if ( ! empty( $latest ) ) {
            $latest_job_token = (string) ( $latest['lock_token'] ?? ( $latest['job_id'] ?? '' ) );
            $latest_status = (string) ( $latest['status'] ?? '' );
            if ( $latest_job_token !== $job_lock_token || 'running' !== $latest_status ) {
                return $latest;
            }
        }

        return shk_parser_save_import_job( $job );
    }

    $run = shk_parser_get_run( $job['run_id'] ?? '' );
    $import_path = 'media' === ( $job['type'] ?? '' )
        ? ( $run['files']['media'] ?? '' )
        : ( $run['files']['products'] ?? '' );

    if ( empty( $run ) || ! $import_path || ! file_exists( $import_path ) ) {
        $job['status'] = 'failed';
        $job['finished_at'] = current_time( 'mysql' );
        shk_parser_append_import_log( $job, ( 'media' === ( $job['type'] ?? '' ) ? 'media.csv' : 'products.csv' ) . ' недоступен, задача остановлена.' );
        return shk_parser_save_import_job( $job );
    }

    if ( 'media' === ( $job['type'] ?? '' ) ) {
        if ( ! isset( $job['media_rows_ok'] ) ) {
            $job['media_rows_ok'] = 0;
        }
        if ( ! isset( $job['media_photos_added'] ) ) {
            $job['media_photos_added'] = 0;
        }
        if ( ! isset( $job['media_videos_linked'] ) ) {
            $job['media_videos_linked'] = 0;
        }
        if ( ! isset( $job['media_stale_removed'] ) ) {
            $job['media_stale_removed'] = 0;
        }
    }

    $rows = shk_parser_read_csv_assoc( $import_path );
    if ( 'media' === ( $job['type'] ?? '' ) ) {
        $target_source_slugs = array_values(
            array_unique(
                array_filter(
                    array_map(
                        static function ( $slug ) {
                            return trim( (string) $slug );
                        },
                        (array) ( $job['target_source_slugs'] ?? [] )
                    )
                )
            )
        );
        if ( ! empty( $target_source_slugs ) ) {
            $lookup = array_fill_keys( $target_source_slugs, true );
            $rows = array_values(
                array_filter(
                    $rows,
                    static function ( $row ) use ( $lookup ) {
                        if ( ! is_array( $row ) ) {
                            return false;
                        }
                        $source_slug = shk_parser_media_row_source_slug( $row );
                        return '' !== $source_slug && isset( $lookup[ $source_slug ] );
                    }
                )
            );
            // Keep total consistent with the filtered rows during long-running ticks.
            $job['total'] = count( $rows );
        }
    }
    $slice = array_slice( $rows, (int) $job['offset'], (int) $limit );

    foreach ( $slice as $row ) {
        // If the job was stopped/replaced while this tick is running, abort immediately.
        $latest_before_row = shk_parser_get_import_job();
        $latest_before_row_token = (string) ( $latest_before_row['lock_token'] ?? ( $latest_before_row['job_id'] ?? '' ) );
        if ( empty( $latest_before_row ) || 'running' !== ( $latest_before_row['status'] ?? '' ) || $latest_before_row_token !== $job_lock_token ) {
            return $latest_before_row;
        }

        try {
            if ( 'products' === $job['type'] ) {
                $result = shk_parser_import_product_row( $row, $job['run_id'] );
                if ( is_wp_error( $result ) ) {
                    $job['failed']++;
                    shk_parser_append_import_log( $job, 'Ошибка карточки ' . ( $row['slug'] ?? '' ) . ': ' . $result->get_error_message() );
                } else {
                    if ( ! empty( $result['created'] ) ) {
                        $job['created']++;
                    } else {
                        $job['updated']++;
                    }
                }
            } else {
                $result = shk_parser_import_media_row( $row, $job['run_id'] );
                if ( is_wp_error( $result ) ) {
                    $job['failed']++;
                    $job_slug = (string) ( $row['slug'] ?? ( $row['product_slug'] ?? '' ) );
                    shk_parser_append_import_log( $job, 'Ошибка медиа ' . $job_slug . ': ' . $result->get_error_message() );
                } else {
                    $job['media_rows_ok']++;
                    $job['media_photos_added'] += count( (array) ( $result['attachment_ids'] ?? [] ) );
                    $job['media_videos_linked'] += count( (array) ( $result['video_urls'] ?? [] ) );
                    $job['media_stale_removed'] += (int) ( $result['stale_removed'] ?? 0 );
                }
            }
        } catch ( Throwable $e ) {
            $job['failed']++;
            $job_slug = (string) ( $row['slug'] ?? ( $row['product_slug'] ?? '' ) );
            shk_parser_append_import_log( $job, 'Исключение ' . $job_slug . ': ' . $e->getMessage() );
        }

        $job['processed']++;
        $job['offset']++;
    }

    if ( (int) $job['offset'] >= (int) $job['total'] ) {
        if ( 'products' === $job['type'] ) {
            shk_parser_finalize_related_links( $job['run_id'], $job );
            shk_parser_finalize_products_1to1( $job['run_id'], $job );
        } elseif ( 'media' === $job['type'] ) {
            shk_parser_append_import_log(
                $job,
                sprintf(
                    'Медиа-итоги: успешных строк %d, фото прикреплено %d, видео-ссылок сохранено %d, удалено лишних медиа %d.',
                    (int) ( $job['media_rows_ok'] ?? 0 ),
                    (int) ( $job['media_photos_added'] ?? 0 ),
                    (int) ( $job['media_videos_linked'] ?? 0 ),
                    (int) ( $job['media_stale_removed'] ?? 0 )
                )
            );
        }
        $job['status'] = 'done';
        $job['finished_at'] = current_time( 'mysql' );
        shk_parser_append_import_log( $job, 'Задача завершена.' );
    }

    // Concurrency guard: do not overwrite a job that was stopped/replaced while this tick was running.
    $latest = shk_parser_get_import_job();
    if ( ! empty( $latest ) ) {
        $latest_job_token = (string) ( $latest['lock_token'] ?? ( $latest['job_id'] ?? '' ) );
        $latest_status = (string) ( $latest['status'] ?? '' );
        if ( $latest_job_token !== $job_lock_token || 'running' !== $latest_status ) {
            return $latest;
        }
    }

    return shk_parser_save_import_job( $job );
}

add_filter(
    'shk_parser_state_payload',
    function ( $payload ) {
    $payload['woo_categories'] = shk_parser_get_product_cat_choices();
    $payload['category_map'] = shk_parser_get_category_map();
    $payload['import_job'] = shk_parser_get_import_job();
    $payload['existing_products'] = shk_parser_get_existing_products_stats();
        return $payload;
    }
);

add_action( 'wp_ajax_shk_parser_bind_existing_products', 'shk_parser_ajax_bind_existing_products' );
function shk_parser_ajax_bind_existing_products() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Недостаточно прав.' ], 403 );
    }
    check_ajax_referer( 'shk_parser_nonce', 'nonce' );

    $bound = shk_parser_bind_existing_products_by_slug();
    wp_send_json_success(
        [
            'bound'             => $bound,
            'existing_products' => shk_parser_get_existing_products_stats(),
        ]
    );
}

add_action( 'wp_ajax_shk_parser_repair_family_links', 'shk_parser_ajax_repair_family_links' );
function shk_parser_ajax_repair_family_links() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Недостаточно прав.' ], 403 );
    }
    check_ajax_referer( 'shk_parser_nonce', 'nonce' );

    $result = shk_parser_repair_color_family_links();
    wp_send_json_success(
        [
            'repair'            => $result,
            'existing_products' => shk_parser_get_existing_products_stats(),
        ]
    );
}

add_action( 'wp_ajax_shk_parser_repair_run_data', 'shk_parser_ajax_repair_run_data' );
function shk_parser_ajax_repair_run_data() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Недостаточно прав.' ], 403 );
    }
    check_ajax_referer( 'shk_parser_nonce', 'nonce' );

    $run_id = isset( $_POST['run_id'] ) ? sanitize_text_field( wp_unslash( $_POST['run_id'] ) ) : '';
    if ( '' === $run_id ) {
        wp_send_json_error( [ 'message' => 'Не передан run_id.' ], 400 );
    }

    $repair = shk_parser_repair_from_run( $run_id );
    if ( is_wp_error( $repair ) ) {
        wp_send_json_error( [ 'message' => $repair->get_error_message() ], 400 );
    }

    wp_send_json_success(
        [
            'repair'            => $repair,
            'existing_products' => shk_parser_get_existing_products_stats(),
        ]
    );
}

add_action( 'wp_ajax_shk_parser_save_category_map', 'shk_parser_ajax_save_category_map' );
function shk_parser_ajax_save_category_map() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Недостаточно прав.' ], 403 );
    }
    check_ajax_referer( 'shk_parser_nonce', 'nonce' );

    $mapping_json = isset( $_POST['mapping_json'] ) ? wp_unslash( $_POST['mapping_json'] ) : '{}';
    $mapping = json_decode( $mapping_json, true );
    if ( ! is_array( $mapping ) ) {
        wp_send_json_error( [ 'message' => 'Некорректный JSON сопоставлений.' ], 400 );
    }

    $saved = shk_parser_save_category_map( $mapping );
    wp_send_json_success( [ 'category_map' => $saved ] );
}

add_action( 'wp_ajax_shk_parser_auto_map_categories', 'shk_parser_ajax_auto_map_categories' );
function shk_parser_ajax_auto_map_categories() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Недостаточно прав.' ], 403 );
    }
    check_ajax_referer( 'shk_parser_nonce', 'nonce' );

    $saved = shk_parser_auto_map_categories_1to1();
    wp_send_json_success( [ 'category_map' => $saved ] );
}

add_action( 'wp_ajax_shk_parser_start_import_products', 'shk_parser_ajax_start_import_products' );
function shk_parser_ajax_start_import_products() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Недостаточно прав.' ], 403 );
    }
    check_ajax_referer( 'shk_parser_nonce', 'nonce' );

    $run_id = isset( $_POST['run_id'] ) ? sanitize_text_field( wp_unslash( $_POST['run_id'] ) ) : '';
    if ( '' === $run_id ) {
        wp_send_json_error( [ 'message' => 'Не передан run_id.' ], 400 );
    }

    $job = shk_parser_start_import_job( 'products', $run_id );
    if ( is_wp_error( $job ) ) {
        wp_send_json_error( [ 'message' => $job->get_error_message() ], 400 );
    }

    wp_send_json_success( [ 'import_job' => $job ] );
}

add_action( 'wp_ajax_shk_parser_start_import_media', 'shk_parser_ajax_start_import_media' );
function shk_parser_ajax_start_import_media() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Недостаточно прав.' ], 403 );
    }
    check_ajax_referer( 'shk_parser_nonce', 'nonce' );

    $run_id = isset( $_POST['run_id'] ) ? sanitize_text_field( wp_unslash( $_POST['run_id'] ) ) : '';
    if ( '' === $run_id ) {
        wp_send_json_error( [ 'message' => 'Не передан run_id.' ], 400 );
    }

    $job = shk_parser_start_import_job( 'media', $run_id );
    if ( is_wp_error( $job ) ) {
        wp_send_json_error( [ 'message' => $job->get_error_message() ], 400 );
    }

    wp_send_json_success( [ 'import_job' => $job ] );
}

add_action( 'wp_ajax_shk_parser_start_import_media_broken', 'shk_parser_ajax_start_import_media_broken' );
function shk_parser_ajax_start_import_media_broken() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Недостаточно прав.' ], 403 );
    }
    check_ajax_referer( 'shk_parser_nonce', 'nonce' );

    $run_id = isset( $_POST['run_id'] ) ? sanitize_text_field( wp_unslash( $_POST['run_id'] ) ) : '';
    if ( '' === $run_id ) {
        wp_send_json_error( [ 'message' => 'Не передан run_id.' ], 400 );
    }

    $target_source_slugs = shk_parser_collect_existing_product_slugs( 'broken_media' );
    if ( empty( $target_source_slugs ) ) {
        wp_send_json_error( [ 'message' => 'Проблемные товары не найдены.' ], 400 );
    }

    $job = shk_parser_start_import_job(
        'media',
        $run_id,
        [
            'target_source_slugs' => $target_source_slugs,
            'title'               => 'Догрузка медиа Woo (только проблемные)',
        ]
    );
    if ( is_wp_error( $job ) ) {
        wp_send_json_error( [ 'message' => $job->get_error_message() ], 400 );
    }

    wp_send_json_success(
        [
            'import_job'      => $job,
            'selected_count'  => count( $target_source_slugs ),
        ]
    );
}

add_action( 'wp_ajax_shk_parser_start_media_cleanup', 'shk_parser_ajax_start_media_cleanup' );
function shk_parser_ajax_start_media_cleanup() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Недостаточно прав.' ], 403 );
    }
    check_ajax_referer( 'shk_parser_nonce', 'nonce' );

    $job = shk_parser_start_import_job( 'media_cleanup', '' );
    if ( is_wp_error( $job ) ) {
        wp_send_json_error( [ 'message' => $job->get_error_message() ], 400 );
    }

    wp_send_json_success( [ 'import_job' => $job ] );
}

add_action( 'wp_ajax_shk_parser_import_tick', 'shk_parser_ajax_import_tick' );
function shk_parser_ajax_import_tick() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Недостаточно прав.' ], 403 );
    }
    check_ajax_referer( 'shk_parser_nonce', 'nonce' );

    $job = shk_parser_process_import_job_chunk( 12 );
    wp_send_json_success( [ 'import_job' => $job ] );
}

add_action( 'wp_ajax_shk_parser_stop_import_job', 'shk_parser_ajax_stop_import_job' );
function shk_parser_ajax_stop_import_job() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Недостаточно прав.' ], 403 );
    }
    check_ajax_referer( 'shk_parser_nonce', 'nonce' );

    $job = shk_parser_get_import_job();
    if ( empty( $job ) || 'running' !== ( $job['status'] ?? '' ) ) {
        wp_send_json_error( [ 'message' => 'Нет активной Woo-задачи для остановки.' ], 400 );
    }

    $job['status'] = 'stopped';
    // Rotate lock so in-flight ticks cannot write stale progress back.
    $job['lock_token'] = wp_generate_uuid4();
    $job['finished_at'] = current_time( 'mysql' );
    shk_parser_append_import_log( $job, 'Задача остановлена пользователем.' );
    $job = shk_parser_save_import_job( $job );

    wp_send_json_success( [ 'import_job' => $job ] );
}
