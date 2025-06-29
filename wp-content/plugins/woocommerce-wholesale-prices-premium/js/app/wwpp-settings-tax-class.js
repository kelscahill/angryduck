jQuery(document).ready(function ($) {
  var $wholesale_role_tax_options = $("#wholesale-role-tax-class-options"),
    $field_controls = $wholesale_role_tax_options.find("#wholesale-role-tax-class-options-field-controls"),
    $wwpp_wholesale_roles = $field_controls.find("#wwpp-wholesale-roles"),
    $wwpp_tax_classes = $field_controls.find("#wwpp-tax-classes"),
    $button_controls = $wholesale_role_tax_options.find("#wholesale-role-tax-class-options-button-controls"),
    $add_btn = $button_controls.find("#add-mapping"),
    $save_btn = $button_controls.find("#save-mapping"),
    $cancel_edit_btn = $button_controls.find("#cancel-edit-mapping"),
    $mapping_table = $wholesale_role_tax_options.find("#wholesale-role-tax-class-options-mapping"),
    error_message_duration = "10000",
    success_message_duration = "5000";

  /*
     |------------------------------------------------------------------------------------------------------------------
     | Helper Functions
     |------------------------------------------------------------------------------------------------------------------
     */

  function generate_mapping_data($field_controls) {
    var wholesale_role_key = $.trim($field_controls.find("#wwpp-wholesale-roles").val()),
      wholesale_role_name = $.trim(
        $field_controls
          .find("#wwpp-wholesale-roles")
          .find("option[value='" + wholesale_role_key + "']")
          .text()
      ),
      tax_class = $.trim($field_controls.find("#wwpp-tax-classes").val()),
      tax_class_name = $.trim(
        $field_controls
          .find("#wwpp-tax-classes")
          .find("option[value='" + tax_class + "']")
          .text()
      ),
      error = [],
      data = {};

    if (wholesale_role_key === "") error.push(wwpp_settings_tax_class_var.please_specify_wholesale_role);

    if (tax_class === "") error.push(wwpp_settings_tax_class_var.please_specify_tax_classes);

    if (error.length <= 0) {
      data = {
        "wholesale-role-key": wholesale_role_key,
        "wholesale-role-name": wholesale_role_name,
        "tax-class": tax_class,
        "tax-class-name": tax_class_name,
      };
    }

    return {
      data: data,
      error: error,
    };
  }

  function reset_field_controls($field_controls) {
    $field_controls.find("select").val("").removeAttr("disabled").trigger("chosen:updated");
  }

  function button_controls_add_mode($button_controls) {
    $button_controls.removeClass("edit-mode").addClass("add-mode");
  }

  function button_controls_edit_mode($button_controls) {
    $button_controls.removeClass("add-mode").addClass("edit-mode");
  }

  function button_controls_processing_mode($button_controls) {
    $button_controls.find("input").attr("disabled", "disabled").end().addClass("processing");
  }

  function button_controls_normal_mode($button_controls) {
    $button_controls.find("input").removeAttr("disabled").end().removeClass("processing");
  }

  function save_mapping_entry_to_table($mapping_table, $mapping_entry_markup, mode, data) {
    if ($mapping_table.find("tbody tr.no-items").length) $mapping_table.find("tbody tr.no-items").remove();

    if (mode === "add") {
      $mapping_table.find("tbody").append($mapping_entry_markup);
    } else if (mode === "edit") {
      $mapping_table
        .find("tbody tr td.meta span.wholesale-role:contains('" + data["wholesale-role-key"] + "')")
        .closest("tr")
        .replaceWith($mapping_entry_markup);
    }
  }

  function remove_mapping_entry_to_table($mapping_table, wholesale_role_key) {
    $mapping_table
      .find("tbody tr td.meta span.wholesale-role:contains('" + wholesale_role_key + "')")
      .closest("tr")
      .remove();

    if ($mapping_table.find("tbody tr").length <= 0) {
      $mapping_table
        .find("tbody")
        .append(
          '<tr class="no-items">' +
            '<td class="colspanchange" colspan="3">' +
            wwpp_settings_tax_class_var.no_mappings_found +
            "</td>" +
            "</tr>"
        );
    }
  }

  function prepopulate_field_controls($field_controls, wholesale_role_key, tax_class) {
    $field_controls
      .find("#wwpp-wholesale-roles")
      .val(wholesale_role_key)
      .attr("disabled", "disabled")
      .trigger("chosen:updated");
    $field_controls.find("#wwpp-tax-classes").val(tax_class).trigger("chosen:updated");
  }

  function table_row_processing_mode($mapping_table, $tr) {
    $mapping_table.addClass("processing");
    $tr.addClass("processing");
  }

  function table_row_normal_mode($mapping_table) {
    $mapping_table.removeClass("processing").find("tr").removeClass("processing");
  }

  function save_mapping_entry(mode) {
    var d = generate_mapping_data($field_controls);

    if (d.error.length > 0) {
      var err_msg = "";

      for (var i = 0; i < d.error.length; i++) err_msg += d.error[i] + "\n";

      toastr.error(err_msg, wwpp_settings_tax_class_var.form_error, {
        closeButton: true,
        showDuration: error_message_duration,
      });
    } else {
      button_controls_processing_mode($button_controls);

      d.data.mode = mode;

      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwpp_save_tax_class_mapping",
          "mapping-data": d.data,
          nonce: wwpp_settings_tax_class_var.ajax_save_tax_class_mapping_nonce,
        },
        dataType: "json",
      })
        .done(function (data) {
          if (data.status === "success") {
            save_mapping_entry_to_table($mapping_table, data.entry_data_markup, mode, d.data);
            reset_field_controls($field_controls);
            button_controls_add_mode($button_controls);
            table_row_normal_mode($mapping_table);
          } else {
            console.log(data);
            toastr.error(data.error_msg, wwpp_settings_tax_class_var.form_error, {
              closeButton: true,
              showDuration: error_message_duration,
            });
          }
        })
        .fail(function (jqxhr) {
          console.log(jqxhr);
          toastr.error(wwpp_settings_tax_class_var.failed_save_mapping_entry, wwpp_settings_tax_class_var.form_error, {
            closeButton: true,
            showDuration: error_message_duration,
          });
        })
        .always(function () {
          button_controls_normal_mode($button_controls);
        });
    }
  }

  /*
     |------------------------------------------------------------------------------------------------------------------
     | Events
     |------------------------------------------------------------------------------------------------------------------
     */

  $add_btn.click(function () {
    save_mapping_entry("add");
  });

  $mapping_table.on("click", "tbody tr td.controls .edit", function () {
    var $tr = $(this).closest("tr"),
      wholesale_role_key = $.trim($tr.find(".meta .wholesale-role").text()),
      tax_class = $.trim($tr.find(".meta .tax-class").text());

    prepopulate_field_controls($field_controls, wholesale_role_key, tax_class);
    button_controls_edit_mode($button_controls);
    table_row_processing_mode($mapping_table, $tr);
  });

  $cancel_edit_btn.click(function () {
    reset_field_controls($field_controls);
    button_controls_add_mode($button_controls);
    table_row_normal_mode($mapping_table);
  });

  $save_btn.click(function () {
    save_mapping_entry("edit");
  });

  $mapping_table.on("click", "tbody tr td.controls .delete", function () {
    var $tr = $(this).closest("tr");

    table_row_processing_mode($mapping_table, $tr);

    if (confirm(wwpp_settings_tax_class_var.confirm_delete_mapping_entry)) {
      var wholesale_role_key = $.trim($tr.find(".meta .wholesale-role").text());

      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwpp_delete_tax_class_mapping",
          "wholesale-role-key": wholesale_role_key,
          nonce: wwpp_settings_tax_class_var.ajax_delete_tax_class_mapping_nonce,
        },
        datatype: "json",
      })
        .done(function (data) {
          if (data.status === "success") {
            remove_mapping_entry_to_table($mapping_table, wholesale_role_key);
          } else {
            console.log(data);
            toastr.error(data.error_msg, wwpp_settings_tax_class_var.form_error, {
              closeButton: true,
              showDuration: error_message_duration,
            });
          }
        })
        .fail(function (jqxhr) {
          console.log(jqxhr);
          toastr.error(
            wwpp_settings_tax_class_var.failed_delete_mapping_entry,
            wwpp_settings_tax_class_var.form_error,
            { closeButton: true, showDuration: error_message_duration }
          );
        })
        .always(function () {
          table_row_normal_mode($mapping_table);
        });
    } else table_row_normal_mode($mapping_table);
  });

  /*
     |------------------------------------------------------------------------------------------------------------------
     | Init
     |------------------------------------------------------------------------------------------------------------------
     */

  $wwpp_wholesale_roles.chosen({ allow_single_deselect: true, width: "300px" });
  $wwpp_tax_classes.chosen({ allow_single_deselect: true, width: "300px" });
});
