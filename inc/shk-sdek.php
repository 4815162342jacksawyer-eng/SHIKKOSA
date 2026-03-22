<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * SHIKKOSA: CDEK pickup split logic
 *
 * Splits CDEK pickup rates into explicit variants:
 * - PVZ (MSK/MO) without fitting
 * - PVZ (MSK/MO) with fitting
 * - PVZ (RF) without fitting
 * - PVZ (RF) with fitting
 *
 * Keeps courier rates untouched.
 */

function shikkosa_sdek_settings_default() {
    return array(
        'enabled'            => 'yes',
        'cdek_door_door_label'   => '',
        'cdek_door_door_price'   => '',
        'cdek_door_door_price_comment' => '',
        'cdek_door_door_delivery_comment' => '',
        'cdek_door_warehouse_label'   => '',
        'cdek_door_warehouse_price'   => '',
        'cdek_door_warehouse_price_comment' => '',
        'cdek_door_warehouse_delivery_comment' => '',
        'cdek_pickup_label'   => '',
        'cdek_pickup_price'   => '',
        'cdek_pickup_price_comment' => '',
        'cdek_pickup_delivery_comment' => '',
        'cdek_express_door_door_label'   => '',
        'cdek_express_door_door_price'   => '',
        'cdek_express_door_door_price_comment' => '',
        'cdek_express_door_door_delivery_comment' => '',
        'msk_no_fit_label'   => 'СДЭК ПВЗ (МСК/МО, без примерки)',
        'msk_fit_label'      => 'СДЭК ПВЗ (МСК/МО, с примеркой)',
        'rf_no_fit_label'    => 'СДЭК ПВЗ (РФ, без примерки)',
        'rf_fit_label'       => 'СДЭК ПВЗ (РФ, с примеркой)',
        'msk_no_fit_price'   => '',
        'msk_fit_price'      => '',
        'rf_no_fit_price'    => '',
        'rf_fit_price'       => '',
        'msk_no_fit_price_comment' => '',
        'msk_fit_price_comment'    => '',
        'rf_no_fit_price_comment'  => '',
        'rf_fit_price_comment'     => '',
        'msk_no_fit_delivery_comment' => '',
        'msk_fit_delivery_comment'    => '',
        'rf_no_fit_delivery_comment'  => '',
        'rf_fit_delivery_comment'     => '',
        'msk_no_fit_extra'   => '0',
        'msk_fit_extra'      => '0',
        'rf_no_fit_extra'    => '0',
        'rf_fit_extra'       => '0',
    );
}

function shikkosa_sdek_settings() {
    $defaults = shikkosa_sdek_settings_default();
    $saved_raw = get_option( 'shikkosa_sdek_settings', null );

    if ( null === $saved_raw ) {
        return $defaults;
    }

    $saved = is_array( $saved_raw ) ? $saved_raw : array();
    $merged = wp_parse_args( $saved, $defaults );

    // Checkbox key is absent when unchecked -> treat as explicit "no".
    if ( ! array_key_exists( 'enabled', $saved ) ) {
        $merged['enabled'] = 'no';
    }

    return $merged;
}

function shikkosa_sdek_settings_sanitize( $input ) {
    $defaults = shikkosa_sdek_settings_default();
    $input = is_array( $input ) ? $input : array();

    $out = wp_parse_args( $input, $defaults );
    $out['enabled'] = ( isset( $input['enabled'] ) && 'yes' === (string) $input['enabled'] ) ? 'yes' : 'no';

    foreach ( array(
        'cdek_door_door_label',
        'cdek_door_warehouse_label',
        'cdek_pickup_label',
        'cdek_express_door_door_label',
        'msk_no_fit_label',
        'msk_fit_label',
        'rf_no_fit_label',
        'rf_fit_label',
    ) as $key ) {
        $value = isset( $input[ $key ] ) ? $input[ $key ] : $defaults[ $key ];
        $value = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : (string) $defaults[ $key ];
        $out[ $key ] = $value;
    }

    foreach ( array(
        'cdek_door_door_price',
        'cdek_door_warehouse_price',
        'cdek_pickup_price',
        'cdek_express_door_door_price',
        'msk_no_fit_price',
        'msk_fit_price',
        'rf_no_fit_price',
        'rf_fit_price',
    ) as $key ) {
        $value = isset( $input[ $key ] ) ? $input[ $key ] : '';
        if ( '' === trim( (string) $value ) ) {
            $out[ $key ] = '';
            continue;
        }
        $out[ $key ] = is_scalar( $value ) ? (string) wc_format_decimal( (string) $value ) : '';
    }

    foreach ( array(
        'cdek_door_door_price_comment',
        'cdek_door_warehouse_price_comment',
        'cdek_pickup_price_comment',
        'cdek_express_door_door_price_comment',
        'cdek_door_door_delivery_comment',
        'cdek_door_warehouse_delivery_comment',
        'cdek_pickup_delivery_comment',
        'cdek_express_door_door_delivery_comment',
        'msk_no_fit_price_comment',
        'msk_fit_price_comment',
        'rf_no_fit_price_comment',
        'rf_fit_price_comment',
        'msk_no_fit_delivery_comment',
        'msk_fit_delivery_comment',
        'rf_no_fit_delivery_comment',
        'rf_fit_delivery_comment',
    ) as $key ) {
        $value = isset( $input[ $key ] ) ? $input[ $key ] : '';
        $out[ $key ] = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
    }

    foreach ( array( 'msk_no_fit_extra', 'msk_fit_extra', 'rf_no_fit_extra', 'rf_fit_extra' ) as $key ) {
        $value = isset( $input[ $key ] ) ? $input[ $key ] : $defaults[ $key ];
        $out[ $key ] = is_scalar( $value ) ? (string) wc_format_decimal( (string) $value ) : (string) $defaults[ $key ];
    }

    return $out;
}

function shikkosa_sdek_rate_profile_code( $rate ) {
    $s = shikkosa_sdek_rate_string( $rate );
    if ( '' === $s ) {
        return '';
    }

    $is_express = ( false !== strpos( $s, 'экспресс' ) || false !== strpos( $s, 'express' ) );
    $has_door   = ( false !== strpos( $s, 'двер' ) || false !== strpos( $s, 'door' ) );
    $has_sklad  = ( false !== strpos( $s, 'склад' ) || false !== strpos( $s, 'warehouse' ) );
    $has_pickup = ( false !== strpos( $s, 'пвз' ) || false !== strpos( $s, 'пункт' ) || false !== strpos( $s, 'pickup' ) || false !== strpos( $s, 'самовывоз' ) );

    if ( $is_express && $has_door ) {
        return 'cdek_express_door_door';
    }
    if ( $has_door && $has_sklad ) {
        return 'cdek_door_warehouse';
    }
    if ( $has_door && false !== strpos( $s, 'двер-двер' ) ) {
        return 'cdek_door_door';
    }
    if ( $has_door && false !== strpos( $s, 'door-door' ) ) {
        return 'cdek_door_door';
    }
    if ( $has_pickup ) {
        return 'cdek_pickup';
    }
    if ( $has_door ) {
        return 'cdek_door_door';
    }

    return '';
}

function shikkosa_sdek_apply_profile_overrides( $rate, $settings, $profile ) {
    if ( ! $rate || ! is_a( $rate, 'WC_Shipping_Rate' ) || '' === $profile ) {
        return $rate;
    }

    $label_key = $profile . '_label';
    $price_key = $profile . '_price';
    $price_comment_key = $profile . '_price_comment';
    $delivery_comment_key = $profile . '_delivery_comment';

    $current_label = method_exists( $rate, 'get_label' ) ? (string) $rate->get_label() : '';
    $label = isset( $settings[ $label_key ] ) ? trim( (string) $settings[ $label_key ] ) : '';
    if ( '' === $label ) {
        $label = $current_label;
    }

    $current_cost = method_exists( $rate, 'get_cost' ) ? (float) $rate->get_cost() : 0.0;
    $fixed_price  = isset( $settings[ $price_key ] ) ? trim( (string) $settings[ $price_key ] ) : '';
    $cost         = ( '' !== $fixed_price && is_numeric( $fixed_price ) ) ? max( 0.0, (float) $fixed_price ) : $current_cost;

    $rate_id = method_exists( $rate, 'get_id' ) ? (string) $rate->get_id() : uniqid( 'shk_sdek_', true );
    $new_rate = shikkosa_clone_rate_with_label_and_cost( $rate, $rate_id, $label, $cost );

    $price_comment = isset( $settings[ $price_comment_key ] ) ? trim( (string) $settings[ $price_comment_key ] ) : '';
    $delivery_comment = isset( $settings[ $delivery_comment_key ] ) ? trim( (string) $settings[ $delivery_comment_key ] ) : '';

    if ( '' !== $price_comment ) {
        $new_rate->add_meta_data( '_shk_price_comment', $price_comment, true );
    }
    if ( '' !== $delivery_comment ) {
        $new_rate->add_meta_data( '_shk_delivery_comment', $delivery_comment, true );
    }

    return $new_rate;
}

function shikkosa_sdek_rate_string( $rate ) {
    $parts = array();
    if ( is_object( $rate ) ) {
        if ( method_exists( $rate, 'get_id' ) ) {
            $parts[] = (string) $rate->get_id();
        }
        if ( method_exists( $rate, 'get_method_id' ) ) {
            $parts[] = (string) $rate->get_method_id();
        }
        if ( method_exists( $rate, 'get_label' ) ) {
            $parts[] = (string) $rate->get_label();
        }
    }
    return function_exists( 'mb_strtolower' )
        ? mb_strtolower( implode( ' ', $parts ) )
        : strtolower( implode( ' ', $parts ) );
}

function shikkosa_is_cdek_rate( $rate ) {
    $s = shikkosa_sdek_rate_string( $rate );
    return ( false !== strpos( $s, 'cdek' ) || false !== strpos( $s, 'sdek' ) || false !== strpos( $s, 'сдэк' ) );
}

function shikkosa_is_cdek_pickup_rate( $rate ) {
    $s = shikkosa_sdek_rate_string( $rate );
    if ( ! shikkosa_is_cdek_rate( $rate ) ) {
        return false;
    }
    return (
        false !== strpos( $s, 'pickup' ) ||
        false !== strpos( $s, 'pvz' ) ||
        false !== strpos( $s, 'пвз' ) ||
        false !== strpos( $s, 'пункт' ) ||
        false !== strpos( $s, 'самовывоз' )
    );
}

function shikkosa_is_msk_mo_destination( $package ) {
    $dest = isset( $package['destination'] ) && is_array( $package['destination'] ) ? $package['destination'] : array();
    $country  = isset( $dest['country'] ) ? strtoupper( (string) $dest['country'] ) : '';
    $state    = isset( $dest['state'] ) ? strtoupper( (string) $dest['state'] ) : '';
    $city     = isset( $dest['city'] ) ? (string) $dest['city'] : '';
    $postcode = isset( $dest['postcode'] ) ? preg_replace( '/\D+/', '', (string) $dest['postcode'] ) : '';

    if ( '' !== $country && 'RU' !== $country ) {
        return false;
    }

    // Common Woo RU state codes (depends on locale pack):
    if ( in_array( $state, array( 'MOW', 'MOS', 'RU-MOW', 'RU-MOS', '77', '50' ), true ) ) {
        return true;
    }

    $city_lc = function_exists( 'mb_strtolower' ) ? mb_strtolower( $city ) : strtolower( $city );
    if ( false !== strpos( $city_lc, 'моск' ) || false !== strpos( $city_lc, 'moscow' ) ) {
        return true;
    }

    // Moscow / Moscow region postcode ranges (coarse but practical fallback)
    if ( '' !== $postcode ) {
        $pc = (int) $postcode;
        if ( $pc >= 101000 && $pc <= 143999 ) {
            return true;
        }
    }

    return false;
}

function shikkosa_clone_rate_with_label_and_cost( $source_rate, $new_id, $new_label, $new_cost ) {
    $method_id   = method_exists( $source_rate, 'get_method_id' ) ? (string) $source_rate->get_method_id() : '';
    $instance_id = method_exists( $source_rate, 'get_instance_id' ) ? (int) $source_rate->get_instance_id() : 0;

    $new = new WC_Shipping_Rate(
        (string) $new_id,
        (string) $new_label,
        (float) $new_cost,
        array(),
        $method_id,
        $instance_id
    );

    if ( method_exists( $source_rate, 'get_meta_data' ) ) {
        $meta = (array) $source_rate->get_meta_data();
        foreach ( $meta as $k => $v ) {
            if ( is_string( $k ) && '' !== $k ) {
                $new->add_meta_data( $k, $v, true );
            }
        }
    }

    return $new;
}

function shikkosa_sdek_resolve_cost( $settings, $price_key, $base_cost, $extra_key ) {
    $fixed_price = isset( $settings[ $price_key ] ) ? trim( (string) $settings[ $price_key ] ) : '';
    if ( '' !== $fixed_price && is_numeric( $fixed_price ) ) {
        return max( 0.0, (float) $fixed_price );
    }
    $extra = isset( $settings[ $extra_key ] ) ? (float) $settings[ $extra_key ] : 0.0;
    return max( 0.0, (float) $base_cost + $extra );
}

add_filter( 'woocommerce_package_rates', 'shikkosa_split_cdek_pickup_rates', 120, 2 );
function shikkosa_split_cdek_pickup_rates( $rates, $package ) {
    if ( ! is_array( $rates ) || empty( $rates ) ) {
        return $rates;
    }

    $settings = shikkosa_sdek_settings();
    $is_msk_mo = shikkosa_is_msk_mo_destination( $package );
    $split_enabled = ( 'yes' === (string) $settings['enabled'] );

    $new_rates = array();

    foreach ( $rates as $rate_id => $rate ) {
        if ( ! is_object( $rate ) || ! is_a( $rate, 'WC_Shipping_Rate' ) ) {
            $new_rates[ $rate_id ] = $rate;
            continue;
        }

        // Keep all non-CDEK rates untouched.
        if ( ! shikkosa_is_cdek_rate( $rate ) ) {
            $new_rates[ $rate_id ] = $rate;
            continue;
        }

        $profile = shikkosa_sdek_rate_profile_code( $rate );

        // Keep CDEK courier untouched; split only pickup/PVZ.
        if ( ! $split_enabled || ! shikkosa_is_cdek_pickup_rate( $rate ) ) {
            $new_rates[ $rate_id ] = shikkosa_sdek_apply_profile_overrides( $rate, $settings, $profile );
            continue;
        }

        $base_cost = method_exists( $rate, 'get_cost' ) ? (float) $rate->get_cost() : 0.0;

        if ( $is_msk_mo ) {
            $scenarios = array(
                array(
                    'code'             => 'pvz_msk_no_fit',
                    'label'            => isset( $settings['msk_no_fit_label'] ) ? (string) $settings['msk_no_fit_label'] : 'СДЭК ПВЗ (МСК/МО, без примерки)',
                    'cost'             => shikkosa_sdek_resolve_cost( $settings, 'msk_no_fit_price', $base_cost, 'msk_no_fit_extra' ),
                    'price_comment'    => isset( $settings['msk_no_fit_price_comment'] ) ? (string) $settings['msk_no_fit_price_comment'] : '',
                    'delivery_comment' => isset( $settings['msk_no_fit_delivery_comment'] ) ? (string) $settings['msk_no_fit_delivery_comment'] : '',
                ),
                array(
                    'code'             => 'pvz_msk_fit',
                    'label'            => isset( $settings['msk_fit_label'] ) ? (string) $settings['msk_fit_label'] : 'СДЭК ПВЗ (МСК/МО, с примеркой)',
                    'cost'             => shikkosa_sdek_resolve_cost( $settings, 'msk_fit_price', $base_cost, 'msk_fit_extra' ),
                    'price_comment'    => isset( $settings['msk_fit_price_comment'] ) ? (string) $settings['msk_fit_price_comment'] : '',
                    'delivery_comment' => isset( $settings['msk_fit_delivery_comment'] ) ? (string) $settings['msk_fit_delivery_comment'] : '',
                ),
            );
        } else {
            $scenarios = array(
                array(
                    'code'             => 'pvz_rf_no_fit',
                    'label'            => isset( $settings['rf_no_fit_label'] ) ? (string) $settings['rf_no_fit_label'] : 'СДЭК ПВЗ (РФ, без примерки)',
                    'cost'             => shikkosa_sdek_resolve_cost( $settings, 'rf_no_fit_price', $base_cost, 'rf_no_fit_extra' ),
                    'price_comment'    => isset( $settings['rf_no_fit_price_comment'] ) ? (string) $settings['rf_no_fit_price_comment'] : '',
                    'delivery_comment' => isset( $settings['rf_no_fit_delivery_comment'] ) ? (string) $settings['rf_no_fit_delivery_comment'] : '',
                ),
                array(
                    'code'             => 'pvz_rf_fit',
                    'label'            => isset( $settings['rf_fit_label'] ) ? (string) $settings['rf_fit_label'] : 'СДЭК ПВЗ (РФ, с примеркой)',
                    'cost'             => shikkosa_sdek_resolve_cost( $settings, 'rf_fit_price', $base_cost, 'rf_fit_extra' ),
                    'price_comment'    => isset( $settings['rf_fit_price_comment'] ) ? (string) $settings['rf_fit_price_comment'] : '',
                    'delivery_comment' => isset( $settings['rf_fit_delivery_comment'] ) ? (string) $settings['rf_fit_delivery_comment'] : '',
                ),
            );
        }

        foreach ( $scenarios as $scenario ) {
            $new_id   = (string) $rate_id . '__shk_' . (string) $scenario['code'];
            $new_cost = isset( $scenario['cost'] ) ? (float) $scenario['cost'] : $base_cost;
            $new_rate = shikkosa_clone_rate_with_label_and_cost( $rate, $new_id, $scenario['label'], $new_cost );
            if ( ! empty( $scenario['price_comment'] ) ) {
                $new_rate->add_meta_data( '_shk_price_comment', (string) $scenario['price_comment'], true );
            }
            if ( ! empty( $scenario['delivery_comment'] ) ) {
                $new_rate->add_meta_data( '_shk_delivery_comment', (string) $scenario['delivery_comment'], true );
            }
            $new_rates[ $new_id ] = $new_rate;
        }
    }

    return $new_rates;
}

add_action( 'woocommerce_checkout_create_order', 'shikkosa_sdek_store_checkout_meta', 20, 2 );
function shikkosa_sdek_store_checkout_meta( $order, $data ) {
    if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
        return;
    }

    $methods = $order->get_shipping_methods();
    foreach ( $methods as $shipping_item ) {
        $method_id = method_exists( $shipping_item, 'get_method_id' ) ? (string) $shipping_item->get_method_id() : '';
        $instance_id = method_exists( $shipping_item, 'get_instance_id' ) ? (string) $shipping_item->get_instance_id() : '';
        $name = method_exists( $shipping_item, 'get_name' ) ? (string) $shipping_item->get_name() : '';

        $joined = strtolower( $method_id . ' ' . $instance_id . ' ' . $name );
        if ( false === strpos( $joined, 'cdek' ) && false === strpos( $joined, 'sdek' ) && false === strpos( $joined, 'сдэк' ) ) {
            continue;
        }

        $fitting = ( false !== strpos( $joined, 'примерк' ) || false !== strpos( $joined, 'fit' ) ) ? 'yes' : 'no';
        $order->update_meta_data( '_shk_sdek_fitting', $fitting );
        $order->update_meta_data( '_shk_sdek_shipping_label', $name );
        break;
    }
}

add_action( 'admin_menu', 'shikkosa_sdek_settings_menu' );
function shikkosa_sdek_settings_menu() {
    add_submenu_page(
        'woocommerce',
        'SHK СДЭК',
        'SHK СДЭК',
        'manage_woocommerce',
        'shk-sdek',
        'shikkosa_sdek_settings_page'
    );
}

add_action( 'admin_init', 'shikkosa_sdek_settings_register' );
function shikkosa_sdek_settings_register() {
    register_setting(
        'shikkosa_sdek_settings_group',
        'shikkosa_sdek_settings',
        array(
            'sanitize_callback' => 'shikkosa_sdek_settings_sanitize',
        )
    );
}

function shikkosa_sdek_settings_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    $opt = shikkosa_sdek_settings();
    ?>
    <div class="wrap">
        <h1>SHK СДЭК</h1>
        <p>Деление только для ПВЗ СДЭК. Курьер СДЭК остаётся как в плагине.</p>
        <form method="post" action="options.php">
            <?php settings_fields( 'shikkosa_sdek_settings_group' ); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Включить разделение ПВЗ</th>
                    <td>
                        <label>
                            <input type="checkbox" name="shikkosa_sdek_settings[enabled]" value="yes" <?php checked( $opt['enabled'], 'yes' ); ?> />
                            Да
                        </label>
                    </td>
                </tr>
                <tr><th scope="row" colspan="2"><h2 style="margin:8px 0 0;">Все виды СДЭК (общие настройки)</h2></th></tr>
                <tr><th scope="row">СДЭК дверь-дверь: название</th><td><input type="text" class="regular-text" name="shikkosa_sdek_settings[cdek_door_door_label]" value="<?php echo esc_attr( $opt['cdek_door_door_label'] ); ?>" /></td></tr>
                <tr><th scope="row">СДЭК дверь-дверь: стоимость</th><td><input type="number" step="0.01" name="shikkosa_sdek_settings[cdek_door_door_price]" value="<?php echo esc_attr( $opt['cdek_door_door_price'] ); ?>" /></td></tr>
                <tr><th scope="row">СДЭК дверь-дверь: комментарий к цене</th><td><input type="text" class="regular-text" name="shikkosa_sdek_settings[cdek_door_door_price_comment]" value="<?php echo esc_attr( $opt['cdek_door_door_price_comment'] ); ?>" /></td></tr>
                <tr><th scope="row">СДЭК дверь-дверь: комментарий по сроку/условиям</th><td><input type="text" class="regular-text" name="shikkosa_sdek_settings[cdek_door_door_delivery_comment]" value="<?php echo esc_attr( $opt['cdek_door_door_delivery_comment'] ); ?>" /></td></tr>
                <tr><th scope="row">СДЭК дверь-склад/пункт: название</th><td><input type="text" class="regular-text" name="shikkosa_sdek_settings[cdek_door_warehouse_label]" value="<?php echo esc_attr( $opt['cdek_door_warehouse_label'] ); ?>" /></td></tr>
                <tr><th scope="row">СДЭК дверь-склад/пункт: стоимость</th><td><input type="number" step="0.01" name="shikkosa_sdek_settings[cdek_door_warehouse_price]" value="<?php echo esc_attr( $opt['cdek_door_warehouse_price'] ); ?>" /></td></tr>
                <tr><th scope="row">СДЭК дверь-склад/пункт: комментарий к цене</th><td><input type="text" class="regular-text" name="shikkosa_sdek_settings[cdek_door_warehouse_price_comment]" value="<?php echo esc_attr( $opt['cdek_door_warehouse_price_comment'] ); ?>" /></td></tr>
                <tr><th scope="row">СДЭК дверь-склад/пункт: комментарий по сроку/условиям</th><td><input type="text" class="regular-text" name="shikkosa_sdek_settings[cdek_door_warehouse_delivery_comment]" value="<?php echo esc_attr( $opt['cdek_door_warehouse_delivery_comment'] ); ?>" /></td></tr>
                <tr><th scope="row">СДЭК ПВЗ/самовывоз: название</th><td><input type="text" class="regular-text" name="shikkosa_sdek_settings[cdek_pickup_label]" value="<?php echo esc_attr( $opt['cdek_pickup_label'] ); ?>" /></td></tr>
                <tr><th scope="row">СДЭК ПВЗ/самовывоз: стоимость</th><td><input type="number" step="0.01" name="shikkosa_sdek_settings[cdek_pickup_price]" value="<?php echo esc_attr( $opt['cdek_pickup_price'] ); ?>" /></td></tr>
                <tr><th scope="row">СДЭК ПВЗ/самовывоз: комментарий к цене</th><td><input type="text" class="regular-text" name="shikkosa_sdek_settings[cdek_pickup_price_comment]" value="<?php echo esc_attr( $opt['cdek_pickup_price_comment'] ); ?>" /></td></tr>
                <tr><th scope="row">СДЭК ПВЗ/самовывоз: комментарий по сроку/условиям</th><td><input type="text" class="regular-text" name="shikkosa_sdek_settings[cdek_pickup_delivery_comment]" value="<?php echo esc_attr( $opt['cdek_pickup_delivery_comment'] ); ?>" /></td></tr>
                <tr><th scope="row">СДЭК Экспресс дверь-дверь: название</th><td><input type="text" class="regular-text" name="shikkosa_sdek_settings[cdek_express_door_door_label]" value="<?php echo esc_attr( $opt['cdek_express_door_door_label'] ); ?>" /></td></tr>
                <tr><th scope="row">СДЭК Экспресс дверь-дверь: стоимость</th><td><input type="number" step="0.01" name="shikkosa_sdek_settings[cdek_express_door_door_price]" value="<?php echo esc_attr( $opt['cdek_express_door_door_price'] ); ?>" /></td></tr>
                <tr><th scope="row">СДЭК Экспресс дверь-дверь: комментарий к цене</th><td><input type="text" class="regular-text" name="shikkosa_sdek_settings[cdek_express_door_door_price_comment]" value="<?php echo esc_attr( $opt['cdek_express_door_door_price_comment'] ); ?>" /></td></tr>
                <tr><th scope="row">СДЭК Экспресс дверь-дверь: комментарий по сроку/условиям</th><td><input type="text" class="regular-text" name="shikkosa_sdek_settings[cdek_express_door_door_delivery_comment]" value="<?php echo esc_attr( $opt['cdek_express_door_door_delivery_comment'] ); ?>" /></td></tr>
                <tr><th scope="row" colspan="2"><h2 style="margin:8px 0 0;">Split ПВЗ (когда включено разделение)</h2></th></tr>
                <tr><th scope="row">МСК/МО, ПВЗ без примерки (добавка к базовой цене)</th><td><input type="number" step="0.01" name="shikkosa_sdek_settings[msk_no_fit_extra]" value="<?php echo esc_attr( $opt['msk_no_fit_extra'] ); ?>" /></td></tr>
                <tr><th scope="row">МСК/МО, ПВЗ с примеркой (добавка к базовой цене)</th><td><input type="number" step="0.01" name="shikkosa_sdek_settings[msk_fit_extra]" value="<?php echo esc_attr( $opt['msk_fit_extra'] ); ?>" /></td></tr>
                <tr><th scope="row">РФ, ПВЗ без примерки (добавка к базовой цене)</th><td><input type="number" step="0.01" name="shikkosa_sdek_settings[rf_no_fit_extra]" value="<?php echo esc_attr( $opt['rf_no_fit_extra'] ); ?>" /></td></tr>
                <tr><th scope="row">РФ, ПВЗ с примеркой (добавка к базовой цене)</th><td><input type="number" step="0.01" name="shikkosa_sdek_settings[rf_fit_extra]" value="<?php echo esc_attr( $opt['rf_fit_extra'] ); ?>" /></td></tr>
                <tr>
                    <th scope="row">МСК/МО, без примерки: название</th>
                    <td><input type="text" class="regular-text" name="shikkosa_sdek_settings[msk_no_fit_label]" value="<?php echo esc_attr( $opt['msk_no_fit_label'] ); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row">МСК/МО, без примерки: стоимость</th>
                    <td><input type="number" step="0.01" name="shikkosa_sdek_settings[msk_no_fit_price]" value="<?php echo esc_attr( $opt['msk_no_fit_price'] ); ?>" /> <p class="description">Если пусто — берётся базовая цена + добавка.</p></td>
                </tr>
                <tr>
                    <th scope="row">МСК/МО, без примерки: комментарий к цене</th>
                    <td><input type="text" class="regular-text" name="shikkosa_sdek_settings[msk_no_fit_price_comment]" value="<?php echo esc_attr( $opt['msk_no_fit_price_comment'] ); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row">МСК/МО, без примерки: комментарий по сроку/условиям</th>
                    <td><input type="text" class="regular-text" name="shikkosa_sdek_settings[msk_no_fit_delivery_comment]" value="<?php echo esc_attr( $opt['msk_no_fit_delivery_comment'] ); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row">МСК/МО, с примеркой: название</th>
                    <td><input type="text" class="regular-text" name="shikkosa_sdek_settings[msk_fit_label]" value="<?php echo esc_attr( $opt['msk_fit_label'] ); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row">МСК/МО, с примеркой: стоимость</th>
                    <td><input type="number" step="0.01" name="shikkosa_sdek_settings[msk_fit_price]" value="<?php echo esc_attr( $opt['msk_fit_price'] ); ?>" /> <p class="description">Если пусто — берётся базовая цена + добавка.</p></td>
                </tr>
                <tr>
                    <th scope="row">МСК/МО, с примеркой: комментарий к цене</th>
                    <td><input type="text" class="regular-text" name="shikkosa_sdek_settings[msk_fit_price_comment]" value="<?php echo esc_attr( $opt['msk_fit_price_comment'] ); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row">МСК/МО, с примеркой: комментарий по сроку/условиям</th>
                    <td><input type="text" class="regular-text" name="shikkosa_sdek_settings[msk_fit_delivery_comment]" value="<?php echo esc_attr( $opt['msk_fit_delivery_comment'] ); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row">РФ, без примерки: название</th>
                    <td><input type="text" class="regular-text" name="shikkosa_sdek_settings[rf_no_fit_label]" value="<?php echo esc_attr( $opt['rf_no_fit_label'] ); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row">РФ, без примерки: стоимость</th>
                    <td><input type="number" step="0.01" name="shikkosa_sdek_settings[rf_no_fit_price]" value="<?php echo esc_attr( $opt['rf_no_fit_price'] ); ?>" /> <p class="description">Если пусто — берётся базовая цена + добавка.</p></td>
                </tr>
                <tr>
                    <th scope="row">РФ, без примерки: комментарий к цене</th>
                    <td><input type="text" class="regular-text" name="shikkosa_sdek_settings[rf_no_fit_price_comment]" value="<?php echo esc_attr( $opt['rf_no_fit_price_comment'] ); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row">РФ, без примерки: комментарий по сроку/условиям</th>
                    <td><input type="text" class="regular-text" name="shikkosa_sdek_settings[rf_no_fit_delivery_comment]" value="<?php echo esc_attr( $opt['rf_no_fit_delivery_comment'] ); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row">РФ, с примеркой: название</th>
                    <td><input type="text" class="regular-text" name="shikkosa_sdek_settings[rf_fit_label]" value="<?php echo esc_attr( $opt['rf_fit_label'] ); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row">РФ, с примеркой: стоимость</th>
                    <td><input type="number" step="0.01" name="shikkosa_sdek_settings[rf_fit_price]" value="<?php echo esc_attr( $opt['rf_fit_price'] ); ?>" /> <p class="description">Если пусто — берётся базовая цена + добавка.</p></td>
                </tr>
                <tr>
                    <th scope="row">РФ, с примеркой: комментарий к цене</th>
                    <td><input type="text" class="regular-text" name="shikkosa_sdek_settings[rf_fit_price_comment]" value="<?php echo esc_attr( $opt['rf_fit_price_comment'] ); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row">РФ, с примеркой: комментарий по сроку/условиям</th>
                    <td><input type="text" class="regular-text" name="shikkosa_sdek_settings[rf_fit_delivery_comment]" value="<?php echo esc_attr( $opt['rf_fit_delivery_comment'] ); ?>" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>

        <hr />
        <h2>Тест</h2>
        <ol>
            <li>Откройте корзину и перейдите в checkout.</li>
            <li>Проверьте адрес с Москвой/МО — должны быть 2 опции ПВЗ (с/без примерки).</li>
            <li>Проверьте адрес по РФ вне МСК/МО — также 2 опции ПВЗ (с/без примерки), но с RF-логикой.</li>
            <li>Проверьте, что курьер СДЭК отображается отдельно и без изменений.</li>
            <li>После заказа в админке у заказа будут мета: <code>_shk_sdek_fitting</code> и <code>_shk_sdek_shipping_label</code>.</li>
        </ol>
    </div>
    <?php
}

add_action( 'wp_footer', 'shikkosa_sdek_checkout_notes_blocks', 130 );
function shikkosa_sdek_checkout_notes_blocks() {
    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
        return;
    }

    $opt = shikkosa_sdek_settings();
    $notes = array(
        'cdek_door_door' => array(
            'price'    => isset( $opt['cdek_door_door_price_comment'] ) ? (string) $opt['cdek_door_door_price_comment'] : '',
            'delivery' => isset( $opt['cdek_door_door_delivery_comment'] ) ? (string) $opt['cdek_door_door_delivery_comment'] : '',
        ),
        'cdek_door_warehouse' => array(
            'price'    => isset( $opt['cdek_door_warehouse_price_comment'] ) ? (string) $opt['cdek_door_warehouse_price_comment'] : '',
            'delivery' => isset( $opt['cdek_door_warehouse_delivery_comment'] ) ? (string) $opt['cdek_door_warehouse_delivery_comment'] : '',
        ),
        'cdek_pickup' => array(
            'price'    => isset( $opt['cdek_pickup_price_comment'] ) ? (string) $opt['cdek_pickup_price_comment'] : '',
            'delivery' => isset( $opt['cdek_pickup_delivery_comment'] ) ? (string) $opt['cdek_pickup_delivery_comment'] : '',
        ),
        'cdek_express_door_door' => array(
            'price'    => isset( $opt['cdek_express_door_door_price_comment'] ) ? (string) $opt['cdek_express_door_door_price_comment'] : '',
            'delivery' => isset( $opt['cdek_express_door_door_delivery_comment'] ) ? (string) $opt['cdek_express_door_door_delivery_comment'] : '',
        ),
        'pvz_msk_no_fit' => array(
            'price'    => isset( $opt['msk_no_fit_price_comment'] ) ? (string) $opt['msk_no_fit_price_comment'] : '',
            'delivery' => isset( $opt['msk_no_fit_delivery_comment'] ) ? (string) $opt['msk_no_fit_delivery_comment'] : '',
        ),
        'pvz_msk_fit' => array(
            'price'    => isset( $opt['msk_fit_price_comment'] ) ? (string) $opt['msk_fit_price_comment'] : '',
            'delivery' => isset( $opt['msk_fit_delivery_comment'] ) ? (string) $opt['msk_fit_delivery_comment'] : '',
        ),
        'pvz_rf_no_fit' => array(
            'price'    => isset( $opt['rf_no_fit_price_comment'] ) ? (string) $opt['rf_no_fit_price_comment'] : '',
            'delivery' => isset( $opt['rf_no_fit_delivery_comment'] ) ? (string) $opt['rf_no_fit_delivery_comment'] : '',
        ),
        'pvz_rf_fit' => array(
            'price'    => isset( $opt['rf_fit_price_comment'] ) ? (string) $opt['rf_fit_price_comment'] : '',
            'delivery' => isset( $opt['rf_fit_delivery_comment'] ) ? (string) $opt['rf_fit_delivery_comment'] : '',
        ),
    );
    ?>
    <script>
    (function () {
      var notes = <?php echo wp_json_encode( $notes ); ?>;

      function detectCode(inputValue, inputId) {
        var hay = (String(inputValue || '') + ' ' + String(inputId || '')).toLowerCase();
        if (hay.indexOf('pvz_msk_no_fit') !== -1) return 'pvz_msk_no_fit';
        if (hay.indexOf('pvz_msk_fit') !== -1) return 'pvz_msk_fit';
        if (hay.indexOf('pvz_rf_no_fit') !== -1) return 'pvz_rf_no_fit';
        if (hay.indexOf('pvz_rf_fit') !== -1) return 'pvz_rf_fit';
        if (hay.indexOf('экспресс') !== -1 || hay.indexOf('express') !== -1) return 'cdek_express_door_door';
        if (hay.indexOf('двер') !== -1 && hay.indexOf('склад') !== -1) return 'cdek_door_warehouse';
        if (hay.indexOf('пвз') !== -1 || hay.indexOf('пункт') !== -1 || hay.indexOf('pickup') !== -1 || hay.indexOf('самовывоз') !== -1) return 'cdek_pickup';
        if (hay.indexOf('двер') !== -1 || hay.indexOf('door') !== -1) return 'cdek_door_door';
        return '';
      }

      function applyNotes() {
        var options = document.querySelectorAll('.wc-block-checkout__shipping-option .wc-block-components-radio-control__option');
        if (!options.length) return;

        options.forEach(function (opt) {
          var input = opt.querySelector('.wc-block-components-radio-control__input');
          if (!input) return;
          var labelTextNode = opt.querySelector('.wc-block-components-radio-control__label');
          var labelText = labelTextNode ? labelTextNode.textContent : '';

          var code = detectCode((input.value || '') + ' ' + labelText, input.id);
          if (!code || !notes[code]) return;

          var noteData = notes[code];
          var layout = opt.querySelector('.wc-block-components-radio-control__option-layout');
          if (!layout) return;

          var existing = opt.querySelector('.shk-sdek-note');
          if (!existing) {
            existing = document.createElement('div');
            existing.className = 'shk-sdek-note';
            layout.appendChild(existing);
          }

          var price = (noteData.price || '').trim();
          var delivery = (noteData.delivery || '').trim();

          if (!price && !delivery) {
            existing.innerHTML = '';
            return;
          }

          var html = '';
          if (price) {
            html += '<div class="shk-sdek-note__price">' + price.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</div>';
          }
          if (delivery) {
            html += '<div class="shk-sdek-note__delivery">' + delivery.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</div>';
          }
          existing.innerHTML = html;
        });
      }

      var t = 0;
      var iv = setInterval(function () {
        t++;
        applyNotes();
        if (t > 80) clearInterval(iv);
      }, 250);

      document.addEventListener('change', applyNotes);
      document.addEventListener('wc-blocks_checkout_update', applyNotes);
    })();
    </script>
    <?php
}
