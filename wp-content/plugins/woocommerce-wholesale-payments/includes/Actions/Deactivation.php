<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WPay\Actions
 */

namespace RymeraWebCo\WPay\Actions;

use RymeraWebCo\WPay\Abstracts\Abstract_Class;

/**
 * Deactivation class.
 *
 * @since 1.0.0
 */
class Deactivation extends Abstract_Class {

    /**
     * Holds boolean value whether the plugin is being activated network wide.
     *
     * @var bool
     */
    protected $network_wide;

    /**
     * Constructor.
     *
     * @param bool $network_wide Whether the plugin is being activated network wide.
     */
    public function __construct( $network_wide ) {

        $this->network_wide = $network_wide;
    }

    /**
     * Run plugin deactivation actions.
     */
    public function run() {
        // TODO: Implement run() method.
    }
}
