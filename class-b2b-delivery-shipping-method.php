<?php

class WC_B2B_Delivery_Shipping_Method extends WC_Shipping_Method {
	
	public function __construct() {
		$this->id = 'b2b_delivery_shipping_method';
		$this->method_title = __( 'Levering op locatie', 'ob2c' );
		$this->init_form_fields();
		$this->init_settings();
		$this->enabled = $this->get_option( 'enabled' );
		$this->title = $this->get_option( 'title' );
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title' => __( 'Enable/Disable', 'woocommerce' ),
				'type' => 'checkbox',
				'label' => __( 'Schakel B2B-leveringen op locatie in', 'ob2c' ),
				'default' => 'no'
			),
			'title' => array(
				'title' => __( 'Method Title', 'woocommerce' ),
				'type' => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
				'default' => __( 'B2B-levering', 'ob2c' ),
			),
		);
	}

	public function is_available( $package ) {
		if ( is_b2b_customer() ) {
			return true;
		} else {
			return false;
		}
	}

	public function calculate_shipping( $package ) {
		$this->add_rate( array( 'id' => $this->id, 'label' => $this->title, 'cost' => 0.00 ) );
	}

}

?>