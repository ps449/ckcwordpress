<?php
/**
 * CKC Coupons：CyberBiz 風格折扣券（前台領券中心＋後台券管理欄位）
 *
 *  - 啟用 WooCommerce 原生折扣碼（購物車／結帳出現輸入框）
 *  - 後台「行銷 > 折價券」編輯頁新增：顯示於領券中心、券面標題
 *  - 購物車頁領券中心：券卡片（面額、低消、效期）＋一鍵套用
 *  - 我的帳號「專屬優惠券」頁：可領券列表與已套用狀態
 *
 * 券的金額、類型、低消、效期、次數限制全部沿用 WooCommerce 原生設定。
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ---------------- 啟用原生折扣碼 ---------------- */
add_filter( 'woocommerce_coupons_enabled', '__return_true', 20 );
add_filter( 'option_woocommerce_enable_coupons', 'ckc_force_enable_coupons_option', 20 );
add_filter( 'pre_option_woocommerce_enable_coupons', 'ckc_force_enable_coupons_option', 20 );
function ckc_force_enable_coupons_option( $value ) {
    return 'yes';
}

add_action( 'admin_init', 'ckc_ensure_coupons_enabled_in_db' );
function ckc_ensure_coupons_enabled_in_db() {
    if ( get_option( 'woocommerce_enable_coupons' ) !== 'yes' ) {
        update_option( 'woocommerce_enable_coupons', 'yes' );
    }
}

/* ---------------- 後台：券編輯頁欄位 ---------------- */
add_action( 'woocommerce_coupon_options', 'ckc_coupon_admin_fields', 20, 2 );
function ckc_coupon_admin_fields( $coupon_id, $coupon ) {
    woocommerce_wp_checkbox( array(
        'id'          => '_ckc_coupon_public',
        'label'       => '顯示於領券中心',
        'description' => '勾選後，此券會出現在購物車「領券中心」與會員「專屬優惠券」頁，消費者可一鍵套用',
    ) );
    woocommerce_wp_text_field( array(
        'id'          => '_ckc_coupon_label',
        'label'       => '券面標題',
        'placeholder' => '例如：新客見面禮',
        'description' => '顯示在券卡片上的標題；留空則顯示折扣內容',
        'desc_tip'    => true,
    ) );
}
add_action( 'woocommerce_coupon_options_save', 'ckc_coupon_admin_fields_save', 20, 2 );
function ckc_coupon_admin_fields_save( $coupon_id, $coupon ) {
    $public = isset( $_POST['_ckc_coupon_public'] ) ? 'yes' : 'no';
    $label  = isset( $_POST['_ckc_coupon_label'] ) ? sanitize_text_field( wp_unslash( $_POST['_ckc_coupon_label'] ) ) : '';
    
    update_post_meta( $coupon_id, '_ckc_coupon_public', $public );
    update_post_meta( $coupon_id, '_ckc_coupon_label', $label );
}

/* ---------------- 公開券查詢 ---------------- */
function ckc_get_public_coupons() {
    $posts = get_posts( array(
        'post_type'   => 'shop_coupon',
        'post_status' => 'publish',
        'numberposts' => 20,
        'meta_key'    => '_ckc_coupon_public',
        'meta_value'  => 'yes',
        'orderby'     => 'date',
        'order'       => 'DESC',
    ) );
    $coupons = array();
    foreach ( $posts as $post ) {
        $coupon = new WC_Coupon( $post->post_title );
        if ( ! $coupon->get_id() ) {
            continue;
        }
        // 過期或總次數用罄者不顯示
        $expires = $coupon->get_date_expires();
        if ( $expires && $expires->getTimestamp() < time() ) {
            continue;
        }
        $limit = $coupon->get_usage_limit();
        if ( $limit && $coupon->get_usage_count() >= $limit ) {
            continue;
        }
        $coupons[] = $coupon;
    }
    return $coupons;
}

// 券值文字（例：92 折／折 NT$100／免運費）
function ckc_coupon_value_text( $coupon ) {
    $amount = floatval( $coupon->get_amount() );
    $type   = $coupon->get_discount_type();
    $parts  = array();
    if ( 'percent' === $type && $amount > 0 ) {
        $off = 100 - $amount;
        $parts[] = ( 0 === (int) ( $off % 10 ) && $off > 0 ) ? ( ( $off / 10 ) . ' 折' ) : ( rtrim( rtrim( number_format( $off / 10, 1 ), '0' ), '.' ) . ' 折' );
    } elseif ( $amount > 0 ) {
        $parts[] = '折 NT$' . number_format( $amount );
    }
    if ( $coupon->get_free_shipping() ) {
        $parts[] = '免運費';
    }
    return $parts ? implode( '＋', $parts ) : '優惠券';
}

/* ---------------- 一鍵套用（?ckc_apply_coupon=CODE） ---------------- */
add_action( 'template_redirect', 'ckc_coupon_apply_from_url', 20 );
function ckc_coupon_apply_from_url() {
    if ( empty( $_GET['ckc_apply_coupon'] ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
        return;
    }
    $code   = wc_format_coupon_code( sanitize_text_field( wp_unslash( $_GET['ckc_apply_coupon'] ) ) );
    $coupon = new WC_Coupon( $code );
    // 僅允許套用「領券中心」的公開券，避免暴力猜碼
    if ( ! $coupon->get_id() || 'yes' !== $coupon->get_meta( '_ckc_coupon_public' ) ) {
        wp_safe_redirect( wc_get_cart_url() );
        exit;
    }
    if ( ! WC()->cart->has_discount( $code ) ) {
        WC()->cart->apply_coupon( $code ); // 失敗時 WooCommerce 會自行顯示原因通知
    }
    wp_safe_redirect( wc_get_cart_url() );
    exit;
}

/* ---------------- 券卡片渲染（購物車與帳號頁共用） ---------------- */
function ckc_render_coupon_cards( $context = 'cart' ) {
    $coupons = ckc_get_public_coupons();
    if ( empty( $coupons ) ) {
        if ( 'account' === $context ) {
            echo '<p style="color:#64748b;font-size:14px;">目前沒有可領取的優惠券，新券上架會顯示在這裡，敬請期待！</p>';
        }
        return;
    }
    $in_cart_page = function_exists( 'is_cart' ) && is_cart();
    ?>
    <div class="ckc-coupon-grid">
        <?php foreach ( $coupons as $coupon ) :
            $code    = $coupon->get_code();
            $label   = $coupon->get_meta( '_ckc_coupon_label' );
            $value   = ckc_coupon_value_text( $coupon );
            $min     = floatval( $coupon->get_minimum_amount() );
            $expires = $coupon->get_date_expires();
            $applied = WC()->cart ? WC()->cart->has_discount( $code ) : false;
            $apply_url = add_query_arg( 'ckc_apply_coupon', rawurlencode( $code ), wc_get_cart_url() );
            ?>
            <div class="ckc-coupon-card<?php echo $applied ? ' is-applied' : ''; ?>">
                <div class="ckc-coupon-left">
                    <div class="ckc-coupon-value"><?php echo esc_html( $value ); ?></div>
                    <?php if ( $min > 0 ) : ?>
                        <div class="ckc-coupon-min">低消 NT$<?php echo esc_html( number_format( $min ) ); ?></div>
                    <?php endif; ?>
                </div>
                <div class="ckc-coupon-body">
                    <div class="ckc-coupon-title"><?php echo esc_html( $label ? $label : $value ); ?></div>
                    <div class="ckc-coupon-meta">
                        代碼 <code><?php echo esc_html( strtoupper( $code ) ); ?></code>
                        <?php if ( $expires ) : ?>
                            ・<?php echo esc_html( $expires->date_i18n( 'Y/m/d' ) ); ?> 前有效
                        <?php endif; ?>
                    </div>
                </div>
                <div class="ckc-coupon-action">
                    <?php if ( $applied ) : ?>
                        <span class="ckc-coupon-applied">✓ 已套用</span>
                    <?php else : ?>
                        <a href="<?php echo esc_url( $apply_url ); ?>" class="ckc-coupon-apply"><?php echo $in_cart_page ? '立即套用' : '套用去結帳'; ?></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <style>
    .ckc-coupon-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 12px; margin: 14px 0; }
    .ckc-coupon-card { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px dashed #d6a878; border-radius: 10px; padding: 12px 14px; }
    .ckc-coupon-card.is-applied { border-style: solid; border-color: #16a34a; background: #f0fdf4; }
    .ckc-coupon-left { text-align: center; min-width: 76px; border-right: 1px dashed #e2e8f0; padding-right: 12px; }
    .ckc-coupon-value { font-size: 17px; font-weight: 800; color: #b91c1c; line-height: 1.3; }
    .ckc-coupon-min { font-size: 11px; color: #94a3b8; margin-top: 2px; }
    .ckc-coupon-body { flex: 1; min-width: 0; }
    .ckc-coupon-title { font-size: 14px; font-weight: 700; color: #1e293b; }
    .ckc-coupon-meta { font-size: 12px; color: #64748b; margin-top: 3px; }
    .ckc-coupon-meta code { background: #f1f5f9; padding: 1px 6px; border-radius: 4px; }
    .ckc-coupon-apply { display: inline-block; background: #7f6c60; color: #fff !important; border-radius: 16px; padding: 7px 16px; font-size: 12px; font-weight: 700; text-decoration: none; white-space: nowrap; }
    .ckc-coupon-applied { color: #16a34a; font-size: 13px; font-weight: 700; white-space: nowrap; }
    </style>
    <?php
}

/* ---------------- 購物車頁：領券中心 ---------------- */
add_action( 'woocommerce_before_cart', 'ckc_cart_coupon_center', 15 );
function ckc_cart_coupon_center() {
    $coupons = ckc_get_public_coupons();
    if ( empty( $coupons ) ) {
        return;
    }
    echo '<div class="ckc-coupon-center" style="margin-bottom: 20px;">';
    echo '<div style="font-size: 15px; font-weight: 700; color: #334155; margin-bottom: 4px;">🎟️ 領券中心</div>';
    ckc_render_coupon_cards( 'cart' );
    echo '</div>';
}

/* ---------------- 我的帳號「專屬優惠券」頁 ---------------- */
add_action( 'init', 'ckc_coupons_register_endpoint', 6 );
function ckc_coupons_register_endpoint() {
    add_rewrite_endpoint( 'coupons', EP_ROOT | EP_PAGES );
    if ( '1' !== get_option( 'ckc_coupons_rewrite_flushed' ) ) {
        flush_rewrite_rules();
        update_option( 'ckc_coupons_rewrite_flushed', '1' );
    }
}

add_filter( 'woocommerce_account_menu_items', 'ckc_coupons_account_menu_item', 25 );
function ckc_coupons_account_menu_item( $items ) {
    $new = array();
    foreach ( $items as $key => $label ) {
        $new[ $key ] = $label;
        if ( 'points' === $key ) {
            $new['coupons'] = '專屬優惠券';
        }
    }
    if ( ! isset( $new['coupons'] ) ) {
        $new['coupons'] = '專屬優惠券';
    }
    return $new;
}

add_filter( 'woocommerce_endpoint_coupons_title', function () {
    return '專屬優惠券';
} );

add_action( 'woocommerce_account_coupons_endpoint', 'ckc_coupons_account_content' );
function ckc_coupons_account_content() {
    ?>
    <div style="background: #fdfaf7; border: 1px solid #f5ebe6; border-radius: 10px; padding: 16px 18px; margin-bottom: 18px;">
        <p style="margin: 0; font-size: 14px; color: #6b7280; line-height: 1.8;">
            以下優惠券皆可直接使用——點「套用去結帳」會自動套入購物車；也可以在結帳時手動輸入折扣碼。
        </p>
    </div>
    <?php
    ckc_render_coupon_cards( 'account' );
}
