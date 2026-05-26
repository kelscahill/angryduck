jQuery(document).ready(function ($) {

    window.wwlcLoginForm = {

        onSubmitEvent : function() {

            $login_form.on("submit", "#wwlc_loginform", function (e) {

                var uname = $("#wwlc_loginform").find("#user_login").val();
                var pword = $("#wwlc_loginform").find("#user_pass").val();

                if (uname != '' && pword != '') {
                    if (wwlc_login_page.wwlc_capatcha_enabled ) {
                        if (wwlc_login_page.wwlc_captcha_type === 'v3_score_based') {
                            e.preventDefault();
                            wwlcExecuteRecaptchaV3(
                                wwlc_login_page.wwlc_captcha_site_key,
                                wwlc_login_page.wwlc_captcha_v3_action
                            ).then(
                                function (token) {
                                    var $form = $("#wwlc_loginform");
                                    var $hidden = $form.find('input[name="g-recaptcha-response"]');
                                    if ($hidden.length === 0) {
                                        $hidden = $('<input type="hidden" name="g-recaptcha-response">');
                                        $form.append($hidden);
                                    }
                                    $hidden.val(token);
                                    // Native DOM submit() bypasses the delegated jQuery submit handler,
                                    // avoiding re-entry into the v3 branch (which would call execute again).
                                    $form.get(0).submit();
                                },
                                function () {
                                    var $form = $("#wwlc_loginform");
                                    // Remove any stale v3 error from a previous failed attempt so messages
                                    // do not stack on repeated retries.
                                    $form.find('span.wwlc-v3-error').remove();
                                    $form.append('<span class="error wwlc-v3-error" style="margin-left: 5px; color: #ff6060;">' + wwlc_login_page.empty_recaptcha + '</span>');
                                }
                            );
                        } else {
                            // Use name selector instead of ID selector to correctly find the
                            // reCAPTCHA response textarea within the login form, even when
                            // multiple reCAPTCHA widgets exist on the same page.
                            var captcha = $("#wwlc_loginform").find('textarea[name="g-recaptcha-response"]');
                            var loginWidgetId = wwlcGetRecaptchaWidgetId('#wwlc-login-form');
                            var isCaptchaValid = typeof grecaptcha !== 'undefined' && grecaptcha.getResponse(loginWidgetId).length > 0;

                            if(wwlc_login_page.wwlc_captcha_type == 'v2_im_not_a_robot' && captcha.val() == '' && !isCaptchaValid) {
                                e.preventDefault();
                                captcha.closest('.field-set').append('<span class="error" style="margin-left: 5px; color: #ff6060;">' + wwlc_login_page.empty_recaptcha + '</span>');
                            }
                        }
                    }
                }
            });

        },
        triggerSubmit : function() {
            $login_form.find("#wwlc_loginform").submit();
        }
    }

    var $login_form = $( "#wwlc-login-form" );
    
    wwlcLoginForm.onSubmitEvent();

});

// Invisible Recaptcha
function submitForm(token) {
    
    if(token){
        wwlcLoginForm.triggerSubmit();
    }

}

// Checkbox Recaptcha
function recaptchaCallback() {
    jQuery('#recaptcha_field').find('span.error').remove();
}