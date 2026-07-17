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
        update_post_meta( $coupon_id, '_ckc_coupon_claim_image',       '' );
        update_post_meta( $coupon_id, '_ckc_coupon_claim_banner',      '' );
    }

    // 清除相關快取
    delete_transient( 'ckc_coupon_page_checked' );

    // 標記已執行，不再重複
    update_option( 'ckc_demo_coupons_seeded', '1' );
}

/* ---------------- 後台：券編輯頁欄位 ---------------- */
add_action( 'woocommerce_coupon_options', 'ckc_coupon_admin_fields', 20, 2 );
function ckc_coupon_admin_fields( $coupon_id, $coupon ) {
    woocommerce_wp_checkbox( array(
        'id'          => '_ckc_coupon_public',
        'label'       => '顯示於領券中心',
        'description' => '勾選後，此券會出現在購物車「領券中心」與會員「專屬優惠券」頁，消費者可一鍵套用',
    ) );
    woocommerce_wp_text_field( array(
        'id'          => '_ckc_coupon_label',
        'label'       => '券面標題',
        'placeholder' => '例如：新客見面禮',
        'description' => '顯示在券卡片上的標題；留空則顯示折扣內容',
        'desc_tip'    => true,
    ) );
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
    $deadline     = isset( $_POST['_ckc_coupon_claim_deadline'] ) ? sanitize_text_field( wp_unslash( $_POST['_ckc_coupon_claim_deadline'] ) ) : '';
    $image_url    = isset( $_POST['_ckc_coupon_claim_image'] ) ? esc_url_raw( wp_unslash( $_POST['_ckc_coupon_claim_image'] ) ) : '';
    $banner_url   = isset( $_POST['_ckc_coupon_claim_banner'] ) ? esc_url_raw( wp_unslash( $_POST['_ckc_coupon_claim_banner'] ) ) : '';
    $description  = isset( $_POST['_ckc_coupon_claim_description'] ) ? wp_kses_post( wp_unslash( $_POST['_ckc_coupon_claim_description'] ) ) : '';
    $notes        = isset( $_POST['_ckc_coupon_claim_notes'] ) ? wp_kses_post( wp_unslash( $_POST['_ckc_coupon_claim_notes'] ) ) : '';

    update_post_meta( $coupon_id, '_ckc_coupon_claim_public', $claim_public );
    update_post_meta( $coupon_id, '_ckc_coupon_claim_inventory', $inventory );
    update_post_meta( $coupon_id, '_ckc_coupon_claim_count', $claim_count );
    update_post_meta( $coupon_id, '_ckc_coupon_claim_category', $category );
    update_post_meta( $coupon_id, '_ckc_coupon_claim_deadline', $deadline );
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
    ?>
    <div id="ckc_claim_center_coupon_data" class="panel woocommerce_options_panel">
        <div class="options_group">
            <?php
            // 1. 是否啟用領券中心
            $claim_public_val = get_post_meta( $coupon_id, '_ckc_coupon_claim_public', true );
            woocommerce_wp_checkbox( array(
                'id'          => '_ckc_coupon_claim_public',
                'label'       => '啟用領取中心上架',
                'value'       => $claim_public_val,
                'cbvalue'     => 'yes',
                'description' => '勾選後，此折價券將上架至「折價券領取中心」供會員公開領取',
            ) );

            // 2. 領取限額/總庫存
            $inventory_val = get_post_meta( $coupon_id, '_ckc_coupon_claim_inventory', true );
            woocommerce_wp_text_input( array(
                'id'          => '_ckc_coupon_claim_inventory',
                'label'       => '領取限額 (總庫存)',
                'value'       => $inventory_val,
                'placeholder' => '無限制請留空',
                'type'        => 'number',
                'description' => '當領取次數達到此上限時，前台會顯示「已搶光」且無法再領取',
                'desc_tip'    => true,
            ) );

            // 3. 目前已領取次數
            $claim_count = get_post_meta( $coupon_id, '_ckc_coupon_claim_count', true );
            woocommerce_wp_text_input( array(
                'id'          => '_ckc_coupon_claim_count',
                'label'       => '已領取次數',
                'value'       => $claim_count !== '' ? intval( $claim_count ) : 0,
                'type'        => 'number',
                'description' => '此為系統統計次數，可手動修正',
                'desc_tip'    => true,
            ) );

            // 4. 活動類別
            $category_val = get_post_meta( $coupon_id, '_ckc_coupon_claim_category', true );
            woocommerce_wp_text_field( array(
                'id'          => '_ckc_coupon_claim_category',
                'label'       => '活動類別',
                'value'       => $category_val,
                'placeholder' => '例如：全聯好康、保鮮收納',
                'description' => '用於前台分頁標籤篩選，留空則不分類',
                'desc_tip'    => true,
            ) );

            // 5. 領取截止期限
            $deadline = get_post_meta( $coupon_id, '_ckc_coupon_claim_deadline', true );
            woocommerce_wp_text_input( array(
                'id'                => '_ckc_coupon_claim_deadline',
                'value'             => esc_attr( $deadline ),
                'label'             => '領取截止期限',
                'placeholder'       => 'YYYY-MM-DD',
                'description'       => '前台顯示的領取期限，過期後會自動下架。格式：YYYY-MM-DD',
                'desc_tip'          => true,
                'class'             => 'date-picker',
                'custom_attributes' => array(
                    'pattern' => '[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])',
                ),
            ) );
            ?>
        </div>

        <div class="options_group">
            <?php
            // 6. 列表縮圖
            $image_url = get_post_meta( $coupon_id, '_ckc_coupon_claim_image', true );
            ?>
            <p class="form-field _ckc_coupon_claim_image_field">
                <label for="_ckc_coupon_claim_image">列表小縮圖</label>
                <input type="text" name="_ckc_coupon_claim_image" id="_ckc_coupon_claim_image" value="<?php echo esc_attr( $image_url ); ?>" style="width: 50%;" placeholder="請上傳或輸入圖片網址" />
                <button type="button" class="button ckc_upload_image_btn" data-target="_ckc_coupon_claim_image">上傳/選擇圖片</button>
                <?php echo wc_help_tip( '前台列表卡片上顯示的小方圖' ); ?>
            </p>

            <?php
            // 7. 詳情頁 Banner
            $banner_url = get_post_meta( $coupon_id, '_ckc_coupon_claim_banner', true );
            ?>
            <p class="form-field _ckc_coupon_claim_banner_field">
                <label for="_ckc_coupon_claim_banner">詳情頁大 Banner</label>
                <input type="text" name="_ckc_coupon_claim_banner" id="_ckc_coupon_claim_banner" value="<?php echo esc_attr( $banner_url ); ?>" style="width: 50%;" placeholder="請上傳或輸入圖片網址" />
                <button type="button" class="button ckc_upload_image_btn" data-target="_ckc_coupon_claim_banner">上傳/選擇圖片</button>
                <?php echo wc_help_tip( '點擊使用規則後，彈出視窗上方顯示的大 Banner 圖' ); ?>
            </p>
        </div>

        <div class="options_group">
            <?php
            // 8. 活動說明 (Textarea)
            $description_val = get_post_meta( $coupon_id, '_ckc_coupon_claim_description', true );
            woocommerce_wp_textarea_input( array(
                'id'          => '_ckc_coupon_claim_description',
                'label'       => '活動說明',
                'value'       => $description_val,
                'placeholder' => '請輸入折價券的活動說明...',
                'style'       => 'height: 100px;',
            ) );

            // 9. 使用限制與注意事項 (Textarea)
            woocommerce_wp_textarea_input( array(
                'id'          => '_ckc_coupon_claim_notes',
                'label'       => '使用限制與注意事項',
                'placeholder' => "1. 本券限於實體門市結帳使用...\n2. 本券為不記名，任何人持本券皆可使用...",
                'style'       => 'height: 120px;',
            ) );
            ?>
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

/* ---------------- 一鍵套用（?ckc_apply_coupon=CODE） ---------------- */
add_action( 'template_redirect', 'ckc_coupon_apply_from_url', 20 );
function ckc_coupon_apply_from_url() {
    if ( empty( $_GET['ckc_apply_coupon'] ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
        return;
    }
    $code   = wc_format_coupon_code( sanitize_text_field( wp_unslash( $_GET['ckc_apply_coupon'] ) ) );
    $coupon = new WC_Coupon( $code );
    
    // 允許套用「購物車領券」或是「領券中心」的公開券，避免暴力猜碼
    $is_public = 'yes' === $coupon->get_meta( '_ckc_coupon_public' ) || 'yes' === $coupon->get_meta( '_ckc_coupon_claim_public' );
    if ( ! $coupon->get_id() || ! $is_public ) {
        wp_safe_redirect( wc_get_cart_url() );
        exit;
    }
    if ( ! WC()->cart->has_discount( $code ) ) {
        WC()->cart->apply_coupon( $code ); // 失敗時 WooCommerce 會自行顯示原因通知
    }
    wp_safe_redirect( wc_get_cart_url() );
    exit;
}

/* ---------------- 券卡片渲染（購物車與帳號頁共用） ---------------- */
function ckc_render_coupon_cards( $context = 'cart' ) {
    $coupons = ckc_get_public_coupons();
    if ( empty( $coupons ) ) {
        if ( 'account' === $context ) {
            echo '<p style="color:#64748b;font-size:14px;">目前沒有可領取的優惠券，新券上架會顯示在這裡，敬請期待！</p>';
        }
        return;
    }
    $in_cart_page = function_exists( 'is_cart' ) && is_cart();
    ?>
    <div class="ckc-coupon-grid">
        <?php foreach ( $coupons as $coupon ) :
            $code    = $coupon->get_code();
            $label   = $coupon->get_meta( '_ckc_coupon_label' );
            $value   = ckc_coupon_value_text( $coupon );
            $min     = floatval( $coupon->get_minimum_amount() );
            $expires = $coupon->get_date_expires();
            $applied = WC()->cart ? WC()->cart->has_discount( $code ) : false;
            $apply_url = add_query_arg( 'ckc_apply_coupon', rawurlencode( $code ), wc_get_cart_url() );
            ?>
            <div class="ckc-coupon-card<?php echo $applied ? ' is-applied' : ''; ?>">
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
                    </div>
                </div>
                <div class="ckc-coupon-action">
                    <?php if ( $applied ) : ?>
                        <span class="ckc-coupon-applied">✓ 已套用</span>
                    <?php else : ?>
                        <a href="<?php echo esc_url( $apply_url ); ?>" class="ckc-coupon-apply"><?php echo $in_cart_page ? '立即套用' : '套用去結帳'; ?></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <style>
    .ckc-coupon-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 12px; margin: 14px 0; }
    .ckc-coupon-card { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px dashed #d6a878; border-radius: 10px; padding: 12px 14px; }
    .ckc-coupon-card.is-applied { border-style: solid; border-color: #16a34a; background: #f0fdf4; }
    .ckc-coupon-left { text-align: center; min-width: 76px; border-right: 1px dashed #e2e8f0; padding-right: 12px; }
    .ckc-coupon-value { font-size: 17px; font-weight: 800; color: #b91c1c; line-height: 1.3; }
    .ckc-coupon-min { font-size: 11px; color: #94a3b8; margin-top: 2px; }
    .ckc-coupon-body { flex: 1; min-width: 0; }
    .ckc-coupon-title { font-size: 14px; font-weight: 700; color: #1e293b; }
    .ckc-coupon-meta { font-size: 12px; color: #64748b; margin-top: 3px; }
    .ckc-coupon-meta code { background: #f1f5f9; padding: 1px 6px; border-radius: 4px; }
    .ckc-coupon-apply { display: inline-block; background: #7f6c60; color: #fff !important; border-radius: 16px; padding: 7px 16px; font-size: 12px; font-weight: 700; text-decoration: none; white-space: nowrap; }
    .ckc-coupon-applied { color: #16a34a; font-size: 13px; font-weight: 700; white-space: nowrap; }
    </style>
    <?php
}

/* ---------------- 購物車頁：領券中心 ---------------- */
add_action( 'woocommerce_before_cart', 'ckc_cart_coupon_center', 15 );
function ckc_cart_coupon_center() {
    $coupons = ckc_get_public_coupons();
    if ( empty( $coupons ) ) {
        return;
    }
    echo '<div class="ckc-coupon-center" style="margin-bottom: 20px;">';
    echo '<div style="font-size: 15px; font-weight: 700; color: #334155; margin-bottom: 4px;">🎟️ 領券中心</div>';
    ckc_render_coupon_cards( 'cart' );
    echo '</div>';
}

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

    // 驗證領取期限
    $deadline = $coupon->get_meta( '_ckc_coupon_claim_deadline' );
    if ( $deadline && strtotime( $deadline ) < strtotime( date('Y-m-d') ) ) {
        wp_send_json_error( array( 'message' => '此折價券領取期限已過！' ) );
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

    // 驗證領取期限
    $deadline = $coupon->get_meta( '_ckc_coupon_claim_deadline' );
    if ( $deadline && strtotime( $deadline ) < strtotime( date('Y-m-d') ) ) {
        wp_send_json_error( array( 'message' => '此折價券領取期限已過！' ) );
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
                <button type="button" class="ckc-nav-btn" data-tab="my-box">
                    我的券匣 <span class="ckc-box-count"><?php echo count( $claimed_ids ); ?></span>
                </button>
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
                            $deadline    = $coupon->get_meta( '_ckc_coupon_claim_deadline' );
                            $thumbnail   = $coupon->get_meta( '_ckc_coupon_claim_image' );
                            $banner      = $coupon->get_meta( '_ckc_coupon_claim_banner' );
                            $category    = $coupon->get_meta( '_ckc_coupon_claim_category' );
                            $desc        = $coupon->get_meta( '_ckc_coupon_claim_description' );
                            $notes       = $coupon->get_meta( '_ckc_coupon_claim_notes' );
                            $inventory   = $coupon->get_meta( '_ckc_coupon_claim_inventory' );
                            $claim_count = intval( $coupon->get_meta( '_ckc_coupon_claim_count' ) );

                            if ( empty( $thumbnail ) ) {
                                $thumbnail = get_template_directory_uri() . '/assets/images/default-coupon.png';
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
                                        <img src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php echo esc_attr( $title ); ?>" />
                                    </div>
                                    <a href="#" class="ckc-rules-trigger">使用規則</a>
                                </div>
                                
                                <div class="ckc-card-middle">
                                    <h3 class="ckc-card-title"><?php echo esc_html( $title ); ?></h3>
                                    <?php if ( $deadline ) : ?>
                                        <div class="ckc-card-deadline">領取期限：<?php echo esc_html( str_replace('-', '/', $deadline) ); ?></div>
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
                    <?php
                    $my_claimed_coupons = array();
                    if ( ! empty( $claimed_ids ) ) {
                        foreach ( $claimed_ids as $cid ) {
                            $c = new WC_Coupon( $cid );
                            if ( $c->get_id() ) {
                                $my_claimed_coupons[] = $c;
                            }
                        }
                    }

                    if ( empty( $my_claimed_coupons ) ) : ?>
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
                                $thumbnail = get_template_directory_uri() . '/assets/images/default-coupon.png';
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
                                        <img src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php echo esc_attr( $title ); ?>" />
                                    </div>
                                    <a href="#" class="ckc-rules-trigger">使用規則</a>
                                </div>
                                
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

    <!-- 領券中心專屬樣式 CSS (Modular styling) -->
    <style>
    #ckc-claim-center-container {
        max-width: 800px;
        margin: 0 auto;
        font-family: -apple-system, BlinkMacSystemFont, "Noto Sans TC", "PingFang TC", Arial, sans-serif;
        color: #334155;
        background-color: #f8fafc;
        padding: 20px;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    }
    
    .ckc-claim-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        flex-wrap: wrap;
        gap: 12px;
    }
    .ckc-claim-title {
        font-size: 22px;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
    }
    .ckc-claim-nav-buttons {
        display: flex;
        background: #e2e8f0;
        padding: 4px;
        border-radius: 30px;
    }
    .ckc-nav-btn {
        background: none !important;
        border: none !important;
        padding: 8px 20px !important;
        font-size: 14px !important;
        font-weight: 600 !important;
        color: #64748b !important;
        cursor: pointer !important;
        border-radius: 20px !important;
        transition: all 0.25s ease !important;
        box-shadow: none !important;
    }
    .ckc-nav-btn.active {
        background: #fff !important;
        color: #1e293b !important;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08) !important;
    }
    .ckc-box-count {
        background: #ef4444;
        color: #fff;
        padding: 1px 6px;
        font-size: 11px;
        border-radius: 10px;
        margin-left: 4px;
    }
    
    .ckc-search-bar-wrap {
        background: #fff;
        padding: 12px 16px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        margin-bottom: 24px;
    }
    .ckc-search-bar-inner {
        display: flex;
        gap: 8px;
    }
    .ckc-search-bar-inner input {
        flex: 1;
        height: 42px !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 8px !important;
        padding: 0 16px !important;
        font-size: 15px !important;
        background: #f8fafc !important;
        outline: none !important;
        transition: border-color 0.2s !important;
    }
    .ckc-search-bar-inner input:focus {
        border-color: #7c6767 !important;
    }
    .ckc-search-bar-inner button {
        height: 42px !important;
        line-height: 42px !important;
        padding: 0 24px !important;
        background: #7c6767 !important;
        color: #fff !important;
        border: none !important;
        border-radius: 8px !important;
        font-size: 15px !important;
        font-weight: 600 !important;
        cursor: pointer !important;
        transition: background 0.2s !important;
    }
    .ckc-search-bar-inner button:hover {
        background: #655248 !important;
    }
    
    .ckc-spa-panel {
        display: none;
    }
    .ckc-spa-panel.active {
        display: block;
    }
    
    .ckc-categories-filter-bar {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        padding-bottom: 12px;
        margin-bottom: 20px;
        scrollbar-width: none;
    }
    .ckc-categories-filter-bar::-webkit-scrollbar {
        display: none;
    }
    .ckc-cat-tab {
        background: #fff !important;
        border: 1px solid #e2e8f0 !important;
        color: #475569 !important;
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
        background: #ef0050 !important; /* Matches PX Pay style */
        border-color: #ef0050 !important;
        color: #fff !important;
    }
    
    .ckc-coupon-cards-grid {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    .ckc-no-coupons {
        text-align: center;
        padding: 40px 20px;
        color: #64748b;
        font-size: 15px;
    }
    
    .ckc-coupon-item-card {
        display: flex;
        align-items: center;
        background: #fff;
        border-radius: 12px;
        padding: 16px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.02);
        border: 1px solid #f1f5f9;
        transition: transform 0.2s, box-shadow 0.2s;
        gap: 16px;
    }
    .ckc-coupon-item-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0,0,0,0.04);
    }
    
    .ckc-card-left {
        display: flex;
        flex-direction: column;
        align-items: center;
        min-width: 90px;
        gap: 8px;
    }
    .ckc-card-img-wrap {
        width: 80px;
        height: 80px;
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid #f1f5f9;
    }
    .ckc-card-img-wrap img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .ckc-rules-trigger {
        font-size: 12px;
        color: #64748b;
        text-decoration: underline;
        font-weight: 500;
        cursor: pointer;
    }
    .ckc-rules-trigger:hover {
        color: #ef0050;
    }
    
    .ckc-card-middle {
        flex: 1;
        min-width: 0;
    }
    .ckc-card-title {
        font-size: 16px;
        font-weight: 700;
        color: #1e293b;
        margin: 0 0 6px 0;
        line-height: 1.4;
        cursor: pointer;
    }
    .ckc-card-title:hover {
        color: #ef0050;
    }
    .ckc-card-deadline {
        font-size: 13px;
        color: #64748b;
    }
    .ckc-card-code {
        font-size: 13px;
        color: #64748b;
    }
    .ckc-card-code code {
        background: #f1f5f9;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 12px;
        font-family: monospace;
    }
    
    .ckc-card-right {
        display: flex;
        justify-content: flex-end;
        min-width: 94px;
    }
    .ckc-claim-action-btn,
    .ckc-apply-action-btn {
        display: inline-block !important;
        text-align: center !important;
        width: 94px !important;
        height: 42px !important;
        line-height: 42px !important;
        padding: 0 !important;
        background: #ef0050 !important;
        color: #fff !important;
        border: none !important;
        border-radius: 20px !important;
        font-size: 14px !important;
        font-weight: 700 !important;
        cursor: pointer !important;
        transition: background 0.2s, opacity 0.2s !important;
        white-space: nowrap !important;
        text-decoration: none !important;
        box-shadow: 0 2px 4px rgba(239,0,80,0.15) !important;
    }
    .ckc-claim-action-btn.claimed {
        background: #f1f5f9 !important;
        color: #94a3b8 !important;
        cursor: default !important;
        box-shadow: none !important;
    }
    .ckc-claim-action-btn.sold-out {
        background: #f1f5f9 !important;
        color: #cbd5e1 !important;
        cursor: default !important;
        box-shadow: none !important;
    }
    .ckc-apply-action-btn {
        background: #7c6767 !important;
        box-shadow: 0 2px 4px rgba(124,103,103,0.15) !important;
    }
    .ckc-apply-action-btn:hover {
        background: #655248 !important;
    }
    .ckc-claim-action-btn:not(.claimed):not(.sold-out):hover {
        background: #d00045 !important;
    }
    
    /* 滑動彈出說明視窗 (Bottom/Slide-up panel) */
    .ckc-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
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
        background: #fff;
        width: 100%;
        max-width: 500px;
        border-radius: 20px 20px 0 0;
        display: flex;
        flex-direction: column;
        max-height: 85vh;
        transform: translateY(100%);
        transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        box-shadow: 0 -10px 30px rgba(0,0,0,0.15);
    }
    .ckc-modal-overlay.open .ckc-modal-sheet {
        transform: translateY(0);
    }
    
    .ckc-modal-header {
        display: flex;
        align-items: center;
        padding: 16px;
        border-bottom: 1px solid #f1f5f9;
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
        fill: #475569;
    }
    .ckc-modal-header-title {
        font-size: 17px;
        font-weight: 700;
        color: #1e293b;
        flex: 1;
        text-align: center;
        margin-right: 32px;
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
        background: #f1f5f9;
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
        font-weight: 800;
        color: #1e293b;
        margin: 0 0 12px 0;
        line-height: 1.4;
    }
    .ckc-modal-date-info {
        background: #f8fafc;
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
    }
    .ckc-date-row {
        margin-bottom: 4px;
    }
    .ckc-date-row:last-child {
        margin-bottom: 0;
    }
    .ckc-date-label {
        font-weight: 600;
        color: #64748b;
    }
    .ckc-modal-section-group {
        margin-bottom: 20px;
    }
    .ckc-section-heading {
        font-size: 15px;
        font-weight: 700;
        color: #334155;
        border-left: 3px solid #ef0050;
        padding-left: 8px;
        margin: 0 0 10px 0;
    }
    .ckc-section-text {
        font-size: 14px;
        line-height: 1.7;
        color: #475569;
        white-space: pre-line;
    }
    
    .ckc-modal-footer {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 16px 20px;
        background: rgba(255,255,255,0.9);
        backdrop-filter: blur(10px);
        border-top: 1px solid #f1f5f9;
        display: flex;
        justify-content: center;
        z-index: 10;
    }
    .ckc-modal-claim-btn {
        width: 100% !important;
        height: 46px !important;
        background: #ef0050 !important;
        color: #fff !important;
        border: none !important;
        border-radius: 25px !important;
        font-size: 16px !important;
        font-weight: 700 !important;
        cursor: pointer !important;
        transition: background 0.2s !important;
        box-shadow: 0 4px 12px rgba(239,0,80,0.2) !important;
    }
    .ckc-modal-claim-btn.claimed {
        background: #cbd5e1 !important;
        color: #94a3b8 !important;
        cursor: default !important;
        box-shadow: none !important;
    }
    .ckc-modal-claim-btn.sold-out {
        background: #e2e8f0 !important;
        color: #cbd5e1 !important;
        cursor: default !important;
        box-shadow: none !important;
    }
    
    /* 吐司小提示 Toast */
    .ckc-toast-box {
        position: fixed;
        bottom: 40px;
        left: 50%;
        transform: translateX(-50%) translateY(100px);
        background: rgba(30,30,30,0.95);
        color: #fff;
        padding: 12px 28px;
        border-radius: 30px;
        font-size: 14px;
        font-weight: 500;
        z-index: 100000;
        box-shadow: 0 5px 20px rgba(0,0,0,0.2);
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
    </style>

    <!-- 前端互動 AJAX/SPA 邏輯 JavaScript -->
    <script type="text/javascript">
    var ckcClaimNonce = '<?php echo esc_js( wp_create_nonce( 'ckc_claim_nonce' ) ); ?>';
    jQuery(document).ready(function($) {
        // 切換頁籤 (SPA 分頁動作)
        $('.ckc-nav-btn').on('click', function() {
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
 * 後台：自訂「折價券管理」頂層選單
 * 取代原本隱藏的 WooCommerce 折價券連結，提供友善的後台入口
 * ================================================================================ */

// 1. 移除原始 WooCommerce 折價券選單（多層兜底，確保完全移除）
add_action( 'admin_menu', 'ckc_remove_woocommerce_coupons_menu', 9999 );
function ckc_remove_woocommerce_coupons_menu() {
    global $submenu;

    // 方法 A：標準 WordPress API
    remove_submenu_page( 'woocommerce', 'edit.php?post_type=shop_coupon' );
    remove_submenu_page( 'woocommerce-marketing', 'edit.php?post_type=shop_coupon' );
    remove_menu_page( 'edit.php?post_type=shop_coupon' );

    // 方法 B：直接操作全域 $submenu 陣列（最可靠，繞過 WC 版本差異）
    $menus_to_clean = array( 'woocommerce', 'woocommerce-marketing' );
    foreach ( $menus_to_clean as $parent_slug ) {
        if ( ! isset( $submenu[ $parent_slug ] ) ) {
            continue;
        }
        foreach ( $submenu[ $parent_slug ] as $key => $item ) {
            // $item[2] 是該子選單的 slug/URL
            if (
                isset( $item[2] ) && (
                    $item[2] === 'edit.php?post_type=shop_coupon' ||
                    strpos( (string) $item[2], 'post_type=shop_coupon' ) !== false
                )
            ) {
                unset( $submenu[ $parent_slug ][ $key ] );
            }
        }
    }
}

// 1b. CSS 兜底：萬一 JS 渲染後還看得到，用 CSS 強制隱藏
add_action( 'admin_head', 'ckc_hide_coupon_menu_css' );
function ckc_hide_coupon_menu_css() {
    ?>
    <style id="ckc-hide-wc-coupon">
        /* 隱藏 WooCommerce 子選單中的折價券連結 */
        #adminmenu a[href="edit.php?post_type=shop_coupon"],
        #adminmenu li:has(a[href="edit.php?post_type=shop_coupon"]) {
            display: none !important;
        }
    </style>
    <?php
}

// 1c. 若管理員直接進入 shop_coupon 列表，重新導向到自訂管理頁
add_action( 'current_screen', 'ckc_redirect_coupon_list_to_custom_page' );
function ckc_redirect_coupon_list_to_custom_page() {
    $screen = get_current_screen();
    if ( ! $screen ) return;
    // 攔截 WooCommerce 折價券列表頁（edit.php?post_type=shop_coupon）
    if ( 'edit-shop_coupon' === $screen->id && ! isset( $_GET['ckc_bypass'] ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=ckc-coupon-center' ) );
        exit;
    }
}


// 2. 新增自訂「折價券管理」頂層選單（放在 WooCommerce 之後）
add_action( 'admin_menu', 'ckc_register_coupon_admin_menu', 25 );
function ckc_register_coupon_admin_menu() {
    // 頂層選單 → 直接導向「領券中心」折價券列表頁（加 meta_key 篩選）
    add_menu_page(
        '折價券管理',                              // 頁面標題
        '🎟️ 折價券管理',                          // 選單標題
        'manage_woocommerce',                    // 需要 WooCommerce 管理權限
        'ckc-coupon-center',                     // 選單 slug
        'ckc_coupon_center_admin_page',          // 渲染函數
        '',                                      // 圖示（使用 emoji 在標題中）
        56                                       // 位置：WooCommerce(55) 之後
    );

    // 子選單 1：領券中心設定（列表 + 快速管理）
    add_submenu_page(
        'ckc-coupon-center',
        '領券中心折價券',
        '領券中心折價券',
        'manage_woocommerce',
        'ckc-coupon-center',
        'ckc_coupon_center_admin_page'
    );

    // 子選單 2：新增折價券（直接連到 WooCommerce 新增頁）
    add_submenu_page(
        'ckc-coupon-center',
        '新增折價券',
        '➕ 新增折價券',
        'manage_woocommerce',
        'post-new.php?post_type=shop_coupon'
    );

    // 子選單 3：前往領券中心頁面（外部連結）
    add_submenu_page(
        'ckc-coupon-center',
        '查看前台領券中心',
        '🔗 查看前台',
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
                    <th>截止期限</th>
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
                $deadline_text = $deadline ? str_replace('-', '/', $deadline) : '無限制';
                ?>
                <tr>
                    <td>
                        <strong><a href="<?php echo esc_url($edit_url); ?>"><?php echo esc_html( $label ?: $post->post_title ); ?></a></strong><br>
                        <code style="font-size:11px;background:#f1f5f9;padding:1px 5px;border-radius:3px;"><?php echo esc_html( strtoupper( $post->post_title ) ); ?></code>
                    </td>
                    <td><?php echo $type_text; ?></td>
                    <td><?php echo $category ? esc_html($category) : '<span style="color:#94a3b8">─</span>'; ?></td>
                    <td><?php echo $progress_html; ?></td>
                    <td style="<?php echo ($deadline && strtotime($deadline) < time()) ? 'color:#ef4444;' : ''; ?>">
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
