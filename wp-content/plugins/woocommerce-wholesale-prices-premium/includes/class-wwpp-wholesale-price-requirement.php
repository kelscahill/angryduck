<?php
// Exit if accessed directly.
use Automattic\WooCommerce\StoreApi\Routes\V1\Cart;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWPP_Wholesale_Price_Requirement' ) ) {

    /**
     * Model that handles the checking if a wholesale user meets the requirements of having wholesale price.
     *
     * @since 1.12.8
     */
    class WWPP_Wholesale_Price_Requirement {

        /**
         * Child quantity input-name tokens emitted by WC Product Bundles / WC Composite Products in
         * their (private) markup. Detection of bundle/composite child quantity inputs relies on these,
         * so the third-party coupling lives in one place — if either integration renames its field the
         * match must be updated here.
         *
         * @since 2.1.1
         */
        const BUNDLED_QTY_INPUT_NAME   = 'bundle_quantity_';
        const COMPOSITE_QTY_INPUT_NAME = 'wccp_component_quantity';

        /**
         * Class Properties
         */

        /**
         * Property that holds the single main instance of WWPP_Wholesale_Price_Requirement.
         *
         * @since  1.12.8
         * @access private
         * @var WWPP_Wholesale_Price_Requirement
         */
        private static $_instance;

        /**
         * Model that houses the logic of retrieving information relating to wholesale role/s of a user.
         *
         * @since  1.12.8
         * @access private
         * @var WWPP_Wholesale_Roles
         */
        private $_wwpp_wholesale_roles;

        /**
         * Class attribute that houses bundle product items from a given cart.
         *
         * @since  1.15.0
         * @access public
         * @var array
         */
        private $_bundle_product_items;

        /**
         * Class attribute that houses composite product items from a given cart.
         *
         * @since  1.15.0
         * @access public
         * @var array
         */
        private $_composite_product_items;

        /**
         * Preload cart variation result
         *
         * @var array
         */
        private $cart_variation_result = array();

        /**
         * Preload variation attributes.
         *
         * @var array
         */
        private $cart_variation_attributes = array();


        /**
         * Cart variation quantity total.
         *
         * @var  array
         */
        private $cart_variation_qty_total = array();

        /**
         * Render-nesting depth for bundled (WC Product Bundles) / composite (WC Composite Products)
         * child items currently rendering on the single product page. Incremented while those
         * integrations render their child items — including each child's price html — so the
         * standalone wholesale minimum-order-quantity label can be suppressed for the child (the
         * parent controls the child's quantity, so the standalone MOQ is not enforced for it).
         *
         * A depth counter (rather than a bool) so that a composite containing a bundle component —
         * or any nested bundle/composite — keeps the label suppressed for every child: an inner
         * render closing does not prematurely re-enable the label for the outer parent's remaining
         * children.
         *
         * @since  2.1.1
         * @access private
         * @var    int
         */
        private $bundle_or_composite_child_render_depth = 0;

        /**
         * Class Methods
         */

        /**
         * WWPP_Wholesale_Price_Requirement constructor.
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Wholesale_Price_Requirement model.
         *
         * @since  1.12.8
         * @access public
         */
        public function __construct( $dependencies ) {

            $this->_wwpp_wholesale_roles = $dependencies['WWPP_Wholesale_Roles'];
        }

        /**
         * Ensure that only one instance of WWPP_Wholesale_Price_Requirement is loaded or can be loaded (Singleton Pattern).
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Wholesale_Price_Requirement model.
         *
         * @since  1.12.8
         * @access public
         *
         * @return WWPP_Wholesale_Price_Requirement
         */
        public static function instance( $dependencies ) {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /*
        |--------------------------------------------------------------------------
        | Minimum Order Quantity Requirement
        |--------------------------------------------------------------------------
         */

        /**
         * Filter the price to show the minimum order quantity for wholesale users for this specific product.
         *
         * @param string     $wholesale_price_html Wholesale price markup.
         * @param float      $price                Product price.
         * @param WC_Product $product              Product object.
         * @param array      $user_wholesale_role  Array of user wholesale roles.
         *
         * @since  1.4.0
         * @since  1.14.0 Refactor codebase and move to its proper model.
         * @since  1.14.5 Bug Fix. Per parent variable product minimum wholesale order quantity requirement not shown. (WWPP-417).
         * @since  1.15.3 Improvement. Move the variable product min order requirement label onto the variable product price instead of the variation price.
         * @since  1.16.0 Add support for wholesale order quantity step.
         * @since  1.30.2 Add filter hook to allow 3rd party to modify the minimum order quantity requirement label.
         * @since  2.1.1  Skip the label while rendering a bundled/composite child item.
         * @access public
         *
         * @return string Filtered wholesale price markup.
         */
        public function display_minimum_wholesale_order_quantity( $wholesale_price_html, $price, $product, $user_wholesale_role ) {

            // Bundled (WC Product Bundles) and composite (WC Composite Products) child items have their
            // quantity controlled by the parent, so the standalone wholesale minimum order quantity is not
            // enforced for them. Rendering the "Min: X" / "Increments of X" label against a child's price on
            // the bundle/composite product page is therefore misleading, so suppress it here.
            if ( $this->is_rendering_bundle_or_composite_child() ) {
                return $wholesale_price_html;
            }

            if ( ! empty( $user_wholesale_role ) ) {

                $min_wholesale_order_qty_html = '';
                $product_type                 = $product->get_type();

                if ( 'variable' === $product_type ) {

                    $minimum_order  = $product->get_meta( $user_wholesale_role[0] . '_variable_level_wholesale_minimum_order_quantity', true );
                    $order_qty_step = $product->get_meta( $user_wholesale_role[0] . '_variable_level_wholesale_order_quantity_step', true );

                } else {

                    // variation and simple.
                    $minimum_order  = $product->get_meta( $user_wholesale_role[0] . '_wholesale_minimum_order_quantity', true );
                    $order_qty_step = $product->get_meta( $user_wholesale_role[0] . '_wholesale_order_quantity_step', true );

                }

                if ( isset( $minimum_order ) && $minimum_order > 0 ) {

                    if ( 'variable' === $product_type ) {
                        /* translators: %1$s Minimum order quantity */
                        $min_wholesale_order_qty_html .= ' <span class="wholesale_price_minimum_order_quantity" style="display: block;">' . sprintf( __( 'Min: %1$s of any variation combination', 'woocommerce-wholesale-prices-premium' ), $minimum_order ) . '</span>';
                    } else { // variation and simple.
                        /* translators: %1$s Minimum order quantity */
                        $min_wholesale_order_qty_html .= ' <span class="wholesale_price_minimum_order_quantity" style="display: block;">' . sprintf( __( 'Min: %1$s', 'woocommerce-wholesale-prices-premium' ), $minimum_order ) . '</span>';
                    }

                    if ( isset( $order_qty_step ) && $order_qty_step > 0 ) {

                        if ( 'variable' === $product_type ) {
                            /* translators: %1$s Order quantity step */
                            $min_wholesale_order_qty_html .= ' <span class="wholesale_price_order_quantity_step" style="display: block;">' . sprintf( __( 'Increments of %1$s', 'woocommerce-wholesale-prices-premium' ), $order_qty_step ) . '</span>';
                        } else {
                            /* translators: %1$s Order quantity step */
                            $min_wholesale_order_qty_html .= ' <span class="wholesale_price_order_quantity_step" style="display: block;">' . sprintf( __( 'Increments of %1$s', 'woocommerce-wholesale-prices-premium' ), $order_qty_step ) . '</span>';
                        }
                    }
                }

                // check if min_wholesale_order_qty_html string is not empty.
                if ( ! empty( $min_wholesale_order_qty_html ) ) {
                    $wholesale_price_html = $wholesale_price_html . apply_filters( 'wwp_display_wholesale_price_minimum_order_quantity', $min_wholesale_order_qty_html, $product, $product_type, $user_wholesale_role, $minimum_order, $order_qty_step );
                }
            }

            return $wholesale_price_html;
        }

        /**
         * Mark the start of a bundled/composite child item render.
         *
         * Hooked to WC Product Bundles' 'woocommerce_before_bundled_items' and WC Composite Products'
         * 'woocommerce_composite_before_components' actions, which bracket the rendering of the child
         * items on a bundle/composite single product page (including each child's price html). While
         * the render depth is above zero, display_minimum_wholesale_order_quantity() suppresses the
         * standalone wholesale minimum order quantity label for the child being rendered. Incrementing
         * (rather than setting a bool) keeps nested bundle/composite renders balanced.
         *
         * @since  2.1.1
         * @access public
         */
        public function mark_bundle_or_composite_child_render_start() {
            ++$this->bundle_or_composite_child_render_depth;
        }

        /**
         * Mark the end of a bundled/composite child item render.
         *
         * Hooked to WC Product Bundles' 'woocommerce_after_bundled_items' and WC Composite Products'
         * 'woocommerce_composite_after_components' actions. Decrements the render depth, clamped at
         * zero so an unbalanced close cannot drive it negative.
         *
         * @since  2.1.1
         * @access public
         */
        public function mark_bundle_or_composite_child_render_end() {
            $this->bundle_or_composite_child_render_depth = max( 0, $this->bundle_or_composite_child_render_depth - 1 );
        }

        /**
         * Whether a bundled (WC Product Bundles) or composite (WC Composite Products) child item is
         * currently being rendered on the single product page.
         *
         * @since  2.1.1
         * @access public
         *
         * @return bool True while a bundle/composite child item is being rendered.
         */
        public function is_rendering_bundle_or_composite_child() {
            return $this->bundle_or_composite_child_render_depth > 0;
        }

        /**
         * Strip the standalone wholesale minimum-order-quantity label from a variation's price html
         * while its variation data is being built for a bundled/composite child.
         *
         * For variable children, WooCommerce bakes each variation's price html (with the "Min: X"
         * label appended by display_minimum_wholesale_order_quantity()) into the variation data that
         * ships to the front end — the inline `data-product_variations` JSON, or the `wc-ajax=get_variation`
         * response. That price html is built and cached the first time `get_available_variations()` runs,
         * which can be *before* WC PB / WC Composite set the render-depth flag (e.g. for a child that is
         * NOT priced individually, the price is computed during the parent's aggregate calculation). So
         * the early-return guard in display_minimum_wholesale_order_quantity() can't catch it. This filter
         * runs while the integrations build the child's variation data (the depth flag is set via their
         * price-filter brackets) and removes the already-baked label from the markup. Standalone variable
         * products are unaffected — the flag is false there, so the label is left intact.
         *
         * @since  2.1.1
         * @access public
         *
         * @param array $variation_data The variation data passed to the front end (includes price_html).
         *
         * @return array Filtered variation data.
         */
        public function strip_min_order_qty_label_from_bundled_variation( $variation_data ) {

            if ( $this->is_rendering_bundle_or_composite_child() && ! empty( $variation_data['price_html'] ) && false !== strpos( $variation_data['price_html'], 'wholesale_price_minimum_order_quantity' ) ) {
                $variation_data['price_html'] = preg_replace(
                    '/\s*<span class="wholesale_price_(?:minimum_order_quantity|order_quantity_step)"[^>]*>.*?<\/span>/s',
                    '',
                    $variation_data['price_html']
                );
            }

            return $variation_data;
        }

        /**
         * Set order quantity attribute values for non variable product if one is set.
         *
         * @param array      $args    Quantity field input args.
         * @param WC_Product $product Product object.
         *
         * @since  1.4.2
         * @since  1.14.0 Refactor codebase and move to its proper model.
         * @since  1.14.5 Bug Fix. Per parent variable product minimum wholesale order quantity requirement not shown. (WWPP-417).
         * @since  1.15.3 Silence notices thrown by function is_shop.
         * @since  1.16.0 Supports wholesale order quantity step.
         * @since  1.24   Fix Wholesale Minimum Order Quantity and Wholesale Order Quantity Step not working on Cart and Checkout.
         * @since  1.30.1 Fix Allow add to cart below product minimum is not working if the min qty has its qty step.
         * @since  2.0.3  Check if variable product is a valid product object.
         * @since  2.1.1  Skip override for bundled/composite child quantity inputs. (WWPP-1828)
         * @access public
         *
         * @return array Filtered quantity field input args.
         */
        public function set_order_quantity_attribute_values( $args, $product ) {

            if ( is_null( $product ) ) {
                $product = $GLOBALS['product'];
            }

            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();
            $filtered_args       = $args;

            // Remove restriction for Wholesale Minimum Order Quantity and Wholesale Order Quantity Step on cart and checkout
            // Should work on Single product, cart and checkout page or any page using 'woocommerce_quantity_input_args' filter.
            // Check if product is a valid product object.
            //
            // Bundled (WC Product Bundles) and composite (WC Composite Products) child items manage their
            // own quantity (the parent controls it), so skip the wholesale minimum order quantity override
            // for their quantity inputs — both integrations render those inputs through this same filter.
            // Skipping leaves $filtered_args === $args, which the shared return at the end of this method
            // passes through unchanged (WWPP-1828).
            if ( ! $this->is_bundled_or_composite_quantity_input( $filtered_args )
                && ! empty( $user_wholesale_role ) && $product && is_a( $product, 'WC_Product' ) ) {

                // No need for variable product, we don't need it be applied on a price range
                // We need it to be applied per variation for variable products.
                if ( 'variable' !== $product->get_type() ) {

                    $price_arr       = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $product->get_id(), $user_wholesale_role );
                    $wholesale_price = $price_arr['wholesale_price'];

                    if ( $wholesale_price ) {

                        $allow_add_to_cart_below_product_minimum = get_option( 'wwpp_settings_allow_add_to_cart_below_product_minimum', false );
                        $minimum_order_qty                       = $product->get_meta( $user_wholesale_role[0] . '_wholesale_minimum_order_quantity', true );
                        if ( 'variation' === $product->get_type() && ! $minimum_order_qty ) {
                            $minimum_order_qty = get_post_meta( $product->get_parent_id(), $user_wholesale_role[0] . '_variable_level_wholesale_minimum_order_quantity', true );
                        }

                        if ( $minimum_order_qty ) {

                            if ( is_cart() ) {

                                $filtered_args['min_value'] = 0;

                            } else {

                                // Have minimum order quty
                                // For other pages other than cart.
                                $filtered_args['min_value']   = 1;
                                $filtered_args['input_value'] = $minimum_order_qty;

                            }

                            $order_qty_step = $product->get_meta( $user_wholesale_role[0] . '_wholesale_order_quantity_step', true );
                            if ( 'variation' === $product->get_type() && ! $order_qty_step ) {
                                $order_qty_step = get_post_meta( $product->get_parent_id(), $user_wholesale_role[0] . '_variable_level_wholesale_order_quantity_step', true );
                            }

                            if ( $order_qty_step ) {

                                /**
                                 * Step will require min qty to be set. If step is set, but min is not, step will be voided.
                                 *
                                 * Ok explanation as to why doing this.
                                 *
                                 * HTML 5 have this behavior for number fields.
                                 * -> If step value is greater or equal than input value, it will base off of min value
                                 * ----> Ex. min : 1 , value : 10 , step : 10 , if you click up arrow key once, the value becomes 11, not 20, coz 1 ( min ) + 10 ( step ) = 11
                                 * -> If step value is less than the input value, it will base off of input value
                                 * ----> Ex. min : 1 , value : 10 , step : 9 , if you click up arrow key once, the value becomes 19, not 10, coz 10 ( input value ) + 9 ( step ) = 19
                                 *
                                 * So to resolve this unexpected behavior, we either set min as blank or value of zero.
                                 * Setting min as blank will allow them to order quantity less than and equal zero. ( but ordering qty less than zero will not add item to cart ).
                                 * Setting min as zero allows them to order quantity with value of zero ( but it will only add 1 qty of this product to cart,  this is similar to shop page, where you can add item without specifying the qty ).
                                 * Setting the min to the min we set however will solve this issue.
                                 *
                                 * Setting value of min to zero or blank will still not allow them to order lower than min qty anyways, that is not within the step multiplier.
                                 *
                                 * So setting step will prevent wholesale customers from buying lower than min qty.
                                 */

                                $filtered_args['step']      = 1;
                                $filtered_args['min_value'] = 0;

                                // If in cart page and "Enforce Min/Step On Cart" option is enabled then dont allow setting below the min qty.
                                // If "Enforce Min/Step On Cart" is disabled, allow setting below the min qty.
                                // If user is in other page than cart then always enforce, which is the original behavior.
                                if ( ( is_cart() && get_option( 'wwpp_settings_enforce_product_min_step_on_cart_page' ) === 'yes' ) || ! is_cart() ) {

                                    // If Allow Add To Cart Below Product Minimum setting is enabled, don't restrict minimum qty.
                                    if ( 'yes' !== $allow_add_to_cart_below_product_minimum ) {
                                        $filtered_args['min_value'] = $minimum_order_qty;
                                    } else {
                                        // Set the min value to the smallest divident of order qty step and min order qty value but avoid zero value.
                                        $filtered_args['min_value'] = $order_qty_step === $minimum_order_qty ? $minimum_order_qty : min( $order_qty_step, $minimum_order_qty ) % max( $order_qty_step, $minimum_order_qty );
                                    }

                                    $filtered_args['step'] = $order_qty_step;

                                }
                            }
                        }
                    }
                }
            }

            return apply_filters( 'wwpp_filter_set_product_quantity_value_to_minimum_order_quantity', $filtered_args, $args, $product, $user_wholesale_role );
        }

        /**
         * Check if the quantity input args belong to a bundled (WC Product Bundles) or composite
         * (WC Composite Products) child item.
         *
         * Both integrations render their child quantity inputs through the
         * 'woocommerce_quantity_input_args' filter using a consistent input name convention:
         * 'bundle_quantity_{id}' for bundled items and 'wccp_component_quantity[{id}]' for composite
         * components.
         *
         * @param array $args Quantity field input args.
         *
         * @since  2.1.1
         * @access private
         *
         * @return bool True if the input belongs to a bundled/composite child item.
         */
        private function is_bundled_or_composite_quantity_input( $args ) {

            if ( empty( $args['input_name'] ) || ! is_string( $args['input_name'] ) ) {
                return false;
            }

            return false !== strpos( $args['input_name'], self::BUNDLED_QTY_INPUT_NAME )
                || false !== strpos( $args['input_name'], self::COMPOSITE_QTY_INPUT_NAME );
        }

        /**
         * Set minimum order quantity requirement for product.
         *
         * @param WC_Cart $cart Cart object.
         *
         * @since  2.0.1
         * @since  2.1.1 Skip enforcement on bundled/composite child cart items. (WWPP-1828)
         * @access public
         */
        public function set_cart_item_quantity( $cart ) {
            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            if ( ! empty( $user_wholesale_role ) && 'yes' === get_option( 'wwpp_settings_enforce_product_min_step_on_cart_page' ) ) {
                // Check if cart is not empty.
                if ( ! WC()->cart->is_empty() ) {
                    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
                        // Bundled (WC Product Bundles) and composite (WC Composite Products) child
                        // items have their quantity controlled by the parent, so the wholesale
                        // minimum order quantity must not be enforced on them here. This exemption is
                        // unconditional, unlike filter_if_cart_item_is_included_on_cart_totals_computation(),
                        // which only exempts children that are NOT priced individually.
                        if ( ! empty( $cart_item['bundled_by'] ) || ! empty( $cart_item['composite_parent'] ) ) {
                            continue;
                        }

                        $product_id   = $cart_item['data']->get_id();
                        $product_type = $cart_item['data']->get_type();

                        // Get quantity.
                        $quantity          = $cart_item['quantity'];
                        $minimum_order_qty = get_post_meta( $product_id, $user_wholesale_role[0] . '_wholesale_minimum_order_quantity', true );

                        if ( 'variation' === $product_type ) {
                            $parent_variable_id       = $cart_item['data']->get_parent_id();
                            $parent_minimum_order_qty = get_post_meta( $parent_variable_id, $user_wholesale_role[0] . '_variable_level_wholesale_minimum_order_quantity', true );

                            $related_variable_quantities    = $this->get_variable_cart_item_quantities( $parent_variable_id, $user_wholesale_role );
                            $counted_variant_quantities     = $related_variable_quantities['counted_variant_quantities'];
                            $not_counted_variant_quantities = $related_variable_quantities['not_counted_variant_quantities'];

                            if ( ! empty( $counted_variant_quantities ) && count( $counted_variant_quantities ) > 1 ) {
                                $total_counted_variant_qty = ! empty( $counted_variant_quantities ) ? array_sum( $counted_variant_quantities ) : 0;
                                if ( $total_counted_variant_qty < $parent_minimum_order_qty && ! isset( $not_counted_variant_quantities[ $product_id ] ) ) {
                                    // Unset the current variation.
                                    unset( $counted_variant_quantities[ $product_id ] );

                                    // Get the quantities of related variations.
                                    $counted_related_quantities = ! empty( $counted_variant_quantities ) ? array_sum( $counted_variant_quantities ) : 0;

                                    $remaining_qty = $parent_minimum_order_qty - $counted_related_quantities;

                                    // Set quantity to minimum order quantity.
                                    $cart->cart_contents[ $cart_item_key ]['quantity'] = $remaining_qty;
                                } elseif ( isset( $not_counted_variant_quantities[ $product_id ] ) && ! empty( $minimum_order_qty ) && $quantity < $minimum_order_qty ) {
                                    // Set quantity to minimum order quantity.
                                    $cart->cart_contents[ $cart_item_key ]['quantity'] = $minimum_order_qty;
                                }
                            } elseif ( ! empty( $minimum_order_qty ) && $quantity < $minimum_order_qty ) {
                                // Set quantity to minimum order quantity.
                                $cart->cart_contents[ $cart_item_key ]['quantity'] = $minimum_order_qty;
                            }
                        } elseif ( ! empty( $minimum_order_qty ) && $quantity < $minimum_order_qty ) {
                            // Set quantity to minimum order quantity.
                            $cart->cart_contents[ $cart_item_key ]['quantity'] = $minimum_order_qty;
                        }
                    }
                }
            }
        }

        /**
         * Get the variable cart item quantities.
         *
         * @param int   $variable_id Variable product ID.
         * @param array $user_wholesale_role User wholesale role.
         *
         * @since  2.0.1
         * @since  2.1.1 Skip bundled/composite child cart items so they don't inflate the aggregation.
         * @access private
         *
         * @return array
         */
        private function get_variable_cart_item_quantities( $variable_id, $user_wholesale_role ) {
            $counted_variant_quantities     = array();
            $not_counted_variant_quantities = array();
            $variable_cart_item_quantities  = array();
            $cart                           = WC()->cart->get_cart();

            foreach ( $cart as $cart_item_key => $cart_item ) {
                // Bundled (WC Product Bundles) and composite (WC Composite Products) child items have their
                // quantity controlled by the parent, so they must not count toward a standalone variable
                // product's wholesale minimum order quantity aggregation.
                if ( ! empty( $cart_item['bundled_by'] ) || ! empty( $cart_item['composite_parent'] ) ) {
                    continue;
                }

                $product_id         = $cart_item['data']->get_id();
                $parent_variable_id = $cart_item['data']->get_parent_id();
                $product_type       = $cart_item['data']->get_type();

                $minimum_order_qty = get_post_meta( $product_id, $user_wholesale_role[0] . '_wholesale_minimum_order_quantity', true );

                if ( 'variation' === $product_type && $parent_variable_id === $variable_id ) {
                    $counted_variant_quantities[ $product_id ] = $cart_item['quantity'];
                }

                // Set not counted variant quantities.
                if ( ! empty( $minimum_order_qty ) && 'variation' === $product_type && $parent_variable_id === $variable_id ) {
                    $not_counted_variant_quantities[ $product_id ] = $cart_item['quantity'];
                }
            }

            return array(
                'counted_variant_quantities'     => $counted_variant_quantities,
                'not_counted_variant_quantities' => $not_counted_variant_quantities,
            );
        }

        /**
         * Mute the min max plugin min qty rule if the current user is a wholesale customer and have its own min qty rule.
         * Basically WWPP overrides min max plugin min set for this product for this wholesale customer.
         *
         * @param int $min_max_min_rule Min max plugin min rule.
         * @param int $product_id       Product ID.
         *
         * @since  1.16.1
         * @access public
         */
        public function mute_min_max_plugin_min_qty_rule( $min_max_min_rule, $product_id ) {

            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            if ( empty( $user_wholesale_role ) ) {
                return $min_max_min_rule;
            }

            $price_arr       = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $product_id, $user_wholesale_role );
            $wholesale_price = $price_arr['wholesale_price'];

            if ( ! $wholesale_price ) {
                return $min_max_min_rule;
            }

            $minimum_order_qty = get_post_meta( $product_id, $user_wholesale_role[0] . '_wholesale_minimum_order_quantity', true );

            if ( $minimum_order_qty ) {
                return '';
            } else {
                return $min_max_min_rule;
            }
        }

        /**
         * Mute the min max plugin order qty rule if the current user is a wholesale customer and have its own step qty rule.
         * Basically WWPP overrides min max plugin min set for this product for this wholesale customer.
         *
         * @param int $min_max_group_rule Min max plugin min rule.
         * @param int $product_id         Product ID.
         *
         * @since  1.16.1
         * @access public
         */
        public function mute_min_max_plugin_group_qty_rule( $min_max_group_rule, $product_id ) {

            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            if ( empty( $user_wholesale_role ) ) {
                return $min_max_group_rule;
            }

            $product      = wc_get_product( $product_id );
            $product_type = WWP_Helper_Functions::wwp_get_product_type( $product );

            if ( ! in_array( $product_type, array( 'simple', 'variation' ), true ) ) {
                return $min_max_group_rule;
            }

            $price_arr       = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $product_id, $user_wholesale_role );
            $wholesale_price = $price_arr['wholesale_price'];

            if ( ! $wholesale_price ) {
                return $min_max_group_rule;
            }

            $order_qty_step = $product->get_meta( $user_wholesale_role[0] . '_wholesale_order_quantity_step', true );

            if ( $order_qty_step ) {
                return '';
            } else {
                return $min_max_group_rule;
            }
        }

        /**
         * Mute the changes min max plugin will make on the cart page when accessing cart page as wholesale customer.
         *
         * @since  1.16.1
         * @access public
         */
        public function mute_min_max_qty_field_mods_on_cart_page() {

            if ( ! is_cart() ) {
                return;
            }

            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            if ( empty( $user_wholesale_role ) ) {
                return;
            }

            $wc_min_max_quantities = WC_Min_Max_Quantities::get_instance();

            remove_filter( 'woocommerce_quantity_input_args', array( $wc_min_max_quantities, 'update_quantity_args' ), 10, 2 );
            remove_filter( 'woocommerce_available_variation', array( $wc_min_max_quantities, 'available_variation' ), 10, 3 );
            remove_action( 'wp_enqueue_scripts', array( $wc_min_max_quantities, 'load_scripts' ) );
        }

        /**
         * Mute the add product to cart validation of min max plugin if current user is wholesale customer and current product being added have wholesale price.
         *
         * @param bool $pass       Validation.
         * @param int  $product_id Product ID.
         *
         * @since  1.16.1
         * @access public
         */
        public function mute_min_max_plugin_add_product_to_cart_validation( $pass, $product_id ) {

            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            if ( empty( $user_wholesale_role ) ) {
                return $pass;
            }

            $product      = wc_get_product( $product_id );
            $product_type = WWP_Helper_Functions::wwp_get_product_type( $product );

            if ( ! in_array( $product_type, array( 'simple', 'variation', 'variable' ), true ) ) {
                return $pass;
            }

            $price_arr       = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $product_id, $user_wholesale_role );
            $wholesale_price = $price_arr['wholesale_price'];

            if ( ! $wholesale_price ) {
                return $pass;
            }

            $wc_min_max_quantities = WC_Min_Max_Quantities::get_instance();

            remove_filter( 'woocommerce_add_to_cart_validation', array( $wc_min_max_quantities, 'add_to_cart' ), 10, 4 );

            return $pass;
        }

        /**
         * Set qty field value to the min set for this variation product. We only use the per variation min, we dont enforce min if it is set per variable product.
         * This function is Min/Max Quantities plugin compatible. We only change the qty field value, not touch the others.
         * Our min set will take precedence over what is set on Min/Max Quantities plugin.
         * The min we set will be set on the qty field value, not the min attribute coz we still allow purchase lower than min set.
         *
         * @param array                $args      Array of variation arguments.
         * @param WC_Product_Variable  $variable  Variable product instance.
         * @param WC_Product_Variation $variation Variation product instance.
         *
         * @since  1.27.10 Fix minimum order quantity not being loaded in input value if is set on parent variable product
         * @since  1.30.1  Fix Allow add to cart below product minimum is not working for variation level min qty.
         *
         * WWPP-585
         * There are bugs when a variable product have more than 30 variations.
         * 1.) The min order quantity and step values are not set on the quantity field.
         * 2.) The min order quantity and step values notice markup is not shown when variations of a variable product have the same price ( same regular and same wholesale price ).
         *
         * The reason for this is if a variable product have more than 30 variations, it will have different behavior on getting variations data versus when it has only 30 or less variations.
         * When 30 or less variations, it will append the variation data on the form markup as a data attribute.
         * When more than 30, it will fetch the variations data via ajax on the backend.
         * @access public
         *
         * @since  1.15.3
         * @since  1.16.0  Add support for wholesale order quantity step.
         * @since  1.16.7
         * @since  2.2.3   Fall back to the variable-level wholesale order quantity step so a step set on the parent variable product (not per variation) is enforced on the qty field.
         * @return array Filtered array of variation arguments.
         */
        public function enforce_min_order_qty_requirement_on_qty_field( $args, $variable, $variation ) {

            // Bundled (WC Product Bundles) and composite (WC Composite Products) child items have their
            // quantity controlled by the parent, so the standalone wholesale minimum order quantity is not
            // enforced for them. Skip both the quantity-field enforcement (input_value / min_value / step)
            // and the "Min: X" / "Increments of X" label this filter injects into the variation price html
            // while a bundle/composite child is rendering — mirroring the suppression in
            // display_minimum_wholesale_order_quantity().
            if ( $this->is_rendering_bundle_or_composite_child() ) {
                return $args;
            }

            $variable_id         = WWP_Helper_Functions::wwp_get_product_id( $variable );
            $variation_id        = WWP_Helper_Functions::wwp_get_product_id( $variation );
            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();
            $price_arr           = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $variation_id, $user_wholesale_role );
            $wholesale_price     = $price_arr['wholesale_price'];
            $custom_price_html   = false;

            if ( ! empty( $user_wholesale_role ) && ! empty( $wholesale_price ) ) {

                $allow_add_to_cart_below_product_minimum = get_option( 'wwpp_settings_allow_add_to_cart_below_product_minimum', false );
                $minimum_order_qty                       = $variation->get_meta( $user_wholesale_role[0] . '_wholesale_minimum_order_quantity', true );
                $variable_min_order_qty                  = $variable->get_meta( $user_wholesale_role[0] . '_variable_level_wholesale_minimum_order_quantity', true );

                // Per parent variable level.
                if ( ! $minimum_order_qty ) {
                    $minimum_order_qty = $variable_min_order_qty;
                }

                if ( $minimum_order_qty ) {

                    $args['input_value'] = $minimum_order_qty;
                    $args['min_value']   = 1;

                    /**
                     * If price html is not set or have a value of empty string.
                     * It means that the variations of a variable product all have same price ( regular and wholesale ).
                     * We need to return a custom price html containing the min order quantity notice markup.
                     */
                    if ( ! isset( $args['price_html'] ) || empty( $args['price_html'] ) ) {
                        /* translators: %1$s Minimum order quantity */
                        $args['price_html'] = '<span class="wholesale_price_minimum_order_quantity" style="display: block;">' . sprintf( __( 'Min: %1$s', 'woocommerce-wholesale-prices-premium' ), $minimum_order_qty ) . '</span>';
                        $custom_price_html  = true;

                    }

                    $order_qty_step = $variation->get_meta( $user_wholesale_role[0] . '_wholesale_order_quantity_step', true );

                    // Per parent variable level. Mirrors the minimum order quantity fallback above so a
                    // step set on the variable parent (not per variation) is still enforced on the qty field.
                    if ( ! $order_qty_step ) {
                        $order_qty_step = $variable->get_meta( $user_wholesale_role[0] . '_variable_level_wholesale_order_quantity_step', true );
                    }

                    if ( $order_qty_step ) {

                        $args['wwpp_step_specified'] = true;
                        $args['step']                = $order_qty_step;

                        // If Allow Add To Cart Below Product Minimum setting is enabled, don't restrict minimum qty.
                        if ( 'yes' !== $allow_add_to_cart_below_product_minimum ) {
                            $args['min_value'] = $minimum_order_qty;
                        } else {
                            // Set the min value to the smallest divident of order qty step and min order qty value but avoid zero value.
                            $args['min_value'] = $order_qty_step === $minimum_order_qty ? $minimum_order_qty : min( $order_qty_step, $minimum_order_qty ) % max( $order_qty_step, $minimum_order_qty );
                        }

                        /**
                         * Meaning we have generated our own price_html markup, if so, then append the step value notice markup.
                         * We need to make sure we only append if we have a custom price_html, else , we will end up on appending on the original price_html markup generated by woocommerce.
                         */
                        if ( $custom_price_html ) {
                            /* translators: %1$s Order quantity step */
                            $args['price_html'] .= '<span class="wholesale_price_order_quantity_step" style="display: block;">' . sprintf( __( 'Increments of %1$s', 'woocommerce-wholesale-prices-premium' ), $order_qty_step ) . '</span>';
                        }
                    }
                }
            }

            return $args;
        }

        /**
         * Extract bundle and composite products from cart items. We will use these later.
         *
         * @param WC_Cart $cart_object WC_Cart object.
         *
         * @since  1.15.0
         * @access public
         */
        public function extract_bundle_and_composite_products( $cart_object ) {

            $this->_bundle_product_items    = array();
            $this->_composite_product_items = array();

            $bundle_items_extracted         = array();
            $composite_items_extracted      = array();
            $cart_items_for_composite_items = array();

            foreach ( $cart_object->get_cart() as $cart_item_key => $cart_item ) {

                if ( WWP_Helper_Functions::wwp_get_product_type( $cart_item['data'] ) === 'bundle' ) {
                    $product_id     = WWP_Helper_Functions::wwp_get_product_id( $cart_item['data'] );
                    $bundle_product = wc_get_product( $product_id );
                    $bundle_items   = $bundle_product->get_bundled_items();

                    if ( ! empty( $bundle_items ) ) {
                        array_push( $bundle_items_extracted, ...$bundle_items );
                    }
                } elseif ( WWP_Helper_Functions::wwp_get_product_type( $cart_item['data'] ) === 'composite' ) {

                    $product_id        = WWP_Helper_Functions::wwp_get_product_id( $cart_item['data'] );
                    $composite_product = wc_get_product( $product_id );

                    if ( method_exists( $composite_product, 'get_composite_data' ) && count( $composite_product->get_composite_data() ) > 0 ) {
                        foreach ( $composite_product->get_composite_data() as $component_data ) {
                            $composite_items_extracted[]      = $component_data;
                            $cart_items_for_composite_items[] = $cart_item['data'];
                        }
                    }
                }
            }

            foreach ( $bundle_items_extracted as $bundle_item ) {
                $this->_bundle_product_items[ $bundle_item->item_id ] = array( 'is_priced_individually' => $bundle_item->is_priced_individually() );
            }

            $min_count = min( count( $composite_items_extracted ), count( $cart_items_for_composite_items ) ); // we need to loop over these two to get the composite product proper calculation. made this into linear time O(n).
            for ( $i = 0; $i < $min_count; $i++ ) {
                $composite_item = $composite_items_extracted[ $i ];
                $cart_item_data = $cart_items_for_composite_items[ $i ];

                $composite_item_obj = new WC_CP_Component( $composite_item['component_id'], $cart_item_data );

                $this->_composite_product_items[ $composite_item['component_id'] ] = array( 'is_priced_individually' => $composite_item_obj->is_priced_individually() );
            }
        }

        /**
         * Check if cart item is to be included on cart totals computation. This is our own cart price total and items total computation.
         * We cannot use the get_total function of WC_Cart here coz we are hooking into 'woocommerce_before_calculate_totals' which is too early to use get_total.
         * We need to hook into 'woocommerce_before_calculate_totals' tho, this is important.
         * Basically what we are doing is skipping products on cart that are part or a component of a complex product and they are not priced individually.
         *
         * @param boolean $is_included         Boolean flag that determines either include or exclude current cart item on our custom cart totals computation.
         * @param array   $cart_item           Cart item.
         * @param array   $user_wholesale_role Current user wholesale roles.
         *
         * @since  1.15.0
         * @access public
         */
        public function filter_if_cart_item_is_included_on_cart_totals_computation( $is_included, $cart_item, $user_wholesale_role ) {

            /**
             * Only perform the check if wholesale role is not empty and product price is not empty
             * Products with empty price might belong to a composite or a bundle or any complex product set up as non per-product pricing.
             * In these cases, automatically return false. ( Don't apply wholesale pricing ).
             */
            if ( empty( $user_wholesale_role ) || $cart_item['data']->get_price() === '' ) {
                return false;
            }
            // Not a wholesale user and/or no product price.

            if ( isset( $cart_item['bundled_by'] ) && $cart_item['bundled_by'] && isset( $this->_bundle_product_items[ $cart_item['bundled_item_id'] ] ) && ! empty( $this->_bundle_product_items[ $cart_item['bundled_item_id'] ] ) && ! $this->_bundle_product_items[ $cart_item['bundled_item_id'] ]['is_priced_individually'] ) {
                return false;
            }

            if ( isset( $cart_item['composite_parent'] ) && $cart_item['composite_parent'] && isset( $this->_composite_product_items[ $cart_item['composite_item'] ] ) && ! empty( $this->_composite_product_items[ $cart_item['composite_item'] ] ) && ! $this->_composite_product_items[ $cart_item['composite_item'] ]['is_priced_individually'] ) {
                return false;
            }

            return $is_included;
        }

        /**
         * Filter if apply wholesale price per product level. Validate if per product level requirements are meet or not.
         *
         * Important Note: We are retrieving the raw wholesale price, not wholesale price with applied tax. Just the raw
         * wholesale price of the product.
         *
         * @param boolean $apply_wholesale_price Boolean flag that determines either to apply or not wholesale pricing to the current cart item.
         * @param array   $cart_item             Cart item.
         * @param WC_Cart $cart_object           WC_Cart instance.
         * @param array   $user_wholesale_role   Current user wholesale roles.
         * @param float   $wholesale_price       Wholesale price.
         *
         * @since  1.16.0 Add support for wholesale order quantity step.
         * @since  2.1.1  Skip the MOQ pricing gate for bundled/composite child cart items. (WWPP-1828)
         * @access public
         *
         * @since  1.15.0
         * @return array|boolean Array of error notices on if current cart item fails product requirement, boolean true if passed and should apply wholesale pricing.
         */
        public function filter_if_apply_wholesale_price_per_product_level( $apply_wholesale_price, $cart_item, $cart_object, $user_wholesale_role, $wholesale_price ) {

            global $wc_wholesale_prices_premium;

            if ( ! $this->filter_if_cart_item_is_included_on_cart_totals_computation( true, $cart_item, $user_wholesale_role ) ) {
                return false;
            }

            // Bundled (WC Product Bundles) and composite (WC Composite Products) child items have their
            // quantity controlled by the parent, so the child's standalone wholesale minimum order quantity
            // must not gate wholesale pricing here. Without this, a priced-individually child sitting below
            // its MOQ (now that its quantity is no longer forced up to it) would be denied wholesale pricing
            // and charged the regular price. Apply wholesale pricing at the bundle/composite-defined
            // quantity (WWPP-1828).
            if ( ! empty( $cart_item['bundled_by'] ) || ! empty( $cart_item['composite_parent'] ) ) {
                return $apply_wholesale_price;
            }

            $notice                    = array();
            $product_id                = $cart_item['data']->get_id();
            $product_obj               = $cart_item['data'];
            $active_currency           = get_woocommerce_currency();
            $wholesale_price           = WWP_Wholesale_Prices::get_product_wholesale_price_on_cart( $product_id, $user_wholesale_role, $cart_item, $cart_object );
            $formatted_wholesale_price = $wc_wholesale_prices_premium->wwpp_wholesale_prices->get_product_shop_price_with_taxing_applied( $product_obj, $wholesale_price, array( 'currency' => $active_currency ), $user_wholesale_role );

            if ( in_array( $product_obj->get_type(), array( 'simple', 'bundle', 'composite' ), true ) ) {

                $moq = get_post_meta( $product_id, $user_wholesale_role[0] . '_wholesale_minimum_order_quantity', true );
                $moq = ( is_numeric( $moq ) ) ? (int) $moq : 0;

                if ( $cart_item['quantity'] < $moq ) {
                    $notice = array(
                        'type'    => 'notice',
                        'message' => sprintf(/* translators: %1$s Notice wrapper, %2$s Minimum order quantity, %3$s Product title, %4$s Formatted wholesale price, %5$s Notice wrapper */
                            __(
                                '%1$sYou did not meet the minimum order quantity %2$s of the product %3$s to activate wholesale pricing %4$s. Please increase quantities to the cart to activate adjusted pricing.%5$s',
                                'woocommerce-wholesale-prices-premium'
                            ),
                            '<span class="wwpp-notice">',
                            '<b>(' . $moq . ' items)</b>',
                            '<b>' . $cart_item['data']->get_title() . '</b>',
                            '<b>(' . $formatted_wholesale_price . ')</b>',
                            '</span>'
                        ),
                    );
                } elseif ( $cart_item['quantity'] !== $moq && $moq > 0 ) {

                    $oqs = get_post_meta( $product_id, $user_wholesale_role[0] . '_wholesale_order_quantity_step', true );
                    $oqs = ( is_numeric( $oqs ) ) ? (int) $oqs : 0;

                    if ( $oqs ) {

                        $excess_qty = $cart_item['quantity'] - $moq;

                        if ( 0 !== $excess_qty % $oqs ) {
                            $notice = array(
                                'type'    => 'notice',
                                'message' => sprintf(/* translators: %1$s Notice wrapper, %2$s Minimum order quantity, %3$s Order quantity step, %4$s Product title, %5$s Formatted wholesale price, %6$s Notice wrapper */
                                    __(
                                        '%1$sYou did not meet the correct order quantity, %2$s and %3$s of the product %4$s to activate wholesale pricing %5$s. Please correct quantities to the cart to activate adjusted pricing.%6$s',
                                        'woocommerce-wholesale-prices-premium'
                                    ),
                                    '<span class="wwpp-notice">',
                                    '<b>minimum of ' . $moq . '</b>',
                                    '<b>increments of ' . $oqs . '</b>',
                                    '<b>' . $cart_item['data']->get_title() . '</b>',
                                    '<b>(' . $formatted_wholesale_price . ')</b>',
                                    '</span>'
                                ),
                            );
                        }
                    }
                }
            } elseif ( $product_obj->get_type() === 'variation' ) {

                // Process variable level wholesale minimum order quantity.
                $variation_id = $cart_item['data']->get_id();

                // Check variable product requirements.
                $check_result = isset( $this->cart_variation_result[ $variation_id ] ) ? $this->cart_variation_result[ $variation_id ] : false;

                if ( is_array( $check_result ) ) {

                    $variable_attributes = isset( $this->cart_variation_attributes[ $variation_id ] ) ? $this->cart_variation_attributes[ $variation_id ] : '';

                    switch ( $check_result['fail_type'] ) {
                        case 'variable_level_moq':
                            $notice = array(
                                'type'    => 'notice',
                                'message' => sprintf(/* translators: %1$s Notice wrapper, %2$s Minimum order quantity, %3$s Product title, %4$s Formatted wholesale price, %5$s Variable attribute, %6$s Notice wrapper */
                                    __(
                                        '%1$sYou did not meet the minimum order quantity %2$s of the product %3$s to activate wholesale pricing %4$s for the variation %5$s. Please increase quantities to the cart to activate adjusted pricing.%6$s',
                                        'woocommerce-wholesale-prices-premium'
                                    ),
                                    '<span class="wwpp-notice">',
                                    '<b>(' . $check_result['variable_level_moq'] . ' items of any variation)</b>',
                                    '<b>' . $cart_item['data']->get_title() . '</b>',
                                    '<b>(' . $formatted_wholesale_price . ')</b>',
                                    '<b>' . $variable_attributes . '</b>',
                                    '</span>'
                                ),
                            );
                            break;
                        case 'variable_level_oqs':
                            $notice = array(
                                'type'    => 'notice',
                                'message' => sprintf(/* translators: %1$s Notice wrapper, %2$s Minimum order quantity, %3$s Order quantity step, %4$s Product title, %5$s Formatted wholesale price, %6$s Variable attribute, %7$s Notice wrapper */
                                    __(
                                        '%1$sYou did not meet the correct order quantity, %2$s and %3$s of the any combination of variations of the product %4$s to activate wholesale pricing %5$s for the variation %6$s. Please increase quantities to the cart to activate adjusted pricing.%7$s',
                                        'woocommerce-wholesale-prices-premium'
                                    ),
                                    '<span class="wwpp-notice">',
                                    '<b>minimum of ' . $check_result['variable_level_moq'] . '</b>',
                                    '<b>increments of ' . $check_result['variable_level_oqs'] . '</b>',
                                    '<b>' . $cart_item['data']->get_title() . '</b>',
                                    '<b>(' . $formatted_wholesale_price . ')</b>',
                                    '<b>' . $variable_attributes . '</b>',
                                    '</span>'
                                ),
                            );
                            break;
                        case 'variation_level_moq':
                            $notice = array(
                                'type'    => 'notice',
                                'message' => sprintf(/* translators: %1$s Notice wrapper, %2$s Minimum order quantity, %3$s Variable product and variation name, %4$s Formatted wholesale price, %5$s Notice wrapper */
                                    __(
                                        '%1$sYou did not meet the minimum order quantity %2$s of the product %3$s to activate wholesale pricing %4$s. Please increase quantities to the cart to activate adjusted pricing.%5$s',
                                        'woocommerce-wholesale-prices-premium'
                                    ),
                                    '<span class="wwpp-notice">',
                                    '<b>(' . $check_result['variation_level_moq'] . ' items)</b>',
                                    '<b>' . $cart_item['data']->get_title() . ' ' . $variable_attributes . '</b>',
                                    '<b>(' . $formatted_wholesale_price . ')</b>',
                                    '</span>'
                                ),
                            );
                            break;
                        case 'variation_level_oqs':
                            $notice = array(
                                'type'    => 'notice',
                                'message' => sprintf(/* translators: %1$s Notice wrapper, %2$s Minimum order quantity, %3$s Order quantity step, %4$s Variable product and variation name, %5$s Formatted wholesale price, %6$s Notice wrapper */
                                    __(
                                        '%1$sYou did not meet the correct order quantity, %2$s and %3$s of the product %4$s to activate wholesale pricing %5$s. Please correct quantities to the cart to activate adjusted pricing.%6$s',
                                        'woocommerce-wholesale-prices-premium'
                                    ),
                                    '<span class="wwpp-notice">',
                                    '<b>minimum of ' . $check_result['variation_level_moq'] . '</b>',
                                    '<b>increments of ' . $check_result['variation_level_oqs'] . '</b>',
                                    '<b>' . $cart_item['data']->get_title() . ' ' . $variable_attributes . '</b>',
                                    '<b>(' . $formatted_wholesale_price . ')</b>',
                                    '</span>'
                                ),
                            );
                            break;

                    }
                }
            }

            $notice = apply_filters( 'wwpp_filter_wholesale_price_per_product_basis_requirement_failure_notice', $notice, $cart_item, $cart_object, $user_wholesale_role );

            return ! empty( $notice ) ? $notice : $apply_wholesale_price;
        }

        /**
         * Filter if apply wholesale price per cart level. Validate if cart level requirements are meet or not.
         *
         * @param boolean $apply_wholesale_price Boolean flag that determines either to apply or not wholesale pricing per cart level.
         * @param float   $cart_total            Cart total supplied by the caller. NOTE: as of v2.1.0 this
         *                                       argument is discarded inside the callback and recomputed via
         *                                       `WWPP_Helper_Functions::calculate_cart_totals()` so it shares
         *                                       the same basis used by the minimum-order notice.
         * @param array   $cart_items            Aray of cart items.
         * @param WC_Cart $cart_object           WC_Cart instance.
         * @param array   $user_wholesale_role   Current user wholesale roles.
         *
         * @since  1.15.0
         * @since  1.16.0  Support per wholesale user settings.
         * @since  1.16.4  Add compatibility with "Minimum sub-total amount" with Aelia currency switcher plugin.
         * @since  1.27.10 Make sure the general override order requirements setting is set, before applying the override minimum order requirements on per user level
         * @since  1.28    Fix Added Products by ACFW coupon is being added on the cart items quantity.
         *                Add compatibility with ACFW Bogo deals, if the added product is free then do not calculate the price of the product to the min order subtotal requirement
         *                and do not count the added product to the min order quantity requirement.
         * @since  1.30.1  Fix minimum order subtotal price not calculating properly if ACFW BOGO or Add Products discount is applied.
         * @since  1.30.2  Move the proces of the minimum order subtotal price filter to helper.
         * @since  2.1.0   Recompute `$cart_total` via `calculate_cart_totals()` so the wholesale-price
         *                 application check uses the same basis (the raw wholesale-price subtotal) as
         *                 the minimum-order notice.
         * @access public
         *
         * @return array|boolean Array of error notices on if current cart item fails cart requirements, boolean true if passed and should apply wholesale pricing.
         */
        public function filter_if_apply_wholesale_price_cart_level( $apply_wholesale_price, $cart_total, $cart_items, $cart_object, $user_wholesale_role ) {
            // Recompute $cart_total via calculate_cart_totals() so the threshold check uses the same
            // basis as minimum_order_requirement_notice() — the raw wholesale-price subtotal. Without
            // this, the price-application check would compare against WC's cart_contents_total (the
            // actual line totals) instead of the wholesale-price subtotal the notice uses, so the two
            // could diverge near the threshold.
            $cart_total            = WWPP_Helper_Functions::calculate_cart_totals( $cart_object->cart_contents_total, $cart_object, $user_wholesale_role );
            $apply_wholesale_price = WWPP_Helper_Functions::apply_wholesale_price_per_cart_level_min_condition( $cart_total, $cart_object, $user_wholesale_role );

            // Avoid returning array if wholesale price is not applied,
            // This is to avoid the notice being printed multiple times on the cart page,
            // Since the filter is using woocommerce_before_calculate_totals hook.
            return ( WWPP_Helper_Functions::has_wc_cart_block() || WWPP_Helper_Functions::has_wc_checkout_block() )
                ? $apply_wholesale_price : ( true === $apply_wholesale_price );
        }

        /**
         * Print minimum order requirement notice if wholesale user did not meet the minimum order requirement.
         *
         * @since 1.30.2
         */
        public function minimum_order_requirement_notice() {
            global $wc_wholesale_prices_premium;
            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            if ( ! empty( $user_wholesale_role ) ) {
                $cart_object           = WC()->cart;
                $cart_total            = is_cart() ? $wc_wholesale_prices_premium->wwpp_wholesale_prices->preload_calculate_cart_total : WWPP_Helper_Functions::calculate_cart_totals( $cart_object->cart_contents_total, $cart_object, $user_wholesale_role );
                $apply_wholesale_price = WWPP_Helper_Functions::apply_wholesale_price_per_cart_level_min_condition( $cart_total, $cart_object, $user_wholesale_role );

                if ( true !== $apply_wholesale_price && is_array( $apply_wholesale_price ) ) {
                    wc_print_notice( $apply_wholesale_price['message'], $apply_wholesale_price['type'] );
                }
            }
        }

        /**
         * Disable the purchasing capabilities of the current wholesale user if not all wholesale requirements are met.
         *
         * @param WC_Cart $cart_object         Cart object.
         * @param array   $user_wholesale_role Array of wholesale role keys for the current customer.
         *
         * @since  1.14.0
         * @since  1.15.3 Add support for WC 3.2 checkout page changes.
         * @access public
         */
        public function disable_purchasing_capabilities( $cart_object, $user_wholesale_role ) {

            if ( ! empty( $user_wholesale_role ) ) {

                $all_registered_wholesale_roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();
                $only_allow_wholesale_orders    = isset( $all_registered_wholesale_roles[ $user_wholesale_role[0] ]['onlyAllowWholesalePurchases'] ) ? $all_registered_wholesale_roles[ $user_wholesale_role[0] ]['onlyAllowWholesalePurchases'] : 'no';

                if ( 'yes' === $only_allow_wholesale_orders && ( is_cart() || is_checkout() ) ) {

                    add_filter(
                        'woocommerce_order_button_html',
                        function () {

                            return '<h4>' . __( 'Please adjust your cart to meet all of the wholesale requirements in order to proceed.', 'woocommerce-wholesale-prices-premium' ) . '</h4>';
                        }
                    );

                    remove_action( 'woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 20 );
                    remove_action( 'woocommerce_proceed_to_checkout', array( $this, 'output_disable_purchase_notice' ) );
                    add_action( 'woocommerce_proceed_to_checkout', array( $this, 'output_disable_purchase_notice' ) );

                }
            }
        }

        /**
         * Output disable purchase notice.
         *
         * @since  1.14.9
         * @access public
         */
        public function output_disable_purchase_notice() {

            echo wp_kses_post( __( '<h4>Please adjust your cart to meet all of the wholesale requirements in order to proceed.</h4>', 'woocommerce-wholesale-prices-premium' ) ); //phpcs:ignore
        }

        /**
         * Apply "Wholesale Minimum Order Quantity" when adding product to cart via shop page
         *
         * @param array      $args    Button args.
         * @param WC_Product $product The product.
         *
         * @since  1.27.4
         * @since  1.27.10 Add support for Allow Add To Cart Below Product Minimum setting when enabled if the quantity is below moq then don't use moq
         * @access public
         *
         * @return array
         */
        public function apply_minimum_order_quantity_on_shop_page_add_to_cart( $args, $product ) {

            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            if ( $user_wholesale_role ) {

                $minimum_order_qty = $product->get_meta( $user_wholesale_role[0] . '_wholesale_minimum_order_quantity', true );

                // Check if products if allowed for backorders in the product setting and the wwpp allow backorder setting.
                $backorders_allowed = get_option( 'wwpp_settings_always_allow_backorders_to_wholesale_users', false ) === 'yes' || $product->get_backorders() !== 'no' ? true : false;

                // Get the Allow add to cart below product minimum setting.
                $allow_add_to_cart_below_product_minimum = get_option( 'wwpp_settings_allow_add_to_cart_below_product_minimum', false );

                /**
                 * Use products min order quantity if the met below conditions:
                 *
                 * When allow add to cart below product minimum is disabled
                 * or
                 * When allow add to cart below product minimum is enabled and product is allowed for backorders
                 * or
                 * When allow add to cart below product minimum is enabled and product stock status is in stock and stock is not managed
                 * or
                 * When allow add to cart below product minimum is enabled and product stock is managed and stock quantity greater than the product moq
                 */
                if ( 'yes' !== $allow_add_to_cart_below_product_minimum ||
                    ( 'yes' === $allow_add_to_cart_below_product_minimum &&
                        ( ( $product->is_in_stock() && ! $product->managing_stock() ) ||
                            ( $product->managing_stock() && $minimum_order_qty <= $product->get_stock_quantity() ) ||
                            true === $backorders_allowed )
                    )
                ) {
                    $quantity         = isset( $args['quantity'] ) ? $args['quantity'] : 1;
                    $args['quantity'] = $minimum_order_qty ? $minimum_order_qty : $quantity;
                }
            }

            return $args;
        }

        /**
         * Validate "Wholesale Minimum Order Quantity" when adding product to cart via shop page
         *
         * @param boolean $is_valid     True if the item passed validation.
         * @param int     $product_id   The product id.
         * @param int     $quantity     The quantity.
         * @param integer $variation_id Variation ID being added to the cart.
         *
         * @since  1.27.10 Add support for Allow Add To Cart Below Product Minimum setting
         *                Fix wholesale user able to add variable product to the cart bellow products parent product min order quantity
         * @since  1.30.4  Update function name.
         *                Add $variation_id parameter.
         * @access public
         *
         * @since  1.27.4
         * @return boolean
         */
        public function validate_minimum_order_quantity_on_shop_page_add_to_cart( $is_valid, $product_id, $quantity, $variation_id = 0 ) {

            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            if ( $user_wholesale_role && get_option( 'wwpp_settings_allow_add_to_cart_below_product_minimum', false ) !== 'yes' ) {
                // For variable product, check if the the min order qty set on the parent level or variations level.
                if ( ! empty( $variation_id ) ) {
                    $parent_minimum_order_qty    = get_post_meta( $product_id, $user_wholesale_role[0] . '_variable_level_wholesale_minimum_order_quantity', true );
                    $variation_minimum_order_qty = get_post_meta( $variation_id, $user_wholesale_role[0] . '_wholesale_minimum_order_quantity', true );

                    if ( ! empty( $variation_minimum_order_qty ) ) {
                        $is_valid = ( $quantity >= $variation_minimum_order_qty ) ? true : false;
                    }
                } else {
                    $minimum_order_qty = get_post_meta( $product_id, $user_wholesale_role[0] . '_wholesale_minimum_order_quantity', true );
                    $is_valid          = ( $quantity >= $minimum_order_qty ) ? true : false;
                }

                // Show notice not sufficient message if stock is lower than wholesale MOQ.
                if ( false === $is_valid ) {
                    /* translators: %1$s Product name */
                    $error_message = sprintf( sprintf( __( 'You cannot add "%s" to cart because the quantity is lower than the specified wholesale minimum amount.', 'woocommerce-wholesale-prices-premium' ), get_the_title( $product_id ) ) );
                    wc_add_notice( $error_message, 'error' );
                }
            }

            return $is_valid;
        }

        /**
         * Disable add to cart if stock is lower than wholesale MOQ
         *
         * @param bool       $is_in_stock Is product in stock or not.
         * @param WC_Product $product     Product object.
         *
         * @since  1.27.10
         * @access public
         *
         * @return bool
         */
        public function wwpp_disable_adding_cart_if_stock_below_moq( $is_in_stock, $product ) {

            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            if ( empty( $user_wholesale_role )
                || get_option( 'wwpp_settings_always_allow_backorders_to_wholesale_users', false ) === 'yes'
                || get_option( 'wwpp_settings_allow_add_to_cart_below_product_minimum', false ) === 'yes'
            ) {
                return $is_in_stock;
            }

            if ( 'variation' === $product->get_type() ) {
                $parent_product_id           = $product->get_parent_id();
                $parent_minimum_order_qty    = get_post_meta( $parent_product_id, $user_wholesale_role[0] . '_variable_level_wholesale_minimum_order_quantity', true );
                $variation_minimum_order_qty = $product->get_meta( $user_wholesale_role[0] . '_variable_level_wholesale_minimum_order_quantity', true );

                $minimum_order_qty = $variation_minimum_order_qty ? $variation_minimum_order_qty : $parent_minimum_order_qty;
            } else {
                $minimum_order_qty = $product->get_meta( $user_wholesale_role[0] . '_wholesale_minimum_order_quantity', true );
            }

            if ( $product->managing_stock()
                && $minimum_order_qty > $product->get_stock_quantity()
                && 'no' === $product->get_backorders()
            ) {
                $is_in_stock = false;
            }

            return $is_in_stock;
        }

        /**
         * Set stock status text to not sufficent if stock is lower than wholesale MOQ
         *
         * @param bool       $availability Is product available or not.
         * @param WC_Product $product      Product object.
         *
         * @since  1.27.10
         * @access public
         *
         * @return bool
         */
        public function wwpp_set_stock_text_insufficent_if_stock_below_moq( $availability, $product ) {

            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            if ( empty( $user_wholesale_role )
                || get_option( 'wwpp_settings_always_allow_backorders_to_wholesale_users', false ) === 'yes'
                || get_option( 'wwpp_settings_allow_add_to_cart_below_product_minimum', false ) === 'yes'
            ) {
                return $availability;
            }

            if ( 'variation' === $product->get_type() ) {
                $parent_product_id           = $product->get_parent_id();
                $parent_minimum_order_qty    = get_post_meta( $parent_product_id, $user_wholesale_role[0] . '_variable_level_wholesale_minimum_order_quantity', true );
                $variation_minimum_order_qty = $product->get_meta( $user_wholesale_role[0] . '_variable_level_wholesale_minimum_order_quantity', true );

                $minimum_order_qty = $variation_minimum_order_qty ? $variation_minimum_order_qty : $parent_minimum_order_qty;
            } else {
                $minimum_order_qty = $product->get_meta( $user_wholesale_role[0] . '_wholesale_minimum_order_quantity', true );
            }

            if ( $product->managing_stock()
                && $minimum_order_qty > $product->get_stock_quantity()
                && 'no' === $product->get_backorders()
            ) {
                $availability = __( 'Stock not sufficient', 'woocommerce-wholesale-prices-premium' );
            }

            return $availability;
        }

        /**
         * Custom data for store api.
         *
         * @since 2.0.0
         * @return void
         */
        public function wc_cart_block_wwpp_data() {

            woocommerce_store_api_register_endpoint_data(
                array(
                    'endpoint'        => Cart::IDENTIFIER,
                    'namespace'       => 'wwpp_block',
                    'data_callback'   => array( $this, 'check_cart_checkout_block_min_req' ),
                    'schema_callback' => function () {

                        return array(
                            'wwpp_notices' => array(
                                'type'     => 'array',
                                'readonly' => true,
                            ),
                        );
                    },
                    'schema_type'     => ARRAY_A,
                )
            );

            woocommerce_store_api_register_endpoint_data(
                array(
                    'endpoint'        => CartItemSchema::IDENTIFIER,
                    'namespace'       => 'wwpp_block',
                    'data_callback'   => array( $this, 'check_cart_item_block_min_req' ),
                    'schema_callback' => function () {

                        return array(
                            'wwpp_notices' => array(
                                'type'     => 'array',
                                'readonly' => true,
                            ),
                        );
                    },
                    'schema_type'     => ARRAY_A,
                )
            );
        }

        /**
         * Set quantity minimum for cart block for the current product.
         *
         * @param int        $value   Quantity minimum.
         * @param WC_Product $product Product object.
         *
         * @since 2.0.0
         * @return int
         */
        public function cart_block_set_quantity_minimum( $value, $product ) {

            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            if ( ! empty( $user_wholesale_role ) ) {
                if ( 'variable' !== $product->get_type() ) {
                    $price_arr       = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $product->get_id(), $user_wholesale_role );
                    $wholesale_price = $price_arr['wholesale_price'];

                    if ( $wholesale_price && WWPP_Helper_Functions::enforce_product_min_step_on_cart_page() && ! WWPP_Helper_Functions::allow_add_to_cart_below_product_minimum() ) {
                        $minimum_order_qty = (int) $product->get_meta( $user_wholesale_role[0] . '_wholesale_minimum_order_quantity' );

                        if ( 'variation' === $product->get_type() && ! $minimum_order_qty ) {
                            $parent_product    = wc_get_product( $product->get_parent_id() );
                            $minimum_order_qty = $parent_product->get_meta( $user_wholesale_role[0] . '_variable_level_wholesale_minimum_order_quantity' );
                        }

                        if ( ! $minimum_order_qty ) {
                            $minimum_order_qty = (int) WWPP_Helper_Functions::get_global_minimum_order_quantity();
                        }

                        if ( $minimum_order_qty ) {
                            $value = $minimum_order_qty;
                        }
                    }
                }
            }

            return $value;
        }

        /**
         * Set quantity step for cart block for the current product.
         *
         * @param int        $value   Quantity step.
         * @param WC_Product $product Product object.
         *
         * @return int
         */
        public function cart_block_set_quantity_step( $value, $product ) {

            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            if ( ! empty( $user_wholesale_role ) ) {
                if ( 'variable' !== $product->get_type() ) {
                    $price_arr       = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $product->get_id(), $user_wholesale_role );
                    $wholesale_price = $price_arr['wholesale_price'];

                    if ( $wholesale_price ) {
                        $minimum_order_qty = $product->get_meta( $user_wholesale_role[0] . '_wholesale_minimum_order_quantity' );
                        if ( 'variation' === $product->get_type() && ! $minimum_order_qty ) {
                            $parent_product    = wc_get_product( $product->get_parent_id() );
                            $minimum_order_qty = $parent_product->get_meta( $user_wholesale_role[0] . '_variable_level_wholesale_minimum_order_quantity' );
                        }
                        if ( $minimum_order_qty ) {
                            $order_qty_step = $product->get_meta( $user_wholesale_role[0] . '_wholesale_order_quantity_step' );
                            if ( 'variation' === $product->get_type() && ! $order_qty_step ) {
                                $parent_product = empty( $parent_product ) ? wc_get_product( $product->get_parent_id() ) : $parent_product;
                                $order_qty_step = $parent_product->get_meta( $user_wholesale_role[0] . '_variable_level_wholesale_order_quantity_step' );
                            }
                            if ( $order_qty_step && WWPP_Helper_Functions::enforce_product_min_step_on_cart_page() ) {
                                $value = $order_qty_step;
                            }
                        }
                    }
                }
            }

            return $value;
        }

        /**
         * Check cart/checkout block min requirements.
         *
         * @since 2.0.0
         * @return array
         */
        public function check_cart_checkout_block_min_req() {

            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            $apply_wholesale_price = true;
            if ( ! empty( $user_wholesale_role ) ) {
                $cart_object           = WC()->cart;
                $cart_total            = WWPP_Helper_Functions::calculate_cart_totals( $cart_object->cart_contents_total, $cart_object, $user_wholesale_role );
                $apply_wholesale_price = WWPP_Helper_Functions::apply_wholesale_price_per_cart_level_min_condition( $cart_total, $cart_object, $user_wholesale_role );
            }

            return array( 'notices' => true !== $apply_wholesale_price ? $apply_wholesale_price : null );
        }

        /**
         * Check per product item min requirements on cart block.
         *
         * @param array $cart_item Cart item.
         *
         * @since 2.0.0
         * @return array
         */
        public function check_cart_item_block_min_req( $cart_item ) {

            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            $apply_wholesale_price = true;
            if ( ! empty( $user_wholesale_role ) ) {
                $apply_wholesale_price = $this->filter_if_apply_wholesale_price_per_product_level( true, $cart_item, WC()->cart, $user_wholesale_role, 0 );
            }

            return array( 'notices' => true !== $apply_wholesale_price ? $apply_wholesale_price : null );
        }

        /**
         * Show/hide minimum requirement notice on cart/checkout block.
         *
         * @return void
         */
        public function cart_checkout_block_notices() {

            $l10n = array();
            if ( WWPP_Helper_Functions::has_wc_cart_block() ) {

                $l10n = array_merge(
                    $l10n,
                    array(
                        'isCartBlock'                 => true,
                        'onlyAllowWholesalePurchases' => WWPP_Helper_Functions::only_allow_wholesale_purchases(),
                    )
                );

                $script = new \RymeraWebCo\WWPP\Vite_App(
                    'wwpp-cart-checkout-block-notices',
                    'src/vanilla/cart-checkout-min-req.ts',
                    array( 'wp-i18n' ),
                    $l10n
                );
                $script->enqueue();

                $css = <<<'CSS'
.wwpp-block-notice .wc-block-components-notices .wc-block-components-notice-banner.is-info {
  background-color: #3D9CD2;
  color: #FFF;
  font-size: inherit;
}

.wwpp-block-notice .wc-block-components-notices .wc-block-components-notice-banner__dismiss svg {
    fill: #FFF;
    font-size: inherit;
}

.wwpp-block-cart-checkout-btn-hide .wp-block-woocommerce-proceed-to-checkout-block .wc-block-cart__submit-button {
    display: none;
}
CSS;

                wp_add_inline_style( 'wc-blocks-style', $css );
            } elseif ( WWPP_Helper_Functions::has_wc_checkout_block() ) {

                if ( WWPP_Helper_Functions::only_allow_wholesale_purchases() === 'yes' ) {
                    $l10n = array_merge(
                        $l10n,
                        array(
                            'isCheckoutBlock'             => true,
                            'onlyAllowWholesalePurchases' => WWPP_Helper_Functions::only_allow_wholesale_purchases(),
                        )
                    );

                    $script = new \RymeraWebCo\WWPP\Vite_App(
                        'wwpp-cart-checkout-block-notices',
                        'src/vanilla/cart-checkout-min-req.ts',
                        array( 'wp-i18n' ),
                        $l10n
                    );
                    $script->enqueue();

                    $css = <<<'CSS'
                        body.wwpp-block-checkout-place-order-hide .wc-block-checkout__actions_row .wc-block-components-checkout-place-order-button {
                            display: none;
                        }
                    CSS;

                    wp_add_inline_style( 'wc-blocks-style', $css );
                }
            }
        }

        /**
         * Preload necessary cart requirement data for wholesale price validation.
         *
         * This method processes the cart contents to:
         * 1. Calculate total quantities for variable products (sum of all variations)
         * 2. Check if wholesale price requirements are met for each variation
         * 3. Format variation attributes for display in notices/messages
         *
         * The method stores results in class properties for later use:
         * - $this->cart_variation_result: Contains requirement check results for variations
         * - $this->cart_variation_attributes: Contains formatted attribute strings for variations
         *
         * @since 2.0.7
         * @param WC_Cart $cart_object The WooCommerce cart object containing cart contents.
         * @param array   $user_wholesale_role Current Wholesale User.
         * @return void
         */
        public function preload_necessary_cart_requirement_datas( $cart_object, $user_wholesale_role ) {
            $this->cart_variation_qty_total = array(); // Since this is called twice and the total is manipulated for a variable product, for example, the first time this is called the cart total has 10 belonging to a specific variable id, then the second time it becomes double which is 20 and so on. This is why the total was showing an error on the cart page for min order requirements, so every time this is called the property '$this->cart_variation_qty_total' becomes non-persistent.

            /**
             * Calculate the total to be used below.
             */
            foreach ( $cart_object->cart_contents as $cart_content ) {
                if ( 'variation' === $cart_content['data']->get_type() ) {
                    $this->cart_variation_qty_total[ $cart_content['data']->get_parent_id() ] = ( isset( $this->cart_variation_qty_total[ $cart_content['data']->get_parent_id() ] ) ? $this->cart_variation_qty_total[ $cart_content['data']->get_parent_id() ] : 0 ) + $cart_content['quantity'];
                }
            }

            /**
             * Store the result to be used later in this 'class'.
             */
            foreach ( $cart_object->cart_contents as $cart_content ) {
                if ( 'variation' === $cart_content['data']->get_type() ) {
                    $variable_id    = $cart_content['data']->get_parent_id();
                    $variation_id   = $cart_content['data']->get_id();
                    $variable_total = isset( $this->cart_variation_qty_total[ $variable_id ] ) ? $this->cart_variation_qty_total[ $variable_id ] : 0;

                    $check_result = WWPP_Helper_Functions::check_if_variable_product_requirement_is_meet( $variable_id, $variation_id, $cart_content, $variable_total, $user_wholesale_role[0] );

                    $this->cart_variation_result[ $variation_id ] = $check_result;
                }
            }

            /**
             * Form the variation attributes to be used as 'message' for notice.
             */
            foreach ( $cart_object->cart_contents as $cart_content ) {
                if ( 'variation' === $cart_content['data']->get_type() ) {
                    $variation_id = $cart_content['data']->get_id();

                    $variable_attributes = '';

                    if ( isset( $cart_content['variation'] ) && is_array( $cart_content['variation'] ) ) {
                        foreach ( $cart_content['variation'] as $attribute => $attributeVal ) {

                            $attribute = str_replace( 'attribute_', '', $attribute );

                            if ( str_contains( $attribute, 'pa_' ) ) {

                                // Attribute based variable product attribute.
                                $attribute = str_replace( 'pa_', '', $attribute );

                                $attributeVal = str_replace( '-', ' ', $attributeVal );
                                $attributeVal = ucwords( $attributeVal );

                            }

                            $attribute = str_replace( '-', ' ', $attribute );
                            $attribute = ucwords( $attribute );

                            if ( ! empty( $variable_attributes ) ) {
                                $variable_attributes .= ', ';
                            }

                            $variable_attributes .= $attribute . ': ' . $attributeVal;
                        }
                    }

                    if ( ! empty( $variable_attributes ) ) {
                        $this->cart_variation_attributes[ $variation_id ] = '(' . $variable_attributes . ')';
                    }
                }
            }
        }

        /**
         * Remove cart items with no wholesale price configured for the current wholesale role,
         * and block checkout for items still resolving to retail price due to unmet minimum-order
         * requirements.
         *
         * Fires on woocommerce_check_cart_items, which WooCommerce calls on both the cart page and
         * during checkout processing. Items with no wholesale price configured are removed and a
         * user-facing notice is shown. Items whose wholesale pricing was withheld because minimum-order
         * requirements were not met are left in the cart but block checkout with an error notice.
         *
         * @since  2.1.0
         * @access public
         * @return void
         */
        public function validate_cart_wholesale_pricing() {

            $user_wholesale_role                                 = $this->_wwpp_wholesale_roles->getUserWholesaleRole();
            $remove_retail_product_from_cart_for_wholesale_users = wc_string_to_bool( get_option( 'wwpp_settings_remove_retail_products_for_wholesale_customers_in_cart' ) ); // get the option.

            if ( empty( $user_wholesale_role ) || ! $remove_retail_product_from_cart_for_wholesale_users ) {
                return;
            }

            $cart             = WC()->cart;
            $blocked_products = array();

            foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {

                // Re-check whether a wholesale price is actually configured for this product.
                $product_id      = WWP_Helper_Functions::wwp_get_product_id( $cart_item['data'] );
                $wholesale_price = WWP_Wholesale_Prices::get_product_wholesale_price_on_cart( $product_id, $user_wholesale_role, $cart_item, $cart );
                $product_name    = $cart_item['data']->get_name();

                if ( empty( $wholesale_price ) ) {
                    $cart->remove_cart_item( $cart_item_key );

                    wc_add_notice(
                        sprintf(
                            /* translators: %s: product name */
                            __( '"%s" was removed from your cart because no wholesale price is configured for your role. Please contact the store if you believe this is in error.', 'woocommerce-wholesale-prices-premium' ),
                            esc_html( $product_name )
                        ),
                        'notice'
                    );
                } elseif ( ! isset( $cart_item['wwp_data']['wholesale_priced'] ) || 'no' === $cart_item['wwp_data']['wholesale_priced'] ) {
                    // Wholesale price exists but wasn't applied (e.g. min subtotal not met) — collect for aggregated error notice.
                    $blocked_products[] = $product_name;
                }
            }

            // Emit one aggregated error notice for all blocked products to avoid notice stacking.
            if ( ! empty( $blocked_products ) ) {
                wc_add_notice(
                    sprintf(
                        /* translators: %s: comma-separated product names */
                        _n(
                            '"%s" could not be priced at the wholesale rate. Please adjust your cart to meet wholesale pricing requirements before continuing.',
                            'The following products could not be priced at the wholesale rate: %s. Please adjust your cart to meet wholesale pricing requirements before continuing.',
                            count( $blocked_products ),
                            'woocommerce-wholesale-prices-premium'
                        ),
                        esc_html( wc_format_list_of_items( $blocked_products ) )
                    ),
                    'error'
                );
            }
        }

        /**
         * Execute model.
         *
         * @since  1.12.8
         * @access public
         */
        public function run() {

            // Add order minimum order quantity data on product wholesale price fields.
            add_filter( 'wwp_filter_wholesale_price_html', array( $this, 'display_minimum_wholesale_order_quantity' ), 100, 4 );

            // Track when WC Product Bundles / WC Composite Products render their child items so the
            // standalone wholesale minimum order quantity label is suppressed for those children (the
            // parent controls the child's quantity). These actions bracket the child rendering — including
            // each child's price html — on the bundle/composite single product page.
            add_action( 'woocommerce_before_bundled_items', array( $this, 'mark_bundle_or_composite_child_render_start' ) );
            add_action( 'woocommerce_after_bundled_items', array( $this, 'mark_bundle_or_composite_child_render_end' ) );
            add_action( 'woocommerce_composite_before_components', array( $this, 'mark_bundle_or_composite_child_render_start' ) );
            add_action( 'woocommerce_composite_after_components', array( $this, 'mark_bundle_or_composite_child_render_end' ) );

            // The before/after actions above bracket the *inline* child render only. When a variable
            // child has more variations than WooCommerce's ajax_variation_threshold, its variation
            // price html is built during a separate `wc-ajax=get_variation` request where those actions
            // never fire — so the standalone "Min: X" label leaked back onto the variation price. WC PB
            // and WC Composite both bracket a child's price computation (including that ajax request)
            // with their own filter add/remove actions, so hook those too. The render-depth counter
            // keeps the overlapping brackets balanced.
            add_action( 'woocommerce_bundled_product_price_filters_added', array( $this, 'mark_bundle_or_composite_child_render_start' ) );
            add_action( 'woocommerce_bundled_product_price_filters_removed', array( $this, 'mark_bundle_or_composite_child_render_end' ) );
            add_action( 'woocommerce_composite_products_apply_product_filters', array( $this, 'mark_bundle_or_composite_child_render_start' ) );
            add_action( 'woocommerce_composite_products_remove_product_filters', array( $this, 'mark_bundle_or_composite_child_render_end' ) );

            // A variable child's per-variation price html is built and cached (into the variation data /
            // ajax get_variation response) before the brackets above set the depth flag — and for a child
            // that is NOT priced individually the price html is built during the parent's aggregate
            // calculation, outside any child-render window. The depth-flag guard in
            // display_minimum_wholesale_order_quantity() therefore can't suppress the label for those
            // variations. Strip the already-baked label from the variation data while the integrations
            // build the child's variations (flag set via the price-filter brackets).
            add_filter( 'woocommerce_available_variation', array( $this, 'strip_min_order_qty_label_from_bundled_variation' ), 99, 1 );

            add_filter( 'woocommerce_quantity_input_args', array( $this, 'set_order_quantity_attribute_values' ), 11, 2 );
            add_filter( 'wwof_variation_quantity_input_args', array( $this, 'set_order_quantity_attribute_values' ), 10, 2 );

            // Enforce minimum order quantity requirement.
            add_action( 'woocommerce_before_calculate_totals', array( $this, 'set_cart_item_quantity' ), 10, 1 );

            // Min max plugin compat.
            if ( WWP_Helper_Functions::is_plugin_active( 'woocommerce-min-max-quantities/woocommerce-min-max-quantities.php' ) ) {

                add_filter( 'wc_min_max_quantity_minimum_allowed_quantity', array( $this, 'mute_min_max_plugin_min_qty_rule' ), 10, 2 );
                add_filter( 'wc_min_max_quantity_group_of_quantity', array( $this, 'mute_min_max_plugin_group_qty_rule' ), 10, 2 );
                add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'mute_min_max_plugin_add_product_to_cart_validation' ), 9, 2 );
                add_action( 'woocommerce_before_cart', array( $this, 'mute_min_max_qty_field_mods_on_cart_page' ), 99 );

            }

            // Set min qty as qty field initial value ( variation ).
            add_filter( 'woocommerce_available_variation', array( $this, 'enforce_min_order_qty_requirement_on_qty_field' ), 20, 3 );

            // Check if current user meets the wholesale price requirement.
            add_action( 'wwp_before_apply_product_wholesale_price_cart_loop', array( $this, 'extract_bundle_and_composite_products' ), 10, 1 );
            add_filter( 'wwp_include_cart_item_on_cart_totals_computation', array( $this, 'filter_if_cart_item_is_included_on_cart_totals_computation' ), 10, 3 );
            add_filter( 'wwp_apply_wholesale_price_per_product_level', array( $this, 'filter_if_apply_wholesale_price_per_product_level' ), 10, 5 );
            add_filter( 'wwp_apply_wholesale_price_cart_level', array( $this, 'filter_if_apply_wholesale_price_cart_level' ), 10, 5 );
            add_action( 'wwp_wholesale_requirements_not_passed', array( $this, 'disable_purchasing_capabilities' ), 10, 2 );

            add_action( 'wwp_before_apply_product_wholesale_price_cart_loop', array( $this, 'preload_necessary_cart_requirement_datas' ), 5, 2 );

            // Print minimum order requirement notice.
            add_action( 'woocommerce_before_cart', array( $this, 'minimum_order_requirement_notice' ), 10 );
            add_action( 'woocommerce_before_checkout_form', array( $this, 'minimum_order_requirement_notice' ), 10 );

            // Remove cart items with no wholesale price configured for the current role.
            add_action( 'woocommerce_check_cart_items', array( $this, 'validate_cart_wholesale_pricing' ) );

            // Apply minimum order quantity when adding product to cart via shop page.
            add_filter( 'woocommerce_loop_add_to_cart_args', array( $this, 'apply_minimum_order_quantity_on_shop_page_add_to_cart' ), 10, 2 );

            // Validate minimum order quantity when adding product to cart via shop page.
            add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_minimum_order_quantity_on_shop_page_add_to_cart' ), 10, 4 );

            // Disable add to cart and set the stock text to not sufficent when wholesale min product quantity is higher than the current stock on hand.
            add_filter( 'woocommerce_product_is_in_stock', array( $this, 'wwpp_disable_adding_cart_if_stock_below_moq' ), 10, 2 );
            add_filter( 'woocommerce_get_availability_text', array( $this, 'wwpp_set_stock_text_insufficent_if_stock_below_moq' ), 10, 2 );

            add_action( 'woocommerce_blocks_loaded', array( $this, 'wc_cart_block_wwpp_data' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'cart_checkout_block_notices' ) );

            add_filter( 'woocommerce_store_api_product_quantity_multiple_of', array( $this, 'cart_block_set_quantity_step' ), 10, 2 );
            add_filter( 'woocommerce_store_api_product_quantity_minimum', array( $this, 'cart_block_set_quantity_minimum' ), 10, 2 );
        }
    }
}
