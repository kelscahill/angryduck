var wwlcFormActions = function(){

    var resetForm = function( $form ) {

            $form.find( ".form_field").each( function () {

                var $this = jQuery( this );

                if ( $this.attr( 'type' ) !== undefined && $this.attr( 'type' ) != 'radio' && $this.attr( 'type' ) != 'checkbox' )
                    $this.removeClass( "err" ).removeAttr( "disabled" );
                else if ( $this.attr( 'type' ) == 'radio' )
                    $this.removeAttr( "checked" ).removeAttr( "disabled" ).closest( ".field-set" ).removeClass( "err" );
                else if ( $this.attr( 'type' ) == 'checkbox' )
                    $this.removeAttr( "checked" ).removeAttr( "disabled" ).closest( ".field-set" ).removeClass( "err" );
                else
                    $this.removeClass( "err" ).removeAttr( "disabled" ).find( "option:first" ).attr( "selected" , "selected" );

            } );

        },
        deactivateFormControls = function( $form ) {

            $form.find( ".form-control" ).attr( "disabled" , "disabled" );

        },
        activateFormControls = function( $form ){

            $form.find( ".form-control" ).removeAttr( "disabled" );

        },
        displayStatesDropdownField = function( $form, states, stateSelected, isRequired = false ){

            var requiredTxt = isRequired ? 'data-required="yes"' : '',
                selectField = '<select id="wwlc_state" name="wwlc_state" class="wwlc_registration_field wwlc_form_field" ' + requiredTxt + '>';

                selectField += '<option value="">'+ wwlc_form_object.select_option +'...</option>';

                jQuery.each( states, function( key, value ) {
                    if( stateSelected === key )
                        selectField += '<option value="' + key + '" selected>' + value + '</option>';
                    else
                        selectField += '<option value="' + key + '">' + value + '</option>';
                });
            selectField += "</select>";

            if( $form.find( "select#wwlc_state" ).length != 0 ){

                $form.find( "#wwlc_state" ).siblings( "br" ).remove();
                $form.find( "select#wwlc_state" ).after( selectField );
                $form.find( "select#wwlc_state" ).siblings( ".chosen-container" ).remove();
                $form.find( "select#wwlc_state" ).siblings( ".select2-container" ).remove();
                $form.find( "select#wwlc_state" ).first().remove();

            }else if( $form.find( "input#wwlc_state" ).length != 0 ){

                $form.find( "#wwlc_state" ).siblings( "br" ).remove();
                $form.find( "input#wwlc_state" ).after( selectField );
                $form.find( "input#wwlc_state" ).remove();

            }

        },
        displayStatesTextField = function( $form ){

            var id = $form.attr( "id" );
            if( id == "your-profile" ){
                // if in profile section
                var inputField = '<input type="text" name="wwlc_state" id="wwlc_state" value="" class="regular-text"><br>';
            }else{
                // if in registration form section
                var inputField = '<input type="text" id="wwlc_state" class="input wwlc_registration_field form_field">';
            }

            if( $form.find( "select#wwlc_state" ).length != 0 ){

                $form.find( "select#wwlc_state" ).after( inputField );
                $form.find( "select#wwlc_state" ).siblings( ".chosen-container" ).remove();
                $form.find( "select#wwlc_state" ).siblings( ".select2-container" ).remove();
                $form.find( "select#wwlc_state" ).remove();

            }
        },
        checkPasswordStrength = function( $pass1, $pass2, $strengthResult, $submitButton, blacklistArray, $this ) {

            var pass1 = $pass1.val(),
                pass2 = $pass2.val();

            // Reset the form & meter
            $strengthResult.removeClass( "short bad good strong" );

            // Extend our blacklist array with those from the inputs & site data
            blacklistArray = blacklistArray.concat( wp.passwordStrength.userInputDisallowedList() )

            // Get the password strength
            var strength = wp.passwordStrength.meter( pass1, blacklistArray, pass2 );

            if( pass1 !== "" )
                $this.find( "#wwlc-password-strength" ).show().css({ 'display' : 'inline-block' });
            else
                $this.find( "#wwlc-password-strength" ).hide();

            if( pass1 !== "" && jQuery.inArray( strength, [ 0 , 1 , 2 , 5 ] ) !== -1 )
                $this.find( ".password-field-confirm-weak" ).show();
            else
                $this.find( ".password-field-confirm-weak" ).hide();

            // Add the strength meter results
            switch ( strength ) {

                case 2:
                    $strengthResult.addClass( "bad" ).html( wwlc_pword_meter.bad );
                    break;

                case 3:
                    $strengthResult.addClass( "good" ).html( wwlc_pword_meter.good );
                    break;

                case 4:
                    $strengthResult.addClass( "strong" ).html( wwlc_pword_meter.strong );
                    break;

                case 5:
                    $strengthResult.addClass( "short" ).html( wwlc_pword_meter.mismatch );
                    break;

                default:
                    $strengthResult.addClass( "short" ).html( wwlc_pword_meter.short );

            }

            return strength;
        };

    return {

        resetForm                   :   resetForm,
        deactivateFormControls      :   deactivateFormControls,
        activateFormControls        :   activateFormControls,
        displayStatesDropdownField  :   displayStatesDropdownField,
        displayStatesTextField      :   displayStatesTextField,
        checkPasswordStrength       :   checkPasswordStrength

    };

}();
