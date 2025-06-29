<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WPay
 */

namespace RymeraWebCo\WPay\Abstracts;

use RymeraWebCo\WPay\Traits\Magic_Get_Trait;

/**
 * Abstract_Cache class.
 *
 * @since 1.0.0
 */
abstract class Abstract_Cache {

    use Magic_Get_Trait;

    /**
     * Holds the cache group value.
     *
     * @since 1.0.0
     * @var string Cache group.
     */
    protected $cache_group;

    /**
     * Holds the cache key value.
     *
     * @since 1.0.0
     * @var string Cache key.
     */
    protected $cache_key;

    /**
     * Holds transient key name.
     *
     * @var string Transient key name.
     */
    protected $transient_key;

    /**
     * True if object cache is available, otherwise false to use transients.
     *
     * @var bool Whether to use object cache or transients.
     */
    protected $use_object_cache;

    /**
     * Holds the callback function or method to use to get the data.
     *
     * @var callable
     */
    protected $get_data_callback;

    /**
     * Cache constructor.
     *
     * @param string   $cache_key         Cache key name.
     * @param callable $get_data_callback The callback function or method to use to get the data.
     * @param null     $cache_group       Cache group name.
     */
    public function __construct( $cache_key, $get_data_callback, $cache_group = null ) {

        /***************************************************************************
         * Check object cache
         ***************************************************************************
         *
         * Let's check if we can use object cache.
         */
        $this->use_object_cache = wp_using_ext_object_cache();

        /***************************************************************************
         * Cache Group and Key
         ***************************************************************************
         *
         * Set the cache group and key for this cache.
         */
        $this->cache_group   = $cache_group ?? strtolower( wp_basename( get_class( $this ) ) );
        $this->cache_key     = $cache_key;
        $this->transient_key = $this->cache_group . '_' . $this->cache_key;

        /***************************************************************************
         * Set the callback function or method
         ***************************************************************************
         *
         * Set the callback function or method to use to get the data before
         * caching.
         */
        $this->get_data_callback = $get_data_callback;
    }

    /**
     * Get value from cache. Use `wp_cache_get()` if `use_object_cache` property is true, otherwise use
     * `get_transient()`. Use `transient_key` class property to access transient key name.
     *
     * @param bool $force Whether to force an update of the local cache from the persistent cache. Default false.
     *
     * @return mixed
     */
    abstract public function get( $force = false );

    /**
     * Set cache value. Use `wp_cache_set()` if `use_object_cache` property is true, otherwise use
     * `set_transient()`. Use `transient_key` class property to access transient key name.
     *
     * @param mixed $value      Cache value to set.
     * @param int   $expiration Cache expiration time in seconds.
     *
     * @return mixed
     */
    abstract public function set( $value, $expiration = 0 );

    /**
     * Delete cache value.
     *
     * @return bool
     */
    public function delete() {

        /***************************************************************************
         * Delete data from object cache
         ***************************************************************************
         *
         * We delete cache data from object cache if it is available.
         */
        if ( $this->use_object_cache ) {
            return wp_cache_delete( $this->cache_key, $this->cache_group );
        }

        /***************************************************************************
         * Delete data from transient cache
         ***************************************************************************
         *
         * Otherwise, we delete cache data from transient cache.
         */

        return delete_transient( $this->transient_key );
    }

    /**
     * Serialize data.
     *
     * @return array
     */
    public function __serialize() {

        /***************************************************************************
         * Serialize class properties
         ***************************************************************************
         *
         * We serialize the class properties if the class is serialized.
         */
        return array(
            'cache_group'      => $this->cache_group,
            'cache_key'        => $this->cache_key,
            'transient_key'    => $this->transient_key,
            'use_object_cache' => $this->use_object_cache,
        );
    }

    /**
     * Unserialize object.
     *
     * @param array $data Cache object properties.
     *
     * @return void
     */
    public function __unserialize( $data ) {

        /***************************************************************************
         * Restore class properties
         ***************************************************************************
         *
         * We restore the class properties if the class is unserialized.
         */
        $this->cache_group      = $data['cache_group'];
        $this->cache_key        = $data['cache_key'];
        $this->transient_key    = $data['transient_key'];
        $this->use_object_cache = $data['use_object_cache'];
    }
}
