<?php

	// WordPress volledig inladen, zodat we alle helper functies en constanten kunnen aanspreken
	// Relatief pad enkel geldig vanuit subfolder in subfolder van themamap!
	require_once '../../../../../wp-load.php';
	
	require_once WP_PLUGIN_DIR.'/mailchimp-api-wrapper.php');
	use \DrewM\MailChimp\MailChimp;
	
	// Testlijst
	$list_id = 'd15ad40df2';
	$store_id = 'virtualstore';
	
	// Fictieve deelnemers
	$participants = array(
		226 => array( 0 => 'Kessel-Lo' ),
		1744 => array( 0 => 'Wilsele' ),
		1774 => array( 0 => 'Heverlee' ),
		2115 => array( 0 => 'Leuven' ),
		2240 => array( 0 => 'Wijgmaal' ),
	);
	
	// Lijst actuele producten (later dynamisch op te halen uit de MailChimp-store)
	$mailchimp_products = array( 'W10031', 'W10032', 'W10050', 'W10052', 'W10054', 'W10055', 'W10058', 'W10059', 'W10060', 'W10062', 'W10067', 'W10068', 'W10070', 'W10071', 'W10152', 'W10154', 'W10211', 'W10212', 'W10225', 'W10249', 'W10250', 'W10252', 'W10253', 'W10257', 'W10258', 'W10260', 'W10261', 'W10409', 'W10410', 'W10413', 'W10414', 'W10415', 'W10600', 'W10603', 'W10607', 'W11000', 'W11002', 'W11003', 'W11008', 'W11010', 'W11050', 'W11052', 'W11100', 'W11102', 'W11103', 'W11107', 'W11500', 'W11502', 'W11504', 'W11515', 'W12005', 'W12013', 'W12019', 'W12001', 'W12002', 'W12004', 'W12008', 'W12029', 'W12200', 'W12206', 'W12400', 'W12401', 'W12600', 'W12601', 'W12602', 'W12604', 'W12704', 'W12705', 'X12722', 'X12723', 'W22750', 'W12800', 'W12805', 'W13002', 'W13006', 'W13201', 'W13400', 'W13401', 'W13402', 'W13501', 'W13503', 'W13504', 'W13505', 'W13506', 'W13507', 'W13702', 'W13705', 'W14006', 'X14016', 'W14100', 'W14101', 'W14102', 'W14103', 'W14117', 'W14218', 'W14219', 'W14220', 'W14230', 'W14231', 'W14232', 'W14233', 'W14240', 'X14290', 'X14291', 'W14300', 'W14303', 'W14501', 'W14502', 'W24525', 'W14529', 'W14532', 'W14631', 'W14634', 'W14638', 'W14641', 'W14642', 'W15004', 'X15006', 'W15009', 'W15010', 'X15011', 'X15012', 'W15201', 'W15208', 'W15210', 'W15211', 'W15216', 'W15217', 'W15218', 'W15219', 'W15220', 'W15221', 'W15300', 'W15301', 'W15302', 'W15310', 'W15314', 'W15315', 'X15397', 'X15398', 'X15399', 'W15404', 'W15405', 'W15406', 'W15407', 'W15450', 'W15451', 'X15480', 'X15481', 'W15600', 'W15617', 'X15627', 'X15628', 'X15713', 'X15715', 'X15721', 'X15722', 'X15723', 'X15724', 'W16002', 'W16008', 'W16009', 'W16010', 'W16011', 'W16012', 'X16311', 'X16312', 'X16314', 'W16400', 'W16401', 'W16402', 'X16419', 'X16493', 'X16494', 'W16700', 'W16701', 'W16703', 'W26712', 'W17003', 'W17008', 'W17009', 'X17011', 'W17051', 'W17100', 'W17101', 'W17103', 'W17109', 'W17111', 'W17113', 'W17150', 'X17201', 'X17204', 'X17205', 'W17502', 'W17503', 'W17506', 'X17510', 'X17512', 'X17808', 'X17810', 'X17811', 'X17813', 'X17818', 'X17819', 'X17820', 'X17821', 'X17822', 'W18018', 'X18020', 'W18310', 'W18311', 'W18312', 'X18318', 'X18319', 'X18321', 'X18324', 'X18327', 'X18328', 'X18329', 'W10073', 'W16000', 'X14286', 'X11061', 'X17996', 'X17999', 'X17997', 'X17998', 'X16315', 'W15629', 'W10263', 'W17013', 'X10700', 'X18800', 'X18801', 'X18802', 'X18803', 'X16490', 'X18810', 'X18811', 'W14637', 'W12209', 'W10075', 'W10074', 'W10601', 'W11011', 'W11108', 'X15728', 'W11498', 'W11499', 'X17054', 'X13697', 'X13698', 'X13699', 'X15727', 'M64880' );
	
	// Fictieve promotie
	$mailchimp_promo_products = array( 'GIFT-W10074' => 10 );
	
	// We laten slechts één ticket toe per request!
	$object = simplexml_load_file('php://input');
	$debug = array();

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
		global $list_id, $store_id, $mailchimp_promo_products, $debug;

		$MailChimp = new MailChimp( MAILCHIMP_APIKEY );
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
		global $list_id, $store_id, $debug;
		
		$MailChimp = new MailChimp( MAILCHIMP_APIKEY );
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