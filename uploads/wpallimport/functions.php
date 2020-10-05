<?php
	function alter_brand( $brand ) {
		if ( $brand == 'Oxfam Fairtrade' or $brand == 'EZA' ) {
			$brand = 'Oxfam Fair Trade';
		}
		return $brand;
	}

	function only_last_term( $string ) {
		$terms = explode( ', ', $string );
		foreach( $terms as $term ) {
			$parts = explode( '->', $term );
			$answer[] = $parts[count($parts)-1];
		}
		return implode( ', ', $answer );
	}

	function calc_content_per_kg_l( $stat, $ompak ) {
		$calc = 0.0;
		$teller = floatval(str_replace(',', '.', $stat));
		$noemer = intval($ompak);
		if ( $teller > 0.001 and $noemer >= 1) {
			$calc = $teller / $noemer;
		}
		return $calc;
	}

	function get_content( $stat, $ompak, $unit ) {
		$cont = "/";
		$calc = calc_content_per_kg_l( $stat, $ompak );
		if ( $calc > 0 ) {	
			if ( $unit == 'L' ) {
				$cont = number_format(100*$calc, 0)." cl";
			} else {
				if ( $calc >= 1 ) {
					$cont = number_format($calc, 2, ',', '.')." kg";
				} else {
					$cont = number_format(1000*$calc, 0)." g";
				}
			}
		}
		return $cont;
	}

	function get_eprice( $cp, $stat, $ompak, $unit ) {
		$eprice = "/";
		$noemer = calc_content_per_kg_l( $stat, $ompak );
		if ( $noemer > 0 ) {
			$teller = floatval(str_replace(',', '.', $cp));
			if ( $teller > 0 ) {
				$calc = $teller / $noemer;
				$eprice = number_format( $calc, 2, ',', '.');
			}
		}
		return $eprice;
	}

	function ditch_zeros( $value ) {
		if ( $value === '0' or $value === '0,0' or $value === '0,000' ) {
			$value = '';
		}
		return $value;
	}

	function translate_tax( $tax_class ) {
		if ( $tax_class === 'reduced' ) {
			return 'voeding';
		} elseif ( $tax_class === 'zero' ) {
			return 'vrijgesteld';
		}
		return $tax_class;
	}

	function add_promo_tag( $tags, $from_date, $to_date ) {
		if ( strtotime($from_date) !== false and strtotime($to_date) !== false ) {
			if ( strtotime($from_date) < strtotime('today midnight') and strtotime($to_date) > strtotime('tomorrow midnight') ) {
				$tags .= '|promotie';
			}
		} elseif ( strtotime($to_date) !== false ) {
			// Eenmaal de 'vanaf'-datum gepasseerd is, wist WooCommerce dit automatisch!
			if ( strtotime($to_date) > strtotime('tomorrow midnight') ) {
				$tags .= '|promotie';
			}
		}
		return $tags;
	}

	function merge_bio_status( $preferences, $bio ) {
		if ( $bio === 'Ja' ) {
			$preferences .= '|biologisch';
		}
		return $preferences;
	}

	function only_lowest_term( $string ) {
		$parts = explode( '>', $string );
		return strtolower( $parts[count($parts)-1] );
	}

	function replace_commas_with_pipes( $string ) {
		$parts = explode( ', ', $string );
		return implode( '|', $parts );
	}
?>