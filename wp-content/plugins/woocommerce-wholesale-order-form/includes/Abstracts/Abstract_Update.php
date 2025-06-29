<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Abstracts
 */

namespace RymeraWebCo\WWOF\Abstracts;

use RymeraWebCo\WWOF\Helpers\WWOF;
use RymeraWebCo\WWOF\Traits\Magic_Get_Trait;

/**
 * Abstract Update class.<br>
 * Update classes should extend this abstract class and implement the `actions()` method. It should do whatever is
 * needed to update the plugin except updating the version number in database as that is already done by default by the
 * abstract class, unless, `run()` method is overridden.
 *
 * @since 3.0
 */
abstract class Abstract_Update extends Abstract_Class {

    use Magic_Get_Trait;

    /**
     * Run updates.
     *
     * @since 3.0
     */
    public function run() {

        $this->actions();

        if ( version_compare( WWOF::get_current_plugin_version(), get_option( WWOF_OPTION_INSTALLED_VERSION ), '!=' ) ) {
            /***************************************************************************
             * Update plugin version installed in database
             ***************************************************************************
             *
             * We update the version value in the database to the current version of the
             * plugin.
             */
            if ( is_multisite() ) {
                update_site_option( WWOF_OPTION_INSTALLED_VERSION, WWOF::get_current_plugin_version() );
            } else {
                update_option( WWOF_OPTION_INSTALLED_VERSION, WWOF::get_current_plugin_version() );
            }
        }
    }

    /**
     * Perform update actions.
     *
     * @return void
     */
    abstract public function actions();
}
