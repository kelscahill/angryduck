<?php
/**
 * License admin notice translations.
 *
 * @package RymeraWebCo\WPay
 */

defined( 'ABSPATH' ) || exit;

return array(
    'noLicense' => array(
        'lt_7'   => array(
            'title'           => __( 'Oops! Did you forget to enter your license for Wholesale Payments?', 'woocommerce-wholesale-payments' ),
            /* translators: %1$s = opening <a> tag; %2$s = closing </a> tag; %3$s = opening <strong> tag; %4$s = closing </strong> tag */
            'desc'            => __( 'Enter your license to fully activate Wholesale Payments and gain access to automatic updates, technical support, and premium features. If you don\'t have a license, you can %1$spurchase one%2$s or continue to use the plugin but %3$swe will be collecting a 3%% processing fee%4$s.', 'woocommerce-wholesale-payments' ),
            /* translators: %1$s = opening <a> tag; %2$s = closing </a> tag */
            'cantFindKey'     => __( 'Can\'t find your key? %1$sLogin to your account.%2$s', 'woocommerce-wholesale-payments' ),
            /* translators: %1$s = opening <a> tag; %2$s = closing </a> tag */
            'dontHaveLicense' => __( 'Don\'t have a license yet? %1$sPurchase one here.%2$s', 'woocommerce-wholesale-payments' ),
        ),
        'lt_14'  => array(
            /* translators: %1$s = opening <strong class="wpay-tw-text-red"> tag; %2$s = closing </strong> tag; %3$s = opening <strong> tag; %4$s = closing </strong> tag */
            'title' => __( '%1$sAction required!%2$s %3$sEnter your license for Wholesale Payments%2$s to continue.' ),
            /* translators: %1$s = opening <a> tag; %2$s = closing </a> tag; %3$s = opening <strong> tag; %4$s = closing </strong> tag */
            'desc'  => __( 'Don\'t worry, your wholesale customers & orders are completely safe and payment plans are still working. But you will need to enter a license key to continue using Wholesale Payments. If you don\'t have a license, you can %1$spurchase one%2$s or continue to use the plugin but %3$swe will be collecting a 3%% processing fee%4$s.', 'woocommerce-wholesale-payments' ),
        ),
        'gte_14' => array(
            /* translators: %1$s = opening <strong> tag; %2$s = closing </strong> tag */
            'title'  => __( '%1$sUrgent!%2$s Your Wholesale Payments license is missing!', 'woocommerce-wholesale-payments' ),
            'desc'   => __( 'Without an active license, your website checkout process using Wholesale Payments will still continue to work but we will be adding a 3% payment fee to generated invoices.', 'woocommerce-wholesale-payments' ),
            'footer' => array(
                'button'    => __( 'Enter Your License Now', 'woocommerce-wholesale-payments' ),
                /* translators: %1$s = opening <a> tag; %2$s = closing </a> tag */
                'learnMore' => __( 'Learn more', 'woocommerce-wholesale-payments' ),
            ),
        ),
    ),
    'preExpiry' => array(
        /* translators: %1$s = opening <strong> tag; %2$s = closing </strong> tag; %3$d = number of days until expiry */
        'title'  => __( '%1$sAction Required!%2$s Your Wholesale Payments license is about to expire in %3$d day(s).', 'woocommerce-wholesale-payments' ),
        /* translators: %1$d = number of days until expiry; %2$s = expiry date */
        'desc'   => __(
            'Your Wholesale Payments license is about to expire in %1$d day(s) and automatic renewals are turned off.
         The current license will expire on %2$s. Once expired, you won\'t be able to make changes to your payment plans, receive plugin updates or support, and we will be adding a 3%% payment fee to generated invoices.
         To avoid interruptions, simply reactivate your subscription.',
            'woocommerce-wholesale-payments'
        ),
        'footer' => array(
            'loginReactivate' => __( 'Login & Reactivate', 'woocommerce-wholesale-payments' ),
            'learnMore'       => __( 'Learn more', 'woocommerce-wholesale-payments' ),
        ),
    ),
    'expired'   => array(
        'lt_14'  => array(
            /* translators: %1$s = opening <strong> tag; %2$s = closing </strong> tag */
            'title'  => __( '%1$sOh no!%2$s Your Wholesale Payments license has expired!', 'woocommerce-wholesale-payments' ),
            /* translators: %1$s = opening <a> tag; %2$s = closing </a> tag; %3$s = opening <strong> tag; %4$s = closing </strong> tag */
            'desc'   => __(
                'Don\'t worry, your wholesale customers & orders are completely safe and payment plans are still working.
             But you will need to enter a license key to continue using Wholesale Payments.
              If you don\'t have a license, you can %1$spurchase one%2$s or continue to use the plugin but %3$swe will be collecting a 3%% processing fee%4$s.',
                'woocommerce-wholesale-payments'
            ),
            'footer' => array(
                'renewLicense' => __( 'Renew License', 'woocommerce-wholesale-payments' ),
                'learnMore'    => __( 'Learn more', 'woocommerce-wholesale-payments' ),
            ),
        ),
        'gte_14' => array(
            /* translators: %1$s = opening <strong> tag; %2$s = closing </strong> tag */
            'title' => __( '%1$sUrgent!%2$s Your Wholesale Payments license has expired!', 'woocommerce-wholesale-payments' ),
        ),
    ),
);
