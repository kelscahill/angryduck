<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Integrations
 */

namespace RymeraWebCo\WWOF\Integrations;

use RymeraWebCo\WWOF\Abstracts\Abstract_Class;
use RymeraWebCo\WWOF\Helpers\WP_Rocket as WP_Rocket_Helper;
use RymeraWebCo\WWOF\Shortcodes\WWOF_Product_Listing;
use WP_Query;

/**
 * WP_Rocket class.
 *
 * @since 3.0.1
 */
class WP_Rocket extends Abstract_Class {

    /**
     * Exclude the wholesale order form from WP Rocket cache.
     *
     * @param bool $filter Array of URIs to exclude from cache.
     *
     * @since 3.0.1
     * @return bool
     */
    public function exclude_page_from_cache( $filter ) {

        $shortcode = WWOF_Product_Listing::instance()->tag;
        $args      = array(
            's'                      => "[$shortcode",
            'post_type'              => 'page',
            'post_status'            => 'publish',
            'posts_per_page'         => -1,
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'fields'                 => 'ids',
        );

        $query = new WP_Query( $args );
        if ( $query->have_posts() && in_array( get_the_ID(), $query->posts, true ) ) {
            $filter = false;
        }

        return $filter;
    }

    /**
     * Run WP Rocket integration hooks.
     *
     * @since 3.0.1
     */
    public function run() {

        if ( ! WP_Rocket_Helper::is_active() ) {
            return;
        }

        add_filter( 'do_rocket_generate_caching_files', array( $this, 'exclude_page_from_cache' ) );
    }
}
