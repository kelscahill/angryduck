<?php
/**
 * WooCommerce Wholesale Lead Capture - send email.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/woocommerce-wholesale-lead-capture-email.php.
 *
 * @version 1.17.4
 */
defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );?>

<?php echo wp_kses_post( $email->get_message() ); ?>

<?php
do_action( 'wwlc_email_footer', $email );
