<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WPay
 */

namespace RymeraWebCo\WPay\Abstracts;

use RymeraWebCo\WPay\Traits\Magic_Get_Trait;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract Class
 */
abstract class Abstract_Class {

    use Magic_Get_Trait;

    /**
     * Run the class
     *
     * @codeCoverageIgnore
     * @since 1.0.0
     */
    abstract public function run();
}
