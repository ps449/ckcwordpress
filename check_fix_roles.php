<?php
require_once( 'wp-load.php' );

if ( ! current_user_can( 'manage_options' ) ) {
    echo "權限不足，請先在瀏覽器登入網站管理員帳號後再開啟這個網址。\n";
    exit;
}

$raw = get_option( 'wp_user_roles' );

echo "=== 資料庫目前實際存的角色名稱（未經任何程式碼改寫）===\n";
if ( is_array( $raw ) ) {
    foreach ( $raw as $slug => $data ) {
        $name = isset( $data['name'] ) ? $data['name'] : '(無)';
        echo "$slug => $name\n";
    }
} else {
    echo "讀不到 wp_user_roles 這個選項\n";
}

$fix = isset( $_GET['fix'] ) && '1' === $_GET['fix'];

if ( $fix && is_array( $raw ) ) {
    echo "\n=== 執行修正：customer/subscriber 改回預設名稱 ===\n";
    if ( isset( $raw['customer'] ) ) {
        $raw['customer']['name'] = 'Customer';
    }
    if ( isset( $raw['subscriber'] ) ) {
        $raw['subscriber']['name'] = 'Subscriber';
    }
    update_option( 'wp_user_roles', $raw );
    wp_cache_delete( 'wp_user_roles', 'options' );
    wp_cache_delete( 'user_roles', 'options' );
    if ( function_exists( 'opcache_reset' ) ) {
        opcache_reset();
    }
    echo "已更新，再讀一次確認：\n";
    $check = get_option( 'wp_user_roles' );
    echo "customer => " . ( $check['customer']['name'] ?? '(無)' ) . "\n";
    echo "subscriber => " . ( $check['subscriber']['name'] ?? '(無)' ) . "\n";
} else {
    echo "\n（如果上面 customer/subscriber 顯示的不是英文預設值 Customer/Subscriber，代表資料庫真的被寫進中文名稱了；在網址後面加上 ?fix=1 即可執行修正。）\n";
}
