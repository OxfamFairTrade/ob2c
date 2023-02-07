<?php
	function contains( $allergens ) {
		$parts = explode( '|', $allergens );
		foreach ( $parts as $part ) {
			$term = explode( '>', $part );
			if ( $term[0] == 'Product bevat' ) {
				$contains[] = $term[1];
			}
		}
		return implode( ', ', $contains );
	}

	function may_contain( $allergens ) {
		$parts = explode( '|', $allergens );
		foreach ( $parts as $part ) {
			$term = explode( '>', $part );
			if ( $term[0] == 'Kan sporen bevatten van' ) {
				$may_contain[] = $term[1];
			}
		}
		return implode( ', ', $may_contain );
	}

	function get_subcategory( $cats ) {
		switch ( $cats ) {
			case stristr( $cats, 'Koffie' ):
				return 'Koffie & thee';
				break;
			case stristr( $cats, 'Ontbijt' ):
				return 'Ontbijt';
				break;
			case stristr( $cats, 'Snacks' ):
				return 'Snacks & drinks';
				break;
			case stristr( $cats, 'Wereldkeuken' ):
				return 'Wereldkeuken';
				break;
			case stristr( $cats, 'Wijn' ):
				return 'Wijn';
				break;
			default:
				return $cats;
				break;
		}
	}

	function get_subcategory_fr( $cats ) {
		switch ( $cats ) {
			case stristr( $cats, 'Koffie' ):
				return 'Café & thé';
				break;
			case stristr( $cats, 'Ontbijt' ):
				return 'Petit déjeuner';
				break;
			case stristr( $cats, 'Snacks' ):
				return 'Snacks & drinks';
				break;
			case stristr( $cats, 'Wereldkeuken' ):
				return 'Cuisine du monde';
				break;
			case stristr( $cats, 'Wijn' ):
				return 'Vin';
				break;
			default:
				return $cats;
				break;
		}
	}

	function get_subcategory_en( $cats ) {
		switch ( $cats ) {
			case stristr( $cats, 'Koffie' ):
				return 'Coffee & tea';
				break;
			case stristr( $cats, 'Ontbijt' ):
				return 'Breakfast';
				break;
			case stristr( $cats, 'Snacks' ):
				return 'Snacks & drinks';
				break;
			case stristr( $cats, 'Wereldkeuken' ):
				return 'World kitchen';
				break;
			case stristr( $cats, 'Wijn' ):
				return 'Wine';
				break;
			default:
				return $cats;
				break;
		}
	}

	function get_bio_label( $bio ) {
		if ( $bio == 'Ja' ) {
			return ':biobol.psd';
		} else {
			return ':geenbio.psd';
		}
	}

	function split_by_paragraph( $text ) {
		$parts = explode( '</p><p>', $text );
		$bits = explode( '<br>', $parts[0] );
		return $bits[0];
	}

	function get_product_image( $sku ) {
		return ':'.$sku.'.jpg';
	}

	function get_custom_label_0( $tags ) {
		$tags = explode( ',', $tags );
		if ( in_array( 'promotie', $tags ) ) {
			return 'promotie';
		} else {
			return '';
		}
	}

	function get_custom_label_1( $tags ) {
		$tags = explode( ',', $tags );
		if ( in_array( 'bite to fight', $tags ) ) {
			return 'bite to fight';
		} else {
			return '';
		}
	}

	function get_custom_label_2( $tags ) {
		$tags = explode( ',', $tags );
		if ( in_array( 'sinterklaas', $tags ) ) {
			return 'sinterklaas';
		} elseif ( in_array( 'pasen', $tags ) ) {
			return 'pasen';
		} else {
			return '';
		}
	}

	function get_medium_sized_image( $image_id ) {
		$image_data = wp_get_attachment_image_src( $image_id, 'medium' );
		if ( $image_data ) {
			// URL staat als 1ste element in de array
			return $image_data[0].'?modified='.get_the_date( 'Y-m-d', $image_id );
		} else {
			return '';
		}
	}
?>