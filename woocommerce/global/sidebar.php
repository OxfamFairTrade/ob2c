<?php
/**
 * Sidebar
 *
 * @see 	    https://docs.woocommerce.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version     1.6.4
NM: Modified */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<div class="nm-shop-sidebar-col col-md-3 col-sm-12">
	<div id="nm-shop-sidebar" class="nm-shop-sidebar" data-sidebar-layout="default">
		<?php
			// Meegeven van argumenten vereist WP 5.5+
			get_template_part( 'template-parts/store-selector/current', NULL, array( 'context' => 'sidebar' ) );
		?>

		<div class="catalog-filters">
			<div class="nm-shop-widget-col">
				<h3 class="nm-widget-title">In de kijker</h3>
				<?php
					$args = array(
						'stock_status' => 'instock',
						'include' => wc_get_product_ids_on_sale(),
					);
					$sale_products = wc_get_products( $args );
					if ( count( $sale_products ) > 0 ) {
						// Of toch liever iets proberen m.b.v. add_query_arg( 'filter_product_tag', 'promotie' ) / remove_query_arg( 'filter_product_tag' ) ?
						// Maar wordt dan nog altijd niet automatisch opgenomen in actieve filters!
						$term_link = get_term_link( 'promotie', 'product_tag' );
						if ( ! is_wp_error( $term_link ) ) {
							if ( is_product_tag('promotie') ) {
								$class = 'chosen';
								$url = get_permalink( wc_get_page_id('shop') );
							} else {
								$class = '';
								$url = $term_link.'#nm-shop-products';
							}
							echo '<a href="'.$url.'" class="'.$class.'"><span>Promoties</span></a>';
						}
					}
					
					$term_link = get_term_link( 'sinterklaas', 'product_tag' );
					if ( ! is_wp_error( $term_link ) ) {
						if ( is_product_tag('sinterklaas') ) {
							$class = 'chosen';
							$url = get_permalink( wc_get_page_id('shop') );
						} else {
							$class = '';
							$url = $term_link.'#nm-shop-products';
						}
						echo '<a href="'.$url.'" class="'.$class.'"><span>Sinterklaas</span></a>';
					}
				?>
			</div>

			<ul id="nm-shop-widgets-ul">
				<?php
					// Toon expliciet bepaalde widgets in plaats van sidebar 'widgets-shop' op te roepen
					// Voordeel: instellingen moeten niet gesynchroniseerd worden over de webshops heen!
					// Nadeel: zelfde code moet toegevoegd worden aan woocommerce/ajax/shop-full.php voor AJAX-reload
					
					// Zie https://developer.wordpress.org/reference/functions/the_widget/
					$args = array(
						'before_widget' => '<li class="widget %s">',
						'after_widget' => '</li>',
						'before_title' => '<h3 class="nm-widget-title">',
						'after_title' => '</h3><span class="reset-filters"><a href="'.get_permalink( wc_get_page_id('shop') ).'">Wis alle filters</a></span>',
					);
					the_widget( 'WC_Widget_Layered_Nav_Filters', array(), $args );
					$args['after_title'] = '</h3>';
					the_widget( 'WC_Widget_Product_Categories', array( 'title' => 'Categorieën', 'orderby' => 'order', 'show_children_only' => 1 ), $args );
					the_widget( 'WC_Widget_Layered_Nav', array( 'title' => 'Voedingsvoorkeuren', 'attribute' => 'preferences' ), $args );
					
					if ( is_main_site() ) {
						// Duidelijk een probleem met het tellen van de termen ...
						// var_dump_pre( get_terms( 'pa_countries', array( 'hide_empty' => '0' ) ) );
						// _wc_term_recount( get_terms( 'pa_countries', array( 'hide_empty' => '0' ) ), get_taxonomy('pa_countries'), true, false );
						the_widget( 'WC_Widget_Layered_Nav', array( 'title' => 'Herkomstland', 'attribute' => 'countries', 'display_type' => 'dropdown' ), $args );
					}
				?>
			</ul>

			<div class="mobile-only"><span class="btn close-filter">Sluiten</span></div>
		</div>

		<?php the_widget( 'WC_Widget_Recently_Viewed', array( 'title' => 'Laatst bekeken', 'number' => 4 ) ); ?>

		<div class="nm-shop-widget-col">
			<span class="btn toggle-filter">Filteren</span>
		</div>
	</div>
</div>
