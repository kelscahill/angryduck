<?php
/**
 * Pre-configured payment plans.
 *
 * @since   1.0.0
 * @package RymeraWebCo\WPay
 */

use RymeraWebCo\WPay\Helpers\Helper;

defined( 'ABSPATH' ) || exit;

return array(
    array(
        'id'          => Helper::generate_id(),
        'title'       => __( 'Net 30', 'woocommerce-wholesale-payments' ),
        'subTitle'    => __( 'Nothing upfront and the full amount in 30 days', 'woocommerce-wholesale-payments' ),
        'description' => __( "You can checkout your order now, and we'll bill you in 30 days.", 'woocommerce-wholesale-payments' ),
        'enabled'     => 'yes',
        'breakdown'   => array(
            array(
                'id'               => Helper::generate_id(),
                'day'              => 0,
                'day_format'       => 'day',
                'due'              => array(
                    'type'  => 'percentage',
                    'value' => 0,
                ),
                'payment_reminder' => array(
                    'enabled' => false,
                    'day'     => 1,
                ),
                'overdue_reminder' => array(
                    'enabled' => false,
                    'day'     => 1,
                ),
            ),
            array(
                'id'               => Helper::generate_id(),
                'day'              => 30,
                'day_format'       => 'day',
                'due'              => array(
                    'type'  => 'percentage',
                    'value' => 100,
                ),
                'payment_reminder' => array(
                    'enabled' => true,
                    'day'     => 1,
                ),
                'overdue_reminder' => array(
                    'enabled' => true,
                    'day'     => 1,
                ),
            ),
        ),
    ),
    array(
        'id'          => Helper::generate_id(),
        'title'       => __( '50% upfront', 'woocommerce-wholesale-payments' ),
        'subTitle'    => __( '50% upfront and the remainder due in 30 days', 'woocommerce-wholesale-payments' ),
        'description' => __( "Pay 50% upon checkout, and we'll bill you the remaining 50% in 30 days.", 'woocommerce-wholesale-payments' ),
        'enabled'     => 'yes',
        'breakdown'   => array(
            array(
                'id'               => Helper::generate_id(),
                'day'              => 0,
                'day_format'       => 'day',
                'due'              => array(
                    'type'  => 'percentage',
                    'value' => 50,
                ),
                'payment_reminder' => array(
                    'enabled' => false,
                    'day'     => 1,
                ),
                'overdue_reminder' => array(
                    'enabled' => false,
                    'day'     => 1,
                ),
            ),
            array(
                'id'               => Helper::generate_id(),
                'day'              => 30,
                'day_format'       => 'day',
                'due'              => array(
                    'type'  => 'percentage',
                    'value' => 50,
                ),
                'payment_reminder' => array(
                    'enabled' => true,
                    'day'     => 1,
                ),
                'overdue_reminder' => array(
                    'enabled' => true,
                    'day'     => 1,
                ),
            ),
        ),
    ),
    array(
        'id'          => Helper::generate_id(),
        'title'       => __( '3 equal payments', 'woocommerce-wholesale-payments' ),
        'subTitle'    => __( 'The total invoice divided into 3 equal payments over 60 days', 'woocommerce-wholesale-payments' ),
        'description' => __( "Pay 1/3 upon checkout, and we'll bill you the remaining 2/3 in 30 and 60 days.", 'woocommerce-wholesale-payments' ),
        'enabled'     => 'yes',
        'breakdown'   => array(
            array(
                'id'               => Helper::generate_id(),
                'day'              => 0,
                'day_format'       => 'day',
                'due'              => array(
                    'type'  => 'percentage',
                    'value' => 33,
                ),
                'payment_reminder' => array(
                    'enabled' => false,
                    'day'     => 1,
                ),
                'overdue_reminder' => array(
                    'enabled' => false,
                    'day'     => 1,
                ),
            ),
            array(
                'id'               => Helper::generate_id(),
                'day'              => 30,
                'day_format'       => 'day',
                'due'              => array(
                    'type'  => 'percentage',
                    'value' => 33,
                ),
                'payment_reminder' => array(
                    'enabled' => true,
                    'day'     => 1,
                ),
                'overdue_reminder' => array(
                    'enabled' => true,
                    'day'     => 1,
                ),
            ),
            array(
                'id'               => Helper::generate_id(),
                'day'              => 60,
                'day_format'       => 'day',
                'due'              => array(
                    'type'  => 'percentage',
                    'value' => 34,
                ),
                'payment_reminder' => array(
                    'enabled' => true,
                    'day'     => 1,
                ),
                'overdue_reminder' => array(
                    'enabled' => true,
                    'day'     => 1,
                ),
            ),
        ),
    ),
    array(
        'id'          => Helper::generate_id(),
        'title'       => __( '25% upfront', 'woocommerce-wholesale-payments' ),
        'subTitle'    => __( '25% upfront and the remainder due in 30 days', 'woocommerce-wholesale-payments' ),
        'description' => __( "Pay 25% upon checkout, and we'll bill you the remaining in 30 days.", 'woocommerce-wholesale-payments' ),
        'enabled'     => 'yes',
        'breakdown'   => array(
            array(
                'id'               => Helper::generate_id(),
                'day'              => 0,
                'day_format'       => 'day',
                'due'              => array(
                    'type'  => 'percentage',
                    'value' => 25,
                ),
                'payment_reminder' => array(
                    'enabled' => false,
                    'day'     => 1,
                ),
                'overdue_reminder' => array(
                    'enabled' => false,
                    'day'     => 1,
                ),
            ),
            array(
                'id'               => Helper::generate_id(),
                'day'              => 30,
                'day_format'       => 'day',
                'due'              => array(
                    'type'  => 'percentage',
                    'value' => 75,
                ),
                'payment_reminder' => array(
                    'enabled' => true,
                    'day'     => 1,
                ),
                'overdue_reminder' => array(
                    'enabled' => true,
                    'day'     => 1,
                ),
            ),
        ),
    ),
    array(
        'id'          => Helper::generate_id(),
        'title'       => __( 'Net 60', 'woocommerce-wholesale-payments' ),
        'subTitle'    => __( 'Nothing upfront and the full amount in 60 days', 'woocommerce-wholesale-payments' ),
        'description' => __( "You can checkout your order now, and we'll bill you in 60 days.", 'woocommerce-wholesale-payments' ),
        'enabled'     => 'yes',
        'breakdown'   => array(
            array(
                'id'               => Helper::generate_id(),
                'day'              => 0,
                'day_format'       => 'day',
                'due'              => array(
                    'type'  => 'percentage',
                    'value' => 0,
                ),
                'payment_reminder' => array(
                    'enabled' => false,
                    'day'     => 1,
                ),
                'overdue_reminder' => array(
                    'enabled' => false,
                    'day'     => 1,
                ),
            ),
            array(
                'id'               => Helper::generate_id(),
                'day'              => 60,
                'day_format'       => 'day',
                'due'              => array(
                    'type'  => 'percentage',
                    'value' => 100,
                ),
                'payment_reminder' => array(
                    'enabled' => true,
                    'day'     => 1,
                ),
                'overdue_reminder' => array(
                    'enabled' => true,
                    'day'     => 1,
                ),
            ),
        ),
    ),
    array(
        'id'          => Helper::generate_id(),
        'title'       => __( 'Net 90', 'woocommerce-wholesale-payments' ),
        'subTitle'    => __( 'Nothing upfront and the full amount in 90 days', 'woocommerce-wholesale-payments' ),
        'description' => __( "You can checkout your order now, and we'll bill you in 90 days.", 'woocommerce-wholesale-payments' ),
        'enabled'     => 'yes',
        'breakdown'   => array(
            array(
                'id'               => Helper::generate_id(),
                'day'              => 0,
                'day_format'       => 'day',
                'due'              => array(
                    'type'  => 'percentage',
                    'value' => 0,
                ),
                'payment_reminder' => array(
                    'enabled' => false,
                    'day'     => 1,
                ),
                'overdue_reminder' => array(
                    'enabled' => false,
                    'day'     => 1,
                ),
            ),
            array(
                'id'               => Helper::generate_id(),
                'day'              => 90,
                'day_format'       => 'day',
                'due'              => array(
                    'type'  => 'percentage',
                    'value' => 100,
                ),
                'payment_reminder' => array(
                    'enabled' => true,
                    'day'     => 1,
                ),
                'overdue_reminder' => array(
                    'enabled' => true,
                    'day'     => 1,
                ),
            ),
        ),
    ),
    array(
        'id'          => Helper::generate_id(),
        'title'       => __( '30 Day from EOM', 'woocommerce-wholesale-payments' ),
        'subTitle'    => __( 'The full amount due 30 days from end of current month.', 'woocommerce-wholesale-payments' ),
        'description' => __( "You can checkout your order now, and we'll bill you 30 days after the end of this month.", 'woocommerce-wholesale-payments' ),
        'enabled'     => 'yes',
        'breakdown'   => array(
            array(
                'id'               => Helper::generate_id(),
                'day'              => 0,
                'day_format'       => 'day',
                'due'              => array(
                    'type'  => 'percentage',
                    'value' => 0,
                ),
                'payment_reminder' => array(
                    'enabled' => false,
                    'day'     => 1,
                ),
                'overdue_reminder' => array(
                    'enabled' => false,
                    'day'     => 1,
                ),
            ),
            array(
                'id'               => Helper::generate_id(),
                'day'              => 'end_of_month_plus_30_days',
                'day_format'       => 'custom',
                'due'              => array(
                    'type'  => 'percentage',
                    'value' => 100,
                ),
                'payment_reminder' => array(
                    'enabled' => true,
                    'day'     => 1,
                ),
                'overdue_reminder' => array(
                    'enabled' => true,
                    'day'     => 1,
                ),
            ),
        ),
    ),
    array(
        'id'          => Helper::generate_id(),
        'title'       => __( 'Due 21st Next Month', 'woocommerce-wholesale-payments' ),
        'subTitle'    => __( 'The full amount due on 21st of next month', 'woocommerce-wholesale-payments' ),
        'description' => __( "You can checkout your order now, and we'll bill you on the 21st of next month.", 'woocommerce-wholesale-payments' ),
        'enabled'     => 'yes',
        'breakdown'   => array(
            array(
                'id'               => Helper::generate_id(),
                'day'              => 0,
                'day_format'       => 'day',
                'due'              => array(
                    'type'  => 'percentage',
                    'value' => 0,
                ),
                'payment_reminder' => array(
                    'enabled' => false,
                    'day'     => 1,
                ),
                'overdue_reminder' => array(
                    'enabled' => false,
                    'day'     => 1,
                ),
            ),
            array(
                'id'               => Helper::generate_id(),
                'day'              => 'on_21st_of_next_month',
                'day_format'       => 'timestamp',
                'due'              => array(
                    'type'  => 'percentage',
                    'value' => 100,
                ),
                'payment_reminder' => array(
                    'enabled' => true,
                    'day'     => 1,
                ),
                'overdue_reminder' => array(
                    'enabled' => true,
                    'day'     => 1,
                ),
            ),
        ),
    ),
);
