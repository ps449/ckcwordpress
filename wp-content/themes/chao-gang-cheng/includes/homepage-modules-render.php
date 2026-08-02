<?php
/**
 * 首頁模塊 — 前台渲染函式
 *
 * 每個函式對應 includes/admin/homepage-builder.php 註冊表中的一種模塊類型，
 * 命名規則：ckc_render_module_{type}( array $settings )
 *
 * 內容大致沿用原本 front-page.php 寫死的區塊 HTML，只是改成讀取
 * 模塊的 $settings 陣列，而不是直接呼叫 get_theme_mod()。
 *
 * @package Chao_Gang_Cheng
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* -------------------------------------------------------------------------
 * 1. 主視覺橫幅 Banner
 * ---------------------------------------------------------------------- */
function ckc_render_module_banner( $settings ) {
    $banner_image         = isset( $settings['image'] ) ? $settings['image'] : get_template_directory_uri() . '/assets/images/slide-buffet.jpg';
    $banner_top_sub       = isset( $settings['top_sub'] ) ? $settings['top_sub'] : '';
    $banner_sub2          = isset( $settings['sub2'] ) ? $settings['sub2'] : '';
    $banner_center_slogan = isset( $settings['center_slogan'] ) ? $settings['center_slogan'] : '';
    $banner_badge         = isset( $settings['badge'] ) ? $settings['badge'] : '';
    $banner_sub_slogan    = isset( $settings['sub_slogan'] ) ? $settings['sub_slogan'] : '';
    $banner_title         = isset( $settings['title'] ) ? $settings['title'] : '';
    $banner_desc          = isset( $settings['desc'] ) ? $settings['desc'] : '';
    $banner_link          = isset( $settings['link'] ) ? $settings['link'] : '';

    if ( empty( $banner_link ) && class_exists( 'WooCommerce' ) ) {
        $banner_link = get_permalink( wc_get_page_id( 'shop' ) );
    }
    ?>
    <section class="limited-promo-banner">
        <a href="<?php echo esc_url( $banner_link ); ?>">
            <div class="promo-banner-wrapper" style="background-image: url('<?php echo esc_url( $banner_image ); ?>');">
                <div class="promo-banner-overlay"></div>

                <div class="banner-top-left-logo">
                    <span class="brand-group">潮港城</span>
                    <span class="brand-name">太陽百匯</span>
                </div>

                <div class="promo-banner-text">
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

                    <?php if ( ! empty( $banner_center_slogan ) ) : ?>
                        <div class="banner-center-slogan"><?php echo esc_html( $banner_center_slogan ); ?></div>
                    <?php endif; ?>

                    <div class="banner-mid-action">
                        <?php if ( ! empty( $banner_badge ) ) : ?>
                            <span class="badge"><?php echo esc_html( $banner_badge ); ?></span>
                        <?php endif; ?>
                        <?php if ( ! empty( $banner_sub_slogan ) ) : ?>
                            <span class="sub-slogan"><?php echo esc_html( $banner_sub_slogan ); ?></span>
                        <?php endif; ?>
                    </div>

                    <h2 class="banner-main-title"><?php echo esc_html( $banner_title ); ?></h2>
                    <p class="banner-description"><?php echo esc_html( $banner_desc ); ?></p>
                </div>
            </div>
        </a>
    </section>
    <?php
}

/* -------------------------------------------------------------------------
 * 2. 促銷清單 Promo List
 * ---------------------------------------------------------------------- */
function ckc_render_module_promo_list( $settings ) {
    $items = isset( $settings['items'] ) && is_array( $settings['items'] ) ? $settings['items'] : array();
    if ( empty( $items ) ) {
        return;
    }
    ?>
    <section class="monthly-promo-section container">
        <ul class="promo-list">
            <?php foreach ( $items as $item ) :
                $text  = isset( $item['text'] ) ? $item['text'] : '';
                $link  = isset( $item['link'] ) ? $item['link'] : '#';
                $color = isset( $item['color'] ) ? $item['color'] : '#f5f5f5';
                if ( '' === $text ) {
                    continue;
                }
                ?>
                <li>
                    <a class="promo-item" href="<?php echo esc_url( $link ); ?>" style="background: <?php echo esc_attr( $color ); ?>;">
                        <?php echo esc_html( $text ); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php
}

/* -------------------------------------------------------------------------
 * 3. 精選商品輪播 Hero Slider
 * ---------------------------------------------------------------------- */
function ckc_render_module_hero_slider( $settings ) {
    $count = ! empty( $settings['products_count'] ) ? intval( $settings['products_count'] ) : 5;

    $featured_products = array();
    if ( class_exists( 'WooCommerce' ) ) {
        $featured_products = wc_get_products( array(
            'featured' => true,
            'status'   => 'publish',
            'limit'    => $count,
        ) );
    }
    ?>
    <section class="hero-slider" id="home-slider">
        <?php if ( ! empty( $featured_products ) ) : ?>
            <?php
            $slide_index = 0;
            foreach ( $featured_products as $product ) :
                $image_id      = $product->get_image_id();
                $image_url     = $image_id ? wp_get_attachment_url( $image_id ) : wc_placeholder_img_src();
                $categories    = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
                $category_name = ! empty( $categories ) ? $categories[0] : '精選商品';
                $short_desc    = $product->get_short_description();
                if ( empty( $short_desc ) ) {
                    $short_desc = wp_strip_all_tags( $product->get_description() );
                    $short_desc = mb_strimwidth( $short_desc, 0, 160, '...' );
                }
                ?>
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
            <div class="slide active" style="background-image: url('<?php echo esc_url( get_template_directory_uri() . '/assets/images/slide-buffet.jpg' ); ?>');">
                <div class="slide-overlay"></div>
                <div class="container" style="position: relative; height: 100%;">
                    <div class="slide-content">
                        <h2>太陽百匯 SOLIS BUFFET</h2>
                        <p>海陸頂級美味盛宴，豪華龍蝦、生蠔、和牛、刺身現點現做！平日/假日餐券限時搶購中。</p>
                        <a href="<?php echo esc_url( class_exists( 'WooCommerce' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/' ) ); ?>" class="btn">搶購優惠餐券</a>
                    </div>
                </div>
            </div>
            <div class="slide" style="background-image: url('<?php echo esc_url( get_template_directory_uri() . '/assets/images/slide-frozen.jpg' ); ?>');">
                <div class="slide-overlay"></div>
                <div class="container" style="position: relative; height: 100%;">
                    <div class="slide-content">
                        <h2>主廚嚴選 經典宅配</h2>
                        <p>將星級主廚的私房手路菜，以急速冷凍包裝宅配到家。招牌紅燒牛肉爐、佛跳牆，美味輕鬆上桌！</p>
                        <a href="<?php echo esc_url( class_exists( 'WooCommerce' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/' ) ); ?>" class="btn btn-gold">選購冷凍美食</a>
                    </div>
                </div>
            </div>
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

        <div class="slider-arrow slider-prev" id="slider-prev-btn">&lt;</div>
        <div class="slider-arrow slider-next" id="slider-next-btn">&gt;</div>

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

    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        var slides = document.querySelectorAll('#home-slider .slide');
        if (!slides.length) { return; }
        var dots = document.querySelectorAll('#home-slider .slider-dot');
        var prevBtn = document.getElementById('slider-prev-btn');
        var nextBtn = document.getElementById('slider-next-btn');
        var currentSlide = 0;
        var slideInterval = setInterval(nextSlide, 5000);

        function goToSlide(n) {
            slides[currentSlide].classList.remove('active');
            if (dots[currentSlide]) { dots[currentSlide].classList.remove('active'); }
            currentSlide = (n + slides.length) % slides.length;
            slides[currentSlide].classList.add('active');
            if (dots[currentSlide]) { dots[currentSlide].classList.add('active'); }
        }
        function nextSlide() { goToSlide(currentSlide + 1); }
        function prevSlide() { goToSlide(currentSlide - 1); }

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
    <?php
}

/* -------------------------------------------------------------------------
 * 4. 分類商品展示區（單一分類；要展示多個分類就加多個此模塊）
 * ---------------------------------------------------------------------- */
function ckc_render_module_category_showcase( $settings ) {
    $cat_slug = isset( $settings['category'] ) ? $settings['category'] : '';
    if ( empty( $cat_slug ) || ! class_exists( 'WooCommerce' ) ) {
        return;
    }
    $term = get_term_by( 'slug', $cat_slug, 'product_cat' );
    if ( ! $term || is_wp_error( $term ) ) {
        return;
    }
    $cat_link  = get_term_link( $term );
    $cat_name  = ! empty( $settings['title_override'] ) ? $settings['title_override'] : $term->name;
    $count     = ! empty( $settings['products_count'] ) ? intval( $settings['products_count'] ) : 4;
    $bg_style  = ! empty( $settings['bg_light'] ) ? ' style="background-color: var(--light-bg);"' : '';
    ?>
    <section class="product-showcase"<?php echo $bg_style; ?>>
        <div class="container">
            <div class="section-header">
                <h2><a href="<?php echo esc_url( is_wp_error( $cat_link ) ? '#' : $cat_link ); ?>">— &nbsp;&nbsp; <?php echo esc_html( $cat_name ); ?> &nbsp;&nbsp; —</a></h2>
            </div>
            <div class="products-grid">
                <?php
                $products = wc_get_products( array(
                    'limit'    => $count,
                    'status'   => 'publish',
                    'category' => array( $cat_slug ),
                ) );
                if ( ! empty( $products ) ) {
                    chao_gang_cheng_render_products( $products );
                } else {
                    echo '<p style="grid-column: 1/-1; text-align: center; color: #999; padding: 40px 0;">目前尚無商品上架</p>';
                }
                ?>
            </div>
        </div>
    </section>
    <?php
    $banner_img = isset( $settings['divider_banner_image'] ) ? $settings['divider_banner_image'] : '';
    if ( ! empty( $banner_img ) ) {
        $banner_link = isset( $settings['divider_banner_link'] ) ? $settings['divider_banner_link'] : '';
        // 注意：這裡故意不包 <div class="container">（那個 class 帶
        // max-width，會把圖片限制在版面寬度內）。外層 #content.site-content
        // 本身沒有 max-width 限制，讓這個 <section> 直接滿版，圖片才能真正
        // 貼齊左右螢幕邊緣（網頁全橫幅），不是像其他區塊一樣置中、兩側留白。
        ?>
        <section class="category-divider-banner">
            <?php if ( ! empty( $banner_link ) ) : ?>
                <a href="<?php echo esc_url( $banner_link ); ?>" class="category-divider-banner-link">
            <?php endif; ?>
            <img class="cat-divider-banner-img" src="<?php echo esc_url( $banner_img ); ?>" alt="分類 Banner" width="1200" height="669" loading="lazy" decoding="async">
            <?php if ( ! empty( $banner_link ) ) : ?>
                </a>
            <?php endif; ?>
        </section>
        <?php
    }
}

/**
 * 商品卡片渲染輔助函式（供 category_showcase 模塊使用）
 */
if ( ! function_exists( 'chao_gang_cheng_render_products' ) ) {
    function chao_gang_cheng_render_products( $products ) {
        foreach ( $products as $product ) {
            $image_id  = $product->get_image_id();
            $image_url = '';
            if ( $image_id ) {
                $image = wp_get_attachment_image_src( $image_id, 'medium' );
                $image_url = $image ? $image[0] : '';
            }

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

            $is_out_of_stock = ! $product->is_in_stock();
            $card_classes    = 'product-card' . ( $is_out_of_stock ? ' outofstock' : '' );
            ?>
            <div class="<?php echo esc_attr( $card_classes ); ?>">
                <a href="<?php echo esc_url( $product->get_permalink() ); ?>" class="product-image-link" style="position: relative; display: block;">
                    <?php
                    if ( $product->is_on_sale() ) {
                        $badge_label = '特價';
                        $regular     = floatval( $product->get_regular_price() );
                        $sale        = floatval( $product->get_sale_price() );
                        if ( $regular > 0 && $sale > 0 && $sale < $regular ) {
                            $percent = round( ( $sale / $regular ) * 100 );
                            if ( $percent >= 1 && $percent < 100 ) {
                                $badge_label = ( $percent % 10 === 0 ) ? ( ( $percent / 10 ) . '折' ) : ( $percent . '折' );
                            }
                        }
                        echo '<span class="chao-onsale">' . esc_html( $badge_label ) . '</span>';
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
}

/* -------------------------------------------------------------------------
 * 5. 通用圖片橫幅 Banner
 * ---------------------------------------------------------------------- */
function ckc_render_module_image_banner( $settings ) {
    $image = isset( $settings['image'] ) ? $settings['image'] : '';
    if ( empty( $image ) ) {
        return;
    }
    $link     = isset( $settings['link'] ) ? $settings['link'] : '';
    $alt_text = isset( $settings['alt_text'] ) ? $settings['alt_text'] : '活動 Banner';
    // 注意：這裡故意不包 <div class="container">（那個 class 帶 max-width，
    // 會把圖片限制在版面寬度內）。外層 #content.site-content 本身沒有
    // max-width 限制，讓這個 <section> 直接滿版，圖片才能真正貼齊左右
    // 螢幕邊緣（網頁全橫幅），不是像其他區塊一樣置中、兩側留白。滿版圖片
    // 邊緣本來就會貼齊螢幕，圓角拿掉才符合「全橫幅」的視覺效果。
    ?>
    <section class="home-image-banner" style="padding: 0 0 20px 0;">
        <?php if ( ! empty( $link ) ) : ?>
            <a href="<?php echo esc_url( $link ); ?>" style="display:block; width:100%;">
        <?php endif; ?>
        <img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $alt_text ); ?>" width="1200" height="300" loading="lazy" decoding="async" style="width:100%; height:auto; display:block;">
        <?php if ( ! empty( $link ) ) : ?>
            </a>
        <?php endif; ?>
    </section>
    <?php
}

/* -------------------------------------------------------------------------
 * 6. 最新消息／專案 Portfolio 網格
 * ---------------------------------------------------------------------- */
function ckc_render_module_portfolio_grid( $settings ) {
    $heading = isset( $settings['heading'] ) ? $settings['heading'] : '';
    $count   = ! empty( $settings['posts_count'] ) ? intval( $settings['posts_count'] ) : 4;
    ?>
    <section class="home-portfolio-section" style="padding: 60px 0; background-color: var(--white); border-top: 1px solid var(--border-color);">
        <div class="container">
            <?php if ( ! empty( $heading ) ) : ?>
                <div class="section-header" style="text-align:center; margin-bottom: 30px;">
                    <h2><?php echo esc_html( $heading ); ?></h2>
                </div>
            <?php endif; ?>
            <?php
            $portfolio_query = new WP_Query( array(
                'post_type'      => 'jetpack-portfolio',
                'posts_per_page' => $count,
                'post_status'    => 'publish',
            ) );
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
                    <?php
                    $fallback_cards = array(
                        array( 'img' => 'slide-buffet.jpg', 'title' => '太陽百匯奢華海鮮祭', 'desc' => '引進頂級生蠔與限量波士頓龍蝦，打造全台最奢華的吃到飽海鮮盛宴。' ),
                        array( 'img' => 'slide-frozen.jpg', 'title' => '手路菜冷凍宅配計劃', 'desc' => '與在地小農合作，將經典年菜、紅燒牛肉爐新鮮冷凍，直送全台家門口。' ),
                        array( 'img' => 'slide-line.jpg', 'title' => '潮港城婚宴 brand 升級', 'desc' => '全新裝潢百萬燈光音響，首創沉浸式環景劇院婚宴體驗。' ),
                        array( 'img' => 'product-beef.jpg', 'title' => '主廚私房菜線上廚房', 'desc' => '分享經典老饕下酒菜、經典紅燒牛肉爐的美味做法，在家輕鬆當主廚。' ),
                    );
                    foreach ( $fallback_cards as $card ) :
                        ?>
                        <div class="portfolio-card" style="background: var(--white); border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.02); transition: transform 0.3s ease, box-shadow 0.3s ease;">
                            <div style="display: block; overflow: hidden; height: 200px;">
                                <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/' . $card['img'] ); ?>" alt="<?php echo esc_attr( $card['title'] ); ?>" width="400" height="200" loading="lazy" decoding="async" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease;">
                            </div>
                            <div style="padding: 20px;">
                                <h3 style="font-size: 16px; font-weight: 700; margin-top: 0; margin-bottom: 10px;">
                                    <span style="color: var(--primary-color); text-decoration: none;"><?php echo esc_html( $card['title'] ); ?></span>
                                </h3>
                                <p style="font-size: 13px; color: var(--text-muted); line-height: 1.6; margin: 0;"><?php echo esc_html( $card['desc'] ); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <style>
        .portfolio-card:hover { transform: translateY(-5px) !important; box-shadow: 0 8px 25px rgba(0,0,0,0.08) !important; }
        .portfolio-card:hover img { transform: scale(1.05) !important; }
    </style>
    <?php
}

/* -------------------------------------------------------------------------
 * 7. YouTube 影片摘要
 * ---------------------------------------------------------------------- */
function ckc_render_module_youtube_feed( $settings ) {
    $heading     = isset( $settings['heading'] ) ? $settings['heading'] : '';
    $subheading  = isset( $settings['subheading'] ) ? $settings['subheading'] : '';
    $channel_url = isset( $settings['channel_url'] ) ? $settings['channel_url'] : 'https://www.youtube.com/@ckcgroup';
    ?>
    <section class="home-youtube-section" style="padding: 60px 0; background-color: #fafafa; border-top: 1px solid var(--border-color);">
        <div class="container">
            <div class="section-header" style="text-align: center; margin-bottom: 40px;">
                <?php if ( ! empty( $heading ) || ! empty( $subheading ) ) : ?>
                    <div class="youtube-profile-header" style="text-align: center; margin-bottom: 12px;">
                        <?php if ( ! empty( $heading ) ) : ?>
                            <h2 style="font-size: 24px; font-weight: 700; color: var(--primary-color); margin: 0 0 5px 0;"><?php echo esc_html( $heading ); ?></h2>
                        <?php endif; ?>
                        <?php if ( ! empty( $subheading ) ) : ?>
                            <p style="font-size: 14px; color: var(--text-muted); margin: 0;"><?php echo esc_html( $subheading ); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <a href="<?php echo esc_url( $channel_url ); ?>" target="_blank" class="btn" style="background-color: #ff0000; color: var(--white); padding: 6px 16px; font-size: 13px; display: inline-flex; align-items: center; gap: 8px; border-radius: 20px; border: none; text-decoration: none;">
                    訂閱 YouTube 頻道
                </a>
            </div>

            <?php
            // 統一使用自家 RSS 抓取＋卡片樣式，不再依賴外掛 shortcode（yotuwp／youtube-feed）
            // 輸出——外掛版面跟這裡精心設計的縮圖／播放鍵／hover 效果不一致，故不採用。
            $yt_videos = function_exists( 'chao_gang_cheng_get_youtube_videos' ) ? chao_gang_cheng_get_youtube_videos( $channel_url ) : array();
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
                    <?php
                    $fallback_videos = array(
                        array( 'img' => 'slide-buffet.jpg', 'title' => '太陽百匯釜山海鮮季新上市！帶您直擊頂級生猛海鮮盛宴' ),
                        array( 'img' => 'slide-frozen.jpg', 'title' => '國宴主廚大公開！星級極品佛跳牆的備料與慢火熬煮秘訣' ),
                        array( 'img' => 'slide-line.jpg', 'title' => '百萬光影與巨幕環景！直擊潮港城最新概念沉浸式婚宴' ),
                        array( 'img' => 'product-beef.jpg', 'title' => '極致濃郁！主廚揭秘招牌紅燒牛肉爐十二道中藥慢燉工序' ),
                    );
                    foreach ( $fallback_videos as $video ) :
                        ?>
                        <a href="<?php echo esc_url( $channel_url ); ?>" target="_blank" class="youtube-item" style="position: relative; display: block; background: var(--white); border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.02); text-decoration: none; transition: transform 0.3s ease, box-shadow 0.3s ease;">
                            <div class="yt-thumb-wrapper" style="position: relative; aspect-ratio: 16/9; overflow: hidden; background-color: #000;">
                                <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/' . $video['img'] ); ?>" alt="<?php echo esc_attr( $video['title'] ); ?>" width="480" height="270" loading="lazy" decoding="async" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease;">
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
                <?php endif; ?>
            </div>
            <style>
                .youtube-item:hover { transform: translateY(-5px) !important; box-shadow: 0 8px 25px rgba(0,0,0,0.08) !important; }
                .youtube-item:hover .yt-thumb-wrapper img { transform: scale(1.05) !important; }
                .youtube-item:hover .yt-play-button { background-color: #ff0000 !important; transform: scale(1.1) !important; }
                .youtube-item:hover .yt-title { color: #ff0000 !important; }
            </style>
        </div>
    </section>
    <?php
}

/* -------------------------------------------------------------------------
 * 8. 精選影片（Instagram／Facebook，電腦版一屏 4 則／手機版一屏 2 則，
 *    勻速自動向右捲動並無縫循環回開頭；官方內嵌元件，站內直接播放）
 * ---------------------------------------------------------------------- */
function ckc_render_module_instagram_showcase( $settings ) {
    $heading    = isset( $settings['heading'] ) ? $settings['heading'] : '';
    $subheading = isset( $settings['subheading'] ) ? $settings['subheading'] : '';
    $items      = isset( $settings['items'] ) && is_array( $settings['items'] ) ? $settings['items'] : array();

    // 整理清單：判斷平台（Instagram／Facebook）跟是否為 Reel。
    // - Instagram 一般貼文（/p/）：官方內嵌元件站內直接播放，完全正常。
    // - Instagram Reels（/reel/、/reels/）：官方內嵌元件目前有已知的顯示
    //   問題（連 Instagram 自己的 Reel 都會嵌成空白，經實測確認跟版權
    //   音樂無關，是 Reels 這個內容類型本身的內嵌管線有問題），所以改成
    //   縮圖卡片，點擊開新分頁到 Instagram 播放（縮圖需要後台手動上傳，
    //   因為沒有官方 API 可以抓）。
    // - Facebook 貼文／Reel：用 Facebook 官方 Video Plugin（fb-video），
    //   經實測 Reels 也能正常站內播放，不需要縮圖 fallback。
    $entries = array();
    foreach ( $items as $item ) {
        $url = isset( $item['url'] ) ? trim( $item['url'] ) : '';
        if ( ! $url ) {
            continue;
        }
        if ( false !== strpos( $url, 'instagram.com' ) ) {
            $is_reel = (bool) preg_match( '#instagram\.com/reels?/#i', $url );
            $entries[] = array(
                'platform'  => 'instagram',
                'url'       => $url,
                'is_reel'   => $is_reel,
                'thumbnail' => $is_reel && isset( $item['thumbnail'] ) ? trim( $item['thumbnail'] ) : '',
            );
        } elseif ( false !== strpos( $url, 'facebook.com' ) || false !== strpos( $url, 'fb.watch' ) ) {
            $entries[] = array(
                'platform'  => 'facebook',
                'url'       => $url,
                'is_reel'   => false,
                'thumbnail' => '',
            );
        }
        // 其他網址（貼錯或不支援的平台）直接忽略，避免輸出無效的內嵌元件。
    }

    if ( empty( $entries ) ) {
        return; // 尚未設定任何貼文網址時，這個區塊不輸出，避免首頁出現空區塊。
    }
    ?>
    <section class="instagram-showcase-section">
        <div class="container">
            <?php if ( $heading || $subheading ) : ?>
                <div class="section-header" style="text-align: center; margin-bottom: 30px;">
                    <?php if ( $heading ) : ?>
                        <h2 style="font-size: 24px; font-weight: 700; color: var(--primary-color); margin: 0 0 5px 0;"><?php echo esc_html( $heading ); ?></h2>
                    <?php endif; ?>
                    <?php if ( $subheading ) : ?>
                        <p style="font-size: 14px; color: var(--text-muted); margin: 0;"><?php echo esc_html( $subheading ); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="instagram-showcase-wrap" data-item-count="<?php echo esc_attr( count( $entries ) ); ?>">
            <button type="button" class="instagram-showcase-arrow instagram-showcase-prev" aria-label="上一則">&lt;</button>
            <div class="instagram-showcase-viewport">
                <div class="instagram-showcase-track">
                    <?php
                    // 項目清單重複輸出一次（原本 + 複製），讓自動捲動可以無縫接回開頭，
                    // 不會在捲到最後一則時出現「跳一下」的斷點。複製的那一份用
                    // aria-hidden 標記，避免螢幕閱讀器重複朗讀。
                    for ( $pass = 0; $pass < 2; $pass++ ) :
                        foreach ( $entries as $entry ) :
                            $hidden_attr = $pass > 0 ? ' aria-hidden="true"' : '';
                            if ( 'instagram' === $entry['platform'] && $entry['is_reel'] ) :
                                ?>
                                <div class="instagram-showcase-item instagram-showcase-item-reel"<?php echo $hidden_attr; ?>>
                                    <a class="instagram-showcase-reel-link" href="<?php echo esc_url( $entry['url'] ); ?>" target="_blank" rel="noopener noreferrer">
                                        <span class="instagram-showcase-reel-thumb"<?php echo $entry['thumbnail'] ? ' style="background-image:url(' . esc_url( $entry['thumbnail'] ) . ');"' : ''; ?>>
                                            <span class="instagram-showcase-reel-play" aria-hidden="true">▶</span>
                                        </span>
                                        <span class="instagram-showcase-reel-caption">到 Instagram 觀看 Reel</span>
                                    </a>
                                </div>
                                <?php
                            elseif ( 'facebook' === $entry['platform'] ) :
                                ?>
                                <div class="instagram-showcase-item"<?php echo $hidden_attr; ?>>
                                    <div class="instagram-showcase-embed-scale instagram-showcase-fb-embed">
                                        <div class="fb-video" data-href="<?php echo esc_url( $entry['url'] ); ?>" data-width="326" data-allowfullscreen="true"></div>
                                    </div>
                                </div>
                                <?php
                            else :
                                ?>
                                <div class="instagram-showcase-item"<?php echo $hidden_attr; ?>>
                                    <div class="instagram-showcase-embed-scale">
                                        <blockquote class="instagram-media" data-instgrm-permalink="<?php echo esc_url( $entry['url'] ); ?>" data-instgrm-version="14" style="margin: 0; width: 100%;"></blockquote>
                                    </div>
                                </div>
                                <?php
                            endif;
                        endforeach;
                    endfor;
                    ?>
                </div>
            </div>
            <button type="button" class="instagram-showcase-arrow instagram-showcase-next" aria-label="下一則">&gt;</button>
            <div class="instagram-showcase-progress" aria-hidden="true">
                <div class="instagram-showcase-progress-track">
                    <div class="instagram-showcase-progress-fill"></div>
                </div>
            </div>
        </div>
    </section>
    <?php
    // Instagram 官方內嵌元件（embed.js）比較重，用 IntersectionObserver 延遲到
    // 使用者實際捲到這個區塊附近才載入，不影響首頁一開始的載入速度；就算頁面上
    // 有多個 Instagram 精選影片模塊，這段載入腳本也只會輸出一次（static 旗標）。
    static $script_printed = false;
    if ( $script_printed ) {
        return;
    }
    $script_printed = true;
    ?>
    <script>
    (function () {
        var embedLoaded = false;
        function loadInstagramEmbed() {
            if (embedLoaded) {
                if (window.instgrm && window.instgrm.Embeds) { window.instgrm.Embeds.process(); }
                return;
            }
            embedLoaded = true;
            var script = document.createElement('script');
            script.async = true;
            script.src = 'https://www.instagram.com/embed.js';
            document.body.appendChild(script);
        }

        // Facebook 官方 Video Plugin：跟 Instagram 內嵌元件不同套系統，需要
        // 另外載入 Facebook JavaScript SDK（xfbml=1）。經實測，Facebook 的
        // Reels 用這個官方元件可以正常站內播放，沒有 Instagram Reels 那個
        // 顯示問題。
        var fbLoaded = false;
        function loadFacebookEmbed() {
            if (fbLoaded) {
                if (window.FB && window.FB.XFBML) { window.FB.XFBML.parse(); }
                return;
            }
            fbLoaded = true;
            if (!document.getElementById('fb-root')) {
                var fbRoot = document.createElement('div');
                fbRoot.id = 'fb-root';
                document.body.appendChild(fbRoot);
            }
            var script = document.createElement('script');
            script.async = true;
            script.defer = true;
            script.crossOrigin = 'anonymous';
            script.src = 'https://connect.facebook.net/zh_TW/sdk.js#xfbml=1&version=v19.0';
            document.body.appendChild(script);
        }

        var MOBILE_QUERY = '(max-width: 768px)';

        // 電腦版一屏顯示 4 則、手機版一屏顯示 2 則：因為 Instagram 官方內嵌
        // 元件天生有 326px 的最小寬度（無法用一般 CSS 直接改寬度，會破壞
        // 元件內部版面），所以改用「內層維持 326px、外層用 transform: scale()
        // 等比縮小」的方式，讓卡片依可視寬度自動縮放到剛好排滿指定則數。
        function layoutWrap(wrap) {
            var viewport = wrap.querySelector('.instagram-showcase-viewport');
            var track = wrap.querySelector('.instagram-showcase-track');
            var items = track ? track.querySelectorAll('.instagram-showcase-item') : [];
            if (!viewport || !track || !items.length) { return; }

            var isMobile = window.matchMedia(MOBILE_QUERY).matches;
            var visibleCount = isMobile ? 2 : 4;
            var gap = isMobile ? 10 : 16;
            var viewportWidth = viewport.clientWidth;
            if (viewportWidth <= 0) { return; }

            var footprint = (viewportWidth - gap * (visibleCount - 1)) / visibleCount;
            var scale = Math.min(footprint / 326, 1);
            var actualFootprint = 326 * scale;

            // Facebook 的 Video Plugin 官方最小寬度是 220px，太窄會被元件
            // 自己擋掉／跑版，所以 fb-video 的寬度要另外夾在 220px 以上
            // （比對其他卡片的 footprint 可能會稍微寬一點，屬於可接受的
            // 小誤差）。
            var fbNeedsReparse = false;

            items.forEach(function (item) {
                item.style.width = actualFootprint + 'px';
                var scaleEl = item.querySelector('.instagram-showcase-embed-scale');
                if (!scaleEl) { return; }
                var fbVideo = scaleEl.querySelector('.fb-video');
                if (fbVideo) {
                    var fbWidth = Math.max(220, Math.round(actualFootprint));
                    if (fbVideo.getAttribute('data-width') !== String(fbWidth)) {
                        fbVideo.setAttribute('data-width', fbWidth);
                        fbNeedsReparse = true;
                    }
                } else {
                    scaleEl.style.transform = 'scale(' + scale + ')';
                }
            });

            if (fbNeedsReparse && window.FB && window.FB.XFBML) {
                window.FB.XFBML.parse();
            }

            wrap.__ckcIgScale = scale;
            wrap.__ckcIgFootprint = actualFootprint;
            wrap.__ckcIgGap = gap;
            wrap.__ckcIgVisibleCount = visibleCount;

            var itemCount = parseInt(wrap.getAttribute('data-item-count'), 10) || 0;
            wrap.classList.toggle('instagram-showcase-static', itemCount <= visibleCount);

            syncItemHeights(wrap);
        }

        // 用 ResizeObserver 監看每則貼文實際渲染出來的高度（Instagram 元件
        // 從 blockquote 換成 iframe 時高度會變、之後也可能再次調整），
        // 依縮放比例同步外層容器高度，避免卡片之間出現大片空白或裁切。
        function syncItemHeights(wrap) {
            if (!('ResizeObserver' in window)) { return; }
            wrap.querySelectorAll('.instagram-showcase-item').forEach(function (item) {
                if (item.__ckcObserved) { return; }
                var scaleEl = item.querySelector('.instagram-showcase-embed-scale');
                if (!scaleEl) { return; }
                item.__ckcObserved = true;
                var ro = new ResizeObserver(function () {
                    // 重要：Instagram 內嵌元件在真正把貼文內容載入完成前，
                    // 量到的高度會是 0（或極小的暫時值）。如果這時候就把
                    // item 的高度鎖定成 0，加上 overflow: hidden，會讓這個
                    // 元素在視覺上完全塌陷——而 Instagram 官方腳本本身會依
                    // 元素是否「有實際可視範圍」來決定要不要繼續處理／載入
                    // 內容，塌陷成 0 高度反而會讓它卡住、永遠載入不出來。
                    // 所以只有量到「看起來像真的內容」（> 20px）才套用縮放
                    // 後的高度；在那之前維持瀏覽器原生的 auto 高度，讓
                    // Instagram 的元件有機會正常完成載入。
                    var rawHeight = scaleEl.offsetHeight;
                    if (rawHeight > 20) {
                        var scale = wrap.__ckcIgScale || 1;
                        item.style.height = (rawHeight * scale) + 'px';
                    }
                });
                ro.observe(scaleEl);
            });
        }

        // 勻速自動向右捲動，捲到（複製那一份的）底就無縫接回開頭；滑鼠移入、
        // 手指觸控、拖曳、或點擊左右箭頭時暫停，離開／間隔一段時間後自動恢復。
        //
        // 手機版滾動 UX：原本 .instagram-showcase-viewport 是 overflow:hidden
        // （因為捲動是用 transform 位移模擬，不是瀏覽器原生 scroll），導致手指
        // 滑動完全沒有反應，使用者只能被動看自動跑馬燈。這裡改用 Pointer Events
        // 統一處理滑鼠拖曳與手指滑動：拖曳時直接跟著手指位移，放開時吸附到最近
        // 一則的邊界（snap），並依「只是點一下」或「確實拖曳過」給不同的暫停
        // 時間，讓使用者拖曳瀏覽時不會太快被打斷。
        //
        // 已知限制：Instagram／Facebook 官方內嵌元件最終會渲染成跨網域
        // iframe，瀏覽器基於安全性不會把發生在 iframe 上的指標事件回傳給外層
        // 頁面，所以直接在該 iframe 範圍內按住拖曳不會觸發這裡的拖曳邏輯
        // （需要從卡片間距、Reel 縮圖卡片、或箭頭按鈕開始拖曳／點擊）；這是
        // 瀏覽器層級的限制，並非本次修正遺漏。
        function initMarquee(wrap) {
            var track = wrap.querySelector('.instagram-showcase-track');
            var prevBtn = wrap.querySelector('.instagram-showcase-prev');
            var nextBtn = wrap.querySelector('.instagram-showcase-next');
            var progressFill = wrap.querySelector('.instagram-showcase-progress-fill');
            if (!track || wrap.__ckcMarqueeInit) { return; }
            wrap.__ckcMarqueeInit = true;

            var offset = 0;
            var paused = false;
            var dragging = false;
            var dragMoved = false;
            var dragStartX = 0;
            var dragStartOffset = 0;
            var DRAG_THRESHOLD = 6;
            var speed = 40; // px / 秒
            var lastTs = null;
            var resumeTimer = null;

            function itemsPerSet() {
                var count = parseInt(wrap.getAttribute('data-item-count'), 10) || 0;
                return count;
            }

            function stepWidth() {
                return (wrap.__ckcIgFootprint || 326) + (wrap.__ckcIgGap || 16);
            }

            function setWidth() {
                var perSet = itemsPerSet();
                return perSet > 0 ? perSet * stepWidth() : 0;
            }

            function wrapOffset(value) {
                var w = setWidth();
                if (w <= 0) { return value; }
                value = value % w;
                if (value < 0) { value += w; }
                return value;
            }

            function updateProgress() {
                if (!progressFill) { return; }
                var w = setWidth();
                var ratio = w > 0 ? Math.min(1, Math.max(0.001, offset / w)) : 0.001;
                progressFill.style.transform = 'scaleX(' + ratio + ')';
            }

            function applyTransform() {
                track.style.transform = 'translateX(-' + offset + 'px)';
                updateProgress();
            }

            function tick(ts) {
                if (!wrap.classList.contains('instagram-showcase-static') && !paused && !dragging) {
                    if (lastTs !== null) {
                        var dt = (ts - lastTs) / 1000;
                        offset = wrapOffset(offset + speed * dt);
                        applyTransform();
                    }
                    lastTs = ts;
                } else {
                    lastTs = null;
                }
                requestAnimationFrame(tick);
            }

            function pauseTemporarily(duration) {
                paused = true;
                if (resumeTimer) { clearTimeout(resumeTimer); }
                resumeTimer = setTimeout(function () { paused = false; }, duration || 2500);
            }

            // 拖曳／點擊箭頭放開時，套用短暫的 CSS transition 讓位移吸附到最近
            // 一則的邊界時有平滑的「喀」一下動畫，而不是瞬間跳過去；自動跑馬燈
            // 逐幀位移不套用這個 transition，避免動畫互相打架。
            function withSnapTransition(fn) {
                track.classList.add('instagram-showcase-snap');
                fn();
                window.setTimeout(function () {
                    track.classList.remove('instagram-showcase-snap');
                }, 340);
            }

            function snapToNearest() {
                var step = stepWidth();
                if (step <= 0) { return; }
                withSnapTransition(function () {
                    offset = wrapOffset(Math.round(offset / step) * step);
                    applyTransform();
                });
            }

            function nudge(direction) {
                if (setWidth() <= 0) { return; }
                withSnapTransition(function () {
                    offset = wrapOffset(offset + direction * stepWidth());
                    applyTransform();
                });
                pauseTemporarily(2500);
            }

            function onPointerDown(e) {
                // 箭頭按鈕自己有點擊邏輯（nudge），不需要也走一次拖曳判斷。
                if (e.target.closest && e.target.closest('.instagram-showcase-arrow')) { return; }
                if (e.pointerType === 'mouse' && e.button !== 0) { return; }
                if (!e.isPrimary) { return; }
                dragging = true;
                dragMoved = false;
                dragStartX = e.clientX;
                dragStartOffset = offset;
                paused = true;
                try { wrap.setPointerCapture(e.pointerId); } catch (err) { /* 忽略不支援的環境 */ }
                wrap.classList.add('instagram-showcase-dragging');
            }

            function onPointerMove(e) {
                if (!dragging) { return; }
                var delta = dragStartX - e.clientX;
                if (!dragMoved && Math.abs(delta) < DRAG_THRESHOLD) { return; }
                dragMoved = true;
                if (e.cancelable) { e.preventDefault(); }
                offset = wrapOffset(dragStartOffset + delta);
                applyTransform();
            }

            function endDrag(e) {
                if (!dragging) { return; }
                dragging = false;
                wrap.classList.remove('instagram-showcase-dragging');
                if (dragMoved) {
                    snapToNearest();
                    // 確實拖曳過，給更長的閱讀時間再恢復自動捲動。
                    pauseTemporarily(5000);
                    // 吞掉拖曳放開後瀏覽器補送的 click，避免誤觸卡片內連結／播放鍵。
                    var guard = function (ev) {
                        ev.preventDefault();
                        ev.stopPropagation();
                        wrap.removeEventListener('click', guard, true);
                    };
                    wrap.addEventListener('click', guard, true);
                    window.setTimeout(function () {
                        wrap.removeEventListener('click', guard, true);
                    }, 350);
                } else {
                    // 只是點一下（可能是點卡片內連結），維持原本較短的暫停時間。
                    pauseTemporarily(2500);
                }
            }

            wrap.addEventListener('pointerdown', onPointerDown);
            wrap.addEventListener('pointermove', onPointerMove);
            wrap.addEventListener('pointerup', endDrag);
            wrap.addEventListener('pointercancel', endDrag);

            wrap.addEventListener('mouseenter', function () { if (!dragging) { paused = true; } });
            wrap.addEventListener('mouseleave', function () { if (!dragging) { paused = false; } });

            if (prevBtn) { prevBtn.addEventListener('click', function () { nudge(-1); }); }
            if (nextBtn) { nextBtn.addEventListener('click', function () { nudge(1); }); }

            requestAnimationFrame(tick);
        }

        function initAllWraps() {
            document.querySelectorAll('.instagram-showcase-wrap').forEach(function (wrap) {
                layoutWrap(wrap);
                initMarquee(wrap);
            });
        }

        var resizeTimer = null;
        function onResize() {
            if (resizeTimer) { clearTimeout(resizeTimer); }
            resizeTimer = setTimeout(function () {
                document.querySelectorAll('.instagram-showcase-wrap').forEach(layoutWrap);
            }, 150);
        }

        document.addEventListener('DOMContentLoaded', function () {
            initAllWraps();
            window.addEventListener('resize', onResize);

            var sections = document.querySelectorAll('.instagram-showcase-section');
            if (!sections.length) { return; }

            function loadNeededEmbeds(section) {
                if (section.querySelector('.instagram-media')) { loadInstagramEmbed(); }
                if (section.querySelector('.fb-video')) { loadFacebookEmbed(); }
            }

            if ('IntersectionObserver' in window) {
                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            loadNeededEmbeds(entry.target);
                            observer.unobserve(entry.target);
                        }
                    });
                }, { rootMargin: '300px' });
                sections.forEach(function (s) { observer.observe(s); });
            } else {
                sections.forEach(loadNeededEmbeds);
            }
        });
    })();
    </script>
    <?php
}

/* -------------------------------------------------------------------------
 * 9. 社群連結卡片
 * ---------------------------------------------------------------------- */
function ckc_render_module_social_links( $settings ) {
    $fb  = isset( $settings['facebook_url'] ) ? $settings['facebook_url'] : '';
    $ig  = isset( $settings['instagram_url'] ) ? $settings['instagram_url'] : '';
    $ln  = isset( $settings['line_url'] ) ? $settings['line_url'] : '';
    $yt  = isset( $settings['youtube_url'] ) ? $settings['youtube_url'] : '';
    ?>
    <section class="social-links-section" style="margin-top: 0; margin-bottom: 0;">
        <div class="container social-grid">
            <?php if ( ! empty( $fb ) ) : ?>
            <a href="<?php echo esc_url( $fb ); ?>" target="_blank" class="social-card fb-card">
                <div class="social-icon-wrapper fb-color">
                    <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"/></svg>
                </div>
                <h4>官方 Facebook</h4>
                <p>追蹤最新活動與菜色公告</p>
            </a>
            <?php endif; ?>
            <?php if ( ! empty( $ig ) ) : ?>
            <a href="<?php echo esc_url( $ig ); ?>" target="_blank" class="social-card ig-card">
                <div class="social-icon-wrapper ig-color">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
                </div>
                <h4>官方 Instagram</h4>
                <p>精美菜色照片與打卡資訊</p>
            </a>
            <?php endif; ?>
            <?php if ( ! empty( $ln ) ) : ?>
            <a href="<?php echo esc_url( $ln ); ?>" target="_blank" class="social-card ln-card">
                <div class="social-icon-wrapper ln-color">
                    <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2c5.522 0 10 3.978 10 8.878 0 4.364-3.55 8.046-8.348 8.756-.374.08-.88.252-1.008.574-.116.29-.074.744-.036 1.036l.134.81c.046.29.214 1.136-1.008.618-1.222-.516-6.596-3.896-8.996-6.66-1.658-1.822-2.746-3.664-2.746-5.714 0-4.9 4.478-8.878 10-8.878z"/></svg>
                </div>
                <h4>官方 LINE 帳號</h4>
                <p>一對一客服與專屬優惠券</p>
            </a>
            <?php endif; ?>
            <?php if ( ! empty( $yt ) ) : ?>
            <a href="<?php echo esc_url( $yt ); ?>" target="_blank" class="social-card yt-card">
                <div class="social-icon-wrapper yt-color" style="display: flex; align-items: center; justify-content: center;">
                    <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/youtube.png' ); ?>" alt="YouTube" width="24" height="24" loading="lazy" decoding="async" style="width: 24px; height: 24px;">
                </div>
                <h4>官方 YouTube</h4>
                <p>主廚做菜秘訣與宣傳影片</p>
            </a>
            <?php endif; ?>
        </div>
    </section>
    <?php
}

/* -------------------------------------------------------------------------
 * 10. 自訂文字／HTML 區塊
 * ---------------------------------------------------------------------- */
function ckc_render_module_html_block( $settings ) {
    $content = isset( $settings['content'] ) ? $settings['content'] : '';
    if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
        return;
    }
    $contained = isset( $settings['contained'] ) ? ! empty( $settings['contained'] ) : true;
    ?>
    <section class="home-html-block" style="padding: 30px 0;">
        <?php if ( $contained ) : ?>
            <div class="container"><?php echo wp_kses_post( wpautop( $content ) ); ?></div>
        <?php else : ?>
            <?php echo wp_kses_post( wpautop( $content ) ); ?>
        <?php endif; ?>
    </section>
    <?php
}
