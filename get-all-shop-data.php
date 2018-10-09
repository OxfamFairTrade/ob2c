<!DOCTYPE html>
<html>

<head>
	<meta charset="UTF-8">
	<title>Logomateriaal Oxfam-Wereldwinkels</title>
</head>

<body style="margin: 20px;">

	<?php
		// Laad de WordPress-omgeving (relatief pad geldig vanuit elk thema)
		require_once '../../../wp-load.php';
		
		// Nodig want op de main site worden de gewoon de gegevens van de CVBA geretoureneerd (ID 28 = Regio Leuven)
		switch_to_blog( 28 );

		if ( isset( $_GET['node'] ) ) {

			echo implode( ';', get_data_for_sellpoint( $_GET['node'] ) );

		} else {

			global $wpdb;
			// Vraag ID's en namen van alle gepubliceerde winkels op
			$results = $wpdb->get_results( "SELECT nid, title FROM node WHERE type = 'sellpoint' AND status = 1", OBJECT );
			
			// Print de header die InDesign begrijpt
			$header = array( 'naam', 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag', 'zondag', 'straat', 'stad', 'email', 'rekeningnummer', 'btw', 'telefoon', 'fax' );
			echo implode( ';', $header ).'<br/>';

			foreach ( $results as $sellpoint ) {
				// Bij numerieke keys voegt array_merge() de elementen gewoon achteraan toe!
				$data = array_merge( $sellpoint->title, get_data_for_sellpoint( $sellpoint->nid ) );
				echo implode( ';', $data ).'<br/>';
				unset($data);
			}

		}

		function get_data_for_sellpoint( $node ) {
			$data = array();
			
			$office_hours = get_office_hours($node);
			foreach( $office_hours as $day ) {
				if ( $day === false ) {
					$data[] = 'Gesloten';
				} else {
					$parts = array();
					foreach( $day as $hours ) {
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

			return $data;
		}
		
	?>

</body>

</html>