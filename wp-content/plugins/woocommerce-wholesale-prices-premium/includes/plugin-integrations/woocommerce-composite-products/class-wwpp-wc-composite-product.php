<?php
if ( ! defined( 'ABSPATH' ) ) {
exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WWPP_WC_Composite_Product' ) ) {

    /**
     * Model that houses the logic of integrating with 'WooCommerce Composite Products' plugin.
     *
     * Composite products just inherits from simple product so that's why they are very similar.
     * So most of the codebase here are just reusing the codes from simple product.
     *
     * @since 1.13.0
     */
    class WWPP_WC_Composite_Product {
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
         * Model that houses logic  admin custom fields for simple products.
         *
         * @since 1.13.0
         * @access private
         * @var WWPP_Admin_Custom_Fields_Simple_Product
         */
        private $_wwpp_admin_custom_fields_simple_product;

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
         * Add composite product wholesale price field.
         *
         * @since 1.13.0
         * @access public
         */
        public function add_wholesale_price_fields() {

            global $post, $wc_wholesale_prices;

            $product = wc_get_product( $post->ID );

            if ( WWP_Helper_Functions::wwp_get_product_type( $product ) === 'composite' ) {
                $wc_wholesale_prices->wwp_admin_custom_fields_simple_product->add_wholesale_price_fields();
            }
        }

        /**
         * Save composite product wholesale price.
         *
         * @since 1.12.0
         * @since 1.13.0 Refactor codebase and move to its new refactored model.
         * @access public
         *
         * @param int $post_id Composite product id.
         */
        public function save_wholesale_price_fields( $post_id ) {

            global $wc_wholesale_prices;

            $wc_wholesale_prices->wwp_admin_custom_fields_simple_product->save_wholesale_price_fields( $post_id, 'composite' );
        }

        /**
         * Save minimum order quantity custom field value for composite products on product edit page.
         *
         * @since 1.12.0
         * @since 1.13.0 Refactor codebase and move to its new refactored model.
         * @access public
         *
         * @param int $post_id Composite product id.
         */
        public function save_minimum_order_quantity_fields( $post_id ) {

            // Composite products are very similar to simple products in terms of their fields structure.
            // Therefore we can reuse the code we have on saving wholesale minimum order quantity for simple products to composite products.
            // BTW the adding of custom wholesale minimum order quantity field to composite products are already handled by this function 'add_minimum_order_quantity_fields' on 'WWPP_Admin_Custom_Fields_Simple_Product'. Read the desc of the function.
            $this->_wwpp_admin_custom_fields_simple_product->save_minimum_order_quantity_fields( $post_id, 'composite' );
        }

        /**
         * Check if a user role have access to certain composite product component option.
         * I used the component option term coz take note, a component can have multiple products ( options ).
         *
         * @since 1.12.5
         * @since 1.13.0 Move to its new refactored model.
         * @access public
         *
         * @param array  $component_options  Component options to traverse.
         * @param string $user_role_to_check User role to check if it has access to the component options.
         * @return boolean Flag that determines if a user have access to composite product component.
         */
        public function check_user_have_access_to_component_option( $component_options, $user_role_to_check = 'all' ) {

            if ( ! is_array( $component_options ) ) {
                $component_options[] = (int) $component_options;
            }

            foreach ( $component_options as $product_id ) {

                $curr_product_wholesale_filter = get_post_meta( $product_id, WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER );
                if ( ! is_array( $curr_product_wholesale_filter ) ) {
                    $curr_product_wholesale_filter = array();
                }

                if ( in_array( 'all', $curr_product_wholesale_filter, true ) || in_array( $user_role_to_check, $curr_product_wholesale_filter, true ) ) {
                    continue;
                } else {
                    return false;
                }
            }

            return true;
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
                $allowed_product_types[] = 'composite';
            }

            return $allowed_product_types;
        }

        /**
         * Filter the composite products.
         *
         * @param array $option_data Components option data.
         * @param array $component   Component options.
         *
         * @since 1.30.2
         *
         * @return string
         */
        public function filter_woocommerce_composite_component_option_data( $option_data, $component ) { // phpcs:ignore.
            $composite_product = wc_get_product( get_the_ID() );
            $wholesale_data    = array();

            // Check if the product is a composite product.
            if ( $composite_product && $composite_product->is_type( 'composite' ) ) {

                $wholesale_parent_price = 0;
                $user_wholesale_role    = $this->_wwpp_wholesale_roles->getUserWholesaleRole();
                if ( ! empty( $user_wholesale_role ) ) {

                    // Get the composite product data.
                    $components          = $composite_product->get_components();
                    $priced_individually = false;
                    foreach ( $components as $comp ) {
                        $component_options  = $comp->get_options();
                        $component_settings = $comp->get_data();

                        if ( in_array( $option_data['option_id'], $component_options, true ) && 'yes' === $component_settings['priced_individually'] ) {
                            $priced_individually = true;
                        }
                    }

                    $wholesale_parent_price_raw = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $composite_product->get_id(), $user_wholesale_role );
                    if ( ! empty( $wholesale_parent_price_raw['wholesale_price_raw'] ) ) {
                        $wholesale_parent_price   = $wholesale_parent_price_raw['wholesale_price_raw'];
                        $wholesale_sale_price_raw = WWPP_Wholesale_Prices::get_product_wholesale_sale_price( $composite_product->get_id(), $user_wholesale_role );
                        if ( ! empty( $wholesale_sale_price_raw['is_on_sale'] ) && isset( $wholesale_sale_price_raw['wholesale_sale_price'] ) ) {
                            $wholesale_parent_price = $wholesale_sale_price_raw['wholesale_sale_price'];
                        }
                    }

                    if ( ! empty( $option_data['option_id'] ) && $priced_individually ) {

                        $product_id = $option_data['option_id'];
                        $product    = wc_get_product( $product_id );

                        if ( 'variable' === $product->get_type() ) {
                            $variations = WWP_Helper_Functions::wwp_get_variable_product_variations( $product );

                            $variation_options = array();
                            if ( ! empty( $variations ) ) {
                                foreach ( $variations as $variation ) {
                                    $variation_product = wc_get_product( $variation['variation_id'] );
                                    $attribute_val     = array();
                                    $wholesale_price   = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $variation['variation_id'], $user_wholesale_role );

                                    if ( ! empty( $variation['attributes'] ) ) {
                                        foreach ( $variation['attributes'] as $attribute ) {
                                            $attribute_val[] = $attribute;
                                        }
                                    }

                                    $variation_product_price = $wholesale_price['wholesale_price'];
                                    if ( empty( $wholesale_price['wholesale_price'] ) ) {
                                        $variation_product_price = $variation_product->get_price();
                                    } else {
                                        // Get the wholesale sale price.
                                        $wholesale_sale_price_raw = WWPP_Wholesale_Prices::get_product_wholesale_sale_price( $variation['variation_id'], $user_wholesale_role );
                                        if ( ! empty( $wholesale_sale_price_raw['is_on_sale'] ) && isset( $wholesale_sale_price_raw['wholesale_sale_price'] ) ) {
                                            $variation_product_price = $wholesale_sale_price_raw['wholesale_sale_price'];
                                        }
                                    }
                                    $variation_options[] = array(
                                        'variation_attributes' => $attribute_val,
                                        'variation_id' => $variation['variation_id'],
                                        'wholesale_price_raw' => $variation_product_price,
                                    );
                                }
                            }

                            $wholesale_data['wholesale_variations'] = $variation_options;
                        } else {
                            $wholesale_price = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $product_id, $user_wholesale_role );
                            $product_price   = $wholesale_price['wholesale_price'];
                            if ( empty( $wholesale_price['wholesale_price'] ) ) {
                                $product_price = $product->get_price();
                            } else {
                                // Get the wholesale sale price.
                                $wholesale_sale_price_raw = WWPP_Wholesale_Prices::get_product_wholesale_sale_price( $product_id, $user_wholesale_role );
                                if ( ! empty( $wholesale_sale_price_raw['is_on_sale'] ) && isset( $wholesale_sale_price_raw['wholesale_sale_price'] ) ) {
                                    $product_price = $wholesale_sale_price_raw['wholesale_sale_price'];
                                }
                            }
                            $wholesale_data['wholesale_price_raw'] = $product_price;
                        }
                        $wholesale_price_total_title_text                   = __( 'Wholesale Total Price:', 'woocommerce-wholesale-prices-premium' );
                        $wholesale_data['wholesale_price_total_title_text'] = $wholesale_price_total_title_text;
                        $wholesale_data['wc_active_currency']               = get_woocommerce_currency_symbol();
                    }

                    $wholesale_data['wholesale_parent_title'] = __( 'Main Price:', 'woocommerce-wholesale-prices-premium' );
                    $wholesale_data['priced_individually']    = $priced_individually;
                    $wholesale_data['wholesale_parent_price'] = $wholesale_parent_price;
                }
            }
            $option_data['wwpp_data'] = $wholesale_data;
            return $option_data;
        }

        /**
         * Add wholesale price column data for each product on the product listing page
         *
         * @since 1.30.2 Add composite wholesale sale price to the product listing page.
         *
         * @param string $column  Current column.
         * @param int    $post_id Product Id.
         */
        public function add_wholesale_price_column_value_to_composite_product_cpt_listing( $column, $post_id ) {
            switch ( $column ) {

                case 'wholesale_price':
                    ?>

                    <div class="wholesale_prices" id="wholesale_prices_<?php echo esc_attr( $post_id ); ?>">

                        <style>ins { text-decoration: none !important; }</style>

                        <?php
                        $all_wholesale_roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();
                        $product             = wc_get_product( $post_id );

                        if ( 'composite' === WWP_Helper_Functions::wwp_get_product_type( $product ) ) {
                            foreach ( $all_wholesale_roles as $roleKey => $role ) {

                                $wholesale_price = WWPP_Helper_Functions::wwpp_get_composite_product_wholesale_price_from( $product, array( $roleKey ) );

                                if ( ! empty( $wholesale_price ) ) {

                                    $formatted_wholesale_price = WWP_Helper_Functions::wwp_formatted_price( $wholesale_price );

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

        /**
         * Add wholesale price to composite product in catalog.
         *
         * @since 1.30.2
         *
         * @param string $price_range Composite product price range.
         * @param object $product     Composite product object.
         */
        public function filter_wwpp_woocommerce_composite_price_html( $price_range, $product ) {
            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            if ( ! empty( $user_wholesale_role ) ) {
                $wholesale_price           = WWPP_Helper_Functions::wwpp_get_composite_product_wholesale_price_from( $product, $user_wholesale_role );
                $formatted_wholesale_price = WWP_Helper_Functions::wwp_formatted_price( $wholesale_price );

                $wholesale_price_title_text = __( 'Wholesale Price', 'woocommerce-wholesale-prices-premium' );
                $wholesale_price_title_text = apply_filters( 'wwp_filter_wholesale_price_title_text', $wholesale_price_title_text );
                $wholesale_price_title_text = str_replace( ':', '', $wholesale_price_title_text );

                $wholesale_price_html = '<del class="original-computed-price">' . $price_range . '</del>';

                $wholesale_price_html .= '<span style="display: block;" class="wholesale_price_container">
                <span class="wholesale_price_title">' . $wholesale_price_title_text . ' From:</span>
                <ins>' . $formatted_wholesale_price . '</ins>
                </span>';

                return sprintf( '<span class="price">%s</span>', wp_kses_post( $wholesale_price_html ) );
            } else {
                return wp_kses_post( $price_range );
            }
        }

        /**
         * Filter composite product components.
         *
         * @since 1.30.5
         *
         * @param array  $components Composite product components.
         * @param object $product   Composite product object.
         */
        public function filter_message_woocommerce_composite_before_components( $components, $product ) {
            if ( $product && is_a( $product, 'WC_Product_Composite' ) ) {
                $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();
                $user_role           = ! empty( $user_wholesale_role ) ? $user_wholesale_role[0] : 'all';

                if ( ! empty( $components ) ) {
                    foreach ( $components as $component ) {
                        // Get component details.
                        $component_id = $component->get_id();
                        $component    = $product->get_component( $component_id );

                        if ( ! $this->check_user_have_access_to_component_option( $this->get_component_options( $component ), $user_role ) ) {

                            // Remove options.
                            $this->remove_composite_actions();

                            if ( 'yes' !== $component['optional'] ) {
                                $required_component_text = __(
                                    'You do not have access to a required component',
                                    'woocommerce-wholesale-prices-premium'
                                );
                                $contact_store_text      = __(
                                    'Please contact store owner.',
                                    'woocommerce-wholesale-prices-premium'
                                );

                                echo '<div class="unavailable-component">';
                                echo '<p>';
                                echo esc_html( $required_component_text ) . ' <b>(' . esc_html( $component['title'] ) . ')</b>. ' . esc_html( $contact_store_text );
                                echo '</p>';
                                echo '</div>';
                            }
                        }
                    }
                }
            }
        }

        /**
         * Remove composite actions.
         *
         * @since 1.30.5
         */
        public function remove_composite_actions() {
            // Remove single component actions.
            remove_action( 'woocommerce_composite_component_selections_single', 'wc_cp_component_options_sorting', 10 );
            remove_action( 'woocommerce_composite_component_selections_single', 'wc_cp_component_options_filtering', 20 );
            remove_action( 'woocommerce_composite_component_selections_single', 'wc_cp_component_options_title', 30 );
            remove_action( 'woocommerce_composite_component_selections_single', 'wc_cp_component_options_pagination_top', 39 );
            remove_action( 'woocommerce_composite_component_selections_single', 'wc_cp_component_options', 40 );
            remove_action( 'woocommerce_composite_component_selections_single', 'wc_cp_component_options_pagination_bottom', 41 );
            remove_action( 'woocommerce_composite_component_selections_single', 'wc_cp_component_selection', 50 );

            // Remove progressive component actions.
            remove_action( 'woocommerce_composite_component_selections_progressive', 'wc_cp_component_options_progressive_start', 0 );
            remove_action( 'woocommerce_composite_component_selections_progressive', 'wc_cp_component_options_sorting', 10 );
            remove_action( 'woocommerce_composite_component_selections_progressive', 'wc_cp_component_options_filtering', 20 );
            remove_action( 'woocommerce_composite_component_selections_progressive', 'wc_cp_component_options_title', 30 );
            remove_action( 'woocommerce_composite_component_selections_progressive', 'wc_cp_component_options_pagination_top', 39 );
            remove_action( 'woocommerce_composite_component_selections_progressive', 'wc_cp_component_options', 40 );
            remove_action( 'woocommerce_composite_component_selections_progressive', 'wc_cp_component_options_pagination_bottom', 41 );
            remove_action( 'woocommerce_composite_component_selections_progressive', 'wc_cp_component_options_progressive_end', 45 );
            remove_action( 'woocommerce_composite_component_selections_progressive', 'wc_cp_component_selection', 50 );
            remove_action( 'woocommerce_composite_component_selections_progressive', 'wc_cp_component_selection_message_progressive', 40 );

            // Remove multi-page component actions.
            remove_action( 'woocommerce_composite_component_selections_paged', 'wc_cp_component_options_scroll_target_paged_top', -20 );
            remove_action( 'woocommerce_composite_component_selections_paged', 'wc_cp_component_options_message_paged_top', -10 );
            remove_action( 'woocommerce_composite_component_selections_paged', 'wc_cp_component_selection_paged_top', 0 );
            remove_action( 'woocommerce_composite_component_selections_paged', 'wc_cp_component_options_sorting', 10 );
            remove_action( 'woocommerce_composite_component_selections_paged', 'wc_cp_component_options_filtering', 20 );
            remove_action( 'woocommerce_composite_component_selections_paged', 'wc_cp_component_options_title', 30 );
            remove_action( 'woocommerce_composite_component_selections_paged', 'wc_cp_component_options_pagination_top', 39 );
            remove_action( 'woocommerce_composite_component_selections_paged', 'wc_cp_component_options', 40 );
            remove_action( 'woocommerce_composite_component_selections_paged', 'wc_cp_component_options_pagination_bottom', 41 );
            remove_action( 'woocommerce_composite_component_selections_paged', 'wc_cp_component_selection_paged_bottom', 50 );
            remove_action( 'woocommerce_composite_component_selections_paged', 'wc_cp_component_selection_message_paged_bottom', 60 );

            // Remove composite actions.
            remove_action( 'woocommerce_composite_after_components', 'wc_cp_after_components', 10 );
            remove_action( 'woocommerce_composite_after_components', 'wc_cp_no_js_msg', 15 );
        }

        /**
         * Execute model.
         *
         * @since 1.13.0
         * @access public
         */
        public function run() {

            if ( WWP_Helper_Functions::is_plugin_active( 'woocommerce-composite-products/woocommerce-composite-products.php' ) ) {

                add_action( 'woocommerce_product_options_pricing', array( $this, 'add_wholesale_price_fields' ), 11 );
                add_action( 'woocommerce_process_product_meta_composite', array( $this, 'save_wholesale_price_fields' ), 20, 1 );
                add_action( 'woocommerce_process_product_meta_composite', array( $this, 'save_minimum_order_quantity_fields' ), 20, 1 );

                // WC 2.7.
                add_filter( 'wwp_third_party_product_id', array( $this, 'get_product_id' ), 10, 2 );

                // Quick edit support.
                add_filter( 'wwp_quick_edit_allowed_product_types', array( $this, 'support_for_quick_edit_fields' ), 10, 2 );

                // Filter composite component option data.
                add_filter( 'woocommerce_composite_component_option_data', array( $this, 'filter_woocommerce_composite_component_option_data' ), 10, 2 );

                // Add wholesale price column to composite product listing page.
                add_action( 'manage_product_posts_custom_column', array( $this, 'add_wholesale_price_column_value_to_composite_product_cpt_listing' ), 99, 2 );

                // Filter composite product price.
                add_filter( 'woocommerce_composite_price_html', array( $this, 'filter_wwpp_woocommerce_composite_price_html' ), 10, 2 );
                add_filter( 'woocommerce_composite_sale_price_html', array( $this, 'filter_wwpp_woocommerce_composite_price_html' ), 10, 2 );
                add_filter( 'woocommerce_composite_free_price_html', array( $this, 'filter_wwpp_woocommerce_composite_price_html' ), 10, 2 );

                // Filter composite product components.
                add_action( 'woocommerce_composite_before_components', array( $this, 'filter_message_woocommerce_composite_before_components' ), 10, 2 );
            }
        }

        /**
         * Get component options
         *
         * @since 1.14.2
         * @access public
         *
         * @param WC_CP_Component $component component object.
         */
        public function get_component_options( $component ) {

            if ( is_a( $component, 'WC_CP_Component' ) ) {

                $wccp_data = WWP_Helper_Functions::get_plugin_data( 'woocommerce-composite-products/woocommerce-composite-products.php' );

                if ( version_compare( $wccp_data['Version'], '3.9.0', '>=' ) ) {
                    return $component->get_options();
                } else {
                    return $component->options;
                }
            } else {

                error_log( 'WWPP Error : WWPP_WC_Composite_Product::get_component_options method expect parameter $component of type WC_CP_Component.' ); // phpcs:ignore.

                return 0;

            }
        }
    }
}
