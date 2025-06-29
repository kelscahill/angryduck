<?php
/**
 * Plugin autoload file.
 *
 * @package RymeraWebCo\WWOF
 */

namespace RymeraWebCo\WWOF;

use Exception;

defined( 'ABSPATH' ) || exit;

/***************************************************************************
 * For Development Only
 ***************************************************************************
 *
 * If composer is installed and we are using PHP 8.0 or greater, then we can
 * use the composer autoloader. This is only for development purposes. The
 * composer autoloader is not included in the plugin zip file.
 */
if ( file_exists( WWOF_PLUGIN_DIR_PATH . 'vendor/autoload.php' ) &&
    version_compare( PHP_VERSION, '8.0', '>=' ) ) {
    require_once WWOF_PLUGIN_DIR_PATH . 'vendor/autoload.php';
}

/**
 * Builds the path to the class file within the plugin directory.
 *
 * @param string $class The class name.
 *
 * @since 3.0
 * @return string The full class file path.
 */
function get_class_file_path( $class ) {

    $class = ltrim( $class, '\\' );
    $class = str_replace( '\\', DIRECTORY_SEPARATOR, $class );
    $class = mb_substr( $class, 17 );

    return WWOF_PLUGIN_DIR_PATH . "includes/$class.php";
}

/**
 * Namespaced autoload function for the plugin.
 *
 * @param string $class The class name.
 *
 * @since 3.0
 */
function autoload( $class ) {

    $file = '';
    if ( 'RymeraWebCo\WWOF' === mb_substr( $class, 0, 16 ) ) {

        $file = get_class_file_path( $class );

        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
}

try {
    spl_autoload_register( '\RymeraWebCo\WWOF\autoload' );
} catch ( Exception $exception ) {
    if ( is_admin() ) {
        add_action(
            'admin_notices',
            function () use ( $exception ) {

                ?>
                <div class="error settings-error notice">
                    <p><strong>ERROR:</strong> <?php echo esc_html( $exception->getMessage() ); ?></p>
                </div>
                <?php
            }
        );
    } elseif ( current_user_can( 'manage_options' ) ) {
        add_action(
            'wp_footer',
            function () use ( $exception ) {

                ?>
                <div class="error">
                    <p class="text-danger">
                        <strong>ERROR:</strong> <?php echo esc_html( $exception->getMessage() ); ?></p>
                </div>
                <?php
            }
        );
    }
}
