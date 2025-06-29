<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Factories
 */

namespace RymeraWebCo\WWOF\Factories;

use RymeraWebCo\WWOF\Abstracts\Abstract_Class;
use RymeraWebCo\WWOF\Helpers\WPML;
use RymeraWebCo\WWOF\Helpers\WWOF;

/**
 * Class Setup_Wizard
 *
 * @since 3.0
 */
class Setup_Wizard extends Abstract_Class {

    /**
     *  WC Admin Note unique name
     *
     * @since 2.0
     */
    const NOTE_NAME = 'wwof-setup-wizard-wc-inbox';

    /**
     * WWOF_Setup_Wizard_Note constructor.
     *
     * @since  2.0
     * @access public
     */
    public function __construct() {
    }

    /**
     * Insert setup wizard wc admin note.
     *
     * @since  2.0
     * @access public
     */
    public static function setup_wizard_note() {

        /*
        |--------------------------------------------------------------------------
        | Check WC Admin
        |--------------------------------------------------------------------------
        |
        | If WC Admin is not active then don't proceed.
        |
        */
        if ( ! WWOF::is_wc_admin_active() ) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Check if not fresh install
        |--------------------------------------------------------------------------
        */
        if ( ! WWOF::is_fresh_install() ) {

            /*
            |--------------------------------------------------------------------------
            | Check for template overrides or WPML/Addons active
            |--------------------------------------------------------------------------
            |
            | We don't want to display the notice if the user has template overrides
            | or WPML or Addons active.
            |
            */
            // Check if WWOF has Template Overrides or WPML or Addons active then dont display the notice.
            if ( WPML::is_active() || WWOF::is_product_addons_active() || WWOF::has_template_overrides() ) {
                return;
            }
        }

        if (
            get_option( WWOF_DISPLAY_WIZARD_NOTICE ) === 'yes' &&
            get_option( WWOF_WIZARD_SETUP_DONE ) !== 'yes'
        ) {

            try {

                $data_store = \WC_Data_Store::load( 'admin-note' );

                // We already have this note? Then exit, we're done.
                $note_ids = $data_store->get_notes_with_name( self::NOTE_NAME );
                if ( ! empty( $note_ids ) ) {
                    return;
                }

                $setup_wizard_link = admin_url( 'admin.php?page=order-forms-setup-wizard' );

                $note_content = sprintf(/* translators: %1$s = opening <strong> tag; %2$s = closing </strong> tag */
                    esc_html__(
                        'Congratulations! %1$sWholesale Order Forms%2$s plugin has been successfully installed and is ready to be set up.',
                        'woocommerce-wholesale-order-form'
                    ),
                    '<strong>',
                    '</strong>'
                );

                $note_content .= '<br/><br/>';
                $note_content .= sprintf(/* translators: %1$s = opening <strong> tag; %2$s = closing </strong> tag */
                    esc_html__(
                        'Get Started quickly by clicking %1$s"Start Setup"%2$s and we\'ll guide you through creating your first form.',
                        'woocommerce-wholesale-order-form'
                    ),
                    '<strong>',
                    '</strong>'
                );

                $note = WWOF::wc_admin_note_instance();
                $note->set_title( __( 'Wholesale Order Form Setup Wizard', 'woocommerce-wholesale-order-form' ) );
                $note->set_content( $note_content );
                $note->set_content_data( (object) array() );
                $note->set_type( $note::E_WC_ADMIN_NOTE_INFORMATIONAL );
                $note->set_name( self::NOTE_NAME );
                $note->set_source( 'woocommerce-admin' );
                $note->add_action(
                    'start-setup-wizard',
                    __( 'Start Setup Wizard', 'woocommerce-wholesale-order-form' ),
                    $setup_wizard_link,
                    $note::E_WC_ADMIN_NOTE_ACTIONED,
                    true
                );
                $note->save();

            } catch ( \Exception $e ) {
                return;
            }
        }
    }

    /**
     * Dismiss the admin note when the migration wizard is done.
     *
     * @param \WP_REST_Request $request Full data about the request.
     * @param array            $data    Additional data.
     *
     * @access public
     * @since  2.0
     */
    public function dismiss_admin_note_after_wizard_is_done( $request, $data ) {

        $this->set_admin_note_to_actioned();
    }

    /**
     * Set the admin note to actioned.
     *
     * @since  2.0
     * @access private
     */
    private function set_admin_note_to_actioned() {

        $data_store = \WC_Data_Store::load( 'admin-note' );
        $note_ids   = $data_store->get_notes_with_name( self::NOTE_NAME );

        if ( ! empty( $note_ids ) ) {

            $note_id = current( $note_ids );
            $note    = WWOF::wc_admin_note_instance( $note_id );

            $note->set_status( $note::E_WC_ADMIN_NOTE_ACTIONED );
            $note->save();
        }
    }

    /**
     * Run the setup wizard.
     */
    public function run() {

        $this->dismiss_setup_wizard_note();

        add_action( 'wwof_wizard_done', array( $this, 'dismiss_admin_note_after_wizard_is_done' ), 10, 2 );
    }

    /**
     * Dismisses the note if Setup Wizard is done.
     *
     * @since  2.0
     * @access public
     */
    public function dismiss_setup_wizard_note() {

        /*
        |--------------------------------------------------------------------------
        | Show Setup Wizard to admin only
        |--------------------------------------------------------------------------
        |
        | If the user is not admin then don't proceed.
        |
        */
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $wc_data = WWOF::get_woocommerce_data();

        if ( $wc_data && version_compare( $wc_data['Version'], '4.3.0', '>=' ) ) {

            global $wpdb;

            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}wc_admin_notes WHERE name = %s",
                    self::NOTE_NAME
                ),
                ARRAY_A
            );

            /*
            |--------------------------------------------------------------------------
            | Check column layout exists
            |--------------------------------------------------------------------------
            |
            | Check if column layout doesn't exist in wc_admin_notes then don't proceed.
            |
            */
            if ( empty( $row['layout'] ) ) {
                return;
            }
        }

        try {

            /*
            |--------------------------------------------------------------------------
            | Mark setup wizard note as actioned
            |--------------------------------------------------------------------------
            |
            | If the user has already completed the setup wizard then mark the note as
            | actioned.
            |
            */
            if ( get_option( WWOF_WIZARD_SETUP_DONE ) === 'yes' ) {

                $data_store = \WC_Data_Store::load( 'admin-note' );
                $note_ids   = $data_store->get_notes_with_name( self::NOTE_NAME );

                if ( ! empty( $note_ids ) ) {

                    $note_id = current( $note_ids );
                    $note    = WWOF::wc_admin_note_instance( $note_id );

                    $note->set_status( $note::E_WC_ADMIN_NOTE_ACTIONED );
                    $note->save();
                }
            }
        } catch ( \Exception $e ) {
            return;
        }
    }
}
