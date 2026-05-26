<?php
namespace ACFWF\Models\BOGO;

use ACFWF\Abstracts\Abstract_BOGO_Deal;
use ACFWF\Abstracts\Abstract_Main_Plugin_Class;
use ACFWF\Abstracts\Base_Model;
use ACFWF\Helpers\Helper_Functions;
use ACFWF\Helpers\Plugin_Constants;
use ACFWF\Interfaces\Model_Interface;
use ACFWF\Models\Objects\Advanced_Coupon;
use ACFWF\Models\Objects\BOGO\Calculation;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// Exit if accessed directly.

/**
 * Model that houses the logic of extending the coupon system of woocommerce.
 * It houses the logic of handling coupon url.
 * Public Model.
 *
 * @since 1.4
 */
class Frontend extends Base_Model implements Model_Interface {
    /**
    |--------------------------------------------------------------------------
    | Class Properties
    |--------------------------------------------------------------------------
     */

    /**
     * Property that houses the model name to be used when calling publicly.
     *
     * @since 2.8
     * @access private
     * @var string
     */
    private $_model_name = 'BOGO_Frontend';

    /**
     * Property that houses the BOGO Calculation instance.
     *
     * @since 1.4
     * @access private
     * @var Calculation
     */
    private $_calculation;

    /**
     * List of products to display on coupon cart total row.
     *
     * @since 1.4
     * @access private
     * @var array
     */
    private $_price_display = array();

    /**
     * Meta key used to store the BOGO-intended price directly on the product object
     * (in-memory only, never saved to DB). The value is a float already in the active
     * currency. A priority-100 woocommerce_product_get_price filter reads this to
     * restore the correct price after WooPayments Multi-Currency re-converts it at
     * priority 99. Following the same pattern as WooPayments's own ProductAddOns
     * compatibility class (_wcpay_multi_currency_addons_converted).
     *
     * @since 4.6.8
     */
    const BOGO_LOCKED_PRICE_META_KEY = '_acfw_bogo_locked_price';

    /*
    |--------------------------------------------------------------------------
    | Class Methods
    |--------------------------------------------------------------------------
     */

    /**
     * Class constructor.
     *
     * @since 1.4
     * @access public
     *
     * @param Abstract_Main_Plugin_Class $main_plugin      Main plugin object.
     * @param Plugin_Constants           $constants        Plugin constants object.
     * @param Helper_Functions           $helper_functions Helper functions object.
     */
    public function __construct( Abstract_Main_Plugin_Class $main_plugin, Plugin_Constants $constants, Helper_Functions $helper_functions ) {
        parent::__construct( $main_plugin, $constants, $helper_functions );
        $main_plugin->add_to_all_plugin_models( $this, $this->_model_name );
        $main_plugin->add_to_public_models( $this, $this->_model_name );
    }

    /*
    |--------------------------------------------------------------------------
    | Implementation related functions.
    |--------------------------------------------------------------------------
     */

    /**
     * Restrict cart to only allow one BOGO to be applied.
     *
     * @since 4.1
     * @access public
     *
     * @param bool            $value Filter return value.
     * @param Advanced_Coupon $coupon Advanced coupon object.
     * @return string Notice markup.
     * @throws \Exception When BOGO coupon is already applied to the cart.
     */
    public function restrict_cart_to_only_one_bogo_deal( $value, $coupon ) {
        if ( $coupon->is_type( 'acfw_bogo' ) && ! empty( \WC()->cart->get_applied_coupons() ) ) {

            $calculation = Calculation::get_instance();

            if ( ! in_array( $coupon->get_code(), $calculation->get_bogo_coupon_codes(), true ) && get_option( ACFWF()->Plugin_Constants->ALLOWED_BOGO_COUPONS_COUNT, 1 ) <= count( $calculation->get_bogo_coupon_codes() ) ) {
                // Translators: %s is the coupon code.
                $message = __( 'Sorry, coupon "%s" cannot be used in conjunction with the other coupons already applied.', 'advanced-coupons-for-woocommerce-free' );
                do_action( 'acfw_restrict_allowed_bogo_coupons_error_message', $message, 100, $coupon );

                throw new \Exception( esc_html( sprintf( $message, $coupon->get_code() ) ) );
            }
        }

        return $value;
    }

    /**
     * Implement BOGO Deals for all applied coupon in the cart.
     *
     * @since 1.4
     * @since 4.1 Skip calculation when there is no BOGO Deal present to calculate.
     * @access public
     */
    public function implement_bogo_deals() {
        // Skip when there are no coupon's applied yet.
        if ( empty( \WC()->cart->get_applied_coupons() ) ) {
            return;
        }

        // create BOGO Calculation instance.
        if ( ! $this->_calculation instanceof Calculation ) {
            $this->_calculation = Calculation::get_instance();
        }

        // skip if there's no BOGO coupon or when calculation is already done.
        if ( $this->_calculation->is_calculation_done() ) {
            return;
        }

        // check if calculation is available in session and is still valid.
        if ( ! $this->_calculation->is_calculated_from_session() ) {
            // clear previous session data.
            Calculation::clear_session_data();

            foreach ( $this->_calculation->get_all_bogo_deals() as $bogo_deal ) {
                $this->_implement_bogo_deal( $bogo_deal );
            }

            // add eligible notices for deals with missing items.
            $this->_add_notice_for_eligible_deals();

            // save calculation and notices data to session.
            $this->_calculation->set_session_data();
        }

        // apply discount by adjusting cart item prices.
        if ( ! empty( $this->_calculation->get_all_entries() ) ) {
            $this->_set_matching_cart_item_deals_prices();

            // apply price of matching cart item triggers.
            if ( apply_filters( 'acfw_enable_matching_cart_triggers_prices', false ) ) {
                $this->_set_matching_cart_item_triggers_prices();
            }
        }

        // mark calculation as done.
        $this->_calculation->done_calculation();

        // display eligible for deals notices.
        $this->_display_eligible_deal_notices();
    }

    /**
     * Run BOGO implementation
     *
     * @since 1.4
     * @access private
     *
     * @param Abstract_BOGO_Deal $bogo_deal BOGO Deal object.
     */
    private function _implement_bogo_deal( Abstract_BOGO_Deal $bogo_deal ) {
        // skip if the re are no triggers or deals data.
        if ( 0 >= count( $bogo_deal->triggers ) || 0 >= count( $bogo_deal->deals ) ) {
            return;
        }

        // set current BOGO deal being processed in calculation object.
        $this->_calculation->set_bogo_deal( $bogo_deal );

        // allow 3rd party implementations for BOGO deals.
        if ( apply_filters( 'acfwf_before_implement_bogo_for_coupon', false ) ) {
            return;
        }

        do {
            $deals_fulfilled = false;

            // reset counters and temporary entries on each loop instance.
            $bogo_deal->reset_counters();

            // verify triggers with cart items eligible only for triggers.
            // For same-products, don't use trigger_only mode because trigger and deal are the same products.
            $trigger_only = 'same-products' !== $bogo_deal->deal_type;
            $this->_calculation->verify_triggers( $trigger_only );

            /**
             * Verify deal items and then verify triggers again with shared items.
             * If deal items are valid, but triggers are not, then clear the temporarily matched deal items.
             * Then reverify triggers first, and then verify deal items again.
             *
             * Note: For same-products, we skip this double verification because each product is independent.
             * The trigger and deal for each product should be evaluated separately.
             */
            if ( 'same-products' !== $bogo_deal->deal_type && $this->_calculation->verify_deals() && ! $this->_calculation->verify_triggers() ) {

                // reset counters and temp matched.
                $bogo_deal->reset_counters( 'deal' );
                $this->_calculation->clear_temp_entries( 'deal' );

                // reverify triggers, and reverify deals if triggers are valid.
                if ( $this->_calculation->verify_triggers() ) {
                    $this->_calculation->verify_deals();
                }
            } elseif ( 'same-products' === $bogo_deal->deal_type ) {
                // For same-products, simply verify deals after triggers without double verification.
                $this->_calculation->verify_deals();
            }

            // hook to run after verifying items (auto-add).
            do_action( 'acfw_bogo_after_verify_trigger_deals', $bogo_deal );

            // verify BOGO trigger conditions.
            // For same-products, we check if at least one trigger-deal pair is verified.
            // For other types, we check if all triggers are verified.
            $is_trigger_verified = ( 'same-products' === $bogo_deal->deal_type )
                ? $this->_is_same_products_trigger_verified( $bogo_deal )
                : $bogo_deal->is_trigger_verified();

            if ( $is_trigger_verified ) {

                // check if all deal items for this instance were all fulfilled.
                $deals_fulfilled = $bogo_deal->is_deal_fulfilled();

                // proccess deals that are missing in the cart (display notice for later).
                if ( ! $deals_fulfilled ) {
                    $this->_calculation->process_allowed_deals_data();
                }

                // if at least 1 deal item fulfilled, then confirm the matched triggers and deals.
                // NOTE: This is to ensure that if a BOGO Deal has no deals fulfilled, then the items verified in trigger
                // can still be used by other coupons.
                if ( $bogo_deal->has_deal_fulfilled() ) {
                    $this->_calculation->confirm_matched_triggers();
                }
            }

            // clear temporary matched entries.
            $this->_calculation->clear_temp_entries();

            // Increment run counter for the BOGO Deal.
            $bogo_deal->increment_run_counter();

            // For same-products with repeat, check if any product still has spare quantity.
            // This prevents infinite loops when some products are exhausted but others have allowed_deals > 0.
            if ( 'same-products' === $bogo_deal->deal_type && $bogo_deal->is_repeat ) {
                $has_spare       = $this->_same_products_has_spare_quantity( $bogo_deal );
                $deals_fulfilled = $deals_fulfilled && $has_spare;
            }
        } while (
            $bogo_deal->is_repeat && $deals_fulfilled
        );
    }

    /**
     * Apply discount of matching cart item deals by adjusting the price of cart line items.
     *
     * @since 1.4
     * @access private
     */
    private function _set_matching_cart_item_deals_prices() {
        foreach ( \WC()->cart->get_cart_contents() as $cart_item ) {

            $key = $cart_item['key'];

            // if cart key already present in price display, then skip.
            // this prevents discount be applied multiple times on the cart.
            if ( isset( $this->_price_display[ $key ] ) ) {
                continue;
            }

            $deals = $this->_calculation->get_entries_by_cart_item( $key, 'deal' );

            // don't proceed if there are no deal entries for the current item.
            if ( empty( $deals ) ) {
                continue;
            }

            // Get prices.
            $price            = array();
            $price['regular'] = $this->_helper_functions->get_price( $cart_item['data'], array( 'cart_item' => $cart_item ) );

            $total_discount     = 0.0;
            $total_discount_qty = 0;
            $discounted_prices  = array(); // list new prices per coupon discount and quantity.

            foreach ( $deals as $deal ) {
                $discount            = \ACFWF()->Helper_Functions->calculate_discount_by_type( $deal['discount_type'], $deal['discount'], $price['regular'] );
                $total_discount     += $discount * $deal['quantity'];
                $total_discount_qty += $deal['quantity'];

                // Cast the discount to a string if it's a float.
                $discount_key = (string) $discount;

                if ( ! isset( $discounted_prices[ $discount_key ] ) ) {
                    $discounted_prices[ $discount_key ] = array(
                        'discount' => $discount,
                        'quantity' => 0,
                    );
                }

                $discounted_prices[ $discount_key ]['quantity'] += $deal['quantity'];
            }

            // calculate new item price based on the total discount and set it.
            // NOTE: this will only be false when $discount value is 0.
            if ( (bool) $total_discount ) {
                // get BOGO Buys price.
                $price['buy'] = $this->_helper_functions->get_price(
                    $cart_item['data'],
                    array(
                        'ignore_always_use_regular_price' => 'all_valid' !== get_option( Plugin_Constants::ALWAYS_USE_REGULAR_PRICE ), // ignore always use regular price option, because BOGO Buys should always use the sale price if present.
                        'cart_item'                       => $cart_item,
                    )
                );

                // Calculate new_price, to get total price of the item.
                // new_price is the average price of the item after discount.
                $total_bogo_buy_qty = $cart_item['quantity'] - $total_discount_qty;
                $total_bogo_buy     = $price['buy'] * $total_bogo_buy_qty;
                $total_bogo_get     = ( $price['regular'] * $total_discount_qty ) - $total_discount;
                $new_price          = ( $total_bogo_buy + $total_bogo_get ) / $cart_item['quantity'];

                // Change displayed price when setting tax is set to yes and tax display cart is excl.
                if ( \wc_tax_enabled() && 'yes' === get_option( 'woocommerce_prices_include_tax' ) && 'excl' === get_option( 'woocommerce_tax_display_cart' ) ) {
                    $price['buy'] = (float) wc_get_price_excluding_tax( $cart_item['data'] );
                }
                // Lock the intended active-currency price directly on the product object
                // (in-memory only). Our priority-100 woocommerce_product_get_price filter
                // reads this meta and returns it, overriding whatever WooPayments at
                // priority 99 computed. This follows the same pattern WooPayments itself
                // uses in its WooCommerceProductAddOns compatibility class.
                $bogo_new_price = apply_filters( 'acfw_bogo_get_item_new_price', $new_price, $cart_item );
                $cart_item['data']->update_meta_data( self::BOGO_LOCKED_PRICE_META_KEY, (float) $bogo_new_price );
                $cart_item['data']->set_price( $bogo_new_price );

                // add details to $this->_price_display property price differences on cart table.
                $this->_price_display[ $key ] = array(
                    'name'              => $cart_item['data']->get_name(),
                    'price'             => $price,
                    'new_price'         => $new_price,
                    'total_discount'    => $total_discount,
                    'discounted_prices' => $discounted_prices,
                );
            }
        }
    }

    /**
     * Apply price of matching cart item triggers by adjusting of cart line items.
     *
     * @since 4.6.5
     * @access private
     */
    private function _set_matching_cart_item_triggers_prices() {
        foreach ( \WC()->cart->get_cart_contents() as $cart_item ) {

            $key = $cart_item['key'];

            // if cart key already present in price display, then skip.
            // this prevents price be applied multiple times on the cart.
            if ( isset( $this->_price_display[ $key ] ) ) {
                continue;
            }

            $triggers = $this->_calculation->get_entries_by_cart_item( $key, 'trigger' );

            // don't proceed if there are no deal triggers for the current item.
            if ( empty( $triggers ) ) {
                continue;
            }

            $price = $this->_helper_functions->get_price( $cart_item['data'], array( 'ignore_always_use_regular_price' => 'all_valid' !== get_option( Plugin_Constants::ALWAYS_USE_REGULAR_PRICE ) ) );
            $cart_item['data']->set_price( apply_filters( 'acfw_bogo_set_trigger_item_price', $price, $cart_item ) );
        }
    }

    /**
     * Reset BOGO deal item prices to their original values.
     *
     * This method is used to undo any price modifications applied by the BOGO logic
     * when conditions are not met or the coupon becomes invalid.
     * It skips items already recorded in the internal `_price_display` array to prevent
     * overwriting already discounted items, and resets the remaining deal item prices to their base value.
     *
     * @since 4.6.7
     * @access public
     */
    public function reset_bogo_deals_prices() {
        $cart_keys = array_keys( \WC()->cart->get_cart_contents() );

        // Remove _price_display entries for items that are no longer in cart.
        foreach ( $this->_price_display as $key => $data ) {
            if ( ! in_array( $key, $cart_keys, true ) ) {
                unset( $this->_price_display[ $key ] );
            }
        }

        // Reset prices for items that don't have BOGO discounts.
        foreach ( \WC()->cart->get_cart_contents() as $cart_item ) {
            $key = $cart_item['key'];

            // Skip items that have BOGO discounts.
            if ( isset( $this->_price_display[ $key ] ) ) {
                continue;
            }

            // Reset price to original for items without BOGO discounts.
            // Clear the locked-price meta so get_price() returns the WooPayments-converted
            // customer-currency price, then re-lock that value so our priority-100 filter
            // returns it unchanged on subsequent get_price() calls (preventing a second
            // WooPayments conversion of the already-converted value).
            $cart_item['data']->delete_meta_data( self::BOGO_LOCKED_PRICE_META_KEY );
            $price       = $this->_helper_functions->get_price( $cart_item['data'], array( 'ignore_always_use_regular_price' => 'all_valid' !== get_option( Plugin_Constants::ALWAYS_USE_REGULAR_PRICE ) ) );
            $reset_price = apply_filters( 'acfw_bogo_reset_deal_item_price', $price, $cart_item );
            $cart_item['data']->update_meta_data( self::BOGO_LOCKED_PRICE_META_KEY, (float) $reset_price );
            $cart_item['data']->set_price( $reset_price );
        }
    }

    /**
     * Check if same-products trigger is verified.
     * For same-products, we check if at least one product has its trigger verified.
     * This is different from regular BOGO where ALL triggers must be verified.
     *
     * @since 4.6.9
     * @access private
     *
     * @param Abstract_BOGO_Deal $bogo_deal BOGO Deal object.
     * @return bool True if at least one trigger is verified, false otherwise.
     */
    private function _is_same_products_trigger_verified( $bogo_deal ) {
        if ( 0 >= count( $bogo_deal->needed_triggers ) ) {
            return false;
        }

        // For same-products, check if at least one trigger is fully verified (needed_quantity === 0).
        foreach ( $bogo_deal->needed_triggers as $entry_id => $needed_qty ) {
            if ( 0 >= $needed_qty ) {
                return true; // At least one trigger is verified.
            }
        }

        return false;
    }

    /**
     * Check if any product in same-products BOGO still has spare quantity for repeat.
     * This checks if any cart item matching the triggers still has enough quantity for another trigger+deal.
     *
     * @since 4.6.9
     * @access private
     *
     * @param Abstract_BOGO_Deal $bogo_deal BOGO Deal object.
     * @return bool True if at least one product has spare quantity, false otherwise.
     */
    private function _same_products_has_spare_quantity( $bogo_deal ) {
        $cart_items = \WC()->cart->get_cart_contents();

        foreach ( $bogo_deal->triggers as $trigger ) {
            $trigger_qty = isset( $trigger['quantity'] ) ? absint( $trigger['quantity'] ) : 1;

            // Find matching cart items for this trigger.
            foreach ( $cart_items as $cart_item ) {
                if ( $bogo_deal->is_cart_item_match_entries( $cart_item, $trigger ) ) {
                    // Calculate spare quantity after current matched entries.
                    $spare_qty = $this->_calculation->calculate_cart_item_spare_quantity( $cart_item );

                    // Check if spare quantity is enough for at least one more trigger+deal cycle.
                    // For same-products, we need trigger_qty + deal_qty spare.
                    // Since trigger and deal quantities are usually the same, we need at least 2 * trigger_qty.
                    if ( $spare_qty > $trigger_qty ) {
                        return true; // At least one product has spare quantity for another cycle.
                    }
                }
            }
        }

        return false; // No product has spare quantity for another cycle.
    }

    /**
     * Add notice for all eligible deals.
     *
     * @since 1.4
     * @access private
     */
    private function _add_notice_for_eligible_deals() {
        foreach ( $this->_calculation->get_all_bogo_deals() as $bogo_deal ) {
            // if BOGO Deal last iteration has no fulfilled deals, then reverify triggers.
            if ( ! $bogo_deal->has_deal_fulfilled() ) {
                $this->_calculation->set_bogo_deal( $bogo_deal );
                $bogo_deal->reset_counters();

                // skip displaying notice if triggers are not verified.
                // NOTE: This means that the items that were used to verify the last iteration was used by another coupon.
                if ( ! $this->_calculation->verify_triggers( false, false ) ) {
                    continue;
                }
            }

            $coupon           = $bogo_deal->get_coupon();
            $allowed_entries  = $this->_calculation->get_entries_by_coupon( $coupon->get_code(), 'deal', 'allowed' );
            $allowed_quantity = array_sum( array_column( $allowed_entries, 'quantity' ) );

            if ( ! $allowed_quantity || ! apply_filters( 'acfw_bogo_deals_is_eligible_notice', true, $allowed_quantity, $coupon ) ) {
                continue;
            }

            $settings    = $coupon->get_bogo_notice_settings();
            $message     = isset( $settings['message'] ) && $settings['message'] ? $settings['message'] : __( 'Your current cart is eligible to redeem deals.', 'advanced-coupons-for-woocommerce-free' );
            $message     = str_replace( array( '{acfw_bogo_remaining_deals_quantity}', '{acfw_bogo_coupon_code}' ), array( $allowed_quantity, $coupon->get_code() ), $message );
            $notice_type = isset( $settings['notice_type'] ) && $settings['notice_type'] ? $settings['notice_type'] : 'notice';
            $button_url  = isset( $settings['button_url'] ) && $settings['button_url'] ? $settings['button_url'] : get_permalink( wc_get_page_id( 'shop' ) );
            $button_text = isset( $settings['button_text'] ) && $settings['button_text'] ? $settings['button_text'] : __( 'View Deals', 'advanced-coupons-for-woocommerce-free' );
            $notice_text = sprintf( '<span class="acfw-bogo-notice-text">%s <a href="%s" class="button">%s</a></span>', $message, $button_url, $button_text );

            $this->_calculation->add_notice( $notice_text, $notice_type, $coupon->get_code() );
        }
    }

    /**
     * Get eligible notices WC Blocks parsed htmlentities.
     *
     * @since 4.6.0
     * @since 4.6.1 Call reget_bogo_coupon_codes() to ensure that the coupon codes are up-to-date.
     * @access public
     *
     * @return array BOGO eligible notices WC Blocks.
     */
    public function get_eligible_deal_notices_message_wc_blocks() {
        $notices_wc_blocks = array();

        // Return empty array if calculation is not set.
        if ( ! $this->_calculation instanceof Calculation ) {
            return $notices_wc_blocks;
        }

        // This function is called in the Store API, not in the wc hooks. Therefore, it is important to call get_bogo_deals_from_cart.
        $bogo_deals = $this->_calculation->get_bogo_deals_from_cart();

        // Checking if there are any BOGO coupon codes applied.
        if ( ! empty( $bogo_deals ) ) {
            foreach ( $this->_calculation->get_notices() as $notice ) {
                $notices_wc_blocks[] = htmlentities( $notice['message'] );
            }
        }

        return $notices_wc_blocks;
    }

    /**
     * Display eligible for deals notices.
     *
     * @since 1.4
     * @access private
     */
    private function _display_eligible_deal_notices() {
        if ( ! $this->_is_display_notice() ) {
            return;
        }

        foreach ( $this->_calculation->get_notices() as $notice ) {
            wc_add_notice(
                $notice['message'],
                $notice['type'],
                array(
                    'acfw-bogo' => true,
                    'coupon'    => $notice['coupon_code'],
                )
            );
        }
    }

    /**
     * Remove all eligible for deals notices.
     *
     * @since 1.4
     * @access private
     */
    private function _remove_eligible_for_deals_notices() {
        $all_notices = wc_get_notices();

        if ( empty( $all_notices ) ) {
            return;
        }

        foreach ( $all_notices as $notice_type => $notices ) {
            $all_notices[ $notice_type ] = array_filter(
                $notices,
                function ( $n ) {
                return ! isset( $n['data']['acfw-bogo'] );
                }
            );
        }

        wc_set_notices( $all_notices );
    }

    /**
     * Display discounted price on cart price column.
     *
     * @since 1.0
     * @access public
     *
     * @param string $price_html Item price.
     * @param array  $item       Cart item data.
     * @return string Filtered item price.
     */
    public function display_discounted_price( $price_html, $item ) {
        $key               = $item['key'];
        $data              = isset( $this->_price_display[ $key ] ) ? $this->_price_display[ $key ] : array();
        $discounted_prices = isset( $data['discounted_prices'] ) ? $data['discounted_prices'] : array();

        if ( ! empty( $discounted_prices ) ) {

            // show price for undiscounted quantity.
            $undiscounted_quantity = $item['quantity'] - array_sum( array_column( $discounted_prices, 'quantity' ) );
            $price_html            = $undiscounted_quantity > 0 ? sprintf( '<span class="acfw-undiscounted-price">%s × %s</span><br />', wc_price( $data['price']['buy'] ), $undiscounted_quantity ) : '';

            // create separate line for discount and its relative quantity.
            $per_coupon_price = array_map(
                function ( $dp ) use ( $data ) {
                return sprintf( '<span class="acfw-bogo-discounted-price">%s × %s</span>', wc_price( $data['price']['regular'] - $dp['discount'] ), $dp['quantity'] );
                },
                $discounted_prices
            );

            $price_html .= implode( '<br />', $per_coupon_price );
        }

        return $price_html;
    }

    /**
     * Get BOGO discounts summary for a coupon.
     *
     * @since 4.6.0
     * @access public
     *
     * @param \WC_Coupon $coupon Coupon object.
     * @return string BOGO discounts summary.
     */
    public function get_bogo_discount_summary_for_coupon( $coupon ) {
        if ( 'acfw_bogo' !== $coupon->get_discount_type() ) {
            return '';
        }

        $discounts = $this->calculate_bogo_discounts_for_coupon( $coupon->get_code() );
        $template  = '<li><span class="label">%s x %s:</span> <span class="discount">%s</span></li>';
        $summary   = '';

        foreach ( $discounts as $discount ) {
            $item = $this->_helper_functions->get_cart_item( $discount['key'] );

            // Skip if the item is not found in the cart.
            if ( empty( $item ) || ! isset( $item['data'] ) ) {
                continue;
            }

            $summary .= sprintf( $template, $item['data']->get_name(), $discount['quantity'], wc_price( $discount['total'] * -1 ) );
        }

        return $summary ? sprintf( '<ul class="acfw-bogo-summary %s-bogo-summary" style="margin: 10px;">%s</ul>', $coupon->get_code(), $summary ) : '';
    }

    /**
     * Display BOGO discounts summary on the coupons cart total row.
     *
     * @since 1.0
     * @access public
     *
     * @param string    $coupon_html Coupon row html.
     * @param WC_Coupon $coupon      Coupon object.
     * @param string    $discount_amount_html      Discount amount html.
     * @return string Filtered Coupon row html.
     */
    public function display_bogo_discount_summary( $coupon_html, $coupon, $discount_amount_html ) {
        if ( ! is_array( $this->_price_display ) || empty( $this->_price_display ) ) {
            return $coupon_html;
        }

        // get coupon raw discount amount.
        $amount  = \WC()->cart->get_coupon_discount_amount( $coupon->get_code(), WC()->cart->display_cart_ex_tax );
        $summary = $this->get_bogo_discount_summary_for_coupon( $coupon );

        // remove coupon discount amount display if value is 0.
        if ( 0 === $amount ) {
            $coupon_html = str_replace( $discount_amount_html, '', $coupon_html );
        }

        return $coupon_html .= $summary;
    }

    /**
     * Add BOGO discounts summary to cart/checkout block.
     *
     * @since 4.6.0
     * @access public
     *
     * @param string     $summary Summary content.
     * @param \WC_Coupon $coupon Coupon object.
     * @return string
     */
    public function add_bogo_discount_summary_to_cart_checkout_block( $summary, $coupon ) {
        $summary .= $this->get_bogo_discount_summary_for_coupon( $coupon );
        return $summary;
    }

    /**
     * Save bogo discounts to order.
     *
     * @since 1.0
     * @since 4.3.3 Save total calculated BOGO discount to coupon line item meta.
     * @access public
     *
     * @param int       $order_id    Order id.
     * @param array     $posted_data Order posted data.
     * @param \WC_Order $order       Order object.
     */
    public function save_bogo_discounts_to_order( $order_id, $posted_data, $order ) {
        if ( ! is_array( $this->_price_display ) || empty( $this->_price_display ) ) {
            return;
        }

        // save overall BOGO discounts data to the order meta.
        $order->update_meta_data( Plugin_Constants::ORDER_BOGO_DISCOUNTS, array_values( $this->_price_display ) );
        $order->save_meta_data();

        $order_coupons = $order->get_items( 'coupon' );

        foreach ( $order_coupons as $order_coupon ) {
            $discounts = $this->calculate_bogo_discounts_for_coupon( $order_coupon->get_code() );

            // calculate the total discount via BOGO for coupon.
            $bogo_discount = array_reduce(
                $discounts,
                function ( $c, $d ) {
                    return $c + $d['amount'];
                },
                0.0
            );

            // save BOGO total discount to the coupon line item meta.
            $order_coupon->update_meta_data( Plugin_Constants::ORDER_COUPON_BOGO_DISCOUNT, $bogo_discount );
            $order_coupon->save_meta_data();
        }

        // clear session data.
        Calculation::clear_session_data();
    }

    /*
    |--------------------------------------------------------------------------
    | Utility Functions
    |--------------------------------------------------------------------------
     */

    /**
     * Only show notice when a new coupon is being applied or when loading the cart or checkout fragments refresh.
     *
     * @since 1.4
     * @access private
     *
     * @return bool
     */
    private function _is_display_notice() {
        // don't display notice on stripe cart details check.
        if ( isset( $_REQUEST['wc-ajax'] ) && 'wc_stripe_get_cart_details' === $_REQUEST['wc-ajax'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return false;
        }

        return $this->_helper_functions->is_cart() || $this->_helper_functions->is_checkout_fragments();
    }

    /**
     * Display BOGO eligible deal notices on classic cart pages.
     *
     * Reads notices from the BOGO session data and adds them to the WooCommerce notice
     * queue before WooCommerce outputs all notices (priority 10). This ensures BOGO
     * notices appear immediately on the current page request for classic cart and
     * without relying solely on the woocommerce_before_calculate_totals
     * timing which may fire after notices have already been printed.
     *
     * @since 4.7.2
     * @access public
     */
    public function display_bogo_notices_on_classic_pages() {
        if ( ! $this->_helper_functions->is_module( Plugin_Constants::BOGO_DEALS_MODULE ) ) {
            return;
        }

        // Check if a BOGO notice is already in the WC notice queue to avoid duplicates.
        foreach ( wc_get_notices() as $notices ) {
            foreach ( $notices as $notice ) {
                if ( isset( $notice['data']['acfw-bogo'] ) ) {
                    return;
                }
            }
        }

        // Read notices from the BOGO session data set during the most recent calculation.
        $session_data = \WC()->session ? \WC()->session->get( 'acfw_bogo_entries' ) : null;
        if ( ! is_array( $session_data ) || empty( $session_data['notices'] ) || ! is_array( $session_data['notices'] ) ) {
            return;
        }

        $allowed_notice_types = array( 'error', 'success', 'notice', 'info' );

        foreach ( $session_data['notices'] as $notice ) {
            if ( ! is_array( $notice ) || ! isset( $notice['message'], $notice['type'] ) || ! is_string( $notice['message'] ) ) {
                continue;
            }

            $type = in_array( $notice['type'], $allowed_notice_types, true ) ? $notice['type'] : 'notice';

            wc_add_notice(
                $notice['message'],
                $type,
                array(
                    'acfw-bogo' => true,
                    'coupon'    => $notice['coupon_code'] ?? '',
                )
            );
        }
    }

    /**
     * Calculate BOGO discounts for a coupon.
     *
     * @since 4.5.8
     * @access public
     *
     * @param string $coupon_code Coupon code.
     * @return array BOGO discounts.
     */
    public function calculate_bogo_discounts_for_coupon( $coupon_code ) {
        if ( ! $this->_calculation instanceof Calculation ) {
            $this->_calculation = Calculation::get_instance();
        }

        $deals     = $this->_calculation->get_entries_by_coupon( $coupon_code, 'deal' );
        $discounts = array();

        foreach ( $deals as $deal ) {
            $data              = isset( $this->_price_display[ $deal['key'] ] ) ? $this->_price_display[ $deal['key'] ] : array();
            $discounted_prices = isset( $data['discounted_prices'] ) ? $data['discounted_prices'] : array();

            $price = $data['price']['regular'];
            // Change displayed price when setting tax is set to yes and tax display cart is excl.
            if ( \wc_tax_enabled() && 'yes' === get_option( 'woocommerce_prices_include_tax' ) && 'excl' === get_option( 'woocommerce_tax_display_cart' ) ) {
                $price = $data['price']['buy'];
            }

            // calculate total discount value for matched deal item, by looping on all applied discount prices.
            $amount = \ACFWF()->Helper_Functions->calculate_discount_by_type( $deal['discount_type'], $deal['discount'], $price );
            $total  = $amount * $deal['quantity'];

            /**
             * If discount is negative, it means that the new price is greater than the regular price.
             * This happens when the price deal is using override to increase the price instead of a discount.
             * So we don't show this in the discount summary.
             */
            if ( empty( $data ) || 0 >= $total || $data['new_price'] >= $data['price'] ) {
                continue;
            }

            $discounts[] = array(
                'key'      => $deal['key'],
                'amount'   => $amount,
                'quantity' => $deal['quantity'],
                'total'    => $total,
            );
        }

        return $discounts;
    }

    /*
    |--------------------------------------------------------------------------
    | Fulfill implemented interface contracts
    |--------------------------------------------------------------------------
     */

    /**
     * Prevent WooPayments Multi-Currency from re-converting BOGO-adjusted prices.
     *
     * When BOGO sets a blended per-item price via set_price(), the value is already
     * expressed in the customer's active currency (because get_price() returns the
     * converted value when WooPayments filters are active). Allowing WooPayments to
     * convert the stored price a second time produces wrong cart totals in non-base
     * currencies. This callback returns false (skip conversion) for any product that
     * has been stamped with the BOGO_LOCKED_PRICE_META_KEY meta flag.
     *
     * @since 4.6.8
     * @access public
     *
     * @param bool       $should_convert Whether the price should be converted.
     * @param WC_Product $product        The product being evaluated.
     *
     * @return bool False when BOGO has already set the price; original value otherwise.
     */
    public function skip_bogo_price_conversion( bool $should_convert, $product ): bool {
        if ( ! $should_convert ) {
            return false;
        }
        // If BOGO has locked a price onto this product object, tell WooPayments to skip
        // conversion — the stored value is already in the customer's active currency.
        return '' === $product->get_meta( self::BOGO_LOCKED_PRICE_META_KEY, true );
    }

    /**
     * Restore the BOGO-intended price after WooPayments Multi-Currency converts it.
     *
     * WooPayments hooks woocommerce_product_get_price at priority 99 and treats the stored
     * value as base-currency, converting it to the active currency. When BOGO has already
     * set a price that is in the active currency (blended or reset), that conversion is
     * wrong. This callback runs at priority 100 (after WooPayments) and overrides the
     * converted value with the correct active-currency price that BOGO stored.
     *
     * Works alongside skip_bogo_price_conversion() as a safety net: even if the
     * WooPayments should_convert filter doesn't fire, this restores the right value.
     *
     * @since 4.6.8
     * @access public
     *
     * @param mixed      $price   The price returned by the previous filter in the chain.
     * @param WC_Product $product The product being evaluated.
     *
     * @return mixed The stored BOGO price when available; otherwise the unchanged $price.
     */
    public function restore_bogo_set_price( $price, $product ) {
        $locked = $product->get_meta( self::BOGO_LOCKED_PRICE_META_KEY, true );
        if ( is_numeric( $locked ) ) {
            return (float) $locked;
        }
        return $price;
    }

    /**
     * Execute Frontend class.
     *
     * @since 1.4
     * @access public
     * @inherit ACFWF\Interfaces\Model_Interface
     */
    public function run() {
        if ( ! $this->_helper_functions->is_module( Plugin_Constants::BOGO_DEALS_MODULE ) ) {
            return;
        }

        add_filter( 'woocommerce_coupon_is_valid', array( $this, 'restrict_cart_to_only_one_bogo_deal' ), 10, 2 );
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'implement_bogo_deals' ), apply_filters( 'acfw_bogo_implementation_priority', 11 ) );
        add_filter( 'woocommerce_cart_item_price', array( $this, 'display_discounted_price' ), 10, 2 );
        add_filter( 'woocommerce_cart_totals_coupon_html', array( $this, 'display_bogo_discount_summary' ), 10, 3 );
        add_filter( 'acfwf_cart_checkout_block_coupon_summary', array( $this, 'add_bogo_discount_summary_to_cart_checkout_block' ), 10, 2 );
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'save_bogo_discounts_to_order' ), 10, 3 );
        add_action( 'woocommerce_before_cart', array( $this, 'display_bogo_notices_on_classic_pages' ), 9 );
        // Prevent WooPayments Multi-Currency from double-converting BOGO-set prices.
        // skip_bogo_price_conversion (priority 50) asks WooPayments to skip conversion.
        // restore_bogo_set_price (priority 100) acts as a safety net: if WooPayments
        // converts anyway, we override the result with the correct active-currency value.
        add_filter( 'wcpay_multi_currency_should_convert_product_price', array( $this, 'skip_bogo_price_conversion' ), 50, 2 );
        add_filter( 'woocommerce_product_get_price', array( $this, 'restore_bogo_set_price' ), 100, 2 );
        add_filter( 'woocommerce_product_variation_get_price', array( $this, 'restore_bogo_set_price' ), 100, 2 );
    }
}
