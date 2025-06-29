<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Integrations
 */

namespace RymeraWebCo\WWOF\Integrations;

use RymeraWebCo\WWOF\Abstracts\Abstract_Class;
use RymeraWebCo\WWOF\Helpers\WWOF;
use RymeraWebCo\WWOF\Helpers\WWPP as WWPP_Helper;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;

/**
 * WWPP class.
 *
 * @since 3.0
 */
class WWPP extends Abstract_Class {

    /**
     * Holds the instance of the WP_REST_Request class.
     *
     * @since 3.0.1
     * @var WP_REST_Request
     */
    private $request;

    /**
     * Filter the query arguments, before passing them to `get_terms()`.
     *
     * Enables adding extra arguments or setting defaults for a terms
     * collection request.
     *
     * @see   https://developer.wordpress.org/reference/functions/get_terms/
     *
     * @param array           $prepared_args Array of arguments to be passed to get_terms.
     * @param WP_REST_Request $request       The current request.
     *
     * @since 3.0
     * @return array
     */
    public function rest_product_cat_query( $prepared_args, $request ) {

        global $wc_wholesale_prices, $wc_wholesale_prices_premium;

        /***************************************************************************
         * Exclude product categories that are restricted to certain wholesale roles
         ***************************************************************************
         *
         * We exclude product categories that are restricted to certain wholesale
         * roles if current user's role is not in the list of allowed roles.
         */
        if ( WWPP_Helper::is_active() && defined( 'WWPP_OPTION_PRODUCT_CAT_WHOLESALE_ROLE_FILTER' ) && 'view' === $request->get_param( 'context' ) ) {
            $role_filter = get_option( WWPP_OPTION_PRODUCT_CAT_WHOLESALE_ROLE_FILTER, array() );
            if ( ! empty( $role_filter ) ) {
                $current_user_role = $wc_wholesale_prices->wwp_wholesale_roles->getUserWholesaleRole();
                $filtered_term_ids = $wc_wholesale_prices_premium->wwpp_query->_get_restricted_product_cat_ids_for_wholesale_user(
                    $current_user_role[0] ?? ''
                );
                if ( ! empty( $filtered_term_ids ) ) {
                    $filtered_term_ids = array_map( 'intval', $filtered_term_ids );
                    $already_excluded  = array_intersect( $filtered_term_ids, $prepared_args['exclude'] );
                    if ( empty( $prepared_args['exclude'] ) || empty( $already_excluded ) ) {
                        $prepared_args['exclude'] = array_map( 'intval', $filtered_term_ids );
                    }
                }
            }
        }

        return $prepared_args;
    }

    /**
     * Add wholesale product category meta to the REST API response.
     *
     * @param WP_REST_Response $response The response object.
     *
     * @since 3.0
     * @return mixed
     */
    public function rest_prepare_product_object( $response ) {

        if ( ! empty( $response->data['categories'] ) ) {
            foreach ( $response->data['categories'] as &$category ) {
                if ( is_a( $category, 'WP_Term' ) ) {
                    $category->meta = array(
                        'wwpp_enable_quantity_based_wholesale_discount'  => get_term_meta( $category->term_id, 'wwpp_enable_quantity_based_wholesale_discount', true ),
                        'wwpp_quantity_based_wholesale_discount_mapping' => get_term_meta( $category->term_id, 'wwpp_quantity_based_wholesale_discount_mapping', true ),
                    );
                } elseif ( is_array( $category ) && isset( $category['id'] ) ) {
                    $category['meta'] = array(
                        'wwpp_enable_quantity_based_wholesale_discount'  => get_term_meta( $category['id'], 'wwpp_enable_quantity_based_wholesale_discount', true ),
                        'wwpp_quantity_based_wholesale_discount_mapping' => get_term_meta( $category['id'], 'wwpp_quantity_based_wholesale_discount_mapping', true ),
                    );
                }
            }
        }

        return $response;
    }

    /**
     * Query clauses.
     *
     * @param array $clauses        The query clauses.
     * @param WC    $wc_integration The WC integration instance.
     *
     * @since 3.0
     * @return array
     */
    public function posts_clauses_request( $clauses, $wc_integration ) {

        global $wpdb, $wc_wholesale_prices, $wc_wholesale_prices_premium;

        if ( defined( 'REST_REQUEST' ) && is_a( $wc_integration->rest_request, 'WP_REST_Request' ) && $wc_integration->product_variations_query ) {
            $current_user_role = $wc_wholesale_prices->wwp_wholesale_roles->getUserWholesaleRole();
            $filtered_term_ids = $wc_wholesale_prices_premium->wwpp_query->_get_restricted_product_cat_ids_for_wholesale_user(
                $current_user_role[0] ?? ''
            );

            $placeholders = implode( ', ', array_fill( 0, count( $filtered_term_ids ), '%d' ) );

            /***************************************************************************
             * Maybe add a subquery
             ***************************************************************************
             *
             * We add a subquery to exclude product variations that are restricted to
             * certain wholesale roles if current user's role is not in the list of
             * allowed roles.
             */
            if ( ! empty( $filtered_term_ids ) ) {
                $sub_query = $wpdb->prepare(
                    "SELECT $wpdb->posts.ID FROM $wpdb->posts
LEFT JOIN $wpdb->term_relationships ON ($wpdb->posts.ID = $wpdb->term_relationships.object_id)
WHERE $wpdb->term_relationships.term_taxonomy_id IN ($placeholders)", //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
                    $filtered_term_ids
                );

                $clauses['where'] .= sprintf( " AND $wpdb->posts.post_parent NOT IN (%s)", $sub_query );
            }
        }

        return $clauses;
    }

    /**
     * Removes the custom handlers added to the WP_Query.
     *
     * @param WP_REST_Response|WP_HTTP_Response|WP_Error|mixed $response The REST API response data object.
     *
     * @since 3.0
     * @return array
     */
    public function remove_wp_query_custom_handlers( $response ) {

        if ( defined( 'REST_REQUEST' ) && filter_input( INPUT_GET, 'wwof', FILTER_VALIDATE_BOOLEAN ) ) {
            remove_filter( 'posts_clauses_request', array( $this, 'product_variation_replace_clauses' ), 100 );
        }

        return $response;
    }

    /**
     * Customize the product variation query.
     *
     * @param array $clauses The query clauses.
     *
     * @since 3.0.1
     * @return array
     */
    public function product_variation_replace_clauses( $clauses ) {

        global $wpdb, $wc_wholesale_prices;

        if ( ! is_a( $this->request, 'WP_REST_Request' ) || ! $this->request->get_param( 'wwof' ) ) {
            return $clauses;
        }

        $wholesale_role = $wc_wholesale_prices->wwp_wholesale_roles->getUserWholesaleRole()[0] ?? WWOF::get_app_user();

        $product_cat_wholesale_role_filter = (array) get_option( WWPP_OPTION_PRODUCT_CAT_WHOLESALE_ROLE_FILTER, array() );
        $product_cat_wholesale_role_filter = array_filter( $product_cat_wholesale_role_filter );
        if ( ! empty( $product_cat_wholesale_role_filter ) ) {
            $filtered_term_ids = array();
            foreach ( $product_cat_wholesale_role_filter as $term_id => $roles ) {
                if ( in_array( $wholesale_role, $roles, true ) ) {
                    $filtered_term_ids[] = $term_id;
                }
            }

            if ( ! empty( $filtered_term_ids ) ) {
                $clauses['join'] .= "
LEFT JOIN $wpdb->term_relationships ON ($wpdb->posts.ID = $wpdb->term_relationships.object_id)";

                $filtered_term_ids = implode( ', ', array_flip( array_flip( $filtered_term_ids ) ) );
                $clauses['where']  = preg_replace(
                    '/\)\s+AND\s+\(\s*mt(\d)*\.meta_key/i',
                    ") OR ($wpdb->term_relationships.term_taxonomy_id IN ($filtered_term_ids)) OR ( mt$1.meta_key",
                    $clauses['where']
                );
            }
        }

        return $clauses;
    }

    /**
     * Maybe customize the product variation REST query.
     *
     * @param array           $args    Array of arguments for WP_Query.
     * @param WP_REST_Request $request The REST API request.
     *
     * @since 3.0.1
     * @return array
     */
    public function rest_product_variation_object_query( $args, $request ) {

        if ( ! $request->get_param( 'wwof' ) ) {
            return $args;
        }

        $this->request = $request;

        if ( 'yes' === get_option( 'wwpp_settings_only_show_wholesale_products_to_wholesale_users', 'no' ) ) {
            add_filter( 'posts_clauses_request', array( $this, 'product_variation_replace_clauses' ), 100 );
        }

        return $args;
    }

    /**
     * Run WWPP integration hooks.
     *
     * @since 3.0
     */
    public function run() {

        if ( ! WWPP_Helper::is_active() ) {
            return;
        }

        /***************************************************************************
         * Filter WC product category query args
         ***************************************************************************
         *
         * We add a filter to WP REST API query to customize query params for
         * product category taxonomy.
         */
        add_filter( 'woocommerce_rest_product_cat_query', array( $this, 'rest_product_cat_query' ), 100, 2 );

        /***************************************************************************
         * Filter WC product variation query args
         ***************************************************************************
         *
         * We add a filter to WP REST API query to customize query params for
         * product variation post type.
         */
        add_filter(
            'woocommerce_rest_product_variation_object_query',
            array(
                $this,
                'rest_product_variation_object_query',
            ),
            100,
            2
        );

        /***************************************************************************
         * Additional wholesale product category meta in REST API response
         ***************************************************************************
         *
         * We add additional wholesale product category meta to the REST API
         * response.
         */
        add_filter( 'wwof_wc_rest_prepare_product_object', array( $this, 'rest_prepare_product_object' ) );

        /***************************************************************************
         * REST API query clauses
         ***************************************************************************
         *
         * Custom query clauses for REST API.
         */
        add_filter( 'wwof_posts_clauses_request', array( $this, 'posts_clauses_request' ), 10, 2 );

        /***************************************************************************
         * Remove custom handlers added to WP_Query
         ***************************************************************************
         *
         * Let's make sure to cleanup after ourselves and remove the custom handlers
         * we added to the WP_Query.
         */
        add_filter( 'rest_request_after_callbacks', array( $this, 'remove_wp_query_custom_handlers' ), 100 );
    }
}
