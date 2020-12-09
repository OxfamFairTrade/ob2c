<?php

	if ( ! defined('ABSPATH') ) exit;

	use Automattic\WooCommerce\Client;
	use Automattic\WooCommerce\HttpClient\HttpClientException;

	// Verwijder originelen uit srcset (wordt opgepikt door zoekmachines!)
	add_filter( 'wp_calculate_image_srcset_meta', 'ob2c_remove_large_images_from_srcset' );

	function ob2c_remove_large_images_from_srcset( $image_meta ) {
		if ( ! is_array( $image_meta ) ) {
			return $image_meta;
		}
		
		// write_log( print_r( $image_meta['sizes'], true ) );
		if ( ! empty($image_meta['sizes'] ) ) {
			if ( ! empty( $image_meta['sizes']['wc_order_status_icon'] ) ) {
				unset( $image_meta['sizes']['wc_order_status_icon'] );
			}
			if ( ! empty( $image_meta['sizes']['nm_quick_view'] ) ) {
				unset( $image_meta['sizes']['nm_quick_view'] );
			}
			if ( ! empty( $image_meta['sizes']['large'] ) ) {
				unset( $image_meta['sizes']['large'] );
			}
		}
		
		return $image_meta;
	}

	// Was vroeger 6.5566;
	define( 'REDUCED_VAT_SHIPPING_COST', 4.6698 );
	// Was vroeger 5.7438;
	define( 'STANDARD_VAT_SHIPPING_COST', 4.0909 );

	// Alle subsites opnieuw indexeren m.b.v. WP-CLI: wp site list --field=url | xargs -n1 -I % wp --url=% relevanssi index
	// DB-upgrade voor WooCommerce op alle subsites laten lopen: wp site list --field=url | xargs -n1 -I % wp --url=% wc update

	// Schrijf shortcodes uit in WooCommerce Local Pickup Plus 2.9+
	add_filter( 'wc_local_pickup_plus_pickup_location_description', 'do_shortcode' );
	add_filter( 'wc_local_pickup_plus_pickup_location_phone', 'do_shortcode' );
	// In de 'WC_Local_Pickup_Plus_Address'-klasse zelf zijn geen filters beschikbaar!
	add_filter( 'wc_local_pickup_plus_pickup_location_address', 'ob2c_do_shortcode_on_pickup_address_object' );

	function ob2c_do_shortcode_on_pickup_address_object( $address ) {
		if ( $address instanceof WC_Local_Pickup_Plus_Address ) {
			$array = $address->get_array();
			// Deze key wordt niet opgehaald door get_array(), voorkom dat we de naam wissen door ze opnieuw op te vullen!
			$array['name'] = $address->get_name();
			$array['address_1'] = do_shortcode( $address->get_address_line_1() );
			$array['postcode'] = do_shortcode( $address->get_postcode() );
			$array['city'] = do_shortcode( $address->get_city() );
			$address->set_address( $array );
		}

		// write_log( print_r( $address, true ) );
		return $address;
	}

	// Wijzig de formattering van de dropdownopties
	add_filter( 'wc_local_pickup_plus_pickup_location_option_label', 'change_pickup_location_options_formatting', 10, 3 );

	function change_pickup_location_options_formatting( $name, $context, $pickup_location ) {
		if ( 'frontend' === $context ) {
			$name = 'Oxfam-Wereldwinkel '.$pickup_location->get_name();
		}
		return $name;
	}

	// Laat lokale beheerders enkel lokale producten verwijderen (eventueel vervangen door 'pre_trash_post'-filter, maar die eindigt ook gewoon met wp_die() ...)
	add_action( 'wp_trash_post', 'disable_manual_product_removal', 10, 1 );

	function disable_manual_product_removal( $post_id ) {
		if ( get_post_type( $post_id ) == 'product' ) {
			if ( ! wp_doing_cron() and ! current_user_can('update_core') ) {
				if ( ! in_array( get_post_field( 'post_author', $post_id ), get_local_manager_user_ids() ) ) {
					wp_die( sprintf( 'Uit veiligheidsoverwegingen is het verwijderen van nationale producten door lokale beheerders niet toegestaan! Oude producten worden verwijderd van zodra de laatst uitgeleverde THT-datum verstreken is en/of alle lokale webshopvoorraden opgebruikt zijn.<br/><br/>Keer terug naar %s of mail naar %s indien deze melding volgens jou ten onrechte getoond wordt.', '<a href="'.wp_get_referer().'">de vorige pagina</a>', '<a href="mailto:'.get_site_option('admin_email').'">'.get_site_option('admin_email').'</a>' ) );
				}
			}
		}
	}

	// Met deze filter kunnen we het winkeladres in CC zetten bij een afhaling!
	// add_filter( 'wc_local_pickup_plus_pickup_location_email_recipients', 'add_shop_email' );

	// Gebruik deze actie om de hoofddata te tweaken (na de switch_to_blog(), net voor het effectief opslaan in de subsite) VOORLOPIG NOG UITSCHAKELEN
	// add_action( 'threewp_broadcast_broadcasting_before_restore_current_blog', 'localize_broadcasted_custom_fields' );

	function localize_broadcasted_custom_fields( $action ) {
		$bcd = $action->broadcasting_data;

		if ( 'shop_coupon' === $bcd->modified_post->post_type ) {
			write_log( "GLOBAL COUPON ID: ".$bcd->parent_post_id );
			write_log( print_r( $bcd->modified_post, true ) );
			
			$custom_fields_to_translate = array( 'product_ids', 'exclude_product_ids', '_wjecf_free_product_ids' );
			foreach ( $custom_fields_to_translate as $meta_key ) {
				if ( array_key_exists( $meta_key, $bcd->post_custom_fields ) ) {
					$localized_values = broadcast_master_to_slave_ids( $meta_key, $bcd->post_custom_fields[ $meta_key ][0] );
					$bcd->custom_fields()->child_fields()->update_meta( $meta_key, $localized_values );
				}
			}
		}
	}

	// Verberg extra metadata op het orderdetail in de back-end
	add_filter( 'woocommerce_hidden_order_itemmeta', function( $forbidden ) {
		$forbidden[] = '_shipping_item_id';
		return $forbidden;
	}, 10, 1 );

	// Vergt het activeren van de 'Posts edit access'-module én het toekennen van 'edit_others_products'!
	add_filter( 'ure_post_edit_access_authors_list', 'ure_modify_authors_list', 10, 1 );
	
	function ure_modify_authors_list( $authors ) {
		// Producten die aangemaakt werden door een user die inmiddels beheerder af is, zullen onbewerkbaar worden!
		// TO DO: Bij het degraderen van een user de auteur van zijn/haar producten aanpassen via 'set_user_role'-actie? 
		if ( count( get_local_manager_user_ids() ) > 0 ) {
			return $authors . ',' . get_local_manager_user_ids( true );
		} else {
			return $authors;
		}
	}

	function get_local_manager_user_ids( $implode = false ) {
		$local_managers = new WP_User_Query( array( 'role' => 'local_manager', 'fields' => 'ID' ) );
		if ( count( $local_managers->get_results() ) > 0 ) {
			if ( current_user_can('update_core') ) {
				write_log( "Local managers blog-ID ".get_current_blog_id().": ".implode( ', ', $local_managers->get_results() ) );
			}

			if ( $implode ) {
				return implode( ',', $local_managers->get_results() );
			} else {
				return $local_managers->get_results();
			}
		} else {
			// In principe is er altijd minstens één lokale beheerder, maar wie weet!
			return array();
		}
	}

	// 'Posts edit access'-module blokkeert automatisch ook het inkijken van andere post types zoals orders!
	add_filter( 'ure_restrict_edit_post_type', 'exclude_posts_from_edit_restrictions' );
	
	function exclude_posts_from_edit_restrictions( $post_type ) {
		$restrict_it = false;
		if ( $post_type === 'product' ) {
			$user = wp_get_current_user();
			if ( in_array( 'local_manager', $user->roles ) ) {
				$restrict_it = true;
			}
		}
		return $restrict_it;
	}

	// Lijst ook de posts op die de gebruiker niét kan bewerken
	add_filter( 'ure_posts_show_full_list', '__return_true' );

	// Enkel admins mogen producten dupliceren
	add_filter( 'woocommerce_duplicate_product_capability', function( $cap ) {
		return 'manage_options';
	}, 10, 1 );

	// Disable bulk edit van producten
	add_filter( 'bulk_actions-edit-product', function( $actions ) {
		// var_dump_pre( $actions);
		if ( ! current_user_can('manage_options') and array_key_exists( 'edit', $actions ) ) {
			unset( $actions['edit'] );
		}
		return $actions;
	}, 10, 1 );

	// Disable quick edit van producten
	add_filter( 'post_row_actions', 'remove_quick_edit', 10, 2 );

	function remove_quick_edit( $actions, $post ) {
		if ( get_post_type( $post ) === 'product' ) {
			// var_dump_pre( $actions );
			if ( ! current_user_can('manage_options') and array_key_exists( 'inline hide-if-no-js', $actions ) ) {
				unset( $actions['inline hide-if-no-js'] );
			}
		}
		return $actions;
	}

	// Verwijder overbodige productopties
	add_filter( 'product_type_selector', function( $types ) {
		unset( $types['grouped'] );
		unset( $types['external'] );
		unset( $types['variable'] );
		return $types;
	}, 10, 1 );

	add_filter( 'product_type_options', function( $options ) {
		unset( $options['virtual'] );
		unset( $options['downloadable'] );
		return $options;
	}, 10, 1 );

	add_action( 'woocommerce_product_options_general_product_data', 'add_oxfam_general_product_fields', 5 );
	add_action( 'woocommerce_product_options_inventory_product_data', 'add_oxfam_inventory_product_fields', 5 );
	add_action( 'woocommerce_process_product_meta_simple', 'save_oxfam_product_fields' );

	function add_oxfam_general_product_fields() {
		echo '<div class="options_group oxfam">';

			$net_unit_args = array( 
				'id' => '_net_unit',
				'label' => 'Inhoudsmaat',
				'desc_tip' => true,
				'description' => 'Selecteer de maateenheid (optioneel). Dit is noodzakelijk als je de eenheidsprijs wil laten berekenen en tonen op de productpagina (wettelijk verplicht bij voedingsproducten).',
				'options' => array(
					'' => '(selecteer)',
					'g' => 'gram (vast product)',
					'cl' => 'centiliter (vloeibaar product)',
				),
			);

			$net_content_args = array( 
				'id' => '_net_content',
				'label' => 'Netto-inhoud',
				'type' => 'number',
				'desc_tip' => true,
				'description' => 'Geef de netto-inhoud van de verpakking in volgens de eenheid die je hierboven selecteerde. Reken kilo dus om naar gram (x1000), liter naar centiliter (x100), milliliter naar centiliter (:10), ... anders zal de automatische berekening van de eenheidsprijs niet correct zijn.',
				'custom_attributes' => array(
					'min' => '0',
					'max' => '10000',
				),
			);

			$fairtrade_share_args = array( 
				'id' => '_fairtrade_share',
				'label' => 'Fairtradepercentage (%)',
				'type' => 'number',
				'desc_tip' => true,
				'description' => 'Geef aan welk gewichtspercentage van de ingrediënten verhandeld is volgens de principes van eerlijke handel (optioneel).',
				'custom_attributes' => array(
					'min' => '0',
					'max' => '100',
				),
			);

			global $product_object;
			if ( is_national_product( $product_object ) ) {
				$net_unit_args['custom_attributes']['disabled'] = true;
				$net_content_args['custom_attributes']['readonly'] = true;
				$fairtrade_share_args['custom_attributes']['readonly'] = true;
			}
			
			woocommerce_wp_select( $net_unit_args );
			woocommerce_wp_text_input( $net_content_args);
			echo '<p class="form-field"><small>Is het product geen gewichtartikel maar wil je wel aanduiden dat het bv. uit 3 onderdelen bestaat? Laat bovenstaande velden dan leeg en gebruik het veld \'Netto-inhoud\' op het tabblad \'Eigenschappen\'.</small></p>';
			woocommerce_wp_text_input( $fairtrade_share_args);

		echo '</div>';
	}

	function add_oxfam_inventory_product_fields() {
		echo '<div class="options_group oxfam">';

			// In de subsites tonen we enkel 'hét' artikelnummer
			if ( is_main_site() ) {
				woocommerce_wp_text_input(
					array( 
						'id' => '_shopplus_code',
						'label' => 'ShopPlus',
					)
				);

				woocommerce_wp_select(
					array( 
						'id' => '_in_bestelweb',
						'label' => 'In BestelWeb?',
						'options' => array(
							'' => '(selecteer)',
							'ja' => 'ja',
							'nee' => 'nee',
						),
					)
				);
			}

			$cu_ean_args = array( 
				'id' => '_cu_ean',
				'label' => 'Barcode',
				'type' => 'number',
				'wrapper_class' => 'wide',
				'desc_tip' => true,
				'description' => 'Vul de barcode in zoals vermeld op de verpakking (optioneel). Deze barcode zal opgenomen worden in de pick-Excel voor import in ShopPlus.',
				'custom_attributes' => array(
					'min' => '10000',
					'max' => '99999999999999',
				),
			);

			$multiple_args = array( 
				'id' => '_multiple',
				'label' => 'Verpakt per',
				'type' => 'number',
				'desc_tip' => true,
				'description' => 'Geef aan hoeveel consumenteneenheden er in één ompak zitten (optioneel). Enkel van belang voor geregistreerde B2B-klanten, die producten standaard per ompak toevoegen aan hun winkelmandje.',
				'custom_attributes' => array(
					'min' => '1',
					'max' => '100',
				),
			);

			global $product_object;
			if ( is_national_product( $product_object ) ) {
				$cu_ean_args['custom_attributes']['readonly'] = true;
				$multiple_args['custom_attributes']['readonly'] = true;
			}
			
			woocommerce_wp_text_input( $cu_ean_args );
			woocommerce_wp_text_input( $multiple_args );

		echo '</div>';
	}

	function save_oxfam_product_fields( $post_id ) {
		// Logica niet doorlopen tijdens imports, ontbreken van $_POST veroorzaakt verdwijnen van metadata
		if ( get_site_option('oft_import_active') === 'yes' ) {
			return;
		}

		$regular_meta_keys = array();

		// Of kijken naar waarde $_POST['_woonet_child_inherit_updates'] / werken met woocommerce_wp_hidden_input()?
		if ( ! is_national_product( $post_id ) ) {
			// Deze velden zijn enkel bewerkbaar (en dus aanwezig in $_POST) indien lokaal product
			$regular_meta_keys[] = '_cu_ean';
			$regular_meta_keys[] = '_multiple';
			$regular_meta_keys[] = '_net_unit';
			$regular_meta_keys[] = '_net_content';
			$regular_meta_keys[] = '_fairtrade_share';
		}

		if ( is_main_site() ) {
			// Deze velden zijn enkel zichtbaar (en dus aanwezig in $_POST) op hoofdniveau
			$regular_meta_keys[] = '_shopplus_code';
			$regular_meta_keys[] = '_in_bestelweb';
		} else {
			// Bereken - indien mogelijk - de eenheidsprijs a.d.h.v. alle data in $_POST
			update_unit_price( $post_id, $_POST['_regular_price'], $_POST['_net_content'], $_POST['_net_unit'] );
			
			// Synchroniseer niet-numerieke artikelnummers naar het ShopPlus-nummer
			if ( ! empty( $_POST['_sku'] ) and ! is_numeric( $_POST['_sku'] ) ) {
				update_post_meta( $post_id, '_shopplus_code', $_POST['_sku'] );
			}
		}

		foreach ( $regular_meta_keys as $meta_key ) {
			if ( isset( $_POST[ $meta_key ] ) ) {
				update_post_meta( $post_id, $meta_key, sanitize_text_field( $_POST[$meta_key] ) );
			} else {
				update_post_meta( $post_id, $meta_key, '' );
			}
		}

		// if ( is_main_site() ) {
		// 	$product = wc_get_product( $post_id );
		// 	if ( $product !== false ) {
		// 		$logger = wc_get_logger();
		// 		$context = array( 'source' => 'Oxfam Manual Product Sync' );
					
		// 		if ( $product->get_meta('_multiple') === '' ) {
		// 			// Het is een hoofdproduct dat nog niet omgezet is naar de nieuwe datastructuur IN PRINCIPE NIET MEER NODIG
		// 			$to_migrate = array(
		// 				'shopplus' => '_shopplus_code',
		// 				'ean' => '_cu_ean',
		// 				'ompak' => '_multiple',
		// 				'eenheid' => '_stat_uom',
		// 				'fairtrade' => '_fairtrade_share',
		// 				'eprijs' => '_unit_price',
		// 			);
		// 		} else {
		// 			// Eenheidsprijs moet nog verhuisd worden in import!
		// 			$to_migrate = array(
		// 				'eprijs' => '_unit_price',
		// 			);
		// 		}

		// 		foreach ( $to_migrate as $attribute => $meta_key ) {
		// 			$migrated_values[] =  $meta_key.': '.$product->get_attribute( $attribute );
		// 			$product->update_meta_data( $meta_key, $product->get_attribute( $attribute ) );
		// 		}

		// 		$logger->info( 'Migrating SKU '.$product->get_sku().' to new data structure ('.implode( ', ', $migrated_values ).')', $context );
		// 		$product->save();
		// 	}
		// }
	}

	function update_unit_price( $post_id, $price = false, $content = false, $unit = false ) {
		$product = wc_get_product( $post_id );
		if ( $product !== false ) {
			// Waarde voor $content eventueel uit $product->get_attribute('inhoud') halen maar daar zit de eenheid ook al bij ...
			if ( false !== ( $unit_price = calculate_unit_price( $price, $content, $unit ) ) ) {
				$product->update_meta_data( '_unit_price', number_format( $unit_price, 2, '.', '' ) );
			} else {
				// Indien er een gegeven ontbreekt: verwijder sowieso de oude waarde
				$product->delete_meta_data('_unit_price');
			}
			$product->save();
		}
	}

	function calculate_unit_price( $price, $content, $unit ) {
		$unit_price = false;
		
		// empty() checkt meteen ook op nulwaardes!
		if ( ! empty( $price ) and ! empty( $content ) and ! empty( $unit ) ) {
			$unit_price = floatval( str_replace( ',', '.', $price ) ) / floatval( $content );
			if ( $unit === 'g' ) {
				$unit_price *= 1000;
			} elseif ( $unit === 'cl' ) {
				$unit_price *= 100;
			}
		}
		
		return $unit_price;
	}

	// Stuur mail uit bij publicatie van nieuw lokaal product
	add_action( 'draft_to_publish', 'notify_on_local_product_creation', 10, 1 );
	// add_action( 'publish_product', 'notify_on_local_product_creation_bis', 10, 2 );
	
	function notify_on_local_product_creation( $post ) {
		if ( ! is_main_site() and $post->post_type === 'product' ) {
			if ( ! is_national_product( $post->ID ) ) {
				$product = wc_get_product( $post->ID );
				if ( $product !== false ) {
					send_automated_mail_to_helpdesk( 'Nieuw lokaal product ('.$product->get_sku().'): '.$product->get_name(), '<p>Bekijk het product <a href="'.$product->get_permalink().'">in de front-end</a>.</p>' );
				}
			}
		}
	}

	function notify_on_local_product_creation_bis( $post_id, $post ) {
		if ( ! is_main_site() ) {
			if ( ! is_national_product( $post_id ) ) {
				send_automated_mail_to_helpdesk( 'Nieuw lokaal product: '.get_the_title( $post ), '<p>Bekijk het product <a href="'.get_permalink( $post ).'">in de front-end</a>.</p>' );
			}
		}
	}

	// Verberg voorlopig gewoon de hele <div> voor tags!
	// add_action( 'pre_insert_term', function( $term, $taxonomy ) {
	// 	return ( 'product_tag' === $taxonomy ) ? new WP_Error( 'term_addition_blocked', 'Aanmaak van nieuwe producttags is verboden' ) : $term;
	// }, 1, 2 );

	// Herbenoem de default voorraadstatussen
	add_filter( 'woocommerce_product_stock_status_options', function( $statuses ) {
		$statuses['instock'] = 'Op voorraad';
		$statuses['onbackorder'] = 'Tijdelijk uit voorraad';
		$statuses['outofstock'] = 'Niet in assortiment';
		// Dit is toevallig de gewenste volgorde
		ksort( $statuses );
		return $statuses;
	}, 10, 1 );

	// In de back-end worden de labels op een andere manier opgehaald ...
	add_filter( 'woocommerce_admin_stock_html', function( $stock_html ) {
		$stock_html = str_replace( 'In nabestelling', 'Tijdelijk uit voorraad', $stock_html );
		$stock_html = str_replace( 'Uitverkocht', 'Niet in assortiment', $stock_html );
		return $stock_html;
	}, 10, 1 );

	// Limiteer de grootte van het ingezoomde packshot in de WooCommerce galerij
	add_filter( 'woocommerce_gallery_full_size', function( $size ) {
		return 'large';
	} );

	// Limiteer de grootte van packshots in de loop
	add_filter( 'woocommerce_product_thumbnails_large_size', function( $size ) {
		return 'medium';
	} );

	// Neem supergrote afbeeldingen nooit mee in de srcset's (kan geïndexeerd geraken door zoekmachines!) 
	add_filter( 'wp_calculate_image_srcset', function( $sources ) {
		foreach ( $sources as $key => $value ) {
			if ( $key > 2000 ) {
				unset( $sources[ $key ] );
			}
		}
		// write_log( print_r( $sources, true ) );
		return $sources;
	} );

	// Parameter om winkelmandje te legen (zonder nonce, dus enkel tijdens debuggen)
	add_action( 'init', function() {
		if ( isset( $_GET['emptyCart'] ) and wp_get_environment_type() !== 'production' ) {
			WC()->cart->empty_cart(true);
		}
	} );

	// Wordt gebruikt in o.a. mini cart en order items
	// Wordt op cataloguspagina's overruled door woocommerce-template-functions.php!
	add_filter( 'woocommerce_product_get_image', 'get_parent_image_if_non_set', 10, 5 );
	
	function get_parent_image_if_non_set( $image, $product, $size, $attr, $placeholder ) {
		if ( ! is_main_site() ) {
			$main_image_id = false;

			if ( ! empty ( $product->get_meta('_main_thumbnail_id') ) ) {
				// Er is een globaal beeld ingesteld
				$main_image_id = $product->get_meta('_main_thumbnail_id');
			} elseif ( in_array( $product->get_sku(), get_oxfam_empties_skus_array() ) ) {
				// Het is een leeggoedartikel
				$main_image_id = 836;
			}
			
			if ( $main_image_id ) {
				switch_to_blog(1);
				// Checkt of de file nog bestaat én een afbeelding is
				if ( wp_attachment_is_image( $main_image_id ) ) {
					// Als we de standaard WooCommerce-formaten oproepen, krijgen we steeds het originele beeld terug ...
					// Heel vreemd, dus switch naar de Savoy-formaten, die volledig identiek zijn!
					switch ( $size ) {
						case 'woocommerce_gallery_thumbnail':
							$size = 'shop_thumbnail';
							break;

						case 'woocommerce_thumbnail':
							$size = 'shop_catalog';
							break;

						case 'woocommerce_single':
							$size = 'shop_single';
							break;
					}
					// Retourneert lege string bij error
					$image = wp_get_attachment_image( $main_image_id, $size, false, $attr );
				}
				restore_current_blog();
			}

			if ( $image === '' ) {
				// $image = wc_placeholder_img( $size, $attr );
			}
		}

		return $image;
	}

	// Wordt gebruikt in o.a. single product
	add_filter( 'woocommerce_single_product_image_thumbnail_html', 'get_single_parent_image_if_non_set', 10, 2 );
	
	function get_single_parent_image_if_non_set( $html, $image_id ) {
		if ( ! is_main_site() ) {
			// Als er lokaal geen productafbeelding beschikbaar is, is op dit ogenblik reeds een placeholder ingevoegd
			// write_log( $image_id );
			// Check of er een globaal beeld ingesteld is
			global $product;
			if ( ! empty ( $product->get_meta('_main_thumbnail_id') ) ) {
				$main_image_id = $product->get_meta('_main_thumbnail_id');
				switch_to_blog(1);
				// Check of de file nog bestaat
				if ( get_post_type( $main_image_id ) === 'attachment' ) {
					// Dit levert nog steeds een source op die de lokale shop bevat, waardoor het beeld fysiek wel identiek is maar toch niet in cache zit ...
					$html = wc_get_gallery_image_html( $main_image_id, true );
				}
				restore_current_blog();
			}
		}
		return $html;
	}

	// Pas winkelmandkorting WVDFT2020-QUINOA toe op totaal i.p.v. subtotaal
	// add_filter( 'wjecf_coupon_can_be_applied', 'apply_coupon_on_total_not_subtotal', 20, 2 );

	function apply_coupon_on_total_not_subtotal( $can_be_applied, $coupon ) {
		if ( $coupon->get_code() === 'wvdft2020-quinoa' ) {
			// Dit geeft het totaal NA kortingen inclusief BTW als float MAAR bevat ook de verzendkosten!
			// write_log( "CART TOTAL: ".WC()->cart->get_total('edit') );
			$totals = WC()->cart->get_totals();
			if ( $totals['cart_contents_total'] + $totals['cart_contents_tax'] < $coupon->get_minimum_amount() ) {
				return false;	
			}
		}

		return $can_be_applied;
	}

	// Probeer wijnduo's enkel toe te passen op gelijke paren (dus niet 3+1, 5+1, 5+3, ...)
	add_filter( 'woocommerce_coupon_get_apply_quantity', 'limit_coupon_to_equal_pairs', 100, 4 );

	function limit_coupon_to_equal_pairs( $apply_quantity, $item, $coupon, $wc_discounts ) {
		if ( is_admin() ) {
			return $apply_quantity;
		}

		if ( in_array( $coupon->get_code(), array( 'duo-argentinie', 'duo-chili', 'duo-zuid-afrika' ) ) ) {
			$this_quantity = 0;
			$other_quantity = 0;
			$old_apply_quantity = $apply_quantity;
			
			// Check of beide vereiste producten in gelijke hoeveelheid aanwezig zijn
			foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
				$product_in_cart = $values['data'];
				
				// Cart item maakt deel uit van de promotie
				if ( in_array( $product_in_cart->get_id(), $coupon->get_product_ids() ) ) {
					if ( $product_in_cart->get_id() === $item->product->get_id() ) {
						$this_quantity = intval( $values['quantity'] );
						$this_sku = intval( $product_in_cart->get_sku() );
					} else {
						$other_quantity = intval( $values['quantity'] );
						$other_sku = intval( $product_in_cart->get_sku() );
					}

					if ( $other_quantity !== 0 and $this_quantity !== 0 ) {
						// We passen de korting VOLLEDIG toe op het kleinste artikelnummer van het duo
						if ( $this_sku < $other_sku ) {
							// Niet meer delen door twee!
							$apply_quantity = min( $this_quantity, $other_quantity );
						} else {
							$apply_quantity = 0;
						}

						// We hebben beide producten gevonden en kunnen afsluiten
						// write_log( "APPLY QUANTITY FOR ".$coupon->get_code()." ON SKU ".$item->product->get_sku().": ".$old_apply_quantity." => ".$apply_quantity );
						break;
					}
				}
			}
		}

		return $apply_quantity;
	}

	add_filter( 'woocommerce_products_admin_list_table_filters', 'sort_categories_by_menu_order', 1000, 1 );

	function sort_categories_by_menu_order( $filters ) {
		$filters['product_category'] = wc_product_dropdown_categories(
			array(
				'option_select_text' => __( 'Filter by category', 'woocommerce' ),
				'hide_empty' => 0,
				// Sorteer volgens onze custom volgorde
				'orderby' => 'menu_order',
			)
		);

		// Verwijder de nutteloze filter van WooMultistore
		if ( array_key_exists( 'parent_child', $filters ) ) {
			unset( $filters['parent_child'] );
		}

		return $filters;
	}

	// Limiteer de afbeeldingsgrootte op subsites
	add_filter( 'big_image_size_threshold', 'reduce_maximum_size_on_subsites', 10, 1 );

	function reduce_maximum_size_on_subsites( $threshold ) {
		if ( is_main_site() ) {
			return false;
		} else {
			// Komt overeen met thumbnail 1536x1536
			return 1536;
		}
	}

	// Wordt zowel doorlopen in woocommerce/ajax/shop-full.php als woocommerce/archive-product.php?
	add_action( 'woocommerce_before_shop_loop', 'add_custom_dropdown_filters_per_category' );

	function add_custom_dropdown_filters_per_category() {
		echo '<div class="small-container"><div class="row">';
			if ( is_product_category( array( 'wijn', 'rood', 'rose', 'wit', 'schuimwijn', 'dessertwijn' ) ) ) {
				echo '<div class="col-md-3 supplementary-filter">';
					$args = array(
						'display_type' => 'dropdown',
						'title' => 'Druivenrassen',
						'attribute' => 'grapes',
					);
					the_widget( 'WC_Widget_Layered_Nav', $args );
				echo '</div>';
				echo '<div class="col-md-3 supplementary-filter">';
					$args['title'] = 'Gerechten';
					$args['attribute'] = 'recipes';
					the_widget( 'WC_Widget_Layered_Nav', $args );
				echo '</div>';
				echo '<div class="col-md-3 supplementary-filter">';
					$args['title'] = 'Smaken';
					$args['attribute'] = 'tastes';
					the_widget( 'WC_Widget_Layered_Nav', $args );
				echo '</div>';
			}

			// @toDo: Lay-out tweaken en inschakelen
			// echo '<div class="col-md-3 supplementary-filter">';
			// 	woocommerce_catalog_ordering();
			// echo '</div>';
		echo '</div></div>';
	}

	// Verberg categorie 'Geschenkverpakkingen' in widgets
	add_filter( 'woocommerce_product_categories_widget_args', 'ob2c_hide_gift_wrapper_category', 10, 1 );

	function ob2c_hide_gift_wrapper_category( $args ) {
		$gift_category_id = get_option('wcgwp_category_id');
		if ( intval( $gift_category_id ) > 0 ) {
			// Na aanklikken van hoofdcategorie is de categorie reeds expliciet opgenomen in 'include'
			$include_ids = explode( ',', $args['include'] );
			foreach ( $include_ids as $key => $value ) {
				if ( $gift_category_id == $value ) {
					unset( $include_ids[ $key ] );
					break;
				}
			}
			$args['include'] = implode( ',', $include_ids );
			$args['exclude'] = $gift_category_id;
		}

		// write_log( print_r( $args, true ) );
		return $args;
	}

	// Pas de labels bij non-selectie van een dropdown aan
	add_filter( 'woocommerce_layered_nav_any_label', 'tweak_layered_nav_any_labels', 10, 3 );

	function tweak_layered_nav_any_labels( $label, $raw_label, $taxonomy ) {
		switch ( $taxonomy ) {
			case 'pa_recipes':
				$label = '(selecteer een gerecht)';
				break;

			case 'pa_grapes':
				$label = '(selecteer een druivenras)';
				break;

			case 'pa_tastes':
				$label = '(selecteer een smaak)';
				break;

			case 'pa_countries':
				$label = '(selecteer een land)';
				break;
		}
		return $label;
	}

	// Update bij elke cart load (ook via AJAX!) onze custom cookies
	add_action( 'woocommerce_set_cart_cookies', 'set_number_of_items_in_cart_cookie' );

	function set_number_of_items_in_cart_cookie() {
		// Vroege actie, check altijd of aangeroepen functies reeds beschikbaar zijn!
		if ( ! is_main_site() ) {
			// Instellen van 'latest_blog_id' gebeurt enkel bij expliciet kiezen in store selector!
			// Check of de huidige cookie overeenkomt met de huidige blog-ID
			if ( isset( $_COOKIE['latest_blog_id'] ) and $_COOKIE['latest_blog_id'] == get_current_blog_id() ) {
				$current_blog = get_blog_details();
				setcookie( 'latest_blog_path', str_replace( '/', '', $current_blog->path ), time() + MONTH_IN_SECONDS, '/', OXFAM_COOKIE_DOMAIN );
				if ( is_object( WC()->cart ) ) {
					setcookie( 'blog_'.get_current_blog_id().'_items_in_cart', WC()->cart->get_cart_contents_count(), time() + MONTH_IN_SECONDS, '/', OXFAM_COOKIE_DOMAIN );
				}
				// Stel shipping_city in op gemeente die overeenkomt met current_location?
			}
		}
	}

	// Toon breadcrumbs wél op shoppagina's
	add_filter( 'nm_shop_breadcrumbs_hide', '__return_false' );
	// Laad géén extra NM-stijlen rechtstreeks in de pagina!
	add_filter( 'nm_include_custom_styles', '__return_false' );

	add_filter( 'woocommerce_get_breadcrumb', 'modify_woocommerce_breadcrumbs' );
	function modify_woocommerce_breadcrumbs( $crumbs ) {
		$new_crumbs = array();
		// Key 0 = Titel, Key 1 = URL
		foreach ( $crumbs as $page ) {
			if ( $page[0] === 'Home' ) {
				$page[1] = 'https://'.OXFAM_MAIN_SITE_DOMAIN.'/';
			} elseif ( $page[0] === 'Producten' and ! is_main_site() ) {
				// Prepend de lokale homepage van de huidige webshop
				$new_crumbs[] = array( 0 => 'Webshop '.get_webshop_name(true), 1 => get_site_url() );
			}
			$new_crumbs[] = $page;
		}
		return $new_crumbs;
	}
	
	// Geautomatiseerde manier om diverse instellingen te kopiëren naar subsites
	add_action( 'update_option_woocommerce_enable_reviews', 'sync_settings_to_subsites', 10, 3 );
	add_action( 'update_option_woocommerce_placeholder_image', 'sync_settings_to_subsites', 10, 3 );
	add_action( 'update_option_woocommerce_google_analytics_settings', 'sync_settings_to_subsites', 10, 3 );
	// add_action( 'update_option_woocommerce_local_pickup_plus_settings', 'sync_settings_to_subsites', 10, 3 );
	add_action( 'update_option_wp_mail_smtp', 'sync_settings_to_subsites', 10, 3 );
	add_action( 'update_option_nm_theme_options', 'sync_settings_to_subsites', 10, 3 );
	add_action( 'update_option_wpsl_settings', 'sync_settings_to_subsites', 10, 3 );
	add_action( 'update_option_cookie_notice_options', 'sync_settings_to_subsites', 10, 3 );
	add_action( 'update_option_wjecf_licence', 'sync_settings_to_subsites', 10, 3 );
	add_action( 'update_option_wcgwp_category_id', 'sync_settings_to_subsites', 10, 3 );
	add_action( 'update_option_wcgwp_details', 'sync_settings_to_subsites', 10, 3 );
	add_action( 'update_option_wcgwp_display', 'sync_settings_to_subsites', 10, 3 );
	add_action( 'update_option_wcgwp_link', 'sync_settings_to_subsites', 10, 3 );
	add_action( 'update_option_wcgwp_number', 'sync_settings_to_subsites', 10, 3 );
	add_action( 'update_option_wcgwp_modal', 'sync_settings_to_subsites', 10, 3 );
	add_action( 'update_option_wcgwp_show_thumb', 'sync_settings_to_subsites', 10, 3 );
	add_action( 'update_option_wcgwp_textarea_limit', 'sync_settings_to_subsites', 10, 3 );
	
	function sync_settings_to_subsites( $old_value, $new_value, $option ) {
		// Actie wordt enkel doorlopen indien oude en nieuwe waarde verschillen, dus geen extra check nodig
		if ( get_current_blog_id() === 1 and current_user_can('update_core') ) {
			$logger = wc_get_logger();
			$context = array( 'source' => 'Oxfam Options Sync' );
			$sites = get_sites( array( 'site__not_in' => array(1) ) );
			// write_log( print_r( $new_value, true ) );
			
			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );
				
				$success = false;
				if ( $option === 'wp_mail_smtp' and is_array( $new_value ) ) {
					// Instellingen van WP Mail SMTP lokaal maken
					$new_value['mail']['from_email'] = get_option('admin_email');
					$new_value['mail']['from_name'] = get_bloginfo('name');
				}

				if ( $option === 'wcgwp_category_id' ) {
					$gift_category = get_term_by( 'slug', 'geschenkverpakkingen', 'product_cat' );
					if ( $gift_category !== false ) {
						$new_value = $gift_category->term_id;
					}
				}

				if ( $option === 'cookie_notice_options' ) {
					// Boolean waardes worden niet goed overgenomen m.b.v. update_option() ...
					// Manipuleer de database rechtstreeks, blog-ID zit reeds vervat in prefix!
					global $wpdb;
					$success = $wpdb->update( $wpdb->prefix.'options', array( 'option_value' => serialize( $new_value ) ), array( 'option_name' => $option ) );
				} else {
					if ( update_option( $option, $new_value ) ) {
						$success = true;
					}
				}
				
				restore_current_blog();

				// Log op het hoofdniveau!
				if ( $success ) {
					$logger->info( "Setting '".$option."' synced to subsite ".$site->path, $context );
				}
			}
		}
	}

	function get_ingredients_legend( $ingredients ) {
		$legend = array();
		if ( ! empty( $ingredients ) ) {
			if ( strpos( $ingredients, '*' ) !== false ) {
				$legend[] = '* ingrediënt uit een eerlijke handelsrelatie';
			}
			if ( strpos( $ingredients, '°' ) !== false ) {
				$legend[] = '° ingrediënt van biologische landbouw';
			}
			if ( strpos( $ingredients, '†' ) !== false ) {
				$legend[] = '† ingrediënt verkregen in de periode van omschakeling naar biologische landbouw';
			}
		}
		return $legend;
	}

	// Toon kolom met winkel waar elke gebruiker lid van is
	add_filter( 'manage_users_columns', 'add_member_of_shop_column', 10, 1 );
	add_filter( 'manage_users_custom_column', 'add_member_of_shop_column_value', 10, 3 );

	function add_member_of_shop_column( $columns ) {
		if ( is_regional_webshop() ) {
			$columns['member_of_shop'] = 'Bevestigt thuisleveringen voor';
		}
		return $columns;
	}

	function add_member_of_shop_column_value( $value, $column_name, $user_id ) {
		if ( $column_name === 'member_of_shop' ) {
			$value = trim_and_uppercase( get_user_meta( $user_id, 'blog_'.get_current_blog_id().'_member_of_shop', true ) );
		}
		return $value;
	}

	// Schakel Gutenberg uit
	add_filter( 'use_block_editor_for_post', '__return_false', 100 );
	add_filter( 'use_block_editor_for_post_type', '__return_false', 100 );
	
	add_filter( 'wc_product_enable_dimensions_display', '__return_false' );
	add_filter( 'woocommerce_get_availability_text', 'modify_backorder_text', 10, 2 );

	function modify_backorder_text( $availability, $product ) {
		if ( $availability === __( 'Available on backorder', 'woocommerce' ) ) {
			$availability = 'Tijdelijk niet beschikbaar';
		}
		return $availability;
	}

	// Verberg acties op orders (ook in bulk en preview)
	add_filter( 'bulk_actions-edit-shop_order', 'remove_dangerous_bulk_actions', 10000, 1 );
	add_filter( 'woocommerce_admin_order_actions', 'remove_dangerous_preview_actions', 100, 2 );
	add_filter( 'woocommerce_admin_order_preview_actions', 'remove_dangerous_preview_actions', 100, 2 );
	add_filter( 'woocommerce_order_actions', 'remove_dangerous_singular_actions', 100, 1 );

	function remove_dangerous_bulk_actions( $actions ) {
		unset( $actions['mark_processing'] );
		unset( $actions['mark_completed'] );
		// WERKT NIET, WELLICHT MOET WOOCOMMERCE ORDER STATUSES NOG BIJGEWERKT WORDEN (INJECTEERT STATUSSEN NOG VIA JAVASCRIPT?)
		// var_dump_pre( $actions);
		return $actions;
	}

	function remove_dangerous_preview_actions( $actions, $order ) {
		// Voorlopig volledig verbergen, later misschien flow voorzien?
		unset( $actions['status'] );
		return $actions;
	}

	function remove_dangerous_singular_actions( $actions ) {
		unset( $actions['send_order_details'] );
		unset( $actions['send_order_details_admin'] );
		unset( $actions['regenerate_download_permissions'] );
		return $actions;
	}

	// Verwijder bepaalde filters boven het productoverzicht in de back-end (beschikbaar vanaf WC3.5+)
	add_filter( 'woocommerce_products_admin_list_table_filters', 'oft_remove_product_filters', 10, 1 );

	function oft_remove_product_filters( $filters ) {
		unset($filters['product_type']);
		return $filters;
	}

	// Beperk de beschikbare statussen op het orderdetail voor lokale beheerders 
	add_filter( 'wc_order_statuses', 'limit_status_possibilities_on_edit_order_screen', 1000 );
	
	function limit_status_possibilities_on_edit_order_screen( $order_statuses ) {
		if ( is_admin() ) {
			global $pagenow, $post_type;
			if ( $pagenow === 'post.php' and $post_type === 'shop_order' ) {
				if ( ! current_user_can('update_core') ) {
					global $post;
					$order = wc_get_order( $post->ID );
					// Cancelled misschien wel toestaan bij B2B-bestellingen?
					// Ook volledige terugbetaling altijd handmatig registreren met opgave van reden!
					$forbidden_statuses = array( 'wc-pending', 'wc-on-hold', 'wc-failed', 'wc-refunded', 'wc-cancelled' );
					foreach ( $forbidden_statuses as $key ) {
						// Verhinder dat we de huidige status van het order verwijderen
						if ( array_key_exists( $key, $order_statuses ) and $order->get_status() !== str_replace( 'wc-', '', $key ) ) {
							unset( $order_statuses[ $key ] );
						}
					}
				}
			}
		}
		return $order_statuses;
	}

	// Sitemap van afbeeldingen uitschakelen
	add_filter( 'jetpack_sitemap_image_skip_post', '__return_true' );

	// Verhinder bekijken van site door mensen die geen beheerder zijn van deze webshop
	add_action( 'init', 'force_user_login' );
	
	function force_user_login() {
		// Demosite eventueel afschermen met login
		// if ( get_current_site()->domain !== 'shop.oxfamwereldwinkels.be' ) {
		// 	if ( ! is_user_logged_in() ) {
		// 		$url = get_current_url();
		// 		// Nooit redirecten: inlog-, reset-, activatiepagina en WC API calls
		// 		if ( preg_replace( '/\?.*/', '', $url ) != preg_replace( '/\?.*/', '', wp_login_url() ) and preg_replace( '/\?.*/', '', $url ) != preg_replace( '/\?.*/', '', wc_lostpassword_url() ) and ! strpos( $url, 'activate.php' ) and ! strpos( $url, 'wc-api' ) ) {
		// 			// Stuur gebruiker na inloggen terug naar huidige pagina
		// 			wp_safe_redirect( wp_login_url($url) );
		// 			exit();
		// 		}
		// 	} elseif ( ! is_user_member_of_blog( get_current_user_id(), get_current_blog_id() ) and ! is_super_admin() ) {
		// 		// Toon tijdelijke boodschap, het heeft geen zin om deze gebruiker naar de inlogpagina te sturen!
		// 		wp_safe_redirect( network_site_url('/wp-content/blog-suspended.php') );
		// 		exit();
		// 	}
		// }

		// Voeg producten toe o.b.v. parameters in URL
		// Vermijd eindeloze loop indien we de store locator nog moeten openen
		if ( ! empty( $_GET['addSkus'] ) and ! isset( $_GET['triggerStoreLocator'] ) ) {
			if ( is_main_site() ) {
				// Redirect mag vanaf nu altijd gebeuren!
				if ( ! empty( $_COOKIE['latest_blog_id'] ) ) {
					$destination_blog = get_blog_details( $_COOKIE['latest_blog_id'], false );
					if ( $destination_blog->path !== '/' ) {
						wp_safe_redirect( network_site_url( $destination_blog->path.'?addSkus='.$_GET['addSkus'].'&recipeId='.$_GET['recipeId'] ) );
						exit();
					}
				} else {
					// Trigger de store locator met uitleg bovenaan (over het waarom van de tussenstap)
					wp_safe_redirect( get_permalink( wc_get_page_id('shop') ).'?addSkus='.$_GET['addSkus'].'&recipeId='.$_GET['recipeId'].'&triggerStoreLocator' );
					exit();
				}
			} else {
				add_action( 'template_redirect', 'add_product_to_cart_by_get_parameter' );
			}
		}
	}

	function get_current_url() {
		$url = ( isset( $_SERVER['HTTPS'] ) and 'on' === $_SERVER['HTTPS'] ) ? 'https' : 'http';
		$url .= '://' . $_SERVER['SERVER_NAME'];
		$url .= in_array( $_SERVER['SERVER_PORT'], array( '80', '443' ) ) ? '' : ':' . $_SERVER['SERVER_PORT'];
		$url .= $_SERVER['REQUEST_URI'];
		return $url;
	}

	function add_product_to_cart_by_get_parameter() {
		if ( is_admin() ) {
			return;
		}

		$recipe = false;
		$recipes = array(
			'26373' => 'Toastjes met cashewcrème en gemarineerde wortel',
			'28156' => 'Mediterraanse focaccia met rozemarijn',
			'28168' => 'Kerststronk met Bite to Fight-chocolade',
			'28186' => 'Gevulde pompoen met quinoa en tahinsaus',
			'28211' => 'Rode ui-taartjes',
			'28206' => 'Pittig gevulde champignons',
			'28237' => 'Tartelettes van rode biet met (kerst)kroketjes',
		);

		if ( ! empty( $_GET['recipeId'] ) ) {
			$recipe_id = $_GET['recipeId'];
			if ( array_key_exists( $recipe_id, $recipes ) ) {
				$recipe = $recipes[ $recipe_id ];
			}
		}

		if ( WC()->session->has_session() and $recipe ) {
			// VOORLOPIG UITSCHAKELEN
			// $executed = WC()->session->get( 'recipe_'.$recipe_id.'_products_ordered', 'no' );
			$executed = 'no';
		} else {
			$executed = 'no';
		}

		// Voorkom opnieuw toevoegen bij het terugkeren
		if ( $executed === 'no' ) {
			$products_added = 0;
			$total_products = 0;

			// Géén urldecode() nodig, de globals $_GET en $_REQUEST ondergingen dit reeds! 
			$articles = explode( ',', $_GET['addSkus'] );

			foreach ( $articles as $article ) {
				$parts = explode( '|', $article );
			
				$sku = $parts[0];
				if ( count( $parts ) > 1 ) {
					$quantity = intval( $parts[1] );
				} else {
					$quantity = 1;
				}	
				$total_products += $quantity;
				
				if ( $quantity > 0 ) {
					$product_id = wc_get_product_id_by_sku( $sku );
					if ( $product_id > 0 ) {
						$product = wc_get_product( $product_id );
						if ( WC()->cart->add_to_cart( $product_id, $quantity ) !== false ) {
							$products_added += $quantity;
							wc_add_notice( sprintf( __( '"%s" werd toegevoegd aan je winkelmandje.', 'oxfam-webshop' ), $product->get_name() ), 'success' );
						} else {
							// In dit geval zal add_to_cart() zelf al een notice uitspuwen, bv. indien uit voorraad
							
							if ( count( $articles ) === 1 ) {
								// Redirect naar productpagina
								wp_safe_redirect( $product->get_permalink() );
								exit();
							}
						}
					} else {
						wc_add_notice( sprintf( __( 'Sorry, artikelnummer %s is nog niet beschikbaar voor online verkoop.', 'oxfam-webshop' ), $sku ), 'error' );
					}
				}
			}

			if ( $products_added < $total_products ) {
				if ( $recipe ) {
					$message = sprintf( __( 'Sommige Oxfam-ingrediënten voor "%s" konden niet toegevoegd worden aan je winkelmandje.', 'oxfam-webshop' ), $recipe );
				} else {
					$message = __( 'Sommige producten konden niet toegevoegd worden aan je winkelmandje.', 'oxfam-webshop' );
				}
				wc_add_notice( $message, 'success' );
			} else {
				if ( $recipe ) {
					$message = sprintf( __( 'Alle Oxfam-ingrediënten voor "%s" zijn toegevoegd aan je winkelmandje!', 'oxfam-webshop' ), $recipe );
					WC()->session->set( 'recipe_'.$recipe_id.'_products_ordered', 'yes' );
				} else {
					$message = __( 'Alle producten zijn toegevoegd aan je winkelmandje!', 'oxfam-webshop' );
				}
				wc_add_notice( $message, 'success' );
			}
		} else {
			wc_add_notice( sprintf( __( 'De ingrediënten voor "%s" waren reeds toegevoegd aan je winkelmandje!', 'oxfam-webshop' ), $recipe ), 'error' );
		}

		// Redirect naar het winkelmandje, zodat eventuele foutmeldingen en kortingsbonnen zeker verschijnen
		wp_safe_redirect( wc_get_cart_url() );
		exit();
	}
	
	// Schakel Google Analytics uit in bepaalde gevallen
	add_filter( 'woocommerce_ga_disable_tracking', 'disable_ga_tracking_for_certain_users', 10, 2 );

	function disable_ga_tracking_for_certain_users( $disable, $type ) {
		// Parameter $type bevat het soort GA-tracking
		if ( get_current_site()->domain === 'shop.oxfamwereldwinkels.be' ) {
			// Wordt standaard enkel uitgeschakeld voor users met 'manage_options'-rechten (= superadmins)
			// Let op: hoofdniveau NIET in testmodus zetten, anders verliezen we tracking van homepage!
			if ( current_user_can('manage_woocommerce') or ! cn_cookies_accepted() or get_option('mollie-payments-for-woocommerce_test_mode_enabled') === 'yes' ) {
				return true;
			} else {
				return false;
			}
		}
	}

	// Activeer Google Tag Manager (JS)
	add_action( 'wp_head', 'add_google_tag_manager_js', 100 );

	function add_google_tag_manager_js() {
		if ( get_current_site()->domain === 'shop.oxfamwereldwinkels.be' ) {
			echo '<link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">';
			if ( ! current_user_can('manage_woocommerce') and cn_cookies_accepted() and get_option('mollie-payments-for-woocommerce_test_mode_enabled') !== 'yes' ) {
				?>
				<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
				new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
				j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
				'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
				})(window,document,'script','dataLayer','GTM-KMKZ7HH');</script>
				<?php
			}
		}

		// Verhinder het indexeren van lokale productpagina's (zorgt voor enorm veel duplicate content)
		if ( ! is_main_site() ) {
			if ( is_product() ) {
				// Eventueel niet doen bij lokaal assortiment? 
				wp_no_robots();
			}
		}
	}

	// Activeer Google Tag Manager (no JS)
	add_action( 'wp_head', 'add_google_tag_manager_no_js', 100 );

	function add_google_tag_manager_no_js() {
		if ( get_current_site()->domain === 'shop.oxfamwereldwinkels.be' ) {
			if ( ! current_user_can('manage_woocommerce') and cn_cookies_accepted() and get_option('mollie-payments-for-woocommerce_test_mode_enabled') !== 'yes' ) {
				?>
				<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-KMKZ7HH"
				height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
				<?php
			}
		}
	}

	// Activeer Facebook Pixel (JS)
	add_action( 'wp_head', 'add_facebook_pixel_js', 200 );
	add_action( 'woocommerce_thankyou', 'add_fb_purchase_event', 10, 1 );

	function add_facebook_pixel_js() {
		if ( get_current_site()->domain === 'shop.oxfamwereldwinkels.be' ) {
			if ( ! current_user_can('manage_woocommerce') and cn_cookies_accepted() and get_option('mollie-payments-for-woocommerce_test_mode_enabled') !== 'yes' ) {
				global $post;
				?>
				<script>!function(f,b,e,v,n,t,s)
				{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
				n.callMethod.apply(n,arguments):n.queue.push(arguments)};
				if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
				n.queue=[];t=b.createElement(e);t.async=!0;
				t.src=v;s=b.getElementsByTagName(e)[0];
				s.parentNode.insertBefore(t,s)}(window, document,'script',
				'https://connect.facebook.net/en_US/fbevents.js');
				fbq('init', '1964131620531187');
				fbq('track', 'PageView');</script>
				<?php

				if ( is_product() ) {
					?>
					<script>
						fbq('track', 'ViewContent', {
							content_ids: '<?php echo get_post_meta( $post->ID, '_sku', true ); ?>',
							content_type: 'product'
						});
					</script>
					<?php
				}
			}
		}
	}

	function add_fb_purchase_event( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Track geen onbetaalde bestellingen
		if ( ! $order->is_paid() ) {
			return;
		}

		$contents = array();
		$content_ids = array();

		foreach ( $order->get_items() as $item ) {
			if ( $product = isset( $item['product_id'] ) ? wc_get_product( $item['product_id'] ) : NULL ) {
				$content_ids[] = $product->get_sku();
				
				$content = new \stdClass();
				$content->id = $product->get_sku();
				$content->quantity = $item->get_quantity();
				$contents[] = $content;
			}
		}

		// Als Facebook Pixel niet ingeladen is, zal dit gewoon zacht falen (geen extra check nodig)
		// Door een event-ID mee te geven worden dubbel verstuurde events (bv. door heen en weer navigeren) weggefilterd
		?>
		<script>
			fbq('track', 'Purchase', {
				content_ids: <?php echo wp_json_encode( $content_ids ); ?>,
				contents: <?php echo wp_json_encode( $contents ); ?>,
				content_type: 'product',
				value: <?php echo $order->get_total(); ?>,
				currency: 'EUR'
			}, { eventID: '<?php echo $order->get_order_number() ?>' });
		</script>
		<?php
	}

	// Activeer Facebook Pixel (no JS)
	add_action( 'wp_head', 'add_facebook_pixel_no_js', 200 );

	function add_facebook_pixel_no_js() {
		if ( get_current_site()->domain === 'shop.oxfamwereldwinkels.be' ) {
			if ( ! current_user_can('manage_woocommerce') and cn_cookies_accepted() and get_option('mollie-payments-for-woocommerce_test_mode_enabled') !== 'yes' ) {
				?>
				<noscript><img height="1" width="1" style="display:none"
				src="https://www.facebook.com/tr?id=1964131620531187&ev=PageView&noscript=1"
				/></noscript>
				<?php
			}
		}
	}

	// Activeer Facebook Messenger
	add_action( 'wp_footer', 'add_facebook_messenger', 300 );

	function add_facebook_messenger() {
		if ( get_current_site()->domain === 'shop.oxfamwereldwinkels.be' ) {
			if ( ! current_user_can('manage_woocommerce') and cn_cookies_accepted() and is_cart() ) {
				?>
				<div id='fb-root'></div>
				<script>(function(d, s, id) {
					var js, fjs = d.getElementsByTagName(s)[0];
					js = d.createElement(s); js.id = id;
					js.src = 'https://connect.facebook.net/nl_NL/sdk/xfbml.customerchat.js';
					fjs.parentNode.insertBefore(js, fjs);
					}(document, 'script', 'facebook-jssdk'));</script>
				<div class='fb-customerchat' attribution="wordpress" page_id='116000561802704' theme_color='#61A534' logged_in_greeting='Is er nog iets onduidelijk? Vraag het ons!' logged_out_greeting='Is er nog iets onduidelijk? Log in via Facebook en vraag het ons!'></div>
				<?php
			}
		}
	}

	// Sta HTML-attribuut 'target' toe in beschrijvingen van taxonomieën
	add_action( 'init', 'allow_target_tag', 20 );

	function allow_target_tag() { 
		global $allowedtags;
		$allowedtags['a']['target'] = 1;
	}

	// Voeg extra CSS-klasses toe aan body (front-end)
	add_filter( 'body_class', 'add_main_site_class' );

	function add_main_site_class( $classes ) {
		if ( is_main_site() ) {
			$classes[] = 'portal';
		}
		if ( is_b2b_customer() ) {
			$classes[] = 'is_b2b_customer';
		}
		return $classes;
	}

	// Voeg extra CSS-klasses toe aan body (back-end)
	add_filter( 'admin_body_class', 'add_user_role_class' );

	function add_user_role_class( $class_string ) {
		if ( ! current_user_can('update_core') ) {
			$class_string .= ' local_manager ';
		}
		return $class_string;
	}

	// Laad het child theme
	add_action( 'wp_enqueue_scripts', 'load_child_theme', 20 );
	add_action( 'wp_enqueue_scripts', 'dequeue_unwanted_styles_and_scripts', 100 );

	function load_child_theme() {
		wp_enqueue_style( 'oxfam-webshop', get_stylesheet_uri(), array( 'nm-core' ), '2.1.1' );
		// In de languages map van het child theme zal dit niet werken (checkt enkel nl_NL.mo) maar fallback is de algemene languages map (inclusief textdomain)
		load_child_theme_textdomain( 'oxfam-webshop', get_stylesheet_directory().'/languages' );

		// Ook WordPress 5.5 gebruikt nog jQuery UI 1.11.4, upgrades voorzien vanaf WP 5.6+
		wp_register_style( 'jquery-ui', 'https://code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css' );
		wp_enqueue_style( 'jquery-ui' );

		wp_enqueue_script( 'jquery-ui-autocomplete' );
		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_script( 'jquery-ui-tooltip' );

		// Inladen in de footer om dependency issues met jQuery te vermijden 
		wp_enqueue_script( 'readmore', get_stylesheet_directory_uri() . '/libraries/readmore/readmore.min.js', array(), false, true );
		wp_enqueue_script( 'scripts', get_stylesheet_directory_uri() . '/js/scripts-min.js', array(), false, true );
	}

	function dequeue_unwanted_styles_and_scripts() {
		// Verwijder Savoy grid styling
		wp_dequeue_style('nm-grid');
		wp_deregister_style('nm-grid');

		// Verhinder het automatisch activeren van SelectWoo op filter dropdowns
		if ( class_exists('woocommerce') ) {
			// Niet uitschakelen op winkelmandje/checkout, library is noodzakelijk voor WooCommerce Local Pickup Plus 2.9+
			if ( ! is_cart() and ! is_checkout() ) {
				wp_dequeue_style('select2');
				wp_deregister_style('select2');
				wp_dequeue_script('selectWoo');
				wp_deregister_script('selectWoo');
			}
		}
	}

	// Voeg custom styling toe aan de adminomgeving (voor Relevanssi en Voorraadbeheer)
	add_action( 'admin_enqueue_scripts', 'load_admin_css' );

	function load_admin_css() {
		wp_enqueue_style( 'oxfam-admin', get_stylesheet_directory_uri().'/css/admin.css', array(), '1.3.2' );
	}

	// Fixes i.v.m. cURL
	add_action( 'http_api_curl', 'custom_curl_timeout', 10, 3 );
	
	function custom_curl_timeout( $handle, $r, $url ) {
		// Fix error 28 - Operation timed out after 10000 milliseconds with 0 bytes received (bij het connecteren van Jetpack met Wordpress.com)
		curl_setopt( $handle, CURLOPT_TIMEOUT, 180 );
		// Fix error 60 - SSL certificate problem: unable to get local issuer certificate (bij het downloaden van een CSV in WP All Import)
		curl_setopt( $handle, CURLOPT_SSL_VERIFYPEER, false );
	}

	// Jetpack-tags uitschakelen op homepages om dubbel werk te vermijden
	if ( is_front_page() ) {
		add_filter( 'jetpack_enable_open_graph', '__return_false' );
	}



	####################
	# WP STORE LOCATOR #
	####################

	// Laad onze custom markers (zowel in front-end als back-end)
	define( 'WPSL_MARKER_URI', dirname( get_bloginfo('stylesheet_url') ).'/markers/' );
	add_filter( 'wpsl_admin_marker_dir', 'wpsl_admin_marker_dir' );

	function wpsl_admin_marker_dir() {
		return get_stylesheet_directory().'/markers/';
	}

	// Verberg de startpositie (zonder gefoefel met transparante afbeeldingen)
	add_filter( 'wpsl_js_settings', 'wpsl_hide_start_marker' );

	function wpsl_hide_start_marker( $settings ) {
		$settings['startMarker'] = '';
		return $settings;
	}

	add_filter( 'wpsl_templates', 'wpsl_add_no_map_template' );

	function wpsl_add_no_map_template( $templates ) {
		$templates[] = array (
			'id'   => 'no_map',
			'name' => 'Modal zonder kaart',
			'path' => get_stylesheet_directory().'/wpsl-templates/no-map.php',
		);
		return $templates;
	}

	add_filter( 'wpsl_listing_template', 'wpsl_custom_results_template' );

	function wpsl_custom_results_template() {
		global $wpsl, $wpsl_settings;

		// Omdat we in deze Underscore.js-template beperkt zijn qua controlestructuren herhalen we de logica voor beide types winkels!
		$listing_template = '<% if ( available == "yes" ) { %>' . "\r\n";
		
		// WINKEL MET WEBSHOP
		$listing_template .= "\t" . '<li data-store-id="<%= id %>" data-oxfam-shop-post-id="<%= oxfamShopPostId %>" data-webshop-url="<%= webshopUrl %>" data-webshop-blog-id="<%= webshopBlogId %>" class="available" style="cursor: pointer;">' . "\r\n";
		$listing_template .= "\t\t" . '<div class="wpsl-store-location">' . "\r\n";
		$listing_template .= "\t\t\t" . '<div class="wpsl-store-wrap">' . "\r\n";
		$listing_template .= "\t\t\t\t" . '<div class="wpsl-description-wrap">' . "\r\n";
		$listing_template .= "\t\t\t\t\t" . '<%= thumb %>' . "\r\n";
		$listing_template .= "\t\t\t\t\t" . wpsl_store_header_template( 'listing' ) . "\r\n";
		$listing_template .= "\t\t\t\t\t" . '<span class="wpsl-street"><%= address %>, ' . wpsl_address_format_placeholders() . '</span>' . "\r\n";
		$listing_template .= "\t\t\t\t" . '</div>' . "\r\n";

		$listing_template .= "\t\t\t" . '<div class="wpsl-direction-wrap">' . "\r\n";
		if ( ! $wpsl_settings['hide_distance'] and 1 === 2 ) {
			$listing_template .= "\t\t\t\t" . '+/- <%= distance %> ' . esc_html( $wpsl_settings['distance_unit'] ) . '' . "\r\n";
		}
		$listing_template .= "\t\t\t" . '</div>' . "\r\n";

		$listing_template .= "\t\t\t\t" . '<div class="wpsl-delivery-wrap">' . "\r\n";
		$listing_template .= "\t\t\t\t\t" . '<ul class="delivery-options">' . "\r\n";
		$listing_template .= "\t\t\t\t\t\t" . '<%= pickup %>' . "\r\n";
		$listing_template .= "\t\t\t\t\t\t" . '<%= delivery %>' . "\r\n";
		$listing_template .= "\t\t\t\t\t" . '</ul>' . "\r\n";
		$listing_template .= "\t\t\t\t" . '</div>' . "\r\n";
		$listing_template .= "\t\t\t" . '</div>' . "\r\n";

		$listing_template .= "\t\t\t" . '<div class="wpsl-actions-wrap">' . "\r\n";
		$listing_template .= "\t\t\t\t" . '<button>Online winkelen</button>' . "\r\n";
		$listing_template .= "\t\t\t" . '</div>' . "\r\n";
		$listing_template .= "\t\t" . '</div>' . "\r\n";
		$listing_template .= "\t" . '</li>';

		$listing_template .= '<% } else { %>' . "\r\n";
		
		// WINKEL ZONDER WEBSHOP
		$listing_template .= "\t" . '<li data-store-id="<%= id %>" data-oxfam-shop-post-id="<%= oxfamShopPostId %>" class="not-available" style="cursor: not-allowed;">' . "\r\n";
		$listing_template .= "\t\t" . '<div class="wpsl-store-location">' . "\r\n";
		$listing_template .= "\t\t\t" . '<div class="wpsl-store-wrap">' . "\r\n";
		$listing_template .= "\t\t\t\t" . '<div class="wpsl-description-wrap">' . "\r\n";
		$listing_template .= "\t\t\t\t\t" . '<%= thumb %>' . "\r\n";
		$listing_template .= "\t\t\t\t\t" . wpsl_store_header_template( 'listing' ) . "\r\n";
		$listing_template .= "\t\t\t\t\t" . '<span class="wpsl-street"><%= address %>, ' . wpsl_address_format_placeholders() . '</span>' . "\r\n";
		$listing_template .= "\t\t\t\t" . '</div>' . "\r\n";

		$listing_template .= "\t\t\t" . '<div class="wpsl-direction-wrap">' . "\r\n";
		if ( ! $wpsl_settings['hide_distance'] and 1 === 2 ) {
			$listing_template .= "\t\t\t\t" . '+/- <%= distance %> ' . esc_html( $wpsl_settings['distance_unit'] ) . '' . "\r\n";
		}
		$listing_template .= "\t\t\t" . '</div>' . "\r\n";

		$listing_template .= "\t\t" . '</div>' . "\r\n";
		$listing_template .= "\t\t" . '<div class="wpsl-actions-wrap">' . "\r\n";
		$listing_template .= "\t\t\t" . '<span>Online winkelen niet beschikbaar.<br/>Stuur je bestelling <a href="mailto:<%= email %>">per e-mail</a>.</span>' . "\r\n";
		$listing_template .= "\t\t" . '</div>' . "\r\n";
		$listing_template .= "\t" . '</li>';

		$listing_template .= '<% } %>' . "\r\n";

		return $listing_template;
	}

	// Voorbeeld van 'featured' winkels: https://wpstorelocator.co/document/create-featured-store-that-shows-up-first-in-search-results/
	add_filter( 'wpsl_store_data', 'wpsl_change_results_sorting', 1000 );

	function wpsl_change_results_sorting( $store_meta ) {
		$custom_sort = array();
		foreach ( $store_meta as $key => $row ) {
			// In plaats van sorteren op 'distance'
			// Key bestaat niet indien de winkel geen webshop heeft!
			$custom_sort[ $key ] = ! empty( $row['webshop'] ) ? $row['webshop'] : '';
			
			// Formatteer de afstand op z'n Belgisch
			$store_meta[ $key ]['distance'] = round( $row['distance'], 0 );
		}

		// Winkels zonder webshop-URL (= lege string) belanden onderaan
		// array_multisort( $custom_sort, SORT_ASC, SORT_REGULAR, $store_meta );
		// write_log( print_r( $store_meta, true ) );
		return $store_meta;
	}

	add_filter( 'wpsl_store_meta', 'wpsl_add_delivery_parameters_to_meta', 2 );

	function wpsl_add_delivery_parameters_to_meta( $store_meta, $store_id = 0 ) {
		// Vreemd genoeg is $store_id altijd leeg ...
		// Keys moeten altijd aanwezig zijn, anders loopt de Underscore.js-template vast
		$store_meta['pickup'] = '<li class="pickup inactive">Afhalen in de winkel</li>';
		$store_meta['delivery'] = '<li class="delivery inactive">Geen levering aan huis</li>';
		$store_meta['available'] = 'no'; 

		// Haal de huidige postcode op
		$current_location = false;
		if ( ! empty( $_COOKIE['current_location'] ) ) {
			$current_location = intval( $_COOKIE['current_location'] );
			$store_meta['delivery'] = '<li class="delivery inactive">Geen levering aan huis in '.$current_location.'</li>';
		}
		
		if ( $store_meta['webshopBlogId'] !== '' ) {
			$store_meta['available'] = 'yes'; 
			
			switch_to_blog( $store_meta['webshopBlogId'] );
			
			if ( does_local_pickup() ) {
				$store_meta['pickup'] = '<li class="delivery active">Afhalen in de winkel</li>';
			}
			
			if ( $current_location !== false and does_home_delivery( $current_location ) ) {
				$store_meta['delivery'] = '<li class="delivery active">Levering aan huis in '.$current_location.'</li>';
			} else {
				// write_log( "Zipcode ".$current_location." is not in range: ".serialize( get_oxfam_covered_zips() ) );
			}

			restore_current_blog();
		}

		return $store_meta;
	}

	// Deze filters zullen misschien van pas komen indien er geen enkel resultaat gevonden werd
	add_filter( 'wpsl_no_results_sql', 'wpsl_show_default_webshop_for_home_delivery' );
	// add_filter( 'wpsl_sql_placeholder_values', 'debug_no_results_for_antwerp', 10, 1 );
	
	function wpsl_show_default_webshop_for_home_delivery( $store_data ) {
		write_log("Geen enkele winkel gevonden binnen de 30 kilometer!");
		return $store_data;
	}

	function debug_no_results_for_antwerp( $values ) {
		write_log("WP Store Locator Lat-Long Search: ".$values[1].",".$values[2]);
		return $values;
	}

	// Voeg o.a. post-ID toe als extra metadata op winkel
	add_filter( 'wpsl_meta_box_fields', 'wpsl_add_meta_box_fields' );

	function wpsl_add_meta_box_fields( $meta_fields ) {
		$meta_fields[ __( 'Additional Information', 'wpsl' ) ] = array(
			'phone' => array( 'label' => 'Telefoon' ),
			'email' => array( 'label' => 'E-mail' ),
			'url' => array( 'label' => 'Winkelpagina' ),
			'oxfam_shop_post_id' => array( 'label' => 'Post-ID in OWW-site' ),
			'webshop' => array( 'label' => 'URL van de webshop' ),
			'webshop_blog_id' => array( 'label' => 'Blog-ID van de webshop' ),
			'mailchimp' => array( 'label' => 'Lokale nieuwsbrief' ),
		);

		return $meta_fields;
	}

	// Geef de extra metadata mee in de JSON-response
	add_filter( 'wpsl_frontend_meta_fields', 'wpsl_add_frontend_meta_fields' );

	function wpsl_add_frontend_meta_fields( $store_fields ) {
		$store_fields['wpsl_oxfam_shop_post_id'] = array( 'name' => 'oxfamShopPostId' );
		$store_fields['wpsl_webshop'] = array( 'name' => 'webshopUrl' );
		$store_fields['wpsl_webshop_blog_id'] = array( 'name' => 'webshopBlogId' );
		$store_fields['wpsl_mailchimp'] = array( 'name' => 'mailchimpUrl' );
		return $store_fields;
	}

	// Kan nog van pas komen om gewenst artikelnummer door te geven aan subsite
	function append_get_parameter_to_href( $str, $get_param ) {
		if ( isset( $_GET[$get_param] ) ) {
			// Check inbouwen op reeds aanwezige parameters in $2-fragment? 
			$str = preg_replace( '/<a(.*)href="([^"]*)"(.*)>/','<a$1href="$2?'.$get_param.'='.$_GET[$get_param].'"$3>', $str );
		}
		return $str;
	}



	############
	# SECURITY #
	############

	// Toon het blokje 'Additional Capabilities' op de profielpagina nooit
	add_filter( 'ure_show_additional_capabilities_section', '__return_false' );

	// Schakel de sterkte-indicator voor paswoorden uit
	add_action( 'wp_print_scripts', 'remove_password_strength', 100 );
	
	function remove_password_strength() {
		if ( wp_script_is( 'wc-password-strength-meter', 'enqueued' ) ) {
			wp_dequeue_script( 'wc-password-strength-meter' );
		}
	}

	// Fix probleem waarbij wc_modify_editable_roles() in wc-user-functions.php admins en speciale rollen enkel nog toegang geeft tot gewone klanten
	// Zie o.a. https://github.com/woocommerce/woocommerce/pull/21555
	add_filter( 'woocommerce_shop_manager_editable_roles', 'give_custom_user_roles_access_to_role_edit_capabilities' );

	function give_custom_user_roles_access_to_role_edit_capabilities( $roles ) {
		// Toestaan dat lokale beheerders ook lokale assistenten bewerken
		if ( current_user_can('manage_network_users') ) {
			$roles[] = 'local_helper';
		}

		// Superadmins toegang geven tot alle rollen uit het netwerk
		if ( current_user_can('create_sites') ) {
			global $wp_roles;
			if ( ! isset( $wp_roles ) ) {
				$wp_roles = new WP_Roles();
			}
			$roles = array_keys( $wp_roles->role_names );
		}
		
		return $roles;
	}

	// Zorg ervoor dat lokale assistenten/beheerders hun gearchiveerde site toch al kunnen bekijken
	add_filter( 'ms_site_check', 'allow_local_manager_on_archived' );

	function allow_local_manager_on_archived() {
		if ( current_user_can('manage_woocommerce') ) {
			return true;
		}
		// Terug te plaatsen winkelboodschap: "We zijn vandaag uitzonderlijk gesloten. Bestellingen worden opnieuw verwerkt vanaf de eerstvolgende openingsdag. De geschatte leverdatum houdt hiermee rekening."
	}

	// Tegenhouden lukt niet omdat de orderstatus al bijgewerkt is als de actie doorlopen wordt ...
	// add_action( 'woocommerce_order_status_processing', 'warn_if_processing_from_invalid_status', 1, 2 );
	// add_action( 'woocommerce_order_status_completed', 'warn_if_completed_from_invalid_status', 1, 2 );
	
	function warn_if_processing_from_invalid_status( $order_id, $order ) {
		if ( in_array( $order->get_status(), array( 'completed', 'cancelled', 'refunded' ) ) ) {
			send_automated_mail_to_helpdesk( $order->get_order_number().' onderging bijna een ongeoorloofde statuswijziging', '<p>Bekijk de bestelling <a href="'.$order->get_edit_order_url().'">in de back-end</a>.</p>' );
			// Dit zal verderop opgevangen worden, en de foutmelding wordt als melding getoond in de back-end 
			// Zie https://github.com/woocommerce/woocommerce/blob/0f134ca6a20c8132be490b22ad8d1dc245d81cc0/includes/class-wc-order.php#L396
			throw new Exception( $order->get_order_number().' is reeds afgewerkt en kan niet opnieuw in verwerking genomen worden.' );
		}
	}

	function warn_if_completed_from_invalid_status( $order_id, $order ) {
		if ( in_array( $order->get_status(), array( 'pending', 'cancelled', 'refunded' ) ) ) {
			send_automated_mail_to_helpdesk( $order->get_order_number().' onderging bijna een ongeoorloofde statuswijziging', '<p>Bekijk de bestelling <a href="'.$order->get_edit_order_url().'">in de back-end</a>.</p>' );
			throw new Exception( $order->get_order_number().' werd niet betaald en dient niet in verwerking genomen te worden.' );
		}
	}

	add_action( 'woocommerce_order_status_pending_to_completed', 'warn_if_invalid_status_change', 10, 2 );
	add_action( 'woocommerce_order_status_refunded_to_processing', 'warn_if_invalid_status_change', 10, 2 );
	add_action( 'woocommerce_order_status_refunded_to_completed', 'warn_if_invalid_status_change', 10, 2 );
	add_action( 'woocommerce_order_status_completed_to_processing', 'warn_if_invalid_status_change', 10, 2 );
	add_action( 'woocommerce_order_status_completed_to_claimed', 'warn_if_invalid_status_change', 10, 2 );
	add_action( 'woocommerce_order_status_cancelled_to_pending', 'warn_if_invalid_status_change', 10, 2 );
	add_action( 'woocommerce_order_status_cancelled_to_processing', 'warn_if_invalid_status_change', 10, 2 );
	add_action( 'woocommerce_order_status_cancelled_to_claimed', 'warn_if_invalid_status_change', 10, 2 );
	add_action( 'woocommerce_order_status_cancelled_to_completed', 'warn_if_invalid_status_change', 10, 2 );
	
	function warn_if_invalid_status_change( $order_id, $order ) {
		send_automated_mail_to_helpdesk( 'Bestelling '.$order->get_order_number().' onderging een ongeoorloofde statuswijziging naar '.$order->get_status(), '<p>Gelieve de logs te checken <a href="'.$order->get_edit_order_url().'">in de back-end</a>!</p>' );
	}

	// Functie is niet gebaseerd op eigenschappen van gebruikers en dus al zeer vroeg al bepaald (geen 'init' nodig)
	if ( is_regional_webshop() ) {
		// Definieer een profielveld in de back-end waarin we kunnen bijhouden van welke winkel de gebruiker lid is
		add_action( 'show_user_profile', 'add_member_of_shop_user_field' );
		add_action( 'edit_user_profile', 'add_member_of_shop_user_field' );
		// Zorg ervoor dat het ook bewaard wordt
		add_action( 'personal_options_update', 'save_member_of_shop_user_field' );
		add_action( 'edit_user_profile_update', 'save_member_of_shop_user_field' );
		
		// Voeg de claimende winkel toe aan de ordermetadata van zodra iemand op het winkeltje klikt (en verwijder indien we teruggaan)
		add_action( 'woocommerce_order_status_processing_to_claimed', 'register_claiming_member_shop', 10, 2 );
		// Veroorzaakt probleem indien volgorde niet 100% gerespecteerd wordt
		// add_action( 'woocommerce_order_status_claimed_to_processing', 'delete_claiming_member_shop' );

		// Deze transities zullen in principe niet voorkomen, maar voor alle zekerheid ...
		add_action( 'woocommerce_order_status_on-hold_to_claimed', 'register_claiming_member_shop', 10, 2 );
		// Veroorzaakt probleem indien volgorde niet 100% gerespecteerd wordt
		// add_action( 'woocommerce_order_status_claimed_to_on-hold', 'delete_claiming_member_shop' );

		// Laat succesvol betaalde afhalingen automatisch claimen door de gekozen winkel
		add_action( 'woocommerce_thankyou', 'auto_claim_local_pickup' );
		
		// Creëer bovenaan de orderlijst een dropdown met de deelnemende winkels uit de regio
		add_action( 'restrict_manage_posts', 'add_claimed_by_filtering' );
		
		// Voer de filtering uit tijdens het bekijken van orders in de admin
		add_action( 'pre_get_posts', 'filter_orders_by_owner', 15 );

		// Voeg ook een kolom toe aan het besteloverzicht in de back-end
		add_filter( 'manage_edit-shop_order_columns', 'add_claimed_by_column', 11 );

		// Maak sorteren op deze nieuwe kolom mogelijk
		add_filter( 'manage_edit-shop_order_sortable_columns', 'make_claimed_by_column_sortable' );

		// Toon de data van elk order in de kolom
		add_action( 'manage_shop_order_posts_custom_column', 'get_claimed_by_value', 10, 2 );

		// Laat de custom statusfilter verschijnen volgens de normale flow van de verwerking
		add_filter( 'views_edit-shop_order', 'put_claimed_after_processing' );

		// Tel geclaimde orders bij de nog te behandelen bestellingen
		add_filter( 'woocommerce_menu_order_count', 'ob2c_add_claimed_to_open_orders_count', 10, 1 );

		// Zorg ervoor dat refunds aan dezelfde winkel toegekend worden als het oorspronkelijke bestelling, zodat ze correct getoond worden in de gefilterde rapporten
		// @toDo: Te testen
		// add_action( 'woocommerce_order_refunded', 'ob2c_copy_metadata_from_order_to_refund', 10, 2 );

		// Maak de boodschap om te filteren op winkel beschikbaar bij de rapporten
		add_filter( 'woocommerce_reports_get_order_report_data_args', 'limit_reports_to_member_shop', 10, 2 );
	}

	function add_member_of_shop_user_field( $user ) {
		if ( user_can( $user, 'manage_woocommerce' ) ) {
			$key = 'blog_'.get_current_blog_id().'_member_of_shop';
			?>
			<h3>Regiosamenwerking</h3>
			<table class="form-table">
				<tr>
					<th><label for="<?php echo $key; ?>">Ik bevestig orders voor ...</label></th>
					<td>
						<?php
							echo '<select name="'.$key.'" id="'.$key.'">';
								$member_of = get_the_author_meta( $key, $user->ID );
								$shops = get_option('oxfam_member_shops');
								$selected = empty( $member_of ) ? ' selected' : '';
								echo '<option value=""'.$selected.'>(selecteer)</option>';
								foreach ( $shops as $shop ) {
									$selected = ( $shop === $member_of ) ? ' selected' : '';
									echo '<option value="'.$shop.'"'.$selected.'>'.trim_and_uppercase( $shop ).'</option>';
								}
							echo '</select>';
						?>
						<span class="description">Opgelet: deze keuze bepaalt aan welke winkel de bestellingen die jij bevestigt toegekend worden!</span>
					</td>
				</tr>
			</table>
			<?php
		}
	}

	function save_member_of_shop_user_field( $user_id ) {
		if ( ! current_user_can( 'edit_users', $user_id ) ) {
			return false;
		}

		// Usermeta is netwerkbreed, dus ID van blog toevoegen aan de key!
		$member_key = 'blog_'.get_current_blog_id().'_member_of_shop';
		// Check of het veld wel bestaat voor deze gebruiker
		if ( isset($_POST[$member_key]) ) {
			update_user_meta( $user_id, $member_key, $_POST[$member_key] );
		}
	}

	function auto_claim_local_pickup( $order_id ) {
		if ( ! $order_id ) {
			return;
		}
		$order = wc_get_order( $order_id );
		// Check of de betaling wel succesvol was door enkel te claimen indien status reeds op 'In behandeling' staat
		if ( $order->has_shipping_method('local_pickup_plus') and $order->get_status() === 'processing' ) {
			$order->update_status('claimed');
		}
	}

	function register_claiming_member_shop( $order_id, $order ) {
		$logger = wc_get_logger();
		$context = array( 'source' => 'Oxfam Regional Webshop' );

		if ( get_current_user_id() > 1 ) {
			// Een gewone klant heeft deze eigenschap niet en retourneert dus sowieso 'false'
			$owner = get_the_author_meta( 'blog_'.get_current_blog_id().'_member_of_shop', get_current_user_id() );
		} else {
			// Indien het order rechtstreeks afgerond wordt vanuit SendCloud gebeurt het onder de user met ID 1 (= Frederik)
			if ( get_current_blog_id() == 24 ) {
				$owner = 'antwerpen';
				$logger->info( $order->get_order_number().': order completed from SendCloud and attributed to '.$owner, $context );
			}
		}
		
		if ( $order->has_shipping_method('local_pickup_plus') ) {
			// Koppel automatisch aan de winkel waar de afhaling zal gebeuren
			$methods = $order->get_shipping_methods();
			$method = reset( $methods );
			
			$meta_data = $method->get_meta_data();
			$pickup_data = reset($meta_data);
			// OF NIEUWE MANIER?
			// $pickup_location = $method->get_meta('pickup_location');
			// $pickup_location['shipping_company']
			$city = mb_strtolower( trim( str_replace( 'Oxfam-Wereldwinkel', '', $pickup_data->value['shipping_company'] ) ) );
			if ( in_array( $city, get_option('oxfam_member_shops') ) ) {
				// Dubbelcheck of deze stad wel tussen de deelnemende winkels zit
				$owner = $city;
			} elseif ( strpos( $city, 'boortmeerbeek' ) !== false ) {
				// HABOBIB Boortmeerbeek is zelf geen lid van de regio!
				$owner = 'boortmeerbeek';
				$logger->info( $order->get_order_number().': HABOBIB order attributed to '.$owner, $context );
			}
		}

		if ( ! isset( $owner ) ) {
			send_automated_mail_to_helpdesk( 'Geen eigenaar gevonden voor te claimen bestelling '.$order->get_order_number(), '<p>Gelieve het \'claimed_by\'-veld te checken <a href="'.$order->get_edit_order_url().'">in de back-end</a>!</p>' );
			// Koppel als laatste redmiddel aan de locatie van de hoofdwinkel
			$owner = mb_strtolower( get_oxfam_shop_data('city') );
		}

		update_post_meta( $order_id, 'claimed_by', $owner );
	}

	function delete_claiming_member_shop( $order_id ) {
		delete_post_meta( $order_id, 'claimed_by' );
	}

	function add_claimed_by_filtering() {
		global $pagenow, $post_type;
		if ( $pagenow === 'edit.php' and $post_type === 'shop_order' ) {
			$shops = get_option('oxfam_member_shops');
			echo '<select name="claimed_by" id="claimed_by">';
				$all = ( ! empty($_GET['claimed_by']) and sanitize_text_field($_GET['claimed_by']) === 'all' ) ? ' selected' : '';
				echo '<option value="all" '.$all.'>Alle winkels uit de regio</option>';
				foreach ( $shops as $shop ) {
					$selected = ( ! empty($_GET['claimed_by']) and sanitize_text_field($_GET['claimed_by']) === $shop ) ? ' selected' : '';
					echo '<option value="'.$shop.'" '.$selected.'>Enkel '.trim_and_uppercase( $shop ).'</option>';
				}
			echo '</select>';
		}
	}

	function filter_orders_by_owner( $query ) {
		global $pagenow, $post_type;
		if ( $pagenow === 'edit.php' and $post_type === 'shop_order' and $query->query['post_type'] === 'shop_order' ) {
			if ( ! empty( $_GET['claimed_by'] ) and $_GET['claimed_by'] !== 'all' ) {
				$meta_query_args = array(
					'relation' => 'AND',
					array(
						'key' => 'claimed_by',
						'value' => $_GET['claimed_by'],
						'compare' => '=',
					),
				);
				$query->set( 'meta_query', $meta_query_args );
			} elseif ( 1 < 0 ) {
				// Eventueel AUTOMATISCH filteren op eigen winkel (tenzij expliciet anders aangegeven)
				$owner = get_the_author_meta( 'blog_'.get_current_blog_id().'_member_of_shop', get_current_user_id() );
				if ( ! $owner ) {
					$meta_query_args = array(
						'relation' => 'AND',
						array(
							'key' => 'claimed_by',
							'value' => $owner,
							'compare' => '=',
						),
					);
					$query->set( 'meta_query', $meta_query_args );
				}
			}
		}
	}

	function add_claimed_by_column( $columns ) {
		$columns['claimed_by'] = 'Behandeling door';
		// Eventueel bepaalde kolommen volledig verwijderen?
		// unset( $columns['order_notes'] );
		// unset( $columns['order_actions'] );
		return $columns;
	}

	function make_claimed_by_column_sortable( $columns ) {
		$columns['claimed_by'] = 'claimed_by';
		return $columns;
	}

	function get_claimed_by_value( $column ) {
		global $the_order;
		if ( $column === 'claimed_by' ) { 
			if ( $the_order->get_status() === 'pending' ) {
				echo '<i>nog niet betaald</i>';
			} elseif ( $the_order->get_status() === 'processing' ) {
				echo '<i>nog niet bevestigd</i>';
			} elseif ( $the_order->get_status() === 'cancelled' ) {
				echo '<i>geannuleerd</i>';
			} else {
				if ( $the_order->get_meta('claimed_by') !== '' ) {
					echo 'OWW '.trim_and_uppercase( $the_order->get_meta('claimed_by') );
				} else {
					// Reeds verderop in het verwerkingsproces maar geen winkel? Dat zou niet mogen zijn!
					echo '<i>ERROR</i>';
				}
			}
		}
	}

	// Voeg ook een kolom toe aan het besteloverzicht in de back-end
	add_filter( 'manage_edit-shop_order_columns', 'add_estimated_delivery_column', 12 );

	// Maak sorteren op deze nieuwe kolom mogelijk
	add_filter( 'manage_edit-shop_order_sortable_columns', 'make_estimated_delivery_column_sortable' );

	// Toon de data van elk order in de kolom
	add_action( 'manage_shop_order_posts_custom_column' , 'get_estimated_delivery_value', 10, 2 );

	// Voer de sortering uit tijdens het bekijken van orders in de admin (voor alle zekerheid NA filteren uitvoeren)
	add_action( 'pre_get_posts', 'sort_orders_on_custom_column', 20 );
	
	// Zorg ervoor dat links naar Google Maps meteen in het juiste formaat staan
	add_filter( 'woocommerce_shipping_address_map_url_parts', 'ob2c_shuffle_google_maps_address', 10, 1 );
	add_filter( 'woocommerce_shipping_address_map_url', 'ob2c_add_starting_point_to_google_maps', 10, 2 );

	function ob2c_shuffle_google_maps_address( $address ) {
		$address['city'] = $address['postcode'].' '.$address['city'];
		unset($address['address_2']);
		unset($address['state']);
		unset($address['postcode']);
		return $address;
	}

	function ob2c_add_starting_point_to_google_maps( $url, $order ) {
		// Neem als default de hoofdwinkel
		$shop_address = get_shop_address();
		
		if ( $order->get_meta('claimed_by') !== '' ) {
			foreach ( ob2c_get_pickup_locations() as $shop_post_id => $shop_name ) {
				if ( stristr( $shop_name, $order->get_meta('claimed_by') ) ) {
					// Toon route vanaf de winkel die de thuislevering zal uitvoeren a.d.h.v. de post-ID in de straatnaam
					$shop_address = get_shop_address( array( 'id' => $shop_post_id ) );
					break;
				}
			}
		}

		// Zet locatielink om in routelink, voeg landencode en eindslash toe en vervang fixed zoomniveau door fietsnavigatie
		// Tip: meerdere stops zijn mogelijk, blijf adressen gewoon chainen met slashes!
		return str_replace( 'https://maps.google.com/maps?&q=', 'https://www.google.com/maps/dir/' . rawurlencode( str_replace( '<br/>', ', ', $shop_address ) ) . ',+BE/', str_replace( '&z=16', '/data=!4m2!4m1!3e1', $url ) );

		// Overige dataparameters
		// Car 			/data=!4m2!4m1!3e0
		// Bicycling 	/data=!4m2!4m1!3e1
		// Walking 		/data=!4m2!4m1!3e2
	}

	// Maak bestellingen vindbaar o.b.v. ordernummer en behandelende winkel
	add_filter( 'woocommerce_shop_order_search_fields', 'ob2c_add_shop_order_search_fields' );

	function ob2c_add_shop_order_search_fields( $fields ) {
		$fields[] = '_order_number';
		$fields[] = 'claimed_by';
		return $fields;
	}

	function sort_orders_on_custom_column( $query ) {
		global $pagenow, $post_type;
		if ( $pagenow === 'edit.php' and $post_type === 'shop_order' and $query->query['post_type'] === 'shop_order' ) {
			// Check of we moeten sorteren op één van onze custom kolommen
			if ( $query->get( 'orderby' ) === 'estimated_delivery' ) {
				$query->set( 'meta_key', 'estimated_delivery' );
				$query->set( 'orderby', 'meta_value_num' );
			}
			if ( $query->get( 'orderby' ) === 'claimed_by' ) {
				$query->set( 'meta_key', 'claimed_by' );
				$query->set( 'orderby', 'meta_value' );
			}
		}
	}

	function add_estimated_delivery_column( $columns ) {
		$columns['estimated_delivery'] = 'Uiterste leverdag';
		$columns['excel_file_name'] = 'Picklijst';
		unset($columns['billing_address']);
		unset($columns['order_notes']);
		return $columns;
	}

	function make_estimated_delivery_column_sortable( $columns ) {
		$columns['estimated_delivery'] = 'estimated_delivery';
		return $columns;
	}

	function get_estimated_delivery_value( $column ) {
		global $the_order;
		if ( $column === 'estimated_delivery' ) {
			$processing_statusses = array( 'processing', 'claimed' );
			$completed_statusses = array( 'completed' );
			if ( $the_order->get_meta('estimated_delivery') !== '' ) {
				$delivery = date( 'Y-m-d H:i:s', intval( $the_order->get_meta('estimated_delivery') ) );
				if ( in_array( $the_order->get_status(), $processing_statusses ) ) {
					if ( get_date_from_gmt( $delivery, 'Y-m-d' ) < date_i18n( 'Y-m-d' ) ) {
						$color = 'red';
					} elseif ( get_date_from_gmt( $delivery, 'Y-m-d' ) === date_i18n( 'Y-m-d' ) ) {
						$color = 'orange';
					} else {
						$color = 'green';
					}
					echo '<span style="color: '.$color.';">'.get_date_from_gmt( $delivery, 'd-m-Y' ).'</span>';
				} elseif ( in_array( $the_order->get_status(), $completed_statusses ) ) {
					if ( $the_order->get_date_completed() !== NULL ) {
						if ( $the_order->get_date_completed()->date_i18n( 'Y-m-d H:i:s' ) < $delivery ) {
							echo '<i>op tijd geleverd</i>';
						} else {
							echo '<i>te laat geleverd</i>';
						}
					} else {
						echo '<i>afwerkdatum ontbreekt</i>';
					}
				}
			} else {
				if ( $the_order->get_status() === 'cancelled' ) {
					echo '<i>geannuleerd</i>';
				} elseif ( $the_order->get_meta('is_b2b_sale') === 'yes' ) {
					echo '<i>B2B-bestelling</i>';
				} else {
					echo '<i>niet beschikbaar</i>';
				}
			}
		} elseif ( $column === 'excel_file_name' ) {
			if ( strpos( $the_order->get_meta('_excel_file_name'), '.xlsx' ) > 10 ) {
				$file_path = '/uploads/xlsx/'.$the_order->get_meta('_excel_file_name');
				if ( file_exists( WP_CONTENT_DIR . $file_path ) ) {
					echo '<a href="'.content_url( $file_path ).'" download>Download</a>';
				} else {
					echo '<i>niet meer beschikbaar</i>';
				}
			} else {
				echo '<i>niet beschikbaar</i>';
			}
		}
	}

	function put_claimed_after_processing( $array ) {
		// Check eerst of de statusknop wel aanwezig is op dit moment!
		if ( array_key_exists( 'wc-claimed', $array ) ) {
			$cnt = 1;
			$stored_value = $array['wc-claimed'];
			unset($array['wc-claimed']);
			foreach ( $array as $key => $value ) {
				if ( $key === 'wc-processing' ) {
					$array = array_slice( $array, 0, $cnt ) + array( 'wc-claimed' => $stored_value ) + array_slice( $array, $cnt, count($array) - $cnt );
					// Zorg ervoor dat de loop stopt!
					break;
				} elseif ( $key === 'wc-completed' ) {
					$array = array_slice( $array, 0, $cnt-1 ) + array( 'wc-claimed' => $stored_value ) + array_slice( $array, $cnt-1, count($array) - ($cnt-1) );
					break;
				}
				$cnt++;
			}
		}
		return $array;
	}	

	// Bevat geen WooCommerce-acties want die worden wegens een bug ingeladen via JavaScript!
	// add_filter( 'bulk_actions-edit-shop_order', 'my_custom_bulk_actions', 1, 10000 );

	function my_custom_bulk_actions( $actions ) {
		var_dump_pre( $actions );
		return $actions;
	}

	// Poging om de actie die de JavaScript toe te voegen weer uitschakelt
	// add_action( 'plugins_loaded', 'disable_wc_actions' );

	function disable_wc_actions() {
		remove_action( 'bulk_actions-edit-shop_order', array( WC_Admin_CPT_Shop_Order::getInstance(), 'admin_footer' ), 10 );
	}

	function ob2c_add_claimed_to_open_orders_count( $count ) {
		$count += wc_orders_count('claimed');
		return $count;
	}

	function ob2c_copy_metadata_from_order_to_refund( $order_id, $refund_id ) {
		$order = wc_get_order( $order_id );
		$refund = wc_get_order( $refund_id );
		if ( $order !== false and $refund !== false ) {
			$refund->update_meta_data( 'claimed_by', $order->get_meta('claimed_by') );
			$refund->save();
		}
	}

	// Global om ervoor te zorgen dat de boodschap enkel in de eerste loop geëchood wordt
	$warning_shown = false;

	function limit_reports_to_member_shop( $args ) {
		global $pagenow, $warning_shown;
		if ( $pagenow === 'admin.php' and $_GET['page'] === 'wc-reports' ) {
			if ( ! empty( $_GET['claimed_by'] ) ) {
				$new_args['where_meta'] = array(
					'relation' => 'AND',
					array(
						'meta_key'   => 'claimed_by',
						'meta_value' => $_GET['claimed_by'],
						'operator'   => '=',
					),
				);

				// Nette manier om twee argumenten te mergen (in het bijzonder voor individuele productraportage, anders blijft enkel de laatste meta query bewaard)
				$args['where_meta'] = array_key_exists( 'where_meta', $args ) ? wp_parse_args( $new_args['where_meta'], $args['where_meta'] ) : $new_args['where_meta'];
				
				if ( ! $warning_shown ) {
					echo "<div style='background-color: red; color: white; padding: 0.25em 1em;'>";
						echo "<p>Opgelet: momenteel bekijk je een gefilterd rapport met enkel de bestellingen die verwerkt werden door <b>OWW ".trim_and_uppercase( $_GET['claimed_by'] )."</b>.</p>";
						echo "<p style='text-align: right;'>";
							$members = get_option('oxfam_member_shops');
							foreach ( $members as $member ) {
								if ( $member !== $_GET['claimed_by'] ) {
									echo "<a href='".esc_url( add_query_arg( 'claimed_by', $member ) )."' style='color: black;'>Bekijk ".trim_and_uppercase( $member )." »</a><br/>";
								}
							}
							echo "<br/><a href='".esc_url( remove_query_arg( 'claimed_by' ) )."' style='color: black;'>Terug naar volledige regio »</a>";
						echo "</p>";
					echo "</div>";
				}
			} else {
				if ( ! $warning_shown ) {
					echo "<div style='background-color: green; color: white; padding: 0.25em 1em;'>";
						echo "<p>Momenteel bekijk je het rapport met de bestellingen van alle winkels uit de regio. Klik hieronder om de omzet te filteren op een bepaalde winkel.</p>";
						echo "<p style='text-align: right;'>";
							$members = get_option('oxfam_member_shops');
							foreach ( $members as $member ) {
								echo "<a href='".esc_url( add_query_arg( 'claimed_by', $member ) )."' style='color: black;'>Bekijk enkel ".trim_and_uppercase( $member )." »</a><br/>";
							}
						echo "</p>";
					echo "</div>";
				}
			}
			$warning_shown = true;
		}
		return $args;
	}

	// Voeg gewicht en volume toe op orderdetail
	add_action( 'woocommerce_admin_order_data_after_shipping_address', 'ob2c_add_logistic_parameters', 10, 1 );

	function ob2c_add_logistic_parameters( $order ) {
		echo '<p><strong>Logistieke info:</strong><br/>';
		$logistics = get_logistic_params( $order );
		echo number_format( $logistics['volume'], 1, ',', '.' ).' liter / '.number_format( $logistics['weight'], 1, ',', '.' ).' kg';
		echo '</p>';
	}

	// Voeg acties toe op orderdetailscherm om status te wijzigen (want keuzemenu bestelstatus geblokkeerd!)
	add_action( 'woocommerce_order_actions', 'add_order_status_changing_actions' );

	function add_order_status_changing_actions( $actions ) {
		global $theorder;
		
		if ( $theorder->has_shipping_method('local_pickup_plus') ) {
			$completed_label = 'Markeer als klaargezet in de winkel';
		} else {
			$completed_label = 'Markeer als ingepakt voor verzending';
		}

		if ( ! is_regional_webshop() ) {
			if ( $theorder->get_status() === 'processing' ) {
				$actions['oxfam_mark_completed'] = $completed_label;
			}
		} else {
			if ( $theorder->get_status() === 'claimed' ) {
				$actions['oxfam_mark_completed'] = $completed_label;
			} elseif ( $theorder->get_status() === 'processing' ) {
				$actions['oxfam_mark_claimed'] = 'Markeer als geclaimd';
			}
		}

		if ( $theorder->get_meta('is_b2b_sale') === 'yes' ) {
			// $actions['oxfam_mark_invoiced'] = 'Markeer als factuur opgesteld';
		}

		// Pas vanaf WC 3.1+ toegang tot alle statussen
		// write_log( wc_print_r( $actions, true ) );
		// unset($actions['send_order_details']);
		// unset($actions['regenerate_download_permissions']);
		
		return $actions;
	}

	add_action( 'woocommerce_order_action_oxfam_mark_completed', 'proces_oxfam_mark_completed' );
	add_action( 'woocommerce_order_action_oxfam_mark_claimed', 'proces_oxfam_mark_claimed' );

	function proces_oxfam_mark_completed( $order ) {
		$order->set_status('completed');
		$order->save();
	}

	function proces_oxfam_mark_claimed( $order ) {
		$order->set_status('claimed');
		$order->save();
	}

	// Voer shortcodes ook uit in widgets, titels en e-mailfooters
	add_filter( 'widget_text', 'do_shortcode' );
	add_filter( 'the_title', 'do_shortcode' );
	add_filter( 'woocommerce_email_footer_text', 'do_shortcode' );

	// Zorg ervoor dat het Return-Path gelijk is aan de afzender (= webshop.gemeente@oxfamwereldwinkels.be, met correct ingesteld MX-record)
	// add_action( 'phpmailer_init', 'fix_bounce_address' );

	function fix_bounce_address( $phpmailer ) {
		$phpmailer->Sender = $phpmailer->From;
	}
	
	// Pas het onderwerp van de mails aan naargelang de gekozen levermethode
	add_filter( 'woocommerce_email_subject_customer_processing_order', 'change_processing_order_subject', 10, 2 );
	add_filter( 'woocommerce_email_subject_customer_completed_order', 'change_completed_order_subject', 10, 2 );
	add_filter( 'woocommerce_email_subject_customer_refunded_order', 'change_refunded_order_subject', 10, 2 );
	add_filter( 'woocommerce_email_subject_customer_note', 'change_note_subject', 10, 2 );

	function change_processing_order_subject( $subject, $order ) {
		$subject = sprintf( __( 'Onderwerp van de 1ste bevestigingsmail inclusief besteldatum (%s)', 'oxfam-webshop' ), $order->get_date_created()->date_i18n('d/m/Y') );
		if ( get_current_site()->domain !== 'shop.oxfamwereldwinkels.be' ) { 
			$subject = 'DEMO '.$subject;
		}
		return $subject;
	}

	function change_completed_order_subject( $subject, $order ) {
		if ( $order->has_shipping_method('local_pickup_plus') ) {
			$subject = sprintf( __( 'Onderwerp van de 2de bevestigingsmail (indien afhaling) inclusief besteldatum (%s)', 'oxfam-webshop' ), $order->get_date_created()->date_i18n('d/m/Y') );
		} else {
			$subject = sprintf( __( 'Onderwerp van de 2de bevestigingsmail (indien thuislevering) inclusief besteldatum (%s)', 'oxfam-webshop' ), $order->get_date_created()->date_i18n('d/m/Y') );
		}
		if ( get_current_site()->domain !== 'shop.oxfamwereldwinkels.be' ) { 
			$subject = 'DEMO '.$subject;
		}
		return $subject;
	}

	function change_refunded_order_subject( $subject, $order ) {
		if ( $order->get_total_refunded() == $order->get_total() ) {
			$subject = sprintf( __( 'Onderwerp van de terugbetalingsmail (volledig) inclusief besteldatum (%s)', 'oxfam-webshop' ), $order->get_date_created()->date_i18n('d/m/Y') );
		} else {
			$subject = sprintf( __( 'Onderwerp van de terugbetalingsmail (gedeeltelijk) inclusief besteldatum (%s)', 'oxfam-webshop' ), $order->get_date_created()->date_i18n('d/m/Y') );
		}
		if ( get_current_site()->domain !== 'shop.oxfamwereldwinkels.be' ) { 
			$subject = 'DEMO '.$subject;
		}
		return $subject;
	}

	function change_note_subject( $subject, $order ) {
		$subject = sprintf( __( 'Onderwerp van de opmerkingenmail inclusief besteldatum (%s)', 'oxfam-webshop' ), $order->get_date_created()->date_i18n('d/m/Y') );
		if ( get_current_site()->domain !== 'shop.oxfamwereldwinkels.be' ) { 
			$subject = 'DEMO '.$subject;
		}
		return $subject;
	}

	// Wijzig de bestemmelingen van de adminmails
	add_filter( 'woocommerce_email_recipient_new_order', 'switch_admin_recipient_dynamically', 10, 2 );
	add_filter( 'woocommerce_email_recipient_cancelled_order', 'switch_admin_recipient_dynamically', 10, 2 );

	function switch_admin_recipient_dynamically( $recipients, $order ) {
		// Filter wordt ook doorlopen op instellingenpagina (zonder 2de argument), dus check eerst of het object wel een order is
		if ( $order !== NULL and $order instanceof WC_Order ) {
			
		}

		return get_staged_recipients( $recipients );
	}

	// Leid mails op DEV-omgevingen om naar de site admin
	function get_staged_recipients( $recipients ) {
		if ( $_SERVER['SERVER_NAME'] !== 'shop.oxfamwereldwinkels.be' ) {
			return get_option('admin_email');
		}
		return $recipients;
	}

	// Pas de header van de mails aan naargelang de gekozen levermethode
	add_filter( 'woocommerce_email_heading_new_order', 'change_new_order_email_heading', 10, 2 );
	add_filter( 'woocommerce_email_heading_customer_processing_order', 'change_processing_email_heading', 10, 2 );
	add_filter( 'woocommerce_email_heading_customer_completed_order', 'change_completed_email_heading', 10, 2 );
	add_filter( 'woocommerce_email_heading_customer_refunded_order', 'change_refunded_email_heading', 10, 2 );
	add_filter( 'woocommerce_email_heading_customer_note', 'change_note_email_heading', 10, 2 );
	add_filter( 'woocommerce_email_heading_customer_new_account', 'change_new_account_email_heading', 10, 2 );

	function change_new_order_email_heading( $email_heading, $order ) {
		$email_heading = __( 'Heading van de mail aan de webshopbeheerder', 'oxfam-webshop' );
		return $email_heading;
	}

	function change_processing_email_heading( $email_heading, $order ) {
		$email_heading = __( 'Heading van de 1ste bevestigingsmail', 'oxfam-webshop' );
		return $email_heading;
	}

	function change_completed_email_heading( $email_heading, $order ) {
		if ( $order->has_shipping_method('local_pickup_plus') ) {
			$email_heading = __( 'Heading van de 2de bevestigingsmail (indien afhaling)', 'oxfam-webshop' );
		} else {
			$email_heading = __( 'Heading van de 2de bevestigingsmail (indien thuislevering)', 'oxfam-webshop' );
		}
		return $email_heading;
	}

	function change_refunded_email_heading( $email_heading, $order ) {
		if ( $order->get_total_refunded() == $order->get_total() ) {
			$email_heading = __( 'Heading van de terugbetalingsmail (volledig)', 'oxfam-webshop' );
		} else {
			$email_heading = __( 'Heading van de terugbetalingsmail (gedeeltelijk)', 'oxfam-webshop' );
		}
		return $email_heading;
	}

	function change_note_email_heading( $email_heading, $order ) {
		$email_heading = __( 'Heading van de opmerkingenmail', 'oxfam-webshop' );
		return $email_heading;
	}

	function change_new_account_email_heading( $email_heading, $email ) {
		$email_heading = __( 'Heading van de welkomstmail', 'oxfam-webshop' );
		return $email_heading;
	}

	// Schakel autosaves uit
	add_action( 'wp_print_scripts', function() { wp_deregister_script('autosave'); } );

	if ( is_main_site() ) {
		// Zorg ervoor dat productrevisies bijgehouden worden op de hoofdsite
		add_filter( 'woocommerce_register_post_type_product', 'add_product_revisions' );
		// Log wijzigingen aan metadata na het succesvol bijwerken
		add_action( 'updated_post_metadata', 'log_product_changes', 100, 4 );
		// Toon de lokale webshops die het product nog op voorraad hebben ZEER TRAGE FUNCTIE
		// add_action( 'woocommerce_product_options_inventory_product_data', 'add_inventory_fields', 5 );
	}
	
	function add_product_revisions( $vars ) {
		$vars['supports'][] = 'revisions';
		return $vars;
	}

	function log_product_changes( $meta_id, $post_id, $meta_key, $new_meta_value ) {
		// Alle overige interessante data zitten in het algemene veld '_product_attributes' dus daarvoor best een ander filtertje zoeken
		$watched_metas = array( '_price', '_stock_status', '_tax_class', '_weight', '_length', '_width', '_height', '_thumbnail_id', '_force_sell_synced_ids', '_product_attributes' );
		// Deze actie vuurt bij 'single value meta keys' enkel indien er een wezenlijke wijziging was, dus check hoeft niet meer
		if ( get_post_type($post_id) === 'product' and in_array( $meta_key, $watched_metas ) ) {
			// Schrijf weg in log per weeknummer (zonder leading zero's)
			$user = wp_get_current_user();
			$str = date_i18n('d/m/Y H:i:s') . "\t" . get_post_meta( $post_id, '_sku', true ) . "\t" . $user->user_firstname . "\t" . $meta_key . " gewijzigd in " . serialize($new_meta_value) . "\t" . get_the_title( $post_id ) . "\n";
			file_put_contents( WP_CONTENT_DIR."/changelog-week-".intval( date_i18n('W') ).".csv", $str, FILE_APPEND );
		}
	}

	function add_inventory_fields() {
		global $product_object;
		echo '<div class="options_group oft"><p class="form-field">';
			$shops_instock = array();
			$sites = get_sites( array( 'site__not_in' => get_site_option('oxfam_blocked_sites'), 'public' => 1, 'orderby' => 'path' ) );
			foreach ( $sites as $site ) {
				if ( $site->blog_id != 1 ) {
					switch_to_blog( $site->blog_id );
					$local_product = wc_get_product( wc_get_product_id_by_sku( $product_object->get_sku() ) );
					if ( $local_product !== false and $local_product->is_in_stock() ) {
						$shops_instock[] = get_webshop_name();
					}
					restore_current_blog();
				}
			}
			echo '<label>Op voorraad? ('.count($shops_instock).'/'.(count($sites)-1).')</label>';
			if ( count($shops_instock) > 0 ) {
				foreach ($shops_instock as $shop_name ) {
					echo $shop_name.'<br/>';
				}
			}
		echo '</p></div>';
	}

	// Verberg alle koopknoppen op het hoofddomein (ook reeds geblokkeerd via .htaccess but better be safe than sorry)
	add_filter( 'woocommerce_get_price_html' , 'no_orders_on_main', 10, 2 );
	
	function no_orders_on_main( $price, $product ) {
		if ( ! is_admin() ) {
			if ( is_b2b_customer() ) {
				$price .= ' per stuk';
			}
		}
		return $price;
	}

	// Doorstreepte adviesprijs en badge uitschakelen (meestal geen rechtstreekse productkorting)
	add_filter( 'woocommerce_sale_flash', '__return_false' );
	add_filter( 'woocommerce_format_sale_price', 'format_sale_as_regular_price', 10, 3 );

	function format_sale_as_regular_price( $price, $regular_price, $sale_price ) {
		return wc_price($regular_price);
	}

	// Zorg ervoor dat winkelbeheerders na bv. het opslaan van feestdagen of het filteren in regiorapporten niet teruggedwongen worden naar het dashboard
	add_filter( 'ure_admin_menu_access_allowed_args', 'ure_allow_args_for_oxfam_options', 10, 1 );

	function ure_allow_args_for_oxfam_options( $args ) {
		$args['edit.php'][''][] = 'claimed_by';
		$args['edit.php'][''][] = 'stock_status';
		// Deze argumenten zijn reeds automatisch voorzien ...
		// $args['edit.php'][''][] = 'product_cat';
		// $args['edit.php'][''][] = 'filter_action';
		// $args['edit.php'][''][] = 'action';
		// $args['edit.php'][''][] = 'action2';
		// $args['edit.php'][''][] = 'paged';
		$args['admin.php']['wc-reports'] = array(
			'tab',
			'report',
			'range',
			'claimed_by',
			'product_ids',
			'show_categories',
			'coupon_codes',
			'start_date',
			'end_date',
			'wc_reports_nonce',
		);
		$args['admin.php']['oxfam-options'] = array(
			'page',
			'settings-updated',
		);
		$args['admin.php']['wpml_plugin_log'] = array(
			'page',
			'paged',
			'action',
			'action2',
			's',
			'wpml-list_table_nonce',
			'_wp_http_referer',
			'_wpnonce',
		);
		$args['admin.php']['pmxe-admin-export'] = array(
			'id',
			'action',
			'pmxe_nt',
			'warnings',
		);
		$args['admin.php']['pmxe-admin-manage'] = array(
			'id',
			'action',
			'pmxe_nt',
			'warnings',
		);
		$args['profile.php'][''] = array(
			'updated',
		);
		return $args;
	}


	
	###############
	# WOOCOMMERCE #
	###############

	// Voeg allerlei checks toe net na het inladen van WordPress
	add_action( 'init', 'woocommerce_parameter_checks_after_loading' );
	
	function woocommerce_parameter_checks_after_loading() {
		// Uniformeer de gebruikersdata net voor we ze opslaan in de database STAAT GEEN WIJZIGINGEN TOE
		// add_filter( 'update_user_metadata', 'sanitize_woocommerce_customer_fields', 10, 5 );

		if ( ! empty( $_GET['referralZip'] ) ) {
			// Dit volstaat ook om de variabele te creëren indien nog niet beschikbaar
			WC()->customer->set_billing_postcode( intval( $_GET['referralZip'] ) );
			WC()->customer->set_shipping_postcode( intval( $_GET['referralZip'] ) );
		}
		if ( ! empty( $_GET['referralCity'] ) ) {
			WC()->customer->set_billing_city( $_GET['referralCity'] );
			WC()->customer->set_shipping_city( $_GET['referralCity'] );
			// @toDo: Check of dit ingesteld wordt indien winkelmandje ontbreekt
			// write_log( print_r( $_GET['referralCity'], true ) );
		}
	}

	// Verhoog het aantal producten per winkelpagina WORDT OVERRULED DOOR SAVOY
	add_filter( 'loop_shop_per_page', 'modify_number_of_products_per_page', 20, 1 );

	function modify_number_of_products_per_page( $per_page ) {
		return 20;
	}

	// Orden items in bestellingen volgens stijgend productnummer
	add_filter( 'woocommerce_order_get_items', 'sort_order_by_sku', 10, 2 );

	function sort_order_by_sku( $items, $order ) {
		uasort( $items, function( $a, $b ) {
			// Verhinder dat we ook tax- en verzendlijnen shufflen
			if ( $a->get_type() === 'line_item' and $b->get_type() === 'line_item' ) {
				$product_a = $a->get_product();
				$product_b = $b->get_product();
				// Zorg ervoor dat variabelen altijd gedefinieerd zijn!
				if ( $product_a !== false ) {
					$sku_a = $product_a->get_sku();
				} else {
					$sku_a = 'error';
				}
				if ( $product_b !== false ) {
					$sku_b = $product_b->get_sku();
				} else {
					$sku_b = 'error';
				}
				// Deze logica plaatst niet-numerieke referenties (en dus ook inmiddels ter ziele gegane producten) onderaan
				if ( is_numeric( $sku_a ) ) {
					if ( is_numeric( $sku_b ) ) {
						return ( intval( $sku_a ) < intval( $sku_b ) ) ? -1 : 1;	
					} else {
						return -1;
					}
				} else {
					if ( is_numeric( $sku_b ) ) {
						return 1;	
					} else {
						return -1;
					}
				}
			} else {
				return 0;
			}
		} );
		return $items;
	}

	// Zorg ervoor dat slechts bepaalde statussen bewerkbaar zijn
	add_filter( 'wc_order_is_editable', 'limit_editable_orders', 20, 2 );

	function limit_editable_orders( $editable, $order ) {
		// Slugs van alle extra orderstatussen (zonder 'wc'-prefix) die bewerkbaar moeten zijn
		// Opmerking: standaard zijn 'pending', 'on-hold' en 'auto-draft' bewerkbaar
		$editable_custom_statuses = array( 'on-hold' );
		if ( in_array( $order->get_status(), $editable_custom_statuses ) ) {
			$editable = true;
		} else {
			$editable = false;
		}
		return $editable;
	}
	
	// Voeg sorteren op artikelnummer toe aan de opties op cataloguspagina's
	add_filter( 'woocommerce_get_catalog_ordering_args', 'add_sku_sorting' );
	add_filter( 'woocommerce_catalog_orderby', 'sku_sorting_orderby' );
	add_filter( 'woocommerce_default_catalog_orderby_options', 'sku_sorting_orderby' );

	function add_sku_sorting( $args ) {
		$orderby_value = isset( $_GET['orderby'] ) ? wc_clean( $_GET['orderby'] ) : apply_filters( 'woocommerce_default_catalog_orderby', get_option( 'woocommerce_default_catalog_orderby' ) );

		if ( 'sku' === $orderby_value ) {
			$args['orderby'] = 'meta_value_num';
			$args['order'] = 'ASC';
			$args['meta_key'] = '_shopplus_code';
		}

		if ( 'sku-desc' === $orderby_value ) {
			$args['orderby'] = 'meta_value_num';
			$args['order'] = 'DESC';
			$args['meta_key'] = '_shopplus_code';
		}

		if ( 'alpha' === $orderby_value ) {
			$args['orderby'] = 'title';
			$args['order'] = 'ASC';
		}

		if ( 'alpha-desc' === $orderby_value ) {
			$args['orderby'] = 'title';
			$args['order'] = 'DESC';
		}

		return $args;
	}
	
	function sku_sorting_orderby( $sortby ) {
		unset( $sortby['rating'] );
		unset( $sortby['popularity'] );
		$sortby['menu_order'] = 'Standaardvolgorde';
		$sortby['date'] = 'Laatst toegevoegd';
		unset( $sortby['price'] );
		unset( $sortby['price-desc'] );
		$sortby['sku'] = 'Stijgend artikelnummer';
		$sortby['sku-desc'] = 'Dalend artikelnummer';
		$sortby['alpha'] = 'Van A tot Z';
		$sortby['alpha-desc'] = 'Van Z tot A';
		return $sortby;
	}

	// Maak B2B-producten enkel zichtbaar voor B2B-klanten (cataloguspagina's)
	add_action( 'woocommerce_product_query', 'ob2c_constrain_assortment_to_b2b' );
	
	function ob2c_constrain_assortment_to_b2b( $query ) {
		// Verberg oude producten in lokale webshops
		if ( ! is_main_site() ) {
			$meta_query = (array) $query->get('meta_query');
			// Toon het product enkel als het nog in de OFT-database zit OF als het lokaal nog voorradig is!
			$meta_query[] = array(
				'relation' => 'OR',
				array(
					'key' => '_in_bestelweb',
					'value' => 'ja',
					'compare' => '=',
				),
				// Andere optie: != 'outofstock' als we ook niet-voorradig lokaal assortiment zichtbaar willen houden!
				array(
					'key' => '_stock_status',
					'value' => 'instock',
					'compare' => '=',
				),
			);
			// write_log( print_r( $meta_query, true ) );
			$query->set( 'meta_query', $meta_query );
		}

		// Sta ook toe dat medewerkers de B2B-producten te zien krijgen
		if ( ! is_b2b_customer() and ! current_user_can('manage_woocommerce') ) {
			$tax_query = (array) $query->get('tax_query');
			// Voeg query toe die alle producten uit de 'Grootverbruik'-categorie uitsluit
			$tax_query[] = array(
				'taxonomy' => 'product_cat',
				'field' => 'name',
				'terms' => array( 'Grootverbruik' ),
				'operator' => 'NOT IN',
			);
			$query->set( 'tax_query', $tax_query );
		}
	}

	// Maak B2B-producten enkel zichtbaar voor B2B-klanten (shortcodes)
	add_filter( 'woocommerce_shortcode_products_query', 'ob2c_shortcode_constrain_assortment_to_b2b' );
	
	function ob2c_shortcode_constrain_assortment_to_b2b( $query_args ) {
		// Sta ook toe dat medewerkers de B2B-producten te zien krijgen
		if ( ! is_b2b_customer() and ! current_user_can('manage_woocommerce') ) {
			// Voeg query toe die alle producten uit de 'Grootverbruik'-categorie uitsluit
			$query_args['tax_query'][] = array(
				'taxonomy' => 'product_cat',
				'field' => 'name',
				'terms' => array( 'Grootverbruik' ),
				'operator' => 'NOT IN',
			);
		}
		return $query_args;
	}

	// Doet de koopknop verdwijnen bij verboden producten én zwiert reeds toegevoegde producten uit het winkelmandje DUS NIET GEBRUIKEN OM HANDMATIG TOEVOEGEN VAN LEEGGOED TE VERHINDEREN
	add_filter( 'woocommerce_is_purchasable', 'ob2c_disable_products_not_in_assortment', 10, 2 );

	function ob2c_disable_products_not_in_assortment( $purchasable, $product ) {
		return apply_filters( 'ob2c_product_is_available', $product->get_id(), is_b2b_customer(), $purchasable );
	}

	// Filter wordt enkel doorlopen bij de 1ste toevoeging van een product!
	add_filter( 'woocommerce_add_to_cart_validation', 'ob2c_disallow_products_not_in_assortment', 10, 2 );

	function ob2c_disallow_products_not_in_assortment( $passed, $product_id ) {
		$passed_extra_conditions = apply_filters( 'ob2c_product_is_available', $product_id, is_b2b_customer(), $passed );

		if ( $passed and ! $passed_extra_conditions ) {
			wc_add_notice( sprintf( __( 'Foutmelding indien een gewone klant een B2B-product probeert te bestellen.', 'oxfam-webshop' ), is_b2b_customer() ), 'error' );
		}
		
		return $passed_extra_conditions;
	}

	// Maak de detailpagina van verboden producten volledig onbereikbaar
	add_action( 'template_redirect', 'ob2c_prevent_access_to_product_page' );
	
	function ob2c_prevent_access_to_product_page() {
		if ( is_product() ) {
			$product = wc_get_product( get_the_ID() );
			$available = apply_filters( 'ob2c_product_is_available', get_the_ID(), is_b2b_customer(), true );
			
			if ( ! $available or ( $product !== false and in_array( $product->get_sku(), get_oxfam_empties_skus_array() ) ) ) {
				// Als de klant nog niets in het winkelmandje zitten heeft, is er nog geen sessie om notices aan toe te voegen!
				if ( ! WC()->session->has_session() ) {
					WC()->session->set_customer_session_cookie(true);
				}

				// Gebruik deze boodschap voorlopig ook als foutmelding bij leeggoed
				wc_add_notice( sprintf( __( 'Foutmelding indien een gewone klant het B2B-product %s probeert te bekijken.', 'oxfam-webshop' ), get_the_title() ), 'error' );
				
				if ( wp_get_referer() ) {
					// Keer terug naar de vorige pagina
					wp_safe_redirect( wp_get_referer() );
				} else {
					// Ga naar de hoofdpagina van de winkel
					wp_safe_redirect( get_permalink( wc_get_page_id('shop') ) );
				}
				exit;
			}
		}
	}

	// Definieer een eigen filter zodat we de voorwaarden slecht één keer centraal hoeven in te geven
	add_filter( 'ob2c_product_is_available', 'ob2c_check_product_availability_for_customer', 10, 3 );

	function ob2c_check_product_availability_for_customer( $product_id, $is_b2b_customer, $available ) {
		// Sta ook toe dat medewerkers de B2B-producten te zien krijgen
		if ( ! $is_b2b_customer and ! current_user_can('manage_woocommerce') ) {
			if ( has_term( 'Grootverbruik', 'product_cat', $product_id ) ) {
				$product = wc_get_product( $product_id );
				write_log( "DISABLED PRODUCT ".$product->get_sku()." VIEW / PURCHASE / ADD TO CART FOR NON B2B CLIENT" );
				$available = false;
			}
		}

		return $available;
	}

	// Herlaad winkelmandje automatisch na aanpassing en zorg dat postcode altijd gecheckt wordt (en activeer live search indien plugin geactiveerd)
	add_action( 'wp_footer', 'cart_update_qty_script' );
	
	function cart_update_qty_script() {
		if ( is_cart() ) {
			?>
			<script type="text/javascript">
				jQuery(document).ready( function() {
					var wto;
					jQuery('div.woocommerce').on( 'change', '.qty', function() {
						clearTimeout(wto);
						// Time-out net iets groter dan buffertijd zodat we bij ingedrukt houden van de spinner niet gewoon +1/-1 doen
						wto = setTimeout(function() {
							jQuery("[name='update_cart']").trigger('click');
						}, 1000);
					});
					<?php if ( isset( $_GET['triggerGiftWrapper'] ) ) : ?>
						// jQuery('.wcgwp-modal-toggle').trigger('click') werkt niet
						jQuery('.wcgwp-modal-toggle')[0].click();
					<?php endif; ?>
				});
			</script>
			<?php
		} elseif ( is_account_page() and is_user_logged_in() ) {
			$current_user = wp_get_current_user();
			$user_meta = get_userdata($current_user->ID);
			$user_roles = $user_meta->roles;
			if ( in_array( 'local_manager', $user_roles ) and $current_user->user_email === get_webshop_email() ) {
				?>
				<script type="text/javascript">
					jQuery(document).ready( function() {
						jQuery("form.woocommerce-EditAccountForm").find('input[name=account_email]').prop('readonly', true);
						jQuery("form.woocommerce-EditAccountForm").find('input[name=account_email]').after('<span class="description">De lokale beheerder dient altijd gekoppeld te blijven aan de webshopmailbox, dus dit veld kun je niet bewerken.</span>');
					});
				</script>
				<?php
			}
		}

		?>
		<script type="text/javascript">
			jQuery(document).ready( function() {
				function hidePlaceholder( dateText, inst ) {
					// Placeholder onmiddellijk verwijderen
					jQuery(this).attr('placeholder', '');
					// Datum is sowieso geldig, verwijder de eventuele foutmelding
					jQuery('#datepicker_field').removeClass('woocommerce-invalid woocommerce-invalid-required-field');
				}

				jQuery("#datepicker").datepicker({
					dayNamesMin: [ "Zo", "Ma", "Di", "Wo", "Do", "Vr", "Za" ],
					monthNamesShort: [ "Jan", "Feb", "Maart", "April", "Mei", "Juni", "Juli", "Aug", "Sep", "Okt", "Nov", "Dec" ],
					changeMonth: true,
					changeYear: true,
					yearRange: "c-50:c+32",
					defaultDate: "-50y",
					maxDate: "-18y",
					onSelect: hidePlaceholder,
				});
			});
		</script>
		<?php
	}

	// Verhinder bepaalde selecties in de back-end
	add_action( 'admin_footer', 'disable_custom_checkboxes' );

	function disable_custom_checkboxes() {
		global $pagenow, $post_type;

		if ( $post_type === 'product' ) : ?>

			<script>
				jQuery(document).ready( function() {
					/* Disable bovenliggende categorie bij alle aangevinkte subcategorieën */
					jQuery('#taxonomy-product_cat').find('.categorychecklist').find('input[type=checkbox]:checked').closest('ul.children').siblings('label.selectit').find('input[type=checkbox]').prop( 'disabled', true );

					/* Deselecteer én disable/enable de bovenliggende categorie bij aan/afvinken van een subcategorie */
					jQuery('#taxonomy-product_cat').find('.categorychecklist').find('input[type=checkbox]').on( 'change', function() {
						jQuery(this).closest('ul.children').siblings('label.selectit').find('input[type=checkbox]').prop( 'checked', false );
						/* Enable enkel indien ALLE subcategorieën in een categorie afgevinkt zijn */
						if ( jQuery(this).closest('ul.children').find('input[type=checkbox]:checked').length == 0 ) {
							jQuery(this).closest('ul.children').siblings('label.selectit').find('input[type=checkbox]').prop( 'disabled', false );
						} else {
							jQuery(this).closest('ul.children').siblings('label.selectit').find('input[type=checkbox]').prop( 'disabled', true );
						}
					});

					/* Disable continenten */
					jQuery('#taxonomy-product_partner').find('.categorychecklist').children('li').children('label.selectit').find('input[type=checkbox]').prop( 'disabled', true );
					
					/* Disable bovenliggend land bij alle aangevinkte partners */
					jQuery('#taxonomy-product_partner').find('.categorychecklist').find('input[type=checkbox]:checked').closest('ul.children').siblings('label.selectit').find('input[type=checkbox]').prop( 'disabled', true );

					/* Deselecteer én disable/enable het bovenliggende land bij aan/afvinken van een partner */
					/* Exra .find('.children .children') zorgt ervoor dat de logica enkel op het 3de niveau werkt */
					jQuery('#taxonomy-product_partner').find('.categorychecklist').find('.children .children').find('input[type=checkbox]').on( 'change', function() {
						jQuery(this).closest('ul.children').siblings('label.selectit').find('input[type=checkbox]').prop( 'checked', false );
						/* Enable enkel indien ALLE partners in een land afgevinkt zijn */
						if ( jQuery(this).closest('ul.children').find('input[type=checkbox]:checked').length == 0 ) {
							jQuery(this).closest('ul.children').siblings('label.selectit').find('input[type=checkbox]').prop( 'disabled', false );
						} else {
							jQuery(this).closest('ul.children').siblings('label.selectit').find('input[type=checkbox]').prop( 'disabled', true );
						}
					});
				});
			</script>
			
			<?php if ( ! current_user_can('manage_options') ) : ?>
				<script>
					jQuery(document).ready( function() {
						/* Checks zowel doorlopen bij creatie als update */
						jQuery('#major-publishing-actions input[type=submit]').click( function() {
							var response = check_product_fields();
							if ( response[0] == false ) {
								alert( response[1] );
							}
							return response[0];
						});

						function check_product_fields() { 
							var pass = true;
							var msg = '';
							
							if ( jQuery('#titlediv').find('input[name=post_title]').val().length == 0 ) {
								msg += '* Je hebt nog geen omschrijving ingevuld!\n';
							}
							
							if ( jQuery('#general_product_data').find('input[name=_regular_price]').val().length == 0 ) {
								msg += '* Je moet nog een reguliere verkoopprijs ingeven!\n';
							}
							
							var sku = jQuery('#inventory_product_data').find('input[name=_sku]').val();
							if ( sku.length == 0 ) {
								msg += '* Je moet nog een artikelnummer ingeven!\n';
							} else if ( ! isNaN( parseFloat( sku ) ) && isFinite( sku ) ) {
								msg += '* Kies een niet-numeriek artikelnummer om conflicten met nationale producten te vermijden!\n';
							}

							if ( jQuery('#product_cat-all').find('input[type=checkbox]:checked').length == 0 ) {
								msg += '* Je moet nog een productcategorie aanvinken!\n';
							}

							if ( msg.length > 0 ) {
								pass = false;
								msg = 'Hold your horses, er zijn enkele issues:\n'+msg;
							}

							return [ pass, msg ];
						}
					});
				</script>
			<?php endif; ?>

		<?php elseif ( $post_type === 'shop_order' ) : ?>

			<script>
				jQuery(document).ready( function() {
					/* Disbable prijswijzigingen bij terugbetalingen */
					jQuery('#order_line_items').find('.refund_line_total.wc_input_price').prop( 'disabled', true );
					jQuery('#order_line_items').find('.refund_line_tax.wc_input_price').prop( 'disabled', true );
					jQuery('.wc-order-totals').find ('#refund_amount').prop( 'disabled', true );
					// jQuery('label[for=restock_refunded_items]').closest('tr').hide();
				});
			</script>

		<?php endif; ?>

		<?php
	}

	// Label en layout de factuurgegevens ENKEL GEBRUIKEN OM NON-CORE-FIELDS TE BEWERKEN OF VELDEN TE UNSETTEN
	add_filter( 'woocommerce_billing_fields', 'format_checkout_billing', 10, 1 );

	function format_checkout_billing( $address_fields ) {
		$address_fields['billing_email'] = array_merge(
			$address_fields['billing_email'],
			array(
				'label' => 'E-mailadres',
				'placeholder' => 'luc@gmail.com',
				'class' => array('form-row-first'),
				'clear' => false,
				'required' => true,
				'priority' => 12,
			)
		);

		$address_fields['billing_birthday'] = array(
			'id' => 'datepicker',
			'label' => 'Geboortedatum',
			'placeholder' => '16/03/1988',
			'class' => array('form-row-last'),
			'clear' => true,
			'required' => true,
			'priority' => 13,
		);

		if ( ! in_array( '', WC()->cart->get_cart_item_tax_classes() ) ) {
			// Als er geen producten à 21% BTW in het mandje zitten (= standaardtarief) wordt er geen alcohol gekocht en wordt het veld optioneel
			// Met het oog op toevoegen non-food wellicht beter af te handelen via verzendklasses (maar get_shipping_class() moet loopen over alle items ...)
			$address_fields['billing_birthday']['required'] = false;
		}
		
		if ( is_b2b_customer() ) {
			$address_fields['billing_vat'] = array(
				'label' => 'BTW-nummer',
				'placeholder' => 'BE 0453.066.016',
				'class' => array('form-row-last'),
				// Want verenigingen hebben niet noodzakelijk een BTW-nummer!
				'required' => false,
				'priority' => 21,
			);
		} else {
			unset( $address_fields['billing_company'] );
		}

		$address_fields['billing_phone'] = array_merge(
			$address_fields['billing_phone'],
			array(
				'label' => 'Telefoonnummer',
				'placeholder' => get_oxfam_shop_data('telephone'),
				'class' => array('form-row-last'),
				'clear' => true,
				'required' => true,
				'priority' => 31,
			)
		);
		
		// Verbergen indien reeds geabonneerd?
		$address_fields['digizine'] = array(
			'id' => 'digizine',
			'type' => 'checkbox',
			// <span> is nodig om lay-out van checkbox in overeenstemming te brengen met andere
			'label' => '<span>Abonneer mij op <a href="https://us3.campaign-archive.com/home/?u=d66c099224e521aa1d87da403&id=5cce3040aa" target="_blank">de maandelijkse nieuwsbrief van Oxfam-Wereldwinkels</a></span>',
			'class' => array('form-row-wide no-margin-bottom'),
			'label_class' => array('woocommerce-form__label woocommerce-form__label-for-checkbox'),
			'input_class' => array('woocommerce-form__input woocommerce-form__input-checkbox'),
			'clear' => true,
			'required' => false,
			'priority' => 101,
		);
		$address_fields['marketing'] = array(
			'id' => 'marketing',
			'type' => 'checkbox',
			// <span> is nodig om lay-out van checkbox in overeenstemming te brengen met andere
			'label' => '<span>Stuur mij commerciële mails (promoties, nieuwigheden, ...)</span>',
			'class' => array('form-row-wide no-margin-bottom'),
			'label_class' => array('woocommerce-form__label woocommerce-form__label-for-checkbox'),
			'input_class' => array('woocommerce-form__input woocommerce-form__input-checkbox'),
			'clear' => true,
			'required' => false,
			'priority' => 102,
		);

		return $address_fields;
	}

	// Label en layout de verzendgegevens ENKEL GEBRUIKEN OM NON-CORE-FIELDS TE BEWERKEN OF VELDEN TE UNSETTEN
	add_filter( 'woocommerce_shipping_fields', 'format_checkout_shipping', 10, 1 );
	
	function format_checkout_shipping( $address_fields ) {
		// Werkt blijkbaar niet meer?
		$address_fields['shipping_address_1']['class'] = array('form-row-wide');
		$address_fields['shipping_address_1']['clear'] = true;
		unset($address_fields['shipping_company']);
		return $address_fields;
	}

	// Verduidelijk de labels en layout van de basisvelden KOMT NA ALLE ANDERE ADRESFILTERS
	add_filter( 'woocommerce_default_address_fields', 'format_addresses_frontend', 100, 1 );

	function format_addresses_frontend( $address_fields ) {
		$address_fields['first_name'] = array_merge(
			$address_fields['first_name'],
			array(
				'label' => 'Voornaam',
				'placeholder' => 'Luc',
				'class' => array('form-row-first'),
				'clear' => false,
				'priority' => 10,
			)
		);

		$address_fields['last_name'] = array_merge(
			$address_fields['last_name'],
			array(
				'label' => 'Familienaam',
				'placeholder' => 'Willems',
				'class' => array('form-row-last'),
				'clear' => true,
				'priority' => 11,
			)
		);

		$address_fields['company'] = array_merge(
			$address_fields['company'],
			array(
				'label' => 'Bedrijf of vereniging',
				'placeholder' => 'Oxfam Fair Trade cvba',
				'class' => array('form-row-first'),
				'clear' => false,
				'required' => true,
				'priority' => 20,
			)
		);

		$address_fields['address_1'] = array_merge(
			$address_fields['address_1'],
			array(
				'label' => 'Straat en huisnummer',
				'placeholder' => 'Stationstraat 16',
				'class' => array('form-row-first'),
				'clear' => false,
				'required' => true,
				'priority' => 30,
			)
		);

		unset($address_fields['address_2']);

		$address_fields['postcode'] = array_merge(
			$address_fields['postcode'],
			array(
				'label' => 'Postcode',
				'placeholder' => get_oxfam_shop_data('zipcode'),
				// Zorg ervoor dat de totalen automatisch bijgewerkt worden na aanpassen van de postcode
				// Werkt enkel indien de voorgaande verplichte velden niet-leeg zijn, zie maybe_update_checkout() in woocommerce/assets/js/frontend/checkout.js 
				'class' => array('form-row-first update_totals_on_change'),
				'clear' => false,
				'required' => true,
				// Wordt door een andere plugin naar 65 geforceerd
				'priority' => 70,
			)
		);

		$address_fields['city'] = array_merge(
			$address_fields['city'],
			array(
				'label' => 'Gemeente',
				'placeholder' => get_oxfam_shop_data('city'),
				'class' => array('form-row-last'),
				'clear' => true,
				'required' => true,
				'priority' => 71,
			)
		);

		// Onzichtbaar gemaakt via CSS maar absoluut nodig voor service points!
		$address_fields['country']['priority'] = 100;

		return $address_fields;
	}

	// Vul andere placeholders in, naar gelang de gekozen verzendmethode op de winkelwagenpagina (wordt NIET geüpdatet bij verandering in checkout)
	add_filter( 'woocommerce_checkout_fields' , 'format_checkout_notes' );

	function format_checkout_notes( $fields ) {
		$fields['account']['account_username']['label'] = "Kies een gebruikersnaam:";
		$fields['account']['account_password']['label'] = "Kies een wachtwoord:";
		
		$shipping_methods = WC()->session->get('chosen_shipping_methods');
		$shipping_id = reset($shipping_methods);
		switch ( $shipping_id ) {
			case stristr( $shipping_id, 'local_pickup' ):
				$placeholder = __( 'Voorbeeldnotitie op afrekenpagina (indien afhaling).', 'oxfam-webshop' );
				break;
			default:
				$placeholder = __( 'Voorbeeldnotitie op afrekenpagina (indien thuislevering).', 'oxfam-webshop' );
				break;
		}
		$fields['order']['order_comments']['placeholder'] = $placeholder;
		// HTML wordt helaas niet uitgevoerd, dus link naar FAQ niet mogelijk!
		$fields['order']['order_comments']['description'] = sprintf( __( 'Boodschap onder de notities op de afrekenpagina, inclusief telefoonnummer van de hoofdwinkel (%s).', 'oxfam-webshop' ), get_oxfam_shop_data('telephone') );

		return $fields;
	}

	// Voeg tooltip toe achter het label van bepaalde velden
	add_filter( 'woocommerce_form_field_text', 'add_tooltips_after_woocommerce_label', 10, 4 );
	add_filter( 'woocommerce_form_field_tel', 'add_tooltips_after_woocommerce_label', 10, 4 );
	add_filter( 'woocommerce_form_field_checkbox', 'add_tooltips_after_woocommerce_label', 10, 4 );

	function add_tooltips_after_woocommerce_label( $field, $key, $args, $value ) {
		if ( $key === 'billing_birthday' ) {
			$field = str_replace( '</label>', '<a class="dashicons dashicons-editor-help tooltip" title="Omdat we ook alcohol verkopen zijn we verplicht om je leeftijd te controleren. We gebruiken deze info nooit voor andere doeleinden."></a></label>', $field );
		}

		if ( $key === 'billing_phone' ) {
			$field = str_replace( '</label>', '<a class="dashicons dashicons-editor-help tooltip" title="We bellen je enkel op indien dit nodig is voor een vlotte verwerking van je bestelling. We gebruiken het nummer nooit voor andere doeleinden."></a></label>', $field );
		}

		if ( $key === 'marketing' ) {
			$field = str_replace( '</span></label>', '</span><a class="dashicons dashicons-editor-help tooltip" title="We mailen je hooguit 1x per week. Je kunt je voorkeuren op elk ogenblik aanpassen."></a></label>', $field );
		}

		return $field;
	}

	// Acties om uit te voeren AAN BEGIN VAN ELKE POGING TOT CHECKOUT
	add_action( 'woocommerce_checkout_process', 'verify_min_max_age_postcode_vat' );

	function verify_min_max_age_postcode_vat() {
		// Stel een bestelminimum in
		$min = 10;
		if ( floatval( WC()->cart->get_total('edit') ) < $min ) {
			wc_add_notice( sprintf( __( 'Foutmelding bij te kleine bestellingen, inclusief minimumbedrag in euro (%d).', 'oxfam-webshop' ), $min ), 'error' );
		}
	}

	// Validaties om uit te voeren NA FORMATTERING DATA door 'woocommerce_process_checkout_field_...'-filters in get_posted_data()
	add_action( 'woocommerce_after_checkout_validation', 'do_age_housenumber_vat_validation', 10, 2 );

	function do_age_housenumber_vat_validation( $fields, $errors ) {
		// Check op het invullen van verplichte velden gebeurt reeds eerder door WooCommerce
		// Als er een waarde meegegeven wordt, checken we wel steeds of de klant meerderjarig is
		if ( ! empty( $fields['billing_birthday'] ) ) {
			if ( $fields['billing_birthday'] === '31/12/2100' ) {
				$errors->add( 'validation', __( 'Foutmelding na het invullen van slecht geformatteerde datum.', 'oxfam-webshop' ) );
			} else {
				// Opletten met de Amerikaanse interpretatie DD/MM/YYYY!
				if ( strtotime( str_replace( '/', '-', $fields['billing_birthday'] ) ) > strtotime('-18 years') ) {
					$errors->add( 'validation', __( 'Foutmelding na het invullen van een geboortedatum die minder dan 18 jaar in het verleden ligt.', 'oxfam-webshop' ) );
				}
			}
		}

		// Check of het huisnummer ingevuld is (behalve bij afhalingen)
		if ( isset( $fields['shipping_method'][0] ) and $fields['shipping_method'][0] !== 'local_pickup_plus' ) {
			if ( $fields['ship_to_different_address'] ) {
				// Er werd een afwijkend verzendadres ingevuld, check die waarde
				$key_to_check = 'shipping_address_1';
			} else {
				// Check enkel 'billing_address_1' want de gegevens die in 'shipping_address_1' doorgegeven worden zijn niet altijd up-to-date!
				// Indien je een wijziging doet aan 'billing_address_1' wordt dit pas na een page refresh gekopieerd naar 'shipping_address_1'
				$key_to_check = 'billing_address_1';
			}

			if ( ! empty( $fields[ $key_to_check ] ) ) {
				// Indien er echt geen huisnummer is, moet Z/N ingevuld worden
				if ( preg_match( '/([0-9]+|ZN)/i', $fields[ $key_to_check ] ) === 0 ) {
					$str = date_i18n('d/m/Y H:i:s')."\t\t".get_home_url()."\t\tHuisnummer ontbreekt in '".$fields[ $key_to_check ]."'\n";
					file_put_contents( "../housenumber_errors.csv", $str, FILE_APPEND );
					$errors->add( 'validation', __( 'Foutmelding na het invullen van een straatnaam zonder huisnummer.', 'oxfam-webshop' ) );
				}
			}
		}

		// Check of het BTW-nummer geldig is
		if ( ! empty( $fields['billing_vat'] ) ) {
			if ( strpos( format_tax( $fields['billing_vat'] ), 'INVALID' ) !== false ) {
				$errors->add( 'validation', __( 'Foutmelding na het ingeven van een ongeldig BTW-nummer.', 'oxfam-webshop' ) );
			}
		}
	}

	// Acties om uit te voeren NA SUCCESVOLLE CHECKOUT (order reeds aangemaakt) 
	add_action( 'woocommerce_checkout_update_order_meta', 'save_b2b_order_fields', 10, 2 );

	function save_b2b_order_fields( $order_id, $data ) {
		// write_log( print_r( $data, true ) );

		if ( $data['digizine'] === 1 or $data['marketing'] === 1 ) {
			$post_data = array(
				// Naam en e-mailadres zijn reeds geformatteerd!
				'fname' => $data['billing_first_name'],
				'lname' => $data['billing_last_name'],
				'email' => $data['billing_email'],
				'source' => 'webshop',
				'newsletter' => 'yes',
				'shop' => get_webshop_name(),
			);

			if ( $data['digizine'] === 1 ) {
				$post_data['digizine'] = 'yes';
				$str = date_i18n('d/m/Y H:i:s')."\t\t".$data['billing_email']."\t\tEnable marketing permission 496c25fb49\n";
				file_put_contents( dirname( ABSPATH, 1 )."/mailchimp_instructions.csv", $str, FILE_APPEND );
			} else {
				$post_data['digizine'] = 'no';
			}

			if ( $data['marketing'] === 1 ) {
				$post_data['marketing'] = 'yes';
				$str = date_i18n('d/m/Y H:i:s')."\t\t".$data['billing_email']."\t\tEnable marketing permission c1cbf23458\n";
				file_put_contents( dirname( ABSPATH, 1 )."/mailchimp_instructions.csv", $str, FILE_APPEND );
			}

			$settings = array(
				'timeout' => 10,
				'body' => $post_data,
			);
			// BIJ VOORKEUR ASYNCHROON DOEN ZODAT HET CHECKOUT NIET VERTRAAGT
			$response = wp_remote_post( 'https://www.oxfamwereldwinkels.be/wp-content/themes/oxfam/mailchimp/subscribe.php', $settings );
			$result = json_decode( wp_remote_retrieve_body( $response ) );
			file_put_contents( dirname( ABSPATH, 1 )."/mailchimp_instructions.csv", date_i18n('d/m/Y H:i:s')."\t\t".$data['billing_email']."\t\t".$result->status."\n", FILE_APPEND );
		}

		// Registreer of het een B2B-verkoop is of niet
		if ( is_b2b_customer() ) {
			$value = 'yes';
			// Extra velden met 'billing'-prefix worden automatisch opgeslagen (maar niet getoond), geen actie nodig
		} else {
			$value = 'no';
		}
		update_post_meta( $order_id, 'is_b2b_sale', $value );
	}

	// Wanneer het order BETAALD wordt, slaan we de geschatte leverdatum op
	add_action( 'woocommerce_order_status_pending_to_processing', 'save_estimated_delivery' );

	function save_estimated_delivery( $order_id ) {
		$order = wc_get_order($order_id);
		$shipping = $order->get_shipping_methods();
		$shipping = reset($shipping);

		if ( $order->get_meta('is_b2b_sale') !== 'yes' ) {
			$timestamp = estimate_delivery_date( $shipping['method_id'], $order_id );
			$order->add_meta_data( 'estimated_delivery', $timestamp, true );
			$order->save_meta_data();
		}
	}

	// Herschrijf bepaalde klantendata naar standaardformaten tijdens afrekenen én bijwerken vanaf accountpagina
	add_filter( 'woocommerce_process_checkout_field_billing_first_name', 'trim_and_uppercase', 10, 1 );
	add_filter( 'woocommerce_process_myaccount_field_billing_first_name', 'trim_and_uppercase', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_billing_last_name', 'trim_and_uppercase', 10, 1 );
	add_filter( 'woocommerce_process_myaccount_field_billing_last_name', 'trim_and_uppercase', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_billing_company', 'trim_and_uppercase', 10, 1 );
	add_filter( 'woocommerce_process_myaccount_field_billing_company', 'trim_and_uppercase', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_billing_vat', 'format_tax', 10, 1 );
	add_filter( 'woocommerce_process_myaccount_field_billing_vat', 'format_tax', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_billing_address_1', 'format_place', 10, 1 );
	add_filter( 'woocommerce_process_myaccount_field_billing_address_1', 'format_place', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_billing_postcode', 'format_zipcode', 10, 1 );
	add_filter( 'woocommerce_process_myaccount_field_billing_postcode', 'format_zipcode', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_billing_city', 'format_city', 10, 1 );
	add_filter( 'woocommerce_process_myaccount_field_billing_city', 'format_city', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_billing_phone', 'format_telephone', 10, 1 );
	add_filter( 'woocommerce_process_myaccount_field_billing_phone', 'format_telephone', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_billing_email', 'format_mail', 10, 1 );
	add_filter( 'woocommerce_process_myaccount_field_billing_email', 'format_mail', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_billing_birthday', 'format_date', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_shipping_first_name', 'trim_and_uppercase', 10, 1 );
	add_filter( 'woocommerce_process_myaccount_field_shipping_first_name', 'trim_and_uppercase', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_shipping_last_name', 'trim_and_uppercase', 10, 1 );
	add_filter( 'woocommerce_process_myaccount_field_shipping_last_name', 'trim_and_uppercase', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_shipping_address_1', 'format_place', 10, 1 );
	add_filter( 'woocommerce_process_myaccount_field_shipping_address_1', 'format_place', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_shipping_postcode', 'format_zipcode', 10, 1 );
	add_filter( 'woocommerce_process_myaccount_field_shipping_postcode', 'format_zipcode', 10, 1 );
	add_filter( 'woocommerce_process_checkout_field_shipping_city', 'format_city', 10, 1 );
	add_filter( 'woocommerce_process_myaccount_field_shipping_city', 'format_city', 10, 1 );
	
	function trim_and_uppercase( $value ) {
		return str_replace( 'Oww ', 'OWW ', implode( '.', array_map( 'ucwords', explode( '.', implode( '(', array_map( 'ucwords', explode( '(', implode( '-', array_map( 'ucwords', explode( '-', mb_strtolower( trim($value) ) ) ) ) ) ) ) ) ) ) );
	}

	function format_tax( $value ) {
		$value = str_replace( 'BE', '', $value );
		$value = preg_replace( '/[\s\-\.\/]/', '', $value );
		if ( mb_strlen($value) === 9 ) {
			$value = '0'.$value;
		}

		if ( mb_strlen($value) === 10 ) {
			$digit_8 = intval( substr( $value, 0, 8 ) );
			$checksum = 97 - ( $digit_8 - intval( $digit_8 / 97 ) * 97 );
			if ( $checksum === intval( substr( $value, 8, 2 ) ) ) {
				return 'BE '.substr( $value, 0, 4 ).".".substr( $value, 4, 3 ).".".substr( $value, 7, 3 );
			} else {
				return 'INVALID CHECKSUM';
			}
		} elseif ( mb_strlen($value) >= 1 ) {
			return 'INVALID LENGTH';
		} else {
			return '';
		}
	}

	function format_account( $iban ) {
		$countries = array( 'BE' => 16, 'NL' => 18 );
		$translate_chars = array( 
			'A' => 10,
			'B' => 11,
			'C' => 12,
			'D' => 13,
			'E' => 14,
			'F' => 15,
			'G' => 16,
			'H' => 17,
			'I' => 18,
			'J' => 19,
			'K' => 20,
			'L' => 21,
			'M' => 22,
			'N' => 23,
			'O' => 24,
			'P' => 25,
			'Q' => 26,
			'R' => 27,
			'S' => 28,
			'T' => 29,
			'U' => 30,
			'V' => 31,
			'W' => 32,
			'X' => 33,
			'Y' => 34,
			'Z' => 35,
		);

		$iban = str_replace( 'IBAN', '', mb_strtoupper($iban) );
		$iban = preg_replace( '/[\s\-\.\/]/', '', $iban );

		if ( array_key_exists( substr( $iban, 0, 2 ), $countries ) and strlen($iban) === $countries[substr( $iban, 0, 2 )] ) {
			$moved_char = substr( $iban, 4 ).substr( $iban, 0, 4 );
			$moved_char_array = str_split($moved_char);
			$controll_string = '';

			foreach ( $moved_char_array as $key => $value ) {
				if ( ! is_numeric($moved_char_array[$key]) ) {
					$moved_char_array[$key] = $translate_chars[$moved_char_array[$key]];
				}
				$controll_string .= $moved_char_array[$key];
			}

			if ( intval($controll_string) % 97 === 1 ) {
				return substr( $iban, 0, 4 )." ".substr( $iban, 4, 4 )." ".substr( $iban, 8, 4 )." ".substr( $iban, 12, 4 );
			} else {
				return 'INVALID CHECKSUM';
			}
		} else {
			return 'INVALID LENGTH';
		}
	}

	function format_place( $value ) {
		return trim_and_uppercase( $value );
	}

	function format_zipcode( $value ) {
		// Verwijder alle tekens die geen cijfer zijn
		return preg_replace( '/\D/', '', trim($value) );
	}

	function format_city( $value ) {
		return trim_and_uppercase( $value );
	}

	function format_mail( $value ) {
		return mb_strtolower( trim($value) );
	}

	function format_headquarter( $value ) {
		return trim_and_uppercase( $value );
	}
	
	// Sta een optionele parameter toe om puntjes te zetten in plaats van spaties (maar: wordt omgezet in streepjes door wc_format_phone() dus niet gebruiken in verkoop!)
	function format_telephone( $value, $delim = ' ' ) {
		if ( $delim === '.' ) {
			$slash = '/';
		} else {
			$slash = $delim;
		}
		// Wis alle spaties, leestekens en landcodes
		$temp_tel = preg_replace( '/[\s\-\.\/]/', '', $value );
		$temp_tel = str_replace( '+32', '0', $temp_tel );
		$temp_tel = preg_replace( '/(^|\s)0032/', '0', $temp_tel );
		
		// Formatteer vaste telefoonnummers
		if ( mb_strlen($temp_tel) === 9 ) {
			if ( intval($temp_tel[1]) === 2 or intval($temp_tel[1]) === 3 or intval($temp_tel[1]) === 4 or intval($temp_tel[1]) === 9 ) {
				$value = substr( $temp_tel, 0, 2 ) . $slash . substr( $temp_tel, 2, 3 ) . $delim . substr( $temp_tel, 5, 2 ) . $delim . substr( $temp_tel, 7, 2 );
			} else {
				$value = substr( $temp_tel, 0, 3 ) . $slash . substr( $temp_tel, 3, 2 ) . $delim . substr( $temp_tel, 5, 2 ) . $delim . substr( $temp_tel, 7, 2 );
			}
		}

		// Formatteer mobiele telefoonnummers
		if ( mb_strlen($temp_tel) === 10 ) {
			$value = substr($temp_tel, 0, 4) . $slash . substr($temp_tel, 4, 2) . $delim . substr($temp_tel, 6, 2) . $delim . substr($temp_tel, 8, 2);
		}
		
		return $value;
	}

	function format_hour( $value ) {
		if ( strlen($value) === 5 ) {
			// Wordpress: geen wijzigingen meer nodig!
			return $value;
		} elseif ( strlen($value) === 4 ) {
			// Drupal: voeg dubbele punt toe in het midden
			return substr( $value, 0, 2 ) . ':' . substr( $value, 2, 2 );
		} else {
			// Drupal: voeg nul toe vooraan bij ochtenduren
			return '0'.substr( $value, 0, 1 ) . ':' . substr( $value, 1, 2 );
		}
	}

	function format_date( $value ) {
		$new_value = preg_replace( '/[\s\-\.\/]/', '', $value );
		if ( strlen($new_value) === 8 ) {
			return substr( $new_value, 0, 2 ) . '/' . substr( $new_value, 2, 2 ) . '/' . substr( $new_value, 4, 4 );
		} elseif ( strlen($new_value) === 0 ) {
			// Ontbrekende datum
			return '';
		} else {
			// Ongeldige datum (dit laat ons toe om het onderscheid te maken!)
			return '31/12/2100';
		}
	}

	// Voeg de bestel-Excel toe aan de adminmail 'nieuwe bestelling'
	add_filter( 'woocommerce_email_attachments', 'attach_picklist_to_email', 10, 3 );

	function attach_picklist_to_email( $attachments, $status , $order ) {
		// Excel altijd bijwerken wanneer de mail opnieuw verstuurd wordt, ook bij refunds
		$create_statuses = array( 'new_order', 'customer_refunded_order' );
		
		if ( isset( $status ) and in_array( $status, $create_statuses ) ) {

			// Sla de besteldatum op
			$order_number = $order->get_order_number();
			// LEVERT UTC-TIMESTAMP OP, DUS VERGELIJKEN MET GLOBALE TIME() 
			$order_timestamp = $order->get_date_created()->getTimestamp();
			
			// Laad PhpSpreadsheet en het bestelsjabloon in, en selecteer het eerste werkblad
			require_once WP_PLUGIN_DIR.'/phpspreadsheet/autoload.php';
			$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();

			if ( get_option('oxfam_remove_excel_header') === 'yes' ) {
				// Neem de versie zonder afbeelding, anders wordt de file corrupt na het wissen van de koprijen
				$spreadsheet = $reader->load( get_stylesheet_directory().'/picklist-no-logo.xlsx' );
			} else {
				$spreadsheet = $reader->load( get_stylesheet_directory().'/picklist.xlsx' );
			}

			$spreadsheet->setActiveSheetIndex(0);
			$pick_sheet = $spreadsheet->getActiveSheet();

			// Sla de levermethode op
			$shipping_methods = $order->get_shipping_methods();
			$shipping_method = reset( $shipping_methods );
			
			$i = 1;
			if ( get_option('oxfam_remove_excel_header') !== 'yes' ) {
				// Bestelgegevens invullen
				$pick_sheet->setTitle( $order_number )->setCellValue( 'F2', $order_number )->setCellValue( 'F3', PHPExcel_Shared_Date::PHPToExcel( $order_timestamp ) );
				$pick_sheet->getStyle( 'F3' )->getNumberFormat()->setFormatCode( PHPExcel_Style_NumberFormat::FORMAT_DATE_DMYSLASH );

				// Factuuradres invullen
				$pick_sheet->setCellValue( 'A2', $order->get_billing_phone() )->setCellValue( 'B1', $order->get_billing_first_name().' '.$order->get_billing_last_name() )->setCellValue( 'B2', $order->get_billing_address_1() )->setCellValue( 'B3', $order->get_billing_postcode().' '.$order->get_billing_city() );

				// Logistieke gegevens invullen
				$logistics = get_logistic_params( $order );
				$pick_sheet->setCellValue( 'A5', number_format( $logistics['volume'], 1, ',', '.' ).' liter / '.number_format( $logistics['weight'], 1, ',', '.' ).' kg' )->setCellValue( 'A6', 'max. '.$logistics['maximum'].' cm' );

				// Begin pas met items vanaf rij 8
				$i = 8;
			}

			// Vul de artikeldata item per item in
			foreach ( $order->get_items() as $order_item_id => $item ) {
				$product = $order->get_product_from_item( $item );
				switch ( $product->get_tax_class() ) {
					case 'voeding':
						$tax = '0.06';
						break;
					case 'vrijgesteld':
						$tax = '0.00';
						break;
					default:
						$tax = '0.21';
						break;
				}
				$product_price = $product->get_price();
				$line_total = $item['line_subtotal'];

				if ( $order->get_meta('is_b2b_sale') === 'yes' ) {
					// Stukprijs exclusief BTW bij B2B-bestellingen
					$product_price /= 1+$tax;
				} else {
					// BTW erbij tellen bij particulieren
					$line_total += $item['line_subtotal_tax'];
				}
				$pick_sheet->setCellValue( 'A'.$i, $product->get_meta('_shopplus_code') )->setCellValue( 'B'.$i, $product->get_title() )->setCellValue( 'C'.$i, $item['qty'] )->setCellValue( 'D'.$i, $product_price )->setCellValue( 'E'.$i, $tax )->setCellValue( 'F'.$i, $line_total )->setCellValue( 'H'.$i, $product->get_attribute('ean') );
				$i++;
			}

			if ( get_option('oxfam_remove_excel_header') !== 'yes' ) {
				$pickup_text = 'Afhaling in winkel';
				// Deze $order->get_meta() is hier reeds beschikbaar!
				if ( $order->get_meta('is_b2b_sale') === 'yes' ) {
					// Switch suffix naar 'excl. BTW'
					$label = $pick_sheet->getCell('D5')->getValue();
					$pick_sheet->setCellValue( 'D5', str_replace( 'incl', 'excl', $label ) );
				} else {
					// Haal geschatte leverdatum op VIA GET_POST_META() WANT $ORDER->GET_META() OP DIT MOMENT NOG NIET BEPAALD
					$delivery_timestamp = get_post_meta( $order->get_id(), 'estimated_delivery', true );
					$pickup_text .= ' vanaf '.date_i18n( 'j/n/y \o\m H:i', $delivery_timestamp );
				} 

				switch ( $shipping_method['method_id'] ) {
					case stristr( $shipping_method['method_id'], 'flat_rate' ):
						
						// Leveradres invullen (is in principe zeker beschikbaar!)
						$pick_sheet->setCellValue( 'B4', $order->get_shipping_first_name().' '.$order->get_shipping_last_name() )->setCellValue( 'B5', $order->get_shipping_address_1() )->setCellValue( 'B6', $order->get_shipping_postcode().' '.$order->get_shipping_city() )->setCellValue( 'D1', mb_strtoupper( get_webshop_name(true) ) );

						// Verzendkosten vermelden
						foreach ( $order->get_items('shipping') as $order_item_id => $shipping ) {
							$total_tax = floatval( $shipping->get_total_tax() );
							$total_excl_tax = floatval( $shipping->get_total() );
							// Enkel printen indien nodig
							if ( $total_tax > 0.01 ) {
								if ( $shipping->get_tax_class() === 'voeding' ) {
									$tax = 0.06;
								} else {
									$tax = 0.21;
								}
								$pick_sheet->setCellValue( 'A'.$i, 'WEB'.intval(100*$tax) )->setCellValue( 'B'.$i, 'Thuislevering' )->setCellValue( 'C'.$i, 1 )->setCellValue( 'D'.$i, $total_excl_tax+$total_tax )->setCellValue( 'E'.$i, $tax )->setCellValue( 'F'.$i, $total_excl_tax+$total_tax );
							}
						}
						break;

					case stristr( $shipping_method['method_id'], 'free_shipping' ):
					// KAN IN DE TOEKOMST OOK BETALEND ZIJN
					case stristr( $shipping_method['method_id'], 'b2b_home_delivery' ):

						// Leveradres invullen (is in principe zeker beschikbaar!)
						$pick_sheet->setCellValue( 'B4', $order->get_shipping_first_name().' '.$order->get_shipping_last_name() )->setCellValue( 'B5', $order->get_shipping_address_1() )->setCellValue( 'B6', $order->get_shipping_postcode().' '.$order->get_shipping_city() )->setCellValue( 'D1', mb_strtoupper( get_webshop_name(true) ) );
						break;

					case stristr( $shipping_method['method_id'], 'service_point_shipping_method' ):

						// Verwijzen naar postpunt
						$service_point = $order->get_meta('sendcloudshipping_service_point_meta');
						$service_point_info = explode ( '|', $service_point['extra'] );
						$pick_sheet->setCellValue( 'B4', 'Postpunt '.$service_point_info[0] )->setCellValue( 'B5', $service_point_info[1].', '.$service_point_info[2] )->setCellValue( 'B6', 'Etiket verplicht aan te maken via SendCloud!' )->setCellValue( 'D1', mb_strtoupper( get_webshop_name(true) ) );

						// Verzendkosten vermelden
						foreach ( $order->get_items('shipping') as $order_item_id => $shipping ) {
							$total_tax = floatval( $shipping->get_total_tax() );
							$total_excl_tax = floatval( $shipping->get_total() );
							// Enkel printen indien nodig
							if ( $total_tax > 0.01 ) {
								// TE VERALGEMENEN MAAR WERKT OOK BIJ VERZENDKOST VAN 4,95 EURO
								if ( $total_tax < 1.00 ) {
									$tax = 0.06;
								} else {
									$tax = 0.21;
								}
								$pick_sheet->setCellValue( 'A'.$i, 'WEB'.intval(100*$tax) )->setCellValue( 'B'.$i, 'Thuislevering' )->setCellValue( 'C'.$i, 1 )->setCellValue( 'D'.$i, $total_excl_tax+$total_tax )->setCellValue( 'E'.$i, $tax )->setCellValue( 'F'.$i, $total_excl_tax+$total_tax );
							}
						}
						break;

					default:
						$meta_data = $shipping_method->get_meta_data();
						$pickup_data = reset( $meta_data );
						// OF NIEUWE MANIER?
						// $pickup_location = $method->get_meta('pickup_location');
						// $pickup_location['shipping_company']
						$pick_sheet->setCellValue( 'B4', $pickup_text )->setCellValue( 'D1', mb_strtoupper( trim( str_replace( 'Oxfam-Wereldwinkel', '', $pickup_data->value['shipping_company'] ) ) ) );
				}

				// Vermeld de totale korting (inclusief/exclusief BTW)
				// Kortingsbedrag per coupon apart vermelden is lastig: https://stackoverflow.com/questions/44977174/get-coupon-discount-type-and-amount-in-woocommerce-orders
				$used_coupons = $order->get_coupon_codes();
				if ( count( $used_coupons ) >= 1 ) {
					$discount = $order->get_discount_total();
					if ( $order->get_meta('is_b2b_sale') !== 'yes' ) {
						$discount += $order->get_discount_tax();
					}
					$i++;
					$pick_sheet->setCellValue( 'A'.$i, 'Kortingen' )->setCellValue( 'B'.$i, mb_strtoupper( implode( ', ', $used_coupons ) ) )->setCellValue( 'F'.$i, '-'.$discount );
					$i++;
				}

				// Druk eventuele opmerkingen af
				if ( strlen( $order->get_customer_note() ) > 5 ) {
					$i++;
					$customer_text = $order->get_customer_note();
					$pick_sheet->setCellValue( 'A'.$i, 'Opmerking' )->setCellValue( 'B'.$i, $customer_text );
					$pick_sheet->getStyle('A'.$i)->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_TOP);
					$pick_sheet->getStyle('B'.$i)->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
					// Merge resterende kolommen en wrap tekst in opmerkingenvak 
					$pick_sheet->mergeCells('B'.$i.':G'.$i);
					$pick_sheet->getStyle('B'.$i)->getAlignment()->setWrapText(true);

					// setRowHeight(-1) voor autoheight werkt niet, dus probeer goeie hoogte te berekenen bij lange teksten
					// if ( strlen( $customer_text ) > 125 ) {
					// 	$row_padding = 4;
					// 	$row_height = $pick_sheet->getRowDimension($i)->getRowHeight() - $row_padding;
					// 	$pick_sheet->getRowDimension($i)->setRowHeight( ceil( strlen( $customer_text ) / 120 ) * $row_height + $row_padding );
					// }

					// Bovenstaande houdt geen rekening met line breaks, dus gebruik voorlopig vaste (ruime) hoogte
					$pick_sheet->getRowDimension($i)->setRowHeight(80);
					$i++;
				}

				// Bereken en selecteer het totaalbedrag
				$pick_sheet->setSelectedCell('F5')->setCellValue( 'F5', $pick_sheet->getCell('F5')->getCalculatedValue() );
			}

			// Check of we een nieuwe file maken of een bestaande overschrijven
			$filename = $order->get_meta('_excel_file_name');
			if ( strlen( $filename ) < 10 ) {
				$folder = generate_pseudo_random_string();
				mkdir( WP_CONTENT_DIR.'/uploads/xlsx/'.$folder, 0755 );
				$filename = $folder.'/'.$order_number.'.xlsx';
			}

			try {
				// Bewaar de nieuwe file (Excel 2007+)
				$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet );
				$writer->save( WP_CONTENT_DIR.'/uploads/xlsx/'.$filename );
				
				// Bewaar de locatie van de file (random file!) als metadata
				$order->add_meta_data( '_excel_file_name', $filename, true );
				$order->save_meta_data();

				// Bijlage enkel meesturen in 'new_order'-mail aan admin
				if ( $status === 'new_order' ) {
					$attachments[] = WP_CONTENT_DIR.'/uploads/xlsx/'.$filename;
				}
			} catch ( InvalidArgumentException $e ) {
				$logger = wc_get_logger();
				$context = array( 'source' => 'PhpSpreadsheet' );
				$logger->error( $order_number.": ".$e->getMessage(), $context );
			}
		}

		return $attachments;
	}

	// Verduidelijk de profiellabels in de back-end	
	add_filter( 'woocommerce_customer_meta_fields', 'modify_user_admin_fields', 10, 1 );

	function modify_user_admin_fields( $profile_fields ) {
		if ( ! is_b2b_customer() ) {
			$billing_title = 'Klantgegevens';
		} else {
			$billing_title = 'Factuurgegevens';
		}
		$profile_fields['billing']['title'] = $billing_title;
		$profile_fields['billing']['fields']['billing_company']['label'] = 'Bedrijf of vereniging';
		// Klasse slaat op tekstveld, niet op de hele rij
		$profile_fields['billing']['fields']['billing_company']['class'] = 'show-if-b2b-checked important-b2b-field';
		$profile_fields['billing']['fields']['billing_vat']['label'] = 'BTW-nummer';
		$profile_fields['billing']['fields']['billing_vat']['description'] = 'Geldig Belgisch ondernemingsnummer van 9 of 10 cijfers (optioneel).';
		$profile_fields['billing']['fields']['billing_vat']['class'] = 'show-if-b2b-checked important-b2b-field';
		$profile_fields['billing']['fields']['billing_first_name']['label'] = 'Voornaam';
		$profile_fields['billing']['fields']['billing_last_name']['label'] = 'Familienaam';
		$profile_fields['billing']['fields']['billing_email']['label'] = 'Bestelcommunicatie naar';
		$profile_fields['billing']['fields']['billing_email']['description'] = 'E-mailadres waarop de klant zijn/haar bevestigingsmails ontvangt.';
		$profile_fields['billing']['fields']['billing_phone']['label'] = 'Telefoonnummer';
		$profile_fields['billing']['fields']['billing_address_1']['label'] = 'Straat en huisnummer';
		$profile_fields['billing']['fields']['billing_postcode']['label'] = 'Postcode';
		$profile_fields['billing']['fields']['billing_postcode']['maxlength'] = 4;
		$profile_fields['billing']['fields']['billing_city']['label'] = 'Gemeente';
		unset( $profile_fields['billing']['fields']['billing_address_2'] );
		unset( $profile_fields['billing']['fields']['billing_state'] );
		
		$profile_fields['shipping']['title'] = 'Verzendgegevens';
		$profile_fields['shipping']['fields']['shipping_first_name']['label'] = 'Voornaam';
		$profile_fields['shipping']['fields']['shipping_last_name']['label'] = 'Familienaam';
		$profile_fields['shipping']['fields']['shipping_address_1']['label'] = 'Straat en huisnummer';
		$profile_fields['shipping']['fields']['shipping_postcode']['label'] = 'Postcode';
		$profile_fields['shipping']['fields']['shipping_city']['label'] = 'Gemeente';
		unset( $profile_fields['shipping']['fields']['shipping_address_2'] );
		unset( $profile_fields['shipping']['fields']['shipping_company'] );
		unset( $profile_fields['shipping']['fields']['shipping_state'] );

		$profile_fields['shipping']['fields'] = array_swap_assoc( 'shipping_city', 'shipping_postcode', $profile_fields['shipping']['fields'] );

		$billing_field_order = array(
			'billing_company',
			'billing_vat',
			'billing_first_name',
			'billing_last_name',
			'billing_email',
			'billing_phone',
			'billing_address_1',
			'billing_postcode',
			'billing_city',
			'billing_country',
		);

		foreach ( $billing_field_order as $field ) {
			$ordered_billing_fields[$field] = $profile_fields['billing']['fields'][$field];
		}

		$profile_fields['billing']['fields'] = $ordered_billing_fields;
		return $profile_fields;
	}

	// Verberg bepaalde profielvelden (en niet verwijderen, want dat reset sommige waardes!)
	add_action( 'admin_footer-profile.php', 'hide_own_profile_fields' );
	add_action( 'admin_footer-user-edit.php', 'hide_others_profile_fields' );
	
	function hide_own_profile_fields() {
		if ( ! current_user_can('manage_options') ) {
			?>
			<script type="text/javascript">
				jQuery("tr.user-rich-editing-wrap").css( 'display', 'none' );
				jQuery("tr.user-comment-shortcuts-wrap").css( 'display', 'none' );
				jQuery("tr.user-language-wrap").css( 'display', 'none' );
				/* Zeker niét verwijderen -> breekt opslaan van pagina! */
				jQuery("tr.user-nickname-wrap").css( 'display', 'none' );
				jQuery("tr.user-url-wrap").css( 'display', 'none' );
				jQuery("h2:contains('Over jezelf')").next('.form-table').css( 'display', 'none' );
				jQuery("h2:contains('Over jezelf')").css( 'display', 'none' );
				jQuery("h2:contains('Over de gebruiker')").next('.form-table').css( 'display', 'none' );
				jQuery("h2:contains('Over de gebruiker')").css( 'display', 'none' );
				/* Wordt enkel toegevoegd indien toegelaten dus hoeft niet verborgen te worden */
				// jQuery("tr[class$='member_of_shop-wrap']").css( 'display', 'none' );
			</script>
			<?php
		}
		
		$current_user = wp_get_current_user();
		$user_meta = get_userdata($current_user->ID);
		$user_roles = $user_meta->roles;
		if ( in_array( 'local_manager', $user_roles ) and $current_user->user_email === get_webshop_email() ) {
			?>
			<script type="text/javascript">
				/* Verhinder dat lokale webbeheerders het e-mailadres aanpassen van hun hoofdaccount */
				jQuery("tr.user-email-wrap").find('input[type=email]').prop('readonly', true);
				jQuery("tr.user-email-wrap").find('input[type=email]').after('<span class="description">&nbsp;De lokale beheerder dient altijd gekoppeld te blijven aan de webshopmailbox, dus dit veld kun je niet bewerken.</span>');
			</script>
			<?php
		}
	}

	function hide_others_profile_fields() {
		if ( ! current_user_can('manage_options') ) {
		?>
			<script type="text/javascript">
				jQuery("tr.user-rich-editing-wrap").css( 'display', 'none' );
				jQuery("tr.user-admin-color-wrap").css( 'display', 'none' );
				jQuery("tr.user-comment-shortcuts-wrap").css( 'display', 'none' );
				jQuery("tr.user-admin-bar-front-wrap").css( 'display', 'none' );
				jQuery("tr.user-language-wrap").css( 'display', 'none' );
				/* Zeker niét verwijderen -> breekt opslaan van pagina! */
				jQuery("tr.user-nickname-wrap").css( 'display', 'none' );
				jQuery("tr.user-url-wrap").css( 'display', 'none' );
				jQuery("h2:contains('Over de gebruiker')").next('.form-table').css( 'display', 'none' );
				jQuery("h2:contains('Over de gebruiker')").css( 'display', 'none' );
			</script>
		<?php
		}
	}



	################
	# B2B FUNCTIES #
	################

	// Nooit e-mailconfirmatie versturen bij aanmaken nieuwe account
	add_action( 'user_new_form', 'check_disable_confirm_new_user' );
	
	function check_disable_confirm_new_user() {
		?>
		<script type="text/javascript">
			jQuery(document).ready(function() {
				jQuery("#noconfirmation").prop( 'checked', true );
				jQuery("#noconfirmation").parents('tr').hide();
			} );
		</script>
		<?php
	}

	// Algemene functie die retourneert of de gebruiker een B2B-klant is van de huidige webshop
	function is_b2b_customer( $user_id = false ) {
		if ( intval($user_id) < 1 ) {
			// Val terug op de momenteel ingelogde gebruiker
			$current_user = wp_get_current_user();
			$user_id = $current_user->ID;
			
			if ( is_admin() ) {
				// Extra checks op speciale gevallen in de back-end
				if ( isset($_GET['user_id']) ) {
					// Zijn we het profiel van iemand anders aan het bekijken?
					$user_id = $_GET['user_id'];
				} elseif ( isset($_POST['user_id']) ) {
					// Zijn we het profiel van iemand anders aan het updaten?
					$user_id = $_POST['user_id'];
				}
			}
		}
		if ( get_user_meta( intval($user_id), 'blog_'.get_current_blog_id().'_is_b2b_customer', true ) === 'yes' ) {
			return true;
		} else {
			return false;
		}
	}

	// Toon de 'is_b2b_customer'-checkbox in de back-end
	add_action( 'show_user_profile', 'add_b2b_customer_fields' );
	add_action( 'edit_user_profile', 'add_b2b_customer_fields' );
	// Zorg ervoor dat het ook geformatteerd / bewaard wordt (inhaken vòòr 'save_customer_meta_fields'-actie van WooCommerce met default priority)
	add_action( 'personal_options_update', 'save_b2b_customer_fields', 5 );
	add_action( 'edit_user_profile_update', 'save_b2b_customer_fields', 5 );

	function add_b2b_customer_fields( $user ) {
		$check_key = 'blog_'.get_current_blog_id().'_is_b2b_customer';
		$is_b2b_customer = get_the_author_meta( $check_key, $user->ID );
		$select_key = 'blog_'.get_current_blog_id().'_has_b2b_coupon';
		$has_b2b_coupon = get_the_author_meta( $select_key, $user->ID );
		?>
		<h3>B2B-verkoop</h3>
		<table class="form-table">
			<tr>
				<th><label for="<?php echo $check_key; ?>">Geverifieerde bedrijfsklant</label></th>
				<td>
					<input type="checkbox" class="important-b2b-field" name="<?php echo $check_key; ?>" id="<?php echo $check_key; ?>" value="yes" <?php checked( $is_b2b_customer, 'yes' ); ?> />
					<span class="description">Indien aangevinkt moet (en kan) de klant niet op voorhand online betalen. Je maakt zelf een factuur op met de effectief geleverde goederen en volgt achteraf de betaling op. <a href="https://github.com/OxfamFairTrade/ob2c/wiki/8.-B2B-verkoop" target="_blank">Raadpleeg de handleiding.</a></span>
				</td>
			</tr>
			<tr class="show-if-b2b-checked">
				<th><label for="<?php echo $select_key; ?>">Kortingspercentage</label></th>
				<td>
					<select class="important-b2b-field" name="<?php echo $select_key; ?>" id="<?php echo $select_key; ?>">;
					<?php	
						$b2b_payment_method = array('cod');
						$args = array(
							'posts_per_page' => -1,
							'post_type' => 'shop_coupon',
							'post_status' => 'publish',
							'meta_key' => 'coupon_amount',
							'orderby' => 'meta_value_num',
							'order' => 'ASC',
							'meta_query' => array(
								array(
									'key' => '_wjecf_payment_methods',
									'value' => serialize($b2b_payment_method),
									'compare' => 'LIKE',
								)
							),
						);

						$b2b_coupons = get_posts($args);
						echo '<option value="">Geen</option>';
						foreach ( $b2b_coupons as $b2b_coupon ) {
							echo '<option value="'.$b2b_coupon->ID.'" '.selected( $b2b_coupon->ID, $has_b2b_coupon ).'>'.number_format( $b2b_coupon->coupon_amount, 1, ',', '.' ).'%</option>';
						}
					?>
					</select>
					<span class="description">Pas automatisch deze korting toe op het volledige winkelmandje (met uitzondering van leeggoed).</span>
				</td>
			</tr>
			<tr class="show-if-b2b-checked">
				<th><label for="send_invitation">Uitnodiging</label></th>
				<td>
					<?php
						$disabled = '';
						if ( strlen( get_the_author_meta( 'billing_company', $user->ID ) ) < 2 ) {
							$disabled = ' disabled';
						}
						echo '<button type="button" class="button disable-on-b2b-change" id="send_invitation" style="min-width: 600px;"'.$disabled.'>Verstuur welkomstmail naar accounteigenaar</button>';
						echo '<p class="send_invitation description">';
						if ( ! empty( get_the_author_meta( 'blog_'.get_current_blog_id().'_b2b_invitation_sent', $user->ID ) ) ) {
							printf( 'Laatste uitnodiging verstuurd: %s.', date( 'd-n-Y H:i:s', strtotime( get_the_author_meta( 'blog_'.get_current_blog_id().'_b2b_invitation_sent', $user->ID ) ) ) );
						}
						echo '</p>';
					?>
					
					<script type="text/javascript">
						jQuery(document).ready(function() {
							if ( ! jQuery('#<?php echo $check_key; ?>').is(':checked') ) {
								jQuery('.show-if-b2b-checked').closest('tr').hide();
							}

							jQuery('#<?php echo $check_key; ?>').on( 'change', function() {
								jQuery('.show-if-b2b-checked').closest('tr').toggle();
							});

							jQuery('.important-b2b-field').on( 'change', function() {
								disableInvitation();
							});

							function disableInvitation() {
								jQuery('.disable-on-b2b-change').text("Klik op 'Gebruiker bijwerken' vooraleer je de uitnodiging verstuurt").prop( 'disabled', true );
							}

							jQuery('button#send_invitation').on( 'click', function() {
								if ( confirm("Weet je zeker dat je dit wil doen?") ) {
									jQuery(this).prop( 'disabled', true ).text( 'Aan het verwerken ...' );
									sendB2bWelcome( <?php echo $user->ID; ?> );
								}
							});

							function sendB2bWelcome( customer_id ) {
								var input = {
									'action': 'oxfam_invitation_action',
									'customer_id': customer_id,
								};
								
								jQuery.ajax({
									type: 'POST',
									url: ajaxurl,
									data: input,
									dataType: 'html',
									success: function( msg ) {
										jQuery( 'button#send_invitation' ).text( msg );
										var today = new Date();
										jQuery( 'p.send_invitation.description' ).html( 'Laatste actie ondernomen: '+today.toLocaleString('nl-NL')+'.' );
									},
									error: function( jqXHR, statusText, errorThrown ) {
										jQuery( 'button#send_invitation' ).text( 'Asynchroon laden van PHP-file mislukt!' );
										jQuery( 'p.send_invitation.description' ).html( 'Herlaad de pagina en probeer het eens opnieuw.' );
									},
								});
							}
						});
					</script>
				</td>
			</tr>
		</table>
		<?php
	}

	function save_b2b_customer_fields( $user_id ) {
		if ( ! current_user_can( 'edit_users', $user_id ) ) {
			return false;
		}

		$names = array( 'billing_company', 'billing_first_name', 'billing_last_name', 'billing_address_1', 'billing_city', 'shipping_first_name', 'shipping_last_name', 'shipping_address_1', 'shipping_city' );
		foreach ( $names as $name ) {
			if ( isset($_POST[$name]) ) {
				$_POST[$name] = trim_and_uppercase($_POST[$name]);
			}
		}

		if ( isset($_POST['billing_email']) ) {
			// Retourneert false indien ongeldig e-mailformaat
			$_POST['billing_email'] = is_email( format_mail($_POST['billing_email']) );
		}
		if ( isset($_POST['billing_phone']) ) {
			$_POST['billing_phone'] = format_telephone($_POST['billing_phone']);
		}
		if ( isset($_POST['billing_vat']) ) {
			$_POST['billing_vat'] = format_tax($_POST['billing_vat']);
		}
		if ( isset($_POST['billing_postcode']) ) {
			$_POST['billing_postcode'] = format_zipcode($_POST['billing_postcode']);
		}
		if ( isset($_POST['shipping_postcode']) ) {
			$_POST['shipping_postcode'] = format_zipcode($_POST['shipping_postcode']);
		}

		// Usermeta is netwerkbreed, dus ID van blog toevoegen aan de key!
		$check_key = 'blog_'.get_current_blog_id().'_is_b2b_customer';
		// Check of het veld wel bestaat voor deze gebruiker
		if ( isset($_POST[$check_key]) ) {
			update_user_meta( $user_id, $check_key, $_POST[$check_key] );
		} else {
			update_user_meta( $user_id, $check_key, 'no' );
			// 'billing_company' en 'billing_vat' laten we gewoon staan, niet expliciet ledigen!
		}
		
		// Voeg de ID van de klant toe aan de overeenstemmende kortingsbon, op voorwaarde dat B2B aangevinkt is
		$select_key = 'blog_'.get_current_blog_id().'_has_b2b_coupon';
		if ( get_user_meta( $user_id, $check_key, true ) !== 'yes' ) {
			// Ledig het eventueel geselecteerde kortingstarief
			$_POST[$select_key] = '';
		}

		if ( isset($_POST[$select_key]) ) {
			$new_coupon_id = intval( $_POST[$select_key] );
			$previous_coupon_id = intval( get_user_meta( $user_id, $select_key, true ) );

			if ( $new_coupon_id !== $previous_coupon_id ) {
				// Haal de rechthebbenden op van de vroegere coupon
				$previous_users_string = trim( get_post_meta( $previous_coupon_id, '_wjecf_customer_ids', true ) );
				if ( strlen( $previous_users_string ) > 0 ) {
					$previous_users = explode( ',', $previous_users_string );	
				} else {
					// Want anders retourneert explode() een leeg element
					$previous_users = array();
				}

				// Verwijder de user-ID van de vorige coupon, tenzij het user-ID 1 is (= admin)
				if ( $user_id !== 1 and ( $match_key = array_search( $user_id, $previous_users ) ) !== false ) {
					unset($previous_users[$match_key]);
				}
				update_post_meta( $previous_coupon_id, '_wjecf_customer_ids', implode( ',', $previous_users ) );

				// Haal de huidige rechthebbenden op van de nu geselecteerde coupon
				$current_users_string = trim( get_post_meta( $new_coupon_id, '_wjecf_customer_ids', true ) );
				if ( strlen( $current_users_string ) > 0 ) {
					$current_users = explode( ',', $current_users_string );	
				} else {
					// Want anders retourneert explode() een leeg element
					$current_users = array();
				}

				// Koppel de coupon altijd aan user-ID 1 om te vermijden dat de restricties wegvallen indien er geen enkele échte klant aan gekoppeld is!
				if ( ! in_array( 1, $current_users ) ) {
					$current_users[] = 1;
				}
				// Voeg de user-ID toe aan de geselecteerde coupon
				if ( ! in_array( $user_id, $current_users ) ) {
					$current_users[] = $user_id;
				}
				update_post_meta( $new_coupon_id, '_wjecf_customer_ids', implode( ',', $current_users ) );
			}

			// Nu pas de coupon-ID op de gebruiker bijwerken
			update_user_meta( $user_id, $select_key, $_POST[$select_key] );
		}
	}

	// Zorg ervoor dat wijzigingen aan klanten in kortingsbonnen ook gesynct worden met die profielen
	// add_action( 'woocommerce_update_coupon', 'sync_reductions_with_users', 10, 1 );

	function sync_reductions_with_users( $post_id ) {
		write_log( get_post_meta( $post_id, 'exclude_product_ids', true ) );
		write_log( "COUPON ".$post_id." WORDT BIJGEWERKT IN BLOG ".get_current_blog_id() );
	}

	// Functie geeft blijkbaar zeer vroeg al een zinnig antwoord
	add_action( 'init', 'activate_b2b_functions' );

	function activate_b2b_functions() {
		if ( ! is_admin() and is_b2b_customer() ) {
			// Zorg ervoor dat de spinners overal per ompak omhoog/omlaag gaan
			add_filter( 'woocommerce_quantity_input_args', 'suggest_order_unit_multiple', 10, 2 );
	
			// Geen BTW tonen bij producten en in het winkelmandje
			add_filter( 'pre_option_woocommerce_tax_display_shop', 'override_tax_display_setting' );
			add_filter( 'pre_option_woocommerce_tax_display_cart', 'override_tax_display_setting' );

			// Vervang alle prijssuffixen
			add_filter( 'woocommerce_get_price_suffix', 'b2b_price_suffix', 10, 2 );

			// Voeg 'excl. BTW' toe bij stukprijzen en subtotalen in winkelmandje en orderdetail (= ook mails!)
			add_filter( 'woocommerce_cart_subtotal', 'add_ex_tax_label_price', 10, 3 );
			add_filter( 'woocommerce_order_formatted_line_subtotal', 'add_ex_tax_label_price', 10, 3 );

			// Verwijder '(excl. BTW)' bij subtotalen
			add_filter( 'woocommerce_countries_ex_tax_or_vat', 'remove_ex_tax_label_subtotals' );
			
			// Limiteer niet-B2B-kortingsbonnen tot particulieren
			add_filter( 'wjecf_coupon_can_be_applied', 'restrain_coupons_to_b2c', 10, 2 );
		}

		function suggest_order_unit_multiple( $args, $product ) {
			$multiple = intval( $product->get_meta('_multiple') );
			if ( $multiple < 2 ) {
				$multiple = intval( $product->get_meta('_multiple') );
				if ( $multiple < 2 ) {
					$multiple = intval( $product->get_attribute('ompak') );
				}

				if ( $multiple < 2 ) {
					$multiple = 1;
				}
			}

			if ( is_cart() or ( array_key_exists( 'nm_mini_cart_quantity', $args ) and $args['nm_mini_cart_quantity'] === true ) ) {
				// Step enkel overrulen indien er op dit moment een veelvoud van de ompakhoeveelheid in het winkelmandje zit!
				// In de mini-cart wordt dit niet tijdens page-load bepaald omdat AJAX niet de hele blok refresht
				if ( $args['input_value'] % $multiple === 0 ) {
					$args['step'] = $multiple;
				}
			} else {
				// Input value enkel overrulen buiten het winkelmandje!
				$args['input_value'] = $multiple;
				$args['step'] = $multiple;
			}
			return $args;
		}

		function override_tax_display_setting() {
			return 'excl';
		}

		function b2b_price_suffix( $suffix, $product ) {
			return str_replace( 'incl', 'excl', $suffix );
		}

		function remove_ex_tax_label_subtotals() {
			return '';
		}

		function restrain_coupons_to_b2c( $can_be_applied, $coupon ) {
			if ( strpos( $coupon->get_code(), 'b2b-' ) === false ) {
				return false;
			} else {
				return $can_be_applied;
			}
		}
	}

	// Voeg 'incl. BTW' of 'excl. BTW' toe bij stukprijzen in winkelmandje
	add_filter( 'woocommerce_cart_item_price', 'add_ex_tax_label_price', 10, 3 );

	function add_ex_tax_label_price( $price, $cart_item, $cart_item_key ) {
		if ( is_b2b_customer() ) {
			$type = 'excl.';
		} else {
			$type = 'incl.';
		}
		return $price.' <small class="woocommerce-price-suffix">'.$type.' BTW</small>';
	}

	// Schakel BTW-berekeningen op productniveau uit voor geverifieerde bedrijfsklanten MAG ENKEL VOOR BUITENLANDSE KLANTEN
	// add_filter( 'woocommerce_product_get_tax_class', 'zero_rate_for_companies', 1, 2 );

	function zero_rate_for_companies( $tax_class, $product ) {
		$current_user = wp_get_current_user();
		if ( ! empty( get_user_meta( $current_user->ID, 'is_vat_exempt', true ) ) ) {
			$tax_class = 'vrijgesteld';
		}
		return $tax_class;
	}

	// Geef hint om B2B-klant te worden TIJDELIJK UITSCHAKELEN
	// add_action( 'woocommerce_just_before_checkout_form', 'show_b2b_account_hint', 10 );

	function show_b2b_account_hint() {
		// Niet tonen bij Brugge
		if ( ! is_b2b_customer() and get_current_blog_id() !== 25 ) {
			wc_print_notice( 'Wil je als bedrijf of vereniging aankopen op factuur doen? Vraag dan een B2B-account aan via <a href="mailto:'.get_webshop_email().'?subject=Aanvraag B2B-webshopaccount">'.get_webshop_email().'</a>.', 'notice' );
		}
	}
	
	// Toon enkel overschrijving als betaalmethode indien B2B-klant
	add_filter( 'woocommerce_available_payment_gateways', 'b2b_restrict_to_bank_transfer' );

	function b2b_restrict_to_bank_transfer( $gateways ) {
		if ( is_b2b_customer() ) {
			// write_log( print_r( $gateways, true ) );
			unset( $gateways['mollie_wc_gateway_bancontact'] );
			unset( $gateways['mollie_wc_gateway_creditcard'] );
			unset( $gateways['mollie_wc_gateway_kbc'] );
			unset( $gateways['mollie_wc_gateway_belfius'] );
			unset( $gateways['mollie_wc_gateway_inghomepay'] );
			unset( $gateways['mollie_wc_gateway_applepay'] );
			unset( $gateways['mollie_wc_gateway_ideal'] );
		} else {
			unset( $gateways['cod'] );
		}
		return $gateways;
	}

	// Toon aantal stuks dat toegevoegd zal worden aan het winkelmandje
	add_filter( 'woocommerce_product_add_to_cart_text', 'add_multiple_to_add_to_cart_text', 10, 2 );
	add_filter( 'woocommerce_product_single_add_to_cart_text', 'change_single_add_to_cart_text', 10, 2 );
	
	function add_multiple_to_add_to_cart_text( $text, $product ) {
		if ( is_b2b_customer() ) {
			$multiple = intval( $product->get_meta('_multiple') );
			if ( $multiple < 2 ) {
				$multiple = intval( $product->get_attribute('ompak') );
			}
			
			if ( $multiple < 2 ) {
				$text = 'Voeg 1 stuk toe aan mandje';
			} else {
				$text = 'Voeg '.$multiple.' stuks toe aan mandje';
			}
		} else {
			$text = 'Voeg toe aan winkelmandje';
		}
		return $text;
	}

	function change_single_add_to_cart_text( $text, $product ) {
		$text = 'Voeg toe aan mandje';
		return $text;
	}

	// Verberg onnuttige adresvelden tijdens het bewerken op het orderdetailscherm in de back-end
	add_filter( 'woocommerce_admin_billing_fields', 'custom_admin_billing_fields' );
	add_filter( 'woocommerce_admin_shipping_fields', 'custom_admin_shipping_fields' );
	add_action( 'woocommerce_admin_order_data_after_billing_address', 'show_custom_billing_fields', 10, 1 );

	function custom_admin_billing_fields( $address_fields ) {
		unset($address_fields['first_name']);
		unset($address_fields['last_name']);
		unset($address_fields['address_2']);
		unset($address_fields['state']);
		return $address_fields;
	}

	function custom_admin_shipping_fields( $address_fields ) {
		unset($address_fields['first_name']);
		unset($address_fields['last_name']);
		unset($address_fields['company']);
		unset($address_fields['address_2']);
		unset($address_fields['state']);
		return $address_fields;
	}

	function show_custom_billing_fields( $order ) {
		if ( $order->get_meta('_billing_vat') !== '' ) {
			echo '<p><strong>'.__( 'BTW-nummer', 'oxfam-webshop' ).':</strong><br/>'.$order->get_meta('_billing_vat').'</p>';
		}
	}

	// Geef de adresregels binnen 'Mijn account' een logische volgorde
	add_action( 'woocommerce_my_account_my_address_formatted_address', 'show_custom_address_fields', 10, 3 );

	function show_custom_address_fields( $address, $customer_id, $type ) {
		if ( $type === 'billing' ) {
			if ( is_b2b_customer() and get_user_meta( $customer_id, 'billing_vat', true ) ) {
				$address['first_name'] = '';
				$address['last_name'] = '';
				$address['address_2'] = $address['address_1'];
				$address['address_1'] = get_user_meta( $customer_id, 'billing_vat', true );
			}
		}
		return $address;
	}

	// Toon extra klantendata onder de contactgegevens (net boven de adressen)
	add_action( 'woocommerce_order_details_after_customer_details', 'shuffle_account_address', 100, 1 );

	function shuffle_account_address( $order ) {
		// Let op de underscore, wordt verwerkt als een intern veld!
		if ( $order->get_meta('_billing_vat') !== '' ) {
			?>
			<li>
				<h3>BTW-nummer</h3>
				<div><?php echo esc_html( $order->get_meta('_billing_vat') ); ?></div>
			</li>
			<?php
		}
	}

	// Zet webshopbeheerder in BCC bij versturen van B2B-uitnodigingsmails
	add_filter( 'woocommerce_email_headers', 'put_shop_manager_in_bcc', 10, 3 );

	function put_shop_manager_in_bcc( $headers, $type, $object ) {
		$extra_recipients = array();

		// We hernoemden de 'customer_new_account'-template maar het type blijft ongewijzigd!
		if ( $type === 'customer_reset_password' ) {
			// Bij dit type mogen we ervan uit gaan dat $object een WP_User bevat met de property ID
			if ( is_b2b_customer( $object->ID ) ) {
				$logger = wc_get_logger();
				$context = array( 'source' => 'Oxfam Emails' );
			
				// Door bestaan van tijdelijke file te checken, vermijden we om ook in BCC te belanden bij échte wachtwoordresets van B2B-gebruikers
				if ( file_exists( get_stylesheet_directory().'/woocommerce/emails/temporary.php' ) ) {
					$extra_recipients[] = 'Helpdesk E-Commerce <'.get_site_option('admin_email').'>';
					$extra_recipients[] = get_webshop_name().' <'.get_webshop_email().'>';
					$logger->debug( 'B2B-uitnodiging getriggerd naar user-ID '.$object->ID.' mét beheerders in BCC', $context );
				} else {
					$logger->debug( 'B2B-uitnodiging getriggerd naar user-ID '.$object->ID.' zónder beheerders in BCC', $context );
				}
			}
		}

		$headers .= 'BCC: '.implode( ',', $extra_recipients ).'\r\n';
		return $headers;
	}

	// Check tijdelijk de verstuurde bevestigingsmails door mezelf in BCC te zetten
	add_filter( 'woocommerce_email_headers', 'put_administrator_in_bcc', 10, 2 );

	function put_administrator_in_bcc( $headers, $object ) {
		if ( $object === 'customer_processing_order' or $object === 'customer_completed_order' ) {
			if ( wp_get_environment_type() === 'production' ) {
				$headers .= 'BCC: "Developer" <webshops@oxfamfairtrade.be>\r\n';
			} else {
				$headers .= 'BCC: "Developer" <'.get_site_option('admin_email').'>\r\n';
			}
		}
		return $headers;
	}

	add_filter( 'woocommerce_mail_callback_params', 'divert_and_flag_all_mails_in_dev', 10, 2 );

	function divert_and_flag_all_mails_in_dev( $params, $object ) {
		if ( wp_get_environment_type() !== 'production' ) {
			if ( is_array( $params ) ) {
				// Vervang bestemmeling enkel indien niet leeg
				// Dit heeft geen effect op eventuele (B)CC's in headers! 
				if ( $params[0] !== '' ) {
					$params[0] = get_site_option('admin_email');
				}
				// Prefix onderwerp
				$params[1] = 'TEST '.$params[1];
			}
		}
		return $params;
	}



	###################
	# HELPER FUNCTIES #
	###################

	// Print de geschatte leverdatums onder de beschikbare verzendmethodes
	add_filter( 'woocommerce_cart_shipping_method_full_label', 'print_estimated_delivery', 10, 2 );
	
	function print_estimated_delivery( $label, $method ) {
		$descr = '<small class="delivery-estimate">';
		$timestamp = estimate_delivery_date( $method->id );
		
		switch ( $method->id ) {
			// Nummers achter method_id slaan op de (unieke) instance_id binnen DEZE subsite!
			// Alle instances van de 'Gratis afhaling in winkel'-methode
			case stristr( $method->id, 'local_pickup' ):
				// Check of de winkel wel openingsuren heeft!
				if ( $timestamp ) {
					$descr .= sprintf( __( 'Dag (%1$s) en uur (%2$s) vanaf wanneer de bestelling klaarstaat voor afhaling', 'oxfam-webshop' ), date_i18n( 'l d/m/Y', $timestamp ), date_i18n( 'G\ui', $timestamp ) );
				}
				$label .= ':'.wc_price(0);
				break;
			// Alle instances van postpuntlevering
			case stristr( $method->id, 'service_point_shipping_method' ):
				$descr .= sprintf( __( 'Uiterste dag (%s) waarop het pakje beschikbaar zal zijn in postpunt / automaat', 'oxfam-webshop' ),  date_i18n( 'l d/m/Y', $timestamp ) );
				if ( floatval( $method->cost ) == 0 ) {
					$label = str_replace( 'Afhaling', 'Gratis afhaling', $label );
					$label .= ':'.wc_price(0);
				}
				break;
			// Alle instances van thuislevering
			case stristr( $method->id, 'flat_rate' ):
				$descr .= sprintf( __( 'Uiterste dag (%s) waarop de levering zal plaatsvinden', 'oxfam-webshop' ),  date_i18n( 'l d/m/Y', $timestamp ) );
				break;
			// Alle instances van gratis thuislevering
			case stristr( $method->id, 'free_shipping' ):
				$descr .= sprintf( __( 'Uiterste dag (%s) waarop de levering zal plaatsvinden', 'oxfam-webshop' ),  date_i18n( 'l d/m/Y', $timestamp ) );
				$label .= ':'.wc_price(0);
				break;
			// Alle instances van B2B-levering
			case stristr( $method->id, 'b2b_home_delivery' ):
				if ( floatval( $method->cost ) == 0 ) {
					$label .= ':'.wc_price(0);
				}
				break;
			default:
				$descr .= __( 'Boodschap indien schatting leverdatum niet beschikbaar', 'oxfam-webshop' );
				break;
		}
		$descr .= '</small>';
		// Geen schattingen tonen aan B2B-klanten
		if ( ! is_b2b_customer() ) {
			return $label.'<br/>'.$descr;
		} else {
			return $label;
		}
	}

	// Haal de openingsuren van de node voor een bepaalde dag op (werkt met dagindexes van 0 tot 6)
	function get_office_hours_for_day( $day, $node = 0, $shop_post_id = 0 ) {
		if ( $shop_post_id === 0 ) $shop_post_id = get_option('oxfam_shop_post_id');

		$oww_store_data = get_external_wpsl_store( $shop_post_id );
		if ( $oww_store_data !== false ) {
			// Bestaat in principe altijd
			$opening_hours = $oww_store_data['opening_hours'];
			
			$i = 0;
			$hours = array();
			$weekdays = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
			
			foreach ( $opening_hours[ $weekdays[$day] ] as $block ) {
				$parts = explode( ',', $block );
				if ( count($parts) === 2 ) {
					$hours[$i]['start'] = format_hour( $parts[0] );
					$hours[$i]['end'] = format_hour( $parts[1] );
				}
				$i++;
			}
			return $hours;
		}

		return false;
	}

	// Stop de openingsuren in een logische array (werkt met dagindices van 1 tot 7)
	function get_office_hours( $node = 0, $shop_post_id = 0 ) {
		if ( $node !== 0 ) {
			// write_log("get_office_hours() was invoked with deprecated node parameter! (value: ".$node.")");
		}

		if ( $shop_post_id === 0 ) {
			$shop_post_id = get_option('oxfam_shop_post_id');
		}
		
		if ( ! is_numeric( $shop_post_id ) ) {
			$hours = get_site_option( 'oxfam_opening_hours_'.$shop_post_id );
		} else {
			for ( $day = 0; $day <= 6; $day++ ) {
				// Forceer 'natuurlijke' nummering
				$hours[$day+1] = get_office_hours_for_day( $day, $node, $shop_post_id );
			}
		}

		return $hours;
	}

	// Stop de uitzonderlijke sluitingsdagen in een array
	function get_closing_days( $shop_post_id = 0 ) {
		if ( $shop_post_id === 0 ) $shop_post_id = get_option('oxfam_shop_post_id');
		
		if ( ! is_numeric( $shop_post_id ) ) {
			// Retourneert ook false indien onbestaande
			return get_site_option( 'oxfam_closing_days_'.$shop_post_id );
		} elseif ( intval( $shop_post_id ) > 0 ) {
			$oww_store_data = get_external_wpsl_store( $shop_post_id );
			if ( $oww_store_data !== false ) {
				// Bevat datums in 'Y-m-d'-formaat
				$closing_days = $oww_store_data['closing_days'];
				if ( count( $closing_days ) > 0 ) {
					return $closing_days;
				}
			}
		}

		return false;
	}

	// Bereken de eerst mogelijke leverdatum voor de opgegeven verzendmethode (retourneert een timestamp) 
	function estimate_delivery_date( $shipping_id, $order_id = false ) {
		// We gebruiken het geregistreerde besteltijdstip OF het live tijdstip voor schattingen van de leverdatum
		if ( $order_id === false ) {
			$from = current_time('timestamp');
		} else {
			$order = wc_get_order($order_id);
			// We hebben de timestamp van de besteldatum nodig in de huidige tijdzone, dus pas get_date_from_gmt() toe die het formaat 'Y-m-d H:i:s' vereist!
			$from = strtotime( get_date_from_gmt( date_i18n( 'Y-m-d H:i:s', strtotime( $order->get_date_created() ) ) ) );
		}
		
		$timestamp = $from;
		
		// Standaard: bereken a.d.h.v. de hoofdwinkel
		$chosen_shop_post_id = get_option('oxfam_shop_post_id');
		
		switch ( $shipping_id ) {
			// Alle instances van winkelafhalingen
			case stristr( $shipping_id, 'local_pickup' ):

				$locations = ob2c_get_pickup_locations( true, true );
				if ( count( $locations ) > 0 ) {
					if ( $order_id === false ) {
						// @toDo: Werkt dit nog in WooCommerce Local Pickup Plus 2.9+?
						$pickup_locations = WC()->session->get('chosen_pickup_locations');
						// write_log( print_r( $pickup_locations, true ) );
						if ( isset( $pickup_locations ) ) {
							$chosen_pickup_id = reset( $pickup_locations );
						} else {
							$chosen_pickup_id = 'ERROR';
						}
					} else {
						$methods = $order->get_shipping_methods();
						$method = reset( $methods );
						// @toDo: Werkt dit nog in WooCommerce Local Pickup Plus 2.9+?
						$chosen_pickup_location = $method->get_meta('pickup_location');
						// write_log( print_r( $chosen_pickup_location, true ) );
						$chosen_pickup_id = $chosen_pickup_location['id'];
					}
					foreach ( $locations as $shop_post_id => $pickup_id ) {
						if ( $pickup_id == $chosen_pickup_id ) {
							$chosen_shop_post_id = $shop_post_id;
							break;
						}
					}
				}

				if ( $chosen_shop_post_id === 'tuincentrum' ) {
					if ( date_i18n( 'N', $from ) > 4 or ( date_i18n( 'N', $from ) == 4 and date_i18n( 'G', $from ) >= 12 ) ) {
						// Na de deadline van donderdag 12u00: begin pas bij volgende werkdag, kwestie van zeker op volgende week uit te komen
						$from = strtotime( '+1 weekday', $from );
					}

					// Zoek de eerste vrijdag na de volgende middagdeadline
					$timestamp = strtotime( 'next Friday', $from );
				} elseif ( $chosen_shop_post_id === 'vorselaar' ) {
					if ( date_i18n( 'N', $from ) > 4 ) {
						// Na de deadline van donderdag 23u59: begin pas bij volgende werkdag, kwestie van zeker op volgende week uit te komen
						$from = strtotime( '+1 weekday', $from );
					}

					// Zoek de eerste vrijdag na de volgende middagdeadline (wordt wegens openingsuren automatisch zaterdagochtend)
					$timestamp = strtotime( 'next Friday', $from );

					// Skip check op uitzonderlijke sluitingsdagen
					return find_first_opening_hour( get_office_hours( NULL, $chosen_shop_post_id ), $timestamp );
				} elseif ( intval( $chosen_shop_post_id ) === 3478 ) {
					// Meer marge voor Hoogstraten
					if ( date_i18n( 'N', $from ) < 4 or ( date_i18n( 'N', $from ) == 7 and date_i18n( 'G', $from ) >= 22 ) ) {
						// Na de deadline van zondag 22u00: begin pas bij 4de werkdag, kwestie van zeker op volgende week uit te komen
						$from = strtotime( '+4 weekdays', $from );
					}

					// Zoek de eerste donderdag na de volgende middagdeadline (wordt wegens openingsuren automatisch vrijdagochtend)
					$timestamp = strtotime( 'next Thursday', $from );
				} else {
					$timestamp = get_first_working_day( $from );

					// Geef nog twee extra werkdagen voor afhaling in niet-OWW-punten
					if ( ! is_numeric( $chosen_shop_post_id ) ) {
						$timestamp = strtotime( '+2 weekdays', $timestamp );
					}
				}

				// Check of de winkel op deze dag effectief nog geopend is na 12u (tel er indien nodig dagen bij)
				$timestamp = find_first_opening_hour( get_office_hours( NULL, $chosen_shop_post_id ), $timestamp );

				// Tel alle sluitingsdagen die in de verwerkingsperiode vallen (inclusief de eerstkomende openingsdag!) erbij
				$timestamp = move_date_on_holidays( $from, $timestamp );

				// Check of de winkel ook op de nieuwe dag effectief nog geopend is na 12u
				$timestamp = find_first_opening_hour( get_office_hours( NULL, $chosen_shop_post_id ), $timestamp );

				break;

			// Alle (gratis/betalende) instances van postpuntlevering en thuislevering
			default:
				if ( intval( $chosen_shop_post_id ) === 3338 ) {
					// Voorlopig enkel thuislevering op woensdag bij Brussel 
					if ( ( date_i18n( 'N', $from ) == 5 and date_i18n( 'G', $from ) >= 15 ) or date_i18n( 'N', $from ) > 5 ) {
						// Na de deadline van vrijdag 15u00: begin pas bij 4de werkdag, kwestie van zeker op volgende week uit te komen
						$from = strtotime( '+4 weekdays', $from );
					} else {
						// Tel er sowieso 2 werkdagen bij, zodat we op maandag en dinsdag ook doorschuiven naar de volgende week
						$from = strtotime( '+2 weekdays', $from );
					}

					// Zoek de eerste woensdag
					$timestamp = strtotime( 'next Wednesday', $from );
				} else {
					// Zoek de eerste werkdag na de volgende middagdeadline
					$timestamp = get_first_working_day( $from );

					// Geef nog twee extra werkdagen voor de thuislevering
					$timestamp = strtotime( '+2 weekdays', $timestamp );

					// Tel feestdagen die in de verwerkingsperiode vallen erbij
					$timestamp = move_date_on_holidays( $from, $timestamp );
				}
		}

		return $timestamp;
	}

	// Ontvangt een timestamp en antwoordt met eerste werkdag die er toe doet
	function get_first_working_day( $from ) {
		if ( date_i18n( 'N', $from ) < 6 and date_i18n( 'G', $from ) < 12 ) {
			// Geen actie nodig
		} else {
			// We zitten al na de deadline van een werkdag, begin pas vanaf volgende werkdag te tellen
			$from = strtotime( '+1 weekday', $from );
		}

		// Bepaal de eerstvolgende werkdag
		$timestamp = strtotime( '+1 weekday', $from );
		
		return $timestamp;
	}

	// Check of er feestdagen in een bepaalde periode liggen, en zo ja: tel die dagen bij de einddag
	// Neemt een begin- en eindpunt en retourneert het nieuwe eindpunt (allemaal in timestamps)
	function move_date_on_holidays( $from, $till ) {
		// Check of de startdag ook nog in beschouwing genomen moet worden
		if ( date_i18n( 'N', $from ) < 6 and date_i18n( 'G', $from ) >= 12 ) {
			$first = date_i18n( 'Y-m-d', strtotime( '+1 weekday', $from ) );
		} else {
			$first = date_i18n( 'Y-m-d', $from );
		}
		// In dit formaat zijn datum- en tekstsortering equivalent!
		// Tel er een halve dag bij om tijdzoneproblemen te vermijden
		$last = date_i18n( 'Y-m-d', $till+12*60*60 );
		
		// Neem de wettelijke feestdagen indien er geen enkele gedefinieerd zijn TIJDELIJK UITSCHAKELEN
		// foreach ( get_option( 'oxfam_holidays', get_site_option('oxfam_holidays') ) as $holiday ) {
		// 	// Enkel de feestdagen die niet in het weekend vallen moeten we in beschouwing nemen!
		// 	if ( date_i18n( 'N', strtotime($holiday) ) < 6 and ( $holiday > $first ) and ( $holiday <= $last ) ) {
		// 		// TO DO: Enkel werkdag bijtellen indien de winkel niet sowieso al gesloten is op deze weekdag?
		// 		$till = strtotime( '+1 weekday', $till );
		// 		$last = date_i18n( 'Y-m-d', $till+12*60*60 );
		// 	}
		// }
		
		return $till;
	}

	// Zoek het eerstvolgende openeningsuur op een dag (indien $afternoon: pas vanaf 12u)
	function find_first_opening_hour( $hours, $from, $afternoon = true, $tried = 0 ) {
		// Argument 'N' want get_office_hours() werkt van 1 tot 7!
		$i = date_i18n( 'N', $from );
		if ( $hours[$i] ) {
			$day_part = $hours[$i][0];
			$start = intval( substr( $day_part['start'], 0, -2 ) );
			$end = intval( substr( $day_part['end'], 0, -2 ) );
			if ( $afternoon ) {
				if ( $end > 12 ) {
					if ( intval( substr( $day_part['start'], 0, -2 ) ) >= 12 ) {
						// Neem het openingsuur van het eerste deel
						$timestamp = strtotime( date_i18n( 'Y-m-d', $from )." ".$day_part['start'] );
					} else {
						// Toon pas mogelijk vanaf 12u
						$timestamp = strtotime( date_i18n( 'Y-m-d', $from )." 12:00" );
					}
				} else {
					unset( $day_part );
					// Ga naar het tweede dagdeel (we gaan er van uit dat er nooit drie zijn!)
					$day_part = $hours[$i][1];
					$start = intval( substr( $day_part['start'], 0, -2 ) );
					$end = intval( substr( $day_part['end'], 0, -2 ) );
					if ( $end > 12 ) {
						if ( intval( substr( $day_part['start'], 0, -2 ) ) >= 12 ) {
							// Neem het openingsuur van dit deel
							$timestamp = strtotime( date_i18n( 'Y-m-d', $from )." ".$day_part['start'] );
						} else {
							// Toon pas mogelijk vanaf 12u
							$timestamp = strtotime( date_i18n( 'Y-m-d', $from )." 12:00" );
						}
					} else {
						// Het mag ook een dag in het weekend zijn, de wachttijd is vervuld!
						$timestamp = find_first_opening_hour( $hours, strtotime( 'tomorrow', $from ), false );
					}
				}
			} else {
				// Neem sowieso het openingsuur van het eerste dagdeel
				$timestamp = strtotime( date_i18n( 'Y-m-d', $from )." ".$day_part['start'] );
			}
		} else {
			// Indien alle openingsuren weggehaald zijn (elke dag in $hours === false): stop na 7 pogingen
			if ( $tried < 7 ) {
				// Vandaag zijn we gesloten, probeer het morgen opnieuw
				// Het mag nu ook een dag in het weekend zijn, de wachttijd is vervuld!
				$timestamp = find_first_opening_hour( $hours, strtotime( 'tomorrow', $from ), false, $tried+1 );
			} else {
				// Beëindig de loop
				$timestamp = false;
			}
		}
		return $timestamp;
	}

	// Bewaar het verzendadres niet tijdens het afrekenen indien het om een afhaling gaat WEL BIJ SERVICE POINT, WANT NODIG VOOR IMPORT
	add_filter( 'woocommerce_cart_needs_shipping_address', 'skip_shipping_address_on_pickups' ); 
	
	function skip_shipping_address_on_pickups( $needs_shipping_address ) {
		$chosen_methods = WC()->session->get('chosen_shipping_methods');
		// Deze vergelijking zoekt naar methodes die beginnen met deze string
		if ( strpos( reset($chosen_methods), 'local_pickup' ) !== false ) {
			$needs_shipping_address = false;
		}
		return $needs_shipping_address;
	}

	// Verberg het verzendadres na het bestellen ook bij een postpuntlevering in de front-end
	add_filter( 'woocommerce_order_hide_shipping_address', 'hide_shipping_address_on_pickups' ); 
	
	function hide_shipping_address_on_pickups( $hide_on_methods ) {
		// Bevat 'local_pickup' reeds via core en 'local_pickup_plus' via filter in plugin
		// Instances worden er afgeknipt bij de check dus achterwege laten
		$hide_on_methods[] = 'service_point_shipping_method';
		return $hide_on_methods;
	}

	function validate_zip_code( $zip ) {
		if ( does_home_delivery() and $zip !== 0 ) {
			// Eventueel enkel tonen op de winkelmandpagina m.b.v. is_cart()
			if ( ! array_key_exists( $zip, get_site_option( 'oxfam_flemish_zip_codes' ) ) and $zip !== 6941 ) {
				wc_add_notice( __( 'Foutmelding na het ingeven van een onbestaande Vlaamse postcode.', 'oxfam-webshop' ), 'error' );
				return false;
			} else {
				// NIET get_option('oxfam_zip_codes') gebruiken om onterechte foutmeldingen bij overlap te vermijden
				if ( ! in_array( $zip, get_oxfam_covered_zips() ) ) {
					$str = date_i18n('d/m/Y H:i:s')."\t\t".get_home_url()."\t\tPostcode ".$zip."\t\tGeen verzending georganiseerd door deze winkel\n";
					if ( ! current_user_can('update_core') ) {
						file_put_contents( "../shipping_errors.csv", $str, FILE_APPEND );
					}
					
					if ( WC()->customer->get_billing_postcode() !== WC()->customer->get_shipping_postcode() ) {
						// Zet de verzendpostcode gelijk aan de factuurpostcode BETER LETTERLIJK IN FRONTEND DOEN?
						WC()->customer->set_shipping_postcode( WC()->customer->get_billing_postcode() );
						write_log("SHIPPING POSTCODE FORCED TO BILLING (ERROR PROCEDURE)");
						$current_user = wp_get_current_user();
						write_log( "SHIPPING POSTCODE FOR CUSTOMER ".$current_user->user_login." (ERROR PROCEDURE): ".WC()->customer->get_shipping_postcode() );
					}

					// Toon de foutmelding slechts één keer
					if ( WC()->session->get( 'no_zip_delivery_in_'.get_current_blog_id().'_for_'.$zip ) !== 'SHOWN' ) {
						$global_zips = get_shops();
						if ( isset( $global_zips[$zip] ) ) {
							$url = $global_zips[$zip];
							// TO DO: COMMANDO TOEVOEGEN OM WINKELMANDJE OVER TE ZETTEN
							$skus = array();
							if ( WC()->session->has_session() and current_user_can('update_core') ) {
								foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
									$product_in_cart = $values['data'];
									$skus[] = $product_in_cart->get_sku();
								}
							}
							if ( count( $skus ) > 0 ) {
								$url .= '?addSkus='.implode( ',', $skus );
							}
							// Check eventueel of de boodschap al niet in de pijplijn zit door alle values van de array die wc_get_notices('error') retourneert te checken
							wc_add_notice( sprintf( __('Foutmelding na het ingeven van postcode %1$s waar deze webshop geen thuislevering voor organiseert, inclusief URL %2$s van webshop die dat wel doet.', 'oxfam-webshop' ), $zip, $url ), 'error' );
							WC()->session->set( 'no_zip_delivery_in_'.get_current_blog_id().'_for_'.$zip, 'SHOWN' );
						}
					}
				}
			}
		}
	}

	// Zet een maximum op het aantal items dat je kunt toevoegen CHECKT NIET OP REEDS AANWEZIGE ITEMS, NIET INTERESSANT
	// add_action( 'woocommerce_add_to_cart_validation', 'maximum_item_quantity_validation' );

	function maximum_item_quantity_validation( $passed, $product_id, $quantity, $variation_id, $variations ) {
		if ( $quantity > 10 ) {
			wc_add_notice( 'Je kunt maximum 10 exemplaren van een product toevoegen aan je winkelmandje.', 'error' );
		} else {
			return true;
		}
	}

	// Moedig aan om producten toe te voegen om gratis thuislevering te activeren
	add_action( 'woocommerce_before_cart', 'show_almost_free_shipping_notice' );

	function show_almost_free_shipping_notice() {
		if ( is_cart() and ! is_b2b_customer() ) {
			// Indien de threshold afhangt van de postcode zal dit niet helemaal kloppen ...
			$threshold = get_option( 'oxfam_minimum_free_delivery', get_site_option('oxfam_minimum_free_delivery') );
			// Subtotaal = winkelmandje inclusief belasting, exclusief verzending
			$current = WC()->cart->subtotal;
			if ( $current > ( 0.7 * $threshold ) ) {
				if ( $current < $threshold ) {
					// Probeer de boodschap slechts af en toe te tonen via sessiedata
					$cnt = WC()->session->get( 'pursue_free_delivery_message_count', 0 );
					// Opgelet: WooCoomerce moet actief zijn, we moeten in de front-end zitten én er moet al een winkelmandje aangemaakt zijn!
					WC()->session->set( 'pursue_free_delivery_message_count', $cnt+1 );
					$msg = WC()->session->get('no_home_delivery');
					// Enkel tonen indien thuislevering effectief beschikbaar is voor het huidige winkelmandje
					if ( $cnt % 7 === 0 and $msg !== 'SHOWN' ) {
						wc_add_notice( 'Tip: als je nog '.wc_price( $threshold - $current ).' toevoegt, kom je in aanmerking voor gratis thuislevering.', 'success' );
					}
				}
			} else {
				WC()->session->set( 'pursue_free_delivery_message_count', 0 );
			}
		}
	}
	
	// Definieer een globale B2B-levermethode zonder support voor verzendzones
	add_filter( 'woocommerce_shipping_methods', 'add_b2b_home_delivery_method' );
	add_action( 'woocommerce_shipping_init', 'create_b2b_home_delivery_method' );

	function add_b2b_home_delivery_method( $methods ) {
		$methods['b2b_home_delivery'] = 'WC_B2B_Home_Delivery_Method';
		return $methods;
	}
	
	function create_b2b_home_delivery_method() {
		class WC_B2B_Home_Delivery_Method extends WC_Shipping_Method {
			public function __construct() {
				$this->id = 'b2b_home_delivery';
				$this->method_title = __( 'B2B-leveringen', 'oxfam-webshop' );
				$this->init_form_fields();
				$this->init_settings();
				$this->enabled = $this->get_option('enabled');
				$this->title = $this->get_option('title');
				$this->cost = $this->get_option('cost');
				add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
			}

			public function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title' => 'Actief?',
						'type' => 'checkbox',
						'label' => 'Schakel levering op locatie in voor bedrijfsklanten',
						'default' => 'yes',
					),
					'title' => array(
						'title' => 'Label?',
						'type' => 'text',
						'description' => 'Dit is de naam waarmee de verzendmethode onder het winkelmandje verschijnt.',
						'default' => 'Levering op locatie (timing af te spreken)',
					),
					'cost' => array(
						'title' => 'Kostprijs?',
						'type' => 'number',
						'custom_attributes' => array(
							'step' => '0.05',
							'min' => '0',
							'max' => '20',
						),
						'default' => '0',
					),
					// TO DO: Verificatie toevoegen op bereiken van bestelminimum (ofwel in woocommerce_package_rates, ofwel in woocommerce_checkout_process)
					'limit' => array(
						'title' => 'Bestelminimum?',
						'type' => 'number',
						'description' => 'De verzendmethode is enkel beschikbaar indien er voor minimum dit bedrag besteld wordt.',
						'custom_attributes' => array(
							'step' => '1',
							'min' => '10',
							'max' => '1000',
						),
						'default' => '100',
					),
				);
			}

			public function is_available( $package ) {
				if ( $this->enabled === 'yes' and is_b2b_customer() ) {
					return true;
				} else {
					return false;
				}
			}

			public function calculate_shipping( $package = array() ) {
				$rate = array(
					'id' => $this->id,
					'label' => $this->title,
					'cost' => $this->cost,
					// Laat de BTW automatisch variëren volgens inhoud winkelmandje
					'taxes' => '',
					'calc_tax' => 'per_order',
				);
				$this->add_rate($rate);
			}
		}
	}

	// Handig filtertje om het JavaScript-conflict op de checkout te debuggen
	// add_filter( 'woocommerce_ship_to_different_address_checked', '__return_true' );

	// Fix voor verborgen verzendadressen die aanpassen leverpostcode verhinderen
	// add_filter( 'woocommerce_package_rates', 'fix_shipping_postcode', 100, 2 );

	function fix_shipping_postcode( $rates, $package ) {
		// GEWIJZIGD: Zorg dat er altijd al een postcode ingevuld is, zodat de verzendmethodes niet verdwijnen bij het uitklappen
		if ( intval( WC()->customer->get_shipping_postcode() ) < 1000 ) {
			if ( intval( WC()->customer->get_billing_postcode() ) >= 1000 ) {
				// Initialiseer op factuurpostcode
				WC()->customer->set_shipping_postcode( WC()->customer->get_billing_postcode() );
			} else {
				// Initialiseer op winkelpostcode
				WC()->customer->set_shipping_postcode( get_oxfam_shop_data('zipcode') );
			}
		}

		// $current_user = wp_get_current_user();
		// write_log( "SHIPPING POSTCODE FOR CUSTOMER ".$current_user->user_login.": ".WC()->customer->get_shipping_postcode() );
		// if ( ! apply_filters( 'woocommerce_ship_to_different_address_checked', 'shipping' === get_option( 'woocommerce_ship_to_destination' ) ) and WC()->customer->get_billing_postcode() !== WC()->customer->get_shipping_postcode() ) {
		// 	// Zet de verzendpostcode gelijk aan de factuurpostcode
		// 	WC()->customer->set_shipping_postcode( WC()->customer->get_billing_postcode() );
		// 	write_log("SHIPPING POSTCODE FORCED TO BILLING (GENERAL)");
		// 	write_log( "SHIPPING POSTCODE FOR CUSTOMER ".$current_user->user_login." (GENERAL): ".WC()->customer->get_shipping_postcode() );
		// }

		return $rates;
	}

	// Disable sommige verzendmethoden onder bepaalde voorwaarden
	add_filter( 'woocommerce_package_rates', 'hide_shipping_recalculate_taxes', 10, 2 );
	
	function hide_shipping_recalculate_taxes( $rates, $package ) {
		if ( ! is_b2b_customer() ) {
			validate_zip_code( intval( WC()->customer->get_shipping_postcode() ) );

			// Check of er een gratis levermethode beschikbaar is => uniform minimaal bestedingsbedrag!
			$free_home_available = false;
			foreach ( $rates as $rate_key => $rate ) {
				if ( $rate->method_id === 'free_shipping' ) {
					$free_home_available = true;	
					break;
				}
			}

			if ( $free_home_available ) {
				// Verberg alle betalende methodes indien er een gratis thuislevering beschikbaar is
				foreach ( $rates as $rate_key => $rate ) {
					if ( floatval( $rate->cost ) > 0.0 ) {
						unset( $rates[$rate_key] );
					}
				}
			} else {
				// Verberg alle gratis methodes die geen afhaling zijn
				foreach ( $rates as $rate_key => $rate ) {
					if ( $rate->method_id !== 'local_pickup_plus' and floatval( $rate->cost ) === 0.0 ) {
						// IS DIT WEL NODIG, WORDEN TOCH AL VERBORGEN DOOR WOOCOMMERCE?
						// unset( $rates[$rate_key] );
					}
				}
			}

			if ( ! does_risky_delivery() ) {
				// Verhinder alle externe levermethodes indien er een product aanwezig is dat niet thuisgeleverd wordt
				$glass_cnt = 0;
				$plastic_cnt = 0;
				foreach ( WC()->cart->cart_contents as $item_key => $item_value ) {
					if ( $item_value['data']->get_shipping_class() === 'breekbaar' ) {
						// Omwille van de icoontjes is niet alleen het leeggoed maar ook het product als breekbaar gemarkeerd!
						if ( $item_value['product_id'] === wc_get_product_id_by_sku('WLFSG') ) {
							$glass_cnt += intval($item_value['quantity']);
						}
						if ( $item_value['product_id'] === wc_get_product_id_by_sku('WLBS6') or $item_value['product_id'] === wc_get_product_id_by_sku('WLBS24') or $item_value['product_id'] === wc_get_product_id_by_sku('W29917') ) {
							$plastic_cnt += intval($item_value['quantity']);
						}
					} 
				}
				
				if ( $glass_cnt + $plastic_cnt > 0 ) {
					foreach ( $rates as $rate_key => $rate ) {
						// Blokkeer alle methodes behalve afhalingen
						if ( $rate->method_id !== 'local_pickup_plus' ) {
							unset( $rates[$rate_key] );
						}
					}
					// Boodschap heeft enkel zin als thuislevering aangeboden wordt!
					if ( does_home_delivery() ) {
						$msg = WC()->session->get('no_home_delivery');
						// Toon de foutmelding slechts één keer
						if ( $msg !== 'SHOWN' ) {
							if ( $glass_cnt > 0 and $plastic_cnt > 0 ) {
								wc_add_notice( 'Je winkelmandje bevat '.sprintf( _n( '%d grote fles', '%d grote flessen', $glass_cnt, 'oxfam-webshop' ), $glass_cnt ).' fruitsap en '.sprintf( _n( '%d krat', '%d kratten', $plastic_cnt, 'oxfam-webshop' ), $plastic_cnt ).' leeggoed. Deze producten zijn te onhandig om op te sturen. Kom je bestelling afhalen in de winkel, of verwijder ze uit je winkelmandje om thuislevering weer mogelijk te maken.', 'error' );
							} elseif ( $glass_cnt > 0 ) {
								wc_add_notice( 'Je winkelmandje bevat '.sprintf( _n( '%d grote fles', '%d grote flessen', $glass_cnt, 'oxfam-webshop' ), $glass_cnt ).' fruitsap. Deze producten zijn te onhandig om op te sturen. Kom je bestelling afhalen in de winkel, of verwijder ze uit je winkelmandje om thuislevering weer mogelijk te maken.', 'error' );
							} elseif ( $plastic_cnt > 0 ) {
								wc_add_notice( 'Je winkelmandje bevat '.sprintf( _n( '%d krat', '%d kratten', $plastic_cnt, 'oxfam-webshop' ), $plastic_cnt ).' leeggoed. Dit is te onhandig om op te sturen. Kom je bestelling afhalen in de winkel, of verminder het aantal kleine flesjes in je winkelmandje om thuislevering weer mogelijk te maken.', 'error' );
							}
							WC()->session->set( 'no_home_delivery', 'SHOWN' );
						}
					}
				} else {
					WC()->session->set( 'no_home_delivery', 'RESET' );
				}
			}
			
			// Verhinder alle externe levermethodes indien totale brutogewicht > 29 kg (neem 1 kg marge voor verpakking)
			// $cart_weight = wc_get_weight( WC()->cart->get_cart_contents_weight(), 'kg' );
			// if ( $cart_weight > 29 ) {
			// 	foreach ( $rates as $rate_key => $rate ) {
			// 		// Blokkeer alle methodes behalve afhalingen
			// 		if ( $rate->method_id !== 'local_pickup_plus' ) {
			// 			unset( $rates[$rate_key] );
			// 		}
			// 	}
			// 	wc_add_notice( sprintf( __( 'Foutmelding bij bestellingen boven de 30 kg, inclusief het huidige gewicht in kilogram (%s).', 'oxfam-webshop' ), number_format( $cart_weight, 1, ',', '.' ) ), 'error' );
			// }

			$reduced_vat_slug = 'voeding';
			$reduced_vat_rates = WC_Tax::get_rates_for_tax_class( $reduced_vat_slug );
			$reduced_vat_rate = reset( $reduced_vat_rates );
			
			// Slug voor 'standard rate' is een lege string!
			$standard_vat_rates = WC_Tax::get_rates_for_tax_class('');
			$standard_vat_rate = reset( $standard_vat_rates );
			
			$tax_classes = WC()->cart->get_cart_item_tax_classes();
			if ( ! in_array( $reduced_vat_slug, $tax_classes ) ) {
				// Brutoprijs verlagen om te compenseren voor hoger BTW-tarief
				$cost = STANDARD_VAT_SHIPPING_COST;
				// Ook belastingen expliciet herberekenen!
				$tax_cost = 0.21 * $cost;
				$tax_rate = $standard_vat_rate;
			} else {
				$cost = REDUCED_VAT_SHIPPING_COST;
				$tax_cost = 0.06 * $cost;
				$tax_rate = $reduced_vat_rate;
			}
			
			// Overschrijf alle verzendprijzen (dus niet enkel in 'uitsluitend 21%'-geval -> te onzeker) indien betalende thuislevering
			if ( ! $free_home_available ) {
				foreach ( $rates as $rate_key => $rate ) {
					switch ( $rate_key ) {
						case in_array( $rate->method_id, array( 'flat_rate', 'service_point_shipping_method' ) ):
							// Zie WC_Shipping_Rate-klasse, geen save() nodig
							$rate->set_cost( $cost );
							// Dit verwijdert meteen ook het andere BTW-tarief 
							$rate->set_taxes( array( $tax_rate->tax_rate_id => $tax_cost ) );
							break;
						default:
							// Dit zijn de gratis pick-ups (+ eventueel thuisleveringen), niets mee doen
							break;
					}
				}
			}
		} else {
			foreach ( $rates as $rate_key => $rate ) {
				$shipping_zones = WC_Shipping_Zones::get_zones();
				foreach ( $shipping_zones as $shipping_zone ) {
					// Alle niet-B2B-levermethodes uitschakelen
					$non_b2b_methods = $shipping_zone['shipping_methods'];
					foreach ( $non_b2b_methods as $shipping_method ) {
						// Behalve afhalingen en B2B-leveringen maar die vallen niet onder een zone!
						$method_key = $shipping_method->id.':'.$shipping_method->instance_id;
						unset( $rates[ $method_key ] );
					}
				}
			}
		}
		
		// write_log( print_r( $rates, true ) );
		return $rates;
	}

	// Zorg dat afhalingen in de winkel als standaard levermethode geselecteerd worden
	// Nodig omdat Local Pickup Plus geen verzendzones gebruikt maar alles overkoepelt
	// Documentatie in class-wc-shipping.php: "If not set, not available, or available methods have changed, set to the DEFAULT option" UITSCHAKELEN
	add_filter( 'woocommerce_shipping_chosen_method', 'set_pickup_as_default_shipping', 10, 3 );

	function set_pickup_as_default_shipping( $default, $rates, $chosen_method ) {
		return 'local_pickup_plus';
	}

	// Verberg shipping calculator onderaan winkelmandje
	add_filter( 'woocommerce_shipping_show_shipping_calculator', '__return_false' );

	// Eventueel kunnen we ook 'woocommerce_after_shipping_rate' gebruiken (na elke verzendmethode) WORDT NETJES BIJGEWERKT BIJ AJAX-ACTIE UPDATE_SHIPPING
	add_action( 'woocommerce_review_order_before_shipping', 'explain_why_shipping_option_is_lacking' );
	add_action( 'woocommerce_cart_totals_before_shipping', 'explain_why_shipping_option_is_lacking' );
	
	function explain_why_shipping_option_is_lacking() {
		// Als er slechts één methode beschikbaar is in een webshop mét afhaling in de winkel, moet het wel die afhaling zijn!
		if ( does_local_pickup() and count( WC()->shipping->packages[0]['rates'] ) < 2 ) {
		    if ( ! does_home_delivery() ) {
				$title = 'Deze winkel organiseert geen thuislevering. Ga naar de webshop die voor jouw postcode aan huis levert.';
			} elseif ( WC()->session->get('no_home_delivery') === 'SHOWN' ) {
				// Dit werkt enkel indien blokkage omwille van leeggoed reeds getoond
				$title = 'Omdat er producten in je winkelmandje zitten die niet beschikbaar zijn voor thuislevering.';
			} elseif ( strlen( WC()->customer->get_shipping_postcode() ) < 4 ) {
				// WC()->customer->has_calculated_shipping() werkt niet zoals verwacht
				$title = 'Omdat de postcode nog niet ingevuld is.';
			} else {
				$title = 'Omdat deze webshop niet thuislevert in de huidige postcode.';
			}
			echo '<tr><td colspan="2" class="shipping-explanation">Waarom is verzending niet beschikbaar? <a class="dashicons dashicons-editor-help tooltip" title="'.$title.'"></a></td></tr>';
		}
	}

	// Voeg instructietekst toe boven de locaties
	// add_action( 'woocommerce_review_order_before_local_pickup_location', 'add_local_pickup_instructions' );
	
	function add_local_pickup_instructions() {
		echo '<br/><p>Je kunt kiezen uit volgende winkels ...</p>';
	}

	// Verberg de 'kortingsbon invoeren'-boodschap bij het afrekenen
	add_filter( 'woocommerce_checkout_coupon_message', 'remove_msg_filter' );

	function remove_msg_filter( $msg ) {
		if ( is_checkout() ) {
			return '';
		}
		return $msg;
	}

	function get_oxfam_empties_skus_array() {
		return array( 'WLFSK', 'WLFSG', 'W19916', 'WLBS6', 'WLBS24', 'W29917' );
	}

	// Of gewoon rechtstreeks aanpassen in template?
	add_filter( 'wcgwp_add_wrap_message', 'ob2c_change_add_gift_wrap_message', 10, 1 );

	function ob2c_change_add_gift_wrap_message( $html ) {
		return '<b>Optioneel: geef een boodschap mee aan de gelukkige (max. '.get_option( 'wcgwp_textarea_limit', '1000' ).' tekens). Wij schrijven dit in ons mooiste handschrift op een geschenkkaartje. Uiteraard voegen we geen kassaticket toe.</b>';
	}

	function ob2c_product_is_gift_wrapper( $cart_item ) {
		if ( is_array( $cart_item ) ) {
			if ( class_exists('WCGW_Wrapping') ) {
				// Dynamisch ophalen m.b.v. WCGW_Wrapping-klasse
				$wcgw_wrapping = new WCGW_Wrapping();
				return $wcgw_wrapping->check_item_for_giftwrap_cat( $cart_item );
			}
		}
		return false;
	}

	// Voeg bakken leeggoed enkel toe per 6 of 24 flessen
	add_filter( 'wc_force_sell_add_to_cart_product', 'check_plastic_empties_quantity', 10, 2 );

	function check_plastic_empties_quantity( $empties_array, $product_item ) {
		// $empties_array bevat geen volwaardig cart_item, enkel array met de keys id / quantity / variation_id / variation!
		$empties_product = wc_get_product( $empties_array['id'] );
		$do_not_count_for_crates = false;

		if ( $empties_product !== false ) {
			$empties_sku = $empties_product->get_sku();
			
			if ( $empties_sku === 'W19916' or $empties_sku === 'W29917' ) {
				// Vermenigvuldig de flesjes bij samengestelde producten (= eleganter dan een extra leeggoedartikel aan te maken)
				// We kunnen dit niet in de switch verderop doen, aangezien ook de berekening voor W29917 deze gemanipuleerde hoeveelheden nodig heeft
				$product = wc_get_product( $product_item['product_id'] );
				if ( $product !== false ) {
					switch ( $product->get_sku() ) {
						case '20807':
						case '20809':
						case '20811':
							// Voeg 4 flesjes leeggoed toe bij clips
							$empties_array['quantity'] = 4 * intval( $product_item['quantity'] );
							// OVERRULE OOK PRODUCTHOEVEELHEID MET HET OOG OP ONDERSTAANDE LOGICA
							$product_item['quantity'] = 4 * intval( $product_item['quantity'] );
							break;

						case '19236':
						case '19237':
						case '19238':
						case '19239':
							// Voeg 3 flesjes leeggoed toe bij geschenksets
							$empties_array['quantity'] = 3 * intval( $product_item['quantity'] );
							// OVERRULE OOK PRODUCTHOEVEELHEID MET HET OOG OP ONDERSTAANDE LOGICA
							$product_item['quantity'] = 3 * intval( $product_item['quantity'] );
							// Hou met deze flesjes geen rekening bij berekenen van aantal plastic bakken
							$do_not_count_for_crates = true;
							break;
					}
				}
			}
			
			switch ( $empties_sku ) {
				case 'WLBS6':
					$empties_array['quantity'] = floor( intval( $product_item['quantity'] ) / 6 );
					break;

				case 'WLBS24':
				case 'W29917':
					// Door round() voegen we automatisch een bak toe vanaf 13 flesjes
					$empties_array['quantity'] = round( intval( $product_item['quantity'] ) / 24, 0, PHP_ROUND_HALF_DOWN );
					break;

				case 'WLFSG':
				case 'WLFSK':
				case 'W19916':
					// Definieer de koppelingen tussen glas en plastic
					switch ( $empties_sku ) {
						case 'WLFSG':
							$plastic_sku = 'WLBS6';
							$plastic_step = 6;
							break;

						case 'WLFSK':
							$plastic_sku = 'WLBS24';
							$plastic_step = 24;
							break;

						case 'W19916':
							$plastic_sku = 'W29917';
							$plastic_step = 24;
							break;
					}

					if ( ! $do_not_count_for_crates ) {
						$plastic_product_id = wc_get_product_id_by_sku( $plastic_sku );
						foreach( WC()->cart->get_cart() as $cart_item_key => $values ) {
							if ( $values['product_id'] == $product_item['product_id'] ) {
								// Indien er gratis producten toegevoegd worden, kan het product twee keer voorkomen in het winkelmandje!
								add_matching_plastic_crate( $cart_item_key, $plastic_product_id, $product_item, $plastic_step, $empties_array );
							}
						}
					}

					break;
			}
		}

		return $empties_array;
	}

	// Zorg ervoor dat het basisproduct toch gekocht kan worden als het krat omwille van functie hierboven nog niet toevoegd mag worden
	add_filter( 'wc_force_sell_disallow_no_stock', '__return_false' );
	
	// Check bij de bakken leeggoed of we al aan een volledige set van 6/24 flessen zitten 
	add_filter( 'wc_force_sell_update_quantity', 'update_plastic_empties_quantity', 10, 2 );

	function update_plastic_empties_quantity( $quantity, $empties_item ) {
		// Filter wordt per definitie enkel doorlopen bij het updaten van leeggoed
		$product_item = WC()->cart->get_cart_item( $empties_item['forced_by'] );
		$empties_product = wc_get_product( $empties_item['product_id'] );
		$do_not_count_for_crates = false;

		if ( $empties_product !== false ) {
			$empties_sku = $empties_product->get_sku();
			
			if ( $empties_sku === 'W19916' or $empties_sku === 'W29917' ) {
				// Vermenigvuldig de flesjes bij samengestelde producten (= eleganter dan een extra leeggoedartikel aan te maken)
				// We kunnen dit niet in de switch verderop doen, aangezien ook de berekening voor W29917 deze gemanipuleerde hoeveelheden nodig heeft
				$product = wc_get_product( $product_item['product_id'] );
				if ( $product !== false ) {
					switch ( $product->get_sku() ) {
						case '20807':
						case '20809':
						case '20811':
							// Voeg 4 flesjes leeggoed toe bij clips
							$quantity = 4 * intval( $product_item['quantity'] );
							// OVERRULE OOK PRODUCTHOEVEELHEID MET HET OOG OP ONDERSTAANDE LOGICA
							$product_item['quantity'] = 4 * intval( $product_item['quantity'] );
							break;

						case '19236':
						case '19237':
						case '19238':
						case '19239':
							// Voeg 3 flesjes leeggoed toe bij geschenksets
							$quantity = 3 * intval( $product_item['quantity'] );
							// OVERRULE OOK PRODUCTHOEVEELHEID MET HET OOG OP ONDERSTAANDE LOGICA
							$product_item['quantity'] = 3 * intval( $product_item['quantity'] );
							// Hou met deze flesjes geen rekening bij berekenen van aantal plastic bakken
							$do_not_count_for_crates = true;
							break;
					}
				}
			}
			
			switch ( $empties_sku ) {
				case 'WLBS6':
					$quantity = floor( intval( $product_item['quantity'] ) / 6 );
					break;

				case 'WLBS24':
				case 'W29917':
					// Door round() voegen we automatisch een bak toe vanaf 13 flesjes
					$quantity = round( intval( $product_item['quantity'] ) / 24, 0, PHP_ROUND_HALF_DOWN );
					break;

				case 'WLFSG':
				case 'WLFSK':
				case 'W19916':
					// Definieer de koppelingen tussen glas en plastic
					switch ( $empties_sku ) {
						case 'WLFSG':
							$plastic_sku = 'WLBS6';
							$plastic_step = 6;
							break;

						case 'WLFSK':
							$plastic_sku = 'WLBS24';
							$plastic_step = 24;
							break;

						case 'W19916':
							$plastic_sku = 'W29917';
							$plastic_step = 24;
							break;
					}

					if ( ! $do_not_count_for_crates ) {
						$plastic_product_id = wc_get_product_id_by_sku( $plastic_sku );
						foreach( WC()->cart->get_cart() as $cart_item_key => $values ) {
							if ( $values['product_id'] == $product_item['product_id'] ) {
								add_matching_plastic_crate( $cart_item_key, $plastic_product_id, $product_item, $plastic_step, $empties_item );
							}
						}
					}

					// Reset eventueel met het aantal van het hoofdproduct indien $quantity naar 1 zou terugvallen
					// $quantity = $product_item['quantity'];
					break;
			}
		}

		return $quantity;
	}

	function add_matching_plastic_crate( $product_item_key, $plastic_product_id, $product_item, $plastic_step, $empties_item ) {
		$plastic_in_cart = false;
		foreach( WC()->cart->get_cart() as $cart_item_key => $values ) {
			if ( intval( $values['product_id'] ) === $plastic_product_id and $values['forced_by'] === $product_item_key ) {
				// We hebben een krat gevonden dat gelinkt is aan de fles
				$plastic_in_cart = true;
				break;
			}
		}

		if ( ! $plastic_in_cart and round( intval( $product_item['quantity'] ) / $plastic_step, 0, PHP_ROUND_HALF_DOWN ) >= 1 ) {
			$main_product = wc_get_product( $product_item['product_id'] );
			// Voeg het eerste krat handmatig toe en zorg ervoor dat deze cart_item gelinkt wordt aan het product waaraan de fles al gelinkt was
			$result = WC()->cart->add_to_cart( $plastic_product_id, round( intval( $product_item['quantity'] ) / $plastic_step, 0, PHP_ROUND_HALF_DOWN ), $empties_item['variation_id'], $empties_item['variation'], array( 'forced_by' => $product_item_key ) );
		}
	}

	// Toon bij onzichtbaar leeggoed het woord 'flessen' na het productaantal
	add_filter( 'woocommerce_cart_item_quantity', 'add_bottles_to_quantity', 10, 3 );
	
	function add_bottles_to_quantity( $product_quantity, $cart_item_key, $cart_item ) {
		$product = wc_get_product( $cart_item['product_id'] );
		if ( $product !== false ) {
			if ( in_array( $product->get_sku(), get_oxfam_empties_skus_array() ) ) {
				$qty = intval( $product_quantity );
				switch ( $product->get_sku() ) {
					case 'WLFSK':
					case 'W19916':
						return sprintf( _n( '%d flesje', '%d flesjes', $qty ), $qty );
					case 'WLBS6':
						return sprintf( _n( '%d krat', '%d kratten', $qty ), $qty ).' (per 6 flessen)';
					case 'WLBS24':
					case 'W29917':
						return sprintf( _n( '%d krat', '%d kratten', $qty ), $qty ).' (per 24 flesjes)';
					default:
						return sprintf( _n( '%d fles', '%d flessen', $qty ), $qty );
				}
			}
		}

		return $product_quantity;
	}

	// Zet leeggoed en cadeauverpakking onderaan
	add_action( 'woocommerce_cart_loaded_from_session', 'reorder_cart_items' );

	function reorder_cart_items( $cart ) {
		// Niets doen bij leeg winkelmandje
		if ( empty( $cart->cart_contents ) ) {
			return;
		}

		$cart_sorted = $cart->cart_contents;
		$glass_items = array();
		$plastic_items = array();

		foreach ( $cart->cart_contents as $cart_item_key => $cart_item ) {
			if ( ob2c_product_is_gift_wrapper( $cart_item ) ) {
				// Sla het item van de cadeauverpakking op en verwijder het
				$gift_item = $cart_item;
				unset( $cart_sorted[ $cart_item_key ] );
			}

			if ( strpos( $cart_item['data']->get_sku(), 'WLF' ) === 0 or $cart_item['data']->get_sku() === 'W19916' ) {
				$glass_items[ $cart_item_key ] = $cart_item;
				unset( $cart_sorted[ $cart_item_key ] );
			}

			if ( strpos( $cart_item['data']->get_sku(), 'WLB' ) === 0 or $cart_item['data']->get_sku() === 'W29917' ) {
				$plastic_items[ $cart_item_key ] = $cart_item;
				unset( $cart_sorted[ $cart_item_key ] );
			}
		}

		$cart_sorted = array_merge( $cart_sorted, $glass_items, $plastic_items );

		if ( isset( $gift_item ) ) {
			// Voeg de cadeauverpakking opnieuw toe helemaal achteraan (indien het voorkwam)
			$cart_sorted[ $cart_item_key ] = $gift_item;
		}

		// Vervang de itemlijst door de nieuwe array
		$cart->set_cart_contents( $cart_sorted );
	}

	// Toon leeggoed niet in de mini-cart (maar wordt wel meegeteld in subtotaal!)
	add_filter( 'woocommerce_widget_cart_item_visible', 'hide_empties_in_mini_cart', 10, 3 );

	function hide_empties_in_mini_cart( $visible, $cart_item, $cart_item_key ) {
		if ( in_array( $cart_item['data']->get_sku(), get_oxfam_empties_skus_array() ) ) {
			$visible = false;
		} elseif ( ob2c_product_is_gift_wrapper( $cart_item ) ) {
			// Zichtbaar laten zolang we 'woocommerce_cart_contents_count' niet aanpassen
			// $visible = false;
		}
		return $visible;
	}

	// Tel leeggoed niet mee bij aantal items in winkelmandje
	add_filter( 'woocommerce_cart_contents_count', 'exclude_empties_from_cart_count' );
	
	function exclude_empties_from_cart_count( $count ) {
		$cart = WC()->cart->get_cart();
		
		$subtract = 0;
		foreach ( $cart as $key => $value ) {
			if ( isset( $value['forced_by'] ) ) {
				$subtract += $value['quantity'];
			}
		}

		return $count - $subtract;
	}

	// Toon het totaalbedrag van al het leeggoed onderaan
	// add_action( 'woocommerce_widget_shopping_cart_before_buttons', 'show_empties_subtotal' );

	function show_empties_subtotal() {
		echo 'waarvan XX euro leeggoed';
	}

	// Vermijd dat leeggoedlijnen meegekopieerd worden vanuit een vorige bestelling (zonder juiste koppeling met moederproduct)
	// TRIGGEREN VAN WC FORCE SELLS ACHTERAF LUKT NIET
	// add_filter( 'woocommerce_add_order_again_cart_item', 'prevent_empties_from_being_copied', 10, 2 );

	function prevent_empties_from_being_copied( $cart_item_data, $cart_id ) {
		if ( $cart_item_data['data'] !== false ) {
			// Eventueel ook gratis producten annuleren?
			if ( $cart_item_data['data']->get_catalog_visibility() === 'hidden' ) {
				write_log("CANCEL ADDING EMPTIES");
				write_log( print_r( $cart_item_data, true ) );
				// We zijn nog op tijd om het toevoegen te annuleren
				$cart_item_data['quantity'] = 0;
			}
		}

		return $cart_item_data;
	}
	


	############
	# SETTINGS #
	############

	// Voeg optievelden toe
	add_action( 'admin_init', 'register_oxfam_settings' );

	// Let op: $option_group = $page in de oude documentatie!
	function register_oxfam_settings() {
		register_setting( 'oxfam-options-global', 'oxfam_shop_post_id', array( 'type' => 'integer', 'sanitize_callback' => 'absint' ) );
		register_setting( 'oxfam-options-global', 'oxfam_mollie_partner_id', array( 'type' => 'integer', 'sanitize_callback' => 'absint' ) );
		register_setting( 'oxfam-options-global', 'oxfam_zip_codes', array( 'type' => 'array', 'sanitize_callback' => 'comma_string_to_numeric_array' ) );
		register_setting( 'oxfam-options-global', 'oxfam_member_shops', array( 'type' => 'array', 'sanitize_callback' => 'comma_string_to_array' ) );
		// We geven hier bewust geen defaultwaarde mee, aangezien die in de front-end toch niet geïnterpreteerd wordt ('admin_init')
		register_setting( 'oxfam-options-local', 'oxfam_minimum_free_delivery', array( 'type' => 'integer', 'sanitize_callback' => 'absint' ) );
		register_setting( 'oxfam-options-local', 'oxfam_does_risky_delivery', array( 'type' => 'boolean' ) );
		register_setting( 'oxfam-options-local', 'oxfam_sitewide_banner_top', array( 'type' => 'string', 'sanitize_callback' => 'clean_banner_text' ) );
		register_setting( 'oxfam-options-local', 'oxfam_disable_local_pickup', array( 'type' => 'boolean' ) );
		register_setting( 'oxfam-options-local', 'oxfam_remove_excel_header', array( 'type' => 'boolean' ) );
		register_setting( 'oxfam-options-local', 'oxfam_b2b_invitation_text', array( 'type' => 'string', 'sanitize_callback' => 'clean_banner_text' ) );
		// register_setting( 'oxfam-options-local', 'oxfam_b2b_delivery_enabled', array( 'type' => 'boolean' ) );
		// register_setting( 'oxfam-options-local', 'oxfam_holidays', array( 'type' => 'array', 'sanitize_callback' => 'comma_string_to_array' ) );
	}

	// Zorg ervoor dat je lokale opties ook zonder 'manage_options'-rechten opgeslagen kunnen worden
	add_filter( 'option_page_capability_oxfam-options-local', 'lower_manage_options_capability' );
	
	function lower_manage_options_capability( $cap ) {
		return 'manage_woocommerce';
	}

	function comma_string_to_array( $values ) {
		$values = preg_replace( '/\s/', '', $values );
		$values = preg_replace( '/\//', '-', $values );
		$array = (array) preg_split( '/(,|;|&)/', $values, -1, PREG_SPLIT_NO_EMPTY );

		foreach ( $array as $key => $value ) {
			$array[$key] = mb_strtolower( trim($value) );
			// Verwijder datums uit het verleden (woorden van toevallig 10 tekens kunnen niet voor een datum komen!)
			if ( strlen( $array[$key] ) === 10 and $array[$key] < date_i18n('Y-m-d') ) {
				unset( $array[$key] );
			}
		}
		return $array;
	}

	function comma_string_to_numeric_array( $values ) {
		$values = preg_replace( '/\s/', '', $values );
		$values = preg_replace( '/\//', '-', $values );
		$array = (array) preg_split( '/(,|;|&)/', $values, -1, PREG_SPLIT_NO_EMPTY );

		foreach ( $array as $key => $value ) {
			$array[ $key ] = intval( $value );
		}
		sort( $array, SORT_NUMERIC );
		return $array;
	}

	function clean_banner_text( $text ) {
		// Verwijdert ook overtallige witruimte (2de parameter)
		return wp_strip_all_tags( $text, true );
	}

	add_action( 'update_option_oxfam_minimum_free_delivery', 'update_shipping_methods_free_delivery', 10, 3 );

	function update_shipping_methods_free_delivery( $old_min_amount, $new_min_amount, $option ) {
		// Bij Regio Hasselt en Zele hangt het minimumbedrag af van de postcode
		// Ook als het veld verborgen is, wordt de waarde bijgewerkt!
		// Custom instellingen niet overschrijven met één universeel bedrag
		if ( in_array( get_current_blog_id(), array( 27, 38 ) ) ) {
			return;
		}

		$updated = false;
		$shipping_methods = array(
			'free_delivery_by_shop' => 'free_shipping_1',
			'free_delivery_by_eco' => 'free_shipping_3',
			'free_delivery_by_bpost' => 'free_shipping_5',
			'bpack_delivery_by_bpost' => 'flat_rate_7',
		);

		foreach ( $shipping_methods as $name => $key ) {
			// Laad de juiste optie
			if ( $name === 'bpack_delivery_by_bpost' ) {
				$option_key = 'sendcloudshipping_service_point_shipping_method_7_settings';
			} else {
				$option_key = 'woocommerce_'.$key.'_settings';
			}
			
			$settings = get_option( $option_key );
			if ( is_array( $settings ) ) {
				if ( in_array( $name, array( 'free_delivery_by_shop', 'free_delivery_by_eco', 'free_delivery_by_bpost', 'bpack_delivery_by_bpost' ) ) ) {
					if ( $name === 'bpack_delivery_by_bpost' ) {
						if ( array_key_exists( 'free_shipping_min_amount', $settings ) ) {
							$settings['free_shipping_min_amount'] = $new_min_amount;
						}
					} else {
						if ( array_key_exists( 'min_amount', $settings ) ) {
							$settings['min_amount'] = $new_min_amount;
						}
					}
				}

				if ( update_option( $option_key, $settings ) ) {
					$updated = true;
				}
			}
		}

		if ( $updated ) {
			send_automated_mail_to_helpdesk( get_webshop_name(true).' wijzigde de limiet voor gratis verzending', '<p>Alle thuisleveringen zijn nu gratis vanaf '.$new_min_amount.' euro!</p>' );
		} 
	}

	add_action( 'update_option_oxfam_disable_local_pickup', 'oxfam_disable_local_pickup', 10, 3 );

	function oxfam_disable_local_pickup( $old_value, $new_value, $option ) {
		$updated = false;
		
		$option_key = 'woocommerce_local_pickup_plus_settings';
		$settings = get_option( $option_key );
		if ( is_array( $settings ) ) {
			if ( $new_value !== 'yes' ) {
				$new_value = 'no';
			}
			$settings['enabled'] = $new_value;

			if ( update_option( $option_key, $settings ) ) {
				$updated = true;
			}
		}

		if ( $updated ) {
			send_automated_mail_to_helpdesk( get_webshop_name(true).' schakelde afhaling in de winkel uit', '<p>Vanaf nu kunnen klanten enkel nog opteren voor thuislevering in '.implode( ', ', get_oxfam_covered_zips() ).'!</p>' );
		}
	}

	add_action( 'update_option_oxfam_does_risky_delivery', 'oxfam_boolean_field_option_was_updated', 10, 3 );
	add_action( 'update_option_oxfam_disable_local_pickup', 'oxfam_boolean_field_option_was_updated', 10, 3 );
	add_action( 'update_option_oxfam_remove_excel_header', 'oxfam_boolean_field_option_was_updated', 10, 3 );

	function oxfam_boolean_field_option_was_updated( $old_value, $new_value, $option ) {
		$body = false;

		// Actie wordt enkel doorlopen indien oude en nieuwe waarde verschillen, dus geen extra check nodig
		if ( $new_value == 'yes' ) {
			$body = 'ingeschakeld';
		} elseif ( $old_value !== 'no' and $new_value === '' ) {
			$body = 'uitgeschakeld';
		}

		if ( $body ) {
			send_automated_mail_to_helpdesk( get_webshop_name(true).' paste \''.$option.'\'-vinkje aan', '<p>Nieuwe waarde: '.$body.'</p>' );
		}
	}

	add_action( 'add_option_oxfam_sitewide_banner_top', 'oxfam_text_field_option_was_created', 10, 2 );
	add_action( 'update_option_oxfam_sitewide_banner_top', 'oxfam_text_field_option_was_updated', 10, 3 );
	add_action( 'add_option_oxfam_b2b_invitation_text', 'oxfam_text_field_option_was_created', 10, 2 );
	add_action( 'update_option_oxfam_b2b_invitation_text', 'oxfam_text_field_option_was_updated', 10, 3 );

	function oxfam_text_field_option_was_created( $option, $new_text ) {
		if ( strlen( $new_text ) > 0 ) {
			$body = '"'.$new_text.'"';
		}
		send_automated_mail_to_helpdesk( get_webshop_name(true).' paste \''.$option.'\'-tekst aan', '<p>'.$body.'</p>' );
	}

	function oxfam_text_field_option_was_updated( $old_text, $new_text, $option ) {
		if ( strlen( $new_text ) > 0 ) {
			$body = '"'.$new_text.'"';
		} else {
			$body = 'Custom \''.$option.'\'-tekst gewist!';
		}
		send_automated_mail_to_helpdesk( get_webshop_name(true).' paste \''.$option.'\'-tekst aan', '<p>'.$body.'</p>' );
	}

	// Voeg een custom pagina toe onder de algemene opties
	add_action( 'admin_menu', 'custom_oxfam_options' );

	function custom_oxfam_options() {
		add_menu_page( 'Stel de voorraad van je lokale webshop in', 'Voorraadbeheer', 'manage_network_users', 'oxfam-products-list', 'oxfam_products_list_callback', 'dashicons-admin-settings', '56' );
		// add_submenu_page( 'oxfam-products-list', 'Stel de voorraad van je lokale webshop in', 'Lijstweergave', 'manage_network_users', 'oxfam-products-list', 'oxfam_products_list_callback' );
		// add_submenu_page( 'oxfam-products-list', 'Stel de voorraad van je lokale webshop in', 'Fotoweergave', 'manage_network_users', 'oxfam-products-photos', 'oxfam_products_photos_callback' );
		add_menu_page( 'Handige gegevens voor je lokale webshop', 'Winkelgegevens', 'manage_network_users', 'oxfam-options', 'oxfam_options_callback', 'dashicons-megaphone', '58' );
		if ( is_main_site() ) {
			add_media_page( 'Productfoto\'s', 'Productfoto\'s', 'create_sites', 'oxfam-photos', 'oxfam_photos_callback' );
		}
	}

	function oxfam_photos_callback() {
		include get_stylesheet_directory().'/register-bulk-images.php';
	}

	function oxfam_options_callback() {
		include get_stylesheet_directory().'/update-options.php';
	}

	function oxfam_products_photos_callback() {
		include get_stylesheet_directory().'/update-stock-photos.php';
	}

	function oxfam_products_list_callback() {
		include get_stylesheet_directory().'/update-stock-list.php';
	}

	// Vervang onnutige links in netwerkmenu door Oxfam-pagina's
	add_action( 'wp_before_admin_bar_render', 'oxfam_admin_bar_render' );

	function oxfam_admin_bar_render() {
		global $wp_admin_bar;
		if ( current_user_can('create_sites') ) {
			$sites = get_sites( array( 'public' => 1 ) );
			// write_log( print_r( $wp_admin_bar->get_nodes(), true ) );
			foreach ( $sites as $site ) {
				$node_d = $wp_admin_bar->get_node('blog-'.$site->blog_id.'-d');
				if ( $node_d ) {
					$new_node = $node_d;
					$wp_admin_bar->remove_node('blog-'.$site->blog_id.'-d');
					$new_node->title = 'Bestellingen';
					$new_node->href = get_site_url( $site->blog_id, '/wp-admin/edit.php?post_type=shop_order' );
					$wp_admin_bar->add_node( $new_node );
				}
				$node_n = $wp_admin_bar->get_node('blog-'.$site->blog_id.'-n');
				if ( $node_n ) {
					$new_node = $node_n;
					$wp_admin_bar->remove_node('blog-'.$site->blog_id.'-n');
					$new_node->title = 'Winkelgegevens';
					$new_node->href = get_site_url( $site->blog_id, '/wp-admin/admin.php?page=oxfam-options' );
					$wp_admin_bar->add_node( $new_node );
				}
				$node_v = $wp_admin_bar->get_node('blog-'.$site->blog_id.'-v');
				if ( $node_v ) {
					$new_node = $node_v;
					$wp_admin_bar->remove_node('blog-'.$site->blog_id.'-v');
					$new_node->title = 'Voorraadbeheer';
					$new_node->href = get_site_url( $site->blog_id, '/wp-admin/admin.php?page=oxfam-products-list' );
					$wp_admin_bar->add_node( $new_node );
				}
			}
		}
	}

	// Voeg handige links toe aan sitelijst
	add_filter( 'manage_sites_action_links', 'oxfam_sites_list_render', 10, 3 );

	function oxfam_sites_list_render( $actions, $blog_id, $blogname ) {
		unset( $actions['edit'] );
		unset( $actions['spam'] );
		unset( $actions['visit'] );
		unset( $actions['clone'] );
		
		$actions['orders'] = '<a href="'.get_site_url( $blog_id, '/wp-admin/edit.php?post_type=shop_order' ).'">Bestellingen</a>';
		$actions['settings'] = '<a href="'.get_site_url( $blog_id, '/wp-admin/admin.php?page=oxfam-options' ).'">Winkelgegevens</a>';
		$actions['stock'] = '<a href="'.get_site_url( $blog_id, '/wp-admin/admin.php?page=oxfam-products-list' ).'">Voorraadbeheer</a>';
		
		return $actions;
	}

	// Registreer de AJAX-acties
	add_action( 'wp_ajax_oxfam_stock_action', 'oxfam_stock_action_callback' );
	add_action( 'wp_ajax_oxfam_bulk_stock_action', 'oxfam_bulk_stock_action_callback' );
	add_action( 'wp_ajax_oxfam_photo_action', 'oxfam_photo_action_callback' );
	add_action( 'wp_ajax_oxfam_invitation_action', 'oxfam_invitation_action_callback' );

	function oxfam_stock_action_callback() {
		echo ob2c_save_local_product_details( $_POST['id'], $_POST['meta'], $_POST['value'] );
		wp_die();
	}

	function oxfam_bulk_stock_action_callback() {
		echo ob2c_change_regular_products_stock_status( $_POST['status'] );
		wp_die();
	}

	function ob2c_save_local_product_details( $product_id, $meta, $value ) {			
		$output = 'ERROR';
		
		$product = wc_get_product( $product_id );
		if ( $product ) {
			if ( $meta === 'stockstatus' ) {
				$product->set_stock_status( $value );
				$message = 'Voorraadstatus opgeslagen!';
			} elseif ( $meta === 'featured' ) {
				$product->set_featured($value);
				$message = 'Uitlichting opgeslagen!';
			}
			
			if ( $product->save() ) {
				$output = $message;
			}
		}
		
		return $output;
	}

	function ob2c_change_regular_products_stock_status( $status ) {			
		if ( ! array_key_exists( $status, wc_get_product_stock_status_options() ) ) {
			return 'ERROR - INVALID STOCK STATUS PASSED';
		}

		$output = 'ERROR';
		
		// Query alle gepubliceerde producten, orden op ompaknummer
		$args = array(
			'post_type'			=> 'product',
			'post_status'		=> array('publish'),
			'posts_per_page'	=> -1,
			'meta_key'			=> '_sku',
			'orderby'			=> 'meta_value_num',
			'order'				=> 'ASC',
		);
		$products = new WP_Query( $args );

		if ( $products->have_posts() ) {
			$i = 0;
			$empties = get_oxfam_empties_skus_array();
			
			while ( $products->have_posts() ) {
				$products->the_post();
				$product = wc_get_product( get_the_ID() );
				
				// Verhinder dat leeggoed ook bewerkt wordt
				if ( $product === false or in_array( $product->get_sku(), $empties ) ) {
					continue;
				}

				if ( $product->get_stock_status() !== $status ) {
					$product->set_stock_status( $status );
					if ( $product->save() ) {
						$i++;
					}
				}
			}
			wp_reset_postdata();

			$output = $i.' voorraadstatussen bijgewerkt!';
		} else {
			$output = 'ERROR - NO PRODUCTS FOUND';
		}
		
		write_log( $output );
		return $output;
	}

	function oxfam_photo_action_callback() {
		// Wordt standaard op ID geordend, dus creatie op hoofdsite gebeurt als eerste (= noodzakelijk!)
		echo register_photo( $_POST['name'], $_POST['timestamp'], $_POST['path'] );
		wp_die();
	}

	function oxfam_invitation_action_callback() {
		$new_account_path = get_stylesheet_directory() . '/woocommerce/emails/customer-new-account.php';
		$reset_password_path = get_stylesheet_directory() . '/woocommerce/emails/customer-reset-password.php';
		$temporary_path = get_stylesheet_directory() . '/woocommerce/emails/temporary.php';
		// Beter: check of $reset_password_path wel bestaat (= template werd overschreven)
		rename( $reset_password_path, $temporary_path );
		rename( $new_account_path, $reset_password_path );
		$user = get_user_by( 'id', $_POST['customer_id'] );
		if ( retrieve_password_for_customer( $user ) ) {
			printf( 'Succesvol uitgenodigd, kopie verstuurd naar %s!', get_webshop_email() );
			update_user_meta( $user->ID, 'blog_'.get_current_blog_id().'_b2b_invitation_sent', current_time('mysql') );
		} else {
			printf( 'Uitnodigen eigenaar \'%s\' mislukt, herlaad pagina en probeer eens opnieuw!', $user->user_login );
		}
		rename( $reset_password_path, $new_account_path );
		rename( $temporary_path, $reset_password_path );
		wp_die();
	}

	// Laat de wachtwoordlinks in de resetmails langer leven dan 1 dag (= standaard)
	add_filter( 'password_reset_expiration', function( $expiration ) {
		return 2 * WEEK_IN_SECONDS;
	});

	function oxfam_get_attachment_ids_by_file_name( $file_name ) {
		$args = array(
			// Beperkte ondersteuning voor meerdere foto's (er kan al eens een foto dubbel geüpload zijn met index of 'scaled' achteraan)
			'posts_per_page' => 5,
			'post_type'	=> 'attachment',
			// Default wordt 'publish' gebruikt en die bestaat niet voor attachments!
			'post_status' => 'inherit',
			// Zoek op basis van het gekoppelde bestand i.p.v. titel (kan nadien gewijzigd zijn door import)
			'meta_key' => '_wp_attached_file',
			// Eventueel kunnen we naar een specifieke extensie zoeken
			'meta_value' => trim( $file_name ),
			'meta_compare' => 'LIKE',
			'fields' => 'ids',
		);

		$attachments = new WP_Query( $args );
		return $attachments->posts;
	}

	function register_photo( $filename, $filestamp, $main_filepath ) {			
		// Parse de fototitel
		$filetitle = explode( '.jpg', $filename );
		$filetitle = $filetitle[0];
		$product_id = 0;

		// Check of er al een vorige versie bestaat
		$updated = false;
		$deleted = false;
		$old_photo_ids = oxfam_get_attachment_ids_by_file_name( $filetitle );
		if ( count( $old_photo_ids ) > 0 ) {
			$old_photo_id = reset( $old_photo_ids );
			// Bewaar de post_parent van het originele attachment
			$product_id = wp_get_post_parent_id( $old_photo_id );
			// Check of de uploadlocatie op dit punt al ingegeven is!
			if ( $product_id ) {
				$product = wc_get_product( $product_id );
			}

			// Stel het originele high-res bestand veilig
			rename( $main_filepath, WP_CONTENT_DIR.'/uploads/temporary.jpg' );
			// Verwijder de geregistreerde foto (en alle aangemaakte thumbnails!)
			if ( wp_delete_attachment( $old_photo_id, true ) ) {
				// Extra check op het succesvol verwijderen
				$deleted = true;
			}
			$updated = true;
			// Hernoem opnieuw zodat de links weer naar de juiste file wijzen 
			rename( WP_CONTENT_DIR.'/uploads/temporary.jpg', $main_filepath );
		}
		
		// Creëer de parameters voor de foto
		$wp_filetype = wp_check_filetype( $filename, NULL );
		$attachment = array(
			'post_mime_type' => $wp_filetype['type'],
			'post_title' => $filetitle,
			'post_content' => '',
			'post_author' => get_current_user_id(),
			'post_status' => 'inherit',
		);

		// Probeer de foto in de mediabibliotheek te stoppen
		// Laatste argument: stel de uploadlocatie van de nieuwe afbeelding in op het product van het origineel (of 0 = geen)
		$attachment_id = wp_insert_attachment( $attachment, $main_filepath, $product_id );
		
		if ( ! is_wp_error( $attachment_id ) ) {
			// Check of de uploadlocatie ingegeven was!
			if ( ! isset($product_id) ) {
				// Indien het product nog niet bestaat zal de search naar een 0 opleveren
				$product_id = wc_get_product_id_by_sku( $filetitle );
			}

			if ( $product_id > 0 ) {
				// Voeg de nieuwe attachment-ID weer toe aan het oorspronkelijke product
				$product->set_image_id( $attachment_id );
				$product->save();

				// Stel de uploadlocatie van de nieuwe afbeelding in
				wp_update_post(
					array(
						'ID' => $attachment_id, 
						'post_parent' => $product_id,
					)
				);

				// UPDATE EVENTUEEL OOK NOG DE _WOONET_IMAGES_MAPPING VAN HET PRODUCT MET DEZE SKU M.B.V. DE BROADCAST SNIPPET
				// WANT ANDERS WORDT _THUMBNAIL_ID BIJ DE EERSTVOLGENDE ERP-IMPORT WEER OVERSCHREVEN MET DE OUDE FOTO-ID
			}

			// Registreer ook de metadata
			$attachment_data = wp_generate_attachment_metadata( $attachment_id, $main_filepath );
			wp_update_attachment_metadata( $attachment_id,  $attachment_data );
			// Toon een succesboodschap
			if ( $updated ) {
				$deleted = $deleted ? "verwijderd en opnieuw aangemaakt" : "bijgewerkt";
				$msg = "<i>".$filename."</i> ".$deleted." in mediabibliotheek van site-ID ".get_current_blog_id()." om ".date_i18n('H:i:s')." ...<br/>";
			} else {
				$msg = "<i>".$filename."</i> aangemaakt in mediabibliotheek van site-ID ".get_current_blog_id()." om ".date_i18n('H:i:s')." ...<br/>";
			}
			// Sla het uploadtijdstip van de laatste succesvolle registratie op (indien recenter dan huidige optiewaarde)
			if ( $filestamp > get_option( 'laatste_registratie_timestamp' ) ) {
				update_option( 'laatste_registratie_timestamp', $filestamp );
			}
			$registered = true;
		} else {
			// Geef een waarschuwing als de aanmaak mislukte
			$msg = "Opgelet, er liep iets mis met <i>".$filename."</i>!<br/>";
		}

		return $msg;
	}

	function get_scaled_image_path( $attachment_id, $size = 'full' ) {
		$file = get_attached_file( $attachment_id, true );
		if ( $size === 'full' ) return realpath($file);
		
		$info = image_get_intermediate_size( $attachment_id, $size );
		if ( ! is_array($info) or ! isset($info['file']) ) return false;
		
		return realpath( str_replace( wp_basename($file), $info['file'], $file ) );
	}

	function retrieve_password_for_customer( $user ) {
		// Creëer een key en sla ze op in de 'users'-tabel
		$key = get_password_reset_key($user);

		// Verstuur de e-mail met de speciale link
		WC()->mailer();
		do_action( 'woocommerce_reset_password_notification', $user->user_login, $key );

		return true;
	}

	// Creëer een custom hiërarchische taxonomie op producten om partner/landinfo in op te slaan
	add_action( 'init', 'register_partner_taxonomy', 0 );
	
	function register_partner_taxonomy() {
		$taxonomy_name = 'product_partner';
		
		$labels = array(
			'name' => 'Partners',
			'singular_name' => 'Partner',
			'all_items' => 'Alle partners',
			'parent_item' => 'Land',
			'parent_item_colon' => 'Land:',
			'new_item_name' => 'Nieuwe partner',
			'add_new_item' => 'Voeg nieuwe partner toe',
		);

		$args = array(
			'labels' => $labels,
			'description' => 'Ken het product toe aan een partner/land',
			'public' => true,
			'publicly_queryable' => true,
			'hierarchical' => true,
			'show_ui' => true,
			'show_in_menu' => true,
			'show_in_nav_menus' => true,
			'show_in_rest' => true,
			'show_tagcloud' => true,
			'show_in_quick_edit' => false,
			'show_admin_column' => true,
			'query_var' => true,
			'capabilities' => array( 'manage_terms' => 'create_sites', 'edit_terms' => 'create_sites', 'delete_terms' => 'create_sites', 'assign_terms' => 'edit_products' ),
			'rewrite' => array( 'slug' => 'partner', 'with_front' => false, 'ep_mask' => 'test' ),
		);

		register_taxonomy( $taxonomy_name, 'product', $args );
		register_taxonomy_for_object_type( $taxonomy_name, 'product' );
	}

	// Maak onze custom taxonomiën beschikbaar in menu editor
	add_filter( 'woocommerce_attribute_show_in_nav_menus', 'register_custom_taxonomies_for_menus', 1, 2 );

	function register_custom_taxonomies_for_menus( $register, $name = '' ) {
		return true;
	}

	// Vermijd dat geselecteerde termen in hiërarchische taxonomieën naar boven springen
	add_filter( 'wp_terms_checklist_args', 'do_not_jump_to_top', 10, 2 );

	function do_not_jump_to_top( $args, $post_id ) {
		if ( is_admin() ) {
			$args['checked_ontop'] = false;
		}
		return $args;
	}

	// Retourneert een array met strings van landen waaruit dit product afkomstig is (en anders false)
	function get_countries_by_product( $product ) {
		$terms = get_the_terms( $product->get_id(), 'product_partner' );
		$args = array( 'taxonomy' => 'product_partner', 'parent' => 0, 'hide_empty' => false, 'fields' => 'ids' );
		$continents = get_terms( $args );
		
		if ( count($terms) > 0 ) {
			foreach ( $terms as $term ) {
				if ( ! in_array( $term->parent, $continents, true ) ) {
					// De bovenliggende term is geen continent, dus het is een partner!
					$parent_term = get_term_by( 'id', $term->parent, 'product_partner' );
					// Voeg de naam van de bovenliggende term (= land) toe aan het lijstje
					$countries[] = $parent_term->name;
				} else {
					// In dit geval is het zeker een land (en zeker geen continent zijn want checkboxes uitgeschakeld + enkel gelinkt aan laagste term)
					$countries[] = $term->name;
				}
			}
			// Ontdubbel de landen en sorteer values alfabetisch
			$countries = array_unique( $countries );
			sort( $countries, SORT_STRING );
		} else {
			// Fallback indien nog geen herkomstinfo bekend
			$countries = false;
		}
		
		return $countries;
	}

	// Retourneert een array term_id => name van de partners die bijdragen aan het product
	function get_partner_terms_by_product( $product ) {
		// Vraag alle partnertermen op die gelinkt zijn aan dit product (helaas geen filterargumenten beschikbaar)
		// Producten worden door de import enkel aan de laagste hiërarchische term gelinkt, dus dit zijn per definitie landen of partners!
		$terms = get_the_terms( $product->get_id(), 'product_partner' );
		
		// Vraag de term-ID's van de continenten in deze site op
		$args = array( 'taxonomy' => 'product_partner', 'parent' => 0, 'hide_empty' => false, 'fields' => 'ids' );
		$continents = get_terms( $args );
		$partners = array();
		
		if ( is_array($terms) and count($terms) > 0 ) {
			foreach ( $terms as $term ) {
				if ( ! in_array( $term->parent, $continents, true ) ) {
					// De bovenliggende term is geen continent, dus het is een partner!
					$partners[$term->term_id] = $term->name;
				}
			}
		}

		// Sorteer alfabetisch op value (= partnernaam) maar bewaar de index (= term-ID)
		asort($partners);
		return $partners;
	}

	// Retourneert zo veel mogelijk beschikbare info bij een partner (enkel naam en land steeds ingesteld!)
	function get_info_by_partner( $partner ) {
		$partner_info['name'] = $partner->name;
		// Zit ook in de API, maar die bevat enkel A/B-partners
		$partner_info['country'] = get_term_by( 'id', $partner->parent, 'product_partner' )->name;
		$partner_info['archive'] = get_term_link( $partner->term_id );
		
		if ( strlen( $partner->description ) > 20 ) {
			// Check of er een link naar een partnerpagina aanwezig is
			$parts = explode( '/partners/', $partner->description );
			if ( count( $parts ) >= 2 ) {
				// Knip alles weg na de eindslash van de URL
				$slugs = explode( '/', $parts[1] );
				// Fallback: knip alles weg na de afsluitende dubbele quote van het href-attribuut
				$slugs = explode( '"', $slugs[0] );
				$partner_info = array_merge( $partner_info, get_external_partner( $slugs[0] ) );
			} else {
				// Fallback: zet de naam van de partner om in een slug
				$partner_info = array_merge( $partner_info, get_external_partner( $partner->name ) );
			}
		}

		return $partner_info;
	}

	// Verwijder sterren
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 5 );
	
	// Plaats kort beschrijving hoger (+ zodat ze ook in de linkerkolom belandt op tablet, blijkbaar alles t.e.m. prioriteit 15)
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
	add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 8 );

	// Wat is dit?
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50 );
	
	// Toon een boodschap op de detailpagina indien het product niet thuisgeleverd wordt
	// Icoontje wordt toegevoegd op basis van CSS-klasse .product_shipping_class-breekbaar
	add_action( 'woocommerce_single_product_summary', 'show_delivery_warning', 9 );

	function show_delivery_warning() {
		global $product;
		$output = '';
		$cat_ids = $product->get_category_ids();
		if ( count( $cat_ids ) > 0 ) {
			$parent_id = get_term( $cat_ids[0], 'product_cat' )->parent;
			
			if ( get_term( $cat_ids[0], 'product_cat' )->slug === 'spirits' or get_term( $cat_ids[0], 'product_cat' )->slug === 'bier' or get_term( $parent_id, 'product_cat' )->slug === 'wijn' ) {
				$output = 'Ons vakmanschap drink je met verstand! Je dient minstens 18 jaar oud te zijn om dit alcoholische product te bestellen. ';
			}

			if ( ! is_main_site() ) {
				if ( ! is_b2b_customer() and ! does_risky_delivery() and $product->get_shipping_class() === 'breekbaar' ) {
					$output .= 'Opgelet: dit product kan enkel afgehaald worden in de winkel! ';
					if ( get_term( $cat_ids[0], 'product_cat' )->slug === 'bier' ) {
						$output .= 'Tip: losse bierflesjes zijn wel beschikbaar voor thuislevering.';
					}
					if ( get_term( $parent_id, 'product_cat' )->slug === 'fruitsap' ) {
						$output .= 'Tip: tetrabrikken en kleine sapflesjes zijn wel beschikbaar voor thuislevering.';
					}
				}
			}
		}

		if ( $output !== '' ) {
			echo '<p class="wettelijke-info">'.$output.'</p>';
		}
	}

	// Promoties net boven de winkelmandknop tonen
	add_action( 'woocommerce_single_product_summary', 'show_active_promos', 7 );

	function show_active_promos() {
		global $product;
		if ( ! is_b2b_customer() ) {
			// Opgelet: nu verbergen we alle promotekstjes voor B2B-klanten, ook indien er een coupon met 'b2b' aangemaakt zou zijn
			if ( $product->is_on_sale() and $product->get_meta('promo_text') !== '' ) {
				echo '<p class="promotie">';
					echo $product->get_meta('promo_text').' Geldig t.e.m. '.$product->get_date_on_sale_to()->date_i18n('l j F Y').'. <a class="dashicons dashicons-editor-help tooltip" title="Niet cumuleerbaar met andere acties. Niet van toepassing bij verkoop op factuur."></a>';
				echo '</p>';
			}
		}
	}

	// Formatteer de gewichten in de attributen DEPRECATED
	// add_filter( 'woocommerce_attribute', 'add_suffixes', 10, 3 );

	function add_suffixes( $wpautop, $attribute, $values ) {
		global $product;
		$eh = $product->get_meta('_stat_uom');
		if ( $eh === 'L' ) {
			$suffix = 'liter';
		} elseif ( $eh === 'KG' ) {
			$suffix = 'kilogram';
		}

		$percenty_attributes = array('pa_fairtrade');

		if ( in_array( $attribute['name'], $percenty_attributes ) ) {
			$values[0] = number_format( str_replace( ',', '.', $values[0] ), 1, ',', '.' ).' %';
		}

		$wpautop = wpautop( wptexturize( implode( ', ', $values ) ) );
		return $wpautop;
	}



	#############
	# MULTISITE #
	#############

	// Pas de opties van WooMultistore eenvoudig aan voor alle subsites
	// add_filter( 'woo_mstore/options/options_save', 'change_woo_mstore_options_in_bulk' );

	function change_woo_mstore_options_in_bulk( $options ) {
		foreach ( $options['child_inherit_changes_fields_control__product_image'] as $key => $value ) {
			$options['child_inherit_changes_fields_control__product_image'][ $key ] = 'no';
			$options['child_inherit_changes_fields_control__product_gallery'][ $key ] = 'no';
			// $options['child_inherit_changes_fields_control__upsell'][ $key ] = 'yes';
			// $options['child_inherit_changes_fields_control__cross_sells'][ $key ] = 'yes';
			// Onderstaande opties ontbreken in settings-page.php, dus onzichtbaar
			$options['child_inherit_changes_fields_control__featured'][ $key ] = 'no';
			$options['child_inherit_changes_fields_control__shipping_class'][ $key ] = 'yes';
		}
		write_log( print_r( $options, true ) );
		return $options;
	}

	// Verhinder dat de lokale voorraad overschreven wordt bij elke update ZIT NU IN PRINCIPE NETJES IN INSTELLING
	// add_filter( 'WOO_MSTORE_admin_product/slave_product_meta_to_exclude', 'do_not_overwrite_local_stock', 10, 2 );

	function do_not_overwrite_local_stock( $meta_keys, $data ) {
		/**
		 * @param array $meta_keys    Metadata keys to exclude from slave product
		 * @param array $data array(
		 *      WC_Product  master_product              Master product
		 *      array       master_product_attributes   Master product attributes
		 *      integer     master_product_blog_id      Master product blog ID
		 *      array       master_product_terms        Master product terms
		 *      array       master_product_upload_dir   Master product uploads directory information ( see wp_get_upload_dir() )
		 *      array       options                     Plugin options
		 *      WC_Product  slave_product               Slave product
		 * )
		 *
		 * @return array
		 */
		$meta_keys[] = 'total_sales';
		$meta_keys[] = '_stock';
		$meta_keys[] = '_stock_status';
		$meta_keys[] = '_wc_review_count';
		$meta_keys[] = '_wc_rating_count';
		$meta_keys[] = '_wc_average_rating';
		write_log( print_r( $meta_keys, true ) );
		return $meta_keys;
	}

	// Haal extra velden uit de automatische synchronisatie
	add_filter( 'WOO_MSTORE_admin_product/master_slave_products_data_diff', 'unset_extra_products_data_diff', 10, 2 );

	function unset_extra_products_data_diff( $products_data_diff, $data ) {
		if ( 'no' === $data['options']['child_inherit_changes_fields_control__featured'][ get_current_blog_id() ] ) {
			unset( $products_data_diff['featured'] );
		}
		// write_log( print_r( $products_data_diff, true ) );
		return $products_data_diff;
	}

	// Geef aan welke metavelden we willen kopiëren
	add_filter( 'WOO_MSTORE_admin_product/slave_product_meta_to_update', 'update_slave_product_meta', 10, 2 );

	function update_slave_product_meta( $meta_data, $data ) {
		/**
		 * @param array $meta_data    Metadata to update in slave product
		 * @param array $data array(
		 *      WC_Product  master_product              Master product
		 *      array       master_product_attributes   Master product attributes
		 *      integer     master_product_blog_id      Master product blog ID
		 *      array       master_product_terms        Master product terms
		 *      array       master_product_upload_dir   Master product uploads directory information ( see wp_get_upload_dir() )
		 *      array       options                     Plugin options
		 *      WC_Product  slave_product               Slave product
		 * )
		 * @return array
		 */
		
		$keys_to_copy = array( '_in_bestelweb', '_shopplus_code', '_cu_ean', '_multiple', '_stat_uom', '_fairtrade_share', '_main_thumbnail_id', '_net_unit', '_net_content', '_unit_price', 'oft_product_id', 'promo_text' );
		foreach ( $keys_to_copy as $key ) {
			if ( $key === '_main_thumbnail_id' ) {
				$meta_data[ $key ] = $data['master_product']->get_image_id();
			} else {
				$meta_data[ $key ] = $data['master_product']->get_meta( $key );
			}
		}
		
		// '_upsell_ids' en '_crosssell_ids' worden in principe door WooMultistore zelf onderhouden
		$keys_to_translate = array( '_force_sell_ids', '_force_sell_synced_ids' );
		foreach ( $keys_to_translate as $key ) {
			$meta_data[ $key ] = translate_master_to_slave_ids( $key, $data['master_product']->get_meta( $key ), $data['master_product_blog_id'], $data['master_product'] );
		}

		// foreach ( $meta_data as $key => $value ) {
		// 	write_log( $key.' => '.$value );
		// }
		
		return $meta_data;
	}

	// Publieke metadata zoals 'touched_by_import' wordt automatisch gekopieerd bij eerste lokale publicatie ... PAS BESCHIKBAAR VANAF WOOMULTISTORE 4.1.5
	// add_filter( 'WOO_MSTORE_admin_product/slave_product_meta_to_exclude', 'exclude_slave_product_meta', 10, 2 );

	function exclude_slave_product_meta( $meta_keys, $data ) {
		/**
		 * @param array $meta_keys          Metadata keys to exclude from slave product
		 * @param array $data array(
		 *      WC_Product  master_product              Master product
		 *      array       master_product_attributes   Master product attributes
		 *      integer     master_product_blog_id      Master product blog ID
		 *      array       master_product_terms        Master product terms
		 *      array       master_product_upload_dir   Master product uploads directory information ( see wp_get_upload_dir() )
		 *      array       options                     Plugin options
		 *      WC_Product  slave_product               Slave product
		 * )
		 * @return array
		 */
		
		// Voorraadbeheer standaard uitschakelen?
		// $meta_keys[] = '_manage_stock';

		if ( $data['slave_product']->get_meta('touched_by_import') ) {
			$meta_keys[] = 'touched_by_import';
		}

		write_log( implode( ', ', $meta_keys ) );
		return $meta_keys;
	}

	// Zorg dat productupdates ook gesynchroniseerd worden via WP All Import
	add_action( 'pmxi_saved_post', 'execute_product_sync', 100, 1 );
	
	function execute_product_sync( $post_id ) {
		// Enkel uitvoeren indien het een product was dat bijgewerkt werd
		if ( get_post_type( $post_id ) === 'product' ) {
			/**
			 * Mark a new product to sync with a store and then call process_product hook to run the sync.
			 *
			 * @param integer $product_id WooCommerce product ID
			 * @param array   $stores Store IDs
			 * @param string  $child_inherit Set child inherit product change option. Valid value is either yes or no.
			 * @param string  $stock_sync Set stock sync option. Valid value is either yes or no.
			 */

			write_log( "PRODUCT-ID ".$post_id." WAS IMPORTED, PROCESSING UPDATE IN LOCAL STORES ..." );

			$stores = apply_filters( 'WOO_MSTORE/get_store_ids', array() );
			foreach ( $stores as $key => $store_id ) {
				// Producten sowieso nooit publiceren naar sjabloon!
				if ( $store_id == 5 ) {
					unset( $stores[ $key ] );
				}
				// if ( in_array( $store_id, get_site_option('oxfam_blocked_sites') ) ) {
				// 	unset( $stores[ $key ] );
				// }
			}
			write_log( "PUBLISH PRODUCT-ID ".$post_id." TO STORE-ID'S ".implode( ', ', $stores ) );
			do_action( 'WOO_MSTORE_admin_product/set_sync_options', $post_id, $stores, 'yes', 'no' );
			
			/**
			 * After sync option is set, now fire the sync hook.
			 *
			 * @param integer $product_id WooCommerce product ID
			 */
			do_action( 'WOO_MSTORE_admin_product/process_product', $post_id );

			// Gebruik eventueel 'WOO_MSTORE_admin_product/slave_product_updated' voor afsluitende save (indien attributen niet goed doorkomen)
		}
	}

	// add_action( 'WOO_MSTORE_admin_product/slave_product_updated', 'do_something_after_slave_date_saved_in_db', 10, 1 );

	function do_something_after_slave_date_saved_in_db( $data ) {
		write_log( "FINAL SAVE ".$data['master_product']->get_sku() );
		$data['master_product']->save();
	}

	// Doe leuke dingen voor de start van een import
	add_action( 'pmxi_before_xml_import', 'before_xml_import', 10, 1 );
	
	function before_xml_import( $import_id ) {
		update_site_option( 'oft_import_active', 'yes' );
	}

	// Hernoem het importbestand na afloop van de import zodat we een snapshot krijgen dat niet overschreven wordt
	add_action( 'pmxi_after_xml_import', 'after_xml_import', 10, 1 );
	
	function after_xml_import( $import_id ) {
		delete_site_option('oft_import_active');

		if ( $import_id == 7 ) {
			// Vind alle producten die vandaag niet bijgewerkt werden door de ERP-import
			// Best een 2de import opzetten specifiek voor craftsproducten?
			$args = array(
				'post_type'			=> 'product',
				'post_status'		=> array( 'publish', 'draft', 'trash' ),
				'posts_per_page'	=> -1,
				'meta_key'			=> 'touched_by_import', 
				'meta_value'		=> date_i18n('Ymd'),
				'meta_compare'		=> '<',
			);
			$to_outofstock = new WP_Query( $args );
			if ( $to_outofstock->have_posts() ) {
				write_log( $to_outofstock->found_posts." DEPRECATED PRODUCTS" );
				while ( $to_outofstock->have_posts() ) {
					$to_outofstock->the_post();
					$product = wc_get_product( get_the_ID() );
					// write_log( $product->get_sku() );
					// $product->set_stock_status('outofstock');
					// $product->update_meta_data( '_in_bestelweb', 'nee' );
					// $product->save();
				}
				wp_reset_postdata();
			}

			$old = WP_CONTENT_DIR."/erp-import.csv";
			$new = WP_CONTENT_DIR."/erp-import-".date_i18n('Y-m-d').".csv";
			rename( $old, $new );
		}
	}

	// Functie die product-ID's van de hoofdsite vertaalt en het metaveld opslaat in de huidige subsite (op basis van artikelnummer)
	/**
	* @param int $local_product_id
	* @param string $meta_key
	* @param array $product_meta_item_row
	*/	
	function translate_main_to_local_ids( $local_product_id, $meta_key, $product_meta_item_row ) {
		// write_log( "MAAK POST ".get_the_ID()." LOKAAL IN BLOG ".get_current_blog_id() );
		
		if ( $product_meta_item_row ) {
			foreach ( $product_meta_item_row as $main_product_id ) {
				switch_to_blog(1);
				$main_product = wc_get_product( $main_product_id );
				restore_current_blog();
				$local_product_ids[] = wc_get_product_id_by_sku( $main_product->get_sku() );
			}
			// Niet serialiseren voor coupons
			$coupon_keys = array( 'product_ids', 'exclude_product_ids', '_wjecf_free_product_ids' );
			if ( in_array( $meta_key, $coupon_keys ) ) {
				$local_product_ids = implode( ',', $local_product_ids );
			}
			update_post_meta( $local_product_id, $meta_key, $local_product_ids );
		} else {
			// Zorg ervoor dat het veld ook bij de child geleegd wordt!
			update_post_meta( $local_product_id, $meta_key, NULL );
		}
	}

	function translate_master_to_slave_ids( $meta_key, $main_product_ids, $master_blog_id, $master_product ) {
		// write_log( "MAAK EIGENSCHAP ".$meta_key." VAN SKU ".$master_product->get_sku()." LOKAAL IN BLOG ".get_current_blog_id() );
		
		if ( is_array( $main_product_ids ) ) {
			$slave_product_ids = array();
			foreach ( $main_product_ids as $main_product_id ) {
				switch_to_blog( $master_blog_id );
				$main_product = wc_get_product( $main_product_id );
				restore_current_blog();
				if ( $main_product !== false ) {
					$slave_product_id = wc_get_product_id_by_sku( $main_product->get_sku() );
					if ( intval( $slave_product_id ) > 0 ) {
						$slave_product_ids[] = $slave_product_id;
					}
				}
			}
		} else {
			// Indien $main_product_ids leeg is, mogen we dit gewoon zo doorgeven, zodat het ook lokaal leeggemaakt kan worden
			$slave_product_ids = $main_product_ids;
		}
		
		return $slave_product_ids;
	}

	function broadcast_master_to_slave_ids( $meta_key, $main_product_ids ) {
		$main_product_ids = explode( ',', $main_product_ids );
		
		if ( is_array( $main_product_ids ) ) {
			$slave_product_ids = array();
			foreach ( $main_product_ids as $main_product_id ) {
				switch_to_blog(1);
				$main_product = wc_get_product( $main_product_id );
				restore_current_blog();
				if ( $main_product !== false ) {
					$slave_product_id = wc_get_product_id_by_sku( $main_product->get_sku() );
					if ( intval( $slave_product_id ) > 0 ) {
						$slave_product_ids[] = $slave_product_id;
					}
				}
			}
		}
		
		// Verschijnt in de logs van de subsite!
		$logger = wc_get_logger();
		$context = array( 'source' => 'Oxfam Translate Broadcast Fields' );
		$logger->debug( "Translated property '".$meta_key."' from ".implode( ', ', $main_product_ids )." to ".implode( ', ', $slave_product_ids ), $context );
		
		return implode( ',', $slave_product_ids );
	}



	################
	# COMMUNICATIE #
	################

	// Voeg een custom dashboard widget toe met nieuws over het pilootproject
	add_action( 'wp_dashboard_setup', 'add_pilot_widget' );

	function add_pilot_widget() {
		global $wp_meta_boxes;

		wp_add_dashboard_widget(
			'dashboard_pilot_news_widget',
			'Info voor webshopmedewerkers',
			'dashboard_pilot_news_widget_function'
		);

		$dashboard = $wp_meta_boxes['dashboard']['normal']['core'];

		$my_widget = array( 'dashboard_pilot_news_widget' => $dashboard['dashboard_pilot_news_widget'] );
		unset( $dashboard['dashboard_pilot_news_widget'] );

		$sorted_dashboard = array_merge( $my_widget, $dashboard );
		$wp_meta_boxes['dashboard']['normal']['core'] = $sorted_dashboard;
	}
	
	// Stel de inhoud van de widget op
	function dashboard_pilot_news_widget_function() {
		echo '<div class="rss-widget">';
		echo '<p>De <a href="https://github.com/OxfamFairTrade/ob2c/wiki" target="_blank">online FAQ voor webshopbeheerders</a> staat online. Hierin verzamelen we alle mogelijke vragen die jullie als lokale webshopbeheerders kunnen hebben en beantwoorden we ze punt per punt met tekst en screenshots. Gebruik eventueel de zoekfunctie bovenaan rechts.</p>';
		echo '<p>Daarnaast kun je de nieuwe slides van de voorbije opleidingssessies raadplegen voor een overzicht van alle afspraken en praktische details: <a href="https://shop.oxfamwereldwinkels.be/wp-content/uploads/slides-opleiding-B2C-webshop-concept.pdf" download>Deel 1: Concept</a> (16/05/2020) en <a href="https://shop.oxfamwereldwinkels.be/wp-content/uploads/slides-opleiding-B2C-webshop-praktisch.pdf" download>Deel 2: Praktisch</a> (30/05/2020). Op <a href="https://copain.oww.be/webshop" target="_blank">de webshoppagina op Copain</a> vind je een overzicht van de belangrijkste documenten.</p>';
		echo '<p>Stuur een mailtje naar de <a href="mailto:e-commerce@oft.be?">Helpdesk E-Commerce</a> als er toch nog iets onduidelijk is, of als je een suggestie hebt. Tineke, Ive, Elien en Frederik helpen je zo snel mogelijk verder.</p>';
		echo '</div>';
	}

	function get_tracking_info( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		// Query alle order comments waarin het over SendCloud gaat en zet de oudste bovenaan
		$args = array( 'post_id' => $order->get_id(), 'type' => 'order_note', 'orderby' => 'comment_date_gmt', 'order' => 'ASC', 'search' => 'sendcloud' );
		// Want anders zien we de private opmerkingen niet!
		remove_filter( 'comments_clauses', array( 'WC_Comments', 'exclude_order_comments' ) );
		$comments = get_comments( $args );
		
		$tracking_info = false;
		if ( count($comments) > 0 ) {
			foreach ( $comments as $sendcloud_note ) {
				// Enkel waarde in meest recente comment zal geretourneerd worden!
				$tracking_info = array();

				if ( preg_match( '/[0-9]{24}/', $sendcloud_note->comment_content, $numbers ) === 1 ) {
					// We hebben 24-cijferig tracking number van Bpost gevonden
					$tracking_info['carrier'] = 'Bpost';
					$tracking_info['number'] = $numbers[0];
				} elseif ( preg_match( '/[0-9]{14}/', $sendcloud_note->comment_content, $numbers ) === 1 ) {
					// We hebben 14-cijferig tracking number van DPD gevonden
					$tracking_info['carrier'] = 'DPD';
					$tracking_info['number'] = $numbers[0];
				}
				
				// Zeer gevoelig voor wijzigingen van SendCloud uit!
				$parts = explode( 'traced at: ', $sendcloud_note->comment_content );
				if ( count( $parts ) > 1 ) {
					$tracking_info['link'] = esc_url( $parts[1] );
				}
			}
		}
		
		// Reactiveer filter
		add_filter( 'comments_clauses', array( 'WC_Comments', 'exclude_order_comments' ) );
		
		return $tracking_info;
	}

	function get_logistic_params( $order, $echo = false ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$params = array();
		$params['volume'] = 0.0;
		$params['maximum'] = 0.0;
		$params['weight'] = 0.0;
		
		foreach ( $order->get_items() as $line_item ) {
			if ( false !== ( $product = $line_item->get_product() ) ) {
				$volume = 1.0;
				
				if ( ( $length = floatval( $product->get_length() ) ) > 0 ) {
					$volume *= $length;
					if ( $length > $params['maximum'] ) {
						$params['maximum'] = $length;
					}
				}
				if ( ( $width = floatval( $product->get_width() ) ) > 0 ) {
					$volume *= $width;
					if ( $width > $params['maximum'] ) {
						$params['maximum'] = $width;
					}
				}
				if ( ( $height = floatval( $product->get_height() ) ) > 0 ) {
					$volume *= $height;
					if ( $height > $params['maximum'] ) {
						$params['maximum'] = $height;
					}
				}

				if ( $echo ) {
					echo $product->get_name().': '.number_format( $volume / 1000000, 2, ',', '.' ).' liter (x'.$line_item->get_quantity().')<br/>';
				}
				$params['volume'] += $line_item->get_quantity() * $volume;
				$params['weight'] += $line_item->get_quantity() * floatval( $product->get_weight() );
			} 
		}

		// Volume omrekenen van kubieke millimeters naar liter
		$params['volume'] /= 1000000;
		// Maximale afmeting omrekenen naar cm
		$params['maximum'] = ceil( $params['maximum'] / 10 );
		// Gewicht sowieso reeds in kilogram (maar check instellingen?)

		return $params;
	}
	
	// Voeg berichten toe bovenaan adminpagina's
	add_action( 'admin_head', 'show_only_oxfam_notices', 10000 );

	function show_only_oxfam_notices() {
		// Aanpassingen voor iPhone 10+
		echo '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">';

		// Gelijkaardige 'Show plugins/themes notices to admin only'-optie van User Role Editor niet inschakelen!
		if ( ! current_user_can('create_sites') ) {
			remove_all_actions('admin_notices');
		}
		add_action( 'admin_notices', 'oxfam_admin_notices' );
	}

	function oxfam_admin_notices() {
		global $pagenow, $post_type;
		$screen = get_current_screen();
		if ( $pagenow === 'index.php' and $screen->base === 'dashboard' ) {
			if ( in_array( get_current_blog_id(), get_site_option('oxfam_blocked_sites') ) ) {
				echo '<div class="notice notice-error">';
					echo '<p>Deze webshop is momenteel reeds bereikbaar (voor o.a. Mollie-controle) maar verschijnt nog niet in de winkelzoeker!</p>';
				echo '</div>';
			}
			if ( get_option('mollie-payments-for-woocommerce_test_mode_enabled') === 'yes' ) {
				echo '<div class="notice notice-success">';
					echo '<p>De betalingen op deze site staan momenteel in testmodus! Voel je vrij om naar hartelust te experimenteren met bestellingen.</p>';
				echo '</div>';
			}
			// echo '<div class="notice notice-error">';
			// 	echo '<p>Mails naar Microsoft-adressen (@hotmail.com, @live.com, ...) arriveerden de voorbije dagen niet bij de bestemmeling door een blacklisting van de externe mailserver die gekoppeld was aan de webshops. We zijn daarom voor de 3de keer op enkele maanden tijd overgeschakeld op een nieuw systeem.</p>';
			// echo '</div>';
			if ( get_current_site()->domain === 'shop.oxfamwereldwinkels.be' ) {
				echo '<div class="notice notice-success">';
					echo '<p>De nieuwe assortimentsdoos thee werd toegevoegd (opgelet, iets lagere prijs dan de voorgaande referentie!), samen met de doppers en wasnoten:</p><ul style="margin-left: 2em; column-count: 2;">';
						$skus = array( 23508, 12034, 12076, 12093, 12094, 12095, 12108, 48566, 48712, 65762, 87142, 87143, 87144, 87169, 87170, 87171, 87172, 87231, 87232, 87233, 87234, 91702, 91703, 91704 );
						foreach ( $skus as $sku ) {
							$product_id = wc_get_product_id_by_sku( $sku );
							if ( $product_id ) {
								$product = wc_get_product($product_id);
								echo '<li><a href="'.$product->get_permalink().'" target="_blank">'.$product->get_title().'</a> ('.$product->get_meta('_shopplus_code').')</li>';
							}
						}
					echo '</ul><p>';
					if ( current_user_can('manage_network_users') ) {
						// echo 'Je herkent deze producten aan de blauwe achtergrond onder \'<a href="admin.php?page=oxfam-products-list">Voorraadbeheer</a>\'. ';
						echo 'Opgelet: om te vermijden dat al deze producten als \'nieuw\' zouden verschijnen, kreeg de non-food als creatiedatum de dag van aanmaak in fairtradecrafts.be mee. Hierdoor verschijnen deze producten niét in het blauw onder \'<a href="admin.php?page=oxfam-products-list">Voorraadbeheer</a>\'. ';
					}
					echo 'Pas wanneer een beheerder ze in voorraad plaatst, worden deze producten bestelbaar voor klanten.</p>';
					echo '<p>Bovenop de agenda\'s, kalenders en doppers werden op 03/12 ook 149 centraal beheerde non-foodproducten toegevoegd. Het gaat om een beperkte selectie \'vast assortiment\' van MDM én alle producten uit het eindejaarsmagazine. <a href="https://shop.oxfamwereldwinkels.be/20201202-erp-import-crafts.xlsx" download>Raadpleeg de Excel met alle producten.</a> Vanaf nu zullen we elk kwartaal alle producten uit het FAIR-magazine beschikbaar maken. Voor het januaripakket zal dit begin januari gebeuren, samen met de levering in de winkel. Het is momenteel niet werkbaar om de volledige productcatalogus van Magasins du Monde (+/- 2.500 voorradige producten) in het webshopnetwerk te pompen: dit stelt hogere eisen aan de productdata, de zoekfunctie, het voorraadbeheer, onze server, ...</p>';
				echo '</div>';
				echo '<div class="notice notice-success">';
					echo '<p>De <a href="https://copain.oww.be/k/nl/n111/news/view/20167/1429/promo-s-online-winkel-december-update-wijnduo-s.html" target="_blank">decemberpromo\'s</a> werden geactiveerd in alle webshops. Ook de <a href="https://copain.oww.be/k/nl/n118/news/view/20655/12894/eindejaar-wijnduo-s-2020-turfblad.html" target="_blank">feestelijke wijnduo\'s</a> blijven actief tot en met 31 december. Creditering verloopt ook voor online wijnduo\'s via het turfblad in de winkel. Raadpleeg indien nodig <a href="admin.php?page=wc-reports&tab=orders&report=coupon_usage&range=month">de webshopstatistieken</a>.</p>';
				echo '</div>';
				echo '<div class="notice notice-info">';
					$correctie = '';
					$koffie_count = get_number_of_times_coupon_was_used('wvdft2020-koffie');
					if ( get_current_blog_id() === 15 ) {
						// Correctie voor Gentbrugge
						$koffie_extra = 4;
						$koffie_count += $koffie_extra;
						$correctie = ' Dit totaal bevat '.$koffie_extra.' koffiebonnen extra ter compensatie van de foutief toegekende kortingen in OWW07715, waarvoor nogmaals onze excuses.';
					}
					if ( get_current_blog_id() === 19 ) {
						// Correctie voor Lichtaart
						$koffie_extra = 2;
						$koffie_count += $koffie_extra;
						$correctie = ' Dit totaal bevat '.$koffie_extra.' koffiebonnen extra ter compensatie van de foutief toegekende kortingen in OWW07781 en OWW07823, waarvoor nogmaals onze excuses.';
					}
					$koffie_amount = 1.4151 * $koffie_count;
					$quinoa_count = get_number_of_times_coupon_was_used('wvdft2020-quinoa');
					$quinoa_amount = 3.8972 * $quinoa_count;
					echo '<p>Zoals eerder gemeld dien je voor de koffie- en quinoa-actie die tijdens Week van de Fair Trade automatisch geactiveerd werd bij geldige webshopbestellingen <u>geen bonnen in te leveren ter creditering</u>. We raadplegen gewoon de webshopstatistieken om te zien hoe vaak beide kortingen geactiveerd werden in jullie webshop. Voor jullie webshop werden '.$koffie_count.' koffiebonnen (t.w.v. '.wc_price( $koffie_amount ).') en '.$quinoa_count.' quinoabonnen (t.w.v. '.wc_price( $quinoa_amount ).') geregistreerd.';
					if ( $koffie_amount+$quinoa_amount > 0 ) {
						echo ' Het netto kortingsbedrag van '.wc_price( $koffie_amount+$quinoa_amount, array( 'ex_tax_label' => true ) ).' zal terugbetaald worden bij de volgende crediteringsronde, half december. <a href="https://shop.oxfamwereldwinkels.be/online-creditering-WVDFT2020.pdf" download>Raadpleeg de exacte verdeling per winkel in deze PDF.</a>';
					}
					echo $correctie.'</p>';
				echo '</div>';
				echo '<div class="notice notice-info">';
					echo '<p>Er werden 13 extra categorieën toegevoegd die je kunt gebruiken tijdens <a href="https://github.com/OxfamFairTrade/ob2c/wiki/9.-Lokaal-assortiment" target="_blank">het toevoegen van lokale producten</a>. Duiding bij welke producten we in welke categorie verwachten vind je in <a href="https://shop.oxfamwereldwinkels.be/structuur-crafts.pdf" download>deze nota</a>. Opgelet: alle producten die vroeger in de megacategorie \'Wonen, mode & speelgoed\' zaten, zitten nu onder \'Wonen\'. Pas de categorie indien nodig aan naar een geschiktere (sub)categorie. De cadeaubonnen werden verhuisd naar de algemenere categorie \'Geschenken & wenskaarten\'.</p>';
				echo '</div>';
				if ( does_home_delivery() ) {
					// Boodschappen voor winkels die thuislevering doen
					// echo '<div class="notice notice-success">';
					// 	echo '<p>Om de sluiting van het wereldwinkelnetwerk te verzachten werden de verzendkosten in alle webshops verlaagd naar 4,95 i.p.v. 6,95 euro per bestelling én is gratis levering tijdelijk beschikaar vanaf 50 i.p.v. 100 euro.</p>';
					// echo '</div>';
				}
				// echo '<div class="notice notice-warning">';
				// 	echo '<p>5 uitgefaseerde producten werden uit de database verwijderd omdat hun uiterste houdbaarheid inmiddels gepasseerd is, of geen enkele webshop ze nog op voorraad had. 25721 BIO Cashew chili.</p>';
				// echo '</div>';
				if ( does_sendcloud_delivery() ) {
					// Boodschappen voor winkels die verzenden met SendCloud
				}
			}
		}
	}

	function get_number_of_times_coupon_was_used( $coupon_code, $start_date = '2020-10-01', $end_date = '2020-10-31', $return_orders = false ) {
		global $wpdb;
		$total_count = 0;
		$objOrders = array();

		$query = "SELECT p.ID AS order_id FROM {$wpdb->prefix}posts AS p INNER JOIN {$wpdb->prefix}woocommerce_order_items AS woi ON p.ID = woi.order_id WHERE p.post_type = 'shop_order' AND p.post_status IN ('" . implode( "','", array( 'wc-completed' ) ) . "') AND woi.order_item_type = 'coupon' AND woi.order_item_name = '" . $coupon_code . "' AND DATE(p.post_date) BETWEEN '" . $start_date . "' AND '" . $end_date . "';";
		$orders = $wpdb->get_results( $query );

		if ( count( $orders ) > 0 ) {
			foreach ( $orders as $key => $order ) {
				$objOrder = wc_get_order( $order->order_id );
				if ( $objOrder !== false ) {
					$objOrders[] = $objOrder; 
					$coupons = $objOrder->get_items('coupon');
					if ( $coupons ) {
						foreach ( $coupons as $coupon ) {
							if ( $coupon->get_code() == $coupon_code ) {
								$total_count += $coupon->get_quantity();
							}
						}
					}
				}
			}
		}

		if ( $return_orders ) {
			return $objOrders;
		} else {
			return $total_count;
		}
	}

	// Schakel onnuttige widgets uit voor iedereen
	add_action( 'admin_init', 'remove_dashboard_meta' );

	function remove_dashboard_meta() {
		remove_meta_box( 'dashboard_right_now', 'dashboard', 'normal' );
		remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' );
		remove_meta_box( 'woocommerce_dashboard_recent_reviews', 'dashboard', 'normal' );
		remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
		remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
		remove_meta_box( 'wpb_visual_composer', 'vc_grid_item', 'side' );
		remove_meta_box( 'wpb_visual_composer', 'vc_grid_item-network', 'side' );
		
		if ( ! current_user_can('create_sites') ) {
			remove_meta_box( 'dashboard_primary', 'dashboard-network', 'normal' );
			remove_meta_box( 'network_dashboard_right_now', 'dashboard-network', 'normal' );
			// Want lukt niet via URE Pro
			remove_meta_box( 'postcustom', 'shop_order', 'normal' );
		}

		remove_action( 'welcome_panel', 'wp_welcome_panel' );
	}

	// Beheerd via WooCommerce Order Status Manager of is dit voor het dashboard?
	// add_filter( 'woocommerce_reports_get_order_report_data_args', 'wc_reports_get_order_custom_report_data_args', 100, 1 );

	function wc_reports_get_order_custom_report_data_args( $args ) {
		$args['order_status'] = array( 'on-hold', 'processing', 'claimed', 'completed' );
		return $args;
	};

	function get_latest_newsletters_in_folder( $list_id = '5cce3040aa', $folder_id = 'bbc1d65c43' ) {
		$server = substr( MAILCHIMP_APIKEY, strpos( MAILCHIMP_APIKEY, '-' ) + 1 );

		$args = array(
			'headers' => array(
				'Authorization' => 'Basic '.base64_encode( 'user:'.MAILCHIMP_APIKEY ),
			),
		);

		$response = wp_remote_get( 'https://'.$server.'.api.mailchimp.com/3.0/campaigns?since_send_time='.date_i18n( 'Y-m-d', strtotime('-6 months') ).'&status=sent&list_id='.$list_id.'&folder_id='.$folder_id.'&sort_field=send_time&sort_dir=ASC', $args );

		$mailings = "";
		if ( $response['response']['code'] == 200 ) {
			$body = json_decode($response['body']);
			$mailings .= "<p>Dit zijn de nieuwsbrieven van de afgelopen zes maanden:</p>";
			$mailings .= "<ul style='margin-left: 20px; margin-bottom: 1em;'>";

			foreach ( array_reverse($body->campaigns) as $campaign ) {
				$mailings .= '<li><a href="'.$campaign->long_archive_url.'" target="_blank">'.$campaign->settings->subject_line.'</a> ('.date_i18n( 'j F Y', strtotime($campaign->send_time) ).')</li>';
			}

			$mailings .= "</ul>";
		}		

		return $mailings;
	}

	function get_mailchimp_status_in_list( $list_id = '5cce3040aa' ) {
		$current_user = wp_get_current_user();
		$email = $current_user->user_email;
		$response = get_mailchimp_response_by_email( $email );
		 
		$msg = "";
		if ( $response['response']['code'] == 200 ) {
			$body = json_decode( $response['body'] );

			if ( $body->status === "subscribed" ) {
				$msg .= "al geabonneerd op het Digizine. Aan het begin van elke maand ontvang je dus een (h)eerlijke mail boordevol fairtradenieuws.";
			} else {
				$msg .= "helaas niet langer geabonneerd op het Digizine. Vul <a href='https://oxfamwereldwinkels.us3.list-manage.com/subscribe?u=d66c099224e521aa1d87da403&id=".$list_id."&FNAME=".$current_user->user_firstname."&LNAME=".$current_user->user_lastname."&EMAIL=".$email."&SOURCE=webshop' target='_blank'>het formulier</a> in om op je stappen terug te keren!";
			}
		} else {
			$msg .= "nog nooit geabonneerd geweest op het Digzine. Vul <a href='https://oxfamwereldwinkels.us3.list-manage.com/subscribe?u=d66c099224e521aa1d87da403&id=".$list_id."&FNAME=".$current_user->user_firstname."&LNAME=".$current_user->user_lastname."&EMAIL=".$email."&SOURCE=webshop' target='_blank'>het formulier</a> in om daar verandering in te brengen!";
		}

		return "<p>Je bent met het e-mailadres <a href='mailto:".$email."' target='_blank'>".$email."</a> ".$msg."</p>";
	}

	function get_mailchimp_response_by_email( $email, $list_id = '5cce3040aa' ) {
		$server = substr( MAILCHIMP_APIKEY, strpos( MAILCHIMP_APIKEY, '-' ) + 1 );
		$member_id = md5( format_mail( $email ) );

		$args = array(
			'headers' => array(
				'Authorization' => 'Basic '.base64_encode( 'user:'.MAILCHIMP_APIKEY ),
			),
		);

		$response = wp_remote_get( 'https://'.$server.'.api.mailchimp.com/3.0/lists/'.$list_id.'/members/'.$member_id, $args );
		return $response;
	}



	##############
	# SHORTCODES #
	##############

	// Personaliseer de begroeting op de startpagina
	// add_shortcode( 'topbar', 'print_greeting' );
	// add_shortcode( 'copyright', 'print_copyright' );
	add_shortcode( 'straat', 'print_place' );
	add_shortcode( 'postcode', 'print_zipcode' );
	add_shortcode( 'gemeente', 'print_city' );
	add_shortcode( 'telefoon', 'print_telephone' );
	add_shortcode( 'e-mail', 'print_mail' );
	add_shortcode( 'openingsuren', 'print_office_hours' );
	add_shortcode( 'alle_winkels', 'print_all_shops' );
	// add_shortcode( 'toon_titel', 'print_portal_title' );
	// add_shortcode( 'toon_inleiding', 'print_welcome' );
	// add_shortcode( 'toon_shops', 'print_store_selector' );
	// add_shortcode( 'toon_kaart', 'print_store_locator_map' );
	add_shortcode( 'toon_wc_notices', 'print_woocommerce_messages' );
	add_shortcode( 'toon_thuislevering', 'print_delivery_snippet' );
	add_shortcode( 'toon_postcodelijst', 'print_delivery_zips' );
	add_shortcode( 'toon_winkel_kaart', 'print_store_map' );
	// add_shortcode( 'scrolltext', 'print_scroll_text' );
	add_shortcode( 'company_name', 'get_webshop_name' );
	add_shortcode( 'contact_address', 'get_shop_contact' );
	add_shortcode( 'map_address', 'get_shop_address' );
	add_shortcode( 'email_footer', 'get_company_and_year' );
	// add_shortcode( 'toon_zoekbalk_producten', 'show_product_search' );

	function show_product_search() {
		wc_get_template( 'product-searchform_nm.php' );
	}

	function print_greeting() {
		if ( date_i18n('G') < 6 ) {
			$greeting = "Goeienacht";
		} elseif ( date_i18n('G') < 12 ) {
			$greeting = "Goeiemorgen";
		} elseif ( date_i18n('G') < 20 ) {
			$greeting = "Goeiemiddag";
		} else {
			$greeting = "Goeieavond";
		}
		return sprintf( __( 'Verwelkoming (%1$s) van de bezoeker (%2$s) op de webshop (%3$s).', 'oxfam-webshop' ), $greeting, get_customer(), get_webshop_name() );
	}

	function get_customer() {
		global $current_user;
		return ( is_user_logged_in() and strlen($current_user->user_firstname) > 1 ) ? $current_user->user_firstname : "bezoeker";
	}

	function print_copyright() {
		$text = get_webshop_name().' &copy; 2017-'.date_i18n('Y');
		if ( ! is_main_site() ) {
			// Contactpagina niet linken op portaalpagina
			$text = '<a href="'.get_site_url( get_current_blog_id(), '/contact/' ).'">'.$text.'</a>';
		}
		return $text;
	}

	function print_office_hours( $atts = [] ) {
		// Overschrijf defaults met expliciete data van de gebruiker
		$atts = shortcode_atts( array( 'id' => get_option('oxfam_shop_post_id'), 'start' => 'today' ), $atts );
		$output = '';

		$days = get_office_hours( NULL, $atts['id'] );
		// Kijk niet naar sluitingsdagen bij winkels waar we expliciete afhaaluren ingesteld hebben
		$exceptions = array();
		if ( in_array( $atts['id'], $exceptions ) ) {
			$holidays = array( '2020-12-25', '2021-01-01' );
		} else {
			// Uitzondering voor Borgerhout en Merksem
			if ( $atts['id'] == 3316 or $atts['id'] == 3646 ) {
				$holidays = get_site_option('oxfam_holidays');
			} else {
				// TO DO: Vervang dit door de expliciete 'closing_days' van de post-ID, want anders sluiten alle winkels van zodra de hoofdwinkel gesloten is, wat niet noodzakelijk klopt!
				$holidays = get_option( 'oxfam_holidays', get_site_option('oxfam_holidays') );
			}
		}

		if ( $atts['start'] === 'today' ) {
			// Begin met de weekdag van vandaag
			$start = intval( date('N') );
		} else {
			// Begin gewoon op maandag
			$start = 1;
		}

		for ( $cnt = 0; $cnt < 7; $cnt++ ) {
			// Fix voor zondagen
			$index = ( ( $start + $cnt - 1 ) % 7 ) + 1;
			
			// Check of er voor deze dag wel openingsuren bestaan
			if ( $days[$index] ) {
				$date = "";
				if ( $atts['start'] === 'today' ) {
					$date = " ".date( 'j/n', strtotime( "this ".date( 'l', strtotime("Sunday +{$index} days") ) ) );
				}
				// Toon sluitingsdagen indien we de specifieke openingsuren voor de komende 7 dagen tonen
				if ( $atts['start'] === 'today' and in_array( date_i18n( 'Y-m-d', strtotime("+{$cnt} days") ), $holidays ) ) {
					$output .= "<br/>".ucwords( date_i18n( 'l', strtotime("Sunday +{$index} days") ) ).$date.": uitzonderlijk gesloten";
				} else {
					foreach ( $days[$index] as $part => $part_hours ) {
						if ( ! isset( $$index ) ) {
							$output .= "<br/>".ucwords( date_i18n( 'l', strtotime("Sunday +{$index} days") ) ).$date.": " . $part_hours['start'] . " - " . $part_hours['end'];
							$$index = true;
						} else {
							$output .= " en " . $part_hours['start'] . " - " . $part_hours['end'];
						}
					}
				}
			}
		}
		
		// Boodschap over afhaling op afspraak enkel toevoegen indien hele week gesloten
		if ( strpos( $output, ' - ' ) === false ) {
			if ( get_current_blog_id() === 44 ) {
				// Uitzondering voor Diksmuide
				$output = '<p class="corona-notice">Tijdens de lockdown van De Stoasje: gratis thuislevering op donderdag tussen 18u en 20u!</p>';
			} else {
				$text = 'Om de verspreiding van het coronavirus tegen te gaan, is onze winkel momenteel gesloten. Afhalen kan enkel nog <u>op afspraak</u>. Na het plaatsen van je bestelling contacteren we je om een tijdstip af te spreken.';
				$output = '<p class="corona-notice">'.$text.'</p>';
			}
		} else {
			// if ( $atts['id'] === 'brugge' ) {
			// 	// Extra tekst in de mail
			// 	if ( ! is_checkout() ) {
			// 		$text .= '<br/>Opgelet: de poort is gesloten, bel aan bij de deur links. We nemen steeds de nodige hygiënische maatregelen. Alvast bedankt voor je begrip!';
			// 	}
			// }

			// Knip de eerste <br/> er weer af
			$output = substr( $output, 5 );
		}
		
		return $output;
	}

	function print_all_shops() {
		$output = '[vc_tta_tour spacing="5" autoplay="10" active_section="1"]';
		foreach ( ob2c_get_pickup_locations() as $shop_post_id => $shop_name ) {
			$shop_address = get_shop_address( array( 'id' => $shop_post_id ) );
			$output .= '[vc_tta_section title="'.$shop_name.'" tab_id="'.$shop_post_id.'"][vc_row_inner][vc_column_inner width="1/2"][nm_feature icon="pe-7s-home" layout="centered" title="Contactgegevens" icon_color="#282828"][contact_address id="'.$shop_post_id.'"][/nm_feature][/vc_column_inner][vc_column_inner width="1/2"][nm_feature icon="pe-7s-alarm" layout="centered" title="Openingsuren" icon_color="#282828"][openingsuren start="monday" id="'.$shop_post_id.'"][/nm_feature][/vc_column_inner][/vc_row_inner][/vc_tta_section]';
		}
		$output .= '[/vc_tta_tour]';
		
		return do_shortcode( $output );
	}

	function ob2c_get_pickup_locations( $include_external_locations = false, $return_internal_id = false ) {
		$shops = array();
		
		if ( class_exists('WC_Local_Pickup_Plus_Loader') ) {

			// Nieuwe versie
			if ( wc_local_pickup_plus()->get_pickup_locations_instance()->get_pickup_locations_count() > 0 ) {
				// Zet de oudste winkels bovenaan
				$locations = wc_local_pickup_plus()->get_pickup_locations_instance()->get_sorted_pickup_locations( array( 'order' => 'ASC' ) );
				foreach ( $locations as $location ) {
					// We kunnen ook $location->get_address() gebruiken (zoals vroeger) maar dat is een object, geen string
					$parts = explode( 'id=', $location->get_description() );
					if ( isset( $parts[1] ) ) {
						// Het heeft geen zin om het adres van niet-numerieke ID's op te vragen (= uitzonderingen)
						$temporary_shop_post_id = str_replace( ']', '', $parts[1] );
						if ( is_numeric( $temporary_shop_post_id ) ) {
							$shop_post_id = intval( $temporary_shop_post_id );
						} elseif ( $include_external_locations ) {
							// Externe locatie toch opnemen
							$shop_post_id = $temporary_shop_post_id;
						} else {
							// Sla locatie definitief over
							continue;
						}
					} else {
						// Geen argument, dus het is de hoofdwinkel, altijd opnemen!
						$shop_post_id = get_option('oxfam_shop_post_id');
					}

					if ( $return_internal_id ) {
						$shops[ $shop_post_id ] = $location->get_id();
					} else {
						$shops[ $shop_post_id ] = $location->get_name();
					}
				}
			}

		} else {
			
			// Oude versie
			if ( $locations = get_option('woocommerce_pickup_locations') ) {
				foreach ( $locations as $location ) {
					$parts = explode( 'id=', $location['address_1'] );
					if ( isset( $parts[1] ) ) {
						$shop_post_id = str_replace( ']', '', $parts[1] );
						if ( is_numeric( $shop_post_id ) ) {
							$shop_post_id = intval( $shop_post_id );
							// Eventueel str_replace( 'Oxfam-Wereldwinkel ', '', $location['shipping_company'] ) doen?
							$shops[ $shop_post_id ] = $location['shipping_company'];
						} elseif ( $include_external_locations ) {
							$shops[ $shop_post_id ] = $location['shipping_company'];
						}
					} else {
						// Geen argument, dus het is de hoofdwinkel, altijd opnemen!
						$shops[ get_option('oxfam_shop_post_id') ] = $location['shipping_company'];
					}
				}
			}

		}

		// write_log( print_r( $shops, true ) );
		return $shops;
	}

	function print_oxfam_shop_data( $key, $atts ) {
		// Overschrijf defaults door opgegeven attributen
		$atts = shortcode_atts( array( 'id' => get_option('oxfam_shop_post_id') ), $atts );
		return get_oxfam_shop_data( $key, 0, false, $atts['id'] );
	}

	function print_mail( $atts = [] ) {
		$atts = shortcode_atts( array( 'email' => get_webshop_email() ), $atts );
		return "<a href='mailto:".$atts['email']."'>".$atts['email']."</a>";
	}

	function print_place( $atts = [] ) {
		return print_oxfam_shop_data( 'place', $atts );
	}

	function print_zipcode( $atts = [] ) {
		return print_oxfam_shop_data( 'zipcode', $atts );
	}

	function print_city( $atts = [] ) {
		return print_oxfam_shop_data( 'city', $atts );
	}

	function print_telephone( $atts = [] ) {
		$telephone = print_oxfam_shop_data( 'telephone', $atts );
		return '<a href="tel:+32'.substr( preg_replace( '/[^0-9]/', '', $telephone ), 1 ).'">'.$telephone.'</a>';
	}

	function get_shop_vat_number( $atts = [], $before = '', $after = '' ) {
		$vat_number = print_oxfam_shop_data( 'tax', $atts );
		if ( ! empty( $vat_number ) ) {
			return $before . $vat_number . $after;
		} else {
			return '';
		}
	}

	function print_woocommerce_messages() {
		if ( function_exists('wc_print_notices') and wc_notice_count() > 0 ) {
			return wc_print_notices();
		} else {
			return '';
		}
	}

	function print_delivery_snippet() {
		$msg = "";
		if ( does_home_delivery() ) {
			$msg = "Heb je gekozen voor levering? Dan staan we maximaal 3 werkdagen later met je pakje op je stoep (*).";
		}
		return $msg;
	}

	function print_delivery_zips() {
		$msg = "";
		if ( does_home_delivery() ) {
			$cities = get_site_option( 'oxfam_flemish_zip_codes' );
			$zips = get_oxfam_covered_zips();
			$list = array();
			foreach ( $zips as $zip ) {
				// Enkel Vlaamse steden expliciet vermelden
				if ( array_key_exists( $zip, $cities ) ) {
					$zip_city = explode( '/', $cities[$zip] );
					$list[] = $zip." ".trim($zip_city[0]);
				}
			}
			$msg = "<small>(*) Oxfam-Wereldwinkels kiest bewust voor lokale verwerking. Deze webshop levert aan huis in ".implode( ', ', $list ).".<br/><br/>Staat je postcode niet in deze lijst? <a href='/'>Keer dan terug naar de portaalpagina</a> en vul daar je postcode in.</small>";
		}
		return $msg;
	}

	function print_store_map() {
		// Zoom kaart wat minder ver in indien grote regio
		if ( get_current_blog_id() === 25 ) {
			// Uitzondering voor Regio Brugge
			$zoom = 11;
		} elseif ( get_current_blog_id() === 50 ) {
			// Uitzondering voor Oudenaarde-Ronse
			$zoom = 12;
		} elseif ( get_current_blog_id() === 60 ) {
			// Uitzondering voor Hemiksem-Schelle
			$zoom = 14;
		} elseif ( is_regional_webshop() ) {
			$zoom = 13;
		} else {
			$zoom = 15;
		}
		return do_shortcode("[flexiblemap src='".content_url( '/maps/site-'.get_current_blog_id().'.kml?v='.rand() )."' width='100%' height='600px' zoom='".$zoom."' hidemaptype='true' hidescale='false' kmlcache='8 hours' locale='nl-BE' id='map-oxfam']");
	}

	function print_scroll_text() {
		return __( 'Tekst die verschijnt bovenaan de hoofdpagina met producten.', 'oxfam-webshop' );
	}



	###########
	# HELPERS #
	###########

	function get_flemish_zips_and_cities() {
		$zips = get_site_option('oxfam_flemish_zip_codes');
		foreach ( $zips as $zip => $cities ) {
			$parts = explode( '/', $cities );
			foreach ( $parts as $city ) {
				$content[] = array( 'label' => $zip.' '.trim($city), 'value' => $zip );	
			}
		}
		return $content;
	}

	// CHECK OF DIT VOLSTAAT (NOTICES, FAQ, ...)
	function does_risky_delivery() {
		// Zet 'yes'-waarde om in een echte boolean!
		return ( get_option('oxfam_does_risky_delivery') === 'yes' );
	}

	function does_home_delivery( $zipcode = 0 ) {
		if ( intval( $zipcode ) === 0 ) {
			return boolval( get_oxfam_covered_zips() );
		} else {
			// Check of de webshop thuislevering doet voor deze specifieke postcode
			$response = in_array( $zipcode, get_oxfam_covered_zips() );
			return $response;
		}
	}

	function does_local_pickup() {
		$pickup_settings = get_option('woocommerce_local_pickup_plus_settings');
		if ( is_array( $pickup_settings ) ) {
			return ( 'yes' === $pickup_settings['enabled'] );
		} else {
			return false;
		}
	}

	function does_sendcloud_delivery() {
		if ( does_home_delivery() ) {
			$sendcloud_zone_id = 3;
			$zone = WC_Shipping_Zones::get_zone_by( 'zone_id', $sendcloud_zone_id );
			if ( $zone ) {
				// Enkel actieve methodes meetellen
				$methods = $zone->get_shipping_methods( true );
				if ( count( $methods ) > 0 ) {
					return true;
				} else {
					return false;
				}
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	function is_regional_webshop() {
		// Gentbrugge, Antwerpen, Leuven, Mechelen en Wetteren
		$regions = array( 15, 24, 28, 40, 53 );
		// Opgelet: vergeet de custom orderstatus 'claimed' niet te publiceren naar deze subsites!
		return in_array( get_current_blog_id(), $regions );
	}

	// Kan zowel productobject als post-ID ontvangen
	function is_national_product( $object ) {
		if ( $object instanceof WC_Product ) {
			return ( intval( $object->get_meta('_woonet_network_is_child_site_id') ) === 1 );
		} else {
			return ( intval( get_post_meta( $object, '_woonet_network_is_child_site_id', true ) ) === 1 );
		}
	}

	function get_external_wpsl_store( $shop_post_id, $domain = 'www.oxfamwereldwinkels.be' ) {
		$store_data = false;
		$shop_post_id = intval( $shop_post_id );

		if ( $shop_post_id > 0 ) {
			if ( false === ( $store_data = get_site_transient( $shop_post_id.'_store_data' ) ) ) {
				$response = wp_remote_get( 'https://'.$domain.'/wp-json/wp/v2/wpsl_stores/'.$shop_post_id );
				
				if ( wp_remote_retrieve_response_code( $response ) === 200 ) {
					// Zet het JSON-object om in een PHP-array
					$store_data = json_decode( wp_remote_retrieve_body( $response ), true );
					set_site_transient( $shop_post_id.'_store_data', $store_data, DAY_IN_SECONDS );
				} else {
					$logger = wc_get_logger();
					$context = array( 'source' => 'WordPress API' );
					$logger->notice( 'Could not retrieve shop data for ID '.$shop_post_id, $context );
				}
			}
		}

		return $store_data;
	}

	function get_external_wpsl_stores( $domain = 'www.oxfamwereldwinkels.be', $page = 1 ) {
		$all_stores = array();

		// Enkel gepubliceerde winkels zijn beschikbaar via API, net wat we willen!
		// In API is -1 geen geldige waarde voor 'per_page' 
		$response = wp_remote_get( 'https://'.$domain.'/wp-json/wp/v2/wpsl_stores?per_page=100&page='.$page );
		
		if ( wp_remote_retrieve_response_code( $response ) === 200 ) {			
			// Zet het JSON-object om in een PHP-array
			$all_stores = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $page === 1 ) {
				// Deze header geeft aan hoeveel resultatenpagina's er in totaal zijn
				$total_pages = intval( wp_remote_retrieve_header( $response, 'X-WP-TotalPages' ) );

				// Vul indien nodig recursief aan vanaf 2de pagina
				for ( $i = 2; $i <= $total_pages; $i++ ) { 
					$all_stores = array_merge( $all_stores,  get_external_wpsl_stores( $domain, $i ) );
				}
			}
		} else {
			$logger = wc_get_logger();
			$context = array( 'source' => 'WordPress API' );
			$logger->notice( 'Could not retrieve shops on page '.$page, $context );
		}

		return $all_stores;
	}

	function get_external_partner( $partner_name, $domain = 'www.oxfamwereldwinkels.be' ) {
		$partner_data = array();
		$partner_slug = sanitize_title( $partner_name );

		if ( false === ( $partner_data = get_site_transient( $partner_slug.'_partner_data' ) ) ) {
			// Op dit moment is de API nog volledig publiek, dus dit is toekomstmuziek
			$args = array(
				'headers' => array(
					'Authorization' => 'Basic '.base64_encode( OWW_USER.':'.OWW_PASSWORD ),
				),
			);

			// Zoekt default enkel naar objecten met status 'publish', levert in principe één element op
			$response = wp_remote_get( 'https://'.$domain.'/wp-json/wp/v2/partners/?slug='.$partner_slug );
			
			// Log alles op de hoofdsite
			switch_to_blog(1);
			$logger = wc_get_logger();
			$context = array( 'source' => 'WordPress API' );
			
			if ( wp_remote_retrieve_response_code( $response ) === 200 ) {
				// Zet het JSON-object om in een PHP-array
				$matching_partners = json_decode( wp_remote_retrieve_body( $response ), true );
				
				if ( count( $matching_partners ) === 1 ) {
					$partner_data = $matching_partners[0];
					// Beperk de payload
					unset( $partner_data['content'] );
					unset( $partner_data['yoast_head'] );
					unset( $partner_data['_links'] );
					set_site_transient( $partner_slug.'_partner_data', $partner_data, DAY_IN_SECONDS );
					$logger->debug( 'Partner data saved in transient for '.$partner_slug, $context );
				} elseif ( count( $matching_partners ) > 1 ) {
					$logger->notice( 'Multiple partners found for '.$partner_slug, $context );
				} else {
					$logger->notice( 'No partner found for '.$partner_slug, $context );
				}
			} else {
				$logger->notice( 'Could not retrieve partner for '.$partner_slug, $context );
			}

			restore_current_blog();
		}

		return $partner_data;
	}

	// Parameter $raw bepaalt of we de correcties voor de webshops willen uitschakelen (mag verdwijnen van zodra logomateriaal uit OWW-site komt)
	function get_oxfam_shop_data( $key, $node = 0, $raw = false, $shop_post_id = 0 ) {
		if ( $shop_post_id === 0 ) $shop_post_id = get_option('oxfam_shop_post_id');

		if ( ! is_main_site() ) {

			$oww_store_data = get_external_wpsl_store( $shop_post_id );
			if ( $oww_store_data !== false ) {
				// Bestaat in principe altijd
				$location_data = $oww_store_data['location'];
				
				if ( intval( $shop_post_id ) === 3598 ) {
					// Uitzonderingen voor Regio Leuven vzw
					switch ($key) {
						case 'tax':
							return call_user_func( 'format_'.$key, 'BE 0479.961.641' );
						case 'account':
							return call_user_func( 'format_'.$key, 'BE86 0014 0233 4050' );
						case 'headquarter':
							return call_user_func( 'format_'.$key, 'Parijsstraat 56, 3000 Leuven' );
						case 'telephone':
							return call_user_func( 'format_'.$key, '0495325682', '.' );
					}
				} elseif ( intval( $shop_post_id ) === 3226 ) {
					// Uitzonderingen voor Regio Antwerpen vzw
					switch ($key) {
						case 'account':
							return call_user_func( 'format_'.$key, 'BE56 0018 1366 6388' );
					}
				} elseif ( intval( $shop_post_id ) === 3362 ) {
					// Uitzonderingen voor Deinze
					switch ($key) {
						case 'telephone':
							return call_user_func( 'format_'.$key, '0493082695', '.' );
					}
				} elseif ( intval( $shop_post_id ) === 3454 ) {
					// Uitzonderingen voor Hemiksem
					switch ($key) {
						case 'telephone':
							return call_user_func( 'format_'.$key, '0494626517', '.' );
					}
				} elseif ( intval( $shop_post_id ) === 3725 ) {
					// Uitzonderingen voor Schelle
					switch ($key) {
						case 'telephone':
							return call_user_func( 'format_'.$key, '0487436822', '.' );
					}
				} elseif ( intval( $shop_post_id ) === 3383 ) {
					// Uitzonderingen voor Diest
					switch ($key) {
						case 'telephone':
							return call_user_func( 'format_'.$key, '0475596166', '.' );
					}
				}
				
				if ( array_key_exists( $key, $location_data ) and $location_data[$key] !== '' ) {
					
					switch ( $key ) {
						case 'telephone':
							// Geef alternatieve delimiter mee
							return call_user_func( 'format_'.$key, $location_data[$key], '.' );
						case 'headquarter':
							// Plak de adresgegevens van de vzw aan elkaar
							return call_user_func( 'format_place', $location_data[$key]['place'] ).', '.call_user_func( 'format_zipcode', $location_data[$key]['zipcode'] ).' '.call_user_func( 'format_city', $location_data[$key]['city'] );
						case 'll':
							// Er bestaat geen formatteerfunctie voor coördinaten
							return $location_data[$key];
					}

					return call_user_func( 'format_'.$key, $location_data[$key] );

				} else {
					return '';
				}
			}

		} else {

			switch ( $key ) {
				case 'place':
					return call_user_func( 'format_place', 'Ververijstraat 17' );
				case 'zipcode':
					return call_user_func( 'format_zipcode', '9000' );
				case 'city':
					return call_user_func( 'format_city', 'Gent' );
				case 'telephone':
					return call_user_func( 'format_telephone', '092188899', '.' );
				case 'tax':
					return call_user_func( 'format_tax', 'BE 0415.365.777' );
				default:
					return 'niet gevonden';
			}

		}
	}

	function get_webshop_name( $shortened = false ) {
		$webshop_name = get_bloginfo('name');
		if ( $shortened ) {
			$webshop_name = str_replace( 'Oxfam-Wereldwinkel ', '', $webshop_name );
		}
		return $webshop_name;
	}

	function get_webshop_email() {
		return get_option('admin_email');
	}

	// Of rechtstreeks ophalen uit WPSL op hoofdniveau?
	function get_shop_name( $atts = [] ) {
		$atts = shortcode_atts( array( 'id' => get_option('oxfam_shop_post_id') ), $atts );
		// Te integreren in get_oxfam_shop_data()
		$oww_store_data = get_external_wpsl_store( $atts['id'] );
		if ( $oww_store_data !== false ) {
			return 'Oxfam-Wereldwinkel '.$oww_store_data['title']['rendered'];
		} else {
			return false;
		}
	}

	// Of rechtstreeks ophalen uit WPSL op hoofdniveau?
	function get_shop_email( $atts = [] ) {
		$atts = shortcode_atts( array( 'id' => get_option('oxfam_shop_post_id') ), $atts );
		// Te integreren in get_oxfam_shop_data()
		$oww_store_data = get_external_wpsl_store( $atts['id'] );
		if ( $oww_store_data !== false ) {
			return $oww_store_data['location']['mail'];
		} else {
			return false;
		}
	}

	function get_shop_contact( $atts = [] ) {
		$atts = shortcode_atts( array( 'id' => get_option('oxfam_shop_post_id') ), $atts );
		return get_shop_address( $atts )."<br/>".get_oxfam_shop_data( 'telephone', 0, false, $atts['id'] )."<br/>".get_oxfam_shop_data( 'tax', 0, false, $atts['id'] );
	}

	function get_shop_address( $atts = [] ) {
		$atts = shortcode_atts( array( 'id' => get_option('oxfam_shop_post_id') ), $atts );
		return get_oxfam_shop_data( 'place', 0, false, $atts['id'] )."<br/>".get_oxfam_shop_data( 'zipcode', 0, false, $atts['id'] )." ".get_oxfam_shop_data( 'city', 0, false, $atts['id'] );
	}

	function get_shops() {
		$global_zips = array();
		// Negeer afgeschermde en gearchiveerde sites
		$sites = get_sites( array( 'site__not_in' => get_site_option('oxfam_blocked_sites'), 'public' => 1, ) );
		foreach ( $sites as $site ) {
			switch_to_blog( $site->blog_id );
			$local_zips = get_option('oxfam_zip_codes');
			if ( $local_zips !== false ) {
				foreach ( $local_zips as $zip ) {
					if ( isset($global_zips[$zip]) ) {
						write_log("CONSISTENTIEFOUT BLOG-ID ".$site->blog_id.": Postcode ".$zip." is reeds gelinkt aan ".$global_zips[$zip].'!');
					}
					$global_zips[$zip] = 'https://' . $site->domain . $site->path;
				}
			}
			restore_current_blog();
		}
		ksort($global_zips);
		return $global_zips;
	}

	add_filter( 'flexmap_custom_map_types', function( $map_types, $attrs ) {
		if ( empty($attrs['maptype']) ) {
			return $map_types;
		}

		if ( $attrs['maptype'] === 'light_monochrome' and empty($map_types['light_monochrome']) ) {
			$custom_type = '{ "styles" : [{"stylers":[{"hue":"#ffffff"},{"invert_lightness":false},{"saturation":-100}]}], "options" : { "name" : "Light Monochrome" } }';
			$map_types['light_monochrome'] = json_decode($custom_type);
		}
		return $map_types;
	}, 10, 2 );

	function get_company_and_year() {
		return get_webshop_name().' &copy; 2017-'.date_i18n('Y');
	}

	function get_oxfam_covered_zips() {
		global $wpdb;
		$zips = array();
		
		// Hou enkel rekening met ingeschakelde zones
		$locations = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."woocommerce_shipping_zone_locations LEFT JOIN ".$wpdb->prefix."woocommerce_shipping_zone_methods ON ".$wpdb->prefix."woocommerce_shipping_zone_methods.zone_id = ".$wpdb->prefix."woocommerce_shipping_zone_locations.zone_id WHERE ".$wpdb->prefix."woocommerce_shipping_zone_locations.location_type = 'postcode' AND ".$wpdb->prefix."woocommerce_shipping_zone_methods.is_enabled = 1" );
		
		if ( count( $locations ) > 0 ) {
			foreach ( $locations as $row ) {
				$zips[] = $row->location_code;
			}
			$zips = array_unique( $zips );

			// Verwijder de default '9999'-waarde uit ongebruikte verzendmethodes
			if ( ( $key = array_search( '9999', $zips ) ) !== false ) {
				unset( $zips[ $key ] );
			}
			
			sort( $zips, SORT_NUMERIC );
		}

		return $zips;
	}


	
	##########
	# SEARCH #
	##########

	// Verander capability van 'manage_options' naar 'create_sites' zodat enkel superbeheerders de instellingen kunnen wijzigen
	add_filter( 'relevanssi_options_capability', function( $capability ) { return 'create_sites'; } );
	
	// Verander capability van 'edit_pages' naar 'manage_woocommerce' zodat ook lokale beheerders de logs kunnen bekijken
	add_filter( 'relevanssi_user_searches_capability', function( $capability ) { return 'manage_woocommerce'; } );
		
	// Laat logs rechtstreeks doorlinken naar specifieke product search in een nieuw venster
	add_filter( 'relevanssi_user_searches_query_url', 'redirect_relevanissi_search_terms_to_products', 10, 1 );

	function redirect_relevanissi_search_terms_to_products( $query_url ) {
		return $query_url.'&post_type=product';
	}

	// Probeert reguliere meervouden en verkleinwoorden automatisch weg te laten uit zoektermen (én index)
	add_filter( 'relevanssi_stemmer', 'relevanssi_dutch_stemmer' );

	function relevanssi_dutch_stemmer( $term ) {
		// De 'synoniemen' die een woord simpelweg verlengen voeren we pas door nu de content opgesplitst is in woorden
		$synonyms = array( 'blauw' => 'blauwe', 'groen' => 'groene', 'wit' => 'witte', 'zwart' => 'zwarte', 'paars' => 'paarse', 'bruin' => 'bruine' );
		foreach ( $synonyms as $search => $replace ) {
			if ( strcmp( $term, $search ) === 0 ) $term = $replace;
		}
		
		$len = strlen($term);
		
		if ( $len > 4 ) {
			$last_3 = substr($term, -3, 3);
			$last_4 = substr($term, -4, 4);
			$vowels = array( "a", "e", "i", "o", "u" );

			// Knip alle meervouden op 's' weg
			if ( substr($term, -2, 2) === "'s" ) {
				$term = substr($term, 0, -2);
			} elseif ( in_array( $last_4, array( "eaus", "eaux" ) ) ) {
				$term = substr($term, 0, -1);
			} elseif ( substr($term, -1, 1) === "s" and ! in_array( substr($term, -2, 1), array( "a", "i", "o", "u" ), true ) and ! ( in_array( substr($term, -2, 1), $vowels, true ) and in_array( substr($term, -3, 1), $vowels, true ) ) ) {
				// Behalve na een klinker (m.u.v. 'e') of een tweeklank!
				$term = substr($term, 0, -1);
			}

			// Knip de speciale meervouden op 'en' met een wisselende eindletter weg
			if ( $last_3 === "'en" ) {
				$term = substr($term, 0, -3);
			} elseif ( $last_3 === "eën" ) {
				$term = substr($term, 0, -3)."e";
			} elseif ( $last_3 === "iën" ) {
				$term = substr($term, 0, -3)."ie";
			} elseif ( $last_4 === "ozen" ) {
				// Andere onregelmatige meervouden vangen we op via de synoniemen!
				$term = substr($term, 0, -3)."os";
			}

			// Knip de gewone meervouden op 'en' weg
			if ( substr($term, -2, 2) === "en" and ! in_array( substr($term, -3, 1), $vowels, true ) ) {
				$term = substr($term, 0, -2);
			}

			// Knip de verkleinende suffixen weg
			if ( substr($term, -4, 4) === "ltje" ) {
				$term = substr($term, 0, -3);
			} elseif ( substr($term, -4, 4) === "mpje" ) {
				$term = substr($term, 0, -3);
			} elseif ( substr($term, -4, 4) === "etje" ) {
				$term = substr($term, 0, -4);
			} elseif ( substr($term, -2, 2) === "je" ) {
				// Moeilijk te achterhalen wanneer de laatste 't' ook weg moet!
				$term = substr($term, 0, -2);
			}

			// Knip de overblijvende verdubbelde eindletters weg
			if ( in_array( substr($term, -2, 2), array( "bb", "dd", "ff", "gg", "kk", "ll", "mm", "nn", "pp", "rr", "ss", "tt" ) ) ) {
				$term = substr($term, 0, -1);
			}
		}

		return $term;
	}

	// Plaats een zoeksuggestie net onder de titel van zoekpagina's als er minder dan 5 resultaten zijn
	// Probleem: de 'woocommerce_archive_description'-actie wordt niet uitgevoerd door Savoy bovenaan zoekresultaten!
	// add_action( 'woocommerce_archive_description', 'ob2c_add_didyoumean' );

	function ob2c_add_didyoumean() {
		if ( is_search() ) {
			relevanssi_didyoumean( get_search_query(), "<p>Bedoelde je misschien <i>", "</i> ?</p>", 5 );
		}
	}

	// Zorg ervoor dat de zoeksuggestie opnieuw linkt naar de productenzoeker
	add_filter( 'relevanssi_didyoumean_url', 'ob2c_modify_didyoumean_url', 10, 1 );
	
	function ob2c_modify_didyoumean_url( $url ) {
		return add_query_arg( 'post_type', 'product', $url );
	}

	// Verhinder dat termen die slechts 1x in de index voorkomen de automatische suggesties verstoren
	// add_filter( 'relevanssi_get_words_having', function() { return 2; } );

	// Toon de bestsellers op zoekpagina's zonder resultaten MOET MEER NAAR BOVEN + VERSCHIJNT OOK ALS ER WEL RESULTATEN ZIJN
	// add_action( 'woocommerce_after_main_content', 'add_bestsellers' );

	function add_bestsellers() {
		global $wp_query;
		if ( is_search() and $wp_query->found_posts == 0 ) {
			echo do_shortcode('[vc_row css=".vc_custom_1487859300634{padding-top: 25px !important;padding-bottom: 25px !important;}"][vc_column][vc_text_separator title="<h2>Werp een blik op onze bestsellers ...</h2>" css=".vc_custom_1487854440279{padding-bottom: 25px !important;}"][best_selling_products per_page="10" columns="5" orderby="rand"][/vc_column][/vc_row]');
		}
	}

	// Zorg ervoor dat verborgen producten niet geïndexeerd worden (en dus niet opduiken in de zoekresultaten) SOWIESO AL ONZICHTBAAR, ZIE OPTIE
	add_filter( 'relevanssi_woocommerce_indexing', 'ob2c_exclude_hidden_products', 10, 1 );
	
	function ob2c_exclude_hidden_products( $blocks ) {
		$blocks['outofstock'] = false;
		// $blocks['exclude-from-catalog'] = false;
		$blocks['exclude-from-search'] = true;
		return $blocks;
	}

	// Voeg de bovenliggende categorie toe aan de te indexeren content van een product (inclusief synoniemen) NOG NODIG?
	add_filter( 'relevanssi_content_to_index', 'ob2c_index_parent_category_and_origin', 10, 2 );

	function ob2c_index_parent_category_and_origin( $content, $post ) {
		global $relevanssi_variables;
		$categories = get_the_terms( $post->ID, 'product_cat' );
		if ( is_array( $categories ) ) {
			foreach ( $categories as $category ) {
				// Check de bovenliggende cateogrie
				if ( ! empty( $category->parent ) ) {
					$parent = get_term( $category->parent, 'product_cat' );
					if ( array_key_exists( 'synonyms', $relevanssi_variables ) ) {
						// Laat de synoniemenlijst eerst nog even inwerken
						$search = array_keys($relevanssi_variables['synonyms']);
						$replace = array_values($relevanssi_variables['synonyms']);
						$content .= str_ireplace( $search, $replace, $parent->name ).' ';
					} else {
						// Voeg direct toe
						$content .= $parent->name.' ';
					}
				}
			}
		}
		return $content;
	}
	
	// Verleng de logs tot 90 dagen
	add_filter( 'relevanssi_30days', function() { return 90; } );



	#############
	# DEBUGGING #
	#############

	// Verberg updates van plugins die we gehackt hebben!
	add_filter( 'site_transient_update_plugins', 'disable_plugin_updates' );

	function disable_plugin_updates( $value ) {
		if ( wp_get_environment_type() === 'production' ) {
			if ( isset( $value ) and is_object( $value ) ) {
				$disabled_plugin_updates = array( 'woocommerce-force-sells', 'woocommerce-multistore', 'woocommerce-shipping-local-pickup-plus', 'wp-store-locator' );
				foreach ( $disabled_plugin_updates as $slug ) {
					if ( isset( $value->response[ $slug.'/'.$slug.'.php' ] ) ) {
						unset( $value->response[ $slug.'/'.$slug.'.php' ] );
					}
				}
			}
		}
		return $value;
	}

	// Verhinder het lekken van gegevens uit de API aan niet-ingelogde gebruikers
	add_filter( 'rest_authentication_errors', 'only_allow_administrator_rest_access' );

	function only_allow_administrator_rest_access( $access ) {
		if ( ! is_user_logged_in() or ! current_user_can('manage_options') ) {
			return new WP_Error( 'rest_cannot_access', 'Access prohibited!', array( 'status' => rest_authorization_required_code() ) );
		}
		return $access;
	}

	// Verstuur een mail naar de helpdesk uit naam van de lokale webshop
	function send_automated_mail_to_helpdesk( $subject, $body ) {
		$headers = array();
		$headers[] = 'From: '.get_webshop_name().' <'.get_option('admin_email').'>';
		$headers[] = 'Content-Type: text/html';
		// $body moét effectief HTML-code bevatten, anders werpt WP Mail Log soms een error op!
		wp_mail( 'Helpdesk E-Commerce <e-commerce@oft.be>', $subject, $body, $headers );
	}

	// Print variabelen op een overzichtelijke manier naar debug.log
	if ( ! function_exists( 'write_log' ) ) {
		function write_log( $log )  {
			if ( defined('WP_DEBUG_LOG') and WP_DEBUG_LOG ) {
				if ( is_array( $log ) or is_object( $log ) ) {
					$log = serialize( $log );
				}
				error_log( "[".date_i18n('d/m/Y H:i:s')."] ".$log."\n", 3, WP_CONTENT_DIR.'/activity.log' );
			}
		}
	}

	// Verwissel twee associatieve keys in een array
	function array_swap_assoc( $key1, $key2, $array ) {
		$new_array = array();
		foreach ( $array as $key => $value ) {
			if ( $key == $key1 ) {
				$new_array[$key2] = $array[$key2];
			} elseif ( $key == $key2 ) {
				$new_array[$key1] = $array[$key1];
			} else {
				$new_array[$key] = $value;
			}
		}
		return $new_array;
	}

	// Creëer een random sequentie (niet gebruiken voor echte beveiliging)
	function generate_pseudo_random_string( $length = 10 ) {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$characters_length = strlen($characters);
		$random_string = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$random_string .= $characters[rand( 0, $characters_length - 1 )];
		}
		return $random_string;
	}

	// Overzichtelijkere debugfunctie definiëren
	function var_dump_pre( $variable ) {
		echo '<pre>';
		var_dump($variable);
		echo '</pre>';
		return NULL;
	}
?>