    
    jQuery(function($){
        // 行動版友善的浮出提示（非阻塞、底部置中、自動消失），取代 alert
        function ckcToast(msg, isError){
            var $t = $('#ckc-coupon-toast');
            if(!$t.length){ $t = $('<div id="ckc-coupon-toast" role="status" aria-live="polite"></div>').appendTo('body'); }
            $t.text(msg).css('background', isError ? '#b91c1c' : '#16a34a');
            requestAnimationFrame(function(){ $t.addClass('ckc-show'); });
            clearTimeout(window._ckcToastTimer);
            window._ckcToastTimer = setTimeout(function(){ $t.removeClass('ckc-show'); }, 1500);
        }

        // 套用後同步券卡片狀態（限一張：套用的顯示「已套用」，其餘恢復可套用），
        // 修正前端介面沒有連動的問題。
        function ckcRefreshCouponCards(appliedCode){
            var ac = (appliedCode || '').toString().toUpperCase();
            $('.ckc-coupon-card').each(function(){
                var $c = $(this);
                if($c.hasClass('is-expired')){ return; }
                var code = ($c.data('code') || '').toString().toUpperCase();
                var url  = $c.attr('data-apply-url') || '#';
                var $action = $c.find('.ckc-coupon-action');
                if(code === ac){
                    $c.addClass('is-applied');
                    $action.html('<span class="ckc-coupon-applied">✓ 已套用</span>');
                } else {
                    $c.removeClass('is-applied');
                    $action.html('<a href="'+url+'" class="ckc-coupon-apply" data-coupon-code="'+code+'">套用去結帳</a>');
                }
            });
        }

        function ckcApplyCoupon(code, $btn){
            code = $.trim(code || '');
            if(!code){ ckcToast('請輸入折扣碼', true); return; }
            
            // 局部按鈕動畫 (不封鎖全域結帳表單)
            var originalBtnText = '';
            var isCardApply = false;
            if ($btn && $btn.length) {
                originalBtnText = $btn.html();
                isCardApply = $btn.hasClass('ckc-coupon-apply');
                $btn.prop('disabled', true).css({ 'opacity': '0.7', 'cursor': 'not-allowed', 'pointer-events': 'none' });
                // 加入 Spinner
                $btn.html('<svg class="ckc-spin" style="animation: ckc-spin 1s linear infinite; width: 14px; height: 14px; margin-right: 6px; vertical-align: middle; display: inline-block;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>' + (isCardApply ? '套用中' : '驗證中'));
            }

            // OPTIMIZATION: 改用 WooCommerce 原生 AJAX 節點，省去兩次 fetch 的時間 (反應速度加倍)
            var ajaxUrl = '/?wc-ajax=apply_coupon';
            var nonce = '';
            if (typeof wc_checkout_params !== 'undefined') {
                ajaxUrl = wc_checkout_params.wc_ajax_url.toString().replace( '%%endpoint%%', 'apply_coupon' );
                nonce = wc_checkout_params.apply_coupon_nonce;
            }

            $.ajax({
                type: 'POST',
                url: ajaxUrl,
                data: {
                    security: nonce,
                    coupon_code: code
                },
                dataType: 'html',
                success: function( htmlResponse ) {
                    if (htmlResponse.indexOf('woocommerce-error') !== -1) {
                        if ($btn && $btn.length) { $btn.prop('disabled', false).css({ 'opacity': '1', 'cursor': 'pointer', 'pointer-events': 'auto' }).html(originalBtnText); }
                        // 解析原生的錯誤訊息
                        var errMsg = $(htmlResponse).text().trim() || '折價券套用失敗，請確認代碼或使用條件。';
                        ckcToast(errMsg, true);
                    } else {
                        // 成功：給予強烈視覺回饋 (瞬間變為成功狀態)
                        if ($btn && $btn.length) { 
                            $btn.css({ 'background': '#10b981', 'color': '#fff' }).html('✓ 成功'); 
                        }
                        $('#ckc-checkout-coupon-code').val('');
                        ckcRefreshCouponCards(code);          
                        window._ckc_coupon_just_applied = true; 
                        $(document.body).trigger('update_checkout'); 
                        // 金額更新完成後按鈕會自己隨著畫面被重繪覆蓋，或保留成功狀態
                    }
                },
                error: function() {
                    if ($btn && $btn.length) { $btn.prop('disabled', false).css({ 'opacity': '1', 'cursor': 'pointer', 'pointer-events': 'auto' }).html(originalBtnText); }
                    ckcToast('套用時發生錯誤，請稍後再試。', true);
                }
            });
        }
        
        // 監聽結帳頁更新完畢事件，若有套用折價券，這時再顯示成功 Toast，完成最後一步！
        $(document.body).on('updated_checkout', function() {
            if (window._ckc_coupon_just_applied) {
                window._ckc_coupon_just_applied = false;
                ckcToast('折價券使用成功');
                
                // 金額折抵高亮特效 (Highlight)
                var $orderTotal = $('.order-total .woocommerce-Price-amount').first();
                if($orderTotal.length) {
                    $orderTotal.addClass('ckc-price-highlight');
                    setTimeout(function(){
                        $orderTotal.removeClass('ckc-price-highlight');
                    }, 1500);
                }
            }
        });

        // 1. 折扣碼輸入框：點「套用」或按 Enter
        $(document).on('click', '#ckc-checkout-coupon-apply', function(e){
            e.preventDefault();
            ckcApplyCoupon($('#ckc-checkout-coupon-code').val());
        });
        $(document).on('keydown', '#ckc-checkout-coupon-code', function(e){
            if(e.key === 'Enter'){ e.preventDefault(); ckcApplyCoupon($(this).val()); }
        });

        // 2. 券卡片「套用去結帳」：改走 AJAX，避免整頁跳轉回頂部
        $(document).on('click', '.ckc-coupon-apply', function(e){
            var code = $(this).data('coupon-code');
            if(code){ e.preventDefault(); ckcApplyCoupon(code); }
        });
    });
    
    
    jQuery(function($){
        var _ckcScrollY    = 0;
        var _ckcScrollLock = false;
        var _ckcNonce   = $('#ckc-pts-nonce').val();
        var _ckcAjaxUrl = (typeof wc_checkout_params !== 'undefined' && wc_checkout_params.ajax_url)
                        ? wc_checkout_params.ajax_url : '/wp-admin/admin-ajax.php';

        // 覆寫 WooCommerce 原生的 scroll_to_notices，鎖定期間完全不捲動
        if ($.scroll_to_notices) {
            var _wcOrigScroll = $.scroll_to_notices;
            $.scroll_to_notices = function(el) {
                if (_ckcScrollLock) { $('html,body').stop(true,false); return; }
                _wcOrigScroll.call(this, el);
            };
        }

        function ckcLockScroll() {
            _ckcScrollY    = window.scrollY || window.pageYOffset;
            _ckcScrollLock = true;
        }
        function ckcUnlockScroll() {
            _ckcScrollLock = false;
            $('html,body').stop(true,false);
            window.scrollTo(0, _ckcScrollY);
        }
        function showToast(msg, bg) {
            var $t = $('#ckc-coupon-toast');
            if (!$t.length) { $t = $('<div id="ckc-coupon-toast" role="status" aria-live="polite"></div>').appendTo('body'); }
            $t.text(msg).css('background', bg);
            requestAnimationFrame(function(){ $t.addClass('ckc-show'); });
            setTimeout(function(){ $t.removeClass('ckc-show'); }, 2600);
        }

        // 即時切換面板狀態（不重載），修正「套用/移除後面板狀態不刷新」的問題
        function ckcPtsSetApplied(pts, discount) {
            var $card = $('.ckc-points-card');
            var d = (discount != null) ? discount : pts;
            $card.addClass('is-applied');
            $card.find('.ckc-points-value').html('🪙 ' + pts + ' 點');
            $card.find('.ckc-points-worth').text('折抵 NT$' + Number(d).toLocaleString());
            $card.find('.ckc-points-title').text('已套用紅利折抵');
            $('.ckc-points-extra').hide();
            $('#ckc-custom-points-wrap').hide();
        }
        function ckcPtsSetUnapplied() {
            var max = parseInt($('#ckc-pts-max').val(), 10) || 0;
            var $card = $('.ckc-points-card');
            $card.removeClass('is-applied');
            $card.find('.ckc-points-value').html('🪙 ' + max + ' 點');
            $card.find('.ckc-points-worth').text('折抵 NT$' + Number(max).toLocaleString());
            $card.find('.ckc-points-title').text('紅利點數全額折抵');
            $('.ckc-points-extra').show();
        }

        // 點數套用（立即全額 + 自訂）
        $(document).on('click', '.ckc-points-apply-btn, .ckc-points-custom-apply-btn', function(e){
            e.preventDefault();
            var pts = $(this).hasClass('ckc-points-apply-btn')
                      ? parseInt($('#ckc-pts-max').val(), 10)
                      : parseInt($('#ckc_custom_points_input').val(), 10);
            if (!(pts > 0)) { alert('請輸入有效的折抵點數。'); return; }

            ckcLockScroll();
            $.post(_ckcAjaxUrl, { action:'ckc_points_apply', points:pts, nonce:_ckcNonce },
                function(res) {
                    if (res && res.success) {
                        ckcPtsSetApplied(res.data && res.data.points ? res.data.points : pts, res.data && res.data.discount);
                        $(document.body).trigger('update_checkout');
                        $(document.body).one('updated_checkout', function(){
                            ckcUnlockScroll();
                            showToast('已套用紅利折抵', '#16a34a');
                        });
                    } else {
                        ckcUnlockScroll();
                        alert((res && res.data && res.data.msg) || '套用失敗，請重試。');
                    }
                }
            ).fail(function(){ ckcUnlockScroll(); alert('網路錯誤，請重試。'); });
        });

        // 點數移除
        $(document).on('click', '.ckc-points-remove-btn', function(e){
            e.preventDefault();
            ckcLockScroll();
            $.post(_ckcAjaxUrl, { action:'ckc_points_remove', nonce:_ckcNonce },
                function(res) {
                    if (res && res.success) {
                        ckcPtsSetUnapplied();
                        $(document.body).trigger('update_checkout');
                        $(document.body).one('updated_checkout', function(){
                            ckcUnlockScroll();
                            showToast('已取消套用紅利折抵', '#64748b');
                        });
                    } else {
                        ckcUnlockScroll();
                        alert('取消失敗，請重試。');
                    }
                }
            ).fail(function(){ ckcUnlockScroll(); alert('網路錯誤，請重試。'); });
        });
    });
    
