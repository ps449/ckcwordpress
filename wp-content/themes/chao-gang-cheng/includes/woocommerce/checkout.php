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
    
    /* TWQR Logo Styling */
    .chao-twqr-logo {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #103254 0%, #1a5b8c 100%);
        color: #ffffff;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        font-weight: 900;
        font-size: 11px;
        letter-spacing: 0.5px;
        padding: 4px 7px;
        border-radius: 4px;
        margin-right: 12px;
        flex-shrink: 0;
        line-height: 1;
        border: 1px solid #204d74;
    }

    /* Apple Pay Logo Styling */
    .chao-applepay-logo {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #000000;
        color: #ffffff;
        padding: 4px 8px;
        border-radius: 4px;
        margin-right: 12px;
        flex-shrink: 0;
        height: 24px;
        box-sizing: border-box;
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
    /* 目前收件地址／購物車商品組合下，這個配送方式沒有對應的運費資料
       （例如商品沒有勾選「離島」運送類別、但收件地址是離島，宅配就會
       整個不可用）。用灰階＋不可點游標明確表示「這個選項現在不能選」，
       並用 title 顯示原因，取代原本點了完全沒反應、看起來像當機的狀況。 */
    .chao-card.chao-shipping-card.chao-card-disabled {
        opacity: 0.45;
        cursor: not-allowed;
        background: #f2ece0;
    }
    .chao-card.chao-shipping-card.chao-card-disabled:hover {
        border-color: #e2d2b3;
        transform: none;
        box-shadow: none;
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
                        <!-- 1. ATM 轉帳 -->
                        <div class="chao-card chao-payment-card" data-payment="atm">
                            <div class="chao-card-check"></div>
                            <svg class="chao-payment-icon" viewBox="0 0 36 24" width="36" height="24" fill="none" stroke="#1a140f" stroke-width="1.5">
                                <rect x="3" y="2" width="30" height="15" rx="2" />
                                <text x="18" y="11" font-family="sans-serif" font-weight="900" font-size="7" fill="#1a140f" text-anchor="middle" stroke="none">ATM</text>
                                <path d="M10,17 L6,22 L30,22 L26,17 Z" />
                            </svg>
                            <div class="chao-payment-info">
                                <span class="chao-payment-title">ATM 轉帳</span>
                                <span class="chao-payment-desc">虛擬帳號轉帳：支援各家銀行 ATM / 網路銀行轉帳</span>
                            </div>
                        </div>

                        <!-- 2. 超商代碼繳費 -->
                        <div class="chao-card chao-payment-card" data-payment="cvscode">
                            <div class="chao-card-check"></div>
                            <svg class="chao-payment-icon" viewBox="0 0 36 24" width="36" height="24" fill="none" stroke="#1a140f" stroke-width="1.5">
                                <rect x="4" y="2" width="28" height="20" rx="2" />
                                <path d="M8 6h20M8 10h20M8 14h12M8 18h16" />
                            </svg>
                            <div class="chao-payment-info">
                                <span class="chao-payment-title">超商代碼繳費</span>
                                <span class="chao-payment-desc">至超商多媒體機台列印繳費單</span>
                            </div>
                        </div>

                        <!-- 3. TWQR 行動支付 -->
                        <div class="chao-card chao-payment-card" data-payment="twqr">
                            <div class="chao-card-check"></div>
                            <div class="chao-twqr-logo">
                                <span>TWQR</span>
                            </div>
                            <div class="chao-payment-info">
                                <span class="chao-payment-title">TWQR 行動支付</span>
                                <span class="chao-payment-desc">支援台灣 Pay、歐付寶及各家銀行 App 掃碼付款</span>
                            </div>
                        </div>

                        <!-- 4. Apple Pay -->
                        <div class="chao-card chao-payment-card" data-payment="applepay">
                            <div class="chao-card-check"></div>
                            <div class="chao-applepay-logo">
                                <svg viewBox="0 0 42 18" width="38" height="16" fill="#ffffff">
                                    <path d="M7.74 7.63c-.48.58-1.28.98-2.02.94-.1-.78.27-1.6.71-2.12.48-.59 1.34-.97 2.03-.97.09.77-.25 1.58-.72 2.15zm.74 1.1c-1.12-.07-2.07.65-2.61.65-.54 0-1.37-.6-2.27-.58-1.17.02-2.25.68-2.85 1.73-1.22 2.11-.31 5.24.87 6.94.58.83 1.26 1.76 2.16 1.73.87-.04 1.2-.56 2.25-.56 1.05 0 1.35.56 2.26.54.93-.02 1.52-.84 2.09-1.67.66-.96.93-1.89.95-1.94-.02-.01-1.83-.7-1.85-2.77-.02-1.73 1.41-2.56 1.48-2.6-.81-1.18-2.06-1.31-2.48-1.34zM16.64 16.5h-1.63V7.27h3.76c2.27 0 3.73 1.38 3.73 3.49 0 2.14-1.48 3.51-3.77 3.51h-2.09v2.23zm0-3.64h1.99c1.38 0 2.21-.73 2.21-2.09 0-1.34-.83-2.07-2.21-2.07h-1.99v4.16zm9.32 3.64c-.21-.49-.3-1.04-.3-2.07v-3.73c0-1.67 1.07-2.67 2.76-2.67 1.63 0 2.65.98 2.65 2.59v3.81c0 1.04-.09 1.58-.3 2.07h-1.42l-.12-.73c-.5.58-1.2.85-2.02.85-.92 0-1.74-.43-2.13-1.19l-.12.07zm3.68-2.25v-2.31c0-.98-.54-1.52-1.46-1.52-.91 0-1.46.54-1.46 1.52v2.31c0 .98.55 1.52 1.46 1.52.92 0 1.46-.54 1.46-1.52zm5.72 4.95l1.64-5.32-2.52-6.61h1.72l1.67 4.79 1.63-4.79h1.69l-3.99 10.42h-1.84z"/>
                                </svg>
                            </div>
                            <div class="chao-payment-info">
                                <span class="chao-payment-title">Apple Pay</span>
                                <span class="chao-payment-desc">使用 Apple Pay 快速安全結帳（支援 iPhone、iPad、Mac）</span>
                            </div>
                        </div>

                        <!-- 超商取貨付款 (僅超商取貨時顯示) -->
                        <div class="chao-card chao-payment-card" data-payment="cod" style="display: none;">
                            <div class="chao-card-check"></div>
                            <svg class="chao-payment-icon" viewBox="0 0 36 24" width="36" height="24" fill="none" stroke="#1a140f" stroke-width="1.5">
                                <rect x="3" y="4" width="30" height="16" rx="2" />
                                <circle cx="18" cy="12" r="3" />
                                <path d="M7 12h3M26 12h3" />
                            </svg>
                            <div class="chao-payment-info">
                                <span class="chao-payment-title">超商取貨付款</span>
                                <span class="chao-payment-desc">貨到 7-11 超商再付款</span>
                            </div>
                        </div>

                        <!-- 5. 信用卡安全支付 (含直接連附於選項下方的支付窗口) -->
                        <div class="chao-payment-credit-wrapper" style="grid-column: 1 / -1; display: flex; flex-direction: column; width: 100%;">
                            <div class="chao-card chao-payment-card" data-payment="credit" style="width: 100%; box-sizing: border-box;">
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
                            <div id="chao-credit-card-form-area" style="margin-top: 8px; width: 100%; box-sizing: border-box;"></div>
                        </div>
                    </div>
                    <input type="hidden" name="chao_chosen_payment_method" id="chao_chosen_payment_method" value="atm">
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

            // 依目前收件地址／購物車商品實際算出來的可選運送方式（後端
            // chao_gang_cheng_restrict_rates_by_shipping_class() 依商品「適用
            // 運送類別」交集出來的結果），把沒有對應方式的卡片標成不可點選。
            // 修正：先前三張卡片永遠都是可點的樣子，但如果收件地址是離島、
            // 而購物車裡的商品沒有勾選「離島」，後端會把宅配整組方式都拿掉，
            // 這時點「宅配」卡片其實找不到對應的 radio 可以勾，畫面上完全
            // 沒有任何反應，客人會以為當機、卡住。
            var hasDeliveryRate = $('input[name^="shipping_method"][value^="free_shipping"], input[name^="shipping_method"][value^="flat_rate"], input[name^="shipping_method"][value^="Wooecpay_Logistic_Home_Tcat"]').length > 0;
            var hasCvsRate      = $('input[name^="shipping_method"][value^="Wooecpay_Logistic_CVS_711"]').length > 0;
            var hasPickupRate   = $('input[name^="shipping_method"][value^="local_pickup"]').length > 0;

            function chaoSetShippingCardAvailability(method, available, unavailableHint) {
                $('.chao-shipping-card[data-method="' + method + '"]')
                    .toggleClass('chao-card-disabled', !available)
                    .attr('title', available ? '' : unavailableHint);
            }
            chaoSetShippingCardAvailability('delivery', hasDeliveryRate, '此收件地址目前不提供宅配，請改選其他配送方式');
            chaoSetShippingCardAvailability('cvs', hasCvsRate, '此收件地址目前不提供超商取貨，請改選其他配送方式');
            chaoSetShippingCardAvailability('pickup', hasPickupRate, '目前不提供門市自取，請改選其他配送方式');

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
                var fullLabel = $cvsRadio.closest('li').find('label').text().trim();
                // WooCommerce renders cost in the <label> adjacent to the radio, inside a .woocommerce-Price-amount span
                var $cvsLabel = $cvsRadio.closest('li').find('.woocommerce-Price-amount');
                var cvsCostText = $cvsLabel.length ? $cvsLabel.text().trim() : '';
                if (cvsCostText) {
                    $('#chao-cvs-rate-price').text(cvsCostText);
                } else {
                    // Fallback: read full label text and strip the method name prefix
                    // label typically looks like: "7-11 超商冷凍取貨：NT$280.00"
                    var colonIdx = fullLabel.lastIndexOf('：');
                    if (colonIdx === -1) colonIdx = fullLabel.lastIndexOf(':');
                    if (colonIdx !== -1) {
                        $('#chao-cvs-rate-price').text(fullLabel.substring(colonIdx + 1).trim());
                    } else if (/free|免費/i.test(fullLabel)) {
                        // 已達免運資格時，WooCommerce 標籤有時會加註「(Free)／（免費）」文字
                        $('#chao-cvs-rate-price').text('免運');
                    } else {
                        // 已達免運資格（滿額或套用免運優惠券）時，此站台實測 cost=0 且無稅額，
                        // WooCommerce 完全不會輸出任何金額片段（沒有 .woocommerce-Price-amount，
                        // 也沒有冒號可解析），改直接顯示「免運」，避免卡片停留在舊金額不更新。
                        $('#chao-cvs-rate-price').text('免運');
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
            // 2026-08 修正：原本用 .hide()/.show() 讓欄位瞬間消失/出現，導致下方
            // 欄位（詳細地址、郵遞區號等）整段位置瞬間跳動，使用者若在跳動當下
            // 點擊，容易點空或點錯到旁邊欄位。改用 .slideUp()/.slideDown() 讓
            // 欄位「原地展開/收合」，並先判斷目前是否已是目標狀態，避免這支
            // 函式被頻繁呼叫（例如每次 updated_checkout）時重複疊加動畫。
            var isCvsOrPickup = activeShipping.indexOf('Wooecpay_Logistic_CVS_711') !== -1 || activeShipping.indexOf('local_pickup') !== -1;
            var $chaoAddressFields = $('#billing_state_field, #billing_city_field, #billing_address_1_field, #billing_postcode_field, #shipping_state_field, #shipping_city_field, #shipping_address_1_field, #shipping_postcode_field, .woocommerce-shipping-fields');
            if (isCvsOrPickup) {
                $chaoAddressFields.each(function() {
                    var $f = $(this);
                    if ($f.is(':visible')) {
                        $f.stop(true, true).slideUp(200);
                    }
                });
            } else {
                $chaoAddressFields.each(function() {
                    var $f = $(this);
                    if (!$f.is(':visible')) {
                        $f.stop(true, true).slideDown(200);
                    }
                });
            }
            
            // --- SHIPPING & PAYMENT BINDINGS ---
            var isCvs = activeShipping.indexOf('Wooecpay_Logistic_CVS_711') !== -1;
            var $codCard = $('.chao-payment-card[data-payment="cod"]');
            if (isCvs) {
                $codCard.show();
            } else {
                $codCard.hide();
                // If COD was chosen but shipping is no longer CVS, revert payment to ATM
                if ($('#chao_chosen_payment_method').val() === 'cod') {
                    $('#chao_chosen_payment_method').val('atm');
                    var $atmRadio = $('input[name="payment_method"]').filter(function() {
                        var val = ($(this).val() || '').toLowerCase();
                        return (val.indexOf('atm') !== -1 || val.indexOf('vaccount') !== -1) && val.indexOf('webatm') === -1;
                    });
                    if ($atmRadio.length > 0) {
                        $atmRadio.first().prop('checked', true).trigger('click');
                    }
                }
            }

            // Detect active payment from checked radio if not set
            var checkedRadio = $('input[name="payment_method"]:checked').val() || '';
            var chosenPayment = $('#chao_chosen_payment_method').val();
            if (!chosenPayment && checkedRadio) {
                if (checkedRadio === 'chao_ecpay_ecpg') {
                    chosenPayment = 'credit';
                } else if (checkedRadio.toLowerCase().indexOf('atm') !== -1 || checkedRadio.toLowerCase().indexOf('vaccount') !== -1) {
                    chosenPayment = 'atm';
                } else if (checkedRadio.toLowerCase().indexOf('cvs') !== -1) {
                    chosenPayment = 'cvscode';
                } else if (checkedRadio.toLowerCase().indexOf('twqr') !== -1) {
                    chosenPayment = 'twqr';
                } else if (checkedRadio.toLowerCase().indexOf('apple') !== -1) {
                    chosenPayment = 'applepay';
                } else if (checkedRadio === 'cod') {
                    chosenPayment = 'cod';
                }
            }
            if (!chosenPayment) {
                chosenPayment = 'atm';
            }
            $('#chao_chosen_payment_method').val(chosenPayment);

            // Sync payment card active class across all cards
            $('.chao-payment-card').removeClass('active');
            $('.chao-payment-card[data-payment="' + chosenPayment + '"]').addClass('active');

            // 平滑展開或收合信用卡輸入區（直接位於信用卡選項正下方）
            var $creditCardArea = $('#chao-credit-card-form-area, #ECPayPayment-container');
            if (chosenPayment === 'credit') {
                $creditCardArea.stop(true, true).slideDown(250);
            } else {
                $creditCardArea.stop(true, true).slideUp(250);
            }

            // Trigger checkout helpers
            updateSubmitButtonText();
            initTrustSeals();
            initCollapsibleOrderSummary();
            addInlineValidation();
        }
        
        // Handle custom card click events
        $(document.body).on('click', '.chao-shipping-card', function() {
            // 這個配送方式在目前收件地址／購物車商品組合下沒有對應的運費資料
            // （見 syncUIStates() 的 chaoSetShippingCardAvailability()），
            // 直接擋掉點擊，避免使用者以為系統卡住——卡片本身也會顯示灰階
            // 樣式跟滑鼠移上去的提示文字說明原因。
            if ($(this).hasClass('chao-card-disabled')) {
                return;
            }
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
                $(document.body).trigger('update_chao_ecpg');
            } else if (payment === 'cod') {
                // 超商取貨付款對應到 WooCommerce 原生 cod
                $('input[name="payment_method"][value="cod"]').prop('checked', true).trigger('click');
            } else if (payment === 'applepay') {
                // 優先對應官方 Apple Pay 閘道，或使用綠界站內付 2.0
                var $appleRadio = $('input[name="payment_method"]').filter(function() {
                    var val = ($(this).val() || '').toLowerCase();
                    return val.indexOf('apple') !== -1;
                });
                if ($appleRadio.length > 0) {
                    $appleRadio.first().prop('checked', true).trigger('click');
                } else {
                    $('input[name="payment_method"][value="chao_ecpay_ecpg"]').prop('checked', true).trigger('click');
                }
            } else if (payment === 'twqr') {
                // TWQR 行動支付（台灣 Pay / 歐付寶 / 支援 TWQR 之銀行 App 掃碼）
                var $twqrRadio = $('input[name="payment_method"]').filter(function() {
                    var val = ($(this).val() || '').toLowerCase();
                    return val.indexOf('twqr') !== -1;
                });
                if ($twqrRadio.length > 0) {
                    $twqrRadio.first().prop('checked', true).trigger('click');
                } else {
                    var $directTwqr = $('input[name="payment_method"][value="Wooecpay_Gateway_Twqr"]');
                    if ($directTwqr.length > 0) {
                        $directTwqr.prop('checked', true).trigger('click');
                    } else {
                        $('input[name="payment_method"][value="chao_ecpay_ecpg"]').prop('checked', true).trigger('click');
                    }
                }
            } else if (payment === 'atm') {
                // 嚴格鎖定「綠界ATM虛擬帳號」（支援手機/網銀/實體機台轉帳，絕不使用需讀卡機的 WebATM）
                var $atmRadio = $('input[name="payment_method"]').filter(function() {
                    var val = ($(this).val() || '').toLowerCase();
                    return (val.indexOf('atm') !== -1 || val.indexOf('vaccount') !== -1) && val.indexOf('webatm') === -1;
                });

                if ($atmRadio.length > 0) {
                    $atmRadio.first().prop('checked', true).trigger('click');
                } else {
                    var $directAtm = $('input[name="payment_method"][value="Wooecpay_Gateway_Atm"], input[name="payment_method"][value="Wooecpay_Gateway_ATM"], input[name="payment_method"][value="wooecpay_gateway_atm"]');
                    if ($directAtm.length > 0) {
                        $directAtm.first().prop('checked', true).trigger('click');
                    } else {
                        // 絕不選取 Webatm（網路ATM），若無則安全回退
                        $('input[name="payment_method"][value="chao_ecpay_ecpg"]').prop('checked', true).trigger('click');
                    }
                }
            } else if (payment === 'cvscode') {
                // 優先鎖定超商代碼 Wooecpay_Gateway_Cvs
                var $cvsRadio = $('input[name="payment_method"]').filter(function() {
                    var val = ($(this).val() || '').toLowerCase();
                    return val.indexOf('cvs') !== -1;
                });

                if ($cvsRadio.length > 0) {
                    $cvsRadio.first().prop('checked', true).trigger('click');
                } else {
                    var $directCvs = $('input[name="payment_method"][value="Wooecpay_Gateway_Cvs"], input[name="payment_method"][value="Wooecpay_Gateway_CVS"]');
                    if ($directCvs.length > 0) {
                        $directCvs.first().prop('checked', true).trigger('click');
                    } else {
                        $('input[name="payment_method"][value="chao_ecpay_ecpg"]').prop('checked', true).trigger('click');
                    }
                }
            } else {
                // 無法辨識的付款方式，安全回退到信用卡，避免誤送到錯誤的閘道
                $('input[name="payment_method"][value="chao_ecpay_ecpg"]').prop('checked', true).trigger('click');
            }
            
            syncUIStates();
            $(document.body).trigger('update_chao_ecpg');
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
                <div id="chao-collapsible-summary-total" style="color: #1e40af;">${totalText}</div>
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
        
        // 依 WooCommerce AJAX 重新計算後的結果，同步更新摺疊式訂單明細
        // 標題旁邊顯示的金額，避免購物車/運送方式/地址變動後，這裡的
        // 金額卡在初次渲染當下的舊數字，跟下面 order_review 裡的真實
        // 總計脫節（2026-08 修正）。
        function updateCollapsibleOrderSummaryTotal() {
            var totalText = $('.order-total .amount').first().text() || $('.order-total td').first().text() || '';
            if (totalText) {
                $('#chao-collapsible-summary-total').text(totalText);
            }
        }

        // Initial setup and hooks
        initCustomCheckout();
        $(document.body).on('updated_checkout init_checkout', function() {
            initCustomCheckout();
            syncUIStates();
            updateCollapsibleOrderSummaryTotal();
        });
    });
    </script>
    <?php
}

/**
 * 美化 Thank You 頁面的 ATM / CVS 付款指示資訊卡
 */
add_action('woocommerce_thankyou', 'chao_render_atm_payment_instructions', 15, 1);
function chao_render_atm_payment_instructions($order_id) {
    if (!$order_id) return;
    $order = wc_get_order($order_id);
    if (!$order) return;

    $payment_method = $order->get_payment_method();
    
    // 檢查是否為 ATM 虛擬帳號或超商代碼付款
    $is_atm = (strpos($payment_method, 'Atm') !== false || strpos($payment_method, 'atm') !== false);
    $is_cvs = (strpos($payment_method, 'Cvs') !== false || strpos($payment_method, 'cvs') !== false || strpos($payment_method, 'Barcode') !== false);

    if (!$is_atm && !$is_cvs) return;
    if ($order->is_paid()) return;

    $bank_code   = $order->get_meta('_wooecpay_atm_bank_code') ?: $order->get_meta('_chao_atm_bank_code');
    $v_account   = $order->get_meta('_wooecpay_atm_v_account') ?: $order->get_meta('_chao_atm_v_account');
    $expire_date = $order->get_meta('_wooecpay_atm_expire_date') ?: $order->get_meta('_chao_atm_expire_date');

    // 超商繳費代碼
    $payment_no  = $order->get_meta('_wooecpay_cvs_payment_no') ?: $order->get_meta('_chao_cvs_payment_no');

    if (empty($v_account) && empty($payment_no)) {
        return;
    }

    ?>
    <div class="chao-atm-payment-box" style="background: #fdfbf7; border: 2px solid #8b6237; border-radius: 8px; padding: 20px; margin: 20px 0; font-family: inherit;">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
            <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="#8b6237" stroke-width="2">
                <rect x="2" y="5" width="20" height="14" rx="2"></rect>
                <line x1="2" y1="10" x2="22" y2="10"></line>
            </svg>
            <h3 style="margin: 0; color: #8b6237; font-size: 18px; font-weight: 700;">
                <?php echo $is_atm ? 'ATM 虛擬帳號轉帳繳費資訊' : '超商代碼繳費資訊'; ?>
            </h3>
        </div>
        
        <?php if (!empty($v_account)) : ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; background: #fff; padding: 15px; border-radius: 6px; border: 1px solid #ebd9c8;">
                <?php if (!empty($bank_code)) : ?>
                <div>
                    <span style="font-size: 12px; color: #7f6855; display: block;">銀行代碼</span>
                    <strong style="font-size: 16px; color: #2c2520;"><?php echo esc_html($bank_code); ?></strong>
                </div>
                <?php endif; ?>
                <div>
                    <span style="font-size: 12px; color: #7f6855; display: block;">轉帳虛擬帳號</span>
                    <div style="display: flex; align-items: center; gap: 8px; margin-top: 2px;">
                        <strong id="chao-vaccount-val" style="font-size: 18px; color: #c0392b; letter-spacing: 1px;"><?php echo esc_html($v_account); ?></strong>
                        <button type="button" onclick="navigator.clipboard.writeText('<?php echo esc_js($v_account); ?>'); this.innerText='已複製！'; setTimeout(() => this.innerText='複製', 2000);" style="padding: 2px 8px; font-size: 12px; background: #8b6237; color: #fff; border: none; border-radius: 4px; cursor: pointer;">複製</button>
                    </div>
                </div>
                <div>
                    <span style="font-size: 12px; color: #7f6855; display: block;">應繳金額</span>
                    <strong style="font-size: 16px; color: #2c2520;">NT$ <?php echo esc_html(number_format($order->get_total())); ?></strong>
                </div>
                <?php if (!empty($expire_date)) : ?>
                <div>
                    <span style="font-size: 12px; color: #7f6855; display: block;">繳費截止期限</span>
                    <strong style="font-size: 14px; color: #d35400;"><?php echo esc_html($expire_date); ?></strong>
                </div>
                <?php endif; ?>
            </div>
            <p style="font-size: 13px; color: #7f6855; margin: 12px 0 0 0; line-height: 1.5;">
                💡 請於繳費截止期限前，透過各大銀行 ATM 實體機台、網路銀行或行動銀行 App 完成轉帳。轉帳完成後系統將自動對帳完成訂單。
            </p>
        <?php elseif (!empty($payment_no)) : ?>
            <div style="background: #fff; padding: 15px; border-radius: 6px; border: 1px solid #ebd9c8;">
                <span style="font-size: 12px; color: #7f6855; display: block;">超商繳費代碼</span>
                <strong style="font-size: 20px; color: #c0392b; letter-spacing: 1px;"><?php echo esc_html($payment_no); ?></strong>
                <?php if (!empty($expire_date)) : ?>
                <div style="margin-top: 8px; font-size: 13px; color: #d35400;">
                    繳費截止期限：<?php echo esc_html($expire_date); ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
