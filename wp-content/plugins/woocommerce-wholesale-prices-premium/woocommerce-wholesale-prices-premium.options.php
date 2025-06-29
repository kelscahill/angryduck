<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Require WWP options.
require_once plugin_dir_path( __DIR__ ) . 'woocommerce-wholesale-prices/woocommerce-wholesale-prices.options.php';
require_once plugin_dir_path( __DIR__ ) . 'woocommerce-wholesale-prices/woocommerce-wholesale-prices.plugin.php';

// This is where you set various options affecting the plugin.

// Path Constants.
define( 'WWPP_MAIN_PLUGIN_FILE_PATH', WWPP_PLUGIN_FILE );
define( 'WWPP_PLUGIN_BASE_NAME', plugin_basename( WWPP_MAIN_PLUGIN_FILE_PATH ) );
define( 'WWPP_PLUGIN_BASE_PATH', basename( __DIR__ ) . '/' );
define( 'WWPP_PLUGIN_URL', plugins_url() . '/woocommerce-wholesale-prices-premium/' );
define( 'WWPP_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WWPP_CSS_PATH', WWPP_PLUGIN_PATH . 'css/' );
define( 'WWPP_CSS_URL', WWPP_PLUGIN_URL . 'css/' );
define( 'WWPP_IMAGES_PATH', WWPP_PLUGIN_PATH . 'images/' );
define( 'WWPP_IMAGES_URL', WWPP_PLUGIN_URL . 'images/' );
define( 'WWPP_INCLUDES_PATH', WWPP_PLUGIN_PATH . 'includes/' );
define( 'WWPP_INCLUDES_URL', WWPP_PLUGIN_URL . 'includes/' );
define( 'WWPP_JS_PATH', WWPP_PLUGIN_PATH . 'js/' );
define( 'WWPP_JS_URL', WWPP_PLUGIN_URL . 'js/' );
define( 'WWPP_LOGS_PATH', WWPP_PLUGIN_PATH . 'logs/' );
define( 'WWPP_LOGS_URL', WWPP_PLUGIN_URL . 'logs/' );
define( 'WWPP_VIEWS_PATH', WWPP_PLUGIN_PATH . 'views/' );
define( 'WWPP_VIEW_URL', WWPP_PLUGIN_URL . 'views/' );

// SLMW.
if ( ! defined( 'WWS_SLMW_SERVER_URL' ) ) {
    define( 'WWS_SLMW_SERVER_URL', 'https://wholesalesuiteplugin.com' );
}
if ( ! defined( 'WWS_LICENSE_SETTINGS_PAGE' ) ) {
    define( 'WWS_LICENSE_SETTINGS_PAGE', 'woocommerce-wholesale-prices-premium' );
}
if ( ! defined( 'WWS_LICENSE_DATA' ) ) {
    define( 'WWS_LICENSE_DATA', 'wws_license_data' );
}

define( 'WWPP_STATIC_PING_FILE', WWS_SLMW_SERVER_URL . '/WWPP.json' );
define( 'WWPP_OPTION_LICENSE_EMAIL', 'wwpp_option_license_email' );
define( 'WWPP_OPTION_LICENSE_KEY', 'wwpp_option_license_key' );
define( 'WWPP_LICENSE_ACTIVATED', 'wwpp_license_activated' );
define( 'WWPP_UPDATE_DATA', 'wwpp_update_data' );
define( 'WWPP_RETRIEVING_UPDATE_DATA', 'wwpp_retrieving_update_data' );
define( 'WWPP_OPTION_INSTALLED_VERSION', 'wwpp_option_installed_version' );
define( 'WWPP_LAST_LICENSE_CHECK', 'wwpp_last_license_check' );
define( 'WWPP_SHOW_NO_LICENSE_NOTICE', 'wwpp_show_no_license_notice' );
define( 'WWPP_SHOW_PRE_EXPIRY_LICENSE_NOTICE', 'wwpp_show_pre_expiry_license_notice' );
define( 'WWPP_SHOW_EXPIRED_LICENSE_NOTICE', 'wwpp_show_expired_license_notice' );
define( 'WWPP_SHOW_DISABLED_LICENSE_NOTICE', 'wwpp_show_disabled_license_notice' );
define( 'WWPP_SHOW_LICENSE_REMINDER_POINTER', 'wwpp_show_license_reminder_pointer' );
define( 'WWPP_LICENSE_EXPIRED', 'wwpp_license_expired' );

// CRON.
define( 'WWPP_CRON_INITIALIZE_PRODUCT_WHOLESALE_VISIBILITY_FILTER', 'wwpp_cron_initialize_product_wholesale_visibility_filter' );

// Options.
define( 'WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER', 'wwpp_product_wholesale_visibility_filter' );
define( 'WWPP_OPTION_WHOLESALE_ROLE_SHIPPING_METHOD_MAPPING', 'wwpp_option_wholesale_role_shipping_method_mapping' );
define( 'WWPP_OPTION_WHOLESALE_ROLE_GENERAL_DISCOUNT_MAPPING', 'wwpp_option_wholesale_role_general_discount_mapping' );
define( 'WWPP_OPTION_WHOLESALE_ROLE_CART_QTY_BASED_DISCOUNT_MAPPING', 'wwpp_option_wholesale_role_cart_qty_based_discount_mapping' );
define( 'WWPP_OPTION_PAYMENT_GATEWAY_SURCHARGE_MAPPING', 'wwpp_option_payment_gateway_surcharge_mapping' );
define( 'WWPP_OPTION_WHOLESALE_ROLE_PAYMENT_GATEWAY_MAPPING', 'wwpp_option_wholesale_role_payment_gateway_mapping' );
define( 'WWPP_OPTION_ACTIVATION_CODE_TRIGGERED', 'wwpp_option_activation_code_triggered' );
define( 'WWPP_OPTION_WHOLESALE_ROLE_ORDER_REQUIREMENT_MAPPING', 'wwpp_option_wholesale_role_order_requirement_mapping' );
define( 'WWPP_OPTION_WHOLESALE_ROLE_TAX_OPTION_MAPPING', 'wwpp_option_wholesale_role_tax_option_mapping' );
define( 'WWPP_OPTION_WHOLESALE_ROLE_TAX_CLASS_OPTIONS_MAPPING', 'wwpp_option_wholesale_role_tax_class_options_mapping' );
define( 'WWPP_OPTION_PRODUCT_CAT_WHOLESALE_ROLE_FILTER', 'wwpp_option_product_cat_wholesale_role_filter' );
define( 'WWPP_OPTION_WHOLESALE_ROLE_CART_SUBTOTAL_PRICE_BASED_DISCOUNT_MAPPING', 'wwpp_option_wholesale_role_cart_subtotal_price_based_discount_mapping' );

// This constant holds both zoned and non-zoned shipping methods.
define( 'WWPP_OPTION_WHOLESALE_ROLE_SHIPPING_ZONE_METHOD_MAPPING', 'wwpp_option_wholesale_role_shipping_zone_method_mapping' );

// Post Meta.
define( 'WWPP_POST_META_ENABLE_QUANTITY_DISCOUNT_RULE', 'wwpp_post_meta_enable_quantity_discount_rule' );
define( 'WWPP_POST_META_QUANTITY_DISCOUNT_RULE_MAPPING_VIEW', 'wwpp_post_meta_quantity_discount_rule_mapping_view' );
define( 'WWPP_POST_META_QUANTITY_DISCOUNT_RULE_MAPPING', 'wwpp_post_meta_quantity_discount_rule_mapping' );
