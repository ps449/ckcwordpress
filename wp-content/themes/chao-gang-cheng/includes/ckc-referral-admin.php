<?php
/**
 * CKC Referral 後台管理頁：分潤夥伴管理（儀表板＋列表）
 *
 *  - 儀表板統計卡：夥伴數、待審核、待確認/可出金/已出金總額、近 30 天推薦訂單
 *  - 夥伴列表：身分、費率（可直接修改）、三態餘額、推薦碼、一鍵出金
 *  - 申請審核列表：核准（KOL／團購主＋費率）或拒絕
 *  - 出金紀錄列表：金額、扣繳、二代健保、實付、時間
 *  - 近期推薦訂單列表：訂單、推薦人、佣金、狀態
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_menu', 'ckc_refadm_register_menu', 61 );
function ckc_refadm_register_menu() {
    add_menu_page(
        '分潤夥伴管理',
        '分潤夥伴',
        'manage_woocommerce',
        'ckc-referral-admin',
        'ckc_refadm_render_page',
        'dashicons-groups',
        56.5
    );
}

// 動作處理（核准／拒絕／出金／調費率）
function ckc_refadm_handle_actions() {
    if ( empty( $_POST['ckc_refadm_action'] ) || ! current_user_can( 'manage_woocommerce' ) ) {
        return '';
    }
    if ( ! isset( $_POST['ckc_refadm_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ckc_refadm_nonce'] ) ), 'ckc_refadm' ) ) {
        return '安全驗證失敗，請重新操作。';
    }
    $action  = sanitize_text_field( wp_unslash( $_POST['ckc_refadm_action'] ) );
    $user_id = isset( $_POST['ckc_refadm_user'] ) ? intval( $_POST['ckc_refadm_user'] ) : 0;
    if ( $user_id <= 0 || ! get_user_by( 'id', $user_id ) ) {
        return '找不到指定會員。';
    }

    if ( 'approve' === $action ) {
        $type = isset( $_POST['ckc_refadm_type'] ) && 'groupbuyer' === $_POST['ckc_refadm_type'] ? 'groupbuyer' : 'kol';
        $rate = isset( $_POST['ckc_refadm_rate'] ) ? sanitize_text_field( wp_unslash( $_POST['ckc_refadm_rate'] ) ) : '';
        update_user_meta( $user_id, '_ckc_ref_partner', $type );
        delete_user_meta( $user_id, '_ckc_ref_partner_apply' );
        if ( is_numeric( $rate ) && floatval( $rate ) > 0 ) {
            update_user_meta( $user_id, '_ckc_ref_partner_rate', $rate );
        }
        return sprintf( '已核准會員 ID %d 為%s。', $user_id, 'kol' === $type ? 'KOL' : '團購主' );
    }
    if ( 'reject' === $action ) {
        delete_user_meta( $user_id, '_ckc_ref_partner_apply' );
        return sprintf( '已拒絕會員 ID %d 的夥伴申請。', $user_id );
    }
    if ( 'revoke' === $action ) {
        update_user_meta( $user_id, '_ckc_ref_partner', '' );
        return sprintf( '已停用會員 ID %d 的夥伴身分（現金帳本保留）。', $user_id );
    }
    if ( 'rate' === $action ) {
        $rate = isset( $_POST['ckc_refadm_rate'] ) ? sanitize_text_field( wp_unslash( $_POST['ckc_refadm_rate'] ) ) : '';
        update_user_meta( $user_id, '_ckc_ref_partner_rate', is_numeric( $rate ) ? $rate : '' );
        return sprintf( '已更新會員 ID %d 的費率。', $user_id );
    }
    if ( 'payout' === $action && function_exists( 'ckc_refp_mark_paid' ) ) {
        return ckc_refp_mark_paid( $user_id );
    }
    return '';
}

function ckc_refadm_render_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( '權限不足' );
    }
    $notice = ckc_refadm_handle_actions();

    // ---- 資料彙整 ----
    $partners = function_exists( 'ckc_refp_all_partners' ) ? ckc_refp_all_partners() : array();
    $partner_rows = array();
    $sum_pending = $sum_confirmed = $sum_paid = 0;
    $kol_count = $gb_count = 0;
    foreach ( $partners as $pu ) {
        $type = ckc_refp_partner_type( $pu->ID );
        if ( ! $type ) {
            continue;
        }
        $sums = ckc_refp_balances( $pu->ID );
        $sum_pending   += $sums['pending'];
        $sum_confirmed += $sums['confirmed'];
        $sum_paid      += $sums['paid'];
        if ( 'kol' === $type ) { $kol_count++; } else { $gb_count++; }
        $partner_rows[] = array( 'user' => $pu, 'type' => $type, 'sums' => $sums );
    }

    $applicants = get_users( array(
        'meta_key'     => '_ckc_ref_partner_apply',
        'meta_compare' => 'EXISTS',
    ) );
    // 已是夥伴者不重複列入申請
    $applicants = array_filter( $applicants, function ( $u ) {
        return ! ckc_refp_partner_type( $u->ID );
    } );

    // 近 30 天推薦訂單
    $recent_orders = wc_get_orders( array(
        'limit'        => 20,
        'orderby'      => 'date',
        'order'        => 'DESC',
        'date_created' => '>' . ( time() - 30 * DAY_IN_SECONDS ),
        'meta_query'   => array(
            array( 'key' => '_ckc_referrer_id', 'compare' => 'EXISTS' ),
        ),
    ) );
    $ref_order_count = count( $recent_orders );
    $ref_order_total = 0;
    foreach ( $recent_orders as $ro ) {
        $ref_order_total += floatval( $ro->get_total() );
    }

    // 出金紀錄
    $payout_rows = array();
    foreach ( $partner_rows as $row ) {
        $payouts = get_user_meta( $row['user']->ID, '_ckc_ref_payouts', true );
        if ( is_array( $payouts ) ) {
            foreach ( $payouts as $po ) {
                $po['name'] = $row['user']->display_name;
                $po['uid']  = $row['user']->ID;
                $payout_rows[] = $po;
            }
        }
    }
    usort( $payout_rows, function ( $a, $b ) {
        return strcmp( $b['time'], $a['time'] );
    } );

    $threshold = function_exists( 'ckc_refp_payout_threshold' ) ? ckc_refp_payout_threshold() : 1000;
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">分潤夥伴管理</h1>
        <?php if ( $notice ) : ?>
            <div class="notice notice-info is-dismissible" style="margin-top:10px;"><p><?php echo esc_html( $notice ); ?></p></div>
        <?php endif; ?>

        <style>
        .ckc-refadm-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 14px; margin: 18px 0 26px; }
        .ckc-refadm-card { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 16px 18px; }
        .ckc-refadm-card .label { font-size: 12px; color: #646970; margin-bottom: 6px; }
        .ckc-refadm-card .value { font-size: 24px; font-weight: 700; color: #1d2327; line-height: 1.2; }
        .ckc-refadm-card .sub { font-size: 11px; color: #8c8f94; margin-top: 4px; }
        .ckc-refadm-section { margin-bottom: 34px; }
        .ckc-refadm-section h2 { font-size: 16px; margin-bottom: 10px; }
        .ckc-refadm-inline-form { display: inline-flex; gap: 6px; align-items: center; }
        .ckc-refadm-inline-form input[type="number"] { width: 70px; }
        </style>

        <!-- 儀表板 -->
        <div class="ckc-refadm-cards">
            <div class="ckc-refadm-card"><div class="label">推廣夥伴</div><div class="value"><?php echo esc_html( $kol_count + $gb_count ); ?></div><div class="sub">KOL <?php echo esc_html( $kol_count ); ?>・團購主 <?php echo esc_html( $gb_count ); ?></div></div>
            <div class="ckc-refadm-card"><div class="label">待審核申請</div><div class="value" style="color:<?php echo count( $applicants ) ? '#b32d2e' : '#1d2327'; ?>;"><?php echo esc_html( count( $applicants ) ); ?></div></div>
            <div class="ckc-refadm-card"><div class="label">待確認佣金（鑑賞期中）</div><div class="value">NT$<?php echo esc_html( number_format( $sum_pending ) ); ?></div></div>
            <div class="ckc-refadm-card"><div class="label">可出金總額</div><div class="value" style="color:#b32d2e;">NT$<?php echo esc_html( number_format( $sum_confirmed ) ); ?></div><div class="sub">出金門檻 NT$<?php echo esc_html( number_format( $threshold ) ); ?>／人</div></div>
            <div class="ckc-refadm-card"><div class="label">累計已出金</div><div class="value" style="color:#00a32a;">NT$<?php echo esc_html( number_format( $sum_paid ) ); ?></div></div>
            <div class="ckc-refadm-card"><div class="label">近 30 天推薦訂單</div><div class="value"><?php echo esc_html( $ref_order_count ); ?> 筆</div><div class="sub">推薦營收 NT$<?php echo esc_html( number_format( $ref_order_total ) ); ?></div></div>
        </div>

        <!-- 待審核申請 -->
        <div class="ckc-refadm-section">
            <h2>📝 待審核申請（<?php echo esc_html( count( $applicants ) ); ?>）</h2>
            <table class="widefat striped">
                <thead><tr><th>會員</th><th>Email</th><th>申請日期</th><th style="width:380px;">審核動作</th></tr></thead>
                <tbody>
                <?php if ( empty( $applicants ) ) : ?>
                    <tr><td colspan="4">目前沒有待審核的申請。</td></tr>
                <?php else : foreach ( $applicants as $au ) :
                    $applied = intval( get_user_meta( $au->ID, '_ckc_ref_partner_apply', true ) ); ?>
                    <tr>
                        <td><?php echo esc_html( $au->display_name ); ?>（ID <?php echo esc_html( $au->ID ); ?>）</td>
                        <td><?php echo esc_html( $au->user_email ); ?></td>
                        <td><?php echo esc_html( $applied ? date_i18n( 'Y-m-d', $applied ) : '—' ); ?></td>
                        <td>
                            <form method="post" class="ckc-refadm-inline-form">
                                <?php wp_nonce_field( 'ckc_refadm', 'ckc_refadm_nonce' ); ?>
                                <input type="hidden" name="ckc_refadm_user" value="<?php echo esc_attr( $au->ID ); ?>">
                                <select name="ckc_refadm_type">
                                    <option value="kol">KOL</option>
                                    <option value="groupbuyer">團購主</option>
                                </select>
                                <input type="number" name="ckc_refadm_rate" step="0.1" min="0" max="50" placeholder="8%">
                                <button type="submit" name="ckc_refadm_action" value="approve" class="button button-primary">核准</button>
                                <button type="submit" name="ckc_refadm_action" value="reject" class="button">拒絕</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 夥伴列表 -->
        <div class="ckc-refadm-section">
            <h2>⭐ 夥伴列表（<?php echo esc_html( count( $partner_rows ) ); ?>）</h2>
            <table class="widefat striped">
                <thead><tr><th>夥伴</th><th>身分</th><th>推薦碼</th><th>費率</th><th>待確認</th><th>可出金</th><th>已出金</th><th style="width:300px;">動作</th></tr></thead>
                <tbody>
                <?php if ( empty( $partner_rows ) ) : ?>
                    <tr><td colspan="8">尚未有推廣夥伴。可於「待審核申請」核准，或在會員資料頁直接指定。</td></tr>
                <?php else : foreach ( $partner_rows as $row ) :
                    $pu = $row['user'];
                    $rate_pc = rtrim( rtrim( number_format( ckc_refp_partner_rate( $pu->ID ) * 100, 1 ), '0' ), '.' ); ?>
                    <tr>
                        <td><a href="<?php echo esc_url( get_edit_user_link( $pu->ID ) ); ?>"><?php echo esc_html( $pu->display_name ); ?></a>（ID <?php echo esc_html( $pu->ID ); ?>）</td>
                        <td><?php echo 'kol' === $row['type'] ? 'KOL' : '團購主'; ?></td>
                        <td><code><?php echo esc_html( ckc_ref_get_code( $pu->ID ) ); ?></code></td>
                        <td>
                            <form method="post" class="ckc-refadm-inline-form">
                                <?php wp_nonce_field( 'ckc_refadm', 'ckc_refadm_nonce' ); ?>
                                <input type="hidden" name="ckc_refadm_user" value="<?php echo esc_attr( $pu->ID ); ?>">
                                <input type="number" name="ckc_refadm_rate" step="0.1" min="0" max="50" value="<?php echo esc_attr( $rate_pc ); ?>">%
                                <button type="submit" name="ckc_refadm_action" value="rate" class="button button-small">更新</button>
                            </form>
                        </td>
                        <td>NT$<?php echo esc_html( number_format( $row['sums']['pending'] ) ); ?></td>
                        <td style="font-weight:700;color:<?php echo $row['sums']['confirmed'] >= $threshold ? '#b32d2e' : '#1d2327'; ?>;">NT$<?php echo esc_html( number_format( $row['sums']['confirmed'] ) ); ?></td>
                        <td>NT$<?php echo esc_html( number_format( $row['sums']['paid'] ) ); ?></td>
                        <td>
                            <form method="post" class="ckc-refadm-inline-form">
                                <?php wp_nonce_field( 'ckc_refadm', 'ckc_refadm_nonce' ); ?>
                                <input type="hidden" name="ckc_refadm_user" value="<?php echo esc_attr( $pu->ID ); ?>">
                                <button type="submit" name="ckc_refadm_action" value="payout" class="button <?php echo $row['sums']['confirmed'] >= $threshold ? 'button-primary' : ''; ?>" <?php disabled( $row['sums']['confirmed'] < $threshold ); ?> onclick="return confirm('確認將此夥伴的可出金分潤標記為已出金？請先完成實際匯款作業。');">標記出金</button>
                                <button type="submit" name="ckc_refadm_action" value="revoke" class="button button-link-delete" formnovalidate onclick="return confirm('確認停用此夥伴身分？');">停用</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 出金紀錄 -->
        <div class="ckc-refadm-section">
            <h2>💰 出金紀錄（<?php echo esc_html( count( $payout_rows ) ); ?>）</h2>
            <table class="widefat striped">
                <thead><tr><th>時間</th><th>夥伴</th><th>出金總額</th><th>扣繳稅款</th><th>二代健保</th><th>實付金額</th></tr></thead>
                <tbody>
                <?php if ( empty( $payout_rows ) ) : ?>
                    <tr><td colspan="6">尚無出金紀錄。</td></tr>
                <?php else : foreach ( array_slice( $payout_rows, 0, 50 ) as $po ) : ?>
                    <tr>
                        <td><?php echo esc_html( $po['time'] ); ?></td>
                        <td><?php echo esc_html( $po['name'] ); ?>（ID <?php echo esc_html( $po['uid'] ); ?>）</td>
                        <td>NT$<?php echo esc_html( number_format( intval( $po['amount'] ) ) ); ?></td>
                        <td>NT$<?php echo esc_html( number_format( intval( $po['withholding'] ) ) ); ?></td>
                        <td>NT$<?php echo esc_html( number_format( intval( $po['nhi'] ) ) ); ?></td>
                        <td style="font-weight:700;">NT$<?php echo esc_html( number_format( intval( $po['net'] ) ) ); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
            <p style="color:#8c8f94;font-size:12px;">扣繳與二代健保為系統試算值，實際申報請依會計師指示。</p>
        </div>

        <!-- 近期推薦訂單 -->
        <div class="ckc-refadm-section">
            <h2>🛒 近 30 天推薦訂單（最多顯示 20 筆）</h2>
            <table class="widefat striped">
                <thead><tr><th>訂單</th><th>日期</th><th>推薦人</th><th>訂單金額</th><th>佣金</th><th>訂單狀態</th></tr></thead>
                <tbody>
                <?php if ( empty( $recent_orders ) ) : ?>
                    <tr><td colspan="6">近 30 天沒有推薦訂單。</td></tr>
                <?php else : foreach ( $recent_orders as $ro ) :
                    $rid   = intval( $ro->get_meta( '_ckc_referrer_id' ) );
                    $ruser = get_user_by( 'id', $rid );
                    $paid  = $ro->get_meta( '_ckc_ref_commission_paid' );
                    if ( 'cash' === $paid ) {
                        $commission = 'NT$' . number_format( intval( $ro->get_meta( '_ckc_ref_cash_amount' ) ) ) . '（現金）';
                    } elseif ( is_numeric( $paid ) && intval( $paid ) > 0 ) {
                        $commission = intval( $paid ) . ' 點（點數）';
                    } else {
                        $commission = '未發放／不適用';
                    }
                    ?>
                    <tr>
                        <td><a href="<?php echo esc_url( $ro->get_edit_order_url() ); ?>">#<?php echo esc_html( $ro->get_order_number() ); ?></a></td>
                        <td><?php echo esc_html( $ro->get_date_created() ? $ro->get_date_created()->date_i18n( 'Y-m-d' ) : '—' ); ?></td>
                        <td><?php echo esc_html( $ruser ? $ruser->display_name : ( '會員 ID ' . $rid ) ); ?></td>
                        <td>NT$<?php echo esc_html( number_format( floatval( $ro->get_total() ) ) ); ?></td>
                        <td><?php echo esc_html( $commission ); ?></td>
                        <td><?php echo esc_html( wc_get_order_status_name( $ro->get_status() ) ); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
