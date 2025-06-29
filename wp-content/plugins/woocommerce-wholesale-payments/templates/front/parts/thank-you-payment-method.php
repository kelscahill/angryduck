<?php
/**
 * Renders the payment method on the thankyou page.
 *
 * @package RymeraWebCo\WPay
 */

use RymeraWebCo\WPay\Helpers\WPay;

defined( 'ABSPATH' ) || exit;

/**
 * Available variables:
 *
 * @var WC_Order $order
 */

$payment_plan = $order->get_meta( '_wpay_plan' );
$payment_plan = ! empty( $payment_plan ) ? json_decode( $payment_plan ) : false;
?>
<?php if ( empty( $payment_plan ) ) : ?>
    <p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-received wpay-payment-plan">
        <?php esc_html_e( 'Unknown payment plan.', 'woocommerce-wholesale-payments' ); ?>
    </p>
<?php else : ?>
    <ul class='woocommerce-order-overview woocommerce-thankyou-order-details order_details wpay-payment-plan'>
        <li>
            <?php esc_html_e( 'Payment method:', 'woocommerce-wholesale-payments' ); ?>
            <strong>
                <?php
                /* translators: %s = payment plan title */
                echo esc_html( sprintf( apply_filters( 'wpay_thank_you_message_payment_method_title', __( 'Wholesale Payments: %1$s', 'woocommerce-wholesale-payments' ) ), $payment_plan->title ) );
                ?>
            </strong>
        </li>
    </ul>
<?php endif; ?>
