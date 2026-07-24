<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Integrations
 */

namespace RymeraWebCo\WWOF\Integrations;

use RymeraWebCo\WWOF\Abstracts\Abstract_Class;
use SaveToWishlist\Classes\Frontend\Buttons;
use WC_Product;

/**
 * SaveTo_Wishlist class.
 *
 * Renders the SaveTo Wishlist "Add to Wishlist" button for each product in the
 * order form. The button, its markup, AJAX action and front-end assets are owned
 * entirely by SaveTo Wishlist Lite; SaveTo Wishlist Pro is a strict add-on that
 * requires Lite and does not change the button render path or contribute any
 * button styling. Guarding on Lite therefore covers both "Lite only" and
 * "Lite + Pro" states with a single path.
 *
 * @since 3.1.1
 */
class SaveTo_Wishlist extends Abstract_Class {

    /**
     * Build the SaveTo Wishlist button HTML for a product.
     *
     * Mirrors SaveTo Wishlist Lite's own single-product rendering
     * (Buttons::show_button_on_product_page) so the order-form button reflects the
     * store's configured Product Page settings — placement, button type and
     * colours from SaveTo Wishlist Settings > Button Placements & Styles > Product
     * Page — plus the chosen "Frontend Styling" CSS mode, which Lite enqueues
     * globally on every front-end page (the order form included) and which governs
     * whether those colours/fonts render or fall through to the active theme. The
     * Lite renderer reads the global $product, so it is set for the duration of the
     * call and then restored. The store's "disable guest wishlist" setting is
     * honoured the same way Lite's product gate
     * (Buttons::display_wishlist_button_on_product_page) does, so logged-out
     * visitors never get a button that Lite would reject.
     *
     * @param WC_Product $wc_data The product object.
     *
     * @since 3.1.1
     * @return string The button HTML, or an empty string when unavailable.
     */
    private function get_button_html( $wc_data ) {

        if ( ! $wc_data instanceof WC_Product || ! class_exists( Buttons::class ) ) {
            return '';
        }

        // Mirror Lite's product-page guest gate: when the store disables the guest
        // wishlist, the button is hidden from logged-out visitors on the product
        // page, so it must stay hidden in the order form too. When the guest
        // wishlist is enabled (the default), guests get the button.
        if ( ! is_user_logged_in() && 'yes' === get_option( 'stwlite_settings_disable_guest_wishlist', 'no' ) ) {
            return '';
        }

        global $product;
        $previous_product = $product;
        $product          = $wc_data; // The Lite renderer reads the global $product.

        try {
            $buttons = Buttons::instance();

            // Use the store's configured Product Page placement, button type and
            // colours so the order-form button matches what the store owner set up
            // for product pages (and honours any customisations made there).
            $placement     = $buttons->product_wishlist_button_placement_option();
            $style         = $buttons->product_wishlist_button_style_option();
            $color_options = $buttons->product_wishlist_button_color_options_option();

            $button_html = $buttons->wishlist_button_html( $placement, $style, $color_options );

            /*
             * Re-apply Lite's product-button filter so a store's product-button
             * customisations also reach the order-form button. This filter is
             * documented in SaveTo Wishlist Lite
             * Buttons::show_button_on_product_page().
             *
             * NOTE: Lite normally fires this from within single-product context.
             * Here it runs during the order-form REST request, which is NOT a
             * product context — any third-party callback that gates on
             * is_product() will no-op, so those particular customisations won't
             * reach the order-form button. A dedicated order-form hook (e.g.
             * wwof_saveto_wishlist_button_html) is a possible follow-up if that
             * becomes a real need.
             */
            $button_html = apply_filters( 'stwlite_wishlist_button_product_html', $button_html, $product, $placement, $style, $color_options );

            /*
             * Lite builds the container with id="stw-button-placement-<placement>".
             * In the order form the same placement repeats on every product row,
             * which would (a) emit duplicate ids across the listing and (b) trip
             * Lite's single-product script that relocates the "top_image_right"
             * button into .woocommerce-product-gallery and removes the original —
             * deleting the button here, since the order form has no gallery. Suffix
             * the id with the product id so each row's container is unique and that
             * relocation never matches. The placement CLASS is left intact so the
             * configured placement's styling still applies; the dedicated Wishlist
             * column renders the button in normal flow (see SaveToWishlist.vue),
             * which keeps the image-overlay placements from breaking the cell.
             */
            $button_html = str_replace(
                'id="stw-button-placement-' . esc_attr( $placement ) . '"',
                'id="stw-button-placement-' . esc_attr( $placement ) . '-' . $wc_data->get_id() . '"',
                $button_html
            );
        } finally {
            // Always restore the global product, even if the renderer or a
            // filter callback throws, so the rest of the request is unaffected.
            $product = $previous_product;
        }

        return $button_html;
    }

    /**
     * Add the SaveTo Wishlist button HTML to the REST API product response.
     *
     * @param \WP_REST_Response $response The response object.
     * @param \WC_Data          $wc_data  The WC_Data object.
     * @param \WP_REST_Request  $request  Request used to generate the response.
     *
     * @since 3.1.1
     * @return \WP_REST_Response The response object, with the button HTML added when available.
     */
    public function rest_saveto_wishlist_data_object( $response, $wc_data, $request ) {

        $button_html = $this->get_button_html( $wc_data );

        if ( '' !== $button_html ) {
            $response->data['wwof_saveto_wishlist']['html'] = $button_html;
        }

        return $response;
    }

    /**
     * Add additional localization properties for SaveTo Wishlist.
     *
     * @param array $defaults The default localization properties.
     *
     * @since 3.1.1
     * @return array
     */
    public function wwof_app_common_l10n( $defaults ) {

        array_push( $defaults['thirdPartyPlugins'], 'saveto-wishlist-lite-for-woocommerce' );
        array_push( $defaults['_fields'], 'wwof_saveto_wishlist' );

        return $defaults;
    }

    /**
     * Enqueue SaveTo Wishlist Lite's front-end stylesheet on the order-form
     * editor screen.
     *
     * The editor renders a live preview using the same front components, so the
     * wishlist column shows Lite's real button markup. Lite only enqueues its
     * front-end stylesheet on the front-end (wp_enqueue_scripts), so without this
     * the preview button is unstyled — the icon doubles up, the multi-list
     * "Choose list" dropdown shows expanded instead of on-hover, and it crowds the
     * neighbouring Add to Cart column. Load the same stylesheet Lite would, honouring
     * the "Frontend Styling" (Frontend CSS Loading) mode, so the preview matches the
     * front-end.
     *
     * @param string $hook_suffix The current admin page hook suffix.
     *
     * @since 3.1.1
     */
    public function enqueue_order_form_editor_styles( $hook_suffix ) {

        // Only the WWOF order-form admin screen (list + editor share the hook),
        // and only when Lite exposes the URL + path constants we rely on.
        if ( ! defined( 'STWLITE_CSS_URL' ) || ! defined( 'STWLITE_PLUGIN_DIR' )
            || false === strpos( (string) $hook_suffix, 'order-forms' ) ) {
            return;
        }

        // Mirror SaveTo Wishlist Lite's own css-mode file selection
        // (Frontend_Hooks::frontend_scripts_and_styles). Lite exposes no public
        // helper for this mapping, so it is duplicated here and guarded with the
        // file_exists() check below: if a future Lite version renames or
        // reorganises these files, the preview degrades gracefully to unstyled
        // rather than enqueuing a 404.
        $css_filenames = array(
            'full'    => 'savetowishlist-frontend-styles.css',
            'minimal' => 'savetowishlist-frontend-styles-minimal.css',
            'none'    => 'savetowishlist-frontend-styles-positions.css',
        );
        $css_mode      = get_option( 'stwlite_settings_frontend_css_mode', 'full' );
        $css_filename  = isset( $css_filenames[ $css_mode ] ) ? $css_filenames[ $css_mode ] : $css_filenames['full'];

        if ( ! file_exists( STWLITE_PLUGIN_DIR . 'assets/css/' . $css_filename ) ) {
            return;
        }

        wp_enqueue_style(
            'wwof_saveto_wishlist_editor_preview',
            STWLITE_CSS_URL . $css_filename,
            array(),
            defined( 'STWLITE_VERSION' ) ? STWLITE_VERSION : null,
            'all'
        );
    }

    /**
     * Run the SaveTo Wishlist integration hooks.
     *
     * @since 3.1.1
     */
    public function run() {

        // Lite owns the button, AJAX and assets; Pro requires Lite. Guarding on
        // Lite covers both "Lite only" and "Lite + Pro".
        if ( ! defined( 'STWLITE_VERSION' ) ) {
            return;
        }

        add_filter( 'wwof_wc_rest_prepare_product_object', array( $this, 'rest_saveto_wishlist_data_object' ), 10, 3 );
        add_filter( 'wwof_order_form_app_common_l10n_defaults', array( $this, 'wwof_app_common_l10n' ), 10 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_order_form_editor_styles' ) );
    }
}
