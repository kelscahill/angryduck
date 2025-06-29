<?php
/**
 * REST objects.
 *
 * @since   1.0.0
 * @package RymeraWebCo\WPay
 */

use RymeraWebCo\WPay\REST\License_Manager;
use RymeraWebCo\WPay\REST\Payment_Plan;
use RymeraWebCo\WPay\REST\Rymera_Stripe;
use RymeraWebCo\WPay\REST\Invoice_Lists;

defined( 'ABSPATH' ) || exit;

return array(
    Payment_Plan::instance(),
    Rymera_Stripe::instance(),
    License_Manager::instance(),
    Invoice_Lists::instance(),
);
