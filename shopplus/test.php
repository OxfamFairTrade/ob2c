<?php

	include('config.php');
	include('mailchimp.php');
	use \DrewM\MailChimp\MailChimp;
	
	$MailChimp = new MailChimp($api_key);
	$update_args = array(
		'merge_fields' => array( 'POINTS' => intval( $_POST['POINTS'] ) ),
	);
	$update = $MailChimp->patch( 'lists/'.$list_id.'/members/'.md5( strtolower( $_POST['EMAIL'] ) ), $update_args );
	print_r($update);

?>