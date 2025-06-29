<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWPP_Admin_Action_Settings' ) ) {
    /**
     * Model that houses the logic of WooCommerce Wholesale Prices Premium Settings page.
     *
     * @since 1.0.0
     */
    class WWPP_Admin_Action_Settings {

        /**
         * Property that holds the single main instance of WWPP_Dashboard.
         *
         * @since  2.0
         * @access private
         * @var WWPP_Dashboard
         */
        private static $_instance;

        /**
         * Wholesale price cache key.
         *
         * @since 1.27.8
         */
        const WHOLESALE_PRICE_CACHE_KEY = 'wwpp_product_wholesale_price_on_shop_v3_cache';

        /**
         * Model that houses the logic of retrieving information relating to wholesale role/s of a user.
         *
         * @since  2.0
         * @access private
         * @var WWP_Wholesale_Roles
         */
        private $_wwpp_wholesale_roles;

        /**
         * Property that holds all registered wholesale roles.
         *
         * @since 1.16.0
         * @access public
         * @var array
         */
        private $_all_wholesale_roles;

        /**
         * WWPP_Admin_Action_Settings constructor.
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Admin_Settings model.
         *
         * @since  2.0
         * @access public
         */
        public function __construct( $dependencies ) {

            $this->_wwpp_wholesale_roles = $dependencies['WWPP_Wholesale_Roles'];
            $this->_all_wholesale_roles  = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();
        }

        /**
         * Ensure that only one instance of WWPP_Admin_Settings is loaded or can be loaded (Singleton Pattern).
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Admin_Settings model.
         *
         * @since  2.0
         * @access public
         *
         * @return WWPP_Admin_Action_Settings
         */
        public static function instance( $dependencies ) {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Group tax exemption mapping save.
         *
         * @param array $options Options.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function group_tax_exp_mapping_save( $options ) {
            // Remove action.
            unset( $options['action'] );

            $tax_mapping        = array_map( 'sanitize_text_field', $options );
            $tax_option_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_TAX_OPTION_MAPPING, array() );

            if ( ! is_array( $tax_option_mapping ) ) {
                $tax_option_mapping = array();
            }

            $validate = $this->_validate_group_tax_exp_mapping( $tax_mapping );
            if ( ! empty( $validate ) ) {
                return $validate;
            }

            if ( array_key_exists( $tax_mapping['wholesale_role'], $tax_option_mapping ) ) {
                $response = array(
                    'status'  => 'error',
                    'message' => __( 'Duplicate Wholesale Role Tax Option Entry, Already Exist', 'woocommerce-wholesale-prices-premium' ),
                );
            } else {
                $wholesale_role = $tax_mapping['wholesale_role'];
                if ( '' !== trim( $wholesale_role ) ) {
                    unset( $tax_mapping['wholesale_role'] );
                    $tax_option_mapping[ $wholesale_role ] = $tax_mapping;
                    update_option( WWPP_OPTION_WHOLESALE_ROLE_TAX_OPTION_MAPPING, $tax_option_mapping, 'no' );

                    $response = array(
                        'status'  => 'success',
                        'message' => esc_html__( 'Tax exemption saved successfully.', 'woocommerce-wholesale-prices-premium' ),
                    );
                } else {
                    $response = array(
                        'status'  => 'error',
                        'message' => __( 'Wholesale Role is required!', 'woocommerce-wholesale-prices-premium' ),
                    );
                }
            }

            return $response;
        }

        /**
         * Group tax exemption mapping edit.
         *
         * @param array $options Options.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function group_tax_exp_mapping_edit( $options ) {
            // Remove action.
            unset( $options['action'] );

            $tax_mapping        = array_map( 'sanitize_text_field', $options );
            $tax_option_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_TAX_OPTION_MAPPING, array() );

            if ( ! is_array( $tax_option_mapping ) ) {
                $tax_option_mapping = array();
            }

            $validate = $this->_validate_group_tax_exp_mapping( $tax_mapping );
            if ( ! empty( $validate ) ) {
                return $validate;
            }

            if ( ! array_key_exists( $tax_mapping['wholesale_role'], $tax_option_mapping ) ) {
                $response = array(
                    'status'  => 'error',
                    'message' => __( 'Wholesale Role Tax Option Entry You Wish To Edit Does Not Exist', 'woocommerce-wholesale-prices-premium' ),
                );
            } else {
                $wholesale_role = $tax_mapping['wholesale_role'];
                unset( $tax_mapping['wholesale_role'] );
                $tax_option_mapping[ $wholesale_role ] = $tax_mapping;
                update_option( WWPP_OPTION_WHOLESALE_ROLE_TAX_OPTION_MAPPING, $tax_option_mapping, 'no' );

                $response = array(
                    'status'  => 'success',
                    'message' => esc_html__( 'Tax exemption updated successfully.', 'woocommerce-wholesale-prices-premium' ),
                );
            }

            return $response;
        }

        /**
         * Validate group tax mapping.
         *
         * @since 1.16.0
         * @access private
         *
         * @param array $mapping Mapping data.
         * @return array
         */
        private function _validate_group_tax_exp_mapping( $mapping ) {
            // Validate tax class mapping.
            $validate_msg = array();
            if ( empty( $mapping['wholesale_role'] ) ) {
                $validate_msg[] = __( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' );
            }

            $response = array();
            if ( ! empty( $validate_msg ) ) {
                $response = array(
                    'status'  => 'error',
                    /* translators: %s: required fields */
                    'message' => sprintf( _n( '%s is required', '%s are required', count( $validate_msg ), 'woocommerce-wholesale-prices-premium' ), implode( ' and ', $validate_msg ) ),
                );
            }

            return $response;
        }

        /**
         * Group tax exemption mapping delete.
         *
         * @param array $options Options.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function group_tax_exp_mapping_delete( $options ) {
            // Remove action.
            unset( $options['action'] );

            $wholesale_role     = sanitize_text_field( $options['wholesale_role'] );
            $tax_option_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_TAX_OPTION_MAPPING, array() );

            if ( ! is_array( $tax_option_mapping ) ) {
                $tax_option_mapping = array();
            }

            if ( ! array_key_exists( $wholesale_role, $tax_option_mapping ) ) {
                $response = array(
                    'status'  => 'error',
                    'message' => __( 'Wholesale Role Tax Option Entry You Wish To Delete Does Not Exist', 'woocommerce-wholesale-prices-premium' ),
                );
            } else {
                unset( $tax_option_mapping[ $wholesale_role ] );
                update_option( WWPP_OPTION_WHOLESALE_ROLE_TAX_OPTION_MAPPING, $tax_option_mapping, 'no' );

                $response = array(
                    'status'  => 'success',
                    'message' => esc_html__( 'Tax exemption deleted successfully.', 'woocommerce-wholesale-prices-premium' ),
                );
            }

            return $response;
        }

        /**
         * Group tax class mapping save.
         *
         * @param array $options Options.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function group_tax_cls_mapping_save( $options ) {
            // Remove action.
            unset( $options['action'] );

            $tax_mapping        = array_map( 'sanitize_text_field', $options );
            $tax_option_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_TAX_CLASS_OPTIONS_MAPPING );

            if ( ! is_array( $tax_option_mapping ) ) {
                $tax_option_mapping = array();
            }

            $validate = $this->_validate_group_tax_cls_mapping( $tax_mapping );
            if ( ! empty( $validate ) ) {
                return $validate;
            }

            if ( array_key_exists( $tax_mapping['wholesale-role-key'], $tax_option_mapping ) ) {
                $response = array(
                    'status'  => 'error',
                    'message' => __( 'Wholesale role mapping entry already exist', 'woocommerce-wholesale-prices-premium' ),
                );
            } else {
                $wholesale_role = $tax_mapping['wholesale-role-key'];
                if ( '' !== trim( $wholesale_role ) ) {
                    unset( $tax_mapping['wholesale-role-key'] );
                    $tax_option_mapping[ $wholesale_role ] = $tax_mapping;
                    update_option( WWPP_OPTION_WHOLESALE_ROLE_TAX_CLASS_OPTIONS_MAPPING, $tax_option_mapping, 'no' );

                    $response = array(
                        'status'  => 'success',
                        'message' => esc_html__( 'Tax class saved successfully.', 'woocommerce-wholesale-prices-premium' ),
                    );
                } else {
                    $response = array(
                        'status'  => 'error',
                        'message' => __( 'Wholesale Role is required!', 'woocommerce-wholesale-prices-premium' ),
                    );
                }
            }

            return $response;
        }

        /**
         * Group tax class mapping edit.
         *
         * @param array $options Options.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function group_tax_cls_mapping_edit( $options ) {
            // Remove action.
            unset( $options['action'] );

            $tax_mapping        = array_map( 'sanitize_text_field', $options );
            $tax_option_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_TAX_CLASS_OPTIONS_MAPPING );

            if ( ! is_array( $tax_option_mapping ) ) {
                $tax_option_mapping = array();
            }

            $validate = $this->_validate_group_tax_cls_mapping( $tax_mapping );
            if ( ! empty( $validate ) ) {
                return $validate;
            }

            if ( ! array_key_exists( $tax_mapping['wholesale-role-key'], $tax_option_mapping ) ) {
                $response = array(
                    'status'  => 'error',
                    'message' => __( 'Wholesale role mapping entry you are trying to edit does not exist', 'woocommerce-wholesale-prices-premium' ),
                );
            } else {
                $wholesale_role = $tax_mapping['wholesale-role-key'];
                unset( $tax_mapping['wholesale-role-key'] );
                $tax_option_mapping[ $wholesale_role ] = $tax_mapping;
                update_option( WWPP_OPTION_WHOLESALE_ROLE_TAX_CLASS_OPTIONS_MAPPING, $tax_option_mapping, 'no' );

                $response = array(
                    'status'  => 'success',
                    'message' => esc_html__( 'Tax class updated successfully.', 'woocommerce-wholesale-prices-premium' ),
                );
            }

            return $response;
        }

        /**
         * Validate group tax class mapping.
         *
         * @since 1.16.0
         * @access private
         *
         * @param array $mapping Mapping data.
         * @return array
         */
        private function _validate_group_tax_cls_mapping( $mapping ) {
            // Validate tax class mapping.
            $validate_msg = array();
            if ( empty( $mapping['wholesale-role-key'] ) ) {
                $validate_msg[] = __( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' );
            }

            if ( empty( $mapping['tax-class'] ) ) {
                $validate_msg[] = __( 'Tax Class', 'woocommerce-wholesale-prices-premium' );
            }

            $response = array();
            if ( ! empty( $validate_msg ) ) {
                $response = array(
                    'status'  => 'error',
                    /* translators: %s: required fields */
                    'message' => sprintf( _n( '%s is required', '%s are required', count( $validate_msg ), 'woocommerce-wholesale-prices-premium' ), implode( ' and ', $validate_msg ) ),
                );
            }

            return $response;
        }

        /**
         * Group tax class mapping delete.
         *
         * @param array $options Options.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function group_tax_cls_mapping_delete( $options ) {
            // Remove action.
            unset( $options['action'] );

            $wholesale_role     = sanitize_text_field( $options['wholesale-role-key'] );
            $tax_option_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_TAX_CLASS_OPTIONS_MAPPING, array() );

            if ( ! is_array( $tax_option_mapping ) ) {
                $tax_option_mapping = array();
            }

            if ( ! array_key_exists( $wholesale_role, $tax_option_mapping ) ) {
                $response = array(
                    'status'  => 'error',
                    'message' => __( 'Mapping entry you are trying to delete does not exist', 'woocommerce-wholesale-prices-premium' ),
                );
            } else {
                unset( $tax_option_mapping[ $wholesale_role ] );
                update_option( WWPP_OPTION_WHOLESALE_ROLE_TAX_CLASS_OPTIONS_MAPPING, $tax_option_mapping, 'no' );

                $response = array(
                    'status'  => 'success',
                    'message' => esc_html__( 'Tax exemption deleted successfully.', 'woocommerce-wholesale-prices-premium' ),
                );
            }

            return $response;
        }

        /**
         * Group shipping mapping save.
         *
         * @param array $options Options.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function group_shipping_nonzoned_mapping_save( $options ) {
            // Remove action.
            unset( $options['action'] );

            $mapping                = WWPP_Helper_Functions::sanitize_array( $options );
            $wholesale_zone_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_SHIPPING_ZONE_METHOD_MAPPING, array() );

            // Clear proxy data for non zoned.
            if ( 'yes' === $mapping['use_non_zoned_shipping_method'] ) {
                unset( $mapping['shipping_method'] );
                unset( $mapping['shipping_method_text'] );
            } else {
                unset( $mapping['non_zoned_shipping_method'] );
                unset( $mapping['non_zoned_shipping_method_text'] );
            }

            if ( $this->_check_if_mapping_exists( $mapping, $wholesale_zone_mapping ) !== false ) {
                $response = array(
                    'status'  => 'error',
                    'message' => __( 'The mapping you wish to add already exists', 'woocommerce-wholesale-prices-premium' ),
                );
            } else {
                // Validate shipping zone and method.
                $validate_msg = array();
                if ( empty( $mapping['wholesale_role'] ) ) {
                    $validate_msg[] = __( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' );
                }
                if ( 'yes' === $mapping['use_non_zoned_shipping_method'] ) {
                    if ( empty( $mapping['non_zoned_shipping_method'] ) ) {
                        $validate_msg[] = __( 'Non-zoned Shipping Methods', 'woocommerce-wholesale-prices-premium' );
                    }
                } elseif ( 'no' === $mapping['use_non_zoned_shipping_method'] ) {
                    if ( '' === trim( $mapping['shipping_zone'] ) ) {
                        $validate_msg[] = __( 'Shipping Zones', 'woocommerce-wholesale-prices-premium' );
                    }
                    if ( '' === trim( $mapping['shipping_method'] ) ) {
                        $validate_msg[] = __( 'Shipping Zone Methods', 'woocommerce-wholesale-prices-premium' );
                    }
                }

                if ( ! empty( $validate_msg ) ) {
                    $response = array(
                        'status'  => 'error',
                        /* translators: %s: required fields */
                        'message' => sprintf( _n( '%s is required', '%s are required', count( $validate_msg ), 'woocommerce-wholesale-prices-premium' ), implode( ', ', $validate_msg ) ),
                    );
                } else {
                    // Clean $mapping of unnecessary data.
                    unset( $mapping['wholesale_role_text'] );

                    if ( 'yes' === $mapping['use_non_zoned_shipping_method'] ) {
                        unset( $mapping['non_zoned_shipping_method_text'] );
                    } else {
                        unset( $mapping['shipping_zone_text'] );
                        unset( $mapping['shipping_method_text'] );
                    }

                    $wholesale_zone_mapping[] = $mapping;
                    update_option( WWPP_OPTION_WHOLESALE_ROLE_SHIPPING_ZONE_METHOD_MAPPING, $wholesale_zone_mapping, 'no' );
                    end( $wholesale_zone_mapping );
                    $mapping_index = key( $wholesale_zone_mapping );

                    $response = array(
                        'status'  => 'success',
                        'message' => esc_html__( 'Shipping Non-zoned saved successfully.', 'woocommerce-wholesale-prices-premium' ),
                    );
                }
            }

            return $response;
        }

        /**
         * Group shipping mapping edit.
         *
         * @param array $options Options.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function group_shipping_nonzoned_mapping_edit( $options ) {
            // Remove action.
            unset( $options['action'] );

            $index                  = sanitize_key( $options['key'] );
            $mapping                = WWPP_Helper_Functions::sanitize_array( $options );
            $wholesale_zone_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_SHIPPING_ZONE_METHOD_MAPPING, array() );

            // Clear proxy data for non zoned.
            if ( 'yes' === $mapping['use_non_zoned_shipping_method'] ) {
                unset( $mapping['shipping_method'] );
                unset( $mapping['shipping_method_text'] );
                unset( $mapping['shipping_zone'] );
                unset( $mapping['shipping_zone_text'] );
            } else {
                unset( $mapping['non_zoned_shipping_method'] );
                unset( $mapping['non_zoned_shipping_method_text'] );
            }

            // Validate shipping zone and method.
            $validate_msg = array();
            if ( empty( $mapping['wholesale_role'] ) ) {
                $validate_msg[] = __( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' );
            }
            if ( 'yes' === $mapping['use_non_zoned_shipping_method'] ) {
                if ( empty( $mapping['non_zoned_shipping_method'] ) ) {
                    $validate_msg[] = __( 'Non-zoned Shipping Methods', 'woocommerce-wholesale-prices-premium' );
                }
            } elseif ( 'no' === $mapping['use_non_zoned_shipping_method'] ) {
                if ( empty( $mapping['shipping_zone'] ) ) {
                    $validate_msg[] = __( 'Shipping Zones', 'woocommerce-wholesale-prices-premium' );
                }
                if ( empty( $mapping['shipping_method'] ) ) {
                    $validate_msg[] = __( 'Shipping Zone Methods', 'woocommerce-wholesale-prices-premium' );
                }
            }

            if ( ! empty( $validate_msg ) ) {
                $response = array(
                    'status'  => 'error',
                    /* translators: %s: required fields */
                    'message' => sprintf( _n( '%s is required', '%s are required', count( $validate_msg ), 'woocommerce-wholesale-prices-premium' ), implode( ', ', $validate_msg ) ),
                );
            } else {
                $mapping_exists_check = $this->_check_if_mapping_exists( $mapping, $wholesale_zone_mapping );

                if ( ! array_key_exists( $index, $wholesale_zone_mapping ) ) {
                    $response = array(
                        'status'  => 'error',
                        'message' => __( 'The mapping you wish to edit does not exists', 'woocommerce-wholesale-prices-premium' ),
                    );
                } elseif ( false !== $mapping_exists_check && $mapping_exists_check !== $index ) {
                    $response = array(
                        'status'  => 'error',
                        'message' => __( 'The new mapping data you want to save duplicates with another existing mapping', 'woocommerce-wholesale-prices-premium' ),
                    );
                } else {
                    // Clean $mapping of unnecessary data.
                    unset( $mapping['wholesale_role_text'] );

                    if ( 'yes' === $mapping['use_non_zoned_shipping_method'] ) {
                        unset( $mapping['non_zoned_shipping_method_text'] );
                    } else {
                        unset( $mapping['shipping_zone_text'] );
                        unset( $mapping['shipping_method_text'] );
                    }

                    $wholesale_zone_mapping[ $index ] = $mapping;
                    update_option( WWPP_OPTION_WHOLESALE_ROLE_SHIPPING_ZONE_METHOD_MAPPING, $wholesale_zone_mapping, 'no' );
                    $response = array(
                        'status'  => 'success',
                        'message' => esc_html__( 'Shipping Non-zoned updated successfully.', 'woocommerce-wholesale-prices-premium' ),
                    );
                }
            }

            return $response;
        }

        /**
         * Group shipping mapping delete.
         *
         * @param array $options Options.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function group_shipping_nonzoned_mapping_delete( $options ) {
            // Remove action.
            unset( $options['action'] );

            $index                  = sanitize_key( $options['key'] );
            $wholesale_zone_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_SHIPPING_ZONE_METHOD_MAPPING, array() );

            if ( ! array_key_exists( $index, $wholesale_zone_mapping ) ) {
                $response = array(
                    'status'  => 'error',
                    'message' => __( 'The mapping you wish to delete does not exists', 'woocommerce-wholesale-prices-premium' ),
                );
            } else {
                unset( $wholesale_zone_mapping[ $index ] );
                update_option( WWPP_OPTION_WHOLESALE_ROLE_SHIPPING_ZONE_METHOD_MAPPING, $wholesale_zone_mapping, 'no' );

                $response = array(
                    'status'  => 'success',
                    'message' => esc_html__( 'Shipping Non-zoned deleted successfully.', 'woocommerce-wholesale-prices-premium' ),
                );
            }

            return $response;
        }

        /**
         * Check if a mapping already existed on wholesale zone mapping.
         *
         * @since 1.9.1
         * @since 1.14.0 Refactor codebase and move to its proper model.
         * @access public
         *
         * @param array $mapping           Wholesale shipping mapping entry data.
         * @param array $wholesale_mapping Wholesale shipping mapping data.
         * @return bool True if exists, false otherwise.
         */
        private function _check_if_mapping_exists( $mapping, $wholesale_mapping ) {
            if ( 'yes' === $mapping['use_non_zoned_shipping_method'] ) {
                foreach ( $wholesale_mapping as $index => $wm ) {
                    if ( ! isset( $wm['wholesale_role'] ) || ! isset( $wm['non_zoned_shipping_method'] ) ||
                        ! isset( $mapping['wholesale_role'] ) || ! isset( $mapping['non_zoned_shipping_method'] ) ) {
                        continue;
                    }

                    if ( $mapping['wholesale_role'] === $wm['wholesale_role'] &&
                        $mapping['non_zoned_shipping_method'] === $wm['non_zoned_shipping_method'] ) {
                        return $index;
                    }
                }
            } else {
                foreach ( $wholesale_mapping as $index => $wm ) {
                    if ( ! isset( $wm['wholesale_role'] ) || ! isset( $wm['shipping_zone'] ) || ! isset( $wm['shipping_method'] ) ||
                        ! isset( $mapping['wholesale_role'] ) || ! isset( $mapping['shipping_zone'] ) || ! isset( $mapping['shipping_method'] ) ) {
                        continue;
                    }

                    if ( $mapping['wholesale_role'] === $wm['wholesale_role'] &&
                        $mapping['shipping_zone'] === $wm['shipping_zone'] &&
                        $mapping['shipping_method'] === $wm['shipping_method'] ) {
                        return $index;
                    }
                }
            }

            return false;
        }

        /**
         * Group general discount mapping save.
         *
         * @param array $options Options.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function group_general_discount_mapping_save( $options ) {
            // Remove action.
            unset( $options['action'] );

            $discount_mapping = array_map( 'sanitize_text_field', $options );

            $saved_discount_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_GENERAL_DISCOUNT_MAPPING );
            if ( ! is_array( $saved_discount_mapping ) ) {
                $saved_discount_mapping = array();
            }

            if ( ! array_key_exists( $discount_mapping['wholesale_role'], $saved_discount_mapping ) ) {
                // Validate discounts.
                $validate_msg = array();
                if ( empty( $discount_mapping['wholesale_role'] ) ) {
                    $validate_msg[] = __( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' );
                }
                if ( empty( $discount_mapping['general_discount'] ) ) {
                    $validate_msg[] = __( 'Percent Discount', 'woocommerce-wholesale-prices-premium' );
                }

                if ( ! empty( $validate_msg ) ) {
                    $response = array(
                        'status'  => 'error',
                        /* translators: %s: required fields */
                        'message' => sprintf( _n( '%s is required', '%s are required', count( $validate_msg ), 'woocommerce-wholesale-prices-premium' ), implode( ' and ', $validate_msg ) ),
                    );
                } else {
                    $wwpp_product_cache_option = get_option( 'wwpp_enable_product_cache' );

                    if ( 'yes' === $wwpp_product_cache_option ) {
                        global $wc_wholesale_prices_premium;
                        $wc_wholesale_prices_premium->wwpp_cache->clear_product_transients_cache();
                    }

                    $saved_discount_mapping[ $discount_mapping['wholesale_role'] ] = $discount_mapping['general_discount'];
                    update_option( WWPP_OPTION_WHOLESALE_ROLE_GENERAL_DISCOUNT_MAPPING, $saved_discount_mapping, 'no' );

                    $response = array(
                        'status'  => 'success',
                        'message' => esc_html__( 'Discount saved successfully.', 'woocommerce-wholesale-prices-premium' ),
                    );

                    do_action( 'wwpp_add_wholesale_role_general_discount_mapping' );
                }
            } else {
                $response = array(
                    'status'  => 'error',
                    'message' => __( 'Duplicate Entry, Entry Already Exists', 'woocommerce-wholesale-prices-premium' ),
                );
            }

            return $response;
        }

        /**
         * Group general discount mapping edit.
         *
         * @param array $options Options.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function group_general_discount_mapping_edit( $options ) {
            // Remove action.
            unset( $options['action'] );

            $discount_mapping = array_map( 'sanitize_text_field', $options );

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

                $response = array(
                    'status'  => 'success',
                    'message' => esc_html__( 'Discount updated successfully.', 'woocommerce-wholesale-prices-premium' ),
                );

                do_action( 'wwpp_edit_wholesale_role_general_discount_mapping' );

            } else {
                $response = array(
                    'status'  => 'error',
                    'message' => __( 'Entry to be edited does not exist', 'woocommerce-wholesale-prices-premium' ),
                );
            }

            return $response;
        }

        /**
         * Group general discount mapping delete.
         *
         * @param array $options Options.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function group_general_discount_mapping_delete( $options ) {
            // Remove action.
            unset( $options['action'] );

            $wholesale_role = sanitize_text_field( $options['wholesale_role'] );

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

                $response = array(
                    'status'  => 'success',
                    'message' => esc_html__( 'Discount deleted successfully.', 'woocommerce-wholesale-prices-premium' ),
                );

                do_action( 'wwpp_delete_wholesale_role_general_discount_mapping' );

            } else {
                $response = array(
                    'status'  => 'error',
                    'message' => __( 'Entry to be deleted does not exist', 'woocommerce-wholesale-prices-premium' ),
                );
            }

            return $response;
        }

        /**
         * Group general discount mapping save.
         *
         * @param array $options Options.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function group_general_quantity_mapping_save( $options ) {
            // Remove action.
            unset( $options['action'] );

            $rule    = array_map( 'sanitize_text_field', $options );
            $user_id = isset( $options['user_id'] ) ? sanitize_key( $options['user_id'] ) : 0;

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
                    'status'  => 'success',
                    'message' => esc_html__( 'Discount added successfully.', 'woocommerce-wholesale-prices-premium' ),
                );
            }

            return $response;
        }

        /**
         * Group general discount mapping edit.
         *
         * @param array $options Options.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function group_general_quantity_mapping_edit( $options ) {
            // Remove action.
            unset( $options['action'] );

            $rule    = array_map( 'sanitize_text_field', $options );
            $user_id = isset( $options['user_id'] ) ? sanitize_key( $options['user_id'] ) : 0;

            $quantity_discount_rule_mapping = $user_id ? get_user_meta( $user_id, 'wwpp_wholesale_discount_qty_discount_mapping', true ) : get_option( WWPP_OPTION_WHOLESALE_ROLE_CART_QTY_BASED_DISCOUNT_MAPPING, array() );

            if ( ! is_array( $quantity_discount_rule_mapping ) ) {
                $quantity_discount_rule_mapping = array();
            }

            $response = $this->_validate_mapping_entry( $rule, $quantity_discount_rule_mapping, 'edit' );

            if ( true === $response ) {
                $index = $rule['key'];
                unset( $rule['key'] );
                $quantity_discount_rule_mapping[ $index ] = $rule;

                if ( $user_id ) {
                    update_user_meta( $user_id, 'wwpp_wholesale_discount_qty_discount_mapping', $quantity_discount_rule_mapping );
                } else {
                    update_option( WWPP_OPTION_WHOLESALE_ROLE_CART_QTY_BASED_DISCOUNT_MAPPING, $quantity_discount_rule_mapping, 'no' );
                }

                $response = array(
                    'status'  => 'success',
                    'message' => esc_html__( 'Discount updated successfully.', 'woocommerce-wholesale-prices-premium' ),
                );
            }

            return $response;
        }

        /**
         * Group general discount mapping delete.
         *
         * @param array $options Options.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function group_general_quantity_mapping_delete( $options ) {
            // Remove action.
            unset( $options['action'] );

            $index                          = sanitize_key( $options['key'] );
            $user_id                        = isset( $options['user_id'] ) ? sanitize_key( $options['user_id'] ) : 0;
            $quantity_discount_rule_mapping = $user_id ? get_user_meta( $user_id, 'wwpp_wholesale_discount_qty_discount_mapping', true ) : get_option( WWPP_OPTION_WHOLESALE_ROLE_CART_QTY_BASED_DISCOUNT_MAPPING, array() );

            if ( ! is_array( $quantity_discount_rule_mapping ) ) {
                $quantity_discount_rule_mapping = array();
            }

            if ( ! array_key_exists( $index, $quantity_discount_rule_mapping ) ) {
                $response = array(
                    'status'  => 'error',
                    'message' => __( 'The mapping you are trying to delete does not exist', 'woocommerce-wholesale-prices-premium' ),
                );
            } else {
                unset( $quantity_discount_rule_mapping[ $index ] );

                if ( $user_id ) {
                    update_user_meta( $user_id, 'wwpp_wholesale_discount_qty_discount_mapping', $quantity_discount_rule_mapping );
                } else {
                    update_option( WWPP_OPTION_WHOLESALE_ROLE_CART_QTY_BASED_DISCOUNT_MAPPING, $quantity_discount_rule_mapping, 'no' );
                }

                $response = array(
                    'status'  => 'success',
                    'message' => esc_html__( 'Discount deleted successfully.', 'woocommerce-wholesale-prices-premium' ),
                );
            }

            return $response;
        }

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

            // Check required.
            $validate_msg = array();
            if ( empty( $rule['wholesale_role'] ) ) {
                $validate_msg[] = __( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' );
            }
            if ( empty( $rule['start_qty'] ) ) {
                $validate_msg[] = __( 'Starting Qty', 'woocommerce-wholesale-prices-premium' );
            }
            if ( empty( $rule['percent_discount'] ) ) {
                $validate_msg[] = __( 'Percent Discount', 'woocommerce-wholesale-prices-premium' );
            }

            if ( ! empty( $validate_msg ) ) {
                return array(
                    'status'  => 'error',
                    /* translators: %s: required fields */
                    'message' => sprintf( _n( '%s is required', '%s are required', count( $validate_msg ), 'woocommerce-wholesale-prices-premium' ), implode( ', ', $validate_msg ) ),
                );
            }

            // Check data format.
            if ( ! is_array( $rule ) || ! isset( $rule['wholesale_role'], $rule['start_qty'], $rule['percent_discount'] ) ) {
                return array(
                    'status'  => 'error',
                    'message' => __( 'Quantity discount rule data passed is in invalid format.', 'woocommerce-wholesale-prices-premium' ),
                );
            } elseif ( 'edit' === $mode && ! isset( $rule['key'] ) ) {
                return array(
                    'status'  => 'error',
                    'message' => __( 'Quantity discount rule data passed is in invalid format.', 'woocommerce-wholesale-prices-premium' ),
                );
            } else {
                // Check data validity.
                if ( 'edit' === $mode ) {
                    $rule['key'] = sanitize_text_field( $rule['key'] );
                }

                $rule['wholesale_role']   = sanitize_text_field( $rule['wholesale_role'] );
                $rule['start_qty']        = sanitize_text_field( $rule['start_qty'] );
                $rule['end_qty']          = isset( $rule['end_qty'] ) ? sanitize_text_field( $rule['end_qty'] ) : '';
                $rule['percent_discount'] = sanitize_text_field( $rule['percent_discount'] );

                if ( 'edit' === $mode && '' === $rule['key'] ) {
                    return array(
                        'status'  => 'error',
                        'message' => __( 'Quantity discount rule data passed is invalid. Index of the mapping to edit not passed', 'woocommerce-wholesale-prices-premium' ),
                    );
                } elseif ( empty( $rule['wholesale_role'] ) || empty( $rule['start_qty'] ) || empty( $rule['percent_discount'] ) ) {
                    return array(
                        'status'  => 'error',
                        'message' => __( 'Quantity discount rule data passed is invalid. The following fields are required ( Wholesale Role / Starting Qty / Percent Discount ).', 'woocommerce-wholesale-prices-premium' ),
                    );
                } elseif ( ! is_numeric( $rule['start_qty'] ) || ! is_numeric( $rule['percent_discount'] ) || ( ! empty( $rule['end_qty'] ) && ! is_numeric( $rule['end_qty'] ) ) ) {
                    return array(
                        'status'  => 'error',
                        'message' => __( 'Quantity discount rule data passed is invalid. The following fields must be a number ( Starting Qty / Ending Qty / Wholesale Price ).', 'woocommerce-wholesale-prices-premium' ),
                    );
                } elseif ( ! empty( $rule['end_qty'] ) && $rule['end_qty'] < $rule['start_qty'] ) {
                    return array(
                        'status'  => 'error',
                        'message' => __( 'Ending Qty must not be less than Starting Qty', 'woocommerce-wholesale-prices-premium' ),
                    );
                } else {
                    if ( 'edit' === $mode && ! array_key_exists( $rule['key'], $quantity_discount_rule_mapping ) ) {
                        return array(
                            'status'  => 'error',
                            'message' => __( 'Quantity discount rule entry you want to edit does not exist', 'woocommerce-wholesale-prices-premium' ),
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
                            if ( $mapping[ $start_qty_meta_key ] === $rule['start_qty'] && ! $dup && ( 'edit' !== $mode || ( 'edit' === $mode && $rule['key'] != $idx ) ) ) { // phpcs:ignore
                                $dup = true;

                                if ( ! in_array( $idx, $err_indexes, true ) ) {
                                    $err_indexes[] = $idx;
                                }
                            }

                            // Check for overlapping mappings. Only do this if no dup yet.
                            if ( ! $dup && ( 'edit' !== $mode || ( 'edit' === $mode && $rule['key'] !== $idx ) ) ) {

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
                            'status'          => 'error',
                            'message'         => __( 'Duplicate quantity discount rule', 'woocommerce-wholesale-prices-premium' ),
                            'additional_data' => array( 'dup_index' => $err_indexes ),
                        );
                    } elseif ( $start_qty_overlap && $end_qty_overlap ) {
                        return array(
                            'status'          => 'error',
                            'message'         => __( 'Overlap quantity discount rule', 'woocommerce-wholesale-prices-premium' ),
                            'additional_data' => array( 'dup_index' => $err_indexes ),
                        );
                    } else {
                        return true;
                    }
                }
            }
        }

        /**
         * Group general discount mapping save.
         *
         * @param array $options Options.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function group_general_cart_price_discount_mapping_save( $options ) {
            // Remove action.
            unset( $options['action'] );

            // Sanitize.
            $rule = array_map( 'sanitize_text_field', $options );

            $validate = $this->_validate_group_general_cart_price_discount_mapping( $rule );
            if ( ! empty( $validate ) ) {
                return $validate;
            }

            if ( ! empty( $rule['min_total_price'] ) && $rule['min_total_price'] <= 0 ) {
                return array(
                    'status'  => 'error',
                    'message' => __( 'Total price must not be less than or equal to 0.', 'woocommerce-wholesale-prices-premium' ),
                );
            }

            if ( ! empty( $rule['discount_amount'] ) && $rule['discount_amount'] <= 0 ) {
                return array(
                    'status'  => 'error',
                    'message' => __( 'Discount amount must not be less than or equal to 0.', 'woocommerce-wholesale-prices-premium' ),
                );
            }

            $cart_total_price_discount_mapping   = get_option( WWPP_OPTION_WHOLESALE_ROLE_CART_SUBTOTAL_PRICE_BASED_DISCOUNT_MAPPING, array() );
            $cart_total_price_discount_mapping[] = $rule;
            update_option( WWPP_OPTION_WHOLESALE_ROLE_CART_SUBTOTAL_PRICE_BASED_DISCOUNT_MAPPING, $cart_total_price_discount_mapping, 'no' );
            end( $cart_total_price_discount_mapping );
            $last_inserted_item_index = key( $cart_total_price_discount_mapping );

            $response = array(
                'status'  => 'success',
                'message' => __( 'Discount added successfully.', 'woocommerce-wholesale-prices-premium' ),
            );

            return $response;
        }

        /**
         * Group general discount mapping edit.
         *
         * @param array $options Options.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function group_general_cart_price_discount_mapping_edit( $options ) {
            // Remove action.
            unset( $options['action'] );

            // Sanitize.
            $rule = array_map( 'sanitize_text_field', $options );

            $validate = $this->_validate_group_general_cart_price_discount_mapping( $rule );
            if ( ! empty( $validate ) ) {
                return $validate;
            }

            if ( ! empty( $rule['min_total_price'] ) && $rule['min_total_price'] <= 0 ) {
                return array(
                    'status'  => 'error',
                    'message' => __( 'Total price must not be less than or equal to 0.', 'woocommerce-wholesale-prices-premium' ),
                );
            }

            if ( ! empty( $rule['discount_amount'] ) && $rule['discount_amount'] <= 0 ) {
                return array(
                    'status'  => 'error',
                    'message' => __( 'Discount amount must not be less than or equal to 0.', 'woocommerce-wholesale-prices-premium' ),
                );
            }

            $quantity_discount_rule_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_CART_SUBTOTAL_PRICE_BASED_DISCOUNT_MAPPING, array() );
            $index                          = $rule['key'];
            unset( $rule['key'] );
            $quantity_discount_rule_mapping[ $index ] = $rule;
            update_option( WWPP_OPTION_WHOLESALE_ROLE_CART_SUBTOTAL_PRICE_BASED_DISCOUNT_MAPPING, $quantity_discount_rule_mapping, 'no' );

            $response = array(
                'status'  => 'success',
                'message' => __( 'Discount updated successfully.', 'woocommerce-wholesale-prices-premium' ),
            );

            return $response;
        }

        /**
         * Validate group general cart price discount mapping entry.
         *
         * @param array $rule Rule.
         *
         * @since 2.0
         * @access private
         *
         * @return array
         */
        private function _validate_group_general_cart_price_discount_mapping( $rule ) {
            // Validate discounts.
            $validate_msg = array();
            if ( empty( $rule['wholesale_role'] ) ) {
                $validate_msg[] = __( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' );
            }
            if ( empty( $rule['subtotal_price'] ) ) {
                $validate_msg[] = __( 'Subtotal Price', 'woocommerce-wholesale-prices-premium' );
            }
            if ( empty( $rule['discount_type'] ) ) {
                $validate_msg[] = __( 'Discount Type', 'woocommerce-wholesale-prices-premium' );
            }
            if ( empty( $rule['discount_amount'] ) ) {
                $validate_msg[] = __( 'Discount Amount', 'woocommerce-wholesale-prices-premium' );
            }
            if ( empty( $rule['discount_title'] ) ) {
                $validate_msg[] = __( 'Discount Title', 'woocommerce-wholesale-prices-premium' );
            }

            $response = array();
            if ( ! empty( $validate_msg ) ) {
                $response = array(
                    'status'  => 'error',
                    /* translators: %s: required fields */
                    'message' => sprintf( _n( '%s is required', '%s are required', count( $validate_msg ), 'woocommerce-wholesale-prices-premium' ), implode( ', ', $validate_msg ) ),
                );
            }

            return $response;
        }

        /**
         * Group general discount mapping delete.
         *
         * @param array $options Options.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function group_general_cart_price_discount_mapping_delete( $options ) {
            // Remove action.
            unset( $options['action'] );

            $index                          = sanitize_key( $options['key'] );
            $quantity_discount_rule_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_CART_SUBTOTAL_PRICE_BASED_DISCOUNT_MAPPING, array() );

            if ( ! array_key_exists( $index, $quantity_discount_rule_mapping ) ) {
                $response = array(
                    'status'  => 'error',
                    'message' => __( 'The mapping you are trying to delete does not exist', 'woocommerce-wholesale-prices-premium' ),
                );
            } else {
                unset( $quantity_discount_rule_mapping[ $index ] );
                update_option( WWPP_OPTION_WHOLESALE_ROLE_CART_SUBTOTAL_PRICE_BASED_DISCOUNT_MAPPING, $quantity_discount_rule_mapping, 'no' );

                $response = array(
                    'status'  => 'success',
                    'message' => __( 'Discount deleted successfully.', 'woocommerce-wholesale-prices-premium' ),
                );
            }

            return $response;
        }

        /**
         * Group general discount mapping save.
         *
         * @param array $options Options.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function group_payment_gateway_mapping_save( $options ) {
            // Remove action.
            unset( $options['action'] );

            $mapping      = WWPP_Helper_Functions::sanitize_array( $options );
            $wrpg_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_PAYMENT_GATEWAY_MAPPING );

            if ( ! is_array( $wrpg_mapping ) ) {
                $wrpg_mapping = array();
            }

            $payment_gateway_values = array();
            $payment_gateways       = isset( $mapping['payment_gateways'] ) ? json_decode( $mapping['payment_gateways'], true ) : array();
            $payment_gateways_text  = isset( $mapping['payment_gateways_text'] ) ? json_decode( $mapping['payment_gateways_text'], true ) : array();

            // Validate required fields.
            $validate_msg = array();
            if ( empty( $mapping['wholesale_role'] ) ) {
                $validate_msg[] = __( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' );
            }
            if ( empty( $payment_gateways ) ) {
                $validate_msg[] = __( 'Payment Gateways', 'woocommerce-wholesale-prices-premium' );
            }

            if ( ! empty( $validate_msg ) ) {
                $response = array(
                    'status'  => 'error',
                    /* translators: %s: required fields */
                    'message' => sprintf( _n( '%s is required', '%s are required', count( $validate_msg ), 'woocommerce-wholesale-prices-premium' ), implode( ' and ', $validate_msg ) ),
                );
            } elseif ( array_key_exists( $mapping['wholesale_role'], $wrpg_mapping ) ) {
                $response = array(
                    'status'  => 'error',
                    'message' => __( 'Wholesale role you wish to add payment gateway mapping already exist', 'woocommerce-wholesale-prices-premium' ),
                );
            } else {
                $invalid_gateway = false;
                if ( ! empty( $payment_gateways ) ) {
                    // Loop gateways.
                    foreach ( $payment_gateways as $payment_index => $payment_gateway ) {
                        if ( ! empty( $payment_gateway ) && ! empty( $payment_gateways_text[ $payment_index ] ) ) {
                            $payment_gateway_values[] = array(
                                'id'    => $payment_gateway,
                                'title' => $payment_gateways_text[ $payment_index ],
                            );
                        } else {
                            $invalid_gateway = true;
                        }
                    }
                }

                // Validate invalid gateway.
                if ( $invalid_gateway ) {
                    $response = array(
                        'status'  => 'error',
                        'message' => __( 'Invalid/Empty Payment Gateway is not allowed. Please remove it before add.', 'woocommerce-wholesale-prices-premium' ),
                    );
                } else {
                    $wrpg_mapping[ $mapping['wholesale_role'] ] = $payment_gateway_values;
                    update_option( WWPP_OPTION_WHOLESALE_ROLE_PAYMENT_GATEWAY_MAPPING, $wrpg_mapping, 'no' );

                    $response = array(
                        'status'  => 'success',
                        'message' => __( 'Payment gateway added successfully.', 'woocommerce-wholesale-prices-premium' ),
                    );
                }
            }

            return $response;
        }

        /**
         * Group general discount mapping edit.
         *
         * @param array $options Options.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function group_payment_gateway_mapping_edit( $options ) {
            // Remove action.
            unset( $options['action'] );

            $mapping      = WWPP_Helper_Functions::sanitize_array( $options );
            $wrpg_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_PAYMENT_GATEWAY_MAPPING );

            if ( ! is_array( $wrpg_mapping ) ) {
                $wrpg_mapping = array();
            }

            $payment_gateways      = json_decode( $mapping['payment_gateways'], true );
            $payment_gateways_text = json_decode( $mapping['payment_gateways_text'], true );

            // Validate required fields.
            $validate_msg = array();
            if ( empty( $mapping['wholesale_role'] ) ) {
                $validate_msg[] = __( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' );
            }
            if ( empty( $payment_gateways ) ) {
                $validate_msg[] = __( 'Payment Gateways', 'woocommerce-wholesale-prices-premium' );
            }

            if ( ! empty( $validate_msg ) ) {
                $response = array(
                    'status'  => 'error',
                    /* translators: %s: required fields */
                    'message' => sprintf( _n( '%s is required', '%s are required', count( $validate_msg ), 'woocommerce-wholesale-prices-premium' ), implode( ' and ', $validate_msg ) ),
                );
            } else {

                $allow       = true;
                $mapping_key = intval( $mapping['key'] );
                if ( array_key_exists( $mapping['wholesale_role'], $wrpg_mapping ) ) {
                    $mapping_keys = array_keys( $wrpg_mapping );
                    $position     = array_search( $mapping['wholesale_role'], $mapping_keys, true );

                    if ( false !== $position && $position !== $mapping_key ) {
                        $allow = false;
                    }
                } elseif ( ! empty( $wrpg_mapping ) ) {
                    $cntr = 0;
                    foreach ( $wrpg_mapping as $wrpg_key => $wrpg_value ) {
                        if ( $cntr === $mapping_key ) {
                            unset( $wrpg_mapping[ $wrpg_key ] );
                            break;
                        }
                        ++$cntr;
                    }
                }

                if ( $allow ) {
                    // Loop gateways.
                    $payment_gateway_values = array();
                    foreach ( $payment_gateways as $payment_index => $payment_gateway ) {
                        $payment_gateway_values[] = array(
                            'id'    => $payment_gateway,
                            'title' => $payment_gateways_text[ $payment_index ],
                        );
                    }

                    $wrpg_mapping[ $mapping['wholesale_role'] ] = $payment_gateway_values;
                    update_option( WWPP_OPTION_WHOLESALE_ROLE_PAYMENT_GATEWAY_MAPPING, $wrpg_mapping, 'no' );

                    $response = array(
                        'status'  => 'success',
                        'message' => __( 'Payment gateway updated successfully.', 'woocommerce-wholesale-prices-premium' ),
                    );
                } else {
                    $response = array(
                        'status'  => 'error',
                        'message' => __( 'Wholesale role you wish to add payment gateway mapping already exist.', 'woocommerce-wholesale-prices-premium' ),
                    );
                }
            }

            return $response;
        }

        /**
         * Group general discount mapping delete.
         *
         * @param array $options Options.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function group_payment_gateway_mapping_delete( $options ) {
            // Remove action.
            unset( $options['action'] );

            $wholesale_role_key = sanitize_key( $options['wholesale_role'] );
            $wrpg_mapping       = get_option( WWPP_OPTION_WHOLESALE_ROLE_PAYMENT_GATEWAY_MAPPING );

            if ( ! is_array( $wrpg_mapping ) ) {
                $wrpg_mapping = array();
            }

            if ( ! array_key_exists( $wholesale_role_key, $wrpg_mapping ) ) {
                $response = array(
                    'status'  => 'error',
                    'message' => __( 'Wholesale Role / Payment Gateway mapping you wish to delete does not exist on record', 'woocommerce-wholesale-prices-premium' ),
                );
            } else {
                unset( $wrpg_mapping[ $wholesale_role_key ] );
                update_option( WWPP_OPTION_WHOLESALE_ROLE_PAYMENT_GATEWAY_MAPPING, $wrpg_mapping, 'no' );

                $response = array(
                    'status'  => 'success',
                    'message' => __( 'Payment gateway deleted successfully.', 'woocommerce-wholesale-prices-premium' ),
                );
            }

            return $response;
        }

        /**
         * Group general discount mapping save.
         *
         * @param array $options Options.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function group_payment_gateway_surcharge_mapping_save( $options ) {
            // Remove action.
            unset( $options['action'] );

            $surcharge_data = WWPP_Helper_Functions::sanitize_array( $options );

            $user_id           = isset( $surcharge_data['user_id'] ) ? sanitize_key( $surcharge_data['user_id'] ) : 0;
            $surcharge_mapping = $user_id ? get_user_meta( $user_id, 'wwpp_payment_gateway_surcharge_mapping', true ) : get_option( WWPP_OPTION_PAYMENT_GATEWAY_SURCHARGE_MAPPING );

            if ( ! is_array( $surcharge_mapping ) ) {
                $surcharge_mapping = array();
            }

            $validate = $this->_validate_group_payment_gateway_surcharge_mapping( $surcharge_data );
            if ( ! empty( $validate ) ) {
                return $validate;
            }

            $surcharge_mapping[] = $surcharge_data;

            if ( $user_id ) {
                update_user_meta( $user_id, 'wwpp_payment_gateway_surcharge_mapping', $surcharge_mapping );
            } else {
                update_option( WWPP_OPTION_PAYMENT_GATEWAY_SURCHARGE_MAPPING, $surcharge_mapping, 'no' );
            }

            $arr_keys     = array_keys( $surcharge_mapping );
            $latest_index = end( $arr_keys );

            $response = array(
                'status'  => 'success',
                'message' => __( 'Payment gateway surcharge added successfully.', 'woocommerce-wholesale-prices-premium' ),
            );

            return $response;
        }

        /**
         * Group general discount mapping edit.
         *
         * @param array $options Options.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function group_payment_gateway_surcharge_mapping_edit( $options ) {
            // Remove action.
            unset( $options['action'] );

            $idx            = sanitize_key( $options['key'] );
            $surcharge_data = WWPP_Helper_Functions::sanitize_array( $options );

            $user_id           = isset( $options['user_id'] ) ? sanitize_key( $options['user_id'] ) : 0;
            $surcharge_mapping = $user_id ? get_user_meta( $user_id, 'wwpp_payment_gateway_surcharge_mapping', true ) : get_option( WWPP_OPTION_PAYMENT_GATEWAY_SURCHARGE_MAPPING );

            if ( ! is_array( $surcharge_mapping ) ) {
                $surcharge_mapping = array();
            }

            if ( ! array_key_exists( $idx, $surcharge_mapping ) ) {
                $response = array(
                    'status'  => 'error',
                    'message' => __( 'Payment gateway surcharge mapping you wish to update does not exist on record', 'woocommerce-wholesale-prices-premium' ),
                );
            } else {
                $validate = $this->_validate_group_payment_gateway_surcharge_mapping( $surcharge_data );
                if ( ! empty( $validate ) ) {
                    return $validate;
                }

                $surcharge_mapping[ $idx ] = $surcharge_data;

                if ( $user_id ) {
                    update_user_meta( $user_id, 'wwpp_payment_gateway_surcharge_mapping', $surcharge_mapping );
                } else {
                    update_option( WWPP_OPTION_PAYMENT_GATEWAY_SURCHARGE_MAPPING, $surcharge_mapping, 'no' );
                }

                $response = array(
                    'status'  => 'success',
                    'message' => __( 'Payment gateway surcharge updated successfully.', 'woocommerce-wholesale-prices-premium' ),
                );
            }

            return $response;
        }

        /**
         * Validate group payment gateway surcharge mapping.
         *
         * @param array $surcharge_data Data.
         *
         * @since  2.0
         * @access private
         *
         * @return array
         */
        private function _validate_group_payment_gateway_surcharge_mapping( $surcharge_data ) {
            // Validate required fields.
            $validate_msg = array();
            if ( empty( $surcharge_data['wholesale_role'] ) ) {
                $validate_msg[] = __( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' );
            }
            if ( empty( $surcharge_data['payment_gateway'] ) ) {
                $validate_msg[] = __( 'Payment Gateways', 'woocommerce-wholesale-prices-premium' );
            }
            if ( empty( $surcharge_data['surcharge_title'] ) ) {
                $validate_msg[] = __( 'Surcharge Title', 'woocommerce-wholesale-prices-premium' );
            }
            if ( empty( $surcharge_data['surcharge_type'] ) ) {
                $validate_msg[] = __( 'Surcharge Type', 'woocommerce-wholesale-prices-premium' );
            }
            if ( empty( $surcharge_data['surcharge_amount'] ) ) {
                $validate_msg[] = __( 'Surcharge Amount', 'woocommerce-wholesale-prices-premium' );
            }
            if ( empty( $surcharge_data['taxable'] ) ) {
                $validate_msg[] = __( 'Taxable', 'woocommerce-wholesale-prices-premium' );
            }

            $response = array();
            if ( ! empty( $validate_msg ) ) {
                $response = array(
                    'status'  => 'error',
                    /* translators: %s: required fields */
                    'message' => sprintf( _n( '%s is required', '%s are required', count( $validate_msg ), 'woocommerce-wholesale-prices-premium' ), implode( ', ', $validate_msg ) ),
                );
            }

            return $response;
        }

        /**
         * Group general discount mapping delete.
         *
         * @param array $options Options.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function group_payment_gateway_surcharge_mapping_delete( $options ) {
            // Remove action.
            unset( $options['action'] );

            $idx               = sanitize_key( $options['key'] );
            $user_id           = isset( $options['user_id'] ) ? sanitize_key( $options['user_id'] ) : 0;
            $surcharge_mapping = $user_id ? get_user_meta( $user_id, 'wwpp_payment_gateway_surcharge_mapping', true ) : get_option( WWPP_OPTION_PAYMENT_GATEWAY_SURCHARGE_MAPPING );

            if ( ! is_array( $surcharge_mapping ) ) {
                $surcharge_mapping = array();
            }

            if ( ! array_key_exists( $idx, $surcharge_mapping ) ) {
                $response = array(
                    'status'  => 'error',
                    'message' => __( 'Payment gateway surcharge you want to delete does not exist on record', 'woocommerce-wholesale-prices-premium' ),
                );
            } else {
                unset( $surcharge_mapping[ $idx ] );

                if ( $user_id ) {
                    update_user_meta( $user_id, 'wwpp_payment_gateway_surcharge_mapping', $surcharge_mapping );
                } else {
                    update_option( WWPP_OPTION_PAYMENT_GATEWAY_SURCHARGE_MAPPING, $surcharge_mapping, 'no' );
                }

                $response = array(
                    'status'  => 'success',
                    'message' => __( 'Payment gateway surcharge deleted successfully.', 'woocommerce-wholesale-prices-premium' ),
                );
            }

            return $response;
        }

        /**
         * Clear variable product price range caching.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function clear_var_prod_price_range_caching() {
            $this->set_settings_meta_hash();
            $this->set_product_category_meta_hash( null, null, 'product_cat' );
            $this->delete_wholesale_price_on_shop_v3_cache();

            $response = array(
                'status'  => 'success',
                'message' => __( 'Successfully cleared all variable product price range and wholesale price cache.', 'woocommerce-wholesale-prices-premium' ),
            );

            return $response;
        }

        /**
         * Set settings meta hash.
         *
         * @since 1.16.0
         * @since 1.27.8 Delete wholesale price cache.
         * @access public
         * @return string Generated hash.
         */
        public function set_settings_meta_hash() {
            $hash = uniqid( '', true );

            update_option( 'wwpp_settings_hash', $hash, 'no' );

            $this->delete_wholesale_price_on_shop_v3_cache();

            return $hash;
        }

        /**
         * Set product category meta hash.
         *
         * @since 1.16.0
         * @since 1.27.8 Delete wholesale price cache.
         * @access public
         *
         * @param int    $term_id Term Id.
         * @param int    $taxonomy_term_id Taxonomy term id.
         * @param string $taxonomy Taxonomy.
         * @return string|boolean Generated hash or false when operation fails
         */
        public function set_product_category_meta_hash( $term_id, $taxonomy_term_id, $taxonomy = 'product_cat' ) {

            $this->delete_wholesale_price_on_shop_v3_cache();

            if ( 'product_cat' === $taxonomy ) {
                $hash = uniqid( '', true );
                update_option( 'wwpp_product_cat_hash', $hash, 'no' );
                return $hash;
            }

            return false;
        }

        /**
         * Delete wholesale price cache.
         *
         * @since 1.27.8
         * @access public
         */
        public function delete_wholesale_price_on_shop_v3_cache() {
            if ( 'yes' !== get_option( 'wwpp_enable_wholesale_price_cache' ) ) {
                return;
            }

            global $wpdb;

            $product_ids = array();
            $action      = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : ''; // phpcs:ignore

            if ( $action ) {
                switch ( $action ) {
                    // Saving variation(s).
                    case 'woocommerce_save_variations':
                        if ( isset( $_REQUEST['variable_post_id'] ) && ! empty( $_REQUEST['variable_post_id'] ) ) { // phpcs:ignore
                            $product_ids = $_REQUEST['variable_post_id']; // phpcs:ignore
                        }
                        break;

                    // Saving a product.
                    case 'editpost':
                        if ( isset( $_REQUEST['ID'] ) && ! empty( $_REQUEST['ID'] ) ) { // phpcs:ignore
                            $product_ids[] = $_REQUEST['ID']; // phpcs:ignore
                        }
                        break;

                    // Bulk edit.
                    case 'edit':
                        if ( isset( $_REQUEST['post'] ) && ! empty( $_REQUEST['post'] ) ) { // phpcs:ignore
                            $product_ids = $_REQUEST['post']; // phpcs:ignore
                        }
                        break;

                    // Trash/Untrash.
                    case 'trash':
                    case 'untrash':
                        if ( isset( $_REQUEST['post'] ) && ! empty( $_REQUEST['post'] ) ) { // phpcs:ignore
                            if ( is_array( $_REQUEST['post'] ) ) { // phpcs:ignore
                                // Single trash/untrash.
                                $product_ids = $_REQUEST['post']; // phpcs:ignore
                            } else {
                                // Bulk trash/untrash.
                                $product_ids[] = $_REQUEST['post']; // phpcs:ignore
                            }
                        }
                        break;

                }
            }

            if ( ! empty( $product_ids ) ) {

                // Get users with wholesale price cache meta.
                $users_with_cache = $wpdb->get_results( $wpdb->prepare( "SELECT user_id FROM $wpdb->usermeta WHERE meta_key = %s", self::WHOLESALE_PRICE_CACHE_KEY ) );

                if ( $users_with_cache ) {

                    // Loop all users with price cache meta.
                    foreach ( $users_with_cache as $user ) {
                        $user_cached_data = get_user_meta( $user->user_id, self::WHOLESALE_PRICE_CACHE_KEY, true );

                        // Remove only cached related to updated product id.
                        foreach ( $product_ids as $product_id ) {
                            if ( isset( $user_cached_data[ $product_id ] ) ) {
                                unset( $user_cached_data[ $product_id ] );
                            }
                        }

                        update_user_meta( $user->user_id, self::WHOLESALE_PRICE_CACHE_KEY, $user_cached_data );
                    }
                }
            } else {
                // Purge all cache.
                $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->usermeta WHERE meta_key = %s", self::WHOLESALE_PRICE_CACHE_KEY ) );
            }
        }

        /**
         * Clear product transients cache.
         *
         * @since 1.23.2
         * @access public
         *
         * @return array
         */
        public function clear_product_caching() {
            $this->clear_product_transients_cache();

            $response = array(
                'status'  => 'success',
                'message' => __( 'Successfully cleared all products transients cache', 'woocommerce-wholesale-prices-premium' ),
            );

            return $response;
        }

        /**
         * Delete product listing cached transients.
         *
         * @since 1.23.2
         *
         * @param string $transient_name The name of the transient.
         * @access public
         */
        public function clear_product_transients_cache( $transient_name = null ) {

            global $wpdb;

            if ( null !== $transient_name ) {
                delete_transient( $transient_name );
            } else {
                $results = $wpdb->get_results(
                    "SELECT option_name FROM $wpdb->options WHERE option_name LIKE '_transient_wwpp_cached_products_ids%'",
                    ARRAY_A
                );

                // Delete visitor product transients cache.
                delete_transient( 'wwpp_cached_products_ids' );

                // Delete transients.
                if ( ! empty( $results ) ) {
                    foreach ( $results as $key => $name ) {
                        $transient_name = str_replace( '_transient_', '', $name['option_name'] );
                        delete_transient( $transient_name );
                    }
                }
            }
        }

        /**
         * New option to remove all unused product meta data when a role is removed.
         *
         * @since 1.23.9
         * @since 2.0.2 Remove product object in loop to prevent performance issue.
         * @access public
         */
        public function clear_unused_product_meta() {

            // Check current user can manage WC settings.
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                return;
            }

            global $wpdb;

            $existing_roles          = array();
            $wwpp_existing_meta_keys = array();

            foreach ( $this->_all_wholesale_roles as $role_key => $role ) {
                $existing_roles[]          = $role_key;
                $wwpp_existing_meta_keys[] = $role_key . '_wholesale_price';
                $wwpp_existing_meta_keys[] = $role_key . '_have_wholesale_price';
                $wwpp_existing_meta_keys[] = $role_key . '_wholesale_minimum_order_quantity';
                $wwpp_existing_meta_keys[] = $role_key . '_wholesale_order_quantity_step';
            }

            $wwpp_fields = $wpdb->get_results(
                "SELECT 
                    $wpdb->postmeta.* 
                FROM 
                    $wpdb->postmeta 
                WHERE 
                    $wpdb->postmeta.meta_key LIKE '%_wholesale_price' 
                    OR $wpdb->postmeta.meta_key LIKE '%_have_wholesale_price' 
                    OR $wpdb->postmeta.meta_key LIKE '%_wholesale_minimum_order_quantity' 
                    OR $wpdb->postmeta.meta_key LIKE '%_wholesale_order_quantity_step' 
                    OR $wpdb->postmeta.meta_key = 'wwpp_product_wholesale_visibility_filter' 
                    OR $wpdb->postmeta.meta_key = 'wwpp_post_meta_quantity_discount_rule_mapping'"
            );

            if ( ! empty( $wwpp_fields ) ) {

                foreach ( $wwpp_fields as $index => $obj ) {

                    // Delete unused meta keys.
                    switch ( $obj->meta_key ) {
                        case 'wwpp_product_wholesale_visibility_filter':
                            if ( 'all' !== $obj->meta_value && ! in_array( $obj->meta_value, $existing_roles, true ) ) {
                                delete_post_meta( $obj->post_id, $obj->meta_key, $obj->meta_value );
                            }

                            break;
                        case 'wwpp_post_meta_quantity_discount_rule_mapping':
                            $mapping = maybe_unserialize( $obj->meta_value );
                            if ( $mapping ) {
                                foreach ( $mapping as $key => $map ) {
                                    if ( ! in_array( $map['wholesale_role'], $existing_roles, true ) ) {
                                        unset( $mapping[ $key ] );
                                    }
                                }
                            }

                            update_post_meta( $obj->post_id, $obj->meta_key, $mapping );
                            break;

                        default:
                            if ( ! in_array( $obj->meta_key, $wwpp_existing_meta_keys, true ) ) {
                                delete_post_meta( $obj->post_id, $obj->meta_key );
                            }

                            break;
                    }
                }
            }

            $this->initialize_product_visibility_filter_meta();

            $response = array(
                'status'  => 'success',
                'message' => __( 'Successfully cleared unused product meta.', 'woocommerce-wholesale-prices-premium' ),
            );

            return $response;
        }

        /**
         * Get all products and check if the product has no 'wwpp_product_wholesale_visibility_filter' meta key yet. If not,
         * then set a meta for the current product with a key of 'wwpp_product_wholesale_visibility_filter' and value of
         * 'all'. This indicates the product is available for viewing for all users of the site.
         *
         * @since 1.4.2
         * @since 1.13.0 Refactor codebase and move to its own model.
         * @since 1.14.0 Make it handle ajax callback 'wp_ajax_wwpp_initialize_product_visibility_meta'.
         * @since 1.23.9 Set <wholesale_role>_have_wholesale_price meta into the parent group product.
         * @since 1.30.1 Separated the AJAX call so this function can be called from anywhere.
         * @access public
         * @return bool Operation status.
         */
        public function initialize_product_visibility_filter_meta() {
            global $wpdb, $wc_wholesale_prices_premium, $wc_wholesale_prices;

            do_action( 'wwpp_before_initialize_product_visibility_filter_meta' );

            /**
             * In version 1.13.0 we refactored the Wholesale Exclusive Variation feature.
             * Now it is an enhanced select box instead of the old check box.
             * This gives us more flexibility including the 'all' value if no wholesale role is selected.
             * In light to this, we must migrate the old <wholesale_role>_exclusive_variation data to the new 'wwpp_product_visibility_filter'.
             */
            foreach ( $this->_all_wholesale_roles as $role_key => $role ) {
                $wpdb->query(
                    $wpdb->prepare(
                        "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) 
                        SELECT 
                            $wpdb->posts.ID, 
                            'wwpp_product_wholesale_visibility_filter', 
                            %s
                        FROM 
                            $wpdb->posts 
                        WHERE 
                            $wpdb->posts.post_type IN ('product_variation') 
                            AND $wpdb->posts.ID IN (
                            SELECT 
                                $wpdb->posts.ID 
                            FROM 
                                $wpdb->posts 
                                INNER JOIN $wpdb->postmeta ON (
                                $wpdb->posts.ID = $wpdb->postmeta.post_id
                                ) 
                            WHERE 
                                meta_key = %s
                                AND meta_value = 'yes'
                            )
                        ",
                        $role_key,
                        $role_key . '_exclusive_variation'
                    )
                );
            }

            /**
             * Initialize wwpp_product_wholesale_visibility_filter meta
             * This meta is in charge of product visibility. We need to set this to 'all' as mostly
             * all imported products will not have this meta. Meaning, all imported products
             * with no 'wwpp_product_wholesale_visibility_filter' meta set is visible to all users by default.
             */
            $wpdb->query(
                "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) 
                SELECT 
                    $wpdb->posts.ID, 
                    'wwpp_product_wholesale_visibility_filter', 
                    'all' 
                FROM 
                    $wpdb->posts 
                WHERE 
                    $wpdb->posts.post_type IN ('product', 'product_variation') 
                    AND $wpdb->posts.ID NOT IN (
                        SELECT 
                            $wpdb->posts.ID 
                        FROM 
                            $wpdb->posts 
                            INNER JOIN $wpdb->postmeta ON (
                                $wpdb->posts.ID = $wpdb->postmeta.post_id
                            ) 
                        WHERE 
                        meta_key = 'wwpp_product_wholesale_visibility_filter'
                    )"
            );

            /**
             * Address instances where the wwpp_product_wholesale_visibility_filter meta is present but have empty value.
             * This can possibly occur when importing products using external tool that tries to import meta data but fails to properly save the data.
             */
            $wpdb->query(
                "UPDATE 
                    $wpdb->postmeta 
                SET 
                    meta_value = 'all' 
                WHERE 
                    meta_key = 'wwpp_product_wholesale_visibility_filter' 
                    AND meta_value = ''"
            );

            /**
             * Properly set {wholesale_role}_have_wholesale_price meta
             * There will be cases where users import products from external sources and they
             * "set up" wholesale prices via external tools prior to importing
             * We need to handle those cases.
             */
            foreach ( $this->_all_wholesale_roles as $role_key => $role ) {

                // We need to delete prior to inserting, else we will have a stacked meta, same multiple meta for a single post.
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM 
                            $wpdb->postmeta 
                        WHERE 
                            meta_key = %s
                        ",
                        $role_key . '_have_wholesale_price'
                    )
                );

                // Delete wholesale price set by product cat.
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM
                            $wpdb->postmeta
                        WHERE 
                            meta_key = %s
                        ",
                        $role_key . '_have_wholesale_price_set_by_product_cat'
                    )
                );

                // Delete Variations with wholesale price meta. To avoid duplicates or non-existing variation id post.
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM 
                            $wpdb->postmeta 
                        WHERE 
                            meta_key = %s
                        ",
                        $role_key . '_variations_with_wholesale_price'
                    )
                );

                /**
                 * Remove <wholesale_role>_wholesale_price in the variable product meta. This will cause visibility issue.
                 * This scenario happens when a product was still a simple product type (added a wholesale price) and converted to variable.
                 * The wholesale price is not gonna be use anymore so we need to delete it.
                 */
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM 
                            $wpdb->postmeta 
                        WHERE 
                            meta_key = %s
                        AND post_id IN (
                            SELECT 
                                DISTINCT object_id 
                            FROM 
                                $wpdb->term_relationships tr 
                                LEFT JOIN $wpdb->terms terms ON terms.term_id = tr.term_taxonomy_id 
                            WHERE 
                                terms.name = 'variable'
                        )",
                        $role_key . '_wholesale_price'
                    )
                );

                /**
                 * Get all variations that has wholesale_price and assign the id of post meta to the parent variable post meta.
                 * Set <wholesale_role>_variations_with_wholesale_price into post_parent / variable product that has wholesale variations.
                 */
                $wpdb->query(
                    $wpdb->prepare(
                        "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) 
                        SELECT 
                            p.post_parent, 
                            %s, 
                            p.ID 
                        FROM 
                            $wpdb->posts p 
                            LEFT JOIN $wpdb->postmeta pm ON pm.post_id = p.ID 
                        WHERE 
                            p.post_type = 'product_variation' 
                            AND pm.meta_key = %s 
                            AND pm.meta_value > 0
                        ",
                        $role_key . '_variations_with_wholesale_price',
                        $role_key . '_wholesale_price'
                    )
                );

                // Insert have wholesale price.
                $wpdb->query(
                    $wpdb->prepare(
                        "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) 
                        SELECT 
                            $wpdb->posts.ID, 
                            %s, 
                            'yes' 
                        FROM 
                            $wpdb->posts 
                        WHERE 
                            $wpdb->posts.post_type = 'product' 
                            AND $wpdb->posts.ID IN (
                                SELECT 
                                    DISTINCT $wpdb->postmeta.post_id 
                                FROM 
                                    $wpdb->postmeta 
                                WHERE 
                                    (
                                        (
                                            meta_key = %s
                                            AND meta_value > 0
                                        ) 
                                        OR (
                                            meta_key = %s
                                            AND meta_value != ''
                                        ) 
                                        OR (
                                            meta_key = %s
                                            AND meta_value = 'yes'
                                        )
                                    )
                            )",
                        $role_key . '_have_wholesale_price',
                        $role_key . '_wholesale_price',
                        $role_key . '_variations_with_wholesale_price',
                        $role_key . '_have_wholesale_price_set_by_product_cat'
                    )
                );

            }

            // Extra visibility fixes for other product types not covered above (Grouped products, Bundled products).

            // Get grouped products.
            $args = array(
                'type'   => 'grouped',
                'return' => 'ids',
                'limit'  => -1,
            );

            $grouped_products = wc_get_products( $args );

            if ( ! empty( $grouped_products ) ) {

                /**
                 * Set parent group product <wholesale_role>_have_wholesale_price so that it will be visible when
                 * "Only Show Wholesale Products To Wholesale Users" is enabled.
                 */
                foreach ( $grouped_products as $product_id ) {
                    $wc_wholesale_prices->wwp_wholesale_price_grouped_product->insert_have_wholesale_price_meta( $product_id );
                }
            }

            // Get bundled products.
            $bundle_args = array(
                'type'   => 'bundle',
                'return' => 'ids',
                'limit'  => -1,
            );

            $bundled_products = wc_get_products( $bundle_args );

            if ( ! empty( $bundled_products ) ) {

                /**
                 * Set parent group product <wholesale_role>_have_wholesale_price so that it will be visible when
                 * "Only Show Wholesale Products To Wholesale Users" is enabled
                 */
                foreach ( $bundled_products as $bundle_product_id ) {
                    $wc_wholesale_prices_premium->wwpp_wc_bundle_product->set_bundle_product_visibility_meta( $bundle_product_id );
                }
            }

            /**
             * Get all terms and set <wholesale_role>_have_wholesale_price and
             * <wholesale_role>_have_wholesale_price_set_by_product_cat on category level discounts
             */
            $product_terms = get_terms(
                array(
                    'taxonomy'   => 'product_cat',
                    'hide_empty' => false,
                )
            );

            foreach ( $product_terms as $term ) {
                $category_discount = get_option( 'taxonomy_' . $term->term_id );

                if ( ! empty( $category_discount ) ) {
                    $wholesale_role_with_discounts = array();

                    foreach ( $this->_all_wholesale_roles as $role_key => $role ) {
                        if ( isset( $category_discount[ $role_key . '_wholesale_discount' ] ) &&
                            ! empty( $category_discount[ $role_key . '_wholesale_discount' ] ) ) {
                            $wholesale_role_with_discounts[] = $role_key;
                        }
                    }

                    $category_discount_products = wc_get_products(
                        array(
                            'category' => array( $term->slug ),
                            'return'   => 'ids',
                        )
                    );

                    $category_discount_products = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT 
                                DISTINCT p.ID 
                            FROM 
                                $wpdb->posts p 
                                LEFT JOIN $wpdb->postmeta pm ON (p.ID = pm.post_id) 
                                LEFT JOIN $wpdb->term_relationships tr ON (p.ID = tr.object_id) 
                            WHERE 
                                p.post_status = 'publish' 
                                AND p.post_type = 'product' 
                                AND tr.term_taxonomy_id = %d",
                            $term->term_id
                        ),
                        ARRAY_A
                    );

                    if ( ! empty( $category_discount_products ) ) {
                        foreach ( $category_discount_products as $product ) {
                            foreach ( $wholesale_role_with_discounts as $role_key ) {
                                $wpdb->insert(
                                    $wpdb->postmeta,
                                    array(
                                        'post_id'    => $product['ID'],
                                        'meta_key'   => $role_key . '_have_wholesale_price',
                                        'meta_value' => 'yes',
                                    )
                                );

                                $wpdb->insert(
                                    $wpdb->postmeta,
                                    array(
                                        'post_id'    => $product['ID'],
                                        'meta_key'   => $role_key . '_have_wholesale_price_set_by_product_cat',
                                        'meta_value' => 'yes',
                                    )
                                );
                            }
                        }
                    }
                }
            }

            // Clear product id cache.
            $wc_wholesale_prices_premium->wwpp_cache->clear_product_transients_cache();

            // Clear WC Product Transients Cache.
            if ( function_exists( 'wc_delete_product_transients' ) ) {
                wc_delete_product_transients();
            }

            $this->_initialize_wholesale_sale_prices_meta();

            do_action( 'wwpp_after_initialize_product_visibility_filter_meta' );

            return true;
        }

        /**
         * Add wholesale sale prices meta for simple and variable products that has been created on previous version.
         * '{$role_key}_have_wholesale_sale_price': determine if product has wholesale sale price,
         * '{$role_key}_variations_have_wholesale_sale_price': determine if the variations of the variable product has wholesale sale price,
         *
         * @since 1.30.1.1
         * @access public
         */
        private function _initialize_wholesale_sale_prices_meta() {
            global $wpdb, $wc_wholesale_prices_premium;

            foreach ( $this->_all_wholesale_roles as $role_key => $role ) {
                $product_ids = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT posts.ID FROM $wpdb->posts as posts
                                LEFT JOIN $wpdb->postmeta as postmeta ON posts.ID = postmeta.post_id
                            WHERE posts.post_type IN ('product', 'product_variation')
                                AND postmeta.meta_key = %s AND postmeta.meta_value > 0
                        ",
                        $role_key . '_wholesale_sale_price',
                    )
                );

                if ( ! empty( $product_ids ) ) {
                    foreach ( $product_ids as $product_id ) {
                        $product = wc_get_product( $product_id );

                        if ( $product->get_type() === 'simple' ) {
                            update_post_meta( $product_id, "{$role_key}_have_wholesale_sale_price", 'yes' );
                        } elseif ( $product->get_type() === 'variation' ) {
                            update_post_meta( $product_id, "{$role_key}_variations_have_wholesale_sale_price", 'yes' );
                            update_post_meta( $product_id, "{$role_key}_have_wholesale_sale_price", 'yes' );
                        }
                    }
                }
            }

            $wc_wholesale_prices_premium->wwpp_wholesale_prices->scheduled_wholesale_sales();

            return true;
        }

        /**
         * Ajax wrapper function for re-initializing product visibility filter meta data on products. Called from the Help
         * settings page.
         *
         * @access public
         * @return bool Operation status.
         */
        public function re_initialize_product_visibility_meta() {
            // Ready to go, call internal function to initialize product visibility meta.
            $this->initialize_product_visibility_filter_meta();

            $response = array(
                'status'  => 'success',
                'message' => __( 'Visibility meta successfully initialize.', 'woocommerce-wholesale-prices-premium' ),
            );

            return $response;
        }

        /**
         * Force fetch the latest update data from our server.
         *
         * @since 1.26.5
         * @access public
         */
        public function force_fetch_update_data() {
            /**
             * Force check and fetch the update data of the plugin.
             * Will save the update data into the WWPP_UPDATE_DATA option.
             */
            $this->ping_for_new_version( true ); // Force check.

            /**
             * Get the update data from the WWPP_UPDATE_DATA option.
             * Returned data is pre-formatted.
             */
            $update_data       = $this->inject_plugin_update(); // Inject new update data if there are any.
            $installed_version = is_multisite() ? get_site_option( WWPP_OPTION_INSTALLED_VERSION ) : get_option( WWPP_OPTION_INSTALLED_VERSION );

            /**
             * Get wp update transient data.
             * Automatically unserializes the returned value.
             */
            $update_transient = is_multisite() ? get_site_option( '_site_transient_update_plugins', false ) : get_option( '_site_transient_update_plugins', false );

            // If the plugin is up to date then put the plugin in no update.
            if ( $update_data && isset( $update_data['value'] ) && version_compare( $update_data['value']->new_version, $installed_version, '==' ) ) {

                unset( $update_transient->response[ $update_data['key'] ] );
                $update_transient->no_update[ $update_data['key'] ] = $update_data['value'];

            } elseif ( $update_data && $update_transient && isset( $update_transient->response ) && is_array( $update_transient->response ) ) {

                // Inject into the wp update data our latest plugin update data.
                $update_transient->response[ $update_data['key'] ] = $update_data['value'];

            }

            // Update wp update data transient.
            if ( is_multisite() ) {
                update_site_option( '_site_transient_update_plugins', $update_transient );
            } else {
                update_option( '_site_transient_update_plugins', $update_transient, 'no' );
            }

            $response = array(
                'status'  => 'success',
                'message' => __( 'Successfully re-fetch plugin update data.', 'woocommerce-wholesale-prices-premium' ),
            );

            return $response;
        }

        /**
         * Ping plugin for new version. Ping static file first, if indeed new version is available, trigger update data request.
         *
         * @since 1.17
         * @since 1.26.5 We added new parameter $force. This will serve as a flag if we are going to "forcefully" fetch the latest update data from the server.
         *
         * @param bool $force Flag to determine whether to "forcefully" fetch the latest update data from the server.
         * @access public
         */
        public function ping_for_new_version( $force = false ) {

            $license_activated = is_multisite() ? get_site_option( WWPP_LICENSE_ACTIVATED ) : get_option( WWPP_LICENSE_ACTIVATED );

            if ( 'yes' !== $license_activated ) {
                if ( is_multisite() ) {
                    delete_site_option( WWPP_UPDATE_DATA );
                } else {
                    delete_option( WWPP_UPDATE_DATA );
                }

                return;
            }

            $retrieving_update_data = is_multisite() ? get_site_option( WWPP_RETRIEVING_UPDATE_DATA ) : get_option( WWPP_RETRIEVING_UPDATE_DATA );
            if ( 'yes' === $retrieving_update_data ) {
                return;
            }

            /**
             * Only attempt to get the existing saved update data when the operation is not forced.
             * Else, if it is forced, we ignore the existing update data if any
             * and forcefully fetch the latest update data from our server.
             *
             * @since 1.26.5
             */
            if ( ! $force ) {
                $update_data = is_multisite() ? get_site_option( WWPP_UPDATE_DATA ) : get_option( WWPP_UPDATE_DATA );
            } else {
                $update_data = null;
            }

            /**
             * Even if the update data is still valid, we still go ahead and do static json file ping.
             * The reason is on WooCommerce 3.3.x , it seems WooCommerce do not regenerate the download url every time you change the downloadable zip file on WooCommerce store.
             * The side effect is, the download url is still valid, points to the latest zip file, but the update info could be outdated coz we check that if the download url
             * is still valid, we don't check for update info, and since the download url will always be valid even after subsequent release of the plugin coz WooCommerce is reusing the url now
             * then there will be a case our update info is outdated. So that is why we still need to check the static json file, even if update info is still valid.
             */

            $option = apply_filters(
                'wwpp_plugin_new_version_ping_options',
                array(
                    'timeout' => 10, // Seconds.
                    'headers' => array( 'Accept' => 'application/json' ),
                )
            );

            $response = wp_remote_retrieve_body( wp_remote_get( apply_filters( 'wwpp_plugin_new_version_ping_url', WWPP_STATIC_PING_FILE ), $option ) );

            if ( ! empty( $response ) ) {
                $response = json_decode( $response );

                if ( ! empty( $response ) && property_exists( $response, 'version' ) ) {
                    $installed_version = is_multisite() ? get_site_option( WWPP_OPTION_INSTALLED_VERSION ) : get_option( WWPP_OPTION_INSTALLED_VERSION );

                    if ( ( ! $update_data && version_compare( $response->version, $installed_version, '>' ) ) ||
                        ( $update_data && version_compare( $response->version, $update_data->latest_version, '>' ) ) ) {

                        if ( is_multisite() ) {
                            update_site_option( WWPP_RETRIEVING_UPDATE_DATA, 'yes' );
                        } else {
                            update_option( WWPP_RETRIEVING_UPDATE_DATA, 'yes', 'no' );
                        }

                        // Fetch software product update data.
                        if ( is_multisite() ) {
                            $this->_fetch_software_product_update_data( get_site_option( WWPP_OPTION_LICENSE_EMAIL ), get_site_option( WWPP_OPTION_LICENSE_KEY ), home_url() );
                        } else {
                            $this->_fetch_software_product_update_data( get_option( WWPP_OPTION_LICENSE_EMAIL ), get_option( WWPP_OPTION_LICENSE_KEY ), home_url() );
                        }

                        if ( is_multisite() ) {
                            delete_site_option( WWPP_RETRIEVING_UPDATE_DATA );
                        } else {
                            delete_option( WWPP_RETRIEVING_UPDATE_DATA );
                        }
                    }
                }
            }
        }

        /**
         * When WordPress fetches 'update_plugins' transient (which holds data regarding plugins that have updates), we
         * inject our plugin update data in, if we have any. This data is saved in the WWPP_UPDATE_DATA option. It is
         * important we don't delete this option until the plugin has successfully updated. We do this because we are
         * hooking on transient read. So if the option gets deleted on first transient read, then subsequent reads will not
         * include our plugin update data.
         *
         * This function also checks the validity of the update url to cover the edge case where we may have stored the
         * update data locally as an option, then later on the update data became invalid. So as a safety mechanism we check
         * if the update url is still valid and if not we remove the locally stored update data.
         *
         * @since 1.17 Refactor codebase to adapt being called on set_site_transient function. We don't need to check for
         * software update data validity as its already been checked on ping_for_new_version and this function is
         * immediately called right after that.
         * @access public
         * @return array Filtered plugin updates data.
         */
        public function inject_plugin_update() {
            $license_activated = is_multisite() ? get_site_option( WWPP_LICENSE_ACTIVATED ) : get_option( WWPP_LICENSE_ACTIVATED );
            if ( 'yes' !== $license_activated ) {
                return false;
            }

            $software_update_data = is_multisite() ? get_site_option( WWPP_UPDATE_DATA ) : get_option( WWPP_UPDATE_DATA );

            if ( $software_update_data ) {

                // Create update info object.
                $update                       = new \stdClass();
                $update->name                 = 'WooCommerce Wholesale Prices Premium';
                $update->id                   = $software_update_data->download_id;
                $update->slug                 = 'woocommerce-wholesale-prices-premium';
                $update->plugin               = WWPP_PLUGIN_BASE_NAME;
                $update->new_version          = $software_update_data->latest_version;
                $update->url                  = WWS_SLMW_SERVER_URL;
                $update->package              = $software_update_data->download_url;
                $update->tested               = $software_update_data->tested_up_to;
                $update->{'update-supported'} = true;
                $update->update               = false;
                $update->icons                = array(
                    '1x'      => 'https://ps.w.org/woocommerce-wholesale-prices/assets/wwpp-icon-128x128.jpg',
                    '2x'      => 'https://ps.w.org/woocommerce-wholesale-prices/assets/wwpp-icon-256x256.jpg',
                    'default' => 'https://ps.w.org/woocommerce-wholesale-prices/assets/wwpp-icon-256x256.jpg',
                );

                $update->banners = array(
                    '1x'      => 'https://ps.w.org/woocommerce-wholesale-prices/assets/wwpp-banner-772x250.jpg',
                    '2x'      => 'https://ps.w.org/woocommerce-wholesale-prices/assets/wwpp-banner-1544x500.jpg',
                    'default' => 'https://ps.w.org/woocommerce-wholesale-prices/assets/wwpp-banner-1544x500.jpg',
                );

                return array(
                    'key'   => WWPP_PLUGIN_BASE_NAME,
                    'value' => $update,
                );

            }

            return false;
        }

        /**
         * Fetch software product update data.
         *
         * @since 1.17
         * @access public
         *
         * @param string $activation_email Activation email.
         * @param string $license_key      License key.
         * @param string $site_url         Site url.
         */
        private function _fetch_software_product_update_data( $activation_email, $license_key, $site_url ) {

            $update_check_url = add_query_arg(
                array(
                    'activation_email' => rawurlencode( $activation_email ),
                    'license_key'      => $license_key,
                    'site_url'         => $site_url,
                    'software_key'     => 'WWPP',
                    'multisite'        => is_multisite() ? 1 : 0,
                ),
                apply_filters( 'wwpp_software_product_update_data_url', WWS_SLMW_SERVER_URL . '/wp-json/slmw/v1/license/update' )
            );

            $option = apply_filters(
                'wwpp_software_product_update_data_options',
                array(
                    'timeout' => 30, // Seconds.
                    'headers' => array( 'Accept' => 'application/json' ),
                )
            );

            $result = wp_remote_retrieve_body( wp_remote_get( $update_check_url, $option ) );

            if ( ! empty( $result ) ) {
                $result = json_decode( $result );

                if ( ! empty( $result ) && 'success' === $result->status && ! empty( $result->software_update_data ) ) {
                    if ( is_multisite() ) {
                        update_site_option( WWPP_UPDATE_DATA, $result->software_update_data );
                    } else {
                        update_option( WWPP_UPDATE_DATA, $result->software_update_data, 'no' );
                    }
                } else {
                    if ( is_multisite() ) {
                        delete_site_option( WWPP_UPDATE_DATA );
                    } else {
                        delete_option( WWPP_UPDATE_DATA );
                    }

                    if ( ! empty( $result ) && 'fail' === $result->status &&
                        isset( $result->error_key ) &&
                        in_array( $result->error_key, array( 'invalid_license', 'expired_license' ), true ) ) {

                        // Invalid License.
                        if ( is_multisite() ) {
                            update_site_option( WWPP_LICENSE_ACTIVATED, 'no' );
                        } else {
                            update_option( WWPP_LICENSE_ACTIVATED, 'no', 'no' );
                        }

                        // Check if license is expired.
                        if ( 'expired_license' === $result->error_key ) {
                            if ( is_multisite() ) {
                                update_site_option( WWPP_LICENSE_EXPIRED, $result->expiration_timestamp );
                            } else {
                                update_option( WWPP_LICENSE_EXPIRED, $result->expiration_timestamp, 'no' );
                            }
                        } elseif ( is_multisite() ) {
                            delete_site_option( WWPP_LICENSE_EXPIRED );
                        } else {
                            delete_option( WWPP_LICENSE_EXPIRED );
                        }
                    }
                }

                // Fire post product update data hook.
                do_action( 'wwpp_software_product_update_data', $result, $activation_email, $license_key );
            }
        }

        /**
         * Group wholesale role override specific requirements mapping save.
         *
         * @param array $options Options.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function group_wholesale_specific_override_mapping_save( $options ) {
            // Remove action.
            unset( $options['action'] );

            $mapping = WWPP_Helper_Functions::sanitize_array( $options );

            $order_requirement_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_ORDER_REQUIREMENT_MAPPING, array() );
            if ( ! is_array( $order_requirement_mapping ) ) {
                $order_requirement_mapping = array();
            }

            // Validate required fields.
            $validate_msg = array();
            if ( empty( $mapping['wholesale_role'] ) ) {
                $validate_msg[] = __( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' );
            }
            if ( empty( intval( $mapping['minimum_order_quantity'] ) ) && empty( intval( $mapping['minimum_order_subtotal'] ) ) ) {
                $validate_msg[] = __( 'Minimum Order Quantity or Minimum Sub-total Amount ($)', 'woocommerce-wholesale-prices-premium' );
            }

            if ( ! empty( $validate_msg ) ) {
                $response = array(
                    'status'  => 'error',
                    /* translators: %s: required fields */
                    'message' => sprintf( _n( '%s is required', '%s are required', count( $validate_msg ), 'woocommerce-wholesale-prices-premium' ), implode( ' and ', $validate_msg ) ),
                );
            } elseif ( array_key_exists( $mapping['wholesale_role'], $order_requirement_mapping ) ) {
                $response = array(
                    'status'  => 'error',
                    'message' => __( 'Duplicate Wholesale Role Order Requirement Entry, Already Exist', 'woocommerce-wholesale-prices-premium' ),
                );
            } else {
                $wholesale_role = $mapping['wholesale_role'];
                unset( $mapping['wholesale_role'] );
                $order_requirement_mapping[ $wholesale_role ] = $mapping;
                update_option( WWPP_OPTION_WHOLESALE_ROLE_ORDER_REQUIREMENT_MAPPING, $order_requirement_mapping, 'no' );
                $response = array(
                    'status'  => 'success',
                    'message' => __( 'Successfully added wholesale role order requirement mapping.', 'woocommerce-wholesale-prices-premium' ),
                );
            }

            return $response;
        }

        /**
         * Group wholesale role override specific requirements mapping edit.
         *
         * @param array $options Options.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function group_wholesale_specific_override_mapping_edit( $options ) {
            // Remove action.
            unset( $options['action'] );

            $mapping = array_map( 'sanitize_text_field', $options );

            $order_requirement_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_ORDER_REQUIREMENT_MAPPING, array() );
            if ( ! is_array( $order_requirement_mapping ) ) {
                $order_requirement_mapping = array();
            }

            // Validate required fields.
            $validate_msg = array();
            if ( empty( intval( $mapping['minimum_order_quantity'] ) ) && empty( intval( $mapping['minimum_order_subtotal'] ) ) ) {
                $validate_msg[] = __( 'Minimum Order Quantity or Minimum Sub-total Amount ($)', 'woocommerce-wholesale-prices-premium' );
            }

            if ( ! empty( $validate_msg ) ) {
                $response = array(
                    'status'  => 'error',
                    /* translators: %s: required fields */
                    'message' => sprintf( _n( '%s is required', '%s are required', count( $validate_msg ), 'woocommerce-wholesale-prices-premium' ), implode( ' and ', $validate_msg ) ),
                );
            } elseif ( ! array_key_exists( $mapping['wholesale_role'], $order_requirement_mapping ) ) {
                $response = array(
                    'status'  => 'error',
                    'message' => __( 'Wholesale Role Order Requirement Entry You Wish To Edit Does Not Exist', 'woocommerce-wholesale-prices-premium' ),
                );
            } else {
                $wholesale_role = $mapping['wholesale_role'];
                unset( $mapping['wholesale_role'] );
                $order_requirement_mapping[ $wholesale_role ] = $mapping;
                update_option( WWPP_OPTION_WHOLESALE_ROLE_ORDER_REQUIREMENT_MAPPING, $order_requirement_mapping, 'no' );
                $response = array(
                    'status'  => 'success',
                    'message' => __( 'Successfully edited wholesale role order requirement mapping.', 'woocommerce-wholesale-prices-premium' ),
                );
            }

            return $response;
        }

        /**
         * Group wholesale role override specific requirements mapping delete.
         *
         * @param array $options Options.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function group_wholesale_specific_override_mapping_delete( $options ) {
            // Remove action.
            unset( $options['action'] );

            $wholesale_role = sanitize_text_field( $options['wholesale_role'] );

            $order_requirement_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_ORDER_REQUIREMENT_MAPPING, array() );
            if ( ! is_array( $order_requirement_mapping ) ) {
                $order_requirement_mapping = array();
            }

            if ( ! array_key_exists( $wholesale_role, $order_requirement_mapping ) ) {
                $response = array(
                    'status'  => 'error',
                    'message' => __( 'Wholesale Role Order Requirement Entry You Wish To Delete Does Not Exist', 'woocommerce-wholesale-prices-premium' ),
                );
            } else {
                unset( $order_requirement_mapping[ $wholesale_role ] );
                update_option( WWPP_OPTION_WHOLESALE_ROLE_ORDER_REQUIREMENT_MAPPING, $order_requirement_mapping, 'no' );

                $response = array(
                    'status'  => 'success',
                    'message' => __( 'Successfully deleted wholesale role order requirement mapping.', 'woocommerce-wholesale-prices-premium' ),
                );
            }

            return $response;
        }

        /**
         * Execute model.
         *
         * @since  2.0
         * @access public
         */
        public function run() {
            // Group tax save mapping.
            add_action( 'wwp_group_settings_group_tax_exp_mapping_save', array( $this, 'group_tax_exp_mapping_save' ), 10, 1 );
            add_action( 'wwp_group_settings_group_tax_exp_mapping_edit', array( $this, 'group_tax_exp_mapping_edit' ), 10, 1 );
            add_action( 'wwp_group_settings_group_tax_exp_mapping_delete', array( $this, 'group_tax_exp_mapping_delete' ), 10, 1 );
            add_action( 'wwp_group_settings_group_tax_cls_mapping_save', array( $this, 'group_tax_cls_mapping_save' ), 10, 1 );
            add_action( 'wwp_group_settings_group_tax_cls_mapping_edit', array( $this, 'group_tax_cls_mapping_edit' ), 10, 1 );
            add_action( 'wwp_group_settings_group_tax_cls_mapping_delete', array( $this, 'group_tax_cls_mapping_delete' ), 10, 1 );

            // Group shipping save mapping.
            add_action( 'wwp_group_settings_group_shipping_nonzoned_mapping_save', array( $this, 'group_shipping_nonzoned_mapping_save' ), 10, 1 );
            add_action( 'wwp_group_settings_group_shipping_nonzoned_mapping_edit', array( $this, 'group_shipping_nonzoned_mapping_edit' ), 10, 1 );
            add_action( 'wwp_group_settings_group_shipping_nonzoned_mapping_delete', array( $this, 'group_shipping_nonzoned_mapping_delete' ), 10, 1 );

            // Group discount save mapping.
            add_action( 'wwp_group_settings_group_general_discount_mapping_save', array( $this, 'group_general_discount_mapping_save' ), 10, 1 );
            add_action( 'wwp_group_settings_group_general_discount_mapping_edit', array( $this, 'group_general_discount_mapping_edit' ), 10, 1 );
            add_action( 'wwp_group_settings_group_general_discount_mapping_delete', array( $this, 'group_general_discount_mapping_delete' ), 10, 1 );
            add_action( 'wwp_group_settings_group_general_quantity_mapping_save', array( $this, 'group_general_quantity_mapping_save' ), 10, 1 );
            add_action( 'wwp_group_settings_group_general_quantity_mapping_edit', array( $this, 'group_general_quantity_mapping_edit' ), 10, 1 );
            add_action( 'wwp_group_settings_group_general_quantity_mapping_delete', array( $this, 'group_general_quantity_mapping_delete' ), 10, 1 );
            add_action( 'wwp_group_settings_group_general_cart_price_discount_mapping_save', array( $this, 'group_general_cart_price_discount_mapping_save' ), 10, 1 );
            add_action( 'wwp_group_settings_group_general_cart_price_discount_mapping_edit', array( $this, 'group_general_cart_price_discount_mapping_edit' ), 10, 1 );
            add_action( 'wwp_group_settings_group_general_cart_price_discount_mapping_delete', array( $this, 'group_general_cart_price_discount_mapping_delete' ), 10, 1 );

            // Group gateway save mapping.
            add_action( 'wwp_group_settings_group_payment_gateway_mapping_save', array( $this, 'group_payment_gateway_mapping_save' ), 10, 1 );
            add_action( 'wwp_group_settings_group_payment_gateway_mapping_edit', array( $this, 'group_payment_gateway_mapping_edit' ), 10, 1 );
            add_action( 'wwp_group_settings_group_payment_gateway_mapping_delete', array( $this, 'group_payment_gateway_mapping_delete' ), 10, 1 );
            add_action( 'wwp_group_settings_group_payment_gateway_surcharge_mapping_save', array( $this, 'group_payment_gateway_surcharge_mapping_save' ), 10, 1 );
            add_action( 'wwp_group_settings_group_payment_gateway_surcharge_mapping_edit', array( $this, 'group_payment_gateway_surcharge_mapping_edit' ), 10, 1 );
            add_action( 'wwp_group_settings_group_payment_gateway_surcharge_mapping_delete', array( $this, 'group_payment_gateway_surcharge_mapping_delete' ), 10, 1 );

            // Group wholesale role specific requirements save mapping.
            add_action( 'wwp_group_settings_group_wholesale_specific_override_mapping_save', array( $this, 'group_wholesale_specific_override_mapping_save' ), 10, 1 );
            add_action( 'wwp_group_settings_group_wholesale_specific_override_mapping_edit', array( $this, 'group_wholesale_specific_override_mapping_edit' ), 10, 1 );
            add_action( 'wwp_group_settings_group_wholesale_specific_override_mapping_delete', array( $this, 'group_wholesale_specific_override_mapping_delete' ), 10, 1 );

            // Clear cache actions.
            add_action( 'wwp_trigger_clear_var_prod_price_range_caching', array( $this, 'clear_var_prod_price_range_caching' ), 10, 1 );
            add_action( 'wwp_trigger_clear_product_caching', array( $this, 'clear_product_caching' ), 10, 1 );
            add_action( 'wwp_trigger_clear_unused_product_meta', array( $this, 'clear_unused_product_meta' ) );
            add_action( 'wwp_trigger_initialize_product_visibility_meta', array( $this, 're_initialize_product_visibility_meta' ), 10, 1 );
            add_action( 'wwp_trigger_force_fetch_update_data', array( $this, 'force_fetch_update_data' ), 10, 1 );
        }
    }
}
