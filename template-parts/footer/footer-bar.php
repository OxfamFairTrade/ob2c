<?php
	global $nm_theme_options;
    
    // Copyright text
	$copyright_text = ( isset( $nm_theme_options['footer_bar_text'] ) && strlen( $nm_theme_options['footer_bar_text'] ) > 0 ) ? $nm_theme_options['footer_bar_text'] : '';
	if ( $nm_theme_options['footer_bar_text_cr_year'] ) {
		$copyright_text = sprintf( '&copy; %s %s', date( 'Y' ), $copyright_text );
	}
	
	// Right column content
	if ( $nm_theme_options['footer_bar_content'] !== 'social_icons' ) {
		$display_social_icons = false;
		$display_copyright_in_menu = ( $nm_theme_options['footer_bar_content'] !== 'copyright_text' ) ? true : false;
		$content = ( $display_copyright_in_menu ) ? do_shortcode( $nm_theme_options['footer_bar_custom_content'] ) : $copyright_text;
	} else {
		$display_social_icons = true;
		$display_copyright_in_menu = true;
	}
?>
<div class="nm-footer-bar">
    <div class="nm-footer-bar-inner">
        <div class="nm-row">
            <div class="nm-footer-bar-left col-md-6 col-xs-12">
                <ul id="nm-footer-bar-menu" class="menu">
                    <?php
                        // Footer menu
                        wp_nav_menu( array(
                            'theme_location'	=> 'footer-menu',
                            'container'       	=> false,
                            'fallback_cb'     	=> false,
                            'items_wrap'      	=> '%3$s'
                        ) );
                    ?>
                    <?php if ( $display_copyright_in_menu ) : ?>
                    <!-- GEWIJZIGD: Voer shortcode in copyrighttekst uit -->
                    <li class="nm-footer-bar-text menu-item"><div><?php echo wp_kses_post( do_shortcode( $copyright_text ) ); ?></div></li>
                    <?php endif; ?>
                </ul>
                <!-- GEWIJZIGD: Zet logo achter i.p.v. voor menu -->
                <?php 
                    if ( isset( $nm_theme_options['footer_bar_logo'] ) && strlen( $nm_theme_options['footer_bar_logo']['url'] ) > 0 ) : 

                    $logo_src = ( is_ssl() ) ? str_replace( 'http://', 'https://', $nm_theme_options['footer_bar_logo']['url'] ) : $nm_theme_options['footer_bar_logo']['url'];
                ?>
                <div class="nm-footer-bar-logo">
                    <img src="<?php echo esc_url( $logo_src ); ?>" />
                </div>
                <?php endif; ?>
            </div>

            <div class="nm-footer-bar-right col-md-6 col-xs-12">
                <!-- GEWIJZIGD: Voeg betaallogo's toe -->
                <div class="nm-footer-bar-logo betaalmethodes">
                    <a href="https://www.mollie.com/be/consumers" target="_blank">
                        <img src="<?php echo plugins_url( 'mollie-payments-for-woocommerce/assets/images/bancontact.svg' ); ?>">
                        <img src="<?php echo plugins_url( 'mollie-payments-for-woocommerce/assets/images/creditcards.svg' ); ?>">
                        <img src="<?php echo plugins_url( 'mollie-payments-for-woocommerce/assets/images/kbc.svg' ); ?>">
                        <img src="<?php echo plugins_url( 'mollie-payments-for-woocommerce/assets/images/belfius.svg' ); ?>">
                        <img src="<?php echo plugins_url( 'mollie-payments-for-woocommerce/assets/images/ing.svg' ); ?>">
                        <img src="<?php echo plugins_url( 'mollie-payments-for-woocommerce/assets/images/applepay.svg' ); ?>">
                        <img src="<?php echo plugins_url( 'mollie-payments-for-woocommerce/assets/images/ideal.svg' ); ?>">
                    </a>
                </div>
                
                <?php if ( $display_social_icons ) : ?>
                    <?php echo nm_get_social_profiles( 'nm-footer-bar-social' ); // Args: $wrapper_class ?>
                <?php else : ?>
                <ul class="menu">
                    <li class="nm-footer-bar-text menu-item"><div><?php echo wp_kses_post( $content ); ?></div></li>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>