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
    $defaults = array(
        'enabled'            => 'yes',
        'debug_timing'       => 'no',
        'show_all_before_address' => 'yes',
        'custom_rates'       => array(),
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

    foreach ( array( 'cdek_door_door', 'cdek_door_warehouse', 'cdek_pickup', 'cdek_express_door_door' ) as $profile ) {
        $defaults[ $profile . '_visible' ] = 'yes';
        $defaults[ $profile . '_variant_enabled' ] = 'no';
        $defaults[ $profile . '_variant_label' ] = '';
        $defaults[ $profile . '_variant_price' ] = '';
        $defaults[ $profile . '_variant_price_comment' ] = '';
        $defaults[ $profile . '_variant_delivery_comment' ] = '';
        $defaults[ $profile . '_variants' ] = array();
    }

    return $defaults;
}

function shikkosa_use_native_woo_shipping_mode() {
    return true;
}

function shikkosa_shipping_instance_comment_fields( $fields ) {
    $fields = is_array( $fields ) ? $fields : array();

    if ( ! isset( $fields['shk_price_comment'] ) ) {
        $fields['shk_price_comment'] = array(
            'title'       => 'Комментарий к стоимости',
            'type'        => 'text',
            'description' => 'Показывается под способом доставки рядом с ценой.',
            'default'     => '',
            'desc_tip'    => true,
        );
    }

    if ( ! isset( $fields['shk_delivery_comment'] ) ) {
        $fields['shk_delivery_comment'] = array(
            'title'       => 'Комментарий к доставке',
            'type'        => 'text',
            'description' => 'Показывается под способом доставки (срок/условия).',
            'default'     => '',
            'desc_tip'    => true,
        );
    }

    return $fields;
}

function shikkosa_shipping_instance_comment_fields_with_tariff( $fields ) {
    $fields = shikkosa_shipping_instance_comment_fields( $fields );

    if ( ! isset( $fields['shk_sdek_tariff_code'] ) ) {
        $fields['shk_sdek_tariff_code'] = array(
            'title'       => 'Код тарифа CDEK',
            'type'        => 'textarea',
            'description' => 'Один тариф: 138. Несколько SHK пунктов: по 1 строке "Название | КодТарифа | Цена | КомментКЦене | КомментПоСроку".',
            'default'     => '',
            'desc_tip'    => true,
        );
    }

    if ( ! isset( $fields['shk_sdek_inline_points'] ) ) {
        $fields['shk_sdek_inline_points'] = array(
            'title'       => 'SHK пункты (инлайн)',
            'type'        => 'textarea',
            'description' => 'По 1 строке на пункт: Название | КодТарифа | Цена | КомментКЦене | КомментПоСроку',
            'default'     => '',
            'desc_tip'    => false,
        );
    }

    return $fields;
}

add_filter( 'woocommerce_shipping_instance_form_fields_flat_rate', 'shikkosa_shipping_instance_comment_fields', 40 );
add_filter( 'woocommerce_shipping_instance_form_fields_free_shipping', 'shikkosa_shipping_instance_comment_fields', 40 );
add_filter( 'woocommerce_shipping_instance_form_fields_local_pickup', 'shikkosa_shipping_instance_comment_fields', 40 );
add_filter( 'woocommerce_shipping_instance_form_fields_official_cdek', 'shikkosa_shipping_instance_comment_fields_with_tariff', 40 );

function shikkosa_rate_settings_by_instance( $method_id, $instance_id ) {
    $method_id = (string) $method_id;
    $instance_id = (int) $instance_id;
    if ( '' === $method_id || $instance_id <= 0 ) {
        return array();
    }
    $opt_key = 'woocommerce_' . $method_id . '_' . $instance_id . '_settings';
    $settings = get_option( $opt_key, array() );
    return is_array( $settings ) ? $settings : array();
}

function shikkosa_parse_inline_sdek_points( $raw ) {
    if ( ! is_scalar( $raw ) ) {
        return array();
    }
    $text = trim( (string) $raw );
    if ( '' === $text ) {
        return array();
    }

    $lines = preg_split( '/\s*;;\s*|\R+/u', $text );
    $out = array();
    foreach ( (array) $lines as $line ) {
        $line = trim( (string) $line );
        if ( '' === $line ) {
            continue;
        }

        $parts = array_map( 'trim', explode( '|', $line ) );
        $label = isset( $parts[0] ) ? sanitize_text_field( (string) $parts[0] ) : '';
        $tariff_code = isset( $parts[1] ) ? preg_replace( '/[^0-9]/', '', (string) $parts[1] ) : '';
        $price_raw = isset( $parts[2] ) ? trim( (string) $parts[2] ) : '';
        $price_comment = isset( $parts[3] ) ? sanitize_text_field( (string) $parts[3] ) : '';
        $delivery_comment = isset( $parts[4] ) ? sanitize_text_field( (string) $parts[4] ) : '';

        if ( '' === $label ) {
            continue;
        }
        if ( '' === $tariff_code ) {
            continue;
        }

        $price = '';
        if ( '' !== $price_raw && is_numeric( $price_raw ) ) {
            $price = (string) wc_format_decimal( (string) $price_raw );
        }

        $out[] = array(
            'label' => $label,
            'tariff_code' => $tariff_code,
            'price' => $price,
            'price_comment' => $price_comment,
            'delivery_comment' => $delivery_comment,
        );
    }

    return $out;
}

add_action( 'admin_footer', 'shikkosa_sdek_render_inline_points_builder_js', 120 );
function shikkosa_sdek_render_inline_points_builder_js() {
    if ( ! is_admin() ) {
        return;
    }
    $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
    $tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
    if ( 'wc-settings' !== $page || 'shipping' !== $tab ) {
        return;
    }
    ?>
    <script>
    (function(){
      function parseRawToRows(raw) {
        var text = String(raw || '').trim();
        if (!text) return [];
        var parts = text.split(/\s*;;\s*|\n+/).map(function(v){ return String(v || '').trim(); }).filter(Boolean);
        return parts.map(function(line){
          var p = line.split('|').map(function(v){ return String(v || '').trim(); });
          return {
            label: p[0] || '',
            tariff: (p[1] || '').replace(/\D+/g, ''),
            price: p[2] || '',
            priceComment: p[3] || '',
            deliveryComment: p[4] || ''
          };
        });
      }

      function serializeRows(rows) {
        var clean = rows.filter(function(r){
          return String(r.label || '').trim() && String(r.tariff || '').trim();
        });
        return clean.map(function(r){
          return [
            String(r.label || '').trim(),
            String(r.tariff || '').replace(/\D+/g, ''),
            String(r.price || '').trim(),
            String(r.priceComment || '').trim(),
            String(r.deliveryComment || '').trim()
          ].join(' | ');
        }).join(' ;; ');
      }

      function rowTpl(row) {
        var tr = document.createElement('tr');
        tr.innerHTML =
          '<td><input type="text" class="shk-p-label" style="width:100%" /></td>' +
          '<td><input type="text" class="shk-p-tariff" inputmode="numeric" pattern="[0-9]*" style="width:100%" /></td>' +
          '<td><input type="text" class="shk-p-price" style="width:100%" /></td>' +
          '<td><input type="text" class="shk-p-pc" style="width:100%" /></td>' +
          '<td><input type="text" class="shk-p-dc" style="width:100%" /></td>' +
          '<td><button type="button" class="button-link-delete shk-p-del">Удалить</button></td>';
        tr.querySelector('.shk-p-label').value = row.label || '';
        tr.querySelector('.shk-p-tariff').value = row.tariff || '';
        tr.querySelector('.shk-p-price').value = row.price || '';
        tr.querySelector('.shk-p-pc').value = row.priceComment || '';
        tr.querySelector('.shk-p-dc').value = row.deliveryComment || '';
        return tr;
      }

      function initBuilder() {
        var input = document.querySelector(
          'input[name*=\"shk_sdek_tariff_code\"], textarea[name*=\"shk_sdek_tariff_code\"], #woocommerce_official_cdek_shk_sdek_tariff_code'
        );
        if (!input || input.dataset.shkBuilderReady === '1') return;
        input.dataset.shkBuilderReady = '1';

        var wrap = document.createElement('div');
        wrap.className = 'shk-inline-points-builder';
        wrap.style.marginTop = '10px';
        wrap.innerHTML =
          '<p style="margin:0 0 8px 0;font-size:12px;color:#646970">SHK пункты: Название + тариф + цена + комментарии. Работает в этом варианте зоны доставки.</p>' +
          '<table class="widefat striped" style="max-width:1100px">' +
          '<thead><tr><th>Название</th><th>Тариф</th><th>Цена</th><th>Коммент. к цене</th><th>Коммент. к доставке</th><th></th></tr></thead>' +
          '<tbody></tbody></table>' +
          '<p style="margin-top:8px"><button type="button" class="button button-secondary shk-p-add">+ Добавить пункт</button></p>';

        input.insertAdjacentElement('afterend', wrap);
        input.style.display = 'none';

        var tbody = wrap.querySelector('tbody');
        var addBtn = wrap.querySelector('.shk-p-add');

        function collectRows() {
          var rows = [];
          tbody.querySelectorAll('tr').forEach(function(tr){
            rows.push({
              label: (tr.querySelector('.shk-p-label') || {}).value || '',
              tariff: (tr.querySelector('.shk-p-tariff') || {}).value || '',
              price: (tr.querySelector('.shk-p-price') || {}).value || '',
              priceComment: (tr.querySelector('.shk-p-pc') || {}).value || '',
              deliveryComment: (tr.querySelector('.shk-p-dc') || {}).value || ''
            });
          });
          return rows;
        }

        function syncToInput() {
          input.value = serializeRows(collectRows());
        }

        function appendRow(data) {
          var tr = rowTpl(data || {});
          tbody.appendChild(tr);
          tr.querySelectorAll('input').forEach(function(el){
            el.addEventListener('input', syncToInput);
          });
          var del = tr.querySelector('.shk-p-del');
          if (del) {
            del.addEventListener('click', function(){
              tr.remove();
              syncToInput();
            });
          }
          syncToInput();
        }

        var existing = parseRawToRows(input.value || '');
        if (existing.length) {
          existing.forEach(appendRow);
        } else {
          var onlyDigits = String(input.value || '').trim().match(/^\d+$/);
          if (onlyDigits) {
            appendRow({ label: '', tariff: onlyDigits[0], price: '', priceComment: '', deliveryComment: '' });
          }
        }

        addBtn.addEventListener('click', function(){
          appendRow({});
        });
      }

      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBuilder);
      } else {
        initBuilder();
      }
    })();
    </script>
    <?php
}

function shikkosa_apply_instance_meta_to_rates( $rates ) {
    $rates = is_array( $rates ) ? $rates : array();
    $new_rates = array();
    $expanded_instances = array();

    foreach ( $rates as $rate_id => $rate ) {
        if ( ! is_object( $rate ) || ! is_a( $rate, 'WC_Shipping_Rate' ) ) {
            $new_rates[ $rate_id ] = $rate;
            continue;
        }

        $method_id = method_exists( $rate, 'get_method_id' ) ? (string) $rate->get_method_id() : '';
        $instance_id = method_exists( $rate, 'get_instance_id' ) ? (int) $rate->get_instance_id() : 0;
        $settings = shikkosa_rate_settings_by_instance( $method_id, $instance_id );
        $price_comment = ! empty( $settings ) && isset( $settings['shk_price_comment'] ) ? sanitize_text_field( (string) $settings['shk_price_comment'] ) : '';
        $delivery_comment = ! empty( $settings ) && isset( $settings['shk_delivery_comment'] ) ? sanitize_text_field( (string) $settings['shk_delivery_comment'] ) : '';
        $tariff_raw = ! empty( $settings ) && isset( $settings['shk_sdek_tariff_code'] ) ? (string) $settings['shk_sdek_tariff_code'] : '';
        $inline_raw = ! empty( $settings ) && isset( $settings['shk_sdek_inline_points'] ) ? (string) $settings['shk_sdek_inline_points'] : '';
        if ( '' === trim( $inline_raw ) ) {
            $inline_raw = $tariff_raw;
        }
        $inline_points = shikkosa_parse_inline_sdek_points( $inline_raw );
        $tariff_code = preg_replace( '/[^0-9]/', '', $tariff_raw );
        if ( ! empty( $inline_points ) ) {
            $tariff_code = '';
        }

        if ( 'official_cdek' === $method_id && ! empty( $inline_points ) ) {
            $instance_key = $method_id . ':' . (string) $instance_id;
            if ( isset( $expanded_instances[ $instance_key ] ) ) {
                continue;
            }

            $base_cost = method_exists( $rate, 'get_cost' ) ? (float) $rate->get_cost() : 0.0;
            foreach ( $inline_points as $idx => $point ) {
                $p_label = isset( $point['label'] ) ? sanitize_text_field( (string) $point['label'] ) : '';
                $p_tariff = isset( $point['tariff_code'] ) ? preg_replace( '/[^0-9]/', '', (string) $point['tariff_code'] ) : '';
                $p_price_comment = isset( $point['price_comment'] ) ? sanitize_text_field( (string) $point['price_comment'] ) : '';
                $p_delivery_comment = isset( $point['delivery_comment'] ) ? sanitize_text_field( (string) $point['delivery_comment'] ) : '';
                $p_price_raw = isset( $point['price'] ) ? trim( (string) $point['price'] ) : '';

                if ( '' === $p_label || '' === $p_tariff ) {
                    continue;
                }
                $p_cost = ( '' !== $p_price_raw && is_numeric( $p_price_raw ) ) ? max( 0.0, (float) $p_price_raw ) : $base_cost;

                $suffix = '__shk_point_' . ( (int) $idx + 1 ) . '__tariff_' . $p_tariff;
                $new_id = (string) $rate_id . $suffix;
                $new_rate = shikkosa_clone_rate_with_label_and_cost( $rate, $new_id, $p_label, $p_cost );
                $new_rate->add_meta_data( '_shk_sdek_tariff_code', $p_tariff, true );
                if ( '' !== $p_price_comment ) {
                    $new_rate->add_meta_data( '_shk_price_comment', $p_price_comment, true );
                }
                if ( '' !== $p_delivery_comment ) {
                    $new_rate->add_meta_data( '_shk_delivery_comment', $p_delivery_comment, true );
                }
                $new_rates[ $new_id ] = $new_rate;
            }

            $expanded_instances[ $instance_key ] = true;
            continue;
        }

        if ( 'official_cdek' === $method_id && '' !== $tariff_code ) {
            $hay = implode(
                ' ',
                array(
                    (string) $rate_id,
                    method_exists( $rate, 'get_id' ) ? (string) $rate->get_id() : '',
                    method_exists( $rate, 'get_label' ) ? (string) $rate->get_label() : '',
                    function_exists( 'shikkosa_sdek_rate_string' ) ? (string) shikkosa_sdek_rate_string( $rate ) : '',
                )
            );
            $ids = function_exists( 'shikkosa_sdek_extract_tariff_ids_from_value' ) ? shikkosa_sdek_extract_tariff_ids_from_value( $hay ) : array();

            if ( ! empty( $ids ) && ! in_array( (int) $tariff_code, $ids, true ) ) {
                continue;
            }
        }

        if ( '' !== $price_comment ) {
            $rate->add_meta_data( '_shk_price_comment', $price_comment, true );
        }
        if ( '' !== $delivery_comment ) {
            $rate->add_meta_data( '_shk_delivery_comment', $delivery_comment, true );
        }
        if ( '' !== $tariff_code ) {
            $rate->add_meta_data( '_shk_sdek_tariff_code', $tariff_code, true );
        }

        $new_id = (string) $rate_id;
        if ( 'official_cdek' === $method_id && '' !== $tariff_code && false === strpos( $new_id, '__tariff_' ) ) {
            $new_id .= '__tariff_' . $tariff_code;
            $rate = shikkosa_clone_rate_with_label_and_cost(
                $rate,
                $new_id,
                method_exists( $rate, 'get_label' ) ? (string) $rate->get_label() : '',
                method_exists( $rate, 'get_cost' ) ? (float) $rate->get_cost() : 0.0
            );
        }

        $new_rates[ $new_id ] = $rate;
    }

    return $new_rates;
}

function shikkosa_zone_official_cdek_inline_points( $package ) {
    $rows = array();
    if ( ! class_exists( 'WC_Shipping_Zones' ) || ! method_exists( 'WC_Shipping_Zones', 'get_zone_matching_package' ) ) {
        return $rows;
    }

    static $zone_methods_cache = array();
    $dest = isset( $package['destination'] ) && is_array( $package['destination'] ) ? $package['destination'] : array();
    $cache_key = md5(
        wp_json_encode(
            array(
                'country'  => isset( $dest['country'] ) ? (string) $dest['country'] : '',
                'state'    => isset( $dest['state'] ) ? (string) $dest['state'] : '',
                'postcode' => isset( $dest['postcode'] ) ? (string) $dest['postcode'] : '',
                'city'     => isset( $dest['city'] ) ? (string) $dest['city'] : '',
            )
        )
    );

    if ( isset( $zone_methods_cache[ $cache_key ] ) && is_array( $zone_methods_cache[ $cache_key ] ) ) {
        $methods = $zone_methods_cache[ $cache_key ];
    } else {
        $zone = WC_Shipping_Zones::get_zone_matching_package( is_array( $package ) ? $package : array() );
        if ( ! $zone || ! is_a( $zone, 'WC_Shipping_Zone' ) ) {
            return $rows;
        }
        $methods = $zone->get_shipping_methods( true );
        $zone_methods_cache[ $cache_key ] = is_array( $methods ) ? $methods : array();
    }
    if ( ! is_array( $methods ) ) {
        return $rows;
    }

    foreach ( $methods as $method ) {
        if ( ! $method || ! is_object( $method ) ) {
            continue;
        }
        $method_id = isset( $method->id ) ? (string) $method->id : '';
        if ( 'official_cdek' !== $method_id ) {
            continue;
        }
        $enabled = isset( $method->enabled ) ? (string) $method->enabled : 'yes';
        if ( 'yes' !== $enabled ) {
            continue;
        }
        $instance_id = isset( $method->instance_id ) ? (int) $method->instance_id : 0;
        if ( $instance_id <= 0 ) {
            continue;
        }

        $settings = shikkosa_rate_settings_by_instance( 'official_cdek', $instance_id );
        $tariff_raw = isset( $settings['shk_sdek_tariff_code'] ) ? (string) $settings['shk_sdek_tariff_code'] : '';
        $inline_raw = isset( $settings['shk_sdek_inline_points'] ) ? (string) $settings['shk_sdek_inline_points'] : '';
        if ( '' === trim( $inline_raw ) ) {
            $inline_raw = $tariff_raw;
        }
        $inline_points = shikkosa_parse_inline_sdek_points( $inline_raw );
        if ( empty( $inline_points ) ) {
            continue;
        }

        foreach ( $inline_points as $idx => $point ) {
            $label = isset( $point['label'] ) ? sanitize_text_field( (string) $point['label'] ) : '';
            $tariff = isset( $point['tariff_code'] ) ? preg_replace( '/[^0-9]/', '', (string) $point['tariff_code'] ) : '';
            $price = isset( $point['price'] ) ? trim( (string) $point['price'] ) : '';
            $price_comment = isset( $point['price_comment'] ) ? sanitize_text_field( (string) $point['price_comment'] ) : '';
            $delivery_comment = isset( $point['delivery_comment'] ) ? sanitize_text_field( (string) $point['delivery_comment'] ) : '';
            if ( '' === $label || '' === $tariff ) {
                continue;
            }
            $rows[] = array(
                'instance_id' => $instance_id,
                'index' => (int) $idx + 1,
                'label' => $label,
                'tariff_code' => $tariff,
                'price' => $price,
                'price_comment' => $price_comment,
                'delivery_comment' => $delivery_comment,
            );
        }
    }

    return $rows;
}

function shikkosa_append_inline_points_without_source_rate( $rates, $package ) {
    $rates = is_array( $rates ) ? $rates : array();

    foreach ( $rates as $rid => $rate ) {
        if ( false !== strpos( (string) $rid, '__shk_point_' ) ) {
            return $rates;
        }
    }

    $rows = shikkosa_zone_official_cdek_inline_points( $package );
    if ( empty( $rows ) ) {
        return $rates;
    }

    foreach ( $rows as $row ) {
        $instance_id = isset( $row['instance_id'] ) ? (int) $row['instance_id'] : 0;
        $idx = isset( $row['index'] ) ? (int) $row['index'] : 1;
        $label = isset( $row['label'] ) ? (string) $row['label'] : '';
        $tariff = isset( $row['tariff_code'] ) ? (string) $row['tariff_code'] : '';
        $price_raw = isset( $row['price'] ) ? trim( (string) $row['price'] ) : '';
        $cost = ( '' !== $price_raw && is_numeric( $price_raw ) ) ? max( 0.0, (float) $price_raw ) : 0.0;
        if ( '' === $label || '' === $tariff ) {
            continue;
        }

        $new_id = 'official_cdek:' . $instance_id . '__shk_point_' . $idx . '__tariff_' . $tariff;
        $rate = shikkosa_sdek_synthetic_rate( $new_id, $label, $cost, 'official_cdek', $instance_id );
        $rate->add_meta_data( '_shk_sdek_tariff_code', $tariff, true );

        $price_comment = isset( $row['price_comment'] ) ? trim( (string) $row['price_comment'] ) : '';
        $delivery_comment = isset( $row['delivery_comment'] ) ? trim( (string) $row['delivery_comment'] ) : '';
        if ( '' !== $price_comment ) {
            $rate->add_meta_data( '_shk_price_comment', $price_comment, true );
        }
        if ( '' !== $delivery_comment ) {
            $rate->add_meta_data( '_shk_delivery_comment', $delivery_comment, true );
        }

        $rates[ $new_id ] = $rate;
    }

    return $rates;
}

function shikkosa_reorder_rates_by_zone_methods( $rates, $package ) {
    $rates = is_array( $rates ) ? $rates : array();
    if ( empty( $rates ) || ! class_exists( 'WC_Shipping_Zones' ) || ! method_exists( 'WC_Shipping_Zones', 'get_zone_matching_package' ) ) {
        return $rates;
    }

    $zone = WC_Shipping_Zones::get_zone_matching_package( is_array( $package ) ? $package : array() );
    if ( ! $zone || ! is_a( $zone, 'WC_Shipping_Zone' ) ) {
        return $rates;
    }
    $methods = $zone->get_shipping_methods( true );
    if ( ! is_array( $methods ) || empty( $methods ) ) {
        return $rates;
    }

    $buckets = array();
    $bucket_order = array();
    $raw_tail = array();

    foreach ( $rates as $rate_id => $rate ) {
        if ( ! is_object( $rate ) || ! is_a( $rate, 'WC_Shipping_Rate' ) ) {
            $raw_tail[ $rate_id ] = $rate;
            continue;
        }
        $method_id = method_exists( $rate, 'get_method_id' ) ? (string) $rate->get_method_id() : '';
        $instance_id = method_exists( $rate, 'get_instance_id' ) ? (int) $rate->get_instance_id() : 0;
        $key = $method_id . ':' . $instance_id;
        if ( ! isset( $buckets[ $key ] ) ) {
            $buckets[ $key ] = array();
            $bucket_order[] = $key;
        }
        $buckets[ $key ][ $rate_id ] = $rate;
    }

    $ordered = array();
    $used_keys = array();

    foreach ( $methods as $method ) {
        if ( ! $method || ! is_object( $method ) ) {
            continue;
        }
        $enabled = isset( $method->enabled ) ? (string) $method->enabled : 'yes';
        if ( 'yes' !== $enabled ) {
            continue;
        }
        $method_id = isset( $method->id ) ? (string) $method->id : '';
        $instance_id = isset( $method->instance_id ) ? (int) $method->instance_id : 0;
        $key = $method_id . ':' . $instance_id;
        if ( isset( $buckets[ $key ] ) ) {
            foreach ( $buckets[ $key ] as $rid => $rate ) {
                $ordered[ $rid ] = $rate;
            }
            $used_keys[ $key ] = true;
        }
    }

    foreach ( $bucket_order as $key ) {
        if ( isset( $used_keys[ $key ] ) ) {
            continue;
        }
        foreach ( $buckets[ $key ] as $rid => $rate ) {
            $ordered[ $rid ] = $rate;
        }
    }

    foreach ( $raw_tail as $rid => $rate ) {
        $ordered[ $rid ] = $rate;
    }

    return $ordered;
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
    $out['debug_timing'] = ( isset( $input['debug_timing'] ) && 'yes' === (string) $input['debug_timing'] ) ? 'yes' : 'no';
    $out['show_all_before_address'] = ( isset( $input['show_all_before_address'] ) && 'yes' === (string) $input['show_all_before_address'] ) ? 'yes' : 'no';
    $out['custom_rates'] = shikkosa_sdek_sanitize_custom_rates( isset( $input['custom_rates'] ) ? $input['custom_rates'] : array() );

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

    foreach ( array( 'cdek_door_door', 'cdek_door_warehouse', 'cdek_pickup', 'cdek_express_door_door' ) as $profile ) {
        $visible_key = $profile . '_visible';
        $out[ $visible_key ] = ( isset( $input[ $visible_key ] ) && 'yes' === (string) $input[ $visible_key ] ) ? 'yes' : 'no';

        $enabled_key = $profile . '_variant_enabled';
        $out[ $enabled_key ] = ( isset( $input[ $enabled_key ] ) && 'yes' === (string) $input[ $enabled_key ] ) ? 'yes' : 'no';

        $label_key = $profile . '_variant_label';
        $label_val = isset( $input[ $label_key ] ) ? $input[ $label_key ] : '';
        $out[ $label_key ] = is_scalar( $label_val ) ? sanitize_text_field( (string) $label_val ) : '';

        $variants_key = $profile . '_variants';
        $variants_raw = isset( $input[ $variants_key ] ) ? $input[ $variants_key ] : array();
        $out[ $variants_key ] = shikkosa_sdek_sanitize_variants_list( $variants_raw );
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

    foreach ( array( 'cdek_door_door', 'cdek_door_warehouse', 'cdek_pickup', 'cdek_express_door_door' ) as $profile ) {
        $price_key = $profile . '_variant_price';
        $price_val = isset( $input[ $price_key ] ) ? $input[ $price_key ] : '';
        if ( '' === trim( (string) $price_val ) ) {
            $out[ $price_key ] = '';
        } else {
            $out[ $price_key ] = is_scalar( $price_val ) ? (string) wc_format_decimal( (string) $price_val ) : '';
        }
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

    foreach ( array( 'cdek_door_door', 'cdek_door_warehouse', 'cdek_pickup', 'cdek_express_door_door' ) as $profile ) {
        foreach ( array( '_variant_price_comment', '_variant_delivery_comment' ) as $suffix ) {
            $k = $profile . $suffix;
            $value = isset( $input[ $k ] ) ? $input[ $k ] : '';
            $out[ $k ] = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
        }
    }

    foreach ( array( 'msk_no_fit_extra', 'msk_fit_extra', 'rf_no_fit_extra', 'rf_fit_extra' ) as $key ) {
        $value = isset( $input[ $key ] ) ? $input[ $key ] : $defaults[ $key ];
        $out[ $key ] = is_scalar( $value ) ? (string) wc_format_decimal( (string) $value ) : (string) $defaults[ $key ];
    }

    return $out;
}

function shikkosa_sdek_sanitize_variants_list( $raw_variants ) {
    if ( ! is_array( $raw_variants ) ) {
        return array();
    }

    $clean = array();
    foreach ( $raw_variants as $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }

        $label = isset( $row['label'] ) && is_scalar( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '';
        $price = isset( $row['price'] ) && is_scalar( $row['price'] ) ? trim( (string) $row['price'] ) : '';
        $price_comment = isset( $row['price_comment'] ) && is_scalar( $row['price_comment'] ) ? sanitize_text_field( (string) $row['price_comment'] ) : '';
        $delivery_comment = isset( $row['delivery_comment'] ) && is_scalar( $row['delivery_comment'] ) ? sanitize_text_field( (string) $row['delivery_comment'] ) : '';

        if ( '' !== $price ) {
            $price = (string) wc_format_decimal( $price );
        }

        if ( '' === $label && '' === $price && '' === $price_comment && '' === $delivery_comment ) {
            continue;
        }

        $clean[] = array(
            'label' => $label,
            'price' => $price,
            'price_comment' => $price_comment,
            'delivery_comment' => $delivery_comment,
        );
    }

    return $clean;
}

function shikkosa_sdek_sanitize_custom_rates( $raw_rates ) {
    if ( ! is_array( $raw_rates ) ) {
        return array();
    }
    $clean = array();
    foreach ( $raw_rates as $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }
        $enabled = ( isset( $row['enabled'] ) && 'yes' === (string) $row['enabled'] ) ? 'yes' : 'no';
        $label = isset( $row['label'] ) && is_scalar( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '';
        $price = isset( $row['price'] ) && is_scalar( $row['price'] ) ? trim( (string) $row['price'] ) : '';
        $price_comment = isset( $row['price_comment'] ) && is_scalar( $row['price_comment'] ) ? sanitize_text_field( (string) $row['price_comment'] ) : '';
        $delivery_comment = isset( $row['delivery_comment'] ) && is_scalar( $row['delivery_comment'] ) ? sanitize_text_field( (string) $row['delivery_comment'] ) : '';
        $tariff_code_raw = isset( $row['tariff_code'] ) && is_scalar( $row['tariff_code'] ) ? (string) $row['tariff_code'] : '';
        $tariff_code = preg_replace( '/[^0-9]/', '', $tariff_code_raw );

        if ( '' !== $price ) {
            $price = (string) wc_format_decimal( $price );
        }
        if ( '' === $label && '' === $price && '' === $price_comment && '' === $delivery_comment && '' === $tariff_code ) {
            continue;
        }
        $clean[] = array(
            'enabled' => $enabled,
            'label' => $label,
            'price' => $price,
            'price_comment' => $price_comment,
            'delivery_comment' => $delivery_comment,
            'tariff_code' => $tariff_code,
        );
    }
    return $clean;
}

function shikkosa_sdek_get_profile_variants( $settings, $profile ) {
    $list = array();
    $variants_key = $profile . '_variants';

    if ( isset( $settings[ $variants_key ] ) && is_array( $settings[ $variants_key ] ) ) {
        $list = shikkosa_sdek_sanitize_variants_list( $settings[ $variants_key ] );
    }

    // Backward compatibility: old single variant fields.
    if ( empty( $list ) && 'yes' === (string) ( $settings[ $profile . '_variant_enabled' ] ?? 'no' ) ) {
        $list[] = array(
            'label' => trim( (string) ( $settings[ $profile . '_variant_label' ] ?? '' ) ),
            'price' => trim( (string) ( $settings[ $profile . '_variant_price' ] ?? '' ) ),
            'price_comment' => trim( (string) ( $settings[ $profile . '_variant_price_comment' ] ?? '' ) ),
            'delivery_comment' => trim( (string) ( $settings[ $profile . '_variant_delivery_comment' ] ?? '' ) ),
        );
        $list = shikkosa_sdek_sanitize_variants_list( $list );
    }

    return array_values( $list );
}

function shikkosa_sdek_is_profile_visible( $settings, $profile ) {
    return 'no' !== (string) ( $settings[ $profile . '_visible' ] ?? 'yes' );
}

function shikkosa_sdek_profile_code_from_string( $s ) {
    $s = is_scalar( $s ) ? (string) $s : '';
    $s = function_exists( 'mb_strtolower' ) ? mb_strtolower( $s ) : strtolower( $s );
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

function shikkosa_sdek_rate_profile_code( $rate ) {
    return shikkosa_sdek_profile_code_from_string( shikkosa_sdek_rate_string( $rate ) );
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

function shikkosa_sdek_has_destination_address( $package ) {
    $dest = isset( $package['destination'] ) && is_array( $package['destination'] ) ? $package['destination'] : array();
    $city = isset( $dest['city'] ) ? trim( (string) $dest['city'] ) : '';
    $address1 = isset( $dest['address_1'] ) ? trim( (string) $dest['address_1'] ) : '';
    return ( '' !== $city || '' !== $address1 );
}

function shikkosa_sdek_synthetic_rate( $rate_id, $label, $cost, $method_id = 'official_cdek', $instance_id = 0 ) {
    return new WC_Shipping_Rate(
        (string) $rate_id,
        (string) $label,
        (float) $cost,
        array(),
        (string) $method_id,
        (int) $instance_id
    );
}

function shikkosa_sdek_append_custom_rates( $rates, $settings ) {
    $rates = is_array( $rates ) ? $rates : array();
    $custom_rates = isset( $settings['custom_rates'] ) && is_array( $settings['custom_rates'] ) ? $settings['custom_rates'] : array();
    $custom_rates = shikkosa_sdek_sanitize_custom_rates( $custom_rates );

    foreach ( $custom_rates as $idx => $row ) {
        if ( 'yes' !== (string) ( $row['enabled'] ?? 'yes' ) ) {
            continue;
        }
        $label = trim( (string) ( $row['label'] ?? '' ) );
        if ( '' === $label ) {
            continue;
        }
        $price_raw = trim( (string) ( $row['price'] ?? '' ) );
        $cost = ( '' !== $price_raw && is_numeric( $price_raw ) ) ? max( 0.0, (float) $price_raw ) : 0.0;
        $tariff_code = isset( $row['tariff_code'] ) ? preg_replace( '/[^0-9]/', '', (string) $row['tariff_code'] ) : '';

        $id = 'shk_custom_' . ( (int) $idx + 1 ) . ( '' !== $tariff_code ? '__tariff_' . $tariff_code : '' );
        $method_id = ( '' !== $tariff_code ) ? 'official_cdek' : 'shk_manual_delivery';
        $rate = shikkosa_sdek_synthetic_rate( $id, $label, $cost, $method_id, 0 );
        if ( '' !== $tariff_code ) {
            $rate->add_meta_data( '_shk_sdek_tariff_code', $tariff_code, true );
        }

        $price_comment = trim( (string) ( $row['price_comment'] ?? '' ) );
        $delivery_comment = trim( (string) ( $row['delivery_comment'] ?? '' ) );
        if ( '' !== $price_comment ) {
            $rate->add_meta_data( '_shk_price_comment', $price_comment, true );
        }
        if ( '' !== $delivery_comment ) {
            $rate->add_meta_data( '_shk_delivery_comment', $delivery_comment, true );
        }
        $rates[ $id ] = $rate;
    }

    return $rates;
}

function shikkosa_sdek_build_native_custom_rates( $incoming_rates, $settings ) {
    $incoming_rates = is_array( $incoming_rates ) ? $incoming_rates : array();
    $settings = is_array( $settings ) ? $settings : array();

    $custom_rates = isset( $settings['custom_rates'] ) && is_array( $settings['custom_rates'] ) ? $settings['custom_rates'] : array();
    $custom_rates = shikkosa_sdek_sanitize_custom_rates( $custom_rates );
    if ( empty( $custom_rates ) ) {
        return $incoming_rates;
    }

    $out = array();
    $first_cdek_source = null;

    foreach ( $incoming_rates as $rate_id => $rate ) {
        if ( ! is_object( $rate ) || ! is_a( $rate, 'WC_Shipping_Rate' ) ) {
            $out[ $rate_id ] = $rate;
            continue;
        }

        if ( shikkosa_is_cdek_rate( $rate ) ) {
            if ( null === $first_cdek_source ) {
                $first_cdek_source = $rate;
            }
            continue;
        }

        $out[ $rate_id ] = $rate;
    }

    foreach ( $custom_rates as $idx => $row ) {
        if ( 'yes' !== (string) ( $row['enabled'] ?? 'yes' ) ) {
            continue;
        }

        $label = trim( (string) ( $row['label'] ?? '' ) );
        if ( '' === $label ) {
            continue;
        }

        $price_raw = trim( (string) ( $row['price'] ?? '' ) );
        $cost = ( '' !== $price_raw && is_numeric( $price_raw ) ) ? max( 0.0, (float) $price_raw ) : 0.0;
        $tariff_code = isset( $row['tariff_code'] ) ? preg_replace( '/[^0-9]/', '', (string) $row['tariff_code'] ) : '';

        $id_base = 'shk_custom_' . ( (int) $idx + 1 );
        $new_id = $id_base . ( '' !== $tariff_code ? '__tariff_' . $tariff_code : '' );

        if ( '' !== $tariff_code ) {
            if ( $first_cdek_source && is_a( $first_cdek_source, 'WC_Shipping_Rate' ) ) {
                $rate = shikkosa_clone_rate_with_label_and_cost( $first_cdek_source, $new_id, $label, $cost );
            } else {
                $rate = shikkosa_sdek_synthetic_rate( $new_id, $label, $cost, 'official_cdek', 0 );
            }
            $rate->add_meta_data( '_shk_sdek_tariff_code', $tariff_code, true );
        } else {
            $rate = shikkosa_sdek_synthetic_rate( $new_id, $label, $cost, 'shk_manual_delivery', 0 );
        }

        $price_comment = trim( (string) ( $row['price_comment'] ?? '' ) );
        $delivery_comment = trim( (string) ( $row['delivery_comment'] ?? '' ) );
        if ( '' !== $price_comment ) {
            $rate->add_meta_data( '_shk_price_comment', $price_comment, true );
        }
        if ( '' !== $delivery_comment ) {
            $rate->add_meta_data( '_shk_delivery_comment', $delivery_comment, true );
        }

        $out[ $new_id ] = $rate;
    }

    return $out;
}

function shikkosa_sdek_build_prerates_without_address( $rates, $settings ) {
    $title_map = shikkosa_sdek_profile_titles();
    $active_profiles = shikkosa_sdek_detect_active_general_profiles();
    if ( empty( $active_profiles ) ) {
        $active_profiles = array_keys( $title_map );
    }

    $new_rates = array();
    $source_by_profile = array();
    $first_cdek_source = null;

    foreach ( $rates as $rate_id => $rate ) {
        if ( ! is_object( $rate ) || ! is_a( $rate, 'WC_Shipping_Rate' ) ) {
            $new_rates[ $rate_id ] = $rate;
            continue;
        }
        if ( ! shikkosa_is_cdek_rate( $rate ) ) {
            $new_rates[ $rate_id ] = $rate;
            continue;
        }

        if ( null === $first_cdek_source ) {
            $first_cdek_source = $rate;
        }
        $profile = shikkosa_sdek_rate_profile_code( $rate );
        if ( '' !== $profile && ! isset( $source_by_profile[ $profile ] ) ) {
            $source_by_profile[ $profile ] = $rate;
        }
    }

    foreach ( $active_profiles as $profile ) {
        if ( ! isset( $title_map[ $profile ] ) ) {
            continue;
        }
        if ( ! shikkosa_sdek_is_profile_visible( $settings, $profile ) ) {
            continue;
        }

        $source = isset( $source_by_profile[ $profile ] ) ? $source_by_profile[ $profile ] : $first_cdek_source;
        $base_label = '';
        $base_cost = 0.0;

        if ( $source && is_a( $source, 'WC_Shipping_Rate' ) ) {
            $base_label = method_exists( $source, 'get_label' ) ? (string) $source->get_label() : '';
            $base_cost = method_exists( $source, 'get_cost' ) ? (float) $source->get_cost() : 0.0;
        }

        $custom_label = isset( $settings[ $profile . '_label' ] ) ? trim( (string) $settings[ $profile . '_label' ] ) : '';
        $label = '' !== $custom_label ? $custom_label : ( '' !== $base_label ? $base_label : $title_map[ $profile ] );
        $fixed_price = isset( $settings[ $profile . '_price' ] ) ? trim( (string) $settings[ $profile . '_price' ] ) : '';
        $cost = ( '' !== $fixed_price && is_numeric( $fixed_price ) ) ? max( 0.0, (float) $fixed_price ) : max( 0.0, (float) $base_cost );

        $base_id = 'shk_pre_cdek_' . $profile;
        if ( $source && is_a( $source, 'WC_Shipping_Rate' ) ) {
            $base_rate = shikkosa_clone_rate_with_label_and_cost( $source, $base_id, $label, $cost );
        } else {
            $base_rate = shikkosa_sdek_synthetic_rate( $base_id, $label, $cost );
        }

        $price_comment = isset( $settings[ $profile . '_price_comment' ] ) ? trim( (string) $settings[ $profile . '_price_comment' ] ) : '';
        $delivery_comment = isset( $settings[ $profile . '_delivery_comment' ] ) ? trim( (string) $settings[ $profile . '_delivery_comment' ] ) : '';
        if ( '' !== $price_comment ) {
            $base_rate->add_meta_data( '_shk_price_comment', $price_comment, true );
        }
        if ( '' !== $delivery_comment ) {
            $base_rate->add_meta_data( '_shk_delivery_comment', $delivery_comment, true );
        }

        $new_rates[ $base_id ] = $base_rate;

        $variants = shikkosa_sdek_get_profile_variants( $settings, $profile );
        foreach ( $variants as $idx => $variant_data ) {
            $variant_num = (int) $idx + 1;
            $variant_label = trim( (string) ( $variant_data['label'] ?? '' ) );
            if ( '' === $variant_label ) {
                $variant_label = $label . ' (доп. вариант ' . $variant_num . ')';
            }
            $variant_price_raw = trim( (string) ( $variant_data['price'] ?? '' ) );
            $variant_cost = ( '' !== $variant_price_raw && is_numeric( $variant_price_raw ) ) ? max( 0.0, (float) $variant_price_raw ) : $cost;

            $variant_id = $base_id . '__shk_variant_' . $profile . '_' . $variant_num;
            $variant_rate = $source && is_a( $source, 'WC_Shipping_Rate' )
                ? shikkosa_clone_rate_with_label_and_cost( $source, $variant_id, $variant_label, $variant_cost )
                : shikkosa_sdek_synthetic_rate( $variant_id, $variant_label, $variant_cost );

            $variant_price_comment = trim( (string) ( $variant_data['price_comment'] ?? '' ) );
            $variant_delivery_comment = trim( (string) ( $variant_data['delivery_comment'] ?? '' ) );
            if ( '' !== $variant_price_comment ) {
                $variant_rate->add_meta_data( '_shk_price_comment', $variant_price_comment, true );
            }
            if ( '' !== $variant_delivery_comment ) {
                $variant_rate->add_meta_data( '_shk_delivery_comment', $variant_delivery_comment, true );
            }

            $new_rates[ $variant_id ] = $variant_rate;
        }
    }

    return $new_rates;
}

function shikkosa_sdek_manual_rows_from_settings( $settings ) {
    $rows = array();

    $general_profiles = array(
        'cdek_express_door_door',
        'cdek_door_door',
        'cdek_door_warehouse',
        'cdek_pickup',
    );

    foreach ( $general_profiles as $code ) {
        if ( ! shikkosa_sdek_is_profile_visible( $settings, $code ) ) {
            continue;
        }
        $label = isset( $settings[ $code . '_label' ] ) ? trim( (string) $settings[ $code . '_label' ] ) : '';
        if ( '' === $label ) {
            continue;
        }
        $rows[] = array(
            'code'             => $code,
            'label'            => $label,
            'price_key'        => $code . '_price',
            'price_comment_key'    => $code . '_price_comment',
            'delivery_comment_key' => $code . '_delivery_comment',
        );
    }

    $split_rows = array( 'msk_no_fit', 'msk_fit', 'rf_no_fit', 'rf_fit' );
    foreach ( $split_rows as $code ) {
        $label = isset( $settings[ $code . '_label' ] ) ? trim( (string) $settings[ $code . '_label' ] ) : '';
        if ( '' === $label ) {
            continue;
        }
        $rows[] = array(
            'code'             => $code,
            'label'            => $label,
            'price_key'        => $code . '_price',
            'price_comment_key'    => $code . '_price_comment',
            'delivery_comment_key' => $code . '_delivery_comment',
        );
    }

    return $rows;
}

function shikkosa_sdek_build_static_rates_from_settings( $rates, $settings ) {
    $rates = is_array( $rates ) ? $rates : array();
    $new_rates = array();
    $first_cdek_source = null;

    foreach ( $rates as $rate_id => $rate ) {
        if ( ! is_object( $rate ) || ! is_a( $rate, 'WC_Shipping_Rate' ) ) {
            $new_rates[ $rate_id ] = $rate;
            continue;
        }

        if ( shikkosa_is_cdek_rate( $rate ) ) {
            if ( null === $first_cdek_source ) {
                $first_cdek_source = $rate;
            }
            continue;
        }

        $new_rates[ $rate_id ] = $rate;
    }

    $custom_rates = isset( $settings['custom_rates'] ) && is_array( $settings['custom_rates'] ) ? shikkosa_sdek_sanitize_custom_rates( $settings['custom_rates'] ) : array();
    $has_custom_rows = ! empty( $custom_rates );

    if ( ! $has_custom_rows ) {
        $rows = shikkosa_sdek_manual_rows_from_settings( $settings );
        foreach ( $rows as $row ) {
            $code = (string) ( $row['code'] ?? '' );
            $label = (string) ( $row['label'] ?? '' );
            if ( '' === $code || '' === $label ) {
                continue;
            }

            $price_key = (string) ( $row['price_key'] ?? '' );
            $price_raw = '' !== $price_key && isset( $settings[ $price_key ] ) ? trim( (string) $settings[ $price_key ] ) : '';
            $cost = ( '' !== $price_raw && is_numeric( $price_raw ) ) ? max( 0.0, (float) $price_raw ) : 0.0;

            $rate_id = 'shk_manual_cdek_' . $code;
            $rate = ( $first_cdek_source && is_a( $first_cdek_source, 'WC_Shipping_Rate' ) )
                ? shikkosa_clone_rate_with_label_and_cost( $first_cdek_source, $rate_id, $label, $cost )
                : shikkosa_sdek_synthetic_rate( $rate_id, $label, $cost );

            $price_comment_key = (string) ( $row['price_comment_key'] ?? '' );
            $delivery_comment_key = (string) ( $row['delivery_comment_key'] ?? '' );

            $price_comment = '' !== $price_comment_key && isset( $settings[ $price_comment_key ] ) ? trim( (string) $settings[ $price_comment_key ] ) : '';
            $delivery_comment = '' !== $delivery_comment_key && isset( $settings[ $delivery_comment_key ] ) ? trim( (string) $settings[ $delivery_comment_key ] ) : '';

            if ( '' !== $price_comment ) {
                $rate->add_meta_data( '_shk_price_comment', $price_comment, true );
            }
            if ( '' !== $delivery_comment ) {
                $rate->add_meta_data( '_shk_delivery_comment', $delivery_comment, true );
            }

            $new_rates[ $rate_id ] = $rate;
        }
    }

    $new_rates = shikkosa_sdek_append_custom_rates( $new_rates, $settings );
    return $new_rates;
}

add_filter( 'woocommerce_package_rates', 'shikkosa_split_cdek_pickup_rates', 120, 2 );
function shikkosa_split_cdek_pickup_rates( $rates, $package ) {
    if ( shikkosa_use_native_woo_shipping_mode() ) {
        $native_rates = shikkosa_apply_instance_meta_to_rates( $rates );
        $native_rates = shikkosa_append_inline_points_without_source_rate( $native_rates, $package );
        return shikkosa_reorder_rates_by_zone_methods( $native_rates, $package );
    }

    $settings = shikkosa_sdek_settings();
    $started_at = microtime( true );
    $rates = is_array( $rates ) ? $rates : array();

    $show_all_before_address = ( 'yes' === (string) ( $settings['show_all_before_address'] ?? 'yes' ) );
    if ( $show_all_before_address ) {
        $static_rates = shikkosa_sdek_build_static_rates_from_settings( $rates, $settings );
        if ( 'yes' === (string) $settings['debug_timing'] ) {
            $logger = wc_get_logger();
            $logger->info(
                '[SHK SDEK] package_rates: static SHK mode enabled, in=' . count( $rates ) . ', out=' . count( $static_rates ) . ', elapsed=' . round( ( microtime( true ) - $started_at ) * 1000, 2 ) . 'ms',
                array( 'source' => 'shk-sdek' )
            );
        }
        return $static_rates;
    }

    if ( empty( $rates ) ) {
        if ( 'yes' === (string) $settings['debug_timing' ] ) {
            $logger = wc_get_logger();
            $logger->info( '[SHK SDEK] package_rates: empty rates, elapsed=' . round( ( microtime( true ) - $started_at ) * 1000, 2 ) . 'ms', array( 'source' => 'shk-sdek' ) );
        }
        return $rates;
    }

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
        if ( '' !== $profile && ! shikkosa_sdek_is_profile_visible( $settings, $profile ) ) {
            continue;
        }

        $base_rate = shikkosa_sdek_apply_profile_overrides( $rate, $settings, $profile );
        $new_rates[ $rate_id ] = $base_rate;

        // Optional additional variants for the same detected CDEK profile.
        if ( ! $split_enabled || '' === $profile ) {
            continue;
        }

        $variants = shikkosa_sdek_get_profile_variants( $settings, $profile );
        if ( empty( $variants ) ) {
            continue;
        }

        $base_label = method_exists( $base_rate, 'get_label' ) ? (string) $base_rate->get_label() : ( method_exists( $rate, 'get_label' ) ? (string) $rate->get_label() : '' );
        $base_cost = method_exists( $base_rate, 'get_cost' ) ? (float) $base_rate->get_cost() : ( method_exists( $rate, 'get_cost' ) ? (float) $rate->get_cost() : 0.0 );

        foreach ( $variants as $idx => $variant_data ) {
            $variant_num = (int) $idx + 1;
            $variant_label = trim( (string) ( $variant_data['label'] ?? '' ) );
            if ( '' === $variant_label ) {
                $variant_label = $base_label . ' (доп. вариант ' . $variant_num . ')';
            }

            $variant_price_raw = trim( (string) ( $variant_data['price'] ?? '' ) );
            $variant_cost = ( '' !== $variant_price_raw && is_numeric( $variant_price_raw ) ) ? max( 0.0, (float) $variant_price_raw ) : $base_cost;

            $new_id = (string) $rate_id . '__shk_variant_' . $profile . '_' . $variant_num;
            $new_rate = shikkosa_clone_rate_with_label_and_cost( $base_rate, $new_id, $variant_label, $variant_cost );

            $variant_price_comment = trim( (string) ( $variant_data['price_comment'] ?? '' ) );
            $variant_delivery_comment = trim( (string) ( $variant_data['delivery_comment'] ?? '' ) );
            if ( '' !== $variant_price_comment ) {
                $new_rate->add_meta_data( '_shk_price_comment', $variant_price_comment, true );
            }
            if ( '' !== $variant_delivery_comment ) {
                $new_rate->add_meta_data( '_shk_delivery_comment', $variant_delivery_comment, true );
            }

            $new_rates[ $new_id ] = $new_rate;
        }
    }

    $new_rates = shikkosa_sdek_append_custom_rates( $new_rates, $settings );

    if ( 'yes' === (string) $settings['debug_timing'] ) {
        $logger = wc_get_logger();
        $logger->info(
            '[SHK SDEK] package_rates: in=' . count( $rates ) . ', out=' . count( $new_rates ) . ', split=' . ( $split_enabled ? 'yes' : 'no' ) . ', elapsed=' . round( ( microtime( true ) - $started_at ) * 1000, 2 ) . 'ms',
            array( 'source' => 'shk-sdek' )
        );
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

// Legacy left-menu page is disabled: settings are managed in WooCommerce -> Settings -> Shipping -> SHK СДЭК.
// add_action( 'admin_menu', 'shikkosa_sdek_settings_menu' );
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

add_filter( 'woocommerce_get_sections_shipping', 'shikkosa_sdek_add_shipping_section', 30 );
function shikkosa_sdek_add_shipping_section( $sections ) {
    if ( ! is_array( $sections ) ) {
        $sections = array();
    }
    $sections['shk_sdek'] = 'SHK Доставка';
    return $sections;
}

add_filter( 'woocommerce_get_settings_shipping', 'shikkosa_sdek_get_shipping_settings_section', 30, 2 );
function shikkosa_sdek_get_shipping_settings_section( $settings, $current_section ) {
    if ( 'shk_sdek' !== (string) $current_section ) {
        return $settings;
    }

    return array(
        array(
            'name' => 'SHK Доставка',
            'type' => 'title',
            'id'   => 'shikkosa_sdek_wc_section',
            'desc' => 'Редактирование названий, стоимости, комментариев и видимости вариантов доставки.',
        ),
        array(
            'type' => 'shk_sdek_manager',
            'id'   => 'shikkosa_sdek_manager',
        ),
        array(
            'type' => 'sectionend',
            'id'   => 'shikkosa_sdek_wc_section',
        ),
    );
}

add_action( 'woocommerce_update_options_shipping_shk_sdek', 'shikkosa_sdek_save_from_wc_shipping_section' );
add_action( 'woocommerce_update_options_shipping', 'shikkosa_sdek_save_from_wc_shipping_section_fallback', 20 );
add_action( 'woocommerce_settings_save_shipping', 'shikkosa_sdek_save_from_wc_shipping_section_fallback', 20 );
function shikkosa_sdek_save_from_wc_shipping_section() {
    $raw = isset( $_POST['shikkosa_sdek_settings'] ) ? wp_unslash( $_POST['shikkosa_sdek_settings'] ) : array();
    $sanitized = shikkosa_sdek_settings_sanitize( $raw );
    update_option( 'shikkosa_sdek_settings', $sanitized );
}

function shikkosa_sdek_save_from_wc_shipping_section_fallback() {
    $section = '';
    if ( isset( $_REQUEST['section'] ) ) {
        $section = sanitize_key( (string) wp_unslash( $_REQUEST['section'] ) );
    } elseif ( isset( $_GET['section'] ) ) {
        $section = sanitize_key( (string) $_GET['section'] );
    }
    if ( 'shk_sdek' !== $section ) {
        return;
    }
    shikkosa_sdek_save_from_wc_shipping_section();
}

function shikkosa_sdek_profile_titles() {
    return array(
        'cdek_door_door'         => 'СДЭК дверь-дверь',
        'cdek_door_warehouse'    => 'СДЭК дверь-склад/пункт',
        'cdek_pickup'            => 'СДЭК ПВЗ/самовывоз',
        'cdek_express_door_door' => 'СДЭК Экспресс дверь-дверь',
    );
}

function shikkosa_sdek_is_truthy_value( $value ) {
    if ( is_bool( $value ) ) {
        return $value;
    }
    if ( is_numeric( $value ) ) {
        return (float) $value > 0;
    }
    if ( ! is_scalar( $value ) ) {
        return false;
    }
    $v = strtolower( trim( (string) $value ) );
    return in_array( $v, array( '1', 'yes', 'true', 'on', 'enabled' ), true );
}

function shikkosa_sdek_detect_profile_from_text( $text ) {
    $text = is_scalar( $text ) ? (string) $text : '';
    $text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
    if ( '' === $text ) {
        return '';
    }

    $is_express = ( false !== strpos( $text, 'экспресс' ) || false !== strpos( $text, 'express' ) );
    $has_door   = ( false !== strpos( $text, 'двер' ) || false !== strpos( $text, 'door' ) || false !== strpos( $text, 'courier' ) || false !== strpos( $text, 'курьер' ) );
    $has_sklad  = ( false !== strpos( $text, 'склад' ) || false !== strpos( $text, 'warehouse' ) );
    $has_pickup = ( false !== strpos( $text, 'пвз' ) || false !== strpos( $text, 'пункт' ) || false !== strpos( $text, 'pickup' ) || false !== strpos( $text, 'самовывоз' ) || false !== strpos( $text, 'office' ) );

    if ( $is_express && $has_door ) {
        return 'cdek_express_door_door';
    }
    if ( $has_door && $has_sklad ) {
        return 'cdek_door_warehouse';
    }
    if ( $has_pickup ) {
        return 'cdek_pickup';
    }
    if ( $has_door ) {
        return 'cdek_door_door';
    }

    return '';
}

function shikkosa_sdek_profile_from_tariff_id( $tariff_id ) {
    $id = (int) $tariff_id;
    if ( $id <= 0 ) {
        return '';
    }

    // Common CDEK tariff ids grouped by last-mile type used in checkout.
    // Express door-door ids are separated so they can be tuned independently.
    $express_door_door = array( 7, 8, 121, 122, 293, 480 );
    $door_warehouse    = array( 123, 138, 187, 232, 295, 481, 749 );
    $pickup_like       = array( 62, 136, 185, 234, 291, 366, 368, 376, 378, 483, 485, 486, 497, 498, 751 );
    $door_door_like    = array( 137, 139, 184, 186, 231, 233, 294, 482, 748, 750 );

    if ( in_array( $id, $express_door_door, true ) ) {
        return 'cdek_express_door_door';
    }
    if ( in_array( $id, $door_warehouse, true ) ) {
        return 'cdek_door_warehouse';
    }
    if ( in_array( $id, $pickup_like, true ) ) {
        return 'cdek_pickup';
    }
    if ( in_array( $id, $door_door_like, true ) ) {
        return 'cdek_door_door';
    }

    return '';
}

function shikkosa_sdek_extract_tariff_ids_from_value( $value ) {
    $ids = array();

    if ( is_array( $value ) ) {
        foreach ( $value as $item ) {
            foreach ( shikkosa_sdek_extract_tariff_ids_from_value( $item ) as $id ) {
                $ids[ $id ] = true;
            }
        }
        return array_keys( $ids );
    }

    if ( ! is_scalar( $value ) ) {
        return array();
    }

    $text = trim( (string) $value );
    if ( '' === $text ) {
        return array();
    }

    if ( preg_match_all( '/\b\d{1,4}\b/u', $text, $m ) ) {
        foreach ( $m[0] as $num ) {
            $id = (int) $num;
            if ( $id > 0 ) {
                $ids[ $id ] = true;
            }
        }
    }

    return array_keys( $ids );
}

function shikkosa_sdek_collect_profiles_from_tariff_ids_text( $text ) {
    $profiles = array();
    if ( ! is_scalar( $text ) ) {
        return $profiles;
    }
    $raw = (string) $text;
    if ( '' === trim( $raw ) ) {
        return $profiles;
    }
    if ( ! preg_match_all( '/\b\d{1,4}\b/u', $raw, $m ) ) {
        return $profiles;
    }
    foreach ( $m[0] as $num ) {
        $profile = shikkosa_sdek_profile_from_tariff_id( (int) $num );
        if ( '' !== $profile ) {
            $profiles[ $profile ] = true;
        }
    }
    $codes = array_keys( $profiles );
    $codes = apply_filters( 'shikkosa_sdek_active_profiles', $codes, $profiles );
    return array_values( array_filter( array_unique( is_array( $codes ) ? $codes : array() ) ) );
}

function shikkosa_sdek_detect_profiles_from_tariff_ids( $ids ) {
    $profiles = array();
    foreach ( shikkosa_sdek_extract_tariff_ids_from_value( $ids ) as $id ) {
        $profile = shikkosa_sdek_profile_from_tariff_id( $id );
        if ( '' !== $profile ) {
            $profiles[ $profile ] = true;
        }
    }
    return array_keys( $profiles );
}

function shikkosa_sdek_detect_profiles_from_global_options() {
    $profiles = array();

    $direct_list = get_option( 'woocommerce_official_cdek_tariff_list', array() );
    foreach ( shikkosa_sdek_detect_profiles_from_tariff_ids( $direct_list ) as $p ) {
        $profiles[ $p ] = true;
    }

    $settings = get_option( 'woocommerce_official_cdek_settings', array() );
    if ( is_array( $settings ) ) {
        $keys = array(
            'tariff_list',
            'official_cdek_tariff_list',
            'tariffs',
        );
        foreach ( $keys as $k ) {
            if ( isset( $settings[ $k ] ) ) {
                foreach ( shikkosa_sdek_detect_profiles_from_tariff_ids( $settings[ $k ] ) as $p ) {
                    $profiles[ $p ] = true;
                }
            }
        }
    }

    return array_keys( $profiles );
}

function shikkosa_sdek_detect_profiles_from_method_settings( $method ) {
    $profiles = array();
    if ( ! $method || ! is_object( $method ) ) {
        return $profiles;
    }

    $settings = array();
    if ( isset( $method->instance_settings ) && is_array( $method->instance_settings ) ) {
        $settings = $method->instance_settings;
    } elseif ( method_exists( $method, 'get_instance_settings' ) ) {
        $tmp = $method->get_instance_settings();
        if ( is_array( $tmp ) ) {
            $settings = $tmp;
        }
    }
    if ( empty( $settings ) ) {
        return $profiles;
    }

    foreach ( $settings as $key => $value ) {
        $key_lc = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $key ) : strtolower( (string) $key );

        if ( is_array( $value ) ) {
            foreach ( $value as $item ) {
                $profile = shikkosa_sdek_detect_profile_from_text( $key_lc . ' ' . ( is_scalar( $item ) ? (string) $item : '' ) );
                if ( '' !== $profile ) {
                    $profiles[ $profile ] = true;
                }
                if ( is_scalar( $item ) ) {
                    foreach ( shikkosa_sdek_collect_profiles_from_tariff_ids_text( (string) $item ) as $p ) {
                        $profiles[ $p ] = true;
                    }
                }
            }
            continue;
        }

        if ( ! is_scalar( $value ) ) {
            continue;
        }
        $value_lc = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $value ) : strtolower( (string) $value );

        $by_value = shikkosa_sdek_detect_profile_from_text( $value_lc );
        if ( '' !== $by_value ) {
            $profiles[ $by_value ] = true;
        }
        foreach ( shikkosa_sdek_collect_profiles_from_tariff_ids_text( $value_lc ) as $p ) {
            $profiles[ $p ] = true;
        }

        // Many plugins store selected profiles in boolean-like toggles per key.
        if ( shikkosa_sdek_is_truthy_value( $value ) ) {
            $by_key = shikkosa_sdek_detect_profile_from_text( $key_lc );
            if ( '' !== $by_key ) {
                $profiles[ $by_key ] = true;
            }
            foreach ( shikkosa_sdek_collect_profiles_from_tariff_ids_text( $key_lc ) as $p ) {
                $profiles[ $p ] = true;
            }
        }
    }

    return array_keys( $profiles );
}

function shikkosa_sdek_detect_active_general_profiles() {
    $profiles = array();

    if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
        return $profiles;
    }

    $zone_objects = array();
    $zones = WC_Shipping_Zones::get_zones();
    if ( is_array( $zones ) ) {
        foreach ( $zones as $zone_data ) {
            if ( empty( $zone_data['zone_id'] ) ) {
                continue;
            }
            $zone_objects[] = new WC_Shipping_Zone( (int) $zone_data['zone_id'] );
        }
    }
    $zone_objects[] = WC_Shipping_Zones::get_zone( 0 ); // Locations not covered by your other zones.

    foreach ( $zone_objects as $zone ) {
        if ( ! $zone || ! is_a( $zone, 'WC_Shipping_Zone' ) ) {
            continue;
        }
        $methods = $zone->get_shipping_methods( true );
        foreach ( $methods as $method ) {
            if ( ! $method || ! is_object( $method ) ) {
                continue;
            }
            $enabled = isset( $method->enabled ) ? (string) $method->enabled : 'yes';
            if ( 'yes' !== $enabled ) {
                continue;
            }

            $detected_from_settings = shikkosa_sdek_detect_profiles_from_method_settings( $method );
            foreach ( $detected_from_settings as $code ) {
                if ( '' !== $code ) {
                    $profiles[ $code ] = true;
                }
            }
            if ( ! empty( $detected_from_settings ) ) {
                continue;
            }

            $pieces = array(
                isset( $method->id ) ? (string) $method->id : '',
                isset( $method->method_title ) ? (string) $method->method_title : '',
                isset( $method->title ) ? (string) $method->title : '',
                get_class( $method ),
            );
            $hay = implode( ' ', array_filter( $pieces ) );
            $hay_lc = function_exists( 'mb_strtolower' ) ? mb_strtolower( $hay ) : strtolower( $hay );
            if (
                false === strpos( $hay_lc, 'cdek' ) &&
                false === strpos( $hay_lc, 'sdek' ) &&
                false === strpos( $hay_lc, 'сдэк' ) &&
                false === strpos( $hay_lc, 'сдек' ) &&
                false === strpos( $hay_lc, 'edostavka' )
            ) {
                continue;
            }

            $code = shikkosa_sdek_detect_profile_from_text( $hay_lc );
            if ( '' !== $code ) {
                $profiles[ $code ] = true;
            }
        }
    }

    foreach ( shikkosa_sdek_detect_profiles_from_global_options() as $code ) {
        if ( '' !== $code ) {
            $profiles[ $code ] = true;
        }
    }

    return array_keys( $profiles );
}

add_action( 'woocommerce_admin_field_shk_sdek_manager', 'shikkosa_sdek_render_wc_shipping_manager' );
function shikkosa_sdek_render_wc_shipping_manager() {
    $opt = shikkosa_sdek_settings();
    $title_map = shikkosa_sdek_profile_titles();
    $active_profiles = shikkosa_sdek_detect_active_general_profiles();

    if ( empty( $active_profiles ) ) {
        foreach ( $title_map as $profile_code => $profile_title ) {
            $has_any_value = ! empty( $opt[ $profile_code . '_label' ] ) || ! empty( $opt[ $profile_code . '_price' ] ) || ! empty( $opt[ $profile_code . '_price_comment' ] ) || ! empty( $opt[ $profile_code . '_delivery_comment' ] );
            if ( $has_any_value ) {
                $active_profiles[] = $profile_code;
            }
        }
    }

    $detected_empty = empty( $active_profiles );
    if ( $detected_empty ) {
        $active_profiles = array_keys( $title_map );
    }

    ?>
    <style>
        .shk-sdek-inline-table{width:100%;border-collapse:collapse;margin:10px 0 18px;table-layout:fixed}
        .shk-sdek-inline-table th,.shk-sdek-inline-table td{border:1px solid #dcdcde;padding:8px;vertical-align:top}
        .shk-sdek-inline-table th{background:#f6f7f7;font-weight:600;text-align:left}
        .shk-sdek-inline-table td input[type="text"],.shk-sdek-inline-table td input[type="number"]{width:100%;box-sizing:border-box}
        .shk-sdek-inline-table .shk-extra-row{background:#fbfbfc}
        .shk-sdek-inline-table .shk-extra-row[hidden]{display:none}
        .shk-sdek-inline-table .shk-extra-label{font-weight:600;color:#50575e;margin-bottom:2px}
        .shk-sdek-plus-btn{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border:1px solid #2271b1;color:#2271b1;background:#fff;border-radius:4px;font-size:20px;line-height:1;cursor:pointer}
        .shk-sdek-plus-btn:hover{background:#f0f6fc}
        .shk-sdek-variant-item{border:1px solid #dcdcde;background:#fff;padding:8px;margin-bottom:8px}
        .shk-sdek-variant-item-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
        .shk-sdek-variant-item-title{font-weight:600}
        .shk-sdek-variant-drag{cursor:move;color:#646970;font-size:16px;padding:0 8px}
        .shk-sdek-variant-del{border:1px solid #b32d2e;color:#b32d2e;background:#fff;border-radius:4px;padding:2px 8px;cursor:pointer}
        .shk-sdek-variant-grid{display:grid;grid-template-columns:1.2fr 0.7fr 1fr 1.1fr;gap:8px}
        .shk-sdek-empty{color:#646970;font-style:italic}
        .shk-sdek-sort-wrap{display:flex;align-items:center;gap:8px}
        .shk-sdek-variant-item.is-dragging{opacity:.55}
        .shk-sdek-variant-item.is-drop-target{outline:2px dashed #2271b1}
    </style>

    <p>
        <label>
            <input type="checkbox" name="shikkosa_sdek_settings[enabled]" value="yes" <?php checked( $opt['enabled'], 'yes' ); ?> />
            Включить разделение ПВЗ
        </label>
    </p>
    <p>
        <label>
            <input type="checkbox" name="shikkosa_sdek_settings[debug_timing]" value="yes" <?php checked( isset( $opt['debug_timing'] ) ? $opt['debug_timing'] : 'no', 'yes' ); ?> />
            Включить лог времени SHK СДЭК (WooCommerce -> Статус -> Логи -> source: <code>shk-sdek</code>)
        </label>
    </p>
    <p>
        <label>
            <input type="checkbox" name="shikkosa_sdek_settings[show_all_before_address]" value="yes" <?php checked( isset( $opt['show_all_before_address'] ) ? $opt['show_all_before_address'] : 'yes', 'yes' ); ?> />
            Показывать все варианты SHK СДЭК до ввода адреса (город/улица). После ввода адреса включается обычный расчёт СДЭК и карта.
        </label>
    </p>

    <h3>Активные варианты СДЭК</h3>
    <?php if ( $detected_empty ) : ?>
        <p class="description">Не удалось автоматически определить активные варианты СДЭК. Показаны базовые профили.</p>
    <?php endif; ?>
    <?php if ( ! empty( $active_profiles ) ) : ?>
        <table class="shk-sdek-inline-table" role="presentation">
            <thead>
                <tr>
                    <th style="width:14%">Тип</th>
                    <th style="width:8%">Видим</th>
                    <th style="width:18%">Название</th>
                    <th style="width:10%">Стоимость</th>
                    <th style="width:18%">Комментарий к цене</th>
                    <th style="width:22%">Комментарий по сроку/условиям</th>
                    <th style="width:10%">Доп. варианты</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $active_profiles as $profile_code ) : ?>
                <?php if ( ! isset( $title_map[ $profile_code ] ) ) { continue; } ?>
                <?php $variants = shikkosa_sdek_get_profile_variants( $opt, $profile_code ); ?>
                <tr>
                    <td><?php echo esc_html( $title_map[ $profile_code ] ); ?></td>
                    <td style="text-align:center"><input type="checkbox" name="shikkosa_sdek_settings[<?php echo esc_attr( $profile_code ); ?>_visible]" value="yes" <?php checked( shikkosa_sdek_is_profile_visible( $opt, $profile_code ) ? 'yes' : 'no', 'yes' ); ?> /></td>
                    <td><input type="text" name="shikkosa_sdek_settings[<?php echo esc_attr( $profile_code ); ?>_label]" value="<?php echo esc_attr( isset( $opt[ $profile_code . '_label' ] ) ? $opt[ $profile_code . '_label' ] : '' ); ?>" /></td>
                    <td><input type="number" step="0.01" name="shikkosa_sdek_settings[<?php echo esc_attr( $profile_code ); ?>_price]" value="<?php echo esc_attr( isset( $opt[ $profile_code . '_price' ] ) ? $opt[ $profile_code . '_price' ] : '' ); ?>" /></td>
                    <td><input type="text" name="shikkosa_sdek_settings[<?php echo esc_attr( $profile_code ); ?>_price_comment]" value="<?php echo esc_attr( isset( $opt[ $profile_code . '_price_comment' ] ) ? $opt[ $profile_code . '_price_comment' ] : '' ); ?>" /></td>
                    <td><input type="text" name="shikkosa_sdek_settings[<?php echo esc_attr( $profile_code ); ?>_delivery_comment]" value="<?php echo esc_attr( isset( $opt[ $profile_code . '_delivery_comment' ] ) ? $opt[ $profile_code . '_delivery_comment' ] : '' ); ?>" /></td>
                    <td style="text-align:center">
                        <button type="button" class="shk-sdek-plus-btn" data-profile="<?php echo esc_attr( $profile_code ); ?>" aria-label="Добавить доп. вариант" title="Добавить доп. вариант">+</button>
                    </td>
                </tr>
                <tr class="shk-extra-row" data-profile="<?php echo esc_attr( $profile_code ); ?>" <?php echo empty( $variants ) ? 'hidden' : ''; ?>>
                    <td colspan="7">
                        <div class="shk-variants-list" data-profile="<?php echo esc_attr( $profile_code ); ?>" data-next-index="<?php echo esc_attr( (string) count( $variants ) ); ?>">
                            <?php if ( empty( $variants ) ) : ?>
                                <div class="shk-sdek-empty">Нет доп. вариантов.</div>
                            <?php else : ?>
                                <?php foreach ( $variants as $idx => $variant ) : ?>
                                    <div class="shk-sdek-variant-item">
                                        <div class="shk-sdek-variant-item-head">
                                            <div class="shk-sdek-sort-wrap">
                                                <span class="shk-sdek-variant-drag" title="Перетащить">↕</span>
                                                <div class="shk-sdek-variant-item-title">Доп. вариант #<?php echo esc_html( (string) ( $idx + 1 ) ); ?></div>
                                            </div>
                                            <button type="button" class="shk-sdek-variant-del">Удалить</button>
                                        </div>
                                        <div class="shk-sdek-variant-grid">
                                            <div>
                                                <div class="shk-extra-label">Название</div>
                                                <input type="text" name="shikkosa_sdek_settings[<?php echo esc_attr( $profile_code ); ?>_variants][<?php echo esc_attr( (string) $idx ); ?>][label]" value="<?php echo esc_attr( isset( $variant['label'] ) ? $variant['label'] : '' ); ?>" />
                                            </div>
                                            <div>
                                                <div class="shk-extra-label">Стоимость</div>
                                                <input type="number" step="0.01" name="shikkosa_sdek_settings[<?php echo esc_attr( $profile_code ); ?>_variants][<?php echo esc_attr( (string) $idx ); ?>][price]" value="<?php echo esc_attr( isset( $variant['price'] ) ? $variant['price'] : '' ); ?>" />
                                            </div>
                                            <div>
                                                <div class="shk-extra-label">Коммент. к цене</div>
                                                <input type="text" name="shikkosa_sdek_settings[<?php echo esc_attr( $profile_code ); ?>_variants][<?php echo esc_attr( (string) $idx ); ?>][price_comment]" value="<?php echo esc_attr( isset( $variant['price_comment'] ) ? $variant['price_comment'] : '' ); ?>" />
                                            </div>
                                            <div>
                                                <div class="shk-extra-label">Коммент. по сроку</div>
                                                <input type="text" name="shikkosa_sdek_settings[<?php echo esc_attr( $profile_code ); ?>_variants][<?php echo esc_attr( (string) $idx ); ?>][delivery_comment]" value="<?php echo esc_attr( isset( $variant['delivery_comment'] ) ? $variant['delivery_comment'] : '' ); ?>" />
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <script>
        (function() {
            function normalizeInputNames(item, profile, index) {
                item.querySelectorAll('input[name]').forEach(function(input) {
                    var name = input.getAttribute('name') || '';
                    name = name.replace(/(shikkosa_sdek_settings\[[^\]]+_variants\])\[\d+\](\[[^\]]+\])/i, '$1[' + index + ']$2');
                    input.setAttribute('name', name);
                });
            }

            function renumberList(list) {
                if (!list) {
                    return;
                }
                var profile = list.getAttribute('data-profile');
                var items = list.querySelectorAll('.shk-sdek-variant-item');
                items.forEach(function(item, idx) {
                    var title = item.querySelector('.shk-sdek-variant-item-title');
                    if (title) {
                        title.textContent = 'Доп. вариант #' + (idx + 1);
                    }
                    normalizeInputNames(item, profile, idx);
                });
                list.setAttribute('data-next-index', String(items.length));
            }

            function enableDragSort(list) {
                var dragItem = null;
                list.querySelectorAll('.shk-sdek-variant-item').forEach(function(item) {
                    if (item.getAttribute('data-drag-init') === '1') {
                        return;
                    }
                    item.setAttribute('data-drag-init', '1');
                    item.setAttribute('draggable', 'true');
                    item.addEventListener('dragstart', function() {
                        dragItem = item;
                        item.classList.add('is-dragging');
                    });
                    item.addEventListener('dragend', function() {
                        item.classList.remove('is-dragging');
                        list.querySelectorAll('.is-drop-target').forEach(function(el) { el.classList.remove('is-drop-target'); });
                        dragItem = null;
                        renumberList(list);
                    });
                    item.addEventListener('dragover', function(e) {
                        e.preventDefault();
                        if (!dragItem || dragItem === item) return;
                        item.classList.add('is-drop-target');
                    });
                    item.addEventListener('dragleave', function() {
                        item.classList.remove('is-drop-target');
                    });
                    item.addEventListener('drop', function(e) {
                        e.preventDefault();
                        item.classList.remove('is-drop-target');
                        if (!dragItem || dragItem === item) return;
                        var rect = item.getBoundingClientRect();
                        var before = (e.clientY - rect.top) < (rect.height / 2);
                        if (before) {
                            item.parentNode.insertBefore(dragItem, item);
                        } else {
                            item.parentNode.insertBefore(dragItem, item.nextSibling);
                        }
                    });
                });
            }

            function variantItemHtml(profile, index) {
                var title = index + 1;
                return '' +
                    '<div class="shk-sdek-variant-item">' +
                    '  <div class="shk-sdek-variant-item-head">' +
                    '    <div class="shk-sdek-sort-wrap"><span class="shk-sdek-variant-drag" title="Перетащить">↕</span><div class="shk-sdek-variant-item-title">Доп. вариант #' + title + '</div></div>' +
                    '    <button type="button" class="shk-sdek-variant-del">Удалить</button>' +
                    '  </div>' +
                    '  <div class="shk-sdek-variant-grid">' +
                    '    <div><div class="shk-extra-label">Название</div><input type="text" name="shikkosa_sdek_settings[' + profile + '_variants][' + index + '][label]" value=""></div>' +
                    '    <div><div class="shk-extra-label">Стоимость</div><input type="number" step="0.01" name="shikkosa_sdek_settings[' + profile + '_variants][' + index + '][price]" value=""></div>' +
                    '    <div><div class="shk-extra-label">Коммент. к цене</div><input type="text" name="shikkosa_sdek_settings[' + profile + '_variants][' + index + '][price_comment]" value=""></div>' +
                    '    <div><div class="shk-extra-label">Коммент. по сроку</div><input type="text" name="shikkosa_sdek_settings[' + profile + '_variants][' + index + '][delivery_comment]" value=""></div>' +
                    '  </div>' +
                    '</div>';
            }

            function syncRow(profile) {
                var row = document.querySelector('.shk-extra-row[data-profile="' + profile + '"]');
                var list = document.querySelector('.shk-variants-list[data-profile="' + profile + '"]');
                if (!row || !list) {
                    return;
                }

                var items = list.querySelectorAll('.shk-sdek-variant-item');
                var empty = list.querySelector('.shk-sdek-empty');
                if (!items.length) {
                    if (!empty) {
                        empty = document.createElement('div');
                        empty.className = 'shk-sdek-empty';
                        empty.textContent = 'Нет доп. вариантов.';
                        list.appendChild(empty);
                    }
                    row.setAttribute('hidden', 'hidden');
                } else {
                    if (empty) {
                        empty.remove();
                    }
                    row.removeAttribute('hidden');
                }
            }

            document.querySelectorAll('.shk-sdek-plus-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var profile = btn.getAttribute('data-profile');
                    var list = document.querySelector('.shk-variants-list[data-profile="' + profile + '"]');
                    if (!profile || !list) {
                        return;
                    }
                    var nextIndex = parseInt(list.getAttribute('data-next-index') || '0', 10);
                    var wrapper = document.createElement('div');
                    wrapper.innerHTML = variantItemHtml(profile, nextIndex);
                    var node = wrapper.firstElementChild;
                    if (node) {
                        list.appendChild(node);
                        list.setAttribute('data-next-index', String(nextIndex + 1));
                        enableDragSort(list);
                        renumberList(list);
                    }
                    syncRow(profile);
                });
            });

            document.addEventListener('click', function(e) {
                var btn = e.target.closest('.shk-sdek-variant-del');
                if (!btn) {
                    return;
                }
                var item = btn.closest('.shk-sdek-variant-item');
                var list = btn.closest('.shk-variants-list');
                if (!item || !list) {
                    return;
                }
                item.remove();
                renumberList(list);
                syncRow(list.getAttribute('data-profile'));
            });

            document.querySelectorAll('.shk-variants-list').forEach(function(list) {
                enableDragSort(list);
                renumberList(list);
                syncRow(list.getAttribute('data-profile'));
            });
        })();
        </script>
    <?php endif; ?>

    <?php $custom_rates = shikkosa_sdek_sanitize_custom_rates( isset( $opt['custom_rates'] ) ? $opt['custom_rates'] : array() ); ?>
    <h3>Кастомные пункты доставки</h3>
    <p class="description">Эти пункты добавляются в общий список доставки на checkout. Если заполнен «Код тарифа CDEK», пункт работает как CDEK-вариант; если пусто — обычный ручной пункт.</p>
    <table class="shk-sdek-inline-table" role="presentation">
        <thead>
            <tr>
                <th style="width:8%">Вкл</th>
                <th style="width:23%">Название</th>
                <th style="width:10%">Код тарифа CDEK</th>
                <th style="width:11%">Стоимость</th>
                <th style="width:21%">Комментарий к цене</th>
                <th style="width:21%">Комментарий по сроку</th>
                <th style="width:6%"></th>
            </tr>
        </thead>
        <tbody id="shk-custom-rates-body">
            <?php foreach ( $custom_rates as $i => $row ) : ?>
                <tr class="shk-custom-rate-row">
                    <td style="text-align:center"><input type="checkbox" name="shikkosa_sdek_settings[custom_rates][<?php echo esc_attr( (string) $i ); ?>][enabled]" value="yes" <?php checked( isset( $row['enabled'] ) ? $row['enabled'] : 'yes', 'yes' ); ?> /></td>
                    <td><input type="text" name="shikkosa_sdek_settings[custom_rates][<?php echo esc_attr( (string) $i ); ?>][label]" value="<?php echo esc_attr( $row['label'] ?? '' ); ?>" /></td>
                    <td><input type="text" inputmode="numeric" pattern="[0-9]*" name="shikkosa_sdek_settings[custom_rates][<?php echo esc_attr( (string) $i ); ?>][tariff_code]" value="<?php echo esc_attr( $row['tariff_code'] ?? '' ); ?>" /></td>
                    <td><input type="number" step="0.01" name="shikkosa_sdek_settings[custom_rates][<?php echo esc_attr( (string) $i ); ?>][price]" value="<?php echo esc_attr( $row['price'] ?? '' ); ?>" /></td>
                    <td><input type="text" name="shikkosa_sdek_settings[custom_rates][<?php echo esc_attr( (string) $i ); ?>][price_comment]" value="<?php echo esc_attr( $row['price_comment'] ?? '' ); ?>" /></td>
                    <td><input type="text" name="shikkosa_sdek_settings[custom_rates][<?php echo esc_attr( (string) $i ); ?>][delivery_comment]" value="<?php echo esc_attr( $row['delivery_comment'] ?? '' ); ?>" /></td>
                    <td><button type="button" class="button-link-delete shk-custom-rate-del">Удалить</button></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p><button type="button" class="button" id="shk-custom-rate-add">+ Добавить пункт</button></p>
    <script>
    (function() {
      var body = document.getElementById('shk-custom-rates-body');
      var add = document.getElementById('shk-custom-rate-add');
      if (!body || !add) return;

      function renumber() {
        var rows = body.querySelectorAll('.shk-custom-rate-row');
        rows.forEach(function(row, idx) {
          row.querySelectorAll('input[name]').forEach(function(inp) {
            var name = inp.getAttribute('name') || '';
            name = name.replace(/shikkosa_sdek_settings\[custom_rates\]\[\d+\]/, 'shikkosa_sdek_settings[custom_rates][' + idx + ']');
            inp.setAttribute('name', name);
          });
        });
      }

      function rowHtml(idx) {
        return '' +
          '<tr class="shk-custom-rate-row">' +
          '<td style="text-align:center"><input type="checkbox" name="shikkosa_sdek_settings[custom_rates][' + idx + '][enabled]" value="yes" checked></td>' +
          '<td><input type="text" name="shikkosa_sdek_settings[custom_rates][' + idx + '][label]" value=""></td>' +
          '<td><input type="text" inputmode="numeric" pattern="[0-9]*" name="shikkosa_sdek_settings[custom_rates][' + idx + '][tariff_code]" value=""></td>' +
          '<td><input type="number" step="0.01" name="shikkosa_sdek_settings[custom_rates][' + idx + '][price]" value=""></td>' +
          '<td><input type="text" name="shikkosa_sdek_settings[custom_rates][' + idx + '][price_comment]" value=""></td>' +
          '<td><input type="text" name="shikkosa_sdek_settings[custom_rates][' + idx + '][delivery_comment]" value=""></td>' +
          '<td><button type="button" class="button-link-delete shk-custom-rate-del">Удалить</button></td>' +
          '</tr>';
      }

      add.addEventListener('click', function() {
        var idx = body.querySelectorAll('.shk-custom-rate-row').length;
        var wrap = document.createElement('tbody');
        wrap.innerHTML = rowHtml(idx);
        var row = wrap.firstElementChild;
        if (row) body.appendChild(row);
        renumber();
      });

      body.addEventListener('click', function(e) {
        var del = e.target.closest('.shk-custom-rate-del');
        if (!del) return;
        var row = del.closest('.shk-custom-rate-row');
        if (!row) return;
        row.remove();
        renumber();
      });
    })();
    </script>

    <p class="description">Нажмите «+», чтобы добавить один или несколько доп. вариантов для конкретного типа. Пустая стоимость доп. варианта = цена основного варианта.</p>
    <?php
}

function shikkosa_sdek_settings_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    $opt = shikkosa_sdek_settings();
    $general_rows = array(
        array(
            'title' => 'СДЭК дверь-дверь',
            'label' => 'cdek_door_door_label',
            'price' => 'cdek_door_door_price',
            'price_comment' => 'cdek_door_door_price_comment',
            'delivery_comment' => 'cdek_door_door_delivery_comment',
        ),
        array(
            'title' => 'СДЭК дверь-склад/пункт',
            'label' => 'cdek_door_warehouse_label',
            'price' => 'cdek_door_warehouse_price',
            'price_comment' => 'cdek_door_warehouse_price_comment',
            'delivery_comment' => 'cdek_door_warehouse_delivery_comment',
        ),
        array(
            'title' => 'СДЭК ПВЗ/самовывоз',
            'label' => 'cdek_pickup_label',
            'price' => 'cdek_pickup_price',
            'price_comment' => 'cdek_pickup_price_comment',
            'delivery_comment' => 'cdek_pickup_delivery_comment',
        ),
        array(
            'title' => 'СДЭК Экспресс дверь-дверь',
            'label' => 'cdek_express_door_door_label',
            'price' => 'cdek_express_door_door_price',
            'price_comment' => 'cdek_express_door_door_price_comment',
            'delivery_comment' => 'cdek_express_door_door_delivery_comment',
        ),
    );

    $split_rows = array(
        array(
            'title' => 'МСК/МО, ПВЗ без примерки',
            'label' => 'msk_no_fit_label',
            'price' => 'msk_no_fit_price',
            'price_comment' => 'msk_no_fit_price_comment',
            'delivery_comment' => 'msk_no_fit_delivery_comment',
            'extra' => 'msk_no_fit_extra',
        ),
        array(
            'title' => 'МСК/МО, ПВЗ с примеркой',
            'label' => 'msk_fit_label',
            'price' => 'msk_fit_price',
            'price_comment' => 'msk_fit_price_comment',
            'delivery_comment' => 'msk_fit_delivery_comment',
            'extra' => 'msk_fit_extra',
        ),
        array(
            'title' => 'РФ, ПВЗ без примерки',
            'label' => 'rf_no_fit_label',
            'price' => 'rf_no_fit_price',
            'price_comment' => 'rf_no_fit_price_comment',
            'delivery_comment' => 'rf_no_fit_delivery_comment',
            'extra' => 'rf_no_fit_extra',
        ),
        array(
            'title' => 'РФ, ПВЗ с примеркой',
            'label' => 'rf_fit_label',
            'price' => 'rf_fit_price',
            'price_comment' => 'rf_fit_price_comment',
            'delivery_comment' => 'rf_fit_delivery_comment',
            'extra' => 'rf_fit_extra',
        ),
    );
    ?>
    <div class="wrap">
        <h1>SHK Доставка</h1>
        <p>Деление только для ПВЗ СДЭК. Курьер СДЭК остаётся как в плагине.</p>
        <form method="post" action="options.php">
            <?php settings_fields( 'shikkosa_sdek_settings_group' ); ?>
            <style>
                .shk-sdek-inline-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 10px 0 18px;
                    table-layout: fixed;
                }
                .shk-sdek-inline-table th,
                .shk-sdek-inline-table td {
                    border: 1px solid #dcdcde;
                    padding: 8px;
                    vertical-align: top;
                }
                .shk-sdek-inline-table th {
                    background: #f6f7f7;
                    font-weight: 600;
                    text-align: left;
                }
                .shk-sdek-inline-table td input[type="text"],
                .shk-sdek-inline-table td input[type="number"] {
                    width: 100%;
                    box-sizing: border-box;
                }
                .shk-sdek-inline-compact {
                    margin-top: 6px;
                }
            </style>

            <div class="shk-sdek-inline-compact">
                <label>
                    <input type="checkbox" name="shikkosa_sdek_settings[enabled]" value="yes" <?php checked( $opt['enabled'], 'yes' ); ?> />
                    Включить разделение ПВЗ
                </label>
            </div>
            <div class="shk-sdek-inline-compact">
                <label>
                    <input type="checkbox" name="shikkosa_sdek_settings[show_all_before_address]" value="yes" <?php checked( isset( $opt['show_all_before_address'] ) ? $opt['show_all_before_address'] : 'yes', 'yes' ); ?> />
                    Показывать все варианты SHK СДЭК до ввода адреса
                </label>
            </div>

            <h2>Все виды СДЭК (общие настройки)</h2>
            <table class="shk-sdek-inline-table" role="presentation">
                <thead>
                    <tr>
                        <th style="width:16%">Тип доставки</th>
                        <th style="width:22%">Название</th>
                        <th style="width:10%">Стоимость</th>
                        <th style="width:26%">Комментарий к цене</th>
                        <th style="width:26%">Комментарий по сроку/условиям</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $general_rows as $row ) : ?>
                        <tr>
                            <td><?php echo esc_html( $row['title'] ); ?></td>
                            <td><input type="text" name="shikkosa_sdek_settings[<?php echo esc_attr( $row['label'] ); ?>]" value="<?php echo esc_attr( $opt[ $row['label'] ] ); ?>" /></td>
                            <td><input type="number" step="0.01" name="shikkosa_sdek_settings[<?php echo esc_attr( $row['price'] ); ?>]" value="<?php echo esc_attr( $opt[ $row['price'] ] ); ?>" /></td>
                            <td><input type="text" name="shikkosa_sdek_settings[<?php echo esc_attr( $row['price_comment'] ); ?>]" value="<?php echo esc_attr( $opt[ $row['price_comment'] ] ); ?>" /></td>
                            <td><input type="text" name="shikkosa_sdek_settings[<?php echo esc_attr( $row['delivery_comment'] ); ?>]" value="<?php echo esc_attr( $opt[ $row['delivery_comment'] ] ); ?>" /></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2>Split ПВЗ (когда включено разделение)</h2>
            <table class="shk-sdek-inline-table" role="presentation">
                <thead>
                    <tr>
                        <th style="width:16%">Вариант</th>
                        <th style="width:10%">Добавка</th>
                        <th style="width:22%">Название</th>
                        <th style="width:10%">Стоимость</th>
                        <th style="width:21%">Комментарий к цене</th>
                        <th style="width:21%">Комментарий по сроку/условиям</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $split_rows as $row ) : ?>
                        <tr>
                            <td><?php echo esc_html( $row['title'] ); ?></td>
                            <td><input type="number" step="0.01" name="shikkosa_sdek_settings[<?php echo esc_attr( $row['extra'] ); ?>]" value="<?php echo esc_attr( $opt[ $row['extra'] ] ); ?>" /></td>
                            <td><input type="text" name="shikkosa_sdek_settings[<?php echo esc_attr( $row['label'] ); ?>]" value="<?php echo esc_attr( $opt[ $row['label'] ] ); ?>" /></td>
                            <td><input type="number" step="0.01" name="shikkosa_sdek_settings[<?php echo esc_attr( $row['price'] ); ?>]" value="<?php echo esc_attr( $opt[ $row['price'] ] ); ?>" /></td>
                            <td><input type="text" name="shikkosa_sdek_settings[<?php echo esc_attr( $row['price_comment'] ); ?>]" value="<?php echo esc_attr( $opt[ $row['price_comment'] ] ); ?>" /></td>
                            <td><input type="text" name="shikkosa_sdek_settings[<?php echo esc_attr( $row['delivery_comment'] ); ?>]" value="<?php echo esc_attr( $opt[ $row['delivery_comment'] ] ); ?>" /></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p class="description">Если поле «Стоимость» пустое, используется стандартная цена метода (или базовая цена + добавка для split).</p>
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

    if ( shikkosa_use_native_woo_shipping_mode() ) {
        $notes_by_rate = array();
        if ( function_exists( 'WC' ) && WC()->shipping() ) {
            $packages = WC()->shipping()->get_packages();
            if ( is_array( $packages ) ) {
                foreach ( $packages as $package ) {
                    $rates = isset( $package['rates'] ) && is_array( $package['rates'] ) ? $package['rates'] : array();
                    foreach ( $rates as $rate_id => $rate ) {
                        if ( ! is_object( $rate ) || ! is_a( $rate, 'WC_Shipping_Rate' ) ) {
                            continue;
                        }
                        $meta = method_exists( $rate, 'get_meta_data' ) ? (array) $rate->get_meta_data() : array();
                        $price = isset( $meta['_shk_price_comment'] ) ? trim( (string) $meta['_shk_price_comment'] ) : '';
                        $delivery = isset( $meta['_shk_delivery_comment'] ) ? trim( (string) $meta['_shk_delivery_comment'] ) : '';
                        if ( '' === $price && '' === $delivery ) {
                            continue;
                        }
                        $notes_by_rate[ (string) $rate_id ] = array(
                            'price' => $price,
                            'delivery' => $delivery,
                        );
                    }
                }
            }
        }
        ?>
        <script>
        (function () {
          var notesByRate = <?php echo wp_json_encode( $notes_by_rate ); ?>;

          function findNoteByRateValue(raw) {
            var value = String(raw || '');
            if (!value) return null;
            if (notesByRate[value]) return notesByRate[value];
            var key = Object.keys(notesByRate).find(function(k) {
              return value.indexOf(k) !== -1 || k.indexOf(value) !== -1;
            });
            return key ? notesByRate[key] : null;
          }

          function applyNotes() {
            var options = document.querySelectorAll('.wc-block-checkout__shipping-option .wc-block-components-radio-control__option');
            if (!options.length) return;

            options.forEach(function(opt){
              var input = opt.querySelector('.wc-block-components-radio-control__input');
              if (!input) return;
              var noteData = findNoteByRateValue(input.value || '');
              var layout = opt.querySelector('.wc-block-components-radio-control__option-layout');
              if (!layout) return;
              var labelGroup = opt.querySelector('.wc-block-components-radio-control__label-group');
              var secondary = opt.querySelector('.wc-block-components-radio-control__secondary-label');
              var noteMount = labelGroup || layout;

              var existing = opt.querySelector('.shk-sdek-note');
              if (!noteData) {
                if (existing) existing.innerHTML = '';
                return;
              }

              if (!existing) {
                existing = document.createElement('div');
                existing.className = 'shk-sdek-note';
                noteMount.appendChild(existing);
              }
              if (secondary && existing.previousElementSibling !== secondary) {
                secondary.insertAdjacentElement('afterend', existing);
              }

              var price = String(noteData.price || '').trim();
              var delivery = String(noteData.delivery || '').trim();
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

          var i = 0;
          var iv = setInterval(function(){
            i++;
            applyNotes();
            if (i > 120) clearInterval(iv);
          }, 250);

          document.addEventListener('change', applyNotes);
          document.addEventListener('wc-blocks_checkout_update', applyNotes);
        })();
        </script>
        <?php
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
        'msk_no_fit' => array(
            'price'    => isset( $opt['msk_no_fit_price_comment'] ) ? (string) $opt['msk_no_fit_price_comment'] : '',
            'delivery' => isset( $opt['msk_no_fit_delivery_comment'] ) ? (string) $opt['msk_no_fit_delivery_comment'] : '',
        ),
        'msk_fit' => array(
            'price'    => isset( $opt['msk_fit_price_comment'] ) ? (string) $opt['msk_fit_price_comment'] : '',
            'delivery' => isset( $opt['msk_fit_delivery_comment'] ) ? (string) $opt['msk_fit_delivery_comment'] : '',
        ),
        'rf_no_fit' => array(
            'price'    => isset( $opt['rf_no_fit_price_comment'] ) ? (string) $opt['rf_no_fit_price_comment'] : '',
            'delivery' => isset( $opt['rf_no_fit_delivery_comment'] ) ? (string) $opt['rf_no_fit_delivery_comment'] : '',
        ),
        'rf_fit' => array(
            'price'    => isset( $opt['rf_fit_price_comment'] ) ? (string) $opt['rf_fit_price_comment'] : '',
            'delivery' => isset( $opt['rf_fit_delivery_comment'] ) ? (string) $opt['rf_fit_delivery_comment'] : '',
        ),
    );
    foreach ( array( 'cdek_door_door', 'cdek_door_warehouse', 'cdek_pickup', 'cdek_express_door_door' ) as $profile_code ) {
        $variants = shikkosa_sdek_get_profile_variants( $opt, $profile_code );
        foreach ( $variants as $idx => $variant_data ) {
            $n = (int) $idx + 1;
            $notes[ $profile_code . '_variant_' . $n ] = array(
                'price'    => isset( $variant_data['price_comment'] ) ? (string) $variant_data['price_comment'] : '',
                'delivery' => isset( $variant_data['delivery_comment'] ) ? (string) $variant_data['delivery_comment'] : '',
            );
        }
    }
    $custom_rates = shikkosa_sdek_sanitize_custom_rates( isset( $opt['custom_rates'] ) ? $opt['custom_rates'] : array() );
    foreach ( $custom_rates as $idx => $row ) {
        $n = (int) $idx + 1;
        $notes[ 'custom_' . $n ] = array(
            'price'    => isset( $row['price_comment'] ) ? (string) $row['price_comment'] : '',
            'delivery' => isset( $row['delivery_comment'] ) ? (string) $row['delivery_comment'] : '',
        );
    }
    ?>
    <script>
    (function () {
      var notes = <?php echo wp_json_encode( $notes ); ?>;

      function detectCode(inputValue, inputId) {
        var hay = (String(inputValue || '') + ' ' + String(inputId || '')).toLowerCase();
        var custom = hay.match(/shk_custom_(\d+)/);
        if (custom && custom[1]) return 'custom_' + custom[1];
        var byId = hay.match(/__shk_variant_(cdek_express_door_door|cdek_door_warehouse|cdek_pickup|cdek_door_door)_(\d+)/);
        if (byId && byId[1] && byId[2]) return byId[1] + '_variant_' + byId[2];
        if (hay.indexOf('__shk_variant_cdek_express_door_door') !== -1) return 'cdek_express_door_door_variant_1';
        if (hay.indexOf('__shk_variant_cdek_door_warehouse') !== -1) return 'cdek_door_warehouse_variant_1';
        if (hay.indexOf('__shk_variant_cdek_pickup') !== -1) return 'cdek_pickup_variant_1';
        if (hay.indexOf('__shk_variant_cdek_door_door') !== -1) return 'cdek_door_door_variant_1';
        if ((hay.indexOf('москв') !== -1 || hay.indexOf('мкад') !== -1 || hay.indexOf(' мо ') !== -1 || hay.indexOf(' мо,') !== -1) && hay.indexOf('примерк') !== -1) return 'msk_fit';
        if (hay.indexOf('москв') !== -1 || hay.indexOf('мкад') !== -1 || hay.indexOf(' мо ') !== -1 || hay.indexOf(' мо,') !== -1) return 'msk_no_fit';
        if ((hay.indexOf('росси') !== -1 || hay.indexOf('рф') !== -1) && hay.indexOf('примерк') !== -1) return 'rf_fit';
        if (hay.indexOf('росси') !== -1 || hay.indexOf('рф') !== -1) return 'rf_no_fit';
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
          var labelGroup = opt.querySelector('.wc-block-components-radio-control__label-group');
          var secondary = opt.querySelector('.wc-block-components-radio-control__secondary-label');
          var noteMount = labelGroup || layout;

          var existing = opt.querySelector('.shk-sdek-note');
          if (!existing) {
            existing = document.createElement('div');
            existing.className = 'shk-sdek-note';
            noteMount.appendChild(existing);
          }
          if (secondary && existing.previousElementSibling !== secondary) {
            secondary.insertAdjacentElement('afterend', existing);
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
        if (t > 240) clearInterval(iv);
      }, 250);

      document.addEventListener('change', applyNotes);
      document.addEventListener('wc-blocks_checkout_update', applyNotes);
      window.setTimeout(applyNotes, 1200);
      window.setTimeout(applyNotes, 4000);
    })();
    </script>
    <?php
}

function shikkosa_checkout_selected_shipping_is_cdek_local() {
    $methods = array();

    if ( isset( $_POST['shipping_method'] ) ) {
        $posted = wp_unslash( $_POST['shipping_method'] );
        if ( is_array( $posted ) ) {
            $methods = array_merge( $methods, array_map( 'strval', $posted ) );
        } elseif ( is_scalar( $posted ) ) {
            $methods[] = (string) $posted;
        }
    }

    if ( function_exists( 'WC' ) && WC()->session ) {
        $chosen = WC()->session->get( 'chosen_shipping_methods', array() );
        if ( is_array( $chosen ) ) {
            $methods = array_merge( $methods, array_map( 'strval', $chosen ) );
        }
    }

    foreach ( $methods as $method ) {
        $hay = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $method ) : strtolower( (string) $method );
        if ( false !== strpos( $hay, 'cdek' ) || false !== strpos( $hay, 'sdek' ) || false !== strpos( $hay, 'сдэк' ) ) {
            return true;
        }
    }

    return false;
}

function shikkosa_is_unknown_tariff_message_local( $message ) {
    $msg = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $message ) : strtolower( (string) $message );
    return ( false !== strpos( $msg, 'unknown tariff' ) ) || ( false !== strpos( $msg, 'tariff 0' ) );
}

add_action(
    'woocommerce_after_checkout_validation',
    function( $data, $errors ) {
        if ( ! $errors || ! is_a( $errors, 'WP_Error' ) ) {
            return;
        }
        if ( shikkosa_checkout_selected_shipping_is_cdek_local() ) {
            return;
        }

        foreach ( (array) $errors->errors as $code => $messages ) {
            $matched = false;
            foreach ( (array) $messages as $message ) {
                if ( shikkosa_is_unknown_tariff_message_local( $message ) ) {
                    $matched = true;
                    break;
                }
            }
            if ( $matched ) {
                $errors->remove( $code );
            }
        }
    },
    9999,
    2
);

add_action(
    'woocommerce_checkout_process',
    function() {
        if ( shikkosa_checkout_selected_shipping_is_cdek_local() ) {
            return;
        }
        $errors = wc_get_notices( 'error' );
        if ( empty( $errors ) || ! is_array( $errors ) ) {
            return;
        }

        $filtered = array();
        foreach ( $errors as $notice ) {
            $text = is_array( $notice ) && isset( $notice['notice'] ) ? (string) $notice['notice'] : '';
            if ( shikkosa_is_unknown_tariff_message_local( $text ) ) {
                continue;
            }
            $filtered[] = $notice;
        }

        if ( count( $filtered ) === count( $errors ) ) {
            return;
        }

        wc_clear_notices();
        foreach ( $filtered as $notice ) {
            $text = is_array( $notice ) && isset( $notice['notice'] ) ? (string) $notice['notice'] : '';
            $data = is_array( $notice ) && isset( $notice['data'] ) && is_array( $notice['data'] ) ? $notice['data'] : array();
            if ( '' !== $text ) {
                wc_add_notice( $text, 'error', $data );
            }
        }
    },
    9999
);
