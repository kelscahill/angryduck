<?php
/**
 * Wholesale Role Automation Currency Helper.
 *
 * Stateless helper that normalizes per-order totals to the store base
 * currency. Single place that knows about WCML; all other modules stay
 * multi-currency-agnostic.
 *
 * @package    WooCommerceWholeSalePricesPremium
 * @subpackage WholesaleRoleAutomation\Helpers
 * @since      2.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WWPP_Wholesale_Role_Automation_Currency_Helper.
 *
 * Resolves a `WC_Order` to its base-currency total.
 *
 * @since 2.1.0
 */
class WWPP_Wholesale_Role_Automation_Currency_Helper {

    /**
     * Return the order total expressed in the store base currency.
     *
     * Same-currency fast path returns `$order->get_total()` directly. When
     * the order is in a non-base currency and WCML is active, converts via
     * `unconvert_price_amount()` on WCML's lazily-initialised multi-currency
     * `prices` sub-object — accessed through the `get_multi_currency()`
     * accessor so the property is initialised in cron / Action Scheduler /
     * WP-CLI contexts where direct property access otherwise short-circuits.
     * Conversion uses live WCML rates, not order-time rates; threshold
     * comparisons may drift if rates change between order placement and
     * evaluation. When the conversion returns `0` for a non-zero order total
     * (currency removed from WCML, stale rate cache, unconfigured currency)
     * the helper falls through to the warning-log fallback rather than
     * silently zero-ing the customer's spend. When no conversion path is
     * available at all, logs a warning via `wc_get_logger()` and returns
     * the face-value total — documented degradation for non-base /
     * no-WCML stores.
     *
     * Intentionally does NOT use the WCML display-side filter (the
     * base -> active currency direction), which is the wrong direction
     * for this conversion.
     *
     * @since 2.1.0
     *
     * @param WC_Order $order The order to inspect.
     *
     * @return float The order total in store base currency.
     */
    public function order_total_in_base( WC_Order $order ): float {
        $order_currency = $order->get_currency();
        $base_currency  = get_woocommerce_currency();

        if ( $order_currency === $base_currency ) {
            return (float) $order->get_total();
        }

        if ( class_exists( 'woocommerce_wpml' ) ) {
            global $woocommerce_wpml;
            if ( method_exists( $woocommerce_wpml, 'get_multi_currency' ) ) {
                $multi_currency = $woocommerce_wpml->get_multi_currency();
                if ( $multi_currency && isset( $multi_currency->prices ) ) {
                    $converted = (float) $multi_currency->prices->unconvert_price_amount(
                        (float) $order->get_total(),
                        $order_currency
                    );
                    if ( $converted > 0.0 ) {
                        return $converted;
                    }
                }
            }
        }

        wc_get_logger()->warning(
            sprintf(
                /* translators: 1: Order ID. 2: Order currency code. 3: Store base currency code. 4: Order total at face value. */
                __(
                    'Currency_Helper: order #%1$d (currency %2$s) has no WCML conversion path to base %3$s. Using face-value total %4$s as base; threshold comparisons may be off.',
                    'woocommerce-wholesale-prices-premium'
                ),
                $order->get_id(),
                $order_currency,
                $base_currency,
                $order->get_total()
            ),
            array( 'source' => 'wwpp-wholesale-role-automation' )
        );

        return (float) $order->get_total();
    }
}
