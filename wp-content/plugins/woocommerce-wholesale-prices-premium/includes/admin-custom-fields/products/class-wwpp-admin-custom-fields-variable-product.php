<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWPP_Admin_Custom_Fields_Variable_Product' ) ) {

    /**
     * Model that houses logic  admin custom fields for variable products.
     *
     * @since 1.13.0
     */
    class WWPP_Admin_Custom_Fields_Variable_Product {

        /**
         * Class Properties
         */

        /**
         * Property that holds the single main instance of WWPP_Admin_Custom_Fields_Variable_Product.
         *
         * @since 1.13.0
         * @access private
         * @var WWPP_Admin_Custom_Fields_Variable_Product
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
         * Array of registered wholesale roles.
         *
         * @since 1.13.0
         * @access private
         * @var array
         */
        private $_registered_wholesale_roles;

        /*
        |--------------------------------------------------------------------------
        | Class Methods
        |--------------------------------------------------------------------------
         */

        /**
         * WWPP_Admin_Custom_Fields_Variable_Product constructor.
         *
         * @since 1.13.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Admin_Custom_Fields_Variable_Product model.
         */
        public function __construct( $dependencies ) {
            $this->_wwpp_wholesale_roles  = $dependencies['WWPP_Wholesale_Roles'];
            $this->_wwpp_wholesale_prices = $dependencies['WWPP_Wholesale_Prices'];

            $this->_registered_wholesale_roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();
        }

        /**
         * Ensure that only one instance of WWPP_Admin_Custom_Fields_Variable_Product is loaded or can be loaded (Singleton Pattern).
         *
         * @since 1.13.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Admin_Custom_Fields_Variable_Product model.
         * @return WWPP_Admin_Custom_Fields_Variable_Product
         */
        public static function instance( $dependencies ) {
            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Get curent user wholesale role.
         *
         * @since 1.13.0
         * @access private
         *
         * @return string User role string or empty string.
         */
        private function _get_current_user_wholesale_role() {
            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            return ( is_array( $user_wholesale_role ) && ! empty( $user_wholesale_role ) ) ? $user_wholesale_role[0] : '';
        }

        /*
        |--------------------------------------------------------------------------
        | Wholesale Exclusive Variation
        |--------------------------------------------------------------------------
         */

        /**
         * Add wholesale users exclusive variation custom field to variable products on product edit screen.
         * Custom fields are added per variation, not to the parent variable product.
         *
         * @since 1.3.0
         * @since 1.13.0 Refactor codebase and make it use select box now instead of separate checkboxes per role. Move to its own model.
         * @access public
         *
         * @param int     $loop           Loop counter.
         * @param array   $variation_data Array of variation data.
         * @param WP_Post $variation      Variation product object.
         */
        public function add_variation_wholesale_role_visibility_filter_field( $loop, $variation_data, $variation ) {
            global $woocommerce, $post;

            /**
             * Allows to hide role restriction filter field
             *
             * @param boolean $show Whether to show the role restriction filter field. Default true
             * @return boolean
             */
            if ( ! apply_filters( 'wwpp_product_editor_show_role_visibility_filter', true ) ) {
                return;
            }

            // Get the variable product data manually
            // Don't rely on the variation data woocommerce supplied
            // There is a logic change introduced on 2.3 series where they only send variation data (or variation meta)
            // That is built in to woocommerce, so all custom variation meta added to a variable product don't get passed along.
            $variable_product = wc_get_product( $variation->ID );
            $option_value     = WWP_Helper_Functions::get_formatted_meta_data( $variable_product, WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER );

            /**
             * Filter the wholesale roles for wholesale exclusive varioations.
             *
             * @param array   $registered_wholesale_roles The list of currently registered wholesale roles.
             * @param WP_Post $variation The variation post object.
             * @param WP_Post $post The variable product post object.
             */
            $registered_wholesale_roles = apply_filters(
                'wwpp_wholesale_exclusive_variation_roles',
                $this->_registered_wholesale_roles,
                $variation,
                $post
            );
            ?>

            <div class="wholesale-exclusive-variation-options-group options-group options_group"
                style="border-top: 1px solid #DDDDDD; padding-bottom: 15px;">

                <script>
                jQuery(document).ready(function($) {
                    $(".chosen-select").chosen();
                });
                </script>

                <style>
                .chosen-container-multi,
                .chosen-container-multi input[type='text'] {
                    width: 100% !important;
                }
                </style>

                <header class="form-row form-row-full">
                    <h4 style="font-size: 14px; margin: 10px 0;">
                        <?php esc_html_e( 'Wholesale Exclusive Variation', 'woocommerce-wholesale-prices-premium' ); ?></h4>
                    <p style="margin:0; padding:0; line-height: 16px; font-style: italic; font-size: 13px;">
                        <?php echo wp_kses_post( __( 'Specify if this variation should be exclusive to wholesale roles. Leave empty to make it available to all.<br><br>', 'woocommerce-wholesale-prices-premium' ) ); ?>
                    </p>
                </header>

                <div class="form-row form-row-full" style="position: relative;">
                    <select multiple name="<?php echo esc_attr( WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER ) . '[' . esc_attr( $loop ) . '][]'; ?>"
                        class="chosen-select" style="width: 100%;"
                        data-placeholder="<?php esc_attr_e( 'Choose wholesale users...', 'woocommerce-wholesale-prices-premium' ); ?>">
                        <?php foreach ( $registered_wholesale_roles as $role_key => $role ) { ?>
                        <option value="<?php echo esc_attr( $role_key ); ?>"
                            <?php echo in_array( $role_key, $option_value ) ? 'selected' : ''; ?>><?php echo esc_html( $role['roleName'] ); //phpcs:ignore ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>

            </div>
            <!--.options_group-->

            <?php
        }

        /**
         * Save wholesale exclusive variation custom field for variable products on product edit page.
         *
         * @since 1.3.0
         * @since 1.13.0 Refactor codebase and make it use select box now instead of separate checkboxes per role. Move to its own model.
         * @access public
         *
         * @param int $post_id Variable product id.
         */
        public function save_variation_wholesale_role_visibility_filter_field( $post_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
            // phpcs:disable WordPress.Security.NonceVerification.Missing
            global $_POST;

            if ( isset( $_POST['variable_post_id'] ) ) {

                $variable_post_id = $_POST['variable_post_id'];
                $max_loop         = max( array_keys( $variable_post_id ) );

                for ( $i = 0; $i <= $max_loop; $i++ ) {

                    if ( ! isset( $variable_post_id[ $i ] ) ) {
                        continue;
                    }

                    $variation_id = (int) $variable_post_id[ $i ];

                    // Get variant product object.
                    $variation_product = wc_get_product( $variation_id );

                    $variation_product->delete_meta_data( WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER );

                    if ( isset( $_POST[ WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER ][ $i ] ) ) {

                        if ( is_array( $_POST[ WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER ][ $i ] ) ) {

                            foreach ( $_POST[ WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER ][ $i ] as $role_key ) {
                                $variation_product->add_meta_data( WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER, $role_key );
                            }
                        } else {
                            $variation_product->add_meta_data( WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER, $_POST[ WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER ][ $i ] );
                        }
                    } else {
                        $variation_product->add_meta_data( WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER, 'all' );
                    }

                    // Save the data.
                    $variation_product->save();
                }
            }
            // phpcs:enable WordPress.Security.NonceVerification.Missing
        }

        /**
         * Apply filter to only show variations of a variable product in the proper time and place.
         * ( Only show variations with wholesale price on wholesale users if setting is enabled )
         * ( Only show variations to appropriate wholesale users if it is set to be exclusively visible to certain wholesale roles )
         *
         * @since 1.6.0
         * @since 1.13.0 Refactor codebase and make it use select box now instead of separate checkboxes per role. Move to its own model.
         *
         * @param boolean      $visible       Flag that detemines if the current variation is visible to the current user or not.
         * @param int          $variation_id  Variation id.
         * @param int          $variable_id   Variable id.
         * @param WC_Variation $variation_obj Variation object.
         * @return boolean Modified flag that detemines if the current varition is visible to the current user or not.
         */
        public function filter_variation_visibility( $visible, $variation_id, $variable_id, $variation_obj ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
            return $this->filter_variation_availability( $visible, $variation_id );
        }

        /**
         * Apply filter to only make variations of a variable product purchasable in the proper time and place.
         * ( Only make variations purchasable with wholesale price on wholesale users if setting is enabled )
         * ( Only make variations purchasable to appropriate wholesale users if it is set to be exclusively visible to certain wholesale roles )
         *
         * @since 1.13.0
         * @access public
         *
         * @param boolean              $purchasable   Flag that determines if variation is purchasable.
         * @param WC_Product_Variation $variation_obj Variation product object.
         * @return boolean Modified flag that determines if variation is purchasable.
         */
        public function filter_variation_purchasability( $purchasable, $variation_obj ) {
            return $this->filter_variation_availability( $purchasable, WWP_Helper_Functions::wwp_get_product_id( $variation_obj ) );
        }

        /**
         * Filter the default attribute of a variable product and check if the matching variation qualifies to be displayed for the current user.
         *
         * @since 1.13.0
         * @access public
         *
         * @param array               $default_attribute Array of default attributes.
         * @param WC_Product_Variable $variable_product  Variable product object.
         * @return array|string We are returning empty string on failure for some reason, I didn't change to returning empty array, I think there is a good reason why we did that.
         */
        public function filter_variable_product_default_attributes( $default_attribute, $variable_product ) {
            if ( ! current_user_can( 'manage_options' ) ) {

                // Prepare default attribute for compatibility with 'get_matching_variation' or 'find_matching_product_variation' (WC 2.7) function parameter.
                $processed_default_attribute = array();
                foreach ( $default_attribute as $key => $val ) {
                    $processed_default_attribute[ 'attribute_' . $key ] = $val;
                }

                // Get the variation id that matched the default attributes.
                $variation_id = WWP_Helper_Functions::wwp_get_matching_variation( $variable_product, $processed_default_attribute );

                if ( $variation_id ) {
                    if ( ! $this->filter_variation_availability( true, $variation_id ) ) {
                        return '';
                    }
                }
            }

            return $default_attribute;
        }

        /**
         * Check if variation is available based on set of filters.
         *
         * @since 1.13.0
         * @since 1.27.1 Don't restrict Shop Manager to view all product variations available
         * @access public
         *
         * @param boolean $available    Flag that determines whether variation is available or not.
         * @param int     $variation_id Variation id.
         * @return boolean Filtered flag that determines whether variation is available or not.
         */
        public function filter_variation_availability( $available, $variation_id ) {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {

                // Get the variation object.
                $variation_obj = wc_get_product( $variation_id );

                $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

                if ( ! empty( $user_wholesale_role ) && get_option( 'wwpp_settings_only_show_wholesale_products_to_wholesale_users', false ) === 'yes' ) {

                    $price_arr       = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $variation_id, $user_wholesale_role );
                    $wholesale_price = $price_arr['wholesale_price'];

                    if ( empty( $wholesale_price ) ) {
                        $available = false;
                    }
                }

                $variation_visibility_filter = WWP_Helper_Functions::get_formatted_meta_data( $variation_obj, WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER );
                $user_wholesale_role         = ! empty( $user_wholesale_role ) ? $user_wholesale_role[0] : '';

                if ( ! empty( $variation_visibility_filter ) ) {
                    if ( ! in_array( $user_wholesale_role, $variation_visibility_filter ) && ! in_array( 'all', $variation_visibility_filter ) ) { //phpcs:ignore
                        $available = false;
                    }
                }
            }

            return $available;
        }

        /*
        |--------------------------------------------------------------------------------------------------------------
        | Variable Wholesale Minimum Order Quantity
        |--------------------------------------------------------------------------------------------------------------
         */

        /**
         * Add variable product level wholesale minimum order quantity custom field.
         *
         * @since 1.9.0
         * @since 1.13.0 Refactor codebase and move to its own model.
         * @access public
         */
        public function add_variable_product_level_wholesale_min_order_qty_custom_field() {
            /**
             * Allows to hide order quantity base wholesale pricing
             *
             * @param boolean $show Whether to show the pricing fields or not. Default true
             * @return boolean
             */
            if ( ! apply_filters( 'wwpp_product_editor_show_order_quantity_fields', true ) ) {
                return;
            }

            global $woocommerce, $post;

            $product = wc_get_product( $post->ID );

            if ( WWP_Helper_Functions::wwp_get_product_type( $product ) === 'variable' ) {
                ?>

            <div class="wholesale-minium-order-quantity-options-group options-group options_group"
                style="border-top: 1px solid #EEEEEE;">

                <header>
                    <h3 style="font-size: 14px; margin: 10px 12px;">
                        <?php esc_html_e( 'Parent Product Wholesale Minimum Order Quantity', 'woocommerce-wholesale-prices-premium' ); ?></h3>
                    <p style="margin:0; padding:0 12px; line-height: 16px; font-style: italic; font-size: 13px;">
                        <?php esc_html_e( 'Customers can add multiple variations to the cart and if the sum of all these variations exceeds the minimum quantity value supplied here, they will be granted the wholesale pricing as per the wholesale price on each variation.', 'woocommerce-wholesale-prices-premium' ); ?>
                    </p>
                </header>

                <?php
                foreach ( $this->_registered_wholesale_roles as $role_key => $role ) {

                    woocommerce_wp_text_input(
                        array(
                            'id'          => $role_key . '_variable_level_wholesale_minimum_order_quantity',
                            'label'       => $role['roleName'],
                            'placeholder' => '',
                            'desc_tip'    => 'true',
                            /* translators: %1$s Wholesale role */
                            'description' => sprintf( __( 'The minimum order quantity for the sum of all variations of this product in the cart for the "%1$s" role.', 'woocommerce-wholesale-prices-premium' ), $role['roleName'] ),
                            'data_type'   => 'decimal',
                        )
                    );

                }
                ?>

            </div>
            <!--.options_group-->

            <?php
            }
        }

        /**
         * Add variable product level wholesale order qty step custom field.
         *
         * @since 1.16.0
         * @access public
         */
        public function add_variable_product_level_wholesale_order_qty_step_custom_field() {
            global $woocommerce, $post;

            /**
             * Allows to hide order quantity base wholesale pricing
             *
             * @param boolean $show Whether to show the pricing fields or not. Default true
             * @return boolean
             */
            if ( ! apply_filters( 'wwpp_product_editor_show_order_quantity_fields', true ) ) {
                return;
            }

            $product = wc_get_product( $post->ID );

            if ( WWP_Helper_Functions::wwp_get_product_type( $product ) === 'variable' ) {
                ?>

                <div class="wholesale-order-quantity-step-options-group options-group options_group"
                    style="border-top: 1px solid #EEEEEE;">

                    <header>
                        <h3 style="font-size: 14px; margin: 10px 12px;">
                            <?php esc_html_e( 'Parent Product Wholesale Order Quantity Step', 'woocommerce-wholesale-prices-premium' ); ?></h3>
                        <p style="margin:0; padding:0 12px; line-height: 16px; font-style: italic; font-size: 13px;">
                            <?php esc_html_e( 'Customers can add multiple variations to the cart and if the sum of all these variations is within the increments (step) multiplier specified, they will be granted the wholesale pricing as per the wholesale price on each variation. Min order qty above must be specified to enable this feature.', 'woocommerce-wholesale-prices-premium' ); ?>
                        </p>
                    </header>

                    <?php
                    foreach ( $this->_registered_wholesale_roles as $role_key => $role ) {
                        woocommerce_wp_text_input(
                            array(
                                'id'          => $role_key . '_variable_level_wholesale_order_quantity_step',
                                'label'       => $role['roleName'],
                                'placeholder' => '',
                                'desc_tip'    => 'true',
                                /* translators: %1$s Wholesale role */
                                'description' => sprintf( __( 'Order quantity step wholesale users are restricted to when purchasing this product for the "%1$s" role.', 'woocommerce-wholesale-prices-premium' ), $role['roleName'] ),
                                'data_type'   => 'decimal',
                            )
                        );
                    }
                    ?>

                </div>
                <!--.options_group-->

            <?php
            }
        }

        /**
         * Save variable product level wholesale minimum order quantity custom field.
         *
         * @since 1.9.0
         * @since 1.13.0 Refactor codebase and move to its own model.
         * @access public
         *
         * @param int $post_id Product id.
         */
        public function save_variable_product_level_wholesale_min_order_qty_custom_field( $post_id ) {
            // phpcs:disable WordPress.Security.NonceVerification.Missing
            foreach ( $this->_registered_wholesale_roles as $roleKey => $role ) {

                if ( isset( $_POST[ $roleKey . '_variable_level_wholesale_minimum_order_quantity' ] ) ) {
                    // Get product object.
                    $product = wc_get_product( $post_id );

                    $variable_level_moq = trim( esc_attr( $_POST[ $roleKey . '_variable_level_wholesale_minimum_order_quantity' ] ) );

                    if ( ! empty( $variable_level_moq ) ) {

                        if ( ! is_numeric( $variable_level_moq ) ) {
                            $variable_level_moq = '';
                        } elseif ( $variable_level_moq < 0 ) {
                            $variable_level_moq = 0;
                        } else {
                            $variable_level_moq = wc_format_decimal( $variable_level_moq );
                        }

                        $variable_level_moq = round( $variable_level_moq );

                    }

                    $variable_level_moq = wc_clean( apply_filters( 'wwpp_before_save_variable_level_wholesale_minimum_order_quantity', $variable_level_moq, $roleKey, $post_id ) );
                    $product->update_meta_data( $roleKey . '_variable_level_wholesale_minimum_order_quantity', $variable_level_moq );

                    // Save the data.
                    $product->save();
                }
            }
            // phpcs:enable WordPress.Security.NonceVerification.Missing
        }

        /**
         * Save variable product level wholesale order quantity step custom field.
         *
         * @since 1.16.0
         * @access public
         *
         * @param int $post_id Product id.
         */
        public function save_variable_product_level_wholesale_order_qty_step_custom_field( $post_id ) {
            // phpcs:disable WordPress.Security.NonceVerification.Missing
            foreach ( $this->_registered_wholesale_roles as $roleKey => $role ) {

                if ( isset( $_POST[ $roleKey . '_variable_level_wholesale_order_quantity_step' ] ) ) {
                    // Get product object.
                    $product = wc_get_product( $post_id );

                    $variable_level_oqs = trim( esc_attr( $_POST[ $roleKey . '_variable_level_wholesale_order_quantity_step' ] ) );

                    if ( ! empty( $variable_level_oqs ) ) {

                        if ( ! is_numeric( $variable_level_oqs ) ) {
                            $variable_level_oqs = '';
                        } elseif ( $variable_level_oqs < 0 ) {
                            $variable_level_oqs = 0;
                        } else {
                            $variable_level_oqs = wc_format_decimal( $variable_level_oqs );
                        }

                        $variable_level_oqs = round( $variable_level_oqs );

                    }

                    $variable_level_oqs = wc_clean( apply_filters( 'wwpp_before_save_variable_level_wholesale_order_quantity_step', $variable_level_oqs, $roleKey, $post_id ) );
                    $product->update_meta_data( $roleKey . '_variable_level_wholesale_order_quantity_step', $variable_level_oqs );

                    // Save the data.
                    $product->save();
                }
            }
            // phpcs:enable WordPress.Security.NonceVerification.Missing
        }

        /*
        |--------------------------------------------------------------------------------------------------------------
        | Variation Wholesale Minimum Order Quantity
        |--------------------------------------------------------------------------------------------------------------
         */

        /**
         * Add minimum order quantity custom field to variable products on product edit screen.
         * Custom fields are added per variation, not to the parent variable product.
         *
         * @since 1.2.0
         * @since 1.13.0 Refactor codebase and move to its own model.
         * @since 1.16.0 Change section description to indicate changes with new quantity step feature.
         *
         * @param int     $loop           Variation loop count.
         * @param array   $variation_data Array of variation data.
         * @param WP_Post $variation      Variaton object.
         */
        public function add_minimum_order_quantity_custom_fields( $loop, $variation_data, $variation ) {
            global $woocommerce, $post;

            /**
             * Allows to hide order quantity base wholesale pricing
             *
             * @param boolean $show Whether to show the pricing fields or not. Default true
             * @return boolean
             */
            if ( ! apply_filters( 'wwpp_product_editor_show_order_quantity_fields', true ) ) {
                return;
            }

            // Get the variable product data manually
            // Don't rely on the variation data woocommerce supplied
            // There is a logic change introduced on 2.3 series where they only send variation data (or variation meta)
            // That is built in to woocommerce, so all custom variation meta added to a variable product don't get passed along.
            $variation_product = wc_get_product( $variation->ID );
            ?>

                <div class="wholesale-minium-order-quantity-options-group options-group options_group"
                    style="border-top: 1px solid #DDDDDD;">

                    <header class="form-row form-row-full">
                        <h4 style="font-size: 14px; margin: 10px 0;">
                            <?php esc_html_e( 'Wholesale Minimum Order Quantity', 'woocommerce-wholesale-prices-premium' ); ?></h4>
                        <p style="margin:0; padding:0; line-height: 16px; font-style: italic; font-size: 13px;">
                            <?php echo wp_kses_post( __( "Minimum number of items to be purchased in order to avail this product's wholesale price.<br/>Only applies to wholesale users.<br/><br/>Setting a step value below for the corresponding wholesale role will prevent the specific wholesale customer from adding to cart quantity of this product lower than the set minimum.", 'woocommerce-wholesale-prices-premium' ) ); ?>
                        </p>
                    </header>

                    <?php
                    foreach ( $this->_registered_wholesale_roles as $role_key => $role ) {
                        $value = $variation_product->get_meta( $role_key . '_wholesale_minimum_order_quantity', true );
                                ?>

                    <div class="form-row form-row-full">
                        <?php
                            WWP_Helper_Functions::wwp_woocommerce_wp_text_input(
                                array(
                                    'id'          => $role_key . '_wholesale_minimum_order_quantity[' . $loop . ']',
                                    'class'       => $role_key . '_wholesale_minimum_order_quantity wholesale_minimum_order_quantity short',
                                    'label'       => $role['roleName'],
                                    'placeholder' => '',
                                    'desc_tip'    => 'true',
                                    /* translators: %1$s Wholesale role */
                                    'description' => sprintf( __( 'Only applies to users with the role of "%1$s"', 'woocommerce-wholesale-prices-premium' ), $role['roleName'] ),
                                    'data_type'   => 'decimal',
                                    'value'       => ! empty( $value ) ? $value : '',
                                )
                            );
                        ?>
                    </div>

                <?php } ?>

                </div>
                <!--.options_group-->

            <?php
        }

        /**
         * Add order quantity step custom field to variable products on product edit screen.
         * Custom fields are added per variation, not to the parent variable product.
         *
         * @since 1.16.0
         *
         * @param int        $loop           Variation loop count.
         * @param array      $variation_data Variation data.
         * @param WC_Product $variation      Variation product object.
         * @access public
         */
        public function add_order_quantity_step_custom_fields( $loop, $variation_data, $variation ) {
            global $woocommerce, $post;

            /**
             * Allows to hide order quantity base wholesale pricing
             *
             * @param boolean $show Whether to show the pricing fields or not. Default true
             * @return boolean
             */
            if ( ! apply_filters( 'wwpp_product_editor_show_order_quantity_fields', true ) ) {
                return;
            }

            // Get the variable product data manually
            // Don't rely on the variation data woocommerce supplied
            // There is a logic change introduced on 2.3 series where they only send variation data (or variation meta)
            // That is built in to woocommerce, so all custom variation meta added to a variable product don't get passed along.
            $variation_product = wc_get_product( $variation->ID );
            ?>

                <div class="wholesale-order-quantity-step-options-group options-group options_group"
                    style="border-top: 1px solid #DDDDDD;">

                    <header class="form-row form-row-full">
                        <h4 style="font-size: 14px; margin: 10px 0;">
                            <?php esc_html_e( 'Wholesale Order Quantity Step', 'woocommerce-wholesale-prices-premium' ); ?></h4>
                        <p style="margin:0; padding:0; line-height: 16px; font-style: italic; font-size: 13px;">
                            <?php echo wp_kses_post( __( 'Order quantity step wholesale users are restricted to when purchasing this product.<br/>Only applies to wholesale users.<br/><br/>Minimum order quantity above for corresponding wholesale role must be set for this feature to take effect.', 'woocommerce-wholesale-prices-premium' ) ); ?>
                        </p>
                    </header>

                    <?php
                    foreach ( $this->_registered_wholesale_roles as $role_key => $role ) {
                        $value = $variation_product->get_meta( $role_key . '_wholesale_order_quantity_step', true );
                                ?>

                    <div class="form-row form-row-full">
                        <?php
                        WWP_Helper_Functions::wwp_woocommerce_wp_text_input(
                            array(
                                'id'          => $role_key . '_wholesale_order_quantity_step[' . $loop . ']',
                                'class'       => $role_key . '_wholesale_order_quantity_step wholesale_order_quantity_step short',
                                'label'       => $role['roleName'],
                                'placeholder' => '',
                                'desc_tip'    => 'true',
                                /* translators: %1$s Wholesale role */
                                'description' => sprintf( __( 'Only applies to users with the role of "%1$s"', 'woocommerce-wholesale-prices-premium' ), $role['roleName'] ),
                                'data_type'   => 'decimal',
                                'value'       => ! empty( $value ) ? $value : '',
                            )
                        );
                        ?>
                    </div>

                    <?php } ?>
            </div>
            <!--.options_group-->

            <?php
        }

        /**
         * Save minimum order quantity custom field value for variations of a variable product on product edit page.
         *
         * @since 1.2.0
         * @since 1.8.0 Add support for custom variations bulk actions.
         * @since 1.13.0 Refactor codebase and move to its own model.
         *
         * @param int        $post_id         Variable product id.
         * @param array      $wholesale_roles Array of all wholesale roles in which this minimum order quantity requirement is to be saved.
         * @param array/null $variation_ids   Variation ids.
         * @param array/null $wholesale_moqs  Wholesale minimum order quantities.
         */
        public function save_minimum_order_quantity_custom_fields( $post_id, $wholesale_roles = array(), $variation_ids = null, $wholesale_moqs = null ) {
            // phpcs:disable WordPress.Security.NonceVerification.Missing
            global $_POST;

            $wholesale_roles = ! empty( $wholesale_roles ) ? $wholesale_roles : $this->_registered_wholesale_roles;

            if ( ( $variation_ids && $wholesale_moqs ) || isset( $_POST['variable_sku'] ) ) {

                $variable_post_id = $variation_ids ? $variation_ids : $_POST['variable_post_id'];
                $max_loop         = max( array_keys( $variable_post_id ) );

                foreach ( $wholesale_roles as $role_key => $role ) {

                    if ( ! $wholesale_moqs && ! isset( $_POST[ $role_key . '_wholesale_minimum_order_quantity' ] ) ) {
                        continue;
                    }

                    $wholesale_moq = $wholesale_moqs ? $wholesale_moqs : $_POST[ $role_key . '_wholesale_minimum_order_quantity' ];

                    for ( $i = 0; $i <= $max_loop; $i++ ) {

                        if ( ! isset( $variable_post_id[ $i ] ) ) {
                            continue;
                        }

                        $variation_id = (int) $variable_post_id[ $i ];

                        // Get variant product object.
                        $variation_product = wc_get_product( $variation_id );

                        if ( isset( $wholesale_moq[ $i ] ) ) {

                            $wholesale_moq[ $i ] = trim( esc_attr( $wholesale_moq[ $i ] ) );

                            if ( ! empty( $wholesale_moq[ $i ] ) ) {

                                if ( ! is_numeric( $wholesale_moq[ $i ] ) ) {
                                    $wholesale_moq[ $i ] = '';
                                } elseif ( $wholesale_moq[ $i ] < 0 ) {
                                    $wholesale_moq[ $i ] = 0;
                                } else {
                                    $wholesale_moq[ $i ] = wc_format_decimal( $wholesale_moq[ $i ] );
                                }

                                $wholesale_moq[ $i ] = round( $wholesale_moq[ $i ] );

                            }

                            $wholesale_moq[ $i ] = wc_clean( apply_filters( 'wwpp_filter_before_save_wholesale_minimum_order_quantity', $wholesale_moq[ $i ], $role_key, $variation_id, 'variation' ) );
                            $variation_product->update_meta_data( $role_key . '_wholesale_minimum_order_quantity', $wholesale_moq[ $i ] );

                            // Save the data.
                            $variation_product->save();
                        }
                    }
                }
            }
            // phpcs:enable WordPress.Security.NonceVerification.Missing
        }

        /**
         * Save order quantity step custom field value for variations of a variable product on product edit page.
         *
         * @since 1.16.0
         * @access public
         *
         * @param int        $post_id         Variable product id.
         * @param array      $wholesale_roles Array of all wholesale roles in which this order quantity step requirement is to be saved.
         * @param array/null $variation_ids   Variation ids.
         * @param array/null $wholesale_oqss  Wholesale order quantity steps.
         */
        public function save_order_quantity_step_custom_fields( $post_id, $wholesale_roles = array(), $variation_ids = null, $wholesale_oqss = null ) {
            // phpcs:disable WordPress.Security.NonceVerification.Missing
            global $_POST;

            $wholesale_roles = ! empty( $wholesale_roles ) ? $wholesale_roles : $this->_registered_wholesale_roles;

            if ( ( $variation_ids && $wholesale_oqss ) || isset( $_POST['variable_sku'] ) ) {

                $variable_post_id = $variation_ids ? $variation_ids : $_POST['variable_post_id'];
                $max_loop         = max( array_keys( $variable_post_id ) );

                foreach ( $wholesale_roles as $role_key => $role ) {
                    if ( ! $wholesale_oqss && ! isset( $_POST[ $role_key . '_wholesale_order_quantity_step' ] ) ) {
                        continue;
                    }

                    $wholesale_oqs = $wholesale_oqss ? $wholesale_oqss : $_POST[ $role_key . '_wholesale_order_quantity_step' ];

                    for ( $i = 0; $i <= $max_loop; $i++ ) {

                        if ( ! isset( $variable_post_id[ $i ] ) ) {
                            continue;
                        }

                        $variation_id = (int) $variable_post_id[ $i ];

                        // Get variant product object.
                        $variation_product = wc_get_product( $variation_id );

                        if ( isset( $wholesale_oqs[ $i ] ) ) {

                            $wholesale_oqs[ $i ] = trim( esc_attr( $wholesale_oqs[ $i ] ) );

                            if ( ! empty( $wholesale_oqs[ $i ] ) ) {

                                if ( ! is_numeric( $wholesale_oqs[ $i ] ) ) {
                                    $wholesale_oqs[ $i ] = '';
                                } elseif ( $wholesale_oqs[ $i ] < 0 ) {
                                    $wholesale_oqs[ $i ] = 0;
                                } else {
                                    $wholesale_oqs[ $i ] = wc_format_decimal( $wholesale_oqs[ $i ] );
                                }

                                $wholesale_oqs[ $i ] = round( $wholesale_oqs[ $i ] );

                            }

                            $wholesale_oqs[ $i ] = wc_clean( apply_filters( 'wwpp_filter_before_save_wholesale_order_quantity_step', $wholesale_oqs[ $i ], $role_key, $variation_id, 'variation' ) );
                            $variation_product->update_meta_data( $role_key . '_wholesale_order_quantity_step', $wholesale_oqs[ $i ] );

                            // Save the data.
                            $variation_product->save();
                        }
                    }
                }
            }
            // phpcs:enable WordPress.Security.NonceVerification.Missing
        }

        /*
        |--------------------------------------------------------------------------------------------------------------
        | Variation Custom Bulk Actions
        |--------------------------------------------------------------------------------------------------------------
         */

        /**
         * Add variation custom bulk action options.
         *
         * @since 1.0.0
         * @since 1.13.0 Refactor codebase and move to its own model.
         * @since 1.16.0 Add support for wholesale order quantity step feature.
         * @since 1.30.1 Add support for wholesale sale prices feature.
         * @access public
         */
        public function add_variation_custom_bulk_action_options() {
            foreach ( $this->_registered_wholesale_roles as $role_key => $role ) {
            ?>

                <option value="<?php echo esc_attr( $role_key ); ?>_wholesale_sale_price">
                    <?php
                        /* translators: %1$s Wholesale role */
                        printf( esc_html__( 'Set wholesale sale prices (%1$s)', 'woocommerce-wholesale-prices-premium' ), esc_html( $role['roleName'] ) );
                    ?>
                </option>

            <?php
            }

            foreach ( $this->_registered_wholesale_roles as $role_key => $role ) {
            ?>

                <option value="<?php echo esc_attr( $role_key ); ?>_wholesale_min_order_qty">
                    <?php
                        /* translators: %1$s Wholesale role */
                        printf( esc_html__( 'Set minimum order quantity (%1$s)', 'woocommerce-wholesale-prices-premium' ), esc_html( $role['roleName'] ) );
                    ?>
                </option>

            <?php
            }

            foreach ( $this->_registered_wholesale_roles as $role_key => $role ) {
            ?>

                <option value="<?php echo esc_attr( $role_key ); ?>_wholesale_order_qty_step">
                    <?php
                        /* translators: %1$s Wholesale role */
                        printf( esc_html__( 'Set order quantity step (%1$s)', 'woocommerce-wholesale-prices-premium' ), esc_html( $role['roleName'] ) );
                    ?>
                </option>

            <?php
            }
        }

        /**
         * Execute variation custom bulk actions.
         *
         * @since 1.0.0
         * @since 1.13.0 Refactor codebase and move to its own model.
         * @since 1.16.0 Add support for wholesale order quantity step feature.
         * @since 1.30.1 Add support for wholesale sale prices feature.
         * @access public
         *
         * @param string $bulk_action Bulk action.
         * @param array  $data        Bulk action data.
         * @param int    $product_id  Product id.
         * @param array  $variations  Array of variation ids.
         */
        public function execute_variations_custom_bulk_actions( $bulk_action, $data, $product_id, $variations ) {
            if ( strpos( $bulk_action, '_wholesale_sale_price' ) !== false ) {

                if ( is_array( $variations ) && isset( $data['value'] ) ) {
                    $wholesale_role     = str_replace( '_wholesale_sale_price', '', $bulk_action );
                    $wholesale_role_arr = array( $wholesale_role => $this->_registered_wholesale_roles[ $wholesale_role ] );

                    foreach ( $variations as $variation_id ) {
                        // Get variation product object.
                        $variation_product = wc_get_product( $variation_id );

                        $wholesale_percentage_discount = $variation_product->get_meta( $wholesale_role . '_wholesale_percentage_discount', true );

                        // If '_wholesale_percentage_discount' meta is empty, it means the wholesale discount type is fixed.
                        if ( '' === $wholesale_percentage_discount ) {
                            if ( '' !== $data['value'] ) {
                                $raw_wholesale_price = $variation_product->get_meta( $wholesale_role . '_wholesale_price', true );

                                // If the wholesale price is lower than inputed value, then do not save the value.
                                if ( '' === $wholesale_percentage_discount && '' !== $raw_wholesale_price && ( (float) $raw_wholesale_price > (float) $data['value'] ) ) {
                                    $variation_product->update_meta_data( $wholesale_role . '_wholesale_sale_price', $data['value'] );
                                }
                            } else {
                                $variation_product->delete_meta_data( $wholesale_role . '_wholesale_sale_price' );
                            }

                            // Save the data.
                            $variation_product->save();
                        }
                    }
                }
            }

            if ( strpos( $bulk_action, '_wholesale_min_order_qty' ) !== false ) {

                if ( is_array( $variations ) && isset( $data['value'] ) ) {

                    $wholesale_role     = str_replace( '_wholesale_min_order_qty', '', $bulk_action );
                    $wholesale_role_arr = array( $wholesale_role => $this->_registered_wholesale_roles[ $wholesale_role ] );

                    $wholesale_moqs = array();

                    foreach ( $variations as $variation_id ) {
                        $wholesale_moqs[] = $data['value'];
                    }

                    $this->save_minimum_order_quantity_custom_fields( $product_id, $wholesale_role_arr, $variations, $wholesale_moqs );

                }
            }

            if ( strpos( $bulk_action, '_wholesale_order_qty_step' ) !== false ) {

                if ( is_array( $variations ) && isset( $data['value'] ) ) {

                    $wholesale_role     = str_replace( '_wholesale_order_qty_step', '', $bulk_action );
                    $wholesale_role_arr = array( $wholesale_role => $this->_registered_wholesale_roles[ $wholesale_role ] );

                    $wholesale_oqss = array();

                    foreach ( $variations as $variation_id ) {
                        $wholesale_oqss[] = $data['value'];
                    }

                    $this->save_order_quantity_step_custom_fields( $product_id, $wholesale_role_arr, $variations, $wholesale_oqss );

                }
            }
        }

        /**
         * Filter variable variation on variable single product page.
         *
         * @since 1.24.7
         * @access public
         */
        public function filter_variation_visibility_single_product_page() {
            add_filter( 'woocommerce_variation_is_visible', array( $this, 'filter_variation_visibility' ), 10, 4 );
            add_filter( 'woocommerce_variation_is_purchasable', array( $this, 'filter_variation_purchasability' ), 10, 2 );
        }

        /**
         * Filter bundle variable variation on single bundle product page.
         *
         * @since 1.24.7
         * @access public
         *
         * @param WC_Product $product Product Object.
         */
        public function filter_bundle_variation_visibility_single_product_page( $product ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
            remove_filter( 'woocommerce_variation_is_visible', array( $this, 'filter_variation_visibility' ), 10, 4 );
            remove_filter( 'woocommerce_variation_is_purchasable', array( $this, 'filter_variation_purchasability' ), 10, 2 );
        }

        /**
         * Save variable product level wholesale order quantity step custom field.
         *
         * @since 1.16.0
         * @access public
         *
         * @param int $post_id Product id.
         */
        public function save_have_wholesale_price_set_by_product_category( $post_id ) {
            // Get the product object.
            $product = wc_get_product( $post_id );

            $terms = get_the_terms( $post_id, 'product_cat' );
            if ( ! is_array( $terms ) ) {
                $terms = array();
            }

            foreach ( $this->_registered_wholesale_roles as $role_key => $role ) {

                foreach ( $terms as $term ) {

                    $category_wholesale_prices = get_option( 'taxonomy_' . $term->term_id );

                    if ( is_array( $category_wholesale_prices ) && array_key_exists( $role_key . '_wholesale_discount', $category_wholesale_prices ) ) {

                        $curr_discount = $category_wholesale_prices[ $role_key . '_wholesale_discount' ];

                        if ( ! empty( $curr_discount ) ) {

                            $product->update_meta_data( $role_key . '_have_wholesale_price', 'yes' );

                            // Add additional meta to indicate that have wholesale price meta was set by the category.
                            $product->update_meta_data( $role_key . '_have_wholesale_price_set_by_product_cat', 'yes' );

                        }
                    }
                }
            }

            // Save the data.
            $product->save();
        }

        /**
         * Add wholesale sale price dummy field on the variable product edit page.
         *
         * @since 1.30.1
         *
         * @param array   $loop                          The position of the loop.
         * @param array   $variation_data                Array of variation data.
         * @param WP_Post $variation                     Variation object.
         * @param array   $role                          Wholesale role array.
         * @param string  $role_key                      Wholesale role key.
         * @param string  $currency_symbol               Currency symbol.
         * @param int     $wholesale_price               The wholesale price.
         * @param string  $discount_type                 The discount type (fixed | percentage).
         * @param int     $wholesale_percentage_discount The Wholesale percentage discount value.
         */
        public function add_wholesale_sale_price_fields( $loop, $variation_data, $variation, $role, $role_key, $currency_symbol, $wholesale_price, $discount_type, $wholesale_percentage_discount ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
            global $WOOCS, $woocommerce_wpml;

            // Get the product object.
            $product = wc_get_product( $variation->ID );

            if ( empty( $WOOCS ) && empty( $woocommerce_wpml ) ) {
                WWP_Helper_Functions::wwp_woocommerce_wp_text_input(
                    array(
                        'id'                => "{$role_key}_wholesale_sale_discount[{$loop}]",
                        'class'             => $role_key . '_wholesale_sale_discount wholesale_sale_discount',
                        'label'             => __( 'Sale Discount (%)', 'woocommerce-wholesale-prices-premium' ),
                        'placeholder'       => '',
                        'value'             => $product->get_meta( $role_key . '_wholesale_sale_discount', true ),
                        'desc_tip'          => true,
                        'description'       => __( 'The percentage amount discounted from the wholesale price', 'woocommerce-wholesale-prices-premium' ),
                        'data_type'         => 'price',
                        'custom_attributes' => array(
                            'data-wholesale_role' => $role_key,
                            'data-loop_id'        => $loop,
                        ),
                    )
                );
            }

            woocommerce_wp_text_input(
                array(
                    'id'          => $role_key . '_wholesale_sale_price[' . $loop . ']',
                    'class'       => $role_key . '_wholesale_sale_price wholesale_sale_price',
                    /* translators: %s: currency symbol */
                    'label'       => sprintf( __( 'Wholesale Sale Price (%1$s)', 'woocommerce-wholesale-prices-premium' ), $currency_symbol ) . ' <a href="#" class="wholesale_sale_schedule">' . esc_html__( 'Schedule', 'woocommerce-wholesale-prices-premium' ) . '</a><a href="#" class="cancel_wholesale_sale_schedule hidden">' . esc_html__( 'Cancel schedule', 'woocommerce-wholesale-prices-premium' ) . '</a>',
                    'placeholder' => '',
                    'value'       => $product->get_meta( $role_key . '_wholesale_sale_price', true ),
                    'data_type'   => 'price',
                )
            );

            $sale_price_dates_from_timestamp = $product->get_meta( $role_key . '_wholesale_sale_price_dates_from', true ) ? $product->get_meta( $role_key . '_wholesale_sale_price_dates_from', true ) : false;
            $sale_price_dates_to_timestamp   = $product->get_meta( $role_key . '_wholesale_sale_price_dates_to', true ) ? $product->get_meta( $role_key . '_wholesale_sale_price_dates_to', true ) : false;

            $sale_price_dates_from = $sale_price_dates_from_timestamp ? date_i18n( 'Y-m-d', $sale_price_dates_from_timestamp ) : '';
            $sale_price_dates_to   = $sale_price_dates_to_timestamp ? date_i18n( 'Y-m-d', $sale_price_dates_to_timestamp ) : '';

            echo '<div class="form-field ' . esc_attr( $role_key ) . '_wholesale_sale_price_dates_fields wholesale_sale_price_dates_fields wholesale_sale_price_dates_fields__variations hidden">
					<p class="form-row form-row-first">
						<label>' . esc_html__( 'Sale start date', 'woocommerce-wholesale-prices-premium' ) . '</label>
						<input type="text" class="wholesale_sale_price_dates_from" name="' . esc_attr( $role_key ) . '_wholesale_sale_price_dates_from[' . esc_attr( $loop ) . ']" value="' . esc_attr( $sale_price_dates_from ) . '" placeholder="' . esc_attr_x( 'From&hellip;', 'placeholder', 'woocommerce-wholesale-prices-premium' ) . ' YYYY-MM-DD" maxlength="10" pattern="' . esc_attr( apply_filters( 'woocommerce_date_input_html_pattern', '[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])' ) ) . '" />
					</p>
					<p class="form-row form-row-last">
						<label>' . esc_html__( 'Sale end date', 'woocommerce-wholesale-prices-premium' ) . '</label>
						<input type="text" class="wholesale_sale_price_dates_to" name="' . esc_attr( $role_key ) . '_wholesale_sale_price_dates_to[' . esc_attr( $loop ) . ']" value="' . esc_attr( $sale_price_dates_to ) . '" placeholder="' . esc_attr_x( 'To&hellip;', 'placeholder', 'woocommerce-wholesale-prices-premium' ) . '  YYYY-MM-DD" maxlength="10" pattern="' . esc_attr( apply_filters( 'woocommerce_date_input_html_pattern', '[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])' ) ) . '" />
					</p>
				</div>';
        }

        /**
         * Aelia Currency Switcher - Add wholesale sale price field for each available currency on the simple product edit page.
         *
         * @since 1.30.1
         *
         * @param array   $loop                          The position of the loop.
         * @param array   $variation_data                Array of variation data.
         * @param WP_Post $variation                     Variation object.
         * @param array   $role                          Wholesale role array.
         * @param string  $role_key                      Wholesale role key.
         * @param string  $currency_symbol               Currency symbol ( $ | € ).
         * @param string  $currency_code                 Currency code ( AUD | EUR | USD ).
         * @param int     $wholesale_price               The wholesale price.
         */
        public function add_wacs_wholesale_sale_price_fields( $loop, $variation_data, $variation, $role, $role_key, $currency_symbol, $currency_code, $wholesale_price ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter

            // Get the product object.
            $product       = wc_get_product( $variation->ID );
            $base_currency = WWP_ACS_Integration_Helper::get_product_base_currency( $variation->ID );

            if ( $currency_code === $base_currency ) {
                $wholesale_sale_price            = $product->get_meta( $role_key . '_wholesale_sale_price', true ); // Get base currency wholesale sale price.
                $sale_price_dates_from_timestamp = $product->get_meta( $role_key . '_wholesale_sale_price_dates_from', true ) ? $product->get_meta( $role_key . '_wholesale_sale_price_dates_from', true ) : false;
                $sale_price_dates_to_timestamp   = $product->get_meta( $role_key . '_wholesale_sale_price_dates_to', true ) ? $product->get_meta( $role_key . '_wholesale_sale_price_dates_to', true ) : false;
                $field_id                        = esc_attr( $role_key );
            } else {
                $wholesale_sale_price            = $product->get_meta( $role_key . '_' . $currency_code . '_wholesale_sale_price', true ); // Get specific currency wholesale sale price.
                $sale_price_dates_from_timestamp = $product->get_meta( $role_key . '_' . $currency_code . '_wholesale_sale_price_dates_from', true ) ? $product->get_meta( $role_key . '_' . $currency_code . '_wholesale_sale_price_dates_from', true ) : false;
                $sale_price_dates_to_timestamp   = $product->get_meta( $role_key . '_' . $currency_code . '_wholesale_sale_price_dates_to', true ) ? $product->get_meta( $role_key . '_' . $currency_code . '_wholesale_sale_price_dates_to', true ) : false;
                $field_id                        = esc_attr( $role_key ) . '_' . $currency_code;
            }

            woocommerce_wp_text_input(
                array(
                    'id'          => $field_id . '_wholesale_sale_price[' . $loop . ']',
                    'class'       => $field_id . '_wholesale_sale_price wholesale_sale_price short ',
                    /* translators: %s: currency symbol */
                    'label'       => sprintf( __( 'Wholesale Sale Price (%1$s)', 'woocommerce-wholesale-prices-premium' ), $currency_symbol ) . ' <a href="#" class="wholesale_sale_schedule">' . esc_html__( 'Schedule', 'woocommerce-wholesale-prices-premium' ) . '</a><a href="#" class="cancel_wholesale_sale_schedule hidden">' . esc_html__( 'Cancel schedule', 'woocommerce-wholesale-prices-premium' ) . '</a>',
                    'placeholder' => __( 'Auto', 'woocommerce-wholesale-prices-premium' ),
                    'data_type'   => 'price',
                    'value'       => $wholesale_sale_price,
                )
            );

            $sale_price_dates_from = $sale_price_dates_from_timestamp ? date_i18n( 'Y-m-d', $sale_price_dates_from_timestamp ) : '';
            $sale_price_dates_to   = $sale_price_dates_to_timestamp ? date_i18n( 'Y-m-d', $sale_price_dates_to_timestamp ) : '';

            echo '<div class="form-field ' . esc_attr( $field_id ) . '_wholesale_sale_price_dates_fields wholesale_sale_price_dates_fields wholesale_sale_price_dates_fields__variations hidden">
                    <p class="form-row form-row-first">
                        <label>' . esc_html__( 'Sale start date', 'woocommerce-wholesale-prices-premium' ) . '</label>
                        <input type="text" class="wholesale_sale_price_dates_from" name="' . esc_attr( $field_id ) . '_wholesale_sale_price_dates_from[' . esc_attr( $loop ) . ']" value="' . esc_attr( $sale_price_dates_from ) . '" placeholder="' . esc_attr_x( 'From&hellip;', 'placeholder', 'woocommerce-wholesale-prices-premium' ) . ' YYYY-MM-DD" maxlength="10" pattern="' . esc_attr( apply_filters( 'woocommerce_date_input_html_pattern', '[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])' ) ) . '" />
                    </p>
                    <p class="form-row form-row-last">
                        <label>' . esc_html__( 'Sale end date', 'woocommerce-wholesale-prices-premium' ) . '</label>
                        <input type="text" class="wholesale_sale_price_dates_to" name="' . esc_attr( $field_id ) . '_wholesale_sale_price_dates_to[' . esc_attr( $loop ) . ']" value="' . esc_attr( $sale_price_dates_to ) . '" placeholder="' . esc_attr_x( 'To&hellip;', 'placeholder', 'woocommerce-wholesale-prices-premium' ) . '  YYYY-MM-DD" maxlength="10" pattern="' . esc_attr( apply_filters( 'woocommerce_date_input_html_pattern', '[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])' ) ) . '" />
                    </p>
                </div>';
        }

        /**
         * Save wholesale sale prices custom field value for simple products on product edit page.
         *
         * @since 1.30.1
         *
         * @param int $variation_id The product id.
         * @param int $i            Loop position.
         */
        public function save_wholesale_sale_price_fields( $variation_id, $i ) {

            $registered_wholesale_roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();
            $decimal_sep                = wc_get_price_decimal_separator();
            $thousand_sep               = wc_get_price_thousand_separator();

            if ( WWP_ACS_Integration_Helper::aelia_currency_switcher_active() ) {
                $wacs_enabled_currencies = WWP_ACS_Integration_Helper::enabled_currencies(); // Get all active currencies.
                $base_currency           = WWP_ACS_Integration_Helper::get_product_base_currency( $variation_id ); // Get base currency. Product base currency ( if present ) or shop base currency.

                foreach ( $registered_wholesale_roles as $role_key => $role ) {

                    foreach ( $wacs_enabled_currencies as $currency_code ) {

                        if ( $currency_code === $base_currency ) {

                            // Base currency.
                            $meta_field_key   = $role_key;
                            $is_base_currency = true;

                        } else {

                            $meta_field_key   = $role_key . '_' . $currency_code;
                            $is_base_currency = false;

                        }

                        $this->_save_wholesale_sale_price_fields( $variation_id, $i, $role_key, $meta_field_key, $thousand_sep, $decimal_sep, true, $is_base_currency, $currency_code );

                    }
                }
            } else {
                foreach ( $registered_wholesale_roles as $role_key => $role ) {
                    $this->_save_wholesale_sale_price_fields( $variation_id, $i, $role_key, $role_key, $thousand_sep, $decimal_sep );
                }
            }
        }

        /**
         * Save product wholesale sale price.
         *
         * @since 1.30.1
         *
         * @param int     $variation_id                   Product variation id.
         * @param int     $i                              Loop.
         * @param string  $role_key                       Wholesale role key.
         * @param string  $meta_field_key                 Meta field key.
         * @param string  $thousand_sep                   Thousand separator.
         * @param string  $decimal_sep                    Decimal separator.
         * @param boolean $aelia_currency_switcher_active Flag that detemines if aelia currency switcher is active or not.
         * @param boolean $is_base_currency               Flag that determines if this is a base currency.
         * @param mixed   $currency_code                  String of current currency code or null.
         */
        private function _save_wholesale_sale_price_fields( $variation_id, $i, $role_key, $meta_field_key, $thousand_sep, $decimal_sep, $aelia_currency_switcher_active = false, $is_base_currency = false, $currency_code = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter

            // Get variation product object.
            $variation_product = wc_get_product( $variation_id );

            // phpcs:disable WordPress.Security.NonceVerification.Missing
            $wholesale_discount_type = isset( $_POST[ "{$meta_field_key}_wholesale_discount_type" ][ $i ] ) ? trim( esc_attr( $_POST[ "{$meta_field_key}_wholesale_discount_type" ][ $i ] ) ) : '';

            if ( isset( $_POST[ "{$meta_field_key}_wholesale_sale_price" ][ $i ] ) && '' !== $_POST[ $meta_field_key . '_wholesale_sale_price' ][ $i ] ) {
                $wholesale_sale_price = trim( esc_attr( $_POST[ "{$meta_field_key}_wholesale_sale_price" ][ $i ] ) );

                if ( $thousand_sep ) {
                    $wholesale_sale_price = str_replace( $thousand_sep, '', $wholesale_sale_price );
                }

                if ( $decimal_sep ) {
                    $wholesale_sale_price = str_replace( $decimal_sep, '.', $wholesale_sale_price );
                }

                if ( ! empty( $wholesale_sale_price ) && is_numeric( $wholesale_sale_price ) ) {

                    if ( $wholesale_sale_price < 0 ) {
                        $wholesale_sale_price = 0;
                    } else {
                        $wholesale_sale_price = wc_format_decimal( $wholesale_sale_price );
                    }

                    $wholesale_sale_price = wc_clean( apply_filters( 'wwpp_before_save_simple_product_wholesale_sale_price', $wholesale_sale_price, $meta_field_key, $variation_id ) );
                    $variation_product->update_meta_data( $meta_field_key . '_wholesale_sale_price', $wholesale_sale_price );
                }
            } else {
                $variation_product->delete_meta_data( $meta_field_key . '_wholesale_sale_price' );
            }

            if ( 'percentage' === $wholesale_discount_type ) {
                $wholesale_sale_discount = trim( esc_attr( $_POST[ "{$meta_field_key}_wholesale_sale_discount" ][ $i ] ) );

                if ( $decimal_sep ) {
                    $wholesale_sale_discount = str_replace( $decimal_sep, '.', $wholesale_sale_discount );
                }

                if ( ! empty( $wholesale_sale_discount ) && is_numeric( $wholesale_sale_discount ) ) {
                    if ( $wholesale_sale_discount < 0 ) {
                        $wholesale_sale_discount = 0;
                    } else {
                        $wholesale_sale_discount = wc_format_decimal( $wholesale_sale_discount );
                    }
                }

                $variation_product->update_meta_data( $meta_field_key . '_wholesale_sale_discount', $wholesale_sale_discount );
            } else {
                $variation_product->delete_meta_data( $meta_field_key . '_wholesale_sale_discount' );
            }

            // Handle dates.
            $date_on_sale_from = '';
            $date_on_sale_to   = '';

            // Force date from to beginning of day.
            if ( isset( $_POST[ "{$meta_field_key}_wholesale_sale_price_dates_from" ][ $i ] ) ) {
                $date_on_sale_from = wc_clean( wp_unslash( $_POST[ "{$meta_field_key}_wholesale_sale_price_dates_from" ][ $i ] ) );

                if ( ! empty( $date_on_sale_from ) && is_string( $date_on_sale_from ) ) {
                    $date_on_sale_from = strtotime( date( 'Y-m-d 00:00:00', strtotime( $date_on_sale_from ) ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
                }
            }

            $variation_product->update_meta_data( $meta_field_key . '_wholesale_sale_price_dates_from', $date_on_sale_from );

            // Force date to to the end of the day.
            if ( isset( $_POST[ "{$meta_field_key}_wholesale_sale_price_dates_to" ][ $i ] ) ) {
                $date_on_sale_to = wc_clean( wp_unslash( $_POST[ "{$meta_field_key}_wholesale_sale_price_dates_to" ][ $i ] ) );

                if ( ! empty( $date_on_sale_to ) && is_string( $date_on_sale_to ) ) {
                    $date_on_sale_to = strtotime( date( 'Y-m-d 23:59:59', strtotime( $date_on_sale_to ) ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
                }
            }

            $variation_product->update_meta_data( $meta_field_key . '_wholesale_sale_price_dates_to', $date_on_sale_to );

            WWPP_Admin_Custom_Fields_Product::maybe_set_have_wholesale_sale_price_meta( $variation_id, $this->_registered_wholesale_roles );
            // phpcs:enable WordPress.Security.NonceVerification.Missing

            // Check if the product type is subscription.
            if ( WWP_Helper_Functions::is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' ) ) {
                $product_type = isset( $_POST['product-type'] ) ? $_POST['product-type'] : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
                if ( 'variable-subscription' === $product_type ) {
                    $singup_fee_key               = $meta_field_key . '_wholesale_signup_fee';
                    $singup_fee_is_percentage_key = $meta_field_key . '_wholesale_signup_fee_discount';

                    // Meta signup fee.
                    if ( isset( $_POST[ $singup_fee_key ][ $i ] ) ) { // phpcs:ignore
                        $variation_product->update_meta_data( $singup_fee_key, sanitize_text_field( $_POST[ $singup_fee_key ][ $i ] ) ); // phpcs:ignore
                    }

                    // Meta signup fee is percentage.
                    if ( 'percentage' === $wholesale_discount_type && isset( $_POST[ $singup_fee_is_percentage_key ][ $i ] ) ) { // phpcs:ignore
                        $variation_product->update_meta_data( $singup_fee_is_percentage_key, sanitize_text_field( $_POST[ $singup_fee_is_percentage_key ][ $i ] ) ); // phpcs:ignore
                    } else {
                        $variation_product->delete_meta_data( $singup_fee_is_percentage_key );
                    }
                }
            }

            // Save the data.
            $variation_product->save();
        }

        /**
         * Add meta on product creation and update for variable product.
         * '{$role_key}_variations_have_wholesale_sale_price': determine if the variations of the variable product has wholesale sale price,
         * '{$role_key}_variations_have_on_sale_wholesale_sale_price' : determine if the variations of the variable product has active wholesale sale price by respecting the wholesale sale schedule,
         * The purpose of this metas is to increase performance significantly, because we don't need to check the on going sale everytime the product load on the front-end.
         *
         * @since 1.30.1.1
         * @access public
         *
         * @param int $product_id The product variable ID.
         */
        public function maybe_set_variations_have_wholesale_sale_price_meta( $product_id ) {
            $product    = wc_get_product( $product_id );
            $variations = $product->get_children();

            foreach ( $this->_registered_wholesale_roles as $role_key => $role ) {

                $product->delete_meta_data( "{$role_key}_variations_have_wholesale_sale_price" );
                $product->delete_meta_data( "{$role_key}_variations_have_on_sale_wholesale_sale_price" );

                foreach ( $variations as $variation_id ) {
                    $variantion_product = wc_get_product( $variation_id );

                    if ( '' !== $variantion_product->get_meta( "{$role_key}_wholesale_sale_price", true ) ) {
                        $is_on_wholesale_sale = true;
                        $date_on_sale_from    = $variantion_product->get_meta( "{$role_key}_wholesale_sale_price_dates_from", true );
                        $date_on_sale_to      = $variantion_product->get_meta( "{$role_key}_wholesale_sale_price_dates_to", true );

                        $product->update_meta_data( "{$role_key}_variations_have_wholesale_sale_price", 'yes' );

                        if ( $date_on_sale_from && $date_on_sale_from > time() ) {
                            $is_on_wholesale_sale = false;
                        }

                        if ( $date_on_sale_to && $date_on_sale_to < time() ) {
                            $is_on_wholesale_sale = false;
                        }

                        if ( true === $is_on_wholesale_sale ) {
                            $product->update_meta_data( "{$role_key}_variations_have_on_sale_wholesale_sale_price", 'yes' );
                            break;
                        }
                    }
                }
            }

            // Save the data.
            $product->save();
        }

        /**
         * Save have wholesale price meta for variable product.
         *
         * @since 2.0.0
         * @access public
         *
         * @param int $post_id The product variable ID.
         *
         * @return void
         */
        public function save_have_wholesale_price_for_variable( $post_id ) {
            $wholesale_roles       = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();
            $main_variable_product = wc_get_product( $post_id );

            $main_variable_children = $main_variable_product->get_children();
            if ( ! empty( $main_variable_children ) ) {
                foreach ( $wholesale_roles as $role_key => $role ) {
                    $wholesale_child_prices = array();
                    foreach ( $main_variable_children as $main_variable_child ) {
                        $child_product         = wc_get_product( $main_variable_child );
                        $child_wholesale_price = $child_product->get_meta( $role_key . '_wholesale_price' );

                        if ( ! empty( $child_wholesale_price ) ) {
                            $wholesale_child_prices[ $role_key ] = $child_wholesale_price;
                        }
                    }

                    if ( ! empty( $wholesale_child_prices ) && isset( $wholesale_child_prices[ $role_key ] ) ) {
                        $main_variable_product->update_meta_data( $role_key . '_have_wholesale_price', 'yes' );
                    } else {
                        $main_variable_product->update_meta_data( $role_key . '_have_wholesale_price', 'no' );
                    }
                }
            }

            // Save the data.
            $main_variable_product->save();
        }

        /**
         * Execute model.
         *
         * @since 1.13.0
         * @access public
         */
        public function run() {
            $wwp_plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/woocommerce-wholesale-prices/woocommerce-wholesale-prices.bootstrap.php', false, false );

            // Variable Wholesale Minimum Order Quantity.
            add_action( 'woocommerce_product_options_sku', array( $this, 'add_variable_product_level_wholesale_min_order_qty_custom_field' ), 10 );
            add_action( 'woocommerce_product_options_sku', array( $this, 'add_variable_product_level_wholesale_order_qty_step_custom_field' ), 10 );
            add_action( 'woocommerce_process_product_meta_variable', array( $this, 'save_variable_product_level_wholesale_min_order_qty_custom_field' ), 20, 1 );
            add_action( 'woocommerce_process_product_meta_variable', array( $this, 'save_variable_product_level_wholesale_order_qty_step_custom_field' ), 20, 1 );

            // Variation Wholesale Minimum Order Quantity.
            add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'add_minimum_order_quantity_custom_fields' ), 20, 3 );
            add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'add_order_quantity_step_custom_fields' ), 20, 3 );
            add_action( 'woocommerce_ajax_save_product_variations', array( $this, 'save_minimum_order_quantity_custom_fields' ), 20, 1 );
            add_action( 'woocommerce_process_product_meta_variable', array( $this, 'save_minimum_order_quantity_custom_fields' ), 20, 1 );
            add_action( 'woocommerce_ajax_save_product_variations', array( $this, 'save_order_quantity_step_custom_fields' ), 20, 1 );
            add_action( 'woocommerce_process_product_meta_variable', array( $this, 'save_order_quantity_step_custom_fields' ), 20, 1 );

            // Wholesale Exclusive Variation.
            add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'add_variation_wholesale_role_visibility_filter_field' ), 20, 3 );
            add_action( 'woocommerce_process_product_meta_variable', array( $this, 'save_variation_wholesale_role_visibility_filter_field' ), 20, 1 );
            add_action( 'woocommerce_ajax_save_product_variations', array( $this, 'save_variation_wholesale_role_visibility_filter_field' ), 20, 1 );

            // Variation visibility.
            add_filter( 'woocommerce_hide_invisible_variations', '__return_true' );

            add_filter( 'woocommerce_product_default_attributes', array( $this, 'filter_variable_product_default_attributes' ), 10, 2 );
            add_action( 'woocommerce_before_single_product', array( $this, 'filter_variation_visibility_single_product_page' ) );
            add_action( 'woocommerce_before_bundled_items', array( $this, 'filter_bundle_variation_visibility_single_product_page' ), 10, 1 );

            // Variation Custom Bulk Actions.
            add_action( 'wwp_custom_variation_bulk_action_options', array( $this, 'add_variation_custom_bulk_action_options' ), 10 );
            add_action( 'woocommerce_bulk_edit_variations', array( $this, 'execute_variations_custom_bulk_actions' ), 10, 4 );

            // Have wholesale price set by product category.
            add_action( 'woocommerce_ajax_save_product_variations', array( $this, 'save_have_wholesale_price_set_by_product_category' ), 100, 1 );
            add_action( 'woocommerce_process_product_meta_variable', array( $this, 'save_have_wholesale_price_set_by_product_category' ), 100, 1 );

            // Have wholesale price set.
            add_action( 'woocommerce_ajax_save_product_variations', array( $this, 'save_have_wholesale_price_for_variable' ), 11, 1 );
            add_action( 'woocommerce_process_product_meta_variable', array( $this, 'save_have_wholesale_price_for_variable' ), 11, 1 );

            if ( version_compare( $wwp_plugin_data['Version'], '2.1.6', '>=' ) ) {
                // Wholesale sale price fields.
                add_action( 'wwp_after_variable_wholesale_price_field', array( $this, 'add_wholesale_sale_price_fields' ), 10, 9 );
                add_action( 'woocommerce_save_product_variation', array( $this, 'save_wholesale_sale_price_fields' ), 10, 2 );
                add_action( 'woocommerce_ajax_save_product_variations', array( $this, 'maybe_set_variations_have_wholesale_sale_price_meta' ), 10, 1 );

                add_action( 'wwp_after_wacs_variable_wholesale_price_field', array( $this, 'add_wacs_wholesale_sale_price_fields' ), 10, 8 );
            }
        }
}

}
