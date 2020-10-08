<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WOO_MSTORE_admin {
	/** @var WOO_MSTORE_functions */
	var $functions;

	var $licence;

	var $upgrade_require = false;

	/** @var WOO_MSTORE_admin_product */
	var $product_interface;

	public function init() {
		$this->licence                    = new WOO_MSTORE_licence();

		$this->functions                  = new WOO_MSTORE_functions();

		//add product fields
		$this->product_interface          = new WOO_MSTORE_admin_product();
		$this->network_orders_interface   = new WOO_MSTORE_admin_orders();
		$this->network_products_interface = new WOO_MSTORE_admin_products();
		$this->network_category_interface = new WOO_MSTORE_admin_product_category();

		//check for update require
		$this->upgrade_require_check();

		add_action( 'admin_init', array( $this, 'admin_init' ), 1 );

		//geenral network admin menu
		add_action( 'network_admin_menu', array( $this, 'network_admin_menu' ), 999 );
	}


	public function admin_init() {
		add_action( 'admin_enqueue_scripts', array( $this, 'wp_enqueue_woocommerce_style' ) );

		//add quick options for Products
		add_action( 'quick_edit_custom_box', array( $this, 'quick_edit' ), 20, 2 );

		// GEWIJZIGD: Actie uitschakelen, vertraagt het laden van de productpagina enorm
		// Zorgt er natuurlijk wel voor dat we geen quick edits meer kunnen/mogen doen!
		// add_action( 'add_inline_data', array( $this, 'add_quick_edit_inline_data' ) );

		//hide certain menus and forms if don't have enough access
		if ( ! $this->functions->publish_capability_user_can() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'wp_enqueue_no_capability' ) );
		}
	}

	public function network_admin_menu() {
		//only if superadmin
		if ( ! current_user_can( 'manage_sites' ) ) {
			return;
		}

		if ( $this->upgrade_require === false ) {
			//set the latest version
			$options            = $this->functions->get_options();
			$options['version'] = WOO_MSTORE_VERSION;
			$this->functions->update_options( $options );

			return;
		}

		$current_page = isset( $_GET['page'] ) ? $_GET['page'] : '';
		if ( $current_page != 'woonet-upgrade' ) {
			return;
		}

		include_once( WOO_MSTORE_PATH . '/include/class.data-update.php' );
		$data_update = new WOO_Date_Update();

		add_submenu_page(
			'woonet-woocommerce',
			__( 'Upgrade', 'woonet' ),
			__( 'Upgrade', 'woonet' ),
			'manage_product_terms',
			'woonet-upgrade',
			array(
				$data_update,
				'update_run',
			)
		);
	}

	function wp_enqueue_woocommerce_style() {
		wp_enqueue_style( 'woonet_admin', WOO_MSTORE_URL . '/assets/css/admin.css' );

		$screen = get_current_screen();
		if ( empty( $screen ) || empty( $screen->id ) ) {
			return;
		}

		if ( in_array( $screen->id, array( 'edit-product', 'woocommerce_page_woonet-woocommerce-products-network' ) ) ) {
			wp_enqueue_script( 'quick-bulk-edit-woonet', WOO_MSTORE_URL . '/assets/js/quick-bulk-edit.js', array('woocommerce_quick-edit'), WOO_MSTORE_VERSION );
			wp_localize_script( 'quick-bulk-edit-woonet', 'woonet_options', $this->functions->get_options() );
		}
	}

	public function wp_enqueue_no_capability() {
		?>
		<style>
			.inline-edit-row #woocommerce-multistore-fields {
				display: none !important;
			}

			.inline-edit-row #woonet-quick-edit-fields {
				display: none !important;
			}

			.inline-edit-row #woonet-bulk-edit-fields {
				display: none !important;
			}
		</style>
		<?php
	}

	public function bulk_edit( $column_name, $post_type ) {
		error_log( 'Function was called in error. ' . __FILE__ . ':' . __LINE__ . ' | ' . wc_print_r( func_get_args(), true ) );
	}

	public function quick_edit( $column_name, $post_type ) {
		if ( 'price' != $column_name || 'product' != $post_type ) {
			return;
		}

		$screen = get_current_screen();
		if ( empty( $screen ) || empty( $screen->id ) ) {
			return;
		}

		if ( in_array( $screen->id, array( 'edit-product', 'woocommerce_page_woonet-woocommerce-products-network' ) ) ) {
			include( WOO_MSTORE_PATH . '/include/admin/views/html-quick-edit-product.php' );
		}
	}

	/**
	 * Returns a query object with the child products in current blog for a product from a given blog.
	 *
	 * @param integer $main_blog_id
	 * @param integer $post_id
	 *
	 * @return WP_Query
	 */
	public function get_query_child_product( $main_blog_id, $post_id ) {
		//@todo:optimize 
		$args = array(
			'post_type'     =>  'product',
			'post_status'   =>  'any',
			'meta_query'    => array(
				'relation' => 'AND',
				array(
					'key'     => '_woonet_network_is_child_site_id',
					'value'   => $main_blog_id,
					'compare' => '=',
				),
				array(
					'key'     => '_woonet_network_is_child_product_id',
					'value'   => $post_id,
					'compare' => '=',
				),
			)
		);

		return new WP_Query( $args );
	}

	public function add_quick_edit_inline_data( $post ) {
		$master_product_blog_id = get_current_blog_id();

		echo '<div class="hidden" id="woocommerce_multistore_inline_' . absint( $post->ID ) . '">';

			echo '<div class="product_blog_id">' . get_current_blog_id() . '</div>';

			if ( empty( get_post_meta( $post->ID, '_woonet_network_is_child_product_id', true ) ) ) {
				echo '<div class="_is_master_product">yes</div>';

				$blog_ids = $this->functions->get_active_woocommerce_blog_ids();

				foreach ( $blog_ids as $blog_id ) {
					if ( get_current_blog_id() == $blog_id ) {
						echo '<div class="master_blog_id">' . $blog_id . '</div>';

						continue;
					}

					$publish_to = get_post_meta( $post->ID, '_woonet_publish_to_' . $blog_id, true );

					switch_to_blog( $blog_id );

					$slave_product_id  = $this->product_interface->get_slave_product_id( $master_product_blog_id, $post->ID );
					$inherit_updates   = get_post_meta( $slave_product_id, '_woonet_child_inherit_updates', true );
					$stock_synchronize = get_post_meta( $slave_product_id, '_woonet_child_stock_synchronize', true );

					printf(
						'<div class="_woonet_publish_to_%1$d">%2$s</div>
						 <div class="_woonet_publish_to_%1$d_child_inheir">%3$s</div>
						 <div class="_woonet_%1$d_child_stock_synchronize">%4$s</div>',
						$blog_id,
						wc_bool_to_string( $publish_to ),
						wc_bool_to_string( $inherit_updates ),
						wc_bool_to_string( $stock_synchronize )
					);

					restore_current_blog();
				}
			} else {
				echo '<div class="_is_master_product">no</div>';

				$master_blog_id    = get_post_meta( $post->ID, '_woonet_network_is_child_site_id', true );
				$inherit_updates   = get_post_meta( $post->ID, '_woonet_child_inherit_updates', true );
				$stock_synchronize = get_post_meta( $post->ID, '_woonet_child_stock_synchronize', true );

				printf(
					'<div class="master_blog_id">%s</div>
					 <div class="_woonet_child_inherit_updates">%s</div>
					 <div class="_woonet_child_stock_synchronize">%s</div>',
					intval( $master_blog_id ),
					wc_bool_to_string( $inherit_updates ),
					wc_bool_to_string( $stock_synchronize )
				);
			}

		echo '</div>';
	}

	private function upgrade_require_check() {
		$options = $this->functions->get_options();

		$version = empty( $options['version'] ) ? 1 : $options['version'];

		if ( version_compare( $version, '4.0.0', '<' ) ) {
			$this->upgrade_require = true;
		}
	}
}
