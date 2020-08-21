<?php
	global $nm_globals, $nm_theme_options;
?>

<div id="header" class="hidden-sm hidden-xs">
	<div class="container">
		<div class="col-row md-display-flex">
			<div class="col-md-3 md-align-self-center">
				<div class="logo">
					<a href="/">
						<img src="https://www.oxfamwereldwinkels.be/wp-content/themes/oxfam/images/logo-green.svg" alt="Logo Oxfam-Wereldwinkels">
					</a>
				</div>
			</div>
			<div class="col-md-9 md-align-self-center">
				<div class="topbar">
					<div class="top-menu">
						<ul id="menu-top-menu" class="menu"><li id="menu-item-1627" class="menu-item menu-item-type-taxonomy menu-item-object-category menu-item-1627"><a href="https://www.oxfamwereldwinkels.be/pers/">Voor pers</a></li>
							<li id="menu-item-1506" class="menu-item menu-item-type-post_type menu-item-object-page menu-item-1506"><a href="https://www.oxfamwereldwinkels.be/oxfam-op-school/">Voor scholen</a></li>
							<li id="menu-item-75" class="menu-item menu-item-type-custom menu-item-object-custom menu-item-75"><a target="_blank" rel="noopener noreferrer" href="https://www.oxfamfairtrade.be/nl/">Voor bedrijven</a></li>
						</ul>
					</div>
					<div class="top-search">
						<form id="globalSearch" action="/" method="get">
							<input type="text" name="s" placeholder="Zoeken">
							<input type="submit" value="">
						</form>
					</div>
					<div class="top-social">
						<a href="https://www.facebook.com/OxfamWereldwinkels" target="_blank"><span class="fab fa-facebook-f"></span></a>
						<a href="https://www.youtube.com/OxfamWereldwinkels" target="_blank"><span class="fab fa-youtube"></span></a>
						<a href="https://www.instagram.com/oxfam.wereldwinkels/" target="_blank"><span class="fab fa-instagram"></span></a>
						<a href="https://twitter.com/OxfamFairTrade" target="_blank"><span class="fab fa-twitter"></span></a>
						<a href="https://www.linkedin.com/company/oxfam-fair-trade/" target="_blank"><span class="fab fa-linkedin-in"></span></a>
					</div>
				</div>
				<div id="nav" class="nav">
					<ul id="menu-main-menu" class="menu"><li id="menu-item-1587" class="menu-item menu-item-type-post_type_archive menu-item-object-chain menu-item-1587"><a href="https://www.oxfamwereldwinkels.be/expertise/">Eerlijke handel</a></li>
						<li id="menu-item-1515" class="menu-item menu-item-type-post_type menu-item-object-page menu-item-1515"><a href="https://www.oxfamwereldwinkels.be/producten/">Producten</a></li>
						<li id="menu-item-1292" class="menu-item menu-item-type-post_type menu-item-object-page menu-item-1292"><a href="https://www.oxfamwereldwinkels.be/doe-mee/">Doe mee</a></li>
						<li id="menu-item-135" class="menu-item menu-item-type-post_type menu-item-object-page menu-item-135"><a href="https://www.oxfamwereldwinkels.be/over-ons/">Over ons</a></li>
						<li id="menu-item-70" class="orange-btn menu-item menu-item-type-custom menu-item-object-custom menu-item-70"><a href="/winkels/">Vind winkel</a></li>
						<li id="menu-item-71" class="green-btn menu-item menu-item-type-custom menu-item-object-custom menu-item-71"><a href="/word-vrijwilliger/">Word vrijwilliger</a></li>
					</ul>
				</div>
				<div id="mobile-nav"></div>
			</div>
		</div>
	</div>
</div>
<div id="header" class="visible-sm visible-xs">
	<div class="container">
		<div class="col-row display-flex">
			<div class="col-xs-6 align-self-center">
				<div class="logo">
					<a href="/"><img src="https://www.oxfamwereldwinkels.be/wp-content/themes/oxfam/images/logo-green.svg" alt=""></a>
				</div>
			</div> 
			<div class="col-xs-6 align-self-center">
				<div class="header-items display-flex">
					<div class="header__item header__item_winkel">
						<a href="/winkels/">Winkels</a>
					</div>
					<div class="header__item header__item_search">
						<span>Zoek</span>
					</div>
				</div>
			</div>
		</div>
		<div id="menu-btn"><img src="https://www.oxfamwereldwinkels.be/wp-content/themes/oxfam/images/menu-btn-black.png" alt=""></div>
	</div>
	<div class="top-search top-search_mobile hidden">         
		<form action="/" method="get">
			<input type="text" name="s" placeholder="Zoeken">
			<input type="submit" value="">
		</form>
	</div>
</div>

<?php
	// Shop search
	if ( $nm_globals['shop_search_header'] ) {
		get_template_part( 'template-parts/woocommerce/searchform' );
	}
?>