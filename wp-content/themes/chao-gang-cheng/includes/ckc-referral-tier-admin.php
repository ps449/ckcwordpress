<?php
/**
 * CKC Referral: 商品分潤分類機制 (Product Referral Categories)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1. 註冊子選單
add_action( 'admin_menu', 'ckc_reftier_register_menu', 62 );
function ckc_reftier_register_menu() {
    add_submenu_page(
        'ckc-referral-admin',
        '商品分潤分類',
        '商品分潤分類',
        'manage_woocommerce',
        'ckc-referral-product-tier',
        'ckc_reftier_render_page'
    );
}

// 2. 處理表單動作 (儲存全域費率、批次修改分類)
function ckc_reftier_handle_actions() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return '';
    }

    $message = '';

    // 處理儲存費率
    if ( isset( $_POST['ckc_reftier_save_rates'] ) && isset( $_POST['ckc_reftier_nonce'] ) && wp_verify_nonce( $_POST['ckc_reftier_nonce'], 'ckc_reftier_rates' ) ) {
        $rate_hot = isset( $_POST['rate_hot'] ) ? floatval( $_POST['rate_hot'] ) : 3;
        $rate_regular = isset( $_POST['rate_regular'] ) ? floatval( $_POST['rate_regular'] ) : 6;
        $rate_slow = isset( $_POST['rate_slow'] ) ? floatval( $_POST['rate_slow'] ) : 12;
        
        update_option( '_ckc_ref_tier_rate_hot', $rate_hot );
        update_option( '_ckc_ref_tier_rate_regular', $rate_regular );
        update_option( '_ckc_ref_tier_rate_slow', $rate_slow );
        // 未設定分潤永遠固定為 0%，不再提供後台修改以免產生預期外的錯誤
        update_option( '_ckc_ref_tier_rate_uncategorized', 0 );
        
        $message = '<div class="notice notice-success is-dismissible"><p>費率設定已儲存。</p></div>';
    }

    // 處理批次變更分類
    if ( isset( $_POST['ckc_reftier_bulk_action'] ) && isset( $_POST['ckc_reftier_bulk_nonce'] ) && wp_verify_nonce( $_POST['ckc_reftier_bulk_nonce'], 'ckc_reftier_bulk' ) ) {
        $action = sanitize_text_field( $_POST['ckc_reftier_bulk_action'] );
        $product_ids = isset( $_POST['product_ids'] ) ? array_map( 'intval', (array) $_POST['product_ids'] ) : array();

        if ( in_array( $action, array( 'hot', 'regular', 'slow', 'uncategorized' ) ) && ! empty( $product_ids ) ) {
            $user_id = get_current_user_id();
            $count = 0;
            foreach ( $product_ids as $pid ) {
                $old_tier = get_post_meta( $pid, '_ckc_ref_tier', true );
                if ( ! $old_tier ) $old_tier = 'uncategorized';
                
                if ( $old_tier === $action ) continue;

                if ( $action === 'uncategorized' ) {
                    delete_post_meta( $pid, '_ckc_ref_tier' );
                } else {
                    update_post_meta( $pid, '_ckc_ref_tier', $action );
                }

                // 寫入歷史紀錄
                $log_entry = array(
                    'date'     => current_time( 'mysql' ),
                    'user_id'  => $user_id,
                    'old_tier' => $old_tier,
                    'new_tier' => $action
                );
                add_post_meta( $pid, '_ckc_ref_tier_log', $log_entry );
                $count++;
            }
            $message = '<div class="notice notice-success is-dismissible"><p>成功更新了 ' . $count . ' 件商品的分類。</p></div>';
        } elseif ( ! empty( $_POST['ckc_reftier_bulk_action'] ) && $_POST['ckc_reftier_bulk_action'] !== '-1' && empty( $product_ids ) ) {
            $message = '<div class="notice notice-warning is-dismissible"><p>請先勾選商品。</p></div>';
        }
    }

    return $message;
}

// 取得各分類當前費率
function ckc_reftier_get_rates() {
    return array(
        'hot'           => floatval( get_option( '_ckc_ref_tier_rate_hot', 3 ) ),
        'regular'       => floatval( get_option( '_ckc_ref_tier_rate_regular', 6 ) ),
        'slow'          => floatval( get_option( '_ckc_ref_tier_rate_slow', 12 ) ),
        'uncategorized' => 0, // 固定 0%
    );
}

// 取得分類名稱對應
function ckc_reftier_get_labels() {
    return array(
        'hot'           => '熱銷商品',
        'regular'       => '常態商品',
        'slow'          => '低銷商品',
        'uncategorized' => '未設定分潤商品',
    );
}

// 3. 渲染頁面
function ckc_reftier_render_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( '權限不足' );
    }

    $message = ckc_reftier_handle_actions();
    $rates = ckc_reftier_get_rates();
    $labels = ckc_reftier_get_labels();

    $current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'all';
    $paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
    $posts_per_page = 50;

    // 建立 Query 參數
    $args = array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => $posts_per_page,
        'paged'          => $paged,
        'orderby'        => 'date',
        'order'          => 'DESC',
    );

    if ( $current_tab !== 'all' ) {
        if ( $current_tab === 'uncategorized' ) {
            $args['meta_query'] = array(
                'relation' => 'OR',
                array(
                    'key'     => '_ckc_ref_tier',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key'     => '_ckc_ref_tier',
                    'value'   => '',
                    'compare' => '='
                )
            );
        } elseif ( in_array( $current_tab, array( 'hot', 'regular', 'slow' ) ) ) {
            $args['meta_query'] = array(
                array(
                    'key'     => '_ckc_ref_tier',
                    'value'   => $current_tab,
                    'compare' => '='
                )
            );
        }
    }

    // 搜尋功能
    $search_query = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
    if ( ! empty( $search_query ) ) {
        $args['s'] = $search_query;
    }

    $products_query = new WP_Query( $args );
    $total_items = $products_query->found_posts;
    $total_pages = $products_query->max_num_pages;

    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">商品分潤分類管理</h1>
        <hr class="wp-header-end">
        <?php echo $message; ?>

        <!-- 費率設定區塊 -->
        <div class="postbox" style="margin-top:20px; max-width: 800px;">
            <div class="inside">
                <h3><strong>全域分潤費率設定 (%)</strong></h3>
                <p class="description">在此設定各分類的預設分潤抽成比例。更改後即時生效，未來新產生的訂單將採用新費率。</p>
                <form method="post" action="">
                    <?php wp_nonce_field( 'ckc_reftier_rates', 'ckc_reftier_nonce' ); ?>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="rate_hot">熱銷商品 (預設 3%)</label></th>
                                <td>
                                    <input name="rate_hot" type="number" step="0.1" min="0" id="rate_hot" value="<?php echo esc_attr( $rates['hot'] ); ?>" class="small-text"> %
                                    <p class="description">本身已有自然銷量與流量，不需高誘因即可成交。</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="rate_regular">常態商品 (預設 6%)</label></th>
                                <td>
                                    <input name="rate_regular" type="number" step="0.1" min="0" id="rate_regular" value="<?php echo esc_attr( $rates['regular'] ); ?>" class="small-text"> %
                                    <p class="description">銷量穩定，用中等誘因維持推廣動能。</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="rate_slow">低銷商品 (預設 12%)</label></th>
                                <td>
                                    <input name="rate_slow" type="number" step="0.1" min="0" id="rate_slow" value="<?php echo esc_attr( $rates['slow'] ); ?>" class="small-text"> %
                                    <p class="description">銷量差，用高誘因促使夥伴優先推廣、加速去化庫存。</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label>未設定分潤商品</label></th>
                                <td>
                                    <input type="text" value="0" class="small-text" disabled> %
                                    <p class="description">未設定分類的商品將強制為 0% 分潤，避免夥伴誤解。</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="submit">
                        <input type="submit" name="ckc_reftier_save_rates" id="submit" class="button button-primary" value="儲存費率設定">
                    </p>
                </form>
            </div>
        </div>

        <!-- 篩選頁籤 -->
        <h2 class="nav-tab-wrapper" style="margin-top: 30px;">
            <?php
            $tabs = array(
                'all' => '全部商品',
                'hot' => '熱銷商品',
                'regular' => '常態商品',
                'slow' => '低銷商品',
                'uncategorized' => '未設定分潤商品'
            );
            foreach ( $tabs as $tab_key => $tab_name ) {
                $active = ( $current_tab === $tab_key ) ? 'nav-tab-active' : '';
                $url = admin_url( 'admin.php?page=ckc-referral-product-tier&tab=' . $tab_key );
                echo '<a href="' . esc_url( $url ) . '" class="nav-tab ' . esc_attr( $active ) . '">' . esc_html( $tab_name ) . '</a>';
            }
            ?>
        </h2>

        <!-- 商品列表與批次操作 -->
        <form id="tier-filter" method="get">
            <input type="hidden" name="page" value="ckc-referral-product-tier" />
            <input type="hidden" name="tab" value="<?php echo esc_attr( $current_tab ); ?>" />
            
            <p class="search-box">
                <label class="screen-reader-text" for="post-search-input">搜尋商品:</label>
                <input type="search" id="post-search-input" name="s" value="<?php echo esc_attr( $search_query ); ?>">
                <input type="submit" id="search-submit" class="button" value="搜尋商品">
            </p>
        </form>

        <form method="post" action="">
            <?php wp_nonce_field( 'ckc_reftier_bulk', 'ckc_reftier_bulk_nonce' ); ?>
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <label for="bulk-action-selector-top" class="screen-reader-text">選取批次動作</label>
                    <select name="ckc_reftier_bulk_action" id="bulk-action-selector-top">
                        <option value="-1">批次設定分類為...</option>
                        <option value="hot">熱銷商品 (<?php echo $rates['hot']; ?>%)</option>
                        <option value="regular">常態商品 (<?php echo $rates['regular']; ?>%)</option>
                        <option value="slow">低銷商品 (<?php echo $rates['slow']; ?>%)</option>
                        <option value="uncategorized">未設定分潤 (0%)</option>
                    </select>
                    <input type="submit" id="doaction" class="button action" value="套用">
                </div>
                
                <div class="tablenav-pages">
                    <span class="displaying-num">共 <?php echo $total_items; ?> 個項目</span>
                    <?php
                    $page_links = paginate_links( array(
                        'base' => add_query_arg( 'paged', '%#%' ),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $paged,
                    ) );
                    if ( $page_links ) {
                        echo '<span class="pagination-links">' . $page_links . '</span>';
                    }
                    ?>
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped table-view-list posts">
                <thead>
                    <tr>
                        <td id="cb" class="manage-column column-cb check-column">
                            <label class="screen-reader-text" for="cb-select-all-1">全選</label>
                            <input id="cb-select-all-1" type="checkbox">
                        </td>
                        <th scope="col" class="manage-column column-thumb" style="width: 60px;">圖片</th>
                        <th scope="col" class="manage-column column-title column-primary">商品名稱</th>
                        <th scope="col" class="manage-column column-tier">當前分類</th>
                        <th scope="col" class="manage-column column-rate">對應費率</th>
                        <th scope="col" class="manage-column column-date">異動歷史</th>
                    </tr>
                </thead>
                <tbody id="the-list">
                    <?php if ( $products_query->have_posts() ) : ?>
                        <?php while ( $products_query->have_posts() ) : $products_query->the_post(); 
                            $product_id = get_the_ID();
                            $product = wc_get_product( $product_id );
                            if ( ! $product ) continue;
                            
                            $tier = get_post_meta( $product_id, '_ckc_ref_tier', true );
                            if ( ! $tier || ! isset( $labels[$tier] ) ) $tier = 'uncategorized';
                            
                            $logs = get_post_meta( $product_id, '_ckc_ref_tier_log', false );
                        ?>
                            <tr>
                                <th scope="row" class="check-column">
                                    <label class="screen-reader-text" for="cb-select-<?php echo $product_id; ?>">選取 <?php the_title(); ?></label>
                                    <input id="cb-select-<?php echo $product_id; ?>" type="checkbox" name="product_ids[]" value="<?php echo $product_id; ?>">
                                </th>
                                <td class="column-thumb">
                                    <?php echo $product->get_image( array( 40, 40 ) ); ?>
                                </td>
                                <td class="column-title column-primary" data-colname="商品名稱">
                                    <strong><a href="<?php echo get_edit_post_link( $product_id ); ?>"><?php the_title(); ?></a></strong>
                                    <div class="row-actions">
                                        <span class="edit"><a href="<?php echo get_edit_post_link( $product_id ); ?>">編輯商品</a></span>
                                    </div>
                                </td>
                                <td class="column-tier" data-colname="當前分類">
                                    <?php
                                    $badge_style = 'display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 12px; font-weight: bold;';
                                    if ( $tier === 'hot' ) {
                                        echo '<span style="' . $badge_style . ' background: #fef2f2; color: #b91c1c; border: 1px solid #f87171;">熱銷商品</span>';
                                    } elseif ( $tier === 'regular' ) {
                                        echo '<span style="' . $badge_style . ' background: #f0fdf4; color: #15803d; border: 1px solid #86efac;">常態商品</span>';
                                    } elseif ( $tier === 'slow' ) {
                                        echo '<span style="' . $badge_style . ' background: #fffbeb; color: #b45309; border: 1px solid #fde047;">低銷商品</span>';
                                    } else {
                                        echo '<span style="' . $badge_style . ' background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1;">未設定</span>';
                                    }
                                    ?>
                                </td>
                                <td class="column-rate" data-colname="對應費率">
                                    <strong><?php echo $rates[$tier]; ?>%</strong>
                                </td>
                                <td class="column-date" data-colname="異動歷史">
                                    <?php 
                                    if ( empty( $logs ) ) {
                                        echo '無';
                                    } else {
                                        // 只顯示最新一筆，可懸停看更多
                                        $latest_log = end( $logs );
                                        $old_label = isset( $labels[$latest_log['old_tier']] ) ? $labels[$latest_log['old_tier']] : '未設定';
                                        $new_label = isset( $labels[$latest_log['new_tier']] ) ? $labels[$latest_log['new_tier']] : '未設定';
                                        
                                        echo '<span title="從 ' . esc_attr($old_label) . ' 改為 ' . esc_attr($new_label) . '">' . esc_html( date( 'Y-m-d', strtotime( $latest_log['date'] ) ) ) . ' 更新</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else : ?>
                        <tr class="no-items"><td class="colspanchange" colspan="6">找不到任何商品。</td></tr>
                    <?php endif; wp_reset_postdata(); ?>
                </tbody>
            </table>
        </form>
    </div>
    <script>
    jQuery(document).ready(function($) {
        $('#cb-select-all-1').on('click', function() {
            var isChecked = $(this).prop('checked');
            $('input[name="product_ids[]"]').prop('checked', isChecked);
        });
    });
    </script>
    <?php
}
