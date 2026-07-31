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

add_action( 'admin_enqueue_scripts', 'chao_gang_cheng_admin_ui_theme_assets', 20 );
/**
 * 全站後台載入統一風格 CSS。
 *
 * 刻意不限制 $hook，讓所有後台頁面（含 WordPress 核心與 WooCommerce 畫面）
 * 都套用一致的色彩與圖示系統，而非僅限自建頁面。
 */
function chao_gang_cheng_admin_ui_theme_assets( $hook ) {
	$css_path = get_template_directory() . '/assets/css/admin-theme.css';
	$version  = file_exists( $css_path ) ? filemtime( $css_path ) : '1.0';

	wp_enqueue_style(
		'chao-gang-cheng-admin-theme',
		get_template_directory_uri() . '/assets/css/admin-theme.css',
		array(),
		$version
	);
}
