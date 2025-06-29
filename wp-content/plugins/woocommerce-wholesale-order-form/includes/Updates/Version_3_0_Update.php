<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Updates
 */

namespace RymeraWebCo\WWOF\Updates;

use Exception;
use RymeraWebCo\WWOF\Abstracts\Abstract_Update;
use RymeraWebCo\WWOF\Classes\Migrate_Form_Data;
use RymeraWebCo\WWOF\Factories\Order_Form;
use RymeraWebCo\WWOF\Helpers\WWOF;

/**
 * Version_30_Update class.
 *
 * @since 3.0
 */
class Version_3_0_Update extends Abstract_Update {

    /**
     * Replaces marked placeholders: %random_id%, %rtl_alignment% with their respective values.
     *
     * @param mixed $element The element to generate a random ID for.
     *
     * @since 3.0
     * @return void
     */
    private function replace_placeholders( &$element ) {

        foreach ( $element as &$element_value ) {
            if ( is_array( $element_value ) ) {
                $this->replace_placeholders( $element_value );
            } elseif ( '%random_id%' === $element_value ) {
                $element_value = WWOF::generate_id( array_merge( $element, array( 'uniqid' => uniqid() ) ) );
            } elseif ( '%rtl_alignment%' === $element_value ) {
                $element_value = is_rtl() ? 'right' : 'left';
            } elseif ( '%box_alignment%' === $element_value ) {
                $element_value = is_rtl() ? 'flex-end' : 'flex-start';
            }
        }
    }

    /**
     * Run 3.0 update actions.
     *
     * @since 3.0
     */
    public function actions() {

        global $wpdb;

        /***************************************************************************
         * Delete old API keys
         ***************************************************************************
         *
         * We no longer need the old API keys since we are authenticating requests
         * in the context of the current user. So, we can safely delete the old
         * API keys.
         */
        $wpdb->delete( $wpdb->prefix . 'woocommerce_api_keys', array( 'description' => 'WWOF v2' ) );

        delete_option( 'wwof_v2_consumer_key' );
        delete_option( 'wwof_v2_consumer_secret' );

        /***************************************************************************
         * Migrate Order Form v2 Data
         ***************************************************************************
         *
         * We migrate the Order Form v2 data to the new Order Form v3 data.
         */
        $migrator = new Migrate_Form_Data();
        $result   = $migrator->run();
        if ( is_wp_error( $result ) ) {
            WWOF::log_error( $result->get_error_message() );
        }

        /***************************************************************************
         * Create an order form if one doesn't exist
         ***************************************************************************
         *
         * We create an order form if one doesn't exist. This is to ensure that
         * the user has at least one sample form that they can edit.
         */
        $forms = WWOF::get_forms(
            array(
                'posts_per_page' => 1,
            )
        );
        if ( empty( $forms ) ) {
            //phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            $json_str           = file_get_contents( WWOF_PLUGIN_DIR_PATH . '/data/order-form.json' );
            $initial_order_form = json_decode( $json_str, true );

            $form = new Order_Form();
            $form->set_form_property( 'post_title', $initial_order_form['title'] );
            $form->set_form_property( 'post_status', 'publish' );
            $this->replace_placeholders( $initial_order_form );
            $form->set_form_property( WWOF::FORM_HEADER, $initial_order_form['form_header'] );
            $form->set_form_property( WWOF::FORM_BODY, $initial_order_form['form_body'] );
            $form->set_form_property( WWOF::FORM_FOOTER, $initial_order_form['form_footer'] );
            $form->set_form_property( WWOF::FORM_SETTINGS, $initial_order_form['settings'] );

            try {
                $form->save();
            } catch ( Exception $e ) {
                WWOF::log_error( $e->getMessage() );
            }

            if ( ! WWOF::get_page_by_title( 'Wholesale Ordering' ) ) {
                $page_args = array(
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                    'post_title'   => 'Wholesale Ordering',
                    'post_content' => sprintf( '[wwof_product_listing id="%d"]', $form->ID ),
                );
                wp_insert_post( $page_args );
            }
        }
    }
}
