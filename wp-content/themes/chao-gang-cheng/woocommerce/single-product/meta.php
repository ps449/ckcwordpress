<?php
/**
 * Single Product Meta (Custom Override with Brand + Tags)
 *
 * @package Chao_Gang_Cheng
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;
?>
<div class="product_meta">

	<?php do_action( 'woocommerce_product_meta_start' ); ?>

	<?php if ( wc_product_sku_enabled() && ( $product->get_sku() || $product->is_type( 'variable' ) ) ) : ?>

		<span class="sku_wrapper">商品編號： <span class="sku"><?php echo ( $sku = $product->get_sku() ) ? $sku : '無'; ?></span></span>

	<?php endif; ?>

	<?php
	// Brand (product_brand taxonomy)
	$brand_terms = get_the_terms( $product->get_id(), 'product_brand' );
	if ( $brand_terms && ! is_wp_error( $brand_terms ) ) {
		$brand_links = array();
		foreach ( $brand_terms as $term ) {
			$brand_links[] = '<a href="' . esc_url( get_term_link( $term ) ) . '" rel="tag">' . esc_html( $term->name ) . '</a>';
		}
		echo '<span class="posted_in brand_in">' . esc_html__( '品牌：', 'woocommerce' ) . implode( ', ', $brand_links ) . '</span>';
	}
	?>

	<?php echo wc_get_product_category_list( $product->get_id(), ', ', '<span class="posted_in">' . _n( '分類：', '分類：', count( $product->get_category_ids() ), 'woocommerce' ) . ' ', '</span>' ); ?>

	<?php echo wc_get_product_tag_list( $product->get_id(), ', ', '<span class="tagged_as">' . _n( '標籤：', '標籤：', count( $product->get_tag_ids() ), 'woocommerce' ) . ' ', '</span>' ); ?>

	<?php do_action( 'woocommerce_product_meta_end' ); ?>

</div>
