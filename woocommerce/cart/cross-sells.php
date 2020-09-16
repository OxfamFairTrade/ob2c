<?php
/**
 * Cross-sells
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 4.4.0
NM: Modified */

defined( 'ABSPATH' ) || exit;

global $woocommerce_loop, $nm_theme_options;

$woocommerce_loop['name']           = 'cross-sells';
$woocommerce_loop['columns']        = apply_filters( 'woocommerce_cross_sells_columns', $nm_theme_options['product_upsell_related_columns'] );
$woocommerce_loop['columns_xsmall'] = '2';
$woocommerce_loop['columns_small']  = '2';
$woocommerce_loop['columns_medium'] = '4';

if ( $cross_sells ) : ?>

	<div class="container">
		<div class="col-row">
			<div class="cross-sells col-md-12">
				<!-- GEWIJZIGD: Vaste titel -->
				<h2>Mogelijk ook interessant voor jou</h2>

				<!-- GEWIJZIGD: Vervangen door slider? -->
				<?php woocommerce_product_loop_start(); ?>

				<?php foreach ( $cross_sells as $cross_sell ) : ?>

					<?php
						$post_object = get_post( $cross_sell->get_id() );
						setup_postdata( $GLOBALS['post'] =& $post_object );
						wc_get_template_part( 'content', 'product' );
					?>

				<?php endforeach; ?>

				<?php woocommerce_product_loop_end(); ?>

			</div>
		</div>
	</div>

<?php endif;

wp_reset_postdata();
