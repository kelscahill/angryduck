<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWPP_WC_Product_Addon' ) ) {

    /**
     * Model that houses the logic of integrating with 'WooCommerce Product Add-Ons Bundles' plugin.
     *
     * @since 1.13.0
     */
    class WWPP_WC_Product_Addon {

        /**
         * Property that holds the single main instance of WWPP_WC_Product_Addon.
         *
         * @since 1.13.0
         * @access private
         * @var WWPP_WC_Product_Addon
         */
        private static $_instance;

        /**
         * Model that houses the logic of retrieving information relating to wholesale role/s of a user.
         *
         * @since 1.13.0
         * @access private
         * @var WWPP_Wholesale_Roles
         */
        private $_wwpp_wholesale_roles;

        /**
         * Model that houses the logic of wholesale prices.
         *
         * @since 1.13.0
         * @access private
         * @var WWPP_Wholesale_Prices
         */
        private $_wwpp_wholesale_prices;

        /**
         * WWPP_WC_Product_Addon constructor.
         *
         * @since 1.13.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_WC_Product_Addon model.
         */
        public function __construct( $dependencies ) {
            $this->_wwpp_wholesale_roles  = $dependencies['WWPP_Wholesale_Roles'];
            $this->_wwpp_wholesale_prices = $dependencies['WWPP_Wholesale_Prices'];
        }

        /**
         * Ensure that only one instance of WWPP_WC_Product_Addon is loaded or can be loaded (Singleton Pattern).
         *
         * @since 1.13.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_WC_Product_Addon model.
         * @return WWPP_WC_Product_Addon
         */
        public static function instance( $dependencies ) {
            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Add addon role visibility custom fields.
         *
         * @since 1.10.0
         * @since 1.13.0 Refactor codebase and Move to its own model.
         * @access public
         *
         * @param WP_Post $post  Product object.
         * @param array   $addon Addon data.
         * @param int     $loop  Loop counter.
         */
        public function add_wwpp_addon_group_visibility_custom_fields( $post, $addon, $loop ) {
            $all_wholesale_roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();

            // When adding new addon dynamically, lets reset the $addon[ 'wwpp-addon-group-role-visibility' ] entry.
            // They have no hook we can attach to clear or reset custom fields we've added.
            if ( '' === $addon['name'] ) {
                $addon['wwpp-addon-group-role-visibility'] = array();
            }

            $filtered_wholesale_roles  = array( 'non-wholesale-customer' => array( 'roleName' => __( 'Non-Wholesale Customer', 'woocommerce-wholesale-prices-premium' ) ) );
            $filtered_wholesale_roles += $all_wholesale_roles;
            ?>

            <div class="wc-pao-addons-secondary-settings">

                <div class="wwpp-addon-group-role-visibility wc-pao-row" colspan="2">

                    <label
                        for="wwpp-addon-group-role-visibility_<?php echo esc_attr( $loop ); ?>"><?php esc_html_e( 'Specify what user roles this addon is visible. Leave blank to make addon visible to all user roles.', 'woocommerce-wholesale-prices-premium' ); ?></label>

                    <select id="wwpp-addon-group-role-visibility_<?php echo esc_attr( $loop ); ?>"
                        name="wwpp-addon-group-role-visibility[<?php echo esc_attr( $loop ); ?>][]" multiple="multiple"
                        data-placeholder="Select user roles..." class="wwpp-addon-group-role-visibility chosen-select">
                        <?php
                        foreach ( $filtered_wholesale_roles as $role_key => $role ) {

                            $selected = ( isset( $addon['wwpp-addon-group-role-visibility'] ) && is_array( $addon['wwpp-addon-group-role-visibility'] ) && in_array( $role_key, $addon['wwpp-addon-group-role-visibility'], true ) ) ? 'selected="selected"' : '';
                            ?>

                        <option value="<?php echo esc_attr( $role_key ); ?>" <?php echo esc_attr( $selected ); ?>><?php echo esc_html( $role['roleName'] ); ?></option>

                        <?php } ?>
                    </select>

                </div>

            </div>
        <?php
        }

        /**
         * Save addon role visibility custom fields.
         *
         * @since 1.10.0
         * @since 1.13.0 Move to its own model.
         * @access public
         *
         * @param array $data  Addon data.
         * @param int   $index Current addon index.
         */
        public function save_wwpp_addon_group_visibility_custom_fields( $data, $index ) {
            // phpcs:disable WordPress.Security.NonceVerification.Missing
            if ( isset( $_POST['wwpp-addon-group-role-visibility'][ $index ] ) ) {
                $data['wwpp-addon-group-role-visibility'] = $_POST['wwpp-addon-group-role-visibility'][ $index ];
            }

            return $data;
            // phpcs:enable WordPress.Security.NonceVerification.Missing
        }

        /**
         * Filters addon groups from the front end.
         *
         * @since 1.10.0
         * @since 1.13.0 Refactor codebase and move to its model.
         * @access public
         *
         * @param array $addons Addon data.
         * @return array
         */
        public function filter_wwpp_addon_groups( $addons ) {
            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            // Check if user is an admin we will show all addon groups regardless of the role restrictions.
            if ( ! current_user_can( 'manage_options' ) ) {
                foreach ( $addons as $key => $addon ) {
                    if ( isset( $addon['wwpp-addon-group-role-visibility'] ) && ! empty( $addon['wwpp-addon-group-role-visibility'] ) ) {

                        // Check the customer is logged in as a wholesale role OR if they are non-wholesale and we've specifically said to include non-wholesale in this addon group.
                        $role_to_check = ! empty( $user_wholesale_role ) ? $user_wholesale_role[0] : 'non-wholesale-customer';

                        // If the addon group has the role visibility meta set, then check to see if the current role is in the list.
                        if ( ! in_array( $role_to_check, $addon['wwpp-addon-group-role-visibility'], true ) ) {
                            // Remove the addon group from processing.
                            unset( $addons[ $key ] );
                        }
                    }
                }
            }

            return $addons;
        }

        /**
         * Change the product price attributes on the totals element that the addons plugin uses to calculate the grand total.
         * Fix the grand total calculation.
         *
         * @since 1.10.0
         * @since 1.13.0 Refactor codebase and move to its own model.
         * @since 1.14.6 Bug Fix. Grand total bug on variable products ( WWPP-416 ).
         * @access public
         *
         * @param int $product_id Product id.
         */
        public function change_wwpp_addon_product_price( $product_id ) {
            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            // Bit of a hack, but we need to adjust the price of the product to the wholesale price if we are logged in as a wholesale,
            // customer. The addons plugin uses JS to calculate it on the fly on the front end and to do that it stores the original,
            // product price on a special DIV near the add to cart button. There's no filters so the only way to get at it is via JS.
            if ( ! empty( $user_wholesale_role ) ) {

                $product                     = wc_get_product( $product_id );
                $variations_wholesale_prices = null;

                if ( WWP_Helper_Functions::wwp_get_product_type( $product ) === 'variable' ) {

                    $variations = WWP_Helper_Functions::wwp_get_variable_product_variations( $product );

                    foreach ( $variations as $variation ) {

                        $price_arr       = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $variation['variation_id'], $user_wholesale_role );
                        $wholesale_price = $price_arr['wholesale_price'];

                        if ( $wholesale_price ) {
                            $variations_wholesale_prices[ $variation['variation_id'] ] = $wholesale_price;
                        }
                    }
                } else {

                    $price_arr       = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $product_id, $user_wholesale_role );
                    $wholesale_price = $price_arr['wholesale_price'];

                }

                // Update the "product_variations" data property of "variation_form" markup to contain wholesale price instead.
                if ( $variations_wholesale_prices ) {
                ?>
                    <script type="text/javascript">
                        var variations_wholesale_prices = {
                                <?php
                                foreach ( $variations_wholesale_prices as $var_id => $wholesale_price ) {
                                    echo "$var_id : $wholesale_price,"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                }
                                ?>
                            },
                            product_variations_data = jQuery("form.variations_form").data("product_variations");

                        if (product_variations_data)
                            for (var i = 0; i < product_variations_data.length; i++)
                                product_variations_data[i].display_price = variations_wholesale_prices[product_variations_data[i].id ?
                                    product_variations_data[i].id : product_variations_data[i].variation_id];

                        jQuery("form.variations_form").data("product_variations", product_variations_data);
                    </script>

                <?php } elseif ( ! empty( $wholesale_price ) ) { ?>
                    <script type="text/javascript">
                    // We need to run this on document ready, not immediately.
                    // Coz now we are hooking on 9 execution order, so during this time #product-addons-total still not exists.
                    jQuery(document).ready(function() {

                        jQuery('#product-addons-total').data('price', '<?php echo esc_attr( $wholesale_price ); ?>');
                        jQuery('#product-addons-total').data('raw-price', '<?php echo esc_attr( $wholesale_price ); ?>');

                    });
                    </script>
                <?php
                }
            }
        }

        /**
         * Apply product add-on on top of the calculated wholesale price.
         *
         * @since 1.10.0
         * @since 1.13.0    Move to its own model.
         * @since 1.13.1    Bug fix. Return $wholesale_price instead of ''.
         * @since 1.20      Bug fix. Fix addon price types calculation.
         * @since 1.26      Support negative add-on price.
         *
         * @access public
         *
         * @param array $wholesale_price_arr Wholesale price array data.
         * @param int   $product_id          Product id.
         * @param array $user_wholesale_role Array of wholesale roles.
         * @param array $cart_item           Cart item.
         * @return array Filtered wholesale price array data.
         */
        public function apply_addon_to_cart_items( $wholesale_price_arr, $product_id, $user_wholesale_role, $cart_item ) {
            // Adjust price if addons are set.
            if ( ! empty( $wholesale_price_arr['wholesale_price'] ) && ! empty( $cart_item['addons'] ) && apply_filters( 'woocommerce_product_addons_adjust_price', true, $cart_item ) ) {

                // Set addons_price_before_calc cart meta to properly calculate the product addon price.
                if ( isset( $cart_item['key'] ) ) {
                    WC()->cart->cart_contents[ $cart_item['key'] ]['addons_price_before_calc'] = $wholesale_price_arr['wholesale_price'];
                }

                $extra_cost = 0;
                $quantity   = $cart_item['quantity'];

                foreach ( $cart_item['addons'] as $addon ) {

                    $price_type  = $addon['price_type'];
                    $addon_price = $addon['price'];

                    switch ( $price_type ) {
                        case 'percentage_based':
                            $extra_cost += (float) ( $wholesale_price_arr['wholesale_price'] * ( $addon_price / 100 ) );
                            break;
                        case 'flat_fee':
                            $extra_cost += (float) ( $addon_price / $quantity );
                            break;
                        default:
                            $extra_cost += (float) $addon_price;
                            break;
                    }
                }

                $wholesale_price_arr['wholesale_price'] += $extra_cost;

                return $wholesale_price_arr;

            } else {
                return $wholesale_price_arr;
            }
        }

        /**
         * Format Category and General/Per User Quantity Based Discount to match with Per Product format.
         *
         * @since 1.20
         * @access public
         *
         * @param array  $mapping Quantity based discount mapping.
         * @param string $level   Mapping level.
         * @return array|null
         */
        public function reformat_qty_mapping( $mapping, $level ) {
            switch ( $level ) {

                case 'category':
                    $cat_mapping = array();

                    if ( $mapping ) {

                        foreach ( $mapping as $key => $value ) {
                            if ( isset( $value['wholesale-role'] ) ) {
                                $cat_mapping[] = array(
                                    'wholesale_role'  => $value['wholesale-role'],
                                    'start_qty'       => $value['start-qty'],
                                    'end_qty'         => $value['end-qty'],
                                    'price_type'      => 'percent-price',
                                    'wholesale_price' => $value['wholesale-discount'],
                                );
                            }
                        }
                    }
                    $mapping = $cat_mapping;
                    break;

                case 'general': // General / User.
                    $general_mapping = array();

                    if ( $mapping ) {

                        foreach ( $mapping as $key => $value ) {
                            if ( isset( $value['wholesale_role'] ) ) {
                                $general_mapping[] = array(
                                    'wholesale_role'  => $value['wholesale_role'],
                                    'start_qty'       => $value['start_qty'],
                                    'end_qty'         => isset( $value['end_qty'] ) ? $value['end_qty'] : '',
                                    'price_type'      => 'percent-price',
                                    'wholesale_price' => $value['percent_discount'],
                                );
                            }
                        }
                    }
                    $mapping = $general_mapping;
                    break;

            }

            return ! empty( $mapping ) ? $mapping : null;
        }

        /**
         * Load frontend related styles and scripts.
         * Only load em on the right time and on the right place.
         *
         * @since 1.20
         * @access public
         */
        public function load_front_end_styles_and_scripts() {
            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            if ( is_product() && isset( $user_wholesale_role[0] ) ) {

                global $wc_wholesale_prices_premium;

                wp_enqueue_script( 'wwpp_addon_js', WWPP_JS_URL . 'app/wwpp-addon.js', array( 'jquery' ), $wc_wholesale_prices_premium::VERSION, true );
                wp_localize_script(
                    'wwpp_addon_js',
                    'wwpp_single_product_page_addon_params',
                    array(
                        'wholesale_role' => $user_wholesale_role[0],
                    )
                );

            }
        }

        /**
         * Get the product wholesale price.
         *
         * @since 1.26
         * @access public
         *
         * @param int $price      Product id.
         * @param int $product    Product Object.
         * @return int
         */
        public function get_wholesale_price( $price, $product ) {
            global $wc_wholesale_prices;
            $wholesale_role = $wc_wholesale_prices->wwp_wholesale_roles->getUserWholesaleRole();

            if ( ! empty( $wholesale_role ) ) {

                // Avoid infinite loop.
                remove_filter( 'woocommerce_product_get_price', array( $this, 'get_wholesale_price' ), 10, 2 );

                $price_arr = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $product->get_id(), $wholesale_role );

                if ( ! empty( $price_arr['wholesale_price'] ) ) {
                    return $price_arr['wholesale_price'];
                }
            }

            return $price;
        }

        /**
         * Override WC Addons checkout add meta price details to be based off the wholesale price not regular price.
         *
         * @since 1.26
         * @access public
         */
        public function checkout_addon_details() {
            global $Product_Addon_Cart;
            $wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            if ( ! empty( $wholesale_role ) ) {

                remove_action( 'woocommerce_checkout_create_order_line_item', array( $Product_Addon_Cart, 'order_line_item' ), 10, 3 );

                add_action(
                    'woocommerce_checkout_create_order_line_item',
                    function ( $item, $cart_item_key, $values ) {

                    global $Product_Addon_Cart;

                    add_filter( 'woocommerce_product_get_price', array( $this, 'get_wholesale_price' ), 10, 2 );
                    add_filter( 'woocommerce_product_variation_get_price', array( $this, 'get_wholesale_price' ), 10, 2 );

                    $item = $Product_Addon_Cart->order_line_item( $item, $cart_item_key, $values );

                    remove_filter( 'woocommerce_product_get_price', array( $this, 'get_wholesale_price' ), 10, 2 );
                    remove_filter( 'woocommerce_product_variation_get_price', array( $this, 'get_wholesale_price' ), 10, 2 );
                    },
                    10,
                    3
                );

            }
        }

        /**
         * Override display price suffix if spacial tags {price_including_tax} and {price_excluding_tax} are detected
         * so that the subtotal price of simpe product that have product addons in single products page will be set to
         * wholesale price instead of regular product price.
         *
         * @since 1.27.1
         * @access public
         *
         * @param array $params Array of parameters.
         * @return array $params
         */
        public function override_display_price_suffix_parameter( $params ) {
            global $wc_wholesale_prices;

            $user_wholesale_role = $wc_wholesale_prices->wwp_wholesale_roles->getUserWholesaleRole();

            $wholesale_price_suffix = get_option( 'wwpp_settings_override_price_suffix' );

            if ( ! empty( $user_wholesale_role ) ) {

                if ( ( strpos( $params['price_display_suffix'], '{price_including_tax}' ) !== false || strpos( $params['price_display_suffix'], '{price_excluding_tax}' ) !== false ) && ( strpos( $wholesale_price_suffix, '{price_including_tax}' ) !== false || strpos( $wholesale_price_suffix, '{price_excluding_tax}' ) !== false ) ) {

                    $params['price_display_suffix'] = '';

                } else {

                    $params['price_display_suffix'] = $wholesale_price_suffix;

                }
            }

            return $params;
        }

        /**
         * Override ajax get product price excluding tax.
         *
         * @since 1.30.4
         * @access public
         *
         * @param int   $product_price_excl Product price excluding tax.
         * @param int   $qty                Product quantity.
         * @param array $product            Product object.
         * @return int $product_price_incl
         */
        public function wwpp_product_addons_ajax_product_price_excluding_tax( $product_price_excl, $qty, $product ) {
            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            if ( ! empty( $user_wholesale_role ) ) {

                $price_arr       = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $product->get_id(), $user_wholesale_role );
                $wholesale_price = $price_arr['wholesale_price_with_no_tax'];
                if ( $wholesale_price ) {
                    $product_price_excl = $wholesale_price * $qty;
                }
            }

            return $product_price_excl;
        }

        /**
         * Override ajax get product price including tax.
         *
         * @since 1.30.4
         * @access public
         *
         * @param int   $product_price_incl Product price including tax.
         * @param int   $qty                Product quantity.
         * @param array $product            Product object.
         * @return int $product_price_incl
         */
        public function wwpp_product_addons_ajax_product_price_including_tax( $product_price_incl, $qty, $product ) {
            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            if ( ! empty( $user_wholesale_role ) ) {

                $price_arr       = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $product->get_id(), $user_wholesale_role );
                $wholesale_price = $price_arr['wholesale_price_with_tax'];
                if ( $wholesale_price ) {
                    $product_price_excl = $wholesale_price * $qty;
                }
            }

            return $product_price_excl;
        }

        /**
         * Execute model.
         *
         * @since 1.13.0
         * @access public
         */
        public function run() {
            if ( WWP_Helper_Functions::is_plugin_active( 'woocommerce-product-addons/woocommerce-product-addons.php' ) ) {

                add_action( 'woocommerce_product_addons_panel_before_options', array( $this, 'add_wwpp_addon_group_visibility_custom_fields' ), 10, 3 );
                add_filter( 'woocommerce_product_addons_save_data', array( $this, 'save_wwpp_addon_group_visibility_custom_fields' ), 10, 2 );
                add_action( 'get_product_addons', array( $this, 'filter_wwpp_addon_groups' ), 10, 1 );
                add_action( 'woocommerce_product_addons_end', array( $this, 'change_wwpp_addon_product_price' ), 8, 1 ); // Well have to run our code first.

                // Apply add on price on cart items,
                // We run this late, we let wwpp to apply necessary wholesale pricing calculation, after that,
                // we add the add-on price on top of the calculated wholesale price.
                add_filter( 'wwp_filter_wholesale_price_cart', array( $this, 'apply_addon_to_cart_items' ), 500, 4 );

                // Addon scripts.
                add_action( 'wp_enqueue_scripts', array( $this, 'load_front_end_styles_and_scripts' ), 10 );

                // Checkout addon meta details.
                add_action( 'init', array( $this, 'checkout_addon_details' ) );

                // Override price display suffix.
                add_filter( 'woocommerce_product_addons_params', array( $this, 'override_display_price_suffix_parameter' ), 10, 1 );

                // Override ajax get product price.
                add_filter( 'woocommerce_product_addons_ajax_get_product_price_excluding_tax', array( $this, 'wwpp_product_addons_ajax_product_price_excluding_tax' ), 10, 3 );
                add_filter( 'woocommerce_product_addons_ajax_get_product_price_including_tax', array( $this, 'wwpp_product_addons_ajax_product_price_including_tax' ), 10, 3 );

            }
        }
    }

}
