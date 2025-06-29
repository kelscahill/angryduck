/* global jQuery */
jQuery( 'document' ).ready( function( $ ) {

    /*
     |------------------------------------------------------------------------------------------------------------------
     | Initialization
     |------------------------------------------------------------------------------------------------------------------
     */

    // Initialize product add-on visibility select box ( Product Add-on plugin integration )

    $( '.wwpp-addon-group-role-visibility' ).each( function() {
        
        $( "#" + $( this ).attr( 'id' ) ).chosen( {width: '100%'} );

    } );
    
    $( 'body' ).on( 'DOMNodeInserted' , function( e ) {

        var $condition_container = $( e.target );

        if ( $condition_container.hasClass( 'wc-pao-addon' ) ) {

            $condition_container.find( '.wwpp-addon-group-role-visibility' ).each( function() {
                
                $( "#" + $( this ).attr( 'id' ) ).chosen( {width: '100%'} );

            } );

        }

        return $( this );

    } );

} );