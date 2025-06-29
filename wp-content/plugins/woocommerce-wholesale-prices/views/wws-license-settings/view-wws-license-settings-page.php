<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$wws_license_tab = $_GET['tab'] ?? 'wwpp'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>

<h2 class="nav-tab-wrapper">
    <b style="margin-left: 15px; margin-top: 6px; display: inline-block;"><?php esc_html_e( 'WWS License Settings', 'woocommerce-wholesale-prices' ); ?></b>
    <?php do_action( 'wws_action_license_settings_tab' ); ?>
</h2>

<?php do_action( 'wws_action_license_settings_' . $wws_license_tab ); ?>