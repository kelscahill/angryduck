<?php
/**
 * Wholesale Order Form Admin page
 *
 * @package RymeraWebCo\WWOF
 */

if ( ! defined( 'ABSPATH' ) ) {
    die( 'You are not allowed to call this page directly.' );
}
?>
<div class="wrap">
    <h2><?php esc_html_e( 'Wholesale Order Form', 'woocommerce-wholesale-order-form' ); ?></h2>
    <div id="wwof-notices"></div>
    <div id="wwof-order-forms-admin" class="wwof-tw"></div>
</div><!-- #wwof-wrap -->
