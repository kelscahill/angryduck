/* global jQuery */
jQuery(document).ready(function ($) {
  /*
     |------------------------------------------------------------------------------------------------------------------
     | Initialization
     |------------------------------------------------------------------------------------------------------------------
     */

  var $shipping_method_controls = $("#shipping-method-controls"),
    $wholesale_roles = $shipping_method_controls.find("#wholesale-roles"),
    $use_non_zoned_shipping_methods = $shipping_method_controls.find("#use-non-zoned-shipping-methods"),
    $non_zoned_method_controls = $shipping_method_controls.find(".non-zoned-method-controls"),
    $non_zoned_shipping_methods = $shipping_method_controls.find("#non-zoned-shipping-methods"),
    $zone_method_controls = $shipping_method_controls.find(".zone-method-controls"),
    $shipping_zones = $shipping_method_controls.find("#shipping-zones"),
    $shipping_zone_methods = $shipping_method_controls.find("#shipping-zone-methods"),
    $button_controls = $(".button-controls"),
    $add_mapping = $button_controls.find("#add-mapping"),
    $edit_mapping = $button_controls.find("#edit-mapping"),
    $cancel_edit_mapping = $button_controls.find("#cancel-edit-mapping"),
    $mapping_table = $("#wholesale-role-shipping-method-mapping"),
    errorMessageDuration = "10000",
    successMessageDuration = "5000";

  /*
     |------------------------------------------------------------------------------------------------------------------
     | Custom events
     |------------------------------------------------------------------------------------------------------------------
     */

  $shipping_method_controls.on("reset_fields", function (event) {
    event.stopPropagation();

    $wholesale_roles.val("");
    $use_non_zoned_shipping_methods.removeAttr("checked").trigger("change");
    $non_zoned_shipping_methods.val("").trigger("change");
    $shipping_zones.val("").trigger("change");
    $shipping_zone_methods.html(
      '<option value="">' + wp.i18n.__( '--Select Shipping Zone Method--', 'woocommerce-wholesale-prices-premium' ) + "</option>"
    );
    $shipping_zone_methods.trigger("change");

    return $(this);
  });

  $shipping_method_controls.on("prepopulate_fields", function (event, fields_data) {
    $shipping_method_controls.find("#index").val(fields_data["index"]);
    $wholesale_roles.val(fields_data["wholesale_role"]);

    if (fields_data["use_non_zoned_shipping_method"] == "yes") {
      $non_zoned_shipping_methods.val(fields_data["non_zoned_shipping_method"]).trigger("change");
      $use_non_zoned_shipping_methods.attr("checked", "checked").trigger("change");
    } else {
      $non_zoned_shipping_methods.val("").trigger("change");
      $use_non_zoned_shipping_methods.removeAttr("checked").trigger("change");
      $shipping_zones.val(fields_data["shipping_zone"]).trigger("change", [fields_data["shipping_method"]]);
    }
  });

  $shipping_method_controls.on("construct_mapping_data", function (event, mapping) {
    mapping["wholesale_role"] = $.trim($wholesale_roles.val());
    mapping["wholesale_role_text"] = $.trim($wholesale_roles.find("option:selected").text());

    if ($use_non_zoned_shipping_methods.is(":checked")) {
      mapping["use_non_zoned_shipping_method"] = "yes";
      mapping["non_zoned_shipping_method"] = $.trim($non_zoned_shipping_methods.val());
      mapping["non_zoned_shipping_method_text"] = $.trim($non_zoned_shipping_methods.find("option:selected").text());
    } else {
      mapping["use_non_zoned_shipping_method"] = "no";

      mapping["shipping_zone"] = $.trim($shipping_zones.val());
      mapping["shipping_zone_text"] = $.trim($shipping_zones.find("option:selected").text());
      mapping["shipping_method"] = $.trim($shipping_zone_methods.val());
      mapping["shipping_method_text"] = $.trim($shipping_zone_methods.find("option:selected").text());
    }
  });

  $button_controls.on("add_mode", function (event) {
    event.stopPropagation();

    var $this = $(this);

    $this.removeClass("edit-mode");
    $this.addClass("add-mode");

    return $this;
  });

  $button_controls.on("edit_mode", function (event) {
    event.stopPropagation();

    var $this = $(this);

    $this.removeClass("add-mode");
    $this.addClass("edit-mode");

    return $this;
  });

  /*
     |------------------------------------------------------------------------------------------------------------------
     | Events
     |------------------------------------------------------------------------------------------------------------------
     */

  $use_non_zoned_shipping_methods.change(function () {
    var $this = $(this);

    if ($this.is(":checked")) {
      $zone_method_controls.slideUp("fast", function () {
        $non_zoned_method_controls.slideDown("fast");
      });
    } else {
      $non_zoned_method_controls.slideUp("fast", function () {
        $zone_method_controls.slideDown("fast");
      });
    }
  });

  $shipping_zones.on("change", function (event, selected_method_instance_id) {
    var $this = $(this),
      zone_id = $this.val();

    if (zone_id) {
      $this.attr("disabled", "disabled");
      $shipping_zone_methods.attr("disabled", "disabled");
      $button_controls.find(".button").attr("disabled", "disabled");
      $(".woocommerce-save-button").attr("disabled", "disabled");

      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwpp_get_zone_shipping_methods",
          zone_id: zone_id,
          nonce: wwpp_shipping_controls_custom_field_params.wwpp_get_zone_shipping_methods_nonce,
        },
        dataType: "json",
      })
        .done(function (data, text_status, jqxhr) {
          if (data.status == "success") {
            var shipping_method_options =
              '<option value="">' +
              wp.i18n.__( '--Select Shipping Zone Method--', 'woocommerce-wholesale-prices-premium' ) +
              "</option>";

            for (var method_instance_id in data.shipping_methods) {
              if (data.shipping_methods.hasOwnProperty(method_instance_id)) {
                var selected =
                  selected_method_instance_id && method_instance_id == selected_method_instance_id
                    ? 'selected="selected"'
                    : "";
                shipping_method_options +=
                  '<option value="' +
                  method_instance_id +
                  '" ' +
                  selected +
                  ">" +
                  data.shipping_methods[method_instance_id] +
                  "</option>";
              }
            }

            $shipping_zone_methods.html(shipping_method_options);
            $shipping_zone_methods.trigger("change");
          } else {
            console.log(data);
            toastr.error(
              data.error_message,
              wp.i18n.__( 'Failed to retrieve shipping zone methods', 'woocommerce-wholesale-prices-premium' ),
              { closeButton: true, showDuration: errorMessageDuration }
            );
          }
        })
        .fail(function (jqxhr, text_status, error_thrown) {
          console.log(jqxhr);
          toastr.error(
            "",
            wp.i18n.__( 'Failed to retrieve shipping zone methods', 'woocommerce-wholesale-prices-premium' ),
            { closeButton: true, showDuration: errorMessageDuration }
          );
        })
        .always(function () {
          $this.removeAttr("disabled");
          $shipping_zone_methods.removeAttr("disabled");
          $button_controls.find(".button").removeAttr("disabled");
          $(".woocommerce-save-button").removeAttr("disabled");
        });
    }
  });

  $add_mapping.click(function () {
    var $this = $(this),
      mapping = {},
      errors = [];

    $shipping_method_controls.trigger("construct_mapping_data", [mapping]);

    $button_controls.find(".button").attr("disabled", "disabled");
    $button_controls.find(".spinner").css("visibility", "visible");
    $(".woocommerce-save-button").attr("disabled", "disabled");

    if (mapping["wholesale_role"] == "")
      errors.push(wp.i18n.__( 'Empty wholesale role', 'woocommerce-wholesale-prices-premium' ) + "<br/>");

    if (mapping["use_non_zoned_shipping_method"] == "yes") {
      if (mapping["non_zoned_shipping_method"] == "")
        errors.push(wp.i18n.__( 'Empty non-zoned shipping method', 'woocommerce-wholesale-prices-premium' ) + "<br/>");
    } else {
      if (mapping["shipping_zone"] == "")
        errors.push(wp.i18n.__( 'Empty shipping zone', 'woocommerce-wholesale-prices-premium' ) + "<br/>");

      if (mapping["shipping_method"] == "")
        errors.push(wp.i18n.__( 'Empty shipping method', 'woocommerce-wholesale-prices-premium' ) + "<br/>");
    }

    if (errors.length > 0) {
      var err_msg = "";

      for (var i = 0; i < errors.length; i++) err_msg += errors[i];

      toastr.error(err_msg, wp.i18n.__( 'Please fill the form properly', 'woocommerce-wholesale-prices-premium' ), {
        closeButton: true,
        showDuration: errorMessageDuration,
      });

      $button_controls.find(".button").removeAttr("disabled");
      $button_controls.find(".spinner").css("visibility", "hidden");
      $(".woocommerce-save-button").removeAttr("disabled");
    } else {
      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwpp_add_wholesale_zone_mapping",
          mapping: mapping,
          nonce: wwpp_shipping_controls_custom_field_params.wwpp_add_wholesale_zone_mapping_nonce,
        },
        dataType: "json",
      })
        .done(function (data, text_status, jqxhr) {
          if (data.status == "success") {
            if ($mapping_table.find("tr.no-items").length > 0) $mapping_table.find("tr.no-items").remove();

            // Shipping Method Meta
            var shipping_method_meta = "";

            if (mapping["use_non_zoned_shipping_method"] == "yes") {
              shipping_method_meta =
                '<span class="non-zoned-shipping-method">' + mapping["non_zoned_shipping_method"] + "</span>";
            } else {
              shipping_method_meta =
                '<span class="shipping-zone">' +
                mapping["shipping_zone"] +
                "</span>" +
                '<span class="shipping-method">' +
                mapping["shipping_method"] +
                "</span>";
            }

            // Shipping Method Text
            var shipping_method_text = "";

            if (mapping["use_non_zoned_shipping_method"] == "yes") {
              shipping_method_text =
                '<td class="shipping-zone-text"></td>' +
                '<td class="shipping-method-text">' +
                mapping["non_zoned_shipping_method_text"] +
                "</td>";
            } else {
              shipping_method_text =
                '<td class="shipping-zone-text">' +
                mapping["shipping_zone_text"] +
                "</td>" +
                '<td class="shipping-method-text">' +
                mapping["shipping_method_text"] +
                "</td>";
            }

            var tr =
              "<tr>" +
              '<td class="meta hidden">' +
              '<span class="index">' +
              data.mapping_index +
              "</span>" +
              '<span class="wholesale-role">' +
              mapping["wholesale_role"] +
              "</span>" +
              '<span class="use-non-zoned-shipping-method">' +
              mapping["use_non_zoned_shipping_method"] +
              "</span>" +
              shipping_method_meta +
              "</td>" +
              '<td class="wholesale-role-text">' +
              mapping["wholesale_role_text"] +
              "</td>" +
              shipping_method_text +
              '<td class="non-zoned-method-text">' +
              mapping["use_non_zoned_shipping_method"] +
              "</td>" +
              '<td class="controls">' +
              '<span class="dashicons dashicons-edit edit-mapping"></span>' +
              '<span class="dashicons dashicons-no delete-mapping"></span>' +
              "</td>" +
              "</tr>";

            $mapping_table.find("tbody").append(tr);

            toastr.success("", wp.i18n.__( 'Successfully added new wholesale / shipping mapping', 'woocommerce-wholesale-prices-premium' ), {
              closeButton: true,
              showDuration: successMessageDuration,
            });
          } else {
            console.log(data);
            toastr.error(
              data.error_message,
              wp.i18n.__( 'Failed to add new wholesale / shipping mapping', 'woocommerce-wholesale-prices-premium' ),
              { closeButton: true, showDuration: errorMessageDuration }
            );
          }
        })
        .fail(function (jqxhr, text_status, error_thrown) {
          console.log(jqxhr);
          toastr.error("", wp.i18n.__( 'Failed to add new wholesale / shipping mapping', 'woocommerce-wholesale-prices-premium' ), {
            closeButton: true,
            showDuration: errorMessageDuration,
          });
        })
        .always(function () {
          $button_controls.find(".button").removeAttr("disabled");
          $button_controls.find(".spinner").css("visibility", "hidden");
          $(".woocommerce-save-button").removeAttr("disabled");
        });
    }
  });

  $mapping_table.on("click", ".edit-mapping", function () {
    var $this = $(this),
      $tr = $this.closest("tr"),
      fields_data = {};

    $mapping_table.find("tbody tr .dashicons").css("visibility", "hidden");
    $tr.addClass("processing");

    fields_data["index"] = $tr.find(".meta .index").text();
    fields_data["wholesale_role"] = $tr.find(".meta .wholesale-role").text();
    fields_data["use_non_zoned_shipping_method"] = $tr.find(".meta .use-non-zoned-shipping-method").text();

    if (fields_data["use_non_zoned_shipping_method"] == "yes") {
      fields_data["non_zoned_shipping_method"] = $tr.find(".meta .non-zoned-shipping-method").text();
    } else {
      fields_data["shipping_zone"] = $tr.find(".meta .shipping-zone").text();
      fields_data["shipping_method"] = $tr.find(".meta .shipping-method").text();
    }

    $shipping_method_controls.trigger("prepopulate_fields", [fields_data]);
    $button_controls.trigger("edit_mode");
  });

  $cancel_edit_mapping.click(function () {
    $shipping_method_controls.trigger("reset_fields");
    $button_controls.trigger("add_mode");
    $mapping_table.find("tr").removeClass("processing").end().find(".dashicons").css("visibility", "visible");
  });

  $edit_mapping.click(function () {
    var $this = $(this),
      index = $shipping_method_controls.find("#index").val(),
      mapping = {},
      errors = [];

    $shipping_method_controls.trigger("construct_mapping_data", [mapping]);

    $button_controls.find(".button").attr("disabled", "disabled");
    $button_controls.find(".spinner").css("visibility", "visible");
    $(".woocommerce-save-button").attr("disabled", "disabled");

    if (mapping["wholesale_role"] == "")
      errors.push(wp.i18n.__( 'Empty wholesale role', 'woocommerce-wholesale-prices-premium' ) + "<br/>");

    if (mapping["use_non_zoned_shipping_method"] == "yes") {
      if (mapping["non_zoned_shipping_method"] == "")
        errors.push(wp.i18n.__( 'Empty non-zoned shipping method', 'woocommerce-wholesale-prices-premium' ) + "<br/>");
    } else {
      if (mapping["shipping_zone"] == "")
        errors.push(wp.i18n.__( 'Empty shipping zone', 'woocommerce-wholesale-prices-premium' ) + "<br/>");

      if (mapping["shipping_method"] == "")
        errors.push(wp.i18n.__( 'Empty shipping method', 'woocommerce-wholesale-prices-premium' ) + "<br/>");
    }

    if (errors.length > 0) {
      var err_msg = "";

      for (var i = 0; i < errors.length; i++) err_msg += errors[i];

      toastr.error(err_msg, wp.i18n.__( 'Please fill the form properly', 'woocommerce-wholesale-prices-premium' ), {
        closeButton: true,
        showDuration: errorMessageDuration,
      });

      $button_controls.find(".button").removeAttr("disabled");
      $button_controls.find(".spinner").css("visibility", "hidden");
      $(".woocommerce-save-button").removeAttr("disabled");
    } else {
      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwpp_edit_wholesale_zone_mapping",
          index: index,
          mapping: mapping,
          nonce: wwpp_shipping_controls_custom_field_params.wwpp_edit_wholesale_zone_mapping_nonce,
        },
        dataType: "json",
      })
        .done(function (data, text_status, jqxhr) {
          if (data.status == "success") {
            var $tr = $mapping_table.find('tr .meta .index:contains("' + index + '")').closest("tr");

            // Shipping Method Meta
            var shipping_method_meta = "";

            if (mapping["use_non_zoned_shipping_method"] == "yes") {
              shipping_method_meta =
                '<span class="non-zoned-shipping-method">' + mapping["non_zoned_shipping_method"] + "</span>";
            } else {
              shipping_method_meta =
                '<span class="shipping-zone">' +
                mapping["shipping_zone"] +
                "</span>" +
                '<span class="shipping-method">' +
                mapping["shipping_method"] +
                "</span>";
            }

            // Shipping Method Text
            var shipping_method_text = "";

            if (mapping["use_non_zoned_shipping_method"] == "yes") {
              shipping_method_text =
                '<td class="shipping-zone-text"></td>' +
                '<td class="shipping-method-text">' +
                mapping["non_zoned_shipping_method_text"] +
                "</td>";
            } else {
              shipping_method_text =
                '<td class="shipping-zone-text">' +
                mapping["shipping_zone_text"] +
                "</td>" +
                '<td class="shipping-method-text">' +
                mapping["shipping_method_text"] +
                "</td>";
            }

            $tr.html(
              '<td class="meta hidden">' +
                '<span class="index">' +
                index +
                "</span>" +
                '<span class="wholesale-role">' +
                mapping["wholesale_role"] +
                "</span>" +
                '<span class="use-non-zoned-shipping-method">' +
                mapping["use_non_zoned_shipping_method"] +
                "</span>" +
                shipping_method_meta +
                "</td>" +
                '<td class="wholesale-role-text">' +
                mapping["wholesale_role_text"] +
                "</td>" +
                shipping_method_text +
                '<td class="non-zoned-method-text">' +
                mapping["use_non_zoned_shipping_method"] +
                "</td>" +
                '<td class="controls">' +
                '<span class="dashicons dashicons-edit edit-mapping"></span>' +
                '<span class="dashicons dashicons-no delete-mapping"></span>' +
                "</td>"
            );

            $shipping_method_controls.trigger("reset_fields");
            $button_controls.trigger("add_mode");
            $mapping_table.find("tr").removeClass("processing").end().find(".dashicons").css("visibility", "visible");

            toastr.success("", wp.i18n.__( 'Successfully edited wholesale / shipping mapping', 'woocommerce-wholesale-prices-premium' ), {
              closeButton: true,
              showDuration: successMessageDuration,
            });
          } else {
            console.log(data);
            toastr.error(
              data.error_message,
              wp.i18n.__( 'Failed to edit wholesale / shipping mapping', 'woocommerce-wholesale-prices-premium' ),
              { closeButton: true, showDuration: errorMessageDuration }
            );
          }
        })
        .fail(function (jqxhr, text_status, error_thrown) {
          console.log(jqxhr);
          toastr.error("", wp.i18n.__( 'Failed to edit wholesale / shipping mapping', 'woocommerce-wholesale-prices-premium' ), {
            closeButton: true,
            showDuration: errorMessageDuration,
          });
        })
        .always(function () {
          $button_controls.find(".button").removeAttr("disabled");
          $button_controls.find(".spinner").css("visibility", "hidden");
          $(".woocommerce-save-button").removeAttr("disabled");
        });
    }
  });

  $mapping_table.on("click", ".delete-mapping", function () {
    var $this = $(this),
      $tr = $this.closest("tr"),
      index = $.trim($tr.find(".meta .index").text());

    $mapping_table.find("tbody tr .dashicons").css("visibility", "hidden");
    $tr.addClass("processing");
    $button_controls.find(".button").attr("disabled", "disabled");
    $(".woocommerce-save-button").attr("disabled", "disabled");

    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: {
        action: "wwpp_delete_wholesale_zone_mapping",
        index: index,
        nonce: wwpp_shipping_controls_custom_field_params.wwpp_delete_wholesale_zone_mapping_nonce,
      },
      dataType: "json",
    })
      .done(function (data, text_status, jqxhr) {
        if (data.status == "success") {
          $mapping_table
            .find('tbody tr .meta .index:contains("' + index + '")')
            .closest("tr")
            .remove();

          if ($mapping_table.find("tbody tr").length <= 0)
            $mapping_table
              .find("tbody")
              .append(
                '<tr class="no-items">' +
                  '<td class="colspanchange" colspan="5">' +
                  wp.i18n.__( 'No Mappings Found', 'woocommerce-wholesale-prices-premium' ) +
                  "</td>" +
                  "</tr>"
              );

          toastr.success("", wp.i18n.__( 'Successfully deleted wholesale / shipping zone mapping', 'woocommerce-wholesale-prices-premium' ), {
            closeButton: true,
            showDuration: successMessageDuration,
          });
        } else {
          console.log(data);
          toastr.error(
            data.error_message,
            wp.i18n.__( 'Failed to delete wholesale / shipping zone mapping', 'woocommerce-wholesale-prices-premium' ),
            { closeButton: true, showDuration: errorMessageDuration }
          );
        }
      })
      .fail(function (jqxhr, text_status, error_thrown) {
        console.log(jqxhr);
        toastr.error(data.error_message, wp.i18n.__( 'Failed to delete wholesale / shipping zone mapping', 'woocommerce-wholesale-prices-premium' ), {
          closeButton: true,
          showDuration: errorMessageDuration,
        });
      })
      .always(function () {
        $mapping_table.find("tbody tr .dashicons").css("visibility", "visible");
        $mapping_table.find("tbody tr").removeClass("processing");
        $button_controls.find(".button").removeAttr("disabled");
        $(".woocommerce-save-button").removeAttr("disabled");
      });
  });
});
