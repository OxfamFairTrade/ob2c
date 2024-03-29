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
					<a href="<?= home_url('/'); ?>">
						<img src="<?= get_stylesheet_directory_uri(); ?>/images/logo-green.svg" width="250" alt="Logo Oxfam-Wereldwinkels">
					</a>
				</div>
			</div>
			<div class="col-md-9 md-align-self-center">
				<div class="topbar">
					<div class="top-menu">
						<ul id="menu-top-menu" class="menu"><li class="menu-item menu-item-type-taxonomy menu-item-object-category"><a href="https://oxfambelgie.be/pers" target="_blank">Voor pers</a></li>
							<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://oxfambelgie.be/doe-mee/oxfam-op-jouw-school" target="_blank">Voor scholen</a></li>
							<li class="menu-item menu-item-type-custom menu-item-object-custom"><a href="https://www.oxfamfairtrade.be/nl/bedrijven/" target="_blank">Voor bedrijven</a></li>
						</ul>
					</div>
					<!--<div class="top-social logged-in-user">
						<?php if ( ! is_main_site() ) : ?>
							<a href="<?php echo get_permalink( wc_get_page_id('myaccount') ); ?>">
								<?php
									if ( is_user_logged_in() ) {
										$user = wp_get_current_user();
										if ( ! empty( $user->first_name ) ) {
											echo 'Welkom '.$user->first_name;
										} else {
											echo 'Mijn account';
										}
									} else {
										echo 'Aanmelden';
									}
								?>
							</a>
						<?php endif; ?>
					</div>-->
					<div class="top-search">
						<form id="globalSearch" action="https://oxfambelgie.be/search" method="get">
							<input type="text" name="query" placeholder="Zoeken">
							<input type="submit" value="">
						</form>
					</div>
					<div class="top-social">
						<ul class="top-social--menu">
							<li class="top-social--item facebook"><a href="https://www.facebook.com/OxfamWereldwinkels" target="_blank"></a></li>
							<li class="top-social--item instagram"><a href="https://www.instagram.com/oxfam.BE/" target="_blank"></a></li>
							<li class="top-social--item twitter"><a href="https://twitter.com/OxfamBE" target="_blank"></a></li>
						</ul>
					</div>
				</div>
				<div id="nav" class="nav">
					<ul id="menu-main-menu" class="menu">
						<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://oxfambelgie.be/wij-zijn-oxfam" target="_blank">Over Oxfam</a></li>
						<li class="menu-item menu-item-type-post_type menu-item-object-page<?php echo is_shop() ? ' current-menu-item' : ''; ?>"><a href="<?= get_permalink( wc_get_page_id('shop') ); ?>">Producten</a></li>
						<li class="menu-item menu-item-type-custom menu-item-object-custom"><a href="https://www.oxfamfairtrade.be/nl/recepten/" target="_blank">Recepten</a></li>
						<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://oxfambelgie.be/doe-mee" target="_blank">Doe mee</a></li>
						<li class="orange-btn menu-item menu-item-type-custom menu-item-object-custom"><a href="https://oxfambelgie.be/shop-finder" target="_blank">Vind winkel</a></li>
						<li class="green-btn menu-item menu-item-type-custom menu-item-object-custom"><a href="https://oxfambelgie.be/doe-mee/word-vrijwilliger" target="_blank">Word vrijwilliger</a></li>
					</ul>
                    <?php if ( ! is_main_site() ) : ?>
                        <a href="<?= get_permalink( wc_get_page_id('myaccount') ); ?>" class="mini-acc-btn">
                            <?php
                            if ( is_user_logged_in() ) {
                                $user = wp_get_current_user();
                                if ( ! empty( $user->first_name ) ) {
                                    echo 'Welkom '.$user->first_name;
                                } else {
                                    echo 'Mijn account';
                                }
                            } else {
                                echo 'Aanmelden';
                            }
                            ?>
                        </a>
                    <?php endif; ?>
					<?php
						$cart_url = ( $nm_globals['cart_panel'] ) ? '#' : wc_get_cart_url();
						if ( is_main_site() ) {
							$js_target = 'class="mini-cart-btn store-selector-open"';
						} else {
							$js_target = 'id="nm-menu-cart-btn" class="mini-cart-btn"';
						}
						echo sprintf(
							'<a href="%s" %s>%s</a>',
							esc_url( $cart_url ),
							$js_target,
							nm_get_cart_contents_count()
						);
					?>
				</div>
			</div>
		</div>
	</div>
</div>

<div id="header" class="visible-sm visible-xs" style="display: none;">
	<div class="container">
		<div class="col-row">
			<div class="col-xs-6 align-self-center">
				<div class="logo">
					<a href="<?= home_url('/'); ?>"><img src="<?= get_stylesheet_directory_uri(); ?>/images/logo-green.svg" width="250" alt="Logo Oxfam-Wereldwinkels"></a>
				</div>
			</div> 
			<div class="col-xs-6 align-self-center">
				<div class="header-items display-flex">
					<div class="header__item header__item_winkel">
						<a href="https://oxfambelgie.be/shop-finder">Winkels</a>
					</div>
					<div class="header__item header__item_shop">
						<a href="<?= get_permalink( wc_get_page_id('shop') ); ?>">Producten</a>
					</div>
					<div class="header__item header__item_search">
						<span>Zoek</span>
					</div>
                    <?php if ( ! is_main_site() ) : ?>
                        <div class="header__item header__item_account">
                            <a href="<?= get_permalink( wc_get_page_id('myaccount') ); ?>">
                                <?php
                                if ( is_user_logged_in() ) {
                                    $user = wp_get_current_user();
                                    if ( ! empty( $user->first_name ) ) {
                                        echo 'Welkom '.$user->first_name;
                                    } else {
                                        echo 'Mijn account';
                                    }
                                } else {
                                    echo 'Aanmelden';
                                }
                                ?>
                            </a>
                        </div>
                    <?php endif; ?>
                    <div class="header__item header__item_cart">
						<?php
							echo sprintf(
								'<a href="%s" %s>%s</a>',
								esc_url( $cart_url ),
								$js_target,
								nm_get_cart_contents_count()
							);
						?>
					</div>
				</div>
			</div>
		</div>
		<div id="menu-btn"><img src="<?= get_stylesheet_directory_uri(); ?>/images/menu-btn-black.png"></div>
	</div>
	<div class="top-search top-search_mobile hidden">
		<form action="https://oxfambelgie.be/search" method="get">
			<input type="text" name="query" placeholder="Zoeken">
			<input type="submit" value="">
		</form>
	</div>
</div>
<div id="side-menu">
	<!-- Als we het mobiele menu fixed maken, kan deze zoekbalk verdwijnen -->
    <div id="menu-btn-close"></div>
    <div class="top-search">
		<form action="https://oxfambelgie.be/search" method="get">
			<input type="text" name="query" placeholder="Zoeken">
			<input type="submit" value="">
		</form>
	</div>
	<div class="nav items">
		<ul id="menu-main-menu-1" class="menu">
			<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://oxfambelgie.be/wij-zijn-oxfam" target="_blank">Over Oxfam</a></li>
			<li class="menu-item menu-item-type-custom menu-item-object-custom"><a href="<?= get_permalink( wc_get_page_id('shop') ); ?>">Producten</a></li>
			<li class="menu-item menu-item-type-custom menu-item-object-custom"><a href="https://www.oxfamfairtrade.be/nl/recepten/" target="_blank">Recepten</a></li>
			<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://oxfambelgie.be/doe-mee" target="_blank">Doe mee</a></li>
			<li class="orange-btn menu-item menu-item-type-custom menu-item-object-custom"><a href="https://oxfambelgie.be/shop-finder" target="_blank">Vind winkel</a></li>
			<li class="green-btn menu-item menu-item-type-custom menu-item-object-custom"><a href="https://oxfambelgie.be/doe-mee/word-vrijwilliger" target="_blank">Word vrijwilliger</a></li>
		</ul>
	</div>
	<div class="top-menu">
		<ul id="menu-top-menu-1" class="menu">
			<li class="menu-item menu-item-type-taxonomy menu-item-object-category"><a href="https://oxfambelgie.be/pers" target="_blank">Voor pers</a></li>
			<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://oxfambelgie.be/doe-mee/oxfam-op-jouw-school" target="_blank">Voor scholen</a></li>
			<li class="menu-item menu-item-type-custom menu-item-object-custom"><a href="https://www.oxfamfairtrade.be/nl/bedrijven/" target="_blank">Voor bedrijven</a></li>
		</ul>
	</div>
	<div class="nav btns">
		<ul id="menu-main-menu-2" class="menu">
			<li class="orange-btn menu-item menu-item-type-custom menu-item-object-custom"><a href="https://oxfambelgie.be/shop-finder" target="_blank">Vind winkel</a></li>
			<li class="green-btn menu-item menu-item-type-custom menu-item-object-custom"><a href="https://oxfambelgie.be/doe-mee/word-vrijwilliger" target="_blank">Word vrijwilliger</a></li>
		</ul>
	</div>
</div>