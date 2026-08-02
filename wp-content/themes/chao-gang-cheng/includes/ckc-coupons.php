<?php
/**
 * CKC Coupons：CyberBiz 風格折扣券（前台領券中心＋後台券管理欄位）
 *
 *  - 啟用 WooCommerce 原生折扣碼（購物車／結帳出現輸入框）
 *  - 後台「行銷 > 折價券」編輯頁新增：顯示於領券中心、券面標題
 *  - 購物車頁領券中心：券卡片（面額、低消、效期）＋一鍵套用
 *  - 我的帳號「專屬優惠券」頁：可領券列表與已套用狀態
 *
 * 券的金額、類型、低消、效期、次數限制全部沿用 WooCommerce 原生設定。
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ---------------- 啟用原生折扣碼 ---------------- */
add_filter( 'woocommerce_coupons_enabled', '__return_true', 20 );

/* ================================================================
 * 每筆訂單限用一張優惠券（純 order hook，前後台一致）
 *
 * 統一在「訂單層級」保證單券，前台與後台共用同一核心函式 ckc_order_keep_single_coupon()：
 *   - 前台結帳：woocommerce_checkout_create_order（cart 券轉入 order 後、金額最終確定前）
 *   - 後台加券：woocommerce_order_applied_coupon（管理員手動建單／編輯訂單）
 *
 * 注意（此為刻意設計的取捨）：購物車頁在「送出結帳前」不再即時限制單券，
 * 消費者可能暫時看到多張券同時套用；直到建立訂單時才統一清成一張。
 * ================================================================ */

/**
 * 判斷一張券是否為「本站行銷優惠券」（領券中心／我的優惠券上架的券）。
 * 只有這類券才納入「每筆訂單限用一張優惠券」的單券限制；點數折抵（紅利折抵）、
 * 系統虛擬券等不算，可與優惠券並存、不會被單券邏輯移除。
 */
function ckc_is_marketing_coupon( $code ) {
    // 效能：同一 request 內以 static 快取結果，避免重複建立 WC_Coupon（每次都是 DB 查詢）。
    // 此函式會在 cart 載入、套券、結帳等多個 hook 中對同一批券碼反覆呼叫。
    static $cache = array();

    $code = wc_format_coupon_code( is_scalar( $code ) ? (string) $code : '' );
    if ( '' === $code ) {
        return false;
    }
    if ( isset( $cache[ $code ] ) ) {
        return $cache[ $code ];
    }

    // 排除點數折抵等系統虛擬券（不視為行銷優惠券，使其可與行銷優惠券同時使用）
    // WPS 產生的點數折抵券通常包含 wps_ 或 points_ 等關鍵字
    if ( false !== strpos( $code, 'wps' ) || false !== strpos( $code, 'points' ) ) {
        return $cache[ $code ] = false;
    }

    $coupon = new WC_Coupon( $code );
    if ( ! $coupon->get_id() ) {
        return $cache[ $code ] = false; // 虛擬券（如點數折抵）或券不存在 → 不視為行銷優惠券
    }
    // 修正：只要折價券在資料庫中存在（有 ID），且折扣類型為一般折扣型（非 shipping 等系統特殊類型），
    // 一律視為「行銷優惠券」納入單張限制，防止多張疊加導致折扣超額。
    // _ckc_coupon_public / _ckc_coupon_claim_public 不再是唯一判斷依據。
    $discount_types = array( 'fixed_cart', 'percent', 'fixed_product' );
    if ( in_array( $coupon->get_discount_type(), $discount_types, true ) ) {
        return $cache[ $code ] = true;
    }
    // 其餘特殊類型（如第三方插件自訂類型）維持以 meta 為判斷
    return $cache[ $code ] = ( 'yes' === $coupon->get_meta( '_ckc_coupon_public' )
        || 'yes' === $coupon->get_meta( '_ckc_coupon_claim_public' ) );
}

/**
 * 核心：確保一張 WC_Order 只保留一張「行銷優惠券」。
 * 點數折抵等系統券不受影響，可與優惠券並存。
 *
 * @param WC_Order|int   $order     訂單物件或 ID。
 * @param string|WC_Coupon $keep    指定要保留的券碼（後台加券時為剛加的那張）；
 *                                   留空則保留最後（最新）套用的一張行銷券。
 */
function ckc_order_keep_single_coupon( $order, $keep = '' ) {
    if ( ! is_a( $order, 'WC_Order' ) ) {
        $order = is_numeric( $order ) ? wc_get_order( $order ) : null;
    }
    if ( ! $order || ! method_exists( $order, 'get_coupon_codes' ) || ! method_exists( $order, 'remove_coupon' ) ) {
        return;
    }
    // 只針對「行銷優惠券」計數與移除，保留點數折抵等系統券
    $marketing = array_values( array_filter( $order->get_coupon_codes(), 'ckc_is_marketing_coupon' ) );
    if ( count( $marketing ) <= 1 ) {
        return;
    }
    if ( is_object( $keep ) && method_exists( $keep, 'get_code' ) ) {
        $keep = $keep->get_code();
    }
    $keep_code = ( $keep && ckc_is_marketing_coupon( $keep ) ) ? wc_format_coupon_code( $keep ) : wc_format_coupon_code( end( $marketing ) );
    foreach ( $marketing as $c ) {
        if ( wc_format_coupon_code( $c ) !== $keep_code ) {
            $order->remove_coupon( $c );
        }
    }
    $order->calculate_totals();
}

// 前台結帳：cart 的多張券轉入 order 後，於金額最終確定前清成一張
add_action( 'woocommerce_checkout_create_order', 'ckc_checkout_order_single_coupon', 20, 2 );
function ckc_checkout_order_single_coupon( $order, $data ) {
    ckc_order_keep_single_coupon( $order ); // 保留最後套用的一張
}

// 後台：管理員手動建單／編輯訂單加券時，保留剛加的那張
add_action( 'woocommerce_order_applied_coupon', 'ckc_order_applied_single_coupon', 20, 2 );
function ckc_order_applied_single_coupon( $order = null, $applied = '' ) {
    ckc_order_keep_single_coupon( $order, $applied );
}

/* ---------------- 前台：購物車／結帳階段即時限用一張券 ----------------
 * 讓消費者在「送出結帳前」購物車與結帳頁就只有一張券——套用任一張券時
 * （來自領券中心、帳號頁或原生折扣碼輸入框）自動移除其他已套用的券，只留
 * 最新這一張。remove_coupon 觸發的是 removed_coupon（非 applied），無遞迴。
 */
add_action( 'woocommerce_applied_coupon', 'ckc_cart_single_coupon', 20, 1 );
function ckc_cart_single_coupon( $applied_code ) {
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        return;
    }
    $applied_code = wc_format_coupon_code( $applied_code );
    // 只在「新套用的是行銷優惠券」時才啟動單券限制；點數折抵等系統券套用時不動作
    if ( ! ckc_is_marketing_coupon( $applied_code ) ) {
        return;
    }
    $removed = false;
    foreach ( WC()->cart->get_applied_coupons() as $code ) {
        // 只移除「其他行銷優惠券」，保留點數折抵等系統券可並存
        if ( wc_format_coupon_code( $code ) !== $applied_code && ckc_is_marketing_coupon( $code ) ) {
            WC()->cart->remove_coupon( $code );
            $removed = true;
        }
    }
    if ( $removed && function_exists( 'wc_add_notice' ) ) {
        wc_add_notice( '每筆訂單限用一張優惠券，已為您改套用最新選擇的優惠券。', 'notice' );
    }
}

/* 清理購物車 session 中殘留的多張「行銷優惠券」（單券規則上線前套入的舊資料），只留最新一張；點數折抵券不動 */
add_action( 'woocommerce_cart_loaded_from_session', 'ckc_cart_purge_extra_coupons', 20 );
function ckc_cart_purge_extra_coupons( $cart ) {
    if ( ! $cart || ! is_a( $cart, 'WC_Cart' ) ) {
        return;
    }
    $marketing = array_values( array_filter( $cart->get_applied_coupons(), 'ckc_is_marketing_coupon' ) );
    if ( count( $marketing ) <= 1 ) {
        return;
    }
    $keep = end( $marketing );
    foreach ( $marketing as $code ) {
        if ( $code !== $keep ) {
            $cart->remove_coupon( $code );
        }
    }
}
add_filter( 'option_woocommerce_enable_coupons', 'ckc_force_enable_coupons_option', 20 );
add_filter( 'pre_option_woocommerce_enable_coupons', 'ckc_force_enable_coupons_option', 20 );
function ckc_force_enable_coupons_option( $value ) {
    return 'yes';
}

add_action( 'admin_init', 'ckc_ensure_coupons_enabled_in_db' );
function ckc_ensure_coupons_enabled_in_db() {
    if ( get_option( 'woocommerce_enable_coupons' ) !== 'yes' ) {
        update_option( 'woocommerce_enable_coupons', 'yes' );
    }
}

/* ---------------- 一次性示範折價券種子（執行後自動停用） ---------------- */
add_action( 'wp_loaded', 'ckc_seed_demo_coupons', 20 );
function ckc_seed_demo_coupons() {
    // 已執行過就跳過
    if ( get_option( 'ckc_demo_coupons_seeded' ) ) {
        return;
    }
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    $base_img = get_template_directory_uri() . '/assets/images/';

    $demo = array(
        array(
            'code'     => 'NEWMEMBER100',
            'amount'   => '100',
            'type'     => 'fixed_cart',
            'min'      => '500',
            'per_user' => '1',
            'limit'    => '200',
            'label'    => '新會員見面禮 NT$100',
            'cat'      => '新會員專區',
            'deadline' => '2026-12-31',
            'stock'    => '200',
            'image'    => $base_img . 'coupon-newmember.jpg',
            'desc'     => "歡迎加入潮港城！首次消費享 NT\$100 折扣，適用於全館所有商品。",
            'notes'    => "1. 本券每人限領一次\n2. 最低消費 NT\$500 以上方可使用\n3. 不得與其他折扣同時使用\n4. 效期至 2026/12/31",
        ),
        array(
            'code'     => 'SUMMER200',
            'amount'   => '200',
            'type'     => 'fixed_cart',
            'min'      => '1000',
            'per_user' => '1',
            'limit'    => '100',
            'label'    => '夏日限時 NT$200 折',
            'cat'      => '限時特惠',
            'deadline' => '2026-09-30',
            'stock'    => '100',
            'image'    => $base_img . 'coupon-summer.jpg',
            'desc'     => "夏日限定！消費滿 NT\$1,000 即享 NT\$200 折扣，限量 100 張，先搶先贏！",
            'notes'    => "1. 限量 100 張，先搶先贏\n2. 每人限領一次\n3. 消費滿 NT\$1,000 以上方可使用\n4. 活動至 2026/09/30 止",
        ),
        array(
            'code'     => 'FREESHIP',
            'amount'   => '60',
            'type'     => 'fixed_cart',
            'min'      => '500',
            'per_user' => '2',
            'limit'    => '',
            'label'    => '運費折抵 NT$60',
            'cat'      => '運費優惠',
            'deadline' => '',
            'stock'    => '',
            'image'    => $base_img . 'coupon-freeship.jpg',
            'desc'     => "每筆訂單消費滿 NT\$500 即可領取運費折抵 60 元，每人最多領取 2 張！",
            'notes'    => "1. 每人最多領取 2 張\n2. 消費滿 NT\$500 以上方可使用\n3. 無使用期限",
        ),
    );

    foreach ( $demo as $c ) {
        $existing_id = wc_get_coupon_id_by_code( $c['code'] );
        if ( $existing_id ) {
            $coupon_id = $existing_id;
        } else {
            $coupon_id = wp_insert_post( array(
                'post_title'   => $c['code'],
                'post_name'    => strtolower( $c['code'] ),
                'post_content' => '',
                'post_status'  => 'publish',
                'post_type'    => 'shop_coupon',
            ) );
            if ( is_wp_error( $coupon_id ) ) {
                continue;
            }
        }
        // WooCommerce 原生欄位
        update_post_meta( $coupon_id, 'discount_type',        $c['type'] );
        update_post_meta( $coupon_id, 'coupon_amount',        $c['amount'] );
        update_post_meta( $coupon_id, 'minimum_amount',       $c['min'] );
        update_post_meta( $coupon_id, 'usage_limit',          $c['limit'] );
        update_post_meta( $coupon_id, 'usage_limit_per_user', $c['per_user'] );
        // CKC 購物車顯示
        update_post_meta( $coupon_id, '_ckc_coupon_public',   'yes' );
        update_post_meta( $coupon_id, '_ckc_coupon_label',    $c['label'] );
        // CKC 領券中心欄位
        update_post_meta( $coupon_id, '_ckc_coupon_claim_public',      'yes' );
        update_post_meta( $coupon_id, '_ckc_coupon_claim_inventory',   $c['stock'] );
        update_post_meta( $coupon_id, '_ckc_coupon_claim_count',       '0' );
        update_post_meta( $coupon_id, '_ckc_coupon_claim_category',    $c['cat'] );
        update_post_meta( $coupon_id, '_ckc_coupon_claim_deadline',    $c['deadline'] );
        update_post_meta( $coupon_id, '_ckc_coupon_claim_description', $c['desc'] );
        update_post_meta( $coupon_id, '_ckc_coupon_claim_notes',       $c['notes'] );
        update_post_meta( $coupon_id, '_ckc_coupon_claim_image',       $c['image'] );
        update_post_meta( $coupon_id, '_ckc_coupon_claim_banner',      '' );
    }

    // 清除相關快取
    delete_transient( 'ckc_coupon_page_checked' );

    // 標記已執行，不再重複
    update_option( 'ckc_demo_coupons_seeded', '1' );
}

/* ---------------- 已種子折價券圖片補丁（若已 seeded 但圖片為空時補齊）---------------- */
add_action( 'wp_loaded', 'ckc_patch_demo_coupon_images', 21 );
function ckc_patch_demo_coupon_images() {
    // 只在 seeded 旗標存在但圖片尚未設定時執行
    if ( ! get_option( 'ckc_demo_coupons_seeded' ) ) {
        return;
    }
    if ( get_option( 'ckc_demo_coupon_images_patched' ) ) {
        return;
    }
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }
    $base_img = get_template_directory_uri() . '/assets/images/';
    $patch = array(
        'NEWMEMBER100' => $base_img . 'coupon-newmember.jpg',
        'SUMMER200'    => $base_img . 'coupon-summer.jpg',
        'FREESHIP'     => $base_img . 'coupon-freeship.jpg',
    );
    foreach ( $patch as $code => $img_url ) {
        $coupon_id = wc_get_coupon_id_by_code( $code );
        if ( $coupon_id ) {
            $current = get_post_meta( $coupon_id, '_ckc_coupon_claim_image', true );
            if ( empty( $current ) ) {
                update_post_meta( $coupon_id, '_ckc_coupon_claim_image', $img_url );
            }
        }
    }
    update_option( 'ckc_demo_coupon_images_patched', '1' );
}


/* ---------------- 後台：一般 tab 追加欄位（僅限購物車領券面板相關）---------------- */
add_action( 'woocommerce_coupon_options', 'ckc_coupon_admin_fields', 20, 2 );
function ckc_coupon_admin_fields( $coupon_id, $coupon ) {
    // 僅保留「購物車領券小面板 / 會員帳號頁」的顯示開關
    // 說明：此欄位控制是否出現在購物車頁右側的「領券」小彈出面板，
    //        與「領券中心設定」tab 的「啟用領取中心上架」是不同功能。
    woocommerce_wp_checkbox( array(
        'id'          => '_ckc_coupon_public',
        'label'       => '顯示於購物車領券面板',
        'description' => '勾選後，此券會出現在<strong>購物車頁面</strong>右側「領券」彈窗與會員帳號「專屬優惠券」頁，消費者可一鍵套用。（與下方「領券中心設定」為不同入口）',
    ) );
    // 注意：「券面標題」已移至「領券中心設定」頁籤管理，在這裡不再重複顯示。
}

add_action( 'woocommerce_coupon_options_save', 'ckc_coupon_admin_fields_save', 20, 2 );
function ckc_coupon_admin_fields_save( $coupon_id, $coupon ) {
    // 儲存既有的前台領券與券面標題欄位
    $public = isset( $_POST['_ckc_coupon_public'] ) ? 'yes' : 'no';
    $label  = isset( $_POST['_ckc_coupon_label'] ) ? sanitize_text_field( wp_unslash( $_POST['_ckc_coupon_label'] ) ) : '';
    
    update_post_meta( $coupon_id, '_ckc_coupon_public', $public );
    update_post_meta( $coupon_id, '_ckc_coupon_label', $label );

    // 儲存新版領券中心自訂欄位
    $claim_public = isset( $_POST['_ckc_coupon_claim_public'] ) ? 'yes' : 'no';
    $inventory    = isset( $_POST['_ckc_coupon_claim_inventory'] ) && $_POST['_ckc_coupon_claim_inventory'] !== '' ? intval( $_POST['_ckc_coupon_claim_inventory'] ) : '';
    $claim_count  = isset( $_POST['_ckc_coupon_claim_count'] ) ? intval( $_POST['_ckc_coupon_claim_count'] ) : 0;
    $category     = isset( $_POST['_ckc_coupon_claim_category'] ) ? sanitize_text_field( wp_unslash( $_POST['_ckc_coupon_claim_category'] ) ) : '';
    $image_url    = isset( $_POST['_ckc_coupon_claim_image'] ) ? esc_url_raw( wp_unslash( $_POST['_ckc_coupon_claim_image'] ) ) : '';
    $banner_url   = isset( $_POST['_ckc_coupon_claim_banner'] ) ? esc_url_raw( wp_unslash( $_POST['_ckc_coupon_claim_banner'] ) ) : '';
    $description  = isset( $_POST['_ckc_coupon_claim_description'] ) ? wp_kses_post( wp_unslash( $_POST['_ckc_coupon_claim_description'] ) ) : '';
    $notes        = isset( $_POST['_ckc_coupon_claim_notes'] ) ? wp_kses_post( wp_unslash( $_POST['_ckc_coupon_claim_notes'] ) ) : '';

    update_post_meta( $coupon_id, '_ckc_coupon_claim_public', $claim_public );
    update_post_meta( $coupon_id, '_ckc_coupon_claim_inventory', $inventory );
    update_post_meta( $coupon_id, '_ckc_coupon_claim_count', $claim_count );
    update_post_meta( $coupon_id, '_ckc_coupon_claim_category', $category );
    update_post_meta( $coupon_id, '_ckc_coupon_claim_image', $image_url );
    update_post_meta( $coupon_id, '_ckc_coupon_claim_banner', $banner_url );
    update_post_meta( $coupon_id, '_ckc_coupon_claim_description', $description );
    update_post_meta( $coupon_id, '_ckc_coupon_claim_notes', $notes );
}


/* ---------------- 後台：領券中心專屬頁籤 ---------------- */
add_filter( 'woocommerce_coupon_data_tabs', 'ckc_add_coupon_claim_center_tab', 25 );
function ckc_add_coupon_claim_center_tab( $tabs ) {
    $tabs['ckc_claim_center'] = array(
        'label'  => '領券中心設定',
        'target' => 'ckc_claim_center_coupon_data',
        'class'  => 'ckc_claim_center_tab',
    );
    return $tabs;
}

add_action( 'woocommerce_coupon_data_panels', 'ckc_add_coupon_claim_center_panel', 25, 2 );
function ckc_add_coupon_claim_center_panel( $coupon_id, $coupon ) {
    // 讀取所有自訂 meta
    $claim_public_val = get_post_meta( $coupon_id, '_ckc_coupon_claim_public', true );
    $label_val        = get_post_meta( $coupon_id, '_ckc_coupon_label', true );
    $inventory_val    = get_post_meta( $coupon_id, '_ckc_coupon_claim_inventory', true );
    $claim_count      = get_post_meta( $coupon_id, '_ckc_coupon_claim_count', true );
    $category_val     = get_post_meta( $coupon_id, '_ckc_coupon_claim_category', true );
    $image_url        = get_post_meta( $coupon_id, '_ckc_coupon_claim_image', true );
    $banner_url       = get_post_meta( $coupon_id, '_ckc_coupon_claim_banner', true );
    $description_val  = get_post_meta( $coupon_id, '_ckc_coupon_claim_description', true );
    $notes_val        = get_post_meta( $coupon_id, '_ckc_coupon_claim_notes', true );
    $wc_expires       = $coupon->get_date_expires();
    $wc_expires_str   = $wc_expires ? $wc_expires->date( 'Y-m-d' ) : '';

    // 取得所有現有的活動類別（供 datalist 建議）
    $existing_categories = array();
    $cat_posts = get_posts( array(
        'post_type'      => 'shop_coupon',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ) );
    foreach ( $cat_posts as $pid ) {
        $cat = get_post_meta( $pid, '_ckc_coupon_claim_category', true );
        if ( ! empty( $cat ) ) {
            $existing_categories[] = $cat;
        }
    }
    $existing_categories = array_unique( $existing_categories );
    ?>
    <div id="ckc_claim_center_coupon_data" class="panel woocommerce_options_panel">

        <?php /* ── 前台對照說明 ── */ ?>
        <div class="options_group" style="background:#f0f7ff;border-left:4px solid #2196f3;margin:12px;padding:10px 14px;border-radius:0 6px 6px 0;">
            <p style="margin:0 0 6px;font-weight:700;color:#1565c0;font-size:13px;">📋 前台欄位對照說明</p>
            <table style="font-size:12px;color:#1e293b;border-collapse:collapse;width:100%;">
                <tr><td style="padding:2px 8px;font-weight:600;white-space:nowrap;">啟用領取中心上架</td><td>→ 是否在前台「領券中心」頁顯示此折價券</td></tr>
                <tr><td style="padding:2px 8px;font-weight:600;white-space:nowrap;">券面標題</td><td>→ 前台卡片 <strong>大標題</strong>（如「新會員見面禮 NT$100」）</td></tr>
                <tr><td style="padding:2px 8px;font-weight:600;white-space:nowrap;">活動分類</td><td>→ 前台頁面頂端 <strong>篩選 Tab 標籤</strong>（如「新會員專區」）</td></tr>
                <tr><td style="padding:2px 8px;font-weight:600;white-space:nowrap;">列表小縮圖</td><td>→ 卡片左側 <strong>方形 ICON 圖</strong></td></tr>
                <tr><td style="padding:2px 8px;font-weight:600;white-space:nowrap;">活動說明</td><td>→ 彈窗「使用規則」中的 <strong>活動說明</strong> 文字區塊</td></tr>
                <tr><td style="padding:2px 8px;font-weight:600;white-space:nowrap;">使用限制與注意事項</td><td>→ 彈窗中的 <strong>注意事項</strong> 條列清單（每行一條）</td></tr>
                <tr><td style="padding:2px 8px;font-weight:600;white-space:nowrap;">領取限額</td><td>→ 卡片 <strong>領取進度條</strong>（已領 N / 限額 N 張）</td></tr>
            </table>
        </div>

        <?php /* ══ 群組 A：上架設定 ══ */ ?>
        <div class="options_group">
            <p class="form-field" style="margin:8px 0 4px 162px;font-weight:700;color:#374151;font-size:12px;text-transform:uppercase;letter-spacing:.05em;">▌ 上架設定</p>
            <?php
            // 1. 啟用領券中心上架
            woocommerce_wp_checkbox( array(
                'id'          => '_ckc_coupon_claim_public',
                'label'       => '啟用領取中心上架',
                'value'       => $claim_public_val,
                'cbvalue'     => 'yes',
                'description' => '勾選後，此折價券將上架至「折價券領取中心」供會員公開領取',
            ) );

            // 2. 券面標題
            woocommerce_wp_text_input( array(
                'id'          => '_ckc_coupon_label',
                'label'       => '券面標題',
                'value'       => $label_val,
                'placeholder' => '例如：新會員見面禮 NT$100',
                'description' => '前台卡片大標題，留空則自動產生（如「折 NT$200」）',
                'desc_tip'    => true,
            ) );
            ?>

            <?php /* ── 活動分類（支援自訂 + datalist 建議）── */ ?>
            <p class="form-field _ckc_coupon_claim_category_field">
                <label for="_ckc_coupon_claim_category">
                    活動分類
                    <?php echo wc_help_tip( '前台頁面頂部的篩選 Tab 標籤。相同分類名稱的折價券會合併成一個 Tab。' ); ?>
                </label>
                <input
                    type="text"
                    name="_ckc_coupon_claim_category"
                    id="_ckc_coupon_claim_category"
                    value="<?php echo esc_attr( $category_val ); ?>"
                    list="ckc_category_datalist"
                    placeholder="例如：新會員專區、限時特惠、運費優惠"
                    style="width:60%;"
                />
                <datalist id="ckc_category_datalist">
                    <?php foreach ( $existing_categories as $cat ) : ?>
                        <option value="<?php echo esc_attr( $cat ); ?>">
                    <?php endforeach; ?>
                    <option value="新會員專區">
                    <option value="限時特惠">
                    <option value="運費優惠">
                    <option value="會員獨享">
                    <option value="節慶優惠">
                </datalist>
                <span class="description" style="display:block;color:#64748b;font-size:11px;margin-top:2px;">
                    與其他折價券填入<strong>相同分類名稱</strong>，前台即合併成同一個 Tab 篩選
                </span>
            </p>
        </div>

        <?php /* ══ 群組 B：庫存設定 ══ */ ?>
        <div class="options_group">
            <p class="form-field" style="margin:8px 0 4px 162px;font-weight:700;color:#374151;font-size:12px;text-transform:uppercase;letter-spacing:.05em;">▌ 庫存設定</p>
            <?php
            // 3. 領取限額/總庫存
            woocommerce_wp_text_input( array(
                'id'          => '_ckc_coupon_claim_inventory',
                'label'       => '領取限額 (總庫存)',
                'value'       => $inventory_val,
                'placeholder' => '無限制請留空',
                'type'        => 'number',
                'description' => '達到上限後前台顯示「已搶光」，留空表示無限制',
                'desc_tip'    => true,
            ) );

            // 4. 已領取次數（系統統計）
            woocommerce_wp_text_input( array(
                'id'          => '_ckc_coupon_claim_count',
                'label'       => '已領取次數',
                'value'       => $claim_count !== '' ? intval( $claim_count ) : 0,
                'type'        => 'number',
                'description' => '系統自動累計，可手動修正（歸零重置等）',
                'desc_tip'    => true,
            ) );
            ?>
        </div>

        <?php /* ══ 群組 C：圖片設定 ══ */ ?>
        <div class="options_group">
            <p class="form-field" style="margin:8px 0 4px 162px;font-weight:700;color:#374151;font-size:12px;text-transform:uppercase;letter-spacing:.05em;">▌ 圖片設定</p>

            <?php /* 列表小縮圖（帶即時預覽）*/ ?>
            <p class="form-field _ckc_coupon_claim_image_field">
                <label for="_ckc_coupon_claim_image">
                    列表小縮圖
                    <?php echo wc_help_tip( '前台卡片左側的方形 ICON 圖（建議 400×400px）' ); ?>
                </label>
                <input type="text" name="_ckc_coupon_claim_image" id="_ckc_coupon_claim_image"
                       value="<?php echo esc_attr( $image_url ); ?>"
                       style="width:50%;" placeholder="請上傳或輸入圖片網址" />
                <button type="button" class="button ckc_upload_image_btn" data-target="_ckc_coupon_claim_image">上傳/選擇圖片</button>
                <?php if ( ! empty( $image_url ) ) : ?>
                    <br><img src="<?php echo esc_url( $image_url ); ?>" style="margin-top:4px;max-width:80px;max-height:80px;border-radius:8px;border:1px solid #e2e8f0;" alt="縮圖預覽">
                <?php endif; ?>
            </p>

            <?php /* 詳情頁 Banner（帶即時預覽）*/ ?>
            <p class="form-field _ckc_coupon_claim_banner_field">
                <label for="_ckc_coupon_claim_banner">
                    詳情頁大 Banner
                    <?php echo wc_help_tip( '點擊「使用規則」後彈出視窗上方的大橫幅圖（建議 800×300px）' ); ?>
                </label>
                <input type="text" name="_ckc_coupon_claim_banner" id="_ckc_coupon_claim_banner"
                       value="<?php echo esc_attr( $banner_url ); ?>"
                       style="width:50%;" placeholder="請上傳或輸入圖片網址" />
                <button type="button" class="button ckc_upload_image_btn" data-target="_ckc_coupon_claim_banner">上傳/選擇圖片</button>
                <?php if ( ! empty( $banner_url ) ) : ?>
                    <br><img src="<?php echo esc_url( $banner_url ); ?>" style="margin-top:4px;max-width:200px;max-height:80px;border-radius:6px;border:1px solid #e2e8f0;object-fit:cover;" alt="Banner 預覽">
                <?php endif; ?>
            </p>
        </div>

        <?php /* ══ 群組 D：活動說明與注意事項 ══ */ ?>
        <div class="options_group">
            <p class="form-field" style="margin:8px 0 4px 162px;font-weight:700;color:#374151;font-size:12px;text-transform:uppercase;letter-spacing:.05em;">▌ 活動說明與注意事項（對應「使用規則」彈窗）</p>
            <?php
            // 6. 活動說明
            woocommerce_wp_textarea_input( array(
                'id'          => '_ckc_coupon_claim_description',
                'label'       => '活動說明',
                'value'       => $description_val,
                'placeholder' => "例如：\n歡迎加入潮港城！首次消費享 NT\$100 折扣，適用於全館所有商品。",
                'description' => '前台「使用規則」彈窗中「活動說明」段落的內容',
                'desc_tip'    => true,
                'rows'        => 4,
                'style'       => 'height:100px;resize:vertical;',
            ) );

            // 7. 使用限制與注意事項（每行一條）
            woocommerce_wp_textarea_input( array(
                'id'          => '_ckc_coupon_claim_notes',
                'label'       => '使用限制與注意事項',
                'value'       => $notes_val,          // ← 修復：加入 value 參數
                'placeholder' => "每行輸入一條，例如：\n1. 本券每人限領一次\n2. 最低消費 NT\$500 以上方可使用\n3. 不得與其他折扣同時使用",
                'description' => '前台「使用規則」彈窗中「注意事項」條列清單，每行自動成一條',
                'desc_tip'    => true,
                'rows'        => 6,
                'style'       => 'height:140px;resize:vertical;',
            ) );
            ?>
            <p style="margin:0 0 8px 162px;font-size:11px;color:#94a3b8;">💡 「使用限制與注意事項」每行自動轉換為一條條列，行首有無編號皆可。</p>
        </div>
    </div>
    <?php
}

/* ---------------- 後台：載入 WordPress 媒體庫上傳與頁籤記憶 JS ---------------- */
add_action( 'admin_enqueue_scripts', 'ckc_coupon_admin_media_scripts' );
function ckc_coupon_admin_media_scripts( $hook ) {
    global $post_type;
    if ( 'shop_coupon' === $post_type && ( 'post.php' === $hook || 'post-new.php' === $hook ) ) {
        wp_enqueue_media();
        $js = <<<'JS'
            jQuery(document).ready(function($) {
                // 1. 媒體庫上傳按鈕
                $('body').on('click', '.ckc_upload_image_btn', function(e) {
                    e.preventDefault();
                    var button = $(this);
                    var targetId = button.data('target');
                    var inputField = $('#' + targetId);
                    
                    var file_frame = wp.media.frames.file_frame = wp.media({
                        title: '選擇折價券圖片',
                        button: {
                            text: '使用此圖片'
                        },
                        multiple: false
                    });
                    
                    file_frame.on('select', function() {
                        var attachment = file_frame.state().get('selection').first().toJSON();
                        inputField.val(attachment.url);
                    });
                    
                    file_frame.open();
                });

                // 2. 記憶點選的頁籤 (避免存檔後重設為第一頁籤)
                $('.coupon_data_tabs').on('click', 'a', function() {
                    var activeTab = $(this).attr('href');
                    if (activeTab) {
                        localStorage.setItem('wc_coupon_active_tab', activeTab);
                    }
                });

                // 載入時還原點選的頁籤
                var savedTab = localStorage.getItem('wc_coupon_active_tab');
                if (savedTab && $('.coupon_data_tabs a[href="' + savedTab + '"]').length) {
                    setTimeout(function() {
                        $('.coupon_data_tabs a[href="' + savedTab + '"]').click();
                    }, 50);
                }
            });
JS;
        wp_add_inline_script( 'jquery', $js );
    }
}

/* ---------------- 公開券查詢 ---------------- */
function ckc_get_public_coupons() {
    $posts = get_posts( array(
        'post_type'   => 'shop_coupon',
        'post_status' => 'publish',
        'numberposts' => 20,
        'meta_key'    => '_ckc_coupon_public',
        'meta_value'  => 'yes',
        'orderby'     => 'date',
        'order'       => 'DESC',
    ) );
    $coupons = array();
    foreach ( $posts as $post ) {
        $coupon = new WC_Coupon( $post->post_title );
        if ( ! $coupon->get_id() ) {
            continue;
        }
        // 過期或總次數用罄者不顯示
        $expires = $coupon->get_date_expires();
        if ( $expires && $expires->getTimestamp() < time() ) {
            continue;
        }
        $limit = $coupon->get_usage_limit();
        if ( $limit && $coupon->get_usage_count() >= $limit ) {
            continue;
        }
        $coupons[] = $coupon;
    }
    return $coupons;
}

/* ---------------- 領取中心公開券查詢 ---------------- */
function ckc_get_claimable_coupons() {
    $posts = get_posts( array(
        'post_type'      => 'shop_coupon',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'   => '_ckc_coupon_claim_public',
                'value' => 'yes',
            ),
        ),
        'orderby'        => 'date',
        'order'          => 'DESC',
    ) );
    $coupons = array();
    $today   = current_time( 'Y-m-d' );
    foreach ( $posts as $post ) {
        $coupon = new WC_Coupon( $post->ID );
        if ( ! $coupon->get_id() ) {
            continue;
        }
        // 1. 過濾 WooCommerce 原生到期日
        $wc_expires = $coupon->get_date_expires();
        if ( $wc_expires && $wc_expires->getTimestamp() < time() ) {
            continue;
        }
        // 2. 過濾自訂領取截止期限
        $deadline = get_post_meta( $post->ID, '_ckc_coupon_claim_deadline', true );
        if ( $deadline && strtotime( $deadline ) < strtotime( $today ) ) {
            continue; // 已過領取期限不顯示
        }
        $coupons[] = $coupon;
    }
    return $coupons;
}

// 券值文字（例：92 折／折 NT$100／免運費）
function ckc_coupon_value_text( $coupon ) {
    $amount = floatval( $coupon->get_amount() );
    $type   = $coupon->get_discount_type();
    $parts  = array();
    if ( 'percent' === $type && $amount > 0 ) {
        $off = 100 - $amount;
        $parts[] = ( 0 === (int) ( $off % 10 ) && $off > 0 ) ? ( ( $off / 10 ) . ' 折' ) : ( rtrim( rtrim( number_format( $off / 10, 1 ), '0' ), '.' ) . ' 折' );
    } elseif ( $amount > 0 ) {
        $parts[] = '折 NT$' . number_format( $amount );
    }
    if ( $coupon->get_free_shipping() ) {
        $parts[] = '免運費';
    }
    return $parts ? implode( '＋', $parts ) : '優惠券';
}

/* ---------------- 一鍵套用（?ckc_apply_coupon=CODE&ckc_redirect=checkout） ---------------- */
add_action( 'template_redirect', 'ckc_coupon_apply_from_url', 20 );
function ckc_coupon_apply_from_url() {
    if ( empty( $_GET['ckc_apply_coupon'] ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
        return;
    }
    $code   = wc_format_coupon_code( sanitize_text_field( wp_unslash( $_GET['ckc_apply_coupon'] ) ) );
    $coupon = new WC_Coupon( $code );

    // 套用後跳回來源頁（購物車 or 結帳頁），由 ckc_redirect 參數決定
    $redirect = wc_get_cart_url();
    if ( isset( $_GET['ckc_redirect'] ) && 'checkout' === sanitize_key( $_GET['ckc_redirect'] ) ) {
        $redirect = wc_get_checkout_url();
    }

    // 允許套用「購物車領券」或是「領券中心」的公開券，避免暴力猜碼
    $is_public = 'yes' === $coupon->get_meta( '_ckc_coupon_public' ) || 'yes' === $coupon->get_meta( '_ckc_coupon_claim_public' );
    if ( ! $coupon->get_id() || ! $is_public ) {
        wp_safe_redirect( $redirect );
        exit;
    }
    if ( ! WC()->cart->has_discount( $code ) ) {
        WC()->cart->apply_coupon( $code );
    }
    wp_safe_redirect( $redirect );
    exit;
}

/**
 * 統一取得「會員已領取且有效」的折價券。
 * 供領券中心的「我的券匣」徽章數與會員「專屬優惠券」頁共用同一份清單，確保兩處數量一致。
 * 有效 = 已領取 ID 中，券存在、已發佈（publish）、且有券碼（排除已刪除／草稿／空碼殭屍券）。
 */
function ckc_get_user_claimed_coupons( $user_id = 0 ) {
    $user_id = $user_id ? intval( $user_id ) : get_current_user_id();
    if ( ! $user_id ) {
        return array();
    }
    $claimed_ids = (array) get_user_meta( $user_id, '_ckc_claimed_coupons', true );
    $claimed_ids = array_values( array_unique( array_filter( array_map( 'intval', $claimed_ids ) ) ) );
    $coupons = array();
    foreach ( $claimed_ids as $cid ) {
        $coupon = new WC_Coupon( $cid );
        if ( $coupon->get_id() && 'publish' === get_post_status( $cid ) && '' !== $coupon->get_code() ) {
            $coupons[] = $coupon;
        }
    }
    return $coupons;
}

/* ---------------- 券卡片渲染（購物車與帳號頁共用） ---------------- */
function ckc_render_coupon_cards( $context = 'cart', $coupons_override = null ) {

    if ( 'account' === $context ) {
        // ── 專屬優惠券頁：顯示「此會員已領取且有效」的券（與券匣徽章同一來源，數量一致）──
        if ( ! is_user_logged_in() ) {
            echo '<p style="color:#64748b;font-size:14px;">請先登入以查看您的專屬優惠券。</p>';
            return;
        }
        $coupons = ckc_get_user_claimed_coupons( get_current_user_id() );

        if ( empty( $coupons ) ) {
            echo '<p style="color:#64748b;font-size:14px;">目前沒有可用的優惠券，前往<a href="' . esc_url( home_url( '/領券中心/' ) ) . '" style="color:#d97706;font-weight:700;">領券中心</a>領取吧！</p>';
            return;
        }
    } else {
        // ── 購物車頁 / 結帳頁：使用外部傳入的已過濾清單
        if ( is_array( $coupons_override ) ) {
            $coupons = $coupons_override;
        } else {
            $coupons = ckc_get_public_coupons();
        }
        if ( empty( $coupons ) ) {
            return;
        }
    }

    $in_cart_page    = function_exists( 'is_cart' ) && is_cart();
    $in_checkout_page = function_exists( 'is_checkout' ) && is_checkout();
    // 結帳頁套用後跳回結帳頁；購物車頁套用後跳回購物車
    $base_apply_url = ( 'checkout' === $context )
        ? add_query_arg( 'ckc_redirect', 'checkout', wc_get_cart_url() )
        : wc_get_cart_url();
    ?>
    <div class="ckc-coupon-grid">
        <?php foreach ( $coupons as $coupon ) :
            $code    = $coupon->get_code();
            // 跳過已刪除／無券碼的殭屍券（領取紀錄殘留，get_code() 為空），
            // 否則 has_discount('') 會退化成「購物車是否有任何券」而誤判已套用。
            if ( '' === $code ) {
                continue;
            }
            $label   = $coupon->get_meta( '_ckc_coupon_label' );
            $value   = ckc_coupon_value_text( $coupon );
            $min     = floatval( $coupon->get_minimum_amount() );
            $expires = $coupon->get_date_expires();
            $applied = ( WC()->cart && '' !== $code ) ? WC()->cart->has_discount( $code ) : false;
            $apply_url = add_query_arg( 'ckc_apply_coupon', rawurlencode( $code ), $base_apply_url );
            // 判斷是否過期
            $is_expired = $expires && $expires->getTimestamp() < time();
            ?>
            <div class="ckc-coupon-card<?php echo $applied ? ' is-applied' : ( $is_expired ? ' is-expired' : '' ); ?>" data-code="<?php echo esc_attr( $code ); ?>" data-apply-url="<?php echo esc_url( $apply_url ); ?>">
                <div class="ckc-coupon-left">
                    <div class="ckc-coupon-value"><?php echo esc_html( $value ); ?></div>
                    <?php if ( $min > 0 ) : ?>
                        <div class="ckc-coupon-min">低消 NT$<?php echo esc_html( number_format( $min ) ); ?></div>
                    <?php endif; ?>
                </div>
                <div class="ckc-coupon-body">
                    <div class="ckc-coupon-title"><?php echo esc_html( $label ? $label : $value ); ?></div>
                    <div class="ckc-coupon-meta">
                        代碼 <code><?php echo esc_html( strtoupper( $code ) ); ?></code>
                        <?php if ( $expires ) : ?>
                            ・<?php echo esc_html( $expires->date_i18n( 'Y/m/d' ) ); ?> 前有效
                        <?php endif; ?>
                        <?php if ( $is_expired ) : ?>
                            <span style="color:#ef4444;font-weight:700;"> ・已過期</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="ckc-coupon-action">
                    <?php if ( $applied ) : ?>
                        <span class="ckc-coupon-applied">✓ 已套用</span>
                    <?php elseif ( $is_expired ) : ?>
                        <span style="color:#94a3b8;font-size:12px;white-space:nowrap;">已過期</span>
                    <?php else : ?>
                        <a href="<?php echo esc_url( $apply_url ); ?>" class="ckc-coupon-apply" data-coupon-code="<?php echo esc_attr( $code ); ?>"><?php echo ( $in_cart_page ) ? '立即套用' : '套用去結帳'; ?></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <style>
    .ckc-coupon-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 12px; margin: 14px 0; }
    .ckc-coupon-card { display: flex; align-items: center; gap: 12px; background: #fffaf1; border: 1px dashed #c9974a; border-radius: 10px; padding: 12px 14px; }
    .ckc-coupon-card.is-applied { border-style: solid; border-color: #16a34a; background: #f0fdf4; }
    .ckc-coupon-card.is-expired { opacity: 0.55; border-color: #e2d2b3; }
    .ckc-coupon-left { text-align: center; min-width: 76px; border-right: 1px dashed #e2d2b3; padding-right: 12px; }
    .ckc-coupon-value { font-size: 17px; font-weight: 800; color: #f86f69; line-height: 1.3; }
    .ckc-coupon-min { font-size: 11px; color: #8c7a64; margin-top: 2px; }
    .ckc-coupon-body { flex: 1; min-width: 0; }
    .ckc-coupon-title { font-size: 14px; font-weight: 700; color: #1a140f; }
    .ckc-coupon-meta { font-size: 12px; color: #8c7a64; margin-top: 3px; }
    .ckc-coupon-meta code { background: #f2e9d8; padding: 1px 6px; border-radius: 4px; }
    .ckc-coupon-apply { display: inline-block; background: #1a140f; color: #e3c586 !important; border-radius: 16px; padding: 7px 16px; font-size: 12px; font-weight: 700; text-decoration: none; white-space: nowrap; }
    .ckc-coupon-applied { color: #16a34a; font-size: 13px; font-weight: 700; white-space: nowrap; }
    </style>
    <?php
}

/* ---------------- 購物車頁：領券中心（已由需求從購物車移除，僅保留於結帳頁面） ---------------- */
// add_action( 'woocommerce_before_cart', 'ckc_cart_coupon_center', 15 );
function ckc_cart_coupon_center() {
    // 未登入不顯示
    if ( ! is_user_logged_in() ) {
        return;
    }

    $user_id     = get_current_user_id();
    $claimed_ids = (array) get_user_meta( $user_id, '_ckc_claimed_coupons', true );
    $claimed_ids = array_filter( array_map( 'intval', $claimed_ids ) );

    if ( empty( $claimed_ids ) ) {
        return; // 未領取任何券，不顯示面板
    }

    // 建構已領取且有效（未過期）的折價券清單
    $coupons = array();
    foreach ( $claimed_ids as $cid ) {
        $coupon = new WC_Coupon( $cid );
        if ( ! $coupon->get_id() ) continue;
        $wc_exp = $coupon->get_date_expires();
        if ( $wc_exp && $wc_exp->getTimestamp() < time() ) continue; // 過期不顯示
        $coupons[] = $coupon;
    }

    if ( empty( $coupons ) ) {
        return;
    }

    echo '<div class="ckc-coupon-center" style="margin-bottom: 20px;">';
    echo '<div style="font-size: 15px; font-weight: 700; color: #334155; margin-bottom: 4px;">🎟️ 我的優惠券</div>';
    echo '<p style="font-size:12px;color:#94a3b8;margin:0 0 8px;">每筆訂單限用一張優惠券</p>';
    ckc_render_coupon_cards( 'cart', $coupons );
    echo '</div>';
}

/* ---------------- 單券限制已統一至檔案頂部的 order hook（ckc_order_keep_single_coupon）----------------
 * 原本的 cart 層限制（woocommerce_applied_coupon）、手動輸入錯誤提示
 * （woocommerce_coupon_error）與重複的 order hook 已依「純 order hook」方向移除，
 * 前後台一致改在訂單層級保證單券。詳見本檔頂部。
 */

/* ---------------- 我的帳號「專屬優惠券」頁 ---------------- */
add_action( 'init', 'ckc_coupons_register_endpoint', 6 );
function ckc_coupons_register_endpoint() {
    add_rewrite_endpoint( 'coupons', EP_ROOT | EP_PAGES );
    if ( '1' !== get_option( 'ckc_coupons_rewrite_flushed' ) ) {
        flush_rewrite_rules();
        update_option( 'ckc_coupons_rewrite_flushed', '1' );
    }
}

add_action( 'wp_loaded', 'ckc_ensure_coupon_center_page', 10 );
function ckc_ensure_coupon_center_page() {
    if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'get_page_by_path' ) ) {
        return;
    }
    // 使用 transient 快取，避免每次 request 都查資料庫（有效期 24 小時）
    if ( get_transient( 'ckc_coupon_page_checked' ) ) {
        return;
    }
    set_transient( 'ckc_coupon_page_checked', '1', DAY_IN_SECONDS );

    // 檢查「領券中心」頁面是否存在（支援中文 Slug、Urlencode、及英文 slug）
    $page = get_page_by_path( '領券中心' );
    if ( ! $page ) {
        $page = get_page_by_path( rawurlencode( '領券中心' ) );
    }
    if ( ! $page ) {
        $page = get_page_by_path( 'coupon-center' );
    }

    if ( $page ) {
        // 若頁面已存在，確認內容是否包含新版 shortcode
        if ( strpos( $page->post_content, '[ckc_coupon_claim_center]' ) === false ) {
            wp_update_post( array(
                'ID'           => $page->ID,
                'post_content' => '[ckc_coupon_claim_center]',
            ) );
        }
    } else {
        // 若不存在則自動建立頁面
        wp_insert_post( array(
            'post_title'   => '領券中心',
            'post_name'    => '領券中心',
            'post_content' => '[ckc_coupon_claim_center]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ) );
    }
}


add_filter( 'woocommerce_account_menu_items', 'ckc_coupons_account_menu_item', 25 );
function ckc_coupons_account_menu_item( $items ) {
    $new = array();
    foreach ( $items as $key => $label ) {
        $new[ $key ] = $label;
        if ( 'points' === $key ) {
            $new['coupons'] = '專屬優惠券';
        }
    }
    if ( ! isset( $new['coupons'] ) ) {
        $new['coupons'] = '專屬優惠券';
    }
    return $new;
}

add_filter( 'woocommerce_endpoint_coupons_title', function () {
    return '專屬優惠券';
} );

add_action( 'woocommerce_account_coupons_endpoint', 'ckc_coupons_account_content' );
function ckc_coupons_account_content() {
    ?>
    <div style="background: #fdfaf7; border: 1px solid #f5ebe6; border-radius: 10px; padding: 16px 18px; margin-bottom: 18px;">
        <p style="margin: 0; font-size: 14px; color: #6b7280; line-height: 1.8;">
            以下優惠券皆可直接使用——點「套用去結帳」會自動套入購物車；也可以在結帳時手動輸入折扣碼。
        </p>
    </div>
    <?php
    ckc_render_coupon_cards( 'account' );
}

/* ----------------===================================================---------------- */
/* ---------------- 新增功能：PX Pay 風格 [折價券領取中心] 短代碼與 AJAX 領取 ---------------- */
/* ----------------===================================================---------------- */

add_action( 'wp_ajax_ckc_claim_coupon', 'ckc_claim_coupon_ajax_handler' );
add_action( 'wp_ajax_nopriv_ckc_claim_coupon', 'ckc_claim_coupon_ajax_handler' );
function ckc_claim_coupon_ajax_handler() {
    // Nonce 安全驗證
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ckc_claim_nonce' ) ) {
        wp_send_json_error( array( 'message' => '安全驗證失敗，請重新整理頁面後再試！' ) );
    }
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => '請先登入會員以領取折價券！' ) );
    }

    $coupon_id = isset( $_POST['coupon_id'] ) ? intval( $_POST['coupon_id'] ) : 0;
    if ( ! $coupon_id ) {
        wp_send_json_error( array( 'message' => '無效的折價券識別碼！' ) );
    }

    $coupon = new WC_Coupon( $coupon_id );
    if ( ! $coupon->get_id() || 'yes' !== $coupon->get_meta( '_ckc_coupon_claim_public' ) ) {
        wp_send_json_error( array( 'message' => '此折價券未開放領取！' ) );
    }

    // 驗證領取期限（使用 WooCommerce 原生 date_expires）
    $wc_expires = $coupon->get_date_expires();
    if ( $wc_expires && $wc_expires->getTimestamp() < time() ) {
        wp_send_json_error( array( 'message' => '此折價券已過期，無法領取！' ) );
    }

    // 驗證限量庫存
    $inventory = $coupon->get_meta( '_ckc_coupon_claim_inventory' );
    $claim_count = intval( $coupon->get_meta( '_ckc_coupon_claim_count' ) );
    if ( $inventory !== '' && $inventory !== false && $claim_count >= intval( $inventory ) ) {
        wp_send_json_error( array( 'message' => '此折價券已被搶光囉！' ) );
    }

    $user_id = get_current_user_id();
    $claimed_coupons = (array) get_user_meta( $user_id, '_ckc_claimed_coupons', true );

    if ( in_array( $coupon_id, $claimed_coupons, true ) ) {
        wp_send_json_error( array( 'message' => '您已經領取過此折價券囉！' ) );
    }

    // 儲存領取紀錄
    $claimed_coupons[] = $coupon_id;
    update_user_meta( $user_id, '_ckc_claimed_coupons', $claimed_coupons );

    // 增加領取次數
    $new_count = $claim_count + 1;
    update_post_meta( $coupon_id, '_ckc_coupon_claim_count', $new_count );

    wp_send_json_success( array( 
        'message'     => '領取成功！已存入您的券匣',
        'is_sold_out' => ( $inventory !== '' && $new_count >= intval( $inventory ) )
    ) );
}

add_action( 'wp_ajax_ckc_claim_by_code', 'ckc_claim_by_code_ajax_handler' );
add_action( 'wp_ajax_nopriv_ckc_claim_by_code', 'ckc_claim_by_code_ajax_handler' );
function ckc_claim_by_code_ajax_handler() {
    // Nonce 安全驗證
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ckc_claim_nonce' ) ) {
        wp_send_json_error( array( 'message' => '安全驗證失敗，請重新整理頁面後再試！' ) );
    }
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => '請先登入會員以領取折價券！' ) );
    }

    $code = isset( $_POST['coupon_code'] ) ? wc_format_coupon_code( sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) ) : '';
    if ( empty( $code ) ) {
        wp_send_json_error( array( 'message' => '請輸入折價券代碼！' ) );
    }

    $coupon_id = wc_get_coupon_id_by_code( $code );
    if ( ! $coupon_id ) {
        wp_send_json_error( array( 'message' => '找不到此折價券代碼，請確認代碼是否輸入正確。' ) );
    }

    $coupon = new WC_Coupon( $coupon_id );
    if ( ! $coupon->get_id() || 'yes' !== $coupon->get_meta( '_ckc_coupon_claim_public' ) ) {
        wp_send_json_error( array( 'message' => '此折價券不支援公開領取！' ) );
    }

    // 驗證領取期限（使用 WooCommerce 原生 date_expires）
    $wc_expires_check = $coupon->get_date_expires();
    if ( $wc_expires_check && $wc_expires_check->getTimestamp() < time() ) {
        wp_send_json_error( array( 'message' => '此折價券已過期，無法領取！' ) );
    }

    // 驗證限量庫存
    $inventory = $coupon->get_meta( '_ckc_coupon_claim_inventory' );
    $claim_count = intval( $coupon->get_meta( '_ckc_coupon_claim_count' ) );
    if ( $inventory !== '' && $inventory !== false && $claim_count >= intval( $inventory ) ) {
        wp_send_json_error( array( 'message' => '此折價券已被搶光囉！' ) );
    }

    $user_id = get_current_user_id();
    $claimed_coupons = (array) get_user_meta( $user_id, '_ckc_claimed_coupons', true );

    if ( in_array( $coupon_id, $claimed_coupons, true ) ) {
        wp_send_json_error( array( 'message' => '您已經領取過此折價券囉！' ) );
    }

    // 儲存領取紀錄
    $claimed_coupons[] = $coupon_id;
    update_user_meta( $user_id, '_ckc_claimed_coupons', $claimed_coupons );

    // 增加領取次數
    $new_count = $claim_count + 1;
    update_post_meta( $coupon_id, '_ckc_coupon_claim_count', $new_count );

    wp_send_json_success( array( 
        'message'     => '領取成功！已存入您的券匣。',
        'coupon_id'   => $coupon_id,
        'is_sold_out' => ( $inventory !== '' && $new_count >= intval( $inventory ) )
    ) );
}

add_shortcode( 'ckc_coupon_claim_center', 'ckc_coupon_claim_center_shortcode' );
function ckc_coupon_claim_center_shortcode() {
    if ( ! function_exists( 'WC' ) ) {
        return '';
    }

    $user_id = get_current_user_id();
    $claimed_ids = $user_id ? (array) get_user_meta( $user_id, '_ckc_claimed_coupons', true ) : array();
    $claimed_ids = array_filter( array_map( 'intval', $claimed_ids ) );

    // 與會員「專屬優惠券」頁共用同一份有效券清單（排除已刪除／草稿／空碼殭屍券），
    // 確保「我的券匣」徽章數與專屬優惠券頁的張數一致。
    $my_claimed_coupons = ckc_get_user_claimed_coupons( $user_id );

    // 獲取所有供領取的折價券
    $coupons = ckc_get_claimable_coupons();
    
    // 收集所有活動分類以生成分頁標籤
    $categories = array();
    foreach ( $coupons as $coupon ) {
        $cat = $coupon->get_meta( '_ckc_coupon_claim_category' );
        if ( $cat && ! in_array( $cat, $categories, true ) ) {
            $categories[] = $cat;
        }
    }

    ob_start();
    ?>
    <div id="ckc-claim-center-container">
        <!-- SPA 導覽列頭部 -->
        <div class="ckc-claim-header">
            <h2 class="ckc-claim-title">🎟️ 折價券領取中心</h2>
            <div class="ckc-claim-nav-buttons">
                <button type="button" class="ckc-nav-btn active" data-tab="claim-list">領券中心</button>
                <a class="ckc-nav-btn ckc-nav-link" href="<?php echo esc_url( wc_get_account_endpoint_url( 'coupons' ) ); ?>">
                    我的券匣 <span class="ckc-box-count"><?php echo count( $my_claimed_coupons ); ?></span>
                </a>
            </div>
        </div>

        <!-- 優惠代碼輸入搜尋區 -->
        <div class="ckc-search-bar-wrap">
            <div class="ckc-search-bar-inner">
                <input type="text" id="ckc-coupon-code-input" placeholder="請輸入優惠代碼" />
                <button type="button" id="ckc-submit-code-btn">領取</button>
            </div>
        </div>

        <!-- SPA 區塊面板 -->
        <div class="ckc-panel-wrap">
            <!-- 1. 領券中心列表面板 -->
            <div id="ckc-panel-claim-list" class="ckc-spa-panel active">
                <!-- 分類篩選列 -->
                <?php if ( ! empty( $categories ) ) : ?>
                    <div class="ckc-categories-filter-bar">
                        <button type="button" class="ckc-cat-tab active" data-category="all">全部</button>
                        <?php foreach ( $categories as $cat ) : ?>
                            <button type="button" class="ckc-cat-tab" data-category="<?php echo esc_attr( $cat ); ?>"><?php echo esc_html( $cat ); ?></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- 卡片列表 Grid -->
                <div class="ckc-coupon-cards-grid">
                    <?php if ( empty( $coupons ) ) : ?>
                        <div class="ckc-no-coupons">目前沒有可領取的折價券，敬請期待！</div>
                    <?php else : ?>
                        <?php foreach ( $coupons as $coupon ) :
                            $coupon_id   = $coupon->get_id();
                            $code        = $coupon->get_code();
                            $title       = $coupon->get_meta( '_ckc_coupon_label' );
                            if ( empty( $title ) ) {
                                $title = ckc_coupon_value_text( $coupon );
                            }
                            $deadline    = '';
                            $wc_exp      = $coupon->get_date_expires();
                            if ( $wc_exp ) {
                                // 前台顯示格式：YYYY/MM/DD，與後台「折價券到期日」同一資料來源
                                $deadline = $wc_exp->date( 'Y/m/d' );
                            }
                            $thumbnail   = $coupon->get_meta( '_ckc_coupon_claim_image' );
                            $banner      = $coupon->get_meta( '_ckc_coupon_claim_banner' );
                            $category    = $coupon->get_meta( '_ckc_coupon_claim_category' );
                            $desc        = $coupon->get_meta( '_ckc_coupon_claim_description' );
                            $notes       = $coupon->get_meta( '_ckc_coupon_claim_notes' );
                            $inventory   = $coupon->get_meta( '_ckc_coupon_claim_inventory' );
                            $claim_count = intval( $coupon->get_meta( '_ckc_coupon_claim_count' ) );

                            if ( empty( $thumbnail ) ) {
                                $thumbnail = get_template_directory_uri() . '/assets/images/coupon-newmember.jpg';
                            }

                            // 判斷狀態
                            $is_claimed = in_array( $coupon_id, $claimed_ids, true );
                            $is_sold_out = ( $inventory !== '' && $inventory !== false && $claim_count >= intval( $inventory ) );
                            
                            $status_class = '';
                            $btn_text = '領取';
                            $btn_disabled = '';
                            if ( $is_claimed ) {
                                $status_class = 'claimed';
                                $btn_text = '已領取';
                                $btn_disabled = 'disabled';
                            } elseif ( $is_sold_out ) {
                                $status_class = 'sold-out';
                                $btn_text = '已搶光';
                                $btn_disabled = 'disabled';
                            }
                            ?>
                            <div class="ckc-coupon-item-card <?php echo $status_class; ?>" 
                                 data-category="<?php echo esc_attr( $category ? $category : 'all' ); ?>"
                                 data-coupon-id="<?php echo esc_attr( $coupon_id ); ?>"
                                 data-title="<?php echo esc_attr( $title ); ?>"
                                 data-code="<?php echo esc_attr( $code ); ?>"
                                 data-deadline="<?php echo esc_attr( $deadline ); ?>"
                                 data-banner="<?php echo esc_attr( $banner ); ?>"
                                 data-desc="<?php echo esc_attr( $desc ); ?>"
                                 data-notes="<?php echo esc_attr( $notes ); ?>"
                                 data-claimed="<?php echo $is_claimed ? 'true' : 'false'; ?>"
                                 data-soldout="<?php echo $is_sold_out ? 'true' : 'false'; ?>">
                                
                                <div class="ckc-card-left">
                                    <div class="ckc-card-img-wrap">
                                        <img src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php echo esc_attr( $title ); ?>" width="80" height="80" loading="lazy" decoding="async" />
                                    </div>
                                    <a href="#" class="ckc-rules-trigger">使用規則</a>
                                </div>

                                <div class="ckc-card-divider" aria-hidden="true"></div>

                                <div class="ckc-card-middle">
                                    <h3 class="ckc-card-title"><?php echo esc_html( $title ); ?></h3>
                                    <?php if ( $deadline ) : ?>
                                        <div class="ckc-card-deadline">到期日：<?php echo esc_html( $deadline ); ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="ckc-card-right">
                                    <button type="button" class="ckc-claim-action-btn <?php echo $status_class; ?>" <?php echo $btn_disabled; ?>>
                                        <?php echo esc_html( $btn_text ); ?>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 2. 我的券匣面板 -->
            <div id="ckc-panel-my-box" class="ckc-spa-panel">
                <div class="ckc-coupon-cards-grid">
                    <?php if ( empty( $my_claimed_coupons ) ) : ?>
                        <div class="ckc-no-coupons">您的券匣空空如也，快去領券中心領取吧！</div>
                    <?php else : ?>
                        <?php foreach ( $my_claimed_coupons as $coupon ) :
                            $coupon_id   = $coupon->get_id();
                            $code        = $coupon->get_code();
                            $title       = $coupon->get_meta( '_ckc_coupon_label' );
                            if ( empty( $title ) ) {
                                $title = ckc_coupon_value_text( $coupon );
                            }
                            $deadline    = $coupon->get_meta( '_ckc_coupon_claim_deadline' );
                            $thumbnail   = $coupon->get_meta( '_ckc_coupon_claim_image' );
                            $banner      = $coupon->get_meta( '_ckc_coupon_claim_banner' );
                            $desc        = $coupon->get_meta( '_ckc_coupon_claim_description' );
                            $notes       = $coupon->get_meta( '_ckc_coupon_claim_notes' );

                            if ( empty( $thumbnail ) ) {
                                $thumbnail = get_template_directory_uri() . '/assets/images/coupon-newmember.jpg';
                            }
                            $apply_url = add_query_arg( 'ckc_apply_coupon', rawurlencode( $code ), wc_get_cart_url() );
                            ?>
                            <div class="ckc-coupon-item-card claimed-box" 
                                 data-coupon-id="<?php echo esc_attr( $coupon_id ); ?>"
                                 data-title="<?php echo esc_attr( $title ); ?>"
                                 data-code="<?php echo esc_attr( $code ); ?>"
                                 data-deadline="<?php echo esc_attr( $deadline ); ?>"
                                 data-banner="<?php echo esc_attr( $banner ); ?>"
                                 data-desc="<?php echo esc_attr( $desc ); ?>"
                                 data-notes="<?php echo esc_attr( $notes ); ?>"
                                 data-claimed="true"
                                 data-soldout="false">
                                
                                <div class="ckc-card-left">
                                    <div class="ckc-card-img-wrap">
                                        <img src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php echo esc_attr( $title ); ?>" width="80" height="80" loading="lazy" decoding="async" />
                                    </div>
                                    <a href="#" class="ckc-rules-trigger">使用規則</a>
                                </div>

                                <div class="ckc-card-divider" aria-hidden="true"></div>

                                <div class="ckc-card-middle">
                                    <h3 class="ckc-card-title"><?php echo esc_html( $title ); ?></h3>
                                    <div class="ckc-card-code">代碼：<code><?php echo esc_html( strtoupper( $code ) ); ?></code></div>
                                </div>
                                
                                <div class="ckc-card-right">
                                    <a href="<?php echo esc_url( $apply_url ); ?>" class="ckc-apply-action-btn">立即使用</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 詳情頁彈出視窗 Sheet Overlay -->
        <div id="ckc-detail-modal" class="ckc-modal-overlay">
            <div class="ckc-modal-sheet">
                <!-- 彈窗頭部 -->
                <div class="ckc-modal-header">
                    <button type="button" class="ckc-modal-close-btn">
                        <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                    </button>
                    <span class="ckc-modal-header-title">福利券說明</span>
                </div>
                
                <!-- 彈窗內容區 (滾動) -->
                <div class="ckc-modal-body">
                    <div class="ckc-modal-banner-wrap">
                        <img id="ckc-modal-banner-img" src="" alt="Banner" />
                    </div>
                    
                    <div class="ckc-modal-details-content">
                        <h2 id="ckc-modal-title" class="ckc-modal-coupon-title"></h2>
                        
                        <div class="ckc-modal-date-info">
                            <div class="ckc-date-row">
                                <span class="ckc-date-label">領取期限：</span>
                                <span id="ckc-modal-deadline-val"></span>
                            </div>
                        </div>

                        <div class="ckc-modal-section-group">
                            <h4 class="ckc-section-heading">活動說明</h4>
                            <div id="ckc-modal-desc-content" class="ckc-section-text"></div>
                        </div>

                        <div class="ckc-modal-section-group">
                            <h4 class="ckc-section-heading">注意事項</h4>
                            <div id="ckc-modal-notes-content" class="ckc-section-text"></div>
                        </div>
                    </div>
                </div>

                <!-- 彈窗底部固定領取鈕 -->
                <div class="ckc-modal-footer">
                    <button type="button" id="ckc-modal-submit-btn" class="ckc-modal-claim-btn">立即領取</button>
                </div>
            </div>
        </div>

        <!-- 吐司通知提示 Toast -->
        <div id="ckc-toast" class="ckc-toast-box"></div>
    </div>

    <!-- 領券中心專屬樣式 CSS：港口提貨券主題（Modular styling） -->
    <style>
    #ckc-claim-center-container {
        --ckc-ink: #1a140f;
        --ckc-ink-soft: #2c2018;
        --ckc-gold: #c9974a;
        --ckc-gold-soft: #e3c586;
        --ckc-coral: #f86f69;
        --ckc-parchment: #f2e9d8;
        --ckc-card: #fffaf1;
        --ckc-rope: #b28a58;
        --ckc-rope-soft: #e2d2b3;
        --ckc-text: #3a2f24;
        --ckc-muted: #8c7a64;

        max-width: 820px;
        margin: 0 auto;
        font-family: -apple-system, BlinkMacSystemFont, "Noto Sans TC", "PingFang TC", Arial, sans-serif;
        color: var(--ckc-text);
        background-color: var(--ckc-parchment);
        padding: 0 0 22px;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(26,20,15,0.1);
    }
    #ckc-claim-center-container * {
        box-sizing: border-box;
    }

    /* ── 港口售票亭頭部：暗底＋潮金紋理 ── */
    .ckc-claim-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin: 0 0 20px;
        padding: 22px 22px 20px;
        background:
            repeating-linear-gradient(118deg, rgba(201,151,74,0.07) 0 16px, transparent 16px 34px),
            linear-gradient(135deg, var(--ckc-ink) 0%, var(--ckc-ink-soft) 100%);
        border-bottom: 3px solid var(--ckc-gold);
    }
    .ckc-claim-title {
        font-size: 21px;
        font-weight: 700;
        color: var(--ckc-gold-soft);
        letter-spacing: 0.02em;
        margin: 0;
        font-family: Georgia, "Times New Roman", "Songti TC", "PMingLiU", serif;
    }
    .ckc-claim-nav-buttons {
        display: flex;
        background: rgba(255,255,255,0.08);
        padding: 4px;
        border-radius: 30px;
        border: 1px solid rgba(227,197,134,0.25);
    }
    .ckc-nav-btn {
        background: none !important;
        border: none !important;
        padding: 8px 20px !important;
        font-size: 14px !important;
        font-weight: 600 !important;
        color: var(--ckc-gold-soft) !important;
        cursor: pointer !important;
        border-radius: 20px !important;
        transition: all 0.25s ease !important;
        box-shadow: none !important;
    }
    .ckc-nav-btn.active {
        background: var(--ckc-gold) !important;
        color: var(--ckc-ink) !important;
        box-shadow: 0 2px 8px rgba(0,0,0,0.25) !important;
    }
    .ckc-box-count {
        background: var(--ckc-coral);
        color: #fff;
        padding: 1px 6px;
        font-size: 11px;
        border-radius: 10px;
        margin-left: 4px;
    }

    /* ── 售票窗口：優惠代碼輸入 ── */
    .ckc-search-bar-wrap {
        background: var(--ckc-card);
        padding: 12px 16px;
        margin: 0 22px 22px;
        border-radius: 10px;
        border: 1.5px dashed var(--ckc-rope);
    }
    .ckc-search-bar-inner {
        display: flex;
        gap: 8px;
    }
    .ckc-search-bar-inner input {
        flex: 1;
        height: 42px !important;
        border: 1px solid var(--ckc-rope-soft) !important;
        border-radius: 8px !important;
        padding: 0 16px !important;
        font-size: 15px !important;
        background: #fff !important;
        color: var(--ckc-text) !important;
        outline: none !important;
        transition: border-color 0.2s !important;
    }
    .ckc-search-bar-inner input:focus {
        border-color: var(--ckc-gold) !important;
    }
    .ckc-search-bar-inner button {
        height: 42px !important;
        line-height: 42px !important;
        padding: 0 24px !important;
        background: var(--ckc-coral) !important;
        color: #fff !important;
        border: none !important;
        border-radius: 8px !important;
        font-size: 15px !important;
        font-weight: 600 !important;
        cursor: pointer !important;
        transition: background 0.2s !important;
    }
    .ckc-search-bar-inner button:hover {
        background: #e85850 !important;
    }

    .ckc-spa-panel {
        display: none;
        padding: 0 22px;
    }
    .ckc-spa-panel.active {
        display: block;
    }

    /* ── 分類：吊牌式標籤 ── */
    .ckc-categories-filter-bar {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        padding-bottom: 12px;
        margin-bottom: 18px;
        scrollbar-width: none;
    }
    .ckc-categories-filter-bar::-webkit-scrollbar {
        display: none;
    }
    .ckc-cat-tab {
        background: var(--ckc-card) !important;
        border: 1px solid var(--ckc-rope-soft) !important;
        color: var(--ckc-text) !important;
        padding: 6px 16px !important;
        border-radius: 20px !important;
        font-size: 13px !important;
        font-weight: 500 !important;
        cursor: pointer !important;
        white-space: nowrap;
        transition: all 0.2s ease !important;
        box-shadow: none !important;
    }
    .ckc-cat-tab.active {
        background: var(--ckc-ink) !important;
        border-color: var(--ckc-ink) !important;
        color: var(--ckc-gold-soft) !important;
    }

    .ckc-coupon-cards-grid {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }
    .ckc-no-coupons {
        text-align: center;
        padding: 40px 20px;
        color: var(--ckc-muted);
        font-size: 15px;
    }

    /* ── 簽名元素：碼頭提貨券卡片（含撕線打孔）── */
    .ckc-coupon-item-card {
        position: relative;
        display: flex;
        align-items: stretch;
        background: var(--ckc-card);
        border-radius: 12px;
        padding: 16px 18px;
        box-shadow: 0 3px 10px rgba(26,20,15,0.06);
        border: 1px solid var(--ckc-rope-soft);
        transition: transform 0.2s, box-shadow 0.2s;
        gap: 14px;
        overflow: visible;
    }
    .ckc-coupon-item-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 18px rgba(26,20,15,0.1);
    }
    .ckc-coupon-item-card.claimed,
    .ckc-coupon-item-card.sold-out {
        opacity: 0.62;
    }

    .ckc-card-left {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-width: 78px;
        gap: 8px;
    }
    .ckc-card-img-wrap {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        overflow: hidden;
        border: 2px solid var(--ckc-gold);
        box-shadow: 0 0 0 3px var(--ckc-card), 0 0 0 4px var(--ckc-rope-soft);
    }
    .ckc-card-img-wrap img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .ckc-rules-trigger {
        font-size: 11px;
        color: var(--ckc-muted);
        text-decoration: underline;
        text-underline-offset: 2px;
        font-weight: 500;
        cursor: pointer;
        white-space: nowrap;
    }
    .ckc-rules-trigger:hover {
        color: var(--ckc-coral);
    }

    /* 打孔撕線分隔：兩端半圓形「打孔」露出票券外的底色 */
    .ckc-card-divider {
        flex: 0 0 auto;
        width: 0;
        align-self: stretch;
        position: relative;
        border-left: 2px dashed var(--ckc-rope);
        margin: -16px 0;
        padding: 16px 0;
    }
    .ckc-card-divider::before,
    .ckc-card-divider::after {
        content: '';
        position: absolute;
        left: -9px;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: var(--ckc-parchment);
        border: 1px solid var(--ckc-rope-soft);
    }
    .ckc-card-divider::before {
        top: -9px;
    }
    .ckc-card-divider::after {
        bottom: -9px;
    }

    .ckc-card-middle {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .ckc-card-title {
        font-size: 16px;
        font-weight: 700;
        color: var(--ckc-ink);
        margin: 0 0 6px 0;
        line-height: 1.4;
        cursor: pointer;
        font-family: Georgia, "Times New Roman", "Songti TC", "PMingLiU", serif;
    }
    .ckc-card-title:hover {
        color: var(--ckc-coral);
    }
    .ckc-card-deadline {
        font-size: 12.5px;
        color: var(--ckc-muted);
    }
    .ckc-card-code {
        font-size: 12.5px;
        color: var(--ckc-muted);
    }
    .ckc-card-code code {
        background: var(--ckc-parchment);
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 12px;
        font-family: ui-monospace, "SF Mono", Menlo, monospace;
        color: var(--ckc-ink);
        letter-spacing: 0.02em;
    }

    .ckc-card-right {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        min-width: 94px;
    }
    .ckc-claim-action-btn,
    .ckc-apply-action-btn {
        display: inline-block !important;
        text-align: center !important;
        width: 94px !important;
        height: 40px !important;
        line-height: 40px !important;
        padding: 0 !important;
        background: var(--ckc-coral) !important;
        color: #fff !important;
        border: none !important;
        border-radius: 20px !important;
        font-size: 14px !important;
        font-weight: 700 !important;
        cursor: pointer !important;
        transition: background 0.2s, opacity 0.2s !important;
        white-space: nowrap !important;
        text-decoration: none !important;
        box-shadow: 0 2px 6px rgba(248,111,105,0.3) !important;
    }
    .ckc-claim-action-btn.claimed {
        background: var(--ckc-parchment) !important;
        color: var(--ckc-muted) !important;
        cursor: default !important;
        box-shadow: none !important;
    }
    .ckc-claim-action-btn.sold-out {
        background: var(--ckc-parchment) !important;
        color: #b7a889 !important;
        cursor: default !important;
        box-shadow: none !important;
    }
    .ckc-apply-action-btn {
        background: var(--ckc-ink) !important;
        color: var(--ckc-gold-soft) !important;
        box-shadow: 0 2px 6px rgba(26,20,15,0.2) !important;
    }
    .ckc-apply-action-btn:hover {
        background: var(--ckc-ink-soft) !important;
    }
    .ckc-claim-action-btn:not(.claimed):not(.sold-out):hover {
        background: #e85850 !important;
    }

    /* 滑動彈出說明視窗 (Bottom/Slide-up panel) */
    .ckc-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(20,15,10,0.55);
        display: flex;
        align-items: flex-end;
        justify-content: center;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s ease;
        z-index: 99999;
    }
    .ckc-modal-overlay.open {
        opacity: 1;
        pointer-events: auto;
    }
    .ckc-modal-sheet {
        background: var(--ckc-card, #fffaf1);
        width: 100%;
        max-width: 500px;
        border-radius: 20px 20px 0 0;
        display: flex;
        flex-direction: column;
        max-height: 85vh;
        transform: translateY(100%);
        transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        box-shadow: 0 -10px 30px rgba(0,0,0,0.25);
    }
    .ckc-modal-overlay.open .ckc-modal-sheet {
        transform: translateY(0);
    }

    .ckc-modal-header {
        display: flex;
        align-items: center;
        padding: 16px;
        background: var(--ckc-ink, #1a140f);
        border-bottom: 3px solid var(--ckc-gold, #c9974a);
    }
    .ckc-modal-close-btn {
        background: none !important;
        border: none !important;
        padding: 4px !important;
        cursor: pointer !important;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: none !important;
    }
    .ckc-modal-close-btn svg {
        width: 24px;
        height: 24px;
        fill: var(--ckc-gold-soft, #e3c586);
    }
    .ckc-modal-header-title {
        font-size: 16px;
        font-weight: 700;
        color: var(--ckc-gold-soft, #e3c586);
        flex: 1;
        text-align: center;
        margin-right: 32px;
        font-family: Georgia, "Times New Roman", "Songti TC", "PMingLiU", serif;
    }

    .ckc-modal-body {
        flex: 1;
        overflow-y: auto;
        padding-bottom: 80px;
    }
    .ckc-modal-banner-wrap {
        width: 100%;
        aspect-ratio: 16/10;
        overflow: hidden;
        background: var(--ckc-parchment, #f2e9d8);
    }
    .ckc-modal-banner-wrap img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .ckc-modal-details-content {
        padding: 20px;
    }
    .ckc-modal-coupon-title {
        font-size: 19px;
        font-weight: 700;
        color: var(--ckc-ink, #1a140f);
        margin: 0 0 12px 0;
        line-height: 1.4;
        font-family: Georgia, "Times New Roman", "Songti TC", "PMingLiU", serif;
    }
    .ckc-modal-date-info {
        background: var(--ckc-parchment, #f2e9d8);
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
        border: 1px dashed var(--ckc-rope, #b28a58);
    }
    .ckc-date-row {
        margin-bottom: 4px;
    }
    .ckc-date-row:last-child {
        margin-bottom: 0;
    }
    .ckc-date-label {
        font-weight: 600;
        color: var(--ckc-muted, #8c7a64);
    }
    .ckc-modal-section-group {
        margin-bottom: 20px;
    }
    .ckc-section-heading {
        font-size: 15px;
        font-weight: 700;
        color: var(--ckc-ink, #1a140f);
        border-left: 3px solid var(--ckc-coral, #f86f69);
        padding-left: 8px;
        margin: 0 0 10px 0;
    }
    .ckc-section-text {
        font-size: 14px;
        line-height: 1.7;
        color: var(--ckc-text, #3a2f24);
        white-space: pre-line;
    }

    .ckc-modal-footer {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 16px 20px;
        background: rgba(255,250,241,0.92);
        backdrop-filter: blur(10px);
        border-top: 1px solid var(--ckc-rope-soft, #e2d2b3);
        display: flex;
        justify-content: center;
        z-index: 10;
    }
    .ckc-modal-claim-btn {
        width: 100% !important;
        height: 46px !important;
        background: var(--ckc-coral, #f86f69) !important;
        color: #fff !important;
        border: none !important;
        border-radius: 25px !important;
        font-size: 16px !important;
        font-weight: 700 !important;
        cursor: pointer !important;
        transition: background 0.2s !important;
        box-shadow: 0 4px 12px rgba(248,111,105,0.3) !important;
    }
    .ckc-modal-claim-btn.claimed {
        background: #d8c9ac !important;
        color: #8c7a64 !important;
        cursor: default !important;
        box-shadow: none !important;
    }
    .ckc-modal-claim-btn.sold-out {
        background: #e2d2b3 !important;
        color: #b7a889 !important;
        cursor: default !important;
        box-shadow: none !important;
    }

    /* 吐司小提示 Toast */
    .ckc-toast-box {
        position: fixed;
        bottom: 40px;
        left: 50%;
        transform: translateX(-50%) translateY(100px);
        background: var(--ckc-ink, #1a140f);
        color: var(--ckc-gold-soft, #e3c586);
        padding: 12px 28px;
        border-radius: 30px;
        font-size: 14px;
        font-weight: 500;
        z-index: 100000;
        box-shadow: 0 5px 20px rgba(0,0,0,0.3);
        border: 1px solid rgba(227,197,134,0.3);
        opacity: 0;
        pointer-events: none;
        transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.3s ease;
    }
    .ckc-toast-box.show {
        transform: translateX(-50%) translateY(0);
        opacity: 1;
    }

    /* 電腦桌機版彈出視窗微調 */
    @media (min-width: 769px) {
        .ckc-modal-overlay {
            align-items: center;
        }
        .ckc-modal-sheet {
            border-radius: 16px;
            max-height: 80vh;
            transform: scale(0.9);
        }
        .ckc-modal-overlay.open .ckc-modal-sheet {
            transform: scale(1);
        }
        .ckc-modal-footer {
            border-radius: 0 0 16px 16px;
        }
    }

    /* 手機版：卡片欄位縮排、按鈕維持可點面積 */
    @media (max-width: 480px) {
        .ckc-claim-header {
            padding: 18px 16px 16px;
        }
        .ckc-search-bar-wrap,
        .ckc-spa-panel {
            margin-left: 0;
            margin-right: 0;
            padding-left: 16px;
            padding-right: 16px;
        }
        .ckc-search-bar-wrap {
            margin: 0 16px 18px;
        }
        .ckc-coupon-item-card {
            padding: 14px 12px;
            gap: 10px;
        }
        .ckc-card-left {
            min-width: 60px;
        }
        .ckc-card-img-wrap {
            width: 52px;
            height: 52px;
        }
        .ckc-card-right {
            min-width: 78px;
        }
        .ckc-claim-action-btn,
        .ckc-apply-action-btn {
            width: 78px !important;
        }
    }
    </style>

    <!-- 前端互動 AJAX/SPA 邏輯 JavaScript -->
    <script type="text/javascript">
    var ckcClaimNonce = '<?php echo esc_js( wp_create_nonce( 'ckc_claim_nonce' ) ); ?>';
    jQuery(document).ready(function($) {
        // 切換頁籤 (SPA 分頁動作)。只作用於分頁「按鈕」；「我的券匣」已改為
        // 連結（a.ckc-nav-link）直接前往會員專屬優惠券頁，不走 SPA 切換。
        $('button.ckc-nav-btn').on('click', function() {
            var tab = $(this).data('tab');
            $('.ckc-nav-btn').removeClass('active');
            $(this).addClass('active');
            
            $('.ckc-spa-panel').removeClass('active');
            $('#ckc-panel-' + tab).addClass('active');
            
            // 如果切換到「我的券匣」，隱藏類別篩選列與搜尋欄
            if (tab === 'my-box') {
                $('.ckc-categories-filter-bar').hide();
                $('.ckc-search-bar-wrap').hide();
            } else {
                $('.ckc-categories-filter-bar').show();
                $('.ckc-search-bar-wrap').show();
            }
        });

        // 點擊類別進行即時篩選
        $('.ckc-cat-tab').on('click', function() {
            var cat = $(this).data('category');
            $('.ckc-cat-tab').removeClass('active');
            $(this).addClass('active');

            if (cat === 'all') {
                $('.ckc-coupon-item-card:not(.claimed-box)').show();
            } else {
                $('.ckc-coupon-item-card:not(.claimed-box)').hide();
                $('.ckc-coupon-item-card[data-category="' + cat + '"]:not(.claimed-box)').show();
            }
        });

        // Toast 訊息提示
        function showToast(message) {
            var $toast = $('#ckc-toast');
            $toast.text(message).addClass('show');
            setTimeout(function() {
                $toast.removeClass('show');
            }, 3000);
        }

        // 福利券使用規則彈窗詳細資料載入
        var currentModalCouponId = null;
        
        $('body').on('click', '.ckc-rules-trigger, .ckc-card-title', function(e) {
            e.preventDefault();
            var card = $(this).closest('.ckc-coupon-item-card');
            var couponId = card.data('coupon-id');
            var title = card.data('title');
            var deadline = card.data('deadline');
            var banner = card.data('banner');
            var desc = card.data('desc');
            var notes = card.data('notes');
            var isClaimed = card.data('claimed');
            var isSoldOut = card.data('soldout');

            currentModalCouponId = couponId;

            // 帶入彈出視窗資料
            $('#ckc-modal-title').text(title);
            
            if (deadline) {
                var formattedDate = deadline.replace(/-/g, '/');
                $('#ckc-modal-deadline-val').text(formattedDate);
            } else {
                $('#ckc-modal-deadline-val').text('無限制');
            }

            if (banner) {
                $('#ckc-modal-banner-img').attr('src', banner).show();
                $('.ckc-modal-banner-wrap').show();
            } else {
                $('#ckc-modal-banner-img').attr('src', '').hide();
                $('.ckc-modal-banner-wrap').hide();
            }

            $('#ckc-modal-desc-content').text(desc || '暫無活動說明');
            $('#ckc-modal-notes-content').text(notes || '暫無注意事項');

            // 彈出視窗底部的領取按鈕狀態
            var $subBtn = $('#ckc-modal-submit-btn');
            $subBtn.removeClass('claimed sold-out').removeAttr('disabled');
            
            var isMyBox = card.hasClass('claimed-box');
            if (isMyBox || isClaimed === true || isClaimed === 'true') {
                $subBtn.addClass('claimed').text('已領取').attr('disabled', 'disabled');
            } else if (isSoldOut === true || isSoldOut === 'true') {
                $subBtn.addClass('sold-out').text('已搶光').attr('disabled', 'disabled');
            } else {
                $subBtn.text('立即領取');
            }

            $('#ckc-detail-modal').addClass('open');
        });

        // 關閉彈出視窗
        $('.ckc-modal-close-btn, .ckc-modal-overlay').on('click', function(e) {
            if (e.target === this || $(this).hasClass('ckc-modal-close-btn')) {
                $('#ckc-detail-modal').removeClass('open');
            }
        });

        // AJAX 領取折價券底層方法
        function claimCouponAjax(couponId, callback) {
            $.ajax({
                url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
                type: 'POST',
                data: {
                    action: 'ckc_claim_coupon',
                    coupon_id: couponId,
                    nonce: ckcClaimNonce
                },
                success: function(response) {
                    if (response.success) {
                        showToast(response.data.message);
                        
                        // 同步更新列表中卡片的狀態
                        var card = $('.ckc-coupon-item-card[data-coupon-id="' + couponId + '"]');
                        card.data('claimed', 'true').addClass('claimed');
                        card.find('.ckc-claim-action-btn').addClass('claimed').text('已領取').attr('disabled', 'disabled');
                        
                        // 更新券匣總計數
                        var currentCount = parseInt($('.ckc-box-count').text()) || 0;
                        $('.ckc-box-count').text(currentCount + 1);

                        // 判斷庫存狀態
                        if (response.data.is_sold_out) {
                            card.data('soldout', 'true').addClass('sold-out');
                        }

                        // 動態插入一筆到「我的券匣」面板中，使用戶不需重新整理
                        var code = card.data('code');
                        var title = card.data('title');
                        var deadline = card.data('deadline');
                        var banner = card.data('banner');
                        var desc = card.data('desc');
                        var notes = card.data('notes');
                        var img = card.find('.ckc-card-img-wrap img').attr('src');
                        var applyUrl = '<?php echo esc_url( wc_get_cart_url() ); ?>?ckc_apply_coupon=' + encodeURIComponent(code);

                        var claimedHtml = `
                            <div class="ckc-coupon-item-card claimed-box" 
                                 data-coupon-id="${couponId}"
                                 data-title="${title}"
                                 data-code="${code}"
                                 data-deadline="${deadline}"
                                 data-banner="${banner}"
                                 data-desc="${desc}"
                                 data-notes="${notes}"
                                 data-claimed="true"
                                 data-soldout="false">
                                <div class="ckc-card-left">
                                    <div class="ckc-card-img-wrap">
                                        <img src="${img}" alt="${title}" />
                                    </div>
                                    <a href="#" class="ckc-rules-trigger">使用規則</a>
                                </div>
                                <div class="ckc-card-divider" aria-hidden="true"></div>
                                <div class="ckc-card-middle">
                                    <h3 class="ckc-card-title">${title}</h3>
                                    <div class="ckc-card-code">代碼：<code>${code.toUpperCase()}</code></div>
                                </div>
                                <div class="ckc-card-right">
                                    <a href="${applyUrl}" class="ckc-apply-action-btn">立即使用</a>
                                </div>
                            </div>
                        `;

                        $('#ckc-panel-my-box .ckc-no-coupons').remove();
                        $('#ckc-panel-my-box .ckc-coupon-cards-grid').append(claimedHtml);

                        if (callback) callback(true);
                    } else {
                        showToast(response.data.message || '領取失敗，請重新重試！');
                        if (callback) callback(false);
                    }
                },
                error: function() {
                    showToast('網路連線失敗，請稍後重試！');
                    if (callback) callback(false);
                }
            });
        }

        // 列表中的卡片「領取」按鈕點擊事件
        $('body').on('click', '.ckc-claim-action-btn:not(.claimed):not(.sold-out)', function(e) {
            e.preventDefault();
            var btn = $(this);
            var card = btn.closest('.ckc-coupon-item-card');
            var couponId = card.data('coupon-id');

            btn.text('領取中...').attr('disabled', 'disabled');
            claimCouponAjax(couponId, function(success) {
                if (!success) {
                    btn.text('領取').removeAttr('disabled');
                }
            });
        });

        // 彈出視窗內的「立即領取」按鈕點擊事件
        $('#ckc-modal-submit-btn').on('click', function(e) {
            e.preventDefault();
            var btn = $(this);
            if (btn.hasClass('claimed') || btn.hasClass('sold-out') || !currentModalCouponId) {
                return;
            }

            btn.text('領取中...').attr('disabled', 'disabled');
            claimCouponAjax(currentModalCouponId, function(success) {
                if (success) {
                    btn.addClass('claimed').text('已領取').attr('disabled', 'disabled');
                    $('#ckc-detail-modal').removeClass('open');
                } else {
                    btn.removeClass('claimed').text('立即領取').removeAttr('disabled');
                }
            });
        });

        // 手動輸入折價券代碼點擊領取事件
        $('#ckc-submit-code-btn').on('click', function(e) {
            e.preventDefault();
            var btn = $(this);
            var codeInput = $('#ckc-coupon-code-input');
            var code = codeInput.val().trim();

            if (!code) {
                showToast('請輸入折價券代碼！');
                return;
            }

            btn.text('領取中...').attr('disabled', 'disabled');
            $.ajax({
                url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
                type: 'POST',
                data: {
                    action: 'ckc_claim_by_code',
                    coupon_code: code,
                    nonce: ckcClaimNonce
                },
                success: function(response) {
                    btn.text('領取').removeAttr('disabled');
                    if (response.success) {
                        showToast(response.data.message);
                        codeInput.val('');
                        
                        // 若該券存在於目前畫面的列表中，進行同步狀態更新
                        var couponId = response.data.coupon_id;
                        var card = $('.ckc-coupon-item-card[data-coupon-id="' + couponId + '"]');
                        if (card.length) {
                            card.data('claimed', 'true').addClass('claimed');
                            card.find('.ckc-claim-action-btn').addClass('claimed').text('已領取').attr('disabled', 'disabled');
                        }

                        // 稍微延遲後重新整理頁面，同步所有狀態與券匣
                        setTimeout(function() {
                            location.reload();
                        }, 1200);
                    } else {
                        showToast(response.data.message || '無法領取該折扣碼，請重試！');
                    }
                },
                error: function() {
                    btn.text('領取').removeAttr('disabled');
                    showToast('網路連線失敗，請稍後重試！');
                }
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}


/* ================================================================================
 * 後台：統一折價券入口 — 只保留唯一路徑「🎟️ 折價券管理」
 * 完整移除所有其他 WooCommerce / 行銷 / 插件新增的折價券連結
 * ================================================================================ */

// ── Step 1：以最高優先順序（9999）徹底掃描所有選單，移除 shop_coupon 相關連結
add_action( 'admin_menu', 'ckc_purge_all_coupon_menu_entries', 9999 );
function ckc_purge_all_coupon_menu_entries() {
    global $menu, $submenu;

    // 1a. 移除所有頂層 shop_coupon 選單
    remove_menu_page( 'edit.php?post_type=shop_coupon' );

    // 1b. 枚舉所有已知可能包含折價券的父選單（涵蓋 WooCommerce + 行銷 + 第三方插件）
    $known_parents = array(
        'woocommerce',
        'woocommerce-marketing',
        'wc-admin',
        'wc-reports',
    );

    // 也從 $menu 動態蒐集所有頂層選單的 slug
    // 注意：排除 ckc-referral-admin（「會員與行銷」頂層選單，折價券管理
    // 收整後的新掛載父選單，見 ckc-coupons.php 的 ckc_register_coupon_admin_menu()）。
    // 這個父選單底下本來就合法掛了一個指向
    // post-new.php?post_type=shop_coupon 的「新增折價券」子選單，不能被
    // 下面 1c 的清除邏輯誤刪——這裡原本排除的是舊版直接掛載的頂層 slug
    // ckc-coupon-center，收整後這筆合法連結搬到 ckc-referral-admin
    // 底下，排除清單也要跟著改，否則自己剛註冊的「新增折價券」會被
    // 這個掃描迴圈當成別人的殘留連結清掉。
    if ( is_array( $menu ) ) {
        foreach ( $menu as $item ) {
            if ( isset( $item[2] ) && $item[2] !== 'ckc-referral-admin' ) {
                $known_parents[] = $item[2];
            }
        }
    }
    $known_parents = array_unique( $known_parents );

    // 1c. 對每個父選單掃描並移除 shop_coupon 子項
    foreach ( $known_parents as $parent_slug ) {
        // 方法 A：WordPress 標準 API
        remove_submenu_page( $parent_slug, 'edit.php?post_type=shop_coupon' );

        // 方法 B：直接操作 $submenu（最可靠）
        if ( ! isset( $submenu[ $parent_slug ] ) || ! is_array( $submenu[ $parent_slug ] ) ) {
            continue;
        }
        foreach ( $submenu[ $parent_slug ] as $key => $item ) {
            if ( ! isset( $item[2] ) ) {
                continue;
            }
            $slug = (string) $item[2];
            if (
                $slug === 'edit.php?post_type=shop_coupon' ||
                strpos( $slug, 'post_type=shop_coupon' ) !== false
            ) {
                unset( $submenu[ $parent_slug ][ $key ] );
            }
        }
    }
}

// ── Step 2：admin_head CSS 強制隱藏兜底（瀏覽器端最終防線）
add_action( 'admin_head', 'ckc_hide_coupon_menu_css' );
function ckc_hide_coupon_menu_css() {
    global $post_type;
    ?>
    <style id="ckc-hide-wc-coupon">
        /* 強制隱藏所有非自訂的 shop_coupon 連結 */
        #adminmenu a[href="edit.php?post_type=shop_coupon"],
        #adminmenu li:has(> a[href="edit.php?post_type=shop_coupon"]),
        #adminmenu a[href*="post_type=shop_coupon"]:not([href*="post-new"]) {
            display: none !important;
        }
        <?php if ( 'shop_coupon' === $post_type ) : ?>
        /* 隱藏「一般」tab 的「折價券到期日」欄位
           到期日改由「領券中心設定 > 領取截止期限」統一管理並自動同步 */
        .coupon_options_panel p.form-field.expiry_date_field {
            display: none !important;
        }
        <?php endif; ?>
    </style>
    <?php
}

// ── Step 3：攔截直接 URL 存取（edit.php?post_type=shop_coupon）→ 導向自訂管理頁
add_action( 'current_screen', 'ckc_redirect_coupon_list_to_custom_page' );
function ckc_redirect_coupon_list_to_custom_page() {
    $screen = get_current_screen();
    if ( ! $screen ) {
        return;
    }
    if ( 'edit-shop_coupon' === $screen->id && ! isset( $_GET['ckc_bypass'] ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=ckc-coupon-center' ) );
        exit;
    }
}


/* ════════════════════════════════════════════
   前台：隱藏購物車/結帳原生折價券輸入欄
   （優惠券改由「我的優惠券」自訂面板管理）
   ════════════════════════════════════════════ */

// ── CSS 隱藏購物車表格底部「折價券 + 使用優惠券」欄位
add_action( 'wp_head', 'ckc_hide_cart_coupon_input_css' );
function ckc_hide_cart_coupon_input_css() {
    if ( ! ( function_exists('is_cart') && is_cart() ) && ! ( function_exists('is_checkout') && is_checkout() ) ) {
        return;
    }
    ?>
    <style id="ckc-hide-cart-coupon">
        /* 隱藏購物車原生折價券輸入區 */
        .woocommerce-cart-form .coupon,
        .woocommerce-cart .coupon,
        .cart_totals .woocommerce-form-coupon-toggle,
        .woocommerce-checkout .woocommerce-form-coupon-toggle,
        .checkout_coupon.woocommerce-form-coupon {
            display: none !important;
        }
        /* 隱藏結帳頁下方重複的原生紅利點數輸入區 */
        .woocommerce-checkout-review-order .wps_wpr_checkout_points_class,
        #order_review .wps_wpr_checkout_points_class,
        #order_review .custom_point_checkout {
            display: none !important;
        }
        /* 移除紅利點數輸入框可能帶有的日曆等圖示背景 */
        #wps_cart_points {
            background-image: none !important;
            background-position: initial !important;
            padding-left: 20px !important;
        }
        /* 隱藏原生與新版 Gutenberg Block 購物車頁面的紅利點數輸入區 */
        .woocommerce-cart #wps_wpr_button_to_add_points_section,
        .woocommerce-cart .wps_wpr_apply_custom_points,
        .woocommerce-cart .wps_wpr_append_points_apply_html,
        .woocommerce-cart .wps_wpr_points_class,
        .woocommerce-cart .custom_point,
        .wps_wpr_points_class,
        .custom_point {
            display: none !important;
        }
        /* 將「[ 移除 ]」文字連結樣式改為精美按鈕 */
        .woocommerce-remove-coupon,
        #wps_wpr_remove_cart_point,
        .wps_remove_virtual_coupon {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            background-color: #fee2e2 !important; /* 輕粉紅背景 */
            color: #ef4444 !important; /* 紅色文字 */
            border: 1px solid #fecaca !important;
            border-radius: 12px !important; /* 藥丸形圓角 */
            padding: 2px 10px !important;
            font-size: 11px !important;
            line-height: 1.3 !important;
            font-weight: 600 !important;
            text-decoration: none !important;
            margin-left: 8px !important;
            transition: all 0.2s ease-in-out !important;
            cursor: pointer !important;
            vertical-align: middle !important;
        }
        .woocommerce-remove-coupon:hover,
        #wps_wpr_remove_cart_point:hover,
        .wps_remove_virtual_coupon:hover {
            background-color: #ef4444 !important; /* 懸停變深紅 */
            color: #ffffff !important;
            border-color: #ef4444 !important;
        }
    </style>
    <?php
}

// ── 移除結帳頁上方「輸入優惠券代碼？」折疊欄
add_action( 'wp', 'ckc_remove_checkout_coupon_form' );
function ckc_remove_checkout_coupon_form() {
    remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
}

/* ================================================================
 * 效能重構：結帳頁「單一往返」套券
 *
 * 舊流程（慢）：點「套用」→ wc-ajax=apply_coupon（往返 1）→ 成功後
 * 前端再 trigger update_checkout → wc-ajax=update_order_review（往返 2）
 * → 金額才更新。兩次請求各自跑完整個購物車運算，使用者等待時間加倍。
 *
 * 新流程（快）：前端把折扣碼塞進 form.checkout 的隱藏欄位
 * ckc_apply_coupon_now，直接 trigger update_checkout；後端在
 * woocommerce_checkout_update_order_review（update_order_review 內、
 * calculate_totals 之前）先套券，同一次請求就回傳套券後的新金額。
 * ================================================================ */
add_action( 'woocommerce_checkout_update_order_review', 'ckc_checkout_apply_coupon_inline', 5 );
function ckc_checkout_apply_coupon_inline( $post_data ) {
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        return;
    }
    parse_str( (string) $post_data, $data );
    if ( empty( $data['ckc_apply_coupon_now'] ) ) {
        return;
    }
    $code = wc_format_coupon_code( sanitize_text_field( wp_unslash( $data['ckc_apply_coupon_now'] ) ) );
    if ( '' === $code || WC()->cart->has_discount( $code ) ) {
        return;
    }
    if ( WC()->cart->apply_coupon( $code ) ) {
        // 成功訊息交給前端 toast 呈現；清掉原生 success/notice，
        // 避免結帳表單頂端出現通知區塊並觸發自動捲動到頂部。
        // 失敗時不清（錯誤訊息保留給前端解析顯示）。
        wc_clear_notices();
    }
}

// 隨 update_order_review 回傳「目前已套用的券碼」，前端在 updated_checkout
// 事件中即可直接判斷套券成功與否，免再發任何請求。
// （非選擇器的 fragment key 在 checkout.js 端是安全的 no-op）
add_filter( 'woocommerce_update_order_review_fragments', 'ckc_fragments_applied_coupons' );
function ckc_fragments_applied_coupons( $fragments ) {
    $fragments['ckc_applied_coupons'] = ( function_exists( 'WC' ) && WC()->cart )
        ? array_values( array_map( 'wc_format_coupon_code', WC()->cart->get_applied_coupons() ) )
        : array();
    return $fragments;
}

// ── 結帳頁加入「我的優惠券」面板
add_action( 'woocommerce_before_checkout_form', 'ckc_checkout_coupon_panel', 5 );
function ckc_checkout_coupon_panel() {
    if ( ! is_user_logged_in() ) return;

    $user_id     = get_current_user_id();
    $claimed_ids = array_filter( array_map( 'intval', (array) get_user_meta( $user_id, '_ckc_claimed_coupons', true ) ) );

    // 已領取且未過期的券（供券卡片顯示；沒券時仍顯示折扣碼輸入框）
    $coupons = array();
    foreach ( $claimed_ids as $cid ) {
        $coupon = new WC_Coupon( $cid );
        if ( ! $coupon->get_id() ) continue;
        $wc_exp = $coupon->get_date_expires();
        if ( $wc_exp && $wc_exp->getTimestamp() < time() ) continue;
        $coupons[] = $coupon;
    }

    echo '<style>';
    echo 'details.ckc-coupon-details summary::-webkit-details-marker { display: none; }';
    echo 'details.ckc-coupon-details[open] .ckc-details-icon { transform: rotate(180deg); }';
    echo 'details.ckc-coupon-details summary { list-style: none; }';
    echo '</style>';
    echo '<div class="ckc-coupon-center" style="margin-bottom: 24px;">';
    echo '<details class="ckc-coupon-details" style="border: 1px solid #e2d2b3; border-radius: 8px; padding: 12px; background: #fffaf1; box-shadow: 0 1px 3px rgba(26,20,15,0.03); transition: all 0.3s ease;">';
    echo '<summary style="font-size: 15px; font-weight: 700; color: #1a140f; cursor: pointer; display: flex; align-items: center; justify-content: space-between; outline: none; margin: -12px; padding: 12px; border-radius: 8px; user-select: none;">';
    echo '<span style="display: flex; align-items: center; gap: 8px;">🎟️ 我的優惠券 <span style="font-size:12px;color:#8c7a64;font-weight:normal;">每筆訂單限用一張</span></span>';
    echo '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ckc-details-icon" style="color: #8c7a64; transition: transform 0.2s;"><polyline points="6 9 12 15 18 9"></polyline></svg>';
    echo '</summary>';
    echo '<div style="padding-top: 16px; margin-top: 4px; border-top: 1px dashed #e2d2b3;">';
    ?>
    <div class="ckc-checkout-coupon-form" style="display:flex;gap:8px;margin-bottom:12px;max-width:440px;">
        <input type="text" id="ckc-checkout-coupon-code" placeholder="輸入折扣碼" autocomplete="off"
               style="flex:1;min-width:0;border:1px solid #c9974a;border-radius:8px;padding:10px 12px;font-size:14px;background:#fff;">
        <button type="button" id="ckc-checkout-coupon-apply"
                style="border:none;background:#f86f69;color:#fff;border-radius:8px;padding:10px 22px;font-size:14px;font-weight:700;cursor:pointer;white-space:nowrap;">套用</button>
    </div>
    <?php
    if ( ! empty( $coupons ) ) {
        // 傳入已過濾的券清單；套用改由 AJAX 處理（見下方 script），不整頁跳轉
        ckc_render_coupon_cards( 'checkout', $coupons );
    }
    echo '</div>';
    echo '</details>';
    echo '</div>';

    ckc_checkout_coupon_ajax_script();
}

/**
 * 結帳頁折扣碼 AJAX 套用：以 Store API 套券，成功後彈出提示並更新結帳金額，
 * 不整頁重載、不自動滾動到頂部。折扣碼輸入框與券卡片「套用去結帳」共用同一流程。
 */
function ckc_checkout_coupon_ajax_script() {
    static $done = false;
    if ( $done ) {
        return;
    }
    $done = true;
    ?>
    <style>
    #ckc-coupon-toast{
        position:fixed; left:50%; bottom:84px; transform:translateX(-50%) translateY(20px);
        background:#16a34a; color:#fff; padding:14px 24px; border-radius:30px;
        font-size:15px; font-weight:700; line-height:1.4; z-index:2147483000;
        max-width:88vw; text-align:center; box-shadow:0 8px 24px rgba(0,0,0,.25);
        opacity:0; pointer-events:none; transition:opacity .25s ease, transform .25s ease;
    }
    #ckc-coupon-toast.ckc-show{ opacity:1; transform:translateX(-50%) translateY(0); }
    
    @keyframes ckc-spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    @keyframes ckc-price-highlight {
        0% { background-color: transparent; }
        15% { background-color: #d1fae5; color: #047857; transform: scale(1.05); }
        85% { background-color: #d1fae5; color: #047857; transform: scale(1.05); }
        100% { background-color: transparent; transform: scale(1); }
    }
    .ckc-price-highlight {
        display: inline-block;
        padding: 0 4px;
        border-radius: 4px;
        animation: ckc-price-highlight 0.6s ease-out;
    }
    /* 套用優惠券時，WooCommerce 原生的 update_checkout 會把整個結帳表單
       蓋上一層 blockUI 遮罩＋轉圈圈動畫，但那個動畫本身沒有任何文字。
       只在 body 有 ckc-applying-coupon 這個 class（套券進行中）時，於
       轉圈圈下方補一行文字說明，其餘場合（例如切換配送方式）觸發的
       blockUI 不受影響。 */
    body.ckc-applying-coupon .blockUI.blockOverlay::after {
        content: '套用優惠券中，請稍候…';
        position: fixed;
        left: 50%;
        top: 50%;
        transform: translate(-50%, 36px);
        background: rgba(18, 18, 18, 0.85);
        color: #fff;
        padding: 8px 18px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 700;
        white-space: nowrap;
        z-index: 2147483000;
        pointer-events: none;
    }
    </style>
    <script>
    jQuery(function($){
        // 行動版友善的浮出提示（非阻塞、底部置中、自動消失），取代 alert
        // persist=true 時不會自動消失（用於「處理中」提示，等到後續呼叫 ckcToast 更新內容再消失）
        function ckcToast(msg, isError, persist){
            var $t = $('#ckc-coupon-toast');
            if(!$t.length){ $t = $('<div id="ckc-coupon-toast" role="status" aria-live="polite"></div>').appendTo('body'); }
            $t.text(msg).css('background', isError ? '#b91c1c' : '#16a34a');
            requestAnimationFrame(function(){ $t.addClass('ckc-show'); });
            clearTimeout(window._ckcToastTimer);
            if (!persist) {
                window._ckcToastTimer = setTimeout(function(){ $t.removeClass('ckc-show'); }, 1500);
            }
        }

        // 套用後同步券卡片狀態（自動偵測或強制指定），
        // 修正前端手動移除折價券時介面沒有連動的問題。
        function ckcRefreshCouponCards(appliedCode){
            var appliedCodes = [];
            
            if (typeof appliedCode !== 'undefined' && appliedCode) {
                appliedCodes.push(appliedCode.toString().toUpperCase());
            } else {
                // 自動從畫面中偵測已套用的折價券 (限定在結帳明細或購物車主表單，避免被未更新的 mini-cart 影響)
                $('.woocommerce-checkout-review-order-table .cart-discount, .woocommerce-cart-form .cart-discount, .cart_totals .cart-discount').each(function(){
                    var cls = $(this).attr('class') || '';
                    var m = cls.match(/coupon-([a-zA-Z0-9_-]+)/i);
                    if (m && m[1]) { appliedCodes.push(m[1].toUpperCase()); }
                });
            }

            $('.ckc-coupon-card').each(function(){
                var $c = $(this);
                if($c.hasClass('is-expired')){ return; }
                var code = ($c.data('code') || '').toString().toUpperCase();
                var url  = $c.attr('data-apply-url') || '#';
                var $action = $c.find('.ckc-coupon-action');
                if($.inArray(code, appliedCodes) !== -1){
                    $c.addClass('is-applied');
                    $action.html('<span class="ckc-coupon-applied">✓ 已套用</span>');
                } else {
                    $c.removeClass('is-applied');
                    $action.html('<a href="'+url+'" class="ckc-coupon-apply" data-coupon-code="'+code+'">套用去結帳</a>');
                }
            });
        }

        // 監聽結帳頁/購物車更新事件，手動移除折價券時同步卡片狀態
        $(document.body).on('updated_checkout updated_cart_totals', function(){
            ckcRefreshCouponCards();
        });

        // ── 套券時防止 WooCommerce 原生自動捲動到頂部通知區 ──
        function ckcScrollLockOn(){
            window._ckcCouponScrollY = window.scrollY || window.pageYOffset;
            window._ckcCouponScrollLock = true;
        }
        function ckcScrollLockOff(){
            if(!window._ckcCouponScrollLock){ return; }
            window._ckcCouponScrollLock = false;
            $('html,body').stop(true,false);
            window.scrollTo(0, window._ckcCouponScrollY || 0);
        }
        if ($.scroll_to_notices) {
            var _ckcOrigScrollCoupon = $.scroll_to_notices;
            $.scroll_to_notices = function(el){
                if (window._ckcCouponScrollLock) { $('html,body').stop(true,false); return; }
                _ckcOrigScrollCoupon.call(this, el);
            };
        }

        function ckcBtnSpinner($btn){
            var isCardApply = $btn.hasClass('ckc-coupon-apply');
            $btn.prop('disabled', true).css({ 'opacity': '0.7', 'cursor': 'not-allowed', 'pointer-events': 'none' });
            $btn.html('<svg class="ckc-spin" style="animation: ckc-spin 1s linear infinite; width: 14px; height: 14px; margin-right: 6px; vertical-align: middle; display: inline-block;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>' + (isCardApply ? '套用中' : '驗證中'));
        }
        function ckcBtnRestore($btn, originalHtml){
            if ($btn && $btn.length) {
                $btn.prop('disabled', false).css({ 'opacity': '1', 'cursor': 'pointer', 'pointer-events': 'auto' }).html(originalHtml);
            }
        }
        function ckcApplySuccessUI(code, $btn){
            if ($btn && $btn.length) { $btn.css({ 'background': '#10b981', 'color': '#fff' }).html('✓ 成功'); }
            $('#ckc-checkout-coupon-code').val('');
            ckcRefreshCouponCards(code);
            
            // 金額折抵高亮特效 (Highlight)
            var $orderTotal = $('.order-total .woocommerce-Price-amount').first();
            if($orderTotal.length) {
                $orderTotal.addClass('ckc-price-highlight');
                // 動畫 (0.6秒) 完全結束後，才跳出折價券使用成功 Toast 提示（原為 1.5 秒，縮短以加快套用流程回饋）
                setTimeout(function(){
                    $orderTotal.removeClass('ckc-price-highlight');
                    ckcToast('折價券使用成功');
                }, 600);
            } else {
                ckcToast('折價券使用成功');
            }
        }

        /**
         * 效能重構：單一往返套券。
         * 舊流程要打兩次 AJAX（apply_coupon → update_order_review），等待時間加倍；
         * 新流程把折扣碼放進 form.checkout 的隱藏欄位 ckc_apply_coupon_now，
         * 直接 trigger update_checkout —— 後端在同一次 update_order_review 請求內
         * 先套券再算金額，一次往返完成套用＋金額更新。
         */
        function ckcApplyCoupon(code, $btn){
            code = $.trim(code || '');
            if(!code){ ckcToast('請輸入折扣碼', true); return; }
            if (window._ckcApplyPending) { return; } // 防連點

            var originalBtnText = ($btn && $btn.length) ? $btn.html() : '';
            if ($btn && $btn.length) { ckcBtnSpinner($btn); }
            // 立即給予提示（在結帳頁上，套用按鈕跟訂單金額可能不在同一個畫面範圍內，
            // 手機版尤其容易讓人覺得「按下去沒反應」）。持續顯示直到套用結果出來為止。
            ckcToast('正在核算最新金額，請稍候…', false, true);

            var $form = $('form.checkout');
            if ($form.length && typeof wc_checkout_params !== 'undefined') {
                // ── 快速路徑：單一往返 ──
                var $field = $form.find('input[name="ckc_apply_coupon_now"]');
                if(!$field.length){
                    $field = $('<input type="hidden" name="ckc_apply_coupon_now" />').appendTo($form);
                }
                $field.val(code);
                window._ckcApplyPending = { code: code, $btn: $btn, original: originalBtnText };
                ckcScrollLockOn();
                // WooCommerce 原生的 update_checkout 會用 blockUI 把整個結帳表單
                // 蓋上一層半透明遮罩＋轉圈圈動畫，但那個動畫本身完全沒有文字說明。
                // 手機版畫面小，使用者很容易看到畫面整個「糊掉」卻不知道發生什麼事，
                // 加上這個 class 讓 CSS 在轉圈圈旁邊補一行文字提示。
                $('body').addClass('ckc-applying-coupon');
                $(document.body).trigger('update_checkout');
                // 保險絲：逾時未收到 updated_checkout 就還原按鈕
                clearTimeout(window._ckcApplyTimer);
                window._ckcApplyTimer = setTimeout(function(){
                    var p = window._ckcApplyPending;
                    if (p) {
                        window._ckcApplyPending = null;
                        $('form.checkout').find('input[name="ckc_apply_coupon_now"]').val('');
                        ckcBtnRestore(p.$btn, p.original);
                        ckcScrollLockOff();
                        $('body').removeClass('ckc-applying-coupon');
                        ckcToast('連線逾時，請再試一次。', true);
                    }
                }, 15000);
                return;
            }

            // ── 後備路徑（checkout 參數不可用時）：原本的兩段式流程 ──
            var ajaxUrl = '/?wc-ajax=apply_coupon';
            var nonce = '';
            if (typeof wc_checkout_params !== 'undefined') {
                ajaxUrl = wc_checkout_params.wc_ajax_url.toString().replace( '%%endpoint%%', 'apply_coupon' );
                nonce = wc_checkout_params.apply_coupon_nonce;
            }
            $.ajax({
                type: 'POST',
                url: ajaxUrl,
                data: { security: nonce, coupon_code: code },
                dataType: 'html',
                success: function( htmlResponse ) {
                    if (htmlResponse.indexOf('woocommerce-error') !== -1) {
                        ckcBtnRestore($btn, originalBtnText);
                        var errMsg = $(htmlResponse).text().trim() || '折價券套用失敗，請確認代碼或使用條件。';
                        ckcToast(errMsg, true);
                    } else {
                        ckcApplySuccessUI(code, $btn);
                        $(document.body).trigger('update_checkout');
                    }
                },
                error: function() {
                    ckcBtnRestore($btn, originalBtnText);
                    ckcToast('套用時發生錯誤，請稍後再試。', true);
                }
            });
        }

        // 監聽結帳頁更新完畢事件：判斷單一往返套券的結果並收尾
        $(document.body).on('updated_checkout', function(e, data) {
            var p = window._ckcApplyPending;
            if (!p) { return; }
            window._ckcApplyPending = null;
            clearTimeout(window._ckcApplyTimer);
            $('form.checkout').find('input[name="ckc_apply_coupon_now"]').val('');
            $('body').removeClass('ckc-applying-coupon');

            // 判斷是否套用成功：優先用後端回傳的 fragments 清單，其次檢查訂單摘要 DOM
            var codeLower = (p.code || '').toString().toLowerCase();
            var applied;
            var list = data && data.fragments && data.fragments.ckc_applied_coupons;
            if (list && list.length !== undefined) {
                applied = false;
                for (var i = 0; i < list.length; i++) {
                    if ((list[i] || '').toString().toLowerCase() === codeLower) { applied = true; break; }
                }
            } else {
                var cls = 'coupon-' + codeLower.replace(/[^a-z0-9_-]/g, '');
                applied = $('.cart-discount.' + cls).length > 0;
            }

            if (applied) {
                ckcApplySuccessUI(p.code, p.$btn);
                // 移除 update_order_review 可能塞進表單頂端的通知（訊息已由 toast 呈現）
                $('.woocommerce-NoticeGroup-updateOrderReview').remove();
            } else {
                ckcBtnRestore(p.$btn, p.original);
                var $ng = $('.woocommerce-NoticeGroup-updateOrderReview');
                var errText = $.trim($ng.find('.woocommerce-error li').first().text())
                           || $.trim($ng.find('.woocommerce-error').first().text())
                           || '折價券套用失敗，請確認代碼或使用條件。';
                $ng.remove();
                ckcToast(errText, true);
            }
            ckcScrollLockOff();
        });

        // 1. 折扣碼輸入框：點「套用」或按 Enter
        $(document).on('click', '#ckc-checkout-coupon-apply', function(e){
            e.preventDefault();
            ckcApplyCoupon($('#ckc-checkout-coupon-code').val(), $(this));
        });
        $(document).on('keydown', '#ckc-checkout-coupon-code', function(e){
            if(e.key === 'Enter'){ e.preventDefault(); ckcApplyCoupon($(this).val(), $('#ckc-checkout-coupon-apply')); }
        });

        // 2. 券卡片「套用去結帳」：改走 AJAX，避免整頁跳轉回頂部
        $(document).on('click', '.ckc-coupon-apply', function(e){
            var code = $(this).data('coupon-code');
            if(code){ e.preventDefault(); ckcApplyCoupon(code, $(this)); }
        });
    });
    </script>
    <?php
}

// ──────────────────────────────────────────────────────────────────────────────
// 紅利點數折抵：自訂 AJAX 套用 / 取消 + WooCommerce fee 插件
// ──────────────────────────────────────────────────────────────────────────────

add_action( 'woocommerce_cart_calculate_fees', 'ckc_apply_points_as_fee' );
function ckc_apply_points_as_fee( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
    if ( ! is_user_logged_in() ) return;

    $pts = WC()->session ? (int) WC()->session->get( 'ckc_redeem_points', 0 ) : 0;
    if ( $pts <= 0 ) return;

    $wps      = get_option( 'wps_wpr_settings_gallery', [] );
    $pts_rate = (float) ( $wps['wps_wpr_cart_points_rate'] ?? 1 );
    $val_rate = (float) ( $wps['wps_wpr_cart_price_rate']  ?? 1 );
    if ( $pts_rate <= 0 ) $pts_rate = 1;
    if ( $val_rate <= 0 ) $val_rate = 1;

    $balance = ckc_pts_get_user_balance( get_current_user_id() );
    $pts     = min( $pts, $balance );
    if ( $pts <= 0 ) return;

    $discount = round( $pts * ( $val_rate / $pts_rate ), 2 );
    if ( $discount <= 0 ) return;

    $cart->add_fee( sprintf( '紅利點數折抵（%d 點）', $pts ), -$discount, false );
}

add_action( 'wp_ajax_ckc_points_apply', 'ckc_ajax_points_apply' );
function ckc_ajax_points_apply() {
    check_ajax_referer( 'ckc_points_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( [ 'msg' => '請先登入' ] );

    $pts = (int) ( $_POST['points'] ?? 0 );
    if ( $pts <= 0 ) wp_send_json_error( [ 'msg' => '點數無效' ] );

    $balance = ckc_pts_get_user_balance( get_current_user_id() );
    $pts     = min( $pts, $balance );
    if ( $pts <= 0 ) wp_send_json_error( [ 'msg' => '點數不足' ] );

    WC()->session->set( 'ckc_redeem_points', $pts );
    $wps      = get_option( 'wps_wpr_settings_gallery', [] );
    $pts_rate = (float) ( $wps['wps_wpr_cart_points_rate'] ?? 1 );
    $val_rate = (float) ( $wps['wps_wpr_cart_price_rate']  ?? 1 );
    if ( $pts_rate <= 0 ) $pts_rate = 1;
    if ( $val_rate <= 0 ) $val_rate = 1;
    $discount = (int) round( $pts * ( $val_rate / $pts_rate ) );
    wp_send_json_success( [ 'points' => $pts, 'discount' => $discount ] );
}

add_action( 'wp_ajax_ckc_points_remove', 'ckc_ajax_points_remove' );
function ckc_ajax_points_remove() {
    check_ajax_referer( 'ckc_points_nonce', 'nonce' );
    WC()->session->set( 'ckc_redeem_points', 0 );
    wp_send_json_success();
}

// ── 結帳頁加入「紅利點數」折抵面板
add_action( 'woocommerce_before_checkout_form', 'ckc_checkout_points_panel', 6 );
function ckc_checkout_points_panel() {
    $user_id = get_current_user_id();
    $points  = ckc_pts_get_user_balance( $user_id );

    if ( ! is_user_logged_in() ) return;
    if ( $points <= 0 ) return; // 沒有點數就不顯示

    // 取得點數兌換比例 (預設 1:1)
    $wps_wpr_settings = get_option( 'wps_wpr_settings_gallery', array() );
    $wps_wpr_cart_points_rate = isset( $wps_wpr_settings['wps_wpr_cart_points_rate'] ) ? (float) $wps_wpr_settings['wps_wpr_cart_points_rate'] : 1;
    $wps_wpr_cart_price_rate  = isset( $wps_wpr_settings['wps_wpr_cart_price_rate'] ) ? (float) $wps_wpr_settings['wps_wpr_cart_price_rate'] : 1;
    $wps_wpr_cart_points_rate = ( 0 == $wps_wpr_cart_points_rate ) ? 1 : $wps_wpr_cart_points_rate;
    $wps_wpr_cart_price_rate  = ( 0 == $wps_wpr_cart_price_rate ) ? 1 : $wps_wpr_cart_price_rate;

    $one_point_value = $wps_wpr_cart_price_rate / $wps_wpr_cart_points_rate;
    $cart_subtotal   = WC()->cart ? WC()->cart->get_subtotal() : 0;

    $points_needed   = $cart_subtotal / $one_point_value;
    $points_to_apply = min( $points, $points_needed );

    // 檢查目前是否已套用紅利折抵點數
    $applied_points = WC()->session ? (int) WC()->session->get( 'ckc_redeem_points', 0 ) : 0;
    $is_applied     = ( $applied_points > 0 );
    $ckc_pts_nonce  = wp_create_nonce( 'ckc_points_nonce' );

    ?>
    <div class="ckc-points-center" style="margin-bottom: 24px;">
        <div style="font-size: 15px; font-weight: 700; color: #1a140f; margin-bottom: 8px;">🪙 紅利點數折抵</div>

        <div class="ckc-points-card<?php echo $is_applied ? ' is-applied' : ''; ?>" style="display: flex; align-items: center; gap: 12px; background: #fffaf1; border: 1px dashed #c9974a; border-radius: 10px; padding: 12px 14px; max-width: 480px; position: relative; transition: all 0.25s ease;">
            <div class="ckc-points-left" style="text-align: center; min-width: 80px; border-right: 1px dashed #e2d2b3; padding-right: 12px;">
                <div class="ckc-points-value" style="font-size: 17px; font-weight: 800; color: #f86f69; line-height: 1.3;">
                    🪙 <?php echo $is_applied ? esc_html( $applied_points ) : esc_html( $points_to_apply ); ?> 點
                </div>
                <div class="ckc-points-worth" style="font-size: 11px; color: #8c7a64; margin-top: 2px;">
                    折抵 NT$<?php echo esc_html( number_format( ( $is_applied ? $applied_points : $points_to_apply ) * $one_point_value ) ); ?>
                </div>
            </div>

            <div class="ckc-points-body" style="flex: 1; min-width: 0;">
                <div class="ckc-points-title" style="font-size: 14px; font-weight: 700; color: #1a140f;">
                    <?php echo $is_applied ? '已套用紅利折抵' : '紅利點數全額折抵'; ?>
                </div>
                <div class="ckc-points-meta" style="font-size: 12px; color: #8c7a64; margin-top: 3px;">
                    您的帳戶餘額： <code><?php echo esc_html( $points ); ?> 點</code>
                </div>
            </div>

            <div class="ckc-points-action" style="white-space: nowrap;">
                <?php // 兩顆按鈕都輸出，由 CSS 依 .is-applied 顯示其一，AJAX 後前端切換即可即時刷新 ?>
                <button type="button" class="ckc-points-remove-btn" style="display: inline-block; background: #fee2e2; color: #ef4444; border: 1px solid #fecaca; border-radius: 16px; padding: 7px 16px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s;">
                    移除折抵
                </button>
                <button type="button" class="ckc-points-apply-btn" data-points="<?php echo (int) $points_to_apply; ?>" style="display: inline-block; background: #1a140f; color: #e3c586; border: none; border-radius: 16px; padding: 7px 16px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s;">
                    立即折抵
                </button>
            </div>
        </div>

        <div class="ckc-points-extra"<?php echo $is_applied ? ' style="display:none;"' : ''; ?>>
            <a href="javascript:void(0);" onclick="jQuery('#ckc-custom-points-wrap').toggle();" style="display: inline-block; margin-top: 8px; font-size: 12px; color: #f86f69; text-decoration: underline;">自訂折抵點數</a>

            <div id="ckc-custom-points-wrap" style="display: none; margin-top: 10px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <input type="number" min="1" max="<?php echo esc_attr( $points_to_apply ); ?>" id="ckc_custom_points_input" placeholder="輸入點數" style="height: 36px; border-radius: 20px; padding: 0 14px; border: 1px solid #e2d2b3; width: 120px;" />
                    <button type="button" class="ckc-points-custom-apply-btn" style="height: 36px; border-radius: 20px; padding: 0 16px; background: #1a140f; color: #e3c586; border: none; font-size: 12px; font-weight: 600; cursor: pointer; transition: background 0.2s;">套用</button>
                </div>
            </div>
        </div>

        <!-- AJAX nonce -->
        <input type="hidden" id="ckc-pts-nonce" value="<?php echo esc_attr( $ckc_pts_nonce ); ?>" />
        <input type="hidden" id="ckc-pts-max" value="<?php echo (int) $points_to_apply; ?>" />
    </div>

    <style>
    .ckc-points-card.is-applied { border-style: solid !important; border-color: #16a34a !important; background: #f0fdf4 !important; }
    .ckc-points-remove-btn:hover { background-color: #ef4444 !important; color: #fff !important; border-color: #ef4444 !important; }
    .ckc-points-apply-btn:hover { background-color: #f86f69 !important; }
    /* 依套用狀態顯示對應按鈕（AJAX 後前端切換 .is-applied 即可即時刷新） */
    .ckc-points-card:not(.is-applied) .ckc-points-remove-btn { display: none !important; }
    .ckc-points-card.is-applied .ckc-points-apply-btn { display: none !important; }
    /* 共用 toast（若折扣券面板未載入，這裡也定義一份） */
    #ckc-coupon-toast{ position:fixed; left:50%; bottom:84px; transform:translateX(-50%) translateY(20px); background:#16a34a; color:#fff; padding:14px 24px; border-radius:30px; font-size:15px; font-weight:700; line-height:1.4; z-index:2147483000; max-width:88vw; text-align:center; box-shadow:0 8px 24px rgba(0,0,0,.25); opacity:0; pointer-events:none; transition:opacity .25s ease, transform .25s ease; }
    #ckc-coupon-toast.ckc-show{ opacity:1; transform:translateX(-50%) translateY(0); }
    </style>
    <script>
    jQuery(function($){
        var _ckcScrollY    = 0;
        var _ckcScrollLock = false;
        var _ckcNonce   = $('#ckc-pts-nonce').val();
        var _ckcAjaxUrl = (typeof wc_checkout_params !== 'undefined' && wc_checkout_params.ajax_url)
                        ? wc_checkout_params.ajax_url : '/wp-admin/admin-ajax.php';

        // 覆寫 WooCommerce 原生的 scroll_to_notices，鎖定期間完全不捲動
        if ($.scroll_to_notices) {
            var _wcOrigScroll = $.scroll_to_notices;
            $.scroll_to_notices = function(el) {
                if (_ckcScrollLock) { $('html,body').stop(true,false); return; }
                _wcOrigScroll.call(this, el);
            };
        }

        function ckcLockScroll() {
            _ckcScrollY    = window.scrollY || window.pageYOffset;
            _ckcScrollLock = true;
        }
        function ckcUnlockScroll() {
            _ckcScrollLock = false;
            $('html,body').stop(true,false);
            window.scrollTo(0, _ckcScrollY);
        }
        function showToast(msg, bg) {
            var $t = $('#ckc-coupon-toast');
            if (!$t.length) { $t = $('<div id="ckc-coupon-toast" role="status" aria-live="polite"></div>').appendTo('body'); }
            $t.text(msg).css('background', bg);
            requestAnimationFrame(function(){ $t.addClass('ckc-show'); });
            setTimeout(function(){ $t.removeClass('ckc-show'); }, 2600);
        }

        // 即時切換面板狀態（不重載），修正「套用/移除後面板狀態不刷新」的問題
        function ckcPtsSetApplied(pts, discount) {
            var $card = $('.ckc-points-card');
            var d = (discount != null) ? discount : pts;
            $card.addClass('is-applied');
            $card.find('.ckc-points-value').html('🪙 ' + pts + ' 點');
            $card.find('.ckc-points-worth').text('折抵 NT$' + Number(d).toLocaleString());
            $card.find('.ckc-points-title').text('已套用紅利折抵');
            $('.ckc-points-extra').hide();
            $('#ckc-custom-points-wrap').hide();
        }
        function ckcPtsSetUnapplied() {
            var max = parseInt($('#ckc-pts-max').val(), 10) || 0;
            var $card = $('.ckc-points-card');
            $card.removeClass('is-applied');
            $card.find('.ckc-points-value').html('🪙 ' + max + ' 點');
            $card.find('.ckc-points-worth').text('折抵 NT$' + Number(max).toLocaleString());
            $card.find('.ckc-points-title').text('紅利點數全額折抵');
            $('.ckc-points-extra').show();
        }

        // 點數套用（立即全額 + 自訂）
        $(document).on('click', '.ckc-points-apply-btn, .ckc-points-custom-apply-btn', function(e){
            e.preventDefault();
            var pts = $(this).hasClass('ckc-points-apply-btn')
                      ? parseInt($('#ckc-pts-max').val(), 10)
                      : parseInt($('#ckc_custom_points_input').val(), 10);
            if (!(pts > 0)) { alert('請輸入有效的折抵點數。'); return; }

            ckcLockScroll();
            $.post(_ckcAjaxUrl, { action:'ckc_points_apply', points:pts, nonce:_ckcNonce },
                function(res) {
                    if (res && res.success) {
                        ckcPtsSetApplied(res.data && res.data.points ? res.data.points : pts, res.data && res.data.discount);
                        $(document.body).trigger('update_checkout');
                        $(document.body).one('updated_checkout', function(){
                            ckcUnlockScroll();
                            showToast('已套用紅利折抵', '#16a34a');
                        });
                    } else {
                        ckcUnlockScroll();
                        alert((res && res.data && res.data.msg) || '套用失敗，請重試。');
                    }
                }
            ).fail(function(){ ckcUnlockScroll(); alert('網路錯誤，請重試。'); });
        });

        // 點數移除
        $(document).on('click', '.ckc-points-remove-btn', function(e){
            e.preventDefault();
            ckcLockScroll();
            $.post(_ckcAjaxUrl, { action:'ckc_points_remove', nonce:_ckcNonce },
                function(res) {
                    if (res && res.success) {
                        ckcPtsSetUnapplied();
                        $(document.body).trigger('update_checkout');
                        $(document.body).one('updated_checkout', function(){
                            ckcUnlockScroll();
                            showToast('已取消套用紅利折抵', '#64748b');
                        });
                    } else {
                        ckcUnlockScroll();
                        alert('取消失敗，請重試。');
                    }
                }
            ).fail(function(){ ckcUnlockScroll(); alert('網路錯誤，請重試。'); });
        });
    });
    </script>
    <?php
}


// 後台選單整理：原本是獨立頂層選單，收整到「會員與行銷」頂層選單
// （ckc-referral-admin.php 註冊，slug ckc-referral-admin）底下，
// 優先權 22（緊接在分潤夥伴相關的 20、21 之後）。
// 三個子選單各自的 slug、渲染回呼都不變，只改掛載的父選單。
add_action( 'admin_menu', 'ckc_register_coupon_admin_menu', 22 );
function ckc_register_coupon_admin_menu() {
    // ── 子選單 A：折價券列表
    add_submenu_page(
        'ckc-referral-admin',
        '所有折價券',
        '📋 所有折價券',
        'manage_woocommerce',
        'ckc-coupon-center',
        'ckc_coupon_center_admin_page'
    );

    // ── 子選單 B：新增折價券（WooCommerce 新增頁）
    add_submenu_page(
        'ckc-referral-admin',
        '新增折價券',
        '➕ 新增折價券',
        'manage_woocommerce',
        'post-new.php?post_type=shop_coupon'
    );

    // ── 子選單 C：前往前台領券中心（外部連結）
    add_submenu_page(
        'ckc-referral-admin',
        '查看前台領券中心',
        '🔗 前台領券中心',
        'manage_woocommerce',
        'ckc-coupon-frontend',
        'ckc_coupon_frontend_redirect'
    );
}

// 3. 領券中心後台管理列表頁
function ckc_coupon_center_admin_page() {
    // 取得所有「啟用領取中心」的折價券
    $all_posts = get_posts( array(
        'post_type'      => 'shop_coupon',
        'post_status'    => array( 'publish', 'draft' ),
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ) );

    $claim_coupons  = array();
    $other_coupons  = array();
    foreach ( $all_posts as $post ) {
        if ( 'yes' === get_post_meta( $post->ID, '_ckc_coupon_claim_public', true ) ) {
            $claim_coupons[] = $post;
        } else {
            $other_coupons[] = $post;
        }
    }

    $new_url  = admin_url( 'post-new.php?post_type=shop_coupon' );
    $front_url = home_url( '/領券中心/' );
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">🎟️ 折價券管理 ─ 領券中心</h1>
        <a href="<?php echo esc_url( $new_url ); ?>" class="page-title-action">新增折價券</a>
        <a href="<?php echo esc_url( $front_url ); ?>" class="page-title-action" target="_blank" style="background:#ef0050;border-color:#ef0050;color:#fff;">查看前台</a>
        <hr class="wp-header-end">

        <style>
        .ckc-admin-table { width:100%; border-collapse:collapse; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,.07); border-radius:6px; overflow:hidden; margin-top:16px; }
        .ckc-admin-table th { background:#f8f9fa; color:#1e293b; font-weight:600; padding:10px 14px; text-align:left; border-bottom:2px solid #e2e8f0; font-size:13px; }
        .ckc-admin-table td { padding:10px 14px; border-bottom:1px solid #f1f5f9; font-size:13px; vertical-align:middle; }
        .ckc-admin-table tr:last-child td { border-bottom:none; }
        .ckc-admin-table tr:hover td { background:#fafbfc; }
        .ckc-badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; }
        .ckc-badge-on  { background:#dcfce7; color:#15803d; }
        .ckc-badge-off { background:#f1f5f9; color:#94a3b8; }
        .ckc-badge-pct { background:#fef9c3; color:#92400e; }
        .ckc-section-title { font-size:15px; font-weight:700; color:#1e293b; margin:24px 0 8px; display:flex; align-items:center; gap:8px; }
        .ckc-progress-bar { background:#e2e8f0; border-radius:4px; height:6px; width:80px; display:inline-block; vertical-align:middle; overflow:hidden; }
        .ckc-progress-fill { background:#ef0050; height:100%; border-radius:4px; }
        </style>

        <!-- 領券中心折價券列表 -->
        <div class="ckc-section-title">🎟️ 已上架至領券中心的折價券 <span style="color:#64748b;font-weight:400;font-size:13px;">(共 <?php echo count($claim_coupons); ?> 張)</span></div>

        <?php if ( empty( $claim_coupons ) ) : ?>
            <p style="color:#64748b;background:#f8fafc;padding:14px 18px;border-radius:8px;border:1px dashed #cbd5e1;">
                尚無上架到領券中心的折價券。<a href="<?php echo esc_url($new_url); ?>">新增一張</a>，並在「領券中心設定」頁籤勾選「啟用領取中心上架」。
            </p>
        <?php else : ?>
        <table class="ckc-admin-table">
            <thead>
                <tr>
                    <th>折價券名稱 / 代碼</th>
                    <th>優惠類型</th>
                    <th>類別</th>
                    <th>領取進度</th>
                    <th>到期日</th>
                    <th>狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $claim_coupons as $post ) :
                $coupon      = new WC_Coupon( $post->ID );
                $label       = get_post_meta( $post->ID, '_ckc_coupon_label', true );
                $category    = get_post_meta( $post->ID, '_ckc_coupon_claim_category', true );
                $deadline    = get_post_meta( $post->ID, '_ckc_coupon_claim_deadline', true );
                $inventory   = get_post_meta( $post->ID, '_ckc_coupon_claim_inventory', true );
                $claim_count = intval( get_post_meta( $post->ID, '_ckc_coupon_claim_count', true ) );
                $is_active   = ( 'publish' === $post->post_status );
                // 到期日使用 WooCommerce 原生 date_expires（與後台「折價券到期日」欄位同步）
                $wc_exp      = $coupon->get_date_expires();
                $deadline    = $wc_exp ? $wc_exp->date( 'Y/m/d' ) : '';

                // 計算進度
                $pct = '';
                $progress_html = '─';
                if ( $inventory !== '' && $inventory !== false && intval($inventory) > 0 ) {
                    $pct = min(100, round( ($claim_count / intval($inventory)) * 100 ));
                    $progress_html = '<div class="ckc-progress-bar"><div class="ckc-progress-fill" style="width:' . $pct . '%"></div></div> ' . $claim_count . ' / ' . $inventory . '張';
                } elseif ( $inventory === '' || $inventory === false ) {
                    $progress_html = $claim_count . ' 張（無上限）';
                }

                // 優惠文字
                $discount_type = $coupon->get_discount_type();
                $amount = floatval( $coupon->get_amount() );
                if ( 'percent' === $discount_type ) {
                    $type_text = '<span class="ckc-badge ckc-badge-pct">' . (100 - $amount) . ' 折</span>';
                } else {
                    $type_text = '<span class="ckc-badge ckc-badge-on">折 NT$' . number_format($amount) . '</span>';
                }

                $edit_url = get_edit_post_link( $post->ID );
                $deadline_text = $deadline ?: '無限制';
                ?>
                <tr>
                    <td>
                        <strong><a href="<?php echo esc_url($edit_url); ?>"><?php echo esc_html( $label ?: $post->post_title ); ?></a></strong><br>
                        <code style="font-size:11px;background:#f1f5f9;padding:1px 5px;border-radius:3px;"><?php echo esc_html( strtoupper( $post->post_title ) ); ?></code>
                    </td>
                    <td><?php echo $type_text; ?></td>
                    <td><?php echo $category ? esc_html($category) : '<span style="color:#94a3b8">─</span>'; ?></td>
                    <td><?php echo $progress_html; ?></td>
                    <td style="<?php echo ( $wc_exp && $wc_exp->getTimestamp() < time() ) ? 'color:#ef4444;' : ''; ?>">
                        <?php echo esc_html($deadline_text); ?>
                    </td>
                    <td>
                        <span class="ckc-badge <?php echo $is_active ? 'ckc-badge-on' : 'ckc-badge-off'; ?>">
                            <?php echo $is_active ? '上架中' : '草稿'; ?>
                        </span>
                    </td>
                    <td>
                        <a href="<?php echo esc_url($edit_url); ?>" class="button button-small">編輯</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <!-- 其他折價券列表 -->
        <?php if ( ! empty( $other_coupons ) ) : ?>
        <div class="ckc-section-title" style="margin-top:30px;">📋 其他折價券（未上架至領券中心）<span style="color:#64748b;font-weight:400;font-size:13px;">(共 <?php echo count($other_coupons); ?> 張)</span></div>
        <table class="ckc-admin-table">
            <thead>
                <tr>
                    <th>折價券代碼</th>
                    <th>優惠類型</th>
                    <th>已使用 / 上限</th>
                    <th>到期日</th>
                    <th>狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $other_coupons as $post ) :
                $coupon      = new WC_Coupon( $post->ID );
                $usage       = $coupon->get_usage_count();
                $limit       = $coupon->get_usage_limit();
                $expires     = $coupon->get_date_expires();
                $is_active   = ( 'publish' === $post->post_status );
                $edit_url    = get_edit_post_link( $post->ID );
                $label       = get_post_meta( $post->ID, '_ckc_coupon_label', true );
                $amount      = floatval( $coupon->get_amount() );
                $discount_type = $coupon->get_discount_type();
                if ( 'percent' === $discount_type ) {
                    $type_text = '<span class="ckc-badge ckc-badge-pct">' . (100 - $amount) . ' 折</span>';
                } else {
                    $type_text = '<span class="ckc-badge" style="background:#e0f2fe;color:#0369a1;">折 NT$' . number_format($amount) . '</span>';
                }
                ?>
                <tr>
                    <td>
                        <a href="<?php echo esc_url($edit_url); ?>">
                            <?php echo esc_html( $label ?: strtoupper($post->post_title) ); ?>
                        </a><br>
                        <code style="font-size:11px;background:#f1f5f9;padding:1px 5px;border-radius:3px;"><?php echo esc_html(strtoupper($post->post_title)); ?></code>
                    </td>
                    <td><?php echo $type_text; ?></td>
                    <td><?php echo $usage . ( $limit ? ' / ' . $limit : ' / ∞' ); ?></td>
                    <td><?php echo $expires ? $expires->date_i18n('Y/m/d') : '─'; ?></td>
                    <td><span class="ckc-badge <?php echo $is_active ? 'ckc-badge-on' : 'ckc-badge-off'; ?>"><?php echo $is_active ? '啟用' : '草稿'; ?></span></td>
                    <td><a href="<?php echo esc_url($edit_url); ?>" class="button button-small">編輯</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php
}

// 4. 前台重新導向頁（點「查看前台」跳轉）
function ckc_coupon_frontend_redirect() {
    wp_redirect( home_url( '/領券中心/' ) );
    exit;
}
