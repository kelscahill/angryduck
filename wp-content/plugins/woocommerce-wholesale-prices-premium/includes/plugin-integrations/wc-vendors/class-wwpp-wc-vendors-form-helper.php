<?php
/**
 * WWPP_WC_Vendors_Pro_Form_Helper class.
 *
 * WWPP_WC_Vendors_Pro_Form_Helper integration.
 *
 * @since 2.0.3 - Added
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


if ( ! class_exists( 'WWPP_WC_Vendors_Pro_Form_Helper' ) ) {

    /**
     * Defines the form fields for the WC Vendors Pro integration.
     *
     * @since 2.0.3
     */
    class WWPP_WC_Vendors_Pro_Form_Helper {

        /**
         * Create an html toggle switch.
         *
         * @param mixed $args The arguments defining the toggle switch.
         * @return void
         */
        public static function toggle_switch( $args ) {
            $args = wp_parse_args(
                $args,
                array(
                    'id'          => '',
                    'label'       => '',
                    'desc_tip'    => '',
                    'description' => '',
                    'value'       => 'no',
                    'disabled'    => false,
                )
            );

            $id          = $args['id'];
            $description = $args['description'];
            $value       = $args['value'];
            $disabled    = $args['disabled'];

            if ( $disabled ) {
                $value = 'no';
            }

            ?>
            <label class="wcv-switch">
                <input
                    type="checkbox"
                    id="<?php echo esc_attr( $id ); ?>"
                    name="<?php echo esc_attr( $id ); ?>"
                    value="<?php echo esc_attr( $value ); ?>"
                    <?php checked( $value, 'yes' ); ?>
                    <?php echo $disabled ? ' disabled="true"' : ''; ?>
                />
                <span class="slider round"></span>
            </label>
            <?php if ( isset( $args['description'] ) && $args['description'] ) : ?>
                <p class="description">
                    <?php echo wp_kses_post( $description ); ?>
                </p>
            <?php
            endif;
        }

        /**
         * Render a discount type field.
         *
         * @param array $args The arguments defining the discount type field.
         * @return void
         */
        public static function discount_type( $args ) {
            $args = wp_parse_args(
                $args,
                array(
                    'id'          => '',
                    'label'       => '',
                    'description' => '',
                    'value'       => 'fixed',
                    'tooltip'     => '',
                    'class'       => '',
                )
            );

            $id          = $args['id'];
            $label       = $args['label'];
            $description = $args['description'];
            $tooltip     = $args['tooltip'];
            $value       = $args['value'];

            ?>
            <div class="wcv-wholesale-prices-field control-group">
                <label for="<?php echo esc_attr( $id ); ?>">
                    <?php echo esc_html( $label ); ?>

                </label>
                <?php if ( ! empty( $tooltip ) ) : ?>
                    <?php self::get_tooltip_html( $tooltip ); ?>
                <?php endif; ?>
                <div class="wcv-wholesale-prices-field-input control">
                    <select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $id ); ?>" class="wholesale_discount_type select <?php echo esc_attr( $args['class'] ); ?>">
                        <option value="fixed" <?php selected( $value, 'fixed' ); ?>>
                            <?php esc_html_e( 'Fixed', 'woocommerce-wholesale-prices-premium' ); ?>
                        </option>
                        <option value="percentage" <?php selected( $value, 'percentage' ); ?>>
                            <?php esc_html_e( 'Percentage', 'woocommerce-wholesale-prices-premium' ); ?>
                        </option>
                    </select>
                </div>
            </div>
            <?php
        }

        /**
         * Render a checkbox.
         *
         * @param mixed $args The arguments defining the checkbox.
         * @return void
         */
        public static function checkbox( $args ) {
            $args = wp_parse_args(
                $args,
                array(
                    'id'          => '',
                    'label'       => '',
                    'desc_tip'    => '',
                    'description' => '',
                    'value'       => 'no',
                    'disabled'    => false,
                )
            );

            $id          = $args['id'];
            $label       = $args['label'];
            $description = $args['description'];
            $value       = $args['value'];
            $disabled    = $args['disabled'];

            if ( $disabled ) {
                $value = 'no';
            }

            ?>
            <label class="wcv-checkbox">
                <input
                    type="checkbox"
                    id="<?php echo esc_attr( $id ); ?>"
                    name="<?php echo esc_attr( $id ); ?>"
                    value="<?php echo esc_attr( $value ); ?>"
                    <?php checked( $value, 'yes' ); ?>
                    <?php echo $disabled ? ' disabled="true"' : ''; ?>
                />
                <span class="checkmark"></span>
                <?php if ( isset( $args['label'] ) && $args['label'] ) : ?>
                    <span class="checkmark-text">
                        <?php echo esc_html( $label ); ?>
                    </span>
                <?php endif; ?>
            </label>

            <?php if ( isset( $args['description'] ) && $args['description'] ) : ?>
                <p class="description">
                    <small><?php echo wp_kses_post( $description ); ?></small>
                </p>
            <?php
            endif;
        }

        /**
         * Render a wholesale price field.
         *
         * @param array $field The field arguments.
         * @return void
         */
        public static function price_field( $field ) {
            $placeholder = isset( $field['placeholder'] ) ? $field['placeholder'] : '';
            $description = isset( $field['description'] ) ? $field['description'] : '';
            $class       = isset( $field['class'] ) ? $field['class'] : '';
            $label       = isset( $field['label'] ) ? $field['label'] : '&nbsp;';
            $name        = isset( $field['name'] ) ? $field['name'] : $field['id'];
            $type        = isset( $field['type'] ) ? $field['type'] : 'text';
            $data_type   = empty( $field['data_type'] ) ? '' : $field['data_type'];
            $tooltip     = isset( $field['tooltip'] ) ? $field['tooltip'] : '';
            $field_value = isset( $field['value'] ) ? $field['value'] : '';
            $id          = isset( $field['id'] ) ? $field['id'] : '';

            // Strip tags.
            $field_value = wp_strip_all_tags( $field['value'] );

            if ( 'price' === $data_type ) {
                $field_value = wc_format_localized_price( $field['value'] );
            }

            // Custom attribute handling.
            $custom_attributes = array();

            if ( ! empty( $field['custom_attributes'] ) && is_array( $field['custom_attributes'] ) ) {

                // Update validation rules to new system.
                if ( array_key_exists( 'data-rules', $field['custom_attributes'] ) ) {
                    $field['custom_attributes'] = WCVendors_Pro_Form_Helper::check_custom_attributes( $field['custom_attributes'], $field['id'] );
                }

                foreach ( $field['custom_attributes'] as $attribute => $value ) {
                    $custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $value ) . '"';
                }
            }

            $number_data_types = array( 'decimal', 'price', 'number' );

            if ( in_array( $data_type, $number_data_types, true ) ) {

                $type_number = 'data-parsley-type="number"';

                if ( ! empty( $custom_attributes ) && in_array( $type_number, $custom_attributes, true ) ) {
                    $key = array_search( $type_number, $custom_attributes, true );
                    unset( $custom_attributes[ $key ] );
                }

                if ( 'price' === $data_type ) {
                    $custom_attributes[] = 'data-parsley-price';
                } else {
                    $custom_attributes[] = 'data-parsley-decimal="."';
                }
            }

            ?>

            <div class="wcv-wholesale-prices-field control-group">
                <label for="<?php echo esc_attr( $id ); ?>">
                    <?php echo esc_html( $label ); ?>
                </label>
                <?php if ( ! empty( $tooltip ) ) : ?>
                    <?php self::get_tooltip_html( $tooltip ); ?>
                <?php endif; ?>
                <div class="wcv-wholesale-prices-field-input control">
                    <input
                        type="<?php echo esc_attr( $type ); ?>"
                        class="<?php echo esc_attr( $class ); ?>"
                        name="<?php echo esc_attr( $name ); ?>"
                        id="<?php echo esc_attr( $id ); ?>"
                        value="<?php echo esc_attr( $field_value ); ?>"
                        placeholder="<?php echo esc_attr( $placeholder ); ?>"

                        <?php echo esc_attr( implode( ' ', $custom_attributes ) ); ?>
                    />

                    <?php if ( isset( $description ) && $description ) : ?>
                    <p class="description">
                        <small><?php echo wp_kses_post( $description ); ?></small>
                    </p>
                    <?php endif; ?>
                    <?php do_action( 'wwpp_wcvendors_after_' . $id . '_field', $field ); ?>
                </div>
            </div>
            <?php
        }

        /**
         * Render wholesale sale price dates field.
         *
         * @param mixed $field The details of the field to be rendered.
         * @return void
         */
        public static function wholesale_sale_price_dates( $field ) {

            $role_key   = $field['role_key'];
            $dates_from = $field['dates_from'];
            $dates_to   = $field['dates_to'];

            ?>
            <div class="all-100 wholesale_sale_price_dates_fields wcv-cols-group wcv-horizontal-gutters">
                <div class="all-50 small-100 ">
                <?php
                    WCVendors_Pro_Form_Helper::input(
                        array(
                            'id'                => $role_key . '_wholesale_sale_price_dates_from',
                            'label'             => __( 'From', 'woocommerce-wholesale-prices-premium' ),
                            'class'             => 'wcv-datepicker wcv-init-picker',
                            'value'             => esc_attr( $dates_from ),
                            'description'       => '<a href="#" class="wholesale_sale_schedule right">' . __( 'Schedule', 'woocommerce-wholesale-prices-premium' ) . '</a>',
                            'placeholder'       => ( '' === $dates_from ) ? __( 'From&hellip; YYYY-MM-DD', 'woocommerce-wholesale-prices-premium' ) : '',
                            'custom_attributes' => array(
                                'data-close-text' => __( 'Close', 'woocommerce-wholesale-prices-premium' ),
                                'data-clean-text' => __( 'Clear', 'woocommerce-wholesale-prices-premium' ),
                                'data-of-text'    => __( ' of ', 'woocommerce-wholesale-prices-premium' ),
                            ),
                        )
                    );

                ?>
                </div>
                <div class="all-50 small-100">
                <?php
                    WCVendors_Pro_Form_Helper::input(
                        array(
                            'id'                => $role_key . '_wholesale_sale_price_dates_to',
                            'label'             => __( 'To', 'woocommerce-wholesale-prices-premium' ),
                            'class'             => 'wcv-datepicker wcv-init-picker',
                            'placeholder'       => ( '' === $dates_to ) ? __( 'To&hellip; YYYY-MM-DD', 'woocommerce-wholesale-prices-premium' ) : '',
                            'value'             => esc_attr( $dates_to ),
                            'desc_tip'          => true,
                            'description'       => __( 'The sale will end at the beginning of the set date.', 'woocommerce-wholesale-prices-premium' ) . '<a href="#" class="cancel_wholesale_sale_schedule right">' . __( 'Cancel', 'woocommerce-wholesale-prices-premium' ) . '</a>',
                            'custom_attributes' => array(
                                'data-start-date' => '',
                                'data-close-text' => __( 'Close', 'woocommerce-wholesale-prices-premium' ),
                                'data-clean-text' => __( 'Clear', 'woocommerce-wholesale-prices-premium' ),
                                'data-of-text'    => __( ' of ', 'woocommerce-wholesale-prices-premium' ),
                            ),
                        )
                    );
                ?>
                </div>
            </div>
            <?php
        }

        /**
         * Get help tip HTML
         *
         * @param string  $message The message to show in the help tip.
         * @param boolean $allow_html Whether to allow HTML or not.
         * @param boolean $return_html Whether to print or return the value.
         *
         * @return string|void
         */
        public static function get_tooltip_html( $message, $allow_html = true, $return_html = false ) {

            $html = '<span class="wcv-tip">
                <svg class="wcv-icon wcv-setting-icon">
                    <use xlink:href="' . esc_attr( WCV_ASSETS_URL ) . 'svg/wcv-icons.svg#wcv-icon-info"></use>
                </svg>
                <div class="content">
                    <span>' . ( $allow_html ? wp_kses_post( $message ) : esc_attr( $message ) ) . '</span>
                    <span class="arrow"></span>
                </div>
            </span>';

            if ( $return_html ) {
                return $html;
            }

            echo $html; //phpcs:ignore;
        }
    }
}
