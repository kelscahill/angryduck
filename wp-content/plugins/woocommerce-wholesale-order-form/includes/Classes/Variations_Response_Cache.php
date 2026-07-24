<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Classes
 */

namespace RymeraWebCo\WWOF\Classes;

use RymeraWebCo\WWOF\Abstracts\Abstract_Class;
use RymeraWebCo\WWOF\Traits\Singleton_Trait;
use WC_Product;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Role-keyed whole-response cache for the WWP-owned wholesale variations endpoint.
 *
 * ## What it caches and why
 *
 * The `GET /wholesale/v1/products/{id}/variations` endpoint is registered by the
 * WooCommerce Wholesale Prices (WWP) plugin — WWOF only decorates it. Computing
 * each response is expensive: WWP and WWPP apply price callbacks, visibility
 * filters, and a chain of REST field resolvers for every variation returned. Under
 * load this endpoint is the single hottest query path in the order form. This class
 * wraps the entire JSON payload (data + status + headers) in a transient so that
 * repeated requests for the same product / role / pagination combination are served
 * from cache rather than re-running the endpoint callback.
 *
 * WWOF-only traffic is identified via the `wwof` query parameter that the frontend
 * always attaches (its value is the order-form post ID). Requests without that
 * parameter are not cached so that direct WP-Admin or REST-API-explorer calls are
 * unaffected.
 *
 * ## Hook strategy — why `rest_dispatch_request` instead of `rest_pre_dispatch`
 *
 * WordPress's REST server flow:
 *   1. `rest_pre_dispatch`      — fires BEFORE permission checks. Serving a cached
 *                                 response here would bypass the permission callback,
 *                                 which is a security regression.
 *   2. Permission callback      — the WWP endpoint gate (`current_user_can` etc).
 *   3. `rest_dispatch_request`  — fires AFTER the permission callback, BEFORE the
 *                                 route callback. Returning a non-null value from
 *                                 this filter causes WP_REST_Server to skip the
 *                                 (expensive) route callback entirely and use our
 *                                 response directly. This is the earliest safe hook
 *                                 for serving cached content.
 *   4. Route callback           — the WWP handler (runs only on a cache miss).
 *   5. `rest_post_dispatch`     — fires after the response is fully assembled.
 *                                 We capture the fresh response here (priority 20,
 *                                 after core's `rest_filter_response_fields` at 10)
 *                                 so the stored payload already reflects any
 *                                 `_fields` pruning applied by WP core.
 *
 * ## Cache key composition
 *
 * The key is an MD5 of: product ID, server-resolved role bucket, form ID, page,
 * per_page, a hash of every request param the WWP controller reads (`_fields`,
 * `orderby`, `order`, `status`, `show_meta_data`, `wholesale_role`,
 * `return_wholesale_products`, `show_categories`, and WWP's own `fields`), the
 * active currency, the resolved request locale, a per-product version counter, a
 * global version counter, and hashes of WWPP settings/category configs. This means
 * any change to the request shape or the pricing/visibility configuration produces
 * a different key, so stale data is never served — the old key simply expires after
 * the TTL. The raw `wholesale_role` param is keyed (in addition to the
 * server-resolved bucket) so a request whose param diverges from the resolved
 * bucket can never collide with — and therefore poison — the shared bucket payload.
 *
 * ## TTL and invalidation
 *
 * Transients expire after 5 minutes by default — a backstop for any edge case not
 * covered by explicit invalidation — and the TTL is filterable via
 * `wwof_variations_response_cache_ttl`. Explicit invalidation is event-driven:
 *
 * - **Per-product version bump** — triggered by WooCommerce product/variation save,
 *   stock status change, and stock level change hooks. A bump writes a fresh unique
 *   stamp to a post-meta value that is part of every cache key for that product, making
 *   all prior transients for that product unreachable (they will expire naturally within
 *   the TTL). A unique stamp (rather than a +1 counter) is used so concurrent
 *   invalidations can't lose a bump via a read-modify-write race. Stock changes —
 *   including order decrements — are covered via the
 *   `woocommerce_product_set_stock` and `woocommerce_variation_set_stock` hooks.
 *   Trash and delete are covered via the generic `trashed_post` (after-trash) /
 *   `before_delete_post` (before-delete) hooks — WooCommerce trashes products through
 *   `wp_trash_post()` and exposes no parent-level product trash/delete action — so a
 *   trashed or deleted product/variation stops serving its cached payload immediately
 *   rather than lingering until the TTL. For variations the parent's version is bumped
 *   so the parent-level cache is also invalidated.
 *
 * - **Global version bump** — triggered by `woocommerce_settings_saved`. Writing a fresh
 *   stamp to this option invalidates every cached payload site-wide (e.g. after a tax or
 *   display setting change that would alter every product's `price_html`). Note this
 *   fires for *any* settings-tab save, not just Tax/Display — a deliberate trade-off
 *   favouring guaranteed correctness over hit-rate (worst case is a rebuild). It could
 *   be narrowed to `update_option_<name>` hooks for the specific pricing options if
 *   hit-rate ever proves a problem in production.
 *
 * ## Storage footprint and concurrency trade-offs
 *
 * - **Transient storage on sites without a persistent object cache.** With WP's default
 *   (database) transient backend, each cached payload is two `wp_options` rows
 *   (`_transient_*` + `_transient_timeout_*`), and the key dimensions (product × role ×
 *   form × page × param-hash × currency × locale) multiply quickly; a 100-variation
 *   payload can run a few hundred KB. The transients carry an explicit expiry so they
 *   are correctly NOT autoloaded, but WP only purges expired rows lazily (the twice-daily
 *   `delete_expired_transients` cron / on next miss). Sites with large catalogues and no
 *   Redis/Memcached object cache should size `wp_options` accordingly and ideally enable
 *   a persistent object cache.
 *
 * - **Cache stampede is an accepted trade-off.** When a hot product's entry expires or is
 *   invalidated under concurrent load, every in-flight miss runs the live callback and
 *   writes the transient (last write wins) — redundant computation but no correctness
 *   issue. A lock/early-return-stale mitigation is intentionally out of scope here; it can
 *   be revisited if the performance epic surfaces it.
 *
 * ## Bypass conditions
 *
 * The cache is bypassed (request falls through to the live callback) when:
 *   - The request context is `edit` (e.g. Admin API explorers).
 *   - The current user has a per-user wholesale discount override meta
 *     (`wwpp_override_wholesale_discount = yes`).
 *   - The current user has a per-user tax exemption override
 *     (`wwpp_tax_exemption` set to `yes` or `no`).
 *   - WooCommerce tax is enabled, displayed inclusive, and based on a location
 *     other than the store base — in that case the displayed price depends on
 *     the customer's address and cannot be safely shared across requests.
 *   - The `wwof_variations_response_cache_bypass` filter returns true.
 *
 * ## Debug header
 *
 * When `WP_DEBUG` is true, responses carry an `X-WWOF-Cache: hit` or
 * `X-WWOF-Cache: miss` header so developers can verify caching behaviour
 * without enabling database query logging.
 *
 * @see https://github.com/Rymera-Web-Co/woocommerce-wholesale-order-form/issues/995
 *
 * @since 3.1.0
 */
class Variations_Response_Cache extends Abstract_Class {

    use Singleton_Trait;

    /**
     * Holds the class instance object.
     *
     * @var Variations_Response_Cache $instance object
     * @since 3.1.0
     */
    protected static $instance;

    /**
     * Prefix for all transient keys written by this class.
     *
     * @since 3.1.0
     * @var string
     */
    const CACHE_KEY_PREFIX = 'wwof_var_';

    /**
     * Post-meta key that stores the per-product cache version stamp.
     *
     * Writing a fresh value via {@see bump_product_version()} effectively
     * invalidates every cached response for that product without deleting the
     * underlying transients (they expire after the TTL).
     *
     * @since 3.1.0
     * @var string
     */
    const PRODUCT_VERSION_META_KEY = '_wwof_variations_cache_version';

    /**
     * Option key that stores the site-wide cache version stamp.
     *
     * Writing a fresh value invalidates every cached response across all
     * products (e.g. after a global WooCommerce settings save).
     *
     * @since 3.1.0
     * @var string
     */
    const GLOBAL_VERSION_OPTION = 'wwof_variations_cache_global_version';

    /**
     * HTTP response header name used to signal cache hit/miss when WP_DEBUG is true.
     *
     * @since 3.1.0
     * @var string
     */
    const DEBUG_HEADER = 'X-WWOF-Cache';

    /**
     * Response headers that are safe to cache and replay across a role bucket.
     *
     * The full header bag is NOT stored — replaying it to every role-bucket-mate of the
     * original requester would broadcast any user-specific header a third-party plugin
     * attaches to the response. Only this allowlist (pagination plus method/content
     * metadata the frontend relies on) is persisted; everything else is dropped.
     *
     * @since 3.1.0
     * @var string[]
     */
    const CACHEABLE_HEADERS = array( 'Allow', 'Link', 'X-WP-Total', 'X-WP-TotalPages', 'Content-Type' );

    /**
     * Tracks transient keys that were already served from cache in the current
     * request so that {@see maybe_cache_response()} does not re-store them.
     *
     * @since 3.1.0
     * @var array
     */
    private $served = array();

    /**
     * Register hooks.
     *
     * @since 3.1.0
     * @return void
     */
    public function run() {

        // Serve cached responses after permission checks but before the callback.
        add_filter( 'rest_dispatch_request', array( $this, 'maybe_serve_cached_response' ), 10, 4 );

        // Capture fresh responses for storage after core field-pruning runs.
        add_filter( 'rest_post_dispatch', array( $this, 'maybe_cache_response' ), 20, 3 );

        // Per-product invalidation: save/update events. `woocommerce_update_product`
        // and `woocommerce_update_product_variation` fire from the CRUD data store on
        // any save (REST API, programmatic `$product->save()`, importers).
        // `woocommerce_save_product_variation` is the WP Admin meta-box save and is kept
        // for completeness — it overlaps the CRUD hook for admin edits, not a gap it fills.
        add_action( 'woocommerce_update_product', array( $this, 'invalidate_by_id' ), 10, 1 );
        add_action( 'woocommerce_update_product_variation', array( $this, 'invalidate_by_id' ), 10, 1 );
        add_action( 'woocommerce_new_product_variation', array( $this, 'invalidate_by_id' ), 10, 1 );
        add_action( 'woocommerce_save_product_variation', array( $this, 'invalidate_by_id' ), 10, 1 );

        // Per-product invalidation: stock events.
        add_action( 'woocommerce_product_set_stock_status', array( $this, 'invalidate_by_id' ), 10, 1 );
        add_action( 'woocommerce_variation_set_stock_status', array( $this, 'invalidate_by_id' ), 10, 1 );
        add_action( 'woocommerce_product_set_stock', array( $this, 'invalidate_by_object' ), 10, 1 );
        add_action( 'woocommerce_variation_set_stock', array( $this, 'invalidate_by_object' ), 10, 1 );

        // Per-product invalidation: trash/delete events. WooCommerce trashes products
        // through core's `wp_trash_post()` and exposes no parent-level
        // `woocommerce_trash_product`/`woocommerce_delete_product` action, so the generic
        // post hooks are the reliable coverage for both parents and variations. They fire
        // for every post type, so `bump_product_version()` guards by post type and only
        // bumps for products/variations — unrelated post deletions are ignored.
        //
        // Trash uses `trashed_post` (fires AFTER the status flip to `trash`) rather than
        // `wp_trash_post` (fires before) so a concurrent rebuild in the gap can't re-cache
        // the still-published payload. Delete uses `before_delete_post` because its
        // after-delete counterpart (`deleted_post`) runs once the row is gone, leaving
        // `bump_product_version()` unable to resolve the post type or the parent.
        add_action( 'trashed_post', array( $this, 'invalidate_by_id' ), 10, 1 );
        add_action( 'before_delete_post', array( $this, 'invalidate_by_id' ), 10, 1 );

        // Global invalidation: settings change (price display, tax, etc.).
        add_action( 'woocommerce_settings_saved', array( $this, 'invalidate_global' ), 10, 0 );
    }

    /**
     * Return the target product ID if this is a cache-eligible WWOF variations request.
     *
     * A request is eligible when all of the following are true:
     *   - `$request` is a WP_REST_Request instance.
     *   - The HTTP method is GET (WP_REST_Server::READABLE).
     *   - The route matches `/wholesale/v1/products/{id}/variations`.
     *   - The `wwof` query parameter is truthy (the order-form post ID).
     *
     * @param mixed $request Anything; typically a WP_REST_Request from the filter.
     *
     * @since 3.1.0
     * @return int Positive product ID, or 0 when the request is not eligible.
     */
    private function get_target_product_id( $request ) {

        if ( ! $request instanceof WP_REST_Request ) {
            return 0;
        }

        if ( WP_REST_Server::READABLE !== $request->get_method() ) {
            return 0;
        }

        $matches = array();
        if ( ! preg_match( '#^/wholesale/v1/products/(\d+)/variations/?$#', $request->get_route(), $matches ) ) {
            return 0;
        }

        if ( ! $request->get_param( 'wwof' ) ) {
            return 0;
        }

        return (int) $matches[1];
    }

    /**
     * Determine whether the current request must bypass the cache entirely.
     *
     * Bypass conditions prevent stale or personalized data from being served
     * to users whose responses differ from the shared cached payload. See the
     * class-level docblock for the full rationale behind each condition.
     *
     * The `wwof_variations_response_cache_bypass` filter allows third-party
     * plugins and site-specific code to add custom bypass conditions without
     * modifying this class.
     *
     * @param WP_REST_Request $request  The current REST request.
     *
     * @since 3.1.0
     * @return bool True when the cache must be bypassed for this request.
     */
    private function should_bypass( $request ) {

        if ( 'edit' === $request->get_param( 'context' ) ) {
            return true;
        }

        $user_id = get_current_user_id();

        if ( $user_id > 0 ) {
            if ( 'yes' === get_user_meta( $user_id, 'wwpp_override_wholesale_discount', true ) ) {
                return true;
            }

            $tax_exemption = get_user_meta( $user_id, 'wwpp_tax_exemption', true );
            if ( in_array( $tax_exemption, array( 'yes', 'no' ), true ) ) {
                return true;
            }
        }

        if (
            function_exists( 'wc_tax_enabled' )
            && wc_tax_enabled()
            && 'incl' === get_option( 'woocommerce_tax_display_shop' )
            && 'base' !== get_option( 'woocommerce_tax_based_on' )
        ) {
            return true;
        }

        /**
         * Allow external code to bypass the variations response cache for a request.
         *
         * Useful for plugins that apply per-user or per-session pricing that the
         * standard bypass conditions do not cover (e.g. dynamic pricing plugins,
         * B2B quote flows, or custom role-based rules outside of WWPP).
         *
         * @param bool            $bypass  Whether to bypass the cache. Default false.
         * @param WP_REST_Request $request The current REST request.
         * @param int             $user_id The current user ID (0 for guests).
         *
         * @since 3.1.0
         */
        return (bool) apply_filters( 'wwof_variations_response_cache_bypass', false, $request, $user_id );
    }

    /**
     * Resolve a role bucket string for the current user.
     *
     * The bucket partitions the cache so that wholesale customers see their
     * own prices and retail/guest users see retail prices. The bucket is the
     * registered wholesale role key (e.g. `wholesale_customer`) for users
     * with a valid wholesale role, `retail` for logged-in users without one,
     * and `guest` for unauthenticated visitors.
     *
     * @since 3.1.0
     * @return string The role bucket identifier.
     */
    private function resolve_role_bucket() {

        if ( ! is_user_logged_in() ) {
            return 'guest';
        }

        global $wc_wholesale_prices;

        if ( is_object( $wc_wholesale_prices ) && isset( $wc_wholesale_prices->wwp_wholesale_roles ) ) {
            $roles = $wc_wholesale_prices->wwp_wholesale_roles->getUserWholesaleRole();
            $role  = is_array( $roles ) ? ( $roles[0] ?? '' ) : (string) $roles;

            if ( '' !== $role ) {
                $registered = $wc_wholesale_prices->wwp_wholesale_roles->getAllRegisteredWholesaleRoles();
                if ( is_array( $registered ) && isset( $registered[ $role ] ) ) {
                    return $role;
                }
            }
        }

        return 'retail';
    }

    /**
     * Build a deterministic cache key for the given request and product ID.
     *
     * The key is an MD5 hash of a structured array covering every dimension
     * that can produce a different response: product, role bucket, form,
     * pagination, every request param the WWP controller reads, currency,
     * request locale, per-product version, global version, and WWPP settings
     * hashes.
     *
     * @param WP_REST_Request $request    The current REST request.
     * @param int             $product_id The parent product ID.
     *
     * @since 3.1.0
     * @return string Transient key prefixed with {@see CACHE_KEY_PREFIX}.
     */
    private function build_cache_key( $request, $product_id ) {

        $fields = $request->get_param( '_fields' );
        if ( is_string( $fields ) ) {
            $fields = explode( ',', $fields );
        }
        $fields = (array) $fields;
        sort( $fields );

        $params = array(
            'fields'                    => $fields,
            'orderby'                   => (string) $request->get_param( 'orderby' ),
            'order'                     => (string) $request->get_param( 'order' ),
            'status'                    => (string) $request->get_param( 'status' ),
            'show_meta_data'            => (string) $request->get_param( 'show_meta_data' ),
            // The WWP variations controller reads these params too, and the variation
            // SET and field shape it returns depend on them (see
            // WWP_REST_Wholesale_Products_V1_Controller::query_args / get_wwp_meta_data
            // and ::filter_product_object). They must therefore be part of the key.
            //
            // `wholesale_role` in particular: the response is keyed by the
            // server-resolved role bucket below, so including the *raw* request param
            // cannot let a caller read another bucket's cache (the bucket dimension
            // keeps them apart). It instead ensures a request whose `wholesale_role`
            // diverges from the resolved bucket gets its own entry and can never
            // collide with — i.e. poison — the shared bucket payload.
            'wholesale_role'            => (string) $request->get_param( 'wholesale_role' ),
            'return_wholesale_products' => (string) $request->get_param( 'return_wholesale_products' ),
            'show_categories'           => (string) $request->get_param( 'show_categories' ),
            'wwp_fields'                => (string) $request->get_param( 'fields' ),
        );

        $key_data = array(
            'product'  => (int) $product_id,
            'role'     => $this->resolve_role_bucket(),
            // Form ID is part of the key per the issue's specified key shape
            // ({product, role, form, page, fields}). It also keeps entries isolated
            // per form so any current or future form-scoped influence on the query
            // or enrichment can never bleed across forms; the 5-min TTL bounds the
            // extra entries this costs on multi-form sites.
            'form'     => (int) $request->get_param( 'wwof' ),
            'page'     => (int) ( $request->get_param( 'page' ) ? $request->get_param( 'page' ) : 1 ),
            'per_page' => (int) ( $request->get_param( 'per_page' ) ? $request->get_param( 'per_page' ) : 10 ),
            'params'   => $this->hash_data( $params ),
            'currency' => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
            // Variation labels and price formatting are locale-dependent, so a
            // multilingual site (WPML/Polylang) must not serve one language's payload
            // for another. determine_locale() resolves the active request locale
            // (honouring the `_locale` param and language-switch cookies).
            'locale'   => determine_locale(),
            'pver'     => (string) get_post_meta( $product_id, self::PRODUCT_VERSION_META_KEY, true ),
            'gver'     => (string) get_option( self::GLOBAL_VERSION_OPTION, '0' ),
            'wwpp_s'   => (string) get_option( 'wwpp_settings_hash', '' ),
            'wwpp_c'   => (string) get_option( 'wwpp_product_cat_hash', '' ),
        );

        return self::CACHE_KEY_PREFIX . $this->hash_data( $key_data );
    }

    /**
     * Hash an array into a stable MD5 string for use in a cache key.
     *
     * `wp_json_encode()` can return `false` (e.g. malformed UTF-8 in a value), and
     * `(string) false === ''` would make every encode failure collapse to `md5('')` —
     * conflating otherwise-distinct requests. Falling back to `serialize()` on failure
     * keeps the hash discriminating. This is a defensive edge case: every value fed in
     * is a server-resolved scalar/string, so encoding effectively never fails.
     *
     * @param array $data Data to hash.
     *
     * @since 3.1.0
     * @return string 32-character MD5 hash.
     */
    private function hash_data( $data ) {

        $encoded = wp_json_encode( $data );

        if ( false === $encoded ) {
            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- hash input only, never unserialized; deterministic fallback for the rare wp_json_encode() failure.
            $encoded = serialize( $data );
        }

        return md5( $encoded );
    }

    /**
     * Serve a cached response in place of the route callback when one exists.
     *
     * Runs on `rest_dispatch_request` (priority 10), which fires after the
     * permission callback has already passed, so no capability check is
     * bypassed. Returning a non-null value causes WP_REST_Server to skip the
     * expensive route callback and use this response directly.
     *
     * @param mixed           $dispatch_result Null on initial call; non-null if another
     *                                         plugin already overrode the response.
     * @param WP_REST_Request $request         The current REST request.
     * @param string          $route           The matched route string.
     * @param array           $handler         The matched route handler array.
     *
     * @since 3.1.0
     * @return mixed Original `$dispatch_result` on miss; a WP_REST_Response on hit.
     */
    public function maybe_serve_cached_response( $dispatch_result, $request, $route, $handler ) {

        if ( null !== $dispatch_result ) {
            return $dispatch_result;
        }

        $product_id = $this->get_target_product_id( $request );
        if ( ! $product_id ) {
            return $dispatch_result;
        }

        if ( $this->should_bypass( $request ) ) {
            return $dispatch_result;
        }

        $key    = $this->build_cache_key( $request, $product_id );
        $cached = get_transient( $key );

        if ( ! is_array( $cached ) || ! isset( $cached['data'], $cached['status'] ) ) {
            return $dispatch_result;
        }

        $response = new WP_REST_Response( $cached['data'], (int) $cached['status'] );

        if ( ! empty( $cached['headers'] ) && is_array( $cached['headers'] ) ) {
            foreach ( $cached['headers'] as $header => $value ) {
                $response->header( $header, $value );
            }
        }

        $this->served[ $key ] = true;

        if ( $this->is_debug() ) {
            $response->header( self::DEBUG_HEADER, 'hit' );
        }

        return $response;
    }

    /**
     * Store a fresh REST response in the transient cache for future requests.
     *
     * Runs on `rest_post_dispatch` at priority 20 — after WP core's
     * `rest_filter_response_fields` (priority 10) — so the stored payload
     * already reflects any `_fields` pruning. Only 200 responses are cached;
     * errors are always live. Cache-hit responses (tracked via {@see $served})
     * are not re-stored.
     *
     * @param WP_REST_Response $response The REST response about to be sent.
     * @param WP_REST_Server   $server   The WP REST server instance.
     * @param WP_REST_Request  $request  The current REST request.
     *
     * @since 3.1.0
     * @return WP_REST_Response The (potentially header-augmented) response.
     */
    public function maybe_cache_response( $response, $server, $request ) {

        if ( ! $response instanceof WP_REST_Response ) {
            return $response;
        }

        $product_id = $this->get_target_product_id( $request );
        if ( ! $product_id ) {
            return $response;
        }

        if ( $this->should_bypass( $request ) ) {
            return $response;
        }

        if ( 200 !== (int) $response->get_status() ) {
            return $response;
        }

        $key = $this->build_cache_key( $request, $product_id );

        if ( isset( $this->served[ $key ] ) ) {
            return $response;
        }

        // Store only known-safe headers. Caching the full header bag and replaying it to
        // every role-bucket-mate of the original requester would broadcast any
        // user-specific header a third-party plugin might attach to the response (e.g. a
        // personalized header — or a Set-Cookie — added on a `rest_post_dispatch` filter
        // at priority < 20). The allowlist keeps the pagination/method metadata the
        // frontend relies on and drops everything else.
        $headers = array_intersect_key( $response->get_headers(), array_flip( self::CACHEABLE_HEADERS ) );

        /**
         * Filter the variations response cache TTL, in seconds.
         *
         * The 5-minute default suits most stores; a sleepy B2B catalogue can run longer
         * for a higher hit-rate, while a high-edit store (or one with plugins that change
         * prices without firing the documented invalidation hooks) may want it shorter.
         *
         * @param int             $ttl        Cache lifetime in seconds. Default 5 minutes.
         * @param WP_REST_Request $request    The current REST request.
         * @param int             $product_id The parent product ID.
         *
         * @since 3.1.0
         */
        $ttl = (int) apply_filters( 'wwof_variations_response_cache_ttl', 5 * MINUTE_IN_SECONDS, $request, $product_id );

        set_transient(
            $key,
            array(
                'data'    => $response->get_data(),
                'status'  => (int) $response->get_status(),
                'headers' => $headers,
            ),
            // Guard against a filter returning 0, which set_transient() treats as "no
            // expiry" — a non-expiring stale entry is worse than a short-lived one.
            max( 1, $ttl )
        );

        if ( $this->is_debug() ) {
            $response->header( self::DEBUG_HEADER, 'miss' );
        }

        return $response;
    }

    /**
     * Invalidate cached responses for a product identified by its post ID.
     *
     * Accepts both parent product IDs and variation IDs; for variations the
     * parent's version is bumped so the parent-level cache is also cleared.
     *
     * @param int $id Post ID of the product or variation to invalidate.
     *
     * @since 3.1.0
     * @return void
     */
    public function invalidate_by_id( $id ) {

        $this->bump_product_version( $id );
    }

    /**
     * Invalidate cached responses for a product identified by its WC_Product object.
     *
     * Convenience wrapper for hooks that pass a WC_Product instead of a raw ID.
     *
     * @param WC_Product $product The product or variation object to invalidate.
     *
     * @since 3.1.0
     * @return void
     */
    public function invalidate_by_object( $product ) {

        if ( $product instanceof WC_Product ) {
            $this->bump_product_version( $product->get_id() );
        }
    }

    /**
     * Invalidate all cached responses site-wide.
     *
     * Writes a fresh version stamp to the global option so that every existing
     * transient key becomes unreachable. Triggered by `woocommerce_settings_saved`.
     *
     * @since 3.1.0
     * @return void
     */
    public function invalidate_global() {

        update_option( self::GLOBAL_VERSION_OPTION, $this->next_version_stamp() );
    }

    /**
     * Write a fresh per-product version stamp to invalidate its cache entries.
     *
     * For variation IDs the parent product's stamp is written so parent-level
     * cache keys — which are what the variations endpoint is indexed under — are
     * also invalidated. The post type is checked so that the generic
     * `trashed_post`/`before_delete_post` hooks (which fire for every post type)
     * only touch products and variations — deleting an unrelated post is ignored.
     *
     * @param int $id Post ID of the product or variation.
     *
     * @since 3.1.0
     * @return void
     */
    private function bump_product_version( $id ) {

        $id = (int) $id;
        if ( $id <= 0 ) {
            return;
        }

        $post_type = get_post_type( $id );

        if ( 'product_variation' === $post_type ) {
            $parent = (int) wp_get_post_parent_id( $id );
            if ( $parent > 0 ) {
                $id = $parent;
            }
        } elseif ( 'product' !== $post_type ) {
            // Not a WC product or variation (e.g. a page/post hitting the generic
            // trash/delete hooks) — nothing to invalidate.
            return;
        }

        update_post_meta( $id, self::PRODUCT_VERSION_META_KEY, $this->next_version_stamp() );
    }

    /**
     * Generate a fresh, unique cache-version stamp.
     *
     * Used in place of a read-modify-write counter (`get → +1 → update`): generating a
     * unique value per call means concurrent invalidations (e.g. a parallel product
     * import, or an order decrement racing an admin save) cannot lose a bump by both
     * reading the same value and writing the same `+1`. Any value that differs from the
     * stored one invalidates the affected keys, which is all that is required — the
     * stamp is treated as an opaque string in the cache key, never compared or ordered.
     *
     * @since 3.1.0
     * @return string Unique version stamp.
     */
    private function next_version_stamp() {

        return (string) ( time() . wp_rand( 0, 9999 ) );
    }

    /**
     * Whether debug mode is active for the current request.
     *
     * When true, responses are decorated with an X-WWOF-Cache header indicating
     * whether the payload came from the transient cache or from a live callback.
     *
     * @since 3.1.0
     * @return bool
     */
    private function is_debug() {

        return defined( 'WP_DEBUG' ) && WP_DEBUG;
    }
}
