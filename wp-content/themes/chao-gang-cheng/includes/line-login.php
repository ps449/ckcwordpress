<?php
/**
 * Custom LINE Login Integration for WooCommerce with Database Debug Logging
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Database logging helper
function chao_line_login_log( $message ) {
    $timestamp = date( 'Y-m-d H:i:s' );
    $log_entry = "[{$timestamp}] {$message}";
    
    // Get existing logs from database
    $logs = get_option( 'chao_line_login_debug_log', array() );
    if ( ! is_array( $logs ) ) {
        $logs = array();
    }
    
    // Keep last 100 entries
    $logs[] = $log_entry;
    if ( count( $logs ) > 100 ) {
        array_shift( $logs );
    }
    
    update_option( 'chao_line_login_debug_log', $logs, false );
}

// 1. Register Admin Settings Page
add_action( 'admin_menu', 'chao_line_login_add_admin_menu' );
function chao_line_login_add_admin_menu() {
    add_options_page(
        'LINE 登入設定',
        'LINE 登入設定',
        'manage_options',
        'chao-line-login',
        'chao_line_login_settings_page'
    );
}

function chao_line_login_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    
    // Save settings
    if ( isset( $_POST['chao_line_login_save'] ) && check_admin_referer( 'chao_line_login_settings', 'chao_line_login_nonce' ) ) {
        update_option( 'chao_line_login_enabled', isset( $_POST['enabled'] ) ? '1' : '0' );
        update_option( 'chao_line_login_channel_id', sanitize_text_field( $_POST['channel_id'] ) );
        update_option( 'chao_line_login_channel_secret', sanitize_text_field( $_POST['channel_secret'] ) );
        echo '<div class="updated"><p><strong>設定已儲存。</strong></p></div>';
    }
    
    if ( isset( $_POST['chao_line_login_clear_log'] ) ) {
        delete_option( 'chao_line_login_debug_log' );
        echo '<div class="updated"><p><strong>偵錯記錄已清除。</strong></p></div>';
    }
    
    $enabled = get_option( 'chao_line_login_enabled', '0' );
    $channel_id = get_option( 'chao_line_login_channel_id', '' );
    $channel_secret = get_option( 'chao_line_login_channel_secret', '' );
    $default_redirect_uri = add_query_arg( 'line-login-callback', '1', home_url( '/' ) );
    
    ?>
    <div class="wrap">
        <h1>LINE 快速登入設定</h1>
        <form method="post" action="">
            <?php wp_nonce_field( 'chao_line_login_settings', 'chao_line_login_nonce' ); ?>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">啟用 LINE 登入</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" value="1" <?php checked( $enabled, '1' ); ?>> 啟用
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">LINE Channel ID</th>
                        <td>
                            <input name="channel_id" type="text" value="<?php echo esc_attr( $channel_id ); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">LINE Channel Secret</th>
                        <td>
                            <input name="channel_secret" type="password" value="<?php echo esc_attr( $channel_secret ); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Callback URL (Redirect URI)</th>
                        <td>
                            <input type="text" value="<?php echo esc_url( $default_redirect_uri ); ?>" class="large-text" readonly onclick="this.select();" style="background-color: #f0f0f0;">
                            <p class="description">請複製此 URL 並在 LINE Developers Console 的 <strong>Callback URL</strong> 中設定。</p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="submit">
                <input type="submit" name="chao_line_login_save" class="button button-primary" value="儲存設定">
                <input type="submit" name="chao_line_login_clear_log" class="button button-secondary" value="清除偵錯記錄">
            </p>
        </form>
        
        <h2>系統偵錯記錄 (最近 100 筆)</h2>
        <div style="background: #FAF9F6; border: 1px solid #ccd0d4; padding: 15px; max-height: 400px; overflow-y: scroll; font-family: monospace; white-space: pre-wrap;">
<?php
$logs = get_option( 'chao_line_login_debug_log', array() );
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

// 2. Hook to render login button in WooCommerce login and register forms
add_action( 'woocommerce_login_form_end', 'chao_line_login_render_button', 5 );
add_action( 'woocommerce_register_form_end', 'chao_line_login_render_button', 5 );

function chao_line_login_render_button() {
    if ( get_option( 'chao_line_login_enabled', '0' ) !== '1' ) {
        return;
    }
    
    $channel_id = get_option( 'chao_line_login_channel_id', '' );
    if ( empty( $channel_id ) ) {
        return;
    }
    
    // Generate secure state ONCE per page request to prevent multiple button renders from overriding the state cookie
    static $state = null;
    static $redirect_to = null;
    
    if ( null === $state ) {
        $state = wp_generate_password( 24, false );
        // Set cookie domain to '' and path to '/' to support custom mapped domain
        setcookie( 'chao_line_login_state', $state, time() + 600, '/', '', is_ssl(), true );
        
        // Save the redirect URL
        if ( is_checkout() ) {
            $redirect_to = wc_get_checkout_url();
        } elseif ( isset( $_GET['redirect_to'] ) ) {
            $redirect_to = esc_url_raw( $_GET['redirect_to'] );
        } else {
            $redirect_to = wc_get_page_permalink( 'myaccount' );
        }
        
        setcookie( 'chao_line_login_redirect', $redirect_to, time() + 600, '/', '', is_ssl(), true );
        chao_line_login_log( "Button rendered. State set: {$state}. Redirect URL saved: {$redirect_to}" );
    }
    
    $redirect_uri = add_query_arg( 'line-login-callback', '1', home_url( '/' ) );
    
    // Build LINE Auth URL
    $auth_url = add_query_arg( array(
        'response_type' => 'code',
        'client_id'     => $channel_id,
        'redirect_uri'  => urlencode( $redirect_uri ),
        'state'         => $state,
        'scope'         => 'profile openid email',
        'nonce'         => wp_generate_password( 16, false )
    ), 'https://access.line.me/oauth2/v2.1/authorize' );
    
    // Render inline style once
    static $style_rendered = false;
    if ( ! $style_rendered ) {
        chao_line_login_render_styles();
        $style_rendered = true;
    }
    
    ?>
    <div class="line-login-container">
        <div class="line-login-divider"><span>或使用社群帳號快速登入</span></div>
        <a href="<?php echo esc_url( $auth_url ); ?>" class="line-login-button">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20">
                <path fill="#FFFFFF" d="M24 10.3c0-5.7-5.4-10.3-12-10.3S0 4.6 0 10.3c0 5.1 4.3 9.3 10.1 10.1.4.1.9.3 1 .7.1.3.1.8 0 1.1l-.4 2.4c-.1.7.3.7.6.5 2.7-1.8 11.7-6.9 12.3-11.8.3-.9.4-1.9.4-3zm-15.6 2.3c0 .2-.2.4-.4.4H6.5c-.2 0-.4-.2-.4-.4V8.5c0-.2.2-.4.4-.4h.8c.2 0 .4.2.4.4v3.1h1.3c.2 0 .4.2.4.4v.6zm2.3 0c0 .2-.2.4-.4.4h-.8c-.2 0-.4-.2-.4-.4V8.5c0-.2.2-.4.4-.4h.8c.2 0 .4.2.4.4v4.1zm5.2 0c0 .2-.2.4-.4.4h-.8c-.2 0-.3-.1-.4-.2l-2-2.7v2.5c0 .2-.2.4-.4.4h-.8c-.2 0-.4-.2-.4-.4V8.5c0-.2.2-.4.4-.4h.8c.2 0 .3.1.4.2l2 2.7V8.5c0-.2.2-.4.4-.4h.8c.2 0 .4.2.4.4v4.1zm3.8-1.5c0 .2-.2.4-.4.4h-1.6v.7h1.6c.2 0 .4.2.4.4v.6c0 .2-.2.4-.4.4h-2.8c-.2 0-.4-.2-.4-.4V8.5c0-.2.2-.4.4-.4h2.8c.2 0 .4.2.4.4v.6c0 .2-.2.4-.4.4h-1.6v.7h1.6c.2 0 .4.2.4.4v.6z"/>
            </svg>
            <span>LINE 快速登入</span>
        </a>
    </div>
    <?php
}

function chao_line_login_render_styles() {
    ?>
    <style>
        .line-login-container {
            margin: 25px 0 15px 0;
            text-align: center;
            clear: both;
        }
        .line-login-divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin-bottom: 20px;
            color: #8c8c8c;
            font-size: 13px;
            font-weight: 500;
        }
        .line-login-divider::before,
        .line-login-divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e4e7eb;
        }
        .line-login-divider:not(:empty)::before {
            margin-right: 12px;
        }
        .line-login-divider:not(:empty)::after {
            margin-left: 12px;
        }
        .line-login-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: #06c755;
            color: #ffffff !important;
            font-weight: 600;
            font-size: 15px;
            border-radius: 24px;
            padding: 11px 24px;
            text-decoration: none !important;
            width: 100%;
            transition: all 0.25s ease;
            border: 1px solid #06c755;
            cursor: pointer;
            box-sizing: border-box;
            box-shadow: 0 2px 4px rgba(6, 199, 85, 0.15);
        }
        .line-login-button:hover {
            background-color: #05b04b;
            border-color: #05b04b;
            color: #ffffff !important;
            box-shadow: 0 4px 8px rgba(6, 199, 85, 0.25);
            transform: translateY(-1px);
        }
        .line-login-button svg {
            margin-right: 10px;
            fill: #ffffff;
            width: 20px;
            height: 20px;
        }
    </style>
    <?php
}

// 3. Hook to handle LINE callback extremely early (before Jetpack SSO intercepts it)
add_action( 'init', 'chao_line_login_handle_callback', 5 );

function chao_line_login_handle_callback() {
    if ( ! isset( $_GET['line-login-callback'] ) ) {
        return;
    }
    
    chao_line_login_log( "Callback reached. GET: " . json_encode($_GET) . " | COOKIES: " . json_encode($_COOKIE) );
    
    if ( get_option( 'chao_line_login_enabled', '0' ) !== '1' ) {
        chao_line_login_log( "Callback aborted: LINE login is disabled." );
        wp_die( 'LINE 登入尚未啟用。', '錯誤' );
    }
    
    // 1. Verify state CSRF
    $cookie_state = isset( $_COOKIE['chao_line_login_state'] ) ? $_COOKIE['chao_line_login_state'] : '';
    $get_state = isset( $_GET['state'] ) ? $_GET['state'] : '';
    
    if ( empty( $get_state ) || empty( $cookie_state ) || $cookie_state !== $get_state ) {
        chao_line_login_log( "CSRF Check Failed. Cookie state: '{$cookie_state}', GET state: '{$get_state}'" );
        wp_die( '錯誤的狀態驗證 (CSRF)，請重新嘗試登入。', '安全驗證失敗' );
    }
    
    // Clear the state cookie
    setcookie( 'chao_line_login_state', '', time() - 3600, '/', '' );
    
    // Get authorization code
    $code = isset( $_GET['code'] ) ? $_GET['code'] : '';
    if ( empty( $code ) ) {
        chao_line_login_log( "Callback aborted: Code is empty." );
        wp_die( '無法取得 LINE 授權碼，請重新嘗試。', '授權失敗' );
    }
    
    // 2. Exchange authorization code for access token
    $channel_id = get_option( 'chao_line_login_channel_id' );
    $channel_secret = get_option( 'chao_line_login_channel_secret' );
    $redirect_uri = add_query_arg( 'line-login-callback', '1', home_url( '/' ) );
    
    chao_line_login_log( "Exchanging code for token... Channel ID: {$channel_id}" );
    
    $response = wp_remote_post( 'https://api.line.me/oauth2/v2.1/token', array(
        'headers' => array(
            'Content-Type' => 'application/x-www-form-urlencoded',
        ),
        'body' => array(
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $redirect_uri,
            'client_id'     => $channel_id,
            'client_secret' => $channel_secret,
        ),
    ));
    
    if ( is_wp_error( $response ) ) {
        chao_line_login_log( "Token exchange API error: " . $response->get_error_message() );
        wp_die( '連線到 LINE API 失敗: ' . esc_html( $response->get_error_message() ), 'API 錯誤' );
    }
    
    $response_code = wp_remote_retrieve_response_code( $response );
    $response_body = wp_remote_retrieve_body( $response );
    chao_line_login_log( "Token exchange response code: {$response_code}. Body: {$response_body}" );
    
    $data = json_decode( $response_body, true );
    
    if ( $response_code !== 200 || ! isset( $data['access_token'] ) ) {
        $error_msg = isset( $data['error_description'] ) ? $data['error_description'] : '未知錯誤';
        chao_line_login_log( "Token exchange failed: {$error_msg}" );
        wp_die( '取得 Access Token 失敗: ' . esc_html( $error_msg ), '授權失敗' );
    }
    
    $access_token = $data['access_token'];
    $id_token = isset( $data['id_token'] ) ? $data['id_token'] : '';
    
    // 3. Get profile details (from ID Token or profile API)
    $line_user_id = '';
    $display_name = '';
    $picture_url = '';
    $email = '';
    
    if ( ! empty( $id_token ) ) {
        $decoded = chao_line_login_decode_jwt_payload( $id_token );
        chao_line_login_log( "Decoded ID Token: " . json_encode($decoded) );
        if ( $decoded ) {
            $line_user_id = isset( $decoded['sub'] ) ? $decoded['sub'] : '';
            $display_name = isset( $decoded['name'] ) ? $decoded['name'] : '';
            $picture_url = isset( $decoded['picture'] ) ? $decoded['picture'] : '';
            $email = isset( $decoded['email'] ) ? $decoded['email'] : '';
        }
    }
    
    // Fallback to Profile API if fields are missing
    if ( empty( $line_user_id ) || empty( $display_name ) ) {
        chao_line_login_log( "Fields missing, fetching Profile API..." );
        $profile_response = wp_remote_get( 'https://api.line.me/v2/profile', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
            ),
        ));
        
        if ( ! is_wp_error( $profile_response ) && wp_remote_retrieve_response_code( $profile_response ) === 200 ) {
            $profile_body = wp_remote_retrieve_body( $profile_response );
            $profile_data = json_decode( $profile_body, true );
            chao_line_login_log( "Profile API Response: {$profile_body}" );
            
            if ( isset( $profile_data['userId'] ) ) {
                $line_user_id = $profile_data['userId'];
                $display_name = isset( $profile_data['displayName'] ) ? $profile_data['displayName'] : $display_name;
                $picture_url = isset( $profile_data['pictureUrl'] ) ? $profile_data['pictureUrl'] : $picture_url;
            }
        } else {
            chao_line_login_log( "Profile API failed or returned non-200." );
        }
    }
    
    if ( empty( $line_user_id ) ) {
        chao_line_login_log( "Aborted: Line User ID is empty." );
        wp_die( '無法取得您的 LINE Profile 資訊，請重新嘗試。', '讀取資料失敗' );
    }
    
    // 4. Find or Create User
    $user = null;
    
    // 4a. Find by line_user_id meta
    $users = get_users( array(
        'meta_key'   => 'chao_line_user_id',
        'meta_value' => $line_user_id,
        'number'     => 1,
    ));
    
    if ( ! empty( $users ) ) {
        $user = $users[0];
        chao_line_login_log( "Found existing user by LINE ID. User ID: {$user->ID}" );
    } else {
        chao_line_login_log( "No user found by LINE ID. Checking by email..." );
        // 4b. Find by email
        if ( ! empty( $email ) ) {
            $user = get_user_by( 'email', $email );
            if ( $user ) {
                chao_line_login_log( "Found existing user by email. User ID: {$user->ID}. Linking accounts..." );
                // Link this existing user to LINE
                update_user_meta( $user->ID, 'chao_line_user_id', $line_user_id );
            }
        }
        
        // 4c. Create new user if not found
        if ( ! $user ) {
            chao_line_login_log( "Creating new user..." );
            // Generate clean unique username
            $base_username = ! empty( $display_name ) ? sanitize_user( $display_name, true ) : 'line_user';
            if ( empty( $base_username ) || strlen( $base_username ) < 3 ) {
                $base_username = 'line_user';
            }
            
            $username = $base_username;
            $suffix = 1;
            while ( username_exists( $username ) ) {
                $username = $base_username . $suffix;
                $suffix++;
            }
            
            // Generate temporary email if email not provided by LINE
            $user_email = ! empty( $email ) ? $email : 'line_' . $line_user_id . '@line-login.local';
            $password = wp_generate_password( 20, false );
            
            $user_id = wp_create_user( $username, $password, $user_email );
            
            if ( is_wp_error( $user_id ) ) {
                chao_line_login_log( "User registration failed: " . $user_id->get_error_message() );
                wp_die( '註冊新帳號失敗: ' . esc_html( $user_id->get_error_message() ), '註冊失敗' );
            }
            
            $user = get_user_by( 'id', $user_id );
            
            // Set role as customer
            $user->set_role( 'customer' );
            
            // Update profile info
            update_user_meta( $user_id, 'first_name', $display_name );
            update_user_meta( $user_id, 'nickname', $display_name );
            update_user_meta( $user_id, 'chao_line_user_id', $line_user_id );
            chao_line_login_log( "Successfully registered new user. User ID: {$user_id}. Email: {$user_email}" );
        }
    }
    
    // Save avatar url for premium avatar display
    if ( ! empty( $picture_url ) ) {
        update_user_meta( $user->ID, 'chao_line_avatar_url', $picture_url );
    }
    
    // 5. Log the user in
    wp_clear_auth_cookie();
    wp_set_current_user( $user->ID );
    wp_set_auth_cookie( $user->ID, true );
    do_action( 'wp_login', $user->user_login, $user );
    
    chao_line_login_log( "User logged in. ID: {$user->ID}." );
    
    // Get redirect URL
    $redirect_url = isset( $_COOKIE['chao_line_login_redirect'] ) ? $_COOKIE['chao_line_login_redirect'] : '';
    if ( empty( $redirect_url ) ) {
        $redirect_url = wc_get_page_permalink( 'myaccount' );
    }
    
    // Clear redirect cookie
    setcookie( 'chao_line_login_redirect', '', time() - 3600, '/', '' );
    
    chao_line_login_log( "Redirecting user to: {$redirect_url}" );
    wp_safe_redirect( $redirect_url );
    exit;
}

// Decode JWT payload
function chao_line_login_decode_jwt_payload( $id_token ) {
    $parts = explode( '.', $id_token );
    if ( count( $parts ) < 2 ) {
        return false;
    }
    $payload = $parts[1];
    $remainder = strlen( $payload ) % 4;
    if ( $remainder ) {
        $padlen = 4 - $remainder;
        $payload .= str_repeat( '=', $padlen );
    }
    $decoded = base64_decode( str_replace( array('-', '_'), array('+', '/'), $payload ) );
    return json_decode( $decoded, true );
}

// Hook get_avatar_url to display LINE profile picture
add_filter( 'get_avatar_url', 'chao_line_login_custom_avatar_url', 10, 3 );
function chao_line_login_custom_avatar_url( $url, $id_or_email, $args ) {
    $user_id = 0;
    if ( is_numeric( $id_or_email ) ) {
        $user_id = (int) $id_or_email;
    } elseif ( is_string( $id_or_email ) && ( $user = get_user_by( 'email', $id_or_email ) ) ) {
        $user_id = $user->ID;
    } elseif ( is_object( $id_or_email ) && isset( $id_or_email->user_id ) && $id_or_email->user_id ) {
        $user_id = (int) $id_or_email->user_id;
    }
    
    if ( $user_id ) {
        $line_avatar = get_user_meta( $user_id, 'chao_line_avatar_url', true );
        if ( $line_avatar ) {
            return $line_avatar;
        }
    }
    return $url;
}

// 4. Hook to clear temporary LINE email in Checkout billing fields
add_filter( 'woocommerce_checkout_get_value', 'chao_line_login_clear_checkout_temp_email', 10, 2 );
function chao_line_login_clear_checkout_temp_email( $value, $input ) {
    if ( 'billing_email' === $input ) {
        // If the email is a temporary line-login.local address, return empty string so it doesn't prefill
        if ( strpos( $value, '@line-login.local' ) !== false ) {
            return '';
        }
    }
    return $value;
}

// 5. Hook to automatically update user profile email when checkout updates it with real email
add_action( 'woocommerce_checkout_update_user_meta', 'chao_line_login_sync_checkout_email_to_profile', 10, 2 );
function chao_line_login_sync_checkout_email_to_profile( $customer_id, $data ) {
    if ( ! $customer_id ) {
        return;
    }
    
    $user = get_userdata( $customer_id );
    if ( ! $user ) {
        return;
    }
    
    $current_email = $user->user_email;
    $new_email = isset( $data['billing_email'] ) ? sanitize_email( $data['billing_email'] ) : '';
    
    // If user currently has a temporary email, and checkout billing email is non-empty and real
    if ( strpos( $current_email, '@line-login.local' ) !== false && ! empty( $new_email ) && strpos( $new_email, '@line-login.local' ) === false ) {
        wp_update_user( array(
            'ID'         => $customer_id,
            'user_email' => $new_email
        ) );
        chao_line_login_log( "Updated user {$customer_id} email from temporary to checkout email: {$new_email}" );
    }
}
