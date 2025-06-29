<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\CLI
 */

namespace RymeraWebCo\WWOF\CLI;

use RymeraWebCo\WWOF\Classes\Migrate_Form_Data;
use RymeraWebCo\WWOF\Factories\Order_Form;
use RymeraWebCo\WWOF\Factories\Order_Forms_Query;
use Symfony\Component\Yaml\Yaml;
use WP_CLI;
use WP_CLI\ExitException;

defined( 'ABSPATH' ) || exit;

/**
 * Cli class.
 *
 * @since 3.0
 */
class Cli extends \WP_CLI_Command {

    /**
     * Command name.
     *
     * @var string
     */
    const COMMAND = 'wwof';

    /**
     * Migrates WWOF forms settings to the new format.
     *
     * ## OPTIONS
     * [<id>...]
     * : One or more IDs of the forms to migrate. Omitting this will migrate all forms.
     *
     * ## EXAMPLES
     *      wp wwof migrate_forms_settings
     *      wp wwof migrate_forms_settings <id>...
     *
     * @param array $args       Arguments.
     * @param array $assoc_args Associative arguments.
     *
     * @since 3.0
     */
    public function migrate_forms_settings( $args = array(), $assoc_args = array() ) {

        $form_ids = array();
        if ( ! empty( $args ) ) {
            $form_ids = array_map( 'absint', $args );
        }

        $migrator = new Migrate_Form_Data( $form_ids );
        $result   = $migrator->run();

        if ( is_wp_error( $result ) ) {
            WP_CLI::error( $result->get_error_message() );
        }

        $form_count = count( $form_ids );
        if ( $form_count > 0 ) {
            WP_CLI::success(
                sprintf(/* translators: %d = number of order forms migrated */
                    _n(
                        'Migrated %d Order Form to v3.',
                        '%d Order Forms migrated to v3.',
                        $form_count,
                        'woocommerce-wholesale-order-form'
                    ),
                    $form_count
                )
            );
        } else {
            WP_CLI::success( __( 'Order Forms migrated to v3.', 'woocommerce-wholesale-order-form' ) );
        }
    }

    /**
     * Migrates WWOF forms settings to the new format.
     *
     * ## OPTIONS
     * <json_file_path>
     * : The path to the JSON file containing the forms to import.
     *
     * ## EXAMPLES
     *      wp wwof import_v2_forms_from_json_file path/to/orderForms.json
     *
     * @param array $args       Arguments.
     * @param array $assoc_args Associative arguments.
     *
     * @since 3.0
     * @throws ExitException Exit exception.
     */
    public function import_v2_forms_from_json_file( $args = array(), $assoc_args = array() ) {

        //phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $json  = file_get_contents( $args[0] );
        $forms = json_decode( $json, true );
        if ( ! empty( $forms ) ) {
            foreach ( $forms as $index => $form ) {
                $post_id = wp_insert_post(
                    array(
                        'post_title'  => $form['title'] ?? "Order Form {$index}",
                        'post_type'   => Order_Form::POST_TYPE,
                        'post_status' => $form['status'] ?? 'publish',
                    ),
                    true
                );
                if ( is_wp_error( $post_id ) ) {
                    WP_CLI::error( $post_id->get_error_message(), false );
                    continue;
                }
                if ( ! empty( $form['form_elements'] ) ) {
                    update_post_meta( $post_id, 'form_elements', $form['form_elements'] );
                }
                if ( ! empty( $form['editor_area'] ) ) {
                    update_post_meta( $post_id, 'editor_area', $form['editor_area'] );
                }
                if ( ! empty( $form['styles'] ) ) {
                    update_post_meta( $post_id, 'styles', $form['styles'] );
                }
                if ( ! empty( $form['settings'] ) ) {
                    update_post_meta( $post_id, 'settings', $form['settings'] );
                }
            }

            WP_CLI::success( __( 'Order Forms imported.', 'woocommerce-wholesale-order-form' ) );

            return;
        }

        WP_CLI::line( __( 'No Order Forms found in the JSON file.', 'woocommerce-wholesale-order-form' ) );
    }

    /**
     * Export order forms in given format. Either JSON or YAML.
     *
     * ## OPTIONS
     * [<format>]
     * : The format to export the order forms in. Valid values are: json, yml (default).
     *
     * ## EXAMPLES
     *      wp wwof export_order_forms
     *      wp wwof export_order_forms json
     *
     * @param array $args       Arguments.
     * @param array $assoc_args Associative arguments.
     *
     * @since 3.0
     * @throws ExitException Exit exception.
     */
    public function export_order_forms( $args = array(), $assoc_args = array() ) {

        $format = $args[0] ?? 'yml';
        $format = 'yaml' === $format ? 'yml' : $format;

        $order_forms_query = new Order_Forms_Query(
            array(
                'post_status' => array( 'draft', 'publish' ),
                'orderby'     => 'ID',
                'order'       => 'DESC',
            ),
            'edit'
        );

        if ( $order_forms_query->have_posts() ) {
            $order_forms = array();
            foreach ( $order_forms_query->posts as $order_form ) {
                $order_forms[] = array(
                    'id'          => $order_form->ID,
                    'title'       => get_the_title( $order_form->ID ),
                    'form_header' => $order_form->form_header,
                    'form_body'   => $order_form->form_body,
                    'form_footer' => $order_form->form_footer,
                    'settings'    => $order_form->settings,
                );
            }

            $uploads_dir = wp_upload_dir();

            $filename = $uploads_dir['basedir'] . '/orderForms.' . $format;
            if ( 'json' === $format ) {
                //phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
                file_put_contents( $filename, wp_json_encode( $order_forms ) );
            } elseif ( 'yml' === $format ) {
                if ( class_exists( 'Symfony\Component\Yaml\Yaml' ) ) {
                    //phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
                    file_put_contents( $filename, Yaml::dump( $order_forms ) );
                } else {
                    WP_CLI::error(
                        __( 'Class Symfony\\Component\\Yaml\\Yaml not found.', 'woocommerce-wholesale-order-form' )
                    );
                }
            } else {
                WP_CLI::error(
                    WP_CLI::colorize(
                        sprintf(/* translators: %s = given format */
                            __( 'Invalid format %%y%s%%n.', 'woocommerce-wholesale-order-form' ),
                            $format
                        )
                    )
                );
            }
            WP_CLI::success(
                sprintf(/* translators: %1$s = format; %2$s = file path in uploads dir */
                    __( 'Order Forms exported to %1$s format in: %2$s', 'woocommerce-wholesale-order-form' ),
                    $format,
                    $filename
                )
            );

            return;
        }
        WP_CLI::error(
            __( 'No Order Forms found.', 'woocommerce-wholesale-order-form' )
        );
    }

    /**
     * Just a test.
     * ## OPTIONS
     * ## EXAMPLES
     *      wp wwof test
     *
     * @param array $args       Arguments.
     * @param array $assoc_args Associative arguments.
     *
     * @since 3.0
     * @throws ExitException Exit exception.
     */
    public function test( $args = array(), $assoc_args = array() ) {

        try {
            $uploads_dir = wp_upload_dir();
            $filename    = $uploads_dir['basedir'] . '/cli-test.php';
            if ( file_exists( $filename ) ) {
                include $filename;
            }
        } catch ( \Exception $exception ) {
            WP_CLI::error( $exception->getMessage() );
        }

        WP_CLI::success( 'Done!' );
    }
}
