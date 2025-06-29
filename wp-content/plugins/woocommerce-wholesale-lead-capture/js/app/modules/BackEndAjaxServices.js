/**
 * A function implementing the revealing module pattern to house all ajax request. It implements the ajax promise methodology
 * @return {Ajax Promise} promise it returns a promise, I promise that #lamejoke
 *
 * Info:
 * ajaxurl points to admin ajax url for ajax call purposes. Added by wp when script is wp enqueued
 */
var wwlcBackEndAjaxServices = function(){

    var approveUser = function (userID, page) {

        return jQuery.ajax({
            url: ajaxurl,
            type: "POST",
            data: { action: "wwlc_approve_user", userID: userID, page: page },
            dataType: "json"
        });

    },
        rejectUser = function (userID, page) {

            return jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                data: { action: "wwlc_reject_user", userID: userID, page: page },
                dataType: "json"
            });

        },
        activateUser = function (userID, page) {

            return jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                data: { action: "wwlc_activate_user", userID: userID, page: page },
                dataType: "json"
            });

        },
        deactivateUser = function (userID, page) {

            return jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                data: { action: "wwlc_deactivate_user", userID: userID, page: page },
                dataType: "json"
            });

        },
        createLeadPages = function () {

            return jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                data: { action: "wwlc_create_lead_pages" },
                dataType: "json"
            });

        },
        saveWWLCLicenseDetails = function (licenseDetails) {

            return jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wwlc_activate_license',
                    license_email: licenseDetails.license_email,
                    license_key: licenseDetails.license_key,
                    ajax_nonce: licenseDetails.nonce
                },
                dataType: 'json'
            });

        },
        addRegistrationFormCustomField = function (_wpnonce, customField) {

            return jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                data: { action: "wwlc_add_registration_form_custom_field", customField, _wpnonce },
                dataType: "json"
            });

        },
        editRegistrationFormCustomField = function (_wpnonce, customField) {

            return jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                data: { action: "wwlc_edit_registration_form_custom_field", customField, _wpnonce },
                dataType: "json"
            });

        },
        deleteRegistrationFormCustomField = function (_wpnonce, field_id) {

            return jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                data: { action: "wwlc_delete_registration_form_custom_field", field_id, _wpnonce },
                dataType: "json"
            });

        },
        getRegistrationFormCustomFieldByID = function (_wpnonce, field_id) {

            return jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                data: { action: "wwlc_get_custom_field_by_id", field_id, _wpnonce },
                dataType: "json"
            });

        },
        getStates = function (cc) {

            return jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                data: { action: "wwlc_get_states", cc: cc },
                dataType: "json"
            });

        },
        getContentFieldEditor = function (content) {

            return jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                data: { action: "wwlc_content_wp_editor", content: content },
                dataType: 'text'
            });
        },
        forceFetchUpdateData = function () {
            return jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                data: { action: "wwlc_force_fetch_update_data" },
                dataType: "json",
            });
        },
        uploadFile = function (fileData) {
            return jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                data: fileData,
                cache: false,
                dataType: "json",
                processData: false, // Don't process the files
                contentType: false, // Set content type to false as jQuery will tell the server its a query string request
            })
        },
        getAllowedFileSettings = function (fieldID, _wpnonce) {
            return jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                data: { action: "wwlc_get_allowed_file_settings", field_id: fieldID, _wpnonce: _wpnonce },
                dataType: "json"
            });
        },
        removeFile = function (fieldID, userid) {
            return jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                data: { action: "wwlc_file_remove_handler", field_name: fieldID, userid: userid },
                dataType: "json",
            });
        };

    return {
        getStates                           :   getStates,
        approveUser                         :   approveUser,
        rejectUser                          :   rejectUser,
        activateUser                        :   activateUser,
        deactivateUser                      :   deactivateUser,
        createLeadPages                     :   createLeadPages,
        saveWWLCLicenseDetails              :   saveWWLCLicenseDetails,
        addRegistrationFormCustomField      :   addRegistrationFormCustomField,
        editRegistrationFormCustomField     :   editRegistrationFormCustomField,
        deleteRegistrationFormCustomField   :   deleteRegistrationFormCustomField,
        getRegistrationFormCustomFieldByID  :   getRegistrationFormCustomFieldByID,
        getContentFieldEditor               :   getContentFieldEditor,
        forceFetchUpdateData                :   forceFetchUpdateData,
        uploadFile                          :   uploadFile,
        getAllowedFileSettings              :   getAllowedFileSettings,
        removeFile                          :   removeFile

    }

}();
