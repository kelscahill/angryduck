jQuery(function ($) {
    $(document).on('wwpp-order-data-item-after', function (event, data) {
        const order_id = jQuery("#post_ID").val();
        const customerUserId = jQuery("#customer_user").val();

        var country = "";
        var state = "";
        var postcode = "";
        var city = "";
        var items = $("table.woocommerce_order_items :input[name], .wc-order-totals-items :input[name]").serialize()

        if ("shipping" === wwpp_wholesale_prices_order_params.wc_tax_based_on) {
            country = jQuery("#_shipping_country").val();
            state = jQuery("#_shipping_state").val();
            postcode = jQuery("#_shipping_postcode").val();
            city = jQuery("#_shipping_city").val();
        }

        if ("billing" === wwpp_wholesale_prices_order_params.wc_tax_based_on || !country) {
            country = jQuery("#_billing_country").val();
            state = jQuery("#_billing_state").val();
            postcode = jQuery("#_billing_postcode").val();
            city = jQuery("#_billing_city").val();
        }

        var apply_wholesale = jQuery("#apply_wholesale_pricing").is(":checked");

        const dataToSend = {
            action: 'wwpp_order_data_item_after',
            order_id: order_id,
            customer_user: customerUserId,
            country: country,
            state: state,
            postcode: postcode,
            city: city,
            items: items,
            apply_wholesale: apply_wholesale ? 1 : 0,
            security: wwpp_wholesale_prices_order_params.wwpp_order_nonce
        };

        $.ajax({
            url: wwpp_wholesale_prices_order_params.admin_ajax_url,
            data: dataToSend,
            type: 'POST',
            success: function (response) {
                if (response) {
                    $('#woocommerce-order-items').find('.inside').empty();
                    $('#woocommerce-order-items').find('.inside').append(response);
                }
                $(document).trigger('wwpp-order-items-recalculate-after', response);
            }
        });
    });

    jQuery("#apply_wholesale_pricing").on('change', function () {
        const is_enabled = $(this).is(":checked");
        if (is_enabled) {
            if (confirm(wwpp_wholesale_prices_order_params.confirmation_message)) {
                $(document).trigger('wwpp-order-data-item-after');
            } else {
                jQuery("#apply_wholesale_pricing").prop('checked', false);
            }
        } else {
            $(document).trigger('wwpp-order-data-item-after');
        }
    });
});