<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 「此訂單總共省了多少？」— 購物車頁與結帳頁共用同一套計算與渲染。
 *
 * 省下金額 = Σ(商品原價 × 數量，以「小計」相同的含稅／未稅基準換算)
 *          － 小計（get_subtotal，與頁面上「小計」列同一個數字）
 *          + 已套用優惠券的折抵金額（與頁面上「優惠券」列同一個數字，逐張加總）
 *
 * 設計說明：
 * - 「小計」本身已經反映商品目前的實際售價（特價、或加購商品用 set_price() 做的
 *   臨時降價都已經算在裡面），所以「原價 － 小計」自然就包含這兩種折扣，
 *   不需要另外處理「臨時降價」。
 * - WooCommerce 設計上「小計」不含優惠券折抵（優惠券是總計前另一列扣除），
 *   因此要把優惠券折抵金額加回來，才是消費者「實際省下」的總額。
 * - 不含運費、紅利點數折抵（點數是另一種折抵機制，未計入此欄位；如需納入
 *   可在 ckc_apply_points_as_fee 對應的 fee 金額上再擴充）。
 * - 原價加總與小計／優惠券折抵都以 $cart->display_prices_including_tax()
 *   同一套含稅／未稅基準計算，確保三個數字對得起來。
 */
function chao_calc_cart_total_savings( $cart = null ) {
    if ( ! $cart ) {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return 0.0;
        }
        $cart = WC()->cart;
    }
    if ( ! is_a( $cart, 'WC_Cart' ) || $cart->is_empty() ) {
        return 0.0;
    }

    $incl_tax = $cart->display_prices_including_tax();

    // 1. 商品原價總和（跟小計同一套含稅／未稅基準）
    $original_sum = 0.0;
    foreach ( $cart->get_cart() as $cart_item ) {
        $product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            continue;
        }
        $qty     = isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 1;
        $regular = $product->get_regular_price();
        if ( '' === $regular || ! is_numeric( $regular ) ) {
            // 沒有設定原價（或原價異常）時，以目前售價當基準，不視為有折扣
            $regular = $product->get_price();
        }
        if ( '' === $regular || ! is_numeric( $regular ) ) {
            continue;
        }
        $price_args = array( 'qty' => $qty, 'price' => $regular );
        $original_sum += (float) ( $incl_tax
            ? wc_get_price_including_tax( $product, $price_args )
            : wc_get_price_excluding_tax( $product, $price_args ) );
    }

    if ( $original_sum <= 0 ) {
        return 0.0;
    }

    // 2. 小計（與頁面「小計」列同一個數字）
    $subtotal = (float) $cart->get_subtotal();

    // 3. 已套用優惠券的折抵金額（與頁面「優惠券」列同一個數字，逐張加總）
    $coupon_discount = 0.0;
    foreach ( $cart->get_coupons() as $code => $coupon ) {
        $coupon_discount += (float) $cart->get_coupon_discount_amount( $code, $incl_tax );
    }

    // 4. 判斷是否享有免運，若有免運則加上省下的運費 (隨系統真實運費變化)
    $shipping_savings = 0.0;
    if ( $cart->needs_shipping() && $cart->show_shipping() ) {
        $packages = WC()->shipping()->get_packages();
        if ( ! empty( $packages ) && isset( $packages[0]['rates'] ) ) {
            $rates = $packages[0]['rates'];
            $chosen_method_id = isset( WC()->session->chosen_shipping_methods[0] ) ? WC()->session->chosen_shipping_methods[0] : '';
            if ( $chosen_method_id && isset( $rates[ $chosen_method_id ] ) ) {
                $rate = $rates[ $chosen_method_id ];
                // 如果目前運送方式為免運，或運費為 0
                if ( 'free_shipping' === $rate->method_id || (float) $rate->cost == 0 ) {
                    $dynamic_base_cost = 250.0; // 預設 250 保底
                    
                    // 從系統計算出的其他運費選項中，找出真實的運費 (優先找 flat_rate)
                    $found_base = false;
                    foreach ( $rates as $r_id => $r ) {
                        if ( strpos( $r_id, 'flat_rate' ) === 0 && (float) $r->cost > 0 ) {
                            $dynamic_base_cost = (float) $r->cost;
                            $found_base = true;
                            break;
                        }
                    }
                    
                    // 若無 flat_rate，則取可用運費中的最大值作為真實運費
                    if ( ! $found_base ) {
                        $max_cost = 0.0;
                        foreach ( $rates as $r ) {
                            if ( (float) $r->cost > $max_cost ) {
                                $max_cost = (float) $r->cost;
                            }
                        }
                        if ( $max_cost > 0 ) {
                            $dynamic_base_cost = $max_cost;
                        }
                    }
                    
                    $shipping_savings = $dynamic_base_cost;
                }
            }
        }
    }

    $savings = $original_sum - $subtotal + $coupon_discount + $shipping_savings;

    return $savings > 0.01 ? round( $savings, 2 ) : 0.0;
}

/**
 * 共用的一列 HTML。購物車頁與結帳頁的總計表格都是同一種
 * <table class="shop_table"> 結構，直接輸出 <tr> 掛在「應付總計」列之前即可，
 * 兩邊都會隨各自既有的 AJAX 重新整理機制（購物車更新／結帳 update_checkout）
 * 自動重繪，不需要額外的請求或前端 JS。
 */
function chao_render_order_savings_row( $cart = null ) {
    $savings = chao_calc_cart_total_savings( $cart );
    if ( $savings <= 0 ) {
        return;
    }
    ?>
    <tr class="chao-order-savings">
        <th><?php esc_html_e( '已為您省下', 'chao-gang-cheng' ); ?></th>
        <td data-title="<?php esc_attr_e( '已為您省下', 'chao-gang-cheng' ); ?>">
            <strong style="color:#16a34a; display:inline-flex; align-items:center; gap:4px;"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M2 1a1 1 0 0 0-1 1v4.586a1 1 0 0 0 .293.707l7 7a1 1 0 0 0 1.414 0l4.586-4.586a1 1 0 0 0 0-1.414l-7-7A1 1 0 0 0 6.586 1H2zm4 3.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"/></svg><?php echo wp_kses_post( wc_price( $savings ) ); ?></strong>
        </td>
    </tr>
    <?php
}

// 購物車頁：總計表格內，「應付總計」列之前
add_action( 'woocommerce_cart_totals_before_order_total', 'chao_render_order_savings_row', 20 );

// 結帳頁：訂單摘要表格內，「應付總計」列之前（隨 update_checkout AJAX 自動重繪）
add_action( 'woocommerce_review_order_before_order_total', 'chao_render_order_savings_row', 20 );
