<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( 'WWPP_Shortcodes' ) ) {

    /**
     * Class that houses the logic of various shortcodes of the plugin.
     *
     * @since 1.11.0
     */
    class WWPP_Shortcodes {

        /*
        |--------------------------------------------------------------------------
        | Class Properties
        |--------------------------------------------------------------------------
        */

        /**
         * Property that holds the single main instance of WWPP_Shortcodes.
         *
         * @since 1.14.0
         * @access private
         * @var WWPP_Shortcodes
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
        

        
        
        /*
        |--------------------------------------------------------------------------
        | Class Methods
        |--------------------------------------------------------------------------
        */

        /**
         * WWPP_Shortcodes constructor.
         *
         * @since 1.14.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Shortcodes model.
         */
        public function __construct( $dependencies ) {

            $this->_wwpp_wholesale_roles = $dependencies[ 'WWPP_Wholesale_Roles' ];

        }

        /**
         * Ensure that only one instance of WWPP_Shortcodes is loaded or can be loaded (Singleton Pattern).
         *
         * @since 1.14.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Shortcodes model.
         * @return WWPP_Shortcodes
         */
        public static function instance( $dependencies ) {

            if ( !self::$_instance instanceof self )
                self::$_instance = new self( $dependencies );

            return self::$_instance;

        }

        /**
        * Show content to only wholesale users.
        *
        * @since 1.11.0
        * @since 1.14.0 Refactor codebase.
        * @access public
        *
        * @param array  $atts    Shortcode attributes.
        * @param string $content Constents to be visible to wholesale users only.
        * @return string Constents to be visible to wholesale users only.
        */
        public function sc_wholesale_content( $atts , $content = '' ) {

            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            $atts = shortcode_atts( array(
                'role' => ''
            ) , $atts , 'wholesale_content' );

            
            if ( $atts[ 'role' ] == '' ) {

                if ( !empty( $user_wholesale_role ) )
                    return do_shortcode( $content );
                
            } else {

                $filtered_roles = explode( "," , $atts[ 'role' ] );
                $filtered_roles = array_map( "trim" , $filtered_roles );
                $arr_intersect  = array_intersect( $user_wholesale_role , $filtered_roles );

                if ( !empty( $arr_intersect ) )
                    return do_shortcode( $content );

            }

            return '';

        }

        /**
        * Hide content from wholesale users.
        *
        * @since 1.11.0
        * @since 1.14.0 Refactor codebase.
        * @access public
        *
        * @param array  $atts    Shortcode attributes.
        * @param string $content Contents to be hidden from wholesale users.
        * @return string Contents to be hidden from wholesale users.
        */
        public function sc_hide_from_wholesale( $atts , $content = '' ) {

            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            $atts = shortcode_atts( array(
                'role' => ''
            ) , $atts , 'hide_from_wholesale' );

            if ( $atts[ 'role' ] == '' ) {

                if ( !empty( $user_wholesale_role ) )
                    return '';
                
            } else {

                $filtered_roles = explode( "," , $atts[ 'role' ] );
                $filtered_roles = array_map( "trim" , $filtered_roles );
                $arr_intersect  = array_intersect( $user_wholesale_role , $filtered_roles );

                if ( !empty( $arr_intersect ) )
                    return '';

            }

            return do_shortcode( $content );

        }




        /**
         * Execute model.
         *
         * @since 1.14.0
         * @access public
         */
        public function run() {

            add_shortcode( 'wholesale_content'   , array( $this , 'sc_wholesale_content' )   , 10 , 1 );
            add_shortcode( 'hide_from_wholesale' , array( $this , 'sc_hide_from_wholesale' ) , 10 , 1 );

        }

    }

}