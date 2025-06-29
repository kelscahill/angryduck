<?php
/**
 * Customer Wholesale Payment Nearly Due Email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce-wholesale-payments/emails/payment-nearly-due.php.
 *
 * HOWEVER, on occasion WooCommerce Wholesale Payments will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce Wholesale Payments\Templates\Emails
 * @version 1.0.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RymeraWebCo\WPay\Integrations\WPay_Emails;

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

<?php
/**
 * Executes the e-mail header.
 *
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<div id="body_content_inner">
    <p class="wpay-text">
        <?php
        echo esc_html(
            sprintf(
                /* translators: %1$s: Store name, %2$s: Payment date */
                __( 'A payment to %1$s is due on %2$s', 'woocommerce-wholesale-payments' ),
                $site_title,
                $payment_date
            )
        );
        ?>
    </p>

    <p class="wpay-text">
        <?php esc_html_e( 'Please ensure your payment is processed before the due date to maintain uninterrupted access to your account.', 'woocommerce-wholesale-payments' ); ?>
    </p>

    <?php
    // Include additional content if available.
    if ( $additional_content ) {
        echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
    }
    ?>
    <div class="wpay-cta">
        <p>
            <a href="<?php echo esc_url( $invoice_url ); ?>" class="wpay-cta-button">
                <?php esc_html_e( 'Pay Invoice Now', 'woocommerce-wholesale-payments' ); ?>
                <span class="wpay-cta-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                    </svg>
                </span>
            </a>
        </p>
        <p class="wpay-cta-note">
            <?php esc_html_e( 'Manual payment is required, click to view invoice details & payment options.', 'woocommerce-wholesale-payments' ); ?>
        </p>
    </div>
</div>

<div id="template_footer">
    <p><?php esc_html_e( 'This is an automated message, please do not reply directly to this email.', 'woocommerce-wholesale-payments' ); ?></p>

    <div class="wpay-powered-by">
        <div class="wpay-powered-by-content">
            <span class="wpay-powered-by-text">
                <?php esc_html_e( 'Powered by', 'woocommerce-wholesale-payments' ); ?>
            </span>
            <a href="<?php echo esc_url( WPay_Emails::get_utm_url( 'payment-nearly-due-template' ) ); ?>">
                <img
                    src="<?php echo esc_url( plugin_dir_url( WPAY_PLUGIN_FILE ) . 'static/images/logo.svg' ); ?>"
                    alt="Wholesale Suite Logo"
                    class="wpay-powered-by-logo"
                />
            </a>
        </div>
    </div>
</div>

<?php
/**
 * Executes the email footer.
 *
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );
?>
<style>
    <?php echo wp_kses_post( WPay_Emails::get_email_styles( $is_email_preview ?? false ) ); ?>
</style>
