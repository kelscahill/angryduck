jQuery(document).ready(function($){

    // Variable Declaration And Selector Caching
    var $yourProfile    = $("#your-profile"),
        countryCode 	= $yourProfile.find("select#wwlc_country").val(),
    	stateSelected 	= $yourProfile.find("#wwlc_state").val();

    // Events
    $yourProfile.find("select#wwlc_country").select2();

    // On page load prefill state
    wwlcBackEndAjaxServices.getStates( countryCode )
        .done(function(data, textStatus, jqXHR){

            if ( data.status == 'success' ) {
            	
                wwlcFormActions.displayStatesDropdownField( $yourProfile, data.states, stateSelected );
                $yourProfile.find("select#wwlc_state").select2();

            } else {

                wwlcFormActions.displayStatesTextField( $yourProfile );

            }
        })
        .fail(function(jqXHR, textStatus, errorThrown){

            console.log( jqXHR.responseText );
            console.log( textStatus );
            console.log( errorThrown );
            console.log( '----------' );

        });

    $( "#wwlc_country" ).on( "change", function(){

        var cc = $(this).val();

        if( cc != "" ){

            wwlcBackEndAjaxServices.getStates( cc )
                .done(function(data, textStatus, jqXHR){

                    if ( data.status == 'success' ) {
                        console.log(data);
                        wwlcFormActions.displayStatesDropdownField( $yourProfile, data.states, stateSelected );
                        $yourProfile.find("select#wwlc_state").select2();

                    } else {

                        wwlcFormActions.displayStatesTextField( $yourProfile );

                    }
                })
                .fail(function(jqXHR, textStatus, errorThrown){

                    console.log( jqXHR.responseText );
                    console.log( textStatus );
                    console.log( errorThrown );
                    console.log( '----------' );

                });
        }
    });

    // Update user file.
    $(".wwlc-admin-user-field-file input[type='file']").on('change', function () { 
        var fileField        = $(this);
        var closestElement   = fileField.closest(".wwlc-admin-user-field-file");
        var fieldID          = closestElement.find('input[type="hidden"]').prop("id");
        var selectedFile     = fileField[0].files[0];
        var selectedFileType = selectedFile.name.split(".").pop();
        
        // Disable input and show spinner.
        fileField.prop("disabled", true);
        closestElement.find(".wwlc-loader").css("height", 24).show();

        // Check if file type is allowed.
        wwlcBackEndAjaxServices.getAllowedFileSettings(fieldID, $('#wwlc_update_user_nonce_field').val()).done(function (response) {
            // Hide notice
            closestElement.find(".inline-error").remove();

            if (
                $.inArray(
                    selectedFileType,
                    response["allowed_file_types"]
                ) < 0
            ) {
                closestElement
                    .append(
                        '<div class="inline-error">' +
                        UserObject.file_format_not_supported +
                        "</div>"
                );
                // hide file input and loader, and show the filename placeholder
                closestElement.find(".wwlc-loader").hide();
                fileField.val('');
                fileField.prop("disabled", false);
            } else if (selectedFile.size <= 0) {
                closestElement
                    .append(
                        '<div class="inline-error">' +
                        UserObject.file_size_is_empty +
                        "</div>"
                );
                // hide file input and loader, and show the filename placeholder
                closestElement.find(".wwlc-loader").hide();
                fileField.val('');
                fileField.prop("disabled", false);
            } else if (
                selectedFile.size >
                response["max_allowed_file_size"]
            ) {
                closestElement
                    .append(
                        '<div class="inline-error">' +
                        UserObject.file_size_exceeds_max_allowed +
                        "</div>"
                );
                // hide file input and loader, and show the filename placeholder
                closestElement.find(".wwlc-loader").hide();
                fileField.val('');
                fileField.prop("disabled", false);
            } else { 
                var fileData = new FormData();

                fileData.append( "action", "wwlc_file_upload_handler" );
                fileData.append( "uploaded_file", selectedFile );
                fileData.append("file_settings", JSON.stringify(response));
                
                wwlcBackEndAjaxServices.uploadFile(fileData)
                    .done(function (data) {
                        if (data.status === "success") { 
                            // save file name to hidden input
                            closestElement.find(
                                'input[type="hidden"]'
                            ).val(data.file_name);

                            closestElement.find(".inline-error").remove();

                            // hide file input and loader, and show the filename placeholder
                            closestElement.find(".wwlc-loader").hide();
                            fileField.prop("disabled", false);
                        }
                    })
                    .fail(function ( jqXHR, textStatus, errorThrown ) {
                        console.log("ERRORS: " + textStatus);
                    });
            }
        });

    });

    // Remove user file.
    $(".wwlc-admin-user-field .wwlc-remove-file").on('click', function () {
        var closestElement = jQuery(".wwlc-admin-user-field");

        closestElement.find('input[type="hidden"]').val('');
        closestElement.find("a").remove();
        closestElement.find(".wwlc-remove-file").remove();
    });
});