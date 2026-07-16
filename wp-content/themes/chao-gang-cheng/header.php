<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="profile" href="https://gmpg.org/xfn/11">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div id="page" class="site">
    <!-- Announcement Bar -->
    <div class="announcement-bar">
        <span>📣📣📣運費算我的！！！/全館消費滿 $2,000！冷凍宅配、超商取貨免運費。下單後依訂單順序，現貨商品 5 個工作天內出貨。</span>
    </div>

    <header id="masthead" class="main-header" style="background-color: var(--white); box-shadow: 0 2px 10px rgba(0,0,0,0.02);">
        <!-- Desktop Header Layout -->
        <div class="desktop-header-wrapper">
            <!-- Row 1: Centered Logo & Right Actions -->
            <div class="container header-top-row" style="display: flex; align-items: center; justify-content: space-between; position: relative; padding: 15px 0;">
                <!-- Left Placeholder for Visual Centering -->
                <div class="header-left-empty" style="flex: 1; display: flex; align-items: center;"></div>
                
                <!-- Centered Brand Logo -->
                <div class="logo" style="flex: 1; display: flex; justify-content: center; text-align: center;">
                    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="display: inline-block;">
                        <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/logo.png' ); ?>" alt="<?php bloginfo( 'name' ); ?>" width="80" height="80" style="max-height: 80px; height: auto;">
                    </a>
                </div>

                <!-- Right-aligned Header Actions (Profile, Cart, Search) -->
                <div class="header-actions" style="flex: 1; display: flex; justify-content: flex-end; align-items: center; gap: 20px;">
                    
                    <!-- Member Account Icon & Dropdown -->
                    <?php if ( class_exists( 'WooCommerce' ) ) : ?>
                        <div class="account-menu-wrapper" style="position: relative; display: inline-block;">
                            <a href="<?php echo esc_url( get_permalink( get_option('woocommerce_myaccount_page_id') ) ); ?>" class="account-icon-wrapper" title="會員登入 / 我的帳戶" style="color: var(--secondary-color); display: flex; align-items: center; transition: var(--transition); padding: 5px 0;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                            </a>
                            <div class="account-dropdown">
                                <?php if ( is_user_logged_in() ) : 
                                    $current_user = wp_get_current_user();
                                    $display_name = !empty($current_user->display_name) ? $current_user->display_name : $current_user->user_login;
                                ?>
                                    <div class="user-welcome">
                                        <span>您好，<strong><?php echo esc_html( $display_name ); ?></strong></span>
                                    </div>
                                    <div class="dropdown-divider"></div>
                                    <ul class="dropdown-menu-list">
                                        <li>
                                            <a href="<?php echo esc_url( get_permalink( get_option('woocommerce_myaccount_page_id') ) ); ?>">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                                我的帳戶
                                            </a>
                                        </li>
                                        <li>
                                            <a href="<?php echo esc_url( wc_get_endpoint_url( 'orders', '', get_permalink( get_option('woocommerce_myaccount_page_id') ) ) ); ?>">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                                                訂單查詢
                                            </a>
                                        </li>
                                        <li>
                                            <a href="<?php echo esc_url( get_permalink( get_option('woocommerce_myaccount_page_id') ) ); ?>">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                                                專屬優惠券
                                            </a>
                                        </li>
                                        <li>
                                            <a href="<?php echo esc_url( get_permalink( get_option('woocommerce_myaccount_page_id') ) ); ?>">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                                                收藏清單
                                            </a>
                                        </li>
                                        <li class="logout-item">
                                            <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                                                會員登出
                                            </a>
                                        </li>
                                    </ul>
                                <?php else : ?>
                                    <div class="dropdown-buttons">
                                        <a href="<?php echo esc_url( get_permalink( get_option('woocommerce_myaccount_page_id') ) ); ?>" class="button dropdown-login-btn">會員登入</a>
                                        <a href="<?php echo esc_url( get_permalink( get_option('woocommerce_myaccount_page_id') ) ); ?>" class="button dropdown-register-btn">註冊新會員</a>
                                    </div>
                                    <div class="dropdown-divider"></div>
                                    <ul class="dropdown-menu-list">
                                        <li>
                                            <a href="<?php echo esc_url( get_permalink( get_option('woocommerce_myaccount_page_id') ) ); ?>">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                                我的帳戶
                                            </a>
                                        </li>
                                        <li>
                                            <a href="<?php echo esc_url( get_permalink( get_option('woocommerce_myaccount_page_id') ) ); ?>">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                                                訂單查詢
                                            </a>
                                        </li>
                                        <li>
                                            <a href="<?php echo esc_url( get_permalink( get_option('woocommerce_myaccount_page_id') ) ); ?>">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                                                專屬優惠券
                                            </a>
                                        </li>
                                        <li>
                                            <a href="<?php echo esc_url( get_permalink( get_option('woocommerce_myaccount_page_id') ) ); ?>">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                                                收藏清單
                                            </a>
                                        </li>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Shopping Cart Icon & Dropdown -->
                    <?php if ( class_exists( 'WooCommerce' ) ) : ?>
                        <div class="cart-menu-wrapper" style="position: relative; display: inline-block;">
                            <a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="cart-icon-wrapper" title="查看購物車" style="color: var(--secondary-color); display: flex; align-items: center; padding: 5px 0;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="9" cy="21" r="1"></circle>
                                    <circle cx="20" cy="21" r="1"></circle>
                                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                                </svg>
                                <span class="cart-count"><?php echo esc_html( WC()->cart->get_cart_contents_count() ); ?></span>
                            </a>
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
                        </div>
                    <?php endif; ?>

                    <!-- Search Icon & Dropdown -->
                    <div class="search-menu-wrapper" style="position: relative; display: inline-block;">
                        <a href="#" class="search-icon-toggle" title="搜尋商品" style="color: var(--secondary-color); display: flex; align-items: center; padding: 5px 0;" onclick="event.preventDefault();">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                        </a>
                        <div class="search-dropdown">
                            <form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>" style="display: flex; align-items: center; border: 1px solid var(--border-color); border-radius: 20px; padding: 2px 10px; background-color: var(--light-bg);">
                                <input type="search" class="search-field" placeholder="搜尋商品..." value="<?php echo get_search_query(); ?>" name="s" style="border: none; background: transparent; outline: none; font-size: 14px; width: 100%;" />
                                <input type="hidden" name="post_type" value="product" />
                                <button type="submit" class="search-submit" style="background: transparent; border: none; cursor: pointer; color: var(--text-muted);">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Row 2: Navigation Menu -->
            <div class="container header-navigation-row" style="border-top: 1px solid #f0f0f0; padding: 15px 0; display: flex; justify-content: center;">
                <nav id="site-navigation" class="main-navigation" style="width: 100%; text-align: center;">
                    <?php
                    wp_nav_menu( array(
                        'theme_location' => 'primary',
                        'menu_id'        => 'primary-menu',
                        'container'      => false,
                        'fallback_cb'    => 'chao_gang_cheng_fallback_menu',
                    ) );
                    
                    function chao_gang_cheng_fallback_menu() {
                        echo '<ul style="display: flex; list-style: none; gap: 25px 30px; justify-content: center; flex-wrap: wrap; padding: 0; margin: 0;">';
                        echo '<li><a href="' . esc_url( home_url( '/' ) ) . '">首頁</a></li>';
                        echo '<li><a href="' . esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ) . '">線上商城</a></li>';
                        echo '<li><a href="' . esc_url( get_permalink( wc_get_page_id( 'cart' ) ) ) . '">購物車</a></li>';
                        echo '<li><a href="' . esc_url( get_permalink( wc_get_page_id( 'checkout' ) ) ) . '">結帳</a></li>';
                        echo '</ul>';
                    }
                    ?>
                </nav>
            </div>
        </div>

        <!-- Mobile Header Layout -->
        <div class="mobile-header-wrapper">
            <div class="mobile-header-container">
                <!-- Left: Hamburger Menu Button -->
                <button class="mobile-menu-toggle" aria-label="打開選單">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
                
                <div class="mobile-logo-section">
                    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="display: flex; align-items: center; text-decoration: none;">
                        <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/logo.png' ); ?>" alt="<?php bloginfo( 'name' ); ?>" width="38" height="38" style="height: 38px; width: auto;">
                    </a>
                </div>
                
                <!-- Right: Search Icon Toggle Button -->
                <button class="mobile-search-toggle" aria-label="搜尋商品">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Search Dropdown Bar -->
        <div class="mobile-search-bar" style="display: none;">
            <div class="container">
                <form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>" style="display: flex; align-items: center; border: 1px solid var(--border-color); border-radius: 20px; padding: 6px 15px; background-color: var(--white); margin: 5px 0;">
                    <input type="search" class="search-field" placeholder="搜尋商品..." value="<?php echo get_search_query(); ?>" name="s" style="border: none; background: transparent; outline: none; font-size: 14px; width: 100%;" />
                    <input type="hidden" name="post_type" value="product" />
                    <button type="submit" class="search-submit" style="background: transparent; border: none; cursor: pointer; color: var(--text-muted); padding: 0;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </header>

    <!-- Mobile Drawer Overlay & Content -->
    <div class="mobile-menu-overlay" style="display: none;"></div>
    <div class="mobile-menu-drawer">
        <div class="mobile-drawer-header">
            <span class="mobile-drawer-title">全部分類</span>
            <button class="mobile-menu-close" aria-label="關閉選單">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="mobile-drawer-content">
            <nav class="mobile-navigation">
                <?php
                wp_nav_menu( array(
                    'theme_location' => 'primary',
                    'menu_id'        => 'mobile-primary-menu',
                    'container'      => false,
                    'fallback_cb'    => 'chao_gang_cheng_mobile_fallback_menu',
                ) );
                
                if ( ! function_exists( 'chao_gang_cheng_mobile_fallback_menu' ) ) {
                    function chao_gang_cheng_mobile_fallback_menu() {
                        echo '<ul class="mobile-menu-list">';
                        echo '<li><a href="' . esc_url( home_url( '/' ) ) . '">首頁</a></li>';
                        echo '<li><a href="' . esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ) . '">線上商城</a></li>';
                        echo '<li><a href="' . esc_url( get_permalink( wc_get_page_id( 'cart' ) ) ) . '">購物車</a></li>';
                        echo '<li><a href="' . esc_url( get_permalink( wc_get_page_id( 'checkout' ) ) ) . '">結帳</a></li>';
                        echo '</ul>';
                    }
                }
                ?>
            </nav>
            
            <!-- Mobile Drawer Account Section -->
            <div class="mobile-drawer-account">
                <div class="drawer-divider"></div>
                <h3 class="drawer-section-title">帳戶</h3>
                <?php if ( is_user_logged_in() ) : 
                    $current_user = wp_get_current_user();
                    $display_name = !empty($current_user->display_name) ? $current_user->display_name : $current_user->user_login;
                ?>
                    <div class="drawer-welcome" style="padding: 0 15px 10px 15px; font-size: 14px; color: var(--text-dark);">
                        您好，<strong><?php echo esc_html( $display_name ); ?></strong>
                    </div>
                    <ul class="drawer-account-menu" style="list-style: none; padding: 0 15px; margin: 5px 0;">
                        <li style="margin-bottom: 15px;">
                            <a href="<?php echo esc_url( get_permalink( get_option('woocommerce_myaccount_page_id') ) ); ?>" style="text-decoration: none; color: var(--text-dark); font-size: 14px; display: flex; align-items: center; gap: 8px;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                我的帳戶
                            </a>
                        </li>
                        <li style="margin-bottom: 15px;">
                            <a href="<?php echo esc_url( wc_get_endpoint_url( 'orders', '', get_permalink( get_option('woocommerce_myaccount_page_id') ) ) ); ?>" style="text-decoration: none; color: var(--text-dark); font-size: 14px; display: flex; align-items: center; gap: 8px;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                                訂單查詢
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" style="text-decoration: none; color: var(--price-color); font-size: 14px; display: flex; align-items: center; gap: 8px;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                                會員登出
                            </a>
                        </li>
                    </ul>
                <?php else : ?>
                    <div class="drawer-account-buttons" style="padding: 10px 0; display: flex; flex-direction: column; gap: 10px;">
                        <a href="<?php echo esc_url( get_permalink( get_option('woocommerce_myaccount_page_id') ) ); ?>" class="drawer-btn btn-login">會員登入</a>
                        <a href="<?php echo esc_url( get_permalink( get_option('woocommerce_myaccount_page_id') ) ); ?>" class="drawer-btn btn-register">註冊新會員</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Toggle mobile drawer
        $('.mobile-menu-toggle').attr('aria-expanded', 'false');
        $('.mobile-search-toggle').attr('aria-expanded', 'false');

        $('.mobile-menu-toggle').on('click', function(e) {
            e.preventDefault();
            $('.mobile-menu-drawer').addClass('is-open');
            $('.mobile-menu-overlay').fadeIn(250);
            $('body').addClass('mobile-menu-active');
            $(this).attr('aria-expanded', 'true');
            // Move focus into the drawer for keyboard/screen-reader users
            setTimeout(function() { $('.mobile-menu-close').trigger('focus'); }, 260);
        });

        // Close drawer function
        function closeMobileDrawer(returnFocus) {
            if (!$('.mobile-menu-drawer').hasClass('is-open')) { return; }
            $('.mobile-menu-drawer').removeClass('is-open');
            $('.mobile-menu-overlay').fadeOut(250);
            $('body').removeClass('mobile-menu-active');
            $('.mobile-menu-toggle').attr('aria-expanded', 'false');
            if (returnFocus) {
                $('.mobile-menu-toggle').trigger('focus');
            }
        }

        $('.mobile-menu-close, .mobile-menu-overlay').on('click', function(e) {
            e.preventDefault();
            closeMobileDrawer(true);
        });

        // Search bar open/close helpers
        function closeMobileSearchBar() {
            var $searchBar = $('.mobile-search-bar');
            if (!$searchBar.is(':visible')) { return; }
            $searchBar.slideUp(200);
            $('.mobile-search-toggle').removeClass('is-active').attr('aria-expanded', 'false');
        }

        // Toggle mobile search bar
        $('.mobile-search-toggle').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $searchBar = $('.mobile-search-bar');
            var $btn = $(this);
            $searchBar.slideToggle(200, function() {
                var open = $searchBar.is(':visible');
                $btn.toggleClass('is-active', open).attr('aria-expanded', open ? 'true' : 'false');
                if (open) {
                    $searchBar.find('.search-field').trigger('focus');
                }
            });
        });

        // Tapping/clicking outside the search bar closes it
        $(document).on('click touchstart', function(e) {
            if ($(e.target).closest('.mobile-search-bar, .mobile-search-toggle').length === 0) {
                closeMobileSearchBar();
            }
        });

        // Escape key closes the drawer and the search bar
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                closeMobileDrawer(true);
                closeMobileSearchBar();
            }
        });

        // Don't submit empty searches (both mobile and desktop header forms)
        $('.search-form').on('submit', function(e) {
            var $field = $(this).find('.search-field');
            if (!$.trim($field.val())) {
                e.preventDefault();
                $field.trigger('focus');
            }
        });
    });
    </script>


    <!-- Global Breadcrumb Navigation -->
    <?php if ( ! is_front_page() && function_exists( 'woocommerce_breadcrumb' ) ) : ?>
        <?php woocommerce_breadcrumb(); ?>
    <?php endif; ?>

    <div id="content" class="site-content">
