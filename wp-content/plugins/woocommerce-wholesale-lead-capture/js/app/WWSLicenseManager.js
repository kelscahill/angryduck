(function($, window){
    
    // Get params from localized script.
    var software_key = 'wwlc',
        $params      = wwlc_license_manager_params;

    // This script is not yet ready to be publicly stored in the window.
	WWS_SLMW      = window.WWS_SLMW || {};
	WWLC_WWS_SLMW = window.WWLC_WWS_SLMW || {};

    WWS_SLMW.initialized_reminder = [];

    WWS_SLMW.Helper = {
		/**
		 * Get license status i18n.
		 *
		 * @param {string} status
		 * @returns {string}
		 */
		get_license_status_i18n: function( status ) {
            var status_i18n = $params.wws_slmw_license_status_i18n; 

            switch ( status ) {
                case 'active':
                    status = status_i18n.active;
                    break;

                case 'expired':
                    status = status_i18n.expired;
                    break;

                case 'disabled':
                    status = status_i18n.disabled;
                    break;

                default:
                    status = status_i18n.invalid;
                    break;
            }

            return status;
		},
	};

    WWLC_WWS_SLMW.Notice = ( function () {

        /**
         * Notice contructor.
         *
         * @param object $notice
         */
        function Notice( $notice ) {
            // Make sure is called as a constructor.
            if ( ! ( this instanceof Notice ) ) {
                return new Notice( $notice );
            }

            if ( ! $notice.length ) {
                return false;
            }

            // Holds the jQuery instance.
            this.$el          = $notice;
            this.software_key = this.$el.data( 'software_key' );
            this.type         = this.$el.data( 'type' );

            this.setupEvents();
        }

        /**
         * WWS_License_Notice Setup events.
         */
        Notice.prototype.setupEvents = function(){
            var self = this;

            self.$el.on( 'click' , '.notice-dismiss' , function() {
                $.ajax( {
                    url: window.ajaxurl,
                    type: 'POST',
                    data: {
                        action : self.software_key + '_slmw_dismiss_license_notice',
                        nonce  : $params.wws_slmw_dismiss_license_manager_nonce,
                        type   : self.type
                    }
                } );
            });
            
            self.$el.find('form').on( 'submit' , function( e ) {
                e.preventDefault();
                
                var $form = $( this );
                var redirect_to = $form.find( 'input[name="redirect_to"]' ).val();
                
                // Redirect to license settings page and append form data.
                window.location.href = redirect_to + '&' + $form.serialize();
            });
        };

        return Notice;
    } )();

    WWLC_WWS_SLMW.Intersitial = ( function () {
        /**
         * WWS_License_Intersitial
         * 
         * @param object $interstitial
         */
        function Intersitial( $interstitial ) {
            // Make sure is called as a constructor.
            if ( ! ( this instanceof Intersitial ) ) {
                return new Intersitial( $interstitial );
            }

            if ( ! $interstitial.length ) {
                return false;
            }

            this.$el          = $interstitial;
            this.software_key = this.$el.data( 'software_key' );
            this.timer        = $params.wws_slmw_refresh_license_status_timeout;

            this.Timer();
            this.setupEvents();
        }

        /**
         * WWS_License_Intersitial Setup events.
         */
        Intersitial.prototype.setupEvents = function(){
            var self = this;

            self.$el.on( 'click' , '#wws-refresh-license-status-link' , function( e ) {
                e.preventDefault();

                // If license status countdown timer is running, then skip.
                if ( this.timer > 0 ) {
                    return;
                }
        
                // Start license status countdown for 60 seconds.
                self.timer = 60;
                self.Timer( self.timer );
        
                $.ajax( {
                    url: window.ajaxurl,
                    type: 'GET',
                    data: {
                        action : 'wws_slmw_refresh_license_status',
                        nonce  : $params.wws_slmw_refresh_license_status_nonce
                    }
                } ).done( function( response ) {
                    if ( response ) {
                        $.each( response , function( key , value ) {
                            var $plugin_license_status = self.$el.find( '.wws-license-interstitial-plugins-status .plugin-key--' + key.toLowerCase() + ' .plugin-status' );
        
                            $plugin_license_status.html( WWS_SLMW.Helper.get_license_status_i18n( value.license_status ) );
                            $plugin_license_status.removeClass( 'text-color-red text-color-green' );
        
                            if ( 'active' === value.license_status ) {
                                $plugin_license_status.addClass( 'text-color-green' );
        
                                if ( software_key === key ) {
                                    window.location.reload();
                                }
                            } else {
                                $plugin_license_status.addClass( 'text-color-red' );
                            }
                        } );
                    }
                } );
            });
            
        };

        /**
         * Interstitial timer
         */
        Intersitial.prototype.Timer = function(){
            if ( this.timer > 0 ) {
                var countdown     = this.timer;
                var $refresh_link = $( '#wws-refresh-license-status-link' );

                $refresh_link.addClass( 'disabled' );
                $refresh_link.find('.license-status-timeout').html(' (' + countdown + ')');

                var countdown_interval = setInterval(function() {
                    countdown--;
                    $refresh_link.find('.license-status-timeout').html(' (' + countdown + ')');

                    if (countdown <= 0) {
                        clearInterval(countdown_interval);
                        this.timer = 0;

                        $refresh_link.find('.license-status-timeout').html('');
                        $refresh_link.removeClass('disabled');
                    }
                }, 1000 );
            }
        };

        return Intersitial;
    } )();

    WWLC_WWS_SLMW.Reminder = ( function () {
        /**
         * WWS_License_Reminder
         */
        function Reminder() {
            // Make sure is called as a constructor.
            if ( ! ( this instanceof Reminder ) ) {
                return new Reminder();
            }

            // If reminder pointer is disabled, then skip.
            // If on the license settings page, then skip.
            if ( $params.wws_slmw_license_reminder.show === false ) {
                return false;
            }

            this.software_key = software_key;

            this.Init();

            WWS_SLMW.initialized_reminder.push( this );
        }

        /**
         * Initialize reminder pointer.
         */
        Reminder.prototype.Init = function(){
            var self = this;

            self.$elem = $('#toplevel_page_wholesale-suite .wp-menu-name').pointer({
                    pointerClass: 'wws-license-reminder',
                    content: this.Content,
                    position: {
                        edge: 'left',
                        align: '0'
                    },buttons: function( event, t ){
                        return self.createButtons( t );
                    }
                }).pointer( 'open' );
        };

        /**
         * Reminder pointer content.
         */
        Reminder.prototype.Content = function(){
            var title = '<h3 class="wws-reminder-title">' + $params.wws_slmw_license_reminder.html.title + '</h3>';
            var content = '<p class="wws-reminder-content">' + $params.wws_slmw_license_reminder.html.content + '</p>';

            return title + content;
        };

        /**
         * Create reminder pointer buttons.
         */
        Reminder.prototype.createButtons = function( t ){
            this.$buttons = $( '<div></div>', {
                'class': 'wws-reminder-buttons'
            });

            this.createEnterLicenseKeyButton( t );
            this.createCloseButton( t );

            return this.$buttons;
        };


        /**
         * Close button reminder pointer.
         * 
         * @param object t
         */
        Reminder.prototype.createCloseButton = function( t ){
            var self = this;

            $( '<a></a>', {
                'class': 'button',
                'data-software-key': self.software_key,
                'text': $params.wws_slmw_license_reminder.buttons.close_text,
                'click': function( e ){
                    e.preventDefault();

                    var data = {
                        action       : 'wws_slmw_dismiss_license_reminder_pointer',
                        nonce        : $params.wws_slmw_license_reminder.buttons.close_nonce,
                        software_key : self.software_key,
                    };
            
                    $.post( window.ajaxurl, data, function( response ) {
                        if ( response.status == 'success' ) {
                            t.element.pointer( 'close' );
                        }
                    },'json' );
                }
            }).appendTo( this.$buttons );
        };

        /**
         * Enter license key button reminder pointer.
         * 
         * @param object t
         */
        Reminder.prototype.createEnterLicenseKeyButton = function( t ){
            $( '<a></a>', {
                'class': 'button button-primary button-enter-license-key',
                'text': $params.wws_slmw_license_reminder.buttons.enter_license_key_text,
                'click': function( e ){
                    e.preventDefault();
                    t.element.pointer( 'close' );
                    window.location.href = $params.wws_slmw_license_reminder.buttons.enter_license_key_url;
                }
            }).appendTo( this.$buttons );
        }

        return Reminder;
    } )();

    /**
     * DOM ready
     */
    $( function() {
        new WWLC_WWS_SLMW.Notice( $('.wws-license-notice') );
        new WWLC_WWS_SLMW.Intersitial( $('#wws-license-interstitial') );

        if ( window.WWS_SLMW.initialized_reminder.length < 1 ) {
            new WWLC_WWS_SLMW.Reminder();
		}
    });

})( jQuery, window );