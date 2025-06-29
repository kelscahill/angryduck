<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Updates
 */

namespace RymeraWebCo\WWOF\Updates;

use RymeraWebCo\WWOF\Abstracts\Abstract_Class;

/**
 * Class Version_2_0_Update
 *
 * @since 3.0
 */
class Version_2_0_Update extends Abstract_Class {

    /**
     * Run the update.
     *
     * @since 2.0
     */
    public function run() {

        global $wpdb;

        /*
        |--------------------------------------------------------------------------
        | Consumer key update
        |--------------------------------------------------------------------------
        |
        | Replace consumer key option key
        |
        */
        if ( get_option( 'wwof_v2_consumer_key' ) === false ) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE $wpdb->options SET option_name = %s WHERE option_name = %s",
                    'wwof_v2_consumer_key',
                    'wwof_order_form_v2_consumer_key'
                )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Consumer secret update
        |--------------------------------------------------------------------------
        |
        | Replace consumer secret option key
        |
        */
        if ( get_option( 'wwof_v2_consumer_secret' ) === false ) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE $wpdb->options SET option_name = %s WHERE option_name = %s",
                    'wwof_v2_consumer_secret',
                    'wwof_order_form_v2_consumer_secret'
                )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Remove beta="true"
        |--------------------------------------------------------------------------
        |
        | Removes beta="true" from order form shortcodes.
        |
        */
        $wpdb->query(
            "UPDATE $wpdb->posts SET post_content = replace(post_content, ' beta=\"true\"', '') WHERE post_type = 'order_form'"
        );
    }
}
