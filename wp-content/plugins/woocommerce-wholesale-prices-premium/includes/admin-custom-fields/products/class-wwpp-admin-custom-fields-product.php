<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Model that houses logic admin custom fields for all product types in general.
 * As of @since 1.13.0 it includes simple, variable, bundle and composite products.
 *
 * @since 1.13.0
 */
class WWPP_Admin_Custom_Fields_Product {

    /**
     * Class Properties
     */

    /**
     * Property that holds the single main instance of WWPP_Admin_Custom_Fields_Product.
     *
     * @since 1.13.0
     * @access private
     * @var WWPP_Admin_Custom_Fields_Product
     */
    private static $_instance;

    /**
     * Model that houses the logic of retrieving information relating to wholesale role/s of a user.
     *
     * @since 1.13.0
     * @access private
     * @var WWPP_Wholesale_Roles
     */
    private $_wwpp_wholesale_roles;

    /**
     * Array of registered wholesale roles.
     *
     * @since 1.13.0
     * @access private
     * @var array
     */
    private $_registered_wholesale_roles;


    /**
     * Class Methods
     */

    /**
     * WWPP_Admin_Custom_Fields_Product constructor.
     *
     * @since 1.13.0
     * @access public
     *
     * @param array $dependencies Array of instance objects of all dependencies of WWPP_Admin_Custom_Fields_Product model.
     */
    public function __construct( $dependencies ) {
        $this->_wwpp_wholesale_roles       = $dependencies['WWPP_Wholesale_Roles'];
        $this->_registered_wholesale_roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();
    }

    /**
     * Ensure that only one instance of WWPP_Admin_Custom_Fields_Product is loaded or can be loaded (Singleton Pattern).
     *
     * @since 1.13.0
     * @access public
     *
     * @param array $dependencies Array of instance objects of all dependencies of WWPP_Admin_Custom_Fields_Product model.
     * @return WWPP_Admin_Custom_Fields_Product
     */
    public static function instance( $dependencies ) {
        if ( ! self::$_instance instanceof self ) {
            self::$_instance = new self( $dependencies );
        }

        return self::$_instance;
    }

    /**
     * Get curent user wholesale role.
     *
     * @since 1.13.0
     * @access private
     *
     * @return string User role string or empty string.
     */
    private function _get_current_user_wholesale_role() {
        $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();
        return ( is_array( $user_wholesale_role ) && ! empty( $user_wholesale_role ) ) ? $user_wholesale_role[0] : '';
    }

    /**
     * Add Custom Fields
     */

    /**
     * Add order quantity based wholesale pricing custom fields to simple products.
     * It also add this custom product types that are closely related to simple products.
     * As of v1.13.0 it includes bundle and composite products.
     *
     * @since 1.6.0
     * @since 1.13.0 Refactor codebase and its own model.
     * @access public
     */
    public function add_simple_product_quantity_based_wholesale_price_custom_field() {
        global $post;
        $this->_print_order_quantity_based_wholesale_pricing_controls( $post->ID, $this->_registered_wholesale_roles, 'simple' );
    }

    /**
     * Add order quantity based wholesale pricing custom fields to variable products.
     *
     * @since 1.6.0
     * @since 1.13.0 Refactor codebase and its own model.
     * @access public
     *
     * @param int     $loop           Loop counter.
     * @param array   $variation_data Array of variation data.
     * @param WP_Post $variation      Variation product object.
     */
    public function add_variation_product_quantity_based_wholesale_price_custom_field( $loop, $variation_data, $variation ) {
        $this->_print_order_quantity_based_wholesale_pricing_controls( $variation->ID, $this->_registered_wholesale_roles, 'variable' );
    }

    /**
     * Add order quantity based wholesale pricing custom fields to variable products.
     * Discount will be applied if it reaches the total quantity variation.
     *
     * @since 1.19
     * @access public
     */
    public function add_variable_level_product_quantity_based_wholesale_price_custom_field() {
        global $post;

        $product = wc_get_product( $post->ID );

        if ( WWP_Helper_Functions::wwp_get_product_type( $product ) === 'variable' ) {
            $this->_print_order_quantity_based_wholesale_pricing_controls( $post->ID, $this->_registered_wholesale_roles, 'variable parent-variable' );
        }
    }

    /**
     * Print order quantity based wholesale pricing custom fields.
     *
     * @since 1.6.0
     * @since 1.7.0  Add Aelia Currency Switcher Plugin Integration.
     * @since 1.13.0 Refactor codebase and its own model.
     * @since 1.16.4 Bug fix not able to set percent discount for non base currency (WWPP-570).
     * @since 1.19   If quantity based pricing is enabled via variable level, hide quantity pricing optiom in the variation level. (WWPP-592).
     * @access public
     *
     * @param int    $product_id      Product id.
     * @param array  $wholesale_roles Array of wholesale roles in which this custom controls are to be printed.
     * @param string $classes         Css class, also used to determine product type. Also note for variations, it will have a value of 'variable' although it should be variation. Long code history.
     */
    private function _print_order_quantity_based_wholesale_pricing_controls( $product_id, $wholesale_roles, $classes ) {
        $product = wc_get_product( $product_id );

        /**
         * Allows to hide order quantity base wholesale pricing
         *
         * @param boolean $show Whether to show the pricing fields or not. Default true.
         * @return boolean
         */
        if ( ! apply_filters( 'wwpp_product_editor_show_order_quantity_fields', true ) ) {
            return;
        }

        $aelia_currency_switcher_active = WWP_ACS_Integration_Helper::aelia_currency_switcher_active();

        if ( $aelia_currency_switcher_active ) {

            $currency_symbol        = '';
            $base_currency          = WWP_ACS_Integration_Helper::get_product_base_currency( $product_id );
            $woocommerce_currencies = get_woocommerce_currencies();
            $enabled_currencies     = WWP_ACS_Integration_Helper::enabled_currencies();

        } else {
            $currency_symbol = ' (' . get_woocommerce_currency_symbol() . ')';
        }

        $wholesale_roles_arr = array();
        foreach ( $wholesale_roles as $roleKey => $role ) {
            $wholesale_roles_arr[ $roleKey ] = $role['roleName'];
        }

        $pqbwp_enable = $product->get_meta( WWPP_POST_META_ENABLE_QUANTITY_DISCOUNT_RULE, true );

        $pqbwp_controls_styles = '';
        if ( 'yes' !== $pqbwp_enable ) {
            $pqbwp_controls_styles = 'display: none;';
        }

        $mapping = $product->get_meta( WWPP_POST_META_QUANTITY_DISCOUNT_RULE_MAPPING, true );
        if ( ! is_array( $mapping ) ) {
            $mapping = array();
        }

        $hide_variation_qty_based_discount = false;

        // Check if showing variations.
        if ( 'variable' === $classes ) {
            $variation                       = wc_get_product( $product_id );
            $variation_parent_object         = wc_get_product( $variation->get_parent_id() );
            $variable_qty_based_disc_enabled = $variation_parent_object->get_meta( WWPP_POST_META_ENABLE_QUANTITY_DISCOUNT_RULE, true );

            if ( 'yes' === $variable_qty_based_disc_enabled ) {
                $hide_variation_qty_based_discount = true;
            }
        }

        // Variable parent.
        $is_variable_parent_flag = false;
        if ( $hide_variation_qty_based_discount || in_array( $product->get_type(), array( 'simple', 'variation' ), true ) ) {
            $is_variable_parent_flag = true;
        }

        $visibility_classes = apply_filters( 'wwpp_filter_admin_custom_field_wholesale_quantity_based_visibility_clasess', array() ); ?>

        <div class="product-quantity-based-wholesale-pricing options_group <?php echo esc_attr( implode( ' ', $visibility_classes ) ); ?> <?php echo esc_attr( $classes ); ?>" style="display:<?php echo $hide_variation_qty_based_discount ? 'none;' : 'block;'; ?>">
            <header>
                <h3 class="pqbwp-heading"><?php esc_html_e( 'Product Quantity Based Wholesale Pricing', 'woocommerce-wholesale-prices-premium' ); ?></h3>
                <p class="pqbwp-desc">
                    <?php
                        if ( ! $is_variable_parent_flag ) {
                            esc_html_e( 'Specify further wholesale discounts based on the total quantity of all variations of this product in the cart. If you wish to use quantity based pricing on individual variations, uncheck this option and use the controls on the variations instead. Ending Qty can be left blank to apply that price for all quantities above the Starting Qty. Only applies to the wholesale roles that you specify.', 'woocommerce-wholesale-prices-premium' );
                            echo '<br/><b>' . esc_html__( 'Note: Wholesale Price is calculated via percentage discount, it is based on the wholesale price of variation if set else it will use the regular price.', 'woocommerce-wholesale-prices-premium' ) . '</b>';
                        } else {
                            esc_html_e( 'Specify wholesale price for this current product depending on the quantity being purchased. Ending Qty can be left blank to apply that price for all quantities above the Starting Qty. Only applies to the wholesale roles that you specify.', 'woocommerce-wholesale-prices-premium' );
                        }
                        ?>
                </p>

                <?php if ( $aelia_currency_switcher_active ) { ?>
                    <p class="pbwp-desc">
                        <?php esc_html_e( 'Note: If you have not specify mapping for other currencies for a given wholesale role, it will derive its wholesale price automatically by converting the base currency wholesale price to that currency', 'woocommerce-wholesale-prices-premium' ); ?>
                    </p>
                <?php } ?>

            </header>

            <p class="form-field pqbwp-enable-field-container">
                <span class="hidden post-id"><?php echo esc_attr( $product_id ); ?></span>
                <input type="hidden" name="wwpp_post_meta_enable_quantity_discount_rule_<?php echo esc_attr( $product_id ); ?>" class="wwpp_post_meta_enable_quantity_discount_rule" value="<?php echo esc_attr( $pqbwp_enable ); ?>">
                <input type="checkbox" name="pqbwp-enable" class="pqbwp-enable checkbox" value="yes" <?php echo ( 'yes' === $pqbwp_enable ) ? 'checked' : ''; ?> autocomplete="off">
                <span class="description"><?php esc_html_e( 'Enable further wholesale pricing discounts based on quantity purchased?', 'woocommerce-wholesale-prices-premium' ); ?></span>
            </p>

            <div class="processing-indicator"><span class="spinner"></span></div>

            <div class="pqbwp-controls" style="<?php echo esc_attr( $pqbwp_controls_styles ); ?>">
                <input type="hidden" class="mapping-index" value="">
                <?php

                if ( ! $is_variable_parent_flag ) {
                    $wholesal_price_desc = __( '% value off the wholesale price. This will be the discount value used for quantities within the given range. Please input value without comma separator.', 'woocommerce-wholesale-prices-premium' );
                    $price_type_options  = array(
                        'percent-price' => __( 'Discount % off the wholesale price', 'woocommerce-wholesale-prices-premium' ),
                    );
                } else {
                    $wholesal_price_desc = __( '$ or the new % value off the wholesale price. This will be the discount value used for quantities within the given range. Please input value without comma separator.', 'woocommerce-wholesale-prices-premium' );
                    $price_type_options  = array(
                        'fixed-price'   => __( 'Fixed Price', 'woocommerce-wholesale-prices-premium' ),
                        'percent-price' => __( 'Discount % off the wholesale price', 'woocommerce-wholesale-prices-premium' ),
                    );
                }

                /**
                 * The fields below aren't really saved via woocommerce, we just used it here to house our rule controls.
                 * We use these to add our rule controls to abide with woocommerce styling.
                 */
                woocommerce_wp_select(
                    array(
                        'id'                => 'pqbwp_registered_wholesale_roles',
                        'class'             => 'pqbwp_registered_wholesale_roles',
                        'label'             => __( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' ),
                        'placeholder'       => '',
                        'desc_tip'          => 'true',
                        'description'       => __( 'Select wholesale role to which this rule applies.', 'woocommerce-wholesale-prices-premium' ),
                        'options'           => $wholesale_roles_arr,
                        'custom_attributes' => array( 'autocomplete' => 'off' ),
                    )
                );

                woocommerce_wp_text_input(
                    array(
                        'id'                => 'pqbwp_minimum_order_quantity',
                        'class'             => 'pqbwp_minimum_order_quantity',
                        'label'             => __( 'Starting Qty', 'woocommerce-wholesale-prices-premium' ),
                        'placeholder'       => '',
                        'desc_tip'          => 'true',
                        'description'       => __( 'Minimum order quantity required for this rule. Must be a number.', 'woocommerce-wholesale-prices-premium' ),
                        'custom_attributes' => array( 'autocomplete' => 'off' ),
                    )
                );

                woocommerce_wp_text_input(
                    array(
                        'id'                => 'pqbwp_maximum_order_quantity',
                        'class'             => 'pqbwp_maximum_order_quantity',
                        'label'             => __( 'Ending Qty', 'woocommerce-wholesale-prices-premium' ),
                        'placeholder'       => '',
                        'desc_tip'          => 'true',
                        'description'       => __( 'Maximum order quantity required for this rule. Must be a number. Leave this blank for no maximum quantity.', 'woocommerce-wholesale-prices-premium' ),
                        'custom_attributes' => array( 'autocomplete' => 'off' ),
                    )
                );

                woocommerce_wp_select(
                    array(
                        'id'                => 'pqbwp_price_type',
                        'class'             => 'pqbwp_price_type',
                        'label'             => __( 'Price Type', 'woocommerce-wholesale-prices-premium' ),
                        'placeholder'       => '',
                        'desc_tip'          => 'true',
                        'description'       => $is_variable_parent_flag ? __( 'Select pricing type', 'woocommerce-wholesale-prices-premium' ) : __( 'Only percentage discount is allowed for variable level quantity discount.', 'woocommerce-wholesale-prices-premium' ),
                        'options'           => $price_type_options,
                        'custom_attributes' => array( 'autocomplete' => 'off' ),
                    )
                );

                woocommerce_wp_text_input(
                    array(
                        'id'                => 'pqbwp_wholesale_price',
                        'class'             => 'pqbwp_wholesale_price',
                        /* Translators: $1 is currency symbol */
                        'label'             => sprintf( __( 'Wholesale Price%1$s', 'woocommerce-wholesale-prices-premium' ), $currency_symbol ),
                        'placeholder'       => '',
                        'desc_tip'          => 'true',
                        'description'       => $wholesal_price_desc,
                        'data_type'         => 'price',
                        'custom_attributes' => array( 'autocomplete' => 'off' ),
                    )
                );

                if ( $aelia_currency_switcher_active ) {
                    $currency_select_options = array();

                    foreach ( $enabled_currencies as $currency ) {
                        if ( $currency === $base_currency ) {
                            $text = $woocommerce_currencies[ $currency ] . ' ' . __( '(Base Currency)', 'woocommerce-wholesale-prices-premium' );
                        } else {
                            $text = $woocommerce_currencies[ $currency ];
                        }

                        $currency_select_options[ $currency ] = $text;
                    }

                    woocommerce_wp_select(
                        array(
                            'id'                => 'pqbwp_enabled_currencies',
                            'class'             => 'pqbwp_enabled_currencies',
                            'label'             => __( 'Currency', 'woocommerce-wholesale-prices-premium' ),
                            'placeholder'       => '',
                            'desc_tip'          => 'true',
                            'description'       => __( 'Select Currency', 'woocommerce-wholesale-prices-premium' ),
                            'options'           => $currency_select_options,
                            'value'             => $base_currency,
                            'custom_attributes' => array( 'autocomplete' => 'off' ),
                        )
                    );
                }
                ?>

                <p class="form-field button-controls add-mode">
                    <input type="button" class="pqbwp-cancel button button-secondary" value="<?php esc_attr_e( 'Cancel', 'woocommerce-wholesale-prices-premium' ); ?>">
                    <input type="button" class="pqbwp-save-rule button button-primary" value="<?php esc_attr_e( 'Save Quantity Discount Rule', 'woocommerce-wholesale-prices-premium' ); ?>">
                    <input type="button" class="pqbwp-add-rule button button-primary" value="<?php esc_attr_e( 'Add Quantity Discount Rule', 'woocommerce-wholesale-prices-premium' ); ?>">
                    <span class="spinner"></span>
                    <div style="float: none; clear: both; display: block;"></div>
                </p>

                <div class="form-field table-mapping">
                    <table class="pqbwp-mapping wp-list-table widefat">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' ); ?></th>
                                <th><?php esc_html_e( 'Starting Qty', 'woocommerce-wholesale-prices-premium' ); ?></th>
                                <th><?php esc_html_e( 'Ending Qty', 'woocommerce-wholesale-prices-premium' ); ?></th>
                                <th><?php esc_html_e( 'Wholesale Price', 'woocommerce-wholesale-prices-premium' ); ?></th>
                                <?php echo $aelia_currency_switcher_active ? '<th>' . esc_html__( 'Currency', 'woocommerce-wholesale-prices-premium' ) . '</th>' : ''; ?>
                                <th></th>
                            </tr>
                        </thead>

                        <tfoot>
                            <tr>
                                <th><?php esc_html_e( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' ); ?></th>
                                <th><?php esc_html_e( 'Starting Qty', 'woocommerce-wholesale-prices-premium' ); ?></th>
                                <th><?php esc_html_e( 'Ending Qty', 'woocommerce-wholesale-prices-premium' ); ?></th>
                                <th><?php esc_html_e( 'Wholesale Price', 'woocommerce-wholesale-prices-premium' ); ?></th>
                                <?php echo $aelia_currency_switcher_active ? '<th>' . esc_html__( 'Currency', 'woocommerce-wholesale-prices-premium' ) . '</th>' : ''; ?>
                                <th></th>
                            </tr>
                        </tfoot>

                        <tbody>

                        <?php
                        if ( ! empty( $mapping ) ) {
                            if ( $aelia_currency_switcher_active ) {
                                $item_number = 0;

                                foreach ( $mapping as $index => $map ) {
                                    foreach ( $enabled_currencies as $currency ) {
                                        if ( $currency === $base_currency ) {
                                            $wholesale_role_meta_key  = 'wholesale_role';
                                            $wholesale_price_meta_key = 'wholesale_price';
                                            $start_qty_meta_key       = 'start_qty';
                                            $end_qty_meta_key         = 'end_qty';
                                            $price_type_meta_key      = 'price_type';
                                        } else {
                                            $wholesale_role_meta_key  = $currency . '_wholesale_role';
                                            $wholesale_price_meta_key = $currency . '_wholesale_price';
                                            $start_qty_meta_key       = $currency . '_start_qty';
                                            $end_qty_meta_key         = $currency . '_end_qty';
                                            $price_type_meta_key      = $currency . '_price_type';
                                        }

                                        $args = array( 'currency' => $currency );

                                        if ( array_key_exists( $wholesale_role_meta_key, $map ) ) {
                                            ++$item_number;

                                            // One key check is enough.
                                            $this->_print_mapping_item( $item_number, $index, $map, $wholesale_roles, $wholesale_role_meta_key, $wholesale_price_meta_key, $start_qty_meta_key, $end_qty_meta_key, $price_type_meta_key, $aelia_currency_switcher_active, $args, $currency );
                                        }
                                    }
                                }
                            } else {
                                $item_number = 0;

                                foreach ( $mapping as $index => $map ) {
                                    // Skip none base currency mapping.
                                    if ( array_key_exists( 'currency', $map ) ) {
                                        continue;
                                    }

                                    ++$item_number;

                                    $wholesale_role_meta_key  = 'wholesale_role';
                                    $wholesale_price_meta_key = 'wholesale_price';
                                    $start_qty_meta_key       = 'start_qty';
                                    $end_qty_meta_key         = 'end_qty';
                                    $price_type_meta_key      = 'price_type';
                                    $args                     = array( 'currency' => get_woocommerce_currency() );

                                    $this->_print_mapping_item( $item_number, $index, $map, $wholesale_roles, $wholesale_role_meta_key, $wholesale_price_meta_key, $start_qty_meta_key, $end_qty_meta_key, $price_type_meta_key, false, $args );
                                }
                            }
                        } else {
                        ?>
                            <tr class="no-items">
                                <td class="colspanchange" colspan="10"><?php esc_html_e( 'No Quantity Discount Rules Found', 'woocommerce-wholesale-prices-premium' ); ?></td>
                            </tr>
                            <?php
                        }
                        ?>

                        </tbody>
                    </table><!--#pqbwp-mapping-->
                </div>
            </div>
        </div><!--.product-quantity-based-wholesale-pricing-->
        <?php
    }

    /**
     * Print wholesale pricing mapping item.
     *
     * @since 1.7.0
     * @since 1.13.0 Refactor codebase and its own model.
     * @since 1.16.4 Bug fix not able to set percent discount for non base currency (WWPP-570).
     * @access public
     *
     * @param int         $item_number                    Entry item number. Item count.
     * @param int         $index                          Entry array index.
     * @param array       $map                            Mapping entry.
     * @param array       $wholesale_roles                Array of wholesale roles.
     * @param string      $wholesale_role_meta_key        Mapping wholesale role meta key.
     * @param string      $wholesale_role_price_key       Mapping wholesale price meta key.
     * @param int         $start_qty_meta_key             Starting quantity.
     * @param int         $end_qty_meta_key               Ending quantity.
     * @param string      $price_type_meta_key            Price type.
     * @param boolean     $aelia_currency_switcher_active Flag that detemines if aelia currency switcher plugin is active.
     * @param array       $args                           Additional arguments data.
     * @param string|null $currency                       Currency.
     */
    private function _print_mapping_item( $item_number, $index, $map, $wholesale_roles, $wholesale_role_meta_key, $wholesale_role_price_key, $start_qty_meta_key, $end_qty_meta_key, $price_type_meta_key, $aelia_currency_switcher_active, $args, $currency = null ) {

        if ( 0 === $item_number % 2 ) {
            $row_class = 'even';
        } else {
            $row_class = 'odd alternate';
        }
        ?>

        <tr class="<?php echo esc_attr( $row_class ); ?>">
            <td class="meta hidden">
                <span class="index"><?php echo esc_html( $index ); ?></span>
                <span class="wholesale-role"><?php echo esc_html( $map[ $wholesale_role_meta_key ] ); ?></span>
                <span class="price-type"><?php echo isset( $map[ $price_type_meta_key ] ) ? esc_html( $map[ $price_type_meta_key ] ) : 'fixed-price'; ?></span>
                <span class="wholesale-price"><?php echo esc_html( $map[ $wholesale_role_price_key ] ); ?></span>
            </td>
            <td class="wholesale-role-text">
                <?php
                if ( isset( $wholesale_roles[ $map[ $wholesale_role_meta_key ] ]['roleName'] ) ) {
                    echo esc_html( $wholesale_roles[ $map[ $wholesale_role_meta_key ] ]['roleName'] );
                } else {
                    /* Translators: $1 is user role */
                    printf( esc_html__( '%1$s role does not exist anymore', 'woocommerce-wholesale-prices-premium' ), esc_html( $map[ $wholesale_role_meta_key ] ) );
                }
                ?>
            </td>
            <td class="start-qty"><?php echo esc_html( $map[ $start_qty_meta_key ] ); ?></td>
            <td class="end-qty"><?php echo esc_html( $map[ $end_qty_meta_key ] ); ?></td>
            <td class="wholesale-price-text">
                <?php
                if ( isset( $map[ $price_type_meta_key ] ) ) {

                    if ( 'fixed-price' === $map[ $price_type_meta_key ] ) {
                        echo wp_kses_post( WWP_Helper_Functions::wwp_formatted_price( $map[ $wholesale_role_price_key ], $args ) );
                    } elseif ( 'percent-price' === $map[ $price_type_meta_key ] ) {
                        echo wp_kses_post( str_replace( get_woocommerce_currency_symbol(), '', WWP_Helper_Functions::wwp_formatted_price( $map[ $wholesale_role_price_key ], $args ) ) . '%' );
                    }
                } else {
                    echo wp_kses_post( WWP_Helper_Functions::wwp_formatted_price( $map[ $wholesale_role_price_key ], $args ) );
                }
                ?>
            </td>
                <?php
                if ( $aelia_currency_switcher_active ) {
                    ?>
                    <td class="currency"><?php echo esc_html( $currency ); ?></td>
                    <?php
                }
                ?>
            <td class="controls">
                <a class="edit dashicons dashicons-edit"></a>
                <a class="delete dashicons dashicons-no"></a>
            </td>
        </tr>
        <?php
    }

    /**
     * AJAX Interfaces
     */

    /**
     * AJAX interface to toggle product quantity based wholesale pricing feature of a product.
     *
     * @since 1.6.0
     * @since 1.13.0 Refactor codebase and its own model.
     * @access public
     *
     * @param int|null    $post_id   Product id.
     * @param string|null $enable    'yes' or 'no'.
     * @param bool|true   $ajax_call Flag that detemines if this function is being called via ajax or not.
     * @return array If called not via ajax it returns array of response data.
     */
    public function wwpp_toggle_product_quantity_based_wholesale_pricing( $post_id = null, $enable = null, $ajax_call = true ) {
        if ( true === $ajax_call || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
            check_ajax_referer( 'wwpp_toggle_product_quantity_based_wholesale_pricing', 'nonce' );
            $post_id = sanitize_key( $_POST['post_id'] );
            $enable  = sanitize_text_field( $_POST['enable'] );
        }

        // Get product object.
        $product = wc_get_product( $post_id );
        $product->update_meta_data( WWPP_POST_META_ENABLE_QUANTITY_DISCOUNT_RULE, $enable );
        $product->save();

        $response = array( 'status' => 'success' );

        if ( true === $ajax_call || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
            header( 'Content-Type: application/json' );
            echo json_encode( $response ); // phpcs:ignore
            wp_die();
        } else {
            return $response;
        }
    }

    /**
     * Add quantity discount rule. $rule parameter expected to have the following items below.
     * wholesale_role
     * start_qty
     * end_qty
     * wholesale_price
     *
     * @since 1.6.0
     * @since 1.7.0 Add Aelia Currency Switcher Plugin Integration
     * @since 1.13.0 Refactor codebase and its own model.
     * @access public
     *
     * @param int|null   $post_id Product id.
     * @param array|null $rule    Rule data.
     * @param bool|true  $ajax_call Flag that detemines if this function is being called via ajax or not.
     * @return array If called not via ajax it returns array of response data.
     */
    public function wwpp_add_quantity_discount_rule( $post_id = null, $rule = null, $ajax_call = true ) {
        if ( true === $ajax_call || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
            check_ajax_referer( 'wwpp_add_quantity_discount_rule', 'nonce' );
            $rule    = array_map( 'sanitize_text_field', $_POST['rule'] );
            $post_id = sanitize_key( $_POST['post_id'] );
        }

        $thousand_sep = get_option( 'woocommerce_price_thousand_sep' );
        $decimal_sep  = get_option( 'woocommerce_price_decimal_sep' );

        if ( $thousand_sep ) {
            $rule['wholesale_price'] = str_replace( $thousand_sep, '', $rule['wholesale_price'] );
        }

        if ( $decimal_sep ) {
            $rule['wholesale_price'] = str_replace( $decimal_sep, '.', $rule['wholesale_price'] );
        }

        // Check data format.
        if ( ! is_array( $rule ) || ! isset( $post_id, $rule['wholesale_role'], $rule['start_qty'], $rule['end_qty'], $rule['price_type'], $rule['wholesale_price'] ) ) {
            $response = array(
                'status'        => 'fail',
                'error_message' => __( 'Quantity discount rule data passed is in invalid format.', 'woocommerce-wholesale-prices-premium' ),
            );
        } else {
            // Check data validity.
            $post_id                 = sanitize_text_field( $post_id );
            $rule['wholesale_role']  = sanitize_text_field( $rule['wholesale_role'] );
            $rule['start_qty']       = sanitize_text_field( $rule['start_qty'] );
            $rule['end_qty']         = sanitize_text_field( $rule['end_qty'] );
            $rule['price_type']      = sanitize_text_field( $rule['price_type'] );
            $rule['wholesale_price'] = sanitize_text_field( $rule['wholesale_price'] );

            if ( empty( $post_id ) || empty( $rule['wholesale_role'] ) || empty( $rule['start_qty'] ) || empty( $rule['price_type'] ) || empty( $rule['wholesale_price'] ) ) {
                $response = array(
                    'status'        => 'fail',
                    'error_message' => __( 'Quantity discount rule data passed is invalid. The following fields are required ( Wholesale Role / Starting Qty / Price Type / Wholesale Price ).', 'woocommerce-wholesale-prices-premium' ),
                );
            } elseif ( ! is_numeric( $rule['start_qty'] ) || ! is_numeric( $rule['wholesale_price'] ) || ( ! empty( $rule['end_qty'] ) && ! is_numeric( $rule['end_qty'] ) ) ) {
                $response = array(
                    'status'        => 'fail',
                    'error_message' => __( 'Quantity discount rule data passed is invalid. The following fields must be a number ( Starting Qty / Ending Qty / Wholesale Price ).', 'woocommerce-wholesale-prices-premium' ),
                );
            } elseif ( ! empty( $rule['end_qty'] ) && $rule['end_qty'] < $rule['start_qty'] ) {
                $response = array(
                    'status'        => 'fail',
                    'error_message' => __( 'Ending Qty must not be less than Starting Qty', 'woocommerce-wholesale-prices-premium' ),
                );
            } else {
                // Get product object.
                $product = wc_get_product( $post_id );

                $rule['wholesale_price'] = wc_format_decimal( $rule['wholesale_price'] );

                if ( $rule['wholesale_price'] < 0 ) {
                    $rule['wholesale_price'] = 0;
                }

                $quantity_discount_rule_mapping = $product->get_meta( WWPP_POST_META_QUANTITY_DISCOUNT_RULE_MAPPING, true );
                if ( ! is_array( $quantity_discount_rule_mapping ) ) {
                    $quantity_discount_rule_mapping = array();
                }

                $dup               = false;
                $start_qty_overlap = false;
                $end_qty_overlap   = false;
                $err_indexes       = array();

                if ( WWP_ACS_Integration_Helper::aelia_currency_switcher_active() ) {
                    $base_currency = WWP_ACS_Integration_Helper::get_product_base_currency( $post_id );

                    if ( $rule['currency'] === $base_currency ) {
                        $wholesale_role_meta_key = 'wholesale_role';
                        $start_qty_meta_key      = 'start_qty';
                        $end_qty_meta_key        = 'end_qty';
                    } else {
                        $wholesale_role_meta_key = $rule['currency'] . '_wholesale_role';
                        $start_qty_meta_key      = $rule['currency'] . '_start_qty';
                        $end_qty_meta_key        = $rule['currency'] . '_end_qty';
                    }
                } else {
                    $wholesale_role_meta_key = 'wholesale_role';
                    $start_qty_meta_key      = 'start_qty';
                    $end_qty_meta_key        = 'end_qty';
                }

                foreach ( $quantity_discount_rule_mapping as $idx => $mapping ) {
                    if ( ! array_key_exists( $wholesale_role_meta_key, $mapping ) ) {
                        // One key to check is enough.
                        continue;
                    } elseif ( $mapping[ $wholesale_role_meta_key ] === $rule['wholesale_role'] ) {
                            // If it has the same wholesale role and starting quantity then they are considered as the duplicate.
                            if ( $mapping[ $start_qty_meta_key ] === $rule['start_qty'] && ! $dup ) {
                                $dup = true;
                                if ( ! in_array( $idx, $err_indexes ) ) { // phpcs:ignore
                                    $err_indexes[] = $idx;
                                }
                            }

                            // Check for overlapping mappings. Only do this if no dup yet.
                            if ( ! $dup ) {
                                if ( $rule['start_qty'] > $mapping[ $start_qty_meta_key ] && $rule['start_qty'] <= $mapping[ $end_qty_meta_key ] && false === $start_qty_overlap ) {
                                    $start_qty_overlap = true;
                                    if ( ! in_array( $idx, $err_indexes ) ) { // phpcs:ignore
                                        $err_indexes[] = $idx;
                                    }
                                }

                                if ( $rule['end_qty'] <= $mapping[ $end_qty_meta_key ] && $rule['end_qty'] >= $mapping[ $start_qty_meta_key ] && false === $end_qty_overlap ) {
                                    $end_qty_overlap = true;
                                    if ( ! in_array( $idx, $err_indexes ) ) { // phpcs:ignore
                                        $err_indexes[] = $idx;
                                    }
                                }
                            }
                    }

                    // break loop if there is dup or overlap.
                    if ( $dup || ( $start_qty_overlap && $end_qty_overlap ) ) {
                        break;
                    }
                }

                if ( $dup ) {
                    $response = array(
                        'status'          => 'fail',
                        'error_message'   => __( 'Duplicate quantity discount rule', 'woocommerce-wholesale-prices-premium' ),
                        'additional_data' => array( 'dup_index' => $err_indexes ),
                    );
                } elseif ( $start_qty_overlap && $end_qty_overlap ) {
                    $response = array(
                        'status'          => 'fail',
                        'error_message'   => __( 'Overlap quantity discount rule', 'woocommerce-wholesale-prices-premium' ),
                        'additional_data' => array( 'dup_index' => $err_indexes ),
                    );
                } else {
                    $args = array();

                    // We could be changing the key for this so we cached this here.
                    $wholesale_price = $rule['wholesale_price'];
                    $price_type      = $rule['price_type'];

                    if ( WWP_ACS_Integration_Helper::aelia_currency_switcher_active() ) {
                        $base_currency    = WWP_ACS_Integration_Helper::get_product_base_currency( $post_id );
                        $args['currency'] = $rule['currency'];

                        if ( $rule['currency'] === $base_currency ) {
                            /**
                             * Remove currency for base currency mapping. This is of compatibility reasons.
                             * We want to make wwpp work with or without aelia currency switcher plugin.
                             * We use the default keys here for base currency.
                             */
                            unset( $rule['currency'] );
                        } else {

                            /**
                             * For other currencies (not base currency) we modify the keys and append the currency code.
                             * We do this for compatibility reasons. We don't want this to have the same keys as the
                             * base currency. Coz what if Aelia was removed later? WWPP will not know what mapping to use
                             * coz they have all the same keys.
                             *
                             * Note: exception here is the $rule[ 'currency' ]. We are not using 'currency' key before so
                             * we can get away of not renaming that. Also we need not to rename this due to functionality
                             * reasons.
                             */
                            $rule = array(
                                $rule['currency'] . '_wholesale_role' => $rule['wholesale_role'],
                                $rule['currency'] . '_start_qty' => $rule['start_qty'],
                                $rule['currency'] . '_end_qty' => $rule['end_qty'],
                                $rule['currency'] . '_price_type' => $rule['price_type'],
                                $rule['currency'] . '_wholesale_price' => $rule['wholesale_price'],
                                'currency' => $rule['currency'],
                            );
                        }
                    }

                    $quantity_discount_rule_mapping[] = $rule;
                    $product->update_meta_data( WWPP_POST_META_QUANTITY_DISCOUNT_RULE_MAPPING, $quantity_discount_rule_mapping );
                    end( $quantity_discount_rule_mapping );
                    $last_inserted_item_index = key( $quantity_discount_rule_mapping );

                    if ( 'fixed-price' === $price_type ) {
                        $wholesale_price_text = WWP_Helper_Functions::wwp_formatted_price( $wholesale_price, $args );
                    } elseif ( 'percent-price' === $price_type ) {
                        $wholesale_price_text = $wholesale_price . '%';
                    }

                    $response = array(
                        'status'                   => 'success',
                        'last_inserted_item_index' => $last_inserted_item_index,
                        'wholesale_price_text'     => $wholesale_price_text,
                    );

                    // Save product.
                    $product->save();
                }
            }
        }

        if ( true === $ajax_call || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
            header( 'Content-Type: application/json' );
            echo json_encode( $response ); // phpcs:ignore
            wp_die();
        } else {
            return $response;
        }
    }

    /**
     * Edit quantity discount rule. $rule parameter expected to have the following items below.
     * wholesale_role
     * start_qty
     * end_qty
     * wholesale_price
     *
     * @since 1.6.0
     * @since 1.13.0 Refactor codebase and its own model.
     * @access public
     *
     * @param int|null   $post_id   Product id.
     * @param int|null   $index     Entry index.
     * @param array|null $rule      Rule data.
     * @param bool|true  $ajax_call Flag that detemines if this function is being called via ajax or not.
     * @return array If called not via ajax it returns array of response data.
     */
    public function wwpp_save_quantity_discount_rule( $post_id = null, $index = null, $rule = null, $ajax_call = true ) {
        if ( true === $ajax_call || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
            check_ajax_referer( 'wwpp_save_quantity_discount_rule', 'nonce' );
            $rule    = array_map( 'sanitize_text_field', $_POST['rule'] );
            $index   = sanitize_key( $_POST['index'] );
            $post_id = sanitize_key( $_POST['post_id'] );
        }

        $thousand_sep = get_option( 'woocommerce_price_thousand_sep' );
        $decimal_sep  = get_option( 'woocommerce_price_decimal_sep' );

        if ( $thousand_sep ) {
            $rule['wholesale_price'] = str_replace( $thousand_sep, '', $rule['wholesale_price'] );
        }

        if ( $decimal_sep ) {
            $rule['wholesale_price'] = str_replace( $decimal_sep, '.', $rule['wholesale_price'] );
        }

        // Check data format.
        if ( ! is_array( $rule ) || ! isset( $post_id, $index, $rule['wholesale_role'], $rule['start_qty'], $rule['end_qty'], $rule['price_type'], $rule['wholesale_price'] ) ) {
            $response = array(
                'status'        => 'fail',
                'error_message' => __( 'Quantity discount rule data passed is in invalid format.', 'woocommerce-wholesale-prices-premium' ),
            );
        } else {
            // Check data validity.
            $post_id                 = sanitize_text_field( $post_id );
            $index                   = sanitize_text_field( $index );
            $rule['wholesale_role']  = sanitize_text_field( $rule['wholesale_role'] );
            $rule['start_qty']       = sanitize_text_field( $rule['start_qty'] );
            $rule['end_qty']         = sanitize_text_field( $rule['end_qty'] );
            $rule['price_type']      = sanitize_text_field( $rule['price_type'] );
            $rule['wholesale_price'] = sanitize_text_field( $rule['wholesale_price'] );

            if ( empty( $post_id ) || '' === $index || empty( $rule['wholesale_role'] ) || empty( $rule['start_qty'] ) || empty( $rule['price_type'] ) || empty( $rule['wholesale_price'] ) ) {
                $response = array(
                    'status'        => 'fail',
                    'error_message' => __( 'Quantity discount rule data passed is invalid. The following fields are required ( Wholesale Role / Starting Qty / Price Type / Wholesale Price ).', 'woocommerce-wholesale-prices-premium' ),
                );
            } elseif ( ! is_numeric( $rule['start_qty'] ) || ! is_numeric( $rule['wholesale_price'] ) || ( ! empty( $rule['end_qty'] ) && ! is_numeric( $rule['end_qty'] ) ) ) {
                $response = array(
                    'status'        => 'fail',
                    'error_message' => __( 'Quantity discount rule data passed is invalid. The following fields must be a number ( Starting Qty / Ending Qty / Wholesale Price ).', 'woocommerce-wholesale-prices-premium' ),
                );
            } elseif ( ! empty( $rule['end_qty'] ) && $rule['end_qty'] < $rule['start_qty'] ) {
                $response = array(
                    'status'        => 'fail',
                    'error_message' => __( 'Ending Qty must not be less than Starting Qty', 'woocommerce-wholesale-prices-premium' ),
                );
            } else {
                // Get product object.
                $product = wc_get_product( $post_id );

                $quantity_discount_rule_mapping = $product->get_meta( WWPP_POST_META_QUANTITY_DISCOUNT_RULE_MAPPING, true );
                if ( ! is_array( $quantity_discount_rule_mapping ) ) {
                    $quantity_discount_rule_mapping = array();
                }

                if ( ! array_key_exists( $index, $quantity_discount_rule_mapping ) ) {
                    $response = array(
                        'status'        => 'fail',
                        'error_message' => __( 'Quantity discount rule entry you want to edit does not exist', 'woocommerce-wholesale-prices-premium' ),
                    );
                } else {
                    $rule['wholesale_price'] = wc_format_decimal( $rule['wholesale_price'] );

                    if ( $rule['wholesale_price'] < 0 ) {
                        $rule['wholesale_price'] = 0;
                    }

                    $dup               = false;
                    $start_qty_overlap = false;
                    $end_qty_overlap   = false;
                    $err_indexes       = array();

                    if ( WWP_ACS_Integration_Helper::aelia_currency_switcher_active() ) {
                        $base_currency = WWP_ACS_Integration_Helper::get_product_base_currency( $post_id );

                        if ( $rule['currency'] === $base_currency ) {
                            $wholesale_role_meta_key = 'wholesale_role';
                            $start_qty_meta_key      = 'start_qty';
                            $end_qty_meta_key        = 'end_qty';
                        } else {
                            $wholesale_role_meta_key = $rule['currency'] . '_wholesale_role';
                            $start_qty_meta_key      = $rule['currency'] . '_start_qty';
                            $end_qty_meta_key        = $rule['currency'] . '_end_qty';
                        }
                    } else {
                        $wholesale_role_meta_key = 'wholesale_role';
                        $start_qty_meta_key      = 'start_qty';
                        $end_qty_meta_key        = 'end_qty';
                    }

                    foreach ( $quantity_discount_rule_mapping as $idx => $mapping ) {
                        if ( ! array_key_exists( $wholesale_role_meta_key, $mapping ) ) {
                            // One meta key check is enough.
                            continue;
                        } elseif ( $mapping[ $wholesale_role_meta_key ] === $rule['wholesale_role'] ) {

                                /**
                                 * If it has the same wholesale role and starting quantity then they are considered as the duplicate
                                 * Since this is an edit, we need to check too if this is not the same entry as we are editing
                                 */
                                if ( $mapping[ $start_qty_meta_key ] === $rule['start_qty'] && (int) $index !== (int) $idx && ! $dup ) {
                                    $dup = true;

                                    if ( ! in_array( $idx, $err_indexes ) ) { // phpcs:ignore
                                        $err_indexes[] = $idx;
                                    }
                                }

                                // Check for overlapping mappings. Only do this if no dup yet.
                                if ( ! $dup && (int) $index !== (int) $idx ) {
                                    if ( $rule['start_qty'] >= $mapping[ $start_qty_meta_key ] && $rule['start_qty'] <= $mapping[ $end_qty_meta_key ] && false === $start_qty_overlap ) {
                                        $start_qty_overlap = true;

                                        if ( ! in_array( $idx, $err_indexes ) ) { // phpcs:ignore
                                            $err_indexes[] = $idx;
                                        }
                                    }

                                    if ( $rule['end_qty'] <= $mapping[ $end_qty_meta_key ] && $rule['end_qty'] >= $mapping[ $start_qty_meta_key ] && false === $end_qty_overlap ) {
                                        $end_qty_overlap = true;

                                        if ( ! in_array( $idx, $err_indexes ) ) { // phpcs:ignore
                                            $err_indexes[] = $idx;
                                        }
                                    }
                                }
                        }

                        // break loop if there is dup or overlap.
                        if ( $dup || ( $start_qty_overlap && $end_qty_overlap ) ) {
                            break;
                        }
                    }

                    if ( $dup ) {
                        $response = array(
                            'status'          => 'fail',
                            'error_message'   => __( 'Duplicate quantity discount rule', 'woocommerce-wholesale-prices-premium' ),
                            'additional_data' => array( 'dup_index' => $err_indexes ),
                        );
                    } elseif ( $start_qty_overlap && $end_qty_overlap ) {
                        $response = array(
                            'status'          => 'fail',
                            'error_message'   => __( 'Overlap quantity discount rule', 'woocommerce-wholesale-prices-premium' ),
                            'additional_data' => array( 'dup_index' => $err_indexes ),
                        );
                    } else {
                        $args = array();

                        // We could be changing the key for this so we cached this here.
                        $wholesale_price = $rule['wholesale_price'];
                        $price_type      = $rule['price_type'];

                        if ( WWP_ACS_Integration_Helper::aelia_currency_switcher_active() ) {
                            $base_currency    = WWP_ACS_Integration_Helper::get_product_base_currency( $post_id );
                            $args['currency'] = $rule['currency'];

                            if ( $rule['currency'] === $base_currency ) {
                                /**
                                 * Remove currency for base currency mapping. This is of compatibility reasons.
                                 * We want to make wwpp work with or without aelia currency switcher plugin.
                                 * We use the default keys here for base currency.
                                 */
                                unset( $rule['currency'] );
                            } else {
                                /**
                                 * For other currencies (not base currency) we modify the keys and append the currency code.
                                 * We do this for compatibility reasons. We don't want this to have the same keys as the
                                 * base currency. Coz what if Aelia was removed later? WWPP will not know what mapping to use
                                 * coz they have all the same keys.
                                 *
                                 * Note: exception here is the $rule[ 'currency' ]. We are not using 'currency' key before so
                                 * we can get away of not renaming that. Also we need not to rename this due to functionality
                                 * reasons.
                                 */
                                $rule = array(
                                    $rule['currency'] . '_wholesale_role' => $rule['wholesale_role'],
                                    $rule['currency'] . '_start_qty' => $rule['start_qty'],
                                    $rule['currency'] . '_end_qty' => $rule['end_qty'],
                                    $rule['currency'] . '_price_type' => $rule['price_type'],
                                    $rule['currency'] . '_wholesale_price' => $rule['wholesale_price'],
                                    'currency' => $rule['currency'],
                                );
                            }
                        }

                        $quantity_discount_rule_mapping[ $index ] = $rule;
                        $product->update_meta_data( WWPP_POST_META_QUANTITY_DISCOUNT_RULE_MAPPING, $quantity_discount_rule_mapping );

                        if ( 'fixed-price' === $price_type ) {
                            $wholesale_price_text = WWP_Helper_Functions::wwp_formatted_price( $wholesale_price, $args );
                        } elseif ( 'percent-price' === $price_type ) {
                            $wholesale_price_text = str_replace( get_woocommerce_currency_symbol(), '', WWP_Helper_Functions::wwp_formatted_price( $wholesale_price, $args ) ) . '%';
                        }

                        $response = array(
                            'status'               => 'success',
                            'wholesale_price_text' => $wholesale_price_text,
                        );

                        // Save product.
                        $product->save();
                    }
                }
            }
        }

        if ( true === $ajax_call || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
            header( 'Content-Type: application/json' );
            echo json_encode( $response ); // phpcs:ignore
            wp_die();
        } else {
            return $response;
        }
    }

    /**
     * Delete quantity discount rule.
     *
     * @since 1.6.0
     * @since 1.13.0 Refactor codebase and its own model.
     * @access public
     *
     * @param int|null  $post_id   Product id.
     * @param int|null  $index     Entry index.
     * @param bool|true $ajax_call Flag that detemines if this function is being called via ajax or not.
     * @return array If called not via ajax it returns array of response data.
     */
    public function wwpp_delete_quantity_discount_rule( $post_id = null, $index = null, $ajax_call = true ) {
        if ( true === $ajax_call || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
            check_ajax_referer( 'wwpp_delete_quantity_discount_rule', 'nonce' );
            $post_id = sanitize_key( $_POST['post_id'] );
            $index   = sanitize_key( $_POST['index'] );
        }

        // Get product object.
        $product = wc_get_product( $post_id );

        $quantity_discount_rule_mapping = $product->get_meta( WWPP_POST_META_QUANTITY_DISCOUNT_RULE_MAPPING, true );
        if ( ! is_array( $quantity_discount_rule_mapping ) ) {
            $quantity_discount_rule_mapping = array();
        }

        if ( ! array_key_exists( $index, $quantity_discount_rule_mapping ) ) {
            $response = array(
                'status'        => 'fail',
                'error_message' => __( 'Quantity discount rule entry you want to delete does not exist', 'woocommerce-wholesale-prices-premium' ),
            );
        } else {
            unset( $quantity_discount_rule_mapping[ $index ] );
            $product->update_meta_data( WWPP_POST_META_QUANTITY_DISCOUNT_RULE_MAPPING, $quantity_discount_rule_mapping );

            $response = array( 'status' => 'success' );

            // Save product.
            $product->save();
        }

        if ( true === $ajax_call || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
            header( 'Content-Type: application/json' );
            echo json_encode( $response ); // phpcs:ignore
            wp_die();
        } else {
            return $response;
        }
    }

    /**
     * Product Meta Quantity Discount Role Mapping Export and Import
     */

    /**
     * Fix for multiple wwpp_post_meta_quantity_discount_rule_mapping meta EXPORT
     *
     * @since 1.17
     * @access public
     *
     * @param mixed  $value      Mixed value.
     * @param object $meta       WC_Meta_Data Object.
     * @param object $product    WC_Product_Simple | WC_Product_Variable object.
     * @param array  $row        Array of exported product data.
     * @return mixed String|Int|Object
     */
    public function wc_export_meta_value_filter( $value, $meta, $product, $row ) { // phpcs:ignore
        if ( 'wwpp_post_meta_quantity_discount_rule_mapping' === $meta->key ) {
            // Convert the array into json ( wc will export this coz this is considered as string ).
            return wp_json_encode( $value );
        }

        return $value;
    }

    /**
     * Fix for multiple wwpp_post_meta_quantity_discount_rule_mapping meta IMPORT
     *
     * @since 1.17
     * @access public
     *
     * @param object $product    WC_Product_Simple | WC_Product_Variable object.
     * @param array  $data       Array of imported product data.
     */
    public function wc_import_product( $product, $data ) { // phpcs:ignore
        $quantity_discount_rule_mapping = $product->get_meta( 'wwpp_post_meta_quantity_discount_rule_mapping', true );

        if ( '' !== $quantity_discount_rule_mapping && $this->is_valid_json( $quantity_discount_rule_mapping ) ) {
            $quantity_discount_rule_mapping = json_decode( $quantity_discount_rule_mapping, true );

            // Update wwpp_post_meta_quantity_discount_rule_mapping meta with a proper serialized object.
            $product->update_meta_data( 'wwpp_post_meta_quantity_discount_rule_mapping', $quantity_discount_rule_mapping );

            // Save product.
            $product->save();
        }
    }

    /**
     * Check if a string is a valid json.
     *
     * @since 1.17
     * @access public
     *
     * @param string $json_string String to check.
     *
     * @return bool
     */
    public function is_valid_json( $json_string ) {
        json_decode( $json_string );
        return ( json_last_error() === JSON_ERROR_NONE );
    }

    /**
     * Save wwpp post meta.
     *
     * @since 2.0.2
     * @access public
     *
     * @param int    $post_id Product ID.
     * @param object $post WP_Post Object.
     * @param bool   $update true if updating.
     */
    public function save_wwpp_post_meta( $post_id, $post, $update ) {
        // Save post meta for quantity discount rule.
        $this->save_wwpp_post_meta_enable_quantity_discount_rule( $post_id, $post, $update );

        // Save post meta for WC Subscriptions.
        if ( WWP_Helper_Functions::is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' ) ) {
            $this->save_wwpp_post_meta_subscription( $post_id, $post, $update );
            $this->save_wwpp_post_meta_variable_subscription( $post_id, $post, $update );
        }
    }

    /**
     * Fix post meta 'wwpp_post_meta_enable_quantity_discount_rule' not saving properly.
     *
     * @since 1.19
     * @access public
     *
     * @param object $post_id    WC Product ID.
     * @param object $post       WP_Post Object.
     * @param bool   $update     true if updating.
     */
    public function save_wwpp_post_meta_enable_quantity_discount_rule( $post_id, $post, $update ) { // phpcs:ignore
        if ( isset( $_POST[ WWPP_POST_META_ENABLE_QUANTITY_DISCOUNT_RULE . '_' . $post_id ] ) ) { // phpcs:ignore
            // Get product object.
            $product = wc_get_product( $post_id );
            $product->update_meta_data( WWPP_POST_META_ENABLE_QUANTITY_DISCOUNT_RULE, sanitize_text_field( $_POST[ WWPP_POST_META_ENABLE_QUANTITY_DISCOUNT_RULE . '_' . $post_id ] ) ); // phpcs:ignore
            $product->save();
        }
    }

    /**
     * Save wwpp post meta for WC Subscriptions.
     *
     * @since 2.0.2
     * @access public
     *
     * @param int    $post_id Product ID.
     * @param object $post WP_Post Object.
     * @param bool   $update true if updating.
     */
    public function save_wwpp_post_meta_subscription( $post_id, $post, $update ) { // phpcs:ignore
        $product_type = isset( $_POST['product-type'] ) ? $_POST['product-type'] : ''; // phpcs:ignore
        if ( 'subscription' === $product_type ) {
            // Get product object.
            $product = wc_get_product( $post_id );

            foreach ( $this->_registered_wholesale_roles as $role_key => $role ) {

                // Get subscription discount type per role.
                $discount_type = isset( $_POST[ $role_key . '_wholesale_discount_type' ] ) ? $_POST[ $role_key . '_wholesale_discount_type' ] : 'fixed'; // phpcs:ignore

                // Meta subscription type.
                if ( ! empty( $discount_type ) ) {
                    $product->update_meta_data( $role_key . '_wholesale_discount_type', sanitize_text_field( $discount_type ) ); // phpcs:ignore
                }

                // Meta subscription amount.
                if ( isset( $_POST[ $role_key . '_wholesale_price' ] ) ) { // phpcs:ignore
                    $product->update_meta_data( $role_key . '_wholesale_price', sanitize_text_field( $_POST[ $role_key . '_wholesale_price' ] ) ); // phpcs:ignore

                    // check if the price is not empty and greater than 0.
                    if ( '' !== $_POST[ $role_key . '_wholesale_price' ] && 0 < $_POST[ $role_key . '_wholesale_price' ] ) { // phpcs:ignore
                        $product->update_meta_data( $role_key . '_have_wholesale_price', 'yes' );
                    } else {
                        $product->update_meta_data( $role_key . '_have_wholesale_price', 'no' );
                    }
                }

                // Meta subscription discount.
                if ( 'percentage' === $discount_type && isset( $_POST[ $role_key . '_wholesale_percentage_discount' ] ) ) { // phpcs:ignore
                    $product->update_meta_data( $role_key . '_wholesale_percentage_discount', sanitize_text_field( $_POST[ $role_key . '_wholesale_percentage_discount' ] ) ); // phpcs:ignore
                } else {
                    $product->delete_meta_data( $role_key . '_wholesale_percentage_discount' );
                }

                $singup_fee_key               = $role_key . '_wholesale_signup_fee';
                $singup_fee_is_percentage_key = $role_key . '_wholesale_signup_fee_discount';

                // Meta signup fee.
                if ( isset( $_POST[ $singup_fee_key ] ) ) { // phpcs:ignore
                    $product->update_meta_data( $singup_fee_key, sanitize_text_field( $_POST[ $singup_fee_key ] ) ); // phpcs:ignore
                }

                // Meta signup fee is percentage.
                if ( 'percentage' === $discount_type && isset( $_POST[ $singup_fee_is_percentage_key ] ) ) { // phpcs:ignore
                    $product->update_meta_data( $singup_fee_is_percentage_key, sanitize_text_field( $_POST[ $singup_fee_is_percentage_key ] ) ); // phpcs:ignore
                } else {
                    $product->delete_meta_data( $singup_fee_is_percentage_key );
                }

                // Meta subscription sale price.
                if ( isset( $_POST[ $role_key . '_wholesale_sale_price' ] ) ) { // phpcs:ignore
                    $product->update_meta_data( $role_key . '_wholesale_sale_price', sanitize_text_field( $_POST[ $role_key . '_wholesale_sale_price' ] ) ); // phpcs:ignore

                    // check if the price is not empty and greater than 0.
                    if ( '' !== $_POST[ $role_key . '_wholesale_sale_price' ] && 0 < $_POST[ $role_key . '_wholesale_sale_price' ] ) { // phpcs:ignore
                        $product->update_meta_data( $role_key . '_have_wholesale_sale_price', 'yes' );
                    } else {
                        $product->update_meta_data( $role_key . '_have_wholesale_sale_price', 'no' );
                    }
                }

                // Meta subscription sale price dates from.
                if ( isset( $_POST[ $role_key . '_wholesale_sale_price_dates_from' ] ) ) { // phpcs:ignore
                    $product->update_meta_data( $role_key . '_wholesale_sale_price_dates_from', sanitize_text_field( $_POST[ $role_key . '_wholesale_sale_price_dates_from' ] ) ); // phpcs:ignore
                }

                // Meta subscription sale price dates to.
                if ( isset( $_POST[ $role_key . '_wholesale_sale_price_dates_to' ] ) ) { // phpcs:ignore
                    $product->update_meta_data( $role_key . '_wholesale_sale_price_dates_to', sanitize_text_field( $_POST[ $role_key . '_wholesale_sale_price_dates_to' ] ) ); // phpcs:ignore
                }

                // Meta subscription sale is percentage.
                if ( 'percentage' === $discount_type && isset( $_POST[ $role_key . '_wholesale_sale_discount' ] ) ) { // phpcs:ignore
                    $product->update_meta_data( $role_key . '_wholesale_sale_discount', sanitize_text_field( $_POST[ $role_key . '_wholesale_sale_discount' ] ) ); // phpcs:ignore
                } else {
                    $product->delete_meta_data( $role_key . '_wholesale_sale_discount' );
                }

                // Meta subscription minimum order quantity.
                if ( isset( $_POST[ $role_key . '_simple_wholesale_minimum_order_quantity' ] ) ) { // phpcs:ignore
                    $product->update_meta_data( $role_key . '_simple_wholesale_minimum_order_quantity', sanitize_text_field( $_POST[ $role_key . '_simple_wholesale_minimum_order_quantity' ] ) ); // phpcs:ignore
                }
            }

            // Save product.
            $product->save();
        }
    }

    /**
     * Save wwpp post meta for WC Subscriptions Variable Product.
     *
     * @since 2.0.2
     * @access public
     *
     * @param int    $post_id Product ID.
     * @param object $post WP_Post Object.
     * @param bool   $update true if updating.
     *
     * @return void
     */
    public function save_wwpp_post_meta_variable_subscription( $post_id, $post, $update ) { // phpcs:ignore
        $product_type = isset( $_POST['product-type'] ) ? $_POST['product-type'] : ''; // phpcs:ignore
        if ( 'variable-subscription' === $product_type ) {
            // Get product object.
            $main_variable_product = wc_get_product( $post_id );

            $main_variable_children = $main_variable_product->get_children();
            if ( ! empty( $main_variable_children ) ) {
                foreach ( $this->_registered_wholesale_roles as $role_key => $role ) {
                    $wholesale_child_prices = array();
                    foreach ( $main_variable_children as $main_variable_child ) {
                        $child_wholesale_price = get_post_meta( $main_variable_child, $role_key . '_wholesale_price', true );

                        if ( ! empty( $child_wholesale_price ) ) {
                            $wholesale_child_prices[ $role_key ] = $child_wholesale_price;
                        }
                    }

                    if ( ! empty( $wholesale_child_prices ) && isset( $wholesale_child_prices[ $role_key ] ) ) {
                        $main_variable_product->update_meta_data( $role_key . '_have_wholesale_price', 'yes' );
                    } else {
                        $main_variable_product->update_meta_data( $role_key . '_have_wholesale_price', 'no' );
                    }
                }
            }

            // Save the data.
            $main_variable_product->save();
        }
    }

    /**
     * Register ajax handlers.
     *
     * @since 1.13.0
     * @access public
     */
    public function register_ajax_handlers() {
        add_action( 'wp_ajax_wwppToggleProductQuantityBasedWholesalePricing', array( $this, 'wwpp_toggle_product_quantity_based_wholesale_pricing' ) );
        add_action( 'wp_ajax_wwppAddQuantityDiscountRule', array( $this, 'wwpp_add_quantity_discount_rule' ) );
        add_action( 'wp_ajax_wwppSaveQuantityDiscountRule', array( $this, 'wwpp_save_quantity_discount_rule' ) );
        add_action( 'wp_ajax_wwppDeleteQuantityDiscountRule', array( $this, 'wwpp_delete_quantity_discount_rule' ) );
    }

    /**
     * Add meta on product creation and update.
     * '{$role_key}_have_wholesale_sale_price': determine if product has wholesale sale price,
     * '{$role_key}_have_on_sale_wholesale_sale_price' : determine if product has active wholesale sale price by respecting the wholesale sale schedule,
     * The purpose of this metas is to increase performance significantly.
     *
     * @since 1.30.1.1
     * @access public
     *
     * @param int   $product_id                     The product ID.
     * @param array $all_registered_wholesale_roles Array of all registered wholesale roles.
     */
    public static function maybe_set_have_wholesale_sale_price_meta( $product_id, $all_registered_wholesale_roles ) {

        // Get product object.
        $product = wc_get_product( $product_id );

        foreach ( $all_registered_wholesale_roles as $role_key => $role ) {

            $product->delete_meta_data( "{$role_key}_have_wholesale_sale_price" );
            $product->delete_meta_data( "{$role_key}_have_on_sale_wholesale_sale_price" );

            if ( '' !== $product->get_meta( "{$role_key}_wholesale_sale_price", true ) ) {
                $is_on_wholesale_sale = true;
                $date_on_sale_from    = $product->get_meta( "{$role_key}_wholesale_sale_price_dates_from", true );
                $date_on_sale_to      = $product->get_meta( "{$role_key}_wholesale_sale_price_dates_to", true );

                $product->update_meta_data( "{$role_key}_have_wholesale_sale_price", 'yes' );

                if ( $date_on_sale_from && $date_on_sale_from > time() ) {
                    $is_on_wholesale_sale = false;
                }

                if ( $date_on_sale_to && $date_on_sale_to < time() ) {
                    $is_on_wholesale_sale = false;
                }

                if ( true === $is_on_wholesale_sale ) {
                    $product->update_meta_data( "{$role_key}_have_on_sale_wholesale_sale_price", 'yes' );
                }
            }
        }

        // Save product.
        $product->save();
    }

    /**
     * Execute model.
     *
     * @since 1.13.0
     * @access public
     */
    public function run() {

        add_action( 'woocommerce_product_options_pricing', array( $this, 'add_simple_product_quantity_based_wholesale_price_custom_field' ), 30, 1 );
        add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'add_variation_product_quantity_based_wholesale_price_custom_field' ), 30, 3 );

        add_action( 'init', array( $this, 'register_ajax_handlers' ) );

        // Properly import/export wwpp_post_meta_quantity_discount_rule_mapping meta.
        add_filter( 'woocommerce_product_export_meta_value', array( $this, 'wc_export_meta_value_filter' ), 10, 4 );
        add_action( 'woocommerce_product_import_inserted_product_object', array( $this, 'wc_import_product' ), 10, 2 );

        // Set qty based wholesale discount on the parent variable level.
        add_action( 'woocommerce_product_options_sku', array( $this, 'add_variable_level_product_quantity_based_wholesale_price_custom_field' ), 20 );

        // When saving a product.
        add_action( 'save_post_product', array( $this, 'save_wwpp_post_meta' ), 10, 3 );
    }
}
