<?php 
	global $product, $featured_partner;
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
			
			<p><a href="<?php echo esc_url( $featured_partner['link'] ); ?>">Maak kennis met <?php echo $featured_partner['name']; ?></a></p>
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