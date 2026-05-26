<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Integrations
 */

namespace RymeraWebCo\WWOF\Integrations;

use RymeraWebCo\WWOF\Abstracts\Abstract_Class;
use RymeraWebCo\WWOF\Helpers\Database_Optimizer;
use RymeraWebCo\WWOF\Helpers\Logger;
use RymeraWebCo\WWOF\Helpers\WC as WC_Helper;
use RymeraWebCo\WWOF\Helpers\WWOF;
use RymeraWebCo\WWOF\Traits\Magic_Get_Trait;
use WP_REST_Request;

/**
 * WWP class.
 *
 * @since 3.0
 * @since 3.0.8 Performance optimization.
 */
class WWP extends Abstract_Class {

    use Magic_Get_Trait;

    /**
     * Custom query args.
     *
     * @param array           $args           The query args.
     * @param WP_REST_Request $request        The request object.
     * @param WC              $wc_integration The WC integration instance.
     *
     * @since 3.0
     * @return array
     */
    public function product_object_query( $args, $request, $wc_integration ) {

        global $wc_wholesale_prices;

        if ( 'edit' === $request->get_param( 'context' ) ) {
            return $args;
        }

        $orderby         = $request->get_param( 'orderby' );
        $user_role       = $wc_wholesale_prices->wwp_wholesale_roles->getUserWholesaleRole()[0] ?? WWOF::get_app_user();
        $wholesale_roles = $wc_wholesale_prices->wwp_wholesale_roles->getAllRegisteredWholesaleRoles();
        $wholesale_roles = array_keys( $wholesale_roles );
        if ( defined( 'REST_REQUEST' ) && ! $wc_integration->search_sku_in_product_lookup_table &&
        'price' === $orderby && $user_role && in_array( $user_role, $wholesale_roles, true ) ) {

            $meta_query = array(
                'relation' => 'OR',
                array(
                    'key'     => "{$user_role}_wholesale_price",
                    'compare' => '>',
                    'type'    => 'NUMERIC',
                    'value'   => 0,
                ),
                array(
                    'key'   => "{$user_role}_have_wholesale_price_set_by_product_cat",
                    'value' => 'yes',
                ),
                array(
                    'key'     => "{$user_role}_have_wholesale_price",
                    'value'   => 'yes',
                    'compare' => '=',
                ),
            );

            $args['orderby'] = array();

            if ( $wc_integration->product_variations_query ) {
                $meta_query[] = array(
                    'key'     => "{$user_role}_variations_with_wholesale_price",
                    'compare' => '!=',
                    'value'   => '',
                );
            }

            $args['meta_query'] = WC_Helper::add_meta_query( $args, $meta_query );
        }

        return $args;
    }

    /**
     * Add database optimization controls to WWP help tab.
     *
     * @param array $controls The controls array.
     *
     * @since 3.0.8
     * @return array
     */
    public function add_database_optimization_controls( $controls ) {
        // Get current optimization status.
        $status = $this->get_optimization_status();

        // Add our optimization controls to the help tab.
        if ( isset( $controls['wholesale_prices']['help'] ) ) {
            $controls['wholesale_prices']['help']['shipping_options'][] = array(
                'type'         => 'button',
                'label'        => __( 'Database Optimization', 'woocommerce-wholesale-order-form' ),
                'id'           => 'wwp_database_optimization_button',
                'button_label' => $this->get_button_text( $status ),
                'desc_tip'     => $this->get_button_description( $status ),
                'disabled'     => $this->is_button_disabled( $status ),
                'action'       => 'schedule_database_optimization',
            );
        }

        return $controls;
    }

    /**
     * Handle action to schedule database optimization.
     *
     * @since 3.0.8
     * @return array
     */
    public function schedule_database_optimization() {
        try {
            Logger::info(
                __( 'Scheduling database optimization.', 'woocommerce-wholesale-order-form' ),
                array( 'source' => 'wwof-db-optimization' )
            );
            $optimizer = new Database_Optimizer();
            $optimizer->schedule_index_creation();

            Logger::info(
                __( 'Database optimization scheduled.', 'woocommerce-wholesale-order-form' ),
                array( 'source' => 'wwof-db-optimization' )
            );

            return array(
                'status'  => 'success',
                'message' => __( 'Database optimization scheduled successfully!', 'woocommerce-wholesale-order-form' ),
            );

        } catch ( \Exception $exception ) {
            Logger::error(
                sprintf(
                    /* translators: %s: error message */
                    __( 'Database optimization scheduling failed: %s', 'woocommerce-wholesale-order-form' ),
                    $exception->getMessage()
                ),
                array( 'source' => 'wwof-db-optimization' )
            );

            return array(
                'status'  => 'error',
                'message' => sprintf(
                    /* translators: %s: error message */
                    __( 'Failed to schedule database optimization: %s', 'woocommerce-wholesale-order-form' ),
                    $exception->getMessage()
                ),
            );
        }
    }

    /**
     * Get current optimization status.
     *
     * @since 3.0.8
     * @return string
     */
    private function get_optimization_status() {
        $indexes_completed = wp_cache_get( 'wwof_indexes_completed', 'wwof_db_optimization' );
        $is_running        = wp_cache_get( 'wwof_indexes_running', 'wwof_db_optimization' );

        if ( false !== $is_running ) {
            return 'running';
        }

        if ( false !== $indexes_completed ) {
            return 'completed';
        }

        return 'ready';
    }

    /**
     * Get button text based on status.
     *
     * @param string $status The optimization status.
     *
     * @since 3.0.8
     * @return string
     */
    private function get_button_text( $status ) {
        switch ( $status ) {
            case 'completed':
                return __( 'Optimization Complete', 'woocommerce-wholesale-order-form' );
            case 'running':
                return __( 'Optimization Running...', 'woocommerce-wholesale-order-form' );
            default:
                return __( 'Optimize Database', 'woocommerce-wholesale-order-form' );
        }
    }

    /**
     * Get button description based on status.
     *
     * @param string $status The optimization status.
     *
     * @since 3.0.8
     * @return string
     */
    private function get_button_description( $status ) {
        switch ( $status ) {
            case 'completed':
                return __( 'Database optimization has been completed successfully.', 'woocommerce-wholesale-order-form' );
            case 'running':
                return __( 'Database optimization is currently running. Please wait.', 'woocommerce-wholesale-order-form' );
            default:
                return __( 'Click to schedule database optimization for improved performance.', 'woocommerce-wholesale-order-form' );
        }
    }

    /**
     * Check if button should be disabled.
     *
     * @param string $status The optimization status.
     *
     * @since 3.0.8
     * @return bool
     */
    private function is_button_disabled( $status ) {
        return in_array( $status, array( 'running', 'completed' ), true );
    }

    /**
     * Log a message.
     *
     * @param string $message The message to log.
     * @param string $level The log level.
     * @param array  $context The context of the log.
     *
     * @since 3.0.8
     * @return void
     */
    private function log( $message, $level = 'info', $context = array() ) {
        if ( ! function_exists( 'wc_get_logger' ) ) {
            return;
        }

        $logger = wc_get_logger();
        $logger->log( $level, $message, $context );
    }

    /**
     * Run WWP integration hooks.
     *
     * @since 3.0
     * @since 3.0.8 Added database optimization hooks and filter.
     */
    public function run() {

        /***************************************************************************
         * Customize query clauses.
         ***************************************************************************
         *
         * We customize the query clauses to include WWP data in the query.
         */
        add_filter( 'wwof_wc_rest_product_object_query', array( $this, 'product_object_query' ), 10, 3 );

        /**
         * Add database optimization button to WWP help controls.
         *
         * @since 3.0.8
         */
        add_filter( 'wwp_admin_setting_default_controls', array( $this, 'add_database_optimization_controls' ) );

        /**
         * Register button trigger for database optimization.
         *
         * @since 3.0.8
         */
        add_action( 'wwp_trigger_schedule_database_optimization', array( $this, 'schedule_database_optimization' ) );

        // Register allowed trigger actions for WWP REST API validation.
        add_filter( 'wwp_allowed_trigger_actions', array( $this, 'register_allowed_trigger_actions' ) );
    }

    /**
     * Register allowed trigger actions for WWP REST API validation.
     *
     * @since 3.0.9
     *
     * @param array $actions Existing allowed trigger actions.
     * @return array Modified allowed trigger actions.
     */
    public function register_allowed_trigger_actions( $actions ) {
        $wwof_actions = array(
            'schedule_database_optimization',
        );

        return array_merge( $actions, $wwof_actions );
    }
}
