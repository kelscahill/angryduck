<?php
/**
 *
 * @file
 * Register custom theme functions.
 *
 * @package WordPress
 */

/**
 * ACF Save json files
 */
add_filter('acf/settings/save_json', 'my_acf_json_save_point');
function my_acf_json_save_point($path) {
  $path = get_stylesheet_directory() . '/acf-json';
  return $path;
}

/**
 * Use ACF options site wide
 */
add_filter('timber/context', 'angry_duck_timber_context');
function angry_duck_timber_context($context) {
  $context['options'] = get_fields('option');
  $context['password_protected'] = post_password_required();
  $context['sidebar'] = Timber\Timber::get_widgets('sidebar');
  $context['primary_nav'] = Timber\Timber::get_menu('Primary Navigation');
  $context['footer_nav']  = Timber\Timber::get_menu('Footer Navigation');

  /* WooCommerce */
  global $woocommerce;
  $context['woocommerce'] = $woocommerce;
  $context['cart_count'] = $woocommerce->cart->cart_contents_count;

  return $context;
}

/**
 * ACF Options Page
 */
if (function_exists('acf_add_options_page')) {
  acf_add_options_page(array(
    'page_title'  => 'Theme General Settings',
    'menu_title'  => 'Theme Settings',
    'menu_slug'   => 'theme-general-settings',
    'capability'  => 'edit_posts',
    'redirect'    => false
  ));
}

/**
 * Allow SVG's through WP media uploader
 */
add_filter('upload_mimes', 'cc_mime_types');
function cc_mime_types($mimes) {
  $mimes['svg'] = 'image/svg+xml';
  $mimes['zip'] = 'application/zip';
  $mimes['gz'] = 'application/x-gzip';
  return $mimes;
}

function timber_set_product($post) {
  global $product;

  if (is_woocommerce()) {
    $product = wc_get_product( $post->ID );
  }
}

// Disable Woocommerce default css.
add_filter('woocommerce_enqueue_styles', '__return_empty_array');
