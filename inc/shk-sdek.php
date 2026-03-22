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
        'msk_no_fit_extra'   => '0',
        'msk_fit_extra'      => '0',
        'rf_no_fit_extra'    => '0',
        'rf_fit_extra'       => '0',
    );
}

function shikkosa_sdek_settings() {
    $defaults = shikkosa_sdek_settings_default();
    $saved = get_option( 'shikkosa_sdek_settings', array() );
    if ( ! is_array( $saved ) ) {
        $saved = array();
    }
    return wp_parse_args( $saved, $defaults );
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

add_filter( 'woocommerce_package_rates', 'shikkosa_split_cdek_pickup_rates', 120, 2 );
function shikkosa_split_cdek_pickup_rates( $rates, $package ) {
    if ( ! is_array( $rates ) || empty( $rates ) ) {
        return $rates;
    }

    $settings = shikkosa_sdek_settings();
    if ( 'yes' !== (string) $settings['enabled'] ) {
        return $rates;
    }

    $is_msk_mo = shikkosa_is_msk_mo_destination( $package );

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

        // Keep CDEK courier untouched; split only pickup/PVZ.
        if ( ! shikkosa_is_cdek_pickup_rate( $rate ) ) {
            $new_rates[ $rate_id ] = $rate;
            continue;
        }

        $base_cost = method_exists( $rate, 'get_cost' ) ? (float) $rate->get_cost() : 0.0;

        if ( $is_msk_mo ) {
            $scenarios = array(
                array(
                    'code'  => 'pvz_msk_no_fit',
                    'label' => 'СДЭК ПВЗ (МСК/МО, без примерки)',
                    'extra' => (float) $settings['msk_no_fit_extra'],
                ),
                array(
                    'code'  => 'pvz_msk_fit',
                    'label' => 'СДЭК ПВЗ (МСК/МО, с примеркой)',
                    'extra' => (float) $settings['msk_fit_extra'],
                ),
            );
        } else {
            $scenarios = array(
                array(
                    'code'  => 'pvz_rf_no_fit',
                    'label' => 'СДЭК ПВЗ (РФ, без примерки)',
                    'extra' => (float) $settings['rf_no_fit_extra'],
                ),
                array(
                    'code'  => 'pvz_rf_fit',
                    'label' => 'СДЭК ПВЗ (РФ, с примеркой)',
                    'extra' => (float) $settings['rf_fit_extra'],
                ),
            );
        }

        foreach ( $scenarios as $scenario ) {
            $new_id   = (string) $rate_id . '__shk_' . (string) $scenario['code'];
            $new_cost = max( 0, $base_cost + (float) $scenario['extra'] );
            $new_rates[ $new_id ] = shikkosa_clone_rate_with_label_and_cost( $rate, $new_id, $scenario['label'], $new_cost );
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
    register_setting( 'shikkosa_sdek_settings_group', 'shikkosa_sdek_settings' );
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
                <tr><th scope="row">МСК/МО, ПВЗ без примерки (добавка к базовой цене)</th><td><input type="number" step="0.01" name="shikkosa_sdek_settings[msk_no_fit_extra]" value="<?php echo esc_attr( $opt['msk_no_fit_extra'] ); ?>" /></td></tr>
                <tr><th scope="row">МСК/МО, ПВЗ с примеркой (добавка к базовой цене)</th><td><input type="number" step="0.01" name="shikkosa_sdek_settings[msk_fit_extra]" value="<?php echo esc_attr( $opt['msk_fit_extra'] ); ?>" /></td></tr>
                <tr><th scope="row">РФ, ПВЗ без примерки (добавка к базовой цене)</th><td><input type="number" step="0.01" name="shikkosa_sdek_settings[rf_no_fit_extra]" value="<?php echo esc_attr( $opt['rf_no_fit_extra'] ); ?>" /></td></tr>
                <tr><th scope="row">РФ, ПВЗ с примеркой (добавка к базовой цене)</th><td><input type="number" step="0.01" name="shikkosa_sdek_settings[rf_fit_extra]" value="<?php echo esc_attr( $opt['rf_fit_extra'] ); ?>" /></td></tr>
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
