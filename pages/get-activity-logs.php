<?php
	if ( ! defined('ABSPATH') ) exit;
?>

<div class="wrap oxfam-admin-styling">
	<h1>Activiteitenlogs</h1>
	<?php
		// Let op dat de logs niet te snel groeien en de pagina vertragen!
		$file_path = dirname( ABSPATH, 1 ) . '/activity.log';
		
		if ( ( $handle = fopen( $file_path, 'r' ) ) !== false ) {
			echo '<table style="width: 100%;">';
			while ( $line = fgetcsv( $handle, 0, "\t" ) ) {
				// Reset variabele
				$row = '';
				foreach ( $line as $column ) {
					$row .= '<td>'.$column.'</td>';
				}
				echo '<tr>'.$row.'</tr>';
			}
			fclose( $handle );
			echo '</table>';
		} else {
			echo '<p><i>Nog geen logs beschikbaar.</i></p>';
		}
	?>
</div>