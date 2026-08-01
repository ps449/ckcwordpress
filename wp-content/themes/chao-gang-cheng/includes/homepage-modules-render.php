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
 * 8. 社群連結卡片
 * ---------------------------------------------------------------------- */
function ckc_render_module_social_links( $settings ) {
    $fb  = isset( $settings['facebook_url'] ) ? $settings['facebook_url'] : '';
    $ig  = isset( $settings['instagram_url'] ) ? $settings['instagram_url'] : '';
    $ln  = isset( $settings['line_url'] ) ? $settings['line_url'] : '';
    $yt  = isset( $settings['youtube_url'] ) ? $settings['youtube_url'] : '';
    ?>
    <section class="social-links-section" style="margin-top: 20px; margin-bottom: 20px;">
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
 * 9. 自訂文字／HTML 區塊
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
