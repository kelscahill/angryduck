jQuery(document).ready(function ($) {
    if (jQuery('.woocommerce-checkout').length > 0) {
        jQuery('.woocommerce-checkout').on('change', 'input[type=radio][name=payment_method]', function () {
            if (jQuery(this).val() === 'wc_wholesale_payments') {
                jQuery('.payment_method_wc_wholesale_payments input[type=radio][name=wpay_plan]:first').prop('checked', true);
            }
        });
    }
});

jQuery(document).ajaxComplete(function (event, jqxhr, settings) {
    const objData = getQueryParams(settings.data);
    if (typeof objData?.payment_method !== "undefined" && objData?.payment_method == "wc_wholesale_payments") {
        jQuery('.payment_method_wc_wholesale_payments input[type=radio][name=wpay_plan]:first').prop('checked', true);
    }
});

function getQueryParams(data) {
    var params = {};
    var queryString = data;
    var regex = /([^&=]+)=([^&]*)/g;
    var m;

    while (m = regex.exec(queryString)) {
        params[decodeURIComponent(m[1])] = decodeURIComponent(m[2]);
    }

    return params;
}