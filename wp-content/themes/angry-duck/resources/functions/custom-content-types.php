<?php
/**
 *
 * @file
 * Register custom content types.
 *
 * @package WordPress
 */

function register_custom_post_types() {
  /*
   * Post Type: Testimonials.
   */

   $labels = array(
    "name" => __('Testimonials', 'sage'),
    "singular_name" => __('Testimonial', 'sage'),
  );

  $args = array(
    "label" => __("Testimonials", "sage"),
    "labels" => $labels,
    "description" => "",
    "public" => false,
    "publicly_queryable" => true,
    "show_ui" => true,
    "delete_with_user" => false,
    "show_in_rest" => true,
    "rest_base" => "",
    "rest_controller_class" => "WP_REST_Posts_Controller",
    "has_archive" => false,
    "show_in_menu" => true,
    "show_in_nav_menus" => true,
    "exclude_from_search" => false,
    "capability_type" => "post",
    "menu_icon" => "dashicons-format-quote",
    "map_meta_cap" => true,
    "hierarchical" => true,
    "rewrite" => array("slug" => "testimonial", "with_front" => true),
    "query_var" => true,
    "menu_position" => 20,
    "supports" => array("title")
  );

  register_post_type("testimonial", $args);
}
add_action('init', 'register_custom_post_types');
