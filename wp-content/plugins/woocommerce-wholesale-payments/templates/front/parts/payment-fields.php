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
 */
?>
<?php if ( empty( $payment_plans ) ) : ?>
    <p><?php esc_html_e( 'No payment plans found.', 'woocommerce-wholesale-payments' ); ?></p>
<?php else : ?>
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
    <ul style="<?php echo esc_attr( implode( "\n", $payment_plans_style ) ); ?>">
        <?php foreach ( $payment_plans as $payment_plan ) : ?>
            <li style="<?php echo esc_attr( implode( "\n", $payment_plan_items_style ) ); ?>">
                <input
                    id="wpay_plan-<?php echo esc_attr( $payment_plan->post->ID ); ?>"
                    class="input-radio"
                    type="radio"
                    name="wpay_plan"
                    value="<?php echo esc_attr( $payment_plan->post->ID ); ?>"
                />
                <label for="wpay_plan-<?php echo esc_attr( $payment_plan->post->ID ); ?>" style="cursor: pointer;">
                    <?php echo wp_kses_post( nl2br( get_the_title( $payment_plan->post ) ) ); ?>
                </label>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
