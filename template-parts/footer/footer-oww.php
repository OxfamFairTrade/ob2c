<?php
	// Voorlopig volledig statisch, behalve de contactgegevens
	if ( isset( $_COOKIE['latest_shop_id'] ) ) {
		$atts['id'] = $_COOKIE['latest_shop_id'];
	} else {
		$atts['id'] = get_option('oxfam_shop_post_id');
	}
?>
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
									<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://www.oxfamwereldwinkels.be/over-ons/">Missie en aanpak</a></li>
									<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://www.oxfamwereldwinkels.be/steun-ons/">Steun ons</a></li>
									<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://www.oxfamwereldwinkels.be/over-ons/bescherming-tegen-misbruik-en-uitbuiting/">Meld wangedrag</a></li>
									<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://www.oxfamwereldwinkels.be/vacatures/">Vacatures</a></li>
									<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://www.oxfamwereldwinkels.be/over-ons/bevriende-organisaties/">Bevriende organisaties</a></li>
								</ul>
							</div>
						</div>
						<div class="col-md-3">
							<div class="footer-menu">
								<h3>Meer over fair trade</h3>
								<ul id="menu-footer-menu-2" class="menu">
									<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://www.oxfamwereldwinkels.be/expertise/">Expertise</a></li>
									<li class="menu-item menu-item-type-post_type_archive menu-item-object-partner"><a href="https://www.oxfamwereldwinkels.be/partners/">Producenten</a></li>
									<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://www.oxfamwereldwinkels.be/nieuws/">Nieuws</a></li>
									<li class="menu-item menu-item-type-post_type_archive menu-item-object-recipe"><a href="https://www.oxfamwereldwinkels.be/recepten/">Recepten</a></li>
									<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://www.oxfamwereldwinkels.be/magazine-fair/">Magazine FAIR</a></li>
								</ul>
							</div>
						</div>
						<div class="col-md-3">
							<div class="footer-menu">
								<h3>Ik ben</h3>
								<ul id="menu-footer-menu-3" class="menu">
									<li class="menu-item menu-item-type-taxonomy menu-item-object-category"><a href="https://www.oxfamwereldwinkels.be/pers/">Pers</a></li>
									<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://www.oxfamwereldwinkels.be/oxfam-op-school/">School</a></li>
									<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://www.oxfamwereldwinkels.be/expertise/">Beleidsmedewerker</a></li>
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
						<p><?php echo get_company_contact( $atts ); ?></p>
					</div>
				</div>
			</div>
		</div>
		<div class="footer-logos">
			<div class="col-row">
				<div class="col-sm-2 hidden-xs">
					<div class="logo">
						<a href="https://www.oxfamwereldwinkels.be/"><img src="https://www.oxfamwereldwinkels.be/wp-content/themes/oxfam/images/logo-green.svg" alt=""></a>
					</div>
				</div>
				<div class="col-sm-10 col-xs-12">
					<div class="col-row md-display-flex">
						<div class="col-xs-3 md-align-self-center">
							<div class="logo">
								<a href="https://www.mo.be/" target="_blank"><img width="111" height="62" src="https://www.oxfamwereldwinkels.be/wp-content/uploads/2019/04/footer-logo-1.png" class="attachment-medium size-medium" alt=""></a>
							</div>
						</div>
						<div class="col-xs-3 md-align-self-center">
							<div class="logo">
								<a href="https://www.11.be/" target="_blank"><img width="140" height="60" src="https://www.oxfamwereldwinkels.be/wp-content/uploads/2019/04/footer-logo-2.png" class="attachment-medium size-medium" alt=""></a>
							</div>
						</div>
						<div class="col-xs-3 md-align-self-center">
							<div class="logo">
								<a href="https://www.vlaanderen.be/" target="_blank"><img width="153" height="60" src="https://www.oxfamwereldwinkels.be/wp-content/uploads/2019/04/footer-logo-3.png" class="attachment-medium size-medium" alt=""></a>
							</div>
						</div>
						<div class="col-xs-3 md-align-self-center">
							<div class="logo">
								<a href="https://diplomatie.belgium.be/nl/Beleid/Ontwikkelingssamenwerking/" target="_blank"><img width="252" height="60" src="https://www.oxfamwereldwinkels.be/wp-content/uploads/2019/04/footer-logo-4.png" class="attachment-medium size-medium" alt=""></a>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>