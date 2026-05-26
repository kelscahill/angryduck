<?php if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly

if ( ! class_exists( 'WWLC_Invisible_Recaptcha' ) ) {

    /**
     * Handle Invisible reCAPTCHA compatibility.
     *
     * @since 1.15
     */
    class WWLC_Invisible_Recaptcha {
        /*
        |--------------------------------------------------------------------------
        | Class Properties
        |--------------------------------------------------------------------------
         */

        /**
         * Property that holds the single main instance of WWLC_Invisible_Recaptcha.
         *
         * @since 1.15
         * @access private
         * @var WWLC_Invisible_Recaptcha
         */
        private static $_instance;

        /*
        |--------------------------------------------------------------------------
        | Class Methods
        |--------------------------------------------------------------------------
         */

        /**
         * WWLC_Invisible_Recaptcha constructor.
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWLC_Invisible_Recaptcha model.
         *
         * @access public
         * @since 1.15
         */
        public function __construct( $dependencies ) {}

        /**
         * Ensure that only one instance of WWLC_Invisible_Recaptcha is loaded or can be loaded (Singleton Pattern).
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWLC_Invisible_Recaptcha model.
         *
         * @return WWLC_Invisible_Recaptcha
         * @since 1.15
         */
        public static function instance( $dependencies = null ) {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Unregister script from "Invisible reCaptcha for WordPress" plugin.
         * We will have our invisible recaptcha option with our custom code.
         * On remove duplicate script from our shortcodes. Login and Registration page.
         * Users will still be able to use this plugin on other forms.
         *
         * @since 1.15
         */
        public function remove_script() {

            global $post;

            if ( $post && $post instanceof WP_POST ) {
                if ( has_shortcode( $post->post_content, 'wwlc_registration_form' ) || has_shortcode( $post->post_content, 'wwlc_login_form' ) ) {
                    wp_deregister_script( 'google-invisible-recaptcha' );
                }
            }
        }

        /**
         * Override Invisible Recaptcha for WordPress. Always make the captcha valid since we are doing our custom code.
         * This is to avoid any error after registration for submission.
         * Happens on ajax request.
         *
         * @param bool $value   True if captcha is valid or false if not.
         *
         * @since 1.15
         * @return bool
         */
        public function is_recaptcha_valid( $value ) {

            // Only bypass the third-party reCAPTCHA plugin when WWLC's own reCAPTCHA is enabled
            // (WWLC handles its own verification in that case).
            if ( 'yes' === get_option( 'wwlc_security_enable_recaptcha' )
                && isset( $_POST['action'] ) && 'wwlc_create_user' === sanitize_text_field( wp_unslash( $_POST['action'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified below.
            ) {
                if ( isset( $_POST['wwlc_register_user_nonce_field'] ) && wp_verify_nonce( sanitize_key( $_POST['wwlc_register_user_nonce_field'] ), 'wwlc_register_user' ) ) {
                    return true;
                }
            }

            return $value;
        }

        /**
         * Execute model.
         *
         * @since 1.15
         * @access public
         */
        public function run() {

            // Fix conflicts with "Invisible reCaptcha for WordPress" plugin.
            add_action( 'wp_print_scripts', array( $this, 'remove_script' ), 100 );
            add_filter( 'google_invre_is_valid_request_filter', array( $this, 'is_recaptcha_valid' ), 100 );
        }
    }
}
