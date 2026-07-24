<?php
/**
 * Author: Rymera Web Co.
 *
 * @package RymeraWebCo\WWOF\REST
 */

namespace RymeraWebCo\WWOF\REST;

use RymeraWebCo\WWOF\Abstracts\Abstract_REST;
use RymeraWebCo\WWOF\Classes\Order_Source;
use RymeraWebCo\WWOF\Helpers\Product_Bundles as PB_Helper;
use RymeraWebCo\WWOF\REST\Cart\Add_Bundle_Error_Formatter;
use RymeraWebCo\WWOF\Traits\Singleton_Trait;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Cart_Bundle REST endpoint.
 *
 * Wraps `WC_PB()->cart->add_bundle_to_cart()` so the order form can add a
 * bundle (parent + included children) to the cart in a single round-trip
 * using the configuration shape `bundleConfigStore` already maintains.
 * The Store API's own `bundle_configuration` payload uses an ordered array
 * of child entries; our store keys by `bundled_item_id`, so a thin custom
 * endpoint avoids a translation layer in the frontend and gives us
 * straightforward error surfacing.
 *
 * Returns the cart in Store API shape — `{ items, totals, … }` — so the
 * existing Pinia consumers in `useOrderFormStore` can drop the response in
 * verbatim. PB's `WC_PB_Store_API::extend_cart_item_schema` injects
 * `bundled_by` / `bundled_items` metadata when the cart is serialised
 * through the Store API, which is preserved by dispatching an internal
 * `GET /wc/store/v1/cart` after the add succeeds rather than constructing
 * the response shape ourselves.
 *
 * @since 3.1.0
 */
class Cart_Bundle extends Abstract_REST {

    use Singleton_Trait;

    /**
     * Holds the class instance object.
     *
     * @var Cart_Bundle $instance object
     * @since 3.1.0
     */
    protected static $instance;

    /**
     * Initialize REST routes.
     *
     * @since 3.1.0
     * @return void
     */
    public function register_plugin_routes() {

        $this->namespace = 'wwof/v3';
        $this->rest_base = 'cart/add-bundle';
        parent::register_plugin_routes();
    }

    /**
     * Permission check for the add-bundle endpoint.
     *
     * Mirrors the Store API's add-item permissions: anyone (including
     * guests) may add to the cart, with the standard `wp_rest` nonce
     * supplied by the WWOF axios layer providing CSRF protection. Per-role
     * product visibility is already enforced upstream by WWP/WWPP, so
     * authorisation logic here would be redundant.
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @since 3.1.0
     * @return bool
     */
    public function create_item_permissions_check( $request ) {

        return true;
    }

    /**
     * Register REST routes.
     *
     * @since 3.1.0
     * @return void
     */
    public function register_routes() {

        register_rest_route(
            $this->namespace,
            "/$this->rest_base",
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'add_bundle' ),
                'permission_callback' => array( $this, 'create_item_permissions_check' ),
                'args'                => array(
                    'product_id'    => array(
                        'description' => __( 'Bundle product ID.', 'woocommerce-wholesale-order-form' ),
                        'type'        => 'integer',
                        'required'    => true,
                        'minimum'     => 1,
                    ),
                    'quantity'      => array(
                        'description' => __( 'Number of bundles to add to the cart.', 'woocommerce-wholesale-order-form' ),
                        'type'        => 'integer',
                        'required'    => false,
                        'default'     => 1,
                        'minimum'     => 1,
                    ),
                    'configuration' => array(
                        'description' => __( 'Per-child bundle configuration keyed by bundled_item_id.', 'woocommerce-wholesale-order-form' ),
                        'type'        => 'object',
                        'required'    => false,
                        'default'     => array(),
                    ),
                    'error_format'  => array(
                        'description' => __( 'Optional error response shape. `structured` returns a child-level reason envelope; omit for the legacy `{ status, notices }` shape.', 'woocommerce-wholesale-order-form' ),
                        'type'        => 'string',
                        'enum'        => array( 'structured' ),
                        'required'    => false,
                    ),
                    'addon_data'    => array(
                        'description' => __( 'Optional Product Add-Ons form fields (raw `addon-{id}` / `wc_pao_post_data` key-value pairs) collected from the bundle row\'s rendered add-on UI. Bridged into $_POST around `add_bundle_to_cart()` so WC_PAO\'s existing `woocommerce_add_cart_item_data` filter populates the parent cart item\'s addon array without our endpoint having to recompute the structure.', 'woocommerce-wholesale-order-form' ),
                        'type'        => 'object',
                        'required'    => false,
                        'default'     => array(),
                    ),
                ),
            )
        );
    }

    /**
     * Add a bundle (parent + configured children) to the cart.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @since 3.1.0
     * @return WP_REST_Response|WP_Error
     */
    public function add_bundle( $request ) {

        if ( ! PB_Helper::is_active() ) {
            return new WP_Error(
                'wwof_pb_inactive',
                __( 'WooCommerce Product Bundles is not active.', 'woocommerce-wholesale-order-form' ),
                array( 'status' => 400 )
            );
        }

        if ( ! function_exists( 'WC_PB' ) ) {
            return new WP_Error(
                'wwof_pb_missing_api',
                __( 'WooCommerce Product Bundles cart API is unavailable.', 'woocommerce-wholesale-order-form' ),
                array( 'status' => 500 )
            );
        }

        $product_id    = (int) $request->get_param( 'product_id' );
        $quantity      = max( 1, (int) $request->get_param( 'quantity' ) );
        $configuration = (array) $request->get_param( 'configuration' );

        $bundle = wc_get_product( $product_id );
        if ( ! $bundle instanceof \WC_Product_Bundle ) {
            return new WP_Error(
                'wwof_invalid_bundle',
                __( 'The requested product is not a bundle.', 'woocommerce-wholesale-order-form' ),
                array( 'status' => 400 )
            );
        }

        // WC core boots cart and session for Store API requests automatically;
        // for our own REST route we have to opt in. Without this, calls to
        // `WC()->cart` end up adding the bundle to a cart that nobody else
        // can see.
        //
        // `WC_PB_Cart::bundle_add_to_cart` (the hook responsible for adding
        // child line items after the parent lands) bails early when
        // `did_action( 'woocommerce_cart_loaded_from_session' )` is false.
        // `wc_load_cart()` only initialises `WC()->cart` — it does NOT
        // trigger that action. Calling `get_cart_from_session()` explicitly
        // fires it so PB's child-add hook proceeds past the guard.
        if ( function_exists( 'wc_load_cart' ) ) {
            wc_load_cart();
        }
        if ( WC()->cart && ! did_action( 'woocommerce_cart_loaded_from_session' ) ) {
            WC()->cart->get_cart_from_session();
        }

        $parsed_configuration = $this->normalise_configuration( $bundle, $configuration );

        /*
         * Bridge Product Add-Ons form fields into `$_POST` for the duration
         * of `add_bundle_to_cart()`. WC_PAO's
         * `WC_Product_Addons_Cart::add_cart_item_data()` filter (hooked on
         * `woocommerce_add_cart_item_data`) reads add-on submissions from
         * the `$_POST` superglobal — our JSON REST endpoint bypasses the
         * normal form-submit path, so WC_PAO sees an empty $_POST and the
         * bundle's container add-ons silently drop at submit. Temporarily
         * populating `$_POST` with the addon fields the frontend collected
         * lets WC_PAO's existing pipeline produce the structured `addons`
         * array on the parent cart item without our endpoint re-implementing
         * WC_PAO's sanitization, file-handling, and price-calculation logic.
         *
         * Restored in a `finally` block immediately after the PB call so
         * the bridge cannot leak into adjacent request state or sibling
         * hooks. QA finding F7 + Issue 4 on PR #976.
         *
         * @since 3.1.0
         */
        $addon_data = (array) $request->get_param( 'addon_data' );
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- $_POST is saved/restored as a unit around `add_bundle_to_cart()` so WC_PAO can read the bridged keys. The route's own `permission_callback` is the trust boundary; treating each bridged key as a nonce target would invert the REST auth model. Restored after the PB call below and on every error path.
        $previous_post     = $_POST;
        $bridged_post_keys = array();

        /*
         * WC_PAO's `add_cart_item_data()` short-circuits when it can't
         * resolve the submitted product context. Older PAO builds key
         * that on `$_POST['add-to-cart']` matching the filter's
         * `$product_id`; without it the filter exits before iterating
         * the product's addons and the parent cart item never gets the
         * `addons` array. Mirror the two fields a native single-product
         * submit always carries so WC_PAO's gating succeeds and our
         * structured-addon array reaches the cart item.
         *
         * QA #5 on PR #976 — the addon UI captured the box selection
         * but the cart total stayed at the bundle base price ($275)
         * instead of $278.
         */
        $_POST['add-to-cart'] = $product_id;
        $_POST['quantity']    = $quantity;
        $bridged_post_keys[]  = 'add-to-cart';
        $bridged_post_keys[]  = 'quantity';

        if ( ! empty( $addon_data ) ) {
            foreach ( $addon_data as $key => $value ) {
                if ( ! is_string( $key ) || '' === $key ) {
                    continue;
                }
                // WC_PAO expects scalar/array values exactly as a form
                // submit would deliver them; reject objects to keep the
                // bridged surface safe and predictable.
                if ( is_object( $value ) ) {
                    continue;
                }

                /*
                 * Translate PHP's `name[]` array notation into a proper
                 * `$_POST` array. A native form submit's `application/x-www-form-urlencoded`
                 * parser auto-collapses `addon-box[]=yes&addon-box[]=no`
                 * into `$_POST['addon-box'] = ['yes', 'no']`, but our
                 * JSON bridge sees the literal `addon-box[]` key as a
                 * string. WC_PAO reads `$_POST['addon-' . $addon['field_name']]`
                 * (no brackets), so without this translation a
                 * multi-option checkbox addon — the standard PAO emit
                 * for any checkbox group — silently drops on bundles.
                 * QA #5 on PR #976.
                 */
                if ( substr( $key, -2 ) === '[]' ) {
                    $base_key = substr( $key, 0, -2 );
                    if ( '' === $base_key ) {
                        continue;
                    }
                    if ( ! isset( $_POST[ $base_key ] ) || ! is_array( $_POST[ $base_key ] ) ) {
                        $_POST[ $base_key ]  = array();
                        $bridged_post_keys[] = $base_key;
                    }
                    if ( is_array( $value ) ) {
                        foreach ( $value as $item ) {
                            $_POST[ $base_key ][] = wp_unslash( $item );
                        }
                    } else {
                        $_POST[ $base_key ][] = wp_unslash( $value );
                    }
                    continue;
                }

                $_POST[ $key ]       = wp_unslash( $value );
                $bridged_post_keys[] = $key;
            }
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        $previous_notice_count = count( wc_get_notices( 'error' ) );

        /*
         * The Cart_Bundle endpoint doesn't carry the WWOF signal the Store
         * API path uses (header / POST flag), so opt into the source tag
         * explicitly. WC_PB ultimately calls `WC()->cart->add_to_cart()`
         * which fires `woocommerce_add_cart_item_data` — that's where
         * `Order_Source` stamps the flag on each item.
         *
         * Wrapped in an outer try/finally so `stop_tagging()` runs on
         * every exit path — including the Throwable catch below, which
         * has its own `return` (PHP runs the finally before that return
         * propagates).
         *
         * Inner try/catch addresses QA Issue 2.2 on PR #976: PB's
         * `add_bundle_to_cart()` is documented to return a cart-item key,
         * `false`, or `WP_Error`, but its child-add hook can throw an
         * uncaught `Exception` (e.g. `WC_Data_Exception` from
         * `wc_get_product_object`) when a bundled child variation goes
         * out of stock between render and submit. Without this guard the
         * exception surfaces as WordPress's "There has been a critical
         * error" page-level notice; the catch translates it into the
         * same structured 400 envelope the controlled-failure paths
         * return so the frontend's `mapBundleError` helper renders a
         * clean child-level notification.
         */
        Order_Source::start_tagging();
        try {
            try {
                $result = WC_PB()->cart->add_bundle_to_cart( $product_id, $quantity, $parsed_configuration );
            } catch ( \Throwable $thrown ) {

                $_POST = $previous_post;

                $this->trim_error_notices_to( $previous_notice_count );

                $synthetic_error = new WP_Error(
                    'wwof_bundle_add_threw',
                    $thrown->getMessage() !== ''
                        ? wp_strip_all_tags( $thrown->getMessage() )
                        : __( 'The bundle could not be added to the cart.', 'woocommerce-wholesale-order-form' ),
                    array(
                        'status'    => 400,
                        'exception' => get_class( $thrown ),
                    )
                );

                return $this->build_add_bundle_error_response( $synthetic_error, $bundle, $request );
            }
        } finally {
            Order_Source::stop_tagging();
        }

        // Restore $_POST on every success path too so the bridged keys
        // can't leak into adjacent hooks fired LATER in the same request
        // (e.g. order-totals refresh, cart-fragments serialization).
        // Reassignment is safe because PHP's superglobal $_POST is a
        // process-local copy, not a shared structure.
        $_POST = $previous_post;
        unset( $bridged_post_keys ); // unused after this point; named only for grep-ability.

        if ( is_wp_error( $result ) ) {

            // Drain validation notices we surfaced via the WP_Error so they
            // don't leak into the next request's frontend output. Trim error
            // notices back to the count captured before our call so any
            // pre-existing entries the cart load queued before this endpoint
            // ran are preserved.
            $this->trim_error_notices_to( $previous_notice_count );

            return $this->build_add_bundle_error_response( $result, $bundle, $request );
        }

        if ( false === $result ) {
            // `WC()->cart->add_to_cart()` returns false on validation failure
            // and emits a wc_notice. Surface those as the error message.
            $error_notices = array_slice( wc_get_notices( 'error' ), $previous_notice_count );

            // Notice shape: bare string, or array with 'notice' key. Guard
            // against an unexpected array-without-'notice' shape that would
            // otherwise trigger an "Array to string conversion" notice.
            $first         = ! empty( $error_notices ) ? $error_notices[0] : '';
            $raw           = is_array( $first ) ? ( $first['notice'] ?? '' ) : (string) $first;
            $error_message = '' !== $raw
                ? wp_strip_all_tags( $raw )
                : __( 'The bundle could not be added to the cart.', 'woocommerce-wholesale-order-form' );

            $this->trim_error_notices_to( $previous_notice_count );

            $synthetic_error = new WP_Error(
                'wwof_bundle_add_failed',
                $error_message,
                array(
                    'status'  => 400,
                    'notices' => $error_notices,
                )
            );

            return $this->build_add_bundle_error_response( $synthetic_error, $bundle, $request );
        }

        // Even on a parent-success path, PB's child-add hook can queue error
        // notices for individual children that failed validation (e.g. a child
        // went out of stock between page render and add-to-cart). Trim only
        // the new entries so unrelated notices the cart load queued before
        // this endpoint ran are preserved. Future work tracked via #938's
        // follow-ups: surface partial failures in the response so the UI can
        // warn the buyer rather than reporting a clean success.
        $this->trim_error_notices_to( $previous_notice_count );

        return $this->build_cart_response( $request );
    }

    /**
     * Build the WP_Error response for an add-bundle failure, optionally in
     * the structured shape #941 will adopt as the default.
     *
     * The legacy shape — `{ status, notices }` in `data` — remains the
     * default to preserve compatibility with the #938/#939 frontend
     * callers that have already shipped. Callers that opt in via
     * `?error_format=structured` receive `{ status, reason,
     * failing_child_id, failing_child_name, notices }` so the order-form
     * UI can render a child-level notification.
     *
     * @param WP_Error           $error   The PB error (real or synthetic). Notices ride on its `data['notices']`.
     * @param \WC_Product_Bundle $bundle  The bundle product (used by the formatter to identify the failing child).
     * @param WP_REST_Request    $request The originating request (used to read the `error_format` flag).
     *
     * @since 3.1.0
     * @return WP_Error
     */
    private function build_add_bundle_error_response( $error, $bundle, $request ) {

        $error_format = (string) $request->get_param( 'error_format' );

        if ( 'structured' === $error_format ) {
            return Add_Bundle_Error_Formatter::format( $error, $bundle );
        }

        $error_data = $error->get_error_data();
        $notices    = is_array( $error_data ) && isset( $error_data['notices'] ) ? $error_data['notices'] : array();

        return new WP_Error(
            $error->get_error_code(),
            $error->get_error_message(),
            array(
                'status'  => 400,
                'notices' => $notices,
            )
        );
    }

    /**
     * Trim the WC error-notice queue back to the given count.
     *
     * `wc_clear_notices()` takes no arguments and wipes every notice type;
     * scoping the drain to error notices that this endpoint added requires
     * reading the full notice bag, replacing only the `error` slot, and
     * writing it back via `wc_set_notices()`. Non-error notices (success,
     * info) and pre-existing error entries are preserved.
     *
     * @param int $previous_count Original error-notice count to trim back to.
     *
     * @since 3.1.0
     * @return void
     */
    private function trim_error_notices_to( $previous_count ) {

        $current_error_notices = wc_get_notices( 'error' );
        if ( count( $current_error_notices ) <= $previous_count ) {
            return;
        }

        $all_notices = WC()->session ? WC()->session->get( 'wc_notices', array() ) : array();
        if ( ! is_array( $all_notices ) ) {
            $all_notices = array();
        }

        $all_notices['error'] = array_slice( $current_error_notices, 0, $previous_count );

        // Drop the `error` key entirely when empty so `wc_get_notices( 'error' )`
        // returns `array()` rather than the stale bag containing an empty
        // array (both behave the same to readers, but this keeps the session
        // payload tidy).
        if ( empty( $all_notices['error'] ) ) {
            unset( $all_notices['error'] );
        }

        wc_set_notices( $all_notices );
    }

    /**
     * Convert the WWOF-shape configuration into PB's native shape and merge
     * with PB-derived defaults.
     *
     * The frontend already emits PB's child-entry shape (`product_id`,
     * `quantity`, `optional_selected`, `variation_id`, `attributes`) so this
     * helper is mostly defensive type coercion plus a pass through
     * `parse_bundle_configuration()` which fills in any bundled_item_ids the
     * frontend omitted (and seeds the standard `optional_selected: 'no'`
     * default for optional children that arrived missing).
     *
     * @param \WC_Product_Bundle $bundle        The bundle product.
     * @param array              $configuration Raw configuration from the request.
     *
     * @since 3.1.0
     * @return array
     */
    private function normalise_configuration( $bundle, $configuration ) {

        $normalised = array();

        foreach ( $configuration as $bundled_item_id => $entry ) {

            $bundled_item_id = (int) $bundled_item_id;
            if ( $bundled_item_id <= 0 || ! is_array( $entry ) ) {
                continue;
            }

            $child = array(
                'product_id' => isset( $entry['product_id'] ) ? (int) $entry['product_id'] : 0,
                'quantity'   => isset( $entry['quantity'] ) ? max( 0, (int) $entry['quantity'] ) : 0,
            );

            if ( isset( $entry['optional_selected'] ) && in_array( $entry['optional_selected'], array( 'yes', 'no' ), true ) ) {
                $child['optional_selected'] = $entry['optional_selected'];
            }

            if ( isset( $entry['variation_id'] ) && (int) $entry['variation_id'] > 0 ) {
                $child['variation_id'] = (int) $entry['variation_id'];
            }

            if ( isset( $entry['attributes'] ) && is_array( $entry['attributes'] ) ) {
                $sanitised_attrs = array();
                foreach ( $entry['attributes'] as $attr_key => $attr_value ) {
                    $sanitised_attrs[ sanitize_text_field( (string) $attr_key ) ] = sanitize_text_field( (string) $attr_value );
                }
                $child['attributes'] = $sanitised_attrs;
            }

            $normalised[ $bundled_item_id ] = $child;
        }

        return WC_PB()->cart->parse_bundle_configuration( $bundle, $normalised, false );
    }

    /**
     * Build a Store API-shaped cart response by dispatching an internal
     * `GET /wc/store/v1/cart` request.
     *
     * Mirrors how the Store API's own add-item endpoint returns the cart
     * after a mutation, which is what the existing WWOF Pinia consumers
     * already understand. PB's `extend_cart_item_schema` injects
     * `bundled_by` / `bundled_items` on each item via the Store API's
     * extension layer, so dispatching through that layer (rather than
     * hand-rolling the response) keeps the bundle metadata intact.
     *
     * @param WP_REST_Request $request Originating request (used for cart token + nonce headers).
     *
     * @since 3.1.0
     * @return WP_REST_Response|WP_Error
     */
    private function build_cart_response( $request ) {

        $cart_request = new WP_REST_Request( 'GET', '/wc/store/v1/cart' );

        // Forward cart-token / nonce headers from the originating request so
        // the Store API resolves to the same cart session this endpoint just
        // mutated. The Store API checks both `Cart-Token` (authenticated cart
        // session) and the standard `Nonce` header.
        $forward_headers = array( 'Cart-Token', 'Nonce', 'X-WC-Store-API-Nonce' );
        foreach ( $forward_headers as $header ) {
            $value = $request->get_header( $header );
            if ( null !== $value && '' !== $value ) {
                $cart_request->set_header( $header, $value );
            }
        }

        $response = rest_do_request( $cart_request );

        if ( $response->is_error() ) {
            $error = $response->as_error();
            return new WP_Error(
                $error->get_error_code(),
                $error->get_error_message(),
                array( 'status' => 500 )
            );
        }

        // 200 mirrors the Store API's `cart/add-item` response. 201 was
        // technically defensible (we created a new cart-item resource) but
        // any frontend consumer checking `response.status === 200` for parity
        // with `addItemToCart` would see the divergence.
        return new WP_REST_Response( $response->get_data(), 200 );
    }
}
