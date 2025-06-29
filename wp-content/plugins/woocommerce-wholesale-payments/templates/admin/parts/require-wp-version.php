<?php
/**
 * Template part for displaying required WordPress version as admin notices.
 *
 * @package RymeraWebCo\WPay
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="error" id="message">
    <p>
        <?php
        printf(/* translators: %s = plugin name wrapped in <strong> tags */
            esc_html__( 'Plugin %s requires at least WordPress version 5.9.0 to work properly.', 'woocommerce-wholesale-payments' ),
            '<strong>' . esc_html__( 'Wholesale Payments', 'woocommerce-wholesale-payments' ) . '</strong>'
        );
        ?>
    </p>
</div>
