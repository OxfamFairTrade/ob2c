<?php 
	global $product, $featured_partner;
	
	// Interessante velden (universeel)
	// $featured_partner['name'];
	// $featured_partner['country'];
	// $featured_partner['archive'];
	// $featured_partner['link'];
	// $featured_partner['title']['rendered'];
	// $featured_partner['content']['rendered'];
	// $featured_partner['excerpt']['rendered'];
	
	// Interessante velden (OWW-site)
	// $featured_partner['bullet_points'];
	// $featured_partner['quote']['content'];
	// $featured_partner['quote']['by'];
	// $featured_partner['quote']['image'];
	
	// Interessante velden (OFT-site)
	// $featured_partner['image'];
	// $featured_partner['acf']['partner_bullet_points'];
	// $featured_partner['acf']['partner_region'];
	// $featured_partner['acf']['partner_website'];
	
	$blocks = parse_blocks( $featured_partner['content']['rendered'] );
	if ( count( $blocks ) === 1 and $blocks[0]['blockName'] === NULL ) {
		// Klassieke pagina
		return '';
	} else {
		foreach ( $blocks as $block ) {
			if ( $block['blockName'] === 'core/pullquote' ) {
				var_dump_pre( $block );
				$featured_partner['quote']['content'] = wp_strip_all_tags( $block['innerHTML'] );
				$featured_partner['quote']['by'] = '';
			}
		}
	}
?>

<h3>Producent in de kijker: <span style="font-weight: normal;"><?php echo $featured_partner['name']; ?></span></h3>
<div class="featured-partner">
	<div class="col-row">
		<div class="col-md-7">
			<?php if ( array_key_exists( 'image', $featured_partner ) and $featured_partner['image'] !== '' ) : ?>
				<img src="<?= esc_url( $featured_partner['image'] ); ?>">
			<?php elseif ( $featured_partner['quote']['image'] !== '' ) : ?>
				<img src="<?= esc_url( $featured_partner['quote']['image'] ); ?>">
			<?php endif; ?>
			
			<?php if ( array_key_exists( 'acf', $featured_partner ) and $featured_partner['acf']['partner_bullet_points'] !== '' ) : ?>
				<ul>
					<?php foreach ( $featured_partner['acf']['partner_bullet_points'] as $bullet ) : ?>
						<li><?= $bullet['partner_bullet_point']; ?></li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<?= $featured_partner['bullet_points']; ?>
			<?php endif; ?>
			
			<p><a href="<?= esc_url( $featured_partner['link'] ); ?>">Maak kennis met <?= $featured_partner['name']; ?></a></p>
		</div>
		<div class="col-md-5">
			<?php if ( ! empty( $featured_partner['quote']['content'] ) ) : ?>
				<blockquote>
					&#8220;<?= $featured_partner['quote']['content']; ?>&#8221;
					<?php if ( ! empty( $featured_partner['quote']['by'] ) ) : ?>
						<footer><?= $featured_partner['quote']['by']; ?></footer>
					<?php endif; ?>
				</blockquote>
			<?php endif; ?>
		</div>
	</div>
</div>