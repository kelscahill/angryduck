jQuery(document).ready(function ($) {
    /*
     |---------------------------------------------------------------------------------------------------------------
     | Variable Declarations
     |---------------------------------------------------------------------------------------------------------------
     */
    var $custom_field_controls = $(".custom-field-controls"),
        $button_controls = $(".button-controls"),
        $wholesale_lead_capture_custom_fields = $(
            "#wholesale-lead-capture-custom-fields"
        ),
        $select_field_options_container = $(".select-field-options-container"),
        $radio_field_options_container = $(".radio-field-options-container"),
        $checkbox_field_options_container = $(
            ".checkbox-field-options-container"
        ),
        errorMessageDuration = "10000",
        successMessageDuration = "5000";

    /*
     |---------------------------------------------------------------------------------------------------------------
     | Helper Functions
     |---------------------------------------------------------------------------------------------------------------
     */
    function removeTableNoItemsPlaceholder($table) {
        $table.find("tbody").find(".no-items").remove();
    }

    function resetTableRowStyling() {
        $wholesale_lead_capture_custom_fields
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

    function resetFields() {
        $custom_field_controls.find("#wwlc_cf_field_default_value").val("");
        $custom_field_controls.find("#wwlc_cf_field_name").val("");
        $custom_field_controls
            .find("#wwlc_cf_field_id")
            .val("")
            .removeAttr("readonly");
        $custom_field_controls
            .find("#wwlc_cf_field_type")
            .removeAttr("disabled")
            .find("option")
            .removeAttr("disabled")
            .end()
            .find("option:first")
            .prop("selected", true)
            .trigger("change");
        $custom_field_controls
            .find("#wwlc_cf_required_field")
            .removeAttr("checked");
        $custom_field_controls
            .find("#wwlc_cf_enabled_field")
            .removeAttr("checked");
        $custom_field_controls
            .find("#wwlc_cf_checkout_display_field")
            .removeAttr("checked");
        $custom_field_controls
            .find("#wwlc_cf_checkout_display_field")
            .parent()
            .show();
        $custom_field_controls.find("#wwlc_cf_field_order").val("");
        $custom_field_controls.find("#wwlc_cf_field_placeholder").val("");
        $custom_field_controls
            .find("#wwlc_cf_field_allowed_file_types")
            .val("doc,docx,xls,xlsx,pdf,jpg,png,gif,txt");
        $custom_field_controls
            .find("#wwlc_cf_field_max_allowed_file_size")
            .val(20);

        $custom_field_controls
            .find(".numeric-field-attributes-container")
            .find("#wwlc_cf_attrib_numeric_min")
            .val("")
            .end()
            .find("#wwlc_cf_attrib_numeric_max")
            .val("")
            .end()
            .find("#wwlc_cf_attrib_numeric_step")
            .val("")
            .end()
            .css("display", "none");

        $custom_field_controls
            .find(".select-field-options-container")
            .find(".options-list")
            .empty()
            .html(
                "<li>" +
                    '<input type="text" class="option_value" placeholder="' +
                    WWLCCustomFieldsControlVars.option_value +
                    '" value=""/>' +
                    '<input type="text" class="option_text" placeholder="' +
                    WWLCCustomFieldsControlVars.option_text +
                    '" value=""/>' +
                    '<span class="add dashicons dashicons-plus"></span>' +
                    '<span class="remove dashicons dashicons-no"></span>' +
                    "</li>"
            )
            .end()
            .css("display", "none");

        $custom_field_controls
            .find(".radio-field-options-container")
            .find(".options-list")
            .empty()
            .html(
                "<li>" +
                    '<input type="text" class="option_value" placeholder="' +
                    WWLCCustomFieldsControlVars.option_value +
                    '" value=""/>' +
                    '<input type="text" class="option_text" placeholder="' +
                    WWLCCustomFieldsControlVars.option_text +
                    '" value=""/>' +
                    '<span class="add dashicons dashicons-plus"></span>' +
                    '<span class="remove dashicons dashicons-no"></span>' +
                    "</li>"
            )
            .end()
            .css("display", "none");

        $custom_field_controls
            .find(".checkbox-field-options-container")
            .find(".options-list")
            .empty()
            .html(
                "<li>" +
                    '<input type="text" class="option_value" placeholder="' +
                    WWLCCustomFieldsControlVars.option_value +
                    '" value=""/>' +
                    '<input type="text" class="option_text" placeholder="' +
                    WWLCCustomFieldsControlVars.option_text +
                    '" value=""/>' +
                    '<span class="add dashicons dashicons-plus"></span>' +
                    '<span class="remove dashicons dashicons-no"></span>' +
                    "</li>"
            )
            .end()
            .css("display", "none");
    }

    function isNumber(n) {
        return !isNaN(parseFloat(n)) && isFinite(n);
    }

    /*
     |---------------------------------------------------------------------------------------------------------------
     | Events
     |---------------------------------------------------------------------------------------------------------------
     */
    $button_controls.find("#add-custom-field").click(function () {
        var $this = $(this),
            $errFields = [];

        $button_controls.addClass("processing");
        $this.attr("disabled", "disabled");

        var nonce = $this.closest('table.form-table').find('#wwlc_cf_nonce').val();
        
        var field_name = $.trim(
                $custom_field_controls.find("#wwlc_cf_field_name").val()
            ),
            field_id = $.trim(
                $custom_field_controls.find("#wwlc_cf_field_id").val()
            ),
            field_type = $.trim(
                $custom_field_controls.find("#wwlc_cf_field_type").val()
            ),
            field_allowed_filetypes = $.trim(
                $custom_field_controls
                    .find("#wwlc_cf_field_allowed_file_types")
                    .val()
            ),
            max_allowed_file_size = $.trim(
                $custom_field_controls
                    .find("#wwlc_cf_field_max_allowed_file_size")
                    .val()
            ),
            field_order = $.trim(
                $custom_field_controls.find("#wwlc_cf_field_order").val()
            ),
            field_placeholder = $.trim(
                $custom_field_controls.find("#wwlc_cf_field_placeholder").val()
            ),
            default_value = $.trim(
                $custom_field_controls
                    .find("#wwlc_cf_field_default_value")
                    .val()
            ),
            attributes = [],
            options = [];

        if (field_name == "")
            $errFields.push(WWLCCustomFieldsControlVars.field_name);

        if (field_id == "")
            $errFields.push(WWLCCustomFieldsControlVars.field_id);

        if (field_type == "")
            $errFields.push(WWLCCustomFieldsControlVars.field_type);

        if (field_type == "file" && field_allowed_filetypes == "")
            $errFields.push(WWLCCustomFieldsControlVars.allowed_file_types);

        if (field_type == "file" && max_allowed_file_size == "")
            $errFields.push(WWLCCustomFieldsControlVars.max_allowed_file_size);

        if (field_type == "hidden" && field_placeholder == "")
            $errFields.push(WWLCCustomFieldsControlVars.field_value);

        if (field_order == "")
            $errFields.push(WWLCCustomFieldsControlVars.field_order);

        // Get number field attributes
        if (field_type != "" && field_type == "number") {
            var number_field_attrib = $(".numeric-field-attributes-container"),
                min = $.trim(
                    number_field_attrib
                        .find("#wwlc_cf_attrib_numeric_min")
                        .val()
                ),
                max = number_field_attrib
                    .find("#wwlc_cf_attrib_numeric_max")
                    .val(),
                step = number_field_attrib
                    .find("#wwlc_cf_attrib_numeric_step")
                    .val();

            if (!isNumber(min)) min = 0;

            if (!isNumber(max)) max = "";

            if (!isNumber(step)) step = 1;

            attributes = {
                min: min,
                max: max,
                step: step,
            };
        } else if (field_type != "" && field_type == "select") {
            var errCounter = 0;

            $select_field_options_container
                .find(".options-list")
                .find("li")
                .each(function () {
                    var $this = $(this),
                        $value = $.trim($this.find(".option_value").val()),
                        $option = $.trim($this.find(".option_text").val());

                    if ($option != "") {
                        options.push({
                            value: $.trim($this.find(".option_value").val()),
                            text: $.trim($this.find(".option_text").val()),
                        });
                    } else errCounter++;
                });

            if (errCounter > 0)
                $errFields.push(
                    WWLCCustomFieldsControlVars.select_option_value
                );
        } else if (field_type != "" && field_type == "radio") {
            var errCounter = 0;

            $radio_field_options_container
                .find(".options-list")
                .find("li")
                .each(function () {
                    var $this = $(this),
                        $value = $.trim($this.find(".option_value").val()),
                        $option = $.trim($this.find(".option_text").val());

                    if ($value != "" && $option != "") {
                        options.push({
                            value: $.trim($this.find(".option_value").val()),
                            text: $.trim($this.find(".option_text").val()),
                        });
                    } else errCounter++;
                });

            if (errCounter > 0)
                $errFields.push(WWLCCustomFieldsControlVars.radio_option_value);
        } else if (field_type != "" && field_type == "checkbox") {
            var errCounter = 0;

            $checkbox_field_options_container
                .find(".options-list")
                .find("li")
                .each(function () {
                    var $this = $(this),
                        $value = $.trim($this.find(".option_value").val()),
                        $option = $.trim($this.find(".option_text").val());

                    if ($value != "" && $option != "") {
                        options.push({
                            value: $.trim($this.find(".option_value").val()),
                            text: $.trim($this.find(".option_text").val()),
                        });
                    } else errCounter++;
                });

            if (errCounter > 0)
                $errFields.push(
                    WWLCCustomFieldsControlVars.checkbox_option_value
                );
        } else if (field_type != "" && field_type == "email") {
            if (
                default_value != "" &&
                !wwlcFormValidator.validateEmail(default_value)
            )
                $errFields.push(
                    WWLCCustomFieldsControlVars.email_default_value
                );
        } else if (
            field_type != "" &&
            (field_type == "content" || field_type == "terms_conditions")
        ) {
            if (
                typeof tinymce.editors["wwlc_cf_field_default_value"] !==
                "undefined"
            )
                default_value =
                    tinymce.editors["wwlc_cf_field_default_value"].getContent();
        }

        if ($errFields.length > 0) {
            var errFieldsStr = "<ul>";
            for (var i = 0; i < $errFields.length; i++) {
                errFieldsStr += "<li>&rarr; " + $errFields[i] + "</li>";
            }
            errFieldsStr += "<ul>";

            toastr.error(
                errFieldsStr,
                WWLCCustomFieldsControlVars.empty_fields_error_message,
                { closeButton: true, showDuration: errorMessageDuration }
            );

            $button_controls.removeClass("processing");
            $this.removeAttr("disabled");

            return false;
        }

        var customField = {
            field_name: field_name,
            field_id: "wwlc_cf_" + field_id,
            field_type: field_type,
            field_order: field_order,
            required: $("#wwlc_cf_required_field").is(":checked") ? 1 : 0,
            field_placeholder: field_placeholder,
            default_value: default_value,
            checkout_display: $("#wwlc_cf_checkout_display_field").is(":checked") ? 1 : 0,
            enabled: $("#wwlc_cf_enabled_field").is(":checked") ? 1 : 0,
            attributes: attributes,
            options: options,
        };

        if (field_type == "file") {
            customField.field_allowed_filetypes = field_allowed_filetypes;
            customField.max_allowed_file_size = max_allowed_file_size;
        }

        wwlcBackEndAjaxServices
            .addRegistrationFormCustomField( nonce, customField )
            .done(function (data, textStatus, jqXHR) {
                if (data.status == "success") {
                    toastr.success(
                        "",
                        WWLCCustomFieldsControlVars.success_save_message,
                        {
                            closeButton: true,
                            showDuration: successMessageDuration,
                        }
                    );

                    removeTableNoItemsPlaceholder(
                        $wholesale_lead_capture_custom_fields
                    );

                    var tr_class = "";

                    if (
                        $wholesale_lead_capture_custom_fields.find("tr")
                            .length %
                            2 ==
                        0
                    )
                        tr_class = "odd alternate";
                    else tr_class = "even";

                    if (
                        customField.field_type == "content" ||
                        customField.field_type == "terms_conditions"
                    )
                        customField.default_value = "";

                    if (customField.field_type == "terms_conditions")
                        customField.required = true;

                    if (customField.field_type == "hidden") {
                        customField.default_value =
                            customField.field_placeholder;
                        customField.field_placeholder = "";
                    }

                    $wholesale_lead_capture_custom_fields
                        .find("tbody")
                        .append(
                            '<tr class="' +
                                tr_class +
                                ' edited">' +
                                '<td class="meta hidden"></td>' +
                                '<td class="wwlc_cf_td_field_name">' +
                                customField.field_name +
                                "</td>" +
                                '<td class="wwlc_cf_td_field_id">' +
                                customField.field_id +
                                "</td>" +
                                '<td class="wwlc_cf_td_field_type">' +
                                customField.field_type +
                                "</td>" +
                                '<td class="wwlc_cf_td_required">' +
                                (customField.required ? WWLCCustomFieldsControlVars.true : WWLCCustomFieldsControlVars.false) +
                                "</td>" +
                                '<td class="wwlc_cf_td_field_order">' +
                                customField.field_order +
                                "</td>" +
                                '<td class="wwlc_cf_td_field_placeholder">' +
                                customField.field_placeholder +
                                "</td>" +
                                '<td class="wwlc_cf_td_default_value">' +
                                customField.default_value +
                                "</td>" +
                                '<td class="wwlc_cf_td_checkout_display">' +
                                (customField.checkout_display ? WWLCCustomFieldsControlVars.true : WWLCCustomFieldsControlVars.false) +
                                "</td>" +
                                '<td class="wwlc_cf_td_enabled">' +
                                (customField.enabled ? WWLCCustomFieldsControlVars.true : WWLCCustomFieldsControlVars.false) +
                                "</td>" +
                                '<td class="controls">' +
                                '<a class="edit dashicons dashicons-edit"></a>' +
                                '<a class="delete dashicons dashicons-no"></a>' +
                                "</td>" +
                                "</tr>"
                        );

                    resetFields();

                    setTimeout(function () {
                        $wholesale_lead_capture_custom_fields
                            .find("tr.edited")
                            .removeClass("edited");
                    }, 2000);
                } else {
                    toastr.error(
                        data.error_message,
                        WWLCCustomFieldsControlVars.failed_save_message,
                        {
                            closeButton: true,
                            showDuration: errorMessageDuration,
                        }
                    );

                    console.log(
                        WWLCCustomFieldsControlVars.failed_save_message
                    );
                    console.log(data);
                    console.log("----------");
                }
            })
            .fail(function (jqXHR, textStatus, errorThrown) {
                toastr.error(
                    jqXHR.responseText,
                    WWLCCustomFieldsControlVars.failed_save_message,
                    { closeButton: true, showDuration: errorMessageDuration }
                );

                console.log(WWLCCustomFieldsControlVars.failed_save_message);
                console.log(jqXHR);
                console.log("----------");
            })
            .always(function () {
                $button_controls.removeClass("processing");
                $this.removeAttr("disabled");
            });
    });

    $button_controls.find("#save-custom-field").click(function () {
        var $this = $(this),
            $errFields = [];

        $button_controls.addClass("processing");
        $this
            .attr("disabled", "disabled")
            .siblings("#cancel-edit-custom-field")
            .attr("disabled", "disabled");

        var nonce = $this.closest('table.form-table').find('#wwlc_cf_nonce').val();

        var field_name = $.trim(
                $custom_field_controls.find("#wwlc_cf_field_name").val()
            ),
            field_id = $.trim(
                $custom_field_controls.find("#wwlc_cf_field_id").val()
            ),
            field_type = $.trim(
                $custom_field_controls.find("#wwlc_cf_field_type").val()
            ),
            field_allowed_filetypes = $.trim(
                $custom_field_controls
                    .find("#wwlc_cf_field_allowed_file_types")
                    .val()
            ),
            max_allowed_file_size = $.trim(
                $custom_field_controls
                    .find("#wwlc_cf_field_max_allowed_file_size")
                    .val()
            ),
            field_order = $.trim(
                $custom_field_controls.find("#wwlc_cf_field_order").val()
            ),
            field_placeholder = $.trim(
                $custom_field_controls.find("#wwlc_cf_field_placeholder").val()
            ),
            default_value = $.trim(
                $custom_field_controls
                    .find("#wwlc_cf_field_default_value")
                    .val()
            ),
            attributes = [],
            options = [];

        if (field_name == "")
            $errFields.push(WWLCCustomFieldsControlVars.field_name);

        if (field_id == "")
            $errFields.push(WWLCCustomFieldsControlVars.field_id);

        if (field_type == "")
            $errFields.push(WWLCCustomFieldsControlVars.field_type);

        if (field_type == "file" && field_allowed_filetypes == "")
            $errFields.push(WWLCCustomFieldsControlVars.allowed_file_types);

        if (field_type == "file" && max_allowed_file_size == "")
            $errFields.push(WWLCCustomFieldsControlVars.max_allowed_file_size);

        if (field_type == "hidden" && field_placeholder == "")
            $errFields.push(WWLCCustomFieldsControlVars.field_value);

        if (field_order == "")
            $errFields.push(WWLCCustomFieldsControlVars.field_order);

        // Get number field attributes
        if (field_type != "" && field_type == "number") {
            var number_field_attrib = $(".numeric-field-attributes-container"),
                min = $.trim(
                    number_field_attrib
                        .find("#wwlc_cf_attrib_numeric_min")
                        .val()
                ),
                max = number_field_attrib
                    .find("#wwlc_cf_attrib_numeric_max")
                    .val(),
                step = number_field_attrib
                    .find("#wwlc_cf_attrib_numeric_step")
                    .val();

            if (!isNumber(min)) min = 0;

            if (!isNumber(max)) max = "";

            if (!isNumber(step)) step = 1;

            attributes = {
                min: min,
                max: max,
                step: step,
            };
        } else if (field_type != "" && field_type == "select") {
            var errCounter = 0;

            $select_field_options_container
                .find(".options-list")
                .find("li")
                .each(function () {
                    var $this = $(this),
                        $value = $.trim($this.find(".option_value").val()),
                        $option = $.trim($this.find(".option_text").val());

                    if ($option != "") {
                        options.push({
                            value: $.trim($this.find(".option_value").val()),
                            text: $.trim($this.find(".option_text").val()),
                        });
                    } else errCounter++;
                });

            if (errCounter > 0)
                $errFields.push(
                    WWLCCustomFieldsControlVars.select_option_value
                );
        } else if (field_type != "" && field_type == "radio") {
            var errCounter = 0;

            $radio_field_options_container
                .find(".options-list")
                .find("li")
                .each(function () {
                    var $this = $(this),
                        $value = $.trim($this.find(".option_value").val()),
                        $option = $.trim($this.find(".option_text").val());

                    if ($value != "" && $option != "") {
                        options.push({
                            value: $.trim($this.find(".option_value").val()),
                            text: $.trim($this.find(".option_text").val()),
                        });
                    } else errCounter++;
                });

            if (errCounter > 0)
                $errFields.push(WWLCCustomFieldsControlVars.radio_option_value);
        } else if (field_type != "" && field_type == "checkbox") {
            var errCounter = 0;

            $checkbox_field_options_container
                .find(".options-list")
                .find("li")
                .each(function () {
                    var $this = $(this),
                        $value = $.trim($this.find(".option_value").val()),
                        $option = $.trim($this.find(".option_text").val());

                    if ($value != "" && $option != "") {
                        options.push({
                            value: $.trim($this.find(".option_value").val()),
                            text: $.trim($this.find(".option_text").val()),
                        });
                    } else errCounter++;
                });

            if (errCounter > 0)
                $errFields.push(
                    WWLCCustomFieldsControlVars.checkbox_option_value
                );
        } else if (field_type != "" && field_type == "email") {
            if (
                default_value != "" &&
                !wwlcFormValidator.validateEmail(default_value)
            )
                $errFields.push(
                    WWLCCustomFieldsControlVars.email_default_value
                );
        } else if (
            field_type != "" &&
            (field_type == "content" || field_type == "terms_conditions")
        ) {
            default_value =
                tinymce.editors["wwlc_cf_field_default_value"].getContent();
            $custom_field_controls
                .find(".content-wp-editor-field-container .wp-editor-wrap")
                .trigger("clear_wp_editor")
                .data("content", "");
        }

        // Display errors
        if ($errFields.length > 0) {
            var errFieldsStr = "<ul>";
            for (var i = 0; i < $errFields.length; i++) {
                errFieldsStr += "<li>&rarr; " + $errFields[i] + "</li>";
            }
            errFieldsStr += "<ul>";

            toastr.error(
                errFieldsStr,
                WWLCCustomFieldsControlVars.empty_fields_error_message,
                { closeButton: true, showDuration: errorMessageDuration }
            );

            $button_controls.removeClass("processing");
            $this
                .removeAttr("disabled")
                .siblings("#cancel-edit-custom-field")
                .removeAttr("disabled");

            return false;
        }

        var customField = {
            field_name: field_name,
            field_id: "wwlc_cf_" + field_id,
            field_type: field_type,
            field_order: field_order,
            field_placeholder: field_placeholder,
            default_value: default_value,
            required: $("#wwlc_cf_required_field").is(":checked") ? 1 : 0,
            enabled: $("#wwlc_cf_enabled_field").is(":checked") ? 1 : 0,
            checkout_display: $("#wwlc_cf_checkout_display_field").is(":checked") ? 1 : 0,
            attributes: attributes,
            options: options,
        };

        if (field_type == "file") {
            customField.field_allowed_filetypes = field_allowed_filetypes;
            customField.max_allowed_file_size = max_allowed_file_size;
        }

        wwlcBackEndAjaxServices
            .editRegistrationFormCustomField(nonce, customField)
            .done(function (data, textStatus, jqXHR) {
                if (data.status == "success") {
                    toastr.success(
                        "",
                        WWLCCustomFieldsControlVars.success_edit_message,
                        {
                            closeButton: true,
                            showDuration: successMessageDuration,
                        }
                    );

                    if (customField.field_type == "content") {
                        customField.required = "";
                        customField.default_value = "";
                    } else if (customField.field_type == "terms_conditions") {
                        customField.required = "true";
                        customField.default_value = "";
                    } else
                        customField.required = customField.required
                            ? true
                            : false;

                    if (customField.field_type == "hidden") {
                        customField.default_value =
                            customField.field_placeholder;
                        customField.field_placeholder = "";
                    }

                    $wholesale_lead_capture_custom_fields
                        .find("tr.edited")
                        .find(".wwlc_cf_td_field_name")
                        .text(customField.field_name)
                        .end()
                        .find(".wwlc_cf_td_field_id")
                        .text(customField.field_id)
                        .end()
                        .find(".wwlc_cf_td_field_type")
                        .text(customField.field_type)
                        .end()
                        .find(".wwlc_cf_td_field_order")
                        .text(customField.field_order)
                        .end()
                        .find(".wwlc_cf_td_field_placeholder")
                        .text(customField.field_placeholder)
                        .end()
                        .find(".wwlc_cf_td_field_default_value")
                        .text(customField.default_value)
                        .end()
                        .find(".wwlc_cf_td_required")
                        .text(customField.required ? WWLCCustomFieldsControlVars.true : WWLCCustomFieldsControlVars.false)
                        .end()
                        .find(".wwlc_cf_td_enabled")
                        .text(customField.enabled ? WWLCCustomFieldsControlVars.true : WWLCCustomFieldsControlVars.false)
                        .end()
                        .find(".wwlc_cf_td_checkout_display")
                        .text(customField.checkout_display ? WWLCCustomFieldsControlVars.true : WWLCCustomFieldsControlVars.false);

                    resetFields();

                    $button_controls
                        .removeClass("edit-mode")
                        .addClass("add-mode");

                    $wholesale_lead_capture_custom_fields
                        .find(".edit")
                        .css("display", "inline-block")
                        .end()
                        .find(".delete")
                        .css("display", "inline-block");

                    setTimeout(function () {
                        $wholesale_lead_capture_custom_fields
                            .find("tr.edited")
                            .removeClass("edited");
                    }, 1000);
                } else {
                    toastr.error(
                        data.error_message,
                        WWLCCustomFieldsControlVars.failed_edit_message,
                        {
                            closeButton: true,
                            showDuration: errorMessageDuration,
                        }
                    );

                    console.log(
                        WWLCCustomFieldsControlVars.failed_edit_message
                    );
                    console.log(data);
                    console.log("----------");
                }
            })
            .fail(function (jqXHR, textStatus, errorThrown) {
                toastr.error(
                    jqXHR.responseText,
                    WWLCCustomFieldsControlVars.failed_edit_message,
                    { closeButton: true, showDuration: errorMessageDuration }
                );

                console.log(WWLCCustomFieldsControlVars.failed_edit_message);
                console.log(jqXHR);
                console.log("----------");
            })
            .always(function () {
                $button_controls.removeClass("processing");

                $this
                    .removeAttr("disabled")
                    .siblings("#cancel-edit-custom-field")
                    .removeAttr("disabled");
            });
    });

    $button_controls.find("#cancel-edit-custom-field").click(function () {
        resetFields();

        $button_controls
            .removeClass("processing")
            .removeClass("edit-mode")
            .addClass("add-mode");

        $wholesale_lead_capture_custom_fields
            .find("tr")
            .removeClass("edited")
            .find(".edit")
            .css("display", "inline-block")
            .end()
            .find(".delete")
            .css("display", "inline-block");

        $custom_field_controls
            .find(".content-wp-editor-field-container .wp-editor-wrap")
            .trigger("clear_wp_editor")
            .data("content", "");
    });

    $wholesale_lead_capture_custom_fields.on("click", ".edit", function () {
        var $this = $(this),
            $current_tr = $this.closest("tr"),
            field_id = $.trim($current_tr.find(".wwlc_cf_td_field_id").text()),
            nonce = $this.closest('table.form-table').find('#wwlc_cf_nonce').val();

        $current_tr.addClass("edited");

        $wholesale_lead_capture_custom_fields
            .find(".edit")
            .css("display", "none")
            .end()
            .find(".delete")
            .css("display", "none");

        wwlcBackEndAjaxServices
            .getRegistrationFormCustomFieldByID(nonce, field_id)
            .done(function (data, textStatus, jqXHR) {
                if (data.status == "success") {
                    resetFields();

                    if (
                        data.custom_field.field_type == "content" ||
                        data.custom_field.field_type == "terms_conditions"
                    ) {
                        $custom_field_controls
                            .find(
                                ".content-wp-editor-field-container .wp-editor-wrap"
                            )
                            .trigger("clear_wp_editor")
                            .data("content", data.custom_field.default_value);
                        data.custom_field.default_value = "";
                    }

                    $custom_field_controls
                        .find("#wwlc_cf_field_name")
                        .val(data.custom_field.field_name)
                        .end()
                        .find("#wwlc_cf_field_id")
                        .val(data.custom_field.field_id.replace("wwlc_cf_", ""))
                        .attr("readonly", "readonly")
                        .end()
                        .find("#wwlc_cf_field_type")
                        .val(data.custom_field.field_type)
                        .trigger("change")
                        .end()
                        .find("#wwlc_cf_field_order")
                        .val(data.custom_field.field_order)
                        .end()
                        .find("#wwlc_cf_field_placeholder")
                        .val(data.custom_field.field_placeholder)
                        .end()
                        .find("#wwlc_cf_field_default_value")
                        .val(data.custom_field.default_value)
                        .end()
                        .find("#wwlc_cf_field_allowed_file_types")
                        .val(data.custom_field.field_allowed_filetypes)
                        .end()
                        .find("#wwlc_cf_field_max_allowed_file_size")
                        .val(data.custom_field.max_allowed_file_size)
                        .end();

                    // When editing fields, checkbox can't change to any other fields and vice versa
                    // It is because of the nature of a check box which can have multiple values
                    // All other fields have single value
                    if (data.custom_field.field_type == "checkbox")
                        $custom_field_controls
                            .find("#wwlc_cf_field_type")
                            .attr("disabled", "disabled");
                    else
                        $custom_field_controls
                            .find("#wwlc_cf_field_type")
                            .find("option[value='checkbox']")
                            .attr("disabled", "disabled");

                    if (data.custom_field.required == true)
                        $custom_field_controls
                            .find("#wwlc_cf_required_field")
                            .prop("checked", true);
                    //#89 change from .attr() to .prop() to update the current state of checkbox value.
                    else
                        $custom_field_controls
                            .find("#wwlc_cf_required_field")
                            .removeAttr("checked");

                    if (data.custom_field.enabled == true)
                        $custom_field_controls
                            .find("#wwlc_cf_enabled_field")
                            .prop("checked", true);
                    //#89 change from .attr() to .prop() to update the current state of checkbox value.
                    else
                        $custom_field_controls
                            .find("#wwlc_cf_enabled_field")
                            .removeAttr("checked");

                    if (data.custom_field.checkout_display == true)
                        $custom_field_controls
                            .find("#wwlc_cf_checkout_display_field")
                            .prop("checked", true);
                    else
                        $custom_field_controls
                            .find("#wwlc_cf_checkout_display_field")
                            .removeAttr("checked");

                    $custom_field_controls
                        .find("#wwlc_cf_field_placeholder")
                        .val(data.custom_field.field_placeholder);

                    // Get number field attributes
                    if (data.custom_field.field_type == "number") {
                        var $numeric_field_attributes_container = $(
                            ".numeric-field-attributes-container"
                        );

                        $numeric_field_attributes_container
                            .find("#wwlc_cf_attrib_numeric_min")
                            .val(data.custom_field.attributes.min)
                            .end()
                            .find("#wwlc_cf_attrib_numeric_max")
                            .val(data.custom_field.attributes.max)
                            .end()
                            .find("#wwlc_cf_attrib_numeric_step")
                            .val(data.custom_field.attributes.step)
                            .end()
                            .css("display", "block");
                    } else if (data.custom_field.field_type == "select") {
                        var li_html = "";
                        for (
                            var i = 0;
                            i < data.custom_field.options.length;
                            i++
                        ) {
                            li_html +=
                                "<li>" +
                                '<input type="text" class="option_value" placeholder="' +
                                WWLCCustomFieldsControlVars.option_value +
                                '" value="' +
                                data.custom_field.options[i].value +
                                '"/>' +
                                '<input type="text" class="option_text" placeholder="' +
                                WWLCCustomFieldsControlVars.option_text +
                                '" value="' +
                                data.custom_field.options[i].text +
                                '"/>' +
                                '<span class="add dashicons dashicons-plus"></span>' +
                                '<span class="remove dashicons dashicons-no"></span>' +
                                "</li>";
                        }

                        $select_field_options_container
                            .find(".options-list")
                            .html(li_html)
                            .end()
                            .css("display", "block");
                    } else if (data.custom_field.field_type == "radio") {
                        var li_html = "";
                        for (
                            var i = 0;
                            i < data.custom_field.options.length;
                            i++
                        ) {
                            li_html +=
                                "<li>" +
                                '<input type="text" class="option_value" placeholder="' +
                                WWLCCustomFieldsControlVars.option_value +
                                '" value="' +
                                data.custom_field.options[i].value +
                                '"/>' +
                                '<input type="text" class="option_text" placeholder="' +
                                WWLCCustomFieldsControlVars.option_text +
                                '" value="' +
                                data.custom_field.options[i].text +
                                '"/>' +
                                '<span class="add dashicons dashicons-plus"></span>' +
                                '<span class="remove dashicons dashicons-no"></span>' +
                                "</li>";
                        }

                        $radio_field_options_container
                            .find(".options-list")
                            .html(li_html)
                            .end()
                            .css("display", "block");
                    } else if (data.custom_field.field_type == "checkbox") {
                        var li_html = "";
                        for (
                            var i = 0;
                            i < data.custom_field.options.length;
                            i++
                        ) {
                            li_html +=
                                "<li>" +
                                '<input type="text" class="option_value" placeholder="' +
                                WWLCCustomFieldsControlVars.option_value +
                                '" value="' +
                                data.custom_field.options[i].value +
                                '"/>' +
                                '<input type="text" class="option_text" placeholder="' +
                                WWLCCustomFieldsControlVars.option_text +
                                '" value="' +
                                data.custom_field.options[i].text +
                                '"/>' +
                                '<span class="add dashicons dashicons-plus"></span>' +
                                '<span class="remove dashicons dashicons-no"></span>' +
                                "</li>";
                        }

                        $checkbox_field_options_container
                            .find(".options-list")
                            .html(li_html)
                            .end()
                            .css("display", "block");
                    }

                    $button_controls
                        .removeClass("processing")
                        .removeClass("add-mode")
                        .addClass("edit-mode");
                } else {
                    toastr.error(
                        data.error_message,
                        WWLCCustomFieldsControlVars.failed_retrieve_message,
                        {
                            closeButton: true,
                            showDuration: errorMessageDuration,
                        }
                    );

                    console.log(
                        WWLCCustomFieldsControlVars.failed_retrieve_message
                    );
                    console.log(data);
                    console.log("----------");

                    $current_tr.removeClass("edited");

                    $wholesale_lead_capture_custom_fields
                        .find(".edit")
                        .css("display", "inline-block")
                        .end()
                        .find(".delete")
                        .css("display", "inline-block");
                }
            })
            .fail(function (jqXHR, textStatus, errorThrown) {
                toastr.error(
                    jqXHR.responseText,
                    WWLCCustomFieldsControlVars.failed_retrieve_message,
                    { closeButton: true, showDuration: errorMessageDuration }
                );

                console.log(
                    WWLCCustomFieldsControlVars.failed_retrieve_message
                );
                console.log(jqXHR);
                console.log("----------");

                $current_tr.removeClass("edited");

                $wholesale_lead_capture_custom_fields
                    .find(".edit")
                    .css("display", "inline-block")
                    .end()
                    .find(".delete")
                    .css("display", "inline-block");
            });
    });

    $wholesale_lead_capture_custom_fields.on("click", ".delete", function () {
        var $this = $(this),
            $current_tr = $this.closest("tr"),
            nonce = $this.closest('table.form-table').find('#wwlc_cf_nonce').val();

        $current_tr.addClass("edited");

        if (confirm(WWLCCustomFieldsControlVars.confirm_box_message)) {
            var field_id = $.trim(
                $current_tr.find(".wwlc_cf_td_field_id").text()
            );

            $wholesale_lead_capture_custom_fields
                .find(".edit")
                .css("display", "none")
                .end()
                .find(".delete")
                .css("display", "none");

            wwlcBackEndAjaxServices
                .deleteRegistrationFormCustomField(nonce, field_id)
                .done(function (data, textStatus, jqXHR) {
                    if (data.status == "success") {
                        $current_tr.fadeOut("fast", function () {
                            $current_tr.remove();

                            resetTableRowStyling();

                            if (
                                $wholesale_lead_capture_custom_fields
                                    .find("tbody")
                                    .find("tr").length <= 0
                            ) {
                                $wholesale_lead_capture_custom_fields
                                    .find("tbody")
                                    .html(
                                        '<tr class="no-items">' +
                                            '<td class="colspanchange" colspan="7">' +
                                            WWLCCustomFieldsControlVars.no_custom_field_message +
                                            "</td>" +
                                            "</tr>"
                                    );
                            }
                        });

                        toastr.success(
                            "",
                            WWLCCustomFieldsControlVars.success_delete_message,
                            {
                                closeButton: true,
                                showDuration: successMessageDuration,
                            }
                        );
                    } else {
                        toastr.error(
                            data.error_message,
                            WWLCCustomFieldsControlVars.failed_delete_message,
                            {
                                closeButton: true,
                                showDuration: errorMessageDuration,
                            }
                        );

                        console.log("Failed To Delete Custom Field");
                        console.log(data);
                        console.log("----------");

                        $current_tr.removeClass("edited");
                    }
                })
                .fail(function (jqXHR, textStatus, errorThrown) {
                    toastr.error(
                        jqXHR.responseText,
                        WWLCCustomFieldsControlVars.failed_delete_message,
                        {
                            closeButton: true,
                            showDuration: errorMessageDuration,
                        }
                    );

                    console.log(
                        WWLCCustomFieldsControlVars.failed_delete_message
                    );
                    console.log(jqXHR);
                    console.log("----------");

                    $current_tr.removeClass("edited");
                })
                .always(function () {
                    $wholesale_lead_capture_custom_fields
                        .find(".edit")
                        .css("display", "inline-block")
                        .end()
                        .find(".delete")
                        .css("display", "inline-block");
                });
        } else {
            $current_tr.removeClass("edited");
        }
    });

    $custom_field_controls.find("#wwlc_cf_field_type").change(function () {
        var $this = $(this),
            type = $this.val(),
            content;

        // reset form fields.
        $custom_field_controls
            .find("#wwlc_cf_field_default_value")
            .parent()
            .hide();
        $custom_field_controls
            .find("#wwlc_cf_field_placeholder")
            .parent()
            .hide();
        $custom_field_controls.find(".select-field-options-container").hide();
        $custom_field_controls
            .find(".numeric-field-attributes-container")
            .hide();
        $custom_field_controls.find(".radio-field-options-container").hide();
        $custom_field_controls.find(".checkbox-field-options-container").hide();
        $custom_field_controls.find(".file-field-options-container").hide();
        $custom_field_controls
            .find(".placeholder-field-container .hidden_label")
            .hide();
        $custom_field_controls
            .find(".placeholder-field-container .default_label")
            .show();
        $custom_field_controls.find("#wwlc_cf_required_field").parent().show();
        $custom_field_controls.find("#wwlc_cf_checkout_display_field").parent().show();
        $custom_field_controls
            .find(".content-wp-editor-field-container")
            .hide();
        $custom_field_controls.find(".required-field-container").show();
        $custom_field_controls
            .find(".field-container .wp-editor-wrap")
            .trigger("clear_wp_editor");
        $custom_field_controls.find(".field-container .content-notice").hide();
        $button_controls.find("#save-custom-field").prop("disabled", false);
        $button_controls.find("#add-custom-field").prop("disabled", false);

        switch (type) {
            case "number":
                $custom_field_controls
                    .find(".numeric-field-attributes-container")
                    .show();
                $custom_field_controls
                    .find("#wwlc_cf_field_default_value")
                    .attr("type", "number")
                    .parent()
                    .show();
                $custom_field_controls
                    .find("#wwlc_cf_field_placeholder")
                    .parent()
                    .show();
                break;
            case "select":
                $custom_field_controls
                    .find(".select-field-options-container")
                    .show();
                break;
            case "radio":
                $custom_field_controls
                    .find(".radio-field-options-container")
                    .show();
                break;
            case "checkbox":
                $custom_field_controls
                    .find(".checkbox-field-options-container")
                    .show();
                break;
            case "file":
                $custom_field_controls
                    .find(".file-field-options-container")
                    .show();
                $custom_field_controls
                    .find("#wwlc_cf_checkout_display_field")
                    .parent()
                    .hide();
                break;
            case "hidden":
                $custom_field_controls
                    .find(".placeholder-field-container .hidden_label")
                    .show();
                $custom_field_controls
                    .find(".placeholder-field-container .default_label")
                    .hide();
                $custom_field_controls
                    .find("#wwlc_cf_required_field")
                    .parent()
                    .hide();
                $custom_field_controls
                    .find("#wwlc_cf_checkout_display_field")
                    .parent()
                    .hide();
                $custom_field_controls
                    .find("#wwlc_cf_field_placeholder")
                    .parent()
                    .show();
                break;
            case "content":
            case "terms_conditions":
                content = $custom_field_controls
                    .find(".content-wp-editor-field-container .wp-editor-wrap")
                    .data("content");
                $custom_field_controls
                    .find(".content-wp-editor-field-container")
                    .show();
                $custom_field_controls
                    .find(".content-wp-editor-field-container .wwlc-spinner")
                    .show();
                $custom_field_controls.find(".required-field-container").hide();
                $custom_field_controls.find(".checkout-display-field-container").hide();
                wwlcBackEndAjaxServices
                    .getContentFieldEditor(content)
                    .done(function (data, textStatus, jqXHR) {
                        var $field_wrap = $custom_field_controls.find(
                                ".content-wp-editor-field-container"
                            ),
                            $wpeditor_wrap =
                                $field_wrap.find(".wp-editor-wrap"),
                            editorType;

                        $field_wrap.find(".wwlc-spinner").hide();
                        $wpeditor_wrap.html(data);

                        editorType = $wpeditor_wrap
                            .find(".wp-core-ui.wp-editor-wrap")
                            .hasClass("tmce-active")
                            ? ".switch-tmce"
                            : ".switch-html";
                        $custom_field_controls
                            .find(".wp-editor-wrap button" + editorType)
                            .trigger("click");
                    });
                break;
            default:
                $custom_field_controls
                    .find("#wwlc_cf_field_default_value")
                    .attr("type", "text")
                    .parent()
                    .show();
                $custom_field_controls
                    .find("#wwlc_cf_field_placeholder")
                    .parent()
                    .show();
                break;
        }
    });

    $select_field_options_container
        .find(".options-list")
        .on("click", ".add", function () {
            var $this = $(this),
                $current_li = $this.closest("li");

            $current_li.after(
                "<li>" +
                    '<input type="text" class="option_value" placeholder="' +
                    WWLCCustomFieldsControlVars.option_value +
                    '" value=""/>' +
                    '<input type="text" class="option_text" placeholder="' +
                    WWLCCustomFieldsControlVars.option_text +
                    '" value=""/>' +
                    '<span class="add dashicons dashicons-plus"></span>' +
                    '<span class="remove dashicons dashicons-no"></span>' +
                    "</li>"
            );
        });

    $radio_field_options_container
        .find(".options-list")
        .on("click", ".add", function () {
            var $this = $(this),
                $current_li = $this.closest("li");

            $current_li.after(
                "<li>" +
                    '<input type="text" class="option_value" placeholder="' +
                    WWLCCustomFieldsControlVars.option_value +
                    '" value=""/>' +
                    '<input type="text" class="option_text" placeholder="' +
                    WWLCCustomFieldsControlVars.option_text +
                    '" value=""/>' +
                    '<span class="add dashicons dashicons-plus"></span>' +
                    '<span class="remove dashicons dashicons-no"></span>' +
                    "</li>"
            );
        });

    $checkbox_field_options_container
        .find(".options-list")
        .on("click", ".add", function () {
            var $this = $(this),
                $current_li = $this.closest("li");

            $current_li.after(
                "<li>" +
                    '<input type="text" class="option_value" placeholder="' +
                    WWLCCustomFieldsControlVars.option_value +
                    '" value=""/>' +
                    '<input type="text" class="option_text" placeholder="' +
                    WWLCCustomFieldsControlVars.option_text +
                    '" value=""/>' +
                    '<span class="add dashicons dashicons-plus"></span>' +
                    '<span class="remove dashicons dashicons-no"></span>' +
                    "</li>"
            );
        });

    $select_field_options_container
        .find(".options-list")
        .on("click", ".remove", function () {
            var $this = $(this),
                $current_li = $this.closest("li");

            $current_li.fadeOut("fast", function () {
                $current_li.remove();
            });
        });

    $radio_field_options_container
        .find(".options-list")
        .on("click", ".remove", function () {
            var $this = $(this),
                $current_li = $this.closest("li");

            $current_li.fadeOut("fast", function () {
                $current_li.remove();
            });
        });

    $checkbox_field_options_container
        .find(".options-list")
        .on("click", ".remove", function () {
            var $this = $(this),
                $current_li = $this.closest("li");

            $current_li.fadeOut("fast", function () {
                $current_li.remove();
            });
        });

    /*
     |---------------------------------------------------------------------------------------------------------------
     | On Load
     |---------------------------------------------------------------------------------------------------------------
     */
    $custom_field_controls
        .find(".select-field-options-container")
        .find(".options-list")
        .empty()
        .html(
            "<li>" +
                '<input type="text" class="option_value" placeholder="' +
                WWLCCustomFieldsControlVars.option_value +
                '" value=""/>' +
                '<input type="text" class="option_text" placeholder="' +
                WWLCCustomFieldsControlVars.option_text +
                '" value=""/>' +
                '<span class="add dashicons dashicons-plus"></span>' +
                '<span class="remove dashicons dashicons-no"></span>' +
                "</li>"
        );

    $custom_field_controls
        .find(".radio-field-options-container")
        .find(".options-list")
        .empty()
        .html(
            "<li>" +
                '<input type="text" class="option_value" placeholder="' +
                WWLCCustomFieldsControlVars.option_value +
                '" value=""/>' +
                '<input type="text" class="option_text" placeholder="' +
                WWLCCustomFieldsControlVars.option_text +
                '" value=""/>' +
                '<span class="add dashicons dashicons-plus"></span>' +
                '<span class="remove dashicons dashicons-no"></span>' +
                "</li>"
        );

    $custom_field_controls
        .find(".checkbox-field-options-container")
        .find(".options-list")
        .empty()
        .html(
            "<li>" +
                '<input type="text" class="option_value" placeholder="' +
                WWLCCustomFieldsControlVars.option_value +
                '" value=""/>' +
                '<input type="text" class="option_text" placeholder="' +
                WWLCCustomFieldsControlVars.option_text +
                '" value=""/>' +
                '<span class="add dashicons dashicons-plus"></span>' +
                '<span class="remove dashicons dashicons-no"></span>' +
                "</li>"
        );

    $custom_field_controls.on(
        "clear_wp_editor",
        ".wp-editor-wrap",
        function () {
            $(this).data("editor", "");
            $(this).html("");
            $("body").find(".mce-widget").remove();
            $("body").find(".mce-toolbar-grp").remove();
            $("body").find(".ui-widget-content").remove();
            $("body").find(".ui-helper-hidden-accessible").remove();
        }
    );

    $custom_field_controls.on(
        "click",
        ".wp-editor-wrap button.wp-switch-editor",
        function () {
            var $parent = $(this).closest(".field-container"),
                editor = $(this).hasClass("switch-tmce") ? "visual" : "html",
                toggle = editor == "visual" ? false : true;
            display = editor == "visual" ? "none" : "block";

            $parent.find(".content-notice").css("display", display);
            $button_controls
                .find("#save-custom-field")
                .prop("disabled", toggle);
            $button_controls.find("#add-custom-field").prop("disabled", toggle);
        }
    );
});
