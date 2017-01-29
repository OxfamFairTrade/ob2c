<?php
	
	// Belangrijk voor leveringsdagen!
	date_default_timezone_set("Europe/Brussels");
	// Geïnstalleerde taal lijkt niet 'nl_NL' te zijn, lokalisatie werkt enkel met strftime()!
	setlocale(LC_ALL, array('Dutch_Netherlands', 'Dutch', 'nl_NL', 'nl', 'nl_NL.ISO8859-1'));

	// Laad het child theme
	add_action( 'wp_enqueue_scripts', 'theme_enqueue_styles' );

	function theme_enqueue_styles() {
	    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
	}

	// Voeg een custom dashboard widget toe met nieuws over het pilootproject
	add_action( 'wp_dashboard_setup', 'add_pilot_widget' );

	function add_pilot_widget() {
		wp_add_dashboard_widget(
			'dashboard_pilot_news_widget',
			'Nieuws over het pilootproject',
			'dashboard_pilot_news_widget_function'
		);	
	}
	
	// Stel de inhoud van de widget op
	function dashboard_pilot_news_widget_function() {
		echo "<div class='rss-widget'><p>Hier kunnen we o.a. de rechtstreekse links naar de mailings uit de 'Focusgroep Webshop'-map laten verschijnen. Of rechtstreeks linken naar onze FAQ. Dankzij Markdown gaat dat <a href='https://github.com/OxfamFairTrade/ob2c/wiki/FAQ#bestellingen' target='_blank'>heel makkelijk</a>.</p></div>";
		echo '<div class="rss-widget"><ul>'.get_latest_mailings().'</ul></div>';
	}

	// Schakel onnuttige widgets uit voor iedereen
	add_action( 'admin_init', 'remove_dashboard_meta' );

	function remove_dashboard_meta() {
		// remove_meta_box( 'dashboard_right_now', 'dashboard', 'normal' );
		remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' );
		// remove_meta_box( 'dashboard_pilot_news_widget', 'dashboard', 'normal' );
		remove_meta_box( 'woocommerce_dashboard_recent_reviews', 'dashboard', 'normal' );
		// remove_meta_box( 'woocommerce_dashboard_status', 'dashboard', 'normal' );
		remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
		remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
		remove_action( 'welcome_panel', 'wp_welcome_panel' );
    }

	function get_latest_mailings() {
		$server = substr(MC_APIKEY, strpos(MC_APIKEY, '-')+1);
		$list_id = '53ee397c8b';
		$folder_id = '0ad3dbe117';

	    $args = array(
		 	'headers' => array(
				'Authorization' => 'Basic ' .base64_encode('user:'.MC_APIKEY)
			)
		);

		$response = wp_remote_get( 'https://'.$server.'.api.mailchimp.com/3.0/campaigns?since_send_time='.date( 'Y-m-d', strtotime('-6 months') ).'&status=sent&list_id='.$list_id.'&folder_id='.$folder_id, $args );
		
		$mailings = "";
		if ( $response['response']['code'] == 200 ) {
			$body = json_decode($response['body']);
			
			foreach ( array_reverse($body->campaigns) as $campaign ) {
				$mailings .= '<li><a class="rsswidget" href="'.$campaign->long_archive_url.'" target="_blank">'.$campaign->settings->subject_line.'</a> ('.strftime( '%e %B %G', strtotime($campaign->send_time) ).')</li>';
			}
		}		

		return $mailings;
	}

?>