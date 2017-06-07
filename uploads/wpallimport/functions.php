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
?>