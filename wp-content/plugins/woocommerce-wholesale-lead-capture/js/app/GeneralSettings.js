jQuery( document ).ready( function ( $ ) {

    $( '.woocommerce td.wwlc_chosen_select' ).on( 'change' , 'select' , function(e) {

        var $select = $(this),
            $td     = $select.closest( 'td.wwlc_chosen_select' ),
            $input  = $td.find( 'input[type="url"]' ),
            value   = $(this).val(),
            url     = $("option:selected", $select).attr("url");
            
        if ( value === 'custom' )
            $input.prop( 'disabled' , false ).show();
        else
            $input.prop( 'disabled' , true ).hide();

        $select.siblings( "a.view-page" ).remove();
        if( value !== 'custom' && value !== '' ) {
            $select.siblings( ".description" ).before( "<a class='view-page' target='_blank' href='" + url + "'>" + GeneralSettingsVars.view_page + "</a>" );
        }

    } );

    $( '.woocommerce td.wwlc_chosen_select select' ).trigger( 'change' )

} );
