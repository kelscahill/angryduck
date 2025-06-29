<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// WWP.
require_once WWP_INCLUDES_PATH . 'class-wwp-helper-functions.php';
require_once WWP_INCLUDES_PATH . 'class-wwp-aelia-currency-switcher-integration-helper.php';
require_once WWP_INCLUDES_PATH . 'class-wwp-wholesale-roles.php';
require_once WWP_INCLUDES_PATH . 'class-wwp-wholesale-prices.php';

require_once WWPP_INCLUDES_PATH . 'class-wwpp-helper-functions.php';
require_once WWPP_INCLUDES_PATH . 'class-wwpp-wpdb-helper.php';
require_once WWPP_INCLUDES_PATH . 'class-wwpp-wholesale-prices.php';
require_once WWPP_INCLUDES_PATH . 'class-wwpp-admin-settings.php';
require_once WWPP_INCLUDES_PATH . 'class-wwpp-admin-action-settings.php';

require_once WWPP_INCLUDES_PATH . 'class-wwpp-bootstrap.php';
require_once WWPP_INCLUDES_PATH . 'class-wwpp-script-loader.php';
require_once WWPP_INCLUDES_PATH . 'class-wwpp-settings.php';
require_once WWPP_INCLUDES_PATH . 'wholesale-roles/class-wwpp-wholesale-roles-admin-page.php';
require_once WWPP_INCLUDES_PATH . 'wholesale-roles/class-wwpp-wholesale-roles.php';
require_once WWPP_INCLUDES_PATH . 'wholesale-roles/class-wwpp-wholesale-role-general-discount-mapping.php';
require_once WWPP_INCLUDES_PATH . 'wholesale-roles/class-wwpp-wholesale-role-tax-option-mapping.php';
require_once WWPP_INCLUDES_PATH . 'wholesale-roles/class-wwpp-wholesale-role-order-requirement-mapping.php';
require_once WWPP_INCLUDES_PATH . 'wholesale-roles/class-wwpp-wholesale-role-shipping-method.php';
require_once WWPP_INCLUDES_PATH . 'wholesale-roles/class-wwpp-wholesale-role-payment-gateway.php';
require_once WWPP_INCLUDES_PATH . 'class-wwpp-query.php';
require_once WWPP_INCLUDES_PATH . 'admin-custom-fields/product-category/class-wwpp-admin-custom-fields-product-category.php';
require_once WWPP_INCLUDES_PATH . 'admin-custom-fields/products/class-wwpp-admin-custom-fields-product.php';
require_once WWPP_INCLUDES_PATH . 'admin-custom-fields/products/class-wwpp-admin-custom-fields-simple-product.php';
require_once WWPP_INCLUDES_PATH . 'admin-custom-fields/products/class-wwpp-admin-custom-fields-variable-product.php';
require_once WWPP_INCLUDES_PATH . 'class-wwpp-product-visibility.php';
require_once WWPP_INCLUDES_PATH . 'class-wwpp-wholesale-login-logout.php';
require_once WWPP_INCLUDES_PATH . 'class-wwpp-wholesale-price-requirement.php';
require_once WWPP_INCLUDES_PATH . 'class-wwpp-wholesale-back-order.php';
require_once WWPP_INCLUDES_PATH . 'wholesale-prices/class-wwpp-wholesale-price-variable-product.php';
require_once WWPP_INCLUDES_PATH . 'wholesale-prices/class-wwpp-wholesale-price-product-category.php';
require_once WWPP_INCLUDES_PATH . 'wholesale-prices/class-wwpp-wholesale-price-wholesale-role.php';
require_once WWPP_INCLUDES_PATH . 'class-wwpp-tax.php';
require_once WWPP_INCLUDES_PATH . 'class-wwpp-wc-order.php';
require_once WWPP_INCLUDES_PATH . 'reports/class-wwpp-report.php';
require_once WWPP_INCLUDES_PATH . 'class-wwpp-shortcodes.php';
require_once WWPP_INCLUDES_PATH . 'class-wwpp-duplicate-product.php';
require_once WWPP_INCLUDES_PATH . 'class-wwpp-cache.php';
require_once WWPP_INCLUDES_PATH . 'class-wwpp-per-wholesale-user-settings.php';
require_once WWPP_INCLUDES_PATH . 'blocks/class-woocommerce-blocks.php';
require_once WWPP_INCLUDES_PATH . 'class-wwpp-cart-discounts.php';

// Lifecycle Email.
require_once WWPP_INCLUDES_PATH . 'class-wwpp-lifecyle-email.php';

// License manager.
require_once 'includes/class-wwpp-wws-license-manager.php';
require_once 'includes/class-wwpp-wws-update-manager.php';

// Third party plugin integrations.
require_once WWPP_INCLUDES_PATH . 'plugin-integrations/woocommerce-composite-products/class-wwpp-wc-composite-product.php';
require_once WWPP_INCLUDES_PATH . 'plugin-integrations/woocommerce-product-bundles/class-wwpp-wc-product-bundles.php';
require_once WWPP_INCLUDES_PATH . 'plugin-integrations/woocommerce-product-add-ons/class-wwpp-wc-product-add-ons.php';
require_once WWPP_INCLUDES_PATH . 'plugin-integrations/woocommerce-products-by-attributes-variations/class-wwpp-wc-products-by-attributes-variations.php';
require_once WWPP_INCLUDES_PATH . 'plugin-integrations/woocommerce-multilingual/class-wwpp-wc-multilingual.php';
require_once WWPP_INCLUDES_PATH . 'plugin-integrations/wc-vendors/class-wwpp-wc-vendors.php';
require_once WWPP_INCLUDES_PATH . 'plugin-integrations/wc-vendors/class-wwpp-wc-vendors-form-helper.php';
require_once WWPP_INCLUDES_PATH . 'plugin-integrations/woocommerce-subscriptions/class-wwpp-wc-subscriptions.php';

// WWPP API Integrations.
require_once WWPP_INCLUDES_PATH . 'api/class-wwpp-api.php';

/**
 * This is the main plugin class. It's purpose generally is for "ALL PLUGIN RELATED STUFF ONLY".
 * This file or class may also serve as a controller to some degree but most if not all business logic is distributed
 * across include files.
 *
 * Class WooCommerceWholeSalePricesPremium
 */
class WooCommerceWholeSalePricesPremium {


    /**
     * Class Members
     */

    // phpcs:disable
    private static $_instance;

    public $wwpp_wholesale_prices;
    public $wwpp_admin_settings;
    public $wwpp_admin_action_settings;
    public $wwpp_bootstrap;
    public $wwpp_script_loader;
    public $wwpp_settings;
    public $wwpp_wholesale_roles_admin_page;
    public $wwpp_wholesale_roles;
    public $wwpp_wholesale_roles_general_discount_mapping;
    public $wwpp_wholesale_roles_tax_option_mapping;
    public $wwpp_wholesale_roles_order_requirement_mapping;
    public $wwpp_wholesale_shipping_method;
    public $wwpp_query;
    public $wwpp_admin_custom_fields_product_category;
    public $wwpp_admin_custom_fields_product;
    public $wwpp_admin_custom_fields_simple_product;
    public $wwpp_admin_custom_fields_variable_product;
    public $wwpp_product_visibility;
    public $wwpp_wholesale_login_logout;
    public $wwpp_wholesale_price_requirement;
    public $wwpp_wholesale_back_order;
    public $wwpp_wholesale_price_variable_product;
    public $wwpp_wholesale_price_product_category;
    public $wwpp_wholesale_price_wholesale_role;
    public $wwpp_wholesale_role_payment_gateway;
    public $wwpp_tax;
    public $wwpp_wc_order;
    public $wwpp_report;
    public $wwpp_shortcodes;
    public $wwpp_duplicate_product;
    public $wwpp_cache;
    public $wwpp_per_wholesale_user_settings;
    public $wwpp_wc_blocks;
    public $wwpp_rest_api;
    public $wwpp_cart_discounts;

    // Lifecycle Email.
    public $wwpp_lifecyle_email;

    // License manager.
    public $wwpp_wws_license_manager;
    public $wwpp_wws_update_manager;

    // Third party plugin integrations.
    public $wwpp_wc_composite_product;
    public $wwpp_wc_bundle_product;
    public $wwpp_wc_product_on;
    public $wwpp_wc_products_attributes_variations;
    public $wwpp_wc_multilingual;
    public $wwpp_wc_vendors;
    public $wwpp_wc_subscriptions;
    // phpcs:enable

    const VERSION = '2.0.4';

    /*
    |--------------------------------------------------------------------------
    | Class Methods
    |--------------------------------------------------------------------------
     */

    /**
     * WooCommerceWholeSalePricesPremium constructor.
     *
     * @since 1.0.0
     * @since 1.14.0
     * @access public
     */
    public function __construct() {
        // License manager.
        $this->wwpp_wws_license_manager = WWPP_WWS_License_Manager::instance( array( 'WWPP_CURRENT_VERSION' => self::VERSION ) );
        $this->wwpp_wws_update_manager  = WWPP_WWS_Update_Manager::instance( array( 'WWPP_WWS_License_Manager' => $this->wwpp_wws_license_manager ) );

        $this->wwpp_wholesale_roles                           = WWPP_Wholesale_Roles::instance();
        $this->wwpp_wholesale_price_requirement               = WWPP_Wholesale_Price_Requirement::instance( array( 'WWPP_Wholesale_Roles' => $this->wwpp_wholesale_roles ) );
        $this->wwpp_tax                                       = WWPP_Tax::instance(
            array(
                'WWPP_Wholesale_Roles'             => $this->wwpp_wholesale_roles,
                'WWPP_Wholesale_Price_Requirement' => $this->wwpp_wholesale_price_requirement,
            )
        );
        $this->wwpp_wholesale_prices                          = WWPP_Wholesale_Prices::instance(
            array(
                'WWPP_Wholesale_Roles' => $this->wwpp_wholesale_roles,
                'WWPP_Tax'             => $this->wwpp_tax,
            )
        );
        $this->wwpp_admin_settings                            = WWPP_Admin_Settings::instance(
            array(
                'WWPP_WWS_License_Manager' => $this->wwpp_wws_license_manager,
                'WWPP_Wholesale_Roles'     => $this->wwpp_wholesale_roles,
            )
        );
        $this->wwpp_admin_action_settings                     = WWPP_Admin_Action_Settings::instance(
            array(
                'WWPP_Wholesale_Roles' => $this->wwpp_wholesale_roles,
            )
        );
        $this->wwpp_wholesale_role_payment_gateway            = WWPP_Wholesale_Role_Payment_Gateway::instance(
            array(
                'WWPP_Wholesale_Roles' => $this->wwpp_wholesale_roles,
                'WWPP_Tax'             => $this->wwpp_tax,
            )
        );
        $this->wwpp_bootstrap                                 = WWPP_Bootstrap::instance(
            array(
                'WWPP_Wholesale_Roles'                => $this->wwpp_wholesale_roles,
                'WWPP_Wholesale_Role_Payment_Gateway' => $this->wwpp_wholesale_role_payment_gateway,
                'WWPP_WWS_License_Manager'            => $this->wwpp_wws_license_manager,
                'WWPP_CURRENT_VERSION'                => self::VERSION,
            )
        );
        $this->wwpp_wholesale_price_product_category          = WWPP_Wholesale_Price_Product_Category::instance( array() );
        $this->wwpp_wholesale_price_wholesale_role            = WWPP_Wholesale_Price_Wholesale_Role::instance( array() );
        $this->wwpp_script_loader                             = WWPP_Script_Loader::instance(
            array(
                'WWPP_Wholesale_Roles'                  => $this->wwpp_wholesale_roles,
                'WWPP_Wholesale_Prices'                 => $this->wwpp_wholesale_prices,
                'WWPP_Wholesale_Role_Payment_Gateway'   => $this->wwpp_wholesale_role_payment_gateway,
                'WWPP_CURRENT_VERSION'                  => self::VERSION,
                'wwpp_roles_page_handle'                => 'woocommerce_page_wwpp-wholesale-roles-page',
                'WWPP_Wholesale_Price_Product_Category' => $this->wwpp_wholesale_price_product_category,
            )
        );
        $this->wwpp_settings                                  = WWPP_Settings::instance( array( 'WWPP_Wholesale_Roles' => $this->wwpp_wholesale_roles ) );
        $this->wwpp_wholesale_roles_admin_page                = WWPP_Wholesale_Roles_Admin_Page::instance( array( 'WWPP_Wholesale_Roles' => $this->wwpp_wholesale_roles ) );
        $this->wwpp_wholesale_roles_general_discount_mapping  = WWPP_Wholesale_Role_General_Discount_Mapping::instance( array( 'WWPP_Wholesale_Roles' => $this->wwpp_wholesale_roles ) );
        $this->wwpp_wholesale_roles_tax_option_mapping        = WWPP_Wholesale_Role_Tax_Option_Mapping::instance( array( 'WWPP_Wholesale_Roles' => $this->wwpp_wholesale_roles ) );
        $this->wwpp_wholesale_roles_order_requirement_mapping = WWPP_Wholesale_Role_Order_Requirement_Mapping::instance( array( 'WWPP_Wholesale_Roles' => $this->wwpp_wholesale_roles ) );
        $this->wwpp_wholesale_shipping_method                 = WWPP_Wholesale_Role_Shipping_Method::instance( array( 'WWPP_Wholesale_Roles' => $this->wwpp_wholesale_roles ) );
        $this->wwpp_query                                     = WWPP_Query::instance(
            array(
                'WWPP_Wholesale_Roles'                => $this->wwpp_wholesale_roles,
                'WWPP_Wholesale_Price_Wholesale_Role' => $this->wwpp_wholesale_price_wholesale_role,
            )
        );
        $this->wwpp_admin_custom_fields_product_category      = WWPP_Admin_Custom_Fields_Product_Category::instance( array( 'WWPP_Wholesale_Roles' => $this->wwpp_wholesale_roles ) );
        $this->wwpp_admin_custom_fields_product               = WWPP_Admin_Custom_Fields_Product::instance( array( 'WWPP_Wholesale_Roles' => $this->wwpp_wholesale_roles ) );
        $this->wwpp_admin_custom_fields_simple_product        = WWPP_Admin_Custom_Fields_Simple_Product::instance( array( 'WWPP_Wholesale_Roles' => $this->wwpp_wholesale_roles ) );
        $this->wwpp_admin_custom_fields_variable_product      = WWPP_Admin_Custom_Fields_Variable_Product::instance(
            array(
                'WWPP_Wholesale_Roles'  => $this->wwpp_wholesale_roles,
                'WWPP_Wholesale_Prices' => $this->wwpp_wholesale_prices,
            )
        );
        $this->wwpp_product_visibility                        = WWPP_Product_Visibility::instance(
            array(
                'WWPP_Wholesale_Roles'                => $this->wwpp_wholesale_roles,
                'WWPP_Wholesale_Price_Wholesale_Role' => $this->wwpp_wholesale_price_wholesale_role,
            )
        );
        $this->wwpp_wholesale_login_logout                    = WWPP_Wholesale_Login_Logout::instance( array( 'WWPP_Wholesale_Roles' => $this->wwpp_wholesale_roles ) );
        $this->wwpp_wholesale_back_order                      = WWPP_Wholesale_Back_Order::instance( array( 'WWPP_Wholesale_Roles' => $this->wwpp_wholesale_roles ) );
        $this->wwpp_wholesale_price_variable_product          = WWPP_Wholesale_Price_Variable_Product::instance(
            array(
                'WWPP_Wholesale_Roles'                => $this->wwpp_wholesale_roles,
                'WWPP_Wholesale_Price_Wholesale_Role' => $this->wwpp_wholesale_price_wholesale_role,
            )
        );
        $this->wwpp_wc_order                                  = WWPP_WC_Order::instance( array( 'WWPP_Wholesale_Roles' => $this->wwpp_wholesale_roles ) );
        $this->wwpp_report                                    = WWPP_Report::instance( array( 'WWPP_Wholesale_Roles' => $this->wwpp_wholesale_roles ) );
        $this->wwpp_shortcodes                                = WWPP_Shortcodes::instance( array( 'WWPP_Wholesale_Roles' => $this->wwpp_wholesale_roles ) );
        $this->wwpp_duplicate_product                         = WWPP_Duplicate_Product::instance( array( 'WWPP_Wholesale_Roles' => $this->wwpp_wholesale_roles ) );
        $this->wwpp_cache                                     = WWPP_Cache::instance( array() );
        $this->wwpp_per_wholesale_user_settings               = WWPP_Per_Wholesale_User_Settings::instance( array( 'WWPP_Wholesale_Roles' => $this->wwpp_wholesale_roles ) );

        // Lifecycle Email.
        $this->wwpp_lifecyle_email = WWPP_Lifecycle_EMAIL::instance(
            array(
                'WWPP_Wholesale_Roles'  => $this->wwpp_wholesale_roles,
                'WWPP_Wholesale_Prices' => $this->wwpp_wholesale_prices,
            )
        );

        // Third party plugin integrations.
        $this->wwpp_wc_composite_product = WWPP_WC_Composite_Product::instance(
            array(
                'WWPP_Wholesale_Roles'                    => $this->wwpp_wholesale_roles,
                'WWPP_Admin_Custom_Fields_Simple_Product' => $this->wwpp_admin_custom_fields_simple_product,
            )
        );
        $this->wwpp_wc_bundle_product    = WWPP_WC_Product_Bundles::instance(
            array(
                'WWPP_Wholesale_Roles'                    => $this->wwpp_wholesale_roles,
                'WWPP_Admin_Custom_Fields_Simple_Product' => $this->wwpp_admin_custom_fields_simple_product,
                'WWPP_Wholesale_Prices'                   => $this->wwpp_wholesale_prices,
                'WWPP_Wholesale_Price_Product_Category'   => $this->wwpp_wholesale_price_product_category,
                'WWPP_Wholesale_Price_Wholesale_Role'     => $this->wwpp_wholesale_price_wholesale_role,
            )
        );
        $this->wwpp_wc_product_on        = WWPP_WC_Product_Addon::instance(
            array(
                'WWPP_Wholesale_Roles'  => $this->wwpp_wholesale_roles,
                'WWPP_Wholesale_Prices' => $this->wwpp_wholesale_prices,
            )
        );
        $this->wwpp_wc_multilingual      = WWPP_WC_Multilingual::instance( array() );
        $this->wwpp_wc_subscriptions     = WWPP_WC_Subscriptions::instance(
            array(
                'WWPP_Wholesale_Roles' => $this->wwpp_wholesale_roles,
            )
        );

        $this->wwpp_wc_products_attributes_variations = WWPP_WC_Products_Atributes_Variations::instance( array() );

        $this->wwpp_wc_blocks = WWPP_WC_Blocks::instance(
            array(
                'WWPP_Query'           => $this->wwpp_query,
                'WWPP_Wholesale_Roles' => $this->wwpp_wholesale_roles,
            )
        );

        $this->wwpp_cart_discounts = WWPP_Cart_Discounts::instance( array() );
        $this->wwpp_wc_vendors     = WWPP_WC_Vendors::instance(
            array(
                'WWPP_CURRENT_VERSION' => self::VERSION,
                'wwpp_wholesale_roles' => $this->wwpp_wholesale_roles,
            )
        );

        // WWPP API.
        $this->wwpp_rest_api = WWPP_REST_API::instance( array() );
    }

    /**
     * Singleton Pattern.
     *
     * @since 1.0.0
     * @since 1.14.0
     * @access public
     *
     * @return WooCommerceWholeSalePricesPremium
     */
    public static function instance() {
        if ( ! self::$_instance instanceof self ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Declare compatibility with WooCommerce HPOS.
     *
     * @since 1.30.3
     */
    public function declare_hpos_compatibility() {
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', 'woocommerce-wholesale-prices-premium/woocommerce-wholesale-prices-premium.bootstrap.php', true );
        }
    }

    /**
     * Execute WWPP. Triggers the execution codes of the plugin models.
     *
     * @since 1.12.8
     * @access public
     */
    public function run() {
        $this->wwpp_bootstrap->run();
        $this->wwpp_wholesale_prices->run();
        $this->wwpp_admin_settings->run();
        $this->wwpp_admin_action_settings->run();
        $this->wwpp_wholesale_price_product_category->run();
        $this->wwpp_wholesale_price_wholesale_role->run();
        $this->wwpp_script_loader->run();
        $this->wwpp_settings->run();
        $this->wwpp_wholesale_roles_admin_page->run();
        $this->wwpp_wholesale_roles->run();
        $this->wwpp_wholesale_roles_general_discount_mapping->run();
        $this->wwpp_wholesale_roles_tax_option_mapping->run();
        $this->wwpp_wholesale_roles_order_requirement_mapping->run();
        $this->wwpp_wholesale_shipping_method->run();
        $this->wwpp_admin_custom_fields_product_category->run();
        $this->wwpp_admin_custom_fields_product->run();
        $this->wwpp_admin_custom_fields_simple_product->run();
        $this->wwpp_admin_custom_fields_variable_product->run();
        $this->wwpp_query->run();
        $this->wwpp_product_visibility->run();
        $this->wwpp_wholesale_login_logout->run();
        $this->wwpp_wholesale_price_requirement->run();
        $this->wwpp_wholesale_back_order->run();
        $this->wwpp_wholesale_price_variable_product->run();
        $this->wwpp_wholesale_role_payment_gateway->run();
        $this->wwpp_tax->run();
        $this->wwpp_wc_order->run();
        $this->wwpp_report->run();
        $this->wwpp_shortcodes->run();
        $this->wwpp_duplicate_product->run();
        $this->wwpp_cache->run();
        $this->wwpp_per_wholesale_user_settings->run();
        $this->wwpp_wc_blocks->run();
        $this->wwpp_cart_discounts->run();

        // Lifecycle Email.
        $this->wwpp_lifecyle_email->run();

        // License manager.
        $this->wwpp_wws_license_manager->run();
        $this->wwpp_wws_update_manager->run();

        // Third party plugin integrations.
        $this->wwpp_wc_composite_product->run();
        $this->wwpp_wc_bundle_product->run();
        $this->wwpp_wc_product_on->run();
        $this->wwpp_wc_products_attributes_variations->run();
        $this->wwpp_wc_multilingual->run();
        $this->wwpp_wc_vendors->run();
        $this->wwpp_wc_subscriptions->run();

        // HPOS compatibility.
        add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
    }
}
