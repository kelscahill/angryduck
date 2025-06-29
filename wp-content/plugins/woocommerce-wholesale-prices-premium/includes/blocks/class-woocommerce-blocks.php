<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWPP_WC_Blocks' ) ) {

    /**
     * Model that houses the logic relating WWPP_WC_Blocks.
     *
     * @since 1.23.9
     */
    class WWPP_WC_Blocks {

        /**
         * Class Properties
         */

        /**
         * Property that holds the single main instance of WWPP_WC_Blocks.
         *
         * @since 1.23.9
         * @access private
         * @var WWPP_WC_Blocks
         */
        private static $_instance;

        /**
         * Model that houses the logic of filtering on woocommerce query.
         *
         * @since 1.27.9
         * @access private
         * @var WWPP_Query
         */
        private $_wwpp_query;

        /**
         * Model that houses the logic of retrieving information relating to wholesale role/s of a user.
         *
         * @since 1.28
         * @access private
         * @var WWPP_Query
         */
        private $_wwpp_wholesale_roles;

        /*
        |--------------------------------------------------------------------------
        | Class Methods
        |--------------------------------------------------------------------------
        */

        /**
         * WWPP_WC_Blocks constructor.
         *
         * @since 1.23.9
         * @since 1.28   Added WWPP_Query and WWPP_Wholesale_Roles dependency
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_WC_Blocks model.
         */
        public function __construct( $dependencies ) {
            $this->_wwpp_query           = $dependencies['WWPP_Query'];
            $this->_wwpp_wholesale_roles = $dependencies['WWPP_Wholesale_Roles'];
        }

        /**
         * Ensure that only one instance of WWPP_WC_Blocks is loaded or can be loaded (Singleton Pattern).
         *
         * @since 1.23.9
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_WC_Blocks model.
         * @return WWPP_WC_Blocks
         */
        public static function instance( $dependencies ) {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }
        /**
         * Visibility check for WC Blocks.
         *
         * @since 1.23.9
         * @deprecated Deprecated on 1.27.9 The filter below is only to hide the fetched products, not actually try to modify the query to get only wholesale products.
         * @access public
         *
         * @param array  $html       HTML Format.
         * @param array  $data       Blocks Data.
         * @param object $product    WC_Product Object.
         * @return WWPP_WC_Blocks
         */
        public function grid_item( $html, $data, $product ) {

            $user = wp_get_current_user();

            // Perform restrictions on frontend for non admin people
            // This filter will also trigger on wc blocks for the preview.
            if ( ! in_array( 'administrator', $user->roles, true ) ) {

                global $wc_wholesale_prices_premium;

                $user_wholesale_role               = $wc_wholesale_prices_premium->wwpp_wholesale_roles->getUserWholesaleRole();
                $wholesale_role                    = isset( $user_wholesale_role[0] ) ? $user_wholesale_role[0] : '';
                $product_cat_ids                   = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'ids' ) );
                $product_cat_wholesale_role_filter = get_option( WWPP_OPTION_PRODUCT_CAT_WHOLESALE_ROLE_FILTER );

                // Product Level Visibility filter.
                $visibility = WWP_Helper_Functions::get_formatted_meta_data( $product, 'wwpp_product_wholesale_visibility_filter' );

                if ( ! empty( $visibility ) && ! in_array( 'all', $visibility, true ) && ! in_array( $wholesale_role, $visibility, true ) ) {
                    return '';
                }

                if ( $wholesale_role ) {

                    if ( ! empty( $product_cat_wholesale_role_filter ) ) {

                        $filtered_terms_ids = array();

                        foreach ( $product_cat_wholesale_role_filter as $cat_id => $filtered_wholesale_roles ) {
                            if ( ! in_array( $wholesale_role, $filtered_wholesale_roles, true ) ) {
                                $filtered_terms_ids[] = $cat_id;
                            }
                        }

                        // Don't show restricted products in category level for visitors.
                        if ( count( array_intersect( $product_cat_ids, $filtered_terms_ids ) ) > 0 ) {
                            return '';
                        }
                    }

                    // Dont show non-wholesale products
                    // @since 1.27.9 - moved the code outside conditional above, due to the restriction is only works when te products is resctricted in category level if we put this inside the conditioonal.
                    if ( get_option( 'wwpp_settings_only_show_wholesale_products_to_wholesale_users', false ) === 'yes' ) {

                        $wholesale_price = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $product->get_id(), $user_wholesale_role );

                        if ( $product->get_type() === 'simple' && empty( $wholesale_price['wholesale_price'] ) ) {
                            return '';
                        } elseif ( $product->get_meta( $wholesale_role . '_have_wholesale_price', true ) !== 'yes' ) {
                            return '';
                        }
                    }
                } else {

                    $restricted_cat_for_regular_users = array();

                    if ( ! is_array( $product_cat_wholesale_role_filter ) ) {
                        $restricted_cat_for_regular_users = array();
                    } else {
                        foreach ( $product_cat_wholesale_role_filter as $cat_id => $role ) {
                            $restricted_cat_for_regular_users[] = $cat_id;
                        }
                    }

                    // Don't show restricted products in category level for visitors.
                    if ( count( array_intersect( $product_cat_ids, $restricted_cat_for_regular_users ) ) > 0 ) {
                        return '';
                    }
                }
            }

            return $html;
        }

        /**
         * WWPP Visibility check for WC Blocks.
         * To inject a custom query vars for woocommerce product blocks we can do this via the parse_query filter.
         * See the docs on \woocommerce\packages\woocommerce-blocks\src\Utils\BlocksWpQuery.php
         *
         * @since 1.27.9
         * @access public
         *
         * @param object $wp_query   WP_Query Object.
         * @return WP_Query
         */
        public function woocommerce_product_blocks_parse_query( $wp_query ) {
            // Make sure only runs if the query is being executed via `BlocksWpQuery` class.
            $reflect = new \ReflectionClass( $wp_query );
            if ( $reflect->getShortName() === 'BlocksWpQuery' ) {

                // Inject WWPP query custom query for product visibility.
                $wp_query->query_vars = $this->_wwpp_query->pre_get_posts_arg( $wp_query->query_vars );

            }

            return $wp_query;
        }

        /**
         * Apply "Wholesale Minimum Order Quantity" when adding product to cart via product blocks.
         * The WooCommerce product blocks is lack of hook to modify the Add to Cart button attributes unlike the default product behavior.
         * The workaround is, we can use the `woocommerce_blocks_product_grid_item_html` filter and then we modify the `data-quantity` attributes in the button html using PHP HTML DOM Parser.
         * See the `get_add_to_cart` function in woocommerce\packages\woocommerce-blocks\src\BlockTypes\AbstractProductGrid.php
         *
         * @since 1.28
         * @access public
         *
         * @param array  $html       HTML Format.
         * @param array  $data       Blocks Data.
         * @param object $product    WC_Product Object.
         * @return          $html
         */
        public function apply_minimum_order_quantity_on_product_blocks( $html, $data, $product ) {

            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            if ( ! empty( $user_wholesale_role ) && $product->get_type() !== 'variable' ) {

                $minimum_order_qty = $product->get_meta( $user_wholesale_role[0] . '_wholesale_minimum_order_quantity', true );

                if ( $minimum_order_qty &&
                    is_numeric( $minimum_order_qty )
                ) {

                    // Set the data-quantity attribute to the $html variable html markup.
                    $dom = new DOMDocument();
                    $dom->loadHTML( $html, LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED );

                    $finder      = new DomXPath( $dom );
                    $cart_button = $finder->query( "//*[contains(@class, 'wp-block-button__link') and contains(@class, 'add_to_cart_button') ]" );

                    if ( $cart_button instanceof DOMNodeList ) {
                        foreach ( $cart_button as $domCartButton ) {
                            $domCartButton->setAttribute( 'data-quantity', $minimum_order_qty );
                        }
                    }

                    // Adds `data-quantity` property on the add to cart button with product min order quantity value
                    // Example: <a href="" data-quantity="value">.
                    $new_html = $dom->saveHTML();

                    return apply_filters( 'wwpp_min_order_quantity_on_product_blocks', $new_html, $html, $data, $product );

                }
            }

            return $html;
        }

        /*
        |-------------------------------------------------------------------------------------------------------------------
        | Execute Model
        |-------------------------------------------------------------------------------------------------------------------
        */

        /**
         * Execute model.
         *
         * @since 1.23.9
         * @access public
         */
        public function run() {

            // Deprecated @since 1.27.9 due to this filter only to hide the fetched products, not actually modify the query to restrict wholesale products.
            // add_filter( 'woocommerce_blocks_product_grid_item_html' , array( $this , 'grid_item' ) , 10 , 3 );.

            add_filter( 'parse_query', array( $this, 'woocommerce_product_blocks_parse_query' ), 10, 1 );

            add_filter( 'woocommerce_blocks_product_grid_item_html', array( $this, 'apply_minimum_order_quantity_on_product_blocks' ), 10, 3 );
        }
    }
}
