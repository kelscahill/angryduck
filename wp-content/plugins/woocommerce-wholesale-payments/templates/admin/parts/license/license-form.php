<?php
/**
 * License Form template.
 *
 * @package RymeraWebCo\WPay
 */

use RymeraWebCo\WPay\Helpers\License;

defined( 'ABSPATH' ) || exit;

$display_expired_notice = is_numeric( License::get_license_expiration_date() );
?>
<div id="wws_settings_wpay" class="wws_license_settings_page_container">
    <!-- License Form Vue App -->
</div>
