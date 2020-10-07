<?php
	global $nm_theme_options;
	
	// Layout classes
	$border_class = ( $nm_theme_options['footer_widgets_border'] ) ? ' has-border' : '';
	$row_class = ' nm-row-' . $nm_theme_options['footer_widgets_layout'];
	
	// Grid columns class
	$columns_medium = ( intval( $nm_theme_options['footer_widgets_columns'] ) < 2 ) ? '1' : '2';
	$columns_class = apply_filters( 'nm_footer_widgets_columns_class', 'xsmall-block-grid-1  small-block-grid-1 medium-block-grid-' . $columns_medium . ' large-block-grid-' . $nm_theme_options['footer_widgets_columns'] );
?>	
<div class="nm-footer-widgets<?php echo esc_attr( $border_class ); ?> clearfix">
    <div class="nm-footer-widgets-inner">
        <div class="nm-row <?php echo esc_attr( $row_class ); ?>">
            <div class="col-xs-12">
                <ul class="nm-footer-block-grid <?php echo esc_attr( $columns_class ); ?>">
                    <li id="text-7" class="widget widget_text">
                        <div class="textwidget">
                            <?php
                                // Vervang dynamic_sidebar('footer') door makkelijker te verspreiden templateteksten
                                if ( is_b2b_customer() ) {
                                    $text = __( 'Inhoud van praktisch blokje in footer (indien B2B-klant).', 'oxfam-webshop' );
                                } elseif ( does_home_delivery() ) {
                                    $text = __( 'Inhoud van praktisch blokje in footer (indien ook thuislevering).', 'oxfam-webshop' );
                                } else {
                                    $text = __( 'Inhoud van praktisch blokje in footer (inden enkel afhaling).', 'oxfam-webshop' );
                                }
                                echo do_shortcode('[nm_feature icon="pe-7s-global" layout="centered" title="'.__( 'Titel van praktisch blokje in footer', 'oxfam-webshop' ).'"]'.$text.'[/nm_feature]');
                            ?>
                        </div>
                    </li>
                    <li id="text-8" class="widget widget_text">
                        <div class="textwidget">
                            <?php
                                $terms = get_page_by_title('Algemene voorwaarden');
                                $faq = get_page_by_title('Veelgestelde vragen');
                                echo do_shortcode('[nm_feature icon="pe-7s-comment" layout="centered" title="'.__( 'Titel van contactblokje in footer', 'oxfam-webshop' ).'"]<p>Heb je een vraag? We geven je een eerlijk antwoord.<br/><a href="'.get_permalink( $terms->ID ).'">'.$terms->post_title.'</a><br/><a href="'.get_permalink( $faq->ID ).'">'.$faq->post_title.'</a></p><p>'.sprintf( __( 'Inhoud van het contactblokje in de footer. Bevat <a href="mailto:%1$s">een e-mailadres</a> en een aanklikbaar telefoonnummer (%2$s).', 'oxfam-webshop' ), get_webshop_email(), '<a href="tel:+32'.substr( preg_replace( '/[^0-9]/', '', get_oxfam_shop_data('telephone') ), 1 ).'">'.get_oxfam_shop_data('telephone').'</a>' ).'</p>[/nm_feature]');
                            ?>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>