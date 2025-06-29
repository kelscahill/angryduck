var wwlcFormValidator = function () {

    var trimFieldValues = function (formFields) {

        for (var i = 0; i < formFields.length; i++)
            formFields[i].val(formFields[i].val().trim());

    },
        validateRequiredField = function (formFields, RegistrationVars) {

            var checkpoint = true,
                numberFields = [],
                radioFields = {},
                radioCheckpoints = {},
                checkboxFields = {},
                checkboxCheckpoints = {},
                generalRadioCheckpoint = true,
                generalCheckboxCheckpoint = true,
                recaptchaCheckpoint = true,
                recaptchaField = jQuery('#wwlc-registration-form').find('textarea[name="g-recaptcha-response"]');

            for (var i = 0; i < formFields.length; i++) {

                var inlineRequiredError = false;

                if (formFields[i].attr('type') == 'email') {

                    if (formFields[i].attr('data-required') == 'yes' && formFields[i].val() == '') {

                        inlineRequiredError = true;
                        formFields[i].addClass('err');
                        checkpoint = false;
                    } else if (formFields[i].val() !== '' && wwlcFormValidator.validateEmail(formFields[i].val()) == false) {

                        formFields[i].closest('.field-set').append('<span class="inline-error">' + RegistrationVars.email_invalid + '</span>');
                        formFields[i].addClass('err');
                        checkpoint = false;
                    } else {
                        formFields[i].removeClass('err');
                    }

                } else if (formFields[i].attr('type') == 'checkbox') {

                    if (checkboxFields[formFields[i].attr('name')] === undefined)
                        checkboxFields[formFields[i].attr('name')] = [];

                    checkboxFields[formFields[i].attr('name')].push(formFields[i].is(":checked") ? 1 : 0);
                    checkboxCheckpoints[formFields[i].attr('name')] = false;

                } else if (formFields[i].attr('type') == 'radio') {

                    if (radioFields[formFields[i].attr('name')] === undefined)
                        radioFields[formFields[i].attr('name')] = [];

                    radioFields[formFields[i].attr('name')].push(formFields[i].is(":checked") ? 1 : 0);
                    radioCheckpoints[formFields[i].attr('name')] = false;

                } else if (formFields[i].attr('type') == 'tel' && formFields[i].hasClass('phone')) {

                    var mask = formFields[i].attr('data-phonemask');

                    if (mask != 'No format' && formFields[i].val() && formFields[i].val().length != mask.length) {

                        formFields[i].closest('.field-set').append('<span class="inline-error">' + RegistrationVars.phone_invalid + '</span>');
                        formFields[i].addClass('err');
                        checkpoint = false;

                    } else if (formFields[i].attr('data-required') == 'yes' && formFields[i].val() == '') {

                        inlineRequiredError = true;
                        formFields[i].addClass('err');
                        checkpoint = false;

                    } else {
                        formFields[i].removeClass('err');
                    }

                } else {

                    if (formFields[i].attr('data-required') == 'yes' && formFields[i].val() == '') {

                        inlineRequiredError = true;
                        formFields[i].addClass('err');
                        checkpoint = false;

                    } else {

                        formFields[i].removeClass('err');

                        if (formFields[i].attr('type') == 'number')
                            numberFields.push(formFields[i]);

                    }

                }

                // display inline required field error
                if (inlineRequiredError)
                    formFields[i].closest('.field-set').append('<span class="inline-error">' + RegistrationVars.field_is_required + '</span>');
            }

            // Validate number fields
            for (var i = 0; i < numberFields.length; i++) {

                var min = numberFields[i].attr('min'),
                    max = numberFields[i].attr('max'),
                    step = numberFields[i].attr('step'),
                    val = numberFields[i].val();

                min = min == null || min == "" ? 0 : parseInt(min);
                max = max == null || max == "" ? 0 : parseInt(max);
                step = step == null || step == "" ? 0 : parseInt(step);
                val = val == null || val == "" ? 0 : parseInt(val);

                if (val > 0 && (min > 0 || max > 0 || step > 0)) {

                    if (step > 0 && (val - min) % step != 0) {

                        numberFields[i].closest(".field-set").append('<span class="inline-error">' + RegistrationVars.number_not_divisible_by_step + step + '</span>');
                        numberFields[i].addClass('err');
                        checkpoint = false;

                    } else if ((min > 0 && max > 0) && (val < min && val > max)) {

                        numberFields[i].closest(".field-set").append('<span class="inline-error">' + RegistrationVars.number_max_less_than_min + '</span>');
                        numberFields[i].addClass('err');
                        checkpoint = false;

                    } else if (min > 0 && val < min) {

                        numberFields[i].closest(".field-set").append('<span class="inline-error">' + RegistrationVars.number_less_than_min + '</span>');
                        numberFields[i].addClass('err');
                        checkpoint = false;

                    } else if (max > 0 && val > max) {

                        numberFields[i].closest(".field-set").append('<span class="inline-error">' + RegistrationVars.number_greater_than_max + '</span>');
                        numberFields[i].addClass('err');
                        checkpoint = false;

                    } else
                        numberFields[i].removeClass('err');

                } else
                    numberFields[i].removeClass('err');

            }

            // This is the section where we validate checkbox fields
            for (var key in checkboxFields) {

                if (checkboxFields.hasOwnProperty(key)) {

                    var checkboxField = checkboxFields[key];
                    var inputSelector = jQuery("input[ name = '" + key + "' ]");

                    if (inputSelector.closest('.checkbox_options_holder').attr('data-required') == 'yes') {

                        for (var i = 0; i < checkboxField.length; i++) {

                            if (checkboxField[i])
                                checkboxCheckpoints[key] = true;

                        }
                    } else {

                        checkboxCheckpoints[key] = true;
                    }

                    var checkboxErrorMsg = inputSelector.length > 1 ? RegistrationVars.checkbox_inline_error : RegistrationVars.field_is_required;

                    if (inputSelector.closest('.checkbox_options_holder').hasClass('terms_conditions_checkbox'))
                        checkboxErrorMsg = RegistrationVars.agree_terms_conditions_error;

                    if (!checkboxCheckpoints[key])
                        inputSelector.closest(".field-set").addClass("err")
                            .append('<span class="inline-error">' + checkboxErrorMsg + '</span>');
                    else
                        inputSelector.closest(".field-set").removeClass("err");

                    generalCheckboxCheckpoint = generalCheckboxCheckpoint && checkboxCheckpoints[key];

                }

            }

            // This is the section where we validate radio fields
            for (var key in radioFields) {

                if (radioFields.hasOwnProperty(key)) {

                    var radioField = radioFields[key];
                    var inputSelector = jQuery("input[ name = '" + key + "' ]");

                    if (inputSelector.closest('.radio_options_holder').attr('data-required') == 'yes') {

                        for (var i = 0; i < radioField.length; i++) {

                            if (radioField[i])
                                radioCheckpoints[key] = true;

                        }
                    } else {

                        radioCheckpoints[key] = true;
                    }


                    if (!radioCheckpoints[key])
                        inputSelector.closest(".field-set").addClass("err")
                            .append('<span class="inline-error">' + RegistrationVars.field_is_required + '</span>');
                    else
                        inputSelector.closest(".field-set").removeClass("err");

                    generalRadioCheckpoint = generalRadioCheckpoint && radioCheckpoints[key];

                }

            }

            // recaptcha field validation
            if (RegistrationVars.wwlc_captcha_enabled && RegistrationVars.wwlc_captcha_type === 'v2_im_not_a_robot' && recaptchaField.length > 0 && recaptchaField.val() == '') {

                recaptchaField.closest('.field-set').append('<span class="inline-error">' + RegistrationVars.empty_recaptcha + '</span>');
                recaptchaCheckpoint = false;
            }

            return checkpoint && generalRadioCheckpoint && generalCheckboxCheckpoint && recaptchaCheckpoint;

        },
        constructUserData = function (formFields) {

            var userData = {},
                radioFields = {},
                checkboxFields = {};

            for (var i = 0; i < formFields.length; i++) {

                switch (formFields[i].attr("id")) {

                    case 'first_name':
                        userData.first_name = formFields[i].val();
                        break;

                    case 'last_name':
                        userData.last_name = formFields[i].val();
                        break;

                    case 'wwlc_phone':
                        userData.wwlc_phone = formFields[i].val();
                        break;

                    case 'user_email':
                        userData.user_email = formFields[i].val();
                        break;

                    case 'wwlc_username':
                        userData.wwlc_username = formFields[i].val();
                        break;

                    case 'wwlc_company_name':
                        userData.wwlc_company_name = formFields[i].val();
                        break;

                    case 'wwlc_address':
                        userData.wwlc_address = formFields[i].val();
                        break;

                    case 'wwlc_role':
                        userData.wwlc_role = formFields[i].val();
                        break;

                    case 'wwlc_auto_approve':
                        userData.wwlc_auto_approve = formFields[i].val();

                    case 'wwlc_auto_login':
                        userData.wwlc_auto_login = formFields[i].val();

                    default:

                        if (formFields[i].attr('type') == 'checkbox') {

                            if (formFields[i].is(":checked")) {

                                if (checkboxFields[formFields[i].attr('name')] === undefined)
                                    checkboxFields[formFields[i].attr('name')] = [];

                                checkboxFields[formFields[i].attr('name')].push(formFields[i].val());

                            }

                        } else if (formFields[i].attr('type') == 'radio') {

                            if (formFields[i].is(":checked"))
                                if (radioFields[formFields[i].attr('name')] === undefined)
                                    radioFields[formFields[i].attr('name')] = formFields[i].val();

                        } else
                            userData[formFields[i].attr("id")] = formFields[i].val();

                        break;
                }

            }

            for (var key in checkboxFields)
                if (checkboxFields.hasOwnProperty(key))
                    userData[key] = checkboxFields[key];

            for (var key in radioFields)
                if (radioFields.hasOwnProperty(key))
                    userData[key] = radioFields[key];

            return userData;

        },
        validateEmail = function (email) {

            var pattern = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
            return pattern.test(email);

        },
        confirmPassword = function () {

            if (!RegistrationVars.confirm_password_field_enabled)
                return true;

            var $passwd_1 = jQuery('input#wwlc_password'),
                $passwd_2 = jQuery('input#wwlc_password_confirm'),
                check = $passwd_1.val() === $passwd_2.val();

            if (!check) {

                $passwd_1.addClass('err');
                $passwd_2.addClass('err').closest('.form-row')
                    .append('<span class="inline-error">' + RegistrationVars.confirm_password_error_message + '</span>');
            }

            return check;
        },
        validatePasswordStrength = function () {
            // Check if password field is enabled.
            if (!RegistrationVars.activate_password_field)
                return true;

            // Check if password strength is enabled.
            if (!RegistrationVars.password_minimum_strength)
                return true;

            var password = jQuery('input#wwlc_password');
            var strongRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?#&])[A-Za-z\d@$!%*?#&]{12,}$/;
            var check = strongRegex.test(password.val());

            if (!check) {
                password.addClass('err').closest('.form-row').append('<span class="inline-error">' + RegistrationVars.password_strength_error_message + '</span>');
            }

            return check;
        };

    return {

        validateRequiredField: validateRequiredField,
        trimFieldValues: trimFieldValues,
        constructUserData: constructUserData,
        validateEmail: validateEmail,
        confirmPassword: confirmPassword,
        validatePasswordStrength: validatePasswordStrength

    };

}();
