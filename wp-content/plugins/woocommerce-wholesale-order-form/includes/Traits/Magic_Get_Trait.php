<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Traits
 */

namespace RymeraWebCo\WWOF\Traits;

trait Magic_Get_Trait {

    /**
     * Magic get method.
     *
     * @param string $key The key to get.
     *
     * @return null|mixed
     * @since 3.0
     */
    public function __get( $key ) {

        return $this->$key ?? null;
    }
}
