// Ticket: WWLC-144
// Version: 1.6.3
// Source Code: https://code.tutsplus.com/articles/using-the-included-password-strength-meter-script-in-wordpress--wp-34736
jQuery( document ).ready( function( $ ) {

    // Binding to trigger checkPasswordStrength
    $( "body" ).on( "keyup", "input#wwlc_password,input#wwlc_password_confirm", function( event ) {

            var $passwd1      = $( "input#wwlc_password" ),
                $passwd2      = $( "input#wwlc_password_confirm" ),
                $registerForm = $passwd1.closest( 'form.wwlc-register' ),
                $confirmField = RegistrationVars.confirm_password_field_enabled && $passwd2.length ? $passwd2 : $passwd1;

            wwlcFormActions.checkPasswordStrength(
                $passwd1,                                           // First password field
                $confirmField,                                      // Confirm password field
                $registerForm.find( "#wwlc-password-strength" ),    // Strength meter
                $registerForm.find( "#wwlc-register" ),             // Submit button
                wwlc_pword_meter.blacklisted_words,                 // Blacklisted words
                $registerForm
            );

        }
    );

});
