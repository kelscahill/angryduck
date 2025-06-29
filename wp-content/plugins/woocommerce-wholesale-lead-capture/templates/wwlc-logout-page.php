<?php
/**
 * The template to display if a logged in wholesale user visits the wholesale log in page.
 *
 * Override this template by copying it to yourtheme/woocommerce/wwlc-logout-form.php
 *
 * @author 		Rymera Web Co
 * @package 	WooCommerceWholeSaleLeadCapture/Templates
 * @version     1.3.1
 */

if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// NOTE: Don't Remove any ID or Classes inside this template when overriding it.
// Some JS Files Depend on it. You are free to add ID and Classes without any problem.

$current_user = wp_get_current_user();
$name = $current_user->user_firstname;
if ( !$name )
    $name = $current_user->user_login; ?>

<div class="woocommerce">
    <div id="wwlc-logout">
        <p>
            <?php echo sprintf( __( 'Hello %1$s' , 'woocommerce-wholesale-lead-capture' ) , "<em>$name</em>" ); ?>
            <a href="<?php echo esc_url( wp_logout_url() ); ?>"><?php _e( 'Log Out' , 'woocommerce-wholesale-lead-capture' ); ?></a>
        </p>
    </div><!--#wwlc-logout-->
</div><!--.woocommerce-->
