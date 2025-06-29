<?php
/**
 * Integration instance objects.
 *
 * @package RymeraWebCo\WPay
 */

use RymeraWebCo\WPay\Integrations\WC;
use RymeraWebCo\WPay\Integrations\WWP;
use RymeraWebCo\WPay\Integrations\WPay_Block;

defined( 'ABSPATH' ) || exit;

return array(
    new WC(),
    new WWP(),
    new WPay_Block(),
);
