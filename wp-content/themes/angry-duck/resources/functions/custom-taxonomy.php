<?php
/**
 *
 * @file
 * Register custom taxonomies.
 *
 * @package WordPress
 */

/**
 * Registers custom taxonomies.
 */
function register_custom_taxonomy() {
  /**
   * Taxonomy: Example.
   */

  // $labels = array(
  //   "name" => __( "Examples", "sage" ),
  //   "singular_name" => __( "Example", "sage" ),
  // );

  // $args = array(
  //   "label" => __( "Example", "sage" ),
  //   "labels" => $labels,
  //   "public" => true,
  //   "hierarchical" => true,
  //   "show_ui" => true,
  //   "show_in_menu" => true,
  //   "show_in_nav_menus" => true,
  //   "query_var" => true,
  //   "rewrite" => array( 'slug' => 'example', 'with_front' => true, ),
  //   "show_admin_column" => true,
  //   "show_in_rest" => true,
  //   "rest_base" => "",
  //   "show_in_quick_edit" => true,
  // );
  // register_taxonomy( "example", array( "custom_post_type_name" ), $args );
}
add_action('init', 'register_custom_taxonomy');
