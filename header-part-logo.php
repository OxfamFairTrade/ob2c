<?php
	global $nm_theme_options;
			
	// Logo URL
	if ( isset( $nm_theme_options['logo'] ) && strlen( $nm_theme_options['logo']['url'] ) > 0 ) {
		$logo_href = ( is_ssl() ) ? str_replace( 'http://', 'https://', $nm_theme_options['logo']['url'] ) : $nm_theme_options['logo']['url'];
	} else {
		$logo_href = NM_THEME_URI . '/img/logo@2x.png';
	}
	
	// Alternative logo
	$has_alt_logo = false;    
	if ( $nm_theme_options['alt_logo_config'] != '0' ) {
		if ( isset( $nm_theme_options['alt_logo'] ) && strlen( $nm_theme_options['alt_logo']['url'] ) > 0 ) {
			$has_alt_logo = true;
			$alt_logo_href = ( is_ssl() ) ? str_replace( 'http://', 'https://', $nm_theme_options['alt_logo']['url'] ) : $nm_theme_options['alt_logo']['url'];
		}
	}
?>

	<div class="nm-header-logo">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php
				// GEWIJZIGD: Vermeld logo en winkelnaam enkel op lokale sites
				if ( ! is_main_site() ) {
				?>
					<img src="<?php echo get_stylesheet_directory_uri(); ?>/images/oww-webshop-groen-60px.png" class="nm-logo" style="max-height: 60px;">
					<?php if ( $has_alt_logo ) : ?>
					<img src="<?php echo esc_url( $alt_logo_href ); ?>" class="nm-alt-logo" alt="<?php bloginfo( 'name' ); ?>">
					<?php endif; ?>
				<?php
					echo '<div class="winkelnaam">Webshop<br>' . str_replace( 'Oxfam-Wereldwinkel ', '', get_company_name() ) . '</div>';
				} else {
					echo '<img src="'.get_stylesheet_directory_uri().'/images/oww-webshop-zwart.png" class="nm-logo" style="max-height: 60px;">';
					echo '<div class="winkelnaam">Webshop</div>';
					echo '<img src="'.get_stylesheet_directory_uri().'/images/tekstballon.png" class="nm-logo" style="float: right; max-height: 60px;">';
				}
			?>
		</a>
	</div>