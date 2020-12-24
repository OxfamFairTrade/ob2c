<?php
/*
Plugin Name: A faster load_textdomain
Version: 0.0.2
Description: See benchmarks at https://core.trac.wordpress.org/ticket/32052.
Author: Per Soderlind
Author URI: https://soderlind.no
Plugin URI: https://gist.github.com/soderlind/610a9b24dbf95a678c3e
License: GPL

Save the plugin in mu-plugins. You don't have to, but you should add an an object cache.
Met dank aan de clues op https://stackoverflow.com/questions/48852834/slow-load-plugin-textdomain-and-load-default-textdomain
Alternatieve plugin die rechtstreeks de object cache aanspreekt: https://github.com/inpsyde/translation-cache
*/

add_filter( 'override_load_textdomain', 'a_faster_load_textdomain', 1, 3 );

function a_faster_load_textdomain( $retval, $domain, $mofile ) {
	global $l10n;

	if ( ! is_readable( $mofile ) ) return false;

	if ( is_multisite() ) {
		// Switchen naar hoofdsite en zo automatisch gebruik maken van Redis object cache?
		switch_to_blog(1);
	}

	$data = get_transient( 'translation_'.md5( $mofile ) );
	$mtime = filemtime ($mofile );

	$mo = new MO();
	if ( ! $data or ! isset( $data['mtime'] ) or $mtime > $data['mtime'] ) {
		if ( ! $mo->import_from_file( $mofile ) ) return false;
		$data = array(
			'mtime' => $mtime,
			'entries' => $mo->entries,
			'headers' => $mo->headers
		);
		set_transient( 'translation_'.md5( $mofile ), $data, MONTH_IN_SECONDS );
	} else {
		$mo->entries = $data['entries'];
		$mo->headers = $data['headers'];
	}

	if ( is_multisite() ) {
		restore_current_blog();
	}

	if ( isset( $l10n[$domain] ) ) {
		$mo->merge_with( $l10n[$domain] );
	}

	$l10n[$domain] = &$mo;

	return true;
}
