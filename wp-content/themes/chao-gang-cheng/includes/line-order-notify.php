<?php
/**
 * LINE 訂單成功通知
 *
 * 客戶訂單第一次轉入「處理中」或「已完成」狀態時，透過 LINE Messaging API
 * 推播通知到指定的 LINE 群組（或個人帳號）。LINE Notify 已於 2025 年
 * 終止服務，改用官方推薦的替代方案 Messaging API Push Message。
 *
 * 觸發時機說明：
 * - ECPay 站內付／LINE Pay／ATM／超商代碼：付款成功會呼叫
 *   $order->payment_complete()，狀態變成 processing。
 * - 超商取貨付款（COD）：WooCommerce 內建 COD 閘道不會呼叫
 *   payment_complete()，而是直接把狀態改成 processing。
 * 兩種路徑最後都會反映在訂單狀態變成 processing 或 completed，所以統一
 * 掛在 woocommerce_order_status_changed，只要是「第一次」轉入這兩種狀態
 * 就通知，並用訂單 meta 記錄避免重複發送。
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/* ------------------------------------------------------------------ *
 * 0. 偵錯記錄
 * ------------------------------------------------------------------ */

function chao_line_notify_log( $message ) {
    $timestamp = date( 'Y-m-d H:i:s' );
    $log_entry = "[{$timestamp}] {$message}";

    $logs = get_option( 'chao_line_notify_debug_log', array() );
    if ( ! is_array( $logs ) ) {
        $logs = array();
    }

    $logs[] = $log_entry;
    if ( count( $logs ) > 100 ) {
        array_shift( $logs );
    }

    update_option( 'chao_line_notify_debug_log', $logs, false );
}

/* ------------------------------------------------------------------ *
 * 1. 後台設定頁
 * ------------------------------------------------------------------ */

// 掛在「網站功能」（ckc-website-features，見 functions.php 第 26 段）底下，
// 直接跟頁面／媒體／設定等項目同一層顯示，不用再從「設定」裡面找。
// 注意：優先權一定要比 ckc_setup_website_features_menu()（99999）晚，
// 因為那個函式才是真正呼叫 add_menu_page() 建立 ckc-website-features
// 這個父選單的地方。如果用預設優先權（10），add_submenu_page() 執行時
// 父選單根本還不存在，WordPress 內部的 $admin_page_hooks 對照表也還沒有
// ckc-website-features 這個項目，算出來的 hookname 會是錯的，實測會導致
// 這個子選單頁面點進去顯示「目前的登入身分沒有存取這個頁面的權限」，
// 即使目前登入帳號其實有 manage_options 權限也一樣。
add_action( 'admin_menu', 'chao_line_notify_add_admin_menu', 100000 );
function chao_line_notify_add_admin_menu() {
    add_submenu_page(
        'ckc-website-features',
        'LINE 訂單通知設定',
        'LINE 訂單通知設定',
        'edit_theme_options', // 權限（2026-08 由 manage_options 調整，見「使用者權限管理」說明）
        'chao-line-order-notify',
        'chao_line_notify_settings_page'
    );
}

function chao_line_notify_settings_page() {
    if ( ! current_user_can( 'edit_theme_options' ) ) {
        return;
    }

    // 儲存設定
    if ( isset( $_POST['chao_line_notify_save'] ) && check_admin_referer( 'chao_line_notify_settings', 'chao_line_notify_nonce' ) ) {
        update_option( 'chao_line_notify_enabled', isset( $_POST['enabled'] ) ? '1' : '0' );
        update_option( 'chao_line_notify_channel_access_token', sanitize_text_field( wp_unslash( $_POST['channel_access_token'] ) ) );
        update_option( 'chao_line_notify_channel_secret', sanitize_text_field( wp_unslash( $_POST['channel_secret'] ) ) );
        update_option( 'chao_line_notify_group_id', sanitize_text_field( wp_unslash( $_POST['group_id'] ) ) );

        $valid_statuses    = array_keys( chao_line_notify_get_all_statuses() );
        $posted_statuses   = isset( $_POST['notify_statuses'] ) && is_array( $_POST['notify_statuses'] ) ? wp_unslash( $_POST['notify_statuses'] ) : array();
        $selected_statuses = array_values( array_intersect( $valid_statuses, array_map( 'sanitize_text_field', $posted_statuses ) ) );
        update_option( 'chao_line_notify_statuses', $selected_statuses );

        echo '<div class="updated"><p><strong>設定已儲存。</strong></p></div>';
    }    // 測試發送
    if ( isset( $_POST['chao_line_notify_test_send'] ) && check_admin_referer( 'chao_line_notify_settings', 'chao_line_notify_nonce' ) ) {
        $group_id = get_option( 'chao_line_notify_group_id', '' );
        if ( empty( $group_id ) ) {
            echo '<div class="error"><p><strong>尚未設定收件群組 ID，請先填寫下方「收件群組 ID」並點擊儲存設定。</strong></p></div>';
        } else {
            $detail = '';
            $result = chao_line_notify_send_push( $group_id, "🔔 潮港城電商購物\n這是一則測試訊息，如果您在 LINE 群組看到這則訊息，代表訂單通知設定成功！", $detail );
            if ( $result ) {
                echo '<div class="updated"><p><strong>✅ 測試訊息已成功發送！請至 LINE 群組確認是否收到。</strong><br><small style="color:#555;">' . esc_html( $detail ) . '</small></p></div>';
            } else {
                echo '<div class="error" style="border-left-color: #dc3232; padding: 12px 15px;"><p><strong style="font-size: 14px;">❌ 測試訊息發送失敗！</strong></p><p><strong>排查詳情：</strong><br><code style="display:inline-block; margin-top:5px; padding:6px 10px; background:#fff; border:1px solid #ccd0d4; border-radius:4px; max-width:100%; white-space:pre-wrap;">' . esc_html( $detail ) . '</code></p></div>';
            }
        }
    }

    // 清除記錄
    if ( isset( $_POST['chao_line_notify_clear_log'] ) ) {
        delete_option( 'chao_line_notify_debug_log' );
        echo '<div class="updated"><p><strong>偵錯記錄已清除。</strong></p></div>';
    }

    // 一鍵套用偵測到的群組 ID
    if ( isset( $_POST['chao_line_notify_use_detected_group'] ) && check_admin_referer( 'chao_line_notify_settings', 'chao_line_notify_nonce' ) ) {
        $detected = get_option( 'chao_line_notify_last_seen_group_id', '' );
        if ( ! empty( $detected ) ) {
            update_option( 'chao_line_notify_group_id', $detected );
            echo '<div class="updated"><p><strong>已套用偵測到的群組 ID：<code>' . esc_html( $detected ) . '</code></strong></p></div>';
        }
    }

    $enabled              = get_option( 'chao_line_notify_enabled', '0' );
    $channel_access_token = get_option( 'chao_line_notify_channel_access_token', '' );
    $channel_secret       = get_option( 'chao_line_notify_channel_secret', '' );
    $group_id             = get_option( 'chao_line_notify_group_id', '' );
    $detected_group_id    = get_option( 'chao_line_notify_last_seen_group_id', '' );
    $webhook_url          = rest_url( 'chao/v1/line-webhook' );
    $all_statuses         = chao_line_notify_get_all_statuses();
    $selected_statuses    = get_option( 'chao_line_notify_statuses', chao_line_notify_get_default_statuses() );
    if ( ! is_array( $selected_statuses ) ) {
        $selected_statuses = chao_line_notify_get_default_statuses();
    }
    ?>
    <div class="wrap">
        <h1>LINE 訂單通知設定</h1>
        <p>客戶訂單轉入指定狀態時，自動透過 LINE Messaging API 發送即時推播通知到管理員 LINE 群組。</p>

        <div style="background: #fff; border: 1px solid #ccd0d4; border-left: 4px solid #00b900; padding: 14px 18px; margin: 15px 0; border-radius: 4px;">
            <h3 style="margin-top:0; color: #00b900; display:flex; align-items:center; gap:6px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="#00b900"><path d="M12 2C6.48 2 2 5.92 2 10.75c0 2.97 1.7 5.59 4.34 7.15-.19.68-.69 2.47-.79 2.85-.13.48.17.47.36.34.15-.1 2.39-1.63 3.36-2.29.56.09 1.14.15 1.73.15 5.52 0 10-3.92 10-8.75S17.52 2 12 2z"/></svg>
                LINE 官方帳號推播串接檢查清單
            </h3>
            <ol style="margin-bottom:0; line-height: 1.8; padding-left: 20px;">
                <li><strong>發行 Channel Access Token</strong>：請前往 <a href="https://developers.line.biz/console/" target="_blank">LINE Developers Console</a> 登入 → 點選 Provider → 點選 Channel（<code>2010688476</code>）→ 切換至 <strong>Messaging API</strong> 分頁 → 滑動到最底部 <strong>Channel access token (long-lived)</strong> 點擊 <strong>Issue (發行)</strong> 取得長權杖（約 170+ 字元），貼入下方欄位。<em>（⚠️ 請勿誤填 32 字元的 Channel Secret）</em></li>
                <li><strong>開啟 Webhook 與群組權限</strong>：前往 <a href="https://manager.line.biz/" target="_blank">LINE Official Account Manager</a>：
                    <ul>
                        <li><strong>「設定 → 回應設定」</strong>：將 <strong>Webhook</strong> 切換為 <strong>開啟</strong>。</li>
                        <li><strong>「設定 → 帳號設定」</strong>：將 <strong>加入群組或多人聊天室</strong> 設定為 <strong>允許</strong>。</li>
                    </ul>
                </li>
                <li><strong>邀請機器人入群</strong>：將官方帳號（<code>@588colaw</code>）邀請進接收通知的 LINE 群組，機器人入群後會自動回覆群組 ID（<code>C...</code>），貼至下方「收件群組 ID」即可。</li>
            </ol>
        </div>

        <form method="post" action="">
            <?php wp_nonce_field( 'chao_line_notify_settings', 'chao_line_notify_nonce' ); ?>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">啟用訂單通知</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" value="1" <?php checked( $enabled, '1' ); ?>> <strong>啟用 LINE 訂單即時通知</strong>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">通知節點</th>
                        <td>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 10px; margin-bottom: 8px;">
                                <?php foreach ( $all_statuses as $status_slug => $status_label ) : ?>
                                    <?php $presentation = chao_line_notify_get_status_presentation( $status_slug ); ?>
                                    <label style="display: block; background: #fff; border: 1px solid #dcdcde; border-radius: 6px; padding: 10px 12px; cursor: pointer; transition: all 0.2s;">
                                        <div style="display: flex; align-items: center; justify-content: space-between;">
                                            <span style="font-weight: 600; font-size: 14px; color: #1d2327; display: flex; align-items: center; gap: 6px;">
                                                <input type="checkbox" name="notify_statuses[]" value="<?php echo esc_attr( $status_slug ); ?>" <?php checked( in_array( $status_slug, $selected_statuses, true ) ); ?> style="margin: 0;">
                                                <?php echo esc_html( $presentation['emoji'] ); ?> <?php echo esc_html( $status_label ); ?>
                                            </span>
                                            <code style="font-size: 11px; color: #646970; background: #f0f0f1; padding: 1px 5px; border-radius: 3px;"><?php echo esc_html( $status_slug ); ?></code>
                                        </div>
                                        <?php if ( ! empty( $presentation['desc'] ) ) : ?>
                                            <div style="font-size: 12px; color: #646970; margin-top: 4px; padding-left: 22px; line-height: 1.4;">
                                                <?php echo esc_html( $presentation['desc'] ); ?>
                                            </div>
                                        <?php endif; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="description">勾選訂單轉入哪些狀態時要發送 LINE 通知。每個狀態節點各自獨立判斷，同一張訂單在不同階段各發送一次。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Channel Access Token</th>
                        <td>
                            <input name="channel_access_token" type="password" value="<?php echo esc_attr( $channel_access_token ); ?>" class="large-text" placeholder="請至 LINE Developers Console -> Messaging API 發行長權杖 (約 170+ 字元)">
                            <p class="description">至 <a href="https://developers.line.biz/console/" target="_blank">LINE Developers Console</a> → Messaging API 分頁最底部的「Channel access token (long-lived)」發行取得。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Channel Secret</th>
                        <td>
                            <input name="channel_secret" type="password" value="<?php echo esc_attr( $channel_secret ); ?>" class="regular-text" placeholder="32 字元金鑰">
                            <p class="description">在 LINE Developers Console → Basic settings 分頁（目前 Channel ID: 2010688476），用來驗證 Webhook 訊息來源。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Webhook URL</th>
                        <td>
                            <input type="text" value="<?php echo esc_url( $webhook_url ); ?>" class="large-text" readonly onclick="this.select();" style="background-color: #f0f0f0;">
                            <p class="description">請複製此網址，貼到 LINE Developers Console → Messaging API 分頁的 Webhook URL 欄位，並開啟 <strong>Use webhook</strong>。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">收件群組 ID</th>
                        <td>
                            <input name="group_id" type="text" value="<?php echo esc_attr( $group_id ); ?>" class="regular-text" placeholder="例如 C798edcfe3396dac4c023d1284c1d56b4">
                            <p class="description">
                                把機器人加入 LINE 群組後，機器人會自動回報群組 ID；亦可填寫個人 User ID (U...) 進行單人測試。
                                <?php if ( ! empty( $detected_group_id ) && $detected_group_id !== $group_id ) : ?>
                                    <br><span style="color:#0073aa;">ℹ️ 偵測到最新 Webhook 收到的 ID：<code><?php echo esc_html( $detected_group_id ); ?></code></span>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="submit">
                <input type="submit" name="chao_line_notify_save" class="button button-primary" value="儲存設定">
                <input type="submit" name="chao_line_notify_test_send" class="button button-secondary" value="測試發送">
                <?php if ( ! empty( $detected_group_id ) && $detected_group_id !== $group_id ) : ?>
                    <input type="submit" name="chao_line_notify_use_detected_group" class="button button-secondary" value="套用偵測到的群組 ID">
                <?php endif; ?>
                <input type="submit" name="chao_line_notify_clear_log" class="button button-secondary" value="清除偵錯記錄">
            </p>
        </form>

        <h2>系統偵錯記錄（最近 100 筆）</h2>
        <div style="background: #FAF9F6; border: 1px solid #ccd0d4; padding: 15px; max-height: 400px; overflow-y: scroll; font-family: monospace; white-space: pre-wrap;">
<?php
$logs = get_option( 'chao_line_notify_debug_log', array() );
if ( empty( $logs ) ) {
    echo '尚無任何記錄。';
} else {
    foreach ( array_reverse( $logs ) as $log ) {
        echo esc_html( $log ) . "\n";
    }
}
?>
        </div>
    </div>
    <?php
}

/* ------------------------------------------------------------------ *
 * 2. LINE Messaging API 呼叫（推播 / 回覆）
 * ------------------------------------------------------------------ */

function chao_line_notify_send_push( $to, $text, &$out_detail = null ) {
    $token = get_option( 'chao_line_notify_channel_access_token', '' );
    if ( empty( $token ) ) {
        $msg = 'Push 失敗：尚未設定 Channel Access Token。請至 LINE Developers Console -> Messaging API 發行長權杖並儲存。';
        chao_line_notify_log( $msg );
        if ( null !== $out_detail ) $out_detail = $msg;
        return false;
    }
    if ( empty( $to ) ) {
        $msg = 'Push 失敗：尚未設定收件群組 ID。';
        chao_line_notify_log( $msg );
        if ( null !== $out_detail ) $out_detail = $msg;
        return false;
    }

    $response = wp_remote_post( 'https://api.line.me/v2/bot/message/push', array(
        'headers' => array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ),
        'body'    => wp_json_encode( array(
            'to'       => $to,
            'messages' => array(
                array(
                    'type' => 'text',
                    'text' => $text,
                ),
            ),
        ) ),
        'timeout' => 15,
    ) );

    if ( is_wp_error( $response ) ) {
        $msg = 'Push API 連線錯誤：' . $response->get_error_message();
        chao_line_notify_log( $msg );
        if ( null !== $out_detail ) $out_detail = $msg;
        return false;
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );

    if ( 200 !== $code ) {
        $explain = '';
        if ( 401 === $code ) {
            $explain = '【HTTP 401 權杖驗證失敗】Channel Access Token 無效或錯誤。請確認是否至 LINE Developers Console -> Messaging API 最底部的「Channel access token (long-lived)」點擊「Issue」發行長金鑰，請勿填寫 32 字元的 Channel Secret。';
        } elseif ( 400 === $code ) {
            $explain = '【HTTP 400 請求錯誤】' . $body . '。若訊息為「Not a member of the group」，表示官方帳號機器人（@588colaw）尚未加入該 LINE 群組或已被移出群組。';
        } elseif ( 403 === $code ) {
            $explain = '【HTTP 403 權限不足】' . $body . '。請確認官方帳號 Messaging API 方案與功能是否正常啟用。';
        } else {
            $explain = "【HTTP {$code}】{$body}";
        }

        chao_line_notify_log( "Push API 失敗：{$explain}" );
        if ( null !== $out_detail ) $out_detail = $explain;
        return false;
    }

    $msg = "Push 成功發送到 {$to}。";
    chao_line_notify_log( $msg );
    if ( null !== $out_detail ) $out_detail = $msg;
    return true;
}

function chao_line_notify_send_reply( $reply_token, $text ) {
    $token = get_option( 'chao_line_notify_channel_access_token', '' );
    if ( empty( $token ) || empty( $reply_token ) ) {
        return false;
    }

    $response = wp_remote_post( 'https://api.line.me/v2/bot/message/reply', array(
        'headers' => array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ),
        'body'    => wp_json_encode( array(
            'replyToken' => $reply_token,
            'messages'   => array(
                array(
                    'type' => 'text',
                    'text' => $text,
                ),
            ),
        ) ),
        'timeout' => 15,
    ) );

    if ( is_wp_error( $response ) ) {
        chao_line_notify_log( 'Reply API 連線錯誤：' . $response->get_error_message() );
        return false;
    }

    $code = wp_remote_retrieve_response_code( $response );
    if ( 200 !== $code ) {
        chao_line_notify_log( "Reply API 回傳非 200（{$code}）：" . wp_remote_retrieve_body( $response ) );
        return false;
    }

    return true;
}

/* ------------------------------------------------------------------ *
 * 3. Webhook：驗證簽章、偵測群組 ID、自動回報
 * ------------------------------------------------------------------ */

add_action( 'rest_api_init', 'chao_line_notify_register_webhook_route' );
function chao_line_notify_register_webhook_route() {
    register_rest_route( 'chao/v1', '/line-webhook', array(
        'methods'             => 'POST',
        'callback'            => 'chao_line_notify_handle_webhook',
        'permission_callback' => '__return_true', // 驗證改用 X-Line-Signature 簽章，不能用一般登入權限判斷
    ) );
}

function chao_line_notify_handle_webhook( WP_REST_Request $request ) {
    $secret = get_option( 'chao_line_notify_channel_secret', '' );
    $body   = $request->get_body();
    
    // 多方相容取得簽章 Header
    $signature = $request->get_header( 'x-line-signature' );
    if ( empty( $signature ) ) {
        $signature = $request->get_header( 'x_line_signature' );
    }
    if ( empty( $signature ) && isset( $_SERVER['HTTP_X_LINE_SIGNATURE'] ) ) {
        $signature = $_SERVER['HTTP_X_LINE_SIGNATURE'];
    }

    if ( empty( $secret ) ) {
        chao_line_notify_log( 'Webhook 收到請求，但尚未設定 Channel Secret，無法驗證簽章。' );
        return new WP_REST_Response( 'channel secret not configured', 400 );
    }

    if ( empty( $signature ) ) {
        chao_line_notify_log( 'Webhook 收到請求，但缺少 X-Line-Signature 標頭。' );
        return new WP_REST_Response( 'missing signature', 400 );
    }

    $expected = base64_encode( hash_hmac( 'sha256', $body, $secret, true ) );
    if ( ! hash_equals( $expected, $signature ) ) {
        chao_line_notify_log( 'Webhook 簽章驗證失敗，忽略此次請求。' );
        return new WP_REST_Response( 'invalid signature', 403 );
    }

    $data = json_decode( $body, true );
    if ( empty( $data['events'] ) || ! is_array( $data['events'] ) ) {
        return new WP_REST_Response( 'ok', 200 );
    }

    $saved_group_id = get_option( 'chao_line_notify_group_id', '' );

    foreach ( $data['events'] as $event ) {
        $source = isset( $event['source'] ) ? $event['source'] : array();
        $source_type = isset( $source['type'] ) ? $source['type'] : '';
        $target_id = '';

        if ( 'group' === $source_type && ! empty( $source['groupId'] ) ) {
            $target_id = $source['groupId'];
        } elseif ( 'room' === $source_type && ! empty( $source['roomId'] ) ) {
            $target_id = $source['roomId'];
        } elseif ( 'user' === $source_type && ! empty( $source['userId'] ) ) {
            $target_id = $source['userId'];
        }

        if ( empty( $target_id ) ) {
            continue;
        }

        update_option( 'chao_line_notify_last_seen_group_id', $target_id, false );
        chao_line_notify_log( "Webhook 收到事件（{$event['type']} / {$source_type}），來源 ID：{$target_id}" );

        // 當收件 ID 尚未設定時，自動回覆回報 ID 方便複製貼上
        if ( $target_id !== $saved_group_id && ! empty( $event['replyToken'] ) ) {
            chao_line_notify_send_reply(
                $event['replyToken'],
                "🔧 潮港城電商購物通知設定：\n本對話/群組 ID 為：\n{$target_id}\n\n請將此 ID 複製貼至後台「網站功能 → LINE 訂單通知設定」的「收件群組 ID」欄位儲存即可。"
            );
        }
    }

    return new WP_REST_Response( 'ok', 200 );
}

/* ------------------------------------------------------------------ *
 * 4. 通知節點定義（可勾選的狀態、對應表情符號／標題、預設勾選項目）
 * ------------------------------------------------------------------ */

// 保留舊版行為當預設值：既有安裝在還沒去設定頁調整之前，維持只通知
// 處理中／已完成，不會因為這次更新突然對所有狀態發通知。
function chao_line_notify_get_default_statuses() {
    return array( 'processing', 'completed' );
}

// 讀取 WooCommerce 官方標準訂單狀態，並依訂單處理生命週期順序排列（排除草稿與垃圾桶）
function chao_line_notify_get_all_statuses() {
    // 依 WooCommerce 標準處理順序定義
    $ordered_keys = array(
        'pending'    => '等待付款中',
        'processing' => '處理中',
        'on-hold'    => '保留中',
        'completed'  => '完成',
        'cancelled'  => '已取消',
        'refunded'   => '已退費',
        'failed'     => '失敗',
    );

    $wc_statuses = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();
    $result = array();

    // 1. 優先放入 7 大核心處理狀態
    foreach ( $ordered_keys as $slug => $fallback_label ) {
        $wc_key = 'wc-' . $slug;
        if ( isset( $wc_statuses[ $wc_key ] ) ) {
            $result[ $slug ] = $wc_statuses[ $wc_key ];
        } else {
            $result[ $slug ] = $fallback_label;
        }
    }

    // 2. 若商店有其他自訂業務狀態（排除草稿/暫存/垃圾桶），排在後方
    foreach ( $wc_statuses as $key => $label ) {
        $slug = ( 0 === strpos( $key, 'wc-' ) ) ? substr( $key, 3 ) : $key;
        if ( in_array( $slug, array( 'checkout-draft', 'auto-draft', 'draft', 'trash' ), true ) ) {
            continue;
        }
        if ( ! isset( $result[ $slug ] ) ) {
            $result[ $slug ] = $label;
        }
    }

    return $result;
}

// 各節點對應的表情符號、訊息標題與功能說明，完全符合 WooCommerce 訂單處理狀態定義
function chao_line_notify_get_status_presentation( $status ) {
    $map = array(
        'pending'    => array(
            'emoji' => '🛒',
            'title' => '新訂單建立（等待付款中）',
            'desc'  => '顧客已下單但尚未付款（如 ATM 轉帳、超商代碼待繳費、線上刷卡中）',
        ),
        'processing' => array(
            'emoji' => '🔔',
            'title' => '新訂單成立（處理中／待出貨）',
            'desc'  => '顧客付款完成或選擇貨到付款，訂單正式成立，待店家備貨出貨',
        ),
        'on-hold'    => array(
            'emoji' => '⏸️',
            'title' => '訂單保留中（待確認）',
            'desc'  => '庫存已扣除但等待店家人工查核對帳或確認商品',
        ),
        'completed'  => array(
            'emoji' => '✅',
            'title' => '訂單已完成（已出貨／完成交易）',
            'desc'  => '商品已出貨寄達，或訂單所有流程已完成結案',
        ),
        'cancelled'  => array(
            'emoji' => '❌',
            'title' => '訂單已取消',
            'desc'  => '顧客或管理員主動取消訂單（庫存自動釋放）',
        ),
        'refunded'   => array(
            'emoji' => '💰',
            'title' => '訂單已退款',
            'desc'  => '管理員已為顧客執行全額或部分退款／信用卡退刷',
        ),
        'failed'     => array(
            'emoji' => '⚠️',
            'title' => '訂單付款失敗',
            'desc'  => '信用卡 3D 驗證失敗、額度不足或授權超時未完成',
        ),
    );

    if ( isset( $map[ $status ] ) ) {
        return $map[ $status ];
    }
    return array(
        'emoji' => '📦',
        'title' => '訂單狀態更新',
        'desc'  => '自訂狀態或系統狀態變更',
    );
}

/* ------------------------------------------------------------------ *
 * 5. 訂單狀態變化 → 發送通知
 * ------------------------------------------------------------------ */

// 1. 訂單狀態改變時觸發
add_action( 'woocommerce_order_status_changed', 'chao_line_notify_on_order_status_changed', 10, 4 );
function chao_line_notify_on_order_status_changed( $order_id, $old_status, $new_status, $order ) {
    if ( get_option( 'chao_line_notify_enabled', '0' ) !== '1' ) {
        return;
    }

    $clean_status = ( 0 === strpos( $new_status, 'wc-' ) ) ? substr( $new_status, 3 ) : $new_status;
    $selected_statuses = get_option( 'chao_line_notify_statuses', chao_line_notify_get_default_statuses() );
    if ( ! is_array( $selected_statuses ) || ! in_array( $clean_status, $selected_statuses, true ) ) {
        return;
    }

    if ( ! ( $order instanceof WC_Order ) ) {
        $order = wc_get_order( $order_id );
    }
    if ( ! $order ) {
        return;
    }

    chao_line_notify_send_order_notification( $order, $clean_status );
}

// 2. 結帳送出完成建立新訂單時觸發（捕捉初次建立且未觸發 status_changed 的狀態如 pending/processing）
add_action( 'woocommerce_checkout_order_processed', 'chao_line_notify_on_checkout_processed', 20, 3 );
function chao_line_notify_on_checkout_processed( $order_id, $posted_data = array(), $order = null ) {
    if ( get_option( 'chao_line_notify_enabled', '0' ) !== '1' ) {
        return;
    }
    if ( ! ( $order instanceof WC_Order ) ) {
        $order = wc_get_order( $order_id );
    }
    if ( ! $order ) {
        return;
    }

    $status = $order->get_status();
    $clean_status = ( 0 === strpos( $status, 'wc-' ) ) ? substr( $status, 3 ) : $status;
    $selected_statuses = get_option( 'chao_line_notify_statuses', chao_line_notify_get_default_statuses() );

    if ( is_array( $selected_statuses ) && in_array( $clean_status, $selected_statuses, true ) ) {
        chao_line_notify_send_order_notification( $order, $clean_status );
    }
}

// 3. 金流付款完成時觸發
add_action( 'woocommerce_payment_complete', 'chao_line_notify_on_payment_complete', 20, 1 );
function chao_line_notify_on_payment_complete( $order_id ) {
    if ( get_option( 'chao_line_notify_enabled', '0' ) !== '1' ) {
        return;
    }
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }

    $status = $order->get_status();
    $clean_status = ( 0 === strpos( $status, 'wc-' ) ) ? substr( $status, 3 ) : $status;
    $selected_statuses = get_option( 'chao_line_notify_statuses', chao_line_notify_get_default_statuses() );

    if ( is_array( $selected_statuses ) && in_array( $clean_status, $selected_statuses, true ) ) {
        chao_line_notify_send_order_notification( $order, $clean_status );
    }
}

// 每張訂單、每個節點各自獨立記錄是否已發送過（_chao_line_notify_sent_statuses
// 存已發送節點的陣列），同一張單經過多個有勾選的節點會各自通知一次，但同一個
// 節點不會因為狀態被改來改去而重複通知。
function chao_line_notify_send_order_notification( $order, $status = null, $force = false ) {
    if ( null === $status ) {
        $status = $order->get_status();
    }
    $clean_status = ( 0 === strpos( $status, 'wc-' ) ) ? substr( $status, 3 ) : $status;

    $sent_statuses = $order->get_meta( '_chao_line_notify_sent_statuses' );
    if ( ! is_array( $sent_statuses ) ) {
        $sent_statuses = array();
    }

    // 若非強制重發且該狀態已成功發送過，則略過
    if ( ! $force && in_array( $clean_status, $sent_statuses, true ) ) {
        chao_line_notify_log( '訂單 #' . $order->get_order_number() . "「{$clean_status}」節點先前已成功發送過通知，略過。" );
        return false;
    }

    $group_id = get_option( 'chao_line_notify_group_id', '' );
    $message  = chao_line_notify_build_order_message( $order, $clean_status );
    $detail   = '';
    $sent     = chao_line_notify_send_push( $group_id, $message, $detail );

    $presentation = chao_line_notify_get_status_presentation( $clean_status );
    $status_label = wc_get_order_status_name( $clean_status );

    if ( $sent ) {
        // 成功發送才寫入已發送清單
        if ( ! in_array( $clean_status, $sent_statuses, true ) ) {
            $sent_statuses[] = $clean_status;
            $order->update_meta_data( '_chao_line_notify_sent_statuses', $sent_statuses );
            $order->save();
        }
        chao_line_notify_log( '訂單 #' . $order->get_order_number() . "「{$clean_status}」節點通知已發送。" );
        $order->add_order_note( sprintf( '【LINE 訂單通知】%s 狀態「%s」通知已發送至 LINE 群組。', $presentation['emoji'], $status_label ) );
        return true;
    } else {
        chao_line_notify_log( '訂單 #' . $order->get_order_number() . "「{$clean_status}」節點通知發送失敗：{$detail}" );
        $order->add_order_note( sprintf( '【LINE 訂單通知】狀態「%s」發送失敗：%s', $status_label, $detail ) );
        return false;
    }
}

function chao_line_notify_build_order_message( $order, $status = null ) {
    if ( null === $status ) {
        $status = $order->get_status();
    }
    $clean_status = ( 0 === strpos( $status, 'wc-' ) ) ? substr( $status, 3 ) : $status;
    $presentation = chao_line_notify_get_status_presentation( $clean_status );

    $order_number     = $order->get_order_number();
    $customer_name    = trim( $order->get_formatted_billing_full_name() );
    $phone            = $order->get_billing_phone();
    $total            = $order->get_formatted_order_total();
    $payment_method   = $order->get_payment_method_title();
    $shipping_method  = $order->get_shipping_method();
    $status_label     = wc_get_order_status_name( $clean_status );
    $edit_url         = $order->get_edit_order_url();

    // 取得訂購商品清單明細
    $items = array();
    foreach ( $order->get_items() as $item ) {
        $items[] = '• ' . $item->get_name() . ' x ' . $item->get_quantity();
    }

    $lines   = array();
    $lines[] = $presentation['emoji'] . ' 潮港城電商購物 - ' . $presentation['title'];
    $lines[] = '━━━━━━━━━━━━━━━━';
    $lines[] = '訂單編號：#' . $order_number;
    if ( ! empty( $customer_name ) || ! empty( $phone ) ) {
        $lines[] = '訂購顧客：' . ( $customer_name ? $customer_name : '—' ) . '（' . ( $phone ? $phone : '—' ) . '）';
    }
    $lines[] = '訂單金額：' . wp_strip_all_tags( $total );
    if ( ! empty( $payment_method ) ) {
        $lines[] = '付款方式：' . $payment_method;
    }
    if ( ! empty( $shipping_method ) ) {
        $lines[] = '配送方式：' . $shipping_method;
    }
    $lines[] = '當前狀態：' . $status_label;

    if ( ! empty( $items ) ) {
        $lines[] = '━━━━━━━━━━━━━━━━';
        $lines[] = '📦 訂購明細：';
        $lines[] = implode( "\n", array_slice( $items, 0, 5 ) );
        if ( count( $items ) > 5 ) {
            $lines[] = '...等共 ' . count( $items ) . ' 項商品';
        }
    }

    $lines[] = '━━━━━━━━━━━━━━━━';
    $lines[] = '🔗 管理訂單：' . $edit_url;

    return implode( "\n", $lines );
}

/* ------------------------------------------------------------------ *
 * 6. 訂單編輯頁：手動重新發送
 * ------------------------------------------------------------------ */

add_filter( 'woocommerce_order_actions', 'chao_line_notify_add_order_action' );
function chao_line_notify_add_order_action( $actions ) {
    $actions['chao_line_notify_resend'] = '重新發送 LINE 通知（目前狀態）';
    return $actions;
}

add_action( 'woocommerce_order_action_chao_line_notify_resend', 'chao_line_notify_handle_resend_action' );
function chao_line_notify_handle_resend_action( $order ) {
    $status = $order->get_status();
    chao_line_notify_send_order_notification( $order, $status, true );
}
