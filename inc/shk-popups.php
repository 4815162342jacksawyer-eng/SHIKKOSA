<?php
add_action('wp_footer', function(){
    ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  // Fix malformed selector used by legacy delegated handlers.
  if (window.jQuery && window.jQuery.find) {
    var oldFind = window.jQuery.find;
    var newFind = function(selector, context, results, seed) {
      if (typeof selector === 'string' && selector.indexOf('a[href="#callback\']') !== -1) {
        selector = selector.replace(/a\[href="#callback'\]/g, 'a[href="#callback"]');
      }
      return oldFind.call(this, selector, context, results, seed);
    };
    // Preserve jQuery.find static helpers (matchesSelector, matches, etc.).
    Object.keys(oldFind).forEach(function(key){
      try { newFind[key] = oldFind[key]; } catch (e) {}
    });
    if (oldFind.matches) newFind.matches = oldFind.matches;
    if (oldFind.matchesSelector) newFind.matchesSelector = oldFind.matchesSelector;
    window.jQuery.find = newFind;
  }

  // Prevent top-banner popup from closing on any click.
  var topBanner = document.querySelector('[data-id="8b17b39"]');
  if (topBanner) {
    topBanner.addEventListener('click', function (e) {
      e.stopPropagation();
    }, true);
  }
});
</script>
<?php
}, 5);

add_action('wp_footer', function(){
    // На страницах товара отключаем этот обработчик,
    // чтобы не конфликтовал с Woo/Elementor popup overlay.
    if ( is_product() ) {
        return;
    }
    ?>
<script>
document.addEventListener('click', function(e){
  var trigger = e.target.closest('a[href="#callback"], [data-shk-callback="1"]');
  if (!trigger) return;

  e.preventDefault();
  e.stopPropagation();

  setTimeout(function(){
    if (window.elementorProFrontend && window.elementorProFrontend.modules && window.elementorProFrontend.modules.popup) {
      try {
        window.elementorProFrontend.modules.popup.showPopup({ id: 10730 });
        return;
      } catch (err) {}
    }

    if (window.jQuery) {
      try {
        window.jQuery(document).trigger('elementor/popup/show', [10730]);
      } catch (err) {}
    }
  }, 120);
}, false);
</script>
<?php });
