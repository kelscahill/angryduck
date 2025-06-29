jQuery(document).ready(function($){

    // Variable Declaration And Selector Caching
    var $wwlc_approve = $(".wwlc_approve"),
        $wwlc_reject = $(".wwlc_reject"),
        $wwlc_activate = $(".wwlc_activate"),
        $wwlc_deactivate = $(".wwlc_deactivate"),
        $body = $("body"),
        errorNoticeDuration = "8000",
        successNoticeDuration = "5000",
        page = "listings";

    // Events
    $wwlc_approve.click(function(){

        var $this = $(this);

        $this.attr( 'disabled' , 'disabled' );

        var userID = $.trim( $this.attr( "data-userID" ) );

        if( $this.parents( '.manage-user-controls').attr( 'data-screen-view' ) )
            page = $this.parents( '.manage-user-controls').attr( 'data-screen-view' );

        $body.find( ".loading-screen" ).show();

        wwlcBackEndAjaxServices.approveUser( userID, page )
            .done(function( data , textStatus , jqXHR ){

                if ( data.status == 'success' )
                    window.location = data.redirect_url;

                $body.find( ".loading-screen" ).hide();
            })
            .fail(function( jqXHR , textStatus , errorThrown ){

                toastr.error( jqXHR.responseText , UserListingVars.approving_failed_message , { "closeButton" : true , "showDuration" : errorNoticeDuration } );

                console.log( UserListingVars.approving_failed_message );
                console.log( jqXHR );
                console.log( '----------' );

                $this.removeAttr( 'disabled' );

                $body.find( ".loading-screen" ).hide();

            });

        return false;

    });

    $wwlc_reject.click(function(){

        var $this = $(this);

        $this.attr( 'disabled' , 'disabled' );

        var userID = $.trim( $this.attr( "data-userID" ) );

        if( $this.parents( '.manage-user-controls').attr( 'data-screen-view' ) )
            page = $this.parents( '.manage-user-controls').attr( 'data-screen-view' );

        $body.find( ".loading-screen" ).show();

        wwlcBackEndAjaxServices.rejectUser( userID, page )
            .done(function( data , textStatus , jqXHR ){

                if ( data.status == 'success' )
                    window.location = data.redirect_url;

                $body.find( ".loading-screen" ).hide();

            })
            .fail(function( jqXHR , textStatus , errorThrown ){

                toastr.error( jqXHR.responseText , UserListingVars.rejecting_failed_message , { "closeButton" : true , "showDuration" : errorNoticeDuration } );

                console.log( UserListingVars.rejecting_failed_message );
                console.log( jqXHR );
                console.log( '----------' );

                $this.removeAttr( 'disabled' );

                $body.find( ".loading-screen" ).hide();

            });

        return false;

    });

    $wwlc_activate.click(function(){

        var $this = $(this);

        $this.attr( 'disabled' , 'disabled' );

        var userID = $.trim( $this.attr( "data-userID" ) );

        if( $this.parents( '.manage-user-controls').attr( 'data-screen-view' ) )
            page = $this.parents( '.manage-user-controls').attr( 'data-screen-view' );

        $body.find( ".loading-screen" ).show();

        wwlcBackEndAjaxServices.activateUser( userID, page )
            .done(function( data , textStatus , jqXHR ){

                if ( data.status == 'success' )
                    window.location = data.redirect_url;

                $body.find( ".loading-screen" ).hide();

            })
            .fail(function( jqXHR , textStatus , errorThrown ){

                toastr.error( jqXHR.responseText , UserListingVars.activating_failed_message , { "closeButton" : true , "showDuration" : errorNoticeDuration } );

                console.log( UserListingVars.activating_failed_message );
                console.log( jqXHR );
                console.log( '----------' );

                $this.removeAttr( 'disabled' );

                $body.find( ".loading-screen" ).hide();

            });

        return false;

    });

    $wwlc_deactivate.click(function(){

        var $this = $(this);

        $this.attr( 'disabled' , 'disabled' );

        var userID = $.trim( $this.attr( "data-userID" ) );

        if( $this.parents( '.manage-user-controls').attr( 'data-screen-view' ) )
            page = $this.parents( '.manage-user-controls').attr( 'data-screen-view' );

        $body.find( ".loading-screen" ).show();

        wwlcBackEndAjaxServices.deactivateUser( userID, page )
            .done(function( data , textStatus , jqXHR ){

                if ( data.status == 'success' )
                    window.location = data.redirect_url;

                $body.find( ".loading-screen" ).hide();

            })
            .fail(function( jqXHR , textStatus , errorThrown ){

                toastr.error( jqXHR.responseText , UserListingVars.deactivating_failed_message , { "closeButton" : true , "showDuration" : errorNoticeDuration } );

                console.log( UserListingVars.deactivating_failed_message );
                console.log( jqXHR );
                console.log( '----------' );

                $this.removeAttr( 'disabled' );

                $body.find( ".loading-screen" ).hide();

            });

        return false;

    });

    // WWLC v1.5.0 - Follow User Actions when scrolling down found on user edit screen
    var element = $( ".manage-user-controls" ),
        originalY = "";

    if( element.length > 0 ){
        originalY = element.offset().top;
    }

    $( this ).on( "scroll", function( event ) {
        if( originalY > 0 ){

            var scrollTop = $( window ).scrollTop();

            element.stop(false, false).animate({
                top: scrollTop < originalY ? 0 : scrollTop - originalY,
                width: scrollTop < originalY ? "100%" : "300px",
            }, 100 );

            if( scrollTop < originalY )
                element.animate( 1000 ).css( "float", "none" );
            else
                element.animate( 1000 ).css( "float", "right" );

        }
    });

    // On Load
    $( ".wwlc_user_row_action" ).removeAttr( "disabled" , "disabled" );
    $( ".wwlc_user_row_action.hidden" ).closest( "span" ).css( "display" , "none" );

});