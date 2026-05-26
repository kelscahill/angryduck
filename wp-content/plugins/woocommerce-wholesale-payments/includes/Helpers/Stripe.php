<?php
/**
 * Author: Rymera Web Co.
 *
 * @package RymeraWebCo\WPay\Helpers
 */

namespace RymeraWebCo\WPay\Helpers;

use RymeraWebCo\WPay\Traits\Singleton_Trait;
use WP_Error;
use RymeraWebCo\WPay\Integrations\WPay_Emails;
use WC_Payment_Token_CC;
use WC_Payment_Tokens;

/**
 * Stripe class.
 *
 * @since 1.0.0
 */
class Stripe {

    use Singleton_Trait;

    /**
     * Holds the class instance object
     *
     * @since 3.0.6
     * @var Stripe $instance object
     */
    protected static $instance;

    /**
     * Stripe API version.
     *
     * @since 1.0.0 Stripe Invoice Payment Plans is in beta.
     */
    private const STRIPE_API_VERSION = '2023-10-16;invoice_payment_plans_beta=v1';

    /**
     * Stripe API base URL.
     *
     * @since 1.0.0
     * @var string
     */
    private $base_url;

    /**
     * Stripe API headers.
     *
     * @since 1.0.0
     * @var array
     */
    private $headers;

    /**
     * Stripe constructor.
     */
    public function __construct() {

        $this->base_url = 'https://api.stripe.com/v1';
        $this->headers  = array(
            'Authorization'  => 'Bearer ' . WPay::get_token(),
            'Content-Type'   => 'application/json',
            'Stripe-Version' => apply_filters( 'wpay_stripe_api_version', self::STRIPE_API_VERSION ),
        );
    }

    /**
     * Send a GET request to Stripe API.
     *
     * @see   wp_remote_get()
     *
     * @param string $endpoint     Stripe API endpoint.
     * @param mixed  $query_string Query string parameters.
     * @param array  $options      wp_remote_get() options.
     *
     * @since 1.0.0
     * @return array|mixed|WP_Error
     */
    public function send_get_request( $endpoint, $query_string = array(), $options = array() ) {

        $args = wp_parse_args(
            $options,
            array(
                'headers' => $this->headers,
            )
        );

        $url = "$this->base_url/$endpoint";
        if ( ! empty( $query_string ) ) {
            // Build query string manually to preserve special characters like quotes.
            // add_query_arg() + esc_url_raw() can strip quotes needed for Stripe search API.
            $query_parts = array();
            foreach ( $query_string as $key => $value ) {
                $query_parts[] = rawurlencode( $key ) . '=' . rawurlencode( $value );
            }
            $url .= '?' . implode( '&', $query_parts );
        }
        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $json = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $json['error']['code'] ) && isset( $json['error']['message'] ) ) {
            return new WP_Error( $json['error']['code'], $json['error']['message'] );
        } elseif ( isset( $response['response']['code'] ) && $response['response']['code'] >= 400 ) {
            if ( isset( $json['error']['type'] ) && isset( $json['error']['message'] ) ) {
                return new WP_Error( $json['error']['type'], $json['error']['message'] );
            } elseif ( isset( $json['message'] ) ) {
                return new WP_Error( $response['response']['code'], $json['message'] );
            }

            return new WP_Error( $response['response']['code'], $response['response']['message'] );
        } elseif ( empty( $json ) ) {
            return new WP_Error( 'wpay_stripe_invalid_response', __( 'Invalid response from Stripe.', 'woocommerce-wholesale-payments' ) );
        }

        return $json;
    }

    /**
     * Send a POST request to Stripe.
     *
     * @see   wp_remote_post()
     *
     * @param string $endpoint The Stripe endpoint to send the request to.
     * @param array  $payload  The payload to send to Stripe.
     * @param array  $options  wp_remote_post() options.
     *
     * @since 1.0.0
     * @return array|mixed|WP_Error
     */
    public function send_post_request( $endpoint, $payload = array(), $options = array() ) {

        $args     = wp_parse_args(
            $options,
            array(
                'headers' => wp_parse_args( array( 'Content-Type' => 'application/x-www-form-urlencoded' ), $this->headers ),
                'body'    => $payload,
            )
        );
        $response = wp_remote_post( "$this->base_url/$endpoint", $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }
        $json = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset( $response['response']['code'] ) && $response['response']['code'] >= 400 ) {
            if ( isset( $json['message'] ) ) {
                return new WP_Error( $response['response']['code'], $json['message'] );
            } elseif ( isset( $json['error']['type'] ) && isset( $json['error']['message'] ) ) {
                return new WP_Error( $json['error']['type'], $json['error']['message'] );
            }

            return new WP_Error( $response['response']['code'], $response['response']['message'] );
        }

        return $json;
    }

    /**
     * Send DELETE request to Stripe API.
     *
     * @param string $endpoint Stripe API endpoint.
     * @param array  $options  Additional request options.
     *
     * @since 1.0.5.3
     * @return array|WP_Error Response data or error object.
     */
    public function send_delete_request( $endpoint, $options = array() ) {

        $args     = wp_parse_args(
            $options,
            array(
                'method'  => 'DELETE',
                'headers' => $this->headers,
            )
        );
        $response = wp_remote_request( "$this->base_url/$endpoint", $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }
        $json = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset( $response['response']['code'] ) && $response['response']['code'] >= 400 ) {
            if ( isset( $json['message'] ) ) {
                return new WP_Error( $response['response']['code'], $json['message'] );
            } elseif ( isset( $json['error']['type'] ) && isset( $json['error']['message'] ) ) {
                return new WP_Error( $json['error']['type'], $json['error']['message'] );
            }

            return new WP_Error( $response['response']['code'], $response['response']['message'] );
        }

        return $json;
    }

    /**
     * Get Stripe account details.
     *
     * @param string|null $account_id Stripe account ID.
     *
     * @since 1.0.0
     * @return array|WP_Error
     */
    public function get_account( $account_id = null ) {

        if ( null === $account_id ) {
            $account_id = WPay::get_account_number();
        }

        if ( empty( $account_id ) ) {
            return new WP_Error( 'wpay_stripe_account_id_not_found', __( 'Stripe account ID not found.', 'woocommerce-wholesale-payments' ) );
        }

        return $this->send_get_request( "accounts/$account_id" );
    }

    /**
     * Search for a customer.
     *
     * @see   https://stripe.com/docs/search#query-fields-for-customers.
     *
     * @param array $query Stripe customers query fields.
     *
     * @since 1.0.0
     * @since 3.0.6 Search for a customer using the query array.
     * @return array|mixed|WP_Error
     */
    public function search_customer( $query ) {

        $query_array = array();
        $operators   = array(
            ':',
            '~',
            '<',
            '>',
            '=',
        );
        foreach ( $query as $key => $value ) {
            if ( ! in_array( substr( $key, -1 ), $operators, true ) ) {
                $key .= ':';
            }
            // Escape quotes and backslashes in the value for Stripe search syntax.
            if ( ! is_numeric( $value ) ) {
                $value = '"' . addcslashes( $value, '\\"' ) . '"';
            }
            $query_array[] = "$key$value";
        }

        // Search for a customer using the query array.
        return $this->send_get_request(
            'customers/search',
            array(
                'query' => implode( ' AND ', $query_array ),
            ),
            array()
        );
    }

    /**
     * Create Stripe customer.
     *
     * @param string $name  Name of the customer.
     * @param string $email Email of the customer.
     *
     * @since 1.0.0
     * @return array|mixed|WP_Error
     */
    public function create_customer( $name, $email ) {
        $payload = array(
            'name'  => $name,
            'email' => $email,
        );

        // Disable email reminders if the setting is disabled.
        if ( ! WPay_Emails::is_stripe_invoice_reminders_enabled() ) {
            $payload['invoice_settings'] = array(
                'default_payment_method' => null,
                'custom_fields'          => null,
                'footer'                 => null,
                'rendering_options'      => array(
                    'amount_tax_display' => null,
                ),
            );
        }

        return $this->send_post_request(
            'customers',
            $payload
        );
    }

    /**
     * Create Stripe Invoice Payment plan.
     *
     * @param string $customer_id   Stripe customer ID.
     * @param array  $amounts_due   Amounts due (in cents) for the invoice.
     * @param bool   $auto_charge   Whether to auto-charge the invoice.
     * @param bool   $skip_finalize Optional. Skip invoice finalization. Default false.
     *
     * @since 1.0.0
     * @since 1.0.6 Fixed invoice creation logic for auto-charge and added validation.
     * @since 1.0.6 Added cleanup to delete orphaned draft invoices on failure.
     * @return array|mixed|WP_Error
     */
    public function create_invoice( $customer_id, $amounts_due, $auto_charge = false, $skip_finalize = false ) {

        // Validate inputs.
        if ( empty( $customer_id ) ) {
            return new WP_Error(
                'invalid_customer',
                __( 'Customer ID is required to create an invoice.', 'woocommerce-wholesale-payments' )
            );
        }

        if ( empty( $amounts_due ) || ! is_array( $amounts_due ) ) {
            return new WP_Error(
                'invalid_amounts',
                __( 'Invoice amounts are required.', 'woocommerce-wholesale-payments' )
            );
        }

        $payload = array(
            'customer'          => $customer_id,
            'collection_method' => 'send_invoice',
            'auto_advance'      => 'true',
            'currency'          => get_woocommerce_currency(),
        );

        $total_amount = array_sum( wp_list_pluck( $amounts_due, 'amount' ) );

        // Validate total amount.
        if ( $total_amount <= 0 ) {
            return new WP_Error(
                'invalid_total',
                __( 'Invoice total must be greater than zero.', 'woocommerce-wholesale-payments' )
            );
        }

        // Check if settings are set to auto-charge invoices.
        if ( $auto_charge ) {
            $payload['collection_method'] = 'charge_automatically';
        } else {
            // For send_invoice collection method, Stripe requires due_date or days_until_due at invoice level.
            // Use the earliest due date from the amounts_due items, or default to 30 days.
            $earliest_days      = 30; // Default fallback.
            $earliest_timestamp = null;

            foreach ( $amounts_due as $item ) {
                if ( isset( $item['days_until_due'] ) && is_numeric( $item['days_until_due'] ) ) {
                    $earliest_days = min( $earliest_days, absint( $item['days_until_due'] ) );
                } elseif ( isset( $item['due_date'] ) && is_numeric( $item['due_date'] ) ) {
                    if ( null === $earliest_timestamp || $item['due_date'] < $earliest_timestamp ) {
                        $earliest_timestamp = $item['due_date'];
                    }
                }
            }

            // Prefer due_date if available, otherwise use days_until_due.
            if ( null !== $earliest_timestamp ) {
                $payload['due_date'] = $earliest_timestamp;
            } else {
                $payload['days_until_due'] = $earliest_days;
            }
        }

        // Disable email reminders if the setting is disabled.
        if ( ! WPay_Emails::is_stripe_invoice_reminders_enabled() ) {
            $payload['payment_settings'] = array(
                'payment_method_types'   => null,
                'payment_method_options' => null,
                'default_payment_method' => null,
            );
        }

        $license_status = License::get_license_status_type();
        if ( ( in_array( $license_status['type'], array( 'no_license', 'expired', 'disabled-cancelled' ), true ) &&
                'gte_14' === $license_status['interval'] ) ||
            ( in_array( $license_status['type'], array( 'disabled-active', 'disabled-pending-cancel' ), true ) &&
                in_array( $license_status['interval'], array( 'lt_14', 'gte_14' ), true ) ) ) {

            $application_fee_amount = 0;
            if ( $auto_charge ) {
                $application_fee_amount = ( $total_amount * 0.03 ) / count( $amounts_due );
            } else {
                $application_fee_amount = $total_amount * 0.03;
            }

            $payload['application_fee_amount'] = $application_fee_amount;
        }

        // Create the invoice first without amounts_due (not a valid Stripe parameter).
        $invoice_response = $this->send_post_request(
            'invoices',
            $payload
        );

        // If invoice creation failed, return the error.
        if ( is_wp_error( $invoice_response ) ) {
            return $invoice_response;
        }

        $invoice_id = $invoice_response['id'];

        // Add invoice items for ALL invoices (both auto-charge and non-auto-charge).
        foreach ( $amounts_due as $amount_due ) {
            $item_response = $this->add_invoice_item(
                $customer_id,
                $invoice_id,
                $amount_due['amount'],
                isset( $amount_due['description'] ) ? $amount_due['description'] : ''
            );

            // If adding any item fails, delete the orphaned draft invoice and return the error.
            if ( is_wp_error( $item_response ) ) {
                // Clean up: Delete the draft invoice to prevent orphaned resources.
                $delete_result = $this->delete_invoice( $invoice_id );
                if ( is_wp_error( $delete_result ) ) {
                    // Log deletion failure but still return the original error.
                    wc_get_logger()->error(
                        sprintf(
                            'Failed to delete orphaned invoice %s after item addition error: %s',
                            $invoice_id,
                            $delete_result->get_error_message()
                        ),
                        array( 'source' => 'woocommerce-wholesale-payments' )
                    );
                }
                return $item_response;
            }
        }

        /**
         *Finalize the non auto-charge invoices.
         *
         * For auto-charge invoices, leave in draft state - they'll be auto-finalized when charged.
        */
        if ( ! $auto_charge && ! $skip_finalize ) {
            // Check invoice status before finalizing to avoid double-finalization errors.
            if ( isset( $invoice_response['status'] ) && 'draft' === $invoice_response['status'] ) {
                $finalize_response = $this->finalize_invoice( $invoice_id );
                if ( is_wp_error( $finalize_response ) ) {
                    // Clean up: Delete the draft invoice to prevent orphaned resources.
                    $delete_result = $this->delete_invoice( $invoice_id );
                    if ( is_wp_error( $delete_result ) ) {
                        // Log deletion failure but still return the original error.
                        wc_get_logger()->error(
                            sprintf(
                                'Failed to delete orphaned invoice %s after finalization error: %s',
                                $invoice_id,
                                $delete_result->get_error_message()
                            ),
                            array( 'source' => 'woocommerce-wholesale-payments' )
                        );
                    }
                    return $finalize_response;
                }

                /**
                 * Update invoice_response with the complete finalized invoice response.
                 * This includes critical data: invoice items (lines), invoice number, hosted_invoice_url, etc.
                 */
                $invoice_response = $finalize_response; // Use the complete finalized invoice response.
            }
        }

        // Return the complete invoice response (with all items added).
        return $invoice_response;
    }

    /**
     * Add an item to a Stripe Invoice Payment plan.
     *
     * @param string $customer_id Stripe customer ID.
     * @param string $invoice_id  Stripe invoice ID.
     * @param int    $amount      Total amount (in cents) to be added to the invoice.
     * @param string $description Description of the invoice item.
     *
     * @since 1.0.0
     * @return array|mixed|WP_Error
     */
    public function add_invoice_item( $customer_id, $invoice_id, $amount, $description = '' ) {

        $payload = array(
            'customer' => $customer_id,
            'invoice'  => $invoice_id,
            'amount'   => $amount,
            'currency' => get_woocommerce_currency(),
        );

        if ( ! empty( $description ) ) {
            $payload['description'] = $description;
        }

        return $this->send_post_request(
            'invoiceitems',
            $payload
        );
    }

    /**
     * Finalize Stripe Invoice Payment plan.
     *
     * @param string $invoice_id Stripe invoice ID.
     *
     * @since 1.0.0
     * @return array|mixed|WP_Error
     */
    public function finalize_invoice( $invoice_id ) {

        return $this->send_post_request( "invoices/$invoice_id/finalize" );
    }

    /**
     * Pay Stripe Invoice Payment plan.
     *
     * @param string $invoice_id Stripe invoice ID.
     * @param string $paid       Whether the invoice is paid.
     *
     * @since 1.0.0
     * @return array|mixed|WP_Error
     */
    public function pay_invoice( $invoice_id, $paid = 'false' ) {

        $payload = array(
            'paid_out_of_band' => $paid,
        );

        return $this->send_post_request( "invoices/$invoice_id/pay", $payload );
    }

    /**
     * Delete a draft Stripe Invoice.
     *
     * Only draft invoices can be deleted. Once finalized or paid, use void instead.
     *
     * @param string $invoice_id Stripe invoice ID.
     *
     * @since 1.0.5.3
     * @return array|mixed|WP_Error
     */
    public function delete_invoice( $invoice_id ) {

        if ( empty( $invoice_id ) ) {
            return new WP_Error(
                'invalid_invoice_id',
                __( 'Invoice ID is required to delete an invoice.', 'woocommerce-wholesale-payments' )
            );
        }

        return $this->send_delete_request( "invoices/$invoice_id" );
    }

    /**
     * Get Stripe customer by ID.
     *
     * @param string $customer_id Stripe customer ID.
     *
     * @since 1.0.0
     * @return array|WP_Error
     */
    public function get_customer( $customer_id ) {

        if ( empty( $customer_id ) ) {
            return new WP_Error( 'wpay_stripe_customer_id_not_found', __( 'Stripe customer ID not found.', 'woocommerce-wholesale-payments' ) );
        }

        return $this->send_get_request( "customers/$customer_id" );
    }

    /**
     * List customer's payment methods.
     *
     * @param string $customer_id Stripe customer ID.
     * @param string $type        Payment method type. Defaults to 'card'.
     *
     * @since 1.0.0
     * @since 1.0.6 List customer's payment methods.
     * @return array|WP_Error
     */
    public function list_payment_methods( $customer_id, $type = 'card' ) {

        if ( empty( $customer_id ) ) {
            return new WP_Error( 'wpay_stripe_customer_id_not_found', __( 'Stripe customer ID not found.', 'woocommerce-wholesale-payments' ) );
        }

        return $this->send_get_request(
            'payment_methods',
            array(
                'customer' => $customer_id,
                'type'     => $type,
            )
        );
    }

    /**
     * Check if customer has default payment method.
     *
     * @param string $customer_id Stripe customer ID.
     * @param int    $user_id     WordPress user ID (optional).
     * @param bool   $skip_sync   Skip sync to prevent recursion (optional, default false).
     *
     * @since 1.0.6 Check if customer has default payment method and WooCommerce tokens.
     * @return bool|WP_Error
     */
    public function customer_has_default_payment_method( $customer_id, $user_id = null, $skip_sync = false ) {

        $customer = $this->get_customer( $customer_id );
        if ( is_wp_error( $customer ) ) {
            return $customer;
        }

        $default = isset( $customer['invoice_settings']['default_payment_method'] ) ? $customer['invoice_settings']['default_payment_method'] : null;

        /*
         * Check WooCommerce tokens first if user ID is provided.
         * This prevents unnecessary Stripe API calls during checkout.
         */
        if ( ! empty( $user_id ) && ! empty( $default ) ) {
            // Check if default payment method exists as WooCommerce token.
            if ( $this->has_woocommerce_token( $default, $user_id ) ) {
                return $default;
            }

            // If no local token but Stripe has payment methods, trigger sync.
            // Skip sync if called from sync_payment_methods_to_woocommerce to prevent recursion.
            $wc_tokens = $this->get_woocommerce_tokens( $user_id );
            if ( empty( $wc_tokens ) && ! $skip_sync ) {

                $sync_result = $this->sync_payment_methods_to_woocommerce( $customer_id, $user_id );
                if ( ! is_wp_error( $sync_result ) ) {
                    // Re-check after sync.
                    if ( $this->has_woocommerce_token( $default, $user_id ) ) {
                        return $default;
                    }
                }
            }
        }

        /*
         * Additional verification: List actual payment methods attached to customer.
         * This prevents false positives where default_payment_method might be set
         * but no actual payment methods exist.
         */
        if ( ! empty( $default ) ) {
            $payment_methods = $this->send_get_request(
                'payment_methods',
                array(
                    'customer' => $customer_id,
                    'type'     => 'card',
                    'limit'    => 1,
                )
            );

            if ( is_wp_error( $payment_methods ) ) {
                return $payment_methods;
            }

            // Verify customer actually has at least one payment method.
            if ( empty( $payment_methods['data'] ) || ! is_array( $payment_methods['data'] ) || 0 === count( $payment_methods['data'] ) ) {
                return false;
            }
        }

        return ! empty( $default );
    }

    /**
     * Attach a payment method to customer.
     *
     * @param string $customer_id       Stripe customer ID.
     * @param string $payment_method_id Stripe payment method ID.
     * @param int    $user_id           WordPress user ID (optional).
     *
     * @since 1.0.0
     * @since 1.0.6 Added user ID parameter for token synchronization.
     * @return array|WP_Error
     */
    public function attach_payment_method_to_customer( $customer_id, $payment_method_id, $user_id = null ) {

        if ( empty( $customer_id ) || empty( $payment_method_id ) ) {
            return new WP_Error( 'wpay_stripe_invalid_params', __( 'Invalid Stripe customer or payment method.', 'woocommerce-wholesale-payments' ) );
        }

        $attached = $this->send_post_request( "payment_methods/$payment_method_id/attach", array( 'customer' => $customer_id ) );
        if ( is_wp_error( $attached ) ) {
            return $attached;
        }

        // Set as default for invoices.
        $updated = $this->update_customer_default_payment_method( $customer_id, $payment_method_id );
        if ( is_wp_error( $updated ) ) {
            return $updated;
        }

        // Create WooCommerce token if user ID is provided.
        $token_id = null;
        if ( ! empty( $user_id ) ) {
            $payment_method_data = $this->send_get_request( "payment_methods/$payment_method_id" );
            if ( ! is_wp_error( $payment_method_data ) ) {
                $token_result = $this->create_woocommerce_token_from_payment_method( $payment_method_data, $user_id );
                if ( ! is_wp_error( $token_result ) ) {
                    $token_id = $token_result;
                }
            }
        }

        // Return enhanced response with token information.
        $response = $attached;
        if ( ! empty( $token_id ) ) {
            $response['wc_token_id'] = $token_id;
        }

        return $response;
    }

    /**
     * Detach all payment methods from a customer.
     *
     * This method removes all payment methods associated with a Stripe customer,
     * both from Stripe and from WooCommerce payment tokens. It also clears the
     * default payment method setting. Customer IDs are preserved to maintain
     * the relationship between WordPress users and Stripe customers.
     *
     * @param string   $customer_id Stripe customer ID.
     * @param int|null $user_id     WordPress user ID (optional, for token cleanup).
     *
     * @since 1.0.6
     * @throws WP_Error If customer ID is invalid or Stripe API call fails.
     * @return array|WP_Error Array with 'success', 'detached_count', 'wc_deleted_count', and 'customer_id', or WP_Error on failure.
     */
    public function detach_all_payment_methods( $customer_id, $user_id = null ) {

        if ( empty( $customer_id ) ) {
            return new WP_Error( 'wpay_stripe_customer_id_not_found', __( 'Stripe customer ID not found.', 'woocommerce-wholesale-payments' ) );
        }

        // First, clear the default payment method from the customer.
        // Note: If this fails, we still proceed with detachment to ensure consistency.
        $cleared = $this->send_post_request( "customers/$customer_id", array( 'invoice_settings[default_payment_method]' => '' ) );
        if ( is_wp_error( $cleared ) ) {
            // Log the error but continue with detachment to prevent inconsistent state.
            wc_get_logger()->warning(
                sprintf(
                    /* translators: %1$s = customer ID, %2$s = error message */
                    __( 'Failed to clear default payment method for customer %1$s: %2$s. Proceeding with payment method detachment.', 'woocommerce-wholesale-payments' ),
                    $customer_id,
                    $cleared->get_error_message()
                ),
                array( 'source' => 'woocommerce-wholesale-payments' )
            );
        }

        // Get all payment methods for this customer.
        $methods = $this->list_payment_methods( $customer_id, 'card' );
        if ( is_wp_error( $methods ) ) {
            return $methods;
        }

        $detached_count = 0;
        if ( isset( $methods['data'] ) && is_array( $methods['data'] ) ) {
            foreach ( $methods['data'] as $method ) {
                if ( empty( $method['id'] ) ) {
                    continue;
                }

                // Detach each payment method.
                $detached = $this->send_post_request( "payment_methods/{$method['id']}/detach", array() );
                if ( ! is_wp_error( $detached ) ) {
                    ++$detached_count;
                }
            }
        }

        // Delete WooCommerce tokens if user ID is provided.
        $wc_deleted_count = 0;
        if ( ! empty( $user_id ) ) {
            $wc_tokens = WC_Payment_Tokens::get_customer_tokens( $user_id, 'wc_wholesale_payments' );
            foreach ( $wc_tokens as $wc_token ) {
                WC_Payment_Tokens::delete( $wc_token->get_id() );
                ++$wc_deleted_count;
            }

            // Clear payment method related user meta.
            // Note: Customer IDs (_wpay_stripe_customer_id and _stripe_customer_id) are NOT deleted
            // to maintain the relationship between WordPress users and Stripe customers.
            // Customer IDs should only be deleted if the Stripe customer itself is deleted.
            delete_user_meta( $user_id, '_wpay_default_payment_method' );
            delete_user_meta( $user_id, '_stripe_default_payment_method' );
        }

        return array(
            'success'          => true,
            'detached_count'   => $detached_count,
            'wc_deleted_count' => $wc_deleted_count,
            'customer_id'      => $customer_id,
        );
    }

    /**
     * Update customer's default payment method.
     *
     * @param string $customer_id       Stripe customer ID.
     * @param string $payment_method_id Stripe payment method ID.
     *
     * @since 1.0.6 Update customer's default payment method.
     * @return array|WP_Error
     */
    public function update_customer_default_payment_method( $customer_id, $payment_method_id ) {

        if ( empty( $customer_id ) || empty( $payment_method_id ) ) {
            return new WP_Error( 'wpay_stripe_invalid_params', __( 'Invalid Stripe customer or payment method.', 'woocommerce-wholesale-payments' ) );
        }

        // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
        return $this->send_post_request( "customers/$customer_id", array( 'invoice_settings[default_payment_method]' => $payment_method_id ) );
    }

    /**
     * Create a SetupIntent for saving a payment method.
     *
     * @param string $customer_id Stripe customer ID.
     * @param array  $types       Payment method types.
     *
     * @since 1.0.6 Create a SetupIntent for saving a payment method.
     * @return array|WP_Error
     */
    public function create_setup_intent( $customer_id, $types = array( 'card' ) ) {

        if ( empty( $customer_id ) ) {
            return new WP_Error( 'wpay_stripe_customer_id_not_found', __( 'Stripe customer ID not found.', 'woocommerce-wholesale-payments' ) );
        }

        $payload = array(
            'customer' => $customer_id,
            'usage'    => 'off_session',
        );

        // Add payment method types.
        if ( ! empty( $types ) ) {
            foreach ( (array) $types as $i => $type ) {
                $payload[ "payment_method_types[$i]" ] = $type;
            }
        }

        return $this->send_post_request( 'setup_intents', $payload );
    }

    /**
     * Send Stripe Invoice Payment plan.
     *
     * @param array $query Stripe invoice ID.
     *
     * @since 1.0.0
     * @return array|mixed|WP_Error
     */
    public function search_invoice( $query ) {

        $query_array = array();
        $operators   = array(
            ':',
            '~',
            '<',
            '>',
            '=',
        );
        foreach ( $query as $key => $value ) {
            if ( ! in_array( substr( $key, -1 ), $operators, true ) ) {
                $key .= ':';
            }
            $query_array[] = "$key" . ( is_numeric( $value ) ? $value : "\"$value\"" );
        }

        return $this->send_get_request(
            'invoices/search',
            array(),
            array(
                'body' => array(
                    'query' => implode( ' AND ', $query_array ),
                ),
            )
        );
    }

    /**
     * Get Stripe invoice.
     *
     * @param string $invoice_id Stripe invoice ID.
     *
     * @since 1.0.0
     * @return array|mixed|WP_Error
     */
    public function get_invoice( $invoice_id ) {

        return $this->send_get_request(
            "invoices/$invoice_id"
        );
    }

    /**
     * Get Stripe registered webhooks.
     *
     * @since 1.0.0
     * @return array|mixed|WP_Error
     */
    public function get_webhooks() {

        return $this->send_get_request(
            'webhook_endpoints'
        );
    }

    /**
     * Create a Stripe webhook.
     *
     * @param string   $url    Site URL to receive the webhook.
     * @param string[] $events Stripe webhook events.
     *
     * @since 1.0.0
     * @return array|mixed|WP_Error
     */
    public function create_webhook( $url, $events ) {

        $payload = array(
            'url'            => $url,
            'enabled_events' => $events,
            'api_version'    => apply_filters( 'wpay_stripe_api_version', self::STRIPE_API_VERSION ),
        );

        return $this->send_post_request(
            'webhook_endpoints',
            $payload
        );
    }

    /**
     * Verify Stripe signature.
     *
     * @param string $signature Stripe signature.
     * @param string $payload   Raw body of the request from Stripe.
     *
     * @since 1.0.0
     * @return bool
     */
    public function verify_signature( $signature, $payload ) {

        $elements = explode( ',', $signature );
        if ( is_array( $elements ) ) {
            $timestamp  = null;
            $signatures = array();
            foreach ( $elements as $element ) {
                $parts = explode( '=', $element );
                if ( 't' === $parts[0] ) {
                    $timestamp = $parts[1];
                }
                if ( 'v1' === $parts[0] ) {
                    $signatures[] = $parts[1];
                }
            }

            if ( empty( $timestamp ) || empty( $signatures ) ) {
                return false;
            }

            $signed_payload     = "$timestamp.$payload";
            $expected_signature = hash_hmac( 'sha256', $signed_payload, WPay::get_webhook_secret() );
            foreach ( $signatures as $signature ) {
                if ( hash_equals( $expected_signature, $signature ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Create a WooCommerce payment token from Stripe payment method data.
     *
     * @param array $payment_method Stripe payment method data.
     * @param int   $user_id        WordPress user ID.
     *
     * @since 1.0.6 Added functionality: Create a WooCommerce payment token from Stripe payment method data.
     * @return int|WP_Error Token ID on success, WP_Error on failure.
     */
    public function create_woocommerce_token_from_payment_method( $payment_method, $user_id ) {
        if ( ! isset( $payment_method['id'] ) || ! isset( $payment_method['card'] ) ) {
            return new WP_Error(
                'wpay_invalid_payment_method',
                __( 'Invalid payment method data provided.', 'woocommerce-wholesale-payments' )
            );
        }

        $payment_method_id = $payment_method['id'];
        $card_data         = $payment_method['card'];

        // Create WooCommerce payment token.
        $token = new WC_Payment_Token_CC();
        $token->set_token( $payment_method_id );
        $token->set_gateway_id( 'wc_wholesale_payments' );
        $token->set_user_id( $user_id );

        // Set card details.
        $token->set_card_type( strtolower( $card_data['brand'] ) );
        $token->set_last4( $card_data['last4'] );
        $token->set_expiry_month( str_pad( $card_data['exp_month'], 2, '0', STR_PAD_LEFT ) );
        $token->set_expiry_year( $card_data['exp_year'] );

        try {
            $token->save();

            return $token->get_id();
        } catch ( \Exception $e ) {
            $logger = wc_get_logger();
            $logger->error(
                sprintf(
                    /* translators: %1$s = Payment method ID, %2$s = Error message */
                    __( 'Failed to create WooCommerce token for payment method %1$s: %2$s', 'woocommerce-wholesale-payments' ),
                    $payment_method_id,
                    $e->getMessage()
                ),
                array( 'source' => 'wpay_token_creation' )
            );

            return new WP_Error(
                'wpay_token_creation_failed',
                sprintf(
                    /* translators: %s = Error message */
                    __( 'Failed to create payment token: %s', 'woocommerce-wholesale-payments' ),
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * Synchronize Stripe payment methods with WooCommerce tokens.
     *
     * @param string $customer_id Stripe customer ID.
     * @param int    $user_id     WordPress user ID.
     *
     * @since 1.0.6 Added functionality: Synchronize Stripe payment methods with WooCommerce tokens.
     * @return array|WP_Error Sync results or WP_Error on failure.
     */
    public function sync_payment_methods_to_woocommerce( $customer_id, $user_id ) {
        // Get Stripe payment methods.
        $stripe_methods = $this->list_payment_methods( $customer_id, 'card' );
        if ( is_wp_error( $stripe_methods ) ) {
            return $stripe_methods;
        }

        // Get existing WooCommerce tokens for this user and gateway.
        $wc_tokens = WC_Payment_Tokens::get_customer_tokens( $user_id, 'wc_wholesale_payments' );

        $created_count = 0;
        $deleted_count = 0;

        // Create tokens for missing payment methods.
        if ( isset( $stripe_methods['data'] ) && is_array( $stripe_methods['data'] ) ) {
            foreach ( $stripe_methods['data'] as $stripe_method ) {
                $exists_in_wc = false;

                // Check if token already exists in WooCommerce.
                foreach ( $wc_tokens as $wc_token ) {
                    if ( $wc_token->get_token() === $stripe_method['id'] ) {
                        $exists_in_wc = true;
                        break;
                    }
                }

                if ( ! $exists_in_wc ) {
                    $token_id = $this->create_woocommerce_token_from_payment_method( $stripe_method, $user_id );
                    if ( ! is_wp_error( $token_id ) ) {
                        ++$created_count;
                    }
                }
            }
        }

        // Remove orphaned WooCommerce tokens (exist in WC but not in Stripe).
        foreach ( $wc_tokens as $wc_token ) {
            $exists_in_stripe = false;
            if ( isset( $stripe_methods['data'] ) && is_array( $stripe_methods['data'] ) ) {
                foreach ( $stripe_methods['data'] as $stripe_method ) {
                    if ( $wc_token->get_token() === $stripe_method['id'] ) {
                        $exists_in_stripe = true;
                        break;
                    }
                }
            }

            if ( ! $exists_in_stripe ) {
                WC_Payment_Tokens::delete( $wc_token->get_id() );
                ++$deleted_count;
            }
        }

        // Update default payment method to match Stripe.
        // Pass skip_sync=true to prevent recursion (we're already in sync_payment_methods_to_woocommerce).
        $default_pm = $this->customer_has_default_payment_method( $customer_id, $user_id, true );
        if ( ! is_wp_error( $default_pm ) && $default_pm ) {
            $wc_tokens   = WC_Payment_Tokens::get_customer_tokens( $user_id, 'wc_wholesale_payments' );
            $default_set = false;
            foreach ( $wc_tokens as $wc_token ) {
                if ( $wc_token->get_token() === $default_pm ) {
                    $wc_token->set_default( true );
                    $wc_token->save();
                    $default_set = true;
                    break;
                }
            }

            // If default wasn't found in existing tokens, clear all defaults first then set it.
            if ( ! $default_set ) {
                foreach ( $wc_tokens as $wc_token ) {
                    if ( $wc_token->is_default() ) {
                        $wc_token->set_default( false );
                        $wc_token->save();
                    }
                }

                // Re-fetch tokens and try again.
                $wc_tokens = WC_Payment_Tokens::get_customer_tokens( $user_id, 'wc_wholesale_payments' );
                foreach ( $wc_tokens as $wc_token ) {
                    if ( $wc_token->get_token() === $default_pm ) {
                        $wc_token->set_default( true );
                        $wc_token->save();
                        break;
                    }
                }
            }
        }

        return array(
            'success'       => true,
            'created_count' => $created_count,
            'deleted_count' => $deleted_count,
            'customer_id'   => $customer_id,
            'user_id'       => $user_id,
        );
    }

    /**
     * Check if customer has WooCommerce payment tokens for this gateway.
     *
     * @param int $user_id WordPress user ID.
     *
     * @since 1.0.6 Added functionality: Check if customer has WooCommerce payment tokens for this gateway.
     * @return array Array of WC_Payment_Token objects.
     */
    public function get_woocommerce_tokens( $user_id ) {
        if ( empty( $user_id ) ) {
            return array();
        }

        return WC_Payment_Tokens::get_customer_tokens( $user_id, 'wc_wholesale_payments' );
    }

    /**
     * Check if a specific payment method exists as a WooCommerce token.
     *
     * @param string $payment_method_id Stripe payment method ID.
     * @param int    $user_id           WordPress user ID.
     *
     * @since 1.0.6 Added functionality: Check if a specific payment method exists as a WooCommerce token.
     * @return bool True if token exists, false otherwise.
     */
    public function has_woocommerce_token( $payment_method_id, $user_id ) {
        if ( empty( $payment_method_id ) || empty( $user_id ) ) {
            return false;
        }

        $tokens = $this->get_woocommerce_tokens( $user_id );
        foreach ( $tokens as $token ) {
            if ( $token->get_token() === $payment_method_id ) {
                return true;
            }
        }

        return false;
    }
}
