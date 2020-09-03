<?php global $nm_theme_options; ?>
<!DOCTYPE html>

<html <?php language_attributes(); ?> class="<?php echo esc_attr( 'footer-sticky-' . $nm_theme_options['footer_sticky'] ); ?>">
    <head>
        <meta charset="<?php bloginfo( 'charset' ); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        
        <link rel="profile" href="http://gmpg.org/xfn/11">
		<link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>">

        <!-- GEWIJZIGD: Handmatig toevoegen van Open Graph-tags op homepage (Jetpack uitgeschakeld) -->
        <?php if ( is_front_page() ) : ?>
            <meta property="og:title" content="<?php echo get_bloginfo('title'); ?>">
            <meta property="og:url" content="<?php echo get_bloginfo('url') . "/"; ?>">
            <meta property="og:description" content="Shop nu ook online in jouw wereldwinkel. Op je gemak. Wanneer het jou past. Jij kiest en betaalt online, onze plaatselijke vrijwilligers zetten je boodschappen klaar. De grootste keuze aan eerlijke voedingsproducten!">
            <meta property="og:image" content="https://shop.oxfamwereldwinkels.be/wp-content/uploads/facebook.png">
        <?php endif; ?>
        
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
                    
                        // GEWIJZIGD: Custom banner met afhaal/leverinfo tonen op shoppagina's
                        if ( is_woocommerce() ) {
                            get_template_part( 'template-parts/header/general-store-notice' );
                        }
                    ?>
