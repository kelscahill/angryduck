jQuery(document).ready(function ($) {
    /**=================================================================================================================
     * Variables
     =================================================================================================================*/
    var $wwpp_show_wholesale_saving_amount_checkbox = $(
            "#wwpp_settings_show_saving_amount"
        ),
        $fieldset                                           = $wwpp_show_wholesale_saving_amount_checkbox.closest("fieldset"),
        show_wholesale_saving_amount_page_shop              = wwpp_wholesale_saving_amount_var.wwpp_show_wholesale_saving_amount_page_shop == "no" ? "" : "checked", // Check if it will show "Click to See Wholesale Prices" in Shops Archives
        show_wholesale_saving_amount_page_single_product    = wwpp_wholesale_saving_amount_var.wwpp_show_wholesale_saving_amount_page_single_product == "no" ? "" : "checked", // Check if it will show "Click to See Wholesale Prices" in single products page
        show_wholesale_saving_amount_page_cart              = wwpp_wholesale_saving_amount_var.wwpp_show_wholesale_saving_amount_page_cart == "no" ? "" : "checked", // Check if it will show "Click to See Wholesale Prices" in Wholesale Order Form.  Precondition: WWOF Plugin should be activated;
        show_wholesale_saving_amount_page_invoice           = wwpp_wholesale_saving_amount_var.wwpp_show_wholesale_saving_amount_page_invoice == "no" ? "" : "checked"; // Check if it will show "Click to See Wholesale Prices" in Wholesale Order Form.  Precondition: WWOF Plugin should be activated;

    /**=================================================================================================================
     * Functions
     =================================================================================================================*/

    /**
     * This function is responsible for rendering controls in the Price options of the Wholesale Price settings
     * @since 1.15.0
     * @returns string containing html tags and fields
     */
    function render_show_wholesale_saving_amount() {

        // Control container
        var control_container =
            "<!--Begin: #wwwpp-settings-show-saving-amount-settings-->" +
            "<div id='wwwpp-settings-show-saving-amount-settings' style='max-width: 680px !important; display: none;'>" +
            "<div class='wwwpp-settings-show-saving-amount-page'>" +
            "<h4>"+ wwpp_wholesale_saving_amount_var.i18n_wwpp_show_wholesale_saving_amount_page_title_header +"</h4>" +
            //----------------------------------------------------------------------------------------------------------
            // Show Saved Wholesale Amount in pages
            //----------------------------------------------------------------------------------------------------------
            "<table class='form-table'>" +
            "<tbody>" +
            "<tr valign='top'><td>" +
            "<label for='wwpp_settings_show_saving_amount_page_shop' style='padding-right: 20px;'><input id='wwpp_settings_show_saving_amount_page_shop' name='wwpp_settings_show_saving_amount_page_shop' class='wwpp_settings_show_saving_amount_page_shop' type='checkbox' value='yes'" +
            show_wholesale_saving_amount_page_shop +
            "> "+ wwpp_wholesale_saving_amount_var.i18n_wwpp_show_wholesale_saving_amount_page_shop_title +"</label>" +
            "</td></tr>" +
            "<tr valign='top'><td>" +
            "<label for='wwpp_settings_show_saving_amount_page_single_product' style='padding-right: 20px;'><input id='wwpp_settings_show_saving_amount_page_single_product' name='wwpp_settings_show_saving_amount_page_single_product' class='wwpp_settings_show_saving_amount_page_single_product' type='checkbox' value='yes'" +
            show_wholesale_saving_amount_page_single_product +
            "> "+ wwpp_wholesale_saving_amount_var.i18n_wwpp_show_wholesale_saving_amount_page_single_product_title +"</label>" +
            "</td></tr>" +
            "<tr valign='top'><td>" +
            "<label for='wwpp_settings_show_saving_amount_page_cart' style='padding-right: 20px;'><input id='wwpp_settings_show_saving_amount_page_cart' name='wwpp_settings_show_saving_amount_page_cart' class='wwpp_settings_show_saving_amount_page_cart' type='checkbox' value='yes'" +
            show_wholesale_saving_amount_page_cart +
            "> "+ wwpp_wholesale_saving_amount_var.i18n_wwpp_show_wholesale_saving_amount_page_cart_title +"</label>" +
            "</td></tr>" +
            "<tr valign='top'><td>" +
            "<label for='wwpp_settings_show_saving_amount_page_invoice' style='padding-right: 20px;'><input id='wwpp_settings_show_saving_amount_page_invoice' name='wwpp_settings_show_saving_amount_page_invoice' class='wwpp_settings_show_saving_amount_page_invoice' type='checkbox' value='yes'" +
            show_wholesale_saving_amount_page_invoice +
            "> "+ wwpp_wholesale_saving_amount_var.i18n_wwpp_show_wholesale_saving_amount_page_invoice_title +"</label>" +
            "</td></tr>" +
            "</tbody>" +
            "</table>" +
            "</div><hr/>" +
            // Consumer Key
            "<div class='wwwpp-settings-show-saving-amount-text'>" +
            "<h4>"+ wwpp_wholesale_saving_amount_var.i18n_wwpp_show_wholesale_saving_amount_tags_title +"</h4>" +
            "<ul style='list-style: initial; padding-inline-start: 40px;'>" +
            "<li>" +
            "<code>{saved_amount}</code> : " +
            wwpp_wholesale_saving_amount_var.i18n_wwpp_show_wholesale_saving_amount_tags_saved_amount_desc +
            "</li>" +
            "<li>" +
            "<code>{saved_percentage}</code> : " +
            wwpp_wholesale_saving_amount_var.i18n_wwpp_show_wholesale_saving_amount_tags_saved_percentage_desc +
            "</li>" +
            "</ul>" +
            "<table class='form-table'>" +
            "<tbody>" +
            "<tr valign='top'>" +
            "<th class='titledesc' scope='row'>" +
            "<label for='wwpp_settings_show_saving_amount_text'>" +
            wwpp_wholesale_saving_amount_var.i18n_wwpp_show_wholesale_saving_amount_text_title +
            wwpp_wholesale_saving_amount_var.wwpp_show_wholesale_saving_amount_text_tooltip +
            "</label>" +
            "</th>" +
            "<td class='forminp forminp-text' style='padding-right:0;'>" +
            "<input id='wwpp_settings_show_saving_amount_text' name='wwpp_settings_show_saving_amount_text' type='text' style='width:100%' placeholder='' value='"+ wwpp_wholesale_saving_amount_var.wwpp_show_wholesale_saving_amount_text_value +"' />" +
            "</td>" +
            "</tr>" +
            "</tbody>" +
            "</table>" +
            "</div>" +
            "</div>" +
            "<!--End: #wwwpp-settings-show-saving-amount-settings -->";

        // Append Control container
        $fieldset.append(control_container);

    }

    $wwpp_show_wholesale_saving_amount_checkbox.change(function () {
        if ($(this).is(":checked")) {
            $("#wwwpp-settings-show-saving-amount-settings").slideDown();
        } else {
            $("#wwwpp-settings-show-saving-amount-settings").slideUp();
        }
    });

    /**=================================================================================================================
     * Page Load
     =================================================================================================================*/

    // Render controls
    render_show_wholesale_saving_amount();

    $wwpp_show_wholesale_saving_amount_checkbox.trigger("change");

    // Run Events
    // run_events();

});