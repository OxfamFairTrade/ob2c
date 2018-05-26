<?php
/**
 * Customer new account email
 *
 * @see 		https://docs.woocommerce.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates/Emails
 * @version 	1.6.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$customer = get_user_by( 'login', $user_login );
$email_heading = __( 'Titel in de header van de welkomstmail', 'oxfam-webshop' );

?>

<?php do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php if ( 'yes' === get_the_author_meta( 'blog_'.get_current_blog_id().'_is_b2b_customer', $customer->ID ) ) : ?>

	<p>
		<?php
			if ( '' !== $customer->first_name and '' !== $customer->last_name ) {
				$name = $customer->first_name.' '.$customer->last_name;
			} else {
				$name = 'klant';
			}
			printf( __( 'Begroeting van de bedrijfsverantwoordelijke (%s)', 'oxfam-webshop' ), esc_html( $name ) );
		?>
	</p>

	<p><?php printf( __( 'Eerste alinea in de uitnodingsmail aan B2B-gebruikers, inclusief naam van webshop (%s).', 'oxfam-webshop' ), get_company_name() ); ?></p>

	<ul>
		<?php
			$author_metas = array(
				'billing_company' => 'Bedrijf of vereniging',
				'billing_vat' => 'BTW-nummer',
				'billing_address_1' => 'Factuuradres',
				'billing_postcode' => 'Postcode',
				'billing_city' => 'Gemeente',
			);
			
			foreach ( $author_metas as $key => $label ) {
				if ( '' !== get_the_author_meta( $key, $customer->ID ) ) {
					if ( $key === 'billing_city' ) {
						echo '<li>Gemeente: '.get_the_author_meta( 'billing_postcode', $customer->ID ).' '.get_the_author_meta( $key, $customer->ID ).'</li>';
					} elseif ( $key !== 'billing_postcode' ) {
						echo '<li>'.$label.': '.get_the_author_meta( $key, $customer->ID ).'</li>';
					}
				}
			}
		?>
	</ul>

	<p><?php printf( __( 'Tweede alinea in de uitnodingsmail aan B2B-gebruikers, inclusief gebruikersnaam (%s).', 'oxfam-webshop' ), '<strong>&laquo;&nbsp;' . esc_html( $user_login ) . '&nbsp;&raquo;</strong>' ); ?></p>

	<p style="text-align: center;">
		<a class="link" href="<?php echo esc_url( add_query_arg( array( 'key' => $reset_key, 'login' => rawurlencode( $user_login ) ), wc_get_endpoint_url( 'lost-password', '', wc_get_page_permalink( 'myaccount' ) ) ) ); ?>">
			<b><?php _e( 'Speciale wachtwoordresetlink', 'oxfam-webshop' ); ?> &raquo;</b>
		</a>
	</p>

	<p>
		<?php
			_e( 'Derde alinea in de uitnodingsmail aan B2B-gebruikers.', 'oxfam-webshop' );

			$b2b_coupon_id = intval( get_the_author_meta( 'blog_'.get_current_blog_id().'_has_b2b_coupon', $customer->ID ) );
			if ( $b2b_coupon_id > 0 ) {
				$b2b_coupon = get_post($b2b_coupon_id);
				echo ' '.sprintf( __( 'Uitleg over algemeen kortingstarief, inclusief percentage (%1$s) en naam van webshop (%2$s).', 'oxfam-webshop' ), $b2b_coupon->coupon_amount.'%', get_company_name() );
			}
		?>
	</p>

	<p><?php _e( 'Vierde alinea in de uitnodingsmail aan B2B-gebruikers.', 'oxfam-webshop' ); ?></p>

	<p><?php _e( 'Vijfde alinea in de uitnodingsmail aan B2B-gebruikers.', 'oxfam-webshop' ); ?></p>

	<p><?php _e( 'Zesde alinea in de uitnodingsmail aan B2B-gebruikers.', 'oxfam-webshop' ); ?></p>

<?php else : ?>	

	<p><?php printf( __( 'Eerste alinea in de welkomstmail aan nieuwe gebruikers, inclusief naam van de webshop (%1$s) en vetgedrukte gebruikersnaam (%2$s).', 'oxfam-webshop' ), esc_html( $blogname ), '<strong>' . esc_html( $user_login ) . '</strong>' ); ?></p>

	<p><?php printf( __( 'Uitleg over de \'Mijn account\'-pagina, inclusief URL in de webshop waar de gebruiker zich registreerde (%s).', 'oxfam-webshop' ), esc_url( wc_get_page_permalink( 'myaccount' ) ) ); ?></p>

<?php endif; ?>

<p><?php printf( __( 'Uitsmijter van het mailbericht bij nieuwe accounts.', 'oxfam-webshop' ) ); ?></p>

<p><?php printf( __( 'Ondertekening van het mailbericht bij nieuwe accounts.', 'oxfam-webshop' ) ); ?></p>

<?php do_action( 'woocommerce_email_footer', $email );