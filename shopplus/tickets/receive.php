<?php

	include('../config.php');
	include('../mailchimp.php');
	use \DrewM\MailChimp\MailChimp;

	// We laten slechts één ticket toe per request!
	$object = simplexml_load_file('php://input');

	$debug = array();
	// $debug[] = $object;

	if ( $object !== false and ! empty( $object->authentification ) ) {

		$authentification = $object->authentification;

		if ( ! empty( $authentification->password ) and ! empty( $authentification->billingNumber ) and isset( $authentification->shippingNumber ) ) {
			
			$billing_number_oft = intval( $authentification->billingNumber->__toString() );
			$shipping_number_oft = intval( $authentification->shippingNumber->__toString() );

			if ( $authentification->password->__toString() !== $password ) {
				
				$code = 401;
				$message = "Wachtwoord is ongeldig.";

			} elseif ( ! array_key_exists( $billing_number_oft, $participants ) ) {

				$code = 401;
				$message = "Klantnummer is geen deelnemer.";

			} elseif ( ! array_key_exists( $shipping_number_oft, $participants[$billing_number_oft] ) ) {

				$code = 401;
				$message = "Levernummer is geen deelnemer.";

			} else {
				
				if ( ! empty( $object->ticket ) ) {

					$ticket = $object->ticket;

					if ( empty( $ticket->header->ticketNumber ) ) {

						$code = 403;
						$message = "Ticketnummer ontbreekt.";

					} elseif ( empty( $ticket->header->orderDate ) ) {

						$code = 403;
						$message = "Ticketdatum ontbreekt.";

					} elseif ( empty( $ticket->header->orderTotal ) ) {

						$code = 403;
						$message = "Tickettotaal ontbreekt.";

					} elseif ( empty( $ticket->header->clientNumber ) ) {

						$code = 403;
						$message = "Klantnummer ontbreekt.";

					} else {

						// WE ZIJN NU ZEKER DAT ALLE REQUIRED VELDEN AANWEZIG ZIJN
						// $ticket_number = $ticket->header->ticketNumber->__toString();
						// $date = date( 'd/m/Y', strtotime( $ticket->header->orderDate->__toString() ) );
						// $total = floatval( $ticket->header->orderTotal->__toString() );
						
						// ZOEK OP MERGE FIELD WERKT NOG NIET VIA API
						// $member_hash = get_mailchimp_member_by_client_number( $ticket->header->clientNumber->__toString() );
						$client = $ticket->header->clientNumber->__toString();
						$clients = array( 'OWW123456' => 'info@koekedozeklan.be', 'OWW111111' => 'freeaanzee@gmail.com', 'OWW999999' => 'info@fullstackahead.be', 'OWW100001' => 'e-commerce@oft.be' );
						// E-mailadres moet lowercase zijn!
						$member_hash = md5( strtolower( $clients[$client] ) );
						
						$result = add_order_to_mailchimp_member( $member_hash, $ticket, $participants[$billing_number_oft][$shipping_number_oft] );
						$code = $result['code'];
						$message = $result['message'];
					}

				} else {
					$code = 400;
					$message = "Geen ticket gevonden.";
				}

			}

		} else {
			$code = 400;
			$message = "Ontbrekende parameters.";
		}

	} else {
		$code = 400;
		$message = "Geen data gevonden.";
	}

	echo json_response( $code, $message, $debug );

	function add_order_to_mailchimp_member( $member_hash, $ticket, $location ) {
		global $api_key, $list_id, $store_id, $mailchimp_promo_products, $debug;

		$MailChimp = new MailChimp($api_key);
		$retrieve = $MailChimp->get( 'lists/'.$list_id.'/members/'.$member_hash );
		if ( $retrieve['id'] !== $member_hash ) {
			// Voor ShopPlus is de kous wel af, maar eigenlijk moeten wij nu een queue beginnen met probleemgevallen ...
			$debug[] = $retrieve;
		} else {
			$points = intval( $retrieve['merge_fields']['POINTS'] );
			$client_number = $retrieve['merge_fields']['CLIENT'];
			$ticket_number = $ticket->header->ticketNumber->__toString();

			$lines = get_order_lines( $ticket, $ticket_number );
			$promos = get_promo_codes( $ticket, $ticket_number );
			$total = floatval( $ticket->header->orderTotal->__toString() );
			$discount = floatval( $ticket->header->orderDiscount->__toString() );

			$min_points = 0;
			foreach ( $promos as $promo ) {
				if ( array_key_exists( $promo['code'], $mailchimp_promo_products ) ) {
					$cost = $mailchimp_promo_products[ $promo['code'] ];
					$min_points += $cost;
					$note = $MailChimp->post( 'lists/'.$list_id.'/members/'.$member_hash.'/notes', array( 'note' => 'Je ruilde '.$cost.' punten in voor '.$promo['code'].' in OWW '.$location.'.' ) );
					if ( isset( $note['status'] ) ) {
						$debug[] = $note;
					}
				}
			}
			$extra_points = intval( floor( $total / 10 ) );
			
			// Tel de punten er al bij vòòr we eventuele mails verzenden MAAR risicio dat de aanmaak van het ticket mislukt terwijl punten reeds toegekend zijn ...
			$update_args = array(
				'merge_fields' => array( 'POINTS' => $points + $extra_points - $min_points, 'LATEST_PTS' => $extra_points, 'LATEST_LOC' => $location ),
			);
			$update = $MailChimp->patch( 'lists/'.$list_id.'/members/'.$member_hash, $update_args );
			
			if ( $update['id'] !== $member_hash ) {
				$debug[] = $update;
			} else {
				$note = $MailChimp->post( 'lists/'.$list_id.'/members/'.$member_hash.'/notes', array( 'note' => 'Je spaarde '.$extra_points.' punten in OWW '.$location.'.' ) );
				if ( isset( $note['status'] ) ) {
					$debug[] = $note;
				}
				if ( intval( $update['merge_fields']['POINTS'] ) < 0 ) {
					$debug[] = "Deze sloeber heeft een negatief puntenaantal!";
				}
			}

			$create_args = array(
				'id' => $ticket_number,
				// Information about a specific customer. This information will update any existing customer. If the customer doesn’t exist in the store, a new customer will be created.
				// The customer’s opt-in status. This value will never overwrite the opt-in status of a pre-existing Mailchimp list member.
				'customer' => array( 'id' => $client_number, 'email_address' => $retrieve['email_address'], 'opt_in_status' => false ),
				// Dit is de campagne 'Order Notifictions for Wereldwinkelnetwerk'
				'campaign_id' => '0d65a3f02f',
				'currency_code' => 'EUR',
				'order_total' => $total,
				'discount_total' => $discount,
				// Niet doen, want dan moeten we producten overal exclusief BTW ingeven
				// 'tax_total' => floatval( $ticket->header->orderTax->__toString() ),
				// Altijd naar het profiel van de klant linken, komt o.a. voor in de 'refund'-mail
				'order_url' => 'https://shop.oxfamwereldwinkels.be/profile?id='.$member_hash,
				'landing_site' => 'https://www.oxfamwereldwinkels.be/'.strtolower($location),
				'financial_status' => 'pending',
				// Tijdstip in ISO 8601, bv. 2019-02-19T10:02:47+01:00
				'processed_at_foreign' => $ticket->header->orderDate->__toString(),
				'lines' => $lines,
				'promos' => $promos,
			);
			$create = $MailChimp->post( 'ecommerce/stores/'.$store_id.'/orders', $create_args );
			$debug[] = $create;
			
			if ( $create['id'] !== $ticket_number ) {
				$debug[] = $create;
			} else {
				$products_added = array();
				foreach ( $create['lines'] as $line ) {
					$products_added[] = $line['quantity'].'x '.$line['product_title'];
				}

				// Als de klant een bevestiging wil ontvangen migreren we het order naar 'paid' om de order notification te triggeren
				foreach ( $retrieve['marketing_permissions'] as $permission ) {
					if ( $permission['marketing_permission_id'] === '7964efca7f' ) {
						if ( $permission['enabled'] === true ) {
							// Probleem: besteloverzicht kan nog niet vertaald/gewijzigd worden, dus misschien moeten we ons beperken tot het korte 'refunded'-bericht (vuile truc)
							$migrate = $MailChimp->patch( 'ecommerce/stores/'.$store_id.'/orders/'.$ticket_number, array( 'financial_status' => 'paid' ) );
							break;
						} 
					}
				}

				// Met automations of API trigger kunnen we een mailadres slechts eenmalig toevoegen aan een queue (moet bovendien subscriber zijn, geen transactional)
				// $debug[] = $MailChimp->post( 'automations/c0cce767e6/emails/e291a22309/queue', array( 'email_address' => $retrieve['email_address'] ) );
			}
		}

		$code = 202;
		$message .= "Bedankt Stefaan, kasticket van ".date( 'd/m/Y', strtotime( $ticket->header->orderDate->__toString() ) )." in OWW ".$location." goed ontvangen! Klant ".$client_number." spendeerde ".$total." euro, spaarde ".$extra_points." punten en heeft nu in totaal ".$update['merge_fields']['POINTS']." punten. Deze producten werden geregistreerd: ".implode( ', ', $products_added ).".";

		return array( 'code' => $code, 'message' => $message );
	}

	function get_mailchimp_member_by_client_number( $client_number ) {
		global $api_key, $list_id, $store_id, $debug;
		
		$MailChimp = new MailChimp($api_key);
		$search_args = array(
			// Zoekt enkel in e-mailadres, MERGE1 en MERGE2!
			'query' => $client_number,
			'list_id' => $list_id,
		);
		$search = $MailChimp->get( 'search-members', $search_args );
		
		if ( $search['exact_matches']['total_items'] === 1 ) {
			// Precies één klant gevonden, joepie
			$member = reset($search['exact_matches']['members']);
			$member_hash = $member['id'];
		} else {
			$member_hash = false;
			$debug[] = $search;
		}

		return $member_hash;
	}

	function get_order_lines( $ticket, $ticket_number ) {
		global $mailchimp_products;

		$i = 1;
		$lines = array();
		
		foreach ( $ticket->items->children() as $item ) {
			// Knip de prefix voor gratis producten eraf (of maken we ze ook aan in de productdatabase?)
			$shopplus = str_replace( 'GIFT-', '', $item->sku->__toString() );
			$quantity = intval( $item->quantity->__toString() );
			$price = floatval( $item->total->__toString() );
			
			// Voor elk product eerst een GET-query doen zal alles nogal traag maken, dus haal ze liever uit een array die bv. dagelijks bijgewerkt wordt
			if ( in_array( $shopplus, $mailchimp_products ) ) {
				$product_id = $shopplus;
			} else {
				$prefix = substr( $shopplus, 0, 1 );
				switch ( $prefix ) {
					case 'W':
						$product_id = 'OWW';
						break;
					case 'F':
						$product_id = 'OFTC';
						break;
					case 'M':
						$product_id = 'MDM';
						break;
					case 'P':
						$product_id = 'PUUR';
						break;
					default:
						$product_id = 'EXT';
				}
				
			}

			if ( substr( $item->sku->__toString(), 0, 5 ) === 'GIFT-' ) {
				$discount = $price;
			} else {
				$discount = 0;
			}
			$lines[] = array(
				// Misschien vervangen door écht lijnnummer van ShopPlus?
				'id' => $ticket_number.'-'.$i,
				'product_id' => $product_id,
				'product_variant_id' => $product_id,
				'quantity' => $quantity,
				'price' => $price,
				'discount' => $discount,
			);
			$i++;

			// Toch liever in een database stoppen met tabellen verwerkt / in de wachtrij / ...
			$str = date('d/m/Y H:i:s')."\t".$_SERVER['REMOTE_ADDR']."\t".$product_id." - ".$quantity." ex. - ".$price." EUR\n";
			file_put_contents( "items.csv", $str, FILE_APPEND );
		}

		return $lines;
	}

	function get_promo_codes( $ticket, $ticket_number ) {
		global $mailchimp_promo_products;

		$promos = array();
		
		foreach ( $ticket->items->children() as $item ) {
			$shopplus = $item->sku->__toString();
			$quantity = intval( $item->quantity->__toString() );
			$price = floatval( $item->total->__toString() );
			
			// Voor elk product eerst een GET-query doen zal alles nogal traag maken, dus haal ze liever uit een array die bv. dagelijks bijgewerkt wordt
			if ( array_key_exists( $shopplus, $mailchimp_promo_products ) ) {
				$promos[] = array(
					'code' => $shopplus,
					// HOE PRIJS BEPALEN?
					'amount_discounted' => 10.35 * $quantity,
					'type' => 'fixed',
				);
			}
		}

		return $promos;
	}

	function json_response( $code = 200, $message = null, $debug = null ) {
		header_remove();
		header('Cache-Control: no-transform,public,max-age=0');
		header('Content-Type: application/json');
		
		$status = array(
			200 => '200 OK',
			202 => '202 Accepted',
			400 => '400 Bad Request',
			401 => '401 Unauthorized',
			403 => '403 Forbidden',
			500 => '500 Internal Server Error',
		);
		http_response_code($code);
		
		return json_encode(
			array(
				'success' => $code < 300,
				'message' => $message,
				'debug' => $debug,
			)
		);
	}

?>