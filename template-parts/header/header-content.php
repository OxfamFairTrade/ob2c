<?php
    global $nm_globals, $nm_theme_options;
    
    // Header classes
    $header_classes = nm_header_get_classes();
?>
<div id="nm-header-placeholder" class="nm-header-placeholder"></div>

<header id="nm-header" class="nm-header <?php echo esc_attr( $header_classes ); ?> clear">
        <div class="nm-header-inner">
        <?php
            // Include header layout
            if ( $nm_theme_options['header_layout'] == 'centered' ) {
                get_template_part( 'template-parts/header/header', 'layout-centered' );
            } else {
                get_template_part( 'template-parts/header/header', 'layout' );
            }
        ?>
    </div>
</header>

<?php
    // GEWIJZIGD: Custom banner met afhaal/leverinfo
    if ( is_main_site() or does_home_delivery() ) {
        echo '<div class="general-store-notice"><p class="free-shipping">';
        if ( get_current_blog_id() === 9 or get_current_blog_id() === 15 ) {
            // Uitzondering voor De Pinte en Gentbrugge
            echo 'Nu met <b><u>gratis</u></b> thuislevering!</p>';
        } elseif ( get_current_blog_id() === 13 or get_current_blog_id() === 26 ) {
            // Uitzondering voor Evergem en Regio Brussel
            echo 'Nu met thuislevering <b><u>op vrijdag</u></b>, gratis vanaf 50 euro!';
        } elseif ( get_current_blog_id() === 27 ) {
            // Uitzondering voor Regio Hasselt
            echo '<b><u>Gratis</u></b> thuislevering in Hasselt! (elders vanaf 50 euro)</p>';
        } elseif ( get_current_blog_id() === 37 ) {
            // Uitzondering voor Wuustwezel
            echo 'Nu met <b><u>gratis</u></b> verzending vanaf 30 euro!';
        } elseif ( get_current_blog_id() === 38 ) {
            // Uitzondering voor Zele
            echo '<b><u>Gratis</u></b> thuislevering in Zele en Berlare! (elders vanaf 50 euro)</p>';
        } else {
            echo 'Nu met <b><u>gratis</u></b> verzending vanaf 50 euro!';
        }
        echo '</p></div>';
    } elseif ( ! is_main_site() and ! does_home_delivery() ) {
        if ( get_current_blog_id() === 29 ) {
            // Uitzondering voor Roeselare
            echo '<div class="general-store-notice"><p class="local-pickup">Omwille van het coronavirus kun je je bestelling momenteel enkel <b><u>op vrijdag tussen 13u30 en 18u afhalen</u></b> in de winkel.</p></div>';
        } elseif ( get_current_blog_id() !== 12 ) {
            // Uitzondering voor Dilbeek
            echo '<div class="general-store-notice"><p class="local-pickup">Omwille van het coronavirus gebeuren alle afhalingen <b><u>op afspraak</u></b>. We contacteren je na het plaatsen van je bestelling!</p></div>';
        }
    }
    
    // Shop search
    if ( $nm_globals['shop_search_header'] ) {
        get_template_part( 'template-parts/woocommerce/searchform' );
    }
?>