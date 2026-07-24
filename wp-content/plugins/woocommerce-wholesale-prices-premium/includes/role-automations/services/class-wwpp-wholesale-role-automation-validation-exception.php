<?php
/**
 * Wholesale Role Automation Validation Exception.
 *
 * Thrown by `Rules_Repository::create()` and `update()` when a rule payload
 * fails validation. Each error carries one of the documented codes:
 * `invalid_scope`, `invalid_role_key`, `invalid_metric`, `invalid_window`,
 * `invalid_threshold`, `missing_target`, `too_many_rules`, or `rule_not_found`
 * (the last is emitted by `update()` when the supplied UUID does not match a
 * stored rule). Caught at the HTTP boundary by
 * `Action_Handlers::handle_rule_save()` / `handle_rule_edit()` and rendered
 * back to the admin UI as a `{ status, message }` response.
 *
 * @package    WooCommerceWholeSalePricesPremium
 * @subpackage WholesaleRoleAutomation\Services
 * @since      2.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WWPP_Wholesale_Role_Automation_Validation_Exception.
 *
 * Extends `RuntimeException` because validation failures are recoverable at
 * the call site (the admin UI shows the error and the user retries) — they
 * are not indicators of broken invariants.
 *
 * Carries an array of `{ code, message }` entries so callers can render
 * multiple validation failures in a single round trip.
 *
 * @since 2.1.0
 */
class WWPP_Wholesale_Role_Automation_Validation_Exception extends RuntimeException {

    /**
     * The list of validation errors.
     *
     * Each entry is shaped as `array( 'code' => string, 'message' => string )`.
     *
     * @since  2.1.0
     * @access private
     * @var    array
     */
    private $errors;

    /**
     * Constructor.
     *
     * @since 2.1.0
     *
     * @param array $errors Array of `{ code, message }` validation errors.
     */
    public function __construct( array $errors = array() ) {
        $this->errors = array_values( $errors );

        $first_message = isset( $this->errors[0]['message'] ) ? (string) $this->errors[0]['message'] : '';
        parent::__construct( $first_message );
    }

    /**
     * Return the full list of validation errors.
     *
     * @since 2.1.0
     *
     * @return array Array of `{ code, message }` entries.
     */
    public function get_errors(): array {
        return $this->errors;
    }

    /**
     * Return the first validation error code, if any.
     *
     * @since 2.1.0
     *
     * @return string|null The first error code, or null when no errors exist.
     */
    public function get_first_code(): ?string {
        return isset( $this->errors[0]['code'] ) ? (string) $this->errors[0]['code'] : null;
    }
}
