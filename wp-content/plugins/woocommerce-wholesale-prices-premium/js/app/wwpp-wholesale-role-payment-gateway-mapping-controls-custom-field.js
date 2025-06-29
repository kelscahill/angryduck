jQuery( document ).ready( function ( $ ) {

    /*
     |------------------------------------------------------------------------------------------------------------------
     | Variable Declarations
     |------------------------------------------------------------------------------------------------------------------
     */

    var $wr_pg_mapping_controls     = $( ".wr-pg-mapping-controls" ),
        $wwpp_wr_pg_wholesale_roles = $wr_pg_mapping_controls.find( "#wwpp-wr-pg-wholesale-roles" ),
        $wwpp_wr_pg_payment_gateway = $wr_pg_mapping_controls.find( "#wwpp-wr-pg-payment-gateway" ),
        $wr_pg_button_controls      = $( ".wr-pg-button-controls" ),
        $wr_pg_mapping              = $( "#wr-pg-mapping" ),
        errorMessageDuration        = '10000',
        successMessageDuration      = '5000';




    /*
     |------------------------------------------------------------------------------------------------------------------
     | Helper Functions
     |------------------------------------------------------------------------------------------------------------------
     */

    function removeTableNoItemsPlaceholder ( $table ) {

        $table.find( "tbody" ).find( ".no-items" ).remove();

    }

    function resetTableRowStyling () {

        $wr_pg_mapping
            .find( "tbody" )
            .find( "tr" )
            .each( function( index ) {

                index++; // we do this coz index is zero base

                if (index % 2 == 0) {
                    // even
                    $(this)
                        .removeClass("odd")
                        .removeClass("alternate")
                        .addClass("even");

                } else {
                    // odd
                    $(this)
                        .removeClass("even")
                        .addClass("odd")
                        .addClass("alternate");

                }

            } );

    }

    function resetFields () {

        $wwpp_wr_pg_wholesale_roles.val( "" ).removeAttr( "disabled" ).trigger( "change" ).trigger( "chosen:updated" );
        $wwpp_wr_pg_payment_gateway.val( "" ).trigger( "change" ).trigger( "chosen:updated" );

    }

    function validateFields () {

        error_fields = [];

        if ( $.trim( $wwpp_wr_pg_wholesale_roles.val() ) == "" )
            error_fields.push( wwpp_wholesale_role_payment_gateway_mapping_controls_custom_field_params.i18n_wholesale_role );

        if ( $.trim( $wwpp_wr_pg_payment_gateway.val() ) == "" )
            error_fields.push( wwpp_wholesale_role_payment_gateway_mapping_controls_custom_field_params.i18n_payment_gateways );

        return error_fields;

    }




    /*
     |------------------------------------------------------------------------------------------------------------------
     | Events
     |------------------------------------------------------------------------------------------------------------------
     */

    $wr_pg_button_controls.find( "#wr-pg-add-mapping" ).click( function () {

        var $this = $( this );

        $this.attr( 'disabled' , 'disabled' );
        $wr_pg_button_controls.addClass( 'processing' );

        var error_fields = validateFields();

        if ( error_fields.length > 0 ) {

            var msg = "Please specify values for the following field/s:<br/><ul>";

            for ( var i = 0 ; i < error_fields.length ; i++ )
                msg += "<li>" + error_fields[ i ] + "</li>";

            msg += "</ul>";

            toastr.error( msg , wwpp_wholesale_role_payment_gateway_mapping_controls_custom_field_params.i18n_form_error , { "closeButton" : true , "showDuration" : errorMessageDuration } );

            $this.removeAttr( 'disabled' );
            $wr_pg_button_controls.removeClass( 'processing' );

            return false;

        }

        var mapping = {
            "wholesale_role"    :   $.trim( $wwpp_wr_pg_wholesale_roles.val() ),
            "payment_gateways"  :   [],
        };

        var selected_gateways = $wwpp_wr_pg_payment_gateway.val();

        for ( var i = 0 ; i < selected_gateways.length ; i++ ) {

            mapping.payment_gateways.push( {
                'id'    :   selected_gateways[ i ],
                'title' :   $wwpp_wr_pg_payment_gateway.find( "option[value='" + selected_gateways[ i ] + "']" ).text()
            } );

        }

        wwppBackendAjaxServices.addWholesaleRolePaymentGatewayMapping( mapping )
            .done( function ( data , textStatus , jqXHR ) {

                if ( data.status == 'success' ) {

                    toastr.success( '' , wwpp_wholesale_role_payment_gateway_mapping_controls_custom_field_params.i18n_success_add_wholesale_role , { "closeButton" : true , "showDuration" : successMessageDuration } );

                    removeTableNoItemsPlaceholder( $wr_pg_mapping );

                    var tr_class = "";

                    if( $wr_pg_mapping.find( "tr" ).length % 2 == 0 ) // currently even, next add (our add) would make it odd
                        tr_class = "odd alternate";
                    else // currently odd, next add (our add) would make it even
                        tr_class = "even";

                    var payment_gateway_id_list = '<ul>',
                        payment_gateway_title_list = '<ul>';

                    for ( var i = 0 ; i < mapping.payment_gateways.length ; i++ ) {

                        payment_gateway_id_list += '<li>' + mapping.payment_gateways[ i ].id + '</li>';
                        payment_gateway_title_list += '<li>' + mapping.payment_gateways[ i ].title + '</li>' ;

                    }

                    payment_gateway_id_list += '</ul>';
                    payment_gateway_title_list += '</ul>';

                    $wr_pg_mapping.find( "tbody" )
                        .append(    '<tr class="' + tr_class + ' edited">' +
                                        '<td class="meta hidden">' +
                                            '<span class="wholesale-role">' + mapping[ 'wholesale_role' ] + '</span>' +
                                            '<span class="payment-gateways">' +
                                                payment_gateway_id_list +
                                            '</span>' +
                                        '</td>' +
                                        '<td class="wholesale-role-text">' + $wwpp_wr_pg_wholesale_roles.find( "option[value='" + mapping[ 'wholesale_role' ] + "']" ).text() + '</td>' +
                                        '<td class="payment-gateways-text">' +
                                            payment_gateway_title_list +
                                        '</td>' +
                                        '<td class="controls">' +
                                            '<a class="edit dashicons dashicons-edit"></a>' +
                                            '<a class="delete dashicons dashicons-no"></a>' +
                                        '</td>' +
                                    '</tr>' ) ;

                    resetFields();

                    // Remove edited class to the recently added user field
                    setTimeout( function () {
                        $wr_pg_mapping
                            .find( "tr.edited" )
                            .removeClass( "edited" );
                    } , 500 );

                } else {

                    toastr.error( data.error_message , wwpp_wholesale_role_payment_gateway_mapping_controls_custom_field_params.i18n_fail_add_wholesale_role , { "closeButton" : true , "showDuration" : errorMessageDuration } );

                    console.log( wwpp_wholesale_role_payment_gateway_mapping_controls_custom_field_params.i18n_fail_add_wholesale_role );
                    console.log( data );
                    console.log( '----------' );

                }

            } )
            .fail( function ( jqXHR , textStatus , errorThrown ) {

                toastr.error( jqXHR.responseText , wwpp_wholesale_role_payment_gateway_mapping_controls_custom_field_params.i18n_fail_add_wholesale_role , { "closeButton" : true , "showDuration" : errorMessageDuration } );

                console.log( wwpp_wholesale_role_payment_gateway_mapping_controls_custom_field_params.i18n_fail_add_wholesale_role );
                console.log( jqXHR );
                console.log( '----------' );

            } )
            .always( function () {

                $this.removeAttr( 'disabled' );
                $wr_pg_button_controls.removeClass( 'processing' );

            } );

    } );

    $wr_pg_button_controls.find( "#wr-pg-save-mapping" ).click( function () {

        var $this = $( this );

        $this.attr( 'disabled' , 'disabled' );
        $wr_pg_button_controls.addClass( 'processing' );

        var error_fields = validateFields();

        if ( error_fields.length > 0 ) {

            var msg = "Please specify values for the following field/s:<br/><ul>";

            for ( var i = 0 ; i < error_fields.length ; i++ )
                msg += "<li>" + error_fields[ i ] + "</li>";

            msg += "</ul>";

            toastr.error( msg , wwpp_wholesale_role_payment_gateway_mapping_controls_custom_field_params.i18n_form_error , { "closeButton" : true , "showDuration" : errorMessageDuration } );

            $this.removeAttr( 'disabled' );
            $wr_pg_button_controls.removeClass( 'processing' );

            return false;

        }

        var mapping = {
            "wholesale_role"    :   $.trim( $wwpp_wr_pg_wholesale_roles.val() ),
            "payment_gateways"  :   [],
        };

        var selected_gateways = $wwpp_wr_pg_payment_gateway.val();

        for ( var i = 0 ; i < selected_gateways.length ; i++ ) {

            mapping.payment_gateways.push( {
                'id'    :   selected_gateways[ i ],
                'title' :   $wwpp_wr_pg_payment_gateway.find( "option[value='" + selected_gateways[ i ] + "']" ).text()
            } );

        }

        wwppBackendAjaxServices.updateWholesaleRolePaymentGatewayMapping( mapping )
            .done( function ( data , textStatus , jqXHR ) {

                if ( data.status == 'success' ) {

                    var payment_gateway_id_list = '<ul>',
                        payment_gateway_title_list = '<ul>';

                    for ( var i = 0 ; i < mapping.payment_gateways.length ; i++ ) {

                        payment_gateway_id_list += '<li>' + mapping.payment_gateways[ i ].id + '</li>';
                        payment_gateway_title_list += '<li>' + mapping.payment_gateways[ i ].title + '</li>' ;

                    }

                    payment_gateway_id_list += '</ul>';
                    payment_gateway_title_list += '</ul>';

                    $wr_pg_mapping.find( "tr.edited" )
                        .find( ".meta" )
                        .find( ".wholesale-role" ).text( mapping.wholesale_role ).end()
                        .find( ".payment-gateways" ).html( payment_gateway_id_list ).end()
                        .end()
                        .find( ".wholesale-role-text" ).text( $wwpp_wr_pg_wholesale_roles.find( "option[value='" + mapping.wholesale_role + "']" ).text() ).end()
                        .find( ".payment-gateways-text" ).html( payment_gateway_title_list );

                    $wr_pg_mapping.find( "tr .controls .dashicons" )
                        .css( "display" , "inline-block" );

                    resetFields();

                    // Remove edited class to the recently added user field
                    setTimeout( function () {
                        $wr_pg_mapping
                            .find( "tr.edited" )
                            .removeClass( "edited" );
                    } , 500 );

                    $wr_pg_button_controls
                        .removeClass( "edit-mode" )
                        .addClass( "add-mode" );

                    toastr.success( '' , wwpp_wholesale_role_payment_gateway_mapping_controls_custom_field_params.i18n_success_update_wholesale_role , { "closeButton" : true , "showDuration" : successMessageDuration } );

                } else {

                    toastr.error( data.error_message , wwpp_wholesale_role_payment_gateway_mapping_controls_custom_field_params.i18n_fail_update_wholesale_role , { "closeButton" : true , "showDuration" : errorMessageDuration } );

                    console.log( data.error_message , wwpp_wholesale_role_payment_gateway_mapping_controls_custom_field_params.i18n_fail_update_wholesale_role );
                    console.log( data );
                    console.log( '----------' );

                }

            } )
            .fail( function ( jqXHR , textStatus , errorThrown ) {

                toastr.error( jqXHR.responseText , data.error_message , wwpp_wholesale_role_payment_gateway_mapping_controls_custom_field_params.i18n_fail_update_wholesale_role , { "closeButton" : true , "showDuration" : errorMessageDuration } );

                console.log( data.error_message , wwpp_wholesale_role_payment_gateway_mapping_controls_custom_field_params.i18n_fail_update_wholesale_role );
                console.log( jqXHR );
                console.log( '----------' );

            } )
            .always( function () {

                $this.removeAttr( 'disabled' );
                $wr_pg_button_controls.removeClass( 'processing' );

            } );

    } );

    $wr_pg_button_controls.find( "#wr-pg-cancel-edit-mapping" ).click( function () {

        resetFields();

        $wr_pg_button_controls
            .removeClass( "edit-mode" )
            .addClass( "add-mode" );

        $wr_pg_mapping
            .find( "tbody tr" )
            .removeClass( "edited" )
            .find( ".controls .dashicons" )
            .css( "display" , "inline-block" );

    } );

    $wr_pg_mapping.delegate( '.edit' , 'click' , function () {

        var $this = $( this ),
            $currentTr = $this.closest( 'tr' );

        $currentTr.addClass( "edited" );
        $wr_pg_mapping.find( ".controls .dashicons" )
            .css( "display" , "none" );

        var currMapping = {
            "wholesale_role"    :   $.trim( $currentTr.find( ".meta" ).find( ".wholesale-role" ).text() ),
            "payment_gateways"   :   [],
        };

        $currentTr.find( ".meta" ).find( ".payment-gateways ul li" ).each( function () {

            currMapping.payment_gateways.push( $.trim( $( this ).text() ) );

        } );

        $wwpp_wr_pg_wholesale_roles.val( currMapping.wholesale_role ).attr( "disabled" , "disabled" ).trigger( "change" ).trigger( "chosen:updated" );

        for ( var i = 0 ; i < currMapping.payment_gateways.length ; i++ )
            $wwpp_wr_pg_payment_gateway.find( "option[value='" + currMapping.payment_gateways[ i ] + "']" ).prop('selected', true);

        $wwpp_wr_pg_payment_gateway.trigger( "change" ).trigger( "chosen:updated" );

        $wr_pg_button_controls
            .removeClass( "add-mode" )
            .addClass( "edit-mode" );

    } );

    $wr_pg_mapping.delegate( '.delete' , 'click' , function () {

        var $this = $( this ),
            $currentTr = $this.closest( 'tr' );

        $currentTr.addClass( "edited" );

        if ( confirm( wwpp_wholesale_role_payment_gateway_mapping_controls_custom_field_params.i18n_click_ok_remove_wholesale_role ) ) {

            var wholesaleRoleKey = $.trim( $currentTr.find( ".meta" ).find( ".wholesale-role" ).text() );

            $wr_pg_mapping.find( ".controls .dashicons" )
                .css( "display" , "none" );

            wwppBackendAjaxServices.deleteWholesaleRolePaymentGatewayMapping( wholesaleRoleKey )
                .done( function ( data , textStatus , jqXHR ) {

                    if ( data.status == 'success' ) {

                        $currentTr.fadeOut( "fast" , function () {

                            $currentTr.remove();

                            resetTableRowStyling();

                            // If no more item then append the empty table placeholder
                            if ( $wr_pg_mapping.find( "tbody" ).find( "tr" ).length <= 0 ) {

                                $wr_pg_mapping
                                    .find("tbody")
                                    .html(  '<tr class="no-items">' +
                                                '<td class="colspanchange" colspan="3">' + wwpp_wholesale_role_payment_gateway_mapping_controls_custom_field_params.i18n_no_mappings_found + '</td>' +
                                            '</tr>');

                            }

                        } );

                        toastr.success( '' , wwpp_wholesale_role_payment_gateway_mapping_controls_custom_field_params.i18n_success_delete_wholesale_role , { "closeButton" : true , "showDuration" : successMessageDuration } );

                    } else {

                        toastr.error( data.error_message , wwpp_wholesale_role_payment_gateway_mapping_controls_custom_field_params.i18n_fail_delete_wholesale_role , { "closeButton" : true , "showDuration" : errorMessageDuration } );

                        console.log( wwpp_wholesale_role_payment_gateway_mapping_controls_custom_field_params.i18n_fail_delete_wholesale_role );
                        console.log( data );
                        console.log( '----------' );

                    }

                } )
                .fail( function ( jqXHR , textStatus , errorThrown ) {

                    toastr.error( jqXHR.responseText , wwpp_wholesale_role_payment_gateway_mapping_controls_custom_field_params.i18n_fail_delete_wholesale_role , { "closeButton" : true , "showDuration" : errorMessageDuration } );

                    console.log( wwpp_wholesale_role_payment_gateway_mapping_controls_custom_field_params.i18n_fail_delete_wholesale_role );
                    console.log( jqXHR );
                    console.log( '----------' );

                } )
                .always( function () {

                    $wr_pg_mapping.find( ".controls .dashicons" )
                        .css( "display" , "inline-block" );

                } );

        } else {

            $currentTr.removeClass( "edited" );

        }

    } );




    /*
     |------------------------------------------------------------------------------------------------------------------
     | On Page Load
     |------------------------------------------------------------------------------------------------------------------
     */

    $wwpp_wr_pg_wholesale_roles.chosen( { allow_single_deselect : true } );
    $wwpp_wr_pg_payment_gateway.chosen();

} );
