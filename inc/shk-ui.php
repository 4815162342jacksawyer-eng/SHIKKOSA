<?php
add_action('wp_footer', function () { ?>
<script>
document.addEventListener("DOMContentLoaded", () => {
  const SELECT_TOP_NAME = "select-1";
  const SELECT_BOTTOM_NAME = "select-2";

  const MAP_TOP = {
    "XS": { t1:"78–82", t2:"61–65", t3:"86–90" },
    "S":  { t1:"82–86", t2:"65–69", t3:"90–94" },
    "M":  { t1:"86–90", t2:"69–73", t3:"94–98" },
    "L":  { t1:"90–94", t2:"73–77", t3:"98–102" },
    "XL": { t1:"94–98", t2:"77–81", t3:"102–106" },
    "XXL":{ t1:"98–102",t2:"81–85", t3:"106–110" }
  };

  const MAP_BOTTOM = {
    "XS": { t4:"68–72", t5:"82–84" },
    "S":  { t4:"72–76", t5:"84–86" },
    "M":  { t4:"76–80", t5:"86–88" },
    "L":  { t4:"80–84", t5:"88–90" },
    "XL": { t4:"84–88", t5:"90–92" },
    "XXL":{ t4:"88–92", t5:"92–94" }
  };

  function bindForminator(form) {
    if (!form || form.dataset.shkBound === "1") return;
    form.dataset.shkBound = "1";

    const topSelect = form.querySelector(`[name="${SELECT_TOP_NAME}"]`);
    const bottomSelect = form.querySelector(`[name="${SELECT_BOTTOM_NAME}"]`);

    const t1 = form.querySelector('[name="text-1"]');
    const t2 = form.querySelector('[name="text-2"]');
    const t3 = form.querySelector('[name="text-3"]');
    const t4 = form.querySelector('[name="text-4"]');
    const t5 = form.querySelector('[name="text-5"]');

    [t1,t2,t3,t4,t5].forEach(f => {
      if (f) { f.readOnly = true; f.setAttribute("readonly","readonly"); }
    });

    function getSelectKey(selectEl) {
      if (!selectEl) return '';
      var val = String(selectEl.value || '').trim();
      var label = '';
      if (selectEl.selectedIndex >= 0) {
        var opt = selectEl.options[selectEl.selectedIndex];
        if (opt && opt.text) {
          label = String(opt.text || '').trim();
        }
      }
      return MAP_TOP[val] || MAP_BOTTOM[val] ? val : label;
    }

    const applyTop = (val) => {
      const d = MAP_TOP[val];
      if (!d) { [t1,t2,t3].forEach(f => f && (f.value="")); return; }
      if (t1) t1.value = d.t1;
      if (t2) t2.value = d.t2;
      if (t3) t3.value = d.t3;
    };

    const applyBottom = (val) => {
      const d = MAP_BOTTOM[val];
      if (!d) { [t4,t5].forEach(f => f && (f.value="")); return; }
      if (t4) t4.value = d.t4;
      if (t5) t5.value = d.t5;
    };

    if (topSelect) {
      topSelect.addEventListener("change", e => applyTop(getSelectKey(e.target)));
      applyTop(getSelectKey(topSelect));
    }

    if (bottomSelect) {
      bottomSelect.addEventListener("change", e => applyBottom(getSelectKey(e.target)));
      applyBottom(getSelectKey(bottomSelect));
    }
  }

  function scanAndBind() {
    document.querySelectorAll('.forminator-ui form').forEach(bindForminator);
  }

  scanAndBind();

  const observer = new MutationObserver(() => {
    scanAndBind();
  });
  observer.observe(document.body, { childList: true, subtree: true });
});
</script>
<?php }, 99);

add_action('wp_footer', function () { ?>
<script>
(function(){
  var MAP_TOP = {
    "XS": { t1:"78-82", t2:"61-65", t3:"86-90" },
    "S":  { t1:"83-87", t2:"65-70", t3:"90-95" },
    "M":  { t1:"88-92", t2:"70-75", t3:"95-100" },
    "L":  { t1:"93-97", t2:"75-80", t3:"100-105" },
    "XL": { t1:"98-102", t2:"80-85", t3:"105-110" },
    "XXL":{ t1:"103-107", t2:"85-90", t3:"110-115" },
    "S/M":{ t1:"83-92", t2:"65-75", t3:"90-100" },
    "L/XL":{ t1:"93-102", t2:"75-85", t3:"100-110" }
  };
  var MAP_BOTTOM = {
    "70A": { t4:"68-72", t5:"82-84" },
    "70B": { t4:"68-72", t5:"84-86" },
    "70C": { t4:"68-72", t5:"86-88" },
    "70D": { t4:"68-72", t5:"88-90" },
    "70DD": { t4:"68-72", t5:"90-92" },
    "70F": { t4:"68-72", t5:"92-94" },
    "75A": { t4:"73-77", t5:"87-89" },
    "75B": { t4:"73-77", t5:"89-91" },
    "75C": { t4:"73-77", t5:"91-93" },
    "75D": { t4:"73-77", t5:"93-95" },
    "75DD": { t4:"73-77", t5:"95-97" },
    "75E": { t4:"73-77", t5:"95-97" },
    "75F": { t4:"73-77", t5:"97-99" },
    "80B": { t4:"78-82", t5:"94-96" },
    "80C": { t4:"78-82", t5:"96-98" },
    "80D": { t4:"78-82", t5:"98-100" },
    "80E": { t4:"78-82", t5:"100-102" },
    "80F": { t4:"78-82", t5:"102-104" },
    "85C": { t4:"83-87", t5:"101-103" },
    "85D": { t4:"83-87", t5:"103-105" },
    "85E": { t4:"83-87", t5:"105-107" },
    "70E": { t4:"68-72", t5:"90-92" },
    "70G": { t4:"68-72", t5:"94-96" },
    "75G": { t4:"73-75", t5:"99-101" },
    "85B": { t4:"83-87", t5:"99-101" },
    "80G": { t4:"78-82", t5:"104-106" },
    "85F": { t4:"83-87", t5:"107-109" },
    "85G": { t4:"83-87", t5:"109-111" }
  };

  function applyMap(map, value, fields, root) {
    fields.forEach(function(field){
      var el = root.querySelector('[data-size-field="' + field + '"]');
      if (!el) return;
      el.value = map[value] ? map[value][field] : '';
    });
  }

  function updateFromSelect(select) {
    if (!select) return;
    var root = select.closest('#sizeModal');
    if (!root) return;
    var type = select.getAttribute('data-size-type');
    if (type === 'top') {
      applyMap(MAP_TOP, select.value, ['t1','t2','t3'], root);
    } else {
      applyMap(MAP_BOTTOM, select.value, ['t4','t5'], root);
    }
  }

  function initSizeModal(root) {
    if (!root || root.dataset.shkSizeInit === "1") return;
    root.dataset.shkSizeInit = "1";

    root.querySelectorAll('.js-size-switchSize').forEach(function(select){
      updateFromSelect(select);
    });
  }

  function scan() {
    document.querySelectorAll('#sizeModal').forEach(initSizeModal);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', scan);
  } else {
    scan();
  }

  if (window.jQuery) {
    window.jQuery(document).on('elementor/popup/show', function(){
      setTimeout(scan, 50);
      setTimeout(function(){
        document.querySelectorAll('#sizeModal .js-size-switchSize').forEach(updateFromSelect);
      }, 150);
    });
  }

  var observer = new MutationObserver(function(){
    scan();
  });
  observer.observe(document.body, { childList: true, subtree: true });

  document.addEventListener('change', function(e){
    var select = e.target.closest && e.target.closest('#sizeModal .js-size-switchSize');
    if (!select) return;
    updateFromSelect(select);
  });

  var retries = 0;
  var timer = setInterval(function(){
    retries += 1;
    document.querySelectorAll('#sizeModal .js-size-switchSize').forEach(updateFromSelect);
    if (retries >= 10) clearInterval(timer);
  }, 500);
})();
</script>
<?php }, 120);

add_action('wp_footer', function(){ ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
  var stickyElement = document.querySelector('[data-id="f862b8f"]');
  if(!stickyElement) return;

  window.addEventListener('scroll', function(){
    var scrolled = window.scrollY > 30;
    if(scrolled){
      stickyElement.style.setProperty('background-color', 'var(--e-global-color-text)', 'important');
      stickyElement.style.setProperty('box-shadow', '0 2px 10px rgba(0,0,0,0.15)', 'important');
    } else {
      stickyElement.style.setProperty('background-color', '', 'important');
      stickyElement.style.setProperty('box-shadow', '', 'important');
    }
  }, {passive: true});
});
</script>
<?php });

add_action('wp_footer', function(){ ?>
<script>
(function(){
  function parseLoopPriceValue(raw){
    var text = String(raw || '').replace(/\s+/g, ' ');
    var normalized = text
      .replace(/\u00a0/g, '')
      .replace(/[^\d,.\-]/g, '')
      .replace(',', '.');
    var value = parseFloat(normalized);
    return isFinite(value) ? value : 0;
  }

  function refreshLoop4016DiscountTags(root){
    var scope = root || document;
    scope.querySelectorAll('.elementor-4016.e-loop-item').forEach(function(card){
      if (!card) return;

      var tagText = card.querySelector('.shk-product-tag .elementor-shortcode');
      if (!tagText) return;

      var priceBox = card.querySelector('.elementor-element.elementor-element-848c657 .elementor-image-box-content');
      if (!priceBox) return;

      var title = priceBox.querySelector('.elementor-image-box-title');
      var desc = priceBox.querySelector('.elementor-image-box-description');
      if (!title || !desc) return;

      var titlePrice = parseLoopPriceValue(title.textContent || '');
      var descPrice = parseLoopPriceValue(desc.textContent || '');
      if (!(titlePrice > 0) || !(descPrice > 0)) return;

      var original = Math.max(titlePrice, descPrice);
      var current = Math.min(titlePrice, descPrice);
      if (!(original > current)) return;

      var percent = Math.round(((original - current) / original) * 100);
      if (!(percent > 0)) return;

      var discountText = '-' + String(percent) + '%';
      if (String(tagText.textContent || '').trim() !== discountText) {
        tagText.textContent = discountText;
      }
    });
  }

  function swapLoop4016PriceRows(root){
    var scope = root || document;
    scope.querySelectorAll('.elementor-4016 .elementor-element.elementor-element-848c657 .elementor-image-box-content').forEach(function(box){
      if (!box || box.dataset.shkPriceSwapped === '1') return;

      var title = box.querySelector('.elementor-image-box-title');
      var desc = box.querySelector('.elementor-image-box-description');
      if (!title || !desc) return;

      var titleText = String(title.textContent || '').replace(/\s+/g, ' ').trim();
      var descText = String(desc.textContent || '').replace(/\s+/g, ' ').trim();
      if (!titleText || !descText) return;

      var titleHtml = title.innerHTML;
      title.innerHTML = desc.innerHTML;
      desc.innerHTML = titleHtml;
      box.dataset.shkPriceSwapped = '1';
    });
  }

  function bootLoop4016Swap(){
    swapLoop4016PriceRows(document);
    refreshLoop4016DiscountTags(document);
    var obs = new MutationObserver(function(){
      swapLoop4016PriceRows(document);
      refreshLoop4016DiscountTags(document);
    });
    obs.observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootLoop4016Swap);
  } else {
    bootLoop4016Swap();
  }

  function enhanceMiniCart(root){
    var wrap = root || document;
    wrap.querySelectorAll('.widget_shopping_cart_content').forEach(function(cart){
      cart.classList.add('shk-mini-cart-enhanced');

      var emptyMessage = cart.querySelector('.woocommerce-mini-cart__empty-message, p');
      if (emptyMessage) {
        var raw = String(emptyMessage.textContent || '').trim();
        if (
          raw === 'No products in the cart.' ||
          raw === 'No products in cart.'
        ) {
          emptyMessage.textContent = 'В корзине пока нет товаров.';
        }
      }

      cart.querySelectorAll('.elementor-menu-cart__product.woocommerce-cart-form__cart-item').forEach(function(item){
        var nameLink = item.querySelector('.elementor-menu-cart__product-name a');
        var price = item.querySelector('.elementor-menu-cart__product-price');
        var remove = item.querySelector('.elementor-menu-cart__product-remove .elementor_remove_from_cart_button');
        if (!price || !remove) return;

        function syncRemoveLink(delLink) {
          if (!delLink || !remove) return;
          delLink.href = remove.getAttribute('href') || delLink.href || '#';
          delLink.classList.add('elementor_remove_from_cart_button', 'remove_from_cart_button');

          var attrs = ['data-product_id', 'data-cart_item_key', 'data-product_sku', 'aria-label'];
          attrs.forEach(function(attr){
            var val = remove.getAttribute(attr);
            if (val !== null) {
              delLink.setAttribute(attr, val);
            }
          });
        }

        var actions = item.querySelector('.shk-mini-cart-actions');
        if (!actions) {
          actions = document.createElement('div');
          actions.className = 'shk-mini-cart-actions';

          var edit = document.createElement('a');
          edit.className = 'shk-mini-cart-edit';
          edit.textContent = 'Редактировать';
          edit.href = nameLink ? nameLink.href : '/cart/';
          actions.appendChild(edit);

          var del = document.createElement('a');
          del.className = 'shk-mini-cart-remove';
          del.textContent = 'Удалить';
          syncRemoveLink(del);
          actions.appendChild(del);

          price.insertAdjacentElement('afterend', actions);
        } else {
          var delExisting = actions.querySelector('.shk-mini-cart-remove');
          if (delExisting) {
            syncRemoveLink(delExisting);
          }
        }
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function(){ enhanceMiniCart(document); });
  } else {
    enhanceMiniCart(document);
  }

  var refreshQueued = false;
  function queueEnhance(target) {
    if (refreshQueued) return;
    refreshQueued = true;
    requestAnimationFrame(function(){
      refreshQueued = false;
      enhanceMiniCart(target || document);
    });
  }

  var mo = new MutationObserver(function(mutations){
    var shouldRefresh = false;
    mutations.forEach(function(m){
      if (shouldRefresh) return;
      var target = m.target;
      if (!(target instanceof Element)) return;

      if (
        target.closest('.elementor-menu-cart__container') ||
        target.matches('.widget_shopping_cart_content, .elementor-menu-cart__container, .elementor-menu-cart__main')
      ) {
        shouldRefresh = true;
      }
    });
    if (shouldRefresh) queueEnhance(document);
  });
  mo.observe(document.body, {childList:true, subtree:true});
})();
</script>
<?php }, 180);

add_action('wp_footer', function(){ ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
  document.querySelectorAll('.catalog__filter').forEach(function(filter){
    var btn = filter.querySelector('.js-catalog-toggleFilter');
    if (!btn) return;
    var params = new URLSearchParams(window.location.search);
    if (params.has('sort') || params.has('size') || params.has('color')) {
      filter.classList.add('is-open');
      btn.classList.add('active');
    } else {
      filter.classList.remove('is-open');
      btn.classList.remove('active');
    }
    btn.addEventListener('click', function(){
      filter.classList.toggle('is-open');
      btn.classList.toggle('active', filter.classList.contains('is-open'));
    });

    filter.addEventListener('click', function(e){
      var link = e.target.closest && e.target.closest('.catalog__filter_link');
      if (!link) return;
      if (link.closest('.catalog__filter_nav.catalog-menu')) return;
      filter.classList.add('is-open');
      btn.classList.add('active');
    });
  });
});
</script>
<?php });

add_action('wp_footer', function(){ ?>
<script>
document.addEventListener('click', function(e){
  var link = e.target.closest && e.target.closest('.catalog__filter_link.is-disabled');
  if (!link) return;

  var isSize = link.closest('.catalog__filter_item') && link.closest('.catalog__filter_item').querySelector('.catalog__filter_label') && link.closest('.catalog__filter_item').querySelector('.catalog__filter_label').textContent.toLowerCase().indexOf('размер') !== -1;
  var isColor = link.classList.contains('color-link');

  if (!isSize && !isColor) return;

  e.preventDefault();

  try {
    var url = new URL(link.href, window.location.origin);
    if (isSize) {
      url.searchParams.delete('color');
    }
    if (isColor) {
      url.searchParams.delete('size');
    }
    window.location.href = url.toString();
  } catch (err) {
    window.location.href = link.href;
  }
});
</script>
<?php });

add_action('wp_footer', function(){ ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
  var triggers = document.querySelectorAll('.e-n-menu-item #offcanvas_1 a.e-n-menu-title-container');
  if (!triggers.length) return;

  triggers.forEach(function(offcanvasTrigger){
    var firstItem = offcanvasTrigger.closest('.e-n-menu-item');
    if (!firstItem) return;
    if (firstItem.querySelector('.catalog-native-link')) return;

    var menuRoot = firstItem.closest('[id^="menubar-"]');
    if (menuRoot) {
      menuRoot.classList.add('catalog-native-menu');
      var lastItem = menuRoot.querySelector('.e-n-menu-item:last-child');
      if (lastItem) {
        lastItem.classList.add('catalog-native-last');
      }
    }
    firstItem.classList.add('catalog-native-first');

    firstItem.style.position = 'relative';
    var catalogLink = document.createElement('a');
    catalogLink.className = 'catalog-native-link e-n-menu-title-container e-focus e-link';
    catalogLink.href = '/product-category/catalog/';
    catalogLink.setAttribute('aria-current', 'page');
    catalogLink.innerHTML = offcanvasTrigger.innerHTML;
    catalogLink.style.cssText = 'position:absolute;inset:0;z-index:2;';
    firstItem.appendChild(catalogLink);

    // Keep trigger accessible; hiding it with aria-hidden may conflict with focus management.
    offcanvasTrigger.style.pointerEvents = 'none';
    offcanvasTrigger.style.opacity = '0';

    function isOffcanvasOpen() {
      return !!document.querySelector('[id^="off-canvas-"][aria-hidden="false"]');
    }

    function openOffcanvas(e){
      if (e && typeof e.preventDefault === 'function') {
        e.preventDefault();
      }
      if (!offcanvasTrigger || !offcanvasTrigger.isConnected || isOffcanvasOpen()) {
        return;
      }

      try {
        // Elementor Mega Menu handles opening more reliably on click than synthetic mouseenter.
        offcanvasTrigger.click();
      } catch (err) {
        // Last-resort fallback.
        offcanvasTrigger.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
      }
    }
    catalogLink.addEventListener('mouseenter', openOffcanvas);
    catalogLink.addEventListener('focus', openOffcanvas);
  });
});
</script>
<?php });
