<?php 
	global $nm_theme_options, $nm_globals;
	
	// Copyright text
	$copyright_text = ( isset( $nm_theme_options['footer_bar_text'] ) && strlen( $nm_theme_options['footer_bar_text'] ) > 0 ) ? $nm_theme_options['footer_bar_text'] : '';
	if ( $nm_theme_options['footer_bar_text_cr_year'] ) {
		$copyright_text = sprintf( '&copy; %s %s', date( 'Y' ), $copyright_text );
	}
	
	// Bar right-column content
	if ( $nm_theme_options['footer_bar_content'] !== 'social_icons' ) {
		$display_social_icons = false;
		$display_copyright_in_menu = ( $nm_theme_options['footer_bar_content'] !== 'copyright_text' ) ? true : false;
		$bar_content = ( $display_copyright_in_menu ) ? do_shortcode( $nm_theme_options['footer_bar_custom_content'] ) : $copyright_text;
	} else {
		$display_social_icons = true;
		$display_copyright_in_menu = true;
	}
?>                

                </div>
            </div>
            <!-- /page wrappers -->
            
            <div id="nm-page-overlay" class="nm-page-overlay"></div>
            <div id="nm-widget-panel-overlay" class="nm-page-overlay"></div>
            
            <!-- footer -->
            <footer id="nm-footer" class="nm-footer">
                <?php
                    if ( is_active_sidebar( 'footer' ) ) {
                        get_footer( 'widgets' );
                    }
                ?>
                
                <div class="nm-footer-bar">
                    <div class="nm-footer-bar-inner">
                        <div class="nm-row">
                            <div class="nm-footer-bar-left col-md-8 col-xs-12">
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
                                    <!-- GEWIJZIGD: Voer shortcode in copyrighttekst in -->
                                    <li class="nm-footer-bar-text menu-item"><div><?php echo wp_kses_post( do_shortcode( $copyright_text ) ); ?></div></li>
                                    <?php endif; ?>
                                </ul>
                                <!-- GEWIJZIGD: Zet logo achter i.p.v. voor menu -->
                                <?php 
                                    if ( isset( $nm_theme_options['footer_bar_logo'] ) && strlen( $nm_theme_options['footer_bar_logo']['url'] ) > 0 ) : 
                                    
                                    $footer_bar_logo_src = ( is_ssl() ) ? str_replace( 'http://', 'https://', $nm_theme_options['footer_bar_logo']['url'] ) : $nm_theme_options['footer_bar_logo']['url'];
                                ?>
                                <div class="nm-footer-bar-logo">
                                    <img src="<?php echo esc_url( $footer_bar_logo_src ); ?>" />
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="nm-footer-bar-right col-md-4 col-xs-12">
                                <!-- GEWIJZIGD: Voeg betaallogo's toe -->
                                <div class="nm-footer-bar-logo betaalmethodes">
                                    <a href="https://www.mollie.com/be/consumers" target="_blank">
                                        <img src="<?php echo plugins_url( 'mollie-payments-for-woocommerce/assets/images/mistercash@2x.png' ); ?>">
                                        <img src="<?php echo plugins_url( 'mollie-payments-for-woocommerce/assets/images/creditcard@2x.png' ); ?>">
                                        <img src="<?php echo plugins_url( 'mollie-payments-for-woocommerce/assets/images/kbc@2x.png' ); ?>">
                                        <img src="<?php echo plugins_url( 'mollie-payments-for-woocommerce/assets/images/belfius@2x.png' ); ?>">
                                        <img src="<?php echo plugins_url( 'mollie-payments-for-woocommerce/assets/images/ideal@2x.png' ); ?>">
                                    </a>
                                </div>

                                <?php if ( $display_social_icons ) : ?>
									<?php echo nm_get_social_profiles( 'nm-footer-bar-social' ); // Args: $wrapper_class ?>
                                <?php else : ?>
                                <ul class="menu">
                                    <li class="nm-footer-bar-text menu-item"><div><?php echo wp_kses_post( $bar_content ); ?></div></li>
                                </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </footer>
            <!-- /footer -->
            
            <!-- mobile menu -->
            <div id="nm-mobile-menu" class="nm-mobile-menu">
                <div class="nm-mobile-menu-scroll">
                    <div class="nm-mobile-menu-content">
                        <div class="nm-row">
                                                    
                            <div class="nm-mobile-menu-top col-xs-12">
                                <ul id="nm-mobile-menu-top-ul" class="menu">
                                    <?php if ( $nm_globals['cart_link'] ) : ?>
                                    <li class="nm-mobile-menu-item-cart menu-item">
                                        <a href="<?php echo esc_url( WC()->cart->get_cart_url() ); ?>" id="nm-mobile-menu-cart-btn">
                                            <?php echo nm_get_cart_title(); ?>
                                            <?php echo nm_get_cart_contents_count(); ?>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    <?php if ( $nm_globals['shop_search_header'] ) : ?>
                                    <li class="nm-mobile-menu-item-search menu-item">
                                        <form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
                                            <input type="text" id="nm-mobile-menu-shop-search-input" class="nm-mobile-menu-search" autocomplete="off" value="" name="s" placeholder="<?php esc_attr_e( 'Search products', 'woocommerce' ); ?>" />
                                            <span class="nm-font nm-font-search-alt"></span>
                                            <input type="hidden" name="post_type" value="product" />
                                        </form>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                             
                            <div class="nm-mobile-menu-main col-xs-12">
                                <ul id="nm-mobile-menu-main-ul" class="menu">
                                    <?php
                                        // Main menu
                                        wp_nav_menu( array(
                                            'theme_location'	=> 'main-menu',
                                            'container'       	=> false,
                                            'fallback_cb'     	=> false,
                                            'after' 	 		=> '<span class="nm-menu-toggle"></span>',
                                            'items_wrap'      	=> '%3$s'
                                        ) );
                                        
                                        // Right menu                        
                                        wp_nav_menu( array(
                                            'theme_location'	=> 'right-menu',
                                            'container'       	=> false,
                                            'fallback_cb'     	=> false,
                                            'after' 	 		=> '<span class="nm-menu-toggle"></span>',
                                            'items_wrap'      	=> '%3$s'
                                        ) );
                                    ?>
                                </ul>
                            </div>
        
                            <div class="nm-mobile-menu-secondary col-xs-12">
                                <ul id="nm-mobile-menu-secondary-ul" class="menu">
                                    <?php
                                        // Top bar menu
                                        if ( $nm_theme_options['top_bar'] ) {
                                            wp_nav_menu( array(
                                                'theme_location'	=> 'top-bar-menu',
                                                'container'       	=> false,
                                                'fallback_cb'     	=> false,
                                                'after' 	 		=> '<span class="nm-menu-toggle"></span>',
                                                'items_wrap'      	=> '%3$s'
                                            ) );
                                        }
                                    ?>
                                    <?php if ( nm_woocommerce_activated() && $nm_theme_options['menu_login'] ) : ?>
                                    <li class="nm-menu-item-login menu-item">
                                        <?php echo nm_get_myaccount_link( false ); ?>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        
                        </div>
                    </div>
                </div>
            </div>
            <!-- /mobile menu -->
            
            <?php if ( $nm_globals['cart_panel'] ) : ?>
            <!-- widget panel -->                
            <div id="nm-widget-panel" class="nm-widget-panel">
                <div class="nm-widget-panel-inner">
                    <div class="nm-widget-panel-header">
                        <div class="nm-widget-panel-header-inner">
                            <a href="#" id="nm-widget-panel-close">
                                <span class="nm-cart-panel-title"><?php esc_html_e( 'Cart', 'woocommerce' ); ?> <span class="nm-menu-cart-count count"><?php echo WC()->cart->get_cart_contents_count(); ?></span></span>
                                <span class="nm-widget-panel-close-title"><?php esc_html_e( 'Close', 'woocommerce' ); ?></span>
                            </a>
                        </div>
                    </div>
                    
                    <div class="widget_shopping_cart_content">
                        <?php woocommerce_mini_cart(); ?>
                    </div>
                </div>
            </div>
            <!-- /widget panel -->
            <?php endif; ?>
            
            <?php
				if ( $nm_globals['login_popup'] && ! is_user_logged_in() && ! is_account_page() ) :
					nm_add_page_include( 'login-popup' );
			?>
				<!-- login popup -->
                <div id="nm-login-popup-wrap" class="nm-login-popup-wrap mfp-hide">
                    <?php wc_get_template( 'myaccount/form-login.php', array( 'is_popup' => true ) ); ?>
				</div>
                <!-- /login popup -->
			<?php endif; ?>
            
            <!-- quickview -->
            <div id="nm-quickview" class="clearfix"></div>
            <!-- /quickview -->
            
            <?php if ( strlen( $nm_theme_options['custom_js'] ) > 0 ) : ?>
            <!-- Custom Javascript -->
            <script type="text/javascript">
                <?php echo $nm_theme_options['custom_js']; ?>
            </script>
            <?php endif; ?>
            
            <?php
                // WordPress footer hook
                wp_footer();
            ?>
        
        </div>
        <!-- /page overflow wrapper -->
    	
	</body>
    
</html>
