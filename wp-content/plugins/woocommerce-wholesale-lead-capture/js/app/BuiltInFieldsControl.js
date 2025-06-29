jQuery( document ).ready( function ( $ ) {

    // change phone placeholder when format is selected
    $('#wwlc_fields_phone_mask_pattern').on( 'change', function() {

        var phoneFormat = $(this).val();
        var placeholder = ( 'No format' == phoneFormat ) ? '' : phoneFormat;

        $('#wwlc_fields_phone_field_placeholder').val( placeholder );
    });
});
