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
 * Migration Wizard class.
 *
 * @depecated 3.0
 */
class Migration_Wizard extends Abstract_Class {

    /**
     *  WC Admin Note unique name
     *
     * @since 2.0
     */
    const NOTE_NAME = 'wwof-migration-wizard-wc-inbox';

    /**
     * WWOF_Migration_Wizard_Note constructor.
     *
     * @since  2.0
     * @access public
     */
    public function __construct() {

    }

    /**
     * Insert migration wizard wc admin note.
     *
     * @since  2.0
     * @access public
     */
    public static function migration_wizard_note() {

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

        if ( ! WWOF::is_fresh_install() &&
            ( WPML::is_active() || WWOF::is_product_addons_active() || WWOF::has_template_overrides() ) ) {

            return;
        }

        if (
            get_option( WWOF_DISPLAY_WIZARD_NOTICE ) === 'yes' &&
            get_option( WWOF_WIZARD_SETUP_DONE ) !== 'yes'
        ) {

            try {

                $data_store = \WC_Data_Store::load( 'admin-note' );

                /*
                |--------------------------------------------------------------------------
                | Check if note exists
                |--------------------------------------------------------------------------
                |
                | We already have this note? Then bail.
                |
                */
                $note_ids = $data_store->get_notes_with_name( self::NOTE_NAME );
                if ( ! empty( $note_ids ) ) {
                    return;
                }

                $migration_wizard_link = admin_url( 'admin.php?page=order-forms-setup-wizard&migration=true' );

                $note_content = sprintf(/* translators: %1$s = opening <strong>; %2$s = closing </strong> */
                    esc_html__(
                        'Congrats! %1$sWholesale Order Form%2$s 2.0 introduces a new form builder, multiple forms, and lots of great new options making it more powerful than ever.',
                        'woocommerce-wholesale-order-form'
                    ),
                    '<strong>',
                    '</strong>'
                );

                $note_content .= '<br/><br/>';
                $note_content .= __(
                    'Get started by migrating your old form over to the new style. If you\'re not ready yet, you can choose to do it later via the Order Form Settings area.',
                    'woocommerce-wholesale-order-form'
                );

                $note = WWOF::wc_admin_note_instance();
                $note->set_title( __( 'Wholesale Order Form Migration Wizard', 'woocommerce-wholesale-order-form' ) );
                $note->set_content( $note_content );
                $note->set_content_data( (object) array() );
                $note->set_type( $note::E_WC_ADMIN_NOTE_INFORMATIONAL );
                $note->set_name( self::NOTE_NAME );
                $note->set_source( 'woocommerce-admin' );
                $note->add_action(
                    'start-migration-wizard',
                    __( 'Start Migration Wizard', 'woocommerce-wholesale-order-form' ),
                    $migration_wizard_link,
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
     * Run on plugin activation.
     */
    public function run() {

        $this->dismiss_migration_wizard_note();

        /*
        |--------------------------------------------------------------------------
        | Check if migration wizard is done
        |--------------------------------------------------------------------------
        |
        | Dismiss after the wizard is done
        |
        */
        add_action( 'wwof_wizard_done', array( $this, 'dismiss_admin_note_after_wizard_is_done' ), 10, 2 );
    }

    /**
     * Dismisses the note if Migration Wizard is done.
     *
     * @since  2.0
     * @access public
     */
    public function dismiss_migration_wizard_note() {

        /*
        |--------------------------------------------------------------------------
        | Check WC Admin
        |--------------------------------------------------------------------------
        |
        | If WC Admin is not active then don't proceed
        |
        */
        if ( ! WWOF::is_wc_admin_active() ) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Check user capability
        |--------------------------------------------------------------------------
        |
        | We check if current user has the capability to manage options.
        |
        */
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $wc_data = WWOF::get_woocommerce_data();

        if ( $wc_data && version_compare( $wc_data['Version'], '4.3.0', '>=' ) ) {

            global $wpdb;

            $note_name = self::NOTE_NAME;
            $row       = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}wc_admin_notes WHERE name = %s",
                    $note_name
                ),
                ARRAY_A
            );

            /*
            |--------------------------------------------------------------------------
            | Check if layout is set
            |--------------------------------------------------------------------------
            |
            | Check if column layout doesn't exist in wc_admin_notes then don't proceed
            |
            */
            if ( empty( $row['layout'] ) ) {
                return;
            }
        }

        try {

            if ( get_option( WWOF_WIZARD_SETUP_DONE ) === 'yes' ) {
                $this->set_admin_note_to_actioned();
            }
        } catch ( \Exception $e ) {
            return;
        }
    }
}
