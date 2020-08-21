<?php global $nm_theme_options; ?>
<!DOCTYPE html>

<html <?php language_attributes(); ?> class="<?php echo esc_attr( 'footer-sticky-' . $nm_theme_options['footer_sticky'] ); ?>">
    <head>
        <meta charset="<?php bloginfo( 'charset' ); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        
        <link rel="profile" href="http://gmpg.org/xfn/11">
		<link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>">
        
		<?php wp_head(); ?>
    </head>
    
	<body <?php body_class(); ?>>
        <?php if ( $nm_theme_options['page_load_transition'] ) : ?>
        <div id="nm-page-load-overlay" class="nm-page-load-overlay"></div>
        <?php endif; ?>
        
        <div class="nm-page-overflow">
            <div class="nm-page-wrap">
                <?php
                    // Top bar
                    if ( $nm_theme_options['top_bar'] ) {
                        get_template_part( 'template-parts/header/header', 'top-bar' );
                    }
                ?>
                            
                <div class="nm-page-wrap-inner">
                    <?php
                        // GEWIJZIGD: Header content vervangen door header van OWW-site
                        get_template_part( 'template-parts/header/header', 'oww' );
                    
                        // GEWIJZIGD: Custom banner met afhaal/leverinfo
                        if ( strlen( get_option('oxfam_sitewide_banner_top') ) > 0 ) {

                            echo '<div class="general-store-notice"><p>'.get_option('oxfam_sitewide_banner_top').'</p></div>';

                        } elseif ( is_main_site() or does_home_delivery() ) {

                            // Neem netwerkinstelling als defaultwaarde
                            $min_amount = get_option( 'oxfam_minimum_free_delivery', get_site_option('oxfam_minimum_free_delivery') );
                            
                            echo '<div class="general-store-notice"><p class="free-shipping">';
                            if ( $min_amount > 0 ) {
                                echo '<b><u>Gratis</u></b> verzending vanaf '.$min_amount.' euro!';
                            } else {
                                echo 'Nu met <b><u>gratis</u></b> thuislevering!';
                            }
                            echo '</p></div>';

                        } elseif ( ! is_main_site() and ! does_home_delivery() ) {

                            // Standaardboodschap voor winkels die geen thuislevering aanbieden
                            // echo '<div class="general-store-notice"><p class="local-pickup">Omwille van het coronavirus kun je je bestelling momenteel enkel <b><u>op afspraak</u></b> afhalen in de winkel.</p></div>';

                        }
                    ?>
