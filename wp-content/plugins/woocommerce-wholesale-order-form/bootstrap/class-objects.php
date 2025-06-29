<?php
/**
 * Plugin class objects.
 *
 * @since   3.0
 * @package RymeraWebCo\WWOF
 */

use RymeraWebCo\WWOF\Classes\License_Manager;
use RymeraWebCo\WWOF\Classes\Update_Manager;
use RymeraWebCo\WWOF\Post_Types\Order_Form_Post_Type;
use RymeraWebCo\WWOF\Classes\Order_Forms_Admin_Page;
use RymeraWebCo\WWOF\Classes\WP_Admin;

defined( 'ABSPATH' ) || exit;

return array(
    new Order_Form_Post_Type(),
    new WP_Admin(),
    Order_Forms_Admin_Page::instance(),
    License_Manager::instance(),
    Update_Manager::instance(),
);
