<?php
/**
 * Vendor Wholesale Settings.
 *
 * @since 2.0.3 - Added
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>
<div class="tabs-content" id="wholesale">
    <div class="wcv-cols-group wcv-horizontal-gutters">
        <div class="all-30 small-100">
            <label for="wwp_prices_settings_show_wholesale_prices_to_non_wholesale">
                <?php esc_html_e( 'Show Wholesale Price to non-wholesale users', 'woocommerce-wholesale-prices-premium' ); ?>
            </label>
        </div>
        <div class="all-70 small-100">
            <?php

            $is_disabled = 'no' === $show_prices_to_non_wholesale_users ? true : false;

            $description = $is_disabled
                ? __(
                    'This option is currently disabled by the administrator.',
                    'woocommerce-wholesale-prices-premium'
                )
                : __(
                    'If enabled, displays the wholesale price on the front-end to entice non-wholesale customers to register to become wholesale customers. This is only shown for guest, customers, administrators, and shop managers.',
                    'woocommerce-wholesale-prices-premium'
                );

            WWPP_WC_Vendors_Pro_Form_Helper::toggle_switch(
                array(
                    'id'          => 'wwp_prices_settings_show_wholesale_prices_to_non_wholesale',
                    'label'       => __( 'Show Wholesale Price to non-wholesale users', 'woocommerce-wholesale-prices-premium' ),
                    'description' => $description,
                    'value'       => get_user_meta( $vendor_id, 'wwp_prices_settings_show_wholesale_prices_to_non_wholesale', true ),
                    'disabled'    => $is_disabled,
                )
            );
            ?>
        </div>
    </div>

    <div id="wwpp_show_if_vendors_show_prices">
        <div class="wcv-cols-group wcv-horizontal-gutters">
            <div class="all-30 small-100">
                <label for="wwp_non_wholesale_show_in_products">
                    <?php esc_html_e( 'Locations', 'woocommerce-wholesale-prices-premium' ); ?>
                </label>
            </div>
            <div class="all-70 small-100">
                <?php

                WWPP_WC_Vendors_Pro_Form_Helper::checkbox(
                    array(
                        'id'       => 'wwp_non_wholesale_show_in_products',
                        'label'    => __( 'Single Products', 'woocommerce-wholesale-prices-premium' ),
                        'desc_tip' => 'true',
                        'value'    => get_user_meta( $vendor_id, 'wwp_non_wholesale_show_in_products', true ),
                    )
                );

                WWPP_WC_Vendors_Pro_Form_Helper::checkbox(
                    array(
                        'id'       => 'wwp_non_wholesale_show_in_shop',
                        'label'    => sprintf(
                            // translators: %s - is the name used to refer to a vendor.
                            __( '%s Store', 'woocommerce-wholesale-prices-premium' ),
                            wcv_get_vendor_name()
                        ),
                        'desc_tip' => 'true',
                        'value'    => get_user_meta( $vendor_id, 'wwp_non_wholesale_show_in_shop', true ),
                    )
                );

                ?>
            </div>
        </div>
        <div class="wcv-cols-group wcv-horizontal-gutters">
            <div class="all-30 small-100">
                <label for="wwp_non_wholesale_wholesale_role_select2">
                    <?php esc_html_e( 'Wholesale Role(s)', 'woocommerce-wholesale-prices-premium' ); ?>
                </label>
            </div>
            <div class="all-70 small-100">
                <?php
                WCVendors_Pro_Form_Helper::select(
                    array(
                        'id'                => 'wwp_non_wholesale_wholesale_role_select2',
                        'class'             => 'select2',
                        'multiple'          => true,
                        'label'             => '',
                        'desc_tip'          => true,
                        'options'           => $allowed_options,
                        'default'           => $allowed_options,
                        'value'             => $roles_value,
                        'wrapper_start'     => '<div class="all-100">',
                        'wrapper_end'       => '</div>',
                        'custom_attributes' => array(
                            'multiple' => true,
                        ),
                    )
                );
                ?>
            </div>
        </div>
    </div>
</div>
