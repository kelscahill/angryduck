<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWPP_Admin_Custom_Fields_Simple_Product' ) ) {

    /**
     * Model that houses logic  admin custom fields for simple products.
     *
     * @since 1.13.0
     */
    class WWPP_Admin_Custom_Fields_Simple_Product {

        /**
         * Class Properties.
         */

        /**
         * Property that holds the single main instance of WWPP_Admin_Custom_Fields_Simple_Product.
         *
         * @since 1.13.0
         * @access private
         * @var WWPP_Admin_Custom_Fields_Simple_Product
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
         * Class Methods.
         */

        /**
         * WWPP_Admin_Custom_Fields_Simple_Product constructor.
         *
         * @since 1.13.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Admin_Custom_Fields_Simple_Product model.
         */
        public function __construct( $dependencies ) {

            $this->_wwpp_wholesale_roles = $dependencies['WWPP_Wholesale_Roles'];
        }

        /**
         * Ensure that only one instance of WWPP_Admin_Custom_Fields_Simple_Product is loaded or can be loaded (Singleton Pattern).
         *
         * @since 1.13.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Admin_Custom_Fields_Simple_Product model.
         * @return WWPP_Admin_Custom_Fields_Simple_Product
         */
        public static function instance( $dependencies ) {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }




        /*
        |--------------------------------------------------------------------------
        | Minimum order quantity custom fields
        |--------------------------------------------------------------------------
        */

        /**
         * Add minimum order quantity custom field to simple products on product edit screen.
         * Note this also adds these custom fields to external products that closely similar to simple products since we used the more generic 'woocommerce_product_options_pricing' hook.
         * Ex. bundle and composite products.
         *
         * @since 1.2.0
         * @since 1.13.0 Refactor codebase and move to its dedicated model.
         * @since 1.16.0 Change section description to indicate changes with new quantity step feature.
         * @since 1.27.8 Hide the field if the product type is Advanced Gift Card.
         * @since 1.27.9 Added 'name' property for simple product type input to  to differentiate the input form between the simple product and the variable product to avoid the data is being overriden when change the product type from variable to simple on backend.
         *
         * @access public
         */
        public function add_minimum_order_quantity_fields() {
            /**
             * Allows to hide order quantity base wholesale pricing
             *
             * @param boolean $show Whether to show the pricing fields or not. Default true.
             * @return boolean
             */
            if ( ! apply_filters( 'wwpp_product_editor_show_order_quantity_fields', true ) ) {
                return;
            }

            $registered_wholesale_roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();
            global $woocommerce, $post;

            $visibility_classes = apply_filters( 'wwpp_filter_admin_custom_field_wholesale_min_order_quantity_visibility_clasess', array() ); ?>

            <div class="wholesale-minium-order-quantity-options-group options-group options_group <?php echo esc_attr( implode( ' ', $visibility_classes ) ); ?>">

                <header>
                    <h3 style="padding-bottom: 10px;"><?php esc_html_e( 'Wholesale Minimum Order Quantity', 'woocommerce-wholesale-prices-premium' ); ?></h3>
                    <p style="margin:0; padding:0 12px; line-height: 16px; font-style: italic; font-size: 13px;"><?php echo wp_kses_post( __( "Minimum number of items to be purchased in order to avail this product's wholesale price.<br/>Only applies to wholesale users.<br/><br/>Setting a step value below for the corresponding wholesale role will prevent the specific wholesale customer from adding to cart quantity of this product lower than the set minimum.", 'woocommerce-wholesale-prices-premium' ) ); ?></p>
                </header>

                <?php
                foreach ( $registered_wholesale_roles as $role_key => $role ) {

                    woocommerce_wp_text_input(
                        array(
                            'id'          => $role_key . '_wholesale_minimum_order_quantity',
                            'class'       => $role_key . '_wholesale_minimum_order_quantity wholesale_minimum_order_quantity short',
                            'name'        => $role_key . '_simple_wholesale_minimum_order_quantity',
                            'label'       => $role['roleName'],
                            'placeholder' => '',
                            'desc_tip'    => 'true',
                            'description' => sprintf(
                                // translators: %1$s is the wholesale role name.
                                __( 'Only applies to users with the role of "%1$s"', 'woocommerce-wholesale-prices-premium' ),
                                $role['roleName']
                            ),
                            'data_type'   => 'decimal',
                        )
                    );

                }
                ?>

            </div><!--.options_group-->

            <?php
        }

        /**
         * Add order quantity step custom field to simple products on product edit screen.
         * This is added on both simple and bundled products only.
         * Variable products have their own code logic.
         *
         * @since 1.16.0
         * @since 1.16.3 Add integration with bundle products.
         * @since 1.27.8 Hide the field if the product type is Advanced Gift Card.
         * @since 1.27.9 Added 'name' property for simple product type input to  to differentiate the input form between the simple product and the variable product to avoid the data is being overriden when change the product type from variable to simple on backend.
         *
         * @access public
         */
        public function add_order_quantity_step_fields() {
            /**
             * Allows to hide order quantity base wholesale pricing
             *
             * @param boolean $show Whether to show the pricing fields or not. Default true
             * @return boolean
             */
            if ( ! apply_filters( 'wwpp_product_editor_show_order_quantity_fields', true ) ) {
                return;
            }

            $registered_wholesale_roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();
            global $woocommerce, $post;

            $product = wc_get_product( $post->ID );

            if ( in_array( WWP_Helper_Functions::wwp_get_product_type( $product ), array( 'simple', 'bundle', 'variable' ), true ) ) {

                    $visibility_classes = apply_filters( 'wwpp_filter_admin_custom_field_wholesale_order_quantity_step_visibility_clasess', array() );
                    ?>

                    <div class="wholesale-order-quantity-step-options-group options-group options_group <?php echo esc_attr( implode( ' ', $visibility_classes ) ); ?>">

                    <header>
                        <h3 style="padding-bottom: 10px;"><?php esc_html_e( 'Wholesale Order Quantity Step', 'woocommerce-wholesale-prices-premium' ); ?></h3>
                        <p style="margin:0; padding:0 12px; line-height: 16px; font-style: italic; font-size: 13px;"><?php echo wp_kses_post( __( 'Order quantity step wholesale users are restricted to when purchasing this product.<br/>Only applies to wholesale users.<br/><br/>Minimum order quantity above for corresponding wholesale role must be set for this feature to take effect.', 'woocommerce-wholesale-prices-premium' ) ); ?></p>
                    </header>

                    <?php
                    foreach ( $registered_wholesale_roles as $role_key => $role ) {

                        woocommerce_wp_text_input(
                            array(
                                'id'          => $role_key . '_wholesale_order_quantity_step',
                                'class'       => $role_key . '_wholesale_order_quantity_step wholesale_order_quantity_step short',
                                'name'        => $role_key . '_simple_wholesale_order_quantity_step',
                                'label'       => $role['roleName'],
                                'placeholder' => '',
                                'desc_tip'    => 'true',
                                'description' => sprintf(
                                    // translators: %1$s is the wholesale role name.
                                    __( 'Only applies to users with the role of "%1$s"', 'woocommerce-wholesale-prices-premium' ),
                                    $role['roleName']
                                ),
                                'data_type'   => 'decimal',
                            )
                        );

                    }
                    ?>

                </div><!--.options_group-->

            <?php
            }
        }

        /**
         * Save minimum order quantity custom field value for simple products on product edit page.
         *
         * @since 1.2.0
         * @since 1.13.0 Refactor codebase and move its own model.
         * @since 1.27.9 Renamed '_wholesale_minimum_order_quantity' to '_simple_wholesale_minimum_order_quantity', if we use same exact value with the one on variable product, eventually when customer change from variable to simple the simple product data value will be overridden with the variable product data.
         *
         * @param int    $post_id      Product id.
         * @param string $product_type Product type.
         */
        public function save_minimum_order_quantity_fields( $post_id, $product_type = 'simple' ) {
            if (
                ( isset( $_POST['woocommerce_meta_nonce'] ) && wp_verify_nonce( wp_unslash( $_POST['woocommerce_meta_nonce'] ), 'woocommerce_save_data' ) ) ||
                ( isset( $_POST['woocommerce_quick_edit_nonce'] ) && wp_verify_nonce( wp_unslash( $_POST['woocommerce_quick_edit_nonce'] ), 'woocommerce_quick_edit_nonce' ) )
            ) {
                // Get product object.
                $product = wc_get_product( $post_id );

                $registered_wholesale_roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();

                foreach ( $registered_wholesale_roles as $role_key => $role ) {

                    if ( ! isset( $_POST[ $role_key . '_simple_wholesale_minimum_order_quantity' ] ) ) {
                        continue;
                    }

                    $wholesale_moq = trim( esc_attr( $_POST[ $role_key . '_simple_wholesale_minimum_order_quantity' ] ) );

                    if ( ! empty( $wholesale_moq ) ) {

                        if ( ! is_numeric( $wholesale_moq ) ) {
                            $wholesale_moq = '';
                        } elseif ( $wholesale_moq < 0 ) {
                            $wholesale_moq = 0;
                        } else {
                            $wholesale_moq = wc_format_decimal( $wholesale_moq );
                        }

                        $wholesale_moq = is_numeric( $wholesale_moq ) ? round( $wholesale_moq ) : '';

                    }

                    $wholesale_moq = wc_clean( apply_filters( 'wwpp_before_save_' . $product_type . '_product_wholesale_minimum_order_quantity', $wholesale_moq, $role_key, $post_id ) );
                    $product->update_meta_data( $role_key . '_wholesale_minimum_order_quantity', $wholesale_moq );
                }

                // Save the product.
                $product->save();
            }
        }

        /**
         * Save order quantity step custom field value for simple products on product edit page.
         *
         * @since 1.16.0
         * @since 1.27.9 Renamed '_wholesale_minimum_order_quantity' to '_simple_wholesale_minimum_order_quantity', if we use same exact value with the one on variable product, eventually when customer change from variable to simple the simple product data value will be overridden with the variable product data.
         *
         * @param int    $post_id      Product id.
         * @param string $product_type Product type.
         */
        public function save_order_quantity_step_fields( $post_id, $product_type = 'simple' ) {
            if (
                ( isset( $_POST['woocommerce_meta_nonce'] ) && wp_verify_nonce( wp_unslash( $_POST['woocommerce_meta_nonce'] ), 'woocommerce_save_data' ) ) ||
                ( isset( $_POST['woocommerce_quick_edit_nonce'] ) && wp_verify_nonce( wp_unslash( $_POST['woocommerce_quick_edit_nonce'] ), 'woocommerce_quick_edit_nonce' ) )
            ) {
                // Get product object.
                $product = wc_get_product( $post_id );

                $registered_wholesale_roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();

                foreach ( $registered_wholesale_roles as $role_key => $role ) {

                    if ( ! isset( $_POST[ $role_key . '_simple_wholesale_order_quantity_step' ] ) ) {
                        continue;
                    }

                    $wholesale_oqs = trim( esc_attr( $_POST[ $role_key . '_simple_wholesale_order_quantity_step' ] ) );

                    if ( ! empty( $wholesale_oqs ) ) {

                        if ( ! is_numeric( $wholesale_oqs ) ) {
                            $wholesale_oqs = '';
                        } elseif ( $wholesale_oqs < 0 ) {
                            $wholesale_oqs = 0;
                        } else {
                            $wholesale_oqs = wc_format_decimal( $wholesale_oqs );
                        }

                        $wholesale_oqs = is_numeric( $wholesale_oqs ) ? round( $wholesale_oqs ) : '';

                    }

                    $wholesale_oqs = wc_clean( apply_filters( 'wwpp_before_save_' . $product_type . '_product_wholesale_order_quantity_step', $wholesale_oqs, $role_key, $post_id ) );
                    $product->update_meta_data( $role_key . '_wholesale_order_quantity_step', $wholesale_oqs );

                }

                // Save the product.
                $product->save();
            }
        }

        /**
         * Display wholesale minimum order quantity in quick edit. Hooked into 'wwp_after_quick_edit_wholesale_price_fields'.
         *
         * @since 1.14.4
         * @since 1.16.0 Add wholesale order quantity step fields
         * @access public
         *
         * @param Array $all_wholesale_roles list of wholesale roles.
         */
        public function quick_edit_display_wwpp_fields( $all_wholesale_roles ) {

            ?>
                <div class="wwpp_quick_edit_fields quick_edit_wholesale_minimum_order_quantity" style="float: none; clear: both; display: block;">
                    <div style="height: 1px;"></div><!--To Prevent Heading From Bumping Up-->
                    <h4><?php esc_html_e( 'Wholesale Minimum Order Quantity', 'woocommerce-wholesale-prices-premium' ); ?></h4>
                    <?php
                        foreach ( $all_wholesale_roles as $role_key => $role ) {

                            // translators: %1$s: wholesale role name.
                            $wmoq_field_title = sprintf( __( '%1$s Minimum Order Quantity', 'woocommerce-wholesale-prices-premium' ), $role['roleName'] );
                            $wmoq_field_name  = $role_key . '_simple_wholesale_minimum_order_quantity';

                            $this->_add_wholesale_minimum_order_quantity_field_on_quick_edit_screen( $wmoq_field_title, $wmoq_field_name );

                        }
                    ?>
                </div>

                <div class="wwpp_quick_edit_fields quick_edit_wholesale_order_quantity_step" style="float: none; clear: both; display: block;">
                    <div style="height: 1px;"></div><!--To Prevent Heading From Bumping Up-->
                    <h4><?php esc_html_e( 'Wholesale Order Quantity Step', 'woocommerce-wholesale-prices-premium' ); ?></h4>
                    <?php
                        foreach ( $all_wholesale_roles as $role_key => $role ) {

                            // translators: %1$s: wholesale role name.
                            $woqs_field_title = sprintf( __( '%1$s Order Quantity Step', 'woocommerce-wholesale-prices-premium' ), $role['roleName'] );
                            $woqs_field_name  = $role_key . '_simple_wholesale_order_quantity_step';

                            $this->_add_wholesale_order_quantity_step_field_on_quick_edit_screen( $woqs_field_title, $woqs_field_name );

                        }
                    ?>
                </div>
            <?php
        }

        /**
         * Print custom wholesale minimum order quantity field on quick edit screen.
         *
         * @since 1.14.4
         * @access public
         *
         * @param string $field_title  Field title.
         * @param strin  $field_name   Field name.
         * @param string $place_holder Field placeholder.
         */
        private function _add_wholesale_minimum_order_quantity_field_on_quick_edit_screen( $field_title, $field_name, $place_holder = '' ) {

            ?>

            <label class="alignleft" style="width: 100%;">
                <div class="title"><?php echo esc_html( $field_title ); ?></div>
                <input type="text" name="<?php echo esc_attr( $field_name ); ?>" class="text wholesale_minimum_order_quantity wc_input_decimal" placeholder="<?php echo esc_attr( $place_holder ); ?>" value="">
            </label>

            <?php
        }

        /**
         * Print custom wholesale order quantity step field on quick edit screen.
         *
         * @since 1.16.0
         * @access public
         *
         * @param string $field_title  Field title.
         * @param strin  $field_name   Field name.
         * @param string $place_holder Field placeholder.
         */
        private function _add_wholesale_order_quantity_step_field_on_quick_edit_screen( $field_title, $field_name, $place_holder = '' ) {

            ?>

            <label class="alignleft" style="width: 100%;">
                <div class="title"><?php echo esc_html( $field_title ); ?></div>
                <input type="text" name="<?php echo esc_attr( $field_name ); ?>" class="text wholesale_order_quantity_step wc_input_decimal" placeholder="<?php echo esc_attr( $place_holder ); ?>" value="">
            </label>

            <?php
        }

        /**
         * Add the wholesale minimum order quantity data on the product listing column so it can be used to populate the
         * current values of the quick edit fields via javascript.
         *
         * @since 1.14.4
         * @since 1.16.0 Add support for wholesale order quantity step.
         * @access public
         *
         * @param Array $all_wholesale_roles   list of wholesale roles.
         * @param int   $product_id            Product ID.
         */
        public function add_wwpp_fields_data_to_product_listing_column( $all_wholesale_roles, $product_id ) {

            // Get product object.
            $product = wc_get_product( $product_id );

            $allowed_product_types = apply_filters( 'wwp_quick_edit_allowed_product_types', array( 'simple', 'external' ), 'wholesale_minimum_order_quantity' );
            ?>

            <div class="wholesale_custom_quick_edit_fields_allowed_product_types" data-product_types='<?php echo wp_json_encode( $allowed_product_types ); ?>'></div>

            <?php foreach ( $all_wholesale_roles as $role_key => $role ) : ?>

                <div class="wholesale_minimum_order_quantity_data" data-role="<?php echo esc_attr( $role_key ); ?>"><?php echo esc_html( $product->get_meta( $role_key . '_wholesale_minimum_order_quantity', true ) ); ?></div>
                <div class="wholesale_order_quantity_step_data" data-role="<?php echo esc_attr( $role_key ); ?>"><?php echo esc_html( $product->get_meta( $role_key . '_wholesale_order_quantity_step', true ) ); ?></div>

            <?php
            endforeach;
        }

        /**
         * Save wholesale custom fields on the quick edit option.
         *
         * @since 1.14.4
         * @since 1.16.0 Add support for wholesale order quantity step.
         * @access public
         *
         * @param WC_Product $product               Product object.
         * @param int        $product_id            Product ID.
         */
        public function save_wwpp_fields_on_quick_edit_screen( $product, $product_id ) {

            // Save minimum order quantity fields.
            $this->save_minimum_order_quantity_fields( $product_id );

            // Save order quantity step fields.
            $this->save_order_quantity_step_fields( $product_id );
        }

        /**
         * Add wholesale sale price field on the simple product edit page.
         *
         * @since 2.1.6
         *
         * @param array  $post_id                       The post ID.
         * @param array  $role                          Wholesale role array.
         * @param string $role_key                      Wholesale role key.
         * @param string $currency_symbol               Currency symbol ( $ | € ).
         * @param int    $wholesale_price               The wholesale price.
         * @param string $discount_type                 The discount type (fixed | percentage).
         * @param int    $wholesale_percentage_discount The Wholesale percentage discount value.
         */
        public function add_wholesale_sale_price_fields( $post_id, $role, $role_key, $currency_symbol, $wholesale_price, $discount_type, $wholesale_percentage_discount ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter

            global $WOOCS, $woocommerce_wpml;

            $product_object   = wc_get_product( $post_id );
            $fixed_sale_price = $product_object->get_meta( $role_key . '_wholesale_sale_price', true ) && ! $product_object->get_meta( $role_key . '_wholesale_percentage_discount', true ) ? $product_object->get_meta( $role_key . '_wholesale_sale_price', true ) : null;

            if ( empty( $WOOCS ) && empty( $woocommerce_wpml ) ) {
                woocommerce_wp_text_input(
                    array(
                        'id'                => $role_key . '_wholesale_sale_discount',
                        'class'             => $role_key . '_wholesale_sale_discount wholesale_sale_discount',
                        'label'             => __( 'Sale Discount (%)', 'woocommerce-wholesale-prices-premium' ),
                        'placeholder'       => '',
                        'desc_tip'          => 'true',
                        'description'       => __( 'The percentage amount discounted from the wholesale price', 'woocommerce-wholesale-prices-premium' ),
                        'data_type'         => 'price',
                        'custom_attributes' => array(
                            'data-wholesale_role' => $role_key,
                        ),
                    )
                );
            }

            woocommerce_wp_text_input(
                array(
                    'id'                => $role_key . '_wholesale_sale_price',
                    'class'             => $role_key . '_wholesale_sale_price wholesale_sale_price',
                    /* translators: %s: currency symbol */
                    'label'             => sprintf( __( 'Wholesale Sale Price (%1$s)', 'woocommerce-wholesale-prices-premiums' ), $currency_symbol ),
                    'placeholder'       => '',
                    'description'       => '<a href="#" class="wholesale_sale_schedule">' . __( 'Schedule', 'woocommerce-wholesale-prices-premium' ) . '</a>',
                    'data_type'         => 'price',
                    'custom_attributes' => array(
                        'data-fixed_sale_price' => $fixed_sale_price,
                    ),
                )
            );

            $sale_price_dates_from_timestamp = $product_object->get_meta( $role_key . '_wholesale_sale_price_dates_from', true ) ? $product_object->get_meta( $role_key . '_wholesale_sale_price_dates_from', true ) : false;
            $sale_price_dates_to_timestamp   = $product_object->get_meta( $role_key . '_wholesale_sale_price_dates_to', true ) ? $product_object->get_meta( $role_key . '_wholesale_sale_price_dates_to', true ) : false;

            $sale_price_dates_from = $sale_price_dates_from_timestamp ? date_i18n( 'Y-m-d', $sale_price_dates_from_timestamp ) : '';
            $sale_price_dates_to   = $sale_price_dates_to_timestamp ? date_i18n( 'Y-m-d', $sale_price_dates_to_timestamp ) : '';

            echo '<p class="form-field ' . esc_attr( $role_key ) . '_wholesale_sale_price_dates_fields wholesale_sale_price_dates_fields">
				<label for="wholesale__sale_price_dates_from">' . esc_html__( 'Sale price dates', 'woocommerce-wholesale-prices-premium' ) . '</label>
				<input type="text" name="' . esc_attr( $role_key ) . '_wholesale_sale_price_dates_from" id="' . esc_attr( $role_key ) . '_wholesale_sale_price_dates_from" value="' . esc_attr( $sale_price_dates_from ) . '" placeholder="' . esc_html( _x( 'From&hellip;', 'placeholder', 'woocommerce-wholesale-prices-premium' ) ) . ' YYYY-MM-DD" maxlength="10" pattern="' . esc_attr( apply_filters( 'woocommerce_date_input_html_pattern', '[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])' ) ) . '" />
				<input type="text" name="' . esc_attr( $role_key ) . '_wholesale_sale_price_dates_to" id="' . esc_attr( $role_key ) . '_wholesale_sale_price_dates_to" value="' . esc_attr( $sale_price_dates_to ) . '" placeholder="' . esc_html( _x( 'To&hellip;', 'placeholder', 'woocommerce-wholesale-prices-premium' ) ) . '  YYYY-MM-DD" maxlength="10" pattern="' . esc_attr( apply_filters( 'woocommerce_date_input_html_pattern', '[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])' ) ) . '" />
				<a href="#" class="description cancel_wholesale_sale_schedule">' . esc_html__( 'Cancel', 'woocommerce-wholesale-prices-premium' ) . '</a>' . wp_kses_post( wc_help_tip( __( 'The sale will start at 00:00:00 of "From" date and end at 23:59:59 of "To" date.', 'woocommerce-wholesale-prices-premium' ) ) ) . '
			</p>';
        }

        /**
         * Aelia Currency Switcher - Add wholesale sale price field for each available currency on the simple product edit page.
         *
         * @since 2.1.6
         *
         * @param array  $post_id                       The post ID.
         * @param array  $role                          Wholesale role array.
         * @param string $role_key                      Wholesale role key.
         * @param string $currency_symbol               Currency symbol ( $ | € ).
         * @param string $currency_code                 Currency code ( AUD | EUR | USD ).
         * @param int    $wholesale_price               The wholesale price.
         */
        public function add_wacs_wholesale_sale_price_fields( $post_id, $role, $role_key, $currency_symbol, $currency_code, $wholesale_price ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter

            $product_object = wc_get_product( $post_id );
            $base_currency  = WWP_ACS_Integration_Helper::get_product_base_currency( $post_id );

            if ( $currency_code === $base_currency ) {
                $wholesale_sale_price            = $product_object->get_meta( $role_key . '_wholesale_sale_price', true ); // Get base currency wholesale sale price.
                $sale_price_dates_from_timestamp = $product_object->get_meta( $role_key . '_wholesale_sale_price_dates_from', true ) ? $product_object->get_meta( $role_key . '_wholesale_sale_price_dates_from', true ) : false;
                $sale_price_dates_to_timestamp   = $product_object->get_meta( $role_key . '_wholesale_sale_price_dates_to', true ) ? $product_object->get_meta( $role_key . '_wholesale_sale_price_dates_to', true ) : false;
                $field_id                        = esc_attr( $role_key );
            } else {
                $wholesale_sale_price            = $product_object->get_meta( $role_key . '_' . $currency_code . '_wholesale_sale_price', true ); // Get specific currency wholesale sale price.
                $sale_price_dates_from_timestamp = $product_object->get_meta( $role_key . '_' . $currency_code . '_wholesale_sale_price_dates_from', true ) ? $product_object->get_meta( $role_key . '_' . $currency_code . '_wholesale_sale_price_dates_from', true ) : false;
                $sale_price_dates_to_timestamp   = $product_object->get_meta( $role_key . '_' . $currency_code . '_wholesale_sale_price_dates_to', true ) ? $product_object->get_meta( $role_key . '_' . $currency_code . '_wholesale_sale_price_dates_to', true ) : false;
                $field_id                        = esc_attr( $role_key ) . '_' . $currency_code;
            }

            woocommerce_wp_text_input(
                array(
                    'id'          => $field_id . '_wholesale_sale_price',
                    'class'       => $field_id . '_wholesale_sale_price wholesale_sale_price short ',
                    /* translators: %s: currency symbol */
                    'label'       => sprintf( __( 'Wholesale Sale Price (%1$s)', 'woocommerce-wholesale-prices-premiums' ), $currency_symbol ),
                    'placeholder' => __( 'Auto', 'woocommerce-wholesale-prices-premium' ),
                    'description' => '<a href="#" class="wholesale_sale_schedule">' . __( 'Schedule', 'woocommerce-wholesale-prices-premium' ) . '</a>',
                    'data_type'   => 'price',
                    'value'       => $wholesale_sale_price,
                )
            );

            $sale_price_dates_from = $sale_price_dates_from_timestamp ? date_i18n( 'Y-m-d', $sale_price_dates_from_timestamp ) : '';
            $sale_price_dates_to   = $sale_price_dates_to_timestamp ? date_i18n( 'Y-m-d', $sale_price_dates_to_timestamp ) : '';

            echo '<p class="form-field ' . esc_attr( $field_id ) . '_wholesale_sale_price_dates wholesale_sale_price_dates_fields">
				<label for="wholesale__sale_price_dates_from">' . esc_html__( 'Sale price dates', 'woocommerce-wholesale-prices-premium' ) . '</label>
				<input type="text" name="' . esc_attr( $field_id ) . '_wholesale_sale_price_dates_from" id="' . esc_attr( $field_id ) . '_wholesale_sale_price_dates_from" value="' . esc_attr( $sale_price_dates_from ) . '" placeholder="' . esc_html( _x( 'From&hellip;', 'placeholder', 'woocommerce-wholesale-prices-premium' ) ) . ' YYYY-MM-DD" maxlength="10" pattern="' . esc_attr( apply_filters( 'woocommerce_date_input_html_pattern', '[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])' ) ) . '" />
				<input type="text" name="' . esc_attr( $field_id ) . '_wholesale_sale_price_dates_to" id="' . esc_attr( $field_id ) . '_wholesale_sale_price_dates_to" value="' . esc_attr( $sale_price_dates_to ) . '" placeholder="' . esc_html( _x( 'To&hellip;', 'placeholder', 'woocommerce-wholesale-prices-premium' ) ) . '  YYYY-MM-DD" maxlength="10" pattern="' . esc_attr( apply_filters( 'woocommerce_date_input_html_pattern', '[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])' ) ) . '" />
				<a href="#" class="description cancel_wholesale_sale_schedule">' . esc_html__( 'Cancel', 'woocommerce-wholesale-prices-premium' ) . '</a>' . wp_kses_post( wc_help_tip( __( 'The sale will start at 00:00:00 of "From" date and end at 23:59:59 of "To" date.', 'woocommerce-wholesale-prices-premium' ) ) ) . '
			</p>';
        }

        /**
         * Save wholesale sale prices custom field value for simple products on product edit page.
         *
         * @since 1.30.1
         *
         * @param int $post_id The product id.
         */
        public function save_wholesale_sale_price_fields( $post_id ) {

            $registered_wholesale_roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();
            $decimal_sep                = wc_get_price_decimal_separator();
            $thousand_sep               = wc_get_price_thousand_separator();

            if ( WWP_ACS_Integration_Helper::aelia_currency_switcher_active() ) {
                $wacs_enabled_currencies = WWP_ACS_Integration_Helper::enabled_currencies(); // Get all active currencies.
                $base_currency           = WWP_ACS_Integration_Helper::get_product_base_currency( $post_id ); // Get base currency. Product base currency ( if present ) or shop base currency.

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

                        $this->_save_wholesale_sale_price_fields( $post_id, $role_key, $meta_field_key, $thousand_sep, $decimal_sep, true, $is_base_currency, $currency_code );

                    }
                }
            } else {
                foreach ( $registered_wholesale_roles as $role_key => $role ) {
                    $this->_save_wholesale_sale_price_fields( $post_id, $role_key, $role_key, $thousand_sep, $decimal_sep );
                }
            }
        }

        /**
         * Save product wholesale sale price.
         *
         * @since 1.30.1
         *
         * @param int     $post_id                        Product id.
         * @param string  $role_key                       Wholesale role key.
         * @param string  $meta_field_key                 Meta field key.
         * @param string  $thousand_sep                   Thousand separator.
         * @param string  $decimal_sep                    Decimal separator.
         * @param boolean $aelia_currency_switcher_active Flag that detemines if aelia currency switcher is active or not.
         * @param boolean $is_base_currency               Flag that determines if this is a base currency.
         * @param mixed   $currency_code                  String of current currency code or null.
         */
        private function _save_wholesale_sale_price_fields( $post_id, $role_key, $meta_field_key, $thousand_sep, $decimal_sep, $aelia_currency_switcher_active = false, $is_base_currency = false, $currency_code = null ) {

            if ( wp_verify_nonce( wp_unslash( $_POST['woocommerce_meta_nonce'] ), 'woocommerce_save_data' ) ) {
                // Get product object.
                $product = wc_get_product( $post_id );

                $wholesale_discount_type = isset( $_POST [ $meta_field_key . '_wholesale_discount_type' ] ) ? trim( esc_attr( $_POST[ $meta_field_key . '_wholesale_discount_type' ] ) ) : '';

                if ( isset( $_POST[ $meta_field_key . '_wholesale_sale_price' ] ) && '' !== $_POST[ $meta_field_key . '_wholesale_sale_price' ] ) {
                    $wholesale_sale_price = trim( esc_attr( $_POST[ $meta_field_key . '_wholesale_sale_price' ] ) );

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

                        $wholesale_sale_price = wc_clean( apply_filters( 'wwpp_before_save_simple_product_wholesale_sale_price', $wholesale_sale_price, $role_key, $post_id, $aelia_currency_switcher_active, $is_base_currency, $currency_code ) );

                        $product->update_meta_data( $meta_field_key . '_wholesale_sale_price', $wholesale_sale_price );
                    }
                } else {
                    $product->delete_meta_data( $meta_field_key . '_wholesale_sale_price' );
                }

                if ( 'percentage' === $wholesale_discount_type ) {
                    $wholesale_sale_discount = trim( esc_attr( $_POST[ $meta_field_key . '_wholesale_sale_discount' ] ) );
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

                    $product->update_meta_data( $meta_field_key . '_wholesale_sale_discount', $wholesale_sale_discount );
                } else {
                    $product->delete_meta_data( $meta_field_key . '_wholesale_sale_discount' );
                }

                // Handle dates.
                $date_on_sale_from = '';
                $date_on_sale_to   = '';

                // Force date from to beginning of day.
                if ( isset( $_POST[ $meta_field_key . '_wholesale_sale_price_dates_from' ] ) ) {
                    $date_on_sale_from = wc_clean( wp_unslash( $_POST[ $meta_field_key . '_wholesale_sale_price_dates_from' ] ) );

                    if ( ! empty( $date_on_sale_from ) && is_string( $date_on_sale_from ) ) {
                        $date_on_sale_from = strtotime( date( 'Y-m-d 00:00:00', strtotime( $date_on_sale_from ) ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
                    }
                }

                $product->update_meta_data( $meta_field_key . '_wholesale_sale_price_dates_from', $date_on_sale_from );

                // Force date to to the end of the day.
                if ( isset( $_POST[ $meta_field_key . '_wholesale_sale_price_dates_to' ] ) ) {
                    $date_on_sale_to = wc_clean( wp_unslash( $_POST[ $meta_field_key . '_wholesale_sale_price_dates_to' ] ) );

                    if ( ! empty( $date_on_sale_to ) && is_string( $date_on_sale_to ) ) {
                        $date_on_sale_to = strtotime( date( 'Y-m-d 23:59:59', strtotime( $date_on_sale_to ) ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
                    }
                }

                $product->update_meta_data( $meta_field_key . '_wholesale_sale_price_dates_to', $date_on_sale_to );

                WWPP_Admin_Custom_Fields_Product::maybe_set_have_wholesale_sale_price_meta( $post_id, $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles() );

                // Save the product.
                $product->save();
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

            $wwp_plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/woocommerce-wholesale-prices/woocommerce-wholesale-prices.bootstrap.php', false, false );

            add_action( 'woocommerce_product_options_pricing', array( $this, 'add_minimum_order_quantity_fields' ), 20, 1 );
            add_action( 'woocommerce_product_options_pricing', array( $this, 'add_order_quantity_step_fields' ), 20, 1 );
            add_action( 'woocommerce_process_product_meta_simple', array( $this, 'save_minimum_order_quantity_fields' ), 20, 1 );
            add_action( 'woocommerce_process_product_meta_simple', array( $this, 'save_order_quantity_step_fields' ), 20, 1 );
            add_action( 'wwp_after_quick_edit_wholesale_price_fields', array( $this, 'quick_edit_display_wwpp_fields' ), 10, 1 );
            add_action( 'wwp_add_wholesale_price_fields_data_to_product_listing_column', array( $this, 'add_wwpp_fields_data_to_product_listing_column' ), 10, 2 );
            add_action( 'wwp_save_wholesale_price_fields_on_quick_edit_screen', array( $this, 'save_wwpp_fields_on_quick_edit_screen' ), 10, 2 );

            if ( version_compare( $wwp_plugin_data['Version'], '2.1.6', '>=' ) ) {
                // Wholesale sale price fields.
                add_action( 'wwp_after_simple_wholesale_price_field', array( $this, 'add_wholesale_sale_price_fields' ), 10, 7 );
                add_action( 'woocommerce_process_product_meta_simple', array( $this, 'save_wholesale_sale_price_fields' ), 20, 1 );

                add_action( 'wwp_after_wacs_simple_wholesale_price_field', array( $this, 'add_wacs_wholesale_sale_price_fields' ), 10, 6 );
            }
        }
    }
}
