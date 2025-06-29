<?php
/**
 * Vendor Wholesale Pricing Fields Form.
 *
 * @since 2.0.3 - Added
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wcv-wholesale-prices-fields wholesale-prices-fields--<?php echo esc_attr( $product_type ); ?>">
    <div class="all-50 small-100">
        <label><?php echo esc_html( $role['roleName'] ); ?></label>
    </div>
    <div class="wcv-wholesale-prices-fields-container all-50 small-100">
        <?php

        WWPP_WC_Vendors_Pro_Form_Helper::discount_type(
            array(
                'id'      => $discount_type_id,
                'label'   => __( 'Discount Type', 'woocommerce-wholesale-prices-premium' ),
                'value'   => $discount_type,
                'tooltip' => __( 'Choose Price Type. Fixed (default)/Percentage', 'woocommerce-wholesale-prices-premium' ),
            )
        );

        WWPP_WC_Vendors_Pro_Form_Helper::price_field(
            array(
                'id'                => $percentage_discount_id,
                'class'             => 'wholesale_discount',
                'label'             => __( 'Discount (%)', 'woocommerce-wholesale-prices-premium' ),
                'placeholder'       => '',
                'tooltip'           => $field_desc_percentage,
                'data_type'         => 'price',
                'value'             => $wholesale_percentage_discount,
                'custom_attributes' => array(
                    'data-wholesale_role' => $role_key,
                ),
            )
        );

        WWPP_WC_Vendors_Pro_Form_Helper::price_field(
            array(
                'id'                => $wholesale_price_id,
                'class'             => $role_key . '_wholesale_price wholesale_price',
                'label'             => $field_label,
                'placeholder'       => '',
                'tooltip'           => $field_desc,
                'data_type'         => 'price',
                'value'             => $wholesale_price,
                'custom_attributes' => array(
                    'data-wholesale_role'        => $role_key,
                    'data-field_desc_fixed'      => html_entity_decode( $field_desc_fixed ),
                    'data-field_desc_percentage' => html_entity_decode( $field_desc_percentage ),
                ),
            )
        );

        ?>
        <div class="all-100 wwpp-wcv-wholesale-sale-dates-fields">
        <?php
        WWPP_WC_Vendors_Pro_Form_Helper::price_field(
            array(
                'id'                => $wholesale_sale_price_id,
                'class'             => $role_key . '_wholesale_sale_price wholesale_sale_price',
                'label'             => $sale_field_label,
                'placeholder'       => '',
                'tooltip'           => $field_desc,
                'description'       => '<a href="#" class="wholesale_sale_schedule right" data-wholesale-role="' . esc_attr( $role_key ) .
                '" data-field-id="' . esc_attr( $wholesale_sale_price_id ) . '">' . __( 'Schedule', 'woocommerce-wholesale-prices-premium' ) . '</a>',
                'data_type'         => 'price',
                'value'             => $wholesale_sale_price,
                'custom_attributes' => array(
                    'data-wholesale_role'        => $role_key,
                    'data-field_desc_fixed'      => html_entity_decode( $field_desc_fixed ),
                    'data-field_desc_percentage' => html_entity_decode( $field_desc_percentage ),
                ),
                'role_key'          => $role_key,
                'product'           => $product,
            )
        );
        ?>
        </div>
    </div>
</div>
