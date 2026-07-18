<?php
/**
 * CKC 紅利點數後台管理系統
 *
 * 子頁：
 *  📊 整體總覽   ─ 全站統計 + Top 10 + 近期異動
 *  👥 會員點數   ─ 列表 + AJAX 單筆調整
 *  📋 異動紀錄   ─ 全站流水帳（含篩選）
 *  🎁 批量發放   ─ 依角色/標籤/上傳名單批量給點
 *  ⚙️ 發放設定   ─ 分潤比例、首購禮、單筆上限
 *
 * 與 WPS Points and Rewards 外掛完全相容
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ============================================================
 * 1. 選單
 * ============================================================ */
add_action( 'admin_menu', 'ckc_pts_register_menu', 62 );
function ckc_pts_register_menu() {
    add_menu_page( '紅利點數管理', '🪙 紅利點數', 'manage_woocommerce',
        'ckc-points-admin', 'ckc_pts_page_overview', 'dashicons-star-filled', 57 );
    add_submenu_page( 'ckc-points-admin', '整體總覽',   '📊 整體總覽',
        'manage_woocommerce', 'ckc-points-admin',    'ckc_pts_page_overview' );
    add_submenu_page( 'ckc-points-admin', '會員點數',   '👥 會員點數',
        'manage_woocommerce', 'ckc-points-members',  'ckc_pts_page_members' );
    add_submenu_page( 'ckc-points-admin', '異動紀錄',   '📋 異動紀錄',
        'manage_woocommerce', 'ckc-points-log',      'ckc_pts_page_log' );
    add_submenu_page( 'ckc-points-admin', '批量發放',   '🎁 批量發放',
        'manage_woocommerce', 'ckc-points-batch',    'ckc_pts_page_batch' );
    add_submenu_page( 'ckc-points-admin', '發放設定',   '⚙️ 發放設定',
        'manage_woocommerce', 'ckc-points-settings', 'ckc_pts_page_settings' );
}

/* ============================================================
 * 2. 共用 CSS / JS
 * ============================================================ */
add_action( 'admin_enqueue_scripts', 'ckc_pts_assets' );
function ckc_pts_assets( $hook ) {
    // 支援 URL エンコードされたユニコード親メニュー名 (例: %f0%9f%aa%99-%e7%b4%85%e5%88%a9%e9%bb%9e%e6%95%b8_page_ckc-points-members)
    if ( $hook !== 'toplevel_page_ckc-points-admin' && strpos( $hook, 'ckc-points' ) === false ) { return; }
    // 正確掛載 inline CSS：先 register 再 add_inline_style
    wp_register_style( 'ckc-pts-css', false, array(), null );
    wp_enqueue_style( 'ckc-pts-css' );
    wp_add_inline_style( 'ckc-pts-css', ckc_pts_css() );
    // 正確掛載 inline JS：先 register 再 add_inline_script
    wp_register_script( 'ckc-pts-js', false, array( 'jquery' ), null, true );
    wp_enqueue_script( 'ckc-pts-js' );
    wp_add_inline_script( 'ckc-pts-js', ckc_pts_js() );
    wp_localize_script( 'ckc-pts-js', 'ckcPts', array(
        'ajax'  => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'ckc_pts_admin' ),
    ) );
}

function ckc_pts_css() { return '
/* ── 共用 ── */
.ckc-pts{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#1e293b;max-width:1400px}
.ckc-pts h1{display:flex;align-items:center;gap:10px;margin-bottom:4px!important}
/* ── 儀表板 Hero 橫幅 ── */
.ckc-dash-hero{background:linear-gradient(135deg,#1e293b 0%,#334155 60%,#475569 100%);border-radius:14px;padding:28px 32px;margin:16px 0 24px;display:flex;align-items:center;gap:24px;color:#fff;box-shadow:0 4px 24px rgba(0,0,0,.18)}
.ckc-dash-hero-icon{font-size:52px;line-height:1;filter:drop-shadow(0 2px 8px rgba(0,0,0,.3))}
.ckc-dash-hero-title{font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#94a3b8}
.ckc-dash-hero-value{font-size:42px;font-weight:900;color:#fff;line-height:1;margin:4px 0}
.ckc-dash-hero-sub{font-size:13px;color:#94a3b8}
.ckc-dash-hero-divider{width:1px;background:rgba(255,255,255,.15);height:70px;margin:0 8px}
/* ── KPI 卡片 ── */
.ckc-kpi-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px}
@media(max-width:1100px){.ckc-kpi-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:700px){.ckc-kpi-grid{grid-template-columns:1fr}}
.ckc-kpi{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px 22px;display:flex;align-items:center;gap:16px;box-shadow:0 1px 4px rgba(0,0,0,.05);transition:box-shadow .2s}
.ckc-kpi:hover{box-shadow:0 4px 16px rgba(0,0,0,.1)}
.ckc-kpi-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0}
.ckc-kpi-icon.blue{background:#dbeafe}.ckc-kpi-icon.green{background:#dcfce7}.ckc-kpi-icon.amber{background:#fef9c3}.ckc-kpi-icon.purple{background:#ede9fe}.ckc-kpi-icon.rose{background:#fee2e2}
.ckc-kpi-label{font-size:12px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.5px}
.ckc-kpi-value{font-size:26px;font-weight:800;color:#0f172a;line-height:1.2;margin:4px 0}
.ckc-kpi-desc{font-size:12px;color:#94a3b8}
/* ── 快捷操作 ── */
.ckc-actions{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:24px}
.ckc-action-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;border:none;cursor:pointer;transition:all .2s}
.ckc-action-btn.primary{background:#1e293b;color:#fff}.ckc-action-btn.primary:hover{background:#0f172a;color:#fff}
.ckc-action-btn.green{background:#15803d;color:#fff}.ckc-action-btn.green:hover{background:#166534;color:#fff}
.ckc-action-btn.outline{background:#fff;color:#1e293b;border:1px solid #e2e8f0}.ckc-action-btn.outline:hover{background:#f8fafc;color:#1e293b}
/* ── 雙欄佈局 ── */
.ckc-dash-row{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px}
@media(max-width:1000px){.ckc-dash-row{grid-template-columns:1fr}}
.ckc-panel{background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.04)}
.ckc-panel-head{padding:14px 18px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between}
.ckc-panel-title{font-size:14px;font-weight:700;color:#1e293b;display:flex;align-items:center;gap:8px}
.ckc-panel-more{font-size:12px;color:#64748b;text-decoration:none}.ckc-panel-more:hover{color:#1e293b}
/* ── Top 10 表格 ── */
.ckc-top-tbl{width:100%;border-collapse:collapse}
.ckc-top-tbl td{padding:10px 16px;border-bottom:1px solid #f8fafc;font-size:13px;vertical-align:middle}
.ckc-top-tbl tr:last-child td{border-bottom:none}
.ckc-top-tbl tr:hover td{background:#fafbfc}
.ckc-medal{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;font-size:11px;font-weight:800}
.ckc-medal-1{background:linear-gradient(135deg,#fde68a,#f59e0b);color:#78350f}
.ckc-medal-2{background:linear-gradient(135deg,#e2e8f0,#94a3b8);color:#334155}
.ckc-medal-3{background:linear-gradient(135deg,#fed7aa,#f97316);color:#7c2d12}
.ckc-medal-n{background:#f1f5f9;color:#94a3b8}
/* ── 異動時間軸 ── */
.ckc-timeline{padding:8px 0}
.ckc-tl-item{display:flex;gap:12px;padding:10px 16px;border-bottom:1px solid #f8fafc;align-items:flex-start}
.ckc-tl-item:last-child{border-bottom:none}
.ckc-tl-dot{width:8px;height:8px;border-radius:50%;margin-top:5px;flex-shrink:0}
.ckc-tl-dot.plus{background:#10b981}.ckc-tl-dot.minus{background:#ef4444}
.ckc-tl-name{font-size:13px;font-weight:600;color:#1e293b}
.ckc-tl-reason{font-size:12px;color:#94a3b8;margin-top:2px}
.ckc-tl-time{font-size:11px;color:#cbd5e1;margin-top:2px}
.ckc-tl-pts{margin-left:auto;flex-shrink:0}
/* ── 設定區塊 ── */
.ckc-g4{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin:20px 0}
.ckc-g3{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin:16px 0}
@media(max-width:700px){.ckc-g4,.ckc-g3{grid-template-columns:1fr}}
.ckc-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px 24px}
.ckc-cl{font-size:11px;color:#64748b;font-weight:700;text-transform:uppercase;letter-spacing:.5px}
.ckc-cv{font-size:28px;font-weight:800;color:#0f172a;margin:6px 0 2px;line-height:1}
.ckc-cv.red{color:#b91c1c}.ckc-cv.green{color:#15803d}
.ckc-cs{font-size:12px;color:#94a3b8}
.ckc-sec{margin:28px 0 8px;font-size:15px;font-weight:700;color:#1e293b;display:flex;align-items:center;gap:8px;border-bottom:2px solid #f1f5f9;padding-bottom:8px}
.ckc-tbl{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden}
.ckc-tbl th{background:#f8fafc;color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;padding:10px 14px;text-align:left;border-bottom:1px solid #e2e8f0;white-space:nowrap}
.ckc-tbl td{padding:10px 14px;border-bottom:1px solid #f1f5f9;font-size:13px;vertical-align:middle}
.ckc-tbl tr:last-child td{border-bottom:none}
.ckc-tbl tr:hover td{background:#fafbfc}
.ckc-b{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700}
.ckc-bplus{background:#dcfce7;color:#15803d}.ckc-bminus{background:#fee2e2;color:#b91c1c}
.ckc-bblue{background:#dbeafe;color:#1d4ed8}
.ckc-sb{display:flex;gap:10px;align-items:center;margin:16px 0;flex-wrap:wrap}
.ckc-sb input,.ckc-sb select{border:1px solid #e2e8f0;border-radius:6px;padding:7px 12px;font-size:13px}
.ckc-adjrow{display:none;background:#f8fafc}
.ckc-adjrow td{padding:14px;border-bottom:1px solid #e2e8f0}
.ckc-adjform{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.ckc-adjform input[type=number]{width:100px;border:1px solid #e2e8f0;border-radius:6px;padding:6px 10px;font-size:13px}
.ckc-adjform input[type=text]{width:230px;border:1px solid #e2e8f0;border-radius:6px;padding:6px 10px;font-size:13px}
.ckc-adjform select{border:1px solid #e2e8f0;border-radius:6px;padding:6px 10px;font-size:13px}
.ckc-ntc{background:#ecfdf5;border-left:4px solid #10b981;color:#065f46;padding:10px 14px;border-radius:0 6px 6px 0;margin:12px 0;font-size:13px;display:none}
.ckc-ntc.err{background:#fef2f2;border-color:#ef4444;color:#7f1d1d}
.ckc-pag{display:flex;gap:6px;align-items:center;margin:16px 0;flex-wrap:wrap}
.ckc-pag a,.ckc-pag span{display:inline-block;padding:5px 10px;border:1px solid #e2e8f0;border-radius:5px;font-size:12px;text-decoration:none;color:#1e293b;background:#fff}
.ckc-pag .cur{background:#1e293b;color:#fff;border-color:#1e293b}
.ckc-empty{text-align:center;padding:40px;color:#94a3b8;font-size:14px}
.ckc-setrow{display:grid;grid-template-columns:260px 1fr;gap:16px;align-items:start;padding:20px 0;border-bottom:1px solid #f1f5f9}
.ckc-setrow:last-child{border-bottom:none}
.ckc-setlbl{font-size:14px;font-weight:600;color:#1e293b}
.ckc-setdesc{font-size:12px;color:#64748b;margin-top:4px;line-height:1.5}
.ckc-setinput input,.ckc-setinput select,.ckc-setinput textarea{border:1px solid #e2e8f0;border-radius:6px;padding:8px 12px;font-size:14px;width:220px}
.ckc-setinput textarea{width:360px;height:120px;resize:vertical;font-size:13px}
.ckc-rank{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;font-size:11px;font-weight:700}
.ckc-rank.g{background:#fef9c3;color:#92400e}.ckc-rank.s{background:#f1f5f9;color:#475569}
.ckc-rank.b{background:#fff7ed;color:#c2410c}.ckc-rank.o{background:#f8fafc;color:#94a3b8}
.ckc-batch-preview{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin:12px 0;display:none}
.ckc-batch-preview table{width:100%;font-size:13px;border-collapse:collapse}
.ckc-batch-preview td,.ckc-batch-preview th{padding:6px 10px;border-bottom:1px solid #e2e8f0}
.ckc-batch-preview tr:last-child td{border-bottom:none}
.ckc-progress-wrap{background:#e2e8f0;border-radius:4px;height:8px;margin:8px 0;overflow:hidden}
.ckc-progress-bar{background:#15803d;height:100%;border-radius:4px;width:0;transition:width .3s}
'; }

function ckc_pts_js() { return '
jQuery(function($){

    /* ── 單筆點數調整 ── */
    $(document).on("click",".ckc-adj-toggle",function(){
        var uid=$(this).data("uid");
        var $r=$("#ckc-adj-"+uid);
        $(".ckc-adjrow").not($r).slideUp(150);
        $r.slideToggle(150);
    });
    $(document).on("click",".ckc-adj-submit",function(e){
        e.preventDefault();
        try {
            var $b=$(this),uid=$b.data("uid");
            var sign=$("#ckc-sign-"+uid).val();
            var amt_val=$("#ckc-amt-"+uid).val();
            var amt=parseInt(amt_val,10);
            var rsn=$("#ckc-rsn-"+uid).val() ? $("#ckc-rsn-"+uid).val().trim() : "";
            var $n=$("#ckc-n-"+uid);
            
            $n.removeClass("err").hide();
            
            if(!amt_val || isNaN(amt) || amt<=0){
                $n.text("請輸入有效點數（正整數）").addClass("err").show();
                return;
            }
            if(!rsn){
                $n.text("請填寫調整原因").addClass("err").show();
                return;
            }
            
            $b.prop("disabled",true).text("處理中…");
            
            $.post(ckcPts.ajax,{
                action:"ckc_adj_pts",
                nonce:ckcPts.nonce,
                user_id:uid,
                sign:sign,
                amount:amt,
                reason:rsn
            },function(r){
                $b.prop("disabled",false).text("確認");
                if(r.success){
                    $n.removeClass("err").text(r.data.msg).show();
                    $("#ckc-bal-"+uid).text(r.data.bal.toLocaleString());
                    setTimeout(function(){
                        $n.fadeOut();
                        $("#ckc-adj-"+uid).slideUp(150);
                        $("#ckc-amt-"+uid).val("");
                        $("#ckc-rsn-"+uid).val("");
                    },2200);
                }else{
                    $n.addClass("err").text(r.data||"操作失敗").show();
                }
            }).fail(function(){
                $b.prop("disabled",false).text("確認");
                $n.addClass("err").text("網路錯誤，請重試").show();
            });
        } catch(err) {
            alert("JS錯誤: " + err.message);
        }
    });

    /* ── 批量發放 ── */
    // 切換目標類型顯示
    $("#ckc-batch-target").on("change",function(){
        var v=$(this).val();
        $(".ckc-batch-target-section").hide();
        if(v){$("#ckc-batch-"+v).show();}
    });

    // 預覽
    $("#ckc-batch-preview-btn").on("click",function(){
        var $btn=$(this);
        var payload=ckc_batch_payload();
        if(!payload){return;}
        $btn.prop("disabled",true).text("預覽中…");
        $.post(ckcPts.ajax,$.extend({action:"ckc_batch_preview",nonce:ckcPts.nonce},payload),function(r){
            $btn.prop("disabled",false).text("📋 預覽名單");
            if(r.success){
                var html="<p style=\'font-weight:700;margin:0 0 8px\'>預計發放給 <strong>"+r.data.count+"</strong> 位會員，每人 <strong>"+payload.amount+"</strong> 點</p>";
                html+="<table><tr><th>ID</th><th>姓名</th><th>Email</th><th>目前餘額</th></tr>";
                $.each(r.data.users,function(i,u){
                    html+="<tr><td>"+u.ID+"</td><td>"+u.name+"</td><td>"+u.email+"</td><td>"+u.pts+" 點</td></tr>";
                });
                html+="</table>";
                $("#ckc-batch-preview-body").html(html);
                $("#ckc-batch-preview-wrap").show();
            }else{alert(r.data||"預覽失敗");}
        }).fail(function(){$btn.prop("disabled",false).text("📋 預覽名單");alert("網路錯誤");});
    });

    // 執行批量發放
    $("#ckc-batch-exec-btn").on("click",function(){
        if(!confirm("確定要批量發放點數？此操作無法復原。")){return;}
        var $btn=$(this);
        var payload=ckc_batch_payload();
        if(!payload){return;}
        $btn.prop("disabled",true).text("發放中…");
        $("#ckc-batch-progress").show();
        var $bar=$("#ckc-batch-bar");
        var $ntc=$("#ckc-batch-ntc");
        $bar.css("width","0%");
        $.post(ckcPts.ajax,$.extend({action:"ckc_batch_exec",nonce:ckcPts.nonce},payload),function(r){
            $bar.css("width","100%");
            $btn.prop("disabled",false).text("🚀 執行發放");
            if(r.success){
                $ntc.removeClass("err").text("✅ 批量發放完成！已成功發放給 "+r.data.count+" 位會員，共 "+r.data.total+" 點。").show();
                setTimeout(function(){$ntc.fadeOut();},6000);
            }else{$ntc.addClass("err").text("❌ "+(r.data||"發放失敗")).show();}
        }).fail(function(){$btn.prop("disabled",false).text("🚀 執行發放");alert("網路錯誤，請重試");});
    });

    // 儲存設定
    $("#ckc-pts-save").on("click",function(){
        var $b=$(this),$n=$("#ckc-set-ntc");
        $b.prop("disabled",true).text("儲存中…");
        $.post(ckcPts.ajax,{action:"ckc_save_pts_settings",nonce:ckcPts.nonce,
            rate:$("#ckc-s-rate").val(),cap:$("#ckc-s-cap").val(),
            bonus:$("#ckc-s-bonus").val(),signup:$("#ckc-s-signup").val(),
            redeem_pts:$("#ckc-s-redeem-pts").val(),redeem_val:$("#ckc-s-redeem-val").val(),
            purchase_enabled:$("#ckc-s-purchase-enabled").val(),
            purchase_val:$("#ckc-s-purchase-val").val(),
            purchase_pts:$("#ckc-s-purchase-pts").val()},function(r){
            $b.prop("disabled",false).text("儲存設定");
            $n.removeClass("err").show();
            if(r.success){$n.text("✅ "+(r.data||"設定已儲存"));}
            else{$n.addClass("err").text("❌ "+(r.data||"儲存失敗"));}
            setTimeout(function(){$n.fadeOut();},3000);
        });
    });


    // 即時篩選
    $("#ckc-ms").on("keyup",function(){
        var q=$(this).val().toLowerCase();
        $(".ckc-mrow").each(function(){
            $(this).toggle(($(this).data("n")+""+$(this).data("e")).toLowerCase().indexOf(q)>-1);
        });
    });

    function ckc_batch_payload(){
        var target=$("#ckc-batch-target").val();
        var sign=$("#ckc-batch-sign").val();
        var amount=parseInt($("#ckc-batch-amount").val(),10);
        var reason=$("#ckc-batch-reason").val().trim();
        if(!target){alert("請選擇發放對象");return null;}
        if(!amount||amount<=0){alert("請輸入有效點數數量");return null;}
        if(!reason){alert("請填寫發放原因");return null;}
        var extra={};
        if(target==="role")  extra.role=$("#ckc-batch-role").val();
        if(target==="tag")   extra.tag=$("#ckc-batch-tag").val();
        if(target==="list")  extra.list=$("#ckc-batch-list").val();
        if(target==="order") extra.order_days=$("#ckc-batch-order-days").val();
        return $.extend({target:target,sign:sign,amount:amount,reason:reason},extra);
    }
});
'; }

/* ============================================================
 * 3. AJAX — 單筆點數調整
 * ============================================================ */
add_action( 'wp_ajax_ckc_adj_pts', 'ckc_ajax_adj_pts' );
function ckc_ajax_adj_pts() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_send_json_error( '權限不足' ); }
    if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'ckc_pts_admin' ) ) { wp_send_json_error( '安全驗證失敗' ); }
    $uid    = intval( $_POST['user_id'] ?? 0 );
    $sign   = in_array( $_POST['sign'] ?? '', array('+','-'), true ) ? $_POST['sign'] : '+';
    $amount = intval( $_POST['amount'] ?? 0 );
    $reason = sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) );
    if ( $uid <= 0 || $amount <= 0 || ! get_user_by( 'id', $uid ) ) { wp_send_json_error( '參數錯誤' ); }
    $delta  = '+' === $sign ? $amount : -$amount;
    $new_bal = ckc_pts_update( $uid, $delta, $reason . '（後台手動調整）' );
    wp_send_json_success( array( 'msg' => sprintf( '%s %d 點完成！新餘額：%d 點', '+' === $sign ? '＋增加' : '－扣除', $amount, $new_bal ), 'bal' => $new_bal ) );
}

/* ============================================================
 * 4. AJAX — 批量發放（預覽 + 執行）
 * ============================================================ */
add_action( 'wp_ajax_ckc_batch_preview', 'ckc_ajax_batch_preview' );
function ckc_ajax_batch_preview() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_send_json_error( '權限不足' ); }
    if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'ckc_pts_admin' ) ) { wp_send_json_error( '安全驗證失敗' ); }
    $users = ckc_pts_batch_resolve_users( $_POST );
    $preview = array();
    foreach ( array_slice( $users, 0, 50 ) as $u ) {
        $preview[] = array(
            'ID'    => $u->ID,
            'name'  => esc_html( $u->display_name ),
            'email' => esc_html( $u->user_email ),
            'pts'   => (int) get_user_meta( $u->ID, 'wps_wpr_points', true ),
        );
    }
    wp_send_json_success( array( 'count' => count( $users ), 'users' => $preview ) );
}

add_action( 'wp_ajax_ckc_batch_exec', 'ckc_ajax_batch_exec' );
function ckc_ajax_batch_exec() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_send_json_error( '權限不足' ); }
    if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'ckc_pts_admin' ) ) { wp_send_json_error( '安全驗證失敗' ); }
    $sign   = in_array( $_POST['sign'] ?? '', array('+','-'), true ) ? $_POST['sign'] : '+';
    $amount = intval( $_POST['amount'] ?? 0 );
    $reason = sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) ) . '（批量發放）';
    if ( $amount <= 0 ) { wp_send_json_error( '點數數量錯誤' ); }
    $delta  = '+' === $sign ? $amount : -$amount;
    $users  = ckc_pts_batch_resolve_users( $_POST );
    $count  = 0;
    foreach ( $users as $u ) {
        ckc_pts_update( $u->ID, $delta, $reason );
        $count++;
    }
    wp_send_json_success( array( 'count' => $count, 'total' => $count * abs( $delta ) ) );
}

/* 解析批量發放的目標使用者清單 */
function ckc_pts_batch_resolve_users( $post ) {
    $target = sanitize_text_field( $post['target'] ?? '' );
    $args = array( 'fields' => 'all', 'number' => 2000 );
    switch ( $target ) {
        case 'role':
            $role = sanitize_text_field( $post['role'] ?? 'customer' );
            $args['role'] = $role;
            break;
        case 'tag':
            $tag = sanitize_text_field( $post['tag'] ?? '' );
            if ( ! $tag ) { return array(); }
            // 使用自訂 customer tag 搜尋（WooCommerce 客戶標籤）
            $args['meta_query'] = array( array(
                'key'     => '_ckc_customer_tag',
                'value'   => $tag,
                'compare' => 'LIKE',
            ) );
            break;
        case 'list':
            // 手動輸入 Email 名單（逗號或換行分隔）
            $raw   = sanitize_textarea_field( $post['list'] ?? '' );
            $emails = preg_split( '/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY );
            $users = array();
            foreach ( $emails as $email ) {
                $u = get_user_by( 'email', sanitize_email( $email ) );
                if ( $u ) { $users[] = $u; }
            }
            return $users;
        case 'order':
            // 近 N 天內有完成訂單的顧客
            $days   = max( 1, intval( $post['order_days'] ?? 30 ) );
            $orders = wc_get_orders( array(
                'limit'        => -1,
                'status'       => 'completed',
                'date_created' => '>' . ( time() - $days * DAY_IN_SECONDS ),
                'return'       => 'ids',
            ) );
            $uid_set = array();
            foreach ( $orders as $oid ) {
                $o = wc_get_order( $oid );
                if ( $o && $o->get_customer_id() ) {
                    $uid_set[ $o->get_customer_id() ] = true;
                }
            }
            $users = array();
            foreach ( array_keys( $uid_set ) as $uid ) {
                $u = get_user_by( 'id', $uid );
                if ( $u ) { $users[] = $u; }
            }
            return $users;
        case 'all_with_points':
            // 所有持有點數的會員
            $args['meta_query'] = array( array(
                'key'     => 'wps_wpr_points',
                'value'   => 0,
                'compare' => '>',
                'type'    => 'NUMERIC',
            ) );
            break;
        default:
            return array();
    }
    $q = new WP_User_Query( $args );
    return $q->get_results();
}

/* ============================================================
 * 5. AJAX — 儲存設定
 * ============================================================ */
add_action( 'wp_ajax_ckc_save_pts_settings', 'ckc_ajax_save_pts_settings' );
function ckc_ajax_save_pts_settings() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_send_json_error( '權限不足' ); }
    if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'ckc_pts_admin' ) ) { wp_send_json_error( '安全驗證失敗' ); }

    $rate        = floatval( $_POST['rate']        ?? 5 ) / 100;
    $cap         = intval(   $_POST['cap']         ?? 500 );
    $bonus       = intval(   $_POST['bonus']       ?? 50 );
    $signup      = intval(   $_POST['signup']      ?? 0 );
    $redeem_pts  = max( 1, intval( $_POST['redeem_pts']  ?? 1 ) ); // N 點
    $redeem_val  = max( 1, intval( $_POST['redeem_val']  ?? 1 ) ); // 折抵 NT$M

    $purchase_enabled = sanitize_text_field( $_POST['purchase_enabled'] ?? 'no' );
    $purchase_pts     = max( 1, intval( $_POST['purchase_pts'] ?? 1 ) );
    $purchase_val     = max( 1, intval( $_POST['purchase_val'] ?? 100 ) );

    if ( $rate <= 0 || $rate > 1 ) { wp_send_json_error( '分潤比例請輸入 0.1 ~ 100 之間的數字' ); }

    // 我們自己的 options
    update_option( '_ckc_ref_commission_rate', $rate );
    update_option( '_ckc_ref_commission_cap',  $cap );
    update_option( '_ckc_ref_referred_bonus',  $bonus );
    update_option( '_ckc_ref_signup_bonus',    $signup );
    update_option( '_ckc_redeem_pts',          $redeem_pts );
    update_option( '_ckc_redeem_val',          $redeem_val );
    update_option( '_ckc_purchase_bonus_enabled', $purchase_enabled );
    update_option( '_ckc_purchase_bonus_pts',     $purchase_pts );
    update_option( '_ckc_purchase_bonus_val',     $purchase_val );

    // 同步回 WPS 外掛 options（前台結帳折抵使用）
    $wps = get_option( 'wps_wpr_settings_gallery', array() );
    $wps['wps_wpr_cart_points_rate'] = $redeem_pts;
    $wps['wps_wpr_cart_price_rate']  = $redeem_val;
    update_option( 'wps_wpr_settings_gallery', $wps );

    // 同步回 WPS 兌換設定（我的帳號點數頁使用）
    $conv = get_option( 'wps_wpr_redeeming_conversion_settings', array() );
    $conv['wps_wpr_redeem_pts'] = $redeem_pts;
    $conv['wps_wpr_redeem_val'] = $redeem_val;
    update_option( 'wps_wpr_redeeming_conversion_settings', $conv );

    wp_send_json_success( '設定已儲存，兌換比例已同步至前台！' );
}

/* ============================================================
 * 6. 輔助函式
 * ============================================================ */

/**
 * 更新會員點數，同時寫入 WPS 相容格式 + _ckc_ref_log
 * @return int 新餘額
 */
function ckc_pts_update( $user_id, $delta, $reason ) {
    $balance = ckc_pts_get_user_balance( $user_id );
    $new_bal = max( 0, $balance + $delta );
    update_user_meta( $user_id, 'wps_wpr_points', $new_bal );
    
    // 自點數累積首月起算有效期限
    $start_month = get_user_meta( $user_id, '_ckc_points_start_month', true );
    if ( ! $start_month && $new_bal > 0 ) {
        update_user_meta( $user_id, '_ckc_points_start_month', current_time( 'Y-m' ) );
    }
    
    clean_user_cache( $user_id );
    // WPS points_details 相容格式
    $details = get_user_meta( $user_id, 'points_details', true );
    if ( ! is_array( $details ) ) { $details = array(); }
    if ( ! isset( $details['admin_points'] ) || ! is_array( $details['admin_points'] ) ) {
        $details['admin_points'] = array();
    }
    $details['admin_points'][] = array(
        'admin_points' => abs( $delta ),
        'date'         => date_i18n( 'Y-m-d h:i:sa' ),
        'sign'         => $delta >= 0 ? '+' : '-',
        'reason'       => $reason,
    );
    update_user_meta( $user_id, 'points_details', $details );
    // 自訂紀錄
    $log = get_user_meta( $user_id, '_ckc_ref_log', true );
    if ( ! is_array( $log ) ) { $log = array(); }
    $log[] = array( 'points' => $delta, 'reason' => $reason, 'time' => current_time( 'mysql' ) );
    update_user_meta( $user_id, '_ckc_ref_log', $log );
    return $new_bal;
}

function ckc_pts_site_stats() {
    global $wpdb;
    return array(
        'total'   => (int) $wpdb->get_var( "SELECT SUM(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->usermeta} WHERE meta_key='wps_wpr_points' AND meta_value>0" ),
        'members' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key='wps_wpr_points' AND meta_value>0" ),
        'month'   => (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->postmeta} WHERE meta_key='_ckc_ref_commission_paid' AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type='shop_order' AND post_date>=%s)",
            date('Y-m-01 00:00:00') ) ),
    );
}

function ckc_pts_top10() {
    global $wpdb;
    return $wpdb->get_results( "SELECT u.ID,u.display_name,u.user_email,CAST(um.meta_value AS UNSIGNED) AS pts FROM {$wpdb->users} u JOIN {$wpdb->usermeta} um ON u.ID=um.user_id AND um.meta_key='wps_wpr_points' WHERE CAST(um.meta_value AS UNSIGNED)>0 ORDER BY pts DESC LIMIT 10" );
}

function ckc_pts_recent_log( $limit = 60 ) {
    global $wpdb;
    $raw = $wpdb->get_results( "SELECT user_id,meta_value FROM {$wpdb->usermeta} WHERE meta_key='_ckc_ref_log' LIMIT 400" );
    $all = array();
    foreach ( $raw as $row ) {
        $log = maybe_unserialize( $row->meta_value );
        if ( ! is_array( $log ) ) { continue; }
        foreach ( $log as $e ) {
            if ( empty( $e['time'] ) ) { continue; }
            $e['user_id'] = $row->user_id;
            $all[] = $e;
        }
    }
    usort( $all, fn( $a, $b ) => strcmp( $b['time'], $a['time'] ) );
    return array_slice( $all, 0, $limit );
}

/* ============================================================
 * 7. 📊 整體總覽
 * ============================================================ */
function ckc_pts_page_overview() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( '權限不足' ); }
    $s      = ckc_pts_site_stats();
    $top    = ckc_pts_top10();
    $logs   = ckc_pts_recent_log( 15 );
    $rate   = round( (float) get_option( '_ckc_ref_commission_rate', 0.05 ) * 100, 1 );
    $cap    = (int) get_option( '_ckc_ref_commission_cap', 500 );
    $bon    = (int) get_option( '_ckc_ref_referred_bonus', 50 );
    $signup = (int) get_option( '_ckc_ref_signup_bonus', 0 );
    $rpts   = (int) get_option( '_ckc_redeem_pts', 1 );
    $rval   = (int) get_option( '_ckc_redeem_val', 1 );
    $now    = date_i18n( 'Y/m/d H:i' );
    ?>
    <div class="wrap ckc-pts">
    <h1>🪙 紅利點數 儀表板</h1>
    <p style="color:#94a3b8;font-size:13px;margin-top:0;margin-bottom:16px">最後更新：<?php echo $now; ?></p>

    <!-- Hero 橫幅：全站總持有點數 -->
    <div class="ckc-dash-hero">
        <div class="ckc-dash-hero-icon">🪙</div>
        <div>
            <div class="ckc-dash-hero-title">全站紅利點數總餘額</div>
            <div class="ckc-dash-hero-value"><?php echo number_format( $s['total'] ); ?></div>
            <div class="ckc-dash-hero-sub">所有會員持有點數合計 ／ 折抵價值 NT$<?php echo number_format( floor( $s['total'] * $rval / max(1,$rpts) ) ); ?></div>
        </div>
        <div class="ckc-dash-hero-divider"></div>
        <div style="text-align:center">
            <div class="ckc-dash-hero-title">持有點數會員</div>
            <div class="ckc-dash-hero-value" style="font-size:28px"><?php echo number_format( $s['members'] ); ?></div>
            <div class="ckc-dash-hero-sub">帳戶餘額 &gt; 0</div>
        </div>
        <div class="ckc-dash-hero-divider"></div>
        <div style="text-align:center">
            <div class="ckc-dash-hero-title">本月分潤發放</div>
            <div class="ckc-dash-hero-value" style="font-size:28px;color:#4ade80"><?php echo number_format( $s['month'] ); ?></div>
            <div class="ckc-dash-hero-sub"><?php echo date_i18n('Y 年 m 月'); ?></div>
        </div>
        <div style="margin-left:auto">
            <a href="<?php echo admin_url('admin.php?page=ckc-points-settings'); ?>" class="ckc-action-btn outline" style="background:rgba(255,255,255,.08);border-color:rgba(255,255,255,.2);color:#fff;text-decoration:none">⚙️ 設定</a>
        </div>
    </div>

    <!-- KPI 卡片 -->
    <div class="ckc-kpi-grid">
        <div class="ckc-kpi">
            <div class="ckc-kpi-icon rose">📈</div>
            <div>
                <div class="ckc-kpi-label">分潤比例</div>
                <div class="ckc-kpi-value"><?php echo $rate; ?>%</div>
                <div class="ckc-kpi-desc">單筆上限 <?php echo number_format($cap); ?> 點</div>
            </div>
        </div>
        <div class="ckc-kpi">
            <div class="ckc-kpi-icon green">🎁</div>
            <div>
                <div class="ckc-kpi-label">首購禮</div>
                <div class="ckc-kpi-value"><?php echo number_format($bon); ?> <span style="font-size:14px;font-weight:600;color:#64748b">點</span></div>
                <div class="ckc-kpi-desc">被推薦人首次下單加贈</div>
            </div>
        </div>
        <div class="ckc-kpi">
            <div class="ckc-kpi-icon amber">🎊</div>
            <div>
                <div class="ckc-kpi-label">新會員禮</div>
                <div class="ckc-kpi-value"><?php echo $signup > 0 ? number_format($signup) : '─'; ?> <?php echo $signup > 0 ? '<span style="font-size:14px;font-weight:600;color:#64748b">點</span>' : ''; ?></div>
                <div class="ckc-kpi-desc"><?php echo $signup > 0 ? '完成註冊自動發放' : '目前停用（設為 0）'; ?></div>
            </div>
        </div>
        <div class="ckc-kpi">
            <div class="ckc-kpi-icon blue">💱</div>
            <div>
                <div class="ckc-kpi-label">折抵比例</div>
                <div class="ckc-kpi-value"><?php echo $rpts; ?> <span style="font-size:14px;font-weight:600;color:#64748b">點</span></div>
                <div class="ckc-kpi-desc">= NT$<?php echo $rval; ?>（每點 NT$<?php echo round($rval/max(1,$rpts),2); ?>）</div>
            </div>
        </div>
        <div class="ckc-kpi">
            <div class="ckc-kpi-icon purple">👥</div>
            <div>
                <div class="ckc-kpi-label">總會員人數</div>
                <?php $all_users = (int)(new WP_User_Query(['count_total'=>true,'number'=>1]))->get_total(); ?>
                <div class="ckc-kpi-value"><?php echo number_format($all_users); ?></div>
                <div class="ckc-kpi-desc">全站已註冊帳號</div>
            </div>
        </div>
        <div class="ckc-kpi">
            <div class="ckc-kpi-icon green">💰</div>
            <div>
                <div class="ckc-kpi-label">點數滲透率</div>
                <div class="ckc-kpi-value"><?php echo $all_users > 0 ? round($s['members']/$all_users*100,1) : 0; ?>%</div>
                <div class="ckc-kpi-desc">持有點數 / 全部會員</div>
            </div>
        </div>
    </div>

    <!-- 快捷操作 -->
    <div class="ckc-actions">
        <a href="<?php echo admin_url('admin.php?page=ckc-points-members'); ?>" class="ckc-action-btn primary">👥 查看會員點數</a>
        <a href="<?php echo admin_url('admin.php?page=ckc-points-batch'); ?>" class="ckc-action-btn green">🎁 批量發放</a>
        <a href="<?php echo admin_url('admin.php?page=ckc-points-log'); ?>" class="ckc-action-btn outline">📋 完整異動紀錄</a>
        <a href="<?php echo admin_url('admin.php?page=ckc-points-settings'); ?>" class="ckc-action-btn outline">⚙️ 發放設定</a>
    </div>

    <!-- 雙欄：Top 10 + 近期異動 -->
    <div class="ckc-dash-row">

        <!-- Top 10 -->
        <div class="ckc-panel">
            <div class="ckc-panel-head">
                <div class="ckc-panel-title">🏆 點數排行 Top 10</div>
                <a href="<?php echo admin_url('admin.php?page=ckc-points-members'); ?>" class="ckc-panel-more">查看全部 →</a>
            </div>
            <table class="ckc-top-tbl">
                <?php if ( empty($top) ): ?>
                <tr><td colspan="3" class="ckc-empty">尚無持有點數的會員</td></tr>
                <?php else: ?>
                <?php foreach ( $top as $i => $r ):
                    $rk = $i + 1;
                    $mc = $rk === 1 ? 'ckc-medal-1' : ($rk === 2 ? 'ckc-medal-2' : ($rk === 3 ? 'ckc-medal-3' : 'ckc-medal-n'));
                    $bar_w = $top[0]->pts > 0 ? round( $r->pts / $top[0]->pts * 100 ) : 0;
                ?>
                <tr>
                    <td style="width:32px"><span class="ckc-medal <?php echo $mc; ?>"><?php echo $rk; ?></span></td>
                    <td>
                        <div style="font-size:13px;font-weight:600"><a href="<?php echo esc_url(get_edit_user_link($r->ID)); ?>" style="color:#1e293b;text-decoration:none"><?php echo esc_html($r->display_name); ?></a></div>
                        <div style="font-size:11px;color:#94a3b8;margin-top:2px"><?php echo esc_html($r->user_email); ?></div>
                        <div style="height:4px;background:#f1f5f9;border-radius:2px;margin-top:5px;overflow:hidden"><div style="height:100%;width:<?php echo $bar_w; ?>%;background:<?php echo $rk===1?'#f59e0b':($rk<=3?'#f97316':'#cbd5e1'); ?>;border-radius:2px"></div></div>
                    </td>
                    <td style="text-align:right;white-space:nowrap">
                        <div style="font-weight:800;color:#b91c1c;font-size:14px"><?php echo number_format($r->pts); ?></div>
                        <div style="font-size:11px;color:#94a3b8">點</div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </table>
        </div>

        <!-- 近期異動 -->
        <div class="ckc-panel">
            <div class="ckc-panel-head">
                <div class="ckc-panel-title">🕒 近期異動</div>
                <a href="<?php echo admin_url('admin.php?page=ckc-points-log'); ?>" class="ckc-panel-more">完整紀錄 →</a>
            </div>
            <div class="ckc-timeline">
                <?php if ( empty($logs) ): ?>
                <div class="ckc-empty">暫無異動紀錄</div>
                <?php else: ?>
                <?php foreach ( $logs as $e ):
                    $pts = intval( $e['points'] ?? 0 );
                    $u   = get_userdata( $e['user_id'] );
                    $is_plus = $pts >= 0;
                ?>
                <div class="ckc-tl-item">
                    <div class="ckc-tl-dot <?php echo $is_plus ? 'plus' : 'minus'; ?>"></div>
                    <div style="flex:1;min-width:0">
                        <div class="ckc-tl-name"><?php echo $u ? esc_html($u->display_name) : 'ID ' . $e['user_id']; ?></div>
                        <div class="ckc-tl-reason"><?php echo esc_html( $e['reason'] ?? '─' ); ?></div>
                        <div class="ckc-tl-time"><?php echo esc_html( substr($e['time'] ?? '', 0, 16) ); ?></div>
                    </div>
                    <div class="ckc-tl-pts">
                        <span class="ckc-b <?php echo $is_plus ? 'ckc-bplus' : 'ckc-bminus'; ?>">
                            <?php echo $is_plus ? '+' : ''; echo number_format($pts); ?> 點
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /.ckc-dash-row -->
    </div><!-- /.wrap -->
    <?php
}



/* ============================================================
 * 8. 👥 會員點數列表
 * ============================================================ */
function ckc_pts_page_members() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( '權限不足' ); }
    $paged    = max( 1, intval( $_GET['paged'] ?? 1 ) );
    $per_page = 30;
    $search   = sanitize_text_field( $_GET['s'] ?? '' );
    $sort     = in_array( $_GET['sort'] ?? '', array( 'points', 'date', 'name' ), true ) ? $_GET['sort'] : 'points';
    $base     = admin_url( 'admin.php?page=ckc-points-members' );

    $args = array(
        'number'  => $per_page, 'paged' => $paged,
        'orderby' => 'meta_value_num', 'order' => 'DESC', 'meta_key' => 'wps_wpr_points',
        'meta_query' => array( 'relation' => 'OR',
            array( 'key' => 'wps_wpr_points', 'compare' => 'EXISTS' ),
            array( 'key' => 'wps_wpr_points', 'compare' => 'NOT EXISTS' ),
        ),
    );
    if ( $sort === 'date' )      { $args['orderby'] = 'registered'; $args['order'] = 'DESC'; unset($args['meta_key']); }
    elseif ( $sort === 'name' )  { $args['orderby'] = 'display_name'; $args['order'] = 'ASC'; unset($args['meta_key']); }
    if ( $search ) { $args['search'] = '*'.$search.'*'; $args['search_columns'] = array('user_login','user_email','display_name'); }

    $q = new WP_User_Query( $args );
    $users = $q->get_results(); $total = $q->get_total(); $pages = ceil($total/$per_page);

    $all_cnt  = (new WP_User_Query( array('count_total'=>true,'number'=>1) ))->get_total();
    $pts_cnt  = (new WP_User_Query( array('count_total'=>true,'number'=>1,'meta_query'=>array(array('key'=>'wps_wpr_points','value'=>0,'compare'=>'>','type'=>'NUMERIC'))) ))->get_total();
    $zero_cnt = $all_cnt - $pts_cnt;
    ?>
    <div class="wrap ckc-pts">
    <h1>👥 會員點數列表</h1>

    <!-- 緊湊統計列 -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:14px;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:10px 16px;box-shadow:0 1px 3px rgba(0,0,0,.04)">
        <span style="font-size:12px;color:#94a3b8;font-weight:600">全部</span>
        <span style="font-size:18px;font-weight:800;color:#0f172a"><?php echo number_format($all_cnt); ?></span>
        <span style="color:#e2e8f0;margin:0 4px">│</span>
        <span style="font-size:12px;color:#94a3b8;font-weight:600">🪙 持有</span>
        <span style="font-size:18px;font-weight:800;color:#b91c1c"><?php echo number_format($pts_cnt); ?></span>
        <span style="color:#e2e8f0;margin:0 4px">│</span>
        <span style="font-size:12px;color:#94a3b8;font-weight:600">⭕ 零點</span>
        <span style="font-size:18px;font-weight:800;color:#64748b"><?php echo number_format($zero_cnt); ?></span>
        <span style="color:#e2e8f0;margin:0 4px">│</span>
        <span style="font-size:12px;color:#94a3b8;font-weight:600">📊 滲透率</span>
        <span style="font-size:18px;font-weight:800;color:#15803d"><?php echo $all_cnt>0?round($pts_cnt/$all_cnt*100,1):0; ?>%</span>
        <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
            <a href="<?php echo admin_url('admin.php?page=ckc-points-batch'); ?>" class="ckc-action-btn green" style="padding:5px 12px;font-size:12px">🎁 批量發放</a>
        </div>
    </div>

    <!-- 搜尋 + 排序 -->
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:10px">
        <form method="get" style="display:flex;gap:6px;align-items:center">
            <input type="hidden" name="page" value="ckc-points-members">
            <input type="hidden" name="sort" value="<?php echo esc_attr($sort); ?>">
            <input type="text" name="s" placeholder="🔍 搜尋姓名 / Email…" value="<?php echo esc_attr($search); ?>" style="width:210px;border:1px solid #e2e8f0;border-radius:6px;padding:5px 10px;font-size:13px">
            <button type="submit" class="button" style="padding:4px 10px;font-size:13px">搜尋</button>
            <?php if($search): ?><a href="<?php echo esc_url(add_query_arg('sort',$sort,$base)); ?>" class="button" style="padding:4px 8px;font-size:12px">✕</a><?php endif; ?>
        </form>
        <span style="font-size:12px;color:#94a3b8"><?php echo number_format($total); ?> 位</span>
        <div style="margin-left:auto;display:flex;gap:4px;align-items:center">
            <span style="font-size:11px;color:#94a3b8">排序：</span>
            <?php foreach ( array('points'=>'點數','date'=>'加入日','name'=>'姓名') as $sk=>$sl ): ?>
            <a href="<?php echo esc_url(add_query_arg(array('sort'=>$sk,'s'=>$search,'paged'=>1),$base)); ?>"
               style="padding:3px 8px;border-radius:4px;font-size:11px;text-decoration:none;border:1px solid <?php echo $sort===$sk?'#1e293b':'#e2e8f0'; ?>;background:<?php echo $sort===$sk?'#1e293b':'#fff'; ?>;color:<?php echo $sort===$sk?'#fff':'#64748b'; ?>"><?php echo $sl; ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 精簡條列表格 -->
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.04)">
    <table style="width:100%;border-collapse:collapse">
        <thead>
        <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0">
            <th style="padding:8px 14px;text-align:left;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;width:22%">姓名</th>
            <th style="padding:8px 14px;text-align:left;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px">Email</th>
            <th style="padding:8px 14px;text-align:center;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;width:100px">點數</th>
            <th style="padding:8px 14px;text-align:center;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;width:90px">累計</th>
            <th style="padding:8px 14px;text-align:center;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;width:80px">加入</th>
            <th style="padding:8px 14px;width:60px"></th>
        </tr>
        </thead>
        <tbody>
        <?php if ( empty($users) ): ?>
        <tr><td colspan="6" class="ckc-empty"><?php echo $search?'查無「'.esc_html($search).'」':'尚無會員'; ?></td></tr>
        <?php else: ?>
        <?php foreach ( $users as $u ):
            $uid  = $u->ID;
            $bal  = (int) get_user_meta($uid,'wps_wpr_points',true);
            $log  = get_user_meta($uid,'_ckc_ref_log',true);
            $earned = 0;
            if(is_array($log)){foreach($log as $e){if(isset($e['points'])&&intval($e['points'])>0)$earned+=intval($e['points']);}}
            $hp = $bal > 0;
            $email_short = strlen($u->user_email) > 32 ? substr($u->user_email,0,30).'…' : $u->user_email;
        ?>
        <tr style="border-bottom:1px solid #f1f5f9;transition:background .12s" onmouseover="this.style.background='#fafbfc'" onmouseout="this.style.background=''">
            <td style="padding:7px 14px">
                <a href="<?php echo esc_url(get_edit_user_link($uid)); ?>" style="font-weight:600;font-size:13px;color:#1e293b;text-decoration:none"><?php echo esc_html($u->display_name); ?></a>
                <span style="font-size:11px;color:#cbd5e1;margin-left:5px">#<?php echo $uid; ?></span>
            </td>
            <td style="padding:7px 14px;font-size:12px;color:#64748b" title="<?php echo esc_attr($u->user_email); ?>"><?php echo esc_html($email_short); ?></td>
            <td style="padding:7px 14px;text-align:center">
                <span id="ckc-bal-<?php echo $uid; ?>" style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:12px;font-weight:700;<?php echo $hp?'background:#fee2e2;color:#b91c1c':'background:#f1f5f9;color:#94a3b8'; ?>"><?php echo number_format($bal); ?></span>
                <?php 
                $start_month = get_user_meta($uid, '_ckc_points_start_month', true);
                if ( $hp && $start_month ) : 
                    $start_time = strtotime( $start_month . '-01 00:00:00' );
                    $expire_time = strtotime( '+2 years -1 day', $start_time );
                ?>
                    <div style="font-size:9px;color:#94a3b8;margin-top:2px;line-height:1;white-space:nowrap" title="起算首月: <?php echo $start_month; ?>">⌛ <?php echo date_i18n('Y/m/d', $expire_time); ?></div>
                <?php endif; ?>
            </td>
            <td style="padding:7px 14px;text-align:center;font-size:12px;color:<?php echo $earned>0?'#15803d':'#cbd5e1'; ?>;font-weight:600">
                <?php echo $earned>0?'+'.number_format($earned):'─'; ?>
            </td>
            <td style="padding:7px 14px;text-align:center;font-size:11px;color:#cbd5e1;white-space:nowrap"><?php echo date_i18n('m/d', strtotime($u->user_registered)); ?></td>
            <td style="padding:7px 10px;text-align:center">
                <button class="ckc-adj-toggle" data-uid="<?php echo $uid; ?>"
                    style="background:none;border:1px solid #e2e8f0;border-radius:5px;padding:3px 8px;font-size:11px;cursor:pointer;color:#64748b;transition:all .15s"
                    onmouseover="this.style.borderColor='#1e293b';this.style.color='#1e293b'"
                    onmouseout="this.style.borderColor='#e2e8f0';this.style.color='#64748b'">✏️</button>
            </td>
        </tr>
        <!-- 調整列（摺疊） -->
        <tr class="ckc-adjrow" id="ckc-adj-<?php echo $uid; ?>" style="background:#f8fafc;border-bottom:1px solid #f1f5f9">
            <td colspan="6" style="padding:8px 14px">
                <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                    <select id="ckc-sign-<?php echo $uid; ?>" style="border:1px solid #e2e8f0;border-radius:5px;padding:4px 8px;font-size:12px;color:#1e293b;background:#fff">
                        <option value="+">＋ 增加</option><option value="-">－ 扣除</option>
                    </select>
                    <input type="number" id="ckc-amt-<?php echo $uid; ?>" placeholder="點數" min="1"
                        style="width:80px;border:1px solid #e2e8f0;border-radius:5px;padding:4px 8px;font-size:12px">
                    <input type="text" id="ckc-rsn-<?php echo $uid; ?>" placeholder="調整原因（必填）"
                        style="flex:1;min-width:140px;border:1px solid #e2e8f0;border-radius:5px;padding:4px 8px;font-size:12px">
                    <button type="button" class="button button-primary button-small ckc-adj-submit" data-uid="<?php echo $uid; ?>" style="font-size:12px;padding:4px 12px">確認</button>
                    <span style="font-size:11px;color:#94a3b8">現有 <strong><?php echo number_format($bal); ?></strong> 點</span>
                </div>
                <div id="ckc-n-<?php echo $uid; ?>" class="ckc-ntc" style="margin-top:6px;font-size:12px;padding:6px 10px"></div>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    </div>

    <!-- 分頁 -->
    <?php if($pages>1): ?>
    <div class="ckc-pag" style="margin-top:12px">
        <?php if($paged>1): ?><a href="<?php echo esc_url(add_query_arg(array('paged'=>$paged-1,'s'=>$search,'sort'=>$sort),$base)); ?>">‹ 上頁</a><?php endif; ?>
        <?php for($p=max(1,$paged-3);$p<=min($pages,$paged+3);$p++): ?>
            <?php if($p===$paged): ?><span class="cur"><?php echo $p; ?></span>
            <?php else: ?><a href="<?php echo esc_url(add_query_arg(array('paged'=>$p,'s'=>$search,'sort'=>$sort),$base)); ?>"><?php echo $p; ?></a><?php endif; ?>
        <?php endfor; ?>
        <?php if($paged<$pages): ?><a href="<?php echo esc_url(add_query_arg(array('paged'=>$paged+1,'s'=>$search,'sort'=>$sort),$base)); ?>">下頁 ›</a><?php endif; ?>
        <span style="font-size:12px;color:#94a3b8">第<?php echo $paged;?>/<?php echo $pages;?>頁，共<?php echo number_format($total);?>位</span>
    </div>
    <?php endif; ?>
    </div>
    <?php
}


/* ============================================================
 * 9. 📋 異動紀錄
 * ============================================================ */
function ckc_pts_page_log() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( '權限不足' ); }
    $filter  = sanitize_text_field($_GET['type']??'');
    $base    = admin_url('admin.php?page=ckc-points-log');
    $logs    = ckc_pts_recent_log(100);
    ?>
    <div class="wrap ckc-pts"><h1>📋 點數異動紀錄</h1>
    <div class="ckc-sb">
        <?php foreach(array(''=>'全部','分潤'=>'分潤','首購'=>'首購禮','手動'=>'手動調整','退款'=>'退款回收','批量'=>'批量發放') as $k=>$lbl): ?>
        <a href="<?php echo esc_url($k?add_query_arg('type',$k,$base):$base); ?>" class="button <?php echo $filter===$k?'button-primary':''; ?>"><?php echo $lbl; ?></a>
        <?php endforeach; ?>
    </div>
    <table class="ckc-tbl"><thead><tr><th>時間</th><th>會員</th><th>Email</th><th style="text-align:right">變動</th><th>原因</th></tr></thead><tbody>
    <?php $shown=0;
    foreach($logs as $e){
        $rsn=$e['reason']??'';
        if($filter&&mb_strpos($rsn,$filter)===false){continue;}
        $pts=intval($e['points']??0);$u=get_userdata($e['user_id']);$shown++;
        ?>
    <tr><td style="color:#94a3b8;font-size:12px;white-space:nowrap"><?php echo esc_html($e['time']); ?></td>
        <td><?php if($u):?><a href="<?php echo esc_url(get_edit_user_link($u->ID)); ?>"><?php echo esc_html($u->display_name); ?></a><?php else:echo 'ID '.$e['user_id'];endif; ?></td>
        <td style="color:#94a3b8;font-size:12px"><?php echo $u?esc_html($u->user_email):'─'; ?></td>
        <td style="text-align:right"><span class="ckc-b <?php echo $pts>=0?'ckc-bplus':'ckc-bminus'; ?>"><?php echo ($pts>=0?'+':'').number_format($pts); ?> 點</span></td>
        <td style="color:#475569;font-size:13px"><?php echo esc_html($rsn); ?></td>
    </tr><?php }
    if(!$shown){echo '<tr><td colspan=5 class="ckc-empty">此分類暫無紀錄</td></tr>';}
    ?></tbody></table>
    <p style="color:#94a3b8;font-size:12px;margin-top:12px">⚠️ 顯示最近 100 筆。完整歷史查詢 user meta: <code>_ckc_ref_log</code></p>
    </div>
    <?php
}

/* ============================================================
 * 10. 🎁 批量發放
 * ============================================================ */
function ckc_pts_page_batch() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( '權限不足' ); }
    $roles = wp_roles()->get_names();
    ?>
    <div class="wrap ckc-pts"><h1>🎁 批量發放點數</h1>
    <p style="color:#64748b;margin-bottom:20px">選擇發放對象、輸入點數與原因，可先「預覽名單」確認後再執行。</p>
    <div class="ckc-card" style="max-width:820px">
        <div id="ckc-batch-ntc" class="ckc-ntc"></div>

        <!-- 發放方向 + 點數 + 原因 -->
        <table style="width:100%;border-collapse:collapse">
        <tr><td style="padding:10px 0;width:140px;font-weight:600;font-size:14px">操作方向</td>
            <td><select id="ckc-batch-sign" style="border:1px solid #e2e8f0;border-radius:6px;padding:8px 12px;font-size:14px;width:200px">
                <option value="+">＋ 增加點數</option>
                <option value="-">－ 扣除點數</option>
            </select></td></tr>
        <tr><td style="padding:10px 0;font-weight:600;font-size:14px">點數數量</td>
            <td><input type="number" id="ckc-batch-amount" min="1" placeholder="例：100" style="border:1px solid #e2e8f0;border-radius:6px;padding:8px 12px;font-size:14px;width:160px"> <span style="color:#64748b;font-size:13px">點（每位會員）</span></td></tr>
        <tr><td style="padding:10px 0;font-weight:600;font-size:14px">發放原因</td>
            <td><input type="text" id="ckc-batch-reason" placeholder="例：7月份購物節活動回饋" style="border:1px solid #e2e8f0;border-radius:6px;padding:8px 12px;font-size:14px;width:360px"></td></tr>
        </table>

        <div style="margin:20px 0 8px;font-weight:700;font-size:14px;color:#1e293b;border-top:1px solid #f1f5f9;padding-top:16px">📌 發放對象</div>
        <select id="ckc-batch-target" style="border:1px solid #e2e8f0;border-radius:6px;padding:8px 12px;font-size:14px;width:260px;margin-bottom:16px">
            <option value="">── 請選擇發放對象 ──</option>
            <option value="role">依會員角色</option>
            <option value="tag">依客戶標籤</option>
            <option value="list">手動輸入名單（Email）</option>
            <option value="order">近期消費顧客</option>
            <option value="all_with_points">所有持有點數會員</option>
        </select>

        <!-- 角色 -->
        <div class="ckc-batch-target-section" id="ckc-batch-role" style="display:none">
            <label style="font-size:13px;color:#64748b;display:block;margin-bottom:6px">選擇角色：</label>
            <select id="ckc-batch-role" style="border:1px solid #e2e8f0;border-radius:6px;padding:8px 12px;font-size:14px;width:260px">
                <?php foreach($roles as $k=>$v): ?><option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($v); ?></option><?php endforeach; ?>
            </select>
        </div>

        <!-- 標籤 -->
        <div class="ckc-batch-target-section" id="ckc-batch-tag" style="display:none">
            <label style="font-size:13px;color:#64748b;display:block;margin-bottom:6px">客戶標籤名稱（user meta <code>_ckc_customer_tag</code>）：</label>
            <input type="text" id="ckc-batch-tag" placeholder="例：VIP" style="border:1px solid #e2e8f0;border-radius:6px;padding:8px 12px;font-size:14px;width:260px">
        </div>

        <!-- 手動名單 -->
        <div class="ckc-batch-target-section" id="ckc-batch-list" style="display:none">
            <label style="font-size:13px;color:#64748b;display:block;margin-bottom:6px">Email 名單（每行一個，或逗號分隔）：</label>
            <textarea id="ckc-batch-list" placeholder="example@gmail.com&#10;another@email.com" style="border:1px solid #e2e8f0;border-radius:6px;padding:8px 12px;font-size:13px;width:380px;height:120px;resize:vertical"></textarea>
        </div>

        <!-- 近期消費顧客 -->
        <div class="ckc-batch-target-section" id="ckc-batch-order" style="display:none">
            <label style="font-size:13px;color:#64748b;display:block;margin-bottom:6px">近幾天內有「已完成」訂單的顧客：</label>
            <input type="number" id="ckc-batch-order-days" value="30" min="1" style="border:1px solid #e2e8f0;border-radius:6px;padding:8px 12px;font-size:14px;width:100px"> <span style="color:#64748b;font-size:13px">天內</span>
        </div>

        <!-- 按鈕 -->
        <div style="margin-top:24px;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
            <button id="ckc-batch-preview-btn" class="button" style="padding:9px 20px;font-size:14px">📋 預覽名單</button>
            <button id="ckc-batch-exec-btn" class="button button-primary" style="padding:9px 20px;font-size:14px;background:#15803d;border-color:#15803d">🚀 執行發放</button>
            <span style="color:#94a3b8;font-size:12px">⚠️ 建議先預覽確認名單，再執行發放。執行後不可復原。</span>
        </div>

        <!-- 進度條 -->
        <div id="ckc-batch-progress" style="display:none;margin-top:12px">
            <div style="font-size:13px;color:#64748b;margin-bottom:4px">發放中，請稍候…</div>
            <div class="ckc-progress-wrap"><div class="ckc-progress-bar" id="ckc-batch-bar"></div></div>
        </div>

        <!-- 預覽結果 -->
        <div class="ckc-batch-preview" id="ckc-batch-preview-wrap">
            <div id="ckc-batch-preview-body"></div>
            <p style="color:#64748b;font-size:12px;margin:8px 0 0">⚠️ 預覽最多顯示 50 筆，實際發放依完整名單計算。</p>
        </div>
    </div>

    <!-- 說明 -->
    <div class="ckc-sec" style="margin-top:32px">📎 各發放對象說明</div>
    <table class="ckc-tbl" style="max-width:820px"><thead><tr><th>發放對象</th><th>篩選邏輯</th><th>適用情境</th></tr></thead><tbody>
    <tr><td>依會員角色</td><td>WP 角色（如 customer、subscriber）</td><td>對全體顧客或特定角色統一發點</td></tr>
    <tr><td>依客戶標籤</td><td>user meta <code>_ckc_customer_tag</code> 模糊比對</td><td>已分組標籤的 VIP / 分層顧客</td></tr>
    <tr><td>手動名單</td><td>Email 精確比對，找到對應帳號</td><td>活動抽獎名單、名冊發放</td></tr>
    <tr><td>近期消費顧客</td><td>N 天內「已完成」訂單的 customer_id</td><td>消費回饋、購物節活動</td></tr>
    <tr><td>所有持有點數會員</td><td>wps_wpr_points &gt; 0</td><td>全點數會員周年禮、大型活動</td></tr>
    </tbody></table>
    </div>
    <?php
}

/* ============================================================
 * 11. ⚙️ 發放設定
 * ============================================================ */
function ckc_pts_page_settings() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( '權限不足' ); }
    $rate        = (float) get_option('_ckc_ref_commission_rate', 0.05);
    $cap         = (int)   get_option('_ckc_ref_commission_cap',  500);
    $bonus       = (int)   get_option('_ckc_ref_referred_bonus',  50);
    $signup      = (int)   get_option('_ckc_ref_signup_bonus',    0);
    // 兌換比例：優先讀我們自己的 option，初始值從 WPS 外掛抓
    $wps              = get_option('wps_wpr_settings_gallery', array());
    $redeem_pts       = (int) get_option('_ckc_redeem_pts', $wps['wps_wpr_cart_points_rate'] ?? 1);
    $redeem_val       = (int) get_option('_ckc_redeem_val', $wps['wps_wpr_cart_price_rate']  ?? 1);
    $per_point        = $redeem_pts > 0 ? round( $redeem_val / $redeem_pts, 2 ) : 1;
    $purchase_enabled = get_option('_ckc_purchase_bonus_enabled', 'no');
    $purchase_pts     = (int) get_option('_ckc_purchase_bonus_pts', 1);
    $purchase_val     = (int) get_option('_ckc_purchase_bonus_val', 100);
    ?>
    <div class="wrap ckc-pts"><h1>⚙️ 紅利點數 發放設定</h1>
    <p style="color:#64748b;margin-bottom:20px">控制紅利點數的兌換比例與發放邏輯，修改後即時同步前台。</p>
    <div id="ckc-set-ntc" class="ckc-ntc"></div>

    <div class="ckc-sec">💱 點數兌換比例</div>
    <div class="ckc-card" style="max-width:700px;margin-bottom:24px">
        <div class="ckc-setrow">
            <div>
                <div class="ckc-setlbl">折抵比例設定</div>
                <div class="ckc-setdesc">
                    N 點 可折抵 NT$M<br>
                    儲存後自動同步至前台結帳折抵與「我的帳號 → 紅利點數」顯示
                </div>
            </div>
            <div class="ckc-setinput" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <input type="number" id="ckc-s-redeem-pts" value="<?php echo esc_attr($redeem_pts); ?>" min="1" step="1" style="width:80px"> <span style="color:#64748b;font-size:13px">點</span>
                <span style="color:#94a3b8;font-size:15px;padding:0 4px">=</span>
                <span style="color:#64748b;font-size:13px">NT$</span><input type="number" id="ckc-s-redeem-val" value="<?php echo esc_attr($redeem_val); ?>" min="1" step="1" style="width:80px">
            </div>
        </div>
        <div style="margin:12px 0 0;padding:12px 16px;background:#f8fafc;border-radius:8px;font-size:13px;color:#475569">
            📌 目前比例：<strong><?php echo $redeem_pts; ?> 點</strong> 折抵 <strong>NT$<?php echo $redeem_val; ?></strong>（每點折抵 NT$<?php echo $per_point; ?>）
            ／折抵 NT$1 需 <strong><?php echo $per_point > 0 ? round(1/$per_point,1) : '─'; ?> 點</strong>
        </div>
    </div>

    <div class="ckc-sec">🎛️ 分潤 &amp; 發放設定</div>
    <div class="ckc-card" style="max-width:700px">
        <div class="ckc-setrow">
            <div><div class="ckc-setlbl">分潤比例 (%)</div><div class="ckc-setdesc">推薦人訂單完成後獲得的點數比例<br>例：5 = 訂單金額 5% 作為點數</div></div>
            <div class="ckc-setinput"><input type="number" id="ckc-s-rate" value="<?php echo esc_attr($rate*100); ?>" min=".1" max="100" step=".1"> <span style="color:#64748b;font-size:13px">%</span></div>
        </div>
        <div class="ckc-setrow">
            <div><div class="ckc-setlbl">單筆分潤上限（點）</div><div class="ckc-setdesc">每筆訂單分潤上限，0 = 不限制</div></div>
            <div class="ckc-setinput"><input type="number" id="ckc-s-cap" value="<?php echo esc_attr($cap); ?>" min="0" step="1"> <span style="color:#64748b;font-size:13px">點</span></div>
        </div>
        <div class="ckc-setrow">
            <div><div class="ckc-setlbl">被推薦人首購禮（點）</div><div class="ckc-setdesc">首次透過推薦連結下單完成的買家加贈<br>0 = 停用</div></div>
            <div class="ckc-setinput"><input type="number" id="ckc-s-bonus" value="<?php echo esc_attr($bonus); ?>" min="0" step="1"> <span style="color:#64748b;font-size:13px">點</span></div>
        </div>
        <div class="ckc-setrow">
            <div><div class="ckc-setlbl">新會員註冊禮（點）</div><div class="ckc-setdesc">新帳號完成註冊時發放<br>0 = 停用</div></div>
            <div class="ckc-setinput"><input type="number" id="ckc-s-signup" value="<?php echo esc_attr($signup); ?>" min="0" step="1"> <span style="color:#64748b;font-size:13px">點</span></div>
    </div>

    <div class="ckc-sec">🛍️ 消費回饋設定</div>
    <div class="ckc-card" style="max-width:700px;margin-bottom:24px">
        <div class="ckc-setrow">
            <div><div class="ckc-setlbl">啟用消費回饋</div><div class="ckc-setdesc">顧客購買完成後是否發放紅利點數回饋</div></div>
            <div class="ckc-setinput">
                <select id="ckc-s-purchase-enabled" style="border:1px solid #e2e8f0;border-radius:6px;padding:6px 10px;font-size:13px;width:120px">
                    <option value="yes" <?php selected($purchase_enabled, 'yes'); ?>>啟用</option>
                    <option value="no" <?php selected($purchase_enabled, 'no'); ?>>停用</option>
                </select>
            </div>
        </div>
        <div class="ckc-setrow">
            <div>
                <div class="ckc-setlbl">消費回饋比例</div>
                <div class="ckc-setdesc">每消費 N 元，可獲得 M 點<br>計算基礎：以商品小計金額扣除折價券折抵後的實質消費額（不含運費）</div>
            </div>
            <div class="ckc-setinput" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <span style="color:#64748b;font-size:13px">每消費 NT$</span><input type="number" id="ckc-s-purchase-val" value="<?php echo esc_attr($purchase_val); ?>" min="1" step="1" style="width:80px">
                <span style="color:#94a3b8;font-size:15px;padding:0 4px">➔</span>
                <span style="color:#64748b;font-size:13px">回饋</span><input type="number" id="ckc-s-purchase-pts" value="<?php echo esc_attr($purchase_pts); ?>" min="1" step="1" style="width:80px"><span style="color:#64748b;font-size:13px">點</span>
            </div>
        </div>
        <div style="margin-top:20px;border-top:1px solid #f1f5f9;padding-top:16px">
            <button id="ckc-pts-save" class="button button-primary" style="padding:8px 24px;font-size:14px">儲存設定</button>
            <span style="margin-left:12px;font-size:12px;color:#94a3b8">儲存後立即生效（含兌換比例、分潤與消費回饋設定）</span>
        </div>
    </div>

    <!-- 前台顯示預覽 -->
    <div class="ckc-sec" style="margin-top:32px">👁️ 前台顯示預覽（依目前儲存設定）</div>
    <div class="ckc-card" style="background:#fdfaf7;border-color:#f5ebe6;max-width:700px;margin-bottom:24px">
        <p style="margin:0 0 4px;font-size:12px;color:#94a3b8;font-weight:600">前台「推薦好友」頁說明文字：</p>
        <p style="margin:0;font-size:14px;color:#475569;line-height:2">
            「把您的專屬連結分享給親朋好友——好友透過連結下單完成後，您可獲得訂單金額
            <strong style="color:#b91c1c"><?php echo round($rate*100,1); ?>%</strong>
            的紅利點數回饋（單筆最高 <strong style="color:#b91c1c"><?php echo number_format($cap); ?></strong> 點），
            好友首購再加贈 <strong style="color:#b91c1c"><?php echo number_format($bonus); ?> 點</strong>！1 點可折抵 NT$1。」
        </p>
        <p style="margin:10px 0 0;font-size:12px;color:#94a3b8">
            ✅ 以上文字從後台設定即時讀取，儲存後前台自動更新。<a href="<?php echo home_url('/my-account/referral/'); ?>" target="_blank">查看前台推薦頁 →</a>
        </p>
    </div>

    <!-- 點數有效期限說明 -->
    <div class="ckc-sec" style="margin-top:32px">⌛ 點數有效期限規則</div>
    <div class="ckc-card" style="background:#f8fafc;max-width:700px;margin-bottom:24px">
        <p style="margin:0;font-size:13px;color:#475569;line-height:1.8">
            📌 <strong>有效期限規範</strong>：自會員紅利點數<strong>累積首月</strong>起算，<strong>二年內有效，並以二年為一期</strong>。<br>
            - <strong>起算點</strong>：當會員帳戶點數大於 0 時，該月份即被自動標記為點數累積起算首月。<br>
            - <strong>到期清除</strong>：二年期滿當日（例如 2026/07 累積首筆，到期日為 2028/06/30），該期內累積的所有點數餘額將會自動清除歸零。<br>
            - <strong>新的一期</strong>：點數歸零後，會員下一筆獲得點數的月份將自動重新起算新的一期（二年）。
        </p>
    </div>

    <!-- 資料對照表 -->

    <div class="ckc-sec" style="margin-top:32px">📎 資料對照表</div>
    <table class="ckc-tbl" style="max-width:700px"><thead><tr><th>設定項目</th><th>wp_options key</th></tr></thead><tbody>
    <tr><td>分潤比例</td><td><code>_ckc_ref_commission_rate</code>（小數，0.05 = 5%）</td></tr>
    <tr><td>單筆上限</td><td><code>_ckc_ref_commission_cap</code></td></tr>
    <tr><td>首購禮</td><td><code>_ckc_ref_referred_bonus</code></td></tr>
    <tr><td>註冊禮</td><td><code>_ckc_ref_signup_bonus</code></td></tr>
    <tr><td>會員點數餘額</td><td><code>wps_wpr_points</code>（user meta）</td></tr>
    <tr><td>點數累積起算首月</td><td><code>_ckc_points_start_month</code>（user meta，格式 Y-m）</td></tr>
    <tr><td>點數異動日誌（WPS 格式）</td><td><code>points_details</code>（user meta）</td></tr>
    <tr><td>分潤日誌（自訂）</td><td><code>_ckc_ref_log</code>（user meta）</td></tr>
    <tr><td>消費回饋啟用狀態</td><td><code>_ckc_purchase_bonus_enabled</code>（'yes' / 'no'）</td></tr>
    <tr><td>消費回饋比例</td><td><code>_ckc_purchase_bonus_val</code>（消費金額）/ <code>_ckc_purchase_bonus_pts</code>（回饋點數）</td></tr>
    </tbody></table>
    </div>
    <?php
}
