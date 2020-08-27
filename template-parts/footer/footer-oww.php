<?php
	// Voorlopig volledig statisch, behalve de contactgegevens
	if ( isset( $_COOKIE['latest_shop_id'] ) ) {
		$atts['id'] = $_COOKIE['latest_shop_id'];
	} else {
		$atts['id'] = get_option('oxfam_shop_post_id');
	}
?>
<?php if ( get_post_type() === 'wpsl_stores' and get_field('mailchimp_signup_url') ) : ?>
    <div id="newsletter">
        <div class="container">
            <div class="col-row md-display-flex">
                <div class="col-md-5 md-align-self-center">
                    <h2>Abonneer je hier op de<br/> nieuwsbrief van <?php the_title(); ?></h2>
                </div>
                <div class="col-md-7 md-align-self-center">
                    <form action="<?php the_field('mailchimp_signup_url'); ?>" method="post" target="_blank">
                        <input type="text" name="FNAME" placeholder="Voornaam" required="required">
                        <input type="email" name="EMAIL" placeholder="E-mailadres" required="required">
                        <input type="submit" value="Inschrijven">
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php elseif ( in_array( $post->ID, get_option('oxfam_school_pages') ) or in_array( wp_get_post_parent_id( $post->ID ), get_option('oxfam_school_pages') ) or is_singular('scholenactie') ) : ?>
    <div id="newsletter">
        <div class="container">
            <div class="col-row md-display-flex">
                <div class="col-md-5 md-align-self-center">
                    <h2>Abonneer je op de<br/> scholennieuwsbrief</h2>
                </div>
                <div class="col-md-7 md-align-self-center">
                    <form id="school-newsletter" class="mc4wp-form" method="post" data-name="Aboneer je op de scholennieuwsbrief">
                        <div class="mc4wp-form-fields">
                            <input type="text" name="FNAME" placeholder="Voornaam" required />
                            <input type="email" name="EMAIL" placeholder="E-mailadres" required />
                            <input type="hidden" name="LIST_ID" value="66358ad206">
                            <label style="display: none !important;">Laat dit veld leeg als je een mens bent: <input type="text" name="HONING" value="" tabindex="-1" autocomplete="off"></label>
                            <input type="submit" value="Inschrijven" />
                        </div>
                        <div class="mc4wp-response"></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script type="text/javascript">
      jQuery(document).ready( function() {
        jQuery('#school-newsletter').on( 'submit', function(event) {
          event.preventDefault();
          var form = jQuery(this);
          var output = form.find('.mc4wp-response');

          form.find("input[type='submit']").prop( 'disabled', true );
          output.html('Even geduld ...');

          var newsletter = 'no';
          if ( form.find("input[name='HONING']").val().length == 0 ) {
            newsletter = 'yes';
          }

          jQuery.ajax({
            type: 'POST',
            url: '<?php echo get_stylesheet_directory_uri().'/mailchimp/subscribe.php'; ?>',
            data: {
              'fname': form.find("input[name='FNAME']").val(),
              'email': form.find("input[name='EMAIL']").val(),
              'list_id': form.find("input[name='LIST_ID']").val(),
              'newsletter': newsletter
            },
            dataType: 'json',
          }).done( function(response) {
            if ( response.status == 'subscribed' || response.status == 'resubscribed') {
              output.html('Hoera, je bent nu ingeschreven op onze nieuwsbrief voor scholen!');
            } else if ( response.status == 'updated' ) {
              output.html('Je was al ingeschreven op deze nieuwsbrief. Bedankt voor je enthousiasme!');
            } else {
              output.html('Er liep iets verkeerd ('+response.error+').');
            }
          }).fail( function() {
            output.html('Er liep iets verkeerd. Probeer je het eens opnieuw?');
          }).always( function() {
            form.find("input[type='submit']").prop( 'disabled', false );
          });
        });
      });
    </script>
<?php elseif ( get_post_type() !== 'faq' and get_post_type() !== 'press' and get_post_type() !== 'wpsl_stores' ) : ?>
    <div id="newsletter">
        <div class="container">
            <div class="col-row md-display-flex">
                <div class="col-md-5 md-align-self-center">
                    <h2>Abonneer je hier<br/>op onze nieuwsbrief</h2>
                </div>
                <div class="col-md-7 md-align-self-center">
                    <?php echo do_shortcode('[mc4wp_form id="101"]'); ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<div id="footer">
	<div class="container">
		<div class="footer">
			<div class="col-row">
				<div class="col-md-10">
					<div class="col-row">
						<div class="col-md-3">
							<div class="footer-menu">
								<h3>Oxfam-Wereldwinkels</h3>
								<ul id="menu-footer-menu-1" class="menu">
									<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://stage.oxfamwereldwinkels.be/over-ons/">Missie en aanpak</a></li>
									<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://stage.oxfamwereldwinkels.be/steun-ons/">Steun ons</a></li>
									<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://stage.oxfamwereldwinkels.be/over-ons/bescherming-tegen-misbruik-en-uitbuiting/">Meld wangedrag</a></li>
									<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://stage.oxfamwereldwinkels.be/vacatures/">Vacatures</a></li>
									<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://stage.oxfamwereldwinkels.be/over-ons/bevriende-organisaties/">Bevriende organisaties</a></li>
								</ul>
							</div>
						</div>
						<div class="col-md-3">
							<div class="footer-menu">
								<h3>Meer over fair trade</h3>
								<ul id="menu-footer-menu-2" class="menu">
									<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://stage.oxfamwereldwinkels.be/expertise/">Expertise</a></li>
									<li class="menu-item menu-item-type-post_type_archive menu-item-object-partner"><a href="https://stage.oxfamwereldwinkels.be/partners/">Producenten</a></li>
									<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://stage.oxfamwereldwinkels.be/nieuws/">Nieuws</a></li>
									<li class="menu-item menu-item-type-post_type_archive menu-item-object-recipe"><a href="https://stage.oxfamwereldwinkels.be/recepten/">Recepten</a></li>
									<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://stage.oxfamwereldwinkels.be/magazine-fair/">Magazine FAIR</a></li>
								</ul>
							</div>
						</div>
						<div class="col-md-3">
							<div class="footer-menu">
								<h3>Ik ben</h3>
								<ul id="menu-footer-menu-3" class="menu">
									<li class="menu-item menu-item-type-taxonomy menu-item-object-category"><a href="https://stage.oxfamwereldwinkels.be/pers/">Pers</a></li>
									<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://stage.oxfamwereldwinkels.be/oxfam-op-school/">School</a></li>
									<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://stage.oxfamwereldwinkels.be/expertise/">Beleidsmedewerker</a></li>
									<li class="menu-item menu-item-type-custom menu-item-object-custom"><a target="_blank" rel="noopener noreferrer" href="https://www.oxfamfairtrade.be/nl/">Bedrijf</a></li>
									<li class="menu-item menu-item-type-custom menu-item-object-custom"><a target="_blank" rel="noopener noreferrer" href="https://copain.oww.be">Vrijwilliger</a></li>
								</ul>
							</div>
						</div>
						<div class="col-md-3">
							<div class="footer-menu">
								<h3>Blijf op de hoogte</h3>
								<ul id="menu-footer-menu-4" class="menu">
									<li class="menu-item menu-item-type-custom menu-item-object-custom"><a target="_blank" rel="noopener noreferrer" href="https://www.facebook.com/OxfamWereldwinkels">Facebook</a></li>
									<li class="menu-item menu-item-type-custom menu-item-object-custom"><a target="_blank" rel="noopener noreferrer" href="https://www.youtube.com/OxfamWereldwinkels">YouTube</a></li>
									<li class="menu-item menu-item-type-custom menu-item-object-custom"><a target="_blank" rel="noopener noreferrer" href="https://www.instagram.com/oxfam.wereldwinkels/">Instagram</a></li>
									<li class="menu-item menu-item-type-custom menu-item-object-custom"><a target="_blank" rel="noopener noreferrer" href="https://twitter.com/OxfamFairTrade">Twitter</a></li>
									<li class="menu-item menu-item-type-custom menu-item-object-custom"><a target="_blank" rel="noopener noreferrer" href="https://www.linkedin.com/company/oxfam-fair-trade/">LinkedIn</a></li>
								</ul>
							</div>
						</div>
					</div>
				</div>
				<div class="col-md-2">
					<div class="footer-info">
						<h3>Contact</h3>
						<?php
							if ( is_main_site() ) {
								echo '<p>Ververijstraat 17<br/>B-9000 Gent<br/>BE 0415.365.777<br/>RPR GENT</p>';
								echo '<p><a href="tel:+32092188899">+32 (0)9/218.88.99</a></p>';
								echo '<a href="https://stage.oxfamwereldwinkels.be/contact/" class="btn">Contacteer ons</a>';
							} else {
								echo get_company_contact( $atts );
							}
						?>
					</div>
				</div>
			</div>
		</div>
		<div class="footer-logos">
			<div class="col-row">
				<div class="col-sm-2 hidden-xs">
					<div class="logo">
						<a href="https://stage.oxfamwereldwinkels.be/"><img src="https://stage.oxfamwereldwinkels.be/wp-content/themes/oxfam/images/logo-green.svg" alt=""></a>
					</div>
				</div>
				<div class="col-sm-10 col-xs-12">
					<div class="col-row md-display-flex">
						<div class="col-xs-3 md-align-self-center">
							<div class="logo">
								<a href="https://www.mo.be/" target="_blank"><img width="111" height="62" src="https://stage.oxfamwereldwinkels.be/wp-content/uploads/2019/04/footer-logo-1.png" class="attachment-medium size-medium" alt=""></a>
							</div>
						</div>
						<div class="col-xs-3 md-align-self-center">
							<div class="logo">
								<a href="https://www.11.be/" target="_blank"><img width="140" height="60" src="https://stage.oxfamwereldwinkels.be/wp-content/uploads/2019/04/footer-logo-2.png" class="attachment-medium size-medium" alt=""></a>
							</div>
						</div>
						<div class="col-xs-3 md-align-self-center">
							<div class="logo">
								<a href="https://www.vlaanderen.be/" target="_blank"><img width="153" height="60" src="https://stage.oxfamwereldwinkels.be/wp-content/uploads/2019/04/footer-logo-3.png" class="attachment-medium size-medium" alt=""></a>
							</div>
						</div>
						<div class="col-xs-3 md-align-self-center">
							<div class="logo">
								<a href="https://diplomatie.belgium.be/nl/Beleid/Ontwikkelingssamenwerking/" target="_blank"><img width="252" height="60" src="https://stage.oxfamwereldwinkels.be/wp-content/uploads/2019/04/footer-logo-4.png" class="attachment-medium size-medium" alt=""></a>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<div id="copyright">
    <div class="container">
        <div class="col-row">
            <div class="col-sm-6">
                <p>Oxfam-Wereldwinkels vzw &copy; 2019-<?php echo date_i18n('Y'); ?>. Alle rechten voorbehouden.</p>
            </div>
            <div class="col-sm-6 text-right">
                <?php //the_field( 'copyright', 'options' ); ?>

                <!-- @ToDo: refactor to WP menu? -->
                <p><a href="/privacy/">Privacybeleid</a> / <a href="/cookiebeleid/">Cookiebeleid</a> / <a href="/sitemap/">Sitemap</a></p>
            </div>
        </div>
    </div>
</div>