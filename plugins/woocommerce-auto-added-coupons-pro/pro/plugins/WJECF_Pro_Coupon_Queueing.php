<?php /* phpcs:ignore */

if ( defined( 'ABSPATH' ) && ! class_exists( 'WJECF_Pro_Coupon_Queueing' ) ) {

	/**
	 * Queue coupons that are invalid when customer applies, but might validate later
	 */
	class WJECF_Pro_Coupon_Queueing extends Abstract_WJECF_Plugin {

		public function __construct() {
			$this->set_plugin_data(
				array(
					'description'     => __( 'Allow coupons that are invalid upon application to be applied to the cart once they become valid.', 'woocommerce-jos-autocoupon' ),
					'dependencies'    => array( 'autocoupon' ),
					'can_be_disabled' => true,
				)
			);
		}

		public function init_hook() {
			if ( WJECF()->is_request( 'frontend' ) ) {
				add_filter( 'wjecf_before_update_matched_autocoupons', array( $this, 'apply_valid_queued_coupons' ), 10, 1 );
				add_filter( 'wjecf_auto_remove_invalid_coupon', array( $this, 'auto_remove_invalid_coupon' ), 10, 2 );
				add_filter( 'woocommerce_coupon_error', array( $this, 'filter_woocommerce_coupon_error' ), 10, 3 );
				add_action( 'woocommerce_removed_coupon', array( $this, 'on_woocommerce_removed_coupon' ), 10, 1 );
			}
		}

		public function init_admin_hook() {
			add_action( 'wjecf_woocommerce_coupon_options_extended_features', array( $this, 'admin_coupon_options_extended_features' ), 40, 2 );
		}

		public function admin_coupon_options_extended_features( $thepostid, $post ) {

			echo '<div class="_wjecf_hide_if_autocoupon">';
			echo '<h3>' . esc_html( __( 'Allow applying when invalid', 'woocommerce-jos-autocoupon' ) ) . "</h3>\n";

			$coupon = WJECF_WC()->get_coupon( intval( $thepostid ) );
			// GEWIJZIGD: Default op nee zetten, zodat deze plugin de foutmeldingen op onze digicheques niet overrulet
			// $value  = $coupon->get_meta( '_wjecf_allow_enqueue' ) == 'no' ? 'no' : 'yes'; // Defaults to yes
			$value  = $coupon->get_meta( '_wjecf_allow_enqueue' ) == 'yes' ? 'yes' : 'no'; // Defaults to no

			woocommerce_wp_checkbox(
				array(
					'id'          => '_wjecf_allow_enqueue',
					'value'       => $value,
					'label'       => __( 'Allow applying when invalid', 'woocommerce-jos-autocoupon' ),
					'description' => __( 'When the customer attempts to apply the coupon while its conditions are not met, a message will be displayed. Once the conditions are met it will be applied automatically.', 'woocommerce-jos-autocoupon' ),
				)
			);

			woocommerce_wp_textarea_input(
				array(
					'id'            => '_wjecf_enqueue_message',
					'wrapper_class' => '_wjecf_show_if_allow_enqueue',
					'label'         => __( 'Message', 'woocommerce-jos-autocoupon' ),
					'description'   => __( 'This message will be displayed when the customer applies a coupon while it is not yet valid.', 'woocommerce-jos-autocoupon' ),
					'placeholder'   => sprintf( __( 'Coupon \'%s\' will be applied when its conditions are met.', 'woocommerce-jos-autocoupon' ), '&hellip;' ), // phpcs:ignore
				)
			);

			?>        
			<script type="text/javascript">
				//Hide/show when AUTO-COUPON value changes
				function update_wjecf_allow_enqueue_field( animation ) { 
						if ( animation === undefined ) animation = 'slow';

						if (jQuery("#_wjecf_allow_enqueue").prop('checked')) {
							jQuery("._wjecf_show_if_allow_enqueue").show( animation );
						} else {
							jQuery("._wjecf_show_if_allow_enqueue").hide( animation );
						}
				}
				jQuery( function( $ ) {
					$("#_wjecf_allow_enqueue").click( update_wjecf_allow_enqueue_field );
					update_wjecf_allow_enqueue_field( 0 ); 
				} );
			</script>
			<?php

			echo '</div>';
		}

		public function admin_coupon_meta_fields( $coupon ) {
			return array(
				'_wjecf_allow_enqueue'   => 'yesno',
				'_wjecf_enqueue_message' => 'html',
			);
		}

		// Frontend

		/**
		 * FILTER woocommerce_coupon_error
		 *
		 * If adding a coupon has failed (restrictions not met) queue the coupon so it will be applied once valid
		 *
		 * Using filter woocommerce_coupon_error is a bit hacky; it is called if applying a coupon has failed (invalidated),
		 * but also on any is_valid() call that returns false; therefore we have to doublecheck that it really was
		 * the customer who tried to apply the coupon on the cart
		 *
		 * @param string $err
		 * @param int $err_code
		 * @param WC_Coupon|null $coupon
		 * @return void
		 */
		function filter_woocommerce_coupon_error( $err, $err_code, $coupon ) {
			//NOTE: $coupon can be null on certain calls
			if ( is_null( $coupon ) ) {
				return $err;
			}

			//Don't queue in these cases
			if ( in_array(
				$err_code,
				array(
					WC_Coupon::E_WC_COUPON_ALREADY_APPLIED,
					WC_Coupon::E_WC_COUPON_NOT_EXIST,
					WC_Coupon::E_WC_COUPON_USAGE_LIMIT_REACHED,
					WC_Coupon::E_WC_COUPON_EXPIRED,
				)
			) ) {
				return $err;
			}

			$allow_enqueue = $this->is_enqueue_allowed( $coupon ); //default is yes!!
			if ( ! $allow_enqueue ) {
				return $err;
			}

			if ( doing_filter( 'wp_loaded' ) && isset( $_GET['apply_coupon'] ) ) {
				//Coupon by url using WJECF_Autocoupon
				$coupon_codes = explode( ',', strtolower( wc_clean( $_GET['apply_coupon'] ) ) );
				$do_queue     = in_array( strtolower( $coupon->get_code() ), $coupon_codes );
			} elseif ( ( doing_filter( 'wp_loaded' ) || doing_filter( 'wp_ajax_woocommerce_apply_coupon' ) || doing_filter( 'wp_ajax_nopriv_woocommerce_apply_coupon' ) || doing_filter( 'wc_ajax_apply_coupon' ) ) && isset( $_REQUEST['coupon_code'] ) ) {
				//Form submit on cart page (AJAX of non-AJAX)
				$do_queue = strtolower( sanitize_text_field( $_REQUEST['coupon_code'] ) ) === strtolower( $coupon->get_code() );
			} else {
				$do_queue = false;
			}

			//Was it really added by customer?
			if ( $do_queue ) {
				$notice = $coupon->get_meta( '_wjecf_enqueue_message' );
				if ( '' == $notice ) {
					// translators: %s: coupon code
					$notice = sprintf( __( 'Coupon \'%s\' will be applied when its conditions are met.', 'woocommerce-jos-autocoupon' ), $coupon->get_code() );
				} else {
					// phpcs:ignore
					$notice = __( $notice, 'woocommerce-jos-autocoupon' ); //allows translation
				}
				if ( ! in_array( $notice, wc_get_notices( 'success' ) ) ) {
					wc_add_notice( $notice, 'success' );
					$this->queue_coupon_code( $coupon->get_code() );
				}
				$err = ''; // don't display error!
			}

			return $err;
		}

		function on_woocommerce_removed_coupon( $coupon_code ) {
			$do_unqueue  = isset( $_GET['wc-ajax'] ) && 'remove_coupon' === $_GET['wc-ajax'] && sanitize_text_field( $_POST['coupon'] ) === $coupon_code;
			$do_unqueue |= isset( $_GET['remove_coupon'] ) && sanitize_text_field( $_GET['remove_coupon'] ) === $coupon_code;

			if ( $do_unqueue ) {
				$this->unqueue_coupon_code( $coupon_code );
			}
		}

		/**
		 * Let WJECF_Autocoupon automatically remove invalid enqueued coupons
		 */
		public function auto_remove_invalid_coupon( $remove, $coupon ) {
			return $remove || $this->is_enqueue_allowed( $coupon );
		}

		/**
		 * Apply the valid queued coupons
		 *
		 * (Queued coupons are coupons that the customer tried to apply; but was not yet valid)
		 * @return void
		 */
		public function apply_valid_queued_coupons() {
			//2.3.3 Keep track of apply_coupon coupons and apply when they validate
			if ( ! WJECF()->is_pro() ) {
				return;
			}

			$queued_coupon_codes = $this->get_queued_coupon_codes();

			if ( ! empty( $queued_coupon_codes ) ) {
				$this->log( 'debug', 'Queued coupons: ' . implode( ' ', $queued_coupon_codes ) );
			}

			$coupons_to_apply = array();
			foreach ( $queued_coupon_codes as $coupon_code ) {
				if ( WC()->cart->has_discount( $coupon_code ) ) {
					continue;
				}

				$coupon = new WC_Coupon( $coupon_code );

				if ( ! $coupon->get_id() && ! $coupon->get_virtual() ) {
					$this->log( 'debug', 'Coupon does not exist: ' . $coupon_code );
					$this->unqueue_coupon_code( $coupon_code );
					continue;
				}

				$allow_enqueue = $this->is_enqueue_allowed( $coupon ); //default is yes!!
				if ( ! $allow_enqueue ) {
					$this->log( 'debug', 'Enqueue not allowed: ' . $coupon_code );
					$this->unqueue_coupon_code( $coupon_code );
					continue;
				}

				if ( ! $coupon->is_valid() ) {
					//$this->log( 'debug', "Coupon not valid: " . $coupon_code );
					continue;
				}

				$coupons_to_apply[] = $coupon;
			}

			$coupons_to_apply = WJECF()->coupon_combination_filter( $coupons_to_apply );
			foreach ( $coupons_to_apply as $coupon ) {
				$this->log( 'debug', sprintf( 'Applying queued coupon %s', $coupon->get_code() ) );
				$new_succss_msg = sprintf(
					// translators: 1: coupon code
					__( "Coupon '%s' applied.", 'woocommerce-jos-autocoupon' ),
					__( $coupon->get_code(), 'woocommerce-jos-autocoupon' ) // phpcs:ignore
				);

				WJECF()->start_overwrite_success_message( $coupon, $new_succss_msg );
				WC()->cart->add_discount( $coupon->get_code() ); //Causes calculation and will remove other coupons if it's an individual coupon
				WJECF()->stop_overwrite_success_message();
				//$calc_needed = false; //Already done by adding the discount
			}
		}

		/**
		 * Get the queued coupon codes from the session
		 * @return array The queued coupon codes
		 */
		public function get_queued_coupon_codes() {
			return WJECF()->get_session( 'wjecf_queued_coupons', array() );
		}

		/**
		 * Save the queued coupon codes in the session
		 * @param array $coupon_codes
		 * @return void
		 */
		public function set_queued_coupon_codes( $coupon_codes ) {
			WJECF()->set_session( 'wjecf_queued_coupons', array_unique( $coupon_codes ) );
		}

		private function queue_coupon_code( $coupon_code ) {
			$queued_coupon_codes = $this->get_queued_coupon_codes();
			if ( ! in_array( $coupon_code, $queued_coupon_codes ) ) {
				$queued_coupon_codes[] = $coupon_code;
				$this->set_queued_coupon_codes( $queued_coupon_codes );
			}
		}

		private function unqueue_coupon_code( $coupon_code ) {
			$queued_coupon_codes = $this->get_queued_coupon_codes();
			$key                 = array_search( $coupon_code, $queued_coupon_codes );

			if ( false !== $key ) {
				unset( $queued_coupon_codes[ $key ] );
				$this->set_queued_coupon_codes( $queued_coupon_codes );
			}
		}

		private function is_enqueue_allowed( $coupon ) {
			// GEWIJZIGD: Default op nee zetten, zodat deze plugin de foutmeldingen op onze digicheques niet overrulet
			return ( $coupon->get_meta( '_wjecf_allow_enqueue' ) == 'yes' ) && ( $coupon->get_meta( '_wjecf_is_auto_coupon' ) != 'yes' ); //Auto coupons never enqueue
		}
	}
}
