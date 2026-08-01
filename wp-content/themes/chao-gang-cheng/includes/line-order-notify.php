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
add_action( 'admin_menu', 'chao_line_notify_add_admin_menu' );
function chao_line_notify_add_admin_menu() {
    add_submenu_page(
        'ckc-website-features',
        'LINE 訂單通知設定',
        'LINE 訂單通知設定',
        'manage_options',
        'chao-line-order-notify',
        'chao_line_notify_settings_page'
    );
}

function chao_line_notify_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // 儲存設定
    if ( isset( $_POST['chao_line_notify_save'] ) && check_admin_referer( 'chao_line_notify_settings', 'chao_line_notify_nonce' ) ) {
        update_option( 'chao_line_notify_enabled', isset( $_POST['enabled'] ) ? '1' : '0' );
        update_option( 'chao_line_notify_channel_access_token', sanitize_text_field( wp_unslash( $_POST['channel_access_token'] ) ) );
        update_option( 'chao_line_notify_channel_secret', sanitize_text_field( wp_unslash( $_POST['channel_secret'] ) ) );
        update_option( 'chao_line_notify_group_id', sanitize_text_field( wp_unslash( $_POST['group_id'] ) ) );
        echo '<div class="updated"><p><strong>設定已儲存。</strong></p></div>';
    }

    // 測試發送
    if ( isset( $_POST['chao_line_notify_test_send'] ) && check_admin_referer( 'chao_line_notify_settings', 'chao_line_notify_nonce' ) ) {
        $group_id = get_option( 'chao_line_notify_group_id', '' );
        if ( empty( $group_id ) ) {
            echo '<div class="error"><p><strong>尚未設定群組 ID，請先完成上方設定並儲存。</strong></p></div>';
        } else {
            $result = chao_line_notify_send_push( $group_id, "🔔 潮港城電商購物\n這是一則測試訊息，如果您在 LINE 群組看到這則訊息，代表訂單通知設定成功！" );
            if ( $result ) {
                echo '<div class="updated"><p><strong>測試訊息已發送，請確認 LINE 群組是否收到。</strong></p></div>';
            } else {
                echo '<div class="error"><p><strong>測試訊息發送失敗，請查看下方偵錯記錄確認原因。</strong></p></div>';
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
            echo '<div class="updated"><p><strong>已套用偵測到的群組 ID。</strong></p></div>';
        }
    }

    $enabled              = get_option( 'chao_line_notify_enabled', '0' );
    $channel_access_token = get_option( 'chao_line_notify_channel_access_token', '' );
    $channel_secret       = get_option( 'chao_line_notify_channel_secret', '' );
    $group_id             = get_option( 'chao_line_notify_group_id', '' );
    $detected_group_id    = get_option( 'chao_line_notify_last_seen_group_id', '' );
    $webhook_url          = rest_url( 'chao/v1/line-webhook' );
    ?>
    <div class="wrap">
        <h1>LINE 訂單通知設定</h1>
        <p>當訂單第一次轉入「處理中」或「已完成」狀態時，自動發送 LINE 通知到指定群組。</p>
        <form method="post" action="">
            <?php wp_nonce_field( 'chao_line_notify_settings', 'chao_line_notify_nonce' ); ?>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">啟用訂單通知</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" value="1" <?php checked( $enabled, '1' ); ?>> 啟用
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Channel Access Token</th>
                        <td>
                            <input name="channel_access_token" type="password" value="<?php echo esc_attr( $channel_access_token ); ?>" class="large-text">
                            <p class="description">在 LINE Developers Console → Messaging API 分頁 → Channel access token 發行。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Channel Secret</th>
                        <td>
                            <input name="channel_secret" type="password" value="<?php echo esc_attr( $channel_secret ); ?>" class="regular-text">
                            <p class="description">在 LINE Developers Console → Basic settings 分頁可以看到，用來驗證 Webhook 訊息來源。</p>
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
                            <input name="group_id" type="text" value="<?php echo esc_attr( $group_id ); ?>" class="regular-text">
                            <p class="description">
                                把機器人加入 LINE 群組後，群組裡會自動收到一則回報群組 ID 的訊息，複製貼上即可。
                                <?php if ( ! empty( $detected_group_id ) && $detected_group_id !== $group_id ) : ?>
                                    <br>偵測到尚未套用的群組 ID：<code><?php echo esc_html( $detected_group_id ); ?></code>

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

function chao_line_notify_send_push( $to, $text ) {
    $token = get_option( 'chao_line_notify_channel_access_token', '' );
    if ( empty( $token ) ) {
        chao_line_notify_log( 'Push 失敗：尚未設定 Channel Access Token。' );
        return false;
    }
    if ( empty( $to ) ) {
        chao_line_notify_log( 'Push 失敗：尚未設定收件群組 ID。' );
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
        chao_line_notify_log( 'Push API 連線錯誤：' . $response->get_error_message() );
        return false;
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );

    if ( 200 !== $code ) {
        chao_line_notify_log( "Push API 回傳非 200（{$code}）：{$body}" );
        return false;
    }

    chao_line_notify_log( "Push 成功發送到 {$to}。" );
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
    $signature = $request->get_header( 'x_line_signature' );

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
        if ( empty( $source['type'] ) || 'group' !== $source['type'] || empty( $source['groupId'] ) ) {
            continue;
        }

        $group_id = $source['groupId'];
        update_option( 'chao_line_notify_last_seen_group_id', $group_id, false );
        chao_line_notify_log( "Webhook 收到群組事件（{$event['type']}），群組 ID：{$group_id}" );

        // 這個群組還沒被設定成收件群組時，自動回一則訊息把 ID 報出來，方便店家複製貼上。
        if ( $group_id !== $saved_group_id && ! empty( $event['replyToken'] ) ) {
            chao_line_notify_send_reply(
                $event['replyToken'],
                "🔧 本群組 ID：\n{$group_id}\n\n請將此 ID 貼到後台「設定 → LINE 訂單通知設定」頁面的收件群組 ID 欄位，即可開始接收訂單通知。"
            );
        }
    }

    return new WP_REST_Response( 'ok', 200 );
}

/* ------------------------------------------------------------------ *
 * 4. 訂單狀態變化 → 發送通知
 * ------------------------------------------------------------------ */

add_action( 'woocommerce_order_status_changed', 'chao_line_notify_on_order_status_changed', 10, 4 );
function chao_line_notify_on_order_status_changed( $order_id, $old_status, $new_status, $order ) {
    if ( get_option( 'chao_line_notify_enabled', '0' ) !== '1' ) {
        return;
    }

    $paid_statuses = array( 'processing', 'completed' );

    // 只在「第一次」轉入處理中／已完成狀態時通知，避免同一張單被通知多次
    // （例如 processing → completed 又觸發一次）。
    if ( ! in_array( $new_status, $paid_statuses, true ) ) {
        return;
    }
    if ( in_array( $old_status, $paid_statuses, true ) ) {
        return;
    }

    if ( ! ( $order instanceof WC_Order ) ) {
        $order = wc_get_order( $order_id );
    }
    if ( ! $order ) {
        return;
    }

    chao_line_notify_send_order_notification( $order );
}

function chao_line_notify_send_order_notification( $order ) {
    if ( 'yes' === $order->get_meta( '_chao_line_notify_sent' ) ) {
        chao_line_notify_log( '訂單 #' . $order->get_order_number() . ' 先前已發送過通知，略過。' );
        return;
    }

    $group_id = get_option( 'chao_line_notify_group_id', '' );
    $message  = chao_line_notify_build_order_message( $order );
    $sent     = chao_line_notify_send_push( $group_id, $message );

    // 不論成功或失敗都標記已處理，避免同一張單反覆自動重試發送；
    // 若真的發送失敗，可在訂單編輯頁用「重新發送 LINE 通知」手動補發。
    $order->update_meta_data( '_chao_line_notify_sent', 'yes' );
    $order->save();

    if ( $sent ) {
        chao_line_notify_log( '訂單 #' . $order->get_order_number() . ' 通知已發送。' );
    } else {
        chao_line_notify_log( '訂單 #' . $order->get_order_number() . ' 通知發送失敗。' );
    }
}

function chao_line_notify_build_order_message( $order ) {
    $order_number   = $order->get_order_number();
    $customer_name  = trim( $order->get_formatted_billing_full_name() );
    $phone          = $order->get_billing_phone();
    $total          = $order->get_formatted_order_total();
    $payment_method = $order->get_payment_method_title();
    $shipping_method = $order->get_shipping_method();
    $status_label   = wc_get_order_status_name( $order->get_status() );
    $edit_url       = $order->get_edit_order_url();

    $lines   = array();
    $lines[] = '🔔 潮港城電商購物 - 新訂單通知';
    $lines[] = '訂單編號：#' . $order_number;
    if ( ! empty( $customer_name ) || ! empty( $phone ) ) {
        $lines[] = '客戶姓名：' . ( $customer_name ? $customer_name : '—' ) . '　電話：' . ( $phone ? $phone : '—' );
    }
    $lines[] = '訂單金額：' . wp_strip_all_tags( $total );
    if ( ! empty( $payment_method ) ) {
        $lines[] = '付款方式：' . $payment_method;
    }
    if ( ! empty( $shipping_method ) ) {
        $lines[] = '配送方式：' . $shipping_method;
    }
    $lines[] = '訂單狀態：' . $status_label;
    $lines[] = '查看訂單：' . $edit_url;

    return implode( "\n", $lines );
}

/* ------------------------------------------------------------------ *
 * 5. 訂單編輯頁：手動重新發送
 * ------------------------------------------------------------------ */

add_filter( 'woocommerce_order_actions', 'chao_line_notify_add_order_action' );
function chao_line_notify_add_order_action( $actions ) {
    $actions['chao_line_notify_resend'] = '重新發送 LINE 通知';
    return $actions;
}

add_action( 'woocommerce_order_action_chao_line_notify_resend', 'chao_line_notify_handle_resend_action' );
function chao_line_notify_handle_resend_action( $order ) {
    // 手動重新發送不受「已發送過」限制，直接重發一次。
    $order->delete_meta_data( '_chao_line_notify_sent' );
    $order->save();
    chao_line_notify_send_order_notification( $order );
}
