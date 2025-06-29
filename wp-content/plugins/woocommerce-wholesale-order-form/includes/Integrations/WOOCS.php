<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Integrations
 */

namespace RymeraWebCo\WWOF\Integrations;

use RymeraWebCo\WWOF\Abstracts\Abstract_Class;
use RymeraWebCo\WWOF\Helpers\WOOCS as WOOCS_Helper;

/**
 * WOOCS class.
 *
 * @since 3.0
 */
class WOOCS extends Abstract_Class {

    /**
     * Customize the localization array for the Order Form app.
     *
     * @param array $defaults The default values for the localization array.
     *
     * @since 3.0
     * @return array
     */
    public function wwof_app_common_l10n( $defaults ) {

        return array_merge(
            $defaults,
            array(
                'currentCurrency' => get_woocommerce_currency() ? get_woocommerce_currency() : null,
            )
        );
    }

    /**
     * Run the WOOCS integration hooks.
     *
     * @since 3.0
     */
    public function run() {

        if ( ! WOOCS_Helper::is_active() ) {
            return;
        }

        add_filter( 'wwof_order_form_app_common_l10n_defaults', array( $this, 'wwof_app_common_l10n' ), 100 );
    }
}
