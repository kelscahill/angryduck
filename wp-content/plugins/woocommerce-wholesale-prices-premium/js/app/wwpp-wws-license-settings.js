jQuery( document ).ready( function ( $ ) {

    var $wws_settings_wwpp      = $( "#wws_settings_wwpp" ),
        $wws_wwpp_license_email = $wws_settings_wwpp.find( "#wws_wwpp_license_email" ),
        $wws_wwpp_license_key   = $wws_settings_wwpp.find( "#wws_wwpp_license_key" ),
        $wws_save_btn           = $wws_settings_wwpp.find( "#wws_save_btn"),
        errorMessageDuration    = '10000',
        successMessageDuration  = '5000';

    $wws_save_btn.click( function () {

        var $this = $( this );

        $this
            .attr( "disabled" , "disabled" )
            .siblings( ".spinner" )
                .css( {
                        display : 'inline-block',
                        visibility : 'visible'
                    } );

        var $licenseDetails = {
            'license_email' : $.trim( $wws_wwpp_license_email.val() ),
            'license_key'   : $.trim( $wws_wwpp_license_key.val() ),
            'nonce'         : wwpp_wws_license_settings_params.nonce_activate_license
        };

        wwppBackendAjaxServices.saveWWPPLicenseDetails( $licenseDetails )
            .done( function ( data , textStatus , jqXHR ) {

                if ( data.license_status == "active" ) {

                    toastr.success( '' , data.success_msg , { "closeButton" : true , "showDuration" : successMessageDuration } );

                } else {

                    const message = data.error_msg ? data.error_msg : data.message;

                    toastr.error( '' , message , { "closeButton" : true , "showDuration" : errorMessageDuration } );

                    if ( data.expiration_timestamp_i18n ) {

                        var $expired_notice = $( "#wws_wwpp_license_expired_notice" );

                        $expired_notice.find( '#wwpp-license-expiration-date' ).text( data.expiration_timestamp_i18n );
                        $expired_notice.css( { 'display' : 'table-row' } );

                    }

                    console.log( 'Failed To Save Wholesale Prices License Details' );
                    console.log( data );
                    console.log( '----------' );

                }

            } )
            .fail( function ( jqXHR , textStatus , errorThrown ) {

                toastr.error( jqXHR.responseText , wwpp_wws_license_settings_params.i18n_fail_save_wholesale_price , { "closeButton" : true , "showDuration" : errorMessageDuration } );

                console.log( wwpp_wws_license_settings_params.i18n_fail_save_wholesale_price );
                console.log( jqXHR );
                console.log( '----------' );

            } )
            .always( function () {

                $this
                    .removeAttr( "disabled" )
                    .siblings( ".spinner" )
                    .css( {
                        display : 'none',
                        visibility : 'hidden'
                    } );

            } );

    } );

} );