<?php
/**
 * Author: Rymera Web Co
 *
 * @since   1.15.3
 * @package RymeraWebCo\WWOF
 */

use RymeraWebCo\WWOF\Classes\WP_Admin;
use RymeraWebCo\WWOF\Helpers\WWOF;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Available variables:
 *
 * @var WP_Admin $this
 */

$wwp_active = is_plugin_active( 'woocommerce-wholesale-prices/woocommerce-wholesale-prices.bootstrap.php' );
$wwp_notice = get_option( 'wwp_admin_notice_getting_started_show' );

$wwpp_active = is_plugin_active(
    'woocommerce-wholesale-prices-premium/woocommerce-wholesale-prices-premium.bootstrap.php'
);
$wwpp_notice = get_option( 'wwpp_admin_notice_getting_started_show' );

$active_counter = 1;

if ( ( $wwp_active && 'yes' === $wwp_notice ) || ( $wwpp_active && 'yes' === $wwpp_notice ) ) {
    ++$active_counter;
}

$wwp_getting_started_link = 'https://wholesalesuiteplugin.com/kb/woocommerce-wholesale-prices-free-plugin-getting-started-guide/?utm_source=freeplugin&utm_medium=kb&utm_campaign=wwpgettingstarted';

$wwpp_getting_started_link = 'https://wholesalesuiteplugin.com/kb/woocommerce-wholesale-prices-premium-getting-started-guide/?utm_source=wwpp&utm_medium=kb&utm_campaign=wwppgettingstarted';

$wwof_getting_started_link = 'https://wholesalesuiteplugin.com/kb/woocommerce-wholesale-order-form-getting-started-guide/?utm_source=wwof&utm_medium=kb&utm_campaign=wwofgettingstarted';

/*
|--------------------------------------------------------------------------
| Check current user permissions
|--------------------------------------------------------------------------
|
| Check if current user is admin or shop manager and check if getting
| started option is 'yes'.
|
*/
if ( current_user_can( 'manage_woocommerce' ) && WWOF::get_getting_started_notice() === 'yes' ) :

    $screen = get_current_screen();

    $wws_activation_notice_logo = sprintf(
        '%swholesale-suite-activation-notice-logo.png',
        WWOF_PLUGIN_DIR_URL . 'static/images/'
    );

    if ( $this->show_getting_started_notice ) :
        if ( $active_counter > 1 ) :
            ?>
            <div class="updated notice wwof-getting-started">
                <p>
                    <img
                        src="<?php echo esc_attr( $wws_activation_notice_logo ); ?>"
                        alt="Wholesale suite activation notice image"
                    />
                </p>
                <p>
                    <?php
                    esc_html_e(
                        'Thank you for choosing Wholesale Suite – the most complete wholesale solution for building wholesale sales into your existing WooCommerce driven store.',
                        'woocommerce-wholesale-order-form'
                    );
                    ?>
                </p>
                <p>
                    <?php
                    esc_html_e(
                        'To help you get up and running as quickly and as smoothly as possible, we\'ve published a number of getting started guides for our tools. You\'ll find links to these at any time inside the Help section in the settings for each plugin, but here are the links below so you can read them now.',
                        'woocommerce-wholesale-order-form'
                    );
                    ?>
                </p>
                <p>
                    <?php
                    if ( $wwpp_active && 'yes' === $wwpp_notice ) :
                        ?>
                        <a href="<?php echo esc_attr( $wwpp_getting_started_link ); ?>" target="_blank">
                            <?php esc_html_e( 'Wholesale Prices Premium Guide', 'woocommerce-wholesale-order-form' ); ?>
                            <span class="dashicons dashicons-arrow-right-alt" style="margin-top: 5px"></span>
                        </a>
                    <?php elseif ( $wwp_active && 'yes' === $wwp_notice ) : ?>
                        <a href="<?php echo esc_attr( $wwp_getting_started_link ); ?>" target="_blank">
                            <?php esc_html_e( 'Wholesale Prices Guide', 'woocommerce-wholesale-order-form' ); ?>
                            <span class="dashicons dashicons-arrow-right-alt" style="margin-top: 5px"></span>
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo esc_attr( $wwof_getting_started_link ); ?>" target="_blank">
                        <?php esc_html_e( 'Wholesale Order Form Guide', 'woocommerce-wholesale-order-form' ); ?>
                        <span class="dashicons dashicons-arrow-right-alt" style="margin-top: 5px"></span>
                    </a>
                </p>
                <button type="button" class="notice-dismiss">
                <span class="screen-reader-text">
                    <?php
                    esc_html_e(
                        'Dismiss this notice.',
                        'woocommerce-wholesale-order-form'
                    );
                    ?>
                </span>
                </button>
            </div>
        <?php else : ?>
            <div class="updated notice wwof-getting-started">
                <p>
                    <img
                        src="<?php echo esc_attr( $wws_activation_notice_logo ); ?>"
                        alt="Wholesale suite activation notice image"
                    />
                </p>
                <p>
                    <?php
                    esc_html_e(
                        'Thank you for choosing Order Form to provide an efficient, optimized ordering experience for your wholesale customers. We know they\'re going to love it!',
                        'woocommerce-wholesale-order-form'
                    );
                    ?>
                <p>
                    <?php
                    esc_html_e(
                        'The plugin has already created an order form page which you\'ll find under the Wholesale menu. We highly recommend reading the getting started guide to help you get up to speed on customizing order form experience.',
                        'woocommerce-wholesale-order-form'
                    );
                    ?>
                <p>
                    <a href="<?php echo esc_attr( $wwof_getting_started_link ); ?>" target="_blank">
                        <?php esc_html_e( 'Read the Getting Started guide', 'woocommerce-wholesale-order-form' ); ?>
                        <span class="dashicons dashicons-arrow-right-alt" style="margin-top: 5px"></span>
                    </a>
                </p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">
                        <?php
                        esc_html_e(
                            'Dismiss this notice.',
                            'woocommerce-wholesale-order-form'
                        );
                        ?>
                    </span>
                </button>
            </div>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
<?php
