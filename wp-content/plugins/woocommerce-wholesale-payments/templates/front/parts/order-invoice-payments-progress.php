<?php
/**
 * Renders the Stripe invoice payments progress on customers order details page.
 *
 * @package RymeraWebCo\WPay
 */

use RymeraWebCo\WPay\Helpers\WPay;

defined( 'ABSPATH' ) || exit;

/**
 * Available variables:
 *
 * @var WC_Order $order        Order object.
 * @var array    $invoice      Invoice data.
 * @var string   $payment_plan Payment plan data (in JSON string).
 */

[
    'amounts_due'        => $amounts_due,
    'hosted_invoice_url' => $hosted_invoice_url,
] = wp_parse_args(
    $invoice,
    array(
        'amounts_due'        => array(),
        'hosted_invoice_url' => '',
    )
);

$payment_linked = false;
$is_deferred    = ! empty( $amounts_due ) && isset( $amounts_due[0]['status'] ) && 'deferred' === $amounts_due[0]['status'];
?>
<h2 class="woocommerce-column__title"><?php esc_html_e( 'Wholesale Payments', 'woocommerce-wholesale-payments' ); ?></h2>
<?php if ( $is_deferred ) : ?>
    <p><?php esc_html_e( 'Payment plan schedule is pending — installments will begin when this order is completed.', 'woocommerce-wholesale-payments' ); ?></p>
<?php elseif ( empty( $amounts_due ) ) : ?>
    <p><?php esc_html_e( 'No invoice payment plan associated.' ); ?></p>
<?php else : ?>
    <table class="wholesale-payments-progress">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Amount Due', 'woocommerce-wholesale-payments' ); ?></th>
                <th><?php esc_html_e( 'Due Date', 'woocommerce-wholesale-payments' ); ?></th>
                <th><?php esc_html_e( 'Status', 'woocommerce-wholesale-payments' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $amounts_due as $amount ) : ?>
                <tr>
                    <td class="amount-due"><?php echo wp_kses_post( wc_price( $amount['amount'] / 100 ) ); ?></td>
                    <td class="due-date"><?php echo esc_html( date_i18n( wc_date_format(), $amount['due_date'] ) ); ?></td>
                    <td class="status">
                        <?php if ( ! $payment_linked && ( 'past_due' === $amount['status'] || 'open' === $amount['status'] ) ) : ?>
                            <?php $payment_linked = true; ?>
                            <a
                                href="<?php echo esc_url( $hosted_invoice_url ); ?>" target="_blank"
                                title="<?php esc_attr_e( 'Pay Now', 'woocommerce-wholesale-payments' ); ?>"
                            >
                                <?php echo esc_html( WPay::get_human_readable_invoice_status( $amount['status'] ) ); ?>
                            </a>
                        <?php else : ?>
                            <?php echo esc_html( WPay::get_human_readable_invoice_status( $amount['status'] ) ); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
