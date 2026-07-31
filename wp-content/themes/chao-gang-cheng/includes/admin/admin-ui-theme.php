<?php
/**
 * 後台 UI/UX 全站統一風格
 *
 * 目標：簡約商務、直覺清晰、色彩與圖示系統統一，套用與前台一致的品牌色系
 * （--primary-color / --accent-color，見 style.css）。純視覺層 CSS，
 * 透過 admin_enqueue_scripts 掛在「所有」後台頁面（不限自建頁面），
 * 涵蓋 WordPress 核心畫面（選單、儀表板、列表）與 WooCommerce 畫面。
 *
 * @package Chao_Gang_Cheng
 */

defined( 'ABSPATH' ) || exit;

/**
 * 全站後台載入統一風格 CSS。
 *
 * 刻意不限制 $hook，讓所有後台頁面（含 WordPress 核心與 WooCommerce 畫面）
 * 都套用一致的色彩與圖示系統，而非僅限自建頁面。
 *
 * 注意：這裡刻意不用 wp_enqueue_style() 掛在 admin_enqueue_scripts，改成在
 * admin_head 用很晚的 priority（999）直接輸出 <link> 標籤。原因：
 * 1) WordPress.com Atomic 主機會透過內建的資源合併機制（page-optimize／
 *    _static/?? 合併請求）把多個以 wp_enqueue_style() 註冊的樣式表打包、
 *    重新排序，實測發現我們的樣式表被併進較早輸出的合併請求，導致排在
 *    WordPress 核心「後台配色」樣式表（colors/modern/colors.min.css 等，
 *    同樣使用 !important）之前，被其覆蓋、視覺上完全沒有作用。
 * 2) 改用 admin_head（比 admin_enqueue_scripts 觸發時機更晚，且不經過
 *    合併機制）直接輸出 <link>，可確保這份樣式表在整個 <head> 的「最後面」
 *    載入——同層級 !important 規則以後出現者為準，藉此穩定覆蓋核心配色。
 */
add_action( 'admin_head', 'chao_gang_cheng_admin_ui_theme_assets', 999 );
function chao_gang_cheng_admin_ui_theme_assets() {
	$css_path = get_template_directory() . '/assets/css/admin-theme.css';
	$version  = file_exists( $css_path ) ? filemtime( $css_path ) : '1.0';
	$css_url  = get_template_directory_uri() . '/assets/css/admin-theme.css';

	printf(
		'<link rel="stylesheet" id="chao-gang-cheng-admin-theme-css" href="%s" media="all" />' . "\n",
		esc_url( add_query_arg( 'ver', $version, $css_url ) )
	);
}
