<?php
/**
 * WooCommerce Template Wrapper
 *
 * @package Chao_Gang_Cheng
 */

get_header(); ?>

<?php if ( is_shop() || is_product_taxonomy() ) : ?>
    <!-- Category Hero Banner -->
    <?php
    $banner_image_url = '';
    $banner_title = '';
    $banner_desc = '';

    if ( is_product_category() ) {
        $queried_object = get_queried_object();
        $term_id = $queried_object->term_id;
        $banner_title = $queried_object->name;
        $banner_desc = $queried_object->description;

        // 1. 優先使用後台「編輯分類」頁面設定的專屬 Banner 圖片
        //    （獨立於分類縮圖之外，見 includes/admin/category-banner.php）
        if ( function_exists( 'ckc_get_category_banner_url' ) ) {
            $banner_image_url = ckc_get_category_banner_url( $term_id );
        }

        // 2. 沒設定的話，沿用 WooCommerce 分類本身的縮圖
        if ( ! $banner_image_url ) {
            $thumbnail_id = get_term_meta( $term_id, 'thumbnail_id', true );
            if ( $thumbnail_id ) {
                $banner_image_url = wp_get_attachment_url( $thumbnail_id );
            }
        }

        // 3. 再沒有的話，才落到少數分類寫死的預設圖／通用圖
        if ( ! $banner_image_url ) {
            $slug = $queried_object->slug;
            if ( $slug === 'tickets' ) {
                $banner_image_url = get_template_directory_uri() . '/assets/images/slide-buffet.jpg';
                $banner_desc = empty($banner_desc) ? '線上購買最划算，平假日優惠餐券現正熱銷' : $banner_desc;
            } elseif ( $slug === 'frozen' ) {
                $banner_image_url = get_template_directory_uri() . '/assets/images/slide-frozen.jpg';
                $banner_desc = empty($banner_desc) ? '一斤肉牛肉爐、年菜手路菜，產地冷凍宅配' : $banner_desc;
            } elseif ( $slug === 'side-dishes' ) {
                $banner_image_url = get_template_directory_uri() . '/assets/images/slide-line.jpg';
                $banner_desc = empty($banner_desc) ? '主廚私房香滷鳳爪、加碼老饕下酒菜系列' : $banner_desc;
            } else {
                $banner_image_url = get_template_directory_uri() . '/assets/images/slide-line.jpg';
            }
        }
    } elseif ( is_shop() ) {
        $shop_id = wc_get_page_id( 'shop' );
        $banner_title = get_the_title( $shop_id );
        $shop_post = get_post( $shop_id );
        $banner_desc = $shop_post ? trim( strip_tags( $shop_post->post_content ) ) : '';

        // 商店主頁 Banner 圖片：改成後台固定設定（商店頁編輯畫面的 meta box，
        // 見 includes/admin/category-banner.php），取代原本「每次隨機抽一張
        // 分類縮圖」的做法——同一頁每次重新整理看到不同圖，不利品牌一致性。
        $banner_image_url = function_exists( 'ckc_get_shop_banner_url' ) ? ckc_get_shop_banner_url() : '';
        if ( ! $banner_image_url ) {
            $banner_image_url = get_template_directory_uri() . '/assets/images/slide-line.jpg';
        }
    }
    ?>

    <?php // 純 Banner 圖片，不疊加深色圖層也不疊加文字（標題/說明已經在下方 woocommerce_content() 的頁面標題重複顯示過一次，這裡不用再疊一次）。 ?>
    <?php if ( $banner_image_url ) : ?>
        <div class="category-hero-banner" style="background-image: url('<?php echo esc_url( $banner_image_url ); ?>');"></div>
    <?php endif; ?>

    <div class="woocommerce-page-wrapper archive-layout">
        <div class="container shop-layout-container">
            <aside class="shop-sidebar">
                <!-- 1. Category Navigation (Title: 所有分類) -->
                <div class="widget widget_nav_menu" style="margin-bottom: 35px;">
                    <h3 class="widget-title" style="font-size: 16px; font-weight: bold; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">所有分類</h3>
                    <?php
                    wp_nav_menu( array(
                        'theme_location' => 'footer',
                        'menu_class'     => 'shop-sidebar-menu',
                        'fallback_cb'    => 'chao_gang_cheng_sidebar_fallback_menu',
                    ) );
                    ?>
                </div>

                <!-- 2. WooCommerce Filters -->
                <?php if ( is_active_sidebar( 'shop-sidebar' ) ) : ?>
                    <?php dynamic_sidebar( 'shop-sidebar' ); ?>
                <?php else : ?>
                    <!-- Programmatic default fallbacks if widgets aren't set in WordPress yet -->
                    <?php
                    // Active Filters widget
                    if ( class_exists( 'WC_Widget_Layered_Nav_Filters' ) ) {
                        the_widget( 'WC_Widget_Layered_Nav_Filters', array( 'title' => '已選篩選條件' ) );
                    }
                    // Price Filter widget
                    if ( class_exists( 'WC_Widget_Price_Filter' ) ) {
                        the_widget( 'WC_Widget_Price_Filter', array( 'title' => '依價格篩選' ) );
                    }
                    ?>
                <?php endif; ?>
            </aside>

            <main class="shop-main-content">
                <?php woocommerce_content(); ?>
            </main>
        </div>
    </div>
<?php else : ?>
    <div class="woocommerce-page-wrapper">
        <div class="container">
            <?php woocommerce_content(); ?>
        </div>
    </div>
<?php endif; ?>

<?php get_footer(); ?>
