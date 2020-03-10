<?php
	// Logo URLs
	$logo_url = nm_logo_get_url();
	$alt_logo_url = nm_alt_logo_get_url();
?>
<div class="nm-header-logo">
	<a href="<?php echo esc_url( home_url('/') ); ?>">
		<!-- GEWIJZIGD: Vermeld logo en winkelnaam enkel op lokale sites -->
		<?php if ( ! is_main_site() ) : ?>
			<img src="<?php echo get_stylesheet_directory_uri(); ?>/images/oww-webshop-groen-60px@2x.png" class="nm-logo" alt="<?php bloginfo('name'); ?>">
			<?php if ( $alt_logo_url ) : ?>
				<img src="<?php echo esc_url( $alt_logo_url ); ?>" class="nm-alt-logo" alt="<?php bloginfo('name'); ?>">
			<?php endif; ?>
			<div class="winkelnaam">Webshop<br/><?php echo str_replace( 'Oxfam-Wereldwinkel ', '', get_company_name() ); ?></div>
		<?php else : ?>
			<img src="<?php echo get_stylesheet_directory_uri(); ?>/images/oww-webshop-zwart-60px@2x.png" class="nm-logo" alt="<?php bloginfo('name'); ?>">
			<div class="winkelnaam black">Webshop</div>
		<?php endif; ?>
	</a>
</div>