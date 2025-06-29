document.addEventListener('DOMContentLoaded', function() {
    
    // Select the element(s) with the class you want to observe
    const elementsToObserve = document.querySelectorAll('.composite_price');

    // Create a new MutationObserver object
    var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function (mutation) {
            
            // Get the composite options
            let wwpp_wholesale_prices = [];
            let wwpp_wholesale_parent_price = 0;
            let wc_currency_symbol = '';
            let wholesale_price_total_title_text = '';
            let wwpp_wholesale_parent_title = '';
            let wholesale_user = false;
            const component_options = document.querySelectorAll('.component_options');
            component_options.forEach(function (component_option) {
                const selected_option_data = jQuery(component_option).find('.component_options_select').val();
                const options_data = jQuery(component_option).attr('data-options_data');
                const options_data_json = JSON.parse(options_data);
                const selected_options_data = options_data_json.filter(function (option) {
                    return selected_option_data === option.option_id;
                });
                const selected_object = selected_options_data.slice(0, 1).shift();

                // Get the parent composite price.
                if (selected_object.wwpp_data.hasOwnProperty('wholesale_parent_price')) { 
                    wholesale_user = true;
                    wwpp_wholesale_parent_title = selected_object.wwpp_data.wholesale_parent_title;
                    wwpp_wholesale_parent_price = selected_object.wwpp_data.wholesale_parent_price;
                }

                if (selected_object.wwpp_data.hasOwnProperty('priced_individually') && selected_object.wwpp_data.priced_individually) {
                    if (selected_object.wwpp_data.hasOwnProperty('wholesale_variations')) {
                        const selected_variable = [];
                        const composite_component_variables = component_option.closest('.composite_component').querySelectorAll('.attribute_options');
                        composite_component_variables.forEach(function (component_option) {
                            const selected_variable_option_data = jQuery(component_option).find('select').val();
                            selected_variable.push(selected_variable_option_data);
                        });

                        const wholesale_variations_json = selected_object.wwpp_data.wholesale_variations;
                        
                        wc_currency_symbol = selected_object.wwpp_data.wc_active_currency;
                        wholesale_price_total_title_text = selected_object.wwpp_data.wholesale_price_total_title_text;
                        
                        wholesale_variations_json.forEach(function (wholesale_variation) {
                            if (wholesale_variation.hasOwnProperty('wholesale_price_raw')) {
                                const attributes = wholesale_variation.variation_attributes;
                                const wholesale_price = wholesale_variation.wholesale_price_raw;
                                const selected_variable_data = arrays_have_same_values(selected_variable, attributes);
                                
                                if (selected_variable_data) {
                                    wwpp_wholesale_prices.push(wholesale_price);
                                }
                            }
                        });
                    } else {
                        if (selected_object.wwpp_data.hasOwnProperty('wholesale_price_raw')) {
                            const wholesale_price = selected_object.wwpp_data.wholesale_price_raw;
                            wc_currency_symbol = selected_object.wwpp_data.wc_active_currency;
                            wholesale_price_total_title_text = selected_object.wwpp_data.wholesale_price_total_title_text;
                            
                            wwpp_wholesale_prices.push(wholesale_price);
                        }
                    }
                }
            });

            
            if (wholesale_user) {

                if ( wwpp_wholesale_prices.length > 0 ) {
                    // Add the parent composite price.
                    let composite_price_html = '';
                    if (wwpp_wholesale_parent_price !== 0) {
                        composite_price_html += '<span class="wholesale_price_title wholesale_price_parent_title">'+ wwpp_wholesale_parent_title +'</span> <span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">'+ wc_currency_symbol +'</span>'+ wwpp_wholesale_parent_price +'</span><br>';
                        wwpp_wholesale_prices.push(wwpp_wholesale_parent_price);
                    }
                    
                    // Get the sum of the wholesale prices.
                    const sum_of_wholesale_prices = sum_of_wholesale_price(wwpp_wholesale_prices);
                    const composite_price = parseFloat(sum_of_wholesale_prices).toFixed(2);
                    
                    // Update the composite price.
                    if (typeof composite_price !== 'undefined' && composite_price !== 0) {
                        composite_price_html += '<span class="wholesale_price_title">'+ wholesale_price_total_title_text +'</span> <span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">'+ wc_currency_symbol +'</span>'+ composite_price +'</span>';
                        jQuery('.composite_price').find('.price').html(composite_price_html);
                    } else {
                        jQuery('.composite_price').find('.price').html('');
                    }
                } else {
                    jQuery('.composite_price').find('.price').html('');
                }
            }
        });
    });

    // Configure the observer to watch for changes to the content of the element(s)
    const observerConfig = {
        childList: true,
        subtree: false
    };

    // Start observing the element(s)
    elementsToObserve.forEach(function (element) {
        if (element.classList.contains('composite_price')) { 
            observer.observe(element, observerConfig);
        }
    });

});

function sum_of_wholesale_price( obj ) {
  var sum = 0;
  for( var el in obj ) {
    if( obj.hasOwnProperty( el ) ) {
      sum += parseFloat( obj[el] );
    }
  }
  return sum;
}

function arrays_have_same_values(arr1, arr2) {
  if (arr1.length !== arr2.length) {
    return false;
  }
  return arr1.every((value, index) => arr2.includes(value));
}