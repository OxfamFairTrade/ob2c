<?php
/**
 * Loop Add to Cart
 *
 * @see 	    https://docs.woocommerce.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version     3.3.0
 NM: Modified - Added page-include */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product, $nm_page_includes;

$nm_page_includes['products'] = true; // Required for the "Add to cart" element/shortcode

// GEWIJZIGD: Voeg product standaard per ompak toe bij B2B-klanten
if ( is_b2b_customer() ) {
	$multiple = intval( $product->get_attribute('ompak') );
	if ( $multiple < 2 ) {
		$multiple = 1;
	}
}

// GEWIJZIGD: Store locator triggeren op hoofdniveau
if ( is_main_site() ) {
	if ( strstr( $product->get_name(), 'Geschenkencheque' ) !== false ) {
		echo '<a href="https://www.oxfamwereldwinkels.be/cadeaubon-voor-eerlijke-producten/" class="button product_type_simple">Bestel online</a>';
	} elseif ( strstr( $product->get_name(), 'Oxfam Pakt Uit' ) !== false ) {
		echo '<a href="https://shop.oxfampaktuit.be/nl/koop-een-cadeau/" target="_blank" class="button product_type_simple">Bestel online</a>';
	} elseif ( $product->get_meta('_woonet_publish_to_23') === 'yes' ) {
		if ( $product->get_date_created()->date_i18n('Y-m-d') > date_i18n( 'Y-m-d', strtotime('-2 weeks') ) ) {
			//  Geef 2 weken buffer om lokale voorraad aan te leggen
			echo '<span class="soon-available">Weldra online beschikbaar</span>';
		} else {
			echo '<a href="#" class="button product_type_simple store-selector-open"></a>';
		}
	} else {
		// Het product wordt niet online verkocht (o.b.v. aanwezigheid in webshop Oostende als test case)
		echo '<span class="unavailable">Niet online beschikbaar</span>';
	}
} else {
	// GEWIJZIGD: Knop niet tonen bij voorraadstatus 'onbackorder'
	if ( $product->is_on_backorder() ) {
		echo 'Tijdelijk uitverkocht';
	} elseif ( $product->is_in_stock() ) {
		echo apply_filters( 'woocommerce_loop_add_to_cart_link',
			sprintf( '<a rel="nofollow" href="%s" data-quantity="%s" class="%s" %s>%s</a>',
				esc_url( $product->add_to_cart_url() ),
				esc_attr( isset( $multiple ) ? $multiple : ( isset( $args['quantity'] ) ? $args['quantity'] : 1 ) ),
				esc_attr( isset( $args['class'] ) ? $args['class'] : 'button' ),
				isset( $args['attributes'] ) ? wc_implode_html_attributes( $args['attributes'] ) : '',
				esc_html( $product->add_to_cart_text() )
			),
		$product, $args );
	} else {
		echo 'Niet in assortiment';
	}
}