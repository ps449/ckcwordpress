<?php
add_action( 'wp_footer', 'chao_checkout_custom_js_css' );
function chao_checkout_custom_js_css() {
    if ( ! is_checkout() ) {
        return;
    }
    ?>
    <style>
    /* Custom Checkout Section Styles：淺色牛皮紙系（呼應領券中心／我的帳號設計語彙） */
    .chao-checkout-section {
        background: #fffaf1;
        border: 1px solid #e2d2b3;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 4px 12px rgba(26,20,15,0.04);
    }
    .chao-section-title {
        font-size: 18px;
        font-weight: 700;
        color: #1a140f;
        margin-bottom: 20px;
        border-bottom: 2px solid #c9974a;
        padding-bottom: 10px;
        font-family: Georgia, "Times New Roman", "Songti TC", "PMingLiU", serif;
    }
    .chao-sub-title {
        font-size: 15px;
        font-weight: 600;
        color: #3a2f24;
        margin: 15px 0 10px 0;
    }
    
    /* Grid Layouts */
    .chao-shipping-cards-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
    }
    .chao-payment-cards-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 16px;
    }
    @media (min-width: 640px) {
        .chao-payment-cards-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    /* Payment Icon Styles */
    .chao-payment-icon {
        margin-right: 12px;
        flex-shrink: 0;
        vertical-align: middle;
    }
    .chao-payment-icon-gpay {
        margin-right: 12px;
        flex-shrink: 0;
        vertical-align: middle;
    }
    
    /* LINE Pay Logo Styling */
    .chao-linepay-logo {
        display: inline-flex;
        align-items: center;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        font-weight: 900;
        font-style: italic;
        gap: 2px;
        margin-right: 12px;
        flex-shrink: 0;
        user-select: none;
    }
    .chao-linepay-text-line {
        color: #000000;
        font-size: 20px;
        letter-spacing: -1px;
        line-height: 1;
    }
    .chao-linepay-text-pay {
        background: #00c300;
        color: #ffffff;
        font-size: 13px;
        padding: 1px 6px;
        border-radius: 3px;
        font-style: normal;
        font-weight: 900;
        display: inline-block;
        margin-left: 2px;
        line-height: 1.2;
    }
    
    /* Cards Style：金框選取，取代原本的黑框樣板感 */
    .chao-card {
        border: 1px solid #e2d2b3;
        border-radius: 10px;
        padding: 16px 20px;
        display: flex;
        align-items: center;
        cursor: pointer;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        background: #fffaf1;
        user-select: none;
    }
    .chao-card:hover {
        border-color: #c9974a;
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(26,20,15,0.06);
    }
    .chao-card.active {
        border: 2px solid #c9974a !important;
        background: #fff;
        box-shadow: 0 2px 8px rgba(201,151,74,0.15);
    }

    /* Circle Checkmark Icon */
    .chao-card-check {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        border: 2px solid #d8c39c;
        margin-right: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.25s;
        flex-shrink: 0;
        position: relative;
    }
    .chao-card.active .chao-card-check {
        border-color: #c9974a;
        background: #c9974a;
    }
    .chao-card.active .chao-card-check::after {
        content: "";
        width: 9px;
        height: 5px;
        border-left: 2px solid #fff;
        border-bottom: 2px solid #fff;
        transform: rotate(-45deg);
        position: absolute;
        top: 6px;
        left: 5px;
    }
    .chao-card-text {
        font-size: 15px;
        font-weight: 600;
        color: #1a140f;
    }

    /* CVS options section */
    .chao-cvs-options {
        margin-top: 20px;
        border-top: 1px dashed #e2d2b3;
        padding-top: 20px;
    }
    .chao-cvs-subcard {
        border: 1px solid #e2d2b3;
        border-radius: 10px;
        padding: 16px 20px;
        display: flex;
        align-items: center;
        background: #fffaf1;
        position: relative;
        margin-bottom: 16px;
    }
    .chao-cvs-subcard.active {
        border: 2px solid #c9974a;
        background: #fff;
    }
    .chao-cvs-info {
        display: flex;
        justify-content: space-between;
        width: 100%;
        align-items: center;
        margin-right: 14px;
    }
    .chao-cvs-name {
        font-size: 15px;
        font-weight: 600;
        color: #1a140f;
    }
    .chao-cvs-price {
        font-size: 15px;
        font-weight: 700;
        color: #8c7a64;
    }
    .chao-cvs-subcard.active .chao-cvs-price {
        color: #1a140f;
    }
    .chao-cvs-free-shipping-msg {
        position: absolute;
        bottom: -22px;
        left: 56px;
        font-size: 12px;
        color: #f86f69;
        font-weight: 500;
    }

    /* Custom store selection button */
    .chao-select-store-btn {
        width: 100%;
        background: #fffaf1;
        border: 1px solid #c9974a;
        color: #1a140f;
        font-weight: 600;
        font-size: 14px;
        padding: 12px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
        text-align: center;
        margin-top: 26px;
    }
    .chao-select-store-btn:hover {
        background: #fdf3e0;
        border-color: #b28a58;
    }
    .chao-selected-store-info {
        background: #f6ecd9;
        border: 1px solid #e2d2b3;
        border-radius: 8px;
        padding: 14px 18px;
        margin-top: 14px;
        font-size: 14px;
        color: #3a2f24;
        line-height: 1.5;
    }
    .chao-store-name {
        font-weight: 700;
        color: #1a140f;
    }

    /* Payment styling */
    .chao-payment-info {
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }
    .chao-payment-title {
        font-size: 15px;
        font-weight: 700;
        color: #1a140f;
    }
    .chao-payment-desc {
        font-size: 13px;
        color: #8c7a64;
        font-weight: 400;
    }
    
    /* Hide native WooCommerce elements */
    #shipping_method input[type="radio"] {
        display: none !important;
    }
    #shipping_method {
        list-style: none !important;
        margin: 0 !important;
        padding: 0 !important;
        display: block !important;
    }
    .woocommerce-checkout-payment ul.payment_methods {
        display: none !important;
    }
    
    /* Center and Enlarge Place Order Button */
    .form-row.place-order {
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        float: none !important;
        width: 100% !important;
        margin: 20px auto 0 auto !important;
        padding: 0 !important;
    }
    #place_order {
        order: 1 !important;
        float: none !important;
        display: block !important;
        margin: 0 auto !important;
        font-size: 18px !important;
        padding: 16px 24px !important;
        width: 100% !important;
        max-width: 450px !important;
        border-radius: 30px !important;
        font-weight: 700 !important;
        transition: all 0.25s ease-in-out !important;
        box-shadow: 0 4px 14px rgba(248,111,105,0.3) !important;
        background-color: #f86f69 !important;
        color: #fff !important;
        border: none !important;
    }
    #place_order:hover {
        transform: translateY(-1px) !important;
        box-shadow: 0 6px 18px rgba(248,111,105,0.4) !important;
        background-color: #e85850 !important;
    }
    .woocommerce-terms-and-conditions-wrapper {
        order: 2 !important;
        width: 100% !important;
        text-align: center !important;
        margin-top: 15px !important;
    }
    #chao-trust-seals {
        order: 3 !important;
        width: 100% !important;
    }
    </style>
    
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // --- 優化 WooCommerce 原生動畫與讀取效果 (減少等待感) ---
        // 1. 降低 blockUI 預設淡入淡出時間 (原預設約 400ms)
        if (typeof $.blockUI !== 'undefined') {
            $.blockUI.defaults.fadeIn = 50;
            $.blockUI.defaults.fadeOut = 50;
        }
        
        // 2. 攔截並加速結帳頁的滾動動畫 (scrollTop) - 避免原生的 1000ms 緩慢滾動
        var originalAnimate = $.fn.animate;
        $.fn.animate = function(prop, speed, easing, callback) {
            if (prop && typeof prop.scrollTop !== 'undefined' && $('form.checkout').length) {
                // 如果是滾動動畫，將速度鎖定為 150ms 快速完成
                speed = 150; 
            }
            return originalAnimate.apply(this, [prop, speed, easing, callback]);
        };
        // ----------------------------------------------------
    });
        
    if (typeof window.mydyboxCvs === 'undefined') {
        window.mydyboxCvs = {
            ajaxUrl: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
            nonce: '<?php echo esc_js( wp_create_nonce( 'mydybox_cvs_map' ) ); ?>',
            cvsType: '<?php echo ( get_option( 'wooecpay_logistic_cvs_type', 'C2C' ) === 'C2C' ) ? 'UNIMARTC2C' : 'UNIMART'; ?>'
        };
    }
    jQuery(document).ready(function($) {
        var myMapWindow = null;

        // Remove MyDyBox phone number hyphen auto-formatting
        function removePhoneFormattingHandlers() {
            $(document.body).off('blur', '#billing_phone').off('focus', '#billing_phone');
        }
        removePhoneFormattingHandlers();
        $(document.body).on('focus click', '#billing_phone, #shipping_phone', removePhoneFormattingHandlers);
        
        // Strip non-digits from input fields in real-time
        $(document.body).on('input change blur focus', '#billing_phone, #shipping_phone', function() {
            var cleanVal = this.value.replace(/[^0-9]/g, '');
            if (this.value !== cleanVal) {
                this.value = cleanVal;
            }
        });
        
        // Run cleanup periodically on load to clean fields populated by AJAX / WooCommerce settings
        var cleanInterval = setInterval(function() {
            removePhoneFormattingHandlers();
            $('#billing_phone, #shipping_phone').each(function() {
                var cleanVal = this.value.replace(/[^0-9]/g, '');
                if (this.value !== cleanVal) {
                    this.value = cleanVal;
                }
            });
        }, 150);
        setTimeout(function() {
            clearInterval(cleanInterval);
        }, 4000);

        // Insert custom layout after WooCommerce forms load
        function initCustomCheckout() {
            // 1. Generate Custom Shipping Section HTML (if not already injected)
            if ($('#chao-shipping-section').length === 0) {
                var shippingHtml = `
                <div class="chao-checkout-section" id="chao-shipping-section">
                    <div class="chao-section-title">配送方式</div>
                    <div class="chao-shipping-cards-grid">
                        <div class="chao-card chao-shipping-card" data-method="cvs">
                            <div class="chao-card-check"></div>
                            <div class="chao-card-text">超商</div>
                        </div>
                        <div class="chao-card chao-shipping-card" data-method="delivery">
                            <div class="chao-card-check"></div>
                            <div class="chao-card-text">宅配</div>
                        </div>
                        <div class="chao-card chao-shipping-card" data-method="pickup">
                            <div class="chao-card-check"></div>
                            <div class="chao-card-text">門市自取</div>
                        </div>
                    </div>
                    
                    <div class="chao-cvs-options" style="display: none;">
                        <div class="chao-sub-title">請選擇超商</div>
                        <div class="chao-cvs-subcard chao-card active">
                            <div class="chao-card-check"></div>
                            <div class="chao-cvs-info">
                                <div class="chao-cvs-name">7-11 冷凍取貨(先付款)</div>
                                <div class="chao-cvs-price" id="chao-cvs-rate-price">NT$<?php echo esc_js( intval( chao_get_cvs_shipping_cost() ) ); ?></div>
                            </div>
                            <div class="chao-cvs-free-shipping-msg"></div>
                        </div>
                        <button type="button" class="chao-select-store-btn">請選擇取貨門市</button>
                        <input type="hidden" id="mydybox_cvs_store_id" name="mydybox_cvs_store_id" value="">
                        <input type="hidden" id="mydybox_cvs_store_name" name="mydybox_cvs_store_name" value="">
                        <input type="hidden" id="mydybox_cvs_store_addr" name="mydybox_cvs_store_addr" value="">
                        <input type="hidden" id="mydybox_cvs_store_type" name="mydybox_cvs_store_type" value="">
                        <div class="chao-selected-store-info" style="display: none;">
                            <strong>已選門市：</strong> <span class="chao-store-name"></span>
                        </div>
                    </div>
                </div>
                `;
                // Insert shipping layout before customer details form
                $('#customer_details').before(shippingHtml);
            }
            
            // 2. Generate Custom Payment Section HTML (if not already injected)
            if ($('#chao-payment-section').length === 0) {
                var paymentHtml = `
                <div class="chao-checkout-section" id="chao-payment-section">
                    <div class="chao-section-title">付款方式</div>
                    <div class="chao-payment-cards-grid">
                        <div class="chao-card chao-payment-card" data-payment="credit">
                            <div class="chao-card-check"></div>
                            <svg class="chao-payment-icon" viewBox="0 0 36 24" width="36" height="24" fill="none" stroke="#1a140f" stroke-width="1.5">
                                <rect x="2" y="2" width="32" height="20" rx="3" />
                                <rect x="6" y="7" width="8" height="6" rx="1" />
                                <circle cx="24" cy="15" r="3.5" />
                                <circle cx="28" cy="15" r="3.5" />
                            </svg>
                            <div class="chao-payment-info">
                                <span class="chao-payment-title">信用卡安全支付</span>
                                <span class="chao-payment-desc">信用卡一次付清 (VISA、MasterCard、JCB)</span>
                            </div>
                        </div>
                        <div class="chao-card chao-payment-card" data-payment="cod" style="display: none;">
                            <div class="chao-card-check"></div>
                            <svg class="chao-payment-icon" viewBox="0 0 36 24" width="36" height="24" fill="none" stroke="#1a140f" stroke-width="1.5">
                                <rect x="3" y="4" width="30" height="16" rx="2" />
                                <circle cx="18" cy="12" r="3" />
                                <path d="M7 12h3M26 12h3" />
                            </svg>
                            <div class="chao-payment-info">
                                <span class="chao-payment-title">超商取貨付款</span>
                                <span class="chao-payment-desc">貨到 7-11 / 全家超商再付款</span>
                            </div>
                        </div>
                        <div class="chao-card chao-payment-card" data-payment="linepay">
                            <div class="chao-card-check"></div>
                            <div class="chao-linepay-logo">
                                <span class="chao-linepay-text-line">LINE</span>
                                <span class="chao-linepay-text-pay">Pay</span>
                            </div>
                            <div class="chao-payment-info">
                                <span class="chao-payment-title">LINE Pay</span>
                                <span class="chao-payment-desc">使用 LINE Pay 行動支付，可折抵 LINE Points</span>
                            </div>
                        </div>
                        <div class="chao-card chao-payment-card" data-payment="atm">
                            <div class="chao-card-check"></div>
                            <svg class="chao-payment-icon" viewBox="0 0 36 24" width="36" height="24" fill="none" stroke="#1a140f" stroke-width="1.5">
                                <rect x="3" y="2" width="30" height="15" rx="2" />
                                <text x="18" y="11" font-family="sans-serif" font-weight="900" font-size="7" fill="#1a140f" text-anchor="middle" stroke="none">ATM</text>
                                <path d="M10,17 L6,22 L30,22 L26,17 Z" />
                            </svg>
                            <div class="chao-payment-info">
                                <span class="chao-payment-title">虛擬 ATM 轉帳</span>
                                <span class="chao-payment-desc">虛擬帳號轉帳：支援各家銀行 ATM / 網路銀行轉帳</span>
                            </div>
                        </div>
                        <div class="chao-card chao-payment-card" data-payment="cvscode">
                            <div class="chao-card-check"></div>
                            <svg class="chao-payment-icon" viewBox="0 0 36 24" width="36" height="24" fill="none" stroke="#1a140f" stroke-width="1.5">
                                <rect x="4" y="2" width="28" height="20" rx="2" />
                                <path d="M8 6h20M8 10h20M8 14h12M8 18h16" />
                            </svg>
                            <div class="chao-payment-info">
                                <span class="chao-payment-title">超商代碼繳費</span>
                                <span class="chao-payment-desc">超商代碼繳費：至超商多媒體機台列印繳費單</span>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="chao_chosen_payment_method" id="chao_chosen_payment_method" value="credit">
                </div>
                `;
                // Insert payment layout inside the payment div container
                $('.woocommerce-checkout-payment').prepend(paymentHtml);
            }

            // 3. Inject Secure Payment Overlay Loader if not exists
            if ($('#chao-checkout-loader-overlay').length === 0) {
                $('body').append(`
                    <div id="chao-checkout-loader-overlay" style="display:none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.6); backdrop-filter: blur(4px); z-index: 999999; justify-content: center; align-items: center; font-family: -apple-system, BlinkMacSystemFont, sans-serif;">
                        <div style="background: #fff; padding: 30px; border-radius: 12px; max-width: 400px; text-align: center; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); margin: 20px;">
                            <div style="width: 48px; height: 48px; border: 4px solid #f1f5f9; border-top-color: #3b82f6; border-radius: 50%; animation: chao-spin 1s linear infinite; margin: 0 auto 20px;"></div>
                            <h3 style="margin: 0 0 10px 0; color: #0f172a; font-size: 18px; font-weight: 700;">系統正在處理您的訂單...</h3>
                            <p style="margin: 0; color: #64748b; font-size: 14px; line-height: 1.6;">請勿重新整理網頁，或重複點擊送出按鈕，以免造成重複扣款或重複訂單。</p>
                        </div>
                    </div>
                    <style>
                        @keyframes chao-spin {
                            0% { transform: rotate(0deg); }
                            100% { transform: rotate(360deg); }
                        }
                    </style>
                `);
            }
            
            syncUIStates();
        }
        
        // Synchronize WooCommerce states to Custom UI
        function syncUIStates() {
            // Clean up any hyphens/spaces from phone fields (e.g. populated from user profile database)
            $('#billing_phone, #shipping_phone').each(function() {
                var cleanVal = this.value.replace(/[^0-9]/g, '');
                if (this.value !== cleanVal) {
                    this.value = cleanVal;
                }
            });

            // Display only the active shipping method and cost next to 運費
            $('#shipping_method li').hide();
            $('#shipping_method input:checked').closest('li').show();

            // --- SHIPPING ---
            var activeShipping = $('input[name^="shipping_method"]:checked').val() || '';
            
            // 7-11 CVS -> Wooecpay_Logistic_CVS_711
            if (activeShipping.indexOf('Wooecpay_Logistic_CVS_711') !== -1) {
                $('.chao-shipping-card[data-method="cvs"]').addClass('active').siblings().removeClass('active');
                $('.chao-cvs-options').show();
            }
            // Home Delivery -> flat_rate or free_shipping or Wooecpay_Logistic_Home_Tcat
            else if (activeShipping.indexOf('flat_rate') !== -1 || activeShipping.indexOf('free_shipping') !== -1 || activeShipping.indexOf('Wooecpay_Logistic_Home_Tcat') !== -1) {
                $('.chao-shipping-card[data-method="delivery"]').addClass('active').siblings().removeClass('active');
                $('.chao-cvs-options').hide();
            }
            // Local Pickup -> local_pickup
            else if (activeShipping.indexOf('local_pickup') !== -1) {
                $('.chao-shipping-card[data-method="pickup"]').addClass('active').siblings().removeClass('active');
                $('.chao-cvs-options').hide();
            }
            
            // Sync CVS displayed cost from the actual WooCommerce shipping_method radio label
            var $cvsRadio = $('input[name^="shipping_method"][value^="Wooecpay_Logistic_CVS_711"]');
            if ($cvsRadio.length) {
                // WooCommerce renders cost in the <label> adjacent to the radio, inside a .woocommerce-Price-amount span
                var $cvsLabel = $cvsRadio.closest('li').find('.woocommerce-Price-amount');
                var cvsCostText = $cvsLabel.length ? $cvsLabel.text().trim() : '';
                if (cvsCostText) {
                    $('#chao-cvs-rate-price').text(cvsCostText);
                } else {
                    // Fallback: read full label text and strip the method name prefix
                    var fullLabel = $cvsRadio.closest('li').find('label').text().trim();
                    // label typically looks like: "7-11 超商冷凍取貨：NT$280.00"
                    var colonIdx = fullLabel.lastIndexOf('：');
                    if (colonIdx === -1) colonIdx = fullLabel.lastIndexOf(':');
                    if (colonIdx !== -1) {
                        $('#chao-cvs-rate-price').text(fullLabel.substring(colonIdx + 1).trim());
                    }
                }
            }

            // Check if store info is selected
            var storeName = $('#mydybox_cvs_store_name').val() || '';
            var storeAddr = $('#mydybox_cvs_store_addr').val() || '';
            if (storeName) {
                $('.chao-store-name').text(storeName + ' (' + storeAddr + ')');
                $('.chao-selected-store-info').show();
                $('.chao-select-store-btn').text('更換取貨門市');
            } else {
                $('.chao-selected-store-info').hide();
                $('.chao-select-store-btn').text('請選擇取貨門市');
            }
            
            // --- SHIPPING FIELDS DYNAMIC TOGGLE ---
            var isCvsOrPickup = activeShipping.indexOf('Wooecpay_Logistic_CVS_711') !== -1 || activeShipping.indexOf('local_pickup') !== -1;
            if (isCvsOrPickup) {
                // CVS or Local Pickup -> Hide address fields
                $('#billing_state_field, #billing_city_field, #billing_address_1_field, #billing_postcode_field').hide();
                $('#shipping_state_field, #shipping_city_field, #shipping_address_1_field, #shipping_postcode_field').hide();
                $('.woocommerce-shipping-fields').hide(); // Also hide ship to different address checkbox wrapper
            } else {
                // Home Delivery -> Show address fields
                $('#billing_state_field, #billing_city_field, #billing_address_1_field, #billing_postcode_field').show();
                $('#shipping_state_field, #shipping_city_field, #shipping_address_1_field, #shipping_postcode_field').show();
                $('.woocommerce-shipping-fields').show();
            }
            
            // --- SHIPPING & PAYMENT BINDINGS ---
            var isCvs = activeShipping.indexOf('Wooecpay_Logistic_CVS_711') !== -1;
            var $codCard = $('.chao-payment-card[data-payment="cod"]');
            if (isCvs) {
                $codCard.show();
            } else {
                $codCard.hide();
                // If COD was chosen but shipping is no longer CVS, revert payment to credit
                if ($('#chao_chosen_payment_method').val() === 'cod') {
                    $('#chao_chosen_payment_method').val('credit');
                    $('input[name="payment_method"][value="chao_ecpay_ecpg"]').prop('checked', true).trigger('click');
                }
            }

            // Sync payment card active class
            var chosenPayment = $('#chao_chosen_payment_method').val() || 'credit';
            $('.chao-payment-card[data-payment="' + chosenPayment + '"]').addClass('active').siblings().removeClass('active');

            // Trigger checkout helpers
            updateSubmitButtonText();
            initTrustSeals();
            initCollapsibleOrderSummary();
            addInlineValidation();
        }
        
        // Handle custom card click events
        $(document.body).on('click', '.chao-shipping-card', function() {
            var method = $(this).data('method');
            var targetVal = '';
            
            if (method === 'cvs') {
                targetVal = $('input[name^="shipping_method"][value^="Wooecpay_Logistic_CVS_711"]').val();
            } else if (method === 'delivery') {
                // Prefer free_shipping, then flat_rate, then Wooecpay_Logistic_Home_Tcat
                var freeRadio = $('input[name^="shipping_method"][value^="free_shipping"]');
                var flatRadio = $('input[name^="shipping_method"][value^="flat_rate"]');
                var tcatRadio = $('input[name^="shipping_method"][value^="Wooecpay_Logistic_Home_Tcat"]');
                
                if (freeRadio.length > 0) targetVal = freeRadio.val();
                else if (flatRadio.length > 0) targetVal = flatRadio.val();
                else if (tcatRadio.length > 0) targetVal = tcatRadio.val();
            } else if (method === 'pickup') {
                targetVal = $('input[name^="shipping_method"][value^="local_pickup"]').val();
            }
            
            if (targetVal) {
                $('input[name^="shipping_method"][value="' + targetVal + '"]').prop('checked', true).trigger('change');
            }
        });
        
        $(document.body).on('click', '.chao-payment-card', function() {
            var payment = $(this).data('payment');
            $('#chao_chosen_payment_method').val(payment);
            
            if (payment === 'credit') {
                // 信用卡對應到新開發的綠界站內付 2.0 (chao_ecpay_ecpg)
                $('input[name="payment_method"][value="chao_ecpay_ecpg"]').prop('checked', true).trigger('click');
            } else if (payment === 'cod') {
                // 超商取貨付款對應到 WooCommerce 原生 cod
                $('input[name="payment_method"][value="cod"]').prop('checked', true).trigger('click');
            } else {
                // 其他付款方式 (LINE Pay, ATM 等) 仍走舊的 MyDyBox AIO 全方位金流 (mydybox_ecpay)
                $('input[name="payment_method"][value="mydybox_ecpay"]').prop('checked', true).trigger('click');
            }
            
            syncUIStates();
        });
        
        // Handle ECPay Store selection map button click (bypass provider check in original JS)
        $(document.body).on('click', '.chao-select-store-btn', function() {
            if (typeof mydyboxCvs !== 'undefined' && mydyboxCvs.ajaxUrl) {
                $.post(mydyboxCvs.ajaxUrl, {
                    action: 'mydybox_open_cvs_map',
                    nonce: mydyboxCvs.nonce,
                    cvs_type: mydyboxCvs.cvsType || 'UNIMART'
                }, function(res) {
                    if (!res.success) return;
                    var popup = window.open('', 'mydybox_cvs_map', 'width=1000,height=680,scrollbars=yes');
                    popup.document.open();
                    popup.document.write(res.data.form);
                    popup.document.close();
                    try { popup.document.forms[0].submit(); } catch (e) {}
                    myMapWindow = popup;
                });
            }
        });

        // Listen for postMessage from map popup
        window.addEventListener('message', function(e) {
            if (e.origin !== window.location.origin) return;
            if (!e.data || e.data.type !== 'mydybox_cvs_store') return;
            var store = e.data.store;
            if (!store || !store.id) return;

            $('#mydybox_cvs_store_id').val(store.id);
            $('#mydybox_cvs_store_name').val(store.name);
            $('#mydybox_cvs_store_addr').val(store.addr);
            $('#mydybox_cvs_store_type').val(store.type);

            if (myMapWindow) {
                myMapWindow.close();
                myMapWindow = null;
            }
            
            syncUIStates();
        });
        
        // Validate store selection on Place Order & trigger safe loading overlay
        $(document.body).on('checkout_place_order', function() {
            var activeShipping = $('input[name^="shipping_method"]:checked').val() || '';
            if (activeShipping.indexOf('Wooecpay_Logistic_CVS_711') !== -1) {
                var storeId = $('#mydybox_cvs_store_id').val() || '';
                if (!storeId) {
                    alert('請先選擇取貨門市！');
                    return false;
                }
            }
            
            // Show secure loading overlay to prevent double-clicks
            $('#chao-checkout-loader-overlay').css('display', 'flex');
        });

        // Hide overlay on checkout failure
        $(document.body).on('checkout_error', function() {
            $('#chao-checkout-loader-overlay').hide();
        });
        
        // Monitor hidden store values for changes using timer
        setInterval(function() {
            var currentStore = $('#mydybox_cvs_store_name').val() || '';
            var displayedStore = $('.chao-store-name').text() || '';
            if (currentStore && displayedStore.indexOf(currentStore) === -1) {
                syncUIStates();
            }
        }, 1000);

        // --- CHECKOUT UI HELPER FUNCTIONS ---

        function updateSubmitButtonText() {
            var totalText = $('.order-total .amount').first().text() || $('.order-total td').first().text() || '';
            if (totalText) {
                $('#place_order').text('確認付款 ' + totalText);
            }
        }
        
        function initTrustSeals() {
            if ($('#chao-trust-seals').length > 0) return;
            var sealsHtml = `
            <div id="chao-trust-seals" style="margin-top: 15px; text-align: center; border-top: 1px dashed #cbd5e1; padding-top: 15px; width: 100%;">
                <div style="display: flex; justify-content: center; gap: 15px; margin-bottom: 8px; flex-wrap: wrap;">
                    <span style="font-size: 12px; color: #64748b; display: flex; align-items: center; gap: 4px;">🛡️ SSL 256位元安全加密</span>
                </div>
                <div style="font-size: 11px; color: #94a3b8; line-height: 1.4;">
                    本網站採用綠界科技安全交易模組，全面保護您的付款與個人隱私資訊。
                </div>
            </div>
            `;
            $('#place_order').after(sealsHtml);
        }

        function initCollapsibleOrderSummary() {
            if ($(window).width() > 768 || $('#chao-collapsible-summary-trigger').length > 0) return;
            
            var totalText = $('.order-total .amount').first().text() || $('.order-total td').first().text() || '';
            
            var triggerHtml = `
            <div id="chao-collapsible-summary-trigger" style="display: flex; justify-content: space-between; align-items: center; padding: 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 15px; cursor: pointer; font-size: 14px; font-weight: 600; color: #0f172a;">
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span></span>
                    <span>顯示訂單明細</span>
                    <span id="chao-summary-arrow" style="font-size: 10px; transition: transform 0.2s;">▼</span>
                </div>
                <div style="color: #1e40af;">${totalText}</div>
            </div>
            `;
            
            var $reviewTable = $('#order_review');
            if ($reviewTable.length > 0 && $('#chao-summary-wrapper').length === 0) {
                $reviewTable.before(triggerHtml);
                $reviewTable.wrap('<div id="chao-summary-wrapper" style="display: none; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; margin-bottom: 20px; background: #fff;"></div>');
                
                $('#chao-collapsible-summary-trigger').on('click', function() {
                    $('#chao-summary-wrapper').slideToggle(200);
                    var arrow = $('#chao-summary-arrow');
                    if (arrow.text() === '▼') {
                        arrow.text('▲');
                    } else {
                        arrow.text('▼');
                    }
                });
            }
        }

        function addInlineValidation() {
            var fields = [
                { id: '#billing_first_name', errorMsg: '請輸入您的真實完整姓名', validate: val => val.trim().length >= 2 },
                { id: '#billing_phone', errorMsg: '請輸入有效的台灣手機號碼（例：0912345678）', validate: val => {
                    var activeShipping = $('input[name^="shipping_method"]:checked').val() || '';
                    var isPickup = activeShipping.indexOf('local_pickup') !== -1;
                    return isPickup ? (val.trim() === '' || /^09\d{8}$/.test(val.replace(/[-\s]/g, ''))) : /^09\d{8}$/.test(val.replace(/[-\s]/g, ''));
                }},
                { id: '#billing_email', errorMsg: '請輸入正確的電子郵件格式（例：customer@gmail.com）', validate: val => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val) },
                { id: '#billing_address_1', errorMsg: '請輸入詳細收件路街、樓層與門牌號碼', validate: val => {
                    var activeShipping = $('input[name^="shipping_method"]:checked').val() || '';
                    var isCvsOrPickup = activeShipping.indexOf('Wooecpay_Logistic_CVS_711') !== -1 || activeShipping.indexOf('local_pickup') !== -1;
                    return isCvsOrPickup || val.trim().length >= 5;
                }}
            ];
            
            fields.forEach(field => {
                var $input = $(field.id);
                if ($input.length === 0) return;
                
                var errorId = field.id.replace('#', '') + '_error';
                if ($('#' + errorId).length === 0) {
                    $input.after(`<div id="${errorId}" class="chao-inline-error" style="color: #ea4335; font-size: 12px; margin-top: 4px; display: none;">${field.errorMsg}</div>`);
                }
                
                $input.off('blur input.validation').on('blur input.validation', function() {
                    var val = $(this).val();
                    var isValid = field.validate(val);
                    var $errorDiv = $('#' + errorId);
                    
                    if (!isValid) {
                        $(this).css('border-color', '#ea4335');
                        $errorDiv.show();
                    } else {
                        $(this).css('border-color', '');
                        $errorDiv.hide();
                    }
                });
            });
        }
        
        // Initial setup and hooks
        initCustomCheckout();
        $(document.body).on('updated_checkout init_checkout', function() {
            initCustomCheckout();
            syncUIStates();
        });
    });
    </script>
    <?php
}
