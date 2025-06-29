<?php
/**
 * Customer Wholesale Payment Due Today Email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce-wholesale-payments/emails/plain/payment-due-today.php.
 *
 * HOWEVER, on occasion WooCommerce Wholesale Payments will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce Wholesale Payments\Templates\Emails
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/*
 * @var WC_Order $order
 * @var string $email_heading
 * @var string $additional_content
 * @var bool $sent_to_admin
 * @var bool $plain_text
 * @var WC_Email $email
 * @var string $site_title
 * @var string $payment_date
 * @var string $payment_amount
 */
?>

<?php echo esc_html( $email_heading ); ?>

<?php
echo esc_html(
    sprintf(
    /* translators: %1$s: Store name, %2$s: Payment date */
        __( 'A payment to %1$s is overdue by %2$s days', 'woocommerce-wholesale-payments' ),
        $site_title,
        $days_overdue
    )
);
?>

<?php
esc_html_e(
    'Please ensure your payment is processed today to maintain uninterrupted access to your account.',
    'woocommerce-wholesale-payments'
);
?>

<?php
// Include additional content if available.
if ( $additional_content ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}
?>

<?php
esc_html_e(
    'Manual payment is required, click to view invoice details & payment options.',
    'woocommerce-wholesale-payments'
);
?>

<?php echo esc_url( $invoice_url ); ?>

<?php
esc_html_e(
    'This is an automated message, please do not reply directly to this email.',
    'woocommerce-wholesale-payments'
);
?>

<?php
esc_html_e( 'Powered by Wholesale Suite', 'woocommerce-wholesale-payments' );
