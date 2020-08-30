<?php 
	global $product;

	// Nieuwe globals, te vervangen door template parameters (WP 5.5+)
	// Opgelet: als de volgorde waarin product-origin.php en product-details.php aangeroepen worden wijzigt, hebben we een probleem!
	global $partners, $featured_partner;

	$partners = array();
	$featured_partner = false;
	$partner_terms = get_partner_terms_by_product( $product );
	
	if ( count( $partner_terms ) > 0 ) {
		foreach ( $partner_terms as $term_id => $partner_name ) {
			$partners[] = get_info_by_partner( get_term_by( 'id', $term_id, 'product_partner' ) );
		}

		$a_partners = wp_list_filter( $partners, array( 'type' => 'A' ) );
		$b_partners = wp_list_filter( $partners, array( 'type' => 'B' ) );
		
		// Zoek een random A/B-partner om uit te lichten
		if ( count( $a_partners ) > 0 ) {
			$featured_partner = $a_partners[ array_rand( $a_partners ) ];
		} elseif ( count( $b_partners ) > 0 ) {
			$featured_partner = $b_partners[ array_rand( $b_partners ) ];
		}

		// var_dump_pre( $partners );
		// var_dump_pre( $featured_partner );
	}

	// $partners_with_quote = array_filter( $partners, 'test_if_quote_not_empty' );
	// var_dump_pre( $partners_with_quote );
	function test_if_quote_not_empty( $partner ) {
		return ! empty( $partner['quote']['content'] );
	}
?>

<div class="product-origin">
	<?php if ( count( $partners ) > 0 ) : ?>
		
		<p class="partners">
			<?php
				$terms = array();
				foreach ( $partners as $partner ) {
					// Niet alle partners zullen de eigenschap 'link' bevatten (o.a. alle C-partners)
					// Eventueel check toevoegen op waarde van '_link_to' (= bij crafts, externe site in nieuw tabblad openen)
					if ( ! empty( $partner['link'] ) ) {
						$output = '<a href="'.esc_url( $partner['link'] ).'">'.$partner['name'].'</a>';
					} else {
						$output = $partner['name'];
					}
					$terms[] = $output . ' (' . $partner['country'] . ')';
				}
				echo _n( 'Producent', 'Producenten', count( $terms ) ) . ': ' . implode( ', ', $terms );
			?>
		</p>

	<?php elseif ( $product->get_meta('_herkomst_nl') !== '' ) : ?>

		<p class="countries">
			Herkomst: <?php echo $product->get_meta('_herkomst_nl'); ?>
			<?php
				// Eventueel kunnen we er ook een opsomming van maken
				// echo '<ul>';
				// $countries = explode( ', ', $product->get_meta('_herkomst_nl') );
				// foreach( $countries as $country ) {
				// 	echo '<li>'.$country.'</li>';
				// }
				// echo '</ul>';
			?>
		</p>
	<?php endif; ?>
</div>