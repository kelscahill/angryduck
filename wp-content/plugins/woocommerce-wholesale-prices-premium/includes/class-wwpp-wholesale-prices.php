<?php

use Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry;
use Automattic\WooCommerce\StoreApi\Routes\V1\Cart;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Model that houses the logic wholesale back orders.
 *
 * @since 1.12.8
 */
class WWPP_Wholesale_Prices {

    /**
     * Action Scheduler hook name for a single scheduled-wholesale-sales batch.
     *
     * @since 2.1.0
     * @var string
     */
    const SCHEDULED_SALES_BATCH_ACTION = 'wwpp_scheduled_wholesale_sales_batch';

    /**
     * Action Scheduler group for scheduled-wholesale-sales batches.
     *
     * @since 2.1.0
     * @var string
     */
    const SCHEDULED_SALES_GROUP = 'wwpp_scheduled_wholesale_sales';

    /**
     * Default number of products processed per scheduled-wholesale-sales batch.
     *
     * @since 2.1.0
     * @var int
     */
    const SCHEDULED_SALES_CHUNK_SIZE = 100;

    /**
     * Property that holds the single main instance of WWPP_Wholesale_Prices.
     *
     * @since  1.12.8
     * @access private
     * @var WWPP_Wholesale_Prices
     */
    private static $_instance;

    /**
     * Model that houses the logic of retrieving information relating to wholesale role/s of a user.
     *
     * @since  1.16.0
     * @access private
     * @var WWPP_Wholesale_Roles
     */
    private $_wwpp_wholesale_roles;

    /**
     * Model that houses the logic of retrieving information relating to tax.
     *
     * @since  1.9
     * @access private
     * @var WWPP_Tax
     */
    private $_wwpp_tax;


    /**
     * Calculate the cart total before it's needed .
     *
     * @var int
     */
    public $preload_calculate_cart_total = 0;

    /**
     * Holds the quantity discount table markup of the main product when it is repositioned away from the price block.
     *
     * @since 2.1.0
     *
     * @var string
     */
    private $repositioned_quantity_discount_table_html = '';

    /**
     * Memoized quantity discount table position for the current request.
     *
     * @since 2.1.0
     *
     * @var string|null
     */
    private $quantity_discount_table_position = null;

    /*
    |--------------------------------------------------------------------------
    | Class Methods
    |--------------------------------------------------------------------------
     */

    /**
     * WWPP_Wholesale_Prices constructor.
     *
     * @param array $dependencies Array of instance objects of all dependencies of WWPP_Wholesale_Prices model.
     *
     * @since  1.16.0
     * @access public
     */
    public function __construct( $dependencies = array() ) {

        $this->_wwpp_wholesale_roles = $dependencies['WWPP_Wholesale_Roles'];
        $this->_wwpp_tax             = $dependencies['WWPP_Tax'];
    }

    /**
     * Ensure that only one instance of WWPP_Wholesale_Prices is loaded or can be loaded (Singleton Pattern).
     *
     * @param array $dependencies Array of instance objects of all dependencies of WWPP_Wholesale_Prices model.
     *
     * @since      1.12.8
     * @return WWPP_Wholesale_Prices
     * @deprecated Deprecated on 1.16.0
     * @access     public
     */
    public static function getInstance( $dependencies = array() ) {

        if ( ! self::$_instance instanceof self ) {
            self::$_instance = new self( $dependencies );
        }

        return self::$_instance;
    }

    /**
     * Ensure that only one instance of WWPP_Wholesale_Prices is loaded or can be loaded (Singleton Pattern).
     *
     * @param array $dependencies Array of instance objects of all dependencies of WWPP_Wholesale_Prices model.
     *
     * @since  1.16.0
     * @access public
     *
     * @return WWPP_Wholesale_Prices
     */
    public static function instance( $dependencies = array() ) {

        if ( ! self::$_instance instanceof self ) {
            self::$_instance = new self( $dependencies );
        }

        return self::$_instance;
    }

    /**
     * Get curent user wholesale role.
     *
     * @since  1.16.0
     * @access private
     *
     * @return string User role string or empty string.
     */
    private function _get_current_user_wholesale_role() {

        $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

        $wholesale_role = ( is_array( $user_wholesale_role ) && ! empty( $user_wholesale_role ) ) ? $user_wholesale_role[0] : '';

        return apply_filters( 'wwpp_get_current_wholesale_role', $wholesale_role );
    }

    /*
    |-------------------------------------------------------------------------------------------------------------------
    | Per Product Level Order Qty Wholesale Discount
    |-------------------------------------------------------------------------------------------------------------------
     */

    /**
     * Display quantity based discount markup on single product pages.
     *
     * @see    _print_wholesale_price_order_quantity_table
     *
     * @param string     $wholesale_price_html       Wholesale price html.
     * @param string     $price                      Active price html( non wholesale ).
     * @param WC_Product $product                    WC_Product object.
     * @param array      $user_wholesale_role        Array user wholesale roles.
     * @param string     $wholesale_price_title_text Wholesale price title text.
     * @param string     $raw_wholesale_price        Raw wholesale price.
     * @param string     $source                     Source of the wholesale price being applied.
     *
     * @since  1.6.0
     * @since  1.7.0 Add Aelia currency switcher plugin integration
     * @since  1.16.0
     * Renamed from 'displayOrderQuantityBasedWholesalePricing' to
     * 'render_per_product_level_order_quantity_based_wholesale_discount_table_markup'. Refactor codebase.
     * @since  1.19   If quantity based pricing is enabled via variable level then show the markup discount table else
     *         show the variation. (WWPP-592).
     * @since  1.30.3 Fix qty based pricing not respecting 'Always use regular price' setting.
     *               Fix price not based on wholesale sale price if wholesale sale price is set.
     * @since  2.1.0 Stash the table markup instead of appending it when a non-default table position is configured.
     * @access public
     * @return string Filtered wholesale price html.
     */
    public function render_per_product_level_order_quantity_based_wholesale_discount_table_markup( $wholesale_price_html, $price, $product, $user_wholesale_role, $wholesale_price_title_text, $raw_wholesale_price, $source ) {

        // Only apply this to single product pages and proper ajax request
        // When a variable product have lots of variations, WC will not load variation data on variable product page load on front end
        // Instead it will load variations data as you select them on the variations select box
        // We need to support this too.
        if (
            ! empty( $user_wholesale_role ) &&
            (
                (
                    get_option( 'wwpp_settings_hide_quantity_discount_table', false ) !== 'yes' &&
                    ( is_product() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) &&
                    in_array( WWP_Helper_Functions::wwp_get_product_type( $product ), array( 'simple', 'composite', 'bundle', 'variation' ), true )
                ) ||
                apply_filters( 'render_order_quantity_based_wholesale_pricing', false )
            )
        ) {

            // condition check for WWOF.
            if ( apply_filters( 'wwof_hide_table_on_wwof_form', false ) ) {
                return $wholesale_price_html;
            }

            $use_regular_price = get_option( 'wwpp_settings_explicitly_use_product_regular_price_on_discount_calc' );

            // Make sure that wholesale price being applied is per product level.
            if ( ! empty( $raw_wholesale_price ) && 'per_product_level' === $source ) {

                $enabled = 'no';

                if ( $product->is_type( 'variation' ) ) {
                    $enabled = get_post_meta( $product->get_parent_id(), WWPP_POST_META_ENABLE_QUANTITY_DISCOUNT_RULE, true );
                }

                // If the variable qty based discount is enabled we use its mapping else we use the per variation mapping.
                if ( 'yes' === $enabled ) {

                    $mapping = get_post_meta( $product->get_parent_id(), WWPP_POST_META_QUANTITY_DISCOUNT_RULE_MAPPING, true );

                } else {

                    $enabled = $product->get_meta( WWPP_POST_META_ENABLE_QUANTITY_DISCOUNT_RULE, true );
                    $mapping = $product->get_meta( WWPP_POST_META_QUANTITY_DISCOUNT_RULE_MAPPING, true );

                }

                if ( ! is_array( $mapping ) ) {
                    $mapping = array();
                }

                // Table view.
                $mapping_table_html = '';

                if ( 'yes' === $enabled && ! empty( $mapping ) ) {
                    ob_start();

                    /*
                     * Get the base currency mapping. The base currency mapping well determine what wholesale
                     * role and range pairing a product has wholesale price with.
                     */
                    $base_currency_mapping = $this->_get_base_currency_mapping( $mapping, $user_wholesale_role );
                    $wholesale_sale_price  = self::get_product_wholesale_sale_price( $product->get_id(), $user_wholesale_role );
                    $quantity_based_price  = ! empty( $wholesale_sale_price ) && $wholesale_sale_price['is_on_sale'] ? $wholesale_sale_price['wholesale_sale_price'] : $raw_wholesale_price;

                    if ( 'yes' === $use_regular_price ) {
                        $quantity_based_price = $product->get_regular_price();
                    }

                    $quantity_based_price = apply_filters( 'wwpp_quantity_based_discount_base_price', $quantity_based_price, $mapping, $raw_wholesale_price, $product, $user_wholesale_role );

                    if ( ! empty( $base_currency_mapping ) ) {

                        if ( WWP_ACS_Integration_Helper::aelia_currency_switcher_active() ) {

                            $base_currency   = WWP_ACS_Integration_Helper::get_product_base_currency( $product->get_id() );
                            $active_currency = get_woocommerce_currency();

                            if ( $base_currency === $active_currency ) {
                                /*
                                 * If active currency is equal to base currency, then we just need to pass
                                 * the base currency mapping.
                                 */
                                $this->_print_wholesale_price_order_quantity_table( $quantity_based_price, $base_currency_mapping, array(), $mapping, $product, $user_wholesale_role, true, $base_currency, $active_currency );

                            } else {

                                $specific_currency_mapping = $this->_get_specific_currency_mapping( $mapping, $user_wholesale_role, $active_currency, $base_currency_mapping );

                                $this->_print_wholesale_price_order_quantity_table( $quantity_based_price, $base_currency_mapping, $specific_currency_mapping, $mapping, $product, $user_wholesale_role, false, $base_currency, $active_currency );

                            }
                        } else {

                            // Default without Aelia currency switcher plugin.
                            $this->_print_wholesale_price_order_quantity_table( $quantity_based_price, $base_currency_mapping, array(), $mapping, $product, $user_wholesale_role, true, get_woocommerce_currency(), get_woocommerce_currency() );

                        }
                    }

                    $mapping_table_html = ob_get_clean();

                }

                /*
                 * When the merchant chooses to display the table somewhere other than the price block, stash the
                 * markup for the main product so a dedicated single-product-summary action can render it at the
                 * configured position. Related/upsell products and ajax (variation) requests keep appending so the
                 * default layout and the JS-driven variable product flow are not affected.
                 */
                if ( 'after_price' !== $this->get_quantity_discount_table_position() && $this->is_main_single_product( $product ) ) {
                    $this->repositioned_quantity_discount_table_html = $mapping_table_html;
                } else {
                    $wholesale_price_html .= $mapping_table_html;
                }
            }
        }

        return $wholesale_price_html;
    }

    /**
     * Check whether the given product is the main product being viewed on the current single product page.
     *
     * Used to decide whether the quantity discount table should be repositioned. Only the queried product
     * is repositioned; related/upsell products and ajax (variation) requests keep their default markup.
     *
     * @since 2.1.0
     *
     * @param WC_Product $product WC_Product object.
     *
     * @return bool True when the product is the queried single product, false otherwise.
     */
    private function is_main_single_product( $product ) {

        if ( ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ! is_product() || ! $product instanceof WC_Product ) {
            return false;
        }

        return WWP_Helper_Functions::wwp_get_product_id( $product ) === get_queried_object_id();
    }

    /**
     * Get the configured position for the quantity discount table on the single product page.
     *
     * @since 2.1.0
     *
     * @return string One of 'after_price', 'after_short_description' or 'before_add_to_cart'.
     */
    public function get_quantity_discount_table_position() {

        if ( null !== $this->quantity_discount_table_position ) {
            return $this->quantity_discount_table_position;
        }

        $position        = get_option( 'wwpp_settings_quantity_discount_table_position', 'after_price' );
        $valid_positions = array( 'after_price', 'after_short_description', 'before_add_to_cart' );

        if ( ! in_array( $position, $valid_positions, true ) ) {
            $position = 'after_price';
        }

        $this->quantity_discount_table_position = apply_filters( 'wwpp_quantity_discount_table_position', $position );

        return $this->quantity_discount_table_position;
    }

    /**
     * Get the woocommerce_single_product_summary priority used to render the repositioned quantity discount table.
     *
     * The short description renders at priority 20 and the add to cart button at priority 30, so the table is
     * rendered between them depending on the chosen position.
     *
     * @since 2.1.0
     *
     * @param string $position The configured table position.
     *
     * @return int The hook priority.
     */
    private function get_quantity_discount_table_position_priority( $position ) {

        $priority = 'before_add_to_cart' === $position ? 25 : 21;

        return (int) apply_filters( 'wwpp_quantity_discount_table_position_priority', $priority, $position );
    }

    /**
     * Register the single product summary hook that renders the repositioned quantity discount table.
     *
     * Hooked on `wp` rather than at plugin include time so the configured position (and the
     * wwpp_quantity_discount_table_position filter) is read after themes and plugins have registered their
     * filter callbacks on the usual hooks. Does nothing when the table is left at its default position.
     *
     * @since 2.1.0
     *
     * @return void
     */
    public function register_repositioned_quantity_discount_table_hook() {

        $position = $this->get_quantity_discount_table_position();

        if ( 'after_price' === $position ) {
            return;
        }

        add_action(
            'woocommerce_single_product_summary',
            array( $this, 'render_repositioned_quantity_discount_table' ),
            $this->get_quantity_discount_table_position_priority( $position )
        );
    }

    /**
     * Render the quantity discount table at the configured position on the single product page.
     *
     * For simple, composite and bundle products the stashed markup is rendered directly. For variable products an
     * empty positioning container is rendered so the frontend script can move the variation table into place once a
     * variation is selected.
     *
     * @since 2.1.0
     *
     * @return void
     */
    public function render_repositioned_quantity_discount_table() {

        // Mirror the positioning script enqueue, which only loads for wholesale users. Without this guard a
        // variable product would emit an empty positioning container for every visitor, including guests, even
        // though the script that moves the table into it never loads for non-wholesale users.
        if ( empty( $this->_wwpp_wholesale_roles->getUserWholesaleRole() ) ) {
            return;
        }

        $product = wc_get_product( get_queried_object_id() );

        if ( ! $product instanceof WC_Product ) {
            return;
        }

        $is_variable = $product->is_type( 'variable' );

        // Nothing to render for non-variable products without a stashed table.
        if ( ! $is_variable && '' === $this->repositioned_quantity_discount_table_html ) {
            return;
        }

        echo '<div class="wwpp-quantity-discount-table-position">';
        // Plugin-generated markup mirroring the default wwp_filter_wholesale_price_html output; not request-derived.
        echo $this->repositioned_quantity_discount_table_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plugin-generated markup mirroring the default wholesale price HTML output; not request-derived.
        echo '</div>';

        $this->repositioned_quantity_discount_table_html = '';
    }

    /**
     * Print wholesale pricing per order quantity table.
     *
     * @see    render_per_product_level_order_quantity_based_wholesale_discount_table_markup
     *
     * @param string     $wholesale_price           Wholesale price.
     * @param array      $base_currency_mapping     Base currency mapping.
     * @param array      $specific_currency_mapping Specific currency mapping.
     * @param array      $mapping                   Mapping.
     * @param WC_Product $product                   Product.
     * @param string[]   $user_wholesale_role       User wholesale role.
     * @param bool       $is_base_currency          Is base currency.
     * @param string     $base_currency             Base currency.
     * @param string     $active_currency           Active currency.
     *
     * @since  1.7.0
     * @since  1.7.1 Apply taxing on the wholesale price on the per order quantity wholesale pricing table.
     * @since  1.16.0
     *         Rename from '_printWholesalePricePerOrderQuantityTable' to '_print_wholesale_price_order_quantity_table'.
     *         Refactor codebase.
     * @since  1.16.4 Bug fix not able to set percent discount for non base currency (WWPP-570).
     * @since  1.27.9 Replace round with wc_format_decimal function
     * @access private
     */
    private function _print_wholesale_price_order_quantity_table( $wholesale_price, $base_currency_mapping, $specific_currency_mapping, $mapping, $product, $user_wholesale_role, $is_base_currency, $base_currency, $active_currency ) {

        $desc = WWP_Helper_Functions::wwp_get_product_type( $product ) === 'variation' ? __( 'Quantity based discounts available based on how many of this variation are in your cart.', 'woocommerce-wholesale-prices-premium' ) : __( 'Quantity based discounts available based on how many of this product are in your cart.', 'woocommerce-wholesale-prices-premium' );

        $headers = apply_filters(
            'wwpp_quantity_based_discount_headers',
            array(
                'qty'   => __( 'Qty', 'woocommerce-wholesale-prices-premium' ),
                'price' => __( 'Price', 'woocommerce-wholesale-prices-premium' ),
                'save'  => __( 'Save', 'woocommerce-wholesale-prices-premium' ),
            ),
            'per_product_level'
        );

        // Description.
        $qty_table = '<div class="qty-based-discount-table-description">';

        $qty_table .= '<p class="desc">' . apply_filters( 'wwpp_per_product_level_qty_discount_table_desc', $desc ) . '</p>';
        $qty_table .= '</div>';

        // Qty Table.
        $qty_table .= '<table class="order-quantity-based-wholesale-pricing-view table-view" data-wholesale_price="' . $wholesale_price . '" data-product_quantity_mapping="' . htmlspecialchars( wp_json_encode( $mapping ), ENT_QUOTES, 'UTF-8' ) . '">';
        $qty_table .= '<thead>';
        $qty_table .= '<tr>';

        do_action( 'wwpp_action_before_wholesale_price_table_per_order_quantity_heading_view', $mapping, $product, $user_wholesale_role );

        // Headers.
        foreach ( $headers as $header ) {
            $qty_table .= '<th>' . $header . '</th>';

        }

        do_action( 'wwpp_action_after_wholesale_price_table_per_order_quantity_heading_view', $mapping, $product, $user_wholesale_role );

        $qty_table .= '</tr>';
        $qty_table .= '</thead>';
        $qty_table .= '<tbody>';

        if ( ! $is_base_currency ) {

            // Specific currency.
            foreach ( $base_currency_mapping as $base_map ) {

                /**
                 * Even if this is a not a base currency, we will still rely on the base currency "RANGE".
                 * Because some range that are present on the base currency, may not be present in this current currency.
                 * But this current currency still has a wholesale price for that range, its wholesale price will be derived
                 * from base currency wholesale price by converting it to this current currency.
                 *
                 * Also if a wholesale price is set for this current currency range ( ex. 10 - 20 ) but that range
                 * is not present on the base currency mapping. We don't recognize this specific product on this range
                 * ( 10 - 20 ) as having wholesale price. User must set wholesale price on the base currency for the
                 * 10 - 20 range for this to be recognized as having a wholesale price.
                 */

                $qty  = $base_map['start_qty'];
                $save = '';

                if ( ! empty( $base_map['end_qty'] ) ) {
                    $qty .= ' - ' . $base_map['end_qty'];
                } else {
                    $qty .= '+';
                }

                $price = '';

                /**
                 * First check if a price is set for this wholesale role : range pair in the specific currency mapping.
                 * If wholesale price is present, then use it.
                 */
                foreach ( $specific_currency_mapping as $specific_map ) {

                    if ( $specific_map[ $active_currency . '_start_qty' ] === $base_map['start_qty'] && $specific_map[ $active_currency . '_end_qty' ] === $base_map['end_qty'] ) {

                        if ( isset( $specific_map[ $active_currency . '_price_type' ] ) ) {

                            if ( 'fixed-price' === $specific_map[ $active_currency . '_price_type' ] ) {
                                $price = WWP_Helper_Functions::wwp_formatted_price( $specific_map[ $active_currency . '_wholesale_price' ], array( 'currency' => $active_currency ) );
                            } elseif ( 'percent-price' === $specific_map[ $active_currency . '_price_type' ] ) {

                                $price = $wholesale_price - ( ( $specific_map[ $active_currency . '_wholesale_price' ] / 100 ) * $wholesale_price );

                                /**
                                 * No need to use
                                 * $price = WWP_Helper_Functions::wwp_formatted_price( $price  , array( 'currency' => $active_currency ) );
                                 * to convert the price because the $wholesale_price variable is already using the converted wholesale price
                                 * WWPP-558
                                 */

                            }
                        } else {
                            $price = WWP_Helper_Functions::wwp_formatted_price( $specific_map[ $active_currency . '_wholesale_price' ], array( 'currency' => $active_currency ) );
                        }
                    }
                }

                /**
                 * Now if there is no mapping for this specific wholesale role : range pair inn the specific currency mapping,
                 * since this range is present on the base map mapping. We derive the price by converting the price set on the
                 * base currency mapping to this active currency.
                 */
                if ( ! $price ) {

                    if ( isset( $base_map['price_type'] ) ) {

                        if ( 'fixed-price' === $base_map['price_type'] ) {

                            $map_wholesale_price = wc_format_decimal( $base_map['wholesale_price'], '' );
                            $map_wholesale_price = WWPP_Helper_Functions::woocs_exchange( $map_wholesale_price );
                            $price               = WWP_ACS_Integration_Helper::convert( $map_wholesale_price, $active_currency, $base_currency );
                            $wholesale_price_tax = $this->get_product_shop_price_with_taxing_applied( $product, $map_wholesale_price, array( 'currency' => $active_currency ), $user_wholesale_role, false ); // Check if we apply or not apply tax to price.
                            $save                = wc_format_decimal( 100 - ( wc_format_decimal( $wholesale_price_tax, '' ) / floatval( $product->get_price() ) * 100 ), 1 ) . '%';

                        } elseif ( 'percent-price' === $base_map['price_type'] ) {

                            $price = $wholesale_price - ( ( $base_map['wholesale_price'] / 100 ) * $wholesale_price );
                            $save  = $base_map['wholesale_price'] . '%';

                            /**
                             * No need to use
                             * $price = WWP_ACS_Integration_Helper::convert( $price , $active_currency , $base_currency );
                             * to convert the price because the $wholesale_price variable is already using the converted wholesale price
                             * WWPP-558
                             */

                        }
                    } else {
                        $price = WWP_ACS_Integration_Helper::convert( $base_map['wholesale_price'], $active_currency, $base_currency );
                    }

                    $price = $this->get_product_shop_price_with_taxing_applied( $product, $price, array( 'currency' => $active_currency ), $user_wholesale_role );

                }

                $qty_table .= '<tr>';
                do_action( 'wwpp_action_before_wholesale_price_table_per_order_quantity_entry_view', $base_map, $product, $user_wholesale_role );

                if ( isset( $headers['qty'] ) ) {
                    $qty_table .= '<td>' . $qty . '</td>';
                }
                if ( isset( $headers['price'] ) ) {
                    $qty_table .= '<td>' . $price . '</td>';
                }
                if ( isset( $headers['save'] ) ) {
                    $qty_table .= '<td>' . $save . '</td>';
                }

                do_action( 'wwpp_action_after_wholesale_price_table_per_order_quantity_entry_view', $base_map, $product, $user_wholesale_role );
                $qty_table .= '</tr>';

            }
        } else {

            /**
             * Base currency.
             * Also the default if Aelia currency switcher plugin isn't active.
             */
            foreach ( $base_currency_mapping as $map ) {

                $qty  = $map['start_qty'];
                $save = '';

                if ( ! empty( $map['end_qty'] ) ) {
                    $qty .= ' - ' . $map['end_qty'];
                } else {
                    $qty .= '+';
                }

                if ( isset( $map['price_type'] ) ) {

                    // We only apply taxing on fixed price, we don't need taxing on percent price since the wholesale price is already taxed.
                    if ( 'fixed-price' === $map['price_type'] ) {

                        $map_wholesale_price = wc_format_decimal( $map['wholesale_price'], '' );
                        $map_wholesale_price = WWPP_Helper_Functions::woocs_exchange( $map_wholesale_price );
                        $price               = $this->get_product_shop_price_with_taxing_applied( $product, $map_wholesale_price, array( 'currency' => $base_currency ), $user_wholesale_role );
                        $wholesale_price_tax = $this->get_product_shop_price_with_taxing_applied( $product, $map_wholesale_price, array( 'currency' => $active_currency ), $user_wholesale_role, false ); // Check if we apply or not apply tax to price.
                        $save                = wc_format_decimal( 100 - ( wc_format_decimal( $wholesale_price_tax, '' ) / floatval( $wholesale_price ) * 100 ), '' ) . '%';

                    } elseif ( 'percent-price' === $map['price_type'] ) {

                        $price = wc_format_decimal( $wholesale_price - ( ( (float) $map['wholesale_price'] / 100 ) * $wholesale_price ), '' );
                        $price = WWP_Helper_Functions::wwp_formatted_price( $price );
                        $save  = wc_format_decimal( $map['wholesale_price'], '' ) . '%';

                    }
                } else {
                    $price = $this->get_product_shop_price_with_taxing_applied( $product, $map['wholesale_price'], array( 'currency' => $base_currency ), $user_wholesale_role );
                }

                $qty_table .= '<tr>';
                do_action( 'wwpp_action_before_wholesale_price_table_per_order_quantity_entry_view', $map, $product, $user_wholesale_role );
                if ( isset( $headers['qty'] ) ) {
                    $qty_table .= '<td>' . $qty . '</td>';
                }

                if ( isset( $headers['price'] ) ) {
                    $qty_table .= '<td>' . $price . '</td>';
                }

                if ( isset( $headers['save'] ) ) {
                    $qty_table .= '<td>' . $save . '</td>';
                }

                do_action( 'wwpp_action_after_wholesale_price_table_per_order_quantity_entry_view', $map, $product, $user_wholesale_role );
                $qty_table .= '</tr>';
            }
        }

        $qty_table .= '</tbody>';

        $qty_table .= '</table>'; // .order-quantity-based-wholesale-pricing-view table-view

        echo apply_filters( 'wwpp_qty_based_table_product_level', $qty_table, $wholesale_price, $mapping, $product, $user_wholesale_role ); // phpcs:ignore.
    }

    /**
     * Apply quantity based discount on products on cart.
     *
     * @param array   $wholesale_price_arr Wholesale price array data.
     * @param int     $product_id          Product Id.
     * @param array   $user_wholesale_role Array of user wholesale role.
     * @param WC_Cart $cart_item           WC_Cart object.
     *
     * @since 1.6.0
     * @since 1.7.0 Add Aelia currency switcher plugin integration
     * @since 1.16.0 Rename from 'applyOrderQuantityBasedWholesalePricing' to
     *        'apply_product_level_order_quantity_based_wholesale_pricing'. Refactor codebase.
     * @since 1.30.3 Fix qty based pricing not respecting 'Always use regular price' setting.
     *               Fix price not based on wholesale sale price if wholesale sale price is set.
     *
     * @return array Filtered wholesale price array data.
     */
    public function apply_product_level_order_quantity_based_wholesale_pricing( $wholesale_price_arr, $product_id, $user_wholesale_role, $cart_item ) {

        // Quantity based discount depends on a wholesale price being set on the per product level
        // If none is set, then, quantity based discount will not be applied even if it is defined.
        if ( ! empty( $user_wholesale_role ) && ! empty( $wholesale_price_arr['wholesale_price'] ) ) {

            $product           = wc_get_product( $product_id );
            $enabled           = 'no';
            $use_regular_price = get_option( 'wwpp_settings_explicitly_use_product_regular_price_on_discount_calc' );

            if ( $product->is_type( 'variation' ) ) {
                $enabled = get_post_meta( $product->get_parent_id(), WWPP_POST_META_ENABLE_QUANTITY_DISCOUNT_RULE, true );
            }

            // If the variable qty based discount is enabled we use its mapping else we use the per variation mapping.
            if ( 'yes' === $enabled ) {

                $mapping = get_post_meta( $product->get_parent_id(), WWPP_POST_META_QUANTITY_DISCOUNT_RULE_MAPPING, true );

            } else {

                $enabled = $product->get_meta( WWPP_POST_META_ENABLE_QUANTITY_DISCOUNT_RULE, true );
                $mapping = $product->get_meta( WWPP_POST_META_QUANTITY_DISCOUNT_RULE_MAPPING, true );

            }

            if ( ! is_array( $mapping ) ) {
                $mapping = array();
            }

            if ( 'yes' === $enabled && ! empty( $mapping ) ) {
                /*
                 * Get the base currency mapping. The base currency mapping well determine what wholesale
                 * role and range pairing a product has wholesale price with.
                 */
                $base_currency_mapping = $this->_get_base_currency_mapping( $mapping, $user_wholesale_role );
                $wholesale_sale_price  = self::get_product_wholesale_sale_price( $product_id, $user_wholesale_role );
                $wholesale_main_price  = ! empty( $wholesale_sale_price ) && $wholesale_sale_price['is_on_sale'] ? $wholesale_sale_price['wholesale_sale_price'] : $wholesale_price_arr['wholesale_price'];
                $quantity_based_price  = $wholesale_main_price;

                if ( 'yes' === $use_regular_price ) {
                    $quantity_based_price = $product->get_regular_price();
                }

                $quantity_based_price = apply_filters( 'wwpp_quantity_based_discount_base_price', $quantity_based_price, $mapping, $wholesale_price_arr['wholesale_price'], $product, $user_wholesale_role );

                if ( WWP_ACS_Integration_Helper::aelia_currency_switcher_active() ) {

                    $base_currency   = WWP_ACS_Integration_Helper::get_product_base_currency( $product_id );
                    $active_currency = get_woocommerce_currency();

                    if ( $base_currency === $active_currency ) {

                        $wholesale_price_arr['wholesale_price'] = $this->_get_wholesale_price_from_mapping( $quantity_based_price, $base_currency_mapping, array(), $cart_item, $base_currency, $active_currency, true, $wholesale_main_price );
                        $wholesale_price_arr['source']          = 'per_product_level_qty_based';

                    } else {

                        // Get specific currency mapping.
                        $specific_currency_mapping = $this->_get_specific_currency_mapping( $mapping, $user_wholesale_role, $active_currency, $base_currency_mapping );

                        $wholesale_price_arr['wholesale_price'] = $this->_get_wholesale_price_from_mapping( $quantity_based_price, $base_currency_mapping, $specific_currency_mapping, $cart_item, $base_currency, $active_currency, false, $wholesale_main_price );
                        $wholesale_price_arr['source']          = 'per_product_level_qty_based';

                    }
                } else {

                    $wholesale_price_arr['wholesale_price'] = $this->_get_wholesale_price_from_mapping( $quantity_based_price, $base_currency_mapping, array(), $cart_item, get_woocommerce_currency(), get_woocommerce_currency(), true, $wholesale_main_price );
                    $wholesale_price_arr['source']          = 'per_product_level_qty_based';

                }
            }
        }

        return $wholesale_price_arr;
    }

    /**
     * Get the wholesale price of a wholesale role for the appropriate range from the wholesale price per order
     * quantity mapping that is appropriate for the current items on the current wholesale user's cart.
     *
     * @param string        $wholesale_price           Wholesale Price.
     * @param array         $base_currency_mapping     Base currency mapping.
     * @param array         $specific_currency_mapping Specific currency mapping.
     * @param WC_Cart|array $cart_item                 Cart item data.
     * @param string        $base_currency             Base currency.
     * @param string        $active_currency           Active currency.
     * @param boolean       $is_base_currency          Is base currency.
     * @param string        $wholesale_main_price      Wholesale main price.
     *
     * @since 1.7.0
     * @since 1.16.0
     * Renamed from '_getWholesalePriceFromMapping' to '_get_wholesale_price_from_mapping'.
     * Refactor codebase.
     * @since 1.19   If quantity based pricing is enabled via variable level then apply discount to all variation if
     *        the total reaches the quantity range else use variation mapping discount. (WWPP-592).
     * @since 1.27.9 Replace round with wc_format_decimal function
     *
     * @return string Filtered wholesale price.
     */
    private function _get_wholesale_price_from_mapping( $wholesale_price, $base_currency_mapping, $specific_currency_mapping, $cart_item, $base_currency, $active_currency, $is_base_currency, $wholesale_main_price ) {

        if ( in_array( WWP_Helper_Functions::wwp_get_product_type( $cart_item['data'] ), array( 'simple', 'variation' ), true ) ) {
            $cart_item['quantity'] = $this->tally_product_qty_in_shopping_cart( $cart_item );
        }

        $applied_mapping = false;
        if ( ! $is_base_currency ) {

            foreach ( $base_currency_mapping as $baseMap ) {

                $price = '';

                /*
                 * First check if a price is set for this wholesale role : range pair in the specific currency mapping.
                 * If wholesale price is present, then use it.
                 */
                foreach ( $specific_currency_mapping as $specificMap ) {

                    if ( $cart_item['quantity'] >= $specificMap[ $active_currency . '_start_qty' ] && ( empty( $specificMap[ $active_currency . '_end_qty' ] ) || $cart_item['quantity'] <= $specificMap[ $active_currency . '_end_qty' ] ) && '' !== $specificMap[ $active_currency . '_wholesale_price' ] ) {

                        if ( isset( $specificMap[ $active_currency . '_price_type' ] ) ) {

                            if ( 'fixed-price' === $specificMap[ $active_currency . '_price_type' ] ) {
                                $price = $specificMap[ $active_currency . '_wholesale_price' ];
                            } elseif ( 'percent-price' === $specificMap[ $active_currency . '_price_type' ] ) {
                                $price = wc_format_decimal( $wholesale_price - ( ( $specificMap[ $active_currency . '_wholesale_price' ] / 100 ) * $wholesale_price ), '' );
                            }
                        } else {
                            $price = $specificMap[ $active_currency . '_wholesale_price' ];
                        }
                    }
                }

                /*
                 * Now if there is no mapping for this specific wholesale role : range pair in the specific currency mapping,
                 * since this range is present on the base map mapping. We derive the price by converting the price set on the
                 * base currency mapping to this active currency.
                 */
                if ( ! $price ) {

                    if ( $cart_item['quantity'] >= $baseMap['start_qty'] &&
                        ( empty( $baseMap['end_qty'] ) || $cart_item['quantity'] <= $baseMap['end_qty'] ) &&
                        '' !== $baseMap['wholesale_price'] ) {

                        if ( isset( $baseMap['price_type'] ) ) {

                            if ( 'fixed-price' === $baseMap['price_type'] ) {
                                $price = WWP_ACS_Integration_Helper::convert( $baseMap['wholesale_price'], $active_currency, $base_currency );
                            } elseif ( 'percent-price' === $baseMap['price_type'] ) {

                                $price = wc_format_decimal( $wholesale_price - ( ( $baseMap['wholesale_price'] / 100 ) * $wholesale_price ), '' );

                                /**
                                 * No need to use
                                 * $price = WWP_ACS_Integration_Helper::convert( $price , $active_currency , $base_currency );
                                 * to convert the price because the $wholesale_price variable is already using the converted wholesale price
                                 * WWPP-558
                                 */

                            }
                        } else {
                            $price = WWP_ACS_Integration_Helper::convert( $baseMap['wholesale_price'], $active_currency, $base_currency );
                        }
                    }
                }

                if ( $price ) {

                    $wholesale_price = $price;
                    $applied_mapping = true;
                    break;

                }
            }
        } else {

            foreach ( $base_currency_mapping as $map ) {

                if ( $cart_item['quantity'] >= $map['start_qty'] &&
                    ( empty( $map['end_qty'] ) || $cart_item['quantity'] <= $map['end_qty'] ) &&
                    '' !== $map['wholesale_price'] ) {

                    if ( isset( $map['price_type'] ) ) {

                        if ( 'fixed-price' === $map['price_type'] ) {
                            $wholesale_price = $map['wholesale_price'];
                        } elseif ( 'percent-price' === $map['price_type'] ) {
                            $wholesale_price = wc_format_decimal( $wholesale_price - ( ( $map['wholesale_price'] / 100 ) * $wholesale_price ), '' );
                        }
                    } else {
                        $wholesale_price = $map['wholesale_price'];
                    }

                    $applied_mapping = true;

                    break;

                }
            }
        }

        if ( ! $applied_mapping ) {
            $wholesale_price = $wholesale_main_price;
        }

        return $wholesale_price;
    }

    /**
     * Get the base currency mapping from the wholesale price per order quantity mapping.
     *
     * @param array $mapping             Quantity discount mapping data.
     * @param array $user_wholesale_role Arry of user wholesale roles.
     *
     * @since 1.7.0
     * @since 1.16.0
     * Renamed 'getBaseCurrencyMapping' to '_get_base_currency_mapping'.
     * Refactor codebase.
     *
     * @return array Base currency mapping.
     */
    private function _get_base_currency_mapping( $mapping, $user_wholesale_role ) {

        $base_currency_mapping = array();

        foreach ( $mapping as $map ) {

            // Skip non base currency mapping.
            if ( array_key_exists( 'currency', $map ) ) {
                continue;
            }

            // Skip mapping not meant for the current user wholesale role.
            if ( $user_wholesale_role[0] !== $map['wholesale_role'] ) {
                continue;
            }

            $base_currency_mapping[] = $map;

        }

        return $base_currency_mapping;
    }

    /**
     * Get the specific currency mapping from the wholesale price per order quantity mapping.
     *
     * @param array  $mapping               Quantity discount mapping data.
     * @param array  $user_wholesale_role   Arry of user wholesale roles.
     * @param string $active_currency       Active currency.
     * @param array  $base_currency_mapping Base currency mapping.
     *
     * @since 1.7.0
     * @since 1.16.0
     * Renamed from '_getSpecificCurrencyMapping' to '_get_specific_currency_mapping'.
     * Refactor codebase.
     *
     * @return array Specific currency mapping.
     */
    private function _get_specific_currency_mapping( $mapping, $user_wholesale_role, $active_currency, $base_currency_mapping ) {

        // Get specific currency mapping.
        $specific_currency_mapping = array();

        foreach ( $mapping as $map ) {

            // Skip base currency.
            if ( ! array_key_exists( 'currency', $map ) ) {
                continue;
            }

            // Skip mappings that are not for the active currency.
            if ( ! array_key_exists( $active_currency . '_wholesale_role', $map ) ) {
                continue;
            }

            // Skip mapping not meant for the currency user wholesale role.
            if ( $user_wholesale_role[0] !== $map[ $active_currency . '_wholesale_role' ] ) {
                continue;
            }

            // Only extract out mappings for this current currency that has equivalent mapping
            // on the base currency.
            foreach ( $base_currency_mapping as $base_map ) {

                if ( $base_map['start_qty'] === $map[ $active_currency . '_start_qty' ] && $base_map['end_qty'] === $map[ $active_currency . '_end_qty' ] ) {

                    $specific_currency_mapping[] = $map;
                    break;

                }
            }
        }

        return $specific_currency_mapping;
    }

    /**
     * This functions get's product variable price suffix, when '{price_including_tax}', '{price_excluding_tax}' tags
     * are used in the 'Price display suffix'. This will not return any price computation. This fixes price suffix when
     * apply in product variable if '{price_including_tax}', '{price_excluding_tax}' tags are used.
     *
     * @param string     $wc_price_suffix             Contains price suffix.
     * @param WC_Product $product                     WC_Product object.
     * @param array      $user_wholesale_role         User wholesale role.
     * @param string     $wholesale_price             The wholesale price.
     * @param string     $return_wholesale_price_only Return wholesale price only.
     * @param array      $extra_args                  Extra arguments.
     *
     * @since  1.27.1
     * @access public
     * @return string       Wholesale price suffix.
     */
    public function get_wholesale_price_display_suffix_filter( $wc_price_suffix, $product, $user_wholesale_role, $wholesale_price, $return_wholesale_price_only, $extra_args ) {

        if ( ! empty( $user_wholesale_role ) ) {

            // Check if product type is variable.
            if ( WWP_Helper_Functions::wwp_get_product_type( $product ) === 'variable' ) {

                // If product type is bundle product, $user_wholesale_role variable returns string
                // We need to convert this to array to use get_product_wholesale_price_on_shop_v3 function.
                if ( ! is_array( $user_wholesale_role ) ) {
                    $user_wholesale_role = array( $user_wholesale_role );
                }

                // Get product wholesale raw price, if empty then assign produtcs regular price.
                $price_base = apply_filters( 'wwp_wholesale_price_suffix_base_price', ! isset( $extra_args['base_price'] ) ? $extra_args['base_price'] : $product->get_regular_price(), $product );

                // Get price suffix.
                $wc_price_suffix = $this->override_wholesale_price_suffix( get_option( 'woocommerce_price_display_suffix' ) );

                // Get variable product price display.
                $product_price_display = get_option( 'wwpp_settings_variable_product_price_display' );

                // Get variable product variations.
                $min_price = isset( $extra_args['min_price'] ) ? $extra_args['min_price'] : '';
                $max_price = isset( $extra_args['max_price'] ) ? $extra_args['max_price'] : '';
                $price     = '';

                // Check variable price display.
                if ( 'minimum' === $product_price_display ) {
                    $price = $min_price;
                } elseif ( 'maximum' === $product_price_display ) {
                    $price = $max_price;
                } else {
                    $price = $max_price;
                }

                // Check if price suffix contain including tax tag {price_including_tax}.
                if ( str_contains( $wc_price_suffix, '{price_including_tax}' ) ) {

                    // Get formatted wholesale price with tax.
                    $wholesale_price_incl_tax = WWP_Helper_Functions::wwp_formatted_price(
                        WWP_Helper_Functions::wwp_get_price_including_tax(
                            $product,
                            array(
                                'qty'   => 1,
                                'price' => $price,
                            )
                        )
                    );

                    // Replace {price_including_tax} tag with wholesale price with tax.
                    $wc_price_suffix = str_replace( '{price_including_tax}', $wholesale_price_incl_tax, $wc_price_suffix );
                }

                // Check if price suffix contain excluding tax tag {price_excluding_tax}.
                if ( str_contains( $wc_price_suffix, '{price_excluding_tax}' ) ) {

                    // Get formatted wholesale price without tax.
                    $wholesale_price_excl_tax = WWP_Helper_Functions::wwp_formatted_price(
                        WWP_Helper_Functions::wwp_get_price_excluding_tax(
                            $product,
                            array(
                                'qty'   => 1,
                                'price' => $price,
                            )
                        )
                    );

                    // Replace {price_excluding_tax} tag with wholesale price without tax.
                    $wc_price_suffix = str_replace( '{price_excluding_tax}', $wholesale_price_excl_tax, $wc_price_suffix );
                }

                $wc_price_suffix = ' <small class="woocommerce-price-suffix wholesale-price-suffix">' . $wc_price_suffix . '</small>';

            }
        }

        return $wc_price_suffix;
    }

    /*
    |-------------------------------------------------------------------------------------------------------------------
    | Apply wholesale prices on shop and cart for custom product types
    |-------------------------------------------------------------------------------------------------------------------
     */

    /**
     * Filter callback that alters the product price, it embeds the wholesale price of a product for a wholesale user (
     * Custom product types ).
     *
     * @param string     $price   Product price.
     * @param WC_Product $product WC_Product instance.
     *
     * @since  1.16.0
     * Renamed from 'wholesalePriceHTMLFilter' to 'custom_product_type_wholesale_price_html_filter'.
     * Refactor codebase.
     * Supports new wholesale price model.
     * @access public
     *
     * @since  1.8.0 Partial support for composite product.
     * @since  1.9.0 Partial support for bundle product.
     * @return string Product price.
     */
    public function custom_product_type_wholesale_price_html_filter( $price, $product ) {

        $user_wholesale_role = $this->_get_current_user_wholesale_role();

        if ( ! empty( $user_wholesale_role ) && ! empty( $price ) ) {

            $raw_wholesale_price = '';
            $wholesale_price     = '';
            $source              = '';

            if ( in_array( WWP_Helper_Functions::wwp_get_product_type( $product ), array( 'composite', 'bundle' ), true ) ) {

                $price_arr                   = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( WWP_Helper_Functions::wwp_get_product_id( $product ), array( $user_wholesale_role ) );
                $raw_wholesale_price         = $price_arr['wholesale_price'];
                $source                      = $price_arr['source'];
                $wholesale_price_with_no_tax = $price_arr['wholesale_price_with_no_tax'];
                $wholesale_sale_price        = self::get_product_wholesale_sale_price( $product->get_ID(), array( $user_wholesale_role ) );

                if ( null !== $wholesale_sale_price && $wholesale_sale_price['is_on_sale'] ) {
                    $raw_wholesale_price         = $wholesale_sale_price['wholesale_sale_price'];
                    $wholesale_price_with_no_tax = $wholesale_sale_price['wholesale_sale_price_with_no_tax'];
                }

                if ( strcasecmp( $raw_wholesale_price, '' ) !== 0 ) {
                    $wholesale_price = WWP_Helper_Functions::wwp_formatted_price( $raw_wholesale_price ) . WWP_Wholesale_Prices::get_wholesale_price_suffix( $product, $user_wholesale_role, $wholesale_price_with_no_tax );
                }
            }

            if ( strcasecmp( $wholesale_price, '' ) !== 0 ) {

                $wholesale_price_html = apply_filters( 'wwp_product_original_price', '<del class="original-computed-price">' . $price . '</del>', $wholesale_price, $price, $product, array( $user_wholesale_role ) );

                $wholesale_price_title_text = __( 'Wholesale Price:', 'woocommerce-wholesale-prices-premium' );
                $wholesale_price_title_text = apply_filters( 'wwp_filter_wholesale_price_title_text', $wholesale_price_title_text );

                $wholesale_price_html .= '<span style="display: block;" class="wholesale_price_container">
                                            <span class="wholesale_price_title">' . $wholesale_price_title_text . '</span>
                                            <ins>' . $wholesale_price . '</ins>
                                        </span>';

                return apply_filters( 'wwp_filter_wholesale_price_html', $wholesale_price_html, $price, $product, array( $user_wholesale_role ), $wholesale_price_title_text, $raw_wholesale_price, $source );

            }
        }

        return $price;
    }

    /**
     * Apply wholesale price upon adding product to cart ( Custom Product Types ).
     *
     * @param string $wholesale_price     Wholesale price.
     * @param array  $cart_item           Cart item data.
     * @param array  $user_wholesale_role Array of user wholesale role.
     * @param object $cart_object         Cart object.
     *
     * @since  1.15.0 Use 'get_product_wholesale_price_on_cart' function of class WWP_Wholesale_Prices.
     * @since  1.16.0
     * Renamed from 'applyCustomProductTypeWholesalePrice'  to 'apply_custom_product_type_wholesale_price'.
     * Refactor codebase.
     * @since  2.1.0 Route `subscription_variation` through `get_product_wholesale_price_on_cart()` so variable
     *               subscription variations resolve their wholesale price and `_wwp_wholesale_priced` is set to `yes`.
     * @access public
     *
     * @since  1.8.0
     * @return string Filtered wholesale price.
     */
    public function apply_custom_product_type_wholesale_price( $wholesale_price, $cart_item, $user_wholesale_role, $cart_object ) {

        if ( in_array( WWP_Helper_Functions::wwp_get_product_type( $cart_item['data'] ), array( 'composite', 'bundle', 'subscription_variation' ), true ) ) {
            $wholesale_price = WWP_Wholesale_Prices::get_product_wholesale_price_on_cart( WWP_Helper_Functions::wwp_get_product_id( $cart_item['data'] ), $user_wholesale_role, $cart_item, $cart_object );
        }

        return $wholesale_price;
    }

    /*
    |-------------------------------------------------------------------------------------------------------------------
    | Mesc wholesale price related operations
    |-------------------------------------------------------------------------------------------------------------------
     */

    /**
     * Override the price suffix for wholesale users only.
     *
     * @param string $suffix Price display suffix.
     *
     * @since  1.24.5    Refactor code. Codes are transferred to WWP. By default if WC price suffix is used it will be
     *         used in the wholesale price as well. If "Wholesale Price Suffix" option is set then override wholesale
     *         price suffix.
     * @access public
     *
     * @return string    Wholesale Price Suffix.
     */
    public function override_wholesale_price_suffix( $suffix ) {

        $price_suffix_option = get_option( 'wwpp_settings_override_price_suffix' );

        if ( ! empty( $price_suffix_option ) ) {
            return $price_suffix_option;
        }

        return $suffix;
    }

    /**
     * Override the price suffix for regular prices viewed by wholesale customers.
     *
     * @param string     $price_suffix_html Price suffix markup.
     * @param WC_Product $product           WC Product instance.
     * @param string     $price             Product price.
     * @param int        $qty               Quantity.
     *
     * @since  1.14.7
     * @since  1.16.0
     * Renamed from 'overrideRegularPriceSuffixForWholesaleRoles' to
     * 'override_regular_price_suffix_for_wholesale_roles'. Refactor codebase. Add support for '{price_including_tax}'
     * and '{price_excluding_tax}' placeholders.
     * @access public
     *
     * @return string Filtered price suffix markup.
     */
    public function override_regular_price_suffix_for_wholesale_roles( $price_suffix_html, $product, $price = null, $qty = 1 ) { // phpcs:ignore.
        if ( empty( $price_suffix_html ) ) {
            return $price_suffix_html;
        }
        // Called on a variable product price range.

        if ( is_null( $price ) ) {
            $price = $product->get_price();
        } elseif ( 'range' === $price && is_object( $product ) && $product->is_type( 'bundle' ) ) {
            // bundled product price.
            $price = $product->get_bundle_price();
        }

        $price = apply_filters( 'wwpp_regular_price_suffix_base_price', $price, $product );

        $user_wholesale_role = $this->_get_current_user_wholesale_role();

        if ( ! empty( $user_wholesale_role ) ) {

            $price_suffix_option = get_option( 'wwpp_settings_override_price_suffix_regular_price' );
            if ( empty( $price_suffix_option ) ) {
                $price_suffix_option = get_option( 'woocommerce_price_display_suffix' );
            }

            $wholesale_suffix_for_regular_price = $price_suffix_option;
            $has_match                          = false;

            if ( str_contains( $wholesale_suffix_for_regular_price, '{price_including_tax}' ) ) {

                $product_price_incl_tax             = WWP_Helper_Functions::wwp_formatted_price(
                    WWP_Helper_Functions::wwp_get_price_including_tax(
                        $product,
                        array(
                            'qty'   => 1,
                            'price' => $price,
                        )
                    )
                );
                $wholesale_suffix_for_regular_price = str_replace( '{price_including_tax}', $product_price_incl_tax, $wholesale_suffix_for_regular_price );
                $has_match                          = true;

            }

            if ( str_contains( $wholesale_suffix_for_regular_price, '{price_excluding_tax}' ) ) {

                $product_price_excl_tax             = WWP_Helper_Functions::wwp_formatted_price(
                    WWP_Helper_Functions::wwp_get_price_excluding_tax(
                        $product,
                        array(
                            'qty'   => 1,
                            'price' => $price,
                        )
                    )
                );
                $wholesale_suffix_for_regular_price = str_replace( '{price_excluding_tax}', $product_price_excl_tax, $wholesale_suffix_for_regular_price );
                $has_match                          = true;

            }

            return $has_match ? ' <small class="woocommerce-price-suffix wholesale-user-regular-price-suffix">' . $wholesale_suffix_for_regular_price . '</small>' : ' <small class="woocommerce-price-suffix">' . $price_suffix_option . '</small>';

        }

        return $price_suffix_html;
    }

    /*
    |-------------------------------------------------------------------------------------------------------------------
    | Helper Functions
    |-------------------------------------------------------------------------------------------------------------------
     */

    /**
     * Get the price of a product on shop pages with taxing applied (Meaning either including or excluding tax
     * depending on the settings of the shop).
     *
     * @param WC_Product $product             The Product Object.
     * @param string     $price               The product price.
     * @param array      $wc_price_arg        Price args.
     * @param array      $user_wholesale_role User wholesale role.
     * @param bool       $formatted           Whether to return formatted price or plain price. Default is formatted
     *                                        price.
     *
     * @since  1.16.0 Renamed from 'getProductShopPriceWithTaxingApplied' to
     *         'get_product_shop_price_with_taxing_applied'.
     * @access public
     *
     * @since  1.7.1
     * @return string
     */
    public function get_product_shop_price_with_taxing_applied( $product, $price, $wc_price_arg = array(), $user_wholesale_role = '', $formatted = true ) {

        if ( get_option( 'woocommerce_calc_taxes', false ) === 'yes' ) {

            $woocommerce_tax_display_shop = get_option( 'woocommerce_tax_display_shop', false ); // (WooCommerce) Display Prices in the Shop
            $wholesale_tax_display_shop   = get_option( 'wwpp_settings_incl_excl_tax_on_wholesale_price', false ); // (Wholesale) Display Prices in the Shop
            $tax_exempted                 = ! empty( $user_wholesale_role ) ? WWPP_Helper_Functions::is_user_wwpp_tax_exempted( get_current_user_id(), $user_wholesale_role[0] ) : '';

            if ( 'yes' === $tax_exempted ) {

                // Wholesale user is tax exempted so no matter what, the user will always see tax exempted prices.
                $filtered_price = WWP_Helper_Functions::wwp_get_price_excluding_tax(
                    $product,
                    array(
                        'qty'   => 1,
                        'price' => $price,
                    )
                );

            } elseif ( 'incl' === $wholesale_tax_display_shop ) {

                $filtered_price = WWP_Helper_Functions::wwp_get_price_including_tax(
                    $product,
                    array(
                        'qty'   => 1,
                        'price' => $price,
                    )
                );
            } elseif ( 'excl' === $wholesale_tax_display_shop ) {
                $filtered_price = WWP_Helper_Functions::wwp_get_price_excluding_tax(
                    $product,
                    array(
                        'qty'   => 1,
                        'price' => $price,
                    )
                );
            } elseif ( empty( $wholesale_tax_display_shop ) ) {

                if ( 'incl' === $woocommerce_tax_display_shop ) {
                    $filtered_price = WWP_Helper_Functions::wwp_get_price_including_tax(
                        $product,
                        array(
                            'qty'   => 1,
                            'price' => $price,
                        )
                    );
                } else {
                    $filtered_price = WWP_Helper_Functions::wwp_get_price_excluding_tax(
                        $product,
                        array(
                            'qty'   => 1,
                            'price' => $price,
                        )
                    );
                }
            }

            if ( $formatted ) {
                $filtered_price = WWP_Helper_Functions::wwp_formatted_price( $filtered_price, $wc_price_arg );
            }

            return apply_filters( 'wwpp_filter_product_shop_price_with_taxing_applied', $filtered_price, $price, $product );

        }

        if ( $formatted ) {
            $price = WWP_Helper_Functions::wwp_formatted_price( $price, $wc_price_arg );
        }

        return $price;
    }

    /**
     * Tally all same products that has different prices. Example of this is product addons separate the product when
     * added to cart because of its different addon price types. We need to do this for wholesale quantity discount
     * options we have.
     *
     * @param array $cart_item Cart item.
     *
     * @since  1.20 Added compatibility to product addons. Rename function from
     *         per_variable_product_variation_total_shopping_cart to tally_product_qty_in_shopping_cart.
     * @access public
     *
     * @since  1.19
     * @return int
     */
    public function tally_product_qty_in_shopping_cart( $cart_item ) {

        global $woocommerce;

        $variation_qty_disc_enabled = '';
        $parent_qty_disc_enabled    = get_post_meta( $cart_item['product_id'], WWPP_POST_META_ENABLE_QUANTITY_DISCOUNT_RULE, true );
        $cart_items                 = $woocommerce->cart->get_cart();
        $qty_total                  = 0;
        if ( ! empty( $cart_item['variation_id'] ) ) {
            $variation_qty_disc_enabled = get_post_meta( $cart_item['variation_id'], WWPP_POST_META_ENABLE_QUANTITY_DISCOUNT_RULE, true );
        }

        foreach ( $cart_items as $item ) {

            if ( 'yes' === $parent_qty_disc_enabled && $item['product_id'] === $cart_item['product_id'] ) {

                // Modify quantity total to count all variations under the variable product or same product that is separated by product addon that has different price types.
                $qty_total += $item['quantity'];

            } elseif ( 'yes' === $variation_qty_disc_enabled && $item['variation_id'] === $cart_item['variation_id'] ) {

                // If qty discount is set per variations, we need to tally each quantity or if addons are added to cart that has different price types.
                $qty_total += $item['quantity'];

            }
        }

        return empty( $qty_total ) ? $cart_item['quantity'] : $qty_total;
    }

    /**
     * Always use regular price option for variable price range
     *
     * @param string     $price   Price.
     * @param WC_Product $product Product.
     *
     * @since  1.24.5
     * @access public
     *
     * @return string
     */
    public function always_use_regular_price_option_for_variable_product( $price, $product ) {

        $user_wholesale_role = $this->_get_current_user_wholesale_role();
        $use_regular_price   = get_option( 'wwpp_settings_explicitly_use_product_regular_price_on_discount_calc' );

        if ( ! empty( $user_wholesale_role ) && 'yes' === $use_regular_price && $product->get_meta( $user_wholesale_role[0] . '_have_wholesale_price', true ) === 'yes' ) {

            $prices        = $product->get_variation_prices( true );
            $min_reg_price = current( $prices['regular_price'] );
            $max_reg_price = end( $prices['regular_price'] );

            if ( $min_reg_price !== $max_reg_price ) {
                $price = wc_format_price_range( $min_reg_price, $max_reg_price );
            } else {
                $price = wc_price( $min_reg_price );
            }

            return apply_filters( 'always_use_regular_price_option_for_variable_product', $price, $product );
        }

        return apply_filters( 'wwpp_woocommerce_variable_price_html', $price, $product );
    }

    /**
     * Filter to override the base price for regular prices suffix viewed by wholesale customers.
     * WooCommerce Product Bundle: When the product priced individually and the discount is set make sure to use the
     * 'bundled_item_price' instead 'price'
     *
     * @param int        $price_base Base price.
     * @param WC_Product $product    Product.
     *
     * @since  1.27.2
     * @access public
     *
     * @return int
     */
    public function filter_regular_price_suffix_base_price( $price_base, $product ) {

        if ( property_exists( $product, 'bundled_item_price' ) && $product->bundled_item_price > 0 ) {
            $price_base = WWPP_Helper_Functions::woocs_exchange( $product->bundled_item_price );
        }

        return $price_base;
    }

    /**
     * Filter to override the base price for wholesale prices suffix viewed by wholesale customers.
     * WooCommerce Product Bundle: When the product priced individually and the discount is set make sure to use the
     * 'discounted_wholesale_price' instead 'wholesale_price_raw'
     *
     * @param int        $price_base Base price.
     * @param WC_Product $product    Product.
     *
     * @since  1.27.2
     * @access public
     *
     * @return int
     */
    public function filter_wholesale_price_suffix_base_price( $price_base, $product ) {

        global $WOOCS;

        if ( property_exists( $product, 'wholesale_price_data' ) && property_exists( $product, 'discounted_wholesale_price' ) && $product->discounted_wholesale_price > 0 ) {
            if ( WWP_Helper_Functions::wwp_get_product_type( $product ) !== 'variation' ||
                ( ! $WOOCS && WWP_Helper_Functions::wwp_get_product_type( $product ) === 'variation' && 'per_product_level' === $product->wholesale_price_data['source'] )
            ) {
                $price_base = WWPP_Helper_Functions::woocs_exchange( $product->discounted_wholesale_price );
            } elseif ( $WOOCS && $WOOCS->default_currency !== $WOOCS->current_currency && WWP_Helper_Functions::wwp_get_product_type( $product ) === 'variation' && 'per_product_level' !== $product->wholesale_price_data['source'] ) {
                $currencies    = $WOOCS->get_currencies();
                $currency_rate = $currencies[ $WOOCS->current_currency ]['rate'];

                if ( $currency_rate >= 1 ) {
                    $price_base = $product->discounted_wholesale_price;
                } else {
                    // If selected currency is not default currency, WOOCS is coverting the currency twice and applied the bundle discount twice, so we need to do back convert of the default currency and recalculate the bundle item discount once.
                    // this behaviour only occurs on the general discount and category discount level, because WOOCS converts the regular price.
                    $price_base = WWPP_Helper_Functions::woocs_back_convert( $product->discounted_wholesale_price ) * ( 100 / $product->bundle_item_discount );
                }
            }
        }

        return $price_base;
    }

    /**
     * Apply wholesale saved price on single product and shop page.
     *
     * @param string     $wholesale_price_html       Wholesale price markup.
     * @param float      $price                      Product price.
     * @param WC_Product $product                    Product object.
     * @param array      $user_wholesale_role        Array of user wholesale roles.
     * @param string     $wholesale_price_title_text Wholesale price title text.
     * @param string     $raw_wholesale_price        Raw wholesale price.
     * @param string     $source                     Source of the wholesale price being applied.
     *
     * @since  1.29
     * @access public
     *
     * @return string    Wholesale saving amount markup.
     */
    public function apply_wholesale_saving_amount_on_single_and_shop_page( $wholesale_price_html, $price, $product, $user_wholesale_role, $wholesale_price_title_text, $raw_wholesale_price, $source ) { // phpcs:ignore.

        if ( ! empty( $user_wholesale_role ) &&
            is_numeric( $raw_wholesale_price ) &&
            WWP_Helper_Functions::wwp_get_product_type( $product ) !== 'variable' &&
            get_option( 'wwpp_settings_show_saving_amount', false ) === 'yes'
        ) {
            global $woocommerce_loop;

            /**
             * Show the wholsale saving amount if the met below conditions:
             *
             * When show saving amount on single product is enabled and in the product single page and not in loop
             * or
             * When show saving amount on shop page is enabled and in the shop page or in taxomony page or in loop (Related product, Upsell product, etc.)
             */
            if ( ( get_option( 'wwpp_settings_show_saving_amount_page_single_product', false ) === 'yes' && is_product() && empty( $woocommerce_loop['name'] ) ) || ( ( get_option( 'wwpp_settings_show_saving_amount_page_shop', false ) === 'yes' ) && ( is_shop() || is_product_taxonomy() || ( isset( $woocommerce_loop['name'] ) && '' !== $woocommerce_loop['name'] ) ) ) ) {

                remove_filter( 'woocommerce_product_is_on_sale', array( $this, 'apply_on_sale_badge_on_wholesale_sale_products' ), 10, 2 );
                $use_regular_price = get_option( 'wwpp_settings_explicitly_use_product_regular_price_on_discount_calc' );
                $product_price     = ( $product->is_on_sale() && 'yes' !== $use_regular_price ) ? $product->get_sale_price() : $product->get_regular_price();
                $retail_price      = (float) self::get_product_shop_price_with_taxing_applied( $product, $product_price, array(), $user_wholesale_role, false );
                add_filter( 'woocommerce_product_is_on_sale', array( $this, 'apply_on_sale_badge_on_wholesale_sale_products' ), 10, 2 );

                $wholesale_sale_price = self::get_product_wholesale_sale_price( $product->get_ID(), $user_wholesale_role );
                $wholesale_price      = null !== $wholesale_sale_price && $wholesale_sale_price['is_on_sale'] ? $wholesale_sale_price['wholesale_sale_price'] : $raw_wholesale_price;

                // To avoid unexpected integer float calculation, force the $raw_wholesale_price to float.
                $wholesale_price = wc_format_decimal( (float) $wholesale_price, '' );

                /**
                 * Make saved amount compatible with WPML Multi Currency" plugin
                 */
                if ( is_plugin_active( 'woocommerce-multilingual/wpml-woocommerce.php' ) ) {

                    global $woocommerce_wpml;

                    if ( ! defined( 'WCML_MULTI_CURRENCIES_INDEPENDENT' ) ) {
                        include_once WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'wpml-woocommerce' . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'constants.php';
                    }

                    if ( WCML_MULTI_CURRENCIES_INDEPENDENT === $woocommerce_wpml->settings['enable_multi_currency'] ) {

                        if ( $woocommerce_wpml->multi_currency->get_client_currency() !== get_option( 'woocommerce_currency' ) ) {
                            $wholesale_price = apply_filters( 'wcml_raw_price_amount', $wholesale_price, $woocommerce_wpml->multi_currency->get_client_currency() );
                        }
                    }
                }

                if ( $retail_price > $wholesale_price ) {
                    $saved_amount      = $retail_price - $wholesale_price;
                    $saving_percentage = round( 100 - ( $wholesale_price / $retail_price * 100 ), wc_get_price_decimals() );

                    // The wholesale price is stored rounded to the store's price decimals, so
                    // reverse-deriving the percentage from it can drift from the configured
                    // discount (e.g. 5% rendering as 4.99% / 5.01%). Prefer the configured
                    // percentage for percentage-based discounts when the drift is rounding noise.
                    $saving_percentage = $this->get_display_saving_percentage( $product, $user_wholesale_role, $source, $retail_price, $saving_percentage );

                    $wholesale_price_html .= $this->get_saved_amount_text( $saved_amount, $saving_percentage );
                }
            }
        }

        return $wholesale_price_html;
    }

    /**
     * Resolve the wholesale saving percentage to display next to the saved amount.
     *
     * The saving percentage is derived from the wholesale price, which is stored rounded to the
     * store's price decimals. Reverse-deriving the percentage from that rounded price introduces a
     * small discrepancy (e.g. a 5% discount on a 71.50 product can render as 4.99% or 5.01%). For
     * percentage-based discounts (wholesale role general discount, per-user override discount, and
     * product category discount) the configured percentage is authoritative, so it is returned
     * whenever the derived percentage differs only by the noise that cent rounding can introduce.
     * Per-product fixed wholesale prices (and any other source) have no nominal percentage and keep
     * the derived value.
     *
     * @since 2.1.2
     * @access private
     *
     * @param WC_Product $product             Product object.
     * @param array      $user_wholesale_role Array of user wholesale roles.
     * @param string     $source              Source of the wholesale price being applied.
     * @param float      $retail_price        Retail price used in the saving calculation.
     * @param float      $computed_percentage Percentage derived from the rounded wholesale price.
     * @return float Percentage to display.
     */
    private function get_display_saving_percentage( $product, $user_wholesale_role, $source, $retail_price, $computed_percentage ) {

        $role = is_array( $user_wholesale_role ) ? reset( $user_wholesale_role ) : $user_wholesale_role;

        if ( empty( $role ) || $retail_price <= 0 ) {
            return $computed_percentage;
        }

        $nominal_percentage = $this->get_nominal_saving_percentage( $product, $role, $source, get_current_user_id() );

        return $this->snap_saving_percentage_to_nominal( $computed_percentage, $nominal_percentage, $retail_price );
    }

    /**
     * Resolve the configured (nominal) percentage discount behind a wholesale price.
     *
     * Percentage-based discounts store an authoritative percentage that the displayed saving should
     * reflect. This returns that percentage for the wholesale role general discount, the per-user
     * override discount, and the product category discount, or null for per-product fixed wholesale
     * prices (and any other source) that have no nominal percentage.
     *
     * @since 2.1.2
     * @access private
     *
     * @param WC_Product $product Product object.
     * @param string     $role    Single wholesale role key.
     * @param string     $source  Source of the wholesale price being applied.
     * @param int        $user_id User whose discount configuration should be read (the order's
     *                            customer on order/email surfaces, not necessarily the current user).
     * @return float|null Configured percentage, or null when the source has no nominal percentage.
     */
    private function get_nominal_saving_percentage( $product, $role, $source, $user_id ) {

        global $wc_wholesale_prices_premium;

        if ( empty( $role ) || ! isset( $wc_wholesale_prices_premium ) ) {
            return null;
        }

        if ( 'wholesale_role_level' === $source || 'per_user_level' === $source ) {

            // get_user_wholesale_role_level_discount() discriminates the source itself and returns
            // the per-user override percentage for 'per_user_level' and the role general discount for
            // 'wholesale_role_level', so both nominal-percentage sources resolve through this branch.
            $role_discount = $wc_wholesale_prices_premium->wwpp_wholesale_price_wholesale_role->get_user_wholesale_role_level_discount( $user_id, $role );

            if ( isset( $role_discount['discount'] ) && is_numeric( $role_discount['discount'] ) ) {
                return (float) $role_discount['discount'];
            }
        } elseif ( 'product_category_level' === $source ) {

            $category_discount = $wc_wholesale_prices_premium->wwpp_wholesale_price_product_category->get_base_term_id_and_wholesale_discount( WWP_Helper_Functions::wwp_get_product_id( $product ), array( $role ) );

            if ( isset( $category_discount['discount'] ) && is_numeric( $category_discount['discount'] ) ) {
                return (float) $category_discount['discount'];
            }
        }

        return null;
    }

    /**
     * Snap a derived saving percentage back to the configured percentage when they differ only by
     * cent-rounding noise.
     *
     * The wholesale price is stored rounded to the store's price decimals, so reverse-deriving the
     * percentage from it can drift from the configured discount (e.g. a 5% discount on a 71.50
     * product renders as 4.99% / 5.01%). When the derived percentage is within the noise a cent of
     * rounding can introduce, the configured percentage is returned; a larger difference means
     * something other than rounding changed the effective price, so the derived value is kept.
     *
     * @since 2.1.2
     * @access private
     *
     * @param float      $computed_percentage Percentage derived from the rounded wholesale price(s).
     * @param float|null $nominal_percentage  Configured percentage to snap to, or null to skip.
     * @param float      $retail_basis        Unit retail price the tolerance is scaled against (the
     *                                        average unit retail for aggregated surfaces).
     * @return float Percentage to display.
     */
    private function snap_saving_percentage_to_nominal( $computed_percentage, $nominal_percentage, $retail_basis ) {

        if ( null === $nominal_percentage || $retail_basis <= 0 ) {
            return $computed_percentage;
        }

        $decimals  = wc_get_price_decimals();
        $tolerance = ( 200 * pow( 10, -$decimals ) ) / $retail_basis;

        if ( abs( $nominal_percentage - $computed_percentage ) <= $tolerance ) {
            return round( $nominal_percentage, $decimals );
        }

        return $computed_percentage;
    }

    /**
     * Track whether every line item shares a single nominal percentage discount.
     *
     * The aggregated saving-amount surfaces (cart, order, invoice email) show one percentage for the
     * whole basket. That aggregate only equals a single configured percentage when every line item is
     * discounted by the same nominal percentage; a per-product fixed price, an undiscounted item, or a
     * differing percentage makes the basket a blend with no single nominal to snap to. This resolves
     * each item's source and nominal percentage and folds the result into the running state.
     *
     * @since 2.1.2
     * @access private
     *
     * @param WC_Product $product             Line item product object.
     * @param string     $role                Single wholesale role key.
     * @param array      $user_wholesale_role Array of user wholesale roles (for source resolution).
     * @param int        $user_id             User whose discount configuration should be read.
     * @param float|null $shared_nominal      Running shared nominal percentage (by reference).
     * @param bool       $nominal_is_uniform  Whether all items share one nominal so far (by reference).
     */
    private function accumulate_nominal_saving_percentage( $product, $role, $user_wholesale_role, $user_id, &$shared_nominal, &$nominal_is_uniform ) {

        // Once the basket is known to be non-uniform the result is sealed; skip further resolution.
        if ( ! $nominal_is_uniform ) {
            return;
        }

        $price_data   = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( WWP_Helper_Functions::wwp_get_product_id( $product ), $user_wholesale_role );
        $item_source  = isset( $price_data['source'] ) ? $price_data['source'] : '';
        $item_nominal = $this->get_nominal_saving_percentage( $product, $role, $item_source, $user_id );

        if ( null === $item_nominal ) {
            $nominal_is_uniform = false;
        } elseif ( null === $shared_nominal ) {
            $shared_nominal = $item_nominal;
        } elseif ( abs( $item_nominal - $shared_nominal ) > 0.0000001 ) {
            $nominal_is_uniform = false;
        }
    }

    /**
     * Apply wholesale saved price on cart and checkout page.
     *
     * @since  1.29
     * @access public
     */
    public function apply_wholesale_saving_amount_on_cart_and_checkout_page() {

        $user_wholesale_role = $this->_get_current_user_wholesale_role();

        if ( ! empty( $user_wholesale_role ) &&
            get_option( 'wwpp_settings_show_saving_amount', false ) === 'yes' &&
            get_option( 'wwpp_settings_show_saving_amount_page_cart', false ) === 'yes'
        ) {

            $tax_display_cart_setting       = $this->_wwpp_tax->wholesale_tax_display_cart( get_option( 'woocommerce_tax_display_cart' ) );
            $cart_subtotal                  = ( 'incl' === $tax_display_cart_setting ) ? WC()->cart->get_subtotal() + WC()->cart->get_subtotal_tax() : WC()->cart->get_subtotal();
            $cart_subtotal_before_wholesale = 0;
            $saving_percentage              = 0;
            $use_regular_price              = get_option( 'wwpp_settings_explicitly_use_product_regular_price_on_discount_calc' );
            $role                           = $user_wholesale_role;
            $role_array                     = array( $user_wholesale_role );
            $user_id                        = get_current_user_id();
            $total_units                    = 0;
            $shared_nominal                 = null;
            $nominal_is_uniform             = true;

            // Loop through the cart items to get the regular price of the products.
            foreach ( WC()->cart->cart_contents as $cart_item ) {
                $product_price = ( $cart_item['data']->is_on_sale() && 'yes' !== $use_regular_price ) ? (float) $cart_item['data']->get_sale_price() : (float) $cart_item['data']->get_regular_price();
                $retail_price  = ( 'incl' === $tax_display_cart_setting ) ? WWP_Helper_Functions::wwp_get_price_including_tax(
                    $cart_item['data'],
                    array(
                        'qty'   => 1,
                        'price' => $product_price,
                    )
                ) : WWP_Helper_Functions::wwp_get_price_excluding_tax(
                    $cart_item['data'],
                    array(
                        'qty'   => 1,
                        'price' => $product_price,
                    )
                );
                $quantity      = $cart_item['quantity'];

                $cart_subtotal_before_wholesale += $retail_price * $quantity;
                $total_units                    += $quantity;

                $this->accumulate_nominal_saving_percentage( $cart_item['data'], $role, $role_array, $user_id, $shared_nominal, $nominal_is_uniform );
            }

            // Check if cart retail subtotal is greater that cart subtotal with wholesale price.
            if ( $cart_subtotal_before_wholesale > $cart_subtotal ) {
                $saved_amount      = $cart_subtotal_before_wholesale - $cart_subtotal;
                $saving_percentage = round( 100 - ( $cart_subtotal / $cart_subtotal_before_wholesale * 100 ), wc_get_price_decimals() );

                if ( $nominal_is_uniform && null !== $shared_nominal && $total_units > 0 ) {
                    $saving_percentage = $this->snap_saving_percentage_to_nominal( $saving_percentage, $shared_nominal, $cart_subtotal_before_wholesale / $total_units );
                }

                // The hook used is an wc action so we should echoing the text instead of returning.
                echo $this->get_saved_amount_text( $saved_amount, $saving_percentage ); // phpcs:ignore.
            }
        }
    }

    /**
     * Apply wholesale saved price on order page.
     *
     * @param WC_Order $order Order object.
     *
     * @since  1.29
     * @access public
     */
    public function apply_wholesale_saving_amount_on_order_page( $order ) {

        $user_wholesale_role = $order->get_meta( 'wwp_wholesale_role' );

        if ( '' !== $user_wholesale_role &&
            get_option( 'wwpp_settings_show_saving_amount', false ) === 'yes' &&
            get_option( 'wwpp_settings_show_saving_amount_page_cart', false ) === 'yes'
        ) {

            $tax_display_cart_setting        = $this->_wwpp_tax->wholesale_tax_display_cart( get_option( 'woocommerce_tax_display_cart' ) );
            $order_subtotal                  = ( 'incl' === $tax_display_cart_setting ) ? $order->get_subtotal() + $order->get_cart_tax() : $order->get_subtotal();
            $order_subtotal_before_wholesale = 0;
            $saving_percentage               = 0;
            $use_regular_price               = get_option( 'wwpp_settings_explicitly_use_product_regular_price_on_discount_calc' );
            $role                            = $user_wholesale_role;
            $role_array                      = array( $user_wholesale_role );
            $user_id                         = $order->get_user_id();
            $total_units                     = 0;
            $shared_nominal                  = null;
            $nominal_is_uniform              = true;

            foreach ( $order->get_items() as $order_item ) {
                $product       = $order_item->get_product();
                $product_price = ( $product->is_on_sale() && 'yes' !== $use_regular_price ) ? (float) $product->get_sale_price() : (float) $product->get_regular_price();
                $retail_price  = ( 'incl' === $tax_display_cart_setting ) ? WWP_Helper_Functions::wwp_get_price_including_tax(
                    $product,
                    array(
                        'qty'   => 1,
                        'price' => $product_price,
                    )
                ) : WWP_Helper_Functions::wwp_get_price_excluding_tax(
                    $product,
                    array(
                        'qty'   => 1,
                        'price' => $product_price,
                    )
                );
                $quantity      = $order_item->get_quantity();

                $order_subtotal_before_wholesale += $retail_price * $quantity;
                $total_units                     += $quantity;

                $this->accumulate_nominal_saving_percentage( $product, $role, $role_array, $user_id, $shared_nominal, $nominal_is_uniform );
            }

            // Check if cart retail subtotal is greater that cart subtotal with wholesale price.
            if ( $order_subtotal_before_wholesale > $order_subtotal ) {
                $saved_amount      = $order_subtotal_before_wholesale - $order_subtotal;
                $saving_percentage = round( 100 - ( $order_subtotal / $order_subtotal_before_wholesale * 100 ), wc_get_price_decimals() );

                if ( $nominal_is_uniform && null !== $shared_nominal && $total_units > 0 ) {
                    $saving_percentage = $this->snap_saving_percentage_to_nominal( $saving_percentage, $shared_nominal, $order_subtotal_before_wholesale / $total_units );
                }

                // The hook used is an wc action so we should echoing the text instead of returning.
                echo $this->get_saved_amount_text( $saved_amount, $saving_percentage ); // phpcs:ignore.
            }
        }
    }

    /**
     * Apply wholesale saved price on order page and email invoice.
     *
     * @param WC_Order $order         Order object.
     * @param bool     $sent_to_admin Is email sent to admin.
     * @param string   $plain_text    Plain text.
     * @param string   $email         Email.
     *
     * @since  1.29
     * @access public
     */
    public function apply_wholesale_saving_amount_on_email_invoice( $order, $sent_to_admin, $plain_text, $email ) { // phpcs:ignore.

        $user_wholesale_role = $order->get_meta( 'wwp_wholesale_role' );

        if ( '' !== $user_wholesale_role &&
            ! $sent_to_admin &&
            get_option( 'wwpp_settings_show_saving_amount', false ) === 'yes' &&
            get_option( 'wwpp_settings_show_saving_amount_page_invoice', false ) === 'yes'
        ) {

            $tax_display_cart_setting        = $this->_wwpp_tax->wholesale_tax_display_cart( get_option( 'woocommerce_tax_display_cart' ) );
            $order_subtotal                  = ( 'incl' === $tax_display_cart_setting ) ? $order->get_subtotal() + $order->get_cart_tax() : $order->get_subtotal();
            $order_subtotal_before_wholesale = 0;
            $saving_percentage               = 0;
            $use_regular_price               = get_option( 'wwpp_settings_explicitly_use_product_regular_price_on_discount_calc' );
            $role                            = $user_wholesale_role;
            $role_array                      = array( $user_wholesale_role );
            $user_id                         = $order->get_user_id();
            $total_units                     = 0;
            $shared_nominal                  = null;
            $nominal_is_uniform              = true;

            foreach ( $order->get_items() as $order_item ) {
                $product       = $order_item->get_product();
                $product_price = ( $product->is_on_sale() && 'yes' !== $use_regular_price ) ? (float) $product->get_sale_price() : (float) $product->get_regular_price();
                $retail_price  = ( 'incl' === $tax_display_cart_setting ) ? WWP_Helper_Functions::wwp_get_price_including_tax(
                    $product,
                    array(
                        'qty'   => 1,
                        'price' => $product_price,
                    )
                ) : WWP_Helper_Functions::wwp_get_price_excluding_tax(
                    $product,
                    array(
                        'qty'   => 1,
                        'price' => $product_price,
                    )
                );
                $quantity      = $order_item->get_quantity();

                $order_subtotal_before_wholesale += $retail_price * $quantity;
                $total_units                     += $quantity;

                $this->accumulate_nominal_saving_percentage( $product, $role, $role_array, $user_id, $shared_nominal, $nominal_is_uniform );
            }

            // Check if cart retail subtotal is greater that cart subtotal with wholesale price.
            if ( $order_subtotal_before_wholesale > $order_subtotal ) {
                $saved_amount      = $order_subtotal_before_wholesale - $order_subtotal;
                $saving_percentage = round( 100 - ( $order_subtotal / $order_subtotal_before_wholesale * 100 ), wc_get_price_decimals() );

                if ( $nominal_is_uniform && null !== $shared_nominal && $total_units > 0 ) {
                    $saving_percentage = $this->snap_saving_percentage_to_nominal( $saving_percentage, $shared_nominal, $order_subtotal_before_wholesale / $total_units );
                }

                // The hook used is an wc action so we should echoing the text instead of returning.
                echo $this->get_saved_amount_text( $saved_amount, $saving_percentage, true ); // phpcs:ignore.
            }
        }
    }

    /**
     * Get the saved amount html markup in various pages.
     *
     * @param float $saved_amount     The saved amount.
     * @param float $saved_percentage The saved percentage.
     * @param bool  $email_invoice    is applied on email invoice.
     *
     * @since  1.29
     * @access public
     *
     * @return string
     */
    public function get_saved_amount_text( $saved_amount, $saved_percentage, $email_invoice = null ) {

        $saved_amount_text = get_option( 'wwpp_settings_show_saving_amount_text' );

        // Check if setting value contain including tax tag {saved_amount}.
        if ( str_contains( $saved_amount_text, '{saved_amount}' ) ) {
            $saved_amount_text = str_replace( '{saved_amount}', '<strong>' . wc_price( $saved_amount, array() ) . '</strong>', $saved_amount_text );
        }

        // Check if setting value contain excluding tax tag {saved_percentage}.
        if ( str_contains( $saved_amount_text, '{saved_percentage}' ) ) {
            $saved_amount_text = str_replace( '{saved_percentage}', '<strong>' . $saved_percentage . '%</strong>', $saved_amount_text );
        }

        // If shown in cart, checkout and order received page.
        if ( ( is_checkout() || is_cart() ) && ! $email_invoice ) {

            $saved_amount_text = '<tr class="cart-saved-wholesale-price">
                    <td colspan="2" class="wholesale_saved_price_container"><span class="wholesale_saved_price_text">' . $saved_amount_text . '</span></td>
                </tr>';

            // If the saved amount text is shown on the order received page, then add table html markup.
            if ( is_order_received_page() ) {
                $saved_amount_text = '<table>' . $saved_amount_text . '</table>';
            }
        } elseif ( ( ! is_checkout() || ! is_cart() ) && $email_invoice ) { // If shown in email invoice.
            $saved_amount_text = str_replace( '<strong>', "<strong style='color: #159967'>", $saved_amount_text );

            $saved_amount_text = '<table style="color:#636363;vertical-align:middle;width:100%;margin-bottom:40px;font-family:\'Helvetica Neue\',Helvetica,Roboto,Arial,sans-serif;" width="100%">
                    <tbody>
                        <tr>
                            <td colspan="2" style="background-color: #ebf7ed;text-align: center;"><span>' . $saved_amount_text . '</span></td>
                        </tr>
                    </tbody>
                </table>';
        } else { // If shown in single product and shop page.
            $saved_amount_text = ' <span class="wholesale_saved_price_container"><small class="wholesale_saved_price_text">' . $saved_amount_text . '</small></span>';
        }

        return apply_filters( 'wwpp_saved_amount_text_html', $saved_amount_text, $saved_amount, $saved_percentage, $email_invoice );
    }

    /**
     * Override the stock display format for wholesale customers.
     *
     * @param string     $availability Display format.
     * @param WC_Product $product      WC Product instance.
     *
     * @since  1.28
     * @access public
     *
     * @return string Filtered display format.
     */
    public function override_stock_display_format_for_wholesale_roles( $availability, $product ) {

        $user_wholesale_role = $this->_get_current_user_wholesale_role();

        if ( ! empty( $user_wholesale_role ) ) {

            if ( ! $product->is_in_stock()
                || ( $product->managing_stock() && $product->is_on_backorder( 1 ) )
                || ( ! $product->managing_stock() && $product->is_on_backorder( 1 ) )
            ) {
                return $availability;
            }

            if ( $product->managing_stock() ) {

                $wwpp_display_format_option = get_option( 'wwpp_settings_override_stock_display_format' );

                if ( ! empty( $wwpp_display_format_option ) ) {

                    $stock_amount = $product->get_stock_quantity();

                    switch ( $wwpp_display_format_option ) {
                        case 'amount':
                            /* translators: %s: stock amount */
                            $availability = sprintf( __( '%s in stock', 'woocommerce' ), wc_format_stock_quantity_for_display( $stock_amount, $product ) );
                            break;
                        case 'low_amount':
                            if ( $stock_amount <= wc_get_low_stock_amount( $product ) ) {
                                /* translators: %s: stock amount */
                                $availability = sprintf( __( 'Only %s left in stock', 'woocommerce' ), wc_format_stock_quantity_for_display( $stock_amount, $product ) );
                            }
                            break;
                        case 'no_amount':
                            $availability = __( 'In stock', 'woocommerce' );
                            break;
                    }

                    if ( $product->backorders_allowed() && $product->backorders_require_notification() ) {
                        $availability .= ' ' . __( '(can be backordered)', 'woocommerce' );
                    }
                }
            }
        }

        return apply_filters( 'wwpp_override_stock_display_format_for_wholesale_roles', $availability, $product, $user_wholesale_role );
    }

    /**
     * Get the wholesale sale price data.
     * Returns an array containing wholesale sale price both passed through and not passed through taxing,
     * and the value of the sale date in WC_DateTime object.
     *
     * @param int   $product_id          The product id.
     * @param array $user_wholesale_role The user wholesale role.
     *
     * @since  1.30.1
     * @access public
     *
     * @return array|null Array of wholesale sale price data or null.
     */
    public static function get_product_wholesale_sale_price( $product_id, $user_wholesale_role ) {

        $wholesale_sale_price_arr = array();
        $wholesale_sale_price     = self::get_product_raw_wholesale_sale_price( $product_id, $user_wholesale_role );
        $product                  = wc_get_product( $product_id );

        if ( '' !== $wholesale_sale_price ) {
            if ( WWP_Helper_Functions::is_plugin_active( 'woocommerce-currency-switcher/index.php' ) &&
                ! WWP_Helper_Functions::is_plugin_active( 'woocommerce-aelia-currencyswitcher/woocommerce-aelia-currencyswitcher.php' )
            ) {
                $wholesale_sale_price = apply_filters( 'woocommerce_product_get_price', $wholesale_sale_price, $product );
            }

            $wholesale_sale_price_arr['wholesale_sale_price'] = trim( apply_filters( 'wwp_pass_wholesale_price_through_taxing', $wholesale_sale_price, $product_id, $user_wholesale_role ) );
            $sale_price_dates_from_timestamp                  = $product->get_meta( $user_wholesale_role[0] . '_wholesale_sale_price_dates_from', true );
            $sale_price_dates_to_timestamp                    = $product->get_meta( $user_wholesale_role[0] . '_wholesale_sale_price_dates_to', true );

            // If the product price is inclusive of tax, then use the calculated wholesale_price here cause it has been deducted by tax.
            if ( wc_prices_include_tax() && $wholesale_sale_price ) {
                $wholesale_sale_price_arr['wholesale_sale_price_with_no_tax'] = WWP_Helper_Functions::wwp_get_price_excluding_tax(
                    $product,
                    array(
                        'qty'   => 1,
                        'price' => $wholesale_sale_price,
                    )
                );
            } else {
                $wholesale_sale_price_arr['wholesale_sale_price_with_no_tax'] = $wholesale_sale_price;
            }

            $wholesale_sale_price_arr['wholesale_sale_price_with_tax'] = WWP_Helper_Functions::wwp_get_price_including_tax(
                $product,
                array(
                    'qty'   => 1,
                    'price' => $wholesale_sale_price,
                )
            );

            if ( '' !== $sale_price_dates_from_timestamp ) {
                $sale_price_dates_from = $sale_price_dates_from_timestamp ? date_i18n( 'Y-m-d H:i:s', $sale_price_dates_from_timestamp ) : '';

                $wholesale_sale_price_arr['wholesale_sale_price_date_on_sale_from'] = wc_string_to_datetime( $sale_price_dates_from );
            } else {
                $wholesale_sale_price_arr['wholesale_sale_price_date_on_sale_from'] = null;
            }

            if ( '' !== $sale_price_dates_to_timestamp ) {
                $sale_price_dates_to = $sale_price_dates_to_timestamp ? date_i18n( 'Y-m-d H:i:s', $sale_price_dates_to_timestamp ) : '';

                $wholesale_sale_price_arr['wholesale_sale_price_date_on_sale_to'] = wc_string_to_datetime( $sale_price_dates_to );
            } else {
                $wholesale_sale_price_arr['wholesale_sale_price_date_on_sale_to'] = null;
            }

            $wholesale_sale_price_arr['is_on_sale'] = $product->get_meta( $user_wholesale_role[0] . '_have_on_sale_wholesale_sale_price', true ) === 'yes' ? true : false;

        }

        /**
         * Filter the wholesale sale price data.
         *
         * @param array  $wholesale_sale_price_arr Array of wholesale sale price data.
         * @param float  $wholesale_sale_price     Wholesale sale price.
         * @param string $sale_price_dates_from    Sale price date from.
         * @param string $sale_price_dates_to      Sale price date to.
         * @param object $product                  The product object.
         * @param array  $user_wholesale_role      The user wholesale role.
         */
        return ! empty( $wholesale_sale_price_arr ) ? apply_filters( 'wwpp_filter_product_wholesale_sale_price', $wholesale_sale_price_arr, $wholesale_sale_price, $sale_price_dates_from_timestamp, $sale_price_dates_to_timestamp, $product, $user_wholesale_role ) : null;
    }

    /**
     * Get product raw wholesale sale price. Without being passed through any filter.
     *
     * @param int   $product_id          The product id.
     * @param array $user_wholesale_role The user wholesale role.
     *
     * @since  1.30.1
     * @access public
     *
     * @return float Unfiltered product raw wholesale sale price.
     */
    public static function get_product_raw_wholesale_sale_price( $product_id, $user_wholesale_role ) {

        if ( empty( $user_wholesale_role ) ) {
            $wholesale_sale_price = '';
        } elseif ( WWP_ACS_Integration_Helper::aelia_currency_switcher_active() ) {
            $baseCurrencyWholesalePrice = get_post_meta( $product_id, $user_wholesale_role[0] . '_wholesale_sale_price', true );
            $wholesale_sale_price       = $baseCurrencyWholesalePrice;

            if ( $baseCurrencyWholesalePrice ) {

                $activeCurrency = get_woocommerce_currency();
                $baseCurrency   = WWP_ACS_Integration_Helper::get_product_base_currency( $product_id );

                if ( $activeCurrency === $baseCurrency ) {
                    $wholesale_sale_price = $baseCurrencyWholesalePrice;
                } else {
                    // Base Currency.
                    $wholesale_sale_price = get_post_meta( $product_id, $user_wholesale_role[0] . '_' . $activeCurrency . '_wholesale_sale_price', true );

                    if ( ! $wholesale_sale_price ) {

                        /**
                         * This specific currency has no explicit wholesale price (Auto). Therefore will need to convert the wholesale price
                         * set on the base currency to this specific currency.
                         *
                         * This is why it is very important users set the wholesale price for the base currency if they want wholesale pricing
                         * to work properly with aelia currency switcher plugin integration.
                         */
                        $wholesale_sale_price = WWP_ACS_Integration_Helper::convert( $baseCurrencyWholesalePrice, $activeCurrency, $baseCurrency );

                    }
                }
            } else {
                $wholesale_sale_price = '';
            }
            // Base currency not set. Ignore the rest of the wholesale price set on other currencies.
        } else {
            $wholesale_sale_price = get_post_meta( $product_id, $user_wholesale_role[0] . '_wholesale_sale_price', true );
        }

        /**
         * Filter the product raw wholesale sale price.
         *
         * @param float $wholesale_sale_price Wholesale sale price.
         * @param int   $product_id           The product id.
         * @param array $user_wholesale_role  The user wholesale role.
         */
        return apply_filters( 'wwpp_filter_product_raw_wholesale_sale_price', $wholesale_sale_price, $product_id, $user_wholesale_role );
    }

    /**
     * Filter callback that alters the product price, it embeds the wholesale sale price of a product for a wholesale
     * user.
     *
     * @param string     $wholesale_price_html        Wholesale price html.
     * @param string     $price                       Active price html( non wholesale ).
     * @param WC_Product $product                     WC_Product object.
     * @param array      $user_wholesale_role         Array user wholesale roles.
     * @param string     $wholesale_price_title_text  Wholesale price title text.
     * @param float      $raw_wholesale_price         Raw wholesale price.
     * @param string     $source                      Source of the wholesale price being applied.
     * @param boolean    $return_wholesale_price_only Whether to only return the wholesale price markup. Used for
     *                                                products cpt listing.
     * @param string     $wholesale_price             String of the wholesale price only without container.
     *
     * @since  1.30.1
     * @access public
     *
     * @return string Filtered wholesale price html with wholesale sale price.
     */
    public function apply_wholesale_sale_price_on_single_and_shop_page( $wholesale_price_html, $price, $product, $user_wholesale_role, $wholesale_price_title_text, $raw_wholesale_price, $source, $return_wholesale_price_only = false, $wholesale_price = '' ) {

        $formatted_wholesale_sale_price = '';

        if ( in_array( $product->get_type(), array( 'simple', 'variation' ), true ) ) {

            $wholesale_sale_price_arr = self::get_product_wholesale_sale_price( $product->get_ID(), $user_wholesale_role );

            if ( null !== $wholesale_sale_price_arr && true === $wholesale_sale_price_arr['is_on_sale'] ) {
                $formatted_wholesale_sale_price = WWP_Helper_Functions::wwp_formatted_price( $wholesale_sale_price_arr['wholesale_sale_price'] );

                if ( ! $return_wholesale_price_only ) {
                    $formatted_wholesale_sale_price .= WWP_Wholesale_Prices::get_wholesale_price_suffix( $product, $user_wholesale_role, $wholesale_sale_price_arr['wholesale_sale_price_with_no_tax'] );
                }
            }
        } elseif ( $product->get_type() === 'variable' ) {

            $variations_have_on_sale_wholesale_sale_price = $product->get_meta( "{$user_wholesale_role[0]}_variations_have_on_sale_wholesale_sale_price", true );

            if ( 'yes' === $variations_have_on_sale_wholesale_sale_price ) {

                $variations                              = WWP_Helper_Functions::wwp_get_variable_product_variations( $product );
                $display_mode                            = get_option( 'wwpp_settings_variable_product_price_display' );
                $wc_price_suffix                         = get_option( 'woocommerce_price_display_suffix' );
                $min_sale_price                          = '';
                $min_wholesale_sale_price_without_taxing = '';
                $max_sale_price                          = '';
                $max_wholesale_sale_price_without_taxing = '';

                foreach ( $variations as $variation ) {

                    if ( ! $variation['is_purchasable'] ) {
                        continue;
                    }

                    $curr_var_sale_price      = $variation['display_price'];
                    $price_arr                = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $variation['variation_id'], $user_wholesale_role );
                    $wholesale_sale_price_arr = self::get_product_wholesale_sale_price( $variation['variation_id'], $user_wholesale_role );

                    if ( strcasecmp( $price_arr['wholesale_price'], '' ) !== 0 ) {
                        $curr_var_sale_price = $price_arr['wholesale_price'];
                    }

                    if ( null !== $wholesale_sale_price_arr && true === $wholesale_sale_price_arr['is_on_sale'] ) {

                        $curr_var_sale_price = $wholesale_sale_price_arr['wholesale_sale_price'];

                    }

                    if ( strcasecmp( $min_sale_price, '' ) === 0 || $curr_var_sale_price < $min_sale_price ) {

                        $min_sale_price                          = $curr_var_sale_price;
                        $min_wholesale_sale_price_without_taxing = null !== $wholesale_sale_price_arr && 0 !== $wholesale_sale_price_arr['wholesale_sale_price_with_no_tax'] ? $wholesale_sale_price_arr['wholesale_sale_price_with_no_tax'] : '';

                    }

                    if ( strcasecmp( $max_sale_price, '' ) === 0 || $curr_var_sale_price > $max_sale_price ) {

                        $max_sale_price                          = $curr_var_sale_price;
                        $max_wholesale_sale_price_without_taxing = null !== $wholesale_sale_price_arr && 0 !== $wholesale_sale_price_arr['wholesale_sale_price_with_no_tax'] ? $wholesale_sale_price_arr['wholesale_sale_price_with_no_tax'] : '';

                    }
                }

                // Only alter price html if, some/all variations of this variable product have sale price and min and max price have valid values.
                if ( 'yes' === $variations_have_on_sale_wholesale_sale_price && strcasecmp( $min_sale_price, '' ) !== 0 && strcasecmp( $max_sale_price, '' ) !== 0 ) {
                    if ( $min_sale_price !== $max_sale_price && $min_sale_price < $max_sale_price ) {
                        switch ( $display_mode ) {
                            case 'minimum':
                                $formatted_wholesale_sale_price = WWP_Helper_Functions::wwp_formatted_price( $min_sale_price );
                                break;
                            case 'maximum':
                                $formatted_wholesale_sale_price = WWP_Helper_Functions::wwp_formatted_price( $max_sale_price );
                                break;
                            default:
                                $formatted_wholesale_sale_price = WWP_Helper_Functions::wwp_formatted_price( $min_sale_price ) . ' - ' . WWP_Helper_Functions::wwp_formatted_price( $max_sale_price );
                                break;
                        }

                        if ( ! str_contains( $wc_price_suffix, '{price_including_tax}' ) && ! str_contains( $wc_price_suffix, '{price_excluding_tax}' ) ) {
                            $wsprice = ! empty( $max_wholesale_price_without_taxing ) ? $max_wholesale_price_without_taxing : null;

                            if ( ! $return_wholesale_price_only ) {
                                $formatted_wholesale_sale_price .= WWP_Wholesale_Prices::get_wholesale_price_suffix( $product, $user_wholesale_role, $wsprice );
                            }
                        }
                    } else {
                        $formatted_wholesale_sale_price = WWP_Helper_Functions::wwp_formatted_price( $max_sale_price );
                    }
                }
            }
        }

        if ( strcasecmp( $formatted_wholesale_sale_price, '' ) !== 0 ) {

            // Since WWP 2.1.6.3 the $wholesale_price added as parameter to the filter, so we can use it instead of doing DOMXPath to get the wholesale price.
            // Get the wholesale price ins tag price using DOMXPath, so no need to do extra calculation for the wholesale price in this section, very useful for variable product.
            if ( '' === $wholesale_price ) {
                $dom = new DOMDocument();
                $dom->loadHTML( mb_convert_encoding( $wholesale_price_html, 'HTML-ENTITIES', 'UTF-8' ), LIBXML_NOERROR );
                $xpath           = new DOMXPath( $dom );
                $ins             = $xpath->query( '//ins' );
                $ins             = $ins->item( 0 );
                $ins             = $dom->saveHTML( $ins );
                $ins             = preg_replace( '/<ins[^>]*>/', '', $ins );
                $ins             = str_replace( '</ins>', '', $ins );
                $wholesale_price = html_entity_decode( $ins );
            }

            $wholesale_price_html = '<span style="display: block;" class="wholesale_price_container wholesale_price_container--onsale">
                                        <span class="wholesale_price_title">' . $wholesale_price_title_text . '</span>
                                        <del>' . $wholesale_price . '</del> <ins>' . $formatted_wholesale_sale_price . '</ins>
                                    </span>';

            /**
             * Filter wholesale sale price html.
             *
             * @param string     $wholesale_price_html           The Wholesale price markup.
             * @param WC_Product $product                        Product object.
             * @param array      $user_wholesale_role            Array of user wholesale roles.
             * @param string     $wholesale_price_title_text     Wholesale price title text.
             * @param string     $price                          Original product price.
             * @param string     $formatted_wholesale_sale_price Formatted wholesale sale price.
             * @param string     $wholesale_price                Formatted wholesale price.
             */
            $wholesale_price_html = apply_filters( 'wwpp_filter_wholesale_sale_price_html', $wholesale_price_html, $product, $user_wholesale_role, $wholesale_price_title_text, $price, $formatted_wholesale_sale_price, $wholesale_price );
        }

        return $wholesale_price_html;
    }

    /**
     * The wholesale sale product is defined as a sale product when the wholesale sale price is set.
     * Woocommerce will recognize the product as sale product even if the regular sale price is not being set, that
     * will cause the regular price shows twice. Here, we temporary remove the  WWPP filter then remove the woocommerce
     * regular sale price html markup using preg_replace
     *
     * @param string     $original_price      Crossed out original price html.
     * @param float      $wholesale_price     wholesale price.
     * @param float      $price               Original price.
     * @param WC_Product $product             Product object.
     * @param array      $user_wholesale_role User wholesale role.
     *
     * @since  1.30.1
     * @access public
     *
     * @return string Filtered crossed out original price html.
     */
    public function filter_product_original_price_for_wholesale_sale_price( $original_price, $wholesale_price, $price, $product, $user_wholesale_role ) { // phpcs:ignore.
        remove_filter( 'woocommerce_product_is_on_sale', array( $this, 'apply_on_sale_badge_on_wholesale_sale_products' ), 10, 2 );
        if ( ! $product->is_on_sale() ) {
            $original_price = preg_replace( '#<del aria-hidden="true">(.*?)</del>#', '', $original_price );
        }
        add_filter( 'woocommerce_product_is_on_sale', array( $this, 'apply_on_sale_badge_on_wholesale_sale_products' ), 10, 2 );

        return $original_price;
    }

    /**
     * Apply wholesale sale price for product on cart.
     *
     * @param array   $wholesale_price_arr Wholesale price array data.
     * @param int     $product_id          Product Id.
     * @param array   $user_wholesale_role Array of user wholesale role.
     * @param WC_Cart $cart_item           WC_Cart object.
     *
     * @since  1.30.1
     * @access public
     *
     * @return array Filtered wholesale price array data.
     */
    public function apply_wholesale_sale_price_on_cart( $wholesale_price_arr, $product_id, $user_wholesale_role, $cart_item ) { // phpcs:ignore.
        if ( ! empty( $user_wholesale_role ) && ! empty( $wholesale_price_arr['wholesale_price'] ) ) {

            $raw_wholesale_sale_price = self::get_product_raw_wholesale_sale_price( $product_id, $user_wholesale_role );
            $is_on_wholesale_sale     = get_post_meta( $product_id, "{$user_wholesale_role[0]}_have_on_sale_wholesale_sale_price", true );

            if ( '' !== $raw_wholesale_sale_price && 'yes' === $is_on_wholesale_sale ) {
                $wholesale_price_arr['wholesale_price'] = $raw_wholesale_sale_price;
            }
        }

        return $wholesale_price_arr;
    }

    /**
     * Apply sale badge if product has wholesale sale price.
     *
     * @param bool       $on_sale Wholesale price array data.
     * @param WC_Product $product WC_Product object.
     *
     * @since  1.30.1
     * @access public
     *
     * @return bool True if on sale, false otherwise.
     */
    public function apply_on_sale_badge_on_wholesale_sale_products( $on_sale, $product ) {

        $user_wholesale_role  = $this->_get_current_user_wholesale_role();
        $is_on_wholesale_sale = '';

        if ( ! empty( $user_wholesale_role ) ) {
            if ( in_array( $product->get_type(), array( 'simple', 'variation' ), true ) ) {
                $is_on_wholesale_sale = $product->get_meta( "{$user_wholesale_role}_have_on_sale_wholesale_sale_price", true );
            } elseif ( $product->get_type() === 'variable' ) {
                $is_on_wholesale_sale = $product->get_meta( "{$user_wholesale_role}_variations_have_on_sale_wholesale_sale_price", true );
            }

            if ( isset( $is_on_wholesale_sale ) && 'yes' === $is_on_wholesale_sale ) {
                $on_sale = true;
            }
        }

        /**
         * Filter to apply sale badge on wholesale sale products.
         *
         * @param bool       $on_sale              True if on sale, false otherwise.
         * @param WC_Product $product              WC_Product object.
         * @param array      $user_wholesale_role  Array of user wholesale role.
         * @param string     $is_on_wholesale_sale Is on wholesale sale.
         */
        return apply_filters( 'wwpp_apply_sale_badge_on_wholesale_sale_products', $on_sale, $product, $user_wholesale_role, $is_on_wholesale_sale );
    }

    /**
     * Cron entry point that starts/ends scheduled wholesale sales.
     *
     * Hooked to the daily `woocommerce_scheduled_sales` cron. For each registered
     * wholesale role it runs one bounded `INNER JOIN ... DISTINCT` scan for products
     * flagged as having a wholesale sale price, then processes the matched ids in
     * fixed-size Action Scheduler batches so the cron tick never blocks on a
     * full-catalog scan plus per-product hydration.
     *
     * The migration path ({@see WWPP_Bootstrap}) and the admin "update wholesale
     * sales" tool ({@see WWPP_Admin_Action_Settings}) pass `$run_synchronously = true`
     * so the on-sale flags are fully recomputed before they return their success
     * status. When Action Scheduler is unavailable the batches also run inline.
     *
     * @since  1.30.1
     * @since  2.1.0 Batched via Action Scheduler; reads variation date metas without
     *               hydrating full variation data; saves only when the on-sale flag
     *               actually changes.
     * @access public
     *
     * @param bool $run_synchronously Process the batches inline instead of enqueuing
     *                                them. Used by the migration and admin-tool callers.
     */
    public function scheduled_wholesale_sales( $run_synchronously = false ) {

        global $wpdb;

        $can_enqueue = ! $run_synchronously && function_exists( 'as_enqueue_async_action' );

        /**
         * Number of products processed per scheduled-wholesale-sales batch.
         *
         * @since 2.1.0
         *
         * @param int $chunk_size Products per batch.
         */
        $chunk_size = (int) apply_filters( 'wwpp_scheduled_wholesale_sales_chunk_size', self::SCHEDULED_SALES_CHUNK_SIZE );
        $chunk_size = $chunk_size > 0 ? $chunk_size : self::SCHEDULED_SALES_CHUNK_SIZE;

        foreach ( $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles() as $role_key => $role ) {

            // Bounded scan for products/variations flagged as having a wholesale sale price.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $product_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT posts.ID FROM {$wpdb->posts} AS posts
                            INNER JOIN {$wpdb->postmeta} AS postmeta ON posts.ID = postmeta.post_id
                            WHERE posts.post_type IN ('product', 'product_variation')
                                AND postmeta.meta_value = 'yes'
                                AND postmeta.meta_key IN ( %s, %s )",
                    $role_key . '_have_wholesale_sale_price',
                    $role_key . '_variations_have_wholesale_sale_price'
                )
            );

            if ( empty( $product_ids ) ) {
                continue;
            }

            foreach ( array_chunk( $product_ids, $chunk_size ) as $chunk ) {
                if ( $can_enqueue ) {
                    // Skip enqueuing when an identical batch is already pending so overlapping
                    // cron ticks don't stack duplicate (idempotent) work.
                    if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action(
                        self::SCHEDULED_SALES_BATCH_ACTION,
                        array( $role_key, $chunk ),
                        self::SCHEDULED_SALES_GROUP
                    ) ) {
                        continue;
                    }

                    as_enqueue_async_action(
                        self::SCHEDULED_SALES_BATCH_ACTION,
                        array( $role_key, $chunk ),
                        self::SCHEDULED_SALES_GROUP
                    );
                } else {
                    $this->process_scheduled_wholesale_sales_batch( $role_key, $chunk );
                }
            }
        }
    }

    /**
     * Process one batch of products for scheduled wholesale sales.
     *
     * Shared by the synchronous call sites and the
     * `wwpp_scheduled_wholesale_sales_batch` Action Scheduler job. Recomputes the
     * per-role on-sale flag for each product and saves only when the flag actually
     * changed (no `post_modified` churn). Variation date metas are read via
     * `get_children()` plus a single primed `update_meta_cache()` call — never via
     * `get_available_variations()`, which would build full variation arrays and
     * render price HTML.
     *
     * @since  2.1.0
     * @since  2.1.1 Skip variations the display never renders as on sale when computing the parent's
     *               on-sale flag — mirroring {@see WWPP_Wholesale_Prices::apply_wholesale_sale_price_on_single_and_shop_page()},
     *               which iterates get_available_variations() (out-of-stock excluded only when the
     *               hide-out-of-stock setting is on) and then skips non-purchasable variations.
     * @access public
     *
     * @param string $role_key    Wholesale role key.
     * @param array  $product_ids Product/variation IDs to process.
     */
    public function process_scheduled_wholesale_sales_batch( $role_key, $product_ids ) {

        if ( empty( $role_key ) || ! is_array( $product_ids ) || empty( $product_ids ) ) {
            return;
        }

        // Prime the parent meta cache so the per-product get_meta() reads below hit cache.
        update_meta_cache( 'post', $product_ids );

        $now = time();

        foreach ( $product_ids as $product_id ) {

            $product = wc_get_product( $product_id );

            if ( ! $product instanceof WC_Product ) {
                continue;
            }

            $product_type = $product->get_type();

            if ( in_array( $product_type, array( 'simple', 'variation' ), true ) ) {

                $meta_key    = "{$role_key}_have_on_sale_wholesale_sale_price";
                $is_on_sale  = $this->is_within_wholesale_sale_window(
                    $product->get_meta( "{$role_key}_wholesale_sale_price_dates_from", true ),
                    $product->get_meta( "{$role_key}_wholesale_sale_price_dates_to", true ),
                    $now
                );
                $was_on_sale = 'yes' === $product->get_meta( $meta_key, true );

            } elseif ( 'variable' === $product_type ) {

                $meta_key   = "{$role_key}_variations_have_on_sale_wholesale_sale_price";
                $child_ids  = $product->get_children();
                $is_on_sale = false;

                if ( ! empty( $child_ids ) ) {

                    // Prime the variation meta cache in one query before the per-child reads.
                    update_meta_cache( 'post', $child_ids );

                    // The display path iterates get_available_variations(), which drops out-of-stock
                    // variations only when "Hide out of stock items from the catalog" is enabled.
                    $hide_out_of_stock = 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' );

                    foreach ( $child_ids as $child_id ) {

                        $child_product = wc_get_product( $child_id );

                        // Mirror the display's inclusion criteria in apply_wholesale_sale_price_on_single_and_shop_page():
                        // it iterates get_available_variations() (out-of-stock variations excluded only when the
                        // hide-out-of-stock setting is on) and then skips non-purchasable ones. Matching both keeps the
                        // parent flag — and the sale badge, which reads the flag alone — from being set for a variation
                        // the front end never renders as on sale.
                        if ( ! $child_product instanceof WC_Product
                            || ! $child_product->is_purchasable()
                            || ( $hide_out_of_stock && ! $child_product->is_in_stock() ) ) {
                            continue;
                        }

                        if ( $this->is_within_wholesale_sale_window(
                            get_post_meta( $child_id, "{$role_key}_wholesale_sale_price_dates_from", true ),
                            get_post_meta( $child_id, "{$role_key}_wholesale_sale_price_dates_to", true ),
                            $now
                        ) ) {
                            $is_on_sale = true;
                            break;
                        }
                    }
                }

                $was_on_sale = 'yes' === $product->get_meta( $meta_key, true );

            } else {
                continue;
            }

            // Only write when the on-sale flag actually changed.
            if ( $is_on_sale === $was_on_sale ) {
                continue;
            }

            if ( $is_on_sale ) {
                $product->update_meta_data( $meta_key, 'yes' );
            } else {
                $product->delete_meta_data( $meta_key );
            }

            $product->save();
        }
    }

    /**
     * Determine whether "now" falls within a wholesale sale's start/end window.
     *
     * Mirrors the original cron semantics: an empty bound is treated as open-ended on
     * that side, so a configured wholesale sale price with no dates is always active.
     *
     * @since  2.1.0
     * @access private
     *
     * @param string|int $date_from Sale start timestamp, or empty for no start bound.
     * @param string|int $date_to   Sale end timestamp, or empty for no end bound.
     * @param int        $now       Current timestamp.
     *
     * @return bool True when the sale is currently active.
     */
    private function is_within_wholesale_sale_window( $date_from, $date_to, $now ) {

        if ( $date_from && (int) $date_from > $now ) {
            return false;
        }

        if ( $date_to && (int) $date_to < $now ) {
            return false;
        }

        return true;
    }

    /**
     * Filter the product price in the cart.
     *
     * Determines whether the cart item fails its wholesale minimum requirements
     * before computing any wholesale/sale/quantity-discount price. When the
     * requirements are met (the common case) the unchanged price HTML is returned
     * without running the pricing pipeline, so the expensive price lookups only run
     * for rows that will actually render the "minimum requirement not met" notice.
     *
     * @param string $price_html    Price HTML.
     * @param array  $cart_item     Cart item data.
     * @param string $cart_item_key Cart item key.
     *
     * @since 1.30.2
     * @since 2.1.1 Evaluate the minimum-requirement check first and fetch wholesale/sale/quantity-discount
     *              prices only when the not-met notice will be rendered.
     * @since 2.1.1 Exempt bundled/composite child items from the MOQ price-display gate. (WWPP-1828)
     *
     * @return string
     */
    public function filter_woocommerce_cart_item_price( $price_html, $cart_item, $cart_item_key ) { // phpcs:ignore.
        global $woocommerce;
        global $wc_wholesale_prices;

        $user_wholesale_role = $this->_get_current_user_wholesale_role();

        if ( is_cart() && ! empty( $user_wholesale_role ) ) {
            $did_not_meet = false;

            // Evaluate the minimum-requirement checks first. These rely only on cart totals
            // (preloaded once per request) and per-product quantity meta, so the full pricing
            // pipeline is not needed to decide whether a notice will be rendered.
            $apply_wholesale_price_cart_level = WWPP_Helper_Functions::apply_wholesale_price_per_cart_level_min_condition( $this->preload_calculate_cart_total, $woocommerce->cart, array( $user_wholesale_role ) );

            if ( true !== $apply_wholesale_price_cart_level ) {
                $did_not_meet = true;
            }

            $apply_wholesale_price_product_level = WWPP_Helper_Functions::apply_wholesale_price_per_product_level_min_condition( $cart_item, $woocommerce->cart, array( $user_wholesale_role ), 0 );

            if ( $apply_wholesale_price_product_level ) {
                $did_not_meet = true;
            }

            // Bundled (WC Product Bundles) and composite (WC Composite Products) child items have their
            // quantity controlled by the parent, so the standalone wholesale minimum order quantity does not
            // gate their wholesale pricing — see set_cart_item_quantity() and
            // filter_if_apply_wholesale_price_per_product_level() in class-wwpp-wholesale-price-requirement.php,
            // both of which exempt these children unconditionally. Clear the "did not meet" flag for them here
            // too so the cart price display matches the price actually applied; otherwise a priced-individually
            // child below its MOQ would render the "minimum requirement not met" notice while still being
            // charged the wholesale price (WWPP-1828).
            if ( ! empty( $cart_item['bundled_by'] ) || ! empty( $cart_item['composite_parent'] ) ) {
                $did_not_meet = false;
            }

            if ( ! $did_not_meet ) {
                // Requirements met: nothing to render, so skip the pricing pipeline entirely.
                return $price_html;
            }

            // From here the notice will be rendered, so fetch the wholesale/sale/quantity-discount prices.
            $product_id                   = ( ! empty( $cart_item['variation_id'] ) ) ? $cart_item['variation_id'] : $cart_item['product_id'];
            $product                      = wc_get_product( $product_id );
            $product_price                = $cart_item['data']->get_sale_price() ? $cart_item['data']->get_sale_price() : $cart_item['data']->get_regular_price();
            $active_currency              = get_woocommerce_currency();
            $wholesale_price_raw          = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $product_id, array( $user_wholesale_role ) );
            $quantity_based_price         = WWPP_Helper_Functions::get_quantity_discount_mapping_price( $product, array( $user_wholesale_role ), $cart_item );
            $wholesale_price              = isset( $wholesale_price_raw['wholesale_price'] ) ? $wholesale_price_raw['wholesale_price'] : 0;
            $product_wholesale_sale_price = $this->get_product_wholesale_sale_price( $product_id, array( $user_wholesale_role ) );
            $acfw_bogo_entries            = WC()->session->get( 'acfw_bogo_entries' );
            $acfw_bogo_deals              = array();
            $has_acfw_bogo_coupon_applied = WWPP_Helper_Functions::check_if_has_acfw_bogo_coupon_applied();

            // Get acfw_bogo deals, set it to $acfw_bogo_deals.
            if ( ! empty( $acfw_bogo_entries ) && $has_acfw_bogo_coupon_applied ) {
                foreach ( $acfw_bogo_entries['matched'] as $matched_entry ) {
                    if ( $cart_item_key === $matched_entry['key'] ) {
                        $acfw_bogo_deals[ $matched_entry['type'] ] = array(
                            'type'          => $matched_entry['type'],
                            'quantity'      => $matched_entry['quantity'],
                            'discount'      => $matched_entry['discount'],
                            'discount_type' => $matched_entry['discount_type'],
                        );
                    }
                }
            }

            if ( ( isset( $product_wholesale_sale_price['is_on_sale'] ) && true === $product_wholesale_sale_price['is_on_sale'] ) && isset( $product_wholesale_sale_price['wholesale_sale_price'] ) ) {
                $wholesale_price = $product_wholesale_sale_price['wholesale_sale_price'];
            }

            // Check if the product has quantity based wholesale price.
            if ( ! empty( $quantity_based_price ) ) {
                $wholesale_price = $quantity_based_price['wholesale_price'];
            }

            // If conditions are not meet, apply wholesale price in product level.
            if ( ! empty( $wholesale_price ) ) {
                $wholesale_price        = WWP_Helper_Functions::wwp_formatted_price( $wholesale_price );
                $formatted_retail_price = $this->get_product_shop_price_with_taxing_applied( $product, $product_price, array( 'currency' => $active_currency ), $user_wholesale_role );

                $price_html = apply_filters( 'wwp_product_original_price', '<span class="original-computed-price">' . $formatted_retail_price . '</span>', $wholesale_price, $product_price, $product, array( $user_wholesale_role ) );

                $min_tooltip_msg = '<span class="wwpp-minimum-requirements"><span class="wwpp-minimum-tooltiptext">' . __( 'Price adjustment not applied due to minimum requirement rule. Please check notices above.', 'woocommerce-wholesale-prices-premium' ) . '</span></span>';

                // If cart item has acfw_bogo coupon applied, then we need to check if the acfw_bogo deal is override.
                if ( $has_acfw_bogo_coupon_applied && array_key_exists( 'deal', $acfw_bogo_deals ) ) {
                    if ( 'override' === $acfw_bogo_deals['deal']['discount_type'] ) {
                        $acfw_bogo_price = $acfw_bogo_deals['deal']['discount'];
                    } elseif ( 'fixed' === $acfw_bogo_deals['deal']['discount_type'] ) {
                        $acfw_bogo_price = $product_price - $acfw_bogo_deals['deal']['discount'];
                    } elseif ( 'percent' === $acfw_bogo_deals['deal']['discount_type'] ) {
                        $acfw_bogo_price = $product_price * ( $acfw_bogo_deals['deal']['discount'] / 100 );
                    }

                    $formatted_acfw_bogo_price = $this->get_product_shop_price_with_taxing_applied( $product, $acfw_bogo_price, array( 'currency' => $active_currency ), $user_wholesale_role );
                    $price_html                = '<span class="acfw-undiscounted-price">' . $price_html . ' x ' . $acfw_bogo_deals['trigger']['quantity'] . $min_tooltip_msg . '</span>';

                    // If bogo discount price is zero, then don't show the tooltip message.
                    if ( $acfw_bogo_price > 0 ) {
                        $price_html .= '<br/><span class="acfw-bogo-discounted-price">' . $formatted_acfw_bogo_price . ' x ' . $acfw_bogo_deals['deal']['quantity'] . $min_tooltip_msg . '</span>';
                    } else {
                        $price_html .= '<br/><span class="acfw-bogo-discounted-price">' . $formatted_acfw_bogo_price . ' x ' . $acfw_bogo_deals['deal']['quantity'] . '</span>';
                    }
                } else {
                    $price_html .= $min_tooltip_msg;
                }

                $wholesale_price_title_text = __( 'Wholesale Price:', 'woocommerce-wholesale-prices-premium' );
                $wholesale_price_title_text = apply_filters( 'wwp_filter_wholesale_price_title_text', $wholesale_price_title_text );

                $price_html .= '<del style="display: block;" class="wholesale_price_container">
                        <span class="wholesale_price_title">' . $wholesale_price_title_text . '</span>
                        <ins>' . $wholesale_price . '</ins>
                    </del>';

                /***************************************************************************
                 * Remove WWP filter to prevent double price filtering
                 ***************************************************************************
                 *
                 * Since we're already handling the price display when minimum requirements
                 * aren't met, we need to remove WWP's filter to prevent conflicting price
                 * formatting.
                 *
                 * Note: This filter removal is scoped to the condition where minimum
                 * requirements aren't met because WWPP is handling the price display in
                 * this scenario. In other cases, WWP's filter should remain active.
                 *
                 * @since 2.0.8 Fix for issue #1453
                 */
                if ( isset( $wc_wholesale_prices->wwp_wholesale_prices ) && has_filter( 'woocommerce_cart_item_price', array( $wc_wholesale_prices->wwp_wholesale_prices, 'filter_product_price' ) ) ) {
                    remove_filter( 'woocommerce_cart_item_price', array( $wc_wholesale_prices->wwp_wholesale_prices, 'filter_product_price' ), 100, 2 );
                }
            }
        }

        // Return the custom price HTML.
        return $price_html;
    }

    /**
     * Register the cart and checkout block integration.
     *
     * @param IntegrationRegistry $integration_registry Integration registry.
     *
     * @since 2.0.0
     * @return void
     */
    public function cart_checkout_block_integration( $integration_registry ) {

        $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

        if ( ! empty( $user_wholesale_role ) && WWPP_Helper_Functions::show_saving_amount( 'cart' ) ) {
            $integration_registry->register(
                new \RymeraWebCo\WWPP\Cart_Checkout_Block_Integration()
            );
        }
    }

    /**
     * Check cart/checkout block extensions data.
     *
     * @since 2.0.0
     * @return array
     */
    public function check_cart_checkout_block_data() {

        $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

        $apply_wholesale_price = true;
        if ( ! empty( $user_wholesale_role ) ) {
            $cart_object           = WC()->cart;
            $cart_total            = WWPP_Helper_Functions::calculate_cart_totals( $cart_object->cart_contents_total, $cart_object, array( $user_wholesale_role ) );
            $apply_wholesale_price = WWPP_Helper_Functions::apply_wholesale_price_per_cart_level_min_condition( $cart_total, $cart_object, $user_wholesale_role );
        }

        /***************************************************************************
         * Wholesale savings block HTML.
         ***************************************************************************
         *
         * Get the wholesale savings block HTML.
         */
        ob_start();
        \WooCommerceWholeSalePricesPremium::instance()->wwpp_wholesale_prices->apply_wholesale_saving_amount_on_cart_and_checkout_page();
        $wholesale_savings_html = ob_get_clean();

        return array(
            'notices'              => true !== $apply_wholesale_price ? $apply_wholesale_price : null,
            'wholesaleSavingsHtml' => $wholesale_savings_html,
        );
    }

    /**
     * Check per product item min requirements on cart block.
     *
     * @param array $cart_item Cart item.
     *
     * @since 2.0.0
     * @return array
     */
    public function cart_item_block_data( $cart_item ) {

        $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

        $apply_wholesale_price = true;
        if ( ! empty( $user_wholesale_role ) ) {
            $apply_wholesale_price = WooCommerceWholeSalePricesPremium::instance()->wwpp_wholesale_price_requirement
                ->filter_if_apply_wholesale_price_per_product_level( true, $cart_item, WC()->cart, $user_wholesale_role, 0 );
        }

        return array( 'notices' => true !== $apply_wholesale_price ? $apply_wholesale_price : null );
    }

    /**
     * Custom data for store api.
     *
     * @since 2.0.0
     * @return void
     */
    public function wc_cart_block_wwpp_data() {

        woocommerce_store_api_register_endpoint_data(
            array(
                'endpoint'        => Cart::IDENTIFIER,
                'namespace'       => 'wwpp_block',
                'data_callback'   => array( $this, 'check_cart_checkout_block_data' ),
                'schema_callback' => function () {

                    return array(
                        'wwpp_notices' => array(
                            'type'     => 'array',
                            'readonly' => true,
                        ),
                    );
                },
                'schema_type'     => ARRAY_A,
            )
        );

        woocommerce_store_api_register_endpoint_data(
            array(
                'endpoint'        => CartItemSchema::IDENTIFIER,
                'namespace'       => 'wwpp_block',
                'data_callback'   => array( $this, 'cart_item_block_data' ),
                'schema_callback' => function () {

                    return array(
                        'wwpp_notices' => array(
                            'type'     => 'array',
                            'readonly' => true,
                        ),
                    );
                },
                'schema_type'     => ARRAY_A,
            )
        );
    }

    /**
     * Preload the calculation, because this is an expensive call
     *
     * @return void
     */
    public function wc_cart_total_calculation_preload() {
        global $woocommerce;
        $user_wholesale_role = $this->_get_current_user_wholesale_role();
        if ( ! empty( $user_wholesale_role ) && is_cart() ) {
            $this->preload_calculate_cart_total = WWPP_Helper_Functions::calculate_cart_totals( $woocommerce->cart->get_cart_total(), $woocommerce->cart, array( $user_wholesale_role ) );
        }
    }

    /**
     * Execute model.
     *
     * @since  1.16.0
     * @since  2.1.0 Register the action that renders the quantity discount table at a configurable position.
     * @access public
     */
    public function run() {

        // Per Product Level Order Qty Wholesale Discount.
        add_filter( 'wwp_filter_wholesale_price_html', array( $this, 'render_per_product_level_order_quantity_based_wholesale_discount_table_markup' ), 10, 7 );

        // Register the repositioned quantity discount table hook on `wp` rather than at include time, so the
        // position option and its wwpp_quantity_discount_table_position filter are read after themes and
        // plugins have registered their callbacks on the usual hooks (init, after_setup_theme, plugins_loaded).
        add_action( 'wp', array( $this, 'register_repositioned_quantity_discount_table_hook' ) );
        add_filter( 'wwp_filter_wholesale_price_cart', array( $this, 'apply_product_level_order_quantity_based_wholesale_pricing' ), 11, 4 );

        // Apply wholesale prices on shop and cart for custom product types.
        add_filter( 'woocommerce_get_price_html', array( $this, 'custom_product_type_wholesale_price_html_filter' ), 10, 2 );
        add_filter( 'wwp_filter_get_custom_product_type_wholesale_price', array( $this, 'apply_custom_product_type_wholesale_price' ), 10, 4 );

        // Apply filters to override the default wholesale price suffix if the Override Price Suffix in the settings is set.
        add_filter( 'wwp_wholesale_price_suffix', array( $this, 'override_wholesale_price_suffix' ), 10, 1 );
        add_filter( 'woocommerce_get_price_suffix', array( $this, 'override_regular_price_suffix_for_wholesale_roles' ), 10, 4 );

        // Apply filters to override the default base price when WooCommerce Product Bundle is active and product priced individually and the discount is set.
        add_filter( 'wwp_wholesale_price_suffix_base_price', array( $this, 'filter_wholesale_price_suffix_base_price' ), 10, 2 );
        add_filter( 'wwpp_regular_price_suffix_base_price', array( $this, 'filter_regular_price_suffix_base_price' ), 10, 2 );

        // Always use regular price option for variable price range.
        add_filter( 'woocommerce_variable_price_html', array( $this, 'always_use_regular_price_option_for_variable_product' ), 10, 2 );

        // Apply filter for product variables on price suffix.
        add_filter( 'wwp_filter_wholesale_price_display_suffix', array( $this, 'get_wholesale_price_display_suffix_filter' ), 10, 6 );

        // Apply wholesale saved price in single product and shop page.
        add_filter( 'wwp_filter_wholesale_price_html', array( $this, 'apply_wholesale_saving_amount_on_single_and_shop_page' ), 10, 7 );
        add_action( 'woocommerce_cart_totals_before_order_total', array( $this, 'apply_wholesale_saving_amount_on_cart_and_checkout_page' ), 10 );
        add_action( 'woocommerce_review_order_before_order_total', array( $this, 'apply_wholesale_saving_amount_on_cart_and_checkout_page' ), 10 );
        add_action( 'woocommerce_order_details_after_order_table', array( $this, 'apply_wholesale_saving_amount_on_order_page' ), 10, 1 );
        add_action( 'woocommerce_email_after_order_table', array( $this, 'apply_wholesale_saving_amount_on_email_invoice' ), 10, 4 );

        // Apply filters to override the default woocommerce stock display format if the Wholesale Stock Display Format in the settings is set.
        add_filter( 'woocommerce_get_availability_text', array( $this, 'override_stock_display_format_for_wholesale_roles' ), 10, 2 );

        // Wholesale sale prices.
        add_filter( 'wwp_filter_wholesale_price_html_before_return_wholesale_price_only', array( $this, 'apply_wholesale_sale_price_on_single_and_shop_page' ), 5, 9 );
        add_filter( 'wwp_product_original_price', array( $this, 'filter_product_original_price_for_wholesale_sale_price' ), 10, 5 );
        add_filter( 'wwp_filter_wholesale_price_cart', array( $this, 'apply_wholesale_sale_price_on_cart' ), 10, 4 );
        add_filter( 'woocommerce_product_is_on_sale', array( $this, 'apply_on_sale_badge_on_wholesale_sale_products' ), 10, 2 );
        add_action( 'woocommerce_scheduled_sales', array( $this, 'scheduled_wholesale_sales' ), 10, 1 );
        add_action( self::SCHEDULED_SALES_BATCH_ACTION, array( $this, 'process_scheduled_wholesale_sales_batch' ), 10, 2 );

        // Filter cart item price.
        add_filter( 'woocommerce_cart_item_price', array( $this, 'filter_woocommerce_cart_item_price' ), 20, 3 );

        add_action( 'woocommerce_blocks_cart_block_registration', array( $this, 'cart_checkout_block_integration' ) );
        add_action( 'woocommerce_blocks_checkout_block_registration', array( $this, 'cart_checkout_block_integration' ) );
        add_action( 'woocommerce_blocks_loaded', array( $this, 'wc_cart_block_wwpp_data' ) );

        add_action( 'woocommerce_before_cart', array( $this, 'wc_cart_total_calculation_preload' ), 9 );
    }
}
