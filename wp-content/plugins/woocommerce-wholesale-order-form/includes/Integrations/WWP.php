<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Integrations
 */

namespace RymeraWebCo\WWOF\Integrations;

use RymeraWebCo\WWOF\Abstracts\Abstract_Class;
use RymeraWebCo\WWOF\Helpers\WC as WC_Helper;
use RymeraWebCo\WWOF\Helpers\WWOF;
use RymeraWebCo\WWOF\Traits\Magic_Get_Trait;
use WP_REST_Request;

/**
 * WWP class.
 *
 * @since 3.0
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
     * Run WWP integration hooks.
     *
     * @since 3.0
     */
    public function run() {

        /***************************************************************************
         * Customize query clauses.
         ***************************************************************************
         *
         * We customize the query clauses to include WWP data in the query.
         */
        add_filter( 'wwof_wc_rest_product_object_query', array( $this, 'product_object_query' ), 10, 3 );
    }
}
