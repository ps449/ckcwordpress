<?php
/**
 * CKC Referral / 分潤系統（CyberBiz 顧客推薦模式，第一階段：紅利點數軌）
 *
 * 功能：
 *  - 每位會員專屬推薦碼與推薦連結（?ref=CODE）
 *  - 30 天 cookie 歸因（last-click），自我推薦防護
 *  - 訂單完成後發放推薦人點數（預設訂單小計 5%，單筆上限 300 點）
 *  - 被推薦人首購完成加贈點數（預設 50 點）
 *  - 退款／取消自動反沖已發點數
 *  - 我的帳號「推薦好友」專區（連結複製、LINE 分享、成效統計）
 *
 * 費率與上限皆可透過 filter 調整，詳見 affiliate_program_plan_taiwan.docx。
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ------------------------------------------------------------------
 * 設定（可用 filter 覆寫）
 * ---------------------------------------------------------------- */
function ckc_ref_commission_rate() {
    return floatval( apply_filters( 'ckc_ref_commission_rate', 0.05 ) ); // 5%
}
function ckc_ref_commission_cap() {
    return intval( apply_filters( 'ckc_ref_commission_cap', 300 ) ); // 單筆上限 300 點
}
function ckc_ref_cookie_days() {
    return intval( apply_filters( 'ckc_ref_cookie_days', 30 ) ); // 歸因 30 天
}
function ckc_ref_referred_bonus() {
    return intval( apply_filters( 'ckc_ref_referred_first_order_bonus', 50 ) ); // 被推薦人首購加贈
}

/* ------------------------------------------------------------------
 * 推薦碼
 * ---------------------------------------------------------------- */
function ckc_ref_get_code( $user_id ) {
    $user_id = intval( $user_id );
    if ( $user_id <= 0 ) {
        return '';
    }
    $code = get_user_meta( $user_id, '_ckc_ref_code', true );
    if ( ! $code ) {
        $code = 'CKC' . strtoupper( base_convert( (string) ( $user_id * 137 + 10007 ), 10, 36 ) );
        update_user_meta( $user_id, '_ckc_ref_code', $code );
    }
    return $code;
}

function ckc_ref_user_by_code( $code ) {
    $code = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $code ) );
    if ( '' === $code ) {
        return 0;
    }
    $users = get_users( array(
        'meta_key'   => '_ckc_ref_code',
        'meta_value' => $code,
        'number'     => 1,
        'fields'     => 'ID',
    ) );
    return ! empty( $users ) ? intval( $users[0] ) : 0;
}

function ckc_ref_link( $user_id ) {
    return add_query_arg( 'ref', ckc_ref_get_code( $user_id ), home_url( '/' ) );
}

/* ------------------------------------------------------------------
 * 歸因：?ref= 參數 → cookie（last-click，30 天）
 * ---------------------------------------------------------------- */
add_action( 'init', 'ckc_ref_capture_visit', 20 );
function ckc_ref_capture_visit() {
    if ( empty( $_GET['ref'] ) || is_admin() ) {
        return;
    }
    $referrer_id = ckc_ref_user_by_code( sanitize_text_field( wp_unslash( $_GET['ref'] ) ) );
    if ( ! $referrer_id ) {
        return;
    }
    // 自我點擊不覆寫
    if ( get_current_user_id() === $referrer_id ) {
        return;
    }
    $expiry = time() + ckc_ref_cookie_days() * DAY_IN_SECONDS;
    if ( function_exists( 'wc_setcookie' ) ) {
        wc_setcookie( 'ckc_ref', (string) $referrer_id, $expiry );
    } else {
        setcookie( 'ckc_ref', (string) $referrer_id, $expiry, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN );
    }
    $_COOKIE['ckc_ref'] = (string) $referrer_id;
}

/* ------------------------------------------------------------------
 * 訂單綁定推薦人（結帳建立訂單時）
 * ---------------------------------------------------------------- */
add_action( 'woocommerce_checkout_create_order', 'ckc_ref_attach_to_order', 20, 2 );
function ckc_ref_attach_to_order( $order, $data ) {
    if ( empty( $_COOKIE['ckc_ref'] ) ) {
        return;
    }
    $referrer_id = intval( $_COOKIE['ckc_ref'] );
    if ( $referrer_id <= 0 || ! get_user_by( 'id', $referrer_id ) ) {
        return;
    }
    // 自我推薦防護：下單者本人，或與推薦人同 Email
    $customer_id = $order->get_customer_id();
    if ( $customer_id && $customer_id === $referrer_id ) {
        return;
    }
    $referrer = get_user_by( 'id', $referrer_id );
    if ( $referrer && strtolower( $referrer->user_email ) === strtolower( (string) $order->get_billing_email() ) ) {
        return;
    }
    $order->update_meta_data( '_ckc_referrer_id', $referrer_id );
    $order->update_meta_data( '_ckc_ref_code', ckc_ref_get_code( $referrer_id ) );
}

/* ------------------------------------------------------------------
 * 點數發放輔助（與 WPS Points and Rewards 相容）
 * ---------------------------------------------------------------- */
function ckc_ref_add_points( $user_id, $points, $reason ) {
    $user_id = intval( $user_id );
    $points  = intval( $points );
    if ( $user_id <= 0 || 0 === $points ) {
        return false;
    }
    $balance = (int) get_user_meta( $user_id, 'wps_wpr_points', true );
    $new_balance = max( 0, $balance + $points );
    update_user_meta( $user_id, 'wps_wpr_points', $new_balance );

    // 寫入 WPS 點數紀錄（admin_points 類別），讓外掛紀錄頁保持一致
    $details = get_user_meta( $user_id, 'points_details', true );
    if ( ! is_array( $details ) ) {
        $details = array();
    }
    if ( ! isset( $details['admin_points'] ) || ! is_array( $details['admin_points'] ) ) {
        $details['admin_points'] = array();
    }
    $details['admin_points'][] = array(
        'admin_points' => $points,
        'date'         => date( 'Y-m-d h:i:sa' ),
    );
    update_user_meta( $user_id, 'points_details', $details );

    // 自有分潤紀錄（推薦專區統計用）
    $log = get_user_meta( $user_id, '_ckc_ref_log', true );
    if ( ! is_array( $log ) ) {
        $log = array();
    }
    $log[] = array(
        'points' => $points,
        'reason' => sanitize_text_field( $reason ),
        'time'   => current_time( 'mysql' ),
    );
    update_user_meta( $user_id, '_ckc_ref_log', $log );
    return true;
}

/* ------------------------------------------------------------------
 * 訂單完成 → 發放分潤點數＋被推薦人首購加贈
 * ---------------------------------------------------------------- */
add_action( 'woocommerce_order_status_completed', 'ckc_ref_pay_commission', 20 );
function ckc_ref_pay_commission( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }
    $referrer_id = intval( $order->get_meta( '_ckc_referrer_id' ) );
    if ( $referrer_id <= 0 ) {
        return;
    }
    if ( $order->get_meta( '_ckc_ref_commission_paid' ) ) {
        return; // 已發放，避免重複
    }

    // 佣金基礎：商品小計（不含運費），扣除折讓
    $basis  = floatval( $order->get_subtotal() ) - floatval( $order->get_total_discount() );
    $points = (int) floor( max( 0, $basis ) * ckc_ref_commission_rate() );
    $points = min( $points, ckc_ref_commission_cap() );

    if ( $points > 0 && ckc_ref_add_points( $referrer_id, $points, sprintf( '推薦訂單 #%d 分潤', $order_id ) ) ) {
        $order->update_meta_data( '_ckc_ref_commission_paid', $points );
        $order->save();
        $order->add_order_note( sprintf( '分潤系統：已發放 %d 點紅利給推薦人（會員 ID %d，推薦碼 %s）。', $points, $referrer_id, $order->get_meta( '_ckc_ref_code' ) ) );
    }

    // 被推薦人首購加贈
    $customer_id = $order->get_customer_id();
    if ( $customer_id && ! get_user_meta( $customer_id, '_ckc_ref_first_bonus_given', true ) ) {
        $bonus = ckc_ref_referred_bonus();
        if ( $bonus > 0 && ckc_ref_add_points( $customer_id, $bonus, sprintf( '首購禮（經推薦碼 %s 推薦）', $order->get_meta( '_ckc_ref_code' ) ) ) ) {
            update_user_meta( $customer_id, '_ckc_ref_first_bonus_given', $order_id );
            $order->add_order_note( sprintf( '分潤系統：已加贈首購禮 %d 點給被推薦客戶。', $bonus ), 1 );
        }
    }
}

/* ------------------------------------------------------------------
 * 退款／取消 → 反沖已發點數
 * ---------------------------------------------------------------- */
add_action( 'woocommerce_order_status_refunded', 'ckc_ref_reverse_commission', 20 );
add_action( 'woocommerce_order_status_cancelled', 'ckc_ref_reverse_commission', 20 );
function ckc_ref_reverse_commission( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }
    $paid = intval( $order->get_meta( '_ckc_ref_commission_paid' ) );
    if ( $paid <= 0 || $order->get_meta( '_ckc_ref_commission_reversed' ) ) {
        return;
    }
    $referrer_id = intval( $order->get_meta( '_ckc_referrer_id' ) );
    if ( $referrer_id > 0 && ckc_ref_add_points( $referrer_id, -$paid, sprintf( '訂單 #%d 退款／取消，分潤回收', $order_id ) ) ) {
        $order->update_meta_data( '_ckc_ref_commission_reversed', $paid );
        $order->save();
        $order->add_order_note( sprintf( '分潤系統：訂單退款／取消，已自推薦人（會員 ID %d）回收 %d 點。', $referrer_id, $paid ) );
    }
}

/* ------------------------------------------------------------------
 * 我的帳號「推薦好友」專區
 * ---------------------------------------------------------------- */
add_action( 'init', 'ckc_ref_register_endpoint', 6 );
function ckc_ref_register_endpoint() {
    add_rewrite_endpoint( 'referral', EP_ROOT | EP_PAGES );
    // 首次載入時沖洗 rewrite rules（一次性）
    if ( '1' !== get_option( 'ckc_ref_rewrite_flushed' ) ) {
        flush_rewrite_rules();
        update_option( 'ckc_ref_rewrite_flushed', '1' );
    }
}

add_filter( 'woocommerce_account_menu_items', 'ckc_ref_account_menu_item', 20 );
function ckc_ref_account_menu_item( $items ) {
    $new = array();
    foreach ( $items as $key => $label ) {
        if ( 'customer-logout' === $key ) {
            $new['referral'] = '推薦好友';
        }
        $new[ $key ] = $label;
    }
    if ( ! isset( $new['referral'] ) ) {
        $new['referral'] = '推薦好友';
    }
    return $new;
}

add_filter( 'woocommerce_endpoint_referral_title', function () {
    return '推薦好友';
} );

add_action( 'woocommerce_account_referral_endpoint', 'ckc_ref_account_referral_content' );
function ckc_ref_account_referral_content() {
    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return;
    }
    $code = ckc_ref_get_code( $user_id );
    $link = ckc_ref_link( $user_id );

    // 成效統計：推薦訂單數與累計分潤點數
    $ref_orders = wc_get_orders( array(
        'limit'      => -1,
        'return'     => 'ids',
        'meta_query' => array(
            array(
                'key'   => '_ckc_referrer_id',
                'value' => $user_id,
            ),
        ),
    ) );
    $order_count = is_array( $ref_orders ) ? count( $ref_orders ) : 0;

    $earned = 0;
    $log = get_user_meta( $user_id, '_ckc_ref_log', true );
    if ( is_array( $log ) ) {
        foreach ( $log as $entry ) {
            if ( isset( $entry['points'] ) && intval( $entry['points'] ) > 0 && isset( $entry['reason'] ) && false !== mb_strpos( $entry['reason'], '分潤' ) ) {
                $earned += intval( $entry['points'] );
            }
        }
    }

    $rate_percent = round( ckc_ref_commission_rate() * 100 );
    $bonus        = ckc_ref_referred_bonus();
    $line_share   = 'https://social-plugins.line.me/lineit/share?url=' . rawurlencode( $link );
    ?>
    <div style="background: #fdfaf7; border: 1px solid #f5ebe6; border-radius: 10px; padding: 20px; margin-bottom: 20px;">
        <h3 style="margin: 0 0 8px; font-size: 17px; color: #7f6c60;">🤝 分享美味，賺紅利點數</h3>
        <p style="margin: 0; font-size: 14px; color: #6b7280; line-height: 1.8;">
            把您的專屬連結分享給親朋好友——好友透過連結下單完成後，您可獲得訂單金額
            <strong style="color: #b91c1c;"><?php echo esc_html( $rate_percent ); ?>%</strong> 的紅利點數回饋（單筆最高 <?php echo esc_html( ckc_ref_commission_cap() ); ?> 點），
            好友首購再加贈 <strong style="color: #b91c1c;"><?php echo esc_html( $bonus ); ?> 點</strong>！1 點可折抵 NT$1。
        </p>
    </div>

    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 20px;">
        <p style="margin: 0 0 8px; font-size: 13px; font-weight: 700; color: #64748b;">您的專屬推薦連結（推薦碼：<?php echo esc_html( $code ); ?>）</p>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <input type="text" id="ckc-ref-link" readonly value="<?php echo esc_attr( $link ); ?>"
                   style="flex: 1; min-width: 220px; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px; font-size: 13px; color: #334155; background: #f8fafc;">
            <button type="button" id="ckc-ref-copy"
                    style="border: none; background: #7f6c60; color: #fff; border-radius: 6px; padding: 10px 20px; font-size: 13px; font-weight: 600; cursor: pointer;">複製連結</button>
            <a href="<?php echo esc_url( $line_share ); ?>" target="_blank" rel="noopener"
               style="display: inline-flex; align-items: center; gap: 6px; background: #06C755; color: #fff; border-radius: 6px; padding: 10px 20px; font-size: 13px; font-weight: 700; text-decoration: none;">LINE 分享</a>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; text-align: center;">
            <div style="font-size: 13px; color: #64748b; margin-bottom: 6px;">累計推薦訂單</div>
            <div style="font-size: 28px; font-weight: 700; color: #1e293b;"><?php echo esc_html( number_format( $order_count ) ); ?> <span style="font-size: 14px; color: #94a3b8;">筆</span></div>
        </div>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; text-align: center;">
            <div style="font-size: 13px; color: #64748b; margin-bottom: 6px;">累計獲得分潤點數</div>
            <div style="font-size: 28px; font-weight: 700; color: #b91c1c;"><?php echo esc_html( number_format( $earned ) ); ?> <span style="font-size: 14px; color: #94a3b8;">點</span></div>
        </div>
    </div>

    <p style="margin-top: 16px; font-size: 12px; color: #94a3b8; line-height: 1.8;">
        注意事項：分潤點數於好友訂單「完成」後發放；若訂單退款或取消，對應點數將自動回收。禁止以自己的帳號透過推薦連結下單（自我推薦），系統將自動排除。本活動辦法得由潮港城調整並公告。
    </p>

    <script>
    (function() {
        var btn = document.getElementById('ckc-ref-copy');
        if (!btn) { return; }
        btn.addEventListener('click', function() {
            var input = document.getElementById('ckc-ref-link');
            input.select();
            input.setSelectionRange(0, 99999);
            var done = function() {
                btn.textContent = '已複製 ✓';
                setTimeout(function() { btn.textContent = '複製連結'; }, 2000);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(input.value).then(done);
            } else {
                document.execCommand('copy');
                done();
            }
        });
    })();
    </script>
    <?php
}

/* ------------------------------------------------------------------
 * 後台報表輔助（供出貨AI助理呼叫）
 * ---------------------------------------------------------------- */
function ckc_ref_admin_report_text() {
    $orders = wc_get_orders( array(
        'limit'      => 100,
        'orderby'    => 'date',
        'order'      => 'DESC',
        'meta_query' => array(
            array(
                'key'     => '_ckc_referrer_id',
                'compare' => 'EXISTS',
            ),
        ),
    ) );
    if ( empty( $orders ) ) {
        return '目前尚無任何經推薦連結成立的訂單。';
    }
    $total_orders = 0;
    $total_amount = 0;
    $total_points = 0;
    $by_referrer  = array();
    foreach ( $orders as $order ) {
        $total_orders++;
        $total_amount += floatval( $order->get_total() );
        $paid = intval( $order->get_meta( '_ckc_ref_commission_paid' ) );
        $total_points += $paid;
        $rid = intval( $order->get_meta( '_ckc_referrer_id' ) );
        if ( ! isset( $by_referrer[ $rid ] ) ) {
            $by_referrer[ $rid ] = array( 'orders' => 0, 'points' => 0 );
        }
        $by_referrer[ $rid ]['orders']++;
        $by_referrer[ $rid ]['points'] += $paid;
    }
    uasort( $by_referrer, function ( $a, $b ) {
        return $b['points'] - $a['points'];
    } );
    $top_lines = array();
    $i = 0;
    foreach ( $by_referrer as $rid => $stat ) {
        if ( ++$i > 5 ) {
            break;
        }
        $user = get_user_by( 'id', $rid );
        $name = $user ? $user->display_name : ( '會員 ID ' . $rid );
        $top_lines[] = sprintf( '%d. %s：%d 筆訂單，累計 %d 點', $i, $name, $stat['orders'], $stat['points'] );
    }
    return sprintf(
        "推薦訂單共 %d 筆（最近 100 筆內），推薦營收 NT$%s，已發放分潤 %d 點。\nTop 推薦人：\n%s",
        $total_orders,
        number_format( $total_amount ),
        $total_points,
        implode( "\n", $top_lines )
    );
}
