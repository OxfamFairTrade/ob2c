<?php
	
	if ( ! defined('ABSPATH') ) exit;
	
	#############
	# MAILCHIMP #
	#############
	
	// Controleer of de klant zich wenst in te schrijven op de nieuwsbrief
	// Actie wordt doorlopen na SUCCESVOLLE checkout (order reeds aangemaakt)
	add_action( 'woocommerce_checkout_update_order_meta', 'check_mailchimp_subscription_on_checkout', 100, 2 );
	
	function check_mailchimp_subscription_on_checkout( $order_id, $data ) {
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
			
			// Gegevens asynchroon doorsturen via Action Scheduler, zodat checkout niet vertraagt!
			$args = array( 'post_data' => $post_data );
			if ( ! as_enqueue_async_action( 'call_external_mailchimp_subscribe_action', $args, 'MailChimp' ) > 0 ) {
				write_log('Something went wrong, could not schedule '.$post_data['email'].' for MailChimp subscribe');
			}
		}
	}
	
	add_action( 'call_external_mailchimp_subscribe_action', 'call_external_mailchimp_subscribe', 10, 1 );
	
	function call_external_mailchimp_subscribe( $post_data ) {
		$settings = array(
			'timeout' => 30,
			'body' => $post_data,
		);
		
		$response = wp_remote_post( get_stylesheet_directory_uri().'/functions/mailchimp/subscribe.php', $settings );
		// Eventueel zouden we de actie opnieuw kunnen schedulen indien het antwoord onbevredigend was
		// Maar dan moet onze webservice met duidelijkere headers antwoorden ...
		$result = json_decode( wp_remote_retrieve_body( $response ) );
		file_put_contents( dirname( ABSPATH, 1 )."/mailchimp_instructions.csv", date_i18n('d/m/Y H:i:s')."\t\t".$post_data['email']."\t\t".$result->status."\n", FILE_APPEND );
	}
	
	function get_latest_newsletters_in_folder( $list_id = '5cce3040aa', $folder_id = 'bbc1d65c43' ) {
		require_once WP_PLUGIN_DIR.'/mailchimp-api-wrapper.php';
		$mailchimp = new \DrewM\MailChimp\MailChimp( MAILCHIMP_APIKEY );
		
		$settings = array(
			'list_id' => $list_id,
			'since_send_time' => date_i18n( 'Y-m-d', strtotime('-6 months') ),
			'status' => 'sent',
			'folder_id' => $folder_id,
			'sort_field' => 'send_time',
			'sort_dir' => 'DESC',
		);
		$retrieve = $mailchimp->get( 'campaigns', $settings );
		
		$mailings = "";
		if ( $mailchimp->success() ) {
			$mailings .= "<p>Dit zijn de nieuwsbrieven van de afgelopen zes maanden:</p>";
			$mailings .= "<ul style='margin-left: 20px; margin-bottom: 1em;'>";
			
			foreach ( $retrieve['campaigns'] as $campaign ) {
				$mailings .= '<li><a href="'.$campaign['long_archive_url'].'" target="_blank">'.$campaign['settings']['subject_line'].'</a> ('.date_i18n( 'j F Y', strtotime( $campaign['send_time'] ) ).')</li>';
			}
			
			$mailings .= "</ul>";
		}
		
		return $mailings;
	}
	
	function get_mailchimp_member_status_in_list( $list_id = '5cce3040aa' ) {
		$current_user = wp_get_current_user();
		$email = $current_user->user_email;
		$response = get_mailchimp_member_in_list_by_email( $email, $list_id );
		
		switch ( $list_id ) {
			case '5cce3040aa':
				$list_name = 'het Digizine';
				break;
			
			default:
				$list_name = 'lijst '.$list_id;
		}
		
		$msg = "";
		if ( $response ) {
			if ( $response['status'] === "subscribed" ) {
				$msg .= "al geabonneerd op ".$list_name.". Aan het begin van elke maand ontvang je dus een (h)eerlijke mail boordevol fairtradenieuws.";
			} else {
				$msg .= "helaas niet langer geabonneerd op ".$list_name.". Vul <a href='https://oxfamwereldwinkels.us3.list-manage.com/subscribe?u=d66c099224e521aa1d87da403&id=".$list_id."&FNAME=".$current_user->user_firstname."&LNAME=".$current_user->user_lastname."&EMAIL=".$email."&SOURCE=webshop' target='_blank'>het formulier</a> in om op je stappen terug te keren!";
			}
		} else {
			$msg .= "nog nooit geabonneerd geweest op ".$list_name.". Vul <a href='https://oxfamwereldwinkels.us3.list-manage.com/subscribe?u=d66c099224e521aa1d87da403&id=".$list_id."&FNAME=".$current_user->user_firstname."&LNAME=".$current_user->user_lastname."&EMAIL=".$email."&SOURCE=webshop' target='_blank'>het formulier</a> in om daar verandering in te brengen!";
		}
		
		return "<p>Je bent met het e-mailadres <a href='mailto:".$email."' target='_blank'>".$email."</a> ".$msg."</p>";
	}
	
	function get_mailchimp_member_in_list_by_email( $email, $list_id ) {
		require_once WP_PLUGIN_DIR.'/mailchimp-api-wrapper.php';
		$mailchimp = new \DrewM\MailChimp\MailChimp( MAILCHIMP_APIKEY );
		$response = $mailchimp->get( 'lists/'.$list_id.'/members/'.md5( format_mail( $email ) ) );
		
		if ( $mailchimp->success() ) {
			return $response;
		} else {
			return false;
		}
	}