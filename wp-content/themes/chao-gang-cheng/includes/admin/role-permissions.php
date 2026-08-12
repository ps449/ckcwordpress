<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 使用者權限管理（簡化版）。
 *
 * 背景：後台使用者列表（使用者 > 全部使用者）本身可以指派角色（網站管理員／
 * 編輯／作者／投稿者／訂閱者／客戶／商店經理），但 WordPress／WooCommerce
 * 預設的角色權限是寫死的，商家沒辦法自己決定「客服人員（例如指派成商店經理）
 * 能不能看到運費管理」「哪個角色能不能碰折價券點數」這種細節。這裡加一個
 * 獨立的後台頁面，讓網站管理員可以用勾選矩陣的方式，針對每個角色，決定
 * 他們登入後台後看不看得到／能不能打開每一個頂層選單（含這個主題自訂的
 * 運費管理、折價券點數…等，以及 WordPress／WooCommerce 原生的商品、
 * 使用者、外掛…等）。
 *
 * 設計取捨（商家已確認採用簡化版，而非安裝 User Role Editor 這類成熟外掛）：
 * - 權限granularity 只到「頂層選單」，不做到子選單、更不做到 WordPress
 *   capability 這種細粒度（例如沒辦法做到「能看訂單但不能刪除訂單」）。
 *   一個頂層選單被封鎖時，它底下所有子選單一併封鎖，簡化心智模型。
 * - 「網站管理員」角色永遠不受限、且無法在這裡被限制——避免設定錯誤時
 *   把自己或其他管理員鎖在後台外面，叫天不應叫地不靈。
 * - 預設（尚未替某個角色儲存過設定）＝完全不限制，維持 WordPress 原本的
 *   角色權限行為，這個功能是「選擇性疊加限制」，不會一裝上就打亂現有站台。
 * - 只做「隱藏選單」還不夠（懂網址的人可以直接輸入 URL 繞過），所以另外
 *   在 admin_init 擋一次直接存取；但要完整比對出「目前這一頁對應到哪個
 *   頂層選單 slug」，WordPress 沒有現成的通用函式可以做到 100% 全面，
 *   這裡涵蓋所有 admin.php?page=xxx 形式的自訂頁面（涵蓋這個主題目前所有
 *   自訂後台功能）＋常見的原生後台頁面（商品、文章、使用者、外掛、佈景
 *   主題、工具、設定、媒體、留言），未涵蓋到的冷門頁面保守起見不擋，
 *   避免誤傷正常操作。
 *
 * 修復（2026-08）：勾選矩陣「打勾＝有權限、取消＝隱藏且無權限」原本沒有
 * 真的生效——因為「出貨人員／財務人員／財務人員／客服人員」這三個角色
 * 實際上是把 WordPress 內建的 editor／author／contributor 角色換了個
 * 中文顯示名稱（見 functions.php 的 chao_gang_cheng_rename_admin_roles()，
 * 底層 capabilities 完全沒動）。這三個內建角色本來就沒有 manage_woocommerce
 * 之類的能力，所以就算在矩陣裡把「運費管理」「折價券點數」等功能打勾，
 * 使用者點進去還是會先被 WordPress 自己的權限檢查擋下（顯示「您沒有足夠
 * 的權限」），跟這個矩陣的勾選狀態無關——看起來就像「打勾沒有用」。
 * 下面 chao_gang_cheng_sync_staff_role_capabilities() 把「商店經理」
 * （shop_manager，WooCommerce 內建、專門設計給店員用的角色）目前擁有的
 * 所有 capability，同步授權給這三個角色，讓底層權限「夠用」，矩陣的勾選
 * 才能真正決定看不看得到、打不打得開——勾選的項目使用者才真正進得去，
 * 沒勾選的項目則交給下面既有的 chao_gang_cheng_enforce_role_menu_permissions()
 * ／chao_gang_cheng_block_direct_menu_access() 隱藏＋擋下直接存取。
 */

/**
 * 把「商店經理」現有的所有 capability，補給「出貨人員／財務人員／客服人員」
 * 這三個角色（底層是 editor／author／contributor，只是換了顯示名稱），讓
 * 「使用者權限管理」矩陣打勾的項目使用者才真的進得去（否則會先被 WordPress
 * 自己的權限檢查擋下，跟矩陣勾選狀態無關）。只用「新增」capability，不會
 * 拿掉這三個角色原本就有的能力（例如編輯文章），避免影響既有用法。
 *
 * 用一次性版本旗標避免每次頁面載入都重新寫入 wp_options（角色資料存在
 * wp_user_roles 這個 option 裡，add_cap() 每次呼叫都會整包重寫，量大時
 * 不必要地跑會有效能成本）。之後如果要調整補的 capability 內容，把
 * $version 字串改掉（例如 v3），就會讓已經跑過的站台重新同步一次。
 */
add_action( 'init', 'chao_gang_cheng_sync_staff_role_capabilities', 25 );
function chao_gang_cheng_sync_staff_role_capabilities() {
    $version = 'v3';
    if ( get_option( 'ckc_staff_role_caps_synced' ) === $version ) {
        return;
    }

    $shop_manager = get_role( 'shop_manager' );
    if ( ! $shop_manager ) {
        return; // WooCommerce 角色還沒建立好（例如剛啟用），旗標不設定，下次 init 會再試一次
    }

    $wc_caps = array_keys( array_filter( $shop_manager->capabilities ) );

    // 除了 WooCommerce 相關 capability（manage_woocommerce／edit_products／
    // manage_product_terms 等，商店經理本來就有），這個主題的自訂後台頁面
    // 還用到兩個 WordPress 原生 capability，是 WooCommerce 從來不會給任何
    // 角色（包含商店經理自己）的：
    // - edit_theme_options：選單管理、快捷列／加價專區／公告列等「網站功能」
    //   設定要求（實際比較接近網站外觀配置，不是敏感的系統設定）。
    // - edit_pages：「網站功能」父選單本身、「出貨AI助理」要求。
    // 2026-08 第三次調整（v3）：修正 v2 的疏漏——這兩個 capability 原本只
    // 補給出貨／財務／客服人員（editor／author／contributor），忘了商店
    // 經理（shop_manager）本身也一樣沒有這兩個 WordPress 原生 capability
    // （WooCommerce 建立這個角色時只給 WooCommerce 相關能力），導致商店
    // 經理勾選「首頁」「網站功能」整組後，一樣打不開。這裡把商店經理也
    // 加進補 capability 的名單（只補這兩個 extra_caps，不需要也不會重複
    // 複製一次自己的 WooCommerce capability）。
    $extra_caps = array( 'edit_theme_options', 'edit_pages' );

    $staff_roles = array( 'editor', 'author', 'contributor' );
    foreach ( $staff_roles as $role_slug ) {
        $role = get_role( $role_slug );
        if ( ! $role ) {
            continue;
        }
        foreach ( array_merge( $wc_caps, $extra_caps ) as $cap ) {
            $role->add_cap( $cap );
        }
    }

    foreach ( $extra_caps as $cap ) {
        $shop_manager->add_cap( $cap );
    }

    update_option( 'ckc_staff_role_caps_synced', $version );
}

/**
 * 取得可以被個別設定權限的角色清單（排除「網站管理員」，理由見上方說明）。
 *
 * @return array role_slug => 角色顯示名稱
 */
function chao_gang_cheng_get_manageable_roles() {
    if ( ! function_exists( 'get_editable_roles' ) ) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }
    $editable = get_editable_roles();
    $out = array();
    foreach ( $editable as $role_slug => $role ) {
        if ( 'administrator' === $role_slug ) {
            continue; // 網站管理員永遠完整權限，不開放在這裡設定
        }
        $out[ $role_slug ] = translate_user_role( $role['name'] );
    }
    return $out;
}

/**
 * 讀取目前已儲存的角色權限設定。
 *
 * 格式：array( role_slug => array( 允許的頂層選單 slug, ... ) )
 * 某個角色的 key 完全不存在＝該角色目前不受限制（維持 WordPress 預設權限）。
 */
function chao_gang_cheng_get_role_menu_permissions() {
    $saved = get_option( 'chao_gang_cheng_role_menu_permissions', array() );
    return is_array( $saved ) ? $saved : array();
}

/**
 * 讀取目前後台選單的完整清單（只抓頂層項目），給設定頁渲染勾選矩陣用。
 * 必須在 admin_menu 都跑完之後呼叫（也就是頁面渲染階段呼叫沒問題，因為
 * admin_menu 這個 hook 這時候一定已經全部執行完畢），才能抓到「最終」
 * 呈現在側邊欄的選單結構（含這個主題自己重新分組過的順序／分類標題）。
 *
 * 跳過：分隔線（wp-menu-separator）、我們自己插入的分類標題列
 * （slug 開頭是 #ckc-header-，見 ckc_reorganize_admin_menu_groups()）、
 * 「控制台」（index.php，永遠保留給所有角色，避免整個後台空白）。
 */
function chao_gang_cheng_get_admin_menu_inventory() {
    global $menu;
    $inventory = array();
    if ( ! is_array( $menu ) ) {
        return $inventory;
    }

    $current_group = '一般';
    foreach ( $menu as $item ) {
        if ( empty( $item[2] ) ) {
            continue;
        }
        $slug  = $item[2];
        $class = isset( $item[4] ) ? $item[4] : '';

        if ( false !== strpos( $class, 'wp-menu-separator' ) ) {
            continue;
        }
        if ( 0 === strpos( $slug, '#ckc-header-' ) ) {
            // 這是我們自己插入的分類標題列，記錄下來當作接下來項目的分組名稱，
            // 本身不列成一個可勾選的權限項目。
            $current_group = wp_strip_all_tags( isset( $item[0] ) ? $item[0] : '一般' );
            continue;
        }
        if ( 'index.php' === $slug ) {
            continue; // 控制台每個角色都保留，不需要也不應該被限制
        }

        // 選單文字裡常包著更新數量小紅點或其他徽章（例如「外掛 3」、
        // WordPress.com 加的方案徽章），這些 <span>…</span> 一律整段拿掉
        // 再 strip_tags，不然會變成文字黏在一起的奇怪殘留（例如
        // 「升級方案Commerce」）。這裡不限定特定 class 名稱，因為徽章的
        // class 命名不保證固定（尤其 WordPress.com 相關選單），乾脆對這個
        // 純粹拿來當權限清單標籤用的文字，直接整段拿掉所有 <span>。
        $raw_label = isset( $item[0] ) ? $item[0] : $slug;
        $raw_label = preg_replace( '/<span\b[^>]*>.*?<\/span>/is', '', $raw_label );
        $label = trim( wp_strip_all_tags( $raw_label ) );
        if ( '' === $label ) {
            $label = $slug;
        }

        $inventory[] = array(
            'slug'  => $slug,
            'label' => $label,
            'group' => $current_group,
        );
    }

    return $inventory;
}

/**
 * 後台選單：掛在「會員管理」分組底下（見 functions.php 的
 * ckc_reorganize_admin_menu_groups()，把這個 slug 加進『會員管理』分組
 * 陣列，才會排在使用者、分潤夥伴旁邊，符合這個功能本質上是「使用者
 * （角色）管理」的一部分）。
 */
add_action( 'admin_menu', 'ckc_role_permissions_menu' );
function ckc_role_permissions_menu() {
    add_menu_page(
        '使用者權限管理',
        '使用者權限管理',
        'manage_options',
        'ckc-role-permissions',
        'ckc_role_permissions_render_page',
        'dashicons-lock',
        58
    );
}

/**
 * 表單送出處理：儲存每個角色的選單權限設定。
 */
function ckc_role_permissions_handle_save() {
    if ( ! isset( $_POST['ckc_role_permissions_nonce'] ) ) {
        return null;
    }
    if ( ! wp_verify_nonce( $_POST['ckc_role_permissions_nonce'], 'ckc_role_permissions_save' ) ) {
        return false;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        return false;
    }

    $roles = chao_gang_cheng_get_manageable_roles();
    $raw_allowed      = isset( $_POST['ckc_role_perm'] ) && is_array( $_POST['ckc_role_perm'] ) ? wp_unslash( $_POST['ckc_role_perm'] ) : array();
    $raw_unrestricted = isset( $_POST['ckc_role_unrestricted'] ) && is_array( $_POST['ckc_role_unrestricted'] ) ? wp_unslash( $_POST['ckc_role_unrestricted'] ) : array();

    $new_settings = chao_gang_cheng_get_role_menu_permissions();

    foreach ( $roles as $role_slug => $role_label ) {
        if ( isset( $raw_unrestricted[ $role_slug ] ) ) {
            // 使用者勾選「此角色不限制」→ 直接移除這個角色的設定，
            // 等於恢復 WordPress 預設權限，不受這個功能影響。
            unset( $new_settings[ $role_slug ] );
            continue;
        }

        $allowed = isset( $raw_allowed[ $role_slug ] ) && is_array( $raw_allowed[ $role_slug ] )
            ? array_map( 'sanitize_text_field', $raw_allowed[ $role_slug ] )
            : array();

        // 2026-08 修正：原本這裡會強制把「使用者權限管理」塞進每個角色的
        // 允許清單，導致管理員在畫面上取消勾選這一項、按儲存後又被強制
        // 打勾，看起來像是勾不掉的 bug（商家實測回報）。
        // 移除這個強制邏輯的原因：這個頁面本身是掛
        // 'manage_options' 權限（見 ckc_role_permissions_menu()），而這裡
        // 能個別設定的角色（商店經理／出貨人員／財務人員／客服人員／
        // 行銷人員／倉管人員）依 WordPress／WooCommerce 預設都沒有
        // manage_options（WooCommerce 只給 shop_manager
        // manage_woocommerce，不是 manage_options）。也就是說，就算選單
        // 有顯示「使用者權限管理」，這些角色點進去也只會看到「您沒有
        // 權限存取此頁面」，強制保留選單可見性其實沒有實質保護作用，
        // 只會造成使用上的困惑。故拿掉，讓這個項目跟矩陣裡其他項目
        // 一樣完全由管理員自己勾選決定。
        $new_settings[ $role_slug ] = array_values( array_unique( $allowed ) );
    }

    update_option( 'chao_gang_cheng_role_menu_permissions', $new_settings, false );

    return true;
}

/**
 * 後台頁面渲染：勾選矩陣（列＝後台選單項目，依現有五大分類分組；
 * 欄＝可設定的角色），每個角色最上面有一個「此角色不限制」總開關。
 */
function ckc_role_permissions_render_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( '您沒有權限存取此頁面。', 'chao-gang-cheng' ) );
    }

    $save_result = null;
    if ( isset( $_POST['ckc_role_permissions_submit'] ) ) {
        $save_result = ckc_role_permissions_handle_save();
    }

    $roles     = chao_gang_cheng_get_manageable_roles();
    $settings  = chao_gang_cheng_get_role_menu_permissions();
    $inventory = chao_gang_cheng_get_admin_menu_inventory();

    // 依 group 分組，維持選單原本的走訪順序。
    $grouped = array();
    foreach ( $inventory as $row ) {
        $grouped[ $row['group'] ][] = $row;
    }
    ?>
    <div class="wrap ckc-role-permissions-wrap">
        <h1 class="wp-heading-inline">使用者權限管理</h1>
        <hr class="wp-header-end">
        <p style="max-width:760px;color:#555;">
            針對每個角色，勾選他們登入後台後可以看到、使用的功能（以左側選單的頂層項目為單位；某個項目被取消勾選時，它底下的子項目也會一併被隱藏、直接輸入網址也無法打開）。「網站管理員」角色權限完整、不在這裡設定，避免誤設把管理員自己鎖在後台外面。尚未設定過的角色維持 WordPress 預設權限，不受影響。
        </p>

        <?php if ( true === $save_result ) : ?>
            <div class="notice notice-success is-dismissible"><p>權限設定已儲存。</p></div>
        <?php elseif ( false === $save_result ) : ?>
            <div class="notice notice-error is-dismissible"><p>儲存失敗，請重新整理頁面後再試一次。</p></div>
        <?php endif; ?>

        <?php if ( empty( $roles ) ) : ?>
            <p>目前網站沒有可以個別設定的角色。</p>
            <?php return; ?>
        <?php endif; ?>

        <style>
            .ckc-role-permissions-wrap table.ckc-role-perm-table {
                border-collapse: collapse;
                background: #fff;
                border: 1px solid #dcdcde;
                margin-top: 16px;
                width: 100%;
            }
            .ckc-role-permissions-wrap table.ckc-role-perm-table th,
            .ckc-role-permissions-wrap table.ckc-role-perm-table td {
                border: 1px solid #e2e4e7;
                padding: 8px 10px;
                text-align: center;
                vertical-align: middle;
            }
            .ckc-role-permissions-wrap table.ckc-role-perm-table th {
                background: #f6f7f7;
                font-weight: 700;
            }
            .ckc-role-permissions-wrap table.ckc-role-perm-table td:first-child,
            .ckc-role-permissions-wrap table.ckc-role-perm-table th:first-child {
                text-align: left;
                min-width: 220px;
            }
            .ckc-role-permissions-wrap tr.ckc-role-perm-group-row td {
                background: #fbfbfc;
                font-weight: 700;
                text-align: left;
                color: #3a2f24;
            }
            .ckc-role-permissions-wrap tr.ckc-role-perm-unrestricted-row td {
                background: #fffaf1;
            }
            .ckc-role-permissions-wrap .ckc-role-perm-checkbox[disabled] {
                opacity: 0.35;
            }
            .ckc-role-permissions-wrap .ckc-role-perm-note {
                font-size: 12px;
                color: #8c7a64;
                margin-top: 4px;
            }
        </style>

        <form method="post">
            <?php wp_nonce_field( 'ckc_role_permissions_save', 'ckc_role_permissions_nonce' ); ?>

            <div style="overflow-x:auto;">
            <table class="ckc-role-perm-table">
                <thead>
                    <tr>
                        <th>後台功能</th>
                        <?php foreach ( $roles as $role_slug => $role_label ) : ?>
                            <th><?php echo esc_html( $role_label ); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr class="ckc-role-perm-unrestricted-row">
                        <td>此角色不限制（維持 WordPress 預設權限）</td>
                        <?php foreach ( $roles as $role_slug => $role_label ) :
                            $is_unrestricted = ! isset( $settings[ $role_slug ] );
                            ?>
                            <td>
                                <input
                                    type="checkbox"
                                    class="ckc-role-perm-unrestricted"
                                    name="ckc_role_unrestricted[<?php echo esc_attr( $role_slug ); ?>]"
                                    value="1"
                                    data-role="<?php echo esc_attr( $role_slug ); ?>"
                                    <?php checked( $is_unrestricted ); ?>
                                >
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php foreach ( $grouped as $group_label => $rows ) : ?>
                        <tr class="ckc-role-perm-group-row">
                            <td colspan="<?php echo esc_attr( count( $roles ) + 1 ); ?>"><?php echo esc_html( $group_label ); ?></td>
                        </tr>
                        <?php foreach ( $rows as $row ) : ?>
                            <tr>
                                <td><?php echo esc_html( $row['label'] ); ?></td>
                                <?php foreach ( $roles as $role_slug => $role_label ) :
                                    $is_unrestricted = ! isset( $settings[ $role_slug ] );
                                    $allowed_list    = $is_unrestricted ? array() : (array) $settings[ $role_slug ];
                                    $is_checked      = $is_unrestricted ? true : in_array( $row['slug'], $allowed_list, true );
                                    ?>
                                    <td>
                                        <input
                                            type="checkbox"
                                            class="ckc-role-perm-checkbox ckc-role-perm-checkbox-<?php echo esc_attr( $role_slug ); ?>"
                                            name="ckc_role_perm[<?php echo esc_attr( $role_slug ); ?>][]"
                                            value="<?php echo esc_attr( $row['slug'] ); ?>"
                                            <?php checked( $is_checked ); ?>
                                            <?php disabled( $is_unrestricted ); ?>
                                        >
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <p class="ckc-role-perm-note">勾選「此角色不限制」時，下面該角色欄位的勾選框會停用（該角色維持完整權限，不會送出限制清單）；取消「不限制」後就能個別勾選這個角色可以使用的功能。</p>

            <p style="margin-top:20px;">
                <button type="submit" name="ckc_role_permissions_submit" class="button button-primary">儲存權限設定</button>
            </p>
        </form>
    </div>

    <script>
    (function() {
        document.querySelectorAll('.ckc-role-perm-unrestricted').forEach(function(cb) {
            function sync() {
                var role = cb.getAttribute('data-role');
                document.querySelectorAll('.ckc-role-perm-checkbox-' + CSS.escape(role)).forEach(function(target) {
                    target.disabled = cb.checked;
                });
            }
            cb.addEventListener('change', sync);
            sync();
        });
    })();
    </script>
    <?php
}

/**
 * 隱藏受限角色看不到的頂層選單（含子選單，因為 WordPress 移除頂層項目時
 * 子選單本來就一併不會顯示）。網站管理員、或尚未替該角色儲存過設定的
 * 情況，完全不受影響。
 */
add_action( 'admin_menu', 'chao_gang_cheng_enforce_role_menu_permissions', 99999 );
function chao_gang_cheng_enforce_role_menu_permissions() {
    $user = wp_get_current_user();
    if ( ! $user || ! $user->exists() ) {
        return;
    }
    if ( in_array( 'administrator', (array) $user->roles, true ) ) {
        return;
    }

    $settings = chao_gang_cheng_get_role_menu_permissions();
    if ( empty( $settings ) ) {
        return; // 完全沒有任何角色被設定過，不影響任何人
    }

    $user_roles = (array) $user->roles;

    // 使用者可能身兼多個角色：只要其中一個角色沒被設定過（＝不受限），
    // 就整體不限制，採最寬鬆認定，避免多角色使用者被過度限制。
    foreach ( $user_roles as $role_slug ) {
        if ( ! isset( $settings[ $role_slug ] ) ) {
            return;
        }
    }

    // 取所有角色允許清單的聯集（只要某個角色允許，就允許）。
    $allowed_union = array();
    foreach ( $user_roles as $role_slug ) {
        if ( isset( $settings[ $role_slug ] ) && is_array( $settings[ $role_slug ] ) ) {
            $allowed_union = array_merge( $allowed_union, $settings[ $role_slug ] );
        }
    }
    $allowed_union   = array_unique( $allowed_union );
    $allowed_union[] = 'index.php'; // 控制台永遠保留

    global $menu;
    if ( ! is_array( $menu ) ) {
        return;
    }

    foreach ( $menu as $item ) {
        if ( empty( $item[2] ) ) {
            continue;
        }
        $slug  = $item[2];
        $class = isset( $item[4] ) ? $item[4] : '';

        if ( false !== strpos( $class, 'wp-menu-separator' ) ) {
            continue;
        }
        if ( 0 === strpos( $slug, '#ckc-header-' ) ) {
            continue; // 分類標題列本身不是可勾選項目，交給旁邊真正的項目決定去留
        }
        if ( ! in_array( $slug, $allowed_union, true ) ) {
            remove_menu_page( $slug );
        }
    }
}

/**
 * 修復（2026-08）：矩陣的勾選單位是「頂層選單」，但這個主題有好幾組頂層
 * 選單底下掛了一大串子選單，而且子選單的 slug 通常跟父選單完全不同字串
 * （例如「首頁」父選單 slug 是 ckc-homepage-builder，底下「快捷列設定」
 * 子選單卻是完全不相關的 ckc-floating-btns；WooCommerce／商品／折價券
 * 點數這幾組也都一樣）。下面 chao_gang_cheng_resolve_current_admin_slug()
 * 原本直接把 $_GET['page'] 當成要比對的 slug，導致就算矩陣打勾了「首頁」
 * 整組，使用者點進「快捷列設定」「LOGO設定」這類子選單時，比對的是
 * ckc-floating-btns／ckc-site-logo 這些子選單自己的 slug，根本不在允許
 * 清單裡（清單裡存的是父選單 slug ckc-homepage-builder），結果被誤擋
 * 顯示「權限不足」——即使那一整組明明已經打勾允許。
 *
 * 這個函式把任何子選單 slug，透過 WordPress 全域 $submenu 陣列（記錄每個
 * 父選單 slug 底下掛了哪些子選單，跟目前使用者權限無關，一定拿得到完整
 * 清單）反查回它所屬的頂層父選單 slug，讓比對的對象永遠跟矩陣裡存的
 * （頂層）slug 一致。如果傳進來的 slug 本身就是頂層選單（或找不到所屬
 * 父選單，例如少數沒有掛在任何自訂父選單底下的獨立頁面），原樣傳回。
 */
function chao_gang_cheng_resolve_slug_to_top_level( $slug ) {
    global $menu, $submenu;

    if ( is_array( $menu ) ) {
        foreach ( $menu as $item ) {
            if ( isset( $item[2] ) && $item[2] === $slug ) {
                return $slug; // 本身就是頂層選單，不需要轉換
            }
        }
    }

    if ( is_array( $submenu ) ) {
        foreach ( $submenu as $parent_slug => $items ) {
            foreach ( (array) $items as $item ) {
                if ( isset( $item[2] ) && $item[2] === $slug ) {
                    return $parent_slug;
                }
            }
        }
    }

    return $slug; // 找不到所屬父選單，原樣傳回
}

/**
 * 把目前這個後台頁面請求，對應回它所屬的「頂層選單 slug」，給下面
 * chao_gang_cheng_block_direct_menu_access() 用來比對權限。
 *
 * 這是簡化版比對，不追求涵蓋 WordPress 後台每一個可能的頁面：
 * - admin.php?page=xxx 形式的頁面（這個主題所有自訂後台功能都是這個
 *   形式），先取出 $_GET['page']，再用上面 chao_gang_cheng_resolve_slug_to_top_level()
 *   反查回真正的頂層父選單 slug（子選單 slug 常跟父選單完全不同字串）。
 * - 商品／文章這類依 post_type 分開的原生列表頁／編輯頁，組回
 *   'edit.php?post_type=xxx' 這個跟 $menu 裡登記的 slug 完全一致的格式。
 * - 使用者、外掛、佈景主題、工具、設定、媒體、留言這幾個最常見的原生
 *   後台分類，各自歸戶到對應的頂層 slug。
 * - 其他沒特別處理到的冷門頁面，回傳 null（=不擋，保守起見避免誤傷
 *   正常功能）。
 *
 * @return string|null
 */
function chao_gang_cheng_resolve_current_admin_slug() {
    global $pagenow;

    if ( ! empty( $_GET['page'] ) ) {
        return chao_gang_cheng_resolve_slug_to_top_level( sanitize_text_field( wp_unslash( $_GET['page'] ) ) );
    }

    if ( in_array( $pagenow, array( 'edit.php', 'post.php', 'post-new.php' ), true ) ) {
        $post_type = '';
        if ( ! empty( $_GET['post_type'] ) ) {
            $post_type = sanitize_text_field( wp_unslash( $_GET['post_type'] ) );
        } elseif ( ! empty( $_GET['post'] ) ) {
            $existing = get_post( absint( $_GET['post'] ) );
            $post_type = $existing ? $existing->post_type : 'post';
        } else {
            $post_type = 'post';
        }
        return 'post' === $post_type ? 'edit.php' : 'edit.php?post_type=' . $post_type;
    }

    if ( in_array( $pagenow, array( 'users.php', 'user-new.php', 'user-edit.php' ), true ) ) {
        return 'users.php';
    }
    if ( in_array( $pagenow, array( 'plugins.php', 'plugin-install.php', 'plugin-editor.php' ), true ) ) {
        return 'plugins.php';
    }
    if ( in_array( $pagenow, array( 'themes.php', 'theme-editor.php', 'customize.php' ), true ) ) {
        return 'themes.php';
    }
    if ( in_array( $pagenow, array( 'tools.php', 'import.php', 'export.php', 'site-health.php' ), true ) ) {
        return 'tools.php';
    }
    if ( 0 === strpos( (string) $pagenow, 'options-' ) ) {
        return 'options-general.php';
    }
    if ( in_array( $pagenow, array( 'upload.php', 'media-new.php' ), true ) ) {
        return 'upload.php';
    }
    if ( 'edit-comments.php' === $pagenow ) {
        return 'edit-comments.php';
    }

    return null;
}

/**
 * 擋下直接輸入網址存取「選單被隱藏」頁面的行為——單純隱藏選單連結還不夠，
 * 知道網址的人還是可以直接打開。個人資料頁、AJAX 端點、控制台一律放行，
 * 這幾個是每個角色本來就該能用／背景需要用到的頁面。
 */
add_action( 'admin_init', 'chao_gang_cheng_block_direct_menu_access' );
function chao_gang_cheng_block_direct_menu_access() {
    if ( wp_doing_ajax() ) {
        return;
    }

    $user = wp_get_current_user();
    if ( ! $user || ! $user->exists() ) {
        return;
    }
    if ( in_array( 'administrator', (array) $user->roles, true ) ) {
        return;
    }

    $settings = chao_gang_cheng_get_role_menu_permissions();
    if ( empty( $settings ) ) {
        return;
    }

    $user_roles = (array) $user->roles;
    foreach ( $user_roles as $role_slug ) {
        if ( ! isset( $settings[ $role_slug ] ) ) {
            return; // 有一個角色不受限，整體不擋
        }
    }

    global $pagenow;
    $always_allowed = array( 'index.php', 'profile.php', 'admin-ajax.php', 'async-upload.php' );
    if ( in_array( $pagenow, $always_allowed, true ) ) {
        return;
    }

    $current_slug = chao_gang_cheng_resolve_current_admin_slug();
    if ( null === $current_slug ) {
        return; // 判斷不出來的頁面保守起見不擋
    }

    $allowed_union = array();
    foreach ( $user_roles as $role_slug ) {
        if ( isset( $settings[ $role_slug ] ) && is_array( $settings[ $role_slug ] ) ) {
            $allowed_union = array_merge( $allowed_union, $settings[ $role_slug ] );
        }
    }
    $allowed_union = array_unique( $allowed_union );

    if ( ! in_array( $current_slug, $allowed_union, true ) ) {
        wp_die(
            '<h1>權限不足</h1><p>您的帳號角色目前沒有開放使用這個後台功能，如有需要請聯繫網站管理員。</p>',
            '權限不足',
            array( 'response' => 403, 'back_link' => true )
        );
    }
}
