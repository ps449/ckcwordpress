<?php
// 全站彈窗廣告系統
// WordPress 後台：外觀 > 彈窗廣告設定
// =============================================================================

/**
 * 21a. 後台選單 —「首頁」頂層選單底下的子選單「彈窗管理」
 * 原本是獨立頂層選單（位置 30），現在收整到 ckc-homepage-builder.php
 * 註冊的「首頁」頂層選單底下，用 admin_menu 優先權 11（緊接在首頁編輯
 * 的預設優先權 10 之後）確保在子選單列表中排第 2 順位。
 */
add_action( 'admin_menu', 'ckc_popup_add_menu', 11 );
function ckc_popup_add_menu() {
    add_submenu_page(
        'ckc-homepage-builder',
        '彈窗管理',
        '彈窗管理',
        'edit_theme_options', // 權限（2026-08 由 manage_options 調整，見「使用者權限管理」說明）
        'ckc-popup-settings',
        'ckc_popup_page_html'
    );
}

/**
 * 21b. 在後台頁面載入媒體庫腳本
 *
 * 注意：不要用 $hook（hook suffix）比對。這個 hook suffix 是拿父選單
 * 「首頁」的選單標題（中文）去跑 sanitize_title() 組出來的，實測發現
 * 就算改用 add_submenu_page() 的回傳值存起來比對，理論上該是同一個值，
 * 實測卻還是比對不上。改成直接比對網址上的 $_GET['page']，不透過 hook
 * suffix 這條容易受中文選單標題影響的路徑，最直接也最不受影響。
 */
add_action( 'admin_enqueue_scripts', 'ckc_popup_enqueue_admin_scripts' );
function ckc_popup_enqueue_admin_scripts( $hook ) {
    if ( empty( $_GET['page'] ) || 'ckc-popup-settings' !== $_GET['page'] ) return;
    wp_enqueue_media();
}

/**
 * 21c. 向 Settings API 註冊設定
 */
add_action( 'admin_init', 'ckc_popup_register_settings' );
function ckc_popup_register_settings() {
    register_setting(
        'ckc_popup_group',
        'chao_gang_cheng_popup',
        array( 'sanitize_callback' => 'ckc_popup_sanitize' )
    );
}

/**
 * 21d. 資料清理
 */
function ckc_popup_sanitize( $input ) {
    $clean = array();
    $clean['enabled']        = ! empty( $input['enabled'] )       ? '1' : '';
    $clean['image_id']       = isset( $input['image_id'] )        ? absint( $input['image_id'] )                       : 0;
    $clean['link_url']       = isset( $input['link_url'] )        ? esc_url_raw( trim( $input['link_url'] ) )           : '';
    $clean['link_target']    = ! empty( $input['link_target'] )   ? '_blank'                                            : '_self';
    $clean['show_home']      = ! empty( $input['show_home'] )     ? '1' : '';
    $clean['show_shop']      = ! empty( $input['show_shop'] )     ? '1' : '';
    $clean['show_product']   = ! empty( $input['show_product'] )  ? '1' : '';
    $clean['show_mobile']    = ! empty( $input['show_mobile'] )   ? '1' : '';
    $clean['show_desktop']   = ! empty( $input['show_desktop'] )  ? '1' : '';
    $clean['cookie_days']    = isset( $input['cookie_days'] )     ? intval( $input['cookie_days'] )                     : 1;
    $clean['delay_seconds']  = isset( $input['delay_seconds'] )   ? max( 0, intval( $input['delay_seconds'] ) )         : 0;
    return $clean;
}

/**
 * 21e. 後台設定頁面 HTML
 */
function ckc_popup_page_html() {
    if ( ! current_user_can( 'edit_theme_options' ) ) {
        wp_die( '您沒有權限存取此頁面。' );
    }

    $opts = wp_parse_args(
        get_option( 'chao_gang_cheng_popup', array() ),
        array(
            'enabled'       => '1',
            'image_id'      => 0,
            'link_url'      => '',
            'link_target'   => '_blank',
            'show_home'     => '1',
            'show_shop'     => '1',
            'show_product'  => '1',
            'show_mobile'   => '1',
            'show_desktop'  => '1',
            'cookie_days'   => 1,
            'delay_seconds' => 0,
        )
    );

    $image_url = $opts['image_id'] ? wp_get_attachment_url( $opts['image_id'] ) : '';
    ?>
    <div class="wrap">
        <h1 style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:26px;">🎯</span>
            彈窗廣告設定
        </h1>
        <p style="color:#666;margin-top:4px;">設定前台自動彈出的廣告圖片，可指定顯示頁面與顯示頻率。</p>
        <hr style="margin:18px 0;">

        <?php if ( isset( $_GET['settings-updated'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><strong>✅ 彈窗設定已儲存！</strong></p>
        </div>
        <?php endif; ?>

        <form method="post" action="options.php" style="max-width:720px;">
            <?php settings_fields( 'ckc_popup_group' ); ?>

            <!-- ── 總開關 ── -->
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:20px 24px;margin-bottom:20px;box-shadow:0 1px 4px rgba(0,0,0,.05);">
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <h3 style="margin:0 0 4px;">啟用彈窗廣告</h3>
                        <p style="margin:0;color:#888;font-size:13px;">關閉後全站停止顯示彈窗</p>
                    </div>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="chao_gang_cheng_popup[enabled]" value="1" <?php checked( '1', $opts['enabled'] ); ?> style="width:20px;height:20px;cursor:pointer;">
                        <span style="font-size:15px;font-weight:600;color:#333;">啟用</span>
                    </label>
                </div>
            </div>

            <!-- ── 彈窗圖片 ── -->
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:20px 24px;margin-bottom:20px;box-shadow:0 1px 4px rgba(0,0,0,.05);">
                <h3 style="margin:0 0 14px;">📸 彈窗圖片</h3>
                <input type="hidden" name="chao_gang_cheng_popup[image_id]" id="ckc-popup-image-id" value="<?php echo esc_attr( $opts['image_id'] ); ?>">

                <div id="ckc-popup-preview" style="margin-bottom:14px;<?php echo $image_url ? '' : 'display:none;'; ?>">
                    <img id="ckc-popup-preview-img" src="<?php echo esc_url( $image_url ); ?>"
                         style="max-width:320px;max-height:200px;object-fit:contain;border-radius:6px;border:1px solid #ddd;">
                </div>

                <div id="ckc-popup-placeholder" style="<?php echo $image_url ? 'display:none;' : ''; ?>background:#f5f5f5;border:2px dashed #ddd;border-radius:8px;padding:30px;text-align:center;margin-bottom:14px;color:#aaa;">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1.5" style="margin-bottom:8px;"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                    <p style="margin:0;font-size:14px;">尚未設定彈窗圖片</p>
                </div>

                <div style="display:flex;gap:10px;">
                    <button type="button" id="ckc-popup-select-img" class="button button-secondary" style="height:36px;padding:0 16px;">
                        🖼️ 從媒體庫選取
                    </button>
                    <button type="button" id="ckc-popup-remove-img" class="button" style="height:36px;padding:0 16px;color:#c00;<?php echo $image_url ? '' : 'display:none;'; ?>">
                        ✕ 移除圖片
                    </button>
                </div>
                <p style="margin:8px 0 0;font-size:12px;color:#aaa;">建議圖片尺寸：600×600px 或 600×800px，JPG/PNG 格式</p>
            </div>

            <!-- ── 點擊連結 ── -->
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:20px 24px;margin-bottom:20px;box-shadow:0 1px 4px rgba(0,0,0,.05);">
                <h3 style="margin:0 0 14px;">🔗 點擊連結</h3>
                <div style="background:#f9f9f9;border-radius:7px;padding:14px 16px;margin-bottom:12px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:6px;">點擊圖片後跳轉的網址（可留空）</label>
                    <input type="url" name="chao_gang_cheng_popup[link_url]"
                           value="<?php echo esc_attr( $opts['link_url'] ); ?>"
                           placeholder="https://example.com/..."
                           style="width:100%;padding:9px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;box-sizing:border-box;">
                </div>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="chao_gang_cheng_popup[link_target]" value="_blank"
                           <?php checked( '_blank', $opts['link_target'] ); ?>
                           style="width:16px;height:16px;cursor:pointer;">
                    <span style="font-size:14px;color:#333;">在新分頁開啟連結</span>
                </label>
            </div>

            <!-- ── 顯示頁面 ── -->
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:20px 24px;margin-bottom:20px;box-shadow:0 1px 4px rgba(0,0,0,.05);">
                <h3 style="margin:0 0 14px;">📄 顯示頁面</h3>
                <p style="margin:0 0 14px;color:#666;font-size:13px;">勾選彈窗要出現的頁面類型（可複選）</p>
                <div style="display:flex;flex-direction:column;gap:12px;">
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:12px 16px;border:1px solid #eee;border-radius:8px;transition:background .15s;" onmouseover="this.style.background='#f9f9f9'" onmouseout="this.style.background='#fff'">
                        <input type="checkbox" name="chao_gang_cheng_popup[show_home]" value="1" <?php checked( '1', $opts['show_home'] ); ?> style="width:18px;height:18px;cursor:pointer;">
                        <span style="font-size:24px;"></span>
                        <div>
                            <strong style="font-size:14px;">首頁</strong>
                            <p style="margin:2px 0 0;font-size:12px;color:#aaa;">網站首頁（front-page / home）</p>
                        </div>
                    </label>
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:12px 16px;border:1px solid #eee;border-radius:8px;transition:background .15s;" onmouseover="this.style.background='#f9f9f9'" onmouseout="this.style.background='#fff'">
                        <input type="checkbox" name="chao_gang_cheng_popup[show_shop]" value="1" <?php checked( '1', $opts['show_shop'] ); ?> style="width:18px;height:18px;cursor:pointer;">
                        <span style="font-size:24px;">🛍️</span>
                        <div>
                            <strong style="font-size:14px;">商品分類頁</strong>
                            <p style="margin:2px 0 0;font-size:12px;color:#aaa;">商店主頁與所有商品分類頁</p>
                        </div>
                    </label>
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:12px 16px;border:1px solid #eee;border-radius:8px;transition:background .15s;" onmouseover="this.style.background='#f9f9f9'" onmouseout="this.style.background='#fff'">
                        <input type="checkbox" name="chao_gang_cheng_popup[show_product]" value="1" <?php checked( '1', $opts['show_product'] ); ?> style="width:18px;height:18px;cursor:pointer;">
                        <span style="font-size:24px;"></span>
                        <div>
                            <strong style="font-size:14px;">商品詳情頁</strong>
                            <p style="margin:2px 0 0;font-size:12px;color:#aaa;">所有單一商品頁面</p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- ── 顯示裝置 ── -->
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:20px 24px;margin-bottom:20px;box-shadow:0 1px 4px rgba(0,0,0,.05);">
                <h3 style="margin:0 0 14px;">📱💻 顯示裝置</h3>
                <p style="margin:0 0 14px;color:#666;font-size:13px;">選擇彈窗要在哪種裝置上顯示（可複選，兩個都不勾等於全部裝置都不顯示）</p>
                <div style="display:flex;gap:16px;">
                    <label style="flex:1;display:flex;align-items:center;gap:10px;cursor:pointer;padding:12px 16px;border:1px solid #eee;border-radius:8px;transition:background .15s;" onmouseover="this.style.background='#f9f9f9'" onmouseout="this.style.background='#fff'">
                        <input type="checkbox" name="chao_gang_cheng_popup[show_mobile]" value="1" <?php checked( '1', $opts['show_mobile'] ); ?> style="width:18px;height:18px;cursor:pointer;">
                        <span style="font-size:24px;">📱</span>
                        <div>
                            <strong style="font-size:14px;">手機版</strong>
                            <p style="margin:2px 0 0;font-size:12px;color:#aaa;">手機瀏覽器造訪時顯示</p>
                        </div>
                    </label>
                    <label style="flex:1;display:flex;align-items:center;gap:10px;cursor:pointer;padding:12px 16px;border:1px solid #eee;border-radius:8px;transition:background .15s;" onmouseover="this.style.background='#f9f9f9'" onmouseout="this.style.background='#fff'">
                        <input type="checkbox" name="chao_gang_cheng_popup[show_desktop]" value="1" <?php checked( '1', $opts['show_desktop'] ); ?> style="width:18px;height:18px;cursor:pointer;">
                        <span style="font-size:24px;">💻</span>
                        <div>
                            <strong style="font-size:14px;">桌機版</strong>
                            <p style="margin:2px 0 0;font-size:12px;color:#aaa;">電腦瀏覽器造訪時顯示</p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- ── 顯示設定 ── -->
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:20px 24px;margin-bottom:28px;box-shadow:0 1px 4px rgba(0,0,0,.05);">
                <h3 style="margin:0 0 14px;">顯示設定</h3>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div style="background:#f9f9f9;border-radius:7px;padding:14px 16px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:8px;">🍪 顯示頻率</label>
                        <select name="chao_gang_cheng_popup[cookie_days]"
                                style="width:100%;padding:9px 10px;border:1px solid #ddd;border-radius:6px;font-size:14px;background:#fff;">
                            <option value="0"  <?php selected( 0,  $opts['cookie_days'] ); ?>>每次造訪都顯示</option>
                            <option value="1"  <?php selected( 1,  $opts['cookie_days'] ); ?>>每天 1 次</option>
                            <option value="3"  <?php selected( 3,  $opts['cookie_days'] ); ?>>每 3 天 1 次</option>
                            <option value="7"  <?php selected( 7,  $opts['cookie_days'] ); ?>>每週 1 次</option>
                            <option value="30" <?php selected( 30, $opts['cookie_days'] ); ?>>每月 1 次</option>
                        </select>
                        <p style="margin:6px 0 0;font-size:12px;color:#aaa;">關閉彈窗後隔幾天再次顯示</p>
                    </div>
                    <div style="background:#f9f9f9;border-radius:7px;padding:14px 16px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:8px;">⏱️ 延遲顯示（秒）</label>
                        <input type="number" name="chao_gang_cheng_popup[delay_seconds]"
                               value="<?php echo esc_attr( $opts['delay_seconds'] ); ?>"
                               min="0" max="30" step="1"
                               style="width:100%;padding:9px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;box-sizing:border-box;">
                        <p style="margin:6px 0 0;font-size:12px;color:#aaa;">進入頁面後幾秒彈出（0 = 立即）</p>
                    </div>
                </div>
            </div>

            <div style="display:flex;align-items:center;gap:12px;">
                <?php submit_button( '💾 儲存設定', 'primary large', 'submit', false, array( 'style' => 'height:44px;padding:0 28px;font-size:15px;font-weight:600;border-radius:8px;' ) ); ?>
                <span style="font-size:13px;color:#aaa;">設定儲存後立即生效於前台</span>
            </div>
        </form>

        <hr style="margin:32px 0 20px;">
        <p style="font-size:12px;color:#bbb;">潮港城客製電商主題 · 彈窗廣告設定 · 由 Antigravity AI 建置</p>
    </div>

    <script>
    (function($) {
        var mediaFrame;

        $('#ckc-popup-select-img').on('click', function(e) {
            e.preventDefault();
            if (mediaFrame) { mediaFrame.open(); return; }
            mediaFrame = wp.media({
                title: '選取彈窗圖片',
                button: { text: '使用此圖片' },
                multiple: false,
                library: { type: 'image' }
            });
            mediaFrame.on('select', function() {
                var attachment = mediaFrame.state().get('selection').first().toJSON();
                $('#ckc-popup-image-id').val(attachment.id);
                $('#ckc-popup-preview-img').attr('src', attachment.url);
                $('#ckc-popup-preview').show();
                $('#ckc-popup-placeholder').hide();
                $('#ckc-popup-remove-img').show();
            });
            mediaFrame.open();
        });

        $('#ckc-popup-remove-img').on('click', function() {
            $('#ckc-popup-image-id').val('0');
            $('#ckc-popup-preview-img').attr('src', '');
            $('#ckc-popup-preview').hide();
            $('#ckc-popup-placeholder').show();
            $(this).hide();
        });
    })(jQuery);
    </script>
    <?php
}


/**
 * 21f. 前台彈窗渲染
 */
add_action( 'wp_footer', 'ckc_popup_render' );
function ckc_popup_render() {
    $opts = wp_parse_args(
        get_option( 'chao_gang_cheng_popup', array() ),
        array(
            'enabled'       => '',
            'image_id'      => 0,
            'link_url'      => '',
            'link_target'   => '_blank',
            'show_home'     => '1',
            'show_shop'     => '1',
            'show_product'  => '1',
            'show_mobile'   => '1',
            'show_desktop'  => '1',
            'cookie_days'   => 1,
            'delay_seconds' => 0,
        )
    );

    // 1. 總開關
    if ( empty( $opts['enabled'] ) ) return;

    // 2. 必須有圖片
    if ( empty( $opts['image_id'] ) ) return;
    $image_url = wp_get_attachment_url( $opts['image_id'] );
    if ( ! $image_url ) return;

    // 3. 判斷裝置是否應顯示（手機／桌機分開設定，兩個都沒勾就完全不顯示）
    $is_mobile = wp_is_mobile();
    if ( $is_mobile ) {
        if ( empty( $opts['show_mobile'] ) ) return;
    } else {
        if ( empty( $opts['show_desktop'] ) ) return;
    }

    // 4. 判斷當前頁面是否應顯示
    $should_show = false;
    if ( ! empty( $opts['show_home'] )    && ( is_front_page() || is_home() ) ) $should_show = true;
    if ( ! empty( $opts['show_shop'] )    && ( is_shop() || is_product_taxonomy() ) )  $should_show = true;
    if ( ! empty( $opts['show_product'] ) && is_product() )                     $should_show = true;

    if ( ! $should_show ) return;

    // 5. 準備參數
    $cookie_days   = intval( $opts['cookie_days'] );
    $delay_ms      = intval( $opts['delay_seconds'] ) * 1000;
    $link_url      = esc_url( $opts['link_url'] );
    $link_target   = $opts['link_target'] === '_blank' ? '_blank' : '_self';
    $image_alt     = get_post_meta( $opts['image_id'], '_wp_attachment_image_alt', true );
    $image_alt     = $image_alt ? esc_attr( $image_alt ) : '彈窗廣告';
    ?>
    <!-- CKC Popup Ad -->
    <style>
    #ckc-popup-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.72);
        z-index: 999999;
        align-items: center;
        justify-content: center;
        padding: 20px;
        box-sizing: border-box;
    }
    #ckc-popup-overlay.ckc-active {
        display: flex;
        animation: ckcFadeIn 0.35s ease;
    }
    @keyframes ckcFadeIn {
        from { opacity: 0; }
        to   { opacity: 1; }
    }
    #ckc-popup-box {
        position: relative;
        max-width: 600px;
        width: 100%;
        animation: ckcSlideUp 0.35s ease;
    }
    @keyframes ckcSlideUp {
        from { transform: translateY(30px); opacity: 0; }
        to   { transform: translateY(0);   opacity: 1; }
    }
    #ckc-popup-box img {
        width: 100%;
        height: auto;
        display: block;
        border-radius: 8px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.5);
    }
    #ckc-popup-close {
        position: absolute;
        top: -18px;
        right: -18px;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #fff;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        font-weight: 700;
        color: #333;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        transition: background 0.2s, transform 0.2s;
        z-index: 10;
        line-height: 1;
        padding: 0;
    }
    #ckc-popup-close:hover {
        background: #f0f0f0;
        transform: scale(1.1);
    }
    @media (max-width: 480px) {
        #ckc-popup-close {
            top: -14px;
            right: -6px;
            width: 34px;
            height: 34px;
            font-size: 18px;
        }
    }
    </style>

    <div id="ckc-popup-overlay" role="dialog" aria-modal="true" aria-label="廣告彈窗">
        <div id="ckc-popup-box">
            <button id="ckc-popup-close" type="button" aria-label="關閉彈窗">×</button>
            <?php if ( $link_url ) : ?>
            <a href="<?php echo $link_url; ?>" target="<?php echo esc_attr( $link_target ); ?>" rel="noopener">
                <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo $image_alt; ?>">
            </a>
            <?php else : ?>
            <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo $image_alt; ?>">
            <?php endif; ?>
        </div>
    </div>

    <script>
    (function() {
        var COOKIE_KEY  = 'ckc_popup_closed';
        var COOKIE_DAYS = <?php echo $cookie_days; ?>;
        var DELAY_MS    = <?php echo $delay_ms; ?>;

        // Check cookie
        function getCookie(name) {
            var v = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
            return v ? v.pop() : '';
        }
        function setCookie(name, value, days) {
            var expires = '';
            if (days > 0) {
                var d = new Date();
                d.setTime(d.getTime() + days * 86400000);
                expires = '; expires=' + d.toUTCString();
            }
            document.cookie = name + '=' + value + expires + '; path=/; SameSite=Lax';
        }

        if (COOKIE_DAYS > 0 && getCookie(COOKIE_KEY) === '1') return;

        var overlay = document.getElementById('ckc-popup-overlay');
        var closeBtn = document.getElementById('ckc-popup-close');

        function closePopup() {
            overlay.classList.remove('ckc-active');
            overlay.style.display = 'none';
            if (COOKIE_DAYS > 0) {
                setCookie(COOKIE_KEY, '1', COOKIE_DAYS);
            }
        }

        function openPopup() {
            overlay.classList.add('ckc-active');
        }

        // Open after delay
        setTimeout(openPopup, DELAY_MS);

        // Close on button click
        closeBtn.addEventListener('click', closePopup);

        // Close on overlay click (not box)
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) closePopup();
        });

        // Close on ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closePopup();
        });
    })();
    </script>
    <?php
}
