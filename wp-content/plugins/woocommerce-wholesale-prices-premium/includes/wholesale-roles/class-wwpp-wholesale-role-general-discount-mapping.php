<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Model that houses the logic of wholesale roles general discount mapping.
 *
 * @since 1.14.0
 */
class WWPP_Wholesale_Role_General_Discount_Mapping {

    /**
     * Class Properties
     */

    /**
     * Property that holds the single main instance of WWPP_Wholesale_Role_General_Discount_Mapping.
     *
     * @since 1.14.0
     * @access private
     * @var WWPP_Wholesale_Role_General_Discount_Mapping
     */
    private static $_instance;

    /**
     * Model that houses the logic of retrieving information relating to wholesale role/s of a user.
     *
     * @since 1.14.0
     * @access private
     * @var WWPP_Wholesale_Roles
     */
    private $_wwpp_wholesale_roles;

    /**
     * Class Methods
     */

    /**
     * WWPP_Wholesale_Role_General_Discount_Mapping constructor.
     *
     * @since 1.14.0
     * @access public
     *
     * @param array $dependencies Array of instance objects of all dependencies of WWPP_Wholesale_Role_General_Discount_Mapping model.
     */
    public function __construct( $dependencies ) {
        $this->_wwpp_wholesale_roles = $dependencies['WWPP_Wholesale_Roles'];
    }

    /**
     * Ensure that only one instance of WWPP_Wholesale_Role_General_Discount_Mapping is loaded or can be loaded (Singleton Pattern).
     *
     * @since 1.14.0
     * @access public
     *
     * @param array $dependencies Array of instance objects of all dependencies of WWPP_Wholesale_Role_General_Discount_Mapping model.
     * @return WWPP_Wholesale_Role_General_Discount_Mapping
     */
    public static function instance( $dependencies ) {
        if ( ! self::$_instance instanceof self ) {
            self::$_instance = new self( $dependencies );
        }

        return self::$_instance;
    }

    /**
     * General Per Wholesale Role Discount
     */

    /**
     * Add wholesale role / general discount mapping.
     * $discountMapping variable is expected to be an array with the following keys.
     * wholesale_role
     * general_discount
     *
     * @since 1.2.0
     * @since 1.14.0 Refactor codebase and move to its proper model.
     * @access public
     *
     * @param null|array $discount_mapping Discount mapping data.
     */
    public function add_wholesale_role_general_discount_mapping( $discount_mapping = null ) {
        // Security checks.
        if ( ! check_ajax_referer( 'wwppAddWholesaleRoleGeneralDiscountMapping', 'nonce', false ) ) {
            @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
            echo wp_json_encode(
                array(
                    'status'    => 'fail',
                    'error_msg' => __(
                        'Security check failed',
                        'woocommerce-wholesale-prices-premium'
                    ),
                )
            );
            wp_die();
        }

        // Fetch discount mapping from $_POST var and sanitize.
        $discount_mapping = array_map( 'sanitize_text_field', $_POST['discountMapping'] );

        $saved_discount_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_GENERAL_DISCOUNT_MAPPING );
        if ( ! is_array( $saved_discount_mapping ) ) {
            $saved_discount_mapping = array();
        }

        if ( ! array_key_exists( $discount_mapping['wholesale_role'], $saved_discount_mapping ) ) {
            $wwpp_product_cache_option = get_option( 'wwpp_enable_product_cache' );

            if ( 'yes' === $wwpp_product_cache_option ) {
                global $wc_wholesale_prices_premium;
                $wc_wholesale_prices_premium->wwpp_cache->clear_product_transients_cache();
            }

            $saved_discount_mapping[ $discount_mapping['wholesale_role'] ] = $discount_mapping['general_discount'];
            update_option( WWPP_OPTION_WHOLESALE_ROLE_GENERAL_DISCOUNT_MAPPING, $saved_discount_mapping, 'no' );
            $response = array( 'status' => 'success' );

            do_action( 'wwpp_add_wholesale_role_general_discount_mapping' );

        } else {
            $response = array(
                'status'        => 'fail',
                'error_message' => __( 'Duplicate Entry, Entry Already Exists', 'woocommerce-wholesale-prices-premium' ),
            );
        }

        @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
        echo wp_json_encode( $response );
        wp_die();
    }

    /**
     * Edit saved wholesale role / general discount mapping.
     *
     * @since 1.2.0
     * @since 1.14.0 Refactor codebase and move to its proper model.
     * @access public
     *
     * @param null|array $discount_mapping Discount mapping data.
     */
    public function edit_wholesale_role_general_discount_mapping( $discount_mapping = null ) {
        // Security checks.
        if ( ! check_ajax_referer( 'wwppEditWholesaleRoleGeneralDiscountMapping', 'nonce', false ) ) {
            @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
            echo wp_json_encode(
                array(
                    'status'    => 'fail',
                    'error_msg' => __(
                        'Security check failed',
                        'woocommerce-wholesale-prices-premium'
                    ),
                )
            );
            wp_die();
        }

        $discount_mapping = array_map( 'sanitize_text_field', $_POST['discountMapping'] );

        $saved_discount_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_GENERAL_DISCOUNT_MAPPING );
        if ( ! is_array( $saved_discount_mapping ) ) {
            $saved_discount_mapping = array();
        }

        if ( array_key_exists( $discount_mapping['wholesale_role'], $saved_discount_mapping ) ) {

            $wwpp_product_cache_option = get_option( 'wwpp_enable_product_cache' );

            if ( 'yes' === $wwpp_product_cache_option ) {
                global $wc_wholesale_prices_premium;
                $wc_wholesale_prices_premium->wwpp_cache->clear_product_transients_cache();
            }

            $saved_discount_mapping[ $discount_mapping['wholesale_role'] ] = $discount_mapping['general_discount'];
            update_option( WWPP_OPTION_WHOLESALE_ROLE_GENERAL_DISCOUNT_MAPPING, $saved_discount_mapping, 'no' );
            $response = array( 'status' => 'success' );

            do_action( 'wwpp_edit_wholesale_role_general_discount_mapping' );

        } else {
            $response = array(
                'status'        => 'fail',
                'error_message' => __( 'Entry to be edited does not exist', 'woocommerce-wholesale-prices-premium' ),
            );
        }

        @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
        echo wp_json_encode( $response );
        wp_die();
    }

    /**
     * Delete a wholesale role / general discount mapping entry.
     *
     * @since 1.2.0
     * @since 1.14.0 Refactor codebase and move to its proper model.
     * @access public
     *
     * @param null|string $wholesale_role The wholesale role.
     * @return array Operation status.
     */
    public function delete_wholesale_role_general_discount_mapping( $wholesale_role = null ) {
        // Security checks.
        if ( ! check_ajax_referer( 'wwppDeleteWholesaleRoleGeneralDiscountMapping', 'nonce', false ) ) {
            @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
            echo wp_json_encode(
                array(
                    'status'    => 'fail',
                    'error_msg' => __(
                        'Security check failed',
                        'woocommerce-wholesale-prices-premium'
                    ),
                )
            );
            wp_die();
        }

        $wholesale_role = sanitize_text_field( $_POST['wholesaleRole'] );

        $saved_discount_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_GENERAL_DISCOUNT_MAPPING );
        if ( ! is_array( $saved_discount_mapping ) ) {
            $saved_discount_mapping = array();
        }

        if ( array_key_exists( $wholesale_role, $saved_discount_mapping ) ) {

            $wwpp_product_cache_option = get_option( 'wwpp_enable_product_cache' );

            if ( 'yes' === $wwpp_product_cache_option ) {
                global $wc_wholesale_prices_premium;
                $wc_wholesale_prices_premium->wwpp_cache->clear_product_transients_cache();
            }

            unset( $saved_discount_mapping[ $wholesale_role ] );
            update_option( WWPP_OPTION_WHOLESALE_ROLE_GENERAL_DISCOUNT_MAPPING, $saved_discount_mapping, 'no' );
            $response = array( 'status' => 'success' );

            do_action( 'wwpp_delete_wholesale_role_general_discount_mapping' );

        } else {
            $response = array(
                'status'        => 'fail',
                'error_message' => __( 'Entry to be deleted does not exist', 'woocommerce-wholesale-prices-premium' ),
            );
        }

        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
            echo wp_json_encode( $response );
            wp_die();
        } else {
            return true;
        }
    }

    /**
     * General Wholesale Role Cart Quantity Based Wholesale Discount
     */

    /**
     * Validate mapping entry.
     *
     * @since 1.16.0
     * @access public
     *
     * @param array  $rule                           Array of rule data.
     * @param array  $quantity_discount_rule_mapping Array of quantity discount rule mapping.
     * @param string $mode                           Add or Edit.
     */
    private function _validate_mapping_entry( $rule, $quantity_discount_rule_mapping, $mode = 'add' ) {
        // Check data format.
        if ( ! is_array( $rule ) || ! isset( $rule['wholesale_role'], $rule['start_qty'], $rule['end_qty'], $rule['percent_discount'] ) ) {
            return array(
                'status'    => 'fail',
                'error_msg' => __( 'Quantity discount rule data passed is in invalid format.', 'woocommerce-wholesale-prices-premium' ),
            );
        } elseif ( 'edit' === $mode && ! isset( $rule['index'] ) ) {
            return array(
                'status'    => 'fail',
                'error_msg' => __( 'Quantity discount rule data passed is in invalid format.', 'woocommerce-wholesale-prices-premium' ),
            );
        } else {
            // Check data validity.
            if ( 'edit' === $mode ) {
                $rule['index'] = sanitize_text_field( $rule['index'] );
            }

            $rule['wholesale_role']   = sanitize_text_field( $rule['wholesale_role'] );
            $rule['start_qty']        = sanitize_text_field( $rule['start_qty'] );
            $rule['end_qty']          = sanitize_text_field( $rule['end_qty'] );
            $rule['percent_discount'] = sanitize_text_field( $rule['percent_discount'] );

            if ( 'edit' === $mode && '' === $rule['index'] ) {
                return array(
                    'status'    => 'fail',
                    'error_msg' => __( 'Quantity discount rule data passed is invalid. Index of the mapping to edit not passed', 'woocommerce-wholesale-prices-premium' ),
                );
            } elseif ( empty( $rule['wholesale_role'] ) || empty( $rule['start_qty'] ) || empty( $rule['percent_discount'] ) ) {
                return array(
                    'status'    => 'fail',
                    'error_msg' => __( 'Quantity discount rule data passed is invalid. The following fields are required ( Wholesale Role / Starting Qty / Percent Discount ).', 'woocommerce-wholesale-prices-premium' ),
                );
            } elseif ( ! is_numeric( $rule['start_qty'] ) || ! is_numeric( $rule['percent_discount'] ) || ( ! empty( $rule['end_qty'] ) && ! is_numeric( $rule['end_qty'] ) ) ) {
                return array(
                    'status'    => 'fail',
                    'error_msg' => __( 'Quantity discount rule data passed is invalid. The following fields must be a number ( Starting Qty / Ending Qty / Wholesale Price ).', 'woocommerce-wholesale-prices-premium' ),
                );
            } elseif ( ! empty( $rule['end_qty'] ) && $rule['end_qty'] < $rule['start_qty'] ) {
                return array(
                    'status'    => 'fail',
                    'error_msg' => __( 'Ending Qty must not be less than Starting Qty', 'woocommerce-wholesale-prices-premium' ),
                );
            } else {
                if ( 'edit' === $mode && ! array_key_exists( $rule['index'], $quantity_discount_rule_mapping ) ) {
                    return array(
                        'status'    => 'fail',
                        'error_msg' => __( 'Quantity discount rule entry you want to edit does not exist', 'woocommerce-wholesale-prices-premium' ),
                    );
                }

                $rule['percent_discount'] = wc_format_decimal( $rule['percent_discount'] );

                if ( $rule['percent_discount'] < 0 ) {
                    $rule['percent_discount'] = 0;
                }

                $dup               = false;
                $start_qty_overlap = false;
                $end_qty_overlap   = false;
                $err_indexes       = array();

                $wholesale_role_meta_key = 'wholesale_role';
                $start_qty_meta_key      = 'start_qty';
                $end_qty_meta_key        = 'end_qty';

                foreach ( $quantity_discount_rule_mapping as $idx => $mapping ) {
                    if ( ! array_key_exists( $wholesale_role_meta_key, $mapping ) ) {
                        continue;
                    } elseif ( $mapping[ $wholesale_role_meta_key ] === $rule['wholesale_role'] ) {

                        // If it has the same wholesale role and starting quantity then they are considered as the duplicate.
                        if ( $mapping[ $start_qty_meta_key ] === $rule['start_qty'] && ! $dup && ( 'edit' !== $mode || ( 'edit' === $mode && $rule['index'] != $idx ) ) ) { // phpcs:ignore
                            $dup = true;

                            if ( ! in_array( $idx, $err_indexes, true ) ) {
                                $err_indexes[] = $idx;
                            }
                        }

                        // Check for overlapping mappings. Only do this if no dup yet.
                        if ( ! $dup && ( 'edit' !== $mode || ( 'edit' === $mode && $rule['index'] !== $idx ) ) ) {

                            if ( $rule['start_qty'] > $mapping[ $start_qty_meta_key ] && $rule['start_qty'] <= $mapping[ $end_qty_meta_key ] && false === $start_qty_overlap ) {
                                $start_qty_overlap = true;

                                if ( ! in_array( $idx, $err_indexes, true ) ) {
                                    $err_indexes[] = $idx;
                                }
                            }

                            if ( $rule['end_qty'] <= $mapping[ $end_qty_meta_key ] && $rule['end_qty'] >= $mapping[ $start_qty_meta_key ] && false === $end_qty_overlap ) {
                                $end_qty_overlap = true;

                                if ( ! in_array( $idx, $err_indexes, true ) ) {
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
                    return array(
                        'status'          => 'fail',
                        'error_msg'       => __( 'Duplicate quantity discount rule', 'woocommerce-wholesale-prices-premium' ),
                        'additional_data' => array( 'dup_index' => $err_indexes ),
                    );
                } elseif ( $start_qty_overlap && $end_qty_overlap ) {
                    return array(
                        'status'          => 'fail',
                        'error_msg'       => __( 'Overlap quantity discount rule', 'woocommerce-wholesale-prices-premium' ),
                        'additional_data' => array( 'dup_index' => $err_indexes ),
                    );
                } else {
                    return true;
                }
            }
        }
    }

    /**
     * AJAX add wholesale role quantity based wholesale discount mapping entry.
     * This is the same code used for saving mapping on the per wholesale user level cart qty wholesale discount.
     *
     * @since 1.16.0
     * @access public
     */
    public function ajax_add_wholesale_role_qty_based_discount_mapping() {
        // Security checks.
        if ( ! check_ajax_referer( 'wwpp_add_wholesale_role_qty_based_discount_mapping', 'nonce', false ) ) {
            @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
            echo wp_json_encode(
                array(
                    'status'    => 'fail',
                    'error_msg' => __(
                        'Security check failed',
                        'woocommerce-wholesale-prices-premium'
                    ),
                )
            );
            wp_die();
        }

        if ( ! isset( $_POST['qty_based_discount_mapping'] ) ) {
            $response = array(
                'status'    => 'fail',
                'error_msg' => __( 'Required data not supplied', 'woocommerce-wholesale-prices-premium' ),
            );
        } else {
            $rule    = array_map( 'sanitize_text_field', $_POST['qty_based_discount_mapping'] );
            $user_id = sanitize_key( $_POST['user_id'] );

            $quantity_discount_rule_mapping = $user_id ? get_user_meta( $user_id, 'wwpp_wholesale_discount_qty_discount_mapping', true ) : get_option( WWPP_OPTION_WHOLESALE_ROLE_CART_QTY_BASED_DISCOUNT_MAPPING, array() );

            if ( ! is_array( $quantity_discount_rule_mapping ) ) {
                $quantity_discount_rule_mapping = array();
            }

            $response = $this->_validate_mapping_entry( $rule, $quantity_discount_rule_mapping, 'add' );

            if ( true === $response ) {
                $quantity_discount_rule_mapping[] = $rule;

                if ( $user_id ) {
                    update_user_meta( $user_id, 'wwpp_wholesale_discount_qty_discount_mapping', $quantity_discount_rule_mapping );
                } else {
                    update_option( WWPP_OPTION_WHOLESALE_ROLE_CART_QTY_BASED_DISCOUNT_MAPPING, $quantity_discount_rule_mapping, 'no' );
                }

                end( $quantity_discount_rule_mapping );
                $last_inserted_item_index = key( $quantity_discount_rule_mapping );

                $response = array(
                    'status'                   => 'success',
                    'last_inserted_item_index' => $last_inserted_item_index,
                );
            }
        }

        @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
        echo wp_json_encode( $response );
        wp_die();
    }

    /**
     * AJAX edit wholesale role quantity based discount mapping entry.
     * This is the same code used for saving mapping on the per wholesale user level cart qty wholesale discount.
     *
     * @since 1.16.0
     * @access public
     */
    public function ajax_edit_wholesale_role_qty_based_discount_mapping() {
        // Security checks.
        if ( ! check_ajax_referer( 'wwpp_edit_wholesale_role_qty_based_discount_mapping', 'nonce', false ) ) {
            @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
            echo wp_json_encode(
                array(
                    'status'    => 'fail',
                    'error_msg' => __(
                        'Security check failed',
                        'woocommerce-wholesale-prices-premium'
                    ),
                )
            );
            wp_die();
        }

        if ( ! isset( $_POST['qty_based_discount_mapping'] ) ) {
            $response = array(
                'status'    => 'fail',
                'error_msg' => __( 'Required data not supplied', 'woocommerce-wholesale-prices-premium' ),
            );
        } else {
            $rule    = array_map( 'sanitize_text_field', $_POST['qty_based_discount_mapping'] );
            $user_id = sanitize_key( $_POST['user_id'] );

            $quantity_discount_rule_mapping = $user_id ? get_user_meta( $user_id, 'wwpp_wholesale_discount_qty_discount_mapping', true ) : get_option( WWPP_OPTION_WHOLESALE_ROLE_CART_QTY_BASED_DISCOUNT_MAPPING, array() );

            if ( ! is_array( $quantity_discount_rule_mapping ) ) {
                $quantity_discount_rule_mapping = array();
            }

            $response = $this->_validate_mapping_entry( $rule, $quantity_discount_rule_mapping, 'edit' );

            if ( true === $response ) {
                $index = $rule['index'];
                unset( $rule['index'] );
                $quantity_discount_rule_mapping[ $index ] = $rule;

                if ( $user_id ) {
                    update_user_meta( $user_id, 'wwpp_wholesale_discount_qty_discount_mapping', $quantity_discount_rule_mapping );
                } else {
                    update_option( WWPP_OPTION_WHOLESALE_ROLE_CART_QTY_BASED_DISCOUNT_MAPPING, $quantity_discount_rule_mapping, 'no' );
                }

                $response = array( 'status' => 'success' );
            }
        }

        @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
        echo wp_json_encode( $response );
        wp_die();
    }

    /**
     * AJAX delete wholesale role quantity based discount mapping entry.
     * This is the same code used for saving mapping on the per wholesale user level cart qty wholesale discount.
     *
     * @since 1.16.0
     * @access public
     */
    public function ajax_delete_wholesale_role_qty_based_discount_mapping() {
        // Security checks.
        if ( ! check_ajax_referer( 'wwpp_delete_wholesale_role_qty_based_discount_mapping', 'nonce', false ) ) {
            @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
            echo wp_json_encode(
                array(
                    'status'    => 'fail',
                    'error_msg' => __(
                        'Security check failed',
                        'woocommerce-wholesale-prices-premium'
                    ),
                )
            );
            wp_die();
        }

        if ( ! isset( $_POST['index'] ) ) {
            $response = array(
                'status'    => 'fail',
                'error_msg' => __( 'Required data not supplied', 'woocommerce-wholesale-prices-premium' ),
            );
        } else {
            $user_id                        = sanitize_key( $_POST['user_id'] );
            $index                          = sanitize_key( $_POST['index'] );
            $quantity_discount_rule_mapping = $user_id ? get_user_meta( $user_id, 'wwpp_wholesale_discount_qty_discount_mapping', true ) : get_option( WWPP_OPTION_WHOLESALE_ROLE_CART_QTY_BASED_DISCOUNT_MAPPING, array() );

            if ( ! is_array( $quantity_discount_rule_mapping ) ) {
                $quantity_discount_rule_mapping = array();
            }

            if ( ! array_key_exists( $index, $quantity_discount_rule_mapping ) ) {
                $response = array(
                    'status'    => 'fail',
                    'error_msg' => __( 'The mapping you are trying to delete does not exist', 'woocommerce-wholesale-prices-premium' ),
                );
            } else {
                unset( $quantity_discount_rule_mapping[ $index ] );

                if ( $user_id ) {
                    update_user_meta( $user_id, 'wwpp_wholesale_discount_qty_discount_mapping', $quantity_discount_rule_mapping );
                } else {
                    update_option( WWPP_OPTION_WHOLESALE_ROLE_CART_QTY_BASED_DISCOUNT_MAPPING, $quantity_discount_rule_mapping, 'no' );
                }

                $response = array( 'status' => 'success' );
            }
        }

        @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
        echo wp_json_encode( $response );
        wp_die();
    }

    /**
     * Cart Total Price Based Discount
     */

    /**
     * AJAX add wholesale role cart total price based discount mapping entry.
     *
     * @since 1.26
     * @access public
     */
    public function ajax_add_wholesale_role_cart_subtotal_discount_mapping() {
        // Security checks.
        if ( ! check_ajax_referer( 'wwpp_add_wholesale_role_cart_subtotal_discount_mapping', 'nonce', false ) ) {
            @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
            echo wp_json_encode(
                array(
                    'status'    => 'fail',
                    'error_msg' => __(
                        'Security check failed',
                        'woocommerce-wholesale-prices-premium'
                    ),
                )
            );
            wp_die();
        }

        if ( ! isset( $_POST['cart_total_based_discount_mapping'] ) ) {
            $response = array(
                'status'    => 'fail',
                'error_msg' => __( 'Required data not supplied', 'woocommerce-wholesale-prices-premium' ),
            );
        } else {
            // Sanitize.
            $rule = array_map( 'sanitize_text_field', $_POST['cart_total_based_discount_mapping'] );

            if ( ! empty( $rule['min_total_price'] ) && $rule['min_total_price'] <= 0 ) {
                return array(
                    'status'    => 'fail',
                    'error_msg' => __( 'Total price must not be less than or equal to 0.', 'woocommerce-wholesale-prices-premium' ),
                );
            }

            if ( ! empty( $rule['discount_amount'] ) && $rule['discount_amount'] <= 0 ) {
                return array(
                    'status'    => 'fail',
                    'error_msg' => __( 'Discount amount must not be less than or equal to 0.', 'woocommerce-wholesale-prices-premium' ),
                );
            }

            $cart_total_price_discount_mapping   = get_option( WWPP_OPTION_WHOLESALE_ROLE_CART_SUBTOTAL_PRICE_BASED_DISCOUNT_MAPPING, array() );
            $cart_total_price_discount_mapping[] = $rule;
            update_option( WWPP_OPTION_WHOLESALE_ROLE_CART_SUBTOTAL_PRICE_BASED_DISCOUNT_MAPPING, $cart_total_price_discount_mapping, 'no' );
            end( $cart_total_price_discount_mapping );
            $last_inserted_item_index = key( $cart_total_price_discount_mapping );

            $response = array(
                'status'                   => 'success',
                'last_inserted_item_index' => $last_inserted_item_index,
            );
        }

        @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
        echo wp_json_encode( $response );
        wp_die();
    }

    /**
     *  AJAX edit wholesale role cart total price based discount mapping entry.
     *
     * @since 1.26
     * @access public
     */
    public function ajax_edit_wholesale_role_cart_subtotal_discount_mapping() {
        // Security checks.
        if ( ! check_ajax_referer( 'wwpp_edit_wholesale_role_cart_subtotal_discount_mapping', 'nonce', false ) ) {
            @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
            echo wp_json_encode(
                array(
                    'status'    => 'fail',
                    'error_msg' => __(
                        'Security check failed',
                        'woocommerce-wholesale-prices-premium'
                    ),
                )
            );
            wp_die();
        }

        if ( ! isset( $_POST['cart_total_based_discount_mapping'] ) ) {
            $response = array(
                'status'    => 'fail',
                'error_msg' => __( 'Required data not supplied', 'woocommerce-wholesale-prices-premium' ),
            );
        } else {
            // Sanitize.
            $rule = array_map( 'sanitize_text_field', $_POST['cart_total_based_discount_mapping'] );

            if ( ! empty( $rule['min_total_price'] ) && $rule['min_total_price'] <= 0 ) {
                return array(
                    'status'    => 'fail',
                    'error_msg' => __( 'Total price must not be less than or equal to 0.', 'woocommerce-wholesale-prices-premium' ),
                );
            }

            if ( ! empty( $rule['discount_amount'] ) && $rule['discount_amount'] <= 0 ) {
                return array(
                    'status'    => 'fail',
                    'error_msg' => __( 'Discount amount must not be less than or equal to 0.', 'woocommerce-wholesale-prices-premium' ),
                );
            }

            $quantity_discount_rule_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_CART_SUBTOTAL_PRICE_BASED_DISCOUNT_MAPPING, array() );
            $index                          = $rule['index'];
            unset( $rule['index'] );
            $quantity_discount_rule_mapping[ $index ] = $rule;
            update_option( WWPP_OPTION_WHOLESALE_ROLE_CART_SUBTOTAL_PRICE_BASED_DISCOUNT_MAPPING, $quantity_discount_rule_mapping, 'no' );
            $response = array( 'status' => 'success' );
        }

        @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
        echo wp_json_encode( $response );
        wp_die();
    }

    /**
     * AJAX delete wholesale role cart total price based discount mapping entry.
     *
     * @since 1.26
     * @access public
     */
    public function ajax_delete_wholesale_role_cart_subtotal_discount_mapping() {
        // Security checks.
        if ( ! check_ajax_referer( 'wwpp_delete_wholesale_role_cart_subtotal_discount_mapping', 'nonce', false ) ) {
            @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
            echo wp_json_encode(
                array(
                    'status'    => 'fail',
                    'error_msg' => __(
                        'Security check failed',
                        'woocommerce-wholesale-prices-premium'
                    ),
                )
            );
            wp_die();
        }

        if ( ! isset( $_POST['index'] ) ) {
            $response = array(
                'status'    => 'fail',
                'error_msg' => __( 'Required data not supplied', 'woocommerce-wholesale-prices-premium' ),
            );
        } else {
            $index = sanitize_key( $_POST['index'] );

            $quantity_discount_rule_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_CART_SUBTOTAL_PRICE_BASED_DISCOUNT_MAPPING, array() );

            if ( ! array_key_exists( $index, $quantity_discount_rule_mapping ) ) {
                $response = array(
                    'status'    => 'fail',
                    'error_msg' => __( 'The mapping you are trying to delete does not exist', 'woocommerce-wholesale-prices-premium' ),
                );
            } else {
                unset( $quantity_discount_rule_mapping[ $index ] );
                update_option( WWPP_OPTION_WHOLESALE_ROLE_CART_SUBTOTAL_PRICE_BASED_DISCOUNT_MAPPING, $quantity_discount_rule_mapping, 'no' );
                $response = array( 'status' => 'success' );
            }
        }

        @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
        echo wp_json_encode( $response );
        wp_die();
    }

    /**
     * Register model ajax handlers.
     *
     * @since 1.14.0
     * @access public
     */
    public function register_ajax_handler() {
        // Wholesale General Discount mapping.
        add_action( 'wp_ajax_wwppAddWholesaleRoleGeneralDiscountMapping', array( $this, 'add_wholesale_role_general_discount_mapping' ) );
        add_action( 'wp_ajax_wwppEditWholesaleRoleGeneralDiscountMapping', array( $this, 'edit_wholesale_role_general_discount_mapping' ) );
        add_action( 'wp_ajax_wwppDeleteWholesaleRoleGeneralDiscountMapping', array( $this, 'delete_wholesale_role_general_discount_mapping' ) );

        // Wholesale Role Cart Quantity Based Wholesale Pricing.
        add_action( 'wp_ajax_wwpp_add_wholesale_role_qty_based_discount_mapping', array( $this, 'ajax_add_wholesale_role_qty_based_discount_mapping' ) );
        add_action( 'wp_ajax_wwpp_edit_wholesale_role_qty_based_discount_mapping', array( $this, 'ajax_edit_wholesale_role_qty_based_discount_mapping' ) );
        add_action( 'wp_ajax_wwpp_delete_wholesale_role_qty_based_discount_mapping', array( $this, 'ajax_delete_wholesale_role_qty_based_discount_mapping' ) );

        // Wholesale Role Cart Total Based Discount.
        add_action( 'wp_ajax_wwpp_add_wholesale_role_cart_subtotal_discount_mapping', array( $this, 'ajax_add_wholesale_role_cart_subtotal_discount_mapping' ) );
        add_action( 'wp_ajax_wwpp_edit_wholesale_role_cart_subtotal_discount_mapping', array( $this, 'ajax_edit_wholesale_role_cart_subtotal_discount_mapping' ) );
        add_action( 'wp_ajax_wwpp_delete_wholesale_role_cart_subtotal_discount_mapping', array( $this, 'ajax_delete_wholesale_role_cart_subtotal_discount_mapping' ) );
    }

    /**
     * Execute model.
     *
     * @since 1.14.0
     * @access public
     */
    public function run() {
        add_action( 'init', array( $this, 'register_ajax_handler' ) );
    }
}
