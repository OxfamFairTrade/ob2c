<?php

	include('config.php');
	include('mailchimp.php');
	use \DrewM\MailChimp\MailChimp;
	
	$MailChimp = new MailChimp($api_key);

	$response = array();

	// E-mailadres is al gelowercased
	$retrieve = $MailChimp->get( 'lists/'.$list_id.'/members/'.md5($_POST['email']) );
	
	if ( $_POST['newsletter'] === 'true' ) {

		if ( $retrieve['status'] !== 404 ) {
			
			// Stop enkel de velden die nog leeg zijn in de array
			$settings = array( 'merge_fields' );
			$fields = array( 'GENDER', 'FNAME', 'LNAME', 'SHOP' );
			foreach ( $fields as $key ) {
				if ( $retrieve['merge_fields'][$key] === '' ) {
					$settings['merge_fields'][$key] = $_POST[strtolower($key)];
				}
			}

			if ( $retrieve['status'] !== 'subscribed' ) {
				// De statussen 'cleaned', 'unsubscribed' en 'pending' moeten we opnieuw inschrijven via opt-in (anders compliance error)
				$settings['status'] = 'pending';
				// In dit geval mogen we de oorsprong ook overschrijven met de nieuwe source!
				$settings['merge_fields']['SOURCE'] = 'klantenkaart';
			}

			// Werk de bestaande abonnee bij
			$update = $MailChimp->patch( 'lists/'.$list_id.'/members/'.md5($_POST['email']), $settings );
			
			if ( $update['status'] === 'subscribed' ) {
				
				$response['status'] = 'updated';
				
				if ( do_mailing() ) {
					$response['status'] .= ' and notified';
				} else {
					$response['status'] .= ' but notification error';
				}

			} elseif ( $update['status'] === 'pending' ) {

				$response['status'] = 'resubscribed';

				// Bedanking én verwelkoming wordt verstuurd vanuit MailChimp

			} else {

				$response['status'] = 'update error';
				$response['error'] = $update['detail'];

				if ( do_mailing() ) {
					$response['status'] .= ' and notified';
				} else {
					$response['status'] .= ' but notification error';
				}
			}

		} else {

			// Volledig nieuwe member registreren met alle velden én oorsprong
			$settings = array(
				'email_address' => $_POST['email'],
				'status' => 'subscribed',
				'merge_fields' => array( 'FNAME' => $_POST['fname'], 'LNAME' => $_POST['lname'], 'SHOP' => $_POST['shop'], 'SOURCE' => 'cacaopetitie2018' )
			);
			$create = $MailChimp->post( 'lists/'.$list_id.'/members', $settings );

			if ( $create['status'] === 'subscribed' ) {
				$response['status'] = 'subscribed';
			} else {
				$response['status'] = 'create error';
				$response['error'] = $create['detail'];
			}

			// Bedanking én verwelkoming wordt verstuurd vanuit MailChimp

		}

	}

	// Altijd proberen: tag toevoegen door abonnee in statisch segment te stoppen 
	$segment_id = '48093';
	$tag = $MailChimp->post( 'lists/'.$list_id.'/segments/'.$segment_id, array(
		'members_to_add' => array( $_POST['email'] ),
	) );

	// Altijd proberen: notitie op profiel van abonnee plaatsen
	$shop = '';
	if ( strlen($_POST['shop']) > 1 ) {
		$shop = ' in OWW '.$_POST['shop'];
	}
	$annotate = $MailChimp->post( 'lists/'.$list_id.'/members/'.md5($_POST['email']).'/notes', array(
		'note' => $_POST['fname'].' ondertekende'.$shop.' de petitie voor een eerlijke cacaosector.',
	) );

	$str = date('d/m/Y H:i:s')."\t".$_SERVER['REMOTE_ADDR']."\t".$_POST['email'].": ".$response['status']."\n";
	file_put_contents( "logs.csv", $str, FILE_APPEND );
	
	echo json_encode($response);

?>