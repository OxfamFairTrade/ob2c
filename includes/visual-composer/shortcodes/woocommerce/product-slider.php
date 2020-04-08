<?php
	
	// Shortcode: nm_product_slider
	function nm_shortcode_product_slider( $atts, $content = NULL ) {
		nm_add_page_include( 'product-slider' );
		
		extract( shortcode_atts( array(
			'shortcode'	=> 'recent_products',
			'per_page'	=> '12',
			'columns'	=> '4',
			'orderby'	=> 'date',
			'order'		=> 'DESC'
		), $atts ) );
		
        $columns = intval( $columns );
        $data_settings = 'data-slides-to-show="' . $columns . '" data-slides-to-scroll="' . $columns . '"';
        
		$shortcode_string = '[' . $shortcode . ' per_page="' . intval( $per_page ) . '" columns="' . intval( $columns ) . '" orderby="' . $orderby . '" order="' . $order . '"]';
		
        return '<div class="nm-product-slider col-' . $columns . '" ' . $data_settings . '>' . do_shortcode( $shortcode_string ) . '</div>';
	}
	
	add_shortcode( 'nm_product_slider', 'nm_shortcode_product_slider' );
	