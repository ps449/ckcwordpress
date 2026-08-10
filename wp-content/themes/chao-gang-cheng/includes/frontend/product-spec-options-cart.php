<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 商品「二層規格選項」購物車／結帳／訂單整合（第 3 期）。
 *
 * 串接第 2 期前台選單送出的 ckc_spec_selected[類別id]=值id：
 * - 加入購物車時把選擇的值記錄進購物車項目資料（woocommerce_add_cart_item_data）。
 * - 加入購物車前擋掉「完整命中一個已停用／已額滿組合」的請求
 *   （woocommerce_add_to_cart_validation），跟前台 JS 的擋法呼應，但這裡
 *   是伺服器端最後一道防線，避免繞過前台直接送表單。
 * - 購物車頁／結帳頁顯示已選規格（woocommerce_get_item_data）。
 * - 每次重新計算金額時，如果購物車項目的規格選擇「完整命中」組合表，
 *   套用該組合當下的 price_adjust；沒有完整命中（部分選擇或完全沒選）
 *   維持商品原價（woocommerce_before_calculate_totals）。這裡刻意每次都
 *   用 wc_get_product() 重新讀一次商品目前的價格當基準，不是拿購物車項目
 *   已經被改過的價格再疊加一次，這樣同一個請求裡如果這個 hook被觸發
 *   不只一次也不會重複疊加（實務上常見的錯誤是拿已調整過的價格再調一次，
 *   越算越貴）。
 * - 訂單成立時把已選規格寫進訂單項目 meta（woocommerce_checkout_create_order_line_item），
 *   讓訂單明細、後台訂單頁、通知信都看得到，且是「下單當下」的規格文字，
 *   之後就算後台規格類別/值被改名或刪除，這筆訂單的紀錄也不會跟著變動。
 *
 * 不包含（留到第 4 期）：庫存扣減與超賣防護。
 */

/**
 * 從目前這次 request 的 $_POST['ckc_spec_selected'] 讀出「使用者實際選了
 * 什麼」，並且對照商品目前設定的規格類別／值做白名單過濾——只收真的
 * 存在的類別 id、值 id 組合，其餘一律忽略，避免表單被竄改夾帶亂七八糟
 * 的資料進購物車。
 *
 * @param int $product_id
 * @return array cat_id => array( 'cat_label', 'val_id', 'val_label' )，只包含
 *               使用者「有選」的類別（可以只選一部分、或完全不選）。
 */
function chao_gang_cheng_sanitize_spec_selection_from_post( $product_id ) {
    $categories = chao_gang_cheng_get_spec_categories( $product_id );
    if ( empty( $categories ) || ! isset( $_POST['ckc_spec_selected'] ) || ! is_array( $_POST['ckc_spec_selected'] ) ) {
        return array();
    }

    $raw    = wp_unslash( $_POST['ckc_spec_selected'] );
    $result = array();

    foreach ( $categories as $cat ) {
        $cat_id = $cat['id'];
        if ( empty( $raw[ $cat_id ] ) ) {
            continue; // 這個類別使用者沒選，允許（規格是可選的）
        }
        $val_id = sanitize_key( $raw[ $cat_id ] );
        foreach ( $cat['values'] as $val ) {
            if ( $val['id'] === $val_id ) {
                $result[ $cat_id ] = array(
                    'cat_label' => $cat['label'],
                    'val_id'    => $val_id,
                    'val_label' => $val['label'],
                );
                break;
            }
        }
    }

    return $result;
}

/**
 * 判斷「一組選擇」是不是完整命中組合表的某一列（每個類別都選了），
 * 命中的話回傳組合 key（跟後台儲存時同一套排序規則：類別 id 字母序）；
 * 沒有完整命中（部分選擇／完全沒選）回傳 null。
 *
 * @param int   $product_id
 * @param array $selected 格式同 chao_gang_cheng_sanitize_spec_selection_from_post() 的回傳值。
 * @return string|null
 */
function chao_gang_cheng_spec_full_match_key( $product_id, $selected ) {
    $categories = chao_gang_cheng_get_spec_categories( $product_id );
    if ( empty( $categories ) ) {
        return null;
    }
    $cat_ids = wp_list_pluck( $categories, 'id' );
    sort( $cat_ids );

    $parts = array();
    foreach ( $cat_ids as $cat_id ) {
        if ( empty( $selected[ $cat_id ]['val_id'] ) ) {
            return null; // 有類別沒選，不算完整命中
        }
        $parts[] = $cat_id . ':' . $selected[ $cat_id ]['val_id'];
    }
    return implode( '|', $parts );
}

/**
 * 加入購物車前的伺服器端把關：如果選的規格「完整命中」一個已停用或
 * 已額滿（庫存 0）的組合，擋下來並顯示錯誤訊息，不讓它進購物車。
 */
add_filter( 'woocommerce_add_to_cart_validation', 'chao_gang_cheng_validate_spec_selection_add_to_cart', 10, 3 );
function chao_gang_cheng_validate_spec_selection_add_to_cart( $passed, $product_id, $quantity ) {
    if ( ! $passed || ! chao_gang_cheng_product_uses_spec_options( $product_id ) ) {
        return $passed;
    }

    $selected = chao_gang_cheng_sanitize_spec_selection_from_post( $product_id );
    $key      = chao_gang_cheng_spec_full_match_key( $product_id, $selected );
    if ( null === $key ) {
        return $passed; // 部分選擇或完全沒選，不受組合表限制
    }

    $combos = chao_gang_cheng_get_spec_combinations( $product_id );
    $combo  = isset( $combos[ $key ] ) ? $combos[ $key ] : null;
    if ( ! $combo ) {
        return $passed; // 找不到對應組合資料，不擋（跟前台 JS 的保守做法一致）
    }

    $sold_out = ( false === $combo['enabled'] ) || ( null !== $combo['stock_qty'] && (int) $combo['stock_qty'] <= 0 );
    if ( $sold_out ) {
        wc_add_notice( '此規格組合目前無法選購（已額滿或已停用），請選擇其他組合。', 'error' );
        return false;
    }

    return $passed;
}

/**
 * 加入購物車時，把使用者選的規格值存進購物車項目資料。
 */
add_filter( 'woocommerce_add_cart_item_data', 'chao_gang_cheng_add_cart_item_spec_data', 10, 3 );
function chao_gang_cheng_add_cart_item_spec_data( $cart_item_data, $product_id, $variation_id ) {
    if ( ! chao_gang_cheng_product_uses_spec_options( $product_id ) ) {
        return $cart_item_data;
    }
    $selected = chao_gang_cheng_sanitize_spec_selection_from_post( $product_id );
    if ( ! empty( $selected ) ) {
        // 這組資料會被 WooCommerce 的 generate_cart_id() 一起雜湊進購物車項目
        // 的 key，所以不同規格選擇天生就會是購物車裡不同的一列，不會被
        // WooCommerce 誤判成同一個商品合併數量。
        $cart_item_data['ckc_spec_selected'] = $selected;
    }
    return $cart_item_data;
}

/**
 * 購物車頁／結帳頁「審核訂單」表格裡，在商品名稱下方顯示已選的規格
 * （例如「日期：8/15」「時間：11:00」），沒選的類別不顯示。
 */
add_filter( 'woocommerce_get_item_data', 'chao_gang_cheng_display_spec_selection_in_cart', 10, 2 );
function chao_gang_cheng_display_spec_selection_in_cart( $item_data, $cart_item ) {
    if ( empty( $cart_item['ckc_spec_selected'] ) || ! is_array( $cart_item['ckc_spec_selected'] ) ) {
        return $item_data;
    }
    foreach ( $cart_item['ckc_spec_selected'] as $selection ) {
        $item_data[] = array(
            'name'  => $selection['cat_label'],
            'value' => $selection['val_label'],
        );
    }
    return $item_data;
}

/**
 * 每次重新計算購物車金額時，如果這個購物車項目的規格選擇「完整命中」
 * 組合表裡的一列，套用該組合當下的 price_adjust；沒有完整命中就維持
 * 商品原價，不做任何調整。
 */
add_action( 'woocommerce_before_calculate_totals', 'chao_gang_cheng_apply_spec_price_adjust', 20 );
function chao_gang_cheng_apply_spec_price_adjust( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }
    if ( empty( $cart->cart_contents ) ) {
        return;
    }

    foreach ( $cart->cart_contents as $cart_item_key => &$cart_item ) {
        if ( empty( $cart_item['ckc_spec_selected'] ) || ! is_array( $cart_item['ckc_spec_selected'] ) ) {
            continue;
        }

        $product_id = $cart_item['product_id'];
        $key        = chao_gang_cheng_spec_full_match_key( $product_id, $cart_item['ckc_spec_selected'] );
        if ( null === $key ) {
            continue; // 部分選擇，不調整價格
        }

        $combos = chao_gang_cheng_get_spec_combinations( $product_id );
        $combo  = isset( $combos[ $key ] ) ? $combos[ $key ] : null;
        if ( ! $combo ) {
            continue;
        }

        // 一律用「重新讀取商品目前的價格」當基準，不是拿購物車項目已經被
        // 改過的價格再加一次，避免這個 hook 在同一個請求裡被觸發多次時
        // 越算越貴。
        $fresh_product = wc_get_product( $product_id );
        if ( ! $fresh_product ) {
            continue;
        }
        $base_price   = (float) $fresh_product->get_price();
        $adjust       = isset( $combo['price_adjust'] ) ? (float) $combo['price_adjust'] : 0;
        $final_price  = max( 0, $base_price + $adjust );

        // 同時把「原價」也設成跟最終價格一樣，讓這個購物車項目回報自己
        // 「沒有在特價」。原因：如果不這樣做，WooCommerce 的購物車/結帳
        // 頁樣板還是會照商品原本的特價設定，畫出「NT$350（劃線）→
        // NT$399」這種樣子——因為調整後的價格比商品原價還高，看起來會
        // 變成「打折後反而更貴」，容易讓客人誤會算錯了。規格加價本來就
        // 是跟「特價」不同的概念（已經有「日期：8/15」這類文字說明選了
        // 什麼），這裡直接顯示單一、明確的最終金額最不會造成誤會。
        $cart_item['data']->set_regular_price( $final_price );
        $cart_item['data']->set_sale_price( '' );
        $cart_item['data']->set_price( $final_price );
    }
    unset( $cart_item );
}

/**
 * 訂單成立時，把已選規格寫進訂單項目 meta（結帳頁「填寫訂購資訊」送出
 * 後，購物車轉成訂單的當下），讓訂單明細、後台訂單頁、通知信都看得到。
 * 用 add_meta_data()（不加底線開頭）而不是內部用的 _ckc_ 前綴 meta key，
 * 讓 WooCommerce 把這些當成一般的「品項屬性」自動顯示出來，作法跟商品
 * 變體屬性寫進訂單項目的方式一致。
 */
add_action( 'woocommerce_checkout_create_order_line_item', 'chao_gang_cheng_save_spec_selection_to_order_item', 10, 4 );
function chao_gang_cheng_save_spec_selection_to_order_item( $item, $cart_item_key, $values, $order ) {
    if ( empty( $values['ckc_spec_selected'] ) || ! is_array( $values['ckc_spec_selected'] ) ) {
        return;
    }
    foreach ( $values['ckc_spec_selected'] as $selection ) {
        $item->add_meta_data( $selection['cat_label'], $selection['val_label'] );
    }
}
