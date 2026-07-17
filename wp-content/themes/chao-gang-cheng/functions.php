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
		'primary'             => esc_html__( 'Primary Menu', 'chao-gang-cheng' ),
		'footer'              => esc_html__( 'Footer Menu', 'chao-gang-cheng' ),
		'homepage-categories' => esc_html__( 'Homepage Category Showcase', 'chao-gang-cheng' ),
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
 * Update WooCommerce Cart Fragment via AJAX
 */
add_filter( 'woocommerce_add_to_cart_fragments', 'chao_gang_cheng_cart_fragments' );
function chao_gang_cheng_cart_fragments( $fragments ) {
	ob_start();
	?>
	<span class="cart-count"><?php echo esc_html( WC()->cart->get_cart_contents_count() ); ?></span>
	<?php
	$fragments['span.cart-count'] = ob_get_clean();
	
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
            'personal' => '個人電子發票 (會員載具)',
            'carrier'  => '手機條碼載具',
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
    if ( ! is_checkout() && ! is_wc_endpoint_url( 'edit-address' ) && ! is_account_page() ) {
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
            $invoice_type_label = '個人電子發票 (會員載具)';
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
            $invoice_type_label = '個人電子發票 (會員載具)';
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

add_action( 'woocommerce_before_cart', 'chao_gang_cheng_cart_free_shipping_progress' );
function chao_gang_cheng_cart_free_shipping_progress() {
    $threshold = chao_get_free_shipping_threshold();
    // Calculate current subtotal (exclude tax/shipping/discounts if desired, default subtotal is correct)
    $cart_subtotal = WC()->cart->get_subtotal();
    
    if ( $cart_subtotal >= $threshold ) {
        $percent = 100;
        $message = '🎉 太棒了！已符合免運條件，本筆訂單免運費！';
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
 */
add_action( 'admin_init', 'chao_gang_cheng_enforce_registration_settings' );
add_action( 'init', 'chao_gang_cheng_enforce_registration_settings' );
function chao_gang_cheng_enforce_registration_settings() {
    if ( get_option( 'woocommerce_enable_myaccount_registration' ) !== 'yes' ) {
        update_option( 'woocommerce_enable_myaccount_registration', 'yes' );
    }
    if ( get_option( 'woocommerce_registration_generate_username' ) !== 'yes' ) {
        update_option( 'woocommerce_registration_generate_username', 'yes' );
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

    if ( ! $show_sold && $hide_heritage ) {
        return; // Nothing to show for this product
    }
    ?>
    <div class="chao-social-proof" style="display: flex; flex-wrap: wrap; gap: 8px; margin: 4px 0 14px;">
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
    woocommerce_wp_text_field( array(
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
 * Add-on Purchase Zone
 */
add_action( 'woocommerce_after_single_product_summary', 'chao_gang_cheng_addon_purchase_section', 5 );
function chao_gang_cheng_addon_purchase_section() {
    // Query up to 6 products to display in the slider addons (excluding current product)
    // Use date-based ordering instead of 'rand' (avoids costly ORDER BY RAND() full table scan)
    // Focused add-on selection: only complementary food categories, so the block
    // matches its「主廚經典手路菜」copy (no skincare / apparel / tickets).
    $addon_categories = apply_filters( 'chao_addon_zone_categories', array( 'frozen', 'side-dishes' ) );
    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => 6,
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
                        $discount = 20; // 20 TWD discount for addons
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
        <span style="font-size: 16px; line-height: 1;">🎁</span>
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
                $('#username').attr('placeholder', '請輸入您的使用者名稱或電子郵件');
                $('#password').attr('placeholder', '請輸入您的密碼');
                // Add placeholders to register fields if present
                $('#reg_email').attr('placeholder', '請輸入您的電子郵件地址');
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
        .woocommerce-Address-title {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            margin-bottom: 20px !important;
            border-bottom: 1px solid #f1f5f9 !important;
            padding-bottom: 12px !important;
        }
        
        .woocommerce-Address-title h3 {
            margin: 0 !important;
            font-size: 18px !important;
            font-weight: 700 !important;
            color: #1e293b !important;
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
        
        /* Address card padding and look overrides */
        .woocommerce-Address,
        .woocommerce-Addresses .col-1,
        .woocommerce-Addresses .col-2 {
            background: #ffffff !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 12px !important;
            padding: 24px !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02) !important;
            box-sizing: border-box !important;
            transition: all 0.3s ease !important;
        }
        
        .woocommerce-Address address {
            font-style: normal !important;
            line-height: 1.6 !important;
            color: #475569 !important;
            font-size: 14px !important;
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
            array( '🎁 註冊成功即可獲得 ', ' 點紅利點數（1 點可折抵 NT$1）！' ),
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
                $(this).text('🎁 註冊成功即可獲得 ' + pts + ' 點紅利點數（1 點可折抵 NT$1）！');
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
    ?>
    <div style="background: #fdfaf7; border: 1px solid #f5ebe6; border-radius: 8px; padding: 14px 16px; margin-bottom: 20px;">
        <div style="font-size: 13px; font-weight: 700; color: #7f6c60; margin-bottom: 8px;">加入會員專屬好處</div>
        <ul style="margin: 0; padding: 0; list-style: none; font-size: 13px; color: #6b7280; line-height: 2;">
            <li>🎁 紅利點數回饋，1 點可折抵 NT$1</li>
            <li>💰 消費享 1% 現金回饋</li>
            <li>📦 訂單查詢、收藏清單、下次結帳免填資料</li>
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
 * Fetch and cache latest YouTube videos from a specific channel using RSS feed
 */
function chao_gang_cheng_get_youtube_videos() {
    $transient_key = 'chao_gang_cheng_youtube_feed';
    $videos = get_transient( $transient_key );
    
    if ( false === $videos ) {
        $feed_url = 'https://www.youtube.com/feeds/videos.xml?channel_id=UCICXOKIAEFoX0ZZEkKdkbHA';
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
    
    // Set Points menu item to "紅利點數" and position it before Logout
    if ( isset( $items['points'] ) ) {
        $items['points'] = '紅利點數';
    } else {
        $logout = isset( $items['customer-logout'] ) ? $items['customer-logout'] : '';
        unset( $items['customer-logout'] );
        $items['points'] = '紅利點數';
        if ( $logout ) {
            $items['customer-logout'] = $logout;
        }
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

    $points = (int) get_user_meta( $user_id, 'wps_wpr_points', true );

    $recent_orders = wc_get_orders( array(
        'customer_id' => $user_id,
        'limit'       => 3,
        'orderby'     => 'date',
        'order'       => 'DESC',
    ) );

    $card_style  = 'background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);';
    $title_style = 'font-size: 14px; font-weight: 700; color: #64748b; margin: 0 0 12px; letter-spacing: 0.5px;';
    ?>
    <style>
    .chao-account-overview { display: grid; grid-template-columns: 1fr 2fr; gap: 16px; margin: 20px 0; }
    @media (max-width: 768px) { .chao-account-overview { grid-template-columns: 1fr; } }
    .chao-account-quicklinks { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 10px; }
    .chao-account-quicklinks a {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 9px 18px; border: 1px solid #e2e8f0; border-radius: 20px;
        background: #fff; color: #374151; font-size: 13px; font-weight: 600; text-decoration: none;
        transition: border-color .2s ease, color .2s ease;
    }
    .chao-account-quicklinks a:hover { border-color: #7f6c60; color: #7f6c60; }
    .chao-account-order-row { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px dashed #e2e8f0; font-size: 14px; }
    .chao-account-order-row:last-child { border-bottom: none; }
    .chao-order-status { font-size: 12px; padding: 3px 10px; border-radius: 12px; background: #f1f5f9; color: #475569; white-space: nowrap; }
    </style>

    <div class="chao-account-overview">
        <div style="<?php echo esc_attr( $card_style ); ?>">
            <p style="<?php echo esc_attr( $title_style ); ?>">🎁 紅利點數</p>
            <div style="font-size: 32px; font-weight: 700; color: #7f6c60; line-height: 1.2;"><?php echo esc_html( number_format( $points ) ); ?> <span style="font-size: 14px; color: #94a3b8;">點</span></div>
            <p style="font-size: 12px; color: #94a3b8; margin: 8px 0 14px;">1 點可折抵 NT$1，結帳時直接折抵</p>
            <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'points' ) ); ?>" style="font-size: 13px; color: #7f6c60; font-weight: 600; text-decoration: underline;">查看點數紀錄 →</a>
        </div>

        <div style="<?php echo esc_attr( $card_style ); ?>">
            <p style="<?php echo esc_attr( $title_style ); ?>">📦 近期訂單</p>
            <?php if ( ! empty( $recent_orders ) ) : ?>
                <?php foreach ( $recent_orders as $order ) : ?>
                    <div class="chao-account-order-row">
                        <a href="<?php echo esc_url( $order->get_view_order_url() ); ?>" style="color: #1e293b; font-weight: 600; text-decoration: none;">#<?php echo esc_html( $order->get_order_number() ); ?></a>
                        <span style="color: #94a3b8; font-size: 13px;"><?php echo esc_html( $order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y/m/d' ) : '' ); ?></span>
                        <span class="chao-order-status"><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></span>
                        <span style="font-weight: 700; color: #1e293b;"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span>
                    </div>
                <?php endforeach; ?>
                <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>" style="display: inline-block; margin-top: 12px; font-size: 13px; color: #7f6c60; font-weight: 600; text-decoration: underline;">查看全部訂單 →</a>
            <?php else : ?>
                <p style="font-size: 14px; color: #64748b; margin: 0 0 12px;">還沒有任何訂單，來看看主廚為您準備了什麼吧！</p>
                <a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" style="display: inline-block; background-color: #7f6c60; color: #fff; border-radius: 20px; padding: 8px 24px; text-decoration: none; font-size: 13px; font-weight: 600;">前往商店選購</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="chao-account-quicklinks">
        <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">📦 我的訂單</a>
        <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'downloads' ) ); ?>">❤️ 收藏清單</a>
        <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-address' ) ); ?>">🏠 收件地址</a>
        <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-account' ) ); ?>">⚙️ 帳戶資料</a>
        <a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">🛒 繼續購物</a>
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
    $points = (int) get_user_meta( $user_id, 'wps_wpr_points', true );
    
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
    ?>
    <div class="woocommerce-MyAccount-points-card" style="background-color: white; border: 1px solid #e4e7eb; border-radius: 6px; padding: 25px 30px; display: flex; align-items: center; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); min-height: 80px;">
        <div style="font-size: 16px; color: #111827; font-weight: 600; display: flex; align-items: center; flex-wrap: wrap; gap: 8px;">
            <span style="color: #000; font-size: 18px; font-weight: bold; letter-spacing: 0.5px;">可用點數總計</span>
            <span style="color: #f28b82; font-size: 24px; font-weight: bold; margin-left: 10px; margin-right: 2px;"><?php echo esc_html( $points ); ?></span>
            <span style="color: #f28b82; font-size: 24px; font-weight: bold; margin-right: 10px;">點</span>
            <span style="color: #7f8c8d; font-size: 14px; font-weight: normal; margin-top: 4px;">(等同於NT$<?php echo esc_html( $money_val ); ?>)</span>
        </div>
    </div>
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

// 17. Register Homepage Customizer Settings for the Top Banner
add_action( 'customize_register', 'chao_gang_cheng_customize_register' );
function chao_gang_cheng_customize_register( $wp_customize ) {
    $wp_customize->add_section( 'chao_gang_cheng_banner_section', array(
        'title'      => '首頁頂部 Banner 設定',
        'priority'   => 30,
    ) );

    $fields = array(
        'ckc_banner_image' => array(
            'label'    => 'Banner 背景圖片',
            'type'     => 'image',
            'default'  => get_template_directory_uri() . '/assets/images/slide-buffet.jpg',
        ),
        'ckc_banner_top_sub' => array(
            'label'    => '頂部子標題 1 (如：【太陽百匯 SOLIS BUFFET】)',
            'type'     => 'text',
            'default'  => '【太陽百匯 SOLIS BUFFET】',
        ),
        'ckc_banner_sub2' => array(
            'label'    => '頂部子標題 2 (如：華麗盛宴・盡享海陸頂級美味)',
            'type'     => 'text',
            'default'  => '華麗盛宴・盡享海陸頂級美味',
        ),
        'ckc_banner_center_slogan' => array(
            'label'    => '中間主標語 (如：豪華龍蝦、生蠔、和牛、刺身)',
            'type'     => 'text',
            'default'  => '豪華龍蝦、生蠔、和牛、刺身',
        ),
        'ckc_banner_badge' => array(
            'label'    => '活動標籤 (如：限定活動)',
            'type'     => 'text',
            'default'  => '限定活動',
        ),
        'ckc_banner_sub_slogan' => array(
            'label'    => '活動副標語 (如：全新呈獻！)',
            'type'     => 'text',
            'default'  => '全新呈獻！',
        ),
        'ckc_banner_title' => array(
            'label'    => '下殺標題 (如：太陽百匯美食饗宴・平日單人餐券限時下殺)',
            'type'     => 'text',
            'default'  => '太陽百匯美食饗宴・平日單人餐券限時下殺',
        ),
        'ckc_banner_desc' => array(
            'label'    => '底部介紹文字',
            'type'     => 'textarea',
            'default'  => '台中吃到飽首選！鮮美海鮮、現切和牛、各國百匯佳餚，即刻搶購享最優折扣！',
        ),
        'ckc_banner_link' => array(
            'label'    => 'Banner 連結網址',
            'type'     => 'text',
            'default'  => '',
        ),
    );

    foreach ( $fields as $id => $data ) {
        $wp_customize->add_setting( $id, array(
            'default'   => $data['default'],
            'transport' => 'refresh',
            'sanitize_callback' => 'sanitize_text_field',
        ) );

        if ( $data['type'] === 'image' ) {
            $wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, $id, array(
                'label'    => $data['label'],
                'section'  => 'chao_gang_cheng_banner_section',
                'settings' => $id,
            ) ) );
        } elseif ( $data['type'] === 'textarea' ) {
            $wp_customize->add_control( $id, array(
                'label'    => $data['label'],
                'section'  => 'chao_gang_cheng_banner_section',
                'type'     => 'textarea',
                'settings' => $id,
            ) );
        } else {
            $wp_customize->add_control( $id, array(
                'label'    => $data['label'],
                'section'  => 'chao_gang_cheng_banner_section',
                'type'     => 'text',
                'settings' => $id,
            ) );
        }
    }

    // Add section for monthly promos
    $wp_customize->add_section( 'chao_gang_cheng_promo_section', array(
        'title'      => '首頁促銷活動設定',
        'priority'   => 35,
    ) );

    $promos = array(
        1 => array(
            'text_default'  => '🔥 限時特惠｜太陽百匯平日單人餐券任選 3 張，結帳即享 95 折優惠！',
            'color_default' => '#FFE8CC',
            'label'         => '第一列活動',
        ),
        2 => array(
            'text_default'  => '🍲 本月限定｜招牌冷凍食品＋下酒菜任選 3 件 95 折，急速冷凍配送到家！',
            'color_default' => '#E8FFF6',
            'label'         => '第二列活動',
        ),
        3 => array(
            'text_default'  => '🍺 老饕最愛｜獨享紅燒牛肉爐＋經典老滷系列任選 2 件即享 9 折限時搶購！',
            'color_default' => '#FFECEC',
            'label'         => '第三列活動',
        ),
    );

    foreach ( $promos as $num => $data ) {
        // Text Setting
        $wp_customize->add_setting( "ckc_promo_text_{$num}", array(
            'default'           => $data['text_default'],
            'transport'         => 'refresh',
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        $wp_customize->add_control( "ckc_promo_text_{$num}", array(
            'label'    => "{$data['label']} - 文字內容",
            'section'  => 'chao_gang_cheng_promo_section',
            'type'     => 'text',
        ) );

        // Link Setting
        $wp_customize->add_setting( "ckc_promo_link_{$num}", array(
            'default'           => '',
            'transport'         => 'refresh',
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        $wp_customize->add_control( "ckc_promo_link_{$num}", array(
            'label'    => "{$data['label']} - 連結網址",
            'section'  => 'chao_gang_cheng_promo_section',
            'type'     => 'text',
        ) );

        // Color Setting
        $wp_customize->add_setting( "ckc_promo_color_{$num}", array(
            'default'           => $data['color_default'],
            'transport'         => 'refresh',
            'sanitize_callback' => 'sanitize_hex_color',
        ) );
        $wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, "ckc_promo_color_{$num}", array(
            'label'    => "{$data['label']} - 背景顏色",
            'section'  => 'chao_gang_cheng_promo_section',
        ) ) );
    }

    // Add section for inter-category banners
    $wp_customize->add_section( 'chao_gang_cheng_cat_banner_section', array(
        'title'      => '首頁分類間 Banner 設定',
        'priority'   => 38,
        'description'=> '設定首頁各個商品分類區塊之間的廣告 Banner（可自由啟用或停用每個間隔的 Banner）',
    ) );

    for ( $i = 1; $i <= 5; $i++ ) {
        // Enable Setting
        $wp_customize->add_setting( "ckc_cat_banner_enable_{$i}", array(
            'default'           => true,
            'transport'         => 'refresh',
            'sanitize_callback' => 'absint',
        ) );
        $wp_customize->add_control( "ckc_cat_banner_enable_{$i}", array(
            'label'    => "啟用第 {$i} 個分類後的 Banner",
            'section'  => 'chao_gang_cheng_cat_banner_section',
            'type'     => 'checkbox',
        ) );

        // Image Setting
        $wp_customize->add_setting( "ckc_cat_banner_img_{$i}", array(
            'default'           => '',
            'transport'         => 'refresh',
            'sanitize_callback' => 'esc_url_raw',
        ) );
        $wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, "ckc_cat_banner_img_{$i}", array(
            'label'    => "第 {$i} 個分類後的 Banner 圖片",
            'section'  => 'chao_gang_cheng_cat_banner_section',
        ) ) );

        // Link Setting
        $wp_customize->add_setting( "ckc_cat_banner_link_{$i}", array(
            'default'           => '',
            'transport'         => 'refresh',
            'sanitize_callback' => 'esc_url_raw',
        ) );
        $wp_customize->add_control( "ckc_cat_banner_link_{$i}", array(
            'label'    => "第 {$i} 個分類後的 Banner 連結網址",
            'section'  => 'chao_gang_cheng_cat_banner_section',
            'type'     => 'text',
        ) );
    }

    // Add section for Homepage News Banner
    $wp_customize->add_section( 'chao_gang_cheng_news_banner_section', array(
        'title'      => '首頁新聞 Banner 設定',
        'priority'   => 39,
        'description'=> '設定首頁最新消息/新聞區塊上方的廣告 Banner（圖片建議尺寸：1200X300）',
    ) );

    // Enable Setting
    $wp_customize->add_setting( 'ckc_news_banner_enable', array(
        'default'           => true,
        'transport'         => 'refresh',
        'sanitize_callback' => 'absint',
    ) );
    $wp_customize->add_control( 'ckc_news_banner_enable', array(
        'label'    => '啟用新聞區塊 Banner',
        'section'  => 'chao_gang_cheng_news_banner_section',
        'type'     => 'checkbox',
    ) );

    // Image Setting
    $wp_customize->add_setting( 'ckc_news_banner_img', array(
        'default'           => '',
        'transport'         => 'refresh',
        'sanitize_callback' => 'esc_url_raw',
    ) );
    $wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'ckc_news_banner_img', array(
        'label'    => '新聞 Banner 圖片 (建議尺寸 1200X300)',
        'section'  => 'chao_gang_cheng_news_banner_section',
    ) ) );

    // Link Setting
    $wp_customize->add_setting( 'ckc_news_banner_link', array(
        'default'           => '',
        'transport'         => 'refresh',
        'sanitize_callback' => 'esc_url_raw',
    ) );
    $wp_customize->add_control( 'ckc_news_banner_link', array(
        'label'    => '新聞 Banner 連結網址',
        'section'  => 'chao_gang_cheng_news_banner_section',
        'type'     => 'text',
    ) );
}


// 17b. Remove WooCommerce taxonomy/archive description from below the controls bar
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
    // ordering (e.g.「依熱銷度排序」), not a hard-coded「預設排序」.
    $default_orderby = get_option( 'woocommerce_default_catalog_orderby', 'menu_order' );
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

// 21. Inject shop layout styles inline to bypass aggressive staging caches
add_action( 'wp_head', 'chao_gang_cheng_inline_shop_styles', 100 );
function chao_gang_cheng_inline_shop_styles() {
    if ( ! ( is_shop() || is_product_taxonomy() || ( is_account_page() && is_wc_endpoint_url( 'downloads' ) ) ) ) {
        return;
    }
    ?>
    <style>
    /* Hide native sorting and count globally */
    .woocommerce-ordering,
    .woocommerce-result-count {
      display: none !important;
    }

    /* Make shop layout container span full width */
    .shop-layout-container {
      grid-template-columns: 1fr !important;
    }

    /* Title Left Bar styling */
    .shop-main-content h1.page-title {
      font-size: 24px !important;
      font-weight: 700 !important;
      border-left: 5px solid var(--primary-color) !important;
      padding-left: 12px !important;
      margin-bottom: 20px !important;
      line-height: 1.25 !important;
      text-align: left !important;
      color: var(--primary-color) !important;
    }

    /* Custom Shop Controls Bar */
    .mobile-shop-controls-bar {
      display: flex !important;
      justify-content: space-between;
      align-items: center;
      border-top: 1px solid rgba(0,0,0,0.06);
      border-bottom: 1px solid rgba(0,0,0,0.06);
      padding: 10px 0;
      margin-bottom: 25px;
      font-size: 13px;
      color: #555;
      gap: 10px;
      flex-wrap: wrap;
    }

    .mobile-shop-controls-bar .controls-left,
    .mobile-shop-controls-bar .controls-right {
      display: flex;
      align-items: center;
      gap: 16px;
    }

    /* Custom Dropdown Filters */
    .mobile-dropdown-filter {
      position: relative;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      cursor: pointer;
      font-weight: 600;
      color: #555;
      padding: 6px 0;
      user-select: none;
    }

    .mobile-dropdown-filter .icon-chevron {
      transition: transform 0.2s ease;
      color: #888;
    }

    .mobile-dropdown-filter.active .icon-chevron {
      transform: rotate(180deg);
    }

    .dropdown-menu-content {
      position: absolute;
      top: 100%;
      left: 0;
      background-color: var(--white);
      min-width: 150px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.12);
      border: 1px solid rgba(0,0,0,0.08);
      border-radius: 8px;
      padding: 8px 0;
      z-index: 1000;
      display: none;
      margin-top: 5px;
    }

    .mobile-dropdown-filter.active .dropdown-menu-content {
      display: block;
    }

    .dropdown-menu-content a {
      display: block;
      padding: 8px 16px;
      color: #555;
      text-decoration: none;
      font-size: 13px;
      font-weight: 500;
      transition: background-color 0.15s ease;
      text-align: left;
    }

    .dropdown-menu-content a:hover {
      background-color: #f5f5f5;
      color: var(--primary-color);
    }

    /* Highlight active filter trigger labels */
    .mobile-dropdown-filter .active-filter {
      color: var(--primary-color) !important;
    }

    /* Specific styling for the Price Slider dropdown inside menu content */
    .price-slider-dropdown {
      min-width: 250px;
      padding: 15px;
      box-sizing: border-box;
    }

    .price-slider-dropdown .price_slider_wrapper {
      margin: 0;
    }

    /* Filter Drawer Button */
    .mobile-filter-btn {
      background: none !important;
      border: none !important;
      padding: 0 !important;
      font-size: 13px !important;
      font-weight: 600 !important;
      color: #555 !important;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      cursor: pointer;
    }

    /* Item Count & Layout Switches */
    .mobile-result-count {
      color: #888;
      font-size: 13px;
    }

    /* Shop Sidebar slide-out drawer overrides globally */
    .shop-sidebar {
      position: fixed !important;
      top: 0 !important;
      left: -320px !important; /* Hidden */
      width: 290px !important;
      height: 100vh !important;
      background-color: var(--white) !important;
      z-index: 100000 !important;
      padding: 20px !important;
      box-shadow: 4px 0 20px rgba(0,0,0,0.15) !important;
      overflow-y: auto !important;
      transition: left 0.3s cubic-bezier(0.25, 0.8, 0.25, 1) !important;
      border: none !important;
      box-sizing: border-box !important;
      padding-bottom: 80px !important;
      display: block !important;
    }

    .shop-sidebar.is-open {
      left: 0 !important; /* Slide-in */
    }

    /* Sidebar drawer close button */
    .shop-sidebar-close {
      position: absolute;
      top: 15px;
      right: 15px;
      background: none !important;
      border: none !important;
      font-size: 26px !important;
      color: #999 !important;
      cursor: pointer;
      line-height: 1;
      z-index: 10;
    }

    /* Overlay backdrop filter */
    .shop-sidebar-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.4);
      z-index: 99999;
      display: none;
    }

    .shop-sidebar-overlay.is-active {
      display: block;
    }

    body.shop-sidebar-active {
      overflow: hidden !important;
    }

    /* Grid/List View overrides */
    ul.products.list-view {
      display: flex !important;
      flex-direction: column !important;
      gap: 15px !important;
    }

    ul.products.list-view li.product {
      width: 100% !important;
      float: none !important;
      margin: 0 !important;
      border: 1px solid var(--border-color) !important;
      border-radius: var(--border-radius) !important;
      background-color: var(--white) !important;
      padding: 15px !important;
      display: flex !important;
      flex-direction: row !important;
      align-items: center !important;
      box-sizing: border-box !important;
    }

    ul.products.list-view li.product a.woocommerce-LoopProduct-link {
      display: flex !important;
      flex-direction: row !important;
      align-items: center !important;
      width: calc(100% - 120px) !important;
      text-decoration: none !important;
    }

    ul.products.list-view li.product img {
      width: 100px !important;
      height: 100px !important;
      object-fit: cover !important;
      border-radius: 6px !important;
      margin-right: 20px !important;
      margin-bottom: 0 !important;
    }

    ul.products.list-view li.product .woocommerce-loop-product__title {
      font-size: 15px !important;
      margin: 0 0 8px 0 !important;
      height: auto !important;
      line-height: 1.4 !important;
      text-align: left !important;
      white-space: normal !important;
    }

    ul.products.list-view li.product .price {
      font-size: 14px !important;
      text-align: left !important;
      margin-bottom: 0 !important;
    }

    /* Align add to cart buttons on the right side of list item cards */
    ul.products.list-view li.product a.add_to_cart_button {
      margin-top: 0 !important;
      margin-left: auto !important;
      width: 110px !important;
      font-size: 12px !important;
      padding: 8px 0 !important;
      text-align: center !important;
      border-radius: 20px !important;
    }

    /* Mobile fine-tuning overrides */
    @media (max-width: 768px) {
      .mobile-shop-controls-bar {
        padding: 8px 0;
        font-size: 12px;
      }
      .mobile-shop-controls-bar .controls-left,
      .mobile-shop-controls-bar .controls-right {
        gap: 12px;
      }
      .mobile-dropdown-filter {
        font-size: 12px;
      }
      .mobile-filter-btn {
        font-size: 12px !important;
      }
      ul.products.list-view li.product img {
        width: 80px !important;
        height: 80px !important;
        margin-right: 12px !important;
      }
      ul.products.list-view li.product a.woocommerce-LoopProduct-link {
        width: calc(100% - 100px) !important;
      }
      ul.products.list-view li.product a.add_to_cart_button {
        width: 90px !important;
        font-size: 11px !important;
        padding: 6px 0 !important;
        text-align: center !important;
        border-radius: 20px !important;
      }
    }
    
    /* --- Product Loop Cards Hover Overlay & Option Buttons --- */
    .product-image-wrapper {
      position: relative;
      overflow: hidden;
      border-radius: var(--border-radius, 8px);
      display: block;
      width: 100%;
    }

    .product-image-hover-overlay {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.15) !important;
      display: flex !important;
      align-items: center;
      justify-content: center;
      opacity: 0;
      transition: opacity 0.3s ease;
      z-index: 5;
      pointer-events: none;
    }

    .woocommerce ul.products li.product:hover .product-image-hover-overlay,
    .woocommerce-page ul.products li.product:hover .product-image-hover-overlay {
      opacity: 1;
      pointer-events: auto;
    }

    .hover-btn-group {
      display: flex !important;
      gap: 15px !important;
      transform: translateY(10px);
      transition: transform 0.3s ease;
      pointer-events: auto;
    }

    .woocommerce ul.products li.product:hover .hover-btn-group,
    .woocommerce-page ul.products li.product:hover .hover-btn-group {
      transform: translateY(0);
    }

    .hover-btn {
      width: 50px !important;
      height: 50px !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      border-radius: 8px !important;
      box-sizing: border-box !important;
      text-decoration: none !important;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
      transition: all 0.2s ease !important;
      cursor: pointer !important;
    }

    .hover-btn.btn-details {
      background-color: #ffffff !important;
      border: 1.5px solid #6a5252 !important;
    }

    .hover-btn.btn-details:hover {
      background-color: #f5f5f5 !important;
      transform: scale(1.08) !important;
    }

    .hover-btn.btn-details svg {
      stroke: #6a5252 !important;
    }

    .hover-btn.btn-cart {
      background-color: #7d6565 !important;
      border: none !important;
    }

    .hover-btn.btn-cart:hover {
      background-color: #6a5252 !important;
      transform: scale(1.08) !important;
    }

    /* Hide native Add to Cart button on archive product loops */
    .archive ul.products li.product > a.button,
    .archive ul.products li.product > .added_to_cart {
      display: none !important;
    }

    /* Responsive Mobile Overrides */
    @media (max-width: 768px) {
      .product-image-hover-overlay {
        opacity: 1 !important;
        background-color: transparent !important;
        align-items: flex-end !important;
        justify-content: flex-end !important;
        padding: 10px !important;
        box-sizing: border-box !important;
        pointer-events: none !important;
      }
      
      .hover-btn-group {
        transform: none !important;
        gap: 8px !important;
        pointer-events: auto !important;
      }

      .hover-btn {
        /* 44px minimum touch target (Apple HIG / WCAG 2.5.5) */
        width: 44px !important;
        height: 44px !important;
        border-radius: 8px !important;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1) !important;
      }

      .hover-btn svg {
        width: 18px !important;
        height: 18px !important;
      }

      /* The whole card is already tappable on mobile — the magnifier
         "view details" button is redundant and invites mis-taps. */
      .hover-btn.btn-details {
        display: none !important;
      }
    }
    
    /* Hide WooCommerce AJAX appended View Cart link from the image overlay wrapper */
    .product-image-wrapper .added_to_cart {
      display: none !important;
    }

    /* Premium WooCommerce Pagination Styling */
    .woocommerce-pagination {
        text-align: center !important;
        margin: 40px 0 !important;
        width: 100% !important;
    }
    .woocommerce-pagination ul.page-numbers {
        display: inline-flex !important;
        gap: 8px !important;
        padding: 0 !important;
        margin: 0 !important;
        list-style: none !important;
        border: none !important;
    }
    .woocommerce-pagination ul.page-numbers li {
        border-right: none !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    .woocommerce-pagination ul.page-numbers li a,
    .woocommerce-pagination ul.page-numbers li span {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: 42px !important;
        height: 42px !important;
        font-size: 15px !important;
        font-weight: 600 !important;
        text-decoration: none !important;
        border-radius: 50% !important;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
        background-color: #f8fafc !important;
        color: #4b5563 !important;
        border: 1px solid #e5e7eb !important;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02) !important;
    }
    .woocommerce-pagination ul.page-numbers li span.current {
        background-color: #8c7e7e !important;
        color: #ffffff !important;
        border-color: #8c7e7e !important;
        box-shadow: 0 4px 10px rgba(140, 126, 126, 0.25) !important;
    }
    .woocommerce-pagination ul.page-numbers li a:hover {
        background-color: #eaeaea !important;
        color: #111827 !important;
        border-color: #d1d5db !important;
        transform: translateY(-2px) !important;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08) !important;
    }
    .woocommerce-pagination ul.page-numbers li a.next,
    .woocommerce-pagination ul.page-numbers li a.prev {
        font-size: 18px !important;
    }

    /* Optimize My Account Wishlist Product Card Layout on Mobile */
    @media (max-width: 768px) {
        .woocommerce-account ul.products li.product .woocommerce-loop-product__title {
            height: auto !important;
            min-height: 62px !important;
            max-height: 62px !important;
            display: -webkit-box !important;
            -webkit-line-clamp: 3 !important;
            -webkit-box-orient: vertical !important;
            overflow: hidden !important;
            padding: 8px 10px 2px !important;
            font-size: 13px !important;
            line-height: 1.4 !important;
        }
        .woocommerce-account ul.products li.product .price {
            padding: 4px 10px 8px !important;
            margin-top: auto !important;
        }
        .woocommerce-account ul.products li.product a.button.add_to_cart_button {
            display: block !important;
            width: calc(100% - 20px) !important;
            margin: 0 auto 12px auto !important;
            padding: 10px 0 !important;
            font-size: 13px !important;
            font-weight: 600 !important;
            text-align: center !important;
            border-radius: 20px !important;
            background-color: #8c7e7e !important;
            color: #fff !important;
            border: none !important;
            box-shadow: 0 2px 6px rgba(140, 126, 126, 0.15) !important;
            transition: all 0.2s ease-in-out !important;
        }
        .woocommerce-account ul.products li.product a.button.add_to_cart_button:hover {
            background-color: #7a6d6d !important;
        }
        .woocommerce-account ul.products li.product {
            height: 100% !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: space-between !important;
        }
    }
    </style>
    <?php
}

// 22. Adjust shipping rates for outlying islands (澎湖, 金門, 連江, 綠島, 蘭嶼, 琉球)
add_filter( 'woocommerce_package_rates', 'chao_gang_cheng_adjust_shipping_rates', 10, 2 );
function chao_gang_cheng_adjust_shipping_rates( $rates, $package ) {
    $destination = isset( $package['destination'] ) ? $package['destination'] : array();
    $state = isset( $destination['state'] ) ? trim( $destination['state'] ) : '';
    $city = isset( $destination['city'] ) ? trim( $destination['city'] ) : '';
    $postcode = isset( $destination['postcode'] ) ? trim( $destination['postcode'] ) : '';
    
    $is_outlying = false;
    
    // Check postcode prefix
    $postcode_prefix = substr( $postcode, 0, 3 );
    if ( in_array( $postcode_prefix, array( '951', '952', '929' ) ) || 
         ( intval( $postcode_prefix ) >= 880 && intval( $postcode_prefix ) <= 885 ) || 
         ( intval( $postcode_prefix ) >= 890 && intval( $postcode_prefix ) <= 896 ) || 
         ( intval( $postcode_prefix ) >= 209 && intval( $postcode_prefix ) <= 212 ) ) {
        $is_outlying = true;
    }
    
    // Check state/county name
    if ( ! $is_outlying ) {
        $outlying_states = array( '澎湖縣', '金門縣', '連江縣', 'PEN', 'KIN', 'LIE' );
        if ( in_array( $state, $outlying_states ) ) {
            $is_outlying = true;
        }
    }
    
    // Check city name
    if ( ! $is_outlying ) {
        $outlying_cities = array( '綠島', '蘭嶼', '琉球' );
        foreach ( $outlying_cities as $oc ) {
            if ( strpos( $city, $oc ) !== false ) {
                $is_outlying = true;
                break;
            }
        }
    }
    
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
    
    return $rates;
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
            'limit'   => $needed,
            'status'  => 'publish',
            'exclude' => $exclude_ids,
            'orderby' => 'date',
            'order'   => 'DESC',
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
            // Insert Section Headers
            $('#menu-dashboard').before('<li class="menu-section-header">網站內容</li>');
            
            var $storeStart = $('#toplevel_page_ckc-gemini-agent');
            if (!$storeStart.length) $storeStart = $('#toplevel_page_woocommerce');
            if (!$storeStart.length) $storeStart = $('#menu-posts-product');
            if ($storeStart.length) {
                $storeStart.before('<li class="menu-section-header">電商營運</li>');
            }
            
            $('#menu-appearance').before('<li class="menu-section-header">系統配置</li>');

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
            translateMonsterInsights($('body'));
        });
    </script>
    <?php
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
    .woocommerce span.onsale.chao-onsale,
    .woocommerce-page span.onsale.chao-onsale {
        display: block !important;
        position: absolute; top: 10px; left: 10px; z-index: 9;
        min-width: 0; min-height: 0; line-height: 1;
        background: #dc2626; color: #fff;
        font-size: 12px; font-weight: 700; letter-spacing: 0.5px;
        padding: 5px 10px; border-radius: 14px;
        box-shadow: 0 2px 6px rgba(220, 38, 38, 0.35);
        margin: 0;
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

add_action( 'admin_menu', 'ckc_floating_btns_add_menu' );

function ckc_floating_btns_add_menu() {
    add_theme_page(
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
// 全站彈窗廣告系統
// WordPress 後台：外觀 > 彈窗廣告設定
// =============================================================================

/**
 * 21a. 後台選單 — 外觀 > 彈窗廣告設定
 */
add_action( 'admin_menu', 'ckc_popup_add_menu' );
function ckc_popup_add_menu() {
    add_theme_page(
        '彈窗廣告設定',
        '彈窗廣告設定',
        'manage_options',
        'ckc-popup-settings',
        'ckc_popup_page_html'
    );
}

/**
 * 21b. 在後台頁面載入媒體庫腳本
 */
add_action( 'admin_enqueue_scripts', 'ckc_popup_enqueue_admin_scripts' );
function ckc_popup_enqueue_admin_scripts( $hook ) {
    if ( $hook !== 'appearance_page_ckc-popup-settings' ) return;
    wp_enqueue_media();
}

/**
 * 21c. 向 Settings API 註冊設定
 */
add_action( 'admin_init', 'ckc_popup_register_settings' );
function ckc_popup_register_settings() {
    register_setting(
        'ckc_popup_group',
        'chao_gang_cheng_popup',
        array( 'sanitize_callback' => 'ckc_popup_sanitize' )
    );
}

/**
 * 21d. 資料清理
 */
function ckc_popup_sanitize( $input ) {
    $clean = array();
    $clean['enabled']        = ! empty( $input['enabled'] )       ? '1' : '';
    $clean['image_id']       = isset( $input['image_id'] )        ? absint( $input['image_id'] )                       : 0;
    $clean['link_url']       = isset( $input['link_url'] )        ? esc_url_raw( trim( $input['link_url'] ) )           : '';
    $clean['link_target']    = ! empty( $input['link_target'] )   ? '_blank'                                            : '_self';
    $clean['show_home']      = ! empty( $input['show_home'] )     ? '1' : '';
    $clean['show_shop']      = ! empty( $input['show_shop'] )     ? '1' : '';
    $clean['show_product']   = ! empty( $input['show_product'] )  ? '1' : '';
    $clean['cookie_days']    = isset( $input['cookie_days'] )     ? intval( $input['cookie_days'] )                     : 1;
    $clean['delay_seconds']  = isset( $input['delay_seconds'] )   ? max( 0, intval( $input['delay_seconds'] ) )         : 0;
    return $clean;
}

/**
 * 21e. 後台設定頁面 HTML
 */
function ckc_popup_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( '您沒有權限存取此頁面。' );
    }

    $opts = wp_parse_args(
        get_option( 'chao_gang_cheng_popup', array() ),
        array(
            'enabled'       => '1',
            'image_id'      => 0,
            'link_url'      => '',
            'link_target'   => '_blank',
            'show_home'     => '1',
            'show_shop'     => '1',
            'show_product'  => '1',
            'cookie_days'   => 1,
            'delay_seconds' => 0,
        )
    );

    $image_url = $opts['image_id'] ? wp_get_attachment_url( $opts['image_id'] ) : '';
    ?>
    <div class="wrap">
        <h1 style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:26px;">🎯</span>
            彈窗廣告設定
        </h1>
        <p style="color:#666;margin-top:4px;">設定前台自動彈出的廣告圖片，可指定顯示頁面與顯示頻率。</p>
        <hr style="margin:18px 0;">

        <?php if ( isset( $_GET['settings-updated'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><strong>✅ 彈窗設定已儲存！</strong></p>
        </div>
        <?php endif; ?>

        <form method="post" action="options.php" style="max-width:720px;">
            <?php settings_fields( 'ckc_popup_group' ); ?>

            <!-- ── 總開關 ── -->
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:20px 24px;margin-bottom:20px;box-shadow:0 1px 4px rgba(0,0,0,.05);">
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <h3 style="margin:0 0 4px;">啟用彈窗廣告</h3>
                        <p style="margin:0;color:#888;font-size:13px;">關閉後全站停止顯示彈窗</p>
                    </div>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="chao_gang_cheng_popup[enabled]" value="1" <?php checked( '1', $opts['enabled'] ); ?> style="width:20px;height:20px;cursor:pointer;">
                        <span style="font-size:15px;font-weight:600;color:#333;">啟用</span>
                    </label>
                </div>
            </div>

            <!-- ── 彈窗圖片 ── -->
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:20px 24px;margin-bottom:20px;box-shadow:0 1px 4px rgba(0,0,0,.05);">
                <h3 style="margin:0 0 14px;">📸 彈窗圖片</h3>
                <input type="hidden" name="chao_gang_cheng_popup[image_id]" id="ckc-popup-image-id" value="<?php echo esc_attr( $opts['image_id'] ); ?>">

                <div id="ckc-popup-preview" style="margin-bottom:14px;<?php echo $image_url ? '' : 'display:none;'; ?>">
                    <img id="ckc-popup-preview-img" src="<?php echo esc_url( $image_url ); ?>"
                         style="max-width:320px;max-height:200px;object-fit:contain;border-radius:6px;border:1px solid #ddd;">
                </div>

                <div id="ckc-popup-placeholder" style="<?php echo $image_url ? 'display:none;' : ''; ?>background:#f5f5f5;border:2px dashed #ddd;border-radius:8px;padding:30px;text-align:center;margin-bottom:14px;color:#aaa;">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1.5" style="margin-bottom:8px;"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                    <p style="margin:0;font-size:14px;">尚未設定彈窗圖片</p>
                </div>

                <div style="display:flex;gap:10px;">
                    <button type="button" id="ckc-popup-select-img" class="button button-secondary" style="height:36px;padding:0 16px;">
                        🖼️ 從媒體庫選取
                    </button>
                    <button type="button" id="ckc-popup-remove-img" class="button" style="height:36px;padding:0 16px;color:#c00;<?php echo $image_url ? '' : 'display:none;'; ?>">
                        ✕ 移除圖片
                    </button>
                </div>
                <p style="margin:8px 0 0;font-size:12px;color:#aaa;">建議圖片尺寸：600×600px 或 600×800px，JPG/PNG 格式</p>
            </div>

            <!-- ── 點擊連結 ── -->
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:20px 24px;margin-bottom:20px;box-shadow:0 1px 4px rgba(0,0,0,.05);">
                <h3 style="margin:0 0 14px;">🔗 點擊連結</h3>
                <div style="background:#f9f9f9;border-radius:7px;padding:14px 16px;margin-bottom:12px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:6px;">點擊圖片後跳轉的網址（可留空）</label>
                    <input type="url" name="chao_gang_cheng_popup[link_url]"
                           value="<?php echo esc_attr( $opts['link_url'] ); ?>"
                           placeholder="https://example.com/..."
                           style="width:100%;padding:9px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;box-sizing:border-box;">
                </div>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="chao_gang_cheng_popup[link_target]" value="_blank"
                           <?php checked( '_blank', $opts['link_target'] ); ?>
                           style="width:16px;height:16px;cursor:pointer;">
                    <span style="font-size:14px;color:#333;">在新分頁開啟連結</span>
                </label>
            </div>

            <!-- ── 顯示頁面 ── -->
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:20px 24px;margin-bottom:20px;box-shadow:0 1px 4px rgba(0,0,0,.05);">
                <h3 style="margin:0 0 14px;">📄 顯示頁面</h3>
                <p style="margin:0 0 14px;color:#666;font-size:13px;">勾選彈窗要出現的頁面類型（可複選）</p>
                <div style="display:flex;flex-direction:column;gap:12px;">
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:12px 16px;border:1px solid #eee;border-radius:8px;transition:background .15s;" onmouseover="this.style.background='#f9f9f9'" onmouseout="this.style.background='#fff'">
                        <input type="checkbox" name="chao_gang_cheng_popup[show_home]" value="1" <?php checked( '1', $opts['show_home'] ); ?> style="width:18px;height:18px;cursor:pointer;">
                        <span style="font-size:24px;">🏠</span>
                        <div>
                            <strong style="font-size:14px;">首頁</strong>
                            <p style="margin:2px 0 0;font-size:12px;color:#aaa;">網站首頁（front-page / home）</p>
                        </div>
                    </label>
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:12px 16px;border:1px solid #eee;border-radius:8px;transition:background .15s;" onmouseover="this.style.background='#f9f9f9'" onmouseout="this.style.background='#fff'">
                        <input type="checkbox" name="chao_gang_cheng_popup[show_shop]" value="1" <?php checked( '1', $opts['show_shop'] ); ?> style="width:18px;height:18px;cursor:pointer;">
                        <span style="font-size:24px;">🛍️</span>
                        <div>
                            <strong style="font-size:14px;">商品分類頁</strong>
                            <p style="margin:2px 0 0;font-size:12px;color:#aaa;">商店主頁與所有商品分類頁</p>
                        </div>
                    </label>
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:12px 16px;border:1px solid #eee;border-radius:8px;transition:background .15s;" onmouseover="this.style.background='#f9f9f9'" onmouseout="this.style.background='#fff'">
                        <input type="checkbox" name="chao_gang_cheng_popup[show_product]" value="1" <?php checked( '1', $opts['show_product'] ); ?> style="width:18px;height:18px;cursor:pointer;">
                        <span style="font-size:24px;">📦</span>
                        <div>
                            <strong style="font-size:14px;">商品詳情頁</strong>
                            <p style="margin:2px 0 0;font-size:12px;color:#aaa;">所有單一商品頁面</p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- ── 顯示設定 ── -->
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:20px 24px;margin-bottom:28px;box-shadow:0 1px 4px rgba(0,0,0,.05);">
                <h3 style="margin:0 0 14px;">⚙️ 顯示設定</h3>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div style="background:#f9f9f9;border-radius:7px;padding:14px 16px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:8px;">🍪 顯示頻率</label>
                        <select name="chao_gang_cheng_popup[cookie_days]"
                                style="width:100%;padding:9px 10px;border:1px solid #ddd;border-radius:6px;font-size:14px;background:#fff;">
                            <option value="0"  <?php selected( 0,  $opts['cookie_days'] ); ?>>每次造訪都顯示</option>
                            <option value="1"  <?php selected( 1,  $opts['cookie_days'] ); ?>>每天 1 次</option>
                            <option value="3"  <?php selected( 3,  $opts['cookie_days'] ); ?>>每 3 天 1 次</option>
                            <option value="7"  <?php selected( 7,  $opts['cookie_days'] ); ?>>每週 1 次</option>
                            <option value="30" <?php selected( 30, $opts['cookie_days'] ); ?>>每月 1 次</option>
                        </select>
                        <p style="margin:6px 0 0;font-size:12px;color:#aaa;">關閉彈窗後隔幾天再次顯示</p>
                    </div>
                    <div style="background:#f9f9f9;border-radius:7px;padding:14px 16px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:8px;">⏱️ 延遲顯示（秒）</label>
                        <input type="number" name="chao_gang_cheng_popup[delay_seconds]"
                               value="<?php echo esc_attr( $opts['delay_seconds'] ); ?>"
                               min="0" max="30" step="1"
                               style="width:100%;padding:9px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;box-sizing:border-box;">
                        <p style="margin:6px 0 0;font-size:12px;color:#aaa;">進入頁面後幾秒彈出（0 = 立即）</p>
                    </div>
                </div>
            </div>

            <div style="display:flex;align-items:center;gap:12px;">
                <?php submit_button( '💾 儲存設定', 'primary large', 'submit', false, array( 'style' => 'height:44px;padding:0 28px;font-size:15px;font-weight:600;border-radius:8px;' ) ); ?>
                <span style="font-size:13px;color:#aaa;">設定儲存後立即生效於前台</span>
            </div>
        </form>

        <hr style="margin:32px 0 20px;">
        <p style="font-size:12px;color:#bbb;">潮港城客製電商主題 · 彈窗廣告設定 · 由 Antigravity AI 建置</p>
    </div>

    <script>
    (function($) {
        var mediaFrame;

        $('#ckc-popup-select-img').on('click', function(e) {
            e.preventDefault();
            if (mediaFrame) { mediaFrame.open(); return; }
            mediaFrame = wp.media({
                title: '選取彈窗圖片',
                button: { text: '使用此圖片' },
                multiple: false,
                library: { type: 'image' }
            });
            mediaFrame.on('select', function() {
                var attachment = mediaFrame.state().get('selection').first().toJSON();
                $('#ckc-popup-image-id').val(attachment.id);
                $('#ckc-popup-preview-img').attr('src', attachment.url);
                $('#ckc-popup-preview').show();
                $('#ckc-popup-placeholder').hide();
                $('#ckc-popup-remove-img').show();
            });
            mediaFrame.open();
        });

        $('#ckc-popup-remove-img').on('click', function() {
            $('#ckc-popup-image-id').val('0');
            $('#ckc-popup-preview-img').attr('src', '');
            $('#ckc-popup-preview').hide();
            $('#ckc-popup-placeholder').show();
            $(this).hide();
        });
    })(jQuery);
    </script>
    <?php
}


/**
 * 21f. 前台彈窗渲染
 */
add_action( 'wp_footer', 'ckc_popup_render' );
function ckc_popup_render() {
    $opts = wp_parse_args(
        get_option( 'chao_gang_cheng_popup', array() ),
        array(
            'enabled'       => '',
            'image_id'      => 0,
            'link_url'      => '',
            'link_target'   => '_blank',
            'show_home'     => '1',
            'show_shop'     => '1',
            'show_product'  => '1',
            'cookie_days'   => 1,
            'delay_seconds' => 0,
        )
    );

    // 1. 總開關
    if ( empty( $opts['enabled'] ) ) return;

    // 2. 必須有圖片
    if ( empty( $opts['image_id'] ) ) return;
    $image_url = wp_get_attachment_url( $opts['image_id'] );
    if ( ! $image_url ) return;

    // 3. 判斷當前頁面是否應顯示
    $should_show = false;
    if ( ! empty( $opts['show_home'] )    && ( is_front_page() || is_home() ) ) $should_show = true;
    if ( ! empty( $opts['show_shop'] )    && ( is_shop() || is_product_taxonomy() ) )  $should_show = true;
    if ( ! empty( $opts['show_product'] ) && is_product() )                     $should_show = true;

    if ( ! $should_show ) return;

    // 4. 準備參數
    $cookie_days   = intval( $opts['cookie_days'] );
    $delay_ms      = intval( $opts['delay_seconds'] ) * 1000;
    $link_url      = esc_url( $opts['link_url'] );
    $link_target   = $opts['link_target'] === '_blank' ? '_blank' : '_self';
    $image_alt     = get_post_meta( $opts['image_id'], '_wp_attachment_image_alt', true );
    $image_alt     = $image_alt ? esc_attr( $image_alt ) : '彈窗廣告';
    ?>
    <!-- CKC Popup Ad -->
    <style>
    #ckc-popup-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.72);
        z-index: 999999;
        align-items: center;
        justify-content: center;
        padding: 20px;
        box-sizing: border-box;
    }
    #ckc-popup-overlay.ckc-active {
        display: flex;
        animation: ckcFadeIn 0.35s ease;
    }
    @keyframes ckcFadeIn {
        from { opacity: 0; }
        to   { opacity: 1; }
    }
    #ckc-popup-box {
        position: relative;
        max-width: 600px;
        width: 100%;
        animation: ckcSlideUp 0.35s ease;
    }
    @keyframes ckcSlideUp {
        from { transform: translateY(30px); opacity: 0; }
        to   { transform: translateY(0);   opacity: 1; }
    }
    #ckc-popup-box img {
        width: 100%;
        height: auto;
        display: block;
        border-radius: 8px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.5);
    }
    #ckc-popup-close {
        position: absolute;
        top: -18px;
        right: -18px;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #fff;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        font-weight: 700;
        color: #333;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        transition: background 0.2s, transform 0.2s;
        z-index: 10;
        line-height: 1;
        padding: 0;
    }
    #ckc-popup-close:hover {
        background: #f0f0f0;
        transform: scale(1.1);
    }
    @media (max-width: 480px) {
        #ckc-popup-close {
            top: -14px;
            right: -6px;
            width: 34px;
            height: 34px;
            font-size: 18px;
        }
    }
    </style>

    <div id="ckc-popup-overlay" role="dialog" aria-modal="true" aria-label="廣告彈窗">
        <div id="ckc-popup-box">
            <button id="ckc-popup-close" type="button" aria-label="關閉彈窗">×</button>
            <?php if ( $link_url ) : ?>
            <a href="<?php echo $link_url; ?>" target="<?php echo esc_attr( $link_target ); ?>" rel="noopener">
                <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo $image_alt; ?>">
            </a>
            <?php else : ?>
            <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo $image_alt; ?>">
            <?php endif; ?>
        </div>
    </div>

    <script>
    (function() {
        var COOKIE_KEY  = 'ckc_popup_closed';
        var COOKIE_DAYS = <?php echo $cookie_days; ?>;
        var DELAY_MS    = <?php echo $delay_ms; ?>;

        // Check cookie
        function getCookie(name) {
            var v = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
            return v ? v.pop() : '';
        }
        function setCookie(name, value, days) {
            var expires = '';
            if (days > 0) {
                var d = new Date();
                d.setTime(d.getTime() + days * 86400000);
                expires = '; expires=' + d.toUTCString();
            }
            document.cookie = name + '=' + value + expires + '; path=/; SameSite=Lax';
        }

        if (COOKIE_DAYS > 0 && getCookie(COOKIE_KEY) === '1') return;

        var overlay = document.getElementById('ckc-popup-overlay');
        var closeBtn = document.getElementById('ckc-popup-close');

        function closePopup() {
            overlay.classList.remove('ckc-active');
            overlay.style.display = 'none';
            if (COOKIE_DAYS > 0) {
                setCookie(COOKIE_KEY, '1', COOKIE_DAYS);
            }
        }

        function openPopup() {
            overlay.classList.add('ckc-active');
        }

        // Open after delay
        setTimeout(openPopup, DELAY_MS);

        // Close on button click
        closeBtn.addEventListener('click', closePopup);

        // Close on overlay click (not box)
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) closePopup();
        });

        // Close on ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closePopup();
        });
    })();
    </script>
    <?php
}


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
        'yotuwp'    => array( 'title' => 'YotuWP', 'slug' => 'yotuwp-settings', 'found' => false ),
        'payments'  => array( 'title' => '付款', 'slug' => 'admin.php?page=wc-settings&tab=checkout&from=PAYMENTS_MENU_ITEM', 'found' => false ),
        'plugins'   => array( 'title' => '外掛', 'slug' => 'plugins.php', 'found' => false ),
        'tools'     => array( 'title' => '工具', 'slug' => 'tools.php', 'found' => false ),
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
            $matched_key = 'yotuwp';
        } elseif ( stripos( $slug, 'tab=checkout' ) !== false || stripos( $title, '付款' ) !== false ) {
            $matched_key = 'payments';
        } elseif ( $slug === 'plugins.php' ) {
            $matched_key = 'plugins';
        } elseif ( $slug === 'tools.php' ) {
            $matched_key = 'tools';
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
                    <h3>📦 出貨與庫存作業 (Agent 功能)</h3>
                    <p style="color: #64748b; font-size: 12px; margin-bottom: 12px;">點擊直接讀取 WooCommerce 資料庫，或對訂單執行狀態更新：</p>
                    <div class="quick-prompts-list">
                        <button class="quick-prompt-btn" data-prompt="請幫我統計目前所有「處理中」訂單中的商品總量，合併計算並產生今日的「配貨與撿貨清單」，以便我到倉庫備貨。">📋 產生今日出貨「配貨與撿貨清單」</button>
                        <button class="quick-prompt-btn" data-prompt="請幫我產生所有「處理中」訂單的物流宅配名冊，包含訂單號、收件人、電話、地址與商品，以便我匯入物流系統。">🚚 匯出今日待出貨「物流宅配名冊」</button>
                        <button class="quick-prompt-btn" data-prompt="請幫我執行批次自動化出貨：一鍵將目前系統中所有狀態為「處理中」的訂單更新為「已出貨」狀態。">🤖 動作：一鍵批次自動化出貨</button>
                        <button class="quick-prompt-btn" data-prompt="請幫我將黑貓託運單號 【請貼上託運單號】 填入訂單 #【請輸入訂單號】，並通知客戶。">🐈‍⬛ 動作：填入黑貓託運單號（單筆）</button>
                        <button class="quick-prompt-btn" data-prompt="請幫我批次匯入以下黑貓託運單號清單（每行一組：訂單號 託運單號）：&#10;【請在此貼上黑貓契客系統匯出的清單，例如：&#10;#265 9031234567890&#10;#271 9031234567891】">📦 動作：批次匯入黑貓託運單號</button>
                        <button class="quick-prompt-btn" data-prompt="請幫我自動抓取所有已填託運單號訂單的黑貓貨運狀態，並回填至後台訂單紀錄。">📡 動作：自動抓取黑貓貨態回填後台</button>
                        <button class="quick-prompt-btn" data-prompt="請給我推薦分潤報表：推薦訂單數、推薦營收、已發放點數與 Top 推薦人。">🤝 查詢推薦分潤報表</button>
                        <button class="quick-prompt-btn" data-prompt="請產生夥伴分潤對帳單，包含每位夥伴的待確認、可出金、已出金金額與稅務試算。">💰 產生夥伴分潤對帳單</button>
                        <button class="quick-prompt-btn" data-prompt="請標記會員 ID 【請輸入會員ID】 的可出金分潤為已出金。">✅ 動作：標記夥伴分潤已出金</button>
                        <button class="quick-prompt-btn" data-prompt="請核准會員 ID 【請輸入會員ID】 成為推廣夥伴，費率 8%。">🌟 動作：核准推廣夥伴申請</button>
                        <button class="quick-prompt-btn" data-prompt="請幫我執行自動化每日營運檢查：1. 確認是否有付款超過 24 小時卻未出貨的延遲訂單；2. 列出目前零庫存或負庫存的警告商品；3. 提供補貨建議。">⚠️ 執行每日庫存與出貨「自動化健康檢查」</button>
                        <button class="quick-prompt-btn" data-prompt="請幫我查詢最新的一筆處理中訂單的詳細資訊，包含收件人、電話、地址與商品清單。">🔍 查詢特定訂單狀況</button>
                        <button class="quick-prompt-btn" data-prompt="請幫我搜尋收件人是「王小明」的訂單記錄與目前的配送狀態。">👤 搜尋收件人訂單記錄</button>
                        <button class="quick-prompt-btn" data-prompt="請幫我更新訂單 #10246 的狀態為已出貨。">✏️ 動作：將訂單標記為已出貨</button>
                        <button class="quick-prompt-btn" data-prompt="請幫我將商品「潮港城一斤肉牛肉爐」的庫存數量更新為 50 件。">⚙️ 動作：更新特定商品庫存</button>
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

/**
 * 29. AJAX 呼叫 Gemini API 進行聊天對話 (具備實時讀取與寫入資料庫之 Agent 功能)
 */
add_action( 'wp_ajax_ckc_gemini_chat', 'ckc_ajax_gemini_chat' );
function ckc_ajax_gemini_chat() {
    if ( ! current_user_can( 'edit_pages' ) ) {
        wp_send_json_error( array( 'message' => '權限不足' ) );
    }

    $message = isset( $_POST['message'] ) ? sanitize_textarea_field( $_POST['message'] ) : '';
    if ( empty( $message ) ) {
        wp_send_json_error( array( 'message' => '訊息不能為空' ) );
    }

    $api_key = get_option( 'ckc_gemini_api_key' );
    
    // System Instruction for E-commerce Assistant / Agent
    $system_context = "你是一位整合在「潮港城國際美食館」電商後台的「出貨AI助理」。你已具備自主 Agent 功能，能直接讀取與寫入商店資料庫。請以專業、親切的語氣協助管理者，根據系統提供的 [即時數據庫內容] 或 [系統動作執行紀錄] 進行回答與回報。回答請簡明扼要。";
    
    // --- 1a. 解析並執行訂單狀態寫入操作 (Agent Action: Update Order Status) ---
    $action_result = '';
    $has_action = false;
    if ( preg_match( '/(?:標記|修改|變更|更新)訂單\s*#?(\d+)\s*(?:為|的狀態為)?\s*(已出貨|已完成|處理中|取消|完成|completed|processing|cancelled)/u', $message, $matches ) ) {
        $order_id = intval( $matches[1] );
        $raw_status = $matches[2];
        $has_action = true;
        
        $status_map = array(
            '已出貨' => 'completed',
            '已完成' => 'completed',
            '完成'   => 'completed',
            'completed' => 'completed',
            '處理中' => 'processing',
            'processing' => 'processing',
            '取消'   => 'cancelled',
            'cancelled' => 'cancelled',
        );
        
        $target_status = isset( $status_map[$raw_status] ) ? $status_map[$raw_status] : '';
        if ( ! empty( $api_key ) ) {
            if ( $target_status && function_exists( 'wc_get_order' ) ) {
                $order = wc_get_order( $order_id );
                if ( $order ) {
                    $order->update_status( $target_status, '由 出貨AI助理 自動更新。' );
                    $action_result = "\n[系統動作執行紀錄]：成功將訂單編號 #{$order_id} 的狀態變更為「{$raw_status}」！";
                } else {
                    $action_result = "\n[系統動作執行紀錄]：執行失敗，找不到訂單編號 #{$order_id}。";
                }
            }
        } else {
            // Sandbox Mode Mock Action
            $action_result = "\n[系統動作執行紀錄]：【模擬沙盒動作成功】已成功將模擬訂單 #{$order_id} 狀態變更為「{$raw_status}」！(如配置真實 API Key 將直接修改資料庫)";
        }
    }

    // --- 1b. 解析並執行商品庫存寫入操作 (Agent Action: Update Stock Level) ---
    if ( preg_match( '/(?:更新|修改|調整)商品\s*「?([^」\n]+)」?\s*(?:的)?庫存(?:數量)?為\s*(\d+)\s*(?:件|包|個)?/u', $message, $matches ) ) {
        $product_name_query = trim( $matches[1] );
        $target_qty = intval( $matches[2] );
        $has_action = true;
        
        if ( ! empty( $api_key ) && function_exists( 'wc_get_products' ) ) {
            $prods = wc_get_products( array( 'title' => $product_name_query, 'limit' => 1 ) );
            if ( ! empty( $prods ) ) {
                $product = reset( $prods );
                $product->set_manage_stock( true );
                $product->set_stock_quantity( $target_qty );
                $product->save();
                $action_result = "\n[系統動作執行紀錄]：成功將商品「{$product->get_name()}」的庫存數量更新為 {$target_qty} 件！";
            } else {
                $action_result = "\n[系統動作執行紀錄]：執行失敗，找不到品名為「{$product_name_query}」的商品。";
            }
        } else {
            // Sandbox Mode Mock Action
            $action_result = "\n[系統動作執行紀錄]：【模擬沙盒動作成功】成功將商品「{$product_name_query}」的模擬庫存數量更新為 {$target_qty} 件！(如配置真實 API Key 將直接修改資料庫)";
        }
    }

    // --- 1c. 解析並執行批次出貨寫入操作 (Agent Action: Batch Update Order Status to Shipped) ---
    if ( preg_match( '/(?:批次自動化出貨|批次將.*(?:變更|更新|標記)為已出貨|批次更新.*已出貨|批次一鍵)/u', $message ) ) {
        $has_action = true;
        if ( ! empty( $api_key ) ) {
            if ( function_exists( 'wc_get_orders' ) ) {
                $orders = wc_get_orders( array( 'status' => 'processing', 'limit' => 100 ) );
                $updated_ids = array();
                foreach ( $orders as $order ) {
                    $order->update_status( 'completed', '由 出貨AI助理 批次自動化出貨。' );
                    $updated_ids[] = '#' . $order->get_id();
                }
                $count = count( $updated_ids );
                if ( $count > 0 ) {
                    $action_result = "\n[系統動作執行紀錄]：成功完成批次自動化出貨！共更新了 {$count} 筆處理中訂單（訂單編號：" . implode( ', ', $updated_ids ) . "）為「已出貨」狀態。";
                } else {
                    $action_result = "\n[系統動作執行紀錄]：目前沒有狀態為「處理中」的待出貨訂單。";
                }
            }
        } else {
            // Sandbox Mode Mock Action
            $action_result = "\n[系統動作執行紀錄]：【模擬沙盒動作成功】成功將模擬訂單 #10245, #10246, #10247 一鍵批次更新為「已出貨」狀態！(如配置真實 API Key 將直接批次修改真實資料庫)";
        }
    }

    // --- 1d. 填入黑貓託運單號（單筆）(Agent Action: Fill T-cat Tracking Number) ---
    $tracking_filled = false;
    $single_pairs = array();
    if ( preg_match( '/訂單\s*#?(\d+)\s*(?:的)?\s*(?:黑貓)?\s*託運單號\s*(?:為|是|填入|[:：])?\s*([0-9\-]{8,20})/u', $message, $m ) ) {
        $single_pairs[] = array( intval( $m[1] ), $m[2] );
    } elseif ( preg_match( '/(?:黑貓)?託運單號\s*[:：]?\s*([0-9\-]{8,20})\s*(?:填入|寫入|登記到|填到)\s*訂單\s*#?(\d+)/u', $message, $m ) ) {
        $single_pairs[] = array( intval( $m[2] ), $m[1] );
    }
    if ( ! empty( $single_pairs ) ) {
        $has_action = true;
        $tracking_filled = true;
        list( $t_order_id, $t_no ) = $single_pairs[0];
        if ( ! empty( $api_key ) && function_exists( 'wc_get_order' ) ) {
            if ( ckc_tcat_fill_tracking( $t_order_id, $t_no ) ) {
                $action_result .= "\n[系統動作執行紀錄]：成功將黑貓託運單號 {$t_no} 填入訂單 #{$t_order_id}，並已寄送出貨通知（含託運單號與查詢連結）給客戶！";
            } else {
                $action_result .= "\n[系統動作執行紀錄]：執行失敗，找不到訂單編號 #{$t_order_id}。";
            }
        } else {
            $action_result .= "\n[系統動作執行紀錄]：【模擬沙盒動作成功】已將黑貓託運單號 {$t_no} 填入模擬訂單 #{$t_order_id}！(如配置真實 API Key 將直接寫入資料庫並通知客戶)";
        }
    }

    // --- 1e. 批次填入黑貓託運單號（貼上黑貓契客系統匯出的「訂單號 託運單號」清單）---
    if ( ! $tracking_filled && preg_match( '/(?:批次|匯入|貼上).{0,20}託運單號|託運單號.{0,20}(?:批次|匯入|清單)/u', $message ) ) {
        if ( preg_match_all( '/#?(\d{2,8})\s*[:：,，\t、 ]+\s*(\d{10,13})\b/u', $message, $pair_matches, PREG_SET_ORDER ) && count( $pair_matches ) > 0 ) {
            $has_action = true;
            $tracking_filled = true;
            if ( ! empty( $api_key ) && function_exists( 'wc_get_order' ) ) {
                $ok = array();
                $fail = array();
                foreach ( array_slice( $pair_matches, 0, 50 ) as $pm ) {
                    if ( ckc_tcat_fill_tracking( intval( $pm[1] ), $pm[2] ) ) {
                        $ok[] = '#' . $pm[1] . '→' . $pm[2];
                    } else {
                        $fail[] = '#' . $pm[1];
                    }
                }
                $action_result .= "\n[系統動作執行紀錄]：批次填入黑貓託運單號完成！成功 " . count( $ok ) . " 筆（" . implode( '、', $ok ) . "）";
                if ( $fail ) {
                    $action_result .= "；失敗 " . count( $fail ) . " 筆（找不到訂單：" . implode( '、', $fail ) . "）";
                }
                $action_result .= "。每筆成功訂單皆已寄送含託運單號的出貨通知給客戶。";
            } else {
                $action_result .= "\n[系統動作執行紀錄]：【模擬沙盒動作成功】已批次填入 " . count( $pair_matches ) . " 筆模擬黑貓託運單號！(如配置真實 API Key 將直接寫入資料庫並通知客戶)";
            }
        }
    }

    // --- 1f. 自動抓取黑貓貨態並回填後台 (Agent Action: Auto-fetch T-cat Delivery Status) ---
    if ( ! $tracking_filled && preg_match( '/(?:抓取|同步|查詢|更新).{0,15}黑貓|黑貓.{0,15}(?:貨態|貨運狀態|配送狀態|狀態)/u', $message ) ) {
        $has_action = true;
        if ( ! empty( $api_key ) && function_exists( 'wc_get_orders' ) ) {
            $orders = wc_get_orders( array(
                'limit'      => 10,
                'orderby'    => 'date',
                'order'      => 'DESC',
                'meta_query' => array(
                    array(
                        'key'     => '_tcat_tracking_number',
                        'compare' => 'EXISTS',
                    ),
                ),
            ) );
            if ( empty( $orders ) ) {
                $action_result .= "\n[系統動作執行紀錄]：目前沒有任何已填入黑貓託運單號的訂單，請先填入託運單號後再執行貨態抓取。";
            } else {
                $lines = array();
                foreach ( $orders as $order ) {
                    $t_no = $order->get_meta( '_tcat_tracking_number' );
                    $status = ckc_tcat_fetch_status( $t_no );
                    if ( '' !== $status ) {
                        $prev = $order->get_meta( '_tcat_last_status' );
                        $order->update_meta_data( '_tcat_last_status', $status );
                        $order->update_meta_data( '_tcat_last_status_time', current_time( 'mysql' ) );
                        $order->save();
                        if ( $prev !== $status ) {
                            $order->add_order_note( sprintf( '黑貓貨態更新：%s（託運單號 %s，由出貨AI助理自動抓取）', $status, $t_no ) );
                        }
                        $lines[] = "- 訂單 #{$order->get_id()}（託運單號 {$t_no}）：{$status}" . ( false !== mb_strpos( $status, '順利送達' ) ? ' ✅' : '' );
                    } else {
                        $lines[] = "- 訂單 #{$order->get_id()}（託運單號 {$t_no}）：暫時查無貨態（可能尚未集貨或官網查詢暫時無回應），可稍後重試或至黑貓官網人工查詢。";
                    }
                }
                $action_result .= "\n[系統動作執行紀錄]：黑貓貨態自動抓取完成（最近 " . count( $orders ) . " 筆有託運單號的訂單），結果已回填至各訂單紀錄：\n" . implode( "\n", $lines );
            }
        } else {
            $action_result .= "\n[系統動作執行紀錄]：【模擬沙盒動作成功】已自動抓取 3 筆模擬訂單的黑貓貨態並回填後台：#10245 配送中、#10246 已集貨、#10247 順利送達 ✅ (如配置真實 API Key 將實際查詢黑貓官網並寫入資料庫)";
        }
    }

    // --- 1h. 夥伴分潤對帳單 (Agent Query: Partner Payout Statement) ---
    if ( preg_match( '/(?:夥伴|出金).{0,10}(?:對帳|對帳單|結算)|對帳單/u', $message ) ) {
        $has_action = true;
        if ( ! empty( $api_key ) && function_exists( 'ckc_refp_statement_text' ) ) {
            $action_result .= "\n[系統動作執行紀錄]：\n" . ckc_refp_statement_text();
        } else {
            $action_result .= "\n[系統動作執行紀錄]：【模擬沙盒數據】夥伴分潤對帳單：王小明（KOL，8%）可出金 NT$3,200（扣繳 NT$0、二代健保 NT$0、實付 NT$3,200）。(如配置真實 API Key 將讀取真實帳本)";
        }
    }

    // --- 1i. 標記夥伴出金 (Agent Action: Mark Partner Payout as Paid) ---
    if ( preg_match( '/(?:標記|完成).{0,15}會員\s*ID?\s*(\d+).{0,20}(?:已出金|出金|已付款|已匯款)/u', $message, $m ) ) {
        $has_action = true;
        if ( ! empty( $api_key ) && function_exists( 'ckc_refp_mark_paid' ) ) {
            $action_result .= "\n[系統動作執行紀錄]：" . ckc_refp_mark_paid( intval( $m[1] ) );
        } else {
            $action_result .= "\n[系統動作執行紀錄]：【模擬沙盒動作成功】已將會員 ID {$m[1]} 的可出金分潤標記為已出金！(如配置真實 API Key 將實際更新帳本)";
        }
    }

    // --- 1j. 核准推廣夥伴 (Agent Action: Approve Partner Application) ---
    if ( preg_match( '/核准.{0,10}會員\s*ID?\s*(\d+).{0,15}(?:成為)?(?:推廣)?夥伴(?:.{0,15}?(\d+(?:\.\d+)?)\s*%)?/u', $message, $m ) ) {
        $has_action = true;
        $approve_id = intval( $m[1] );
        $approve_rate = isset( $m[2] ) && '' !== $m[2] ? $m[2] : '';
        if ( ! empty( $api_key ) && function_exists( 'ckc_refp_partner_type' ) ) {
            if ( get_user_by( 'id', $approve_id ) ) {
                $p_type = ( false !== mb_strpos( $message, '團購' ) ) ? 'groupbuyer' : 'kol';
                update_user_meta( $approve_id, '_ckc_ref_partner', $p_type );
                delete_user_meta( $approve_id, '_ckc_ref_partner_apply' );
                if ( '' !== $approve_rate ) {
                    update_user_meta( $approve_id, '_ckc_ref_partner_rate', $approve_rate );
                }
                $action_result .= "\n[系統動作執行紀錄]：已核准會員 ID {$approve_id} 成為推廣夥伴（" . ( 'kol' === $p_type ? 'KOL' : '團購主' ) . ( '' !== $approve_rate ? "，費率 {$approve_rate}%" : '，費率預設 8%' ) . "），其推薦訂單將改走現金分潤軌。";
            } else {
                $action_result .= "\n[系統動作執行紀錄]：執行失敗，找不到會員 ID {$approve_id}。";
            }
        } else {
            $action_result .= "\n[系統動作執行紀錄]：【模擬沙盒動作成功】已核准會員 ID {$approve_id} 成為推廣夥伴！(如配置真實 API Key 將實際更新會員資料)";
        }
    }

    // --- 1g. 推薦分潤報表 (Agent Query: Referral Commission Report) ---
    if ( preg_match( '/(?:推薦|分潤).{0,10}(?:報表|統計|概況|成效)|(?:報表|統計).{0,10}(?:推薦|分潤)/u', $message ) ) {
        $has_action = true;
        if ( ! empty( $api_key ) && function_exists( 'ckc_ref_admin_report_text' ) ) {
            $action_result .= "\n[系統動作執行紀錄]：推薦分潤報表查詢結果：\n" . ckc_ref_admin_report_text();
        } else {
            $action_result .= "\n[系統動作執行紀錄]：【模擬沙盒數據】推薦訂單共 12 筆，推薦營收 NT$18,600，已發放分潤 930 點。Top 推薦人：1. 王小明：5 筆訂單，累計 420 點。(如配置真實 API Key 將讀取真實資料庫)";
        }
    }

    // --- 2. 解析並讀取即時資料庫數據 (Agent Query) ---
    $db_context = '';
    
    // a. 待出貨訂單
    if ( ( stripos( $message, '出貨' ) !== false || stripos( $message, '處理中' ) !== false ) && stripos( $message, '健康檢查' ) === false && stripos( $message, '營運檢查' ) === false ) {
        if ( stripos( $message, '統計' ) === false && stripos( $message, '概況' ) === false && stripos( $message, '撿貨' ) === false && stripos( $message, '配貨' ) === false && stripos( $message, '宅配' ) === false && stripos( $message, '名冊' ) === false && stripos( $message, '物流' ) === false ) {
            if ( ! empty( $api_key ) && function_exists( 'wc_get_orders' ) ) {
                $orders = wc_get_orders( array( 'status' => 'processing', 'limit' => 10 ) );
                $formatted_orders = array();
                foreach ( $orders as $order ) {
                    $items = array();
                    foreach ( $order->get_items() as $item ) {
                        $items[] = $item->get_name() . ' x ' . $item->get_quantity();
                    }
                    $user_id = $order->get_customer_id();
                    $tags = get_user_meta( $user_id, 'ckc_customer_tags', true );
                    $tags_str = ! empty( $tags ) && is_array( $tags ) ? implode( ', ', $tags ) : '無';
                    $formatted_orders[] = sprintf(
                        "- 訂單編號: #%d, 收件人: %s, 聯絡電話: %s, 商品: %s, 金額: $%s, 狀態: %s, 會員標籤: %s",
                        $order->get_id(),
                        $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                        $order->get_billing_phone(),
                        implode( ', ', $items ),
                        $order->get_total(),
                        $order->get_status(),
                        $tags_str
                    );
                }
                $db_context .= "\n[即時待出貨訂單列表]：\n" . ( empty( $formatted_orders ) ? "目前沒有待出貨的訂單。" : implode( "\n", $formatted_orders ) );
            } else {
                // Sandbox Mode Mock Data
                $db_context .= "\n[即時待出貨訂單列表]：\n" .
                               "- 訂單編號: #10245, 收件人: 王小明, 聯絡電話: 0912-345678, 商品: 潮港城一斤肉牛肉爐 x 2, 金額: $1980, 狀態: 處理中\n" .
                               "- 訂單編號: #10246, 收件人: 李美華, 聯絡電話: 0928-111222, 商品: 太陽百匯平日午餐券 x 4, 金額: $3520, 狀態: 處理中\n" .
                               "- 訂單編號: #10247, 收件人: 陳大同, 聯絡電話: 0933-444555, 商品: 黃金鮑魚土雞煲 x 1, 金額: $1280, 狀態: 處理中";
            }
        }
    }
    
    // b. 最新訂單
    if ( stripos( $message, '最新' ) !== false && stripos( $message, '撿貨' ) === false && stripos( $message, '配貨' ) === false && stripos( $message, '宅配' ) === false && stripos( $message, '名冊' ) === false && stripos( $message, '物流' ) === false ) {
        if ( ! empty( $api_key ) && function_exists( 'wc_get_orders' ) ) {
            $orders = wc_get_orders( array( 'limit' => 5, 'orderby' => 'date', 'order' => 'DESC' ) );
            $formatted_orders = array();
            foreach ( $orders as $order ) {
                $items = array();
                foreach ( $order->get_items() as $item ) {
                    $items[] = $item->get_name() . ' x ' . $item->get_quantity();
                }
                $formatted_orders[] = sprintf(
                    "- 訂單編號: #%d, 收件人: %s, 商品: %s, 金額: $%s, 狀態: %s",
                    $order->get_id(),
                    $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    implode( ', ', $items ),
                    $order->get_total(),
                    $order->get_status()
                );
            }
            $db_context .= "\n[即時最新 5 筆訂單列表]：\n" . ( empty( $formatted_orders ) ? "目前沒有任何訂單。" : implode( "\n", $formatted_orders ) );
        } else {
            // Sandbox Mode Mock Data
            $db_context .= "\n[即時最新 5 筆訂單列表]：\n" .
                           "- 訂單編號: #10248, 收件人: 林曉婷, 商品: 港式臘味蘿蔔糕 x 2, 金額: $560, 狀態: 等待付款\n" .
                           "- 訂單編號: #10247, 收件人: 陳大同, 商品: 黃金鮑魚土雞煲 x 1, 金額: $1280, 狀態: 處理中\n" .
                           "- 訂單編號: #10246, 收件人: 李美華, 商品: 太陽百匯平日午餐券 x 4, 金額: $3520, 狀態: 處理中\n" .
                           "- 訂單編號: #10245, 收件人: 王小明, 商品: 潮港城一斤肉牛肉爐 x 2, 金額: $1980, 狀態: 處理中\n" .
                           "- 訂單編號: #10244, 收件人: 趙自強, 商品: 經典海鮮煲 x 1, 金額: $1580, 狀態: 已完成";
        }
    }

    // c. 庫存查詢 / 低庫存商品
    if ( ( stripos( $message, '庫存' ) !== false || stripos( $message, '警報' ) !== false ) && !$has_action && stripos( $message, '健康檢查' ) === false && stripos( $message, '營運檢查' ) === false ) {
        if ( ! empty( $api_key ) && function_exists( 'wc_get_products' ) ) {
            $products = wc_get_products( array( 'limit' => 40 ) );
            $formatted_products = array();
            foreach ( $products as $product ) {
                $stock = $product->get_stock_quantity();
                $stock_status = $product->get_stock_status();
                
                if ( stripos( $message, '低' ) !== false || stripos( $message, '警報' ) !== false ) {
                    // 低庫存篩選
                    if ( $product->managing_stock() && $stock <= 15 ) {
                        $formatted_products[] = sprintf( "- 商品: %s (SKU: %s), 剩餘庫存: %d 件, 狀態: %s", $product->get_name(), $product->get_sku(), $stock, $stock_status );
                    }
                } else {
                    $formatted_products[] = sprintf( "- 商品: %s (SKU: %s), 剩餘庫存: %s 件, 狀態: %s", $product->get_name(), $product->get_sku(), $product->managing_stock() ? $stock : '未啟用庫存管理', $stock_status );
                }
            }
            $db_context .= "\n[即時低庫存商品列表]：\n" . ( empty( $formatted_products ) ? "目前沒有符合條件的商品。" : implode( "\n", $formatted_products ) );
        } else {
            // Sandbox Mode Mock Data
            $db_context .= "\n[即時低庫存商品列表]：\n" .
                           "- 商品: 潮港城一斤肉牛肉爐 (SKU: CKC-BEEF-01), 剩餘庫存: 5 件, 狀態: instock\n" .
                           "- 商品: 黃金鮑魚土雞煲 (SKU: CKC-CHICKEN-02), 剩餘庫存: 2 件, 狀態: instock\n" .
                           "- 商品: 太陽百匯平日午餐券 (SKU: CKC-TICKET-WD), 剩餘庫存: 12 件, 狀態: instock";
        }
    }

    // d. 查詢特定訂單詳細狀況
    if ( preg_match( '/訂單\s*#?(\d+)/u', $message, $matches ) && ! $has_action ) {
        $order_id = intval( $matches[1] );
        if ( ! empty( $api_key ) && function_exists( 'wc_get_order' ) ) {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $items = array();
                foreach ( $order->get_items() as $item ) {
                    $items[] = $item->get_name() . ' x ' . $item->get_quantity();
                }
                $user_id = $order->get_customer_id();
                $tags = get_user_meta( $user_id, 'ckc_customer_tags', true );
                $tags_str = ! empty($tags) && is_array($tags) ? implode(', ', $tags) : '無';
                $db_context .= sprintf(
                    "\n[即時訂單 #%d 詳細資訊]：\n- 狀態: %s\n- 收件人: %s\n- 聯絡電話: %s\n- 配送地址: %s\n- 購買商品: %s\n- 總金額: $%s\n- 付款方式: %s\n- 建立日期: %s\n- 會員標籤: %s",
                    $order_id,
                    $order->get_status(),
                    $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
                    $order->get_billing_phone(),
                    $order->get_shipping_address_1() . ' ' . $order->get_shipping_city(),
                    implode( ', ', $items ),
                    $order->get_total(),
                    $order->get_payment_method_title(),
                    $order->get_date_created()->date('Y-m-d H:i:s'),
                    $tags_str
                );
            } else {
                $db_context .= "\n[即時資料庫搜尋結果]：找不到訂單編號 #{$order_id}。";
            }
        } else {
            // Sandbox Mode Mock Detail
            $db_context .= "\n[即時訂單 #{$order_id} 詳細資訊]：\n- 狀態: 處理中 (processing)\n- 收件人: 李美華\n- 聯絡電話: 0928-111222\n- 配送地址: 台中市南屯區公益路二段99號\n- 購買商品: 太陽百匯平日午餐券 x 4\n- 總金額: $3520\n- 付款方式: 信用卡線上支付\n- 建立日期: " . date('Y-m-d') . " 10:14:32";
        }
    }

    // e. 搜尋特定收件人的訂單
    if ( preg_match( '/(?:搜尋|查詢)收件人\s*「?([^」\n]+)」?/u', $message, $matches ) ) {
        $name = trim( $matches[1] );
        if ( ! empty( $api_key ) && function_exists( 'wc_get_orders' ) ) {
            $orders = wc_get_orders( array( 'billing_first_name' => $name, 'limit' => 5 ) );
            if ( empty( $orders ) ) {
                $orders = wc_get_orders( array( 'shipping_first_name' => $name, 'limit' => 5 ) );
            }
            $formatted_orders = array();
            foreach ( $orders as $order ) {
                $items = array();
                foreach ( $order->get_items() as $item ) {
                    $items[] = $item->get_name() . ' x ' . $item->get_quantity();
                }
                $formatted_orders[] = sprintf(
                    "- 訂單 #%d | 收件人: %s | 商品: %s | 金額: $%s | 狀態: %s",
                    $order->get_id(),
                    $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    implode( ', ', $items ),
                    $order->get_total(),
                    $order->get_status()
                );
            }
            $db_context .= "\n[收件人「{$name}」的訂單記錄]：\n" . ( empty( $formatted_orders ) ? "查無此收件人的訂單。" : implode( "\n", $formatted_orders ) );
        } else {
            // Sandbox Mode Mock Search
            if ( $name === '王小明' ) {
                $db_context .= "\n[收件人「王小明」的訂單記錄]：\n- 訂單 #10245 | 收件人: 王小明 | 商品: 潮港城一斤肉牛肉爐 x 2 | 金額: $1980 | 狀態: 處理中";
            } else {
                $db_context .= "\n[收件人「{$name}」的訂單記錄]：\n- 訂單 #10250 | 收件人: {$name} | 商品: 經典海鮮煲 x 1 | 金額: $1580 | 狀態: 已完成";
            }
        }
    }

    // f. 統計今日/本月出貨與銷售概況
    if ( stripos( $message, '統計' ) !== false || stripos( $message, '概況' ) !== false ) {
        if ( ! empty( $api_key ) && function_exists( 'wc_get_orders' ) ) {
            $all_orders = wc_get_orders( array( 'limit' => 100, 'status' => array( 'completed', 'processing' ) ) );
            $total_sales = 0;
            $order_count = count( $all_orders );
            foreach ( $all_orders as $order ) {
                $total_sales += floatval( $order->get_total() );
            }
            $db_context .= sprintf(
                "\n[即時商店銷售統計]：\n- 統計期間：最近 100 筆已付款訂單\n- 總訂單筆數：%d 筆\n- 累計總銷售金額：$%s\n- 待出貨訂單數：%d 筆",
                $order_count,
                number_format( $total_sales, 2 ),
                count( wc_get_orders( array( 'status' => 'processing', 'limit' => 100 ) ) )
            );
        } else {
            // Sandbox Mode Mock Statistics
            $db_context .= "\n[即時商店銷售統計]：\n- 統計期間：本月份截至今日\n- 總訂單筆數：158 筆\n- 累計總銷售金額：$284,500\n- 已完成出貨：142 筆\n- 待出貨處理中：16 筆\n- 本月最暢銷商品：\n  1. 潮港城一斤肉牛肉爐 (銷量: 235 包)\n  2. 太陽百匯平日午餐券 (銷量: 180 張)";
        }
    }

    // g. 產生配貨與撿貨清單
    if ( stripos( $message, '撿貨' ) !== false || stripos( $message, '配貨' ) !== false ) {
        if ( ! empty( $api_key ) && function_exists( 'wc_get_orders' ) ) {
            $orders = wc_get_orders( array( 'status' => 'processing', 'limit' => 100 ) );
            $picking_list = array();
            foreach ( $orders as $order ) {
                foreach ( $order->get_items() as $item ) {
                    $prod_name = $item->get_name();
                    $qty = $item->get_quantity();
                    $product = $item->get_product();
                    $sku = $product ? $product->get_sku() : '無 SKU';
                    
                    if ( isset( $picking_list[$prod_name] ) ) {
                        $picking_list[$prod_name]['qty'] += $qty;
                        $picking_list[$prod_name]['orders'][] = '#' . $order->get_id();
                    } else {
                        $picking_list[$prod_name] = array(
                            'sku' => $sku,
                            'qty' => $qty,
                            'orders' => array( '#' . $order->get_id() )
                        );
                    }
                }
            }
            $formatted_picking = array();
            foreach ( $picking_list as $name => $info ) {
                $formatted_picking[] = sprintf(
                    "- **%s** (SKU: %s) ➔ 總需求: **%d** 件 (來自訂單: %s)",
                    $name,
                    $info['sku'],
                    $info['qty'],
                    implode( ', ', $info['orders'] )
                );
            }
            $db_context .= "\n[即時配貨與撿貨總量清單]：\n" . ( empty( $formatted_picking ) ? "目前沒有待處理訂單，無須備貨。" : implode( "\n", $formatted_picking ) );
        } else {
            // Sandbox Mock Picking List
            $db_context .= "\n[即時配貨與撿貨總量清單]：\n" .
                           "- **潮港城一斤肉牛肉爐** (SKU: CKC-BEEF-01) ➔ 總需求: **2** 包 (來自訂單: #10245)\n" .
                           "- **太陽百匯平日午餐券** (SKU: CKC-TICKET-WD) ➔ 總需求: **4** 張 (來自訂單: #10246)\n" .
                           "- **黃金鮑魚土雞煲** (SKU: CKC-CHICKEN-02) ➔ 總需求: **1** 包 (來自訂單: #10247)";
        }
    }

    // h. 物流宅配名冊
    if ( stripos( $message, '宅配' ) !== false || stripos( $message, '名冊' ) !== false || stripos( $message, '物流' ) !== false ) {
        if ( ! empty( $api_key ) && function_exists( 'wc_get_orders' ) ) {
            $orders = wc_get_orders( array( 'status' => 'processing', 'limit' => 100 ) );
            $manifest = array();
            foreach ( $orders as $order ) {
                $items = array();
                foreach ( $order->get_items() as $item ) {
                    $items[] = $item->get_name() . ' x' . $item->get_quantity();
                }
                $manifest[] = sprintf(
                    "訂單: #%d | 收件人: %s | 電話: %s | 地址: %s | 內容: %s",
                    $order->get_id(),
                    $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
                    $order->get_billing_phone(),
                    $order->get_shipping_address_1() . ' ' . $order->get_shipping_city(),
                    implode( ', ', $items )
                );
            }
            $db_context .= "
[即時物流宅配名冊]：
" . ( empty( $manifest ) ? "目前沒有待出貨訂單的宅配資訊。" : implode( "
", $manifest ) );
        } else {
            // Sandbox Mock Manifest
            $db_context .= "
[即時物流宅配名冊]：
" .
                           "1. 訂單 #10245 | 收件人: 王小明 | 電話: 0912-345678 | 地址: 台中市南屯區公益路二段99號 | 內容: 潮港城一斤肉牛肉爐 x2
" .
                           "2. 訂單 #10246 | 收件人: 李美華 | 電話: 0928-111222 | 地址: 台北市大安區信義路四段100號 | 內容: 太陽百匯平日午餐券 x4
" .
                           "3. 訂單 #10247 | 收件人: 陳大同 | 電話: 0933-444555 | 地址: 台南市中西區民權路三段50號 | 內容: 黃金鮑魚土雞煲 x1";
        }
    }

    // i. 自動化營運與庫存健康檢查
    if ( stripos( $message, '健康檢查' ) !== false || stripos( $message, '營運檢查' ) !== false ) {
        if ( ! empty( $api_key ) && function_exists( 'wc_get_orders' ) ) {
            // Check processing orders created > 24 hours ago
            $processing_orders = wc_get_orders( array( 'status' => 'processing', 'limit' => 100 ) );
            $delayed_orders = array();
            $now = current_time( 'timestamp' );
            foreach ( $processing_orders as $order ) {
                $created = $order->get_date_created()->getTimestamp();
                if ( ($now - $created) > 86400 ) {
                    $hours = round( ($now - $created) / 3600 );
                    $delayed_orders[] = sprintf( "- 訂單 #%d (%s) - 已等待 %d 小時未出貨", $order->get_id(), $order->get_billing_first_name(), $hours );
                }
            }
            
            // Check out-of-stock or negative stock items
            $products = wc_get_products( array( 'limit' => 100 ) );
            $out_of_stock = array();
            $out_of_stock = array();
            foreach ( $products as $product ) {
                if ( $product->managing_stock() ) {
                    $stock = $product->get_stock_quantity();
                    if ( $stock <= 0 ) {
                        $out_of_stock[] = sprintf( "- %s (SKU: %s, 目前庫存: %d 件)", $product->get_name(), $product->get_sku(), $stock );
                    }
                }
            }
            
            $db_context .= "\n[自動化每日營運檢查報告]：\n";
            $db_context .= "【訂單出貨時效稽核】：\n" . ( empty( $delayed_orders ) ? "✅ 太棒了！目前沒有付款超過 24 小時卻未出貨 the delayed orders。\n" : implode( "\n", $delayed_orders ) . "\n" );
            $db_context .= "【缺貨/斷貨商品監控】：\n" . ( empty( $out_of_stock ) ? "✅ 良好！目前沒有庫存為零或负數的商品。\n" : implode( "\n", $out_of_stock ) . "\n" );
        } else {
            // Sandbox Mock Health Check Report
            $db_context .= "\n[自動化每日營運檢查報告]：\n" .
                           "【訂單出貨時效稽核】：\n" .
                           "- ⚠️ 訂單 #10240 (林阿美) - 已等待 48 小時未出貨 (待處理)\n" .
                           "- ⚠️ 訂單 #10242 (張大千) - 已等待 36 小時未出貨 (待處理)\n" .
                           "【缺貨/斷貨商品監控】：\n" .
                           "- ❌ 潮港城招牌蘿蔔糕 (SKU: CKC-CAKE-01, 目前庫存: 0 件)\n" .
                           "- ❌ 冷凍熟凍小龍蝦 (SKU: CKC-CRAY-05, 目前庫存: -2 件)\n" .
                           "【營運優化建議】：\n" .
                           "1. 延遲訂單請儘速包裝寄出，避免引起客訴。\n" .
                           "2. 招牌蘿蔔糕為缺貨商品，需通知廚房備料上架。小龍蝦出現負庫存，請確認實體倉儲數量與系統設定。";
        }
    }

    // --- 3. 處理模擬測試模式與真實 API 發送 ---
    if ( empty( $api_key ) ) {
        // 沙盒模擬模式下的 AI 回應組合
        $reply = "【沙盒模擬模式測試】\n我是您的出貨AI助理。目前系統處於模擬對話沙盒中：\n\n";
        
        if ( $has_action ) {
            $reply .= $action_result . "\n\n💡 配置您的真實 Gemini API 金鑰後，此指令將在資料庫中自動對 WooCommerce 進行修改！";
        } elseif ( stripos( $message, '撿貨' ) !== false || stripos( $message, '配貨' ) !== false ) {
            $reply .= "📋 **今日待出貨「配貨與撿貨總量清單」** (合併總數量)：\n\n" .
                      "| 商品名稱 | SKU | 待撿總數量 | 來源訂單 |\n" .
                      "| :--- | :--- | :---: | :--- |\n" .
                      "| **潮港城一斤肉牛肉爐** | CKC-BEEF-01 | **2** 包 | #10245 |\n" .
                      "| **太陽百匯平日午餐券** | CKC-TICKET-WD | **4** 張 | #10246 |\n" .
                      "| **黃金鮑魚土雞煲** | CKC-CHICKEN-02 | **1** 包 | #10247 |\n\n" .
                      "💡 **出貨提示**：牛肉爐與土雞煲為冷凍商品，請提前自冷凍庫備出；餐券為有價實體票券，請確認編號無誤後封信封裝寄。";
        } elseif ( stripos( $message, '宅配' ) !== false || stripos( $message, '名冊' ) !== false || stripos( $message, '物流' ) !== false ) {
            $reply .= "🚚 **今日待出貨「物流宅配名冊」**：\n\n" .
                      "| 訂單號 | 收件人 | 聯絡電話 | 配送地址 | 購買品項 |\n" .
                      "| :--- | :--- | :--- | :--- | :--- |\n" .
                      "| #10245 | 王小明 | 0912-345678 | 台中市南屯區公益路二段99號 | 潮港城一斤肉牛肉爐 x2 |\n" .
                      "| #10246 | 李美華 | 0928-111222 | 台北市大安區信義路四段100號 | 太陽百匯平日午餐券 x4 |\n" .
                      "| #10247 | 陳大同 | 0933-444555 | 台南市中西區民權路三段50號 | 黃金鮑魚土雞煲 x1 |\n\n" .
                      "💡 **提示**：可直接複製此表單匯入至物流系統後台進行大宗列印寄件單。";
        } elseif ( stripos( $message, '健康檢查' ) !== false || stripos( $message, '營運檢查' ) !== false ) {
            $reply .= "⚠️ **自動化每日營運檢查報告**：\n\n" .
                      "### 1. 訂單出貨時效稽核 (超過 24 小時未出貨)\n" .
                      "- 🔴 訂單 **#10240** (林阿美) - 已付款待出貨 **48** 小時\n" .
                      "- 🔴 訂單 **#10242** (張大千) - 已付款待出貨 **36** 小時\n\n" .
                      "### 2. 缺貨/斷貨商品監控 (庫存 <= 0)\n" .
                      "- ❌ **潮港城招牌蘿蔔糕** (SKU: CKC-CAKE-01) ➔ **目前庫存：0 件**\n" .
                      "- ❌ **冷凍熟凍小龍蝦** (SKU: CKC-CRAY-05) ➔ **目前庫存：-2 件** (負數異常)\n\n" .
                      "### 3. 營運優化建議\n" .
                      "1. 延遲的 2 筆訂單請優先安排揀貨出貨，避免引起延誤客訴。\n" .
                      "2. 招招牌蘿蔔糕已完全無庫存，請儘速通知廚房製作，或在系統後台調整狀態。\n" .
                      "3. 小龍蝦出現負庫存，請實體倉管人員進行複盤。";
        } elseif ( stripos( $message, '出貨' ) !== false || stripos( $message, '處理中' ) !== false ) {
            $reply .= "📋 **今日待出貨訂單明細**：\n" .
                      "1. 訂單 #10245 (王小明) - 潮港城一斤肉牛肉爐 x 2 ($1980) ➔ 待配貨\n" .
                      "2. 訂單 #10246 (李美華) - 太陽百匯平日午餐券 x 4 ($3520) ➔ 待備券\n" .
                      "3. 訂單 #10247 (陳大同) - 黃金鮑魚土雞煲 x 1 ($1280) ➔ 待配貨\n\n" .
                      "出貨人員提示：請優先打包牛肉爐與土雞煲，並確保冷凍保存箱工作正常。自取訂單請提前印出出貨單備查。";
        } elseif ( stripos( $message, '庫存' ) !== false || stripos( $message, '警報' ) !== false ) {
            $reply .= "⚠️ **低庫存警告商品列表 (低於 15 件)**：\n" .
                      "- **潮港城一斤肉牛肉爐** (SKU: CKC-BEEF-01) ➔ **僅剩 5 包**\n" .
                      "- **黃金鮑魚土雞煲** (SKU: CKC-CHICKEN-02) ➔ **僅剩 2 包**\n" .
                      "- **太陽百匯平日午餐券** (SKU: CKC-TICKET-WD) ➔ **僅剩 12 張**\n\n" .
                      "作業提示：牛肉爐與土雞煲庫存量均低於安全水位 (10包)，請盡快向廚房或採購單位提出補貨排程！";
        } elseif ( stripos( $message, '統計' ) !== false || stripos( $message, '概況' ) !== false ) {
            $reply .= "📈 **今日與本月電商銷售與出貨統計概況**：\n" .
                      "- **本月總銷售金額**：$284,500 元\n" .
                      "- **已付款總訂單數**：158 筆\n" .
                      "- **已出貨完成**：142 筆\n" .
                      "- **待出貨 (處理中)**：16 筆\n\n" .
                      "🔥 **本月熱銷排行榜**：\n" .
                      "1. 潮港城一斤肉牛肉爐（累計銷售 235 包）➔ 庫存緊張\n" .
                      "2. 太陽百匯平日午餐券（累計銷售 180 張）\n" .
                      "3. 黃金鮑魚土雞煲（累計銷售 98 包）";
        } elseif ( stripos( $message, '搜尋' ) !== false || stripos( $message, '收件人' ) !== false ) {
            $reply .= "👤 **搜尋收件人「王小明」的訂單記錄**：\n" .
                      "- 訂單 #10245 | 王小明 | 潮港城一斤肉牛肉爐 x 2 | 金額: $1980 | 狀態: 處理中 (已付款，待出貨)\n\n" .
                      "出貨人員提示：該客戶訂購冷凍商品，出貨時請黏貼冷凍標籤，並確認宅配單號已正確輸入。";
        } elseif ( preg_match( '/訂單\s*#?(\d+)/u', $message, $matches ) ) {
            $order_id = intval( $matches[1] );
            $reply .= "🔍 **訂單 #{$order_id} 詳細資訊**：\n" .
                      "- **狀態**：處理中 (Processing)\n" .
                      "- **收件人**：李美華 (電話: 0928-111222)\n" .
                      "- **收件地址**：台中市南屯區公益路二段99號\n" .
                      "- **購買商品**：太陽百匯平日午餐券 x 4 | 金額: $3520\n" .
                      "- **付款方式**：信用卡線上支付\n" .
                      "- **建立時間**：2026-07-11 10:14:32\n\n" .
                      "作業提示：該筆訂單為實體餐券，出貨時請雙重確認餐券編號並以掛號寄出。";
        } else {
            $reply = "哈囉！我是您的出貨AI助理 🤖 目前您尚未儲存 Gemini API 金鑰，系統正處於沙盒模擬測試狀態。\n\n您可以點擊左側「出貨與庫存作業」列表，模擬讀取待出貨訂單、低庫存警報，或是模擬更新訂單狀態！儲存您的 API 金鑰後，將可完全開通與真實 WooCommerce 資料庫的實時智慧串接！";
        }
        
        wp_send_json_success( array( 'reply' => $reply, 'is_mock' => true ) );
    }

    // --- 3b. 使用真實 Gemini API 連線發送 ---
    $full_prompt = $system_context;
    if ( ! empty( $db_context ) ) {
        $full_prompt .= "\n\n[系統即時讀取的資料庫內容]：\n" . $db_context;
    }
    if ( ! empty( $action_result ) ) {
        $full_prompt .= "\n\n[系統寫入動作執行紀錄]：\n" . $action_result;
    }
    $full_prompt .= "\n\n使用者提問：" . $message;

    $payload = array(
        'contents' => array(
            array(
                'parts' => array(
                    array( 'text' => $full_prompt )
                )
            )
        )
    );

    // 嘗試呼叫的端點與模型順序 (優先採用新世代穩定 v1 的 gemini-2.5-flash / gemini-2.0-flash / gemini-3.5-flash，並以 flash-latest / pro-latest / 1.5-flash 保底)
    $endpoints = array(
        'https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key=' . urlencode( $api_key ),
        'https://generativelanguage.googleapis.com/v1/models/gemini-2.0-flash:generateContent?key=' . urlencode( $api_key ),
        'https://generativelanguage.googleapis.com/v1/models/gemini-3.5-flash:generateContent?key=' . urlencode( $api_key ),
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=' . urlencode( $api_key ),
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro-latest:generateContent?key=' . urlencode( $api_key ),
        'https://generativelanguage.googleapis.com/v1/models/gemini-1.5-flash:generateContent?key=' . urlencode( $api_key ),
    );

    $last_error = '';
    $success = false;
    $reply = '';

    foreach ( $endpoints as $api_url ) {
        $response = wp_remote_post( $api_url, array(
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            $last_error = '連線失敗：' . $response->get_error_message();
            continue;
        }

        $body_text = wp_remote_retrieve_body( $response );
        $data      = json_decode( $body_text, true );

        if ( isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
            $reply = $data['candidates'][0]['content']['parts'][0]['text'];
            $success = true;
            break;
        } else {
            $last_error = isset( $data['error']['message'] ) ? $data['error']['message'] : '無法解析 API 回傳內容';
        }
    }

    if ( $success ) {
        wp_send_json_success( array( 'reply' => $reply, 'is_mock' => false ) );
    } else {
        wp_send_json_error( array( 'message' => 'API 錯誤：' . $last_error ) );
    }
}

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
                                $statusText.text('🎉 恭喜！全體顧客標籤重新整理完畢！').css('color', '#10b981');
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
        return $cached;
    }

    $recommended_ids = ckc_generate_ai_recommendations( $product_id );
    if ( ! empty( $recommended_ids ) ) {
        update_post_meta( $product_id, '_ckc_ai_recommendations', $recommended_ids );
        return $recommended_ids;
    }

    return $related_posts;
}

/**
 * 32e. 修改前台 WooCommerce 相關商品推薦區塊之 Heading 標題
 */
add_filter( 'woocommerce_product_related_products_heading', 'ckc_ai_related_products_heading' );
function ckc_ai_related_products_heading( $heading ) {
    return '✨ AI 智慧推薦商品';
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
                    product_id: <?php echo $product_id; ?>,
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
        $html_content .= "  <li><strong>📦 急速冷凍真空包裝</strong>：採用先進急速冷凍技術，鎖住第一手現做美味與極致鮮度，簡單加熱即刻享用。</li>\n";
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
add_action( 'wp_footer', 'chao_checkout_custom_js_css' );
function chao_checkout_custom_js_css() {
    if ( ! is_checkout() ) {
        return;
    }
    ?>
    <style>
    /* Custom Checkout Section Styles */
    .chao-checkout-section {
        background: #ffffff;
        border: 1px solid #e1e8ed;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.02);
    }
    .chao-section-title {
        font-size: 18px;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 20px;
        border-bottom: 2px solid #f1f5f9;
        padding-bottom: 10px;
    }
    .chao-sub-title {
        font-size: 15px;
        font-weight: 600;
        color: #34495e;
        margin: 15px 0 10px 0;
    }
    
    /* Grid Layouts */
    .chao-shipping-cards-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
    }
    .chao-payment-cards-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 16px;
    }
    @media (min-width: 640px) {
        .chao-payment-cards-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    /* Payment Icon Styles */
    .chao-payment-icon {
        margin-right: 12px;
        flex-shrink: 0;
        vertical-align: middle;
    }
    .chao-payment-icon-gpay {
        margin-right: 12px;
        flex-shrink: 0;
        vertical-align: middle;
    }
    
    /* LINE Pay Logo Styling */
    .chao-linepay-logo {
        display: inline-flex;
        align-items: center;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        font-weight: 900;
        font-style: italic;
        gap: 2px;
        margin-right: 12px;
        flex-shrink: 0;
        user-select: none;
    }
    .chao-linepay-text-line {
        color: #000000;
        font-size: 20px;
        letter-spacing: -1px;
        line-height: 1;
    }
    .chao-linepay-text-pay {
        background: #00c300;
        color: #ffffff;
        font-size: 13px;
        padding: 1px 6px;
        border-radius: 3px;
        font-style: normal;
        font-weight: 900;
        display: inline-block;
        margin-left: 2px;
        line-height: 1.2;
    }
    
    /* Cards Style */
    .chao-card {
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 16px 20px;
        display: flex;
        align-items: center;
        cursor: pointer;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        background: #fff;
        user-select: none;
    }
    .chao-card:hover {
        border-color: #94a3b8;
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(0,0,0,0.03);
    }
    .chao-card.active {
        border: 2px solid #000000 !important;
        background: #ffffff;
    }
    
    /* Circle Checkmark Icon */
    .chao-card-check {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        border: 2px solid #cbd5e1;
        margin-right: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.25s;
        flex-shrink: 0;
        position: relative;
    }
    .chao-card.active .chao-card-check {
        border-color: #000;
        background: #000;
    }
    .chao-card.active .chao-card-check::after {
        content: "";
        width: 9px;
        height: 5px;
        border-left: 2px solid #fff;
        border-bottom: 2px solid #fff;
        transform: rotate(-45deg);
        position: absolute;
        top: 6px;
        left: 5px;
    }
    .chao-card-text {
        font-size: 15px;
        font-weight: 600;
        color: #1e293b;
    }
    
    /* CVS options section */
    .chao-cvs-options {
        margin-top: 20px;
        border-top: 1px solid #f1f5f9;
        padding-top: 20px;
    }
    .chao-cvs-subcard {
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 16px 20px;
        display: flex;
        align-items: center;
        background: #fff;
        position: relative;
        margin-bottom: 16px;
    }
    .chao-cvs-subcard.active {
        border: 2px solid #000000;
    }
    .chao-cvs-info {
        display: flex;
        justify-content: space-between;
        width: 100%;
        align-items: center;
        margin-right: 14px;
    }
    .chao-cvs-name {
        font-size: 15px;
        font-weight: 600;
        color: #1e293b;
    }
    .chao-cvs-price {
        font-size: 15px;
        font-weight: 700;
        color: #64748b;
    }
    .chao-cvs-subcard.active .chao-cvs-price {
        color: #000;
    }
    .chao-cvs-free-shipping-msg {
        position: absolute;
        bottom: -22px;
        left: 56px;
        font-size: 12px;
        color: #ef4444;
        font-weight: 500;
    }
    
    /* Custom store selection button */
    .chao-select-store-btn {
        width: 100%;
        background: #fff;
        border: 1px solid #000;
        color: #000;
        font-weight: 600;
        font-size: 14px;
        padding: 12px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
        text-align: center;
        margin-top: 26px;
    }
    .chao-select-store-btn:hover {
        background: #f8fafc;
        border-color: #334155;
    }
    .chao-selected-store-info {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 14px 18px;
        margin-top: 14px;
        font-size: 14px;
        color: #334155;
        line-height: 1.5;
    }
    .chao-store-name {
        font-weight: 700;
        color: #0f172a;
    }
    
    /* Payment styling */
    .chao-payment-info {
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }
    .chao-payment-title {
        font-size: 15px;
        font-weight: 700;
        color: #1e293b;
    }
    .chao-payment-desc {
        font-size: 13px;
        color: #64748b;
        font-weight: 400;
    }
    
    /* Hide native WooCommerce elements */
    #shipping_method input[type="radio"] {
        display: none !important;
    }
    #shipping_method {
        list-style: none !important;
        margin: 0 !important;
        padding: 0 !important;
        display: block !important;
    }
    .woocommerce-checkout-payment ul.payment_methods {
        display: none !important;
    }
    
    /* Center and Enlarge Place Order Button */
    .form-row.place-order {
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        float: none !important;
        width: 100% !important;
        margin: 20px auto 0 auto !important;
        padding: 0 !important;
    }
    #place_order {
        order: 1 !important;
        float: none !important;
        display: block !important;
        margin: 0 auto !important;
        font-size: 18px !important;
        padding: 16px 24px !important;
        width: 100% !important;
        max-width: 450px !important;
        border-radius: 30px !important;
        font-weight: 700 !important;
        transition: all 0.25s ease-in-out !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08) !important;
        background-color: #8c7e7e !important;
        color: #fff !important;
        border: none !important;
    }
    #place_order:hover {
        transform: translateY(-1px) !important;
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12) !important;
        background-color: #7a6d6d !important;
    }
    .woocommerce-terms-and-conditions-wrapper {
        order: 2 !important;
        width: 100% !important;
        text-align: center !important;
        margin-top: 15px !important;
    }
    #chao-trust-seals {
        order: 3 !important;
        width: 100% !important;
    }
    </style>
    
    <script type="text/javascript">
    if (typeof window.mydyboxCvs === 'undefined') {
        window.mydyboxCvs = {
            ajaxUrl: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
            nonce: '<?php echo esc_js( wp_create_nonce( 'mydybox_cvs_map' ) ); ?>',
            cvsType: '<?php echo ( get_option( 'wooecpay_logistic_cvs_type', 'C2C' ) === 'C2C' ) ? 'UNIMARTC2C' : 'UNIMART'; ?>'
        };
    }
    jQuery(document).ready(function($) {
        var myMapWindow = null;

        // Remove MyDyBox phone number hyphen auto-formatting
        function removePhoneFormattingHandlers() {
            $(document.body).off('blur', '#billing_phone').off('focus', '#billing_phone');
        }
        removePhoneFormattingHandlers();
        $(document.body).on('focus click', '#billing_phone, #shipping_phone', removePhoneFormattingHandlers);
        
        // Strip non-digits from input fields in real-time
        $(document.body).on('input change blur focus', '#billing_phone, #shipping_phone', function() {
            var cleanVal = this.value.replace(/[^0-9]/g, '');
            if (this.value !== cleanVal) {
                this.value = cleanVal;
            }
        });
        
        // Run cleanup periodically on load to clean fields populated by AJAX / WooCommerce settings
        var cleanInterval = setInterval(function() {
            removePhoneFormattingHandlers();
            $('#billing_phone, #shipping_phone').each(function() {
                var cleanVal = this.value.replace(/[^0-9]/g, '');
                if (this.value !== cleanVal) {
                    this.value = cleanVal;
                }
            });
        }, 150);
        setTimeout(function() {
            clearInterval(cleanInterval);
        }, 4000);

        // Insert custom layout after WooCommerce forms load
        function initCustomCheckout() {
            // 1. Generate Custom Shipping Section HTML (if not already injected)
            if ($('#chao-shipping-section').length === 0) {
                var shippingHtml = `
                <div class="chao-checkout-section" id="chao-shipping-section">
                    <div class="chao-section-title">配送方式</div>
                    <div class="chao-shipping-cards-grid">
                        <div class="chao-card chao-shipping-card" data-method="cvs">
                            <div class="chao-card-check"></div>
                            <div class="chao-card-text">超商</div>
                        </div>
                        <div class="chao-card chao-shipping-card" data-method="delivery">
                            <div class="chao-card-check"></div>
                            <div class="chao-card-text">宅配</div>
                        </div>
                        <div class="chao-card chao-shipping-card" data-method="pickup">
                            <div class="chao-card-check"></div>
                            <div class="chao-card-text">門市自取</div>
                        </div>
                    </div>
                    
                    <div class="chao-cvs-options" style="display: none;">
                        <div class="chao-sub-title">請選擇超商</div>
                        <div class="chao-cvs-subcard chao-card active">
                            <div class="chao-card-check"></div>
                            <div class="chao-cvs-info">
                                <div class="chao-cvs-name">7-11 冷凍取貨(先付款)</div>
                                <div class="chao-cvs-price">NT$280</div>
                            </div>
                            <div class="chao-cvs-free-shipping-msg"></div>
                        </div>
                        <button type="button" class="chao-select-store-btn">請選擇取貨門市</button>
                        <input type="hidden" id="mydybox_cvs_store_id" name="mydybox_cvs_store_id" value="">
                        <input type="hidden" id="mydybox_cvs_store_name" name="mydybox_cvs_store_name" value="">
                        <input type="hidden" id="mydybox_cvs_store_addr" name="mydybox_cvs_store_addr" value="">
                        <input type="hidden" id="mydybox_cvs_store_type" name="mydybox_cvs_store_type" value="">
                        <div class="chao-selected-store-info" style="display: none;">
                            <strong>已選門市：</strong> <span class="chao-store-name"></span>
                        </div>
                    </div>
                </div>
                `;
                // Insert shipping layout before customer details form
                $('#customer_details').before(shippingHtml);
            }
            
            // 2. Generate Custom Payment Section HTML (if not already injected)
            if ($('#chao-payment-section').length === 0) {
                var paymentHtml = `
                <div class="chao-checkout-section" id="chao-payment-section">
                    <div class="chao-section-title">付款方式</div>
                    <div class="chao-payment-cards-grid">
                        <div class="chao-card chao-payment-card" data-payment="credit">
                            <div class="chao-card-check"></div>
                            <svg class="chao-payment-icon" viewBox="0 0 36 24" width="36" height="24" fill="none" stroke="#1e293b" stroke-width="1.5">
                                <rect x="2" y="2" width="32" height="20" rx="3" />
                                <rect x="6" y="7" width="8" height="6" rx="1" />
                                <circle cx="24" cy="15" r="3.5" />
                                <circle cx="28" cy="15" r="3.5" />
                            </svg>
                            <div class="chao-payment-info">
                                <span class="chao-payment-title">信用卡安全支付</span>
                                <span class="chao-payment-desc">信用卡一次付清 (VISA、MasterCard、JCB)</span>
                            </div>
                        </div>
                        <div class="chao-card chao-payment-card" data-payment="cod" style="display: none;">
                            <div class="chao-card-check"></div>
                            <svg class="chao-payment-icon" viewBox="0 0 36 24" width="36" height="24" fill="none" stroke="#1e293b" stroke-width="1.5">
                                <rect x="3" y="4" width="30" height="16" rx="2" />
                                <circle cx="18" cy="12" r="3" />
                                <path d="M7 12h3M26 12h3" />
                            </svg>
                            <div class="chao-payment-info">
                                <span class="chao-payment-title">超商取貨付款</span>
                                <span class="chao-payment-desc">貨到 7-11 / 全家超商再付款</span>
                            </div>
                        </div>
                        <div class="chao-card chao-payment-card" data-payment="linepay">
                            <div class="chao-card-check"></div>
                            <div class="chao-linepay-logo">
                                <span class="chao-linepay-text-line">LINE</span>
                                <span class="chao-linepay-text-pay">Pay</span>
                            </div>
                            <div class="chao-payment-info">
                                <span class="chao-payment-title">LINE Pay</span>
                                <span class="chao-payment-desc">使用 LINE Pay 行動支付，可折抵 LINE Points</span>
                            </div>
                        </div>
                        <div class="chao-card chao-payment-card" data-payment="atm">
                            <div class="chao-card-check"></div>
                            <svg class="chao-payment-icon" viewBox="0 0 36 24" width="36" height="24" fill="none" stroke="#1e293b" stroke-width="1.5">
                                <rect x="3" y="2" width="30" height="15" rx="2" />
                                <text x="18" y="11" font-family="sans-serif" font-weight="900" font-size="7" fill="#1e293b" text-anchor="middle" stroke="none">ATM</text>
                                <path d="M10,17 L6,22 L30,22 L26,17 Z" />
                            </svg>
                            <div class="chao-payment-info">
                                <span class="chao-payment-title">虛擬 ATM 轉帳</span>
                                <span class="chao-payment-desc">虛擬帳號轉帳：支援各家銀行 ATM / 網路銀行轉帳</span>
                            </div>
                        </div>
                        <div class="chao-card chao-payment-card" data-payment="cvscode">
                            <div class="chao-card-check"></div>
                            <svg class="chao-payment-icon" viewBox="0 0 36 24" width="36" height="24" fill="none" stroke="#1e293b" stroke-width="1.5">
                                <rect x="4" y="2" width="28" height="20" rx="2" />
                                <path d="M8 6h20M8 10h20M8 14h12M8 18h16" />
                            </svg>
                            <div class="chao-payment-info">
                                <span class="chao-payment-title">超商代碼繳費</span>
                                <span class="chao-payment-desc">超商代碼繳費：至超商多媒體機台列印繳費單</span>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="chao_chosen_payment_method" id="chao_chosen_payment_method" value="credit">
                </div>
                `;
                // Insert payment layout inside the payment div container
                $('.woocommerce-checkout-payment').prepend(paymentHtml);
            }

            // 3. Inject Secure Payment Overlay Loader if not exists
            if ($('#chao-checkout-loader-overlay').length === 0) {
                $('body').append(`
                    <div id="chao-checkout-loader-overlay" style="display:none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.6); backdrop-filter: blur(4px); z-index: 999999; justify-content: center; align-items: center; font-family: -apple-system, BlinkMacSystemFont, sans-serif;">
                        <div style="background: #fff; padding: 30px; border-radius: 12px; max-width: 400px; text-align: center; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); margin: 20px;">
                            <div style="width: 48px; height: 48px; border: 4px solid #f1f5f9; border-top-color: #3b82f6; border-radius: 50%; animation: chao-spin 1s linear infinite; margin: 0 auto 20px;"></div>
                            <h3 style="margin: 0 0 10px 0; color: #0f172a; font-size: 18px; font-weight: 700;">系統正在處理您的訂單...</h3>
                            <p style="margin: 0; color: #64748b; font-size: 14px; line-height: 1.6;">請勿重新整理網頁，或重複點擊送出按鈕，以免造成重複扣款或重複訂單。</p>
                        </div>
                    </div>
                    <style>
                        @keyframes chao-spin {
                            0% { transform: rotate(0deg); }
                            100% { transform: rotate(360deg); }
                        }
                    </style>
                `);
            }
            
            syncUIStates();
        }
        
        // Synchronize WooCommerce states to Custom UI
        function syncUIStates() {
            // Clean up any hyphens/spaces from phone fields (e.g. populated from user profile database)
            $('#billing_phone, #shipping_phone').each(function() {
                var cleanVal = this.value.replace(/[^0-9]/g, '');
                if (this.value !== cleanVal) {
                    this.value = cleanVal;
                }
            });

            // Display only the active shipping method and cost next to 運費
            $('#shipping_method li').hide();
            $('#shipping_method input:checked').closest('li').show();

            // --- SHIPPING ---
            var activeShipping = $('input[name^="shipping_method"]:checked').val() || '';
            
            // 7-11 CVS -> Wooecpay_Logistic_CVS_711
            if (activeShipping.indexOf('Wooecpay_Logistic_CVS_711') !== -1) {
                $('.chao-shipping-card[data-method="cvs"]').addClass('active').siblings().removeClass('active');
                $('.chao-cvs-options').show();
            }
            // Home Delivery -> flat_rate or free_shipping or Wooecpay_Logistic_Home_Tcat
            else if (activeShipping.indexOf('flat_rate') !== -1 || activeShipping.indexOf('free_shipping') !== -1 || activeShipping.indexOf('Wooecpay_Logistic_Home_Tcat') !== -1) {
                $('.chao-shipping-card[data-method="delivery"]').addClass('active').siblings().removeClass('active');
                $('.chao-cvs-options').hide();
            }
            // Local Pickup -> local_pickup
            else if (activeShipping.indexOf('local_pickup') !== -1) {
                $('.chao-shipping-card[data-method="pickup"]').addClass('active').siblings().removeClass('active');
                $('.chao-cvs-options').hide();
            }
            
            // Check if store info is selected
            var storeName = $('#mydybox_cvs_store_name').val() || '';
            var storeAddr = $('#mydybox_cvs_store_addr').val() || '';
            if (storeName) {
                $('.chao-store-name').text(storeName + ' (' + storeAddr + ')');
                $('.chao-selected-store-info').show();
                $('.chao-select-store-btn').text('更換取貨門市');
            } else {
                $('.chao-selected-store-info').hide();
                $('.chao-select-store-btn').text('請選擇取貨門市');
            }
            
            // --- SHIPPING FIELDS DYNAMIC TOGGLE ---
            var isCvsOrPickup = activeShipping.indexOf('Wooecpay_Logistic_CVS_711') !== -1 || activeShipping.indexOf('local_pickup') !== -1;
            if (isCvsOrPickup) {
                // CVS or Local Pickup -> Hide address fields
                $('#billing_state_field, #billing_city_field, #billing_address_1_field, #billing_postcode_field').hide();
                $('#shipping_state_field, #shipping_city_field, #shipping_address_1_field, #shipping_postcode_field').hide();
                $('.woocommerce-shipping-fields').hide(); // Also hide ship to different address checkbox wrapper
            } else {
                // Home Delivery -> Show address fields
                $('#billing_state_field, #billing_city_field, #billing_address_1_field, #billing_postcode_field').show();
                $('#shipping_state_field, #shipping_city_field, #shipping_address_1_field, #shipping_postcode_field').show();
                $('.woocommerce-shipping-fields').show();
            }
            
            // --- SHIPPING & PAYMENT BINDINGS ---
            var isCvs = activeShipping.indexOf('Wooecpay_Logistic_CVS_711') !== -1;
            var $codCard = $('.chao-payment-card[data-payment="cod"]');
            if (isCvs) {
                $codCard.show();
            } else {
                $codCard.hide();
                // If COD was chosen but shipping is no longer CVS, revert payment to credit
                if ($('#chao_chosen_payment_method').val() === 'cod') {
                    $('#chao_chosen_payment_method').val('credit');
                    $('input[name="payment_method"][value="chao_ecpay_ecpg"]').prop('checked', true).trigger('click');
                }
            }

            // Sync payment card active class
            var chosenPayment = $('#chao_chosen_payment_method').val() || 'credit';
            $('.chao-payment-card[data-payment="' + chosenPayment + '"]').addClass('active').siblings().removeClass('active');

            // Trigger checkout helpers
            updateSubmitButtonText();
            initTrustSeals();
            initCollapsibleOrderSummary();
            addInlineValidation();
        }
        
        // Handle custom card click events
        $(document.body).on('click', '.chao-shipping-card', function() {
            var method = $(this).data('method');
            var targetVal = '';
            
            if (method === 'cvs') {
                targetVal = $('input[name^="shipping_method"][value^="Wooecpay_Logistic_CVS_711"]').val();
            } else if (method === 'delivery') {
                // Prefer free_shipping, then flat_rate, then Wooecpay_Logistic_Home_Tcat
                var freeRadio = $('input[name^="shipping_method"][value^="free_shipping"]');
                var flatRadio = $('input[name^="shipping_method"][value^="flat_rate"]');
                var tcatRadio = $('input[name^="shipping_method"][value^="Wooecpay_Logistic_Home_Tcat"]');
                
                if (freeRadio.length > 0) targetVal = freeRadio.val();
                else if (flatRadio.length > 0) targetVal = flatRadio.val();
                else if (tcatRadio.length > 0) targetVal = tcatRadio.val();
            } else if (method === 'pickup') {
                targetVal = $('input[name^="shipping_method"][value^="local_pickup"]').val();
            }
            
            if (targetVal) {
                $('input[name^="shipping_method"][value="' + targetVal + '"]').prop('checked', true).trigger('change');
            }
        });
        
        $(document.body).on('click', '.chao-payment-card', function() {
            var payment = $(this).data('payment');
            $('#chao_chosen_payment_method').val(payment);
            
            if (payment === 'credit') {
                // 信用卡對應到新開發的綠界站內付 2.0 (chao_ecpay_ecpg)
                $('input[name="payment_method"][value="chao_ecpay_ecpg"]').prop('checked', true).trigger('click');
            } else if (payment === 'cod') {
                // 超商取貨付款對應到 WooCommerce 原生 cod
                $('input[name="payment_method"][value="cod"]').prop('checked', true).trigger('click');
            } else {
                // 其他付款方式 (LINE Pay, ATM 等) 仍走舊的 MyDyBox AIO 全方位金流 (mydybox_ecpay)
                $('input[name="payment_method"][value="mydybox_ecpay"]').prop('checked', true).trigger('click');
            }
            
            syncUIStates();
        });
        
        // Handle ECPay Store selection map button click (bypass provider check in original JS)
        $(document.body).on('click', '.chao-select-store-btn', function() {
            if (typeof mydyboxCvs !== 'undefined' && mydyboxCvs.ajaxUrl) {
                $.post(mydyboxCvs.ajaxUrl, {
                    action: 'mydybox_open_cvs_map',
                    nonce: mydyboxCvs.nonce,
                    cvs_type: mydyboxCvs.cvsType || 'UNIMART'
                }, function(res) {
                    if (!res.success) return;
                    var popup = window.open('', 'mydybox_cvs_map', 'width=1000,height=680,scrollbars=yes');
                    popup.document.open();
                    popup.document.write(res.data.form);
                    popup.document.close();
                    try { popup.document.forms[0].submit(); } catch (e) {}
                    myMapWindow = popup;
                });
            }
        });

        // Listen for postMessage from map popup
        window.addEventListener('message', function(e) {
            if (e.origin !== window.location.origin) return;
            if (!e.data || e.data.type !== 'mydybox_cvs_store') return;
            var store = e.data.store;
            if (!store || !store.id) return;

            $('#mydybox_cvs_store_id').val(store.id);
            $('#mydybox_cvs_store_name').val(store.name);
            $('#mydybox_cvs_store_addr').val(store.addr);
            $('#mydybox_cvs_store_type').val(store.type);

            if (myMapWindow) {
                myMapWindow.close();
                myMapWindow = null;
            }
            
            syncUIStates();
        });
        
        // Validate store selection on Place Order & trigger safe loading overlay
        $(document.body).on('checkout_place_order', function() {
            var activeShipping = $('input[name^="shipping_method"]:checked').val() || '';
            if (activeShipping.indexOf('Wooecpay_Logistic_CVS_711') !== -1) {
                var storeId = $('#mydybox_cvs_store_id').val() || '';
                if (!storeId) {
                    alert('請先選擇取貨門市！');
                    return false;
                }
            }
            
            // Show secure loading overlay to prevent double-clicks
            $('#chao-checkout-loader-overlay').css('display', 'flex');
        });

        // Hide overlay on checkout failure
        $(document.body).on('checkout_error', function() {
            $('#chao-checkout-loader-overlay').hide();
        });
        
        // Monitor hidden store values for changes using timer
        setInterval(function() {
            var currentStore = $('#mydybox_cvs_store_name').val() || '';
            var displayedStore = $('.chao-store-name').text() || '';
            if (currentStore && displayedStore.indexOf(currentStore) === -1) {
                syncUIStates();
            }
        }, 1000);

        // --- CHECKOUT UI HELPER FUNCTIONS ---

        function updateSubmitButtonText() {
            var totalText = $('.order-total .amount').first().text() || $('.order-total td').first().text() || '';
            if (totalText) {
                $('#place_order').text('確認付款 ' + totalText);
            }
        }
        
        function initTrustSeals() {
            if ($('#chao-trust-seals').length > 0) return;
            var sealsHtml = `
            <div id="chao-trust-seals" style="margin-top: 15px; text-align: center; border-top: 1px dashed #cbd5e1; padding-top: 15px; width: 100%;">
                <div style="display: flex; justify-content: center; gap: 15px; margin-bottom: 8px; flex-wrap: wrap;">
                    <span style="font-size: 12px; color: #64748b; display: flex; align-items: center; gap: 4px;">🛡️ SSL 256位元安全加密</span>
                </div>
                <div style="font-size: 11px; color: #94a3b8; line-height: 1.4;">
                    本網站採用綠界科技安全交易模組，全面保護您的付款與個人隱私資訊。
                </div>
            </div>
            `;
            $('#place_order').after(sealsHtml);
        }

        function initCollapsibleOrderSummary() {
            if ($(window).width() > 768 || $('#chao-collapsible-summary-trigger').length > 0) return;
            
            var totalText = $('.order-total .amount').first().text() || $('.order-total td').first().text() || '';
            
            var triggerHtml = `
            <div id="chao-collapsible-summary-trigger" style="display: flex; justify-content: space-between; align-items: center; padding: 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 15px; cursor: pointer; font-size: 14px; font-weight: 600; color: #0f172a;">
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span>🛒</span>
                    <span>顯示訂單明細</span>
                    <span id="chao-summary-arrow" style="font-size: 10px; transition: transform 0.2s;">▼</span>
                </div>
                <div style="color: #1e40af;">${totalText}</div>
            </div>
            `;
            
            var $reviewTable = $('#order_review');
            if ($reviewTable.length > 0 && $('#chao-summary-wrapper').length === 0) {
                $reviewTable.before(triggerHtml);
                $reviewTable.wrap('<div id="chao-summary-wrapper" style="display: none; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; margin-bottom: 20px; background: #fff;"></div>');
                
                $('#chao-collapsible-summary-trigger').on('click', function() {
                    $('#chao-summary-wrapper').slideToggle(200);
                    var arrow = $('#chao-summary-arrow');
                    if (arrow.text() === '▼') {
                        arrow.text('▲');
                    } else {
                        arrow.text('▼');
                    }
                });
            }
        }

        function addInlineValidation() {
            var fields = [
                { id: '#billing_first_name', errorMsg: '請輸入您的真實完整姓名', validate: val => val.trim().length >= 2 },
                { id: '#billing_phone', errorMsg: '請輸入有效的台灣手機號碼（例：0912345678）', validate: val => {
                    var activeShipping = $('input[name^="shipping_method"]:checked').val() || '';
                    var isPickup = activeShipping.indexOf('local_pickup') !== -1;
                    return isPickup ? (val.trim() === '' || /^09\d{8}$/.test(val.replace(/[-\s]/g, ''))) : /^09\d{8}$/.test(val.replace(/[-\s]/g, ''));
                }},
                { id: '#billing_email', errorMsg: '請輸入正確的電子郵件格式（例：customer@gmail.com）', validate: val => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val) },
                { id: '#billing_address_1', errorMsg: '請輸入詳細收件路街、樓層與門牌號碼', validate: val => {
                    var activeShipping = $('input[name^="shipping_method"]:checked').val() || '';
                    var isCvsOrPickup = activeShipping.indexOf('Wooecpay_Logistic_CVS_711') !== -1 || activeShipping.indexOf('local_pickup') !== -1;
                    return isCvsOrPickup || val.trim().length >= 5;
                }}
            ];
            
            fields.forEach(field => {
                var $input = $(field.id);
                if ($input.length === 0) return;
                
                var errorId = field.id.replace('#', '') + '_error';
                if ($('#' + errorId).length === 0) {
                    $input.after(`<div id="${errorId}" class="chao-inline-error" style="color: #ea4335; font-size: 12px; margin-top: 4px; display: none;">${field.errorMsg}</div>`);
                }
                
                $input.off('blur input.validation').on('blur input.validation', function() {
                    var val = $(this).val();
                    var isValid = field.validate(val);
                    var $errorDiv = $('#' + errorId);
                    
                    if (!isValid) {
                        $(this).css('border-color', '#ea4335');
                        $errorDiv.show();
                    } else {
                        $(this).css('border-color', '');
                        $errorDiv.hide();
                    }
                });
            });
        }
        
        // Initial setup and hooks
        initCustomCheckout();
        $(document.body).on('updated_checkout init_checkout', function() {
            initCustomCheckout();
            syncUIStates();
        });
    });
    </script>
    <?php
}

// Load custom LINE Login module
require_once get_template_directory() . '/includes/line-login.php';
require_once get_template_directory() . '/includes/ckc-referral.php'; // 分潤系統（推薦好友，第一階段點數軌）
require_once get_template_directory() . '/includes/ckc-referral-partner.php'; // 分潤系統（第二階段夥伴現金軌）
require_once get_template_directory() . '/includes/ckc-referral-admin.php'; // 分潤系統（後台夥伴管理頁）
require_once get_template_directory() . '/includes/ckc-coupons.php'; // 折扣券（領券中心＋專屬優惠券頁）

// Load custom ECPay ECPg 2.0 (站內付 2.0) Payment Gateway
require_once get_template_directory() . '/includes/ecpay-ecpg-gateway.php';

/**
 * --- CHECKOUT UX OPTIMIZATION BACKEND HOOKS ---
 */

/**
 * 1. Customize "Create an account?" checkbox label text
 */
add_filter( 'gettext', 'chao_custom_checkout_gettext', 20, 3 );
function chao_custom_checkout_gettext( $translated_text, $text, $domain ) {
    if ( is_checkout() && 'woocommerce' === $domain ) {
        if ( 'Create an account?' === $text ) {
            return '建立帳號以確認訂單 (非必填，免註冊也能結帳)';
        }
    }
    return $translated_text;
}

/**
 * 2. Dynamically restrict WooCommerce native COD payment availability to CVS shipping
 */
add_filter( 'woocommerce_available_payment_gateways', 'chao_available_payment_gateways', 25 );
function chao_available_payment_gateways( $gateways ) {
    if ( is_admin() ) {
        return $gateways;
    }
    
    // Check chosen shipping method
    $chosen_shipping = '';
    if ( WC()->session ) {
        $chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
        $chosen_shipping = isset( $chosen_methods[0] ) ? $chosen_methods[0] : '';
    }
    
    $is_cvs = ( strpos( $chosen_shipping, 'Wooecpay_Logistic_CVS_711' ) !== false );
    $loaded_gateways = WC()->payment_gateways->payment_gateways();
    
    if ( $is_cvs ) {
        // 1. If CVS is active, force-enable and inject native COD gateway
        if ( ! isset( $gateways['cod'] ) && isset( $loaded_gateways['cod'] ) ) {
            $cod_gateway = $loaded_gateways['cod'];
            $cod_gateway->enabled = 'yes';
            $cod_gateway->enable_for_methods = array();
            $gateways['cod'] = $cod_gateway;
        } elseif ( ! isset( $gateways['cod'] ) && class_exists( 'WC_Gateway_COD' ) ) {
            $cod_gateway = new WC_Gateway_COD();
            $cod_gateway->enabled = 'yes';
            $cod_gateway->enable_for_methods = array();
            $gateways['cod'] = $cod_gateway;
        }
        
        // 2. Re-inject Credit Card (站內付 2.0) and mydybox_ecpay (LINE Pay, ATM, CVS Code) if removed by shipping plugins
        if ( ! isset( $gateways['chao_ecpay_ecpg'] ) && isset( $loaded_gateways['chao_ecpay_ecpg'] ) ) {
            $gateways['chao_ecpay_ecpg'] = $loaded_gateways['chao_ecpay_ecpg'];
        }
        if ( ! isset( $gateways['mydybox_ecpay'] ) && isset( $loaded_gateways['mydybox_ecpay'] ) ) {
            $gateways['mydybox_ecpay'] = $loaded_gateways['mydybox_ecpay'];
        }
    } else {
        // Remove native COD option if chosen shipping is NOT CVS
        if ( isset( $gateways['cod'] ) ) {
            unset( $gateways['cod'] );
        }
    }
    
    return $gateways;
}

/**
 * 3. Render free shipping progress bar at the top of the checkout form
 */
add_action( 'woocommerce_before_checkout_form', 'chao_checkout_free_shipping_progress', 5 );
function chao_checkout_free_shipping_progress() {
    $threshold = chao_get_free_shipping_threshold();

    $cart_total = floatval( WC()->cart->get_cart_contents_total() );
    $remaining = $threshold - $cart_total;
    
    // Render progress bar
    ?>
    <div class="chao-shipping-progress-container" style="margin-bottom: 25px; padding: 18px; border-radius: 10px; background: #fff; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-size: 14px; font-weight: 600;">
            <span style="color: #334155; display: flex; align-items: center; gap: 6px;">
                <?php if ( $remaining > 0 ) : ?>
                    <span style="font-size: 16px;">🚚</span> 距離免運門檻還差 <span style="color: #e11d48; font-size: 16px; font-weight: 700;">NT$<?php echo number_format( $remaining ); ?></span>
                <?php else : ?>
                    <span style="font-size: 16px;">🎉</span> 恭喜！您已達免運門檻，本次配送免運費！
                <?php endif; ?>
            </span>
            <span style="color: #64748b; font-size: 13px;">免運門檻 NT$<?php echo number_format( $threshold ); ?></span>
        </div>
        <div class="chao-progress-track" style="width: 100%; height: 8px; background: #f1f5f9; border-radius: 4px; overflow: hidden;">
            <?php
            $percentage = min( 100, max( 0, ( $cart_total / $threshold ) * 100 ) );
            $bar_color = $remaining > 0 ? 'linear-gradient(90deg, #3b82f6 0%, #1d4ed8 100%)' : 'linear-gradient(90deg, #10b981 0%, #047857 100%)';
            ?>
            <div class="chao-progress-bar" style="width: <?php echo esc_attr( $percentage ); ?>%; height: 100%; background: <?php echo esc_attr( $bar_color ); ?>; transition: width 0.4s ease-in-out;"></div>
        </div>
    </div>
    <?php
}

/**
 * 4. Hook to render one-click guest registration on WooCommerce thank you page
 */
add_action( 'woocommerce_thankyou', 'chao_thankyou_guest_registration_form', 25, 1 );
function chao_thankyou_guest_registration_form( $order_id ) {
    if ( is_user_logged_in() || ! $order_id ) {
        return;
    }
    $order = wc_get_order( $order_id );
    if ( ! $order || $order->get_customer_id() ) {
        return;
    }
    $email = $order->get_billing_email();
    if ( ! $email || email_exists( $email ) ) {
        return;
    }
    
    // Output HTML and JS for guest registration
    ?>
    <div class="chao-thankyou-register-card" style="margin: 30px 0; padding: 25px; border: 1px solid #e2e8f0; border-radius: 12px; background: #f8fafc; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
        <h3 style="margin-top: 0; color: #1e293b; font-size: 18px; display: flex; align-items: center; gap: 8px;">
            <span>🎁</span> 一鍵建立帳號，隨時查詢訂單進度！
        </h3>
        <p style="color: #64748b; font-size: 14px; margin-bottom: 20px; line-height: 1.6;">
            免去重複填寫資料的麻煩，還能即時追蹤您的出貨進度。我們已為您帶入此訂單的電子郵件：<strong><?php echo esc_html( $email ); ?></strong>，只需設定密碼即可立即啟用帳號！
        </p>
        <div class="chao-register-inputs" style="display: flex; gap: 12px; max-width: 500px; flex-wrap: wrap;">
            <input type="password" id="chao_register_password" placeholder="請設定您的登入密碼" style="flex: 1; min-width: 220px; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;" />
            <button id="chao_register_submit" data-order-id="<?php echo esc_attr( $order_id ); ?>" style="padding: 10px 20px; background: #0073aa; color: #fff; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s;">
                立即建立並登入
            </button>
        </div>
        <div id="chao_register_message" style="margin-top: 12px; font-size: 14px; font-weight: 500;"></div>
    </div>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#chao_register_password').on('keypress', function(e) {
            if (e.which === 13) {
                $('#chao_register_submit').trigger('click');
            }
        });
        
        $('#chao_register_submit').on('click', function() {
            var $btn = $(this);
            var password = $('#chao_register_password').val();
            var orderId = $btn.data('order-id');
            var $msg = $('#chao_register_message');
            
            if (!password || password.length < 6) {
                $msg.css('color', '#ea4335').text('密碼長度需至少為 6 個字元。');
                return;
            }
            
            $btn.prop('disabled', true).text('建立中...');
            
            $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                action: 'chao_thankyou_guest_register',
                password: password,
                order_id: orderId,
                nonce: '<?php echo wp_create_nonce("chao_thankyou_register_nonce"); ?>'
            }, function(res) {
                if (res.success) {
                    $msg.css('color', '#10b981').text(res.data.message);
                    $('.chao-thankyou-register-card').delay(2500).slideUp(500);
                } else {
                    $msg.css('color', '#ea4335').text(res.data.message);
                    $btn.prop('disabled', false).text('立即建立並登入');
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * 5. AJAX handler for guest registration on thank you page
 */
add_action( 'wp_ajax_nopriv_chao_thankyou_guest_register', 'chao_ajax_thankyou_guest_register_handler' );
function chao_ajax_thankyou_guest_register_handler() {
    check_ajax_referer( 'chao_thankyou_register_nonce', 'nonce' );
    
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    
    if ( empty($password) || strlen($password) < 6 ) {
        wp_send_json_error( array( 'message' => '密碼長度需至少為 6 個字元。' ) );
    }
    
    if ( ! $order_id ) {
        wp_send_json_error( array( 'message' => '無效的訂單編號。' ) );
    }
    
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        wp_send_json_error( array( 'message' => '找不到對應的訂單。' ) );
    }
    
    $email = $order->get_billing_email();
    if ( ! $email ) {
        wp_send_json_error( array( 'message' => '該訂單無電子郵件資訊。' ) );
    }
    
    if ( email_exists( $email ) ) {
        wp_send_json_error( array( 'message' => '此電子郵件已被註冊，請直接登入。' ) );
    }
    
    // Create new customer
    $username = sanitize_user( current( explode( '@', $email ) ) );
    // Avoid username conflicts
    $base_username = $username;
    $i = 1;
    while ( username_exists( $username ) ) {
        $username = $base_username . $i;
        $i++;
    }
    
    $customer_id = wc_create_new_customer( $email, $username, $password );
    if ( is_wp_error( $customer_id ) ) {
        wp_send_json_error( array( 'message' => $customer_id->get_error_message() ) );
    }
    
    // Link current order to new customer
    update_post_meta( $order_id, '_customer_user', $customer_id );
    
    // Link past orders under same email
    wc_update_new_customer_past_orders( $customer_id );
    
    // Log the user in
    wp_clear_auth_cookie();
    wp_set_current_user( $customer_id );
    wp_set_auth_cookie( $customer_id );
    
    wp_send_json_success( array( 'message' => '🎉 帳號建立成功，系統已為您自動登入！' ) );
}

/**
 * 6. Programmatically force enable WooCommerce native COD settings option for frontend checkout
 */
add_filter( 'option_woocommerce_cod_settings', 'chao_force_enable_cod_setting' );
function chao_force_enable_cod_setting( $value ) {
    if ( is_admin() ) {
        return $value;
    }
    if ( ! is_array( $value ) ) {
        $value = array();
    }
    $value['enabled'] = 'yes';
    return $value;
}

/**
 * 7. Hide email field on account details page for LINE login users
 */
add_action( 'wp_head', 'chao_gang_cheng_hide_line_email_field' );
function chao_gang_cheng_hide_line_email_field() {
    if ( is_user_logged_in() ) {
        $current_user = wp_get_current_user();
        if ( $current_user && strpos( $current_user->user_email, 'line-login.local' ) !== false ) {
            ?>
            <style>
                /* Hide email address row from edit-account form */
                .woocommerce-EditAccountForm p.form-row:has(#account_email),
                .woocommerce-form-row:has(#account_email),
                p.form-row-wide:has(#account_email) {
                    display: none !important;
                }
            </style>
            <script>
                jQuery(document).ready(function($) {
                    // Fail-safe: double check and hide via JQuery
                    if ($('#account_email').length) {
                        $('#account_email').closest('.form-row').hide();
                    }
                });
            </script>
            <?php
        }
    }
}

/**
 * Customize WooCommerce loop add to cart link/button for out of stock products
 */
add_filter( 'woocommerce_loop_add_to_cart_link', 'ckc_custom_loop_add_to_cart_link', 99, 2 );
function ckc_custom_loop_add_to_cart_link( $html, $product ) {
    if ( ! $product->is_in_stock() ) {
        $html = sprintf(
            '<a href="javascript:void(0);" class="button add-to-cart-btn disabled" aria-label="%s" style="pointer-events: none; background-color: #eaeaea !important; color: #888 !important; border: 1px solid #ddd !important; text-align: center; box-shadow: none !important; cursor: not-allowed !important;">%s</a>',
            esc_attr__( '已售完', 'chao-gang-cheng' ),
            esc_html__( '已售完', 'chao-gang-cheng' )
        );
    }
    return $html;
}

/**
 * 31x. Order-pay（金流跳轉頁）防護：
 * 綠界 AIO 外掛在此頁以泛用選擇器自動送出表單，曾誤送主題搜尋表單導致
 * 跳轉到空白搜尋頁而非綠界付款頁。header.php 已在此頁停止輸出搜尋表單；
 * 這裡再加後備防護：移除任何 GET 表單（含管理列搜尋），並確保綠界付款
 * 表單被「定向」送出。
 */
add_action( 'wp_head', 'chao_orderpay_payment_redirect_guard', 5 );
function chao_orderpay_payment_redirect_guard() {
    if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'order-pay' ) ) {
        return;
    }
    ?>
    <script>
    (function() {
        // 移除頁面上所有 GET 表單（搜尋、管理列），讓 forms[0] 一定是付款表單
        function purgeGetForms() {
            var forms = document.querySelectorAll('form');
            for (var i = 0; i < forms.length; i++) {
                var f = forms[i];
                if ((f.method || '').toLowerCase() === 'get' && f.parentNode) {
                    f.parentNode.removeChild(f);
                }
            }
        }
        try {
            new MutationObserver(purgeGetForms).observe(document.documentElement, { childList: true, subtree: true });
        } catch (e) {}
        document.addEventListener('DOMContentLoaded', function() {
            purgeGetForms();
            // 後備：一秒後若仍在本頁，定向送出綠界付款表單
            setTimeout(function() {
                var pay = document.querySelector('form[action*="ecpay.com.tw"]');
                if (pay) {
                    pay.submit();
                }
            }, 1000);
        });
    })();
    </script>
    <?php
}

/* ============================================================
 * 32. Cart page UX optimizations (cart_ux_optimization_plan.docx §4–§5)
 *   32a. Estimated shipping row in cart totals (shipping transparency)
 *   32b. Free-shipping cross-sell block ("湊免運" recommendations)
 *   32c. Continue-shopping link in cart actions
 *   32d. Trust badges under the proceed-to-checkout button
 *   32e. Cart JS/CSS: auto quantity recalculation, live progress bar,
 *        mobile sticky checkout bar
 * ============================================================ */

// 32a-helper. Collect enabled paid shipping methods (title => cost) for the estimate display
function chao_get_estimated_shipping_rates() {
    $rates = array();
    if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
        return $rates;
    }
    foreach ( WC_Shipping_Zones::get_zones() as $zone ) {
        // Skip outlying-island zones so the estimate reflects the common case
        if ( isset( $zone['zone_name'] ) && preg_match( '/離島|澎湖|金門|馬祖/u', $zone['zone_name'] ) ) {
            continue;
        }
        foreach ( $zone['shipping_methods'] as $method ) {
            if ( 'yes' !== $method->enabled || 'free_shipping' === $method->id ) {
                continue;
            }
            $cost = $method->get_option( 'cost' );
            if ( '' === $cost || null === $cost || ! is_numeric( $cost ) ) {
                continue;
            }
            $title = $method->get_title();
            if ( $title && ! isset( $rates[ $title ] ) ) {
                $rates[ $title ] = floatval( $cost );
            }
        }
        if ( count( $rates ) >= 3 ) {
            break;
        }
    }
    return $rates;
}

// 32a. Show estimated shipping between subtotal and total on the cart page
add_action( 'woocommerce_cart_totals_before_order_total', 'chao_cart_estimated_shipping_row' );
function chao_cart_estimated_shipping_row() {
    $threshold = chao_get_free_shipping_threshold();
    $subtotal  = WC()->cart->get_subtotal();
    ?>
    <tr class="chao-est-shipping">
        <th>預估運費</th>
        <td data-title="預估運費">
            <?php if ( $subtotal >= $threshold ) : ?>
                <strong style="color:#16a34a;">免運費 🎉</strong>
            <?php else : ?>
                <?php
                $rates = chao_get_estimated_shipping_rates();
                if ( ! empty( $rates ) ) {
                    $parts = array();
                    foreach ( $rates as $title => $cost ) {
                        $parts[] = esc_html( $title ) . ' ' . wc_price( $cost );
                    }
                    echo '<span class="chao-est-shipping-rates">' . implode( '<span style="color:#94a3b8;">｜</span>', $parts ) . '</span>';
                }
                ?>
                <div style="font-size:12px;color:#64748b;margin-top:4px;">滿 <?php echo wc_price( $threshold ); ?> 免運，實際運費依結帳時選擇的物流方式計算</div>
            <?php endif; ?>
        </td>
    </tr>
    <?php
}

// 32b. "湊免運" cross-sell block: shown below the cart items when under the threshold
add_action( 'woocommerce_after_cart_table', 'chao_cart_free_shipping_cross_sell', 15 );
function chao_cart_free_shipping_cross_sell() {
    if ( ! WC()->cart || WC()->cart->is_empty() ) {
        return;
    }
    $threshold = chao_get_free_shipping_threshold();
    $subtotal  = WC()->cart->get_subtotal();
    if ( $subtotal >= $threshold ) {
        return;
    }
    $diff = $threshold - $subtotal;

    $exclude = array( 0 );
    foreach ( WC()->cart->get_cart() as $cart_item ) {
        $exclude[] = $cart_item['product_id'];
    }

    // Best sellers first; pick in-stock simple products whose price fits the gap (or a low-price cap)
    $price_cap = max( $diff, 400 );
    $query     = new WP_Query( array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 20,
        'post__not_in'   => $exclude,
        'meta_key'       => 'total_sales',
        'orderby'        => 'meta_value_num',
        'order'          => 'DESC',
        'no_found_rows'  => true,
    ) );

    $picks = array();
    foreach ( $query->posts as $post ) {
        $product = wc_get_product( $post->ID );
        if ( ! $product || ! $product->is_type( 'simple' ) || ! $product->is_in_stock() || ! $product->is_purchasable() ) {
            continue;
        }
        $price = floatval( $product->get_price() );
        if ( $price <= 0 || $price > $price_cap ) {
            continue;
        }
        $picks[] = $product;
        if ( count( $picks ) >= 4 ) {
            break;
        }
    }
    wp_reset_postdata();

    if ( count( $picks ) < 2 ) {
        return;
    }
    ?>
    <div class="chao-cart-cross-sell">
        <div class="chao-cart-cross-sell-title">還差 <strong><?php echo wc_price( $diff ); ?></strong> 免運，加購這些剛剛好 👇</div>
        <div class="chao-cart-cross-sell-grid">
            <?php foreach ( $picks as $product ) : ?>
                <div class="chao-cart-cross-sell-item">
                    <a href="<?php echo esc_url( $product->get_permalink() ); ?>" class="chao-cross-sell-thumb">
                        <?php echo $product->get_image( 'woocommerce_thumbnail' ); ?>
                    </a>
                    <a href="<?php echo esc_url( $product->get_permalink() ); ?>" class="chao-cross-sell-name"><?php echo esc_html( $product->get_name() ); ?></a>
                    <span class="chao-cross-sell-price"><?php echo $product->get_price_html(); ?></span>
                    <a href="<?php echo esc_url( $product->add_to_cart_url() ); ?>" data-quantity="1"
                       class="button add_to_cart_button ajax_add_to_cart chao-cross-sell-add"
                       data-product_id="<?php echo esc_attr( $product->get_id() ); ?>" rel="nofollow">＋ 加入購物車</a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

// 32c. Continue-shopping link inside the cart actions row
add_action( 'woocommerce_cart_actions', 'chao_cart_continue_shopping_link' );
function chao_cart_continue_shopping_link() {
    echo '<a class="chao-continue-shopping" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">← 繼續購物</a>';
}

// 32d. Trust badges + policy link under the proceed-to-checkout button
add_action( 'woocommerce_proceed_to_checkout', 'chao_cart_trust_badges', 30 );
function chao_cart_trust_badges() {
    ?>
    <div class="chao-cart-trust">
        <span>🔒 綠界科技 SSL 安全加密付款：VISA・MasterCard・JCB・LINE Pay</span>
        <a href="<?php echo esc_url( home_url( '/shipping-policy/' ) ); ?>">配送與運費政策</a>
    </div>
    <?php
}

// 32e. Cart page JS/CSS: auto quantity recalculation, live progress bar, mobile sticky checkout bar
add_action( 'wp_footer', 'chao_cart_ux_footer_assets' );
function chao_cart_ux_footer_assets() {
    if ( ! is_cart() ) {
        return;
    }
    $threshold = chao_get_free_shipping_threshold();
    ?>
    <style>
    /* Continue shopping link */
    .chao-continue-shopping { display: inline-block; margin-right: 12px; color: #64748b; text-decoration: none; font-size: 14px; line-height: 38px; }
    .chao-continue-shopping:hover { color: #1e293b; text-decoration: underline; }
    /* De-emphasize the now-automatic update button */
    .woocommerce-cart-form button[name="update_cart"] { opacity: 0.45; }
    /* Trust badges */
    .chao-cart-trust { margin-top: 12px; text-align: center; font-size: 12px; color: #64748b; line-height: 1.7; }
    .chao-cart-trust a { color: #64748b; text-decoration: underline; margin-left: 8px; }
    /* Cross-sell block */
    .chao-cart-cross-sell { margin: 20px 0; padding: 18px; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; }
    .chao-cart-cross-sell-title { font-size: 15px; font-weight: 600; color: #334155; margin-bottom: 14px; }
    .chao-cart-cross-sell-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
    .chao-cart-cross-sell-item { display: flex; flex-direction: column; align-items: center; text-align: center; gap: 6px; }
    .chao-cart-cross-sell-item .chao-cross-sell-thumb img { width: 100%; height: auto; border-radius: 8px; display: block; }
    .chao-cart-cross-sell-item .chao-cross-sell-name { font-size: 13px; color: #1e293b; text-decoration: none; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 36px; }
    .chao-cart-cross-sell-item .chao-cross-sell-price { font-size: 14px; font-weight: 700; color: #b91c1c; }
    .chao-cart-cross-sell-item .chao-cross-sell-add { font-size: 13px; padding: 6px 12px; width: 100%; text-align: center; }
    @media (max-width: 768px) {
        .chao-cart-cross-sell-grid { grid-template-columns: repeat(2, 1fr); }
    }
    /* Mobile sticky checkout bar */
    #chao-cart-sticky-bar { display: none; }
    @media (max-width: 768px) {
        #chao-cart-sticky-bar {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            position: fixed; bottom: 56px; left: 0; right: 0; z-index: 99998;
            background: #fff; border-top: 1px solid rgba(0,0,0,0.08);
            box-shadow: 0 -4px 20px rgba(0,0,0,0.08); padding: 10px 16px; box-sizing: border-box;
        }
        #chao-cart-sticky-bar .chao-cart-sticky-info { display: flex; flex-direction: column; line-height: 1.3; }
        #chao-cart-sticky-bar .chao-cart-sticky-info span { font-size: 12px; color: #64748b; }
        #chao-cart-sticky-bar .chao-cart-sticky-info strong { font-size: 18px; color: #b91c1c; }
        #chao-cart-sticky-bar .chao-cart-sticky-btn {
            flex: 1; max-width: 220px; text-align: center; background-color: var(--secondary-color, #7f6c60);
            color: #fff; border-radius: 24px; padding: 12px 18px; font-size: 15px; font-weight: 700; text-decoration: none;
        }
        body.woocommerce-cart { padding-bottom: 130px !important; }
    }
    </style>
    <?php if ( WC()->cart && ! WC()->cart->is_empty() ) : ?>
    <div id="chao-cart-sticky-bar">
        <div class="chao-cart-sticky-info">
            <span>總計</span>
            <strong id="chao-cart-sticky-total"></strong>
        </div>
        <a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" class="chao-cart-sticky-btn">前往結帳</a>
    </div>
    <?php endif; ?>
    <script>
    jQuery(function($) {
        var chaoFreeShipThreshold = <?php echo (int) $threshold; ?>;

        // 1. Auto quantity recalculation: debounce, then programmatically press "update cart"
        //    (Baymard: don't require an explicit update button click)
        var chaoQtyTimer = null;
        $(document.body).on('change input', '.woocommerce-cart-form input.qty', function() {
            clearTimeout(chaoQtyTimer);
            chaoQtyTimer = setTimeout(function() {
                var $btn = $('.woocommerce-cart-form button[name="update_cart"]');
                if ($btn.length) {
                    $btn.prop('disabled', false).attr('aria-disabled', 'false').trigger('click');
                }
            }, 600);
        });

        // 2. Keep the free-shipping progress bar in sync after AJAX cart updates
        //    (the bar sits outside the form WooCommerce replaces, so refresh it client-side)
        function chaoParseAmount(text) {
            var digits = (text || '').replace(/[^0-9.]/g, '');
            return digits ? parseFloat(digits) : NaN;
        }
        function chaoFormatNTD(num) {
            return 'NT$' + Math.round(num).toLocaleString('en-US');
        }
        function chaoRefreshProgress() {
            var $wrap = $('.cart-shipping-progress-wrapper');
            if (!$wrap.length) { return; }
            var subtotal = chaoParseAmount($('.cart_totals .cart-subtotal .woocommerce-Price-amount').first().text());
            if (isNaN(subtotal)) { return; }
            var percent, message;
            if (subtotal >= chaoFreeShipThreshold) {
                percent = 100;
                message = '🎉 太棒了！已符合免運條件，本筆訂單免運費！';
            } else {
                percent = Math.round((subtotal / chaoFreeShipThreshold) * 100);
                message = '🚚 還差 <strong>' + chaoFormatNTD(chaoFreeShipThreshold - subtotal) + '</strong> 即可享冷凍宅配、超商取貨免運費！';
            }
            $wrap.find('.progress-message').html(message);
            $wrap.find('.progress-bar-fill').css('width', percent + '%');
        }

        // 3. Mobile sticky bar: mirror the cart total, hide when the cart becomes empty
        function chaoSyncStickyBar() {
            var $bar = $('#chao-cart-sticky-bar');
            if (!$bar.length) { return; }
            if (!$('.woocommerce-cart-form').length) {
                $bar.hide();
                return;
            }
            var totalText = $('.cart_totals .order-total .woocommerce-Price-amount').last().text().trim();
            if (totalText) { $('#chao-cart-sticky-total').text(totalText); }
        }

        chaoSyncStickyBar();
        $(document.body).on('updated_cart_totals updated_wc_div wc_fragments_refreshed', function() {
            chaoRefreshProgress();
            chaoSyncStickyBar();
        });

        // 4. After an AJAX add-to-cart from the cross-sell block, reload so
        //    items, totals, progress bar and recommendations all refresh together
        $(document.body).on('added_to_cart', function() {
            location.reload();
        });
    });
    </script>
    <?php
}

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

