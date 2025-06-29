<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Integrations
 */

namespace RymeraWebCo\WWOF\Integrations;

use RymeraWebCo\WWOF\Abstracts\Abstract_Class;
use RymeraWebCo\WWOF\Classes\Order_Forms_Admin_Page;
use RymeraWebCo\WWOF\Helpers\Site_Ground_Optimizer as SGO;

/**
 * Site_Ground_Optimizer class.
 *
 * @since 3.0
 */
class Site_Ground_Optimizer extends Abstract_Class {

    /**
     * Exclude script handles from SiteGround Optimizer.
     *
     * @param string[] $excluded Script handles to exclude.
     *
     * @since 3.0
     * @return string[]
     */
    public function exclude_resource_handles( $excluded = array() ) {

        if ( ! in_array( 'wwof_product_listing', $excluded, true ) ) {
            $excluded[] = 'wwof_product_listing';
        }
        if ( ! in_array( Order_Forms_Admin_Page::MENU_SLUG, $excluded, true ) ) {
            $excluded[] = Order_Forms_Admin_Page::MENU_SLUG;
        }

        return $excluded;
    }

    /**
     * Run the SiteGround Optimizer integration hooks
     */
    public function run() {

        if ( ! SGO::is_active() ) {
            return;
        }

        add_filter( 'sgo_javascript_combine_exclude', array( $this, 'exclude_resource_handles' ), 100 );
        add_filter( 'sgo_js_minify_exclude', array( $this, 'exclude_resource_handles' ), 100 );
        add_filter( 'sgo_css_combine_exclude', array( $this, 'exclude_resource_handles' ), 100 );
        add_filter( 'sgo_css_minify_exclude', array( $this, 'exclude_resource_handles' ), 100 );

        add_filter( 'wwof_enable_subresource_integrity_check', '__return_false' );
    }
}
