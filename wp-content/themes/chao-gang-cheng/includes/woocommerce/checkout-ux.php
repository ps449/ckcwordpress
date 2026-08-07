<?php
/**
 * --- CHECKOUT UX OPTIMIZATION BACKEND HOOKS ---
 */

/**
 * 1. Customize "Create an account?" checkbox label text
 */
add_filter( 'gettext', 'chao_custom_checkout_gettext', 20, 3 );
function chao_custom_checkout_gettext( $translated_text, $text, $domain ) {
    if ( is_checkout() && 'woocommerce' === $domain ) {
        if ( 'Create an account?' === $text ) {
            return '建立帳戶以確認訂單(非必填，註冊後即可累積紅利點數）';
        }
    }
    return $translated_text;
}

/**
 * 結帳頁添加商品原價 (小計欄位顯示刪除線原價)
 */
add_filter( 'woocommerce_cart_item_subtotal', 'ckc_checkout_item_subtotal_add_regular', 10, 3 );
function ckc_checkout_item_subtotal_add_regular( $subtotal_html, $cart_item, $cart_item_key ) {
    // 只在結帳頁處理，避免影響購物車 (購物車已有單價原價)
    if ( is_checkout() && ! is_wc_endpoint_url() ) {
        $product = $cart_item['data'];

        if ( $product && $product->is_on_sale() ) {
            // 直接從資料庫 meta 讀取原價，完全繞過 WooCommerce 價格 hook 干擾
            $product_id    = $product->get_id();
            $regular_price = (float) get_post_meta( $product_id, '_regular_price', true );

            // 修正：WooCommerce 購物車 quantity key 是 'quantity'，不是 'qty'
            $qty = isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 1;

            if ( $regular_price > 0 && $qty > 0 ) {
                $regular_subtotal = $regular_price * $qty;
                $clean_regular    = strip_tags( wc_price( $regular_subtotal ) );
                $clean_current    = strip_tags( $subtotal_html );

                // 只在真的有打折時才顯示刪除線
                if ( $clean_regular !== $clean_current ) {
                    return '<del style="color:#999; font-size:0.85em; display:block; line-height: 1; margin-bottom:2px;">' . $clean_regular . '</del>' .
                           '<ins style="text-decoration:none; display:block; font-weight:700;">' . $subtotal_html . '</ins>';
                }
            }
        }
    }
    return $subtotal_html;
}

/**
 * 2. Dynamically restrict WooCommerce native COD payment availability to CVS shipping
 */
add_filter( 'woocommerce_available_payment_gateways', 'chao_available_payment_gateways', 25 );
function chao_available_payment_gateways( $gateways ) {
    if ( is_admin() ) {
        return $gateways;
    }
    
    // Check chosen shipping method
    $chosen_shipping = '';
    if ( WC()->session ) {
        $chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
        $chosen_shipping = isset( $chosen_methods[0] ) ? $chosen_methods[0] : '';
    }
    
    $is_cvs = ( strpos( $chosen_shipping, 'Wooecpay_Logistic_CVS_711' ) !== false );
    $loaded_gateways = WC()->payment_gateways->payment_gateways();
    
    if ( $is_cvs ) {
        // 1. If CVS is active, force-enable and inject native COD gateway
        if ( ! isset( $gateways['cod'] ) && isset( $loaded_gateways['cod'] ) ) {
            $cod_gateway = $loaded_gateways['cod'];
            $cod_gateway->enabled = 'yes';
            $cod_gateway->enable_for_methods = array();
            $gateways['cod'] = $cod_gateway;
        } elseif ( ! isset( $gateways['cod'] ) && class_exists( 'WC_Gateway_COD' ) ) {
            $cod_gateway = new WC_Gateway_COD();
            $cod_gateway->enabled = 'yes';
            $cod_gateway->enable_for_methods = array();
            $gateways['cod'] = $cod_gateway;
        }
        
        // 2. Re-inject Credit Card (站內付 2.0) and mydybox_ecpay (LINE Pay, ATM, CVS Code) if removed by shipping plugins
        if ( ! isset( $gateways['chao_ecpay_ecpg'] ) && isset( $loaded_gateways['chao_ecpay_ecpg'] ) ) {
            $gateways['chao_ecpay_ecpg'] = $loaded_gateways['chao_ecpay_ecpg'];
        }
        if ( ! isset( $gateways['mydybox_ecpay'] ) && isset( $loaded_gateways['mydybox_ecpay'] ) ) {
            $gateways['mydybox_ecpay'] = $loaded_gateways['mydybox_ecpay'];
        }
    } else {
        // Remove native COD option if chosen shipping is NOT CVS
        if ( isset( $gateways['cod'] ) ) {
            unset( $gateways['cod'] );
        }
    }
    
    return $gateways;
}

/**
 * 3. Render free shipping progress bar at the top of the checkout form
 */
add_action( 'woocommerce_before_checkout_form', 'chao_checkout_free_shipping_progress', 5 );
function chao_checkout_free_shipping_progress() {
    $threshold = chao_get_free_shipping_threshold();

    // 這裡原本就是用折扣後金額（正確），現在統一呼叫共用 helper，
    // 確保跟購物車頁的判斷基準永遠是同一份邏輯，見
    // functions.php: chao_get_free_shipping_progress_amount()
    $cart_total = chao_get_free_shipping_progress_amount();
    $remaining  = $threshold - $cart_total;

    // 檢查是否已套用含「允許免運費」的折價券
    $coupon_free_shipping = false;
    if ( WC()->cart ) {
        foreach ( WC()->cart->get_applied_coupons() as $coupon_code ) {
            $coupon = new WC_Coupon( $coupon_code );
            if ( $coupon->get_id() && $coupon->get_free_shipping() ) {
                $coupon_free_shipping = true;
                break;
            }
        }
    }

    $is_free = $coupon_free_shipping || $remaining <= 0;
    ?>
    <div class="chao-shipping-progress-container" style="margin-bottom: 25px; padding: 18px; border-radius: 10px; background: #fffaf1; border: 1px solid #e2d2b3; box-shadow: 0 1px 3px rgba(26,20,15,0.04);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-size: 14px; font-weight: 600;">
            <span style="color: #3a2f24; display: flex; align-items: center; gap: 6px;">
                <?php if ( $coupon_free_shipping ) : ?>
                    <span style="font-size: 16px;"></span> 已套用免運優惠券，本次配送免運費！
                <?php elseif ( $remaining <= 0 ) : ?>
                    <span style="font-size: 16px;"></span> 恭喜！您已達免運門檻，本次配送免運費！
                <?php else : ?>
                    <span style="font-size: 16px;">🚚</span> 距離免運門檻還差 <span style="color: #f86f69; font-size: 16px; font-weight: 700;">NT$<?php echo number_format( $remaining ); ?></span>
                <?php endif; ?>
            </span>
            <?php if ( ! $coupon_free_shipping ) : ?>
            <span style="color: #8c7a64; font-size: 13px;">免運門檻 NT$<?php echo number_format( $threshold ); ?></span>
            <?php endif; ?>
        </div>
        <div class="chao-progress-track" style="width: 100%; height: 8px; background: #f2e9d8; border-radius: 4px; overflow: hidden;">
            <?php
            $percentage = $is_free ? 100 : min( 100, max( 0, ( $cart_total / $threshold ) * 100 ) );
            $bar_color  = $is_free ? 'linear-gradient(90deg, #10b981 0%, #047857 100%)' : 'linear-gradient(90deg, #e3c586 0%, #c9974a 100%)';
            ?>
            <div class="chao-progress-bar" style="width: <?php echo esc_attr( $percentage ); ?>%; height: 100%; background: <?php echo esc_attr( $bar_color ); ?>; transition: width 0.4s ease-in-out;"></div>
        </div>
    </div>
    <?php
}

/**
 * 4. Hook to render one-click guest registration on WooCommerce thank you page
 */
add_action( 'woocommerce_thankyou', 'chao_thankyou_guest_registration_form', 25, 1 );
function chao_thankyou_guest_registration_form( $order_id ) {
    if ( is_user_logged_in() || ! $order_id ) {
        return;
    }
    $order = wc_get_order( $order_id );
    if ( ! $order || $order->get_customer_id() ) {
        return;
    }
    $email = $order->get_billing_email();
    if ( ! $email || email_exists( $email ) ) {
        return;
    }
    
    // Output HTML and JS for guest registration
    ?>
    <div class="chao-thankyou-register-card" style="margin: 30px 0; padding: 25px; border: 1px solid #e2e8f0; border-radius: 12px; background: #f8fafc; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
        <h3 style="margin-top: 0; color: #1e293b; font-size: 18px; display: flex; align-items: center; gap: 8px;">
            <span></span> 一鍵建立帳號，隨時查詢訂單進度！
        </h3>
        <p style="color: #64748b; font-size: 14px; margin-bottom: 20px; line-height: 1.6;">
            免去重複填寫資料的麻煩，還能即時追蹤您的出貨進度。我們已為您帶入此訂單的電子郵件：<strong><?php echo esc_html( $email ); ?></strong>，只需設定密碼即可立即啟用帳號！
        </p>
        <div class="chao-register-inputs" style="display: flex; gap: 12px; max-width: 500px; flex-wrap: wrap;">
            <input type="password" id="chao_register_password" placeholder="請設定您的登入密碼" style="flex: 1; min-width: 220px; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;" />
            <button id="chao_register_submit" data-order-id="<?php echo esc_attr( $order_id ); ?>" style="padding: 10px 20px; background: #0073aa; color: #fff; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s;">
                立即建立並登入
            </button>
        </div>
        <div id="chao_register_message" style="margin-top: 12px; font-size: 14px; font-weight: 500;"></div>
    </div>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#chao_register_password').on('keypress', function(e) {
            if (e.which === 13) {
                $('#chao_register_submit').trigger('click');
            }
        });
        
        $('#chao_register_submit').on('click', function() {
            var $btn = $(this);
            var password = $('#chao_register_password').val();
            var orderId = $btn.data('order-id');
            var $msg = $('#chao_register_message');
            
            if (!password || password.length < 6) {
                $msg.css('color', '#ea4335').text('密碼長度需至少為 6 個字元。');
                return;
            }
            
            $btn.prop('disabled', true).text('建立中...');
            
            $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                action: 'chao_thankyou_guest_register',
                password: password,
                order_id: orderId,
                nonce: '<?php echo wp_create_nonce("chao_thankyou_register_nonce"); ?>'
            }, function(res) {
                if (res.success) {
                    $msg.css('color', '#10b981').text(res.data.message);
                    $('.chao-thankyou-register-card').delay(2500).slideUp(500);
                } else {
                    $msg.css('color', '#ea4335').text(res.data.message);
                    $btn.prop('disabled', false).text('立即建立並登入');
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * 5. AJAX handler for guest registration on thank you page
 */
add_action( 'wp_ajax_nopriv_chao_thankyou_guest_register', 'chao_ajax_thankyou_guest_register_handler' );
function chao_ajax_thankyou_guest_register_handler() {
    check_ajax_referer( 'chao_thankyou_register_nonce', 'nonce' );
    
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    
    if ( empty($password) || strlen($password) < 6 ) {
        wp_send_json_error( array( 'message' => '密碼長度需至少為 6 個字元。' ) );
    }
    
    if ( ! $order_id ) {
        wp_send_json_error( array( 'message' => '無效的訂單編號。' ) );
    }
    
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        wp_send_json_error( array( 'message' => '找不到對應的訂單。' ) );
    }
    
    $email = $order->get_billing_email();
    if ( ! $email ) {
        wp_send_json_error( array( 'message' => '該訂單無電子郵件資訊。' ) );
    }
    
    if ( email_exists( $email ) ) {
        wp_send_json_error( array( 'message' => '此電子郵件已被註冊，請直接登入。' ) );
    }
    
    // Create new customer
    $username = sanitize_user( current( explode( '@', $email ) ) );
    // Avoid username conflicts
    $base_username = $username;
    $i = 1;
    while ( username_exists( $username ) ) {
        $username = $base_username . $i;
        $i++;
    }
    
    $customer_id = wc_create_new_customer( $email, $username, $password );
    if ( is_wp_error( $customer_id ) ) {
        wp_send_json_error( array( 'message' => $customer_id->get_error_message() ) );
    }
    
    // Link current order to new customer
    update_post_meta( $order_id, '_customer_user', $customer_id );
    
    // Link past orders under same email
    wc_update_new_customer_past_orders( $customer_id );
    
    // Log the user in
    wp_clear_auth_cookie();
    wp_set_current_user( $customer_id );
    wp_set_auth_cookie( $customer_id );
    
    wp_send_json_success( array( 'message' => '帳號建立成功，系統已為您自動登入！' ) );
}

/**
 * 6. Programmatically force enable WooCommerce native COD settings option for frontend checkout
 */
add_filter( 'option_woocommerce_cod_settings', 'chao_force_enable_cod_setting' );
function chao_force_enable_cod_setting( $value ) {
    if ( is_admin() ) {
        return $value;
    }
    if ( ! is_array( $value ) ) {
        $value = array();
    }
    $value['enabled'] = 'yes';
    return $value;
}

/**
 * 7. Hide email field on account details page for LINE login users
 */
add_action( 'wp_head', 'chao_gang_cheng_hide_line_email_field' );
function chao_gang_cheng_hide_line_email_field() {
    if ( is_user_logged_in() ) {
        $current_user = wp_get_current_user();
        if ( $current_user && strpos( $current_user->user_email, 'line-login.local' ) !== false ) {
            ?>
            <style>
                /* Hide email address row from edit-account form */
                .woocommerce-EditAccountForm p.form-row:has(#account_email),
                .woocommerce-form-row:has(#account_email),
                p.form-row-wide:has(#account_email) {
                    display: none !important;
                }
            </style>
            <script>
                jQuery(document).ready(function($) {
                    // Fail-safe: double check and hide via JQuery
                    if ($('#account_email').length) {
                        $('#account_email').closest('.form-row').hide();
                    }
                });
            </script>
            <?php
        }
    }
}

/**
 * Customize WooCommerce loop add to cart link/button for out of stock products
 */
add_filter( 'woocommerce_loop_add_to_cart_link', 'ckc_custom_loop_add_to_cart_link', 99, 2 );
function ckc_custom_loop_add_to_cart_link( $html, $product ) {
    if ( ! $product->is_in_stock() ) {
        $html = sprintf(
            '<a href="javascript:void(0);" class="button add-to-cart-btn disabled" aria-label="%s" style="pointer-events: none; background-color: #eaeaea !important; color: #888 !important; border: 1px solid #ddd !important; text-align: center; box-shadow: none !important; cursor: not-allowed !important;">%s</a>',
            esc_attr__( '已售完', 'chao-gang-cheng' ),
            esc_html__( '已售完', 'chao-gang-cheng' )
        );
    }
    return $html;
}

/**
 * 31x. Order-pay（金流跳轉頁）防護：
 * 綠界 AIO 外掛在此頁以泛用選擇器自動送出表單，曾誤送主題搜尋表單導致
 * 跳轉到空白搜尋頁而非綠界付款頁。header.php 已在此頁停止輸出搜尋表單；
 * 這裡再加後備防護：移除任何 GET 表單（含管理列搜尋），並確保綠界付款
 * 表單被「定向」送出。
 */
add_action( 'wp_head', 'chao_orderpay_payment_redirect_guard', 5 );
function chao_orderpay_payment_redirect_guard() {
    if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'order-pay' ) ) {
        return;
    }
    ?>
    <script>
    (function() {
        // 移除頁面上所有 GET 表單（搜尋、管理列），讓 forms[0] 一定是付款表單
        function purgeGetForms() {
            var forms = document.querySelectorAll('form');
            for (var i = 0; i < forms.length; i++) {
                var f = forms[i];
                if ((f.method || '').toLowerCase() === 'get' && f.parentNode) {
                    f.parentNode.removeChild(f);
                }
            }
        }
        try {
            new MutationObserver(purgeGetForms).observe(document.documentElement, { childList: true, subtree: true });
        } catch (e) {}
        document.addEventListener('DOMContentLoaded', function() {
            purgeGetForms();
            // 後備：一秒後若仍在本頁，定向送出綠界付款表單
            setTimeout(function() {
                var pay = document.querySelector('form[action*="ecpay.com.tw"]');
                if (pay) {
                    pay.submit();
                }
            }, 1000);
        });
    })();
    </script>
    <?php
}

/* ============================================================
 * 32. Cart page UX optimizations (cart_ux_optimization_plan.docx §4–§5)
 *   32a. Estimated shipping row in cart totals (shipping transparency)
 *   32b. Free-shipping cross-sell block ("湊免運" recommendations)
 *   32c. Continue-shopping link in cart actions
 *   32d. Trust badges under the proceed-to-checkout button
 *   32e. Cart JS/CSS: auto quantity recalculation, live progress bar,
 *        mobile sticky checkout bar
 * ============================================================ */

// 32a-helper-cvs. Get CVS shipping cost from WooCommerce shipping zones (Wooecpay_Logistic_CVS_711).
// Used for the server-side initial value in the checkout CVS card to avoid hardcoded NT$280.
function chao_get_cvs_shipping_cost() {
    static $cached = null;
    if ( $cached !== null ) {
        return $cached;
    }
    $cached = 280; // default fallback
    if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
        return $cached;
    }
    $all_zones = array_merge(
        WC_Shipping_Zones::get_zones(),
        array( array( 'shipping_methods' => WC_Shipping_Zones::get_zone_by( 'zone_id', 0 )->get_shipping_methods( true ) ) )
    );
    foreach ( $all_zones as $zone ) {
        $methods = isset( $zone['shipping_methods'] ) ? $zone['shipping_methods'] : array();
        foreach ( $methods as $method ) {
            // Match ECPay CVS 711 shipping method
            if ( strpos( $method->id, 'Wooecpay_Logistic_CVS' ) !== false && 'yes' === $method->enabled ) {
                $cost = $method->get_option( 'cost' );
                if ( '' !== $cost && is_numeric( $cost ) ) {
                    $cached = floatval( $cost );
                    return $cached;
                }
            }
        }
    }
    return $cached;
}

// 32a-helper. Collect estimated shipping costs (label => cost) for the cart-page estimate display.
//
// 改讀「電商營運 > 運費管理」後台設定（見 includes/admin/shipping-management.php），
// 取代原本直接讀 WC_Shipping_Zones 方式設定成本的做法——後者只是各運送方式
// 自己的固定成本，跟結帳頁實際套用 chao_gang_cheng_apply_shipping_management_rates()
// 算出來的金額（依地區×溫層×件數分級距）已經對不起來，會出現購物車頁「預估運費」
// 跟結帳頁實收運費不一致的情況。這裡改成用同一套 lookup 邏輯計算，確保兩邊金額一致。
//
// 地區固定用本島（跟原本邏輯一樣，只反映常見情況，離島實際運費以結帳頁為準）；
// 溫層則依購物車目前商品的溫層交集判斷，件數用購物車商品總件數。
function chao_get_estimated_shipping_rates() {
    $rates = array();

    if ( ! function_exists( 'chao_gang_cheng_get_shipping_settings' ) || ! function_exists( 'chao_gang_cheng_lookup_shipping_fee' ) ) {
        return $rates; // 後台運費管理尚未載入，不顯示（避免顯示錯誤/過時金額）。
    }
    if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
        return $rates;
    }

    $settings = chao_gang_cheng_get_shipping_settings();

    $zone = 'ambient';
    if ( function_exists( 'chao_gang_cheng_determine_package_temperature_zone' ) ) {
        $zone = chao_gang_cheng_determine_package_temperature_zone( array( 'contents' => WC()->cart->get_cart() ) );
    }

    $qty = 0;
    foreach ( WC()->cart->get_cart() as $cart_item ) {
        $qty += isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 0;
    }

    $labels = array(
        'home_delivery' => '宅配',
        'cvs'           => '超商',
    );
    foreach ( $labels as $method_key => $label ) {
        $fee = chao_gang_cheng_lookup_shipping_fee( $method_key, 'main_island', $zone, $qty, $settings );
        if ( null === $fee ) {
            continue; // 這個組合後台還沒設定資料，不顯示，避免誤導。
        }
        $rates[ $label ] = $fee;
    }

    return $rates;
}

// 32a. Show estimated shipping between subtotal and total on the cart page
add_action( 'woocommerce_cart_totals_before_order_total', 'chao_cart_estimated_shipping_row' );
function chao_cart_estimated_shipping_row() {
    $threshold = chao_get_free_shipping_threshold();
    // 用折扣後金額比對門檻，理由見 functions.php: chao_get_free_shipping_progress_amount()
    $subtotal  = chao_get_free_shipping_progress_amount();

    // 檢查目前購物車是否已套用含「允許免運費」的折價券
    $coupon_free_shipping = false;
    if ( WC()->cart ) {
        foreach ( WC()->cart->get_applied_coupons() as $coupon_code ) {
            $coupon = new WC_Coupon( $coupon_code );
            if ( $coupon->get_id() && $coupon->get_free_shipping() ) {
                $coupon_free_shipping = true;
                break;
            }
        }
    }
    ?>
    <tr class="chao-est-shipping">
        <th>預估運費</th>
        <td data-title="預估運費">
            <?php if ( $coupon_free_shipping ) : ?>
                <strong style="color:#16a34a;">已套用免運優惠券，本次配送免運費！</strong>
            <?php elseif ( $subtotal >= $threshold ) : ?>
                <strong style="color:#16a34a;">免運費</strong>
            <?php else : ?>
                <?php
                $rates = chao_get_estimated_shipping_rates();
                if ( ! empty( $rates ) ) {
                    $parts = array();
                    foreach ( $rates as $title => $cost ) {
                        $parts[] = esc_html( $title ) . ' ' . wc_price( $cost );
                    }
                    echo '<span class="chao-est-shipping-rates">' . implode( '<span style="color:#c9a86c;">｜</span>', $parts ) . '</span>';
                }
                ?>
                <div style="font-size:12px;color:#8c7a64;margin-top:4px;">滿 <?php echo wc_price( $threshold ); ?> 免運，實際運費依結帳時選擇的物流方式計算</div>
            <?php endif; ?>
        </td>
    </tr>
    <?php
}

// 32b. "湊免運" cross-sell block: shown below the cart items when under the threshold
add_action( 'woocommerce_after_cart_table', 'chao_cart_free_shipping_cross_sell', 15 );
function chao_cart_free_shipping_cross_sell() {
    if ( ! WC()->cart || WC()->cart->is_empty() ) {
        return;
    }
    $threshold = chao_get_free_shipping_threshold();
    // 用折扣後金額比對門檻，理由見 functions.php: chao_get_free_shipping_progress_amount()
    $subtotal  = chao_get_free_shipping_progress_amount();
    if ( $subtotal >= $threshold ) {
        return;
    }
    $diff = $threshold - $subtotal;

    $exclude = array( 0 );
    foreach ( WC()->cart->get_cart() as $cart_item ) {
        $exclude[] = $cart_item['product_id'];
    }

    // Best sellers first; pick in-stock simple products whose price fits the gap (or a low-price cap)
    // 效能：候選商品池改讀取快取（見 chao_checkout_crosssell_pool_ids），避免購物車每次
    // AJAX 更新（加減數量、套券）都重跑一次 meta_value_num 排序查詢。
    $price_cap = max( $diff, 400 );
    $candidate_ids = chao_checkout_crosssell_pool_ids();
    // 溫層過濾：不推薦會跟購物車現有商品溫層衝突的加購品（例如購物車已
    // 有冷凍年菜，就不該推薦常溫零食），見 functions.php 的
    // chao_gang_cheng_get_cart_common_temperature_zones() 說明。
    $cart_zones = function_exists( 'chao_gang_cheng_get_cart_common_temperature_zones' )
        ? chao_gang_cheng_get_cart_common_temperature_zones()
        : null;

    $picks = array();
    foreach ( $candidate_ids as $product_id ) {
        if ( in_array( (int) $product_id, $exclude, true ) ) {
            continue;
        }
        $product = wc_get_product( $product_id );
        if ( ! $product || ! $product->is_type( 'simple' ) || ! $product->is_in_stock() || ! $product->is_purchasable() ) {
            continue;
        }
        $price = floatval( $product->get_price() );
        if ( $price <= 0 || $price > $price_cap ) {
            continue;
        }
        if ( function_exists( 'chao_gang_cheng_product_matches_cart_temperature_zone' )
            && ! chao_gang_cheng_product_matches_cart_temperature_zone( $product, $cart_zones ) ) {
            continue;
        }
        $picks[] = $product;
        if ( count( $picks ) >= 4 ) {
            break;
        }
    }

    if ( count( $picks ) < 2 ) {
        return;
    }
    ?>
    <div class="chao-cart-cross-sell">
        <div class="chao-cart-cross-sell-title">還差 <strong><?php echo wc_price( $diff ); ?></strong> 免運，加購這些剛剛好 👇</div>
        <div class="chao-cart-cross-sell-grid">
            <?php foreach ( $picks as $product ) : ?>
                <div class="chao-cart-cross-sell-item">
                    <a href="<?php echo esc_url( $product->get_permalink() ); ?>" class="chao-cross-sell-thumb">
                        <?php echo $product->get_image( 'woocommerce_thumbnail' ); ?>
                    </a>
                    <a href="<?php echo esc_url( $product->get_permalink() ); ?>" class="chao-cross-sell-name"><?php echo esc_html( $product->get_name() ); ?></a>
                    <span class="chao-cross-sell-price"><?php echo $product->get_price_html(); ?></span>
                    <a href="<?php echo esc_url( $product->add_to_cart_url() ); ?>" data-quantity="1"
                       class="button add_to_cart_button ajax_add_to_cart chao-cross-sell-add"
                       data-product_id="<?php echo esc_attr( $product->get_id() ); ?>" rel="nofollow">＋ 加入購物車</a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

/**
 * 效能重構：結帳頁加購商品的「候選商品池」改用 transient 快取。
 *
 * 舊做法：每次結帳頁 AJAX 更新（update_checkout，例如套用優惠券、改數量、
 * 換運送方式）都會重新執行一次 meta_key=total_sales / orderby=meta_value_num
 * 的 WP_Query —— 這種依 postmeta 數值排序的查詢在 WooCommerce 是公認較重的
 * 查詢方式，等於每次套券都要多付出一次不必要的重量級 DB 查詢成本。
 *
 * 新做法：熱銷商品 ID 池每 10 分鐘才重新查詢一次並快取；每次結帳頁更新只從
 * 快取的 ID 池讀取，再依當下購物車內容（排除已在購物車的商品、價格上限、
 * 庫存）做輕量篩選，不再重複打那支重查詢。
 */
function chao_checkout_crosssell_pool_ids() {
    $cache_key = 'ckc_checkout_crosssell_pool_v1';
    $ids = get_transient( $cache_key );
    if ( false !== $ids && is_array( $ids ) ) {
        return $ids;
    }

    $query = new WP_Query( array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 40,
        'meta_key'       => 'total_sales',
        'orderby'        => 'meta_value_num',
        'order'          => 'DESC',
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ) );
    $ids = $query->posts;
    wp_reset_postdata();

    set_transient( $cache_key, $ids, 10 * MINUTE_IN_SECONDS );
    return $ids;
}

// 32c-2. 結帳頁：未達免運門檻時顯示加購商品區（AJAX 加入，不整頁重載）
add_action( 'woocommerce_checkout_before_order_review', 'chao_checkout_free_shipping_cross_sell', 20 );
function chao_checkout_free_shipping_cross_sell() {
    if ( ! WC()->cart || WC()->cart->is_empty() ) {
        return;
    }
    $threshold = chao_get_free_shipping_threshold();
    // 用折扣後金額比對門檻，理由見 functions.php: chao_get_free_shipping_progress_amount()
    $subtotal  = chao_get_free_shipping_progress_amount();
    if ( $subtotal >= $threshold ) {
        return;
    }
    $diff = $threshold - $subtotal;

    $exclude = array( 0 );
    foreach ( WC()->cart->get_cart() as $cart_item ) {
        $exclude[] = $cart_item['product_id'];
    }
    $price_cap = max( $diff, 400 );
    // 溫層過濾：跟購物車頁 chao_cart_free_shipping_cross_sell() 用同一套邏輯，
    // 不推薦會跟購物車現有商品溫層衝突的加購品。
    $cart_zones = function_exists( 'chao_gang_cheng_get_cart_common_temperature_zones' )
        ? chao_gang_cheng_get_cart_common_temperature_zones()
        : null;

    $candidate_ids = chao_checkout_crosssell_pool_ids();
    $picks = array();
    foreach ( $candidate_ids as $product_id ) {
        if ( in_array( (int) $product_id, $exclude, true ) ) {
            continue;
        }
        $product = wc_get_product( $product_id );
        if ( ! $product || ! $product->is_type( 'simple' ) || ! $product->is_in_stock() || ! $product->is_purchasable() ) {
            continue;
        }
        $price = floatval( $product->get_price() );
        if ( $price <= 0 || $price > $price_cap ) {
            continue;
        }
        if ( function_exists( 'chao_gang_cheng_product_matches_cart_temperature_zone' )
            && ! chao_gang_cheng_product_matches_cart_temperature_zone( $product, $cart_zones ) ) {
            continue;
        }
        $picks[] = $product;
        if ( count( $picks ) >= 4 ) {
            break;
        }
    }
    if ( count( $picks ) < 2 ) {
        return;
    }
    ?>
    <div class="chao-checkout-cross-sell" data-threshold="<?php echo (int) $threshold; ?>" style="margin-bottom:20px;padding:16px;background:#fffaf1;border:1px solid #e2d2b3;border-radius:10px;">
        <div style="font-size:14px;font-weight:700;color:#3a2f24;margin-bottom:12px;">還差 <strong style="color:#f86f69;"><?php echo wc_price( $diff ); ?></strong> 免運，加購這些剛剛好 👇</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px;">
            <?php foreach ( $picks as $product ) : ?>
                <div style="display:flex;flex-direction:column;gap:6px;text-align:center;">
                    <a href="<?php echo esc_url( $product->get_permalink() ); ?>" style="display:block;"><?php echo $product->get_image( 'woocommerce_thumbnail' ); ?></a>
                    <a href="<?php echo esc_url( $product->get_permalink() ); ?>" style="font-size:13px;color:#1a140f;text-decoration:none;line-height:1.4;min-height:36px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"><?php echo esc_html( $product->get_name() ); ?></a>
                    <span style="font-size:14px;font-weight:700;color:#f86f69;"><?php echo $product->get_price_html(); ?></span>
                    <button type="button" class="chao-checkout-crosssell-add" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>" style="border:1px solid #c9974a;background:#fff;color:#1a140f;border-radius:16px;padding:7px 10px;font-size:12px;font-weight:700;cursor:pointer;">＋ 加入</button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

// 32c-3. 結帳頁加購 AJAX（Store API add-item）+ toast + 更新結帳金額，不重載
add_action( 'wp_footer', 'chao_checkout_cross_sell_script' );
function chao_checkout_cross_sell_script() {
    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url() ) {
        return;
    }
    ?>
    <style>
    #ckc-coupon-toast{ position:fixed; left:50%; bottom:84px; transform:translateX(-50%) translateY(20px); background:#16a34a; color:#fff; padding:14px 24px; border-radius:30px; font-size:15px; font-weight:700; z-index:2147483000; max-width:88vw; text-align:center; box-shadow:0 8px 24px rgba(0,0,0,.25); opacity:0; pointer-events:none; transition:opacity .25s ease, transform .25s ease; }
    #ckc-coupon-toast.ckc-show{ opacity:1; transform:translateX(-50%) translateY(0); }
    </style>
    <script>
    jQuery(function($){
        function chaoToast(msg){
            var $t = $('#ckc-coupon-toast');
            if(!$t.length){ $t = $('<div id="ckc-coupon-toast" role="status" aria-live="polite"></div>').appendTo('body'); }
            $t.text(msg).css('background','#16a34a');
            requestAnimationFrame(function(){ $t.addClass('ckc-show'); });
            clearTimeout(window._ckcToastTimer);
            window._ckcToastTimer = setTimeout(function(){ $t.removeClass('ckc-show'); }, 1500);
        }
        $(document).on('click', '.chao-checkout-crosssell-add', function(e){
            e.preventDefault();
            var $btn = $(this), pid = parseInt($btn.data('product-id'), 10);
            if(!pid){ return; }
            $btn.prop('disabled', true).css('opacity', .6);
            fetch('/wp-json/wc/store/cart', {credentials:'include'})
                .then(function(r){ return r.headers.get('Nonce'); })
                .then(function(nonce){
                    return fetch('/wp-json/wc/store/v1/cart/add-item', {
                        method:'POST', credentials:'include',
                        headers:{'Content-Type':'application/json','Nonce': nonce || ''},
                        body: JSON.stringify({ id: pid, quantity: 1 })
                    }).then(function(r){ return r.json().then(function(d){ return { ok:r.ok, data:d }; }); });
                })
                .then(function(res){
                    if(res.ok){
                        chaoToast('已加入購物車');
                        // 若加入後已達免運門檻，收起整個加購區
                        var $wrap = $('.chao-checkout-cross-sell');
                        var th = parseInt($wrap.data('threshold'), 10) || 0;
                        var sub = parseInt((res.data && res.data.totals && res.data.totals.total_items) || '0', 10);
                        if(th > 0 && sub >= th){ $wrap.slideUp(220); }
                        $(document.body).trigger('update_checkout');
                    } else {
                        chaoToast((res.data && res.data.message) ? res.data.message : '加入失敗，請稍後再試');
                        $btn.prop('disabled', false).css('opacity', 1);
                    }
                })
                .catch(function(){ $btn.prop('disabled', false).css('opacity', 1); });
        });
    });
    </script>
    <?php
}

// 32c. Continue-shopping link inside the cart actions row
add_action( 'woocommerce_cart_actions', 'chao_cart_continue_shopping_link' );
function chao_cart_continue_shopping_link() {
    echo '<a class="chao-continue-shopping" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">← 繼續購物</a>';
}

// 32d. Trust badges + policy link under the proceed-to-checkout button
add_action( 'woocommerce_proceed_to_checkout', 'chao_cart_trust_badges', 30 );
function chao_cart_trust_badges() {
    ?>
    <div class="chao-cart-trust">
        <span>🔒 綠界科技 SSL 安全加密付款：VISA・MasterCard・JCB・LINE Pay</span>
        <a href="<?php echo esc_url( home_url( '/shipping-policy/' ) ); ?>">配送與運費政策</a>
    </div>
    <?php
}

// 32e. Cart page JS/CSS: auto quantity recalculation, live progress bar, mobile sticky checkout bar
add_action( 'wp_footer', 'chao_cart_ux_footer_assets' );
function chao_cart_ux_footer_assets() {
    if ( ! is_cart() ) {
        return;
    }
    ?>
    <style>
    /* Continue shopping link */
    .chao-continue-shopping { display: inline-block; margin-right: 12px; color: #8c7a64; text-decoration: none; font-size: 14px; line-height: 38px; }
    .chao-continue-shopping:hover { color: #1a140f; text-decoration: underline; }
    /* De-emphasize the now-automatic update button */
    .woocommerce-cart-form button[name="update_cart"] { opacity: 0.45; }
    /* Trust badges */
    .chao-cart-trust { margin-top: 12px; text-align: center; font-size: 12px; color: #8c7a64; line-height: 1.7; }
    .chao-cart-trust a { color: #8c7a64; text-decoration: underline; margin-left: 8px; }
    /* Cross-sell block */
    .chao-cart-cross-sell { margin: 20px 0; padding: 18px; background: #fffaf1; border: 1px solid #e2d2b3; border-radius: 10px; }
    .chao-cart-cross-sell-title { font-size: 15px; font-weight: 600; color: #3a2f24; margin-bottom: 14px; }
    .chao-cart-cross-sell-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
    .chao-cart-cross-sell-item { display: flex; flex-direction: column; align-items: center; text-align: center; gap: 6px; }
    .chao-cart-cross-sell-item .chao-cross-sell-thumb img { width: 100%; height: auto; border-radius: 8px; display: block; }
    .chao-cart-cross-sell-item .chao-cross-sell-name { font-size: 13px; color: #1a140f; text-decoration: none; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 36px; }
    .chao-cart-cross-sell-item .chao-cross-sell-price { font-size: 14px; font-weight: 700; color: #f86f69; }
    .chao-cart-cross-sell-item .chao-cross-sell-add { font-size: 13px; padding: 6px 12px; width: 100%; text-align: center; border: 1px solid #c9974a !important; background: #fff !important; color: #1a140f !important; }
    @media (max-width: 768px) {
        .chao-cart-cross-sell-grid { grid-template-columns: repeat(2, 1fr); }
    }
    /* Mobile sticky checkout bar */
    #chao-cart-sticky-bar { display: none; }
    @media (max-width: 768px) {
        #chao-cart-sticky-bar {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            position: fixed; bottom: 56px; left: 0; right: 0; z-index: 99998;
            background: #fffaf1; border-top: 1px solid #e2d2b3;
            box-shadow: 0 -4px 20px rgba(26,20,15,0.1); padding: 10px 16px; box-sizing: border-box;
        }
        #chao-cart-sticky-bar .chao-cart-sticky-info { display: flex; flex-direction: column; line-height: 1.3; }
        #chao-cart-sticky-bar .chao-cart-sticky-info span { font-size: 12px; color: #8c7a64; }
        #chao-cart-sticky-bar .chao-cart-sticky-info strong { font-size: 18px; color: #f86f69; }
        #chao-cart-sticky-bar .chao-cart-sticky-btn {
            flex: 1; max-width: 220px; text-align: center; background-color: #f86f69;
            color: #fff; border-radius: 24px; padding: 12px 18px; font-size: 15px; font-weight: 700; text-decoration: none;
        }
        body.woocommerce-cart { padding-bottom: 130px !important; }
    }
    </style>
    <?php if ( WC()->cart && ! WC()->cart->is_empty() ) : ?>
    <div id="chao-cart-sticky-bar">
        <div class="chao-cart-sticky-info">
            <span>總計</span>
            <strong id="chao-cart-sticky-total"></strong>
        </div>
        <a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" class="chao-cart-sticky-btn">前往結帳</a>
    </div>
    <?php endif; ?>
    <script>
    jQuery(function($) {
        // 1. Auto quantity recalculation: debounce, then programmatically press "update cart"
        //    (Baymard: don't require an explicit update button click)
        var chaoQtyTimer = null;
        $(document.body).on('change input', '.woocommerce-cart-form input.qty', function() {
            clearTimeout(chaoQtyTimer);
            chaoQtyTimer = setTimeout(function() {
                var $btn = $('.woocommerce-cart-form button[name="update_cart"]');
                if ($btn.length) {
                    $btn.prop('disabled', false).attr('aria-disabled', 'false').trigger('click');
                }
            }, 600);
        });

        // 2. 免運進度條（.cart-shipping-progress-wrapper）現在已經改用
        //    woocommerce_add_to_cart_fragments 讓它跟著購物車表單一起用 AJAX
        //    更新（見 functions.php: chao_gang_cheng_cart_fragments()），
        //    不再需要這裡另外寫 JS 解析畫面文字金額自行重算——原本那個做法
        //    讀到的是折扣「前」的小計，是「購物車顯示免運、結帳頁卻收運費」
        //    問題的根因，改用 fragment 直接重用同一段 PHP 邏輯render，
        //    保證跟結帳頁的免運判斷基準一致。

        // 3. Mobile sticky bar: mirror the cart total, hide when the cart becomes empty
        function chaoSyncStickyBar() {
            var $bar = $('#chao-cart-sticky-bar');
            if (!$bar.length) { return; }
            if (!$('.woocommerce-cart-form').length) {
                $bar.hide();
                return;
            }
            var totalText = $('.cart_totals .order-total .woocommerce-Price-amount').last().text().trim();
            if (totalText) { $('#chao-cart-sticky-total').text(totalText); }
        }

        chaoSyncStickyBar();
        $(document.body).on('updated_cart_totals updated_wc_div wc_fragments_refreshed', function() {
            chaoSyncStickyBar();
        });

        // 4. After an AJAX add-to-cart from the cross-sell block, reload so
        //    items, totals, progress bar and recommendations all refresh together
        $(document.body).on('added_to_cart', function() {
            location.reload();
        });
    });
    </script>
    <?php
}
