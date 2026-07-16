<?php
/**
 * Theme Footer Template
 *
 * @package Chao_Gang_Cheng
 */
?>
    </div><!-- #content -->

    <footer id="colophon" class="main-footer">
        <div class="container footer-grid">
            <!-- Column 1: Company Info -->
            <div class="footer-widget">
                <h3>潮港城事業股份有限公司</h3>
                <p style="font-size: 13px; line-height: 1.8; color: var(--text-muted); margin-bottom: 8px;">
                    統一編號：53301080
                </p>
                <p style="font-size: 13px; line-height: 1.8; color: var(--text-muted);">
                    地址：台中市南屯區環中路四段2號
                </p>
            </div>

            <!-- Column 2: Contact Details -->
            <div class="footer-widget footer-contact">
                <h3>聯絡我們</h3>
                <p>客服專線：04-2386-3322</p>
                <p>客服時間：平日10:00－18:00</p>
                <p>信箱：service@ckcgroup.com.tw</p>
            </div>

            <!-- Column 3: Info Links -->
            <div class="footer-widget">
                <h3>關於</h3>
                <ul>
                    <li><a href="<?php echo esc_url( home_url( '/about-us/' ) ); ?>">關於我們</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/my-account/' ) ); ?>">我的帳號</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/product-insurance-registration/' ) ); ?>">產品責任險與食登字號</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/shopping-guide/' ) ); ?>">購物說明與流程</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/faq/' ) ); ?>">常見問題 FAQ</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/shipping-policy/' ) ); ?>">配送與運費政策</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/refund-policy/' ) ); ?>">退換貨及退款服務</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>">隱私權保護政策</a></li>
                </ul>
            </div>

            <!-- Column 4: Social Widget -->
            <div class="footer-widget footer-social-col">
                <h3>官方粉絲專頁</h3>
                <!-- Facebook Page Plugin Iframe (hide_cover=true, show_facepile=false, small_header=true) -->
                <div class="fb-page-wrapper">
                    <iframe src="https://www.facebook.com/plugins/page.php?href=https%3A%2F%2Fwww.facebook.com%2Fckcfood%2F&tabs&width=340&height=154&small_header=false&adapt_container_width=true&hide_cover=true&show_facepile=false&appId" width="100%" height="154" style="border:none;border-radius:8px;background:white;width:100%;max-width:340px;height:154px;display:block;" scrolling="no" frameborder="0" allowfullscreen="true" allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share"></iframe>
                </div>
                <!-- Social media SVGs with circular backgrounds -->
                <div class="footer-social-icons" style="display: flex; gap: 10px;">
                    <a href="https://www.facebook.com/ckcfood/" target="_blank" class="social-circle fb" title="Facebook" style="background-color: #999999; border: none; color: #ffffff;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"/></svg>
                    </a>
                    <a href="https://www.instagram.com/ckc_banquet/" target="_blank" class="social-circle ig" title="Instagram" style="background-color: #999999; border: none; color: #ffffff;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
                    </a>
                    <a href="https://line.me/R/ti/p/@rsh5501l" target="_blank" class="social-circle ln" title="Line" style="background-color: #999999; border: none; color: #ffffff;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2c5.522 0 10 3.978 10 8.878 0 4.364-3.55 8.046-8.348 8.756-.374.08-.88.252-1.008.574-.116.29-.074.744-.036 1.036l.134.81c.046.29.214 1.136-1.008.618-1.222-.516-6.596-3.896-8.996-6.66-1.658-1.822-2.746-3.664-2.746-5.714 0-4.9 4.478-8.878 10-8.878z"/></svg>
                    </a>
                    <a href="https://www.youtube.com/@ckcgroup" target="_blank" class="social-circle yt" title="YouTube" style="background-color: #999999; border: none; color: #ffffff;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.163a3.003 3.003 0 0 0-2.11-2.108C19.524 3.545 12 3.545 12 3.545s-7.525 0-9.388.51A3.002 3.002 0 0 0 .502 6.163C0 8.07 0 12 0 12s0 3.93.502 5.837a3.003 3.003 0 0 0 2.11 2.108c1.863.51 9.388.51 9.388.51s7.525 0 9.388-.51a3.002 3.002 0 0 0 2.11-2.108C24 15.93 24 12 24 12s0-3.93-.502-5.837zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                    </a>
                </div>
            </div>
        </div>

        <div class="container footer-bottom">
            <div class="copyright">
                &copy; <?php echo esc_html( date( 'Y' ) ); ?> 潮港城餐飲集團. 版權所有。
            </div>
            
            <!-- Payment Icons -->
            <div class="payment-methods" style="display: flex; gap: 10px; align-items: center;">
                <span style="font-size: 11px; color: var(--text-muted); margin-right: 5px;">安全支付：</span>
                <span style="background: var(--light-bg); padding: 3px 8px; border-radius: 4px; color: var(--text-dark); font-size: 10px; font-weight: 700; border: 1px solid var(--border-color);">VISA</span>
                <span style="background: var(--light-bg); padding: 3px 8px; border-radius: 4px; color: var(--text-dark); font-size: 10px; font-weight: 700; border: 1px solid var(--border-color);">MasterCard</span>
                <span style="background: var(--light-bg); padding: 3px 8px; border-radius: 4px; color: var(--text-dark); font-size: 10px; font-weight: 700; border: 1px solid var(--border-color);">JCB</span>
                <span style="background: var(--light-bg); padding: 3px 8px; border-radius: 4px; color: #06c755; font-size: 10px; font-weight: 700; border: 1px solid var(--border-color);">LinePay</span>
            </div>
        </div>
    </footer>
</div><!-- #page -->

<?php wp_footer(); ?>
</body>
</html>
