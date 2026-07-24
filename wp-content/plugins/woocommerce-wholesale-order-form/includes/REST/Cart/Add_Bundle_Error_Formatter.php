<?php
/**
 * Author: Rymera Web Co.
 *
 * @package RymeraWebCo\WWOF\REST\Cart
 */

namespace RymeraWebCo\WWOF\REST\Cart;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Add_Bundle_Error_Formatter.
 *
 * Wraps the WP_Error returned by `WC_PB()->cart->add_bundle_to_cart()` into
 * the structured `{ code, message, data: { failing_child_id, failing_child_name, reason } }`
 * envelope #941 specifies, so the order-form frontend can render a child-level
 * notification rather than PB's bundle-level localised string.
 *
 * Used only when the originating REST request carries
 * `?error_format=structured`. The legacy pass-through shape (status + notices)
 * remains the default until #941 wires the flag flip across all callers; this
 * keeps the addition strictly additive against the existing #938/#939 shape.
 *
 * Notice → reason mapping is heuristic — PB emits its localised notices as
 * free-text strings, so the formatter does substring matching against PB's
 * documented templates and the bundle's own child titles. Anything that does
 * not match a known template falls through to `reason: 'generic'` carrying
 * PB's raw message verbatim, so an unmapped failure mode never disappears.
 *
 * @since 3.1.0
 */
class Add_Bundle_Error_Formatter {

    /**
     * The set of `reason` values surfaced in the structured envelope.
     *
     * Mirrored on the frontend by `BundleErrorCode` in
     * `bundle-error-mapper.ts`; keep the two lists in sync.
     *
     * @since 3.1.0
     * @var string[]
     */
    const REASONS = array(
        'container-out-of-stock',
        'out-of-stock-required-child',
        'out-of-stock-variation',
        'role-restriction',
        'generic',
    );

    /**
     * Format a PB add-bundle WP_Error into the structured WWOF envelope.
     *
     * The returned WP_Error always carries:
     *   - `code`    — the original PB code (or `wwof_bundle_add_failed` if none).
     *   - `message` — PB's localised string (or the first notice if message is empty).
     *   - `data`    — `{ status, reason, failing_child_id, failing_child_name, notices }`.
     *
     * @param WP_Error           $error  The original error from PB.
     * @param \WC_Product_Bundle $bundle The bundle product (for child-name lookup).
     *
     * @since 3.1.0
     * @return WP_Error
     */
    public static function format( $error, $bundle ) {

        $original_data = $error->get_error_data();
        $original_data = is_array( $original_data ) ? $original_data : array();

        $notices = isset( $original_data['notices'] ) && is_array( $original_data['notices'] )
            ? $original_data['notices']
            : array();

        $notice_texts = self::collect_notice_texts( $notices );

        $message = (string) $error->get_error_message();
        if ( '' === $message && ! empty( $notice_texts ) ) {
            $message = $notice_texts[0];
        }

        $code = (string) $error->get_error_code();
        if ( '' === $code ) {
            $code = 'wwof_bundle_add_failed';
        }

        $resolution = self::resolve_failing_child( $notice_texts, $bundle, $code );

        // Self-check the contract: any value not in self::REASONS must
        // collapse to `generic` so a stray return from `classify_*()` (or
        // a future PB code that maps to a reason we forgot to add to
        // REASONS) can't leak an unknown string to the frontend mapper.
        $reason = in_array( $resolution['reason'], self::REASONS, true ) ? $resolution['reason'] : 'generic';

        $structured = array(
            'status'             => isset( $original_data['status'] ) ? (int) $original_data['status'] : 400,
            'reason'             => $reason,
            'failing_child_id'   => $resolution['failing_child_id'],
            'failing_child_name' => $resolution['failing_child_name'],
            'notices'            => $notices,
        );

        return new WP_Error( $code, $message, $structured );
    }

    /**
     * Pull plain-text strings out of WC's notices array shape.
     *
     * `wc_get_notices( 'error' )` returns an array of either bare strings
     * (legacy) or `array( 'notice' => '...', 'data' => ... )` entries.
     * Normalise to a flat string array so downstream classification has
     * one shape to work with. HTML tags are stripped so `&quot;` and entity
     * artefacts in PB's notices don't trip up substring matches against
     * raw child titles.
     *
     * @param array $notices Raw notices array.
     *
     * @since 3.1.0
     * @return string[]
     */
    private static function collect_notice_texts( $notices ) {

        $texts = array();

        foreach ( $notices as $notice ) {
            if ( is_string( $notice ) ) {
                $texts[] = wp_strip_all_tags( html_entity_decode( $notice, ENT_QUOTES | ENT_HTML5 ) );
                continue;
            }

            if ( is_array( $notice ) && isset( $notice['notice'] ) ) {
                $texts[] = wp_strip_all_tags( html_entity_decode( (string) $notice['notice'], ENT_QUOTES | ENT_HTML5 ) );
            }
        }

        return $texts;
    }

    /**
     * Match notice texts against the bundle's children to identify the
     * failing child and classify the reason.
     *
     * Resolution priority:
     *   1. WP_Error code-based classification (locale-independent — preferred).
     *   2. Notice text substring classification (English-only — best effort).
     *   3. Child-title match attaches `failing_child_*` to whichever reason
     *      classification produced; a child match never overrides a more
     *      specific classification, and never promotes `generic` into a
     *      stock-related reason on its own.
     *
     * @param string[]           $notice_texts Notice strings to inspect.
     * @param \WC_Product_Bundle $bundle       The bundle product.
     * @param string             $error_code   The WP_Error code from PB (locale-stable).
     *
     * @since 3.1.0
     * @return array{reason:string,failing_child_id:int,failing_child_name:string}
     */
    private static function resolve_failing_child( $notice_texts, $bundle, $error_code = '' ) {

        $default = array(
            'reason'             => 'generic',
            'failing_child_id'   => 0,
            'failing_child_name' => '',
        );

        $code_reason = self::classify_by_error_code( (string) $error_code );

        if ( empty( $notice_texts ) ) {
            $default['reason'] = $code_reason;
            return $default;
        }

        $children     = self::index_children_by_title( $bundle );
        $bundle_title = is_object( $bundle ) && method_exists( $bundle, 'get_title' ) ? (string) $bundle->get_title() : '';

        foreach ( $notice_texts as $text ) {
            $reason = 'generic' !== $code_reason ? $code_reason : self::classify_reason( $text );
            $match  = self::match_child_in_text( $text, $children );

            if ( 'container-out-of-stock' === $reason ) {
                return array(
                    'reason'             => 'container-out-of-stock',
                    'failing_child_id'   => 0,
                    'failing_child_name' => '',
                );
            }

            if ( null !== $match ) {
                return array(
                    'reason'             => $reason,
                    'failing_child_id'   => $match['product_id'],
                    'failing_child_name' => $match['title'],
                );
            }

            if ( '' !== $bundle_title && self::text_contains( $text, $bundle_title ) ) {
                return array(
                    'reason'             => 'container-out-of-stock',
                    'failing_child_id'   => 0,
                    'failing_child_name' => '',
                );
            }
        }

        $default['reason'] = $code_reason;
        return $default;
    }

    /**
     * Classify by WP_Error code (locale-independent).
     *
     * PB's error codes are stable identifiers, not localised strings, so
     * code-based classification works on every store regardless of language.
     * Used as the primary signal; substring matching is a fallback for codes
     * we haven't enumerated.
     *
     * @param string $code The WP_Error code.
     *
     * @since 3.1.0
     * @return string One of the {@see self::REASONS} values.
     */
    private static function classify_by_error_code( $code ) {

        switch ( $code ) {
            case 'woocommerce_bundle_out_of_stock':
            case 'woocommerce_bundle_invalid_bundle_stock_status':
                return 'container-out-of-stock';

            case 'woocommerce_bundled_item_stock_error':
            case 'woocommerce_bundled_item_out_of_stock':
                return 'out-of-stock-required-child';

            case 'woocommerce_bundled_variation_invalid':
            case 'woocommerce_bundled_variation_out_of_stock':
                return 'out-of-stock-variation';

            default:
                return 'generic';
        }
    }

    /**
     * Classify a single notice string into a reason code via substring match.
     *
     * Fallback used only when the WP_Error code didn't resolve to a known
     * reason. PB's notice templates are localised, so this matcher is
     * English-store-only on principle — anything that doesn't match a
     * keyword falls through to `generic` and carries PB's raw message
     * rather than being mis-categorised.
     *
     * Order matters: role-restriction is checked first because PB role
     * notices can mention the word "variation" (e.g., "the chosen X
     * variation is not available for your role"), and the variation
     * keyword would otherwise win.
     *
     * @param string $text The notice string (HTML stripped).
     *
     * @since 3.1.0
     * @return string One of the {@see self::REASONS} values.
     */
    private static function classify_reason( $text ) {

        $lower = strtolower( $text );

        if (
            false !== strpos( $lower, 'restricted' ) ||
            false !== strpos( $lower, 'not available for your' ) ||
            false !== strpos( $lower, 'wholesale role' )
        ) {
            return 'role-restriction';
        }

        if ( false !== strpos( $lower, 'variation' ) ) {
            return 'out-of-stock-variation';
        }

        if (
            // Match PB's exact "is a required <field>" template (see
            // class-wc-pb-cart.php:948), not the bare "is required"
            // phrasing that role/quantity notices can also produce.
            false !== strpos( $lower, 'is a required' ) ||
            false !== strpos( $lower, 'are required' ) ||
            false !== strpos( $lower, 'cannot be purchased' ) ||
            false !== strpos( $lower, 'out of stock' )
        ) {
            return 'out-of-stock-required-child';
        }

        return 'generic';
    }

    /**
     * Build a `lowercased title => { product_id, title }` map for the bundle's
     * children, sorted longest-title first so a child whose title is a prefix
     * of another doesn't shadow the longer match.
     *
     * @param \WC_Product_Bundle $bundle The bundle product.
     *
     * @since 3.1.0
     * @return array<int, array{product_id:int, title:string, lower:string}>
     */
    private static function index_children_by_title( $bundle ) {

        if ( ! is_object( $bundle ) || ! method_exists( $bundle, 'get_bundled_items' ) ) {
            return array();
        }

        $entries = array();

        foreach ( $bundle->get_bundled_items() as $bundled_item ) {
            if ( ! is_object( $bundled_item ) ) {
                continue;
            }

            $title = method_exists( $bundled_item, 'get_raw_title' ) ? (string) $bundled_item->get_raw_title() : '';
            if ( '' === $title && method_exists( $bundled_item, 'get_title' ) ) {
                $title = (string) $bundled_item->get_title();
            }

            if ( '' === $title ) {
                continue;
            }

            $entries[] = array(
                'product_id' => method_exists( $bundled_item, 'get_product_id' ) ? (int) $bundled_item->get_product_id() : 0,
                'title'      => $title,
                'lower'      => strtolower( $title ),
            );
        }

        usort(
            $entries,
            static function ( $a, $b ) {
                return strlen( $b['lower'] ) - strlen( $a['lower'] );
            }
        );

        return $entries;
    }

    /**
     * Return the first child entry whose title appears in the given text.
     *
     * @param string                                                        $text     The notice text.
     * @param array<int, array{product_id:int, title:string, lower:string}> $children Indexed children.
     *
     * @since 3.1.0
     * @return array{product_id:int, title:string}|null
     */
    private static function match_child_in_text( $text, $children ) {

        $lower = strtolower( $text );

        // PB's notice templates consistently quote the child title with
        // ASCII quotes (`&quot;` → `"` after `html_entity_decode`):
        // `"Ergonomic Chair" cannot be added...`. Prefer the quoted form
        // so a child titled "Pen" doesn't substring-match "Open the lid".
        // Fall back to bare-title matching for non-quoted notice shapes
        // (e.g. wrapped messages from validation contexts that don't go
        // through PB's quoted template).
        //
        // ASCII-only assumption: PB sources notices from un-texturized
        // templates and WC doesn't run `wptexturize()` on notice strings
        // before queuing them, so smart quotes (U+201C/U+201D, French
        // guillemets, etc.) are not expected. If a future PB version
        // ships a smart-quoted template, the quoted matcher will silently
        // fall through to the bare-title matcher below — no hard break,
        // just the same false-positive class the quoted matcher prevents
        // for ASCII titles. Extend with `\u{201C}` / `\u{201D}` if PB
        // ever changes.
        foreach ( $children as $entry ) {
            if ( '' !== $entry['lower'] && false !== strpos( $lower, '"' . $entry['lower'] . '"' ) ) {
                return array(
                    'product_id' => $entry['product_id'],
                    'title'      => $entry['title'],
                );
            }
        }

        foreach ( $children as $entry ) {
            if ( '' !== $entry['lower'] && false !== strpos( $lower, $entry['lower'] ) ) {
                return array(
                    'product_id' => $entry['product_id'],
                    'title'      => $entry['title'],
                );
            }
        }

        return null;
    }

    /**
     * Case-insensitive substring containment test.
     *
     * @param string $haystack The haystack.
     * @param string $needle   The needle.
     *
     * @since 3.1.0
     * @return bool
     */
    private static function text_contains( $haystack, $needle ) {

        if ( '' === $needle ) {
            return false;
        }

        return false !== stripos( $haystack, $needle );
    }
}
