<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 商品「二層規格選項」庫存扣減與超賣防護（第 4 期）。
 *
 * 什麼時候扣庫存／回補庫存：
 * - 扣庫存：跟 WooCommerce 原生商品庫存同一個時間點——訂單的庫存被
 *   「正式扣減」的時候（通常是付款完成、訂單狀態轉成處理中/已完成），
 *   掛在 woocommerce_reduce_order_stock。
 * - 回補庫存：訂單被取消或退款、WooCommerce 把先前扣掉的庫存加回去的
 *   時候，掛在 woocommerce_restore_order_stock。
 *
 * 為什麼不是在「加入購物車」或「送出訂單」那一刻就扣庫存：
 * 那樣等於要做一整套「結帳中途暫時鎖庫存、放棄結帳或逾時要自動解鎖」
 * 的預留系統（WooCommerce 自己對「原生商品庫存」有這一套，是額外一張
 * 資料庫表＋逾時釋放的機制，本身就相當複雜）。這裡選擇跟 WooCommerce
 * 原生庫存同一個時間點扣，理由：
 * (1) 邏輯簡單、行為可預期，不會有「訂單一直沒付款但庫存卻被鎖住，
 *     其他人明明看得到規格選項卻選不到」的副作用；
 * (2) 加入購物車時已經有即時庫存檢查
 *     （product-spec-options-cart.php 的 woocommerce_add_to_cart_validation，
 *     連「這次要買的數量夠不夠庫存」都會檢查），可以擋掉大部分
 *     「明知已經額滿還硬加入購物車」的情況。
 * 代價：在「很多人同時搶最後幾個名額、且短時間內一起完成付款」這種
 * 極端情況下，還是有機率會有一兩筆訂單扣不到庫存——這種情況這裡選擇
 * 讓訂單照樣成立（不影響已經收到的款項、不讓結帳流程本身失敗），只在
 * 訂單留一則附註提醒管理員注意，由人工決定怎麼處理。
 *
 * 庫存本身怎麼扣：見 chao_gang_cheng_adjust_spec_combo_stock()
 * （product-spec-options.php），用資料庫層級的條件式 UPDATE 做原子扣減，
 * 避免併發扣成負數。
 */

add_action( 'woocommerce_reduce_order_stock', 'chao_gang_cheng_reduce_spec_combo_stock' );
function chao_gang_cheng_reduce_spec_combo_stock( $order ) {
    chao_gang_cheng_adjust_order_spec_combo_stock( $order, -1 );
}

add_action( 'woocommerce_restore_order_stock', 'chao_gang_cheng_restore_spec_combo_stock' );
function chao_gang_cheng_restore_spec_combo_stock( $order ) {
    chao_gang_cheng_adjust_order_spec_combo_stock( $order, 1 );
}

/**
 * @param WC_Order $order
 * @param int      $direction -1 = 扣庫存，1 = 回補庫存。
 */
function chao_gang_cheng_adjust_order_spec_combo_stock( $order, $direction ) {
    if ( ! $order instanceof WC_Order ) {
        return;
    }

    // 用訂單項目自己的 meta 記錄「這個品項的組合庫存扣過了沒」，避免
    // 同一筆訂單的 reduce/restore 事件因為某些流程重複觸發（例如手動
    // 在後台改了兩次訂單狀態），造成同一筆訂單被重複扣庫存或重複回補。
    $flag_key = '_ckc_spec_stock_reduced';

    foreach ( $order->get_items() as $item ) {
        if ( ! $item instanceof WC_Order_Item_Product ) {
            continue;
        }
        $selected = $item->get_meta( '_ckc_spec_selected', true );
        if ( empty( $selected ) || ! is_array( $selected ) ) {
            continue; // 這個品項沒有規格選擇資料，跟這個功能無關
        }

        $product_id = $item->get_product_id();
        if ( ! $product_id || ! chao_gang_cheng_product_uses_spec_options( $product_id ) ) {
            continue;
        }

        $key = chao_gang_cheng_spec_full_match_key( $product_id, $selected );
        if ( null === $key ) {
            continue; // 下單當時是部分選擇，沒有完整命中組合，不用管庫存
        }

        $combos = chao_gang_cheng_get_spec_combinations( $product_id );
        if ( ! isset( $combos[ $key ] ) || null === $combos[ $key ]['stock_qty'] ) {
            continue; // 這個組合不限制庫存（或組合已經被刪掉），不用處理
        }

        $already_reduced = $item->get_meta( $flag_key, true );
        $qty             = (int) $item->get_quantity();

        if ( $direction < 0 ) {
            if ( $already_reduced || $qty <= 0 ) {
                continue;
            }
            $ok = chao_gang_cheng_adjust_spec_combo_stock( $product_id, $key, -$qty );
            if ( ! $ok ) {
                $order->add_order_note(
                    sprintf(
                        '規格組合庫存扣減失敗（品項：%s，可能是庫存已經被搶完），請人工確認實際庫存與這筆訂單是否要另外處理。',
                        $item->get_name()
                    )
                );
            }
            $item->add_meta_data( $flag_key, 'yes', true );
            $item->save();
        } else {
            if ( ! $already_reduced ) {
                continue; // 沒扣過，不需要回補
            }
            chao_gang_cheng_adjust_spec_combo_stock( $product_id, $key, $qty );
            $item->delete_meta_data( $flag_key );
            $item->save();
        }
    }
}
