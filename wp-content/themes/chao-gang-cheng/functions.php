<?php
/**
 * Chao Gang Cheng Theme Functions
 *
 * @package Chao_Gang_Cheng
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * ─────────────────────────────────────────────────────────────────
 * WC 配送方法數量 Transient 自動修復
 * 症狀：wc_get_shipping_method_count() 返回 0（快取壞掉），
 *       導致 WC()->cart->needs_shipping() = false → 結帳頁無配送選項。
 * 修復：每次 woocommerce_loaded 時若快取為 0 但 DB 有啟用的方法，
 *       自動刪除壞掉的 transient 讓 WC 重新計算。
 * ─────────────────────────────────────────────────────────────────
 */
add_action( 'woocommerce_loaded', 'ckc_heal_shipping_method_count_transient' );
function ckc_heal_shipping_method_count_transient() {
    // 只在快取回傳 0 時介入，避免不必要的 DB 查詢
    if ( wc_get_shipping_method_count( true ) === 0 ) {
        global $wpdb;
        $db_count = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT method_id)
               FROM {$wpdb->prefix}woocommerce_shipping_zone_methods
              WHERE is_enabled = 1"
        );
        if ( $db_count > 0 ) {
            // 快取值與 DB 不一致 → 刪除 transient，下次 WC 會重新計算
            delete_transient( 'wc_shipping_method_count_0' );
            delete_transient( 'wc_shipping_method_count_1' );
        }
    }
}


/**
 * Setup Theme Support
 */
function chao_gang_cheng_setup() {
	// Add default posts and comments RSS feed links to head.
	add_theme_support( 'automatic-feed-links' );

	// Let WordPress manage the document title.
	add_theme_support( 'title-tag' );

	// Enable support for Post Thumbnails on posts and pages.
	add_theme_support( 'post-thumbnails' );

	// Register Navigation Menus.
	register_nav_menus( array(
		'primary'             => esc_html__( '主選單', 'chao-gang-cheng' ),
		'footer'              => esc_html__( '頁尾選單', 'chao-gang-cheng' ),
		'homepage-categories' => esc_html__( '首頁/分類管理/商品分類', 'chao-gang-cheng' ),
	) );

	// Switch default core markup for search form, comment form, and comments to output valid HTML5.
	add_theme_support( 'html5', array(
		'search-form',
		'comment-form',
		'comment-list',
		'gallery',
		'caption',
		'style',
		'script',
	) );

	// Enable support for WooCommerce
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
}
add_action( 'after_setup_theme', 'chao_gang_cheng_setup' );

/**
 * Enqueue scripts and styles.
 */
function chao_gang_cheng_scripts() {
	// Enqueue Google Font Noto Sans TC (wght 400 and 700 only)
	wp_enqueue_style( 'google-fonts', 'https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;700&display=swap', array(), null );

	// Enqueue minified stylesheet if it exists, otherwise fall back to full stylesheet
	$min_css_path = get_stylesheet_directory() . '/style.min.css';
	if ( file_exists( $min_css_path ) ) {
		$css_ver = filemtime( $min_css_path ) . '.3';
		wp_enqueue_style( 'chao-gang-cheng-style', get_stylesheet_directory_uri() . '/style.min.css', array(), $css_ver );
	} else {
		$css_ver = filemtime( get_stylesheet_directory() . '/style.css' ) . '.3';
		wp_enqueue_style( 'chao-gang-cheng-style', get_stylesheet_directory_uri() . '/style.css', array(), $css_ver );
	}
}
add_action( 'wp_enqueue_scripts', 'chao_gang_cheng_scripts' );

/**
 * Preconnect & DNS Prefetch to speed up external resource loading
 */
add_action( 'wp_head', 'chao_gang_cheng_resource_hints', 1 );
function chao_gang_cheng_resource_hints() {
	// Preconnect to Google Fonts (fastest, establishes full connection)
	echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
	echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
	// DNS Prefetch for other external resources (lightweight, DNS only)
	echo '<link rel="dns-prefetch" href="//i0.wp.com">' . "\n";
	echo '<link rel="dns-prefetch" href="//c0.wp.com">' . "\n";
	echo '<link rel="dns-prefetch" href="//s0.wp.com">' . "\n";
	echo '<link rel="dns-prefetch" href="//stats.wp.com">' . "\n";
	echo '<link rel="dns-prefetch" href="//connect.facebook.net">' . "\n";
}

/**
 * Intercept and proxy YouTube Iframe API Player creation to enable mobile muted autoplay & playsinline
 */
add_action( 'wp_head', 'chao_gang_cheng_yt_autoplay_mobile_proxy', 1 );
function chao_gang_cheng_yt_autoplay_mobile_proxy() {
    ?>
    <script type="text/javascript">
    (function() {
        var isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        if (!isMobile) return; // Only target mobile devices as requested
        
        // 1. Intercept YT Player API calls
        Object.defineProperty(window, 'YT', {
            configurable: true,
            enumerable: true,
            get: function() {
                return window._YT;
            },
            set: function(val) {
                window._YT = val;
                if (val && val.Player) {
                    var OriginalPlayer = val.Player;
                    val.Player = function(id, options) {
                        if (options) {
                            if (!options.playerVars) {
                                options.playerVars = {};
                            }
                            options.playerVars.playsinline = 1;
                            options.playerVars.webkitPlaysinline = 1;
                            options.playerVars.autoplay = 1;
                            options.playerVars.mute = 1;
                            
                            var originalEvents = options.events || {};
                            var originalOnReady = originalEvents.onReady;
                            
                            originalEvents.onReady = function(e) {
                                e.target.mute();
                                setTimeout(function() {
                                    try {
                                        e.target.playVideo();
                                    } catch (err) {
                                        console.error('YT playVideo error:', err);
                                    }
                                }, 50);
                                if (typeof originalOnReady === 'function') {
                                    originalOnReady(e);
                                }
                            };
                            options.events = originalEvents;
                        }
                        return new OriginalPlayer(id, options);
                    };
                    Object.assign(val.Player, OriginalPlayer);
                    val.Player.prototype = OriginalPlayer.prototype;
                }
            }
        });

        // 2. Intercept static/raw iframes after document load and mutations
        function scanAndSetAutoplay() {
            var iframes = document.querySelectorAll('iframe[src*="youtube.com"]');
            for (var i = 0; i < iframes.length; i++) {
                var iframe = iframes[i];
                var src = iframe.src;
                if (src && src.indexOf('autoplay=1') === -1) {
                    try {
                        var url = new URL(src);
                        url.searchParams.set('autoplay', '1');
                        url.searchParams.set('mute', '1');
                        url.searchParams.set('playsinline', '1');
                        iframe.src = url.toString();
                        iframe.setAttribute('allow', (iframe.getAttribute('allow') || '') + '; autoplay; encrypted-media');
                    } catch (e) {
                        console.error('Error modifying iframe src:', e);
                    }
                }
            }
        }
        
        document.addEventListener('DOMContentLoaded', scanAndSetAutoplay);
        window.addEventListener('load', scanAndSetAutoplay);
        
        // Use MutationObserver to catch dynamically added iframes (like lightbox popup modal)
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes) {
                    for (var i = 0; i < mutation.addedNodes.length; i++) {
                        var node = mutation.addedNodes[i];
                        if (node.tagName === 'IFRAME') {
                            if (node.src && node.src.indexOf('youtube.com') !== -1) {
                                scanAndSetAutoplay();
                            }
                        } else if (node.querySelectorAll) {
                            var innerIframes = node.querySelectorAll('iframe[src*="youtube.com"]');
                            if (innerIframes.length > 0) {
                                scanAndSetAutoplay();
                            }
                        }
                    }
                }
            });
        });
        
        // Run safety check on document element load
        if (document.documentElement) {
            observer.observe(document.documentElement, { childList: true, subtree: true });
        }
    })();
    </script>
    <?php
}

/**
 * Preload LCP (Largest Contentful Paint) image for faster first render
 */
add_action( 'wp_head', 'chao_gang_cheng_preload_lcp_image', 2 );
function chao_gang_cheng_preload_lcp_image() {
	if ( is_front_page() ) {
		// Preload the hero banner background image (LCP candidate)
		$banner_image = get_theme_mod( 'ckc_banner_image', get_template_directory_uri() . '/assets/images/slide-buffet.jpg' );
		echo '<link rel="preload" as="image" href="' . esc_url( $banner_image ) . '">' . "\n";
	}
}

/**
 * Remove WordPress bloat from wp_head for faster page loads
 */
add_action( 'init', 'chao_gang_cheng_remove_wp_bloat' );
function chao_gang_cheng_remove_wp_bloat() {
	// Remove emoji scripts and styles (saves ~47KB)
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	// Remove RSD link (XML-RPC discovery)
	remove_action( 'wp_head', 'rsd_link' );
	// Remove wlwmanifest link (Windows Live Writer)
	remove_action( 'wp_head', 'wlwmanifest_link' );
	// Remove shortlink
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	// Remove WordPress version (security + minor perf)
	remove_action( 'wp_head', 'wp_generator' );
	// Remove REST API link from head
	remove_action( 'wp_head', 'rest_output_link_wp_head' );
	// Remove oEmbed discovery links
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	// Remove feed links from head
	remove_action( 'wp_head', 'feed_links_extra', 3 );
}

/**
 * Load Google Fonts stylesheet asynchronously to prevent render-blocking
 */
add_filter( 'style_loader_tag', 'chao_gang_cheng_async_google_fonts', 10, 2 );
function chao_gang_cheng_async_google_fonts( $html, $handle ) {
	if ( 'google-fonts' === $handle ) {
		return str_replace( "rel='stylesheet'", "rel='stylesheet' media='print' onload=\"this.media='all'\"", $html );
	}
	return $html;
}

/**
 * Dequeue WooCommerce styles on non-WooCommerce pages to improve FCP/LCP
 */
add_action( 'wp_enqueue_scripts', 'chao_gang_cheng_dequeue_woocommerce_styles', 99 );
function chao_gang_cheng_dequeue_woocommerce_styles() {
	if ( function_exists( 'is_woocommerce' ) ) {
		if ( ! is_woocommerce() && ! is_cart() && ! is_checkout() && ! is_account_page() ) {
			wp_dequeue_style( 'woocommerce-layout' );
			wp_dequeue_style( 'woocommerce-general' );
			wp_dequeue_style( 'woocommerce-smallscreen' );
			
			// Dequeue WooCommerce block styles
			wp_dequeue_style( 'wc-blocks-vendors-style' );
			wp_dequeue_style( 'wc-blocks-style' );
			wp_dequeue_style( 'wc-block-style' );
		}
	}
}

/**
 * Self-host Font Awesome stylesheet to prevent external third-party requests
 */
add_action( 'wp_enqueue_scripts', 'chao_gang_cheng_self_host_font_awesome', 999 );
function chao_gang_cheng_self_host_font_awesome() {
	wp_deregister_style( 'sb-font-awesome' );
	wp_dequeue_style( 'sb-font-awesome' );
	wp_enqueue_style( 'sb-font-awesome', get_stylesheet_directory_uri() . '/assets/css/font-awesome.min.css', array(), '4.7.0' );
}

/**
 * Performance Optimization 1: Remove jQuery Migrate to eliminate legacy JS overhead (~31KB)
 * jQuery Migrate is only needed for very old (pre-1.9) jQuery code. Modern WooCommerce/plugins don't need it.
 */
add_action( 'wp_default_scripts', 'chao_gang_cheng_remove_jquery_migrate' );
function chao_gang_cheng_remove_jquery_migrate( &$scripts ) {
	if ( ! is_admin() && isset( $scripts->registered['jquery'] ) ) {
		$script = $scripts->registered['jquery'];
		if ( $script->deps ) {
			$script->deps = array_diff( $script->deps, array( 'jquery-migrate' ) );
		}
	}
}

/**
 * Performance Optimization 2: Add defer attribute to theme scripts to eliminate render-blocking
 */
add_filter( 'script_loader_tag', 'chao_gang_cheng_defer_scripts', 10, 2 );
function chao_gang_cheng_defer_scripts( $tag, $handle ) {
	// Only defer on front-end, not in admin
	if ( is_admin() ) {
		return $tag;
	}
	// Scripts that should be deferred
	$defer_handles = array(
		'chao-gang-cheng-navigation',
		'chao-gang-cheng-main',
	);
	if ( in_array( $handle, $defer_handles, true ) ) {
		return str_replace( ' src=', ' defer src=', $tag );
	}
	return $tag;
}

/**
 * Performance Optimization 3: Dequeue Gutenberg block editor front-end assets
 * Saves ~400KB+ of JS/CSS that is not needed for this custom theme
 */
add_action( 'wp_enqueue_scripts', 'chao_gang_cheng_dequeue_block_editor_assets', 100 );
function chao_gang_cheng_dequeue_block_editor_assets() {
	// Remove global styles and theme JSON inline styles generated by the block editor
	wp_dequeue_style( 'global-styles' );
	wp_deregister_style( 'global-styles' );
	// Remove classic-theme-styles (large unused stylesheet)
	wp_dequeue_style( 'classic-theme-styles' );
	wp_deregister_style( 'classic-theme-styles' );
	// Remove block library styles on non-content pages
	if ( ! is_singular() || ( is_singular() && ! has_blocks() ) ) {
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'wp-block-library-theme' );
	}
	// Remove dashicons for non-logged-in users (saves ~30KB)
	if ( ! is_user_logged_in() ) {
		wp_dequeue_style( 'dashicons' );
	}
}

/**
 * Performance Optimization 4: Conditionally load plugin CSS only where needed
 * - Sticky Cart bar: only needed on single product pages
 * - Saves unnecessary CSS payload on shop/category/home pages
 */
add_action( 'wp_enqueue_scripts', 'chao_gang_cheng_conditional_plugin_css', 99 );
function chao_gang_cheng_conditional_plugin_css() {
	// Only dequeue on non-product pages to prevent unnecessary CSS load
	if ( ! is_singular( 'product' ) ) {
		// Dequeue sticky cart bar CSS (only needed on single product pages)
		wp_dequeue_style( 'mydybox-taiwan-for-woocommerce-sticky-cart-style' );
		wp_deregister_style( 'mydybox-taiwan-for-woocommerce-sticky-cart-style' );
		wp_dequeue_style( 'mydybox-taiwan-for-woocommerce-sticky-cart' );
		wp_deregister_style( 'mydybox-taiwan-for-woocommerce-sticky-cart' );
	}
	// Dequeue unnecessary WooCommerce block editor styles everywhere
	wp_dequeue_style( 'wc-blocks-vendors-style' );
	wp_dequeue_style( 'wc-blocks-style' );
	wp_dequeue_style( 'wc-block-style' );
}

/**
 * Performance Optimization 5: Dequeue WooCommerce cart fragment JS on non-WooCommerce pages
 * Cart fragments makes an AJAX request on every page load - skip it on pages that don't show the cart
 */
add_action( 'wp_enqueue_scripts', 'chao_gang_cheng_dequeue_wc_scripts_on_non_wc', 99 );
function chao_gang_cheng_dequeue_wc_scripts_on_non_wc() {
	if ( ! function_exists( 'is_woocommerce' ) ) {
		return;
	}
	// Only keep WC scripts on WooCommerce related pages
	if ( ! is_woocommerce() && ! is_cart() && ! is_checkout() && ! is_account_page() ) {
		// Dequeue WC cart fragments (prevents unnecessary AJAX call on every non-WC page)
		wp_dequeue_script( 'wc-cart-fragments' );
		// Dequeue WooCommerce add to cart script on homepage/category only (not individual products)
		if ( ! is_front_page() && ! is_home() ) {
			wp_dequeue_script( 'woocommerce' );
		}
	}
}

/**
 * Performance Optimization 6: Set browser cache headers for static theme assets
 * Instructs browsers/CDN to cache CSS, JS, and font files for 1 year
 */
add_action( 'send_headers', 'chao_gang_cheng_browser_cache_headers' );
function chao_gang_cheng_browser_cache_headers() {
	// Only apply to front-end, not admin
	if ( is_admin() ) {
		return;
	}
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	// Apply long cache for theme static assets
	if ( preg_match( '/\.(?:css|js|woff2?|ttf|eot|svg|jpg|jpeg|png|gif|webp|ico)(?:\?.*)?$/', $uri ) ) {
		header( 'Cache-Control: public, max-age=31536000, immutable' ); // 1 year
		header( 'Vary: Accept-Encoding' );
	}
}

/**
 * Performance Optimization 7: Dequeue unused "Custom Facebook Feed" plugin assets sitewide
 *
 * 稽核「反應速度」時用 Performance API 抓每頁的資源清單，發現這個外掛的
 * cff-scripts.min.js 每一頁都在載入（被 WordPress.com 的資源合併機制跟
 * 其他檔案包在同一個請求裡），但站上實際檢查後找不到任何地方在用這個
 * 外掛的動態貼文牆——首頁的 Facebook 卡片跟頁尾的粉專小卡都是另外手刻
 * 的 iframe／連結，不是這個外掛產生的。等於每個訪客都在多下載、多解析
 * 一支完全沒有畫面產出的 JS。這裡先在前台全站停用；如果之後真的要用
 * 這個外掛的動態貼文牆功能，把這段拿掉即可（wp_dequeue 對不存在的
 * handle 只是安全的無操作，不會影響其他功能）。
 */
add_action( 'wp_enqueue_scripts', 'chao_gang_cheng_dequeue_unused_cff_assets', 99 );
function chao_gang_cheng_dequeue_unused_cff_assets() {
	wp_dequeue_script( 'cff-scripts' );
	wp_deregister_script( 'cff-scripts' );
	wp_dequeue_style( 'cff-style' );
	wp_deregister_style( 'cff-style' );
	wp_dequeue_style( 'cff-styles' );
	wp_deregister_style( 'cff-styles' );
	wp_dequeue_style( 'cff-fontawesome' );
	wp_deregister_style( 'cff-fontawesome' );
}

/**
 * Performance Optimization 8: 針對可快取的前台頁面（首頁／商品頁／分類頁／
 * 一般文章頁）啟用 stale-while-revalidate，訪客可以立即看到（可能稍微過期
 * 的）快取內容，系統同時在背景重新擷取最新版本，下一位訪客就能看到更新後
 * 的版本；不會有人被卡著等第一手資料重新產生完成才看到畫面。
 *
 * 注意：
 * 1. 這裡設定的是「頁面本身」（HTML）的 Cache-Control，跟上面
 *    Performance Optimization 6 設定的靜態資源（CSS/JS/圖片）快取是分開的
 *    兩件事，不會互相覆蓋。
 * 2. 只套用在真正「大家看到內容都一樣」的頁面：首頁、商品頁、商品分類／
 *    標籤頁、一般文章／新聞頁。購物車、結帳、會員中心這些「每個人看到的
 *    內容都不一樣」的頁面，以及後台、已登入使用者（含管理員預覽），一律
 *    不套用，避免快取到別人的購物車/訂單資料。
 * 3. WordPress.com 平台本身也有自己的 Global Edge Cache／Object Cache，
 *    這兩層是分開運作的；這裡的設定是從網站本身送出正確的
 *    stale-while-revalidate 標頭，讓平台的邊緣快取、或未來接的任何 CDN，
 *    只要有遵守標準 HTTP 快取規則，都能套用這個「先給快取、背景更新」的
 *    行為。
 */
add_action( 'send_headers', 'chao_gang_cheng_enable_stale_while_revalidate' );
function chao_gang_cheng_enable_stale_while_revalidate() {
	if ( is_admin() || is_user_logged_in() ) {
		return;
	}
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		return;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return;
	}
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
		return;
	}
	// 購物車／結帳／會員中心／搜尋結果：每個訪客看到的內容都不一樣或
	// 依查詢字串而變，不能套用共用快取。
	if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
		return;
	}
	if ( is_search() ) {
		return;
	}
	if ( headers_sent() ) {
		return;
	}
	// 60 秒內視為新鮮；超過 60 秒後的 10 分鐘內，先送出快取內容給訪客，
	// 同時在背景重新擷取最新版本，供之後的訪客使用。
	header( 'Cache-Control: public, max-age=60, stale-while-revalidate=600' );
}

/**
 * Update WooCommerce Cart Fragment via AJAX
 */
add_filter( 'woocommerce_add_to_cart_fragments', 'chao_gang_cheng_cart_fragments' );
function chao_gang_cheng_cart_fragments( $fragments ) {
	ob_start();
	?>
	<span class="cart-count"><?php echo esc_html( WC()->cart->get_cart_contents_count() ); ?></span>
	<?php
	$fragments['span.cart-count'] = ob_get_clean();

	// 購物車頁的免運進度條（.cart-shipping-progress-wrapper）位置在
	// woocommerce_before_cart，不在 <form class="woocommerce-cart-form"> 裡面，
	// 所以 WooCommerce 內建的購物車表單 AJAX 更新（加減數量、套用優惠券）
	// 不會自動刷新它。改用 fragment 讓它跟表單一起用 AJAX 更新，
	// 且直接重用同一個 PHP function 渲染，跟結帳頁的免運判斷基準保證一致
	// （不再需要另外寫一段 JS 去解析畫面上的文字金額，那個做法讀到的是
	// 折扣前的小計，是先前「購物車顯示免運、結帳頁卻收運費」問題的根因）。
	// 注意：這裡不判斷 is_cart()——WooCommerce 的 fragment 刷新是走
	// wc-ajax 端點，不會建立一般頁面的 $wp_query，is_cart() 在那個情境下
	// 一律回傳 false；但 WC()->cart 本身在任何情境都可用，且 jQuery 找不到
	// 對應的 .cart-shipping-progress-wrapper 元素時，fragment 內容單純不會
	// 被套用，不會有副作用，所以其他頁面一律回傳也是安全的。
	ob_start();
	chao_gang_cheng_cart_free_shipping_progress();
	$fragments['.cart-shipping-progress-wrapper'] = ob_get_clean();

	// Update the cart dropdown too
	ob_start();
	?>
	<div class="cart-dropdown">
		<?php if ( WC()->cart->is_empty() ) : ?>
			<div class="cart-empty-state">
				<p class="empty-message">目前的購物車是空的！</p>
				<a href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>" class="button cart-empty-btn">前往商品商城</a>
			</div>
		<?php else : ?>
			<div class="cart-dropdown-items-list">
				<?php
				foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
					$_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
					$product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );

					if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
						$product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
						$thumbnail         = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image( array( 50, 50 ) ), $cart_item, $cart_item_key );
						$product_name      = apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key );
						$product_price     = apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key );
						?>
						<div class="cart-dropdown-item">
							<div class="item-thumbnail">
								<?php echo $thumbnail; ?>
							</div>
							<div class="item-info">
								<h4 class="item-name"><?php echo $product_name; ?></h4>
								<span class="item-meta"><?php echo $cart_item['quantity']; ?> x <?php echo $product_price; ?></span>
							</div>
						</div>
						<?php
					}
				}
				?>
			</div>
			<div class="cart-dropdown-footer">
				<div class="cart-dropdown-subtotal">
					<span>小計：</span>
					<strong><?php echo WC()->cart->get_cart_subtotal(); ?></strong>
				</div>
				<div class="cart-dropdown-buttons">
					<a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="button dropdown-view-cart-btn">查看購物車</a>
					<a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" class="button dropdown-checkout-btn">前往結帳</a>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<?php
	$fragments['div.cart-dropdown'] = ob_get_clean();

	return $fragments;
}

/**
 * Customize WooCommerce breadcrumb delimiters
 */
add_filter( 'woocommerce_breadcrumb_defaults', 'chao_gang_cheng_woocommerce_breadcrumbs' );
function chao_gang_cheng_woocommerce_breadcrumbs() {
    return array(
        'delimiter'   => '<span style="margin: 0 10px; color: #b3b3b3;">&gt;</span>',
        'wrap_before' => '<div class="global-breadcrumb-wrapper" style="background-color: #fbfbfb; border-bottom: 1px solid #f0f0f0; padding: 12px 0;"><div class="container" style="font-size: 13px; color: #888; display: flex; align-items: center;"><nav class="woocommerce-breadcrumb" itemprop="breadcrumb" style="margin: 0; padding: 0; display: flex; align-items: center;"><a href="' . esc_url( home_url( '/' ) ) . '" style="display: inline-flex; align-items: center; text-decoration: none; color: inherit; margin-right: 6px;"><svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor" style="vertical-align: middle;"><path d="M8.707 1.5a1 1 0 0 0-1.414 0L.646 8.146a.5.5 0 0 0 .708.708L8 2.207l6.646 6.647a.5.5 0 0 0 .708-.708L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293L8.707 1.5Z"/><path d="m8 3.293 6 6V13.5a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 2 13.5V9.293l6-6Z"/></svg></a>',
        'wrap_after'  => '</nav></div></div>',
        'before'      => '',
        'after'       => '',
        'home'        => '首頁',
    );
}
// Remove default WooCommerce breadcrumb hook to avoid duplicates (we render it in header.php)
remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20, 0 );


/**
 * Remote Import Trigger
 */
add_action( 'init', 'chao_gang_cheng_remote_import' );
function chao_gang_cheng_remote_import() {
    if ( isset( $_GET['import_chao_gang_cheng_products'] ) && $_GET['import_chao_gang_cheng_products'] === 'secret123' ) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $theme_dir = get_template_directory();
        $products_data = array(
            array(
                'name'          => '【太陽百匯】平日單人自助午餐券',
                'slug'          => 'solis-buffet-weekday-ticket',
                'category'      => 'tickets',
                'category_name' => '票券',
                'price'         => '830',
                'reg_price'     => '880',
                'image'         => $theme_dir . '/assets/images/ticket-weekday.jpg',
                'desc'          => '憑本券可享用潮港城太陽百匯平日自助午餐乙客。享用時段：11:30 - 14:00。本券已內含10%服務費。',
                'short_desc'    => '台中最受歡迎的生鮮海鮮吃到飽！平日限定超值午餐券。'
            ),
            array(
                'name'          => '【太陽百匯】假日單人午/晚餐券',
                'slug'          => 'solis-buffet-weekend-ticket',
                'category'      => 'tickets',
                'category_name' => '票券',
                'price'         => '2680',
                'reg_price'     => '2880',
                'image'         => $theme_dir . '/assets/images/ticket-weekend.jpg',
                'desc'          => '憑本券可享用潮港城太陽百匯假日午餐或晚餐吃到飽乙客。本券已內含服務費。適合家庭聚餐與節慶慶祝。',
                'short_desc'    => '假日海陸全席盛宴！龍蝦、生蠔、和牛無限量供應。'
            ),
            array(
                'name'          => '【主廚嚴選】招牌紅燒牛肉爐 (3-4人份)',
                'slug'          => 'signature-beef-hotpot',
                'category'      => 'frozen',
                'category_name' => '冷凍食品',
                'price'         => '599',
                'reg_price'     => '699',
                'image'         => $theme_dir . '/assets/images/product-beef.jpg',
                'desc'          => '潮港城30年主廚獨門研發！精選牛腩肉慢火燉煮8小時，湯頭醇厚、牛肉軟嫩多汁。急凍密封包裝，加熱即可享用。',
                'short_desc'    => '主廚研發！一箱滿足全家人的經典紅燒牛肉爐。'
            ),
            array(
                'name'          => '【老饕下酒菜】主廚私房香滷鳳爪 (2入組)',
                'slug'          => 'chef-chicken-feet',
                'category'      => 'side-dishes',
                'category_name' => '下酒菜',
                'price'         => '199',
                'reg_price'     => '250',
                'image'         => $theme_dir . '/assets/images/product-chicken.jpg',
                'desc'          => '嚴選肥美鳳爪，搭配十餘種中藥材與香料慢火老滷，口感Q彈有嚼勁，膠原蛋白滿滿，是下酒、小聚的最佳良伴。',
                'short_desc'    => '香氣撲鼻、老滷入味，老饕必點下酒小菜！'
            ),
            array(
                'name'          => '【國宴佳餚】潮港城極品佛跳牆 (附甕)',
                'slug'          => 'signature-buddha-soup',
                'category'      => 'frozen',
                'category_name' => '冷凍食品',
                'price'         => '1080',
                'reg_price'     => '1280',
                'image'         => $theme_dir . '/assets/images/product-buddha.jpg',
                'desc'          => '國宴級經典大菜！選用頂級鮑魚、干貝、排骨酥、鳥蛋、芋頭等十餘種名貴食材，層層堆疊慢火燉煮，湯頭濃意鮮美，送禮自用兩相宜。',
                'short_desc'    => '尊貴極致宴席大菜，圍爐必備極品佛跳牆。'
            )
        );

        foreach ( $products_data as $data ) {
            // Check if product already exists
            $existing = get_posts( array(
                'post_type'  => 'product',
                'name'       => $data['slug'],
                'posts_per_page' => 1
            ) );

            if ( ! empty( $existing ) ) {
                continue;
            }

            $product = new WC_Product_Simple();
            $product->set_name( $data['name'] );
            $product->set_slug( $data['slug'] );
            $product->set_status( 'publish' );
            $product->set_description( $data['desc'] );
            $product->set_short_description( $data['short_desc'] );
            $product->set_regular_price( $data['reg_price'] );
            $product->set_sale_price( $data['price'] );

            // Category logic
            $term = get_term_by( 'slug', $data['category'], 'product_cat' );
            if ( ! $term ) {
                $inserted = wp_insert_term( $data['category_name'], 'product_cat', array( 'slug' => $data['category'] ) );
                if ( ! is_wp_error( $inserted ) ) {
                    $term_id = $inserted['term_id'];
                }
            } else {
                $term_id = $term->term_id;
            }
            if ( isset( $term_id ) ) {
                $product->set_category_ids( array( $term_id ) );
            }

            // Image logic
            if ( file_exists( $data['image'] ) ) {
                $upload_dir = wp_upload_dir();
                $filename = basename( $data['image'] );
                $target_path = $upload_dir['path'] . '/' . $filename;

                copy( $data['image'], $target_path );

                $wp_filetype = wp_check_filetype( $filename, null );
                $attachment = array(
                    'post_mime_type' => $wp_filetype['type'],
                    'post_title'     => sanitize_file_name( $filename ),
                    'post_content'   => '',
                    'post_status'    => 'inherit'
                );

                $attach_id = wp_insert_attachment( $attachment, $target_path );
                $attach_data = wp_generate_attachment_metadata( $attach_id, $target_path );
                wp_update_attachment_metadata( $attach_id, $attach_data );

                $product->set_image_id( $attach_id );
            }

            $product->save();
        }

        echo "import_success";
        exit;
    }
}

/**
 * ============================================================================
 * WooCommerce Checkout & Cart UI/UX Customizations (Aligning with shop.c-k.tw)
 * ============================================================================
 */

/**
 * Customize WooCommerce Checkout Fields (Simplify Name, Taiwan Address Layout, Invoice Fields)
 */
add_filter( 'woocommerce_checkout_fields' , 'chao_gang_cheng_custom_checkout_fields' );
function chao_gang_cheng_custom_checkout_fields( $fields ) {
    // 1. Simplify Name: Change first name to "姓名" and remove last name
    $fields['billing']['billing_first_name']['label'] = '姓名';
    $fields['billing']['billing_first_name']['placeholder'] = '請輸入完整姓名';
    $fields['billing']['billing_first_name']['class'] = array( 'form-row-wide' );
    $fields['billing']['billing_first_name']['priority'] = 10;
    unset( $fields['billing']['billing_last_name'] );

    $fields['shipping']['shipping_first_name']['label'] = '姓名';
    $fields['shipping']['shipping_first_name']['placeholder'] = '請輸入完整姓名';
    $fields['shipping']['shipping_first_name']['class'] = array( 'form-row-wide' );
    $fields['shipping']['shipping_first_name']['priority'] = 10;
    unset( $fields['shipping']['shipping_last_name'] );

    // 2. Adjust billing field labels & placeholders for Taiwan
    $fields['billing']['billing_phone']['label'] = '聯絡電話';
    $fields['billing']['billing_phone']['placeholder'] = '請輸入電話，宅配人員將以此電話聯繫';
    $fields['billing']['billing_phone']['required'] = false;
    $fields['billing']['billing_phone']['priority'] = 20;

    $fields['billing']['billing_email']['label'] = '電子郵件';
    $fields['billing']['billing_email']['placeholder'] = '請輸入電子郵件，例：example@gmail.com';
    $fields['billing']['billing_email']['priority'] = 30;

    $fields['billing']['billing_country']['type'] = 'hidden';
    $fields['billing']['billing_country']['default'] = 'TW';
    $fields['billing']['billing_country']['priority'] = 40;

    $fields['billing']['billing_state']['label'] = '縣市';
    $fields['billing']['billing_state']['placeholder'] = '請選擇縣市';
    $fields['billing']['billing_state']['class'] = array( 'form-row-wide' );
    $fields['billing']['billing_state']['priority'] = 50;

    $fields['billing']['billing_city']['label'] = '鄉鎮市區';
    $fields['billing']['billing_city']['placeholder'] = '請輸入鄉鎮市區';
    $fields['billing']['billing_city']['class'] = array( 'form-row-wide' );
    $fields['billing']['billing_city']['priority'] = 60;

    $fields['billing']['billing_address_1']['label'] = '詳細地址';
    $fields['billing']['billing_address_1']['placeholder'] = '請輸入詳細路街、巷弄、門牌與樓層';
    $fields['billing']['billing_address_1']['class'] = array( 'form-row-wide' );
    $fields['billing']['billing_address_1']['priority'] = 70;

    $fields['billing']['billing_postcode']['label'] = '郵遞區號';
    $fields['billing']['billing_postcode']['placeholder'] = '郵遞區號';
    $fields['billing']['billing_postcode']['class'] = array( 'form-row-wide' );
    $fields['billing']['billing_postcode']['priority'] = 80;

    if ( isset( $fields['billing']['billing_company'] ) ) {
        $fields['billing']['billing_company']['priority'] = 90;
    }

    // Apply same to shipping
    $fields['shipping']['shipping_country']['type'] = 'hidden';
    $fields['shipping']['shipping_country']['default'] = 'TW';
    $fields['shipping']['shipping_country']['priority'] = 20;

    $fields['shipping']['shipping_state']['label'] = '縣市';
    $fields['shipping']['shipping_state']['placeholder'] = '請選擇縣市';
    $fields['shipping']['shipping_state']['class'] = array( 'form-row-wide' );
    $fields['shipping']['shipping_state']['priority'] = 30;

    $fields['shipping']['shipping_city']['label'] = '鄉鎮市區';
    $fields['shipping']['shipping_city']['placeholder'] = '請輸入鄉鎮市區';
    $fields['shipping']['shipping_city']['class'] = array( 'form-row-wide' );
    $fields['shipping']['shipping_city']['priority'] = 40;

    $fields['shipping']['shipping_address_1']['label'] = '詳細地址';
    $fields['shipping']['shipping_address_1']['placeholder'] = '請輸入詳細路街、巷弄、門牌與樓層';
    $fields['shipping']['shipping_address_1']['class'] = array( 'form-row-wide' );
    $fields['shipping']['shipping_address_1']['priority'] = 50;

    $fields['shipping']['shipping_postcode']['label'] = '郵遞區號';
    $fields['shipping']['shipping_postcode']['placeholder'] = '郵遞區號';
    $fields['shipping']['shipping_postcode']['class'] = array( 'form-row-wide' );
    $fields['shipping']['shipping_postcode']['priority'] = 60;

    if ( isset( $fields['shipping']['shipping_company'] ) ) {
        $fields['shipping']['shipping_company']['priority'] = 70;
    }

    // 3. Reorder billing fields keys
    $billing_order = array(
        'billing_first_name',
        'billing_phone',
        'billing_email',
        'billing_country',
        'billing_state',
        'billing_city',
        'billing_address_1',
        'billing_postcode',
        'billing_company'
    );
    $new_billing_fields = array();
    foreach ( $billing_order as $field_key ) {
        if ( isset( $fields['billing'][$field_key] ) ) {
            $new_billing_fields[$field_key] = $fields['billing'][$field_key];
        }
    }
    $fields['billing'] = $new_billing_fields;

    // Reorder shipping fields keys
    $shipping_order = array(
        'shipping_first_name',
        'shipping_phone',
        'shipping_country',
        'shipping_state',
        'shipping_city',
        'shipping_address_1',
        'shipping_postcode',
        'shipping_company'
    );
    $new_shipping_fields = array();
    foreach ( $shipping_order as $field_key ) {
        if ( isset( $fields['shipping'][$field_key] ) ) {
            $new_shipping_fields[$field_key] = $fields['shipping'][$field_key];
        }
    }
    $fields['shipping'] = $new_shipping_fields;

    // 4. Add Invoice Fields Section (發票資訊)
    $fields['billing']['billing_invoice_type'] = array(
        'type'        => 'select',
        'label'       => '發票類型',
        'class'       => array( 'form-row-wide', 'invoice-type-select' ),
        'required'    => true,
        'options'     => array(
            'personal' => '個人發票',
            'company'  => '公司用電子發票 (三聯式)',
            'donate'   => '捐贈發票'
        ),
        'default'     => 'personal',
        'priority'    => 100
    );

    $fields['billing']['billing_invoice_carrier'] = array(
        'type'        => 'text',
        'label'       => '手機條碼載具',
        'placeholder' => '請輸入手機條碼載具，例如：/ABC1234',
        'class'       => array( 'form-row-wide', 'invoice-conditional-field', 'invoice-carrier-row' ),
        'required'    => false,
        'priority'    => 110
    );

    $fields['billing']['billing_invoice_tax_id'] = array(
        'type'        => 'text',
        'label'       => '統一編號',
        'placeholder' => '請輸入公司統一編號 (8位數字)',
        'class'       => array( 'form-row-first', 'invoice-conditional-field', 'invoice-company-row' ),
        'required'    => false,
        'priority'    => 120
    );

    $fields['billing']['billing_invoice_company_name'] = array(
        'type'        => 'text',
        'label'       => '公司抬頭',
        'placeholder' => '請輸入公司發票抬頭',
        'class'       => array( 'form-row-last', 'invoice-conditional-field', 'invoice-company-row' ),
        'required'    => false,
        'priority'    => 130
    );

    $fields['billing']['billing_invoice_donate_code'] = array(
        'type'        => 'text',
        'label'       => '捐贈碼',
        'placeholder' => '請輸入受贈單位愛心碼，例如：329',
        'class'       => array( 'form-row-wide', 'invoice-conditional-field', 'invoice-donate-row' ),
        'required'    => false,
        'priority'    => 140
    );

    // Dynamically adjust address fields requirements based on chosen shipping method in session
    $chosen_shipping = '';
    if ( WC()->session ) {
        $chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
        $chosen_shipping = isset( $chosen_methods[0] ) ? $chosen_methods[0] : '';
    }

    $is_cvs_or_pickup = ( strpos( $chosen_shipping, 'Wooecpay_Logistic_CVS_711' ) !== false || strpos( $chosen_shipping, 'local_pickup' ) !== false );

    // Adjust billing address requirements
    $fields['billing']['billing_state']['required']     = ! $is_cvs_or_pickup;
    $fields['billing']['billing_city']['required']      = ! $is_cvs_or_pickup;
    $fields['billing']['billing_address_1']['required'] = ! $is_cvs_or_pickup;
    $fields['billing']['billing_postcode']['required']  = ! $is_cvs_or_pickup;

    // Adjust shipping address requirements for compatibility
    if ( isset( $fields['shipping'] ) ) {
        $fields['shipping']['shipping_state']['required']     = ! $is_cvs_or_pickup;
        $fields['shipping']['shipping_city']['required']      = ! $is_cvs_or_pickup;
        $fields['shipping']['shipping_address_1']['required'] = ! $is_cvs_or_pickup;
        $fields['shipping']['shipping_postcode']['required']  = ! $is_cvs_or_pickup;
    }

    // Remove company fields (simplified, as they are collected in Invoice section if needed)
    unset( $fields['billing']['billing_company'] );
    if ( isset( $fields['shipping'] ) ) {
        unset( $fields['shipping']['shipping_company'] );
    }

    return $fields;
}

/**
 * Inject Checkout JavaScript for Dynamic Invoice Fields Toggle
 */
add_action( 'wp_footer', 'chao_gang_cheng_checkout_toggle_js' );
function chao_gang_cheng_checkout_toggle_js() {
    if ( ! is_checkout() && ! is_cart() && ! is_wc_endpoint_url( 'edit-address' ) && ! is_account_page() ) {
        return;
    }
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {

        // Toggle Invoice section
        function toggleInvoiceFields() {
            var selectedType = $('#billing_invoice_type').val();
            
            // Hide all conditional fields
            $('#billing_invoice_carrier_field').hide();
            $('#billing_invoice_tax_id_field').hide();
            $('#billing_invoice_company_name_field').hide();
            $('#billing_invoice_donate_code_field').hide();
            
            // Remove required attributes on hide
            $('#billing_invoice_carrier').prop('required', false);
            $('#billing_invoice_tax_id').prop('required', false);
            $('#billing_invoice_company_name').prop('required', false);
            $('#billing_invoice_donate_code').prop('required', false);

            if (selectedType === 'carrier') {
                $('#billing_invoice_carrier_field').show();
                $('#billing_invoice_carrier').prop('required', true);
            } else if (selectedType === 'company') {
                $('#billing_invoice_tax_id_field').show();
                $('#billing_invoice_company_name_field').show();
                $('#billing_invoice_tax_id').prop('required', true);
                $('#billing_invoice_company_name').prop('required', true);
            } else if (selectedType === 'donate') {
                $('#billing_invoice_donate_code_field').show();
                $('#billing_invoice_donate_code').prop('required', true);
            }
        }

        function translateNewsletterOptIn() {
            var targetText = "I would like to receive exclusive emails with discounts and product information";
            var oldTranslation = "我願意收到最新優惠與產品資訊的專屬電子郵件";
            var replacementText = "『我同意接收商家發送的電子報及行銷訊息』";
            
            // Search all elements that might contain the text
            $('label, span, p, div, .woocommerce-form__label-for-checkbox').each(function() {
                var $this = $(this);
                if ($this.contents().length > 0) {
                    $this.contents().each(function() {
                        if (this.nodeType === 3) {
                            var textVal = this.nodeValue;
                            if (textVal.indexOf(targetText) !== -1 || textVal.indexOf(oldTranslation) !== -1) {
                                this.nodeValue = textVal.replace(targetText, replacementText).replace(oldTranslation, replacementText);
                                
                                // Auto-check the checkbox
                                var $parentLabel = $this.closest('label');
                                if ($parentLabel.length > 0) {
                                    var $checkbox = $parentLabel.find('input[type="checkbox"]');
                                    if ($checkbox.length > 0 && !$checkbox.prop('checked')) {
                                        $checkbox.prop('checked', true).trigger('change');
                                    }
                                }
                            }
                        }
                    });
                }
            });
        }

        function translateRegisterNewsletter() {
            var targetText = "I want to receive updates about products and promotions.";
            var replacementText = "我願意接收最新商品與促銷活動資訊。";
            
            $('label, span, p, div, .woocommerce-form__label-for-checkbox').each(function() {
                var $this = $(this);
                if ($this.contents().length > 0) {
                    $this.contents().each(function() {
                        if (this.nodeType === 3) {
                            var textVal = this.nodeValue;
                            if (textVal.indexOf(targetText) !== -1) {
                                this.nodeValue = textVal.replace(targetText, replacementText);
                            }
                        }
                    });
                }
            });
        }

        function removeOptionalLabels() {
            // Remove "(選填)" and "(optional)" from placeholders
            $('input, textarea').each(function() {
                var placeholder = $(this).attr('placeholder');
                if (placeholder) {
                    var newPlaceholder = placeholder.replace('(選填)', '').replace('(optional)', '').trim();
                    if (newPlaceholder !== placeholder) {
                        $(this).attr('placeholder', newPlaceholder);
                    }
                }
            });

            // Remove "(選填)" and "(optional)" from labels and text nodes
            $('label, span, p, div, option').each(function() {
                var $this = $(this);
                if ($this.contents().length > 0) {
                    $this.contents().each(function() {
                        if (this.nodeType === 3) {
                            var textVal = this.nodeValue;
                            if (textVal.indexOf('(選填)') !== -1 || textVal.indexOf('(optional)') !== -1) {
                                this.nodeValue = textVal.replace('(選填)', '').replace('(optional)', '').trim();
                            }
                        }
                    });
                }
            });
        }

        // Trigger on change
        $(document.body).on('change', '#billing_invoice_type', function() {
            toggleInvoiceFields();
        });

        // Trigger checkout update when state/city/postcode changes to recalculate outlying island shipping rates instantly
        $(document.body).on('change', '#billing_state, #shipping_state, #billing_city, #shipping_city, #billing_postcode, #shipping_postcode', function() {
            $(document.body).trigger('update_checkout');
        });

        // Trigger initially
        toggleInvoiceFields();
        translateNewsletterOptIn();
        translateRegisterNewsletter();
        removeOptionalLabels();

        // Listen for WooCommerce updates to recheck fields
        $(document.body).on('updated_checkout init_checkout', function() {
            toggleInvoiceFields();
            translateNewsletterOptIn();
            translateRegisterNewsletter();
            removeOptionalLabels();
        });

        // Use MutationObserver instead of setInterval for better performance
        var translationObserver = new MutationObserver(function(mutations) {
            translateNewsletterOptIn();
            translateRegisterNewsletter();
            removeOptionalLabels();
        });
        var checkoutForm = document.querySelector('.woocommerce-checkout');
        if (checkoutForm) {
            translationObserver.observe(checkoutForm, { childList: true, subtree: true });
            // Auto-disconnect after 10 seconds to prevent indefinite observation
            setTimeout(function() { translationObserver.disconnect(); }, 10000);
        }

        // ── Coupon success: block auto-scroll & show alert ──────────────────
        // WooCommerce core scrolls via jQuery.animate({scrollTop:...}) at an
        // unpredictable time inside its AJAX callback chain. The only reliable
        // way to neutralise it is to temporarily monkey-patch jQuery.animate
        // so that any scrollTop animation is silently discarded.
        var _scrollBlocked = false;
        var _origAnimate   = $.fn.animate;

        $.fn.animate = function(props, speed, easing, cb) {
            if (_scrollBlocked && props && typeof props.scrollTop !== 'undefined') {
                // Drop the scroll animation but still invoke the callback if provided
                if (typeof speed === 'function') { speed.call(this[0]); }
                else if (typeof easing === 'function') { easing.call(this[0]); }
                else if (typeof cb === 'function') { cb.call(this[0]); }
                return this;
            }
            return _origAnimate.apply(this, arguments);
        };

        $(document.body).on('applied_coupon', function(event, coupon_code) {
            // Enable scroll-block immediately
            _scrollBlocked = true;

            // Release block after 1.5 s (well after WC finishes its animate calls)
            setTimeout(function() { _scrollBlocked = false; }, 1500);

            // Also stop any already-running scroll animation
            $('html, body').stop(true, false);

            // Read & remove the WooCommerce success notice before WC re-renders
            var $message = $('.woocommerce-message');
            var msgText  = $message.length ? $message.text().trim() : '折價券使用成功';
            $message.remove();

            // Wait until the WC AJAX cycle injects a fresh message, then remove it too
            $(document.body).one('updated_checkout updated_wc_div', function() {
                $('.woocommerce-message').remove();
                $('html, body').stop(true, false);
            });

            // Toast 提示已由 ckc-coupons.php 處理，此處避免跳出原生的 blocking alert
            setTimeout(function() {
                $('html, body').stop(true, false);
            }, 200);
        });
    });
    </script>
    <?php
}

/**
 * Validate Custom Checkout Fields
 */
add_action('woocommerce_checkout_process', 'chao_gang_cheng_checkout_validation');
function chao_gang_cheng_checkout_validation() {
    if ( isset( $_POST['billing_invoice_type'] ) ) {
        $invoice_type = sanitize_text_field( $_POST['billing_invoice_type'] );
        if ( $invoice_type === 'carrier' ) {
            if ( empty( $_POST['billing_invoice_carrier'] ) ) {
                wc_add_notice( __( '請輸入手機條碼載具。' ), 'error' );
            } elseif ( substr( $_POST['billing_invoice_carrier'], 0, 1 ) !== '/' ) {
                wc_add_notice( __( '手機條碼載具格式不正確，應以「/」開頭。' ), 'error' );
            }
        } elseif ( $invoice_type === 'company' ) {
            if ( empty( $_POST['billing_invoice_tax_id'] ) ) {
                wc_add_notice( __( '請輸入統一編號。' ), 'error' );
            } elseif ( ! preg_match( '/^[0-9]{8}$/', $_POST['billing_invoice_tax_id'] ) ) {
                wc_add_notice( __( '統一編號格式不正確，應為 8 位數字。' ), 'error' );
            }
            if ( empty( $_POST['billing_invoice_company_name'] ) ) {
                wc_add_notice( __( '請輸入公司發票抬頭。' ), 'error' );
            }
        } elseif ( $invoice_type === 'donate' ) {
            if ( empty( $_POST['billing_invoice_donate_code'] ) ) {
                wc_add_notice( __( '請輸入受贈單位愛心碼。' ), 'error' );
            }
        }
    }
}

/**
 * Save custom invoice fields to order meta
 */
add_action( 'woocommerce_checkout_update_order_meta', 'chao_gang_cheng_save_invoice_meta' );
function chao_gang_cheng_save_invoice_meta( $order_id ) {
    if ( ! empty( $_POST['billing_invoice_type'] ) ) {
        update_post_meta( $order_id, 'billing_invoice_type', sanitize_text_field( $_POST['billing_invoice_type'] ) );
    }
    if ( ! empty( $_POST['billing_invoice_carrier'] ) ) {
        update_post_meta( $order_id, 'billing_invoice_carrier', sanitize_text_field( $_POST['billing_invoice_carrier'] ) );
    }
    if ( ! empty( $_POST['billing_invoice_tax_id'] ) ) {
        update_post_meta( $order_id, 'billing_invoice_tax_id', sanitize_text_field( $_POST['billing_invoice_tax_id'] ) );
    }
    if ( ! empty( $_POST['billing_invoice_company_name'] ) ) {
        update_post_meta( $order_id, 'billing_invoice_company_name', sanitize_text_field( $_POST['billing_invoice_company_name'] ) );
    }
    if ( ! empty( $_POST['billing_invoice_donate_code'] ) ) {
        update_post_meta( $order_id, 'billing_invoice_donate_code', sanitize_text_field( $_POST['billing_invoice_donate_code'] ) );
    }
}

/**
 * Display invoice details in WooCommerce Admin Order page
 */
add_action( 'woocommerce_admin_order_data_after_billing_address', 'chao_gang_cheng_admin_invoice_details', 10, 1 );
function chao_gang_cheng_admin_invoice_details( $order ) {
    $invoice_type = get_post_meta( $order->get_id(), 'billing_invoice_type', true );
    $invoice_type_label = '';
    
    switch ( $invoice_type ) {
        case 'personal':
            $invoice_type_label = '個人發票';
            break;
        case 'carrier':
            $invoice_type_label = '手機條碼載具';
            break;
        case 'company':
            $invoice_type_label = '公司用電子發票 (三聯式)';
            break;
        case 'donate':
            $invoice_type_label = '捐贈發票';
            break;
        default:
            $invoice_type_label = '未選擇';
    }

    echo '<div class="invoice-admin-details" style="margin-top: 15px; padding-top: 10px; border-top: 1px solid #eee; clear: both;">';
    echo '<h3>發票資訊</h3>';
    echo '<p><strong>發票類型：</strong>' . esc_html( $invoice_type_label ) . '</p>';

    if ( $invoice_type === 'carrier' ) {
        $carrier = get_post_meta( $order->get_id(), 'billing_invoice_carrier', true );
        echo '<p><strong>手機條碼：</strong>' . esc_html( $carrier ) . '</p>';
    } elseif ( $invoice_type === 'company' ) {
        $tax_id = get_post_meta( $order->get_id(), 'billing_invoice_tax_id', true );
        $company_name = get_post_meta( $order->get_id(), 'billing_invoice_company_name', true );
        echo '<p><strong>統一編號：</strong>' . esc_html( $tax_id ) . '</p>';
        echo '<p><strong>公司抬頭：</strong>' . esc_html( $company_name ) . '</p>';
    } elseif ( $invoice_type === 'donate' ) {
        $donate = get_post_meta( $order->get_id(), 'billing_invoice_donate_code', true );
        echo '<p><strong>愛心碼：</strong>' . esc_html( $donate ) . '</p>';
    }
    echo '</div>';
}

/**
 * Add invoice details to order emails
 */
add_action( 'woocommerce_email_after_order_table', 'chao_gang_cheng_email_invoice_details', 10, 4 );
function chao_gang_cheng_email_invoice_details( $order, $sent_to_admin, $plain_text, $email ) {
    $invoice_type = get_post_meta( $order->get_id(), 'billing_invoice_type', true );
    if ( ! $invoice_type ) {
        return;
    }
    
    $invoice_type_label = '';
    switch ( $invoice_type ) {
        case 'personal':
            $invoice_type_label = '個人發票';
            break;
        case 'carrier':
            $invoice_type_label = '手機條碼載具';
            break;
        case 'company':
            $invoice_type_label = '公司用電子發票 (三聯式)';
            break;
        case 'donate':
            $invoice_type_label = '捐贈發票';
            break;
    }

    if ( $plain_text ) {
        echo "\n========================================\n";
        echo "發票資訊\n";
        echo "========================================\n";
        echo "發票類型: " . $invoice_type_label . "\n";
        if ( $invoice_type === 'carrier' ) {
            echo "手機條碼: " . get_post_meta( $order->get_id(), 'billing_invoice_carrier', true ) . "\n";
        } elseif ( $invoice_type === 'company' ) {
            echo "統一編號: " . get_post_meta( $order->get_id(), 'billing_invoice_tax_id', true ) . "\n";
            echo "公司抬頭: " . get_post_meta( $order->get_id(), 'billing_invoice_company_name', true ) . "\n";
        } elseif ( $invoice_type === 'donate' ) {
            echo "愛心碼: " . get_post_meta( $order->get_id(), 'billing_invoice_donate_code', true ) . "\n";
        }
    } else {
        ?>
        <div style="font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; margin-bottom: 40px; border: 1px solid #e2e2e2; padding: 20px; border-radius: 12px;">
            <h2 style="color: #7c6767; font-size: 18px; margin-bottom: 10px; border-bottom: 2px solid #7c6767; padding-bottom: 5px;">發票資訊</h2>
            <p style="margin: 5px 0;"><strong>發票類型：</strong><?php echo esc_html( $invoice_type_label ); ?></p>
            <?php if ( $invoice_type === 'carrier' ) : ?>
                <p style="margin: 5px 0;"><strong>手機條碼：</strong><?php echo esc_html( get_post_meta( $order->get_id(), 'billing_invoice_carrier', true ) ); ?></p>
            <?php elseif ( $invoice_type === 'company' ) : ?>
                <p style="margin: 5px 0;"><strong>統一編號：</strong><?php echo esc_html( get_post_meta( $order->get_id(), 'billing_invoice_tax_id', true ) ); ?></p>
                <p style="margin: 5px 0;"><strong>公司抬頭：</strong><?php echo esc_html( get_post_meta( $order->get_id(), 'billing_invoice_company_name', true ) ); ?></p>
            <?php elseif ( $invoice_type === 'donate' ) : ?>
                <p style="margin: 5px 0;"><strong>愛心碼：</strong><?php echo esc_html( get_post_meta( $order->get_id(), 'billing_invoice_donate_code', true ) ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
}

/**
 * Render Free Shipping Progress Bar in Cart
 */
/**
 * Single source of truth for the free-shipping threshold.
 * Reads the WooCommerce shipping-zone free_shipping min_amount; falls back to 2000.
 * Shared by the cart progress bar, checkout progress bar, estimated-shipping row and cross-sell block.
 */
function chao_get_free_shipping_threshold() {
    static $cached = null;
    if ( $cached === null ) {
        // Delegate to the existing theme helper (checks named zones + default zone 0)
        // so the whole theme reads the threshold from one place, with per-request caching.
        $cached = chao_gang_cheng_get_free_shipping_threshold();
    }
    return $cached;
}

/**
 * 全站判斷「是否達免運門檻」統一用這個金額——修正 bug：先前購物車頁多處
 * （進度條／預估運費列／湊免運推薦區）各自呼叫 WC()->cart->get_subtotal()
 * （折扣「前」金額）來比對門檻，但 WooCommerce 免運方式實際判斷資格時看的
 * 是折扣「後」金額（結帳頁 chao_checkout_free_shipping_progress() 原本就是
 * 用 get_cart_contents_total()，這個才是對的）。兩邊金額基準不一致，就會
 * 出現購物車頁顯示「免運費」，套用優惠券後小計低於門檻，結帳頁卻被收運費
 * 的落差（使用者實際回報過這個問題：購物車顯示免運，結帳頁多收 NT$250）。
 *
 * 統一改成這個 helper（=get_cart_contents_total()，已扣掉優惠券折扣、
 * 稅／運費前），讓購物車頁的免運提示跟結帳頁的實際運費計算基準一致。
 */
function chao_get_free_shipping_progress_amount() {
    return WC()->cart ? floatval( WC()->cart->get_cart_contents_total() ) : 0;
}

add_action( 'woocommerce_before_cart', 'chao_gang_cheng_cart_free_shipping_progress' );
function chao_gang_cheng_cart_free_shipping_progress() {
    $threshold = chao_get_free_shipping_threshold();
    $cart_subtotal = chao_get_free_shipping_progress_amount();

    if ( $cart_subtotal >= $threshold ) {
        $percent = 100;
        $message = '太棒了！已符合免運條件，本筆訂單免運費！';
    } else {
        $diff = $threshold - $cart_subtotal;
        $percent = round( ($cart_subtotal / $threshold) * 100 );
        $message = '🚚 還差 <strong>' . wc_price( $diff ) . '</strong> 即可享冷凍宅配、超商取貨免運費！';
    }

    ?>
    <div class="cart-shipping-progress-wrapper">
        <p class="progress-message"><?php echo $message; ?></p>
        <div class="progress-bar-container">
            <div class="progress-bar-fill" style="width: <?php echo esc_attr( $percent ); ?>%;"></div>
        </div>
    </div>
    <?php
}

/**
 * Convert Cart and Checkout Pages to Classic Shortcodes to support standard hooks and styling
 */
add_action( 'init', 'chao_gang_cheng_convert_cart_checkout_pages' );
function chao_gang_cheng_convert_cart_checkout_pages() {
    // Convert Cart Page
    $cart_page_id = wc_get_page_id( 'cart' );
    if ( $cart_page_id ) {
        $cart_page = get_post( $cart_page_id );
        if ( $cart_page && ( has_block( 'woocommerce/cart', $cart_page->post_content ) || strpos( $cart_page->post_content, 'woocommerce/cart' ) !== false || strpos( $cart_page->post_content, 'wp:woocommerce/cart' ) !== false ) ) {
            wp_update_post( array(
                'ID'           => $cart_page_id,
                'post_content' => '[woocommerce_cart]'
            ) );
        }
    }
    
    // Convert Checkout Page
    $checkout_page_id = wc_get_page_id( 'checkout' );
    if ( $checkout_page_id ) {
        $checkout_page = get_post( $checkout_page_id );
        if ( $checkout_page && ( has_block( 'woocommerce/checkout', $checkout_page->post_content ) || strpos( $checkout_page->post_content, 'woocommerce/checkout' ) !== false || strpos( $checkout_page->post_content, 'wp:woocommerce/checkout' ) !== false ) ) {
            wp_update_post( array(
                'ID'           => $checkout_page_id,
                'post_content' => '[woocommerce_checkout]'
            ) );
        }
    }
}

/**
 * Force Enable WooCommerce Registration on My Account Page with User Password Choice
 *
 * 修正：woocommerce_registration_generate_username 原本被強制設為 'yes'，導致
 * WooCommerce 完全不會顯示原生的「使用者名稱」欄位，帳號登入用的 user_login
 * 永遠是系統從 Email 自動產生的一串英文字（例：test@gmail.com -> test）。
 * 曾經誤把下面的 billing_first_name（收件人姓名）欄位標籤改成「使用者名稱」，
 * 但那個欄位其實跟登入帳號無關，造成標籤跟實際功能對不上。
 * 現在改成 'no'，讓 WooCommerce 原生的使用者名稱欄位（name="username"）出現在
 * 註冊表單上，顧客輸入的文字就會是真正的登入帳號（WooCommerce 核心已內建
 * 空值、格式、重複帳號等驗證，不需要另外寫）。
 */
add_action( 'admin_init', 'chao_gang_cheng_enforce_registration_settings' );
add_action( 'init', 'chao_gang_cheng_enforce_registration_settings' );
function chao_gang_cheng_enforce_registration_settings() {
    if ( get_option( 'woocommerce_enable_myaccount_registration' ) !== 'yes' ) {
        update_option( 'woocommerce_enable_myaccount_registration', 'yes' );
    }
    if ( get_option( 'woocommerce_registration_generate_username' ) !== 'no' ) {
        update_option( 'woocommerce_registration_generate_username', 'no' );
    }
    if ( get_option( 'woocommerce_registration_generate_password' ) !== 'no' ) {
        update_option( 'woocommerce_registration_generate_password', 'no' );
    }
}

/**
 * Add Custom Fields to WooCommerce Registration Form (Name and Mobile Phone)
 */
add_action( 'woocommerce_register_form', 'chao_gang_cheng_extra_register_fields' );
function chao_gang_cheng_extra_register_fields() {
    ?>
    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
        <label for="reg_billing_first_name">姓名 <span class="required">*</span></label>
        <input type="text" class="input-text" name="billing_first_name" id="reg_billing_first_name" value="<?php if ( ! empty( $_POST['billing_first_name'] ) ) echo esc_attr( $_POST['billing_first_name'] ); ?>" placeholder="請輸入您的真實姓名" />
    </p>
    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
        <label for="reg_billing_phone">行動電話 (手機) <span class="required">*</span></label>
        <input type="tel" class="input-text" name="billing_phone" id="reg_billing_phone" value="<?php if ( ! empty( $_POST['billing_phone'] ) ) echo esc_attr( $_POST['billing_phone'] ); ?>" placeholder="請輸入行動電話，例：0912345678" />
    </p>
    <?php
}

/**
 * Validate Custom Registration Fields
 */
add_filter( 'woocommerce_registration_errors', 'chao_gang_cheng_validate_extra_register_fields', 10, 3 );
function chao_gang_cheng_validate_extra_register_fields( $validation_errors, $username, $email ) {
    if ( isset( $_POST['billing_first_name'] ) && empty( $_POST['billing_first_name'] ) ) {
        $validation_errors->add( 'billing_first_name_error', '<strong>錯誤</strong>：請輸入姓名！' );
    }
    if ( isset( $_POST['billing_phone'] ) && empty( $_POST['billing_phone'] ) ) {
        $validation_errors->add( 'billing_phone_error', '<strong>錯誤</strong>：請輸入行動電話！' );
    } elseif ( isset( $_POST['billing_phone'] ) && ! preg_match( '/^09[0-9]{8}$/', $_POST['billing_phone'] ) ) {
        $validation_errors->add( 'billing_phone_format_error', '<strong>錯誤</strong>：行動電話格式不正確，應為 09 開頭的 10 位數字！' );
    }
    return $validation_errors;
}

/**
 * Save Custom Registration Fields to Customer Meta
 */
add_action( 'woocommerce_created_customer', 'chao_gang_cheng_save_extra_register_fields' );
function chao_gang_cheng_save_extra_register_fields( $customer_id ) {
    if ( isset( $_POST['billing_first_name'] ) ) {
        update_user_meta( $customer_id, 'first_name', sanitize_text_field( $_POST['billing_first_name'] ) );
        update_user_meta( $customer_id, 'billing_first_name', sanitize_text_field( $_POST['billing_first_name'] ) );
    }
    if ( isset( $_POST['billing_phone'] ) ) {
        update_user_meta( $customer_id, 'billing_phone', sanitize_text_field( $_POST['billing_phone'] ) );
    }
}

/**
 * 會員登入：除了 WordPress 原生就支援的「使用者名稱」與「電子郵件」，
 * 再加上「行動電話」也能登入。
 *
 * 註冊時 billing_phone 已強制要求為 09 開頭的 10 碼數字（見上面的
 * chao_gang_cheng_validate_extra_register_fields 驗證邏輯），所以每個
 * 會員都一定有這筆資料可以比對。
 *
 * 掛在 authenticate 的 priority 15，搶在 WordPress 核心處理使用者名稱／
 * 電子郵件登入的 wp_authenticate_username_password（priority 20）之前跑。
 * 只有在輸入內容「看起來像手機號碼」時才介入：找到對應會員後，直接把
 * 輸入換成該會員真正的使用者名稱，再原封不動交給 WordPress 原生的
 * wp_authenticate_username_password 去驗證密碼——密碼驗證邏輯完全沒有
 * 自己重寫，只有「用什麼字串去比對帳號」這一步被替換掉，安全性跟原本
 * 帳密登入一致。如果輸入的不是手機號碼格式，就直接放行給後面的原生
 * 流程處理，不影響原本使用者名稱／電子郵件登入的行為。
 */
add_filter( 'authenticate', 'chao_gang_cheng_authenticate_by_phone', 15, 3 );
function chao_gang_cheng_authenticate_by_phone( $user, $username, $password ) {
    // 已經有其他驗證流程解析出結果的話就不重複處理
    if ( $user instanceof WP_User || empty( $username ) ) {
        return $user;
    }

    $digits = preg_replace( '/[^0-9]/', '', $username );
    if ( ! preg_match( '/^09\d{8}$/', $digits ) ) {
        return $user;
    }

    $matched_users = get_users( array(
        'meta_key'   => 'billing_phone',
        'meta_value' => $digits,
        'number'     => 1,
        'fields'     => 'all',
    ) );

    if ( empty( $matched_users ) ) {
        return $user;
    }

    return wp_authenticate_username_password( null, $matched_users[0]->user_login, $password );
}

/**
 * 把登入表單上「使用者名稱或電子郵件地址」的欄位標籤，改成也提到手機號碼，
 * 跟上面新增的手機號碼登入功能對應起來（欄位本身有 CSS 讓標籤視覺上隱藏，
 * 只顯示 placeholder，但畫面閱讀器仍會讀到這個標籤文字，所以兩處都要改）。
 */
add_filter( 'gettext', 'chao_gang_cheng_translate_login_label', 10, 3 );
function chao_gang_cheng_translate_login_label( $translated, $original, $domain ) {
    if ( 'woocommerce' !== $domain ) {
        return $translated;
    }
    if ( 'Username or email address' === $original || '使用者名稱或電子郵件地址' === $translated ) {
        return '使用者名稱、電子郵件或手機號碼';
    }
    return $translated;
}

/**
 * Reduce WooCommerce Password Strength Requirements and Disable Front-end Meter
 * Allows users to register/checkout with simpler passwords while keeping a minimum length of 6 characters for security.
 */

// 1. Lower minimum password strength requirement (0 = Very Weak, 1 = Weak, 2 = Medium, 3 = Strong)
add_filter( 'woocommerce_min_password_strength', 'chao_gang_cheng_lower_password_strength' );
function chao_gang_cheng_lower_password_strength() {
    return 0; // Accept any password strength on WooCommerce forms
}

// 2. Remove the password strength meter scripts to prevent intrusive prompts on front-end
add_action( 'wp_print_scripts', 'chao_gang_cheng_remove_password_strength_meter', 100 );
function chao_gang_cheng_remove_password_strength_meter() {
    wp_dequeue_script( 'wc-password-strength-meter' );
}

// 3. Enforce a friendly minimum length (e.g. 6 characters) on Registration and Checkout forms
add_filter( 'woocommerce_registration_errors', 'chao_gang_cheng_validate_registration_password_length', 15, 3 );
function chao_gang_cheng_validate_registration_password_length( $validation_errors, $username, $email ) {
    $password_to_check = '';
    
    if ( isset( $_POST['password'] ) && ! empty( $_POST['password'] ) ) {
        $password_to_check = $_POST['password'];
    } elseif ( isset( $_POST['account_password'] ) && ! empty( $_POST['account_password'] ) ) {
        $password_to_check = $_POST['account_password'];
    }

    if ( ! empty( $password_to_check ) && strlen( $password_to_check ) < 6 ) {
        $validation_errors->add( 'password_too_short_error', '<strong>錯誤</strong>：密碼長度必須至少為 6 個字元！' );
    }
    
    return $validation_errors;
}

// 4. Enforce the same 6-character minimum length when editing Account Details
add_action( 'woocommerce_save_account_details_errors', 'chao_gang_cheng_validate_account_details_password_length', 10, 1 );
function chao_gang_cheng_validate_account_details_password_length( $errors ) {
    if ( isset( $_POST['password_1'] ) && ! empty( $_POST['password_1'] ) ) {
        if ( strlen( $_POST['password_1'] ) < 6 ) {
            $errors->add( 'password_too_short_error', '密碼長度必須至少為 6 個字元！' );
        }
    }
}


/**
 * Single Product Actions wrapper and Buy Now button
 */
add_action( 'woocommerce_after_add_to_cart_quantity', 'chao_gang_cheng_start_action_buttons_wrapper' );
function chao_gang_cheng_start_action_buttons_wrapper() {
    global $product;
    echo '<input type="hidden" name="add-to-cart" value="' . esc_attr( $product->get_id() ) . '" />';
    echo '<div class="product-action-buttons">';
}

add_action( 'woocommerce_after_add_to_cart_button', 'chao_gang_cheng_end_action_buttons_wrapper' );
function chao_gang_cheng_end_action_buttons_wrapper() {
    echo '<button type="submit" name="buy_now" value="1" class="buy-now-btn button alt">立即購買</button>';
    echo '</div>';
}

/**
 * Redirect to Checkout when Buy Now clicked
 */
add_filter( 'woocommerce_add_to_cart_redirect', 'chao_gang_cheng_buy_now_redirect_handler' );
function chao_gang_cheng_buy_now_redirect_handler( $url ) {
    if ( isset( $_REQUEST['buy_now'] ) ) {
        return wc_get_checkout_url();
    }
    return $url;
}

/**
 * Get WooCommerce Free Shipping Threshold dynamically
 */
function chao_gang_cheng_get_free_shipping_threshold() {
    $min_amount = 0;
    if ( class_exists( 'WC_Shipping_Zones' ) ) {
        $zones = WC_Shipping_Zones::get_zones();
        foreach ( $zones as $zone ) {
            foreach ( $zone['shipping_methods'] as $method ) {
                if ( 'free_shipping' === $method->id && 'yes' === $method->enabled ) {
                    $val = isset( $method->min_amount ) ? floatval( $method->min_amount ) : 0;
                    if ( $val > 0 ) {
                        $min_amount = $val;
                        break 2;
                    }
                }
            }
        }
        $default_zone = WC_Shipping_Zones::get_zone_by( 'zone_id', 0 );
        if ( $default_zone ) {
            foreach ( $default_zone->get_shipping_methods( true ) as $method ) {
                if ( 'free_shipping' === $method->id && 'yes' === $method->enabled ) {
                    $val = isset( $method->min_amount ) ? floatval( $method->min_amount ) : 0;
                    if ( $val > 0 ) {
                        $min_amount = $val;
                        break;
                    }
                }
            }
        }
    }
    return $min_amount > 0 ? $min_amount : 2000;
}

/**
 * Get active discount rules from Flycart's Discount Rules for WooCommerce
 */
function chao_gang_cheng_get_active_discount_rules() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wdr_rules';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
        return array();
    }
    $results = $wpdb->get_results( 
        "SELECT title FROM $table_name WHERE enabled = 1 AND deleted = 0 ORDER BY priority ASC, id ASC" 
    );
    $rules = array();
    if ( ! empty( $results ) ) {
        foreach ( $results as $row ) {
            $rules[] = $row->title;
        }
    }
    return $rules;
}

/**
 * Display Promotions Box on Single Product Page
 */
/**
 * Social proof badges under the short description (product_page plan §4.2-⑤ step 1):
 * sold count (only when meaningful) + heritage badge, configurable per product
 * in wp-admin (Product data > General: hide checkbox + custom badge text).
 */
add_action( 'woocommerce_single_product_summary', 'chao_gang_cheng_product_social_proof', 21 );
function chao_gang_cheng_product_social_proof() {
    global $product;
    if ( ! $product ) {
        return;
    }
    $sold          = (int) $product->get_total_sales();
    $sold_min_show = (int) apply_filters( 'chao_social_proof_min_sales', 10 );
    $show_sold     = ( $sold >= $sold_min_show );

    // Per-product backend settings
    $hide_heritage = ( 'yes' === $product->get_meta( '_chao_hide_heritage_badge' ) );
    $badge_text    = trim( (string) $product->get_meta( '_chao_heritage_badge_text' ) );
    if ( '' === $badge_text ) {
        $badge_text = '潮港城 30 年辦桌口碑'; // Default when the field is left empty
    }

    // 溫層徽章（常溫／冷藏／冷凍，可複選），只在單一商品頁顯示，跟其他徽章
    // 共用同一列、同一種樣式，只是各溫層配色不同，方便一眼辨識。
    $temperature_zones = chao_gang_cheng_get_product_temperature_zones( $product );

    if ( ! $show_sold && $hide_heritage && empty( $temperature_zones ) ) {
        return; // Nothing to show for this product
    }
    ?>
    <div class="chao-social-proof" style="display: flex; flex-wrap: wrap; gap: 8px; margin: 4px 0 14px;">
        <?php foreach ( $temperature_zones as $tz_slug ) : ?>
            <?php $tz_info = chao_gang_cheng_get_temperature_zone_info( $tz_slug ); ?>
            <?php if ( $tz_info ) : ?>
                <span style="display: inline-flex; align-items: center; gap: 4px; background: <?php echo esc_attr( $tz_info['bg'] ); ?>; color: <?php echo esc_attr( $tz_info['color'] ); ?>; border: 1px solid <?php echo esc_attr( $tz_info['border'] ); ?>; border-radius: 20px; padding: 4px 12px; font-size: 12px; font-weight: 600;"><?php echo esc_html( $tz_info['icon'] . ' ' . $tz_info['label'] ); ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ( $show_sold ) : ?>
            <span style="display: inline-flex; align-items: center; gap: 4px; background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; border-radius: 20px; padding: 4px 12px; font-size: 12px; font-weight: 600;">🔥 已售出 <?php echo esc_html( number_format( $sold ) ); ?> 件</span>
        <?php endif; ?>
        <?php if ( ! $hide_heritage ) : ?>
            <span style="display: inline-flex; align-items: center; gap: 4px; background: #fdfaf7; color: #7f6c60; border: 1px solid #f5ebe6; border-radius: 20px; padding: 4px 12px; font-size: 12px; font-weight: 600;">🏆 <?php echo esc_html( $badge_text ); ?></span>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * 溫層（常溫／冷藏／冷凍）的顯示資料（icon、標籤文字、配色）。
 * 集中定義在這一個函式，前台徽章跟後台下拉選單都從這裡取用同一份
 * 對照表，避免兩邊文字或選項各自維護、之後改一個地方漏改另一個。
 *
 * @param string $zone 儲存在 _ckc_temperature_zone 的值：''｜'ambient'｜'chilled'｜'frozen'
 * @return array|false 沒有對應資料（例如還沒設定過）時回傳 false
 */
function chao_gang_cheng_get_temperature_zone_info( $zone ) {
    $zones = array(
        'ambient' => array(
            'label'  => '常溫',
            'icon'   => '🌡️',
            'bg'     => '#fdf6ec',
            'color'  => '#8a5a2b',
            'border' => '#f0e0c8',
        ),
        'chilled' => array(
            'label'  => '冷藏',
            'icon'   => '🧊',
            'bg'     => '#eef7fb',
            'color'  => '#2b6a8a',
            'border' => '#cde6f0',
        ),
        'frozen'  => array(
            'label'  => '冷凍',
            'icon'   => '❄️',
            'bg'     => '#eef2fb',
            'color'  => '#2b3f8a',
            'border' => '#ccd6f0',
        ),
    );
    return isset( $zones[ $zone ] ) ? $zones[ $zone ] : false;
}

/**
 * 讀取商品「可用溫層」清單（可複選）。優先讀新的複選欄位
 * _ckc_temperature_zones（陣列），沒有設定時回退到舊的單選欄位
 * _ckc_temperature_zone，確保既有商品資料不會因為升級而消失。
 * 回傳空陣列 = 未設定／不限制（前台不顯示徽章，購物車溫層檢查也略過）。
 *
 * @param WC_Product $product
 * @return array
 */
function chao_gang_cheng_get_product_temperature_zones( $product ) {
    if ( ! $product ) {
        return array();
    }
    $meta = $product->get_meta( '_ckc_temperature_zones' );
    if ( is_array( $meta ) && ! empty( $meta ) ) {
        return $meta;
    }
    $legacy = $product->get_meta( '_ckc_temperature_zone' );
    if ( is_string( $legacy ) && in_array( $legacy, array( 'ambient', 'chilled', 'frozen' ), true ) ) {
        return array( $legacy );
    }
    return array();
}

/**
 * 後台：商品編輯畫面「商品資料 > 一般」新增「溫層」複選核取方塊
 * （常溫／冷藏／冷凍，可複選），對應前台 chao_gang_cheng_product_social_proof()
 * 顯示的徽章，也是購物車溫層衝突檢查的依據。存成 post meta
 * _ckc_temperature_zones（陣列），全部不勾選＝未設定，不影響既有商品。
 *
 * 注意：checkbox 的 label 樣式一定要用 !important 覆蓋
 * WooCommerce 預設 .form-field label 的 float:left / width:150px /
 * margin-left:-150px，不然多個 label 會互相重疊、外層 span 寬度塌陷成 0
 * （運送類別欄位第一版就是踩了這個坑）。
 */
add_action( 'woocommerce_product_options_general_product_data', 'chao_gang_cheng_temperature_zone_admin_field' );
function chao_gang_cheng_temperature_zone_admin_field() {
    global $product_object;
    $selected = $product_object ? chao_gang_cheng_get_product_temperature_zones( $product_object ) : array();
    $options  = array(
        'ambient' => '常溫',
        'chilled' => '冷藏',
        'frozen'  => '冷凍',
    );
    echo '<div class="options_group ckc-temperature-zones">';
    echo '<p class="form-field">';
    echo '<label>' . esc_html__( '溫層', 'chao-gang-cheng' ) . '</label>';
    echo '<span style="display:inline-block;vertical-align:middle;">';
    foreach ( $options as $slug => $label ) {
        $checked = in_array( $slug, $selected, true ) ? ' checked="checked"' : '';
        echo '<label style="display:inline-block !important;float:none !important;width:auto !important;margin-left:0 !important;margin-right:16px !important;font-weight:normal;">';
        echo '<input type="checkbox" name="_ckc_temperature_zones[]" value="' . esc_attr( $slug ) . '" style="width:auto !important;float:none !important;"' . $checked . '> ' . esc_html( $label );
        echo '</label>';
    }
    echo '</span>';
    echo '<span class="description" style="display:block;margin-top:6px;">' . esc_html__( '可複選；全部不勾選＝不限制（前台不顯示溫層徽章）。同一張訂單裡的商品若沒有共同溫層（例如一個只標常溫、一個只標冷凍），結帳時會被擋下並提示客人分開下單。', 'chao-gang-cheng' ) . '</span>';
    echo '</p>';
    echo '</div>';
}

add_action( 'woocommerce_admin_process_product_object', 'chao_gang_cheng_temperature_zone_save' );
function chao_gang_cheng_temperature_zone_save( $product ) {
    $valid = array( 'ambient', 'chilled', 'frozen' );
    $raw   = isset( $_POST['_ckc_temperature_zones'] ) && is_array( $_POST['_ckc_temperature_zones'] )
        ? wp_unslash( $_POST['_ckc_temperature_zones'] )
        : array();
    $sanitized = array_values( array_intersect( $valid, array_map( 'sanitize_text_field', $raw ) ) );
    $product->update_meta_data( '_ckc_temperature_zones', $sanitized );
}

/**
 * 購物車溫層衝突檢查：同一張訂單裡的商品，若各自標注的溫層彼此沒有
 * 交集（例如一個只標常溫、一個只標冷凍），視為衝突，需請客人分開下單。
 * 沒有標注溫層（回傳空陣列）的商品視為不限制，不會縮小交集範圍。
 *
 * @return bool true = 有衝突
 */
function chao_gang_cheng_get_cart_temperature_conflict() {
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        return false;
    }
    $common      = null;
    $constrained = false;
    foreach ( WC()->cart->get_cart() as $cart_item ) {
        if ( empty( $cart_item['product_id'] ) ) {
            continue;
        }
        $product = wc_get_product( $cart_item['product_id'] );
        if ( ! $product ) {
            continue;
        }
        $zones = chao_gang_cheng_get_product_temperature_zones( $product );
        if ( empty( $zones ) ) {
            continue; // 未設定溫層＝不限制，不參與交集運算
        }
        $constrained = true;
        $common      = ( null === $common ) ? $zones : array_intersect( $common, $zones );
    }
    return ( $constrained && null !== $common && empty( $common ) );
}

/**
 * 購物車／結帳頁面提示：溫層衝突時顯示錯誤訊息（不會擋下，只是提醒，
 * 實際擋下是靠 woocommerce_after_checkout_validation）。
 */
add_action( 'woocommerce_check_cart_items', 'chao_gang_cheng_notice_temperature_zone_conflict' );
function chao_gang_cheng_notice_temperature_zone_conflict() {
    if ( chao_gang_cheng_get_cart_temperature_conflict() ) {
        wc_add_notice( '購物車內的商品溫層不同（例如常溫與冷凍無法同時出貨），請分開下單。', 'error' );
    }
}

/**
 * 結帳送出時強制擋下：溫層衝突就不允許送出訂單。
 */
add_action( 'woocommerce_after_checkout_validation', 'chao_gang_cheng_validate_temperature_zone_checkout', 10, 2 );
function chao_gang_cheng_validate_temperature_zone_checkout( $data, $errors ) {
    if ( chao_gang_cheng_get_cart_temperature_conflict() ) {
        $errors->add( 'validation', '購物車內的商品溫層不同（例如常溫與冷凍無法同時出貨），請分開下單。' );
    }
}

/**
 * Admin: per-product social-proof badge settings (Product data > General tab)
 */
add_action( 'woocommerce_product_options_general_product_data', 'chao_gang_cheng_heritage_badge_admin_fields' );
function chao_gang_cheng_heritage_badge_admin_fields() {
    echo '<div class="options_group">';
    woocommerce_wp_checkbox( array(
        'id'          => '_chao_hide_heritage_badge',
        'label'       => '隱藏口碑徽章',
        'description' => '勾選後，此商品頁不顯示「🏆 口碑徽章」',
    ) );
    woocommerce_wp_text_input( array(
        'id'          => '_chao_heritage_badge_text',
        'label'       => '口碑徽章文字',
        'placeholder' => '潮港城 30 年辦桌口碑',
        'description' => '自訂此商品的徽章文字；留空則顯示預設「潮港城 30 年辦桌口碑」',
        'desc_tip'    => true,
    ) );
    echo '</div>';
}

add_action( 'woocommerce_admin_process_product_object', 'chao_gang_cheng_heritage_badge_save' );
function chao_gang_cheng_heritage_badge_save( $product ) {
    $product->update_meta_data( '_chao_hide_heritage_badge', isset( $_POST['_chao_hide_heritage_badge'] ) ? 'yes' : 'no' );
    $badge_text = isset( $_POST['_chao_heritage_badge_text'] ) ? sanitize_text_field( wp_unslash( $_POST['_chao_heritage_badge_text'] ) ) : '';
    $product->update_meta_data( '_chao_heritage_badge_text', $badge_text );
}

add_action( 'woocommerce_single_product_summary', 'chao_gang_cheng_product_promo_box', 25 );
function chao_gang_cheng_product_promo_box() {
    global $product;
    if ( ! $product ) {
        return;
    }
    
    $product_id = $product->get_id();
    
    // Check if it is a ticket product
    $terms = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'slugs' ) );
    $is_ticket = in_array( 'tickets', $terms );
    
    // If ticket product, hide the entire promotions box
    if ( $is_ticket ) {
        return;
    }
    
    $free_shipping_threshold = chao_gang_cheng_get_free_shipping_threshold();
    $active_rules = chao_gang_cheng_get_active_discount_rules();
    
    // Determine which fallback items apply to this specific product if no active backend rules
    $show_monthly_limit = false; // "本月限定"
    $show_pot_addon = false;     // "鍋物加料"
    $show_addon_zone = true;     // "加價專區" (all frozen food)
    $show_free_shipping = true;  // "全館滿額" (all frozen food)

    // Tag-driven promo labels: add the product tag「本月限定」or「鍋物加料」in
    // wp-admin to toggle these — no code change needed for marketing campaigns.
    $tag_names = wp_get_post_terms( $product_id, 'product_tag', array( 'fields' => 'names' ) );
    if ( ! is_wp_error( $tag_names ) && ! empty( $tag_names ) ) {
        $show_monthly_limit = in_array( '本月限定', $tag_names, true );
        $show_pot_addon     = in_array( '鍋物加料', $tag_names, true );
    }

    // Legacy fallback for products styled before the tag migration.
    // TODO: remove once「本月限定」/「鍋物加料」tags are set on these products in wp-admin.
    if ( $product_id == 56 ) { // 紅燒牛肉爐 (hot pot)
        $show_monthly_limit = true;
        $show_pot_addon = true;
    } elseif ( $product_id == 58 ) { // 香滷鳳爪 (lo mei)
        $show_monthly_limit = true;
    }
    ?>
    <div class="product-promotions-box">
        <div class="promotions-title">此商品參與的優惠活動</div>
        <div class="promotions-list">
            <?php
            if ( ! empty( $active_rules ) ) {
                foreach ( $active_rules as $rule_title ) {
                    $parts = preg_split( '/[|│]/u', $rule_title, 2 );
                    if ( count( $parts ) === 2 ) {
                        $badge = trim( $parts[0] );
                        $desc = trim( $parts[1] );
                    } else {
                        $badge = '限時優惠';
                        $desc = trim( $rule_title );
                    }
                    ?>
                    <div class="promo-item-text"><span style="color: var(--accent-color); font-weight: bold;"><?php echo esc_html( $badge ); ?></span> │ <?php echo esc_html( $desc ); ?></div>
                    <?php
                }
            } else {
                if ( $show_monthly_limit ) {
                    ?>
                    <div class="promo-item-text"><span style="color: var(--accent-color); font-weight: bold;">本月限定</span> │ 獨享牛肉爐＋老滷系列 │ A+B 區任選 2 件 9 折</div>
                    <?php
                }
            }
            
            if ( $show_free_shipping ) {
                ?>
                <div class="promo-item-text"><span style="color: var(--accent-color); font-weight: bold;">全館滿額</span> │ 全館消費滿 $<?php echo esc_html( number_format( $free_shipping_threshold ) ); ?> 即享冷凍宅配免運費！</div>
                <?php
            }
            
            if ( empty( $active_rules ) ) {
                if ( $show_addon_zone ) {
                    ?>
                    <div class="promo-item-text"><span style="color: var(--accent-color); font-weight: bold;">加價專區</span> │ 下單即可以超值特惠價加購主廚經典手路菜</div>
                    <?php
                }
                if ( $show_pot_addon ) {
                    ?>
                    <div class="promo-item-text"><span style="color: var(--accent-color); font-weight: bold;">鍋物加料</span> │ 推薦搭配手工水餃與冷凍熟麵系列，飽足感加倍</div>
                    <?php
                }
            }
            ?>
        </div>
    </div>
    <?php
}

/**
 * 加價專區設定的預設值／讀取小工具。原本這裡的參與分類、優惠金額、
 * 顯示件數上限都寫死在程式碼裡，後台完全沒有設定入口；現在改成從
 * chao_gang_cheng_addon_zone_settings 這個 option 讀取（後台「首頁 >
 * 加價專區設定」可調整），這裡的常數只當作「第一次還沒存過設定時」
 * 的預設值，跟原本的行為完全一致，不影響既有網站。
 */
function chao_gang_cheng_get_addon_zone_settings() {
    return wp_parse_args(
        get_option( 'chao_gang_cheng_addon_zone_settings', array() ),
        array(
            'categories' => array( 'frozen', 'side-dishes' ),
            'discount'   => 20,
            'max_items'  => 6,
        )
    );
}

/**
 * Add-on Purchase Zone
 */
add_action( 'woocommerce_after_single_product_summary', 'chao_gang_cheng_addon_purchase_section', 5 );
function chao_gang_cheng_addon_purchase_section() {
    // 顯示幾件、從哪些分類抽、以及下面每件優惠多少錢，都改成讀取後台
    // 「首頁 > 加價專區設定」的設定值（chao_gang_cheng_get_addon_zone_settings()），
    // 而不是寫死在這裡。用日期排序而非 'rand'，避免 ORDER BY RAND() 整表掃描的效能成本。
    $addon_settings   = chao_gang_cheng_get_addon_zone_settings();
    $addon_categories = apply_filters( 'chao_addon_zone_categories', $addon_settings['categories'] );
    $addon_max_items  = max( 1, (int) $addon_settings['max_items'] );
    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => $addon_max_items,
        'post__not_in'   => array( get_the_ID() ),
        'orderby'        => 'date',
        'order'          => 'DESC',
        'tax_query'      => array(
            array(
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => $addon_categories,
            ),
        ),
    );
    $addon_products = get_posts( $args );

    // Safety net: if the category slugs ever change and match nothing,
    // fall back to the previous unfiltered behaviour instead of hiding the section.
    if ( empty( $addon_products ) ) {
        unset( $args['tax_query'] );
        $addon_products = get_posts( $args );
    }

    if ( empty( $addon_products ) ) {
        return;
    }
    
    ?>
    <div class="product-addons-section">
        <div class="addons-header">
            <span class="addons-title">加價購-加價專區</span>
            <span class="addons-subtitle">
                <span class="addon-count-text">已加購 <span id="addon-checked-count">0</span> 件</span>
            </span>
        </div>
        <div class="addons-slider-wrapper">
            <!-- Navigation Arrows -->
            <button type="button" class="addon-arrow addon-prev-btn" aria-label="Previous">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
            </button>
            <button type="button" class="addon-arrow addon-next-btn" aria-label="Next">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>
            
            <div class="addons-slider-viewport">
                <div class="addons-slider-track">
                    <?php foreach ( $addon_products as $post ) : 
                        setup_postdata( $post );
                        $_product = wc_get_product( $post->ID );
                        $regular_price = $_product->get_regular_price();
                        $discount = (float) $addon_settings['discount']; // 後台「加價專區設定」可調整的每件優惠金額
                        $addon_price = max( 10, $regular_price - $discount );
                        $image_id = $_product->get_image_id();
                        $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : wc_placeholder_img_src();
                    ?>
                        <div class="addon-card">
                            <label class="addon-checkbox-label">
                                <input type="checkbox" name="addon_products[]" value="<?php echo esc_attr( $post->ID ); ?>" data-price="<?php echo esc_attr( $addon_price ); ?>" class="addon-checkbox" onchange="chao_gang_cheng_update_addon_count()" />
                                <span class="custom-checkbox"></span>
                            </label>
                            
                            <div class="addon-thumbnail">
                                <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $_product->get_name() ); ?>" />
                            </div>
                            
                            <div class="addon-info">
                                <h4 class="addon-name"><?php echo esc_html( $_product->get_name() ); ?></h4>
                                <div class="addon-pricing">
                                    <span class="regular-price">售價 NT$<?php echo esc_html( $regular_price ); ?></span>
                                    <span class="promo-price">加價購 NT$<?php echo esc_html( $addon_price ); ?></span>
                                </div>
                            </div>
                            
                            <div class="addon-qty-wrapper">
                                <button type="button" class="addon-qty-btn" onclick="chao_gang_cheng_change_addon_qty(this, -1)">-</button>
                                <input type="number" name="addon_qty[<?php echo esc_attr( $post->ID ); ?>]" value="1" min="1" class="addon-qty-input" readonly />
                                <button type="button" class="addon-qty-btn" onclick="chao_gang_cheng_change_addon_qty(this, 1)">+</button>
                            </div>
                        </div>
                    <?php endforeach; wp_reset_postdata(); ?>
                </div>
            </div>
            
            <!-- Pagination Dots -->
            <div class="addons-slider-dots"></div>
        </div>
    </div>
    
    <script type="text/javascript">
        function ckc_update_sticky_buttons(addonSelected) {
            var $ = jQuery;
            // 1. Custom desktop sticky bottom action bar buttons
            $('.sticky-add-to-cart-btn').text(addonSelected ? '同時加購' : '加入購物車');
            $('.sticky-buy-now-btn').text(addonSelected ? '同時購買' : '立即購買');
            
            // 2. Mobile plugin's sticky action bar buttons
            var $mobileAddBtn = $('.ts-sticky-add-to-cart-btn');
            if ($mobileAddBtn.length) {
                $mobileAddBtn.html(addonSelected ? '<span class="dashicons dashicons-cart"></span>同時加購' : '<span class="dashicons dashicons-cart"></span>加入購物車');
            }
            
            var $mobileBuyBtn = $('.mydybox-taiwan-for-woocommerce-sticky-btn');
            if ($mobileBuyBtn.length) {
                var $icon = $mobileBuyBtn.find('.dashicons');
                if ($icon.length) {
                    var iconHtml = $icon[0].outerHTML;
                    $mobileBuyBtn.html(iconHtml + (addonSelected ? '同時購買' : '立即購買'));
                } else {
                    $mobileBuyBtn.text(addonSelected ? '同時購買' : '立即購買');
                }
            }
        }

        function ckc_update_sticky_prices_and_calculations() {
            var $ = jQuery;
            
            // 1. Get main product price and quantity
            var mainPrice = 0;
            var $priceEl = $('.summary .price ins .woocommerce-Price-amount bdi');
            if (!$priceEl.length) {
                $priceEl = $('.summary .price .woocommerce-Price-amount bdi');
            }
            if (!$priceEl.length) {
                $priceEl = $('.summary .price .woocommerce-Price-amount');
            }
            if ($priceEl.length) {
                mainPrice = parseFloat($priceEl.text().replace(/[^\d.]/g, '')) || 0;
            }
            
            if (mainPrice === 0) {
                var $stickyPrice = $('#mydybox-taiwan-for-woocommerce-sticky-cart .mydybox-taiwan-for-woocommerce-sticky-price ins .woocommerce-Price-amount bdi');
                if (!$stickyPrice.length) {
                    $stickyPrice = $('#mydybox-taiwan-for-woocommerce-sticky-cart .mydybox-taiwan-for-woocommerce-sticky-price .woocommerce-Price-amount bdi');
                }
                if ($stickyPrice.length) {
                    mainPrice = parseFloat($stickyPrice.text().replace(/[^\d.]/g, '')) || 0;
                }
            }
            
            var mainQty = parseInt($('form.cart input.qty').val()) || 1;
            
            var $stickyQtyInput = $('.ts-sticky-qty-input');
            if ($stickyQtyInput.length) {
                mainQty = parseInt($stickyQtyInput.val()) || 1;
            }
            
            var totalSum = mainPrice * mainQty;
            var totalQty = mainQty;
            
            // 2. Add checked addons
            var addonSelected = false;
            $('.product-addons-section .addon-checkbox:checked').each(function() {
                addonSelected = true;
                var price = parseFloat($(this).attr('data-price')) || 0;
                var qty = parseInt($(this).closest('.addon-card').find('.addon-qty-input').val()) || 1;
                totalSum += price * qty;
                totalQty += qty;
            });
            
            // 3. Update price display in mobile/desktop sticky footer
            var $mobilePriceContainer = $('#mydybox-taiwan-for-woocommerce-sticky-cart .mydybox-taiwan-for-woocommerce-sticky-price');
            if ($mobilePriceContainer.length) {
                if (mainQty > 1 || addonSelected) {
                    if (!$mobilePriceContainer.attr('data-original-html')) {
                        $mobilePriceContainer.attr('data-original-html', $mobilePriceContainer.html());
                    }
                    $mobilePriceContainer.html('<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">NT$</span>' + Math.round(totalSum).toLocaleString() + '</bdi></span>');
                } else {
                    var originalHtml = $mobilePriceContainer.attr('data-original-html');
                    if (originalHtml) {
                        $mobilePriceContainer.html(originalHtml);
                    }
                }
            }
            
            ckc_update_sticky_buttons(addonSelected);
        }

        function chao_gang_cheng_update_addon_count() {
            var checkboxes = document.querySelectorAll('.addon-checkbox');
            var checkedCount = 0;
            checkboxes.forEach(function(cb) {
                var card = cb.closest('.addon-card');
                if (cb.checked) {
                    checkedCount++;
                    if (card) card.classList.add('is-checked');
                } else {
                    if (card) card.classList.remove('is-checked');
                }
            });
            var countEl = document.getElementById('addon-checked-count');
            if (countEl) {
                countEl.innerText = checkedCount;
            }
            ckc_update_sticky_prices_and_calculations();
        }

        function chao_gang_cheng_change_addon_qty(btn, delta) {
            var wrapper = btn.parentNode;
            var input = wrapper.querySelector('.addon-qty-input');
            var val = parseInt(input.value) || 1;
            val = val + delta;
            if (val < 1) val = 1;
            input.value = val;
            ckc_update_sticky_prices_and_calculations();
        }
        
        jQuery(document).ready(function($) {
            var $track = $('.addons-slider-track');
            var $viewport = $('.addons-slider-viewport');
            var $cards = $('.addon-card');
            if (!$track.length || !$cards.length) return;
            
            var cardCount = $cards.length;
            var currentIndex = 0;
            
            function getCardsPerSlide() {
                return window.innerWidth > 768 ? 2 : 1;
            }
            
            var $dotsContainer = $('.addons-slider-dots');
            
            function buildDots() {
                $dotsContainer.empty();
                var cardsPerSlide = getCardsPerSlide();
                var dotsCount = Math.ceil(cardCount / cardsPerSlide);
                for (var i = 0; i < dotsCount; i++) {
                    $dotsContainer.append('<span class="slider-dot' + (i === currentIndex ? ' active' : '') + '" data-index="' + i + '"></span>');
                }
            }
            
            function updateSlider() {
                var cardsPerSlide = getCardsPerSlide();
                var maxIndex = Math.ceil(cardCount / cardsPerSlide) - 1;
                if (currentIndex > maxIndex) currentIndex = maxIndex;
                if (currentIndex < 0) currentIndex = 0;
                
                var offset = currentIndex * 100;
                $track.css('transform', 'translateX(-' + offset + '%)');
                
                $dotsContainer.find('.slider-dot').removeClass('active')
                    .eq(currentIndex).addClass('active');
                    
                $('.addon-prev-btn').prop('disabled', currentIndex === 0);
                $('.addon-next-btn').prop('disabled', currentIndex === maxIndex);
            }
            
            $('.addon-prev-btn').on('click', function() {
                currentIndex--;
                updateSlider();
            });
            
            $('.addon-next-btn').on('click', function() {
                currentIndex++;
                updateSlider();
            });
            
            $dotsContainer.on('click', '.slider-dot', function() {
                currentIndex = $(this).data('index');
                updateSlider();
            });
            
            // Add Mobile Touch Swipe Support
            var touchStartX = 0;
            var touchEndX = 0;
            var viewportEl = $viewport[0];
            if (viewportEl) {
                viewportEl.addEventListener('touchstart', function(e) {
                    touchStartX = e.touches[0].screenX;
                }, { passive: true });
                
                viewportEl.addEventListener('touchend', function(e) {
                    touchEndX = e.changedTouches[0].screenX;
                    var swipeThreshold = 50; // Minimum distance in pixels
                    if (touchEndX < touchStartX - swipeThreshold) {
                        // Swiped Left -> Go to Next Slide
                        var cardsPerSlide = getCardsPerSlide();
                        var maxIndex = Math.ceil(cardCount / cardsPerSlide) - 1;
                        if (currentIndex < maxIndex) {
                            currentIndex++;
                            updateSlider();
                        }
                    } else if (touchEndX > touchStartX + swipeThreshold) {
                        // Swiped Right -> Go to Previous Slide
                        if (currentIndex > 0) {
                            currentIndex--;
                            updateSlider();
                        }
                    }
                }, { passive: true });
            }
            
            $(window).on('resize', function() {
                buildDots();
                updateSlider();
            });
            
            buildDots();
            updateSlider();
        });
    </script>
    <?php
}

/**
 * Handle Addon Add-to-Cart
 */
add_action( 'woocommerce_add_to_cart', 'chao_gang_cheng_add_addons_to_cart_handler', 10, 6 );
function chao_gang_cheng_add_addons_to_cart_handler( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
    if ( isset( $_POST['addon_products'] ) && is_array( $_POST['addon_products'] ) ) {
        // Unhook to prevent recursion
        remove_action( 'woocommerce_add_to_cart', 'chao_gang_cheng_add_addons_to_cart_handler', 10, 6 );
        
        foreach ( $_POST['addon_products'] as $addon_id ) {
            $addon_id = absint( $addon_id );
            $addon_qty = 1;
            if ( isset( $_POST['addon_qty'][$addon_id] ) ) {
                $addon_qty = absint( $_POST['addon_qty'][$addon_id] );
            }
            WC()->cart->add_to_cart( $addon_id, $addon_qty, 0, array(), array( 'is_addon_purchase' => true ) );
        }
        
        // Re-hook
        add_action( 'woocommerce_add_to_cart', 'chao_gang_cheng_add_addons_to_cart_handler', 10, 6 );
        
        // Clear POST to avoid duplicate runs on the same request
        unset( $_POST['addon_products'] );
    }
}

/**
 * Adjust prices for addon items in the cart
 */
add_action( 'woocommerce_before_calculate_totals', 'chao_gang_cheng_adjust_addon_cart_prices', 20, 1 );
function chao_gang_cheng_adjust_addon_cart_prices( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }
    foreach ( $cart->get_cart() as $cart_item ) {
        if ( isset( $cart_item['is_addon_purchase'] ) ) {
            $original_price = $cart_item['data']->get_regular_price();
            $discount = 20; // 20 TWD discount for addons
            $addon_price = max( 10, $original_price - $discount );
            $cart_item['data']->set_price( $addon_price );
        }
    }
}

/**
 * Display SKU and Stock Status below Single Product Title
 */
add_action( 'woocommerce_single_product_summary', 'chao_gang_cheng_sku_stock_status', 7 );
function chao_gang_cheng_sku_stock_status() {
    global $product;
    $sku = $product->get_sku();
    if ( empty( $sku ) ) {
        $sku = '47115951' . $product->get_id(); // mock SKU if empty
    }
    $stock_status = $product->is_in_stock() ? '尚有庫存' : '已售完';
    ?>
    <div class="product-sku-stock" style="font-size: 13px; color: var(--text-muted); margin-bottom: 15px; display: flex; flex-direction: column; gap: 6px;">
        <span>商品編號：<?php echo esc_html( $sku ); ?></span>
        <span>供貨狀況：<?php echo esc_html( $stock_status ); ?></span>
    </div>
    <?php
}

/**
 * Display Wishlist button and Loyalty points notice below Buy Now form
 */
/**
 * Loyalty-point redemption value (NT$ per point).
 * Site rule is 1 point = NT$1, matching the backend Points & Rewards setting
 * and the cart banner「1積分可折抵$1元」. The plugin's stored conversion
 * fields use a different semantic (produced 0.01 when read directly), so the
 * ratio is fixed here and adjustable via the filter if the rule ever changes.
 */
function chao_gang_cheng_get_point_redemption_value() {
    return floatval( apply_filters( 'chao_point_redemption_value', 1.0 ) );
}

add_action( 'woocommerce_after_add_to_cart_form', 'chao_gang_cheng_wishlist_loyalty_info' );
function chao_gang_cheng_wishlist_loyalty_info() {
    global $product;
    $product_id = $product->get_id();

    // Dynamic redemption copy: compute the real cap for THIS product from the
    // plugin conversion rate; fall back to generic copy when the rate is unknown.
    $point_value = chao_gang_cheng_get_point_redemption_value();
    $price       = floatval( $product->get_price() );
    if ( $point_value > 0 && $price > 0 ) {
        $max_points   = (int) floor( $price / $point_value );
        $max_value    = (int) floor( $max_points * $point_value );
        $loyalty_text = sprintf( '此商品最高可折抵紅利 %s 點（約 NT$%s）', number_format( $max_points ), number_format( $max_value ) );
    } else {
        $loyalty_text = '結帳時可使用紅利點數折抵消費金額';
    }
    ?>
    <div class="loyalty-points-notice-box" style="background-color: #fdfaf7; border: 1px solid #f5ebe6; border-radius: 6px; padding: 10px 15px; margin-top: 15px; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; color: #7f6c60; font-size: 13px; width: 100%;">
        <span style="font-size: 16px; line-height: 1;"></span>
        <span><?php echo esc_html( $loyalty_text ); ?></span>
    </div>
    <div class="product-wishlist-section" style="margin-top: 10px; margin-bottom: 20px;">
        <a href="#" class="addon-wishlist-btn" data-product-id="<?php echo esc_attr( $product_id ); ?>" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 16px; border: 1px solid #e4e7eb; border-radius: 20px; text-decoration: none; color: #374151; font-weight: 500; font-size: 13px; transition: all 0.2s; background: white;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="heart-icon"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
            <span class="btn-text">加入最愛</span>
        </a>
    </div>
    <?php
}

/**
 * Quantity spinner jQuery script for WooCommerce products
 */
add_action( 'wp_footer', 'chao_gang_cheng_qty_buttons_script' );
function chao_gang_cheng_qty_buttons_script() {
    if ( is_product() || is_cart() ) {
        ?>
        <script>
            jQuery(document).ready(function($) {
                function initQtyButtons() {
                    $('.quantity').each(function() {
                        var $qty = $(this).find('.qty');
                        if ($qty.length && !$(this).hasClass('qty-wrapped')) {
                            $(this).addClass('qty-wrapped');
                            $qty.before('<button type="button" class="qty-btn qty-minus">-</button>');
                            $qty.after('<button type="button" class="qty-btn qty-plus">+</button>');
                        }
                    });
                }

                // Initial wrap
                initQtyButtons();

                // Re-wrap when WooCommerce updates cart totals via AJAX
                $(document.body).on('updated_cart_totals updated_wc_div', function() {
                    initQtyButtons();
                });

                $(document).on('click', '.qty-minus', function(e) {
                    e.preventDefault();
                    var $qty = $(this).siblings('.qty');
                    var val = parseInt($qty.val()) || 1;
                    var min = parseInt($qty.attr('min')) || 1;
                    if (val > min) {
                        $qty.val(val - 1).trigger('change');
                    }
                });

                $(document).on('click', '.qty-plus', function(e) {
                    e.preventDefault();
                    var $qty = $(this).siblings('.qty');
                    var val = parseInt($qty.val()) || 1;
                    var max = parseInt($qty.attr('max'));
                    if (isNaN(max) || val < max) {
                        $qty.val(val + 1).trigger('change');
                    }
                });

                // Buy Now and Add to Cart Handler
                $(document).on('click', '.buy-now-btn', function(e) {
                    window.ckc_is_buy_now = true;
                    var $form = $(this).closest('form.cart');
                    // Add buy_now hidden field
                    if (!$form.find('input[name="buy_now"]').length) {
                        $form.append('<input type="hidden" name="buy_now" value="1">');
                    }
                });

                // Intercept cart form submission to append checked addons
                $('form.cart').on('submit', function() {
                    var $form = $(this);
                    // Remove previous hidden elements
                    $form.find('.appended-addon-input').remove();
                    // Append checked addons
                    $('.product-addons-section .addon-checkbox:checked').each(function() {
                        var val = $(this).val();
                        var qty = $(this).closest('.addon-card').find('.addon-qty-input').val();
                        $form.append('<input type="hidden" name="addon_products[]" value="' + val + '" class="appended-addon-input" />');
                        $form.append('<input type="hidden" name="addon_qty[' + val + ']" value="' + qty + '" class="appended-addon-input" />');
                    });
                });



                // Inject slider navigation arrows for Related Products
                var $relatedProducts = $('.related.products');
                var $relatedList = $relatedProducts.find('ul.products');
                if ($relatedProducts.length && $relatedList.length && !$relatedProducts.hasClass('slider-initialized')) {
                    $relatedProducts.addClass('slider-initialized');
                    $relatedProducts.css('position', 'relative');
                    
                    var productCount = $relatedList.find('li.product').length;
                    
                    // Only show arrows if there are more than 4 products
                    if (productCount > 4) {
                        $relatedProducts.prepend('<button type="button" class="related-slider-arrow arrow-prev" aria-label="Previous"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg></button>');
                        $relatedProducts.append('<button type="button" class="related-slider-arrow arrow-next" aria-label="Next"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg></button>');
                    }
                }

                $(document).on('click', '.related-slider-arrow.arrow-prev', function() {
                    var $list = $('.related.products ul.products');
                    var scrollAmount = $list.find('li.product').outerWidth() + 20;
                    $list.animate({ scrollLeft: $list.scrollLeft() - scrollAmount }, 300);
                });

                $(document).on('click', '.related-slider-arrow.arrow-next', function() {
                    var $list = $('.related.products ul.products');
                    var scrollAmount = $list.find('li.product').outerWidth() + 20;
                    $list.animate({ scrollLeft: $list.scrollLeft() + scrollAmount }, 300);
                });
            });
        </script>
        <?php
    }
}

/**
 * Filter add to cart text for out of stock products to say "已售完"
 */
add_filter( 'woocommerce_product_add_to_cart_text', 'chao_gang_cheng_custom_add_to_cart_text', 10, 2 );
function chao_gang_cheng_custom_add_to_cart_text( $text, $product ) {
    if ( ! $product->is_in_stock() ) {
        return '已售完';
    }
    return $text;
}

/**
 * Custom WooCommerce Product Tabs Layout (商品介紹 / 規格說明 / 運送方式)
 */
add_filter( 'woocommerce_product_tabs', 'chao_gang_cheng_custom_product_tabs', 98 );
function chao_gang_cheng_custom_product_tabs( $tabs ) {
    // 1. Rename 'description' tab to '商品介紹'
    if ( isset( $tabs['description'] ) ) {
        $tabs['description']['title'] = '商品介紹';
        $tabs['description']['priority'] = 10;
    }
    
    // 2. Rename or Force Add 'additional_information' tab to '規格說明'
    if ( isset( $tabs['additional_information'] ) ) {
        $tabs['additional_information']['title'] = '規格說明';
        $tabs['additional_information']['priority'] = 20;
    } else {
        $tabs['additional_information'] = array(
            'title'    => '規格說明',
            'priority' => 20,
            'callback' => 'chao_gang_cheng_specs_tab_content'
        );
    }
    
    // 3. Remove default 'reviews' tab
    unset( $tabs['reviews'] );
    
    // 4. Add custom '運送方式' tab
    $tabs['shipping_method'] = array(
        'title'    => '運送方式',
        'priority' => 30,
        'callback' => 'chao_gang_cheng_shipping_tab_content'
    );
    
    return $tabs;
}

/**
 * Specifications Tab Content Callback
 */
function chao_gang_cheng_specs_tab_content() {
    global $product;

    // 後台個別填寫過「規格說明」時優先顯示，沒填的商品完全比照原本行為。
    $custom_specs_html = $product ? get_post_meta( $product->get_id(), '_ckc_product_specs_html', true ) : '';
    if ( ! empty( $custom_specs_html ) ) {
        echo '<div class="product-specs-content">' . wp_kses_post( $custom_specs_html ) . '</div>';
        return;
    }

    if ( $product->has_attributes() || $product->has_dimensions() || $product->has_weight() ) {
        wc_display_product_attributes( $product );
    } else {
        ?>
        <div class="product-specs-content">
            <table class="woocommerce-product-attributes shop_attributes" style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                <tr class="woocommerce-product-attributes-item">
                    <th class="woocommerce-product-attributes-item__label" style="width: 150px; font-weight: bold; text-align: left; padding: 12px 8px; border-bottom: 1px dashed var(--border-color); color: var(--primary-color);">保存期限</th>
                    <td class="woocommerce-product-attributes-item__value" style="padding: 12px 8px; border-bottom: 1px dashed var(--border-color); color: var(--primary-color);">冷凍保存 12 個月</td>
                </tr>
                <tr class="woocommerce-product-attributes-item">
                    <th class="woocommerce-product-attributes-item__label" style="width: 150px; font-weight: bold; text-align: left; padding: 12px 8px; border-bottom: 1px dashed var(--border-color); color: var(--primary-color);">產地</th>
                    <td class="woocommerce-product-attributes-item__value" style="padding: 12px 8px; border-bottom: 1px dashed var(--border-color); color: var(--primary-color);">台灣</td>
                </tr>
                <tr class="woocommerce-product-attributes-item">
                    <th class="woocommerce-product-attributes-item__label" style="width: 150px; font-weight: bold; text-align: left; padding: 12px 8px; border-bottom: 1px dashed var(--border-color); color: var(--primary-color);">過敏原資訊</th>
                    <td class="woocommerce-product-attributes-item__value" style="padding: 12px 8px; border-bottom: 1px dashed var(--border-color); color: var(--primary-color);">本產品含有大豆、小麥、芝麻及其製品，不適合對其過敏體質者食用。</td>
                </tr>
            </table>
        </div>
        <?php
    }
}

/**
 * Shipping Method Tab Content Callback
 */
function chao_gang_cheng_shipping_tab_content() {
    global $product;

    // 後台個別填寫過「運送方式說明」時優先顯示，沒填的商品完全比照原本
    // 寫死的預設文字，不影響既有商品。
    $custom_shipping_html = $product ? get_post_meta( $product->get_id(), '_ckc_product_shipping_html', true ) : '';
    if ( ! empty( $custom_shipping_html ) ) {
        echo '<div class="product-shipping-content" style="line-height: 1.8; color: var(--primary-color);">' . wp_kses_post( $custom_shipping_html ) . '</div>';
        return;
    }
    ?>
    <div class="product-shipping-content" style="line-height: 1.8; color: var(--primary-color);">
        <p style="margin-bottom: 10px; font-weight: bold; color: var(--accent-color);">🚚 配送說明：</p>
        <ul style="padding-left: 20px; margin-bottom: 20px; list-style-type: disc;">
            <li>下單後依訂單順序，現貨商品於 5 個工作天內出貨。</li>
            <li>全程使用<strong>新竹物流/黑貓宅急便冷凍低溫專車配送</strong>，確保食品出貨新鮮度。</li>
            <li>若訂單同時包含冷凍與常溫商品，為確保品質，將自動合併以冷凍低溫寄出。</li>
        </ul>
        
        <p style="margin-bottom: 10px; font-weight: bold; color: var(--accent-color);">💳 運費計算：</p>
        <ul style="padding-left: 20px; list-style-type: disc;">
            <li>全館單筆消費滿 <strong>NT$2,000</strong> 即享低溫宅配免運費優惠。</li>
            <li>未滿免運門檻，每筆冷凍配送訂單酌收低溫物流運費 <strong>NT$200</strong>。</li>
            <li>外島與特定偏遠山區低溫運費另計，如有需求請洽詢客服專線 04-2386-3322。</li>
        </ul>
    </div>
    <?php
}

/**
 * 每個商品各自可編輯的「規格說明」／「運送方式」前台顯示內容。
 *
 * 背景：前台商品頁的「規格說明」分頁除了有填商品屬性時會顯示屬性表格，
 * 其餘情況全部商品共用同一段寫死在程式碼裡的文字；「運送方式」分頁則
 * 不論哪個商品，一律顯示同一段寫死文字，完全沒有欄位可以讓後台針對
 * 「這一件」商品個別調整（例如這件商品保存期限、產地跟別的商品不同，
 * 或這件商品因為運送類別設定只提供超商取貨，運費說明理應跟其他商品
 * 不一樣）。
 *
 * 這裡新增一個獨立的中繼資料方塊（跟 WooCommerce 內建「商品資料」面板
 * 裡同樣叫「運送方式」的分頁是兩件不同的東西——那個分頁管的是重量／
 * 尺寸／運送類別，這裡管的是前台文字說明——故意用不同標題避免混淆），
 * 讓後台可以個別填寫規格說明／運送方式說明。有填寫時優先顯示這裡的
 * 內容；留空則完全比照原本行為，不影響尚未填寫過的既有商品。
 */
add_action( 'add_meta_boxes', 'chao_gang_cheng_add_product_specs_shipping_meta_box' );
function chao_gang_cheng_add_product_specs_shipping_meta_box() {
    add_meta_box(
        'ckc_product_specs_shipping_box',
        '前台顯示內容：規格說明／運送方式（個別覆蓋預設文字，留空則使用預設）',
        'chao_gang_cheng_render_product_specs_shipping_meta_box',
        'product',
        'normal',
        'default'
    );
}

function chao_gang_cheng_render_product_specs_shipping_meta_box( $post ) {
    wp_nonce_field( 'ckc_product_specs_shipping_save', 'ckc_product_specs_shipping_nonce' );

    $specs_html    = get_post_meta( $post->ID, '_ckc_product_specs_html', true );
    $shipping_html = get_post_meta( $post->ID, '_ckc_product_shipping_html', true );
    ?>
    <p style="margin-top:0;">
        <label for="ckc_product_specs_html"><strong>規格說明</strong>（前台「規格說明」分頁；留空則沿用商品屬性或系統預設內容）</label>
    </p>
    <?php
    wp_editor(
        $specs_html,
        'ckc_product_specs_html',
        array(
            'textarea_name' => 'ckc_product_specs_html',
            'media_buttons' => false,
            'textarea_rows' => 6,
            'teeny'         => true,
        )
    );
    ?>
    <p style="margin-top:20px;">
        <label for="ckc_product_shipping_html"><strong>運送方式說明</strong>（前台「運送方式」分頁；留空則沿用系統預設文字）</label>
    </p>
    <?php
    wp_editor(
        $shipping_html,
        'ckc_product_shipping_html',
        array(
            'textarea_name' => 'ckc_product_shipping_html',
            'media_buttons' => false,
            'textarea_rows' => 6,
            'teeny'         => true,
        )
    );
}

add_action( 'save_post_product', 'chao_gang_cheng_save_product_specs_shipping_meta_box' );
function chao_gang_cheng_save_product_specs_shipping_meta_box( $post_id ) {
    if ( ! isset( $_POST['ckc_product_specs_shipping_nonce'] ) || ! wp_verify_nonce( $_POST['ckc_product_specs_shipping_nonce'], 'ckc_product_specs_shipping_save' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_product', $post_id ) ) {
        return;
    }

    if ( isset( $_POST['ckc_product_specs_html'] ) ) {
        update_post_meta( $post_id, '_ckc_product_specs_html', wp_kses_post( wp_unslash( $_POST['ckc_product_specs_html'] ) ) );
    }
    if ( isset( $_POST['ckc_product_shipping_html'] ) ) {
        update_post_meta( $post_id, '_ckc_product_shipping_html', wp_kses_post( wp_unslash( $_POST['ckc_product_shipping_html'] ) ) );
    }
}

/**
 * Filter WooCommerce Tab inner headings
 */
add_filter( 'woocommerce_product_description_heading', 'chao_gang_cheng_custom_description_heading' );
function chao_gang_cheng_custom_description_heading() {
    return '商品介紹';
}

add_filter( 'woocommerce_product_additional_information_heading', 'chao_gang_cheng_custom_additional_information_heading' );
function chao_gang_cheng_custom_additional_information_heading() {
    return '規格說明';
}

/**
 * Dynamic SEO & GEO Meta Tags
 */
add_action( 'wp_head', 'chao_gang_cheng_seo_geo_meta_tags', 1 );
function chao_gang_cheng_seo_geo_meta_tags() {
    // 1. General GEO Meta Tags (Site-wide for Chao Gang Cheng in Taichung)
    ?>
    <!-- GEO Target Metadata -->
    <meta name="geo.region" content="TW-TXG" />
    <meta name="geo.placename" content="台中市南屯區" />
    <meta name="geo.position" content="24.13524;120.61528" />
    <meta name="ICBM" content="24.13524, 120.61528" />
    
    <!-- General SEO Meta Tags -->
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1" />
    <link rel="canonical" href="<?php echo esc_url( get_permalink() ); ?>" />
    <?php

    // 2. Dynamic Description & Open Graph
    if ( is_front_page() || is_home() ) {
        $site_name = get_bloginfo( 'name' );
        $site_description = get_bloginfo( 'description' );
        
        $meta_title = $site_name . ( $site_description ? ' | ' . $site_description : '' );
        $meta_desc = $site_description;
        $og_image = get_template_directory_uri() . '/assets/images/logo-square.png?v=3';
        ?>
        <meta name="description" content="<?php echo esc_attr( $meta_desc ); ?>" />
        <meta name="keywords" content="潮港城, 太陽百匯, 台中美食, 潮港城餐券, 冷凍食品, 年菜宅配, 功夫菜, 台中餐廳" />
        
        <!-- Open Graph -->
        <meta property="og:locale" content="zh_TW" />
        <meta property="og:type" content="website" />
        <meta property="og:title" content="<?php echo esc_attr( $meta_title ); ?>" />
        <meta property="og:description" content="<?php echo esc_attr( $meta_desc ); ?>" />
        <meta property="og:url" content="<?php echo esc_url( home_url( '/' ) ); ?>" />
        <meta property="og:site_name" content="<?php echo esc_attr( $site_name ); ?>" />
        <meta property="og:image" content="<?php echo esc_url( $og_image ); ?>" />
        <meta property="og:image:width" content="1200" />
        <meta property="og:image:height" content="630" />
        <?php
    } elseif ( is_product() ) {
        global $post;
        $product = wc_get_product( $post->ID );
        if ( $product ) {
            $meta_desc = wp_strip_all_tags( $product->get_short_description() );
            if ( empty( $meta_desc ) ) {
                $meta_desc = wp_strip_all_tags( $product->get_description() );
            }
            $meta_desc = wp_html_excerpt( $meta_desc, 150, '...' );
            $meta_title = $product->get_name() . ' | 潮港城美食商城';
            $image_id = $product->get_image_id();
            $og_image = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : get_template_directory_uri() . '/assets/images/logo.png';
            ?>
            <meta name="description" content="<?php echo esc_attr( $meta_desc ); ?>" />
            
            <!-- Open Graph -->
            <meta property="og:locale" content="zh_TW" />
            <meta property="og:type" content="product" />
            <meta property="og:title" content="<?php echo esc_attr( $meta_title ); ?>" />
            <meta property="og:description" content="<?php echo esc_attr( $meta_desc ); ?>" />
            <meta property="og:url" content="<?php echo esc_url( get_permalink() ); ?>" />
            <meta property="og:site_name" content="潮港城美食商城" />
            <meta property="og:image" content="<?php echo esc_url( $og_image ); ?>" />
            <meta property="product:price:amount" content="<?php echo esc_attr( $product->get_price() ); ?>" />
            <meta property="product:price:currency" content="TWD" />
            <?php
        }
    } elseif ( is_product_category() ) {
        $term = get_queried_object();
        $meta_desc = $term->description ? wp_strip_all_tags( $term->description ) : $term->name . '系列商品線上訂購，名廚手藝低溫配送。';
        $meta_title = $term->name . '商品分類 | 潮港城美食商城';
        ?>
        <meta name="description" content="<?php echo esc_attr( $meta_desc ); ?>" />
        
        <!-- Open Graph -->
        <meta property="og:locale" content="zh_TW" />
        <meta property="og:type" content="website" />
        <meta property="og:title" content="<?php echo esc_attr( $meta_title ); ?>" />
        <meta property="og:description" content="<?php echo esc_attr( $meta_desc ); ?>" />
        <meta property="og:url" content="<?php echo esc_url( get_term_link( $term ) ); ?>" />
        <meta property="og:site_name" content="潮港城美食商城" />
        <?php
    }
}

/**
 * Inject Structured Data (JSON-LD) for Local Business (潮港城)
 */
add_action( 'wp_head', 'chao_gang_cheng_structured_data_local_business', 20 );
function chao_gang_cheng_structured_data_local_business() {
    if ( is_front_page() || is_home() ) {
        $logo_url = get_template_directory_uri() . '/assets/images/logo.png';
        $sd = array(
            '@context' => 'https://schema.org',
            '@type' => 'Restaurant',
            '@id' => home_url( '/' ) . '#restaurant',
            'name' => '潮港城國際美食館',
            'image' => array(
                $logo_url
            ),
            'url' => home_url( '/' ),
            'telephone' => '+886-4-2386-3322',
            'priceRange' => '$$',
            'address' => array(
                '@type' => 'PostalAddress',
                'streetAddress' => '環中路四段2號',
                'addressLocality' => '台中市南屯區',
                'addressRegion' => '台中市',
                'postalCode' => '408',
                'addressCountry' => 'TW'
            ),
            'geo' => array(
                '@type' => 'GeoCoordinates',
                'latitude' => 24.13524,
                'longitude' => 120.61528
            ),
            'openingHoursSpecification' => array(
                array(
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => array(
                        'Monday',
                        'Tuesday',
                        'Wednesday',
                        'Thursday',
                        'Friday',
                        'Saturday',
                        'Sunday'
                    ),
                    'opens' => '11:00',
                    'closes' => '21:00'
                )
            ),
            'sameAs' => array(
                'https://www.facebook.com/ckc.taichung/',
                'https://www.ckcchao.com/'
            ),
            'taxID' => '53301080'
        );
        
        echo "\n" . '<script type="application/ld+json">' . json_encode( $sd, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . '</script>' . "\n";
    }
}add_action( 'wp_footer', 'chao_gang_cheng_account_page_script' );
function chao_gang_cheng_account_page_script() {
    if ( is_account_page() ) {
        ?>
        <script>
            jQuery(document).ready(function($) {
                // Add placeholders to login fields
                $('#username').attr('placeholder', '請輸入使用者名稱、電子郵件或手機號碼');
                $('#password').attr('placeholder', '請輸入您的密碼');
                // Add placeholders to register fields if present
                $('#reg_email').attr('placeholder', '請輸入您的電子郵件地址');
                $('#reg_username').attr('placeholder', '請設定您的使用者名稱（可用於登入）');
                $('#reg_password').attr('placeholder', '請設定您的密碼');
                $('#reg_billing_first_name').attr('placeholder', '請輸入您的真實姓名');
                $('#reg_billing_phone').attr('placeholder', '請輸入行動電話，例：0912345678');
                // Update form titles
                $('#customer_login .u-column1 h2').text('會員登入');
                $('#customer_login .u-column2 h2').text('註冊新會員');
                
                // Remove edit avatar overlay elements completely from DOM
                $('.woocommerce-account-gravatar__edit-wrapper').remove();
                $(document).on('mouseenter', '.woocommerce-account-gravatar__avatar-wrapper', function() {
                    $(this).find('.woocommerce-account-gravatar__edit-wrapper').remove();
                });
                
                // Handle dynamic late-rendering by the plugin's JS
                var editCleanupCount = 0;
                var editCleanupInterval = setInterval(function() {
                    $('.woocommerce-account-gravatar__edit-wrapper').remove();
                    editCleanupCount++;
                    if (editCleanupCount > 15) {
                        clearInterval(editCleanupInterval);
                    }
                }, 200);
            });
        </script>
        <?php
    }
}

/**
 * Global Header Dropdown UX
 */
add_action( 'wp_footer', 'chao_gang_cheng_global_header_script' );
function chao_gang_cheng_global_header_script() {
    ?>
    <style>
        /* Force hide WooCommerce AJAX-appended "View Cart" links and elements globally */
        .added_to_cart {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            height: 0 !important;
            width: 0 !important;
            overflow: hidden !important;
            position: absolute !important;
            pointer-events: none !important;
        }

        /* WooCommerce Address Page Layout & Edit Buttons Beautification */
        .woocommerce-Addresses.col2-set {
            display: flex !important;
            flex-wrap: wrap !important;
            gap: 24px !important;
            justify-content: center !important;
            margin: 0 auto !important;
            width: 100% !important;
            max-width: 1000px !important;
        }

        .woocommerce-Addresses .col-1,
        .woocommerce-Addresses .col-2,
        .woocommerce-Address {
            float: none !important;
            width: 100% !important;
            flex: 1 1 350px !important;
            max-width: 480px !important;
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 12px !important;
            padding: 28px !important;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03) !important;
            box-sizing: border-box !important;
            transition: all 0.3s ease !important;
        }

        .woocommerce-Address:hover {
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06) !important;
            border-color: #cbd5e1 !important;
        }

        .woocommerce-Address-title {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            margin-bottom: 20px !important;
            border-bottom: 1px solid #f1f5f9 !important;
            padding-bottom: 16px !important;
        }
        
        .woocommerce-Address-title h3 {
            margin: 0 !important;
            font-size: 20px !important;
            font-weight: 700 !important;
            color: #1e293b !important;
            text-align: left !important;
        }
        
        .woocommerce-Address-title a.edit,
        .woocommerce-Addresses a.edit {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            background-color: #8c7e7e !important; /* Theme brown color */
            color: #ffffff !important;
            padding: 8px 18px !important;
            font-size: 13px !important;
            font-weight: 600 !important;
            text-decoration: none !important;
            border-radius: 20px !important;
            border: none !important;
            box-shadow: 0 2px 6px rgba(140, 126, 126, 0.15) !important;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
        }
        
        .woocommerce-Address-title a.edit:hover,
        .woocommerce-Addresses a.edit:hover {
            background-color: #7a6d6d !important;
            color: #ffffff !important;
            transform: translateY(-1.5px) !important;
            box-shadow: 0 4px 10px rgba(140, 126, 126, 0.25) !important;
        }
        
        .woocommerce-Address address {
            font-style: normal !important;
            line-height: 1.8 !important;
            color: #475569 !important;
            font-size: 15px !important;
        }

        /* 手機版優化與置中 */
        @media (max-width: 768px) {
            .woocommerce-Addresses.col2-set {
                padding: 0 15px !important; /* 確保左右有留白，不會貼邊 */
                flex-direction: column !important;
                align-items: center !important;
            }
            .woocommerce-Addresses .col-1,
            .woocommerce-Addresses .col-2,
            .woocommerce-Address {
                width: 100% !important;
                max-width: 100% !important;
                padding: 24px 16px !important;
            }
            .woocommerce-Address-title {
                flex-direction: column !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 12px !important;
            }
            .woocommerce-Address-title h3 {
                text-align: center !important;
            }
            .woocommerce-Address address {
                text-align: center !important;
            }
        }
    </style>
    <script>
        jQuery(document).ready(function($) {
            var dropdownTimer;
            var $menuWrappers = $('.account-menu-wrapper, .cart-menu-wrapper, .search-menu-wrapper');

            $menuWrappers.on('mouseenter', function() {
                clearTimeout(dropdownTimer);
                $menuWrappers.not(this).removeClass('is-active');
                $(this).addClass('is-active');
            }).on('mouseleave', function() {
                var $this = $(this);
                // Exclude search menu wrapper from closing on mouseleave to allow autocomplete interactions
                if ($this.hasClass('search-menu-wrapper')) {
                    return;
                }
                // 2. If mouse has moved into any search autocomplete suggestion popover elements, don't close
                if ($('[class*="search"]:hover, [class*="autocomplete"]:hover, [class*="suggestion"]:hover, [id*="search"]:hover, [id*="autocomplete"]:hover, [id*="suggestion"]:hover').length) {
                    return;
                }
                dropdownTimer = setTimeout(function() {
                    $this.removeClass('is-active');
                }, 300);
            });

            $(document).on('click', function(e) {
                var $target = $(e.target);
                // 1. If clicked inside the menu wrappers (search bar, cart menu, account menu), don't close
                if ($target.closest($menuWrappers).length) {
                    return;
                }
                // 2. If clicked inside any search suggestions, autocomplete popovers, or dropdown boxes enqueued outside the wrapper DOM, don't close
                if ($target.closest('[class*="search"], [class*="autocomplete"], [class*="suggestion"], [id*="search"], [id*="autocomplete"], [id*="suggestion"]').length) {
                    return;
                }
                // 3. If the search field is currently focused, don't close
                if ($('.search-field').is(':focus')) {
                    return;
                }
                // Otherwise, close the wrappers
                $menuWrappers.removeClass('is-active');
            });

            // ==============================================================
            // PRICE DECIMAL TRIMMER — strips any residual .00 from prices
            // ==============================================================
            function trimPriceDecimals() {
                $('.amount, .price, .product-price, .promo-price, .regular-price, del, ins, bdi, .woocommerce-Price-amount').each(function() {
                    var $this = $(this);
                    $this.contents().each(function() {
                        if (this.nodeType === 3 && this.nodeValue && this.nodeValue.indexOf('.00') !== -1) {
                            this.nodeValue = this.nodeValue.replace(/\.00/g, '');
                        }
                    });
                });
            }
            trimPriceDecimals();
            $(document.body).on('updated_checkout updated_cart_totals updated_addons post-load wc_fragments_refreshed', trimPriceDecimals);
            var trimInterval = setInterval(trimPriceDecimals, 400);
            setTimeout(function() { clearInterval(trimInterval); }, 8000);

            if (!$('body').hasClass('woocommerce-checkout') && !$('body').hasClass('woocommerce-cart')) {
                // ==============================================================
                // CART POPUP MODAL — central checkmark popup shown on add to cart
                // ==============================================================
                var popupShowing = false;

                function showCartPopup() {
                    if (window.ckc_is_buy_now) return;
                    if (popupShowing) return;
                    popupShowing = true;

                    // Remove any existing overlay first
                    $('.custom-cart-popup-overlay').remove();

                    var $overlay = $('<div class="custom-cart-popup-overlay"><div class="custom-cart-popup-card"><div class="custom-cart-popup-icon"><svg width="56" height="56" viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="28" cy="28" r="27" stroke="#5cb85c" stroke-width="2.5"/><path d="M17 28.5L24 35.5L39 20.5" stroke="#5cb85c" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg></div><div class="custom-cart-popup-text">已加入購物車</div></div></div>');
                    $('body').append($overlay);

                    setTimeout(function() { $overlay.addClass('is-visible'); }, 30);

                    setTimeout(function() {
                        $overlay.removeClass('is-visible');
                        setTimeout(function() {
                            $overlay.remove();
                            popupShowing = false;
                        }, 300);
                    }, 1800);
                }

                // ==============================================================
                // TRIGGER 1: WooCommerce native AJAX add_to_cart event (covers all
                // standard loop/archive/shop pages on both WEB and mobile)
                // ==============================================================
                $(document.body).on('added_to_cart', function() {
                    // Instantly remove WooCommerce default "View Cart" links
                    $('.added_to_cart').remove();
                    showCartPopup();
                });

                // ==============================================================
                // TRIGGER 2: Page-reload detection — if page loaded with a WC success
                // notice, show popup and hide the raw notice (single product fallback)
                // ==============================================================
                var $wcMsg = $('.woocommerce-message');
                if ($wcMsg.length > 0 && ($wcMsg.find('.wc-forward').length > 0 || $wcMsg.text().indexOf('已加入') !== -1 || $wcMsg.text().indexOf('加入您的購物車') !== -1 || $wcMsg.text().indexOf('has been added') !== -1)) {
                    $wcMsg.css('display', 'none');
                    setTimeout(showCartPopup, 200);
                }

                // ==============================================================
                // TRIGGER 3: Single Product page — AJAX intercept form submit
                // Uses WooCommerce's own wc-ajax endpoint to avoid page reload
                // ==============================================================
                $(document).on('click', '.single_add_to_cart_button', function(e) {
                    var $button = $(this);
                    var $form   = $button.closest('form.cart');

                    // Skip if no form, or if it's the checkout form
                    if (!$form.length) return;
                    if ($form.closest('.woocommerce-checkout').length || $form.hasClass('checkout')) return;
                    // Skip disabled/loading buttons
                    if ($button.is(':disabled') || $button.hasClass('disabled') || $button.hasClass('loading')) return;

                    e.preventDefault();
                    $button.addClass('loading').prop('disabled', true);

                    // Collect all form fields including variation selects
                    var formData = $form.serialize();

                    // Append the product id as add-to-cart if not already in form data
                    var productId = $button.val() || $form.find('input[name="add-to-cart"]').val() || $form.find('input[name="product_id"]').val() || '';
                    if (productId && formData.indexOf('add-to-cart=') === -1) {
                        formData += '&add-to-cart=' + encodeURIComponent(productId);
                    }

                    // POST to current page URL (WC handles add-to-cart via $_GET and $_POST)
                    $.ajax({
                        type: 'POST',
                        url: window.location.href,
                        data: formData,
                        success: function(response) {
                            $button.removeClass('loading').prop('disabled', false);

                            // If response contains errors (e.g. no variation selected), fall back to normal submit
                            var hasError = false;
                            if (typeof response === 'string') {
                                if (response.indexOf('class="woocommerce-' + 'error"') !== -1 || 
                                    response.indexOf("class='woocommerce-" + "error'") !== -1) {
                                    hasError = true;
                                }
                            }
                            if (hasError) {
                                $form[0].submit();
                                return;
                            }

                            // Success — refresh cart fragments and trigger popup
                            $('.added_to_cart').remove();

                            // Parse response to instantly update cart badge, dropdown & sticky badge (no-delay refresh)
                            try {
                                var $parsed = $('<div>').append($.parseHTML(response));
                                var newCartCount = $parsed.find('.cart-count').first().text();
                                var $newCartDropdown = $parsed.find('.cart-dropdown');
                                var $newStickyCartBadge = $parsed.find('.cart-badge-count');
                                
                                if (newCartCount !== '') {
                                    $('.cart-count').text(newCartCount);
                                }
                                if ($newCartDropdown.length) {
                                    $('.cart-dropdown').html($newCartDropdown.html());
                                }
                                if ($newStickyCartBadge.length) {
                                    var badgeText = $newStickyCartBadge.first().text();
                                    $('.cart-badge-count').text(badgeText);
                                    if ($newStickyCartBadge.first().hasClass('badge-empty') || badgeText === '0' || badgeText === '') {
                                        $('.cart-badge-count').addClass('badge-empty');
                                    } else {
                                        $('.cart-badge-count').removeClass('badge-empty');
                                    }
                                }
                            } catch (err) {
                                console.error('Error updating cart fragments instantly:', err);
                            }

                            $(document.body).trigger('wc_fragment_refresh');
                            showCartPopup();
                        },
                        error: function() {
                            $button.removeClass('loading').prop('disabled', false);
                            // Network/server error — fallback to normal submit
                            $form[0].submit();
                        }
                    });
                });

                // ==============================================================
                // TRIGGER 4: Front-page product grid buttons (.add-to-cart-btn)
                // These already have ajax_add_to_cart class but we add a safety net
                // listener in case the WC AJAX handler doesn't fire properly
                // ==============================================================
                $(document).on('click', '.product-card .add-to-cart-btn, .product-card a.ajax_add_to_cart', function(e) {
                    // Let WooCommerce handle the AJAX, we just ensure popup fires
                    // The added_to_cart event from WC will trigger showCartPopup via TRIGGER 1
                    // This is just a safety net — nothing extra needed here
                });
            }


        });
    </script>
    <?php
}

/**
 * Separate My Account Login and Registration Forms
 */
add_filter('body_class', 'chao_gang_cheng_myaccount_body_class');
function chao_gang_cheng_myaccount_body_class($classes) {
    if ( is_account_page() && ! is_user_logged_in() ) {
        if ( isset( $_POST['register'] ) || ( isset( $_GET['action'] ) && $_GET['action'] === 'register' ) ) {
            $classes[] = 'show-register-form';
        } else {
            $classes[] = 'show-login-form';
        }
    }
    return $classes;
}

/**
 * Login page: translate the Points & Rewards English signup notice
 * ("You will get X points for a successful signup.") into Traditional Chinese.
 */
add_filter( 'gettext', 'chao_gang_cheng_translate_points_signup_message', 20, 3 );
function chao_gang_cheng_translate_points_signup_message( $translated, $text, $domain ) {
    if ( false !== strpos( $text, 'points for a successful signup' ) ) {
        $translated = str_replace(
            array( 'You will get ', ' points for a successful signup.' ),
            array( '註冊成功即可獲得 ', ' 點紅利點數（1 點可折抵 NT$1）！' ),
            $translated
        );
    }
    return $translated;
}

// JS fallback for the same notice when the plugin echoes the string directly (bypassing gettext)
add_action( 'wp_footer', 'chao_gang_cheng_points_signup_message_js_fallback' );
function chao_gang_cheng_points_signup_message_js_fallback() {
    if ( ! is_account_page() || is_user_logged_in() ) {
        return;
    }
    ?>
    <script>
    jQuery(function($) {
        $('.woocommerce-info, .woocommerce-message, .woocommerce p, .woocommerce div').each(function() {
            if (this.childElementCount === 0 && /points for a successful signup/i.test($(this).text())) {
                var m = $(this).text().match(/(\d+)/);
                var pts = m ? m[1] : '5';
                $(this).text('註冊成功即可獲得 ' + pts + ' 點紅利點數（1 點可折抵 NT$1）！');
            }
        });
    });
    </script>
    <?php
}

/**
 * Register form: membership benefits box at the top, so the value of signing
 * up is explicit before the user starts typing.
 */
add_action( 'woocommerce_register_form_start', 'chao_gang_cheng_register_benefits_box' );
function chao_gang_cheng_register_benefits_box() {
    $signup = (int) get_option( '_ckc_ref_signup_bonus', 0 );
    
    // 取得點數兌換比例
    $wps = get_option('wps_wpr_settings_gallery', array());
    $redeem_pts = (int) get_option('_ckc_redeem_pts', $wps['wps_wpr_cart_points_rate'] ?? 1);
    $redeem_val = (int) get_option('_ckc_redeem_val', $wps['wps_wpr_cart_price_rate']  ?? 1);
    $rate_text = sprintf( '%d 點可折抵 NT$%d', $redeem_pts, $redeem_val );
    ?>
    <div style="background: #fdfaf7; border: 1px solid #f5ebe6; border-radius: 8px; padding: 14px 16px; margin-bottom: 20px;">
        <div style="font-size: 13px; font-weight: 700; color: #7f6c60; margin-bottom: 8px;">加入會員專屬好處</div>
        <ul style="margin: 0; padding: 0; list-style: none; font-size: 13px; color: #6b7280; line-height: 2;">
            <?php if ( $signup > 0 ) : ?>
                <li style="color: #b91c1c; font-weight: 700;">註冊即贈 <?php echo $signup; ?> 點紅利點數！</li>
            <?php endif; ?>
            <li>🪙 紅利點數回饋，<?php echo $rate_text; ?></li>
            <li>💰 消費享 1% 現金回饋</li>
            <li>訂單查詢、收藏清單、下次結帳免填資料</li>
        </ul>
    </div>
    <?php
}

add_action( 'woocommerce_login_form_end', 'chao_gang_cheng_add_register_link' );
function chao_gang_cheng_add_register_link() {
    echo '<div style="text-align: center; margin-top: 20px; border-top: 1px solid var(--border-color); padding-top: 15px;">';
    echo '<a href="' . esc_url( add_query_arg( 'action', 'register', wc_get_page_permalink( 'myaccount' ) ) ) . '" style="color: var(--primary-color); font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">';
    echo '<svg width="18" height="18" fill="currentColor" viewBox="0 0 16 16"><path d="M12.5 16a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm.5-5v1h1a.5.5 0 0 1 0 1h-1v1a.5.5 0 0 1-1 0v-1h-1a.5.5 0 0 1 0-1h1v-1a.5.5 0 0 1 1 0Zm-2-6a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path d="M2 13c0 1 1 1 1 1h5.256A4.493 4.493 0 0 1 8 12.5a4.49 4.49 0 0 1 1.544-3.393C9.077 9.038 8.564 9 8 9c-5 0-6 3-6 4Z"/></svg>';
    echo '註冊新會員</a>';
    echo '</div>';
}

add_action( 'woocommerce_register_form_end', 'chao_gang_cheng_add_login_link' );
function chao_gang_cheng_add_login_link() {
    echo '<div style="text-align: center; margin-top: 20px; border-top: 1px solid var(--border-color); padding-top: 15px;">';
    echo '<a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '" style="color: var(--primary-color); font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">';
    echo '<svg width="18" height="18" fill="currentColor" viewBox="0 0 16 16"><path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/><path fill-rule="evenodd" d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8zm8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1z"/></svg>';
    echo '已有帳號？返回登入</a>';
    echo '</div>';
}

/**
 * Register Shop Sidebar widget area.
 */
function chao_gang_cheng_widgets_init() {
    register_sidebar( array(
        'name'          => esc_html__( '商店側邊欄', 'chao-gang-cheng' ),
        'id'            => 'shop-sidebar',
        'description'   => esc_html__( '此處的元件將顯示於商店與分類頁面的左側側邊欄。', 'chao-gang-cheng' ),
        'before_widget' => '<section id="%1$s" class="widget %2$s" style="margin-bottom: 30px;">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3 class="widget-title" style="font-size: 16px; font-weight: bold; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">',
        'after_title'   => '</h3>',
    ) );
}
add_action( 'widgets_init', 'chao_gang_cheng_widgets_init' );

/**
 * Fallback menu for the shop sidebar if the footer menu is empty.
 * Displays WooCommerce product categories list.
 */
function chao_gang_cheng_sidebar_fallback_menu() {
    $args = array(
        'taxonomy'   => 'product_cat',
        'title_li'   => '',
        'hide_empty' => true,
    );
    echo '<ul class="shop-sidebar-menu">';
    wp_list_categories( $args );
    echo '</ul>';
}

/**
 * Configure related products count and columns.
 */
add_filter( 'woocommerce_output_related_products_args', 'chao_gang_cheng_related_products_args', 20 );
function chao_gang_cheng_related_products_args( $args ) {
    $args['posts_per_page'] = 4;
    $args['columns']        = 4;
    return $args;
}

/**
 * 將後台「頻道連結」欄位（可能是 /channel/UCxxx、/user/name、/@handle、/c/Name
 * 等各種格式）解析成 YouTube RSS feed 實際可用的參數。
 *
 * RSS feed（https://www.youtube.com/feeds/videos.xml）本身只接受 channel_id
 * 或 user 兩種參數，不支援 @handle／自訂網址，所以遇到 handle／自訂網址時，
 * 需要先抓一次頻道頁面 HTML，從裡面解析出真正的 channel ID，並用 transient
 * 快取 7 天（頻道 ID 幾乎不會變動，沒必要每次都重新請求）。
 *
 * @param string $channel_url 後台「頻道連結」欄位的原始網址
 * @return string 成功時回傳 'UCxxxxxxxx...'（channel_id）或 'user:帳號名'（舊式 user 格式）；解析失敗回傳空字串
 */
function chao_gang_cheng_resolve_youtube_channel_id( $channel_url ) {
    $channel_url = trim( (string) $channel_url );
    if ( '' === $channel_url ) {
        return '';
    }

    // 1) /channel/UCxxxx 格式：ID 已經在網址裡，不需要額外請求
    if ( preg_match( '#youtube\.com/channel/(UC[0-9A-Za-z_-]{10,})#i', $channel_url, $m ) ) {
        return $m[1];
    }

    // 2) 舊式 /user/username 格式：RSS feed 原生支援 user 參數，直接標記回傳
    if ( preg_match( '#youtube\.com/user/([^/?#]+)#i', $channel_url, $m ) ) {
        return 'user:' . $m[1];
    }

    // 3) /@handle 或 /c/CustomName：抓頻道頁面 HTML 解析出真正的 channel ID
    $cache_key = 'ckc_yt_chid_' . md5( $channel_url );
    $cached    = get_transient( $cache_key );
    if ( false !== $cached ) {
        return $cached;
    }

    $response = wp_remote_get( $channel_url, array( 'timeout' => 8 ) );
    if ( is_wp_error( $response ) ) {
        return '';
    }
    $body = wp_remote_retrieve_body( $response );
    if ( empty( $body ) ) {
        return '';
    }

    $resolved = '';
    if ( preg_match( '#"channelId":"(UC[0-9A-Za-z_-]{10,})"#', $body, $m ) ) {
        $resolved = $m[1];
    } elseif ( preg_match( '#<link rel="canonical" href="https://www\.youtube\.com/channel/(UC[0-9A-Za-z_-]{10,})"#', $body, $m ) ) {
        $resolved = $m[1];
    }

    // 只快取「成功解析」的結果；失敗時不快取，讓下次還有機會重新嘗試
    // （例如頻道頁面暫時打不開），避免長期卡在空結果。
    if ( '' !== $resolved ) {
        set_transient( $cache_key, $resolved, 7 * DAY_IN_SECONDS );
    }

    return $resolved;
}

/**
 * Fetch and cache latest YouTube videos from a specific channel using RSS feed
 *
 * @param string $channel_url 頻道連結（來自首頁模塊「頻道連結」欄位）；留空則使用預設頻道
 */
function chao_gang_cheng_get_youtube_videos( $channel_url = '' ) {
    if ( '' === trim( (string) $channel_url ) ) {
        $channel_url = 'https://www.youtube.com/@ckcgroup'; // 保底預設頻道
    }

    $transient_key = 'chao_gang_cheng_youtube_feed_' . md5( $channel_url );
    $videos = get_transient( $transient_key );

    if ( false === $videos ) {
        $resolved = chao_gang_cheng_resolve_youtube_channel_id( $channel_url );
        if ( '' === $resolved ) {
            return array(); // 解析失敗，交由呼叫端顯示保底假資料卡片
        }

        if ( 0 === strpos( $resolved, 'user:' ) ) {
            $feed_url = 'https://www.youtube.com/feeds/videos.xml?user=' . rawurlencode( substr( $resolved, 5 ) );
        } else {
            $feed_url = 'https://www.youtube.com/feeds/videos.xml?channel_id=' . rawurlencode( $resolved );
        }

        $response = wp_remote_get( $feed_url );
        
        if ( is_wp_error( $response ) ) {
            return array();
        }
        
        $body = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) {
            return array();
        }
        
        // Disable XML external entity loading for security
        if ( function_exists( 'libxml_disable_entity_loader' ) ) {
            $libxml_previous_state = libxml_disable_entity_loader( true );
        }
        
        $xml = simplexml_load_string( $body );
        
        if ( function_exists( 'libxml_disable_entity_loader' ) && isset( $libxml_previous_state ) ) {
            libxml_disable_entity_loader( $libxml_previous_state );
        }
        
        if ( ! $xml ) {
            return array();
        }
        
        $videos = array();
        $count = 0;
        foreach ( $xml->entry as $entry ) {
            if ( $count >= 4 ) { // Fetch latest 4 videos
                break;
            }
            
            // Extract Video ID
            $yt_id = '';
            if ( isset( $entry->children( 'yt', true )->videoId ) ) {
                $yt_id = (string) $entry->children( 'yt', true )->videoId;
            }
            if ( empty( $yt_id ) ) {
                $yt_id = str_replace( 'yt:video:', '', (string) $entry->id );
            }
            
            $thumbnail = '';
            $media_group = $entry->children( 'media', true )->group;
            if ( $media_group && isset( $media_group->thumbnail ) ) {
                $thumbnail = (string) $media_group->thumbnail->attributes()->url;
            }
            if ( empty( $thumbnail ) ) {
                $thumbnail = 'https://img.youtube.com/vi/' . $yt_id . '/hqdefault.jpg';
            }
            
            $videos[] = array(
                'id'        => (string) $yt_id,
                'title'     => (string) $entry->title,
                'link'      => (string) $entry->link->attributes()->href,
                'thumbnail' => $thumbnail,
            );
            $count++;
        }
        
        // Cache feed for 4 hours
        set_transient( $transient_key, $videos, 4 * HOUR_IN_SECONDS );
    }
    
    return $videos;
}

/**
 * AJAX Handler to toggle product wishlist/favorites for logged-in user
 */
add_action( 'wp_ajax_toggle_wishlist', 'chao_gang_cheng_toggle_wishlist_handler' );
add_action( 'wp_ajax_nopriv_toggle_wishlist', 'chao_gang_cheng_toggle_wishlist_handler' );
function chao_gang_cheng_toggle_wishlist_handler() {
    $product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
    if ( ! $product_id ) {
        wp_send_json_error( 'Invalid product ID' );
    }
    
    if ( is_user_logged_in() ) {
        $user_id = get_current_user_id();
        $favorites = get_user_meta( $user_id, '_ckc_favorites', true );
        if ( ! is_array( $favorites ) ) {
            $favorites = array();
        }
        
        if ( in_array( $product_id, $favorites ) ) {
            $favorites = array_diff( $favorites, array( $product_id ) );
            $status = 'removed';
        } else {
            $favorites[] = $product_id;
            $status = 'added';
        }
        update_user_meta( $user_id, '_ckc_favorites', $favorites );
        wp_send_json_success( array( 'status' => $status, 'logged_in' => true ) );
    } else {
        wp_send_json_success( array( 'status' => 'local_only', 'logged_in' => false ) );
    }
}

/**
 * Rename Downloads Tab to "收藏清單" in My Account
 */
add_filter( 'woocommerce_account_menu_items', 'chao_gang_cheng_custom_my_account_menu_items' );
function chao_gang_cheng_custom_my_account_menu_items( $items ) {
    if ( isset( $items['downloads'] ) ) {
        $items['downloads'] = '收藏清單';
    }
    if ( isset( $items['backinstock'] ) ) {
        unset( $items['backinstock'] );
    }
    // 隱藏禮物卡頁籤（功能保留但不顯示於導覽列）
    // 涵蓋各禮物卡外掛的不同 menu key
    foreach ( array( 'gift-card', 'gift_card', 'giftcards', 'woo-gift-cards', 'yith-gift-cards' ) as $_gift_key ) {
        unset( $items[ $_gift_key ] );
    }
    
    // Set Points menu item to "紅利點數"
    if ( isset( $items['points'] ) ) {
        $items['points'] = '紅利點數';
    } else {
        $items['points'] = '紅利點數';
    }
    
    // 登出在此面板隱藏不出現
    if ( isset( $items['customer-logout'] ) ) {
        unset( $items['customer-logout'] );
    }
    return $items;
}


/**
 * My Account dashboard overview: points balance, recent orders and quick links
 * (replaces the bare default two-line dashboard text with useful content).
 */
add_action( 'woocommerce_account_dashboard', 'chao_gang_cheng_account_dashboard_overview' );
function chao_gang_cheng_account_dashboard_overview() {
    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return;
    }

    $points = ckc_pts_get_user_balance( $user_id );
    
    // 取得點數兌換比例
    $wps        = get_option('wps_wpr_settings_gallery', array());
    $redeem_pts = (int) get_option('_ckc_redeem_pts', $wps['wps_wpr_cart_points_rate'] ?? 1);
    $redeem_val = (int) get_option('_ckc_redeem_val', $wps['wps_wpr_cart_price_rate']  ?? 1);
    $rate_text  = sprintf( '每 %d 點可折抵 NT$%d', $redeem_pts, $redeem_val );

    $recent_orders = wc_get_orders( array(
        'customer_id' => $user_id,
        'limit'       => 3,
        'orderby'     => 'date',
        'order'       => 'DESC',
    ) );

    $card_style  = 'background: #fffaf1; border: 1px solid #e2d2b3; border-radius: 14px; padding: 20px; box-shadow: 0 2px 8px rgba(26,20,15,0.05);';
    $title_style = 'font-size: 13px; font-weight: 700; color: #8c7a64; margin: 0 0 12px; letter-spacing: 0.08em; text-transform: uppercase;';
    ?>
    <style>
    .chao-account-overview { display: grid; grid-template-columns: 1fr 2fr; gap: 16px; margin: 20px 0; }
    @media (max-width: 768px) { .chao-account-overview { grid-template-columns: 1fr; } }
    .chao-account-quicklinks { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 10px; }
    .chao-account-quicklinks a {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 9px 18px; border: 1px solid #e2d2b3; border-radius: 20px;
        background: #fffaf1; color: #3a2f24; font-size: 13px; font-weight: 600; text-decoration: none;
        transition: border-color .2s ease, color .2s ease, background .2s ease;
    }
    .chao-account-quicklinks a:hover { border-color: #f86f69; color: #f86f69; background: #fff5f4; }
    .chao-account-order-row { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px dashed #e2d2b3; font-size: 14px; }
    .chao-account-order-row:last-child { border-bottom: none; }
    .chao-order-status { font-size: 12px; padding: 3px 10px; border-radius: 12px; background: #f2e9d8; color: #3a2f24; white-space: nowrap; }
    </style>

    <div class="chao-account-overview">
        <div style="<?php echo esc_attr( $card_style ); ?>">
            <p style="<?php echo esc_attr( $title_style ); ?>; display: flex; align-items: center; gap: 6px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13"/><path d="M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5A4.8 8 0 0 1 12 8a4.8 8 0 0 1 4.5-5 2.5 2.5 0 0 1 0 5"/></svg>
                紅利點數
            </p>
            <div style="font-size: 32px; font-weight: 700; color: #c9974a; line-height: 1.2; font-family: Georgia, 'Times New Roman', 'Songti TC', 'PMingLiU', serif;"><?php echo esc_html( number_format( $points ) ); ?> <span style="font-size: 14px; color: #8c7a64; font-family: -apple-system, BlinkMacSystemFont, 'Noto Sans TC', 'PingFang TC', Arial, sans-serif;">點</span></div>
            <p style="font-size: 12px; color: #8c7a64; margin: 8px 0 14px;"><?php echo esc_html( $rate_text ); ?>，結帳時直接折抵</p>
            <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'points' ) ); ?>" style="font-size: 13px; color: #f86f69; font-weight: 600; text-decoration: underline;">查看點數紀錄 →</a>
        </div>

        <div style="<?php echo esc_attr( $card_style ); ?>">
            <p style="<?php echo esc_attr( $title_style ); ?>; display: flex; align-items: center; gap: 6px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                近期訂單
            </p>
            <?php if ( ! empty( $recent_orders ) ) : ?>
                <?php foreach ( $recent_orders as $order ) : ?>
                    <div class="chao-account-order-row">
                        <a href="<?php echo esc_url( $order->get_view_order_url() ); ?>" style="color: #1a140f; font-weight: 600; text-decoration: none;">#<?php echo esc_html( $order->get_order_number() ); ?></a>
                        <span style="color: #8c7a64; font-size: 13px;"><?php echo esc_html( $order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y/m/d' ) : '' ); ?></span>
                        <span class="chao-order-status"><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></span>
                        <span style="font-weight: 700; color: #1a140f;"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span>
                    </div>
                <?php endforeach; ?>
                <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>" style="display: inline-block; margin-top: 12px; font-size: 13px; color: #f86f69; font-weight: 600; text-decoration: underline;">查看全部訂單 →</a>
            <?php else : ?>
                <p style="font-size: 14px; color: #8c7a64; margin: 0 0 12px;">還沒有任何訂單，來看看主廚為您準備了什麼吧！</p>
                <a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" style="display: inline-block; background-color: #f86f69; color: #fff; border-radius: 20px; padding: 8px 24px; text-decoration: none; font-size: 13px; font-weight: 600;">前往商店選購</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="chao-account-quicklinks">
        <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
            我的訂單
        </a>
        <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'downloads' ) ); ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
            收藏清單
        </a>
        <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-address' ) ); ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            收件地址
        </a>
        <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-account' ) ); ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
            帳戶資料
        </a>
        <a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
            繼續購物
        </a>
    </div>
    <?php
}

/**
 * Change Downloads Page Title to "收藏清單"
 */
add_filter( 'woocommerce_endpoint_downloads_title', 'chao_gang_cheng_downloads_endpoint_title' );
function chao_gang_cheng_downloads_endpoint_title( $title ) {
    return '收藏清單';
}

add_filter( 'woocommerce_endpoint_points_title', 'chao_gang_cheng_points_endpoint_title' );
function chao_gang_cheng_points_endpoint_title( $title ) {
    return '紅利點數';
}

/**
 * Render Favorites List inside the Downloads tab content
 */
remove_action( 'woocommerce_account_downloads_endpoint', 'woocommerce_account_downloads' );
add_action( 'woocommerce_account_downloads_endpoint', 'chao_gang_cheng_account_wishlist_content' );
function chao_gang_cheng_account_wishlist_content() {
    $user_id = get_current_user_id();
    $favorites = get_user_meta( $user_id, '_ckc_favorites', true );
    
    if ( ! is_array( $favorites ) || empty( $favorites ) ) {
        ?>
        <div class="woocommerce-MyAccount-empty-wishlist" style="text-align: center; padding: 40px 20px; background: white; border: 1px solid #e4e7eb; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 20px;">
            <div style="color: #cbd5e1; margin-bottom: 15px;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto;"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
            </div>
            <h3 style="font-size: 18px; color: #1f2937; margin-top: 0; margin-bottom: 8px; font-weight: 600;">您的收藏清單是空的</h3>
            <p style="font-size: 14px; color: #6b7280; margin-bottom: 20px; max-width: 320px; margin-left: auto; margin-right: auto; line-height: 1.5;">看到心儀的商品時，點選「加入最愛」即可將它們儲存在這裡。</p>
            <a class="button" href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>" style="display: inline-block; background-color: var(--secondary-color, #7f6c60); color: white; border-radius: 20px; padding: 10px 30px; text-decoration: none; font-size: 14px; font-weight: 600; border: none; width: auto !important; max-width: 200px; margin: 0 auto;">
                前往商店選購
            </a>
        </div>
        <?php
        return;
    }
    
    // Output Favorites product grid
    $args = array(
        'post_type'      => 'product',
        'post__in'       => $favorites,
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    );
    $favorites_query = new WP_Query( $args );
    
    if ( $favorites_query->have_posts() ) {
        echo '<div class="woocommerce columns-3" style="margin-top: 20px;">';
        woocommerce_product_loop_start();
        while ( $favorites_query->have_posts() ) {
            $favorites_query->the_post();
            wc_get_template_part( 'content', 'product' );
        }
        woocommerce_product_loop_end();
        echo '</div>';
        wp_reset_postdata();
    } else {
        ?>
        <div class="woocommerce-MyAccount-empty-wishlist" style="text-align: center; padding: 40px 20px; background: white; border: 1px solid #e4e7eb; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 20px;">
            <div style="color: #cbd5e1; margin-bottom: 15px;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto;"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
            </div>
            <h3 style="font-size: 18px; color: #1f2937; margin-top: 0; margin-bottom: 8px; font-weight: 600;">您的收藏清單是空的</h3>
            <p style="font-size: 14px; color: #6b7280; margin-bottom: 20px; max-width: 320px; margin-left: auto; margin-right: auto; line-height: 1.5;">看到心儀的商品時，點選「加入最愛」即可將它們儲存在這裡。</p>
            <a class="button" href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>" style="display: inline-block; background-color: var(--secondary-color, #7f6c60); color: white; border-radius: 20px; padding: 10px 30px; text-decoration: none; font-size: 14px; font-weight: 600; border: none; width: auto !important; max-width: 200px; margin: 0 auto;">
                前往商店選購
            </a>
        </div>
        <?php
    }
}

/**
 * ============================================================================
 * WooCommerce Checkout Customizations: Defaults & Translations
 * ============================================================================
 */

// 1. Default "Ship to different address" (運送到不同的地址？) to unchecked (默認為不打勾)
add_filter( 'woocommerce_ship_to_different_address_checked', '__return_false' );

// 2. Translate newsletter opt-in checkbox text to Chinese (修改為中文顯示)
add_filter( 'gettext', 'chao_gang_cheng_translate_newsletter_opt_in', 20, 3 );
function chao_gang_cheng_translate_newsletter_opt_in( $translated_text, $text, $domain ) {
    if ( $text === 'I would like to receive exclusive emails with discounts and product information' ) {
        return '『我同意接收商家發送的電子報及行銷訊息』';
    }
    return $translated_text;
}

// 3. Translate "Shipment" (貨件) to "運費" (Shipping) in totals row
add_filter( 'gettext_with_context', 'chao_gang_cheng_translate_shipment_context', 20, 4 );
function chao_gang_cheng_translate_shipment_context( $translated_text, $text, $context, $domain ) {
    if ( $domain === 'woocommerce' && $context === 'shipping packages' ) {
        if ( $text === 'Shipment' ) {
            return '運費';
        }
        if ( $text === 'Shipment %d' ) {
            return '運費 %d';
        }
    }
    return $translated_text;
}

add_filter( 'gettext', 'chao_gang_cheng_translate_shipment_general', 20, 3 );
function chao_gang_cheng_translate_shipment_general( $translated_text, $text, $domain ) {
    if ( $domain === 'woocommerce' ) {
        if ( $text === 'Shipment' ) {
            return '運費';
        }
        if ( $text === 'Shipment %d' ) {
            return '運費 %d';
        }
    }
    return $translated_text;
}

// 4. Remove "(optional)" or "(選填)" suffix from all checkout and edit address fields
add_filter( 'woocommerce_form_field', 'chao_gang_cheng_remove_checkout_optional_suffix', 20, 4 );
function chao_gang_cheng_remove_checkout_optional_suffix( $field, $key, $args, $value ) {
    if ( is_checkout() || is_wc_endpoint_url( 'edit-address' ) ) {
        $optional_en = '&nbsp;<span class="optional">(' . esc_html__( 'optional', 'woocommerce' ) . ')</span>';
        $optional_tw = '&nbsp;<span class="optional">(選填)</span>';
        $field = str_replace( $optional_en, '', $field );
        $field = str_replace( $optional_tw, '', $field );
    }
    return $field;
}

// 5. Hide shipping calculations and details on the Cart page (only show on Checkout page)
add_filter( 'woocommerce_cart_needs_shipping', 'chao_gang_cheng_hide_shipping_on_cart' );
function chao_gang_cheng_hide_shipping_on_cart( $needs_shipping ) {
    if ( is_cart() ) {
        return false;
    }
    return $needs_shipping;
}

// 6. Force customer country to Taiwan (TW) to ensure Taiwan address fields are always loaded
add_filter( 'woocommerce_customer_get_billing_country', 'chao_gang_cheng_force_tw_country', 999 );
add_filter( 'woocommerce_customer_get_shipping_country', 'chao_gang_cheng_force_tw_country', 999 );
function chao_gang_cheng_force_tw_country( $country ) {
    return 'TW';
}

add_filter( 'default_checkout_billing_country', 'chao_gang_cheng_default_checkout_country', 999 );
add_filter( 'default_checkout_shipping_country', 'chao_gang_cheng_default_checkout_country', 999 );
function chao_gang_cheng_default_checkout_country() {
    return 'TW';
}

// 7. Customize My Account Edit Address page fields to match checkout page customizations
add_filter( 'woocommerce_address_to_edit', 'chao_gang_cheng_custom_address_to_edit_fields', 20, 2 );
function chao_gang_cheng_custom_address_to_edit_fields( $fields, $load_address ) {
    if ( $load_address === 'billing' ) {
        unset( $fields['billing_last_name'] );
        if ( isset( $fields['billing_first_name'] ) ) {
            $fields['billing_first_name']['label'] = '姓名';
            $fields['billing_first_name']['placeholder'] = '請輸入完整姓名';
            $fields['billing_first_name']['class'] = array( 'form-row-wide' );
        }
        if ( isset( $fields['billing_phone'] ) ) {
            $fields['billing_phone']['label'] = '聯絡電話';
            $fields['billing_phone']['placeholder'] = '請輸入電話，宅配人員將以此電話聯繫';
        }
        if ( isset( $fields['billing_email'] ) ) {
            $fields['billing_email']['label'] = '電子郵件';
            $fields['billing_email']['placeholder'] = '請輸入電子郵件，例：example@gmail.com';
        }
        if ( isset( $fields['billing_country'] ) ) {
            $fields['billing_country']['type'] = 'hidden';
            $fields['billing_country']['default'] = 'TW';
            $fields['billing_country']['value'] = 'TW';
        }
        if ( isset( $fields['billing_state'] ) ) {
            $fields['billing_state']['label'] = '縣市';
            $fields['billing_state']['placeholder'] = '請選擇縣市';
            $fields['billing_state']['class'] = array( 'form-row-wide' );
        }
        if ( isset( $fields['billing_city'] ) ) {
            $fields['billing_city']['label'] = '鄉鎮市區';
            $fields['billing_city']['placeholder'] = '請輸入鄉鎮市區';
            $fields['billing_city']['class'] = array( 'form-row-wide' );
        }
        if ( isset( $fields['billing_address_1'] ) ) {
            $fields['billing_address_1']['label'] = '詳細地址';
            $fields['billing_address_1']['placeholder'] = '請輸入詳細路街、巷弄、門牌與樓層';
            $fields['billing_address_1']['class'] = array( 'form-row-wide' );
        }
        if ( isset( $fields['billing_postcode'] ) ) {
            $fields['billing_postcode']['label'] = '郵遞區號';
            $fields['billing_postcode']['placeholder'] = '郵遞區號';
            $fields['billing_postcode']['class'] = array( 'form-row-wide' );
        }
        unset( $fields['billing_address_2'] );

        // Sort billing fields (Name/Phone/County/Town/Address/Postcode)
        $billing_order = array(
            'billing_first_name',
            'billing_phone',
            'billing_email',
            'billing_country',
            'billing_state',
            'billing_city',
            'billing_address_1',
            'billing_postcode',
            'billing_company'
        );
        $sorted_fields = array();
        foreach ( $billing_order as $key ) {
            if ( isset( $fields[$key] ) ) {
                $sorted_fields[$key] = $fields[$key];
            }
        }
        foreach ( $fields as $key => $val ) {
            if ( ! isset( $sorted_fields[$key] ) ) {
                $sorted_fields[$key] = $val;
            }
        }
        $fields = $sorted_fields;
    }
    
    if ( $load_address === 'shipping' ) {
        unset( $fields['shipping_last_name'] );
        if ( isset( $fields['shipping_first_name'] ) ) {
            $fields['shipping_first_name']['label'] = '姓名';
            $fields['shipping_first_name']['placeholder'] = '請輸入完整姓名';
            $fields['shipping_first_name']['class'] = array( 'form-row-wide' );
        }
        if ( isset( $fields['shipping_phone'] ) ) {
            $fields['shipping_phone']['label'] = '聯絡電話';
            $fields['shipping_phone']['placeholder'] = '請輸入電話，宅配人員將以此電話聯繫';
            $fields['shipping_phone']['class'] = array( 'form-row-wide' );
        }
        if ( isset( $fields['shipping_country'] ) ) {
            $fields['shipping_country']['type'] = 'hidden';
            $fields['shipping_country']['default'] = 'TW';
            $fields['shipping_country']['value'] = 'TW';
        }
        if ( isset( $fields['shipping_state'] ) ) {
            $fields['shipping_state']['label'] = '縣市';
            $fields['shipping_state']['placeholder'] = '請選擇縣市';
            $fields['shipping_state']['class'] = array( 'form-row-wide' );
        }
        if ( isset( $fields['shipping_city'] ) ) {
            $fields['shipping_city']['label'] = '鄉鎮市區';
            $fields['shipping_city']['placeholder'] = '請輸入鄉鎮市區';
            $fields['shipping_city']['class'] = array( 'form-row-wide' );
        }
        if ( isset( $fields['shipping_address_1'] ) ) {
            $fields['shipping_address_1']['label'] = '詳細地址';
            $fields['shipping_address_1']['placeholder'] = '請輸入詳細路街、巷弄、門牌與樓層';
            $fields['shipping_address_1']['class'] = array( 'form-row-wide' );
        }
        if ( isset( $fields['shipping_postcode'] ) ) {
            $fields['shipping_postcode']['label'] = '郵遞區號';
            $fields['shipping_postcode']['placeholder'] = '郵遞區號';
            $fields['shipping_postcode']['class'] = array( 'form-row-wide' );
        }
        unset( $fields['shipping_address_2'] );

        // Sort shipping fields (Name/Phone/County/Town/Address/Postcode)
        $shipping_order = array(
            'shipping_first_name',
            'shipping_phone',
            'shipping_country',
            'shipping_state',
            'shipping_city',
            'shipping_address_1',
            'shipping_postcode',
            'shipping_company'
        );
        $sorted_fields = array();
        foreach ( $shipping_order as $key ) {
            if ( isset( $fields[$key] ) ) {
                $sorted_fields[$key] = $fields[$key];
            }
        }
        foreach ( $fields as $key => $val ) {
            if ( ! isset( $sorted_fields[$key] ) ) {
                $sorted_fields[$key] = $val;
            }
        }
        $fields = $sorted_fields;
    }
    
    return $fields;
}

// 8. Enqueue Wishlist global footer script with LocalStorage-to-Server Sync
add_action( 'wp_footer', 'chao_gang_cheng_wishlist_global_script' );
function chao_gang_cheng_wishlist_global_script() {
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Wishlist toast notification
        function showToast(message) {
            var $toast = $('.ckc-toast-notification');
            if (!$toast.length) {
                $toast = $('<div class="ckc-toast-notification"></div>');
                $('body').append($toast);
            }
            $toast.text(message).addClass('show');
            setTimeout(function() {
                $toast.removeClass('show');
            }, 3000);
        }

        // Set initial button states based on localStorage
        var favorites = JSON.parse(localStorage.getItem('ckc_favorites') || '[]');
        $('.addon-wishlist-btn').each(function() {
            var productId = parseInt($(this).data('product-id'));
            if (productId && favorites.indexOf(productId) !== -1) {
                $(this).addClass('is-active').find('.btn-text').text('已收藏');
            }
        });

        // Click handler for wishlist button
        $(document).on('click', '.addon-wishlist-btn', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var productId = parseInt($btn.data('product-id'));
            if (!productId) return;

            var favs = JSON.parse(localStorage.getItem('ckc_favorites') || '[]');
            var idx = favs.indexOf(productId);

            if (idx !== -1) {
                favs.splice(idx, 1);
                $btn.removeClass('is-active').find('.btn-text').text('加入最愛');
                showToast('已從收藏清單移除！');
            } else {
                favs.push(productId);
                $btn.addClass('is-active').find('.btn-text').text('已收藏');
                showToast('已加入收藏清單！');
            }
            localStorage.setItem('ckc_favorites', JSON.stringify(favs));

            // Sync to server via AJAX
            $.ajax({
                url: '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'toggle_wishlist',
                    product_id: productId
                }
            });
        });

        // Sync local storage favorites with server-side favorites if logged in
        <?php if ( is_user_logged_in() ) : ?>
            var serverFavorites = <?php 
                $user_id = get_current_user_id();
                $favs = get_user_meta( $user_id, '_ckc_favorites', true );
                echo json_encode( is_array( $favs ) ? array_map('intval', $favs) : array() ); 
            ?>;
            var localFavorites = JSON.parse(localStorage.getItem('ckc_favorites') || '[]');
            
            // Clean local storage array
            localFavorites = localFavorites.map(function(id) { return parseInt(id); }).filter(function(id) { return !isNaN(id); });
            
            var needsSync = false;
            localFavorites.forEach(function(id) {
                if (serverFavorites.indexOf(id) === -1) {
                    serverFavorites.push(id);
                    needsSync = true;
                }
            });
            
            if (needsSync) {
                localStorage.setItem('ckc_favorites', JSON.stringify(serverFavorites));
                
                $.ajax({
                    url: '/wp-admin/admin-ajax.php',
                    type: 'POST',
                    data: {
                        action: 'sync_wishlist',
                        favorites: serverFavorites
                    },
                    success: function(response) {
                        if (response.success) {
                            // If we are on the My Account wishlist page, reload to show new items
                            if (window.location.href.indexOf('downloads') !== -1 || $('.woocommerce-MyAccount-content').length > 0) {
                                window.location.reload();
                            }
                        }
                    }
                });
            } else {
                if (serverFavorites.length !== localFavorites.length) {
                    localStorage.setItem('ckc_favorites', JSON.stringify(serverFavorites));
                }
            }
        <?php endif; ?>
    });
    </script>
    <?php
}

// 9. AJAX Handler to sync LocalStorage favorites to server database user_meta
add_action( 'wp_ajax_sync_wishlist', 'chao_gang_cheng_sync_wishlist_handler' );
function chao_gang_cheng_sync_wishlist_handler() {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( 'User not logged in' );
    }
    
    $favorites = isset( $_POST['favorites'] ) ? $_POST['favorites'] : array();
    if ( ! is_array( $favorites ) ) {
        $favorites = array();
    }
    
    $favorites = array_map( 'intval', $favorites );
    $favorites = array_filter( $favorites );
    
    $user_id = get_current_user_id();
    update_user_meta( $user_id, '_ckc_favorites', $favorites );
    wp_send_json_success( 'Wishlist synced' );
}

// 10. Make Last Name optional globally to prevent validation errors when only First Name (姓名) is used
add_filter( 'woocommerce_billing_fields', 'chao_gang_cheng_make_billing_last_name_optional', 999 );
function chao_gang_cheng_make_billing_last_name_optional( $fields ) {
    if ( isset( $fields['billing_last_name'] ) ) {
        $fields['billing_last_name']['required'] = false;
    }
    return $fields;
}

add_filter( 'woocommerce_shipping_fields', 'chao_gang_cheng_make_shipping_last_name_optional', 999 );
function chao_gang_cheng_make_shipping_last_name_optional( $fields ) {
    if ( isset( $fields['shipping_last_name'] ) ) {
        $fields['shipping_last_name']['required'] = false;
    }
    return $fields;
}

// 11. Guarantee Points rewrite endpoints are registered globally
add_action( 'init', 'chao_gang_cheng_register_points_endpoints', 5 );
function chao_gang_cheng_register_points_endpoints() {
    add_rewrite_endpoint( 'points', EP_PAGES );
    add_rewrite_endpoint( 'view-log', EP_PAGES );
}

// 12. Custom Reward Points layout for My Account (remove default templates and output clean layout matching Image 2)
remove_all_actions( 'woocommerce_account_points_endpoint' );
add_action( 'woocommerce_account_points_endpoint', 'chao_gang_cheng_custom_account_points_content' );
function chao_gang_cheng_custom_account_points_content() {
    $user_id = get_current_user_id();
    $points = ckc_pts_get_user_balance( $user_id );
    
    // Get exchange rate
    $redeem_pts = 1;
    $redeem_val = 1;
    $conversion = get_option( 'wps_wpr_redeeming_conversion_settings', array() );
    if ( ! empty( $conversion ) && is_array( $conversion ) ) {
        if ( isset( $conversion['wps_wpr_redeem_pts'] ) && intval( $conversion['wps_wpr_redeem_pts'] ) > 0 ) {
            $redeem_pts = intval( $conversion['wps_wpr_redeem_pts'] );
        }
        if ( isset( $conversion['wps_wpr_redeem_val'] ) && intval( $conversion['wps_wpr_redeem_val'] ) > 0 ) {
            $redeem_val = intval( $conversion['wps_wpr_redeem_val'] );
        }
    }
    
    // Calculate money value
    $money_val = 0;
    if ( $redeem_pts > 0 ) {
        $money_val = floor( ($points / $redeem_pts) * $redeem_val );
    }

    // 取得點數起算月與到期日
    $start_month = get_user_meta( $user_id, '_ckc_points_start_month', true );
    $expire_text = '';
    if ( $points > 0 && $start_month ) {
        $start_time = strtotime( $start_month . '-01 00:00:00' );
        $expire_time = strtotime( '+2 years -1 day', $start_time );
        $expire_text = sprintf( '點數到期日：%s（自首月 %s 起算二年有效，並以二年為一期）', date_i18n( 'Y/m/d', $expire_time ), date_i18n( 'Y/m', $start_time ) );
    }
    ?>
    <!-- 可用點數卡片 -->
    <div class="woocommerce-MyAccount-points-card" style="background-color: white; border: 1px solid #e4e7eb; border-radius: 6px; padding: 25px 30px; display: flex; flex-direction: column; justify-content: center; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); min-height: 80px;">
        <div style="font-size: 16px; color: #111827; font-weight: 600; display: flex; align-items: center; flex-wrap: wrap; gap: 8px;">
            <span style="color: #000; font-size: 18px; font-weight: bold; letter-spacing: 0.5px;">可用點數總計</span>
            <span style="color: #f28b82; font-size: 24px; font-weight: bold; margin-left: 10px; margin-right: 2px;"><?php echo esc_html( $points ); ?></span>
            <span style="color: #f28b82; font-size: 24px; font-weight: bold; margin-right: 10px;">點</span>
            <span style="color: #7f8c8d; font-size: 14px; font-weight: normal; margin-top: 4px;">(等同於NT$<?php echo esc_html( $money_val ); ?>)</span>
        </div>
        <?php if ( $expire_text ) : ?>
            <div style="font-size: 12px; color: #64748b; margin-top: 8px; display: flex; align-items: center; gap: 4px;">
                <span>⌛</span> <?php echo esc_html( $expire_text ); ?>
            </div>
        <?php endif; ?>
    </div>

    <?php
    // 如果有設定註冊禮且尚未發放，顯示提示（已發放者不顯示）
    $signup_bonus = function_exists( 'ckc_ref_signup_bonus' ) ? ckc_ref_signup_bonus() : (int) get_option( '_ckc_ref_signup_bonus', 0 );
    $bonus_given  = get_user_meta( $user_id, '_ckc_signup_bonus_given', true );
    if ( $signup_bonus > 0 && $bonus_given ) : ?>
    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#15803d;display:flex;align-items:center;gap:10px;">
        <span>感謝您加入！新會員註冊禮 <strong><?php echo intval( $bonus_given ); ?> 點</strong>已自動入帳。</span>
    </div>
    <?php endif; ?>

    <?php
    // 近期點數異動紀錄
    $log = get_user_meta( $user_id, '_ckc_ref_log', true );
    if ( is_array( $log ) && ! empty( $log ) ) :
        $log_display = array_reverse( $log ); // 最新在前
        $log_display = array_slice( $log_display, 0, 10 ); // 最多顯示 10 筆
    ?>
    <div style="margin-top:8px;">
        <div style="font-size:14px;font-weight:700;color:#1e293b;margin-bottom:10px;border-bottom:1px solid #f1f5f9;padding-bottom:8px;">🕒 近期點數異動</div>
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.5px;">
                    <th style="padding:6px 8px;text-align:left;font-weight:600">時間</th>
                    <th style="padding:6px 8px;text-align:left;font-weight:600">說明</th>
                    <th style="padding:6px 8px;text-align:right;font-weight:600">點數</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $log_display as $entry ) :
                $pts = intval( $entry['points'] ?? 0 );
                $is_plus = $pts >= 0;
            ?>
                <tr style="border-top:1px solid #f1f5f9;">
                    <td style="padding:8px;color:#94a3b8;white-space:nowrap;font-size:12px;"><?php echo esc_html( substr( $entry['time'] ?? '', 0, 16 ) ); ?></td>
                    <td style="padding:8px;color:#475569;"><?php echo esc_html( $entry['reason'] ?? '─' ); ?></td>
                    <td style="padding:8px;text-align:right;">
                        <span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;<?php echo $is_plus ? 'background:#dcfce7;color:#15803d' : 'background:#fee2e2;color:#b91c1c'; ?>">
                            <?php echo $is_plus ? '+' : ''; echo number_format( $pts ); ?> 點
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ( count( $log ) > 10 ) : ?>
        <p style="margin:8px 0 0;font-size:12px;color:#94a3b8;text-align:right;">顯示最近 10 筆，共 <?php echo count($log); ?> 筆紀錄</p>
        <?php endif; ?>
    </div>
    <?php else : ?>
    <p style="color:#94a3b8;font-size:13px;margin-top:12px;">尚無點數異動紀錄。<a href="<?php echo home_url('/my-account/referral/'); ?>">推薦好友</a>或完成消費即可累積點數！</p>
    <?php endif; ?>
    <?php
}


// 13. Render Mobile Sticky Bottom Action Bar on Product Page
add_action( 'wp_footer', 'chao_gang_cheng_sticky_product_bar' );
function chao_gang_cheng_sticky_product_bar() {
    if ( ! is_product() ) {
        return;
    }
    
    global $product;
    if ( ! $product ) {
        return;
    }
    
    $product_id = $product->get_id();
    $cart_url = wc_get_cart_url();
    $cart_count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
    
    ?>
    <style type="text/css">
        /* Mobile Sticky Bottom Action Bar Custom Styles */
        @media (max-width: 768px) {
            html body.single-product #mydybox-taiwan-for-woocommerce-sticky-cart .ts-sticky-add-to-cart-btn {
                background-color: #7c6767 !important;
                color: #ffffff !important;
                border: none !important;
                box-shadow: 0 2px 6px rgba(124, 103, 103, 0.2) !important;
            }
            html body.single-product #mydybox-taiwan-for-woocommerce-sticky-cart .ts-sticky-add-to-cart-btn:hover {
                opacity: 0.95 !important;
                background-color: #7c6767 !important;
            }
            /* 立即購買（黏底列）：這裡原本也設成跟「加入購物車」一樣的
               taupe（#7c6767），跟頁面內文已經統一成珊瑚紅的立即購買按鈕
               不一致——而且因為這段 <style> 是掛在 wp_footer、輸出順序在
               style.min.css 之後，實際上一直蓋掉 style.css 裡對這顆按鈕
               的顏色設定，才是這顆按鈕手機版一直看起來不對的真正原因。
               改成 var(--accent-color)，和本次其他修正保持同一套色票。 */
            html body.single-product #mydybox-taiwan-for-woocommerce-sticky-cart .mydybox-taiwan-for-woocommerce-sticky-btn {
                background-color: var(--accent-color) !important;
                background-image: none !important;
                color: #ffffff !important;
                border: none !important;
                box-shadow: 0 2px 6px rgba(248, 111, 105, 0.3) !important;
            }
            html body.single-product #mydybox-taiwan-for-woocommerce-sticky-cart .mydybox-taiwan-for-woocommerce-sticky-btn:hover {
                opacity: 0.95 !important;
                background-color: var(--accent-color) !important;
            }
        }
        /* Desktop Sticky Bottom Action Bar Custom Styles */
        @media (min-width: 769px) {
            html body.single-product #mydybox-taiwan-for-woocommerce-sticky-cart .ts-sticky-add-to-cart-btn {
                background-color: #7c6767 !important;
                color: #ffffff !important;
                border: none !important;
                box-shadow: 0 2px 6px rgba(124, 103, 103, 0.2) !important;
            }
            html body.single-product #mydybox-taiwan-for-woocommerce-sticky-cart .ts-sticky-add-to-cart-btn:hover {
                opacity: 0.95 !important;
                background-color: #7c6767 !important;
            }
            html body.single-product #mydybox-taiwan-for-woocommerce-sticky-cart .mydybox-taiwan-for-woocommerce-sticky-btn {
                background-color: #7c6767 !important;
                color: #ffffff !important;
                border: none !important;
                box-shadow: 0 2px 6px rgba(124, 103, 103, 0.2) !important;
            }
            html body.single-product #mydybox-taiwan-for-woocommerce-sticky-cart .mydybox-taiwan-for-woocommerce-sticky-btn:hover {
                opacity: 0.95 !important;
                background-color: #7c6767 !important;
            }
        }
    </style>
    <div class="sticky-bottom-action-bar">
        <div class="sticky-bar-container">
            <div class="sticky-left-actions">
                <button class="sticky-fav-btn" data-product-id="<?php echo esc_attr( $product_id ); ?>" aria-label="收藏商品">
                    <!-- Heart Outline -->
                    <svg class="icon-heart-outline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                    </svg>
                    <!-- Heart Solid -->
                    <svg class="icon-heart-solid" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z" />
                    </svg>
                </button>
                <a href="<?php echo esc_url( $cart_url ); ?>" class="sticky-cart-btn" aria-label="購物車">
                    <svg class="icon-cart" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                    </svg>
                    <span class="cart-badge-count <?php echo $cart_count > 0 ? '' : 'badge-empty'; ?>"><?php echo esc_html( $cart_count ); ?></span>
                </a>
            </div>
            <div class="sticky-right-actions">
                <button class="sticky-add-to-cart-btn">加入購物車</button>
                <button class="sticky-buy-now-btn">立即購買</button>
            </div>
        </div>
    </div>
    
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Sync favorite state from localStorage on load
        var favorites = JSON.parse(localStorage.getItem('ckc_favorites') || '[]');
        var productId = <?php echo intval( $product_id ); ?>;
        if (productId && favorites.indexOf(productId) !== -1) {
            $('.sticky-fav-btn').addClass('is-active');
        }
        
        // Favorite click handler (triggers main favorite button click)
        $(document).on('click', '.sticky-fav-btn', function(e) {
            e.preventDefault();
            var $mainFavBtn = $('.addon-wishlist-btn');
            if ($mainFavBtn.length) {
                $mainFavBtn.trigger('click');
            }
        });
        
        // Sync favorite button state changes
        $(document).on('click', '.addon-wishlist-btn', function() {
            setTimeout(function() {
                var isFav = $('.addon-wishlist-btn').hasClass('is-active');
                $('.sticky-fav-btn').toggleClass('is-active', isFav);
            }, 50);
        });
        
        // Add to Cart click handler
        $(document).on('click', '.sticky-add-to-cart-btn', function(e) {
            e.preventDefault();
            var $mainCartForm = $('form.cart');
            if ($mainCartForm.length) {
                var $submitBtn = $mainCartForm.find('.single_add_to_cart_button');
                if ($submitBtn.length) {
                    $submitBtn.trigger('click');
                } else {
                    $mainCartForm.submit();
                }
            }
        });
        
        // Dynamically insert "Add to Cart" button in mobile plugin sticky bar
        function checkAndInsertMobileAddToCart() {
            var $actionContainer = $('#mydybox-taiwan-for-woocommerce-sticky-cart .mydybox-taiwan-for-woocommerce-sticky-action');
            if ($actionContainer.length && !$actionContainer.find('.ts-sticky-add-to-cart-btn').length) {
                var $buyNowBtn = $actionContainer.find('.mydybox-taiwan-for-woocommerce-sticky-btn');
                if ($buyNowBtn.length) {
                    var $addBtn = $('<button type="button" class="ts-sticky-add-to-cart-btn"><span class="dashicons dashicons-cart"></span>加入購物車</button>');
                    $addBtn.insertBefore($buyNowBtn);

                }
            }
        }
        
        // Run checks to insert button dynamically
        checkAndInsertMobileAddToCart();
        var insertInterval = setInterval(checkAndInsertMobileAddToCart, 500);
        setTimeout(function() { clearInterval(insertInterval); }, 5000);
        
        // Mobile Add to Cart button click handler
        $(document).on('click', '.ts-sticky-add-to-cart-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var form = document.querySelector('form.cart');
            if (form) {
                // Sync quantity
                var stickyQtyInput = document.querySelector('.ts-sticky-qty-input');
                if (stickyQtyInput) {
                    var mainQtyInput = form.querySelector('input.qty');
                    if (mainQtyInput) {
                        mainQtyInput.value = stickyQtyInput.value;
                        jQuery(mainQtyInput).trigger('change');
                    }
                }
                
                // Ensure no buy_now input is present (so it adds to cart normally)
                var buyNowInput = form.querySelector('input[name="buy_now"]');
                if (buyNowInput) {
                    buyNowInput.remove();
                }
                
                // Click WooCommerce add to cart button
                var mainSubmitBtn = form.querySelector('.single_add_to_cart_button');
                if (mainSubmitBtn) {
                    mainSubmitBtn.click();
                } else {
                    jQuery(form).submit();
                }
            }
        });

        
        // Intercept both desktop and mobile Buy Now buttons at the capture phase to bypass AJAX and force native redirect submission
        document.addEventListener('click', function(e) {
            var target = e.target.closest('.mydybox-taiwan-for-woocommerce-sticky-btn, .sticky-buy-now-btn');
            if (target) {
                window.ckc_is_buy_now = true;
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                
                var form = document.querySelector('form.cart');
                if (form) {
                    // Sync quantity from plugin's sticky input to WooCommerce main form
                    if (target.matches('.mydybox-taiwan-for-woocommerce-sticky-btn')) {
                        var stickyQtyInput = document.querySelector('.ts-sticky-qty-input');
                        if (stickyQtyInput) {
                            var mainQtyInput = form.querySelector('input.qty');
                            if (mainQtyInput) {
                                mainQtyInput.value = stickyQtyInput.value;
                                jQuery(mainQtyInput).trigger('change');
                            }
                        }
                    }
                    
                    // Add buy_now hidden field to trigger checkout redirect
                    if (!form.querySelector('input[name="buy_now"]')) {
                        var buyNowInput = document.createElement('input');
                        buyNowInput.type = 'hidden';
                        buyNowInput.name = 'buy_now';
                        buyNowInput.value = '1';
                        form.appendChild(buyNowInput);
                    }
                    
                    jQuery(form).submit();
                }
            }
        }, true);

        // Listen to quantity changes to update calculations
        $(document).on('change input', 'form.cart input.qty, .ts-sticky-qty-input', function() {
            if (typeof ckc_update_sticky_prices_and_calculations === 'function') {
                ckc_update_sticky_prices_and_calculations();
            }
        });

        // Listen to WooCommerce variation changes
        $(document).on('found_variation reset_data', function() {
            setTimeout(function() {
                if (typeof ckc_update_sticky_prices_and_calculations === 'function') {
                    ckc_update_sticky_prices_and_calculations();
                }
            }, 100);
        });

        // Run calculations on load
        setTimeout(function() {
            if (typeof ckc_update_sticky_prices_and_calculations === 'function') {
                ckc_update_sticky_prices_and_calculations();
            }
        }, 800);
    });
    </script>
    <?php
}

// 14. AJAX Fragment update for sticky bottom bar cart badge count
add_filter( 'woocommerce_add_to_cart_fragments', 'chao_gang_cheng_sticky_cart_fragment' );
function chao_gang_cheng_sticky_cart_fragment( $fragments ) {
    $cart_count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
    
    ob_start();
    ?>
    <span class="cart-badge-count <?php echo $cart_count > 0 ? '' : 'badge-empty'; ?>"><?php echo esc_html( $cart_count > 99 ? '99+' : $cart_count ); ?></span>
    <?php
    $fragments['span.cart-badge-count'] = ob_get_clean();
    
    return $fragments;
}


// 15. Helper to determine if current page should display the bottom shortcut navigation bar
function chao_gang_cheng_is_shortcut_bar_page() {
    return true;
}

// 16. Render Mobile Bottom Shortcut Navigation Bar
add_action( 'wp_footer', 'chao_gang_cheng_mobile_shortcut_bar' );
function chao_gang_cheng_mobile_shortcut_bar() {
    if ( ! chao_gang_cheng_is_shortcut_bar_page() ) {
        return;
    }
    
    $cart_url = wc_get_cart_url();
    $cart_count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
    $wishlist_url = class_exists( 'WooCommerce' ) ? wc_get_endpoint_url( 'downloads', '', get_permalink( get_option('woocommerce_myaccount_page_id') ) ) : '#';
    $account_url = class_exists( 'WooCommerce' ) ? get_permalink( get_option('woocommerce_myaccount_page_id') ) : '#';
    
    ?>
    <style>
    /* Bottom shortcut bar refinements: labels, safe-area, press feedback */
    .mobile-shortcut-navigation-bar {
        padding-bottom: calc(8px + env(safe-area-inset-bottom)) !important;
    }
    .mobile-shortcut-navigation-bar .shortcut-item {
        flex-direction: column;
        gap: 2px;
        width: auto !important;
        min-width: 44px;
        height: auto !important;
        min-height: 44px;
        padding: 4px 10px !important;
        transition: transform 0.15s ease;
    }
    .mobile-shortcut-navigation-bar .shortcut-item:active {
        transform: scale(0.9);
    }
    .mobile-shortcut-navigation-bar .shortcut-item svg {
        width: 24px !important;
        height: 24px !important;
    }
    .mobile-shortcut-navigation-bar .shortcut-label {
        font-size: 10px;
        line-height: 1.2;
        color: #7c6767;
        font-weight: 600;
        letter-spacing: 0.5px;
    }
    .mobile-shortcut-navigation-bar .shortcut-item.is-active .shortcut-label {
        color: var(--accent-color);
    }
    .mobile-shortcut-navigation-bar .shortcut-item .cart-badge-count {
        top: 0 !important;
        right: 4px !important;
    }
    </style>
    <div class="mobile-shortcut-navigation-bar">
        <div class="shortcut-bar-container">
            <!-- 1. Home Link -->
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="shortcut-item <?php echo is_front_page() ? 'is-active' : ''; ?>" aria-label="首頁">
                <!-- Outline -->
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon-outline icon-home-outline">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M19.5 9.75v10.125c0 .621-.504 1.125-1.125 1.125H14.25v-4.875" />
                </svg>
                <!-- Solid -->
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="icon-solid icon-home-solid">
                    <path d="M11.47 3.82a.75.75 0 011.06 0l8.69 8.69a.75.75 0 11-1.06 1.06l-.22-.22v6.39a1.5 1.5 0 01-1.5 1.5h-2.25a.75.75 0 01-.75-.75v-3.75c0-.62-.5-1.12-1.12-1.12h-2.25c-.62 0-1.12.5-1.12 1.12v3.75a.75.75 0 01-.75.75H5.75a1.5 1.5 0 01-1.5-1.5v-6.39l-.22.22a.75.75 0 01-1.06-1.06l8.69-8.69z" />
                </svg>
                <span class="shortcut-label">首頁</span>
            </a>
            
            <!-- 2. Wishlist / Favorites Link -->
            <a href="<?php echo esc_url( $wishlist_url ); ?>" class="shortcut-item <?php echo is_wc_endpoint_url( 'downloads' ) ? 'is-active' : ''; ?>" aria-label="收藏清單">
                <!-- Outline -->
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon-outline icon-heart-outline">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                </svg>
                <!-- Solid -->
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="icon-solid icon-heart-solid">
                    <path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z" />
                </svg>
                <span class="shortcut-label">收藏</span>
            </a>
            
            <!-- 3. Shopping Cart Link with badge count -->
            <a href="<?php echo esc_url( $cart_url ); ?>" class="shortcut-item <?php echo is_cart() ? 'is-active' : ''; ?>" aria-label="購物車">
                <!-- Outline -->
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon-outline icon-cart-outline">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                </svg>
                <!-- Solid -->
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="icon-solid icon-cart-solid">
                    <path d="M2.25 2.25a.75.75 0 000 1.5h1.386c.17 0 .318.114.362.278l2.558 9.592a3.752 3.752 0 00-2.806 3.63c0 .414.336.75.75.75h15.75a.75.75 0 000-1.5H5.378A2.25 2.25 0 017.5 15h11.218a.75.75 0 00.674-.421 60.086 60.086 0 003.882-9.743A.75.75 0 0022.5 4H5.97L5.436 2.008A1.5 1.5 0 003.978 1.5H2.25zM7.5 19.5a1.5 1.5 0 100 3 1.5 1.5 0 000-3zm11.25 0a1.5 1.5 0 100 3 1.5 1.5 0 000-3z" />
                </svg>
                <span class="cart-badge-count <?php echo $cart_count > 0 ? '' : 'badge-empty'; ?>"><?php echo esc_html( $cart_count > 99 ? '99+' : $cart_count ); ?></span>
                <span class="shortcut-label">購物車</span>
            </a>

            <!-- 4. Member / Account Link -->
            <a href="<?php echo esc_url( $account_url ); ?>" class="shortcut-item <?php echo ( is_account_page() && ! is_wc_endpoint_url( 'downloads' ) ) ? 'is-active' : ''; ?>" aria-label="會員帳戶">
                <!-- Outline -->
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon-outline icon-user-outline">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                </svg>
                <!-- Solid -->
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="icon-solid icon-user-solid">
                    <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM3.751 20.105a8.25 8.25 0 0116.498 0 .75.75 0 01-.437.695A18.683 18.683 0 0112 22.5c-2.786 0-5.216-.608-7.812-1.7a.75.75 0 01-.437-.695z" clip-rule="evenodd" />
                </svg>
                <span class="shortcut-label">會員</span>
            </a>
        </div>
    </div>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        function highlightShortcutBar() {
            var path = window.location.pathname;
            
            // Remove active classes first
            $('.shortcut-item').removeClass('is-active');
            
            if (path === '/' || path === '' || path === '/index.php') {
                $('.shortcut-item[aria-label="首頁"]').addClass('is-active');
            } else if (path.indexOf('/my-account/downloads/') !== -1) {
                $('.shortcut-item[aria-label="收藏清單"]').addClass('is-active');
            } else if (path.indexOf('/cart/') !== -1) {
                $('.shortcut-item[aria-label="購物車"]').addClass('is-active');
            } else if (path.indexOf('/my-account/') !== -1) {
                $('.shortcut-item[aria-label="會員帳戶"]').addClass('is-active');
            }
        }
        highlightShortcutBar();
        // Also listen to window popstate or hashchange just in case
        $(window).on('popstate hashchange', highlightShortcutBar);
    });
    </script>
    <?php
}

// 17. （已移除）首頁頂部 Banner／促銷活動／分類間 Banner／新聞 Banner 這四組
// Customizer 設定面板：這些內容現在都已改由後台「首頁編輯」（首頁模塊化編輯器，
// includes/admin/homepage-builder.php）管理，Customizer 裡的舊版設定欄位已是
// 死路（不會再被任何前台渲染邏輯讀取），故整組移除，避免造成操作者混淆，
// 誤以為改這裡也能調整首頁內容。

// 17b. 移除 Customizer 內建的「小工具」面板與「首頁設定」區塊
// 小工具：小工具管理已可從「外觀 > 小工具」獨立頁面操作，不需要在 Customizer 重複出現。
// 首頁設定（static_front_page）：WordPress 樣板優先權規則下，只要佈景主題有
// front-page.php（本站就有），首頁一律使用該樣板，「您的首頁顯示」這個切換
// 完全不會影響實際顯示內容，留著只會讓操作者誤以為改這裡有用，故移除。
// 選單（nav_menus）：後台側邊選單已新增獨立的「選單管理」入口，直接連到功能
// 完整的 nav-menus.php 選單編輯頁面，客製化工具裡這個功能較陽春的「選單」
// 面板已重複且多餘，故移除。
// WooCommerce（woocommerce）：面板內的商店通知／產品目錄／商品圖片／結帳等設定，
// 在後台「WooCommerce > 設定」都有對應且更完整的頁面可以調整，客製化工具這裡
// 只是重複入口，故移除（不影響已設定的數值，只是隱藏這個較少人使用的入口）。
add_action( 'customize_register', 'chao_gang_cheng_remove_legacy_customizer_panels', 999 );
function chao_gang_cheng_remove_legacy_customizer_panels( $wp_customize ) {
    $wp_customize->remove_panel( 'widgets' );
    $wp_customize->remove_section( 'static_front_page' );
    $wp_customize->remove_panel( 'nav_menus' );
    $wp_customize->remove_panel( 'woocommerce' );
}


// 17c. Remove WooCommerce taxonomy/archive description from below the controls bar
// (Description is already shown in the custom category hero banner above the grid)
remove_action( 'woocommerce_archive_description', 'woocommerce_taxonomy_archive_description', 10 );
remove_action( 'woocommerce_archive_description', 'woocommerce_product_archive_description', 10 );

// 17c. Set WooCommerce product loop columns
// Return 3 columns for desktop, responsive CSS will force 2 columns on mobile
add_filter( 'loop_shop_columns', 'chao_gang_cheng_shop_columns' );
function chao_gang_cheng_shop_columns( $columns ) {
    return 3;
}

// 18. Render Floating Contact and Back to Top Buttons on all pages
// Reads settings from WordPress options (configurable in Appearance > 快捷列設定)
add_action( 'wp_footer', 'chao_gang_cheng_floating_contact_buttons' );
function chao_gang_cheng_floating_contact_buttons() {
    // Load settings with defaults
    $opts = wp_parse_args(
        get_option( 'chao_gang_cheng_floating_btns', array() ),
        array(
            'show_totop'   => '1',
            'show_line'    => '1',
            'line_url'     => 'https://lin.ee/YkngLqF',
            'show_phone'   => '1',
            'phone_number' => '+886423863322',
        )
    );

    // Only render the container if at least one button is enabled
    $has_any = $opts['show_totop'] || $opts['show_line'] || $opts['show_phone'];
    if ( ! $has_any ) return;
    ?>
    <div class="floating-contact-buttons">
        <?php if ( ! empty( $opts['show_totop'] ) ) : ?>
        <!-- 1. Back to Top -->
        <a href="javascript:void(0);" onclick="window.scrollTo({ top: 0, behavior: 'smooth' });" class="floating-btn btn-totop" aria-label="回到頂端">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
            </svg>
        </a>
        <?php endif; ?>

        <?php if ( ! empty( $opts['show_line'] ) && ! empty( $opts['line_url'] ) ) : ?>
        <!-- 2. LINE Link -->
        <a href="<?php echo esc_url( $opts['line_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="floating-btn btn-line" aria-label="LINE 客服">
            <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/LINE_APP_Android.png' ); ?>" alt="LINE">
        </a>
        <?php endif; ?>

        <?php if ( ! empty( $opts['show_phone'] ) && ! empty( $opts['phone_number'] ) ) : ?>
        <!-- 3. Phone Link -->
        <?php
        $phone_raw = preg_replace( '/[^\d+]/', '', $opts['phone_number'] );
        $phone_display = esc_html( $opts['phone_number'] );
        ?>
        <a href="tel:<?php echo esc_attr( $phone_raw ); ?>" class="floating-btn btn-phone" aria-label="電話客服">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
            </svg>
        </a>
        <?php endif; ?>
    </div>
    <?php
}


// 19. Add Mobile Shop Archive Filter and Layout Scripts
add_action( 'wp_footer', 'chao_gang_cheng_mobile_shop_scripts' );
function chao_gang_cheng_mobile_shop_scripts() {
    if ( ! ( is_shop() || is_product_taxonomy() ) ) {
        return;
    }
    // Default sort label should reflect the actual WooCommerce default catalog
    // ordering (e.g.「依熱銷度排序」), not a hard-coded「預設排序」. 要透過
    // 'woocommerce_default_catalog_orderby' 這個 filter 讀（不能只讀
    // get_option() 的原始值），因為 includes/admin/product-order.php 掛了
    // 一個 filter 把實際排序方式強制蓋成 menu_order，這裡的顯示文字也要
    // 跟著反映真正生效的排序方式，兩邊才不會兜不起來。
    $default_orderby = apply_filters( 'woocommerce_default_catalog_orderby', get_option( 'woocommerce_default_catalog_orderby', 'menu_order' ) );
    $orderby_labels  = array(
        'menu_order' => '預設排序',
        'popularity' => '依熱銷度排序',
        'rating'     => '依評分排序',
        'date'       => '依最新項目排序',
        'price'      => '價格：低至高',
        'price-desc' => '價格：高至低',
    );
    $default_orderby_label = isset( $orderby_labels[ $default_orderby ] ) ? $orderby_labels[ $default_orderby ] : '預設排序';
    ?>
    <script>
    var chaoDefaultOrderbyLabel = <?php echo wp_json_encode( $default_orderby_label ); ?>;
    jQuery(document).ready(function($) {
        if (true) { // Apply to all viewports (WEB & Mobile)
            // Hide default orderby and result count
            var $nativeOrderingForm = $('.woocommerce-ordering');
            var $nativeResultCount = $('.woocommerce-result-count');
            
            // Get active query parameters
            var urlParams = new URLSearchParams(window.location.search);
            var activeFilterCount = 0;
            urlParams.forEach(function(value, key) {
                if (key.indexOf('filter_') === 0 || key === 'min_price' || key === 'max_price') {
                    activeFilterCount++;
                }
            });
            
            // Get result count
            var totalCount = 0;
            if ($nativeResultCount.length) {
                var countText = $nativeResultCount.text();
                var matches = countText.match(/顯示所有\s*(\d+)/) || countText.match(/顯示\s*(\d+)/) || countText.match(/(\d+)\s*結果/) || countText.match(/共\s*(\d+)/);
                if (matches && matches[1]) {
                    totalCount = parseInt(matches[1]);
                }
            }
            if (totalCount === 0) {
                totalCount = $('ul.products li.product').length;
            }
            
            // Create responsive filter bar HTML
            var filterBarHtml = `
                <div class="mobile-shop-controls-bar">
                    <div class="controls-left">
                        <!-- 1. Combined Orderby/Filter Dropdown -->
                        <div class="mobile-dropdown-filter" id="dropdown-orderby">
                            <span class="dropdown-trigger-text">${chaoDefaultOrderbyLabel}</span>
                            <svg class="icon-chevron" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
                            <div class="dropdown-menu-content">
                                <a href="#" data-orderby="menu_order">預設排序</a>
                                <a href="#" data-orderby="popularity">依熱銷度排序</a>
                                <a href="#" data-orderby="date">依最新項目排序</a>
                                <a href="#" data-orderby="price">價格：低至高</a>
                                <a href="#" data-orderby="price-desc">價格：高至低</a>
                                <a href="#" data-stock="instock">僅顯示有庫存</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="controls-right">
                        <!-- 4. All Filters Toggle -->
                        <button class="mobile-filter-btn" type="button">
                            <span>所有分類</span>
                            <svg class="icon-chevron" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
                        </button>
                        
                        <!-- 5. Result Count -->
                        <span class="mobile-result-count">共 ${totalCount} 件商品</span>
                    </div>
                </div>
            `;
            
            // Inject the bar
            var $targetTitle = $('.shop-main-content h1.page-title');
            if ($targetTitle.length) {
                $targetTitle.after(filterBarHtml);
            } else {
                $('.shop-main-content').prepend(filterBarHtml);
            }
            
            // 1. Setup Orderby & Filter triggers
            var currentOrderby = urlParams.get('orderby');
            var currentStock = urlParams.get('stock_status');
            
            var activeLabel = '';
            if (currentStock === 'instock') {
                activeLabel = $('#dropdown-orderby a[data-stock="instock"]').text();
            } else if (currentOrderby) {
                activeLabel = $('#dropdown-orderby a[data-orderby="' + currentOrderby + '"]').text();
            }
            
            if (activeLabel) {
                $('#dropdown-orderby .dropdown-trigger-text').text(activeLabel).addClass('active-filter');
            } else {
                // No explicit orderby in the URL: show the store's actual default
                // catalog ordering label (e.g.「依熱銷度排序」), not「預設排序」.
                $('#dropdown-orderby .dropdown-trigger-text').text(chaoDefaultOrderbyLabel);
            }
            
            $(document).on('click', '#dropdown-orderby a', function(e) {
                e.preventDefault();
                var orderbyVal = $(this).data('orderby');
                var stockVal = $(this).data('stock');
                var url = new URL(window.location.href);
                
                // Reset pagination to avoid empty pages when sorting/filtering changes
                url.searchParams.delete('paged');
                
                if (stockVal === 'instock') {
                    url.searchParams.delete('orderby');
                    url.searchParams.set('stock_status', 'instock');
                    window.location.href = url.toString();
                } else if (orderbyVal) {
                    url.searchParams.delete('stock_status');
                    url.searchParams.set('orderby', orderbyVal);
                    if ($nativeOrderingForm.length) {
                        var $select = $nativeOrderingForm.find('select.orderby');
                        if ($select.length) {
                            $select.val(orderbyVal);
                            $nativeOrderingForm.submit();
                            return;
                        }
                    }
                    window.location.href = url.toString();
                }
            });
            
            // Custom Dropdown Open/Close logic
            $(document).on('click', '.mobile-dropdown-filter .dropdown-trigger-text, .mobile-dropdown-filter .icon-chevron', function(e) {
                e.stopPropagation();
                var $dropdown = $(this).closest('.mobile-dropdown-filter');
                $('.mobile-dropdown-filter').not($dropdown).removeClass('active');
                $dropdown.toggleClass('active');
            });
            
            $(document).on('click', function() {
                $('.mobile-dropdown-filter').removeClass('active');
            });
            
            $(document).on('click', '.dropdown-menu-content', function(e) {
                e.stopPropagation(); // Avoid closing dropdown when clicked inside it
            });
            
            // Sidebar backdrop setup
            $('body').append('<div class="shop-sidebar-overlay"></div>');
            $('.shop-sidebar').prepend('<button class="shop-sidebar-close" type="button">&times;</button>');
            
            // Full sidebar drawer triggers
            $(document).on('click', '.mobile-filter-btn', function(e) {
                e.preventDefault();
                $('.shop-sidebar').addClass('is-open');
                $('.shop-sidebar-overlay').addClass('is-active');
                $('body').addClass('shop-sidebar-active');
            });
            
            $(document).on('click', '.shop-sidebar-close, .shop-sidebar-overlay', function(e) {
                e.preventDefault();
                $('.shop-sidebar').removeClass('is-open');
                $('.shop-sidebar-overlay').removeClass('is-active');
                $('body').removeClass('shop-sidebar-active');
            });
            
            
            // 7. Loop product card hover actions setup
            function setupProductHoverActions() {
                $('ul.products li.product').each(function() {
                    var $card = $(this);
                    var $link = $card.find('a.woocommerce-LoopProduct-link');
                    var $img = $link.find('img').first();
                    if ($img.length && !$link.find('.product-image-wrapper').length) {
                        var productUrl = $link.attr('href');
                        var $originalAddCart = $card.find('a.add_to_cart_button');
                        var productId = $originalAddCart.data('product_id') || '';
                        var productSku = $originalAddCart.data('product_sku') || '';
                        var addToCartUrl = $originalAddCart.attr('href') || '#';
                        
                        // Wrap image inside a wrapper div
                        $img.wrap('<div class="product-image-wrapper"></div>');
                        var $imgWrapper = $link.find('.product-image-wrapper');
                        
                        // Create custom hover overlay HTML enqueued with details and cart actions
                        var hoverOverlayHtml = `
                            <div class="product-image-hover-overlay">
                                <div class="hover-btn-group">
                                    <!-- Magnifying Glass Details Button -->
                                    <a href="${productUrl}" class="hover-btn btn-details" aria-label="查看詳情">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6a5252" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="11" cy="11" r="8"></circle>
                                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                        </svg>
                                    </a>
                                    <!-- Cart Button -->
                                    <a href="${addToCartUrl}" data-product_id="${productId}" data-product_sku="${productSku}" class="hover-btn btn-cart add_to_cart_button ajax_add_to_cart" aria-label="加入購物車">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="9" cy="21" r="1"></circle>
                                            <circle cx="20" cy="21" r="1"></circle>
                                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        `;
                        $imgWrapper.append(hoverOverlayHtml);
                    }
                });
            }
            
            // Run setup on load and on Ajax complete
            setupProductHoverActions();
            $(document).on('ajaxComplete', function() {
                setupProductHoverActions();
            });
        }
    });
    </script>
    <?php
}

// 20. Filter products by stock status via URL query parameter
add_filter( 'woocommerce_product_query_meta_query', 'chao_gang_cheng_filter_by_stock_status', 10, 2 );
function chao_gang_cheng_filter_by_stock_status( $meta_query, $query ) {
    if ( ! is_admin() && isset( $_GET['stock_status'] ) && $_GET['stock_status'] === 'instock' ) {
        $meta_query[] = array(
            'key'     => '_stock_status',
            'value'   => 'instock',
            'compare' => '=',
        );
    }
    return $meta_query;
}

require_once get_template_directory() . '/includes/woocommerce/shop-styles.php';

/**
 * 22a-1. 判斷一個運費 $package 的收件地址是否為離島（澎湖、金門、連江、
 * 綠島、蘭嶼、琉球）。抽成獨立函式，讓下面 22（離島運費調整）跟 22f
 * （商品層級的「適用運送類別」限制，離島是其中一個修飾條件）共用同一套
 * 判斷邏輯，避免兩處各自維護一份、以後修改判斷條件時只改到一邊。
 */
function chao_gang_cheng_is_outlying_island_destination( $package ) {
    $destination = isset( $package['destination'] ) ? $package['destination'] : array();
    $state = isset( $destination['state'] ) ? trim( $destination['state'] ) : '';
    $city = isset( $destination['city'] ) ? trim( $destination['city'] ) : '';
    $postcode = isset( $destination['postcode'] ) ? trim( $destination['postcode'] ) : '';

    // Check postcode prefix
    $postcode_prefix = substr( $postcode, 0, 3 );
    if ( in_array( $postcode_prefix, array( '951', '952', '929' ) ) ||
         ( intval( $postcode_prefix ) >= 880 && intval( $postcode_prefix ) <= 885 ) ||
         ( intval( $postcode_prefix ) >= 890 && intval( $postcode_prefix ) <= 896 ) ||
         ( intval( $postcode_prefix ) >= 209 && intval( $postcode_prefix ) <= 212 ) ) {
        return true;
    }

    // Check state/county name
    $outlying_states = array( '澎湖縣', '金門縣', '連江縣', 'PEN', 'KIN', 'LIE' );
    if ( in_array( $state, $outlying_states ) ) {
        return true;
    }

    // Check city name
    $outlying_cities = array( '綠島', '蘭嶼', '琉球' );
    foreach ( $outlying_cities as $oc ) {
        if ( strpos( $city, $oc ) !== false ) {
            return true;
        }
    }

    return false;
}

// 22. Adjust shipping rates for outlying islands (澎湖, 金門, 連江, 綠島, 蘭嶼, 琉球)
add_filter( 'woocommerce_package_rates', 'chao_gang_cheng_adjust_shipping_rates', 10, 2 );
function chao_gang_cheng_adjust_shipping_rates( $rates, $package ) {
    $is_outlying = chao_gang_cheng_is_outlying_island_destination( $package );

    if ( $is_outlying ) {
        foreach ( $rates as $rate_key => $rate ) {
            if ( 'flat_rate' === $rate->method_id ) {
                // Adjust shipping cost to 350 for outlying islands
                $rates[$rate_key]->cost = 350;
                $rates[$rate_key]->label = '單一費率 (離島)';

                if ( wc_tax_enabled() && 'taxable' === $rates[$rate_key]->tax_status ) {
                    $taxes = WC_Tax::calc_shipping_tax( 350, WC_Tax::get_shipping_tax_rates() );
                    $rates[$rate_key]->taxes = $taxes;
                } else {
                    $rates[$rate_key]->taxes = array();
                }
            }

            if ( 'free_shipping' === $rate->method_id ) {
                // Disable/Remove free shipping for outlying islands
                unset( $rates[$rate_key] );
            }
        }
    }

    // 22b. 當已達免運資格時（滿額或套用免運優惠券），7-11 冷凍取貨(先付款)
    // 一併免運，修正 bug：官網文案「冷凍宅配、超商取貨免運費」原本只有
    // 「宅配」有效——WooCommerce 原生 free_shipping 方式達門檻時只會讓
    // 自己出現在 $rates 且 cost 為 0，但它在前端只對應「宅配」卡片，跟它
    // 平行、各自獨立的 Wooecpay_Logistic_CVS_711（超商取貨）運費完全不受
    // 影響，導致達免運門檻後選 7-11 取貨仍被收 NT$250。
    //
    // 這裡不重新判斷一次門檻金額，而是直接看「free_shipping 這個方式此刻
    // 是否已經出現在 $rates 裡」——因為那正是 WooCommerce 對「是否達免運
    // 資格」的唯一權威判斷（同時涵蓋滿額與套用免運優惠券兩種情況），避免
    // 站內出現第二套門檻邏輯，兩邊金額基準又不一致（先前就出現過購物車
    // 頁／結帳頁門檻判斷基準不一致的 bug，見 chao_get_free_shipping_progress_amount()
    // 的說明）。離島已在上面被 unset 掉 free_shipping，所以離島訂單不會
    // 誤觸發這裡。
    $has_free_shipping = false;
    foreach ( $rates as $rate ) {
        if ( 'free_shipping' === $rate->method_id ) {
            $has_free_shipping = true;
            break;
        }
    }

    if ( $has_free_shipping ) {
        foreach ( $rates as $rate_key => $rate ) {
            if ( false !== strpos( $rate->method_id, 'Wooecpay_Logistic_CVS' ) ) {
                $rates[$rate_key]->cost = 0;
                $rates[$rate_key]->taxes = array();
            }
        }
    }

    return $rates;
}

/**
 * 22c. 讓後台商品「運送方式」分頁裡的「運送類別」下拉選單真正對前台結帳
 * 生效，而不再只是一個沒有作用的欄位。
 *
 * 自動建立四個運送類別（如果還不存在的話）：
 *   超商自取 (cvs-pickup)      → 對應「綠界物流 超商取貨」系列
 *   宅配     (home-delivery)   → 對應「免費運送 / 單一費率 / 綠界物流 宅配 黑貓」
 *   自取     (self-pickup)     → 對應「自行取貨」(local_pickup)
 *   離島     (outlying-island) → 修飾條件：客人收件地址是離島時，商品要有
 *                                 勾選這個才能用「宅配」寄過去（見下面 22f）。
 *
 * 用固定的英文代稱（slug）而非讓 WordPress 自動把中文名稱轉成
 * %e9%9b%a2...這種百分比編碼代稱，避免程式裡比對代稱時不穩定。
 *
 * 注意：這個原生的「運送類別」下拉選單本身只能單選，下面 22f 已經改成
 * 用獨立的核選方塊（可複選）讓商家設定「這件商品適用哪些運送類別」，
 * 這裡繼續建立這些 term 純粹是保留原生下拉選單可用、並讓已經用過它的
 * 商品（單選）在還沒手動設定新版核選方塊之前，可以被 22d 的限制邏輯
 * 當作退回預設值繼續辨識。
 */
add_action( 'init', 'chao_gang_cheng_ensure_shipping_classes' );
function chao_gang_cheng_ensure_shipping_classes() {
    if ( ! taxonomy_exists( 'product_shipping_class' ) ) {
        return;
    }
    $classes = array(
        'cvs-pickup'      => '超商自取',
        'home-delivery'   => '宅配',
        'self-pickup'     => '自取',
        'outlying-island' => '離島',
    );
    foreach ( $classes as $slug => $name ) {
        if ( ! term_exists( $slug, 'product_shipping_class' ) && ! term_exists( $name, 'product_shipping_class' ) ) {
            wp_insert_term( $name, 'product_shipping_class', array( 'slug' => $slug ) );
        }
    }
}

/**
 * 22d. 依購物車內商品的「適用運送類別」（見下面 22f 的核選方塊欄位），
 * 過濾結帳頁可選的運送方式。
 *
 * 掛在比 chao_gang_cheng_adjust_shipping_rates()（優先權 10）更後面的
 * 優先權 20，確保離島運費調整、以及「達免運門檻時 7-11 取貨一併免運」
 * 這兩個既有邏輯都先跑完、$rates 裡的費用都算好之後，才在這裡依運送
 * 類別把不符合的「方式」整個移除——這樣才不會因為這裡先把 free_shipping
 * 移除掉，反而讓前面判斷「是否達免運資格」的依據消失。
 *
 * 作法：檢查購物車每一項商品「適用運送類別」（可複選：自取／超商自取／
 * 宅配／離島），換算成「這件商品在目前這張訂單允許用哪些運送方式」，
 * 取所有商品的交集作為這張訂單最終可選方式。商品沒有設定任何類別（多數
 * 商品目前是這樣）視為不限制，維持原本行為。如果購物車內商品彼此衝突、
 * 交集後完全沒有共同可用方式（例如同時有「限自取」跟「限超商取貨」的
 * 商品），保守起見不限制、回傳原本全部方式，避免客人卡在結帳頁完全無法
 * 選擇任何運送方式。
 *
 * 「離島」是修飾條件，不是獨立的運送方式：只有在客人收件地址確實是離島
 * 時才會生效——這時商品的「宅配」資格必須同時也勾選「離島」才算數，否則
 * 這件商品在離島訂單裡就不允許用宅配（但自取／超商自取類別不受此影響，
 * 因為那兩種本來就跟收件地址無關）。
 */
add_filter( 'woocommerce_package_rates', 'chao_gang_cheng_restrict_rates_by_shipping_class', 20, 2 );
function chao_gang_cheng_restrict_rates_by_shipping_class( $rates, $package ) {
    if ( empty( $package['contents'] ) || ! is_array( $package['contents'] ) ) {
        return $rates;
    }

    $method_groups = array(
        'cvs-pickup'    => array( 'prefix' => 'Wooecpay_Logistic_CVS' ),
        'home-delivery' => array( 'exact' => array( 'free_shipping', 'flat_rate', 'Wooecpay_Logistic_Home_Tcat' ) ),
        'self-pickup'   => array( 'exact' => array( 'local_pickup' ) ),
    );

    $method_matches_group = function ( $method_id, $group ) {
        if ( isset( $group['prefix'] ) && false !== strpos( $method_id, $group['prefix'] ) ) {
            return true;
        }
        if ( isset( $group['exact'] ) && in_array( $method_id, $group['exact'], true ) ) {
            return true;
        }
        return false;
    };

    $is_outlying        = chao_gang_cheng_is_outlying_island_destination( $package );
    $allowed_rate_keys  = null; // null = 目前為止沒有任何商品限制過運送方式

    foreach ( $package['contents'] as $item ) {
        if ( empty( $item['product_id'] ) ) {
            continue;
        }
        $product = wc_get_product( $item['product_id'] );
        if ( ! $product ) {
            continue;
        }

        $categories = chao_gang_cheng_get_product_shipping_categories( $product );
        if ( empty( $categories ) ) {
            continue; // 沒設定運送類別，不限制此商品的運送方式。
        }

        // 「離島」是修飾條件，本身不對應任何運送方式；目的地是離島時，
        // 商品若沒有勾選「離島」，就把「宅配」從這件商品允許的類別中移除。
        $active_groups = array_diff( $categories, array( 'outlying-island' ) );
        if ( $is_outlying && in_array( 'home-delivery', $active_groups, true )
            && ! in_array( 'outlying-island', $categories, true ) ) {
            $active_groups = array_diff( $active_groups, array( 'home-delivery' ) );
        }

        if ( empty( $active_groups ) ) {
            // 這件商品在目前收件地址下沒有任何允許的運送方式，保守起見
            // 不限制此商品（避免整張訂單被鎖死選不了任何運送方式）。
            continue;
        }

        $item_allowed_keys = array();
        foreach ( $rates as $rate_key => $rate ) {
            foreach ( $active_groups as $group_slug ) {
                if ( isset( $method_groups[ $group_slug ] ) && $method_matches_group( $rate->method_id, $method_groups[ $group_slug ] ) ) {
                    $item_allowed_keys[ $rate_key ] = true;
                    break;
                }
            }
        }

        if ( null === $allowed_rate_keys ) {
            $allowed_rate_keys = $item_allowed_keys;
        } else {
            $allowed_rate_keys = array_intersect_key( $allowed_rate_keys, $item_allowed_keys );
        }
    }

    if ( null === $allowed_rate_keys || empty( $allowed_rate_keys ) ) {
        // 沒有任何商品限制過運送方式，或購物車內商品彼此衝突、交集為空
        // ——兩種情況都保守起見不限制，回傳原本全部方式。
        return $rates;
    }

    return array_intersect_key( $rates, $allowed_rate_keys );
}

/**
 * 22f. 商品「運送方式」分頁：新增「適用運送類別」核選方塊（可複選），
 * 取代原生「運送類別」下拉選單只能單選的限制。
 *
 * 儲存成獨立的 _ckc_shipping_categories 商品 meta（字串陣列），跟原生的
 * shipping_class 分類法分開存放，兩者互不影響：
 *   - 原生「運送類別」下拉選單繼續保留（給需要依類別設定運費的其他外掛/
 *     邏輯使用），但不再是這裡限制運送方式的依據。
 *   - 這裡的核選方塊才是 22d 用來判斷「這件商品適用哪些運送方式」的
 *     依據；全部不勾選＝不限制（維持目前多數商品的預設行為）。
 *
 * 讀取時如果商品從來沒有存過這個 meta（_ckc_shipping_categories 不是
 * 陣列），退回讀取原生單選 shipping_class 當作預設值，讓已經用舊版
 * 下拉選單設定過的商品行為不會被這次改版打斷；一旦商家在這個新欄位按過
 * 一次「更新」，就一律以這個欄位（可能是空陣列＝不限制）為準。
 */
function chao_gang_cheng_get_product_shipping_categories( $product ) {
    $meta = $product->get_meta( '_ckc_shipping_categories' );
    if ( is_array( $meta ) ) {
        return $meta;
    }
    $legacy_class = $product->get_shipping_class(); // term slug，沒設定時為空字串
    $legacy_valid = array( 'cvs-pickup', 'home-delivery', 'self-pickup', 'outlying-island' );
    if ( '' !== $legacy_class && in_array( $legacy_class, $legacy_valid, true ) ) {
        return array( $legacy_class );
    }
    return array();
}

add_action( 'woocommerce_product_options_shipping', 'chao_gang_cheng_shipping_categories_admin_field' );
function chao_gang_cheng_shipping_categories_admin_field() {
    global $product_object;
    $selected = $product_object ? chao_gang_cheng_get_product_shipping_categories( $product_object ) : array();
    $options  = array(
        'self-pickup'     => '自取',
        'cvs-pickup'      => '超商自取',
        'home-delivery'   => '宅配',
        'outlying-island' => '離島（此商品可寄送到離島）',
    );
    echo '<div class="options_group ckc-shipping-categories">';
    echo '<p class="form-field">';
    echo '<label>' . esc_html__( '適用運送類別', 'chao-gang-cheng' ) . '</label>';
    echo '<span style="display:inline-block;vertical-align:middle;">';
    foreach ( $options as $slug => $label ) {
        $checked = in_array( $slug, $selected, true ) ? ' checked="checked"' : '';
        echo '<label style="display:inline-block !important;float:none !important;width:auto !important;margin-left:0 !important;margin-right:16px !important;font-weight:normal;">';
        echo '<input type="checkbox" name="_ckc_shipping_categories[]" value="' . esc_attr( $slug ) . '" style="width:auto !important;float:none !important;"' . $checked . '> ' . esc_html( $label );
        echo '</label>';
    }
    echo '</span>';
    echo '<span class="description" style="display:block;margin-top:6px;">' . esc_html__( '可複選；全部不勾選＝不限制運送方式（結帳頁照常顯示所有運送方式）。勾選後，前台結帳只會顯示符合這些類別的運送方式。「離島」只有在客人收件地址確實是離島時才會被檢查：這時「宅配」要同時也勾選「離島」才能用宅配寄送。', 'chao-gang-cheng' ) . '</span>';
    echo '</p>';
    echo '</div>';
}

add_action( 'woocommerce_admin_process_product_object', 'chao_gang_cheng_shipping_categories_save' );
function chao_gang_cheng_shipping_categories_save( $product ) {
    $valid = array( 'self-pickup', 'cvs-pickup', 'home-delivery', 'outlying-island' );
    $raw   = isset( $_POST['_ckc_shipping_categories'] ) && is_array( $_POST['_ckc_shipping_categories'] )
        ? wp_unslash( $_POST['_ckc_shipping_categories'] )
        : array();
    $sanitized = array_values( array_intersect( $valid, array_map( 'sanitize_text_field', $raw ) ) );
    $product->update_meta_data( '_ckc_shipping_categories', $sanitized );
}

/**
 * 22e. 移除商品編輯畫面「商品資料」面板裡的 Facebook／Pinterest 分頁。
 *
 * 這兩個分頁是「Facebook for WooCommerce」跟「Pinterest for WooCommerce」
 * 外掛各自掛上去的（fb_commerce_tab／pinterest_attributes_tab），不是這個
 * 佈景主題加的，所以用官方建議的 woocommerce_product_data_tabs 過濾器
 * 把這兩個 key 移除，而不是去改外掛程式碼。優先權設 999，確保排在外掛
 * 自己掛上去（通常是預設優先權 10）之後執行，才能真的移除得掉。
 */
add_filter( 'woocommerce_product_data_tabs', 'chao_gang_cheng_remove_product_data_tabs', PHP_INT_MAX );
function chao_gang_cheng_remove_product_data_tabs( $tabs ) {
    unset( $tabs['fb_commerce_tab'] );
    // 注意：Pinterest for WooCommerce 實際註冊的 array key 是
    // 'pinterest_attributes'，不是看起來很像的 'pinterest_attributes_tab'
    // （後者其實是 WooCommerce 樣板組出來的 CSS class 名稱
    // "{$key}_tab"，不是 key 本身）。第一次上版時猜錯 key，導致
    // Facebook 分頁消失了但 Pinterest 分頁還在，這裡修正。
    unset( $tabs['pinterest_attributes'] );
    unset( $tabs['pinterest_attributes_tab'] ); // 保留以防萬一，不影響其他 key

    // 移除「屬性」「進階」（WooCommerce 內建）兩個分頁。
    // （「附加選項」分頁改在下面 chao_gang_cheng_hide_product_addons_tab()
    // 用 CSS 隱藏，原因見該函式註解。）
    // 「屬性」拿掉後不影響前台「規格說明」分頁——那裡已經改成優先讀取
    // 商品編輯畫面新增的可視化編輯器欄位（_ckc_product_specs_html），
    // 沒填才會回退看商品屬性；屬性資料本身不會被刪除，只是分頁不顯示，
    // 之後如需要仍可以把這行拿掉即可恢復。
    unset( $tabs['attribute'] );
    unset( $tabs['advanced'] );

    return $tabs;
}

/**
 * 「附加選項」分頁（WooCommerce Product Add-ons 外掛）用 CSS 隱藏，
 * 而不是像上面兩個一樣去 unset woocommerce_product_data_tabs 的 array key。
 *
 * 原因：曾經加過 unset( $tabs['addons'] ) / unset( $tabs['addons_tab'] )，
 * 並用除錯訊息實際印出過濾後的 $tabs 剩餘 key，證實 'addons' 這個 key
 * 當時就已經不在陣列裡了——但分頁在畫面上還是照樣出現。代表這個外掛
 * 的分頁根本不是透過 woocommerce_product_data_tabs 這個 filter 陣列
 * 加上去的，而是用另一套機制直接輸出 DOM，unset 陣列 key 對它沒有
 * 作用。因此改用實際從畫面 DOM 確認過的 class（li.addons_tab）跟分頁
 * 內容目標（#product_addons_data）直接用 CSS 隱藏，效果一樣是「後台
 * 看不到這個分頁」，且不用再猜第二次 array key。
 */
add_action( 'admin_head', 'chao_gang_cheng_hide_product_addons_tab' );
function chao_gang_cheng_hide_product_addons_tab() {
    echo '<style>
        .product_data_tabs li.addons_tab,
        #woocommerce-product-data #product_addons_data {
            display: none !important;
        }
    </style>';
}

/**
 * 移除「商品」選單底下的「附加選項」子選單（WooCommerce Product Add-ons
 * 外掛的全域附加選項管理頁，slug 是 edit.php?post_type=product&page=addons，
 * 已從實際後台選單 DOM 確認過這個 page slug，不是用猜的）。
 *
 * 用 remove_submenu_page() 精確移除，優先權設 99999：比照本檔案其他
 * 「移除選單較晚才生效」案例的既有經驗（見
 * ckc_remove_product_category_from_products_menu() 的註解），較早的
 * admin_menu 優先權有可能因為外掛更晚才註冊這個子選單而移除不掉，
 * 優先權設得夠晚才能確保外掛已經把選單加上去之後再移除。
 */
add_action( 'admin_menu', 'chao_gang_cheng_remove_product_addons_submenu', 99999 );
function chao_gang_cheng_remove_product_addons_submenu() {
    remove_submenu_page( 'edit.php?post_type=product', 'addons' );
}

/**
 * 移除文章／商品內容編輯器工具列上 Jetpack 的「新增聯絡表單」按鈕
 * （id="insert-jetpack-contact-form"，已從商品編輯畫面實際 DOM 確認過
 * 這個 id，不是用猜的）。只隱藏這顆按鈕本身，不停用 Jetpack 聯絡表單
 * 模組，不影響網站上其他地方既有的聯絡表單功能。
 */
add_action( 'admin_head', 'chao_gang_cheng_hide_jetpack_contact_form_button' );
function chao_gang_cheng_hide_jetpack_contact_form_button() {
    echo '<style>#insert-jetpack-contact-form{display:none !important;}</style>';
}

/**
 * 隱藏商品編輯畫面「商品資料 —」標題列旁邊的「可下載」「禮物卡」兩個
 * 核取方塊（保留「虛擬」）。
 *
 * 這兩個核取方塊是 WooCommerce 核心／WooCommerce Gift Cards 外掛直接
 * 用 action 印出來的 <label>，不是走可以 unset key 的陣列機制（跟前面
 * 「附加選項」分頁同一種情況），所以一樣改用 CSS 隱藏，已從實際 DOM
 * 確認過對應的 label 選擇器（label[for="_downloadable"] 與
 * label.gift_card_checkbox），不是用猜的。只隱藏勾選 UI，不影響任何
 * 既有商品本身「可下載」／「禮物卡」資料或功能。
 */
add_action( 'admin_head', 'chao_gang_cheng_hide_downloadable_giftcard_checkboxes' );
function chao_gang_cheng_hide_downloadable_giftcard_checkboxes() {
    echo '<style>
        label[for="_downloadable"],
        label.gift_card_checkbox {
            display: none !important;
        }
    </style>';
}

/**
 * 隱藏文章／商品編輯畫面「發佈」區塊裡的「Jetpack Social：未連結」那一列
 * （沒有連結社群帳號，這行只會一直顯示「未連結」，沒有實際用途）。
 * 已從實際 DOM 確認過對應元素是 Jetpack 原生輸出的
 * #publicize（misc-publishing-actions 底下），不是用猜的；只隱藏這個
 * UI 顯示列，不影響 Jetpack Social 外掛本身或任何既有設定。
 */
add_action( 'admin_head', 'chao_gang_cheng_hide_jetpack_social_publish_box_row' );
function chao_gang_cheng_hide_jetpack_social_publish_box_row() {
    echo '<style>
        #publicize.misc-pub-section {
            display: none !important;
        }
    </style>';
}

/**
 * 簡化「商品分類」列表頁面：移除「下限」「上限」「數量單位」三個欄位
 * （由 WooCommerce Min/Max Quantities 外掛加到 product_cat 分類列表的
 * 欄位，實際欄位 key 是 min／max／groupof，已從畫面上的欄位標頭
 * id="min"／id="max"／id="groupof" 確認過，不是用猜的）。只影響列表
 * 顯示的欄位，不影響這個外掛本身的數量限制功能或既有分類設定的資料。
 */
add_filter( 'manage_edit-product_cat_columns', 'chao_gang_cheng_simplify_product_cat_columns', 20 );
function chao_gang_cheng_simplify_product_cat_columns( $columns ) {
    unset( $columns['min'], $columns['max'], $columns['groupof'] );
    return $columns;
}

/**
 * 同一批「數量下限／數量上限／數量單位」欄位（WooCommerce Min/Max
 * Quantities 外掛）也會出現在「新增分類」／「編輯分類」表單裡，跟上面
 * 列表欄位是分開的兩個地方，所以另外用 CSS 隱藏。已從實際表單畫面
 * 確認過對應 <label for="minimum_quantity">／<label for="maximum_quantity">／
 * <label for="group_of_quantity">，用 :has() 精準只隱藏這三個欄位的
 * .form-field 外層，不影響表單裡其他欄位（名稱／代稱／上層分類等）。
 * 只隱藏 UI，不影響外掛功能或既有分類已設定的數量限制資料。
 */
add_action( 'admin_head-edit-tags.php', 'chao_gang_cheng_hide_product_cat_minmax_form_fields' );
add_action( 'admin_head-term.php', 'chao_gang_cheng_hide_product_cat_minmax_form_fields' );
function chao_gang_cheng_hide_product_cat_minmax_form_fields() {
    if ( ! isset( $_GET['taxonomy'] ) || 'product_cat' !== $_GET['taxonomy'] ) {
        return;
    }
    echo '<style>
        .form-field:has( label[for="minimum_quantity"] ),
        .form-field:has( label[for="maximum_quantity"] ),
        .form-field:has( label[for="group_of_quantity"] ) {
            display: none !important;
        }
    </style>';
}

/**
 * 把「庫存狀態」裡的「延期交貨」（WooCommerce 內建 onbackorder 狀態，
 * 核心英文原文 On backorder）改標籤為「預購商品」，比較貼近餐廳實際
 * 用法（現貨已出完、之後現做現出的商品，用這個狀態代表「開放預購」
 * 而不是字面上的「缺貨延後出貨」）。
 *
 * 底層關聯檢查（已逐一確認，只有這兩個地方會顯示這個狀態的文字，
 * 沒有遺漏）：
 * 1. 後台商品編輯畫面「庫存狀態」下拉選單／單選——由 WooCommerce 核心
 *    wc_get_product_stock_status_options() 透過
 *    woocommerce_product_stock_status_options 這個 filter 產生選項文字，
 *    這裡攔截並只覆蓋 onbackorder 這個 key，instock／outofstock 不動。
 * 2. 前台商品頁「貨況」文字——由 WC_Product::get_availability_text()
 *    透過 woocommerce_get_availability_text 這個 filter 輸出，這裡攔截
 *    並只在 $product->get_stock_status() 真的是 'onbackorder' 時才覆蓋，
 *    避免用文字比對（不同語系原文不同、不可靠）。
 * 3. 確認過站內沒有其他寫死「延期交貨」或 'onbackorder' 字樣的地方
 *    （唯一一處是 admin-theme.css 裡 mark.onbackorder 這個純樣式選擇器，
 *    只是圖示顏色樣式，不含文字，不用改）；購物車／結帳頁 WooCommerce
 *    本身也沒有另外針對這個狀態顯示提示文字，不需要額外處理。
 * 4. 資料庫欄位本身（_stock_status = 'onbackorder'）完全沒有變動，只是
 *    顯示文字換了，不影響庫存邏輯、允許缺貨訂購設定、或既有訂單資料。
 */
add_filter( 'woocommerce_product_stock_status_options', 'chao_gang_cheng_relabel_backorder_stock_status' );
function chao_gang_cheng_relabel_backorder_stock_status( $options ) {
    if ( isset( $options['onbackorder'] ) ) {
        $options['onbackorder'] = '預購商品';
    }
    return $options;
}

add_filter( 'woocommerce_get_availability_text', 'chao_gang_cheng_relabel_backorder_availability_text', 10, 2 );
function chao_gang_cheng_relabel_backorder_availability_text( $availability, $product ) {
    if ( $product && is_a( $product, 'WC_Product' ) && 'onbackorder' === $product->get_stock_status() && '' !== $availability ) {
        $note = trim( (string) $product->get_meta( '_ckc_preorder_note' ) );
        return '預購商品' . ( '' !== $note ? '（' . $note . '）' : '' );
    }
    return $availability;
}

/**
 * 「預購商品」自訂備註文字（例如：預計 12/25 起陸續出貨）。
 * 存成 post meta _ckc_preorder_note，同步顯示在前台「預購商品」文字
 * 後面（括號附加，見上面 chao_gang_cheng_relabel_backorder_availability_text）。
 *
 * 後台：欄位一律都會送出存檔（不用 disabled，避免庫存狀態切換時
 * 資料被清空），但只有「庫存狀態」目前選到「預購商品」（onbackorder）
 * 才能打字編輯，其餘狀態用 readonly 反灰鎖定，避免存進跟目前庫存
 * 狀態矛盾的文字，同時保留舊值方便之後切回預購商品時還在。
 */
add_action( 'woocommerce_product_options_stock', 'chao_gang_cheng_preorder_note_admin_field' );
function chao_gang_cheng_preorder_note_admin_field() {
    global $product_object;
    $note = $product_object ? $product_object->get_meta( '_ckc_preorder_note' ) : '';
    woocommerce_wp_text_input(
        array(
            'id'            => '_ckc_preorder_note',
            'label'         => '預購說明文字',
            'placeholder'   => '例如：預計 12/25 起陸續出貨',
            'description'   => '只有庫存狀態選「預購商品」時才能編輯，會同步顯示在前台「預購商品」文字後面；其餘狀態此欄位反灰鎖定。',
            'desc_tip'      => true,
            'value'         => $note,
            'wrapper_class' => 'ckc-preorder-note-field',
        )
    );
}

add_action( 'woocommerce_admin_process_product_object', 'chao_gang_cheng_preorder_note_save' );
function chao_gang_cheng_preorder_note_save( $product ) {
    $note = isset( $_POST['_ckc_preorder_note'] ) ? sanitize_text_field( wp_unslash( $_POST['_ckc_preorder_note'] ) ) : '';
    $product->update_meta_data( '_ckc_preorder_note', $note );
}

/**
 * 後台 JS：「庫存狀態」切換時，即時反灰／解鎖「預購說明文字」欄位
 * （readonly，非 disabled，確保欄位值仍會隨表單送出、不會遺失）。
 */
add_action( 'admin_footer', 'chao_gang_cheng_preorder_note_toggle_script' );
function chao_gang_cheng_preorder_note_toggle_script() {
    global $pagenow, $typenow;
    if ( 'product' !== $typenow || ! in_array( $pagenow, array( 'post.php', 'post-new.php' ), true ) ) {
        return;
    }
    ?>
    <script>
    jQuery(function ($) {
        function ckcTogglePreorderNote() {
            var checked = $('input[name="_stock_status"]:checked').val();
            var $field  = $('.ckc-preorder-note-field input[name="_ckc_preorder_note"]');
            if ('onbackorder' === checked) {
                $field.prop('readonly', false).css('opacity', '');
            } else {
                $field.prop('readonly', true).css('opacity', '0.5');
            }
        }
        $(document).on('change', 'input[name="_stock_status"]', ckcTogglePreorderNote);
        ckcTogglePreorderNote();
    });
    </script>
    <?php
}

/**
 * 「Google Listings & Ads」外掛的「Channel visibility」面板是獨立的
 * React 元件（#channel_visibility .gla_meta_box），文字是外掛自己的
 * JS 語言包，不是我們主題可以直接用 gettext filter 攔截的 PHP 字串，
 * 所以改用 MutationObserver 在文字實際渲染出來後就地替換成中文。
 * 只掃描這個面板本身（#channel_visibility），不影響頁面其他地方；
 * 使用者切換下拉選單時 React 會重新渲染，observer 會自動再翻譯一次。
 */
add_action( 'admin_footer', 'chao_gang_cheng_translate_channel_visibility_panel' );
function chao_gang_cheng_translate_channel_visibility_panel() {
    global $pagenow, $typenow;
    if ( 'product' !== $typenow || ! in_array( $pagenow, array( 'post.php', 'post-new.php' ), true ) ) {
        return;
    }
    ?>
    <script>
    (function () {
        var map = {
            'Channel visibility':      '頻道顯示狀態',
            'Sync and show':           '同步並顯示',
            "Don't Sync and show":     '不同步也不顯示'
        };
        function translateWithin( root ) {
            if ( ! root ) { return; }
            var walker = document.createTreeWalker( root, NodeFilter.SHOW_TEXT, null );
            var node;
            while ( ( node = walker.nextNode() ) ) {
                var text = node.nodeValue.trim();
                if ( map[ text ] ) {
                    node.nodeValue = map[ text ];
                }
            }
            root.querySelectorAll( 'option' ).forEach( function ( opt ) {
                var t = opt.textContent.trim();
                if ( map[ t ] ) {
                    opt.textContent = map[ t ];
                }
            } );
        }
        function init() {
            var panel = document.getElementById( 'channel_visibility' );
            if ( ! panel ) {
                return false;
            }
            translateWithin( panel );
            new MutationObserver( function () {
                translateWithin( panel );
            } ).observe( panel, { childList: true, subtree: true, characterData: true } );
            return true;
        }
        if ( ! init() ) {
            var bodyObserver = new MutationObserver( function () {
                if ( init() ) {
                    bodyObserver.disconnect();
                }
            } );
            bodyObserver.observe( document.body, { childList: true, subtree: true } );
        }
    })();
    </script>
    <?php
}

/**
 * 「商品類型」下拉選單只保留「簡單商品」，隱藏組合商品／外部或加盟
 * 商品／可變商品／產品搭售方案。只影響下拉選單本身可選的項目，
 * 不會動到既有商品資料；目前商店裡的商品也都已經是簡單商品。
 */
add_filter( 'product_type_selector', 'chao_gang_cheng_limit_product_type_selector_to_simple' );
function chao_gang_cheng_limit_product_type_selector_to_simple( $types ) {
    return array(
        'simple' => isset( $types['simple'] ) ? $types['simple'] : __( 'Simple product', 'woocommerce' ),
    );
}

/**
 * 副作用修復：上面把「商品類型」下拉選單只留下「簡單商品」之後，
 * WooCommerce Product Bundles 外掛自己的顯示/隱藏切換邏輯抓不到
 * 「搭售方案」這個選項，導致它專屬（只給搭售方案用）的幾個欄位
 * 沒被正確隱藏，在簡單商品編輯畫面裡跟簡單商品自己的欄位重複出現
 * （最明顯的是「虛擬」核取方塊出現兩次）。
 *
 * 已從實際 DOM 逐一確認過，以下這幾個都是「只給搭售方案用、不含
 * show_if_simple」的欄位，不是簡單商品也會用到的共用欄位（例如
 * 「庫存」分頁本身是兩種類型共用，不在這個清單裡，不會被誤隱藏）：
 * label[for="_virtual_bundle"]（虛擬，重複的那個）、
 * .bundled_products_tab（搭售產品分頁）、.bundle_stock_msg、
 * ._wc_pb_sold_individually_field（限購一件）、
 * .options_group.bundle_type（搭售方案類型）、
 * ._wc_pb_aggregate_weight_field（組裝後重量）。
 * 既然這個商店已經不能建立搭售方案商品了，這些欄位永遠用不到，
 * 直接隱藏是安全的。
 */
add_action( 'admin_head', 'chao_gang_cheng_hide_orphaned_bundle_only_fields' );
function chao_gang_cheng_hide_orphaned_bundle_only_fields() {
    echo '<style>
        label[for="_virtual_bundle"],
        .bundled_products_tab,
        .bundle_stock_msg,
        ._wc_pb_sold_individually_field,
        .options_group.bundle_type,
        ._wc_pb_aggregate_weight_field {
            display: none !important;
        }
    </style>';
}

/**
 * 「廣告審查用主圖」：跟官網真正顯示的主圖（特色圖像 Featured Image，
 * 可能含優惠標籤／文字疊圖）分開，讓 Google／Meta 商品目錄同步／廣告
 * 審查改用這張「乾淨」的圖，官網實際顯示的特色圖像完全不受影響。
 *
 * 存成 post meta _ckc_ad_review_image_id（附件 ID）。留空＝不啟用，
 * 兩個通路都照常使用特色圖像，不影響任何既有商品。
 *
 * 同步機制：
 * - Google（Google Listings & Ads）：用該外掛官方提供的
 *   `woocommerce_gla_product_attribute_values` filter 覆蓋 imageLink，
 *   這是外掛文件明載、用來覆蓋主圖的正式做法，不是猜的。
 * - Meta（Facebook for WooCommerce）：這個外掛本身就有內建的「自訂圖片」
 *   來源機制（meta key _wc_facebook_product_image_source＝custom，
 *   圖片網址存在 fb_product_image），所以直接寫這兩個 meta 讓外掛自己
 *   的同步邏輯採用我們這張圖，不用另外重複實作一次 Meta 目錄的圖片
 *   輸出。這兩個 meta key 是 facebook-for-woocommerce 外掛原始碼裡的
 *   常數 Products::PRODUCT_IMAGE_SOURCE_META_KEY／
 *   WC_Facebook_Product::FB_PRODUCT_IMAGE 對應的字串，來源：
 *   https://github.com/woocommerce/facebook-for-woocommerce
 *   （若店家自己在「Facebook」分頁手動設定過其他圖片來源，這裡不會
 *   覆蓋，只有先前是「這個欄位」寫入 custom 來源時，清空欄位才會
 *   自動還原，避免互相打架）。
 */
add_action( 'woocommerce_product_options_general_product_data', 'chao_gang_cheng_ad_review_image_admin_field' );
function chao_gang_cheng_ad_review_image_admin_field() {
    global $product_object;
    $image_id  = $product_object ? (int) $product_object->get_meta( '_ckc_ad_review_image_id' ) : 0;
    $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
    echo '<div class="options_group ckc-ad-review-image-field">';
    echo '<p class="form-field">';
    echo '<label>' . esc_html__( '廣告審查用主圖', 'chao-gang-cheng' ) . '</label>';
    echo '<span style="display:inline-block;vertical-align:middle;">';
    echo '<img src="' . esc_url( $image_url ) . '" class="ckc-ad-review-image-preview" style="max-width:80px;max-height:80px;display:' . ( $image_id ? 'inline-block' : 'none' ) . ';vertical-align:middle;margin-right:10px;border:1px solid #ddd;" />';
    echo '<input type="hidden" name="_ckc_ad_review_image_id" class="ckc-ad-review-image-id" value="' . esc_attr( $image_id ) . '" />';
    echo '<button type="button" class="button ckc-ad-review-image-select">' . esc_html__( '選擇圖片', 'chao-gang-cheng' ) . '</button> ';
    echo '<button type="button" class="button ckc-ad-review-image-remove" style="' . ( $image_id ? '' : 'display:none;' ) . '">' . esc_html__( '移除圖片', 'chao-gang-cheng' ) . '</button>';
    echo '</span>';
    echo '<span class="description" style="display:block;margin-top:6px;">' . esc_html__( '若上傳，Google／Meta 商品目錄同步會改用這張圖片；官網實際顯示的主圖（特色圖像）完全不受影響。留空＝兩個通路都照常使用特色圖像。', 'chao-gang-cheng' ) . '</span>';
    echo '</p>';
    echo '</div>';
}

add_action( 'woocommerce_admin_process_product_object', 'chao_gang_cheng_ad_review_image_save' );
function chao_gang_cheng_ad_review_image_save( $product ) {
    $image_id = isset( $_POST['_ckc_ad_review_image_id'] ) ? absint( $_POST['_ckc_ad_review_image_id'] ) : 0;
    $product->update_meta_data( '_ckc_ad_review_image_id', $image_id );

    if ( $image_id ) {
        $image_url = wp_get_attachment_image_url( $image_id, 'full' );
        if ( $image_url ) {
            $product->update_meta_data( '_wc_facebook_product_image_source', 'custom' );
            $product->update_meta_data( 'fb_product_image', $image_url );
            $product->update_meta_data( '_ckc_ad_review_image_drives_fb_source', 'yes' );
        }
    } elseif ( 'yes' === $product->get_meta( '_ckc_ad_review_image_drives_fb_source' ) ) {
        $product->update_meta_data( '_wc_facebook_product_image_source', 'product' );
        $product->update_meta_data( 'fb_product_image', '' );
        $product->update_meta_data( '_ckc_ad_review_image_drives_fb_source', '' );
    }
}

add_filter( 'woocommerce_gla_product_attribute_values', 'chao_gang_cheng_gla_ad_review_image_override', 10, 3 );
function chao_gang_cheng_gla_ad_review_image_override( $attributes, $wc_product, $adapter ) {
    $image_id = $wc_product ? (int) $wc_product->get_meta( '_ckc_ad_review_image_id' ) : 0;
    if ( $image_id ) {
        $image_url = wp_get_attachment_image_url( $image_id, 'full' );
        if ( $image_url ) {
            $attributes['imageLink'] = $image_url;
        }
    }
    return $attributes;
}

/**
 * 後台 JS：「廣告審查用主圖」的媒體庫選圖／移除按鈕（wp.media）。
 */
add_action( 'admin_footer', 'chao_gang_cheng_ad_review_image_uploader_script' );
function chao_gang_cheng_ad_review_image_uploader_script() {
    global $pagenow, $typenow;
    if ( 'product' !== $typenow || ! in_array( $pagenow, array( 'post.php', 'post-new.php' ), true ) ) {
        return;
    }
    ?>
    <script>
    jQuery(function ($) {
        var ckcAdReviewFrame;
        $(document).on('click', '.ckc-ad-review-image-select', function (e) {
            e.preventDefault();
            var $wrap = $(this).closest('.ckc-ad-review-image-field');
            if (ckcAdReviewFrame) {
                ckcAdReviewFrame.open();
                return;
            }
            ckcAdReviewFrame = wp.media({
                title: '選擇廣告審查用主圖',
                button: { text: '使用這張圖片' },
                multiple: false
            });
            ckcAdReviewFrame.on('select', function () {
                var attachment = ckcAdReviewFrame.state().get('selection').first().toJSON();
                var previewUrl = ( attachment.sizes && attachment.sizes.thumbnail ) ? attachment.sizes.thumbnail.url : attachment.url;
                $wrap.find('.ckc-ad-review-image-id').val(attachment.id);
                $wrap.find('.ckc-ad-review-image-preview').attr('src', previewUrl).show();
                $wrap.find('.ckc-ad-review-image-remove').show();
            });
            ckcAdReviewFrame.open();
        });
        $(document).on('click', '.ckc-ad-review-image-remove', function (e) {
            e.preventDefault();
            var $wrap = $(this).closest('.ckc-ad-review-image-field');
            $wrap.find('.ckc-ad-review-image-id').val('');
            $wrap.find('.ckc-ad-review-image-preview').hide();
            $(this).hide();
        });
    });
    </script>
    <?php
}

// 23. Guest checkout by default: "Create an account" checkbox unchecked (Baymard: forced account creation causes ~25% checkout abandonment)
add_filter( 'woocommerce_create_account_default_checked', '__return_false' );

// 24. Ensure related products always has 4 items by filling with other products if needed
add_filter( 'woocommerce_related_products', 'chao_gang_cheng_fill_related_products', 20, 3 );
function chao_gang_cheng_fill_related_products( $related_posts, $product_id, $args ) {
    $desired_count = 4;
    if ( count( $related_posts ) < $desired_count ) {
        $needed = $desired_count - count( $related_posts );
        $exclude_ids = array_merge( array( $product_id ), $related_posts );
        
        $filler_products = wc_get_products( array(
            'limit'        => $needed,
            'status'       => 'publish',
            'stock_status' => 'instock',
            'exclude'      => $exclude_ids,
            'orderby'      => 'date',
            'order'        => 'DESC',
        ) );

        foreach ( $filler_products as $filler ) {
            $related_posts[] = $filler->get_id();
        }
    }
    return $related_posts;
}

/**
 * Render user first letter as SVG avatar instead of Gravatar
 */
add_filter( 'get_avatar', 'chao_gang_cheng_custom_avatar', 99, 5 );
function chao_gang_cheng_custom_avatar( $avatar, $id_or_email, $size, $default, $alt ) {
    $user = false;

    if ( is_numeric( $id_or_email ) ) {
        $user = get_user_by( 'id', (int) $id_or_email );
    } elseif ( is_string( $id_or_email ) && ( $user_obj = get_user_by( 'email', $id_or_email ) ) ) {
        $user = $user_obj;
    } elseif ( is_object( $id_or_email ) && isset( $id_or_email->user_id ) && $id_or_email->user_id > 0 ) {
        $user = get_user_by( 'id', $id_or_email->user_id );
    }

    if ( $user ) {
        // Get the first character of the display name or username
        $name = ! empty( $user->display_name ) ? $user->display_name : $user->user_login;
        $first_char = mb_substr( $name, 0, 1 );
        $first_char = mb_strtoupper( $first_char );

        // Generate a consistent background color based on the username string
        $hash = md5( $user->user_login );
        $colors = array( '#7c6767', '#f86f69', '#3a6073', '#4ca1af', '#2c3e50', '#16a085', '#27ae60', '#2980b9', '#8e44ad', '#d35400', '#c0392b' );
        $color_index = hexdec( substr( $hash, 0, 2 ) ) % count( $colors );
        $bg_color = $colors[$color_index];

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="100%" height="100%">' .
               '<circle cx="50" cy="50" r="50" fill="' . esc_attr( $bg_color ) . '" />' .
               '<text x="50" y="50" dy=".35em" text-anchor="middle" font-size="45" font-family="system-ui, -apple-system, sans-serif" font-weight="bold" fill="#ffffff">' . esc_html( $first_char ) . '</text>' .
               '</svg>';
               
        $data_uri = 'data:image/svg+xml;base64,' . base64_encode( $svg );
        
        $class = 'avatar avatar-' . $size . ' photo';
        if ( is_array( $alt ) && isset( $alt['class'] ) ) {
            $class .= ' ' . $alt['class'];
        } elseif ( is_string( $alt ) && ! empty( $alt ) ) {
            $class .= ' ' . $alt;
        }
        
        $avatar = sprintf(
            '<img alt="%s" src="%s" class="%s" height="%d" width="%d" />',
            esc_attr( $name ),
            $data_uri,
            esc_attr( $class ),
            (int) $size,
            (int) $size
        );
    }
    return $avatar;
}

/**
 * Display prices as integers without decimal points
 */
add_filter( 'woocommerce_price_num_decimals', 'chao_gang_cheng_remove_price_decimals' );
function chao_gang_cheng_remove_price_decimals( $decimals ) {
    return 0;
}

/**
 * Optimize WordPress Admin Menu Sidebar for a cleaner, clearer structure
 */
add_action( 'admin_menu', 'chao_gang_cheng_optimize_admin_menu', 9999 );
function chao_gang_cheng_optimize_admin_menu() {
    // 1. Remove redundant feed and dev tools
    remove_menu_page( 'gutenberg' );          // Gutenberg
    remove_menu_page( 'cff-top' );            // Facebook Feed
    remove_menu_page( 'sbtt' );               // TikTok Feeds
    remove_menu_page( 'sbr' );                // Reviews Feed
    remove_menu_page( 'ctf-top' );            // Twitter Feeds
    
    // 2. Remove unused default WP features
    remove_menu_page( 'edit-comments.php' );  // 留言 (Comments)
    remove_menu_page( 'edit.php?post_type=jetpack-portfolio' ); // 產品組合 (Portfolio)
    remove_menu_page( 'edit.php?post_type=portfolio' );
    
    // 3. Remove Jetpack/WordPress.com billing/hosting stuff to make it cleaner
    remove_menu_page( 'wpcom-my-home' );      // 我的首頁
    remove_menu_page( 'wpcom-hosting' );      // 主機服務
    remove_menu_page( 'wpcom-billing' );      // 升級方案 / 帳單
    remove_menu_page( 'wpcom-upgrades' );     // 升級方案
    remove_menu_page( 'wpcom-upgrades-sub' ); // 升級方案子項
    remove_menu_page( 'jetpack' );            // Jetpack
    // 4. Remove YotuWP
    remove_menu_page( 'yotuwp' );
}

/**
 * Inject Sidebar Section Headers and CSS to group items cleanly
 */
add_action( 'admin_footer', 'chao_gang_cheng_admin_menu_styling' );
function chao_gang_cheng_admin_menu_styling() {
    ?>
    <style>
        #adminmenu li.menu-section-header {
            padding: 16px 15px 6px !important;
            font-size: 11px !important;
            font-weight: 700 !important;
            color: #8899a6 !important;
            text-transform: uppercase !important;
            letter-spacing: 1px !important;
            pointer-events: none;
            opacity: 0.85;
        }
        #adminmenu li.wp-menu-separator {
            display: none !important;
        }
    </style>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            // 注意：後台選單分類標題（網站內容／電商營運／行銷相關／會員管理／
            // 系統配置）已經改用 PHP 端的 ckc_reorganize_admin_menu_groups()
            // 直接寫進 $menu 全域陣列一次到位處理（含真正的項目搬移排序），
            // 不再用這裡的 jQuery 插入 <li> 的做法。原本這裡把 AutomateWoo
            // 移到「電商營運」前面的邏輯，維持在這裡用 jQuery 處理即可
            // （AutomateWoo 不是每次都有安裝，用 jQuery 偵測比較彈性）。
            var $aw = $('#toplevel_page_automatewoo');
            var $storeStart = $('#toplevel_page_ckc-homepage-builder');
            if ($aw.length && $storeStart.length) {
                $aw.insertBefore($storeStart);
            }

            // Translate MonsterInsights English admin notice to Traditional Chinese
            function translateMonsterInsights($el) {
                $el.contents().each(function() {
                    if (this.nodeType === 3) { // Text node
                        var text = this.nodeValue.trim();
                        if (text === 'Please Setup Website Analytics to See Audience Insights') {
                            this.nodeValue = '請設定網站分析以查看客群分析';
                        } else if (text.indexOf('MonsterInsights, the #1 WordPress Analytics') !== -1) {
                            this.nodeValue = 'MonsterInsights 是第一大的 WordPress 分析外掛，能協助您輕鬆將網站連結至 Google Analytics，讓您清楚了解訪客如何找到並使用您的網站。超過 300 萬名網站主正使用 MonsterInsights 來追蹤關鍵數據並增長業務。';
                        } else if (text === 'Please Connect Your Website to MonsterInsights') {
                            this.nodeValue = '請將您的網站連結至 MonsterInsights';
                        } else if (text === 'Learn More') {
                            if (jQuery(this).parent().closest('div').text().indexOf('MonsterInsights') !== -1) {
                                this.nodeValue = '了解更多';
                            }
                        } else if (text === 'Note: You will be transfered to MonsterInsights.com to complete the setup wizard.') {
                            this.nodeValue = '注意：您將被引導至 MonsterInsights.com 以完成設定精靈。';
                        }
                    } else if (this.nodeType === 1) { // Element node
                        translateMonsterInsights(jQuery(this));
                    }
                });
            }
            // 只針對通知區塊進行翻譯，避免遞迴掃描整個 body 導致 WooCommerce 商品編輯頁面當機 (Maximum call stack size exceeded)
            translateMonsterInsights($('.notice, .update-nag, .monsterinsights-notice'));
        });
    </script>
    <?php
}

/**
 * 修正「商店設定」（WooCommerce 原生頂層選單，slug: woocommerce）底下第一個
 * 子選單也叫「首頁」，跟本站另一個頂層選單「首頁」（網站內容編輯，
 * ckc-homepage-builder）撞名的問題——兩者意思完全不同（一個是 WooCommerce
 * 自己的營運總覽儀表板，一個是編輯前台首頁內容的地方），撞名容易讓人
 * 點錯。改名成「商店總覽」，不影響實際連結與功能，只改顯示文字。
 */
add_action( 'admin_menu', 'chao_gang_cheng_rename_woocommerce_home_submenu', 9999 );
function chao_gang_cheng_rename_woocommerce_home_submenu() {
    global $submenu;
    if ( empty( $submenu['woocommerce'] ) || ! is_array( $submenu['woocommerce'] ) ) {
        return;
    }
    foreach ( $submenu['woocommerce'] as $key => $item ) {
        if ( isset( $item[0] ) && '首頁' === wp_strip_all_tags( $item[0] ) ) {
            $submenu['woocommerce'][ $key ][0] = '商店總覽';
            break;
        }
    }
}

/**
 * 移除後台「商品」選單底下的「屬性」子選單（product_attributes，
 * 已從實際選單連結確認過 page slug 是 product_attributes，不是用猜
 * 的）。這個商店已經把商品類型限制為只有「簡單商品」（見上面
 * chao_gang_cheng_limit_product_type_selector_to_simple），不會用到
 * 可變商品／規格屬性，這個選單留著也用不到。用 remove_submenu_page
 * 移除，只影響選單顯示，不會刪除任何既有屬性資料，之後真的需要
 * 還是可以直接輸入網址回到這個頁面。
 */
add_action( 'admin_menu', 'chao_gang_cheng_remove_product_attributes_submenu', 9999 );
function chao_gang_cheng_remove_product_attributes_submenu() {
    global $submenu;
    $parent_slug = 'edit.php?post_type=product';
    if ( empty( $submenu[ $parent_slug ] ) || ! is_array( $submenu[ $parent_slug ] ) ) {
        return;
    }
    foreach ( $submenu[ $parent_slug ] as $key => $item ) {
        // $item[2] 是這個子選單自己的 slug；WooCommerce 註冊「屬性」頁面時
        // 用的是 product_attributes，用 strpos 比對比較保險，避免因為完整
        // 網址格式（有沒有帶 post_type 參數）不一致而比對不到。
        if ( isset( $item[2] ) && false !== strpos( $item[2], 'product_attributes' ) ) {
            unset( $submenu[ $parent_slug ][ $key ] );
        }
    }
}

/**
 * Discount badge on product cards & product page (category page optimization):
 * shows「-XX%」for sale items so discounts are scannable in the product grid.
 * (Replaces the previous behaviour of hiding the sale flash entirely.)
 */
add_filter( 'woocommerce_sale_flash', 'chao_gang_cheng_discount_sale_flash', 99, 3 );
function chao_gang_cheng_discount_sale_flash( $html, $post, $product ) {
    if ( ! $product || ! $product->is_on_sale() ) {
        return '';
    }
    $label   = '特價';
    $regular = floatval( $product->get_regular_price() );
    $sale    = floatval( $product->get_sale_price() );
    if ( $regular > 0 && $sale > 0 && $sale < $regular ) {
        $percent = round( ( $sale / $regular ) * 100 );
        if ( $percent >= 1 && $percent < 100 ) {
            if ( $percent % 10 === 0 ) {
                $label = ( $percent / 10 ) . '折';
            } else {
                $label = $percent . '折';
            }
        }
    }
    return '<span class="onsale chao-onsale">' . esc_html( $label ) . '</span>';
}

/**
 * Added-to-cart toast on shop/category pages: the grid's quick-add button is
 * icon-only and the "view cart" link is hidden, so give an explicit success
 * toast with a link to checkout after an AJAX add-to-cart.
 */
add_action( 'wp_footer', 'chao_gang_cheng_archive_add_to_cart_toast' );
function chao_gang_cheng_archive_add_to_cart_toast() {
    if ( ! ( is_shop() || is_product_taxonomy() || is_front_page() ) ) {
        return;
    }
    ?>
    <style>
    #chao-atc-toast {
        position: fixed; left: 50%; bottom: 80px; transform: translateX(-50%) translateY(20px);
        background: #1e293b; color: #fff; padding: 12px 20px; border-radius: 30px;
        font-size: 14px; display: flex; align-items: center; gap: 12px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.25); z-index: 999999;
        opacity: 0; pointer-events: none; transition: opacity .25s ease, transform .25s ease;
        max-width: calc(100vw - 32px); box-sizing: border-box; white-space: nowrap;
    }
    #chao-atc-toast.chao-atc-show { opacity: 1; transform: translateX(-50%) translateY(0); pointer-events: auto; }
    #chao-atc-toast a { color: #fbbf24; text-decoration: underline; font-weight: 700; }
    </style>
    <div id="chao-atc-toast" role="status" aria-live="polite">
        <span>✓ 已加入購物車</span>
        <a href="<?php echo esc_url( wc_get_cart_url() ); ?>">查看購物車</a>
    </div>
    <script>
    jQuery(function($) {
        var chaoToastTimer = null;
        $(document.body).on('added_to_cart', function() {
            var $toast = $('#chao-atc-toast');
            $toast.addClass('chao-atc-show');
            clearTimeout(chaoToastTimer);
            chaoToastTimer = setTimeout(function() {
                $toast.removeClass('chao-atc-show');
            }, 3500);
        });
    });
    </script>
    <?php
}

/**
 * Preload the homepage hero banner image (LCP element) for faster first paint.
 */
add_action( 'wp_head', 'chao_gang_cheng_front_page_preload_banner', 2 );
function chao_gang_cheng_front_page_preload_banner() {
    if ( ! is_front_page() ) {
        return;
    }
    $banner_image = get_theme_mod( 'ckc_banner_image', get_template_directory_uri() . '/assets/images/slide-buffet.jpg' );
    if ( $banner_image ) {
        echo '<link rel="preload" as="image" href="' . esc_url( $banner_image ) . '" fetchpriority="high">' . "\n";
    }
}

// Styling for the discount badge (grid cards + single product gallery)
add_action( 'wp_head', 'chao_gang_cheng_discount_badge_css' );
function chao_gang_cheng_discount_badge_css() {
    ?>
    <style>
    /* 確保商品卡片容器具備相對定位，使折扣標籤能精確對齊左上角 */
    .woocommerce ul.products li.product,
    .woocommerce-page ul.products li.product,
    .product-card {
        position: relative !important;
    }
    /* 全站統一特價標籤 (包含首頁與分類/商店分頁)，美化視覺效果 */
    .chao-onsale,
    .woocommerce span.onsale.chao-onsale,
    .woocommerce-page span.onsale.chao-onsale {
        display: inline-block !important; /* 縮小至符合文字寬度 */
        position: absolute !important;
        top: 10px !important;
        left: 10px !important;
        right: auto !important; /* 覆蓋拉伸屬性 */
        width: auto !important; /* 覆蓋拉伸屬性 */
        z-index: 9 !important;
        min-width: 0 !important;
        min-height: 0 !important;
        line-height: 1 !important;
        background: linear-gradient(135deg, #ef4444, #dc2626) !important; /* 精緻漸層紅 */
        color: #fff !important;
        font-size: 11px !important;
        font-weight: 700 !important;
        letter-spacing: 0.5px !important;
        padding: 5px 9px !important;
        border-radius: 12px !important; /* 藥丸流線型 */
        box-shadow: 0 3px 8px rgba(220, 38, 38, 0.3) !important; /* 精緻紅影 */
        margin: 0 !important;
        text-align: center !important;
        text-transform: uppercase !important;
    }
    
    /* 商品頁特價標籤定位修正 (補償 .product 的 padding 以對齊圖片邊緣) */
    .single-product .product span.onsale.chao-onsale {
        top: 50px !important; /* Desktop: 40px padding + 10px offset */
        left: 50px !important;
    }
    @media (max-width: 992px) {
        .single-product .product span.onsale.chao-onsale {
            top: 30px !important; /* Tablet: 20px padding + 10px offset */
            left: 30px !important;
        }
    }
    @media (max-width: 768px) {
        .single-product .product span.onsale.chao-onsale {
            top: 30px !important; /* Mobile: 20px padding top + 10px */
            left: 25px !important; /* Mobile: 15px padding left + 10px */
        }
    }

    /* 修正：上面 .single-product .product span.onsale 選擇器範圍太大，
       連帶誤傷了商品頁下方「AI 智慧推薦商品／相關商品」格線裡每張
       卡片的折扣標籤（因為格線裡每個 <li> 也帶有 .product class，
       跟商品主圖共用同一組選擇器），把標籤位移到 50px（電腦）／
       30px（手機），跟格線版面的圖片位置對不上，造成跑版。這裡把
       相關商品／加購商品區塊的標籤位置改回跟一般商品格線一樣的
       10px，不影響商品主圖本身的標籤定位；電腦/手機都要覆蓋。 */
    .single-product .related.products span.onsale.chao-onsale,
    .single-product .up-sells.products span.onsale.chao-onsale,
    .single-product .cross-sells.products span.onsale.chao-onsale {
        top: 10px !important;
        left: 10px !important;
    }
    @media (max-width: 992px) {
        .single-product .related.products span.onsale.chao-onsale,
        .single-product .up-sells.products span.onsale.chao-onsale,
        .single-product .cross-sells.products span.onsale.chao-onsale {
            top: 10px !important;
            left: 10px !important;
        }
    }
    @media (max-width: 768px) {
        .single-product .related.products span.onsale.chao-onsale,
        .single-product .up-sells.products span.onsale.chao-onsale,
        .single-product .cross-sells.products span.onsale.chao-onsale {
            top: 10px !important;
            left: 10px !important;
        }
    }
    </style>
    <?php
}

/**
 * Enable Jetpack sharing and likes on WooCommerce products
 * Uses a flag to prevent Jetpack's automatic the_content filter from duplicating
 * the sharing buttons — only show when our manual hook calls sharing_display().
 */
add_filter( 'sharing_show', 'chao_gang_cheng_force_sharing_on_products', 99, 2 );
function chao_gang_cheng_force_sharing_on_products( $show, $post = null ) {
    if ( $post && 'product' === $post->post_type ) {
        // Only allow sharing when our manual flag is set
        return ! empty( $GLOBALS['chao_gang_cheng_manual_sharing'] );
    }
    return $show;
}

add_filter( 'wpl_is_likes_visible', 'chao_gang_cheng_force_likes_on_products', 99, 2 );
function chao_gang_cheng_force_likes_on_products( $show, $post = null ) {
    if ( ! $post ) {
        $post = get_post();
    }
    if ( $post && 'product' === $post->post_type ) {
        return true;
    }
    return $show;
}

/**
 * Override broken database post meta (a:1:{i:0;N;}) to prevent Jetpack from disabling likes/sharing
 */
add_filter( 'get_post_metadata', 'chao_gang_cheng_override_sharing_likes_meta', 10, 4 );
function chao_gang_cheng_override_sharing_likes_meta( $value, $object_id, $meta_key, $single ) {
    if ( in_array( $meta_key, array( 'sharing_disabled', 'switch_like_status' ), true ) ) {
        remove_filter( 'get_post_metadata', 'chao_gang_cheng_override_sharing_likes_meta', 10 );
        $raw = get_post_meta( $object_id, $meta_key, false );
        add_filter( 'get_post_metadata', 'chao_gang_cheng_override_sharing_likes_meta', 10, 4 );
        
        if ( ! empty( $raw ) ) {
            $first = $raw[0];
            if ( $first === 'a:1:{i:0;N;}' || $first === array(null) || $first === array(0 => null) ) {
                return array(); // Indicate NOT disabled
            }
        }
    }
    return $value;
}

/**
 * Hook to render Jetpack sharing and Jetpack likes inside single product summary
 */
add_action( 'woocommerce_share', 'chao_gang_cheng_product_social_buttons', 15 );
function chao_gang_cheng_product_social_buttons() {
    echo '<div class="product-social-share-buttons" style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">';
    
    // 1. Output Jetpack sharing display (set flag so sharing_show filter allows it)
    if ( function_exists( 'sharing_display' ) ) {
        $GLOBALS['chao_gang_cheng_manual_sharing'] = true;
        echo '<div class="product-social-item product-jetpack-share" style="margin-bottom: 15px;">';
        sharing_display( '', true );
        echo '</div>';
        $GLOBALS['chao_gang_cheng_manual_sharing'] = false;
    }
    
    // 2. Output Jetpack likes display
    if ( class_exists( 'Jetpack_Likes' ) ) {
        echo '<div class="product-social-item product-jetpack-likes" style="margin-bottom: 10px;">';
        $likes = Jetpack_Likes::init();
        echo $likes->post_likes( '' );
        echo '</div>';
    }
    
    echo '</div>';
}

// Remove Jetpack's default woocommerce_share hook to prevent potential duplicates
remove_action( 'woocommerce_share', 'jetpack_woocommerce_social_share_icons', 10 );


// =============================================================================
// 後台插件選單重新整理
// 將指定插件的頂層選單移至正確父選單下
// =============================================================================

/**
 * 輔助函式：在 $menu 中以關鍵字搜尋父選單 slug
 *
 * @param  string[] $candidates 可能的 slug 關鍵字（大小寫不敏感）
 * @return string|null
 */
function ckc_find_menu_parent_slug( array $candidates ) {
    global $menu;
    foreach ( $menu as $item ) {
        if ( empty( $item[2] ) ) continue;
        foreach ( $candidates as $kw ) {
            if ( stripos( $item[2], $kw ) !== false ) {
                return $item[2];
            }
        }
    }
    return null;
}

/**
 * 將插件頂層選單項目搬移到指定父選單
 * - WhatsApp → 行銷 (marketing / woocommerce-marketing)
 * - WebP Tools / YotuWP → 設定 (options-general.php)
 *
 * 使用優先度 9999 確保所有插件都已完成選單註冊
 */
add_action( 'admin_menu', 'ckc_reorganize_plugin_menus', 9999 );
function ckc_reorganize_plugin_menus() {
    global $menu, $submenu;

    // ── 定義搬移規則 ──────────────────────────────────────────
    // key   = 搜尋關鍵字（比對 slug 或 title，大小寫不敏感）
    // value = 目標父選單 slug（或 '__marketing__' 代號）
    $move_rules = array(
        'whatsapp' => '__marketing__',  // WhatsApp → 行銷
        'webp'     => 'options-general.php',  // WebP Tools → 設定
    );

    // ── 找出行銷父選單 slug ───────────────────────────────────
    $marketing_slug = ckc_find_menu_parent_slug( array(
        'marketing',
        'woocommerce-marketing',
        'wc-admin',
    ) );

    // ── 掃描頂層選單並搬移 ────────────────────────────────────
    $processed_slugs = array(); // 防止重複處理
    foreach ( $menu as $pos => $item ) {
        if ( empty( $item[2] ) ) continue;

        $item_slug  = $item[2];
        $item_title = isset( $item[0] ) ? wp_strip_all_tags( $item[0] ) : '';
        $item_cap   = isset( $item[1] ) ? $item[1] : 'manage_options';

        if ( in_array( $item_slug, $processed_slugs, true ) ) continue;

        foreach ( $move_rules as $keyword => $target_parent ) {
            if ( stripos( $item_slug, $keyword ) === false &&
                 stripos( $item_title, $keyword ) === false ) {
                continue;
            }

            $parent_slug = ( $target_parent === '__marketing__' )
                ? $marketing_slug
                : $target_parent;

            if ( ! $parent_slug ) continue;

            // 從頂層移除
            unset( $menu[ $pos ] );
            $processed_slugs[] = $item_slug;

            // 加入目標父選單
            if ( ! isset( $submenu[ $parent_slug ] ) ) {
                $submenu[ $parent_slug ] = array();
            }
            $submenu[ $parent_slug ][] = array(
                $item_title,
                $item_cap,
                $item_slug,
                $item_title,
            );

            break;
        }
    }
}

// =============================================================================
// 後台選單分類重組
// 把左側選單依「控制台／總覽」獨立列 + 五大分類（網站內容、電商營運、
// 行銷相關、會員管理、系統配置）重新分組排序，並在每組最前面插入分類
// 標題（沿用既有 .menu-section-header 樣式）。
//
// 做法：從 $menu 全域陣列中，用「slug 完全比對」抓出所有要搬移的既有
// 項目（抓到就從原本位置移除），然後依照下面 $groups 定義的順序，
// 緊接在「控制台」後面重新插入一次，順便套用需要改的顯示名稱
// （Stats→總覽、行銷→外部行銷、分析→數據分析）。沒被抓到的項目
// （例如「我的首頁」、選單分隔線，或未來新安裝的外掛選單）完全不動，
// 只是被整組往後推，不會被移除或遺失。
//
// 用「slug 比對」而不是直接對著文字改標籤，是因為這樣即使日後
// WooCommerce／Jetpack 更新調整了顯示文字，只要 slug 沒變，這裡的
// 分組邏輯還是能正確運作。
//
// 優先權用 999999，確保排在所有其他選單註冊、搬移邏輯（包含上面的
// ckc_setup_website_features_menu 優先權 99999）都執行完之後才跑，
// 這樣才能抓到每個項目「最終」的註冊資料。
// =============================================================================
add_action( 'admin_menu', 'ckc_reorganize_admin_menu_groups', 999999 );
function ckc_reorganize_admin_menu_groups() {
    global $menu;

    if ( ! is_array( $menu ) ) {
        return;
    }

    // 目標分組與新顯示名稱（value 為 null 代表沿用原本註冊時的文字）。
    // key 為分類標題文字，null 代表「不加分類標題」的獨立項目。
    $groups = array(
        null => array(
            'stats' => '總覽',
        ),
        '網站內容' => array(
            'ckc-homepage-builder' => null, // 首頁
            'ckc-website-features' => null, // 網站功能
        ),
        '電商營運' => array(
            'edit.php?post_type=product' => null, // 商品
            'woocommerce'                => null, // 商店設定
            'ckc-gemini-agent'           => null, // 出貨AI助理
        ),
        '行銷相關' => array(
            'ckc-coupon-center'                 => null, // 折價券點數
            'woocommerce-marketing'             => '外部行銷',
            'wc-admin&path=/analytics/overview' => '數據分析',
        ),
        '會員管理' => array(
            'users.php'                 => null, // 使用者
            'ckc-referral-admin'        => null, // 分潤夥伴
            'ckc-referral-product-tier' => null, // 商品分潤分類
        ),
        '系統配置' => array(
            'https://wordpress.com/overview/eshopckc.com' => null, // 主機服務
            'paid-upgrades'                                => null, // 升級方案
        ),
    );

    // 1. 建立「要搬移的 slug」查詢表
    $wanted_slugs = array();
    foreach ( $groups as $items ) {
        foreach ( $items as $slug => $label ) {
            $wanted_slugs[ $slug ] = true;
        }
    }

    // 2. 從現有 $menu 抓出要搬移的項目，並從原位置移除
    $captured = array();
    foreach ( $menu as $pos => $item ) {
        if ( ! empty( $item[2] ) && isset( $wanted_slugs[ $item[2] ] ) ) {
            $captured[ $item[2] ] = $item;
            unset( $menu[ $pos ] );
        }
    }

    if ( empty( $captured ) ) {
        return; // 一個都沒抓到，代表結構已經跟預期差很多，直接跳過避免誤動作
    }

    // 3. 依目標順序組出要插入的列（分類標題 + 項目）
    $rows = array();
    foreach ( $groups as $header_label => $items ) {
        $group_rows = array();
        foreach ( $items as $slug => $new_label ) {
            if ( ! isset( $captured[ $slug ] ) ) {
                continue; // 這次沒抓到（例如外掛未啟用），跳過避免整組報錯
            }
            $group_rows[] = array( 'type' => 'item', 'slug' => $slug, 'label' => $new_label );
        }
        if ( empty( $group_rows ) ) {
            continue; // 整組都沒抓到就不插入空的分類標題
        }
        if ( $header_label ) {
            $rows[] = array( 'type' => 'header', 'label' => $header_label );
        }
        foreach ( $group_rows as $row ) {
            $rows[] = $row;
        }
    }

    // 4. 重建 $menu：依序走訪「移除搬移項目後」剩下的 $menu（保留其原本
    //    的走訪順序，不動到任何其他項目），走到「控制台」（index.php）
    //    時，直接把整組 $rows（分類標題＋項目，順序已經照 3. 排好）
    //    原封不動地接在它後面插入。這裡刻意採用跟既有 ckc_setup_website_
    //    features_menu()（見上方 26g）同一套「重新走訪、原地插入」的
    //    做法，而不是憑位置數字去猜插入點——避免多個項目/標題彼此之間
    //    的浮點數位置值太接近，导致最終排序跟走訪順序對不上的問題。
    $new_menu = array();
    $inserted = false;
    foreach ( $menu as $pos => $item ) {
        $new_menu[ $pos ] = $item;

        $slug = isset( $item[2] ) ? $item[2] : '';
        if ( ! $inserted && 'index.php' === $slug ) {
            $base_pos = floatval( $pos );
            foreach ( $rows as $n => $row ) {
                $key = strval( $base_pos + 0.001 * ( $n + 1 ) );
                while ( isset( $new_menu[ $key ] ) || isset( $menu[ $key ] ) ) {
                    $key = strval( floatval( $key ) + 0.0001 );
                }
                if ( 'header' === $row['type'] ) {
                    $new_menu[ $key ] = array( $row['label'], 'read', '#', '', 'menu-section-header ckc-menu-section-header' );
                } else {
                    $row_item = $captured[ $row['slug'] ];
                    if ( null !== $row['label'] ) {
                        $row_item[0] = $row['label'];
                    }
                    $new_menu[ $key ] = $row_item;
                }
            }
            $inserted = true;
        }
    }

    if ( ! $inserted ) {
        // 保底：理論上一定找得到「控制台」，這只是防呆，找不到就整組放最前面
        $prefix = array();
        foreach ( $rows as $n => $row ) {
            $key = strval( 1 + 0.001 * ( $n + 1 ) );
            if ( 'header' === $row['type'] ) {
                $prefix[ $key ] = array( $row['label'], 'read', '#', '', 'menu-section-header ckc-menu-section-header' );
            } else {
                $row_item = $captured[ $row['slug'] ];
                if ( null !== $row['label'] ) {
                    $row_item[0] = $row['label'];
                }
                $prefix[ $key ] = $row_item;
            }
        }
        $new_menu = $prefix + $new_menu;
    }

    $menu = $new_menu;
}

// =============================================================================
// 右側快捷列後台設定頁
// WordPress 後台：外觀 > 快捷列設定
// =============================================================================

/**
 * 20a. 在外觀選單下新增「快捷列設定」子選單
 */
/**
 * 隱藏外觀選單中的「佈景主題」與「區塊版面配置」子項目
 * 使用優先度 999 確保在所有選單都已註冊後才執行移除
 */
add_action( 'admin_menu', 'ckc_remove_appearance_submenus', 999 );
function ckc_remove_appearance_submenus() {
    // 佈景主題 (Themes)
    remove_submenu_page( 'themes.php', 'themes.php' );

    // 區塊版面配置 / 全站編輯器 (Block Patterns / Site Editor)
    remove_submenu_page( 'themes.php', 'site-editor.php' );

    // 區塊版面配置（舊版 / 自訂文章類型版）
    remove_submenu_page( 'themes.php', 'edit.php?post_type=wp_block' );
}

/**
 * 20a-1. 「快捷列設定」原本掛在「外觀」選單底下，後來一度移到「網站內容」
 * 分類區塊成為獨立頂層選單，現在收整到「首頁」頂層選單（homepage-builder.php
 * 註冊的 ckc-homepage-builder）底下的子選單，跟其他 5 個首頁相關設定並列，
 * 方便管理前台右側浮動按鈕（LINE／電話／回到頂端）的設定入口。
 * 沿用既有的 slug 與渲染回呼，用 admin_menu 優先權 14 確保排在子選單
 * 列表倒數第 2 位。
 */
add_action( 'admin_menu', 'ckc_floating_btns_add_menu', 14 );

function ckc_floating_btns_add_menu() {
    add_submenu_page(
        'ckc-homepage-builder', // 父選單 slug（「首頁」）
        '快捷列設定',        // 頁面標題
        '快捷列設定',        // 選單標籤
        'manage_options',    // 權限：管理員
        'ckc-floating-btns', // 選單 slug
        'ckc_floating_btns_page_html' // 渲染回呼
    );
}

/**
 * 20b. 向 WordPress Settings API 註冊設定
 */
add_action( 'admin_init', 'ckc_floating_btns_register_settings' );
function ckc_floating_btns_register_settings() {
    register_setting(
        'ckc_floating_btns_group',          // option group
        'chao_gang_cheng_floating_btns',    // option name (儲存在 wp_options)
        array(
            'sanitize_callback' => 'ckc_floating_btns_sanitize',
        )
    );
}

/**
 * 20c. 資料清理與驗證
 */
function ckc_floating_btns_sanitize( $input ) {
    $clean = array();
    $clean['show_totop']   = ! empty( $input['show_totop'] )   ? '1' : '';
    $clean['show_line']    = ! empty( $input['show_line'] )    ? '1' : '';
    $clean['line_url']     = isset( $input['line_url'] )       ? esc_url_raw( trim( $input['line_url'] ) )  : '';
    $clean['show_phone']   = ! empty( $input['show_phone'] )   ? '1' : '';
    $clean['phone_number'] = isset( $input['phone_number'] )   ? sanitize_text_field( trim( $input['phone_number'] ) ) : '';
    return $clean;
}

/**
 * 20d. 後台設定頁面 HTML
 */
function ckc_floating_btns_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( '您沒有權限存取此頁面。' );
    }

    $opts = wp_parse_args(
        get_option( 'chao_gang_cheng_floating_btns', array() ),
        array(
            'show_totop'   => '1',
            'show_line'    => '1',
            'line_url'     => 'https://lin.ee/YkngLqF',
            'show_phone'   => '1',
            'phone_number' => '+886423863322',
        )
    );
    ?>
    <div class="wrap" id="ckc-floating-settings">
        <h1 style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:24px;">⚡</span>
            右側快捷列設定
        </h1>
        <p style="color:#666;margin-top:4px;">控制前台右側浮動按鈕的顯示與連結設定。設定儲存後立即在前台生效。</p>
        <hr style="margin:20px 0;">

        <?php if ( isset( $_GET['settings-updated'] ) ) : ?>
        <div id="setting-error-settings_updated" class="notice notice-success settings-error is-dismissible">
            <p><strong>✅ 設定已成功儲存！</strong></p>
        </div>
        <?php endif; ?>

        <form method="post" action="options.php" style="max-width:680px;">
            <?php
            settings_fields( 'ckc_floating_btns_group' );
            ?>

            <!-- ── 回到頂端按鈕 ── -->
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:24px 28px;margin-bottom:20px;box-shadow:0 1px 4px rgba(0,0,0,.06);">
                <div style="display:flex;align-items:center;gap:14px;">
                    <div style="width:48px;height:48px;border-radius:50%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#555" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5"/></svg>
                    </div>
                    <div style="flex:1;">
                        <h3 style="margin:0 0 4px;">回到頂端按鈕</h3>
                        <p style="margin:0;color:#888;font-size:13px;">點擊後平滑滾動至頁面頂端</p>
                    </div>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="chao_gang_cheng_floating_btns[show_totop]" value="1" <?php checked( '1', $opts['show_totop'] ); ?>
                               style="width:18px;height:18px;cursor:pointer;">
                        <span style="font-size:14px;color:#333;">顯示</span>
                    </label>
                </div>
            </div>

            <!-- ── LINE 按鈕 ── -->
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:24px 28px;margin-bottom:20px;box-shadow:0 1px 4px rgba(0,0,0,.06);">
                <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px;">
                    <div style="width:48px;height:48px;border-radius:50%;background:#06C755;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg width="26" height="26" viewBox="0 0 48 48" fill="#fff"><path d="M24 4C12.95 4 4 11.82 4 21.5c0 5.92 3.37 11.15 8.6 14.52-.37 1.38-1.34 4.98-1.54 5.75-.24.93.34 1.15.72.84.3-.24 4.73-3.2 6.65-4.5.82.12 1.67.18 2.57.18 11.05 0 20-7.82 20-17.5S35.05 4 24 4z"/></svg>
                    </div>
                    <div style="flex:1;">
                        <h3 style="margin:0 0 4px;">LINE 按鈕</h3>
                        <p style="margin:0;color:#888;font-size:13px;">點擊後開啟 LINE 官方帳號連結</p>
                    </div>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="chao_gang_cheng_floating_btns[show_line]" value="1" <?php checked( '1', $opts['show_line'] ); ?>
                               style="width:18px;height:18px;cursor:pointer;">
                        <span style="font-size:14px;color:#333;">顯示</span>
                    </label>
                </div>
                <div style="background:#f9f9f9;border-radius:7px;padding:14px 16px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:6px;">LINE 連結網址</label>
                    <input type="url" name="chao_gang_cheng_floating_btns[line_url]"
                           value="<?php echo esc_attr( $opts['line_url'] ); ?>"
                           placeholder="https://lin.ee/xxxxxxx"
                           style="width:100%;padding:9px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;box-sizing:border-box;">
                    <p style="margin:6px 0 0;font-size:12px;color:#aaa;">請輸入完整的 LINE 好友連結或群組邀請網址</p>
                </div>
            </div>

            <!-- ── 電話按鈕 ── -->
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:24px 28px;margin-bottom:28px;box-shadow:0 1px 4px rgba(0,0,0,.06);">
                <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px;">
                    <div style="width:48px;height:48px;border-radius:50%;background:#4E8D9C;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg>
                    </div>
                    <div style="flex:1;">
                        <h3 style="margin:0 0 4px;">電話按鈕</h3>
                        <p style="margin:0;color:#888;font-size:13px;">點擊後直撥電話（行動裝置有效）</p>
                    </div>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="chao_gang_cheng_floating_btns[show_phone]" value="1" <?php checked( '1', $opts['show_phone'] ); ?>
                               style="width:18px;height:18px;cursor:pointer;">
                        <span style="font-size:14px;color:#333;">顯示</span>
                    </label>
                </div>
                <div style="background:#f9f9f9;border-radius:7px;padding:14px 16px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:6px;">電話號碼</label>
                    <input type="tel" name="chao_gang_cheng_floating_btns[phone_number]"
                           value="<?php echo esc_attr( $opts['phone_number'] ); ?>"
                           placeholder="+886423863322"
                           style="width:100%;padding:9px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;box-sizing:border-box;">
                    <p style="margin:6px 0 0;font-size:12px;color:#aaa;">建議含國碼格式，例如：+886423863322（台灣：04-2386-3322）</p>
                </div>
            </div>

            <div style="display:flex;align-items:center;gap:12px;">
                <?php submit_button( '💾 儲存設定', 'primary large', 'submit', false, array( 'style' => 'height:44px;padding:0 28px;font-size:15px;font-weight:600;border-radius:8px;' ) ); ?>
                <span style="font-size:13px;color:#aaa;">設定儲存後即時生效，無需清除快取</span>
            </div>
        </form>

        <hr style="margin:32px 0 20px;">
        <p style="font-size:12px;color:#bbb;">潮港城客製電商主題 · 快捷列設定 · 由 Antigravity AI 建置</p>
    </div>
    <?php
}


// =============================================================================
// WordPress 後台：首頁 > 加價專區設定
//
// 背景：商品頁的「加價專區」（下單即可加購主廚經典手路菜）原本參與的
// 分類（冷凍／下酒菜）、每件優惠金額（NT$20）、最多顯示幾件（6 件）
// 全部寫死在 chao_gang_cheng_addon_purchase_section() 裡，後台完全沒有
// 調整入口。這裡比照「快捷列設定」同樣的做法（WordPress Settings API +
// 收整到「首頁」頂層選單底下），新增一個設定頁面，讓這三項可以直接在
// 後台調整、儲存後即時生效，不用改程式碼。
// =============================================================================

/**
 * 在「首頁」頂層選單底下新增「加價專區設定」子選單。
 * 優先權 16，排在「Logo 設定」（優先權 15）之後。
 */
add_action( 'admin_menu', 'ckc_addon_zone_add_menu', 16 );
function ckc_addon_zone_add_menu() {
    add_submenu_page(
        'ckc-homepage-builder',
        '加價專區設定',
        '加價專區設定',
        'manage_options',
        'ckc-addon-zone',
        'ckc_addon_zone_page_html'
    );
}

/**
 * 向 WordPress Settings API 註冊設定
 */
add_action( 'admin_init', 'ckc_addon_zone_register_settings' );
function ckc_addon_zone_register_settings() {
    register_setting(
        'ckc_addon_zone_group',
        'chao_gang_cheng_addon_zone_settings',
        array(
            'sanitize_callback' => 'ckc_addon_zone_sanitize',
        )
    );
}

/**
 * 資料清理與驗證。
 * - categories：只留下真的存在於商品分類（product_cat）裡的 slug，避免存進
 *   已被刪除或打錯字的分類 slug，導致前台完全查不到任何加購商品。
 * - discount：優惠金額，限制在 0 ~ 原價之間都合理的正整數範圍（0 ~ 100000）。
 * - max_items：最多顯示幾件，限制在 1 ~ 20（太多會讓橫向滑動的卡片列表塞爆版面）。
 */
function ckc_addon_zone_sanitize( $input ) {
    $clean = array();

    $submitted_categories = isset( $input['categories'] ) && is_array( $input['categories'] )
        ? array_map( 'sanitize_title', $input['categories'] )
        : array();

    $valid_slugs = array();
    $all_cats    = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
    if ( ! is_wp_error( $all_cats ) ) {
        foreach ( $all_cats as $cat ) {
            $valid_slugs[] = $cat->slug;
        }
    }
    $clean['categories'] = array_values( array_intersect( $submitted_categories, $valid_slugs ) );

    // 一個分類都沒勾會讓前台完全查不到商品、整個區塊消失，保守起見退回
    // 原本的預設分類，而不是存一個空陣列。
    if ( empty( $clean['categories'] ) ) {
        $clean['categories'] = array( 'frozen', 'side-dishes' );
    }

    $discount          = isset( $input['discount'] ) ? (float) $input['discount'] : 20;
    $clean['discount'] = max( 0, min( 100000, $discount ) );

    $max_items          = isset( $input['max_items'] ) ? (int) $input['max_items'] : 6;
    $clean['max_items'] = max( 1, min( 20, $max_items ) );

    return $clean;
}

/**
 * 後台設定頁面 HTML
 */
function ckc_addon_zone_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( '您沒有權限存取此頁面。' );
    }

    $opts = chao_gang_cheng_get_addon_zone_settings();

    $all_cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
    if ( is_wp_error( $all_cats ) ) {
        $all_cats = array();
    }
    ?>
    <div class="wrap" id="ckc-addon-zone-settings">
        <h1 style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:24px;">🛒</span>
            加價專區設定
        </h1>
        <p style="color:#666;margin-top:4px;">控制商品頁「加價專區」要從哪些分類抽商品、每件優惠多少錢、最多顯示幾件。設定儲存後立即在前台生效。</p>
        <hr style="margin:20px 0;">

        <?php if ( isset( $_GET['settings-updated'] ) ) : ?>
        <div id="setting-error-settings_updated" class="notice notice-success settings-error is-dismissible">
            <p><strong>✅ 設定已成功儲存！</strong></p>
        </div>
        <?php endif; ?>

        <form method="post" action="options.php" style="max-width:680px;">
            <?php settings_fields( 'ckc_addon_zone_group' ); ?>

            <!-- ── 參與分類 ── -->
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:24px 28px;margin-bottom:20px;box-shadow:0 1px 4px rgba(0,0,0,.06);">
                <h3 style="margin:0 0 4px;">參與分類</h3>
                <p style="margin:0 0 14px;color:#888;font-size:13px;">加價專區只會從勾選的分類裡抽商品（依上架時間新到舊排序）。</p>
                <div style="display:flex;flex-wrap:wrap;gap:10px 24px;">
                    <?php foreach ( $all_cats as $cat ) : ?>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:14px;">
                            <input type="checkbox"
                                   name="chao_gang_cheng_addon_zone_settings[categories][]"
                                   value="<?php echo esc_attr( $cat->slug ); ?>"
                                   <?php checked( in_array( $cat->slug, $opts['categories'], true ) ); ?>
                                   style="width:16px;height:16px;cursor:pointer;">
                            <?php echo esc_html( $cat->name ); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ── 優惠金額與顯示件數 ── -->
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:24px 28px;margin-bottom:28px;box-shadow:0 1px 4px rgba(0,0,0,.06);">
                <div style="margin-bottom:18px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:6px;">每件優惠金額（NT$）</label>
                    <input type="number" min="0" step="1"
                           name="chao_gang_cheng_addon_zone_settings[discount]"
                           value="<?php echo esc_attr( $opts['discount'] ); ?>"
                           style="width:160px;padding:9px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;">
                    <p style="margin:6px 0 0;font-size:12px;color:#aaa;">加購價 = 該商品原價 - 這個金額（最低不會低於 NT$10）。</p>
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:6px;">最多顯示幾件</label>
                    <input type="number" min="1" max="20" step="1"
                           name="chao_gang_cheng_addon_zone_settings[max_items]"
                           value="<?php echo esc_attr( $opts['max_items'] ); ?>"
                           style="width:160px;padding:9px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;">
                    <p style="margin:6px 0 0;font-size:12px;color:#aaa;">建議 4～8 件，太多會讓橫向滑動列表過長。</p>
                </div>
            </div>

            <div style="display:flex;align-items:center;gap:12px;">
                <?php submit_button( '💾 儲存設定', 'primary large', 'submit', false, array( 'style' => 'height:44px;padding:0 28px;font-size:15px;font-weight:600;border-radius:8px;' ) ); ?>
                <span style="font-size:13px;color:#aaa;">設定儲存後即時生效，無需清除快取</span>
            </div>
        </form>

        <hr style="margin:32px 0 20px;">
        <p style="font-size:12px;color:#bbb;">潮港城客製電商主題 · 加價專區設定 · 由 Antigravity AI 建置</p>
    </div>
    <?php
}

/**
 * 頂端公告列設定：原本這條網站最上方的公告文字（含優惠訊息）是寫死在
 * header.php 裡，改為在後台「首頁」頂層選單下新增「公告列設定」子選單，
 * 讓操作者可以自行開關、修改文字，不用改程式碼。跟「加價專區設定」
 * 用同一套 WordPress Settings API 做法，優先權 17，緊接在它（16）之後。
 */
add_action( 'admin_menu', 'ckc_announcement_bar_add_menu', 17 );
function ckc_announcement_bar_add_menu() {
    add_submenu_page(
        'ckc-homepage-builder',
        '公告列設定',
        '公告列設定',
        'manage_options',
        'ckc-announcement-bar',
        'ckc_announcement_bar_page_html'
    );
}

add_action( 'admin_init', 'ckc_announcement_bar_register_settings' );
function ckc_announcement_bar_register_settings() {
    register_setting(
        'ckc_announcement_bar_group',
        'chao_gang_cheng_announcement_bar_settings',
        array(
            'sanitize_callback' => 'ckc_announcement_bar_sanitize',
        )
    );
}

/**
 * 讀取設定，帶預設值（預設值＝原本寫死在 header.php 裡的文字，確保
 * 還沒存過設定的情況下，前台顯示內容跟改版前完全一樣，不會突然消失
 * 或變成空白）。
 */
function chao_gang_cheng_get_announcement_bar_settings() {
    return wp_parse_args(
        get_option( 'chao_gang_cheng_announcement_bar_settings', array() ),
        array(
            'enabled'    => true,
            'text'       => '📣📣📣運費算我的！！！/全館消費滿 $2,000！冷凍宅配、超商取貨免運費。下單後依訂單順序，現貨商品 5 個工作天內出貨。',
            'link_url'   => '',
            'link_blank' => false,
        )
    );
}

/**
 * 文字允許基本排版標籤（例如 <strong> 強調金額），但不允許 script／
 * style 等危險標籤，用 wp_kses_post 處理，跟商品說明欄位的清理方式
 * 一致。連結網址留空＝整條公告列不加超連結（跟改版前一樣，純文字）。
 */
function ckc_announcement_bar_sanitize( $input ) {
    return array(
        'enabled'    => ! empty( $input['enabled'] ),
        'text'       => isset( $input['text'] ) ? wp_kses_post( wp_unslash( $input['text'] ) ) : '',
        'link_url'   => isset( $input['link_url'] ) ? esc_url_raw( wp_unslash( $input['link_url'] ) ) : '',
        'link_blank' => ! empty( $input['link_blank'] ),
    );
}

function ckc_announcement_bar_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( '您沒有權限存取此頁面。' );
    }

    $opts = chao_gang_cheng_get_announcement_bar_settings();
    ?>
    <div class="wrap" id="ckc-announcement-bar-settings">
        <h1 style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:24px;">📣</span>
            公告列設定
        </h1>
        <p style="color:#666;margin-top:4px;">控制網站最上方（Logo 上面那一條）的公告文字要顯示什麼、要不要顯示。設定儲存後立即在前台生效。</p>
        <hr style="margin:20px 0;">

        <?php if ( isset( $_GET['settings-updated'] ) ) : ?>
        <div id="setting-error-settings_updated" class="notice notice-success settings-error is-dismissible">
            <p><strong>✅ 設定已成功儲存！</strong></p>
        </div>
        <?php endif; ?>

        <form method="post" action="options.php" style="max-width:680px;">
            <?php settings_fields( 'ckc_announcement_bar_group' ); ?>

            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:24px 28px;margin-bottom:20px;box-shadow:0 1px 4px rgba(0,0,0,.06);">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;font-weight:600;margin-bottom:18px;">
                    <input type="checkbox"
                           name="chao_gang_cheng_announcement_bar_settings[enabled]"
                           value="1"
                           <?php checked( $opts['enabled'] ); ?>
                           style="width:16px;height:16px;cursor:pointer;">
                    顯示公告列
                </label>
                <p style="margin:0 0 6px;color:#888;font-size:13px;">取消勾選就會整條隱藏，不會佔用版面。</p>
                <hr style="margin:16px 0;border-color:#f0f0f0;">
                <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:6px;">公告文字</label>
                <textarea name="chao_gang_cheng_announcement_bar_settings[text]"
                          rows="3"
                          style="width:100%;max-width:640px;padding:9px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;"><?php echo esc_textarea( $opts['text'] ); ?></textarea>
                <p style="margin:6px 0 0;font-size:12px;color:#aaa;">可以用 &lt;strong&gt;文字&lt;/strong&gt; 讓部分文字加粗；不需要自己加表情符號前綴，想留就留、不想留可以刪掉。</p>

                <hr style="margin:16px 0;border-color:#f0f0f0;">
                <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:6px;">超連結網址（選填）</label>
                <input type="url"
                       name="chao_gang_cheng_announcement_bar_settings[link_url]"
                       value="<?php echo esc_attr( $opts['link_url'] ); ?>"
                       placeholder="https://eshopckc.com/product-category/xxx/"
                       style="width:100%;max-width:640px;padding:9px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;">
                <p style="margin:6px 0 10px;font-size:12px;color:#aaa;">填了網址，整條公告列就會變成可以點擊的連結（例如導去優惠券頁面或特定分類）；留空就是純文字，不能點擊，跟改版前一樣。</p>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;">
                    <input type="checkbox"
                           name="chao_gang_cheng_announcement_bar_settings[link_blank]"
                           value="1"
                           <?php checked( $opts['link_blank'] ); ?>
                           style="width:16px;height:16px;cursor:pointer;">
                    在新分頁開啟連結
                </label>
            </div>

            <div style="display:flex;align-items:center;gap:12px;">
                <?php submit_button( '💾 儲存設定', 'primary large', 'submit', false, array( 'style' => 'height:44px;padding:0 28px;font-size:15px;font-weight:600;border-radius:8px;' ) ); ?>
                <span style="font-size:13px;color:#aaa;">設定儲存後即時生效，無需清除快取</span>
            </div>
        </form>

        <hr style="margin:32px 0 20px;">
        <p style="font-size:12px;color:#bbb;">潮港城客製電商主題 · 公告列設定 · 由 Antigravity AI 建置</p>
    </div>
    <?php
}


// =============================================================================
require_once get_template_directory() . '/includes/core/popup.php';


/**
 * 22. 強制全站所有搜尋請求皆導向 WooCommerce 商品搜尋
 * 如果搜尋網址沒有帶有 post_type=product 參數，會自動重定向補上
 * 這樣能確保不論是直接輸入網址還是第三方連入，都能正確載入商品版面與客製的「查無結果」模板。
 */
add_action( 'template_redirect', 'ckc_redirect_general_search_to_product_search' );
function ckc_redirect_general_search_to_product_search() {
    if ( is_search() && ! is_admin() && isset( $_GET['s'] ) ) {
        if ( ! isset( $_GET['post_type'] ) || $_GET['post_type'] !== 'product' ) {
            wp_safe_redirect( add_query_arg( array( 's' => $_GET['s'], 'post_type' => 'product' ), home_url( '/' ) ) );
            exit;
        }
    }
}



/**
 * 23. 將後台「專案/作品集」自訂文章類型文字改成「文章」
 */
add_action( 'registered_post_type', 'ckc_rename_portfolio_cpt_labels', 10, 2 );
function ckc_rename_portfolio_cpt_labels( $post_type, $args ) {
    if ( in_array( $post_type, array( 'jetpack-portfolio', 'portfolio', 'project' ), true ) ) {
        global $wp_post_types;
        if ( isset( $wp_post_types[ $post_type ] ) ) {
            $labels = &$wp_post_types[ $post_type ]->labels;
            $labels->name               = '文章';
            $labels->singular_name      = '文章';
            $labels->add_new            = '新增文章';
            $labels->add_new_item       = '新增文章項目';
            $labels->edit_item          = '編輯文章';
            $labels->new_item           = '新文章';
            $labels->view_item          = '檢視文章';
            $labels->view_items         = '檢視文章';
            $labels->search_items       = '搜尋文章';
            $labels->not_found          = '找不到文章';
            $labels->not_found_in_trash = '垃圾桶內找不到文章';
            $labels->all_items          = '所有文章';
            $labels->menu_name          = '文章';
            $labels->name_admin_bar     = '文章';
        }
    }
}

add_action( 'admin_menu', 'ckc_rename_portfolio_menu_label', 999 );
function ckc_rename_portfolio_menu_label() {
    global $menu, $submenu;
    foreach ( $menu as $key => $item ) {
        if ( isset( $item[0] ) && ( stripos( $item[0], '專案' ) !== false || stripos( $item[0], 'portfolio' ) !== false || stripos( $item[0], '新聞' ) !== false ) ) {
            $menu[$key][0] = str_ireplace( array( '專案', '新聞' ), '文章', $item[0] );
        }
    }
    foreach ( $submenu as $parent => $items ) {
        foreach ( $items as $key => $item ) {
            if ( isset( $item[0] ) && ( stripos( $item[0], '專案' ) !== false || stripos( $item[0], 'portfolio' ) !== false || stripos( $item[0], '新聞' ) !== false ) ) {
                $submenu[$parent][$key][0] = str_ireplace( array( '專案', '新聞' ), '文章', $item[0] );
            }
        }
    }
}

/**
 * 24. 在後台隱藏並停用預設的「文章 (Posts)」功能
 */
add_action( 'admin_menu', 'ckc_remove_posts_admin_menu', 999 );
function ckc_remove_posts_admin_menu() {
    remove_menu_page( 'edit.php' );
}

add_action( 'wp_before_admin_bar_render', 'ckc_remove_new_post_option_admin_bar', 999 );
function ckc_remove_new_post_option_admin_bar() {
    global $wp_admin_bar;
    $wp_admin_bar->remove_menu( 'new-post' );
}

add_action( 'admin_init', 'ckc_disable_posts_direct_access' );
function ckc_disable_posts_direct_access() {
    global $pagenow;
    if ( ( $pagenow === 'edit.php' || $pagenow === 'post-new.php' ) && ( ! isset( $_GET['post_type'] ) || $_GET['post_type'] === 'post' ) ) {
        wp_safe_redirect( admin_url( '/' ) );
        exit;
    }
}

/**
 * 25. 替換後台左上角圖示與登入頁 Logo 為潮港城 LOGO-方.png
 */
add_action( 'admin_head', 'ckc_custom_admin_logo_styles' );
function ckc_custom_admin_logo_styles() {
    $logo_url = get_template_directory_uri() . '/assets/images/logo-square.png?v=3';
    ?>
    <style>
    /* 1. 替換頂部管理列的 WordPress W 圖示 */
    #wpadminbar #wp-admin-bar-wp-logo > .ab-item .ab-icon:before {
        content: "" !important;
        display: none !important;
    }
    #wpadminbar #wp-admin-bar-wp-logo > .ab-item .ab-icon {
        background-image: url('<?php echo esc_url( $logo_url ); ?>') !important;
        background-size: contain !important;
        background-repeat: no-repeat !important;
        background-position: center !important;
        width: 24px !important;
        height: 24px !important;
        display: inline-block !important;
        margin-top: 4px !important;
    }

    /* 2. 替換區塊編輯器 (Gutenberg) 左上角的回到控制台/站點圖示 */
    .edit-post-header__brand svg,
    .edit-post-header__brand img,
    .edit-post-fullscreen-close-button svg,
    .edit-post-fullscreen-close-button img {
        display: none !important;
    }
    .edit-post-header__brand,
    .edit-post-fullscreen-close-button {
        background-image: url('<?php echo esc_url( $logo_url ); ?>') !important;
        background-size: 42px 42px !important;
        background-repeat: no-repeat !important;
        background-position: center !important;
        background-color: #1e1e1e !important;
        width: 60px !important;
        height: 60px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        border-right: 1px solid #2e2e2e !important;
    }
    </style>
    <?php
}

add_action( 'login_head', 'ckc_custom_login_logo_styles' );
function ckc_custom_login_logo_styles() {
    $logo_url = get_template_directory_uri() . '/assets/images/logo-square.png?v=3';
    ?>
    <style type="text/css">
        #login h1 a, .login h1 a {
            background-image: url('<?php echo esc_url( $logo_url ); ?>') !important;
            height: 100px !important;
            width: 100px !important;
            background-size: contain !important;
            background-repeat: no-repeat !important;
            padding-bottom: 20px !important;
        }
    </style>
    <?php
}

/**
 * 26. 建立「網站功能」自訂父選單，並將指定子項目移至其下
 * 包含：頁面 / 媒體 / 文章 / 外觀 / mydybox TW / 付款 / 外掛 / 工具 / 設定
 */
add_action( 'admin_menu', 'ckc_setup_website_features_menu', 99999 );
function ckc_setup_website_features_menu() {
    global $menu, $submenu;

    // 26a. 新增「網站功能」頂層選單
    add_menu_page(
        '網站功能',                  // 頁面標題
        '網站功能',                  // 選單顯示文字
        'edit_pages',               // 權限要求
        'ckc-website-features',     // 選單 Slug
        'ckc_website_features_page',// 渲染回呼（點擊時自動轉導到第一個子頁面）
        'dashicons-admin-site-alt3',// 圖示
        28                          // 排序位置
    );

    // 26b. 定義需要移動的選單項目關鍵字與其預設 Slug
    $targets = array(
        'page'      => array( 'title' => '頁面', 'slug' => 'edit.php?post_type=page', 'found' => false ),
        'media'     => array( 'title' => '媒體', 'slug' => 'upload.php', 'found' => false ),
        'news'      => array( 'title' => '文章', 'slug' => 'edit.php?post_type=jetpack-portfolio', 'found' => false ),
        'themes'    => array( 'title' => '外觀', 'slug' => 'themes.php', 'found' => false ),
        'mydybox'   => array( 'title' => 'Mydybox TW', 'slug' => 'mydybox-taiwan-for-woocommerce', 'found' => false ),
        'payments'  => array( 'title' => '付款', 'slug' => 'admin.php?page=wc-settings&tab=checkout&from=PAYMENTS_MENU_ITEM', 'found' => false ),
        'plugins'   => array( 'title' => '外掛', 'slug' => 'plugins.php', 'found' => false ),
        'settings'  => array( 'title' => '設定', 'slug' => 'options-general.php', 'found' => false ),
    );

    // 26c. 掃描頂層選單，尋找符合關鍵字的項目並移除
    foreach ( $menu as $pos => $item ) {
        if ( empty( $item[2] ) ) continue;

        $slug  = $item[2];
        $title = isset( $item[0] ) ? wp_strip_all_tags( $item[0] ) : '';
        $cap   = isset( $item[1] ) ? $item[1] : 'edit_pages';

        $matched_key = '';
        if ( $slug === 'edit.php?post_type=page' ) {
            $matched_key = 'page';
        } elseif ( $slug === 'upload.php' ) {
            $matched_key = 'media';
        } elseif ( $slug === 'themes.php' ) {
            $matched_key = 'themes';
        } elseif ( in_array( $slug, array( 'edit.php?post_type=jetpack-portfolio', 'edit.php?post_type=portfolio', 'edit.php?post_type=project' ), true ) || stripos( $title, '新聞' ) !== false || stripos( $title, '文章' ) !== false ) {
            $matched_key = 'news';
        } elseif ( stripos( $slug, 'mydybox' ) !== false || stripos( $title, 'mydybox' ) !== false ) {
            $matched_key = 'mydybox';
        } elseif ( stripos( $slug, 'yotuwp' ) !== false || stripos( $slug, 'yotu' ) !== false || stripos( $title, 'yotuwp' ) !== false ) {
            // YotuWP 外掛自己的頂層選單：不再移入「網站功能」子選單，直接從頂層移除即可
            // （首頁 YouTube 影片摘要模塊已改用自家 RSS 抓取，不再依賴這個外掛的 shortcode）。
            unset( $menu[ $pos ] );
            continue;
        } elseif ( stripos( $slug, 'tab=checkout' ) !== false || stripos( $title, '付款' ) !== false ) {
            $matched_key = 'payments';
        } elseif ( $slug === 'plugins.php' ) {
            $matched_key = 'plugins';
        } elseif ( $slug === 'tools.php' ) {
            // 「工具」不再移入「網站功能」子選單，直接從頂層移除
            unset( $menu[ $pos ] );
            continue;
        } elseif ( $slug === 'options-general.php' ) {
            $matched_key = 'settings';
        }

        if ( $matched_key ) {
            $targets[ $matched_key ]['slug']  = $slug;
            $targets[ $matched_key ]['cap']   = $cap;
            $targets[ $matched_key ]['found'] = true;

            // 從頂層移除
            unset( $menu[ $pos ] );
        }
    }

    // 26d. 將找到的項目（或保底項目）加入到「網站功能」子選單中
    foreach ( $targets as $key => $data ) {
        $slug  = $data['slug'];
        $title = $data['title'];
        $cap   = isset( $data['cap'] ) ? $data['cap'] : 'manage_options';

        add_submenu_page(
            'ckc-website-features',
            $title,
            $title,
            $cap,
            $slug,
            '' // 現有頁面，無需回呼函式
        );
    }

    // 26e. 註冊「出貨AI助理」為獨立頂層選單
    add_menu_page(
        '出貨AI助理',              // 頁面標題
        '出貨AI助理',              // 選單顯示文字
        'edit_pages',               // 權限要求
        'ckc-gemini-agent',         // 選單 Slug
        'ckc_gemini_agent_page',    // 渲染回呼
        'dashicons-businessman',    // 圖示
        54.5                        // 預設位置（電商營運前）
    );

    // 26f. 移除重複的第一個自動產生的同名子選單
    remove_submenu_page( 'ckc-website-features', 'ckc-website-features' );

    // 26g. 重建選單排序，將「出貨AI助理」移至「WooCommerce」前面，作為「電商營運」分類下的第一項
    $new_menu = array();
    $agent_item = null;
    
    // 拔除「出貨AI助理」
    foreach ( $menu as $pos => $item ) {
        if ( isset( $item[2] ) && $item[2] === 'ckc-gemini-agent' ) {
            $agent_item = $item;
            unset( $menu[$pos] );
            break;
        }
    }
    
    // 重組選單
    if ( $agent_item ) {
        foreach ( $menu as $pos => $item ) {
            if ( isset( $item[2] ) && $item[2] === 'woocommerce' ) {
                $new_menu[ strval( floatval( $pos ) - 0.1 ) ] = $agent_item;
            }
            $new_menu[ strval( $pos ) ] = $item;
        }
        $menu = $new_menu;
    }
}

/**
 * 網站功能主選單點擊時的自動轉導回呼
 */
function ckc_website_features_page() {
    wp_safe_redirect( admin_url( 'edit.php?post_type=page' ) );
    exit;
}

/**
 * 26h. 將「分類」（商品分類管理，原本是「商品」選單下的子項目）移至
 * 「首頁」頂層選單（ckc-homepage-builder）底下的子選單「分類管理」。
 * 沿用既有的 edit-tags.php?taxonomy=product_cat&post_type=product 頁面，
 * 不重複建立頁面邏輯，只是換一個入口並移除原本「商品」選單下的子項目。
 *
 * 註冊（新增子選單）跟移除舊入口拆成兩個各自獨立的 admin_menu 回呼：
 * - 新增子選單用優先權 12，跟其他首頁相關子選單一樣走「小數字先執行」
 *   的順序控制，確保子選單列表順序穩定。
 * - 移除「商品」選單下的「分類」子項目則維持原本的優先權 99999（比大部分
 *   外掛都晚），避免其他外掛／WooCommerce 在較晚的 admin_menu 優先權才
 *   重新註冊「分類」子選單，導致移除後又被加回來（實測發現用較早的優先
 *   權時，新增選單會成功，但移除「商品」選單下的「分類」子項目卻不會
 *   生效，懷疑就是這個時序問題）——這兩件事的時序需求互相衝突，所以
 *   分成兩個函式各自用最適合的優先權。
 */
add_action( 'admin_menu', 'ckc_add_product_category_submenu', 12 );
function ckc_add_product_category_submenu() {
    // 注意：一開始直接用 add_menu_page() 的 $menu_slug 帶查詢字串
    // （edit-tags.php?taxonomy=product_cat&post_type=product）指向既有頁面，
    // 但實測發現選單項目完全沒有出現（WordPress 核心 get_plugin_page_hookname()
    // 內部會用 preg_replace('!\.php!','', $menu_slug) 處理 hookname，容易與查詢字串
    // 版本的 slug 互相干擾）。改採本檔案「網站功能」（ckc-website-features）已驗證
    // 可行的做法：建立一個全新的獨立 slug 當作子選單，點擊時用回呼函式
    // wp_safe_redirect() 轉導到真正的分類頁面，行為與 ckc_website_features_page()
    // 一致。
    add_submenu_page(
        'ckc-homepage-builder',
        '分類管理',
        '分類管理',
        'manage_product_terms',
        'ckc-product-categories',
        'ckc_product_categories_redirect_page'
    );
}

add_action( 'admin_menu', 'ckc_remove_product_category_from_products_menu', 99999 );
function ckc_remove_product_category_from_products_menu() {
    // 移除原本「商品」選單下的「分類」子項目，避免與上面新增的
    // 「首頁 > 分類管理」重複入口。
    // 注意：改用直接操作 $submenu 全域變數＋模糊比對（stripos 找含有
    // taxonomy=product_cat 字樣的項目）取代 remove_submenu_page() 精確字串比對，
    // 因為實測發現 remove_submenu_page( 'edit.php?post_type=product', $real_slug )
    // 即使 slug 逐字元比對完全相符，仍然移除不掉（懷疑是子選單陣列裡的字串在
    // 這個環境中帶有肉眼不可見的差異，例如編碼或多餘的隱藏字元），改用模糊比對
    // 更保險，也更不受這類差異影響。
    global $submenu;
    if ( isset( $submenu['edit.php?post_type=product'] ) && is_array( $submenu['edit.php?post_type=product'] ) ) {
        foreach ( $submenu['edit.php?post_type=product'] as $i => $item ) {
            if ( isset( $item[2] ) && false !== stripos( $item[2], 'taxonomy=product_cat' ) ) {
                unset( $submenu['edit.php?post_type=product'][ $i ] );
            }
        }
    }
}

/**
 * 「分類管理」頂層選單點擊時的自動轉導回呼，導向真正的分類編輯頁面。
 */
function ckc_product_categories_redirect_page() {
    wp_safe_redirect( admin_url( 'edit-tags.php?taxonomy=product_cat&post_type=product' ) );
    exit;
}

/**
 * 26h-1. 修正「分類管理」點進去（轉導後）側邊選單不會自動展開「首頁」的問題。
 *
 * 原因跟 ckc_fix_nav_menus_admin_highlight() 是同一種情況：使用者點「分類
 * 管理」後會被轉導到真正的 edit-tags.php?taxonomy=product_cat&post_type=product
 * 頁面，而 WordPress 對「分類法編輯頁」的父選單判斷不是走一般子選單陣列
 * 比對，是直接把它視為所屬文章類型（商品）選單底下的頁面，所以會反白
 * 「商品」而不是「首頁」。同樣用 parent_file／submenu_file 過濾器強制
 * 指定回「首頁 > 分類管理」。
 */
add_filter( 'parent_file', 'ckc_fix_product_categories_admin_highlight', 999999 );
function ckc_fix_product_categories_admin_highlight( $parent_file ) {
    if ( ckc_is_product_categories_screen() ) {
        return 'ckc-homepage-builder';
    }
    return $parent_file;
}
add_filter( 'submenu_file', 'ckc_fix_product_categories_admin_submenu_highlight', 999999 );
function ckc_fix_product_categories_admin_submenu_highlight( $submenu_file ) {
    if ( ckc_is_product_categories_screen() ) {
        return 'ckc-product-categories';
    }
    return $submenu_file;
}
function ckc_is_product_categories_screen() {
    global $pagenow;
    return 'edit-tags.php' === $pagenow
        && isset( $_GET['taxonomy'], $_GET['post_type'] )
        && 'product_cat' === $_GET['taxonomy']
        && 'product' === $_GET['post_type'];
}

/**
 * 26i. 修正商品分類頁面「上層分類」欄位說明文字
 * WordPress 核心預設範例文字沿用影劇類比喻（影集／美劇／日劇），
 * 與本站主要銷售冷凍食品的商品分類邏輯不符，改為貼近實際業務的範例。
 */
add_filter( 'register_taxonomy_args', 'ckc_customize_product_cat_labels', 10, 2 );
function ckc_customize_product_cat_labels( $args, $taxonomy ) {
    if ( 'product_cat' !== $taxonomy ) {
        return $args;
    }
    if ( ! isset( $args['labels'] ) || ! is_array( $args['labels'] ) ) {
        $args['labels'] = array();
    }
    $args['labels']['parent_field_description'] = '指派上層分類以建立階層架構。舉例來說，這個網站可以有個「食品」分類，而其下還有「冷凍食品」及「常溫食品」等子分類。';
    return $args;
}

/**
 * 26j. 翻譯商品分類頁面「Google 商品分類」欄位相關文字（Facebook for WooCommerce 外掛）
 *
 * 這個欄位（含說明文字、提示、彈窗、下拉選單佔位文字）都是透過標準 WordPress
 * __() 函式並帶有 'facebook-for-woocommerce' text domain 輸出，可直接用 gettext
 * 過濾器攔截翻譯。注意：Google 官方商品分類樹本身（如「Apparel & Accessories」
 * 等上千筆分類名稱）是外掛內建的原始 PHP 陣列資料，並非透過 __() 輸出，
 * 不在這個過濾器的處理範圍內。
 */
add_filter( 'gettext', 'ckc_translate_facebook_google_category_strings', 20, 3 );
function ckc_translate_facebook_google_category_strings( $translated, $original, $domain ) {
    if ( 'facebook-for-woocommerce' !== $domain ) {
        return $translated;
    }
    static $map = null;
    if ( null === $map ) {
        $map = array(
            'To optimize ad performance, we recommend providing these additional product attributes in WooCommerce. Updates made here will be overwritten with attributes provided in WooCommerce.' => '為了優化廣告成效，建議在 WooCommerce 中提供這些額外的商品屬性。此處所做的修改，將會被 WooCommerce 中設定的屬性覆蓋。',
            'Default Google product category' => '預設 Google 商品分類',
            'Choose a default Google product category for products in this category. Products need at least two category levels defined for tax to be correctly applied.' => '為此分類下的商品選擇預設的 Google 商品分類。商品至少需要指定兩層分類，稅金才能正確套用。',
            'Category Specific Attributes' => '分類專屬屬性',
            'Select default values for enhanced attributes within this category' => '為此分類選擇加強屬性的預設值',
            'Show more attributes' => '顯示更多屬性',
            'Products and categories that inherit this global setting (i.e. they do not have a specific Google product category set) will use the new default immediately. Are you sure you want to proceed?' => '繼承此全域設定的商品與分類（也就是尚未個別設定 Google 商品分類者），將會立即套用新的預設值。確定要繼續嗎？',
            'Cancel' => '取消',
            'Update default Google product category' => '更新預設 Google 商品分類',
            'Search main categories...' => '搜尋主要分類...',
            'Choose a main category first' => '請先選擇主要分類',
            'Choose a category' => '選擇分類',
        );
    }
    return isset( $map[ $original ] ) ? $map[ $original ] : $translated;
}

/**
 * 26k. 新增「選單管理」子選單，收整到「首頁」頂層選單（ckc-homepage-builder）底下。
 *
 * 背景：WordPress 原生的導覽選單管理頁面（nav-menus.php）預設是「外觀」選單
 * 底下的子項目；但本站的「外觀」已被 ckc_setup_website_features_menu() 收合成
 * 「網站功能」下的單一連結（只指向 themes.php，不含子選單），導致 nav-menus.php
 * 完全沒有側邊選單入口可以點進去（雖然直接輸入網址仍能存取）。
 * 這裡沿用既有頁面本身，不重建邏輯，只是在側邊選單新增一個直接入口。
 * 用 admin_menu 優先權 13 確保排在子選單列表第 4 順位。
 */
add_action( 'admin_menu', 'ckc_add_nav_menu_management_page', 13 );
function ckc_add_nav_menu_management_page() {
    add_submenu_page(
        'ckc-homepage-builder',
        '選單管理',
        '選單管理',
        'edit_theme_options',
        'nav-menus.php'
    );
}

/**
 * 26k-1. 修正「選單管理」點進去後，側邊選單不會自動展開「首頁」的問題。
 *
 * 背景：nav-menus.php 是 WordPress 核心頁面，核心本身早就在
 * $submenu['themes.php'] 註冊過一筆同樣指向 nav-menus.php 的子選單
 * （即原本「外觀 > 選單」）。
 *
 * 一開始用 parent_file／submenu_file 過濾器強制指定回「首頁」，但驗證
 * 後發現沒有生效——追進 WordPress 核心 wp-admin/menu-header.php 原始碼
 * 才發現：這兩個過濾器套用「之後」，核心緊接著會呼叫一次不帶參數的
 * get_admin_page_parent()，這個函式會自己重新掃一遍 $submenu 陣列比對
 * $pagenow，只要找到第一個相符的（核心自己在 themes.php 底下註冊的
 * nav-menus.php，因為註冊時機比我們的「首頁」子選單早，一定先比對到）
 * 就直接覆蓋回 $parent_file，把過濾器剛設好的值蓋掉。
 *
 * 真正的解法是 WordPress 內建、專門處理「把某個父選單整個重新導向到
 * 別的父選單」的機制：$_wp_real_parent_file 這個全域陣列。
 * get_admin_page_parent() 掃描比對前，會先用這個陣列把比對到的父選單
 * slug 置換過一次——只要在 nav-menus.php 這個頁面時，把
 * themes.php 置換成 ckc-homepage-builder，掃描比對到 themes.php 底下的
 * nav-menus.php 時就會直接算成「首頁」底下的項目，不會再蓋掉。
 * 只在 $pagenow 是 nav-menus.php 時才置換，不影響其他仍然使用
 * themes.php 的頁面。
 *
 * 注意：優先權要設得非常晚（99999），不能設早。踩過一次坑——
 * add_submenu_page() 內部「也」會查這個 $_wp_real_parent_file 陣列來
 * 置換 parent slug，如果太早設定，會連帶把 WordPress 核心其他晚一點
 * 才用 add_submenu_page('themes.php', ...) 註冊的子選單（例如「佈景
 * 主題展示區」「字型」「佈景主題檔案編輯器」）一起吃進「首頁」底下，
 * 讓側邊選單多出一堆不相干的項目。設在 admin_menu 的最後（99999）
 * 執行，讓所有註冊都完成後才置換，這樣只會影響 get_admin_page_parent()
 * 在畫面渲染階段的查詢，不會影響任何選單的註冊結果。
 */
add_action( 'admin_menu', 'ckc_fix_nav_menus_admin_highlight', 99999 );
function ckc_fix_nav_menus_admin_highlight() {
    global $pagenow;
    if ( 'nav-menus.php' === $pagenow ) {
        $GLOBALS['_wp_real_parent_file']['themes.php'] = 'ckc-homepage-builder';
    }
}

/**
 * 隱藏「選單管理」（nav-menus.php）畫面裡的「分類」區塊。
 *
 * 這個「分類」區塊是 WordPress 原生的「文章分類」（taxonomy: category），
 * 跟商品完全無關——本站是電商網站，沒有在用文章分類建選單，畫面上
 * 「首頁Banner」這種詞彙看起來就是誤植的文章分類詞彙，容易讓人誤以為
 * 跟「分類管理」（商品分類 product_cat，在畫面更下面另一個獨立區塊
 * 「商品分類」）是同一套資料，造成混淆。只隱藏這個區塊本身，不刪除
 * 底下任何文章分類詞彙資料，也完全不影響「商品分類」區塊。
 */
add_action( 'admin_head-nav-menus.php', 'chao_gang_cheng_hide_post_category_nav_menu_box' );
function chao_gang_cheng_hide_post_category_nav_menu_box() {
    remove_meta_box( 'add-category', 'nav-menus', 'side' );
}

/**
 * 隱藏「選單管理」畫面裡的「專案類型」「專案標籤」兩個區塊。
 *
 * 這兩個是 Jetpack Portfolio（作品集）這個自訂文章類型自帶的分類法
 * （taxonomy slug：jetpack-portfolio-type／jetpack-portfolio-tag），
 * 本站沒有在用作品集的分類/標籤功能建選單，隱藏掉避免選單建置畫面
 * 塞一堆用不到的區塊。已從實際畫面 DOM 確認過 meta box id
 * （add-jetpack-portfolio-type／add-jetpack-portfolio-tag），不是用猜的。
 * 只隱藏區塊本身，不刪除任何詞彙資料。
 */
add_action( 'admin_head-nav-menus.php', 'chao_gang_cheng_hide_portfolio_taxonomy_nav_menu_boxes' );
function chao_gang_cheng_hide_portfolio_taxonomy_nav_menu_boxes() {
    remove_meta_box( 'add-jetpack-portfolio-type', 'nav-menus', 'side' );
    remove_meta_box( 'add-jetpack-portfolio-tag', 'nav-menus', 'side' );
}

/**
 * 把「選單管理」畫面裡 Jetpack Portfolio（作品集）這個自訂文章類型的
 * 區塊標題，從「文章」改成「新聞」。
 *
 * 背景：ckc_rename_portfolio_cpt_labels()（見上面「23. 將後台「專案/
 * 作品集」自訂文章類型文字改成「文章」」那段）把這個自訂文章類型全站
 * labels->name 都改成了「文章」，方便編輯者日常操作時把它當「文章」
 * 使用；但 WordPress 原生真正的「文章 (Posts)」post type 本身仍然存在
 * （只是後台選單被隱藏，見 ckc_remove_posts_admin_menu()），選單建置
 * 畫面的「新增選單項目」清單是直接列出所有 show_in_nav_menus 的文章
 * 類型，不受選單隱藏影響，所以會同時看到兩個一模一樣的「文章」區塊，
 * 分不出哪個才是實際在用的內容。
 *
 * 這裡只針對「選單管理」這一個畫面用 nav_menu_meta_box_object 這個
 * filter，把 Jetpack Portfolio 這個區塊的標題改顯示為「新聞」，跟另一個
 * 真正的（但沒在用的）「文章」區塊區分開來。特別注意：這個 filter 拿到
 * 的 \$object 是全站共用、跟 \$wp_post_types 裡註冊物件同一份參照，如果
 * 直接改它的 labels->name，會連帶把全站其他地方（選單列、管理列等）
 * 也一起改掉，跟上面 ckc_rename_portfolio_cpt_labels() 打架；所以這裡
 * 先 clone 一份（包含巢狀的 labels 物件也要另外 clone），只影響這個
 * filter 回傳出去的這一份，不動到全站共用的註冊物件本身。
 */
add_filter( 'nav_menu_meta_box_object', 'chao_gang_cheng_relabel_portfolio_nav_menu_box' );
function chao_gang_cheng_relabel_portfolio_nav_menu_box( $object ) {
    if ( isset( $object->name ) && 'jetpack-portfolio' === $object->name ) {
        $object          = clone $object;
        $object->labels  = clone $object->labels;
        $object->labels->name = '新聞';
    }
    return $object;
}

/**
 * 讓「全部商品」這個商品分類（代稱 allitem）瀏覽頁真的顯示「全部商品」，
 * 而不是照字面上的分類邏輯只顯示「有被指派到這個分類」的商品。
 *
 * 背景：allitem 其實是這個網站的商品分類「未分類」預設分類被改名、
 * 改代稱而來（後台分類列表看得到「設成預設」，代表它是 WooCommerce
 * 的預設分類），本質上代表「沒有被指派任何實際分類的商品」，目前
 * 項目數量顯示 0，因為現有商品都已經各自被分類到冷凍食品／下酒菜／
 * 節慶禮盒，沒有商品落在這個預設分類底下。
 *
 * 這裡不採取「把每件商品都手動也指派一次 allitem 分類」的做法——那樣
 * 除了要處理現有全部商品，以後每上架一件新商品都要記得再指派一次，
 * 很容易漏掉；而且會讓這件商品同時掛兩個分類，可能連帶影響其他地方
 * 依分類數量／分類篩選的邏輯（例如分類下拉選單的商品數量統計）。
 * 改成在瀏覽這個分類頁面時，直接把分類限制拿掉、當作「顯示所有商品」
 * 的頁面來處理，效果上等於商店首頁，但維持在分類頁的網址／麵包屑，
 * 之後不管新增多少商品都不用額外維護。
 *
 * 用 pre_get_posts 優先權 20，確保排在 WooCommerce 自己的
 * WC_Query::product_query()（預設優先權 10，會依網址判斷的分類把
 * tax_query 加上去）之後執行，這裡才能把它清空、真正生效。
 */
add_action( 'pre_get_posts', 'chao_gang_cheng_allitem_category_shows_all_products', 20 );
function chao_gang_cheng_allitem_category_shows_all_products( $query ) {
    if ( is_admin() || ! $query->is_main_query() ) {
        return;
    }
    if ( ! is_tax( 'product_cat', 'allitem' ) ) {
        return;
    }

    // 注意：一開始直接把整個 tax_query 清空（設成空陣列），結果「共 N 項」
    // 的統計數字（分頁用的 found_posts）從原本正確的 4 件變成 21 件，但
    // 實際畫面渲染出來的商品卡片仍然只有 4 件——代表 WC_Query::product_query()
    // 本來就會把「排除隱藏／目錄搜尋排除商品」（product_visibility 這個
    // taxonomy 的條件）跟「這個分類本身」的條件合併放在同一個 tax_query
    // 陣列裡；整個清空等於連目錄可見性的排除條件也一起拿掉了，導致 SQL
    // 查詢本身多算進了不該出現在目錄的商品（前台清單另外有 is_visible()
    // 判斷才把它們擋掉沒顯示出來，但分頁計數已經是錯的）。
    // 修正：只挑出 taxonomy 是 product_cat 的那個子句移除，其餘（例如
    // product_visibility）維持不動，這樣才不會連目錄可見性的過濾一起拿掉。
    $tax_query = $query->get( 'tax_query' );
    if ( is_array( $tax_query ) ) {
        foreach ( $tax_query as $key => $clause ) {
            if ( is_array( $clause ) && isset( $clause['taxonomy'] ) && 'product_cat' === $clause['taxonomy'] ) {
                unset( $tax_query[ $key ] );
            }
        }
        $query->set( 'tax_query', array_values( $tax_query ) );
    }
    $query->set( 'product_cat', '' );
}

/**
 * 修正「全部商品」分類頁的「共 N 項」計數文字。
 *
 * 背景：上面 chao_gang_cheng_allitem_category_shows_all_products() 已經讓
 * 這個分類頁的商品「卡片」正確顯示全站 4 件已發佈商品（實際測試過標題
 * 完全對應，沒有多餘或重複）。但頁面上方「共 N 項」這段文字（以及背後
 * 用來算分頁頁數的數字）是另外由 $wp_query->found_posts 算出來的，實測
 * 發現這個數字异常變成 21（透過除錯輸出直接確認過：全站 wp_count_posts()
 * 的 publish 數量明明是 4，$wpdb 直接查詢也是 4，但 $wp_query->found_posts
 * 卻是 21，且找不到對應的 SQL／篩選器來源，判斷是查詢快取層級的異常，
 * 不是我們自己 tax_query 邏輯的問題）。
 *
 * 為了不管背後根本原因為何，都能保證頁面顯示的數字正確，這裡直接在
 * woocommerce_before_shop_loop（優先權 15，wc_setup_loop 預設優先權 10 之後、
 * woocommerce_result_count 預設優先權 20 之前）用一次獨立、直接的資料庫
 * 查詢重新計算真正的商品總數，強制覆蓋 wc_get_loop_prop('total') 與
 * 'total_pages'，讓「共 N 項」與分頁頁數都反映真實數字。
 */
add_action( 'woocommerce_before_shop_loop', 'chao_gang_cheng_fix_allitem_result_count', 15 );
function chao_gang_cheng_fix_allitem_result_count() {
    if ( is_admin() || ! is_tax( 'product_cat', 'allitem' ) ) {
        return;
    }
    global $wpdb;

    $sql = "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p WHERE p.post_type = 'product' AND p.post_status = 'publish'";

    if ( function_exists( 'wc_get_product_visibility_term_ids' ) ) {
        $visibility_terms = wc_get_product_visibility_term_ids();
        $exclude_id       = isset( $visibility_terms['exclude-from-catalog'] ) ? (int) $visibility_terms['exclude-from-catalog'] : 0;
        if ( $exclude_id ) {
            $sql .= $wpdb->prepare(
                " AND p.ID NOT IN ( SELECT tr.object_id FROM {$wpdb->term_relationships} tr WHERE tr.term_taxonomy_id = %d )",
                $exclude_id
            );
        }
    }

    $real_total = (int) $wpdb->get_var( $sql );

    wc_set_loop_prop( 'total', $real_total );

    $per_page = (int) wc_get_loop_prop( 'per_page' );
    if ( $per_page > 0 ) {
        wc_set_loop_prop( 'total_pages', (int) ceil( $real_total / $per_page ) );
    }
}


/**
 * 27. 出貨AI助理頁面渲染回呼
 */
function ckc_gemini_agent_page() {
    $api_key = get_option( 'ckc_gemini_api_key', '' );
    ?>
    <div class="wrap ckc-gemini-wrap">
        <h1 class="wp-heading-inline">出貨AI助理</h1>
        <hr class="wp-header-end">

        <div class="gemini-container">
            <!-- 左側設定與快捷操作區 -->
            <div class="gemini-sidebar">
                <!-- API 設定區 -->
                <div class="gemini-card">
                    <h3>🔑 金鑰設定</h3>
                    <p style="color: #64748b; font-size: 12px; margin-bottom: 12px;">使用真實 Gemini API 對話請先填寫金鑰，若無填寫將提供預設模擬對話環境。</p>
                    <div class="api-key-input-group">
                        <input type="password" id="gemini-api-key" placeholder="輸入 Gemini API 金鑰" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text">
                        <button type="button" id="save-gemini-key" class="button button-primary">儲存</button>
                    </div>
                    <span class="save-status-msg" style="display: block; margin-top: 8px; font-size: 12px;"></span>
                </div>


                <!-- 出貨與庫存作業 (Agent 功能) -->
                <div class="gemini-card">
                    <h3>出貨與庫存作業 (Agent 功能)</h3>
                    <p style="color: #64748b; font-size: 12px; margin-bottom: 12px;">點擊直接讀取 WooCommerce 資料庫，或對訂單執行狀態更新：</p>
                    <div class="quick-prompts-list">
                        <button class="quick-prompt-btn" data-prompt="請幫我統計目前所有「處理中」訂單中的商品總量，合併計算並產生今日的「配貨與撿貨清單」，以便我到倉庫備貨。">📋 產生今日出貨「配貨與撿貨清單」</button>
                        <button class="quick-prompt-btn" data-prompt="請幫我產生所有「處理中」訂單的物流宅配名冊，包含訂單號、收件人、電話、地址與商品，以便我匯入物流系統。">🚚 匯出今日待出貨「物流宅配名冊」</button>
                        <button class="quick-prompt-btn" data-prompt="請幫我執行批次自動化出貨：一鍵將目前系統中所有狀態為「處理中」的訂單更新為「已出貨」狀態。">🤖 動作：一鍵批次自動化出貨</button>
                        <button class="quick-prompt-btn" data-prompt="請幫我將黑貓託運單號 【請貼上託運單號】 填入訂單 #【請輸入訂單號】，並通知客戶。">🐈‍⬛ 動作：填入黑貓託運單號（單筆）</button>
                        <button class="quick-prompt-btn" data-prompt="請幫我批次匯入以下黑貓託運單號清單（每行一組：訂單號 託運單號）：&#10;【請在此貼上黑貓契客系統匯出的清單，例如：&#10;#265 9031234567890&#10;#271 9031234567891】">動作：批次匯入黑貓託運單號</button>
                        <button class="quick-prompt-btn" data-prompt="請幫我自動抓取所有已填託運單號訂單的黑貓貨運狀態，並回填至後台訂單紀錄。">📡 動作：自動抓取黑貓貨態回填後台</button>
                        <button class="quick-prompt-btn" data-prompt="請給我推薦分潤報表：推薦訂單數、推薦營收、已發放點數與 Top 推薦人。">🤝 查詢推薦分潤報表</button>
                        <button class="quick-prompt-btn" data-prompt="請產生夥伴分潤對帳單，包含每位夥伴的待確認、可出金、已出金金額與稅務試算。">💰 產生夥伴分潤對帳單</button>
                        <button class="quick-prompt-btn" data-prompt="請標記會員 ID 【請輸入會員ID】 的可出金分潤為已出金。">✅ 動作：標記夥伴分潤已出金</button>
                        <button class="quick-prompt-btn" data-prompt="請核准會員 ID 【請輸入會員ID】 成為推廣夥伴，費率 8%。">🌟 動作：核准推廣夥伴申請</button>
                        <button class="quick-prompt-btn" data-prompt="請幫我執行自動化每日營運檢查：1. 確認是否有付款超過 24 小時卻未出貨的延遲訂單；2. 列出目前零庫存或負庫存的警告商品；3. 提供補貨建議。">⚠️ 執行每日庫存與出貨「自動化健康檢查」</button>
                        <button class="quick-prompt-btn" data-prompt="請幫我查詢最新的一筆處理中訂單的詳細資訊，包含收件人、電話、地址與商品清單。">🔍 查詢特定訂單狀況</button>
                        <button class="quick-prompt-btn" data-prompt="請幫我搜尋收件人是「王小明」的訂單記錄與目前的配送狀態。">👤 搜尋收件人訂單記錄</button>
                        <button class="quick-prompt-btn" data-prompt="請幫我更新訂單 #10246 的狀態為已出貨。">✏️ 動作：將訂單標記為已出貨</button>
                        <button class="quick-prompt-btn" data-prompt="請幫我將商品「潮港城一斤肉牛肉爐」的庫存數量更新為 50 件。">動作：更新特定商品庫存</button>
                        <button class="quick-prompt-btn" data-prompt="請幫我統計今日與本月的出貨數量、訂單總額，以及暢銷商品排行與庫存周轉警告。">📈 統計今日/本月銷售與出貨概況</button>
                    </div>
                </div>
            </div>

            <!-- 右側對話視窗 -->
            <div class="gemini-chat-area">
                <div class="chat-header">
                    <div class="chat-header-info">
                        <span class="bot-avatar">🤖</span>
                        <div>
                            <h4>出貨AI助理</h4>
                            <span class="status-indicator <?php echo $api_key ? 'active' : 'sandbox'; ?>">
                                <?php echo $api_key ? '● 連線中' : '● 模擬測試模式'; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- 對話泡泡區 -->
                <div class="chat-messages-container" id="chat-messages-box">
                    <div class="message-bubble bot-message">
                        <div class="bubble-content">
                            哈囉！我是您的出貨AI助理 🤖 今日有什麼我可以幫您的？您可以點選左側「出貨與庫存作業」中的項目讓我為您代勞！
                        </div>
                    </div>
                </div>

                <!-- 輸入區 -->
                <div class="chat-input-container">
                    <textarea id="chat-user-input" rows="2" placeholder="請輸入問題或點選左側小幫手... (按 Enter 送出)"></textarea>
                    <button type="button" id="chat-send-btn" class="button button-primary">
                        <span>傳送</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 4px; vertical-align: middle;"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
    .ckc-gemini-wrap {
        margin: 20px 20px 0 0 !important;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    }
    .gemini-container {
        display: grid;
        grid-template-columns: 320px 1fr;
        gap: 20px;
        margin-top: 20px;
        align-items: start;
    }
    .gemini-sidebar {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    .gemini-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.02);
    }
    .gemini-card h3 {
        margin-top: 0;
        margin-bottom: 8px;
        font-size: 15px;
        font-weight: 600;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .api-key-input-group {
        display: flex;
        gap: 8px;
    }
    .api-key-input-group input {
        flex: 1;
        height: 32px !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 6px !important;
        padding: 4px 10px !important;
        font-size: 13px !important;
    }
    .quick-prompts-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .quick-prompt-btn {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 10px 12px;
        text-align: left;
        font-size: 13px;
        color: #334155;
        cursor: pointer;
        transition: all 0.2s ease;
        line-height: 1.4;
    }
    .quick-prompt-btn:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
        color: var(--primary-color, #2271b1);
        transform: translateY(-1px);
    }
    .gemini-chat-area {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        height: 620px;
        display: flex;
        flex-direction: column;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.02);
        overflow: hidden;
    }
    .chat-header {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 15px 20px;
    }
    .chat-header-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .bot-avatar {
        font-size: 24px;
        background: #e2e8f0;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
    }
    .chat-header-info h4 {
        margin: 0 0 2px 0;
        font-size: 15px;
        font-weight: 600;
        color: #1e293b;
    }
    .status-indicator {
        font-size: 11px;
        font-weight: 500;
    }
    .status-indicator.active {
        color: #10b981;
    }
    .status-indicator.sandbox {
        color: #f59e0b;
    }
    .chat-messages-container {
        flex: 1;
        padding: 20px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 15px;
        background: #fafafa;
    }
    .message-bubble {
        max-width: 80%;
        display: flex;
        flex-direction: column;
    }
    .message-bubble.bot-message {
        align-self: flex-start;
    }
    .message-bubble.user-message {
        align-self: flex-end;
    }
    .bubble-content {
        padding: 12px 16px;
        border-radius: 12px;
        font-size: 14px;
        line-height: 1.5;
        word-break: break-word;
        white-space: pre-wrap;
    }
    .bot-message .bubble-content {
        background: #ffffff;
        color: #1e293b;
        border: 1px solid #e2e8f0;
        border-top-left-radius: 2px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.02);
    }
    .user-message .bubble-content {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: #ffffff;
        border-top-right-radius: 2px;
        box-shadow: 0 4px 10px rgba(37,99,235,0.2);
    }
    .chat-input-container {
        border-top: 1px solid #e2e8f0;
        padding: 15px 20px;
        display: flex;
        gap: 12px;
        align-items: center;
        background: #ffffff;
    }
    .chat-input-container textarea {
        flex: 1;
        border: 1px solid #cbd5e1 !important;
        border-radius: 8px !important;
        padding: 10px 12px !important;
        font-size: 14px !important;
        resize: none !important;
        outline: none !important;
        box-shadow: none !important;
        height: auto !important;
    }
    .chat-input-container textarea:focus {
        border-color: #2563eb !important;
    }
    #chat-send-btn {
        height: 42px !important;
        padding: 0 18px !important;
        font-size: 14px !important;
        font-weight: 500 !important;
        border-radius: 8px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        background: #2563eb !important;
        border-color: #2563eb !important;
    }
    #chat-send-btn:hover {
        background: #1d4ed8 !important;
        border-color: #1d4ed8 !important;
    }
    .typing-indicator {
        display: flex;
        gap: 4px;
        padding: 8px 12px;
    }
    .typing-indicator span {
        width: 6px;
        height: 6px;
        background: #94a3b8;
        border-radius: 50%;
        animation: typing 1.4s infinite ease-in-out both;
    }
    .typing-indicator span:nth-child(1) { animation-delay: -0.32s; }
    .typing-indicator span:nth-child(2) { animation-delay: -0.16s; }
    @keyframes typing {
        0%, 80%, 100% { transform: scale(0); }
        40% { transform: scale(1); }
    }
    </style>

    <script>
    jQuery(document).ready(function($) {
        var $messagesBox = $('#chat-messages-box');
        var $userInput = $('#chat-user-input');
        var $sendBtn = $('#chat-send-btn');

        // Scroll to bottom
        function scrollToBottom() {
            $messagesBox.scrollTop($messagesBox[0].scrollHeight);
        }

        // Save API Key
        $('#save-gemini-key').on('click', function() {
            var apiKey = $('#gemini-api-key').val().trim();
            var $status = $('.save-status-msg');
            $status.text('儲存中...').css('color', '#64748b');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ckc_save_gemini_key',
                    api_key: apiKey
                },
                success: function(response) {
                    if (response.success) {
                        $status.text(response.data.message).css('color', '#10b981');
                        setTimeout(function() {
                            location.reload();
                        }, 800);
                    } else {
                        $status.text(response.data.message).css('color', '#ef4444');
                    }
                },
                error: function() {
                    $status.text('連線錯誤，請重試。').css('color', '#ef4444');
                }
            });
        });

        // Send Message Function
        function sendMessage(text) {
            if (!text) return;
            
            // Render user bubble
            var userHtml = '<div class="message-bubble user-message"><div class="bubble-content">' + $('<div>').text(text).html() + '</div></div>';
            $messagesBox.append(userHtml);
            $userInput.val('');
            scrollToBottom();

            // Disable inputs
            $userInput.prop('disabled', true);
            $sendBtn.prop('disabled', true);

            // Render typing indicator
            var typingHtml = '<div class="message-bubble bot-message temp-typing"><div class="bubble-content"><div class="typing-indicator"><span></span><span></span><span></span></div></div></div>';
            $messagesBox.append(typingHtml);
            scrollToBottom();

            // Call AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ckc_gemini_chat',
                    message: text
                },
                success: function(response) {
                    $('.temp-typing').remove();
                    $userInput.prop('disabled', false).focus();
                    $sendBtn.prop('disabled', false);

                    if (response.success) {
                        var botHtml = '<div class="message-bubble bot-message"><div class="bubble-content">' + response.data.reply + '</div></div>';
                        $messagesBox.append(botHtml);
                    } else {
                        var botHtml = '<div class="message-bubble bot-message"><div class="bubble-content" style="color: #ef4444;">❌ ' + response.data.message + '</div></div>';
                        $messagesBox.append(botHtml);
                    }
                    scrollToBottom();
                },
                error: function() {
                    $('.temp-typing').remove();
                    $userInput.prop('disabled', false).focus();
                    $sendBtn.prop('disabled', false);
                    var botHtml = '<div class="message-bubble bot-message"><div class="bubble-content" style="color: #ef4444;">❌ 系統連線發生問題，請稍後再試。</div></div>';
                    $messagesBox.append(botHtml);
                    scrollToBottom();
                }
            });
        }

        // Click Send button
        $sendBtn.on('click', function() {
            sendMessage($userInput.val().trim());
        });

        // Press Enter to send (Shift+Enter for new line)
        $userInput.on('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage($userInput.val().trim());
            }
        });

        // Quick prompt click - insert into input field instead of sending immediately
        $('.quick-prompt-btn').on('click', function() {
            var prompt = $(this).data('prompt');
            $userInput.val(prompt).focus();
        });

        scrollToBottom();
    });
    </script>
    <?php
}

/**
 * 28. AJAX 儲存 Gemini API 金鑰
 */
add_action( 'wp_ajax_ckc_save_gemini_key', 'ckc_ajax_save_gemini_key' );
function ckc_ajax_save_gemini_key() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => '權限不足' ) );
    }
    
    $api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( $_POST['api_key'] ) : '';
    update_option( 'ckc_gemini_api_key', $api_key );
    wp_send_json_success( array( 'message' => '金鑰儲存成功！' ) );
}

/**
 * 28x. 黑貓宅急便 (T-cat) 整合輔助函式：託運單號填入、貨態抓取、後台欄位
 */

// 將黑貓託運單號填入訂單：寫入 meta、加上客戶可見備註（會寄通知信給客戶）
function ckc_tcat_fill_tracking( $order_id, $tracking_no ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return false;
    }
    $tracking_no = preg_replace( '/[^0-9\-]/', '', $tracking_no );
    $order->update_meta_data( '_tcat_tracking_number', $tracking_no );
    $order->save();
    $order->add_order_note(
        sprintf( '您的包裹已交由黑貓宅急便配送，託運單號：%s。可至黑貓宅急便官網查詢貨態：https://www.t-cat.com.tw/inquire/trace.aspx', $tracking_no ),
        1 // customer note: 顯示於客戶訂單頁並寄送通知信
    );
    return true;
}

// 抓取黑貓官網貨態（公開查詢頁），回傳最新狀態文字；抓不到時回傳空字串
function ckc_tcat_fetch_status( $tracking_no ) {
    $tracking_no = preg_replace( '/[^0-9]/', '', $tracking_no );
    if ( '' === $tracking_no ) {
        return '';
    }
    $url = 'https://www.t-cat.com.tw/inquire/TraceDetail.aspx?BillID=' . rawurlencode( $tracking_no ) . '&ReturnUrl=Trace.aspx';
    $response = wp_remote_get( $url, array(
        'timeout'    => 12,
        'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
    ) );
    if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
        return '';
    }
    $body = wp_remote_retrieve_body( $response );

    // 已知貨態關鍵字（依黑貓查詢頁常見狀態）
    $known_statuses = array( '順利送達', '配送中', '配達中', '已集貨', '轉運中', '暫置營業所', '不在家', '調查處理中', '已取消', '取件中' );
    if ( preg_match_all( '/<tr[^>]*>(.*?)<\/tr>/su', $body, $rows ) ) {
        foreach ( $rows[1] as $row_html ) {
            $cells = array();
            if ( preg_match_all( '/<t[dh][^>]*>(.*?)<\/t[dh]>/su', $row_html, $cell_matches ) ) {
                foreach ( $cell_matches[1] as $cell ) {
                    $cells[] = trim( wp_strip_all_tags( $cell ) );
                }
            }
            foreach ( $cells as $idx => $cell_text ) {
                foreach ( $known_statuses as $status ) {
                    if ( false !== mb_strpos( $cell_text, $status ) ) {
                        // 嘗試帶上相鄰欄位的日期時間資訊
                        $extra = array();
                        foreach ( $cells as $j => $other ) {
                            if ( $j !== $idx && preg_match( '/\d{2,4}[\/\-]\d{1,2}[\/\-]\d{1,2}/', $other ) ) {
                                $extra[] = $other;
                            }
                        }
                        return $cell_text . ( $extra ? '（' . implode( ' ', array_slice( $extra, 0, 1 ) ) . '）' : '' );
                    }
                }
            }
        }
    }
    return '';
}

// 後台訂單列表新增「黑貓託運單號」欄位（傳統列表與 HPOS 皆支援）
add_filter( 'manage_edit-shop_order_columns', 'ckc_tcat_admin_order_column', 20 );
add_filter( 'manage_woocommerce_page_wc-orders_columns', 'ckc_tcat_admin_order_column', 20 );
function ckc_tcat_admin_order_column( $columns ) {
    $new = array();
    foreach ( $columns as $key => $label ) {
        $new[ $key ] = $label;
        if ( 'order_status' === $key ) {
            $new['tcat_tracking'] = '黑貓託運單號';
        }
    }
    if ( ! isset( $new['tcat_tracking'] ) ) {
        $new['tcat_tracking'] = '黑貓託運單號';
    }
    return $new;
}

add_action( 'manage_shop_order_posts_custom_column', 'ckc_tcat_admin_order_column_content', 20, 2 );
add_action( 'manage_woocommerce_page_wc-orders_custom_column', 'ckc_tcat_admin_order_column_content', 20, 2 );
function ckc_tcat_admin_order_column_content( $column, $order_or_id ) {
    if ( 'tcat_tracking' !== $column ) {
        return;
    }
    $order = is_object( $order_or_id ) ? $order_or_id : wc_get_order( $order_or_id );
    if ( ! $order ) {
        return;
    }
    $tracking = $order->get_meta( '_tcat_tracking_number' );
    if ( $tracking ) {
        $status = $order->get_meta( '_tcat_last_status' );
        echo '<a href="https://www.t-cat.com.tw/inquire/trace.aspx" target="_blank" title="前往黑貓官網查詢貨態">' . esc_html( $tracking ) . '</a>';
        if ( $status ) {
            echo '<br><span style="color:#2271b1;font-size:12px;">' . esc_html( $status ) . '</span>';
        }
    } else {
        echo '<span style="color:#bbb;">—</span>';
    }
}

require_once get_template_directory() . '/includes/api/agent-actions.php';

/**
 * 29. 翻譯結帳與紅利點數外掛字串 (Checkout & Points and Rewards)
 */
add_filter( 'gettext', 'ckc_translate_points_and_rewards_strings', 20, 3 );
function ckc_translate_points_and_rewards_strings( $translated_text, $text, $domain ) {
    // 1. 翻譯 WooCommerce 優惠券折價券相關英文字串
    if ( 'Have a coupon?' === $text ) {
        return '有折價券嗎？';
    }
    if ( 'Click here to enter your code' === $text ) {
        return '點此輸入折扣碼';
    }
    if ( 'If you have a coupon code, please apply it below.' === $text ) {
        return '如果您有折價券，請在下方輸入。';
    }
    if ( 'Apply coupon' === $text ) {
        return '使用優惠券';
    }

    // 2. 翻譯紅利點數外掛字串 (不檢查 text domain 以防第三方外掛名稱不一致)
    switch ( $text ) {
        case 'Apply Points':
            return '折抵紅利';
        case 'Your available points:':
            return '您的可用紅利點數：';
        case 'Your available points':
            return '您的可用紅利點數';
        case 'Points':
            return '紅利點數';
        case 'Points =':
            return '點數折抵：';
        case '%s Points':
            return '%s 點';
        case '%s Point':
            return '%s 點';
        case '%s Points = %s':
            return '%s 點 = %s';
        case '%s Point = %s':
            return '%s 點 = %s';
        case '%1$s Points = %2$s':
            return '%1$s 點 = %2$s';
        case '%1$s Point = %2$s':
            return '%1$s 點 = %2$s';
        case 'Cart Discount':
            return '紅利折抵';
        case '[Remove]':
            return '移除';
        case 'Remove':
            return '移除';
    }
    return $translated_text;
}

/**
 * 29b. Add "Apply All Points" (一鍵全部折抵) button on Cart and Checkout pages
 */
add_action( 'wp_footer', 'chao_gang_cheng_points_apply_all_script' );
function chao_gang_cheng_points_apply_all_script() {
    if ( ! ( is_cart() || is_checkout() ) ) {
        return;
    }
    ?>
    <style>
    /* Styling for Coupon and loyalty points inputs */
    #coupon_code,
    #wps_cart_points {
        height: 42px !important;
        line-height: 42px !important;
        padding: 0 20px !important;
        font-size: 15px !important;
        font-weight: 500 !important;
        border: 1px solid #d1d5db !important;
        border-radius: 30px !important;
        background-color: #fff !important;
        color: #333 !important;
        box-sizing: border-box !important;
        display: inline-block !important;
        vertical-align: middle !important;
        transition: all 0.2s ease-in-out !important;
        text-align: left !important;
    }

    #coupon_code::placeholder,
    #wps_cart_points::placeholder {
        color: #9ca3af !important;
        font-size: 15px !important;
    }

    /* Hide Spinners for Points Input to make it clean and look like a text input */
    #wps_cart_points::-webkit-outer-spin-button,
    #wps_cart_points::-webkit-inner-spin-button {
        -webkit-appearance: none !important;
        margin: 0 !important;
    }
    #wps_cart_points {
        -moz-appearance: textfield !important;
    }

    /* Hover and Focus states */
    #coupon_code:hover,
    #wps_cart_points:hover {
        border-color: #9ca3af !important;
    }

    #coupon_code:focus,
    #wps_cart_points:focus {
        border-color: #7c6767 !important; /* Matches --secondary-color */
        box-shadow: 0 0 0 3px rgba(124, 103, 103, 0.15) !important;
        outline: none !important;
    }

    /* Keep all buttons aligned and sized correctly */
    .coupon button[name="apply_coupon"],
    #wps_cart_points_apply,
    #wps_cart_points_apply_all {
        height: 42px !important;
        line-height: 42px !important; /* Ensure vertical alignment of text in buttons */
        padding: 0 24px !important;
        font-size: 15px !important;
        vertical-align: middle !important;
        display: inline-block !important;
        box-sizing: border-box !important;
    }

    #wps_cart_points_apply_all {
        background-color: #6b7280 !important;
        color: #fff !important;
        border: none !important;
        border-radius: 30px !important; /* Changed from 20px to 30px to match standard pill buttons */
        font-weight: 600 !important;
        cursor: pointer !important;
        transition: all 0.2s ease-in-out !important;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05) !important;
    }
    #wps_cart_points_apply_all:hover {
        background-color: #4b5563 !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1) !important;
    }

    /* Style for "Click here to enter your code" (showcoupon link) to make it look like a button */
    .woocommerce-info a.showcoupon {
        display: inline-block !important;
        background-color: #7c6767 !important; /* Matches var(--secondary-color) */
        color: #fff !important;
        padding: 6px 18px !important;
        border-radius: 30px !important;
        font-size: 14px !important;
        font-weight: 500 !important;
        text-decoration: none !important;
        margin-left: 8px !important;
        transition: all 0.2s ease-in-out !important;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05) !important;
        vertical-align: middle !important;
        border: none !important;
    }
    
    .woocommerce-info a.showcoupon:hover {
        background-color: #f86f69 !important; /* Matches var(--accent-color) */
        color: #fff !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1) !important;
    }

    .woocommerce-info {
        line-height: 2 !important; /* Give more height to center the button vertically */
    }
    
    /* Desktop layout */
    @media (min-width: 769px) {
        #coupon_code,
        #wps_cart_points {
            width: 220px !important; /* Make inputs wider on desktop */
            margin-right: 12px !important;
        }

        .coupon button[name="apply_coupon"],
        #wps_cart_points_apply {
            margin-right: 12px !important;
        }

        #wps_cart_points_apply_all {
            margin-left: 0 !important; /* Removed default margin left since we space buttons explicitly */
            margin-top: 0 !important;
        }
    }
    
    /* Mobile layout */
    @media (max-width: 768px) {
        .woocommerce-info {
            text-align: center !important;
            line-height: 1.8 !important;
        }
        .woocommerce-info a.showcoupon {
            margin-left: 0 !important;
            margin-top: 8px !important;
            display: block !important;
            width: fit-content !important;
            margin-right: auto !important;
            margin-left: auto !important;
        }

        #coupon_code,
        #wps_cart_points {
            width: 100% !important;
            margin-bottom: 12px !important;
            text-align: center !important;
        }
        
        .coupon button[name="apply_coupon"],
        #wps_cart_points_apply {
            width: 100% !important;
            margin-bottom: 12px !important;
            display: block !important;
        }

        #wps_cart_points_apply_all {
            margin-top: 0 !important;
            margin-left: 0 !important;
            width: 100% !important;
            display: block !important;
            padding: 0 24px !important;
        }
    }
    </style>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        function initApplyAllPointsBtn() {
            if ($('#wps_cart_points').length && $('#wps_cart_points_apply_all').length === 0) {
                var $applyBtn = $('#wps_cart_points_apply');
                if ($applyBtn.length) {
                    var applyAllHtml = '<button type="button" class="button" id="wps_cart_points_apply_all">一鍵全部折抵</button>';
                    $applyBtn.after(applyAllHtml);
                }
            }
        }

        // Initialize on load
        initApplyAllPointsBtn();

        // Re-initialize on checkout update/AJAX refreshes
        $(document.body).on('updated_checkout updated_cart_totals', function() {
            initApplyAllPointsBtn();
        });

        // Click handler
        $(document).on('click', '#wps_cart_points_apply_all', function(e) {
            e.preventDefault();
            var totalPoints = 0;
            if (typeof wps_wpr !== 'undefined' && wps_wpr.wps_user_current_points) {
                totalPoints = parseInt(wps_wpr.wps_user_current_points.trim()) || 0;
            }
            if (totalPoints > 0) {
                $('#wps_cart_points').val(totalPoints);
                $('#wps_cart_points_apply').trigger('click');
            } else {
                alert('您目前沒有可折抵的紅利點數！');
            }
        });
    });
    </script>
    <?php
}

/**
 * 30. 在「我的帳戶 > 編輯地址」頁面載入台灣縣市與鄉鎮市區二級連動 JavaScript
 */
add_action( 'wp_enqueue_scripts', 'ckc_enqueue_my_account_address_scripts' );
function ckc_enqueue_my_account_address_scripts() {
    if ( ! is_account_page() || ! is_wc_endpoint_url( 'edit-address' ) ) {
        return;
    }

    $districts_file = WP_PLUGIN_DIR . '/mydybox-taiwan-for-woocommerce/includes/modules/checkout-tw/data/tw-districts.php';
    $postcodes_file = WP_PLUGIN_DIR . '/mydybox-taiwan-for-woocommerce/includes/modules/checkout-tw/data/tw-postcodes.php';

    if ( file_exists( $districts_file ) && file_exists( $postcodes_file ) ) {
        $districts = include $districts_file;
        $postcodes = include $postcodes_file;

        $saved_billing_city = get_user_meta( get_current_user_id(), 'billing_city', true );
        $saved_shipping_city = get_user_meta( get_current_user_id(), 'shipping_city', true );

        wp_add_inline_script( 'jquery', "
            jQuery(document).ready(function($) {
                var twDistricts = " . json_encode( $districts ) . ";
                var twPostcodes = " . json_encode( $postcodes ) . ";
                var savedCities = {
                    billing: " . json_encode( $saved_billing_city ) . ",
                    shipping: " . json_encode( $saved_shipping_city ) . "
                };

                function updateDistricts(type) {
                    var state = $('#' + type + '_state').val();
                    var \$citySelect = $('#' + type + '_city');
                    if (!\$citySelect.length) return;
                    
                    var currentCity = \$citySelect.val() || savedCities[type] || '';

                    if (!twDistricts[state]) {
                        if (\$citySelect.is('select')) {
                            \$citySelect.replaceWith('<input type=\"text\" class=\"input-text\" name=\"' + type + '_city\" id=\"' + type + '_city\" value=\"' + currentCity + '\">');
                        }
                        return;
                    }

                    var options = '<option value=\"\">─ 請選擇 ─</option>';
                    $.each(twDistricts[state], function(k, v) {
                        options += '<option value=\"' + k + '\"' + (k === currentCity ? ' selected' : '') + '>' + v + '</option>';
                    });

                    if (\$citySelect.is('input')) {
                        \$citySelect.replaceWith('<select name=\"' + type + '_city\" id=\"' + type + '_city\" class=\"select\">' + options + '</select>');
                    } else {
                        \$citySelect.html(options);
                    }
                }

                $('body').on('change', 'select.state_select', function() {
                    var type = $(this).attr('id').replace('_state', '');
                    updateDistricts(type);
                });

                $('body').on('change', 'select[id$=\"_city\"]', function() {
                    var type = $(this).attr('id').replace('_city', '');
                    var state = $('#' + type + '_state').val();
                    var city = $(this).val();
                    if (twPostcodes[state] && twPostcodes[state][city]) {
                        $('#' + type + '_postcode').val(twPostcodes[state][city]).trigger('change');
                    }
                });

                // 稍微延遲執行以確保 WooCommerce 欄位 DOM 已載入完畢
                setTimeout(function() {
                    updateDistricts('billing');
                    updateDistricts('shipping');
                }, 300);
            });
        " );
    }
}

// 30b. 結帳頁面：動態填入已儲存或已選擇縣市的鄉鎮市區下拉選單選項，以利預先選取
add_filter( 'woocommerce_checkout_fields', 'chao_gang_cheng_populate_checkout_city_options', 9999 );
function chao_gang_cheng_populate_checkout_city_options( $fields ) {
    if ( ! is_admin() && ( is_checkout() || wp_doing_ajax() ) ) {
        $districts_file = WP_PLUGIN_DIR . '/mydybox-taiwan-for-woocommerce/includes/modules/checkout-tw/data/tw-districts.php';
        if ( file_exists( $districts_file ) ) {
            $districts = include $districts_file;
            $user_id = get_current_user_id();

            foreach ( array( 'billing', 'shipping' ) as $type ) {
                $state = '';
                if ( isset( $_POST[ $type . '_state' ] ) ) {
                    $state = sanitize_text_field( $_POST[ $type . '_state' ] );
                }
                if ( empty( $state ) && $user_id ) {
                    $state = get_user_meta( $user_id, $type . '_state', true );
                }
                if ( ! empty( $state ) && isset( $districts[ $state ] ) ) {
                    $options = array( '' => '─ 請選擇 ─' );
                    foreach ( $districts[ $state ] as $k => $v ) {
                        $options[ $k ] = $v;
                    }
                    $fields[ $type ][ $type . '_city' ]['options'] = $options;
                }
            }
        }
    }
    return $fields;
}

/**
 * =========================================================================
 * 31. WOOCOMMERCE 顧客標籤自動化與手動管理系統
 * =========================================================================
 */

/**
 * 31a. 核心功能：重新計算並更新特定會員用戶的顧客標籤
 */
function ckc_recalculate_customer_tags( $user_id ) {
    if ( ! $user_id ) {
        return array();
    }

    $user = get_userdata( $user_id );
    if ( ! $user || in_array( 'administrator', $user->roles ) ) {
        return array();
    }

    if ( ! function_exists( 'wc_get_orders' ) ) {
        return array();
    }

    // 1. 取得該買家所有已付款或已出貨完成的訂單
    $orders = wc_get_orders( array(
        'customer' => $user_id,
        'status'   => array( 'processing', 'completed' ),
        'limit'    => -1,
    ) );

    $tags = array();
    $total_spent = 0;
    $order_count = count( $orders );
    $last_order_timestamp = 0;
    $has_ticket = false;
    $has_frozen = false;

    foreach ( $orders as $order ) {
        $total_spent += floatval( $order->get_total() );
        
        $timestamp = $order->get_date_created()->getTimestamp();
        if ( $timestamp > $last_order_timestamp ) {
            $last_order_timestamp = $timestamp;
        }

        // 檢查購買商品分類
        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            $terms = get_the_terms( $product_id, 'product_cat' );
            if ( $terms && ! is_wp_error( $terms ) ) {
                foreach ( $terms as $term ) {
                    if ( stripos( $term->name, '餐券' ) !== false || stripos( $term->name, '票券' ) !== false || stripos( $term->slug, 'ticket' ) !== false ) {
                        $has_ticket = true;
                    }
                    if ( stripos( $term->name, '冷凍' ) !== false || stripos( $term->slug, 'frozen' ) !== false ) {
                        $has_frozen = true;
                    }
                }
            }
        }
    }

    // 2. 依規則貼上自動標籤
    if ( $total_spent >= 10000 ) {
        $tags[] = 'VIP客戶';
    }
    
    if ( $order_count >= 3 ) {
        $tags[] = '常客';
    } elseif ( $order_count == 2 ) {
        $tags[] = '回購客';
    } elseif ( $order_count == 1 ) {
        $tags[] = '新客戶';
    }

    if ( $order_count > 0 && $last_order_timestamp > 0 ) {
        $now = current_time( 'timestamp' );
        $days_diff = ( $now - $last_order_timestamp ) / 86400;
        if ( $days_diff >= 180 ) {
            $tags[] = '休眠客戶';
        }
    }

    if ( $has_ticket ) {
        $tags[] = '餐券愛好者';
    }
    if ( $has_frozen ) {
        $tags[] = '冷凍食品愛好者';
    }

    // 取得顧客來源並加入標籤
    $source = get_user_meta( $user_id, 'ckc_user_source', true );
    if ( ! empty( $source ) ) {
        $tags[] = '來源: ' . $source;
    }

    // 3. 儲存自動標籤至 user_meta
    update_user_meta( $user_id, 'ckc_auto_customer_tags', $tags );
    
    // 讀取並合併手動標籤
    $manual_tags = get_user_meta( $user_id, 'ckc_manual_customer_tags', true );
    if ( ! is_array( $manual_tags ) ) {
        $manual_tags = array();
    }
    
    $all_tags = array_unique( array_merge( $tags, $manual_tags ) );
    update_user_meta( $user_id, 'ckc_customer_tags', $all_tags );

    return $all_tags;
}

/**
 * 31b. 訂單完成付款或狀態變更時自動觸發計算
 */
add_action( 'woocommerce_order_status_changed', 'ckc_trigger_tag_recalc_on_status_change', 20, 4 );
function ckc_trigger_tag_recalc_on_status_change( $order_id, $old_status, $new_status, $order ) {
    $user_id = $order->get_customer_id();
    if ( $user_id ) {
        ckc_recalculate_customer_tags( $user_id );
    }
}

add_action( 'woocommerce_payment_complete', 'ckc_trigger_tag_recalc_on_payment', 20, 1 );
function ckc_trigger_tag_recalc_on_payment( $order_id ) {
    if ( function_exists( 'wc_get_order' ) ) {
        $order = wc_get_order( $order_id );
        if ( $order ) {
            $user_id = $order->get_customer_id();
            if ( $user_id ) {
                ckc_recalculate_customer_tags( $user_id );
            }
        }
    }
}

/**
 * 31c. 在後台「用戶 > 所有用戶」列表加入「客戶標籤」欄位
 */
add_filter( 'manage_users_columns', 'ckc_add_customer_tags_column' );
function ckc_add_customer_tags_column( $columns ) {
    $columns['ckc_customer_tags'] = '客戶標籤';
    return $columns;
}

add_filter( 'manage_users_custom_column', 'ckc_show_customer_tags_column_content', 10, 3 );
function ckc_show_customer_tags_column_content( $output, $column_name, $user_id ) {
    if ( 'ckc_customer_tags' === $column_name ) {
        $tags = get_user_meta( $user_id, 'ckc_customer_tags', true );
        if ( ! empty( $tags ) && is_array( $tags ) ) {
            $html = '<div style="display: flex; flex-wrap: wrap; gap: 4px;">';
            $tag_styles = array(
                'VIP客戶' => 'background-color: #fef3c7; color: #d97706; border: 1px solid #fde68a;',
                '常客'    => 'background-color: #d1fae5; color: #059669; border: 1px solid #a7f3d0;',
                '回購客'  => 'background-color: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd;',
                '新客戶'  => 'background-color: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb;',
                '休眠客戶' => 'background-color: #fee2e2; color: #dc2626; border: 1px solid #fecaca;',
                '餐券愛好者' => 'background-color: #f3e8ff; color: #7c3aed; border: 1px solid #e9d5ff;',
                '冷凍食品愛好者' => 'background-color: #e0e7ff; color: #4f46e5; border: 1px solid #c7d2fe;',
            );
            $tag_titles = array(
                'VIP客戶' => '累積消費金額達 $10,000 元以上（已付款或已出貨訂單）',
                '常客'    => '付款或出貨完成的訂單數達 3 次以上',
                '回購客'  => '付款 or 出貨完成的訂單數恰為 2 次',
                '新客戶'  => '付款 or 出貨完成的訂單數恰為 1 次',
                '休眠客戶' => '距離最後一次訂單成立時間已超過 180 天（約 6 個月）',
                '餐券愛好者' => '曾購買商品分類名稱中包含「餐券」、「票券」或商品 slug 含有 ticket 的商品',
                '冷凍食品愛好者' => '曾購買商品分類名稱中包含「冷凍」或商品 slug 含有 frozen 的商品',
            );
            
            foreach ( $tags as $tag ) {
                if ( strpos( $tag, '來源:' ) === 0 ) {
                    $style = 'background-color: #ecfeff; color: #0891b2; border: 1px solid #a5f3fc;';
                    $title = '客戶註冊時入站的流量管道來源';
                } else {
                    $style = isset( $tag_styles[$tag] ) ? $tag_styles[$tag] : 'background-color: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;';
                    $title = isset( $tag_titles[$tag] ) ? $tag_titles[$tag] : '手動自訂標籤';
                }
                $html .= sprintf(
                    '<span title="%s" style="cursor: help; padding: 2px 6px; font-size: 11px; font-weight: 500; border-radius: 4px; line-height: 1.2; %s">%s</span>',
                    esc_attr( $title ),
                    $style,
                    esc_html( $tag )
                );
            }
            $html .= '</div>';
            return $html;
        }
        return '<span style="color: #94a3b8; font-size: 12px;">無標籤</span>';
    }
    return $output;
}

/**
 * 31d. 在後台「編輯用戶」頁面中顯示與編輯顧客標籤
 */
add_action( 'show_user_profile', 'ckc_show_user_tags_in_profile' );
add_action( 'edit_user_profile', 'ckc_show_user_tags_in_profile' );
function ckc_show_user_tags_in_profile( $user ) {
    $user_id = $user->ID;
    
    $auto_tags = get_user_meta( $user_id, 'ckc_auto_customer_tags', true );
    if ( ! is_array( $auto_tags ) ) {
        $auto_tags = ckc_recalculate_customer_tags( $user_id );
        $auto_tags = get_user_meta( $user_id, 'ckc_auto_customer_tags', true );
        if ( ! is_array( $auto_tags ) ) {
            $auto_tags = array();
        }
    }
    
    $manual_tags = get_user_meta( $user_id, 'ckc_manual_customer_tags', true );
    if ( ! is_array( $manual_tags ) ) {
        $manual_tags = array();
    }
    
    $manual_tags_str = implode( ',', $manual_tags );
    ?>
    <hr style="margin: 30px 0 20px;" />
    <h2>顧客標籤系統 (WooCommerce 自動化)</h2>
    <table class="form-table">
        <tr>
            <th><label>系統自動標籤</label></th>
            <td>
                <?php if ( ! empty( $auto_tags ) ) : ?>
                    <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px;">
                        <?php foreach ( $auto_tags as $tag ) : ?>
                            <span style="padding: 4px 8px; font-size: 12px; font-weight: 500; border-radius: 4px; background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;"><?php echo esc_html( $tag ); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <p class="description">由系統分析該會員之消費總金額、頻率、歷史品項分類等全自動標記，無法手動刪除。</p>
                <?php else : ?>
                    <span style="color: #94a3b8; display: block; margin-bottom: 8px;">目前尚無自動標籤（可能無付款訂單記錄，或為管理員帳號）</span>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th><label for="ckc_manual_tags">手動自訂標籤</label></th>
            <td>
                <input type="text" name="ckc_manual_tags" id="ckc_manual_tags" value="<?php echo esc_attr( $manual_tags_str ); ?>" class="regular-text" placeholder="例：特案客戶,VIP親友,實體熟客" />
                <p class="description">請以英文半形逗號「,」分隔多個標籤。您可以自由在此輸入自訂的標籤。</p>
            </td>
        </tr>
        <tr>
            <th><label for="ckc_user_source">客戶註冊來源</label></th>
            <td>
                <input type="text" name="ckc_user_source" id="ckc_user_source" value="<?php echo esc_attr( get_user_meta( $user_id, 'ckc_user_source', true ) ); ?>" class="regular-text" placeholder="例：Facebook、LINE、Google 搜尋、直接造訪" />
                <p class="description">系統自動偵測之用戶註冊來源管道。管理員亦可在此手動調整（修改後會自動更新標籤）。</p>
            </td>
        </tr>
        <tr>
            <th><label>自動標籤判定說明</label></th>
            <td>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px 20px; max-width: 600px; color: #334155; font-size: 13px; line-height: 1.6;">
                    <strong style="display: block; margin-bottom: 10px; color: #0f172a; font-size: 14px;">🏷️ 系統自動化標籤判定規則：</strong>
                    <ul style="margin: 0; padding-left: 20px; list-style-type: disc;">
                        <li style="margin-bottom: 6px;"><strong>來源: [來源名稱]</strong>：依據用戶註冊時入站的流量管道（如 Facebook、LINE、Google 搜尋/廣告 等，或直接造訪、後台手動新增）自動標記。</li>
                        <li style="margin-bottom: 6px;"><strong>VIP客戶</strong>：累積消費金額達 <strong>$10,000 元</strong>以上（僅計算「已付款」或「已完成」狀態之訂單）。</li>
                        <li style="margin-bottom: 6px;"><strong>常客</strong>：付款/出貨完成的訂單數達 <strong>3 次</strong>以上。</li>
                        <li style="margin-bottom: 6px;"><strong>回購客</strong>：付款/出貨完成的訂單數恰為 <strong>2 次</strong>。</li>
                        <li style="margin-bottom: 6px;"><strong>新客戶</strong>：付款/出貨完成的訂單數恰為 <strong>1 次</strong>。</li>
                        <li style="margin-bottom: 6px;"><strong>休眠客戶</strong>：距離最後一次訂單成立時間已超過 <strong>180 天</strong>（約 6 個月）。</li>
                        <li style="margin-bottom: 6px;"><strong>餐券愛好者</strong>：曾購買的商品分類名稱中包含「餐券」、「票券」或商品 slug 含有 <code>ticket</code> 的商品。</li>
                        <li style="margin-bottom: 0;"><strong>冷凍食品愛好者</strong>：曾購買的商品分類名稱中包含「冷凍」或商品 slug 含有 <code>frozen</code> 的商品。</li>
                    </ul>
                </div>
            </td>
        </tr>
    </table>
    <?php
}

add_action( 'personal_options_update', 'ckc_save_user_tags_in_profile' );
add_action( 'edit_user_profile_update', 'ckc_save_user_tags_in_profile' );
function ckc_save_user_tags_in_profile( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        return;
    }
    
    $needs_recalc = false;

    if ( isset( $_POST['ckc_user_source'] ) ) {
        $old_source = get_user_meta( $user_id, 'ckc_user_source', true );
        $new_source = sanitize_text_field( $_POST['ckc_user_source'] );
        if ( $old_source !== $new_source ) {
            update_user_meta( $user_id, 'ckc_user_source', $new_source );
            $needs_recalc = true;
        }
    }
    
    if ( isset( $_POST['ckc_manual_tags'] ) ) {
        $manual_tags_raw = sanitize_text_field( $_POST['ckc_manual_tags'] );
        $manual_tags = array();
        if ( ! empty( $manual_tags_raw ) ) {
            $parts = explode( ',', $manual_tags_raw );
            foreach ( $parts as $part ) {
                $trimmed = trim( $part );
                if ( ! empty( $trimmed ) ) {
                    $manual_tags[] = $trimmed;
                }
            }
        }
        
        update_user_meta( $user_id, 'ckc_manual_customer_tags', $manual_tags );
        $needs_recalc = true;
    }

    if ( $needs_recalc ) {
        ckc_recalculate_customer_tags( $user_id );
    }
}

/**
 * 31e. 在 WordPress 後台「用戶」選單下新增一個顧客標籤同步管理頁面
 */
add_action( 'admin_menu', 'ckc_register_customer_tags_sync_page' );
function ckc_register_customer_tags_sync_page() {
    add_users_page(
        '顧客標籤同步',
        '顧客標籤同步',
        'manage_options',
        'ckc-customer-tags-sync',
        'ckc_customer_tags_sync_page_html'
    );
}

function ckc_customer_tags_sync_page_html() {
    ?>
    <div class="wrap">
        <h1>顧客標籤全自動同步與重算工具</h1>
        <p>此工具將掃描您網站上所有的顧客，讀取其歷史訂單金額、訂單數與商品偏好，重新批次產生最新的自動化標籤。</p>
        
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px 20px; max-width: 600px; margin-top: 20px; margin-bottom: 20px; color: #334155; font-size: 13px; line-height: 1.6;">
            <strong style="display: block; margin-bottom: 10px; color: #0f172a; font-size: 14px;">🏷️ 系統自動化標籤判定規則：</strong>
            <ul style="margin: 0; padding-left: 20px; list-style-type: disc;">
                <li style="margin-bottom: 6px;"><strong>來源: [來源名稱]</strong>：依據用戶註冊時入站的流量管道（如 Facebook、LINE、Google 搜尋/廣告 等，或直接造訪、後台手動新增）自動標記。</li>
                <li style="margin-bottom: 6px;"><strong>VIP客戶</strong>：累積消費金額達 <strong>$10,000 元</strong>以上（僅計算「已付款」或「已完成」狀態之訂單）。</li>
                <li style="margin-bottom: 6px;"><strong>常客</strong>：付款/出貨完成的訂單數達 <strong>3 次</strong>以上。</li>
                <li style="margin-bottom: 6px;"><strong>回購客</strong>：付款/出貨完成的訂單數恰為 <strong>2 次</strong>。</li>
                <li style="margin-bottom: 6px;"><strong>新客戶</strong>：付款/出貨完成的訂單數恰為 <strong>1 次</strong>。</li>
                <li style="margin-bottom: 6px;"><strong>休眠客戶</strong>：距離最後一次訂單成立時間已超過 <strong>180 天</strong>（約 6 個月）。</li>
                <li style="margin-bottom: 6px;"><strong>餐券愛好者</strong>：曾購買的商品分類名稱中包含「餐券」、「票券」或商品 slug 含有 <code>ticket</code> 的商品。</li>
                <li style="margin-bottom: 0;"><strong>冷凍食品愛好者</strong>：曾購買的商品分類名稱中包含「冷凍」或商品 slug 含有 <code>frozen</code> 的商品。</li>
            </ul>
        </div>
        
        <div style="background: white; border: 1px solid #ccd0d4; padding: 20px; border-radius: 8px; max-width: 600px; margin-top: 20px;">
            <h3>批次重算作業</h3>
            <p>點選下方按鈕開始批次同步，系統將採用非同步背景分段計算，以防資料庫載入過重導致當機。</p>
            
            <button id="start-sync-tags-btn" class="button button-primary button-large">開始全體同步重算</button>
            
            <div id="sync-progress-container" style="margin-top: 20px; display: none;">
                <div style="background: #f1f5f9; border-radius: 4px; height: 20px; width: 100%; overflow: hidden;">
                    <div id="sync-progress-bar" style="background: #10b981; height: 100%; width: 0%; transition: width 0.3s ease;"></div>
                </div>
                <p id="sync-status-text" style="font-weight: 500; margin-top: 8px;">準備中...</p>
            </div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        var $btn = $('#start-sync-tags-btn');
        var $progressContainer = $('#sync-progress-container');
        var $progressBar = $('#sync-progress-bar');
        var $statusText = $('#sync-status-text');

        $btn.on('click', function() {
            if (!confirm('確認要開始重新計算全體顧客標籤嗎？這會花費一些時間。')) return;

            $btn.prop('disabled', true).text('同步進行中...');
            $progressContainer.show();
            
            var offset = 0;
            var limit = 20;
            
            function runBatch() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ckc_batch_sync_customer_tags',
                        offset: offset,
                        limit: limit
                    },
                    success: function(response) {
                        if (response.success) {
                            var processed = response.data.processed;
                            var total = response.data.total;
                            offset += processed;
                            
                            var pct = Math.round((offset / total) * 100);
                            $progressBar.css('width', pct + '%');
                            $statusText.text('已處理 ' + offset + ' / ' + total + ' 名會員 (' + pct + '%)');

                            if (processed > 0 && offset < total) {
                                runBatch();
                            } else {
                                $progressBar.css('width', '100%');
                                $statusText.text('恭喜！全體顧客標籤重新整理完畢！').css('color', '#10b981');
                                $btn.prop('disabled', false).text('重新開始同步');
                            }
                        } else {
                            $statusText.text('❌ 同步失敗：' + response.data.message).css('color', '#ef4444');
                            $btn.prop('disabled', false).text('重新開始同步');
                        }
                    },
                    error: function() {
                        $statusText.text('❌ 網路或伺服器連線異常。').css('color', '#ef4444');
                        $btn.prop('disabled', false).text('重新開始同步');
                    }
                });
            }

            runBatch();
        });
    });
    </script>
    <?php
}

/**
 * 31f. 顧客標籤批次處理 AJAX 介面
 */
add_action( 'wp_ajax_ckc_batch_sync_customer_tags', 'ckc_ajax_batch_sync_customer_tags' );
function ckc_ajax_batch_sync_customer_tags() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => '權限不足' ) );
    }

    $offset = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
    $limit = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : 20;

    $user_query = new WP_User_Query( array(
        'role__in' => array( 'customer', 'subscriber' ),
        'number'   => -1,
        'fields'   => 'ID',
    ) );
    $all_user_ids = $user_query->get_results();
    $total_users = count( $all_user_ids );

    $batch_user_ids = array_slice( $all_user_ids, $offset, $limit );
    
    $processed = 0;
    foreach ( $batch_user_ids as $user_id ) {
        ckc_recalculate_customer_tags( $user_id );
        $processed++;
    }

    wp_send_json_success( array(
        'processed' => $processed,
        'total'     => $total_users,
    ) );
}

/**
 * 31g. 將顧客標籤與聯絡電話加入 WooCommerce Analytics Customers 報表 API 回傳值
 */
add_filter( 'woocommerce_rest_prepare_report_customers', 'ckc_add_tags_to_customers_report_api', 10, 3 );
function ckc_add_tags_to_customers_report_api( $response, $report, $request ) {
    $data = $response->get_data();
    $user_id = isset( $data['user_id'] ) ? $data['user_id'] : 0;
    if ( ! $user_id && isset( $data['id'] ) ) {
        $user_id = $data['id'];
    }
    
    if ( $user_id ) {
        $tags = get_user_meta( $user_id, 'ckc_customer_tags', true );
        $data['ckc_customer_tags'] = ! empty( $tags ) && is_array( $tags ) ? implode( ', ', $tags ) : '';
        
        $phone = get_user_meta( $user_id, 'billing_phone', true );
        if ( ! $phone ) {
            $phone = get_user_meta( $user_id, 'shipping_phone', true );
        }
        $data['ckc_customer_phone'] = $phone ? $phone : '';
    } else {
        $data['ckc_customer_tags'] = '';
        $data['ckc_customer_phone'] = '';
    }
    
    $response->set_data( $data );
    return $response;
}

/**
 * 31h. 在 WooCommerce Analytics Customers 報表前端 React 表格中注入「聯絡電話」與「客戶標籤」欄位
 */
add_action( 'admin_print_footer_scripts', 'ckc_add_customer_tags_to_wc_analytics_report' );
function ckc_add_customer_tags_to_wc_analytics_report() {
    $screen = get_current_screen();
    if ( ! $screen || strpos( $screen->id, 'woocommerce' ) === false && strpos( $screen->id, 'wc-admin' ) === false ) {
        return;
    }
    ?>
    <script>
    (function() {
        if (typeof wp !== 'undefined' && wp.hooks && typeof wp.hooks.addFilter === 'function') {
            wp.hooks.addFilter('woocommerce_admin_report_table', 'ckc-customer-tags-filter', function(reportTableData) {
                if (reportTableData.endpoint !== 'customers') {
                    return reportTableData;
                }
                
                // 1. 注入聯絡電話 Header
                var hasPhoneHeader = false;
                for (var i = 0; i < reportTableData.headers.length; i++) {
                    if (reportTableData.headers[i].key === 'ckc_customer_phone') {
                        hasPhoneHeader = true;
                        break;
                    }
                }
                if (!hasPhoneHeader) {
                    reportTableData.headers.push({
                        label: '聯絡電話',
                        key: 'ckc_customer_phone'
                    });
                }

                // 2. 注入客戶標籤 Header
                var hasTagsHeader = false;
                for (var i = 0; i < reportTableData.headers.length; i++) {
                    if (reportTableData.headers[i].key === 'ckc_customer_tags') {
                        hasTagsHeader = true;
                        break;
                    }
                }
                if (!hasTagsHeader) {
                    reportTableData.headers.push({
                        label: '客戶標籤',
                        key: 'ckc_customer_tags'
                    });
                }
                
                // 3. 注入 Row 資料 (對應 Headers 順序 push 進去)
                if (reportTableData.rows && reportTableData.items && reportTableData.items.data) {
                    reportTableData.rows = reportTableData.rows.map(function(row, index) {
                        var item = reportTableData.items.data[index];
                        if (item) {
                            var expectedLength = reportTableData.headers.length;
                            var currentLength = row.length;
                            
                            var phone = item.ckc_customer_phone || '無';
                            var tags = item.ckc_customer_tags || '無';
                            
                            if (currentLength === expectedLength - 2) {
                                row.push({
                                    display: phone,
                                    value: phone
                                });
                                row.push({
                                    display: tags,
                                    value: tags
                                });
                            }
                        }
                        return row;
                    });
                }
                
                return reportTableData;
            });
        }
    })();
    </script>
    <?php
}

/**
 * =========================================================================
 * 32. WOOCOMMERCE AI 智慧推薦商品自動化系統
 * =========================================================================
 */

/**
 * 32a. 取得並快取整站所有上架商品的精簡目錄，以利 AI 進行分析
 */
function ckc_get_product_catalog() {
    $catalog = get_transient( 'ckc_product_catalog' );
    if ( false === $catalog ) {
        if ( ! function_exists( 'wc_get_products' ) ) {
            return array();
        }
        $products = wc_get_products( array(
            'status' => 'publish',
            'limit'  => -1,
        ) );
        
        $catalog = array();
        foreach ( $products as $product ) {
            $catalog[] = array(
                'id'    => $product->get_id(),
                'title' => $product->get_title(),
                'price' => $product->get_price(),
                'cats'  => wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) ),
            );
        }
        set_transient( 'ckc_product_catalog', $catalog, DAY_IN_SECONDS );
    }
    return $catalog;
}

/**
 * 32b. 呼叫 Gemini API 發送 Prompt 請求
 */
function ckc_call_gemini_api( $prompt ) {
    $api_key = get_option( 'ckc_gemini_api_key', '' );
    if ( empty( $api_key ) ) {
        return '';
    }

    $payload = array(
        'contents' => array(
            array(
                'parts' => array(
                    array( 'text' => $prompt )
                )
            )
        )
    );

    $endpoints = array(
        'https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key=' . urlencode( $api_key ),
        'https://generativelanguage.googleapis.com/v1/models/gemini-2.0-flash:generateContent?key=' . urlencode( $api_key ),
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=' . urlencode( $api_key ),
        'https://generativelanguage.googleapis.com/v1/models/gemini-1.5-flash:generateContent?key=' . urlencode( $api_key ),
    );

    foreach ( $endpoints as $endpoint ) {
        $response = wp_remote_post( $endpoint, array(
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => json_encode( $payload ),
            'timeout' => 15,
        ) );

        if ( ! is_wp_error( $response ) ) {
            $code = wp_remote_retrieve_response_code( $response );
            if ( $code === 200 ) {
                $body = wp_remote_retrieve_body( $response );
                $data = json_decode( $body, true );
                if ( isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
                    return trim( $data['candidates'][0]['content']['parts'][0]['text'] );
                }
            }
        }
    }

    return '';
}

/**
 * 32c. AI 推薦產生核心邏輯（含 AI 與 Sandbox 相似度比對備份演算法）
 */
function ckc_generate_ai_recommendations( $product_id ) {
    $product = wc_get_product( $product_id );
    if ( ! $product ) {
        return array();
    }

    $api_key = get_option( 'ckc_gemini_api_key', '' );
    $catalog = ckc_get_product_catalog();
    
    // 過濾掉當前商品本身
    $candidates = array();
    foreach ( $catalog as $item ) {
        if ( intval( $item['id'] ) !== intval( $product_id ) ) {
            $candidates[] = $item;
        }
    }

    if ( empty( $candidates ) ) {
        return array();
    }

    // --- A. 如果有 API Key，進行真實 AI 運算 ---
    if ( ! empty( $api_key ) ) {
        $current_cats = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) );
        $prompt = "你是一位精通電子商務交叉銷售與加購搭配的 AI 推薦專家。\n";
        $prompt .= "請為「當前商品」從下面的「候選商品清單」中選出最合適推薦給買家的 4 個相關/推薦商品。\n\n";
        $prompt .= "當前商品：\n";
        $prompt .= "- ID: {$product_id}\n";
        $prompt .= "- 名稱: " . $product->get_title() . "\n";
        $prompt .= "- 分類: " . implode( ', ', $current_cats ) . "\n";
        $prompt .= "- 價格: " . $product->get_price() . "\n\n";
        
        $prompt .= "候選商品清單：\n";
        foreach ( $candidates as $cand ) {
            $prompt .= "- ID: {$cand['id']} | 名稱: {$cand['title']} | 分類: " . implode( ', ', $cand['cats'] ) . " | 價格: {$cand['price']}\n";
        }
        
        $prompt .= "\n請基於搭配性、互補性（例如買火鍋可以加購肉品、買餐券可以推薦其他餐券或伴手禮）選出最優質的 4 個商品。\n";
        $prompt .= "請只返回一個 JSON 陣列，內含這 4 個推薦商品的 ID（例如：[102, 105, 98, 120]），不要包含任何額外說明文字或 Markdown 的包裹（不要使用 ```json 或是額外標記，只要最乾淨的 JSON 陣列）。";

        $response_text = ckc_call_gemini_api( $prompt );
        if ( ! empty( $response_text ) ) {
            $response_text = preg_replace( '/```json/i', '', $response_text );
            $response_text = preg_replace( '/```/i', '', $response_text );
            $response_text = trim( $response_text );

            $recommended_ids = json_decode( $response_text, true );
            if ( is_array( $recommended_ids ) && ! empty( $recommended_ids ) ) {
                $verified_ids = array();
                $cand_ids = wp_list_pluck( $candidates, 'id' );
                foreach ( $recommended_ids as $id ) {
                    $id = intval( $id );
                    if ( in_array( $id, $cand_ids ) ) {
                        $verified_ids[] = $id;
                    }
                }
                if ( ! empty( $verified_ids ) ) {
                    return array_slice( $verified_ids, 0, 4 );
                }
            }
        }
    }

    // --- B. 沙盒/備份模式：利用分類相似度與隨機挑選排序 ---
    $current_cats = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) );
    $same_cat_items = array();
    $other_cat_items = array();

    foreach ( $candidates as $cand ) {
        $intersection = array_intersect( $current_cats, $cand['cats'] );
        if ( ! empty( $intersection ) ) {
            $same_cat_items[] = intval( $cand['id'] );
        } else {
            $other_cat_items[] = intval( $cand['id'] );
        }
    }

    shuffle( $same_cat_items );
    shuffle( $other_cat_items );

    $result = array_merge( $same_cat_items, $other_cat_items );
    return array_slice( $result, 0, 4 );
}

/**
 * 32d. 掛載相關商品篩選器，將預設推薦替換為 AI 智慧推薦商品
 */
add_filter( 'woocommerce_related_products', 'ckc_ai_automated_related_products', 100, 3 );
function ckc_ai_automated_related_products( $related_posts, $product_id, $args ) {
    $cached = get_post_meta( $product_id, '_ckc_ai_recommendations', true );
    if ( is_array( $cached ) && ! empty( $cached ) ) {
        $result = $cached;
    } else {
        $recommended_ids = ckc_generate_ai_recommendations( $product_id );
        if ( ! empty( $recommended_ids ) ) {
            update_post_meta( $product_id, '_ckc_ai_recommendations', $recommended_ids );
            $result = $recommended_ids;
        } else {
            $result = $related_posts;
        }
    }

    // 快取的推薦名單是產生當下的結果，商品之後可能會售完；每次顯示都即時
    // 過濾掉目前已售完／缺貨中的商品，避免推薦顧客買不到的東西（不用清快取，
    // 商品之後補貨回來也會自動再次出現）。
    $result = ckc_filter_instock_product_ids( $result );

    // 過濾後可能不足 4 個，用其他還有庫存的商品遞補，維持區塊固定顯示 4 個商品。
    $result = ckc_top_up_related_product_ids( $result, $product_id, 4 );

    return $result;
}

/**
 * 32d-1. 篩選商品 ID 陣列，只保留目前「尚有庫存」的商品（排除已售完／缺貨中）。
 */
function ckc_filter_instock_product_ids( $product_ids ) {
    $instock_ids = array();
    foreach ( (array) $product_ids as $id ) {
        $product = wc_get_product( $id );
        if ( $product && $product->is_in_stock() ) {
            $instock_ids[] = intval( $id );
        }
    }
    return $instock_ids;
}

/**
 * 32d-2. 補齊商品 ID 陣列到指定數量，只用還有庫存的商品遞補（依上架日期新到舊排序）。
 */
function ckc_top_up_related_product_ids( $product_ids, $exclude_product_id, $desired_count = 4 ) {
    if ( count( $product_ids ) >= $desired_count ) {
        return array_slice( $product_ids, 0, $desired_count );
    }

    $needed      = $desired_count - count( $product_ids );
    $exclude_ids = array_merge( array( $exclude_product_id ), $product_ids );

    $filler_products = wc_get_products( array(
        'limit'        => $needed,
        'status'       => 'publish',
        'stock_status' => 'instock',
        'exclude'      => $exclude_ids,
        'orderby'      => 'date',
        'order'        => 'DESC',
    ) );

    foreach ( $filler_products as $filler ) {
        $product_ids[] = $filler->get_id();
    }

    return $product_ids;
}

/**
 * 32e. 修改前台 WooCommerce 相關商品推薦區塊之 Heading 標題
 */
add_filter( 'woocommerce_product_related_products_heading', 'ckc_ai_related_products_heading' );
function ckc_ai_related_products_heading( $heading ) {
    return '推薦商品';
}

/**
 * 32f. 在後台商品編輯頁面新增 AI 推薦管理側邊欄 Meta Box
 */
add_action( 'add_meta_boxes', 'ckc_add_ai_recommendation_meta_box' );
function ckc_add_ai_recommendation_meta_box() {
    add_meta_box(
        'ckc_ai_recommendation_box',
        'AI 智慧推薦設定',
        'ckc_render_ai_recommendation_meta_box',
        'product',
        'side',
        'default'
    );
}

function ckc_render_ai_recommendation_meta_box( $post ) {
    $product_id = $post->ID;
    $cached = get_post_meta( $product_id, '_ckc_ai_recommendations', true );
    
    $titles = array();
    if ( is_array( $cached ) && ! empty( $cached ) ) {
        foreach ( $cached as $id ) {
            $titles[] = '#' . $id . ' ' . get_the_title( $id );
        }
    }
    
    ?>
    <div style="padding: 10px 0;">
        <p><strong>目前 AI 推薦商品：</strong></p>
        <?php if ( ! empty( $titles ) ) : ?>
            <ul style="margin: 0 0 15px 0; padding-left: 20px; list-style-type: disc;">
                <?php foreach ( $titles as $title ) : ?>
                    <li style="margin-bottom: 4px; font-size: 12px;"><?php echo esc_html( $title ); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <p style="color: #94a3b8; font-style: italic;">尚無快取（當前商品被買家瀏覽時將自動觸發 AI 運算）。</p>
        <?php endif; ?>
        
        <input type="hidden" name="ckc_ai_recalc_nonce" value="<?php echo wp_create_nonce( 'ckc_ai_recalc_action' ); ?>" />
        <button type="submit" name="ckc_clear_ai_cache" value="1" class="button button-secondary" style="width: 100%; text-align: center;">清除快取並重新計算</button>
        <p class="description" style="margin-top: 8px;">點擊此按鈕將在您發布/更新商品時，強制清除快取並呼叫 AI 重新分析推薦。</p>
    </div>
    <?php
}

add_action( 'save_post_product', 'ckc_save_ai_recommendation_meta_box_data' );
function ckc_save_ai_recommendation_meta_box_data( $post_id ) {
    if ( ! isset( $_POST['ckc_ai_recalc_nonce'] ) || ! wp_verify_nonce( $_POST['ckc_ai_recalc_nonce'], 'ckc_ai_recalc_action' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    
    if ( isset( $_POST['ckc_clear_ai_cache'] ) ) {
        delete_post_meta( $post_id, '_ckc_ai_recommendations' );
        
        $recommended_ids = ckc_generate_ai_recommendations( $post_id );
        if ( ! empty( $recommended_ids ) ) {
            update_post_meta( $post_id, '_ckc_ai_recommendations', $recommended_ids );
        }
    }
}

/**
 * 32g. 在商品資料 (Product Data) 方塊中新增一個「AI 智慧推薦」分頁
 */
add_filter( 'woocommerce_product_data_tabs', 'ckc_add_ai_recommendations_product_tab' );
function ckc_add_ai_recommendations_product_tab( $tabs ) {
    $tabs['ckc_ai_recommendations_tab'] = array(
        'label'    => 'AI 智慧推薦',
        'target'   => 'ckc_ai_recommendations_panel',
        'class'    => array( 'show_if_simple', 'show_if_variable' ),
        'priority' => 80,
    );
    return $tabs;
}

function ckc_ai_recommendations_panel_html() {
    global $post;
    $product_id = $post->ID;
    $cached = get_post_meta( $product_id, '_ckc_ai_recommendations', true );
    
    $titles = array();
    if ( is_array( $cached ) && ! empty( $cached ) ) {
        foreach ( $cached as $id ) {
            $titles[] = '#' . $id . ' ' . get_the_title( $id );
        }
    }
    
    ?>
    <div id="ckc_ai_recommendations_panel" class="panel woocommerce_options_panel hidden" style="padding: 20px;">
        <h3 style="margin-top: 0;">🤖 AI 智慧推薦商品設定</h3>
        <p>此商品頁面底部的「✨ AI 智慧推薦商品」區塊由 Google Gemini AI 分析商品標題與內容後自動產生。</p>
        
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 6px; margin: 15px 0; max-width: 600px;">
            <p><strong>目前快取推薦的商品：</strong></p>
            <?php if ( ! empty( $titles ) ) : ?>
                <ul style="margin: 0 0 15px 0; padding-left: 20px; list-style-type: disc;">
                    <?php foreach ( $titles as $title ) : ?>
                        <li style="margin-bottom: 6px; font-weight: 500;"><?php echo esc_html( $title ); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p style="color: #94a3b8; font-style: italic;">尚無快取商品（顧客瀏覽此商品頁面時，系統會自動在背景呼叫 AI 計算並建立快取）。</p>
            <?php endif; ?>
            
            <button type="submit" name="ckc_clear_ai_cache" value="1" class="button button-primary button-large" style="margin-top: 10px;">清除快取並重新計算</button>
            <p class="description" style="margin-top: 8px;">清除後，系統會在您點擊「更新商品」時立即呼叫 AI 重新分析最新商品組合並更新快取。</p>
        </div>
    </div>
    <?php
}
add_action( 'woocommerce_product_data_panels', 'ckc_ai_recommendations_panel_html' );

/**
 * 32g. 在商品資料 (Product Data) 方塊中新增一個「AI SEO 文案」分頁
 */
add_filter( 'woocommerce_product_data_tabs', 'ckc_add_ai_seo_copywriter_product_tab' );
function ckc_add_ai_seo_copywriter_product_tab( $tabs ) {
    $tabs['ckc_ai_seo_copywriter_tab'] = array(
        'label'    => 'AI SEO 文案',
        'target'   => 'ckc_ai_seo_copywriter_panel',
        'class'    => array( 'show_if_simple', 'show_if_variable' ),
        'priority' => 85,
    );
    return $tabs;
}

function ckc_ai_seo_copywriter_panel_html() {
    global $post;
    $product_id = $post->ID;
    ?>
    <div id="ckc_ai_seo_copywriter_panel" class="panel woocommerce_options_panel hidden" style="padding: 20px;">
        <h3 style="margin-top: 0;">🤖 AI 一鍵生成 SEO 產品文案</h3>
        <p>利用 Google Gemini AI，自動根據商品名稱、分類、價格以及您指定的關鍵字，撰寫高轉換率且符合搜尋引擎最佳化 (SEO) 的產品詳情文案。</p>
        
        <table class="form-table" style="max-width: 800px; margin-bottom: 20px;">
            <tr>
                <th style="width: 200px;"><label for="ckc_seo_keywords">SEO 附加關鍵字 (選填)</label></th>
                <td>
                    <input type="text" id="ckc_seo_keywords" class="regular-text" placeholder="例：圍爐首選, 冷凍宅配, 潮港城熱銷" style="width: 100%; max-width: 450px;" />
                    <p class="description">請以英文半形逗號「,」分隔多個關鍵字。AI 會設法自然地將這些詞融入文案中。</p>
                </td>
            </tr>
            <tr>
                <th></th>
                <td>
                    <button type="button" id="ckc-generate-seo-copy-btn" class="button button-primary button-large">開始一鍵生成文案</button>
                    <span id="ckc-seo-copy-loader" style="margin-left: 10px; display: none;">
                        <span class="spinner is-active" style="float: none; margin: 0 5px 0 0; vertical-align: middle;"></span>
                        <span style="color: #64748b; font-weight: 500;">AI 寫作中，請稍候約 3~8 秒...</span>
                    </span>
                </td>
            </tr>
        </table>

        <!-- 預覽與套用區 -->
        <div id="ckc-seo-copy-result-wrapper" style="display: none; border-top: 1px solid #e2e8f0; padding-top: 20px; max-width: 800px;">
            <h3>📝 AI 生成文案預覽</h3>
            
            <div id="ckc-seo-copy-preview-container" style="background: white; border: 1px solid #ccd0d4; border-radius: 6px; padding: 20px; margin: 15px 0; max-height: 400px; overflow-y: auto; line-height: 1.6; font-size: 14px; box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);">
                <!-- HTML 預覽將在此渲染 -->
            </div>
            
            <div style="margin-top: 20px; display: flex; gap: 10px;">
                <button type="button" id="ckc-apply-seo-desc-btn" class="button button-primary button-large">套用至「商品主要描述」</button>
                <button type="button" id="ckc-apply-seo-short-btn" class="button button-secondary button-large">套用至「商品簡短描述」</button>
            </div>
            <p class="description" style="margin-top: 8px; color: #10b981; font-weight: 500;">提示：點擊套用後，文案將會寫入對應的編輯器中，請記得點擊頁面右側的「更新/發布」以儲存商品。</p>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        var $btn = $('#ckc-generate-seo-copy-btn');
        var $loader = $('#ckc-seo-copy-loader');
        var $resultWrapper = $('#ckc-seo-copy-result-wrapper');
        var $preview = $('#ckc-seo-copy-preview-container');
        var generatedContent = '';

        $btn.on('click', function(e) {
            e.preventDefault();
            
            $btn.prop('disabled', true).text('文案生成中...');
            $loader.show();
            $resultWrapper.hide();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ckc_generate_ai_seo_copy',
                    product_id: <?php echo intval( $product_id ); ?>,
                    keywords: $('#ckc_seo_keywords').val(),
                    security: '<?php echo wp_create_nonce( 'ckc_ai_seo_copy_nonce' ); ?>'
                },
                success: function(response) {
                    $btn.prop('disabled', false).text('重新生成文案');
                    $loader.hide();
                    
                    if (response.success) {
                        generatedContent = response.data.content;
                        $preview.html(generatedContent);
                        $resultWrapper.show();
                        
                        // 滾動到預覽區域
                        $('html, body').animate({
                            scrollTop: $resultWrapper.offset().top - 100
                        }, 500);
                    } else {
                        alert('文案生成失敗：' + response.data.message);
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('重新生成文案');
                    $loader.hide();
                    alert('伺服器連線異常，請稍候重試。');
                }
            });
        });

        // 套用至商品主要描述
        $('#ckc-apply-seo-desc-btn').on('click', function(e) {
            e.preventDefault();
            if (!generatedContent) return;
            
            if (confirm('確認要將此文案寫入「商品主要描述」嗎？這將會覆蓋您原先的描述內容。')) {
                if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                    tinymce.get('content').setContent(generatedContent);
                } else if ($('#content').length) {
                    $('#content').val(generatedContent);
                }
                
                if (window.wp && wp.data && wp.data.select('core/editor')) {
                    wp.data.dispatch('core/editor').resetBlocks(wp.blocks.parse(generatedContent));
                }
                
                alert('已套用至商品主要描述！請點擊右側「更新」按鈕以儲存。');
            }
        });

        // 套用至商品簡短描述
        $('#ckc-apply-seo-short-btn').on('click', function(e) {
            e.preventDefault();
            if (!generatedContent) return;
            
            if (confirm('確認要將此文案寫入「商品簡短描述」嗎？這將會覆蓋您原先的簡短描述內容。')) {
                if (typeof tinymce !== 'undefined' && tinymce.get('excerpt')) {
                    tinymce.get('excerpt').setContent(generatedContent);
                } else if ($('#excerpt').length) {
                    $('#excerpt').val(generatedContent);
                }
                
                alert('已套用至商品簡短描述！請點擊右側「更新」按鈕以儲存。');
            }
        });
    });
    </script>
    <?php
}
add_action( 'woocommerce_product_data_panels', 'ckc_ai_seo_copywriter_panel_html' );

/**
 * 32h. 一鍵生成 AI SEO 產品文案 AJAX 處理程序
 */
add_action( 'wp_ajax_ckc_generate_ai_seo_copy', 'ckc_ajax_generate_ai_seo_copy' );
function ckc_ajax_generate_ai_seo_copy() {
    check_ajax_referer( 'ckc_ai_seo_copy_nonce', 'security' );
    if ( ! current_user_can( 'edit_products' ) ) {
        wp_send_json_error( array( 'message' => '權限不足' ) );
    }

    $product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
    $keywords = isset( $_POST['keywords'] ) ? sanitize_text_field( $_POST['keywords'] ) : '';

    $product = wc_get_product( $product_id );
    if ( ! $product ) {
        wp_send_json_error( array( 'message' => '找不到商品' ) );
    }

    $title = $product->get_title();
    $price = $product->get_price();
    $cats = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) );
    $cats_str = implode( ', ', $cats );

    $api_key = get_option( 'ckc_gemini_api_key', '' );
    $html_content = '';
    $api_failed = false;

    if ( ! empty( $api_key ) ) {
        // --- A. 真實 AI 生成模式 ---
        $prompt = "你是一位精通電子商務 SEO、消費者心理學與行銷文案撰寫的大師。\n";
        $prompt .= "請為以下商品撰寫一段結構清晰、排版美觀且極具說服力的商品詳細介紹文案：\n\n";
        $prompt .= "商品名稱：{$title}\n";
        $prompt .= "商品分類：{$cats_str}\n";
        $prompt .= "商品售價：NT$ {$price}\n";
        if ( ! empty( $keywords ) ) {
            $prompt .= "附加關鍵字（請務必自然地融入文案中）：{$keywords}\n";
        }
        
        $prompt .= "\n請用繁體中文（台灣）撰寫。文案必須包含：\n";
        $prompt .= "1. 吸引人的開場引言，點出商品的吸引力與美味/特色。\n";
        $prompt .= "2. 產品三大核心特色（用帶有適當 Emoji 的小標題呈現，例如：🔥、✨、🍲 等，排版使用 <h3> 標題與段落，且內文點列出亮點）。\n";
        $prompt .= "3. 食用方式或使用建議（讓消費者產生具體使用情境）。\n";
        $prompt .= "4. 配送與保存說明（如：冷凍保存、產地說明等）。\n\n";
        $prompt .= "【重要限制】\n";
        $prompt .= "- 請直接輸出最乾淨、立即可用的 HTML 格式代碼（使用 <h3>, <p>, <ul>, <li>, <strong> 等標籤），不要包含任何 markdown 語法包裝（絕對不要使用 ```html 或 ``` 等標記開頭結尾，只要最純粹的 HTML 代碼，方便直接寫入編輯器中）。";

        $api_res = ckc_call_gemini_api( $prompt );
        if ( ! empty( $api_res ) ) {
            $html_content = preg_replace( '/```html/i', '', $api_res );
            $html_content = preg_replace( '/```/i', '', $html_content );
            $html_content = trim( $html_content );
        } else {
            $api_failed = true;
        }
    }

    // --- B. 沙盒/備份/金鑰失效降級模式 ---
    if ( empty( $api_key ) || $api_failed ) {
        $warning_banner = '';
        if ( $api_failed ) {
            $warning_banner = '<div style="background: #fffbeb; border: 1px solid #fef3c7; color: #b45309; padding: 12px; border-radius: 6px; margin-bottom: 15px; font-weight: 500; font-size: 13px; display: flex; align-items: center; gap: 8px;"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink: 0;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg><span>提示：您的 Gemini API 金鑰目前額度已超限或暫時失效 (429)。系統已自動啟動「備用智慧文案引擎」為您產生高品質文案！</span></div>';
        }
        
        $kw_badges = '';
        if ( ! empty( $keywords ) ) {
            $kw_list = explode( ',', $keywords );
            foreach ( $kw_list as $kw ) {
                $kw_badges .= ' #' . trim($kw);
            }
        }
        
        $html_content = $warning_banner;
        $html_content .= "<h3>🌟 經典首選，滿足您對美食的極致渴望！</h3>\n";
        $html_content .= "<p>為您隆重獻上<strong>【{$title}】</strong>！精選上等食材，融合主廚獨家調味，無論是家庭圍爐、朋友聚餐，還是個人的精緻餐點，都是您絕不能錯過的美味指南。{$kw_badges}</p>\n\n";
        
        $html_content .= "<h3>🔥 產品三大核心亮點</h3>\n";
        $html_content .= "<ul>\n";
        $html_content .= "  <li><strong>✨ 頂級食材嚴選</strong>：從源頭嚴格控管食材品質，口口吃得到真實好料，口感扎實有層次，香氣四溢。</li>\n";
        $html_content .= "  <li><strong>🍲 主廚研發秘製配方</strong>：傳承潮港城宴會等級經典風味，完美調和比例，回甘不膩，令人吮指回味。</li>\n";
        $html_content .= "  <li><strong>急速冷凍真空包裝</strong>：採用先進急速冷凍技術，鎖住第一手現做美味與極致鮮度，簡單加熱即刻享用。</li>\n";
        $html_content .= "</ul>\n\n";
        
        $html_content .= "<h3>💡 食用與加熱建議</h3>\n";
        $html_content .= "<p>1. 退冰方法：食用前一晚放置於冰箱冷藏室自然解凍，或置於常溫水流水解凍。<br/>\n";
        $html_content .= "2. 加熱方式：解凍後將內容物倒入鍋中，以中火加熱至沸騰即可；亦可放入蒸籠或電鍋中，外鍋加入一杯水蒸熟後即可食用。</p>\n\n";
        
        $html_content .= "<h3>🚚 保存與配送細節</h3>\n";
        $html_content .= "<p>- <strong>保存期限</strong>：冷凍 -18°C 以下保存 12 個月，開封後請儘速食用完畢。<br/>\n";
        $html_content .= "- <strong>配送方式</strong>：本商品全程採用低溫冷凍宅配，保證商品送到您手中時維持最新鮮的品質。</p>";
    }

    wp_send_json_success( array( 'content' => $html_content ) );
}

/**
 * ============================================================================
 * Custom WooCommerce Checkout Options & ECPay Integration
 * ============================================================================
 */

// 1. Hook to save chosen payment metadata
add_action( 'woocommerce_checkout_update_order_meta', 'chao_save_chosen_payment_meta' );
function chao_save_chosen_payment_meta( $order_id ) {
    if ( ! empty( $_POST['chao_chosen_payment_method'] ) ) {
        $payment_method = sanitize_text_field( $_POST['chao_chosen_payment_method'] );
        $ecpay_val = 'ALL';
        if ( $payment_method === 'credit' || $payment_method === 'unionpay' || $payment_method === 'googlepay' ) {
            $ecpay_val = 'Credit';
        } elseif ( $payment_method === 'linepay' ) {
            $ecpay_val = 'TWQR';
        } elseif ( $payment_method === 'atm' ) {
            $ecpay_val = 'ATM';
        } elseif ( $payment_method === 'cvscode' ) {
            $ecpay_val = 'CVS';
        }
        update_post_meta( $order_id, 'chao_chosen_payment', $ecpay_val );
    }
}

// 1.05 Force enable MyDyBox CVS module and sync API keys with ECPay official plugin settings
add_filter( 'option_mydybox_cvs_enabled', 'chao_force_mydybox_cvs_enabled' );
function chao_force_mydybox_cvs_enabled( $value ) {
    return 'yes';
}

add_filter( 'option_mydybox_cvs_test_mode', 'chao_mydybox_cvs_test_mode' );
function chao_mydybox_cvs_test_mode( $value ) {
    $stage = get_option( 'wooecpay_enabled_payment_stage', get_option( 'wooecpay_enabled_logistic_stage', 'no' ) );
    return ( $stage === 'yes' || $stage === '1' || $stage === 1 ) ? 'yes' : 'no';
}

add_filter( 'option_mydybox_cvs_merchant_id', 'chao_mydybox_cvs_merchant_id' );
function chao_mydybox_cvs_merchant_id( $value ) {
    $mid = get_option( 'wooecpay_payment_mid', get_option( 'wooecpay_logistic_mid' ) );
    return $mid ? $mid : $value;
}

add_filter( 'option_mydybox_cvs_hash_key', 'chao_mydybox_cvs_hash_key' );
function chao_mydybox_cvs_hash_key( $value ) {
    $key = get_option( 'wooecpay_payment_hashkey', get_option( 'wooecpay_logistic_hashkey' ) );
    return $key ? $key : $value;
}

add_filter( 'option_mydybox_cvs_hash_iv', 'chao_mydybox_cvs_hash_iv' );
function chao_mydybox_cvs_hash_iv( $value ) {
    $iv = get_option( 'wooecpay_payment_hashiv', get_option( 'wooecpay_logistic_hashiv' ) );
    return $iv ? $iv : $value;
}

// Manually register MyDyBox AJAX actions since the option filters run after plugins are loaded
if ( class_exists( 'Mydybox\Modules\Checkout_Tw\CVS_Shipping' ) ) {
    $chao_mydybox_cvs = new \Mydybox\Modules\Checkout_Tw\CVS_Shipping();
    add_action( 'wp_ajax_mydybox_open_cvs_map', array( $chao_mydybox_cvs, 'ajax_open_cvs_map' ) );
    add_action( 'wp_ajax_nopriv_mydybox_open_cvs_map', array( $chao_mydybox_cvs, 'ajax_open_cvs_map' ) );
    add_action( 'wp_ajax_mydybox_cvs_map_callback', array( $chao_mydybox_cvs, 'ajax_map_callback' ) );
    add_action( 'wp_ajax_nopriv_mydybox_cvs_map_callback', array( $chao_mydybox_cvs, 'ajax_map_callback' ) );
}

// 1.1 Hook to save CVS store metadata to order
add_action( 'woocommerce_checkout_update_order_meta', 'chao_save_cvs_store_meta' );
function chao_save_cvs_store_meta( $order_id ) {
    $store_id   = isset( $_POST['mydybox_cvs_store_id'] )   ? sanitize_text_field( $_POST['mydybox_cvs_store_id'] )   : '';
    $store_name = isset( $_POST['mydybox_cvs_store_name'] ) ? sanitize_text_field( $_POST['mydybox_cvs_store_name'] ) : '';
    $store_addr = isset( $_POST['mydybox_cvs_store_addr'] ) ? sanitize_text_field( $_POST['mydybox_cvs_store_addr'] ) : '';
    $store_type = isset( $_POST['mydybox_cvs_store_type'] ) ? sanitize_text_field( $_POST['mydybox_cvs_store_type'] ) : '';

    if ( $store_id ) {
        // Mydybox keys
        update_post_meta( $order_id, '_mydybox_cvs_store_id',   $store_id );
        update_post_meta( $order_id, '_mydybox_cvs_store_name', $store_name );
        update_post_meta( $order_id, '_mydybox_cvs_store_addr', $store_addr );
        update_post_meta( $order_id, '_mydybox_cvs_store_type', $store_type );
        
        // ECPay official keys
        update_post_meta( $order_id, '_ecpay_logistic_cvs_store_id',   $store_id );
        update_post_meta( $order_id, '_ecpay_logistic_cvs_store_name', $store_name );
        update_post_meta( $order_id, '_ecpay_logistic_cvs_store_address', $store_addr );
    }
}

// 2. Inject CSS and Javascript on checkout page
require_once get_template_directory() . '/includes/woocommerce/checkout.php';

// Load custom LINE Login module
require_once get_template_directory() . '/includes/line-login.php';
require_once get_template_directory() . '/includes/line-order-notify.php'; // LINE 訂單成功通知（推播到 LINE 群組）
require_once get_template_directory() . '/includes/ckc-referral.php'; // 分潤系統（推薦好友，第一階段點數軌）
require_once get_template_directory() . '/includes/ckc-referral-partner.php'; // 分潤系統（第二階段夥伴現金軌）
require_once get_template_directory() . '/includes/ckc-referral-admin.php'; // 分潤系統（後台夥伴管理頁）
require_once get_template_directory() . '/includes/ckc-referral-tier-admin.php'; // 分潤系統（商品階梯式分潤分類設定）
require_once get_template_directory() . '/includes/ckc-coupons.php'; // 折扣券（領券中心＋專屬優惠券頁）
require_once get_template_directory() . '/includes/ckc-points-admin.php'; // 紅利點數後台管理系統

// Load custom ECPay ECPg 2.0 (站內付 2.0) Payment Gateway
require_once get_template_directory() . '/includes/ecpay-ecpg-gateway.php';

require_once get_template_directory() . '/includes/woocommerce/cart.php';
require_once get_template_directory() . '/includes/woocommerce/checkout-ux.php';
require_once get_template_directory() . '/includes/woocommerce/order-savings.php'; // 購物車／結帳頁「此訂單省了多少」

// 首頁模塊化編輯器（後台可自行新增/刪除/排序首頁區塊）
require_once get_template_directory() . '/includes/admin/homepage-builder.php';
require_once get_template_directory() . '/includes/homepage-modules-render.php';

// 分類頁／商店主頁 Banner 圖片管理（掛在 WooCommerce 編輯分類頁、商店頁編輯畫面）
require_once get_template_directory() . '/includes/admin/category-banner.php';

// 後台 UI/UX 全站統一風格（色彩／圖示系統，套用於整個 wp-admin，不限自建頁面）
require_once get_template_directory() . '/includes/admin/admin-ui-theme.php';

// 網站 Logo 後台替換功能（「首頁」子選單，顯示尺寸統一 240×80）
require_once get_template_directory() . '/includes/admin/site-logo.php';

// 商品「顯示排序」管理頁（「商品」子選單，拖曳調整前台預設排序用的 menu_order）
require_once get_template_directory() . '/includes/admin/product-order.php';

/**
 * Unhook automatic brand output from WC_Brands to prevent duplication
 */
add_action( 'wp', 'chao_gang_cheng_remove_brands_hook', 99 );
function chao_gang_cheng_remove_brands_hook() {
    if ( class_exists( 'WC_Brands' ) ) {
        // Try removing action with class name string
        remove_action( 'woocommerce_product_meta_end', array( 'WC_Brands', 'show_brand' ) );
        
        // Try removing action if registered via global instance
        if ( isset( $GLOBALS['WC_Brands'] ) ) {
            remove_action( 'woocommerce_product_meta_end', array( $GLOBALS['WC_Brands'], 'show_brand' ) );
        }
        if ( isset( $GLOBALS['wc_brands'] ) ) {
            remove_action( 'woocommerce_product_meta_end', array( $GLOBALS['wc_brands'], 'show_brand' ) );
        }
    }
}

/**
 * 31i. 訪客流量來源偵測與 Cookie 記錄
 */
add_action( 'init', 'ckc_track_visitor_source' );
function ckc_track_visitor_source() {
    if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
        return;
    }

    // 如果已經有來源 Cookie，就不重複寫入，保持第一次入站的來源 (First Touch)
    if ( isset( $_COOKIE['ckc_landing_source'] ) ) {
        return;
    }

    $source = '';

    // 1. 優先檢查 URL 中的 UTM 參數
    if ( isset( $_GET['utm_source'] ) && ! empty( $_GET['utm_source'] ) ) {
        $utm_source = sanitize_text_field( $_GET['utm_source'] );
        $utm_lower = strtolower( $utm_source );
        if ( strpos( $utm_lower, 'facebook' ) !== false || $utm_lower === 'fb' ) {
            $source = 'Facebook';
        } elseif ( strpos( $utm_lower, 'line' ) !== false ) {
            $source = 'LINE';
        } elseif ( strpos( $utm_lower, 'instagram' ) !== false || $utm_lower === 'ig' ) {
            $source = 'Instagram';
        } elseif ( strpos( $utm_lower, 'google' ) !== false ) {
            $source = 'Google 廣告';
        } else {
            $source = $utm_source;
        }
    }

    // 2. 如果沒有 UTM，檢查 HTTP Referer (引薦來源)
    if ( empty( $source ) && isset( $_SERVER['HTTP_REFERER'] ) && ! empty( $_SERVER['HTTP_REFERER'] ) ) {
        $referer = $_SERVER['HTTP_REFERER'];
        $referer_host = parse_url( $referer, PHP_URL_HOST );
        $referer_host = strtolower( $referer_host );

        $home_host = parse_url( home_url(), PHP_URL_HOST );
        $home_host = strtolower( $home_host );

        if ( $referer_host && $referer_host !== $home_host ) {
            if ( strpos( $referer_host, 'facebook.com' ) !== false || strpos( $referer_host, 'fb.com' ) !== false ) {
                $source = 'Facebook';
            } elseif ( strpos( $referer_host, 'instagram.com' ) !== false || strpos( $referer_host, 'ig.com' ) !== false ) {
                $source = 'Instagram';
            } elseif ( strpos( $referer_host, 'line.me' ) !== false ) {
                $source = 'LINE';
            } elseif ( strpos( $referer_host, 'google.' ) !== false ) {
                $source = 'Google 搜尋';
            } elseif ( strpos( $referer_host, 'yahoo.' ) !== false ) {
                $source = 'Yahoo 搜尋';
            } elseif ( strpos( $referer_host, 'shopee.' ) !== false ) {
                $source = '蝦皮購物';
            } elseif ( strpos( $referer_host, 'youtube.com' ) !== false || strpos( $referer_host, 'youtu.be' ) !== false ) {
                $source = 'YouTube';
            } else {
                $source = $referer_host;
            }
        }
    }

    // 3. 如果皆無，不主動設定 Cookie（讓註冊時判定為直接造訪或後台新增）
    if ( empty( $source ) ) {
        return;
    }

    // 設定 Cookie，保存 30 天
    setcookie( 'ckc_landing_source', $source, time() + ( 86400 * 30 ), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
}

/**
 * 31j. 用戶註冊時儲存來源資料到 User Meta
 */
add_action( 'user_register', 'ckc_save_user_source_on_register', 10, 1 );
function ckc_save_user_source_on_register( $user_id ) {
    $source = '';
    if ( isset( $_COOKIE['ckc_landing_source'] ) ) {
        $source = sanitize_text_field( $_COOKIE['ckc_landing_source'] );
    }

    if ( empty( $source ) && ! is_admin() ) {
        $source = '直接造訪';
    } elseif ( empty( $source ) && is_admin() ) {
        $source = '後台手動新增';
    }

    update_user_meta( $user_id, 'ckc_user_source', $source );
    
    // 重新計算顧客標籤以納入來源
    ckc_recalculate_customer_tags( $user_id );
}

/**
 * 31k. 訂單成立時，自動偵測並存入訂單流量來源
 */
add_action( 'woocommerce_checkout_create_order', 'ckc_save_order_source_on_checkout', 10, 2 );
function ckc_save_order_source_on_checkout( $order, $data ) {
    $source = '';
    
    // 優先從 Cookie 取得流量來源
    if ( isset( $_COOKIE['ckc_landing_source'] ) ) {
        $source = sanitize_text_field( $_COOKIE['ckc_landing_source'] );
    }
    
    // 如果 Cookie 沒存到且用戶已登入，可從用戶資料中繼承來源
    if ( empty( $source ) ) {
        $user_id = $order->get_customer_id();
        if ( $user_id ) {
            $source = get_user_meta( $user_id, 'ckc_user_source', true );
        }
    }
    
    // 如果還是沒有來源，判斷為直接造訪
    if ( empty( $source ) ) {
        $source = '直接造訪';
    }
    
    $order->update_meta_data( 'ckc_order_source', $source );
}

/**
 * 31l. 在後台訂單列表（傳統列表與 HPOS）中新增「訂單來源」欄位
 */
add_filter( 'manage_edit-shop_order_columns', 'ckc_add_order_source_column', 20 );
add_filter( 'manage_woocommerce_page_wc-orders_columns', 'ckc_add_order_source_column', 20 );
function ckc_add_order_source_column( $columns ) {
    $new = array();
    foreach ( $columns as $key => $label ) {
        $new[ $key ] = $label;
        if ( 'order_date' === $key ) { // 將來源欄位放在日期前面
            $new['ckc_order_source'] = '訂單來源';
        }
    }
    if ( ! isset( $new['ckc_order_source'] ) ) {
        $new['ckc_order_source'] = '訂單來源';
    }
    return $new;
}

add_action( 'manage_shop_order_posts_custom_column', 'ckc_render_order_source_column_content', 20, 2 );
add_action( 'manage_woocommerce_page_wc-orders_custom_column', 'ckc_render_order_source_column_content', 20, 2 );
function ckc_render_order_source_column_content( $column, $order_or_id ) {
    if ( 'ckc_order_source' !== $column ) {
        return;
    }
    $order = is_object( $order_or_id ) ? $order_or_id : wc_get_order( $order_or_id );
    if ( ! $order ) {
        return;
    }
    
    $source = $order->get_meta( 'ckc_order_source' );
    if ( ! $source ) {
        // 試著從顧客資料繼承
        $user_id = $order->get_customer_id();
        if ( $user_id ) {
            $source = get_user_meta( $user_id, 'ckc_user_source', true );
        }
    }
    if ( ! $source ) {
        $source = '直接造訪';
    }
    
    // 定義不同來源的顏色樣式
    $styles = array(
        'Facebook' => 'background-color: #e8f4fd; color: #1d9bf0; border: 1px solid #b3dbf7;',
        'LINE' => 'background-color: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0;',
        'Instagram' => 'background-color: #fdf2f8; color: #db2777; border: 1px solid #fbcfe8;',
        'Google 搜尋' => 'background-color: #f5f3ff; color: #7c3aed; border: 1px solid #ddd6fe;',
        'Google 廣告' => 'background-color: #e0e7ff; color: #4f46e5; border: 1px solid #c7d2fe;',
        '直接造訪' => 'background-color: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;',
        '後台手動新增' => 'background-color: #fff7ed; color: #ea580c; border: 1px solid #ffedd5;',
    );
    
    $style = isset( $styles[$source] ) ? $styles[$source] : 'background-color: #ecfeff; color: #0891b2; border: 1px solid #a5f3fc;';
    
    echo sprintf(
        '<span style="padding: 4px 8px; font-size: 11px; font-weight: 500; border-radius: 4px; display: inline-block; %s">%s</span>',
        $style,
        esc_html( $source )
    );
}

/**
 * 31m. 在後台編輯訂單頁面（帳單資訊下方）顯示流量來源標籤
 */
add_action( 'woocommerce_admin_order_data_after_billing_address', 'ckc_display_order_source_in_admin', 10, 1 );
function ckc_display_order_source_in_admin( $order ) {
    $source = $order->get_meta( 'ckc_order_source' );
    if ( ! $source ) {
        // 試著從顧客資料繼承
        $user_id = $order->get_customer_id();
        if ( $user_id ) {
            $source = get_user_meta( $user_id, 'ckc_user_source', true );
        }
    }
    if ( ! $source ) {
        $source = '直接造訪';
    }
    
    $styles = array(
        'Facebook' => 'background-color: #e8f4fd; color: #1d9bf0; border: 1px solid #b3dbf7;',
        'LINE' => 'background-color: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0;',
        'Instagram' => 'background-color: #fdf2f8; color: #db2777; border: 1px solid #fbcfe8;',
        'Google 搜尋' => 'background-color: #f5f3ff; color: #7c3aed; border: 1px solid #ddd6fe;',
        'Google 廣告' => 'background-color: #e0e7ff; color: #4f46e5; border: 1px solid #c7d2fe;',
        '直接造訪' => 'background-color: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;',
        '後台手動新增' => 'background-color: #fff7ed; color: #ea580c; border: 1px solid #ffedd5;',
    );
    
    $style = isset( $styles[$source] ) ? $styles[$source] : 'background-color: #ecfeff; color: #0891b2; border: 1px solid #a5f3fc;';
    
    echo '<h4>流量來源偵測</h4>';
    echo sprintf(
        '<p><span style="padding: 4px 10px; font-size: 12px; font-weight: bold; border-radius: 4px; display: inline-block; %s">%s</span></p>',
        $style,
        esc_html( $source )
    );
}

/**
 * 檢查並執行紅利點數過期（累積首月起算二年有效並以二年為一期）
 */
function ckc_pts_check_expiration( $user_id ) {
    $start_month = get_user_meta( $user_id, '_ckc_points_start_month', true );
    if ( ! $start_month ) {
        return;
    }
    
    // 起算區間 (例如: 2026-07 -> 2028-06-30 23:59:59，以兩年為一期)
    $start_time = strtotime( $start_month . '-01 00:00:00' );
    $expire_time = strtotime( '+2 years -1 day 23:59:59', $start_time );
    $now = current_time( 'timestamp' );
    
    if ( $now > $expire_time ) {
        global $wpdb;
        $val = $wpdb->get_var( $wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = 'wps_wpr_points'",
            $user_id
        ) );
        $current_pts = $val !== null ? (int) $val : 0;
        
        if ( $current_pts > 0 ) {
            // 歸零更新
            update_user_meta( $user_id, 'wps_wpr_points', 0 );
            
            // 寫入 WPS points_details 日誌
            $details = get_user_meta( $user_id, 'points_details', true );
            if ( ! is_array( $details ) ) { $details = array(); }
            if ( ! isset( $details['admin_points'] ) || ! is_array( $details['admin_points'] ) ) {
                $details['admin_points'] = array();
            }
            $details['admin_points'][] = array(
                'admin_points' => $current_pts,
                'date'         => date_i18n( 'Y-m-d h:i:sa' ),
                'sign'         => '-',
                'reason'       => sprintf( '紅利點數二年到期清除（原額度 %d 點，起算區間：%s）', $current_pts, $start_month ),
            );
            update_user_meta( $user_id, 'points_details', $details );
            
            // 寫入自訂分潤日誌
            $log = get_user_meta( $user_id, '_ckc_ref_log', true );
            if ( ! is_array( $log ) ) { $log = array(); }
            $log[] = array(
                'points' => -$current_pts,
                'reason' => sprintf( '紅利點數二年到期清除（起算區間：%s）', $start_month ),
                'time'   => current_time( 'mysql' )
            );
            update_user_meta( $user_id, '_ckc_ref_log', $log );
        }
        
        // 刪除起算日，下一筆點數累積時會重新起算新的一期
        delete_user_meta( $user_id, '_ckc_points_start_month' );
        clean_user_cache( $user_id );
    }
}

/**
 * 取得會員目前點數餘額（繞過 Object Cache 機制，防範不同步）
 */
function ckc_pts_get_user_balance( $user_id ) {
    global $wpdb;
    
    // 自動檢查點數是否已過期
    ckc_pts_check_expiration( $user_id );
    
    $val = $wpdb->get_var( $wpdb->prepare(
        "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = 'wps_wpr_points'",
        $user_id
    ) );
    return $val !== null ? (int) $val : 0;
}



/**
 * Fix: Bypass WordPress.com Akismet (bkismet) spam check for WooCommerce front-end customer registrations.
 *
 * WordPress.com's built-in anti-spam filter (bkismet_check_signup) is hooked to
 * `wp_pre_insert_user_data` at priority 10. When it detects suspicious traffic
 * (e.g., missing JA3 TLS fingerprint, unusual IP), it returns `false` instead of
 * the user data array. This causes `wp_insert_user()` to return WP_Error('empty_data'),
 * which WooCommerce surfaces as "資料不足，無法建立這個使用者。"
 *
 * Fix strategy:
 *   - Priority  5: Save the pre-bkismet user data into a static variable.
 *   - Priority 20: If bkismet cleared the data AND the request has a valid
 *                  woocommerce-register-nonce, restore the saved data so the
 *                  legitimate customer registration can proceed.
 */
add_filter( 'wp_pre_insert_user_data', 'ckc_save_pre_bkismet_user_data', 5, 1 );
function ckc_save_pre_bkismet_user_data( $data ) {
    if ( ! empty( $data ) && is_array( $data ) ) {
        ckc_pre_bkismet_data_store( $data );
    }
    return $data;
}

add_filter( 'wp_pre_insert_user_data', 'ckc_restore_data_after_bkismet', 20, 2 );
function ckc_restore_data_after_bkismet( $data, $update ) {
    if ( $update || ! empty( $data ) ) {
        return $data;
    }
    $is_wc_register = (
        isset( $_POST['woocommerce-register-nonce'] ) &&
        wp_verify_nonce( sanitize_key( $_POST['woocommerce-register-nonce'] ), 'woocommerce-register' )
    );
    if ( $is_wc_register ) {
        $saved = ckc_pre_bkismet_data_store();
        if ( ! empty( $saved ) && is_array( $saved ) ) {
            return $saved;
        }
    }
    return $data;
}

function ckc_pre_bkismet_data_store( $data = null ) {
    static $stored = null;
    if ( $data !== null ) {
        $stored = $data;
    }
    return $stored;
}


/**
 * Fix: SQLite integration causes wp_insert_user to return ID=0 for new customers.
 *
 * The SQLite integration (used for MailPoet tables) intercepts ALL $wpdb queries.
 * When it encounters a previous error (from unrelated SQLite queries), its internal
 * logic clears insert_id to 0 on subsequent INSERT operations. This causes
 * wp_insert_user() to think the MySQL INSERT failed, even when it actually succeeded.
 *
 * Fix strategy:
 *   1. Before wp_insert_user runs (via woocommerce_new_customer_data priority=100),
 *      clear $wpdb->last_error so the SQLite insert_id-clearing code doesn't fire.
 *   2. After wp_insert_user (via user_register priority=1), if the user_id is 0,
 *      look up the just-created user by email and restore the correct user_id via
 *      a static store, so woocommerce_created_customer fires with the right ID.
 *   3. Hook woocommerce_new_customer_data at 101 to confirm the real ID was found.
 */

// Step 1: Clear SQLite last_error right before the user INSERT runs
add_filter( 'woocommerce_new_customer_data', 'ckc_clear_wpdb_error_before_user_insert', 100 );
function ckc_clear_wpdb_error_before_user_insert( $data ) {
    global $wpdb;
    // Store the email so we can find the user if insert_id comes back as 0
    if ( ! empty( $data['user_email'] ) ) {
        ckc_pending_register_email( $data['user_email'] );
    }
    // Clear any stale SQLite error that would cause insert_id to be zeroed
    if ( ! empty( $wpdb->last_error ) ) {
        $wpdb->last_error = '';
    }
    return $data;
}

// Step 2: If user_id=0 after insert (SQLite bug), look up the real user by email
add_action( 'user_register', 'ckc_fix_zero_user_id_after_register', 1 );
function ckc_fix_zero_user_id_after_register( $user_id ) {
    global $wpdb;
    if ( (int) $user_id !== 0 ) {
        return; // Normal path — ID is valid
    }
    // user_id is 0 → SQLite insert_id bug. Try to find the user in MySQL by email.
    $pending_email = ckc_pending_register_email();
    if ( empty( $pending_email ) ) {
        return;
    }
    $real_id = (int) $wpdb->get_var(
        $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE user_email = %s LIMIT 1", $pending_email )
    );
    if ( $real_id > 0 ) {
        // Store the real user ID so woocommerce_created_customer can use it
        ckc_fixed_user_id_store( $real_id );
        // Run missing post-registration setup that WordPress skipped due to ID=0
        update_user_meta( $real_id, 'wp_capabilities', array( 'customer' => true ) );
        update_user_meta( $real_id, $wpdb->prefix . 'capabilities', array( 'customer' => true ) );
    }
}

// Step 3: Correct the customer_id in woocommerce_created_customer if it was 0
add_action( 'woocommerce_created_customer', 'ckc_fix_zero_wc_customer_id', 1, 3 );
function ckc_fix_zero_wc_customer_id( $customer_id, $new_customer_data, $password_generated ) {
    if ( (int) $customer_id !== 0 ) {
        return; // Normal path
    }
    $real_id = ckc_fixed_user_id_store();
    if ( $real_id > 0 ) {
        // Log in the customer manually since WooCommerce's auto-login got ID=0
        wp_set_auth_cookie( $real_id, false );
        do_action( 'wp_login', get_userdata( $real_id )->user_login, get_userdata( $real_id ) );
        // Fire any remaining post-registration hooks with the real ID
        do_action( 'ckc_after_customer_created', $real_id, $new_customer_data );
    }
}

/** Static store for the email address of the pending registration */
function ckc_pending_register_email( $email = null ) {
    static $stored = '';
    if ( $email !== null ) {
        $stored = $email;
    }
    return $stored;
}

/** Static store for the corrected user ID after SQLite insert_id bug */
function ckc_fixed_user_id_store( $id = null ) {
    static $stored = 0;
    if ( $id !== null ) {
        $stored = (int) $id;
    }
    return $stored;
}

// Bonus points: Give signup bonus to the real user even if customer_id was 0
add_action( 'ckc_after_customer_created', 'ckc_ref_give_signup_bonus_proxy', 10, 2 );
function ckc_ref_give_signup_bonus_proxy( $user_id, $new_customer_data ) {
    // Re-trigger the signup bonus hook with the correct user ID
    do_action( 'woocommerce_created_customer', $user_id, $new_customer_data, false );
}

/**
 * ─────────────────────────────────────────────────────────────────
 * 影片藝廊外掛 (YotuWP) 翻譯設定
 * ─────────────────────────────────────────────────────────────────
 */
add_filter( 'yotuwp_next_text', function() { return '下一頁'; } );
add_filter( 'yotuwp_prev_text', function() { return '上一頁'; } );

/**
 * ─────────────────────────────────────────────────────────────────
 * 變更 WooCommerce 新台幣 (TWD) 貨幣符號
 * ─────────────────────────────────────────────────────────────────
 */
add_filter( 'woocommerce_currency_symbol', 'change_twd_currency_symbol_to_plain_text', 10, 2 );
function change_twd_currency_symbol_to_plain_text( $currency_symbol, $currency ) {
    if ( 'TWD' === $currency ) {
        return 'NT$';
    }
    return $currency_symbol;
}

/**
 * ─────────────────────────────────────────────────────────────────
 * 28. 前端全站效能優化：非必要第三方追蹤腳本延遲載入 + 條件式載入
 * ─────────────────────────────────────────────────────────────────
 * 背景：實測發現首頁載入偏慢（DOMContentLoaded ~4 秒），主因之一是多個
 * 第三方追蹤／整合外掛（Facebook Conversions API、Facebook 像素信號、
 * Google Listings & Ads 的 gtag 事件）的腳本用同步方式載入，且 WooCommerce
 * Product Add-Ons 外掛（含 jQuery UI datepicker）不分頁面全站載入，但實際上
 * 只有商品頁選購附加選項、購物車、結帳頁重新計算金額時才用得到。
 * 這裡改成：(1) 純追蹤類腳本加上 defer，不阻塞首屏渲染；(2) 附加選項相關
 * 腳本改成只在真正需要的頁面才載入。純視覺/行為不變，只改變載入時機。
 */

// 28a. 非關鍵第三方追蹤腳本加上 defer（純追蹤用途，不影響頁面其他功能，
// 加上 defer 後會等 HTML 解析完才依序執行，不再阻塞首屏渲染）。
add_filter( 'script_loader_tag', 'chao_gang_cheng_defer_tracking_scripts', 10, 3 );
function chao_gang_cheng_defer_tracking_scripts( $tag, $handle, $src ) {
    if ( is_admin() ) {
        return $tag;
    }
    $defer_handles = array(
        'facebook-capi-param-builder', // Facebook for WooCommerce：Conversions API 參數建構
        'wc-facebook-signals',         // Facebook for WooCommerce：像素事件信號
        'gla-gtag-events',             // Google Listings & Ads：gtag 轉換事件
    );
    if ( in_array( $handle, $defer_handles, true ) && false === strpos( $tag, ' defer' ) ) {
        $tag = str_replace( ' src=', ' defer src=', $tag );
    }
    return $tag;
}

// 28b. WooCommerce Product Add-Ons 外掛的前端腳本（含 jQuery UI datepicker、
// 驗證庫）改成只在商品頁／購物車／結帳頁載入，其餘頁面（首頁、商店列表、
// 文章等）完全用不到附加選項互動，卻要多載入一整套 jQuery UI，故在這些
// 頁面上停用，減少不必要的下載與執行時間。
add_action( 'wp_enqueue_scripts', 'chao_gang_cheng_conditional_product_addons_assets', 100 );
function chao_gang_cheng_conditional_product_addons_assets() {
    if ( is_product() || is_cart() || is_checkout() ) {
        return; // 這些頁面維持原樣，附加選項功能正常運作
    }
    wp_dequeue_script( 'woocommerce-addons' );
    wp_dequeue_script( 'woocommerce-addons-validation' );
    wp_dequeue_script( 'jquery-ui-datepicker' );
}
// 載入 Facebook for WooCommerce / Google 商品分類中文化腳本
require_once get_template_directory() . '/includes/ckc-google-category-translation.php';
