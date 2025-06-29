<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WPay
 */

namespace RymeraWebCo\WPay;

use RymeraWebCo\WPay\Actions\Activation;
use RymeraWebCo\WPay\Actions\Deactivation;
use RymeraWebCo\WPay\Factories\Admin_Notice;
use RymeraWebCo\WPay\Helpers\Helper;
use RymeraWebCo\WPay\Traits\Singleton_Trait;

defined( 'ABSPATH' ) || exit;

require_once WPAY_PLUGIN_DIR_PATH . 'includes/autoload.php';

/**
 * Class App
 */
class App {

    use Singleton_Trait;

    /**
     * Holds the class instance object
     *
     * @var App $instance object
     * @since 3.0.6
     */
    protected static $instance;

    /**
     * Holds the class object instances.
     *
     * @var array An array of object class instance.
     */
    protected $objects;

    /**
     * App constructor.
     */
    public function __construct() {

        $this->objects = array();
    }

    /**
     * Called at the end of file to initialize autoloader
     */
    public function boot() {

        register_activation_hook( WPAY_PLUGIN_FILE, array( $this, 'activation_actions' ) );
        register_deactivation_hook( WPAY_PLUGIN_FILE, array( $this, 'deactivation_actions' ) );

        /***************************************************************************
         * Run the plugin
         ***************************************************************************
         *
         * We run the plugin classes on `setup_theme` hook with priority 100 as
         * we depend on WooCommerce plugin to be loaded first and we need to make
         * sure that WP_Rewrite global object is already available.
         */
        add_action( 'setup_theme', array( $this, 'run' ), 100 );

        /***************************************************************************
         * Maybe add HTML5 support
         ***************************************************************************
         *
         * We need HTML5 support for the theme in order for the newer script tag
         * attributes to work (_i.e._ `type="module"`).
         */
        add_action( 'after_setup_theme', array( $this, 'maybe_add_html5_support' ), 999 );
    }

    /**
     * Enables HTML5 support for the theme if not already. We require this in order for the newer script tag
     * attributes to work (_i.e._ `type="module"`).
     *
     * @since 1.0.0
     * @return void
     */
    public function maybe_add_html5_support() {

        if ( current_theme_supports( 'html5', 'script' ) ) {
            return;
        }

        add_theme_support( 'html5', array( 'script' ) );
    }

    /**
     * Register classes to run.
     *
     * @param array $objects Array of class instances.
     *
     * @return void
     */
    public function register_objects( $objects ) {

        $this->objects = array_merge( $this->objects, $objects );
    }

    /**
     * Plugin activation actions
     *
     * @param bool $sitewide Whether the plugin is being activated network-wide.
     */
    public function activation_actions( $sitewide ) {

        /***************************************************************************
         * Plugin activation actions
         ***************************************************************************
         *
         * We run the plugin actions here when it's activated.
         */
        ( new Activation( $sitewide ) )->run();

        flush_rewrite_rules();
    }

    /**
     * Run the plugin classes.
     *
     * @return void
     */
    public function run() {

        /***************************************************************************
         * Check required plugins
         ***************************************************************************
         *
         * We check if the required plugins are active.
         */

        $missing_required_plugins = Helper::missing_required_plugins();
        $wpay_plugin_data         = Helper::get_plugin_data();

        $admin_notice = null;
        if ( ! empty( $missing_required_plugins ) ) {

            $required_plugins = array();
            foreach ( $missing_required_plugins as $missing_required_plugin ) {
                $plugin_file = WP_PLUGIN_DIR . "/{$missing_required_plugin['plugin-base']}";
                if ( file_exists( $plugin_file ) ) {
                    $plugin_data = get_plugin_data( $plugin_file, false, false );

                    $required_plugins[] = sprintf(/* translators: %1$s = opening <a> tag; %2$s = closing </a> tag */
                        esc_html__(
                            '%1$sClick here to activate %3$s plugin &rarr;%2$s',
                            'woocommerce-wholesale-payments'
                        ),
                        sprintf(
                            '<a href="%s" title="%s">',
                            wp_nonce_url(
                                self_admin_url(
                                    'plugins.php?action=activate&plugin='
                                ) . $missing_required_plugin['plugin-base'],
                                'activate-plugin_' . $missing_required_plugin['plugin-base']
                            ),
                            esc_attr__( 'Activate this plugin', 'woocommerce-wholesale-payments' )
                        ),
                        '</a>',
                        $plugin_data['Name']
                    );
                } else {

                    $message = '';
                    if ( false !== strpos( $missing_required_plugin['plugin-base'], 'woocommerce.php' ) ) {
                        $message .= sprintf(/* translators: %1$s = opening <p> tag; %2$s = closing </p> tag; %3$s = WooCommerce Wholesale Payments */
                            esc_html__(
                                '%1$sUnable to activate %3$s plugin. Please install and activate WooCoomerce plugin first.%2$s',
                                'woocommerce-wholesale-payments'
                            ),
                            '<p>',
                            '</p>',
                            $wpay_plugin_data['Name']
                        );
                    }

                    $message .= sprintf(/* translators: %1$s = opening <a> tag; %2$s = closing </a> tag */
                        esc_html__(
                            '%1$sClick here to install %3$s plugin &rarr;%2$s',
                            'woocommerce-wholesale-payments'
                        ),
                        sprintf(
                            '<a href="%s" title="%s">',
                            wp_nonce_url(
                                self_admin_url(
                                    'update.php?action=install-plugin&plugin='
                                ) . $missing_required_plugin['plugin-key'],
                                'install-plugin_' . $missing_required_plugin['plugin-key']
                            ),
                            esc_attr__( 'Install this plugin', 'woocommerce-wholesale-payments' )
                        ),
                        '</a>',
                        $missing_required_plugin['plugin-name']
                    );

                    $required_plugins[] = $message;
                }
            }

            /***************************************************************************
             * Required plugins admin notice
             ***************************************************************************
             *
             * We display an admin notice for the missing required plugins.
             */
            $admin_notice = new Admin_Notice(
                sprintf(/* translators: %1$s = opening <strong> tag; %2$s = closing </strong> tag; %3$s = opening <p> tag; %4$s = closing </p> tag */
                    esc_html__(
                        '%3$s%1$sWooCommerce Wholesale Payments%2$s Form plugin missing dependency:%4$s',
                        'woocommerce-wholesale-payments'
                    ),
                    '<strong>',
                    '</strong>',
                    '<p>',
                    '</p>'
                ) . '<p>' . implode( '</p><p>', $required_plugins ) . '</p>',
                'error',
                'html'
            );
        }

        /***************************************************************************
         * Required plugins check failed
         ***************************************************************************
         *
         * If the required plugins check failed we display an admin notice and bail.
         */
        if ( null !== $admin_notice ) {
            $admin_notice->run();

            return;
        }

        /***************************************************************************
         * We make sure that the classes to be run extends the abstract class.
         ***************************************************************************
         *
         * We make sure that the classes to be run extends the abstract class or has
         * implemented a `run` method.
         */
        foreach ( $this->objects as $object ) {
            if ( ! method_exists( $object, 'run' ) ) {
                _doing_it_wrong(
                    __METHOD__,
                    esc_html__(
                        'The class does not have a run method. Please make sure to extend the Abstract_Class class.',
                        'woocommerce-wholesale-payments'
                    ),
                    esc_html( Helper::get_plugin_data( 'Version' ) )
                );
                continue;
            }
            $class_object = strtolower( wp_basename( get_class( $object ) ) );

            $this->objects[ $class_object ] = apply_filters(
                'wpay_class_object',
                $object,
                $class_object,
                $this
            );
            $this->objects[ $class_object ]->run();
        }
    }

    /**
     * Plugin deactivation actions
     *
     * @param bool $sitewide Whether the plugin is being deactivated network-wide.
     */
    public function deactivation_actions( $sitewide ) {

        /***************************************************************************
         * Plugin deactivation actions
         ***************************************************************************
         *
         * We run the plugin actions here when it's deactivated.
         */
        ( new Deactivation( $sitewide ) )->run();

        flush_rewrite_rules();
    }
}

/***************************************************************************
 * Instantiate classes
 ***************************************************************************
 *
 * Instantiate classes to be registered and run.
 */
App::instance()->register_objects(
    array_merge(
        require_once WPAY_PLUGIN_DIR_PATH . 'bootstrap/rest-objects.php',
        require_once WPAY_PLUGIN_DIR_PATH . 'bootstrap/class-objects.php',
        require_once WPAY_PLUGIN_DIR_PATH . 'bootstrap/integration-objects.php',
    )
);

return App::instance();
