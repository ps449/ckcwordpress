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
        
        // Try getting term thumbnail from WooCommerce settings
        $thumbnail_id = get_term_meta( $term_id, 'thumbnail_id', true );
        if ( $thumbnail_id ) {
            $banner_image_url = wp_get_attachment_url( $thumbnail_id );
        }
        
        // Predefined fallback banners based on categories
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
        
        // Randomly pick an image from the existing categories
        $banner_image_url = '';
        $categories = get_terms( array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        ) );
        $category_images = array();
        if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
            foreach ( $categories as $cat ) {
                $thumbnail_id = get_term_meta( $cat->term_id, 'thumbnail_id', true );
                if ( $thumbnail_id ) {
                    $img_url = wp_get_attachment_url( $thumbnail_id );
                    if ( $img_url ) {
                        $category_images[] = $img_url;
                    }
                }
            }
        }
        if ( ! empty( $category_images ) ) {
            $banner_image_url = $category_images[ array_rand( $category_images ) ];
        } else {
            $banner_image_url = get_template_directory_uri() . '/assets/images/slide-line.jpg';
        }
    }
    ?>

    <?php if ( $banner_image_url ) : ?>
        <div class="category-hero-banner" style="background-image: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('<?php echo esc_url( $banner_image_url ); ?>');">
            <div class="banner-content">
                <h1 class="banner-title"><?php echo esc_html( $banner_title ); ?></h1>
                <?php if ( $banner_desc ) : ?>
                    <p class="banner-desc"><?php echo esc_html( $banner_desc ); ?></p>
                <?php endif; ?>
            </div>
        </div>
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
