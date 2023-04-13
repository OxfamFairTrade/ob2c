<?php
	if ( ! defined('ABSPATH') ) exit;
?>

<div class="wrap">
	<h1>Opzoeken van digicheques</h1>
	
	<p>Met behulp van dit formulier kun je de status van een digicheque opzoeken, zonder rechtstreeks in de database te moeten werken.<br/>In geval van nood kun je de digicheque ook handmatig ongeldig maken (bv. omdat een winkel de code toch aanvaardde in de winkel).<br/>Opgelet: in dat geval dien je zelf te onthouden aan welke winkel de code gecrediteerd moet worden! De code zal onder de naam 'UNKNOWN' opduiken in de maandelijkse export.</p>
	
	<input type="text" id="voucher-code" name="voucher-code" class="" placeholder="DEMODEMODEMO">
	<button type="submit" id="voucher-code-lookup" name="voucher-code-lookup" class="button-primary">Zoek</button>
	<div id="result" style="margin: 1em 0; display: none;">
		<p>Hier komt het antwoord ...</p>
	</div>
</div>

<script type="text/javascript">
	jQuery(document).ready( function() {
		jQuery('#voucher-code-lookup').on( 'click', function() {
			// jQuery('#result').html( jQuery('#voucher-code').val() );
			jQuery('#result').slideDown();
		});
	});
</script>