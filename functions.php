<?php

	require_once WP_CONTENT_DIR.'/plugins/mollie-reseller-api/autoloader.php';
		
	// Belangrijk voor correcte vertalingen in strftime()
	setlocale( LC_ALL, array('Dutch_Netherlands', 'Dutch', 'nl_NL', 'nl', 'nl_NL.ISO8859-1') );

	// Laad het child theme
	add_action( 'wp_enqueue_scripts', 'theme_enqueue_styles' );

	function theme_enqueue_styles() {
	    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
	}

	// Voeg een custom dashboard widget toe met nieuws over het pilootproject
	add_action( 'wp_dashboard_setup', 'add_pilot_widget' );

	function add_pilot_widget() {
		global $wp_meta_boxes;

		wp_add_dashboard_widget(
			'dashboard_pilot_news_widget',
			'Nieuws over het pilootproject',
			'dashboard_pilot_news_widget_function'
		);

		$dashboard = $wp_meta_boxes['dashboard']['normal']['core'];

		$my_widget = array( 'dashboard_pilot_news_widget' => $dashboard['dashboard_pilot_news_widget'] );
	 	unset( $dashboard['dashboard_pilot_news_widget'] );

	 	$sorted_dashboard = array_merge( $my_widget, $dashboard );
	 	$wp_meta_boxes['dashboard']['normal']['core'] = $sorted_dashboard;
	}
	
	// Stel de inhoud van de widget op
	function dashboard_pilot_news_widget_function() {
		echo "<div class='rss-widget'><p>Hier kunnen we o.a. de rechtstreekse links naar de mailings uit de 'Focusgroep Webshop'-map laten verschijnen. Of rechtstreeks linken naar onze FAQ. Dankzij Markdown gaat dat <a href='https://github.com/OxfamFairTrade/ob2c/wiki/FAQ#bestellingen' target='_blank'>heel makkelijk</a>.</p></div>";
		echo '<div class="rss-widget"><ul>'.get_latest_mailings().'</ul></div>';
	}

	// Schakel onnuttige widgets uit voor iedereen
	// add_action( 'admin_init', 'remove_dashboard_meta' );

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
		$server = substr(MAILCHIMP_APIKEY, strpos(MAILCHIMP_APIKEY, '-')+1);
		$list_id = '53ee397c8b';
		$folder_id = '2a64174067';

	    $args = array(
		 	'headers' => array(
				'Authorization' => 'Basic ' .base64_encode('user:'.MAILCHIMP_APIKEY)
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

	// Voeg optievelden toe
	add_action( 'admin_init', 'register_oxfam_settings' );

	function register_oxfam_settings() {
		register_setting( 'oxfam-option-group', 'mollie_partner_id', 'absint' );
		// add_settings_section( 'mollie_partner_id', 'Partner-ID bij Mollie', 'eg_setting_section_callback_function', 'options-oxfam' );
		add_settings_field( 'mollie_partner_id', 'Partner-ID bij Mollie', 'eg_setting_callback_function', 'options-oxfam', 'default', array( 'label_for' => 'mollie_partner_id' ) );
	}

	function eg_setting_callback_function( $arg ) {
		echo '<p>id: ' . $arg['id'] . '</p>';
		echo '<p>title: ' . $arg['title'] . '</p>';
		echo '<p>callback: ' . $arg['callback'] . '</p>';
	}

	// Voeg een custom pagina toe onder de algemene opties
	add_action( 'admin_menu', 'custom_oxfam_options' );

	function custom_oxfam_options() {
		add_options_page( 'Instellingen voor lokale webshop', 'Oxfam Fair Trade', 'shop_manager', 'options-oxfam.php', 'options_oxfam' );
	}

	// Output voor de optiepagina
	function options_oxfam() {
		?>
			<div class="wrap">
				<h1>Instellingen voor lokale webshop</h1>
				<form method="post" action="options.php"> 
			<?php
				settings_fields( 'oxfam-option-group' );
				do_settings_sections( 'oxfam-option-group' );
			?>
				<table class="form-table">
        		<tr valign="top">
        			<th scope="row">Test</th>
      	  			<td><input type="text" name="mollie_partner_id" value="<?php echo esc_attr( get_option('partner_id_customer') ); ?>" /></td>
        		</tr>
        	<?php
				submit_button();

				Mollie_Autoloader::register();
				$partner_id = 2485891;
				$profile_key = 'C556F53A';

				$mollie = new Mollie_Reseller( $partner_id, $profile_key, MOLLIE_APIKEY );
				$partner_id_customer = '2842281';

				$simplexml = $mollie->getLoginLink( $partner_id_customer );
				echo "<p><a href='".$simplexml->redirect_url."' target='_blank'>Ga zonder wachtwoord naar je Mollie-betaalaccount!</a> Opgelet: deze link is slechts tijdelijk geldig. Herlaad desnoods even deze pagina.</p>";
			?>
				</form>
			</div>
		<?php
	}

	// Voeg een bericht toe bovenaan alle adminpagina's
	// add_action( 'admin_notices', 'sample_admin_notice' );

	function sample_admin_notice() {
        global $pagenow, $current_user;
	    if ( $pagenow === 'index.php' and current_user_can( 'manage_options' ) ) {
	    	if ( ! get_user_meta( $current_user->ID, 'bancontact_20170131' ) ) {
				?>
			    <div class="notice notice-info is-dismissible">
			        <p>Betalingen met Bancontact zijn tijdelijk onmogelijk! We werken aan een oplossing.</p>
			    </div>
			    <?php
			}
		}
	}
?>