<?php
/**
 * 網站 Logo 後台替換功能
 *
 * 背景：原本 header.php 的 Logo（桌機版、手機版各一處）都是直接寫死引用
 * 佈景主題內建的 /assets/images/logo.png，要換 Logo 必須改程式碼、重新
 * 部署，一般管理員無法自行處理。
 *
 * 這裡新增一個「網站內容」分類底下、「首頁」頂層選單裡的子選單設定頁，
 * 讓管理員可以直接在後台用 WordPress 媒體庫上傳／選擇圖片來替換 Logo，
 * 顯示尺寸統一為 240×80（橫式），沒有另外上傳時維持原本內建的
 * logo.png，不影響尚未設定過的情況。
 *
 * @package Chao_Gang_Cheng
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 1.（已移除）原本這裡註冊了 ckc-site-logo 這個 240×80 裁切尺寸，但
 * WordPress 的裁切尺寸只在上傳當下依「當時」註冊的數字產生一次，之後
 * 改動尺寸不會自動幫已上傳過的圖重新裁切——實測踩到這個坑：把尺寸從
 * 80×80 改成 240×80 後，先前上傳、套用過的 Logo 仍沿用舊的 80×80
 * 裁切檔，被圖片 CDN 依 <img> 上寫的 240×80 硬拉伸，畫面整個放大模糊。
 * 改成一律使用原始圖檔（見下面 ckc_get_site_logo_url()），前台
 * width/height + object-fit:contain 自己負責等比縮放，不再需要、也
 * 不再註冊這個容易過期的裁切尺寸。
 */

/**
 * 2. 後台選單：收整到「首頁」頂層選單（ckc-homepage-builder，見
 *    homepage-builder.php）底下的子選單，用 admin_menu 優先權 15
 *    確保排在子選單列表最後一位（跟其他 5 個首頁相關設定並列）。
 */
add_action( 'admin_menu', 'ckc_site_logo_add_menu', 15 );
function ckc_site_logo_add_menu() {
	// 注意：不能直接寫死猜測 hook suffix 字串（例如 'ckc-homepage-builder_page_ckc-site-logo'）。
	// 父選單「首頁」的選單標題含中文，WordPress 組出來的 hook suffix 其實是
	// 「未經處理的中文選單標題_page_ckc-site-logo」，不是父選單的英文 slug——
	// 實測踩到這個坑：用猜測字串比對永遠對不上，導致 wp_enqueue_media() 跟
	// 圖片選擇器 JS 完全沒載入。改用 add_submenu_page() 的回傳值（真正的
	// hook suffix）存起來，在 ckc_site_logo_admin_assets() 精準比對。
	$GLOBALS['ckc_site_logo_hook'] = add_submenu_page(
		'ckc-homepage-builder',
		'Logo 設定',
		'Logo 設定',
		'manage_options',
		'ckc-site-logo',
		'ckc_site_logo_page_html'
	);
}

/**
 * 3. 向 WordPress Settings API 註冊設定（儲存上傳圖片的附件 ID）
 */
add_action( 'admin_init', 'ckc_site_logo_register_settings' );
function ckc_site_logo_register_settings() {
	register_setting(
		'ckc_site_logo_group',
		'chao_gang_cheng_site_logo_id',
		array(
			'sanitize_callback' => 'absint',
			'default'           => 0,
		)
	);
}

/**
 * 4. 取得目前應該使用的 Logo 網址（給 header.php 呼叫）。
 *
 * 注意：這裡刻意回傳「原始圖檔」（wp_get_attachment_url），不透過
 * wp_get_attachment_image_url() 取 ckc-site-logo 這個裁切尺寸。原因：
 * WordPress 的裁切尺寸只在「上傳當下」依照當時註冊的尺寸產生一次，
 * 之後改了 add_image_size() 的數字並不會自動幫已經上傳過的圖重新裁切。
 * 實測發現：先前把 ckc-site-logo 從 80×80 改成 240×80 後，某張在改動
 * 之前就上傳、套用過的 Logo 仍然沿用舊的 80×80 裁切檔，被 WordPress.com
 * 的圖片 CDN 依 <img> 上寫的 240×80 硬拉伸／裁切，畫面整個放大模糊、
 * 字被裁掉一角。改回吃原始圖檔，讓前台 <img> 的 width/height +
 * object-fit:contain 自己負責等比縮放，就不會再受裁切尺寸是否過期影響。
 *
 * @return string
 */
function ckc_get_site_logo_url() {
	$logo_id = absint( get_option( 'chao_gang_cheng_site_logo_id', 0 ) );
	if ( $logo_id ) {
		$url = wp_get_attachment_url( $logo_id );
		if ( $url ) {
			return $url;
		}
	}
	// 沒有設定，或附件已被刪除：沿用佈景主題內建的預設 Logo。
	return get_template_directory_uri() . '/assets/images/logo.png';
}

/**
 * 5. 設定頁面 HTML
 */
function ckc_site_logo_page_html() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$logo_id  = absint( get_option( 'chao_gang_cheng_site_logo_id', 0 ) );
	$logo_url = $logo_id ? wp_get_attachment_url( $logo_id ) : '';
	$is_custom = $logo_id && $logo_url;
	$preview_url = $is_custom ? $logo_url : ( get_template_directory_uri() . '/assets/images/logo.png' );
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline">Logo 設定</h1>
		<hr class="wp-header-end">

		<p style="max-width:640px;color:#555;">
			替換前台頁首（桌機版）的品牌 Logo。顯示尺寸統一為 240×80（橫式），
			建議上傳長寬比接近 3:1 的橫式圖片（PNG，建議帶透明背景），系統會
			自動裁切成 240×80 顯示，不需要自己先裁好。留空則使用系統預設 Logo。
		</p>

		<form method="post" action="options.php" style="max-width:640px;margin-top:20px;">
			<?php settings_fields( 'ckc_site_logo_group' ); ?>

			<input type="hidden" name="chao_gang_cheng_site_logo_id" id="ckc_site_logo_id" value="<?php echo esc_attr( $logo_id ); ?>">

			<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:24px;">
				<div class="ckc-site-logo-preview-wrap" style="margin-bottom:16px;display:flex;align-items:center;gap:16px;">
					<div style="width:240px;height:80px;border:1px solid #e2e2e2;border-radius:8px;display:flex;align-items:center;justify-content:center;background:#faf6f1;overflow:hidden;flex-shrink:0;">
						<img class="ckc-site-logo-preview" src="<?php echo esc_url( $preview_url ); ?>" alt="Logo 預覽" style="max-width:240px;max-height:80px;object-fit:contain;">
					</div>
					<p style="margin:0;color:#888;font-size:13px;">
						<?php echo $is_custom ? '目前使用自訂 Logo' : '目前使用系統預設 Logo'; ?>
					</p>
				</div>

				<p>
					<button type="button" class="button ckc-site-logo-pick">選擇圖片</button>
					<button type="button" class="button ckc-site-logo-clear" style="<?php echo $is_custom ? '' : 'display:none;'; ?>">還原為預設 Logo</button>
				</p>
			</div>

			<?php submit_button( '儲存變更' ); ?>
		</form>
	</div>
	<?php
}

/**
 * 6. 後台圖片選擇器 JS（wp.media），只在這個設定頁載入。
 */
add_action( 'admin_enqueue_scripts', 'ckc_site_logo_admin_assets' );
function ckc_site_logo_admin_assets( $hook ) {
	if ( empty( $GLOBALS['ckc_site_logo_hook'] ) || $GLOBALS['ckc_site_logo_hook'] !== $hook ) {
		return;
	}

	wp_enqueue_media();

	$default_logo_url = esc_url_raw( get_template_directory_uri() . '/assets/images/logo.png' );

	$js = <<<JS
	jQuery(function(\$){
		\$('.ckc-site-logo-pick').on('click', function(e){
			e.preventDefault();
			var frame = wp.media({ title: '選擇 Logo 圖片', multiple: false, library: { type: 'image' } });
			frame.on('select', function(){
				var att = frame.state().get('selection').first().toJSON();
				\$('#ckc_site_logo_id').val(att.id);
				\$('.ckc-site-logo-preview').attr('src', att.url);
				\$('.ckc-site-logo-preview-wrap p').text('目前使用自訂 Logo');
				\$('.ckc-site-logo-clear').show();
			});
			frame.open();
		});
		\$('.ckc-site-logo-clear').on('click', function(e){
			e.preventDefault();
			\$('#ckc_site_logo_id').val('0');
			\$('.ckc-site-logo-preview').attr('src', '{$default_logo_url}');
			\$('.ckc-site-logo-preview-wrap p').text('目前使用系統預設 Logo');
			\$(this).hide();
		});
	});
JS;

	wp_register_script( 'ckc-site-logo-admin', false, array( 'jquery', 'media-editor' ), '1.0', true );
	wp_enqueue_script( 'ckc-site-logo-admin' );
	wp_add_inline_script( 'ckc-site-logo-admin', $js );
}
