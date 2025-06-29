<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Classes
 */

namespace RymeraWebCo\WWOF\Updates;

use RymeraWebCo\WWOF\Abstracts\Abstract_Class;
use RymeraWebCo\WWOF\Helpers\WWOF;

/**
 * Class Version_1_7_Update
 *
 * @since 3.0
 */
class Version_1_7_Update extends Abstract_Class {

    /**
     * Run the class.
     *
     * @since 1.7
     * @return void
     */
    public function run() {

        global $wpdb;

        if ( version_compare( WWOF::get_current_plugin_version(), '1.17', '>=' ) ) {
            $default_settings = array(
                'selected_category'   => '',
                'filtered_categories' => array(),
                'tax_display'         => '',
                'excluded_categories' => array(),
                'subtotal_pretext'    => '',
                'subtotal_suffix'     => '',
            );

            /***************************************************************************
             * Add plugin version data to database
             ***************************************************************************
             *
             * Select all Order Form where the version is lower than v1.17 or has empty
             * version. Versioning started in v1.17 so empty version means its lower
             * than 1.17.
             */
            $sql1 = "SELECT p.ID
                        FROM $wpdb->posts p
                        LEFT JOIN $wpdb->postmeta pm
                            ON pm.post_id = p.ID AND pm.meta_key = '_wwof_version'
                        WHERE p.post_type = 'order_form'
                        AND (
                            IFNULL(pm.meta_value, '') = ''
                            OR
                            ( pm.meta_key = '_wwof_version' AND pm.meta_value < '1.17' ) )";

            /***************************************************************************
             * The combo-variation-dropdown string
             ***************************************************************************
             *
             * Check if meta value has combo-variation-dropdown string.
             */
            $sql2 = "SELECT p.ID, pm.meta_key
                        FROM $wpdb->posts p
                        LEFT JOIN $wpdb->postmeta pm
                            ON pm.post_id = p.ID
                        WHERE p.post_type = 'order_form'
                        AND pm.meta_value LIKE '%combo-variation-dropdown%'
                        AND p.ID in ( $sql1 )";

            /***************************************************************************
             * Ignore not prepared SQL statement
             ***************************************************************************
             *
             * The SQL statement has no user input so, it's safe to run.
             */
            //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $results = $wpdb->get_results( $sql2 );

            if ( ! empty( $results ) ) {

                foreach ( $results as $result ) {
                    $id   = $result->ID;
                    $key  = $result->meta_key;
                    $data = get_post_meta( $id, $key, true );

                    /***************************************************************************
                     * Styles
                     ***************************************************************************
                     *
                     * Migrate styles from combo-variation-dropdown to variation-dropdown.
                     */
                    if ( isset( $data['combo-variation-dropdown'] ) ) {
                        $data['variation-dropdown'] = $data['combo-variation-dropdown'];
                        unset( $data['combo-variation-dropdown'] );
                    }

                    /***************************************************************************
                     * Editor Area
                     ***************************************************************************
                     */
                    if ( isset( $data['formTable'] ) && isset( $data['formTable']['itemIds'] ) &&
                        in_array(
                            'combo-variation-dropdown',
                            $data['formTable']['itemIds'],
                            true
                        ) ) {
                        $index = array_search( 'combo-variation-dropdown', $data['formTable']['itemIds'], true );
                        if ( $index >= 0 ) {
                            $data['formTable']['itemIds'][ $index ] = 'variation-dropdown';
                        }
                    }

                    update_post_meta( $id, $key, $data );

                    /***************************************************************************
                     * Update settings data
                     ***************************************************************************
                     *
                     * Update Settings with default data if empty after update
                     */
                    $settings = get_post_meta( $id, 'settings', true );
                    if ( empty( $settings ) ) {
                        update_post_meta( $id, 'settings', $default_settings );
                    }
                }
            }
        }
    }
}
