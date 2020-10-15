<?php

class WOO_MSTORE_admin_product {
	/**
	 * @var array
	 */
	public $product_fields;

	/**
	 * @var WOO_MSTORE_functions
	 */
	var $functions;

	/**
	 * @var WOO_MSTORE_licence
	 */
	var $licence;

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct( $init_hooks = true ) {

		if ( $init_hooks === true ) {
			add_action( 'admin_menu', array( $this, 'custom_menu_page' ), 999 );
			add_action( 'plugins_loaded', array( $this, 'init' ), 1 );
		}

		$this->functions = new WOO_MSTORE_functions();
	}

	public function log( $message, $line_number = 0, $level = 'notice' ) {
		static $logger = null;

		if ( empty( $logger ) && function_exists( 'wc_get_logger' ) ) {
			$logger = wc_get_logger();
		}

		if ( empty( $logger ) ) {
			return;
		}

		if ( ! is_scalar( $message ) ) {
			$message = wc_print_r( $message, true );
		}
		$message = __CLASS__ . ':' . $line_number . '=>' . $message;

		switch ( $level ) {
			case 'debug':
				$level = WC_Log_Levels::DEBUG;
				break;
			case 'info':
				$level = WC_Log_Levels::INFO;
				break;
			case 'emergency':
				$level = WC_Log_Levels::EMERGENCY;
				break;
			case 'alert':
				$level = WC_Log_Levels::ALERT;
				break;
			case 'critical':
				$level = WC_Log_Levels::CRITICAL;
				break;
			case 'error':
				$level = WC_Log_Levels::ERROR;
				break;
			case 'warning':
				$level = WC_Log_Levels::WARNING;
				break;
			default:
				$level = WC_Log_Levels::NOTICE;
				break;
		}

		$logger->log( $level, $message, array( 'source' => 'WOO_MSTORE' ) );
	}

	public function init() {
		$this->licence = new WOO_MSTORE_licence();

		if ( ! $this->licence->licence_key_verify() ) {
			return;
		}

		add_action( 'WOO_MSTORE_admin_product/process_product', array( $this, 'process_product' ), PHP_INT_MAX );
		add_action( 'WOO_MSTORE_admin_product/process_slave_product', array( $this, 'process_slave_product' ), PHP_INT_MAX );
		add_action( 'WOO_MSTORE_admin_product/set_sync_options', array( $this, 'set_sync_options' ), PHP_INT_MAX, 4 );
		add_filter( 'WOO_MSTORE/get_store_ids', array( $this, 'get_store_ids_filter' ), 10 );

		add_action( 'delete_term', array( $this, 'update_terms_mapping_on_term_delete' ), 10, 3 );
		add_action( 'delete_attachment', array( $this, 'update_attachments_mapping_on_attachment_delete' ) );

		add_action( 'wp_trash_post', array( $this, 'process_product_delete' ), PHP_INT_MAX );
		add_action( 'untrash_post', array( $this, 'process_product_delete' ), PHP_INT_MAX );
		add_action( 'before_delete_post', array( $this, 'process_product_delete' ), PHP_INT_MAX );

		// hide certain menus and forms if don't have enough access
		if ( $this->functions->publish_capability_user_can() ) {
			add_action( 'woocommerce_product_write_panel_tabs', array( $this, 'add_multistore_tab' ) );
			add_action( 'woocommerce_product_data_panels', array( $this, 'add_multistore_panel' ) );

			add_filter( 'woocommerce_products_admin_list_table_filters', array( $this, 'add_products_parent_child_filter' ) );
			add_filter( 'posts_clauses', array( $this, 'filter_parent_child_post_clauses' ) );
		}

		add_action( 'comment_post', array( $this, 'republish_review' ), PHP_INT_MAX, 3 );

		// unlink duplicate products
		add_action( 'woocommerce_product_duplicate', array( $this, 'unlink_duplicated_product' ), PHP_INT_MAX, 2 );
	}

	/* @todo w8 move function to other class*/
	function custom_menu_page() {
		// only if superadmin
		if ( ! current_user_can( 'manage_sites' ) ) {
			return;
		}

		add_submenu_page(
			'woocommerce',
			'Network Orders',
			'Network Orders',
			'manage_options',
			'network-orders',
			array(
				$this,
				'network_orders_page',
			)
		);

		// add_action( 'init',                                     array(  $this, 'init',  ) );
		add_action( 'admin_head', array( $this, 'admin_head_network_orders_page' ) );
		if ( isset( $_GET['page'] ) && $_GET['page'] == 'network-orders' ) {
			add_action( 'load', array( $this, 'load_network_orders_page' ) );
		}

		add_submenu_page(
			'edit.php?post_type=product',
			'Network Products',
			'Network Products',
			'manage_options',
			'network-products',
			array(
				$this,
				'network_products_page',
			)
		);

		add_action( 'admin_head', array( $this, 'admin_head_network_products_page' ) );
		if ( isset( $_GET['page'] ) && $_GET['page'] == 'network-products' ) {
			add_action( 'load', array( $this, 'load_network_products_page' ) );
		}
	}

	/**
	 * reorder the submenus if the case
	 */
	function admin_head_network_orders_page() {
		// relocate the menu in the first position
		global $submenu;
		// get the last index
		$need_submenu = $submenu['woocommerce'];

		end( $need_submenu );
		$key   = key( $need_submenu );
		$_data = $need_submenu[ $key ];

		unset( $need_submenu[ $key ] );

		reset( $need_submenu );
		$first_key  = key( $need_submenu );
		$first_data = current( $need_submenu );

		$_updated_submenu = array();

		$_updated_submenu[ $first_key ]     = $first_data;
		$_updated_submenu[ $first_key + 1 ] = $_data;

		// reindex the array
		foreach ( $need_submenu as $key => $data ) {
			if ( $key < 2 ) {
				continue;
			}

			$new_key = $key;
			$new_key = $new_key + 4;

			$_updated_submenu[ $new_key ] = $data;
		}

		ksort( $_updated_submenu );

		unset( $submenu['woocommerce'] );
		$submenu['woocommerce'] = $_updated_submenu;
	}

	function load_network_orders_page() {}

	function network_orders_page() {
		wp_redirect( network_site_url( 'wp-admin/network/admin.php?page=woonet-woocommerce' ) );
		exit;
	}

	/**
	 * reorder the submenus if the case
	 */
	function admin_head_network_products_page() {

		// relocate the menu in the first position
		global $submenu;
		// get the last index
		$need_submenu = $submenu['edit.php?post_type=product'];

		// search the network-products
		foreach ( $need_submenu as $key => $data ) {
			if ( $data['2'] == 'network-products' ) {
				break;
			}
		}

		unset( $need_submenu[ $key ] );

		reset( $need_submenu );
		$first_key  = key( $need_submenu );
		$first_data = current( $need_submenu );

		unset( $need_submenu[ $first_key ] );

		$_updated_submenu = array();

		$_updated_submenu[ $first_key ]     = $first_data;
		$_updated_submenu[ $first_key + 1 ] = $data;

		// reindex the array
		foreach ( $need_submenu as $key => $data ) {
			if ( $key < 2 ) {
				continue;
			}

			$new_key = $key;
			$new_key = $new_key + 4;

			$_updated_submenu[ $new_key ] = $data;
		}

		// $_updated_submenu[$first_key    +    2]   =   $_data;
		ksort( $_updated_submenu );

		unset( $submenu['edit.php?post_type=product'] );
		$submenu['edit.php?post_type=product'] = $_updated_submenu;

	}

	function load_network_products_page() {

	}

	function network_products_page() {
		wp_redirect( network_site_url( 'wp-admin/network/admin.php?page=woonet-woocommerce-products' ) );
		exit;
	}

	/**
	 * Define the custom new fields
	 */
	public function define_fields() {
		global $post, $blog_id;

		if ( $this->product_fields ) {
			return;
		}

		$options = $this->functions->get_options();

		$parent_product_blog_id = $blog_id;

		$check_for_child_product = get_post_meta( $post->ID, '_woonet_network_is_child_product_id', true );

		if ( $check_for_child_product > 0 ) {
			$this->product_fields[] = array(
				'id'      => '_woonet_title',
				'label'   => '&nbsp;',
				'type'    => 'heading',
				'no_save' => true,
			);

			$this->product_fields[] = array(
				'class'   => '_woonet_description inline',
				'label'   => __( 'Child product, can\'t be re-published to other sites', 'woonet' ),
				'type'    => 'description',
				'no_save' => true,
			);

			$this->product_fields[] = array(
				'id'      => '_woonet_title',
				'label'   => '&nbsp;',
				'type'    => 'heading',
				'no_save' => true,
			);

			$_woonet_child_inherit_updates = get_post_meta( $post->ID, '_woonet_child_inherit_updates', true );
			$this->product_fields[]        = array(
				'id'          => '_woonet_child_inherit_updates',
				'class'       => '_woonet_child_inherit_updates inline',
				'label'       => '',
				'description' => __( 'If checked, this product will inherit any parent updates', 'woonet' ),
				'type'        => 'checkbox',
				'value'       => $_woonet_child_inherit_updates,
			);

			$_woonet_child_stock_synchronize = get_post_meta( $post->ID, '_woonet_child_stock_synchronize', true );
			$this->product_fields[]          = array(
				'id'          => '_woonet_child_stock_synchronize',
				'class'       => '_woonet_child_stock_synchronize inline',
				'label'       => '',
				'description' => __( 'If checked, any stock change will synchronize across product tree.', 'woonet' ),
				'type'        => 'checkbox',
				'value'       => 'yes',
				'checked'     => ( $_woonet_child_stock_synchronize == 'yes' ) ? true : false,
				'disabled'    => ( $options['synchronize-stock'] == 'yes' ) ? true : false,
			);
		} else {
			$main_blog_id = $blog_id;

			$this->product_fields[] = array(
				'id'          => 'woonet_toggle_all_sites',
				'class'       => 'woonet_toggle_all_sites inline',
				'label'       => '',
				'description' => __( 'Toggle all Sites', 'woonet' ),
				'type'        => 'checkbox',
				'value'       => '',
				'no_save'     => true,
			);

			$this->product_fields[] = array(
				'id'          => 'woonet_toggle_child_product_inherit_updates',
				'class'       => '_woonet_child_inherit_updates inline',
				'label'       => '',
				'description' => __( 'Toggle all Child product inherit Parent changes', 'woonet' ),
				'type'        => 'checkbox',
				'value'       => '',
				'no_save'     => true,
			);

			/**
			** Note
			*/
			$this->product_fields[] = array(
				'class'   => 'woomulti-quick-update-notice',
				'label'   => __( 'Note: A linked product (upsell, cross-sell or grouped product) needs to be synced with the child store before it can be synced as upsell, cross-sell or grouped product for a child store product.', 'woonet' ),
				'type'    => 'description',
				'no_save' => true,
			);

			$this->product_fields[] = array(
				'id'      => '_woonet_title',
				'label'   => __( 'Publish to', 'woonet' ),
				'type'    => 'heading',
				'no_save' => true,
			);

			$network_site_ids = WOO_MSTORE_functions::get_active_woocommerce_blog_ids();
			foreach ( $network_site_ids as $network_site_id ) {

				$blog_details = get_blog_details( $network_site_id );

				$value = get_post_meta( $post->ID, '_woonet_publish_to_' . $network_site_id, true );

				switch_to_blog( $blog_details->blog_id );

				if ( $blog_details->blog_id != $main_blog_id ) {
					$this->product_fields[] = array(
						'id'                => '_woonet_publish_to_' . $network_site_id,
						'class'             => '_woonet_publish_to inline',
						'label'             => '',
						'description'       => '<b>' . $blog_details->blogname . '</b><span class="warning">' . __( '<b>Warning:</b> By unselecting this shop the product is unasigned, but not deleted from the shop, witch should be done manually.', 'woonet' ) . '</span>',
						'type'              => 'checkbox',
						'disabled'          => ( $blog_details->blog_id == $main_blog_id ) ? true : false,
						'set_default_value' => true,
						'custom_attribute'  => 'data-group-id=' . $network_site_id,
						'save_callback'     => array( $this, 'field_process_publish_to' ),
					);

					$class = ' ';
					if ( 'yes' != $value ) {
						$class .= 'default_hide';
					}

					$_woonet_child_inherit_updates   = '';
					$_woonet_child_stock_synchronize = '';

					/**
					 * When new meta key is not available fall back to the legacy code
					 * The new meta key is added to a product when a product is synced.
					 *
					 * @since 4.0.0
					 */
					global $wpdb;
					$child_post = $wpdb->get_row(
						"SELECT * from {$wpdb->prefix}postmeta WHERE 
										meta_key='_woonet_network_is_child_sid_{$parent_product_blog_id}_pid_{$post->ID}'"
					);

					if ( ! empty( $child_post ) && ! empty( $child_post->post_id ) ) {
						$_woonet_child_inherit_updates   = get_post_meta( $child_post->post_id, '_woonet_child_inherit_updates', true );
						$_woonet_child_stock_synchronize = get_post_meta( $child_post->post_id, '_woonet_child_stock_synchronize', true );
					} else {
						/**
						 * Legacy code kept for compability. The query is expensive requiring scanning each of a
						 * table with large number of products
						 */
						$args = array(
							'post_type'   => 'product',
							'post_status' => 'any',
							'meta_query'  => array(
								'relation' => 'AND',
								array(
									'key'     => '_woonet_network_is_child_site_id',
									'value'   => $parent_product_blog_id,
									'compare' => '=',
								),
								array(
									'key'     => '_woonet_network_is_child_product_id',
									'value'   => $post->ID,
									'compare' => '=',
								),
							),
						);

						$custom_query = new WP_Query( $args );

						if ( $custom_query->found_posts > 0 ) {
							// product previously created, this is an update
							$child_post = $custom_query->posts[0];

							$_woonet_child_inherit_updates   = get_post_meta( $child_post->ID, '_woonet_child_inherit_updates', true );
							$_woonet_child_stock_synchronize = get_post_meta( $child_post->ID, '_woonet_child_stock_synchronize', true );
						}
					}

					$this->product_fields[] = array(
						'id'          => '_woonet_publish_to_' . $network_site_id . '_child_inheir',
						'class'       => 'group_' . $blog_details->blog_id . ' _woonet_publish_to_child_inheir inline indent' . $class,
						'label'       => '',
						'description' => __( 'Child product inherit Parent changes', 'woonet' ),
						'type'        => 'checkbox',
						'value'       => 'yes',
						'checked'     => empty( $_woonet_child_inherit_updates ) || $_woonet_child_inherit_updates != 'yes' ? false : true,
						'disabled'    => '',
						'no_save'     => true,
					);

					$this->product_fields[] = array(
						'id'          => '_woonet_' . $network_site_id . '_child_stock_synchronize',
						'class'       => 'group_' . $blog_details->blog_id . ' _woonet_child_stock_synchronize inline indent' . $class,
						'label'       => '',
						'description' => __( 'If checked, any stock change will synchronize across product tree.', 'woonet' ),
						'type'        => 'checkbox',
						'value'       => 'yes',
						'checked'     => ( $_woonet_child_stock_synchronize == 'yes' ) ? true : false,
						'disabled'    => ( $options['synchronize-stock'] == 'yes' ) ? true : false,
						'no_save'     => true,
					);
				}

				restore_current_blog();
			}
		}

		$this->product_fields = apply_filters( 'WOO_MSTORE_admin_product\define_fields\product_fields', $this->product_fields );
	}

	public function add_multistore_tab() {
		printf(
			'<li class="woonet_tab"><a href="#woonet_data" rel="woonet_data"><span>%s</span></a></li>',
			__( 'MultiStore', 'woonet' )
		);
	}

	/**
	 * adds the panel to the product interface
	 */
	public function add_multistore_panel() {
		wp_enqueue_style( 'woosl-product', WOO_MSTORE_URL . '/assets/css/woosl-product.css' );
		wp_enqueue_script( 'woosl-product', WOO_MSTORE_URL . '/assets/js/woosl-product.js', array( 'jquery' ) );

		$this->define_fields();

		echo '<div id="woonet_data" class="panel woocommerce_options_panel" style="display:none;">';
		foreach ( $this->product_fields as $field ) {
			if ( ! is_array( $field ) ) {
				if ( $field == 'start_group' ) {
					echo '<div class="options_group">';
				} elseif ( $field == 'end_group' ) {
					echo '</div>';
				}

				continue;
			}

			switch ( $field['type'] ) {
				case 'heading':
					printf( '<h4>%s</h4>', $field['label'] );
					break;
				case 'description':
					printf(
						'<p class="form-field %s"><span class="description">%s</span></p>',
						$field['class'],
						wp_kses_post( $field['label'] )
					);
					break;
				case 'checkbox':
					printf(
						'<p class="form-field no_label %s" %s>',
						$field['class'],
						isset( $field['custom_attribute'] ) ? $field['custom_attribute'] : ''
					);
					if ( ! empty( $field['label'] ) ) {
						printf( '<label for="%s">%s</label>', $field['id'], $field['label'] );
					}

						$value = get_post_meta( get_the_ID(), $field['id'], true );
						printf(
							'<input type="hidden" name="%s" value="" /><input type="checkbox" id="%s" class="%s" %s %s %s />',
							$field['id'],
							$field['id'],
							$field['class'],
							empty( $field['disabled'] ) ? '' : 'disabled="disabled"',
							checked( wc_string_to_bool( isset( $field['checked'] ) ? $field['checked'] : $value ), true, false ),
							empty( $field['set_default_value'] ) ? '' : 'data-default-value="' . $value . '"'
						);

					if ( ! empty( $field['desc_tip'] ) ) {
						printf(
							'<img class="help_tip" data-tip="%s" src="%s/assets/images/help.png" height="16" width="16" />',
							esc_attr( $field['desc_tip'] ),
							esc_url( plugins_url() . '/woocommerce' )
						);
					}
						printf(
							'<span class="description">%s</span>',
							wp_kses_post( $field['description'] )
						);
					echo '</p>';
					break;
				default:
					$func = 'woocommerce_wp_' . $field['type'] . '_input';
					if ( function_exists( $func ) ) {
						$func( $field );
					}
					break;
			}
		}

		echo '</div>';
	}

	public function add_products_parent_child_filter( $filters ) {
		$filters['parent_child'] = array( $this, 'render_products_parent_child_filter' );

		return $filters;
	}

	public function render_products_parent_child_filter() {
		$current_value = isset( $_REQUEST['parent_child'] ) ? wc_clean( wp_unslash( $_REQUEST['parent_child'] ) ) : false; // WPCS: input var ok, sanitization ok.
		$options       = array(
			'parent' => __( 'Parent products', 'woocommerce' ),
			'child'  => __( 'Child products', 'woocommerce' ),
		);

		$output      = '<select name="parent_child">';
			$output .= '<option value="">' . esc_html__( 'Filter by parent/child', 'woocommerce' ) . '</option>';
		foreach ( $options as $key => $label ) {
			$output .= '<option ' . selected( $key, $current_value, false ) . ' value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>';
		}
		$output .= '</select>';

		echo $output;
	}

	/**
	 * Filter by parent/child.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public function filter_parent_child_post_clauses( $args ) {

		global $wpdb;

		if ( ! empty( $_GET['parent_child'] ) && in_array( $_GET['parent_child'], array( 'parent', 'child' ) ) ) {
			if ( ! strstr( $args['join'], 'wc_product_parent_child' ) ) {
				$args['join'] .= " LEFT JOIN {$wpdb->postmeta} wc_product_parent_child ON $wpdb->posts.ID = wc_product_parent_child.post_id ";
			}

			if ( 'parent' == $_GET['parent_child'] ) {
				$args['where'] .= $wpdb->prepare( ' AND wc_product_parent_child.meta_key=%s ', '_woonet_network_main_product' );
			} elseif ( 'child' == $_GET['parent_child'] ) {
				$args['where'] .= $wpdb->prepare( ' AND wc_product_parent_child.meta_key=%s ', '_woonet_network_is_child_product_id' );
			}
		}

		return $args;
	}

	function field_process_publish_to( $field ) {
		global $post, $blog_id, $wpdb;

		$parent_blog_id = $blog_id;

		if ( is_array( $field ) ) {
			$data = isset( $_POST[ $field['id'] ] ) ? esc_attr( trim( stripslashes( $_POST[ $field['id'] ] ) ) ) : '';

			$child_blog_id = str_replace( '_woonet_publish_to_', '', $field['id'] );

			// get previous data
			$previous_data = get_post_meta( $post->ID, $field['id'], true );
			if ( $previous_data == 'yes' && $data != 'yes' ) {
				// a product has been just unnasigned from the tree, make required changes
				switch_to_blog( $child_blog_id );

				/**
				 * When new meta key is not available fall back to the legacy code
				 * The new meta key is added to a  product when a product is synced.
				 *
				 * @since 4.0.0
				*/
				$child_post = $wpdb->get_row(
					"SELECT * from {$wpdb->prefix}postmeta WHERE 
									meta_key='_woonet_network_is_child_sid_{$parent_blog_id}_pid_{$post->ID}'"
				);

				if ( ! empty( $child_post ) && ! empty( $child_post->post_id ) ) {
					// remove the _woonet_network_is_child_product_id and _woonet_network_is_child_site_id fields
					$_woonet_network_is_child_product_id = get_post_meta( $child_post->post_id, '_woonet_network_is_child_product_id', true );
					$_woonet_network_is_child_site_id    = get_post_meta( $child_post->post_id, '_woonet_network_is_child_site_id', true );

					delete_post_meta( $child_post->post_id, '_woonet_network_is_child_product_id' );
					delete_post_meta( $child_post->post_id, '_woonet_network_is_child_site_id' );

					update_post_meta( $child_post->post_id, '_woonet_network_unassigned_site_id', $_woonet_network_is_child_site_id );
					update_post_meta( $child_post->post_id, '_woonet_network_unassigned_product_id', $_woonet_network_is_child_product_id );
				} else {
					// identify the product which inherited the data
					$args = array(
						'post_type'   => 'product',
						'post_status' => 'any',
						'meta_query'  => array(
							'relation' => 'AND',
							array(
								'key'     => '_woonet_network_is_child_site_id',
								'value'   => $parent_blog_id,
								'compare' => '=',
							),
							array(
								'key'     => '_woonet_network_is_child_product_id',
								'value'   => $post->ID,
								'compare' => '=',
							),
						),
					);

					$custom_query = new WP_Query( $args );

					if ( $custom_query->found_posts > 0 ) {
						$child_post = $custom_query->posts[0];

						// remove the _woonet_network_is_child_product_id and _woonet_network_is_child_site_id fields
						$_woonet_network_is_child_product_id = get_post_meta( $child_post->ID, '_woonet_network_is_child_product_id', true );
						$_woonet_network_is_child_site_id    = get_post_meta( $child_post->ID, '_woonet_network_is_child_site_id', true );

						delete_post_meta( $child_post->ID, '_woonet_network_is_child_product_id' );
						delete_post_meta( $child_post->ID, '_woonet_network_is_child_site_id' );

						update_post_meta( $child_post->ID, '_woonet_network_unassigned_site_id', $_woonet_network_is_child_site_id );
						update_post_meta( $child_post->ID, '_woonet_network_unassigned_product_id', $_woonet_network_is_child_product_id );

					}
				}

				restore_current_blog();
			}

			update_post_meta( $post->ID, $field['id'], $data );
		}
	}


	/**
	 * Mark a product to sync with store
	 *
	 * Mark a new product to sync with a store and then call process_product hook to run the sync.
	 *
	 * @param integer $product_id WooCommerce product ID
	 * @param array   $stores Store IDs
	 * @param string  $child_inherit Set child inherit product change option
	 * @param string  $stock_sync Set stock sync option
	 * @return void
	 */
	public function set_sync_options( $product_id, $stores, $child_inherit = 'yes', $stock_sync = 'no' ) {
		$product    = wc_get_product( $product_id );
		$all_stores = $this->functions->get_active_woocommerce_blog_ids();

		foreach ( $all_stores as $store_id ) {
			if ( in_array( $store_id, $stores ) ) {
				$_REQUEST[ '_woonet_publish_to_' . $store_id ]                   = 'yes';
				$_REQUEST[ '_woonet_publish_to_' . $store_id . '_child_inheir' ] = $child_inherit;
				$_REQUEST[ '_woonet_' . $store_id . '_child_stock_synchronize' ] = $stock_sync;
			} else {
				unset( $_REQUEST[ '_woonet_publish_to_' . $store_id ] );
				unset( $_REQUEST[ '_woonet_publish_to_' . $store_id . '_child_inheir' ] );
				unset( $_REQUEST[ '_woonet_' . $store_id . '_child_stock_synchronize' ] );
			}
		}
	}

	public function process_product_delete( $post_id ) {
		global $wpdb, $post_type;

		if ( empty( $post_type ) || ! in_array( $post_type, array( 'product', 'product_variation' ) ) ) {
			return;
		}

		$master_product = wc_get_product( $post_id );
		if ( empty( $master_product ) ) {
			return;
		}
		$master_parent_product  = 'variation' == $master_product->get_type() ? wc_get_product( $master_product->get_parent_id() ) : $master_product;
		$master_product_blog_id = get_current_blog_id();

		if ( $this->is_slave_product( $master_parent_product ) ) {
			global $wpdb;

			$master_blog_id    = intval( $master_parent_product->get_meta( '_woonet_network_is_child_site_id' ) );
			$master_product_id = intval( $master_parent_product->get_meta( '_woonet_network_is_child_product_id' ) );
			if ( $master_blog_id && $master_product_id ) {
				$master_product_blog_prefix = $wpdb->get_blog_prefix( $master_blog_id );
				$wpdb->update(
					$master_product_blog_prefix . 'postmeta',
					array(
						'meta_value' => 'no',
					),
					array(
						'meta_key' => '_woonet_publish_to_' . $master_product_blog_id,
						'post_id'  => $master_product_id,
					)
				);
			}
		}

		$meta_keys    = array(
			'_woonet_child_inherit_updates'       => '__woonet_child_inherit_updates',
			'_woonet_child_stock_synchronize'     => '__woonet_child_stock_synchronize',
			'_woonet_network_is_child_product_id' => '_woonet_network_unassigned_product_id',
			'_woonet_network_is_child_site_id'    => '_woonet_network_unassigned_site_id',
		);
		$update_query = "UPDATE %s SET meta_key='%s' WHERE meta_key='%s' AND post_id IN ( SELECT ID FROM %s WHERE ID=%d OR post_parent=%d )";
		$delete_query = "DELETE FROM %s WHERE meta_key IN ('%s', '%s') AND post_id IN ( SELECT ID FROM %s WHERE ID=%d OR post_parent=%d )";

		$options = $this->functions->get_options();

		remove_action( current_action(), array( $this, 'process_product_delete' ), PHP_INT_MAX );

		$blog_ids = $this->functions->get_active_woocommerce_blog_ids();
		foreach ( $blog_ids as $slave_product_blog_id ) {
			if ( 'variation' == $master_product->get_type() ) {
				if (
					'yes' != $options['child_inherit_changes_fields_control__variations'][ $slave_product_blog_id ]
					||
					'yes' != $options['child_inherit_changes_fields_control__attributes'][ $slave_product_blog_id ]
				) {
					continue;
				}
			}

			$publish_to = $master_parent_product->get_meta( '_woonet_publish_to_' . $slave_product_blog_id );

			if ( get_current_blog_id() == $slave_product_blog_id || 'yes' != $publish_to ) {
				continue;
			}

			switch_to_blog( $slave_product_blog_id );
				$slave_product_id = $this->get_slave_product_id( $master_product_blog_id, $master_product->get_id(), true );

			if ( ! is_null( $slave_product_id ) ) {
				if ( did_action( 'wp_trash_post' ) ) {
					if ( 'yes' == $options['synchronize-trash'] ) {
						wp_trash_post( $slave_product_id );
					} else {
						foreach ( $meta_keys as $meta_key_linked => $meta_key_unlinked ) {
							$wpdb->query(
								sprintf(
									$update_query,
									$wpdb->postmeta,
									$meta_key_unlinked,
									$meta_key_linked,
									$wpdb->posts,
									$slave_product_id,
									$slave_product_id
								)
							);
						}
					}
				} elseif ( did_action( 'untrash_post' ) ) {
					if ( 'yes' == $options['synchronize-trash'] ) {
						wp_untrash_post( $slave_product_id );
					} else {
						foreach ( $meta_keys as $meta_key_linked => $meta_key_unlinked ) {
							$wpdb->query(
								sprintf(
									$update_query,
									$wpdb->postmeta,
									$meta_key_linked,
									$meta_key_unlinked,
									$wpdb->posts,
									$slave_product_id,
									$slave_product_id
								)
							);
						}
					}
				} elseif ( did_action( 'before_delete_post' ) ) {
					if ( 'yes' == $options['synchronize-trash'] ) {
						wp_delete_post( $slave_product_id );
					} else {
						foreach ( $meta_keys as $meta_key_linked => $meta_key_unlinked ) {
							$wpdb->query(
								sprintf(
									$delete_query,
									$wpdb->postmeta,
									$meta_key_linked,
									$meta_key_unlinked,
									$wpdb->posts,
									$slave_product_id,
									$slave_product_id
								)
							);
						}
					}
				}
			}
			restore_current_blog();
		}

		add_action( current_action(), array( $this, 'process_product_delete' ), PHP_INT_MAX );
	}

	/**
	 * @param WC_Product $master_product
	 */
	public function process_slave_product( $master_product ) {
		if ( ! ( $master_product instanceof WC_Product ) ) {
			return;
		}

		$options = $this->functions->get_options();

		$inherit_updates = $this->is_product_inherit_updates( $master_product );
		if ( $master_product->get_meta( '_woonet_child_inherit_updates' ) != $inherit_updates ) {
			update_post_meta( $master_product->get_id(), '_woonet_child_inherit_updates', $inherit_updates );
		}
		$stock_synchronize = $this->is_product_stock_synchronize( $master_product );
		if ( $master_product->get_meta( '_woonet_child_stock_synchronize' ) != $stock_synchronize ) {
			update_post_meta( $master_product->get_id(), '_woonet_child_stock_synchronize', $stock_synchronize );
		}

		if ( 'yes' == $options['synchronize-stock'] || 'yes' == $this->is_product_stock_synchronize( $master_product ) ) {
			$slave_product              = clone $master_product;
			$master_product_blog_id     = $slave_product->get_meta( '_woonet_network_is_child_site_id' );
			$inherit_variations_changes = $options['child_inherit_changes_fields_control__variations'][ get_current_blog_id() ];
			$inherit_attributes_changes = $options['child_inherit_changes_fields_control__attributes'][ get_current_blog_id() ];

			switch_to_blog( $master_product_blog_id );
			$master_product = wc_get_product( $slave_product->get_meta( '_woonet_network_is_child_product_id' ) );

			if ( empty( $master_product ) ) {
				// the master product doesn't exists or has been deleted. Skip updating stock.
				return false;
			}

			$master_product_force_save = false;
			if ( 'variable' == $master_product->get_type() && 'yes' == $inherit_variations_changes && 'yes' == $inherit_attributes_changes ) {
				foreach ( $master_product->get_children() as $master_product_variation_id ) {
					// get master product variation
					$master_product_variation = wc_get_product( $master_product_variation_id );
					$master_product_variation->read_meta_data();

					// get slave product variation
					restore_current_blog();
					$slave_product_variation = $this->get_slave_product( $master_product_blog_id, $master_product_variation );
					switch_to_blog( $master_product_blog_id );
					if ( 0 == $slave_product_variation->get_id() ) {
						continue;
					}

					$changes                   = $this->synchronize_slave_master_products_stock( $master_product_variation, $slave_product_variation );
					$master_product_force_save = ( $master_product_force_save || boolval( count( $changes ) ) );
				}
			}

			$this->synchronize_slave_master_products_stock( $master_product, $slave_product, $master_product_force_save );
			restore_current_blog();
		}
	}

	/**
	 * Process any actions for a product New/Update
	 *
	 * @param integer $post_id Post ID.
	 */
	public function process_product( $post_id ) {
		/*
		 * may be called two times per product because of call
		 * class-wc-meta-box-product-data.php:394, WC_Meta_Box_Product_Data::save()
		 * and
		 * class-wc-meta-box-product-images.php:94, WC_Meta_Box_Product_Images::save()
		 */

		if ( doing_action( 'wp_ajax_woocommerce_save_variations' ) ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		wp_cache_flush();
		$master_product = wc_get_product( $post_id );
		$master_product->get_children();

		// on "slave" product save
		// todo w8 check import
		if ( $this->is_slave_product( $master_product ) ) {
			do_action( 'WOO_MSTORE_admin_product/process_slave_product', $master_product );

			return;
		}

		// This hook has been moved to class.admin.speed-updater.php
		// remove_action( 'woocommerce_update_product', array( $this, 'process_product' ), PHP_INT_MAX );
		remove_action( 'WOO_MSTORE_admin_product/process_product', array( $this, 'process_product' ), PHP_INT_MAX );

		// set master product meta
		$master_product_meta_to_exclude = $this->get_master_product_meta_to_exclude( $master_product );
		foreach ( $master_product_meta_to_exclude as $meta_key ) {
			$master_product->delete_meta_data( $meta_key );
		}
		$master_product_meta_to_update = $this->get_master_product_meta_to_update( $master_product );
		foreach ( $master_product_meta_to_update as $meta_key => $meta_value ) {
			$master_product->add_meta_data( $meta_key, $meta_value, true );
		}

		if ( count( $master_product_meta_to_exclude ) || count( $master_product_meta_to_update ) ) {
			$master_product->save();
		}

		$master_product_data = array(
			'options'                   => $this->functions->get_options(),
			'master_product'            => $master_product,
			'master_product_blog_id'    => get_current_blog_id(),
			'master_product_attributes' => wc_get_attribute_taxonomies(),
			'master_product_terms'      => $this->get_product_terms( $master_product->get_id() ),
			'master_product_upload_dir' => wp_upload_dir(),
		);

		$blog_ids = $this->functions->get_active_woocommerce_blog_ids();
		foreach ( $blog_ids as $slave_product_blog_id ) {
			if (
				get_current_blog_id() == $slave_product_blog_id
				||
				'yes' !== $master_product->get_meta( '_woonet_publish_to_' . $slave_product_blog_id )
			) {
				continue;
			}

			switch_to_blog( $slave_product_blog_id );
				$this->synchronize_master_slave_products(
					$master_product_data + array(
						'slave_product' => $this->get_slave_product( $master_product_data['master_product_blog_id'], $master_product ),
					)
				);
			restore_current_blog();
		}

		// This hook has been moved to class.admin.speed-updater.php
		// add_action( 'woocommerce_update_product', array( $this, 'process_product' ), PHP_INT_MAX );
		add_action( 'WOO_MSTORE_admin_product/process_product', array( $this, 'process_product' ), PHP_INT_MAX );
	}

	/**
	 * Process any actions for a product New/Update
	 *
	 * @param integer $post_id Post ID.
	 */
	public function process_ajax_product( $post_id, $store_id, $number = 5 ) {
		// start ajax sync hooks
		do_action( 'WOO_MSTORE_admin_product/sync_started' );

		if ( doing_action( 'wp_ajax_woocommerce_save_variations' ) ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		wp_cache_flush();
		$master_product = wc_get_product( $post_id );
		$master_product->get_children();

		// on "slave" product save
		// todo w8 check import
		if ( $this->is_slave_product( $master_product ) ) {
			do_action( 'WOO_MSTORE_admin_product/process_slave_product', $master_product );

			return;
		}

		// remove_action( 'woocommerce_update_product', array( $this, 'process_product' ), PHP_INT_MAX );
		remove_action( 'WOO_MSTORE_admin_product/process_product', array( $this, 'process_product' ), PHP_INT_MAX );

		// set master product meta
		$master_product_meta_to_exclude = $this->get_master_product_meta_to_exclude( $master_product );
		foreach ( $master_product_meta_to_exclude as $meta_key ) {
			$master_product->delete_meta_data( $meta_key );
		}

		$master_product_meta_to_update = $this->get_master_product_meta_to_update( $master_product );

		foreach ( $master_product_meta_to_update as $meta_key => $meta_value ) {
			$master_product->add_meta_data( $meta_key, $meta_value, true );
		}

		if ( count( $master_product_meta_to_exclude ) || count( $master_product_meta_to_update ) ) {
			$master_product->save();
		}

		$master_product_data = array(
			'options'                   => $this->functions->get_options(),
			'master_product'            => $master_product,
			'master_product_blog_id'    => get_current_blog_id(),
			'master_product_attributes' => wc_get_attribute_taxonomies(),
			'master_product_terms'      => $this->get_product_terms( $master_product->get_id() ),
			'master_product_upload_dir' => wp_upload_dir(),
		);

		$blog_ids = (array) $store_id;

		foreach ( $blog_ids as $slave_product_blog_id ) {
			if (
				get_current_blog_id() == $slave_product_blog_id
				||
				'yes' !== $master_product->get_meta( '_woonet_publish_to_' . $slave_product_blog_id )
			) {
				continue;
			}

			switch_to_blog( $slave_product_blog_id );
				$this->synchronize_master_slave_products(
					$master_product_data + array(
						'slave_product' => $this->get_slave_product( $master_product_data['master_product_blog_id'], $master_product ),
					)
				);
			restore_current_blog();
		}

		// This hook has been moved to class.admin.speed-updater.php
		// add_action( 'woocommerce_update_product', array( $this, 'process_product' ), PHP_INT_MAX );
		add_action( 'WOO_MSTORE_admin_product/process_product', array( $this, 'process_product' ), PHP_INT_MAX );
	}

	/**
	 * @param WC_Product $master_product
	 * @param WC_Product $slave_product
	 * @param bool       $force_save
	 *
	 * @return array $master_product_changes
	 */
	public function synchronize_slave_master_products_stock( $master_product, $slave_product, $force_save = false ) {
		if ( $slave_product->get_stock_quantity() != $master_product->get_stock_quantity() ) {
			$master_product->set_stock_quantity( $slave_product->get_stock_quantity() );
		}
		if ( $slave_product->get_stock_status() != $master_product->get_stock_status() ) {
			$master_product->set_stock_status( $slave_product->get_stock_status() );
		}
		if ( $slave_product->get_backorders() != $master_product->get_backorders() ) {
			$master_product->set_backorders( $slave_product->get_backorders() );
		}
		if ( $slave_product->get_manage_stock() != $master_product->get_manage_stock() ) {
			$master_product->set_manage_stock( $slave_product->get_manage_stock() );
		}

		$master_product_changes = $master_product->get_changes();
		if ( $force_save || count( $master_product_changes ) ) {
			$master_product->save();
		}

		return $master_product_changes;
	}

	/**
	 * @param WC_Product $product
	 *
	 * @return bool
	 */
	public function is_slave_product( $product ) {
		return ( ! empty( $product->get_meta( '_woonet_network_is_child_product_id' ) ) );
	}

	/**
	 * @param WC_Product $product
	 *
	 * @return string 'yes'|'no'
	 */
	public function is_product_inherit_updates( $product ) {
		if ( empty( $_REQUEST[ '_woonet_publish_to_' . get_current_blog_id() . '_child_inheir' ] ) ) {
			if ( empty( $_REQUEST['_woonet_child_inherit_updates'] ) ) {
				$result = wc_bool_to_string( $product->get_meta( '_woonet_child_inherit_updates' ) );
			} else {
				$result = $_REQUEST['_woonet_child_inherit_updates'];
			}
		} else {
			$result = $_REQUEST[ '_woonet_publish_to_' . get_current_blog_id() . '_child_inheir' ];
		}

		return apply_filters( 'WOO_MSTORE_admin_product/is_product_inherit_updates', $result, $product );
	}

	/**
	 * @param WC_Product $product
	 *
	 * @return string 'yes'|'no'
	 */
	public function is_product_stock_synchronize( $product ) {
		if ( empty( $_REQUEST[ '_woonet_' . get_current_blog_id() . '_child_stock_synchronize' ] ) ) {
			if ( empty( $_REQUEST['_woonet_child_stock_synchronize'] ) ) {
				$result = wc_bool_to_string( $product->get_meta( '_woonet_child_stock_synchronize' ) );
			} else {
				$result = $_REQUEST['_woonet_child_stock_synchronize'];
			}
		} else {
			$result = $_REQUEST[ '_woonet_' . get_current_blog_id() . '_child_stock_synchronize' ];
		}

		return apply_filters( 'WOO_MSTORE_admin_product/is_product_stock_synchronize', $result, $product );
	}

	/**
	 * @param array $data {
	 *     @type WC_Product $master_product,
	 *     @type WC_Product $slave_product,
	 * }
	 */
	public function synchronize_master_slave_products( $data ) {
		$this->update_slave_product( $data );
		$this->republish_slave_product_reviews( $data );

		if (
			'variable' == $data['master_product']->get_type()
			&&
			'yes' === $data['options']['child_inherit_changes_fields_control__variations'][ get_current_blog_id() ]
			&&
			'yes' === $data['options']['child_inherit_changes_fields_control__attributes'][ get_current_blog_id() ]
		) {
			$this->process_product_variations( $data );
		}
	}

	/**
	 * @param array $data {
	 *     @type WC_Product $master_product
	 *     @type WC_Product $slave_product
	 * }
	 */
	public function update_slave_product( $data ) {
		// update changed fields
		$master_slave_products_data_diff = $this->get_master_slave_products_data_diff( $data );
		foreach ( $master_slave_products_data_diff as $key => $value ) {
			$setter = 'set_' . $key;
			$data['slave_product']->{$setter}( $value );
		}

		$slave_product_meta_to_exclude = $this->get_slave_product_meta_to_exclude( $data );
		foreach ( $slave_product_meta_to_exclude as $meta_key ) {
			$data['slave_product']->delete_meta_data( $meta_key );
		}

		$slave_product_meta_to_update = $this->get_slave_product_meta_to_update( $data );
		foreach ( $slave_product_meta_to_update as $meta_key => $meta_value ) {
			$data['slave_product']->update_meta_data( $meta_key, $meta_value );
		}

		if (
			count( $master_slave_products_data_diff )
			||
			count( $slave_product_meta_to_exclude )
			||
			count( $slave_product_meta_to_update )
		) {
			$data['slave_product']->save();

			do_action( 'WOO_MSTORE_admin_product/slave_product_updated', $data );
		}
	}

	/**
	 * @param array $data {
	 *     @type WC_Product $master_product
	 *     @type WC_Product $slave_product
	 * }
	 */
	public function process_product_variations( $data ) {
		$slave_product_variation_options = $data;

		$slave_product_old_variation_ids = $data['slave_product']->get_children();
		$slave_product_new_variation_ids = array();
		foreach ( $data['master_product']->get_children() as $master_product_variation_id ) {
			// get master product variation
			switch_to_blog( $data['master_product_blog_id'] );
				$master_product_variation = wc_get_product( $master_product_variation_id );
				$master_product_variation->read_meta_data();
			restore_current_blog();

			$slave_product_variation_options['master_product'] = $master_product_variation;

			// get slave product variation
			$slave_product_variation = $this->get_slave_product( $data['master_product_blog_id'], $master_product_variation );
			if ( 0 == $slave_product_variation->get_id() ) {
				$slave_product_variation->set_parent_id( $data['slave_product']->get_id() );
			}
			$slave_product_variation_options['slave_product'] = &$slave_product_variation;

			$this->synchronize_master_slave_products( $slave_product_variation_options );

			$slave_product_new_variation_ids[] = $slave_product_variation->get_id();

			do_action(
				'WOO_MSTORE_admin_product/slave_product_variation_updated',
				$data,
				$master_product_variation->get_id(),
				$slave_product_variation->get_id()
			);
		}

		$data['slave_product']->set_children( $slave_product_new_variation_ids );
		$data['slave_product']->save();
		$data['slave_product']->sync( $data['slave_product'] );

		foreach ( array_diff( $slave_product_old_variation_ids, $slave_product_new_variation_ids ) as $slave_product_old_variation_id ) {
			wp_delete_post( $slave_product_old_variation_id, true );
		}
	}

	/**
	 * Identify the product which inherited the data
	 *
	 * @param integer $master_product_blog_id
	 * @param integer $master_product_id
	 *
	 * @return null | integer
	 */
	public function get_slave_product_id( $master_product_blog_id, $master_product_id, $search_everywhere = false ) {
		$slave_product_id = null;

		/**
		 * When new meta key is not available fall back to the legacy code
		 * The new meta key is added to a  product when a product is synced.
		 *
		 * @since 4.0.0
		 */
		global $wpdb;
		$slave_product = $wpdb->get_row(
			"SELECT * from {$wpdb->prefix}postmeta WHERE 
							meta_key='_woonet_network_is_child_sid_{$master_product_blog_id}_pid_{$master_product_id}'"
		);

		if ( ! empty( $slave_product ) && ! empty( $slave_product->post_id ) ) {
			return $slave_product->post_id;
		}

		/**
		 * Legacy code
		 */

		if ( $search_everywhere ) {
			$post_status = array( 'publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'trash' );
		} else {
			$post_status = array( 'publish' );
		}

		$args = array(
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'post_type'      => array( 'product', 'product_variation' ),
			'post_status'    => $post_status,
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => '_woonet_network_is_child_site_id',
					'value'   => $master_product_blog_id,
					'compare' => '=',
				),
				array(
					'key'     => '_woonet_network_is_child_product_id',
					'value'   => $master_product_id,
					'compare' => '=',
				),
			),
		);

		$slave_product_ids = get_posts( $args );

		// try to restore if available
		if ( empty( $slave_product_ids ) ) {
			$args['meta_query'] = array(
				'relation' => 'AND',
				array(
					'key'     => '_woonet_network_unassigned_site_id',
					'value'   => $master_product_blog_id,
					'compare' => '=',
				),
				array(
					'key'     => '_woonet_network_unassigned_product_id',
					'value'   => $master_product_id,
					'compare' => '=',
				),
			);
			$slave_product_ids  = get_posts( $args );

			if ( empty( $slave_product_ids ) ) {
				$slave_product_id = null;
			} else {
				$slave_product_id = $slave_product_ids[0];

				delete_post_meta( $slave_product_id, '_woonet_network_unassigned_site_id' );
				delete_post_meta( $slave_product_id, '_woonet_network_unassigned_product_id' );

				update_post_meta( $slave_product_id, '_woonet_network_is_child_site_id', $master_product_blog_id );
				update_post_meta( $slave_product_id, '_woonet_network_is_child_product_id', $master_product_id );
			}
		} else {
			$slave_product_id = $slave_product_ids[0];
		}

		return $slave_product_id;
	}

	/**
	 * @param int        $master_product_blog_id
	 * @param WC_Product $master_product
	 *
	 * @return WC_Product $slave_product
	 */
	public function get_slave_product( $master_product_blog_id, $master_product ) {
		$slave_product_id = $this->get_slave_product_id(
			$master_product_blog_id,
			$master_product->get_id(),
			true
		);

		if ( is_null( $slave_product_id ) ) {
			$slave_product = clone $master_product;
			$slave_product->set_id( 0 );

			$slave_product->set_total_sales( 0 );
			$slave_product->set_slug( '' );
			$slave_product->set_rating_counts( array() );
			$slave_product->set_average_rating( 0 );
			$slave_product->set_review_count( 0 );

			// grouped or variable product
			if ( method_exists( $slave_product, 'set_children' ) ) {
				$slave_product->set_children( array() );
			}
		} else {
			$product_type  = $master_product->get_type();
			$classname     = WC_Product_Factory::get_product_classname(
				$master_product->get_id(),
				$product_type ? $product_type : 'simple'
			);
			$slave_product = new $classname( $slave_product_id );
		}

		return $slave_product;
	}

	/**
	 * @param array $data Slave product options.
	 *
	 * @return array
	 */
	public function get_slave_product_meta_to_exclude( $data ) {
		$meta_keys = array();

		$meta_keys[] = '_wp_page_template';
		// $meta_keys[] = '_wpml_media_featured';
		// $meta_keys[] = '_wpml_media_duplicate';

		if ( $data['slave_product']->get_meta( '_woonet_network_main_product' ) ) {
			$meta_keys[] = '_woonet_network_main_product';
		}

		$blog_ids = $this->functions->get_active_woocommerce_blog_ids();
		foreach ( $blog_ids as $blog_id ) {
			$key = '_woonet_publish_to_' . $blog_id;
			if ( $data['slave_product']->get_meta( $key ) ) {
				$meta_keys[] = $key;
			}
		}

		return apply_filters( 'WOO_MSTORE_admin_product/slave_product_meta_to_exclude', $meta_keys, $data );
	}

	/**
	 * @param array $data Slave product options.
	 *
	 * @return array
	 */
	public function get_slave_product_meta_to_update( $data ) {
		$meta_data = array();

		if ( $data['slave_product']->get_meta( '_woonet_network_is_child_site_id' ) != $data['master_product_blog_id'] ) {
			$meta_data['_woonet_network_is_child_site_id'] = $data['master_product_blog_id'];
		}

		if ( $data['slave_product']->get_meta( '_woonet_network_is_child_product_id' ) != $data['master_product']->get_id() ) {
			$meta_data['_woonet_network_is_child_product_id'] = $data['master_product']->get_id();
		}

		$inherit_updates = $this->is_product_inherit_updates( $data['slave_product'] );
		if ( $data['slave_product']->get_meta( '_woonet_child_inherit_updates' ) != $inherit_updates ) {
			$meta_data['_woonet_child_inherit_updates'] = $inherit_updates;
		}

		$stock_synchronize = $this->is_product_stock_synchronize( $data['slave_product'] );
		if ( $data['slave_product']->get_meta( '_woonet_child_stock_synchronize' ) != $stock_synchronize ) {
			$meta_data['_woonet_child_stock_synchronize'] = $stock_synchronize;
		}

		/**
		 *  These additional metadata prevent query by meta_value. meta_value is not index, qyerying by
		 *  the meta_value value require scanning every row. FOr a large number of products, this causes
		 *  significant performance degradation.
		 *
		 * @since 4.0.0
		 */
		if ( ! empty( $data['master_product_blog_id'] ) && $data['master_product']->get_id() ) {
			$meta_data[ '_woonet_network_is_child_sid_' . $data['master_product_blog_id'] . '_pid_' . $data['master_product']->get_id() ] = 'yes';
			$meta_data[ '_woonet_network_is_child_sid_' . $data['master_product_blog_id'] ]   = 'yes';
			$meta_data[ '_woonet_network_is_child_pid_' . $data['master_product']->get_id() ] = 'yes';
		}

		return apply_filters( 'WOO_MSTORE_admin_product/slave_product_meta_to_update', $meta_data, $data );
	}

	/**
	 * @param array $data Slave product options.
	 *
	 * @return array
	 */
	public function get_master_slave_products_data_diff( $data ) {
		$products_data_diff = $this->array_diff( $data['master_product']->get_data(), $data['slave_product']->get_data() );

		// check stock synchronization
		if (
			0 != $data['slave_product']->get_id()
			&&
			'yes' != $data['options']['synchronize-stock']
			&&
			'yes' != $this->is_product_stock_synchronize( $data['slave_product'] )
		) {
			unset(
				$products_data_diff['stock_quantity'],
				$products_data_diff['stock_status'],
				$products_data_diff['backorders'],
				$products_data_diff['manage_stock']
			);
		}

		// check inherit data updates
		if (
			0 != $data['slave_product']->get_id()
			&&
			'no' == $this->is_product_inherit_updates( $data['slave_product'] )
		) {
			// skip all but stock fields
			foreach ( $products_data_diff as $key => $value ) {
				if ( ! in_array( $key, array( 'stock_quantity', 'stock_status', 'backorders', 'manage_stock' ) ) ) {
					unset( $products_data_diff[ $key ] );
				}
			}

			return $products_data_diff;
		}

		unset(
			$products_data_diff['id'],
			$products_data_diff['parent_id'],
			$products_data_diff['date_modified'],
			$products_data_diff['meta_data'],
			$products_data_diff['children'],
			$products_data_diff['cross_sell_ids'],
			$products_data_diff['upsell_ids']
		);

		// Child product inherit status changes
		if (
			empty( $data['options']['child_inherit_changes_fields_control__status'][ get_current_blog_id() ] )
			||
			'no' == $data['options']['child_inherit_changes_fields_control__status'][ get_current_blog_id() ]
		) {
			unset( $products_data_diff['status'] );
		}

		// Child product inherit title changes
		if (
			empty( $data['options']['child_inherit_changes_fields_control__title'][ get_current_blog_id() ] )
			||
			'no' == $data['options']['child_inherit_changes_fields_control__title'][ get_current_blog_id() ]
		) {
			unset( $products_data_diff['name'] );
		}

		// Child product inherit description changes
		if (
			empty( $data['options']['child_inherit_changes_fields_control__description'][ get_current_blog_id() ] )
			||
			'no' == $data['options']['child_inherit_changes_fields_control__description'][ get_current_blog_id() ]
		) {
			unset( $products_data_diff['description'] );
		}

		// Child product inherit short description changes
		if (
			empty( $data['options']['child_inherit_changes_fields_control__short_description'][ get_current_blog_id() ] )
			||
			'no' == $data['options']['child_inherit_changes_fields_control__short_description'][ get_current_blog_id() ]
		) {
			unset( $products_data_diff['short_description'] );
		}

		// Child product inherit price changes
		if (
			empty( $data['options']['child_inherit_changes_fields_control__price'][ get_current_blog_id() ] )
			||
			'no' == $data['options']['child_inherit_changes_fields_control__price'][ get_current_blog_id() ]
		) {
			unset(
				$products_data_diff['price'],
				$products_data_diff['regular_price'],
				$products_data_diff['sale_price'],
				$products_data_diff['sale_price_dates_from'],
				$products_data_diff['sale_price_dates_to']
			);
		}

		// GEWIJZIGD: SKIP TIJDELIJK ALTIJD, WANT HET MATCHEN VAN BESTAANDE FOTO'S MISLUKT
		// if ( isset( $data['options']['child_inherit_changes_fields_control__product_image'][ get_current_blog_id() ] ) && $data['options']['child_inherit_changes_fields_control__product_image'][ get_current_blog_id() ] == 'no' ) {
		// 	unset( $products_data_diff['image_id'] );
		// } else {
		// 	// check main image update
		// 	if ( ! empty( $data['master_product']->get_image_id() ) ) {
		// 		$slave_product_mapped_image_id = $this->get_slave_product_mapped_image_id(
		// 			$data,
		// 			$data['master_product']->get_image_id()
		// 		);
		// 		if ( $data['slave_product']->get_image_id() == $slave_product_mapped_image_id ) {
		// 			unset( $products_data_diff['image_id'] );
		// 		} else {
		// 			$products_data_diff['image_id'] = $slave_product_mapped_image_id;
		// 		}
		// 	} else {
		// 		$products_data_diff['image_id'] = null;
		// 	}
		// }
		unset( $products_data_diff['image_id'] );

		// check gallery update
		if ( isset( $data['options']['child_inherit_changes_fields_control__product_gallery'][ get_current_blog_id() ] )
			 && $data['options']['child_inherit_changes_fields_control__product_gallery'][ get_current_blog_id() ] == 'no' ) {
			unset( $products_data_diff['gallery_image_ids'] );
		} else {
			if ( ! empty( $data['master_product']->get_gallery_image_ids() ) ) {
				$slave_product_mapped_image_id = $this->get_slave_product_mapped_image_id(
					$data,
					$data['master_product']->get_gallery_image_ids()
				);
				if ( $data['slave_product']->get_gallery_image_ids() == $slave_product_mapped_image_id ) {
					unset( $products_data_diff['gallery_image_ids'] );
				} else {
					$products_data_diff['gallery_image_ids'] = $slave_product_mapped_image_id;
				}
			} else {
				$products_data_diff['gallery_image_ids'] = array();
			}
		}

		// Child product inherit categories changes
		if (
			empty( $data['options']['child_inherit_changes_fields_control__product_cat'][ get_current_blog_id() ] )
			||
			'no' == $data['options']['child_inherit_changes_fields_control__product_cat'][ get_current_blog_id() ]
		) {
			unset( $products_data_diff['category_ids'] );
		} else {
			// check categories update
			if ( ! empty( $data['master_product']->get_category_ids() ) ) {
				$slave_product_mapped_term_id = $this->get_slave_product_mapped_term_id(
					$data,
					$data['master_product']->get_category_ids()
				);

				$this->synchronize_category_changes( $data );

				if ( $data['slave_product']->get_category_ids() == $slave_product_mapped_term_id ) {
					unset( $products_data_diff['category_ids'] );
				} else {
					$products_data_diff['category_ids'] = $slave_product_mapped_term_id;
				}
			}
		}

		// Child product inherit tags changes
		if (
			empty( $data['options']['child_inherit_changes_fields_control__product_tag'][ get_current_blog_id() ] )
			||
			'no' == $data['options']['child_inherit_changes_fields_control__product_tag'][ get_current_blog_id() ]
		) {
			unset( $products_data_diff['tag_ids'] );
		} else {
			// check tags update
			$slave_product_mapped_term_id = $this->get_slave_product_mapped_term_id(
				$data,
				$data['master_product']->get_tag_ids()
			);
			if ( $data['slave_product']->get_tag_ids() == $slave_product_mapped_term_id ) {
				unset( $products_data_diff['tag_ids'] );
			} else {
				$products_data_diff['tag_ids'] = $slave_product_mapped_term_id;
			}
		}

		// check shipping class update
		if ( ! empty( $data['master_product']->get_shipping_class_id() ) ) {
			$slave_product_mapped_term_id = $this->get_slave_product_mapped_term_id(
				$data,
				$data['master_product']->get_shipping_class_id()
			);
			$slave_product_mapped_term_id = empty( $slave_product_mapped_term_id ) ? '' : $slave_product_mapped_term_id[0];
			if ( $data['slave_product']->get_shipping_class_id() == $slave_product_mapped_term_id ) {
				unset( $products_data_diff['shipping_class_id'] );
			} else {
				$products_data_diff['shipping_class_id'] = $slave_product_mapped_term_id;
			}
		}

		// Child product inherit attributes changes
		if (
			empty( $data['options']['child_inherit_changes_fields_control__attributes'][ get_current_blog_id() ] )
			||
			'no' == $data['options']['child_inherit_changes_fields_control__attributes'][ get_current_blog_id() ]
		) {
			unset( $products_data_diff['attributes'] );
		} else {
			// check attributes update
			if ( ! empty( $data['master_product']->get_attributes() ) ) {
				$master_product_attributes = array();
				foreach ( $data['master_product_attributes'] as $attribute ) {
					$master_product_attributes[ wc_attribute_taxonomy_name( $attribute->attribute_name ) ] = $attribute;
				}

				foreach ( $data['master_product']->get_attributes() as $attribute_id => $attribute ) {
					if ( is_object( $attribute ) ) {
						$products_data_diff['attributes'][ $attribute_id ] = clone $attribute;

						if ( in_array( $attribute_id, array_keys( $master_product_attributes ) ) ) {
							$this->create_slave_product_attribute( $master_product_attributes[ $attribute_id ] );

							$products_data_diff['attributes'][ $attribute_id ]->set_options(
								$this->get_slave_product_mapped_term_id( $data, $attribute->get_options() )
							);
						}
					} else {
						$products_data_diff['attributes'][ $attribute_id ] = $attribute;
					}
				}
			}
		}

		// Child product inherit URL (slug) changes
		if (
			empty( $data['options']['child_inherit_changes_fields_control__slug'][ get_current_blog_id() ] )
			||
			'no' == $data['options']['child_inherit_changes_fields_control__slug'][ get_current_blog_id() ]
		) {
			unset( $products_data_diff['slug'] );
		}

		// Child product inherit purchase note changes
		if (
			empty( $data['options']['child_inherit_changes_fields_control__purchase_note'][ get_current_blog_id() ] )
			||
			'no' == $data['options']['child_inherit_changes_fields_control__purchase_note'][ get_current_blog_id() ]
		) {
			unset( $products_data_diff['purchase_note'] );
		}

		return apply_filters( 'WOO_MSTORE_admin_product/master_slave_products_data_diff', $products_data_diff, $data );
	}

	public function get_slave_product_mapped_term_id( $data, $master_product_term_ids ) {
		$master_product_term_ids = (array) $master_product_term_ids;

		$master_product_blog_id = $data['master_product_blog_id'];

		// get mapped terms
		$terms_mapping = get_option( 'terms_mapping', array() );

		$slave_product_term_ids   = array();
		$update_terms_mapping     = false;
		$_master_product_term_ids = $master_product_term_ids;
		while ( count( $_master_product_term_ids ) ) {
			$master_product_term_id        = intval( array_shift( $_master_product_term_ids ) );
			$master_product_parent_term_id = intval( $data['master_product_terms'][ $master_product_term_id ]->parent );

			$slave_product_term_id        = isset( $terms_mapping[ $master_product_blog_id ][ $master_product_term_id ] )
				? intval( $terms_mapping[ $master_product_blog_id ][ $master_product_term_id ] )
				: null;
			$slave_product_parent_term_id = isset( $terms_mapping[ $master_product_blog_id ][ $master_product_parent_term_id ] )
				? intval( $terms_mapping[ $master_product_blog_id ][ $master_product_parent_term_id ] )
				: ( empty( $master_product_parent_term_id ) ? 0 : null );

			// add master product parent term id to queue
			if ( is_null( $slave_product_parent_term_id ) ) {
				array_unshift(
					$_master_product_term_ids,
					intval( $master_product_parent_term_id )
				);

				array_push(
					$_master_product_term_ids,
					intval( $master_product_term_id )
				);

				continue;
			}

			// if term id is mapped
			if ( ! is_null( $slave_product_term_id ) ) {
				// if not parent term id
				if ( in_array( $master_product_term_id, $master_product_term_ids ) ) {
					$slave_product_term_ids[] = intval( $slave_product_term_id );
				}

				continue;
			}

			$term_data = term_exists(
				$data['master_product_terms'][ $master_product_term_id ]->name,
				$data['master_product_terms'][ $master_product_term_id ]->taxonomy,
				$slave_product_parent_term_id
			);

			/** fix a bug where divi copy theme settings from parent site to child site */
			if ( has_filter( 'created_term', 'et_pb_force_regenerate_templates' ) ) {
				remove_filter( 'created_term', 'et_pb_force_regenerate_templates', 10 );
			}

			if ( is_null( $term_data ) ) {
				$term_data = wp_insert_term(
					$data['master_product_terms'][ $master_product_term_id ]->name,
					$data['master_product_terms'][ $master_product_term_id ]->taxonomy,
					array(
						'alias_of'    => $data['master_product_terms'][ $master_product_term_id ]->term_group,
						'description' => $data['master_product_terms'][ $master_product_term_id ]->description,
						'parent'      => $slave_product_parent_term_id,
						'slug'        => $data['master_product_terms'][ $master_product_term_id ]->slug,
					)
				);

				if ( is_wp_error( $term_data ) ) {
					$this->log( $term_data, __LINE__, 'error' );

					continue;
				}
			}

			// if not parent term id
			if ( in_array( $master_product_term_id, $master_product_term_ids ) ) {
				$term_id                  = is_array( $term_data ) ? $term_data['term_id'] : $term_data;
				$slave_product_term_ids[] = intval( $term_id );
			}

			$terms_mapping[ $master_product_blog_id ][ $master_product_term_id ] = intval( $term_data['term_id'] );
			$update_terms_mapping = true;
		}

		if ( $update_terms_mapping ) {
			update_option( 'terms_mapping', $terms_mapping, false );
		}

		return $slave_product_term_ids;
	}

	/**
	 * @param array         $data Slave product options.
	 * @param integer|array $master_product_image_id
	 *
	 * @return int|array
	 */
	public function get_slave_product_mapped_image_id( $data, $master_product_image_id ) {
		global $wpdb;

		$master_product_blog_id = $data['master_product_blog_id'];

		// get mapped images
		/** @var array $images_mapping [parent_site_image_id] = to_site_image_id */
		$images_mapping = get_option( 'images_mapping', array() );

		$slave_product_image_ids = array();
		$update_images_mapping   = false;
		foreach ( (array) $master_product_image_id as $_master_product_image_id ) {
			if ( isset( $images_mapping[ $master_product_blog_id ][ $_master_product_image_id ] ) ) {
				/**
				 * Check if the image exists
				 */
				$attachment_path = get_attached_file( $images_mapping[ $master_product_blog_id ][ $_master_product_image_id ] );

				if ( ! empty( $attachment_path ) && file_exists( $attachment_path ) ) {
					$slave_product_image_ids[] = $images_mapping[ $master_product_blog_id ][ $_master_product_image_id ];
					continue;
				}
			}

			// get master image name
			$master_product_blog_prefix = $wpdb->get_blog_prefix( $data['master_product_blog_id'] );

			$query        = "
				SELECT post_content, post_excerpt
				FROM {$master_product_blog_prefix}posts
				WHERE ID=%d";
			$results      = $wpdb->get_row( $wpdb->prepare( $query, $_master_product_image_id ) );
			$post_content = empty( $results->post_content ) ? '' : $results->post_content;
			$post_excerpt = empty( $results->post_excerpt ) ? '' : $results->post_excerpt;

			$query                = "
				SELECT meta_key, meta_value
				FROM {$master_product_blog_prefix}postmeta
				WHERE post_id=%d AND meta_key IN ('_wp_attachment_image_alt', '_wp_attached_file')";
			$results              = $wpdb->get_results( $wpdb->prepare( $query, $_master_product_image_id ), OBJECT_K );
			$master_attached_file = empty( $results['_wp_attached_file'] ) ? '' : $results['_wp_attached_file']->meta_value;
			$image_alt            = empty( $results['_wp_attachment_image_alt'] ) ? '' : $results['_wp_attachment_image_alt']->meta_value;

			if ( empty( $master_attached_file ) ) {
				continue;
			}

			// get master image full name
			$master_attached_file = $data['master_product_upload_dir']['basedir'] . DIRECTORY_SEPARATOR . $master_attached_file;
			if ( ! is_readable( $master_attached_file ) ) {
				continue;
			}

			// copy master image to slave image
			$file_name = basename( $master_attached_file );
			$upload    = wp_upload_bits( $file_name, '', file_get_contents( $master_attached_file ) );
			if ( $upload['error'] ) {
				continue;
			}

			$attachment_id = wc_rest_set_uploaded_image_as_attachment( $upload, is_array( $master_product_image_id ) ? 0 : $data['slave_product']->get_id() );

			$wpdb->update(
				$wpdb->posts,
				array(
					'post_content' => $post_content,
					'post_excerpt' => $post_excerpt,
				),
				array( 'ID' => $attachment_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $image_alt );

			$slave_product_image_ids[] = $attachment_id;
			$images_mapping[ $master_product_blog_id ][ $_master_product_image_id ] = $attachment_id;
			$update_images_mapping = true;
		}

		if ( $update_images_mapping ) {
			update_option( 'images_mapping', $images_mapping, false );
		}

		$slave_product_image_ids = array_filter( $slave_product_image_ids );

		return is_array( $master_product_image_id ) ? $slave_product_image_ids : ( empty( $slave_product_image_ids ) ? '' : $slave_product_image_ids[0] );
	}

	public function get_product_terms( $product_id ) {
		global $wpdb;

		$blog_prefix = $wpdb->get_blog_prefix( get_current_blog_id() );

		$query = "
			SELECT t.*, tt.*
			FROM {$blog_prefix}term_taxonomy      AS tt
			JOIN {$blog_prefix}terms              AS t ON tt.term_id=t.term_id
			WHERE tt.taxonomy='product_cat'
			UNION
			SELECT t.*, tt.*
			FROM {$blog_prefix}term_relationships AS tr
			JOIN {$blog_prefix}term_taxonomy      AS tt ON tr.term_taxonomy_id=tt.term_taxonomy_id
			JOIN {$blog_prefix}terms              AS t  ON tt.term_id=t.term_id
			WHERE tr.object_id=%d AND tt.taxonomy NOT IN ('product_cat', 'product_type')";

		$product_terms = $wpdb->get_results( $wpdb->prepare( $query, $product_id ), OBJECT_K );

		return $product_terms;
	}

	/**
	 * Recursive arrays diff
	 *
	 * @param array $aArray1
	 * @param array $aArray2
	 *
	 * @return array
	 */
	public function array_diff( $aArray1, $aArray2 ) {
		$aReturn = array();

		foreach ( $aArray1 as $mKey => $mValue ) {
			if ( array_key_exists( $mKey, $aArray2 ) ) {
				if ( is_array( $mValue ) ) {
					$aRecursiveDiff = $this->array_diff( $mValue, $aArray2[ $mKey ] );
					if ( count( $aRecursiveDiff ) ) {
						$aReturn[ $mKey ] = $aRecursiveDiff;
					}
				} else {
					if ( $mValue != $aArray2[ $mKey ] ) {
						$aReturn[ $mKey ] = $mValue;
					}
				}
			} else {
				$aReturn[ $mKey ] = $mValue;
			}
		}

		return $aReturn;
	}

	/**
	 * @param $args
	 *
	 * @return int|WP_Error
	 */
	public function create_slave_product_attribute( $args ) {
		global $wpdb;

		$blog_prefix = $wpdb->get_blog_prefix();

		if ( $wpdb->get_results(
			"SELECT * FROM {$blog_prefix}woocommerce_attribute_taxonomies WHERE attribute_name='{$args->attribute_name}'"
		) ) {
			return new WP_Error(
				'invalid_product_attribute_slug_already_exists',
				sprintf(
					__( 'Slug "%s" is already in use. Change it, please.', 'woocommerce' ),
					$args->attribute_name
				),
				array( 'status' => 400 )
			);
		}

		$data = array(
			'attribute_label'   => $args->attribute_label,
			'attribute_name'    => $args->attribute_name,
			'attribute_type'    => $args->attribute_type,
			'attribute_orderby' => $args->attribute_orderby,
			'attribute_public'  => $args->attribute_public,
		);

		$results = $wpdb->insert(
			$wpdb->prefix . 'woocommerce_attribute_taxonomies',
			$data,
			array( '%s', '%s', '%s', '%s', '%d' )
		);

		if ( is_wp_error( $results ) ) {
			/** @var WP_Error $results */
			return new WP_Error(
				'cannot_create_attribute',
				$results->get_error_message(),
				array( 'status' => 400 )
			);
		}

		$id = $wpdb->insert_id;

		/**
		 * Attribute added.
		 *
		 * @param int   $id   Added attribute ID.
		 * @param array $data Attribute data.
		 */
		do_action( 'woocommerce_attribute_added', $id, $data );

		// Clear cache and flush rewrite rules.
		wp_schedule_single_event( time(), 'woocommerce_flush_rewrite_rules' );
		delete_transient( 'wc_attribute_taxonomies' );

		return $id;
	}

	/**
	 * @param WC_Product $master_product
	 *
	 * @return array
	 */
	public function get_master_product_meta_to_exclude( $master_product ) {
		$meta_keys = array();

		if ( $master_product->get_meta( '_woonet_network_unassigned_site_id' ) ) {
			$meta_keys[] = '_woonet_network_unassigned_site_id';
		}

		if ( $master_product->get_meta( '_woonet_network_unassigned_product_id' ) ) {
			$meta_keys[] = '_woonet_network_unassigned_product_id';
		}

		return apply_filters( 'WOO_MSTORE_admin_product/master_product_meta_to_exclude', $meta_keys, $master_product );
	}

	/**
	 * @param WC_Product $master_product
	 *
	 * @return mixed
	 */
	public function get_master_product_meta_to_update( $master_product ) {
		$meta_data = array();

		if (
			// do not change master product meta on slave product update
			! doing_action( 'WOO_MSTORE_admin_product/process_slave_product' )
			&&
			// do not change master product meta on checkout
			( ! defined( 'WOOCOMMERCE_CHECKOUT' ) || false == WOOCOMMERCE_CHECKOUT )
		) {
			if ( empty( $master_product->get_meta( '_woonet_network_main_product' ) ) ) {
				$meta_data['_woonet_network_main_product'] = 'true';
			}

			$blog_ids = $this->functions->get_active_woocommerce_blog_ids();
			foreach ( $blog_ids as $blog_id ) {
				$key = '_woonet_publish_to_' . $blog_id;
				if ( get_current_blog_id() == $blog_id ) {
					$value = 'no';
				} elseif ( empty( $_REQUEST[ $key ] ) ) {
					continue;
					/*
									  if ( isset( $_REQUEST['bulk_edit'] ) ) {
						continue;
					} else {
						$value = 'no';
					}*/
				} else {
					$value = wc_bool_to_string( $_REQUEST[ $key ] );
				}
				if ( $master_product->get_meta( $key ) != $value ) {
					$meta_data[ $key ] = $value;
				}
			}
		}

		return apply_filters( 'WOO_MSTORE_admin_product/master_product_meta_to_update', $meta_data, $master_product );
	}

	public function synchronize_category_changes( $data ) {
		global $WOO_MSTORE;

		if ( $WOO_MSTORE ) {
			$slave_product_blog_id = get_current_blog_id();

			if ( 'yes' === $data['options']['child_inherit_changes_fields_control__category_changes'][ $slave_product_blog_id ] ) {
				switch_to_blog( $data['master_product_blog_id'] );

				foreach ( $data['master_product']->get_category_ids() as $master_product_category_id ) {
					$WOO_MSTORE->network_category_interface->republish_category_changes( $master_product_category_id, array( $slave_product_blog_id ) );
				}

				restore_current_blog();
			}
		}
	}

	public function update_terms_mapping_on_term_delete( $term_id, $tt_id, $taxonomy ) {
		if (
			0 !== strpos( $taxonomy, 'pa_' )
			&&
			'product_cat' != $taxonomy
			&&
			'product_tag' != $taxonomy
		) {
			return;
		}

		// get mapped terms
		$terms_mapping = get_option( 'terms_mapping', array() );

		foreach ( $terms_mapping as $master_product_blog_id => $blog_terms_mapping ) {
			if ( is_array( $blog_terms_mapping ) ) {
				foreach ( $blog_terms_mapping as $master_product_term_id => $slave_product_term_id ) {
					if ( $slave_product_term_id == $term_id ) {
						unset( $terms_mapping[ $master_product_blog_id ][ $master_product_term_id ] );
						$update_terms_mapping = true;
					}
				}
			} else {
				// unused old terms mapping
				unset( $terms_mapping[ $master_product_blog_id ] );
				$update_terms_mapping = true;
			}
		}

		if ( ! empty( $update_terms_mapping ) ) {
			update_option( 'terms_mapping', $terms_mapping, false );
		}
	}

	public function update_attachments_mapping_on_attachment_delete( $attachment_id ) {
		// get mapped images
		$images_mapping = get_option( 'images_mapping', array() );

		foreach ( $images_mapping as $master_product_blog_id => $blog_images_mapping ) {
			if ( is_array( $blog_images_mapping ) ) {
				foreach ( $blog_images_mapping as $master_product_image_id => $slave_product_image_id ) {
					if ( $slave_product_image_id == $attachment_id ) {
						unset( $images_mapping[ $master_product_blog_id ][ $master_product_image_id ] );
						$update_images_mapping = true;
					}
				}
			} else {
				// unused old images mapping
				unset( $images_mapping[ $master_product_blog_id ] );
				$update_images_mapping = true;
			}
		}

		if ( ! empty( $update_images_mapping ) ) {
			update_option( 'images_mapping', $images_mapping, false );
		}
	}

	// republish all product reviews
	public function republish_slave_product_reviews( $data ) {
		global $wpdb;

		$master_blog_prefix = $wpdb->get_blog_prefix( $data['master_product_blog_id'] );

		// get all comments
		$query           = "SELECT * FROM {$master_blog_prefix}comments WHERE comment_post_ID=%d";
		$master_comments = $wpdb->get_results( $wpdb->prepare( $query, $data['master_product']->get_id() ), OBJECT_K );

		// get comments meta
		$query = "SELECT * FROM {$master_blog_prefix}commentmeta WHERE comment_id=%d";
		foreach ( $master_comments as $comment_id => $comment ) {
			$master_comments[ $comment_id ] = (array) $comment;

			$comment_meta = $wpdb->get_results( $wpdb->prepare( $query, $comment_id ), ARRAY_A );
			foreach ( $comment_meta as $meta_data ) {
				$master_comments[ $comment_id ]['comment_meta'][ $meta_data['meta_key'] ] = $meta_data['meta_value'];
			}
		}

		$this->republish_reviews( $master_comments, $data['master_product_blog_id'] );
	}

	// republish new product review
	public function republish_review( $comment_id, $comment_approved, $commentdata ) {
		global $wpdb;

		$master_product = wc_get_product( $commentdata['comment_post_ID'] );
		if ( empty( $master_product ) || $this->is_slave_product( $master_product ) ) {
			return;
		}

		// prepare comments structure
		$master_comments                              = array(
			$comment_id => $commentdata,
		);
		$master_comments[ $comment_id ]['comment_ID'] = $comment_id;

		// get comment meta
		$comment_meta = $wpdb->get_results( "SELECT * FROM {$wpdb->commentmeta} WHERE comment_id={$comment_id}", ARRAY_A );
		foreach ( $comment_meta as $meta_data ) {
			$master_comments[ $comment_id ]['comment_meta'][ $meta_data['meta_key'] ] = $meta_data['meta_value'];
		}

		$master_product_blog_id = get_current_blog_id();
		$blog_ids               = $this->functions->get_active_woocommerce_blog_ids();
		foreach ( $blog_ids as $slave_product_blog_id ) {
			if (
				$master_product_blog_id == $slave_product_blog_id
				||
				'yes' !== $master_product->get_meta( '_woonet_publish_to_' . $slave_product_blog_id )
			) {
				continue;
			}

			switch_to_blog( $slave_product_blog_id );

			$this->republish_reviews( $master_comments, $master_product_blog_id );

			restore_current_blog();
		}
	}

	// republish reviews
	public function republish_reviews( $master_comments, $master_blog_id ) {
		global $wpdb;

		$options = $this->functions->get_options();

		// Child product inherit categories changes
		if (
			empty( $options['child_inherit_changes_fields_control__reviews'][ get_current_blog_id() ] )
			||
			'no' == $options['child_inherit_changes_fields_control__reviews'][ get_current_blog_id() ]
		) {
			return;
		}

		foreach ( $master_comments as $comment ) {
			// check comment is already replicated
			$query            = sprintf(
				'SELECT comment_id FROM %s WHERE meta_key="_woonet_network_is_child_comment_id" AND meta_value="%d_%d"',
				$wpdb->commentmeta,
				$master_blog_id,
				$comment['comment_ID']
			);
			$slave_comment_id = $wpdb->get_var( $query );

			// if comment is not republished yet
			if ( ! $slave_comment_id ) {
				// get slave product id
				$slave_product_id = $this->get_slave_product_id( $master_blog_id, $comment['comment_post_ID'] );

				// prepare comment data to insert
				$comment['comment_post_ID']                                     = $slave_product_id;
				$comment['comment_meta']['_woonet_network_is_child_comment_id'] = $master_blog_id . '_' . $comment['comment_ID'];
				unset( $comment['comment_ID'], $comment['comment_parent'] );

				wp_insert_comment( $comment );
			}
		}
	}

	public function is_editing_product() {
		/**
		 * Saving variations from the edit screen
		 */
		if ( ! empty( $_REQUEST['action'] ) && $_REQUEST['action'] == 'woocommerce_save_variations' ) {
			return true;
		}

		/**
		 * Save request from the edit screen
		 */
		if ( ! empty( $_REQUEST['action'] ) && ! empty( $_REQUEST['post_type'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * The old process product, which was replaced by the new ajax based process product hook
	 * needs to applied conditionally so that stock quantities and backend orders works correctly.
	 */
	public function hook_legacy_process_product( $post_id ) {
		if ( $this->is_editing_product() ) {
			return;
		}

		if ( defined( 'WOO_MULTI_AJAX_PRODUCT_UPDATE' ) ) {
			return;
		}

		return $this->process_product( $post_id );
	}

	/**
	 * check if user is creating new orders from backend
	 */
	public function is_creating_backend_orders() {
		if ( ! empty( $_POST['action'] )
			 && ! empty( $_POST['post_type'] )
			 && $_POST['action'] == 'editpost'
			 && $_POST['post_type'] == 'shop_order'
		) {
			return true;
		}
		return false;
	}

	/**
	 * When a product is duplicated using WooCommerce, delete the metadata related to the plugin
	 * so that the new product is no longer linked to the old child products.
	 *
	 * @since 3.0.6
	 */
	public function unlink_duplicated_product( $duplicate, $product ) {
		if ( get_post_meta( $duplicate->get_id(), '_woonet_network_main_product' ) ) {
			// main product
			$sites = $this->functions->get_sites();

			if ( ! empty( $sites ) ) {
				foreach ( $sites as $site_id ) {
					delete_post_meta( $duplicate->get_id(), '_woonet_publish_to_' . $site_id );
				}
			}

			delete_post_meta( $duplicate->get_id(), '_woonet_network_main_product' );
		} else {
			// child product
			delete_post_meta( $duplicate->get_id(), '_woonet_network_is_child_site_id' );
			delete_post_meta( $duplicate->get_id(), '_woonet_network_is_child_product_id' );
			delete_post_meta( $duplicate->get_id(), '_woonet_child_inherit_updates' );
			delete_post_meta( $duplicate->get_id(), '_woonet_child_stock_synchronize' );

			// If product is variable, remove the metadata from variations as well
			if ( $duplicate->get_type() == 'variable' && ! empty( $duplicate->get_children() ) ) {
				foreach ( $duplicate->get_children() as $variation_id ) {
					// child product
					delete_post_meta( $variation_id, '_woonet_network_is_child_site_id' );
					delete_post_meta( $variation_id, '_woonet_network_is_child_product_id' );
					delete_post_meta( $variation_id, '_woonet_child_inherit_updates' );
					delete_post_meta( $variation_id, '_woonet_child_stock_synchronize' );
				}
			}
		}
	}

	/**
	 * Get store IDs
	 */
	public function get_store_ids_filter( $ids = array() ) {
		return $this->functions->get_active_woocommerce_blog_ids();
	}
}
