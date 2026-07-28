<?php
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
