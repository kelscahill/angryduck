<?php
/**
 * Integration instance objects.
 *
 * @package RymeraWebCo\WWOF
 */

use RymeraWebCo\WWOF\Helpers\Site_Ground_Optimizer as SGO;
use RymeraWebCo\WWOF\Integrations\Aelia;
use RymeraWebCo\WWOF\Integrations\Site_Ground_Optimizer;
use RymeraWebCo\WWOF\Integrations\WC;
use RymeraWebCo\WWOF\Integrations\WP_Rocket;
use RymeraWebCo\WWOF\Integrations\WPML;
use RymeraWebCo\WWOF\Integrations\WWP;
use RymeraWebCo\WWOF\Integrations\WWPP;
use RymeraWebCo\WWOF\Integrations\Product_Addons;

defined( 'ABSPATH' ) || exit;

return array_filter(
    array(
        new WC(),
        new WWP(),
        new WWPP(),
        new WPML(),
        new Aelia(),
        new Site_Ground_Optimizer(),
        new WP_Rocket(),
        new Product_Addons(),
    ),
);
