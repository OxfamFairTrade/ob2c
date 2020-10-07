<?php 
	global $product, $featured_partner;
?>

<h3>Producent in de kijker: <span style="font-weight: normal;"><?php echo $featured_partner['name']; ?></span></h3>
<div class="featured-partner">
	<div class="col-row">
		<div class="col-md-7">
			<img src="<?php echo esc_url( $featured_partner['quote']['image'] ); ?>">
			<?php echo $featured_partner['bullet_points']; ?>
			<p><a href="<?php echo esc_url( $featured_partner['link'] ); ?>">Maak kennis met <?php echo $featured_partner['name']; ?></a></p>
		</div>
		<div class="col-md-5">
			<?php if ( ! empty( $featured_partner['quote']['content'] ) ) : ?>
				<blockquote>
					&#8220;<?php echo $featured_partner['quote']['content']; ?>&#8221;
					<?php if ( ! empty( $featured_partner['quote']['by'] ) ) : ?>
						<footer><?php echo $featured_partner['quote']['by']; ?></footer>
					<?php endif; ?>
				</blockquote>
			<?php endif; ?>
		</div>
	</div>
</div>