<?php 
	global $current_store, $wpsl_settings, $wpsl;

	$options = array();
	$global_zips = get_webshops_by_postcode();
	$all_zips = get_site_option('oxfam_flemish_zip_codes');
	foreach ( $all_zips as $zip => $city ) {
		if ( isset( $global_zips[ $zip ] ) ) {
			$url = $global_zips[ $zip ];
		} else {
			$url = '';
		}
		// Eigenlijk mag dit weggelaten worden?
		// $options[] = '<input type="hidden" id="'.$zip.'" value="'.$url.'">';
	}

	// Laat extra CSS voor kaarten weg
	// $output = $this->get_custom_css(); 
	$autoload_class = ( ! $wpsl_settings['autoload'] ) ? 'class="wpsl-not-loaded"' : '';

	$output .= '<div id="wpsl-wrap" class="wpsl-store-below">' . "\r\n";
	$output .= "\t" . '<div class="wpsl-search wpsl-clearfix ' . $this->get_css_classes() . '">' . "\r\n";
	$output .= "\t\t" . '<div id="wpsl-search-wrap">' . "\r\n";
	$output .= "\t\t\t" . '<form autocomplete="off">' . "\r\n";
	$output .= "\t\t\t" . '<div class="wpsl-input">' . "\r\n";
	$output .= "\t\t\t\t" . '<input id="wpsl-search-input" class="autocomplete-postcodes" type="text" placeholder="Zoek op postcode" value="' . apply_filters( 'wpsl_search_input', '' ) . '" name="wpsl-search-input" placeholder="" aria-required="true" />' . "\r\n";
	$output .= "\t\t\t\t" . implode( "\r\n", $options )  . "\r\n";
	$output .= "\t\t\t" . '</div>' . "\r\n";

	$output .= "\t\t\t\t" . '<div class="wpsl-search-btn-wrap"><input id="wpsl-search-btn" class="frederik" type="submit" value="' . esc_attr( $wpsl->i18n->get_translation( 'search_btn_label', __( 'Search', 'wpsl' ) ) ) . '"></div>' . "\r\n";

	$output .= "\t\t" . '</form>' . "\r\n";
	$output .= "\t\t" . '</div>' . "\r\n";
	$output .= "\t" . '</div>' . "\r\n";

	// Moét blijven staan, anders stopt WPSL gewoon met resultaten zoeken!
	$output .= "\t" . '<div id="wpsl-gmap" class="wpsl-gmap-canvas" style="height: 0 !important;"></div>' . "\r\n";
	$output .= "\t" . '<div id="wpsl-result-list" style="display: none;">' . "\r\n";
	$output .= "\t\t" . '<div id="wpsl-stores" '. $autoload_class .'>' . "\r\n";
	// Deze wrapper moet blijven staan!
	$output .= "\t\t\t" . '<ul>' . "\r\n";
	// Default content, in afwachting van ingeven postcode
	$output .= "\t\t\t\t" . '<p>Laatst gezocht: ' . $current_store . '</p>' . "\r\n";
	$output .= "\t\t\t\t" . '<ul class="benefits">' . "\r\n";
	$output .= "\t\t\t\t\t" . '<li>Gratis verzending vanaf 50 euro</li>' . "\r\n";
	$output .= "\t\t\t\t\t" . '<li>Wij kopen rechtreeks bij kwetsbare producenten, met oog voor ecologische duurzaamheid</li>' . "\r\n";
	$output .= "\t\t\t\t\t" . '<li>Met jouw aankoop steun je onze strijd voor een structureel eerlijk handelssysteem</li>' . "\r\n";
	$output .= "\t\t\t\t" . '</ul>' . "\r\n";
	$output .= "\t\t\t" . '</ul>' . "\r\n";
	$output .= "\t\t" . '</div>' . "\r\n";
	$output .= "\t" . '</div>' . "\r\n";

	$output .= '</div>' . "\r\n";

	return $output;

/* Omit closing PHP tag at the end of PHP files to avoid "headers already sent" issues. */
