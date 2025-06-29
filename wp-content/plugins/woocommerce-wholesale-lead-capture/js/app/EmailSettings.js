jQuery(document).ready(function ($) {

    var options = {
        plugins: ['restore_on_backspace', 'remove_button', 'drag_drop'],
        delimiter: ',',
        persist: false,
        create: function (input) {
            return {
                value: input,
                text: input
            }
        }
    }

    $("#wwlc_emails_main_recipient").selectize(options);
    $("#wwlc_emails_cc").selectize(options);
    $("#wwlc_emails_bcc").selectize(options);

    if ($(".wwlc-email-wysiwyg-desc").length > 0) {
        $(".woocommerce-save-button").prop("disabled", false);
    }

});