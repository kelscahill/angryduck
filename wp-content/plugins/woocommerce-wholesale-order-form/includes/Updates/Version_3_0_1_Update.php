<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Updates
 */

namespace RymeraWebCo\WWOF\Updates;

use RymeraWebCo\WWOF\Abstracts\Abstract_Update;
use RymeraWebCo\WWOF\Helpers\WP_Rocket as WP_Rocket_Helper;

/**
 * Version_3_0_1_Update class.
 *
 * @since 3.0.1
 */
class Version_3_0_1_Update extends Abstract_Update {

    /**
     * Clean entire cache.
     *
     * Should be called on plugin activation.
     *
     * @since 3.0.1
     * @return void
     */
    public function clean_cache() {

        if ( ! WP_Rocket_Helper::is_active() || ! function_exists( 'rocket_clean_domain' ) ) {
            return;
        }

        // Purge entire WP Rocket cache.
        rocket_clean_domain();
    }

    /**
     * Run 3.0.1 update actions.
     */
    public function actions() {

        $this->clean_cache();
    }
}
