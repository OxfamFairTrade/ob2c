<?php
	/*
	Template Name: Lokale Homepage
	Template Post Type: page
	*/

	// Only adding the "entry-content" post class on non-woocommerce pages to avoid CSS conflicts
	$post_class = ( nm_is_woocommerce_page() ) ? '' : 'entry-content';
?>

<?php get_header(); ?>

<?php if ( have_posts() ) : ?>
	<?php while ( have_posts() ) : ?>
		<?php the_post(); ?>
		<div id="content">
			<div class="container">
				<div class="breadcrumb">
					<a href="https://stage.oxfamwereldwinkels.be/">Home</a> <span class="sep"></span> <span class="breadcrumb_last" aria-current="page">Webshop <?php echo get_webshop_name(); ?></span>
				</div>
				<div class="row">
					<div class="col-md-6">
						<h2>Oxfam Webshop <?php echo get_webshop_name(); ?></h2>
						<?php the_content(); ?>
					</div>
					<div class="col-md-6">
						<?php wc_get_template( 'product-searchform_nm.php' ); ?>
					</div>
					<?php get_template_part( 'template-parts/header/general-store-notice' ); ?>
				</div>
				<div class="row">
					<div class="col-md-6">
						<h2>Shoppen per categorie</h2>
					</div>
					<div class="col-md-6">
						<a href="#">Alle producten</a>
					</div>
				</div>
				<div class="row">
					<div class="col-md-12">
						<?php echo do_shortcode('[nm_product_categories orderby="term_order" title_tag="h3" parent="0"]'); ?>
					</div>
				</div>
				<div class="row">
					<div class="col-md-6">
						<h2>Producten in de kijker</h2>
					</div>
					<div class="col-md-6">
						<a href="#">Alle producten</a>
					</div>
				</div>
				<div class="row">
					<div class="col-md-12">
						<?php echo do_shortcode('[nm_product_slider shortcode="featured_products" per_page="-1" columns="4" columns_mobile="1" orderby="menu_order" order="ASC" arrows="1"]'); ?>
					</div>
				</div>
				<div class="row">
					<div class="col-md-12">
						<div class="full-size-banner" style="background-image: url('<?php echo get_the_post_thumbnail_url( get_the_ID(), 'full' ); ?>'); background-size: cover; width: 100%; height: 400px;"></div>
					</div>
				</div>
				<div class="row">
					<div class="col-md-6">
						<h2>Promoties</h2>
					</div>
					<div class="col-md-6">
						<a href="#">Alle producten</a>
					</div>
				</div>
				<div class="row">
					<div class="col-md-12">
						<?php echo do_shortcode('[nm_product_slider shortcode="sale_products" per_page="-1" columns="4" columns_mobile="1" orderby="menu_order" order="ASC" arrows="1"]'); ?>
					</div>
				</div>
			</div>
		</div>
	<?php endwhile; ?>
<?php endif; ?>

<?php get_footer(); ?>