jQuery(document).ready(function ($) {

    window.wwlcLoginForm = {

        onSubmitEvent : function() {

            $login_form.on("submit", "#wwlc_loginform", function (e) {
                
                var uname = $("#wwlc_loginform").find("#user_login").val();
                var pword = $("#wwlc_loginform").find("#user_pass").val();
                
                if (uname != '' && pword != '') {
                    if (wwlc_login_page.wwlc_capatcha_enabled ) {
                        var captcha = $("#wwlc_loginform").find("#g-recaptcha-response");
                        var isCaptchaValid = grecaptcha && grecaptcha.getResponse().length > 0 ? true : false;

                        if(wwlc_login_page.wwlc_captcha_type == 'v2_im_not_a_robot' && captcha.val() == '' && !isCaptchaValid) {
                            e.preventDefault();
                            captcha.closest('.field-set').append('<span class="error" style="margin-left: 5px; color: #ff6060;">' + wwlc_login_page.empty_recaptcha + '</span>');
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