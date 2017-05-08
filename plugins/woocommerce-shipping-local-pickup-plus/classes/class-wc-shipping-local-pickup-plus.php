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
 * The Local Pickup Plus shipping method
 *
 * @since 1.4
 */
class WC_Shipping_Local_Pickup_Plus extends WC_Shipping_Method {


	/** Option name for the pickup locations setting */
	const PICKUP_LOCATIONS_OPTION = 'woocommerce_pickup_locations';

	/** @var float optional cost */
	private $cost;

	/** @var float discount amount for shipping via this method */
	private $discount;

	/** @var array Categories of products which can only be locally picked up */
	private $categories;

	/** @var boolean When enabled, only the categories specified can be locally picked up, all other products must be shipped */
	private $categories_pickup_only;

	/** @var array of pickup locations */
	private $pickup_locations;

	/** @var string When enabled, the pickup location address will be used to calculate tax rather than the customer shipping address.  One of 'yes' or 'no' */
	private $apply_pickup_location_tax;

	/** @var string when enabled the "shipping address" will be hidden during checkout if local pickup plus is enabled.  One of 'yes' or 'no' */
	private $hide_shipping_address;

	/** @var string pickup location styling on checkout, one of 'select' or 'radio' */
	private $checkout_pickup_location_styling;

	/** @var array association between a cart item and order item */
	private $cart_item_to_order_item = array();

	/** @var string Admin page description text */
	private $admin_page_description = '';


	/**
	 * Initialize the local pickup plus shipping method class
	 *
	 * @since 1.4
	 */
	public function __construct() {

		if ( SV_WC_Plugin_Compatibility::is_wc_version_gte_2_6() ) {
			// only WC 2.6 introduces the __construct() method on the class parent
			parent::__construct();
		}

		$this->id           = WC_Local_Pickup_Plus::METHOD_ID;
		$this->method_title = __( 'Local Pickup Plus', 'woocommerce-shipping-local-pickup-plus' );

		$this->admin_page_description  = __( 'Local pickup is a simple method which allows the customer to pick up their order themselves at a specified pickup location.', 'woocommerce-shipping-local-pickup-plus' );

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables
		foreach ( $this->settings as $setting_key => $setting ) {
			$this->$setting_key = $setting;
		}

		// Load pickup locations
		$this->load_pickup_locations();

		// add actions
		if ( $this->is_available( array() ) ) {

			add_action( 'woocommerce_after_template_part', array( $this, 'review_order_shipping_pickup_location' ), 10, 4 );

	        if ( SV_WC_Plugin_Compatibility::is_wc_version_gte_3_0() ) {
		        add_action( 'woocommerce_new_order_item',          array( $this, 'associate_order_item_to_cart_item' ), 10, 3 );
		        add_action( 'woocommerce_new_order_item',          array( $this, 'checkout_new_shipping_order_item' ), 15, 3 );
	        } else {
		        add_action( 'woocommerce_add_order_item_meta',     array( $this, 'associate_order_item_to_cart_item' ), 10, 3 );
				add_action( 'woocommerce_add_shipping_order_item', array( $this, 'checkout_add_shipping_order_item' ),  10, 3 );
	        }

			// add local pickup location selection row to recurring shipping rates tables in Subscriptions v2.0.18+
			add_action( 'woocommerce_subscriptions_after_recurring_shipping_rates', array( $this, 'subscription_review_recurrent_shipping_pickup_location' ), 10, 4 );
			// prevents Subscriptions firing a notice if a discount is applied to a cart containing a subscription
			add_filter( 'woocommerce_subscriptions_validate_coupon_type',           array( $this, 'validate_subscription_pickup_coupon' ), 10, 2 );

			// discount display handling (coupon)
			add_filter( 'woocommerce_cart_totals_coupon_label', array( $this, 'coupon_label' ) );
			add_filter( 'woocommerce_cart_totals_coupon_html',  array( $this, 'coupon_remove_label' ), 10, 2 );
			add_filter( 'woocommerce_coupon_message',           array( $this, 'coupon_remove_message' ), 10, 3 );

			// add the local pickup location to the shipping package so that changing it forces a recalculation
			add_filter( 'woocommerce_cart_shipping_packages', array( $this, 'update_shipping_packages' ) );

			// remove duplicate costs for split packages at same location - WC 2.6+
			add_filter( 'woocommerce_shipping_packages', array( $this, 'remove_duplicate_cost_for_split_packages_at_same_location' ) );

			// use the pickup location as the taxable address
			add_filter( 'woocommerce_customer_taxable_address', array( $this, 'taxable_address' ) );

			add_filter( 'woocommerce_per_product_shipping_skip_free_method_local_pickup_plus', array( $this, 'per_product_shipping_skip_free_method' ) );
		}

		// TODO split the following in a separate admin class in rewrite {FN 2016-09-21}

		// add admin actions
		if ( is_admin() ) {

			// admin Order Edit page render pickup location data
			add_action( 'woocommerce_admin_order_data_after_shipping_address',   array( $this, 'admin_order_pickup_location' ) );

			// save shipping method options
			add_action( 'woocommerce_update_options_shipping_local_pickup_plus', array( $this, 'process_admin_options' ) );

			add_filter( 'woocommerce_hidden_order_itemmeta',                     array( $this, 'admin_order_hide_itemmeta' ) );
		}

		do_action( 'wc_shipping_local_pickup_plus_init', $this );
	}


	/**
	 * Calculate shipping
	 *
	 * This is called only when the shipping package
	 * (which includes the order items) is not found in the transient storage.
	 *
	 * "Overridden" from WC_Shipping_Method, although, in pre-WC 2.6, that class
	 * doesn't actually define this method, it's assumed to exist by the client code.
	 *
	 * @param array $package Order data used to calculate shipping, including order items
	 */
	public function calculate_shipping( $package = array() ) {

		$label       = $this->title;
		$location_id = isset( $package['pickup_location'] ) && is_numeric( $package['pickup_location'] ) ? $package['pickup_location'] : null;
		$location    = is_numeric( $location_id ) ? $this->get_pickup_location_by_id( $location_id ) : null;
		$discount    = $this->get_discount();

		// pickup location selected?
		if ( $location ) {

			$cost = $this->get_cost_for_location( $location );

		// otherwise, get an exact or 'from' amounts
		} else {

			$range = $this->get_location_cost_range();
			$cost  = $range[0];

			// the cost is a range
			if ( $cost && $range[0] !== $range[1] ) {
				$label .= ' ' . __( 'from', 'woocommerce-shipping-local-pickup-plus' );
			}
		}

		// if there's a discount for local pickup,
		// add a note to the displayed shipping method title
		if ( (bool) $discount ) {

			// If it's numeric just show the fixed price, or show a percentage.
			$discount_display = is_numeric( $discount ) ? wc_price( $discount ) : $discount;

			/* translators: Placeholder: %s - discount amount when using Local Pickup Plus shipping method */
			$label .= ' (' . sprintf( _x( 'save %s', 'Discount amount', 'woocommerce-shipping-local-pickup-plus' ), strip_tags( $discount_display ) ) . ')';
		}

		$rate = array(
			'id'       => $this->id,
			'label'    => $label,
			'cost'     => (float) $cost,
			'calc_tax' => 'per_order',
		);

		$this->add_rate( $rate );
	}


	/**
	 * Returns true if this gateway is available,
	 * under the following conditions:
	 *
	 * - is enabled
	 * - one or more pickup locations defined
	 * - if categories pickup only is enabled, there must be at least one product
	 *   from the category in the cart
	 *
	 * Overridden from parent class
	 *
	 * @param array $package Array of order data, including order items
	 * @return bool
	 */
	public function is_available( $package ) {

		if ( ! $this->is_enabled() || 0 === count( $this->pickup_locations ) ) {
			return false;
		}

		if ( 'yes' === $this->categories_pickup_only && ! empty( $this->categories ) && ! empty( $package ) && isset( $package['contents'] ) ) {

			list( $found_products, $other_products ) = $this->get_products_by_allowed_category( $package['contents'] );

			// there's non-pickup products, and no pickup products,
			// so disable this shipping method for this package
			if ( count( $other_products ) > 0 && 0 === count( $found_products ) ) {
				return false;
			}
		}

		return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', true );
	}


	/**
	 * Initialize the form fields that will be displayed on the Local Pickup
	 * Plus admin settings page
	 */
	public function init_form_fields() {

		// get all categories for the multiselect
		$categories = array( 0 => __( 'All Categories', 'woocommerce-shipping-local-pickup-plus' ) );
		$category_terms = get_terms( 'product_cat', 'orderby=name&hide_empty=0' );

		if ( $category_terms ) {
			foreach ( $category_terms as $category_term ) {
				if ( ! isset( $category_term ) || ! is_object( $category_term ) ) {
					continue;
				} else {
					$categories[ $category_term->term_id ] = $category_term->name;
				}
			}
		}

		$this->form_fields = array(

			'enabled' => array(
				'title'   => __( 'Enable', 'woocommerce-shipping-local-pickup-plus' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable local pickup plus', 'woocommerce-shipping-local-pickup-plus' ),
				'default' => 'no',
			),

			'title' => array(
				'title'       => __( 'Title', 'woocommerce-shipping-local-pickup-plus' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-shipping-local-pickup-plus' ),
				'default'     => __( 'Local Pickup', 'woocommerce-shipping-local-pickup-plus' ),
			),

			'cost' => array(
				'title'       => __( 'Cost', 'woocommerce-shipping-local-pickup-plus' ),
				'type'        => 'amount',
				'description' => __( 'Default cost excluding tax. Enter an amount, e.g. 2.50, or leave empty for no default cost.  The default cost can be overriden by setting a cost per pickup location below.', 'woocommerce-shipping-local-pickup-plus' ),
				'default'     => '',
			),

			'discount' => array(
				'title'       => __( 'Discount', 'woocommerce-shipping-local-pickup-plus' ),
				'type'        => 'amount',
				'description' => __( 'Discount for choosing Local Pickup as the shipping option.  Enter an amount, e.g. 2.50, or a percentage, e.g 5% to discount the order total (pre-tax) when this shipping method is used.', 'woocommerce-shipping-local-pickup-plus' ),
				'default'     => '',
			),

			'categories' => array(
				'title'       => __( 'Categories', 'woocommerce-shipping-local-pickup-plus' ),
				'type'        => 'multiselect',
				'description' => __( 'Optional, these categories of products may only be locally picked up.', 'woocommerce-shipping-local-pickup-plus' ),
				'options'     => $categories,
				'class'       => 'wc-enhanced-select',
			),

			'categories_pickup_only' => array(
				'title'   => __( 'Categories Only', 'woocommerce-shipping-local-pickup-plus' ),
				'type'    => 'checkbox',
				'label'   => __( 'Allow local pickup only for the product categories listed above, all other product categories must be shipped via another method', 'woocommerce-shipping-local-pickup-plus' ),
				'default' => 'no',
			),

			'apply_pickup_location_tax' => array(
				'title'   => __( 'Pickup Location Tax', 'woocommerce-shipping-local-pickup-plus' ),
				'type'    => 'checkbox',
				'label'   => __( 'When this shipping method is chosen, apply the tax rate based on the pickup location than for the customer\'s given address.', 'woocommerce-shipping-local-pickup-plus' ),
				'default' => 'no',
			),

			'hide_shipping_address' => array(
				'title'   => __( 'Hide Shipping Address', 'woocommerce-shipping-local-pickup-plus' ),
				'type'    => 'checkbox',
				'label'   => __( 'Hide the shipping address when local pickup plus is selected at checkout.', 'woocommerce-shipping-local-pickup-plus' ),
				'default' => 'no',
			),

			'checkout_pickup_location_styling' => array(
				'title'   => __( 'Pickup Location Styling', 'woocommerce-shipping-local-pickup-plus' ),
				'type'    => 'select',
				'options' => array( 'select' => __( 'Dropdown', 'woocommerce-shipping-local-pickup-plus' ), 'radio' => __( 'Radio Buttons', 'woocommerce-shipping-local-pickup-plus' ) ),
				'default' => 'select',
				'desc_tip' => __( 'Styling of pickup location options on checkout.', 'woocommerce-shipping-local-pickup-plus' ),
			),

		);

		// if coupons are not enabled, disable the discount fields and add a call to action
		if ( ! $this->coupons_enabled() ) {

			/* translators: %1$s - <a> tag, %2$s - </a> tag */
			$this->form_fields['discount']['description'] = sprintf( __( 'To enable discounts for Local Pickup orders, you must first %1$senable the use of coupons%2$s', 'woocommerce-shipping-local-pickup-plus' ),  '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">',  '</a>' );
			$this->form_fields['discount']['disabled'] = true;
		}
	}


	/**
	 * Setup the local pickup plus admin settings screen
	 *
	 * Overridden from parent class
	 */
	public function admin_options() {
		global $wp_version;

		// NOTE:  an index is not specified in the arrayed input elements below as in the original
		//  example I followed because there's a bug with that approach where if you have three
		//  rows with specified indexes 0, 1, 2 and you delete the middle row and add a new
		//  row, the specified indexes will be 0, 2, 2 and only the first and last rows
		//  will be posted.  Letting the array indexes default with [] fixes the bug and should be fine

		$base_country = WC()->countries->get_base_country();
		$base_state   = WC()->countries->get_base_state();

		// get the pickup fields
		// reserved field names used by this plugin: id, country, cost, note
		$pickup_fields = $this->get_pickup_address_fields( $base_country );

		?>
		<style type="text/css">
			.shippingrows tr td:first-child input {
				margin: 0 0 0 8px;
			}
			.shippingrows tr th {
				white-space: nowrap;
				padding-left: 10px;
				padding-right: 10px;
			}

			.woocommerce table.form-table th .woocommerce-help-tip,
			.woocommerce table.form-table th img.help_tip /* WC 2.4 uses images for help tips */ {
				margin: 0 0 0 4px !important;
				float: none;

			}
			.shippingrows.widefat tr .check-column {
				padding-top: 20px;
			}
			.shippingrows tfoot tr th {
				padding-left: 12px;
			}
		</style>
		<h3><?php echo $this->method_title; ?></h3>
		<p><?php echo $this->admin_page_description; ?></p>
		<table class="form-table">
			<?php $this->generate_settings_html(); ?>
			<tr valign="top">
				<th scope="row" class="titledesc"><?php _e( 'Pickup Locations', 'woocommerce-shipping-local-pickup-plus' ); ?>:</th>
				<td class="forminp" id="<?php echo esc_attr( $this->id ); ?>_pickup_locations">
					<table class="shippingrows widefat" cellspacing="0">
						<thead>
						<tr>
							<th class="check-column"><input type="checkbox" /></th>
							<?php
							foreach ( $pickup_fields as $key => $field ) {
								echo "<th>{$field['label']}</th>";
							}
							?>
							<th>
								<?php
								esc_html_e( 'Cost', 'woocommerce-shipping-local-pickup-plus' );
								echo wc_help_tip( __( 'Cost for this pickup location, enter an amount, eg. 0 or 2.50, or leave empty to use the default cost configured above.', 'woocommerce-shipping-local-pickup-plus' ) );
								?>
							</th>
							<th>
								<?php
								esc_html_e( 'Notes', 'woocommerce-shipping-local-pickup-plus' );
								echo wc_help_tip( __( 'Free-form notes to be displayed below the pickup location on checkout/receipt.  HTML content is allowed.', 'woocommerce-shipping-local-pickup-plus' ) );
								?>
							</th>
						</tr>
						</thead>
						<tfoot>
						<tr>
							<th colspan="<?php echo count( $pickup_fields ) + 3 ?>"><a href="#" class="add button"><?php _e( '+ Add Pickup Location', 'woocommerce-shipping-local-pickup-plus' ); ?></a>
								<a href="#" class="remove button"><?php _e( 'Delete Pickup Location', 'woocommerce-shipping-local-pickup-plus' ); ?></a></th>
						</tr>
						</tfoot>
						<tbody class="pickup_locations">
						<?php
						if ( $this->pickup_locations ) foreach ( $this->pickup_locations as $location ) {
							echo '<tr class="pickup_location">
								<td class="check-column" style="width:20px;"><input type="checkbox" name="select" />';
							echo '<input type="hidden" name="' . $this->id . '_id[]" value="' . $location['id'] . '" />';
							echo '<input type="hidden" name="' . $this->id . '_country[]" value="' . $location['country'] . '" />';
							echo '</td>';

							foreach ( $pickup_fields as $key => $field ) {
								echo '<td>';
								if ( 'state' == $key ) {
									// handle state field specially
									if ( $states = WC()->countries->get_states( $location['country'] ) ) {
										// state select box
										echo '<select name="' . $this->id . '_state[]" class="select">';

										foreach ( $states as $key => $value ) {
											echo '<option';
											if ( $location['state'] == $key ) echo ' selected="selected"';
											echo ' value="' . $key . '">' . $value . '</option>';
										}

									} else {
										// state input box
										echo '<input type="text" value="' . $location['state'] . '" name="' . $this->id . '_state[]" />';
									}
								} else {
									// all other fields
									echo '<input type="text" name="' . $this->id . '_' . $key . '[]" value="' . $location[ $key ] . '" placeholder="' . ( in_array( $key, array( 'company', 'address_2', 'phone' ) ) ? __( '(Optional)', 'woocommerce-shipping-local-pickup-plus' ) : '' ) . '" />';
								}

								echo '</td>';
							}
							echo '<td><input type="text" name="' . $this->id . '_cost[]" value="' . ( isset( $location['cost'] ) ? $location['cost'] : '' ) . '" placeholder="' . __( '(Optional)', 'woocommerce-shipping-local-pickup-plus' ) . '" /></td>';
							echo '<td><textarea name="' . $this->id . '_note[]" placeholder="' . __( '(Optional)', 'woocommerce-shipping-local-pickup-plus' ) . '">' . ( isset( $location['note'] ) ? $location['note'] : '' ) . '</textarea></td>';
							echo '</tr>';
						}
						?>
						</tbody>
					</table>
				</td>
			</tr>
		</table><!--/.form-table-->

		<script type="text/javascript">
			jQuery( document ).ready( function( $ ) {

				$( '#<?php echo $this->id; ?>_pickup_locations a.add' ).live( 'click', function() {

				    var row = '<tr class="pickup_location"><td class="check-column" style="width:20px;"><input type="checkbox" name="select" /><input type="hidden" name="<?php echo $this->id ?>_id[]" value="" /><input type="hidden" name="<?php echo $this->id ?>_country[]" value="<?php echo $base_country; ?>" /></td><?php foreach ( $pickup_fields as $key => $field ) : ?><td><?php if ( 'state' === $key ) : ?><?php if ( $states = WC()->countries->get_states( $base_country ) ) : ?><select name="<?php echo $this->id . '_state[]'; ?>" class="select"><?php foreach ( $states as $key => $value ) : ?><option <?php echo $base_state === $key ? ' selected="selected"' : ''; echo 'value="' . $key . '">'; echo esc_js( $value ); ?></option><?php endforeach; ?><?php else : ?><input type="text" value="<?php echo $base_state; ?>" name="<?php echo $this->id . '_state[]'; ?>" /><?php endif; ?><?php else : ?><input type="text" name="<?php echo $this->id . '_' . $key . '[]'; ?>" value="" placeholder="<?php echo ( in_array( $key, array( 'company', 'address_2', 'phone' ) ) ? __( '(Optional)', 'woocommerce-shipping-local-pickup-plus' ) : '' ); ?>" /><?php endif; ?></td><?php endforeach;?><td><input type="text" name="<?php echo $this->id ?>_cost[]" value="" placeholder="<?php _e( '(Optional)', 'woocommerce-shipping-local-pickup-plus' ) ?>" /></td><td><textarea name="<?php echo $this->id ?>_note[]" placeholder="<?php _e( '(Optional)', 'woocommerce-shipping-local-pickup-plus' ) ?>"></textarea></td></tr>';

					$( '#<?php echo $this->id; ?>_pickup_locations table tbody.pickup_locations' ).append( row );

					return false;
				} );

				// Remove row
				$( '#<?php echo $this->id; ?>_pickup_locations a.remove' ).live( 'click', function() {
					var answer = confirm( "<?php _e( 'Delete the selected pickup locations?', 'woocommerce-shipping-local-pickup-plus' ); ?>" );
					if ( answer ) {
						$( '#<?php echo $this->id; ?>_pickup_locations table tbody tr td.check-column input:checked' ).each( function( i, el ){
							$( el ).closest( 'tr' ).remove();
						} );
					}
					return false;
				} );

			} );
		</script>
		<?php
	}


	/**
	 * Helper function to run the given variable through
	 * the woocommerce_clean() and stripslashes_deep() functions
	 *
	 * TODO remove this method by v2.0.0 rewrite {FN 2016-09-28}
	 *
	 * @deprecated since 1.13.3
	 *
	 * @since 1.6
	 * @param mixed $var the variable to clean
	 * @return mixed cleaned variable
	 */
	public function wc_clean( $var ) {
		_deprecated_function( __CLASS__ . '::wc_clean', '1.13.3', 'stripslashes_deep( wc_clean( $value ) )' );
		return stripslashes_deep( wc_clean( $var ) );
	}


	/**
	 * Parses the location fields to return safe values
	 *
	 * @since 1.13.3
	 * @param mixed $value Value to clean
	 * @return mixed Sanitized value
	 */
	private function clean_location_field( $value ) {
		return stripslashes_deep( wc_clean( $value ) );
	}


	/**
	 * Parses a cost field so it can only return a number or a percentage amount
	 *
	 * @since 1.13.3
	 * @param string $value A string that should contain only a number perhaps followed by a % sign
	 * @return float|string Either a numerical value or a percentage amount allowed
	 */
	private function clean_amount_field( $value ) {

		$cost = $this->clean_location_field( $value );

		if ( $this->is_percentage( $cost ) ) {
			$amount = explode( '%', $cost );
			$cost   = $this->get_amount( $amount[0] ) . '%';
		} else {
			$cost = is_numeric( $cost ) ? (float) $cost : abs( $cost );
		}

		return $cost;
	}


	/**
	 * Admin Panel Options Processing.  Processes the shipping method configuration
	 * fields in the standard Settings API manner, and then processes the
	 * special pickup locations separately.
	 *
	 * Overridden from parent
	 */
	public function process_admin_options() {

		// take care of the regular configuration fields
		parent::process_admin_options();

		$base_country     = WC()->countries->get_base_country();
		$pickup_fields    = $this->get_pickup_address_fields( $base_country );
		$pickup_locations = array();
		$posted_fields    = array();

		// reserved fields
		$countries  = isset( $_POST[ $this->id . '_country' ] ) ? array_map( array( $this, 'clean_location_field' ), $_POST[ $this->id . '_country' ] )   : array();
		$ids        = isset( $_POST[ $this->id . '_id' ] )      ? array_map( array( $this, 'clean_location_field' ), $_POST[ $this->id . '_id' ] )        : array();
		$costs      = isset( $_POST[ $this->id . '_cost' ] )    ? array_map( array( $this, 'clean_amount_field' ), $_POST[ $this->id . '_cost' ] )        : array();
		$notes      = isset( $_POST[ $this->id . '_note' ] )    ? array_map( 'stripslashes_deep', $_POST[ $this->id . '_note' ] )                         : array();

		// standard fields
		foreach ( array_keys( $pickup_fields ) as $field_name ) {
			$posted_fields[ $field_name ] = isset( $_POST[ $this->id . '_' . $field_name ] ) ? array_map( array( $this, 'clean_location_field' ), $_POST[ $this->id . '_' . $field_name ] )  : array();
		}

		// determine the current maximum pickup location id
		$max_id = -1;
		foreach ( $this->pickup_locations as $location ) {
			$max_id = max( $max_id, $location['id'] );
		}

		for ( $i = 0, $ix = count( $ids ); $i < $ix; $i++ ) {

			// pickup location id
			$id = $ids[ $i ];

			if ( ! is_numeric( $id ) ) {
				$id = ++$max_id;
			}

			// reserved fields
			$pickup_location = array(
				'id'        => $id,
				'country'   => isset( $countries[ $i ] ) ? $countries[ $i ] : null,
				'note'      => isset( $notes[ $i ] ) ? $notes[ $i ] : null,
			);

			$cost = isset( $costs[ $i ] ) ? trim( $costs[ $i ] ) : null;

			// special handling for cost field
			if ( is_numeric( $cost ) ) {
				$pickup_location['cost'] = ! empty( $cost ) ? (float) $cost : null;
			} elseif ( $this->is_percentage( $cost ) && ( $amount = $this->get_amount( $cost ) ) ) {
				$pickup_location['cost'] = $amount . '%';
			} else {
				$pickup_location['cost'] = null;
			}

			// standard fields
			foreach ( array_keys( $pickup_fields ) as $field_name ) {
				$pickup_location[ $field_name ] = isset( $posted_fields[ $field_name ][ $i ] ) ? $posted_fields[ $field_name ][ $i ] : null;
			}

			$pickup_locations[] = $pickup_location;
		}

		update_option( self::PICKUP_LOCATIONS_OPTION, $pickup_locations );

		$this->load_pickup_locations();
	}


	/** Discount ************************************************************/


	/**
	 * Make the label for the Pickup discount coupon look nicer
	 *
	 * @since 1.10.1
	 * @param  string $label
	 * @return string
	 */
	public function coupon_label( $label ) {

		if ( false !== stripos( $label, 'WC_LOCAL_PICKUP_PLUS' ) ) {
			$label = esc_html( __( 'Pickup', 'woocommerce-shipping-local-pickup-plus' ) );
		}

		return $label;
	}


	/**
	 * If this is our special Pickup discount coupon, remove the "[Remove]" link
	 *
	 * @since 1.10.1
	 * @param string $value HTML coupon label
	 * @param \WC_Coupon $coupon The coupon
	 * @return string
	 */
	public function coupon_remove_label( $value, $coupon ) {

		if ( SV_WC_Plugin_Compatibility::is_wc_version_gte_3_0() ) {
			$coupon_code = $coupon->get_code();
		} else {
			$coupon_code = $coupon->code;
		}

		if ( $coupon_code === $this->get_discount_code() ) {
			$value = preg_replace( '/<a.+\[Remove\].+a>/', '', $value );
		}

		return $value;
	}


	/**
	 * If this is our special Pickup coupon, remove the WC coupon message
	 *
	 * @since 1.10.1
	 * @param string $msg The coupon message
	 * @param int $msg_code Either 200 or 201
	 * @param \WC_Coupon $coupon the coupon
	 * @return false|string
	 */
	public function coupon_remove_message( $msg, $msg_code, $coupon ) {

		if ( SV_WC_Plugin_Compatibility::is_wc_version_gte_3_0() ) {
			$coupon_code = $coupon->get_code();
		} else {
			$coupon_code = $coupon->code;
		}

		return $coupon_code === $this->get_discount_code() ? false : $msg;
	}


	/**
	 * Generate the coupon data required for the local pickup discount,
	 * if there is one currently applied
	 *
	 * @since 1.10.1
	 * @param array $data the coupon data
	 * @param string $code the coupon code
	 * @return array the custom coupon data
	 */
	public function get_discount_data( $data, $code ) {

		if ( strtolower( $code ) !== $this->get_discount_code() ) {
			return $data;
		}

		$discount = $this->get_discount();

		if ( is_numeric( $discount ) && (float) $discount > 0 ) {
			$data = array(
				'discount_type' => 'fixed_cart',
				'coupon_amount' => (float) $discount,
			);
		} elseif ( preg_match( '/(\d+\.?\d*)%/', $discount, $matches ) ) {
			$data = array(
				'discount_type' => 'percent',
				'coupon_amount' => (float) $matches[1],
			);
		}

		if ( ! empty( $data['coupon_amount'] ) ) {

			$data = wp_parse_args( $data, array(

				'id'                         => true,
				'code'                       => $code,

				// TODO pay attention: these keys seem to have changed between WC 2.6 and WC 3.0 {FN 2017-03-14}
				'type'                       => isset( $data['discount_type'] ) ? $data['discount_type'] : 'fixed_cart',
				'discount_type'              => isset( $data['discount_type'] ) ? $data['discount_type'] : 'fixed_cart',
				'amount'                     => max( 0, abs( $data['coupon_amount'] ) ),
				'coupon_amount'              => max( 0, abs( $data['coupon_amount'] ) ),

				'date_created'               => '',
				'date_modified'              => '',
				'date_expires'               => '',
				'individual_use'             => false,
				'usage_count'                => '',
				'usage_limit'                => '',
				'usage_limit_per_user'       => 0,
				'limit_usage_to_x_items'     => 0,
				'expiry_date'                => '',
				'apply_before_tax'           => true,
				'free_shipping'              => false,
				'product_categories'         => array(),
				'exclude_product_categories' => array(),
				'exclude_sale_items'         => false,
				'product_ids'                => array(),
				'excluded_product_ids'       => array(),
				'minimum_amount'             => '',
				'maximum_amount'             => '',
				'customer_email'             => '',
				'email_restrictions'         => array(),
				'used_by'                    => array(),
				'description'                => '',

			) );
		}

		return $data;
	}


	/**
	 * Remove or apply the local pickup discount
	 *
	 * @since 1.10.1
	 */
	 public function maybe_apply_discount() {

		// is there potentially a discount to apply or remove?
		if ( ! $this->is_enabled() || ! $this->get_discount() ) {
			return;
		}

		$chosen_shipping_methods = $this->get_chosen_shipping_methods();

		// Is LPP chosen, and is it available to be chosen, and does the cart need shipping?
		// Need the last two checks for the case of an LPP eligible product being removed
		// and LPP no longer being available or needed, otherwise we get a phantom coupon
		// shown until the subsequent page refresh
		if (    in_array( $this->id, $chosen_shipping_methods, false )
		     && WC()->cart->needs_shipping()
		     && $this->is_available( WC()->cart->get_shipping_packages() ) ) {

			if ( WC()->cart->has_discount( $this->get_discount_code() ) ) {

				// LPP selected and discount has already been applied
				return;
			}

		} else {

			if ( WC()->cart->has_discount( $this->get_discount_code() ) ) {

				// LPP not selected, remove the discount
				WC()->cart->remove_coupon( $this->get_discount_code() );

				// refresh the cart coupons, since this happens prior to our current action
				WC()->cart->coupons = WC()->cart->get_coupons();
			}

			return;
		}

		// apply the discount
		remove_action( 'woocommerce_applied_coupon', array( WC()->cart, 'calculate_totals' ), 20, 0 );

		WC()->cart->add_discount( $this->generate_discount_code() );

		add_action( 'woocommerce_applied_coupon', array( WC()->cart, 'calculate_totals' ), 20, 0 );

		// refresh the cart coupons, since this happens prior to our current action
		WC()->cart->coupons = WC()->cart->get_coupons();
	}


	/**
	 * Generates a unique discount code tied to the current user ID and timestamp
	 *
	 * @since 1.10.1
	 */
	public function generate_discount_code() {

		// set the discount code to the current user ID + the current time
		// in YYYY_MM_DD_H_M format
		$discount_code = sprintf( 'wc_local_pickup_plus_%1$s_%2$s', get_current_user_id(), date( 'Y_m_d_h_i', current_time( 'timestamp' ) ) );

		WC()->session->set( 'wc_local_pickup_plus_discount_code', $discount_code );

		return $discount_code;
	}


	/**
	 * Returns the unique discount code generated for the applied discount if set
	 *
	 * @since 1.10.1
	 * @return string|null Code or null if not set
	 */
	public function get_discount_code() {
		return null !== WC()->session ? WC()->session->get( 'wc_local_pickup_plus_discount_code' ) : null;
	}


	/**
	 * Returns the discount for shipping via this method, if any
	 *
	 * @since 1.4.4
	 * @return float|string the discount amount, as float or as a string percentage
	 */
	private function get_discount() {

		$discount_amount = 0;

		if ( $this->coupons_enabled() ) {

			$discount_amount = $this->is_percentage( $this->discount ) ? $this->discount : $this->get_amount( $this->discount );

			/**
			 * Filter the discount amount
			 *
			 * @since 1.4.4
			 * @param string|float $discount_amount The amount to discount, numerical or percent string amount
			 */
			$discount_amount = apply_filters( 'wc_shipping_local_pickup_plus_discount_amount', $discount_amount );
		}

		return $discount_amount;
	}


	/** Frontend methods ************************************************************/


	/**
	 * Create a package with contents
	 *
	 * @since 1.13.3
	 * @param array $package_contents
	 * @return array
	 */
	private function create_package( array $package_contents ) {

		$package = array(
			'contents'        => $package_contents,
			'contents_cost'   => max( 0, @array_sum( @wp_list_pluck( $package_contents, 'line_total' ) ) ),
			'applied_coupons' => WC()->cart->get_applied_coupons(),
			'user'            => array(
				'ID' => get_current_user_id(),
			),
			'destination'    => array(
				'country'    => WC()->customer->get_shipping_country(),
				'state'      => WC()->customer->get_shipping_state(),
				'postcode'   => WC()->customer->get_shipping_postcode(),
				'city'       => WC()->customer->get_shipping_city(),
				'address'    => WC()->customer->get_shipping_address(),
				'address_2'  => WC()->customer->get_shipping_address_2()
			),
		);

		return $package;
	}


	/**
	 * If the current shipping method is local pickup plus, and there is a
	 * chosen pickup location, add it to the packages so that we can alter the
	 * cached shipping cost based the pickup location
	 *
	 * @param array $packages array of order information used to
	 *        calculate and cache shipping rates
	 *
	 * @return array of order information used to calculate and cache
	 *         shipping rates
	 */
	public function update_shipping_packages( $packages ) {

		// sanity checks
		if ( empty( $packages ) || WC()->cart->is_empty() ) {
			return $packages;
		}

		$split_packages                = array();
		$regular_package_contents      = array();
		$local_pickup_package_contents = array();

		// adding this filter to support a merchant request to be able to
		// allow both local pickup as well as other shipping methods for
		// the selected categories.  Don't want to expose this to the admin
		// quite yet as the settings page has already grown a bit unwieldy
		// and probably needs to be overhauled
		$allow_other_methods_for_found_categories = apply_filters( 'wc_shipping_local_pickup_plus_allow_other_methods_categories', false );

		$subscriptions_version     = class_exists( 'WC_Subscriptions' ) && ! empty( WC_Subscriptions::$version ) ? WC_Subscriptions::$version : null;
		$subscriptions_is_lt_2_1_4 = $subscriptions_version && version_compare( $subscriptions_version, '2.1.4', '<' );

		// loop the existing packages
		foreach ( $packages as $package_index => $package ) {

			// currently, Subscriptions recurring shipping packages do not use the recurring cart key for the index in this array,
			// but they do use it in the chosen shipping methods array; we do a little dance of the indices here to fix this disparity
			if ( isset( $package['recurring_cart_key'] ) && 'none' !== $package['recurring_cart_key'] && wc_local_pickup_plus()->is_subscriptions_active() ) {

				// after Subscriptions v2.1.4 the index might be fixed already and we need to avoid a key string duplication
				if ( $subscriptions_is_lt_2_1_4 || $package['recurring_cart_key'] !== $package_index ) {
					$package_index = WC_Subscriptions_Cart::get_recurring_shipping_package_key( $package['recurring_cart_key'], $package_index );
				}
			}

			list( $found_products, $other_products ) = $this->get_products_by_allowed_category( $package['contents'] );

			// there are items in the package to pick up locally
			if ( count( $found_products ) > 0 ) {

				// the original package only contains local pickup items
				if ( 0 === count( $other_products ) ) {

					// just copy over the package
					$local_pickup_package_contents = (array) $package['contents'];

				// the package also contains shippable items with other methods
				} else {

					// put the local pickup items in one package (array)
					$local_pickup_package_contents = (array) $package['contents'];

					foreach ( $local_pickup_package_contents as $cart_item_key => $cart_item ) {
						if ( ! isset( $found_products[ $cart_item['product_id'] ] ) ) {
							unset( $local_pickup_package_contents[ $cart_item_key ] );
						}
					}

					// create a package containing only the non-local pickup plus
					// category products if "categories pickup only" is enabled,
					// then local pickup plus will be removed  as a shipping method
					// option from is_available()
					$regular_package_contents = (array) $package['contents'];

					foreach ( $regular_package_contents as $cart_item_key => $cart_item ) {
						if ( ! isset( $other_products[ $cart_item['product_id'] ] ) ) {
							unset( $regular_package_contents[ $cart_item_key ] );
						}
					}
				}

			// there are no local pickup items, just retain the package as it is
			} else {

				$regular_package_contents = $package['contents'];
			}

			if ( ! empty( $regular_package_contents ) ) {

				$split_packages[ $package_index ] = $this->create_package( $regular_package_contents );
			}

			if ( ! empty( $local_pickup_package_contents ) ) {

				$local_pickup_package = $this->create_package( $local_pickup_package_contents );

				if ( ! $allow_other_methods_for_found_categories ) {
					$local_pickup_package['ship_via'] = array( $this->id );
				}

				$split_packages[] = $local_pickup_package;
			}

			// add any chosen pickup locations to the packages
			$chosen_shipping_methods = $this->get_chosen_shipping_methods();

			foreach ( $split_packages as $index => $split_package ) {

				if (    isset( $chosen_shipping_methods[ $index ] )
				     && $this->id === $chosen_shipping_methods[ $index ]
				     && $this->has_chosen_pickup_location( $index ) ) {

					$split_packages[ $index ]['pickup_location'] = $this->get_chosen_pickup_location_id( $index );
				}
			}
		}

		return $split_packages;
	}


	/**
	 * Remove duplicate costs for split packages that are being picked up at the same location
	 *
	 * @since 1.13.3
	 * @param array $packages The array of packages after shipping costs are calculated
	 * @return array The filtered array of packages with duplicate costs removed
	 */
	public function remove_duplicate_cost_for_split_packages_at_same_location( $packages ) {

		// keep an array of location ids
		$pickup_locations = array();

		// make sure we're working with multiple packages
		if ( count( $packages ) > 1 ) {

			foreach ( $packages as &$package ) {

				$location_id = isset( $package['pickup_location'] ) && is_numeric( $package['pickup_location'] ) ? $package['pickup_location'] : null;

				// check specifically for null, as 0 is a valid location ID
				if ( is_null( $location_id ) ) {
					continue;
				}

				// check if we've seen a package with the same location id earlier in the loop
				if ( in_array( $location_id, $pickup_locations, false ) ) {

					// sanity check that rates are set as expected
					if ( isset( $package['rates']['local_pickup_plus'] ) ) {

						// remove costs and taxes
						$package['rates']['local_pickup_plus']->cost  = 0;
						$package['rates']['local_pickup_plus']->taxes = array();
					}

				} else {

					$pickup_locations[] = $location_id;
				}
			}
		}

		return $packages;
	}


	/**
	 * Returns the taxable address, which is changed from the customers shipping
	 * address to the pickup location address, if configured
	 *
	 * @since 1.4.4
	 * @param array $address containing country, state, postcode, city
	 * @return array taxable address array containing country, state, postcode, city
	 */
	public function taxable_address( $address ) {

		$chosen_shipping_methods = $this->get_chosen_shipping_methods();

		if (    in_array( $this->id, $chosen_shipping_methods, true )
		     && 'yes' === $this->apply_pickup_location_tax ) {

			// there can be only one taxable address, so if there are multiple pickup locations chosen, just use the first one we find
			foreach ( $chosen_shipping_methods as $package_index => $shipping_method_id ) {

				if ( $this->id == $shipping_method_id ) {

					$location = $this->get_pickup_location_by_id( $this->get_chosen_pickup_location_id( $package_index ) );

					if ( $location ) {
						// first location
						return array( $location['country'], $location['state'], $location['postcode'], $location['city'] );
					}
				}
			}
		}

		return $address;
	}

	/**
	 * Render the pickup location selection box for subscriptions recurring shipping rates
	 *
	 * @internal
	 *
	 * @since 1.13.3
	 * @param string $subscription_index Subscription information with sign up date and recurrence
	 * @param array $base_package The subscription package
	 * @param array $recurring_cart The recurring cart
	 * @param string $chosen_recurring_method The chosen recurring shipping method
	 */
	public function subscription_review_recurrent_shipping_pickup_location( $subscription_index, $base_package, $recurring_cart, $chosen_recurring_method ) {

		if ( $chosen_recurring_method === $this->id && is_checkout() ) {

			$this->render_pickup_location_selection_row( $subscription_index );
		}
	}


	/**
	 * Validate pickup coupon when used with a subscription product.
	 *
	 * @internal
	 *
	 * @since 1.13.4
	 * @param bool $validate Whether to validate the coupon (false to skip validation check).
	 * @param \WC_Coupon $coupon The coupon object.
	 * @return bool Return false: skip validation; true: will have the coupon go through Subscriptions validation.
	 */
	public function validate_subscription_pickup_coupon( $validate, $coupon ) {

		// Skip validation if it's Local Pickup Plus coupon.
		if ( isset( $coupon->code ) && $coupon->code === $this->get_discount_code() ) {
			return false;
		}

		return $validate;
	}


	/**
	 * Render the pickup location selection box on the checkout form
	 *
	 * Considering whether I should make this a template.  On the other hand if
	 * someone really wanted to change the styling they could just
	 * unhook this one and add their own.
	 *
	 * @since 1.5
	 * @param string $template_name the template file name relative to template root
	 * @param string $template_path optional path to template root
	 * @param string $located the absolute template file path
	 * @param array $args arguments passed to template
	 */
	public function review_order_shipping_pickup_location( $template_name, $template_path, $located, $args ) {
		global $wp_query;

		// load pickup location selector:
		// - after the cart shipping template partial
		// - after the cart shipping template partial, for a shipping package with local pickup plus as an option		 +		// - for a shipping package with local pickup plus shipping method,
		// - while on the checkout page (or update checkout ajax request).
		if (    'cart/cart-shipping.php' === $template_name
		     && $this->id === $args['chosen_method']
		     && ( is_checkout() || ( defined( 'WC_DOING_AJAX' ) && 'update_order_review' === $wp_query->get( 'wc-ajax' ) ) ) ) {

			$this->render_pickup_location_selection_row( $args['index'] );
		}
	}


	/**
	 * Render the pickup location selection table row
	 *
	 * TODO perhaps turn this into a WooCommerce overrideable template {FN 2016-10-20}
	 *
	 * @since 1.13.2
	 * @param string $package_index the package index
	 */
	public function render_pickup_location_selection_row( $package_index ) {

		// yes, we already have the pickup locations, but hey, lets refresh them
		//  just in case something changed on the backend
		$this->load_pickup_locations();

		echo '<tr class="pickup_location">';
		echo '<th colspan="1">' . ( 1 == count( $this->pickup_locations ) ? __( 'Your Pickup Location', 'woocommerce-shipping-local-pickup-plus'  ) : __( 'Choose Pickup Location', 'woocommerce-shipping-local-pickup-plus' ) . ' <abbr class="required" title="required" style="border:none;">*</abbr>' ) . '</th>';
		echo '<td class="update_totals_on_change">';

		do_action( 'woocommerce_review_order_before_local_pickup_location', $this->pickup_locations, array(), $package_index );

		if ( 1 === count( $this->pickup_locations ) ) {

			$chosen_pickup_location = $this->pickup_locations[0];
			// GEWIJZIGD: Zorg ervoor dat shortcodes in het adres uitgeschreven worden
			echo do_shortcode( $this->get_formatted_address_helper( $chosen_pickup_location, true ) );

			echo '<input type="hidden" name="pickup_location[' . $package_index . ']" value="' . esc_attr( $chosen_pickup_location['id'] ) . '" />';
		} else {

			$chosen_pickup_location_id = $this->get_chosen_pickup_location_id( $package_index );
			$chosen_pickup_location    = null;

			if ( 'select' === $this->get_checkout_pickup_location_styling() ) {
				echo '<select name="pickup_location[' . $package_index . ']" class="pickup_location" data-placeholder="' . __( 'Choose One', 'woocommerce-shipping-local-pickup-plus' ) . '" style="width: 100%;">';
				echo '<option value=""></option>';
				foreach ( $this->pickup_locations as $location ) {
					// determine the chosen pickup location
					if ( is_numeric( $chosen_pickup_location_id ) && $location['id'] == $chosen_pickup_location_id ) {
						$chosen_pickup_location = $location;
					}
					echo '<option value="' . esc_attr( $location['id'] ) . '" ' . selected( $location['id'], $chosen_pickup_location_id, false ) . '>';
					echo $this->get_formatted_address_helper( $location, true, true );
					echo '</option>';
				}
				echo '</select>';
			} else {
				// radio styling
				echo '<ul style="list-style:none;margin-bottom:5px;">';
				foreach ( $this->pickup_locations as $location ) {
					// determine the chosen pickup location
					if ( is_numeric( $chosen_pickup_location_id ) && $location['id'] == $chosen_pickup_location_id ) {
						$chosen_pickup_location = $location;
					}
					echo '<li style="margin-left:0;">';
					// GEWIJZIGD: Voeg Savoy-klasses toe zodat styling identiek is
					echo '<input type="radio" name="pickup_location[' . $package_index . ']" id="pickup_location_' . $package_index . '_' . $location['id'] . '" value="' . esc_attr( $location['id'] ) . '" class="nm-custom-radio pickup_location" ' . checked( $location['id'], $chosen_pickup_location_id, false ) . ' />';
					// GEWIJZIGD: Zorg ervoor dat shortcodes in het adres uitgeschreven worden
					echo '<label class="nm-custom-radio-label" for="pickup_location_' . $package_index . '_' . $location['id'] . '">' . do_shortcode( $this->get_formatted_address_helper( $location, true, true ) ) . '</label>';
					echo '</li>';
				}
				echo '</ul>';
			}
		}

		// show the note for the selected pickup location, if any
		if ( $chosen_pickup_location && isset( $chosen_pickup_location['note'] ) && $chosen_pickup_location['note'] ) {
			// GEWIJZIGD: Zorg ervoor dat de openingsuren in de shortcode uitgeschreven worden
			echo '<div class="wc-pickup-location-note">' . do_shortcode($chosen_pickup_location['note']) . '</div>';
		}

		echo '</td>';
		echo '</tr>';
	}


	/**
	 * Record the chosen pickup_location from the order review form, if set.
	 * This isn't guaranteed to be called, but if the shipping method is
	 * switched from 'local pickup' to something else, we'll record the selected
	 * pickup location.  This is also invoked when the pickup location is changed.
	 *
	 * @see WC_Local_Pickup_Plus::checkout_update_order_review()
	 * @param string $post_data the data sent from the checkout form
	 */
	public function checkout_update_order_review( $post_data ) {

		$post_data = explode( '&', $post_data );

		foreach ( $post_data as $data ) {
			$data = urldecode( $data );
			if ( false !== strpos( $data, '=' ) ) {
				list( $name, $value ) = explode( '=', $data );

				if ( preg_match( '/pickup_location\[([A-Za-z0-9_]+)\]/', $name, $matches ) ) {

					// I don't really have the opportunity to clear this the way they do with 'chosen_shipping_methods',
					//  but maybe it doesn't really matter
					$chosen_pickup_location = WC()->session->get( 'chosen_pickup_locations' );
					$chosen_pickup_location[ $matches[1] ] = $value;
					WC()->session->set( 'chosen_pickup_locations', $chosen_pickup_location );

				}
			}
		}
	}


	/**
	 * Validate the selected pickup location
	 *
	 * @param array $posted data from checkout form
	 */
	public function after_checkout_validation( $posted ) {

		$shipping_method = $posted['shipping_method'];

		$pickup_location = isset( $_POST['pickup_location'] ) ? $_POST['pickup_location'] : array();

		if ( is_array( $shipping_method ) ) {
			foreach ( $shipping_method as $package_index => $shipping_method_id ) {

				if ( $this->id == $shipping_method_id ) {

					// verify a pickup location is selected for this local pickup plus shipping package
					if ( ! isset( $pickup_location[ $package_index ] ) || ! is_numeric( $pickup_location[ $package_index ] ) ) {
						wc_add_notice( __( 'Please select a local pickup location', 'woocommerce-shipping-local-pickup-plus' ), 'error' );
						return;
					}
				}
			}
		}
	}


	/**
	 * Check the cart items.  If local-pickup-only categories are defined
	 * and the selected shipping method is not 'local pickup', an error
	 * message is displayed.  If local-pickup-only categories are defined,
	 * and other categories are not eligible for local pickup (categories_pickup_only is true),
	 * and the selected shipping method is not local pickup and some of
	 * the ineligble proucts are in the cart, display an error message to
	 * that effect.
	 *
	 * These checks were really critial with pre WC 2.1.  With the advent
	 * of WC 2.1 and multiple shipping methods per order, these checks
	 * *should* never fail since the products are split up by local pickup
	 * availability, but it doesn't hurt to have the extra sanity check.
	 */
	public function woocommerce_check_cart_items() {

		// nothing to check or no shipping required, no errors
		if ( empty( $this->categories ) || ! WC()->cart->needs_shipping() ) {
			return;
		}

		if ( ! $this->get_chosen_shipping_methods() ) {
			// the current action is called before the the default shipping method is determined,
			//  so in order to ensure our messages are displayed correctly we have to figure out
			//  what the default shipping method will be.

			// avoid infinite loop.  thanks maxrice!
			remove_action( 'woocommerce_calculate_totals', array( $this, 'woocommerce_check_cart_items' ) );

			WC()->cart->calculate_totals();
		}

		// are any of the selected categories in the cart?
		$has_errors = false;
		$chosen_shipping_methods = $this->get_chosen_shipping_methods();
		$shipping_packages       = WC()->shipping()->packages;

		$allow_other_methods_for_found_categories = apply_filters( 'wc_shipping_local_pickup_plus_allow_other_methods_categories', false );

		foreach ( $chosen_shipping_methods as $package_id => $shipping_method_id ) {

			if ( isset( $shipping_packages[ $package_id ]['contents'] ) ) {

				list( $found_products, $other_products ) = $this->get_products_by_allowed_category( $shipping_packages[ $package_id ]['contents'] );

				if ( $this->id == $shipping_method_id ) {
					// local pickup plus shipping method selected
					if ( 'yes' === $this->categories_pickup_only && count( $other_products ) > 0 ) {
						wc_add_notice( sprintf( __( 'Some of your cart products are not eligible for local pickup, please remove %s, or select a different shipping method to continue', 'woocommerce-shipping-local-pickup-plus' ), '<strong>' . implode( ', ', $other_products ) . '</strong>' ), 'error' );
						$has_errors = true;
					}
				} else {
					// some other shipping method selected
					if ( count( $found_products ) > 0 && ! $allow_other_methods_for_found_categories ) {
						/* translators: %1$s - list of eligible products, %2$s - Local Pickup plus shipping method name */
						wc_add_notice( sprintf( __( 'Some of your cart products are only eligible for local pickup, please remove %1$s, or change the shipping method to %2$s to continue', 'woocommerce-shipping-local-pickup-plus' ), '<strong>' . implode( ', ', $found_products ) . '</strong>', $this->title ), 'error' );
						$has_errors = true;
					}
				}
			}
		}

		if ( $has_errors && is_cart() ) {
			// if on the cart page and there are shipping/category errors, force the page to refresh when the shipping method changes so the error message will be updated
			//   technically I could probably just hide the error message via javascript, but reloading the page, while more heavyweight, does seem more robust
			wc_enqueue_js( 'jQuery( document.body ).on( "updated_shipping_method", function() { location.reload(); } );' );
		}
	}


	/**
	 * Remove the '(Free)' text from the shipping method label which is
	 * displayed on the cart/checkout pages when the cost of the shipping
	 * method is zero, and looks pretty funky when we add in the (save $5)
	 * text when there is a discount
	 *
	 * @since 1.4.4
	 * @param string $full_label the shipping method full label including price
	 * @param WC_Shipping_Rate $method the shipping method rate object
	 * @return string the shipping method full label including price
	 */
	public function cart_shipping_method_full_label( $full_label, $method ) {

		$index = strlen( ' (' . __( 'Free', 'woocommerce-shipping-local-pickup-plus' ) . ')' );

		if ( $this->get_discount() && substr( $full_label, -$index ) === ' (' . __( 'Free', 'woocommerce-shipping-local-pickup-plus' ) . ')' ) {
			$full_label = substr( $full_label, 0, -$index );
		}

		return $full_label;
	}


	/**
	 * This creates an association between the cart item and order item (via
	 * cart_item_key -> item_id).  This is created so that we can tie an order
	 * line item to its associated order shipping item (via the
	 * _shipping_item_id item meta), which we wouldn't otherwise be able to do
	 * after checkout.
	 *
	 * This hook callback has different args according to WC version pre/post WC 3.0.
	 *
	 * @since 1.5
	 * @see WC_Shipping_Local_Pickup_Plus::checkout_add_shipping_order_item().
	 * @param int $item_id Order item meta ID.
	 * @param array|\WC_Order_Item_Shipping|\WC_Order_Item $item_or_values Item object (WC 3.0+) or values in versions before WC 3.0
	 * @param string $cart_item_key The cart item key hash in versions before WC 3.0 or the order ID if WC 3.0 onwards.
	 */
	public function associate_order_item_to_cart_item( $item_id, $item_or_values, $cart_item_key ) {

		if ( SV_WC_Plugin_Compatibility::is_wc_version_lt_3_0() ) {
			$this->cart_item_to_order_item[ $cart_item_key ] = $item_id;
		} elseif ( $item_or_values instanceof WC_Order_Item_Product && isset( $item_or_values->legacy_cart_item_key ) ) {
			$this->cart_item_to_order_item[ $item_or_values->legacy_cart_item_key ] = $item_id;
		}
	}


	/**
	 * Set the selected pickup locations on the shipping order item when it's added.
	 *
	 * Compatibility callback method for WooCommerce version 3.0+.
	 *
	 * @internal
	 *
	 * @since 1.14.0
	 * @param int $shipping_item_id
	 * @param \WC_Order_Item_Shipping $shipping_item
	 * @param int $order_id
	 */
	public function checkout_new_shipping_order_item( $shipping_item_id, $shipping_item, $order_id ) {

		if ( ! $shipping_item instanceof WC_Order_Item_Shipping ) {
			return;
		}

		$package_index = isset( $shipping_item->legacy_package_key ) ? $shipping_item->legacy_package_key : null;

		// pickup location for this shipping package
		if (    null !== $package_index
		     && isset( $_POST['pickup_location'][ $package_index ] )
		     && ( $pickup_location = $this->get_pickup_location_by_id( $_POST['pickup_location'][ $package_index ] ) ) ) {

			$order            = wc_get_order( $order_id );
			$shipping_methods = $order->get_shipping_methods();

			// the shipping item is indeed using local pickup plus, so add the selected pickup location
			if (    isset( $shipping_methods[ $shipping_item_id ]['method_id'] )
			     && $this->id == $shipping_methods[ $shipping_item_id ]['method_id'] ) {

				// seems like we shouldn't be storing the note field in the order meta,
				// this seems reasonable to look up from the options settings
				unset( $pickup_location['note'] );

				wc_update_order_item_meta( $shipping_item_id, 'pickup_location', $pickup_location );

				// remember which pickup location they used.  Since the packages could be different upon the next
				//  checkout, really the correct thing to do here would be to just save one pickup location as the
				//  default for all packages, but that will require some additional code, figuring out when we've
				//  hit the last package so we can clear out the session, etc
				$chosen_pickup_location                   = WC()->session->get( 'chosen_pickup_locations' );
				$chosen_pickup_location[ $package_index ] = $pickup_location['id'];

				WC()->session->set( 'chosen_pickup_locations', $chosen_pickup_location );

				// figure out which order items were shipped by what shipping method?
				// we have: order item id, package item id, cart item id
				// we want to associate a package item id with an order item id
				$packages = WC()->shipping()->get_packages();

				foreach ( $packages[ $package_index ]['contents'] as $cart_item_id => $values ) {

					$order_item_id = isset( $this->cart_item_to_order_item[ $cart_item_id ] ) ? $this->cart_item_to_order_item[ $cart_item_id ] : null;

					if ( $order_item_id ) {

						wc_update_order_item_meta( $order_item_id, '_shipping_item_id', $shipping_item_id );

						/** @type WC_Product $product */
						$product = isset( $values['data'] ) ? $values['data'] : null;

						// skip virtual items
						if ( ! $product->needs_shipping() ) {
							continue;
						}

						// GEWIJZIGD: Schrijf de ophaallocatie niet expliciet weg bij elke bestellijn
						// wc_update_order_item_meta( $order_item_id, __( 'Pickup Location', 'woocommerce-shipping-local-pickup-plus' ), $this->get_formatted_address_helper( $pickup_location, true ) );
					}
				}
			}
		}
	}


	/**
	 * Set the selected pickup locations on the shipping order item when it's added
	 *
	 * Compatibility callback method for WooCommerce versions earlier than 3.0.
	 *
	 * @internal2
	 * @deprecated
	 *
	 * @since 1.5
	 * @see WC_Shipping_Local_Pickup_Plus::associate_order_item_to_cart_item()
	 * @param int $order_id order identifier
	 * @param int $shipping_item_id shipping order item identifier
	 * @param int $package_index the package index for this shipping order item
	 */
	public function checkout_add_shipping_order_item( $order_id, $shipping_item_id, $package_index ) {

		// pickup location for this shipping package
		if (    isset( $_POST['pickup_location'][ $package_index ] )
		     && ( $pickup_location = $this->get_pickup_location_by_id( $_POST['pickup_location'][ $package_index ] ) ) ) {

			$order            = wc_get_order( $order_id );
			$shipping_methods = $order->get_shipping_methods();

			// the shipping item is indeed using local pickup plus, so add the selected pickup location
			if ( isset( $shipping_methods[ $shipping_item_id ]['method_id'] ) && $this->id == $shipping_methods[ $shipping_item_id ]['method_id'] ) {

				// seems like we shouldn't be storing the note field in the order meta, this seems reasonable to look up from the options settings
				unset( $pickup_location['note'] );

				wc_update_order_item_meta( $shipping_item_id, 'pickup_location', $pickup_location );

				// remember which pickup location they used.  Since the packages could be different upon the next
				//  checkout, really the correct thing to do here would be to just save one pickup location as the
				//  default for all packages, but that will require some additional code, figuring out when we've
				//  hit the last package so we can clear out the session, etc
				$chosen_pickup_location                   = WC()->session->get( 'chosen_pickup_locations' );
				$chosen_pickup_location[ $package_index ] = $pickup_location['id'];

				WC()->session->set( 'chosen_pickup_locations', $chosen_pickup_location );

				// figure out which order items were shipped by what shipping method?
				// we have: order item id, package item id, cart item id
				// we want to associate a package item id with an order item id

				$packages = WC()->shipping()->get_packages();

				foreach ( $packages[ $package_index ]['contents'] as $cart_item_id => $values ) {

					$order_item_id = isset( $this->cart_item_to_order_item ) ? $this->cart_item_to_order_item[ $cart_item_id ] : null;

					if ( $order_item_id ) {

						wc_update_order_item_meta( $order_item_id, '_shipping_item_id', $shipping_item_id );

						// skip virtual items
						if ( ! $values['data']->needs_shipping() ) {
							continue;
						}

						wc_update_order_item_meta( $order_item_id, __( 'Pickup Location', 'woocommerce-shipping-local-pickup-plus' ), $this->get_formatted_address_helper( $pickup_location, true ) );
					}
				}
			}
		}
	}


	/**
	 * Display the pickup location on the 'thank you' page, and the order review
	 * page accessed from the user account
	 *
	 * @param WC_Order $order the order
	 */
	public function order_pickup_location( $order ) {

		$pickup_locations = $this->get_order_pickup_locations( $order );

		if ( count( $pickup_locations ) > 0 ) {
			echo '<div>';
			echo '<header class="title"><h3>' . _n( 'Pickup Location', 'Pickup Locations', count( $pickup_locations ), 'woocommerce-shipping-local-pickup-plus' ) . '</h3></header>';
		}

		foreach ( $pickup_locations as $pickup_location ) {

			$formatted_pickup_location = $this->get_formatted_address_helper( $pickup_location );

			echo '<address>';
			echo '<p>' . $formatted_pickup_location . '</p>';
			echo '</address>';

			if ( isset( $pickup_location['note'] ) && $pickup_location['note'] ) {
				echo '<div>' . $pickup_location['note'] . '</div>';
			}
		}

		if ( count( $pickup_locations ) > 0 ) {
			echo '</div>';
		}
	}


	/**
	 * Display the pickup location on the 'admin new order', 'customer completed order'
	 * 'customer note' admin new order' emails
	 *
	 * @param WC_Order $order the order
	 */
	public function email_pickup_location( $order ) {

		$pickup_locations = $this->get_order_pickup_locations( $order );

		if ( count( $pickup_locations ) > 0 ) {
			echo '<div>';
			echo '<h3>' . _n( 'Pickup Location', 'Pickup Locations', count( $pickup_locations ), 'woocommerce-shipping-local-pickup-plus' ) . '</h3>';
		}

		foreach ( $pickup_locations as $pickup_location ) {

			$formatted_pickup_location = $this->get_formatted_address_helper( $pickup_location );

			echo '<p>';
			echo $formatted_pickup_location;
			echo '</p>';

			if ( isset( $pickup_location['note'] ) && $pickup_location['note'] ) {
				echo '<div>' . $pickup_location['note'] . '</div>';
			}
		}

		if ( count( $pickup_locations ) > 0 ) {
			echo '</div>';
		}
	}


	/**
	 * Display the pickup location on the 'admin new order', 'customer completed order'
	 * 'customer note' admin new order' plaintext emails
	 *
	 * @since 1.5
	 * @param WC_Order $order the order
	 */
	public function email_pickup_location_plain( $order ) {

		$pickup_locations = $this->get_order_pickup_locations( $order );

		if ( count( $pickup_locations ) > 0 ) {
			echo _n( 'Pickup Location', 'Pickup Locations', count( $pickup_locations ), 'woocommerce-shipping-local-pickup-plus' );
		}

		foreach ( $pickup_locations as $pickup_location ) {

			$formatted_pickup_location = $this->get_formatted_address_helper( $pickup_location );

			echo "\n" . $formatted_pickup_location . "\n";

			if ( isset( $pickup_location['note'] ) && $pickup_location['note'] ) {
				echo wp_kses( $pickup_location['note'] ) . "\n";
			}
		}
	}


	/**
	 * Gets any order pickup locations from the given order
	 *
	 * @since 1.5
	 * @param WC_Order $order the order
	 * @return array of pickup locations, with country, postcode, state, city, address_2, adress_1, company, phone, cost and id properties
	 */
	private function get_order_pickup_locations( $order ) {

		$pickup_locations = array();

		foreach ( $order->get_shipping_methods() as $shipping_item ) {

			if ( $this->id == $shipping_item['method_id'] && isset( $shipping_item['pickup_location'] ) ) {

				$location = maybe_unserialize( $shipping_item['pickup_location'] );

				// get the note from the global location, if it's still configured
				$global_location  = $this->get_pickup_location_by_id( $location['id'] );
				$location['note'] = isset( $global_location['note'] ) ? $global_location['note'] : '';

				$pickup_locations[] = $location;
			}
		}

		return $pickup_locations;
	}


	/**
	 * Compatibility with per product shipping when a per-product shipping price
	 * is used along with a free local pickup location
	 *
	 * @since 1.6
	 * @param boolean $skip whether to skip, defaults to true
	 * @return boolean false to apply the per product shipping cost even when
	 *         the local pickup price is $0
	 */
	public function per_product_shipping_skip_free_method( $skip ) {
		return false;
	}


	/** Admin methods ************************************************************/


	/**
	 * Hides the shipping_item_id item meta that we add to associate a line_item
	 * to a shipping item
	 *
	 * @since 1.5
	 * @param array $hidden the item meta to hide
	 * @return array the item meta to hide, including _shipping_item_id
	 */
	public function admin_order_hide_itemmeta( $hidden ) {

		$hidden[] = '_shipping_item_id';

		return array_merge( $hidden, array( '_shipping_item_id' ) );
	}


	/**
	 * Display the pickup location on the admin order data panel
	 *
	 * Had to decide between putting the "Pickup Location" within the
	 * Order Totals > Shipping item area or in the Order Details panel.
	 * Although putting it with the associated shipping item might be more
	 * technically correct, it doesn't actually help you determine which items
	 * are being locally picked up, and it "looks" more in place over with the
	 * billing/shipping address fields.
	 *
	 * The pickup location is also shown as an item meta value so that the item
	 * pickup locations can be distinguished for orders with multiple shipping
	 * methods.  Unfortunately, this can only be done for orders placed through
	 * the checkout process at the moment, and not for orders placed within the
	 * admin, just because the admin doesn't allow you to associate an item
	 * with a shipping method.  Granted you could make the assumption if
	 * there's only one shipping method, but this is not yet implemented.
	 */
	public function admin_order_pickup_location() {

		global $post;

		$order = wc_get_order( $post->ID );
		$title_shown = false;

		if ( $order->has_shipping_method( $this->id ) ) {

			?>
			<style type="text/css">
				#order_data a.edit_pickup_location {
					opacity: 0.4;
				}
				#order_data a.edit_pickup_location:hover, #order_data a.edit_pickup_location:focus {
					opacity: 1;
				}
			</style>
			<script type="text/javascript">
				jQuery( document ).ready( function( $ ) {

					// display the order data pickup location edit fields
					$( 'a.edit_pickup_location' ).click( function() {

						$( this ).hide();
						$( this ).closest( '.order_data_column' ).find( 'div.pickup_location' ).hide();
						$( this ).closest( '.order_data_column' ).find( 'div.edit_pickup_location' ).show();

						return false;
					} );

					// Detect a change in pickup location and update the pickup location for all affected line items
					$( '.edit_pickup_location select' ).change( function() {

						// all order items shipped by this method
						var orderLineItems = $( this ).data( 'line_item_ids' ).toString().split( ',' );

						// new pickup location
						var pickupLocation = $( this ).find( 'option:selected' ).text();

						$.each( orderLineItems, function( index, lineItemId ) {

							var meta_key = $( '.item[data-order_item_id="' + lineItemId + '"] input[type="text"][value="<?php _e( 'Pickup Location', 'woocommerce-shipping-local-pickup-plus' ); ?>"]' );

							if ( meta_key.length > 0 ) {

								// update the data (this will be posted and persisted by the order data meta box save)
								var meta_id = meta_key.attr( 'name' ).match( /\d+/ )[0];
								$( '.item[data-order_item_id="' + lineItemId + '"] textarea[name="meta_value[' + meta_id + ']"]' ).val( pickupLocation );

								// update the view
								$( '.item[data-order_item_id="' + lineItemId + '"] .display_meta tr' ).each( function( index, element ) {
									if ( '<?php _e( 'Pickup Location', 'woocommerce-shipping-local-pickup-plus' ); ?>:' == $( element ).find( 'th' ).text() ) {
										$( element ).find( 'td p' ).text( pickupLocation );
									}
								} );
							}
						} );
					} );
				} );
			</script>
			<?php

			$shipping_methods = $order->get_shipping_methods();

			foreach ( $shipping_methods as $shipping_item_id => $shipping_item ) {

				if ( $this->id == $shipping_item['method_id'] ) {

					// defaults
					$pickup_location = array( 'id' => '' );
					$formatted_pickup_location = '';

					// is there a pickup location selected?
					if ( isset( $shipping_item['pickup_location'] ) && $shipping_item['pickup_location'] ) {
						$pickup_location = maybe_unserialize( $shipping_item['pickup_location'] );
						$formatted_pickup_location = $this->get_formatted_address_helper( $pickup_location );
					}

					// only show the title once
					if ( ! $title_shown ) {
						echo '<h4>' . __( 'Pickup Locations', 'woocommerce-shipping-local-pickup-plus' ) . '<a class="edit_pickup_location" href="#"><img src="' . WC()->plugin_url() . '/assets/images/icons/edit.png" alt="Edit" width="14" /></a></h4>';
						$title_shown = true;
					}

					// Display Values
					echo '<div class="pickup_location">';

					if ( $formatted_pickup_location ) {
						echo '<p>' . esc_html( preg_replace( '#<br\s*/?>#i', ', ', $formatted_pickup_location ) ) . '</p>';
					} else {
						echo '<p class="none_set">' . __( 'No pickup location set.', 'woocommerce-shipping-local-pickup-plus' ) . '</p>';
					}

					echo '</div>';

					// get all line item ids that use this shipping method
					$included_line_item_ids = array();

					foreach ( $order->get_items() as $line_item_id => $line_item ) {
						if ( isset( $line_item['shipping_item_id'] ) && $line_item['shipping_item_id'] == $shipping_item_id ) {
							$included_line_item_ids[] = $line_item_id;
						}
					}

					// Display form
					echo '<div class="edit_pickup_location" style="display:none;">';
					echo '<select name="pickup_location[' . $shipping_item_id . ']" data-line_item_ids="' . implode( ',', $included_line_item_ids ) . '">';

					foreach ( $this->pickup_locations as $location ) {
						echo '<option value="' . esc_attr( $location['id'] ) . '" '.selected( $location['id'], isset( $pickup_location['id'] ) ? $pickup_location['id'] : null, false ) . '>';
						echo $this->get_formatted_address_helper( $location, true );
						echo '</option>';
					}
					echo '</select>';

					echo '</div>';
				}
			}
		}
	}


	/**
	 * Admin order update, save pickup location if needed and add an
	 * order note to the effect
	 *
	 * @param int $post_id order post identifier
	 * @param object $post order post object
	 */
	public function admin_process_shop_order_meta( $post_id, $post ) {

		$order = wc_get_order( $post_id );

		// nothing to do here, shipping method not used, or no pickup location posted
		if ( ! $order->has_shipping_method( $this->id ) || ! isset( $_POST['pickup_location'] ) || ! is_array( $_POST['pickup_location'] ) ) {
			return;
		}

		$pickup_locations = $_POST['pickup_location'];

		if ( isset( $pickup_locations[0] ) ) {

			// this indicates that this was an order placed pre WC 2.1 and
			//  now saved in 2.1 so the old style shipping data has been
			//  updated to the new style and we will also update our data structures
			$shipping_methods = $order->get_shipping_methods();

			// get first (and should be only) shipping item id
			list( $shipping_item_id ) = array_keys( $shipping_methods );
			$pickup_location_id = $pickup_locations[0];

			// simulate the new data structure
			$pickup_locations = array( $shipping_item_id => $pickup_location_id );
			$pickup_location  = $this->get_pickup_location_by_id( $pickup_location_id );

			// create the shipping_item_id entries to link all order line items to the shipping method
			foreach ( $order->get_items() as $order_item_id => $line_item ) {
				wc_update_order_item_meta( $order_item_id, '_shipping_item_id', $shipping_item_id );
				wc_update_order_item_meta( $order_item_id, __( 'Pickup Location', 'woocommerce-shipping-local-pickup-plus' ), $this->get_formatted_address_helper( $pickup_location, true ) );
			}

			// clean up the old style data
			SV_WC_Order_Compatibility::delete_meta_data( $order, '_pickup_location' );
		}

		// Normal WC 2.1+ behavior
		foreach ( $pickup_locations as $shipping_item_id => $pickup_location_id ) {

			$pickup_location = $this->get_pickup_location_by_id( $pickup_location_id );

			// update the shipping item pickup location
			if ( wc_update_order_item_meta( $shipping_item_id, 'pickup_location', $pickup_location ) ) {

				// make a note of the change
				$order->add_order_note(
					sprintf( __( 'Pickup location changed to %s', 'woocommerce-shipping-local-pickup-plus' ),
						$this->get_formatted_address_helper( $pickup_location, true )
					),
					0,     // not a customer note
					true   // note is added by admin user
				);

			}
		}
	}


	/** Helper methods ******************************************************/


	/**
	 * Returns true if this gateway is enabled
	 *
	 * @since 1.10.1
	 * @return bool
	 */
	public function is_enabled() {
		return 'yes' === $this->enabled;
	}


	/**
	 * Returns true if coupons are enabled
	 *
	 * @since 1.10.1
	 * @return boolean true if coupons are enabled in core WooCommerce settings
	 */
	private function coupons_enabled() {
		return (bool) wc_coupons_enabled();
	}

	/**
	 * Returns the array of shipping methods chosen during checkout
	 *
	 * @since 1.7.2
	 * @return array of chosen shipping method ids
	 */
	public static function get_chosen_shipping_methods() {

		$chosen_shipping_methods = isset( WC()->session ) && WC()->session->get( 'chosen_shipping_methods' ) ? WC()->session->get( 'chosen_shipping_methods' ) : array();

		/**
		 * Filters the chosen shipping methods
		 *
		 * @since 1.13.0
		 * @param array $chosen_shipping_methods array of chosen shipping method ids
		 */
		return apply_filters( 'wc_shipping_local_pickup_plus_chosen_shipping_methods', $chosen_shipping_methods );
	}

	/**
	 * Returns true if the shipping address should be hidden on checkout when
	 * this shipping method is selected
	 *
	 * @since 1.4.4
	 * @return boolean true if the shipping address should be hidden, false otherwise
	 */
	public function hide_shipping_address() {
		return 'yes' === $this->hide_shipping_address;
	}


	/**
	 * Returns the configured checkout pickup location styling
	 *
	 * @since 1.7
	 * @return string either 'select' or 'radio'
	 */
	public function get_checkout_pickup_location_styling() {
		// default to dropdown for backwards compatibility
		return $this->checkout_pickup_location_styling ? $this->checkout_pickup_location_styling : 'select';
	}


	/**
	 * Gets the chosen pickup location id.  If no pickup location is selected
	 * and there is only one pickup location defined, that location is defaulted
	 * to and set in the session
	 *
	 * @since 1.4.4
	 * @param int $package_index the index of the shipping package
	 * @return int the selected pickup location id, if any
	 */
	public function get_chosen_pickup_location_id( $package_index ) {

		$pickup_location_ids = isset( WC()->session ) ? WC()->session->get( 'chosen_pickup_locations' ) : array();

		// check for numeric because '0' is a valid location id, whereas null/false/'' are not
		if ( ( ! isset( $pickup_location_ids[ $package_index ] ) || ! is_numeric( $pickup_location_ids[ $package_index ] ) ) && 1 == count( $this->pickup_locations ) ) {

			$location = reset( $this->pickup_locations );
			$pickup_location_ids[ $package_index ] = $location['id'];

			WC()->session->set( 'chosen_pickup_locations', $pickup_location_ids );
		}

		return isset( $pickup_location_ids[ $package_index ] ) ? $pickup_location_ids[ $package_index ] : null;
	}


	/**
	 * Returns true if a chosen pickup location has been selected
	 *
	 * @since 1.4.4
	 * @param int $package_index the index of the shipping package
	 * @return boolean true if a pickup location has been chosen, false otherwise
	 */
	public function has_chosen_pickup_location( $package_index ) {
		// check for numeric because '0' is a valid location id, whereas null/false/'' are not
		return is_numeric( $this->get_chosen_pickup_location_id( $package_index ) );
	}


	/**
	 * Gets the identified pickup location
	 *
	 * @param int $id The pickup location identifier
	 * @return array pickup location associative array with members country,
	 *         postcode, state, city, address_2, address_1, company, phone,
	 *         cost, id.  Or null if there is no pickup location with id $id
	 */
	private function get_pickup_location_by_id( $id ) {

		if ( empty( $this->pickup_locations ) ) {
			$this->load_pickup_locations();
		}

		foreach ( $this->pickup_locations as $location ) {
			if ( $location['id'] == $id ) {
				return $location;
			}
		}

		return null;
	}


	/**
	 * Load the configured pickup locations from the database
	 * and set them to the pickup_locations property
	 */
	private function load_pickup_locations() {

		$this->pickup_locations  = array();

		if ( $option = get_option( self::PICKUP_LOCATIONS_OPTION ) ) {

			$this->pickup_locations = array_filter( (array) $option );
		}
	}


	/**
	 * Returns an array of the pickup address fields tailored to $country
	 *
	 * Good Test Cases:
	 * Vietnam       - VN - hidden address2, hidden postcode, no states
	 * United States - US - state dropdown
	 * Austria       - AT - no states
	 * Greece        - GR - has states, but no dropdown
	 *
	 * @param string $country Two-letter country code
	 * @return string[] associative Array of address fields available for $country
	 */
	private function get_pickup_address_fields( $country ) {

		$locale = WC()->countries->get_country_locale();
		$locale = $locale[ $country ];
		$states = WC()->countries->get_states( $country );

		if ( is_array( $states ) && 0 === count( $states ) ) {
			$use_state = false;
		} else {
			$use_state = true;
		}

		$address_fields = WC()->countries->get_address_fields( $country, 'shipping_' );

		unset( $address_fields['shipping_first_name'], $address_fields['shipping_last_name'], $address_fields['shipping_country'] );
		$pickup_fields = array();
		foreach ( $address_fields as $key => $value ) {
			$key = substr( $key, 9 );  // strip off 'shipping_'
			unset( $value['required'], $value['class'], $value['clear'], $value['type'], $value['label_class'] );

			if ( isset( $locale['postcode_before_city'] ) && $locale['postcode_before_city'] ) {
				if ( 'city' == $key ) {
					$pickup_fields['postcode'] = $address_fields['shipping_postcode'];
				} elseif ( 'postcode' == $key ) {
					// we have already handled this
					continue;
				}
			}

			if ( ( ! isset( $locale[ $key ]['hidden'] ) || ! $locale[ $key ]['hidden'] ) && ( 'state' != $key || $use_state ) ) {
				$pickup_fields[ $key ] = $value;
			}

			// Address 2 label was removed in WC 2.0 for some reason, add it back in for now
			if ( 'address_2' == $key ) {
				$pickup_fields[ $key ]['label'] = __( 'Address 2', 'woocommerce-shipping-local-pickup-plus' );
			}
		}

		// add a phone field if not already available
		if ( ! isset( $pickup_fields['phone'] ) ) {
			$pickup_fields['phone'] = array(
				'label' => __( 'Phone', 'woocommerce-shipping-local-pickup-plus' ),
			);
		}

		return $pickup_fields;
	}


	/**
	 * Helper function to return a formatted address
	 *
	 * @param array $address Associative array containing address data
	 * @param bool $one_line Whether to return the the address on a single line,
	 *                        without the phone number, otherwise (false) broken up
	 *                        onto multiple lines, ending with the phone number if present
	 * @param bool $show_cost Whether to display the cost, if any
	 *                        and applies only if $one_line is true
	 * @return string $address Formatted string
	 */
	private function get_formatted_address_helper( $address, $one_line = false, $show_cost = false ) {

		// pass empty first_name/last_name otherwise we get a bunch of notices
		$formatted = WC()->countries->get_formatted_address( array_merge( array(
			'first_name' => null,
			'last_name'  => null,
			'state'      => null
		), $address ) );

		if ( $one_line ) {

			// OPTIONEEL GEWIJZIGD: Laat de enters in het adres gewoon staan
			$formatted = str_replace( array( '<br/>', '<br />', "\n" ), array( ', ', ', ', '' ), $formatted );

			// GEWIJZIGD: Gebruik het telefoonveld als naam
			if ( ! empty( $address['phone'] ) ) {
				$formatted = $address['phone'] . "<br/>\n" . $formatted;
			}

			if ( $show_cost ) {

				$cost = $this->get_cost_for_location( $address );

				if ( '' !== $cost ) {

					$formatted .= ' (' . wc_price( $cost ) . ')';
				}
			}

		} else {

			if ( ! empty( $address['phone'] ) ) {

				$formatted .= "<br/>\n" . $address['phone'];
			}
		}

		return $formatted;
	}


	/**
	 * Return allowed category products, and non-category products found in
	 * $contents
	 *
	 * @since 1.5
	 * @param array $contents cart items
	 * @return array first element is an array of product id to title of
	 *         products in one of the configured categories, second is an
	 *         array with products that don't belong to one of the categories
	 */
	private function get_products_by_allowed_category( $contents ) {

		$found_products  = array();
		$other_products  = array();

		if ( is_array( $this->categories ) ) {
			foreach ( $this->categories as $category_id ) {
				foreach ( $contents as $item ) {

					// skip virtual items
					if ( ! $item['data']->needs_shipping() ) {
						continue;
					}

					// once a product has been determined to belong to an eligible category, keep it eligible regardless of any other categories
					// 0 = "All Categories"
					if ( 0 == $category_id || has_term( $category_id, 'product_cat', $item['product_id'] ) ) {
						$found_products[ $item['product_id'] ] = $item['data']->get_title();
						unset( $other_products[ $item['product_id'] ] );
					} elseif ( ! isset( $found_products[ $item['product_id'] ] ) ) {
						// also keep track of products in the cart that don't match the selected category ids
						$other_products[ $item['product_id'] ] = $item['data']->get_title();
					}
				}
			}
		}

		return array( $found_products, $other_products );
	}


	/**
	 * Get the cost as an absolute amount or a relative amount to cart totals
	 *
	 * @since 1.13.3
	 * @param string|float $cost Either an absolute numerical value or a percentage string like "3.25%"
	 * @return float Ensures that the returned value is always a number
	 */
	private function get_cost( $cost ) {

		if ( $this->is_percentage( $cost ) ) {
			return (float) max( 0, ( WC()->cart->cart_contents_total * $this->get_percentage_amount( $cost ) ) / 100 );
		}

		return $this->get_amount( $cost );
	}


	/**
	 * Gets the cost (if any) for the pickup location $address
	 *
	 * @param array $address Local pick up address data
	 * @return string|float A numerical amount or empty string if no costs found for the given address
	 */
	private function get_cost_for_location( $address ) {

		// the cost can be overridden by an individual location
		if ( ! empty( $address['cost'] ) ) {
			$cost = $this->get_cost( $address['cost'] );
		} elseif ( ! empty( $this->cost ) ) {
			$cost = $this->get_cost( $this->cost );
		}

		// turn any non positive number into empty string
		// to imply no cost for the given address
		return empty( $cost ) ? '' : $cost;
	}


	/**
	 * Get the range of costs per location as an array with two elements
	 *
	 * @return float[] Array with the minimum cost as the first element,
	 *                 and the maximum cost as the second element
	 */
	private function get_location_cost_range() {

		$min_cost = PHP_INT_MAX;
		$max_cost = -1;

		foreach ( $this->pickup_locations as $location  ) {
			$cost     = $this->get_cost_for_location( $location );
			$min_cost = min( $cost, $min_cost );
			$max_cost = max( $cost, $max_cost );
		}

		return array( $min_cost, $max_cost );
	}


	/**
	 * Check if a string contains a percentage value
	 *
	 * @since 1.13.3
	 * @param string $value Maybe a string containing a percentage amount like "5%"
	 * @return bool
	 */
	private function is_percentage( $value ) {
		return is_string( $value ) ? (bool) preg_match( '/(\d+\.?\d*)%/', $value, $matches ) : false;
	}


	/**
	 * Get percentage amount from a string
	 *
	 * @since 1.13.3
	 * @param string $value Possibly a string containing a percentage amount, e.g. "5%"
	 * @return float Ensures a float amount is always returned
	 */
	private function get_percentage_amount( $value ) {

		if ( is_string( $value ) ) {
			preg_match( '/(\d+\.?\d*)%/', $value, $matches );
		}

		if ( ! empty( $matches[0] ) ) {
			$amount = (float) $matches[0];
		} else {
			$amount = is_numeric( $value ) ? $value : 0;
		}

		return abs( $amount );
	}


	/**
	 * Get the numerical amount from a value which might contain a percentage
	 *
	 * @since 1.13.3
	 * @param float|int|string $value A numerical value or perhaps a string with a percentage value, e.g. "10%"
	 * @return float Ensures the returned value is always a numerical amount
	 */
	private function get_amount( $value ) {

		$amount = 0;

		if ( is_numeric( $value ) ) {
			$amount = $value;
		} elseif ( $this->is_percentage( $value ) ) {
			$amount = $this->get_percentage_amount( $value );
		}

		return abs( $amount );
	}


} // WC_Shipping_Local_Pickup_Plus
