jQuery("document").ready(function ($) {
  /*
     |--------------------------------------------------------------------------
     | Variable Declarations
     |--------------------------------------------------------------------------
     */

  var $toggle_feature = $("#enable-per-order-quantity-wholesale-percent-discount-cat-level"),
    $toggle_mode2 = $("#enable-per-order-quantity-wholesale-percent-discount-cat-level-mode-2"),
    $feature_body = $("#per-order-quantity-wholesale-percent-discount-cat-level-controls-body"),
    $feature_controls = $("#per-order-quantity-wholesale-percent-discount-cat-level-controls"),
    term_id = $.trim(
      $("#per-order-quantity-wholesale-percent-discount-cat-level-controls-header .meta .term-id").text()
    ),
    $mapping_table = $("#per-order-quantity-wholesale-percent-discount-cat-level-mapping-table"),
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
     |--------------------------------------------------------------------------
     | Enable/Disable Feature
     |--------------------------------------------------------------------------
     */

  function toggle_feature() {
    $toggle_feature
      .attr("disabled", "disabled")
      .css("display", "none")
      .siblings(".spinner")
      .css("display", "inline-block");

    var enable = $toggle_feature.is(":checked") ? "yes" : "no",
      ajax_options = {
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwpp_toggle_cat_level_quantity_based_wholesale_discount_fields",
          term_id: term_id,
          enable: enable,
          nonce: $("input[name=wwpp_toggle_cat_level_quantity_based_wholesale_discount_fields]").val(),
        },
        dataType: "json",
      };

    if ($toggle_feature.is(":checked")) {
      $.ajax(ajax_options)
        .done(function (data, text_status, jqxhr) {
          if (data.status == "success") $feature_body.slideDown();
          else {
            console.log(data);
            toastr.error(data.error_message, poqwdcl_params.i18n_failed_enable_feature, {
              closeButton: true,
              showDuration: error_message_duration,
            });
          }
        })
        .fail(function (jqxhr, text_status, error_thrown) {
          console.log(jqxhr);
          toastr.error("", poqwdcl_params.i18n_failed_enable_feature, {
            closeButton: true,
            showDuration: error_message_duration,
          });
        })
        .always(function () {
          $toggle_feature
            .removeAttr("disabled")
            .css("display", "inline-block")
            .siblings(".spinner")
            .css("display", "none");
        });
    } else {
      $.ajax(ajax_options)
        .done(function (data, text_status, jqxhr) {
          if (data.status == "success") $feature_body.slideUp();
          else {
            console.log(data);
            toastr.error(data.error_message, poqwdcl_params.i18n_failed_disable_feature, {
              closeButton: true,
              showDuration: error_message_duration,
            });
          }
        })
        .fail(function (jqxhr, text_status, error_thrown) {
          console.log(data);
          toastr.error(data.error_message, poqwdcl_params.i18n_failed_disable_feature, {
            closeButton: true,
            showDuration: error_message_duration,
          });
        })
        .always(function () {
          $toggle_feature
            .removeAttr("disabled")
            .css("display", "inline-block")
            .siblings(".spinner")
            .css("display", "none");
        });
    }
  }

  $toggle_feature.click(function () {
    toggle_feature();
  });

  if ($toggle_feature.is(":checked")) $feature_body.css("display", "block");
  else $feature_body.css("display", "none");

  $toggle_mode2.click(function () {
    var $this = $(this);

    $this
      .siblings(".spinner")
      .css("display", "inline-block")
      .end()
      .css("display", "none")
      .closest("label")
      .attr("disabled", "disabled");

    var enable = $("#enable-per-order-quantity-wholesale-percent-discount-cat-level-mode-2").is(":checked")
      ? "yes"
      : "no";

    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: {
        action: "wwpp_toggle_cat_level_quantity_based_wholesale_discount_mode2",
        term_id: term_id,
        enable: enable,
        nonce: $("input[name=wwpp_toggle_cat_level_quantity_based_wholesale_discount_mode2]").val(),
      },
      dataType: "json",
    })
      .done(function (data) {
        if (data.status !== "success") {
          toastr.error(data.error_msg, poqwdcl_params.i18n_failed_enable_mode_2, {
            closeButton: true,
            showDuration: error_message_duration,
          });
          console.log(data);
        }
      })
      .fail(function (jqxhr) {
        toastr.error("", poqwdcl_params.i18n_failed_enable_mode_2, {
          closeButton: true,
          showDuration: error_message_duration,
        });
        console.log(jqxhr);
      })
      .always(function () {
        $this
          .siblings(".spinner")
          .css("display", "none")
          .end()
          .css("display", "inline-block")
          .closest("label")
          .removeAttr("disabled");
      });
  });

  /*
     |--------------------------------------------------------------------------
     | Add Mapping Entry
     |--------------------------------------------------------------------------
     */

  $feature_controls.on("reset_fields", function (event) {
    event.stopPropagation();

    $feature_controls.find(".meta .index").text("");
    $feature_controls.find("#wholesale-role option:first-child").attr("selected", "selected");
    $feature_controls.find("#starting-qty").val("");
    $feature_controls.find("#ending-qty").val("");
    $feature_controls.find("#wholesale-discount").val("");

    return $(this);
  });

  $feature_controls.on("processing_mode", function (event) {
    event.stopPropagation();

    var $this = $(this);

    $feature_controls
      .find("input,select,textarea")
      .attr("disabled", "disabled")
      .end()
      .find(".spinner")
      .css("display", "inline-block");

    return $this;
  });

  $feature_controls.on("normal_mode", function (event) {
    event.stopPropagation();

    $feature_controls
      .find("input,select,textarea")
      .removeAttr("disabled")
      .end()
      .find(".spinner")
      .css("display", "none");

    return $(this);
  });

  $feature_controls.on("construct_data", function (event, type, data, errors) {
    event.stopPropagation();

    data["wholesale-role"] = $.trim($feature_controls.find("#wholesale-role").val());
    data["start-qty"] = $.trim($feature_controls.find("#starting-qty").val());
    data["end-qty"] = $.trim($feature_controls.find("#ending-qty").val());
    data["end-qty"] = data["end-qty"] != "" ? parseInt(data["end-qty"], 10) : data["end-qty"];
    data["wholesale-discount"] = $.trim($feature_controls.find("#wholesale-discount").val());

    if (data["wholesale-role"] == "") errors.err_msg.push(poqwdcl_params.i18n_please_specify_wholesale_role);

    if (data["start-qty"] == "") errors.err_msg.push(poqwdcl_params.i18n_please_specify_start_qty);
    else if (data["start-qty"] <= 0 || data["start-qty"] == "" || isNaN(data["start-qty"]))
      errors.err_msg.push(poqwdcl_params.i18n_invalid_start_qty);

    if (data["end-qty"] === 0 || isNaN(data["end-qty"]) || data["end-qty"] < 0)
      errors.err_msg.push(poqwdcl_params.i18n_invalid_end_qty);

    if (data["wholesale-discount"] <= 0 || data["wholesale-discount"] == "")
      errors.err_msg.push(poqwdcl_params.i18n_invalid_percent_discount);

    if (type == "edit") {
      data["index"] = $.trim($feature_controls.find(".meta .index").text());

      if (data["index"] == "") errors.err_msg.push(poqwdcl_params.i18n_please_specify_index_of_entry_to_edit);
    }

    return $(this);
  });

  $mapping_table.on("add_new_entry", function (event, data, returned_data) {
    event.stopPropagation();

    if ($mapping_table.find("tbody tr.no-items").length > 0) $mapping_table.find("tbody").html("");

    var tr =
      "<tr>" +
      '<td class="meta hidden">' +
      '<span class="index">' +
      returned_data.index +
      "</span>" +
      '<span class="wholesale-role">' +
      data["wholesale-role"] +
      "</span>" +
      '<span class="wholesale-discount">' +
      data["wholesale-discount"] +
      "</span>" +
      "</td>" +
      '<td class="wholesale-role-text">' +
      $.trim($feature_controls.find('#wholesale-role option[value="' + data["wholesale-role"] + '"]').text()) +
      "</td>" +
      '<td class="start-qty">' +
      data["start-qty"] +
      "</td>" +
      '<td class="end-qty">' +
      data["end-qty"] +
      "</td>" +
      '<td class="wholesale-discount-text">' +
      data["wholesale-discount"] +
      "%</td>" +
      '<td class="controls">' +
      '<a class="edit dashicons dashicons-edit"></a> ' +
      '<a class="delete dashicons dashicons-no"></a>' +
      "</td>" +
      "</tr>";

    $mapping_table.find("tbody").append(tr);

    return $(this);
  });

  $feature_controls.find("#add-quantity-discount-rule").click(function () {
    var data = {},
      errors = { err_msg: [] };

    $feature_controls.trigger("processing_mode").trigger("construct_data", ["add", data, errors]);

    if (errors.err_msg.length > 0) {
      var err_msg = "";

      for (var i = 0; i < errors.err_msg.length; i++) err_msg += errors.err_msg[i] + "<br>";

      toastr.error(err_msg, poqwdcl_params.i18n_please_fill_form_properly, {
        closeButton: true,
        showDuration: error_message_duration,
      });

      $feature_controls.trigger("normal_mode");
    } else {
      var wholesale_role_text = $.trim($feature_controls.find("#wholesale-role").find("option:selected").text());

      if ($("." + data["wholesale-role"] + "_wholesale_discount").val() === "")
        alert(
          wholesale_role_text +
            " do not have base wholesale discount. This mapping will not be applied unless base wholesale discount is provided for this wholesale customer"
        );

      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwpp_save_cat_level_quantity_based_wholesale_discount_entry",
          term_id: term_id,
          data: data,
          nonce: $("input[name=wwpp_save_cat_level_quantity_based_wholesale_discount_entry]").val(),
        },
        dataType: "json",
      })
        .done(function (returned_data, text_status, jqxhr) {
          if (returned_data.status == "success") {
            $mapping_table.trigger("add_new_entry", [data, returned_data]);
            $feature_controls.trigger("reset_fields");
            toastr.success("", poqwdcl_params.i18n_new_wholesale_discount_mapping_added, {
              closeButton: true,
              showDuration: success_message_duration,
            });
          } else {
            console.log(returned_data);
            toastr.error(
              returned_data.error_message,
              poqwdcl_params.i18n_failed_to_add_new_wholesale_discount_mapping,
              { closeButton: true, showDuration: error_message_duration }
            );
          }
        })
        .fail(function (jqxhr, text_status, error_message) {
          console.log(jqxhr);
          toastr.error("", poqwdcl_params.i18n_failed_to_add_new_wholesale_discount_mapping, {
            closeButton: true,
            showDuration: error_message_duration,
          });
        })
        .always(function () {
          $feature_controls.trigger("normal_mode");
        });
    }
  });

  /*
     |--------------------------------------------------------------------------
     | Edit Mapping Entry
     |--------------------------------------------------------------------------
     */

  $feature_controls.on("edit_mode", function (event) {
    event.stopPropagation();

    $feature_controls.find(".button-field-set").addClass("edit-mode");

    return $(this);
  });

  $feature_controls.on("add_mode", function (event) {
    event.stopPropagation();

    $feature_controls.find(".button-field-set").removeClass("edit-mode");

    return $(this);
  });

  $mapping_table.on("processing_mode", function (event, $tr) {
    event.stopPropagation();

    $mapping_table.find("tbody tr td.controls .dashicons").css("visibility", "hidden");

    if ($tr) $tr.addClass("processing-row");

    return $(this);
  });

  $mapping_table.on("normal_mode", function (event) {
    event.stopPropagation();

    $mapping_table
      .find("tbody tr td.controls .dashicons")
      .css("visibility", "visible")
      .end()
      .find("tbody tr")
      .removeClass("processing-row");

    return $(this);
  });

  $mapping_table.on("click", "tbody tr td.controls .edit", function (event) {
    event.stopPropagation();

    var $this = $(this),
      $tr = $this.closest("tr");

    $mapping_table.trigger("processing_mode", [$tr]);

    $feature_controls.find(".meta .index").text($tr.find(".meta .index").text());
    $feature_controls.find("#wholesale-role").val($tr.find(".meta .wholesale-role").text());
    $feature_controls.find("#starting-qty").val($tr.find(".start-qty").text());
    $feature_controls.find("#ending-qty").val($tr.find(".end-qty").text());
    $feature_controls.find("#wholesale-discount").val($tr.find(".meta .wholesale-discount").text());

    $feature_controls.trigger("edit_mode");

    return $this;
  });

  $feature_controls.find("#cancel-edit-quantity-discount-rule").click(function () {
    $feature_controls.trigger("reset_fields").trigger("add_mode");

    $mapping_table.trigger("normal_mode");
  });

  $mapping_table.on("edit_existing_entry", function (event, data, returned_data) {
    event.stopPropagation();

    var $tr = $mapping_table.find('tbody tr .meta .index:contains("' + returned_data.index + '")').closest("tr");

    $tr.find(".meta .wholesale-role").text(data["wholesale-role"]);
    $tr.find(".meta .wholesale-discount").text(data["wholesale-discount"]);
    $tr
      .find(".wholesale-role-text")
      .text($feature_controls.find('#wholesale-role option[value="' + data["wholesale-role"] + '"]').text());
    $tr.find(".start-qty").text(data["start-qty"]);
    $tr.find(".end-qty").text(data["end-qty"]);
    $tr.find(".wholesale-discount-text").text(data["wholesale-discount"] + "%");

    return $(this);
  });

  $feature_controls.find("#edit-quantity-discount-rule").click(function () {
    var data = {},
      errors = { err_msg: [] };

    $feature_controls.trigger("processing_mode").trigger("construct_data", ["edit", data, errors]);

    if (errors.err_msg.length > 0) {
      var err_msg = "";

      for (var i = 0; i < errors.err_msg.length; i++) err_msg += errors.err_msg[i] + "<br>";

      toastr.error(err_msg, poqwdcl_params.i18n_please_fill_form_properly, {
        closeButton: true,
        showDuration: error_message_duration,
      });

      $feature_controls.trigger("normal_mode");
    } else {
      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwpp_save_cat_level_quantity_based_wholesale_discount_entry",
          term_id: term_id,
          data: data,
          nonce: $("input[name=wwpp_save_cat_level_quantity_based_wholesale_discount_entry]").val(),
        },
        dataType: "json",
      })
        .done(function (returned_data, text_status, jqxhr) {
          if (returned_data.status == "success") {
            $mapping_table.trigger("edit_existing_entry", [data, returned_data]).trigger("normal_mode");

            $feature_controls.trigger("reset_fields").trigger("add_mode");

            toastr.success("", poqwdcl_params.i18n_wholesale_discount_mapping_edited, {
              closeButton: true,
              showDuration: success_message_duration,
            });
          } else {
            console.log(returned_data);
            toastr.error(returned_data.error_message, poqwdcl_params.i18n_failed_to_edit_wholesale_discount_mapping, {
              closeButton: true,
              showDuration: error_message_duration,
            });
          }
        })
        .fail(function (jqxhr, text_status, error_message) {
          console.log(jqxhr);
          toastr.error("", poqwdcl_params.i18n_failed_to_edit_wholesale_discount_mapping, {
            closeButton: true,
            showDuration: error_message_duration,
          });
        })
        .always(function () {
          $feature_controls.trigger("normal_mode");
        });
    }
  });

  /*
     |--------------------------------------------------------------------------
     | Delete Mapping Entry
     |--------------------------------------------------------------------------
     */

  $mapping_table.on("click", "tbody tr td.controls .delete", function (event) {
    event.stopPropagation();

    var $this = $(this),
      $tr = $this.closest("tr");

    $mapping_table.trigger("processing_mode", [$tr]);

    if (confirm(poqwdcl_params.i18n_confirm_remove_wholesale_discount_mapping)) {
      $feature_controls.trigger("processing_mode");

      var index = $tr.find(".meta .index").text();

      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwpp_delete_cat_level_quantity_based_wholesale_discount_entry",
          term_id: term_id,
          index: index,
          nonce: $("input[name=wwpp_delete_cat_level_quantity_based_wholesale_discount_entry]").val(),
        },
        dataType: "json",
      })
        .done(function (data, text_status, jqxhr) {
          if (data.status == "success") {
            $tr.remove();

            if ($mapping_table.find("tbody tr").length <= 0)
              $mapping_table
                .find("tbody")
                .append(
                  '<tr class="no-items">' +
                    '<td class="colspanchange" colspan="5">' +
                    poqwdcl_params.i18n_no_quantity_discount_rules_found +
                    "</td>" +
                    "</tr>"
                );

            toastr.success("", poqwdcl_params.i18n_wholesale_discount_mapping_deleted, {
              closeButton: true,
              showDuration: success_message_duration,
            });
          } else {
            console.log(data);
            toastr.error("", poqwdcl_params.i18n_failed_to_delete_wholesale_discount_mapping, {
              closeButton: true,
              showDuration: error_message_duration,
            });
          }
        })
        .fail(function (jqxhr, text_status, error_thrown) {
          console.log(jqxhr);
          toastr.error("", poqwdcl_params.i18n_failed_to_delete_wholesale_discount_mapping, {
            closeButton: true,
            showDuration: error_message_duration,
          });
        })
        .always(function () {
          $mapping_table.trigger("normal_mode");
          $feature_controls.trigger("normal_mode");
        });
    } else $mapping_table.trigger("normal_mode");
  });
});
