<?php
	if ( ! is_main_site() ) {
		// Haal de huidige postcode op
		$current_location = false;
		if ( ! empty( $_COOKIE['current_location'] ) ) {
			$current_location = intval( $_COOKIE['current_location'] );
		}

		if ( does_local_pickup() ) {
			$local_pickup = 'active';
		} else {
			$local_pickup = 'inactive';
		}

		// Indien we false doorgeven, wordt er niet gefilterd op postcode
		if ( does_home_delivery( $current_location ) ) {
			$home_delivery = 'active';
		} else {
			$home_delivery = 'inactive';
		}

		// Haal de huidige gekozen winkel op
		$current_store = false;
		if ( ! empty( $_COOKIE['latest_shop_node'] ) ) {
			$current_store = intval( $_COOKIE['latest_shop_node'] );
		}

		$shops = ob2c_get_pickup_locations();
		if ( $current_store === false or ! array_key_exists( $current_store, $shops ) ) {
			// De cookie slaat op een winkel uit een andere subsite (bv. door rechtstreeks switchen)
			// Stel de hoofdwinkel van de huidige subsite in als fallback
			$current_store = get_option('oxfam_shop_node');
		}
	}
?>

<?php if ( empty( $current_store ) ) : ?>
	<div class="selected-store not-ok">
		<div class="pointer">Online shoppen in jouw Oxfam-winkel?</div>
		<a href="#" class="store-selector-open"><button>Selecteer winkel</button></a>
	</div>
<?php else : ?>
	<div class="selected-store ok">
		<div class="pointer">Jouw Oxfam-winkel</div>
		<!-- Niet alle ID's zitten in elke shop, beter ophalen op hoofdniveau? -->
        <div class="shop-name">
            <strong><?php echo get_shop_name( array( 'node' => $current_store ) );  ?></strong>
        </div>
        <div class="shop-address">
		    <p><?php echo get_shop_address( array( 'node' => $current_store ) ); ?></p>
        </div>
		<ul class="delivery-options">
			<li class="pickup <?php echo $local_pickup; ?>">Afhalen in de winkel</li>
			<li class="shipping <?php echo $home_delivery; ?>">​Levering aan huis<?php if ( $current_location ) echo ' in '.$current_location; ?></li>
		</ul>
		<a href="#" class="store-selector-open" title="Open winkelkiezer">Winkel wijzigen</a>
		<a href="#" class="store-selector-erase" title="Wis winkelkeuze"></a>

		<?php if ( array_key_exists( 'context', $args ) and $args['context'] === 'cart' ) : ?>
			<?php
				switch_to_blog(1);
				
				// Zoek de post-ID op van het WPSL-object met als shop-ID de huidige $current_store
				$post_args = array(
					'post_type'	=> 'wpsl_stores',
					'post_status' => 'publish',
					'meta_key' => 'wpsl_oxfam_shop_node',
					'meta_value' => $current_store,
				);
				$wpsl_stores = new WP_Query( $post_args );
				
				if ( $wpsl_stores->have_posts() ) {
					$wpsl_stores->the_post();
					echo '<p class="opening-hours-title">Openingsuren</p>';
					echo do_shortcode('[wpsl_hours id="'.get_the_ID().'" hide_closed="true"]');
					wp_reset_postdata();
				}

				restore_current_blog();
			?>
			<p class="next-steps">Je ontvangt een bevestigingsmail zodra je bestelling klaar is voor afhaling of thuislevering.</p>
		<?php endif; ?>
	</div>
<?php endif; ?>