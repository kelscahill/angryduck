/* globals jQuery */
jQuery(document).ready(function ($) {
    /*
     |------------------------------------------------------------------------------------------------------------------
     | Variable Declarations
     |------------------------------------------------------------------------------------------------------------------
     */

    var error_message_duration = "10000",
        success_message_duration = "5000";

    /*
     |--------------------------------------------------------------------------
     | Initialize Tooltips
     |--------------------------------------------------------------------------
     */

    $(".tooltip").tipTip({
        attribute: "data-tip",
        fadeIn: 50,
        fadeOut: 50,
        delay: 200,
    });

    /*
     |------------------------------------------------------------------------------------------------------------------
     | Helper Functions
     |------------------------------------------------------------------------------------------------------------------
     */

    function validate_empty_fields(
        wholesale_role,
        min,
        max,
        price_type,
        price,
        currency
    ) {
        var err_fields = [];

        max = max != "" ? parseInt(max, 10) : max;

        if (wholesale_role == "")
            err_fields.push(
                wwpp_single_product_admin_params.i18n_wholesale_role
            );

        if (min <= 0 || min == "" || isNaN(min))
            err_fields.push(wwpp_single_product_admin_params.i18n_starting_qty);

        if (max === 0 || isNaN(max) || max < 0)
            err_fields.push(wwpp_single_product_admin_params.i18n_ending_qty);

        if (price_type == "")
            err_fields.push(wwpp_single_product_admin_params.i18n_price_type);

        if (price <= 0 || price == "")
            err_fields.push(
                wwpp_single_product_admin_params.i18n_wholesale_price
            );

        if (currency == "")
            err_fields.push(wwpp_single_product_admin_params.i18n_currency);

        return err_fields;
    }

    function validate_quantity_fields(min, max) {
        if (max != "" && max < min) return false;
        else return true;
    }

    function remove_table_no_items_placeholder($table) {
        $table.find("tbody").find(".no-items").remove();
    }

    function reset_table_row_styling($table) {
        $table
            .find("tbody")
            .find("tr")
            .each(function (index) {
                index++; // we do this coz index is zero base

                if (index % 2 == 0) {
                    // even
                    $(this)
                        .removeClass("odd")
                        .removeClass("alternate")
                        .addClass("even");
                } else {
                    // odd
                    $(this)
                        .removeClass("even")
                        .addClass("odd")
                        .addClass("alternate");
                }
            });
    }

    function reset_fields($parent_fields_container) {
        $parent_fields_container.find(".mapping-index").val("");
        $parent_fields_container
            .find(".pqbwp_registered_wholesale_roles")
            .find("option:first-child")
            .attr("selected", "selected");
        $parent_fields_container.find(".pqbwp_minimum_order_quantity").val("");
        $parent_fields_container.find(".pqbwp_maximum_order_quantity").val("");
        $parent_fields_container
            .find(".pqbwp_price_type")
            .find("option:first-child")
            .attr("selected", "selected");
        $parent_fields_container.find(".pqbwp_wholesale_price").val("");

        if (
            $parent_fields_container.find(".pqbwp_enabled_currencies").length >
            0
        )
            $parent_fields_container
                .find(".pqbwp_enabled_currencies")
                .find(
                    "option:contains('" +
                        wwpp_single_product_admin_params.i18n_base_currency +
                        "')"
                )
                .attr("selected", "selected");
    }

    function highlight_error_row($tr) {
        var addClass = true,
            interval = setInterval(function () {
                if (addClass) {
                    $tr.addClass("err-row");
                    addClass = false;
                } else {
                    $tr.removeClass("err-row");
                    addClass = true;
                }
            }, 1000);

        setTimeout(function () {
            clearInterval(interval);
            $tr.removeClass("err-row");
        }, 4000);
    }

    /*
     |------------------------------------------------------------------------------------------------------------------
     | Events
     |------------------------------------------------------------------------------------------------------------------
     */

    $("body").delegate(".pqbwp_price_type", "change", function () {
        var $this = $(this),
            $price_field = $this
                .closest("p")
                .siblings(".pqbwp_wholesale_price_field");

        if ($this.val() == "fixed-price")
            $price_field
                .find("label")
                .text(
                    wwpp_single_product_admin_params.i18n_fixed_price_wholesale_label
                );
        else if ($this.val() == "percent-price")
            $price_field
                .find("label")
                .text(
                    wwpp_single_product_admin_params.i18n_percent_price_wholesale_label
                );
    });

    // WWPP-592 Set price type to percentage discount for variable parent qty discount
    var $parent_variable_price_type = $("body").find(
        ".product-quantity-based-wholesale-pricing.options_group.variable.parent-variable #pqbwp_price_type"
    );

    $parent_variable_price_type
        .find("option[value=percent-price]")
        .attr("selected", "selected");
    $parent_variable_price_type.attr("disabled", "disabled");

    $("body").delegate(".pqbwp-enable", "click", function () {
        var $this = $(this),
            $parent_fields_container = $this.closest(
                ".product-quantity-based-wholesale-pricing"
            ),
            $processing_indicator = $parent_fields_container.find(
                ".processing-indicator"
            ),
            $pqbwp_controls = $parent_fields_container.find(".pqbwp-controls"),
            post_id = $.trim($this.siblings(".post-id").text()),
            enable = $this.is(":checked") ? "yes" : "no",
            is_parent_varibale =
                $parent_fields_container.hasClass("parent-variable"),
            $variation_qty_based_pricing_tbl = $("body").find(
                "#variable_product_options .product-quantity-based-wholesale-pricing.variable"
            );

        $this.attr("disabled", "disabled");

        if (enable == "yes") {
            $processing_indicator.css("display", "block");
            $this
                .siblings(".wwpp_post_meta_enable_quantity_discount_rule")
                .val("yes");
        } else {
            $pqbwp_controls.slideUp("fast", function () {
                $processing_indicator.css("display", "none");
                $this
                    .siblings(".wwpp_post_meta_enable_quantity_discount_rule")
                    .val("no");
            });
        }

        wwppBackendAjaxServices
            .toggle_product_quantity_based_wholesale_pricing(post_id, enable)
            .done(function (data, textStatus, jqXHR) {
                if (data.status == "success") {
                    if (enable == "yes") {
                        $processing_indicator.css("display", "none");
                        $pqbwp_controls.slideDown("fast");

                        // WWPP-592 Hide variation qty based pricing table if the parent variable is enabled
                        if (is_parent_varibale)
                            $variation_qty_based_pricing_tbl.css({
                                display: "none",
                            });
                    } else {
                        // WWPP-592 Hide variation qty based pricing table if the parent variable is disabled
                        if (is_parent_varibale)
                            $variation_qty_based_pricing_tbl.css({
                                display: "block",
                            });

                        $processing_indicator.css("display", "none");
                    }
                } else {
                    var err_msg;

                    if (enable == "yes")
                        err_msg =
                            wwpp_single_product_admin_params.i18n_fail_enable_product_quantity;
                    else
                        err_msg =
                            wwpp_single_product_admin_params.i18n_fail_disable_product_quantity;

                    toastr.error("", err_msg, {
                        closeButton: true,
                        showDuration: error_message_duration,
                    });

                    console.log(err_msg);
                    console.log(data);
                    console.log("----------");
                }
            })
            .fail(function (jqXHR, textStatus, data) {
                var err_msg;

                if (enable == "yes")
                    err_msg =
                        wwpp_single_product_admin_params.i18n_fail_enable_product_quantity;
                else
                    err_msg =
                        wwpp_single_product_admin_params.i18n_fail_disable_product_quantity;

                toastr.error("", err_msg, {
                    closeButton: true,
                    showDuration: error_message_duration,
                });

                console.log(err_msg);
                console.log(jqXHR);
                console.log("----------");
            })
            .always(function () {
                $this.removeAttr("disabled");
            });
    });

    $("body").delegate(".pqbwp-add-rule", "click", function () {
        var $this = $(this),
            $parent_fields_container = $this.closest(
                ".product-quantity-based-wholesale-pricing"
            ),
            $parent_button_controls = $this.closest(".button-controls"),
            $table_mapping = $parent_fields_container.find(".pqbwp-mapping"),
            is_parent_varibale =
                $parent_fields_container.hasClass("parent-variable");

        $this.attr("disabled", "disabled");
        $parent_button_controls.addClass("processing");

        var wholesale_role = $.trim(
                $parent_fields_container
                    .find(".pqbwp_registered_wholesale_roles")
                    .val()
            ),
            wholesale_role_text = $.trim(
                $parent_fields_container
                    .find(".pqbwp_registered_wholesale_roles")
                    .find("option:selected")
                    .text()
            ),
            start_qty = $.trim(
                $parent_fields_container
                    .find(".pqbwp_minimum_order_quantity")
                    .val()
            ),
            end_qty = $.trim(
                $parent_fields_container
                    .find(".pqbwp_maximum_order_quantity")
                    .val()
            ),
            price_type = $.trim(
                $parent_fields_container.find(".pqbwp_price_type").val()
            ),
            wholesale_price = $.trim(
                $parent_fields_container.find(".pqbwp_wholesale_price").val()
            ),
            currency_field = $parent_fields_container.find(
                ".pqbwp_enabled_currencies"
            ),
            currency = "",
            err_fields = "";

        // WWPP-592 only allow % discount on variable level
        if (is_parent_varibale && price_type == "fixed-price") {
            toastr.error(
                err_msg,
                wwpp_single_product_admin_params.i18n_parent_variable_price_type_error,
                { closeButton: true, showDuration: error_message_duration }
            );

            $this.removeAttr("disabled");
            $parent_button_controls.removeClass("processing");

            return false;
        }

        if (currency_field.length > 0) {
            currency = $.trim(currency_field.val());
            err_fields = validate_empty_fields(
                wholesale_role,
                start_qty,
                end_qty,
                price_type,
                wholesale_price,
                currency
            );
        } else err_fields = validate_empty_fields(wholesale_role, start_qty, end_qty, price_type, wholesale_price);

        if (err_fields.length > 0) {
            var err_msg =
                wwpp_single_product_admin_params.i18n_fill_fields_properly +
                "<br/><br/></ul>";

            for (var i = 0; i < err_fields.length; i++)
                err_msg += "<li>" + err_fields[i] + "</li>";

            err_msg += "</ul>";

            toastr.error(
                err_msg,
                wwpp_single_product_admin_params.i18n_fill_form_properly,
                { closeButton: true, showDuration: error_message_duration }
            );

            $this.removeAttr("disabled");
            $parent_button_controls.removeClass("processing");

            return false;
        }

        start_qty = parseInt(start_qty, 10);
        end_qty = end_qty != "" ? parseInt(end_qty, 10) : "";

        if (!validate_quantity_fields(start_qty, end_qty)) {
            toastr.error(
                "",
                wwpp_single_product_admin_params.i18n_ending_qty_must_not_be_less_start_qty,
                { closeButton: true, showDuration: error_message_duration }
            );

            $this.removeAttr("disabled");
            $parent_button_controls.removeClass("processing");

            return false;
        }

        var post_id = $.trim($parent_fields_container.find(".post-id").text()),
            rule = {
                wholesale_role: wholesale_role,
                start_qty: start_qty,
                end_qty: end_qty,
                price_type: price_type,
                wholesale_price: wholesale_price,
            };

        if (currency !== "") rule.currency = currency;

        var $role_wholesale_price_field = $(
            "#variable_product_options input[ id ^= '" +
                wholesale_role +
                "_wholesale_prices' ]"
        ).length
            ? $(
                  "#variable_product_options input[ id ^= '" +
                      wholesale_role +
                      "_wholesale_prices' ]"
              )
            : $(
                  "#general_product_data .pricing #" +
                      wholesale_role +
                      "_wholesale_price"
              );
        if ($role_wholesale_price_field.val() === "")
            alert(
                wwpp_single_product_admin_params
                    .required_base_wholesale_price_err_msg[wholesale_role]
            );

        wwppBackendAjaxServices
            .addQuantityDiscountRule(post_id, rule)
            .done(function (data, textStatus, jqXHR) {
                if (data.status == "success") {
                    toastr.success(
                        "",
                        wwpp_single_product_admin_params.i18n_success_add_discount_mapping,
                        {
                            closeButton: true,
                            showDuration: success_message_duration,
                        }
                    );

                    remove_table_no_items_placeholder($table_mapping);

                    var tr_class = "";

                    if ($table_mapping.find("tr").length % 2 == 0)
                        // currently even, next add (our add) would make it odd
                        tr_class = "odd alternate";
                    // currently odd, next add (our add) would make it even
                    else tr_class = "even";

                    var tr_currency = "";

                    if (currency)
                        tr_currency =
                            '<td class="currency">' + currency + "</td>";

                    $table_mapping.find("tbody").append(
                        '<tr class="' +
                            tr_class +
                            ' edited">' +
                            '<td class="meta hidden">' +
                            '<span class="index">' +
                            data.last_inserted_item_index +
                            "</span>" +
                            '<span class="wholesale-role">' +
                            wholesale_role +
                            "</span>" +
                            '<span class="price-type">' +
                            price_type +
                            "</span>" +
                            '<span class="wholesale-price">' +
                            wholesale_price +
                            "</span>" +
                            "</td>" +
                            '<td class="wholesale-role-text">' +
                            $parent_fields_container
                                .find(".pqbwp_registered_wholesale_roles")
                                .find("option[value='" + wholesale_role + "']")
                                .text() +
                            "</td>" +
                            '<td class="start-qty">' +
                            start_qty +
                            "</td>" +
                            '<td class="end-qty">' +
                            end_qty +
                            "</td>" +
                            '<td class="wholesale-price-text">' +
                            data.wholesale_price_text +
                            "</td>" +
                            tr_currency +
                            '<td class="controls">' +
                            '<a class="edit dashicons dashicons-edit"></a>' +
                            '<a class="delete dashicons dashicons-no"></a>' +
                            "</td>" +
                            "</tr>"
                    );

                    reset_fields($parent_fields_container);

                    // Remove edited class to the recently added user field
                    setTimeout(function () {
                        $table_mapping.find("tr.edited").removeClass("edited");
                    }, 500);
                } else {
                    // Highlight dup and/or overlapping rows
                    for (
                        var i = 0;
                        i < data.additional_data.dup_index.length;
                        i++
                    )
                        highlight_error_row(
                            $table_mapping
                                .find(
                                    "td.meta .index:contains(" +
                                        data.additional_data.dup_index[i] +
                                        ")"
                                )
                                .closest("tr")
                        );

                    toastr.error(
                        data.error_message,
                        wwpp_single_product_admin_params.i18n_fail_add_discount_mapping,
                        {
                            closeButton: true,
                            showDuration: error_message_duration,
                        }
                    );

                    console.log(
                        wwpp_single_product_admin_params.i18n_fail_add_discount_mapping
                    );
                    console.log(data);
                    console.log("----------");
                }
            })
            .fail(function (jqXHR, textStatus, errorThrown) {
                toastr.error(
                    jqXHR.responseText,
                    wwpp_single_product_admin_params.i18n_fail_add_discount_mapping,
                    { closeButton: true, showDuration: error_message_duration }
                );

                console.log(
                    wwpp_single_product_admin_params.i18n_fail_add_discount_mapping
                );
                console.log(jqXHR);
                console.log("----------");
            })
            .always(function () {
                $this.removeAttr("disabled");
                $parent_button_controls.removeClass("processing");
            });
    });

    $("body").delegate(".pqbwp-save-rule", "click", function () {
        var $this = $(this),
            $parent_fields_container = $this.closest(
                ".product-quantity-based-wholesale-pricing"
            ),
            $parent_button_controls = $this.closest(".button-controls"),
            $table_mapping = $parent_fields_container.find(".pqbwp-mapping");

        $parent_button_controls.find(".button").attr("disabled", "disabled");
        $parent_button_controls.addClass("processing");

        var wholesale_role = $.trim(
                $parent_fields_container
                    .find(".pqbwp_registered_wholesale_roles")
                    .val()
            ),
            start_qty = $.trim(
                $parent_fields_container
                    .find(".pqbwp_minimum_order_quantity")
                    .val()
            ),
            end_qty = $.trim(
                $parent_fields_container
                    .find(".pqbwp_maximum_order_quantity")
                    .val()
            ),
            price_type = $.trim(
                $parent_fields_container.find(".pqbwp_price_type").val()
            ),
            wholesale_price = $.trim(
                $parent_fields_container.find(".pqbwp_wholesale_price").val()
            ),
            currency_field = $parent_fields_container.find(
                ".pqbwp_enabled_currencies"
            ),
            currency = "",
            err_fields = "";

        if (currency_field.length > 0) {
            currency = $.trim(currency_field.val());
            err_fields = validate_empty_fields(
                wholesale_role,
                start_qty,
                end_qty,
                price_type,
                wholesale_price,
                currency
            );
        } else err_fields = validate_empty_fields(wholesale_role, start_qty, end_qty, price_type, wholesale_price);

        if (err_fields.length > 0) {
            var err_msg =
                wwpp_single_product_admin_params.i18n_fill_fields_properly +
                "<br/><br/></ul>";

            for (var i = 0; i < err_fields.length; i++)
                err_msg += "<li>" + err_fields[i] + "</li>";

            err_msg += "</ul>";

            toastr.error(
                err_msg,
                wwpp_single_product_admin_params.i18n_fill_form_properly,
                { closeButton: true, showDuration: error_message_duration }
            );

            $parent_button_controls.find(".button").removeAttr("disabled");
            $parent_button_controls.removeClass("processing");

            return false;
        }

        start_qty = parseInt(start_qty, 10);
        end_qty = end_qty != "" ? parseInt(end_qty, 10) : "";

        if (!validate_quantity_fields(start_qty, end_qty)) {
            toastr.error(
                "",
                wwpp_single_product_admin_params.i18n_ending_qty_must_not_be_less_start_qty,
                { closeButton: true, showDuration: error_message_duration }
            );

            $parent_button_controls.find(".button").removeAttr("disabled");
            $parent_button_controls.removeClass("processing");

            return false;
        }

        var post_id = $.trim($parent_fields_container.find(".post-id").text()),
            index = $.trim(
                $parent_fields_container.find(".mapping-index").val()
            ),
            rule = {
                wholesale_role: wholesale_role,
                start_qty: start_qty,
                end_qty: end_qty,
                price_type: price_type,
                wholesale_price: wholesale_price,
            };

        if (currency) rule["currency"] = currency;

        wwppBackendAjaxServices
            .saveQuantityDiscountRule(post_id, index, rule)
            .done(function (data, textStatus, jqXHR) {
                if (data.status == "success") {
                    console.log(currency);

                    $table_mapping
                        .find("tr.edited")
                        .find(".meta")
                        .find(".wholesale-role")
                        .text(wholesale_role)
                        .end()
                        .find(".wholesale-price")
                        .text(wholesale_price)
                        .end()
                        .find(".price-type")
                        .text(price_type)
                        .end()
                        .end()
                        .find(".wholesale-role-text")
                        .text(
                            $parent_fields_container
                                .find(".pqbwp_registered_wholesale_roles")
                                .find("option[value='" + wholesale_role + "']")
                                .text()
                        )
                        .end()
                        .find(".start-qty")
                        .text(start_qty)
                        .end()
                        .find(".end-qty")
                        .text(end_qty)
                        .end()
                        .find(".wholesale-price-text")
                        .html(data.wholesale_price_text);

                    if (currency) {
                        $table_mapping
                            .find("tr.edited")
                            .find(".currency")
                            .text(currency);
                    }

                    $table_mapping
                        .find("tr")
                        .removeClass("edited")
                        .removeClass("disabled");

                    reset_fields($parent_fields_container);

                    // Remove edited class to the recently added user field
                    setTimeout(function () {
                        $table_mapping.find("tr.edited").removeClass("edited");
                    }, 500);

                    $parent_button_controls
                        .removeClass("edit-mode")
                        .addClass("add-mode");

                    toastr.success(
                        "",
                        wwpp_single_product_admin_params.i18n_success_update_discount_mapping,
                        {
                            closeButton: true,
                            showDuration: success_message_duration,
                        }
                    );
                } else {
                    // Highlight dup and/or overlapping rows
                    for (
                        var i = 0;
                        i < data.additional_data.dup_index.length;
                        i++
                    )
                        highlight_error_row(
                            $table_mapping
                                .find(
                                    "td.meta .index:contains(" +
                                        data.additional_data.dup_index[i] +
                                        ")"
                                )
                                .closest("tr")
                        );

                    toastr.error(
                        data.error_message,
                        wwpp_single_product_admin_params.i18n_fail_update_discount_mapping,
                        {
                            closeButton: true,
                            showDuration: error_message_duration,
                        }
                    );

                    console.log(
                        wwpp_single_product_admin_params.i18n_fail_update_discount_mapping
                    );
                    console.log(data);
                    console.log("----------");
                }
            })
            .fail(function (jqXHR, textStatus, errorThrown) {
                toastr.error(
                    jqXHR.responseText,
                    wwpp_single_product_admin_params.i18n_fail_update_discount_mapping,
                    { closeButton: true, showDuration: error_message_duration }
                );

                console.log(
                    wwpp_single_product_admin_params.i18n_fail_update_discount_mapping
                );
                console.log(jqXHR);
                console.log("----------");
            })
            .always(function () {
                $parent_button_controls.find(".button").removeAttr("disabled");
                $parent_button_controls.removeClass("processing");
            });
    });

    $("body").delegate(".pqbwp-cancel", "click", function () {
        var $this = $(this),
            $parent_fields_container = $this.closest(
                ".product-quantity-based-wholesale-pricing"
            ),
            $parent_button_controls = $this.closest(".button-controls"),
            $table_mapping = $parent_fields_container.find(".pqbwp-mapping");

        reset_fields($parent_fields_container);

        $parent_button_controls.removeClass("edit-mode").addClass("add-mode");

        $table_mapping.find("tr").removeClass("edited").removeClass("disabled");
    });

    $("body").delegate(".pqbwp-mapping .edit", "click", function () {
        var $this = $(this),
            $current_tr = $this.closest("tr"),
            $table_mapping = $current_tr.closest(".pqbwp-mapping");
        ($parent_fields_container = $this.closest(
            ".product-quantity-based-wholesale-pricing"
        )),
            ($parent_button_controls =
                $parent_fields_container.find(".button-controls")),
            (index = $.trim($current_tr.find(".meta").find(".index").text())),
            (wholesale_role = $.trim(
                $current_tr.find(".meta").find(".wholesale-role").text()
            )),
            (start_qty = $.trim($current_tr.find(".start-qty").text())),
            (end_qtry = $.trim($current_tr.find(".end-qty").text())),
            (price_type = $.trim(
                $current_tr.find(".meta").find(".price-type").text()
            )),
            (wholesale_price = $.trim(
                $current_tr
                    .find(".meta")
                    .find(".wholesale-price")
                    .text()
                    .replace(".", wwpp_single_product_admin_vars.decimal_sep)
            )),
            (currency = "");

        if ($current_tr.find(".currency").length > 0)
            currency = $.trim($current_tr.find(".currency").text());

        $parent_fields_container.find(".mapping-index").val(index);
        $parent_fields_container
            .find(".pqbwp_registered_wholesale_roles")
            .val(wholesale_role)
            .attr("selected", "selected");
        $parent_fields_container
            .find(".pqbwp_minimum_order_quantity")
            .val(start_qty);
        $parent_fields_container
            .find(".pqbwp_maximum_order_quantity")
            .val(end_qtry);
        $parent_fields_container.find(".pqbwp_price_type").val(price_type);
        $parent_fields_container
            .find(".pqbwp_wholesale_price")
            .val(wholesale_price);

        if (currency)
            $parent_fields_container
                .find(".pqbwp_enabled_currencies")
                .val(currency);

        $current_tr.addClass("edited");

        $table_mapping.find("tr").addClass("disabled");

        $parent_button_controls.removeClass("add-mode").addClass("edit-mode");
    });

    $("body").delegate(".pqbwp-mapping .delete", "click", function () {
        var $this = $(this),
            $current_tr = $this.closest("tr"),
            $table_mapping = $current_tr.closest(".pqbwp-mapping"),
            $parent_fields_container = $this.closest(
                ".product-quantity-based-wholesale-pricing"
            ),
            post_id = $.trim($parent_fields_container.find(".post-id").text()),
            index = $.trim($current_tr.find(".meta").find(".index").text());

        $current_tr.addClass("edited");

        $table_mapping.find("tr").addClass("disabled");

        if (
            confirm(
                wwpp_single_product_admin_params.i18n_click_ok_remove_discount_mapping
            )
        ) {
            wwppBackendAjaxServices
                .deleteQuantityDiscountRule(post_id, index)
                .done(function (data, textStatus, jqXHR) {
                    if (data.status == "success") {
                        $current_tr.fadeOut("fast", function () {
                            $current_tr.remove();

                            reset_table_row_styling($table_mapping);

                            // If no more item then append the empty table placeholder
                            if (
                                $table_mapping.find("tbody").find("tr")
                                    .length <= 0
                            ) {
                                $table_mapping
                                    .find("tbody")
                                    .html(
                                        '<tr class="no-items">' +
                                            '<td class="colspanchange" colspan="5">' +
                                            wwpp_single_product_admin_params.i18n_no_quantity_discount +
                                            "</td>" +
                                            "</tr>"
                                    );
                            }
                        });

                        toastr.success(
                            "",
                            wwpp_single_product_admin_params.i18n_success_delete_discount,
                            {
                                closeButton: true,
                                showDuration: success_message_duration,
                            }
                        );
                    } else {
                        toastr.error(
                            data.error_message,
                            wwpp_single_product_admin_params.i18n_fail_delete_discount,
                            {
                                closeButton: true,
                                showDuration: error_message_duration,
                            }
                        );

                        console.log(
                            wwpp_single_product_admin_params.i18n_fail_delete_discount
                        );
                        console.log(data);
                        console.log("----------");
                    }
                })
                .fail(function (jqXHR, textStatus, errorThrown) {
                    toastr.error(
                        jqXHR.responseText,
                        wwpp_single_product_admin_params.i18n_fail_delete_discount,
                        {
                            closeButton: true,
                            showDuration: error_message_duration,
                        }
                    );

                    console.log(
                        wwpp_single_product_admin_params.i18n_fail_delete_discount
                    );
                    console.log(jqXHR);
                    console.log("----------");
                })
                .always(function () {
                    $table_mapping
                        .find("tr")
                        .removeClass("edited")
                        .removeClass("disabled");
                });
        } else {
            $table_mapping
                .find("tr")
                .removeClass("edited")
                .removeClass("disabled");
        }
    });

    /*
     |------------------------------------------------------------------------------------------------------------------
     | Initialization
     |------------------------------------------------------------------------------------------------------------------
     */

    $("body").find(".pqbwp_price_type").trigger("change");

    /*
     |------------------------------------------------------------------------------------------------------------------
     | Initialization
     |------------------------------------------------------------------------------------------------------------------
     */

    // Initialize product add-on visibility select box ( Product Add-on plugin integration )

    $(".wwpp-addon-group-role-visibility").each(function () {
        $("#" + $(this).attr("id")).chosen({ width: "100%" });
    });

    $("body").on("DOMNodeInserted", function (e) {
        var $condition_container = $(e.target);

        if ($condition_container.hasClass("woocommerce_product_addon")) {
            $condition_container
                .find(".wwpp-addon-group-role-visibility")
                .each(function () {
                    $("#" + $(this).attr("id")).chosen({ width: "100%" });
                });
        }

        return $(this);
    });
});
