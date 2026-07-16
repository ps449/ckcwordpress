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
            $this->method_title = '綠界站內付 2.0 (信用卡/多合一)';
            $this->method_description = '提供嵌入式信用卡卡號輸入與 LINE Pay、虛擬 ATM 等付款方式（無跳轉）';

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title', '綠界站內付 2.0');
            $this->description = $this->get_option('description', '直接在結帳頁面安全填寫信用卡資訊完成付款');

            // 讀取官方金流設定的特店 ID
            $mid = get_option('wooecpay_payment_mid');

            // 如果官方設定為測試模式，或是還沒有填寫正式特店 ID（仍為預設測試 ID 3312200 或空值），則強制作為測試模式運作
            $this->test_mode = (get_option('wooecpay_enabled_payment_stage', 'yes') === 'yes') || empty($mid) || $mid === '3312200';

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
                    'default' => '信用卡 / 行動支付 (站內付 2.0)',
                    'desc_tip' => true,
                ],
                'description' => [
                    'title' => '描述',
                    'type' => 'textarea',
                    'description' => '消費者在結帳頁看到的付款描述',
                    'default' => '安全填寫信用卡或選擇 LINE Pay 完成付款',
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
                    // 立即初始化 ECPay SDK，避免非同步載入衝突
                    if (typeof ECPay !== 'undefined') {
                        ECPay.initialize(ChaoECPg.stage, 1, function(errMsg) {
                            if (errMsg != null) {
                                console.error('ECPay SDK Init Error:', errMsg);
                            } else {
                                ChaoECPg.isInitialized = true;
                            }
                        });
                    }

                    // 顯示可行動的錯誤訊息＋「重試」按鈕（不必重新整理整頁）
                    function showEcpayError(msg) {
                        var el = $('#ecpay-loading');
                        el.empty().show();
                        $('<div/>').css({ color: '#c0392b', 'line-height': '1.6' }).text(msg).appendTo(el);
                        $('<button/>', { type: 'button', id: 'ecpay-retry-btn', text: '重新載入付款模組' })
                            .css({ 'margin-top': '10px', padding: '8px 20px', border: '1px solid #c0392b', background: '#fff', color: '#c0392b', 'border-radius': '4px', cursor: 'pointer' })
                            .appendTo(el);
                    }

                    $(document).on('click', '#ecpay-retry-btn', function() {
                        $('#ECPayPayment').data('token-failed', false).data('token-fetching', false);
                        $('#ecpay-loading').text('載入安全金流模組中，請稍候...');
                        initOrUpdateECPayDebounced();
                    });

                    function initOrUpdateECPay() {
                        var mainContainer = $('#ECPayPayment-container');
                        if (mainContainer.length > 0) {
                            if (!$('#payment_method_chao_ecpay_ecpg').is(':checked')) {
                                mainContainer.hide();
                                return;
                            }
                            
                            // 移動到「信用卡安全支付」卡片正下方（而非整個付款區塊最後），
                            // 讓輸入表單與使用者選取的選項視覺上相連，不必捲過其餘付款方式才看得到。
                            var creditCardEl = $('.chao-payment-card[data-payment=\"credit\"]');
                            if (creditCardEl.length > 0) {
                                if (!mainContainer.prev().is(creditCardEl)) {
                                    mainContainer.insertAfter(creditCardEl);
                                }
                            } else if ($('#chao-payment-section').length > 0 && !mainContainer.parent().is('#chao-payment-section')) {
                                // Fallback：若找不到卡片版型（例如版面調整），維持舊行為避免表單被隱藏而看不見
                                mainContainer.appendTo('#chao-payment-section');
                            }
                            mainContainer.show();
                        } else {
                            if (!$('#payment_method_chao_ecpay_ecpg').is(':checked')) {
                                return;
                            }
                        }
                        
                        var container = $('#ECPayPayment');
                        if (container.length === 0) {
                            return;
                        }

                        // 如果已經有金流介面渲染在裡面，不重複初始化 (站內付 2.0 渲染的是 .ecpay-pay-list-wrap 而非 iframe)
                        if (container.find('.ecpay-pay-list-wrap').length > 0) {
                            return;
                        }

                        if (container.data('token-fetching') || container.data('token-failed')) {
                            return;
                        }

                        container.data('token-fetching', true);
                        container.data('needs-reinit', false);
                        $('#ecpay-loading').show().text('載入安全金流模組中，請稍候...');

                        // 每次重新渲染，都必須取得一個新的單次交易 Token
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
                                
                                if (container.data('needs-reinit') || !currentContainer.is(container)) {
                                    // WooCommerce 在我們抓取 Token 時更新了 DOM，需重新執行
                                    setTimeout(initOrUpdateECPayDebounced, 50);
                                    return;
                                }

                                if (response.success && response.token) {
                                    ChaoECPg.token = response.token;
                                    renderECPay(response.token);
                                } else {
                                    container.data('token-failed', true);
                                    showEcpayError(response.message || '無法載入信用卡付款模組。請點擊下方按鈕重試，或改用其他付款方式（如 ATM、LINE Pay、超商付款）。如持續發生，請聯絡客服（LINE: @eshopckc）。');
                                }
                            },
                            error: function() {
                                var currentContainer = $('#ECPayPayment');
                                if (currentContainer.length > 0) {
                                    currentContainer.data('token-fetching', false);
                                    currentContainer.data('token-failed', true);
                                }
                                showEcpayError('網路連線不穩定，無法載入付款模組。請確認網路狀態後點擊下方按鈕重試，或改用其他付款方式（如 ATM、LINE Pay、超商付款）。');
                            }
                        });
                    }

                    function renderECPay(token) {
                        if (ChaoECPg.isInitialized) {
                            doCreatePayment(token);
                        } else {
                            // 若尚未初始化完成，稍候再試
                            setTimeout(function() {
                                renderECPay(token);
                            }, 100);
                        }
                    }

                    function initOrUpdateECPayDebounced() {
                        clearTimeout(window.ECPayDebounceTimer);
                        window.ECPayDebounceTimer = setTimeout(initOrUpdateECPay, 100);
                    }

                    function doCreatePayment(token) {
                        var container = $('#ECPayPayment');
                        if (container.length === 0) {
                            return;
                        }
                        
                        ECPay.createPayment(token, 'zh-TW', function(errMsg) {
                            if (errMsg != null) {
                                console.error('ECPay Create UI Error:', errMsg);
                                showEcpayError('金流介面載入失敗（' + errMsg + '）。請點擊下方按鈕重試，或改用其他付款方式。如持續發生，請聯絡客服（LINE: @eshopckc）。');
                                container.data('token-failed', true);
                                return;
                            }
                            $('#ecpay-loading').hide();
                        }, 'V2');
                    }

                    // 監聽 WooCommerce 結帳更新完成事件
                    $(document.body).on('updated_checkout', function() {
                        initOrUpdateECPayDebounced();
                    });

                    // 當切換付款方式時，作為備援觸發
                    $(document.body).on('change', 'input[name=\"payment_method\"]', function() {
                        if ($('#payment_method_chao_ecpay_ecpg').is(':checked')) {
                            initOrUpdateECPayDebounced();
                        }
                    });

                    // 頁面初次載入檢查一次
                    initOrUpdateECPayDebounced();

                    // 自動自癒機制：每 500ms 檢查，若選中此付款方式但 DOM 被外部外掛或佈景主題清空且未失敗，則自動重載
                    setInterval(function() {
                        if ($('#payment_method_chao_ecpay_ecpg').is(':checked')) {
                            var container = $('#ECPayPayment');
                            if (container.length > 0 && 
                                container.find('.ecpay-pay-list-wrap').length === 0 && 
                                !container.data('token-fetching') && 
                                !container.data('token-failed')) {
                                console.log('Self-healing: ECPay DOM empty, re-initializing...');
                                initOrUpdateECPayDebounced();
                            }
                        }
                    }, 500);
                    // 攔截 WooCommerce 下單按鈕事件
                    $('form.checkout').on('checkout_place_order_chao_ecpay_ecpg', function() {
                        var form = $(this);
                        
                        if ($('#chao_ecpg_pay_token').val() !== '') {
                            return true;
                        }

                        form.addClass('processing');

                        // 明確的處理中提示，降低消費者誤以為當機而重複送出
                        $('#ecpay-loading').empty().show()
                            .text('付款處理中，請勿重新整理頁面或重複點擊…');

                        // 呼叫 WebJS SDK 取得一次性 PayToken
                        ECPay.getPayToken(function(paymentInfo, errMsg) {
                            if (errMsg != null) {
                                $('#ecpay-loading').hide();
                                alert('信用卡資訊驗證失敗，請確認卡號、有效期限與卡片背面末三碼是否輸入正確後再試一次。若仍無法付款，可改用其他付款方式，或聯絡客服（LINE: @eshopckc）。');
                                form.removeClass('processing');
                                return false;
                            }
                            
                            // 將 PayToken 寫入 hidden 欄位
                            $('#chao_ecpg_pay_token').val(paymentInfo.PayToken);
                            
                            // 重新提交表單
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
                    min-height: 350px !important;
                    width: 100% !important;
                    border: none !important;
                }
            </style>
            <div id="ECPayPayment-container" style="grid-column: 1 / -1; margin-top: 15px; border: 1px solid #ddd; padding: 15px; border-radius: 5px; background: #fff;">
                <div id="ecpay-loading" style="text-align: center; color: #666; font-size: 14px; padding: 20px;">
                    載入安全金流模組中，請稍候...
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
                    'ChoosePaymentList' => '1', // 僅顯示信用卡一次付清 (避免需要 ATMInfo 等額外參數)
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
                        $err_msg = sprintf('取得付款授權憑證失敗（狀態碼：%s），請重新整理網頁並再試一次，或改用其他付款方式（如 ATM、LINE Pay、超商代碼）。如有疑問，歡迎聯絡客服（LINE: @eshopckc）。', $trans_code);
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

                    $order->add_order_note(sprintf('綠界站內付 2.0 交易序號：%s，回傳代碼：%d，回傳訊息：%s', 
                        isset($data['OrderInfo']['TradeNo']) ? $data['OrderInfo']['TradeNo'] : '無', 
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
                        $order->payment_complete();
                        WC()->cart->empty_cart();
                        return [
                            'result' => 'success',
                            'redirect' => $this->get_return_url($order),
                        ];
                    } else {
                        // 授權失敗：具體說明如何修正
                        error_log(sprintf('[ChaoECPg] Credit card payment failed for Order %d: RtnCode=%d RtnMsg=%s', $order_id, $rtn_code, $data['RtnMsg']));
                        wc_add_notice(sprintf('付款失敗：%s。請確認卡片額度或發卡銀行授權狀態，或改用其他付款方式（如 ATM、LINE Pay、超商付款）。如有任何問題，請聯絡客服（LINE: @eshopckc 或撥打 02-1234-5678）。', $data['RtnMsg']), 'error');
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
                            $order->payment_complete($data['TradeNo'] ?? '');
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
                        $order->payment_complete($data['TradeNo'] ?? '');
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
