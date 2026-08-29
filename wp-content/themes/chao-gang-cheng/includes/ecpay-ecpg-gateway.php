<?php
/**
 * WooCommerce ECPay ECPg 2.0 (站內付 2.0) Payment Gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'chao_ecpg_init_gateway', 11);

function chao_ecpg_init_gateway() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    // Ensure ECPay SDK classes (Ecpay\Sdk\*) are loaded.
    // Prefer the official ECPay WooCommerce plugin's vendor autoloader if that
    // plugin is installed; otherwise fall back to the copy of the SDK bundled
    // with this theme so token requests don't fail with a missing class error.
    if (!class_exists('\\Ecpay\\Sdk\\Factories\\Factory')) {
        $ecpay_plugin_autoload = WP_PLUGIN_DIR . '/ecpay-ecommerce-for-woocommerce/vendor/autoload.php';
        $ecpay_bundled_autoload = get_theme_file_path('includes/ecpay-sdk/autoload.php');

        if (file_exists($ecpay_plugin_autoload)) {
            require_once $ecpay_plugin_autoload;
        } elseif (file_exists($ecpay_bundled_autoload)) {
            require_once $ecpay_bundled_autoload;
        }
    }

    class WC_Gateway_Chao_ECPay_ECPg extends WC_Payment_Gateway {
        public $test_mode;
        public $merchant_id;
        public $hash_key;
        public $hash_iv;

        public function __construct() {
            $this->id = 'chao_ecpay_ecpg';
            $this->icon = '';
            $this->has_fields = true;
            $this->method_title = '綠界站內付 2.0 (信用卡安全支付)';
            $this->method_description = '提供嵌入式信用卡卡號輸入（站內付 2.0 無跳轉安全交易）';
            $this->supports = ['products', 'refunds'];

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title', '綠界站內付 2.0');
            $this->description = $this->get_option('description', '直接在結帳頁面安全填寫信用卡資訊完成付款');

            // 讀取官方金流設定的特店 ID
            $mid = get_option('wooecpay_payment_mid');

            // 如果官方設定為測試模式，或是還沒有填寫正式特店 ID（仍為預設測試 ID 3312200 或空值），則強制作為測試模式運作
            $this->test_mode = (get_option('wooecpay_enabled_payment_stage', 'no') === 'yes') || empty($mid);

            if ($this->test_mode) {
                // 綠界站內付 2.0 預設測試金鑰
                $this->merchant_id = '3002607';
                $this->hash_key = 'pwFHCqoQZGmho4w6';
                $this->hash_iv = 'EkRm7iFT261dpevs';
            } else {
                // 正式金鑰繼承官方外掛設定
                $this->merchant_id = $mid;
                $this->hash_key = get_option('wooecpay_payment_hashkey');
                $this->hash_iv = get_option('wooecpay_payment_hashiv');
            }

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
            add_action('wp_enqueue_scripts', [$this, 'enqueue_checkout_scripts']);


        }

        public function init_form_fields() {
            $this->form_fields = [
                'enabled' => [
                    'title' => '啟用/停用',
                    'type' => 'checkbox',
                    'label' => '啟用綠界站內付 2.0',
                    'default' => 'no',
                ],
                'title' => [
                    'title' => '標題',
                    'type' => 'text',
                    'description' => '消費者在結帳頁看到的付款方式標題',
                    'default' => '信用卡安全支付 (站內付 2.0)',
                    'desc_tip' => true,
                ],
                'description' => [
                    'title' => '描述',
                    'type' => 'textarea',
                    'description' => '消費者在結帳頁看到的付款描述',
                    'default' => '直接在結帳頁面安全填寫信用卡資訊完成付款',
                ],
            ];
        }

        public function enqueue_checkout_scripts() {
            if (!is_checkout() || $this->enabled === 'no') {
                return;
            }

            // 載入 node-forge（SDK 必要依賴）
            wp_enqueue_script('node-forge', 'https://cdn.jsdelivr.net/npm/node-forge@0.7.0/dist/forge.min.js', [], '0.7.0', true);

            // 依環境載入綠界 ECPg JS SDK
            $sdk_url = $this->test_mode 
                ? 'https://ecpg-stage.ecpay.com.tw/Scripts/sdk-1.0.0.js?t=20210121100116' 
                : 'https://ecpg.ecpay.com.tw/Scripts/sdk-1.0.0.js?t=20210121100116';

            wp_enqueue_script('ecpay-ecpg-sdk', $sdk_url, ['jquery', 'node-forge'], '1.0.0', true);

            $ajax_url = admin_url('admin-ajax.php');
            $stage_param = $this->test_mode ? 'Stage' : 'Prod';

            wp_add_inline_script('ecpay-ecpg-sdk', "
                window.$ = window.$ || window.jQuery;
                window.ChaoECPg = window.ChaoECPg || {
                    ajaxUrl: '{$ajax_url}',
                    stage: '{$stage_param}',
                    token: null,
                    payToken: null,
                    isInitialized: false
                };
                var ChaoECPg = window.ChaoECPg;

                jQuery(document).ready(function($) {
                    function ensureECPayInitialized(callback) {
                        if (ChaoECPg.isInitialized) {
                            callback(true);
                            return;
                        }
                        if (typeof ECPay !== 'undefined') {
                            ECPay.initialize(ChaoECPg.stage, 1, function(errMsg) {
                                if (errMsg != null) {
                                    console.error('ECPay SDK Init Error:', errMsg);
                                    callback(false, errMsg);
                                } else {
                                    ChaoECPg.isInitialized = true;
                                    callback(true);
                                }
                            });
                        } else {
                            var elapsed = 0;
                            var timer = setInterval(function() {
                                elapsed += 100;
                                if (typeof ECPay !== 'undefined') {
                                    clearInterval(timer);
                                    ensureECPayInitialized(callback);
                                } else if (elapsed >= 10000) {
                                    clearInterval(timer);
                                    callback(false, '金流 SDK 載入逾時');
                                }
                            }, 100);
                        }
                    }

                    // 顯示可行動的錯誤訊息＋「重試」按鈕
                    function showEcpayError(msg) {
                        var el = $('#ecpay-loading');
                        el.empty().show();
                        $('<div/>').css({ color: '#c0392b', 'line-height': '1.6' }).text(msg).appendTo(el);
                        $('<button/>', { type: 'button', id: 'ecpay-retry-btn', text: '重新載入付款模組' })
                            .css({ 'margin-top': '10px', padding: '8px 20px', border: '1px solid #c0392b', background: '#fff', color: '#c0392b', 'border-radius': '4px', cursor: 'pointer' })
                            .appendTo(el);
                    }

                    $(document).on('click', '#ecpay-retry-btn', function() {
                        $('#ECPayPayment').empty().data('token-failed', false).data('token-fetching', false);
                        $('#ecpay-loading').text('載入安全金流模組中，請稍候...');
                        initOrUpdateECPayDebounced();
                    });

                    function initOrUpdateECPay() {
                        var chosenVal = $('#chao_chosen_payment_method').val();
                        var isCreditSelected = chosenVal ? (chosenVal === 'credit') : ($('input[name=\"payment_method\"]:checked').val() === 'chao_ecpay_ecpg');

                        var mainContainer = $('#ECPayPayment-container');
                        if (mainContainer.length === 0) {
                            var containerHtml = '<div id=\"ECPayPayment-container\" style=\"display: none; border: 1.5px solid #d4af37; padding: 22px 24px; border-radius: 12px; background: linear-gradient(180deg, #fffdfa 0%, #fff9f0 100%); margin-top: 16px; box-shadow: 0 4px 20px rgba(184, 134, 11, 0.08);\"><div style=\"display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #ebd9c8;\"><div style=\"font-weight: 700; font-size: 15px; color: #1a140f; display: flex; align-items: center; gap: 8px;\"><svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"#b8860b\" stroke-width=\"2\"><rect x=\"2\" y=\"5\" width=\"20\" height=\"14\" rx=\"2\"/><line x1=\"2\" y1=\"10\" x2=\"22\" y2=\"10\"/></svg>信用卡安全支付</div><div style=\"font-size: 12px; color: #8b6237; display: flex; align-items: center; gap: 4px;\"><svg width=\"13\" height=\"13\" viewBox=\"0 0 24 24\" fill=\"currentColor\"><path d=\"M12 2C9.24 2 7 4.24 7 7v3H6c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2v-8c0-1.1-.9-2-2-2h-1V7c0-2.76-2.24-5-5-5zm3 8H9V7c0-1.66 1.34-3 3-3s3 1.34 3 3v3z\"/></svg>256-bit SSL 加密保護</div></div><div id=\"ecpay-loading\" style=\"text-align: center; color: #8b6237; font-size: 14px; padding: 20px;\"><div style=\"width: 28px; height: 28px; border: 3px solid #f0e6d6; border-top-color: #b8860b; border-radius: 50%; animation: chao-spin 1s linear infinite; margin: 0 auto 10px;\"></div>載入安全信用卡支付元件中，請稍候...</div><div id=\"ECPayPayment\"></div></div>';
                            if ($('#chao-credit-card-form-area').length > 0) {
                                $('#chao-credit-card-form-area').html(containerHtml);
                            } else if ($('#chao-payment-section').length > 0) {
                                $('#chao-payment-section').append(containerHtml);
                            }
                            mainContainer = $('#ECPayPayment-container');
                        } else {
                            if ($('#chao-credit-card-form-area').length > 0 && !mainContainer.parent().is('#chao-credit-card-form-area')) {
                                mainContainer.appendTo('#chao-credit-card-form-area');
                            } else if ($('#chao-payment-section').length > 0 && !mainContainer.parent().is('#chao-payment-section') && $('#chao-credit-card-form-area').length === 0) {
                                mainContainer.appendTo('#chao-payment-section');
                            }
                        }

                        if (!isCreditSelected) {
                            mainContainer.stop(true, true).slideUp(250);
                            return;
                        }

                        mainContainer.stop(true, true).slideDown(250);

                        var container = $('#ECPayPayment');
                        if (container.length === 0) {
                            return;
                        }

                        // 如果已經有金流介面渲染在裡面，確保 loading 隱藏並不重複發起請求
                        if (container.children().length > 0 || container.find('iframe, .ecpay-pay-list-wrap').length > 0) {
                            $('#ecpay-loading').hide();
                            return;
                        }

                        if (container.data('token-fetching')) {
                            return;
                        }

                        container.data('token-fetching', true);
                        container.data('token-failed', false);
                        $('#ecpay-loading').show().text('載入安全金流模組中，請稍候...');

                        // 取得交易 Token
                        $.ajax({
                            url: ChaoECPg.ajaxUrl,
                            type: 'POST',
                            dataType: 'json',
                            data: {
                                action: 'chao_ecpg_get_token'
                            },
                            success: function(response) {
                                var currentContainer = $('#ECPayPayment');
                                if (currentContainer.length > 0) {
                                    currentContainer.data('token-fetching', false);
                                }

                                if (response.success && response.token) {
                                    ChaoECPg.token = response.token;
                                    renderECPay(response.token);
                                } else {
                                    if (currentContainer.length > 0) {
                                        currentContainer.data('token-failed', true);
                                    }
                                    showEcpayError(response.message || '無法載入信用卡付款模組。請點擊下方按鈕重試，或改用其他付款方式。');
                                }
                            },
                            error: function() {
                                var currentContainer = $('#ECPayPayment');
                                if (currentContainer.length > 0) {
                                    currentContainer.data('token-fetching', false);
                                    currentContainer.data('token-failed', true);
                                }
                                showEcpayError('網路連線不穩定，無法載入付款模組。請確認網路狀態後點擊下方按鈕重試。');
                            }
                        });
                    }

                    function renderECPay(token) {
                        ensureECPayInitialized(function(success, errMsg) {
                            if (success) {
                                doCreatePayment(token);
                            } else {
                                var container = $('#ECPayPayment');
                                if (container.length > 0) {
                                    container.data('token-failed', true);
                                }
                                showEcpayError('信用卡付款模組初始化失敗（' + (errMsg || 'SDK 載入失敗') + '）。請點擊下方按鈕重試。');
                            }
                        });
                    }

                    function initOrUpdateECPayDebounced() {
                        clearTimeout(window.ECPayDebounceTimer);
                        window.ECPayDebounceTimer = setTimeout(initOrUpdateECPay, 80);
                    }

                    function doCreatePayment(token) {
                        var container = $('#ECPayPayment');
                        if (container.length === 0) {
                            return;
                        }
                        
                        ECPay.createPayment(token, 'zh-TW', function(errMsg) {
                            if (errMsg != null) {
                                console.error('ECPay Create UI Error:', errMsg);
                                showEcpayError('金流介面載入失敗（' + errMsg + '）。請點擊下方按鈕重試。');
                                container.data('token-failed', true);
                                return;
                            }
                            $('#ecpay-loading').hide();
                        }, 'V2');
                    }

                    var isGettingPayToken = false;

                    // 監聽 WooCommerce 結帳更新事件與自訂事件
                    $(document.body).on('updated_checkout update_chao_ecpg init_checkout', function() {
                        if (isGettingPayToken || $('form.checkout').is('.processing')) {
                            return;
                        }
                        initOrUpdateECPayDebounced();
                    });

                    $(document.body).on('change', 'input[name=\"payment_method\"]', function() {
                        initOrUpdateECPayDebounced();
                    });

                    // 頁面就緒與延遲檢查
                    initOrUpdateECPayDebounced();
                    setTimeout(initOrUpdateECPayDebounced, 300);

                    // 結帳失敗時重設狀態
                    $(document.body).on('checkout_error', function() {
                        isGettingPayToken = false;
                        $('#chao_ecpg_pay_token').val('');
                        $('form.checkout').removeClass('processing');
                        if (typeof $('form.checkout').unblock === 'function') {
                            $('form.checkout').unblock();
                        }
                        $('#chao-checkout-loader-overlay').hide();
                    });

                    // 攔截 WooCommerce 下單按鈕事件
                    $('form.checkout').on('checkout_place_order_chao_ecpay_ecpg', function() {
                        var form = $(this);
                        var tokenVal = $('#chao_ecpg_pay_token').val();
                        
                        // 1. 如果已持有有效的 PayToken，直接放行給 WooCommerce 標準 AJAX 下單流程
                        if (tokenVal && tokenVal.trim() !== '') {
                            return true;
                        }

                        // 2. 避免重複發起 Token 請求
                        if (isGettingPayToken) {
                            return false;
                        }
                        isGettingPayToken = true;

                        // 3. 鎖定表單並顯示讀取中提示遮罩
                        form.addClass('processing');
                        if (typeof form.block === 'function') {
                            form.block({
                                message: null,
                                overlayCSS: {
                                    background: '#fff',
                                    opacity: 0.6
                                }
                            });
                        }
                        $('#chao-checkout-loader-overlay').css('display', 'flex');

                        var chaoPayTokenHandled = false;
                        var chaoPayTokenTimeoutTimer = setTimeout(function() {
                            if (chaoPayTokenHandled) return;
                            chaoPayTokenHandled = true;
                            isGettingPayToken = false;
                            form.removeClass('processing');
                            if (typeof form.unblock === 'function') {
                                form.unblock();
                            }
                            $('#chao-checkout-loader-overlay').hide();
                            showEcpayError('付款請求逾時，尚未完成付款，並未重複扣款。請確認網路連線後點擊下方按鈕重新載入，或改用其他付款方式，或聯絡客服（LINE: @eshopckc）。');
                            $('#ECPayPayment').data('token-failed', true);
                        }, 15000);

                        // 4. 呼叫 WebJS SDK 取得一次性 PayToken
                        ECPay.getPayToken(function(paymentInfo, errMsg) {
                            if (chaoPayTokenHandled) return;
                            chaoPayTokenHandled = true;
                            clearTimeout(chaoPayTokenTimeoutTimer);

                            if (errMsg != null) {
                                console.error('ECPay getPayToken error:', errMsg);
                                isGettingPayToken = false;
                                form.removeClass('processing');
                                if (typeof form.unblock === 'function') {
                                    form.unblock();
                                }
                                $('#chao-checkout-loader-overlay').hide();
                                showEcpayError('信用卡資訊驗證失敗：' + errMsg + '。請確認卡號、有效期限、安全碼與持卡人姓名格式是否正確後點擊下方按鈕重試，或改用其他付款方式，或聯絡客服（LINE: @eshopckc）。');
                                $('#ECPayPayment').data('token-failed', true);
                                return false;
                            }
                            
                            var payToken = (paymentInfo && paymentInfo.PayToken) ? paymentInfo.PayToken : '';
                            if (!payToken || typeof payToken !== 'string') {
                                console.error('ECPay PayToken is empty or invalid:', paymentInfo);
                                isGettingPayToken = false;
                                form.removeClass('processing');
                                if (typeof form.unblock === 'function') {
                                    form.unblock();
                                }
                                $('#chao-checkout-loader-overlay').hide();
                                showEcpayError('取得信用卡付款憑證失敗，請重試或聯繫客服。');
                                return false;
                            }

                            // 5. 填入 PayToken 隱藏欄位
                            $('#chao_ecpg_pay_token').val(payToken);

                            // 6. 移除 processing 狀態與解鎖，讓接下來的 form.submit() 不會被 WooCommerce 核心阻擋
                            form.removeClass('processing');
                            if (typeof form.unblock === 'function') {
                                form.unblock();
                            }
                            isGettingPayToken = false;

                            // 7. 重新以標準 jQuery 提交表單（第二次進入時 tokenVal 已存在，將直接 return true 交由 WooCommerce AJAX 結帳）
                            form.submit();
                        });

                        return false;
                    });
                });
            ");
        }

        public function payment_fields() {
            if ($this->description) {
                echo wpautop(wp_kses_post($this->description));
            }
            ?>
            <style>
                #ECPayPayment iframe {
                    min-height: 360px !important;
                    width: 100% !important;
                    border: none !important;
                }
            </style>
            <div id="ECPayPayment-container" style="display: none; border: 1.5px solid #d4af37; padding: 22px 24px; border-radius: 12px; background: linear-gradient(180deg, #fffdfa 0%, #fff9f0 100%); margin-top: 16px; box-shadow: 0 4px 20px rgba(184, 134, 11, 0.08);">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #ebd9c8;">
                    <div style="font-weight: 700; font-size: 15px; color: #1a140f; display: flex; align-items: center; gap: 8px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#b8860b" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                        信用卡安全支付
                    </div>
                    <div style="font-size: 12px; color: #8b6237; display: flex; align-items: center; gap: 4px;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C9.24 2 7 4.24 7 7v3H6c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2v-8c0-1.1-.9-2-2-2h-1V7c0-2.76-2.24-5-5-5zm3 8H9V7c0-1.66 1.34-3 3-3s3 1.34 3 3v3z"/></svg>
                        256-bit SSL 加密保護
                    </div>
                </div>
                <div id="ecpay-loading" style="text-align: center; color: #8b6237; font-size: 14px; padding: 20px;">
                    <div style="width: 28px; height: 28px; border: 3px solid #f0e6d6; border-top-color: #b8860b; border-radius: 50%; animation: chao-spin 1s linear infinite; margin: 0 auto 10px;"></div>
                    載入安全信用卡支付元件中，請稍候...
                </div>
                <div id="ECPayPayment"></div>
            </div>
            <input type="hidden" name="chao_ecpg_pay_token" id="chao_ecpg_pay_token" value="" />
            <?php
        }

        public function ajax_get_token() {
            if (WC()->cart->is_empty()) {
                wp_send_json(['success' => false, 'message' => '購物車為空。']);
            }

            $total_amount = (int) ceil(WC()->cart->get_total('edit'));
            
            $item_names = [];
            foreach (WC()->cart->get_cart() as $cart_item) {
                $product = $cart_item['data'];
                $item_names[] = str_replace('#', '', $product->get_name());
            }
            $item_name = implode('#', $item_names);
            if (mb_strlen($item_name) > 200) {
                $item_name = mb_substr($item_name, 0, 190) . '...';
            }

            // 產生本次唯一交易編號並記錄於 session 中供付款比對
            $merchant_trade_no = 'ECP' . date('ymdHis') . rand(10, 99);
            WC()->session->set('chao_ecpg_trade_no', $merchant_trade_no);

            // 消費者基本資料
            $customer = WC()->customer;
            $email = $customer->get_billing_email();
            $phone = $customer->get_billing_phone();
            $name = $customer->get_billing_first_name() . ' ' . $customer->get_billing_last_name();

            // 2026-08 補上：ECPay 站內付 2.0 官方規格要求 ConsumerInfo.MerchantMemberID
            // 為必填欄位（格式：MerchantID_買家識別碼），讓綠界能在會員層級做風控
            // （例如後台已啟用的「連續盜刷保護」需要靠此欄位辨識同一會員）。
            // 此時訂單尚未建立（GetTokenbyTrade 在下單前就會呼叫），故無法用
            // order_id，改用登入會員 ID，訪客則用 WooCommerce session 客戶識別碼。
            if ( is_user_logged_in() ) {
                $chao_member_ref = 'u' . get_current_user_id();
            } else {
                $chao_member_ref = 'g' . substr( md5( (string) WC()->session->get_customer_id() ), 0, 12 );
            }
            $merchant_member_id = $this->merchant_id . '_' . $chao_member_ref;

            $api_url = $this->test_mode 
                ? 'https://ecpg-stage.ecpay.com.tw/Merchant/GetTokenbyTrade'
                : 'https://ecpg.ecpay.com.tw/Merchant/GetTokenbyTrade';

            $return_url = WC()->api_request_url('chao_ecpg_callback');
            $order_result_url = WC()->api_request_url('chao_ecpg_result');

            $payload = [
                'MerchantID' => $this->merchant_id,
                'RqHeader' => [
                    'Timestamp' => time(),
                ],
                'Data' => [
                    'MerchantID' => $this->merchant_id,
                    'RememberCard' => 0,
                    'PaymentUIType' => 2,
                    'ChoosePaymentList' => '1', // 僅顯示信用卡一次付清 (避免特店未開通 Apple Pay 時拋出授權錯誤)
                    'OrderInfo' => [
                        'MerchantTradeDate' => date('Y/m/d H:i:s'),
                        'MerchantTradeNo' => $merchant_trade_no,
                        'TotalAmount' => $total_amount,
                        'ReturnURL' => $return_url,
                        'TradeDesc' => 'WooCommerce ECPg Purchase',
                        'ItemName' => $item_name,
                    ],
                    'CardInfo' => [
                        'OrderResultURL' => $order_result_url,
                    ],
                    'ConsumerInfo' => [
                        'MerchantMemberID' => $merchant_member_id,
                        'Email' => $email ?: 'customer@example.com',
                        'Phone' => $phone ?: '0912345678',
                        'Name' => trim($name) ?: 'Customer',
                    ],
                ],
            ];

            try {
                $factory = new \Ecpay\Sdk\Factories\Factory([
                    'hashKey' => $this->hash_key,
                    'hashIv' => $this->hash_iv,
                ]);

                $postService = $factory->create('PostWithAesJsonResponseService');
                $response = $postService->post($payload, $api_url);

                // 完整記錄一次原始回應，供日後排查 Token 取得失敗時使用（不論成功或失敗都留痕）。
                error_log('[ChaoECPg] GetTokenbyTrade response: ' . wp_json_encode($response));

                if (isset($response['TransCode']) && $response['TransCode'] == 1 && isset($response['Data']['Token'])) {
                    wp_send_json([
                        'success' => true,
                        'token' => $response['Data']['Token']
                    ]);
                } else {
                    $trans_code = isset($response['TransCode']) ? $response['TransCode'] : '未知';
                    $err_msg = isset($response['TransMsg']) && $response['TransMsg'] !== '' ? $response['TransMsg'] : '';
                    if (isset($response['Data']['RtnMsg']) && $response['Data']['RtnMsg'] !== '') {
                        $err_msg = ($err_msg !== '' ? $err_msg . ': ' : '') . $response['Data']['RtnMsg'];
                    }
                    if ($err_msg === '') {
                        $err_msg = sprintf('取得付款授權憑證失敗（狀態碼：%s），請重新整理網頁並再試一次，或改用其他付款方式（如 虛擬 ATM、TWQR、超商代碼）。如有疑問，歡迎聯絡客服（LINE: @eshopckc）。', $trans_code);
                    } else {
                        $err_msg = sprintf('取得付款授權憑證失敗（%s）。請重新整理網頁，或改用其他付款方式。如有需要，請聯絡客服（LINE: @eshopckc）為您處理。', $err_msg);
                    }
                    error_log('[ChaoECPg] GetTokenbyTrade failed: TransCode=' . $trans_code . ' message=' . $err_msg);
                    wp_send_json([
                        'success' => false,
                        'message' => $err_msg
                    ]);
                }
            } catch (\Throwable $e) {
                $err_class = get_class($e);
                $err_code = method_exists($e, 'getCode') ? $e->getCode() : '';
                $err_message = $e->getMessage();

                error_log(sprintf(
                    '[ChaoECPg] GetTokenbyTrade exception: %s (code=%s) message=%s',
                    $err_class,
                    $err_code,
                    $err_message !== '' ? $err_message : '(空白訊息)'
                ));

                if ($err_message === '') {
                    // 部分例外（例如綠界回傳 TransCode 失敗但未附文字說明時拋出的 TransException）
                    // getMessage() 可能是空字串，此時仍需給消費者一個看得懂的訊息。
                    $err_message = sprintf('連線綠界科技發生異常（%s，代碼 %s），請稍後重試或聯繫客服。', $err_class, $err_code !== '' ? $err_code : '未知');
                }

                wp_send_json([
                    'success' => false,
                    'message' => $err_message
                ]);
            }
        }

        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            $pay_token = isset($_POST['chao_ecpg_pay_token']) ? sanitize_text_field($_POST['chao_ecpg_pay_token']) : '';

            if (empty($pay_token)) {
                wc_add_notice('系統未能順利取得您的信用卡卡號憑證，請確認您的信用卡號、有效期限與安全碼皆已填寫正確。您也可以稍後重試，或改用其他付款方式。如有任何疑問，請聯繫客服（電話 02-1234-5678 或 LINE 官方帳號 @eshopckc）。', 'error');
                return;
            }

            // 取得 Session 中記錄的交易編號，若遺失則使用訂單編號備援
            $merchant_trade_no = WC()->session->get('chao_ecpg_trade_no');
            if (empty($merchant_trade_no)) {
                $merchant_trade_no = 'ECP' . $order_id . date('ymdHis');
            }

            $order->update_meta_data('_chao_ecpg_trade_no', $merchant_trade_no);
            $order->save();

            $api_url = $this->test_mode 
                ? 'https://ecpg-stage.ecpay.com.tw/Merchant/CreatePayment'
                : 'https://ecpg.ecpay.com.tw/Merchant/CreatePayment';

            $payload = [
                'MerchantID' => $this->merchant_id,
                'RqHeader' => [
                    'Timestamp' => time(),
                ],
                'Data' => [
                    'MerchantID' => $this->merchant_id,
                    'PayToken' => $pay_token,
                    'MerchantTradeNo' => $merchant_trade_no,
                ],
            ];

            try {
                $factory = new \Ecpay\Sdk\Factories\Factory([
                    'hashKey' => $this->hash_key,
                    'hashIv' => $this->hash_iv,
                ]);

                $postService = $factory->create('PostWithAesJsonResponseService');
                $response = $postService->post($payload, $api_url);

                // 完整記錄交易授權的原始回應，供客服與工程排查
                error_log(sprintf('[ChaoECPg] CreatePayment response for Order %d: %s', $order_id, wp_json_encode($response)));

                if (isset($response['TransCode']) && $response['TransCode'] == 1 && isset($response['Data']['RtnCode'])) {
                    $data = $response['Data'];
                    $rtn_code = (int) $data['RtnCode'];
                    $trade_no = isset($data['OrderInfo']['TradeNo']) ? $data['OrderInfo']['TradeNo'] : '';

                    if (!empty($trade_no)) {
                        $order->set_transaction_id($trade_no);
                        $order->update_meta_data('_chao_ecpg_ecpay_trade_no', $trade_no);
                    }
                    $order->save();

                    $order->add_order_note(sprintf('綠界站內付 2.0 交易序號：%s，回傳代碼：%d，回傳訊息：%s', 
                        !empty($trade_no) ? $trade_no : '無', 
                        $rtn_code, 
                        $data['RtnMsg']
                    ));

                    // 檢查 3D Secure 驗證連結（2025/8 起大部分信用卡交易必須引導 3D 驗證）
                    if (isset($data['ThreeDInfo']['ThreeDURL']) && !empty($data['ThreeDInfo']['ThreeDURL'])) {
                        $three_d_url = $data['ThreeDInfo']['ThreeDURL'];
                        return [
                            'result' => 'success',
                            'redirect' => $three_d_url,
                        ];
                    }

                    if ($rtn_code === 1) {
                        $order->payment_complete($trade_no);
                        WC()->cart->empty_cart();
                        return [
                            'result' => 'success',
                            'redirect' => $this->get_return_url($order),
                        ];
                    } else {
                        // 授權失敗：具體說明如何修正
                        error_log(sprintf('[ChaoECPg] Credit card payment failed for Order %d: RtnCode=%d RtnMsg=%s', $order_id, $rtn_code, $data['RtnMsg']));
                        wc_add_notice(sprintf('付款失敗：%s。請確認卡片額度或發卡銀行授權狀態，或改用其他付款方式（如 虛擬 ATM、TWQR、超商付款）。如有任何問題，請聯絡客服（LINE: @eshopckc 或撥打 02-1234-5678）。', $data['RtnMsg']), 'error');
                        return;
                    }
                } else {
                    $err_msg = isset($response['TransMsg']) ? $response['TransMsg'] : '付款授權失敗';
                    if (isset($response['Data']['RtnMsg'])) {
                        $err_msg .= ': ' . $response['Data']['RtnMsg'];
                    }
                    error_log(sprintf('[ChaoECPg] Payment validation failed for Order %d: %s', $order_id, $err_msg));
                    wc_add_notice(sprintf('付款授權失敗：%s。請確認您的信用卡資訊，或稍後重試，亦可改用其他付款方式。如有需要，請聯絡客服（LINE: @eshopckc 或撥打 02-1234-5678）協助為您處理。', $err_msg), 'error');
                    return;
                }
            } catch (\Throwable $e) {
                error_log(sprintf('[ChaoECPg] CreatePayment connection error for Order %d: %s', $order_id, $e->getMessage()));
                wc_add_notice(sprintf('連線至綠界科技付款模組發生異常（%s），請稍後重試。若您的信用卡已被扣款，請勿重複提交，並請立即聯繫客服（LINE: @eshopckc 或撥打 02-1234-5678）為您手動確認訂單。', $e->getMessage()), 'error');
                return;
            }
        }

        /**
         * 處理 WooCommerce 後台線上信用卡退款 (DoAction)
         *
         * @param int $order_id 訂單 ID
         * @param float|null $amount 退款金額
         * @param string $reason 退款原因
         * @return bool|\WP_Error
         */
        public function process_refund($order_id, $amount = null, $reason = '') {
            $order = wc_get_order($order_id);
            if (!$order) {
                return new \WP_Error('invalid_order', '找不到欲退款的訂單。');
            }

            if ($amount <= 0) {
                return new \WP_Error('invalid_refund_amount', '退款金額必須大於 0。');
            }

            $merchant_trade_no = $order->get_meta('_chao_ecpg_trade_no');
            if (empty($merchant_trade_no)) {
                return new \WP_Error('missing_trade_no', '訂單缺少綠界商店交易編號（_chao_ecpg_trade_no），無法執行線上退刷。');
            }

            $ecpay_trade_no = $order->get_transaction_id();
            if (empty($ecpay_trade_no)) {
                $ecpay_trade_no = $order->get_meta('_chao_ecpg_ecpay_trade_no');
            }

            // 依官方規格：DoAction 端點在 ecpayment (正式)
            $api_url = 'https://ecpayment.ecpay.com.tw/1.0.0/Credit/DoAction';
            if ($this->test_mode) {
                $api_url = 'https://ecpayment-stage.ecpay.com.tw/1.0.0/Credit/DoAction';
            }

            $payload = [
                'MerchantID' => $this->merchant_id,
                'RqHeader'   => [
                    'Timestamp' => time(),
                ],
                'Data'       => [
                    'PlatformID'      => '',
                    'MerchantID'      => $this->merchant_id,
                    'MerchantTradeNo' => $merchant_trade_no,
                    'TradeNo'         => !empty($ecpay_trade_no) ? $ecpay_trade_no : '',
                    'Action'          => 'R', // R=退刷/退款, C=請款, E=取消, N=放棄
                    'TotalAmount'     => (int) round($amount),
                    'CustomField'     => substr(preg_replace('/[^a-zA-Z0-9_\-\/\.:]/', '', $reason), 0, 40),
                ],
            ];

            try {
                $factory = new \Ecpay\Sdk\Factories\Factory([
                    'hashKey' => $this->hash_key,
                    'hashIv'  => $this->hash_iv,
                ]);
                $postService = $factory->create('PostWithAesJsonResponseService');
                $response = $postService->post($payload, $api_url);

                error_log(sprintf('[ChaoECPg] DoAction Refund for Order %d: %s', $order_id, wp_json_encode($response)));

                if (isset($response['TransCode']) && $response['TransCode'] == 1 && isset($response['Data']['RtnCode'])) {
                    $data = $response['Data'];
                    if ((int)$data['RtnCode'] === 1) {
                        $order->add_order_note(sprintf(
                            '綠界站內付 2.0 信用卡線上退刷成功！退款金額：NT$%d，綠界交易號：%s，原因：%s',
                            (int) round($amount),
                            $data['TradeNo'] ?? $ecpay_trade_no,
                            !empty($reason) ? esc_html($reason) : '無'
                        ));
                        return true;
                    } else {
                        return new \WP_Error(
                            'ecpay_refund_failed',
                            sprintf('綠界退刷失敗：%s（代碼：%s）', $data['RtnMsg'] ?? '未知錯誤', $data['RtnCode'] ?? '無')
                        );
                    }
                } else {
                    $msg = $response['TransMsg'] ?? ($response['Data']['RtnMsg'] ?? '綠界伺服器傳輸異常');
                    return new \WP_Error('ecpay_refund_error', sprintf('綠界退款請求失敗：%s', $msg));
                }
            } catch (\Throwable $e) {
                error_log(sprintf('[ChaoECPg] DoAction Refund Exception for Order %d: %s', $order_id, $e->getMessage()));
                return new \WP_Error('ecpay_refund_exception', sprintf('連線至綠界執行退款時發生錯誤：%s', $e->getMessage()));
            }
        }

        /**
         * 查詢綠界訂單交易狀態 (QueryTrade)
         *
         * @param \WC_Order|int $order
         * @return array
         */
        public function query_order_status($order) {
            if (is_numeric($order)) {
                $order = wc_get_order($order);
            }
            if (!$order) {
                return ['success' => false, 'message' => '無效的訂單'];
            }

            $merchant_trade_no = $order->get_meta('_chao_ecpg_trade_no');
            if (empty($merchant_trade_no)) {
                return ['success' => false, 'message' => '訂單無綠界交易編號（_chao_ecpg_trade_no）'];
            }

            $api_url = $this->test_mode
                ? 'https://ecpayment-stage.ecpay.com.tw/1.0.0/Cashier/QueryTrade'
                : 'https://ecpayment.ecpay.com.tw/1.0.0/Cashier/QueryTrade';

            $payload = [
                'MerchantID' => $this->merchant_id,
                'RqHeader'   => [
                    'Timestamp' => time(),
                ],
                'Data'       => [
                    'PlatformID'      => '',
                    'MerchantID'      => $this->merchant_id,
                    'MerchantTradeNo' => $merchant_trade_no,
                ],
            ];

            try {
                $factory = new \Ecpay\Sdk\Factories\Factory([
                    'hashKey' => $this->hash_key,
                    'hashIv'  => $this->hash_iv,
                ]);
                $postService = $factory->create('PostWithAesJsonResponseService');
                $response = $postService->post($payload, $api_url);

                if (isset($response['TransCode']) && $response['TransCode'] == 1 && isset($response['Data']['RtnCode'])) {
                    $data = $response['Data'];
                    if ((int)$data['RtnCode'] === 1) {
                        $order_info = $data['OrderInfo'] ?? [];
                        $card_info  = $data['CardInfo'] ?? [];
                        $trade_status = $order_info['TradeStatus'] ?? ''; // 1=已付款, 0=未付款, 10200095=付款失敗
                        
                        $status_desc = '未付款';
                        if ($trade_status === '1' || $trade_status === 1) {
                            $status_desc = '已付款成功';
                            if (!$order->is_paid()) {
                                $order->payment_complete($order_info['TradeNo'] ?? '');
                            }
                        } elseif ($trade_status === '10200095') {
                            $status_desc = '付款失敗';
                        }

                        $note = sprintf(
                            '【綠界交易狀態查詢】狀態：%s (TradeStatus: %s) | 綠界單號：%s | 授權金額：NT$%s | 授權時間：%s | 卡號末四碼：%s | 授權碼：%s',
                            $status_desc,
                            $trade_status,
                            $order_info['TradeNo'] ?? '無',
                            $order_info['TradeAmt'] ?? '無',
                            $order_info['PaymentDate'] ?? ($order_info['TradeDate'] ?? '無'),
                            $card_info['Card4No'] ?? '無',
                            $card_info['AuthCode'] ?? '無'
                        );

                        $order->add_order_note($note);
                        if (!empty($order_info['TradeNo'])) {
                            $order->set_transaction_id($order_info['TradeNo']);
                            $order->update_meta_data('_chao_ecpg_ecpay_trade_no', $order_info['TradeNo']);
                        }
                        $order->save();

                        return [
                            'success' => true,
                            'status'  => $status_desc,
                            'data'    => $data,
                            'message' => $note
                        ];
                    } else {
                        return [
                            'success' => false,
                            'message' => sprintf('綠界回傳錯誤：%s (代碼: %s)', $data['RtnMsg'] ?? '未知', $data['RtnCode'] ?? '無')
                        ];
                    }
                } else {
                    return [
                        'success' => false,
                        'message' => $response['TransMsg'] ?? '綠界伺服器回傳無效資料'
                    ];
                }
            } catch (\Throwable $e) {
                return [
                    'success' => false,
                    'message' => '查詢異常：' . $e->getMessage()
                ];
            }
        }

        public function handle_callback() {
            $raw_body = file_get_contents('php://input');
            $json = json_decode($raw_body, true);

            if (!$json || !isset($json['TransCode']) || $json['TransCode'] != 1 || !isset($json['Data'])) {
                echo 'Invalid Payload';
                exit;
            }

            try {
                $aesService = new \Ecpay\Sdk\Services\AesService($this->hash_key, $this->hash_iv);
                $data = $aesService->decrypt($json['Data']);

                if (isset($data['RtnCode']) && $data['RtnCode'] == 1) {
                    $trade_no = $data['MerchantTradeNo'];
                    
                    $orders = wc_get_orders([
                        'meta_key' => '_chao_ecpg_trade_no',
                        'meta_value' => $trade_no,
                        'limit' => 1,
                    ]);

                    if (!empty($orders)) {
                        $order = reset($orders);
                        if (!$order->is_paid()) {
                            $ecpay_no = $data['TradeNo'] ?? '';
                            $order->payment_complete($ecpay_no);
                            if (!empty($ecpay_no)) {
                                $order->set_transaction_id($ecpay_no);
                                $order->update_meta_data('_chao_ecpg_ecpay_trade_no', $ecpay_no);
                                $order->save();
                            }
                            $order->add_order_note('綠界站內付 2.0 幕後回呼通知付款完成。');
                        }
                    }
                }

                echo '1|OK';
                exit;
            } catch (\Exception $e) {
                echo 'Decryption Error';
                exit;
            }
        }

        public function handle_result() {
            $result_data_str = isset($_POST['ResultData']) ? wp_unslash($_POST['ResultData']) : '';

            if (empty($result_data_str)) {
                wp_die('無效的付款結果回傳');
            }

            $json = json_decode($result_data_str, true);

            if (!$json || !isset($json['TransCode']) || $json['TransCode'] != 1 || !isset($json['Data'])) {
                wp_die('付款結果解析失敗');
            }

            try {
                $aesService = new \Ecpay\Sdk\Services\AesService($this->hash_key, $this->hash_iv);
                $data = $aesService->decrypt($json['Data']);

                $trade_no = $data['MerchantTradeNo'];
                $orders = wc_get_orders([
                    'meta_key' => '_chao_ecpg_trade_no',
                    'meta_value' => $trade_no,
                    'limit' => 1,
                ]);

                if (empty($orders)) {
                    wp_die('找不到對應的訂單，交易號：' . esc_html($trade_no));
                }

                $order = reset($orders);

                if (isset($data['RtnCode']) && $data['RtnCode'] == 1) {
                    if (!$order->is_paid()) {
                        $ecpay_no = $data['TradeNo'] ?? '';
                        $order->payment_complete($ecpay_no);
                        if (!empty($ecpay_no)) {
                            $order->set_transaction_id($ecpay_no);
                            $order->update_meta_data('_chao_ecpg_ecpay_trade_no', $ecpay_no);
                            $order->save();
                        }
                        $order->add_order_note('綠界站內付 2.0 3D驗證完成付款。');
                    }
                    WC()->cart->empty_cart();
                    wp_safe_redirect($this->get_return_url($order));
                    exit;
                } else {
                    $order->update_status('failed', sprintf('綠界站內付 2.0 驗證失敗：%s', $data['RtnMsg']));
                    wc_add_notice('付款失敗：' . $data['RtnMsg'], 'error');
                    wp_safe_redirect(wc_get_checkout_url());
                    exit;
                }
            } catch (\Exception $e) {
                wp_die('付款解密驗證失敗：' . esc_html($e->getMessage()));
            }
        }
    }

    add_filter('woocommerce_payment_gateways', 'chao_ecpg_add_gateway');
    function chao_ecpg_add_gateway($methods) {
        $methods[] = 'WC_Gateway_Chao_ECPay_ECPg';
        return $methods;
    }

    // AJAX Token 端點 (全域註冊)
    add_action('wp_ajax_chao_ecpg_get_token', 'chao_ecpg_ajax_get_token_handler');
    add_action('wp_ajax_nopriv_chao_ecpg_get_token', 'chao_ecpg_ajax_get_token_handler');

    // 綠界回呼 (Callback) 路由 (全域註冊)
    add_action('woocommerce_api_chao_ecpg_callback', 'chao_ecpg_handle_callback_handler');
    add_action('woocommerce_api_chao_ecpg_result', 'chao_ecpg_handle_result_handler');

    // WooCommerce 訂單後台動作：向綠界查詢交易付款狀態
    add_filter('woocommerce_order_actions', 'chao_ecpg_add_order_actions');
    function chao_ecpg_add_order_actions($actions) {
        global $theorder;
        if ($theorder && $theorder->get_payment_method() === 'chao_ecpay_ecpg') {
            $actions['chao_ecpg_query_status'] = '向綠界查詢交易付款狀態 (ECPay)';
        }
        return $actions;
    }

    add_action('woocommerce_order_action_chao_ecpg_query_status', 'chao_ecpg_process_order_action_query_status');
    function chao_ecpg_process_order_action_query_status($order) {
        $gateway = new WC_Gateway_Chao_ECPay_ECPg();
        $result = $gateway->query_order_status($order);
        if ($result['success']) {
            $order->add_order_note('手動觸發綠界查詢成功：' . $result['message']);
        } else {
            $order->add_order_note('手動觸發綠界查詢失敗：' . $result['message']);
        }
    }
}

function chao_ecpg_ajax_get_token_handler() {
    $gateway = new WC_Gateway_Chao_ECPay_ECPg();
    $gateway->ajax_get_token();
}

function chao_ecpg_handle_callback_handler() {
    $gateway = new WC_Gateway_Chao_ECPay_ECPg();
    $gateway->handle_callback();
}

function chao_ecpg_handle_result_handler() {
    $gateway = new WC_Gateway_Chao_ECPay_ECPg();
    $gateway->handle_result();
}

/**
 * 綠界金流幕後服務封裝類別 (Chao_ECPay_Backend_Service)
 * 支援非信用卡幕後取號 (ATM/CVS/BARCODE)、信用卡幕後授權 (BackAuth)、請退款 (DoAction) 與查詢
 */
class Chao_ECPay_Backend_Service {
    protected $merchant_id;
    protected $hash_key;
    protected $hash_iv;
    protected $test_mode;

    public function __construct() {
        $mid = get_option('wooecpay_payment_mid');
        $this->test_mode = (get_option('wooecpay_enabled_payment_stage', 'no') === 'yes') || empty($mid);

        if ($this->test_mode) {
            $this->merchant_id = '3002607';
            $this->hash_key    = 'pwFHCqoQZGmho4w6';
            $this->hash_iv     = 'EkRm7iFT261dpevs';
        } else {
            $this->merchant_id = $mid;
            $this->hash_key    = get_option('wooecpay_payment_hashkey');
            $this->hash_iv     = get_option('wooecpay_payment_hashiv');
        }
    }

    /**
     * 信用卡請退款與授權放棄 (DoAction)
     *
     * @param string $merchant_trade_no 商店交易編號
     * @param string $trade_no 綠界交易序號
     * @param string $action 操作類型: C=請款, R=退款, E=取消關帳, N=放棄
     * @param int $total_amount 金額
     * @param string $reason 原因備註
     * @return array
     */
    public function do_action($merchant_trade_no, $trade_no, $action = 'R', $total_amount = 0, $reason = '') {
        $api_url = $this->test_mode
            ? 'https://ecpayment-stage.ecpay.com.tw/1.0.0/Credit/DoAction'
            : 'https://ecpayment.ecpay.com.tw/1.0.0/Credit/DoAction';

        $payload = [
            'MerchantID' => $this->merchant_id,
            'RqHeader'   => [ 'Timestamp' => time() ],
            'Data'       => [
                'PlatformID'      => '',
                'MerchantID'      => $this->merchant_id,
                'MerchantTradeNo' => $merchant_trade_no,
                'TradeNo'         => $trade_no,
                'Action'          => $action,
                'TotalAmount'     => (int) $total_amount,
                'CustomField'     => substr(preg_replace('/[^a-zA-Z0-9_\-\/\.:]/', '', $reason), 0, 40),
            ],
        ];

        return $this->post_aes_json($payload, $api_url);
    }

    /**
     * 查詢交易狀態 (QueryTrade)
     */
    public function query_trade($merchant_trade_no) {
        $api_url = $this->test_mode
            ? 'https://ecpayment-stage.ecpay.com.tw/1.0.0/Cashier/QueryTrade'
            : 'https://ecpayment.ecpay.com.tw/1.0.0/Cashier/QueryTrade';

        $payload = [
            'MerchantID' => $this->merchant_id,
            'RqHeader'   => [ 'Timestamp' => time() ],
            'Data'       => [
                'PlatformID'      => '',
                'MerchantID'      => $this->merchant_id,
                'MerchantTradeNo' => $merchant_trade_no,
            ],
        ];

        return $this->post_aes_json($payload, $api_url);
    }

    /**
     * 非信用卡幕後取號 (GenPaymentCode)
     */
    public function gen_payment_code($merchant_trade_no, $total_amount, $choose_payment = 'ATM', $extra_info = []) {
        $api_url = $this->test_mode
            ? 'https://ecpayment-stage.ecpay.com.tw/1.0.0/Cashier/GenPaymentCode'
            : 'https://ecpayment.ecpay.com.tw/1.0.0/Cashier/GenPaymentCode';

        $data = [
            'MerchantID'    => $this->merchant_id,
            'ChoosePayment' => $choose_payment,
            'OrderInfo'     => [
                'MerchantTradeNo'   => $merchant_trade_no,
                'MerchantTradeDate' => date('Y/m/d H:i:s'),
                'TotalAmount'       => (int) $total_amount,
                'TradeDesc'         => '幕後取號交易',
                'ItemName'          => '電商購物商品',
                'ReturnURL'         => home_url('/?wc-api=chao_ecpg_callback'),
            ],
        ];

        if ($choose_payment === 'ATM') {
            $data['ATMInfo'] = [
                'ExpireDate' => $extra_info['ExpireDate'] ?? 3,
            ];
        } elseif ($choose_payment === 'CVS') {
            $data['CVSInfo'] = [
                'ExpireDate' => $extra_info['ExpireDate'] ?? 10080,
                'CVSCode'    => $extra_info['CVSCode'] ?? 'CVS',
            ];
        }

        $payload = [
            'MerchantID' => $this->merchant_id,
            'RqHeader'   => [ 'Timestamp' => time() ],
            'Data'       => $data,
        ];

        return $this->post_aes_json($payload, $api_url);
    }

    protected function post_aes_json($payload, $api_url) {
        try {
            $factory = new \Ecpay\Sdk\Factories\Factory([
                'hashKey' => $this->hash_key,
                'hashIv'  => $this->hash_iv,
            ]);
            $postService = $factory->create('PostWithAesJsonResponseService');
            return $postService->post($payload, $api_url);
        } catch (\Throwable $e) {
            return [
                'TransCode' => 0,
                'TransMsg'  => $e->getMessage(),
            ];
        }
    }
}
