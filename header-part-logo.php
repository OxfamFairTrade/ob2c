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
            <img src="<?php echo esc_url( $logo_href ); ?>" class="nm-logo" alt="<?php bloginfo( 'name' ); ?>">
            <?php if ( $has_alt_logo ) : ?>
            <img src="<?php echo esc_url( $alt_logo_href ); ?>" class="nm-alt-logo" alt="<?php bloginfo( 'name' ); ?>">
            <?php endif; ?>
             <!-- GEWIJZIGD: Winkelnaam vermelden -->
            <div class="winkelnaam">
                <?php echo 'Webshop<br>' . str_replace( 'Oxfam-Wereldwinkel ', '', get_company_name() ); ?>
            </div>
        </a>
    </div>