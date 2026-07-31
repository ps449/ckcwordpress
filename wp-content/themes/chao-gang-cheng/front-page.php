<?php
/**
 * Theme Front Page Template
 *
 * 首頁改為「模塊化」渲染：實際顯示的區塊、順序、內容全部由後台
 * 「首頁編輯」頁面（includes/admin/homepage-builder.php）控制，
 * 這個檔案只負責：讀取模塊清單 → 依序呼叫對應的 render function。
 *
 * @package Chao_Gang_Cheng
 */

get_header();

$ckc_homepage_modules = function_exists( 'ckc_get_homepage_modules' ) ? ckc_get_homepage_modules() : array();

foreach ( $ckc_homepage_modules as $ckc_module ) {
    if ( empty( $ckc_module['enabled'] ) ) {
        continue;
    }
    $ckc_render_fn = 'ckc_render_module_' . $ckc_module['type'];
    if ( function_exists( $ckc_render_fn ) ) {
        $ckc_render_fn( isset( $ckc_module['settings'] ) ? $ckc_module['settings'] : array() );
    }
}
?>

<?php get_footer(); ?>
