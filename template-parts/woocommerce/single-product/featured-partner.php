<?php 
	global $product, $featured_partner;
	
	// Interessante velden (universeel)
	// $featured_partner['name'];
	// $featured_partner['country'];
	// $featured_partner['archive'];
	// $featured_partner['link'];
	// $featured_partner['type'];
	// $featured_partner['title']['rendered'];
	// $featured_partner['content']['rendered'];
	// $featured_partner['excerpt']['rendered'];
	
	// Interessante velden (OWW-site)
	// $featured_partner['quote']['image'];
	// $featured_partner['quote']['content'];
	// $featured_partner['quote']['by'];
	// $featured_partner['bullet_points'];
	
	// Interessante velden (OFT-site)
	// $featured_partner['partner_image'];
	// $featured_partner['partner_quote'];
	// $featured_partner['acf']['partner_bullet_points'];
	// $featured_partner['acf']['partner_region'];
	// $featured_partner['acf']['partner_website'];
	
	if ( ! empty( $featured_partner['acf']['partner_region'] ) ) {
		$region = $featured_partner['acf']['partner_region'];
	} else {
		$region = $featured_partner['country'];
	}
?>

<h3>Producent in de kijker: <span style="font-weight: normal;"><?= $featured_partner['name']; ?> uit <?= $region; ?></span></h3>
<div class="featured-partner">
	<div class="col-row">
		<div class="col-md-7">
			<?php if ( array_key_exists( 'partner_image', $featured_partner ) and $featured_partner['partner_image'] ) : ?>
				<img src="<?= esc_url( $featured_partner['partner_image'] ); ?>">
			<?php elseif ( $featured_partner['quote']['image'] !== '' ) : ?>
				<!-- Tijdelijke fallback voor OWW-pagina's -->
				<img src="<?= esc_url( $featured_partner['quote']['image'] ); ?>">
			<?php endif; ?>
			
			<!-- Key 'acf' bestaat altijd, ook bij OWW-partners -->
			<?php if ( array_key_exists( 'partner_bullet_points', $featured_partner['acf'] ) and count( $featured_partner['acf']['partner_bullet_points'] ) > 0 ) : ?>
				<ul>
					<?php foreach ( $featured_partner['acf']['partner_bullet_points'] as $bullet ) : ?>
						<li><?= $bullet['partner_bullet_point']; ?></li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<!-- Tijdelijke fallback voor OWW-pagina's -->
				<?= $featured_partner['bullet_points']; ?>
			<?php endif; ?>
			
			<p><a href="<?= esc_url( $featured_partner['link'] ); ?>" target="_blank">Maak kennis met <?= $featured_partner['name']; ?></a></p>
		</div>
		<div class="col-md-5">
			<?php if ( array_key_exists( 'partner_quote', $featured_partner ) and $featured_partner['partner_quote']['rendered'] ) : ?>
				<?= $featured_partner['partner_quote']['rendered']; ?>
			<?php elseif ( ! empty( $featured_partner['quote']['content'] ) ) : ?>
				<!-- Tijdelijke fallback voor OWW-pagina's -->
				<blockquote>
					&#8220;<?= $featured_partner['quote']['content']; ?>&#8221;
					<?php if ( ! empty( $featured_partner['quote']['by'] ) ) : ?>
						<cite><?= $featured_partner['quote']['by']; ?></cite>
					<?php endif; ?>
				</blockquote>
			<?php endif; ?>
		</div>
	</div>
</div>