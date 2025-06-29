<?php
/**
 * Author: Rymera Web Co.
 *
 * @package RymeraWebCo\WPay\Helpers
 */

namespace RymeraWebCo\WPay\Helpers;

use RymeraWebCo\WPay\Factories\Payment_Plan;
use WP_Query;

/**
 * Payment_Plans class.
 *
 * @since 1.0.0
 */
class Payment_Plans {

    /**
     * Get payment plans.
     *
     * @param array $query_args Query args.
     *
     * @return WP_Query
     */
    public static function get_payment_plans( $query_args = array() ) {

        $defaults = array(
            'post_status' => 'publish',
            'orderby'     => 'date',
            'order'       => 'DESC',
        );

        $args = apply_filters( 'wpay_get_payment_plans', wp_parse_args( $query_args, $defaults ) );

        $args['post_type'] = Payment_Plan::POST_TYPE;

        $query = new WP_Query( $args );

        if ( ! empty( $query->posts ) && ( empty( $args['fields'] ) || 'ids' !== $args['fields'] ) ) {
            $query->posts = array_map( array( Payment_Plan::class, 'get_instance' ), $query->posts );
        }

        return $query;
    }
}
