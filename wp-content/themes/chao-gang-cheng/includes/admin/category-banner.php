<?php
/**
 * 分類頁／商店主頁 Banner 圖片管理
 *
 * 目的：原本商品分類檔案頁（/product-category/xxx/）頂部的 banner 圖片
 * 只能沿用 WooCommerce 分類本身的「縮圖」（尺寸通常是給小圖示用，不適合
 * 直接當滿版橫幅背景），若沒設縮圖就會落到程式碼裡寫死的少數分類專屬圖
 * （tickets/frozen/side-dishes），其他分類一律用同一張通用圖，無法從
 * 後台個別調整。商店主頁（/shop/）則是每次從所有分類縮圖中隨機抽一張。
 *
 * 這裡新增：
 *   1. 商品分類「新增／編輯分類」頁面：獨立於分類縮圖之外的專屬 Banner
 *      圖片欄位（term meta：_ckc_category_banner_id）。
 *   2. 商店主頁（Shop 頁面）編輯畫面：固定的 Banner 圖片欄位（post meta：
 *      _ckc_shop_banner_id），取代原本隨機抽圖的做法。
 *
 * 兩者都只新增「優先於現有邏輯」的來源，沒有設定時會照舊 fallback 到
 * 原本 woocommerce.php 裡的邏輯（分類縮圖 → 少數分類寫死的圖 → 通用圖），
 * 不影響尚未設定過的分類／商店頁。
 *
 * @package Chao_Gang_Cheng
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* =========================================================================
 * 1. 商品分類 Banner 圖片欄位
 * ========================================================================= */

/**
 * 「新增分類」畫面（沒有 term_id，欄位要用 div 包，不是 tr）
 */
add_action( 'product_cat_add_form_fields', 'ckc_category_banner_add_field' );
function ckc_category_banner_add_field( $taxonomy ) {
    ?>
    <div class="form-field ckc-category-banner-field">
        <label for="ckc_category_banner_id">分類頁 Banner 圖片</label>
        <input type="hidden" name="ckc_category_banner_id" id="ckc_category_banner_id" value="">
        <div class="ckc-cat-banner-preview-wrap" style="margin-bottom:8px;">
            <img class="ckc-cat-banner-preview" src="" alt="" style="display:none;max-width:300px;max-height:160px;border-radius:6px;border:1px solid #ddd;">
        </div>
        <p>
            <button type="button" class="button ckc-cat-banner-pick">選擇圖片</button>
            <button type="button" class="button ckc-cat-banner-clear" style="display:none;">清除</button>
        </p>
        <p class="description">獨立於分類縮圖之外的專屬橫幅圖片，用於分類頁最上方的大圖橫幅。留空則沿用分類縮圖或系統預設圖。建議尺寸：1600×500px 以上的橫幅比例。</p>
    </div>
    <?php
    wp_nonce_field( 'ckc_category_banner_save', 'ckc_category_banner_nonce' );
}

/**
 * 「編輯分類」畫面（有 term_id，欄位要用 tr/th/td 包，符合 WP 內建表格版面）
 */
add_action( 'product_cat_edit_form_fields', 'ckc_category_banner_edit_field' );
function ckc_category_banner_edit_field( $term, $taxonomy ) {
    $banner_id  = absint( get_term_meta( $term->term_id, '_ckc_category_banner_id', true ) );
    $banner_url = $banner_id ? wp_get_attachment_url( $banner_id ) : '';
    ?>
    <tr class="form-field ckc-category-banner-field">
        <th scope="row"><label for="ckc_category_banner_id">分類頁 Banner 圖片</label></th>
        <td>
            <input type="hidden" name="ckc_category_banner_id" id="ckc_category_banner_id" value="<?php echo esc_attr( $banner_id ); ?>">
            <div class="ckc-cat-banner-preview-wrap" style="margin-bottom:8px;">
                <img class="ckc-cat-banner-preview" src="<?php echo esc_url( $banner_url ); ?>" alt="" style="<?php echo $banner_url ? '' : 'display:none;'; ?>max-width:300px;max-height:160px;border-radius:6px;border:1px solid #ddd;">
            </div>
            <p>
                <button type="button" class="button ckc-cat-banner-pick">選擇圖片</button>
                <button type="button" class="button ckc-cat-banner-clear" style="<?php echo $banner_url ? '' : 'display:none;'; ?>">清除</button>
            </p>
            <p class="description">獨立於分類縮圖之外的專屬橫幅圖片，用於分類頁最上方的大圖橫幅。留空則沿用分類縮圖或系統預設圖。建議尺寸：1600×500px 以上的橫幅比例。</p>
            <?php wp_nonce_field( 'ckc_category_banner_save', 'ckc_category_banner_nonce' ); ?>
        </td>
    </tr>
    <?php
}

/**
 * 儲存：新增分類／編輯分類都會觸發對應的 hook
 */
add_action( 'created_product_cat', 'ckc_category_banner_save' );
add_action( 'edited_product_cat', 'ckc_category_banner_save' );
function ckc_category_banner_save( $term_id ) {
    if ( ! isset( $_POST['ckc_category_banner_nonce'] ) || ! wp_verify_nonce( $_POST['ckc_category_banner_nonce'], 'ckc_category_banner_save' ) ) {
        return;
    }
    if ( ! current_user_can( 'manage_product_terms' ) ) {
        return;
    }
    $banner_id = isset( $_POST['ckc_category_banner_id'] ) ? absint( $_POST['ckc_category_banner_id'] ) : 0;
    if ( $banner_id > 0 ) {
        update_term_meta( $term_id, '_ckc_category_banner_id', $banner_id );
    } else {
        delete_term_meta( $term_id, '_ckc_category_banner_id' );
    }
}

/**
 * 取得某個商品分類的專屬 Banner 圖片網址（給前台 woocommerce.php 用）
 *
 * @param int $term_id
 * @return string 沒設定時回傳空字串，由呼叫端自行 fallback
 */
function ckc_get_category_banner_url( $term_id ) {
    $banner_id = absint( get_term_meta( $term_id, '_ckc_category_banner_id', true ) );
    if ( ! $banner_id ) {
        return '';
    }
    $url = wp_get_attachment_url( $banner_id );
    return $url ? $url : '';
}

/* =========================================================================
 * 2. 商店主頁（Shop 頁）Banner 圖片欄位
 * ========================================================================= */

add_action( 'add_meta_boxes', 'ckc_shop_banner_add_meta_box' );
function ckc_shop_banner_add_meta_box() {
    if ( ! function_exists( 'wc_get_page_id' ) ) {
        return;
    }
    $shop_page_id = wc_get_page_id( 'shop' );
    if ( ! $shop_page_id || $shop_page_id <= 0 ) {
        return;
    }
    global $post;
    if ( ! isset( $post->ID ) || (int) $post->ID !== (int) $shop_page_id ) {
        return;
    }
    add_meta_box(
        'ckc_shop_banner_box',
        '商店主頁 Banner 圖片',
        'ckc_shop_banner_meta_box_html',
        'page',
        'side',
        'default'
    );
}

function ckc_shop_banner_meta_box_html( $post ) {
    $banner_id  = absint( get_post_meta( $post->ID, '_ckc_shop_banner_id', true ) );
    $banner_url = $banner_id ? wp_get_attachment_url( $banner_id ) : '';
    wp_nonce_field( 'ckc_shop_banner_save', 'ckc_shop_banner_nonce' );
    ?>
    <input type="hidden" name="ckc_shop_banner_id" id="ckc_shop_banner_id" value="<?php echo esc_attr( $banner_id ); ?>">
    <div class="ckc-shop-banner-preview-wrap" style="margin-bottom:8px;">
        <img class="ckc-shop-banner-preview" src="<?php echo esc_url( $banner_url ); ?>" alt="" style="<?php echo $banner_url ? '' : 'display:none;'; ?>max-width:100%;border-radius:6px;border:1px solid #ddd;">
    </div>
    <p>
        <button type="button" class="button ckc-shop-banner-pick" style="width:100%;">選擇圖片</button>
        <button type="button" class="button ckc-shop-banner-clear" style="width:100%;margin-top:6px;<?php echo $banner_url ? '' : 'display:none;'; ?>">清除</button>
    </p>
    <p class="description">商店主頁（/shop/）最上方的橫幅圖片。留空則使用系統預設圖（原本「隨機抽一張分類縮圖」的做法已取消，改為固定設定）。</p>
    <?php
}

add_action( 'save_post_page', 'ckc_shop_banner_save' );
function ckc_shop_banner_save( $post_id ) {
    if ( ! isset( $_POST['ckc_shop_banner_nonce'] ) || ! wp_verify_nonce( $_POST['ckc_shop_banner_nonce'], 'ckc_shop_banner_save' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_page', $post_id ) ) {
        return;
    }
    $banner_id = isset( $_POST['ckc_shop_banner_id'] ) ? absint( $_POST['ckc_shop_banner_id'] ) : 0;
    if ( $banner_id > 0 ) {
        update_post_meta( $post_id, '_ckc_shop_banner_id', $banner_id );
    } else {
        delete_post_meta( $post_id, '_ckc_shop_banner_id' );
    }
}

/**
 * 取得商店主頁的固定 Banner 圖片網址（給前台 woocommerce.php 用）
 *
 * @return string 沒設定時回傳空字串，由呼叫端自行 fallback
 */
function ckc_get_shop_banner_url() {
    if ( ! function_exists( 'wc_get_page_id' ) ) {
        return '';
    }
    $shop_page_id = wc_get_page_id( 'shop' );
    if ( ! $shop_page_id || $shop_page_id <= 0 ) {
        return '';
    }
    $banner_id = absint( get_post_meta( $shop_page_id, '_ckc_shop_banner_id', true ) );
    if ( ! $banner_id ) {
        return '';
    }
    $url = wp_get_attachment_url( $banner_id );
    return $url ? $url : '';
}

/* =========================================================================
 * 3. 後台圖片選擇器 JS（wp.media），兩個畫面共用同一套邏輯，
 *    只是綁定的 class 前綴不同（.ckc-cat-banner-* / .ckc-shop-banner-*）
 * ========================================================================= */
add_action( 'admin_enqueue_scripts', 'ckc_category_banner_admin_assets' );
function ckc_category_banner_admin_assets( $hook ) {
    $is_term_screen = in_array( $hook, array( 'edit-tags.php', 'term.php' ), true )
        && isset( $_GET['taxonomy'] ) && 'product_cat' === $_GET['taxonomy'];

    $is_shop_page_screen = false;
    if ( 'post.php' === $hook && isset( $_GET['post'] ) && function_exists( 'wc_get_page_id' ) ) {
        $shop_page_id = wc_get_page_id( 'shop' );
        if ( $shop_page_id && (int) $_GET['post'] === (int) $shop_page_id ) {
            $is_shop_page_screen = true;
        }
    }

    if ( ! $is_term_screen && ! $is_shop_page_screen ) {
        return;
    }

    wp_enqueue_media();

    $js = <<<'JS'
    jQuery(function($){
        // 分類編輯／新增畫面的欄位共同外層是 .ckc-category-banner-field，
        // 商店頁 meta box 的外層是 #ckc_shop_banner_box——用同一組委派事件
        // 處理兩邊的「選擇圖片」／「清除」按鈕，靠 closest() 找到各自範圍內
        // 的隱藏欄位／預覽圖，避免兩份幾乎一樣的程式碼。
        $(document).on('click', '.ckc-cat-banner-pick, .ckc-shop-banner-pick', function(e){
            e.preventDefault();
            var $btn   = $(this);
            var $scope = $btn.closest('.ckc-category-banner-field, #ckc_shop_banner_box');
            if (!$scope.length) { return; }
            var frame = wp.media({ title: '選擇 Banner 圖片', multiple: false, library: { type: 'image' } });
            frame.on('select', function(){
                var att = frame.state().get('selection').first().toJSON();
                $scope.find('input[name="ckc_category_banner_id"], input[name="ckc_shop_banner_id"]').val(att.id);
                $scope.find('img.ckc-cat-banner-preview, img.ckc-shop-banner-preview').attr('src', att.url).show();
                $scope.find('.ckc-cat-banner-clear, .ckc-shop-banner-clear').show();
            });
            frame.open();
        });
        $(document).on('click', '.ckc-cat-banner-clear, .ckc-shop-banner-clear', function(e){
            e.preventDefault();
            var $btn   = $(this);
            var $scope = $btn.closest('.ckc-category-banner-field, #ckc_shop_banner_box');
            if (!$scope.length) { return; }
            $scope.find('input[name="ckc_category_banner_id"], input[name="ckc_shop_banner_id"]').val('0');
            $scope.find('img.ckc-cat-banner-preview, img.ckc-shop-banner-preview').attr('src', '').hide();
            $btn.hide();
        });
    });
JS;

    wp_register_script( 'ckc-category-banner-admin', false, array( 'jquery', 'media-editor' ), '1.0', true );
    wp_enqueue_script( 'ckc-category-banner-admin' );
    wp_add_inline_script( 'ckc-category-banner-admin', $js );
}
