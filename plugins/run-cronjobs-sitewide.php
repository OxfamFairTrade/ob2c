<?php

// Laad de WordPress-omgeving (relatief pad geldig vanuit domeinmap)
require_once dirname(__FILE__).'/public_html/wp-load.php';

$args = array( 'public' => 1 );
$blogs = get_sites( $args );

global $wp_version;
$agent = 'WordPress/' . $wp_version . '; ' . home_url();

// Run cron on each blog
foreach ( $blogs as $blog ) {
	$domain = $blog->domain;
	$path = $blog->path;
	
	$command = '/usr/local/bin/php ' . dirname(__FILE__) . '/public_html' . ( $path ? $path : '/' ) . 'wp-cron.php doing_wp_cron';
	// Gooit een 'Could not open input file' op maar werkt in de praktijk wel
	$rc = shell_exec( $command );
	
	// Dit vreet I/O omdat het via de front-end loopt (en er ergens iets mis is?)
	// $command = "https://" . $domain . ( $path ? $path : '/' ) . 'wp-cron.php?doing_wp_cron';
	// $ch = curl_init( $command );
	// $rc = curl_setopt( $ch, CURLOPT_RETURNTRANSFER, false );
	// $rc = curl_exec( $ch );
	// curl_close( $ch );

	print_r( "✔ " . $command . PHP_EOL );
}

?>