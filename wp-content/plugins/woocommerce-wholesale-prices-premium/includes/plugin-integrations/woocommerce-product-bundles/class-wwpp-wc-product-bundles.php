<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWPP_WC_Product_Bundles' ) ) {

    /**
     * Model that houses the logic of integrating with 'WooCommerce Product Bundles' plugin.
     *
     * Bundle products just inherits from simple product so that's why they are very similar.
     * So most of the codebase here are just reusing the codes from simple product.
     *
     * @since 1.13.0
     */
    class WWPP_WC_Product_Bundles {
        /*
        |--------------------------------------------------------------------------
        | Class Properties
        |--------------------------------------------------------------------------
         */

        /**
         * Property that holds the single main instance of WWPP_WC_Composite_Product.
         *
         * @since 1.13.0
         * @access private
         * @var WWPP_WC_Composite_Product
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
         * Model that houses logic of admin custom fields for simple products.
         *
         * @since 1.13.0
         * @access private
         * @var WWPP_Admin_Custom_Fields_Simple_Product
         */
        private $_wwpp_admin_custom_fields_simple_product;

        /**
         * Model that houses the logic of wholesale prices.
         *
         * @since 1.13.0
         * @access private
         * @var WWPP_Wholesale_Prices
         */
        private $_wwpp_wholesale_prices;

        /**
         * Model that houses the logic of applying product category level wholesale pricing.
         *
         * @since 1.14.0
         * @access public
         * @var WWPP_Wholesale_Price_Product_Category
         */
        private $_wwpp_wholesale_price_product_category;

        /**
         * Model that houses the logic of product wholesale price on per wholesale role level.
         *
         * @since 1.16.0
         * @access private
         * @var WWPP_Wholesale_Price_Wholesale_Role
         */
        private $_wwpp_wholesale_price_wholesale_role;

        /*
        |--------------------------------------------------------------------------
        | Class Methods
        |--------------------------------------------------------------------------
         */

        /**
         * WWPP_WC_Composite_Product constructor.
         *
         * @since 1.13.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_WC_Composite_Product model.
         */
        public function __construct( $dependencies ) {

            $this->_wwpp_wholesale_roles                    = $dependencies['WWPP_Wholesale_Roles'];
            $this->_wwpp_admin_custom_fields_simple_product = $dependencies['WWPP_Admin_Custom_Fields_Simple_Product'];
            $this->_wwpp_wholesale_prices                   = $dependencies['WWPP_Wholesale_Prices'];
            $this->_wwpp_wholesale_price_product_category   = $dependencies['WWPP_Wholesale_Price_Product_Category'];
            $this->_wwpp_wholesale_price_wholesale_role     = $dependencies['WWPP_Wholesale_Price_Wholesale_Role'];
        }

        /**
         * Ensure that only one instance of WWPP_WC_Composite_Product is loaded or can be loaded (Singleton Pattern).
         *
         * @since 1.13.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_WC_Composite_Product model.
         * @return WWPP_WC_Composite_Product
         */
        public static function instance( $dependencies ) {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Add bundle product wholesale price field.
         *
         * @since 1.13.0
         * @access public
         */
        public function add_wholesale_price_fields() {

            global $post, $wc_wholesale_prices;

            $product = wc_get_product( $post->ID );

            if ( WWP_Helper_Functions::wwp_get_product_type( $product ) === 'bundle' ) {
                $wc_wholesale_prices->wwp_admin_custom_fields_simple_product->add_wholesale_price_fields();
            }
        }

        /**
         * Save bundle product wholesale price field.
         *
         * @since 1.9.0
         * @since 1.13.0 Refactored codebase and move to its dedicated model.
         * @access public
         *
         * @param int $post_id Product id.
         */
        public function save_wholesale_price_fields( $post_id ) {

            global $wc_wholesale_prices;

            $wc_wholesale_prices->wwp_admin_custom_fields_simple_product->save_wholesale_price_fields( $post_id, 'bundle' );
        }

        /**
         * Save bundle product wholesale minimum order quantity field.
         *
         * @since 1.9.0
         * @since 1.13.0 Refactored codebase and move to its dedicated model.
         * @access public
         *
         * @param int $post_id Product id.
         */
        public function save_minimum_order_quantity_fields( $post_id ) {

            /**
             * Bundle products are very similar to simple products in terms of their fields structure.
             * Therefore we can reuse the code we have on saving wholesale minimum order quantity for simple products to bundle products.
             * BTW the adding of custom wholesale minimum order quantity field to bundle products are already handled by this function 'add_minimum_order_quantity_fields' on 'WWPP_Admin_Custom_Fields_Simple_Product'. Read the desc of the function.
             */
            $this->_wwpp_admin_custom_fields_simple_product->save_minimum_order_quantity_fields( $post_id, 'bundle' );
        }

        /**
         * Save order quantity step custom field value for bundle products on product edit page.
         *
         * @since 1.16.3
         * @access public
         *
         * @param int $post_id Product id.
         */
        public function save_order_quantity_step_fields( $post_id ) {

            /**
             * Bundle products are very similar to simple products in terms of their fields structure.
             * Therefore we can reuse the code we have on saving wholesale order quantity step for simple products to bundle products.
             * BTW the adding of custom wholesale order quantity step field to bundle products are already handled by this function 'add_order_quantity_step_fields' on 'WWPP_Admin_Custom_Fields_Simple_Product'. Read the desc of the function.
             */
            $this->_wwpp_admin_custom_fields_simple_product->save_order_quantity_step_fields( $post_id, 'bundle' );
        }

        /**
         * Filter bundled items of a bundle product and check if the current user is allowed to view the bundled item.
         *
         * @since 1.13.0
         * @access public
         *
         * @param array             $bundled_items     Array bundled items.
         * @param WC_Product_Bundle $wc_product_bundle Bundle product instance.
         * @return array Filtered array bundled items.
         */
        public function filter_bundled_items( $bundled_items, $wc_product_bundle ) {

            $bundle_id = $wc_product_bundle->get_id();

            // If parent bundle has "Disregard Product Category Level Wholesale Discount" enabled then override child products.
            if ( 'yes' === $wc_product_bundle->get_meta( 'wwpp_ignore_cat_level_wholesale_discount', true ) ) {
                add_filter(
                    'wwpp_disregard_cat_level_discount',
                    function ( $value ) { // phpcs:ignore.
                    return 'yes';
                    },
                    10,
                    1
                );
            }

            // If parent bundle has "Disregard Wholesale Role Level Wholesale Discount" enabled then override child products.
            if ( 'yes' === $wc_product_bundle->get_meta( 'wwpp_ignore_role_level_wholesale_discount', true ) ) {
                add_filter(
                    'wwpp_disregard_role_level_discount',
                    function ( $value ) { // phpcs:ignore.
                    return 'yes';
                    },
                    10,
                    1
                );
            }

            if ( current_user_can( 'manage_options' ) ) {
                return $bundled_items;
            }

            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();
            $user_wholesale_role = ! empty( $user_wholesale_role ) ? $user_wholesale_role[0] : '';

            // Parent has wholesale discount then return its children (children will be considered wholesale).
            $wholesale_price = $wc_product_bundle->get_meta( $user_wholesale_role . '_wholesale_price', true );

            if ( ! empty( $wholesale_price ) && $wholesale_price > 0 ) {
                return $bundled_items;
            }

            // If discount is based of category then show children.
            if ( ! empty( $user_wholesale_role ) ) {
                $discount_from_category = $wc_product_bundle->get_meta( $user_wholesale_role . '_have_wholesale_price_set_by_product_cat', true );
                if ( 'yes' === $discount_from_category ) {
                    return $bundled_items;
                }
            }

            foreach ( $bundled_items as $bundle_id => $bundled_item ) {

                $product_id = $bundled_item->item_data['product_id'];

                if ( ! $this->is_bundle_item_available_for_current_user( $product_id, $user_wholesale_role ) ) {
                    unset( $bundled_items[ $bundle_id ] );
                }
            }

            return $bundled_items;
        }

        /**
         * Check if current bundle item is available for the current user.
         *
         * @since 1.13.0
         * @since 1.16.0 Refactor code base to get wholesale discount wholesale role level from 'WWPP_Wholesale_Price_Wholesale_Role' model.
         * @access public
         *
         * @param int    $product_id          Product id.
         * @param string $user_wholesale_role User wholesale role.
         * @return boolean True if current user have access to the current bundle item, false otherwise.
         */
        public function is_bundle_item_available_for_current_user( $product_id, $user_wholesale_role ) {

            $have_wholesale_price = 'yes';
            $product              = wc_get_product( $product_id );

            $curr_product_wholesale_filter = get_post_meta( $product_id, WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER );
            if ( ! is_array( $curr_product_wholesale_filter ) ) {
                $curr_product_wholesale_filter = array();
            }

            // If Logged-in as wholesale user and "Only Show.." is enabled.
            if ( ! empty( $user_wholesale_role ) && get_option( 'wwpp_settings_only_show_wholesale_products_to_wholesale_users', false ) === 'yes' ) {

                if ( ! empty( $product ) && 'simple' === $product->get_type() ) {

                    $price_arr = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $product_id, array( $user_wholesale_role ) );

                    switch ( $price_arr['source'] ) {
                        case 'per_product_level':
                            if ( empty( $price_arr['wholesale_price'] ) ) {
                                return false;
                            }
                            break;
                        case 'wholesale_role_level':
                            if ( 'yes' === $product->get_meta( 'wwpp_ignore_role_level_wholesale_discount', true ) ) {
                                return false;
                            }
                            break;
                        case 'product_category_level':
                            if ( 'yes' === $product->get_meta( 'wwpp_ignore_cat_level_wholesale_discount', true ) ) {
                                return false;
                            }
                            break;
                    }
                } elseif ( ! empty( $product ) && 'variable' === $product->get_type() ) {

                    $variation_with_wholesale_price = array();

                    foreach ( $product->get_children() as $variation ) {

                        $price_arr = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $variation, array( $user_wholesale_role ) );

                        switch ( $price_arr['source'] ) {
                            case 'per_product_level':
                                if ( ! empty( $price_arr['wholesale_price'] ) ) {
                                    $variation_with_wholesale_price[] = $variation;
                                }
                                break;
                            case 'wholesale_role_level':
                                if ( 'yes' !== $product->get_meta( 'wwpp_ignore_role_level_wholesale_discount', true ) ) {
                                    $variation_with_wholesale_price[] = $variation;
                                }
                                break;
                            case 'product_category_level':
                                if ( 'yes' !== $product->get_meta( 'wwpp_ignore_cat_level_wholesale_discount', true ) ) {
                                    $variation_with_wholesale_price[] = $variation;
                                }
                                break;
                        }
                    }

                    if ( empty( $variation_with_wholesale_price ) ) {
                        return false;
                    }
                }

                $user_wholesale_discount = $this->_wwpp_wholesale_price_wholesale_role->get_user_wholesale_role_level_discount( get_current_user_id(), $user_wholesale_role );

                if ( $user_wholesale_role && empty( $user_wholesale_discount['discount'] ) ) {
                    $have_wholesale_price = $product->get_meta( $user_wholesale_role . '_have_wholesale_price', true );
                }
            }

            return ( ( in_array( 'all', $curr_product_wholesale_filter, true ) || in_array( $user_wholesale_role, $curr_product_wholesale_filter, true ) ) && 'yes' === $have_wholesale_price );
        }

        /**
         * The purpose of this is to aid in properly calculating the total price of a bundle product if it has wholesale price.
         * If we dont add this filter callback, bundle product will use the bundle product's original base price instead of the wholesale price in calculation.
         * Prior to v1.13.0, we add a note to the single bundle page that the computation of total is wrong and therefore they should check the cart page instead.
         * But due to changes on Product Bundle codebase, we can now successfully properly compute the total with wholesale pricing.
         *
         * @since 1.13.0
         * @since 1.16.4 Bug fix WWPP-564
         * @access public
         *
         * @param array             $bundle_price_data Array of bundle price data.
         * @param WC_Product_Bundle $wc_product_bundle Product Product bundle object.
         */
        public function filter_bundle_product_base_price( $bundle_price_data, $wc_product_bundle ) {

            if ( ! $wc_product_bundle->contains( 'priced_individually' ) ) {
                return $bundle_price_data;
            }

            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            if ( empty( $user_wholesale_role ) ) {
                return $bundle_price_data;
            }

            $bundle_id = $wc_product_bundle->get_id();

            // If parent bundle has "Disregard Product Category Level Wholesale Discount" enabled then override child products.
            if ( 'yes' === $wc_product_bundle->get_meta( 'wwpp_ignore_cat_level_wholesale_discount', true ) ) {
                add_filter(
                    'wwpp_disregard_cat_level_discount',
                    function ( $value ) { // phpcs:ignore.
                    return 'yes';
                    },
                    10,
                    1
                );
            }

            // If parent bundle has "Disregard Wholesale Role Level Wholesale Discount" enabled then override child products.
            if ( 'yes' === $wc_product_bundle->get_meta( 'wwpp_ignore_role_level_wholesale_discount', true ) ) {
                add_filter(
                    'wwpp_disregard_role_level_discount',
                    function ( $value ) { // phpcs:ignore.
                    return 'yes';
                    },
                    10,
                    1
                );
            }

            $price_arr            = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( WWP_Helper_Functions::wwp_get_product_id( $wc_product_bundle ), $user_wholesale_role );
            $wholesale_sale_price = WWPP_Wholesale_Prices::get_product_wholesale_sale_price( $bundle_id, $user_wholesale_role );
            $wholesale_price      = $price_arr['wholesale_price_raw'];

            if ( $wholesale_price ) {
                $bundle_price_data['base_price'] = $wholesale_price;

                if ( null !== $wholesale_sale_price && $wholesale_sale_price['is_on_sale'] ) {
                    $bundle_price_data['base_price'] = $wholesale_sale_price['wholesale_sale_price'];
                }
            }

            // Set individual bundled item price according to the product wholesale price.
            $bundled_items = $this->filter_bundled_items( $wc_product_bundle->get_bundled_items(), $wc_product_bundle );

            if ( ! empty( $bundled_items ) ) {
                foreach ( $bundled_items as $bundled_item ) {

                    if ( ! $bundled_item->is_purchasable() ) {
                        continue;
                    }

                    if ( false === $bundled_item->is_priced_individually() ) {
                        continue;
                    }

                    $bundle_item_id             = $bundled_item->get_id();
                    $bundled_item_regular_price = $bundle_price_data['prices'][ $bundle_item_id ];

                    if ( isset( $bundled_item->item_data['discounted_wholesale_price'] ) && $bundled_item->item_data['discounted_wholesale_price'] > 0 ) {
                        $bundle_price_data['prices'][ $bundle_item_id ] = $bundled_item->item_data['discounted_wholesale_price'];
                    } else {
                        $price_arr = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $bundled_item->product->get_id(), $user_wholesale_role );

                        if ( ! empty( $price_arr ) && $price_arr['wholesale_price_raw'] > 0 ) {
                            $sale_price_arr  = WWPP_Wholesale_Prices::get_product_wholesale_sale_price( WWP_Helper_Functions::wwp_get_product_id( $bundled_item->product ), $user_wholesale_role );
                            $wholesale_price = ! empty( $sale_price_arr ) && true === $sale_price_arr['is_on_sale'] ? $sale_price_arr['wholesale_sale_price'] : $price_arr['wholesale_price_raw'];
                        }

                        $bundle_price_data['prices'][ $bundle_item_id ] = isset( $wholesale_price ) && $wholesale_price > 0 ? $wholesale_price : $bundled_item_regular_price;
                    }

                    $bundle_price_data['prices'][ $bundle_item_id ] = WWPP_Helper_Functions::woocs_exchange( $bundle_price_data['prices'][ $bundle_item_id ] );

                }
            }

            return $bundle_price_data;
        }

        /**
         * Filter the bundle item price. The purpose of this filter is only to add the $discounted_wholesale_price property to the product data.
         * We still need to return the filter to the original $bundled_item_price.
         * Because if we returns the wholesale price, the Product Bundle Plugin will do the total bundle calculation using wholesale price.
         * Which means the retail price will also calculated by the whoelsale price. In 1.27.2, we have added calculation of the wholesale total bundled product.
         *
         * @since 1.13.0
         * @since 1.16.4    Bug fix WWPP-564
         * @since 1.25      Compatibility with WooCommerce Currency Switcher
         * @since 1.26.1    Refactor codes. Fix Bundle Discount. Fix compat with WOOCS.
         * @since 1.27.2    Removed return $wholesale_price if has wholesale price.
         * @since 2.0.4     - Change the saved wholesale price data to wholesale_price_data.
         *                  - Change the saved discounted_wholesale_price to discounted_wholesale_price.
         *                  - Change the saved bundle_item_discount to bundle_item_discount.
         *
         * @access public
         *
         * @param int             $bundled_item_price         Bundle Item Price.
         * @param WC_Product      $product                    Product Object.
         * @param int             $discount                   Bundle Item Disocunt.
         * @param WC_Bundled_Item $bundled_item               Bundle Item Object.
         * @access public
         * @return int
         */
        public function filter_bundle_item_price( $bundled_item_price, $product, $discount, $bundled_item ) {

            global $WOOCS;

            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            // Stop redundant call.
            if ( property_exists( $product, 'wholesale_price_data' ) ) {
                return $bundled_item_price;
            }

            if ( empty( $user_wholesale_role ) ) {
                return $bundled_item_price;
            }

            // Compatibility with WOOCS
            // Avoid double currency conversion.
            if ( $WOOCS ) {
                $_REQUEST['woocs_block_price_hook'] = true;
            }

            // The reason why we need the code below is to avoid infinite filter call loop
            // Bundles plugin adds filters on get_price() and get_regular_price() which is executed inside the callbacks of the 'wwp_filter_wholesale_price_shop' hook
            // Therefore before executing the callbacks of that hook we must remove the filters bundles plugin attached
            // This filters will be auto attached by bundle plugin as necessary
            // Happens for regular product, the helper function WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3 will perform infinite loop.
            WC_PB_Product_Prices::remove_price_filters();
            $wholesale_price_data = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( WWP_Helper_Functions::wwp_get_product_id( $product ), $user_wholesale_role );
            WC_PB_Product_Prices::add_price_filters( $bundled_item );

            if ( $WOOCS ) {
                unset( $_REQUEST['woocs_block_price_hook'] );
            }

            if ( isset( $wholesale_price_data['wholesale_price'] ) && $wholesale_price_data['wholesale_price'] > 0 ) {
                // IF the product has wholesale sae price, then store the wholesale sale price data.
                $wholesale_sale_price_arr = WWPP_Wholesale_Prices::get_product_wholesale_sale_price( WWP_Helper_Functions::wwp_get_product_id( $product ), $user_wholesale_role );
                if ( ! empty( $wholesale_sale_price_arr ) && true === $wholesale_sale_price_arr['is_on_sale'] ) {
                    $wholesale_price_data = array_merge( $wholesale_price_data, $wholesale_sale_price_arr );
                }

                // Store the wholesale_price_data to the bundle product data, this property is used to get wholesale data of the bundled item.
                if ( property_exists( $product, 'wholesale_price_data' ) ) {
                    $product->wholesale_price_data = $wholesale_price_data;
                } else {
                    // 2.0.4 - Change the saved wholesale price data to wholesale_price_data.
                    $product->update_meta_data( 'wholesale_price_data', $wholesale_price_data );
                }

                // 1.27.2 - removed return $wholesale_price
                // Because if we returns the wholesale price, the Product Bundle Plugin will do the total bundle calculation using wholesale price.
                // $wholesale_price               = $wholesale_price_data['wholesale_price_raw']; // Make sure to use 'wholesale_price_raw' as bundle plugin will apply taxing on the price we return from this function.

                $wholesale_price = isset( $wholesale_price_data['wholesale_sale_price'] ) && is_numeric( $wholesale_price_data['wholesale_sale_price'] ) ? $wholesale_price_data['wholesale_sale_price'] : $wholesale_price_data['wholesale_price_raw']; // Make sure to use 'wholesale_price_raw' as bundle plugin will apply taxing on the price we return from this function.

                if ( $discount > 0 ) {

                    // Calculate discounted wholesale price.
                    // Then add the discounted_wholesale_price property to the product data.
                    // The discounted_wholesale_price property will be used to calculate the total wholesale bundle product price.
                    $discounted_wholesale_price = $wholesale_price - ( $wholesale_price * ( $discount / 100 ) );
                    if ( property_exists( $product, 'discounted_wholesale_price' ) ) {
                        $product->discounted_wholesale_price = $discounted_wholesale_price;
                    } else {
                        // 2.0.4 - Change the saved discounted_wholesale_price to discounted_wholesale_price.
                        $product->update_meta_data( 'discounted_wholesale_price', $discounted_wholesale_price );
                    }

                    if ( property_exists( $product, 'bundle_item_discount' ) ) {
                        $product->bundle_item_discount = $discount;
                    } else {
                        // 2.0.4 - Change the saved bundle_item_discount to bundle_item_discount.
                        $product->update_meta_data( 'bundle_item_discount', $discount );
                    }

                    $bundled_item->item_data['discounted_wholesale_price'] = $discounted_wholesale_price;
                    // 1.27.2 - removed return $wholesale_price
                    // $wholesale_price                     = $discounted_wholesale_price;
                }

                // Save the wholesale price data to the product data.
                if ( property_exists( $product, 'wholesale_price_data' ) || property_exists( $product, 'discounted_wholesale_price' ) || property_exists( $product, 'bundle_item_discount' ) ) {
                    $product->save();
                }
            }
            return $bundled_item_price;
        }

        /**
         * With the advent of WC 2.7, product attributes are not directly accessible anymore.
         * We need to refactor how we retrive the id of the product.
         * Note this filter callback is only for WC less than 2.7
         *
         * @since 1.3.1
         * @access public
         *
         * @param int        $product_id Product id.
         * @param WC_Product $product    Product object.
         * @return int Product id.
         */
        public function get_product_id( $product_id, $product ) {

            if ( version_compare( WC()->version, '3.0.0', '<' ) ) {
                return $product->id;
            }

            return $product_id;
        }

        /**
         * Add support for quick edit.
         *
         * @since 1.14.4
         * @access public
         *
         * @param Array  $allowed_product_types list of allowed product types.
         * @param string $field                 wholesale custom field.
         */
        public function support_for_quick_edit_fields( $allowed_product_types, $field ) {

            $supported_fields = array(
                'wholesale_price_fields',
                'wholesale_minimum_order_quantity',
            );

            if ( in_array( $field, $supported_fields, true ) ) {
                $allowed_product_types[] = 'bundle';
            }

            return $allowed_product_types;
        }

        /**
         * We need to do this because variable product bundle items are treated differently compared to simple products.
         * One way is that, bundle plugin uses 'get_available_variations' function of a variable product to get the data of its variations.
         * Now it uses that data instead on calculating the total of the bundle product ( when variable product is priced individually ).
         * The issue with this is we are not attaching any callback to the 'get_available_variations' function of a variable product coz there are no any filters inside that function.
         * So the price data that function returns are the original price data ( data we have not filtered ).
         * That is why on the front end you will the correct pricing but wrong total calculation.
         * So the solution is whenever bundle plugin loads the variations data via json on the front end.
         * We modify the data via js script. We need to do this coz there are no filters available for us to attach to be able to do the fix on the backend (PHP).
         *
         * @since 1.14.5
         * @since 1.16.3
         * With product bundle 5.7.7, they edited there markup again on the front end, which results to this bug WWPP-560, this is fixed in 1.16.3
         * Also integrate the step feature for variable products that are made as bundled item of a bundle product WWPP-532
         * @since 1.16.4 Bug fix WWPP-564
         *
         * @access public
         *
         * @param int             $bundled_product_id Product id of the bundle item.
         * @param WC_Bundled_Item $bundled_item       Bundle item instance.
         */
        public function filter_variable_bundle_variations_data( $bundled_product_id, $bundled_item ) {

            // Only do this if variable product bundle item is priced individually.
            if ( 'yes' !== $bundled_item->item_data['priced_individually'] ) {
                return;
            }

            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            if ( ! empty( $user_wholesale_role ) ) {

                $variations_wholesale_prices = null;
                $variations                  = WWP_Helper_Functions::wwp_get_variable_product_variations( $bundled_item->product );

                foreach ( $variations as $variation ) {

                    WC_PB_Product_Prices::remove_price_filters();
                    // Make sure to use wholesale price with no taxing applied. Bundle plugin will apply taxing for prices here as well.
                    $price_arr       = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $variation['variation_id'], $user_wholesale_role );
                    $wholesale_price = $price_arr['wholesale_price_raw'];
                    if ( ! empty( $price_arr ) && $wholesale_price > 0 ) {
                        $sale_price_arr  = WWPP_Wholesale_Prices::get_product_wholesale_sale_price( $variation['variation_id'], $user_wholesale_role );
                        $wholesale_price = ! empty( $sale_price_arr ) && true === $sale_price_arr['is_on_sale'] ? $sale_price_arr['wholesale_sale_price'] : $wholesale_price;
                    }
                    WC_PB_Product_Prices::add_price_filters( $bundled_item );

                    // Priced Invidually with Discount Percentage.
                    if ( $bundled_item->get_discount() && $wholesale_price > 0 ) {

                        $wholesale_price = $wholesale_price - ( $wholesale_price * ( $bundled_item->get_discount() / 100 ) );

                    }

                    $variations_wholesale_prices[ $variation['variation_id'] ] = (float) $wholesale_price > 0 ? $wholesale_price : 0;

                }

                if ( $variations_wholesale_prices ) {
                // phpcs:disable.    
                ?>

                <script type="text/javascript">
                /**
                * Code should be inside document ready so all required markup have been successfully rendered.
                */
                jQuery(document).ready(function($) {

                    var variations_wholesale_prices = {
                            <?php
                            foreach ( $variations_wholesale_prices as $var_id => $wholesale_price ) {
                                echo "$var_id : $wholesale_price,";
                            }
                            ?>
                        },
                        product_variations_element = jQuery(
                            '.bundled_item_cart_content[data-product_id="<?php echo $bundled_product_id; ?>"][data-bundle_id="<?php echo $bundled_item->bundle_id; ?>"][data-bundled_item_id="<?php echo $bundled_item->get_id(); ?>"]'
                        ),
                        product_variations_qty_field = product_variations_element.find(
                            ".bundled_qty[name='bundle_quantity_<?php echo $bundled_item->get_id(); ?>']"),
                        product_variations_data = product_variations_element.data("product_variations");

                    for (var i = 0; i < product_variations_data.length; i++) {

                        var price = variations_wholesale_prices[product_variations_data[i].id ? product_variations_data[i].id :
                            product_variations_data[i].variation_id];
                        product_variations_data[i].display_price = price > 0 ? price : product_variations_data[i].display_price;
                        product_variations_data[i].price = price > 0 ? price : product_variations_data[i].price;

                        /**
                        * If min qty is set on a variation on wwpp side, just set initial value of the qty field to that min qty
                        * Do not set min attribute of qty field to that min qty
                        * The reason is to make the behavior of variations inside a bundled variable product to variations of independent variable products
                        * where min qty is set as initial value but still allows customers to order lower than min qty.
                        */

                        if (product_variations_data[i].min_value && product_variations_data[i].min_value > 1) {

                            var min_value = parseInt(product_variations_data[i].min_value, 10);

                            if (product_variations_data[i].step && product_variations_data[i].step > 1) {

                                /**
                                * If step is supplied and min qty is also supplied on the variation on wwpp
                                * Then this is where we restrict the qty field
                                * So similar to variations inside an independent variable product, variations
                                */

                                var step = parseInt(product_variations_data[i].step, 10);

                                product_variations_data[i].step = step;
                                product_variations_data[i].min_qty = min_value;

                            }

                        }

                    }

                    jQuery(
                            '.bundled_item_cart_content[data-product_id="<?php echo $bundled_product_id; ?>"][data-bundle_id="<?php echo $bundled_item->bundle_id; ?>"][data-bundled_item_id="<?php echo $bundled_item->get_id(); ?>"]'
                        )
                        .data("product_variations", product_variations_data);

                    function wwpp_update_qty_field_attributes() {

                        var $variations_form = $(this),
                            variation_id = $variations_form.find(".single_variation_wrap .variation_id").attr('value');

                        for (var i = 0; i < product_variations_data.length; i++) {

                            if (product_variations_data[i].variation_id == variation_id) {

                                /**
                                * If min qty is set on a variation on wwpp side, just set initial value of the qty field to that min qty
                                * Do not set min attribute of qty field to that min qty
                                * The reason is to make the behavior of variations inside a bundled variable product to variations of independent variable products
                                * where min qty is set as initial value but still allows customers to order lower than min qty.
                                */

                                if (product_variations_data[i].input_value)
                                    product_variations_qty_field.attr("value", parseInt(product_variations_data[i].input_value,
                                        10));
                                else if (product_variations_data[i].min_qty)
                                    product_variations_qty_field.attr("value", parseInt(product_variations_data[i].min_qty,
                                        10));
                                else
                                    product_variations_qty_field.attr("value", 1);

                                if (product_variations_data[i].min_value && product_variations_data[i].min_value > 1) {

                                    var min_value = parseInt(product_variations_data[i].min_value, 10);

                                    product_variations_qty_field.attr("value", min_value);

                                    if (product_variations_data[i].step && product_variations_data[i].step > 1) {

                                        /**
                                        * If step is supplied and min qty is also supplied on the variation on wwpp
                                        * Then this is where we restrict the qty field
                                        * So similar to variations inside an independent variable product, variations
                                        */

                                        var step = parseInt(product_variations_data[i].step, 10);

                                        product_variations_qty_field.attr("min", min_value);
                                        product_variations_qty_field.attr("step", step);

                                    } else {

                                        product_variations_qty_field.attr("min", 1);
                                        product_variations_qty_field.attr("step", 1);

                                    }

                                } else {

                                    product_variations_qty_field.attr("min", 1);
                                    product_variations_qty_field.attr("step", 1);

                                }

                                break;

                            }

                        }

                    }

                    $("body").on("woocommerce_variation_has_changed", ".variations_form", wwpp_update_qty_field_attributes);
                    $("body").on("found_variation", ".variations_form",
                        wwpp_update_qty_field_attributes); // Only triggered on ajax complete

                });
                </script>

                <?php
                // phpcs:enable.
                }
            }
        }

        /**
         * In the event variable products have the same regular price, it wont show a per variation price html.
         * That will be a problem if the wholesale price is different across variations but have the same regular price.
         * Coz there will be no html markup that we can hook to show the wholesale price per variation.
         * That is the purpose of this code.
         *
         * @since 1.14.5
         * @since 1.16.0 Supports new wholesale price model.
         * @access public
         *
         * @param int             $bundled_product_id Product id of the bundle item.
         * @param WC_Bundled_Item $bundled_item       Bundle item instance.
         */
        public function filter_per_variation_price_html( $bundled_product_id, $bundled_item ) {
            // Get bundle product object.
            $bundle_product = wc_get_product( $bundled_product_id );

            // Only do this if variable product bundle item is priced individually.
            if ( 'yes' !== $bundled_item->item_data['priced_individually'] ) {
                return;
            }

            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            if ( ! empty( $user_wholesale_role ) ) {

                $variations_arr                                 = array();
                $product                                        = $bundled_item->product;
                $has_per_order_quantity_wholesale_price_mapping = false;
                $has_per_cat_level_order_quantity_wholesale_discount_mapping = false;
                $variations = WWP_Helper_Functions::wwp_get_variable_product_variations( $product );

                foreach ( $variations as $variation ) {

                    $variationProduct = wc_get_product( $variation['variation_id'] );
                    $currVarPrice     = $variation['display_price'];
                    $minimumOrder     = $variationProduct->get_meta( $user_wholesale_role[0] . '_wholesale_minimum_order_quantity', true ); // Per variation level.
                    WC_PB_Product_Prices::remove_price_filters();
                    $price_arr = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $variation['variation_id'], $user_wholesale_role );
                    WC_PB_Product_Prices::add_price_filters( $bundled_item );
                    $wholesale_price = $price_arr['wholesale_price_raw'];

                    // Priced Invidually with Discount Percentage.
                    if ( $bundled_item->get_discount() && $wholesale_price > 0 ) {
                        $wholesale_price = $wholesale_price - ( $wholesale_price * ( $bundled_item->get_discount() / 100 ) );
                    }

                    $source = $price_arr['source'];

                    // Per parent variable level.
                    if ( ! $minimumOrder ) {
                        $minimumOrder = $bundle_product->get_meta( $user_wholesale_role[0] . '_variable_level_wholesale_minimum_order_quantity', true );
                    }

                    // Always default to 1.
                    if ( ! $minimumOrder ) {
                        $minimumOrder = 1;
                    }

                    // Check if product have per product level order quantity based wholesale price.
                    if ( is_numeric( $wholesale_price ) && ! $has_per_order_quantity_wholesale_price_mapping ) {

                        $enabled = $variationProduct->get_meta( WWPP_POST_META_ENABLE_QUANTITY_DISCOUNT_RULE, true );
                        $mapping = $variationProduct->get_meta( WWPP_POST_META_QUANTITY_DISCOUNT_RULE_MAPPING, true );
                        if ( ! is_array( $mapping ) ) {
                            $mapping = array();
                        }

                        $has_mapping_entry = false;
                        foreach ( $mapping as $map ) {
                            if ( isset( $map['wholesale_role'] ) && $map['wholesale_role'] === $user_wholesale_role[0] ) {
                                $has_mapping_entry = true;
                            }
                        }

                        if ( 'yes' === $enabled && $has_mapping_entry ) {
                            $has_per_order_quantity_wholesale_price_mapping = true;
                        }
                    }

                    /**
                     * WWPP-373
                     * Check if product have product category level wholesale pricing set.
                     * Have category level discount.
                     * We do not need to check for the per qty based discount on cat level as checking the base cat discount is enough.
                     */
                    if ( is_numeric( $wholesale_price ) && ! $has_per_cat_level_order_quantity_wholesale_discount_mapping ) {

                        $base_term_id_and_discount = $this->_wwpp_wholesale_price_product_category->get_base_term_id_and_wholesale_discount( WWP_Helper_Functions::wwp_get_product_id( $product ), $user_wholesale_role );

                        if ( ! is_null( $base_term_id_and_discount['term_id'] ) && ! is_null( $base_term_id_and_discount['discount'] ) ) {
                            $has_per_cat_level_order_quantity_wholesale_discount_mapping = true;
                        }
                    }

                    // Only pass through to wc_price if a numeric value given otherwise it will spit out $0.00.
                    if ( is_numeric( $wholesale_price ) ) {

                        $wholesalePriceTitleText = __( 'Wholesale Price:', 'woocommerce-wholesale-prices-premium' );
                        $wholesalePriceTitleText = apply_filters( 'wwp_filter_wholesale_price_title_text', $wholesalePriceTitleText );

                        $wholesalePriceHTML = apply_filters( 'wwp_product_original_price', '<del class="original-computed-price">' . WWP_Helper_Functions::wwp_formatted_price( $currVarPrice ) . $product->get_price_suffix() . '</del>', $wholesale_price, $currVarPrice, $product, $user_wholesale_role );

                        $wholesalePriceHTML .= '<span style="display: block;" class="wholesale_price_container">
                                                    <span class="wholesale_price_title">' . $wholesalePriceTitleText . '</span>
                                                    <ins>' . WWP_Helper_Functions::wwp_formatted_price( $wholesale_price ) . WWP_Wholesale_Prices::get_wholesale_price_suffix( $product, $user_wholesale_role, $price_arr['wholesale_price_with_no_tax'] ) . '</ins>
                                                </span>';

                        $wholesalePriceHTML = apply_filters( 'wwp_filter_wholesale_price_html', $wholesalePriceHTML, $currVarPrice, $variationProduct, $user_wholesale_role, $wholesalePriceTitleText, $wholesale_price, $source );

                        $wholesalePriceHTML = '<span class="price">' . $wholesalePriceHTML . '</span>';

                        $priceHTML         = $wholesalePriceHTML;
                        $hasWholesalePrice = true;

                    } else {

                        $priceHTML         = '<p class="price">' . WWP_Helper_Functions::wwp_formatted_price( $currVarPrice ) . $product->get_price_suffix() . '</p>';
                        $hasWholesalePrice = false;

                    }

                    $variations_arr[] = array(
                        'variation_id'        => $variation['variation_id'],
                        'minimum_order'       => (int) $minimumOrder,
                        'raw_regular_price'   => (float) $currVarPrice,
                        'raw_wholesale_price' => (float) $wholesale_price,
                        'price_html'          => $priceHTML,
                        'has_wholesale_price' => $hasWholesalePrice,
                    );

                }
                // phpcs:disable.
                ?>

                <script>
                jQuery(document).ready(function($) {

                    if ($(
                            '.bundled_item_cart_content[data-product_id="<?php echo $bundled_product_id; ?>"][data-bundle_id="<?php echo $bundled_item->bundle_id; ?>"][data-bundled_item_id="<?php echo $bundled_item->get_id(); ?>"]'
                        )
                        .find(".wholesale_price_container").length <= 0) {

                        function update_variation_price_html() {

                            var WWPPVariableProductPageVars = {
                                    variations: <?php echo json_encode( $variations_arr ); ?>
                                },
                                $variations_form = $(
                                    '.bundled_item_cart_content[data-product_id="<?php echo $bundled_product_id; ?>"][data-bundle_id="<?php echo $bundled_item->bundle_id; ?>"][data-bundled_item_id="<?php echo $bundled_item->get_id(); ?>"]'
                                ),
                                variation_id = $variations_form.find(".single_variation_wrap .variation_id").attr('value'),
                                $single_variation = $variations_form.find(".single_variation"),
                                $qty_field = $variations_form.find(".variations_button .qty");

                            for (var i = 0; i < WWPPVariableProductPageVars.variations.length; i++)
                                if (WWPPVariableProductPageVars.variations[i]['variation_id'] == variation_id &&
                                    $single_variation.find(".price").length <= 0)
                                    $single_variation.prepend(WWPPVariableProductPageVars.variations[i]['price_html']);

                        }

                        $("body").on("woocommerce_variation_has_changed",
                            '.bundled_item_cart_content[data-product_id="<?php echo $bundled_product_id; ?>"][data-bundle_id="<?php echo $bundled_item->bundle_id; ?>"][data-bundled_item_id="<?php echo $bundled_item->get_id(); ?>"]',
                            update_variation_price_html);
                        $("body").on("found_variation",
                            '.bundled_item_cart_content[data-product_id="<?php echo $bundled_product_id; ?>"][data-bundle_id="<?php echo $bundled_item->bundle_id; ?>"][data-bundled_item_id="<?php echo $bundled_item->get_id(); ?>"]',
                            update_variation_price_html); // Only triggered on ajax complete

                    }

                });
                </script>

                <?php
                // phpcs:enable.
            }
        }

        /**
         * Set visibility meta on product bundle save.
         *
         * @since 1.24.6
         * @access public
         *
         * @param int $post_id Post ( Product ) Id.
         */
        public function set_bundle_product_visibility_meta( $post_id ) {

            if ( 'publish' === get_post_status( $post_id ) ) {

                $bundled_product = wc_get_product( $post_id );

                if ( ! empty( $bundled_product ) && 'bundle' === $bundled_product->get_type() ) {

                    $this->set_bundle_visiblity_meta( $post_id, $bundled_product );

                } else {

                    global $wpdb;

                    $result = $wpdb->get_row( $wpdb->prepare( "SELECT bundle_id FROM {$wpdb->prefix}woocommerce_bundled_items WHERE product_id = %d", $post_id ) );

                    if ( ! empty( $result ) ) {

                        $bundled_product = wc_get_product( $result->bundle_id );
                        if ( ! empty( $bundled_product ) && 'bundle' === $bundled_product->get_type() ) {
                            $this->set_bundle_visiblity_meta( $result->bundle_id, $bundled_product );
                        }
                    }
                }
            }
        }

        /**
         * Set visibility meta on product bundle save.
         *
         * @since 1.24.6
         * @access public
         *
         * @param int $bundle_id Post ( Product ) Id.
         * @param obj $bundled_product Bundle Product Object.
         */
        public function set_bundle_visiblity_meta( $bundle_id, $bundled_product ) {

            global $wc_wholesale_prices;

            // Get product object.
            $product_bundle = wc_get_product( $bundle_id );

            $wholesale_roles       = $wc_wholesale_prices->wwp_wholesale_roles->getAllRegisteredWholesaleRoles();
            $has_category_discount = false;

            // Check if bundle has category discount
            // Delete _have_wholesale_price meta.
            foreach ( $wholesale_roles as $role_key => $data ) {

                $category_discount = $product_bundle->get_meta( $role_key . '_have_wholesale_price_set_by_product_cat', true );

                if ( 'yes' === $category_discount ) {
                    $has_category_discount = true;
                } else {
                    $product_bundle->delete_meta_data( $role_key . '_have_wholesale_price' );
                }
            }

            if ( $has_category_discount ) {
                return;
            }

            $bundled_items                 = $bundled_product->get_bundled_items();
            $have_wholesale_price_products = array();

            foreach ( $bundled_items as $bundle_item_id => $bundled_item ) {

                $product_id = $bundled_item->item_data['product_id'];
                $product    = wc_get_product( $product_id );

                foreach ( $wholesale_roles as $role_key => $data ) {

                    $disregard = false;

                    if ( 'simple' === $product->get_type() ) {

                        $price_arr = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $product_id, array( $role_key ) );

                        switch ( $price_arr['source'] ) {
                            case 'wholesale_role_level':
                                $bundle_ignore_role_level_wholesale_discount  = $product_bundle->get_meta( 'wwpp_ignore_role_level_wholesale_discount', true );
                                $product_ignore_role_level_wholesale_discount = $product->get_meta( 'wwpp_ignore_role_level_wholesale_discount', true );
                                if ( 'yes' === $bundle_ignore_role_level_wholesale_discount || 'yes' === $product_ignore_role_level_wholesale_discount ) {
                                    $disregard = true;
                                }
                                break;
                            case 'product_category_level':
                                $bundle_ignore_cat_level_wholesale_discount  = $product_bundle->get_meta( 'wwpp_ignore_cat_level_wholesale_discount', true );
                                $product_ignore_cat_level_wholesale_discount = $product->get_meta( 'wwpp_ignore_cat_level_wholesale_discount', true );
                                if ( 'yes' === $bundle_ignore_cat_level_wholesale_discount || 'yes' === $product_ignore_cat_level_wholesale_discount ) {
                                    $disregard = true;
                                }
                                break;
                        }

                        if ( true !== $disregard && ! empty( $price_arr['wholesale_price'] ) ) {
                            $have_wholesale_price_products[ $product_id ][] = $role_key;
                        }
                    } else {

                        $have_wholesale_price                         = false;
                        $prod_have_wholesale_price                    = $product->get_meta( $role_key . '_have_wholesale_price', true );
                        $prod_have_wholesale_price_set_by_product_cat = $product->get_meta( $role_key . '_have_wholesale_price_set_by_product_cat', true );
                        if ( 'yes' === $prod_have_wholesale_price && 'yes' === $prod_have_wholesale_price_set_by_product_cat ) {

                            $have_wholesale_price                        = true;
                            $bundle_ignore_cat_level_wholesale_discount  = $product_bundle->get_meta( 'wwpp_ignore_cat_level_wholesale_discount', true );
                            $product_ignore_cat_level_wholesale_discount = $product->get_meta( 'wwpp_ignore_cat_level_wholesale_discount', true );

                            if ( 'yes' === $bundle_ignore_cat_level_wholesale_discount || 'yes' === $product_ignore_cat_level_wholesale_discount ) {
                                $disregard = true;
                            }
                        } elseif ( WWPP_Helper_Functions::_wholesale_user_have_general_role_discount( $role_key ) ) {

                            $have_wholesale_price                         = true;
                            $bundle_ignore_role_level_wholesale_discount  = $product_bundle->get_meta( 'wwpp_ignore_role_level_wholesale_discount', true );
                            $product_ignore_role_level_wholesale_discount = $product->get_meta( 'wwpp_ignore_role_level_wholesale_discount', true );

                            if ( 'yes' === $bundle_ignore_role_level_wholesale_discount || 'yes' === $product_ignore_role_level_wholesale_discount ) {
                                $disregard = true;
                            }
                        }

                        if ( $have_wholesale_price && true !== $disregard ) {
                            $have_wholesale_price_products[ $product_id ][] = $role_key;
                        }
                    }
                }

                // save product.
                $product->save();
            }

            if ( ! empty( $have_wholesale_price_products ) ) {

                $product_bundle->delete_meta_data( '_children_has_no_wholesale_prices' );

                foreach ( $have_wholesale_price_products as $pid => $roles ) {

                    foreach ( $roles as $role ) {
                        $product_bundle->update_meta_data( $role . '_have_wholesale_price', 'yes' );
                    }
                }
            } else {

                $product_bundle->update_meta_data( '_children_has_no_wholesale_prices', 'yes' );

                foreach ( $wholesale_roles as $role_key => $data ) {
                    $product_bundle->update_meta_data( $role_key . '_have_wholesale_price', 'no' );
                }
            }

            // Save product.
            $product_bundle->save();
        }

        /**
         * Excluded Bundle Products.
         * Check if the bundle is priced individually, bundle has wholesale price but the childrens don't have.
         * The bundle should not be visible if Only show.. option is enabled.
         *
         * @since 1.24.6
         * @access public
         */
        public function excluded_bundle_products() {

            // Get bundled products.
            $bundle_args = array(
                'type'   => 'bundle',
                'return' => 'ids',
            );

            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();
            $bundled_products    = wc_get_products( $bundle_args );
            $excluded_bundles    = array();

            if ( ! empty( $bundled_products ) ) {

                foreach ( $bundled_products as $bundle_product_id ) {
                    $bundle_product                = wc_get_product( $bundle_product_id );
                    $children_has_wholesale_prices = $bundle_product->get_meta( '_children_has_no_wholesale_prices', true );

                    if ( 'no' === $children_has_wholesale_prices ) {
                        $excluded_bundles[] = $bundle_product_id;
                    }
                }
            }

            return apply_filters( 'wwpp_excluded_bundle_products', $excluded_bundles );
        }

        /**
         * Re-initialize visibility meta when removing or adding new general wholesale discount.
         *
         * @since 1.24.7
         * @access public
         *
         * @param array $bundled_item Bundle Item.
         */
        public function re_initialize_visibility_meta( $bundled_item ) { // phpcs:ignore.

            global $wc_wholesale_prices_premium;
            $wc_wholesale_prices_premium->wwpp_bootstrap->initialize_product_visibility_filter_meta();
        }

        /**
         * Filter bundle item pricing on initialize hooks. Prevents warning from popping up when wholesale prices are from category or general discount.
         *
         * @since 1.24.7
         * @access public
         *
         * @param array $bundled_item Bundle Item.
         */
        public function filter_bundle_item_pricing( $bundled_item ) { // phpcs:ignore.

            add_filter( 'woocommerce_bundled_item_price', array( $this, 'filter_bundle_item_price' ), 10, 4 );
        }

        /**
         * Override wholesale price display. Update wholesale price based on additional discount set per bundled item.
         * Note: Category and General discount is being discounted more than once. Reason why the displayed price is odd.
         *
         * @since 1.26.2
         * @since 1.27.10 - Fix wholesale price calculation when bundle product is wholesale priced on the parent bundle product and bundled items contains priced individually item.
         *
         * @access public
         *
         * @param string     $wholesale_price_html       Wholesale Price HTML.
         * @param string     $price                      Product Price.
         * @param WC_Product $product                    Product Object.
         * @param array      $user_wholesale_role        Wholesale Role.
         * @param string     $wholesale_price_title_text Wholesale Price Text.
         * @param int        $raw_wholesale_price        Raw Wholesale Price.
         * @param string     $source                     Wholesale Discount Source.
         * @return string
         */
        public function filter_wholesale_price_html( $wholesale_price_html, $price, $product, $user_wholesale_role, $wholesale_price_title_text, $raw_wholesale_price, $source ) { // phpcs:ignore.

            global $WOOCS;

            $wholesale_price = '';

            /**
             * The product wholesale price only show product bundle (parent) wholesale price, not including the children's price.
             * https://github.com/Rymera-Web-Co/woocommerce-wholesale-prices-premium/issues/201
             * The Wholesale text markup for bundle product should include the calculation of the children's bundle product prices.
             * Here we check if the type of the product and if it's contains children's product with priced individually.
             * For this we could use the get_bundle_wholesale_price_excluding_tax or get_bundle_wholesale_price_including_tax function to get total bundle price according to the tax settings.
             */
            if ( WWP_Helper_Functions::wwp_get_product_type( $product ) === 'bundle' && $product->contains( 'priced_individually' ) ) {

                $wholesale_tax_display_shop   = get_option( 'wwpp_settings_incl_excl_tax_on_wholesale_price', false );
                $woocommerce_tax_display_shop = get_option( 'woocommerce_tax_display_shop', false );
                $tax_exempted                 = ! empty( $user_wholesale_role ) ? WWPP_Helper_Functions::is_user_wwpp_tax_exempted( get_current_user_id(), $user_wholesale_role[0] ) : '';

                $bundled_items_price_min_excl_tax = $this->get_bundle_wholesale_price_excluding_tax( $product, $user_wholesale_role, 'min' );
                $bundled_items_price_max_excl_tax = $this->get_bundle_wholesale_price_excluding_tax( $product, $user_wholesale_role, 'max' );

                // Get the total calculated price of the wholesale bundle product price according to the tax settings.
                if ( 'yes' === $tax_exempted ) {

                    // If min and max total of the bundle product price has diffenrence, then show a price range format.
                    if ( $bundled_items_price_min_excl_tax !== $bundled_items_price_max_excl_tax ) {
                        $wholesale_price = sprintf( '%1$s - %2$s', WWP_Helper_Functions::wwp_formatted_price( $bundled_items_price_min_excl_tax ), WWP_Helper_Functions::wwp_formatted_price( $bundled_items_price_max_excl_tax ) );
                    } else {
                        $wholesale_price = WWP_Helper_Functions::wwp_formatted_price( $bundled_items_price_min_excl_tax );
                    }
                } elseif ( 'incl' === $wholesale_tax_display_shop ) {
                    // Get the bundle product price according to WWPP tax display on shop page setting.
                    $bundled_items_price_min_incl_tax = $this->get_bundle_wholesale_price_including_tax( $product, $user_wholesale_role, 'min' );
                    $bundled_items_price_max_incl_tax = $this->get_bundle_wholesale_price_including_tax( $product, $user_wholesale_role, 'max' );

                    // If min and max total of the bundle product price has diffenrence, then show a price range format.
                    if ( $bundled_items_price_min_incl_tax !== $bundled_items_price_max_incl_tax ) {
                        $wholesale_price = sprintf( '%1$s - %2$s', WWP_Helper_Functions::wwp_formatted_price( $bundled_items_price_min_incl_tax ), WWP_Helper_Functions::wwp_formatted_price( $bundled_items_price_max_incl_tax ) );
                    } else {
                        $wholesale_price = WWP_Helper_Functions::wwp_formatted_price( $bundled_items_price_min_incl_tax );
                    }
                } elseif ( 'excl' === $wholesale_tax_display_shop ) {
                    // If min and max total of the bundle product price has diffenrence, then show a price range format.
                    if ( $bundled_items_price_min_excl_tax !== $bundled_items_price_max_excl_tax ) {
                        // translators: %1$s: Min price, %2$s: Max price.
                        $wholesale_price = sprintf( '%1$s - %2$s', WWP_Helper_Functions::wwp_formatted_price( $bundled_items_price_min_excl_tax ), WWP_Helper_Functions::wwp_formatted_price( $bundled_items_price_max_excl_tax ) );
                    } else {
                        $wholesale_price = WWP_Helper_Functions::wwp_formatted_price( $bundled_items_price_min_excl_tax );
                    }
                } elseif ( empty( $wholesale_tax_display_shop ) ) {

                    // Get the bundle product price according to Woo tax display on shop page setting.
                    if ( 'incl' === $woocommerce_tax_display_shop ) {
                        $bundled_items_price_min_incl_tax = $this->get_bundle_wholesale_price_including_tax( $product, $user_wholesale_role, 'min' );
                        $bundled_items_price_max_incl_tax = $this->get_bundle_wholesale_price_including_tax( $product, $user_wholesale_role, 'max' );

                        // If min and max total of the bundle product price has diffenrence, then show a price range format.
                        if ( $bundled_items_price_min_incl_tax !== $bundled_items_price_max_incl_tax ) {
                            // translators: %1$s: Min price, %2$s: Max price.
                            $wholesale_price = sprintf( '%1$s - %2$s', WWP_Helper_Functions::wwp_formatted_price( $bundled_items_price_min_incl_tax ), WWP_Helper_Functions::wwp_formatted_price( $bundled_items_price_max_incl_tax ) );
                        } else {
                            $wholesale_price = WWP_Helper_Functions::wwp_formatted_price( $bundled_items_price_min_incl_tax );
                        }
                    } elseif ( $bundled_items_price_min_excl_tax !== $bundled_items_price_max_excl_tax ) { // If min and max total of the bundle product price has diffenrence, then show a price range format.
                        // translators: %1$s: Min price, %2$s: Max price.
                        $wholesale_price = sprintf( '%1$s - %2$s', WWP_Helper_Functions::wwp_formatted_price( $bundled_items_price_min_excl_tax ), WWP_Helper_Functions::wwp_formatted_price( $bundled_items_price_max_excl_tax ) );
                    } else {
                        $wholesale_price = WWP_Helper_Functions::wwp_formatted_price( $bundled_items_price_min_excl_tax );
                    }
                }
            } elseif ( property_exists( $product, 'wholesale_price_data' ) ) {

                $wholesale_price_data           = $product->wholesale_price_data;
                $has_discounted_wholesale_price = false;

                if ( isset( $wholesale_price_data['wholesale_price'] ) && $wholesale_price_data['wholesale_price'] > 0 && property_exists( $product, 'discounted_wholesale_price' ) && $product->discounted_wholesale_price > 0 ) {

                    if ( $WOOCS ) {

                        if ( 'variation' === WWP_Helper_Functions::wwp_get_product_type( $product ) ) {
                            if ( $WOOCS->default_currency !== $WOOCS->current_currency && 'per_product_level' !== $wholesale_price_data['source'] ) {
                                $currencies    = $WOOCS->get_currencies();
                                $currency_rate = $currencies[ $WOOCS->current_currency ]['rate'];

                                if ( $currency_rate >= 1 ) {
                                    $wholesale_price = $product->discounted_wholesale_price;
                                } else {
                                    // If selected currency is not default currency, WOOCS is coverting the currency twice and applied the bundle discount once, so we need to do back convert of the default currency and use $raw_wholesale_price.
                                    // this behaviour only occurs on the general discount and category discount level, because WOOCS converts the regular price.
                                    // WOOCS has official fix for this issue, for more detail visit the link here: https://currency-switcher.com/product-bundles-by-somewherewarm/.
                                    $wholesale_price = WWPP_Helper_Functions::woocs_back_convert( $raw_wholesale_price );
                                }
                            } else {
                                remove_filter( 'woocommerce_bundled_item_price', array( $this, 'filter_bundle_item_price' ), 10, 4 );
                                $wholesale_data  = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( WWP_Helper_Functions::wwp_get_product_id( $product ), $user_wholesale_role );
                                $wholesale_price = $wholesale_data['wholesale_price_raw'];
                            }
                        } else {
                            $wholesale_price = WWPP_Helper_Functions::woocs_exchange( $product->discounted_wholesale_price );
                        }
                    } elseif ( 'per_product_level' !== $wholesale_price_data['source'] ) {
                        remove_filter( 'woocommerce_bundled_item_price', array( $this, 'filter_bundle_item_price' ), 10, 4 );
                        $wholesale_data      = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( WWP_Helper_Functions::wwp_get_product_id( $product ), $user_wholesale_role );
                        $wholesale_sale_data = WWPP_Wholesale_Prices::get_product_wholesale_sale_price( WWP_Helper_Functions::wwp_get_product_id( $product ), $user_wholesale_role );
                        $wholesale_price     = $wholesale_data['wholesale_price_raw'];
                    } else {
                        $wholesale_price = $product->discounted_wholesale_price;
                    }

                    $regular_price                  = apply_filters( 'wwp_pass_wholesale_price_through_taxing', $product->get_regular_price(), WWP_Helper_Functions::wwp_get_product_id( $product ), $user_wholesale_role );
                    $item_price                     = apply_filters( 'wwp_pass_wholesale_price_through_taxing', WWPP_Helper_Functions::woocs_exchange( $product->bundled_item_price ), WWP_Helper_Functions::wwp_get_product_id( $product ), $user_wholesale_role );
                    $price                          = wc_format_sale_price( wc_price( $regular_price ), wc_price( $item_price ) );
                    $has_discounted_wholesale_price = true;
                } else {

                    remove_filter( 'woocommerce_bundled_item_price', array( $this, 'filter_bundle_item_price' ), 10, 4 );
                    $wholesale_data  = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( WWP_Helper_Functions::wwp_get_product_id( $product ), $user_wholesale_role );
                    $wholesale_price = $wholesale_data['wholesale_price_raw'];

                    $regular_price = apply_filters( 'wwp_pass_wholesale_price_through_taxing', $product->get_regular_price(), WWP_Helper_Functions::wwp_get_product_id( $product ), $user_wholesale_role );
                    $price         = wc_price( $regular_price );

                }

                $wholesale_price = apply_filters( 'wwp_pass_wholesale_price_through_taxing', $wholesale_price, WWP_Helper_Functions::wwp_get_product_id( $product ), $user_wholesale_role );
                if ( $has_discounted_wholesale_price ) {
                    $wholesale_price_before_discount = apply_filters( 'wwp_pass_wholesale_price_through_taxing', $wholesale_price_data['wholesale_price_raw'], WWP_Helper_Functions::wwp_get_product_id( $product ), $user_wholesale_role );
                    $wholesale_price                 = wc_format_sale_price( WWP_Helper_Functions::wwp_formatted_price( $wholesale_price_before_discount ), WWP_Helper_Functions::wwp_formatted_price( $wholesale_price ) );
                } else {
                    $wholesale_price  = WWP_Helper_Functions::wwp_formatted_price( $wholesale_price_data['wholesale_price'] );
                    $wholesale_price .= WWP_Wholesale_Prices::get_wholesale_price_suffix( $product, $user_wholesale_role, $wholesale_price, false );
                    // If the wholesale price is on sale, then show the sale price.
                    if ( isset( $wholesale_price_data['is_on_sale'] ) &&
                        true === $wholesale_price_data['is_on_sale'] &&
                        $wholesale_price_data['wholesale_sale_price'] > 0
                    ) {
                        $wholesale_price = wc_format_sale_price( $wholesale_price, WWP_Helper_Functions::wwp_formatted_price( $wholesale_price_data['wholesale_sale_price'] ) );
                    } else {
                        $wholesale_price = '<ins>' . $wholesale_price . '</ins>';
                    }
                }
            }

            // If the bundle has wholesale price set on it's parent or has the bundled items with wholesale price, then show the whoesale price text.
            if ( 0 !== strcasecmp( $wholesale_price, '' ) ) {
                $wholesale_price_html = '<span style="display: block;" class="wholesale_price_container">
                                                <span class="wholesale_price_title">' . $wholesale_price_title_text . '</span> '
                                                    . $wholesale_price .
                                                '</span>';

                return apply_filters( 'wwpp_bundle_item_wholesale_price', '<del class="original-computed-price">' . $price . $product->get_price_suffix() . '</del>' . $wholesale_price_html );
            }

            return $wholesale_price_html;
        }

        /**
         * Apply Wholesale Price Suffix in bundle subtotal calculation.
         * We determine Wholesale Price Suffix value using 'wwp_wholesale_price_suffix' filter.
         *
         * @since 1.27.2
         * @access public
         *
         * @param array $params bundle front end params.
         * @return array
         */
        public function filter_bundle_front_end_params( $params ) {

            global $wc_wholesale_prices;
            $wholesale_role = $wc_wholesale_prices->wwp_wholesale_roles->getUserWholesaleRole();

            if ( empty( $wholesale_role ) ) {
                return $params;
            }

            $params['price_display_suffix'] = apply_filters( 'wwp_wholesale_price_suffix', get_option( 'woocommerce_price_display_suffix' ) );

            return $params;
        }

        /**
         * Apply Wholesale Price Suffix in bundle product price if product price is set to zero.
         *
         * @since 1.27.2
         * @access public
         *
         * @param string $suffix_html suffix html.
         * @param string $product     WC_Product object.
         * @param string $price       price.
         * @param string $qty         quantity.
         * @return string
         */
        public function filter_woocommerce_get_price_suffix( $suffix_html, $product, $price, $qty ) { // phpcs:ignore.
            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            if ( ! empty( $user_wholesale_role ) && 'bundle' === WWP_Helper_Functions::wwp_get_product_type( $product ) ) {

                if ( $product->contains( 'priced_individually' ) && 0 === $product->get_price() && $product->get_bundle_price() > 0 ) {

                    $price_suffix_option = get_option( 'wwpp_settings_override_price_suffix_regular_price' );
                    if ( empty( $price_suffix_option ) ) {
                        $price_suffix_option = get_option( 'woocommerce_price_display_suffix' );
                    }

                    $wholesale_suffix_for_regular_price = $price_suffix_option;
                    $has_match                          = false;

                    // Check if price suffix contain including tax tag {price_including_tax}.
                    if ( strpos( $wholesale_suffix_for_regular_price, '{price_including_tax}' ) !== false ) {

                        $price_min = $product->get_bundle_price_including_tax( 'min', true );
                        $price_max = $product->get_bundle_price_including_tax( 'max', true );

                        if ( $price_min !== $price_max ) {
                            $wholesale_price_incl_tax           = sprintf( '%1$s - %2$s', WWP_Helper_Functions::wwp_formatted_price( $price_min ), WWP_Helper_Functions::wwp_formatted_price( $price_max ) );
                            $wholesale_suffix_for_regular_price = str_replace( '{price_including_tax}', $wholesale_price_incl_tax, $wholesale_suffix_for_regular_price );
                        } else {
                            $wholesale_price_incl_tax           = WWP_Helper_Functions::wwp_formatted_price( $product->get_bundle_price_including_tax() );
                            $wholesale_suffix_for_regular_price = str_replace( '{price_including_tax}', $wholesale_price_incl_tax, $wholesale_suffix_for_regular_price );
                        }

                        $has_match = true;

                    }

                    // Check if price suffix contain excluding tax tag {price_excluding_tax}.
                    if ( strpos( $wholesale_suffix_for_regular_price, '{price_excluding_tax}' ) !== false ) {

                        $price_min = $product->get_bundle_price_excluding_tax( 'min', true );
                        $price_max = $product->get_bundle_price_excluding_tax( 'max', true );

                        if ( $price_min !== $price_max ) {
                            $wholesale_price_excl_tax           = sprintf( '%1$s - %2$s', WWP_Helper_Functions::wwp_formatted_price( $price_min ), WWP_Helper_Functions::wwp_formatted_price( $price_max ) );
                            $wholesale_suffix_for_regular_price = str_replace( '{price_excluding_tax}', $wholesale_price_excl_tax, $wholesale_suffix_for_regular_price );
                        } else {
                            $wholesale_price_excl_tax           = WWP_Helper_Functions::wwp_formatted_price( $product->get_bundle_price_excluding_tax() );
                            $wholesale_suffix_for_regular_price = str_replace( '{price_excluding_tax}', $wholesale_price_excl_tax, $wholesale_suffix_for_regular_price );
                        }

                        $has_match = true;
                    }

                    return $has_match ? ' <small class="woocommerce-price-suffix wholesale-user-regular-price-suffix">' . $wholesale_suffix_for_regular_price . '</small>' : ' <small class="woocommerce-price-suffix">' . $price_suffix_option . '</small>';
                }
            }
            return $suffix_html;
        }

        /**
         * Apply Wholesale Price on the bundle product price if product price is set to zero or only has regular price with no wholesale price,
         * but the bundled items is priced individualy and has wholesale prices.
         *
         * @since 1.27.2
         * @access public
         *
         * @param string     $price_html price html.
         * @param WC_Product $product    WC_Product object.
         * @return string
         */
        public function filter_woocommerce_get_bundle_price_html( $price_html, $product ) {
            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            if ( empty( $user_wholesale_role ) && WWP_Helper_Functions::wwp_get_product_type( $product ) !== 'bundle' ) {
                return $price_html;
            }

            $price_arr = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $product->get_ID(), $user_wholesale_role );

            // return if bundle product has wholesale price, continue only when bundle product has no wholesale price but the bundled items has wholesale price.
            if ( is_numeric( $price_arr['wholesale_price'] ) ) {
                return $price_html;
            }

            $bundled_items = $this->filter_bundled_items( $product->get_bundled_items(), $product );

            if ( ! empty( $bundled_items ) ) {

                $bundled_items_has_wholesale_data = '';
                foreach ( $bundled_items as $bundled_item ) {
                    if ( WWP_Helper_Functions::wwp_get_product_type( $bundled_item->product ) === 'variable' ) {
                        $variations = WWP_Helper_Functions::wwp_get_variable_product_variations( $bundled_item->product );

                        foreach ( $variations as $variation ) {
                            if ( ! $variation['is_purchasable'] ) {
                                continue;
                            }

                            if ( isset( $variation['wholesale_price_raw'] ) && $variation['wholesale_price_raw'] ) {
                                $bundled_items_has_wholesale_data = 'yes';
                                break;
                            }
                        }
                    } elseif ( property_exists( $bundled_item->product, 'wholesale_price_data' ) ) {
                            $bundled_items_has_wholesale_data = 'yes';
                            break;
                    }
                }

                if ( $product->contains( 'priced_individually' ) && 'yes' === $bundled_items_has_wholesale_data ) {
                    $wholesale_price_html       = '';
                    $wholesale_price_title_text = __( 'Wholesale Price:', 'woocommerce-wholesale-prices-premium' );
                    $wholesale_price_title_text = apply_filters( 'wwp_filter_wholesale_price_title_text', $wholesale_price_title_text );

                    $wholesale_tax_display_shop   = get_option( 'wwpp_settings_incl_excl_tax_on_wholesale_price', false );
                    $woocommerce_tax_display_shop = get_option( 'woocommerce_tax_display_shop', false );
                    $tax_exempted                 = ! empty( $user_wholesale_role ) ? WWPP_Helper_Functions::is_user_wwpp_tax_exempted( get_current_user_id(), $user_wholesale_role[0] ) : '';

                    $price_min_excl_tax = $this->get_bundle_wholesale_price_excluding_tax( $product, $user_wholesale_role, 'min' );
                    $price_max_excl_tax = $this->get_bundle_wholesale_price_excluding_tax( $product, $user_wholesale_role, 'max' );

                    // Wholesale user is tax exempted so no matter what, the user will always see tax exempted prices.
                    if ( 'yes' === $tax_exempted ) {

                        if ( $price_min_excl_tax !== $price_max_excl_tax ) {
                            // translators: %1$s: Min price, %2$s: Max price.
                            $wholesale_price = sprintf( '%1$s - %2$s', WWP_Helper_Functions::wwp_formatted_price( $price_min_excl_tax ), WWP_Helper_Functions::wwp_formatted_price( $price_max_excl_tax ) );
                        } else {
                            $wholesale_price = WWP_Helper_Functions::wwp_formatted_price( $price_min_excl_tax );
                        }
                    } elseif ( 'incl' === $wholesale_tax_display_shop ) {

                        $price_min_incl_tax = $this->get_bundle_wholesale_price_including_tax( $product, $user_wholesale_role, 'min' );
                        $price_max_incl_tax = $this->get_bundle_wholesale_price_including_tax( $product, $user_wholesale_role, 'max' );

                        if ( $price_min_excl_tax !== $price_max_excl_tax ) {
                            // translators: %1$s: Min price, %2$s: Max price.
                            $wholesale_price = sprintf( '%1$s - %2$s', WWP_Helper_Functions::wwp_formatted_price( $price_min_incl_tax ), WWP_Helper_Functions::wwp_formatted_price( $price_max_incl_tax ) );
                        } else {
                            $wholesale_price = WWP_Helper_Functions::wwp_formatted_price( $price_max_incl_tax );
                        }
                    } elseif ( 'excl' === $wholesale_tax_display_shop ) {
                        if ( $price_min_excl_tax !== $price_max_excl_tax ) {
                            // translators: %1$s: Min price, %2$s: Max price.
                            $wholesale_price = sprintf( '%1$s - %2$s', WWP_Helper_Functions::wwp_formatted_price( $price_min_excl_tax ), WWP_Helper_Functions::wwp_formatted_price( $price_max_excl_tax ) );
                        } else {
                            $wholesale_price = WWP_Helper_Functions::wwp_formatted_price( $price_min_excl_tax );
                        }
                    } elseif ( empty( $wholesale_tax_display_shop ) ) {

                        if ( 'incl' === $woocommerce_tax_display_shop ) {
                            $price_min_incl_tax = $this->get_bundle_wholesale_price_including_tax( $product, $user_wholesale_role, 'min' );
                            $price_max_incl_tax = $this->get_bundle_wholesale_price_including_tax( $product, $user_wholesale_role, 'max' );

                            if ( $price_min_excl_tax !== $price_max_excl_tax ) {
                                // translators: %1$s: Min price, %2$s: Max price.
                                $wholesale_price = sprintf( '%1$s - %2$s', WWP_Helper_Functions::wwp_formatted_price( $price_min_incl_tax ), WWP_Helper_Functions::wwp_formatted_price( $price_max_incl_tax ) );
                            } else {
                                $wholesale_price = WWP_Helper_Functions::wwp_formatted_price( $price_max_incl_tax );
                            }
                        } elseif ( $price_min_excl_tax !== $price_max_excl_tax ) {
                            // translators: %1$s: Min price, %2$s: Max price.
                            $wholesale_price = sprintf( '%1$s - %2$s', WWP_Helper_Functions::wwp_formatted_price( $price_min_excl_tax ), WWP_Helper_Functions::wwp_formatted_price( $price_max_excl_tax ) );
                        } else {
                            $wholesale_price = WWP_Helper_Functions::wwp_formatted_price( $price_min_excl_tax );
                        }
                    }

                    $wholesale_price_html = '<span style="display: block;" class="wholesale_price_container">
                                                        <span class="wholesale_price_title">' . $wholesale_price_title_text . '</span>
                                                        <ins>' . $wholesale_price . WWP_Wholesale_Prices::get_wholesale_price_suffix( $product, $user_wholesale_role, array() ) . '</ins>
                                                    </span>';

                    return apply_filters( 'wwp_product_original_price', '<del class="original-computed-price">' . $price_html . '</del>', $wholesale_price, $price_html, $product, $user_wholesale_role ) . $wholesale_price_html;

                }
            }

            return $price_html;
        }

        /**
         * Calculates bundle wholesale prices.
         *
         * @since  1.27.2
         * @since  1.27.9 Add fix when bundled item max quantity is empty then use 'min' quantity to avoid error on calculation.
         * @since  1.27.10 Add support if the bundle product is regular priced but contains priced individually items with wholesale price.
         *
         * @param  WC_Product $product             WC_Product object.
         * @param  array      $args                min_or_max, qty, calc.
         * @param  array      $user_wholesale_role User wholesale role.
         * @return int
         */
        public function calculate_wholesale_price( $product, $args, $user_wholesale_role ) {

            $min_or_max = isset( $args['min_or_max'] ) && in_array( $args['min_or_max'], array( 'min', 'max' ), true ) ? $args['min_or_max'] : 'min';
            $qty        = isset( $args['qty'] ) ? absint( $args['qty'] ) : 1;
            $price_calc = isset( $args['calc'] ) && in_array( $args['calc'], array( 'incl_tax', 'excl_tax', 'display', '' ), true ) ? $args['calc'] : '';

            if ( $product->contains( 'priced_individually' ) ) {

                $price_arr = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $product->get_ID(), $user_wholesale_role );

                if ( is_numeric( $price_arr['wholesale_price'] ) ) {

                    $wholesale_price             = $price_arr['wholesale_price'];
                    $wholesale_price_with_no_tax = $price_arr['wholesale_price_with_no_tax'];
                    $wholesale_sale_price        = WWPP_Wholesale_Prices::get_product_wholesale_sale_price( $product->get_ID(), $user_wholesale_role );

                    if ( null !== $wholesale_sale_price && $wholesale_sale_price['is_on_sale'] ) {
                        $wholesale_price             = $wholesale_sale_price['wholesale_sale_price'];
                        $wholesale_price_with_no_tax = $wholesale_sale_price['wholesale_sale_price_with_no_tax'];
                    }

                    if ( 'excl_tax' === $price_calc ) {
                        $price = wc_format_decimal( $wholesale_price_with_no_tax, wc_pb_price_num_decimals() );
                    } else {
                        $price = wc_format_decimal( $wholesale_price, wc_pb_price_num_decimals() );
                    }
                } else {
                    $price = wc_format_decimal( $product->get_price(), wc_pb_price_num_decimals() );
                }

                $bundled_items = $this->filter_bundled_items( $product->get_bundled_items(), $product );

                if ( ! empty( $bundled_items ) ) {
                    foreach ( $bundled_items as $bundled_item ) {
                        if ( false === $bundled_item->is_purchasable() ) {
                            continue;
                        }

                        if ( false === $bundled_item->is_priced_individually() ) {
                            continue;
                        }

                        // If bundled item max quantity is empty then use 'min' quantity to avoid error on calculation.
                        if ( ! $bundled_item->item_data['quantity_max'] ) {
                            $min_or_max = 'min';
                        }

                        $bundled_item_qty = $qty * $bundled_item->get_quantity(
                            $min_or_max,
                            array(
                                'context'        => 'price',
                                'check_optional' => 'min' === $min_or_max,
                            )
                        );

                        if ( $bundled_item_qty ) {
                            if ( WWP_Helper_Functions::wwp_get_product_type( $bundled_item->product ) === 'variable' ) {
                                if ( 'max' === $min_or_max ) {
                                    $min_or_max_product = $bundled_item->max_price_product;
                                } else {
                                    $min_or_max_product = $bundled_item->min_price_product;
                                }

                                if ( property_exists( $min_or_max_product, 'wholesale_price_data' ) && property_exists( $min_or_max_product, 'discounted_wholesale_price' ) && $min_or_max_product->discounted_wholesale_price > 0 ) {
                                    $bundled_item_price = $min_or_max_product->discounted_wholesale_price;
                                } elseif ( property_exists( $min_or_max_product, 'wholesale_price_data' ) ) {
                                    $bundled_item_price = isset( $min_or_max_product->wholesale_price_data['wholesale_sale_price'] ) ? $min_or_max_product->wholesale_price_data['wholesale_sale_price'] : $min_or_max_product->wholesale_price_data['wholesale_price_raw'];
                                } else {
                                    $bundled_item_price = $min_or_max_product->get_price();
                                }
                            } elseif ( property_exists( $bundled_item->product, 'wholesale_price_data' ) && property_exists( $bundled_item->product, 'discounted_wholesale_price' ) && $bundled_item->product->discounted_wholesale_price > 0 ) {
                                $bundled_item_price = $bundled_item->product->discounted_wholesale_price;
                            } elseif ( property_exists( $bundled_item->product, 'wholesale_price_data' ) ) {
                                $bundled_item_price = isset( $bundled_item->product->wholesale_price_data['wholesale_sale_price'] ) ? $bundled_item->product->wholesale_price_data['wholesale_sale_price'] : $bundled_item->product->wholesale_price_data['wholesale_price_raw'];
                            } else {
                                $bundled_item_price = $bundled_item->product->get_price();
                            }

                            if ( 'excl_tax' === $price_calc ) {
                                $bundled_item_price_excl_tax = WWP_Helper_Functions::wwp_get_price_excluding_tax(
                                    $product,
                                    array(
                                        'qty'   => $bundled_item_qty,
                                        'price' => $bundled_item_price,
                                    )
                                );
                                $price                      += wc_format_decimal( $bundled_item_price_excl_tax, wc_pb_price_num_decimals() );
                            } else {
                                $bundled_item_price_incl_tax = WWP_Helper_Functions::wwp_get_price_including_tax(
                                    $product,
                                    array(
                                        'qty'   => $bundled_item_qty,
                                        'price' => $bundled_item_price,
                                    )
                                );
                                $price                      += wc_format_decimal( $bundled_item_price_incl_tax, wc_pb_price_num_decimals() );
                            }
                        }
                    }

                    $group_mode = $product->get_group_mode( 'edit' );

                }
            } else {

                $price_arr                   = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $product->get_ID(), $user_wholesale_role );
                $wholesale_sale_price        = WWPP_Wholesale_Prices::get_product_wholesale_sale_price( $product->get_ID(), $user_wholesale_role );
                $wholesale_price             = $price_arr['wholesale_price'];
                $wholesale_price_with_no_tax = $price_arr['wholesale_price_with_no_tax'];

                if ( null !== $wholesale_sale_price && $wholesale_sale_price['is_on_sale'] ) {
                    $wholesale_price             = $wholesale_sale_price['wholesale_sale_price'];
                    $wholesale_price_with_no_tax = $wholesale_sale_price['wholesale_sale_price_with_no_tax'];
                }

                if ( 'excl_tax' === $price_calc ) {
                    $price = $wholesale_price_with_no_tax;
                } else {
                    $price = $wholesale_price;
                }
            }

            return WWPP_Helper_Functions::woocs_exchange( $price );
        }

        /**
         * Min/max bundle wholesale price.
         *
         * @since  1.27.2
         *
         * @param  WC_Product $product                WC_Product object.
         * @param  array      $user_wholesale_role    User wholesale role.
         * @param  string     $min_or_max             min or max price.
         * @param  bool       $display                Display price.
         * @return int
         */
        public function get_bundle_wholesale_price( $product, $user_wholesale_role, $min_or_max = 'min', $display = false ) {
            return $this->calculate_wholesale_price(
                $product,
                array(
                    'min_or_max' => $min_or_max,
                    'calc'       => $display ? 'display' : '',
                ),
                $user_wholesale_role
            );
        }

        /**
         * Min/max bundle wholesale price including tax.
         *
         * @since  1.27.2
         *
         * @param  WC_Product $product                WC_Product object.
         * @param  array      $user_wholesale_role    User wholesale role.
         * @param  string     $min_or_max             min or max price.
         * @return int
         */
        public function get_bundle_wholesale_price_including_tax( $product, $user_wholesale_role, $min_or_max = 'min' ) {
            return $this->calculate_wholesale_price(
                $product,
                array(
                    'min_or_max' => $min_or_max,
                    'calc'       => 'incl_tax',
                ),
                $user_wholesale_role
            );
        }

        /**
         * Min/max bundle wholesale price excluding tax.
         *
         * @since  1.27.2
         *
         * @param  WC_Product $product                WC_Product object.
         * @param  array      $user_wholesale_role    User wholesale role.
         * @param  string     $min_or_max             min or max price.
         * @return int
         */
        public function get_bundle_wholesale_price_excluding_tax( $product, $user_wholesale_role, $min_or_max = 'min' ) {
            return $this->calculate_wholesale_price(
                $product,
                array(
                    'min_or_max' => $min_or_max,
                    'calc'       => 'excl_tax',
                ),
                $user_wholesale_role
            );
        }

        /**
         * Filter to override the suffix base price for bundled items.
         * If bundled items has wholesale price then calculate the totals of the bundled price, instead using product price.
         *
         * @since 1.27.2
         * @access public
         *
         * @param int        $price_base Base price.
         * @param WC_Product $product    WC_Product object.
         * @return int
         */
        public function filter_bundle_wholesale_price_suffix_base_price( $price_base, $product ) {

            if ( WWP_Helper_Functions::wwp_get_product_type( $product ) === 'bundle' ) {
                $bundled_items = $this->filter_bundled_items( $product->get_bundled_items(), $product );

                if ( ! empty( $bundled_items ) ) {

                    $bundled_items_has_wholesale_data = '';
                    foreach ( $bundled_items as $bundled_item ) {

                        if ( WWP_Helper_Functions::wwp_get_product_type( $bundled_item->product ) === 'variable' ) {
                            $variations = WWP_Helper_Functions::wwp_get_variable_product_variations( $bundled_item->product );

                            foreach ( $variations as $variation ) {
                                if ( ! $variation['is_purchasable'] ) {
                                    continue;
                                }

                                if ( isset( $variation['wholesale_price_raw'] ) && $variation['wholesale_price_raw'] ) {
                                    $bundled_items_has_wholesale_data = 'yes';
                                    break;
                                }
                            }
                        } elseif ( property_exists( $bundled_item->product, 'wholesale_price_data' ) ) {
                            $bundled_items_has_wholesale_data = 'yes';
                            break;
                        }
                    }

                    if ( $product->contains( 'priced_individually' ) && 'yes' === $bundled_items_has_wholesale_data ) {
                        $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

                        if ( wc_prices_include_tax() ) {
                            $price_base = $this->get_bundle_wholesale_price_including_tax( $product, $user_wholesale_role, 'min' );
                        } else {
                            $price_base = $this->get_bundle_wholesale_price_excluding_tax( $product, $user_wholesale_role, 'min' );
                        }
                    }
                }
            }

            return $price_base;
        }

        /**
         * This functions get's product variable price suffix, when '{price_including_tax}', '{price_excluding_tax}' tags are used in the 'Price display suffix'. This will not return any price computation. This fixes price suffix when apply in product variable if '{price_including_tax}', '{price_excluding_tax}' tags are used.
         *
         * @since 1.27.2
         * @access public
         * @param string     $wc_price_suffix          contains price suffix.
         * @param WC_Product $product                  WC_Product object.
         * @param array      $user_wholesale_role      User wholesale role.
         * @return string       Wholesale price suffix
         */
        public function filter_bundle_wholesale_price_display_suffix( $wc_price_suffix, $product, $user_wholesale_role ) {
            if ( ! empty( $user_wholesale_role ) && 'bundle' === WWP_Helper_Functions::wwp_get_product_type( $product ) ) {

                // If product type is bundle product, $user_wholesale_role variable returns string
                // We need to convert this to array to use get_product_wholesale_price_on_shop_v3 function.
                if ( is_string( $user_wholesale_role ) ) {
                    $user_wholesale_role_str = $user_wholesale_role;
                    $user_wholesale_role     = array();
                    $user_wholesale_role[]   = $user_wholesale_role_str;
                }

                // Get product wholesale price.
                $price_arr = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( WWP_Helper_Functions::wwp_get_product_id( $product ), $user_wholesale_role );

                // Get product wholesale raw price, if empty then assign produtcs regular price.
                $price_base = apply_filters( 'wwp_wholesale_price_suffix_base_price', ! empty( $price_arr['wholesale_price_raw'] ) ? $price_arr['wholesale_price_raw'] : $product->get_regular_price(), $product );

                // Get price suffix.
                $suffix              = get_option( 'woocommerce_price_display_suffix' );
                $price_suffix_option = get_option( 'wwpp_settings_override_price_suffix' );
                $wc_price_suffix     = ! empty( $price_suffix_option ) ? $price_suffix_option : $suffix;

                $bundled_items = $this->filter_bundled_items( $product->get_bundled_items(), $product );

                if ( ! empty( $bundled_items ) ) {

                    $bundled_items_has_variable = '';
                    foreach ( $bundled_items as $bundled_item ) {
                        if ( 'variable' === WWP_Helper_Functions::wwp_get_product_type( $bundled_item->product ) ) {
                                $bundled_items_has_variable = 'yes';
                                break;
                        }
                    }
                }

                if ( $product->contains( 'priced_individually' ) && 'yes' === $bundled_items_has_variable ) {

                    // Check if price suffix contain including tax tag {price_including_tax}.
                    if ( false !== strpos( $wc_price_suffix, '{price_including_tax}' ) ) {
                        $price_min_incl_tax = $this->get_bundle_wholesale_price_including_tax( $product, $user_wholesale_role, 'min' );
                        $price_max_incl_tax = $this->get_bundle_wholesale_price_including_tax( $product, $user_wholesale_role, 'max' );

                        if ( $price_min_incl_tax !== $price_max_incl_tax ) {
                            // Get formatted wholesale price with tax.
                            $wholesale_price_excl_tax = WWP_Helper_Functions::wwp_formatted_price( $price_min_incl_tax ) . ' - ' . WWP_Helper_Functions::wwp_formatted_price( $price_max_incl_tax );
                        } else {
                            // Get formatted wholesale price with tax.
                            $wholesale_price_excl_tax = WWP_Helper_Functions::wwp_formatted_price( $price_min_incl_tax );
                        }

                        // Replace {price_including_tax} tag with wholesale price with tax.
                        $wc_price_suffix = str_replace( '{price_including_tax}', $wholesale_price_excl_tax, $wc_price_suffix );
                    }

                    // Check if price suffix contain excluding tax tag {price_excluding_tax}.
                    if ( strpos( $wc_price_suffix, '{price_excluding_tax}' ) !== false ) {
                        $price_min_excl_tax = $this->get_bundle_wholesale_price_excluding_tax( $product, $user_wholesale_role, 'min' );
                        $price_max_excl_tax = $this->get_bundle_wholesale_price_excluding_tax( $product, $user_wholesale_role, 'max' );

                        if ( $price_min_excl_tax !== $price_max_excl_tax ) {
                            // Get formatted wholesale price without tax.
                            $wholesale_price_incl_tax = WWP_Helper_Functions::wwp_formatted_price( $price_min_excl_tax ) . ' - ' . WWP_Helper_Functions::wwp_formatted_price( $price_max_excl_tax );
                        } else {
                            // Get formatted wholesale price with tax.
                            $wholesale_price_incl_tax = WWP_Helper_Functions::wwp_formatted_price( $price_min_excl_tax );
                        }

                        // Replace {price_excluding_tax} tag with wholesale price without tax.
                        $wc_price_suffix = str_replace( '{price_excluding_tax}', $wholesale_price_incl_tax, $wc_price_suffix );
                    }

                    $wc_price_suffix = ' <small class="woocommerce-price-suffix wholesale-price-suffix">' . $wc_price_suffix . '</small>';

                } elseif ( $product->contains( 'priced_individually' ) ) {
                    // Check if price suffix contain including tax tag {price_including_tax}.
                    if ( strpos( $wc_price_suffix, '{price_including_tax}' ) !== false ) {

                        // Get formatted wholesale price with tax.
                        $wholesale_price_incl_tax = WWP_Helper_Functions::wwp_formatted_price(
                            WWP_Helper_Functions::wwp_get_price_including_tax(
                                $product,
                                array(
                                    'qty'   => 1,
                                    'price' => $price_base,
                                )
                            )
                        );
                        // Replace {price_including_tax} tag with wholesale price with tax.
                        $wc_price_suffix = str_replace( '{price_including_tax}', $wholesale_price_incl_tax, $wc_price_suffix );
                    }

                    // Check if price suffix contain excluding tax tag {price_excluding_tax}.
                    if ( strpos( $wc_price_suffix, '{price_excluding_tax}' ) !== false ) {

                        // Get formatted wholesale price without tax.
                        $wholesale_price_excl_tax = WWP_Helper_Functions::wwp_formatted_price(
                            WWP_Helper_Functions::wwp_get_price_excluding_tax(
                                $product,
                                array(
                                    'qty'   => 1,
                                    'price' => $price_base,
                                )
                            )
                        );

                        // Replace {price_excluding_tax} tag with wholesale price without tax.
                        $wc_price_suffix = str_replace( '{price_excluding_tax}', $wholesale_price_excl_tax, $wc_price_suffix );
                    }

                    $wc_price_suffix = ' <small class="woocommerce-price-suffix wholesale-price-suffix">' . $wc_price_suffix . '</small>';
                } else {
                    // Check if price suffix contain including tax tag {price_including_tax}.
                    if ( strpos( $wc_price_suffix, '{price_including_tax}' ) !== false ) {

                        // Get formatted wholesale price with tax.
                        $wholesale_price_incl_tax = WWP_Helper_Functions::wwp_formatted_price(
                            WWP_Helper_Functions::wwp_get_price_including_tax(
                                $product,
                                array(
                                    'qty'   => 1,
                                    'price' => $price_base,
                                )
                            )
                        );
                        // Replace {price_including_tax} tag with wholesale price with tax.
                        $wc_price_suffix = str_replace( '{price_including_tax}', $wholesale_price_incl_tax, $wc_price_suffix );
                    }

                    // Check if price suffix contain excluding tax tag {price_excluding_tax}.
                    if ( strpos( $wc_price_suffix, '{price_excluding_tax}' ) !== false ) {

                        // Get formatted wholesale price without tax.
                        $wholesale_price_excl_tax = WWP_Helper_Functions::wwp_formatted_price(
                            WWP_Helper_Functions::wwp_get_price_excluding_tax(
                                $product,
                                array(
                                    'qty'   => 1,
                                    'price' => $price_base,
                                )
                            )
                        );

                        // Replace {price_excluding_tax} tag with wholesale price without tax.
                        $wc_price_suffix = str_replace( '{price_excluding_tax}', $wholesale_price_excl_tax, $wc_price_suffix );
                    }

                    $wc_price_suffix = ' <small class="woocommerce-price-suffix wholesale-price-suffix">' . $wc_price_suffix . '</small>';
                }
            }

            return $wc_price_suffix;
        }

        /**
         * Add wholesale price column data for each product on the product listing page
         *
         * @since 1.30.2 Add bundle wholesale sale price to the product listing page.
         *
         * @param string $column  Current column.
         * @param int    $post_id Product Id.
         */
        public function add_wholesale_price_column_value_to_bundle_product_cpt_listing( $column, $post_id ) {
            switch ( $column ) {

                case 'wholesale_price':
                    ?>

                    <div class="wholesale_prices" id="wholesale_prices_<?php echo esc_attr( $post_id ); ?>">

                        <style>ins { text-decoration: none !important; }</style>

                        <?php
                        $all_wholesale_roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();
                        $product             = wc_get_product( $post_id );

                        if ( 'bundle' === WWP_Helper_Functions::wwp_get_product_type( $product ) ) {
                            foreach ( $all_wholesale_roles as $roleKey => $role ) {

                                $bundle_wholesale_price = $this->get_bundle_wholesale_price( $product, array( $roleKey ) );

                                if ( ! empty( $bundle_wholesale_price ) ) {

                                    $formatted_wholesale_price = WWP_Helper_Functions::wwp_formatted_price( $bundle_wholesale_price );

                                    $wholesale_price_title_text = __( 'Wholesale Price', 'woocommerce-wholesale-prices-premium' );
                                    $wholesale_price_title_text = apply_filters( 'wwp_filter_wholesale_price_title_text', $wholesale_price_title_text );
                                    $wholesale_price_title_text = str_replace( ':', '', $wholesale_price_title_text );

                                    $wholesale_price_html = '<span style="display: block;" class="wholesale_price_container">
                                            <span class="wholesale_price_title">' . $wholesale_price_title_text . ' From:</span>
                                            <ins>' . $formatted_wholesale_price . '</ins>
                                        </span>';

                                    ?>
                                        <div id="<?php echo esc_attr( $roleKey ); ?>_wholesale_price" class="wholesale_price">
                                            <div class="wholesale_role"><b><?php echo wp_kses_post( $role['roleName'] ); ?></b></div>
                                            <?php echo wp_kses_post( $wholesale_price_html ); ?>
                                        </div>
                                    <?php
                                }
                            }
                        }
                    ?>

                    </div>

                    <?php

                    break;

                default:
                    break;

            }
        }

        /*
        |--------------------------------------------------------------------------
        | Execute Model
        |--------------------------------------------------------------------------
         */

        /**
         * Execute model.
         *
         * @since 1.13.0
         * @access public
         */
        public function run() {

            if ( WWP_Helper_Functions::is_plugin_active( 'woocommerce-product-bundles/woocommerce-product-bundles.php' ) ) {

                $wwp_plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/woocommerce-wholesale-prices/woocommerce-wholesale-prices.bootstrap.php', false, false );

                add_action( 'woocommerce_product_options_pricing', array( $this, 'add_wholesale_price_fields' ), 11 );
                add_action( 'woocommerce_process_product_meta_bundle', array( $this, 'save_wholesale_price_fields' ), 20, 1 );
                add_action( 'woocommerce_process_product_meta_bundle', array( $this, 'save_minimum_order_quantity_fields' ), 20, 1 );
                add_action( 'woocommerce_process_product_meta_bundle', array( $this, 'save_order_quantity_step_fields' ), 20, 1 );

                add_filter( 'woocommerce_bundle_price_data', array( $this, 'filter_bundle_product_base_price' ), 10, 2 );

                add_filter( 'woocommerce_bundled_items', array( $this, 'filter_bundled_items' ), 10, 2 );

                add_action( 'woocommerce_bundled_single_variation', array( $this, 'filter_variable_bundle_variations_data' ), 10, 2 );
                add_action( 'woocommerce_bundled_single_variation', array( $this, 'filter_per_variation_price_html' ), 10, 2 );

                // WC 2.7.
                add_filter( 'wwp_third_party_product_id', array( $this, 'get_product_id' ), 10, 2 );

                // Quick edit support.
                add_filter( 'wwp_quick_edit_allowed_product_types', array( $this, 'support_for_quick_edit_fields' ), 10, 2 );

                // Only perform setting wholesale price if bundle pricing filters are set.
                add_action( 'woocommerce_bundled_product_price_filters_added', array( $this, 'filter_bundle_item_pricing' ), 10, 1 );

                // On save post, set visibility meta.
                add_action( 'save_post', array( $this, 'set_bundle_product_visibility_meta' ), 20, 1 );

                // Re-initialize visibility meta when general discount is removed or added.
                add_action( 'wwpp_add_wholesale_role_general_discount_mapping', array( $this, 're_initialize_visibility_meta' ), 10, 1 );
                add_action( 'wwpp_delete_wholesale_role_general_discount_mapping', array( $this, 're_initialize_visibility_meta' ), 10, 1 );

                // Apply additional discount to wholesale price if Bundle Item have Discount set in the bundle item setting.
                add_filter( 'wwp_filter_wholesale_price_html', array( $this, 'filter_wholesale_price_html' ), 7, 7 );
                // Apply Wholesale Price on the bundle product price if product price is set to zero but the bundled items has wholesale prices.
                add_filter( 'woocommerce_get_bundle_price_html', array( $this, 'filter_woocommerce_get_bundle_price_html' ), 5, 2 );

                // Apply Wholesale Price Suffix in bundle subtotal calculation.
                add_filter( 'woocommerce_bundle_front_end_params', array( $this, 'filter_bundle_front_end_params' ), 10, 1 );

                // Apply Wholesale Price Suffix in bundle product price if product price is set to zero.
                add_filter( 'woocommerce_get_price_suffix', array( $this, 'filter_woocommerce_get_price_suffix' ), 10, 4 );
                // Apply filter for product variables on price suffix.
                add_filter( 'wwp_filter_wholesale_price_display_suffix', array( $this, 'filter_bundle_wholesale_price_display_suffix' ), 20, 3 );

                // Apply filter to override the suffix base price for bundled items.
                add_filter( 'wwp_wholesale_price_suffix_base_price', array( $this, 'filter_bundle_wholesale_price_suffix_base_price' ), 5, 2 );

                // Add wholesale price column to bundle product listing page.
                add_action( 'manage_product_posts_custom_column', array( $this, 'add_wholesale_price_column_value_to_bundle_product_cpt_listing' ), 99, 2 );

                // Save wholesale sale price fields.
                if ( version_compare( $wwp_plugin_data['Version'], '2.1.6', '>=' ) ) {
                    add_action( 'woocommerce_process_product_meta_bundle', array( $this->_wwpp_admin_custom_fields_simple_product, 'save_wholesale_sale_price_fields' ), 20, 1 );
                }
            }
        }
    }

}
