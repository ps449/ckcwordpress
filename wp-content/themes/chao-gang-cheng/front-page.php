<?php
/**
 * Theme Front Page Template
 *
 * @package Chao_Gang_Cheng
 */

get_header(); ?>

<!-- Top Limited Promotion Banner -->
<?php
$banner_image         = get_theme_mod( 'ckc_banner_image', get_template_directory_uri() . '/assets/images/slide-buffet.jpg' );
$banner_top_sub       = get_theme_mod( 'ckc_banner_top_sub', '【太陽百匯 SOLIS BUFFET】' );
$banner_sub2          = get_theme_mod( 'ckc_banner_sub2', '華麗盛宴・盡享海陸頂級美味' );
$banner_center_slogan = get_theme_mod( 'ckc_banner_center_slogan', '豪華龍蝦、生蠔、和牛、刺身' );
$banner_badge         = get_theme_mod( 'ckc_banner_badge', '限定活動' );
$banner_sub_slogan    = get_theme_mod( 'ckc_banner_sub_slogan', '全新呈獻！' );
$banner_title         = get_theme_mod( 'ckc_banner_title', '太陽百匯美食饗宴・平日單人餐券限時下殺' );
$banner_desc          = get_theme_mod( 'ckc_banner_desc', '台中吃到飽首選！鮮美海鮮、現切和牛、各國百匯佳餚，即刻搶購享最優折扣！' );
$banner_link          = get_theme_mod( 'ckc_banner_link', '' );

if ( empty( $banner_link ) && class_exists( 'WooCommerce' ) ) {
    $banner_link = get_permalink( wc_get_page_id( 'shop' ) );
}
?>
<section class="limited-promo-banner">
    <a href="<?php echo esc_url( $banner_link ); ?>">
        <div class="promo-banner-wrapper" style="background-image: url('<?php echo esc_url( $banner_image ); ?>');">
            <div class="promo-banner-overlay"></div>
            
            <!-- Top-Left Branding Logo -->
            <div class="banner-top-left-logo">
                <span class="brand-group">潮港城</span>
                <span class="brand-name">太陽百匯</span>
            </div>
            
            <div class="promo-banner-text">
                <!-- Top Center Subtitle Block -->
                <?php if ( ! empty( $banner_top_sub ) || ! empty( $banner_sub2 ) ) : ?>
                    <div class="banner-top-text">
                        <?php if ( ! empty( $banner_top_sub ) ) : ?>
                            <div class="top-sub"><?php echo esc_html( $banner_top_sub ); ?></div>
                        <?php endif; ?>
                        <?php if ( ! empty( $banner_sub2 ) ) : ?>
                            <div class="sub-2"><?php echo esc_html( $banner_sub2 ); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Main Center Slogan -->
                <?php if ( ! empty( $banner_center_slogan ) ) : ?>
                    <div class="banner-center-slogan"><?php echo esc_html( $banner_center_slogan ); ?></div>
                <?php endif; ?>
                
                <!-- Middle Badge and Sub-Slogan -->
                <div class="banner-mid-action">
                    <?php if ( ! empty( $banner_badge ) ) : ?>
                        <span class="badge"><?php echo esc_html( $banner_badge ); ?></span>
                    <?php endif; ?>
                    <?php if ( ! empty( $banner_sub_slogan ) ) : ?>
                        <span class="sub-slogan"><?php echo esc_html( $banner_sub_slogan ); ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Main Title and Slogan Description -->
                <h2 class="banner-main-title"><?php echo esc_html( $banner_title ); ?></h2>
                <p class="banner-description"><?php echo esc_html( $banner_desc ); ?></p>
            </div>
        </div>
    </a>
</section>

<!-- Monthly Promos List -->
<?php
$promo_text_1  = get_theme_mod( 'ckc_promo_text_1', '🔥 限時特惠｜太陽百匯平日單人餐券任選 3 張，結帳即享 95 折優惠！' );
$promo_link_1  = get_theme_mod( 'ckc_promo_link_1', '' );
$promo_color_1 = get_theme_mod( 'ckc_promo_color_1', '#FFE8CC' );

$promo_text_2  = get_theme_mod( 'ckc_promo_text_2', '🍲 本月限定｜招牌冷凍食品＋下酒菜任選 3 件 95 折，急速冷凍配送到家！' );
$promo_link_2  = get_theme_mod( 'ckc_promo_link_2', '' );
$promo_color_2 = get_theme_mod( 'ckc_promo_color_2', '#E8FFF6' );

$promo_text_3  = get_theme_mod( 'ckc_promo_text_3', '🍺 老饕最愛｜獨享紅燒牛肉爐＋經典老滷系列任選 2 件即享 9 折限時搶購！' );
$promo_link_3  = get_theme_mod( 'ckc_promo_link_3', '' );
$promo_color_3 = get_theme_mod( 'ckc_promo_color_3', '#FFECEC' );

$shop_url = class_exists( 'WooCommerce' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : '#';
if ( empty( $promo_link_1 ) ) $promo_link_1 = $shop_url . '?category=tickets';
if ( empty( $promo_link_2 ) ) $promo_link_2 = $shop_url . '?category=frozen';
if ( empty( $promo_link_3 ) ) $promo_link_3 = $shop_url . '?category=side-dishes';
?>
<section class="monthly-promo-section container">
    <ul class="promo-list">
        <li>
            <a class="promo-item" href="<?php echo esc_url( $promo_link_1 ); ?>" style="background: <?php echo esc_attr( $promo_color_1 ); ?>;">
                <?php echo esc_html( $promo_text_1 ); ?>
            </a>
        </li>
        <li>
            <a class="promo-item" href="<?php echo esc_url( $promo_link_2 ); ?>" style="background: <?php echo esc_attr( $promo_color_2 ); ?>;">
                <?php echo esc_html( $promo_text_2 ); ?>
            </a>
        </li>
        <li>
            <a class="promo-item" href="<?php echo esc_url( $promo_link_3 ); ?>" style="background: <?php echo esc_attr( $promo_color_3 ); ?>;">
                <?php echo esc_html( $promo_text_3 ); ?>
            </a>
        </li>
    </ul>
</section>

<!-- Hero Slider -->
<?php
$featured_products = [];
if ( class_exists( 'WooCommerce' ) ) {
    $args = array(
        'featured' => true,
        'status'   => 'publish',
        'limit'    => 5,
    );
    $featured_products = wc_get_products( $args );
}
?>
<section class="hero-slider" id="home-slider">
    <?php if ( ! empty( $featured_products ) ) : ?>
        <?php 
        $slide_index = 0;
        foreach ( $featured_products as $product ) : 
            $image_id = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_url( $image_id ) : wc_placeholder_img_src();
            $categories = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
            $category_name = ! empty( $categories ) ? $categories[0] : '精選商品';
            $short_desc = $product->get_short_description();
            if ( empty( $short_desc ) ) {
                $short_desc = wp_strip_all_tags( $product->get_description() );
                $short_desc = mb_strimwidth( $short_desc, 0, 160, '...' );
            }
            ?>
            <!-- Slide <?php echo $slide_index + 1; ?> -->
            <div class="slide<?php echo $slide_index === 0 ? ' active' : ''; ?>" style="background-image: url('<?php echo esc_url( $image_url ); ?>');">
                <div class="slide-overlay"></div>
                <div class="container" style="position: relative; height: 100%;">
                    <div class="slide-content">
                        <span class="slide-badge" style="background-color: var(--accent-color); color: var(--white); padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 700; text-transform: uppercase; margin-bottom: 15px; display: inline-block;"><?php echo esc_html( $category_name ); ?></span>
                        <h2><?php echo esc_html( $product->get_name() ); ?></h2>
                        <p><?php echo esc_html( $short_desc ); ?></p>
                        <a href="<?php echo esc_url( $product->get_permalink() ); ?>" class="btn">立即搶購</a>
                    </div>
                </div>
            </div>
            <?php 
            $slide_index++;
        endforeach; 
        ?>
    <?php else : ?>
        <!-- Fallback Slide 1 -->
        <div class="slide active" style="background-image: url('<?php echo esc_url( get_template_directory_uri() . '/assets/images/slide-buffet.jpg' ); ?>');">
            <div class="slide-overlay"></div>
            <div class="container" style="position: relative; height: 100%;">
                <div class="slide-content">
                    <h2>太陽百匯 SOLIS BUFFET</h2>
                    <p>海陸頂級美味盛宴，豪華龍蝦、生蠔、和牛、刺身現點現做！平日/假日餐券限時搶購中。</p>
                    <a href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>" class="btn">搶購優惠餐券</a>
                </div>
            </div>
        </div>

        <!-- Fallback Slide 2 -->
        <div class="slide" style="background-image: url('<?php echo esc_url( get_template_directory_uri() . '/assets/images/slide-frozen.jpg' ); ?>');">
            <div class="slide-overlay"></div>
            <div class="container" style="position: relative; height: 100%;">
                <div class="slide-content">
                    <h2>主廚嚴選 經典宅配</h2>
                    <p>將星級主廚的私房手路菜，以急速冷凍包裝宅配到家。招牌紅燒牛肉爐、佛跳牆，美味輕鬆上桌！</p>
                    <a href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>" class="btn btn-gold">選購冷凍美食</a>
                </div>
            </div>
        </div>

        <!-- Fallback Slide 3 -->
        <div class="slide" style="background-image: url('<?php echo esc_url( get_template_directory_uri() . '/assets/images/slide-line.jpg' ); ?>');">
            <div class="slide-overlay"></div>
            <div class="container" style="position: relative; height: 100%;">
                <div class="slide-content">
                    <h2>加入 LINE 好友領取 $100</h2>
                    <p>立即掃描加入潮港城餐飲集團官方 LINE 帳號，即可獲得線上商城 $100 折價券及最新優惠通知。</p>
                    <a href="https://line.me/R/ti/p/@rsh5501l" target="_blank" class="btn">立即加入好友</a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Slider Arrows -->
    <div class="slider-arrow slider-prev" id="slider-prev-btn">&lt;</div>
    <div class="slider-arrow slider-next" id="slider-next-btn">&gt;</div>

    <!-- Slider Dots -->
    <div class="slider-dots" id="slider-dots-container">
        <?php 
        $num_slides = ! empty( $featured_products ) ? count( $featured_products ) : 3;
        for ( $i = 0; $i < $num_slides; $i++ ) {
            $active_class = $i === 0 ? ' active' : '';
            echo '<div class="slider-dot' . $active_class . '" data-index="' . $i . '"></div>';
        }
        ?>
    </div>
</section>



<!-- Dynamic Product Category Showcases linked to Appearance > Menus (homepage-categories location) -->
<?php
$theme_locations = get_nav_menu_locations();
$menu_items = array();
if ( isset( $theme_locations['homepage-categories'] ) ) {
    $menu_obj = wp_get_nav_menu_object( $theme_locations['homepage-categories'] );
    if ( $menu_obj ) {
        $menu_items = wp_get_nav_menu_items( $menu_obj->term_id );
    }
}

$categories_to_show = array();
if ( ! empty( $menu_items ) ) {
    foreach ( $menu_items as $item ) {
        // Only fetch categories (product_cat taxonomy)
        if ( 'taxonomy' === $item->type && 'product_cat' === $item->object ) {
            $term_id = intval( $item->object_id );
            $term = get_term( $term_id, 'product_cat' );
            if ( $term && ! is_wp_error( $term ) ) {
                $categories_to_show[] = array(
                    'slug' => $term->slug,
                    'name' => $item->title ? $item->title : $term->name,
                    'link' => get_term_link( $term ),
                );
            }
        }
    }
}

// Fallback to default categories if no menu is assigned or menu is empty
if ( empty( $categories_to_show ) ) {
    $categories_to_show = array(
        array(
            'slug' => 'tickets',
            'name' => '太陽百匯餐券',
            'link' => class_exists( 'WooCommerce' ) ? get_term_link( 'tickets', 'product_cat' ) : '#',
        ),
        array(
            'slug' => 'frozen',
            'name' => '經典冷凍食品',
            'link' => class_exists( 'WooCommerce' ) ? get_term_link( 'frozen', 'product_cat' ) : '#',
        ),
        array(
            'slug' => 'side-dishes',
            'name' => '老滷系列',
            'link' => class_exists( 'WooCommerce' ) ? get_term_link( 'side-dishes', 'product_cat' ) : '#',
        ),
    );
}

$showcase_index = 0;
foreach ( $categories_to_show as $cat_data ) {
    $cat_slug = $cat_data['slug'];
    $cat_name = $cat_data['name'];
    $cat_link = $cat_data['link'];
    if ( is_wp_error( $cat_link ) ) {
        $cat_link = '#';
    }
    
    // Alternating backgrounds (Index 0 is white, Index 1 is light grey, etc.)
    $bg_style = ( $showcase_index % 2 === 1 ) ? ' style="background-color: var(--light-bg);"' : '';
    ?>
    <section class="product-showcase"<?php echo $bg_style; ?>>
        <div class="container">
            <div class="section-header">
                <h2><a href="<?php echo esc_url( $cat_link ); ?>">— &nbsp;&nbsp; <?php echo esc_html( $cat_name ); ?> &nbsp;&nbsp; —</a></h2>
            </div>

            <div class="products-grid">
                <?php
                if ( class_exists( 'WooCommerce' ) ) {
                    $args = array(
                        'limit'    => 4,
                        'status'   => 'publish',
                        'category' => array( $cat_slug ),
                    );
                    $products = wc_get_products( $args );

                    if ( ! empty( $products ) ) {
                        chao_gang_cheng_render_products( $products );
                    } else {
                        echo '<p style="grid-column: 1/-1; text-align: center; color: #999; padding: 40px 0;">目前尚無商品上架</p>';
                    }
                }
                ?>
            </div>
        </div>
    </section>
    <?php
    // Output banner if enabled for this position (1-based index: after showcase 0, showcase 1, etc.)
    $banner_num = $showcase_index + 1;
    if ( get_theme_mod( "ckc_cat_banner_enable_{$banner_num}", true ) ) {
        $cat_banner_img = get_theme_mod( "ckc_cat_banner_img_{$banner_num}" );
        $cat_banner_link = get_theme_mod( "ckc_cat_banner_link_{$banner_num}" );
        if ( ! empty( $cat_banner_img ) ) {
            // Convert to relative URLs to resolve correctly on both staging and production domains
            $cat_banner_img = str_replace( array( 'https://eshopckc.com', 'http://eshopckc.com' ), '', $cat_banner_img );
            if ( ! empty( $cat_banner_link ) ) {
                $cat_banner_link = str_replace( array( 'https://eshopckc.com', 'http://eshopckc.com' ), '', $cat_banner_link );
            }
            ?>
            <section class="category-divider-banner">
                <div class="container">
                    <?php if ( ! empty( $cat_banner_link ) ) : ?>
                        <a href="<?php echo esc_url( $cat_banner_link ); ?>" class="category-divider-banner-link">
                    <?php endif; ?>
                    <img class="cat-divider-banner-img" src="<?php echo esc_url( $cat_banner_img ); ?>" alt="分類 Banner" width="1200" height="669" loading="lazy" decoding="async">
                    <?php if ( ! empty( $cat_banner_link ) ) : ?>
                        </a>
                    <?php endif; ?>
                </div>
            </section>
            <?php
        }
    }
    
    $showcase_index++;
}
?>

<?php
/**
 * Helper function to render product cards
 */
function chao_gang_cheng_render_products( $products ) {
    foreach ( $products as $product ) {
        $image_id = $product->get_image_id();
        $image_url = '';
        if ( $image_id ) {
            $image = wp_get_attachment_image_src( $image_id, 'medium' );
            $image_url = $image ? $image[0] : '';
        }
        
        // Fallback to static asset if placeholder is used
        if ( ! $image_url ) {
            if ( $product->get_slug() === 'solis-buffet-weekday-ticket' ) {
                $image_url = get_template_directory_uri() . '/assets/images/ticket-weekday.jpg';
            } elseif ( $product->get_slug() === 'solis-buffet-weekend-ticket' ) {
                $image_url = get_template_directory_uri() . '/assets/images/ticket-weekend.jpg';
            } elseif ( $product->get_slug() === 'signature-beef-hotpot' ) {
                $image_url = get_template_directory_uri() . '/assets/images/product-beef.jpg';
            } elseif ( $product->get_slug() === 'chef-chicken-feet' ) {
                $image_url = get_template_directory_uri() . '/assets/images/product-chicken.jpg';
            } elseif ( $product->get_slug() === 'signature-buddha-soup' ) {
                $image_url = get_template_directory_uri() . '/assets/images/product-buddha.jpg';
            } else {
                $image_url = wc_placeholder_img_src();
            }
        }
        ?>
        <?php
        $is_out_of_stock = ! $product->is_in_stock();
        $card_classes = 'product-card' . ( $is_out_of_stock ? ' outofstock' : '' );
        ?>
        <div class="<?php echo esc_attr( $card_classes ); ?>">
            <a href="<?php echo esc_url( $product->get_permalink() ); ?>" class="product-image-link" style="position: relative; display: block;">
                <?php
                // Discount badge, consistent with the category-page sale flash
                if ( $product->is_on_sale() ) {
                    $badge_label = '特價';
                    $regular     = floatval( $product->get_regular_price() );
                    $sale        = floatval( $product->get_sale_price() );
                    if ( $regular > 0 && $sale > 0 && $sale < $regular ) {
                        $percent = round( ( 1 - $sale / $regular ) * 100 );
                        if ( $percent >= 1 ) {
                            $badge_label = '-' . $percent . '%';
                        }
                    }
                    echo '<span class="chao-onsale" style="position: absolute; top: 10px; left: 10px; z-index: 5; background: #dc2626; color: #fff; font-size: 12px; font-weight: 700; letter-spacing: 0.5px; padding: 5px 10px; border-radius: 14px; box-shadow: 0 2px 6px rgba(220,38,38,0.35); line-height: 1;">' . esc_html( $badge_label ) . '</span>';
                }
                ?>
                <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $product->get_name() ); ?>" width="300" height="300" loading="lazy" decoding="async">
            </a>
            <div class="product-details">
                <h3 class="product-title">
                    <a href="<?php echo esc_url( $product->get_permalink() ); ?>">
                        <?php echo esc_html( $product->get_name() ); ?>
                    </a>
                </h3>
                <div class="product-price-wrapper">
                    <span class="product-price">
                        <?php if ( $product->is_on_sale() ) : ?>
                            <del><?php echo wc_price( $product->get_regular_price() ); ?></del>
                            <ins style="text-decoration: none; margin-left: 5px;"><?php echo wc_price( $product->get_sale_price() ); ?></ins>
                        <?php else : ?>
                            <?php echo wc_price( $product->get_price() ); ?>
                        <?php endif; ?>
                    </span>
                </div>
                <?php if ( $is_out_of_stock ) : ?>
                    <a href="javascript:void(0);" class="add-to-cart-btn disabled" aria-label="已售完" style="pointer-events: none; background-color: #eaeaea !important; color: #888 !important; border: 1px solid #ddd !important; cursor: not-allowed !important;">已售完</a>
                <?php else : ?>
                    <a href="?add-to-cart=<?php echo esc_attr( $product->get_id() ); ?>" class="add-to-cart-btn add_to_cart_button ajax_add_to_cart" data-product_id="<?php echo esc_attr( $product->get_id() ); ?>" aria-label="加入購物車">加入購物車</a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
?>

<!-- Portfolio/Projects Section -->
<section class="home-portfolio-section" style="padding: 60px 0; background-color: var(--white); border-top: 1px solid var(--border-color);">
    <div class="container">
        <!-- Homepage News Banner -->
        <?php
        $news_banner_enable = get_theme_mod( 'ckc_news_banner_enable', true );
        $news_banner_img    = get_theme_mod( 'ckc_news_banner_img', '' );
        $news_banner_link   = get_theme_mod( 'ckc_news_banner_link', '' );
        
        if ( $news_banner_enable && ! empty( $news_banner_img ) ) : ?>
            <div class="home-news-banner" style="margin-bottom: 40px; text-align: center;">
                <?php if ( ! empty( $news_banner_link ) ) : ?>
                    <a href="<?php echo esc_url( $news_banner_link ); ?>" class="news-banner-link" style="display: block; width: 100%;">
                <?php endif; ?>
                
                <img class="news-banner-img" 
                     src="<?php echo esc_url( $news_banner_img ); ?>" 
                     alt="最新消息 Banner" 
                     width="1200" height="300" 
                     loading="lazy" decoding="async">
                     
                <?php if ( ! empty( $news_banner_link ) ) : ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php
        $portfolio_args = array(
            'post_type'      => 'jetpack-portfolio',
            'posts_per_page' => 4,
            'post_status'    => 'publish',
        );
        $portfolio_query = new WP_Query( $portfolio_args );
        ?>

        <div class="portfolio-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 30px;">
            <?php if ( $portfolio_query->have_posts() ) : ?>
                <?php while ( $portfolio_query->have_posts() ) : $portfolio_query->the_post(); 
                    $img_url = get_the_post_thumbnail_url( get_the_ID(), 'medium_large' );
                    if ( ! $img_url ) {
                        $img_url = wc_placeholder_img_src();
                    }
                    ?>
                    <div class="portfolio-card" style="background: var(--white); border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.02); transition: transform 0.3s ease, box-shadow 0.3s ease;">
                        <a href="<?php the_permalink(); ?>" style="display: block; overflow: hidden; height: 200px;">
                            <img src="<?php echo esc_url( $img_url ); ?>" alt="<?php the_title_attribute(); ?>" width="400" height="200" loading="lazy" decoding="async" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease;">
                        </a>
                        <div style="padding: 20px;">
                            <h3 style="font-size: 16px; font-weight: 700; margin-top: 0; margin-bottom: 10px;">
                                <a href="<?php the_permalink(); ?>" style="color: var(--primary-color); text-decoration: none;"><?php the_title(); ?></a>
                            </h3>
                            <p style="font-size: 13px; color: var(--text-muted); line-height: 1.6; margin: 0; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;"><?php echo wp_strip_all_tags( get_the_excerpt() ); ?></p>
                        </div>
                    </div>
                <?php endwhile; wp_reset_postdata(); ?>
            <?php else : ?>
                <!-- Fallback Mock Projects -->
                    <div class="portfolio-card" style="background: var(--white); border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.02); transition: transform 0.3s ease, box-shadow 0.3s ease;">
                    <div style="display: block; overflow: hidden; height: 200px;">
                        <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/slide-buffet.jpg' ); ?>" alt="太陽百匯奢華海鮮祭" width="400" height="200" loading="lazy" decoding="async" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease;">
                    </div>
                    <div style="padding: 20px;">
                        <h3 style="font-size: 16px; font-weight: 700; margin-top: 0; margin-bottom: 10px;">
                            <span style="color: var(--primary-color); text-decoration: none;">太陽百匯奢華海鮮祭</span>
                        </h3>
                        <p style="font-size: 13px; color: var(--text-muted); line-height: 1.6; margin: 0;">引進頂級生蠔與限量波士頓龍蝦，打造全台最奢華的吃到飽海鮮盛宴。</p>
                    </div>
                </div>

                <div class="portfolio-card" style="background: var(--white); border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.02); transition: transform 0.3s ease, box-shadow 0.3s ease;">
                    <div style="display: block; overflow: hidden; height: 200px;">
                        <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/slide-frozen.jpg' ); ?>" alt="經典宅配計劃" width="400" height="200" loading="lazy" decoding="async" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease;">
                    </div>
                    <div style="padding: 20px;">
                        <h3 style="font-size: 16px; font-weight: 700; margin-top: 0; margin-bottom: 10px;">
                            <span style="color: var(--primary-color); text-decoration: none;">手路菜冷凍宅配計劃</span>
                        </h3>
                        <p style="font-size: 13px; color: var(--text-muted); line-height: 1.6; margin: 0;">與在地小農合作，將經典年菜、紅燒牛肉爐新鮮冷凍，直送全台家門口。</p>
                    </div>
                </div>

                <div class="portfolio-card" style="background: var(--white); border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.02); transition: transform 0.3s ease, box-shadow 0.3s ease;">
                    <div style="display: block; overflow: hidden; height: 200px;">
                        <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/slide-line.jpg' ); ?>" alt="婚宴品牌升級" width="400" height="200" loading="lazy" decoding="async" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease;">
                    </div>
                    <div style="padding: 20px;">
                        <h3 style="font-size: 16px; font-weight: 700; margin-top: 0; margin-bottom: 10px;">
                            <span style="color: var(--primary-color); text-decoration: none;">潮港城婚宴 brand 升級</span>
                        </h3>
                        <p style="font-size: 13px; color: var(--text-muted); line-height: 1.6; margin: 0;">全新裝潢百萬燈光音響，首創沉浸式環景劇院婚宴體驗。</p>
                    </div>
                </div>

                <div class="portfolio-card" style="background: var(--white); border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.02); transition: transform 0.3s ease, box-shadow 0.3s ease;">
                    <div style="display: block; overflow: hidden; height: 200px;">
                        <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/product-beef.jpg' ); ?>" alt="主廚線上廚房" width="400" height="200" loading="lazy" decoding="async" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease;">
                    </div>
                    <div style="padding: 20px;">
                        <h3 style="font-size: 16px; font-weight: 700; margin-top: 0; margin-bottom: 10px;">
                            <span style="color: var(--primary-color); text-decoration: none;">主廚私房菜線上廚房</span>
                        </h3>
                        <p style="font-size: 13px; color: var(--text-muted); line-height: 1.6; margin: 0;">分享經典老饕下酒菜、經典紅燒牛肉爐的美味做法，在家輕鬆當主廚。</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<style>
    /* CSS-only hover effects for portfolio cards (replaces jQuery for better performance) */
    .portfolio-card:hover {
        transform: translateY(-5px) !important;
        box-shadow: 0 8px 25px rgba(0,0,0,0.08) !important;
    }
    .portfolio-card:hover img {
        transform: scale(1.05) !important;
    }
</style>

<!-- YouTube Feed Section -->
<section class="home-youtube-section" style="padding: 60px 0; background-color: #fafafa; border-top: 1px solid var(--border-color);">
    <div class="container">
        <div class="section-header" style="text-align: center; margin-bottom: 40px;">
            <div class="youtube-profile-header" style="text-align: center; margin-bottom: 12px;">
                <h2 style="font-size: 24px; font-weight: 700; color: var(--primary-color); margin: 0 0 5px 0;">潮港城餐飲集團深耕台中三十年</h2>
                <p style="font-size: 14px; color: var(--text-muted); margin: 0;">邀你共同體驗、新鮮、誠信老朋友料理</p>
            </div>
            <a href="https://www.youtube.com/@ckcgroup" target="_blank" class="btn" style="background-color: #ff0000; color: var(--white); padding: 6px 16px; font-size: 13px; display: inline-flex; align-items: center; gap: 8px; border-radius: 20px; border: none; text-decoration: none;">
                訂閱 YouTube 頻道
            </a>
        </div>

        <?php
        if ( shortcode_exists( 'yotuwp' ) ) {
            echo do_shortcode( '[yotuwp type="channel" id="UCICXOKIAEFoX0ZZEkKdkbHA"]' );
        } elseif ( shortcode_exists( 'youtube-feed' ) ) {
            echo do_shortcode( '[youtube-feed feed="2"]' );
        } else {
            $yt_videos = chao_gang_cheng_get_youtube_videos();
            ?>
            <div class="youtube-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 25px;">
                <?php if ( ! empty( $yt_videos ) ) : ?>
                    <?php foreach ( $yt_videos as $video ) : ?>
                        <a href="<?php echo esc_url( $video['link'] ); ?>" target="_blank" class="youtube-item" style="position: relative; display: block; background: var(--white); border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.02); text-decoration: none; transition: transform 0.3s ease, box-shadow 0.3s ease;">
                            <div class="yt-thumb-wrapper" style="position: relative; aspect-ratio: 16/9; overflow: hidden; background-color: #000;">
                                <img src="<?php echo esc_url( $video['thumbnail'] ); ?>" alt="<?php echo esc_attr( $video['title'] ); ?>" width="480" height="270" loading="lazy" decoding="async" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease;">
                                <div class="yt-play-overlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.15); display: flex; align-items: center; justify-content: center; transition: background-color 0.3s ease;">
                                    <div class="yt-play-button" style="background-color: rgba(255,0,0,0.9); width: 48px; height: 32px; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: var(--white); transition: transform 0.3s ease, background-color 0.3s ease;">
                                        <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24" style="margin-left: 2px;"><path d="M8 5v14l11-7z"/></svg>
                                    </div>
                                </div>
                            </div>
                            <div style="padding: 15px;">
                                <h3 class="yt-title" style="font-size: 14px; font-weight: 600; color: var(--primary-color); line-height: 1.5; margin: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 42px; transition: color 0.3s ease; text-align: left;"><?php echo esc_html( $video['title'] ); ?></h3>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else : ?>
                    <!-- Fallback Mock Videos -->
                    <a href="https://www.youtube.com/@ckcgroup" target="_blank" class="youtube-item" style="position: relative; display: block; background: var(--white); border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.02); text-decoration: none; transition: transform 0.3s ease, box-shadow 0.3s ease;">
                        <div class="yt-thumb-wrapper" style="position: relative; aspect-ratio: 16/9; overflow: hidden; background-color: #000;">
                            <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/slide-buffet.jpg' ); ?>" alt="太陽百匯釜山海鮮季" width="480" height="270" loading="lazy" decoding="async" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease;">
                            <div class="yt-play-overlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.15); display: flex; align-items: center; justify-content: center; transition: background-color 0.3s ease;">
                                <div class="yt-play-button" style="background-color: rgba(255,0,0,0.9); width: 48px; height: 32px; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: var(--white); transition: transform 0.3s ease, background-color 0.3s ease;">
                                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24" style="margin-left: 2px;"><path d="M8 5v14l11-7z"/></svg>
                                </div>
                            </div>
                        </div>
                        <div style="padding: 15px;">
                            <h3 class="yt-title" style="font-size: 14px; font-weight: 600; color: var(--primary-color); line-height: 1.5; margin: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 42px; transition: color 0.3s ease; text-align: left;">太陽百匯釜山海鮮季新上市！帶您直擊頂級生猛海鮮盛宴</h3>
                        </div>
                    </a>
                    <a href="https://www.youtube.com/@ckcgroup" target="_blank" class="youtube-item" style="position: relative; display: block; background: var(--white); border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.02); text-decoration: none; transition: transform 0.3s ease, box-shadow 0.3s ease;">
                        <div class="yt-thumb-wrapper" style="position: relative; aspect-ratio: 16/9; overflow: hidden; background-color: #000;">
                            <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/slide-frozen.jpg' ); ?>" alt="主廚私房佛跳牆大解密" width="480" height="270" loading="lazy" decoding="async" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease;">
                            <div class="yt-play-overlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.15); display: flex; align-items: center; justify-content: center; transition: background-color 0.3s ease;">
                                <div class="yt-play-button" style="background-color: rgba(255,0,0,0.9); width: 48px; height: 32px; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: var(--white); transition: transform 0.3s ease, background-color 0.3s ease;">
                                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24" style="margin-left: 2px;"><path d="M8 5v14l11-7z"/></svg>
                                </div>
                            </div>
                        </div>
                        <div style="padding: 15px;">
                            <h3 class="yt-title" style="font-size: 14px; font-weight: 600; color: var(--primary-color); line-height: 1.5; margin: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 42px; transition: color 0.3s ease; text-align: left;">國宴主廚大公開！星級極品佛跳牆的備料與慢火熬煮秘訣</h3>
                        </div>
                    </a>
                    <a href="https://www.youtube.com/@ckcgroup" target="_blank" class="youtube-item" style="position: relative; display: block; background: var(--white); border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.02); text-decoration: none; transition: transform 0.3s ease, box-shadow 0.3s ease;">
                        <div class="yt-thumb-wrapper" style="position: relative; aspect-ratio: 16/9; overflow: hidden; background-color: #000;">
                            <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/slide-line.jpg' ); ?>" alt="潮港城全新沉浸式劇場婚禮體驗" width="480" height="270" loading="lazy" decoding="async" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease;">
                            <div class="yt-play-overlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.15); display: flex; align-items: center; justify-content: center; transition: background-color 0.3s ease;">
                                <div class="yt-play-button" style="background-color: rgba(255,0,0,0.9); width: 48px; height: 32px; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: var(--white); transition: transform 0.3s ease, background-color 0.3s ease;">
                                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24" style="margin-left: 2px;"><path d="M8 5v14l11-7z"/></svg>
                                </div>
                            </div>
                        </div>
                        <div style="padding: 15px;">
                            <h3 class="yt-title" style="font-size: 14px; font-weight: 600; color: var(--primary-color); line-height: 1.5; margin: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 42px; transition: color 0.3s ease; text-align: left;">百萬光影與巨幕環景！直擊潮港城最新概念沉浸式婚宴</h3>
                        </div>
                    </a>
                    <a href="https://www.youtube.com/@ckcgroup" target="_blank" class="youtube-item" style="position: relative; display: block; background: var(--white); border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.02); text-decoration: none; transition: transform 0.3s ease, box-shadow 0.3s ease;">
                        <div class="yt-thumb-wrapper" style="position: relative; aspect-ratio: 16/9; overflow: hidden; background-color: #000;">
                            <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/product-beef.jpg' ); ?>" alt="招牌紅燒牛肉爐備料工序" width="480" height="270" loading="lazy" decoding="async" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease;">
                            <div class="yt-play-overlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.15); display: flex; align-items: center; justify-content: center; transition: background-color 0.3s ease;">
                                <div class="yt-play-button" style="background-color: rgba(255,0,0,0.9); width: 48px; height: 32px; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: var(--white); transition: transform 0.3s ease, background-color 0.3s ease;">
                                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24" style="margin-left: 2px;"><path d="M8 5v14l11-7z"/></svg>
                                </div>
                            </div>
                        </div>
                        <div style="padding: 15px;">
                            <h3 class="yt-title" style="font-size: 14px; font-weight: 600; color: var(--primary-color); line-height: 1.5; margin: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 42px; transition: color 0.3s ease; text-align: left;">極致濃郁！主廚揭秘招牌紅燒牛肉爐十二道中藥慢燉工序</h3>
                        </div>
                    </a>
                <?php endif; ?>
            </div>

            <style>
                /* CSS-only hover effects for YouTube items (replaces jQuery for better performance) */
                .youtube-item:hover {
                    transform: translateY(-5px) !important;
                    box-shadow: 0 8px 25px rgba(0,0,0,0.08) !important;
                }
                .youtube-item:hover .yt-thumb-wrapper img {
                    transform: scale(1.05) !important;
                }
                .youtube-item:hover .yt-play-button {
                    background-color: #ff0000 !important;
                    transform: scale(1.1) !important;
                }
                .youtube-item:hover .yt-title {
                    color: #ff0000 !important;
                }
            </style>
            <?php
        }
        ?>
    </div>
</section>

<!-- Social Links Section -->
<section class="social-links-section" style="margin-top: 20px; margin-bottom: 20px;">
    <div class="container social-grid">
        <a href="https://www.facebook.com/ckcfood/" target="_blank" class="social-card fb-card">
            <div class="social-icon-wrapper fb-color">
                <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"/></svg>
            </div>
            <h4>官方 Facebook</h4>
            <p>追蹤最新活動與菜色公告</p>
        </a>
        <a href="https://www.instagram.com/ckc_banquet/" target="_blank" class="social-card ig-card">
            <div class="social-icon-wrapper ig-color">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
            </div>
            <h4>官方 Instagram</h4>
            <p>精美菜色照片與打卡資訊</p>
        </a>
        <a href="https://line.me/R/ti/p/@rsh5501l" target="_blank" class="social-card ln-card">
            <div class="social-icon-wrapper ln-color">
                <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2c5.522 0 10 3.978 10 8.878 0 4.364-3.55 8.046-8.348 8.756-.374.08-.88.252-1.008.574-.116.29-.074.744-.036 1.036l.134.81c.046.29.214 1.136-1.008.618-1.222-.516-6.596-3.896-8.996-6.66-1.658-1.822-2.746-3.664-2.746-5.714 0-4.9 4.478-8.878 10-8.878z"/></svg>
            </div>
            <h4>官方 LINE 帳號</h4>
            <p>一對一客服與專屬優惠券</p>
        </a>
        <a href="https://www.youtube.com/@ckcgroup" target="_blank" class="social-card yt-card">
            <div class="social-icon-wrapper yt-color" style="display: flex; align-items: center; justify-content: center;">
                <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/youtube.png' ); ?>" alt="YouTube" width="24" height="24" loading="lazy" decoding="async" style="width: 24px; height: 24px;">
            </div>
            <h4>官方 YouTube</h4>
            <p>主廚做菜秘訣與宣傳影片</p>
        </a>
    </div>
</section>

<!-- Inline JS Slider Script -->
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    var slides = document.querySelectorAll('#home-slider .slide');
    var dots = document.querySelectorAll('#home-slider .slider-dot');
    var prevBtn = document.getElementById('slider-prev-btn');
    var nextBtn = document.getElementById('slider-next-btn');
    var currentSlide = 0;
    var slideInterval = setInterval(nextSlide, 5000);

    function goToSlide(n) {
        slides[currentSlide].classList.remove('active');
        dots[currentSlide].classList.remove('active');
        currentSlide = (n + slides.length) % slides.length;
        slides[currentSlide].classList.add('active');
        dots[currentSlide].classList.add('active');
    }

    function nextSlide() {
        goToSlide(currentSlide + 1);
    }

    function prevSlide() {
        goToSlide(currentSlide - 1);
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function() {
            nextSlide();
            clearInterval(slideInterval);
            slideInterval = setInterval(nextSlide, 5000);
        });
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', function() {
            prevSlide();
            clearInterval(slideInterval);
            slideInterval = setInterval(nextSlide, 5000);
        });
    }

    dots.forEach(function(dot, idx) {
        dot.addEventListener('click', function() {
            goToSlide(idx);
            clearInterval(slideInterval);
            slideInterval = setInterval(nextSlide, 5000);
        });
    });
});
</script>

<?php get_footer(); ?>
