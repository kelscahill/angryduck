jQuery(document).ready(function ($) {
  /*
     |------------------------------------------------------------------------------------------------------------------
     | Wholesale Role Cart Quantity Based Wholesale Discount
     |------------------------------------------------------------------------------------------------------------------
     */

  var $wrcqbwdc = $("#wholesale-role-cart-qty-based-wholesale-discount-container"),
    $wrcqbwdc_field_controls = $wrcqbwdc.find(".field-controls"),
    $wrcqbwdc_mapping_index = $wrcqbwdc.find("#mapping-index"),
    $wrcqbwdc_wholesale_roles = $wrcqbwdc.find("#wrcqbwd-wholesale-roles"),
    $wrcqbwdc_starting_qty = $wrcqbwdc.find("#wrcqbwd-starting-qty"),
    $wrcqbwdc_ending_qty = $wrcqbwdc.find("#wrcqbwd-ending-qty"),
    $wrcqbwdc_discount = $wrcqbwdc.find("#wrcqbwd-percent-discount"),
    $wrcqbwdc_button_controls = $wrcqbwdc.find(".button-controls"),
    $wrcqbwdc_add_mapping = $wrcqbwdc.find("#wrcqbwd-add-mapping"),
    $wrcqbwdc_save_mapping = $wrcqbwdc.find("#wrcqbwd-save-mapping"),
    $wrcqbwdc_cancel_save_mapping = $wrcqbwdc.find("#wrcqbwd-cancel-edit-mapping"),
    $wrcqbwdc_mapping_table = $wrcqbwdc.find("#wholesale-role-cart-qty-based-wholesale-discount-mapping"),
    error_message_duration = "10000",
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

  function wrcqbwdc_construct_mapping_data(wholesale_role, wholesale_name, start_qty, end_qty, discount, index) {
    var err = [];

    wholesale_role = $.trim(wholesale_role);
    wholesale_name = $.trim(wholesale_name);
    start_qty = parseInt($.trim(start_qty), 10);
    end_qty = $.trim(end_qty);
    discount = $.trim(parseFloat(discount));

    end_qty = end_qty != "" ? parseInt(end_qty, 10) : end_qty;

    if (index) index = $.trim(index);

    if (wholesale_role == "") err.push(wwpp_wrcqbwd_params.i18n_please_specify_wholesale_role);

    if (start_qty <= 0 || start_qty == "" || isNaN(start_qty)) err.push(wwpp_wrcqbwd_params.i18n_invalid_start_qty);

    if (end_qty === 0 || isNaN(end_qty) || end_qty < 0) err.push(wwpp_wrcqbwd_params.i18n_invalid_end_qty);

    if (discount <= 0 || discount == "" || isNaN(discount)) err.push(wwpp_wrcqbwd_params.i18n_invalid_percent_discount);

    if (err.length <= 0) {
      var data = {
        wholesale_role: wholesale_role,
        wholesale_name: wholesale_name,
        start_qty: start_qty,
        end_qty: end_qty,
        percent_discount: discount,
      };

      if (index) data.index = index;

      return {
        status: "success",
        data: data,
      };
    } else return { status: "fail", error: err };
  }

  function wrcqbwdc_prepopulate_form_with_entry_data($tr, $field_controls, $button_controls) {
    $field_controls.find("#mapping-index").val($.trim($tr.attr("data-index")));
    $field_controls
      .find("#wrcqbwd-wholesale-roles")
      .val($.trim($tr.find(".meta .wholesale-role").text()))
      .trigger("chosen:updated");
    $field_controls.find("#wrcqbwd-starting-qty").val($.trim($tr.find(".start_qty").text()));
    $field_controls.find("#wrcqbwd-ending-qty").val($.trim($tr.find(".end_qty").text()));
    $field_controls
      .find("#wrcqbwd-percent-discount")
      .val($.trim($tr.find(".percent_discount").text().replace("%", "")));

    $button_controls.removeClass("add-mode").addClass("edit-mode");
  }

  function wrcqbwdc_form_in_processing_mode($field_controls, $button_controls) {
    $field_controls.find(".field-control").attr("disabled", "disabled");
    $button_controls.find(".button").attr("disabled", "disabled").siblings(".spinner").css("visibility", "visible");
  }

  function wrcqbwdc_form_in_normal_mode($field_controls, $button_controls) {
    $field_controls.find(".field-control").removeAttr("disabled");
    $button_controls.find(".button").removeAttr("disabled").siblings(".spinner").css("visibility", "hidden");
  }

  function wrcqbwdc_add_new_mapping_table_entry(index, data, $mapping_table) {
    var entry_str =
      '<tr data-index="' +
      index +
      '">' +
      '<td class="meta hidden">' +
      '<span class="index">' +
      index +
      "</span>" +
      '<span class="wholesale-role">' +
      data.wholesale_role +
      "</span>" +
      '<span class="wholesale-discount">' +
      data.percent_discount +
      "</span>" +
      "</td>" +
      '<td class="wholesale_role">' +
      data.wholesale_name +
      "</td>" +
      '<td class="start_qty">' +
      data.start_qty +
      "</td>" +
      '<td class="end_qty">' +
      data.end_qty +
      "</td>" +
      '<td class="percent_discount">' +
      data.percent_discount +
      "%</td>" +
      '<td class="controls">' +
      '<a class="edit dashicons dashicons-edit"></a>' +
      '<a class="delete dashicons dashicons-no"></a>' +
      "</td>" +
      "</tr>";

    if ($mapping_table.find("tbody tr.no-items").length > 0) $mapping_table.find("tbody").html(entry_str);
    else $mapping_table.find("tbody").append(entry_str);
  }

  function wrcqbwdc_edit_mapping_table_entry(index, data, $mapping_table) {
    $mapping_table
      .find("tbody tr[data-index='" + index + "']")
      .find("td.meta .wholesale-role")
      .text(data.wholesale_role)
      .end()
      .find("td.meta .wholesale-discount")
      .text(data.percent_discount)
      .end()
      .find("td.wholesale_role")
      .text(data.wholesale_name)
      .end()
      .find("td.start_qty")
      .text(data.start_qty)
      .end()
      .find("td.end_qty")
      .text(data.end_qty)
      .end()
      .find("td.percent_discount")
      .text(data.percent_discount + "%");
  }

  function wrcqbwdc_remove_mapping_table_entry(index, $mapping_table) {
    $mapping_table.find("tbody tr[data-index='" + index + "']").remove();

    if ($mapping_table.find("tbody tr").length <= 0)
      $mapping_table
        .find("tbody")
        .append(
          '<tr class="no-items"><td class="colspanchange" colspan="5">' +
            wwpp_wrcqbwd_params.i18n_no_mappings_found +
            "</td></tr>"
        );
  }

  function wrcqbwdc_reset_form($field_controls, $button_controls, $mapping_table) {
    wrcqbwdc_form_in_normal_mode($field_controls, $button_controls);

    $button_controls.removeClass("edit-mode").addClass("add-mode");
    $field_controls.find(".field-control").val("");

    $field_controls.find("#wrcqbwd-wholesale-roles").trigger("chosen:updated");

    $mapping_table.find("tbody tr").removeClass("editing").find("td.controls .dashicons").css("visibility", "visible");
  }

  /*
    |------------------------------------------------------------------------------------------------------------------
    | Events
    |------------------------------------------------------------------------------------------------------------------
    */

  $wrcqbwdc_add_mapping.click(function (e) {
    e.preventDefault();

    var result = wrcqbwdc_construct_mapping_data(
      $wrcqbwdc_wholesale_roles.val(),
      $wrcqbwdc_wholesale_roles.find("option:selected").text(),
      $wrcqbwdc_starting_qty.val(),
      $wrcqbwdc_ending_qty.val(),
      $wrcqbwdc_discount.val()
    );

    if (result.status !== "success") {
      var err_msg = "";

      for (var i = 0; i < result.error.length; i++) err_msg += result.error[i] + "<br/>";

      toastr.error(err_msg, wwpp_wrcqbwd_params.i18n_form_error, {
        closeButton: true,
        showDuration: error_message_duration,
      });
    } else {
      wrcqbwdc_form_in_processing_mode($wrcqbwdc_field_controls, $wrcqbwdc_button_controls);

      $.ajax({
        url: ajaxurl,
        type: "post",
        data: {
          action: "wwpp_add_wholesale_role_qty_based_discount_mapping",
          qty_based_discount_mapping: result.data,
          user_id: wwpp_wrcqbwd_params.user_id,
          nonce: wwpp_wrcqbwd_params.add_wholesale_role_qty_based_discount_mapping_nonce,
        },
        dataType: "json",
      })
        .done(function (data) {
          if (data.status == "success") {
            wrcqbwdc_add_new_mapping_table_entry(data.last_inserted_item_index, result.data, $wrcqbwdc_mapping_table);
            wrcqbwdc_reset_form($wrcqbwdc_field_controls, $wrcqbwdc_button_controls, $wrcqbwdc_mapping_table);
          } else {
            toastr.error(data.error_msg, wwpp_wrcqbwd_params.i18n_add_mapping_error, {
              closeButton: true,
              showDuration: error_message_duration,
            });
            console.log(data);
          }
        })
        .fail(function (jqxhr) {
          toastr.error(
            wwpp_wrcqbwd_params.i18n_failed_to_record_new_mapping_entry,
            wwpp_wrcqbwd_params.i18n_add_mapping_error,
            { closeButton: true, showDuration: error_message_duration }
          );
          console.log(jqxhr);
        })
        .always(function () {
          wrcqbwdc_form_in_normal_mode($wrcqbwdc_field_controls, $wrcqbwdc_button_controls);
        });
    }
  });

  $wrcqbwdc_mapping_table.on("click", "tbody td.controls .delete", function (e) {
    e.preventDefault();

    var $tr = $(this).closest("tr");

    $tr.addClass("editing");

    if (confirm(wwpp_wrcqbwd_params.i18n_confirm_remove_mapping)) {
      $wrcqbwdc_cancel_save_mapping.find("tbody tr td.controls .dashicons").css("visibility", "hidden");

      var index = $.trim($tr.attr("data-index"));

      $.ajax({
        url: ajaxurl,
        type: "post",
        data: {
          action: "wwpp_delete_wholesale_role_qty_based_discount_mapping",
          index: index,
          user_id: wwpp_wrcqbwd_params.user_id,
          nonce: wwpp_wrcqbwd_params.delete_wholesale_role_qty_based_discount_mapping_nonce,
        },
        dataType: "json",
      })
        .done(function (data) {
          if (data.status === "success") wrcqbwdc_remove_mapping_table_entry(index, $wrcqbwdc_mapping_table);
          else {
            toastr.error(data.error_msg, wwpp_wrcqbwd_params.i18n_delete_mapping_error, {
              closeButton: true,
              showDuration: error_message_duration,
            });
            console.log(data);
          }
        })
        .fail(function (jqxhr) {
          toastr.error(
            wwpp_wrcqbwd_params.i18n_failed_to_deleted_mapping,
            wwpp_wrcqbwd_params.i18n_delete_mapping_error,
            { closeButton: true, showDuration: error_message_duration }
          );
          console.log(jqxhr);
        })
        .always(function () {
          $wrcqbwdc_cancel_save_mapping.find("tbody tr td.controls .dashicons").css("visibility", "visible");

          if ($tr) $tr.removeClass("editing");
        });
    } else $tr.removeClass("editing");
  });

  $wrcqbwdc_mapping_table.on("click", "tbody td.controls .edit", function (e) {
    e.preventDefault();

    var $tr = $(this).closest("tr");

    $tr.addClass("editing");

    wrcqbwdc_prepopulate_form_with_entry_data($tr, $wrcqbwdc_field_controls, $wrcqbwdc_button_controls);

    $wrcqbwdc_mapping_table.find("tbody tr td.controls .dashicons").css("visibility", "hidden");
  });

  $wrcqbwdc_cancel_save_mapping.click(function (e) {
    e.preventDefault();

    wrcqbwdc_reset_form($wrcqbwdc_field_controls, $wrcqbwdc_button_controls, $wrcqbwdc_mapping_table);

    $wrcqbwdc_mapping_table
      .find("tbody tr")
      .removeClass("editing")
      .find("td.controls .dashicons")
      .css({ visibility: "visible" });
  });

  $wrcqbwdc_save_mapping.click(function (e) {
    e.preventDefault();

    var result = wrcqbwdc_construct_mapping_data(
      $wrcqbwdc_wholesale_roles.val(),
      $wrcqbwdc_wholesale_roles.find("option:selected").text(),
      $wrcqbwdc_starting_qty.val(),
      $wrcqbwdc_ending_qty.val(),
      $wrcqbwdc_discount.val(),
      $wrcqbwdc_mapping_index.val()
    );

    if (result.status !== "success") {
      var err_msg = "";

      for (var i = 0; i < result.error.length; i++) err_msg += result.error[i] + "<br/>";

      toastr.error(err_msg, wwpp_wrcqbwd_params.i18n_form_error, {
        closeButton: true,
        showDuration: error_message_duration,
      });
    } else {
      wrcqbwdc_form_in_processing_mode($wrcqbwdc_field_controls, $wrcqbwdc_button_controls);

      $.ajax({
        url: ajaxurl,
        type: "post",
        data: {
          action: "wwpp_edit_wholesale_role_qty_based_discount_mapping",
          qty_based_discount_mapping: result.data,
          user_id: wwpp_wrcqbwd_params.user_id,
          nonce: wwpp_wrcqbwd_params.edit_wholesale_role_qty_based_discount_mapping_nonce,
        },
        dataType: "json",
      })
        .done(function (data) {
          if (data.status == "success") {
            wrcqbwdc_edit_mapping_table_entry(result.data.index, result.data, $wrcqbwdc_mapping_table);
            wrcqbwdc_reset_form($wrcqbwdc_field_controls, $wrcqbwdc_button_controls, $wrcqbwdc_mapping_table);
          } else {
            toastr.error(data.error_msg, wwpp_wrcqbwd_params.i18n_edit_mapping_error, {
              closeButton: true,
              showDuration: error_message_duration,
            });
            console.log(data);
          }
        })
        .fail(function (jqxhr) {
          toastr.error(wwpp_wrcqbwd_params.i18n_failed_edit_mapping, wwpp_wrcqbwd_params.i18n_edit_mapping_error, {
            closeButton: true,
            showDuration: error_message_duration,
          });
          console.log(jqxhr);
        })
        .always(function () {
          wrcqbwdc_form_in_normal_mode($wrcqbwdc_field_controls, $wrcqbwdc_button_controls);
        });
    }
  });

  /*
    |------------------------------------------------------------------------------------------------------------------
    | On Page Load
    |------------------------------------------------------------------------------------------------------------------
    */

  $wrcqbwdc_wholesale_roles.chosen({ allow_single_deselect: true });
});
