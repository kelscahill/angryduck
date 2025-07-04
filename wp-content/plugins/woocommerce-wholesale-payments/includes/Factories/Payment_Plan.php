<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WPay\Factories
 */

namespace RymeraWebCo\WPay\Factories;

use WP_Error;
use WP_Post;
use RymeraWebCo\WPay\Helpers\WPay;
use RymeraWebCo\WPay\Traits\Magic_Get_Trait;

/**
 * Payment_Plan class.
 *
 * @since 1.0.0
 */
class Payment_Plan {

    use Magic_Get_Trait;

    /**
     * Payment plan post type.
     *
     * @since 1.0.0
     */
    const POST_TYPE = 'wpay_plan';

    /**
     * Holds the WP_Post object of wpay_plan post type.
     *
     * @since 1.0.0
     * @var WP_Post
     */
    public $post;

    /**
     * Holds the payment plan breakdown.
     *
     * @since 1.0.0
     * @var array Payment plan breakdown.
     */
    public $breakdown;

    /**
     * Holds the payment plan enabled status.
     *
     * @since 1.0.0
     * @var string Enabled = 'yes'; Disabled = 'no'.
     */
    public $enabled;

    /**
     * Holds the wholesale roles the payment plan is restricted to.
     *
     * @since 1.0.0
     * @var string[] Wholesale roles.
     */
    public $wholesale_roles;

    /**
     * Holds the usernames the payment plan is restricted to.
     *
     * @since 1.0.0
     * @var string[] Allowed users.
     */
    public $allowed_users;

    /**
     * Holds the apply restrictions status. Either 'yes' or 'no'.
     *
     * @since 1.0.0
     * @var string Apply restrictions.
     */
    public $apply_restrictions;

    /**
     * Holds the active orders.
     *
     * @since 1.0.1
     * @var string Active orders.
     */
    public $active_orders;

    /**
     * Holds the apply auto charge status. Either 'yes' or 'no'.
     *
     * @since 1.0.5
     * @var string Apply auto charge.
     */
    public $apply_auto_charge;

    /**
     * Payment_Plan constructor.
     *
     * @param WP_Post|object $post Post object.
     *
     * @since 1.0.0
     * @since 1.0.1 Changes: Added 'active_orders'.
     */
    public function __construct( &$post ) {

        $this->post = &$post;

        $this->enabled            = get_post_meta( $this->post->ID, 'enabled', true );
        $this->wholesale_roles    = get_post_meta( $this->post->ID, 'wholesale_roles', true );
        $this->allowed_users      = get_post_meta( $this->post->ID, 'allowed_users', true );
        $this->apply_restrictions = get_post_meta( $this->post->ID, 'apply_restrictions', true );
        $this->apply_auto_charge  = get_post_meta( $this->post->ID, 'apply_auto_charge', true );
        $this->active_orders      = $this->get_active_orders( $this->post->ID );
        $this->set_breakdown();
        $this->set_apply_auto_charge();
    }

    /**
     * Get an instance of a Payment Plan.
     *
     * @param int $post_id Post ID.
     *
     * @return Payment_Plan|bool
     */
    public static function get_instance( $post_id ) {

        $post = get_post( $post_id );

        if ( ! $post || get_post_type( $post ) !== self::POST_TYPE ) {
            return false;
        }

        return new Payment_Plan( $post );
    }

    /**
     * Get the apply auto charge status.
     *
     * @since 1.0.5
     * @return void
     */
    public function set_apply_auto_charge() {
        $stripe_auto_charge = get_option( 'wpay_stripe_auto_charge_invoices', 'no' );
        $auto_charge        = ! empty( $stripe_auto_charge ) && 'yes' === $stripe_auto_charge ? true : false;
        $apply_auto_charge  = get_post_meta( $this->post->ID, 'apply_auto_charge', true );

        if ( ! empty( $apply_auto_charge ) && 'yes' === $apply_auto_charge ) {
            $auto_charge = $apply_auto_charge;
        }

        $this->apply_auto_charge = $auto_charge;
    }

    /**
     * Set the payment plan breakdown.
     *
     * @since 1.0.0
     * @return void
     */
    private function set_breakdown() {

        $this->breakdown = array();
        $breakdown       = get_post_meta( $this->post->ID, 'breakdown', true );
        if ( ! empty( $breakdown ) ) {
            foreach ( $breakdown as $item ) {

                switch ( $item['day_format'] ) {
                    case 'timestamp':
                    case 'custom':
                        $this->breakdown[] = wp_parse_args(
                            array(
                                'day_display' => WPay::parse_day( $item['day'], 'view' ),
                            ),
                            $item
                        );
                        break;
                    default:
                        $this->breakdown[] = $item;
                }
            }
        }
    }

    /**
     * Get active orders.
     *
     * @param int $plan_id Plan ID.
     *
     * @since 1.0.1
     * @return int
     */
    private function get_active_orders( $plan_id ) {

        $meta_key   = '_wpay_plan';
        $meta_value = '"id":' . $plan_id . ',';

        $orders = wc_get_orders(
            array(
                'limit'        => -1,
                'return'       => 'ids',
                'status'       => array( 'wc-processing', 'wc-on-hold' ),
                'meta_key'     => $meta_key,
                'meta_value'   => $meta_value,
                'meta_compare' => 'LIKE',
            )
        );

        $total_orders = count( $orders );

        return $total_orders;
    }

    /**
     * Create a new payment plan.
     *
     * @param array $args Array of arguments.
     *
     * @return bool|WP_Error|Payment_Plan
     */
    public static function save( $args = array() ) {

        $defaults = array(
            'ID'                 => 0,
            'post_title'         => '',
            'post_content'       => '',
            'breakdown'          => array(),
            'enabled'            => 'yes',
            'wholesale_roles'    => array(),
            'allowed_users'      => array(),
            'apply_restrictions' => 'no',
            'apply_auto_charge'  => 'no',
        );

        $args = wp_parse_args( $args, $defaults );

        $days     = array_column( $args['breakdown'], 'day' );
        $int_days = array_map( 'intval', $days );

        if ( count( array_unique( $days ) ) !== count( $days ) ) {
            return new WP_Error( 'invalid_days', __( 'Days must be unique.', 'woocommerce-wholesale-payments' ), array( 'status' => 400 ) );
        }

        $sorted_days = $int_days;
        sort( $sorted_days );
        if ( $sorted_days !== $int_days ) {
            return new WP_Error( 'invalid_days', __( 'Days in breakdown must be in ascending order.', 'woocommerce-wholesale-payments' ), array( 'status' => 400 ) );
        }

        $total_percentage = 0;
        $has_percentage   = false;
        foreach ( $args['breakdown'] as $key => $item ) {
            if ( 'percentage' === $item['due']['type'] ) {
                $has_percentage = true;
                if ( false !== strpos( $item['due']['value'], '.' ) ) {
                    return new WP_Error( 'invalid_percentage', __( 'Percentage value must be an integer.', 'woocommerce-wholesale-payments' ), array( 'status' => 400 ) );
                }
                $total_percentage += (int) $item['due']['value'];
            }
        }

        if ( $has_percentage && 100 !== $total_percentage ) {
            return new WP_Error( 'invalid_percentage', __( 'Percentage value must sum up to 100.', 'woocommerce-wholesale-payments' ), array( 'status' => 400 ) );
        }

        $args['post_type']   = self::POST_TYPE;
        $args['post_status'] = 'publish';

        if ( ! $args['ID'] ) {
            $post_id = wp_insert_post( $args );
        } else {
            $post_id = wp_update_post( $args );
        }

        if ( ! $post_id ) {
            return false;
        }

        update_post_meta( $post_id, 'breakdown', $args['breakdown'] );

        $enabled_arg = wc_bool_to_string( $args['enabled'] );
        update_post_meta( $post_id, 'enabled', $enabled_arg );
        update_post_meta( $post_id, 'wholesale_roles', $args['wholesale_roles'] );
        update_post_meta( $post_id, 'allowed_users', $args['allowed_users'] );

        $apply_restrictions_arg = wc_bool_to_string( $args['apply_restrictions'] );
        update_post_meta( $post_id, 'apply_restrictions', $apply_restrictions_arg );

        $apply_auto_charge_arg = wc_bool_to_string( $args['apply_auto_charge'] );
        update_post_meta( $post_id, 'apply_auto_charge', $apply_auto_charge_arg );

        return self::get_instance( $post_id );
    }

    /**
     * Set the payment plan status to enabled.
     * This will set the 'enabled' meta to 'yes'.
     *
     * @param array $args Array of arguments.
     *
     * @return Payment_Plan|bool
     */
    public static function set_plan_status( $args ) {

        $defaults = array(
            'ID'      => 0,
            'enabled' => 'yes',
        );

        $args = wp_parse_args( $args, $defaults );

        $args['post_type']   = self::POST_TYPE;
        $args['post_status'] = 'publish';

        $post_id = wp_update_post( $args );

        if ( ! $post_id ) {
            return false;
        }

        if ( is_bool( $args['enabled'] ) ) {
            if ( true !== $args['enabled'] ) {
                $args['enabled'] = 'no';
            } else {
                $args['enabled'] = 'yes';
            }
        }
        update_post_meta( $post_id, 'enabled', $args['enabled'] );

        return self::get_instance( $post_id );
    }

    /**
     * Update a payment plan.
     *
     * @return $this
     */
    public function update() {

        wp_update_post(
            array(
                'ID'           => $this->post->ID,
                'post_title'   => $this->post->post_title,
                'post_content' => $this->post->post_content,
            )
        );

        update_post_meta( $this->post->ID, 'breakdown', $this->breakdown );

        return $this;
    }

    /**
     * Delete a payment plan.
     *
     * @param bool $force_delete Force delete.
     *
     * @return bool
     */
    public function delete( $force_delete = true ) {

        return wp_delete_post( $this->post->ID, $force_delete );
    }

    /**
     * If payment plan is enabled.
     *
     * @return bool
     */
    public function is_enabled() {

        return 'yes' === $this->enabled || true === $this->enabled;
    }

    /**
     * Convert payment plan to JSON.
     *
     * @since 1.0.0
     * @since 1.0.1 Changes:
     *        - Renamed 'roles' to 'allowed_roles'
     *        - Added 'allowed_users'
     *
     * @return false|string
     */
    public function to_json() {

        return wp_json_encode(
            array(
                'id'            => $this->post->ID,
                'title'         => $this->post->post_title,
                'breakdown'     => $this->breakdown,
                'enabled'       => $this->is_enabled(),
                'allowed_roles' => $this->wholesale_roles,
                'allowed_users' => $this->allowed_users,
            )
        );
    }

    /**
     * Check if a payment plan is applicable for a given total.
     *
     * @param float|string $total Total number.
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_applicable( $total ) {

        global $wc_wholesale_prices;

        $total = floatval( $total );

        if ( ! $this->is_enabled() ) {
            return false;
        }

        if ( empty( $this->breakdown ) ) {
            return false;
        }

        if ( 'yes' === $this->apply_restrictions && ( ! empty( $this->allowed_users ) || ! empty( $this->wholesale_roles ) ) ) {
            $user           = wp_get_current_user();
            $wholesale_role = $wc_wholesale_prices->wwp_wholesale_roles->getUserWholesaleRole();
            $allow          = false;

            if ( ! empty( $this->allowed_users ) && ! empty( $this->wholesale_roles ) ) {
                if ( in_array( $user->ID, $this->allowed_users, true ) ) {
                    $allow = true;
                }
                if ( ! in_array( $user->ID, $this->allowed_users, true ) &&
                    ( ! empty( $wholesale_role[0] ) && in_array( $wholesale_role[0], $this->wholesale_roles, true ) ) ) {
                    $allow = true;
                }
            } elseif ( ! empty( $this->allowed_users ) && in_array( $user->ID, $this->allowed_users, true ) ) {
                $allow = true;
            } elseif ( ! empty( $this->wholesale_roles ) && ! empty( $wholesale_role[0] ) &&
                in_array( $wholesale_role[0], $this->wholesale_roles, true ) ) {
                $allow = true;
            }

            if ( ! $allow ) {
                return false;
            }
        }

        $total_due        = 0;
        $fixed_items      = array();
        $percentage_items = array();

        /***************************************************************************
         * Check if breakdown is mixed (percentage and fixed).
         ***************************************************************************
         *
         * We need to check if the breakdown is mixed (percentage and fixed) or not.
         */
        foreach ( $this->breakdown as $item ) {
            if ( 'percentage' === $item['due']['type'] ) {
                $percentage_items[] = $item;
            } else {
                $fixed_items[] = $item;
            }
        }

        if ( ! empty( $fixed_items ) && ! empty( $percentage_items ) ) {
            foreach ( $fixed_items as $fixed_item ) {
                $value = (float) $fixed_item['due']['value'];
                if ( $value > $total ) {
                    return false;
                }
                $total -= $value;
            }
            foreach ( $percentage_items as $percentage_item ) {
                $total_due += (float) $total * $percentage_item['due']['value'] / 100;

                if ( $total_due > $total ) {
                    return false;
                }
            }
        } else {
            foreach ( $this->breakdown as $item ) {
                if ( 'percentage' === $item['due']['type'] ) {
                    $total_due += (float) $total * $item['due']['value'] / 100;
                } else {
                    $total_due += (float) $item['due']['value'];
                }

                if ( $total_due > $total ) {
                    return false;
                }
            }
        }

        if ( $total_due !== $total ) {
            return false;
        }

        return true;
    }
}
