<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function shikkosa_size_modal_shortcode() {
    ob_start();
    ?>
    <div id="sizeModal" class="favorite__modal active">
        <div class="size__modal">
            <div id="boxSize" class="size__modal_block">
                <div class="size__modal_grid">
                    <div class="size__modal_item">
                        <p class="size__modal_item_title">Международный размер</p>
                        <select class="size__modal_select js-size-switchSize" data-size-type="top">
                            <option value="XS">XS</option>
                            <option value="S">S</option>
                            <option value="M">M</option>
                            <option value="L">L</option>
                            <option value="XL">XL</option>
                            <option value="XXL">XXL</option>
                            <option value="S/M">S/M</option>
                            <option value="L/XL">L/XL</option>
                        </select>
                    </div>
                    <div class="size__modal_item">
                        <p class="size__modal_item_title">Обхват груди</p>
                        <input class="size__modal_input" type="text" data-size-field="t1" value="78-82" readonly>
                    </div>
                    <div class="size__modal_item">
                        <p class="size__modal_item_title">Обхват талии</p>
                        <input class="size__modal_input" type="text" data-size-field="t2" value="61-65" readonly>
                    </div>
                    <div class="size__modal_item">
                        <p class="size__modal_item_title">Обхват бедер</p>
                        <input class="size__modal_input" type="text" data-size-field="t3" value="86-90" readonly>
                    </div>
                </div>
                <div class="size__modal_grid">
                    <div class="size__modal_item">
                        <p class="size__modal_item_title">Международный размер</p>
                        <select class="size__modal_select js-size-switchSize" data-size-type="bottom">
                            <option value="70A">70A</option>
                            <option value="70B">70B</option>
                            <option value="70C">70C</option>
                            <option value="70D">70D</option>
                            <option value="70DD">70DD</option>
                            <option value="70F">70F</option>
                            <option value="75A">75A</option>
                            <option value="75B">75B</option>
                            <option value="75C">75C</option>
                            <option value="75D">75D</option>
                            <option value="75DD">75DD</option>
                            <option value="75E">75E</option>
                            <option value="75F">75F</option>
                            <option value="80B">80B</option>
                            <option value="80C">80C</option>
                            <option value="80D">80D</option>
                            <option value="80E">80E</option>
                            <option value="80F">80F</option>
                            <option value="85C">85C</option>
                            <option value="85D">85D</option>
                            <option value="85E">85E</option>
                            <option value="70E">70E</option>
                            <option value="70G">70G</option>
                            <option value="75G">75G</option>
                            <option value="85B">85B</option>
                            <option value="80G">80G</option>
                            <option value="85F">85F</option>
                            <option value="85G">85G</option>
                        </select>
                    </div>
                    <div class="size__modal_item">
                        <p class="size__modal_item_title">Обхват под грудью</p>
                        <input class="size__modal_input" type="text" data-size-field="t4" value="68-72" readonly>
                    </div>
                    <div class="size__modal_item">
                        <p class="size__modal_item_title">Обхват груди по выступающим точкам</p>
                        <input class="size__modal_input" type="text" data-size-field="t5" value="82-84" readonly>
                    </div>
                </div>
                <div class="size__modal_content">
                    <p>Белье должно быть не только красивым, но и комфортным, так как носим мы его ежедневно. Самый важный критерий комфорта при выборе нижнего белья – это точное знание своего размера.</p>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'shk_size_modal', 'shikkosa_size_modal_shortcode' );
