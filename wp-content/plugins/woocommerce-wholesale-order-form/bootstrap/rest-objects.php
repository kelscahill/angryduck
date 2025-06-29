<?php
/**
 * REST objects.
 *
 * @since   3.0
 * @package RymeraWebCo\WWOF
 */

use RymeraWebCo\WWOF\REST\Admin_Notices;
use RymeraWebCo\WWOF\REST\Favourites;
use RymeraWebCo\WWOF\REST\License_Manager;
use RymeraWebCo\WWOF\REST\Order_Form;
use RymeraWebCo\WWOF\REST\Setup_Wizard;

defined( 'ABSPATH' ) || exit;

return array(
    Setup_Wizard::instance(),
    License_Manager::instance(),
    Admin_Notices::instance(),
    Order_Form::instance(),
    Favourites::instance(),
);
