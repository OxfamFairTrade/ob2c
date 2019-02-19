<?php

	include('config.php');
	include('mailchimp.php');
	use \DrewM\MailChimp\MailChimp;
	
	$MailChimp = new MailChimp($api_key);
	$update_args = array(
		'merge_fields' => array( 'POINTS' => 0 ),
	);
	$update = $MailChimp->patch( 'lists/'.$list_id.'/members/'.md5( strtolower( 'info@koekedozeklan.be' ) ), $update_args );
	print_r($update);

?>