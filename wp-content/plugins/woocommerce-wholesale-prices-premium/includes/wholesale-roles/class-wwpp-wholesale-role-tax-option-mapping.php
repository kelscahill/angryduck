<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Model that houses the logic of wholesale role tax options mapping.
 *
 * @since 1.14.0
 */
class WWPP_Wholesale_Role_Tax_Option_Mapping {

    /**
     * Class Properties
     */

    /**
     * Property that holds the single main instance of WWPP_Wholesale_Role_Tax_Option_Mapping.
     *
     * @since 1.14.0
     * @access private
     * @var WWPP_Wholesale_Role_Tax_Option_Mapping
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
     * WWPP_Wholesale_Role_Tax_Option_Mapping constructor.
     *
     * @since 1.14.0
     * @access public
     *
     * @param array $dependencies Array of instance objects of all dependencies of WWPP_Wholesale_Role_Tax_Option_Mapping model.
     */
    public function __construct( $dependencies ) {
        $this->_wwpp_wholesale_roles = $dependencies['WWPP_Wholesale_Roles'];
    }

    /**
     * Ensure that only one instance of WWPP_Wholesale_Role_Tax_Option_Mapping is loaded or can be loaded (Singleton Pattern).
     *
     * @since 1.14.0
     * @access public
     *
     * @param array $dependencies Array of instance objects of all dependencies of WWPP_Wholesale_Role_Tax_Option_Mapping model.
     * @return WWPP_Wholesale_Role_Tax_Option_Mapping
     */
    public static function instance( $dependencies ) {
        if ( ! self::$_instance instanceof self ) {
            self::$_instance = new self( $dependencies );
        }

        return self::$_instance;
    }

    /**
     * Add an entry to wholesale role / tax option mapping.
     * Design based on trust that the caller will supply an array with the following elements below.
     * wholesale_role
     * tax_exempted
     *
     * @since 1.4.7
     * @since 1.14.0 Refactor codebase and move to its proper model.
     * @access public
     */
    public function wwpp_add_wholesale_role_tax_option() {
        // Security checks.
        if ( ! check_ajax_referer( 'wwpp_add_wholesale_role_tax_option', 'nonce', false ) ) {
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

        $mapping            = WWPP_Helper_Functions::sanitize_array( $_POST['mapping'] ?? array() );
        $tax_option_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_TAX_OPTION_MAPPING, array() );

        if ( ! is_array( $tax_option_mapping ) ) {
            $tax_option_mapping = array();
        }

        if ( array_key_exists( $mapping['wholesale_role'], $tax_option_mapping ) ) {
            $response = array(
                'status'        => 'fail',
                'error_message' => __( 'Duplicate Wholesale Role Tax Option Entry, Already Exist', 'woocommerce-wholesale-prices-premium' ),
            );
        } else {
            $wholesale_role = $mapping['wholesale_role'];
            unset( $mapping['wholesale_role'] );
            $tax_option_mapping[ $wholesale_role ] = $mapping;
            update_option( WWPP_OPTION_WHOLESALE_ROLE_TAX_OPTION_MAPPING, $tax_option_mapping, 'no' );

            $response = array( 'status' => 'success' );
        }

        @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
        echo wp_json_encode( $response );
        wp_die();
    }

    /**
     * Edit an entry of wholesale role / tax option mapping. Caller must supply an array with the following elements below.
     * wholesale_role
     * tax_exempted
     *
     * @since 1.4.7
     * @since 1.14.0 Refactor codebase and move to its proper model.
     * @access public
     */
    public function wwpp_edit_wholesale_role_tax_option() {
        // Security checks.
        if ( ! check_ajax_referer( 'wwpp_edit_wholesale_role_tax_option', 'nonce', false ) ) {
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

        $mapping            = WWPP_Helper_Functions::sanitize_array( $_POST['mapping'] ?? array() );
        $tax_option_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_TAX_OPTION_MAPPING, array() );

        if ( ! is_array( $tax_option_mapping ) ) {
            $tax_option_mapping = array();
        }

        if ( ! array_key_exists( $mapping['wholesale_role'], $tax_option_mapping ) ) {
            $response = array(
                'status'        => 'fail',
                'error_message' => __( 'Wholesale Role Tax Option Entry You Wish To Edit Does Not Exist', 'woocommerce-wholesale-prices-premium' ),
            );
        } else {
            $wholesale_role = $mapping['wholesale_role'];
            unset( $mapping['wholesale_role'] );
            $tax_option_mapping[ $wholesale_role ] = $mapping;
            update_option( WWPP_OPTION_WHOLESALE_ROLE_TAX_OPTION_MAPPING, $tax_option_mapping, 'no' );

            $response = array( 'status' => 'success' );
        }

        @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
        echo wp_json_encode( $response );
        wp_die();
    }

    /**
     * Delete an entry of wholesale role / tax option mapping.
     *
     * @since 1.4.7
     * @since 1.14.0 Refactor codebase and move to its proper model.
     * @access public
     */
    public function wwpp_delete_wholesale_role_tax_option() {
        // Security checks.
        if ( ! check_ajax_referer( 'wwpp_delete_wholesale_role_tax_option', 'nonce', false ) ) {
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

        $wholesale_role     = sanitize_text_field( $_POST['wholesale_role'] ?? '' );
        $tax_option_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_TAX_OPTION_MAPPING, array() );

        if ( ! is_array( $tax_option_mapping ) ) {
            $tax_option_mapping = array();
        }

        if ( ! array_key_exists( $wholesale_role, $tax_option_mapping ) ) {
            $response = array(
                'status'        => 'fail',
                'error_message' => __( 'Wholesale Role Tax Option Entry You Wish To Delete Does Not Exist', 'woocommerce-wholesale-prices-premium' ),
            );
        } else {
            unset( $tax_option_mapping[ $wholesale_role ] );
            update_option( WWPP_OPTION_WHOLESALE_ROLE_TAX_OPTION_MAPPING, $tax_option_mapping, 'no' );

            $response = array( 'status' => 'success' );
        }

        @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
        echo wp_json_encode( $response );
        wp_die();
    }

    /**
     * Generate mapping entry markup.
     * TODO: Move this to a view file.
     *
     * @since 1.16.0
     * @access public
     *
     * @param string $wholesale_role_key Wholesale role key.
     * @param array  $mapping_entry      Mapping entry.
     * @return string Mapping entry markup.
     */
    private function _generate_mapping_entry_markup( $wholesale_role_key, $mapping_entry ) {
        ?>
        <tr>
            <td class="meta hidden">
                <span class="wholesale-role"><?php echo esc_html( $wholesale_role_key ?? '' ); ?></span>
                <ul class="tax-class"><?php echo esc_html( $mapping_entry['tax-class'] ?? '' ); ?></ul>
            </td>
            <td class="wholesale-role-name"><?php echo esc_html( $mapping_entry['wholesale-role-name'] ?? '' ); ?></td>
            <td class="tax-classes-name"><ul><?php echo esc_html( $mapping_entry['tax-class-name'] ?? '' ); ?></ul></td>
            <td class="controls">
                <a class="edit dashicons dashicons-edit"></a>
                <a class="delete dashicons dashicons-no"></a>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Save tax class mapping entry.
     *
     * @since 1.16.0
     * @access public
     */
    public function ajax_save_tax_class_mapping() {
        // Security checks.
        if ( ! check_ajax_referer( 'ajax_save_tax_class_mapping', 'nonce', false ) ) {
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

        if ( ! isset( $_POST['mapping-data'] ) ) {
            echo wp_json_encode(
                array(
                    'status'    => 'fail',
                    'error_msg' => __(
                        'Required data not supplied',
                        'woocommerce-wholesale-prices-premium'
                    ),
                )
            );
            wp_die();
        }

        $mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_TAX_CLASS_OPTIONS_MAPPING );

        if ( ! is_array( $mapping ) ) {
            $mapping = array();
        }

        $wholesale_role_key = isset( $_POST['mapping-data']['wholesale-role-key'] ) ? sanitize_key( $_POST['mapping-data']['wholesale-role-key'] ) : null;
        $mode               = isset( $_POST['mapping-data']['mode'] ) ? sanitize_text_field( $_POST['mapping-data']['mode'] ) : null;

        if ( 'add' === $mode && array_key_exists( $wholesale_role_key, $mapping ) ) {
            $response = array(
                'status'    => 'fail',
                'error_msg' => __( 'Wholesale role mapping entry already exist', 'woocommerce-wholesale-prices-premium' ),
            );
        } elseif ( 'edit' === $mode && ! array_key_exists( $wholesale_role_key, $mapping ) ) {
            $response = array(
                'status'    => 'fail',
                'error_msg' => __( 'Wholesale role mapping entry you are trying to edit does not exist', 'woocommerce-wholesale-prices-premium' ),
            );
        } else {
            // phpcs:disable
            unset( $_POST['mapping-data']['wholesale-role-key'] );
            unset( $_POST['mapping-data']['mode'] );
            // phpcs:enable

            $mapping[ $wholesale_role_key ] = WWPP_Helper_Functions::sanitize_array( $_POST['mapping-data'] );

            update_option( WWPP_OPTION_WHOLESALE_ROLE_TAX_CLASS_OPTIONS_MAPPING, $mapping, 'no' );

            $response = array(
                'status'            => 'success',
                'entry_data'        => array( $wholesale_role_key => $mapping[ $wholesale_role_key ] ),
                'entry_data_markup' => $this->_generate_mapping_entry_markup( $wholesale_role_key, $mapping[ $wholesale_role_key ] ),
            );
        }

        @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
        echo wp_json_encode( $response );
        wp_die();
    }

    /**
     * Delete tax class mapping entry.
     *
     * @since 1.16.0
     * @access public
     */
    public function ajax_delete_tax_class_mapping() {
        // Security checks.
        if ( ! check_ajax_referer( 'ajax_delete_tax_class_mapping', 'nonce', false ) ) {
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

        if ( ! isset( $_POST['wholesale-role-key'] ) ) {
            echo wp_json_encode(
                array(
                    'status'    => 'fail',
                    'error_msg' => __(
                        'Required data not supplied',
                        'woocommerce-wholesale-prices-premium'
                    ),
                )
            );
            wp_die();
        }

        $mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_TAX_CLASS_OPTIONS_MAPPING );

        if ( ! is_array( $mapping ) ) {
            $mapping = array();
        }

        if ( ! array_key_exists( sanitize_key( $_POST['wholesale-role-key'] ), $mapping ) ) {
            $response = array(
                'status'    => 'fail',
                'error_msg' => __( 'Mapping entry you are trying to delete does not exist', 'woocommerce-wholesale-prices-premium' ),
            );
        } else {
            unset( $mapping[ sanitize_key( $_POST['wholesale-role-key'] ) ] );
            update_option( WWPP_OPTION_WHOLESALE_ROLE_TAX_CLASS_OPTIONS_MAPPING, $mapping, 'no' );

            $response = array( 'status' => 'success' );
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
        add_action( 'wp_ajax_wwpp_add_wholesale_role_tax_option', array( $this, 'wwpp_add_wholesale_role_tax_option' ) );
        add_action( 'wp_ajax_wwpp_edit_wholesale_role_tax_option', array( $this, 'wwpp_edit_wholesale_role_tax_option' ) );
        add_action( 'wp_ajax_wwpp_delete_wholesale_role_tax_option', array( $this, 'wwpp_delete_wholesale_role_tax_option' ) );
        add_action( 'wp_ajax_wwpp_save_tax_class_mapping', array( $this, 'ajax_save_tax_class_mapping' ) );
        add_action( 'wp_ajax_wwpp_delete_tax_class_mapping', array( $this, 'ajax_delete_tax_class_mapping' ) );
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
