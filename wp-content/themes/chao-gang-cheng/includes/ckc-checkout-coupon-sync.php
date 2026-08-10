<?php
/**
 * 結帳頁優惠券套用/移除同步腳本（獨立檔案）。
 *
 * 背景（2026-08 除錯記錄）：這段 JS 原本寫在 includes/ckc-coupons.php 的
 * ckc_checkout_coupon_ajax_script() 裡。程式碼本身完全正確（git status 確認
 * 已經 push、和遠端一致，PHP 語法也驗證通過），但實測發現這段輸出完全沒有
 * 出現在結帳頁的原始碼裡——甚至連放在函式最前面、無害的 HTML 註解標記都
 * 沒有出現，同時已經確認：(1) 沒有 PHP fatal error（同一個 hook 上，優先權
 * 更後面的紅利點數面板照樣正常輸出）、(2) 不是 WooCommerce AJAX 把這段內容
 * 換掉（實測這段 DOM 完全在 .woocommerce-checkout-review-order-table／
 * .woocommerce-checkout-payment 這兩個會被 AJAX 換掉的區域之外）、(3) 不是
 * WordPress.com 的邊緣快取或物件快取（Hosting Dashboard 手動全部清除過，
 * 清完照樣沒有）、(4) 不是 git 沒 push（git status 顯示乾淨、everything
 * up-to-date）。所有跡象指向 PHP 層還在跑這個檔案「舊版」的編譯結果（opcache
 * 沒有隨部署更新，但這不是網站管理者能自行清除的層級）。
 *
 * 解法：把這段輸出搬到一個全新、從未存在過的檔案（也就是這個檔案），讓它
 * 一定會被當成全新內容重新編譯，繞過舊檔案可能卡住的編譯快取。
 * ckc-coupons.php 裡的 ckc_checkout_coupon_panel() 改成呼叫這裡的函式。
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 獨立掛在同一個 hook 上（priority 7，晚於 ckc_checkout_coupon_panel 的 5 與
// ckc_checkout_points_panel 的 6），不依賴 ckc-coupons.php 裡的呼叫是否有生效
// ——就算 ckc-coupons.php 那邊的呼叫因為編譯快取沒吃到新版也沒關係，這裡會
// 獨立輸出。所有監聽器都是掛在 document／document.body 上的事件代理，跟
// 這段 <script> 在頁面上輸出的先後順序無關，一定吃得到。
add_action( 'woocommerce_before_checkout_form', 'ckc_checkout_coupon_sync_script', 7 );
function ckc_checkout_coupon_sync_script() {
    static $done = false;
    if ( $done ) {
        return;
    }
    $done = true;
    ?>
    <style>
    #ckc-coupon-toast{
        position:fixed; left:50%; bottom:84px; transform:translateX(-50%) translateY(20px);
        background:#16a34a; color:#fff; padding:14px 24px; border-radius:30px;
        font-size:15px; font-weight:700; line-height:1.4; z-index:2147483000;
        max-width:88vw; text-align:center; box-shadow:0 8px 24px rgba(0,0,0,.25);
        opacity:0; pointer-events:none; transition:opacity .25s ease, transform .25s ease;
    }
    #ckc-coupon-toast.ckc-show{ opacity:1; transform:translateX(-50%) translateY(0); }

    @keyframes ckc-spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    @keyframes ckc-price-highlight {
        0% { background-color: transparent; }
        15% { background-color: #d1fae5; color: #047857; transform: scale(1.05); }
        85% { background-color: #d1fae5; color: #047857; transform: scale(1.05); }
        100% { background-color: transparent; transform: scale(1); }
    }
    .ckc-price-highlight {
        display: inline-block;
        padding: 0 4px;
        border-radius: 4px;
        animation: ckc-price-highlight 0.6s ease-out;
    }
    body.ckc-applying-coupon .blockUI.blockOverlay::after {
        content: '套用優惠券中，請稍候…';
        position: fixed;
        left: 50%;
        top: 50%;
        transform: translate(-50%, 36px);
        background: rgba(18, 18, 18, 0.85);
        color: #fff;
        padding: 8px 18px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 700;
        white-space: nowrap;
        z-index: 2147483000;
        pointer-events: none;
    }
    </style>
    <script>
    jQuery(function($){
        function ckcToast(msg, isError, persist){
            var $t = $('#ckc-coupon-toast');
            if(!$t.length){ $t = $('<div id="ckc-coupon-toast" role="status" aria-live="polite"></div>').appendTo('body'); }
            $t.text(msg).css('background', isError ? '#b91c1c' : '#16a34a');
            requestAnimationFrame(function(){ $t.addClass('ckc-show'); });
            clearTimeout(window._ckcToastTimer);
            if (!persist) {
                window._ckcToastTimer = setTimeout(function(){ $t.removeClass('ckc-show'); }, 1500);
            }
        }

        function ckcRefreshCouponCards(appliedCode){
            var appliedCodes = [];

            if (typeof appliedCode !== 'undefined' && appliedCode) {
                appliedCodes.push(appliedCode.toString().toUpperCase());
            } else {
                $('.woocommerce-checkout-review-order-table .cart-discount, .woocommerce-cart-form .cart-discount, .cart_totals .cart-discount').each(function(){
                    var cls = $(this).attr('class') || '';
                    var m = cls.match(/coupon-([a-zA-Z0-9_-]+)/i);
                    if (m && m[1]) { appliedCodes.push(m[1].toUpperCase()); }
                });
            }

            $('.ckc-coupon-card').each(function(){
                var $c = $(this);
                if($c.hasClass('is-expired')){ return; }
                var code = ($c.data('code') || '').toString().toUpperCase();
                var url  = $c.attr('data-apply-url') || '#';
                var $action = $c.find('.ckc-coupon-action');
                if($.inArray(code, appliedCodes) !== -1){
                    $c.addClass('is-applied');
                    $action.html('<span class="ckc-coupon-applied">✓ 已套用</span>');
                } else {
                    $c.removeClass('is-applied');
                    $action.html('<a href="'+url+'" class="ckc-coupon-apply" data-coupon-code="'+code+'">套用去結帳</a>');
                }
            });
        }

        $(document.body).on('updated_checkout updated_cart_totals', function(){
            ckcRefreshCouponCards();
        });

        function ckcScrollLockOn(){
            window._ckcCouponScrollY = window.scrollY || window.pageYOffset;
            window._ckcCouponScrollLock = true;
        }
        function ckcScrollLockOff(){
            if(!window._ckcCouponScrollLock){ return; }
            window._ckcCouponScrollLock = false;
            $('html,body').stop(true,false);
            window.scrollTo(0, window._ckcCouponScrollY || 0);
        }
        if ($.scroll_to_notices) {
            var _ckcOrigScrollCoupon = $.scroll_to_notices;
            $.scroll_to_notices = function(el){
                if (window._ckcCouponScrollLock) { $('html,body').stop(true,false); return; }
                _ckcOrigScrollCoupon.call(this, el);
            };
        }

        function ckcBtnSpinner($btn){
            var isCardApply = $btn.hasClass('ckc-coupon-apply');
            $btn.prop('disabled', true).css({ 'opacity': '0.7', 'cursor': 'not-allowed', 'pointer-events': 'none' });
            $btn.html('<svg class="ckc-spin" style="animation: ckc-spin 1s linear infinite; width: 14px; height: 14px; margin-right: 6px; vertical-align: middle; display: inline-block;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>' + (isCardApply ? '套用中' : '驗證中'));
        }
        function ckcBtnRestore($btn, originalHtml){
            if ($btn && $btn.length) {
                $btn.prop('disabled', false).css({ 'opacity': '1', 'cursor': 'pointer', 'pointer-events': 'auto' }).html(originalHtml);
            }
        }
        function ckcApplySuccessUI(code, $btn){
            if ($btn && $btn.length) { $btn.css({ 'background': '#10b981', 'color': '#fff' }).html('✓ 成功'); }
            $('#ckc-checkout-coupon-code').val('');
            ckcRefreshCouponCards(code);

            var $orderTotal = $('.order-total .woocommerce-Price-amount').first();
            if($orderTotal.length) {
                $orderTotal.addClass('ckc-price-highlight');
                setTimeout(function(){
                    $orderTotal.removeClass('ckc-price-highlight');
                    ckcToast('折價券使用成功');
                }, 600);
            } else {
                ckcToast('折價券使用成功');
            }
        }

        function ckcApplyCoupon(code, $btn){
            code = $.trim(code || '');
            if(!code){ ckcToast('請輸入折扣碼', true); return; }
            if (window._ckcApplyPending) { return; }

            var originalBtnText = ($btn && $btn.length) ? $btn.html() : '';
            if ($btn && $btn.length) { ckcBtnSpinner($btn); }
            ckcToast('正在核算最新金額，請稍候…', false, true);

            var $form = $('form.checkout');
            if ($form.length && typeof wc_checkout_params !== 'undefined') {
                var $field = $form.find('input[name="ckc_apply_coupon_now"]');
                if(!$field.length){
                    $field = $('<input type="hidden" name="ckc_apply_coupon_now" />').appendTo($form);
                }
                $field.val(code);
                window._ckcApplyPending = { code: code, $btn: $btn, original: originalBtnText };
                ckcScrollLockOn();
                $('body').addClass('ckc-applying-coupon');
                $(document.body).trigger('update_checkout');
                clearTimeout(window._ckcApplyTimer);
                window._ckcApplyTimer = setTimeout(function(){
                    var p = window._ckcApplyPending;
                    if (p) {
                        window._ckcApplyPending = null;
                        $('form.checkout').find('input[name="ckc_apply_coupon_now"]').val('');
                        ckcBtnRestore(p.$btn, p.original);
                        ckcScrollLockOff();
                        $('body').removeClass('ckc-applying-coupon');
                        ckcToast('連線逾時，請再試一次。', true);
                    }
                }, 15000);
                return;
            }

            var ajaxUrl = '/?wc-ajax=apply_coupon';
            var nonce = '';
            if (typeof wc_checkout_params !== 'undefined') {
                ajaxUrl = wc_checkout_params.wc_ajax_url.toString().replace( '%%endpoint%%', 'apply_coupon' );
                nonce = wc_checkout_params.apply_coupon_nonce;
            }
            $.ajax({
                type: 'POST',
                url: ajaxUrl,
                data: { security: nonce, coupon_code: code },
                dataType: 'html',
                success: function( htmlResponse ) {
                    if (htmlResponse.indexOf('woocommerce-error') !== -1) {
                        ckcBtnRestore($btn, originalBtnText);
                        var errMsg = $(htmlResponse).text().trim() || '折價券套用失敗，請確認代碼或使用條件。';
                        ckcToast(errMsg, true);
                    } else {
                        ckcApplySuccessUI(code, $btn);
                        $(document.body).trigger('update_checkout');
                    }
                },
                error: function() {
                    ckcBtnRestore($btn, originalBtnText);
                    ckcToast('套用時發生錯誤，請稍後再試。', true);
                }
            });
        }

        $(document.body).on('updated_checkout', function(e, data) {
            var p = window._ckcApplyPending;
            if (!p) { return; }
            window._ckcApplyPending = null;
            clearTimeout(window._ckcApplyTimer);
            $('form.checkout').find('input[name="ckc_apply_coupon_now"]').val('');
            $('body').removeClass('ckc-applying-coupon');

            var codeLower = (p.code || '').toString().toLowerCase();
            var applied;
            var list = data && data.fragments && data.fragments.ckc_applied_coupons;
            if (list && list.length !== undefined) {
                applied = false;
                for (var i = 0; i < list.length; i++) {
                    if ((list[i] || '').toString().toLowerCase() === codeLower) { applied = true; break; }
                }
            } else {
                var cls = 'coupon-' + codeLower.replace(/[^a-z0-9_-]/g, '');
                applied = $('.cart-discount.' + cls).length > 0;
            }

            if (applied) {
                ckcApplySuccessUI(p.code, p.$btn);
                $('.woocommerce-NoticeGroup-updateOrderReview').remove();
            } else {
                ckcBtnRestore(p.$btn, p.original);
                var $ng = $('.woocommerce-NoticeGroup-updateOrderReview');
                var errText = $.trim($ng.find('.woocommerce-error li').first().text())
                           || $.trim($ng.find('.woocommerce-error').first().text())
                           || '折價券套用失敗，請確認代碼或使用條件。';
                $ng.remove();
                ckcToast(errText, true);
            }
            ckcScrollLockOff();
        });

        $(document).on('click', '#ckc-checkout-coupon-apply', function(e){
            e.preventDefault();
            ckcApplyCoupon($('#ckc-checkout-coupon-code').val(), $(this));
        });
        $(document).on('keydown', '#ckc-checkout-coupon-code', function(e){
            if(e.key === 'Enter'){ e.preventDefault(); ckcApplyCoupon($(this).val(), $('#ckc-checkout-coupon-apply')); }
        });

        $(document).on('click', '.ckc-coupon-apply', function(e){
            var code = $(this).data('coupon-code');
            if(code){ e.preventDefault(); ckcApplyCoupon(code, $(this)); }
        });
    });
    </script>
    <?php
}
