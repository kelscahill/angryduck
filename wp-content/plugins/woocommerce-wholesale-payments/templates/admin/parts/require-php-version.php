<?php
/**
 * Markup for displaying required PHP version to run the plugin.
 *
 * @package RymeraWebCo\WPay
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="error" id="message">
    <p>
        <?php
        printf( /* translators: %s: current server PHP version */
            esc_html__(
                'WooCommerce Wholesale Payments plugin requires at least PHP 7.4 to work properly. Your server is currently using PHP %s.',
                'woocommerce-wholesale-payments'
            ),
            PHP_VERSION
        );
        ?>
    </p>
</div>
