<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WPay\Traits
 */

namespace RymeraWebCo\WPay\Traits;

trait Magic_Get_Trait {

    /**
     * Magic get method.
     *
     * @param string $key The key to get.
     *
     * @return null|mixed
     * @since 1.0.0
     */
    public function __get( $key ) {

        return $this->$key ?? null;
    }
}
