<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 商品「二層規格選項」前台顯示（第 2 期）。
 *
 * 只做：在商品頁「加入購物車」按鈕上方顯示規格類別選單（可不選，預設
 * 「不指定」），選擇時用 JS 即時比對後台設定的組合表，命中「全部類別
 * 都選了」的組合時更新價格顯示；命中的組合如果庫存為 0 或被停用，顯示
 * 提示並停用「加入購物車」按鈕。
 *
 * 不包含（留到後續階段）：加入購物車/購物車頁/結帳頁/訂單資料整合、
 * 庫存扣減與超賣防護。這裡的 <select> 欄位命名為
 * ckc_spec_selected[類別id]，本來就在 WooCommerce 的 form.cart 表單裡，
 * 選擇結果會隨表單一起送到伺服器，方便後續階段直接讀取，但目前還沒有
 * 任何程式碼處理這個送出的資料。
 */

add_action( 'woocommerce_before_add_to_cart_button', 'chao_gang_cheng_render_spec_options_frontend', 5 );
function chao_gang_cheng_render_spec_options_frontend() {
    global $product;

    if ( ! $product instanceof WC_Product ) {
        return;
    }
    if ( ! chao_gang_cheng_product_uses_spec_options( $product->get_id() ) ) {
        return;
    }

    $categories = chao_gang_cheng_get_spec_categories( $product->get_id() );
    if ( empty( $categories ) ) {
        return;
    }

    $combinations = chao_gang_cheng_get_spec_combinations( $product->get_id() );
    $base_price   = (float) $product->get_price();
    $cat_ids      = wp_list_pluck( $categories, 'id' );
    sort( $cat_ids ); // 跟後台儲存組合 key 用的類別 id 字母序一致
    ?>
    <div class="ckc-spec-frontend">
        <style>
            .ckc-spec-frontend { margin: 0 0 16px; }
            .ckc-spec-frontend .ckc-spec-frontend-group { margin-bottom: 10px; }
            .ckc-spec-frontend .ckc-spec-frontend-group label {
                display: block;
                font-weight: 600;
                margin-bottom: 4px;
            }
            .ckc-spec-frontend select {
                min-width: 200px;
                padding: 6px 10px;
            }
            .ckc-spec-price-preview {
                font-size: 15px;
                color: #a8501c;
                font-weight: 600;
                margin: 8px 0;
            }
            .ckc-spec-stock-warning {
                font-size: 14px;
                color: #b32d2e;
                margin: 8px 0;
            }
            /* 規格已額滿/已停用時，「立即購買」（含桌機／手機黏底列版本）
               也要跟主要的「加入購物車」按鈕一樣呈現停用外觀，避免使用者
               以為還能直接搶購。
               注意：這裡不能只用 opacity/filter——實測發現 .buy-now-btn
               本來就有 transition，用 opacity 降不透明度時，瀏覽器會把它
               當成一次「正常的過渡動畫」來處理，同一次 style 重新計算內
               算出的 computed style 還是舊值，视覺上完全看不出變灰（不是
               動畫本身的問題，是這顆按鈕原本的樣式規則 specificity 比對
               直接蓋過）。改用 background-color／color 直接換成灰色最保
               險；同時 selector 要跟主題原本那條
               `.woocommerce .product-action-buttons button.buy-now-btn.alt`
               規則的 specificity 打平或更高（同樣是 !important，光加
               !important 沒用，specificity 較低還是會輸），才蓋得過去。 */
            .woocommerce .product-action-buttons button.buy-now-btn.alt.ckc-spec-disabled,
            .buy-now-btn.ckc-spec-disabled,
            .sticky-buy-now-btn.ckc-spec-disabled,
            .mydybox-taiwan-for-woocommerce-sticky-btn.ckc-spec-disabled {
                background-color: #b0b0b0 !important;
                color: #ffffff !important;
                cursor: not-allowed !important;
                pointer-events: none !important;
            }
        </style>

        <?php foreach ( $categories as $cat ) : ?>
            <div class="ckc-spec-frontend-group">
                <label for="ckc-spec-select-<?php echo esc_attr( $cat['id'] ); ?>"><?php echo esc_html( $cat['label'] ); ?></label>
                <select
                    id="ckc-spec-select-<?php echo esc_attr( $cat['id'] ); ?>"
                    class="ckc-spec-frontend-select"
                    name="ckc_spec_selected[<?php echo esc_attr( $cat['id'] ); ?>]"
                    data-cat-id="<?php echo esc_attr( $cat['id'] ); ?>"
                >
                    <option value="">不指定</option>
                    <?php foreach ( $cat['values'] as $val ) : ?>
                        <option value="<?php echo esc_attr( $val['id'] ); ?>"><?php echo esc_html( $val['label'] ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endforeach; ?>

        <p class="ckc-spec-price-preview" style="display:none;"></p>
        <p class="ckc-spec-stock-warning" style="display:none;"></p>

        <script>
        (function () {
            var wrap        = document.currentScript.closest('.ckc-spec-frontend');
            var selects      = wrap.querySelectorAll('.ckc-spec-frontend-select');
            var pricePreview = wrap.querySelector('.ckc-spec-price-preview');
            var stockWarning = wrap.querySelector('.ckc-spec-stock-warning');
            var form         = wrap.closest('form.cart');

            // 注意：這段 script 是透過 woocommerce_before_add_to_cart_button 掛進去的，
            // 在 HTML 原始碼裡排在「加入購物車」按鈕的「前面」，所以頁面第一次解析
            // 這段 <script> 的當下，按鈕元素根本還沒被瀏覽器解析出來——如果在這裡
            // 先查一次快取起來，拿到的會是 null，之後永遠停用不了按鈕（實測踩過）。
            // 改成每次要用的時候才即時查詢，確保一定拿得到當下真正存在的按鈕。
            function getSubmitBtn() {
                return form ? form.querySelector('button[type="submit"], .single_add_to_cart_button') : null;
            }

            var combos       = <?php echo wp_json_encode( is_array( $combinations ) ? $combinations : new stdClass() ); ?>;
            var sortedCatIds = <?php echo wp_json_encode( array_values( $cat_ids ) ); ?>;
            var basePrice    = <?php echo wp_json_encode( $base_price ); ?>;
            var currency     = <?php echo wp_json_encode( get_woocommerce_currency_symbol() ); ?>;

            function formatPrice( amount ) {
                var rounded = Math.round( amount );
                return currency + rounded.toLocaleString( 'zh-TW' );
            }

            function currentSelections() {
                var sel = {};
                selects.forEach( function ( s ) {
                    sel[ s.getAttribute( 'data-cat-id' ) ] = s.value;
                } );
                return sel;
            }

            // 只有「每一個類別都選了」才算完整命中組合表，key 的組成方式要
            // 跟後台儲存時的排序（類別 id 字母序）完全一致才對得起來。
            function fullMatchKey( selections ) {
                var parts = [];
                for ( var i = 0; i < sortedCatIds.length; i++ ) {
                    var catId = sortedCatIds[ i ];
                    var val   = selections[ catId ];
                    if ( ! val ) {
                        return null; // 這個類別沒選，不算完整命中
                    }
                    parts.push( catId + ':' + val );
                }
                return parts.join( '|' );
            }

            // 「立即購買」按鈕（主要版本 + 桌機/手機黏底列版本）跟主要的
            // 「加入購物車」按鈕共用同一個 form.cart，送出時一樣會帶著
            // 目前選到的規格一起送到伺服器，所以伺服器端的驗證本來就會擋
            // 下已額滿/已停用的組合，不會真的賣超。但畫面上先前這幾顆
            // 「立即購買」按鈕沒有跟著停用，使用者會誤以為還能直接搶購，
            // 送出後才被導回商品頁看到錯誤訊息，體驗不好，這裡一起處理。
            var buyNowSelectors = '.buy-now-btn, .sticky-buy-now-btn, .mydybox-taiwan-for-woocommerce-sticky-btn';

            function setAddToCartDisabled( disabled ) {
                var submitBtn = getSubmitBtn();
                if ( submitBtn ) {
                    submitBtn.disabled = disabled;
                    submitBtn.classList.toggle( 'disabled', disabled );
                    submitBtn.classList.toggle( 'wc-variation-selection-needed', disabled );
                }

                document.querySelectorAll( buyNowSelectors ).forEach( function ( btn ) {
                    btn.disabled = disabled;
                    btn.classList.toggle( 'ckc-spec-disabled', disabled );
                } );
            }

            function refresh() {
                var selections = currentSelections();
                var key = fullMatchKey( selections );

                if ( ! key ) {
                    // 部分選擇或完全沒選：維持原價，正常可以加入購物車
                    pricePreview.style.display = 'none';
                    stockWarning.style.display = 'none';
                    setAddToCartDisabled( false );
                    return;
                }

                var combo = combos[ key ];

                if ( ! combo ) {
                    // 選到的組合在後台資料裡找不到（例如新增值後尚未存檔），
                    // 安全起見一律當作沒有價格調整，正常可以加入購物車
                    pricePreview.style.display = 'none';
                    stockWarning.style.display = 'none';
                    setAddToCartDisabled( false );
                    return;
                }

                var soldOut = ( combo.enabled === false ) || ( combo.stock_qty !== null && Number( combo.stock_qty ) <= 0 );

                if ( soldOut ) {
                    pricePreview.style.display = 'none';
                    stockWarning.textContent = '此規格組合目前無法選購（已額滿或已停用），請選擇其他組合。';
                    stockWarning.style.display = '';
                    setAddToCartDisabled( true );
                    return;
                }

                var adjust   = combo.price_adjust ? Number( combo.price_adjust ) : 0;
                var adjusted = basePrice + adjust;
                var adjustText = adjust !== 0
                    ? '（原價 ' + formatPrice( basePrice ) + '，' + ( adjust > 0 ? '+' : '-' ) + Math.round( Math.abs( adjust ) ).toLocaleString( 'zh-TW' ) + '）'
                    : '';
                pricePreview.textContent = '此規格價格：' + formatPrice( adjusted ) + adjustText;
                pricePreview.style.display = '';
                stockWarning.style.display = 'none';
                setAddToCartDisabled( false );
            }

            selects.forEach( function ( s ) {
                s.addEventListener( 'change', refresh );
            } );

            refresh();
        })();
        </script>
    </div>
    <?php
}
