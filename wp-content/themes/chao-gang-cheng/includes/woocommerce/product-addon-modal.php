<?php
/**
 * 加購商品規格選擇彈跳視窗（Add-on Spec Modal）模組
 *
 * 當客人在購物車或結帳頁面「加購湊免運區」點選具備規格選項（自訂規格或多規格變體）
 * 的商品時，自動彈出精緻規格挑選視窗，供客人選定完整規格後自動加入購物車並即時更新訂單。
 *
 * @package ChaoGangCheng
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 檢查商品是否具備規格選項（自訂規格類別或 WooCommerce 多規格變體）
 *
 * @param int|WC_Product $product
 * @return bool
 */
function chao_product_has_specs_or_variations( $product ) {
    if ( ! $product instanceof WC_Product ) {
        $product = wc_get_product( $product );
    }
    if ( ! $product ) {
        return false;
    }

    // 1. 檢查 WooCommerce 原生多規格變體
    if ( $product->is_type( 'variable' ) ) {
        return true;
    }

    // 2. 檢查主題自訂二層規格選項
    if ( function_exists( 'chao_gang_cheng_get_spec_categories' ) ) {
        $categories = chao_gang_cheng_get_spec_categories( $product->get_id() );
        if ( ! empty( $categories ) && is_array( $categories ) ) {
            return true;
        }
    }

    return false;
}

/**
 * AJAX 端點 1：取得加購商品之規格詳細資訊與彈窗內容
 */
add_action( 'wp_ajax_ckc_get_addon_modal_data', 'ckc_ajax_get_addon_modal_data' );
add_action( 'wp_ajax_nopriv_ckc_get_addon_modal_data', 'ckc_ajax_get_addon_modal_data' );
function ckc_ajax_get_addon_modal_data() {
    $product_id = isset( $_REQUEST['product_id'] ) ? absint( $_REQUEST['product_id'] ) : 0;
    if ( ! $product_id ) {
        wp_send_json_error( array( 'message' => '缺少商品編號。' ) );
    }

    $product = wc_get_product( $product_id );
    if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
        wp_send_json_error( array( 'message' => '此商品目前無法選購或已售完。' ) );
    }

    $base_price    = (float) $product->get_price();
    $image_url     = wp_get_attachment_image_url( $product->get_image_id(), 'medium' );
    if ( ! $image_url ) {
        $image_url = wc_placeholder_img_src( 'medium' );
    }

    $has_specs = false;
    $spec_type = 'simple';
    $categories = array();
    $combinations = array();
    $variations = array();

    // 檢查自訂規格
    if ( function_exists( 'chao_gang_cheng_get_spec_categories' ) ) {
        $custom_cats = chao_gang_cheng_get_spec_categories( $product_id );
        if ( ! empty( $custom_cats ) ) {
            $has_specs    = true;
            $spec_type    = 'ckc_spec';
            $categories   = $custom_cats;
            $combinations = function_exists( 'chao_gang_cheng_get_spec_combinations' )
                ? chao_gang_cheng_get_spec_combinations( $product_id )
                : array();
        }
    }

    // 檢查 WooCommerce 原生變體
    if ( ! $has_specs && $product->is_type( 'variable' ) ) {
        $has_specs  = true;
        $spec_type  = 'wc_variable';
        $variations = $product->get_available_variations();
        $raw_attrs  = $product->get_variation_attributes();
        foreach ( $raw_attrs as $attr_name => $options ) {
            $cat_values = array();
            foreach ( $options as $opt ) {
                $term = get_term_by( 'slug', $opt, $attr_name );
                $cat_values[] = array(
                    'id'    => $opt,
                    'label' => $term ? $term->name : $opt,
                );
            }
            $categories[] = array(
                'id'     => $attr_name,
                'label'  => wc_attribute_label( $attr_name ),
                'values' => $cat_values,
            );
        }
    }

    wp_send_json_success( array(
        'product_id'   => $product_id,
        'title'        => $product->get_name(),
        'image'        => $image_url,
        'base_price'   => $base_price,
        'price_html'   => $product->get_price_html(),
        'has_specs'    => $has_specs,
        'spec_type'    => $spec_type,
        'categories'   => $categories,
        'combinations' => $combinations,
        'variations'   => $variations,
    ) );
}

/**
 * AJAX 端點 2：處理加購商品加入購物車（支援自訂規格與原生變體）
 */
add_action( 'wp_ajax_ckc_add_addon_to_cart', 'ckc_ajax_add_addon_to_cart' );
add_action( 'wp_ajax_nopriv_ckc_add_addon_to_cart', 'ckc_ajax_add_addon_to_cart' );
function ckc_ajax_add_addon_to_cart() {
    // 確保購物車與 Session 環境完整載入
    if ( function_exists( 'wc_load_cart' ) && null === WC()->cart ) {
        wc_load_cart();
    }
    if ( function_exists( 'WC' ) && WC()->session && ! WC()->session->has_session() ) {
        WC()->session->set_customer_session_cookie( true );
    }

    $product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
    $quantity     = isset( $_POST['quantity'] ) ? max( 1, absint( $_POST['quantity'] ) ) : 1;
    $variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
    $variation    = isset( $_POST['variation'] ) && is_array( $_POST['variation'] ) ? $_POST['variation'] : array();

    if ( ! $product_id ) {
        wp_send_json_error( array( 'message' => '缺少商品編號。' ) );
    }

    $product = wc_get_product( $product_id );
    if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
        wp_send_json_error( array( 'message' => '此商品目前無法選購或已售完。' ) );
    }

    // 傳入 ckc_spec_selected 以供 woocommerce_add_cart_item_data 攔截
    if ( isset( $_POST['ckc_spec_selected'] ) && is_array( $_POST['ckc_spec_selected'] ) ) {
        $_POST['ckc_spec_selected'] = wp_unslash( $_POST['ckc_spec_selected'] );
    }

    // 2026-08-30 重要修正：WC_Cart::add_to_cart() 這個類別方法本身並不會
    // 觸發 woocommerce_add_to_cart_validation 這個 filter——實際觸發點只在
    // WooCommerce 自己的原生進入點（class-wc-ajax.php 的原生加入購物車
    // AJAX、class-wc-form-handler.php 的表單送出處理），直接呼叫這個類別
    // 方法（像這裡）完全繞過驗證。這是實測用 wp-cli 直接呼叫
    // WC()->cart->add_to_cart() 才發現的，一開始以為溫層驗證 filter 沒生效，
    // 後來才確認是這裡完全沒有觸發過 filter，不是 filter 本身寫錯。因此這裡
    // 必須自己手動 apply_filters() 一次，重現 WooCommerce 原生進入點的行為，
    // 這支端點正是 spec-同溫層限制與運費顯示修正.md §5.1 第 3 點明確點名
    // 必須驗證的入口（購物車頁「湊免運加購推薦」／規格選擇彈窗）。
    $passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variation );
    $cart_item_key      = $passed_validation ? WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation ) : false;

    if ( $cart_item_key ) {
        WC()->cart->calculate_totals();

        // 確保 Session 與 Cookie 立即持久化儲存
        if ( WC()->session ) {
            WC()->session->save_data();
        }
        if ( WC()->cart ) {
            WC()->cart->maybe_set_cart_cookies();
        }

        $threshold = function_exists( 'chao_get_free_shipping_threshold' ) ? chao_get_free_shipping_threshold() : 0;
        $subtotal  = function_exists( 'chao_get_free_shipping_progress_amount' ) ? chao_get_free_shipping_progress_amount() : (float) WC()->cart->get_cart_contents_total();

        $fragments = apply_filters( 'woocommerce_add_to_cart_fragments', array() );

        wp_send_json_success( array(
            'message'       => sprintf( '已成功加購「%s」！', esc_html( $product->get_name() ) ),
            'cart_item_key' => $cart_item_key,
            'cart_count'    => WC()->cart->get_cart_contents_count(),
            'threshold'     => $threshold,
            'subtotal'      => $subtotal,
            'reached_free'  => ( $threshold > 0 && $subtotal >= $threshold ),
            'fragments'     => $fragments,
            'cart_hash'     => WC()->cart->get_cart_hash(),
        ) );
    } else {
        // 抓取 WooCommerce 驗證錯誤訊息
        $notices = wc_get_notices( 'error' );
        $err_msg = '加入購物車失敗，請稍後再試。';
        if ( ! empty( $notices ) ) {
            $messages = array();
            foreach ( $notices as $notice ) {
                $messages[] = wp_strip_all_tags( is_array( $notice ) ? $notice['notice'] : $notice );
            }
            $err_msg = implode( ' ', $messages );
            wc_clear_notices();
        }

        // 2026-08-30 新增（spec-同溫層限制與運費顯示修正.md 第一階段）：獨立
        // 重新判斷這次失敗是不是溫層衝突造成的，不依賴解析錯誤文字字串——
        // 讓前端可以精準地只在這個情況顯示「清空購物車，改買此商品」的專屬
        // 攔截彈窗，其他失敗原因（例如缺貨、售完）維持原本的一般 toast 提示。
        $temperature_conflict = false;
        if ( function_exists( 'chao_gang_cheng_get_cart_common_temperature_zones' ) && function_exists( 'chao_gang_cheng_product_matches_cart_temperature_zone' ) ) {
            $cart_zones            = chao_gang_cheng_get_cart_common_temperature_zones();
            $temperature_conflict  = ! chao_gang_cheng_product_matches_cart_temperature_zone( $product, $cart_zones );
        }

        wp_send_json_error( array(
            'message'              => $err_msg,
            'temperature_conflict' => $temperature_conflict,
        ) );
    }
}

/**
 * 於前台 Footer 載入加購規格彈窗 HTML 結構、CSS 樣式與互動 JavaScript
 */
add_action( 'wp_footer', 'ckc_render_addon_spec_modal_markup', 40 );
function ckc_render_addon_spec_modal_markup() {
    if ( is_admin() ) {
        return;
    }
    ?>
    <!-- 加購商品規格選擇彈跳視窗 -->
    <div id="ckc-addon-spec-modal" class="ckc-addon-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="ckc-addon-modal-title">
        <div class="ckc-addon-modal-overlay"></div>
        <div class="ckc-addon-modal-dialog">
            <button type="button" class="ckc-addon-modal-close" aria-label="關閉視窗">&times;</button>
            <div class="ckc-addon-modal-content">
                <div class="ckc-addon-modal-header">
                    <div class="ckc-addon-modal-thumb">
                        <img id="ckc-addon-modal-img" src="" alt="商品圖片">
                    </div>
                    <div class="ckc-addon-modal-info">
                        <h3 id="ckc-addon-modal-title" class="ckc-addon-modal-product-title"></h3>
                        <div id="ckc-addon-modal-price" class="ckc-addon-modal-price-box"></div>
                    </div>
                </div>

                <div class="ckc-addon-modal-body">
                    <div id="ckc-addon-modal-groups" class="ckc-addon-spec-groups"></div>
                    
                    <div id="ckc-addon-modal-stock-warn" class="ckc-addon-stock-warning" style="display: none;"></div>

                    <div class="ckc-addon-qty-row">
                        <label>購買數量：</label>
                        <div class="ckc-addon-qty-stepper">
                            <button type="button" class="ckc-addon-qty-btn ckc-addon-qty-minus">−</button>
                            <input type="number" id="ckc-addon-qty-input" class="ckc-addon-qty-field" value="1" min="1" max="99" readonly>
                            <button type="button" class="ckc-addon-qty-btn ckc-addon-qty-plus">＋</button>
                        </div>
                    </div>
                </div>

                <div class="ckc-addon-modal-footer">
                    <button type="button" id="ckc-addon-modal-submit" class="ckc-addon-modal-submit-btn">
                        <span class="ckc-addon-submit-text">確定加購</span>
                        <span class="ckc-addon-submit-spinner" style="display: none;">處理中...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 2026-08-30 新增（spec-同溫層限制與運費顯示修正.md 第一階段 §5.2）：
         溫層不相容攔截彈窗。跟上面規格選擇彈窗是兩個獨立的彈窗，各自
         display:none 互不影響；共用同一組 .ckc-addon-modal* 基礎樣式，
         只多加 .ckc-temp-conflict-dialog 這個 class 做內容區的客製排版。 -->
    <div id="ckc-temp-conflict-modal" class="ckc-addon-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="ckc-temp-conflict-title">
        <div class="ckc-addon-modal-overlay"></div>
        <div class="ckc-addon-modal-dialog ckc-temp-conflict-dialog">
            <button type="button" class="ckc-addon-modal-close" aria-label="關閉視窗">&times;</button>
            <div class="ckc-addon-modal-content">
                <h3 id="ckc-temp-conflict-title" class="ckc-temp-conflict-title">⚠️ 無法合併配送</h3>
                <p class="ckc-temp-conflict-message"></p>
                <div class="ckc-temp-conflict-actions">
                    <button type="button" class="ckc-temp-conflict-cancel">取消</button>
                    <button type="button" class="ckc-temp-conflict-clear">清空購物車，改買此商品</button>
                </div>
            </div>
        </div>
    </div>

    <style>
    /* 加購規格選擇彈窗樣式 */
    .ckc-addon-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        z-index: 999999;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.25s ease, visibility 0.25s ease;
    }
    .ckc-addon-modal.ckc-modal-open {
        opacity: 1;
        visibility: visible;
    }
    .ckc-addon-modal-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.55);
        backdrop-filter: blur(3px);
        -webkit-backdrop-filter: blur(3px);
    }
    .ckc-addon-modal-dialog {
        position: relative;
        width: 90%;
        max-width: 440px;
        background: #ffffff;
        border-radius: 14px;
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.22);
        z-index: 10;
        overflow: hidden;
        transform: translateY(20px) scale(0.96);
        transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        display: flex;
        flex-direction: column;
        max-height: 88vh;
    }
    .ckc-addon-modal.ckc-modal-open .ckc-addon-modal-dialog {
        transform: translateY(0) scale(1);
    }
    .ckc-addon-modal-close {
        position: absolute;
        top: 12px;
        right: 14px;
        width: 32px;
        height: 32px;
        border: none;
        background: #f0f0f1;
        border-radius: 50%;
        font-size: 20px;
        line-height: 1;
        color: #646970;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 12;
        transition: all 0.2s;
    }
    .ckc-addon-modal-close:hover {
        background: #e2e4e7;
        color: #1d2327;
    }
    .ckc-addon-modal-content {
        padding: 20px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    .ckc-addon-modal-header {
        display: flex;
        gap: 14px;
        align-items: center;
        padding-bottom: 14px;
        border-bottom: 1px solid #f0ebe4;
    }
    .ckc-addon-modal-thumb {
        width: 72px;
        height: 72px;
        border-radius: 8px;
        overflow: hidden;
        background: #f7f7f8;
        flex-shrink: 0;
        border: 1px solid #eee;
    }
    .ckc-addon-modal-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .ckc-addon-modal-info {
        flex: 1;
        min-width: 0;
        padding-right: 20px;
    }
    .ckc-addon-modal-product-title {
        font-size: 15px;
        font-weight: 700;
        color: #1d2327;
        margin: 0 0 6px;
        line-height: 1.35;
    }
    .ckc-addon-modal-price-box {
        font-size: 16px;
        font-weight: 700;
        color: #f86f69;
    }
    .ckc-addon-modal-body {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }
    .ckc-addon-spec-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .ckc-addon-spec-label {
        font-size: 13px;
        font-weight: 700;
        color: #3c434a;
    }
    .ckc-addon-spec-pills {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .ckc-addon-spec-btn {
        padding: 7px 14px;
        border: 1px solid #dcdcde;
        border-radius: 6px;
        background: #ffffff;
        color: #2c3338;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.15s ease;
        line-height: 1.4;
    }
    .ckc-addon-spec-btn:hover {
        border-color: #c5a880;
        color: #a8501c;
    }
    .ckc-addon-spec-btn.active {
        background: #f86f69 !important;
        border-color: #f86f69 !important;
        color: #ffffff !important;
        font-weight: 700;
        box-shadow: 0 2px 6px rgba(248, 111, 105, 0.35);
    }
    .ckc-addon-spec-btn.disabled {
        background: #f6f7f7 !important;
        border-color: #e5e5e5 !important;
        color: #a7aaad !important;
        cursor: not-allowed !important;
        text-decoration: line-through;
    }
    .ckc-addon-qty-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-top: 10px;
        border-top: 1px dashed #f0ebe4;
    }
    .ckc-addon-qty-row label {
        font-size: 14px;
        font-weight: 700;
        color: #3c434a;
        margin: 0;
    }
    .ckc-addon-qty-stepper {
        display: flex;
        align-items: center;
        border: 1px solid #dcdcde;
        border-radius: 6px;
        overflow: hidden;
    }
    .ckc-addon-qty-btn {
        width: 34px;
        height: 34px;
        border: none;
        background: #f6f7f7;
        font-size: 16px;
        font-weight: 700;
        color: #2c3338;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.15s;
    }
    .ckc-addon-qty-btn:hover {
        background: #e2e4e7;
    }
    .ckc-addon-qty-field {
        width: 44px;
        height: 34px;
        border: none;
        border-left: 1px solid #dcdcde;
        border-right: 1px solid #dcdcde;
        text-align: center;
        font-size: 14px;
        font-weight: 700;
        color: #1d2327;
        background: #fff;
        padding: 0;
        margin: 0;
    }
    .ckc-addon-stock-warning {
        font-size: 12px;
        color: #d63638;
        background: #fcf0f1;
        padding: 6px 10px;
        border-radius: 4px;
    }
    .ckc-addon-modal-footer {
        padding-top: 6px;
    }
    .ckc-addon-modal-submit-btn {
        width: 100%;
        height: 46px;
        border: none;
        background: #c9974a;
        color: #ffffff;
        font-size: 15px;
        font-weight: 700;
        border-radius: 24px;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: 0 4px 12px rgba(201, 151, 74, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .ckc-addon-modal-submit-btn:hover {
        background: #b58238;
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(201, 151, 74, 0.4);
    }
    .ckc-addon-modal-submit-btn:disabled {
        background: #dcdcde !important;
        color: #8c8f94 !important;
        cursor: not-allowed !important;
        transform: none !important;
        box-shadow: none !important;
    }
    /* 提示訊息 Toast 樣式 */
    #ckc-coupon-toast {
        position: fixed;
        left: 50%;
        bottom: 84px;
        transform: translateX(-50%) translateY(20px);
        background: #16a34a;
        color: #fff;
        padding: 14px 24px;
        border-radius: 30px;
        font-size: 15px;
        font-weight: 700;
        z-index: 2147483000;
        max-width: 88vw;
        text-align: center;
        box-shadow: 0 8px 24px rgba(0,0,0,.25);
        opacity: 0;
        pointer-events: none;
        transition: opacity .25s ease, transform .25s ease;
    }
    #ckc-coupon-toast.ckc-show {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }
    /* 溫層不相容攔截彈窗樣式 */
    .ckc-temp-conflict-dialog {
        max-width: 420px;
    }
    .ckc-temp-conflict-title {
        margin: 0 0 12px 0;
        font-size: 18px;
        font-weight: 700;
        color: #1a140f;
    }
    .ckc-temp-conflict-message {
        margin: 0 0 20px 0;
        font-size: 14px;
        line-height: 1.7;
        color: #5c4033;
        white-space: pre-line;
    }
    .ckc-temp-conflict-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .ckc-temp-conflict-cancel,
    .ckc-temp-conflict-clear {
        width: 100%;
        padding: 12px 16px;
        border-radius: 24px;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .ckc-temp-conflict-cancel {
        background: #fff;
        color: #1a140f;
        border: 1px solid #c9974a;
    }
    .ckc-temp-conflict-cancel:hover {
        background: #fdf6ec;
    }
    .ckc-temp-conflict-clear {
        background: #fff6f5;
        color: #e2685f;
        border: 1px solid #e2685f;
    }
    .ckc-temp-conflict-clear:hover {
        background: #e2685f;
        color: #fff;
    }
    .ckc-temp-conflict-clear:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    </style>

    <script type="text/javascript">
    jQuery(function($) {
        'use strict';

        var ajaxUrl = <?php echo json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
        var currentModalData = null;
        var selectedSpecs = {};

        function showToast(msg) {
            var $t = $('#ckc-coupon-toast');
            if (!$t.length) {
                $t = $('<div id="ckc-coupon-toast" role="status" aria-live="polite"></div>').appendTo('body');
            }
            $t.text(msg).css('background', '#16a34a');
            requestAnimationFrame(function() { $t.addClass('ckc-show'); });
            clearTimeout(window._ckcToastTimer);
            window._ckcToastTimer = setTimeout(function() { $t.removeClass('ckc-show'); }, 1800);
        }
        // 暴露給其他檔案（例如 functions.php 商品頁「加入購物車」的 AJAX
        // 攔截邏輯，是獨立的 <script> 區塊／closure）共用同一顆 toast。
        window.ckcShowToast = showToast;

        function closeModal() {
            $('#ckc-addon-spec-modal').removeClass('ckc-modal-open');
            setTimeout(function() {
                $('#ckc-addon-spec-modal').hide();
                currentModalData = null;
                selectedSpecs = {};
            }, 250);
        }

        // 2026-08-30 新增（spec-同溫層限制與運費顯示修正.md 第一階段 §5.2）：
        // 溫層不相容攔截彈窗。onConfirmClear 是呼叫端自訂的 callback
        // function(done, fail)——不同入口（商品頁單品按鈕／購物車湊免運加購
        // ／規格彈窗）各自「怎麼重新加入商品」的流程不同，這裡不假設固定的
        // product_id/quantity payload 形狀，改由呼叫端自己決定要怎麼做，
        // 只負責彈窗本身的顯示/關閉/二次確認/按鈕狀態管理。
        // 暴露在 window 上，讓 functions.php 那個獨立 closure 也能呼叫。
        window.ckcShowTemperatureConflictModal = function(message, onConfirmClear) {
            var $modal = $('#ckc-temp-conflict-modal');
            $modal.find('.ckc-temp-conflict-message').text(message);
            $modal.data('onConfirmClear', typeof onConfirmClear === 'function' ? onConfirmClear : null);
            $modal.show();
            requestAnimationFrame(function() { $modal.addClass('ckc-modal-open'); });
        };

        function closeTempConflictModal() {
            var $modal = $('#ckc-temp-conflict-modal');
            $modal.removeClass('ckc-modal-open');
            setTimeout(function() {
                $modal.hide();
                $modal.data('onConfirmClear', null);
            }, 250);
        }

        $(document).on('click', '#ckc-temp-conflict-modal .ckc-temp-conflict-cancel, #ckc-temp-conflict-modal .ckc-addon-modal-close, #ckc-temp-conflict-modal .ckc-addon-modal-overlay', function() {
            closeTempConflictModal();
        });

        $(document).on('click', '#ckc-temp-conflict-modal .ckc-temp-conflict-clear', function() {
            var $modal = $('#ckc-temp-conflict-modal');
            var cb = $modal.data('onConfirmClear');
            if (typeof cb !== 'function') {
                return;
            }
            // 「清空購物車」屬破壞性操作，執行前需二次確認（spec §5.2／§7④，
            // 交接文件第十五章 15.6 教訓）。這裡是使用者直接點擊觸發的
            // confirm()，屬於真實使用者手勢，不會被瀏覽器靜默阻擋。
            if (!window.confirm('確定要清空購物車內所有商品，並改買這件商品嗎？此動作無法復原。')) {
                return;
            }
            var $btn = $(this);
            $btn.prop('disabled', true).text('處理中...');
            cb(
                function done() {
                    $btn.prop('disabled', false).text('清空購物車，改買此商品');
                    closeTempConflictModal();
                },
                function fail() {
                    $btn.prop('disabled', false).text('清空購物車，改買此商品');
                }
            );
        });

        // 2026-08-30 修正：加購成功後原地套用後端已回傳、之前完全沒被使用的
        // fragments（購物車數量徽章／下拉選單／免運進度條），取代原本「先
        // toast、再等 400ms 整頁 reload」的做法——reload 會把捲動位置重置回
        // 頁面頂端，加上 AJAX 本身要價約 1.4 秒，使用者感覺點擊沒有反應。
        function ckcApplyAddonFragments(fragments) {
            if (!fragments) return;
            $.each(fragments, function(selector, html) {
                var $target = $(selector);
                if ($target.length) {
                    $target.replaceWith(html);
                }
            });
        }

        // 購物車頁加購成功後，原地重新抓取本頁最新內容並替換整個 .woocommerce
        // 區塊（涵蓋購物車商品列表、總計、免運進度條與加購專區本身），一次
        // 更新到位。沿用交接文件 12.2.3 已驗證有效的手法：不依賴
        // wc_fragment_refresh / wc-cart-fragments（該腳本在此站台被平台層級
        // 機制攔截），改用帶時間戳記、關閉快取的自行 AJAX 重抓＋手動替換。
        // 完成後觸發 updated_cart_totals，讓行動版吸底結帳列等既有監聽者
        // 一併同步更新。
        function ckcRefreshCartPageAfterAddonAdd() {
            $.ajax({
                url: window.location.href + (window.location.href.indexOf('?') === -1 ? '?' : '&') + 'chao_cache_bust=' + Date.now(),
                cache: false,
                success: function(response) {
                    try {
                        var $parsed = $('<div>').append($.parseHTML(response));
                        var $newWoo = $parsed.find('.woocommerce').first();
                        var $curWoo = $('.woocommerce').first();
                        if ($newWoo.length && $curWoo.length) {
                            $curWoo.replaceWith($newWoo);
                            $(document.body).trigger('updated_cart_totals');
                        }
                    } catch (err) {
                        console.error('Error refreshing cart page after addon add:', err);
                    }
                }
            });
        }

        // 加購成功（含「清空購物車改買」成功）後，統一處理購物車頁/結帳頁
        // 的畫面同步，避免兩個入口各自重複寫一份一樣的邏輯。
        function ckcSyncPageAfterCartChange() {
            var isCartPage = $('body').hasClass('woocommerce-cart') || $('.woocommerce-cart-form').length > 0;
            var isCheckoutPage = !isCartPage && ($('body').hasClass('woocommerce-checkout') || $('form.woocommerce-checkout').length > 0);
            if (isCartPage) {
                ckcRefreshCartPageAfterAddonAdd();
            } else if (isCheckoutPage) {
                $(document.body).trigger('update_checkout');
            }
        }

        // 2026-08-30 新增（spec-同溫層限制與運費顯示修正.md 第一階段 §5.2）：
        // 組出溫層攔截彈窗「清空購物車，改買此商品」按鈕要用的 onConfirmClear
        // callback，呼叫共用的 ckc_empty_cart_and_add 端點，成功後複用跟一般
        // 加購成功完全相同的畫面同步方式（fragments／原地重抓購物車內容）。
        function ckcBuildEmptyCartAndAddRetry(productId, quantity, specSelected) {
            return function(done, fail) {
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'ckc_empty_cart_and_add',
                        product_id: productId,
                        quantity: quantity || 1,
                        ckc_spec_selected: specSelected || {}
                    },
                    success: function(res) {
                        if (res.success) {
                            showToast(res.data.message || '已清空購物車並加入商品！');
                            ckcApplyAddonFragments(res.data.fragments);
                            ckcSyncPageAfterCartChange();
                            done();
                        } else {
                            showToast((res.data && res.data.message) || '操作失敗，請稍後再試');
                            fail();
                        }
                    },
                    error: function() {
                        showToast('網路連線失敗，請稍後再試');
                        fail();
                    }
                });
            };
        }

        function updateModalPriceAndValidation() {
            if (!currentModalData) return;

            var allChosen = true;
            var catIds = [];
            $.each(currentModalData.categories, function(i, cat) {
                catIds.push(cat.id);
                if (!selectedSpecs[cat.id]) {
                    allChosen = false;
                }
            });

            var price = currentModalData.base_price;
            var isAvailable = true;
            var warnMsg = '';

            if (currentModalData.spec_type === 'ckc_spec' && allChosen) {
                catIds.sort();
                var comboParts = [];
                $.each(catIds, function(i, catId) {
                    comboParts.push(catId + ':' + selectedSpecs[catId]);
                });
                var comboKey = comboParts.join('|');
                var combo = currentModalData.combinations[comboKey] || null;

                if (combo) {
                    if (combo.enabled === false) {
                        isAvailable = false;
                        warnMsg = '此規格組合目前無法選購（已停用）。';
                    } else if (combo.stock_qty !== null && parseInt(combo.stock_qty, 10) <= 0) {
                        isAvailable = false;
                        warnMsg = '此規格組合目前已額滿。';
                    }
                    if (combo.price_adjust) {
                        price += parseFloat(combo.price_adjust);
                    }
                }
            }

            var formattedPrice = 'NT$' + Math.round(price).toLocaleString();
            $('#ckc-addon-modal-price').text(formattedPrice);

            var qty = parseInt($('#ckc-addon-qty-input').val(), 10) || 1;
            var totalPriceText = 'NT$' + Math.round(price * qty).toLocaleString();

            if (warnMsg) {
                $('#ckc-addon-modal-stock-warn').text(warnMsg).show();
            } else {
                $('#ckc-addon-modal-stock-warn').hide();
            }

            var $btn = $('#ckc-addon-modal-submit');
            if (allChosen && isAvailable) {
                $btn.prop('disabled', false).find('.ckc-addon-submit-text').text('確定加購 (' + totalPriceText + ')');
            } else {
                $btn.prop('disabled', true).find('.ckc-addon-submit-text').text('請選擇規格 (' + formattedPrice + ')');
            }
        }

        function openSpecModal(productId, $triggerBtn) {
            $triggerBtn.prop('disabled', true).css('opacity', 0.6);

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'ckc_get_addon_modal_data',
                    product_id: productId
                },
                success: function(response) {
                    $triggerBtn.prop('disabled', false).css('opacity', 1);
                    if (!response.success || !response.data) {
                        showToast((response.data && response.data.message) || '載入失敗，請稍後再試');
                        return;
                    }

                    var data = response.data;
                    currentModalData = data;
                    selectedSpecs = {};

                    // 填入標題、圖片、價格
                    $('#ckc-addon-modal-title').text(data.title);
                    $('#ckc-addon-modal-img').attr('src', data.image);
                    $('#ckc-addon-modal-price').html(data.price_html);
                    $('#ckc-addon-qty-input').val(1);
                    $('#ckc-addon-modal-stock-warn').hide();

                    // 渲染規格群組
                    var $groupsContainer = $('#ckc-addon-modal-groups').empty();

                    $.each(data.categories, function(idx, cat) {
                        var $group = $('<div class="ckc-addon-spec-group"></div>');
                        $group.append('<div class="ckc-addon-spec-label">' + cat.label + '：</div>');

                        var $pills = $('<div class="ckc-addon-spec-pills"></div>');
                        $.each(cat.values, function(vIdx, val) {
                            var $btn = $('<button type="button" class="ckc-addon-spec-btn" data-cat-id="' + cat.id + '" data-val-id="' + val.id + '">' + val.label + '</button>');
                            // 若只有一個選項自動預選
                            if (cat.values.length === 1) {
                                $btn.addClass('active');
                                selectedSpecs[cat.id] = val.id;
                            }
                            $pills.append($btn);
                        });

                        $group.append($pills);
                        $groupsContainer.append($group);
                    });

                    updateModalPriceAndValidation();

                    $('#ckc-addon-spec-modal').show();
                    requestAnimationFrame(function() {
                        $('#ckc-addon-spec-modal').addClass('ckc-modal-open');
                    });
                },
                error: function() {
                    $triggerBtn.prop('disabled', false).css('opacity', 1);
                    showToast('網路連線逾時，請稍後再試');
                }
            });
        }

        // 點擊規格膠囊按鈕
        $(document).on('click', '.ckc-addon-spec-btn', function() {
            var $btn = $(this);
            if ($btn.hasClass('disabled')) return;

            var catId = $btn.data('cat-id');
            var valId = $btn.data('val-id');

            if ($btn.hasClass('active')) {
                $btn.removeClass('active');
                delete selectedSpecs[catId];
            } else {
                $btn.addClass('active').siblings().removeClass('active');
                selectedSpecs[catId] = valId;
            }

            updateModalPriceAndValidation();
        });

        // 數量加減
        $(document).on('click', '.ckc-addon-qty-minus', function() {
            var $input = $('#ckc-addon-qty-input');
            var val = parseInt($input.val(), 10) || 1;
            if (val > 1) {
                $input.val(val - 1);
                updateModalPriceAndValidation();
            }
        });

        $(document).on('click', '.ckc-addon-qty-plus', function() {
            var $input = $('#ckc-addon-qty-input');
            var val = parseInt($input.val(), 10) || 1;
            if (val < 99) {
                $input.val(val + 1);
                updateModalPriceAndValidation();
            }
        });

        // 點擊彈窗內「確定加購」
        $(document).on('click', '#ckc-addon-modal-submit', function() {
            if (!currentModalData) return;

            var $btn = $(this);
            $btn.prop('disabled', true).find('.ckc-addon-submit-text').hide();
            $btn.find('.ckc-addon-submit-spinner').show();

            var qty = parseInt($('#ckc-addon-qty-input').val(), 10) || 1;

            var postData = {
                action: 'ckc_add_addon_to_cart',
                product_id: currentModalData.product_id,
                quantity: qty,
                ckc_spec_selected: selectedSpecs
            };

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: postData,
                success: function(response) {
                    $btn.find('.ckc-addon-submit-spinner').hide();
                    $btn.find('.ckc-addon-submit-text').show();

                    if (response.success) {
                        closeModal();
                        showToast(response.data.message || '已成功加入購物車！');

                        // 觸發 WooCommerce 原生事件（供既有的「已加入購物車」彈窗等監聽使用）
                        $(document.body).trigger('added_to_cart', [response.data.fragments, response.data.cart_hash, $btn]);

                        // 原地套用 fragments（購物車數量徽章／下拉選單／免運進度條）
                        ckcApplyAddonFragments(response.data.fragments);

                        // 頁面同步更新
                        ckcSyncPageAfterCartChange();
                    } else if (response.data && response.data.temperature_conflict) {
                        // 溫層衝突：關掉規格彈窗，改顯示專屬攔截彈窗（spec §5.2）
                        $btn.prop('disabled', false);
                        closeModal();
                        window.ckcShowTemperatureConflictModal(
                            response.data.message || '此商品的配送溫層與購物車內現有商品不同，無法合併於同一筆訂單。',
                            ckcBuildEmptyCartAndAddRetry(postData.product_id, qty, selectedSpecs)
                        );
                    } else {
                        $btn.prop('disabled', false);
                        showToast((response.data && response.data.message) || '加入失敗，請稍後再試');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).find('.ckc-addon-submit-spinner').hide();
                    $btn.find('.ckc-addon-submit-text').show();
                    showToast('加入失敗，請檢查網路連線');
                }
            });
        });

        // 關閉彈窗
        $(document).on('click', '.ckc-addon-modal-close, .ckc-addon-modal-overlay', function() {
            closeModal();
        });

        // 攔截所有加購按鈕（購物車頁與結帳頁）
        $(document).on('click', '.chao-checkout-crosssell-add, .chao-cross-sell-add', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (e.stopImmediatePropagation) {
                e.stopImmediatePropagation();
            }

            var $btn = $(this);
            if ($btn.prop('disabled')) {
                return;
            }

            var pid = parseInt($btn.data('product-id') || $btn.data('product_id'), 10);
            var hasSpecs = $btn.attr('data-has-specs') === '1';

            if (!pid) return;

            // 若有規格，打開規格選擇彈窗
            if (hasSpecs) {
                openSpecModal(pid, $btn);
                return;
            }

            // 無規格商品，直接透過 AJAX 加入購物車
            $btn.prop('disabled', true).css('opacity', 0.6);
            var origText = $btn.html();
            $btn.text('加入中...');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'ckc_add_addon_to_cart',
                    product_id: pid,
                    quantity: 1
                },
                success: function(res) {
                    if (res.success) {
                        showToast(res.data.message || '已成功加入購物車！');

                        // 觸發 WooCommerce 原生事件（供既有的「已加入購物車」彈窗等監聽使用）
                        $(document.body).trigger('added_to_cart', [res.data.fragments, res.data.cart_hash, $btn]);

                        // 原地套用 fragments（購物車數量徽章／下拉選單／免運進度條）
                        ckcApplyAddonFragments(res.data.fragments);

                        // 頁面同步更新
                        var isCartPage = $('body').hasClass('woocommerce-cart') || $('.woocommerce-cart-form').length > 0;
                        var isCheckoutPage = !isCartPage && ($('body').hasClass('woocommerce-checkout') || $('form.woocommerce-checkout').length > 0);

                        if (isCartPage) {
                            // 購物車頁：原地重抓並替換購物車內容（商品列表／總計／
                            // 免運進度條／加購專區），不整頁重載、不重置捲動位置。
                            // 這顆按鈕本身就在會被替換的區塊內，替換完成後會自然
                            // 換成全新渲染的按鈕，不需要手動還原 disabled 狀態。
                            ckcRefreshCartPageAfterAddonAdd();
                        } else if (isCheckoutPage) {
                            $(document.body).trigger('update_checkout');
                            $btn.prop('disabled', false).css('opacity', 1).html(origText);
                        } else {
                            $btn.prop('disabled', false).css('opacity', 1).html(origText);
                        }
                    } else if (res.data && res.data.temperature_conflict) {
                        // 溫層衝突：顯示專屬攔截彈窗（spec §5.2），按鈕先還原成可再次點擊的狀態
                        $btn.prop('disabled', false).css('opacity', 1).html(origText);
                        window.ckcShowTemperatureConflictModal(
                            res.data.message || '此商品的配送溫層與購物車內現有商品不同，無法合併於同一筆訂單。',
                            ckcBuildEmptyCartAndAddRetry(pid, 1)
                        );
                    } else {
                        showToast((res.data && res.data.message) ? res.data.message : '加入失敗，請稍後再試');
                        $btn.prop('disabled', false).css('opacity', 1).html(origText);
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).css('opacity', 1).html(origText);
                    showToast('網路連線失敗，請稍後再試');
                }
            });
        });
    });
    </script>
    <?php
}
