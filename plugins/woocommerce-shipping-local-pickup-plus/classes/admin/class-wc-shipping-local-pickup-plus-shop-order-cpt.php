<?php
/**
 * WooCommerce Local Pickup Plus
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Local Pickup Plus to newer
 * versions in the future. If you wish to customize WooCommerce Local Pickup Plus for your
 * needs please refer to http://docs.woothemes.com/document/local-pickup-plus/
 *
 * @package     WC-Shipping-Local-Pickup-Plus
 * @author      SkyVerge
 * @copyright   Copyright (c) 2012-2017, SkyVerge, Inc.
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

/**
 * Local Pickup Plus order custom post type class
 *
 * Handles modifications to the shop order custom post type
 * on both View Orders list table and Edit Order screen
 *
 * TODO it's confusing to call this class CPT since we don't introduce a new CPT, add new general admin class then rename this one to _Admin_Orders in rewrite {FN 2016-09-21}
 *
 * @since 1.8.0
 */
class WC_Shipping_Local_Pickup_Plus_CPT {


	/**
	 * Add actions/filters for View Orders/Edit Order screen
	 *
	 * @since 1.8.0
	 */
	public function __construct() {

		// add 'Pickup Locations' orders page column header
		add_filter( 'manage_edit-shop_order_columns',        array( $this, 'render_pickup_locations_column_header' ), 20 );
		// add 'Pickup Locations' orders page column content
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_pickup_locations_column_content' ) );
		// add CSS to tweak the 'Pickup Locations' column
		add_action( 'admin_head',                            array( $this, 'render_pickup_locations_column_styles' ) );

		// Filter the keys that should be hidden from order edit screens.
		add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'filter_order_hidden_meta' ) );
	}


	/**
	 * Hide 'pickup_location' meta array from order edit screens.
	 *
	 * @internal
	 *
	 * @since 1.14.0
	 * @param $hidden_meta
	 * @return array
	 */
	public function filter_order_hidden_meta( $hidden_meta ) {

		if ( SV_WC_Plugin_Compatibility::is_wc_version_gte_3_0() ) {
			$hidden_meta[] = 'pickup_location';
		}

		return $hidden_meta;
	}


	/** Listable Columns ******************************************************/


	/**
	 * Adds 'Pickup Locations' column header to 'Orders' page
	 * immediately after 'Ship to' column
	 *
	 * @since 1.8.0
	 * @param array $columns
	 * @return array $new_columns
	 */
	public function render_pickup_locations_column_header( $columns ) {

		$new_columns = array();

		foreach ( $columns as $column_name => $column_info ) {

			$new_columns[ $column_name ] = $column_info;

			if ( 'shipping_address' === $column_name ) {

				$new_columns['pickup_locations'] = __( 'Pickup Location', 'woocommerce-shipping-local-pickup-plus' );
			}
		}

		return $new_columns;
	}


	/**
	 * Adds 'Pickup Locations' column content to 'Orders' page immediately after 'Order Status' column
	 *
	 * @since 1.8.0
	 * @param array $column name of column being displayed
	 */
	public function render_pickup_locations_column_content( $column ) {
		global $post;

		if ( 'pickup_locations' === $column ) {

			$order = wc_get_order( $post->ID );

			$pickup_locations = $this->get_order_pickup_locations( $order );

			foreach ( $pickup_locations as $pickup_location ) {
				
				$formatted_pickup_location = WC()->countries->get_formatted_address( array_merge( array( 'first_name' => null, 'last_name' => null, 'state' => null ), $pickup_location ) );

				if ( isset( $pickup_location['shipping_company'] ) && $pickup_location['shipping_company'] ) {
					// GEWIJZIGD: Enkel naam van afhaalpunt tonen en OWW afkorten => dit is een attribuut zonder shortcodes, dus tekstvervanging kan al
					$formatted_pickup_location = str_replace( 'Oxfam-Wereldwinkel', 'OWW', $pickup_location['shipping_company'] );
				}

				// GEWIJZIGD: Door gebruik van get_formatted_address() i.p.v. get_formatted_address_helper() zijn de shortcodes nog niet uitgevoerd
				echo esc_html( preg_replace( '#<br\s*/?>#i', ', ', do_shortcode($formatted_pickup_location) ) );
			}
		}
	}


	/**
	 * Adds CSS to style the 'Pickup Locations' column
	 *
	 * @since 1.10.1
	 */
	public function render_pickup_locations_column_styles() {

		$screen = get_current_screen();

		if ( 'edit-shop_order' === $screen->id ) :

			?>
			<style type="text/css">
				.widefat .column-pickup_locations {
					width: 12%;
				}
			</style>
			<?php

		endif;
	}


	/** Helper Methods ********************************************************/


	/**
	 * Gets any order pickup locations from the given order
	 *
	 * @since 1.8.0
	 * @param \WC_Order $order the order
	 * @return array of pickup locations, with country, postcode, state, city, address_2, address_1, company, phone, cost and id properties
	 */
	private function get_order_pickup_locations( $order ) {

		$pickup_locations = array();
		/** @type array|\WC_Order_Item_Shipping[] $shipping_methods */
		$shipping_methods = $order->get_shipping_methods();

		foreach ( $shipping_methods as $shipping_item ) {

			if ( SV_WC_Plugin_Compatibility::is_wc_version_gte_3_0() ) {

				$method_id       = $shipping_item->get_method_id();
				$pickup_location = $shipping_item->get_meta( 'pickup_location', true );

			} else {

				if ( ! isset( $shipping_item['pickup_location'] ) ) {
					continue;
				}

				$method_id       = $shipping_item['method_id'];
				$pickup_location = $shipping_item['pickup_location'];
			}

			if ( WC_Local_Pickup_Plus::METHOD_ID === $method_id && ! empty( $pickup_location ) ) {
				$pickup_locations[] = maybe_unserialize( $pickup_location );
			}
		}

		return $pickup_locations;
	}


} // end \WC_Shipping_Local_Pickup_Plus_CPT class
