<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Helpers
 */

namespace RymeraWebCo\WWOF\Helpers;

use WC_Logger;

/**
 * Logger class to handle logging
 *
 * @since 3.0.8
 */
class Logger {
    /**
     * Logger
     *
     * @var WC_Logger
     */
    private static $logger;

    /**
     * Log a message.
     *
     * @param string $message The message to log.
     * @param string $level The log level.
     * @param array  $context The context of the log.
     *
     * @since 3.0.8
     * @return void
     */
    public static function log( $message, $level = 'info', $context = array() ) {
        if ( ! self::$logger ) {
            self::$logger = wc_get_logger();
        }

        self::$logger->log( $level, $message, $context );
    }

    /**
     * Log an error message.
     *
     * @param string $message The message to log.
     * @param array  $context The context of the log.
     *
     * @since 3.0.8
     * @return void
     */
    public static function error( $message, $context = array() ) {
        self::log( $message, 'error', $context );
    }

    /**
     * Log a warning message.
     *
     * @param string $message The message to log.
     * @param array  $context The context of the log.
     *
     * @since 3.0.8
     * @return void
     */
    public static function warning( $message, $context = array() ) {
        self::log( $message, 'warning', $context );
    }

    /**
     * Log an info message.
     *
     * @param string $message The message to log.
     * @param array  $context The context of the log.
     *
     * @since 3.0.8
     * @return void
     */
    public static function info( $message, $context = array() ) {
        self::log( $message, 'info', $context );
    }

    /**
     * Log a debug message.
     *
     * @param string $message The message to log.
     * @param array  $context The context of the log.
     *
     * @since 3.0.8
     * @return void
     */
    public static function debug( $message, $context = array() ) {
        self::log( $message, 'debug', $context );
    }
}
