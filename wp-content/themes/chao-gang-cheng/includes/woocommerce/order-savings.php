<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 直接從運送區域設定讀取「固定費率」(flat_rate) 方式本身設定的費用，
 * 不受目前購物車 package 篩選結果（例如達免運門檻、離島限制、依運送
 * 類別限制可用方式等）影響，用來當「免運省下多少運費」計算的可靠保底
 * 來源。做法比照既有的 chao_gang_cheng_get_free_shipping_threshold()
 * （同樣走「先找命名區域、找不到再退回預設區域 0」的順序）。
 *
 * 固定費率的費用欄位在 WooCommerce 後台可以填公式（例如
 * "10 + ( 2 * [qty] )"），這裡只取數字開頭部分，公式類的複雜運費不會
 * 完全精準，但作為「你省下了多少」的行銷提示文字已經足夠準確。
 */
function chao_get_zone_flat_rate_cost() {
    if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
        return 0.0;
    }
    $zones = WC_Shipping_Zones::get_zones();
    $zones[] = array( 'zone_id' => 0 ); // 附加預設區域，稍後統一處理
    foreach ( $zones as $zone_data ) {
        $zone = isset( $zone_data['zone_id'] )
            ? WC_Shipping_Zones::get_zone_by( 'zone_id', $zone_data['zone_id'] )
            : null;
        if ( ! $zone ) {
            continue;
        }
        foreach ( $zone->get_shipping_methods( true ) as $method ) {
            if ( 'flat_rate' !== $method->id || 'yes' !== $method->enabled ) {
                continue;
            }
            $cost = method_exists( $method, 'get_option' ) ? $method->get_option( 'cost' ) : ( isset( $method->cost ) ? $method->cost : '' );
            $cost = (float) $cost; // 只取數字開頭，公式類費用不精算
            if ( $cost > 0 ) {
                return $cost;
            }
        }
    }
    return 0.0;
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
    //
    // 注意：這裡原本是靠 WC()->shipping()->get_packages() 去讀「目前選擇的
    // 運送方式」，但實測發現這條路走不通——購物車頁的「預估運費」列
    // （chao_cart_estimated_shipping_row()，見 checkout-ux.php）其實根本
    // 不是用 WooCommerce 原生的運費試算流程，而是直接拿「小計是否達免運
    // 門檻」＋「是否套用免運優惠券」來判斷、顯示靜態文字，從來不會觸發
    // WC()->shipping()->calculate_shipping()，所以 get_packages() 在這個
    // 流程裡常常是空的或還沒算過，導致這裡永遠偵測不到免運、省下的運費
    // 也就一直算成 0。改成比照「預估運費」列同一套判斷方式（門檻＋免運
    // 優惠券），兩邊基準完全一致，也不再依賴可能還沒算好的 shipping
    // package 資料。
    //
    // 再修正一次：也不能用 $cart->needs_shipping() 當守門條件——這個網站
    // 特地掛了 woocommerce_cart_needs_shipping 這個 filter，在購物車頁
    // （is_cart()）強制回傳 false（只在結帳頁才顯示運費相關資訊，見
    // chao_gang_cheng_hide_shipping_on_cart()），導致這裡在購物車頁必定
    // 誤判成「不需要運費」而完全跳過整段免運判斷。跟「預估運費」列一樣
    // 乾脆不檢查 needs_shipping()，純粹用門檻／優惠券判斷。
    $shipping_savings = 0.0;
    $threshold        = function_exists( 'chao_get_free_shipping_threshold' )
        ? chao_get_free_shipping_threshold()
        : 2000;
    $progress_amount  = function_exists( 'chao_get_free_shipping_progress_amount' )
        ? chao_get_free_shipping_progress_amount()
        : (float) $cart->get_cart_contents_total();

    $coupon_free_shipping = false;
    foreach ( $cart->get_applied_coupons() as $coupon_code ) {
        $applied_coupon = new WC_Coupon( $coupon_code );
        if ( $applied_coupon->get_id() && $applied_coupon->get_free_shipping() ) {
            $coupon_free_shipping = true;
            break;
        }
    }

    if ( $coupon_free_shipping || $progress_amount >= $threshold ) {
        // 保底運費金額 250，優先改用「電商營運 > 運費管理」後台設定
        // （見 includes/admin/shipping-management.php）算出的宅配實際運費，
        // 跟結帳頁 chao_gang_cheng_apply_shipping_management_rates() 用
        // 同一套 lookup 邏輯（地區固定本島、溫層依購物車商品交集、件數
        // 用購物車總件數），確保「省下的運費」跟結帳頁實際運費金額一致，
        // 不再顯示舊的、跟結帳頁對不上的固定值／WC_Shipping_Zones 費率。
        $dynamic_base_cost = 250.0;

        if ( function_exists( 'chao_gang_cheng_lookup_shipping_fee' ) && function_exists( 'chao_gang_cheng_determine_package_temperature_zone' ) ) {
            $zone_for_savings = chao_gang_cheng_determine_package_temperature_zone( array( 'contents' => $cart->get_cart() ) );
            $qty_for_savings  = 0;
            foreach ( $cart->get_cart() as $savings_cart_item ) {
                $qty_for_savings += isset( $savings_cart_item['quantity'] ) ? (int) $savings_cart_item['quantity'] : 0;
            }
            $home_delivery_fee = chao_gang_cheng_lookup_shipping_fee( 'home_delivery', 'main_island', $zone_for_savings, $qty_for_savings );
            if ( null !== $home_delivery_fee && $home_delivery_fee > 0 ) {
                $dynamic_base_cost = $home_delivery_fee;
            } else {
                $zone_cost = chao_get_zone_flat_rate_cost();
                if ( $zone_cost > 0 ) {
                    $dynamic_base_cost = $zone_cost;
                }
            }
        } else {
            // 後備：運費管理設定尚未載入時，退回舊的 WC_Shipping_Zones 讀法。
            $zone_cost = chao_get_zone_flat_rate_cost();
            if ( $zone_cost > 0 ) {
                $dynamic_base_cost = $zone_cost;
            }
        }

        $shipping_savings = $dynamic_base_cost;
    }

    $savings = $original_sum - $subtotal + $coupon_discount + $shipping_savings;

    return $savings > 0.01 ? round( $savings, 2 ) : 0.0;
}

/**
 * 商品原價總和（跟「小計」同一套含稅／未稅基準），給「購物車總計」的
 * 「小計」列同時顯示原價用。邏輯跟 chao_calc_cart_total_savings() 裡的
 * 第 1 步完全一樣，獨立成一個函式方便重複使用、不用重算兩次也不用
 * 動到既有已驗證過的省下金額計算。
 */
function chao_calc_cart_original_subtotal( $cart = null ) {
    if ( ! $cart ) {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return 0.0;
        }
        $cart = WC()->cart;
    }
    if ( ! is_a( $cart, 'WC_Cart' ) || $cart->is_empty() ) {
        return 0.0;
    }

    $incl_tax     = $cart->display_prices_including_tax();
    $original_sum = 0.0;

    foreach ( $cart->get_cart() as $cart_item ) {
        $product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            continue;
        }
        $qty     = isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 1;
        $regular = $product->get_regular_price();
        if ( '' === $regular || ! is_numeric( $regular ) ) {
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

    return $original_sum;
}

/**
 * 「購物車總計」表格的「小計」列，同時顯示原價（刪除線）＋折扣後小計，
 * 樣式沿用購物車商品列現有的原價刪除線做法（見 cart.php
 * ckc_cart_item_price_add_regular()），維持前台視覺一致。
 * 只有在原價確實高於小計（有實際折扣）時才加顯原價，避免沒有折扣的
 * 訂單也顯示一模一樣的兩行造成混淆。
 *
 * 注意：這裡原本掛錯 filter 名稱（woocommerce_cart_totals_subtotal_html
 * 這個 filter 根本不存在，wc_cart_totals_subtotal_html() 直接輸出
 * WC_Cart::get_cart_subtotal() 的結果，實際套用的 filter 是
 * WC_Cart::get_cart_subtotal() 內部呼叫的 woocommerce_cart_subtotal，
 * 且回呼參數是 3 個：$return, $compound, $cart），導致這個 filter 從
 * 頭到尾沒被執行過。改成正確的 filter 名稱與參數簽名。
 */
add_filter( 'woocommerce_cart_subtotal', 'chao_cart_totals_subtotal_add_regular', 10, 3 );
function chao_cart_totals_subtotal_add_regular( $subtotal_html, $compound, $cart ) {
    if ( $compound ) {
        return $subtotal_html; // 複合稅額顯示模式維持原樣，不處理
    }

    $original_sum = chao_calc_cart_original_subtotal( $cart );
    $subtotal     = (float) $cart->get_subtotal();

    if ( $original_sum <= $subtotal + 0.01 ) {
        return $subtotal_html; // 沒有折扣，維持原樣
    }

    $regular_html = wp_kses_post( wc_price( $original_sum ) );

    return '<del style="color:#999; font-size:0.85em; display:block; line-height: 1; margin-bottom:2px;">' . $regular_html . '</del>'
         . '<ins style="text-decoration:none; display:block; font-weight:700;">' . $subtotal_html . '</ins>';
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
        <th>
            <?php esc_html_e( '已為您省下', 'chao-gang-cheng' ); ?>
            <span style="color:#999; font-size:0.8em; font-weight:normal;"><?php esc_html_e( '（含商品折扣與優惠）', 'chao-gang-cheng' ); ?></span>
        </th>
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
