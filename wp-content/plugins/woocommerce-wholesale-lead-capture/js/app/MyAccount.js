jQuery(document).ready(function($){
    
    $(".wwlc-account-details-field input[type='file']").on('change', function () { 
        var fileField        = $(this);
        var closestElement   = fileField.closest(".wwlc-account-details-field");
        var fieldID          = closestElement.find('input[type="hidden"]').prop("id");
        var selectedFile     = fileField[0].files[0];
        var selectedFileType = selectedFile.name.split(".").pop();
        
        // Disable input and show spinner.
        fileField.prop("disabled", true);
        closestElement.find(".wwlc-loader").css("height", 24).show();

        // Check if file type is allowed.
        wwlcFrontEndAjaxServices.getAllowedFileSettings(fieldID, $('#wwlc_update_user_nonce_field').val()).done(function (response) {
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
                        MyAccountVars.file_format_not_supported +
                        "</div>"
                    );
            } else if (selectedFile.size <= 0) {
                closestElement
                    .append(
                        '<div class="inline-error">' +
                        MyAccountVars.file_size_is_empty +
                        "</div>"
                    );
            } else if (
                selectedFile.size >
                response["max_allowed_file_size"]
            ) {
                closestElement
                    .append(
                        '<div class="inline-error">' +
                        MyAccountVars.file_size_exceeds_max_allowed +
                        "</div>"
                    );
            } else { 
                var fileData = new FormData();

                fileData.append( "action", "wwlc_file_upload_handler" );
                fileData.append( "uploaded_file", selectedFile );
                fileData.append("file_settings", JSON.stringify(response));
                
                wwlcFrontEndAjaxServices.uploadFile(fileData)
                    .done(function (data) {
                        if (data.status === "success") { 
                            // save file name to hidden input
                            closestElement.find(
                                'input[type="hidden"]'
                            ).val(data.file_name);

                            closestElement.find(".inline-error").remove();
                        }
                    })
                    .error(function ( jqXHR, textStatus, errorThrown ) {
                        console.log("ERRORS: " + textStatus);
                    });
            }

            // hide file input and loader, and show the filename placeholder
            closestElement.find(".wwlc-loader").hide();
            fileField.prop("disabled", false);
        });

    });

});