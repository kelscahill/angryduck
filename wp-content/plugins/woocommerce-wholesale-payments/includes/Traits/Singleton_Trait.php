<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WPay\Traits
 */

namespace RymeraWebCo\WPay\Traits;

/**
 * Trait Trait_Instance
 *
 * @since 1.0.0
 */
trait Singleton_Trait {

    /**
     * Holds the class instance object
     *
     * @var static $instance object
     * @since 1.0.0
     */
    protected static $instance;

    /**
     * Return an instance of this class
     *
     * @param array ...$args The arguments to pass to the constructor.
     *
     * @codeCoverageIgnore
     *
     * @return static The class instance object
     * @since 1.0.0
     */
    public static function instance( ...$args ) {

        if ( null === static::$instance ) {
            static::$instance = new static( ...$args );
        }

        return static::$instance;
    }

    /**
     * Magic get method
     *
     * @param string $key Class property to get.
     *
     * @return null|mixed
     * @since 1.0.0
     */
    public function __get( $key ) {

        return $this->$key ?? null;
    }
}
