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

      async function mountUnifiedShippingRates(root) {
        if (!root || root.dataset.shkUnifiedRatesBusy === '1') return;
        root.dataset.shkUnifiedRatesBusy = '1';

        try {
          var shippingMethodSwitch = root.querySelector('fieldset.wc-block-checkout__shipping-method');
          var shippingOptions = root.querySelector('fieldset.wc-block-checkout__shipping-option');
          if (!shippingMethodSwitch || !shippingOptions) return;

          var switchContainer = shippingMethodSwitch.querySelector('.wc-block-checkout__shipping-method-container');
          var switchModes = switchContainer ? switchContainer.querySelectorAll('.wc-block-checkout__shipping-method-option') : [];
          if (!switchContainer || !switchModes.length) return;

          var content = shippingOptions.querySelector('.wc-block-components-checkout-step__content');
          var nativeRatesWrap = shippingOptions.querySelector('.wc-block-components-shipping-rates-control');
          if (!content || !nativeRatesWrap) return;
          nativeRatesWrap.style.display = '';

          var modeData = [];
          var currentModeIdx = 0;
          switchModes.forEach(function(modeEl, idx) {
            var titleNode = modeEl.querySelector('.wc-block-checkout__shipping-method-option-title');
            var title = titleNode ? (titleNode.textContent || '').trim() : ('mode-' + idx);
            modeData.push({ idx: idx, title: title, el: modeEl, rates: [] });
            if (modeEl.getAttribute('aria-checked') === 'true') currentModeIdx = idx;
          });

          for (var i = 0; i < modeData.length; i++) {
            if (modeData[i].idx !== currentModeIdx) {
              modeData[i].el.click();
              await wait(450);
            }
            modeData[i].rates = await waitForRateOptions(shippingOptions, 1, 14);
          }

          if (modeData[currentModeIdx]) {
            modeData[currentModeIdx].el.click();
            await wait(200);
          }

          var activeRates = await waitForRateOptions(shippingOptions, 1, 8);
          var currentValue = '';
          activeRates.forEach(function(r) { if (r.checked) currentValue = r.value; });

          var unified = content.querySelector('.shk-unified-shipping-list');
          if (!unified) {
            unified = document.createElement('div');
            unified.className = 'shk-unified-shipping-list wc-block-components-radio-control';
            content.insertAdjacentElement('afterbegin', unified);
          } else {
            unified.innerHTML = '';
          }

          var rowsAdded = 0;
          modeData.forEach(function(mode) {
            mode.rates.forEach(function(rate) {
              if (!rate || !rate.value || !rate.label) return;
              var row = document.createElement('div');
              row.className = 'wc-block-components-radio-control__option shk-unified-shipping-option';
              if (mode.idx === currentModeIdx && rate.value === currentValue) {
                row.classList.add('wc-block-components-radio-control__option-checked');
              }
              row.setAttribute('data-shk-mode-idx', String(mode.idx));
              row.setAttribute('data-shk-rate-value', rate.value);
              row.setAttribute('data-shk-rate-label', rate.label);
              row.innerHTML =
                '<label><span class="wc-block-components-radio-control__option-layout">' +
                '<span class="wc-block-components-radio-control__label-group">' +
                '<span class="wc-block-components-radio-control__label"></span>' +
                '<span class="wc-block-components-radio-control__secondary-label"></span>' +
                '</span></span></label>' +
                '<input type="radio" class="wc-block-components-radio-control__input" style="display:none" value="">';

              var labelEl = row.querySelector('.wc-block-components-radio-control__label');
              var secEl = row.querySelector('.wc-block-components-radio-control__secondary-label');
              var hiddenInput = row.querySelector('.wc-block-components-radio-control__input');
              if (labelEl) labelEl.textContent = rate.label;
              if (secEl) secEl.textContent = rate.secondary || '';
              if (hiddenInput) hiddenInput.value = rate.value || '';

              row.addEventListener('click', function() {
                var targetModeIdx = parseInt(row.getAttribute('data-shk-mode-idx') || '0', 10);
                var targetValue = row.getAttribute('data-shk-rate-value') || '';
                var targetLabel = row.getAttribute('data-shk-rate-label') || '';
                var modeEl = switchModes[targetModeIdx];
                if (!modeEl) return;
                modeEl.click();
                window.setTimeout(function() {
                  var done = false;
                  var liveOptions = shippingOptions.querySelectorAll('.wc-block-components-shipping-rates-control .wc-block-components-radio-control__option .wc-block-components-radio-control__input');
                  liveOptions.forEach(function(inp) {
                    if (String(inp.value || '') === targetValue) {
                      var lbl = inp.closest('label');
                      if (lbl) {
                        lbl.click();
                      } else {
                        inp.click();
                      }
                      done = true;
                    }
                  });
                  if (!done && targetLabel) {
                    var liveRows = shippingOptions.querySelectorAll('.wc-block-components-shipping-rates-control .wc-block-components-radio-control__option');
                    liveRows.forEach(function(liveRow) {
                      if (done) return;
                      var liveLabel = liveRow.querySelector('.wc-block-components-radio-control__label');
                      var txt = liveLabel ? (liveLabel.textContent || '').trim() : '';
                      if (txt === targetLabel) {
                        var liveInput = liveRow.querySelector('.wc-block-components-radio-control__input');
                        if (liveInput) {
                          var liveLbl = liveInput.closest('label');
                          if (liveLbl) {
                            liveLbl.click();
                          } else {
                            liveInput.click();
                          }
                          done = true;
                        }
                      }
                    });
                  }
                }, 250);
              });

              unified.appendChild(row);
              rowsAdded += 1;
            });
          });

          if (rowsAdded > 0) {
            shippingMethodSwitch.style.display = 'none';
            nativeRatesWrap.style.display = 'none';
            unified.style.display = '';
          } else {
            shippingMethodSwitch.style.display = '';
            nativeRatesWrap.style.display = '';
            if (unified) {
              unified.style.display = 'none';
            }
          }
        } finally {
          root.dataset.shkUnifiedRatesBusy = '0';
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
        var root = document.querySelector('.wp-block-woocommerce-checkout.wc-block-checkout');
        if (!root) return false;
        if (root.dataset.shkDonorReady === '1') return true;

        var contact = root.querySelector('fieldset.wc-block-checkout__contact-fields');
        var shippingFields = root.querySelector('fieldset.wc-block-checkout__shipping-fields');
        var shippingOptions = root.querySelector('fieldset.wc-block-checkout__shipping-option');
        var payment = root.querySelector('fieldset.wc-block-checkout__payment-method');

        if (!contact || !shippingFields || !shippingOptions || !payment) return false;
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
        hideTermsNotice(root);
        moveOrderItemsToMainTop(root);
        renameSummaryTitle(root);
        movePlaceOrderButtonIntoSummary(root);
        ensurePrivacyConsentInSummary(root);

        root.classList.add('shk-checkout-donor');
        root.dataset.shkDonorReady = '1';
        document.body.classList.add('shk-checkout-donor-ready');

        document.dispatchEvent(new Event('wc_update_checkout'));
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
        enforceCheckoutOrder(root);
        mountUnifiedShippingRates(root);
        hideTermsNotice(root);
        moveOrderItemsToMainTop(root);
        renameSummaryTitle(root);
        movePlaceOrderButtonIntoSummary(root);
        ensurePrivacyConsentInSummary(root);
      });

      document.addEventListener('wc-blocks_checkout_update', function () {
        var root = document.querySelector('.wp-block-woocommerce-checkout.wc-block-checkout');
        enforceCheckoutOrder(root);
        mountUnifiedShippingRates(root);
        hideTermsNotice(root);
        moveOrderItemsToMainTop(root);
        renameSummaryTitle(root);
        movePlaceOrderButtonIntoSummary(root);
        ensurePrivacyConsentInSummary(root);
      });
    })();
    </script>
    <?php
}
