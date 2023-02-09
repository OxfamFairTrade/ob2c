<html>

<head></head>

<body>
	<?php
		// Laad de WordPress-omgeving (relatief pad geldig vanuit elk thema)
		require_once '../../../wp-load.php';
		
		$shop_nodes = array( '586', '759', '760', '761', '762', '763', '764', '765', '766', '767', '768', '770', '771', '772', '773', '774', '775', '776', '777', '778', '779', '780', '781', '782', '783', '784', '785', '786', '787', '788', '789', '790', '791', '792', '793', '794', '795', '796', '797', '798', '799', '800', '801', '802', '803', '804', '805', '806', '807', '808', '809', '810', '811', '812', '813', '814', '815', '816', '817', '818', '819', '820', '821', '822', '824', '825', '826', '827', '828', '830', '831', '832', '833', '834', '835', '836', '837', '838', '839', '840', '841', '842', '843', '844', '845', '846', '847', '848', '849', '850', '851', '852', '853', '854', '855', '856', '857', '858', '859', '861', '862', '863', '864', '866', '867', '868', '869', '870', '871', '872', '873', '874', '876', '877', '878', '879', '880', '881', '883', '884', '885', '886', '887', '888', '889', '891', '892', '893', '894', '895', '896', '897', '898', '899', '900', '901', '902', '903', '904', '905', '906', '907', '908', '909', '910', '911', '912', '913', '914', '915', '916', '917', '918', '919', '920', '921', '922', '923', '924', '925', '926', '927', '928', '929', '930', '931', '932', '934', '935', '936', '937', '938', '939', '940', '941', '942', '943', '944', '945', '946', '947', '948', '949', '950', '951', '952', '953', '954', '955', '956', '957', '958', '959', '960', '961', '962', '963', '964', '965', '966', '967', '968', '969', '970', '972', '974', '975', '976', '977', '978', '979', '980', '981', '982', '983', '984', '985', '986', '987', '988', '990', '991', '992', '993', '994', '1483', '1486', '1487', '1488', '1489', '1490', '1491', '1539', '1664', '1918', '2531', '3953', '4165', '4285' );
		
		// Nodig om de get_oxfam_shop_data() functie te laten werken met expliciet opgegeven $node
		switch_to_blog( 23 );
		
		$vzw_nodes = array();
		foreach ( $shop_nodes as $shop_node ) {
			$vzw_node = get_oxfam_shop_data( 'shop', $shop_node );
			if ( ! in_array( $vzw_node, $vzw_nodes ) ) {
				$vzw_nodes[$shop_node] = $vzw_node;
			}
		}
		
		echo '<p>Er staan '.count($vzw_nodes).' unieke VZW\'s geregistreerd in de OWW-site ... Opgelet: onofficiële VZW-namen en niet helemaal in alfabetische volgorde (nieuwste onderaan)!</p>';
		
		foreach ( $vzw_nodes as $shop_node => $vzw_node ) :
		?>
			<p>Oxfam-Wereldwinkel <?php echo get_oxfam_shop_data( 'city', $shop_node ); ?> vzw<ul>
				<li>BTW-nummer: <?php echo get_oxfam_shop_data( 'tax', $shop_node ); ?></li>
				<li><a href="https://kbopub.economie.fgov.be/kbopub/zoeknummerform.html?nummer=<?php echo str_replace( 'BE ', '', get_oxfam_shop_data( 'tax', $shop_node ) ); ?>&actionlu=zoek" target="_blank">Fiche in KBO-databank</a></li>
				<li>OWW-node: <?php echo $shop_node; ?></li>
				<li>VZW-node: <?php echo $vzw_node; ?></li>
			</ul></p>
		<?php
		endforeach;
		
		restore_current_blog();
	?>
</body>

</html>