<?php
/**
 * 首頁模塊化編輯器 (Homepage Modular Builder)
 *
 * 讓後台操作者可以自行新增/刪除/複製/拖曳排序首頁區塊（模塊），
 * 並編輯每個模塊各自的內容欄位（文字、圖片、連結等），
 * 不需要工程師改程式碼即可調整首頁版面。
 *
 * 資料儲存：單一 wp_options 選項 `ckc_homepage_modules`，
 * 內容為模塊陣列，陣列順序即為首頁顯示順序。每個模塊：
 *   array(
 *     'id'       => 'mod_xxxxxxxx',   // 穩定識別碼（新增/複製時產生，用於後台操作，不影響前台）
 *     'type'     => 'banner',          // 對應 ckc_homepage_module_registry() 的 key
 *     'enabled'  => true,              // 是否顯示於首頁
 *     'settings' => array( ... ),      // 依模塊類型而異的欄位資料
 *   )
 *
 * @package Chao_Gang_Cheng
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* =========================================================================
 * 1. 模塊類型註冊表
 *
 * 每個模塊類型定義：
 *   - label       後台顯示名稱
 *   - description 後台簡短說明
 *   - fields      欄位 schema（後台表單自動產生 + 儲存時清理資料用）
 *
 * 欄位 type 支援：text / textarea / url / image / checkbox / number / select / repeater
 * ========================================================================= */
function ckc_homepage_module_registry() {
    static $registry = null;
    if ( null !== $registry ) {
        return $registry;
    }

    $shop_url = ( function_exists( 'wc_get_page_id' ) && class_exists( 'WooCommerce' ) )
        ? get_permalink( wc_get_page_id( 'shop' ) )
        : home_url( '/' );

    $registry = array(

        'banner' => array(
            'label'       => '主視覺橫幅 Banner',
            'description' => '首頁最上方的大型促銷橫幅（背景圖 + 多層文字 + 連結）。',
            'fields'      => array(
                'image'            => array( 'label' => '背景圖片', 'type' => 'image', 'default' => get_template_directory_uri() . '/assets/images/slide-buffet.jpg' ),
                'top_sub'          => array( 'label' => '頂部小標（第一行）', 'type' => 'text', 'default' => '【太陽百匯 SOLIS BUFFET】' ),
                'sub2'             => array( 'label' => '頂部小標（第二行）', 'type' => 'text', 'default' => '華麗盛宴・盡享海陸頂級美味' ),
                'center_slogan'    => array( 'label' => '置中主標語', 'type' => 'text', 'default' => '豪華龍蝦、生蠔、和牛、刺身' ),
                'badge'            => array( 'label' => '徽章文字', 'type' => 'text', 'default' => '限定活動' ),
                'sub_slogan'       => array( 'label' => '徽章旁副標語', 'type' => 'text', 'default' => '全新呈獻！' ),
                'title'            => array( 'label' => '主標題', 'type' => 'text', 'default' => '太陽百匯美食饗宴・平日單人餐券限時下殺' ),
                'desc'             => array( 'label' => '說明文字', 'type' => 'textarea', 'default' => '台中吃到飽首選！鮮美海鮮、現切和牛、各國百匯佳餚，即刻搶購享最優折扣！' ),
                'link'             => array( 'label' => '點擊連結', 'type' => 'url', 'default' => $shop_url ),
            ),
        ),

        'promo_list' => array(
            'label'       => '促銷清單 Promo List',
            'description' => '一排彩色促銷提示條，可自由新增/刪除多筆，每筆有文字、顏色、連結。',
            'fields'      => array(
                'items' => array(
                    'label'   => '促銷項目',
                    'type'    => 'repeater',
                    'row_fields' => array(
                        'text'  => array( 'label' => '文字', 'type' => 'text', 'default' => '' ),
                        'link'  => array( 'label' => '連結', 'type' => 'url', 'default' => '' ),
                        'color' => array( 'label' => '背景色', 'type' => 'color', 'default' => '#FFE8CC' ),
                    ),
                    'default' => array(
                        array( 'text' => '🔥 限時特惠｜太陽百匯平日單人餐券任選 3 張，結帳即享 95 折優惠！', 'link' => $shop_url . '?category=tickets', 'color' => '#FFE8CC' ),
                        array( 'text' => '🍲 本月限定｜招牌冷凍食品＋下酒菜任選 3 件 95 折，急速冷凍配送到家！', 'link' => $shop_url . '?category=frozen', 'color' => '#E8FFF6' ),
                        array( 'text' => '🍺 老饕最愛｜獨享紅燒牛肉爐＋經典老滷系列任選 2 件即享 9 折限時搶購！', 'link' => $shop_url . '?category=side-dishes', 'color' => '#FFECEC' ),
                    ),
                ),
            ),
        ),

        'hero_slider' => array(
            'label'       => '精選商品輪播 Hero Slider',
            'description' => '自動抓取「精選商品」（WooCommerce 商品的 Featured 標記）製作輪播；若無精選商品則顯示預設輪播圖。',
            'fields'      => array(
                'products_count' => array( 'label' => '最多顯示幾張', 'type' => 'number', 'default' => 5, 'min' => 1, 'max' => 10 ),
            ),
        ),

        'category_showcase' => array(
            'label'       => '分類商品展示區',
            'description' => '展示某一個商品分類底下的商品（一個模塊只對應一個分類；要展示多個分類，就新增多個此模塊並各自選擇分類）。',
            'fields'      => array(
                'category'               => array( 'label' => '商品分類', 'type' => 'select', 'options_callback' => 'ckc_homepage_get_product_cat_choices', 'default' => '' ),
                'title_override'         => array( 'label' => '標題文字（留空則用分類名稱）', 'type' => 'text', 'default' => '' ),
                'products_count'         => array( 'label' => '顯示商品數量', 'type' => 'number', 'default' => 4, 'min' => 1, 'max' => 12 ),
                'bg_light'               => array( 'label' => '使用淺灰底色', 'type' => 'checkbox', 'default' => false ),
                'divider_banner_image'   => array( 'label' => '區塊下方插播 Banner 圖片（留空則不顯示）', 'type' => 'image', 'default' => '' ),
                'divider_banner_link'    => array( 'label' => '插播 Banner 連結', 'type' => 'url', 'default' => '' ),
            ),
        ),

        'image_banner' => array(
            'label'       => '圖片橫幅 Banner（通用）',
            'description' => '單張全寬圖片橫幅，可設定點擊連結，適合插在任何位置作為活動宣傳。',
            'fields'      => array(
                'image'    => array( 'label' => '圖片', 'type' => 'image', 'default' => '' ),
                'link'     => array( 'label' => '點擊連結（留空則不可點擊）', 'type' => 'url', 'default' => '' ),
                'alt_text' => array( 'label' => '圖片替代文字（Alt）', 'type' => 'text', 'default' => '活動 Banner' ),
            ),
        ),

        'portfolio_grid' => array(
            'label'       => '最新消息／專案 Portfolio',
            'description' => '顯示「Portfolio」文章類型的最新項目卡片（沒有內容時顯示預設示意卡片）。',
            'fields'      => array(
                'heading'     => array( 'label' => '區塊標題（可留空）', 'type' => 'text', 'default' => '' ),
                'posts_count' => array( 'label' => '顯示數量', 'type' => 'number', 'default' => 4, 'min' => 1, 'max' => 12 ),
            ),
        ),

        'youtube_feed' => array(
            'label'       => 'YouTube 影片摘要',
            'description' => '顯示 YouTube 頻道的最新影片縮圖網格。',
            'fields'      => array(
                'heading'     => array( 'label' => '標題', 'type' => 'text', 'default' => '潮港城餐飲集團深耕台中三十年' ),
                'subheading'  => array( 'label' => '副標題', 'type' => 'text', 'default' => '邀你共同體驗、新鮮、誠信老朋友料理' ),
                'channel_url' => array( 'label' => '頻道連結', 'type' => 'url', 'default' => 'https://www.youtube.com/@ckcgroup' ),
            ),
        ),

        'social_links' => array(
            'label'       => '社群連結卡片',
            'description' => 'Facebook / Instagram / LINE / YouTube 四張社群連結卡片。',
            'fields'      => array(
                'facebook_url'  => array( 'label' => 'Facebook 連結', 'type' => 'url', 'default' => 'https://www.facebook.com/ckcfood/' ),
                'instagram_url' => array( 'label' => 'Instagram 連結', 'type' => 'url', 'default' => 'https://www.instagram.com/ckc_banquet/' ),
                'line_url'      => array( 'label' => 'LINE 連結', 'type' => 'url', 'default' => 'https://line.me/R/ti/p/@rsh5501l' ),
                'youtube_url'   => array( 'label' => 'YouTube 連結', 'type' => 'url', 'default' => 'https://www.youtube.com/@ckcgroup' ),
            ),
        ),

        'html_block' => array(
            'label'       => '自訂文字／HTML 區塊',
            'description' => '自由輸入文字或簡單 HTML，適合公告、活動說明等彈性內容。',
            'fields'      => array(
                'content'   => array( 'label' => '內容（支援基本 HTML）', 'type' => 'textarea', 'default' => '' ),
                'contained' => array( 'label' => '限制在版面寬度內（取消勾選則滿版）', 'type' => 'checkbox', 'default' => true ),
            ),
        ),
    );

    return $registry;
}

/**
 * 動態選單：商品分類清單（供 category_showcase 模塊的 category 欄位使用）
 */
function ckc_homepage_get_product_cat_choices() {
    $choices = array( '' => '— 請選擇分類 —' );
    if ( ! taxonomy_exists( 'product_cat' ) ) {
        return $choices;
    }
    $terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
    if ( ! is_wp_error( $terms ) ) {
        foreach ( $terms as $term ) {
            $choices[ $term->slug ] = $term->name;
        }
    }
    return $choices;
}

/**
 * 產生一個新的穩定模塊 ID
 */
function ckc_homepage_new_module_id() {
    return 'mod_' . substr( str_replace( '.', '', uniqid( '', true ) ), 0, 16 );
}

/**
 * 依欄位 schema 建立一個模塊的預設 settings
 */
function ckc_homepage_default_settings_for_type( $type ) {
    $registry = ckc_homepage_module_registry();
    if ( ! isset( $registry[ $type ]['fields'] ) ) {
        return array();
    }
    $settings = array();
    foreach ( $registry[ $type ]['fields'] as $key => $field ) {
        $settings[ $key ] = isset( $field['default'] ) ? $field['default'] : '';
    }
    return $settings;
}

/* =========================================================================
 * 2. 讀取 / 遷移 / 儲存
 * ========================================================================= */

/**
 * 取得目前的首頁模塊清單（陣列順序 = 首頁顯示順序）。
 * 若選項尚未建立（新系統第一次啟用），自動從既有 theme_mod 設定值
 * 遷移產生預設模塊清單，確保上線當下首頁畫面與遷移前完全一致。
 */
function ckc_get_homepage_modules() {
    $modules = get_option( 'ckc_homepage_modules', null );
    if ( null === $modules || ! is_array( $modules ) ) {
        $modules = ckc_homepage_modules_migrate_defaults();
        update_option( 'ckc_homepage_modules', $modules );
    }
    return $modules;
}

/**
 * 從既有 theme_mod／既有首頁邏輯建立預設模塊清單（僅在初次啟用時執行一次）。
 */
function ckc_homepage_modules_migrate_defaults() {
    $modules  = array();
    $shop_url = ( function_exists( 'wc_get_page_id' ) && class_exists( 'WooCommerce' ) )
        ? get_permalink( wc_get_page_id( 'shop' ) )
        : home_url( '/' );

    // 1. 主視覺橫幅
    $modules[] = array(
        'id'      => ckc_homepage_new_module_id(),
        'type'    => 'banner',
        'enabled' => true,
        'settings' => array(
            'image'         => get_theme_mod( 'ckc_banner_image', get_template_directory_uri() . '/assets/images/slide-buffet.jpg' ),
            'top_sub'       => get_theme_mod( 'ckc_banner_top_sub', '【太陽百匯 SOLIS BUFFET】' ),
            'sub2'          => get_theme_mod( 'ckc_banner_sub2', '華麗盛宴・盡享海陸頂級美味' ),
            'center_slogan' => get_theme_mod( 'ckc_banner_center_slogan', '豪華龍蝦、生蠔、和牛、刺身' ),
            'badge'         => get_theme_mod( 'ckc_banner_badge', '限定活動' ),
            'sub_slogan'    => get_theme_mod( 'ckc_banner_sub_slogan', '全新呈獻！' ),
            'title'         => get_theme_mod( 'ckc_banner_title', '太陽百匯美食饗宴・平日單人餐券限時下殺' ),
            'desc'          => get_theme_mod( 'ckc_banner_desc', '台中吃到飽首選！鮮美海鮮、現切和牛、各國百匯佳餚，即刻搶購享最優折扣！' ),
            'link'          => get_theme_mod( 'ckc_banner_link', '' ) ?: $shop_url,
        ),
    );

    // 2. 促銷清單
    $default_promo_links = array(
        1 => $shop_url . '?category=tickets',
        2 => $shop_url . '?category=frozen',
        3 => $shop_url . '?category=side-dishes',
    );
    $default_promo_text = array(
        1 => '🔥 限時特惠｜太陽百匯平日單人餐券任選 3 張，結帳即享 95 折優惠！',
        2 => '🍲 本月限定｜招牌冷凍食品＋下酒菜任選 3 件 95 折，急速冷凍配送到家！',
        3 => '🍺 老饕最愛｜獨享紅燒牛肉爐＋經典老滷系列任選 2 件即享 9 折限時搶購！',
    );
    $default_promo_color = array( 1 => '#FFE8CC', 2 => '#E8FFF6', 3 => '#FFECEC' );
    $promo_items = array();
    for ( $i = 1; $i <= 3; $i++ ) {
        $promo_items[] = array(
            'text'  => get_theme_mod( "ckc_promo_text_{$i}", $default_promo_text[ $i ] ),
            'link'  => get_theme_mod( "ckc_promo_link_{$i}", '' ) ?: $default_promo_links[ $i ],
            'color' => get_theme_mod( "ckc_promo_color_{$i}", $default_promo_color[ $i ] ),
        );
    }
    $modules[] = array(
        'id'       => ckc_homepage_new_module_id(),
        'type'     => 'promo_list',
        'enabled'  => true,
        'settings' => array( 'items' => $promo_items ),
    );

    // 3. 精選商品輪播
    $modules[] = array(
        'id'       => ckc_homepage_new_module_id(),
        'type'     => 'hero_slider',
        'enabled'  => true,
        'settings' => array( 'products_count' => 5 ),
    );

    // 4. 分類商品展示區（依現有「homepage-categories」選單位置或預設 3 分類）
    $theme_locations = get_nav_menu_locations();
    $menu_items       = array();
    if ( isset( $theme_locations['homepage-categories'] ) ) {
        $menu_obj = wp_get_nav_menu_object( $theme_locations['homepage-categories'] );
        if ( $menu_obj ) {
            $menu_items = wp_get_nav_menu_items( $menu_obj->term_id );
        }
    }
    $categories_to_show = array();
    if ( ! empty( $menu_items ) ) {
        foreach ( $menu_items as $item ) {
            if ( 'taxonomy' === $item->type && 'product_cat' === $item->object ) {
                $term_id = intval( $item->object_id );
                $term    = get_term( $term_id, 'product_cat' );
                if ( $term && ! is_wp_error( $term ) ) {
                    $categories_to_show[] = array( 'slug' => $term->slug, 'name' => $item->title ? $item->title : $term->name );
                }
            }
        }
    }
    if ( empty( $categories_to_show ) ) {
        $categories_to_show = array(
            array( 'slug' => 'tickets', 'name' => '太陽百匯餐券' ),
            array( 'slug' => 'frozen', 'name' => '經典冷凍食品' ),
            array( 'slug' => 'side-dishes', 'name' => '老滷系列' ),
        );
    }
    $idx = 0;
    foreach ( $categories_to_show as $cat_data ) {
        $idx++;
        $banner_enabled = get_theme_mod( "ckc_cat_banner_enable_{$idx}", true );
        $banner_img     = $banner_enabled ? get_theme_mod( "ckc_cat_banner_img_{$idx}", '' ) : '';
        $banner_link    = get_theme_mod( "ckc_cat_banner_link_{$idx}", '' );
        $modules[]      = array(
            'id'       => ckc_homepage_new_module_id(),
            'type'     => 'category_showcase',
            'enabled'  => true,
            'settings' => array(
                'category'             => $cat_data['slug'],
                'title_override'       => $cat_data['name'],
                'products_count'       => 4,
                'bg_light'             => ( ( $idx - 1 ) % 2 === 1 ),
                'divider_banner_image' => $banner_img,
                'divider_banner_link'  => $banner_link,
            ),
        );
    }

    // 5. 最新消息 Banner（若原本有設定才建立，否則略過）
    $news_banner_img = get_theme_mod( 'ckc_news_banner_img', '' );
    if ( get_theme_mod( 'ckc_news_banner_enable', true ) && ! empty( $news_banner_img ) ) {
        $modules[] = array(
            'id'       => ckc_homepage_new_module_id(),
            'type'     => 'image_banner',
            'enabled'  => true,
            'settings' => array(
                'image'    => $news_banner_img,
                'link'     => get_theme_mod( 'ckc_news_banner_link', '' ),
                'alt_text' => '最新消息 Banner',
            ),
        );
    }

    // 6. Portfolio 網格
    $modules[] = array(
        'id'       => ckc_homepage_new_module_id(),
        'type'     => 'portfolio_grid',
        'enabled'  => true,
        'settings' => array( 'heading' => '', 'posts_count' => 4 ),
    );

    // 7. YouTube 影片摘要
    $modules[] = array(
        'id'       => ckc_homepage_new_module_id(),
        'type'     => 'youtube_feed',
        'enabled'  => true,
        'settings' => array(
            'heading'     => '潮港城餐飲集團深耕台中三十年',
            'subheading'  => '邀你共同體驗、新鮮、誠信老朋友料理',
            'channel_url' => 'https://www.youtube.com/@ckcgroup',
        ),
    );

    // 8. 社群連結卡片
    $modules[] = array(
        'id'       => ckc_homepage_new_module_id(),
        'type'     => 'social_links',
        'enabled'  => true,
        'settings' => array(
            'facebook_url'  => 'https://www.facebook.com/ckcfood/',
            'instagram_url' => 'https://www.instagram.com/ckc_banquet/',
            'line_url'      => 'https://line.me/R/ti/p/@rsh5501l',
            'youtube_url'   => 'https://www.youtube.com/@ckcgroup',
        ),
    );

    return $modules;
}

/**
 * 依欄位型別清理單一欄位值
 */
function ckc_homepage_sanitize_field_value( $raw, $field ) {
    // 注意：呼叫端（ckc_save_homepage_modules_handler）已對整個 $_POST['modules']
    // 陣列做過一次 wp_unslash()，這裡不再重複 unslash，避免內容中若含反斜線被誤刪兩次。
    $type = isset( $field['type'] ) ? $field['type'] : 'text';
    switch ( $type ) {
        case 'textarea':
            return sanitize_textarea_field( $raw );
        case 'url':
        case 'image':
            return esc_url_raw( (string) $raw );
        case 'checkbox':
            return ! empty( $raw );
        case 'number':
            return is_numeric( $raw ) ? floatval( $raw ) : 0;
        case 'color':
            $raw = sanitize_text_field( (string) $raw );
            return preg_match( '/^#[0-9a-fA-F]{3,8}$/', $raw ) ? $raw : '#ffffff';
        case 'select':
            return sanitize_text_field( (string) $raw );
        default:
            return sanitize_text_field( (string) $raw );
    }
}

/**
 * 清理一個模塊的 settings（依 registry 的欄位 schema，含 repeater 子欄位）
 */
function ckc_homepage_sanitize_settings( $type, $raw_settings ) {
    $registry = ckc_homepage_module_registry();
    if ( ! isset( $registry[ $type ]['fields'] ) ) {
        return array();
    }
    $clean = array();
    foreach ( $registry[ $type ]['fields'] as $key => $field ) {
        if ( 'repeater' === $field['type'] ) {
            $rows_raw = isset( $raw_settings[ $key ] ) && is_array( $raw_settings[ $key ] ) ? $raw_settings[ $key ] : array();
            $rows     = array();
            foreach ( $rows_raw as $row_raw ) {
                if ( ! is_array( $row_raw ) ) {
                    continue;
                }
                $row = array();
                foreach ( $field['row_fields'] as $rkey => $rfield ) {
                    $row[ $rkey ] = ckc_homepage_sanitize_field_value( isset( $row_raw[ $rkey ] ) ? $row_raw[ $rkey ] : '', $rfield );
                }
                $rows[] = $row;
            }
            $clean[ $key ] = $rows;
        } else {
            $clean[ $key ] = ckc_homepage_sanitize_field_value( isset( $raw_settings[ $key ] ) ? $raw_settings[ $key ] : '', $field );
        }
    }
    return $clean;
}

/**
 * 儲存表單處理
 */
add_action( 'admin_post_ckc_save_homepage_modules', 'ckc_save_homepage_modules_handler' );
function ckc_save_homepage_modules_handler() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( '權限不足' );
    }
    check_admin_referer( 'ckc_homepage_modules_save', 'ckc_homepage_modules_nonce' );

    $registry = ckc_homepage_module_registry();
    $raw      = isset( $_POST['modules'] ) && is_array( $_POST['modules'] ) ? wp_unslash( $_POST['modules'] ) : array();

    $clean = array();
    foreach ( $raw as $mod ) {
        if ( ! is_array( $mod ) || empty( $mod['type'] ) ) {
            continue;
        }
        $type = sanitize_key( $mod['type'] );
        if ( ! isset( $registry[ $type ] ) ) {
            continue; // 未知模塊類型，忽略
        }
        $id = isset( $mod['id'] ) ? sanitize_key( $mod['id'] ) : '';
        if ( '' === $id ) {
            $id = ckc_homepage_new_module_id();
        }
        $clean[] = array(
            'id'       => $id,
            'type'     => $type,
            'enabled'  => ! empty( $mod['enabled'] ),
            'settings' => ckc_homepage_sanitize_settings( $type, isset( $mod['settings'] ) && is_array( $mod['settings'] ) ? $mod['settings'] : array() ),
        );
    }

    update_option( 'ckc_homepage_modules', $clean );

    wp_safe_redirect( add_query_arg( array( 'page' => 'ckc-homepage-builder', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
    exit;
}

/* =========================================================================
 * 3. 後台選單頁面
 * ========================================================================= */
add_action( 'admin_menu', 'ckc_homepage_builder_add_menu', 10 );
function ckc_homepage_builder_add_menu() {
    // 位置 29：緊接在「網站功能」（位置 28）之後，落在後台選單「網站內容」分類區塊內
    // （「電商營運」分類標題是由 functions.php 的 chao_gang_cheng_admin_menu_styling()
    // 以 JS 動態插入在「出貨AI助理」〔ckc-gemini-agent，位置約 54.9〕正前方，
    // 所以只要位置數字小於它，就會落在「網站內容」區塊）。
    //
    // 這裡是頂層選單「首頁」，把原本各自獨立佔一排的 6 個首頁相關設定
    // （首頁編輯、彈窗管理、分類管理、選單管理、快捷列設定、Logo 設定）
    // 收整成它底下的子選單，側邊欄從佔 6 排縮成 1 排，點開才展開 6 個項目。
    // 其餘 5 個子選單分別在各自檔案（popup.php、site-logo.php、
    // functions.php 內的快捷列設定／分類管理／選單管理區塊）用遞增的
    // admin_menu 優先權（11～15）依序註冊，確保子選單顯示順序穩定、
    // 不受各檔案載入先後順序影響。
    add_menu_page(
        '首頁',
        '首頁',
        'manage_options',
        'ckc-homepage-builder',
        'ckc_homepage_builder_render_page',
        'dashicons-layout',
        29
    );

    // slug 與頂層選單相同時，WordPress 預設會自動產生一個跟頂層選單同名
    // （這裡會是「首頁」）的第一個子選單項目；這裡明確呼叫 add_submenu_page()
    // 覆寫它的顯示文字，改成「首頁編輯」，跟其他 5 個子選單並列時語意才清楚。
    add_submenu_page(
        'ckc-homepage-builder',
        '首頁編輯',
        '首頁編輯',
        'manage_options',
        'ckc-homepage-builder',
        'ckc_homepage_builder_render_page'
    );
}

/**
 * 依欄位 schema 輸出單一欄位的表單 HTML
 *
 * @param string $name_prefix 例如 modules[mod_xxx][settings]
 */
function ckc_homepage_render_field( $name_prefix, $key, $field, $value ) {
    $name = $name_prefix . '[' . $key . ']';
    $id   = sanitize_html_class( $name );
    $type = isset( $field['type'] ) ? $field['type'] : 'text';
    echo '<div class="ckc-hb-field ckc-hb-field-' . esc_attr( $type ) . '">';
    if ( 'checkbox' !== $type ) {
        echo '<label class="ckc-hb-field-label" for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label>';
    }

    switch ( $type ) {
        case 'textarea':
            echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="3">' . esc_textarea( (string) $value ) . '</textarea>';
            break;

        case 'checkbox':
            echo '<label class="ckc-hb-checkbox-label"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( ! empty( $value ), true, false ) . '> ' . esc_html( $field['label'] ) . '</label>';
            break;

        case 'number':
            $min = isset( $field['min'] ) ? ' min="' . esc_attr( $field['min'] ) . '"' : '';
            $max = isset( $field['max'] ) ? ' max="' . esc_attr( $field['max'] ) . '"' : '';
            echo '<input type="number" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . $min . $max . '>';
            break;

        case 'color':
            echo '<input type="color" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ? $value : '#ffffff' ) . '" class="ckc-hb-color">';
            break;

        case 'url':
            echo '<input type="url" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" placeholder="https://" class="widefat">';
            break;

        case 'select':
            $choices = array();
            if ( ! empty( $field['options_callback'] ) && function_exists( $field['options_callback'] ) ) {
                $choices = call_user_func( $field['options_callback'] );
            } elseif ( ! empty( $field['options'] ) ) {
                $choices = $field['options'];
            }
            echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
            foreach ( $choices as $ckey => $clabel ) {
                echo '<option value="' . esc_attr( $ckey ) . '"' . selected( (string) $value, (string) $ckey, false ) . '>' . esc_html( $clabel ) . '</option>';
            }
            echo '</select>';
            break;

        case 'image':
            echo '<div class="ckc-hb-image-field">';
            echo '<input type="url" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="ckc-hb-image-url widefat" placeholder="https://">';
            echo '<div class="ckc-hb-image-preview-wrap">';
            if ( $value ) {
                echo '<img class="ckc-hb-image-preview" src="' . esc_url( $value ) . '" alt="">';
            } else {
                echo '<img class="ckc-hb-image-preview" src="" alt="" style="display:none;">';
            }
            echo '</div>';
            echo '<button type="button" class="button ckc-hb-image-pick">選擇圖片</button> ';
            echo '<button type="button" class="button ckc-hb-image-clear">清除</button>';
            echo '</div>';
            break;

        case 'repeater':
            echo '<div class="ckc-hb-repeater" data-row-template-name="' . esc_attr( $name ) . '">';
            echo '<div class="ckc-hb-repeater-rows">';
            $rows = is_array( $value ) ? $value : array();
            $row_idx = 0;
            foreach ( $rows as $row ) {
                // 注意：每一列的 text/link/color 欄位必須共用「同一個」陣列索引
                // （例如 items[0][text]、items[0][link]、items[0][color]），
                // 才能讓 PHP 把它們解析回同一列。如果像舊版一樣三個欄位都用
                // items[][text]／items[][link]／items[][color] 這種空中括號，
                // PHP 會把每一次的 [] 都當成「陣列再多加一個全新的元素」，
                // 跟欄位名稱完全無關——結果 N 列會被拆成 3N 個各自只有一個
                // 欄位有值的破碎項目，儲存一次資料就亂掉一次（已在實機驗證
                // 中發現此問題並修正）。
                ckc_homepage_render_repeater_row( $name, $field['row_fields'], $row, $row_idx );
                $row_idx++;
            }
            echo '</div>';
            echo '<button type="button" class="button ckc-hb-repeater-add">＋ 新增一筆</button>';
            // 隱藏的空白列範本：即使目前一筆都沒有，也能靠這份範本新增新的一筆。
            // 範本裡的欄位一律加上 disabled，確保這份範本本身「絕對不會」被
            // 表單一併送出（disabled 的欄位瀏覽器天生就不會提交）；使用者按
            // 「＋ 新增一筆」時，JS 會複製這份範本、拿掉 disabled、並換上一個
            // 真正唯一的列索引（__ROWIDX__）後才附加到畫面上。
            echo '<div class="ckc-hb-repeater-row-template" style="display:none;">';
            ckc_homepage_render_repeater_row( $name, $field['row_fields'], array(), '__ROWIDX__', true );
            echo '</div>';
            echo '</div>';
            break;

        default:
            echo '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="widefat">';
    }
    echo '</div>';
}

function ckc_homepage_render_repeater_row( $name, $row_fields, $row_value, $row_idx, $is_template = false ) {
    $disabled = $is_template ? ' disabled="disabled"' : '';
    echo '<div class="ckc-hb-repeater-row">';
    echo '<span class="ckc-hb-repeater-drag">☰</span>';
    echo '<div class="ckc-hb-repeater-row-fields">';
    foreach ( $row_fields as $rkey => $rfield ) {
        // 三個欄位共用同一個 $row_idx，例如 items[0][text]／items[0][link]／
        // items[0][color]，儲存時才能正確組回同一列（見上方呼叫端註解）。
        $rname = $name . '[' . $row_idx . '][' . $rkey . ']';
        $rval  = isset( $row_value[ $rkey ] ) ? $row_value[ $rkey ] : '';
        if ( 'color' === $rfield['type'] ) {
            echo '<input type="color" name="' . esc_attr( $rname ) . '" value="' . esc_attr( $rval ? $rval : '#ffffff' ) . '" class="ckc-hb-color" title="' . esc_attr( $rfield['label'] ) . '"' . $disabled . '>';
        } elseif ( 'url' === $rfield['type'] ) {
            echo '<input type="url" name="' . esc_attr( $rname ) . '" value="' . esc_attr( $rval ) . '" placeholder="' . esc_attr( $rfield['label'] ) . '"' . $disabled . '>';
        } else {
            echo '<input type="text" name="' . esc_attr( $rname ) . '" value="' . esc_attr( $rval ) . '" placeholder="' . esc_attr( $rfield['label'] ) . '"' . $disabled . '>';
        }
    }
    echo '</div>';
    echo '<button type="button" class="button-link ckc-hb-repeater-remove" title="刪除">✕</button>';
    echo '</div>';
}

/**
 * 輸出單一模塊（可用於既有模塊，也可用於「新增模塊」的隱藏範本）
 */
function ckc_homepage_render_module_block( $module, $registry ) {
    $type    = $module['type'];
    $type_def = isset( $registry[ $type ] ) ? $registry[ $type ] : null;
    if ( ! $type_def ) {
        return;
    }
    $prefix = 'modules[' . $module['id'] . ']';
    ?>
    <div class="ckc-hb-module" data-id="<?php echo esc_attr( $module['id'] ); ?>" data-type="<?php echo esc_attr( $type ); ?>">
        <input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[id]" value="<?php echo esc_attr( $module['id'] ); ?>">
        <input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[type]" value="<?php echo esc_attr( $type ); ?>">
        <div class="ckc-hb-module-header">
            <span class="ckc-hb-drag-handle" title="拖曳排序">☰</span>
            <span class="ckc-hb-module-title">
                <strong><?php echo esc_html( $type_def['label'] ); ?></strong>
                <span class="ckc-hb-module-subtitle"><?php echo esc_html( ckc_homepage_module_summary( $module ) ); ?></span>
            </span>
            <label class="ckc-hb-enable-toggle">
                <input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[enabled]" value="1" <?php checked( ! empty( $module['enabled'] ), true ); ?>>
                啟用
            </label>
            <button type="button" class="button ckc-hb-toggle-body">展開／收合</button>
            <button type="button" class="button ckc-hb-duplicate">複製</button>
            <button type="button" class="button ckc-hb-delete">刪除</button>
        </div>
        <div class="ckc-hb-module-body" style="display:none;">
            <p class="ckc-hb-module-desc"><?php echo esc_html( $type_def['description'] ); ?></p>
            <?php
            foreach ( $type_def['fields'] as $fkey => $field ) {
                $fval = isset( $module['settings'][ $fkey ] ) ? $module['settings'][ $fkey ] : ( isset( $field['default'] ) ? $field['default'] : '' );
                ckc_homepage_render_field( $prefix . '[settings]', $fkey, $field, $fval );
            }
            ?>
        </div>
    </div>
    <?php
}

/**
 * 模塊列表項目的小提示文字（顯示目前重點內容，方便在收合狀態下辨識）
 */
function ckc_homepage_module_summary( $module ) {
    $s = isset( $module['settings'] ) ? $module['settings'] : array();
    switch ( $module['type'] ) {
        case 'banner':
            return isset( $s['title'] ) ? $s['title'] : '';
        case 'category_showcase':
            return isset( $s['category'] ) && $s['category'] ? $s['category'] : '尚未選擇分類';
        case 'image_banner':
            return isset( $s['alt_text'] ) ? $s['alt_text'] : '';
        case 'youtube_feed':
            return isset( $s['heading'] ) ? $s['heading'] : '';
        case 'html_block':
            return isset( $s['content'] ) ? wp_strip_all_tags( mb_strimwidth( $s['content'], 0, 30, '…' ) ) : '';
        default:
            return '';
    }
}

function ckc_homepage_builder_render_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $registry = ckc_homepage_module_registry();
    $modules  = ckc_get_homepage_modules();
    ?>
    <div class="wrap ckc-hb-wrap">
        <h1>首頁編輯</h1>
        <p>拖曳「☰」可調整區塊上下順序；每個區塊都可以個別啟用／停用、複製、刪除，並展開編輯內容。修改完成後記得按下方「儲存變更」。</p>
        <?php if ( isset( $_GET['updated'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p>首頁設定已儲存！</p></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ckc-hb-form">
            <input type="hidden" name="action" value="ckc_save_homepage_modules">
            <?php wp_nonce_field( 'ckc_homepage_modules_save', 'ckc_homepage_modules_nonce' ); ?>

            <div class="ckc-hb-toolbar">
                <select id="ckc-hb-add-type">
                    <?php foreach ( $registry as $type_key => $type_def ) : ?>
                        <option value="<?php echo esc_attr( $type_key ); ?>"><?php echo esc_html( $type_def['label'] ); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="button button-primary" id="ckc-hb-add-btn">＋ 新增模塊</button>
            </div>

            <div class="ckc-hb-module-list" id="ckc-hb-module-list">
                <?php foreach ( $modules as $module ) : ?>
                    <?php ckc_homepage_render_module_block( $module, $registry ); ?>
                <?php endforeach; ?>
            </div>

            <p class="submit">
                <button type="submit" class="button button-primary button-hero">儲存變更</button>
            </p>
        </form>

        <!-- 隱藏範本：每種模塊類型各一份，供「新增模塊」JS 複製使用 -->
        <div id="ckc-hb-templates" style="display:none;">
            <?php
            foreach ( $registry as $type_key => $type_def ) {
                $tmp_module = array(
                    'id'       => '__ID__',
                    'type'     => $type_key,
                    'enabled'  => true,
                    'settings' => ckc_homepage_default_settings_for_type( $type_key ),
                );
                echo '<div class="ckc-hb-template" data-type="' . esc_attr( $type_key ) . '">';
                ckc_homepage_render_module_block( $tmp_module, $registry );
                echo '</div>';
            }
            ?>
        </div>
    </div>
    <?php
}

/**
 * 後台頁面樣式與 JS（拖曳排序、新增/刪除/複製模塊、repeater 子項目、圖片選擇器）
 *
 * 注意：CSS／JS 一律透過 wp_add_inline_style()／wp_add_inline_script() 附加在
 * 有明確相依關係（jquery、jquery-ui-sortable、media-editor）的已註冊 handle 上，
 * 不能直接 echo <style>/<script> 標籤——admin_enqueue_scripts 這個 hook 是在
 * <head> 最前面（jQuery 本體都還沒載入之前）就會執行，若直接 echo 會讓這段
 * <script> 在 jQuery 尚未定義時就先執行，導致 jQuery(...) 拋出錯誤、後面所有
 * 事件綁定（展開/收合、拖曳排序、新增/刪除/複製模塊、repeater、圖片選擇器）
 * 全部失效，且畫面上完全看不出任何反應（此問題已在實機測試中發現並修正）。
 */
add_action( 'admin_enqueue_scripts', 'ckc_homepage_builder_admin_assets' );
function ckc_homepage_builder_admin_assets( $hook ) {
    if ( 'toplevel_page_ckc-homepage-builder' !== $hook ) {
        return;
    }
    wp_enqueue_media();
    wp_enqueue_script( 'jquery-ui-sortable' );

    $css = <<<'CSS'
        .ckc-hb-toolbar { margin: 16px 0; display: flex; gap: 10px; align-items: center; }
        .ckc-hb-module-list { max-width: 900px; }
        .ckc-hb-module { background: #fff; border: 1px solid #dcdcde; border-radius: 6px; margin-bottom: 10px; }
        .ckc-hb-module-header { display: flex; align-items: center; gap: 12px; padding: 12px 14px; }
        .ckc-hb-drag-handle { cursor: grab; color: #8c8f94; font-size: 16px; }
        .ckc-hb-module-title { flex: 1; display: flex; flex-direction: column; }
        .ckc-hb-module-subtitle { color: #8c8f94; font-size: 12px; }
        .ckc-hb-enable-toggle { font-size: 13px; white-space: nowrap; }
        .ckc-hb-module-body { border-top: 1px solid #f0f0f1; padding: 16px 14px; background: #fafafa; }
        .ckc-hb-module-desc { color: #646970; font-size: 12px; margin-top: 0; }
        .ckc-hb-field { margin-bottom: 14px; max-width: 640px; }
        .ckc-hb-field-label { display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px; }
        .ckc-hb-field textarea, .ckc-hb-field input[type=text], .ckc-hb-field input[type=url], .ckc-hb-field input[type=number], .ckc-hb-field select { width: 100%; max-width: 480px; }
        .ckc-hb-image-preview { max-width: 160px; max-height: 90px; display: block; margin: 8px 0; border: 1px solid #dcdcde; border-radius: 4px; }
        .ckc-hb-repeater-row { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; background: #fff; border: 1px solid #e2e2e2; border-radius: 4px; padding: 8px; }
        .ckc-hb-repeater-row-fields { display: flex; gap: 8px; flex: 1; flex-wrap: wrap; }
        .ckc-hb-repeater-row-fields input[type=text], .ckc-hb-repeater-row-fields input[type=url] { flex: 1; min-width: 160px; }
        .ckc-hb-repeater-remove { color: #b32d2e; }
        .ckc-hb-placeholder { border: 2px dashed #8c8f94; background: #f0f0f1; height: 50px; border-radius: 6px; margin-bottom: 10px; }
    CSS;

    wp_register_style( 'ckc-homepage-builder-admin', false, array(), '1.0' );
    wp_enqueue_style( 'ckc-homepage-builder-admin' );
    wp_add_inline_style( 'ckc-homepage-builder-admin', $css );

    $js = <<<'JS'
    jQuery(function($){
        var $list = $('#ckc-hb-module-list');

        function genId(){ return 'mod_' + Date.now().toString(36) + Math.random().toString(36).slice(2,8); }

        // 拖曳排序
        $list.sortable({
            handle: '.ckc-hb-drag-handle',
            placeholder: 'ckc-hb-placeholder',
            axis: 'y'
        });

        // 展開/收合
        $(document).on('click', '.ckc-hb-toggle-body', function(){
            $(this).closest('.ckc-hb-module').find('.ckc-hb-module-body').slideToggle(150);
        });

        // 刪除模塊
        $(document).on('click', '.ckc-hb-delete', function(){
            if (!confirm('確定要刪除這個區塊嗎？')) return;
            $(this).closest('.ckc-hb-module').remove();
        });

        // 複製模塊
        $(document).on('click', '.ckc-hb-duplicate', function(){
            var $orig = $(this).closest('.ckc-hb-module');
            var oldId = $orig.data('id');
            var newId = genId();
            var html = $orig.prop('outerHTML').split(oldId).join(newId);
            var $clone = $(html);
            $clone.find('.ckc-hb-module-body').show();
            $orig.after($clone);
        });

        // 新增模塊（從隱藏範本複製）
        $('#ckc-hb-add-btn').on('click', function(){
            var type = $('#ckc-hb-add-type').val();
            var $tpl = $('#ckc-hb-templates .ckc-hb-template[data-type="' + type + '"]');
            if (!$tpl.length) return;
            var newId = genId();
            var html = $tpl.html().split('__ID__').join(newId);
            var $node = $(html);
            $node.find('.ckc-hb-module-body').show();
            $list.append($node);
            $node[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        });

        // repeater：新增一筆（一律從隱藏的空白列範本複製，確保「目前一筆都沒有」時也能新增）
        // 範本裡的欄位原本是 disabled（確保範本本身絕對不會被表單提交），
        // 複製出來要變成「真正可用的一列」時，必須：
        // 1. 拿掉 disabled，這一列的內容才會在儲存時一併送出。
        // 2. 把欄位名稱裡的 __ROWIDX__ 換成一個目前頁面上絕對不會撞到的
        //    唯一索引，text／link／color 三個欄位才會共用同一個索引、
        //    儲存時才能正確組回同一列（而不是各自變成互不相干的破碎資料）。
        var ckcRepeaterRowSeq = 0;
        $(document).on('click', '.ckc-hb-repeater-add', function(){
            var $repeater = $(this).closest('.ckc-hb-repeater');
            var $rows = $repeater.find('.ckc-hb-repeater-rows');
            var $template = $repeater.find('.ckc-hb-repeater-row-template .ckc-hb-repeater-row').first();
            if (!$template.length) { return; }
            var uniqueIdx = 'n' + Date.now().toString(36) + (ckcRepeaterRowSeq++);
            var html = $template.prop('outerHTML').split('__ROWIDX__').join(uniqueIdx);
            var $newRow = $(html);
            $newRow.find('input').prop('disabled', false);
            $rows.append($newRow);
        });

        // repeater：刪除一筆
        $(document).on('click', '.ckc-hb-repeater-remove', function(){
            $(this).closest('.ckc-hb-repeater-row').remove();
        });

        // 圖片選擇器（wp.media）
        $(document).on('click', '.ckc-hb-image-pick', function(e){
            e.preventDefault();
            var $wrap = $(this).closest('.ckc-hb-image-field');
            var frame = wp.media({ title: '選擇圖片', multiple: false, library: { type: 'image' } });
            frame.on('select', function(){
                var att = frame.state().get('selection').first().toJSON();
                $wrap.find('.ckc-hb-image-url').val(att.url);
                $wrap.find('.ckc-hb-image-preview').attr('src', att.url).show();
            });
            frame.open();
        });
        $(document).on('click', '.ckc-hb-image-clear', function(e){
            e.preventDefault();
            var $wrap = $(this).closest('.ckc-hb-image-field');
            $wrap.find('.ckc-hb-image-url').val('');
            $wrap.find('.ckc-hb-image-preview').attr('src', '').hide();
        });
        // 圖片網址欄位手動輸入時同步預覽
        $(document).on('input', '.ckc-hb-image-url', function(){
            var url = $(this).val();
            var $img = $(this).closest('.ckc-hb-image-field').find('.ckc-hb-image-preview');
            if (url) { $img.attr('src', url).show(); } else { $img.hide(); }
        });
    });
    JS;

    wp_register_script( 'ckc-homepage-builder-admin', false, array( 'jquery', 'jquery-ui-sortable', 'media-editor' ), '1.0', true );
    wp_enqueue_script( 'ckc-homepage-builder-admin' );
    wp_add_inline_script( 'ckc-homepage-builder-admin', $js );
}
