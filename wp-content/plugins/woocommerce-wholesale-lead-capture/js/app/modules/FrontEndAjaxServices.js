/**
 * A function implementing the revealing module pattern to house all ajax request. It implements the ajax promise methodology
 * @return {Ajax Promise} promise it returns a promise, I promise that #lamejoke
 *
 * Info:
 * Ajax is a variable injected by the server inside this js file. It has an attribute named ajaxurl which points
 * to admin ajax url for ajax call purposes
 */
var wwlcFrontEndAjaxServices = function(){

    var createUser =   function( userData , recaptcha_field, wwlc_register_user_nonce_field , wp_http_referer ){
        return jQuery.ajax({
            url         :   Ajax.ajaxurl,
            type        :   "POST",
            data        :   { action : "wwlc_create_user" , user_data : userData , recaptcha_field : recaptcha_field , wwlc_register_user_nonce_field : wwlc_register_user_nonce_field , _wp_http_referer : wp_http_referer },
            dataType    :   "json"
        });
    },
    getStates =   function( cc ){
        return jQuery.ajax({
            url         :   Ajax.ajaxurl,
            type        :   "POST",
            data        :   { action : "wwlc_get_states" , cc : cc },
            dataType    :   "json"
        });
    },
    uploadFile =    function( fileData, formObject ){
        return jQuery.ajax({
            url         :   Ajax.ajaxurl,
            type        :   "POST",
            data        :   fileData,
            cache       :   false,
            dataType    :   "json",
            processData :   false, // Don't process the files
            contentType :   false, // Set content type to false as jQuery will tell the server its a query string request
        })
    },
    getAllowedFileSettings = function( fieldID, _wpnonce ) {
        return jQuery.ajax({
            url         :   Ajax.ajaxurl,
            type        :   "POST",
            data        :   { action : "wwlc_get_allowed_file_settings", field_id : fieldID, _wpnonce : _wpnonce },
            dataType    :   "json"
        });
    };

    return {
        createUser              :   createUser,
        getStates               :   getStates,
        uploadFile              :   uploadFile,
        getAllowedFileSettings  :   getAllowedFileSettings
    }

}();
