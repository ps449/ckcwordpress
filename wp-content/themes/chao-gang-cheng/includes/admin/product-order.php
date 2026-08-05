<?php
/**
 * 商品「顯示排序」管理頁
 *
 * 背景：WooCommerce 本身其實已經內建「拖曳排序」功能（商品列表頁最上方
 * 「全部 / 已發佈 / 回收桶 / 排序」裡的「排序」連結），前台商品頁選擇
 * 「預設排序」時使用的就是這個 menu_order 欄位。但這個入口藏在原生商品
 * 列表頁裡、混雜著一堆欄位（貨號／庫存／價格／分類…），不容易被發現，
 * 也不是「電商營運」選單群組裡一個獨立、好懂的功能。
 *
 * 這裡新增一個獨立子選單「商品 > 顯示排序」，用縮圖＋名稱的簡潔卡片列表
 * 呈現，滑鼠拖曳即可調整順序。實際儲存邏輯直接沿用 WooCommerce 原生的
 * AJAX 端點（action=woocommerce_product_ordering，見
 * woocommerce/includes/class-wc-ajax.php 的 WC_AJAX::product_ordering()），
 * 不重新發明一套排序運算邏輯——這個端點已經處理好 menu_order 重新編號、
 * 快取清除、相關 hook 觸發等細節，直接沿用最穩妥。
 *
 * @package Chao_Gang_Cheng
 */

defined( 'ABSPATH' ) || exit;

/**
 * 修正前後台排序顯示不一致的問題：這個商店儲存的
 * woocommerce_default_catalog_orderby 選項值其實是 'popularity'
 * （依熱銷度排序），不是 'menu_order'（預設排序）。也就是說，顧客一開始
 * 打開商店頁／分類頁時，實際套用的排序方式跟這裡「顯示排序」工具拖曳
 * 調整的順序是兩回事——除非顧客自己手動把排序切換成「預設排序」。
 *
 * 這個 WooCommerce 版本（11.0.0）的後台「WooCommerce > 設定 > 商品」頁面
 * 已經不再提供「預設商品排序」欄位可以直接改，只能用這個 filter 蓋掉
 * 已儲存的選項值，強制全站預設排序方式固定為「預設排序」（menu_order），
 * 「顯示排序」工具調整的順序才會真正是顧客一開始看到的順序。
 */
add_filter( 'woocommerce_default_catalog_orderby', 'ckc_force_default_catalog_orderby_menu_order' );
function ckc_force_default_catalog_orderby_menu_order( $orderby ) {
	return 'menu_order';
}

add_action( 'admin_menu', 'ckc_product_order_add_menu', 12 );
/**
 * 在「商品」選單下新增「顯示排序」子選單。
 */
function ckc_product_order_add_menu() {
	add_submenu_page(
		'edit.php?post_type=product',
		'顯示排序',
		'顯示排序',
		'edit_products',
		'ckc-product-order',
		'ckc_product_order_page_html'
	);
}

add_action( 'admin_enqueue_scripts', 'ckc_product_order_enqueue_assets' );
/**
 * 只在「商品 > 顯示排序」這一頁才載入 jQuery UI Sortable，避免影響其他頁面。
 *
 * @param string $hook 目前後台頁面的 hook suffix。
 */
function ckc_product_order_enqueue_assets( $hook ) {
	if ( 'product_page_ckc-product-order' !== $hook ) {
		return;
	}

	wp_enqueue_script( 'jquery-ui-sortable' );

	// 沿用 WooCommerce 原生商品排序功能的同一個 AJAX 端點與 nonce
	// action 名稱（'product-ordering'），確保後端驗證能通過。
	wp_localize_script(
		'jquery-ui-sortable',
		'ckcProductOrderParams',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'product-ordering' ),
		)
	);
}

/**
 * 「顯示排序」頁面內容。
 *
 * 商品依分類分區顯示（分類的排列順序沿用 WooCommerce 內建的分類拖曳排序，
 * 也就是「商品 > 分類」頁面設定的順序）。分區只是方便尋找商品的視覺分組，
 * 底層仍然是同一份全站共用的 menu_order 排序：同一個商品若掛了多個分類，
 * 只會歸類顯示在第一個分類底下、不會重複出現；拖曳調整順序時也只在同一個
 * 分區內移動，不會把商品拖到別的分類分區去（不會影響商品的分類設定）。
 */
function ckc_product_order_page_html() {
	if ( ! current_user_can( 'edit_products' ) ) {
		wp_die( '您沒有權限存取此頁面。' );
	}

	$products = get_posts(
		array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
		)
	);

	// 分類順序沿用 WooCommerce 內建的分類拖曳排序（get_terms() 對 product_cat
	// 預設就是照這個順序，跟「商品 > 分類」頁面看到的順序一致）。
	$categories = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
		)
	);
	if ( is_wp_error( $categories ) ) {
		$categories = array();
	}

	$valid_cat_ids = wp_list_pluck( $categories, 'term_id' );
	$groups        = array(); // term_id => array of WP_Post
	$uncategorized = array();

	foreach ( $products as $product_post ) {
		$terms   = get_the_terms( $product_post->ID, 'product_cat' );
		$primary = null;

		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( in_array( $term->term_id, $valid_cat_ids, true ) ) {
					$primary = $term;
					break;
				}
			}
		}

		if ( $primary ) {
			if ( ! isset( $groups[ $primary->term_id ] ) ) {
				$groups[ $primary->term_id ] = array();
			}
			$groups[ $primary->term_id ][] = $product_post;
		} else {
			$uncategorized[] = $product_post;
		}
	}
	?>
	<div class="wrap" id="ckc-product-order-page">
		<h1 style="display:flex;align-items:center;gap:10px;">
			<span style="font-size:24px;">↕️</span>
			顯示排序
		</h1>
		<p style="color:#666;margin-top:4px;">
			用滑鼠拖曳調整商品順序，放開滑鼠後會立即儲存。這個順序就是前台商店頁／分類頁選擇「預設排序」時的商品顯示順序（WooCommerce 內建機制，跟商品編輯頁「發佈」欄位裡看到的順序共用），全站只有一份，不分類各自獨立。
			<br>
			下面依商品分類分區顯示，方便尋找；分區順序跟「商品 &gt; 分類」頁面設定的順序一致，同一個商品只會出現在其中一個分類底下。
			<br>
			若顧客在前台手動切換成「熱銷度」「最新上架」「價格」等其他排序方式，則不受這裡的設定影響。
		</p>
		<hr style="margin:20px 0;">

		<?php if ( empty( $products ) ) : ?>
			<div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:24px 28px;">
				<p style="margin:0;color:#888;">目前沒有已發佈的商品可以排序。</p>
			</div>
		<?php else : ?>
			<div id="ckc-product-order-status" style="display:none;margin-bottom:14px;padding:10px 16px;border-radius:8px;font-size:13px;font-weight:600;max-width:640px;"></div>

			<?php
			foreach ( $categories as $cat ) :
				if ( empty( $groups[ $cat->term_id ] ) ) {
					continue;
				}
				?>
				<h2 style="font-size:15px;margin:26px 0 10px;color:#333;"><?php echo esc_html( $cat->name ); ?></h2>
				<ul class="ckc-product-order-list" style="list-style:none;margin:0;padding:0;max-width:640px;">
					<?php foreach ( $groups[ $cat->term_id ] as $product_post ) : ?>
						<?php ckc_product_order_render_item( $product_post ); ?>
					<?php endforeach; ?>
				</ul>
			<?php endforeach; ?>

			<?php if ( ! empty( $uncategorized ) ) : ?>
				<h2 style="font-size:15px;margin:26px 0 10px;color:#333;">未分類</h2>
				<ul class="ckc-product-order-list" style="list-style:none;margin:0;padding:0;max-width:640px;">
					<?php foreach ( $uncategorized as $product_post ) : ?>
						<?php ckc_product_order_render_item( $product_post ); ?>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		<?php endif; ?>
	</div>

	<script>
	jQuery( function ( $ ) {
		var $status = $( '#ckc-product-order-status' );

		if ( typeof ckcProductOrderParams === 'undefined' ) {
			return;
		}

		function showStatus( message, isError ) {
			$status
				.text( message )
				.css( {
					display: 'block',
					background: isError ? '#fbeaea' : '#eaf7ea',
					color: isError ? '#a94442' : '#2e7d32'
				} );
		}

		// 每個分類分區各自獨立一個 sortable 清單，只能在同一個分區內拖曳，
		// 不會拖到別的分類分區（分區只是顯示分組，不影響商品分類設定）。
		$( '.ckc-product-order-list' ).each( function () {
			var $list = $( this );

			$list.sortable( {
				items: '> li',
				cursor: 'move',
				axis: 'y',
				update: function ( event, ui ) {
					var $item  = ui.item;
					var id     = $item.data( 'id' );
					var $prev  = $item.prev();
					var $next  = $item.next();
					var previd = $prev.length ? $prev.data( 'id' ) : 0;
					var nextid = $next.length ? $next.data( 'id' ) : 0;

					$list.sortable( 'disable' );
					$item.css( 'opacity', '0.5' );

					$.post(
						ckcProductOrderParams.ajaxUrl,
						{
							action: 'woocommerce_product_ordering',
							security: ckcProductOrderParams.nonce,
							id: id,
							previd: previd,
							nextid: nextid
						}
					).done( function () {
						showStatus( '✅ 已儲存新順序', false );
					} ).fail( function () {
						showStatus( '⚠️ 儲存失敗，請重新整理頁面後再試一次', true );
					} ).always( function () {
						$item.css( 'opacity', '1' );
						$list.sortable( 'enable' );
					} );
				}
			} );
		} );
	} );
	</script>
	<?php
}

/**
 * 輸出單一商品在拖曳清單裡的一列（縮圖＋名稱）。
 *
 * @param WP_Post $product_post 商品文章物件。
 */
function ckc_product_order_render_item( $product_post ) {
	$thumb_url = get_the_post_thumbnail_url( $product_post->ID, 'thumbnail' );
	?>
	<li data-id="<?php echo esc_attr( $product_post->ID ); ?>"
		style="display:flex;align-items:center;gap:14px;background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:10px 16px;margin-bottom:8px;cursor:move;box-shadow:0 1px 3px rgba(0,0,0,.04);">
		<span class="dashicons dashicons-menu" style="color:#bbb;"></span>
		<span style="width:44px;height:44px;flex-shrink:0;border-radius:6px;overflow:hidden;background:#f4f4f4;display:flex;align-items:center;justify-content:center;">
			<?php if ( $thumb_url ) : ?>
				<img src="<?php echo esc_url( $thumb_url ); ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
			<?php else : ?>
				<span class="dashicons dashicons-format-image" style="color:#ccc;"></span>
			<?php endif; ?>
		</span>
		<span style="font-size:14px;color:#333;"><?php echo esc_html( $product_post->post_title ); ?></span>
	</li>
	<?php
}
