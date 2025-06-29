<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Abstracts
 */

namespace RymeraWebCo\WWOF\Abstracts;

use RymeraWebCo\WWOF\Traits\Magic_Get_Trait;

/**
 * Abstract Shortcode class.
 *
 * @since 3.0
 */
abstract class Abstract_Shortcode {

    use Magic_Get_Trait;

    /**
     * Shortcode tag
     *
     * @since 3.0
     * @var string
     */
    protected $tag;

    /**
     * Shortcode default attributes
     *
     * @since 3.0
     * @var array
     */
    protected $default_attributes;

    /**
     * Shortcode attributes
     *
     * @since 3.0
     * @var array
     */
    protected $attributes;

    /**
     * Shortcode content
     *
     * @since 3.0
     * @var string
     */
    protected $content;

    /**
     * Shortcode constructor.
     *
     * @param array|null  $default_attributes Shortcode default attributes.
     * @param string|null $tag                Shortcode tag. Defaults to class name in snake_case. Capital letters
     *                                        are converted to lowercase and underscores are inserted before the
     *                                        capital letters.
     *
     * @since 3.0
     */
    public function __construct( $default_attributes = null, $tag = null ) {

        /***************************************************************************
         * Automatically sets shortcode tag if not provided.
         ***************************************************************************
         *
         * We automatically set the shortcode tag to the class name in lowercase if
         * it is not provided.
         */
        $this->tag = $tag ?? strtolower( wp_basename( get_class( $this ) ) );

        /***************************************************************************
         * Default shortcode attributes
         ***************************************************************************
         *
         * We set the default shortcode attributes to an empty array if it is not
         * provided.
         */
        $this->default_attributes = $default_attributes ?? array();
    }

    /**
     * Register the shortcode
     *
     * @since 3.0
     * @return void
     */
    public function run() {

        /***************************************************************************
         * Register shortcode with WordPress
         ***************************************************************************
         *
         * Actual shortcode registration with WordPress.
         */
        add_shortcode( $this->tag, array( $this, 'render' ) );

        if ( method_exists( $this, 'wp_enqueue_scripts' ) ) {
            add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );
        }
    }

    /**
     * HTML markup to be displayed when the shortcode output is not allowed to be displayed. Override to customize
     * the HTML markup output.
     *
     * @since 3.0
     * @return string
     */
    protected function get_restriction_content() {

        return apply_filters(
            "{$this->tag}_restriction_content",
            sprintf(
                '%1$s%2$s%3$s',
                '<p class="wwof-error">',
                __( 'You do not have permission to view this content.', 'woocommerce-wholesale-order-form' ),
                '</p>'
            )
        );
    }

    /**
     * Override this method to customize access restriction.
     *
     * @since 3.0
     * @return true|false
     */
    protected function current_user_can_view() {

        return apply_filters( "{$this->tag}_can_view", true );
    }

    /**
     * Render the shortcode
     *
     * @param array|string $attributes The user passed shortcode attributes.
     * @param string       $content    The shortcode content.
     * @param string       $tag        The shortcode tag.
     *
     * @since 3.0
     * @return string
     */
    public function render( $attributes = array(), $content = '', $tag = '' ) {

        /***************************************************************************
         * Parse and merge shortcode attributes
         ***************************************************************************
         *
         * We parse the shortcode attributes and merge them with the defaults.
         */
        $this->attributes = shortcode_atts( $this->default_attributes, $attributes, $tag );

        /***************************************************************************
         * Check if the current user can view the shortcode output
         ***************************************************************************
         *
         * We check if the current user can view the shortcode output. If not, we
         * return the restriction content.
         */
        if ( ! $this->current_user_can_view() ) {
            return $this->get_restriction_content();
        }

        /***************************************************************************
         * Set shortcode content
         ***************************************************************************
         *
         * We set the shortcode content. The content here is the content between
         * the opening and closing shortcode tags.
         */
        $this->content = $content;

        /***************************************************************************
         * Return the shortcode output string
         ***************************************************************************
         *
         * We return the shortcode HTML output markup string.
         */

        return $this->get_content();
    }

    /**
     * Get the shortcode output string.
     *
     * @since 3.0
     * @return string
     */
    abstract protected function get_content();
}
