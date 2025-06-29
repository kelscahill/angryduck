<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Classes\Plugin_Activation
 */

namespace RymeraWebCo\WWOF\Updates;

use RymeraWebCo\WWOF\Abstracts\Abstract_Class;
use RymeraWebCo\WWOF\Helpers\WWOF;

/**
 * Class Version_1_21_Update
 *
 * @since 3.0
 */
class Version_1_21_Update extends Abstract_Class {

    /**
     * Run the class.
     *
     * @since 1.21
     */
    public function run() {

        global $wpdb;

        if ( version_compare( WWOF::get_current_plugin_version(), '1.21', '>=' ) ) {

            /***************************************************************************
             * Update plugin version data to database
             ***************************************************************************
             *
             * Select all Order Form where the version is lower than v1.21 or has empty
             * version. Versioning started in v1.17 so empty version means it's lower
             * than 1.17
             */
            $sql = "SELECT p.ID
                        FROM $wpdb->posts p
                        LEFT JOIN $wpdb->postmeta pm
                            ON pm.post_id = p.ID
                        WHERE p.post_type = 'order_form'
                        AND (
                            ifnull(pm.meta_value, '') = ''
                            OR
                            ( pm.meta_key = '_wwof_version' AND pm.meta_value < '1.21' )
                        )
                        AND pm.meta_value NOT LIKE '%product-meta%'";

            /***************************************************************************
             * Ignore unprepared SQL statement
             ***************************************************************************
             *
             * There are no user inputs in the SQL statement so, it's safe to run.
             */
            //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $order_forms = $wpdb->get_results( $sql );

            if ( ! empty( $order_forms ) ) {
                foreach ( $order_forms as $order_form ) {
                    $form_elements = get_post_meta( $order_form->ID, 'form_elements', true );

                    if ( ! empty( $form_elements['tableElements']['itemIds'] ) &&
                        ! in_array( 'product-meta', $form_elements['tableElements']['itemIds'], true ) ) {
                        $form_elements['tableElements']['itemIds'][] = 'product-meta';

                        /***************************************************************************
                         * Add additional product meta
                         ***************************************************************************
                         *
                         * Add Product Meta draggable element in the Table Column setting.
                         */
                        update_post_meta( $order_form->ID, 'form_elements', $form_elements );
                    }
                }
            }
        }
    }
}
