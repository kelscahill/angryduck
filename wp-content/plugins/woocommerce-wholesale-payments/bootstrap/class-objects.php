<?php
/**
 * Class objects instance list.
 *
 * @since   1.0.0
 * @package RymeraWebCo\WPay
 */

use RymeraWebCo\WPay\Integrations\WP;
use RymeraWebCo\WPay\Classes\WP_Admin;
use RymeraWebCo\WPay\Factories\Admin_Script;
use RymeraWebCo\WPay\Factories\Frontend_Script;
use RymeraWebCo\WPay\Classes\License_Manager;
use RymeraWebCo\WPay\Classes\Settings_Page;
use RymeraWebCo\WPay\Classes\Update_Manager;
use RymeraWebCo\WPay\Post_Types\Payment_Plan_Post_Type;

defined( 'ABSPATH' ) || exit;

return array(
    new WP(),
    new Admin_Script(),
    new Frontend_Script(),
    new WP_Admin(),
    License_Manager::instance(),
    Update_Manager::instance(),
    new Payment_Plan_Post_Type(),
    Settings_Page::instance(),
);
