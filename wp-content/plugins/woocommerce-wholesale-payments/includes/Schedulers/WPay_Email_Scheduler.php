<?php
/**
 * WPay Email Scheduler.
 *
 * @package RymeraWebCo\WPay\Schedulers
 */

namespace RymeraWebCo\WPay\Schedulers;

use RymeraWebCo\WPay\Abstracts\Abstract_Class;
use RymeraWebCo\WPay\Integrations\WPay_Emails;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WPay_Email_Scheduler class.
 *
 * @since 1.0.4
 */
class WPay_Email_Scheduler extends Abstract_Class {

    /**
     * Hook group for scheduled actions.
     *
     * @since 1.0.4
     * @var string
     */
    const HOOK_GROUP = 'wpay_email_reminders';

    /**
     * Run the scheduler.
     *
     * @since 1.0.4
     * @return void
     */
    public function run() {
        // Schedule daily check for payment reminders.
        add_action( 'init', array( $this, 'schedule_daily_check' ) );

        // Hook the actual email sending actions.
        add_action( 'wpay_check_payment_reminders', array( $this, 'check_payment_reminders' ) );
        add_action( 'wpay_send_nearly_due_email', array( $this, 'send_nearly_due_email' ), 10, 2 );
        add_action( 'wpay_send_due_today_email', array( $this, 'send_due_today_email' ), 10, 2 );
        add_action( 'wpay_send_overdue_email', array( $this, 'send_overdue_email' ), 10, 2 );
    }

    /**
     * Schedule the daily check for payment reminders.
     *
     * @since 1.0.4
     * @return void
     */
    public function schedule_daily_check() {
        if ( ! as_has_scheduled_action( 'wpay_check_payment_reminders', array(), self::HOOK_GROUP ) ) {
            as_schedule_recurring_action(
                strtotime( 'tomorrow 9:00 AM' ),
                DAY_IN_SECONDS,
                'wpay_check_payment_reminders',
                array(),
                self::HOOK_GROUP
            );
        }
    }

    /**
     * Check for payment reminders and schedule individual emails.
     *
     * @since 1.0.4
     * @return void
     */
    public function check_payment_reminders() {
        if ( ! WPay_Emails::is_email_reminders_enabled() ) {
            return;
        }

        // Get orders with wholesale payment method.
        $orders = $this->get_wholesale_payment_orders();

        foreach ( $orders as $order ) {
            $this->schedule_order_reminders( $order );
        }
    }

    /**
     * Get orders with wholesale payment method that need reminders.
     *
     * @since 1.0.4
     * @return array
     */
    private function get_wholesale_payment_orders() {
        $args = array(
            'limit'          => -1,
            'status'         => array( 'processing', 'on-hold' ),
            'payment_method' => 'wpay',
            'meta_query'     => array(
                array(
                    'key'     => '_wpay_payment_due_date',
                    'compare' => 'EXISTS',
                ),
            ),
        );

        return wc_get_orders( $args );
    }

    /**
     * Schedule reminders for a specific order.
     *
     * @since 1.0.4
     * @param \WC_Order $order The order object.
     * @return void
     */
    private function schedule_order_reminders( $order ) {
        $due_date = $order->get_meta( '_wpay_payment_due_date' );
        $amount   = $order->get_meta( '_wpay_payment_amount' );

        if ( ! $due_date || ! $amount ) {
            return;
        }

        $due_timestamp = strtotime( $due_date );
        $today         = strtotime( 'today' );
        $order_id      = $order->get_id();

        // Schedule nearly due email.
        $days_before = (int) get_option( WPay_Emails::DAYS_BEFORE_PAYMENT_DUE_OPTION, 7 );
        if ( $days_before > 0 ) {
            $nearly_due_timestamp = $due_timestamp - ( $days_before * DAY_IN_SECONDS );

            if ( $nearly_due_timestamp >= $today ) {
                self::schedule_email_if_not_exists(
                    'wpay_send_nearly_due_email',
                    $nearly_due_timestamp,
                    array(
                        $order_id,
                        array(
                            'date'   => $due_date,
                            'amount' => $amount,
                        ),
                    )
                );
            }
        }

        // Schedule due today email.
        if ( WPay_Emails::is_payment_due_today_reminder_enabled() && $due_timestamp >= $today ) {
            self::schedule_email_if_not_exists(
                'wpay_send_due_today_email',
                $due_timestamp,
                array( $order_id, array( 'amount' => $amount ) )
            );
        }

        // Schedule overdue emails.
        $days_after = (int) get_option( WPay_Emails::DAYS_AFTER_PAYMENT_DUE_OPTION, 3 );
        if ( $days_after > 0 ) {
            for ( $i = 1; $i <= $days_after; $i++ ) {
                $overdue_timestamp = $due_timestamp + ( $i * DAY_IN_SECONDS );

                self::schedule_email_if_not_exists(
                    'wpay_send_overdue_email',
                    $overdue_timestamp,
                    array(
                        $order_id,
                        array(
                            'date'         => $due_date,
                            'amount'       => $amount,
                            'days_overdue' => $i,
                        ),
                    )
                );
            }
        }
    }

    /**
     * Send nearly due email.
     *
     * @since 1.0.4
     * @param int   $order_id The order ID.
     * @param array $payment_data The payment data.
     * @return void
     */
    public function send_nearly_due_email( $order_id, $payment_data ) {
        do_action( 'wpay_payment_nearly_due_notification', $order_id, $payment_data );
    }

    /**
     * Send due today email.
     *
     * @since 1.0.4
     * @param int   $order_id The order ID.
     * @param array $payment_data The payment data.
     * @return void
     */
    public function send_due_today_email( $order_id, $payment_data ) {
        do_action( 'wpay_payment_due_today_notification', $order_id, $payment_data );
    }

    /**
     * Send overdue email.
     *
     * @since 1.0.4
     * @param int   $order_id The order ID.
     * @param array $payment_data The payment data.
     * @return void
     */
    public function send_overdue_email( $order_id, $payment_data ) {
        do_action( 'wpay_payment_overdue_notification', $order_id, $payment_data );
    }

    /**
     * Clear scheduled actions for an order.
     *
     * @since 1.0.4
     * @param int $order_id The order ID.
     * @return void
     */
    public static function clear_order_reminders( $order_id ) {
        $hooks = array(
            'wpay_send_nearly_due_email',
            'wpay_send_due_today_email',
            'wpay_send_overdue_email',
        );

        foreach ( $hooks as $hook ) {
            as_unschedule_all_actions( $hook, array( $order_id ), self::HOOK_GROUP );
        }
    }

    /**
     * Reschedule reminders for an order when payment details change.
     *
     * @since 1.0.4
     * @param int $order_id The order ID.
     * @return void
     */
    public static function reschedule_order_reminders( $order_id ) {
        self::clear_order_reminders( $order_id );

        $order = wc_get_order( $order_id );
        if ( $order ) {
            $scheduler = new self();
            $scheduler->schedule_order_reminders( $order );
        }
    }

    /**
     * Schedule reminders for an order immediately upon creation.
     *
     * @since 1.0.4
     * @param int                                      $order_id The order ID.
     * @param \RymeraWebCo\WPay\Factories\Payment_Plan $payment_plan The payment plan.
     * @return void
     */
    public static function schedule_order_reminders_on_creation( $order_id, $payment_plan ) {
        if ( ! WPay_Emails::is_email_reminders_enabled() ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        // Extract payment schedule from payment plan breakdown.
        $breakdown = $payment_plan->breakdown ?? array();

        foreach ( $breakdown as $i => $item ) {
            // Calculate due date for this payment.
            $due_date = self::calculate_payment_due_date( $item );
            if ( ! $due_date ) {
                continue;
            }

            // Store payment info in order meta for this payment.
            $payment_meta_key = "_wpay_payment_{$i}";
            $order->update_meta_data( $payment_meta_key . '_due_date', $due_date );

            // Calculate amount.
            $order_total = (float) $order->get_total( 'edit' );
            $amount      = self::calculate_payment_amount( $item, $order_total, $breakdown, $i );
            $order->update_meta_data( $payment_meta_key . '_amount', $amount );

            $order->save_meta_data();

            // Schedule reminders for this specific payment.
            self::schedule_payment_reminders( $order_id, $i, $due_date, $amount );
        }
    }

    /**
     * Calculate due date for a payment item.
     *
     * @since 1.0.4
     * @param array $item Payment plan breakdown item.
     * @return string|null Due date in Y-m-d format or null if invalid.
     */
    private static function calculate_payment_due_date( $item ) {
        switch ( $item['day_format'] ) {
            case 'custom':
            case 'timestamp':
                $days = \RymeraWebCo\WPay\Helpers\WPay::parse_day( $item['day'] );
                if ( null === $days ) {
                    return null;
                }

                if ( 'timestamp' === $item['day_format'] ) {
                    return gmdate( 'Y-m-d', $days );
                } else {
                    return gmdate( 'Y-m-d', strtotime( "+{$days} days" ) );
                }

            default:
                $days = absint( $item['day'] );
                return gmdate( 'Y-m-d', strtotime( "+{$days} days" ) );
        }
    }

    /**
     * Calculate payment amount for a breakdown item.
     *
     * @since 1.0.4
     * @param array $item Current payment item.
     * @param float $order_total Total order amount.
     * @param array $breakdown Full payment breakdown.
     * @param int   $current_index Current item index.
     * @return float Payment amount.
     */
    private static function calculate_payment_amount( $item, $order_total, $breakdown, $current_index ) {
        $order_total_in_cents = absint( $order_total * 100 );

        // Handle percentage vs fixed amounts (copied from main payment processing logic).
        $fixed_items      = array();
        $percentage_items = false;

        foreach ( $breakdown as $breakdown_item ) {
            if ( 'fixed' === $breakdown_item['due']['type'] ) {
                $fixed_items[] = $breakdown_item;
            } elseif ( ! $percentage_items ) {
                $percentage_items = true;
            }
        }

        $percentage_base_total = 0;
        if ( ! empty( $fixed_items ) && $percentage_items ) {
            $percentage_base_total = $order_total_in_cents;
            foreach ( $fixed_items as $fixed_item ) {
                $value                  = absint( $fixed_item['due']['value'] * 100 );
                $percentage_base_total -= $value;
            }
        }

        // Calculate amount for current item.
        if ( 'percentage' === $item['due']['type'] ) {
            if ( count( $breakdown ) - 1 === $current_index ) {
                // Last payment gets remainder.
                $sum_previous = 0;
                for ( $i = 0; $i < $current_index; $i++ ) {
                    $prev_item = $breakdown[ $i ];
                    if ( 'percentage' === $prev_item['due']['type'] ) {
                        if ( $percentage_base_total ) {
                            $sum_previous += absint( $percentage_base_total * $prev_item['due']['value'] / 100 );
                        } else {
                            $sum_previous += absint( $order_total_in_cents * $prev_item['due']['value'] / 100 );
                        }
                    } else {
                        $sum_previous += absint( $prev_item['due']['value'] * 100 );
                    }
                }
                $amount = $order_total_in_cents - $sum_previous;
            } elseif ( $percentage_base_total ) {
                $amount = absint( $percentage_base_total * $item['due']['value'] / 100 );
            } else {
                $amount = absint( $order_total_in_cents * $item['due']['value'] / 100 );
            }
        } else {
            $amount = absint( $item['due']['value'] * 100 );
        }

        return $amount / 100; // Convert back to dollars.
    }

    /**
     * Schedule reminders for a specific payment.
     *
     * @since 1.0.4
     * @param int    $order_id The order ID.
     * @param int    $payment_index Payment index.
     * @param string $due_date Due date in Y-m-d format.
     * @param float  $amount Payment amount.
     * @return void
     */
    private static function schedule_payment_reminders( $order_id, $payment_index, $due_date, $amount ) {
        $due_timestamp = strtotime( $due_date );
        $today         = strtotime( 'today' );

        $payment_data = array(
            'date'   => $due_date,
            'amount' => $amount,
            'index'  => $payment_index,
        );

        // Schedule nearly due email.
        $days_before = (int) get_option( WPay_Emails::DAYS_BEFORE_PAYMENT_DUE_OPTION, 7 );
        if ( $days_before > 0 ) {
            $nearly_due_timestamp = $due_timestamp - ( $days_before * DAY_IN_SECONDS );

            if ( $nearly_due_timestamp >= $today ) {
                self::schedule_email_if_not_exists(
                    'wpay_send_nearly_due_email',
                    $nearly_due_timestamp,
                    array( $order_id, $payment_data ),
                    "order_{$order_id}_payment_{$payment_index}_nearly_due"
                );
            }
        }

        // Schedule due today email.
        if ( WPay_Emails::is_payment_due_today_reminder_enabled() && $due_timestamp >= $today ) {
            self::schedule_email_if_not_exists(
                'wpay_send_due_today_email',
                $due_timestamp,
                array( $order_id, $payment_data ),
                "order_{$order_id}_payment_{$payment_index}_due_today"
            );
        }

        // Schedule overdue emails.
        $days_after = (int) get_option( WPay_Emails::DAYS_AFTER_PAYMENT_DUE_OPTION, 3 );
        if ( $days_after > 0 ) {
            for ( $i = 1; $i <= $days_after; $i++ ) {
                $overdue_timestamp            = $due_timestamp + ( $i * DAY_IN_SECONDS );
                $overdue_data                 = $payment_data;
                $overdue_data['days_overdue'] = $i;

                self::schedule_email_if_not_exists(
                    'wpay_send_overdue_email',
                    $overdue_timestamp,
                    array( $order_id, $overdue_data ),
                    "order_{$order_id}_payment_{$payment_index}_overdue_{$i}"
                );
            }
        }
    }

    /**
     * Schedule an email if it doesn't already exist.
     *
     * @since 1.0.4
     * @param string $hook The hook name.
     * @param int    $timestamp The timestamp to schedule.
     * @param array  $args The arguments.
     * @param string $unique_key Optional unique identifier.
     * @return void
     */
    private static function schedule_email_if_not_exists( $hook, $timestamp, $args, $unique_key = '' ) {
        // Use unique key if provided, otherwise use args.
        $search_args = $unique_key ? array( 'unique_key' => $unique_key ) : $args;

        if ( ! as_has_scheduled_action( $hook, $search_args, self::HOOK_GROUP ) ) {
            $schedule_args = $args;
            if ( $unique_key ) {
                $schedule_args['unique_key'] = $unique_key;
            }

            as_schedule_single_action(
                $timestamp,
                $hook,
                $schedule_args,
                self::HOOK_GROUP
            );
        }
    }
}
