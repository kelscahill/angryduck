<?php
/**
 * Render the getting started notice markup.
 *
 * @package RymeraWebCo\WPay
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="updated notice wpay-getting-started is-dismissible">
    <p><img class="wws-logo" src="<?php echo esc_url( plugins_url( 'static/images/logo.svg', WPAY_PLUGIN_FILE ) ); ?>" alt="<?php esc_attr_e( 'Wholesale Suite logo', 'woocommerce-wholesale-payments' ); ?>"/></p>
    <p><?php esc_html_e( "Thank you for choosing Wholesale Payments - we're excited to help you bring payment plans & instalments to your wholesale customers.", 'woocommerce-wholesale-payments' ); ?>
    <p><?php esc_html_e( 'Would you like to find out how to drive the plugin? Check out our getting started guide.', 'woocommerce-wholesale-payments' ); ?>
    <p>
        <a href="https://wholesalesuiteplugin.com/kb/woocommerce-wholesale-payments-getting-started-guide/?utm_source=wpay&utm_medium=kb&utm_campaign=wpaygettingstarted" target="_blank">
            <?php esc_html_e( 'Read the Getting Started guide', 'woocommerce-wholesale-payments' ); ?>
            <span class="dashicons dashicons-arrow-right-alt" style="margin-top: 5px"></span>
        </a>
    </p>
</div>
