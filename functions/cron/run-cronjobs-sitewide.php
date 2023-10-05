<?php

	// WordPress volledig inladen, zodat we alle helper functies en constanten kunnen aanspreken
	// Gebruik van absoluut pad vereist om ook te werken als cron job met PHP i.p.v. WGET
	// dirname() enkel geldig vanuit een Antagonist-domeinmap!
	require_once dirname( __FILE__, 1 ) . '/public_html/wp-load.php';
	
	// Altijd op alle subsites laten draaien!
	$sites = get_sites();
	
	global $wp_version;
	$agent = 'WordPress/' . $wp_version . '; ' . home_url();
	$interval = 1;
	print_r( 'Sleep interval: ' . $interval . ' seconds' . PHP_EOL . PHP_EOL );
	$start = microtime(true);
	
	foreach ( $sites as $site ) {
		// Gooit een 'Could not open input file' op en werkt in de praktijk enkel op het hoofdniveau
		// $path = $site->path;
		// $command = '/usr/local/bin/php ' . dirname(__FILE__) . '/public_html' . ( $path ? $path : '/' ) . 'wp-cron.php doing_wp_cron';
		// $rc = shell_exec( $command );
		
		switch_to_blog( $site->blog_id );
		
		// Schakel enkele onbelangrijke maar trage acties uit
		$to_unhook = array( 'check_plugin_updates-user-role-editor-pro', 'woocommerce_tracker_send_event' );
		foreach ( $to_unhook as $event ) {
			$unhooked = wp_clear_scheduled_hook( $event );
			if ( intval( $unhooked ) > 0 ) {
				print_r( $unhooked . ' \'' . $event . '\' events unhooked' . PHP_EOL );
			}
		}
		
		$command = home_url( 'wp-cron.php?doing_wp_cron' );
		$ch = curl_init( $command );
		$rc = curl_setopt( $ch, CURLOPT_RETURNTRANSFER, false );
		$rc = curl_exec( $ch );
		curl_close( $ch );
		
		print_r( number_format( microtime(true)-$start, 2, ',', '.' ) . ' s - ' . $command . PHP_EOL );
		restore_current_blog();
		
		// Doe het rustig aan (telt niet mee voor max_execution_time)
		sleep( $interval );
	}

?>