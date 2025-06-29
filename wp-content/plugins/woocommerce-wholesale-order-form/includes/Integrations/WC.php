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
            $parent_data = $wc_data->get_parent_data();
            $object_data = $wc_data->get_data();
            if ( empty( $object_data['sku'] ) && $response->data['sku'] === $parent_data['sku'] ) {
                $response->data['sku'] = '';
            }
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
     * @return array
     */
    public function search_post_clauses_request( $clauses ) {

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
            $clauses['join'] = preg_replace(
                "/LEFT JOIN {$wpdb->prefix}wc_product_meta_lookup wc_product_meta_lookup\s*ON $wpdb->posts\.ID = wc_product_meta_lookup\.product_id/",
                '',
                $clauses['join']
            );
            $clauses['join'] = " LEFT JOIN {$wpdb->prefix}wc_product_meta_lookup wc_product_meta_lookup ON {$wpdb->prefix}posts.ID = wc_product_meta_lookup.product_id " . $clauses['join'];

            $clauses['where'] = str_replace(
                ") OR ($wpdb->posts.post_content LIKE",
                $wpdb->prepare( ") OR (wc_product_meta_lookup.sku LIKE %s) OR ($wpdb->posts.post_content LIKE", '%' . $wpdb->esc_like( $this->rest_request->get_param( 'search' ) ) . '%' ),
                $clauses['where']
            );
            $clauses['where'] = preg_replace( '/ AND \(wc_product_meta_lookup.sku LIKE([^)]+)\)/', '', $clauses['where'] );

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
        }

        return $clauses;
    }

    /**
     * Customize query clauses.
     *
     * @param array $clauses Array of clauses.
     *
     * @since 3.0
     * @return array
     */
    public function replace_clauses( $clauses ) {

        global $wpdb;

        if ( defined( 'REST_REQUEST' ) && is_a( $this->rest_request, 'WP_REST_Request' ) && ! $this->search_sku_in_product_lookup_table ) {
            $order_by = sanitize_text_field( $this->rest_request->get_param( 'orderby' ) );
            $order    = sanitize_text_field( $this->rest_request->get_param( 'order' ) );
            $order    = strtoupper( $order );

            $order_form = Order_Form::get_instance( $this->rest_request->get_param( 'wwof' ) );

            /***************************************************************************
             * Show favourites first
             ***************************************************************************
             *
             * We show favourites first on top of the list if the setting is enabled and
             * it's not limited to only show favourites.
             */
            if ( $order_form->allow_favourites() && ! $order_form->only_show_favourites() ) {
                $favourites = WWOF::get_favourite_products();
                if ( ! empty( $favourites ) ) {
                    $placeholders = implode( ', ', array_fill( 0, count( $favourites ), '%d' ) );
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
                    $clauses['orderby'] = $wpdb->prepare( "FIELD($wpdb->posts.ID, $placeholders) DESC, ", $favourites ) . $clauses['orderby'];
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
                $clauses['join'] = "
LEFT JOIN $wpdb->wc_product_meta_lookup wc_product_meta_lookup ON $wpdb->posts.ID = wc_product_meta_lookup.product_id" .
                    $clauses['join'];

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
                $popularity_period = sanitize_text_field( $this->rest_request->get_param( 'popularity_period' ) );
                $popularity_period = ! empty( $popularity_period ) ? $popularity_period : $order_form->get_settings( 'popularityPeriod' );
                if ( ! empty( $popularity_period ) ) {
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
                        $popular_product_ids          = wp_list_pluck( $products_data, 'product_id' );
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

                $clauses['join'] .= "
LEFT JOIN $wpdb->posts pv ON ($wpdb->posts.post_type = 'product_variation' AND $wpdb->posts.post_parent = pv.ID) ";

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
                    ( $this->rest_request->get_param( 'category' ) || $this->rest_request->get_param( 'category__in' ) ) ) {

                    $clauses['join'] .= "
LEFT JOIN $wpdb->term_relationships AS wwoftr ON ($wpdb->posts.post_parent = wwoftr.object_id)";

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

        $search_sku = sanitize_text_field( $request->get_param( 'search_sku' ) );
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

        $show_zero_inventory = $request->get_param( 'show_zero_inventory' );
        if ( empty( $show_zero_inventory ) || 'yes' !== $show_zero_inventory ) {
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

        /***************************************************************************
         * Include favourites if enabled
         ***************************************************************************
         *
         * We include favourites if this setting is enabled and it's limited to
         * only show favourites.
         */
        if ( $order_form->only_show_favourites() ) {
            $favourites = WWOF::get_favourite_products();
            if ( ! empty( $favourites ) ) {
                $post__in = array_merge( $post__in, $favourites );
                $post__in = array_flip( array_flip( $post__in ) );
            }
            if ( empty( $post__in ) ) {
                $post__in = array( 0 );
            }
            $args['post__in'] = $post__in;
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
                        'terms'    => array( 'simple' ),
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
                    'terms'    => array( 'simple', 'variable' ),
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
        $args['tax_query'] = WC_Helper::add_tax_query(
            $args,
            array(
                'relation' => 'OR',
                array(
                    'taxonomy'         => 'product_visibility',
                    'operator'         => 'NOT EXISTS',
                    'include_children' => false,
                ),
                array(
                    'relation' => 'AND',
                    array(
                        'taxonomy'         => 'product_visibility',
                        'terms'            => 'exclude-from-catalog',
                        'field'            => 'name',
                        'operator'         => 'NOT IN',
                        'include_children' => false,
                    ),
                    array(
                        'taxonomy'         => 'product_visibility',
                        'terms'            => 'exclude-from-search',
                        'field'            => 'name',
                        'operator'         => 'IN',
                        'include_children' => false,
                    ),
                ),
                array(
                    'relation' => 'AND',
                    array(
                        'taxonomy'         => 'product_visibility',
                        'terms'            => 'exclude-from-catalog',
                        'field'            => 'name',
                        'operator'         => 'IN',
                        'include_children' => false,
                    ),
                    array(
                        'taxonomy'         => 'product_visibility',
                        'terms'            => 'exclude-from-search',
                        'field'            => 'name',
                        'operator'         => 'NOT IN',
                        'include_children' => false,
                    ),
                ),
                array(
                    'taxonomy'         => 'product_visibility',
                    'terms'            => array( 'exclude-from-catalog', 'exclude-from-search' ),
                    'field'            => 'name',
                    'operator'         => 'NOT IN',
                    'include_children' => false,
                ),
            )
        );

        add_filter( 'posts_clauses_request', array( $this, 'replace_clauses' ), 100 );

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
        $script = <<<JS
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
    }
}
