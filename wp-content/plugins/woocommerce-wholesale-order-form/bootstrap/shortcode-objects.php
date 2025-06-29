<?php
/**
 * WWOF shortcode objects.
 *
 * @package RymeraWebCo\WWOF
 */

use RymeraWebCo\WWOF\Shortcodes\WWOF_Product_Listing;

defined( 'ABSPATH' ) || exit;

return array(
    WWOF_Product_Listing::instance(),
);
