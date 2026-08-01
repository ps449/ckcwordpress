<?php
/**
 * 網站 Logo 後台替換功能
 *
 * 背景：原本 header.php 的 Logo（桌機版、手機版各一處）都是直接寫死引用
 * 佈景主題內建的 /assets/images/logo.png，要換 Logo 必須改程式碼、重新
 * 部署，一般管理員無法自行處理。
 *
 * 這裡新增一個「網站內容」分類底下的設定頁，讓管理員可以直接在後台用
 * WordPress 媒體庫上傳／選擇圖片來替換 Logo，顯示尺寸統一為 80×80
 * （正方形），沒有另外上傳時維持原本內建的 logo.png，不影響尚未設定過
 * 的情況。
 *
 * @package Chao_Gang_Cheng
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 1. 註冊 80×80 的圖片尺寸，上傳的 Logo 圖片會自動裁切出這個尺寸，
 *    避免管理員上傳過大的原圖時前台還要下載、縮放整張大圖。
 */
add_action( 'after_setup_theme', 'ckc_register_site_logo_image_size' );
function ckc_register_site_logo_image_size() {
	add_image_size( 'ckc-site-logo', 80, 80, true );
}

/**
 * 2. 後台選單：移到「網站內容」分類區塊，緊接在「快捷列設定」（位置 33）
 *    之後，位置 34。
 */
add_action( 'admin_menu', 'ckc_site_logo_add_menu' );
function ckc_site_logo_add_menu() {
	add_menu_page(
		'Logo 設定',
		'Logo 設定',
		'manage_options',
		'ckc-site-logo',
		'ckc_site_logo_page_html',
		'dashicons-format-image',
		34
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
 * @param string $size 圖片尺寸，預設用裁切好的 80×80 版本。
 * @return string
 */
function ckc_get_site_logo_url( $size = 'ckc-site-logo' ) {
	$logo_id = absint( get_option( 'chao_gang_cheng_site_logo_id', 0 ) );
	if ( $logo_id ) {
		$url = wp_get_attachment_image_url( $logo_id, $size );
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
	$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'ckc-site-logo' ) : '';
	$is_custom = $logo_id && $logo_url;
	$preview_url = $is_custom ? $logo_url : ( get_template_directory_uri() . '/assets/images/logo.png' );
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline">Logo 設定</h1>
		<hr class="wp-header-end">

		<p style="max-width:640px;color:#555;">
			替換前台頁首（桌機版）的品牌 Logo。顯示尺寸統一為 80×80 正方形，
			建議上傳正方形圖片（PNG，建議帶透明背景），系統會自動裁切成
			80×80 顯示，不需要自己先裁好。留空則使用系統預設 Logo。
		</p>

		<form method="post" action="options.php" style="max-width:640px;margin-top:20px;">
			<?php settings_fields( 'ckc_site_logo_group' ); ?>

			<input type="hidden" name="chao_gang_cheng_site_logo_id" id="ckc_site_logo_id" value="<?php echo esc_attr( $logo_id ); ?>">

			<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:24px;">
				<div class="ckc-site-logo-preview-wrap" style="margin-bottom:16px;display:flex;align-items:center;gap:16px;">
					<div style="width:80px;height:80px;border:1px solid #e2e2e2;border-radius:8px;display:flex;align-items:center;justify-content:center;background:#faf6f1;overflow:hidden;flex-shrink:0;">
						<img class="ckc-site-logo-preview" src="<?php echo esc_url( $preview_url ); ?>" alt="Logo 預覽" style="max-width:80px;max-height:80px;object-fit:contain;">
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
	if ( 'toplevel_page_ckc-site-logo' !== $hook ) {
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
