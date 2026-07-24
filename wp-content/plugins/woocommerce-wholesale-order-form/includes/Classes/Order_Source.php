<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Classes
 */

namespace RymeraWebCo\WWOF\Classes;

use RymeraWebCo\WWOF\Abstracts\Abstract_Class;
use RymeraWebCo\WWOF\Traits\Singleton_Trait;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Order source tracking.
 *
 * Tags orders that originated from the Wholesale Order Form so merchants
 * can distinguish them from orders placed via the standard WooCommerce
 * cart/checkout. The trail starts at add-to-cart: cart items that came
 * through the order form (either via the Store API with the
 * `X-WWOF-Add-To-Cart: 1` HTTP header or via the internal Cart_Bundle
 * endpoint) get a `_wwof_source` flag in their cart-item data. When an
 * order is created from a cart that holds at least one flagged item we
 * set `_wwof_order_source` order meta and add an order note. The admin
 * order screen surfaces the meta as a labeled indicator.
 *
 * The cart-item flag relies on standard WC cart-item persistence
 * (session, "save for later", cart token). Any third-party plugin that
 * reconstructs carts from a non-WC source (e.g. an "abandoned cart
 * recovery" plugin rebuilding the cart from saved post meta) may strip
 * custom keys and break the downstream tagging — by design we don't try
 * to re-detect the source at checkout time, since the original HTTP
 * request is gone by then.
 *
 * @since 3.1.0
 */
class Order_Source extends Abstract_Class {

    use Singleton_Trait;

    /**
     * Holds the class instance object.
     *
     * @var Order_Source $instance object
     * @since 3.1.0
     */
    protected static $instance;

    /**
     * Order meta key that identifies the order source.
     *
     * @since 3.1.0
     * @var string
     */
    const META_KEY = '_wwof_order_source';

    /**
     * Order meta value indicating the order came from the order form.
     *
     * @since 3.1.0
     * @var string
     */
    const SOURCE_VALUE = 'wholesale_order_form';

    /**
     * Cart item data key used to mark items added via the order form.
     *
     * @since 3.1.0
     * @var string
     */
    const CART_ITEM_FLAG = '_wwof_source';

    /**
     * When true, every cart item added during this PHP request is tagged
     * regardless of the request header. Set by `start_tagging()` so callers
     * like the Cart_Bundle REST endpoint can opt in even when their request
     * doesn't carry the `X-WWOF-Add-To-Cart` header.
     *
     * @since 3.1.0
     * @var bool
     */
    private static $force_tag = false;

    /**
     * Begin force-tagging cart items added during the current request.
     *
     * Mirrors the lifetime of an internal add-to-cart call dispatched by
     * a WWOF REST endpoint.
     *
     * MUST be paired with `stop_tagging()` via try/finally so the flag
     * does not leak into unrelated `add_to_cart` calls later in the same
     * request. The flag is class-level static — leaving it set silently
     * tags every subsequent cart item until the PHP request ends. See
     * `Cart_Bundle::add_bundle()` for the canonical pairing pattern.
     *
     * @since 3.1.0
     * @return void
     */
    public static function start_tagging() {

        self::$force_tag = true;
    }

    /**
     * Stop force-tagging cart items.
     *
     * @since 3.1.0
     * @return void
     */
    public static function stop_tagging() {

        self::$force_tag = false;
    }

    /**
     * Decide whether the in-flight add-to-cart call originated from WWOF.
     *
     * Two channels:
     *   1. `self::$force_tag` — set by `start_tagging()` when an internal
     *      WWOF endpoint (e.g. Cart_Bundle) is the caller. Always trusted.
     *   2. `X-WWOF-Add-To-Cart: 1` HTTP header — set by the frontend
     *      order form's axios layer on every cart-mutating call (single
     *      add, bulk "Add Selected to Cart" batch, etc.). Headers ride on
     *      the outer HTTP request and remain readable from inside Store
     *      API batch sub-requests, which is why we use a header instead of
     *      a body flag (a JSON batch body never populates `$_POST`, so the
     *      bulk path's tagging was silently dropped before this change).
     *      Gated on the request URI matching a Store API cart route so a
     *      non-WWOF client can't forge the header on an unrelated POST and
     *      have its order falsely attributed to the order form. The same
     *      URI-gating pattern is used by `FunnelKit_Cart::add_to_cart_trigger()`.
     *
     * @since 3.1.0
     * @return bool
     */
    private function is_wwof_add_to_cart() {

        if ( self::$force_tag ) {
            return true;
        }

        $header = isset( $_SERVER['HTTP_X_WWOF_ADD_TO_CART'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WWOF_ADD_TO_CART'] ) )
            : '';

        if ( '1' !== $header ) {
            return false;
        }

        // Only honour the header inside the WC Store API cart routes —
        // those are the endpoints the order form actually targets. Without
        // the URI gate any client could attach `X-WWOF-Add-To-Cart: 1` to
        // an unrelated authenticated POST and have their order falsely
        // attributed to the order form.
        $request_uri = isset( $_SERVER['REQUEST_URI'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
            : '';

        return false !== strpos( $request_uri, 'wc/store/cart/add-item' )
            || false !== strpos( $request_uri, 'wc/store/v1/cart/add-item' )
            || false !== strpos( $request_uri, 'wc/store/v1/batch' );
    }

    /**
     * Stamp WWOF-originated cart items with the source flag.
     *
     * Runs on the `woocommerce_add_cart_item_data` filter so the flag rides
     * inside the cart item array WC stores in the session. It then
     * survives until checkout creates the order, at which point we look it
     * up to decide whether to tag the order.
     *
     * @param array $cart_item_data The cart item data array.
     *
     * @since 3.1.0
     * @return array
     */
    public function tag_cart_item_data( $cart_item_data ) {

        if ( ! $this->is_wwof_add_to_cart() ) {
            return $cart_item_data;
        }

        $cart_item_data[ self::CART_ITEM_FLAG ] = self::SOURCE_VALUE;

        return $cart_item_data;
    }

    /**
     * Tag the order if any cart item carries the WWOF source flag.
     *
     * Fires on `woocommerce_checkout_create_order` for the classic
     * checkout. The blocks-based checkout takes a separate path through
     * `woocommerce_store_api_checkout_order_processed` — see
     * `maybe_tag_order_store_api()` for the wrapper that re-uses this
     * method and saves the order afterwards.
     *
     * The cart still holds its items at hook time (WC clears it after
     * order creation completes), so reading from `WC()->cart` is
     * reliable on both paths.
     *
     * @param WC_Order $order The order being created.
     *
     * @since 3.1.0
     * @return bool True when this call stamped the order with the WWOF
     *              source meta + order note; false on any short-circuit
     *              (non-WC_Order, meta already set, no cart, no flagged
     *              items). The store-api wrapper reads this to decide
     *              whether `$order->save()` is needed without re-reading
     *              the meta.
     */
    public function maybe_tag_order( $order ) {

        if ( ! $order instanceof WC_Order ) {
            return false;
        }

        // Defensive: don't overwrite a value set by another path (e.g. a
        // future REST flow that stamps the order directly).
        if ( $order->get_meta( self::META_KEY ) ) {
            return false;
        }

        if ( ! WC()->cart ) {
            return false;
        }

        $has_wwof_item = false;
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            if ( isset( $cart_item[ self::CART_ITEM_FLAG ] ) && self::SOURCE_VALUE === $cart_item[ self::CART_ITEM_FLAG ] ) {
                $has_wwof_item = true;
                break;
            }
        }

        if ( ! $has_wwof_item ) {
            return false;
        }

        $order->update_meta_data( self::META_KEY, self::SOURCE_VALUE );
        $order->add_order_note( __( 'Order placed via Wholesale Order Form.', 'woocommerce-wholesale-order-form' ) );

        return true;
    }

    /**
     * Render the source indicator on the admin order edit screen.
     *
     * Hooks `woocommerce_admin_order_data_after_order_details` which fires
     * for both the legacy post-edit screen and the HPOS-based orders
     * screen, so a single hook covers both admin layouts.
     *
     * @param WC_Order $order The order being viewed.
     *
     * @since 3.1.0
     * @return void
     */
    public function display_source_on_admin_order_page( $order ) {

        if ( ! $order instanceof WC_Order ) {
            return;
        }

        if ( self::SOURCE_VALUE !== $order->get_meta( self::META_KEY ) ) {
            return;
        }

        ?>
        <p class="form-field form-field-wide wwof-order-source">
            <strong><?php esc_html_e( 'Order Source:', 'woocommerce-wholesale-order-form' ); ?></strong>
            <span class="wwof-order-source-value"><?php esc_html_e( 'Wholesale Order Form', 'woocommerce-wholesale-order-form' ); ?></span>
        </p>
        <?php
    }

    /**
     * Register hooks.
     *
     * @since 3.1.0
     * @return void
     */
    public function run() {

        add_filter( 'woocommerce_add_cart_item_data', array( $this, 'tag_cart_item_data' ), 10, 1 );

        // Classic checkout (`WC_Checkout::create_order`). Doesn't fire for
        // the blocks-based checkout — see the store-api hook below.
        add_action( 'woocommerce_checkout_create_order', array( $this, 'maybe_tag_order' ), 10, 1 );

        // Blocks-based checkout. The Store API's checkout endpoint
        // creates orders via its own OrderController and does not fire
        // `woocommerce_checkout_create_order`, so we need the explicit
        // store-api hook to cover orders placed through the cart/checkout
        // blocks. Unlike the classic-checkout hook (where WC saves the
        // order shortly after our callback returns), the Store API does
        // NOT persist meta added at hook time automatically — the wrapper
        // `maybe_tag_order_store_api()` calls `$order->save()` explicitly
        // and must not be replaced with the bare `maybe_tag_order()`.
        add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'maybe_tag_order_store_api' ), 10, 1 );

        add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'display_source_on_admin_order_page' ), 10, 1 );
    }

    /**
     * Tag the order placed through the WC Store API (blocks checkout).
     *
     * The store-api hook fires AFTER the order is saved, so we need to
     * persist meta changes ourselves — unlike `woocommerce_checkout_create_order`
     * where WC saves the order shortly after the hook completes. Calling
     * `save()` here writes the meta and the order note in one step.
     *
     * @param WC_Order $order The order being created.
     *
     * @since 3.1.0
     * @return void
     */
    public function maybe_tag_order_store_api( $order ) {

        if ( ! $order instanceof WC_Order ) {
            return;
        }

        if ( $this->maybe_tag_order( $order ) ) {
            $order->save();
        }
    }
}
