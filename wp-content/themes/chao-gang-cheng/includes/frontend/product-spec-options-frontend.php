<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 商品「二層規格選項」前台顯示。
 *
 * 在商品頁「加入購物車」按鈕上方，依後台設定的規格類別，逐類別顯示一排
 * 可點選的按鈕（不是下拉選單，也沒有「不指定」這個選項）；每個類別都要
 * 點選一個按鈕才算選完，任何類別沒選就不能加入購物車／立即購買。已點選
 * 的按鈕可以再點一次取消選取，回到「沒選」的狀態。用 JS 即時比對後台設
 * 定的組合表，命中「全部類別都選了」的組合時更新價格顯示；命中的組合如
 * 果庫存為 0 或被停用，顯示提示並停用「加入購物車」按鈕。
 *
 * 表單欄位命名維持 ckc_spec_selected[類別id]（用隱藏欄位存目前選到的值
 * id，按鈕本身不是表單欄位），跟後端（購物車/結帳/訂單整合、庫存扣減）
 * 讀取 $_POST 的方式相容，不需要另外改後端讀取邏輯。
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
            .ckc-spec-frontend .ckc-spec-frontend-group { margin-bottom: 14px; }
            .ckc-spec-frontend .ckc-spec-frontend-group > label {
                display: block;
                font-weight: 600;
                margin-bottom: 6px;
            }
            .ckc-spec-btn-group {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }
            .ckc-spec-value-btn {
                padding: 8px 16px;
                border: 1px solid #c9c2b8;
                border-radius: 6px;
                background: #ffffff;
                color: #333333;
                cursor: pointer;
                font-size: 14px;
                line-height: 1.4;
                /* 不用 transition：選取狀態要瞬間切換，跟下面「立即購買」
                   停用樣式踩過的過渡動畫問題是同一件事，這裡從一開始就
                   避免。 */
                transition: none;
            }
            .ckc-spec-value-btn:hover {
                border-color: var( --accent-color, #f86f69 );
            }
            .ckc-spec-value-btn.ckc-spec-value-btn--active {
                background: var( --accent-color, #f86f69 );
                border-color: var( --accent-color, #f86f69 );
                color: #ffffff;
                font-weight: 600;
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
            .ckc-spec-incomplete-hint {
                font-size: 14px;
                color: #8c7a64;
                margin: 8px 0;
            }
            /* 規格已額滿/已停用時，「立即購買」（含桌機／手機黏底列版本）
               也要跟主要的「加入購物車」按鈕一樣呈現停用外觀，避免使用者
               以為還能直接搶購。這裡踩過兩個坑，兩個都要一起處理才會真
               的顯示成灰色，缺一個都會看起來完全沒套用到樣式：
               1) Specificity 不夠：主題原本那條
                  `.woocommerce .product-action-buttons button.buy-now-btn.alt`
                  規則也是 !important，但它的 selector 比單純疊兩個 class
                  的 specificity 高，兩邊都 !important 時規則是比
                  specificity，不是比後寫先贏，所以這裡的 selector 要跟
                  主題那條打平或更高才蓋得過去。
               2) Transition 動畫：就算 specificity 贏了，因為這顆按鈕本
                  來就有 background-color 的 transition，單純切換 class
                  在瀏覽器眼裡是一次「正常的過渡動畫」，而正在跑的
                  transition 值的優先權比任何 !important 都高，所以還要
                  搭配下面 JS（setAddToCartDisabled）裡用 inline style
                  先把這顆按鈕的 transition 關掉，讓顏色是瞬間切換、不
                  透過過渡動畫，這裡的 background-color 才會真的顯示出
                  來。 */
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
            <div class="ckc-spec-frontend-group" data-cat-id="<?php echo esc_attr( $cat['id'] ); ?>">
                <label><?php echo esc_html( $cat['label'] ); ?></label>
                <div class="ckc-spec-btn-group">
                    <?php foreach ( $cat['values'] as $val ) : ?>
                        <button type="button" class="ckc-spec-value-btn" data-val-id="<?php echo esc_attr( $val['id'] ); ?>"><?php echo esc_html( $val['label'] ); ?></button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" class="ckc-spec-hidden-input" name="ckc_spec_selected[<?php echo esc_attr( $cat['id'] ); ?>]" value="">
            </div>
        <?php endforeach; ?>

        <p class="ckc-spec-incomplete-hint" style="display:none;">請選擇規格後才能加入購物車。</p>
        <p class="ckc-spec-price-preview" style="display:none;"></p>
        <p class="ckc-spec-stock-warning" style="display:none;"></p>

        <script>
        (function () {
            var wrap          = document.currentScript.closest('.ckc-spec-frontend');
            var groups        = wrap.querySelectorAll('.ckc-spec-frontend-group');
            var pricePreview  = wrap.querySelector('.ckc-spec-price-preview');
            var stockWarning  = wrap.querySelector('.ckc-spec-stock-warning');
            var incompleteHint = wrap.querySelector('.ckc-spec-incomplete-hint');
            var form          = wrap.closest('form.cart');

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
                groups.forEach( function ( g ) {
                    var input = g.querySelector('.ckc-spec-hidden-input');
                    sel[ g.getAttribute( 'data-cat-id' ) ] = input ? input.value : '';
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
                    // 先關掉這顆按鈕的 transition，讓下面切換 class 造成的
                    // background-color 改變是「瞬間套用」，不是走過渡動畫
                    // （不這樣做的話，畫面上顏色實測完全看不出變化，細節
                    // 說明見上面 <style> 裡的註解）。
                    btn.style.setProperty( 'transition', 'none', 'important' );
                    btn.classList.toggle( 'ckc-spec-disabled', disabled );
                } );
            }

            function refresh() {
                var selections = currentSelections();
                var key = fullMatchKey( selections );

                if ( ! key ) {
                    // 規格是必填欄位：只要還有類別沒選就不能加入購物車，
                    // 顯示提示告訴使用者要先選完規格。
                    pricePreview.style.display = 'none';
                    stockWarning.style.display = 'none';
                    incompleteHint.style.display = '';
                    setAddToCartDisabled( true );
                    return;
                }
                incompleteHint.style.display = 'none';

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

            groups.forEach( function ( group ) {
                var hiddenInput = group.querySelector('.ckc-spec-hidden-input');
                group.querySelectorAll('.ckc-spec-value-btn').forEach( function ( btn ) {
                    btn.addEventListener( 'click', function () {
                        var wasActive = btn.classList.contains( 'ckc-spec-value-btn--active' );
                        group.querySelectorAll('.ckc-spec-value-btn').forEach( function ( b ) {
                            b.classList.remove( 'ckc-spec-value-btn--active' );
                        } );
                        if ( wasActive ) {
                            // 再點一次已選取的按鈕：取消選取，回到「沒選」狀態
                            hiddenInput.value = '';
                        } else {
                            btn.classList.add( 'ckc-spec-value-btn--active' );
                            hiddenInput.value = btn.getAttribute( 'data-val-id' );
                        }
                        refresh();
                    } );
                } );
            } );

            // 一開始「規格沒選完整」預設就要停用「加入購物車」，但這段
            // <script> 是透過 woocommerce_before_add_to_cart_button 掛在
            // 按鈕「前面」輸出的，第一次同步執行到這裡的當下，瀏覽器根本
            // 還沒解析到後面那顆真正的按鈕元素——getSubmitBtn() 雖然是即
            // 時查詢，但「即時」也早於按鈕存在的時間點，一樣查不到（實測
            // 踩過），停用會被靜靜跳過。改成等 DOMContentLoaded（此時整份
            // HTML 都解析完了）才做第一次 refresh()，之後按鈕點擊觸發的
            // refresh() 不受影響，本來就是使用者互動之後才會發生。
            if ( 'loading' === document.readyState ) {
                document.addEventListener( 'DOMContentLoaded', refresh );
            } else {
                refresh();
            }
        })();
        </script>
    </div>
    <?php
}
