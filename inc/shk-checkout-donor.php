<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_footer', 'shikkosa_checkout_donor_blocks_tweaks_local', 120 );
function shikkosa_checkout_donor_blocks_tweaks_local() {
    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
        return;
    }
    ?>
    <script>
    (function () {
      if (!document.body.classList.contains('woocommerce-checkout')) return;
      var shkLastSyncedCity = '';
      var shkFallbackCityApplied = false;
      var shkDebug = {
        enabled: false,
        prefix: '[SHK checkout]'
      };

      function dbg() {
        if (!shkDebug.enabled || !window.console || typeof window.console.log !== 'function') return;
        var args = Array.prototype.slice.call(arguments);
        args.unshift(shkDebug.prefix);
        window.console.log.apply(window.console, args);
      }

      function getSelectedShippingDebug(root) {
        if (!root) return { value: '', label: '' };
        var shippingOptions = root.querySelector('fieldset.wc-block-checkout__shipping-option');
        if (!shippingOptions) return { value: '', label: '' };
        var selected = shippingOptions.querySelector('.wc-block-components-radio-control__input:checked');
        if (!selected) return { value: '', label: '' };
        var option = selected.closest('.wc-block-components-radio-control__option');
        var label = option ? String(option.textContent || '').trim().replace(/\s+/g, ' ') : '';
        return {
          value: String(selected.value || ''),
          label: label
        };
      }

      function setTitle(section, text) {
        if (!section) return;
        var title = section.querySelector('.wc-block-components-checkout-step__title');
        if (title) title.textContent = text;
      }

      function enforceCheckoutOrder(root) {
        if (!root) return;
        var contact = root.querySelector('fieldset.wc-block-checkout__contact-fields');
        var shippingMethodSwitch = root.querySelector('fieldset.wc-block-checkout__shipping-method');
        var shippingOptions = root.querySelector('fieldset.wc-block-checkout__shipping-option');
        var shippingFields = root.querySelector('fieldset.wc-block-checkout__shipping-fields');
        var orderNotes = root.querySelector('#order-notes');
        var payment = root.querySelector('fieldset.wc-block-checkout__payment-method');

        if (!contact || !shippingOptions || !shippingFields || !payment) return;

        if (shippingMethodSwitch) {
          shippingMethodSwitch.style.setProperty('display', 'none', 'important');
        }

        if (shippingOptions.previousElementSibling !== contact && contact.parentNode) {
          contact.insertAdjacentElement('afterend', shippingOptions);
        }
        if (shippingFields.previousElementSibling !== shippingOptions && shippingOptions.parentNode) {
          shippingOptions.insertAdjacentElement('afterend', shippingFields);
        }
        if (orderNotes && orderNotes.previousElementSibling !== shippingFields) {
          shippingFields.insertAdjacentElement('afterend', orderNotes);
        }
        if (payment.previousElementSibling !== (orderNotes || shippingFields)) {
          (orderNotes || shippingFields).insertAdjacentElement('afterend', payment);
        }
      }

      function hideEl(root, selector) {
        if (!root) return;
        var el = root.querySelector(selector);
        if (el) el.style.display = 'none';
      }

      function syncShippingAddressAvailability(root) {
        if (!root) return;

        var shippingOptions = root.querySelector('fieldset.wc-block-checkout__shipping-option');
        var shippingFields = root.querySelector('fieldset.wc-block-checkout__shipping-fields');
        if (!shippingOptions || !shippingFields) return;

        var optionInputs = shippingOptions.querySelectorAll('.wc-block-components-radio-control__input');
        if (!optionInputs.length) return;

        var firstValue = String(optionInputs[0].value || '');
        var selectedInput = optionInputs[0];
        var selectedValue = firstValue;
        optionInputs.forEach(function(input) {
          if (input.checked) {
            selectedValue = String(input.value || '');
            selectedInput = input;
          }
        });

        var disableAddressInputs = !!firstValue && selectedValue !== firstValue;
        dbg('syncShippingAddressAvailability', {
          firstValue: firstValue,
          selectedValue: selectedValue,
          disableAddressInputs: disableAddressInputs
        });
        if (disableAddressInputs) {
          shippingFields.classList.add('shk-shipping-address-disabled');
          shippingFields.setAttribute('aria-disabled', 'true');
          shippingFields.style.setProperty('display', 'none', 'important');

          var selectedOption = selectedInput ? selectedInput.closest('.wc-block-components-radio-control__option') : null;
          var selectedHay = (
            String(selectedValue || '') + ' ' +
            String(selectedInput && selectedInput.id ? selectedInput.id : '') + ' ' +
            String(selectedOption ? selectedOption.textContent || '' : '')
          ).toLowerCase();
          var isCdekLike =
            selectedHay.indexOf('cdek') !== -1 ||
            selectedHay.indexOf('sdek') !== -1 ||
            selectedHay.indexOf('сдэк') !== -1 ||
            selectedHay.indexOf('пвз') !== -1;

          if (!shkFallbackCityApplied && isCdekLike && !getCurrentWooShippingCity(root)) {
            applySoftFallbackCity(root, 'Москва');
            shkFallbackCityApplied = true;
          }
        } else {
          shippingFields.classList.remove('shk-shipping-address-disabled');
          shippingFields.removeAttribute('aria-disabled');
          shippingFields.style.removeProperty('display');
        }
      }

      function getCurrentWooShippingCity(root) {
        var city = '';
        if (root) {
          var cityInput = root.querySelector('#shipping-city, input[name="shipping_city"], input[name="shipping-city"], .wc-block-components-address-form__city input');
          city = cityInput ? String(cityInput.value || '').trim() : '';
          if (city) return city;
        }

        var wpData = window.wp && window.wp.data ? window.wp.data : null;
        if (wpData && wpData.select) {
          var cartSelect = wpData.select('wc/store/cart');
          if (cartSelect) {
            if (typeof cartSelect.getShippingAddress === 'function') {
              var addr = cartSelect.getShippingAddress() || {};
              city = String(addr.city || '').trim();
            } else if (typeof cartSelect.getCartData === 'function') {
              var cart = cartSelect.getCartData() || {};
              var ship = cart.shippingAddress || cart.shipping_address || {};
              city = String(ship.city || '').trim();
            }
          }
        }

        return city;
      }

      function applySoftFallbackCity(root, cityValue) {
        if (!root) return;
        var value = String(cityValue || '').trim();
        if (!value) return;
        var hasCity = !!getCurrentWooShippingCity(root);
        if (hasCity) return;

        var cityInput = root.querySelector('#shipping-city, input[name="shipping_city"], input[name="shipping-city"], .wc-block-components-address-form__city input');
        if (cityInput) {
          cityInput.value = value;
          cityInput.dispatchEvent(new Event('input', { bubbles: true }));
          cityInput.dispatchEvent(new Event('change', { bubbles: true }));
        }

        var wpData = window.wp && window.wp.data ? window.wp.data : null;
        if (wpData && wpData.dispatch) {
          var cartDispatch = wpData.dispatch('wc/store/cart');
          if (cartDispatch) {
            if (typeof cartDispatch.setShippingAddress === 'function') {
              cartDispatch.setShippingAddress({ city: value });
            } else if (typeof cartDispatch.__experimentalSetShippingAddress === 'function') {
              cartDispatch.__experimentalSetShippingAddress({ city: value });
            }
          }
        }
      }

      function syncWooShippingCity(root, cityValue) {
        var started = performance.now();
        var value = String(cityValue || '').trim();
        if (!value) {
          dbg('syncWooShippingCity: skip empty');
          return;
        }
        if (shkLastSyncedCity === value) {
          dbg('syncWooShippingCity: skip same as last', value);
          return;
        }
        if (getCurrentWooShippingCity(root) === value) {
          shkLastSyncedCity = value;
          dbg('syncWooShippingCity: already current', value);
          return;
        }

        var cityInputs = [];
        if (root) {
          root.querySelectorAll('#shipping-city, input[name="shipping_city"], input[name="shipping-city"], .wc-block-components-address-form__city input').forEach(function(el){
            if (cityInputs.indexOf(el) === -1) cityInputs.push(el);
          });
        }

        var changed = false;
        cityInputs.forEach(function(cityInput){
          if (String(cityInput.value || '').trim() === value) return;
          cityInput.value = value;
          cityInput.dispatchEvent(new Event('input', { bubbles: true }));
          cityInput.dispatchEvent(new Event('change', { bubbles: true }));
          changed = true;
        });

        var wpData = window.wp && window.wp.data ? window.wp.data : null;
        if (wpData && wpData.dispatch) {
          var cartDispatch = wpData.dispatch('wc/store/cart');
          if (cartDispatch) {
            if (typeof cartDispatch.setShippingAddress === 'function') {
              cartDispatch.setShippingAddress({ city: value });
              changed = true;
            } else if (typeof cartDispatch.__experimentalSetShippingAddress === 'function') {
              cartDispatch.__experimentalSetShippingAddress({ city: value });
              changed = true;
            }
          }
        }

        if (changed) {
          shkLastSyncedCity = value;
        }
        dbg('syncWooShippingCity: done', {
          value: value,
          changed: changed,
          ms: Math.round(performance.now() - started)
        });
      }

      function normalizeCityName(raw) {
        var city = String(raw || '').trim();
        if (!city) return '';
        city = city.replace(/^г\.\s*/i, '');
        city = city.replace(/^город\s+/i, '');
        city = city.replace(/\s+/g, ' ').trim();
        if (city.length < 2) return '';
        return city;
      }

      function isRegionLikeChunk(chunk) {
        var s = String(chunk || '').toLowerCase();
        if (!s) return true;
        return (
          s.indexOf('област') !== -1 ||
          s.indexOf('край') !== -1 ||
          s.indexOf('республика') !== -1 ||
          s.indexOf('район') !== -1 ||
          s.indexOf('округ') !== -1 ||
          s.indexOf('россия') !== -1
        );
      }

      function extractCityFromCdekFields(root) {
        if (!root) return '';
        var candidates = [];
        root.querySelectorAll('input[name*="cdek" i], input[id*="cdek" i], input[name*="sdek" i], input[id*="sdek" i], textarea[name*="cdek" i], textarea[id*="cdek" i], textarea[name*="sdek" i], textarea[id*="sdek" i]').forEach(function(el){
          var val = '';
          if ('value' in el) {
            val = String(el.value || '').trim();
          } else {
            val = String(el.textContent || '').trim();
          }
          if (val) candidates.push(val);
        });

        for (var i = 0; i < candidates.length; i++) {
          var text = String(candidates[i] || '');
          if (text.indexOf(',') !== -1) {
            var chunks = text.split(',').map(function(v){ return normalizeCityName(v); }).filter(Boolean);
            for (var c = chunks.length - 1; c >= 0; c--) {
              var ch = String(chunks[c] || '').trim();
              if (!ch) continue;
              if (/\d/.test(ch)) continue;
              if (isRegionLikeChunk(ch)) continue;
              if (ch.length < 3) continue;
              return ch;
            }
          }

          var byCityPrefix = text.match(/(?:^|,\s*)г\.\s*([A-Za-zА-Яа-яЁё\- ]+)(?:,|$)/i);
          if (byCityPrefix && byCityPrefix[1]) {
            var city1 = normalizeCityName(byCityPrefix[1]);
            if (city1) return city1;
          }
          var firstChunk = text.split(',')[0] || '';
          var city2 = normalizeCityName(firstChunk);
          if (city2) return city2;
        }
        return '';
      }

      function attemptSyncCityFromCdekSelection(root) {
        var started = performance.now();
        var city = extractCityFromCdekFields(root);
        if (!city) {
          dbg('attemptSyncCityFromCdekSelection: no city extracted');
          return;
        }
        if (city.toLowerCase() === 'россия') {
          dbg('attemptSyncCityFromCdekSelection: skip country value', city);
          return;
        }
        dbg('attemptSyncCityFromCdekSelection: extracted city', city);
        syncWooShippingCity(root, city);
        dbg('attemptSyncCityFromCdekSelection: finished', Math.round(performance.now() - started) + 'ms');
      }

      function bindCdekMapCitySync(root) {
        if (!root) return;
        if (root.dataset.shkCdekSyncBound === '1') return;
        root.dataset.shkCdekSyncBound = '1';

        var syncTimer = null;
        function scheduleSync(delay) {
          if (syncTimer) window.clearTimeout(syncTimer);
          syncTimer = window.setTimeout(function() {
            attemptSyncCityFromCdekSelection(root);
          }, Math.max(0, delay || 0));
        }

        function isCdekField(el) {
          if (!el) return false;
          var name = String(el.name || '').toLowerCase();
          var id = String(el.id || '').toLowerCase();
          var cls = String(el.className || '').toLowerCase();
          var ph = String(el.getAttribute && el.getAttribute('placeholder') || '').toLowerCase();
          var aria = String(el.getAttribute && el.getAttribute('aria-label') || '').toLowerCase();
          var wrapped = !!(el.closest && el.closest('[class*="cdek" i], [id*="cdek" i], [class*="sdek" i], [id*="sdek" i]'));
          return (
            name.indexOf('cdek') !== -1 ||
            name.indexOf('sdek') !== -1 ||
            id.indexOf('cdek') !== -1 ||
            id.indexOf('sdek') !== -1 ||
            cls.indexOf('cdek') !== -1 ||
            cls.indexOf('sdek') !== -1 ||
            ph.indexOf('поиск') !== -1 ||
            aria.indexOf('поиск') !== -1 ||
            wrapped
          );
        }

        root.addEventListener('change', function(e){
          var t = e.target;
          if (!isCdekField(t)) return;
          scheduleSync(120);
        }, true);

        root.addEventListener('blur', function(e){
          var t = e.target;
          if (!isCdekField(t)) return;
          scheduleSync(180);
        }, true);

        root.addEventListener('keydown', function(e){
          var t = e.target;
          if (!isCdekField(t)) return;
          if (e.key === 'Enter') {
            scheduleSync(220);
          }
        }, true);

        root.addEventListener('click', function(e){
          var node = e.target && e.target.closest ? e.target.closest('[role="option"], [class*="suggest"], [class*="dropdown-item"]') : null;
          if (!node) return;
          var directText = normalizeCityName(String(node.textContent || ''));
          if (directText && directText.length >= 3 && !isRegionLikeChunk(directText)) {
            syncWooShippingCity(root, directText);
            return;
          }
          scheduleSync(220);
        }, true);
      }

      function ensureCdekMapBootstrap(root) {
        if (!root) return;
        var shippingOptions = root.querySelector('fieldset.wc-block-checkout__shipping-option');
        if (!shippingOptions) return;
        var selected = shippingOptions.querySelector('.wc-block-components-radio-control__input:checked');
        if (!selected) return;
        var opt = selected.closest('.wc-block-components-radio-control__option');
        var hay = (
          String(selected.value || '') + ' ' +
          String(selected.id || '') + ' ' +
          String(opt ? opt.textContent || '' : '')
        ).toLowerCase();
        var isCdekLike = (
          hay.indexOf('cdek') !== -1 ||
          hay.indexOf('sdek') !== -1 ||
          hay.indexOf('сдэк') !== -1 ||
          hay.indexOf('пвз') !== -1
        );
        if (!isCdekLike) return;

        if (!getCurrentWooShippingCity(root)) {
          applySoftFallbackCity(root, 'Москва');
        }
      }

      function enforceAddressFieldVisibility(root) {
        if (!root) return;
        var shippingFields = root.querySelector('fieldset.wc-block-checkout__shipping-fields');
        var shippingForm = shippingFields ? shippingFields.querySelector('#shipping.wc-block-components-address-form') : null;
        if (shippingForm) {
          hideEl(shippingForm, '.wc-block-components-address-form__country');
          hideEl(shippingForm, '.wc-block-components-address-form__state');
          hideEl(shippingForm, '.wc-block-components-address-form__postcode');
          var lastWrap = shippingForm.querySelector('.wc-block-components-address-form__last_name');
          if (lastWrap) {
            lastWrap.style.display = 'none';
          }
        }
        if (shippingFields) {
          hideEl(shippingFields, '.wc-block-checkout__use-address-for-billing');
        }
      }

      function renameLabel(root, selector, text) {
        if (!root) return;
        var wrap = root.querySelector(selector);
        if (!wrap) return;
        var label = wrap.querySelector('label');
        if (label) label.textContent = text;
      }

      function setupHouseApartment(shippingForm) {
        if (!shippingForm) return;

        var address1Wrap = shippingForm.querySelector('.wc-block-components-address-form__address_1');
        var address2Input = shippingForm.querySelector('#shipping-address_2');
        var toggle = shippingForm.querySelector('.wc-block-components-address-form__address_2-toggle');

        if (!address1Wrap || !address2Input) return;
        if (toggle) toggle.style.display = 'none';
        address2Input.style.display = 'none';

        var existing = shippingForm.querySelector('.shk-house-apartment');
        if (!existing) {
          var row = document.createElement('div');
          row.className = 'shk-house-apartment';
          row.innerHTML =
            '<div class="wc-block-components-text-input shk-house-field">' +
              '<input type="text" id="shk-house-field" autocomplete="address-line2" />' +
              '<label for="shk-house-field">Дом</label>' +
            '</div>' +
            '<div class="wc-block-components-text-input shk-apartment-field">' +
              '<input type="text" id="shk-apartment-field" autocomplete="address-line2" />' +
              '<label for="shk-apartment-field">Квартира</label>' +
            '</div>';

          address1Wrap.insertAdjacentElement('afterend', row);
          existing = row;
        }

        var houseInput = existing.querySelector('#shk-house-field');
        var apartmentInput = existing.querySelector('#shk-apartment-field');
        if (!houseInput || !apartmentInput) return;

        var streetRow = shippingForm.querySelector('.shk-street-house-apartment');
        if (!streetRow) {
          streetRow = document.createElement('div');
          streetRow.className = 'shk-street-house-apartment';
          address1Wrap.insertAdjacentElement('beforebegin', streetRow);
        }
        if (!streetRow.contains(address1Wrap)) streetRow.appendChild(address1Wrap);
        if (!streetRow.contains(existing)) streetRow.appendChild(existing);

        var updateAddress2 = function () {
          var house = (houseInput.value || '').trim();
          var apartment = (apartmentInput.value || '').trim();
          var value = '';

          if (house) value += 'Дом ' + house;
          if (apartment) value += (value ? ', ' : '') + 'Квартира ' + apartment;

          address2Input.value = value;
          address2Input.dispatchEvent(new Event('input', { bubbles: true }));
          address2Input.dispatchEvent(new Event('change', { bubbles: true }));
        };

        if (!houseInput.dataset.shkBound) {
          houseInput.addEventListener('input', updateAddress2);
          apartmentInput.addEventListener('input', updateAddress2);
          houseInput.dataset.shkBound = '1';
          apartmentInput.dataset.shkBound = '1';
        }
      }

      function setupCityPostcode(shippingForm) {
        if (!shippingForm) return;

        var cityWrap = shippingForm.querySelector('.wc-block-components-address-form__city');
        var postcodeWrap = shippingForm.querySelector('.wc-block-components-address-form__postcode');
        if (!cityWrap || !postcodeWrap) return;

        var postcodeLabel = postcodeWrap.querySelector('label');
        if (postcodeLabel) postcodeLabel.textContent = 'Индекс';

        var row = shippingForm.querySelector('.shk-city-postcode');
        if (!row) {
          row = document.createElement('div');
          row.className = 'shk-city-postcode';
          postcodeWrap.insertAdjacentElement('beforebegin', row);
        }

        if (!row.contains(cityWrap)) row.appendChild(cityWrap);
        if (!row.contains(postcodeWrap)) row.appendChild(postcodeWrap);
      }

      function wait(ms) {
        return new Promise(function(resolve) {
          window.setTimeout(resolve, ms);
        });
      }

      function captureCurrentRateOptions(shippingOptions) {
        if (!shippingOptions) return [];
        var list = [];
        var options = shippingOptions.querySelectorAll('.wc-block-components-radio-control__option');
        options.forEach(function(opt) {
          var input = opt.querySelector('.wc-block-components-radio-control__input');
          var label = opt.querySelector('.wc-block-components-radio-control__label');
          var secondary = opt.querySelector('.wc-block-components-radio-control__secondary-label');
          if (!input || !label) return;
          list.push({
            value: String(input.value || ''),
            label: (label.textContent || '').trim(),
            secondary: secondary ? (secondary.textContent || '').trim() : '',
            checked: !!input.checked
          });
        });
        return list;
      }

      async function waitForRateOptions(shippingOptions, minCount, tries) {
        var need = minCount || 1;
        var maxTry = tries || 10;
        for (var i = 0; i < maxTry; i++) {
          var current = captureCurrentRateOptions(shippingOptions);
          if (current.length >= need) {
            return current;
          }
          await wait(180);
        }
        return captureCurrentRateOptions(shippingOptions);
      }

      function mountUnifiedShippingRates(root) {
        if (!root) return;
        var shippingMethodSwitch = root.querySelector('fieldset.wc-block-checkout__shipping-method');
        if (shippingMethodSwitch) {
          shippingMethodSwitch.style.setProperty('display', 'none', 'important');
        }
        var unified = root.querySelector('.shk-unified-shipping-list');
        if (unified) {
          unified.style.display = 'none';
          unified.innerHTML = '';
        }
        var nativeRatesWrap = root.querySelector('fieldset.wc-block-checkout__shipping-option .wc-block-components-shipping-rates-control');
        if (nativeRatesWrap) {
          nativeRatesWrap.style.display = '';
        }
      }

      function forceOrderNote(root) {
        var notes = root.querySelector('#order-notes');
        if (!notes) return;

        var addNote = notes.querySelector('.wc-block-checkout__add-note');
        var checkbox = addNote ? addNote.querySelector('input[type="checkbox"]') : null;

        if (checkbox && !checkbox.checked) {
          checkbox.click();
        }

        if (addNote) {
          addNote.style.display = 'none';
        }
      }

      function hideTermsNotice(root) {
        if (!root) return;
        var terms = root.querySelector('.wc-block-checkout__terms');
        if (terms) {
          terms.style.display = 'none';
        }
      }

      function movePlaceOrderButtonIntoSummary(root) {
        if (!root) return;

        var button = root.querySelector('.wc-block-components-checkout-place-order-button');
        var summaryTotals = root.querySelector('.wc-block-checkout__sidebar .wc-block-components-totals-wrapper .wc-block-components-totals-item.wc-block-components-totals-footer-item');
        if (!button || !summaryTotals) return;

        var summaryCard = summaryTotals.closest('.wp-block-woocommerce-checkout-order-summary-block');
        if (!summaryCard) return;

        var mount = summaryCard.querySelector('.shk-summary-place-order');
        if (!mount) {
          mount = document.createElement('div');
          mount.className = 'shk-summary-place-order';
          summaryTotals.parentNode.insertAdjacentElement('afterend', mount);
        }

        if (button.parentElement !== mount) {
          mount.appendChild(button);
        }

        var actionsRow = root.querySelector('.wc-block-checkout__actions_row');
        if (actionsRow) {
          actionsRow.style.display = 'none';
        }
      }

      function moveOrderItemsToMainTop(root) {
        if (!root) return;

        var form = root.querySelector('.wc-block-checkout__main .wc-block-checkout__form');
        var itemsBlock = root.querySelector('.wc-block-checkout__sidebar .wp-block-woocommerce-checkout-order-summary-cart-items-block');
        if (!form || !itemsBlock) return;

        var title = form.querySelector('.shk-main-checkout-title');
        if (!title) {
          title = document.createElement('h2');
          title.className = 'shk-main-checkout-title';
          title.textContent = 'Оформление заказа';
          form.insertAdjacentElement('afterbegin', title);
        }

        var mount = form.querySelector('.shk-main-order-items');
        if (!mount) {
          mount = document.createElement('div');
          mount.className = 'shk-main-order-items';
          if (title.nextSibling) {
            title.parentNode.insertBefore(mount, title.nextSibling);
          } else {
            form.appendChild(mount);
          }
        }

        if (itemsBlock.parentElement !== mount) {
          mount.appendChild(itemsBlock);
        }
      }

      function renameSummaryTitle(root) {
        if (!root) return;
        var summaryTitle = root.querySelector('.wc-block-checkout__sidebar .wc-block-components-checkout-order-summary__title-text');
        if (summaryTitle) {
          summaryTitle.textContent = 'Ваш заказ';
        }
      }

      function getCartItemsFromStore() {
        var wpData = window.wp && window.wp.data ? window.wp.data : null;
        if (!wpData || !wpData.select) return [];
        var storeKey = 'wc/store/cart';
        var selector = wpData.select(storeKey);
        if (!selector) return [];

        if (typeof selector.getCartItems === 'function') {
          var items = selector.getCartItems();
          return Array.isArray(items) ? items : [];
        }
        if (typeof selector.getCartData === 'function') {
          var cartData = selector.getCartData() || {};
          var list = cartData.items || [];
          return Array.isArray(list) ? list : [];
        }
        return [];
      }

      function removeCheckoutItemByKey(itemKey) {
        var wpData = window.wp && window.wp.data ? window.wp.data : null;
        if (!wpData || !wpData.dispatch || !itemKey) return false;
        var storeKey = 'wc/store/cart';
        var dispatch = wpData.dispatch(storeKey);
        if (!dispatch) return false;

        if (typeof dispatch.removeItemFromCart === 'function') {
          dispatch.removeItemFromCart(itemKey);
          return true;
        }
        if (typeof dispatch.removeCartItem === 'function') {
          dispatch.removeCartItem(itemKey);
          return true;
        }
        return false;
      }

      function findItemKeyForRow(row, fallbackIndex) {
        if (!row) return '';
        var directKeys = [
          row.getAttribute('data-cart-item-key'),
          row.getAttribute('data-item-key'),
          row.dataset ? row.dataset.cartItemKey : '',
          row.dataset ? row.dataset.itemKey : ''
        ];
        for (var i = 0; i < directKeys.length; i++) {
          if (directKeys[i]) return String(directKeys[i]);
        }

        var cartItems = getCartItemsFromStore();
        if (cartItems.length && fallbackIndex >= 0 && fallbackIndex < cartItems.length) {
          var item = cartItems[fallbackIndex] || {};
          return String(item.key || '');
        }
        return '';
      }

      function ensureCheckoutRemoveButtons(root) {
        if (!root) return;
        var rows = root.querySelectorAll('.shk-main-order-items .wc-block-components-order-summary-item');
        if (!rows.length) return;

        rows.forEach(function(row, idx){
          var content = row.querySelector('.wc-block-components-order-summary-item__description');
          if (!content) content = row;

          var btn = row.querySelector('.shk-checkout-item-remove');
          if (!btn) {
            btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'shk-checkout-item-remove';
            btn.textContent = 'Удалить';
            content.appendChild(btn);
          }

          var itemKey = findItemKeyForRow(row, idx);
          if (itemKey) {
            btn.dataset.itemKey = itemKey;
            btn.disabled = false;
          } else {
            btn.dataset.itemKey = '';
            btn.disabled = true;
          }

          if (!btn.dataset.shkBound) {
            btn.addEventListener('click', function(e){
              e.preventDefault();
              var key = String(btn.dataset.itemKey || '');
              if (!key) return;
              btn.disabled = true;
              var removed = removeCheckoutItemByKey(key);
              if (!removed) {
                btn.disabled = false;
                return;
              }
              setTimeout(function(){
                btn.disabled = false;
              }, 1500);
            });
            btn.dataset.shkBound = '1';
          }
        });
      }

      function ensurePrivacyConsentInSummary(root) {
        if (!root) return;

        var mount = root.querySelector('.wc-block-checkout__sidebar .shk-summary-place-order');
        if (!mount) return;

        var button = mount.querySelector('.wc-block-components-checkout-place-order-button');
        if (!button) return;

        var existing = mount.querySelector('.shk-privacy-consent');
        if (!existing) {
          var wrap = document.createElement('div');
          wrap.className = 'shk-privacy-consent form-group field-orderform-isprivacyaccept required';
          wrap.innerHTML =
            '<div class="custom-control custom-checkbox">' +
              '<input type="hidden" name="OrderForm[isPrivacyAccept]" value="0">' +
              '<input type="checkbox" id="orderform-isprivacyaccept" class="custom-control-input" name="OrderForm[isPrivacyAccept]" value="1" aria-required="true">' +
              '<label class="custom-control-label" for="orderform-isprivacyaccept">Я даю согласие на обработку персональных данных и соглашаюсь с <a href="/privacy-policy" target="_blank" rel="noopener">политикой конфиденциальности</a></label>' +
              '<div class="invalid-feedback"></div>' +
            '</div>';
          mount.appendChild(wrap);
          existing = wrap;
        }

        var checkbox = existing.querySelector('#orderform-isprivacyaccept');
        var feedback = existing.querySelector('.invalid-feedback');
        if (!checkbox) return;

        var updateState = function () {
          if (checkbox.checked) {
            button.disabled = false;
            button.classList.remove('shk-place-order-disabled');
            checkbox.classList.remove('is-invalid');
            existing.classList.remove('is-invalid');
            if (feedback) feedback.textContent = '';
          } else {
            button.disabled = true;
            button.classList.add('shk-place-order-disabled');
          }
        };

        if (!checkbox.dataset.shkBound) {
          checkbox.addEventListener('change', updateState);
          button.addEventListener('click', function (evt) {
            if (checkbox.checked) return;
            evt.preventDefault();
            evt.stopPropagation();
            checkbox.classList.add('is-invalid');
            existing.classList.add('is-invalid');
            if (feedback) feedback.textContent = 'Необходимо принять условия обработки персональных данных.';
          }, true);
          checkbox.dataset.shkBound = '1';
        }

        updateState();
      }

      function applyTweaks() {
        var started = performance.now();
        var root = document.querySelector('.wp-block-woocommerce-checkout.wc-block-checkout');
        if (!root) {
          dbg('applyTweaks: checkout root not ready');
          return false;
        }
        if (root.dataset.shkDonorReady === '1') return true;

        var contact = root.querySelector('fieldset.wc-block-checkout__contact-fields');
        var shippingFields = root.querySelector('fieldset.wc-block-checkout__shipping-fields');
        var shippingOptions = root.querySelector('fieldset.wc-block-checkout__shipping-option');
        var payment = root.querySelector('fieldset.wc-block-checkout__payment-method');

        if (!contact || !shippingFields || !shippingOptions || !payment) {
          dbg('applyTweaks: required blocks not ready');
          return false;
        }
        enforceCheckoutOrder(root);

        mountUnifiedShippingRates(root);

        setTitle(contact, 'Заполните информацию о себе');
        setTitle(shippingOptions, 'Параметры доставки');
        setTitle(shippingFields, 'Адрес доставки');
        setTitle(payment, 'Способ оплаты');

        var contactForm = contact.querySelector('#contact.wc-block-components-address-form');
        var shippingForm = shippingFields.querySelector('#shipping.wc-block-components-address-form');

        if (contactForm && shippingForm) {
          var phoneWrap = shippingForm.querySelector('.wc-block-components-address-form__phone');
          var emailWrap = contactForm.querySelector('.wc-block-components-address-form__email');

          if (phoneWrap && emailWrap && !contactForm.contains(phoneWrap)) {
            emailWrap.insertAdjacentElement('afterend', phoneWrap);
          }

          var firstWrap = shippingForm.querySelector('.wc-block-components-address-form__first_name');
          var lastWrap = shippingForm.querySelector('.wc-block-components-address-form__last_name');
          var firstInput = firstWrap ? firstWrap.querySelector('input') : null;
          var lastInput = lastWrap ? lastWrap.querySelector('input') : null;

          if (firstWrap && firstInput) {
            var firstLabel = firstWrap.querySelector('label');
            if (firstLabel) firstLabel.textContent = 'Имя и Фамилия';
            firstInput.placeholder = '';
            firstInput.autocomplete = 'name';
          }

          if (firstWrap && !contactForm.contains(firstWrap)) {
            contactForm.insertAdjacentElement('afterbegin', firstWrap);
          }

          if (phoneWrap && !contactForm.contains(phoneWrap)) {
            contactForm.appendChild(phoneWrap);
          }

          if (emailWrap && contactForm.lastElementChild !== emailWrap) {
            contactForm.appendChild(emailWrap);
          }

          renameLabel(contactForm, '.wc-block-components-address-form__phone', 'Телефон');
          renameLabel(contactForm, '.wc-block-components-address-form__email', 'Электронная почта');

          if (lastWrap) {
            lastWrap.style.display = 'none';
          }

          if (firstInput && lastInput && !firstInput.dataset.shkFullnameBound) {
            var syncNames = function () {
              var raw = (firstInput.value || '').trim().replace(/\s+/g, ' ');
              if (!raw) {
                lastInput.value = '-';
                return;
              }

              var parts = raw.split(' ');
              if (parts.length < 2) {
                lastInput.value = '-';
              } else {
                lastInput.value = parts.slice(1).join(' ');
              }

              lastInput.dispatchEvent(new Event('input', { bubbles: true }));
              lastInput.dispatchEvent(new Event('change', { bubbles: true }));
            };

            firstInput.addEventListener('input', syncNames);
            firstInput.addEventListener('blur', syncNames);
            firstInput.dataset.shkFullnameBound = '1';
            syncNames();
          }

          renameLabel(shippingForm, '.wc-block-components-address-form__city', 'Город');
          renameLabel(shippingForm, '.wc-block-components-address-form__address_1', 'Улица');
          hideEl(shippingForm, '.wc-block-components-address-form__country');
          hideEl(shippingForm, '.wc-block-components-address-form__state');
          hideEl(shippingForm, '.wc-block-components-address-form__postcode');
          hideEl(shippingFields, '.wc-block-checkout__use-address-for-billing');

          setupHouseApartment(shippingForm);
        }

        forceOrderNote(root);
        enforceAddressFieldVisibility(root);
        syncShippingAddressAvailability(root);
        bindCdekMapCitySync(root);
        ensureCdekMapBootstrap(root);
        hideTermsNotice(root);
        moveOrderItemsToMainTop(root);
        renameSummaryTitle(root);
        ensureCheckoutRemoveButtons(root);
        movePlaceOrderButtonIntoSummary(root);
        ensurePrivacyConsentInSummary(root);

        root.classList.add('shk-checkout-donor');
        root.dataset.shkDonorReady = '1';
        document.body.classList.add('shk-checkout-donor-ready');

        document.dispatchEvent(new Event('wc_update_checkout'));
        dbg('applyTweaks: ready', {
          ms: Math.round(performance.now() - started),
          selectedShipping: getSelectedShippingDebug(root)
        });
        return true;
      }

      var attempts = 0;
      var maxAttempts = 80;
      var timer = setInterval(function () {
        attempts += 1;
        if (applyTweaks() || attempts >= maxAttempts) {
          clearInterval(timer);
        }
      }, 250);

      document.addEventListener('change', function () {
        var root = document.querySelector('.wp-block-woocommerce-checkout.wc-block-checkout');
        dbg('event: change', getSelectedShippingDebug(root));
        enforceCheckoutOrder(root);
        mountUnifiedShippingRates(root);
        enforceAddressFieldVisibility(root);
        syncShippingAddressAvailability(root);
        bindCdekMapCitySync(root);
        ensureCdekMapBootstrap(root);
        hideTermsNotice(root);
        moveOrderItemsToMainTop(root);
        renameSummaryTitle(root);
        ensureCheckoutRemoveButtons(root);
        movePlaceOrderButtonIntoSummary(root);
        ensurePrivacyConsentInSummary(root);
      });

      document.addEventListener('wc-blocks_checkout_update', function () {
        var root = document.querySelector('.wp-block-woocommerce-checkout.wc-block-checkout');
        dbg('event: wc-blocks_checkout_update', getSelectedShippingDebug(root));
        enforceCheckoutOrder(root);
        mountUnifiedShippingRates(root);
        enforceAddressFieldVisibility(root);
        syncShippingAddressAvailability(root);
        bindCdekMapCitySync(root);
        ensureCdekMapBootstrap(root);
        hideTermsNotice(root);
        moveOrderItemsToMainTop(root);
        renameSummaryTitle(root);
        ensureCheckoutRemoveButtons(root);
        movePlaceOrderButtonIntoSummary(root);
        ensurePrivacyConsentInSummary(root);
      });
    })();
    </script>
    <?php
}
