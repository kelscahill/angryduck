<?php
/**
 * WooCommerce Wholesale Lead Capture - plain text.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/woocommerce-wholesale-lead-capture-email.php.
 *
 * @version 1.17.4
 */

defined( 'ABSPATH' ) || exit;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo esc_html( wp_strip_all_tags( wptexturize( preg_replace( '/\<br(\s*)?\/?\>/i', "\n", $email->get_message() ) ) ) ) . "\n\n";
if ( ! apply_filters( 'wwlc_use_woocommerce_email_footer', false ) ) {
    esc_html_e( 'Powered by', 'woocommerce-wholesale-lead-capture' );
    echo ' Wholesale Suite ';
    echo esc_url_raw( 'https://wholesalesuiteplugin.com/powered-by/?utm_source=wwlc&utm_medium=email&utm_campaign=WWLCPoweredByEmailLink' );
} else {
    echo wp_kses_post( apply_filters( 'wwlc_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
}
