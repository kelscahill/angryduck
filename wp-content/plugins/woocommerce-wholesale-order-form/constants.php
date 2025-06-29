<?php
/**
 * Plugin constants.
 *
 * @since   3.0
 * @package RymeraWebCo\WWOF
 */

defined( 'ABSPATH' ) || exit;

/*
|--------------------------------------------------------------------------
| Plugin paths constants
|--------------------------------------------------------------------------
|
| Constants used to define the plugin paths.
|
*/

/**
 * WWOF_MAIN_PLUGIN_FILE_PATH constant has been replaced by WWOF_PLUGIN_FILE.
 *
 * @deprecated 3.0
 */
define( 'WWOF_MAIN_PLUGIN_FILE_PATH', WWOF_PLUGIN_FILE );
define( 'WWOF_PLUGIN_BASE_NAME', plugin_basename( WWOF_PLUGIN_FILE ) );
/**
 * WWOF_PLUGIN_BASE_PATH constant is no longer in use.
 *
 * @deprecated 3.0
 */
define( 'WWOF_PLUGIN_BASE_PATH', basename( __DIR__ ) . '/' );
/**
 * WWOF_PLUGIN_URL constant is no longer in use.
 *
 * @deprecated 3.0
 */
define( 'WWOF_PLUGIN_URL', plugins_url() . '/woocommerce-wholesale-order-form/' );
/**
 * WWOF_PLUGIN_DIR constant is no longer in use.
 *
 * @deprecated 3.0
 */
define( 'WWOF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
/**
 * WWOF_CSS_ROOT_URL constant is no longer in use.
 *
 * @deprecated 3.0
 */
define( 'WWOF_CSS_ROOT_URL', WWOF_PLUGIN_URL . 'css/' );
/**
 * WWOF_CSS_ROOT_URL constant is no longer in use.
 *
 * @deprecated 3.0
 */
define( 'WWOF_CSS_ROOT_DIR', WWOF_PLUGIN_DIR . 'css/' );
/**
 * WWOF_IMAGES_ROOT_URL constant is no longer in use.
 *
 * @deprecated 3.0
 */
define( 'WWOF_IMAGES_ROOT_URL', WWOF_PLUGIN_URL . 'static/images/' );
/**
 * WWOF_IMAGES_ROOT_DIR constant is no longer in use.
 *
 * @deprecated 3.0
 */
define( 'WWOF_IMAGES_ROOT_DIR', WWOF_PLUGIN_DIR . 'static/images/' );
/**
 * WWOF_JS_ROOT_URL constant is no longer in use.
 *
 * @deprecated 3.0
 */
define( 'WWOF_JS_ROOT_URL', WWOF_PLUGIN_URL . 'js/' );
/**
 * WWOF_JS_ROOT_DIR constant is no longer in use.
 *
 * @deprecated 3.0
 */
define( 'WWOF_JS_ROOT_DIR', WWOF_PLUGIN_DIR . 'js/' );
/**
 * WWOF_TEMPLATES_ROOT_URL constant is no longer in use.
 *
 * @deprecated 3.0
 */
define( 'WWOF_TEMPLATES_ROOT_URL', WWOF_PLUGIN_URL . 'templates/' );
/**
 * WWOF_TEMPLATES_ROOT_DIR constant is no longer in use.
 *
 * @deprecated 3.0
 */
define( 'WWOF_TEMPLATES_ROOT_DIR', WWOF_PLUGIN_DIR . 'templates/' );
/**
 * WWOF_VIEWS_ROOT_URL constant is no longer in use.
 *
 * @deprecated 3.0
 */
define( 'WWOF_VIEWS_ROOT_URL', WWOF_PLUGIN_URL . 'views/' );
/**
 * WWOF_VIEWS_ROOT_DIR constant is no longer in use.
 *
 * @deprecated 3.0
 */
define( 'WWOF_VIEWS_ROOT_DIR', WWOF_PLUGIN_DIR . 'views/' );
/**
 * WWOF_LANGUAGES_ROOT_URL constant is no longer in use.
 *
 * @deprecated 3.0
 */
define( 'WWOF_LANGUAGES_ROOT_URL', WWOF_PLUGIN_URL . 'languages/' );
/**
 * WWOF_LANGUAGES_ROOT_DIR constant is no longer in use.
 *
 * @deprecated 3.0
 */
define( 'WWOF_LANGUAGES_ROOT_DIR', WWOF_PLUGIN_DIR . 'languages/' );

/***************************************************************************
 * SLMW Constants
 ***************************************************************************
 *
 * Constants used for SLMW.
 */

define( 'WWOF_PLUGIN_SITE_URL', 'https://wholesalesuiteplugin.com' );

// SLMW.
if ( ! defined( 'WWS_SLMW_SERVER_URL' ) ) {
    define( 'WWS_SLMW_SERVER_URL', 'https://wholesalesuiteplugin.com' );
}

define( 'WWOF_LICENSE_ACTIVATION_URL', WWOF_PLUGIN_SITE_URL . '/wp-json/slmw/v1/license/activate' );
define( 'WWOF_UPDATE_DATA_URL', WWS_SLMW_SERVER_URL . '/wp-admin/admin-ajax.php?action=slmw_get_update_data' );

if ( ! defined( 'WWS_LICENSE_SETTINGS_PAGE' ) ) {
    define( 'WWS_LICENSE_SETTINGS_PAGE', 'woocommerce-wholesale-order-form' );
}
if ( ! defined( 'WWS_LICENSE_DATA' ) ) {
    define( 'WWS_LICENSE_DATA', 'wws_license_data' );
}
define( 'WWOF_STATIC_PING_FILE', WWS_SLMW_SERVER_URL . '/WWOF.json' );
define( 'WWOF_OPTION_LICENSE_EMAIL', 'wwof_option_license_email' );
define( 'WWOF_OPTION_LICENSE_KEY', 'wwof_option_license_key' );
define( 'WWOF_LICENSE_ACTIVATED', 'wwof_license_activated' );
define( 'WWOF_LAST_LICENSE_CHECK', 'wwof_last_license_check' );
define( 'WWOF_SHOW_NO_LICENSE_NOTICE', 'wwof_show_no_license_notice' );
define( 'WWOF_SHOW_PRE_EXPIRY_LICENSE_NOTICE', 'wwof_show_pre_expiry_license_notice' );
define( 'WWOF_SHOW_EXPIRED_LICENSE_NOTICE', 'wwof_show_expired_license_notice' );
define( 'WWOF_SHOW_DISABLED_LICENSE_NOTICE', 'wwof_show_disabled_license_notice' );
define( 'WWOF_SHOW_LICENSE_REMINDER_POINTER', 'wwof_show_license_reminder_pointer' );
define( 'WWOF_LICENSE_EXPIRED', 'wwof_license_expired' );

/**
 * Option that holds retrieved software product update data.
 */
define( 'WWOF_UPDATE_DATA', 'wwof_update_data' );
define( 'WWOF_RETRIEVING_UPDATE_DATA', 'wwof_retrieving_update_data' );
define( 'WWOF_OPTION_INSTALLED_VERSION', 'wwof_option_installed_version' );
define( 'WWOF_ACTIVATE_LICENSE_NOTICE', 'wwof_activate_license_notice' );
define( 'WWOF_PLUGIN_LICENSE_STATUSES_CACHE', 'wwp_plugin_license_statuses_cache' );

/***************************************************************************
 * Option constants
 ***************************************************************************
 *
 * Constants used as options keys.
 */

define( 'WWOF_ACTIVATION_CODE_TRIGGERED', 'wwof_activation_code_triggered' );
/**
 * WWOF_SETTINGS constant is no longer in use.
 *
 * @deprecated 3.0
 */
define( 'WWOF_SETTINGS', 'wwof_settings' );
/**
 * WWOF_SETTINGS_WHOLESALE_PAGE_ID constant is no longer in use.
 *
 * @deprecated 3.0
 */
define( 'WWOF_SETTINGS_WHOLESALE_PAGE_ID', 'wwof_settings_wholesale_page_id' );
/**
 * WWOF_MIGRATION_DATA_MAPPING constant is no longer in use.
 *
 * @deprecated 3.0
 */
define( 'WWOF_MIGRATION_DATA_MAPPING', 'wwof_migration_data_mapping' );
/**
 * WWOF_WIZARD_SETUP_DONE constant is no longer in use.
 *
 * @deprecated 3.0
 */
define( 'WWOF_WIZARD_SETUP_DONE', 'wwof_wizard_setup_done' );
/**
 * WWOF_SETUP_WIZARD_REDIRECT constant is no longer in use.
 *
 * @deprecated 3.0
 */
define( 'WWOF_SETUP_WIZARD_REDIRECT', 'wwof_setup_wizard_redirect' );
/**
 * WWOF_DISPLAY_WIZARD_NOTICE constant is no longer in use.
 *
 * @deprecated 3.0
 */
define( 'WWOF_DISPLAY_WIZARD_NOTICE', 'wwof_display_wizard_notice' );

/*
|--------------------------------------------------------------------------
| Settings option constants
|--------------------------------------------------------------------------
|
| Global variables used as options keys.
|
*/

/**
 * Holds default products per page value.
 *
 * @deprecated 3.0
 */
$GLOBALS['WWOF_SETTINGS_DEFAULT_PPP'] = 12;

/**
 * Holds default products sort by value.
 *
 * @deprecated 3.0
 */
$GLOBALS['WWOF_SETTINGS_DEFAULT_SORT_BY'] = 'menu_order';

/**
 * Holds default products sort order.
 *
 * @deprecated 3.0
 */

/**
 * Holds sort by settings.
 *
 * @deprecated 3.0
 */
$GLOBALS['WWOF_SETTINGS_SORT_BY'] = null;

/**
 * Initialize the $WWOF_SETTINGS_SORT_BY global variable.
 *
 * @return void
 *
 * @deprecated 3.0
 */
function wwofInitializeGlobalVariables() {

    if ( ! isset( $GLOBALS['WWOF_SETTINGS_SORT_BY'] ) ) {
        $GLOBALS['WWOF_SETTINGS_SORT_BY'] = array(
            'default'    => __( 'Default Sorting', 'woocommerce-wholesale-order-form' ),
            'menu_order' => __( 'Custom Ordering (menu order) + Name', 'woocommerce-wholesale-order-form' ),
            'name'       => __( 'Name', 'woocommerce-wholesale-order-form' ),
            'date'       => __( 'Sort by Date', 'woocommerce-wholesale-order-form' ),
            'sku'        => __( 'SKU', 'woocommerce-wholesale-order-form' ),
        );
    }
}

add_action( 'init', 'wwofInitializeGlobalVariables', 1 );
