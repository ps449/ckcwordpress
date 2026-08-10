<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 商品「二層規格選項」（例如日期、時間），可選、選了會影響價格與庫存。
 *
 * 背景：宴席／預約類商品（例如午餐券、時段預約）需要讓客人選擇日期、
 * 時間等「規格」，不同日期＋時間的組合可能有不同的價格加成、也可能有
 * 各自獨立的名額（庫存）上限；但客人也可以完全不選就直接下單（規格是
 * 加分資訊，不是強制欄位）。
 *
 * 資料設計（詳見 product-specs-feature-plan.md 規劃文件）：
 * - _ckc_spec_categories：規格類別與可選值，例如
 *   [ { id, label:'日期', values:[ {id,label:'8/15'}, ... ] }, { id, label:'時間', values:[...] } ]
 * - _ckc_spec_combinations：組合層級的價格調整／庫存／是否開放，
 *   key 由各類別選到的值 id 依類別 id 字母序組成，例如
 *   'date:v1|time:v2' => { price_adjust, stock_qty, enabled }
 *
 * 這個功能開放給「所有商品」使用（原本只限定「預約類商品」分類，後來
 * 改成不再依分類限制——不管商品有沒有掛 reservation-item 這個分類，
 * 商品編輯頁都會顯示「規格選項設定」區塊；沒有實際設定任何規格類別的
 * 商品，前台不會多顯示任何東西，等於維持原樣，所以放開這個限制對既有
 * 商品是安全的）。
 *
 * reservation-item 這個分類本身還留著（沒有刪除自動建立的邏輯），只是
 * 不再拿來當作「要不要顯示規格設定」的判斷依據；如果之後想恢復成只給
 * 特定分類使用，把 chao_gang_cheng_product_uses_spec_options() 改回
 * has_term() 判斷即可。
 *
 * 本檔案只做「第 1 期」：後台資料結構＋ meta box 設定介面。前台顯示、
 * 加入購物車／訂單整合、庫存扣減防超賣是後續階段，尚未包含在這裡。
 */

/**
 * 1.（已移除）原本這裡會在 init 自動建立「預約類商品」分類，因為規格
 * 選項功能已經開放給所有商品使用，不再需要這個分類，於是移除了自動
 * 重建的邏輯——之前手動刪除分類後又被重新建立，就是這段程式碼造成的。
 * 如果之後想恢復「只給特定分類使用」的設計，可以把這個函式加回來，
 * 並把下面 chao_gang_cheng_product_uses_spec_options() 改回 has_term()
 * 判斷。
 */

/**
 * 2. 判斷商品是否可以使用「規格選項設定」——目前開放所有商品使用，不再
 * 限定「預約類商品」分類。
 *
 * @param int $product_id
 * @return bool
 */
function chao_gang_cheng_product_uses_spec_options( $product_id ) {
    return true;
}

/**
 * 3. 讀取商品目前已儲存的規格類別／值設定。
 *
 * @param int $product_id
 * @return array
 */
function chao_gang_cheng_get_spec_categories( $product_id ) {
    $data = get_post_meta( $product_id, '_ckc_spec_categories', true );
    return is_array( $data ) ? $data : array();
}

/**
 * 4. 讀取商品目前已儲存的組合定價／庫存設定。
 *
 * 注意（第 4 期）：回傳的 stock_qty 是「即時庫存」，不是單純讀
 * _ckc_spec_combinations 這包陣列裡存的靜態數字——如果這個組合有設定
 * 庫存上限（不是 null／不限制），會用獨立 meta 記錄的即時庫存覆蓋過去
 * （訂單扣庫存/回補庫存都是改那個獨立 meta，理由見
 * chao_gang_cheng_adjust_spec_combo_stock() 的說明）。這樣不管是後台
 * 顯示、前台試算、或加入購物車檢查，讀到的都是同一份「現在真正剩多少」
 * 的數字。
 *
 * @param int $product_id
 * @return array key（組合鍵）=> array( price_adjust, stock_qty, enabled )
 */
function chao_gang_cheng_get_spec_combinations( $product_id ) {
    $data = get_post_meta( $product_id, '_ckc_spec_combinations', true );
    $data = is_array( $data ) ? $data : array();

    foreach ( $data as $key => &$combo ) {
        if ( ! isset( $combo['stock_qty'] ) || null === $combo['stock_qty'] ) {
            continue; // 本來就是「不限制」，不需要有即時庫存 meta
        }
        $live = chao_gang_cheng_get_spec_combo_live_stock( $product_id, $key );
        if ( null !== $live ) {
            $combo['stock_qty'] = $live;
        }
    }
    unset( $combo );

    return $data;
}

/**
 * 4a. 組合的「即時庫存」用的 meta key。庫存數字不是跟其他組合資料塞在
 * 同一包 _ckc_spec_combinations 陣列裡，而是每個組合各自存成一個獨立的
 * meta row，這樣才能用資料庫層級的條件式 UPDATE 做「原子扣庫存」
 * （同時間兩筆訂單搶最後一件也不會都扣成功）。組合 key 本身含有
 * `:` `|` 這類字元，直接當 meta_key 用不影響 SQL 儲存，
 * 但用 md5 雜湊一次讓長度固定、也避免特殊字元在少數環境下踩到坑。
 *
 * @param string $combo_key
 * @return string
 */
function chao_gang_cheng_spec_stock_meta_key( $combo_key ) {
    return '_ckc_spec_stock_' . md5( $combo_key );
}

/**
 * 4b. 讀取單一組合目前的即時庫存。
 *
 * @param int    $product_id
 * @param string $combo_key
 * @return int|null null 代表沒有設定庫存上限（不限制，或這個組合根本沒有庫存 meta）
 */
function chao_gang_cheng_get_spec_combo_live_stock( $product_id, $combo_key ) {
    $val = get_post_meta( $product_id, chao_gang_cheng_spec_stock_meta_key( $combo_key ), true );
    return ( '' === $val || false === $val ) ? null : (int) $val;
}

/**
 * 4c. 原子調整（扣減／回補）單一組合的即時庫存，用資料庫層級的條件式
 * UPDATE，避免「同時間兩筆訂單一起讀到庫存還有 1、兩邊都扣成功變成
 * -1」這種超賣情況——跟 WooCommerce 核心自己扣商品庫存（wc_update_product_stock）
 * 是同一種做法。
 *
 * 只有這個組合「本來就有設定庫存上限」（存在即時庫存 meta）才會真的去
 * 扣／補；沒有設定（不限制）的組合完全不受影響，呼叫這個函式也是安全
 * 的空操作。
 *
 * @param int    $product_id
 * @param string $combo_key
 * @param int    $delta 負數＝扣庫存，正數＝回補庫存。
 * @return bool 扣庫存時如果目前庫存不足（或這個組合根本不限制庫存）會回傳 false，
 *              代表沒有扣成功；回補一律回傳 true（回補不會有「扣不夠」的問題）。
 */
function chao_gang_cheng_adjust_spec_combo_stock( $product_id, $combo_key, $delta ) {
    global $wpdb;

    $delta = (int) $delta;
    if ( 0 === $delta ) {
        return true;
    }

    $meta_key = chao_gang_cheng_spec_stock_meta_key( $combo_key );

    if ( $delta > 0 ) {
        // 回補：直接加回去。如果這個組合根本沒有庫存 meta（代表當初就是
        // 不限制），這裡不會意外新增一筆 meta——用 UPDATE 而不是
        // update_post_meta()，沒有對應的 row 就什麼事都不會發生。
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->postmeta} SET meta_value = CAST(meta_value AS SIGNED) + %d WHERE post_id = %d AND meta_key = %s",
                $delta,
                $product_id,
                $meta_key
            )
        );
        clean_post_cache( $product_id );
        return true;
    }

    $need = abs( $delta );
    $updated_rows = $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->postmeta} SET meta_value = CAST(meta_value AS SIGNED) - %d WHERE post_id = %d AND meta_key = %s AND CAST(meta_value AS SIGNED) >= %d",
            $need,
            $product_id,
            $meta_key,
            $need
        )
    );
    clean_post_cache( $product_id );

    return $updated_rows > 0;
}

/**
 * 5. Meta box：所有商品的編輯頁都會顯示。
 */
add_action( 'add_meta_boxes', 'chao_gang_cheng_add_spec_options_meta_box' );
function chao_gang_cheng_add_spec_options_meta_box() {
    $screen = get_current_screen();
    if ( ! $screen || 'product' !== $screen->post_type ) {
        return;
    }
    global $post;
    if ( ! $post || ! chao_gang_cheng_product_uses_spec_options( $post->ID ) ) {
        return;
    }
    add_meta_box(
        'ckc_spec_options_box',
        '規格選項設定（日期／時間等，可選，會影響價格與庫存）',
        'chao_gang_cheng_render_spec_options_meta_box',
        'product',
        'normal',
        'high'
    );
}

/**
 * 6. Meta box 渲染：規格類別／值管理 ＋ 組合定價／庫存表。
 */
function chao_gang_cheng_render_spec_options_meta_box( $post ) {
    wp_nonce_field( 'ckc_spec_options_save', 'ckc_spec_options_nonce' );

    $categories  = chao_gang_cheng_get_spec_categories( $post->ID );
    $combos      = chao_gang_cheng_get_spec_combinations( $post->ID );
    ?>
    <div class="ckc-spec-options-wrap">
        <style>
            .ckc-spec-options-wrap { max-width: 900px; }
            .ckc-spec-options-wrap p.description { margin-top: 0; color: #646970; }
            .ckc-spec-cat-block {
                border: 1px solid #dcdcde;
                background: #f6f7f7;
                border-radius: 6px;
                padding: 12px 14px;
                margin-bottom: 12px;
            }
            .ckc-spec-cat-block .ckc-spec-cat-header {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-bottom: 10px;
            }
            .ckc-spec-cat-block .ckc-spec-cat-header input[type="text"] {
                font-weight: 600;
                width: 240px;
            }
            .ckc-spec-value-row {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-bottom: 6px;
            }
            .ckc-spec-value-row input[type="text"] { width: 200px; }
            .ckc-spec-remove-link { color: #b32d2e; text-decoration: none; cursor: pointer; }
            .ckc-spec-remove-link:hover { text-decoration: underline; }
            table.ckc-spec-combo-table {
                border-collapse: collapse;
                width: 100%;
                margin-top: 8px;
            }
            table.ckc-spec-combo-table th,
            table.ckc-spec-combo-table td {
                border: 1px solid #e2e4e7;
                padding: 6px 8px;
                text-align: left;
                vertical-align: middle;
            }
            table.ckc-spec-combo-table th { background: #f6f7f7; }
            table.ckc-spec-combo-table input[type="number"] { width: 110px; }
            .ckc-spec-empty-hint { color: #8c7a64; font-size: 13px; margin: 10px 0; }
        </style>

        <p class="description">
            設定商品的規格類別（例如「日期」「時間」），每個類別底下再列出可選的值。前台會依可選、選了才會記錄，不影響溫層／配送方式限制。下方「組合定價與庫存」表會依目前的類別／值自動列出所有組合，逐列填寫價格調整與庫存（留空＝不限制）。
        </p>

        <h4>規格類別</h4>
        <div id="ckc-spec-cat-list">
            <?php if ( empty( $categories ) ) : ?>
                <p class="ckc-spec-empty-hint" data-ckc-empty-cats>目前尚未設定任何規格類別，請按下方「新增規格類別」開始設定。</p>
            <?php else : ?>
                <?php foreach ( $categories as $cat_index => $cat ) : ?>
                    <?php echo chao_gang_cheng_render_spec_cat_block_html( $cat_index, $cat ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <p>
            <button type="button" class="button" id="ckc-spec-add-cat">＋ 新增規格類別</button>
        </p>

        <h4>組合定價與庫存</h4>
        <div id="ckc-spec-combo-wrap">
            <p class="ckc-spec-empty-hint" id="ckc-spec-combo-empty" style="display:none;">目前沒有任何規格類別／值，請先在上方新增，這裡才會列出對應的組合。</p>
            <table class="ckc-spec-combo-table" id="ckc-spec-combo-table">
                <thead>
                    <tr>
                        <th style="width:40%;">組合</th>
                        <th style="width:20%;">價格調整（NT$，可負數，0＝不調整）</th>
                        <th style="width:20%;">庫存／名額（留空＝不限制）</th>
                        <th style="width:10%;">開放選擇</th>
                    </tr>
                </thead>
                <tbody id="ckc-spec-combo-tbody"></tbody>
            </table>
        </div>

        <template id="ckc-spec-cat-template">
            <?php echo chao_gang_cheng_render_spec_cat_block_html( '__CAT_INDEX__', array( 'id' => '', 'label' => '', 'values' => array() ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
        </template>
        <template id="ckc-spec-value-row-template">
            <?php echo chao_gang_cheng_render_spec_value_row_html( '__CAT_INDEX__', '__VAL_INDEX__', array( 'id' => '', 'label' => '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
        </template>
        <template id="ckc-spec-combo-row-template">
            <tr class="ckc-spec-combo-row">
                <td class="ckc-spec-combo-label"></td>
                <td><input type="number" step="1" value="0" class="ckc-combo-price"></td>
                <td><input type="number" step="1" min="0" value="" placeholder="不限制" class="ckc-combo-stock"></td>
                <td style="text-align:center;"><input type="checkbox" value="1" checked class="ckc-combo-enabled"></td>
            </tr>
        </template>

        <script>
        (function () {
            var catList   = document.getElementById('ckc-spec-cat-list');
            var comboBody = document.getElementById('ckc-spec-combo-tbody');
            var comboEmpty = document.getElementById('ckc-spec-combo-empty');
            var catTemplate = document.getElementById('ckc-spec-cat-template');
            var valTemplate = document.getElementById('ckc-spec-value-row-template');
            var comboRowTemplate = document.getElementById('ckc-spec-combo-row-template');
            var catCounter = <?php echo (int) count( $categories ); ?>;
            var valCounters = {}; // cat_index -> next value index

            // 現有已儲存的組合資料（第一次載入時用來預先帶入表格）
            var savedCombos = <?php echo wp_json_encode( is_array( $combos ) ? $combos : new stdClass() ); ?>;
            // 使用者在畫面上目前輸入的組合資料，重繪表格時用這份資料還原輸入值
            var liveCombos = Object.assign( {}, savedCombos );

            function slugifyNewId( prefix ) {
                return prefix + '_' + Date.now().toString(36) + Math.floor( Math.random() * 1000 );
            }

            function initValCounter( catIndex ) {
                if ( ! ( catIndex in valCounters ) ) {
                    var block = catList.querySelector('.ckc-spec-cat-block[data-cat-index="' + catIndex + '"]');
                    var count = block ? block.querySelectorAll('.ckc-spec-value-row').length : 0;
                    valCounters[ catIndex ] = count;
                }
            }

            document.querySelectorAll('.ckc-spec-cat-block').forEach(function (block) {
                initValCounter( block.getAttribute('data-cat-index') );
            });

            function html( str ) {
                var div = document.createElement('div');
                div.innerHTML = str.trim();
                return div.firstElementChild;
            }

            document.getElementById('ckc-spec-add-cat').addEventListener('click', function () {
                var emptyHint = catList.querySelector('[data-ckc-empty-cats]');
                if ( emptyHint ) { emptyHint.remove(); }
                var newIndex = catCounter++;
                var tpl = catTemplate.innerHTML.split('__CAT_INDEX__').join( newIndex );
                var node = html( tpl );
                node.querySelector('input[name*="[id]"]').value = slugifyNewId('cat');
                catList.appendChild( node );
                valCounters[ newIndex ] = 0;
                rebuildCombos();
            });

            catList.addEventListener('click', function ( e ) {
                var addVal = e.target.closest('.ckc-spec-add-value');
                if ( addVal ) {
                    var block = addVal.closest('.ckc-spec-cat-block');
                    var catIndex = block.getAttribute('data-cat-index');
                    initValCounter( catIndex );
                    var valIndex = valCounters[ catIndex ]++;
                    var tpl = valTemplate.innerHTML
                        .split('__CAT_INDEX__').join( catIndex )
                        .split('__VAL_INDEX__').join( valIndex );
                    var node = html( tpl );
                    node.querySelector('input[name*="[id]"]').value = slugifyNewId('val');
                    block.querySelector('.ckc-spec-value-list').appendChild( node );
                    rebuildCombos();
                    return;
                }
                var removeVal = e.target.closest('.ckc-spec-remove-value');
                if ( removeVal ) {
                    removeVal.closest('.ckc-spec-value-row').remove();
                    rebuildCombos();
                    return;
                }
                var removeCat = e.target.closest('.ckc-spec-remove-cat');
                if ( removeCat ) {
                    removeCat.closest('.ckc-spec-cat-block').remove();
                    if ( ! catList.querySelector('.ckc-spec-cat-block') ) {
                        var p = document.createElement('p');
                        p.className = 'ckc-spec-empty-hint';
                        p.setAttribute('data-ckc-empty-cats', '');
                        p.textContent = '目前尚未設定任何規格類別，請按下方「新增規格類別」開始設定。';
                        catList.appendChild( p );
                    }
                    rebuildCombos();
                    return;
                }
            });

            catList.addEventListener('input', function ( e ) {
                if ( e.target.matches('input[type="text"]') ) {
                    rebuildCombos();
                }
            });

            comboBody.addEventListener('input', function ( e ) {
                var row = e.target.closest('.ckc-spec-combo-row');
                if ( ! row ) { return; }
                var key = row.getAttribute('data-combo-key');
                if ( ! liveCombos[ key ] ) { liveCombos[ key ] = {}; }
                liveCombos[ key ].price_adjust = row.querySelector('.ckc-combo-price').value;
                liveCombos[ key ].stock_qty    = row.querySelector('.ckc-combo-stock').value;
                liveCombos[ key ].enabled      = row.querySelector('.ckc-combo-enabled').checked;
            });
            comboBody.addEventListener('change', function ( e ) {
                if ( e.target.classList.contains('ckc-combo-enabled') ) {
                    var row = e.target.closest('.ckc-spec-combo-row');
                    var key = row.getAttribute('data-combo-key');
                    if ( ! liveCombos[ key ] ) { liveCombos[ key ] = {}; }
                    liveCombos[ key ].enabled = e.target.checked;
                }
            });

            function readCategoriesFromDom() {
                var cats = [];
                catList.querySelectorAll('.ckc-spec-cat-block').forEach(function ( block ) {
                    var idInput    = block.querySelector(':scope > .ckc-spec-cat-header input[name*="[id]"]');
                    var labelInput = block.querySelector(':scope > .ckc-spec-cat-header input[name*="[label]"]');
                    var id    = idInput ? idInput.value : '';
                    var label = labelInput ? labelInput.value : '';
                    if ( ! id || ! label ) { return; }
                    var values = [];
                    block.querySelectorAll('.ckc-spec-value-row').forEach(function ( row ) {
                        var vId    = row.querySelector('input[name*="[id]"]').value;
                        var vLabel = row.querySelector('input[name*="[label]"]').value;
                        if ( vId && vLabel ) {
                            values.push( { id: vId, label: vLabel } );
                        }
                    });
                    if ( values.length ) {
                        cats.push( { id: id, label: label, values: values } );
                    }
                });
                return cats;
            }

            function cartesianCombos( cats ) {
                if ( ! cats.length ) { return []; }
                var sorted = cats.slice().sort(function ( a, b ) { return a.id < b.id ? -1 : 1; });
                var result = [ { keyParts: [], labelParts: [] } ];
                sorted.forEach(function ( cat ) {
                    var next = [];
                    result.forEach(function ( combo ) {
                        cat.values.forEach(function ( val ) {
                            next.push( {
                                keyParts: combo.keyParts.concat( [ cat.id + ':' + val.id ] ),
                                labelParts: combo.labelParts.concat( [ cat.label + '：' + val.label ] )
                            } );
                        } );
                    } );
                    result = next;
                } );
                return result.map(function ( c ) {
                    return { key: c.keyParts.join('|'), label: c.labelParts.join('、') };
                } );
            }

            function rebuildCombos() {
                var cats = readCategoriesFromDom();
                var combos = cartesianCombos( cats );
                comboBody.innerHTML = '';
                comboEmpty.style.display = combos.length ? 'none' : '';
                combos.forEach(function ( combo ) {
                    // <tr> 樣板必須用 <template>.content.cloneNode() 取出，不能像類別/值
                    // 那樣用「塞進一個 <div> 再讀 innerHTML」的字串拼接方式——瀏覽器的
                    // HTML 解析器不允許 <tr> 單獨出現在 <div> 底下（沒有 <table> 包著），
                    // 會被直接丟棄，導致 querySelector 抓到 null（實測踩過這個坑）。
                    // combo.label 是使用者填的中文顯示文字，用 textContent 賦值，避免文字
                    // 裡如果剛好有 < > 之類字元被當成 HTML 解析。
                    var frag = comboRowTemplate.content.cloneNode( true );
                    var tr = frag.querySelector('.ckc-spec-combo-row');
                    tr.setAttribute( 'data-combo-key', combo.key );
                    tr.querySelector('.ckc-spec-combo-label').textContent = combo.label;
                    var priceInput   = tr.querySelector('.ckc-combo-price');
                    var stockInput   = tr.querySelector('.ckc-combo-stock');
                    var enabledInput = tr.querySelector('.ckc-combo-enabled');
                    priceInput.name   = 'ckc_spec_combos[' + combo.key + '][price_adjust]';
                    stockInput.name   = 'ckc_spec_combos[' + combo.key + '][stock_qty]';
                    enabledInput.name = 'ckc_spec_combos[' + combo.key + '][enabled]';
                    var saved = liveCombos[ combo.key ];
                    if ( saved ) {
                        if ( typeof saved.price_adjust !== 'undefined' ) {
                            priceInput.value = saved.price_adjust;
                        }
                        if ( typeof saved.stock_qty !== 'undefined' ) {
                            stockInput.value = saved.stock_qty;
                        }
                        if ( typeof saved.enabled !== 'undefined' ) {
                            enabledInput.checked = ( saved.enabled === true || saved.enabled === '1' || saved.enabled === 1 );
                        }
                    }
                    comboBody.appendChild( tr );
                } );
            }

            rebuildCombos();
        })();
        </script>
    </div>
    <?php
}

/**
 * 6a. 單一規格類別區塊的 HTML（初次載入既有資料、以及 JS 樣板共用同一份，
 * 避免兩邊寫法兜不起來）。
 */
function chao_gang_cheng_render_spec_cat_block_html( $cat_index, $cat ) {
    $id     = isset( $cat['id'] ) ? $cat['id'] : '';
    $label  = isset( $cat['label'] ) ? $cat['label'] : '';
    $values = isset( $cat['values'] ) && is_array( $cat['values'] ) ? $cat['values'] : array();

    ob_start();
    ?>
    <div class="ckc-spec-cat-block" data-cat-index="<?php echo esc_attr( $cat_index ); ?>">
        <div class="ckc-spec-cat-header">
            <input type="hidden" name="ckc_spec_categories[<?php echo esc_attr( $cat_index ); ?>][id]" value="<?php echo esc_attr( $id ); ?>">
            <input type="text" name="ckc_spec_categories[<?php echo esc_attr( $cat_index ); ?>][label]" value="<?php echo esc_attr( $label ); ?>" placeholder="類別名稱，例如：日期">
            <a href="#" class="ckc-spec-remove-link ckc-spec-remove-cat">移除此類別</a>
        </div>
        <div class="ckc-spec-value-list">
            <?php foreach ( $values as $val_index => $val ) : ?>
                <?php echo chao_gang_cheng_render_spec_value_row_html( $cat_index, $val_index, $val ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <?php endforeach; ?>
        </div>
        <button type="button" class="button button-small ckc-spec-add-value">＋ 新增值</button>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * 6b. 單一規格值列的 HTML。
 */
function chao_gang_cheng_render_spec_value_row_html( $cat_index, $val_index, $val ) {
    $id    = isset( $val['id'] ) ? $val['id'] : '';
    $label = isset( $val['label'] ) ? $val['label'] : '';
    ob_start();
    ?>
    <div class="ckc-spec-value-row">
        <input type="hidden" name="ckc_spec_categories[<?php echo esc_attr( $cat_index ); ?>][values][<?php echo esc_attr( $val_index ); ?>][id]" value="<?php echo esc_attr( $id ); ?>">
        <input type="text" name="ckc_spec_categories[<?php echo esc_attr( $cat_index ); ?>][values][<?php echo esc_attr( $val_index ); ?>][label]" value="<?php echo esc_attr( $label ); ?>" placeholder="值，例如：8/15">
        <a href="#" class="ckc-spec-remove-link ckc-spec-remove-value">移除</a>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * 7. 儲存：規格類別／值＋組合定價庫存。掛在跟現有「規格說明」meta box
 * 相同的 save_post_product 慣例，並且用同一種 nonce 檢查方式。
 *
 * 安全性：組合資料只保留「確實對應目前送出的類別／值」組合出來的
 * combo key，避免表單被竄改夾帶無關的 key。
 */
add_action( 'save_post_product', 'chao_gang_cheng_save_spec_options' );
function chao_gang_cheng_save_spec_options( $post_id ) {
    if ( ! isset( $_POST['ckc_spec_options_nonce'] ) || ! wp_verify_nonce( $_POST['ckc_spec_options_nonce'], 'ckc_spec_options_save' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_product', $post_id ) ) {
        return;
    }

    // 存檔前先記住舊的組合有哪些 key，等一下存完新資料後用來清掉「不再
    // 存在的組合」殘留的即時庫存 meta（例如刪掉了某個規格值）。
    $old_combo_keys = array_keys( get_post_meta( $post_id, '_ckc_spec_combinations', true ) ?: array() );

    $raw_categories = isset( $_POST['ckc_spec_categories'] ) && is_array( $_POST['ckc_spec_categories'] )
        ? wp_unslash( $_POST['ckc_spec_categories'] )
        : array();

    $categories      = array();
    $valid_combo_keys = array();
    $per_cat_id_value_ids = array();

    foreach ( $raw_categories as $cat ) {
        $cat_id    = isset( $cat['id'] ) ? sanitize_key( $cat['id'] ) : '';
        $cat_label = isset( $cat['label'] ) ? sanitize_text_field( $cat['label'] ) : '';
        if ( '' === $cat_id || '' === $cat_label ) {
            continue;
        }
        $values = array();
        if ( isset( $cat['values'] ) && is_array( $cat['values'] ) ) {
            foreach ( $cat['values'] as $val ) {
                $val_id    = isset( $val['id'] ) ? sanitize_key( $val['id'] ) : '';
                $val_label = isset( $val['label'] ) ? sanitize_text_field( $val['label'] ) : '';
                if ( '' === $val_id || '' === $val_label ) {
                    continue;
                }
                $values[] = array( 'id' => $val_id, 'label' => $val_label );
            }
        }
        if ( empty( $values ) ) {
            continue; // 沒有任何值的類別沒有意義，不儲存
        }
        $categories[] = array( 'id' => $cat_id, 'label' => $cat_label, 'values' => $values );
        $per_cat_id_value_ids[ $cat_id ] = wp_list_pluck( $values, 'id' );
    }

    update_post_meta( $post_id, '_ckc_spec_categories', $categories );

    // 依「目前有效的類別／值」重新算出所有合法的組合 key（類別依 id 字母序），
    // 儲存組合資料時只收這個白名單內的 key，其餘一律丟棄。
    if ( ! empty( $per_cat_id_value_ids ) ) {
        ksort( $per_cat_id_value_ids );
        $combos = array( array() );
        foreach ( $per_cat_id_value_ids as $cat_id => $val_ids ) {
            $next = array();
            foreach ( $combos as $combo ) {
                foreach ( $val_ids as $val_id ) {
                    $next[] = array_merge( $combo, array( $cat_id . ':' . $val_id ) );
                }
            }
            $combos = $next;
        }
        foreach ( $combos as $parts ) {
            $valid_combo_keys[ implode( '|', $parts ) ] = true;
        }
    }

    $raw_combos = isset( $_POST['ckc_spec_combos'] ) && is_array( $_POST['ckc_spec_combos'] )
        ? wp_unslash( $_POST['ckc_spec_combos'] )
        : array();

    $combinations = array();
    foreach ( $raw_combos as $key => $data ) {
        $key = sanitize_text_field( $key );
        if ( ! isset( $valid_combo_keys[ $key ] ) ) {
            continue; // 不是目前有效的組合，不收
        }
        $price_adjust = isset( $data['price_adjust'] ) && '' !== $data['price_adjust'] ? (float) $data['price_adjust'] : 0;
        $stock_qty    = isset( $data['stock_qty'] ) && '' !== $data['stock_qty'] ? max( 0, (int) $data['stock_qty'] ) : null;
        $enabled      = ! empty( $data['enabled'] );
        $combinations[ $key ] = array(
            'price_adjust' => $price_adjust,
            'stock_qty'    => $stock_qty,
            'enabled'      => $enabled,
        );
    }

    update_post_meta( $post_id, '_ckc_spec_combinations', $combinations );

    // 第 4 期：把每個組合「管理員在後台填的庫存數字」同步到各自獨立的
    // 即時庫存 meta（訂單扣庫存/回補用的那份，見
    // chao_gang_cheng_adjust_spec_combo_stock() 的說明）。管理員在這裡
    // 存檔，等於是明確設定「現在庫存是多少」，會覆蓋掉先前訂單扣減留下
    // 的數字——跟 WooCommerce 原生商品庫存欄位的行為一致（後台填的數字
    // 就是目前庫存，之後新訂單才會繼續從這個數字往下扣）。
    foreach ( $combinations as $combo_key => $combo_data ) {
        $stock_meta_key = chao_gang_cheng_spec_stock_meta_key( $combo_key );
        if ( null !== $combo_data['stock_qty'] ) {
            update_post_meta( $post_id, $stock_meta_key, (int) $combo_data['stock_qty'] );
        } else {
            delete_post_meta( $post_id, $stock_meta_key ); // 改成不限制，清掉舊的庫存 meta
        }
    }
    // 清掉「這次存檔後已經不存在」的舊組合殘留的庫存 meta（例如刪除了某個規格值）。
    foreach ( $old_combo_keys as $old_key ) {
        if ( ! isset( $combinations[ $old_key ] ) ) {
            delete_post_meta( $post_id, chao_gang_cheng_spec_stock_meta_key( $old_key ) );
        }
    }
}
