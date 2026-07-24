<?php
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Automattic\WooCommerce\Utilities\OrderUtil;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWPP_WC_Order' ) ) {

    /**
     * Model that houses the logic of WWPP integration with WC orders.
     *
     * @since 1.14.0
     * @since 2.1.0 Recalculate wc_order_stats.tax_total from order tax line items so wholesale discount fees do not leak into Analytics tax figures.
     */
    class WWPP_WC_Order {

        /**
         * Class Properties
         */

        /**
         * Property that holds the single main instance of WWPP_WC_Order.
         *
         * @since 1.14.0
         * @access private
         * @var WWPP_WC_Order
         */
        private static $_instance;

        /**
         * Model that houses the logic of retrieving information relating to wholesale role/s of a user.
         *
         * @since 1.14.0
         * @access private
         * @var WWPP_Wholesale_Roles
         */
        private $_wwpp_wholesale_roles;

        /**
         * Class Methods
         */

        /**
         * WWPP_WC_Order constructor.
         *
         * @since 1.14.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_WC_Order model.
         */
        public function __construct( $dependencies ) {

            $this->_wwpp_wholesale_roles = $dependencies['WWPP_Wholesale_Roles'];
        }

        /**
         * Ensure that only one instance of WWPP_WC_Order is loaded or can be loaded (Singleton Pattern).
         *
         * @since 1.14.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_WC_Order model.
         * @return WWPP_WC_Order
         */
        public static function instance( $dependencies ) {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Custom Wholesale Order Thank You Message
         */

        /**
         * Add custom thank you message to thank you page after successful order.
         *
         * @since 1.0.0
         * @since 1.7.4 Only applies to wholesale users now.
         * @since 1.14.0 Refactor codebase and move to its proper model.
         * @access public
         *
         * @param string $orig_msg Original order completed thank you message.
         * @return string Filtered original order completed thank you message.
         */
        public function custom_thank_you_message( $orig_msg ) {

            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            if ( ! empty( $user_wholesale_role ) ) {

                $new_msg = html_entity_decode( trim( get_option( 'wwpp_settings_thankyou_message' ) ) );

                if ( strcasecmp( $new_msg, '' ) !== 0 ) {

                    $pos = get_option( 'wwpp_settings_thankyou_message_position' );

                    switch ( $pos ) {

                        case 'append':
                            return $orig_msg . '<br>' . $new_msg;
                        case 'prepend':
                            return $new_msg . '<br>' . $orig_msg;
                        default:
                            return $new_msg;

                    }
                }
            }

            return $orig_msg;
        }




        /*
        |------------------------------------------------------------------------------------------------------------------
        | WC Order Custom Column
        |------------------------------------------------------------------------------------------------------------------
        */

        /**
         * Add custom column to order listing page.
         *
         * @since 1.0.0
         * @since 1.14.0 Refactor codebase and move to its own model.
         * @access public
         *
         * @param array $columns Orders cpt listing columns.
         * @return array Filtered orders cpt listing columns.
         */
        public function add_orders_listing_custom_column( $columns ) {

            $arrayKeys = array_keys( $columns );
            $lastIndex = $arrayKeys[ count( $arrayKeys ) - 1 ];
            $lastValue = $columns[ $lastIndex ];
            array_pop( $columns );

            $columns['wwpp_order_type'] = __( 'Order Type', 'woocommerce-wholesale-prices-premium' );

            $columns[ $lastIndex ] = $lastValue;

            return $columns;
        }

        /**
         * Add content to the custom column on order listing page.
         *
         * @since 1.0.0
         * @since 1.14.0 Refactor codebase and move to its own model.
         * @access public
         *
         * @param string $column  Current column key.
         * @param int    $post_id Current post id.
         */
        public function add_orders_listing_custom_column_content( $column, $post_id ) {

            $allRegisteredWholesaleRoles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();

            if ( 'wwpp_order_type' === $column ) {

                $order      = wc_get_order( $post_id );
                $order_type = $order->get_meta( '_wwpp_order_type', true );

                if ( '' === $order_type || null === $order_type || false === $order_type || 'retail' === $order_type ) {

                    esc_html_e( 'Retail', 'woocommerce-wholesale-prices-premium' );

                } elseif ( 'wholesale' === $order_type ) {

                    $wholesale_order_type = $order->get_meta( '_wwpp_wholesale_order_type', true );
                    /* translators: %1$s: Wholesale role name */
                    printf( esc_html__( 'Wholesale (%1$s)', 'woocommerce-wholesale-prices-premium' ), esc_html( $allRegisteredWholesaleRoles[ $wholesale_order_type ]['roleName'] ) );

                }
            }
        }

        /*
        |------------------------------------------------------------------------------------------------------------------
        | WWPP order type meta
        |------------------------------------------------------------------------------------------------------------------
        */

        /**
         * Attach custom meta to orders ( the order type metadata ) to be used later for filtering orders by order type
         * on the order listing page.
         *
         * @since 1.0.0
         * @since 1.14.0 Refactor codebase and move to its own model.
         * @since 1.30.4 Compatibility with checkout blocks.
         * @access public
         *
         * @param int|WC_Order $order_id_or_order_object Order id or order object.
         *                                               If the classic checkout is being used this will contains order ID.
         *                                               On checkout block it will contains order object.
         */
        public function add_order_type_meta_to_wc_orders( $order_id_or_order_object ) {

            $all_registered_wholesale_roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();
            $current_order                  = ( $order_id_or_order_object instanceof WC_Order ) ? $order_id_or_order_object : wc_get_order( $order_id_or_order_object );
            $current_order_wp_user          = get_userdata( $current_order->get_user_id() );
            $current_order_user_roles       = array();

            if ( $current_order_wp_user ) {
                $current_order_user_roles = $current_order_wp_user->roles;
            }

            if ( ! is_array( $current_order_user_roles ) ) {
                $current_order_user_roles = array();
            }

            $all_registered_wholesale_roles_keys = array();
            foreach ( $all_registered_wholesale_roles as $roleKey => $role ) {
                $all_registered_wholesale_roles_keys[] = $roleKey;
            }

            $orderUserWholesaleRole = array_values( array_intersect( $current_order_user_roles, $all_registered_wholesale_roles_keys ) );

            if ( isset( $orderUserWholesaleRole[0] ) ) {

                $current_order->update_meta_data( '_wwpp_order_type', 'wholesale' );
                $current_order->update_meta_data( '_wwpp_wholesale_order_type', $orderUserWholesaleRole[0] );

            } else {

                $current_order->update_meta_data( '_wwpp_order_type', 'retail' );
                $current_order->update_meta_data( '_wwpp_wholesale_order_type', '' );

            }

            // Invalidate wwpp reports cache when order is created.
            WWPP_Cache::instance( array() )->invalidate_reports_cache();

            $current_order->save();
        }

        /**
         * Recalculate the analytics tax_total from order tax line items.
         *
         * WWPP applies the cart subtotal-based wholesale discount as a negative cart fee. In WooCommerce versions
         * with synchronous analytics sync, an intermediate order save can persist the discount amount into the
         * wc_order_stats.tax_total column, inflating Analytics tax figures. Recalculating tax_total directly from
         * the order's tax line items at write time keeps the analytics value aligned with the actual tax collected,
         * regardless of when the sync fires.
         *
         * @since 2.1.0
         * @access public
         *
         * @param array                    $data  Data written to the wc_order_stats lookup table.
         * @param WC_Order|WC_Order_Refund $order Order object being synced; refunds are passed through unchanged.
         *
         * @return array Filtered data with tax_total recalculated from tax line items.
         */
        public function recalculate_analytics_tax_total( $data, $order ) {

            // WC_Order_Refund extends WC_Abstract_Order (not WC_Order), so refunds skip this branch and let WooCommerce's refund-specific tax_total handling stand.
            if ( ! $order instanceof WC_Order ) {
                return $data;
            }

            $tax_items = $order->get_items( 'tax' );

            // Orders created outside normal checkout (CSV/migration imports, REST POST /orders, some tax plugins)
            // can store total_tax directly without per-rate tax line items. With nothing to recompute from, leave
            // WooCommerce core's already-populated value untouched rather than zeroing it.
            if ( empty( $tax_items ) ) {
                return $data;
            }

            $tax_total = 0.0;

            foreach ( $tax_items as $tax_item ) {
                $tax_total += (float) $tax_item->get_tax_total()
                            + (float) $tax_item->get_shipping_tax_total();
            }

            $data['tax_total'] = (float) wc_format_decimal( $tax_total );

            return $data;
        }

        /**
         * Add custom filter on order listing page ( order type filter ).
         *
         * @since 1.0.0
         * @since 1.14.0 Refactor codebase and move to its own model.
         * @since 1.30.3 Add compatibility with HPOS.
         *
         * @access public
         *
         * @param string|null $order_type  The order type.
         */
        public function add_wholesale_role_order_listing_filter( $order_type = null ) {
            // phpcs:disable WordPress.Security.NonceVerification
            global $typenow;

            $screen = wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
                ? $order_type
                : $typenow;

            $all_registered_wholesale_roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();

            if ( 'shop_order' === $screen ) {

                ob_start();

                $wwpp_fbwr = null;
                if ( isset( $_GET['wwpp_fbwr'] ) ) {
                    $wwpp_fbwr = $_GET['wwpp_fbwr']; //phpcs:ignore
                }

                $all_registered_wholesale_roles = array( 'all_wholesale_orders' => array( 'roleName' => __( 'All Wholesale Orders', 'woocommerce-wholesale-prices-premium' ) ) ) + $all_registered_wholesale_roles;
                $all_registered_wholesale_roles = array( 'all_retail_orders' => array( 'roleName' => __( 'All Retail Orders', 'woocommerce-wholesale-prices-premium' ) ) ) + $all_registered_wholesale_roles;
                $all_registered_wholesale_roles = array( 'all_order_types' => array( 'roleName' => __( 'Show all order types', 'woocommerce-wholesale-prices-premium' ) ) ) + $all_registered_wholesale_roles;
                ?>

                <select name="wwpp_fbwr" id="filter-by-wholesale-role" class="chosen_select">

                    <?php foreach ( $all_registered_wholesale_roles as $roleKey => $role ) { ?>
                        <option value="<?php echo esc_attr( $roleKey ); ?>" <?php echo ( $roleKey === $wwpp_fbwr ) ? 'selected' : ''; ?>><?php echo esc_html( $role['roleName'] ); ?></option>
                    <?php } ?>

                </select>

                <?php
                echo ob_get_clean(); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

            }
            // phpcs:enable WordPress.Security.NonceVerification
        }

        /**
         * Add functionality to the custom filter added on order listing page ( order type filter ).
         *
         * @since 1.0.0
         * @since 1.14.0 Refactor codebase and move to its own model.
         * @access public
         *
         * @param WP_Query $query WP_Query object.
         */
        public function wholesale_role_order_listing_filter( $query ) {
            // phpcs:disable WordPress.Security.NonceVerification
            global $pagenow;
            $wholesale_filter = null;

            if ( isset( $_GET['wwpp_fbwr'] ) ) {
                $wholesale_filter = trim( $_GET['wwpp_fbwr'] ); //phpcs:ignore
            }

            if ( ! OrderUtil::custom_orders_table_usage_is_enabled() &&
                'edit.php' === $pagenow &&
                isset( $query->query_vars['post_type'] ) &&
                'shop_order' === $query->query_vars['post_type']
            ) {

                switch ( $wholesale_filter ) {

                    case null:
                        // Do nothing.
                        break;

                    case 'all_order_types':
                        // Do nothing.
                        break;

                    case 'all_retail_orders':
                        $query->set(
                            'meta_query',
                            array(
                                'relation' => 'AND',
                                array(
                                    array(
                                        'relation' => 'OR',
                                        array(
                                            'key'     => '_wwpp_order_type',
                                            'value'   => array( 'retail' ),
                                            'compare' => 'IN',
                                        ),
                                        array(
                                            'key'     => '_wwpp_order_type',
                                            'value'   => 'gebbirish', // Pre WP 3.9 bug, must set string for NOT EXISTS to work.
                                            'compare' => 'NOT EXISTS',
                                        ),
                                    ),
                                ),
                            )
                        );

                        if ( ! empty( $_GET['_customer_user'] ) ) {
                            $query->query_vars['meta_query'][] = array(
                                'key'     => '_customer_user',
                                'value'   => $_GET['_customer_user'], //phpcs:ignore
                                'compare' => '=',
                            );
                        }

                        break;

                    case 'all_wholesale_orders':
                        $query->query_vars['meta_key']   = '_wwpp_order_type';
                        $query->query_vars['meta_value'] = 'wholesale';

                        break;

                    default:
                        $query->query_vars['meta_key']   = '_wwpp_wholesale_order_type';
                        $query->query_vars['meta_value'] = $wholesale_filter;

                        break;

                }
            }
            // phpcs:enable WordPress.Security.NonceVerification
            return $query;
        }

        /**
         * Wholesale order type custom filter for the query arguments used in the (Custom Order Table-powered)
         * order list table.
         *
         * @since 2.1.8
         * @access public
         *
         * @param array $order_query_args Arguments to be passed to `wc_get_orders()`.
         * @return array
         */
        public function custom_order_tables_query_args( $order_query_args ) {
            $wholesale_filter = null;

            if ( isset( $_GET['wwpp_fbwr'] ) ) { // phpcs:ignore 
                $wholesale_filter = trim( $_GET['wwpp_fbwr'] ); //phpcs:ignore
            }

            if ( ! is_null( $wholesale_filter ) ) {
                switch ( $wholesale_filter ) {

                    case null:
                        // Do nothing.
                        break;

                    case 'all_order_types':
                        // Do nothing.
                        break;

                    case 'all_retail_orders':
                        $order_query_args['meta_query'] = array(
                            'relation' => 'OR',
                            array(
                                'key'     => '_wwpp_order_type',
                                'value'   => array( 'retail' ),
                                'compare' => 'IN',
                            ),
                            array(
                                'key'     => '_wwpp_order_type',
                                'compare' => 'NOT EXISTS',
                            ),
                        );

                        break;

                    case 'all_wholesale_orders':
                        $order_query_args['meta_key']   = '_wwpp_order_type';
                        $order_query_args['meta_value'] = 'wholesale';

                        break;

                    default:
                        $order_query_args['meta_key']   = '_wwpp_wholesale_order_type';
                        $order_query_args['meta_value'] = $wholesale_filter;

                        break;

                }
            }
            return $order_query_args;
        }

        /**
         * Prevent reduce stock for wholesale order.
         *
         * @since 1.30.2
         * @access public
         *
         * @param bool    $reduce_stock Whether to reduce stock or not.
         * @param WP_Post $order        Order object.
         * @return bool
         */
        public function prevent_reduce_stock_for_wholesale_order( $reduce_stock, $order ) { // phpcs:ignore WordPress.Commenting.FunctionComment.MissingParamComment.
            if ( 'yes' === get_option( 'wwpp_settings_prevent_stock_reduction', 'no' ) ) {
                $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();
                if ( ! empty( $user_wholesale_role ) && $order ) {
                    $reduce_stock = false;
                }
            }

            return $reduce_stock;
        }

        /**
         * Set customer to order.
         *
         * @since 1.14.0
         * @access public
         *
         * @param array  $data     Data.
         * @param object $customer Customer.
         * @param int    $user_id    User ID.
         */
        public function wholesale_ajax_set_customer_to_order( $data, $customer, $user_id ) {
            $wholesale_roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();
            $customer_role   = $customer->get_role();
            if ( ! empty( $_POST['order_id'] ) && array_key_exists( $customer_role, $wholesale_roles ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification done in WooCommerce core
                $order_id = $_POST['order_id']; //phpcs:ignore
                $order    = wc_get_order( $order_id );

                // Check if $order is valid before proceeding.
                if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
                    return $data;
                }

                $old_customer_id = $order->get_customer_id();
                if ( ! $old_customer_id ) {
                    $order->update_meta_data( '_customer_user', $user_id );
                    $order->save();
                }
            }

            return $data;
        }

        /**
         * Process order item.
         *
         * @since 1.14.0
         * @access public
         *
         * @param int    $item_id  Order item id.
         * @param object $item     Order item object.
         * @param int    $order_id Order id.
         */
        public function wholesale_process_order_item( $item_id, $item, $order_id = false ) { // phpcs:ignore WordPress.Commenting.FunctionComment.MissingParamComment.

            // Run if process in admin panel.
            if ( ! is_admin() || ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
                return;
            }

            // Check if valid order id and not empty.
            if ( empty( $order_id ) || ! is_numeric( $order_id ) ) {
                return;
            }

            $order = wc_get_order( $order_id );

            // Check if $order is valid before proceeding.
            if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
                return;
            }

            $this->wholesale_process_order_item_data( $order, true );
        }

        /**
         * A callback function to remove 'woocommerce_update_order_item' hook, when refund is initiated via manual order.
         *
         * @param [type]  $item_id WC_Order Object Line Item Id.
         * @param [type]  $item WC_Order Object Line Item Object.
         * @param boolean $order_id WC_Order order id.
         * @return void
         */
        public function remove_wholesale_refund_action( $item_id, $item, $order_id = false ) { // phpcs:ignore WordPress.Commenting.FunctionComment.MissingParamComment
            $order = wc_get_order( $order_id );
            if ( ! $order || ! is_a( $order, 'OrderRefund' ) ) {
                remove_action( 'woocommerce_update_order_item', array( $this, 'wholesale_process_order_item' ), 13, 3 );
            }
        }

        /**
         * Normalise the apply_wholesale request value to a boolean.
         *
         * Legitimate callers send the value as a scalar ('true'/'false',
         * '1'/'0', 1/0), but a crafted payload could send an array, and
         * wc_string_to_bool() fatals on non-strings under PHP 8. Absent or
         * non-scalar values normalise to false so every AJAX handler that
         * reads the flag degrades to retail pricing instead of fataling.
         *
         * @since 2.1.0
         * @access private
         *
         * @param array $post_variables The raw request variables.
         *
         * @return bool Whether wholesale pricing should be applied.
         */
        private function normalize_apply_wholesale_request( $post_variables ) {
            return isset( $post_variables['apply_wholesale'] ) && is_scalar( $post_variables['apply_wholesale'] )
                ? wc_string_to_bool( $post_variables['apply_wholesale'] )
                : false;
        }

        /**
         * Build the address arguments passed to WC_Order::calculate_taxes() from the posted variables.
         *
         * The order-item AJAX handlers recalculate taxes against the address typed into the admin
         * order screen before the order is saved. Sanitising the four address fields the same way in
         * one place keeps the manual-recalc and line-totals paths from drifting apart.
         *
         * @since 2.1.0
         * @access private
         *
         * @param array $post_variables The raw request variables.
         *
         * @return array The sanitised country/state/postcode/city arguments for calculate_taxes().
         */
        private function build_tax_calculation_args( $post_variables ) {
            return array(
                'country'  => isset( $post_variables['country'] ) ? wc_strtoupper( wc_clean( wp_unslash( $post_variables['country'] ) ) ) : '',
                'state'    => isset( $post_variables['state'] ) ? wc_strtoupper( wc_clean( wp_unslash( $post_variables['state'] ) ) ) : '',
                'postcode' => isset( $post_variables['postcode'] ) ? wc_strtoupper( wc_clean( wp_unslash( $post_variables['postcode'] ) ) ) : '',
                'city'     => isset( $post_variables['city'] ) ? wc_strtoupper( wc_clean( wp_unslash( $post_variables['city'] ) ) ) : '',
            );
        }

        /**
         * Process order item data.
         *
         * @since 1.14.0
         * @since 2.1.0 Normalise the apply_wholesale POST value with wc_string_to_bool() so the integer payload from wwpp-order-data-item-after is honoured. Guard against non-scalar payloads before normalising to avoid a TypeError on PHP 8.
         * @since 2.1.1 Pass the resolved customer role to the wwpp_order_item_wholesale_priced filter so vendor allowed-role restrictions are honoured on manual orders.
         * @access public
         *
         * @param object $order Order object.
         * @param bool   $ajax  Whether it is ajax or not.
         *
         * @return void
         */
        public function wholesale_process_order_item_data( $order, $ajax = false ) {
            global $wc_wholesale_prices_premium;

            $post_variables = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification done in WooCommerce AJAX handler

            // Check if valid order object.
            if ( ! is_a( $order, 'WC_Order' ) ) {
                return;
            }

            // Ensure that the object is not a refund.
            if ( $order instanceof WC_Order_Refund ) {
                return;
            }

            // Ignore if recalculating wholesale prices.
            if ( isset( $post_variables['recalculate_wws'] ) && 1 === intval( $post_variables['recalculate_wws'] ) ) {
                return;
            }

            $items = $order->get_items();
            if ( $items ) {
                foreach ( $items as $item_id => $item ) {

                    $item_type = $item->get_type();

                    // Run if item type is product.
                    if ( 'line_item' !== $item_type ) {
                        return;
                    }

                    $product = $item->get_product();

                    // Run if product is not found.
                    if ( ! is_a( $product, 'WC_Product' ) ) {
                        return;
                    }

                    $wholesale_roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();

                    // Get the selected customer role.
                    $customer_id   = $order->get_customer_id();
                    $customer_role = '';
                    if ( ! $customer_id && ! empty( $post_variables ) ) {
                        $customer_id = isset( $post_variables['customer_user'] ) ? absint( $post_variables['customer_user'] ) : 0;
                    }

                    if ( $customer_id ) {
                        $customer = new WC_Customer( $customer_id );
                        if ( $customer ) {
                            $customer_role = $customer->get_role();
                        }
                    }

                    // Check if user is wholesale customers.
                    if ( ! empty( $customer_role ) && array_key_exists( $customer_role, $wholesale_roles ) ) {
                        $apply_wholesale = $order->get_meta( '_apply_wholesale_pricing_manually', true );
                        $variation_id    = 0;
                        $did_not_meet    = false;
                        $product_id      = $product->get_id();
                        $current_price   = $product->get_price();
                        $quantity        = $item->get_quantity();

                        if ( $product->is_type( 'variation' ) ) {
                            $variation_id = $product->get_id();
                            $product_id   = $product->get_parent_id();
                        }

                        $selected_product_id = ( $variation_id > 0 ) ? $variation_id : $product_id;

                        if ( in_array( $product->get_type(), array( 'simple', 'bundle', 'composite' ), true ) ) {
                            $moq = $product->get_meta( $customer_role . '_wholesale_minimum_order_quantity', true );
                            $moq = ( is_numeric( $moq ) ) ? (int) $moq : 0;

                            if ( ! empty( $moq ) && $quantity < $moq ) {
                                $did_not_meet = true;
                            }
                        }

                        $wholesale_priced = 'no';
                        if ( ! $did_not_meet ) {
                            // Get the wholesale price.
                            $wholesale_price_raw          = WWP_Wholesale_Prices::get_product_raw_wholesale_price( $selected_product_id, array( $customer_role ) );
                            $wholesale_price              = ! empty( $wholesale_price_raw ) ? $wholesale_price_raw : 0;
                            $product_wholesale_sale_price = $wc_wholesale_prices_premium->wwpp_wholesale_prices->get_product_wholesale_sale_price( $selected_product_id, array( $customer_role ) );

                            // Check if the product has sale price.
                            if ( ( isset( $product_wholesale_sale_price['is_on_sale'] ) && true === $product_wholesale_sale_price['is_on_sale'] ) && isset( $product_wholesale_sale_price['wholesale_sale_price'] ) ) {
                                $wholesale_price = $product_wholesale_sale_price['wholesale_sale_price'];
                            }

                            if ( $this->normalize_apply_wholesale_request( $post_variables ) ) {
                                $apply_wholesale  = 'yes';
                                $wholesale_priced = 'yes';
                            } else {
                                $wholesale_price  = '';
                                $wholesale_priced = 'no';

                                // Get the current product price.
                                $sale_price    = $product->get_sale_price();
                                $regular_price = $product->get_regular_price();

                                if ( $product->is_on_sale() && ! empty( $sale_price ) ) {
                                    $current_price = $sale_price;
                                } else {
                                    $current_price = $regular_price;
                                }
                            }

                            if ( ! empty( trim( $apply_wholesale ) ) && 'yes' === $apply_wholesale ) {
                                $wholesale_price                = ( ! empty( $wholesale_price ) ) ? (float) $wholesale_price : $current_price;
                                $woocommerce_prices_include_tax = get_option( 'woocommerce_prices_include_tax' );
                                if ( 'yes' === $woocommerce_prices_include_tax ) {
                                    $wholesale_price = wc_get_price_excluding_tax(
                                        $product,
                                        array(
                                            'price' => $wholesale_price,
                                        )
                                    );
                                }

                                $wholesale_price_total = $wholesale_price * $quantity;

                                // For temp display and save.
                                $item->set_subtotal( $wholesale_price );
                                $item->set_total( $wholesale_price_total );

                                // Force save meta.
                                wc_update_order_item_meta( $item_id, '_line_subtotal', $wholesale_price );
                                wc_update_order_item_meta( $item_id, '_line_total', $wholesale_price_total );

                                if ( $ajax ) {
                                    // Get tax calculation.
                                    $product_tax_class = get_post_meta( $selected_product_id, '_tax_class', true );
                                    $tax_rates         = WC_Tax::get_rates( $product_tax_class );
                                    $taxes             = WC_Tax::calc_tax( $wholesale_price, $tax_rates, false );
                                    $total_tax         = array_sum( $taxes );

                                    // Set tax data.
                                    $line_tax_data = array(
                                        'total'    => $taxes,
                                        'subtotal' => $taxes,
                                    );

                                    // For temp display and save tax meta.
                                    $item->set_props(
                                        array(
                                            'line_tax' => $total_tax,
                                            'line_tax_data' => $line_tax_data,
                                        )
                                    );

                                    // Force save tax meta.
                                    wc_update_order_item_meta( $item_id, '_line_tax', $total_tax );
                                    wc_update_order_item_meta( $item_id, '_line_tax_data', $line_tax_data );
                                }
                            }
                        }

                        $wholesale_priced = apply_filters(
                            'wwpp_order_item_wholesale_priced',
                            $wholesale_priced,
                            $product,
                            $customer_role,
                        );

                        if ( ! empty( trim( $apply_wholesale ) ) && 'yes' === $apply_wholesale ) {
                            wc_update_order_item_meta( $item_id, '_wwp_wholesale_priced', $wholesale_priced );
                            wc_update_order_item_meta( $item_id, '_wwp_wholesale_role', $customer_role );
                        }
                    }
                }
            }
        }

        /**
         * Process order on save.
         *
         * @since 1.14.0
         * @access public
         *
         * @param int    $order_id Order id.
         * @param object $order    Order object.
         */
        public function wholesale_woocommerce_update_order( $order_id = 0, $order = array() ) {

            // Check if no order object.
            if ( empty( $order ) && ! empty( $order_id ) ) {
                $order = wc_get_order( $order_id );
            }

            // Check if valid order object.
            if ( ! is_a( $order, 'WC_Order' ) ) {
                return;
            }

            // Ensure that the object is not a refund.
            if ( $order instanceof WC_Order_Refund ) {
                return;
            }

            $post_variables = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification done in WooCommerce order admin form

            // Exit early for REST API updates (e.g., Metorik) that don't send POST data.
            if ( empty( $post_variables ) ) {
                return;
            }

            // Get the selected customer role.
            $customer_id   = $order->get_customer_id();
            $customer_role = '';
            if ( ! $customer_id && ! empty( $post_variables ) ) {
                $customer_id = isset( $post_variables['customer_user'] ) ? absint( $post_variables['customer_user'] ) : 0;
            }

            // Get the selected customer role.
            if ( $customer_id ) {
                $customer = new WC_Customer( $customer_id );
                if ( $customer ) {
                    $customer_role = $customer->get_role();
                }

                $wholesale_roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();
                // Check if user is wholesale customers.
                if ( array_key_exists( $customer_role, $wholesale_roles ) ) {
                    $order->update_meta_data( '_wwpp_order_type', 'wholesale' );
                    $order->update_meta_data( '_wwpp_wholesale_order_type', $customer_role );
                } else {
                    $order->update_meta_data( '_wwpp_order_type', 'retail' );
                    $order->update_meta_data( '_wwpp_wholesale_order_type', '' );
                }

                $is_apply = 'no';
                if ( ! empty( $post_variables['apply_wholesale_pricing'] ) && 'on' === $post_variables['apply_wholesale_pricing'] ) {
                    $is_apply = 'yes';
                }
                $order->update_meta_data( '_apply_wholesale_pricing', $is_apply );
            }
        }

        /**
         * Persist the "Apply Wholesale Pricing" checkbox state when an order is saved from the admin order edit screen.
         *
         * The checkbox rendered by wholesale_order_custom_checkbox() is considered ticked when either the
         * `_apply_wholesale_pricing` (form-save) or `_apply_wholesale_pricing_manually` (AJAX toggle) meta is `'yes'`.
         *
         * The form-save path previously persisted `_apply_wholesale_pricing` from a callback on `woocommerce_update_order`,
         * which fires after HPOS has already flushed order meta to the database (persist_updates() runs save_meta_data()
         * before the action). The queued meta change — in particular unticking the box — was therefore discarded and the
         * checkbox could never be turned off from the admin order screen. Persisting here, on WooCommerce's dedicated admin
         * order save hook, runs with full POST data after the core order save (priority 40) so $order->save() writes the
         * value reliably in both legacy and HPOS storage. Both meta keys are kept in sync with the posted checkbox state so
         * the rendered checkbox reflects what was saved (a stale `_apply_wholesale_pricing_manually` from a prior AJAX toggle
         * would otherwise keep the box ticked even after unticking and saving). Orders whose checkbox is rendered disabled
         * (processing/completed) are skipped, since a disabled checkbox is not submitted and an absent value would otherwise
         * be misread as "unticked" and wipe the saved state. The skip keys on the render-time companion flag
         * (`apply_wholesale_pricing_editable`) emitted by wholesale_order_custom_checkbox() rather than the post-save status,
         * so it still holds when a locked order is transitioned to an editable status in the same save.
         *
         * @since 2.1.1
         * @access public
         *
         * @param int $order_id Order ID being saved.
         */
        public function save_apply_wholesale_pricing_checkbox( $order_id ) {
            $post_variables = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification handled by WooCommerce admin order save (check_admin_referer) before this hook fires.

            $order = wc_get_order( $order_id );

            if ( ! is_a( $order, 'WC_Order' ) || $order instanceof WC_Order_Refund ) {
                return;
            }

            // The checkbox is rendered disabled for locked statuses (processing/completed) and a disabled checkbox is
            // not submitted. wholesale_order_custom_checkbox() emits the companion `apply_wholesale_pricing_editable`
            // flag only when the checkbox is editable, so its absence means the checkbox was rendered disabled this
            // load — an absent value is "locked", not "unticked". Keying on this render-time flag (rather than the
            // post-save status) also covers saving a locked order while transitioning it to an editable status in the
            // same submit, where get_status() would already reflect the new editable status.
            if ( empty( $post_variables['apply_wholesale_pricing_editable'] ) ) {
                return;
            }

            // Nothing to do for retail orders that never used wholesale pricing: when the field is absent and neither
            // meta is set, bail before $order->save() so no redundant 'no' meta is written on every admin order save.
            $field_present = isset( $post_variables['apply_wholesale_pricing'] );
            if ( ! $field_present && 'yes' !== $order->get_meta( '_apply_wholesale_pricing' ) && 'yes' !== $order->get_meta( '_apply_wholesale_pricing_manually' ) ) {
                return;
            }

            $is_apply = ( $field_present && 'on' === sanitize_text_field( wp_unslash( $post_variables['apply_wholesale_pricing'] ) ) ) ? 'yes' : 'no';

            $order->update_meta_data( '_apply_wholesale_pricing', $is_apply );
            $order->update_meta_data( '_apply_wholesale_pricing_manually', $is_apply );
            $order->save();
        }

        /**
         * Process order item after.
         *
         * @since 1.14.0
         * @since 2.1.0 Normalise every apply_wholesale read through the shared scalar-guarded helper so a malformed payload degrades to retail pricing instead of fataling on PHP 8.
         * @since 2.1.0 Re-render the order items table for wholesale customers even when wholesale pricing is not applied, calculating taxes and totals on the existing line items so the tax column and order totals appear immediately after adding a product instead of only after clicking Apply Wholesale Pricing.
         * @access public
         *
         * @return void
         */
        public function wholesale_process_order_item_after() {
            check_ajax_referer( 'wwpp_order_nonce', 'security' );

            WWPP_Helper_Functions::require_capability_or_send_error( 'edit_shop_orders' );

            $post_variables = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing.
            $order_id       = absint( $post_variables['order_id'] );

            $wholesale_roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();
            $order_obj       = wc_get_order( $order_id );

            // Check if $order_obj is valid before proceeding.
            if ( ! $order_obj || ! is_a( $order_obj, 'WC_Order' ) ) {
                wp_die();
                return;
            }

            // Get the selected customer role.
            $customer_id   = $order_obj->get_customer_id();
            $customer_role = '';
            if ( ! $customer_id ) {
                $customer_id = absint( $post_variables['customer_user'] );
            }

            // Both re-render branches below include the same WooCommerce order-items metabox view.
            $template_path = WC_ABSPATH . 'includes/admin/meta-boxes/views/html-order-items.php';

            if ( wc_string_to_bool( $order_obj->get_meta( '_apply_wholesale_pricing_manually' ) ) ) { // once 'Apply Wholesale Pricing' checkbox is clicked, which means meta is saved. now if the user continuously checks 'Apply Wholesale Pricing', and this logic will be triggered only. below will not get triggered.
                $customer_role = isset( $customer_id ) ? ( new WC_Customer( $customer_id ) )->get_role() : '';

                if ( ! $customer_role ) {
                    die();
                }

                $order = $this->recalculate_order_items_based_on_wholesale_price( $order_obj, $customer_role, $customer_id, $this->normalize_apply_wholesale_request( $post_variables ) );
                include $template_path;
                die();
            }

            $pass_to_check = false;
            if ( ! empty( $post_variables['apply_wholesale'] ) ) {
                $apply_wholesale         = $order_obj->get_meta( '_apply_wholesale_pricing_manually', true );
                $apply_wholesale_manual  = 'yes';
                $apply_wholesale_request = $this->normalize_apply_wholesale_request( $post_variables );
                if ( true === $apply_wholesale_request ) {
                    $pass_to_check = true;
                } elseif ( false === $apply_wholesale_request ) {
                    $apply_wholesale_manual = 'no';

                    if ( ! empty( trim( $apply_wholesale ) ) ) {
                        $pass_to_check = true;
                    }
                }

                $order_obj->update_meta_data( '_apply_wholesale_pricing_manually', $apply_wholesale_manual );
                if ( empty( trim( $apply_wholesale ) ) ) {
                    $order_note = __( 'Wholesale pricing has been applied manually.', 'woocommerce-wholesale-prices-premium' );
                    $order_obj->add_order_note( $order_note );
                }
                $order_obj->save();
            } else {
                $order_obj->delete_meta_data( '_apply_wholesale_pricing_manually' );
                $order_obj->save();
            }

            if ( $customer_id ) {
                $customer_role = ( new WC_Customer( $customer_id ) )->get_role();
            }

            // Re-render the order items table for wholesale customers. Doing this on every
            // item change (not only when wholesale pricing is applied) means the tax column
            // and order totals appear as soon as a product is added, without first clicking
            // the Apply Wholesale Pricing toggle.
            if ( ! empty( $customer_role ) && array_key_exists( $customer_role, $wholesale_roles ) ) {
                if ( $pass_to_check ) {
                    // Wholesale pricing is being applied to the order.
                    $order = $this->recalculate_order_items_based_on_wholesale_price( $order_obj, $customer_role, $customer_id );
                } else {
                    // Wholesale pricing is not applied. Leave the existing line prices untouched
                    // and only (re)calculate taxes and totals for the current items, mirroring
                    // WooCommerce's native recalculation. The posted address is used so the
                    // correct tax rates resolve before the order has been saved.
                    $order_obj->calculate_taxes( $this->build_tax_calculation_args( $post_variables ) );
                    $order_obj->calculate_totals( false );
                    $order = $order_obj;
                }

                include $template_path;
            }

            wp_die();
        }

        /**
         * Get formatted items.
         *
         * @since 1.14.0
         * @access public
         *
         * @param object $order Order object.
         * @return array
         */
        public function wholesale_get_formatted_items( $order ) {
            if ( ! $order ) {
                return;
            }

            $formatted_items = array();
            $items           = $order->get_items();
            if ( $items ) {
                foreach ( $items as $item_id => $item ) {
                    $product      = $item->get_product();
                    $product_id   = $item->get_product_id();
                    $variation_id = $item->get_variation_id();
                    $quantity     = $item->get_quantity();
                    $subtotal     = $item->get_subtotal();
                    $total        = $item->get_total();
                    $tax_class    = $item->get_tax_class();
                    $name         = $item->get_name();
                    $taxes        = $item->get_taxes();
                    $meta_data    = $item->get_meta_data();

                    $formatted_meta_data = array();
                    foreach ( $meta_data as $meta ) {
                        $formatted_meta_data[] = array(
                            'key'   => $meta->key,
                            'value' => $meta->value,
                        );
                    }

                    $formatted_items[] = array(
                        'product_id'   => $product_id,
                        'variation_id' => $variation_id,
                        'quantity'     => $quantity,
                        'subtotal'     => $subtotal,
                        'total'        => $total,
                        'tax_class'    => $tax_class,
                        'name'         => $name,
                        'taxes'        => array(
                            'total'    => $taxes['total'],
                            'subtotal' => $taxes['subtotal'],
                        ),
                        'meta_data'    => $formatted_meta_data,
                    );
                }
            }

            return $formatted_items;
        }

        /**
         * Calculate line totals.
         *
         * @since 1.14.0
         * @access public
         *
         * @param array $post_variables Post variables.
         * @param int   $order_id        Order id.
         * @param array $items           Order items.
         * @return object
         */
        public function wholesale_calc_line_totals( $post_variables, $order_id, $items ) { //phpcs:ignore
            // Save order items first.
            wc_save_order_items( $order_id, $items );

            $calculate_tax_args = $this->build_tax_calculation_args( $post_variables );

            $order_obj = wc_get_order( $order_id );

            // Check if $order_obj is valid before proceeding.
            if ( ! $order_obj || ! is_a( $order_obj, 'WC_Order' ) ) {
                return false;
            }

            $order_obj->calculate_taxes( $calculate_tax_args );

            return $order_obj;
        }

        /**
         * Process order using API.
         *
         * @since 1.14.0
         * @access public
         *
         * @param int   $order_id Order id.
         * @param array $data     Data.
         *
         * @return void
         */
        public function wholesale_woocommerce_create_order_api( $order_id = 0, $data = array() ) {
            $order = wc_get_order( $order_id );

            if ( ! $order ) {
                return;
            }

            // Get the selected customer role.
            $customer_id   = $order->get_customer_id();
            $customer_role = '';
            if ( ! $customer_id ) {
                $customer_id = isset( $data['customer_id'] ) ? absint( $data['customer_id'] ) : 0;
            }

            // Get the selected customer role.
            if ( $customer_id ) {
                // Save order items first.
                $this->wholesale_process_order_item_data( $order );

                // recalculate order totals.
                $order->calculate_totals( true );
            }
        }

        /**
         * Add custom checkbox to order.
         *
         * @since 1.14.0
         * @access public
         *
         * @param object $order Order object.
         */
        public function wholesale_order_custom_checkbox( $order ) {
            $status                   = $order->get_status();
            $apply_wholesale          = $order->get_meta( '_apply_wholesale_pricing', true );
            $apply_wholesale_manually = $order->get_meta( '_apply_wholesale_pricing_manually', true );
            $is_editable              = 'no';
            if ( 'yes' === $apply_wholesale_manually ) {
                $is_editable = 'yes';
            } elseif ( 'yes' === $apply_wholesale ) {
                $is_editable = 'yes';
            }

            $disable = '';
            if ( 'processing' === $status || 'completed' === $status ) {
                $disable = 'disabled';
            }
            ?>
            <p class="form-field form-field-wide">
                <span class="order_checkbox_field">
                    <input type="checkbox" id="apply_wholesale_pricing" name="apply_wholesale_pricing" <?php checked( $is_editable, 'yes' ); ?> style="width: 1rem;" data-toggle="tooltip" data-placement="top" title="<?php esc_attr_e( 'Apply wholesale pricing to this order. Only products with wholesale pricing will be affected. This will keep wholesale prices in sync with products in the catalog.', 'woocommerce-wholesale-prices-premium' ); ?>" <?php echo esc_attr( $disable ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped. ?> />
                    <?php if ( '' === $disable ) : ?>
                        <?php // Companion flag emitted only when the checkbox is editable. A disabled checkbox is not submitted, so save_apply_wholesale_pricing_checkbox() keys off this field's presence to distinguish "rendered disabled (locked)" from a genuine untick. ?>
                        <input type="hidden" name="apply_wholesale_pricing_editable" value="1" />
                    <?php endif; ?>
                    <label for="apply_wholesale_pricing" style="display: inline-block;"><?php esc_html_e( 'Apply Wholesale Pricing', 'woocommerce-wholesale-prices-premium' ); ?></label>
                </span>
            </p>
            <p><em><?php esc_html_e( 'This option will only apply if you select wholesale customer.', 'woocommerce-wholesale-prices-premium' ); ?></em></p>
            <?php
        }

        /**
         * Recalculate order line-item prices, taxes, and wholesale meta in-place.
         *
         * Mutates each existing order item's subtotal/total/tax/tax-class and updates the
         * wholesale-pricing meta keys, preserving the order item ID and all other meta
         * (notably `_reduced_stock`, which WooCommerce uses to track that the item has
         * already been deducted from inventory). The previous remove + recreate approach
         * dropped that meta and caused duplicate stock deductions on every subsequent
         * order save. See issue #1560.
         *
         * @since 1.14.0
         * @since 2.1.0 Mutate existing items in place to preserve `_reduced_stock`.
         * @since 2.1.1 Apply the wholesale sale price using the schedule-aware is_on_sale flag.
         * @since 2.1.1 Carry zero line tax for tax-exempt roles and clear aggregate/cart/shipping tax.
         * @since 2.1.1 Apply the wwpp_order_item_wholesale_priced filter with the resolved customer role so vendor allowed-role restrictions are honoured on manual orders (Path A).
         *
         * @param WC_Order $order           Order being recalculated.
         * @param string   $role            Wholesale role key.
         * @param int      $customer_id     Customer ID. Defaults to 0.
         * @param bool     $apply_wholesale Whether to apply wholesale pricing. Defaults to true.
         * @return WC_Order
         */
        public function recalculate_order_items_based_on_wholesale_price( $order, $role, $customer_id = 0, $apply_wholesale = true ) {
            $order_items = $order->get_items();

            /**
             * Remove the filter that interupts with the tax on 'wholesale_price', when option like 'Display prices in the shop' are changed from 'Woocommerce'.
             */
            global  $wc_wholesale_prices_premium;
            remove_filter( 'wwp_pass_wholesale_price_through_taxing', array( $wc_wholesale_prices_premium->wwpp_tax, 'integrate_tax_to_wholesale_price_on_shop' ), 10, 3 );

            /**
             * Include tax class mapping here from WWP settings.
             */
            $tax_class_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_TAX_CLASS_OPTIONS_MAPPING ) ?? array();
            $tax_class         = isset( $tax_class_mapping[ $role ] ) ? ( isset( $tax_class_mapping[ $role ]['tax-class'] ) ? $tax_class_mapping[ $role ]['tax-class'] : '' ) : '';

            /**
             * Check for general discount availability.
             */
            $does_the_product_have_general_discount = WWPP_Helper_Functions::_wholesale_user_have_general_role_discount( $role );

            /**
             * Check for per user discount availability
             */
            $does_the_product_have_per_user_discount = get_user_meta( $customer_id, 'wwpp_override_wholesale_discount', true );
            $per_user_discount_value                 = get_user_meta( $customer_id, 'wwpp_wholesale_discount', true );
            $does_the_product_have_per_user_discount = wc_string_to_bool( $does_the_product_have_per_user_discount ) && $does_the_product_have_general_discount && ! empty( $per_user_discount_value ); // if general discount is enabled && user discount is enabled. then we can say per user discount is finally enabled.

            /**
             * Include tax exemption mapping from WWP settings.
             */
            $tax_exempted_for_current_role = WWPP_Helper_Functions::is_user_wwpp_tax_exempted( $customer_id, $role );
            $tax_exempted_for_current_role = wc_string_to_bool( $tax_exempted_for_current_role );

            /**
             * Is tax inclusive from 'WooCommerce Tax' settings.
             */
            $woocommerce_inclusive_exclusive_tax_option = get_option( 'woocommerce_prices_include_tax' ); // based on this subtotal or total and total tax will be calculated.
            $woocommerce_inclusive_exclusive_tax_option = wc_string_to_bool( $woocommerce_inclusive_exclusive_tax_option );

            foreach ( $order_items as $order_item_id => $order_item ) {
                $product = $order_item->get_product();

                if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
                    continue; // Skip invalid product.
                }

                $wholesale_sale_price_array = WWPP_Wholesale_Prices::get_product_wholesale_sale_price( $product->get_id(), array( $role ) );

                // Reuse the schedule-aware is_on_sale flag (resolved when the sale price is fetched)
                // instead of re-deriving the sale window here, so manual orders apply the wholesale
                // sale price on exactly the same condition as the storefront and cart.
                $can_wholesale_sale_price_be_applied = ! empty( $wholesale_sale_price_array )
                    && ! empty( $wholesale_sale_price_array['is_on_sale'] )
                    && isset( $wholesale_sale_price_array['wholesale_sale_price'] );

                $wholesale_price_array              = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $product->get_id(), array( $role ) );
                $product_price_including_tax        = wc_get_price_including_tax( $product );
                $product_price_excluding_tax        = wc_get_price_excluding_tax( $product );
                $wholesale_price                    = $can_wholesale_sale_price_be_applied ? ( isset( $wholesale_sale_price_array['wholesale_sale_price'] ) ? (float) $wholesale_sale_price_array['wholesale_sale_price'] : 0 ) : ( isset( $wholesale_price_array['wholesale_price'] ) ? (float) $wholesale_price_array['wholesale_price'] : 0 );
                $wholesale_price_with_tax           = $can_wholesale_sale_price_be_applied ? ( isset( $wholesale_sale_price_array['wholesale_sale_price_with_tax'] ) ? (float) $wholesale_sale_price_array['wholesale_sale_price_with_tax'] : 0 ) : ( isset( $wholesale_price_array['wholesale_price_with_tax'] ) ? (float) $wholesale_price_array['wholesale_price_with_tax'] : 0 );
                $wholesale_price_with_no_tax        = $can_wholesale_sale_price_be_applied ? ( isset( $wholesale_sale_price_array['wholesale_sale_price_with_no_tax'] ) ? (float) $wholesale_sale_price_array['wholesale_sale_price_with_no_tax'] : 0 ) : ( isset( $wholesale_price_array['wholesale_price_with_no_tax'] ) ? (float) $wholesale_price_array['wholesale_price_with_no_tax'] : 0 );
                $wholesale_product_subscription_fee = (float) ( $product->get_meta( $role . '_wholesale_signup_fee', true ) ?? 0 );

                if ( ( 'subscription' === $product->get_type() || 'subscription_variation' === $product->get_type() ) && ! empty( $wholesale_product_subscription_fee ) ) {                    // Get signup fee with tax if prices include tax, without tax if prices exclude tax.
                    $signup_fee_with_tax          = wcs_get_price_including_tax( $product, array( 'price' => $wholesale_product_subscription_fee ) ); // Get the subscription fee with tax.
                    $signup_fee_without_tax       = wcs_get_price_excluding_tax( $product, array( 'price' => $wholesale_product_subscription_fee ) ); // Get the subscription fee without tax.
                    $wholesale_price_with_tax    += $signup_fee_with_tax;
                    $wholesale_price_with_no_tax += $signup_fee_without_tax;
                }

                $does_product_have_wholesale_price         = 'simple' === $product->get_type() ? ( $product->get_meta( $role . '_have_wholesale_price' ) ?? 'no' ) : $product->get_meta( $role . '_wholesale_price' ) ?? 0;
                $does_product_have_wholesale_price         = 'simple' === $product->get_type() ? wc_string_to_bool( $does_product_have_wholesale_price ) : ( ! empty( $does_product_have_wholesale_price ) ? true : false );
                $category_based_discount_on_product        = 'simple' === $product->get_type() ? $wc_wholesale_prices_premium->wwpp_wholesale_price_product_category->get_base_term_id_and_wholesale_discount( $product->get_id(), array( $role ) ) : $wc_wholesale_prices_premium->wwpp_wholesale_price_product_category->get_base_term_id_and_wholesale_discount( $product->get_parent_id(), array( $role ) );
                $does_product_have_category_based_discount = isset( $category_based_discount_on_product['discount'] ) && ! empty( $category_based_discount_on_product['discount'] ) ? true : false; // flag to check product categort based discount.
                $does_product_have_wholesale_price         = $does_product_have_wholesale_price || $does_product_have_category_based_discount || $does_the_product_have_per_user_discount || $does_the_product_have_general_discount;

                /**
                 * Give integrations (e.g. WC Vendors) an opportunity to suppress wholesale pricing
                 * during the Apply Wholesale Pricing recalc, passing the resolved order customer role.
                 * The AJAX save path already exposes this filter; Path A previously wrote
                 * _wwp_wholesale_priced directly with no such hook, so role-based vendor restrictions
                 * had no chance to run for admin-created orders. Because the price below is derived
                 * from this flag, a veto here also reverts the line to its regular price. See #1773.
                 */
                $does_product_have_wholesale_price = wc_string_to_bool(
                    apply_filters(
                        'wwpp_order_item_wholesale_priced',
                        $does_product_have_wholesale_price ? 'yes' : 'no',
                        $product,
                        $role
                    )
                );

                if ( $does_the_product_have_per_user_discount ) { // for per user discount, manual calculation is needed to be done because the wholesale_array does not return the correct calculated wholesale prices.
                    $wholesale_price_with_tax    = $product_price_including_tax - ( $product_price_including_tax * ( $per_user_discount_value / 100 ) );
                    $wholesale_price_with_no_tax = $product_price_excluding_tax - ( $product_price_excluding_tax * ( $per_user_discount_value / 100 ) );
                    $wholesale_price             = $woocommerce_inclusive_exclusive_tax_option ? $wholesale_price_with_tax : $wholesale_price_with_no_tax; // Keep the gross (inc-tax) figure under inclusive tax — even for tax-exempt roles — so the single unconditional deduction below strips tax exactly once. Storing the net here would double-deduct for exempt roles.
                }

                // When prices include tax the wholesale/regular figure above is gross, so always strip the tax to store the net line amount. This also makes tax-exempt roles fall back to the ex-tax base (e.g. an inc-tax wholesale price of 124 is stored as the net 100), matching the frontend, cart and checkout behaviour.
                $should_deduct_tax                                     = $woocommerce_inclusive_exclusive_tax_option;
                $is_tax_exempted_and_does_product_have_wholesale_price = ( $tax_exempted_for_current_role && $does_product_have_wholesale_price );
                $should_apply_wholesale                                = $does_product_have_wholesale_price && $apply_wholesale;
                $final_wholesale_price                                 = $woocommerce_inclusive_exclusive_tax_option ? $wholesale_price : $wholesale_price_with_no_tax; // wholesale price.
                $final_regular_price                                   = $woocommerce_inclusive_exclusive_tax_option ? $product_price_including_tax : $product_price_excluding_tax; // non-wholesale price.

                $existing_product_quantity = $order_item->get_quantity();
                $subtotal_or_total         = $should_apply_wholesale ? $existing_product_quantity * $final_wholesale_price : $existing_product_quantity * $final_regular_price; // if 'price inclusive tax' is set to 'yes' then use 'wholesale_price', else use 'wholesale_price_with_no_tax'.
                $total_tax                 = $should_apply_wholesale ? ( ( $wholesale_price_with_tax - $wholesale_price_with_no_tax ) * $existing_product_quantity ) : ( ( $product_price_including_tax - $product_price_excluding_tax ) * $existing_product_quantity );
                $subtotal_or_total         = $should_deduct_tax ? $subtotal_or_total - $total_tax : $subtotal_or_total; // if 'price inclusive tax' is set to 'yes', deduct the tax so the stored line amount is net of tax; otherwise the figure is already net.
                $item_tax                  = $tax_exempted_for_current_role ? 0 : $total_tax; // Tax-exempt wholesale roles must carry zero tax on the line so the order total stays net (the exemption removes tax for the user, as on the storefront).
                $order_item_tax_class      = $is_tax_exempted_and_does_product_have_wholesale_price ? 'zero-rate' : ( ( strlen( $tax_class ) > 0 ) ? $tax_class : $product->get_tax_class() );// if tax exemption is set to 'yes' for the current role and has wholesale price set, then set 'zero-rate' tax(as taxes will not apply if tax exemption is set for the current role), else apply taxes based on 'tax mapping' or without 'tax-mapping' for product with wholesale price only.

                // Update the existing item in-place to preserve _reduced_stock meta and prevent duplicate stock deductions.
                $order_item->set_subtotal( $subtotal_or_total );
                $order_item->set_total( $subtotal_or_total );
                $order_item->set_total_tax( $item_tax );
                $order_item->set_subtotal_tax( $item_tax );
                $order_item->set_tax_class( $order_item_tax_class );
                $order_item->set_taxes(
                    array(
                        'total'    => array(
                            $item_tax,
                        ),
                        'subtotal' => array(
                            $item_tax,
                        ),
                    )
                );
                $order_item->update_meta_data( '_wwp_wholesale_priced', ( $does_product_have_wholesale_price ? 'yes' : 'no' ) );
                $order_item->update_meta_data( '_wwp_wholesale_role', $role );
                $order_item->save();
            }

            if ( $tax_exempted_for_current_role ) {
                /**
                 * The calculate_totals( false ) call below skips calculate_taxes(), which is
                 * what normally refreshes the aggregate tax and prunes tax line items. Clearing
                 * them here ensures the order total reflects the zero-tax exempt lines rather
                 * than the stale tax captured when the products were first added at their
                 * regular price.
                 */
                foreach ( $order->get_items( 'tax' ) as $tax_item_id => $tax_item ) {
                    $order->remove_item( $tax_item_id );
                }
                $order->set_cart_tax( 0 );
                $order->set_shipping_tax( 0 );
            }

            $order->calculate_totals( $tax_exempted_for_current_role ? false : true );
            $order->save();

            return $order;
        }

        /**
         * Invalidate the reports cache on any order status transition.
         *
         * Hooked to `woocommerce_order_status_changed`, which fires for all status
         * transitions regardless of how they are triggered (WP Admin, payment gateway
         * callbacks, REST API, Action Scheduler, programmatic changes, etc.).
         *
         * @since 2.0.8.1
         * @param int    $order_id   The order ID.
         * @param string $old_status The previous order status (without the wc- prefix).
         * @param string $new_status The new order status (without the wc- prefix).
         * @return void
         */
        public function invalidate_reports_cache_on_status_change( $order_id, $old_status, $new_status ) { //phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
            WWPP_Cache::instance( array() )->invalidate_reports_cache();
        }

        /**
         * Execute model.
         *
         * @since 1.14.0
         * @since 2.1.0 Register woocommerce_analytics_update_order_stats_data filter to recalculate tax_total from order tax line items.
         * @access public
         */
        public function run() {

            add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'custom_thank_you_message' ), 10, 1 );

            add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_orders_listing_custom_column' ), 15, 1 ); // WordPress posts table.
            add_filter( 'woocommerce_shop_order_list_table_columns', array( $this, 'add_orders_listing_custom_column' ), 15, 1 ); // WooCommerce orders tables.
            add_action( 'manage_shop_order_posts_custom_column', array( $this, 'add_orders_listing_custom_column_content' ), 10, 2 ); // WordPress posts table.
            add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'add_orders_listing_custom_column_content' ), 10, 2 ); // WooCommerce orders tables.

            // Add order type meta on classic checkout.
            add_action( 'woocommerce_checkout_order_processed', array( $this, 'add_order_type_meta_to_wc_orders' ), 10, 1 );

            // Add order type meta on checkout blocks.
            add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'add_order_type_meta_to_wc_orders' ), 10, 1 );

            // Keep wc_order_stats.tax_total aligned with the order's tax line items so wholesale discount fees do not leak into Analytics tax figures.
            // Priority 9 runs the shop-currency recalc before any currency-conversion filters (e.g. WooCommerce Multi-Currency) hooked at the default priority 10.
            add_filter( 'woocommerce_analytics_update_order_stats_data', array( $this, 'recalculate_analytics_tax_total' ), 9, 2 );

            add_action( 'restrict_manage_posts', array( $this, 'add_wholesale_role_order_listing_filter' ), 10, 1 ); // WordPress posts table.
            add_action( 'woocommerce_order_list_table_restrict_manage_orders', array( $this, 'add_wholesale_role_order_listing_filter' ), 10, 1 ); // WooCommerce orders tables.
            add_filter( 'parse_query', array( $this, 'wholesale_role_order_listing_filter' ), 10, 1 ); // WordPress posts table.
            add_filter( 'woocommerce_shop_order_list_table_prepare_items_query_args', array( $this, 'custom_order_tables_query_args' ), 10, 1 );  // WooCommerce orders tables.

            add_filter( 'woocommerce_can_reduce_order_stock', array( $this, 'prevent_reduce_stock_for_wholesale_order' ), 10, 2 );

            // Add customer to order.
            add_filter( 'woocommerce_ajax_get_customer_details', array( $this, 'wholesale_ajax_set_customer_to_order' ), 10, 3 );

            // Add order single page action.
            add_action( 'woocommerce_new_order_item', array( $this, 'wholesale_process_order_item' ), 11, 3 );
            add_action( 'woocommerce_update_order_item', array( $this, 'remove_wholesale_refund_action' ), 12, 3 );
            add_action( 'woocommerce_update_order_item', array( $this, 'wholesale_process_order_item' ), 13, 3 );

            // Procees order on save.
            add_action( 'woocommerce_update_order', array( $this, 'wholesale_woocommerce_update_order' ), 11, 2 );
            add_action( 'woocommerce_new_order', array( $this, 'wholesale_woocommerce_update_order' ), 11, 2 );

            // Persist the "Apply Wholesale Pricing" checkbox on admin order save. Priority 60 runs after
            // WC_Meta_Box_Order_Data::save (priority 40) so the customer is set and $order->save() persists
            // reliably here, unlike the woocommerce_update_order callback which fires after HPOS flushes meta.
            add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_apply_wholesale_pricing_checkbox' ), 60, 1 );

            // Process order on save using API.
            add_action( 'woocommerce_rest_insert_shop_order_object', array( $this, 'wholesale_woocommerce_create_order_api' ), 10, 2 );

            // Process order item after.
            add_action( 'wp_ajax_wwpp_order_data_item_after', array( $this, 'wholesale_process_order_item_after' ) );

            // Add custom checkbox to order.
            add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'wholesale_order_custom_checkbox' ), 10, 1 );

            // Invalidate the reports cache on any order status transition (covers admin, gateway callbacks, REST API, etc.).
            add_action( 'woocommerce_order_status_changed', array( $this, 'invalidate_reports_cache_on_status_change' ), 10, 3 );
        }
    }
}
