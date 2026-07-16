<?php
/**
 * Displayed when no products are found matching the current query.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ckc-no-products-found">
    <div class="ckc-no-products-illustration">
        <svg width="240" height="170" viewBox="0 0 240 170" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- Browser Window -->
            <rect x="20" y="20" width="180" height="120" rx="8" stroke="#9fa8ae" stroke-width="3" fill="#ffffff" />
            <!-- Browser Header Line -->
            <line x1="20" y1="44" x2="200" y2="44" stroke="#9fa8ae" stroke-width="3" />
            <!-- Header Dots -->
            <circle cx="36" cy="32" r="3" fill="#9fa8ae" />
            <circle cx="48" cy="32" r="3" fill="#9fa8ae" />
            <circle cx="60" cy="32" r="3" fill="#9fa8ae" />
            
            <!-- Image Card inside Browser -->
            <rect x="36" y="60" width="48" height="36" rx="4" stroke="#9fa8ae" stroke-width="3" fill="#ffffff" />
            <!-- Text lines inside Browser -->
            <line x1="36" y1="108" x2="84" y2="108" stroke="#9fa8ae" stroke-width="3" stroke-linecap="round" />
            <line x1="36" y1="120" x2="108" y2="120" stroke="#9fa8ae" stroke-width="3" stroke-linecap="round" />
            
            <!-- Magnifying Glass -->
            <!-- Glass Circle (white fill masks browser lines behind it) -->
            <circle cx="160" cy="100" r="24" stroke="#9fa8ae" stroke-width="4.5" fill="#ffffff" />
            <!-- Glass Handle -->
            <line x1="177" y1="117" x2="205" y2="145" stroke="#9fa8ae" stroke-width="4.5" stroke-linecap="round" />
        </svg>
    </div>
    
    <p class="ckc-no-products-message">
        <?php
        $search_query = get_search_query();
        if ( ! empty( $search_query ) ) {
            printf(
                /* translators: %s: search query */
                esc_html__( '很抱歉，找不到 %s 請重新輸入搜尋。', 'woocommerce' ),
                esc_html( $search_query )
            );
        } else {
            esc_html_e( '很抱歉，找不到相關商品，請重新輸入搜尋。', 'woocommerce' );
        }
        ?>
    </p>
</div>

<style>
.ckc-no-products-found {
    text-align: center;
    padding: 80px 20px;
    background: #ffffff;
    border-radius: 8px;
    margin: 20px auto;
    max-width: 800px;
}
.ckc-no-products-illustration {
    margin-bottom: 40px;
    display: flex;
    justify-content: center;
}
.ckc-no-products-illustration svg {
    max-width: 100%;
    height: auto;
}
.ckc-no-products-message {
    font-size: 18px;
    color: #8c969c;
    line-height: 1.6;
    margin: 0;
}
@media (max-width: 768px) {
    .ckc-no-products-found {
        padding: 50px 15px;
    }
    .ckc-no-products-message {
        font-size: 16px;
    }
}
</style>
