<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Classes
 */

namespace RymeraWebCo\WWOF\Integrations;

use RymeraWebCo\WWOF\Abstracts\Abstract_Class;
use RymeraWebCo\WWOF\Factories\Order_Form;
use RymeraWebCo\WWOF\Helpers\WWOF;
use RymeraWebCo\WWOF\Helpers\WWP;
use RymeraWebCo\WWOF\Helpers\WC as WC_Helper;
use RymeraWebCo\WWOF\Traits\Magic_Get_Trait;
use WC_Product;
use WC_Product_Variation;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_User;
use WWP_Wholesale_Prices;
use WWPP_Wholesale_Roles;
use Automattic\WooCommerce\Admin\API\Reports\Products\DataStore as ProductsDataStore;


/**
 * WooCommerce Integration class.
 *
 * @since 3.0
 */
class WC extends Abstract_Class {

    use Magic_Get_Trait;

    /**
     * A string to inject into a query to do a partial match SKU search.
     *
     * @since 3.0
     * @var string
     */
    private $search_sku_in_product_lookup_table = '';

    /**
     * Whether we are including product variations in the query.
     *
     * @since 3.0
     * @var bool
     */
    private $product_variations_query = false;

    /**
     * Custom WC order by fields.
     *
     * @since 3.0
     * @var string[]
     */
    private $wc_rest_orderby_meta_fields;

    /**
     * Copy of the current REST request.
     *
     * @since 3.0
     * @var WP_REST_Request
     */
    private $rest_request;

    /**
     * In-memory memoization cache for expensive computations.
     *
     * @since 3.0
     * @var array
     */
    private static $memoization_cache = array();

    /**
     * Constructor.
     *
     * @since 3.0
     */
    public function __construct() {

        /**************************************************************************
         * Register custom order by fields for WC REST API
         **************************************************************************
         *
         * We add some custom params to the product collection params to allow
         * sorting by meta fields.
         */
        $this->wc_rest_orderby_meta_fields = array(
            '_sku',
        );
    }

    /**
     * We override the WooCommerce REST API authentication to run requests in the context of the current user.
     *
     * @param null|WP_User $user Current user.
     *
     * @since 3.0
     * @return WP_User
     */
    public function wc_api_auth_as_current_user( $user = null ) {

        /**************************************************************************
         * Override WooCommerce REST API authentication
         **************************************************************************
         *
         * We override the WooCommerce REST API authentication to run requests in
         * the context of the current user instead of requiring api/secret keys.
         */
        if ( null === $user ) {
            $user = wp_get_current_user();
        }

        return $user;
    }

    /**
     * Override WC REST permissions check.
     *
     * @param bool   $permission Whether the current user has permission.
     * @param string $context    Request context.
     * @param int    $object_id  Object ID.
     * @param string $post_type  Post type.
     *
     * @since 3.0
     * @return bool
     */
    public function wc_rest_check_permissions( $permission, $context, $object_id, $post_type ) {

        global $wc_wholesale_prices;

        $allowed_post_types_rest_read = apply_filters(
            'wwof_rest_allowed_post_types_read',
            array(
                'product',
                'product_variation',
                'product_cat',
            )
        );

        if ( 'read' === $context && 'product_cat' === $post_type && ! $permission ) {
            $permission = true;
        }

        /**************************************************************************
         * Override WooCommerce REST API permissions
         **************************************************************************
         *
         * We override the WooCommerce REST API permissions to run requests in
         * the context of the current user further checking if their account is
         * allowed to access REST resources.
         */
        if ( ! $permission && WWP::is_active() && 'read' === $context && in_array( $post_type, $allowed_post_types_rest_read, true ) ) {
            $form_id        = filter_input( INPUT_GET, 'wwof', FILTER_VALIDATE_INT );
            $wholesale_role = filter_input( INPUT_GET, 'wholesale_role', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
            $order_form     = get_post( $form_id );
            $permission     = ! empty( $order_form ) && Order_Form::POST_TYPE === $order_form->post_type && empty( $wholesale_role );
            if ( ! $permission ) {
                $user_wholesale_role = (array) $wc_wholesale_prices->wwp_wholesale_roles->getUserWholesaleRole();
                $user_wholesale_role = array_filter( $user_wholesale_role );
                if ( ! empty( $user_wholesale_role ) ) {
                    $permission = in_array( $wholesale_role, $user_wholesale_role, true );
                }
                $permission = apply_filters( 'wwof_rest_read_permissions', $permission, $object_id, $post_type );
            }
        }

        return $permission;
    }

    /**
     * Customize preparation of a single WC product object.
     *
     * @param WP_REST_Response $response The response object.
     * @param WC_Product       $wc_data  Object data.
     * @param WP_REST_Request  $request  Request object.
     *
     * @since 3.0
     * @return WP_REST_Response
     */
    public function rest_prepare_product_object( $response, $wc_data, $request ) {

        if ( ! $request->get_param( 'wwof' ) || $request->get_param( 'context' ) === 'edit' ) {
            return $response;
        }

        /***************************************************************************
         * Add `categories` property
         ***************************************************************************
         *
         * We add the `categories` property to the product variation object to be
         * used in the app.
         */
        if ( ( empty( $response->data['categories'] ) && $wc_data->get_parent_id() ) || ( $this->product_variations_query && $wc_data->get_parent_id() ) ) {
            $response->data['categories'] = wc_get_object_terms( $wc_data->get_parent_id(), 'product_cat' );
        }

        /***************************************************************************
         * Add `tags` property to product variations
         ***************************************************************************
         *
         * We add the `tags` property to product variations by inheriting tags
         * from the parent variable product. This applies when:
         * - The product has a parent ID (is a variation), OR
         * - We're querying variations individually and the product has a parent
         */
        if ( ( empty( $response->data['tags'] ) && $wc_data->get_parent_id() ) || ( $this->product_variations_query && $wc_data->get_parent_id() ) ) {
            $response->data['tags'] = wc_get_object_terms( $wc_data->get_parent_id(), 'product_tag' );
        }

        /***************************************************************************
         * Add `tags` property to non-variable products
         ***************************************************************************
         *
         * We add the `tags` property to simple products and other non-variable
         * product types. This handles products that don't have a parent ID.
         */
        if ( empty( $response->data['tags'] ) && 'variable' !== $wc_data->get_type() && 'variation' !== $wc_data->get_type() ) {
            $response->data['tags'] = wc_get_object_terms( $wc_data->get_id(), 'product_tag' );
        }

        /***************************************************************************
         * Replace `default_attributes` property
         ***************************************************************************
         *
         * The default attributes property uses term slug as option instead of term
         * name. We replace the `default_attributes` "option" property with the term
         * name instead as this is what our app is expecting.
         */
        if ( ! empty( $response->data['default_attributes'] ) ) {
            $wc_attr_labels     = array_flip( wc_get_attribute_taxonomy_labels() );
            $default_attributes = array();
            foreach ( $response->data['default_attributes'] as $default_attribute ) {
                if ( isset( $wc_attr_labels[ $default_attribute['name'] ] ) ) {
                    $taxonomy = 'pa_' . $wc_attr_labels[ $default_attribute['name'] ];
                    $term     = get_term_by( 'slug', $default_attribute['option'], $taxonomy );

                    if ( ! is_wp_error( $term ) ) {
                        $default_attributes[] = wp_parse_args(
                            array( 'option' => $term->name ),
                            $default_attribute
                        );
                    }
                } else {
                    /***************************************************************************
                     * Include custom attribute
                     ***************************************************************************
                     *
                     * This is likely a custom attribute. We include it as is.
                     *
                     * @since 3.0.1
                     */
                    $default_attributes[] = $default_attribute;
                }
            }
            if ( ! empty( $default_attributes ) ) {
                $response->data['default_attributes'] = $default_attributes;
            }
        }

        /***************************************************************************
         * Do not inherit SKU from parent for variations
         ***************************************************************************
         *
         * We don't want variations to inherit SKU value from parent product as it's
         * confusing in the order form when sorting by SKU. Variations that actually
         * have empty SKU appears at the top of the list BUT they have the same SKU
         * as the parent product so, it might seem that sorting by SKU is not
         * working properly.
         */
        if ( 'variation' === $response->data['type'] && ! empty( $response->data['sku'] ) ) {
            $parent_id   = $wc_data->get_parent_id();
            $parent      = $parent_id ? wc_get_product( $parent_id ) : null;
            $parent_sku  = $parent ? $parent->get_sku() : '';
            $object_data = $wc_data->get_data();
            if ( empty( $object_data['sku'] ) && $response->data['sku'] === $parent_sku ) {
                $response->data['sku'] = '';
            }
        }

        /***************************************************************************
         * Add variation-specific description
         ***************************************************************************
        *
        * When variations are shown individually, display the variation's own
        * description instead of inheriting from the parent product. This provides
        * more specific information about each variation.
        *
        * @since 3.0.7 Added variation description support for issue #758.
        */
        if ( 'variation' === $response->data['type'] && ! empty( $wc_data->get_description() ) ) {
            $response->data['short_description'] = $wc_data->get_description();
        }

        /**************************************************************************
         * Add `key` property to product object
         **************************************************************************
         *
         * We add the `key` property to the product object to be used in the JS app.
         */
        $response->data['key'] = $wc_data->get_id();

        /**
         * Prepare the product object for the REST response.
         *
         * @param WP_REST_Response $response The response object.
         * @param WC_Product       $wc_data  Object data.
         * @param WP_REST_Request  $request  Request object.
         * @param WC               $this     The current instance of the WC class.
         *
         * @since 3.0
         */
        return apply_filters( 'wwof_wc_rest_prepare_product_object', $response, $wc_data, $request, $this );
    }

    /**
     * Customize preparation of a single WC product object.
     *
     * @param WP_REST_Response     $response The response object.
     * @param WC_Product_Variation $product  Object data.
     * @param WP_REST_Request      $request  Request object.
     *
     * @since 3.0
     * @return WP_REST_Response
     */
    public function rest_prepare_product_variation_object( $response, $product, $request ) {

        if ( ! $request->get_param( 'wwof' ) || $request->get_param( 'context' ) === 'edit' ) {
            return $response;
        }

        /***************************************************************************
         * Ensure `key` property is set
         ***************************************************************************
         *
         * We make sure that the `key` property is set on the product variation
         * object to be used in the app.
         */
        $response->data['key'] = $product->get_id();

        /***************************************************************************
         * Ensure `parent_id` is available
         ***************************************************************************
         *
         * We require the `parent_id` property to be set on the product variation
         * especially for the purpose of determining minimum order quantity on
         * parent product level.
         */
        if ( empty( $response->data['parent_id'] ) ) {
            $response->data['parent_id'] = $product->get_parent_id();
        }

        /***************************************************************************
         * Ensure `attribute_summary` property is set
         ***************************************************************************
         *
         * We make sure that the `attribute_summary` property is set on the product
         * variation object to be used in the app.
         */
        if ( empty( $response->data['attribute_summary'] ) ) {
            $response->data['attribute_summary'] = $product->get_attribute_summary();
        }

        $variation  = new WC_Product_Variation( $product->get_id() );
        $attributes = ! empty( $variation->get_attributes() )
            ? array_keys(
                array_filter(
                    $variation->get_attributes(),
                    function ( $value ) {

                        return empty( $value );
                    }
                )
            )
            : array();
        foreach ( $attributes as $attribute_name ) {
            $response->data['attributes'][] = array(
                'id'     => wc_attribute_taxonomy_id_by_name( $attribute_name ),
                'name'   => wc_attribute_label( $attribute_name, $product ),
                'option' => '',
            );
        }
        $response->data['attributes'] = wp_list_sort( $response->data['attributes'], 'id' );

        /***************************************************************************
         * Add `categories` property
         ***************************************************************************
         *
         * We add the `categories` property to the product variation object to be
         * used in the app.
         */
        if ( empty( $response->data['categories'] ) ) {
            $response->data['categories'] = wc_get_object_terms( $product->get_parent_id(), 'product_cat' );
        }

        /***************************************************************************
         * Add `tags` property to product variations
         ***************************************************************************
         *
         * We add the `tags` property to product variations by inheriting tags
         * from the parent variable product. Variations don't have their own tags
         * in WooCommerce, so we use the parent's tags.
         */
        if ( empty( $response->data['tags'] ) ) {
            $response->data['tags'] = wc_get_object_terms( $product->get_parent_id(), 'product_tag' );
        }

        if ( empty( $response->data['price_html'] ) ) {
            $response->data['price_html'] = $product->get_price_html();
        }

        /***************************************************************************
         * Variations do not include the `name` property.
         * Version 8.3+ includes the `name` property but only the attribute summary.
         ***************************************************************************
         *
         * Ensure that the `name` property is set on the product variation object.
         * Version 8.3+ includes the parent product name.
         */
        if ( empty( $response->data['name'] ) || version_compare( WC()->version, '8.3', '>=' ) ) {
            $response->data['name'] = $product->get_name();
        }

        return $response;
    }

    /**
     * Filter params array to add our custom params.
     *
     * @param array $params Query params.
     *
     * @since 3.0
     * @return array
     */
    public function rest_product_collection_params( $params ) {

        foreach ( $this->wc_rest_orderby_meta_fields as $orderby_field ) {
            $params['orderby']['enum'][] = $orderby_field;
        }

        return $params;
    }

    /**
     * Customize the WC REST API product search query.
     *
     * @param array $clauses The query clauses.
     *
     * @since 3.0
     * @since 3.0.8 Performance optimizations have been added including improved JOIN efficiency and caching for expensive calculations.
     * @return array
     */
    public function search_post_clauses_request( $clauses ) {
        // Clear caches to ensure fresh results.
        self::clear_memoization_cache();

        // Only try to clear search cache if rest_request is set.
        if ( is_a( $this->rest_request, 'WP_REST_Request' ) && $this->rest_request->get_param( 'search' ) ) {
            wp_cache_delete( 'wwof_search_' . md5( $this->rest_request->get_param( 'search' ) ), 'wwof_queries' );
        }

        global $wpdb;

        if ( is_a( $this->rest_request, 'WP_REST_Request' ) && $this->search_sku_in_product_lookup_table ) {
            /***************************************************************************
             * Move LEFT JOIN for wc_product_meta_lookup from the end to the beginning
             ***************************************************************************
             *
             * We move the LEFT JOIN for wc_product_meta_lookup from the end to the
             * beginning of the query to ensure that the `sku` field is available for
             * search.
             */

            // Cache the search term to avoid repeated processing.
            $search_term             = $this->rest_request->get_param( 'search' );
            $cache_key               = 'wwof_search_' . md5( $search_term );
            $cached_clauses          = wp_cache_get( $cache_key, 'wwof_queries' );
            $form_id                 = $this->rest_request->get_param( 'wwof' );
            $order_form              = $form_id ? Order_Form::get_instance( $form_id ) : null;
            $sku_exact_keyword_match = 'no';

            if ( null !== $order_form ) {
                $exact_match_setting     = $order_form->get_form_header_setting_from_meta_data( 'skuSearchExact' );
                $sku_exact_keyword_match = ! empty( $exact_match_setting ) ? $exact_match_setting : 'no';
            }

            if ( false !== $cached_clauses ) {
                return $cached_clauses;
            }

            // Optimize JOIN by ensuring proper index usage.
            $clauses['join'] = preg_replace(
                "/LEFT JOIN {$wpdb->prefix}wc_product_meta_lookup wc_product_meta_lookup\s*ON $wpdb->posts\.ID = wc_product_meta_lookup\.product_id/",
                '',
                $clauses['join']
            );

            // Keep using LEFT JOIN to ensure we don't lose products that don't have meta lookup entries.
            $clauses['join'] = " LEFT JOIN {$wpdb->prefix}wc_product_meta_lookup wc_product_meta_lookup ON {$wpdb->prefix}posts.ID = wc_product_meta_lookup.product_id " . $clauses['join'];

            // Optimize WHERE clause with proper escaping and indexing.
            $escaped_search = wc_string_to_bool( $sku_exact_keyword_match ) ? $wpdb->esc_like( $search_term ) : '%' . $wpdb->esc_like( $search_term ) . '%';

            // Use simpler, more inclusive SKU search pattern.
            $sku_search_condition = '';
            if ( ! empty( $search_term ) ) {
                // Use a broader LIKE pattern to ensure we catch all potential matches.
                $sku_search_condition = $wpdb->prepare( 'wc_product_meta_lookup.sku LIKE %s', $escaped_search );

                // When showing variations individually, also search parent product SKUs for variations without their own SKU.
                if ( $this->product_variations_query ) {
                    // Add LEFT JOIN to parent product's meta lookup table for variations.
                    $clauses['join'] .= " LEFT JOIN {$wpdb->prefix}wc_product_meta_lookup parent_lookup 
                        ON ($wpdb->posts.post_type = 'product_variation' AND $wpdb->posts.post_parent = parent_lookup.product_id) ";

                    // Enhanced SKU search condition that includes parent SKU for variations.
                    $parent_sku_condition = $wpdb->prepare( 'parent_lookup.sku LIKE %s', $escaped_search );
                    $sku_search_condition = "($sku_search_condition OR ($wpdb->posts.post_type = 'product_variation' AND $parent_sku_condition))";
                } else { // in order for this condition to work make sure 'Show Variations Individually' is 'disabled'.
                    $clauses['where'] .= " OR ($sku_search_condition)"; // this is used for fetching individual variations product matching the sku name.
                }
            }

            if ( ! empty( $sku_search_condition ) ) {
                $clauses['where'] = str_replace(
                    ") OR ($wpdb->posts.post_content LIKE",
                    ") OR ($sku_search_condition) OR ($wpdb->posts.post_content LIKE",
                    $clauses['where']
                );
            }

            // Remove any duplicate SKU conditions.
            $clauses['where'] = preg_replace( '/ AND \(wc_product_meta_lookup\.sku LIKE([^)]+)\)/', '', $clauses['where'] );

            $order_by = sanitize_text_field( $this->rest_request->get_param( 'orderby' ) );
            if ( '_sku' === $order_by ) {
                $order = sanitize_text_field( $this->rest_request->get_param( 'order' ) );
                $order = strtoupper( $order );

                $clauses['orderby'] = str_replace(
                    "$wpdb->posts.post_date $order",
                    "wc_product_meta_lookup.sku $order, $wpdb->posts.post_title $order",
                    $clauses['orderby']
                );
            }

            /**
             * Add filter to allow other plugins to customize the search query clauses
             *
             * @param array $clauses The query clauses.
             * @param WC    $this    The current instance of the WC class.
             *
             * @since 3.0.1
             */
            $clauses = apply_filters( 'wwof_search_post_clauses_request', $clauses, $this );

            // Cache the optimized clauses for 5 minutes.
            wp_cache_set( $cache_key, $clauses, 'wwof_queries', 300 );
        }

        return $clauses;
    }

    /**
     * Customize query clauses.
     *
     * @param array $clauses Array of clauses.
     *
     * @since 3.0
     * @since 3.0.7 Added support for priority products.
     * @since 3.0.8 Performance optimizations have been added including improved JOIN efficiency and caching for expensive calculations.
     * @return array
     */
    public function replace_clauses( $clauses ) {
        // Clear memoization cache to ensure fresh results.
        self::clear_memoization_cache();

        global $wpdb;

        if ( defined( 'REST_REQUEST' ) && is_a( $this->rest_request, 'WP_REST_Request' ) && ! $this->search_sku_in_product_lookup_table ) {
            $order_by = $this->rest_request->get_param( 'orderby' ) ? sanitize_text_field( $this->rest_request->get_param( 'orderby' ) ) : '';
            $order    = $this->rest_request->get_param( 'order' ) ? sanitize_text_field( $this->rest_request->get_param( 'order' ) ) : 'ASC';
            $order    = strtoupper( $order );

            $form_id    = $this->rest_request->get_param( 'wwof' );
            $order_form = $form_id ? Order_Form::get_instance( $form_id ) : null;

            if ( ! $order_form ) {
                return $clauses;
            }

            /***************************************************************************
             * Show priority products first
             ***************************************************************************
             *
             * We show priority products first on top of the list below the favourites if selected in the settings.
             */
            $priority_products = $order_form->get_settings( 'priorityProducts' );
            if ( ! empty( $priority_products ) ) {
                // Use memoization for priority products to avoid repeated processing within the same request.
                $priority_memo_key = 'priority_' . md5( wp_json_encode( $priority_products ) );

                if ( ! isset( self::$memoization_cache[ $priority_memo_key ] ) ) {
                    // Fallback to object cache if not in memory.
                    $priority_cache_key    = 'wwof_priority_' . md5( wp_json_encode( $priority_products ) );
                    $cached_priority_order = wp_cache_get( $priority_cache_key, 'wwof_queries' );

                    if ( false === $cached_priority_order ) {
                        $product_lists_placeholders = implode( ', ', array_fill( 0, count( $priority_products ), '%d' ) );
                        $reversed_priority_products = array_reverse( $priority_products );
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
                        $cached_priority_order = $wpdb->prepare( "FIELD($wpdb->posts.ID, $product_lists_placeholders) DESC, ", $reversed_priority_products );
                        wp_cache_set( $priority_cache_key, $cached_priority_order, 'wwof_queries', 600 );
                    }

                    // Store in memory for this request.
                    self::$memoization_cache[ $priority_memo_key ] = $cached_priority_order;
                }

                $cached_priority_order = self::$memoization_cache[ $priority_memo_key ];

                $clauses['orderby'] = $cached_priority_order . $clauses['orderby'];
            }

            /***************************************************************************
             * Show favorites first
             ***************************************************************************
             *
             * We show favorites first on top of the list if the setting is enabled and
             * it's not limited to only show favorites. Performance optimizations have been
             * added including caching for repeated operations.
             */
            if ( $order_form->allow_favourites() && ! $order_form->only_show_favourites() ) {
                $favourites = WWOF::get_favourite_products();
                if ( ! empty( $favourites ) ) {
                    // Use memoization for favourites to avoid repeated processing within the same request.
                    $favourites_memo_key = 'favourites_' . md5( wp_json_encode( $favourites ) );

                    if ( ! isset( self::$memoization_cache[ $favourites_memo_key ] ) ) {
                        // Fallback to object cache if not in memory.
                        $favourites_cache_key    = 'wwof_favourites_' . md5( wp_json_encode( $favourites ) );
                        $cached_favourites_order = wp_cache_get( $favourites_cache_key, 'wwof_queries' );

                        if ( false === $cached_favourites_order ) {
                            $placeholders = implode( ', ', array_fill( 0, count( $favourites ), '%d' ) );
                            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
                            $cached_favourites_order = $wpdb->prepare( "FIELD($wpdb->posts.ID, $placeholders) DESC, ", $favourites );
                            wp_cache_set( $favourites_cache_key, $cached_favourites_order, 'wwof_queries', 300 );
                        }

                        // Store in memory for this request.
                        self::$memoization_cache[ $favourites_memo_key ] = $cached_favourites_order;
                    }

                    $cached_favourites_order = self::$memoization_cache[ $favourites_memo_key ];

                    $clauses['orderby'] = $cached_favourites_order . $clauses['orderby'];
                }
            }

            /***************************************************************************
             * Customize query clauses for regular queries
             ***************************************************************************
             *
             * We customize the query clauses for regular queries (individual variations
             * not enabled).
             */
            if ( '_sku' === $order_by ) {
                // Use LEFT JOIN to ensure we don't lose products when ordering by SKU.
                $clauses['join'] = "LEFT JOIN $wpdb->wc_product_meta_lookup wc_product_meta_lookup ON $wpdb->posts.ID = wc_product_meta_lookup.product_id" . $clauses['join'];

                $clauses['orderby'] = str_replace(
                    "$wpdb->posts.post_date $order",
                    "wc_product_meta_lookup.sku $order, $wpdb->posts.post_title $order",
                    $clauses['orderby']
                );
            }

            /***************************************************************************
             * Customize query clauses for popularity by period
             ***************************************************************************
             *
             * We customize the query clauses for popularity by period.
             * E.g. last 7 days, last 30 days, last 365 days.
             */
            if ( 'popularity' === $order_by ) {
                $popularity_period = $this->rest_request->get_param( 'popularity_period' ) ?
                    sanitize_text_field( $this->rest_request->get_param( 'popularity_period' ) ) : '';
                $popularity_period = ! empty( $popularity_period ) ? $popularity_period : $order_form->get_settings( 'popularityPeriod' );
                if ( ! empty( $popularity_period ) ) {
                    // Use memoization for popularity data to avoid expensive calculations within the same request.
                    $popularity_memo_key = 'popularity_' . $popularity_period . '_' . gmdate( 'Y-m-d' );

                    if ( ! isset( self::$memoization_cache[ $popularity_memo_key ] ) ) {
                        // Fallback to object cache if not in memory.
                        $popularity_cache_key = 'wwof_popularity_' . $popularity_period . '_' . gmdate( 'Y-m-d' );
                        $popular_product_ids  = wp_cache_get( $popularity_cache_key, 'wwof_queries' );

                        if ( false === $popular_product_ids ) {
                            $popularity_period   = intval( $popularity_period );
                            $popularity_args     = array(
                                'context'       => 'view',
                                'page'          => 1,
                                'per_page'      => 100,
                                'after'         => gmdate( 'Y-m-d 00:00:00', strtotime( "-$popularity_period days" ) ),
                                'before'        => gmdate( 'Y-m-d 23:59:59' ),
                                'order'         => 'desc',
                                'orderby'       => 'items_sold',
                                'match'         => 'all',
                                'extended_info' => false,
                            );
                            $popularity_args     = apply_filters( 'wwof_wc_rest_popularity_period_args', $popularity_args, $this );
                            $products_data_store = new ProductsDataStore();
                            $products_data       = $products_data_store->get_data( $popularity_args )->data;

                            if ( ! empty( $products_data ) ) {
                                $popular_product_ids = wp_list_pluck( $products_data, 'product_id' );
                                // Cache for 1 hour since popularity data doesn't change frequently.
                                wp_cache_set( $popularity_cache_key, $popular_product_ids, 'wwof_queries', 3600 );
                            } else {
                                $popular_product_ids = array();
                                wp_cache_set( $popularity_cache_key, $popular_product_ids, 'wwof_queries', 3600 );
                            }
                        }

                        // Store in memory for this request.
                        self::$memoization_cache[ $popularity_memo_key ] = $popular_product_ids;
                    }

                    $popular_product_ids = self::$memoization_cache[ $popularity_memo_key ];

                    if ( ! empty( $popular_product_ids ) ) {
                        $popular_products_placeholder = implode( ', ', array_fill( 0, count( $popular_product_ids ), '%d' ) );

                        $clauses['where'] .= $wpdb->prepare(
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
                            " AND wc_product_meta_lookup.product_id IN ($popular_products_placeholder)",
                            $popular_product_ids
                        );
                    }
                }
            }

            /***************************************************************************
             * Customize query clauses for product variations
             ***************************************************************************
             *
             * We customize the query clauses for product variations to ensure that the
             * `orderby` parameter works as expected for regular queries (not search).
             */
            if ( $this->product_variations_query ) {
                $clauses['where'] = str_replace(
                    "$wpdb->posts.post_type = 'product' ",
                    "$wpdb->posts.post_type IN ('product', 'product_variation') ",
                    $clauses['where']
                );

                // Optimize JOIN for product variations.
                $clauses['join'] .= "LEFT JOIN $wpdb->posts pv
                    ON ($wpdb->posts.post_type = 'product_variation'
                    AND $wpdb->posts.post_parent = pv.ID) ";

                $clauses['where'] = " AND ($wpdb->posts.post_type != 'product_variation' OR ($wpdb->posts.post_type = 'product_variation' AND pv.post_status = 'publish')) " . $clauses['where'];

                if ( 'menu_order' === $order_by ) {
                    $clauses['fields'] .= ",
CASE $wpdb->posts.post_type
    WHEN 'product_variation' THEN pv.menu_order
    ELSE $wpdb->posts.menu_order
END AS menu_order";

                    $clauses['orderby'] = str_replace(
                        "$wpdb->posts.menu_order",
                        'menu_order',
                        $clauses['orderby']
                    );
                }

                if ( is_a( $this->rest_request, 'WP_REST_Request' ) &&
                    (
                        ( $this->rest_request->get_param( 'category' ) && ! empty( $this->rest_request->get_param( 'category' ) ) ) ||
                        ( $this->rest_request->get_param( 'category__in' ) && ! empty( $this->rest_request->get_param( 'category__in' ) ) )
                    )
                ) {

                    $clauses['join'] .= "LEFT JOIN $wpdb->term_relationships AS wwoftr ON ($wpdb->posts.post_parent = wwoftr.object_id)";

                    $clauses['where'] = preg_replace(
                        "/($wpdb->term_relationships\.term_taxonomy_id\s+IN\s+\(([^\)]+)\))/",
                        '($1 OR wwoftr.term_taxonomy_id IN ($2))',
                        $clauses['where']
                    );
                }
            }
        }

        /**
         * Add filter to allow other plugins to customize the query clauses
         *
         * @param array $clauses The query clauses.
         * @param WC    $this    The current instance of the WC class.
         *
         * @since 3.0.1
         */
        return apply_filters( 'wwof_posts_clauses_request', $clauses, $this );
    }

    /**
     * Maybe customize the product REST query.
     *
     * @param array           $args    Array of arguments for WP_Query.
     * @param WP_REST_Request $request The REST API request.
     *
     * @since 3.0
     * @return array
     */
    public function rest_product_object_query( $args, $request ) {

        $form_id = $request->get_param( 'wwof' );
        if ( ! $form_id ) {
            return $args;
        }

        if ( $request->get_param( 'context' ) === 'edit' ) {
            $args['tax_query']  = array();
            $args['meta_query'] = array();

            return $args;
        }

        $order_form = Order_Form::get_instance( $form_id );

        $this->rest_request = $request;

        $this->product_variations_query = 'yes' === $request->get_param( 'product_variations' );

        $search_sku  = sanitize_text_field( $request->get_param( 'search_sku' ) );
        $search_tags = sanitize_text_field( $request->get_param( 'search_tags' ) );

        if ( $search_sku ) {
            /***************************************************************************
             * Replace `AND` with `OR` for SKU search
             ***************************************************************************
             *
             * Add a where clause for matching the SKU field. We override WC's customization to make SKU search an `OR`
             * instead of an `AND`.
             *
             * @see wp-content/plugins/woocommerce/includes/rest-api/Controllers/Version3/class-wc-rest-products-controller.php:285
             */
            $this->search_sku_in_product_lookup_table = $search_sku;
        }

        $show_zero_inventory      = $request->get_param( 'show_zero_inventory' );
        $show_zero_inventory_bool = function_exists( 'wc_string_to_bool' )
            ? wc_string_to_bool( (string) $show_zero_inventory )
            : ( 'yes' === $show_zero_inventory || '1' === $show_zero_inventory || 1 === $show_zero_inventory || true === $show_zero_inventory );
        // Accept "enabled" as truthy for historical frontend usage.
        $show_zero_inventory_bool = $show_zero_inventory_bool || 'enabled' === $show_zero_inventory;
        if ( ! $show_zero_inventory_bool ) {
            $args['meta_query'] = WC_Helper::add_meta_query(
                $args,
                array(
                    'key'     => '_stock_status',
                    'value'   => array( 'instock', 'onbackorder' ),
                    'compare' => 'IN',
                )
            );
        }

        $category__in = $request->get_param( 'category__in' );
        if ( ! empty( $category__in ) ) {
            $args['tax_query'] = WC_Helper::add_tax_query(
                $args,
                array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => $category__in,
                )
            );
        }

        $category__not_in = $request->get_param( 'category__not_in' );
        if ( ! empty( $category__not_in ) ) {
            $args['tax_query'] = WC_Helper::add_tax_query(
                $args,
                array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => $category__not_in,
                    'operator' => 'NOT IN',
                )
            );
        }

        $post__in = $request->get_param( 'include' );
        $post__in = ! empty( $post__in ) ? (array) $post__in : array();

        if ( empty( $post__in ) ) {
            $include_from_settings = (array) $order_form->get_settings( 'includeProducts' );
            if ( ! empty( $include_from_settings ) ) {
                $args['post__in'] = array_map( 'absint', $include_from_settings );
                $post__in         = $args['post__in'];
            }
        }

        /***************************************************************************
         * Include favourites if enabled
         ***************************************************************************
         *
         * We include favourites if this setting is enabled and it's limited to
         * only show favourites.
         */
        if ( $order_form->allow_favourites() && $order_form->only_show_favourites() ) {
            $favourites = WWOF::get_favourite_products();
            if ( ! empty( $favourites ) ) {
                $post__in         = array_values( array_unique( array_map( 'absint', (array) $favourites ) ) );
                $args['post__in'] = $post__in;
            } else {
                $post__in         = array( 0 );
                $args['post__in'] = $post__in;
            }
        }

        /***************************************************************************
         * Customize query if "show variations individually" is enabled
         ***************************************************************************
         *
         * We do further query customization if the "show variations individually"
         * option is enabled.
         */
        if ( $this->product_variations_query ) {
            $exclude = $request->get_param( 'exclude' );
            if ( ! empty( $exclude ) ) {
                $post_parent__not_in = array();
                foreach ( $exclude as $exclude_id ) {
                    $product = wc_get_product( $exclude_id );
                    if ( $product && $product->is_type( 'variable' ) ) {
                        $post_parent__not_in[] = $exclude_id;
                    }
                }
                if ( ! empty( $post_parent__not_in ) ) {
                    $args['post_parent__not_in'] = $post_parent__not_in;
                }
            }

            /***************************************************************************
             * Override `post__in` parameter
             ***************************************************************************
             *
             * We override the `post__in` parameter to include the children of the
             * products in the `post__in` parameter.
             */
            if ( ! empty( $post__in ) && ! $order_form->only_show_favourites() ) {
                $new_post__in = array();
                foreach ( $post__in as $index => $post_in ) {
                    $children = wc_get_product( $post_in )->get_children();
                    if ( ! empty( $children ) ) {
                        $new_post__in = array_merge( $new_post__in, $children );
                        unset( $post__in[ $index ] );
                    }
                }
                if ( ! empty( $new_post__in ) ) {
                    $post__in = array_values( array_merge( $post__in, $new_post__in ) );
                }
                $args['post__in'] = $post__in;
            }

            /***************************************************************************
             * Query product and product variations without parent
             ***************************************************************************
             *
             * We query for `simple` products and for variations, we query for
             * those that do not have a `product_type` only since we need to hide or not
             * include the parent product in the results for the `variable` type.
             */
            $args['tax_query'] = WC_Helper::add_tax_query(
                $args,
                array(
                    'relation' => 'OR',
                    array(
                        'taxonomy' => 'product_type',
                        'operator' => 'NOT EXISTS',
                    ),
                    array(
                        'taxonomy' => 'product_type',
                        'field'    => 'slug',
                        'terms'    => array( 'simple', 'grouped', 'external' ),
                        'operator' => 'IN',
                    ),
                )
            );
        } else {
            /***************************************************************************
             * Limit products query to certain types
             ***************************************************************************
             *
             * We limit products query to certain types.
             *
             * @since 3.0 We only support simple and variable products.
             */
            $args['tax_query'] = WC_Helper::add_tax_query(
                $args,
                array(
                    'taxonomy' => 'product_type',
                    'field'    => 'slug',
                    'terms'    => array( 'simple', 'variable', 'grouped', 'external' ),
                )
            );
        }

        /***************************************************************************
         * Query visible products only
         ***************************************************************************
         *
         * We limit products query to visible products only. Products marked as
         * `private` and/or have `hidden` catalog visibility should be excluded.
         */
        $product_visibility_terms = wc_get_product_visibility_term_ids();
        if ( ! empty( $product_visibility_terms['exclude-from-catalog'] ) ) {
            $tax_query = array(
                array(
                    'taxonomy' => 'product_visibility',
                    'field'    => 'term_taxonomy_id',
                    'terms'    => $product_visibility_terms['exclude-from-catalog'],
                    'operator' => 'NOT IN',
                ),
            );

            $args['tax_query'] = WC_Helper::add_tax_query( $args, $tax_query );
        }

        add_filter( 'posts_clauses_request', array( $this, 'replace_clauses' ), 100 );

        if ( $search_tags ) {
            unset( $args['s'] );

            // Use $search_tags instead of retrieving again.
            $tag_term = get_term_by( 'slug', $search_tags, 'product_tag' );

            if ( $tag_term && ! is_wp_error( $tag_term ) ) {
                $tag_tax_query     = array(
                    array(
                        'taxonomy' => 'product_tag',
                        'field'    => 'slug',
                        'terms'    => array( $search_tags ),
                        'operator' => 'IN',
                    ),
                );
                $args['tax_query'] = WC_Helper::add_tax_query( $args, $tag_tax_query );
            }
        }

        /**
         * Add filter to allow other plugins to customize the product REST query args
         *
         * @param array           $args    Array of arguments for WP_Query.
         * @param WP_REST_Request $request The REST API request.
         * @param WC              $this    The current instance of the WC class.
         *
         * @since 3.0.1
         */
        return apply_filters( 'wwof_wc_rest_product_object_query', $args, $request, $this );
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
            remove_filter( 'posts_clauses_request', array( $this, 'replace_clauses' ), 100 );
        }

        $this->product_variations_query = false;

        if ( ! empty( $this->search_sku_in_product_lookup_table ) ) {
            $this->search_sku_in_product_lookup_table = '';
        }

        return $response;
    }

    /**
     * Declare WooCommerce HPOS Compatibility.
     *
     * @since 3.0
     * @return void
     */
    public function declare_hpos_compatible() {

        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WWOF_PLUGIN_FILE );
        }
    }

    /**
     * Enqueue custom scripts for WooCommerce.
     *
     * @since 3.0
     * @return void
     */
    public function enqueue_script() {

        /***************************************************************************
         * Trigger custom native javascript event when product is removed from cart
         ***************************************************************************
         *
         * We are using a custom event here since we do not want to have a hard
         * dependency on jQuery. This custom event is simply a wrapper for the
         * `removed_from_cart` event that is triggered by WooCommerce via jQuery.
         * In case, the theme or third-party plugin uses, for example, the
         * mini-cart widget script (like e.g. Storefront theme can remove items
         * from the cart from their mini-cart widget icon), we have a way to listen
         * to the event without having to depend on jQuery.
         *
         * @see woocommerce/assets/js/frontend/add-to-cart.js:165
         */
        $script = <<<'JS'
(($) => {
  $(document.body).on('removed_from_cart', (event, fragments) => {
    document.body.dispatchEvent(new Event('wwof_removed_from_cart'))
  })
})(jQuery)
JS;

        wp_add_inline_script( 'wc-add-to-cart', $script );
    }

    /**
     * WooCommerce related overrides.
     *
     * @since 3.0
     */
    public function run() {

        /***************************************************************************
         * Declare WooCommerce HPOS Compatibility
         ***************************************************************************
         *
         * We declare WooCommerce HPOS compatibility to allow HPOS to work with
         * WooCommerce Wholesale Order Form. We aren't doing anything much custom
         * code related to WooCommerce stuff as we are just using the default
         * WooCommerce and WWP REST API.
         */
        add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatible' ) );

        /**************************************************************************
         * Override WC REST API authentication
         **************************************************************************
         *
         * We override the default WooCommerce REST API authentication to run
         * requests in the context of the current user instead of requiring
         * api/secret keys.
         */
        add_filter( 'woocommerce_api_check_authentication', array( $this, 'wc_api_auth_as_current_user' ), 100 );

        /**************************************************************************
         * Override WC REST API Permissions
         **************************************************************************
         *
         * We override the default WooCommerce REST API permissions to allow
         * requests to be made by any user or wholesale customers only.
         */
        add_filter( 'woocommerce_rest_check_permissions', array( $this, 'wc_rest_check_permissions' ), 100, 4 );

        /**************************************************************************
         * Add Custom Properties to WC Product REST API Response
         **************************************************************************
         *
         * We add a filter to the WC REST API response to add custom properties
         * to the response for products query.
         *
         * For the filter hook, see:
         * wp-content/plugins/woocommerce/includes/rest-api/Controllers/Version3/class-wc-rest-crud-controller.php:340
         * wp-content/plugins/woocommerce/includes/rest-api/Controllers/Version2/class-wc-rest-products-v2-controller.php:190
         */
        add_filter( 'woocommerce_rest_prepare_product_object', array( $this, 'rest_prepare_product_object' ), 100, 3 );

        /**************************************************************************
         * Add custom properties to WC Product Variation REST API Response.
         **************************************************************************
         *
         * We add a filter to the WC REST API response to add custom properties
         * to the response for product variations query.
         *
         * For the filter hook, see:
         *
         * @see wp-content/plugins/woocommerce/includes/rest-api/Controllers/Version2/class-wc-rest-product-variations-v2-controller.php:236
         */
        add_filter(
            'woocommerce_rest_prepare_product_variation_object',
            array( $this, 'rest_prepare_product_variation_object' ),
            100,
            3
        );

        /**************************************************************************
         * Add custom params to WC Product REST API.
         **************************************************************************
         *
         * We add custom params to the WC REST API to be used for querying products.
         *
         * For the filter hook, see:
         *
         * @see wp-content/plugins/woocommerce/includes/rest-api/Controllers/Version3/class-wc-rest-crud-controller.php:691
         */
        add_filter( 'rest_product_collection_params', array( $this, 'rest_product_collection_params' ), 100 );

        /**************************************************************************
         * Maybe customize REST API response for product post type
         **************************************************************************
         *
         * We add a filter to WP REST API query to customize query params for
         * product post type.
         *
         * For the filter hook, see:
         *
         * @see wp-content/plugins/woocommerce/includes/rest-api/Controllers/Version3/class-wc-rest-crud-controller.php:340
         */
        add_filter( 'woocommerce_rest_product_object_query', array( $this, 'rest_product_object_query' ), 100, 2 );

        /***************************************************************************
         * Product search query clauses customization
         ***************************************************************************
         *
         * We add a filter to WP REST API query to customize query clauses for
         * product search.
         *
         * We are adding a separate `posts_clauses_request` filter here for search
         * as it seems that WC is firing 2 queries for search. One for the main
         * query and another for the search query (which seems to have identical
         * query statement). We are not sure why this is happening but, we are
         * adding the filter here just in case.
         */
        add_filter( 'posts_clauses_request', array( $this, 'search_post_clauses_request' ), 100 );

        /***************************************************************************
         * Remove custom handlers added to WP_Query
         ***************************************************************************
         *
         * Let's make sure to cleanup after ourselves and remove the custom handlers
         * we added to the WP_Query.
         */
        add_filter( 'rest_request_after_callbacks', array( $this, 'remove_wp_query_custom_handlers' ), 100 );

        /***************************************************************************
         * Enqueue custom scripts
         ***************************************************************************
         *
         * Enqueue custom scripts for WooCommerce.
         */
        add_action( 'wwof_enqueue_wwof_product_listing_sc_script', array( $this, 'enqueue_script' ) );

        /**
         * If a product is restricted, then run this.
         */
        add_filter( 'wwp_rest_wholesale_product_meta_query', array( $this, 'unrestrict_product_for_admin_and_shop_manager' ), 9, 4 );

        /**
         * Sort the products based on price column filter
         */
        add_action( 'wwp_after_product_get_items', array( $this, 'sort_products_by_price' ), 10, 2 );

        /**************************************************************************
         * Add GTIN (global_unique_id) to WC Product REST API Response
         **************************************************************************
         *
         * We expose the `global_unique_id` field (GTIN) stored as `_global_unique_id`
         * private meta (introduced in WooCommerce 9.2+) in the REST API response for
         * both products and product variations.
         */
        add_filter( 'woocommerce_rest_prepare_product_object', array( $this, 'add_product_gtin_to_rest_response' ), 100, 3 );
        add_filter( 'woocommerce_rest_prepare_product_variation_object', array( $this, 'add_product_gtin_to_rest_response' ), 100, 3 );
    }


    /**
     * Callback for sorting the products by price filter column.
     *
     * @since 3.0.8
     *
     * @param object $request Request object.
     * @param object $response Response object.
     *
     * @return void
     */
    public function sort_products_by_price( $request, $response ) {
        $query_params                = $request->get_params();
        $order                       = isset( $query_params['order'] ) ?
            $query_params['order'] : 'asc';
        $order_by                    = isset( $query_params['orderby'] ) ?
            $query_params['orderby'] : 'popularity';
        $current_user_wholesale_role = class_exists( 'WWPP_Wholesale_Roles' ) && ! empty( WWPP_Wholesale_Roles::instance()->getUserWholesaleRole() ) ?
            WWPP_Wholesale_Roles::instance()->getUserWholesaleRole()
            : array( 'wholesale_customer' );

        $get_wholesale_price = function ( $product, $role ) {
            $wholesale_data = isset( $product['wholesale_data']['wholesale_price'] ) && ! empty( $product['wholesale_data']['wholesale_price'] )
                ? $product['wholesale_data']['wholesale_price']
                : array();
            return isset( $wholesale_data[ $role ] )
                ? (float) $wholesale_data[ $role ]
                : ( isset( $product['price'] ) ? (float) $product['price'] : 0.0 );
        };

        if ( ( 'price' === $order_by ) ) {
            $existing_products = $response->data ?? array();

            usort(
                $existing_products,
                function ( $product_one, $product_two ) use ( $current_user_wholesale_role, $order, $get_wholesale_price ) {
                    $product_one_wholesale_price = $get_wholesale_price( $product_one, $current_user_wholesale_role[0] );
                    $product_two_wholesale_price = $get_wholesale_price( $product_two, $current_user_wholesale_role[0] );

                    if ( 'asc' === $order ) {
                        return $product_one_wholesale_price <=> $product_two_wholesale_price;
                    } else {
                        return $product_two_wholesale_price <=> $product_one_wholesale_price;
                    }
                }
            );

            $response->data = $existing_products;
        }
    }

    /**
     * Callback for unrestricting 'admin/shop manager' from viewing restricted category.
     *
     * @since 3.0.8
     *
     * @param array  $meta_query Meta query.
     * @param string $wholesale_role Wholesale role.
     * @param array  $args_copy Args Array.
     * @param object $request Request object.
     *
     * @return array
     */
    public function unrestrict_product_for_admin_and_shop_manager( $meta_query, $wholesale_role, $args_copy, $request ) {
        // Allow admins and shop managers to view all products, bypassing restrictions.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return $meta_query;
        }

        global $wc_wholesale_prices_premium;

        // Check if WWPP is active and has the required API structure.
        if ( null === $wc_wholesale_prices_premium
            || ! isset( $wc_wholesale_prices_premium->wwpp_rest_api->wwpp_rest_api_wholesale_products_controller )
        ) {
            return $meta_query;
        }

        remove_filter(
            'wwp_rest_wholesale_product_meta_query',
            array(
                $wc_wholesale_prices_premium->wwpp_rest_api->wwpp_rest_api_wholesale_products_controller,
                'rest_meta_query_args',
            ),
            10,
            4
        );

        return $meta_query;
    }

    /**
     * Clear the memoization cache.
     *
     * @since 3.0.8 Added this method to clear the memoization cache.
     * @return void
     */
    public static function clear_memoization_cache() {
        self::$memoization_cache = array();
    }

    /**
     * Add the GTIN (global_unique_id) field to the WC REST API product response.
     *
     * WooCommerce 9.2+ stores GTIN as the `_global_unique_id` private meta field.
     * We expose it as `global_unique_id` in the REST response so the frontend
     * order form can display it in the GTIN column element.
     *
     * @param WP_REST_Response $response The response object.
     * @param WC_Product       $wc_data  Object data.
     * @param WP_REST_Request  $request  Request object.
     *
     * @since 3.0
     * @return WP_REST_Response
     */
    public function add_product_gtin_to_rest_response( $response, $wc_data, $request ) {

        if ( ! $request->get_param( 'wwof' ) || $request->get_param( 'context' ) === 'edit' ) {
            return $response;
        }

        /***************************************************************************
         * Add `global_unique_id` property to product object
         ***************************************************************************
         *
         * We add the `global_unique_id` property (GTIN) to the REST response.
         * The `get_global_unique_id()` method was introduced in WooCommerce 9.2,
         * so we check for its existence first for backwards compatibility.
         */
        if ( method_exists( $wc_data, 'get_global_unique_id' ) ) {
            $response->data['global_unique_id'] = $wc_data->get_global_unique_id();
        } else {
            $response->data['global_unique_id'] = get_post_meta( $wc_data->get_id(), '_global_unique_id', true );
        }

        return $response;
    }
}
