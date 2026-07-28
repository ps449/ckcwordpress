<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 購物車頁添加商品原價顯示
 */
add_filter( 'woocommerce_cart_item_price', 'ckc_cart_item_price_add_regular', 10, 3 );
function ckc_cart_item_price_add_regular( $price_html, $cart_item, $cart_item_key ) {
    $product = $cart_item['data'];
    
    // 如果該商品有特價，則在價格上方顯示有刪除線的原價
    if ( $product->is_on_sale() ) {
        // 1. 嘗試抓取最原始的原價 (Raw Data)
        $regular_price = (float) $product->get_regular_price( 'edit' );
        if ( ! $regular_price ) {
            $regular_price = (float) $product->get_price( 'edit' );
        }
        if ( ! $regular_price ) {
            $db_product = wc_get_product( $product->get_id() );
            if ( $db_product ) {
                $regular_price = (float) $db_product->get_regular_price( 'edit' );
                if ( ! $regular_price ) {
                    $regular_price = (float) $db_product->get_price( 'edit' );
                }
            }
        }

        if ( $regular_price > 0 ) {
            $clean_regular = strip_tags( wc_price( $regular_price ) );
            
            // 檢查是否真的等於現在的小計
            if ( strip_tags( $price_html ) === $clean_regular ) {
                return $price_html;
            }
            
            // 組合字串
            return '<del style="color:#999; font-size:0.85em; display:block; line-height: 1; margin-bottom:2px;">' . $clean_regular . '</del>' . 
                   '<ins style="text-decoration:none; display:block; font-weight:700;">' . $price_html . '</ins>';
        }
    }
    
    return $price_html;
}

/**
 * 購物車專屬樣式 (包含恢復上一步按鈕樣式)
 */
add_action( 'wp_head', 'ckc_cart_custom_styles', 100 );
function ckc_cart_custom_styles() {
    if ( ! is_cart() ) {
        return;
    }
    ?>
    <style>
    /* 恢復上一步？ 樣式修改為按鈕 (配合移除提示的紅色調) */
    .woocommerce-message a.restore-item {
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        background-color: #fef2f2 !important; /* 淡紅色背景 */
        color: #dc2626 !important; /* 紅色文字 */
        border: 1px solid #fca5a5 !important; /* 紅色邊框 */
        padding: 6px 16px !important;
        border-radius: 6px;
        text-decoration: none !important;
        font-size: 13px;
        font-weight: 500;
        margin-left: 10px;
        line-height: 1.5;
        transition: all 0.2s ease;
        float: right;
        margin-top: -4px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
    .woocommerce-message a.restore-item:hover {
        background-color: #fee2e2 !important; /* hover 加深背景 */
        color: #b91c1c !important; /* hover 加深文字 */
        border-color: #f87171 !important; /* hover 加深邊框 */
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    
    /* 清除浮動 */
    .woocommerce-message::after {
        content: "";
        display: table;
        clear: both;
    }

    /* 手機版優化排版 */
    @media (max-width: 768px) {
        .woocommerce-message a.restore-item {
            float: none;
            display: flex !important;
            width: 100%;
            margin-left: 0;
            margin-top: 12px;
            box-sizing: border-box;
        }
    }
    </style>
    <?php
}
