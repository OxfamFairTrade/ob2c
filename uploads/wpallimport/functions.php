<?php
	function stitch_countries( $land1 ) {
		$countries = '';
		if ( $land1 !== '' ) {
			$countries .= $land1;
		}
		return $countries;
	}

	function translate_tax( $percentage ) {
		$rate = 'standard-rate';
		if ( $percentage == '6' ) {
			$rate = 'reduced-rate';
		}
		return $rate;
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
		if ( $calc > 0 ) {
			$teller = floatval(str_replace(',', '.', $cp));
			if ( $teller > 0 and $noemer > 0 ) {
				$calc = $teller / $noemer;
				$eprice = "&euro; ".number_format( $calc, 2, ',', '.');
				if ( $unit == 'L' ) {
					$eprice .= " per liter";
				} else {
					$eprice .= " per kilogram";
				}
			}
		}
		return $eprice;
	}
?>