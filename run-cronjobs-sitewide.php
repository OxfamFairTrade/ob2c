<?php

// Laad de WordPress-omgeving (relatief pad geldig vanuit elk thema)
require_once '../../../wp-load.php';

global $wp_version;

// Get Blogs
$args = array( 'public' => 1 );
$blogs = get_sites( $args );

// Run Cron on each blog
echo "Running Crons: " . PHP_EOL;
$agent = 'WordPress/' . $wp_version . '; ' . home_url();
$time  = time();

foreach ( $blogs as $blog ) {
	$domain = $blog->domain;
	$path = $blog->path;
	$command = "http://" . $domain . ( $path ? $path : '/' ) . 'wp-cron.php?doing_wp_cron=' . $time . '&ver=' . $wp_version;

	$ch = curl_init( $command );
	$rc = curl_setopt( $ch, CURLOPT_RETURNTRANSFER, false );
	$rc = curl_exec( $ch );
	curl_close( $ch );

	print_r( $rc );
	print_r( "\t✔ " . $command . PHP_EOL );
}

?>