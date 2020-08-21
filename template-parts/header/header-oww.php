<?php
	global $nm_globals, $nm_theme_options;
	// Voorlopig volledig statisch, behalve de 'Producten'-link
	// We duiden 'Producten' voorlopig ook steeds aan als huidige pagina
?>

<div id="header" class="hidden-sm hidden-xs">
	<div class="container">
		<div class="col-row md-display-flex">
			<div class="col-md-3 md-align-self-center">
				<div class="logo">
					<a href="https://www.oxfamwereldwinkels.be/">
						<img src="https://www.oxfamwereldwinkels.be/wp-content/themes/oxfam/images/logo-green.svg" width="250" alt="Logo Oxfam-Wereldwinkels">
					</a>
				</div>
			</div>
			<div class="col-md-9 md-align-self-center">
				<div class="topbar">
					<div class="top-menu">
						<ul id="menu-top-menu" class="menu"><li class="menu-item menu-item-type-taxonomy menu-item-object-category"><a href="https://www.oxfamwereldwinkels.be/pers/">Voor pers</a></li>
							<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://www.oxfamwereldwinkels.be/oxfam-op-school/">Voor scholen</a></li>
							<li class="menu-item menu-item-type-custom menu-item-object-custom"><a target="_blank" rel="noopener noreferrer" href="https://www.oxfamfairtrade.be/nl/">Voor bedrijven</a></li>
						</ul>
					</div>
					<div class="top-social">
						<a href="<?php echo get_permalink( wc_get_page_id('myaccount') ); ?>"><span class="fab"></span> Aanmelden</a>
					</div>
					<div class="top-search">
						<form id="globalSearch" action="https://www.oxfamwereldwinkels.be/" method="get">
							<input type="text" name="s" placeholder="Zoeken">
							<input type="submit" value="">
						</form>
					</div>
				</div>
				<div id="nav" class="nav">
					<ul id="menu-main-menu" class="menu"><li class="menu-item menu-item-type-post_type_archive menu-item-object-chain menu-item-1587"><a href="https://www.oxfamwereldwinkels.be/expertise/">Eerlijke handel</a></li>
						<li class="menu-item menu-item-type-post_type menu-item-object-page current-menu-item"><a href="<?php echo get_permalink( wc_get_page_id('shop') ); ?>">Producten</a></li>
						<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://www.oxfamwereldwinkels.be/doe-mee/">Doe mee</a></li>
						<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://www.oxfamwereldwinkels.be/over-ons/">Over ons</a></li>
						<li class="orange-btn menu-item menu-item-type-custom menu-item-object-custom"><a href="https://www.oxfamwereldwinkels.be/winkels/">Vind winkel</a></li>
						<li class="green-btn menu-item menu-item-type-custom menu-item-object-custom"><a href="https://www.oxfamwereldwinkels.be/word-vrijwilliger/">Word vrijwilliger</a></li>
					</ul>
				</div>
				<div id="mobile-nav"></div>
			</div>
		</div>
	</div>
</div>
<div id="header" class="visible-sm visible-xs" style="display: none;">
	<div class="container">
		<div class="col-row display-flex">
			<div class="col-xs-6 align-self-center">
				<div class="logo">
					<a href="https://www.oxfamwereldwinkels.be/"><img src="https://www.oxfamwereldwinkels.be/wp-content/themes/oxfam/images/logo-green.svg" width="250" alt="Logo Oxfam-Wereldwinkels"></a>
				</div>
			</div> 
			<div class="col-xs-6 align-self-center">
				<div class="header-items display-flex">
					<div class="header__item header__item_winkel">
						<a href="https://www.oxfamwereldwinkels.be/winkels/">Winkels</a>
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
		<form action="https://www.oxfamwereldwinkels.be/" method="get">
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