<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Classes
 */

namespace RymeraWebCo\WWOF\Shortcodes;

use RymeraWebCo\WWOF\Abstracts\Abstract_Shortcode;
use RymeraWebCo\WWOF\Factories\Order_Form;
use RymeraWebCo\WWOF\Helpers\WWLC;
use RymeraWebCo\WWOF\Helpers\WWOF;
use RymeraWebCo\WWOF\Factories\Vite_App;
use RymeraWebCo\WWOF\Traits\Singleton_Trait;
use stdClass;


/**
 * WWOF_Product_Listing class.
 *
 * @since 3.0
 */
class WWOF_Product_Listing extends Abstract_Shortcode {

    use Singleton_Trait;

    /**
     * Holds the class instance object
     *
     * @since 3.0.6
     * @var WWOF_Product_Listing $instance object
     */
    protected static $instance;

    /**
     * Breakdance shortcode element class name constant.
     *
     * @since 3.1.0
     */
    const BREAKDANCE_SHORTCODE_CLASS = 'EssentialElements\Shortcode';

    /**
     * Maximum recursion depth for Breakdance data traversal.
     *
     * @since 3.1.0
     */
    const MAX_RECURSION_DEPTH = 50;

    /**
     * Initialize the shortcode class.
     */
    public function __construct() {

        parent::__construct(
            array(
                'id' => null,
            )
        );
    }

    /**
     * Check if the current user can view the shortcode content.
     *
     * @since 3.0
     * @return bool
     */
    protected function current_user_can_view() {

        if ( current_user_can( 'manage_woocommerce' ) ) {
            $can_view = true;
        } else {
            $form       = Order_Form::get_instance( $this->attributes['id'] );
            $allow_read = $form ? ( $form->get_settings( 'permissions' )['allowPublicRead'] ?? 'yes' ) : 'yes';
            $can_view   = 'publish' === $form->post_status && empty( $form->settings['permissions']['allowedUserRoles'] ) &&
                ( ( 'yes' === $allow_read ) || ( 'no' === $allow_read && is_user_logged_in() ) );

            $role = wp_get_current_user()->roles[0] ?? null;
            if ( ! empty( $form->settings['permissions']['allowedUserRoles'] ) &&
                $role && in_array( $role, $form->settings['permissions']['allowedUserRoles'], true ) ) {
                $can_view = true;
            }
        }

        /**
         * Filter the current user if they can view the shortcode content.
         *
         * Hook name: `wwof_product_listing_can_view`
         *
         * @param bool $can_view Whether the current user can view the shortcode content.
         *
         * @since 3.0
         */
        return apply_filters( "{$this->tag}_can_view", $can_view );
    }

    /**
     * Custom shortcode restriction content.
     *
     * @since 3.0
     * @return string
     */
    protected function get_restriction_content() {

        $form = Order_Form::get_instance( $this->attributes['id'] );

        $title = ! empty( $form->settings['permissions']['noAccessTitle'] )
            ? do_shortcode( html_entity_decode( $form->settings['permissions']['noAccessTitle'] ) )
            : '<h3>' . esc_html__( 'Access Denied', 'woocommerce-wholesale-order-form' ) . '</h3>';

        $content = ! empty( $form->settings['permissions']['noAccessMessage'] )
            ? do_shortcode( html_entity_decode( $form->settings['permissions']['noAccessMessage'] ) )
            : '<p class="error">' . esc_html__( 'You do not have permission to view this order form.', 'woocommerce-wholesale-order-form' ) . '</p>';

        $login_url = ! empty( $form->settings['permissions']['noAccessLoginUrl'] )
            ? do_shortcode( html_entity_decode( $form->settings['permissions']['noAccessLoginUrl'] ) )
            : ( WWLC::is_active() && function_exists( 'wwlc_get_url_of_page_option' )
                ? wwlc_get_url_of_page_option( 'wwlc_general_login_page' )
                : ( get_permalink( wc_get_page_id( 'myaccount' ) )
                    ? get_permalink( wc_get_page_id( 'myaccount' ) )
                    : wp_login_url() )
            );

        return wp_kses_post(
        /**
         * Filter the restriction content.
         *
         * Hook name: `wwof_product_listing_restriction_content`
         *
         * @param string $content The restriction content.
         *
         * @since 3.0
         */
            apply_filters(
                "{$this->tag}_restriction_content",
                sprintf(
                    '<div class="wwof-restricted">%1$s%2$s%3$s</div>',
                    $title,
                    $content,
                    'publish' !== $form->post_status
                        ? ''
                        : '<p><a href="' . esc_url( $login_url ) . '">' .
                        ( is_user_logged_in()
                            ? esc_html__( 'Logout and login here', 'woocommerce-wholesale-order-form' )
                            : esc_html__( 'Login Here', 'woocommerce-wholesale-order-form' ) ) .
                        '</a></p>'
                )
            )
        );
    }

    /**
     * Get shortcode output content.
     *
     * @since 3.0
     */
    protected function get_content() {

        /*
        |--------------------------------------------------------------------------
        | Track the number of times this shortcode is used on a page.
        |--------------------------------------------------------------------------
        |
        | We track the number of times this shortcode is used on a page so, we can
        | generate a unique element id.
        |
        */
        static $form_count = 0;
        ++$form_count;

        if ( empty( $this->attributes['id'] ) ) {
            if ( current_user_can( 'manage_woocommerce' ) ) {
                return '<p class="wwof-error">' . __( 'No order form ID specified.', 'woocommerce-wholesale-order-form' ) . '</p>';
            } else {
                return '<p class="wwof-error">' . __( 'There is a config error on this form. Please contact the site administrator.', 'woocommerce-wholesale-order-form' ) . '</p>';
            }
        } elseif ( ! get_post( $this->attributes['id'] ) ) {
            if ( current_user_can( 'manage_woocommerce' ) ) {
                /* translators: %d = order form ID given to [wwof_product_listing] shortcode */
                return '<p class="wwof-error">' . sprintf( esc_html__( 'Order form ID %d does not exist!', 'woocommerce-wholesale-order-form' ), $this->attributes['id'] ) . '</p>';
            } else {
                return '<p class="wwof-error">' . esc_html__( 'There is a config error on this form. Please contact the site administrator.', 'woocommerce-wholesale-order-form' ) . '</p>';
            }
        }

        $hash = '-' . mb_substr( md5( wp_json_encode( $this->attributes ) . "-$form_count" ), 0, 8 );

        return sprintf(
            '<div id="order-form-app%s" class="order-form-app app-root" data-form-id="%d" data-wwof-form="1" data-wwof-form-id="%d" data-testid="wwof-form"></div>',
            $hash,
            esc_attr( $this->attributes['id'] ),
            esc_attr( $this->attributes['id'] )
        );
    }

    /**
     * Enqueue scripts and styles.
     *
     * @since 3.0
     * @return void
     */
    public function wp_enqueue_scripts() {

        /**
         * Force Load Order Form Script
         *
         * Force loading of order form app script. If the shortcode is not getting parse properly
         * for its attributes, use the filter hook `wwof_force_load_form_posts` to return a list of
         * order form post objects.
         *
         * Format should be:
         * array(
         *  <order form ID> => \RymeraWebCo\WWOF\Factories\Order_Form::get_instance( get_post(<order form ID>) ),
         *  //...
         * )
         *
         * @param array $form_posts An array of order form post objects.
         *
         * @since 3.0
         */
        $form_posts = apply_filters( 'wwof_force_load_form_posts', array() );

        // Check if Breakdance is active using Breakdance-specific function check.
        $is_breakdance_active = function_exists( 'Breakdance\Data\get_meta' );

        $form_shortcodes = array();

        if ( $is_breakdance_active ) {
            $breakdance_meta = \Breakdance\Data\get_meta( get_the_ID(), '_breakdance_data', true );
            $breakdance_tree = array();
            if ( ! empty( $breakdance_meta ) && isset( $breakdance_meta['tree_json_string'] ) ) {
                $decoded_data = json_decode( $breakdance_meta['tree_json_string'] );
                // Ensure we always pass an array, even if json_decode returns null.
                $breakdance_tree = is_object( $decoded_data ) ? $decoded_data : array();
            }
            $form_shortcodes = $this->get_shortcodes_on_breakdance_builder( $breakdance_tree );
        }

        if ( ! empty( $form_posts ) || has_shortcode( get_the_content(), $this->tag ) || has_shortcode( get_post_field( 'post_content' ), $this->tag ) || ( $is_breakdance_active && ! empty( $form_shortcodes ) ) ) {
            if ( ! empty( $form_posts ) ) {
                $form_posts = (array) $form_posts;
            } elseif ( $is_breakdance_active && ! empty( $form_shortcodes ) ) {
                // Process Breakdance shortcodes.
                foreach ( $form_shortcodes as $form_shortcode ) {
                    $form_posts = $this->parse_shortcode_attributes( $form_shortcode, $form_posts );
                }
            } else {
                // Process regular shortcodes from post content.
                $form_posts = $this->parse_shortcode_attributes( get_the_content(), $form_posts );
            }

            if ( ! empty( $form_posts ) ) {
                $l10n = WWOF::order_form_app_common_l10n(
                    array(
                        'formPosts' => $form_posts,
                    )
                );

                $app = new Vite_App(
                    $this->tag,
                    'src/apps/wwof/front/index.ts',
                    array(
                        'wp-i18n',
                        'wp-url',
                        'wp-hooks',
                        'wp-html-entities',
                        'lodash',
                        'wp-element',
                    ),
                    $l10n
                );

                $app->enqueue();

                /**
                 * Executes right after the order form app script is enqueued.
                 *
                 * Hook name: `wwof_enqueue_wwof_product_listing_sc_script`
                 *
                 * @since 3.0
                 */
                do_action( "wwof_enqueue_{$this->tag}_sc_script" );
            }
        }
    }

    /**
     * Parse shortcode attributes from content and populate form posts array.
     *
     * @since 3.1.0
     * @param string $content The content to search for shortcodes.
     * @param array  $form_posts Existing form posts array to populate.
     * @return array Form posts array with parsed shortcodes.
     */
    protected function parse_shortcode_attributes( $content, $form_posts = array() ) {

        $pattern = get_shortcode_regex( array( $this->tag ) );
        preg_match_all( '/' . $pattern . '/', $content, $matches );

        // Fallback to post_content if no matches found in provided content.
        if ( empty( $matches[3] ) ) {
            preg_match_all( '/' . $pattern . '/', get_post_field( 'post_content' ), $matches );
        }

        if ( ! empty( $matches[3] ) ) {
            foreach ( $matches[3] as $att ) {
                $atts = ! empty( $att ) ? shortcode_parse_atts( $att ) : false;
                if ( ! empty( $atts['id'] ) ) {
                    $form_post = get_post( $atts['id'] );
                    // Validate post type to ensure security.
                    if ( $form_post && Order_Form::POST_TYPE === $form_post->post_type ) {
                        $form_posts[ $atts['id'] ] = Order_Form::get_instance( $form_post );
                    }
                }
            }
        }

        return $form_posts;
    }

    /**
     * Get the shortcodes on breakdance builder
     *
     * @since 3.1.0
     * @param array|object $datas The shortcode on breakdance shortcode element.
     * @param string       $shortcode_string The shortcode string to search for. Defaults to shortcode tag.
     * @param array        $shortcodes The shortcodes that are found.
     * @param int          $depth Current recursion depth to prevent infinite recursion.
     * @return array Shortcodes of wwof_form.
     */
    public function get_shortcodes_on_breakdance_builder( $datas = array(), $shortcode_string = null, $shortcodes = array(), $depth = 0 ) {

        // Prevent infinite recursion by limiting depth.
        if ( $depth >= self::MAX_RECURSION_DEPTH ) {
            return $shortcodes;
        }

        // Use shortcode tag as default if not provided.
        if ( null === $shortcode_string ) {
            $shortcode_string = $this->tag;
        }

        // Check PHP version compatibility once before the loop.
        $has_str_contains = function_exists( 'str_contains' );

        foreach ( $datas as $data ) {
            if ( ! is_object( $data ) ) {
                continue;
            }

            $object = property_exists( $data, 'data' ) ? $data->data : new stdClass();
            $type   = is_object( $object ) && isset( $object->type ) ? $object->type : '';

            $shortcode = '';

            if ( is_object( $object ) && property_exists( $object, 'properties' ) ) {
                $properties = $object->properties;
                if ( is_object( $properties ) && property_exists( $properties, 'content' ) ) {
                    $content = $properties->content;
                    if ( is_object( $content ) && property_exists( $content, 'shortcode' ) ) {
                        $shortcode_obj = $content->shortcode;
                        if ( is_object( $shortcode_obj ) && property_exists( $shortcode_obj, 'full_shortcode' ) ) {
                            $shortcode = $shortcode_obj->full_shortcode;
                        }
                    }
                }
            }

            // Use class constant instead of magic string.
            $has_shortcode = $has_str_contains
                ? str_contains( $shortcode, $shortcode_string )
                : false !== strpos( $shortcode, $shortcode_string );

            if ( self::BREAKDANCE_SHORTCODE_CLASS === $type && $has_shortcode ) {
                $shortcodes[] = $shortcode;
            }

            if ( is_object( $data ) && property_exists( $data, 'children' ) && ! empty( $data->children ) ) {
                $shortcodes = array_merge( $shortcodes, $this->get_shortcodes_on_breakdance_builder( $data->children, $shortcode_string, array(), $depth + 1 ) );
            }
        }

        return $shortcodes;
    }
}
