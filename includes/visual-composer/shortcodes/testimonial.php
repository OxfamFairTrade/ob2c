<?php

	// Shortcode: nm_testimonial
	function nm_shortcode_nm_testimonial( $atts, $content = NULL ) {
		extract( shortcode_atts( array(
			'image_id'		=> '',
			'signature'		=> '',
			'company'		=> '',
			'image_url'		=> '',
			'link'			=> '',
		), $atts ) );

		$image_class = $image_output = '';
		
		// Internal image
		if ( strlen( $image_id ) > 0 ) {
			$image_class = ' has-image';
			$image = wp_get_attachment_image( $image_id, 'full' );
			$image_output = '<div class="nm-testimonial-image">' . $image . '</div>';
		}

		// External image
		if ( strlen( $image_url ) > 0 ) {
			$image_class = ' has-image';
			$image = '<img src="'.esc_url( $image_url ).'">';
			$image_output = '<div class="nm-testimonial-image"><a href="'.esc_url( $link ).'">' . $image . '</a></div>';
		}

		// Signature
		$signature_output = '';
		if ( ! empty( $signature ) ) {
			$signature_output = '<div class="nm-testimonial-author"><span>' . $signature . '</span>';
			$signature_output .= ( ! empty( $company ) ) ? ', <em>' . $company . '</em></div>' : '</div>';
		}

		return '
		<div class="nm-testimonial' . $image_class . '">' .
		$image_output . '
		<div class="nm-testimonial-content">
		<div class="nm-testimonial-description">' . $content . '</div>' .
		$signature_output . '
		</div>
		</div>';
	}

	add_shortcode( 'nm_testimonial', 'nm_shortcode_nm_testimonial' );