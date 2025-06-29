jQuery(document).ready(function ($) {
  window.wwlcRegistration = {
    /**
     * Initialize registration form.
     */
    initForm: function () {
      var $phoneField = $registration_form.find("input.wwlc_form_field.phone");
      phoneMask = $phoneField.length ? $phoneField.data("phonemask") : "";

      wwlcFormActions.resetForm($registration_form);
      $registration_form
        .find("select")
        .find("option:first")
        .attr("selected", "selected");

      // Phone field mask
      if (
        phoneMask &&
        phoneMask != "No format" &&
        typeof phoneMask !== "object"
      )
        $phoneField.mask(phoneMask);

      if ($registration_form.find("#wwlc_country").length > 0)
        $registration_form.find("#wwlc_country").select2();

      // Custom Field Select
      if (
        $registration_form.find(".select_wwlc_custom_field select").length > 0
      )
        $registration_form.find(".select_wwlc_custom_field select").select2();
    },

    /**
     * Get states event.
     */
    getStatesEvent: function () {
      $registration_form.on("change", "#wwlc_country", function () {
        var $country = $(this),
          cc = $country.val();

        if (cc != "") {
          wwlcFrontEndAjaxServices
            .getStates(cc)
            .done(function (data, textStatus, jqXHR) {
              if (data.status == "success") {
                wwlcFormActions.displayStatesDropdownField(
                  $registration_form,
                  data.states,
                  "",
                  $country.data("required")
                );
                $registration_form.find("select#wwlc_state").select2();
              } else {
                wwlcFormActions.displayStatesTextField($registration_form);
              }
            })
            .fail(function (jqXHR, textStatus, errorThrown) {
              console.log(jqXHR.responseText);
              console.log(textStatus);
              console.log(errorThrown);
              console.log("----------");
            });
        }
      });
    },

    /**
     * Register form submit event.
     */
    onSubmitEvent: function () {
      $registration_form.on("submit", "form#registration_form", function (e) {
        e.preventDefault();

        var $fields = $(this).find(".wwlc_form_field"),
          formFields = [],
          n;

        if ($registration_form.find("#honeypot-field").val() != "") {
          window.location = RegistrationVars.registrationThankYouPage;
          return false;
        }

        // remove inline errors
        $registration_form.find(".inline-error").remove();
        $form_error.html("");

        wwlcFormActions.deactivateFormControls($registration_form);

        // validate form fields
        for (n = 0; n < $fields.length; n++) formFields.push($($fields[n]));

        // trim field values.
        wwlcFormValidator.trimFieldValues(formFields);

        // validate form fields.
        if (
          !wwlcFormValidator.validateRequiredField(
            formFields,
            RegistrationVars
          ) ||
          !wwlcFormValidator.confirmPassword() ||
          !wwlcFormValidator.validatePasswordStrength()
        ) {
          wwlcRegistration.displayError(
            RegistrationVars.fill_form_appropriately_message
          );
          wwlcFormActions.activateFormControls($registration_form);

          if (RegistrationVars.wwlc_captcha_type === "v2_invisible") {
            grecaptcha.reset();
          }

          return false;
        }

        // generate user data from formFields
        var userData = wwlcFormValidator.constructUserData(formFields);

        // show loader
        $button_container.find(".wwlc-loader").show();

        wwlcRegistration.createUser(userData);
      });
    },

    /**
     * Handles creating user.
     */
    createUser: function (userData) {
      wwlcFrontEndAjaxServices
        .createUser(
          userData,
          $("#wwlc-registration-form").find("#g-recaptcha-response").val(),
          $("#wwlc_register_user_nonce_field").val(),
          $('input[name="_wp_http_referer"]').val()
        )
        .done(function (data, textStatus, jqXHR) {
          if (data.status == "success") {
            wwlcFormActions.resetForm($registration_form);

            var url = $registration_form.data("redirect")
                ? $registration_form.data("redirect")
                : RegistrationVars.registrationThankYouPage,
              form;

            if (data.hasOwnProperty("redirect")) url = data.redirect;

            // return current url if shortcode is set to current_page
            url = url == "current_page" ? window.location : url;

            // purpose is to display success message
            form = $(
              '<form action="' +
                url +
                '" method="post">' +
                '<input type="hidden" name="inline_message" value="' +
                data.success_message +
                '" />' +
                "</form>"
            );
            $("body").append(form);
            form.submit();

            $button_container.find(".wwlc-loader").hide();

            return false;
          } else {
            $button_container.find(".wwlc-loader").hide();

            if (data && data.error_message)
              wwlcRegistration.displayError(data.error_message);
            else
              wwlcRegistration.displayError(
                RegistrationVars.failed_registration_process_message
              );

            if (data && data.error_obj.errors.existing_user_login) {
              $registration_form
                .find("#wwlc_username")
                .addClass("err")
                .closest(".field-set")
                .append(
                  '<span class="inline-error">' + data.error_message + "</span>"
                );
            }
            if (data && data.error_obj.errors.existing_user_email) {
              $registration_form
                .find("#user_email")
                .addClass("err")
                .closest(".field-set")
                .append(
                  '<span class="inline-error">' + data.error_message + "</span>"
                );
            }

            if (
              $("#wwlc-registration-form").find(
                'textarea[name="g-recaptcha-response"]'
              ).length > 0
            )
              grecaptcha.reset();

            console.log(RegistrationVars.registration_failed_message);
            console.log(data.error_message);
            console.log(data);
            console.log("----------");
          }

          wwlcFormActions.activateFormControls($registration_form);
        })
        .fail(function (jqXHR, textStatus, errorThrown) {
          // wwlcRegistration.displayError( jqXHR.responseText );

          console.log(RegistrationVars.settings_save_failed_message);
          console.log(jqXHR);
          console.log(textStatus);
          console.log(errorThrown);
          console.log("----------");

          wwlcFormActions.activateFormControls($registration_form);
          $registration_form.find(".wwlc-loader").hide();
        });
    },

    /**
     * Upload file event.
     */
    uploadFieldEvent: function () {
      // File upload script
      $(".wwlc-file-upload-form input[type='file']").on("change", function () {
        var fileField = $(this);
        var fieldID = fileField
          .closest(".wwlc-file-upload-form")
          .find('input[type="hidden"]')
          .prop("id");
        var selectedFile = fileField[0].files[0];
        var selectedFileType = selectedFile.name.split(".").pop();
        var form = fileField.closest(".wwlc-file-upload-form");

        // remove any inline errors displayed for the field
        fileField.closest(".field-set").find(".inline-error").remove();

        // Disable input, show spinner and save file name to placeholder
        fileField.prop("disabled", true);
        form.find(".wwlc-loader").css("height", 24).show();

        wwlcFrontEndAjaxServices
          .getAllowedFileSettings(
            fieldID,
            $("#wwlc_register_user_nonce_field").val()
          )
          .done(function (response) {
            // Hide notice
            form.closest(".field-set").find(".inline-error").remove();

            if (
              $.inArray(selectedFileType, response["allowed_file_types"]) < 0
            ) {
              fileField
                .closest(".field-set")
                .append(
                  '<div class="inline-error">' +
                    RegistrationVars.file_format_not_supported +
                    "</div>"
                );
            } else if (selectedFile.size <= 0) {
              fileField
                .closest(".field-set")
                .append(
                  '<div class="inline-error">' +
                    RegistrationVars.file_size_is_empty +
                    "</div>"
                );
            } else if (selectedFile.size > response["max_allowed_file_size"]) {
              fileField
                .closest(".field-set")
                .append(
                  '<div class="inline-error">' +
                    RegistrationVars.file_size_exceeds_max_allowed +
                    "</div>"
                );
            } else {
              var fileData = new FormData();

              form.find(".placeholder").text(selectedFile.name);
              form
                .closest("#wwlc-registration-form")
                .find("input#register")
                .prop("disabled", true);

              fileData.append("action", "wwlc_file_upload_handler");
              fileData.append("uploaded_file", selectedFile);
              fileData.append("file_settings", JSON.stringify(response));

              // Handles the file uploading
              wwlcFrontEndAjaxServices
                .uploadFile(fileData)
                .done(function (data) {
                  if (data.status === "success") {
                    // save file name to hidden input
                    form.find('input[type="hidden"]').val(data.file_name);

                    // form.find(".placeholder").show();
                    form
                      .closest("#wwlc-registration-form")
                      .find("input#register")
                      .prop("disabled", false);

                    form.closest(".field-set").find(".inline-error").remove();
                  } else {
                    // Handle errors here
                    form.closest(".field-set").find(".inline-error").remove();
                    form
                      .closest(".field-set")
                      .append(
                        '<span class="inline-error">' + data.message + "</span>"
                      );

                    // reset file input field
                    var inputFile = form.find('input[type="file"]');
                    inputFile.replaceWith(
                      inputFile.val("").prop("disabled", false).clone(true)
                    );
                    form.find(".wwlc-loader").hide();
                    form
                      .closest("#wwlc-registration-form")
                      .find("input#register")
                      .prop("disabled", false);
                  }
                })
                .fail(function (jqXHR, textStatus, errorThrown) {
                  console.log("ERRORS: " + textStatus);
                });
            }

            // hide file input and loader, and show the filename placeholder
            form.find(".wwlc-loader").hide();
            fileField.prop("disabled", false);
          });
      });
    },

    /**
     * Display error
     */
    displayError: function (error_msg) {
      $form_error.append(
        '<div class="woocommerce-error">' + error_msg + "</div>"
      );
      $("html,body").animate(
        {
          scrollTop: $registration_form.offset().top,
        },
        300
      );
    },

    termsConditionsEvent: function () {
      $registration_form
        .find(".terms_conditions_wwlc_custom_field .show-hide")
        .on("click", function (event) {
          event.preventDefault();
          var $checkbox = $(this).parent().find('input[type="checkbox"]'),
            $tc_content = $registration_form.find(
              "#" + $checkbox.attr("id") + "-content"
            ),
            toggle = !$tc_content.data("show");

          $tc_content.data("show", toggle);

          if ($tc_content.data("show")) {
            $tc_content.slideDown("fast");
          } else {
            $tc_content.slideUp("fast");
          }
        });
    },
    triggerSubmit: function () {
      $registration_form.find("form#registration_form").submit();
    },
  };

  var $registration_form = $("#wwlc-registration-form"),
    $form_control = $registration_form.find(".form-controls-section"),
    $button_container = $form_control.find(".register-button-container"),
    $form_error = $registration_form.find(".wwlc-form-error");

  wwlcRegistration.initForm();
  wwlcRegistration.getStatesEvent();
  wwlcRegistration.onSubmitEvent();
  wwlcRegistration.uploadFieldEvent();
  wwlcRegistration.termsConditionsEvent();
});

// Invisible Recaptcha
function submitForm(token) {
  if (token) {
    wwlcRegistration.triggerSubmit();
  }
}

// Checkbox Recaptcha
function recaptchaCallback() {
  jQuery("#recaptcha_field").find("span.error").remove();
}
