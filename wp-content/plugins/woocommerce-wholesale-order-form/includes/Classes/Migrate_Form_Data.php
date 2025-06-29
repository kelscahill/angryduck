<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Classes
 */

namespace RymeraWebCo\WWOF\Classes;

use RymeraWebCo\WWOF\Abstracts\Abstract_Class;
use RymeraWebCo\WWOF\Helpers\WWOF;
use WP_Error;

/**
 * Migrate_Form_Data class.
 *
 * @since 3.0
 */
class Migrate_Form_Data extends Abstract_Class {

    /**
     * Array of form ids to migrate.
     *
     * @since 3.0
     * @var int[]
     */
    protected $form_ids;

    /**
     * Migrate_Form_Data constructor.
     *
     * @param int[]|string[] $form_ids Array of form ids to migrate.
     *
     * @since 3.0
     */
    public function __construct( $form_ids = array() ) {

        $this->form_ids = $form_ids;
    }

    /**
     * Run order form data migration process.
     *
     * @return true|WP_Error True if successful, WP_Error otherwise.
     */
    public function run() {

        $i18n       = require WWOF_PLUGIN_DIR_PATH . 'includes/I18n/order-form.php';
        $query_args = array();
        if ( ! empty( $this->form_ids ) ) {
            $this->form_ids = array_map( 'absint', $this->form_ids );
            $query_args     = array(
                'post__in' => $this->form_ids,
            );
        }

        $this->form_ids = WWOF::get_v2_forms( $query_args );
        if ( empty( $this->form_ids ) ) {
            return new WP_Error( 'no_forms_found', __( 'No forms found.', 'woocommerce-wholesale-order-form' ) );
        }

        foreach ( $this->form_ids as $form_id ) {
            /***************************************************************************
             * Order Form Metas
             ***************************************************************************
             *
             * `form_elements` - The form elements are those control elements for the
             * header/footer area which are not in use in the order form or the
             * remaining elements that can be dragged and dropped into the order form.
             * We can ignore this as we are not tracking the "unused" elements.
             *
             * `editor_area` - The editor area is the main area of the order form that
             * contains the form elements that are in use in the order form.
             *
             * `styles` - Are the styles for the order form elements and row columns.
             *
             * `settings` - Are the settings for the order form elements.
             */
            $editor_area = array_filter( (array) get_post_meta( $form_id, 'editor_area', true ) );
            $styles      = array_filter( (array) get_post_meta( $form_id, 'styles', true ) );
            $settings    = array_filter( (array) get_post_meta( $form_id, '_settings', true ) );
            if ( empty( $settings ) ) {
                $settings = get_post_meta( $form_id, 'settings', true );
                /***************************************************************************
                 * Backup old settings data
                 ***************************************************************************
                 *
                 * We will backup the old data in case we need to revert back to it.
                 */
                update_post_meta( $form_id, '_settings', $settings );
            }

            [
                'formHeader' => $old_form_header,
                'formTable'  => $old_form_table,
                'formFooter' => $old_form_footer,
            ] = wp_parse_args(
                $editor_area,
                array(
                    'formHeader' => array(),
                    'formTable'  => array(),
                    'formFooter' => array(),
                )
            );

            if ( ! empty( $old_form_header['rows'] ) ) {
                $this->migrate_header_footer_data( $form_id, $old_form_header, $styles );
            }

            if ( ! empty( $old_form_footer['rows'] ) ) {
                $this->migrate_header_footer_data( $form_id, $old_form_footer, $styles );
            }

            if ( ! empty( $old_form_table['itemIds'] ) ) {
                $form_body = array(
                    'columns'  => array(),
                    'settings' => array(
                        'styles'  => null,
                        'options' => null,
                    ),
                );
                foreach ( $old_form_table['itemIds'] as $element_index => $old_element_class ) {
                    switch ( $old_element_class ) {
                        case 'price':
                        case 'sku':
                            $element_class = 'product-' . $old_element_class;
                            break;
                        default:
                            $element_class = $old_element_class;
                    }

                    $field = array(
                        'id'           => WWOF::generate_id( array( $element_index, $element_class ) ),
                        'elementClass' => $element_class,
                        'settings'     => array(
                            'styles'  => $this->migrate_styles( $styles[ $old_element_class ]['props'] ?? null ),
                            'options' => $this->migrate_options( $styles[ $old_element_class ]['props'] ?? null ),
                        ),
                    );

                    $form_body['columns'][] = $field;
                }
                update_post_meta( $form_id, 'form_body', $form_body );
            }

            /***************************************************************************
             * Migrate general form settings
             ***************************************************************************
             *
             * We migrate the data from the old settings array to the new general
             * settings meta. These are the settings that are found in the "Settings"
             * tab in the order form editor app.
             */

            $default_category    = ! empty( $settings['selected_category'] ) ? get_term_by( 'slug', $settings['selected_category'], 'product_cat' ) : null;
            $included_categories = array();
            $excluded_categories = array();
            foreach ( $styles as $style ) {
                /***************************************************************************
                 * Extract included/excluded categories
                 ***************************************************************************
                 *
                 * We extract the included/excluded categories from the styles array. This
                 * is because the old settings array is set in the category select control
                 * but in the v3 order form, this has been moved to the general settings
                 * section.
                 */
                if ( ! empty( $style['props']['includedCategories'] ) ) {
                    foreach ( $style['props']['includedCategories'] as $category_slug ) {
                        $term = get_term_by( 'slug', $category_slug, 'product_cat' );
                        if ( $term && ! is_wp_error( $term ) ) {
                            $included_categories[] = $term->term_id;
                        }
                    }
                }
                if ( ! empty( $style['props']['excludedCategories'] ) ) {
                    foreach ( $style['props']['excludedCategories'] as $category_slug ) {
                        $term = get_term_by( 'slug', $category_slug, 'product_cat' );
                        if ( $term && ! is_wp_error( $term ) ) {
                            $excluded_categories[] = $term->term_id;
                        }
                    }
                }
            }

            /***************************************************************************
             * Ensure excluded categories are not included
             ***************************************************************************
             *
             * We ensure that the excluded categories are not "included" in the included
             * categories. This might create undesirable results if the user has
             * included a category and also set it in excluded.
             */
            if ( ! empty( $included_categories ) && ! empty( $excluded_categories ) ) {
                $excluded_categories = array_diff( $excluded_categories, $included_categories );
            }

            $general_settings = array(
                'permissions'                => array(
                    'allowedUserRoles' => get_option( 'wwof_permissions_user_role_filter', array() ),
                    'noAccessTitle'    => get_option( 'wwof_permissions_noaccess_title', $i18n['accessDenied'] ),
                    'noAccessMessage'  => get_option( 'wwof_permissions_noaccess_message', $i18n['noAccessMessage'] ),
                    'noAccessLoginUrl' => get_option( 'wwof_permissions_noaccess_login_url', wp_login_url() ),
                ),
                'hideFormTitle'              => ! empty( $settings['hide_form_title'] ) ? ( true === $settings['hide_form_title'] ? 'yes' : 'no' ) : 'no',
                'productSort'                => $settings['sort_order'] ?? 'asc',
                'productSortBy'              => $settings['sort_by'] ?? '',
                'productsPerPage'            => $settings['products_per_page'] ?? 10,
                'showVariationsIndividually' => ! empty( $settings['show_variations_individually'] ) ? ( true === $settings['show_variations_individually'] ? 'yes' : 'no' ) : 'no',
                'showZeroInventory'          => ! empty( $settings['show_zero_inventory_products'] ) ? ( true === $settings['show_zero_inventory_products'] ? 'yes' : 'no' ) : 'no',
                'simplifyAddToCartNotices'   => ! empty( $settings['simplify_add_to_cart_notices'] ) ? ( true === $settings['simplify_add_to_cart_notices'] ? 'yes' : 'no' ) : 'no',
                'includeProducts'            => ! empty( $settings['include_products'] ) ? array_map( 'absint', $settings['include_products'] ) : array(),
                'excludeProducts'            => ! empty( $settings['exclude_products'] ) ? array_map( 'absint', $settings['exclude_products'] ) : array(),
                'defaultCategory'            => ! empty( $default_category ) && ! is_wp_error( $default_category ) ? $default_category->term_id : null,
                'includedCategories'         => $included_categories,
                'excludedCategories'         => $excluded_categories,
            );

            update_post_meta( $form_id, 'settings', $general_settings );
        }

        return true;
    }

    /**
     * Converts styles data to new format.
     *
     * @param array $styles Old styles data.
     *
     * @since 3.0
     * @return array
     */
    private function migrate_styles( $styles ) {

        $new_styles = array();
        foreach ( (array) $styles as $style_key => $style_value ) {
            switch ( $style_key ) {
                case 'buttonColor':
                case 'buttonTextColor':
                    $new_styles[ $style_key ] = $style_value;
                    break;
                case 'width':
                    $new_styles['elementWidth'] = array(
                        'width' => $style_value['value'],
                        'unit'  => 'percentage' === $style_value['type'] ? '%' : 'px',
                    );
                    break;
                case 'fontSize':
                    $new_styles['fontSize'] = array(
                        'size' => $style_value['value'],
                        'unit' => 'percentage' === $style_value['type'] ? '%' : 'px',
                    );
                    break;
            }
        }

        return ! empty( $new_styles ) ? $new_styles : null;
    }

    /**
     * Converts options data to new format.
     *
     * @param array $options Old options data.
     *
     * @since 3.0
     * @return array
     */
    private function migrate_options( $options ) {

        $new_options = array();
        foreach ( (array) $options as $option_key => $option_value ) {
            switch ( $option_key ) {
                case 'skuSearch':
                case 'submitOnEnter':
                case 'submitOnChange':
                case 'showClearButton':
                case 'displayVariationDropdown':
                case 'sortable':
                case 'showQuantityBasedPricing':
                case 'smartVisibility':
                case 'openInNewTab':
                    if ( 'yes' !== $option_value && 'no' !== $option_value ) {
                        $option_value = $option_value ? 'yes' : 'no';
                    }

                    if ( 'decimalQuantity' === $option_key ) {
                        $option_key = 'allowDecimalQuantity';
                    }

                    if ( 'showQuantityBasedPricing' === $option_key ) {
                        $option_key = 'quantityBasedPricing';
                    }

                    $new_options[ $option_key ] = $option_value;
                    break;
                case 'placeholder':
                case 'preText':
                case 'emptyCartText':
                case 'subtotalSuffix':
                case 'clearButtonText':
                case 'outOfStockText':
                    $new_options[ $option_key ] = $option_value;
                    break;
                case 'searchButtonText':
                case 'buttonText':
                    $new_options['buttonText'] = $option_value;
                    break;
                case 'notificationDuration':
                    $new_options[ $option_key ] = is_numeric( $option_value ) ? intval( $option_value ) : 10;
                    break;
                case 'maxCharacters':
                    $new_options[ $option_key ] = is_numeric( $option_value ) ? intval( $option_value ) : 200;
                    break;
                case 'columnHeading':
                    $new_options['headingText'] = $option_value;
                    break;
                case 'variationSelectorStyle':
                    $new_options['variationStyle'] = $option_value;
                    break;
                case 'onClick':
                    $new_options['clickAction'] = $option_value;
                    break;
                case 'imageSize':
                    $new_options[ $option_key ] = $option_value;

                    $new_options[ $option_key ]['linked'] = 'yes';
                    break;
            }
        }

        return ! empty( $new_options ) ? $new_options : null;
    }

    /**
     * Migrate old box styles data to new format.
     *
     * @param array $styles Old box styles data.
     *
     * @since 3.0
     * @return array
     */
    private function migrate_box_styles( $styles ) {

        $new_styles = array();
        if ( ! empty( $styles['width'] ) ) {
            $new_styles['boxWidth'] = array(
                'width' => $styles['width']['value'],
                'unit'  => 'percentage' === $styles['width']['type'] ? ' % ' : 'px',
            );
        }
        $new_styles['boxAlignment'] = $styles['justifyContent'] ?? 'flex-start';
        if ( 'flex-start' === $new_styles['boxAlignment'] ) {
            $new_styles['elementAlignment'] = 'left';
        } elseif ( 'flex-end' === $new_styles['boxAlignment'] ) {
            $new_styles['elementAlignment'] = 'right';
        } else {
            $new_styles['elementAlignment'] = 'center';
        }

        $new_styles['margin']  = array(
            'top'    => $styles['marginTop'] ?? '',
            'right'  => $styles['marginRight'] ?? '',
            'bottom' => $styles['marginBottom'] ?? '',
            'left'   => $styles['marginLeft'] ?? '',
            'unit'   => 'px',
            'linked' => 'yes',
        );
        $new_styles['padding'] = array(
            'top'    => $styles['paddingTop'] ?? '',
            'right'  => $styles['paddingRight'] ?? '',
            'bottom' => $styles['paddingBottom'] ?? '',
            'left'   => $styles['paddingLeft'] ?? '',
            'unit'   => 'px',
            'linked' => 'yes',
        );

        return $new_styles;
    }

    /**
     * Migrate header/footer data.
     *
     * @param int   $form_id                Form ID.
     * @param array $old_form_header_footer Old form header/footer data.
     * @param array $styles                 Form styles.
     *
     * @return void
     */
    protected function migrate_header_footer_data( $form_id, $old_form_header_footer, $styles = array() ) {

        $data     = array(
            'rows'     => array(),
            'settings' => array(),
        );
        $meta_key = '';
        foreach ( $old_form_header_footer as $key => $value ) {
            if ( 'title' === $key ) {
                if ( str_contains( $value, 'FOOTER' ) ) {
                    $meta_key = 'form_footer';
                } else {
                    $meta_key = 'form_header';
                }
            } elseif ( 'rows' === $key && is_array( $value ) && ! empty( $value ) ) {
                /***************************************************************************
                 * Go through each row
                 ***************************************************************************
                 *
                 * We check the data for each row.
                 */
                foreach ( $value as $row ) {
                    $columns = array();
                    if ( ! empty( $row['columns'] ) ) {
                        /***************************************************************************
                         * Convert columns to new format
                         ***************************************************************************
                         *
                         * We convert each column data to the new format.
                         */
                        foreach ( $row['columns'] as $column_index => $column ) {
                            if ( empty( $column['itemIds'] ) ) {
                                continue;
                            }
                            /***************************************************************************
                             * Convert column styles to new format
                             ***************************************************************************
                             *
                             * We convert each column style to the new format.
                             */
                            $column_styles = $this->migrate_box_styles( $styles[ $column['colId'] ]['box'] ?? null );
                            /***************************************************************************
                             * Convert field settings to new format
                             ***************************************************************************
                             *
                             * We convert each field setting to the new format.
                             */
                            $styles_options = array_merge(
                                $styles[ $column['itemIds'][0] ]['props'] ?? array(),
                                $styles[ $column['colId'] ]['props'] ?? array(),
                                $styles[ $column['colId'] ]['element'] ?? array()
                            );
                            $field          = array(
                                /***************************************************************************
                                 * Pass a custom array to generate the id.
                                 ***************************************************************************
                                 *
                                 * We can use the column index, the field id and a uniqid to generate a
                                 * unique 5 chars id.
                                 */
                                'id'           => WWOF::generate_id(
                                    array(
                                        $column_index,
                                        $column['itemIds'][0],
                                    )
                                ),
                                'elementClass' => 'pagination' === $column['itemIds'][0] ? 'form-pagination' : $column['itemIds'][0],
                                'settings'     => array(
                                    'styles'  => $this->migrate_styles( $styles_options ),
                                    'options' => $this->migrate_options( $styles_options ),
                                ),
                            );
                            $columns[]      = array(
                                'id'       => WWOF::generate_id( $column ),
                                'fields'   => array( $field ),
                                'settings' => array(
                                    'styles'  => $column_styles,
                                    'options' => null,
                                ),
                            );
                        }
                    }
                    $data['rows'][] = array(
                        'id'       => WWOF::generate_id( $row ),
                        'columns'  => $columns,
                        'settings' => array(
                            'styles'  => ! empty( $styles[ $row['rowId'] ] ) ? $this->migrate_box_styles( $styles[ $row['rowId'] ]['box'] ) : null,
                            'options' => null,
                        ),
                    );
                }
            }
        }
        if ( ! empty( $meta_key ) ) {
            update_post_meta( $form_id, $meta_key, $data );
        }
    }
}
