jQuery( document ).ready( function( $ ) {

    var $wholesale_role_tax_options = $( "#wholesale-role-tax-options" ),
        $wholesale_role = $wholesale_role_tax_options.find( "#wwpp-wholesale-roles" ),
        $tax_exempted = $wholesale_role_tax_options.find( "#wwpp-tax-exempt-wholesale-role" ),
        $tax_option_mapping = $wholesale_role_tax_options.find( "#wholesale-role-tax-options-mapping" ),
        $button_controls = $wholesale_role_tax_options.find( ".wholesale-role-tax-options-button-controls" ),
        error_message_duration = '10000',
        success_message_duration = '5000';




    /*
     |------------------------------------------------------------------------------------------------------------------
     | Functions
     |------------------------------------------------------------------------------------------------------------------
     */

    function remove_table_no_items_placeholder( $table ) {

        $table.find( "tbody" ).find( ".no-items" ).remove();

    }

    function reset_table_row_styling() {

        $tax_option_mapping
            .find( "tbody" )
            .find( "tr" )
            .each( function( index ) {

                index++; // we do this coz index is zero base

                if (index % 2 == 0) {
                    // even
                    $(this)
                        .removeClass( "odd" )
                        .removeClass( "alternate" )
                        .addClass( "even" );

                } else {
                    // odd
                    $(this)
                        .removeClass( "even" )
                        .addClass( "odd" )
                        .addClass( "alternate" );

                }

            } );

    }

    function reset_fields() {

        $wholesale_role.removeAttr( "disabled" ).find( "option:first-child" ).attr( "selected" , "selected" ).end().trigger( "change" ).trigger( "chosen:updated" );
        $tax_exempted.find( "option:first-child" ).attr( "selected" , "selected" );

    }

    function validate_fields( wholesale_role ) {

        var error_fields = [];

        if ( wholesale_role == "" )
            error_fields.push( wp.i18n.__( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' ) );

        return error_fields;

    }




    /*
     |------------------------------------------------------------------------------------------------------------------
     | Events
     |------------------------------------------------------------------------------------------------------------------
     */

    $wholesale_role_tax_options.find( "#add-mapping" ).click( function() {

        var $this = $( this ),
            wholesale_role = $.trim( $wholesale_role.val() ),
            wholesale_role_txt = $.trim( $wholesale_role.find( "option:selected" ).text() ),
            tax_exempted_txt = $.trim( $tax_exempted.find( "option:selected" ).text() ),
            error_fields;

        $this.attr( 'disabled' , 'disabled' );
        $button_controls.addClass( 'processing' );

        error_fields = validate_fields( wholesale_role );

        if ( error_fields.length > 0 ) {

            var msg = wp.i18n.__( 'Please specify values for the following field/s:', 'woocommerce-wholesale-prices-premium' ) + "<br/><ul>";

            for ( var i = 0 ; i < error_fields.length ; i++ )
                msg += "<li>" + error_fields[ i ] + "</li>";

            msg += "</ul>";

            toastr.error( msg , wp.i18n.__( 'Form Error', 'woocommerce-wholesale-prices-premium' ) , { "closeButton" : true , "showDuration" : error_message_duration } );

            $this.removeAttr( 'disabled' );
            $button_controls.removeClass( 'processing' );

            return false;

        }

        var mapping = {
            'wholesale_role'    :   wholesale_role,
            'tax_exempted'      :   $tax_exempted.val()
        }

        wwppBackendAjaxServices.add_wholesale_role_tax_option( mapping )
            .done( function( data , textStatus , jqXHR ) {

                if ( data.status == 'success' ) {

                    toastr.success( '' , wp.i18n.__( 'Successfully Added Wholesale Role Tax Option Mapping', 'woocommerce-wholesale-prices-premium' ) , { "closeButton" : true , "showDuration" : success_message_duration } );

                    remove_table_no_items_placeholder( $tax_option_mapping );

                    var tr_class = "";

                    if( $tax_option_mapping.find( "tr" ).length % 2 == 0 ) // currently even, next add (our add) would make it odd
                        tr_class = "odd alternate";
                    else // currently odd, next add (our add) would make it even
                        tr_class = "even";

                    $tax_option_mapping
                        .find( "tbody" )
                        .append(
                            '<tr class="' + tr_class + ' edited">' +
                                '<td class="meta hidden">' +
                                    '<span class="wholesale-role">' + mapping.wholesale_role + '</span>' +
                                    '<span class="tax-exempted">' + mapping.tax_exempted + '</span>' +
                                '</td>' +
                                '<td class="wholesale-role-name">' + wholesale_role_txt + '</td>' +
                                '<td class="tax-exempted-text">' + tax_exempted_txt + '</td>' +
                                '<td class="controls">' +
                                    '<a class="edit dashicons dashicons-edit"></a>' +
                                    '<a class="delete dashicons dashicons-no"></a>' +
                                '</td>' +
                            '</tr>'
                        );

                    reset_fields();

                    // Remove edited class to the recently added user field
                    setTimeout( function () {

                        $tax_option_mapping
                            .find( "tr.edited" )
                            .removeClass( "edited" );

                    } , 500 );

                } else {

                    toastr.error( data.error_message , wp.i18n.__( 'Failed To Add New Wholesale Role Tax Option Mapping', 'woocommerce-wholesale-prices-premium' ) , { "closeButton" : true , "showDuration" : error_message_duration } );

                    console.log( wp.i18n.__( 'Failed To Add New Wholesale Role Tax Option Mapping', 'woocommerce-wholesale-prices-premium' ) );
                    console.log( data );
                    console.log( '----------' );

                }

            } )
            .fail( function( jqXHR , textStatus , errorThrown ) {

                toastr.error( jqXHR.responseText , wp.i18n.__( 'Failed To Add New Wholesale Role Tax Option Mapping', 'woocommerce-wholesale-prices-premium' ) , { "closeButton" : true , "showDuration" : error_message_duration } );

                console.log( wp.i18n.__( 'Failed To Add New Wholesale Role Tax Option Mapping', 'woocommerce-wholesale-prices-premium' ) );
                console.log( jqXHR );
                console.log( '----------' );

            } )
            .always( function() {

                $this.removeAttr( 'disabled' );
                $button_controls.removeClass( 'processing' );

            } );

    } );

    $wholesale_role_tax_options.find( "#save-mapping" ).click( function() {

        var $this = $( this ),
            wholesale_role = $.trim( $wholesale_role.val() ),
            wholesale_role_txt = $.trim( $wholesale_role.find( "option:selected" ).text() ),
            tax_exempted_txt = $.trim( $tax_exempted.find( "option:selected" ).text() ),
            error_fields;

        $this.attr( 'disabled' , 'disabled' );
        $wholesale_role_tax_options.find( '#cancel-edit-mapping' ).attr( 'disabled' , 'disabled' );
        $button_controls.addClass( 'processing' );

        error_fields = validate_fields( wholesale_role );

        if ( error_fields.length > 0 ) {

            var msg = wp.i18n.__( 'Please specify values for the following field/s:', 'woocommerce-wholesale-prices-premium' ) + "<br/><ul>";

            for ( var i = 0 ; i < error_fields.length ; i++ )
                msg += "<li>" + error_fields[ i ] + "</li>";

            msg += "</ul>";

            toastr.error( msg , wp.i18n.__( 'Form Error', 'woocommerce-wholesale-prices-premium' ) , { "closeButton" : true , "showDuration" : errorMessageDuration } );

            $this.removeAttr( 'disabled' );
            $wholesale_role_tax_options.find( '#cancel-edit-mapping' ).removeAttr( 'disabled' );
            $button_controls.removeClass( 'processing' );

            return false;

        }

        var mapping = {
            'wholesale_role'    :   wholesale_role,
            'tax_exempted'      :   $tax_exempted.val()
        }

        wwppBackendAjaxServices.edit_wholesale_role_tax_option( mapping )
            .done( function ( data , textStatus , jqXHR ) {

                if ( data.status == 'success' ) {

                    $tax_option_mapping.find( "tr.edited" )
                        .find( ".meta" )
                            .find( ".wholesale-role" ).text( mapping.wholesale_role ).end()
                            .find( ".tax-exempted" ).text( mapping.tax_exempted ).end()
                            .end()
                        .find( ".wholesale-role-name" ).text( wholesale_role_txt ).end()
                        .find( ".tax-exempted-text" ).text( tax_exempted_txt );

                    $tax_option_mapping.find( "tr .controls .dashicons" )
                        .css( "display" , "inline-block" );

                    reset_fields();

                    // Remove edited class to the recently added user field
                    setTimeout( function () {
                        $tax_option_mapping
                            .find( "tr.edited" )
                            .removeClass( "edited" );
                    } , 500 );

                    $button_controls
                        .removeClass( "edit-mode" )
                        .addClass( "add-mode" );

                    toastr.success( '' , wp.i18n.__( 'Successfully Updated Wholesale Role Tax Option Mapping', 'woocommerce-wholesale-prices-premium' ) , { "closeButton" : true , "showDuration" : success_message_duration } );

                } else {

                    toastr.error( data.error_message , wp.i18n.__( 'Failed To Update Wholesale Role Tax Option Mapping', 'woocommerce-wholesale-prices-premium' ) , { "closeButton" : true , "showDuration" : error_message_duration } );

                    console.log( wp.i18n.__( 'Failed To Update Wholesale Role Tax Option Mapping', 'woocommerce-wholesale-prices-premium' ) );
                    console.log( data );
                    console.log( '----------' );

                }

            } )
            .fail( function ( jqXHR , textStatus , errorThrown ) {

                toastr.error( jqXHR.responseText , wp.i18n.__( 'Failed To Update Wholesale Role Tax Option Mapping', 'woocommerce-wholesale-prices-premium' ) , { "closeButton" : true , "showDuration" : error_message_duration } );

                console.log( wp.i18n.__( 'Failed To Update Wholesale Role Tax Option Mapping', 'woocommerce-wholesale-prices-premium' ) );
                console.log( data );
                console.log( '----------' );

            } )
            .always( function () {

                $this.removeAttr( 'disabled' );
                $wholesale_role_tax_options.find( '#cancel-edit-mapping' ).removeAttr( 'disabled' );
                $button_controls.removeClass( 'processing' );

            } );

    } );

    $wholesale_role_tax_options.find( "#cancel-edit-mapping" ).click( function() {

        reset_fields();

        $button_controls
            .removeClass( "edit-mode" )
            .addClass( "add-mode" );

        $tax_option_mapping
            .find( "tbody tr" )
            .removeClass( "edited" )
            .find( ".controls .dashicons" )
            .css( "display" , "inline-block" );

    } );

    $( "body" ).delegate( "#wholesale-role-tax-options-mapping .edit" , "click" , function () {

        var $this = $( this ),
            $current_tr = $this.closest( 'tr' );

        $current_tr.addClass( "edited" );
        $tax_option_mapping.find( ".controls .dashicons" ).css( "display" , "none" );

        var curr_mapping = {
            "wholesale_role"    :   $.trim( $current_tr.find( ".meta" ).find( ".wholesale-role" ).text() ),
            "tax_exempted"      :   $.trim( $current_tr.find( ".meta" ).find( ".tax-exempted" ).text() )
        };

        $wholesale_role.val( curr_mapping.wholesale_role ).attr( 'disabled' , 'disabled' ).trigger( "change" ).trigger( "chosen:updated" );
        $tax_exempted.val( curr_mapping.tax_exempted );

        $button_controls
            .removeClass( "add-mode" )
            .addClass( "edit-mode" );

    } );

    $( "body" ).delegate( "#wholesale-role-tax-options-mapping .delete" , "click" , function() {

        var $this = $( this ),
            $current_tr = $this.closest( 'tr' );

        $current_tr.addClass( "edited" );

        if ( confirm( wp.i18n.__( 'Clicking OK will remove the current wholesale role tax option mapping', 'woocommerce-wholesale-prices-premium' ) ) ) {

            var wholesale_role = $.trim( $current_tr.find( ".meta" ).find( ".wholesale-role" ).text() );

            $tax_option_mapping.find( ".controls .dashicons" )
                .css( "display" , "none" );

            wwppBackendAjaxServices.delete_wholesale_role_tax_option( wholesale_role )
                .done( function ( data , textStatus , jqXHR ) {

                    if ( data.status == 'success' ) {

                        $current_tr.fadeOut( "fast" , function () {

                            $current_tr.remove();

                            reset_table_row_styling();

                            // If no more item then append the empty table placeholder
                            if ( $tax_option_mapping.find( "tbody" ).find( "tr" ).length <= 0 ) {

                                $tax_option_mapping
                                    .find( "tbody" )
                                    .html(  '<tr class="no-items">' +
                                                '<td class="colspanchange" colspan="3">' + wp.i18n.__( 'No Mappings Found', 'woocommerce-wholesale-prices-premium' ) + '</td>' +
                                            '</tr>' );

                            }

                        } );

                        toastr.success( '' , wp.i18n.__( 'Successfully Deleted Wholesale Role Tax Option Mapping', 'woocommerce-wholesale-prices-premium' ) , { "closeButton" : true , "showDuration" : success_message_duration } );

                    } else {

                        toastr.error( data.error_message , wp.i18n.__( 'Failed To Delete Wholesale Role Tax Option Mapping', 'woocommerce-wholesale-prices-premium' ) , { "closeButton" : true , "showDuration" : error_message_duration } );

                        console.log( wp.i18n.__( 'Failed To Delete Wholesale Role Tax Option Mapping', 'woocommerce-wholesale-prices-premium' ) );
                        console.log( data );
                        console.log( '----------' );

                    }

                } )
                .fail( function ( jqXHR , textStatus , errorThrown ) {

                    toastr.error( jqXHR.responseText , data.error_message , wp.i18n.__( 'Failed To Delete Wholesale Role Tax Option Mapping', 'woocommerce-wholesale-prices-premium' ) , { "closeButton" : true , "showDuration" : error_message_duration } );

                    console.log( data.error_message , wp.i18n.__( 'Failed To Delete Wholesale Role Tax Option Mapping', 'woocommerce-wholesale-prices-premium' ) );
                    console.log( jqXHR );
                    console.log( '----------' );

                } )
                .always( function () {

                    $tax_option_mapping.find( ".controls .dashicons" )
                        .css( "display" , "inline-block" );

                } );

        } else {

            $current_tr.removeClass( "edited" );

        }

    } );




    /*
     |------------------------------------------------------------------------------------------------------------------
     | Page Load
     |------------------------------------------------------------------------------------------------------------------
     */

    $wholesale_role.chosen( { allow_single_deselect : true , width : '300px' } );

} );