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

            $current_order->save();
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
                    $wwpp_fbwr = $_GET['wwpp_fbwr'];
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
                $wholesale_filter = trim( $_GET['wwpp_fbwr'] );
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
                                'value'   => $_GET['_customer_user'],
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

            if ( isset( $_GET['wwpp_fbwr'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $wholesale_filter = trim( $_GET['wwpp_fbwr'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
                $order_id = $_POST['order_id']; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification done in WooCommerce core
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
         * Process order item data.
         *
         * @since 1.14.0
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

                            if ( ! empty( $post_variables['apply_wholesale'] ) && 'true' === $post_variables['apply_wholesale'] ) {
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
         * Process order item after.
         *
         * @since 1.14.0
         * @access public
         *
         * @return void
         */
        public function wholesale_process_order_item_after() {
            check_ajax_referer( 'wwpp_order_nonce', 'security' );

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

            $pass_to_check = false;
            if ( ! empty( $post_variables['apply_wholesale'] ) ) {
                $apply_wholesale        = $order_obj->get_meta( '_apply_wholesale_pricing_manually', true );
                $apply_wholesale_manual = 'yes';
                if ( 'true' === $post_variables['apply_wholesale'] ) {
                    $pass_to_check = true;
                } elseif ( 'false' === $post_variables['apply_wholesale'] ) {
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

            if ( $pass_to_check ) {
                if ( $customer_id ) {
                    $customer = new WC_Customer( $customer_id );
                    if ( $customer ) {
                        $customer_role = $customer->get_role();
                    }
                }

                // Check if user is wholesale customers.
                if ( ! empty( $customer_role ) && array_key_exists( $customer_role, $wholesale_roles ) ) {
                    $items = $this->wholesale_get_formatted_items( $order_obj );
                    $order = $this->wholesale_calc_line_totals( $post_variables, $order_id, $items );

                    $template_path = WC_ABSPATH . 'includes/admin/meta-boxes/views/html-order-items.php';
                    include $template_path;
                }
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
        public function wholesale_calc_line_totals( $post_variables, $order_id, $items ) {
            // Save order items first.
            wc_save_order_items( $order_id, $items );

            $calculate_tax_args = array(
                'country'  => isset( $post_variables['country'] ) ? wc_strtoupper( wc_clean( wp_unslash( $post_variables['country'] ) ) ) : '',
                'state'    => isset( $post_variables['state'] ) ? wc_strtoupper( wc_clean( wp_unslash( $post_variables['state'] ) ) ) : '',
                'postcode' => isset( $post_variables['postcode'] ) ? wc_strtoupper( wc_clean( wp_unslash( $post_variables['postcode'] ) ) ) : '',
                'city'     => isset( $post_variables['city'] ) ? wc_strtoupper( wc_clean( wp_unslash( $post_variables['city'] ) ) ) : '',
            );

            $order_obj = wc_get_order( $order_id );

            // Check if $order_obj is valid before proceeding.
            if ( ! $order_obj || ! is_a( $order_obj, 'WC_Order' ) ) {
                return false;
            }

            $order_obj->calculate_taxes( $calculate_tax_args );
            $order_obj->calculate_totals( false );

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
                    <label for="apply_wholesale_pricing" style="display: inline-block;"><?php esc_html_e( 'Apply Wholesale Pricing', 'woocommerce-wholesale-prices-premium' ); ?></label>
                </span>
            </p>
            <p><em><?php esc_html_e( 'This option will only apply if you select wholesale customer.', 'woocommerce-wholesale-prices-premium' ); ?></em></p>
            <?php
        }

        /**
         * Execute model.
         *
         * @since 1.14.0
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

            add_action( 'restrict_manage_posts', array( $this, 'add_wholesale_role_order_listing_filter' ), 10, 1 ); // WordPress posts table.
            add_action( 'woocommerce_order_list_table_restrict_manage_orders', array( $this, 'add_wholesale_role_order_listing_filter' ), 10, 1 ); // WooCommerce orders tables.
            add_filter( 'parse_query', array( $this, 'wholesale_role_order_listing_filter' ), 10, 1 ); // WordPress posts table.
            add_filter( 'woocommerce_shop_order_list_table_prepare_items_query_args', array( $this, 'custom_order_tables_query_args' ), 10, 1 );  // WooCommerce orders tables.

            add_filter( 'woocommerce_can_reduce_order_stock', array( $this, 'prevent_reduce_stock_for_wholesale_order' ), 10, 2 );

            // Add customer to order.
            add_filter( 'woocommerce_ajax_get_customer_details', array( $this, 'wholesale_ajax_set_customer_to_order' ), 10, 3 );

            // Add order single page action.
            add_action( 'woocommerce_new_order_item', array( $this, 'wholesale_process_order_item' ), 11, 3 );
            add_action( 'woocommerce_update_order_item', array( $this, 'wholesale_process_order_item' ), 11, 3 );

            // Procees order on save.
            add_action( 'woocommerce_update_order', array( $this, 'wholesale_woocommerce_update_order' ), 11, 2 );
            add_action( 'woocommerce_new_order', array( $this, 'wholesale_woocommerce_update_order' ), 11, 2 );

            // Process order on save using API.
            add_action( 'woocommerce_rest_insert_shop_order_object', array( $this, 'wholesale_woocommerce_create_order_api' ), 10, 2 );

            // Process order item after.
            add_action( 'wp_ajax_wwpp_order_data_item_after', array( $this, 'wholesale_process_order_item_after' ) );

            // Add custom checkbox to order.
            add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'wholesale_order_custom_checkbox' ), 10, 1 );
        }
    }
}
