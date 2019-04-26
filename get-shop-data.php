<?php
	
	// Laad de WordPress-omgeving (relatief pad geldig vanuit elk thema)
	require_once '../../../wp-load.php';
	
	// Nodig want op de main site worden de gewoon de gegevens van de CVBA geretoureneerd (ID 28 = Regio Leuven)
	switch_to_blog(28);

	if ( isset( $_GET['node'] ) ) {

		// Retourneer de winkeleigenschappen als JSON
		header('Content-Type:application/json;Charset=utf-8');
		echo json_encode( get_data_for_sellpoint( $_GET['node'] ) );

	} else {

		// Retourneer de lijst met alle data als CSV
		echo '<!DOCTYPE html><head><meta charset="UTF-8"><title>Logomateriaal Oxfam-Wereldwinkels</title></head><body margin="20px;">';
		
		global $wpdb;
		// Vraag ID's en namen van alle gepubliceerde winkels op
		$results = $wpdb->get_results( "SELECT nid, title FROM node WHERE type = 'sellpoint' AND status = 1", OBJECT );
		
		// Print de header die InDesign begrijpt
		$header = array( 'naam', 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag', 'zondag', 'straat', 'stad', 'email', 'rekeningnummer', 'btw', 'telefoon', 'fax', 'uren per week', '_drupal_nid', 'wpsl_hours' );
		echo implode( '|', $header ).'<br/>';

		foreach ( $results as $sellpoint ) {
			// Bij numerieke keys voegt array_merge() de elementen gewoon achteraan toe!
			$data = array_merge( array( $sellpoint->title ), get_data_for_sellpoint( $sellpoint->nid ) );
			echo implode( '|', $data ).'<br/>';
		}

		echo '</body></html>';

	}

	function get_data_for_sellpoint( $node ) {
		$data = array();
		$total_hours = 0;
		
		$office_hours = get_office_hours($node);
		foreach ( $office_hours as $day ) {
			if ( $day === false ) {
				$data[] = 'Gesloten';
			} else {
				$parts = array();
				foreach ( $day as $hours ) {
					$begin_hour = explode( ':', $hours['start'] );
					$end_hour = explode( ':', $hours['end'] );
					$total_hours += ( intval($end_hour[0]) - intval($begin_hour[0]) );
					$total_hours += ( intval($end_hour[1]) / 60 - intval($begin_hour[1]) / 60 );
					$parts[] = implode( ' - ', $hours );
				}
				$data[] = implode( ' en ', $parts );
				unset($parts);
			}
		}
		
		// Vraag steeds de 'raw'-info op (zonder webshopcorrecties) door als 3de parameter 'true' mee te geven 
		$data[] = get_oxfam_shop_data( 'place', $node, true );
		$data[] = get_oxfam_shop_data( 'zipcode', $node, true ).' '.get_oxfam_shop_data( 'city', $node, true );
		$data[] = get_oxfam_shop_data( 'mail', $node, true );
		$data[] = get_oxfam_shop_data( 'account', $node, true );
		$data[] = get_oxfam_shop_data( 'tax', $node, true );
		$data[] = get_oxfam_shop_data( 'telephone', $node, true );
		$data[] = get_oxfam_shop_data( 'fax', $node, true );
		$data[] = number_format( $total_hours, 1, ',', '' );
		
		$wpsl_hours = array();
		$labels = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
		foreach ( $office_hours as $day_index => $day ) {
			if ( $day === false ) {
				$info = array();
			} else {
				$info = array();
				foreach ( $day as $hours ) {
					$info[] = $hours['start'].','.$hours['end'];
				}
				
			}
			$wpsl_hours[$labels[$day_index-1]] = $info;
		}

		$data[] = $node;
		$data[] = serialize($wpsl_hours);

		return $data;
	}
	
?>