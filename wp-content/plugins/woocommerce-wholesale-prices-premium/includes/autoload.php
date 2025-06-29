<?php
/**
 * Plugin autoload file.
 *
 * @package RymeraWebCo\WWPP
 */

namespace RymeraWebCo\WWPP;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the path to the class file within the plugin directory.
 *
 * @param string $class_name The class name.
 *
 * @return string The full class file path.
 */
function get_class_file_path( $class_name ) {

    $class_file = ltrim( $class_name, '\\' );
    $class_file = str_replace( '\\', DIRECTORY_SEPARATOR, $class_file );
    $class_file = mb_substr( $class_file, 17 );
    $class_file = str_replace( '_', '-', sanitize_key( $class_file ) );
    $class_file = "class-$class_file.php";

    return WWPP_PLUGIN_DIR_PATH . "includes/$class_file";
}

/**
 * Namespaced autoload function for the plugin.
 *
 * @param string $class_name The class name.
 */
function autoload( $class_name ) {

    $file = '';
    if ( 'RymeraWebCo\WWPP' === mb_substr( $class_name, 0, 16 ) ) {

        $file = get_class_file_path( $class_name );

        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
}

try {
    spl_autoload_register( '\RymeraWebCo\WWPP\autoload' );
} catch ( \Exception $exception ) {
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
