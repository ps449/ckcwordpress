<?php
/**
 * CKC Referral Phase 2：KOL／團購主 現金分潤軌（夥伴制度）
 *
 *  - 夥伴身分（kol / groupbuyer）與個別費率（後台使用者資料頁管理，預設 8%）
 *  - 前台「申請成為推廣夥伴」流程（送出申請 → 後台核准）
 *  - 指定商品分潤費率（商品編輯頁欄位，未設定則用夥伴費率；票券分類預設排除）
 *  - 現金帳本：訂單完成 → pending（7 天鑑賞期）→ confirmed → paid；退款自動反沖
 *  - 月結對帳單：含扣繳 10%（單次 >= 20,010）與二代健保 2.11%（單次 >= 20,000）試算
 *  - 出金門檻 NT$1,000；出貨AI助理可產生對帳單與標記出金
 *  - 推薦專區夥伴儀表板（費率、待確認/可出金/已出金、QR Code）
 *
 * 稅務試算僅供對帳參考，實際扣繳申報請由會計師確認。
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ---------------- 設定 ---------------- */
function ckc_refp_default_rate() {
    return floatval( apply_filters( 'ckc_refp_default_rate', 0.08 ) ); // 夥伴預設 8%
}
function ckc_refp_cap() {
    return intval( apply_filters( 'ckc_refp_commission_cap', 1000 ) ); // 單筆上限 NT$1,000
}
function ckc_refp_holding_days() {
    return intval( apply_filters( 'ckc_refp_holding_days', 7 ) ); // 鑑賞期
}
function ckc_refp_payout_threshold() {
    return intval( apply_filters( 'ckc_refp_payout_threshold', 1000 ) ); // 出金門檻
}
function ckc_refp_excluded_categories() {
    return apply_filters( 'ckc_refp_excluded_categories', array( 'tickets' ) ); // 低毛利品類排除
}

/* ---------------- 夥伴身分 ---------------- */
function ckc_refp_partner_type( $user_id ) {
    $type = get_user_meta( intval( $user_id ), '_ckc_ref_partner', true );
    return in_array( $type, array( 'kol', 'groupbuyer' ), true ) ? $type : '';
}
function ckc_refp_partner_rate( $user_id ) {
    $rate = get_user_meta( intval( $user_id ), '_ckc_ref_partner_rate', true );
    if ( '' !== $rate && is_numeric( $rate ) && floatval( $rate ) > 0 ) {
        return floatval( $rate ) / 100;
    }
    return ckc_refp_default_rate();
}

// 後台使用者資料頁：夥伴欄位（僅管理員可編輯）
add_action( 'show_user_profile', 'ckc_refp_user_fields' );
add_action( 'edit_user_profile', 'ckc_refp_user_fields' );
function ckc_refp_user_fields( $user ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $type    = get_user_meta( $user->ID, '_ckc_ref_partner', true );
    $rate    = get_user_meta( $user->ID, '_ckc_ref_partner_rate', true );
    $applied = get_user_meta( $user->ID, '_ckc_ref_partner_apply', true );
    ?>
    <h2>分潤夥伴設定</h2>
    <table class="form-table">
        <tr>
            <th><label for="ckc_ref_partner">夥伴身分</label></th>
            <td>
                <select name="ckc_ref_partner" id="ckc_ref_partner">
                    <option value="" <?php selected( $type, '' ); ?>>一般會員（點數推薦）</option>
                    <option value="kol" <?php selected( $type, 'kol' ); ?>>KOL／內容創作者（現金分潤）</option>
                    <option value="groupbuyer" <?php selected( $type, 'groupbuyer' ); ?>>團購主（現金分潤）</option>
                </select>
                <?php if ( $applied && ! $type ) : ?>
                    <p class="description" style="color:#b91c1c;">此會員已於 <?php echo esc_html( date( 'Y-m-d', intval( $applied ) ) ); ?> 送出夥伴申請，等待核准。</p>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th><label for="ckc_ref_partner_rate">夥伴費率（%）</label></th>
            <td>
                <input type="number" step="0.1" min="0" max="50" name="ckc_ref_partner_rate" id="ckc_ref_partner_rate" value="<?php echo esc_attr( $rate ); ?>" placeholder="8">
                <p class="description">留空使用預設 8%。指定商品費率（商品編輯頁）優先於此設定。</p>
            </td>
        </tr>
    </table>
    <?php
}
add_action( 'personal_options_update', 'ckc_refp_save_user_fields' );
add_action( 'edit_user_profile_update', 'ckc_refp_save_user_fields' );
function ckc_refp_save_user_fields( $user_id ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    if ( isset( $_POST['ckc_ref_partner'] ) ) {
        $type = sanitize_text_field( wp_unslash( $_POST['ckc_ref_partner'] ) );
        if ( in_array( $type, array( '', 'kol', 'groupbuyer' ), true ) ) {
            update_user_meta( $user_id, '_ckc_ref_partner', $type );
            if ( $type ) {
                delete_user_meta( $user_id, '_ckc_ref_partner_apply' );
            }
        }
    }
    if ( isset( $_POST['ckc_ref_partner_rate'] ) ) {
        $rate = sanitize_text_field( wp_unslash( $_POST['ckc_ref_partner_rate'] ) );
        update_user_meta( $user_id, '_ckc_ref_partner_rate', is_numeric( $rate ) ? $rate : '' );
    }
}

/* ---------------- 指定商品費率（商品編輯頁欄位） ---------------- */
add_action( 'woocommerce_product_options_general_product_data', 'ckc_refp_product_rate_field' );
function ckc_refp_product_rate_field() {
    echo '<div class="options_group">';
    woocommerce_wp_text_field( array(
        'id'          => '_ckc_ref_product_rate',
        'label'       => '分潤費率（%）',
        'placeholder' => '留空用夥伴費率',
        'desc_tip'    => true,
        'description' => '此商品的夥伴現金分潤費率；留空則使用夥伴個別費率（預設 8%）。設 0 表示此商品不分潤。',
        'type'        => 'number',
        'custom_attributes' => array( 'step' => '0.1', 'min' => '0', 'max' => '50' ),
    ) );
    echo '</div>';
}
add_action( 'woocommerce_admin_process_product_object', 'ckc_refp_save_product_rate' );
function ckc_refp_save_product_rate( $product ) {
    $val = isset( $_POST['_ckc_ref_product_rate'] ) ? sanitize_text_field( wp_unslash( $_POST['_ckc_ref_product_rate'] ) ) : '';
    $product->update_meta_data( '_ckc_ref_product_rate', is_numeric( $val ) ? $val : '' );
}

/* ---------------- 佣金計算（逐品項，含排除分類與商品費率） ---------------- */
function ckc_refp_calc_commission( $order, $partner_rate ) {
    $excluded = ckc_refp_excluded_categories();
    $total = 0.0;
    foreach ( $order->get_items() as $item ) {
        $product = $item->get_product();
        if ( ! $product ) {
            continue;
        }
        $pid = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
        if ( ! empty( $excluded ) && has_term( $excluded, 'product_cat', $pid ) ) {
            continue;
        }
        $override = get_post_meta( $pid, '_ckc_ref_product_rate', true );
        $rate = ( '' !== $override && is_numeric( $override ) ) ? floatval( $override ) / 100 : $partner_rate;
        if ( $rate <= 0 ) {
            continue;
        }
        $total += floatval( $item->get_total() ) * $rate; // 折扣後小計（不含稅）
    }
    return min( (int) floor( $total ), ckc_refp_cap() );
}

/* ---------------- 現金帳本 ---------------- */
function ckc_refp_get_ledger( $user_id ) {
    $ledger = get_user_meta( intval( $user_id ), '_ckc_ref_cash_ledger', true );
    return is_array( $ledger ) ? $ledger : array();
}
function ckc_refp_save_ledger( $user_id, $ledger ) {
    update_user_meta( intval( $user_id ), '_ckc_ref_cash_ledger', $ledger );
}

// 鑑賞期到期的 pending 轉 confirmed（讀取時惰性轉換並回存）
function ckc_refp_mature_ledger( $user_id ) {
    $ledger  = ckc_refp_get_ledger( $user_id );
    $changed = false;
    $now     = time();
    foreach ( $ledger as $k => $entry ) {
        if ( 'pending' === $entry['status'] && $now >= intval( $entry['available_at'] ) ) {
            $ledger[ $k ]['status'] = 'confirmed';
            $changed = true;
        }
    }
    if ( $changed ) {
        ckc_refp_save_ledger( $user_id, $ledger );
    }
    return $ledger;
}

function ckc_refp_balances( $user_id ) {
    $ledger = ckc_refp_mature_ledger( $user_id );
    $sums = array( 'pending' => 0, 'confirmed' => 0, 'paid' => 0 );
    foreach ( $ledger as $entry ) {
        if ( isset( $sums[ $entry['status'] ] ) ) {
            $sums[ $entry['status'] ] += intval( $entry['amount'] );
        }
    }
    return $sums;
}

/* ---------------- 訂單完成 → 夥伴現金入帳（早於點數軌 priority 20） ---------------- */
add_action( 'woocommerce_order_status_completed', 'ckc_refp_record_commission', 19 );
function ckc_refp_record_commission( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }
    $referrer_id = intval( $order->get_meta( '_ckc_referrer_id' ) );
    if ( $referrer_id <= 0 || $order->get_meta( '_ckc_ref_commission_paid' ) ) {
        return;
    }
    if ( ! ckc_refp_partner_type( $referrer_id ) ) {
        return; // 非夥伴 → 交給點數軌
    }
    $amount = ckc_refp_calc_commission( $order, ckc_refp_partner_rate( $referrer_id ) );
    if ( $amount <= 0 ) {
        $order->update_meta_data( '_ckc_ref_commission_paid', 'cash-0' );
        $order->save();
        return;
    }
    $ledger   = ckc_refp_get_ledger( $referrer_id );
    $ledger[] = array(
        'order_id'     => $order_id,
        'amount'       => $amount,
        'status'       => 'pending',
        'created'      => current_time( 'mysql' ),
        'available_at' => time() + ckc_refp_holding_days() * DAY_IN_SECONDS,
    );
    ckc_refp_save_ledger( $referrer_id, $ledger );
    // 標記訂單已處理（'cash' 非數字，點數軌的發放與反沖都會自動略過）
    $order->update_meta_data( '_ckc_ref_commission_paid', 'cash' );
    $order->update_meta_data( '_ckc_ref_cash_amount', $amount );
    $order->save();
    $order->add_order_note( sprintf( '分潤系統（夥伴現金軌）：入帳 NT$%d 待確認佣金給推薦夥伴（會員 ID %d），%d 天鑑賞期後可出金。', $amount, $referrer_id, ckc_refp_holding_days() ) );
}

/* ---------------- 退款／取消 → 反沖 ---------------- */
add_action( 'woocommerce_order_status_refunded', 'ckc_refp_reverse_commission', 19 );
add_action( 'woocommerce_order_status_cancelled', 'ckc_refp_reverse_commission', 19 );
function ckc_refp_reverse_commission( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order || 'cash' !== $order->get_meta( '_ckc_ref_commission_paid' ) ) {
        return;
    }
    if ( $order->get_meta( '_ckc_ref_commission_reversed' ) ) {
        return;
    }
    $referrer_id = intval( $order->get_meta( '_ckc_referrer_id' ) );
    $ledger  = ckc_refp_get_ledger( $referrer_id );
    $amount  = 0;
    foreach ( $ledger as $k => $entry ) {
        if ( intval( $entry['order_id'] ) === intval( $order_id ) && in_array( $entry['status'], array( 'pending', 'confirmed' ), true ) ) {
            $ledger[ $k ]['status'] = 'reversed';
            $amount = intval( $entry['amount'] );
            break;
        }
    }
    if ( $amount > 0 ) {
        ckc_refp_save_ledger( $referrer_id, $ledger );
        $order->update_meta_data( '_ckc_ref_commission_reversed', $amount );
        $order->save();
        $order->add_order_note( sprintf( '分潤系統（夥伴現金軌）：訂單退款／取消，已反沖佣金 NT$%d（會員 ID %d）。', $amount, $referrer_id ) );
    }
}

/* ---------------- 稅務試算 ---------------- */
function ckc_refp_tax_estimate( $amount ) {
    $amount      = intval( $amount );
    $withholding = $amount >= 20010 ? (int) round( $amount * 0.10 ) : 0;   // 所得稅就源扣繳（居住者）
    $nhi         = $amount >= 20000 ? (int) round( $amount * 0.0211 ) : 0; // 二代健保補充保費
    return array(
        'withholding' => $withholding,
        'nhi'         => $nhi,
        'net'         => $amount - $withholding - $nhi,
    );
}

/* ---------------- 夥伴清單與對帳單 ---------------- */
function ckc_refp_all_partners() {
    return get_users( array(
        'meta_key'     => '_ckc_ref_partner',
        'meta_compare' => 'EXISTS',
        'fields'       => array( 'ID', 'display_name' ),
    ) );
}

function ckc_refp_statement_text() {
    $partners = ckc_refp_all_partners();
    $lines = array();
    $threshold = ckc_refp_payout_threshold();
    foreach ( $partners as $partner ) {
        if ( ! ckc_refp_partner_type( $partner->ID ) ) {
            continue;
        }
        $sums = ckc_refp_balances( $partner->ID );
        if ( 0 === $sums['pending'] && 0 === $sums['confirmed'] && 0 === $sums['paid'] ) {
            continue;
        }
        $tax = ckc_refp_tax_estimate( $sums['confirmed'] );
        $payable = $sums['confirmed'] >= $threshold ? '可出金' : sprintf( '未達出金門檻 NT$%d', $threshold );
        $lines[] = sprintf(
            "- %s（會員 ID %d，%s，費率 %s%%）：待確認 NT$%d｜可出金 NT$%d（%s）｜歷史已出金 NT$%d\n  可出金額試算：扣繳 NT$%d、二代健保 NT$%d、實付 NT$%d",
            $partner->display_name,
            $partner->ID,
            'kol' === ckc_refp_partner_type( $partner->ID ) ? 'KOL' : '團購主',
            rtrim( rtrim( number_format( ckc_refp_partner_rate( $partner->ID ) * 100, 1 ), '0' ), '.' ),
            $sums['pending'],
            $sums['confirmed'],
            $payable,
            $sums['paid'],
            $tax['withholding'],
            $tax['nhi'],
            $tax['net']
        );
    }
    if ( empty( $lines ) ) {
        return '目前沒有任何夥伴帳戶有分潤紀錄。';
    }
    return "夥伴分潤對帳單（截至 " . current_time( 'Y-m-d H:i' ) . "，鑑賞期 " . ckc_refp_holding_days() . " 天）：\n" . implode( "\n", $lines ) . "\n\n備註：稅務金額為試算值（扣繳 10%／二代健保 2.11% 之單次給付門檻判定），實際申報請依會計師指示。";
}

/* ---------------- 標記出金 ---------------- */
function ckc_refp_mark_paid( $user_id ) {
    $user_id = intval( $user_id );
    if ( ! ckc_refp_partner_type( $user_id ) ) {
        return '會員 ID ' . $user_id . ' 不是分潤夥伴，無法出金。';
    }
    $ledger = ckc_refp_mature_ledger( $user_id );
    $total  = 0;
    foreach ( $ledger as $entry ) {
        if ( 'confirmed' === $entry['status'] ) {
            $total += intval( $entry['amount'] );
        }
    }
    if ( $total < ckc_refp_payout_threshold() ) {
        return sprintf( '會員 ID %d 目前可出金 NT$%d，未達出金門檻 NT$%d。', $user_id, $total, ckc_refp_payout_threshold() );
    }
    foreach ( $ledger as $k => $entry ) {
        if ( 'confirmed' === $entry['status'] ) {
            $ledger[ $k ]['status'] = 'paid';
            $ledger[ $k ]['paid_at'] = current_time( 'mysql' );
        }
    }
    ckc_refp_save_ledger( $user_id, $ledger );
    $tax = ckc_refp_tax_estimate( $total );
    $payouts = get_user_meta( $user_id, '_ckc_ref_payouts', true );
    if ( ! is_array( $payouts ) ) {
        $payouts = array();
    }
    $payouts[] = array(
        'amount'      => $total,
        'withholding' => $tax['withholding'],
        'nhi'         => $tax['nhi'],
        'net'         => $tax['net'],
        'time'        => current_time( 'mysql' ),
    );
    update_user_meta( $user_id, '_ckc_ref_payouts', $payouts );
    return sprintf( '已將會員 ID %d 的可出金分潤 NT$%d 標記為已出金（試算：扣繳 NT$%d、二代健保 NT$%d、應實付 NT$%d）。請依試算金額完成匯款與扣繳申報。', $user_id, $total, $tax['withholding'], $tax['nhi'], $tax['net'] );
}

/* ---------------- 前台：夥伴申請＋夥伴儀表板（掛在推薦專區之後） ---------------- */
add_action( 'woocommerce_account_referral_endpoint', 'ckc_refp_account_section', 20 );
function ckc_refp_account_section() {
    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return;
    }

    // 處理申請送出
    if ( isset( $_POST['ckc_refp_apply'] ) && isset( $_POST['ckc_refp_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ckc_refp_nonce'] ) ), 'ckc_refp_apply' ) ) {
        if ( ! ckc_refp_partner_type( $user_id ) && ! get_user_meta( $user_id, '_ckc_ref_partner_apply', true ) ) {
            update_user_meta( $user_id, '_ckc_ref_partner_apply', time() );
        }
    }

    $type = ckc_refp_partner_type( $user_id );
    if ( $type ) {
        $sums = ckc_refp_balances( $user_id );
        $rate_display = rtrim( rtrim( number_format( ckc_refp_partner_rate( $user_id ) * 100, 1 ), '0' ), '.' );
        $qr = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . rawurlencode( ckc_ref_link( $user_id ) );
        ?>
        <div style="background: #fff; border: 2px solid #7f6c60; border-radius: 10px; padding: 20px; margin-top: 20px;">
            <h3 style="margin: 0 0 12px; font-size: 17px; color: #7f6c60;">⭐ 推廣夥伴儀表板（<?php echo 'kol' === $type ? 'KOL' : '團購主'; ?>・現金分潤 <?php echo esc_html( $rate_display ); ?>%）</h3>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; text-align: center;">
                <div style="background: #f8fafc; border-radius: 8px; padding: 14px;">
                    <div style="font-size: 12px; color: #64748b;">待確認（鑑賞期中）</div>
                    <div style="font-size: 22px; font-weight: 700; color: #64748b;">NT$<?php echo esc_html( number_format( $sums['pending'] ) ); ?></div>
                </div>
                <div style="background: #fef2f2; border-radius: 8px; padding: 14px;">
                    <div style="font-size: 12px; color: #b91c1c;">可出金</div>
                    <div style="font-size: 22px; font-weight: 700; color: #b91c1c;">NT$<?php echo esc_html( number_format( $sums['confirmed'] ) ); ?></div>
                </div>
                <div style="background: #f0fdf4; border-radius: 8px; padding: 14px;">
                    <div style="font-size: 12px; color: #16a34a;">累計已出金</div>
                    <div style="font-size: 22px; font-weight: 700; color: #16a34a;">NT$<?php echo esc_html( number_format( $sums['paid'] ) ); ?></div>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 16px; margin-top: 16px; flex-wrap: wrap;">
                <img src="<?php echo esc_url( $qr ); ?>" alt="推薦連結 QR Code" width="120" height="120" style="border: 1px solid #e2e8f0; border-radius: 8px;">
                <p style="flex: 1; min-width: 200px; font-size: 12px; color: #64748b; line-height: 1.8; margin: 0;">
                    左方 QR Code 即您的專屬推薦連結，適合印製於名片、傳單或團購群組使用。分潤於好友訂單完成後 <?php echo esc_html( ckc_refp_holding_days() ); ?> 天轉為可出金；每月結算，出金門檻 NT$<?php echo esc_html( number_format( ckc_refp_payout_threshold() ) ); ?>。單次給付達法定門檻時將依規定代扣所得稅與二代健保補充保費。
                </p>
            </div>
        </div>
        <?php
    } elseif ( get_user_meta( $user_id, '_ckc_ref_partner_apply', true ) ) {
        ?>
        <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; padding: 16px; margin-top: 20px; font-size: 14px; color: #92400e;">
            ⏳ 您的推廣夥伴申請已送出，審核通過後此處將出現現金分潤儀表板。
        </div>
        <?php
    } else {
        ?>
        <div style="background: #fff; border: 1px dashed #cbd5e1; border-radius: 10px; padding: 16px; margin-top: 20px;">
            <p style="margin: 0 0 10px; font-size: 14px; color: #334155;">
                您是 KOL、內容創作者或團購主嗎？申請成為<strong>推廣夥伴</strong>可升級為<strong>現金分潤</strong>（預設 8% 起、月結匯款）。
            </p>
            <form method="post">
                <?php wp_nonce_field( 'ckc_refp_apply', 'ckc_refp_nonce' ); ?>
                <button type="submit" name="ckc_refp_apply" value="1"
                        style="border: none; background: #7f6c60; color: #fff; border-radius: 6px; padding: 10px 24px; font-size: 13px; font-weight: 600; cursor: pointer;">申請成為推廣夥伴</button>
            </form>
        </div>
        <?php
    }
}
