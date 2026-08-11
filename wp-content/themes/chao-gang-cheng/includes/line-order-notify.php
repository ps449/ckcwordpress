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
    $all_statuses         = chao_line_notify_get_all_statuses();
    $selected_statuses    = get_option( 'chao_line_notify_statuses', chao_line_notify_get_default_statuses() );
    if ( ! is_array( $selected_statuses ) ) {
        $selected_statuses = chao_line_notify_get_default_statuses();
    }
    ?>
    <div class="wrap">
        <h1>LINE 訂單通知設定</h1>
        <p>訂單第一次轉入下方勾選的狀態節點時，自動發送 LINE 通知到指定群組（同一張訂單、同一個節點只會通知一次）。</p>
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
                        <th scope="row">通知節點</th>
                        <td>
                            <?php foreach ( $all_statuses as $status_slug => $status_label ) : ?>
                                <?php $presentation = chao_line_notify_get_status_presentation( $status_slug ); ?>
                                <label style="display:inline-block; min-width: 220px; margin: 4px 0;">
                                    <input type="checkbox" name="notify_statuses[]" value="<?php echo esc_attr( $status_slug ); ?>" <?php checked( in_array( $status_slug, $selected_statuses, true ) ); ?>>
                                    <?php echo esc_html( $presentation['emoji'] ); ?> <?php echo esc_html( $status_label ); ?>
                                </label>
                            <?php endforeach; ?>
                            <p class="description">勾選訂單進入哪些狀態時要發送通知；每個節點各自獨立判斷，同一張訂單經過多個有勾選的節點會分別各發送一次。</p>
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
                "🔧 本群組 ID：\n{$group_id}\n\n請將此 ID 貼到後台「網站功能 → LINE 訂單通知設定」頁面的收件群組 ID 欄位，即可開始接收訂單通知。"
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

// 讀取 WooCommerce 目前登記的訂單狀態，去掉 'wc-' 前綴方便跟
// woocommerce_order_status_changed 傳進來的 $new_status（不含前綴）比對。
function chao_line_notify_get_all_statuses() {
    $statuses = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();
    $result = array();
    foreach ( $statuses as $key => $label ) {
        $slug = ( 0 === strpos( $key, 'wc-' ) ) ? substr( $key, 3 ) : $key;
        $result[ $slug ] = $label;
    }
    return $result;
}

// 各節點對應的表情符號／訊息標題，讓不同狀態的通知一眼就能分辨。
function chao_line_notify_get_status_presentation( $status ) {
    $map = array(
        'pending'    => array( 'emoji' => '🛒', 'title' => '新訂單建立（待付款）' ),
        'processing' => array( 'emoji' => '🔔', 'title' => '新訂單通知' ),
        'on-hold'    => array( 'emoji' => '⏸️', 'title' => '訂單保留中' ),
        'completed'  => array( 'emoji' => '✅', 'title' => '訂單已完成' ),
        'cancelled'  => array( 'emoji' => '❌', 'title' => '訂單已取消' ),
        'refunded'   => array( 'emoji' => '💰', 'title' => '訂單已退款' ),
        'failed'     => array( 'emoji' => '⚠️', 'title' => '付款失敗' ),
    );
    if ( isset( $map[ $status ] ) ) {
        return $map[ $status ];
    }
    return array( 'emoji' => '🔔', 'title' => '訂單狀態更新' );
}

/* ------------------------------------------------------------------ *
 * 5. 訂單狀態變化 → 發送通知
 * ------------------------------------------------------------------ */

add_action( 'woocommerce_order_status_changed', 'chao_line_notify_on_order_status_changed', 10, 4 );
function chao_line_notify_on_order_status_changed( $order_id, $old_status, $new_status, $order ) {
    if ( get_option( 'chao_line_notify_enabled', '0' ) !== '1' ) {
        return;
    }

    $selected_statuses = get_option( 'chao_line_notify_statuses', chao_line_notify_get_default_statuses() );
    if ( ! is_array( $selected_statuses ) || ! in_array( $new_status, $selected_statuses, true ) ) {
        return;
    }

    if ( ! ( $order instanceof WC_Order ) ) {
        $order = wc_get_order( $order_id );
    }
    if ( ! $order ) {
        return;
    }

    chao_line_notify_send_order_notification( $order, $new_status );
}

// 每張訂單、每個節點各自獨立記錄是否已發送過（_chao_line_notify_sent_statuses
// 存已發送節點的陣列），同一張單經過多個有勾選的節點會各自通知一次，但同一個
// 節點不會因為狀態被改來改去而重複通知。
function chao_line_notify_send_order_notification( $order, $status = null ) {
    if ( null === $status ) {
        $status = $order->get_status();
    }

    $sent_statuses = $order->get_meta( '_chao_line_notify_sent_statuses' );
    if ( ! is_array( $sent_statuses ) ) {
        $sent_statuses = array();
    }
    if ( in_array( $status, $sent_statuses, true ) ) {
        chao_line_notify_log( '訂單 #' . $order->get_order_number() . "「{$status}」節點先前已發送過通知，略過。" );
        return;
    }

    $group_id = get_option( 'chao_line_notify_group_id', '' );
    $message  = chao_line_notify_build_order_message( $order, $status );
    $sent     = chao_line_notify_send_push( $group_id, $message );

    // 不論成功或失敗都標記該節點已處理，避免反覆自動重試發送；
    // 若真的發送失敗，可在訂單編輯頁用「重新發送 LINE 通知」手動補發。
    $sent_statuses[] = $status;
    $order->update_meta_data( '_chao_line_notify_sent_statuses', $sent_statuses );
    $order->save();

    if ( $sent ) {
        chao_line_notify_log( '訂單 #' . $order->get_order_number() . "「{$status}」節點通知已發送。" );
    } else {
        chao_line_notify_log( '訂單 #' . $order->get_order_number() . "「{$status}」節點通知發送失敗。" );
    }
}

function chao_line_notify_build_order_message( $order, $status = null ) {
    if ( null === $status ) {
        $status = $order->get_status();
    }
    $presentation = chao_line_notify_get_status_presentation( $status );

    $order_number     = $order->get_order_number();
    $customer_name    = trim( $order->get_formatted_billing_full_name() );
    $phone            = $order->get_billing_phone();
    $total            = $order->get_formatted_order_total();
    $payment_method   = $order->get_payment_method_title();
    $shipping_method  = $order->get_shipping_method();
    $status_label     = wc_get_order_status_name( $status );
    $edit_url         = $order->get_edit_order_url();

    $lines   = array();
    $lines[] = $presentation['emoji'] . ' 潮港城電商購物 - ' . $presentation['title'];
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
 * 6. 訂單編輯頁：手動重新發送
 * ------------------------------------------------------------------ */

add_filter( 'woocommerce_order_actions', 'chao_line_notify_add_order_action' );
function chao_line_notify_add_order_action( $actions ) {
    $actions['chao_line_notify_resend'] = '重新發送 LINE 通知（目前狀態）';
    return $actions;
}

add_action( 'woocommerce_order_action_chao_line_notify_resend', 'chao_line_notify_handle_resend_action' );
function chao_line_notify_handle_resend_action( $order ) {
    // 手動重新發送只解除「目前狀態」這個節點的已發送記錄，不影響其他節點的記錄。
    $status        = $order->get_status();
    $sent_statuses = $order->get_meta( '_chao_line_notify_sent_statuses' );
    if ( is_array( $sent_statuses ) && in_array( $status, $sent_statuses, true ) ) {
        $sent_statuses = array_values( array_diff( $sent_statuses, array( $status ) ) );
        $order->update_meta_data( '_chao_line_notify_sent_statuses', $sent_statuses );
        $order->save();
    }
    chao_line_notify_send_order_notification( $order, $status );
}
