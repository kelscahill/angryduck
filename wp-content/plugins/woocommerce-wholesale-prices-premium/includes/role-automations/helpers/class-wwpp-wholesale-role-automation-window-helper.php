<?php
/**
 * Wholesale Role Automation Window Helper.
 *
 * Stateless helper that centralizes calendar / timezone math used by the
 * Metric Provider. Resolves a window descriptor (`lifetime`, `rolling_days`,
 * `fixed_month`, `fixed_year`) into concrete from/to timestamps in site
 * timezone via `wp_timezone()`.
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
 * Class WWPP_Wholesale_Role_Automation_Window_Helper.
 *
 * Single source of truth for window math.
 *
 * @since 2.1.0
 */
class WWPP_Wholesale_Role_Automation_Window_Helper {

    /**
     * Resolve a window descriptor to a from/to timestamp pair.
     *
     * `lifetime` returns `[ 'from_ts' => 0, 'to_ts' => time() ]`.
     * `rolling_days` returns `[ now - N*DAY, now ]`, where `now` is the
     * current second — i.e. the window ends at call time, not at the end
     * of the current calendar day.
     * `fixed_month` and `fixed_year` return the current calendar period in
     * site timezone via `wp_timezone()`. The `to_ts` for fixed periods is
     * inclusive of the last second of the period (`23:59:59` of the last
     * day) so it pairs with `<=` comparisons in `wc_get_orders` date queries.
     *
     * @since 2.1.0
     *
     * @param string   $window_type One of `lifetime`, `rolling_days`, `fixed_month`, `fixed_year`.
     * @param int|null $window_days Number of days for `rolling_days`; ignored for other window types.
     *
     * @return array{from_ts: int, to_ts: int} From/to Unix timestamps; both keys always present.
     * @throws InvalidArgumentException When `$window_type` is unknown, or when `rolling_days` is requested without a positive `$window_days`.
     */
    public function bounds_for( string $window_type, ?int $window_days = null ): array {
        switch ( $window_type ) {
            case 'lifetime':
                return array(
                    'from_ts' => 0,
                    'to_ts'   => time(),
                );

            case 'rolling_days':
                if ( null === $window_days || $window_days <= 0 ) {
                    throw new InvalidArgumentException( 'rolling_days window requires a positive window_days value' );
                }
                $now = time();
                return array(
                    'from_ts' => $now - ( $window_days * DAY_IN_SECONDS ),
                    'to_ts'   => $now,
                );

            case 'fixed_month':
                $tz   = wp_timezone();
                $now  = new DateTimeImmutable( 'now', $tz );
                $from = $now->modify( 'first day of this month' )->setTime( 0, 0, 0 );
                $to   = $now->modify( 'last day of this month' )->setTime( 23, 59, 59 );
                return array(
                    'from_ts' => $from->getTimestamp(),
                    'to_ts'   => $to->getTimestamp(),
                );

            case 'fixed_year':
                $tz   = wp_timezone();
                $now  = new DateTimeImmutable( 'now', $tz );
                $from = $now->modify( 'first day of January this year' )->setTime( 0, 0, 0 );
                $to   = $now->modify( 'last day of December this year' )->setTime( 23, 59, 59 );
                return array(
                    'from_ts' => $from->getTimestamp(),
                    'to_ts'   => $to->getTimestamp(),
                );

            default:
                throw new InvalidArgumentException( 'Unknown window_type: ' . esc_html( $window_type ) );
        }
    }
}
