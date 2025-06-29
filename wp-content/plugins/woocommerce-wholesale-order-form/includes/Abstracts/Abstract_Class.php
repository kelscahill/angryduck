<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF
 */

namespace RymeraWebCo\WWOF\Abstracts;

use RymeraWebCo\WWOF\Traits\Magic_Get_Trait;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract Class
 *
 * @since 3.0
 */
abstract class Abstract_Class {

    use Magic_Get_Trait;

    /**
     * Run the class
     *
     * @codeCoverageIgnore
     * @since 3.0
     */
    abstract public function run();
}
