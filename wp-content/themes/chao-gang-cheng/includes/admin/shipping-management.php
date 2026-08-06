<?php
/**
 * 電商營運 > 運費管理
 *
 * 後台設定頁面：依「配送方式 × 地區 × 溫層」分別設定運費，且每個組合底下
 * 可以再依購買件數分級距各自設定固定運費（例如 1-5 件 NT$150、6-10 件
 * NT$250、11 件以上 NT$350）。
 *
 * 配送方式：
 * - 宅配（home_delivery）：台灣本島／離島，各自常溫／冷藏／冷凍
 * - 超商（cvs）：台灣本島／離島，各自常溫／冷藏／冷凍
 * - 門市自取（store_pickup）：常溫／冷藏／冷凍（不分地區）
 *
 * 前台結帳頁的實際運費已改讀這裡的設定（見檔案下半段「前台套用」區塊的
 * chao_gang_cheng_apply_shipping_management_rates()），取代原本寫死／
 * 散落在 WC_Shipping_Zones 各運送方式設定裡的費率。
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 配送方式／地區的結構定義。門市自取沒有地區區分，用一個空字串 key
 * 代表「不分地區」，讓後面所有迴圈都可以共用同一套寫法，不用另外
 * 為門市自取寫特殊分支。
 */
function chao_gang_cheng_shipping_methods_structure() {
    return array(
        'home_delivery' => array(
            'label'   => '宅配',
            'regions' => array(
                'main_island'     => '台灣本島',
                'outlying_island' => '離島',
            ),
        ),
        'cvs' => array(
            'label'   => '超商',
            'regions' => array(
                'main_island'     => '台灣本島',
                'outlying_island' => '離島',
            ),
        ),
        'store_pickup' => array(
            'label'   => '門市自取',
            'regions' => array(
                '' => '',
            ),
        ),
    );
}

/**
 * 溫層清單，沿用商品「溫層」欄位（_ckc_temperature_zones）已經在用的
 * slug／標籤對照表（chao_gang_cheng_get_temperature_zone_info()），
 * 確保用詞跟商品編輯頁、前台徽章完全一致。
 */
function chao_gang_cheng_shipping_zone_slugs() {
    return array( 'ambient', 'chilled', 'frozen' );
}

/**
 * 免運設定適用的配送方式：只有宅配／超商需要「滿額免運」門檻，
 * 門市自取本身不收運費，不需要另外設定免運條件。
 *
 * 門檻不分本島／離島、不分溫層，宅配、超商各自一個固定金額（跟目前
 * 全站既有的滿額免運判斷邏輯一致：只看訂單金額）。
 */
function chao_gang_cheng_shipping_free_shipping_methods() {
    return array(
        'home_delivery' => '宅配',
        'cvs'           => '超商',
    );
}

/**
 * 單一「配送方式 × 地區 × 溫層」組合底下，件數運費級距的預設值：
 * 只有一級「不限件數」，運費 0 元，讓表格一開始至少有一列可以編輯，
 * 而不是空白表格。
 */
function chao_gang_cheng_shipping_default_tiers() {
    return array(
        array(
            'max_qty' => '',
            'fee'     => 0,
        ),
    );
}

/**
 * 讀取目前已儲存的運費設定，並跟預設結構做深層合併：只要有任何一個
 * 「配送方式 × 地區 × 溫層」組合還沒存過資料，就自動補上預設的單一
 * 級距，避免新增組合（例如以後新增溫層）時後台頁面顯示不完整或報錯。
 *
 * @return array
 */
function chao_gang_cheng_get_shipping_settings() {
    $saved = get_option( 'chao_gang_cheng_shipping_settings', array() );
    if ( ! is_array( $saved ) ) {
        $saved = array();
    }

    $settings = array();
    foreach ( chao_gang_cheng_shipping_methods_structure() as $method_key => $method ) {
        foreach ( $method['regions'] as $region_key => $region_label ) {
            foreach ( chao_gang_cheng_shipping_zone_slugs() as $zone ) {
                $tiers = chao_gang_cheng_shipping_default_tiers();

                $saved_tiers = isset( $saved[ $method_key ][ $region_key ][ $zone ] )
                    ? $saved[ $method_key ][ $region_key ][ $zone ]
                    : null;

                if ( is_array( $saved_tiers ) && ! empty( $saved_tiers ) ) {
                    $clean_tiers = array();
                    foreach ( $saved_tiers as $tier ) {
                        if ( ! is_array( $tier ) || ! isset( $tier['fee'] ) ) {
                            continue;
                        }
                        $clean_tiers[] = array(
                            'max_qty' => isset( $tier['max_qty'] ) ? $tier['max_qty'] : '',
                            'fee'     => $tier['fee'],
                        );
                    }
                    if ( ! empty( $clean_tiers ) ) {
                        $tiers = $clean_tiers;
                    }
                }

                $settings[ $method_key ][ $region_key ][ $zone ] = $tiers;
            }
        }
    }

    // 免運門檻（宅配／超商各一個，金額 0 或未設定＝不啟用免運）。
    $settings['free_shipping'] = array();
    $saved_free_shipping = isset( $saved['free_shipping'] ) && is_array( $saved['free_shipping'] ) ? $saved['free_shipping'] : array();
    foreach ( chao_gang_cheng_shipping_free_shipping_methods() as $method_key => $method_label ) {
        $settings['free_shipping'][ $method_key ] = isset( $saved_free_shipping[ $method_key ] ) ? (float) $saved_free_shipping[ $method_key ] : 0;
    }

    return $settings;
}

/**
 * 後台選單：掛在「電商營運」分組底下（見 functions.php 的
 * ckc_reorganize_admin_menu_groups()，把這個 slug 加進『電商營運』
 * 分組陣列，才會排在正確位置、有正確的分類標題）。
 */
add_action( 'admin_menu', 'ckc_shipping_management_menu' );
function ckc_shipping_management_menu() {
    add_menu_page(
        '運費管理',
        '運費管理',
        'manage_woocommerce',
        'ckc-shipping-management',
        'ckc_shipping_management_render_page',
        'dashicons-car',
        54.6
    );
}

/**
 * 表單送出處理：儲存運費設定。
 */
function ckc_shipping_management_handle_save() {
    if ( ! isset( $_POST['ckc_shipping_management_nonce'] ) ) {
        return null;
    }
    if ( ! wp_verify_nonce( $_POST['ckc_shipping_management_nonce'], 'ckc_shipping_management_save' ) ) {
        return false;
    }
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return false;
    }

    $raw = isset( $_POST['ckc_shipping'] ) && is_array( $_POST['ckc_shipping'] ) ? wp_unslash( $_POST['ckc_shipping'] ) : array();

    $new_settings = array();
    foreach ( chao_gang_cheng_shipping_methods_structure() as $method_key => $method ) {
        foreach ( $method['regions'] as $region_key => $region_label ) {
            foreach ( chao_gang_cheng_shipping_zone_slugs() as $zone ) {
                $max_qty_list = isset( $raw[ $method_key ][ $region_key ][ $zone ]['max_qty'] ) && is_array( $raw[ $method_key ][ $region_key ][ $zone ]['max_qty'] )
                    ? $raw[ $method_key ][ $region_key ][ $zone ]['max_qty']
                    : array();
                $fee_list = isset( $raw[ $method_key ][ $region_key ][ $zone ]['fee'] ) && is_array( $raw[ $method_key ][ $region_key ][ $zone ]['fee'] )
                    ? $raw[ $method_key ][ $region_key ][ $zone ]['fee']
                    : array();

                $tiers = array();
                $count = max( count( $max_qty_list ), count( $fee_list ) );
                for ( $i = 0; $i < $count; $i++ ) {
                    $max_qty_raw = isset( $max_qty_list[ $i ] ) ? trim( (string) $max_qty_list[ $i ] ) : '';
                    $fee_raw     = isset( $fee_list[ $i ] ) ? trim( (string) $fee_list[ $i ] ) : '';

                    // 兩個欄位都留空的列直接跳過（例如使用者按了新增列但沒填）。
                    if ( '' === $max_qty_raw && '' === $fee_raw ) {
                        continue;
                    }

                    $tiers[] = array(
                        'max_qty' => ( '' === $max_qty_raw ) ? '' : max( 0, absint( $max_qty_raw ) ),
                        'fee'     => max( 0, (float) $fee_raw ),
                    );
                }

                if ( empty( $tiers ) ) {
                    $tiers = chao_gang_cheng_shipping_default_tiers();
                }

                $new_settings[ $method_key ][ $region_key ][ $zone ] = $tiers;
            }
        }
    }

    // 免運門檻（獨立的 ckc_shipping_free[method] 欄位，不掛在 ckc_shipping[] 底下，
    // 避免跟上面「配送方式 × 地區 × 溫層」的巢狀迴圈邏輯混在一起）。
    $raw_free_shipping = isset( $_POST['ckc_shipping_free'] ) && is_array( $_POST['ckc_shipping_free'] )
        ? wp_unslash( $_POST['ckc_shipping_free'] )
        : array();
    $new_settings['free_shipping'] = array();
    foreach ( chao_gang_cheng_shipping_free_shipping_methods() as $method_key => $method_label ) {
        $threshold_raw = isset( $raw_free_shipping[ $method_key ] ) ? trim( (string) $raw_free_shipping[ $method_key ] ) : '';
        $new_settings['free_shipping'][ $method_key ] = ( '' === $threshold_raw ) ? 0 : max( 0, (float) $threshold_raw );
    }

    update_option( 'chao_gang_cheng_shipping_settings', $new_settings, false );

    return true;
}

/**
 * 後台頁面渲染。
 */
function ckc_shipping_management_render_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( esc_html__( '您沒有權限存取此頁面。', 'chao-gang-cheng' ) );
    }

    $save_result = null;
    if ( isset( $_POST['ckc_shipping_management_submit'] ) ) {
        $save_result = ckc_shipping_management_handle_save();
    }

    $settings   = chao_gang_cheng_get_shipping_settings();
    $structure  = chao_gang_cheng_shipping_methods_structure();
    $zone_slugs = chao_gang_cheng_shipping_zone_slugs();
    $method_keys = array_keys( $structure );
    ?>
    <div class="wrap ckc-shipping-management-wrap">
        <h1 class="wp-heading-inline">運費管理</h1>
        <hr class="wp-header-end">
        <p style="max-width:760px;color:#555;">
            依「配送方式 × 地區 × 溫層」分別設定運費；每個組合底下可以再依購買件數分級距，設定不同的固定運費（例如 1-5 件 NT$150、6-10 件 NT$250，11 件以上再另外設一列並把「件數上限」留空，代表「以上皆同」）。
        </p>

        <?php if ( true === $save_result ) : ?>
            <div class="notice notice-success is-dismissible"><p>運費設定已儲存。</p></div>
        <?php elseif ( false === $save_result ) : ?>
            <div class="notice notice-error is-dismissible"><p>儲存失敗，請重新整理頁面後再試一次。</p></div>
        <?php endif; ?>

        <style>
            .ckc-shipping-management-wrap .ckc-method-section {
                background: #fff;
                border: 1px solid #dcdcde;
                border-radius: 8px;
                margin: 20px 0;
                overflow: hidden;
            }
            .ckc-shipping-management-wrap .ckc-method-section > summary {
                cursor: pointer;
                list-style: none;
                padding: 16px 20px;
                font-size: 16px;
                font-weight: 700;
                background: #f6f7f7;
                border-bottom: 1px solid #dcdcde;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .ckc-shipping-management-wrap .ckc-method-section > summary::-webkit-details-marker { display: none; }
            .ckc-shipping-management-wrap .ckc-method-section > summary::before {
                content: "▶";
                font-size: 11px;
                color: #787c82;
                transition: transform 0.15s;
            }
            .ckc-shipping-management-wrap .ckc-method-section[open] > summary::before {
                transform: rotate(90deg);
            }
            .ckc-shipping-management-wrap .ckc-method-body {
                padding: 20px;
            }
            .ckc-shipping-management-wrap .ckc-region-block {
                margin-bottom: 24px;
                padding-bottom: 20px;
                border-bottom: 1px dashed #dcdcde;
            }
            .ckc-shipping-management-wrap .ckc-region-block:last-child {
                margin-bottom: 0;
                padding-bottom: 0;
                border-bottom: none;
            }
            .ckc-shipping-management-wrap .ckc-region-title {
                font-size: 14px;
                font-weight: 700;
                color: #1d2327;
                margin: 0 0 12px 0;
            }
            .ckc-shipping-management-wrap .ckc-zone-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
                gap: 16px;
            }
            .ckc-shipping-management-wrap .ckc-zone-card {
                border: 1px solid #e2e4e7;
                border-radius: 6px;
                padding: 14px;
                background: #fbfbfc;
            }
            .ckc-shipping-management-wrap .ckc-zone-card-title {
                font-weight: 700;
                font-size: 13px;
                margin-bottom: 10px;
                display: flex;
                align-items: center;
                gap: 6px;
            }
            .ckc-shipping-management-wrap table.ckc-tier-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 8px;
            }
            .ckc-shipping-management-wrap table.ckc-tier-table th {
                text-align: left;
                font-size: 12px;
                font-weight: 600;
                color: #646970;
                padding: 4px 6px;
            }
            .ckc-shipping-management-wrap table.ckc-tier-table td {
                padding: 4px 6px;
            }
            .ckc-shipping-management-wrap table.ckc-tier-table input[type="number"] {
                width: 100%;
                min-width: 0;
            }
            .ckc-shipping-management-wrap .ckc-tier-remove {
                color: #b32d2e;
                text-decoration: none;
                font-size: 18px;
                line-height: 1;
                padding: 2px 6px;
            }
            .ckc-shipping-management-wrap .ckc-tier-add {
                font-size: 12px;
            }
            .ckc-shipping-management-wrap .ckc-hint {
                font-size: 12px;
                color: #787c82;
                margin: 0 0 8px 0;
            }
        </style>

        <form method="post">
            <?php wp_nonce_field( 'ckc_shipping_management_save', 'ckc_shipping_management_nonce' ); ?>

            <div class="ckc-method-section">
                <div style="padding:16px 20px;font-size:16px;font-weight:700;background:#f6f7f7;border-bottom:1px solid #dcdcde;">免運設定</div>
                <div class="ckc-method-body">
                    <p class="ckc-hint">訂單金額達到門檻時，該配送方式的運費以「免運費」計算；金額設為 0 或留空＝不啟用免運。目前免運門檻只依「消費金額」判斷，不分本島／離島、不分溫層；門市自取本身不收運費，不需要另外設定。</p>
                    <table class="ckc-tier-table" style="max-width:420px;">
                        <thead>
                            <tr>
                                <th style="width:50%;">配送方式</th>
                                <th style="width:50%;">免運門檻 (NT$)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( chao_gang_cheng_shipping_free_shipping_methods() as $method_key => $method_label ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $method_label ); ?></td>
                                    <td><input type="number" min="0" step="1" name="ckc_shipping_free[<?php echo esc_attr( $method_key ); ?>]" value="<?php echo esc_attr( $settings['free_shipping'][ $method_key ] ); ?>" placeholder="0 = 不啟用"></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php foreach ( $structure as $method_key => $method ) : ?>
                <details class="ckc-method-section" <?php echo ( 'home_delivery' === $method_key ) ? 'open' : ''; ?>>
                    <summary><?php echo esc_html( $method['label'] ); ?></summary>
                    <div class="ckc-method-body">
                        <?php foreach ( $method['regions'] as $region_key => $region_label ) : ?>
                            <div class="ckc-region-block">
                                <?php if ( '' !== $region_key ) : ?>
                                    <p class="ckc-region-title"><?php echo esc_html( $region_label ); ?></p>
                                <?php endif; ?>

                                <div class="ckc-zone-grid">
                                    <?php foreach ( $zone_slugs as $zone ) :
                                        $zone_info  = chao_gang_cheng_get_temperature_zone_info( $zone );
                                        $zone_label = $zone_info ? $zone_info['label'] : $zone;
                                        $zone_icon  = $zone_info ? $zone_info['icon'] : '';
                                        $tiers      = isset( $settings[ $method_key ][ $region_key ][ $zone ] ) ? $settings[ $method_key ][ $region_key ][ $zone ] : chao_gang_cheng_shipping_default_tiers();
                                        $field_base = 'ckc_shipping[' . esc_attr( $method_key ) . '][' . esc_attr( $region_key ) . '][' . esc_attr( $zone ) . ']';
                                        ?>
                                        <div class="ckc-zone-card">
                                            <div class="ckc-zone-card-title"><span aria-hidden="true"><?php echo esc_html( $zone_icon ); ?></span> <?php echo esc_html( $zone_label ); ?></div>
                                            <p class="ckc-hint">件數上限留空＝該列以上（含）皆套用此運費。</p>

                                            <table class="ckc-tier-table">
                                                <thead>
                                                    <tr>
                                                        <th style="width:46%;">件數上限</th>
                                                        <th style="width:44%;">運費 (NT$)</th>
                                                        <th style="width:10%;"></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ( $tiers as $tier ) : ?>
                                                        <tr>
                                                            <td><input type="number" min="1" step="1" name="<?php echo $field_base; ?>[max_qty][]" value="<?php echo esc_attr( $tier['max_qty'] ); ?>" placeholder="不限"></td>
                                                            <td><input type="number" min="0" step="1" name="<?php echo $field_base; ?>[fee][]" value="<?php echo esc_attr( $tier['fee'] ); ?>"></td>
                                                            <td><a href="#" class="ckc-tier-remove" title="刪除此列">&times;</a></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                            <button type="button" class="button button-small ckc-tier-add">＋ 新增級距</button>

                                            <template class="ckc-tier-row-template">
                                                <tr>
                                                    <td><input type="number" min="1" step="1" name="<?php echo $field_base; ?>[max_qty][]" value="" placeholder="不限"></td>
                                                    <td><input type="number" min="0" step="1" name="<?php echo $field_base; ?>[fee][]" value="0"></td>
                                                    <td><a href="#" class="ckc-tier-remove" title="刪除此列">&times;</a></td>
                                                </tr>
                                            </template>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endforeach; ?>

            <p class="submit">
                <button type="submit" name="ckc_shipping_management_submit" value="1" class="button button-primary button-large">儲存運費設定</button>
            </p>
        </form>
    </div>

    <script>
    (function () {
        document.addEventListener('click', function (e) {
            var addBtn = e.target.closest('.ckc-tier-add');
            if (addBtn) {
                e.preventDefault();
                var card = addBtn.closest('.ckc-zone-card');
                var template = card.querySelector('.ckc-tier-row-template');
                var tbody = card.querySelector('table.ckc-tier-table tbody');
                if (template && tbody) {
                    var clone = template.content.cloneNode(true);
                    tbody.appendChild(clone);
                }
                return;
            }
            var removeBtn = e.target.closest('.ckc-tier-remove');
            if (removeBtn) {
                e.preventDefault();
                var row = removeBtn.closest('tr');
                var tbody = removeBtn.closest('tbody');
                if (row && tbody && tbody.querySelectorAll('tr').length > 1) {
                    row.parentNode.removeChild(row);
                }
                return;
            }
        });
    })();
    </script>
    <?php
}

/* =========================================================================
 * 前台套用：結帳頁實際運費改讀這個後台設定，取代原本寫死／散落在
 * WC_Shipping_Zones 各方式設定裡的費率。
 *
 * 對應現有結帳頁「宅配／超商／門市自取」三張卡片，各自綁定的 WooCommerce
 * 運送方式 method_id（沿用 chao_gang_cheng_restrict_rates_by_shipping_class()
 * 已經在用的同一套分類，見 functions.php 22d）：
 * - 宅配（home_delivery）  → free_shipping／flat_rate／Wooecpay_Logistic_Home_Tcat
 * - 超商（cvs）            → 開頭是 Wooecpay_Logistic_CVS 的方式
 * - 門市自取（store_pickup）→ local_pickup
 *
 * 地區（本島／離島）沿用既有的 chao_gang_cheng_is_outlying_island_destination()
 * 判斷（functions.php 22），溫層則沿用商品「溫層」欄位跟購物車衝突判斷
 * 同一套 chao_gang_cheng_get_product_temperature_zones()。
 * ========================================================================= */

/**
 * 依「配送方式 × 地區 × 溫層 × 件數」查出應收運費。
 *
 * @param string     $method_key  home_delivery｜cvs｜store_pickup
 * @param string     $region_key  main_island｜outlying_island（store_pickup 不分地區，帶什麼值都會被忽略）
 * @param string     $zone        ambient｜chilled｜frozen
 * @param int        $qty         這個配送包裹裡的商品總件數
 * @param array|null $settings    可選，避免重複讀取 option
 * @return float|null 找不到對應設定時回傳 null，呼叫端應保留原本費用不覆蓋
 */
function chao_gang_cheng_lookup_shipping_fee( $method_key, $region_key, $zone, $qty, $settings = null ) {
    if ( null === $settings ) {
        $settings = chao_gang_cheng_get_shipping_settings();
    }

    // 門市自取不分地區，設定裡固定用空字串當 region key（見
    // chao_gang_cheng_shipping_methods_structure()）。
    $lookup_region = ( 'store_pickup' === $method_key ) ? '' : $region_key;

    $tiers = isset( $settings[ $method_key ][ $lookup_region ][ $zone ] ) ? $settings[ $method_key ][ $lookup_region ][ $zone ] : null;
    if ( empty( $tiers ) || ! is_array( $tiers ) ) {
        return null;
    }

    foreach ( $tiers as $tier ) {
        $max_qty = isset( $tier['max_qty'] ) ? $tier['max_qty'] : '';
        if ( '' === $max_qty || $qty <= (int) $max_qty ) {
            return (float) $tier['fee'];
        }
    }

    // 理論上不會走到這裡（後台一定會保留至少一列「不限」當保底），
    // 保險起見還是回傳最後一列的運費，而不是完全不覆蓋。
    $last_tier = end( $tiers );
    return $last_tier ? (float) $last_tier['fee'] : null;
}

/**
 * 判斷這個配送包裹（購物車／訂單的商品內容）適用哪一種溫層運費。
 *
 * 沿用 chao_gang_cheng_get_cart_temperature_conflict() 同一套「取所有
 * 有標注溫層的商品的交集」邏輯——結帳頁已經會擋下溫層衝突的訂單（見
 * chao_gang_cheng_validate_temperature_zone_checkout()），所以正常情況
 * 這裡交集後只會剩 0 或 1 個溫層。沒有任何商品標注溫層（交集為 null）
 * 時，預設當「常溫」處理。
 *
 * @param array $package WooCommerce shipping package（含 'contents'）
 * @return string ambient｜chilled｜frozen
 */
function chao_gang_cheng_determine_package_temperature_zone( $package ) {
    $common = null;

    if ( ! empty( $package['contents'] ) && is_array( $package['contents'] ) ) {
        foreach ( $package['contents'] as $item ) {
            if ( empty( $item['product_id'] ) ) {
                continue;
            }
            $product = wc_get_product( $item['product_id'] );
            if ( ! $product ) {
                continue;
            }
            $zones = chao_gang_cheng_get_product_temperature_zones( $product );
            if ( empty( $zones ) ) {
                continue; // 未設定溫層＝不限制，不參與交集運算
            }
            $common = ( null === $common ) ? $zones : array_intersect( $common, $zones );
        }
    }

    if ( ! empty( $common ) ) {
        return reset( $common );
    }

    return 'ambient';
}

/**
 * 實際覆蓋結帳頁運費金額。掛在比既有的離島費率調整（優先權 10）、
 * 運送類別限制（優先權 20）都更後面的優先權 30，確保這裡算出來的
 * 金額才是最終顯示／收取的金額，不會被前面兩個既有的 filter 蓋掉。
 *
 * 「免運費」這個原生 WooCommerce 方式（free_shipping）改成完全不用：
 * 宅配／超商是否免運，現在由下面的免運門檻設定各自獨立判斷，不再依賴
 * WooCommerce 原生 free_shipping 方式自己的達成條件；這裡直接把它從
 * $rates 移除，避免同時出現一個名字叫「免運費」但實際運費不是 0 的
 * 選項，造成客人混淆。
 */
add_filter( 'woocommerce_package_rates', 'chao_gang_cheng_apply_shipping_management_rates', 30, 2 );
function chao_gang_cheng_apply_shipping_management_rates( $rates, $package ) {
    if ( ! function_exists( 'chao_gang_cheng_get_shipping_settings' ) ) {
        return $rates;
    }

    $method_groups = array(
        'cvs'           => array( 'prefix' => 'Wooecpay_Logistic_CVS' ),
        'home_delivery' => array( 'exact' => array( 'free_shipping', 'flat_rate', 'Wooecpay_Logistic_Home_Tcat' ) ),
        'store_pickup'  => array( 'exact' => array( 'local_pickup' ) ),
    );

    // 移除原生「免運費」方式，改由下面的門檻邏輯統一決定宅配／超商是否免運。
    foreach ( $rates as $rate_key => $rate ) {
        if ( 'free_shipping' === $rate->method_id ) {
            unset( $rates[ $rate_key ] );
        }
    }

    if ( empty( $rates ) ) {
        return $rates;
    }

    $settings    = chao_gang_cheng_get_shipping_settings();
    $is_outlying = function_exists( 'chao_gang_cheng_is_outlying_island_destination' ) && chao_gang_cheng_is_outlying_island_destination( $package );
    $region_key  = $is_outlying ? 'outlying_island' : 'main_island';
    $zone        = chao_gang_cheng_determine_package_temperature_zone( $package );

    $qty = 0;
    if ( ! empty( $package['contents'] ) && is_array( $package['contents'] ) ) {
        foreach ( $package['contents'] as $item ) {
            $qty += isset( $item['quantity'] ) ? (int) $item['quantity'] : 0;
        }
    }

    // 免運門檻判斷用的訂單金額，沿用購物車頁「已為您省下」／預估運費列
    // 已經在用的同一個金額基準，確保三個地方（購物車預估、已省下、結帳
    // 實收）判斷「有沒有達免運」用的是同一個數字，不會各算各的兜不起來。
    $order_amount = function_exists( 'chao_get_free_shipping_progress_amount' )
        ? chao_get_free_shipping_progress_amount()
        : ( function_exists( 'WC' ) && WC()->cart ? (float) WC()->cart->get_cart_contents_total() : 0 );

    foreach ( $rates as $rate_key => $rate ) {
        $method_key = null;
        foreach ( $method_groups as $key => $group ) {
            if ( isset( $group['prefix'] ) && false !== strpos( $rate->method_id, $group['prefix'] ) ) {
                $method_key = $key;
                break;
            }
            if ( isset( $group['exact'] ) && in_array( $rate->method_id, $group['exact'], true ) ) {
                $method_key = $key;
                break;
            }
        }

        if ( ! $method_key ) {
            continue; // 不認得的方式，維持原本費用不動。
        }

        // 免運門檻只有宅配／超商有設定（見 chao_gang_cheng_shipping_free_shipping_methods()）。
        if ( isset( $settings['free_shipping'][ $method_key ] ) ) {
            $threshold = (float) $settings['free_shipping'][ $method_key ];
            if ( $threshold > 0 && $order_amount >= $threshold ) {
                $rates[ $rate_key ]->cost  = 0;
                $rates[ $rate_key ]->taxes = array();
                continue;
            }
        }

        $fee = chao_gang_cheng_lookup_shipping_fee( $method_key, $region_key, $zone, $qty, $settings );
        if ( null === $fee ) {
            continue; // 這個組合還沒有設定資料，保留原本費用，不覆蓋成 0。
        }

        $rates[ $rate_key ]->cost = $fee;
        if ( wc_tax_enabled() && 'taxable' === $rates[ $rate_key ]->tax_status ) {
            $rates[ $rate_key ]->taxes = WC_Tax::calc_shipping_tax( $fee, WC_Tax::get_shipping_tax_rates() );
        } else {
            $rates[ $rate_key ]->taxes = array();
        }
    }

    return $rates;
}
