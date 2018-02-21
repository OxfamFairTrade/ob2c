<!DOCTYPE html>
<html>

<head>
	<meta charset="UTF-8">
	<title>Logomateriaal Oxfam-Wereldwinkels</title>
</head>

<body style="margin: 20px;">

	<?php
		require_once 'wp-load.php';
		
		// Nodig want op de main site worden de gewoon de gegevens van de CVBA geretoureneerd (ID 28 = Regio Leuven)
		switch_to_blog( 28 );

		global $wpdb;
		// Vraag ID's en namen van alle gepubliceerde winkels op
		$results = $wpdb->get_results( "SELECT nid, title FROM node WHERE type = 'sellpoint' AND status = 1", OBJECT );
		
		// Print de header die InDesign begrijpt
		$header = array( 'naam', 'straat', 'stad', 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag', 'zondag', 'email', 'rekeningnummer', 'btw', 'telefoon', 'fax' );
		echo implode( ';', $header ).'<br>';

		foreach ( $results as $sellpoint ) {
			$office_hours = get_office_hours($sellpoint->nid);
			// var_dump_pre($office_hours);
			
			$data[] = $sellpoint->title;
			$data[] = get_oxfam_shop_data( 'place', $sellpoint->nid );
			$data[] = get_oxfam_shop_data( 'zipcode', $sellpoint->nid).' '.get_oxfam_shop_data( 'city', $sellpoint->nid);
			
			foreach( $office_hours as $day ) {
				if ( $day === false ) {
					$data[] = 'Gesloten';
				} else {
					foreach( $day as $hours ) {
						$parts[] = implode( ' - ', $hours );
					}
					$data[] = implode( ' en ', $parts );
					unset($parts);
				}
			}
			
			// Zit momenteel nog niet in de tabellen!
			$data[] = get_oxfam_shop_data( 'mail', $sellpoint->nid);
			$data[] = get_oxfam_shop_data( 'account', $sellpoint->nid);
			$data[] = get_oxfam_shop_data( 'tax', $sellpoint->nid);
			$data[] = get_oxfam_shop_data( 'telephone', $sellpoint->nid);
			// Zit momenteel nog niet in de tabellen!
			$data[] = get_oxfam_shop_data( 'fax', $sellpoint->nid);
			
			echo implode( ';', $data ).'<br>';
			unset($data);
		}
		
	?>

</body>

</html>