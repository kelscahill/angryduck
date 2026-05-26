<?php
/**
 * File_Description
 *
 * @package RymeraWebCo\WPay
 */

use RymeraWebCo\WPay\Factories\Payment_Plan;
use RymeraWebCo\WPay\Helpers\WPay;

defined( 'ABSPATH' ) || exit;

/**
 * Available variables:
 *
 * @since 1.0.0
 * @var Payment_Plan[] $payment_plans
 * @var string[]       $payment_plans_style
 * @var string[]       $payment_plan_items_style
 * @var bool           $has_overdue_balance
 * @var string         $credit_limit_notice
 */
?>
<?php if ( empty( $payment_plans ) ) : ?>
    <p><?php esc_html_e( 'No payment plans found.', 'woocommerce-wholesale-payments' ); ?></p>
<?php else : ?>
    <?php if ( ! empty( $has_overdue_balance ) ) : ?>
        <div class="wpay-overdue-notice woocommerce-info">
            <p>
                <?php
                // Context: Classic checkout on_hold action — informational warning about order hold.
                // Intentionally different wording from My Account (WC.php) and blocks checkout (wpay-payment-method.js).
                esc_html_e( 'You have an outstanding overdue balance. Your order will be placed on hold until your overdue invoices are paid.', 'woocommerce-wholesale-payments' );
                ?>
            </p>
        </div>
    <?php endif; ?>
    <?php if ( ! empty( $credit_limit_notice ) ) : ?>
        <div class="wpay-credit-limit-notice woocommerce-info">
            <?php echo esc_html( $credit_limit_notice ); ?>
        </div>
    <?php endif; ?>
    <?php if ( WPay::get_api_mode() === 'test' ) : ?>
        <p>
            <em style="color: #bb5504;">
                <?php
                printf(
                    /* translators: %1$s = opening <strong> tag; %2$s = closing </strong> tag */
                    esc_html__( 'Payment method is in %1$sTEST MODE%2$s. No payment will be processed or any shipment fulfilled.', 'woocommerce-wholesale-payments' ),
                    '<strong>',
                    '</strong>'
                );
                ?>
            </em>
        </p>
    <?php endif; ?>
    <p>
        <em><?php echo esc_html( apply_filters( 'wpay_payment_fields_description', __( 'Payment plans available for this order.', 'woocommerce-wholesale-payments' ) ) ); ?></em>
    </p>
    <?php if ( 'yes' === WPay::get_overall_auto_charge_status( $payment_plans ) ) : ?>
        <p class="wpay-auto-charge-description">
            <em>
                <?php
                // phpcs:disable
                printf(
                    /* translators: %1$s = opening <strong> tag; %2$s = closing </strong> tag */
                    esc_html__( apply_filters( 'wpay_auto_charge_description', __( '%1$sAuto-charge%2$s means Stripe will charge your saved payment method (e.g. credit card, debit card, ACH) without requiring manual action from you for each payment.', 'woocommerce-wholesale-payments' ) ) ),
                    '<strong>',
                    '</strong>'
                );
                // phpcs:enable
                ?>
            </em>
        </p>
        <p class="wpay-auto-charge-requirements">
            <em>
                <?php
                esc_html_e( 'To use auto-charge, you must have at least one saved payment method. You can add it below.', 'woocommerce-wholesale-payments' )
                ?>
            </em>
        </p>
    <?php endif; ?>
    <?php
    $auto_charge_invoices = get_option( 'wpay_stripe_auto_charge_invoices', 'no' );
    $overall_auto_charge  = WPay::get_overall_auto_charge_status( $payment_plans );
    ?>
    <div id="wpay-auto-charge-help-classic" class="wpay-auto-charge-help woocommerce-info" style="display: <?php echo ( wc_string_to_bool( $auto_charge_invoices ) ? 'block' : 'none' ); ?>" data-overall-auto-charge="<?php echo esc_attr( wc_string_to_bool( $overall_auto_charge ) ? 'true' : 'false' ); ?>">
        <em>
            <?php
                esc_html_e( 'To use auto-charge, you must have at least one saved payment method. You can add it below.', 'woocommerce-wholesale-payments' )
            ?>
        </em>
    </div>
    <ul style="<?php echo esc_attr( implode( "\n", $payment_plans_style ) ); ?>">
        <?php foreach ( $payment_plans as $payment_plan ) : ?>
            <li style="<?php echo esc_attr( implode( "\n", $payment_plan_items_style ) ); ?>" <?php echo 'yes' === $payment_plan->apply_auto_charge ? 'data-auto-charge="' . esc_attr( $payment_plan->apply_auto_charge ) . '"' : ''; ?>>
                <input
                    id="wpay_plan-<?php echo esc_attr( $payment_plan->post->ID ); ?>"
                    class="input-radio <?php echo esc_attr( 'yes' === $payment_plan->apply_auto_charge ? 'wpay-auto-charge-plan' : '' ); ?>"
                    type="radio"
                    name="wpay_plan"
                    value="<?php echo esc_attr( $payment_plan->post->ID ); ?>"
                />
                <label for="wpay_plan-<?php echo esc_attr( $payment_plan->post->ID ); ?>" style="cursor: pointer;" class="wpay-payment-plan-label <?php echo esc_attr( 'yes' === WPay::get_auto_charge_status( $payment_plan->post->ID ) ? 'wpay-auto-charge-enabled' : 'wpay-auto-charge-disabled' ); ?>">
                    <?php echo wp_kses_post( nl2br( get_the_title( $payment_plan->post ) ) ); ?>
                    <?php if ( 'yes' === WPay::get_auto_charge_status( $payment_plan->post->ID ) ) : ?>
                        <em class="wpay-auto-charge-status" style="<?php echo esc_attr( implode( "\n", $payment_plan_charge_status_style ) ); ?>">
                            <?php esc_html_e( 'Auto Charge', 'woocommerce-wholesale-payments' ); ?>
                        </em>
                    <?php endif; ?>

                </label>
            </li>
        <?php endforeach; ?>
    </ul>
    <div id="wpay-payment-element" style="display:none; margin-top: 10px;"></div>
    <button type="button" id="wpay-save-method" class="button" style="display:none; margin-top: 10px;">
        <?php esc_html_e( 'Save payment method', 'woocommerce-wholesale-payments' ); ?>
    </button>
    <p id="wpay-save-result" style="display:none; margin-top: 8px;"></p>
<?php endif; ?>
