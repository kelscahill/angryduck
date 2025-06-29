<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WPay\Abstracts
 */

namespace RymeraWebCo\WPay\Abstracts;

use RymeraWebCo\WPay\Helpers\Helper;
use RymeraWebCo\WPay\Traits\Magic_Get_Trait;

/**
 * Abstract Update class.<br>
 * Update classes should extend this abstract class and implement the `actions()` method. It should do whatever is
 * needed to update the plugin except updating the version number in database as that is already done by default by the
 * abstract class, unless, `run()` method is overridden.
 *
 * @since 1.0.0
 */
abstract class Abstract_Update extends Abstract_Class {

    use Magic_Get_Trait;

    /**
     * Run updates.
     *
     * @since 1.0.0
     */
    public function run() {

        $this->actions();

        Helper::update_installed_plugin_version( Helper::get_plugin_data( 'Version' ) );
    }

    /**
     * Perform update actions.
     *
     * @return void
     */
    abstract public function actions();
}
