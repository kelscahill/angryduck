jQuery( document ).ready( function ( $ ) {

    var $params       = wwpp_wholesale_sale_prices_params,
        $product_type = $('#product-type').val();

    /**
     * Insert an entry to woocommerce_admin local variable
     * 
     * @since 1.30.1
     */ 
     Object.assign(woocommerce_admin,{
        'i18n_wholesale_price_is_empty_error'                 : $params.i18n_wholesale_price_is_empty_error,
        'i18n_wholesale_discount_is_empty_error'              : $params.i18n_wholesale_discount_is_empty_error,
        'i18n_wholesale_sale_less_than_wholesale_price_error' : $params.i18n_wholesale_sale_less_than_wholesale_price_error,
        'i18n_sale_discount_greater_than_100_percent_error'   : $params.i18n_sale_discount_greater_than_100_percent_error,
        'i18n_sale_discount_less_than_0_percent_error'        : $params.i18n_sale_discount_less_than_0_percent_error
    });

    /**
     * Set the sale discount field and the wholesale sale price field to appropriate attribute on when the discount type changed.
     * 
     * @since 1.30.1
     */
    $( 'body' ).on( 'change', '.wholesale_discount_type', function( e ) {
        var $wrap                  = $( this ).closest( 'div' ),
            $wholesale_sale_price  = $wrap.find( '.wholesale_sale_price' ),
            $sale_discount_field   = $wrap.find( '.wholesale_sale_discount' ).closest( '.form-field' ),
            sale_discount          = $sale_discount_field.find( '.wholesale_sale_discount' ).val(),
            selected_discount_type = $( this ).val(),
            regular_price          = $( '#_regular_price' ).val(),
            discount               = $wrap.find( '.wholesale_discount' ).val(),
            discounted_price       = 0;

        if ( selected_discount_type === 'percentage' ) {
            $sale_discount_field.show();
            $wholesale_sale_price.attr( 'readonly', true );
            
            // Replace it with a period, If the decimal separator is not a period.
            regular_price = ( regular_price !== "" && $params.decimal_sep !== '.' ) ? regular_price.toString().replace( $params.decimal_sep,'.' ) : regular_price;
            discount      = ( discount !== "" && $params.decimal_sep !== '.' ) ? discount.toString().replace( $params.decimal_sep,'.' ) : discount;
            sale_discount = ( sale_discount !== "" && $params.decimal_sep !== '.' ) ? sale_discount.toString().replace( $params.decimal_sep,'.' ) : sale_discount;

            if ( sale_discount != "" && sale_discount > 0 ) {
                discounted_price = calculate_discounted_sale_price( regular_price, discount, sale_discount );
                $wholesale_sale_price.val( discounted_price );
            } else {
                $wholesale_sale_price.val( null );
            }
        } else {
            $sale_discount_field.hide();
            $wholesale_sale_price.attr( 'readonly', false );

            if( $wholesale_sale_price.attr( 'data-fixed_sale_price' ) ) {
                $wholesale_sale_price.val( $wholesale_sale_price.attr( 'data-fixed_sale_price' ) );
            } else {
                $wholesale_sale_price.val( null );
            }
        }
    });

    /**
     * Show/hide the schedule field and the shcedule link on load based on the value on simple product.
     * 
     * @since 1.30.1
     */
    $( '.wholesale_sale_price_dates_fields' ).each( function () {
		var $these_sale_dates = $( this );
		var sale_schedule_set = false;
        
        $these_sale_dates.find( 'input' ).each( function () {
            if ( '' !== $( this ).val() ) {
                sale_schedule_set = true;
            }
        } );

        if ( sale_schedule_set ) {
            $these_sale_dates.prev( 'p[class*="_wholesale_sale_price"]' ).find( '.wholesale_sale_schedule' ).hide();
            $these_sale_dates.show();
        } else {
            $these_sale_dates.prev( 'p[class*="_wholesale_sale_price"]' ).find( '.wholesale_sale_schedule' ).show();
            $these_sale_dates.hide();
        }
	} );

    /**
     * Show the schedule field and hide the shcedule link when user click the shedule link.
     * 
     * @since 1.30.1
     */
    $( '#woocommerce-product-data' ).on(
		'click',
		'.wholesale_sale_schedule',
		function () {
            var $dates_fields = $( this ).closest( 'p' ).next(' .wholesale_sale_price_dates_fields');

            if( $product_type !== 'variable' ) {
                $dates_fields.find( '.cancel_wholesale_sale_schedule' ).show();
            } else {
                $( this ).closest( 'label' ).find( '.cancel_wholesale_sale_schedule' ).show();
            }

            $dates_fields.show();
            $( this ).hide();
            
			return false;
		}
	);

    /**
     * Hide the schedule field and show the shcedule link when user click the cancel shedule link.
     * 
     * @since 1.30.1
     */
    $( '#woocommerce-product-data' ).on(
		'click',
		'.cancel_wholesale_sale_schedule',
		function () {
            if( $product_type !== 'variable' ) {
                var $dates_fields      = $( this ).closest( 'p' );
                var $sale_prices_field = $dates_fields.prev( 'p[class*="_wholesale_sale_price"]' );
                var $datepicker        = $dates_fields.find( 'input' );
                
                $sale_prices_field.find( '.wholesale_sale_schedule' ).show();
            } else {
                var $sale_prices_field = $( this ).closest( 'p' );
                var $dates_fields      = $sale_prices_field.next( '.wholesale_sale_price_dates_fields' );
                var $datepicker        = $dates_fields.find( 'input' );
                
                $( this ).closest( 'label' ).find( '.wholesale_sale_schedule' ).show();
            }
            
            $( this ).hide();
            $dates_fields.hide();
            $datepicker.val( '' );
            $datepicker.trigger( 'change' );
			
            return false;
		}
	);

    /**
     * Initialize the date picker input fields for simple product.
     * 
     * @since 1.30.1
     */
    $( '.wholesale_sale_price_dates_fields' ).each( function () {
		$( this )
			.find( 'input' )
			.datepicker( {
				defaultDate: '',
				dateFormat: 'yy-mm-dd',
				numberOfMonths: 1,
				showButtonPanel: true,
				onSelect: function () {
					wholesale_sale_date_picker_select( $( this ) );
				},
			} );
		$( this )
			.find( 'input' )
			.each( function () {
				wholesale_sale_date_picker_select( $( this ) );
			} );
	} );

    /**
     * Calculate the percentage wholesale sale price every time the user change the value of the discount input field. 
     * 
     * @since 1.30.1
     */
    $( 'body' ).on( 'keyup', '.wholesale_sale_discount', function( e ) {
        var $wrap                           = $( this ).closest( 'div' ),
            $wholesale_sale_price_field     = $wrap.find( '.wholesale_sale_price' ),
            regular_price                   = $product_type !== 'variable' ? $( '#_regular_price' ).val() : $( this ).closest( '.woocommerce_variable_attributes' ).find( 'input[id^="variable_regular_price_"]' ).val(),
            discount                        = $wrap.find( '.wholesale_discount' ).val(),
            sale_discount                   = $( this ).val(),
            discounted_sale_price           = 0;

        // Replace it with a period, If the decimal separator is not a period. 
        regular_price = ( regular_price !== "" && $params.decimal_sep !== '.' ) ? regular_price.toString().replace( $params.decimal_sep,'.' ) : regular_price;
        discount      = ( discount !== "" && $params.decimal_sep !== '.' ) ? discount.toString().replace( $params.decimal_sep,'.' ) : discount;
        sale_discount = ( sale_discount !== "" && $params.decimal_sep !== '.' ) ? sale_discount.toString().replace( $params.decimal_sep,'.' ) : sale_discount;

        // Return if regular price or wholesale discount or wholesale sale discount is not a number.
        if ( isNaN( regular_price ) || isNaN( discount ) || isNaN( sale_discount ) ) return;

        sale_discount = parseFloat( sale_discount );

        if ( discount === '' ) {
            $( document.body ).triggerHandler( 'wc_add_error_tip', [ $( this ), 'i18n_wholesale_discount_is_empty_error' ] );
            
            $( this ).val( '' );
            $wholesale_sale_price_field.val( '' );
        } else if ( sale_discount > 100 ) {
            $( document.body ).triggerHandler( 'wc_add_error_tip', [ $( this ), 'i18n_discount_greater_than_100_percent_error' ] );

            $( this ).val( '' );
            $wholesale_sale_price_field.val( '' );
        } else if ( sale_discount == 100 ) {
            $( document.body ).triggerHandler( 'wc_remove_error_tip', [ $( this ), 'i18n_discount_greater_than_100_percent_error' ] );
            $( document.body ).triggerHandler( 'wc_remove_error_tip', [ $( this ), 'i18n_discount_less_than_0_percent_error' ] );
            $( document.body ).triggerHandler( 'wc_remove_error_tip', [ $( this ), 'i18n_wholesale_discount_is_empty_error' ] );

            $wholesale_sale_price_field.val( 0 );
        } else if ( sale_discount < 0 ) {
            $( document.body ).triggerHandler( 'wc_add_error_tip', [ $( this ), 'i18n_discount_less_than_0_percent_error' ] );

            $( this ).val( '' );
            $wholesale_sale_price_field.val( '' );

        } else if ( sale_discount > 0 && ( regular_price !== "" && discount !== "" ) ) {
            $( document.body ).triggerHandler( 'wc_remove_error_tip', [ $( this ), 'i18n_discount_greater_than_100_percent_error' ] );
            $( document.body ).triggerHandler( 'wc_remove_error_tip', [ $( this ), 'i18n_discount_less_than_0_percent_error' ] );
            $( document.body ).triggerHandler( 'wc_remove_error_tip', [ $( this ), 'i18n_wholesale_discount_is_empty_error' ] );

            discounted_sale_price = calculate_discounted_sale_price( regular_price, discount, sale_discount );

            $wholesale_sale_price_field.val(discounted_sale_price);
        } else if ( sale_discount > 0 && ( regular_price === "" || discount === "" ) ) {
            $( document.body ).triggerHandler( 'wc_remove_error_tip', [ $( this ), 'i18n_discount_greater_than_100_percent_error' ] );
            $( document.body ).triggerHandler( 'wc_remove_error_tip', [ $( this ), 'i18n_discount_less_than_0_percent_error' ] );
            $( document.body ).triggerHandler( 'wc_remove_error_tip', [ $( this ), 'i18n_wholesale_discount_is_empty_error' ] );

            $wholesale_sale_price_field.val( '' );
        } else {
            $( document.body ).triggerHandler( 'wc_remove_error_tip', [ $( this ), 'i18n_discount_less_than_0_percent_error' ] );
            $( document.body ).triggerHandler( 'wc_remove_error_tip', [ $( this ), 'i18n_wholesale_discount_is_empty_error' ] );
            
            $( this ).val( '' );
            $wholesale_sale_price_field.val( '' );
        }
        
    } );

    /**
     * Prevent user to input wholesale sale price value more than the wholesale price.
     * 
     * @since 1.30.1
     */
    $( 'body' ).on( 'keyup', '.wholesale_sale_price', function( e ) {
        var $wrap                = $( this ).closest( 'div' ),
            wholesale_sale_price = $( this ).val(),
            wholesale_price      = $wrap.find('.wholesale_price').val();

        wholesale_sale_price = ( wholesale_sale_price !== "" && $params.decimal_sep !== '.' ) ? wholesale_sale_price.toString().replace( $params.decimal_sep,'.' ) : wholesale_sale_price;
        wholesale_price      = ( wholesale_price !== "" && $params.decimal_sep !== '.' ) ? wholesale_price.toString().replace( $params.decimal_sep,'.' ) : wholesale_price;

        if ( '' !== wholesale_price && parseFloat( wholesale_sale_price ) >= parseFloat( wholesale_price ) ) {
            $( document.body ).triggerHandler( 'wc_add_error_tip', [ $( this ), 'i18n_wholesale_sale_less_than_wholesale_price_error' ] );
            $( this ).val( '' );
        } else if ( '' === wholesale_price ) {
            $( document.body ).triggerHandler( 'wc_add_error_tip', [ $( this ), 'i18n_wholesale_price_is_empty_error' ] );
            $( this ).val( '' );
        } else {
            $( document.body ).triggerHandler( 'wc_remove_error_tip', [ $( this ), 'i18n_wholesale_sale_less_than_wholesale_price_error' ] );
            $( document.body ).triggerHandler( 'wc_remove_error_tip', [ $( this ), 'i18n_wholesale_price_is_empty_error' ] );
        }

    } );
    
    /**
     * Prevent user to input wholesale price value less than the wholesale sale price.
     * 
     * @since 1.30.1
     */
    $( 'body' ).on( 'blur', '.wholesale_price', function( e ) {
        var $wrap                 = $( this ).closest( 'div' ),
            $discount_type        = $wrap.find( '.wholesale_discount_type' ),
            $wholesale_sale_price = $wrap.find( '.wholesale_sale_price' ),
            wholesale_sale_price  = $wholesale_sale_price.val(),
            wholesale_price       = $( this ).val();

        if ( $discount_type.val() === 'fixed' && parseFloat( wholesale_sale_price ) >= parseFloat( wholesale_price ) ) {
            $( document.body ).triggerHandler( 'wc_add_error_tip', [ $wholesale_sale_price, 'i18n_wholesale_sale_less_than_wholesale_price_error' ] );
            $wholesale_sale_price.val( '' );
        } else {
            $( document.body ).triggerHandler( 'wc_remove_error_tip', [ $wholesale_sale_price, 'i18n_wholesale_sale_less_than_wholesale_price_error' ] );
        }
    } );

    /**
     * Recalculate the wholesale sale price percentage on wholsale discount value change.
     * 
     * @since 1.30.1
     */
    $( 'body' ).on( 'keyup', '.wholesale_discount', function() {
        recalculate_discounted_sale_price( $( this ) );
    } );

    /**
     * Recalculate the wholesale sale price percentage on regular price value change.
     * 
     * @since 1.30.1
     */
    $( 'body' ).on( 'keyup', '#_regular_price', function() {
        $( '.wholesale_sale_price' ).each( function () {
            recalculate_discounted_sale_price( $( this ) );
        } );
    } );

    /**
     * Listen to the change event on the product-type dropdown.
     * For variable product, the event used is 'woocommerce_variations_loaded'.
     * 
     * @since 1.30.1
     */
    $( 'body' ).on( 'change', '#product-type', function() {
        $product_type = $( this ).val();

        if ( 'variable' !== $product_type ) {
            process_simple_products();
        }

    } );

    /**
     * Woocommerce event that trigger after successfully load variations via Ajax.
     * 
     * @since 1.30.1
     */
    $( '#woocommerce-product-data' ).on( 'woocommerce_variations_loaded woocommerce_variations_added', function() {
        var $wrapper            = $( '#woocommerce-product-data' );
        var $variations_wrapper = $( '.woocommerce_variation' );

        // Hide/show appropriate wholesale sale price field for product variations.
        $( '.wholesale_sale_price', $variations_wrapper ).each( function () {
            var $wrap                = $( this ).closest( 'div' ),
                $sale_discount_field = $wrap.find( '.wholesale_sale_discount' ).closest( '.form-field' ),
                $discount_type       = $wrap.find( '.wholesale_discount_type' );

            if ( $discount_type !== null ) {
                if ( $discount_type.val() === 'percentage' ) {
                    $( this ).attr('readonly', true);
                    $sale_discount_field.show();
                } else {
                    $( this ).attr('readonly', false);
                    $sale_discount_field.hide();
                }
            }
        } );

        // Initialize datepicker fields for product variations.
        $( '.wholesale_sale_price_dates_fields', $wrapper )
            .find( 'input' )
            .datepicker({
                defaultDate:     '',
                dateFormat:      'yy-mm-dd',
                numberOfMonths:  1,
                showButtonPanel: true,
                onSelect:        function() {
                    var option = $( this ).is( '.wholesale_sale_price_dates_from' ) ? 'minDate' : 'maxDate',
                        dates  = $( this ).closest( '.wholesale_sale_price_dates_fields' ).find( 'input' ),
                        date   = $( this ).datepicker( 'getDate' );

                    dates.not( this ).datepicker( 'option', option, date );
                    $( this ).trigger( 'change' );
                }
            } );
        
        // Hide/show appropriate wholesale sale schedule field for product variations.
        $( '.wholesale_sale_price_dates_fields', $variations_wrapper ).each( function () {
            var $these_sale_dates = $( this );
            var sale_schedule_set = false;

            $these_sale_dates.find( 'input' ).each( function () {
                if ( '' !== $( this ).val() ) {
                    sale_schedule_set = true;
                }
            } );

            if ( sale_schedule_set ) {
                $these_sale_dates.prev( 'p[class*="_wholesale_sale_price"]' ).find( '.cancel_wholesale_sale_schedule' ).show();
                $these_sale_dates.prev( 'p[class*="_wholesale_sale_price"]' ).find( '.wholesale_sale_schedule' ).hide();
                $these_sale_dates.show();
            } else {
                $these_sale_dates.prev( 'p[class*="_wholesale_sale_price"]' ).find( '.wholesale_sale_schedule' ).show();
                $these_sale_dates.hide();
            }

        } );
    } );

    /**
     * Recalculate the value of the wholesale sale price if the discount type is set to percentage.
     * 
     * @since 1.30.1
     * @param Object $object The object of the element
     */
    function recalculate_discounted_sale_price( $object ) {
        var $wrap          = $object.closest( 'div' ),
            $discount_type = $wrap.find( '.wholesale_discount_type' )
            $sale_discount = $wrap.find( '.wholesale_sale_discount' );
            sale_discount  = $wrap.find( '.wholesale_sale_discount' ).val();

        if ( $discount_type.val() === 'percentage' && sale_discount !== '' ) {
            $sale_discount.trigger( "keyup" );
        }
    }

    /**
     * Calculate percentage wholesale sale price.
     * 
     * @since 1.30.1
     * @param Float price         Product regular price
     * @param Float discount      Wholesale price percentage discount
     * @param Float sale_discount Wholesale price percentage discount
     * 
     * @returns Float
     */
    function calculate_discounted_sale_price( price, discount, sale_discount ) {
        var discounted_sale_price = null;

        //  Calculate the discounted wholesale price of regular price
        var wholesale_price =  price - ( discount / 100 ) * price ;

        // Check if the wholesale_price value is less than or equal to 0 or wholesale_price isNaN (Not a Number) is true, then set the wholesale_price value to null. 
        wholesale_price =  wholesale_price > 0 || ! isNaN(wholesale_price) ? wholesale_price : null ;

        if ( wholesale_price !== null ) {
            // Calculate the discounted sale price of wholesale price
            discounted_sale_price = wholesale_price - ( sale_discount / 100 ) * wholesale_price;

            // Format the discounted_sale_price based on the parameters given using the currency.js
            discounted_sale_price = currency( discounted_sale_price, {precision: $params.calculation_decimal_places, decimal: $params.decimal_sep, separator:'', pattern: '#'} ).format();
            
            // Remove trailling zeros
            discounted_sale_price = remove_trailing_zeros( discounted_sale_price );
        }

        return (discounted_sale_price === null) ? null : discounted_sale_price;
    }

    /**
     * Remove all trailing zeros after decimal.
     * 
     * @since 1.30.1
     * @param Float price - The price of the product/item
     * 
     * @returns Float
     */
    function remove_trailing_zeros( price ) {

        try {

            if ( price !== "" && price !== null ) {
                // Convert if has decimal separator of ',' to '.'.
                price = $params.decimal_sep !== '.' ? price.toString().replace( $params.decimal_sep, "." ) : price;

                // Remove all trailing zeros.
                price = parseFloat( price );

                // Check if the decimal separator is comma (','), then change it back to the decimal separator that is set in woo settings.
                if( ! isNaN( price ) ) {
                    price = $params.decimal_sep !== '.' ? price.toString().replace( '.', $params.decimal_sep ) : price;
                } else{
                    price = '';
                }
            }

            return price;

        } catch ( e ) {
            console.error( e.message );
        }

    }

    /**
     * Process the date picker fields on select.
     * 
     * @since 1.30.1
     */
    function wholesale_sale_date_picker_select( datepicker ) {
		var option = $( datepicker ).next().is( '.hasDatepicker' )
				? 'minDate'
				: 'maxDate',
			otherDateField =
				'minDate' === option
					? $( datepicker ).next()
					: $( datepicker ).prev(),
			date = $( datepicker ).datepicker( 'getDate' );

		$( otherDateField ).datepicker( 'option', option, date );
		$( datepicker ).trigger( 'change' );
	}

    /**
     * Process Simple Products for wholesale percentage discount
     * 
     * This function will process the simple products, if the discount type is Percentage or Fixed price.
     * 
     * @since 1.30.1
     */
    function process_simple_products() {
        $( '.wholesale_sale_price' ).each( function () {
            var $wrap                = $( this ).closest( 'div' ),
                $sale_discount_field = $wrap.find( '.wholesale_sale_discount' ).closest( '.form-field' ),
                $discount_type       = $wrap.find( '.wholesale_discount_type' );

            if ( $discount_type !== null ) {
                if ( $discount_type.val() === 'percentage' ) {
                    $( this ).attr('readonly', true);
                    $sale_discount_field.show();
                } else {
                    $( this ).attr('readonly', false);
                    $sale_discount_field.hide();
                }
            }
        });
    }

    /**
     * Process the wholesale sale price fields on page initialization for simple product.
     * 
     * @since 1.30.1
     * 
     */
    function init() {
        if ( 'variable' !== $product_type ) {
            process_simple_products();
        }
    }
    
    // Initialize event(s).
    init();
} );