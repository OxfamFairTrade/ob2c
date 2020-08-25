<?php
	global $nm_globals, $nm_theme_options;
	// Voorlopig volledig statisch, behalve de 'Producten'-link
	// We duiden 'Producten' voorlopig ook steeds aan als huidige pagina
?>

<!-- Regulier menu -->
<div id="header" class="hidden-sm hidden-xs">
	<div class="container">
		<div class="col-row md-display-flex">
			<div class="col-md-3 md-align-self-center">
				<div class="logo">
					<a href="https://stage.oxfamwereldwinkels.be/">
						<img src="https://stage.oxfamwereldwinkels.be/wp-content/themes/oxfam/images/logo-green.svg" width="250" alt="Logo Oxfam-Wereldwinkels">
					</a>
				</div>
			</div>
			<div class="col-md-9 md-align-self-center">
				<div class="topbar">
					<div class="top-menu">
						<ul id="menu-top-menu" class="menu"><li class="menu-item menu-item-type-taxonomy menu-item-object-category"><a href="https://stage.oxfamwereldwinkels.be/pers/">Voor pers</a></li>
							<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://stage.oxfamwereldwinkels.be/oxfam-op-school/">Voor scholen</a></li>
							<li class="menu-item menu-item-type-custom menu-item-object-custom"><a target="_blank" rel="noopener noreferrer" href="https://www.oxfamfairtrade.be/nl/">Voor bedrijven</a></li>
						</ul>
					</div>
					<div class="top-social">
						<a href="<?php echo get_permalink( wc_get_page_id('myaccount') ); ?>"><span class="fab"></span> Aanmelden</a>
					</div>
					<div class="top-search">
						<form id="globalSearch" action="https://stage.oxfamwereldwinkels.be/" method="get">
							<input type="text" name="s" placeholder="Zoeken">
							<input type="submit" value="">
						</form>
					</div>
				</div>
				<div id="nav" class="nav">
					<ul id="menu-main-menu" class="menu"><li class="menu-item menu-item-type-post_type_archive menu-item-object-chain menu-item-1587"><a href="https://stage.oxfamwereldwinkels.be/expertise/">Eerlijke handel</a></li>
						<li class="menu-item menu-item-type-post_type menu-item-object-page current-menu-item"><a href="<?php echo get_permalink( wc_get_page_id('shop') ); ?>">Producten</a></li>
						<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://stage.oxfamwereldwinkels.be/doe-mee/">Doe mee</a></li>
						<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://stage.oxfamwereldwinkels.be/over-ons/">Over ons</a></li>
						<li class="orange-btn menu-item menu-item-type-custom menu-item-object-custom"><a href="https://stage.oxfamwereldwinkels.be/winkels/">Vind winkel</a></li>
						<li class="green-btn menu-item menu-item-type-custom menu-item-object-custom"><a href="https://stage.oxfamwereldwinkels.be/word-vrijwilliger/">Word vrijwilliger</a></li>
						<?php
							$cart_url = ( $nm_globals['cart_panel'] ) ? '#' : wc_get_cart_url();
							echo sprintf(
								'<li class="nm-menu-cart menu-item"><a href="%s" id="nm-menu-cart-btn">%s</a></li>',
								esc_url( $cart_url ),
								nm_get_cart_contents_count()
							);
						?>
					</ul>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Mobiel menu, voorlopig hard verbergen wegens ontbreken van klasses .visible-sm en .visible-xs -->
<div id="header" class="visible-sm visible-xs" style="display: none;">
	<div class="container">
		<div class="col-row display-flex">
			<div class="col-xs-6 align-self-center">
				<div class="logo">
					<a href="https://stage.oxfamwereldwinkels.be/"><img src="https://stage.oxfamwereldwinkels.be/wp-content/themes/oxfam/images/logo-green.svg" width="250" alt="Logo Oxfam-Wereldwinkels"></a>
				</div>
			</div> 
			<div class="col-xs-6 align-self-center">
				<div class="header-items display-flex">
					<div class="header__item header__item_winkel">
						<a href="https://stage.oxfamwereldwinkels.be/winkels/">Winkels</a>
					</div>
					<div class="header__item header__item_search">
						<span>Zoek</span>
					</div>
				</div>
			</div>
		</div>
		<div id="menu-btn"><img src="https://stage.oxfamwereldwinkels.be/wp-content/themes/oxfam/images/menu-btn-black.png" alt=""></div>
	</div>
	<div class="top-search top-search_mobile hidden">         
		<form action="https://stage.oxfamwereldwinkels.be/" method="get">
			<input type="text" name="s" placeholder="Zoeken">
			<input type="submit" value="">
		</form>
	</div>
</div>
<div id="side-menu" class="" style="display: none;">
	<!-- Zoekbalk beter weglaten in mobiel menu? -->
	<div class="top-search">
		<form action="https://stage.oxfamwereldwinkels.be/" method="get">
			<input type="text" name="s" placeholder="Zoeken">
			<input type="submit" value="">
		</form>
	</div>
	<div class="nav items">
		<ul id="menu-main-menu-1" class="menu">
			<li class="menu-item menu-item-type-post_type_archive menu-item-object-chain"><a href="https://stage.oxfamwereldwinkels.be/expertise/">Eerlijke handel</a></li>
			<li class="menu-item menu-item-type-custom menu-item-object-custom"><a href="<?php echo get_permalink( wc_get_page_id('shop') ); ?>">Producten</a></li>
			<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://stage.oxfamwereldwinkels.be/doe-mee/">Doe mee</a></li>
			<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://stage.oxfamwereldwinkels.be/over-ons/">Over ons</a></li>
			<li class="orange-btn menu-item menu-item-type-custom menu-item-object-custom"><a href="https://stage.oxfamwereldwinkels.be/winkels/">Vind winkel</a></li>
			<li class="green-btn menu-item menu-item-type-custom menu-item-object-custom"><a href="https://stage.oxfamwereldwinkels.be/word-vrijwilliger/">Word vrijwilliger</a></li>
		</ul>
	</div>
	<div class="top-menu">
		<ul id="menu-top-menu-1" class="menu">
			<li class="menu-item menu-item-type-taxonomy menu-item-object-category"><a href="https://stage.oxfamwereldwinkels.be/pers/">Voor pers</a></li>
			<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://stage.oxfamwereldwinkels.be/oxfam-op-school/">Voor scholen</a></li>
			<li class="menu-item menu-item-type-custom menu-item-object-custom"><a target="_blank" rel="noopener noreferrer" href="https://www.oxfamfairtrade.be/nl/">Voor bedrijven</a></li>
		</ul>
	</div>
	<!-- Socials beter weglaten in mobiel menu? -->
	<div class="top-social">
		<a href="https://www.facebook.com/OxfamWereldwinkels" target="_blank"><span class="fab fa-facebook-f"></span></a>
		<a href="https://www.youtube.com/OxfamWereldwinkels" target="_blank"><span class="fab fa-youtube"></span></a>
		<a href="https://www.instagram.com/oxfam.wereldwinkels/" target="_blank"><span class="fab fa-instagram"></span></a>
		<a href="https://twitter.com/OxfamFairTrade" target="_blank"><span class="fab fa-twitter"></span></a>
		<a href="https://www.linkedin.com/company/oxfam-fair-trade/" target="_blank"><span class="fab fa-linkedin-in"></span></a>
	</div>
	<div class="nav btns">
		<ul id="menu-main-menu-2" class="menu">
			<li class="orange-btn menu-item menu-item-type-custom menu-item-object-custom"><a href="https://stage.oxfamwereldwinkels.be/winkels/">Vind winkel</a></li>
			<li class="green-btn menu-item menu-item-type-custom menu-item-object-custom"><a href="https://stage.oxfamwereldwinkels.be/word-vrijwilliger/">Word vrijwilliger</a></li>
		</ul>
	</div>
</div>