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

      function applyTweaks() {
        var root = document.querySelector('.wp-block-woocommerce-checkout.wc-block-checkout');
        if (!root) return false;
        if (root.dataset.shkDonorReady === '1') return true;

        var contact = root.querySelector('fieldset.wc-block-checkout__contact-fields');
        var shippingMethodSwitch = root.querySelector('fieldset.wc-block-checkout__shipping-method');
        var shippingFields = root.querySelector('fieldset.wc-block-checkout__shipping-fields');
        var shippingOptions = root.querySelector('fieldset.wc-block-checkout__shipping-option');
        var payment = root.querySelector('fieldset.wc-block-checkout__payment-method');

        if (!contact || !shippingFields || !shippingOptions || !payment) return false;

        if (shippingOptions.previousElementSibling !== contact && shippingOptions.previousElementSibling !== null) {
          contact.insertAdjacentElement('afterend', shippingOptions);
        }

        if (shippingFields.previousElementSibling !== shippingOptions) {
          shippingOptions.insertAdjacentElement('afterend', shippingFields);
        }

        var orderNotes = root.querySelector('#order-notes');
        if (orderNotes && orderNotes.previousElementSibling !== shippingFields) {
          shippingFields.insertAdjacentElement('afterend', orderNotes);
        }

        if (payment.previousElementSibling !== (orderNotes || shippingFields)) {
          (orderNotes || shippingFields).insertAdjacentElement('afterend', payment);
        }

        if (shippingMethodSwitch) {
          shippingMethodSwitch.style.display = 'none';
        }

        setTitle(contact, 'Заполните информацию о себе');
        setTitle(shippingOptions, 'Адрес и способ доставки');
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
    })();
    </script>
    <?php
}
