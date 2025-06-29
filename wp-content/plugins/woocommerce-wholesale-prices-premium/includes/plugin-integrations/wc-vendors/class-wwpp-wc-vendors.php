<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WCVendors class.
 *
 * WCVendors integration.
 *
 * @since 2.0.3 - Added
 */
if ( ! class_exists( 'WWPP_WC_Vendors' ) ) {

    /**
     * The class that handles the integration with WCVendors.
     *
     * @since 2.0.3
     */
    class WWPP_WC_Vendors {

        /**
         * The single instance of WWPP_WC_Vendors.
         *
         * @var WWPP_WC_Vendors
         */
        private static $_instance = null;

        /**
         * The current version of the plugin.
         *
         * @var string
         */
        private $_wwpp_current_version;

        /**
         * Wholesale roles.
         *
         * @var WWPP_Wholesale_Roles
         */
        private $_wwpp_wholesale_roles;

        /**
         * List of all registered roles.
         *
         * @var array
         */
        private $registered_roles = array();

        /**
         * Create a single instance of WWPP_WCVendors.
         *
         * @param mixed $dependencies The dependencies the class needs to run.
         */
        public function __construct( $dependencies ) {
            $this->_wwpp_current_version = $dependencies['WWPP_CURRENT_VERSION'];
            $this->_wwpp_wholesale_roles = $dependencies['wwpp_wholesale_roles'];

            $this->registered_roles = $this->get_registered_wholesale_roles();
        }

        /**
         * Get a single instance of WWPP_WCVendors.
         *
         * @param mixed $dependencies The dependencies the class needs to run.
         * @return WWPP_WC_Vendors
         */
        public static function instance( $dependencies ) {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Check if WC Vendors Pro is active.
         *
         * @since 2.0.3 - Added
         * @access public
         *
         * @return bool
         */
        public function is_wcv_pro_active() {
            return WWP_Helper_Functions::is_plugin_active( 'wc-vendors-pro/wcvendors-pro.php' ) && defined( 'WCV_PRO_VERSION' );
        }

        /**
         * Check if wholesale is enabled in the store.
         *
         * This checks if the wholesale feature is enabled in the store. If not enabled, wholesale features will not be enabled for vendors.
         *
         * @since 2.0.3 - Added
         * @access public
         *
         * @return bool
         */
        public function is_wholesale_enabled() {
            $wholesale_enabled = get_option( 'wwpp_enable_wholesale_for_vendors', 'no' );

            return ( 'yes' === $wholesale_enabled ) ? true : false;
        }

        /**
         * Run the integration.
         *
         * Runs the hooks and applies the filters necessary for the integration.
         *
         * @since 2.0.3
         * @access public
         *
         * @return void
         */
        public function run() {
            // Only run if WC Vendors Pro is active.
            if ( ! $this->is_wcv_pro_active() ) {
                return;
            }

            // Wholesale hooks.
            add_filter( 'wwp_admin_setting_default_tabs', array( $this, 'wholesale_admin_settings_tabs' ) );
            add_filter( 'wwp_admin_setting_default_controls', array( $this, 'wholesale_admin_settings_controls' ) );

            // WC Vendors hooks.
            add_filter( 'wc_vendors_all_vendors_page_setting_fields', array( $this, 'vendor_edit_settings' ) );
            add_filter( 'wcvendors_store_tabs', array( $this, 'wcv_store_tabs' ) );
            add_filter( 'wcvendors_vendor_settings_keys', array( $this, 'vendor_settings_keys' ) );

            add_action( 'wcvendors_settings_after_seo_tab', array( $this, 'vendor_settings_tab_content' ) );
            add_action( 'wcvendors_store_settings_saved', array( $this, 'save_vendor_settings' ) );

            /**
             * Wholesale pricing fields.
             */
            add_action( 'wcv_after_product_prices', array( $this, 'product_price_fields' ) );
            add_action( 'wcv_after_product_prices', array( $this, 'wholesale_roles_restrictions' ) );
            add_action( 'wcv_product_variation_after_pricing', array( $this, 'variation_price_fields' ), 10, 4 );
            add_action( 'wcv_product_variation_after_pricing', array( $this, 'wholesale_exclusive_variation' ), 10, 2 );

            foreach ( $this->registered_roles as $key => $details ) {
                add_action( "wwpp_wcvendors_after_{$key}_wholesale_sale_price_field", array( $this, 'wholesale_sale_price_fields' ) );
            }

            /**
             * Save the wholesale prices for a product.
             */
            add_action( 'wcv_create_product_variation', array( $this, 'save_variation' ), 10, 2 );
            add_action( 'wcv_update_product_variation', array( $this, 'save_variation' ), 10, 2 );
            add_action( 'wcv_save_product_meta', array( $this, 'save_product_meta' ), 10, 2 );
            add_action( 'wwp_after_save_variable_product_wholesale_price', array( $this, 'save_variation_additional_meta' ), 11, 3 );

            /**
             * Dashboard orders hooks.
             */
            add_action( 'wcvendors_vendor_order_item_meta', array( $this, 'order_item_meta' ) );
            add_filter( 'woocommerce_display_item_meta', array( $this, 'order_detail_item_meta' ), 10, 2 );

            // Frontend scripts.
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

            /**
             * Override wholesale prices options for vendor
             */
            add_filter( 'option_wwp_prices_settings_show_wholesale_prices_to_non_wholesale', array( $this, 'override_show_wholesale_prices' ), 99 );
            add_filter( 'option_wwp_show_wholesale_prices_to_non_wholesale_customers', array( $this, 'override_show_wholesale_prices' ), 99 );
            add_filter( 'wwp_show_wholesale_prices_to_non_wholesale_customers', array( $this, 'filter_show_wholesale_prices' ), 99, 2 );
            add_filter( 'wwp_non_wholesale_show_in_shop', array( $this, 'override_show_in_shop' ), 99, 2 );
            add_filter( 'wwp_non_wholesale_show_in_products', array( $this, 'override_show_in_products' ), 99, 2 );
            add_filter( 'wwp_load_show_wholesale_to_non_wholesale', array( $this, 'filter_load_scripts' ), 100, 2 );

            add_filter( 'wwp_filter_wholesale_price_html', array( $this, 'wholesale_price_html' ), 11, 3 );
            add_filter( 'wwpp_filter_wholesale_sale_price_html', array( $this, 'wholesale_sale_price_html' ), 11, 5 );

            add_filter( 'woocommerce_cart_item_price', array( $this, 'filter_cart_item_price' ), 300, 2 );
            add_filter( 'wwp_get_product_raw_wholesale_price', array( $this, 'filter_raw_wholesale_price' ), 11, 2 );
            add_filter( 'wwpp_order_item_wholesale_priced', array( $this, 'filter_order_item_wholesale_priced' ), 11, 2 );
            add_action( 'wwp_add_order_item_meta', array( $this, 'update_order_item_meta' ) );

            /**
             * WP Admin product editor fields
             */
            add_filter( 'wwp_product_editor_show_wholesale_prices_fields', array( $this, 'editor_show_wholesale_prices_fields' ) );
            add_filter( 'wwpp_product_editor_show_order_quantity_fields', array( $this, 'editor_show_order_quantity_fields' ) );
            add_filter( 'wwpp_product_editor_show_role_visibility_filter', array( $this, 'maybe_hide_wholesale_visibility_filter_field' ) );

            add_filter( 'wwp_registered_wholesale_roles', array( $this, 'filter_registered_wholesale_roles' ), 99 );
            add_filter( 'wwpp_wholesale_exclusive_variation_roles', array( $this, 'filter_registered_wholesale_roles' ), 99 );

            add_filter( 'wwp_non_wholesale_roles_options', array( $this, 'filter_non_wholesale_role_options' ), 999, 2 );
            add_filter( 'woocommerce_related_products', array( $this, 'filter_ralated_products' ), 10, 2 );

            /**
             * WP Admin Products filters
             */
            add_filter( 'wwp_show_wholesale_price_column_value', array( $this, 'filter_show_wholesale_price_column_value' ), 11, 2 );
        }

        /**
         * Check if we are on the vendor product edit or vendor dashboard screen
         *
         * @version 2.0.3
         * @since   2.0.3 - Added.
         *
         * @return bool
         */
        public function is_vendor_product_page() {
            global $wp_query;

            $page_objects = array( 'product', 'settings' );

            return ( isset( $wp_query->query['object'] ) && in_array( $wp_query->query['object'], $page_objects, true ) ) || wcv_is_dashboard_page();
        }

        /**
         * Register and enqueue script
         *
         * @version 2.0.3
         * @since   2.0.3 - Added.
         *
         * @return void
         */
        public function enqueue_scripts() {
            if ( ! $this->is_vendor_product_page() ) {
                return;
            }

            wp_register_script(
                'wwpp_wc_vendors_wholesale_prices',
                WWPP_JS_URL . '/app/wwpp-wc-vendors-wholesale-prices.js',
                array( 'jquery' ),
                $this->_wwpp_current_version,
                true
            );

            wp_localize_script(
                'wwpp_wc_vendors_wholesale_prices',
                'wwpp_wcvendors_params',
                array(
                    'wholesale_roles' => $this->registered_roles,
                    'decimal_sep'     => wc_get_price_decimal_separator(),
                    'decimal_num'     => wc_get_price_decimals(),
                    'thousand_sep'    => wc_get_price_thousand_separator(),
                ),
            );

            wp_enqueue_script( 'wwpp_wc_vendors_wholesale_prices' );
        }

        /**
         * Enqueue the styles.
         *
         * @version 2.0.3
         * @since   2.0.3
         *
         * @return void
         */
        public function enqueue_styles() {
            if ( ! $this->is_vendor_product_page() ) {
                return;
            }

            wp_enqueue_style(
                'wwpp_wcvendors_integration',
                WWPP_CSS_URL . 'frontend/wwpp-wc-vendors-integration.css',
                array(),
                $this->_wwpp_current_version,
                'all'
            );
        }

        /**
         * Add the wholesale tab to the WC Vendors store.
         *
         * @since 2.0.3
         * @access public
         *
         * @param mixed $tabs The current tabs array.
         * @return mixed
         */
        public function wcv_store_tabs( $tabs ) {

            if ( ! $this->is_wholesale_enabled() || ! $this->is_wholesale_enabled_for_vendor( get_current_user_id() ) ) {
                return $tabs;
            }

            $tabs['wholesale'] = array(
                'label'  => __( 'Wholesale', 'woocommerce-wholesale-prices-premium' ),
                'target' => 'wholesale',
                'class'  => array(),
            );

            return $tabs;
        }

        /**
         * Add vendor settings keys.
         *
         * @since 2.0.3
         * @access public
         *
         * @param array $keys The vendor settings keys.
         * @return array
         */
        public function vendor_settings_keys( $keys ) {
            if ( ! $this->is_wholesale_enabled() ) {
                return $keys;
            }

            $keys['wcvp']['wholesale_enabled'] = '_wcv_wholesale_enabled';

            return $keys;
        }

        /**
         * Add the WC Vendors to Wholesale admin settings tabs.
         *
         * @since 2.0.3
         * @access public
         *
         * @param mixed $tabs The currently existing tabs array.
         * @return mixed
         */
        public function wholesale_admin_settings_tabs( $tabs ) {
            $tabs['wholesale_prices']['child']['wcvendors'] = array(
                'sort'     => 7,
                'key'      => 'wcvendors',
                'label'    => __( 'WC Vendors', 'woocommerce-wholesale-prices-premium' ),
                'sections' => array(
                    'wcvendors_options' => array(
                        'label' => __( 'WC Vendors', 'woocommerce-wholesale-prices-premium' ),
                        'desc'  => __( 'Manage the capabilities of vendors.', 'woocommerce-wholesale-prices-premium' ),
                    ),
                ),

            );

            return $tabs;
        }

        /**
         * Add the WC Vendors settings controls to the Wholesale admin settings.
         *
         * @since 2.0.3
         * @access public
         *
         * @param mixed $controls The currently existing controls array.
         * @return mixed
         */
        public function wholesale_admin_settings_controls( $controls ) {
            $controls['wholesale_prices']['wcvendors'] = $this->wcvendors_tab_controls();

            return $controls;
        }

        /**
         * Add the WC Vendors settings to the vendor edit screen.
         *
         * @since 2.0.3
         * @access public
         *
         * @param mixed $settings The currently existing vendor settings array.
         * @return mixed
         */
        public function vendor_edit_settings( $settings ) {
            if ( ! $this->is_wholesale_enabled() ) {
                return $settings;
            }

            $vendor_name = wcv_get_vendor_name( true, false );
            $settings[]  = array(
                'key'    => 'wholesale',
                'label'  => __( 'Wholesale', 'woocommerce-wholesale-prices-premium' ),
                'fields' => array(
                    array(
                        'id'      => 'wholesale_enabled',
                        'title'   => __( 'Enable Wholesale', 'woocommerce-wholesale-prices-premium' ),
                        'type'    => 'checkbox',
                        'desc'    => sprintf(
                            // translators: %1$s - is the name used to refer to vendors, %2$s - is the name used to refer to vendors.
                            __( 'Enable wholesale for the %1$s. If enabled, the %2$s will be able to sell products to wholesale customers.', 'woocommerce-wholesale-prices-premium' ),
                            $vendor_name,
                            $vendor_name
                        ),
                        'default' => 'no',
                        'is_pro'  => true,
                        'intro'   => sprintf(
                            // translators: %s - is the name used to refer to vendors.
                            __( 'Enable wholesale for the %s. ', 'woocommerce-wholesale-prices-premium' ),
                            $vendor_name,
                        ),
                    ),
                ),
            );

            return $settings;
        }

        /**
         * The vendor settings controls.
         *
         * @since 2.0.3
         * @access public
         *
         * @return mixed
         */
        public function wcvendors_tab_controls() {
            $registered_roles = array_filter( $this->registered_roles );
            $roles_options    = array_filter( $this->role_keys_to_options( array_keys( $registered_roles ) ) );
            $allowed_roles    = array_filter( get_option( 'wwpp_roles_visible_to_vendors', array_keys( $roles_options ) ) );

            $enable_wholesale_for_vendors        = get_option( 'wwpp_enable_wholesale_for_vendors', 'no' );
            $allow_role_restrictions_for_vendors = get_option( 'wwpp_allow_vendors_to_restrict_product_visibility', 'no' );

            $hide_role_restrictions = ( 'no' === $enable_wholesale_for_vendors ) ? true : false;

            // Roles Options - Section.
            $vendors_name                             = wcv_get_vendor_name( false, false );
            $vendor_tab_controls['wcvendors_options'] = array(
                array(
                    'type'        => 'switch',
                    'label'       => sprintf(
                        // translators: %s - is the name used to refer to a vendor.
                        __( 'Enable wholesale for %s', 'woocommerce-wholesale-prices-premium' ),
                        $vendors_name
                    ),
                    'id'          => 'wwpp_enable_wholesale_for_vendors',
                    'default'     => $enable_wholesale_for_vendors,
                    'options'     => array(
                        'yes' => __( 'Enabled', 'woocommerce-wholesale-prices-premium' ),
                        'no'  => __( 'Disabled', 'woocommerce-wholesale-prices-premium' ),
                    ),
                    'description' => sprintf(
                        // translators: %1$s - is the name used to refer to vendors, %2$s - the name used to refer to vendors.
                        __( 'This will allow %1$s to sell wholesale products. If disabled, wholesale features will not be available for %2$s.', 'woocommerce-wholesale-prices-premium' ),
                        $vendors_name,
                        $vendors_name
                    ),
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => sprintf(
                        // translators: %s - is the name used to refer to a vendor.
                        __( 'Allow %s to restrict product visibility', 'woocommerce-wholesale-prices-premium' ),
                        $vendors_name
                    ),
                    'id'          => 'wwpp_allow_vendors_to_restrict_product_visibility',
                    'default'     => $allow_role_restrictions_for_vendors,
                    'hide'        => $hide_role_restrictions,
                    'condition'   => array(
                        array(
                            'key'   => 'wwpp_enable_wholesale_for_vendors',
                            'value' => 'yes',
                        ),
                    ),
                    'input_label' => sprintf(
                        // translators: %s - is the name used to refer to a vendor.
                        __( 'If checked, %s will be able to restrict product visibility to certain wholesale roles.', 'woocommerce-wholesale-prices-premium' ),
                        $vendors_name
                    ),
                ),
                array(
                    'type'        => 'select',
                    'label'       => __( 'Vendor Wholesale Roles', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_roles_visible_to_vendors',
                    'multiple'    => true,
                    'options'     => $roles_options,
                    'default'     => $allowed_roles,
                    'hide'        => $hide_role_restrictions,
                    'condition'   => array(
                        array(
                            'key'   => 'wwpp_enable_wholesale_for_vendors',
                            'value' => 'yes',
                        ),
                    ),
                    'description' => sprintf(
                        // translators: %s - is the name used to refer to a vendor.
                        __( 'Select the wholesale roles that the %s can select from.', 'woocommerce-wholesale-prices-premium' ),
                        wcv_get_vendor_name( true, false )
                    ),
                ),
            );

            /**
             * Filter to modify the vendor tab controls.
             *
             * @since 2.0.3
             *
             * @param  array $vendor_tab_controls Tab controls.
             * @return array
             */
            $vendor_tab_controls = apply_filters(
                'wwp_admin_setting_wcvendors_tab_controls_controls',
                $vendor_tab_controls
            );

            return $vendor_tab_controls;
        }

        /**
         * Save the vendor settings.
         *
         * @since 2.0.3
         * @access public
         *
         * @param mixed $vendor_id The vendor ID.
         * @return void
         */
        public function save_vendor_settings( $vendor_id ) {

            if ( ! $this->is_wholesale_enabled_for_vendor( $vendor_id ) ) {
                return;
            }

            if ( empty( $_POST['_wcv-save_store_settings'] ) || ! wp_verify_nonce( $_POST['_wcv-save_store_settings'], 'wcv-save_store_settings' ) || ! is_user_logged_in() ) {
                return;
            }

            update_user_meta(
                $vendor_id,
                'wwp_prices_settings_show_wholesale_prices_to_non_wholesale',
                isset( $_POST['wwp_prices_settings_show_wholesale_prices_to_non_wholesale'] ) ? 'yes' : 'no'
            );

            update_user_meta(
                $vendor_id,
                'wwp_non_wholesale_show_in_products',
                isset( $_POST['wwp_non_wholesale_show_in_products'] ) ? 'yes' : 'no'
            );

            update_user_meta(
                $vendor_id,
                'wwp_non_wholesale_show_in_shop',
                isset( $_POST['wwp_non_wholesale_show_in_shop'] ) ? 'yes' : 'no'
            );

            $vendor_wholesale_customer_roles = isset( $_POST['wwp_non_wholesale_wholesale_role_select2'] ) ? $_POST['wwp_non_wholesale_wholesale_role_select2'] : array();

            $wholesale_roles = array_unique( array_map( 'sanitize_text_field', $vendor_wholesale_customer_roles ) );

            update_user_meta(
                $vendor_id,
                'wwp_non_wholesale_wholesale_role_select2',
                implode( ',', $wholesale_roles )
            );
        }

        /**
         * Show the wholesale prices fields.
         *
         * @since 2.0.3
         * @access public
         *
         * @param mixed $product_id The product ID.
         * @return void
         */
        public function product_price_fields( $product_id ) {
            $product = wc_get_product( $product_id );

            $this->render_pricing_fields( $product );
        }

        /**
         * Show the wholesale prices fields for variations.
         *
         * @since 2.0.3
         * @access public
         *
         * @param mixed $loop The loop index.
         * @param mixed $variation_id The variation ID.
         * @param mixed $variation_data The variation data.
         * @param mixed $variation The variation object.
         * @return void
         */
        public function variation_price_fields( $loop, $variation_id, $variation_data, $variation ) {
            // Hide variation wholesale pricing fields if wholesale is disabled for this vendor.
            if ( ! $this->is_wholesale_enabled_for_vendor( get_current_user_id() ) ) {
                return;
            }

            $product = wc_get_product( $variation_id );

            if ( ! is_a( $product, 'WC_Product_Variation' ) && 0 !== $variation_id ) {
                return;
            }

            $variation_args = array(
                'loop'           => $loop,
                'variation_id'   => $variation_id,
                'variation_data' => $variation_data,
                'variation'      => $variation,
            );

            $this->render_pricing_fields(
                $product,
                $variation_args
            );
        }

        /**
         * Render wholesale sale price date fields.
         *
         * @param mixed $field The arguments for creating the field. This is mostly the details of the sale price field, the dates inherit from the price field.
         * @return void
         */
        public function wholesale_sale_price_fields( $field ) {
            // Hide wholesale sale price fields if wholesale is disabled for this vendor.
            if ( ! $this->is_wholesale_enabled_for_vendor( get_current_user_id() ) ) {
                return;
            }

            $role_key = $field['role_key'];
            $product  = $field['product'];

            $sale_price_dates_from_timestamp = is_a( $product, 'WC_Product' ) ? $product->get_meta( $role_key . '_wholesale_sale_price_dates_from', true ) : '';
            $sale_price_dates_to_timestamp   = is_a( $product, 'WC_Product' ) ? $product->get_meta( $role_key . '_wholesale_sale_price_dates_to', true ) : '';

            $sale_price_dates_from = $sale_price_dates_from_timestamp ? date_i18n( 'Y-m-d H:i:s', $sale_price_dates_from_timestamp ) : '';
            $sale_price_dates_to   = $sale_price_dates_to_timestamp ? date_i18n( 'Y-m-d H:i:s', $sale_price_dates_to_timestamp ) : '';

            $field['dates_from'] = '' !== $sale_price_dates_from ? wc_string_to_datetime( $sale_price_dates_from ) : '';
            $field['dates_to']   = '' !== $sale_price_dates_to ? wc_string_to_datetime( $sale_price_dates_to ) : '';
            WWPP_WC_Vendors_Pro_Form_Helper::wholesale_sale_price_dates( $field );
        }

        /**
         * Wholesale role restrictions field
         *
         * @param int $product_id The product ID.
         * @return void
         */
        public function wholesale_roles_restrictions( $product_id ) {
            // Hide wholesale role restrictions if wholesale is disabled for this vendor.
            if ( ! $this->is_wholesale_enabled() || ! $this->is_wholesale_enabled_for_vendor( get_current_user_id() ) || ! $this->is_role_restrictions_enabled_for_vendors() ) {
                return;
            }

            ?>
            <div class="wholesale_restrictions">
                <h4><?php esc_html_e( 'Wholesale Role Restrictions', 'woocommerce-wholesale-prices-premium' ); ?></h4>
                <p><?php esc_html_e( 'Set this product to be visible only to specified wholesale user role/s only', 'woocommerce-wholesale-prices-premium' ); ?></p>
                <div class="wholesale_restrictions_roles">
                    <?php $this->wholesale_visibility_select( $product_id ); ?>
                </div>
            </div>
            <?php
        }

        /**
         * Wholesale exclusive variation roles
         *
         * @param mixed $loop The variation loop index.
         * @param int   $variation_id The variation id.
         *
         * @return void
         */
        public function wholesale_exclusive_variation( $loop, $variation_id ) {
            // Hide wholesale exclusive variation if wholesale is disabled for this vendor.
            if ( ! $this->is_wholesale_enabled() || ! $this->is_wholesale_enabled_for_vendor( get_current_user_id() ) || ! $this->is_role_restrictions_enabled_for_vendors() ) {
                return;
            }

            ?>
            <div class="wholesale_exclusive_variation">
                <h4><?php esc_html_e( 'Wholesale Exclusive Variation', 'woocommerce-wholesale-prices-premium' ); ?></h4>
                <p><?php esc_html_e( 'Specify if this variation should be exclusive to wholesale roles. Leave empty to make it available to all.', 'woocommerce-wholesale-prices-premium' ); ?></p>
                <div class="variation_wholesale_restrictions_roles">
                    <?php $this->wholesale_visibility_select( $variation_id, $loop ); ?>
                </div>
            </div>
            <?php
        }

        /**
         * Render pricing fields for a product
         *
         * @param WC_Product_Simple|WC_Product_Variable $product The product object. Should be a simple product or a variation.
         * @param array                                 $variation_args The variation arguments.
         *
         * @return void
         */
        public function render_pricing_fields( $product, $variation_args = array() ) {
            // Hide wholesale pricing fields if wholesale is disabled for this vendor.
            if ( ! $this->is_wholesale_enabled() || ! $this->is_wholesale_enabled_for_vendor( get_current_user_id() ) ) {
                return;
            }

            ?>
            <div class="wholesale-prices-fields">
                <h4><?php esc_html_e( 'Wholesale Prices', 'woocommerce-wholesale-prices-premium' ); ?></h4>
                <p><?php esc_html_e( 'Set a wholesale price for this product.', 'woocommerce-wholesale-prices-premium' ); ?></p>
            </div>

            <?php

            wp_nonce_field(
                'wwpp_save_vendor_product_wholesale_prices',
                'wwpp_save_vendor_product_wholesale_prices_nonce'
            );

            $vendor_allowed_roles = $this->get_vendor_allowed_roles();

            foreach ( $this->registered_roles as $role_key => $role ) {
                if ( ! in_array( $role_key, $vendor_allowed_roles, true ) ) {
                    continue;
                }
                $currency_symbol = get_woocommerce_currency_symbol();
                if ( array_key_exists( 'currency_symbol', $role ) && ! empty( $role['currency_symbol'] ) ) {
                    $currency_symbol = $role['currency_symbol'];
                }

                $wholesale_price      = $product ? $product->get_meta( $role_key . '_wholesale_price', true ) : null;
                $wholesale_sale_price = $product ? $product->get_meta( $role_key . '_wholesale_sale_price', true ) : null;

                /* translators: %1$s: currency symbol */
                $field_label = sprintf( __( 'Wholesale Price (%1$s)', 'woocommerce-wholesale-prices-premium' ), $currency_symbol );

                /* translators: %1$s: currency symbol */
                $sale_field_label = sprintf( __( 'Wholesale Sale Price (%1$s)', 'woocommerce-wholesale-prices-premium' ), $currency_symbol );

                $field_desc = sprintf(
                    /* translators: %1$s: Wholesale role name */
                    __( 'Wholesale price for %1$s customers', 'woocommerce-wholesale-prices-premium' ),
                    str_replace( array( 'Customer', 'Customers' ), '', $role['roleName'] )
                );
                $field_desc_fixed = $field_desc;

                $field_desc_percentage = sprintf(
                    /* translators: %1$s: Wholesale role name, %2$s: HTML tag (<br/>) */
                    __( 'Wholesale price for %1$s customers %2$s Note: Prices are shown up to 6 decimal places but may be calculated and stored at higher precision.', 'woocommerce-wholesale-prices-premium' ),
                    str_replace( array( 'Customer', 'Customers' ), '', $role['roleName'] ),
                    '<br/>'
                );

                // Percentage Discount.
                $wholesale_percentage_discount = $product ? $product->get_meta( $role_key . '_wholesale_percentage_discount', true ) : null;

                $discount_type = 'fixed';
                if ( $product ) {
                    $discount_type = metadata_exists( 'post', $product->get_id(), $role_key . '_wholesale_percentage_discount' ) ? 'percentage' : 'fixed';
                }

                if ( 'percentage' === $discount_type ) {
                    $field_desc = $field_desc_percentage;
                }

                $is_variation = isset( $variation_args['loop'] ) && isset( $variation_args['variation_id'] );
                $loop         = isset( $variation_args['loop'] ) ? $variation_args['loop'] : 0;
                $product_type = $is_variation ? 'variation' : 'simple';

                $discount_type_id        = $is_variation ? "{$role_key}_wholesale_discount_type[{$loop}]" : "{$role_key}_wholesale_discount_type";
                $percentage_discount_id  = $is_variation ? "{$role_key}_wholesale_percentage_discount[{$loop}]" : "{$role_key}_wholesale_percentage_discount";
                $wholesale_price_id      = $is_variation ? "{$role_key}_wholesale_price[{$loop}]" : "{$role_key}_wholesale_price";
                $wholesale_sale_price_id = $is_variation ? "{$role_key}_wholesale_sale_price[{$loop}]" : "{$role_key}_wholesale_sale_price";

                include WWPP_VIEWS_PATH . 'integrations/wc-vendors/wholesale-pricing-fields-form.php';
            }
        }

        /**
         * Wholesale exclusive product wholesale roles field.
         *
         * @param int   $product_id The product/variation ID.
         * @param mixed $loop The variation index. null for simple products.
         *
         * @return void
         */
        public function wholesale_visibility_select( $product_id, $loop = null ) {
            // Hide  wholesale role restrictions if wholesale is disabled for this vendor.
            if ( ! $this->is_wholesale_enabled() || ! $this->is_wholesale_enabled_for_vendor( get_current_user_id() ) ) {
                return;
            }

            $product  = wc_get_product( $product_id );
            $field_id = is_numeric( $loop ) ? "wholesale_visibility_select[{$loop}]" : 'wholesale_visibility_select';

            // Get and format the saved value.
            $visibility_filters = is_a( $product, 'WC_Product' ) ? $product->get_meta( WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER, false ) : array();

            $field_value = array();
            foreach ( $visibility_filters as $meta ) {
                $meta_data     = $meta->get_data();
                $field_value[] = $meta_data['value'];
            }

            // Get and format the allowed roles.
            $roles_keys    = $this->get_vendor_allowed_roles();
            $roles_options = $this->role_keys_to_options( array_values( $roles_keys ) );

            WCVendors_Pro_Form_Helper::select(
                array(
                    'id'                => $field_id,
                    'multiple'          => true,
                    'class'             => 'select2',
                    'label'             => 'Wholesale customer roles',
                    'desc_tip'          => true,
                    'description'       => __( 'This product will be visible to customers with the selected wholesale roles.', 'woocommerce-wholesale-prices-premium' ),
                    'options'           => $roles_options,
                    'default'           => '',
                    'value'             => $field_value,
                    'wrapper_start'     => '<div class="all-100 control">',
                    'wrapper_end'       => '</div>',
                    'custom_attributes' => array(
                        'multiple' => true,
                    ),
                )
            );
        }

        /**
         * Save the wholesale prices for a product.
         *
         * @since 2.0.3
         * @access public
         *
         * @param int                                    $product_id The product ID.
         * @param WC_Product_Simple|WC_Product_Variation $product The product object.
         * @return void
         */
        public function save_product_meta( $product_id, $product ) {
            if ( $product->get_type() !== 'simple' || ! is_user_logged_in() ) {
                return;
            }

            if ( empty( $_POST['wwpp_save_vendor_product_wholesale_prices_nonce'] ) || ! wp_verify_nonce( $_POST['wwpp_save_vendor_product_wholesale_prices_nonce'], 'wwpp_save_vendor_product_wholesale_prices' ) ) {
                return;
            }

            foreach ( $this->registered_roles as $role_key => $role ) {
                $this->save_wholesale_role_price_meta(
                    $product,
                    $role_key
                );
            }

            $this->save_product_wholesale_visibility_meta( $product );

            $product->save();
        }

        /**
         * Save the wholesale prices for a variation.
         *
         * @param mixed $variation_id The variation ID.
         * @param mixed $loop         The loop index.
         *
         * @since  2.0.3
         * @access public
         *
         * @return void
         */
        public function save_variation( $variation_id, $loop ) {
            $product = wc_get_product( $variation_id );
            if ( ! is_a( $product, 'WC_Product' ) || $product->get_type() !== 'variation' || ! is_user_logged_in() ) {
                return;
            }

            if ( empty( $_POST['wwpp_save_vendor_product_wholesale_prices_nonce'] ) || ! wp_verify_nonce( $_POST['wwpp_save_vendor_product_wholesale_prices_nonce'], 'wwpp_save_vendor_product_wholesale_prices' ) ) {
                return;
            }

            $is_variation = is_numeric( $loop );

            if ( ! $is_variation ) {
                return;
            }

            $variable_product = wc_get_product( $product->get_parent_id() );

            foreach ( $this->get_vendor_allowed_roles() as $role_key ) {

                $this->save_wholesale_role_price_meta(
                    $product,
                    $role_key,
                    $loop
                );

                $this->save_product_wholesale_visibility_meta( $product, $loop );

                $wholesale_price = $this->get_posted_data( "{$role_key}_wholesale_price", $loop, null );
                if ( $wholesale_price ) {
                    $variable_product->add_meta_data( "{$role_key}_variations_with_wholesale_price", $product->get_id() );
                }

                $wholesale_sale_price = $this->get_posted_data( "{$role_key}_wholesale_sale_price", $loop, null );
                if ( $wholesale_sale_price ) {
                    $variable_product->update_meta_data( "{$role_key}_variations_have_wholesale_sale_price", 'yes' );
                }
            }

            $variable_product->save();

            $product->save();
        }

        /**
         * Save the wholesale role price meta.
         *
         * @since 2.0.3
         * @access public
         *
         * @param int    $variation_id The variation ID.
         * @param string $role_key The wholesale role key.
         * @param int    $loop The variation loop index.
         * @return void
         */
        public function save_variation_additional_meta( $variation_id, $role_key, $loop ) {

            $product = wc_get_product( $variation_id );

            if ( ! $product ) {
                return;
            }

            $this->save_wholesale_role_price_meta( $product, $role_key, $loop );
        }

        /**
         * Save the wholesale role price meta.
         *
         * @since 2.0.3
         * @access public
         *
         * @param WC_Product_Simple|WC_Product_Variation $product  The product to save the meta for.
         * @param string                                 $role_key The wholesale role key.
         * @param null|number                            $loop The variation loop index. Null if not variation.
         * @return void
         */
        public function save_wholesale_role_price_meta( $product, $role_key, $loop = null ) {
            if ( ! is_a( $product, 'WC_Product' ) ) {
                return;
            }

            $wholesale_price               = $this->get_posted_data( "{$role_key}_wholesale_price", $loop );
            $wholesale_percentage_discount = $this->get_posted_data( "{$role_key}_wholesale_percentage_discount", $loop );
            $discount_type                 = $this->get_posted_data( "{$role_key}_wholesale_discount_type", $loop );
            $wholesale_sale_price          = $this->get_posted_data( "{$role_key}_wholesale_sale_price", $loop );
            $sale_price_dates_from         = $this->get_posted_data( "{$role_key}_wholesale_sale_price_dates_from", $loop );
            $sale_price_dates_to           = $this->get_posted_data( "{$role_key}_wholesale_sale_price_dates_to", $loop );

            if ( ! $wholesale_price ) {
                $product->update_meta_data( "{$role_key}_have_wholesale_price", 'no' );
            }

            if ( ! $wholesale_percentage_discount ) {
                $product->update_meta_data( "{$role_key}_have_wholesale_sale_price", 'no' );
            }

            if ( 'percentage' === $discount_type ) {
                $product->update_meta_data( "{$role_key}_wholesale_percentage_discount", $wholesale_percentage_discount );
            } else {
                $product->delete_meta_data( "{$role_key}_wholesale_percentage_discount" );
            }

            if ( $wholesale_price ) {
                $product->update_meta_data( "{$role_key}_wholesale_price", $wholesale_price );
            }

            if ( $wholesale_sale_price ) {
                $product->update_meta_data( "{$role_key}_wholesale_sale_price", $wholesale_sale_price );
            }

            if ( $sale_price_dates_from ) {
                $product->update_meta_data( "{$role_key}_wholesale_sale_price_dates_from", $sale_price_dates_from );
            }

            if ( $sale_price_dates_to ) {
                $product->update_meta_data( "{$role_key}_wholesale_sale_price_dates_to", $sale_price_dates_to );
            }

            $product->update_meta_data( "{$role_key}_wholesale_discount_type", $discount_type );

            if ( $wholesale_price || $wholesale_percentage_discount ) {
                $product->update_meta_data( "{$role_key}_have_wholesale_price", 'yes' );
            } else {
                $product->update_meta_data( "{$role_key}_have_wholesale_price", 'no' );
            }

            if ( $wholesale_sale_price ) {
                $product->update_meta_data( "{$role_key}_have_wholesale_sale_price", 'yes' );
            } else {
                $product->update_meta_data( "{$role_key}_have_wholesale_sale_price", 'no' );
            }
        }

        /**
         * Filter cart item price.
         *
         * Checks if wholesale is disabled for the vendor and return the product's original price, or the wholesale price otherwise.
         *
         * @param string $price The cart item price.
         * @param string $cart_item The cart item.
         *
         * @return string $price The cart item price.
         */
        public function filter_cart_item_price( $price, $cart_item ) {
            $product_id = ( ! empty( $cart_item['variation_id'] ) ) ? $cart_item['variation_id'] : $cart_item['product_id'];

            $product = wc_get_product( $product_id );

            $customer = WC()->customer;

            if ( ! is_admin() && $customer ) {
                $customer_role = $customer->get_role();

                if ( ! in_array( $customer_role, $this->get_vendor_allowed_roles(), true ) ) {
                    return wc_price( $product->get_price() );
                }
            }

            $vendor_id = WCV_Vendors::get_vendor_from_product( $product_id );

            if ( ! $this->is_wholesale_enabled_for_vendor( $vendor_id ) || ! $this->is_wholesale_enabled() ) {
                return wc_price( $product->get_price() );
            }

            return $price;
        }

        /**
         * Filter the raw wholesale price.
         *
         * Checks if wholesale is disabled for the vendor and returns the original product price.
         *
         * @param string     $wholesale_price The current wholesale price for the product.
         * @param WC_Product $product    The product object.
         * @return string
         */
        public function filter_raw_wholesale_price( $wholesale_price, $product ) {
            $vendor_id = WCV_Vendors::get_vendor_from_product( $product->get_id() );

            $customer = WC()->customer;

            if ( ! is_admin() && $customer ) {
                $customer_role = $customer->get_role();

                if ( ! in_array( $customer_role, $this->get_vendor_allowed_roles(), true ) ) {
                    return $product->get_price();
                }
            }

            if ( ! $this->is_wholesale_enabled_for_vendor( $vendor_id ) || ! $this->is_wholesale_enabled() ) {
                return $product->get_price();
            }

            return $wholesale_price;
        }

        /**
         * Filter vendor order item wholesale priced meta.
         *
         * Checks if wholesale is disabled for the vendor, and sets the wholesale priced meta to no.
         *
         * @param string     $wholesale_priced Is item wholesale priced. yes|no.
         * @param WC_Product $product The product object.
         * @return mixed
         */
        public function filter_order_item_wholesale_priced( $wholesale_priced, $product ) {
            $vendor_id = WCV_Vendors::get_vendor_from_product( $product->get_id() );

            if ( ! $this->is_wholesale_enabled_for_vendor( $vendor_id ) || ! $this->is_wholesale_enabled() ) {
                $wholesale_priced = 'no';
            }

            return $wholesale_priced;
        }

        /**
         * Dynamically force the loading of the popup scripts.
         *
         * This filter allows us to force loading the wholesale price popup script on certain pages.
         *
         * @param boolean $load_scripts Whether to load the scripts or not.
         * @param string  $context The context where this filter is applied.
         * @return boolean Whether the scripts should be loaded or not.
         */
        public function filter_load_scripts( $load_scripts, $context ) {
            if ( 'scripts' !== $context || ! $this->is_wholesale_enabled() ) {
                return $load_scripts;
            }

            $vendor_id = $this->get_vendor_id();

            if ( $this->is_vendor( $vendor_id ) && $this->is_wholesale_enabled_for_vendor( $vendor_id ) ) {
                return true;
            }

            if ( is_product() || is_shop() || WCV_Vendors::is_vendor_page() ) {
                return true;
            }

            return $load_scripts;
        }

        /**
         * Hide wholesale pricing fields if wholesale is disabled for current vendor.
         *
         * @param boolean $show Whether to show wholesale pricing fields.
         *
         * @return boolean
         */
        public function editor_show_wholesale_prices_fields( $show ) {
            $vendor_id = get_current_user_id();

            if ( ! WCV_Vendors::is_vendor( $vendor_id ) ) {
                return $show;
            }

            if ( ! $this->is_wholesale_enabled_for_vendor( $vendor_id ) || ! $this->is_wholesale_enabled() ) {
                return false; // Return false to hide wholesale pricing fields.
            }

            return $show;
        }

        /**
         * Disable quantity based wholesale pricing on vendor product editor in WP Admin page.
         *
         * @param boolean $show Wether to show quantity based wholesale pricing.
         *
         * @return boolean
         */
        public function editor_show_order_quantity_fields( $show ) {
            $vendor_id = get_current_user_id();

            /**
             * Always return false for vendors irregardless of whether wholesale is enabled for the vendor or not.
             * This is because quantity based wholesale pricing is not supported for vendors at the moment.
             */
            if ( WCV_Vendors::is_vendor( $vendor_id ) ) {
                $show = false;
            }

            return $show;
        }

        /**
         * Filter to show or hide wholesale price column content.
         *
         * Hide wholesale price column content if wholesale is disabled for specific vendors.
         * Hide for all vendors if vendor wholesale is disabled in the marketplace.
         *
         * @param boolean $show Whether to show or hide wholesale price column content.
         * @param int     $product_id The id of the product.
         *
         * @return boolean Whether to show or hide wholesale price column content.
         */
        public function filter_show_wholesale_price_column_value( $show, $product_id ) {
            $vendor_id = WCV_Vendors::get_vendor_from_product( $product_id );

            if ( ! WCV_Vendors::is_vendor( $vendor_id ) ) {
                return $show;
            }

            if ( ! $this->is_wholesale_enabled_for_vendor( $vendor_id ) || ! $this->is_wholesale_enabled() ) {
                return false; // Return false to hide wholesale pricing fields.
            }

            return $show;
        }

        /**
         * Maybe hide wholesale role restrictions for vendors
         *
         * Checks if role restrictions is enabled for vendors then hides the visibility filter field, or show it otherwise
         *
         * @param mixed $show Whether to show the wholesale visibility filter field.
         * @return mixed
         */
        public function maybe_hide_wholesale_visibility_filter_field( $show ) {
            $vendor_id = get_current_user_id();

            $vendor_wholesale_enabled  = $this->is_wholesale_enabled_for_vendor( $vendor_id );
            $role_restrictions_enabled = $this->is_role_restrictions_enabled_for_vendors();
            $is_vendor                 = WCV_Vendors::is_vendor( $vendor_id );

            if ( ! $this->is_wholesale_enabled() || ( $is_vendor && ( ! $vendor_wholesale_enabled || ! $role_restrictions_enabled ) ) ) {
                $show = false;
            }

            return $show;
        }

        /**
         * Filter the registered wholesale roles
         *
         * For vendors, only return the vendor allowed wholesale roles.
         *
         * @param array $registered_roles The registered wholesale roles.
         * @return array $filtered_roles The filtered roles.
         */
        public function filter_registered_wholesale_roles( $registered_roles ) {
            if ( ! class_exists( 'WCV_Vendors' ) ) {
                return $registered_roles;
            }

            // Return all roles for administrator.
            if ( is_user_logged_in() && current_user_can( 'activate_plugins' ) ) {
                return $registered_roles;
            }

            $vendor_id = $this->get_vendor_id();

            if ( ! $this->is_vendor( $vendor_id ) ) {
                return $registered_roles;
            }

            $allowed_roles = array_filter( get_option( 'wwpp_roles_visible_to_vendors', array() ) );

            if ( empty( $allowed_roles ) ) {
                return $registered_roles;
            }

            $filtered_roles = array_filter(
                $registered_roles,
                function ( $role, $role_key ) use ( $allowed_roles ) { // phpcs:ignore.
                    return in_array( $role_key, $allowed_roles, true );
                },
                ARRAY_FILTER_USE_BOTH
            );

            return $filtered_roles;
        }

        /**
         * Filter product non wholesale role options
         *
         * @param array $role_options The role options.
         * @param int   $product_id The product ID.
         * @return mixed
         */
        public function filter_non_wholesale_role_options( $role_options, $product_id ) {
            $vendor_id = WCV_Vendors::get_vendor_from_product( $product_id );

            $post_author = get_post_field( 'post_author', $product_id );

            if ( $post_author !== $vendor_id || ! WCV_Vendors::is_vendor( $vendor_id ) ) {
                return $role_options;
            }

            $vendor_non_wholesale_roles = get_user_meta( $vendor_id, 'wwp_non_wholesale_wholesale_role_select2', true );
            $vendor_show_prices         = get_user_meta( $vendor_id, 'wwp_prices_settings_show_wholesale_prices_to_non_wholesale', true );

            // If the vendor has not changed the settings.
            if ( ! $vendor_non_wholesale_roles && ! $vendor_show_prices ) {
                $vendor_non_wholesale_roles = $this->get_vendor_allowed_roles();
            } else {
                $vendor_non_wholesale_roles = explode( ',', $vendor_non_wholesale_roles );
            }

            $filtered_roles = array_filter(
                $role_options,
                function ( $role_key, $index ) use ( $vendor_non_wholesale_roles ) { // phpcs:ignore.
                    return in_array( $role_key, $vendor_non_wholesale_roles, true );
                },
                ARRAY_FILTER_USE_BOTH
            );

            return $filtered_roles;
        }

        /**
         * Filter related products
         *
         * Hide related products if they don't belong to the vendor of the current product.
         *
         * @param int[] $related_posts List of related products.
         * @param int   $product_id The ID of the product.
         * @return int[]
         */
        public function filter_ralated_products( $related_posts, $product_id ) {

            /**
             * Allow to filter vendor's related products in single product page.
             *
             * @param bool  $allow_filter Whether to allow filtering vendor's related products. Default true.
             * @param int[] $related_posts List of related products.
             * @param int   $product_id The ID of the product.
             *
             * @return bool $allow_filter Whether to allow filtering vendor's related products
             */
            if ( ! apply_filters( 'wwpp_filter_related_vendor_products', true, $related_posts, $product_id ) ) {
                return $related_posts;
            }

            $product = wc_get_product( $product_id );

            if ( ! $product ) {
                return $related_posts;
            }

            $product_author = get_post_field( 'post_author', $product_id );

            $filtered_related_posts = array();
            foreach ( $related_posts as $post_id ) {
                $post_author = get_post_field( 'post_author', $post_id );

                if ( $post_author !== $product_author ) {
                    continue;
                }

                $filtered_related_posts[] = $post_id;
            }

            return $filtered_related_posts;
        }

        /**
         * Update vendor item wholesale priced meta when meta was added.
         *
         * @param mixed $item The order item object.
         * @return void
         */
        public function update_order_item_meta( $item ) {
            $vendor_id = WCV_Vendors::get_vendor_from_product( $item->get_product_id() );

            if ( ! $this->is_wholesale_enabled_for_vendor( $vendor_id ) || ! $this->is_wholesale_enabled() ) {
                $item->update_meta_data( '_wwp_wholesale_priced', 'no' );
            }
        }

        /**
         * Save the wholesale visibility filter for the product
         *
         * @param WC_Product_Simple|WC_Product_Variation $product The product object.
         * @param mixed                                  $loop The loop index if $product is a variation.
         * @return void
         */
        public function save_product_wholesale_visibility_meta( $product, $loop = null ) {
            $wholesale_visibility_filters = $this->get_posted_data( 'wholesale_visibility_select', $loop, array() );

            // Reset before saving.
            $product->delete_meta_data( WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER );

            // Reset the product's wholesale roles restrictions.
            if ( empty( $wholesale_visibility_filters ) ) {
                $product->add_meta_data( WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER, 'all' );
                return;
            }

            if ( is_array( $wholesale_visibility_filters ) ) {
                foreach ( $wholesale_visibility_filters as $filter_role ) {
                    $product->add_meta_data( WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER, $filter_role );
                }
            } else {
                $product->add_meta_data( WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER, 'all' );
            }
        }

        /**
         * Get the posted data by key from global $_POST
         *
         * @param mixed $key     The array key.
         * @param mixed $loop    The loop index for product variations.
         * @param mixed $default_value The default value to return if nothing found.
         * @return mixed
         */
        public function get_posted_data( $key, $loop = null, $default_value = '' ) {
            $is_variation = ! is_null( $loop ) && is_numeric( $loop ) ? true : false;

            if ( ! isset( $_POST['_wcv-save_product'] ) || ! wp_verify_nonce( $_POST['_wcv-save_product'], 'wcv-save_product' ) || ! is_user_logged_in() ) {
                return;
            }

            if ( $is_variation ) {
                return isset( $_POST[ $key ][ $loop ] ) ? $_POST[ $key ][ $loop ] : $default_value;
            }

            return isset( $_POST[ $key ] ) ? $_POST[ $key ] : $default_value;
        }

        /**
         * Vendor settings tab content.
         *
         * @since 2.0.3
         * @access public
         *
         * @return void
         */
        public function vendor_settings_tab_content() {

            $vendor_id = get_current_user_id();

            if ( ! $this->is_wholesale_enabled_for_vendor( $vendor_id ) ) {
                return;
            }

            $registered_wholesale_roles = $this->registered_roles;

            $allowed_roles = $this->get_vendor_allowed_roles();

            $allowed_roles = ! empty( $allowed_roles ) ? array_values( $allowed_roles ) : $registered_wholesale_roles;

            $allowed_options = $this->role_keys_to_options( $allowed_roles );

            // Global setting to show wholesale price to non wholesale users.
            $show_prices_to_non_wholesale_users = get_option( 'wwp_prices_settings_show_wholesale_prices_to_non_wholesale' );

            /**
             * Currently saved vendor wholesale roles
             */
            $roles_value = explode( ',', get_user_meta( $vendor_id, 'wwp_non_wholesale_wholesale_role_select2', true ) );

            require_once WWPP_VIEWS_PATH . 'integrations/wc-vendors/vendor-wholesale-settings.php';
        }

        /**
         * Get roles visible to vendors.
         *
         * @return mixed
         */
        public function get_vendor_allowed_roles() {

            $allowed_roles = get_option(
                'wwpp_roles_visible_to_vendors',
                array()
            );

            return apply_filters(
                'wwp_wholesale_roles_visible_to_vendors',
                $allowed_roles
            );
        }

        /**
         * Format wholesale role keys array to role_key & roleName to use as options.
         *
         * @since 2.0.3
         * @access public
         *
         * @param mixed $role_keys The list of wholesale role keys.
         * @return array
         */
        public function role_keys_to_options( $role_keys ) {
            $registered_roles = $this->registered_roles;

            $options = array();
            foreach ( $role_keys as $role_key ) {
                if ( array_key_exists( $role_key, $registered_roles ) ) {
                    $options[ $role_key ] = $registered_roles[ $role_key ]['roleName'];
                }
            }

            return $options;
        }

        /**
         * Check if the vendor is enabled for wholesale.
         *
         * @since 2.0.3
         * @access public
         *
         * @param mixed $vendor_id The vendor ID.
         * @return bool
         */
        public function is_wholesale_enabled_for_vendor( $vendor_id ) {
            $vendor_id = absint( $vendor_id );

            if ( empty( $vendor_id ) ) {
                return false;
            }

            $wholesale_enabled = get_user_meta( $vendor_id, '_wcv_wholesale_enabled', true );

            // Return false for invalid results.
            if ( '' === $wholesale_enabled || false === $wholesale_enabled ) {
                return false;
            }

            return wc_string_to_bool( $wholesale_enabled );
        }

        /**
         * Check if wholesale role restrictions is enabled for vendors.
         *
         * @since 2.0.3
         * @access public
         *
         * @return bool
         */
        public function is_role_restrictions_enabled_for_vendors() {

            $role_restrictions_enabled = get_option( 'wwpp_allow_vendors_to_restrict_product_visibility', 'no' );

            return 'yes' === $role_restrictions_enabled ? true : false;
        }

        /**
         * Override option to show vendor prices
         *
         * @param string $show_prices Whether to show wholesale prices on vendor prices.
         *
         * @since 2.0.3
         * @access public
         *
         * @return string yes|no
         */
        public function override_show_wholesale_prices( $show_prices ) {
            global $post;

            $product = wc_get_product( $post );

            if ( 'no' === $show_prices ) {
                return $show_prices;
            }

            if ( is_product() && ! $this->product_has_wholesale_price( $product ) ) {
                return 'no';
            }

            if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
                return $show_prices;
            }

            $vendor_id = WCV_Vendors::get_vendor_from_product( $product->get_id() );

            if ( ! WCV_Vendors::is_vendor( $vendor_id ) ) {
                return $show_prices;
            }

            if ( $post->post_author === $vendor_id && WCV_Vendors::is_vendor( $vendor_id ) && ! $this->is_wholesale_enabled_for_vendor( $vendor_id ) ) {
                return 'no';
            }

            $vendor_show_prices = get_user_meta( $vendor_id, 'wwp_prices_settings_show_wholesale_prices_to_non_wholesale', true );

            if ( ! $vendor_show_prices || '' === $vendor_show_prices ) {
                return 'no';
            }

            /**
             * Allow changing the setting to show wholesale prices to non wholesale customer for a vendor.
             *
             * @param string             $vendor_show_prices Whether to show wholesale prices or not. string, yes|no.
             * @param int                $vendor_id The ID of the vendor this setting applies to.
             * @param WP_Post|WC_Product $product The product object.
             *
             * @since 2.0.3
             *
             * @return string
             */
            return apply_filters(
                'wwpp_vendor_show_wholesale_prices_to_non_wholesale_customers',
                $vendor_show_prices,
                $vendor_id,
                $product
            );
        }

        /**
         * Filter show wholesale prices for vendor prroducts
         *
         * @param string     $show_prices Whether to show wholesale prices on vendor prices.
         * @param WC_Product $product The wholesale product.
         *
         * @since 2.0.3
         * @access public
         *
         * @return string yes|no
         */
        public function filter_show_wholesale_prices( $show_prices, $product ) {
            global $post;

            if ( 'no' === $show_prices ) {
                return $show_prices;
            }

            if ( ! is_a( $product, 'WC_Product' ) ) {
                $product = wc_get_product( $product );
            }

            if ( is_product() && ! $this->product_has_wholesale_price( $product ) ) {
                return 'no';
            }

            if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
                return $show_prices;
            }

            $vendor_id = WCV_Vendors::get_vendor_from_product( $product->get_id() );

            if ( ! WCV_Vendors::is_vendor( $vendor_id ) ) {
                return $show_prices;
            }

            if ( ( $post && $post->post_author === $vendor_id ) && WCV_Vendors::is_vendor( $vendor_id ) && ! $this->is_wholesale_enabled_for_vendor( $vendor_id ) ) {
                return 'no';
            }

            $vendor_show_prices = get_user_meta( $vendor_id, 'wwp_prices_settings_show_wholesale_prices_to_non_wholesale', true );

            if ( ! $vendor_show_prices || '' === $vendor_show_prices ) {
                return 'no'; // It's no by default if vendor has not changed.
            }

            /**
             * Allow changing the setting to show wholesale prices to non wholesale customer for a vendor.
             *
             * @param string             $vendor_show_prices Whether to show wholesale prices or not. string, yes|no.
             * @param int                $vendor_id The ID of the vendor this setting applies to.
             * @param WP_Post|WC_Product $product The product object.
             *
             * @since 2.0.3
             *
             * @return string
             */
            return apply_filters(
                'wwpp_vendor_show_wholesale_prices_to_non_wholesale_customers',
                $vendor_show_prices,
                $vendor_id,
                $product
            );
        }

        /**
         * Override the show in products option.
         *
         * @param mixed $show_in_products Whether wholesale prices should be shown in product pages or not.
         *
         * @return string yes|no
         */
        public function override_show_in_products( $show_in_products ) {
            global $post;

            $product = wc_get_product( $post );

            if ( ! $product ) {
                return $show_in_products;
            }

            if ( ! $this->product_has_wholesale_price( $product ) ) {
                return 'no';
            }

            $vendor_id = WCV_Vendors::get_vendor_from_product( $product->get_id() );

            if ( $post->post_author === $vendor_id && WCV_Vendors::is_vendor( $vendor_id ) && ! $this->is_wholesale_enabled_for_vendor( $vendor_id ) ) {
                return $show_in_products;
            }

            $vendor_show_in_products = get_user_meta( $vendor_id, 'wwp_non_wholesale_show_in_products', true );

            if ( ! $vendor_show_in_products || '' === $vendor_show_in_products ) {
                return $show_in_products;
            }

            $customer = WC()->customer;

            $show_to_non_wholesale = get_user_meta( $vendor_id, 'wwp_prices_settings_show_wholesale_prices_to_non_wholesale', true );

            if ( $customer && is_user_logged_in() ) {
                $customer_role = $customer->get_role();

                $admin_roles         = array( 'administrator', 'vendor' );
                $is_admin_user       = in_array( $customer_role, $admin_roles, true );
                $is_allowed_customer = in_array( $customer_role, $this->get_vendor_allowed_roles(), true );
                if ( 'no' === $show_to_non_wholesale && ! $is_allowed_customer && ! $is_admin_user ) {
                    return 'no';
                }
            }

            return $vendor_show_in_products;
        }

        /**
         * Override the setting to show wholesale prices on shop page for vendor.
         *
         * @param mixed              $show_in_shop Whether to show wholesale prices in vendor shop page.
         * @param WP_Post|WC_Product $product The WP_Post or WC_Product object.
         *
         * @since 2.0.3
         * @access public
         *
         * @return string yes|no
         */
        public function override_show_in_shop( $show_in_shop, $product ) {
            if ( ! WCV_Vendors::is_vendor_page() && ! is_shop() && ! is_archive() ) {
                return $show_in_shop;
            }

            if ( ! is_a( $product, 'WC_Product' ) ) {
                $product = wc_get_product( $product );
            }

            if ( ! $this->product_has_wholesale_price( $product ) ) {
                return 'no';
            }

            $vendor_id = $product ? WCV_Vendors::get_vendor_from_product( $product->get_id() ) : $this->get_vendor_id();

            if ( is_shop() && ! WCV_Vendors::is_vendor( $vendor_id ) ) {
                return $show_in_shop;
            }

            if ( WCV_Vendors::is_vendor( $vendor_id ) && ! $this->is_wholesale_enabled_for_vendor( $vendor_id ) ) {
                return 'no';
            }

            $vendor_show_in_shop = get_user_meta( $vendor_id, 'wwp_non_wholesale_show_in_shop', true );

            if ( ! $vendor_show_in_shop || '' === $vendor_show_in_shop ) {
                return $show_in_shop;
            }

            /**
             * Allow the setting to show wholesale prices in vendor's shop page.
             *
             * @param string $vendor_show_in_shop The current setting whether to show wholesale prices in shop or not. String yes|no
             * @param int    $vendor_id The ID of the vendor this setting applies to.
             *
             * @since 2.0.3
             *
             * @return string
             */
            return apply_filters(
                'wwpp_vendor_show_wholesale_prices_in_shop_page',
                $vendor_show_in_shop,
                $vendor_id,
            );
        }

        /**
         * Change the wholesale price HTML on vendor products
         *
         * @param string     $wholesale_price_html The HTML showing the wholesale price on the product page.
         * @param string     $original_price       The product's original price.
         * @param WC_Product $product The product object.
         *
         * @since 2.0.3
         * @access public
         *
         * @return string
         */
        public function wholesale_price_html( $wholesale_price_html, $original_price, $product ) {
            if ( ! $product ) {
                return $wholesale_price_html;
            }

            if ( ! $this->product_has_wholesale_price( $product ) ) {
                return $original_price;
            }

            $vendor_id = WCV_Vendors::get_vendor_from_product( $product->get_id() );

            if ( ! WCV_Vendors::is_vendor( $vendor_id ) ) {
                return $wholesale_price_html;
            }

            if ( ! $this->is_wholesale_enabled_for_vendor( $vendor_id ) || ! ( WCV_Vendors::is_vendor( $vendor_id ) && $this->is_wholesale_enabled() ) ) {
                return $original_price;
            }

            $vendor_show_in_products = get_user_meta( $vendor_id, 'wwp_non_wholesale_show_in_products', true );

            // Set the wholesale price HTML to empty for this vendor.
            if ( $vendor_show_in_products && ! wc_string_to_bool( $vendor_show_in_products ) ) {
                $wholesale_price_html = '';
            }

            /**
             * Allow changing the setting to show wholesale prices on vendor's single product page.
             *
             * @param string $vendor_show_in_products Whether to show wholesale prices on the product page for this vendor.
             * @param int    $vendor_id The ID of the vendor this setting applies to.
             *
             * @since 2.0.3
             *
             * @return string
             */
            return apply_filters(
                'wwpp_filter_vendor_product_wholesale_price_html',
                $wholesale_price_html,
                $product,
                $vendor_id
            );
        }

        /**
         * Change the wholesale price HTML on vendor products
         *
         * @param string     $wholesale_sale_price_html The HTML showing the wholesale price on the product page.
         * @param WC_Product $product                        Product object.
         * @param array      $user_wholesale_role            Array of user wholesale roles.
         * @param string     $wholesale_price_title_text     Wholesale price title text.
         * @param string     $original_price                 Original product price.
         *
         * @since 2.0.3
         * @access public
         *
         * @return string
         */
        public function wholesale_sale_price_html( $wholesale_sale_price_html, $product, $user_wholesale_role, $wholesale_price_title_text, $original_price ) {
            if ( ! $product ) {
                return $wholesale_sale_price_html;
            }

            if ( ! $this->product_has_wholesale_price( $product ) ) {
                return $original_price;
            }

            $vendor_id = WCV_Vendors::get_vendor_from_product( $product->get_id() );

            if ( ! WCV_Vendors::is_vendor( $vendor_id ) ) {
                return $wholesale_sale_price_html;
            }

            if ( ! $this->is_wholesale_enabled_for_vendor( $vendor_id ) || ! ( WCV_Vendors::is_vendor( $vendor_id ) && $this->is_wholesale_enabled() ) ) {
                return $original_price;
            }

            $vendor_show_in_products = get_user_meta( $vendor_id, 'wwp_non_wholesale_show_in_products', true );

            // Set the wholesale price HTML to empty for this vendor.
            if ( $vendor_show_in_products && ! wc_string_to_bool( $vendor_show_in_products ) ) {
                $wholesale_sale_price_html = '';
            }

            /**
             * Allow changing the setting to show wholesale prices on vendor's single product page.
             *
             * @param string $vendor_show_in_products Whether to show wholesale prices on the product page for this vendor.
             * @param int    $vendor_id The ID of the vendor this setting applies to.
             *
             * @since 2.0.3
             *
             * @return string
             */
            return apply_filters(
                'wwpp_filter_vendor_product_wholesale_sale_price_html',
                $wholesale_sale_price_html,
                $product,
                $vendor_id
            );
        }

        /**
         * Check if a product has wholesale prices in any of the wholesale roles.
         *
         * If the product is a variation and has no wholesale prices, check its parent.
         *
         * @param mixed $product The product object, post object or array defining the product.
         *
         * @since 2.0.3
         *
         * @return bool $has_wholesale_product True if at least one of the roles have a wholesale price, false otherwise.
         */
        public function product_has_wholesale_price( $product ) {
            if ( ! $product ) {
                return false;
            }

            if ( ! is_a( $product, 'WC_Product' ) ) {
                $product = wc_get_product( $product );
            }

            if ( ! $product ) {
                return false;
            }

            $allowed_roles = $this->get_vendor_allowed_roles();

            foreach ( $allowed_roles as $role_key ) {
                if ( 'variable' === $product->get_type() ) {
                    $variations_with_wholesale_price = WWP_Helper_Functions::get_formatted_meta_data( $product, $role_key . '_variations_with_wholesale_price' );

                    if ( ! empty( $variations_with_wholesale_price ) ) {
                        return true;
                    }
                } elseif ( 'variation' === $product->get_type() ) {
                    $parent_product           = wc_get_product( $product->get_parent_id() ); // Only retrieve parent if necessary.
                    $variation_role_has_price = $parent_product ? $parent_product->get_meta( "{$role_key}_have_wholesale_price" ) : '';

                    if ( 'yes' === $variation_role_has_price ) {
                        return true;
                    }
                } elseif ( 'simple' === $product->get_type() ) {

                    // If the meta is invalid or not set, check the parent product.
                    $role_has_price = $product->get_meta( "{$role_key}_have_wholesale_price" );

                    // Wholesale role has price if set to yes.
                    if ( 'yes' === $role_has_price ) {
                        return true;
                    }
                }

                $prod_have_wholesale_price_set_by_product_cat = $product->get_meta( $role_key . '_have_wholesale_price_set_by_product_cat', true );

                if ( 'yes' === $prod_have_wholesale_price_set_by_product_cat ) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Print wholesale order item meta.
         *
         * @param int $item_id Order item id.
         *
         * @since 2.0.3
         * @return void
         */
        public function order_item_meta( $item_id ) {
            $item_wholesale_role   = wc_get_order_item_meta( $item_id, '_wwp_wholesale_role', true );
            $item_wholesale_priced = wc_get_order_item_meta( $item_id, '_wwp_wholesale_priced', true );

            if ( 'yes' !== $item_wholesale_priced ) {
                return;
            }

            if ( ! $this->is_wholesale_enabled_for_vendor( $this->get_vendor_id() ) ) {
                return;
            }

            ob_start();

            ?>
            <ul class="wc-item-meta wwpp-wholesale-item-meta">
                <li class="wholesale-priced">
                    <strong class="wc-item-meta-label wholesale-priced-label"><?php esc_html_e( 'Wholesale Priced:', 'woocommerce-wholesale-prices-premium' ); ?></strong>
                    <p class="wholesale-priced-value"><?php echo esc_html( ucfirst( $item_wholesale_priced ) ); ?></p>
                </li>
                <li class="wholesale-priced-role">
                    <strong><?php esc_html_e( 'Wholesale Role:', 'woocommerce-wholesale-prices-premium' ); ?></strong>
                    <p class="wholesale-role"><?php echo esc_html( $item_wholesale_role ); ?></p>
                </li>
            </ul>
            <?php

            echo wp_kses_post( ob_get_clean() );
        }

        /**
         * Render oder item meta on vendor order table
         *
         * @param mixed $products_html The products html.
         * @param mixed $item_id The order item id.
         * @return string
         */
        public function order_table_item_meta( $products_html, $item_id ) {
            ob_start();
            $this->order_item_meta( $item_id );
            $item_html = ob_get_clean();

            $products_html .= $item_html;

            return $products_html;
        }

        /**
         * Render order item meta on vendor order detail.
         *
         * @param mixed $products_html The products list html.
         * @param mixed $item The order item.
         * @return string
         */
        public function order_detail_item_meta( $products_html, $item ) {
            ob_start();
            $this->order_item_meta( $item->get_id() );
            $item_html = ob_get_clean();

            $products_html .= $item_html;

            return $products_html;
        }

        /**
         * Get vendor ID on the current product or page.
         *
         * @since  2.0.3
         * @access public
         *
         * @return int|false
         */
        public function get_vendor_id() {
            global $post, $wp_query, $product;
            if ( ! $wp_query && ! $post & ! $product ) {
                return false;
            }

            if ( is_user_logged_in() ) {
                $vendor_id = get_current_user_id();

                if ( $this->is_vendor( $vendor_id ) ) {
                    return $vendor_id;
                }
            }

            $vendor_shop = urldecode( get_query_var( 'vendor_shop' ) );

            $vendor_id = false;
            if ( $vendor_shop ) {
                $vendor_id = $this->get_store_vendor_id( $vendor_shop );
            } elseif ( is_singular( 'product' ) && is_a( $product, 'WC_Product ' ) ) {
                $current_post = get_post( $product->get_id() );
                $vendor_id    = $current_post->post_author;
            } elseif ( is_a( $post, 'WP_Post' ) ) {
                $vendor_id = $post->post_author;
            } elseif ( isset( $_GET['wcv_vendor_id'] ) ) {// phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $vendor_id = absint( $_GET['wcv_vendor_id'] );// phpcs:ignore WordPress.Security.NonceVerification.Recommended
            }

            return $vendor_id;
        }

        /**
         * Grabs the vendor ID whether a username or an int is provided
         * and returns the vendor_id if it's actually a vendor
         *
         * @param string|int $input The username or user ID.
         *
         * @return int
         */
        public function get_store_vendor_id( $input ) {
            if ( empty( $input ) ) {
                return false;
            }

            $vendor = is_numeric( $input ) ? get_userdata( $input ) : get_user_by( 'login', $input );

            if ( ! $vendor ) {
                $users  = get_users(
                    array(
                        'meta_key'   => 'pv_shop_slug',
                        'meta_value' => sanitize_title( $input ),
                    )
                );
                $vendor = $users[0];
            }

            if ( $vendor ) {
                $vendor_id = $vendor->ID;
                if ( $this->is_vendor( $vendor_id ) ) {
                    return $vendor_id;
                }
            }

            return false;
        }

        /**
         * Check if the given user is a vendor.
         *
         * @param mixed $vendor_id The user id of the vendor to check.
         *
         * @return bool
         */
        public function is_vendor( $vendor_id ) {
            $user         = new WP_User( $vendor_id );
            $vendor_roles = apply_filters( 'wcvendors_vendor_roles', array( 'vendor' ) );
            $is_vendor    = false;

            if ( is_object( $user ) && is_array( $user->roles ) ) {

                foreach ( $vendor_roles as $role ) {
                    if ( in_array( $role, $user->roles, true ) ) {
                        $is_vendor = true;
                        break;
                    }
                }
            }

            return $is_vendor;
        }

        /**
         * Get all registered wholesale roles.
         *
         * @return array $registered_wholesale_roles All the registered wholesale roles.
         * @version 2.0.3
         * @since   2.0.3
         */
        public function get_registered_wholesale_roles() {
            $registered_wholesale_roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();
            /**
             * Allow to modify the registered wholesale roles.
             *
             * @param array $registered_wholesale_roles All the registered wholesale roles.
             */
            return apply_filters(
                'wwpp_wcvendors_get_registered_wholesale_roles',
                $registered_wholesale_roles
            );
        }
    }
}
