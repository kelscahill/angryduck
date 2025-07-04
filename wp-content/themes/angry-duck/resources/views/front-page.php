<?php
/**
 * The template for displaying all pages.
 *
 * This is the template that displays all pages by default.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site will use a
 * different template.
 *
 * To generate specific templates for your pages you can use:
 * /mytheme/views/page-mypage.twig
 * (which will still route through this PHP file)
 * OR
 * /mytheme/page-mypage.php
 * (in which case you'll want to duplicate this file and save to the above path)
 *
 * Methods for TimberHelper can be found in the /lib sub-directory
 *
 * @package  WordPress
 * @subpackage  Timber
 * @since    Timber 0.1
 */

$context = Timber\Timber::context();
$post = Timber\Timber::get_post();
$context['post'] = $post;
$context['is_front_page'] = 'true';

$featured_products_args = array(
  'post_type' => 'product',
  'posts_per_page' => 4,
  'post_status' => 'publish',
  'tax_query' => array(
    array(
      'taxonomy' => 'product_cat',
      'field' => 'slug',
      'terms' => 'sauce',
    )
  )
);
$context['featured_products'] = Timber\Timber::query_posts($featured_products_args);

$testimonials_args = array(
  'post_type' => 'testimonial',
  'posts_per_page' => -1,
  'post_status' => 'publish'
);
$context['testimonials'] = Timber\Timber::query_posts($testimonials_args);

Timber\Timber::render(array('05-pages/page-' . $post->post_name . '.twig', '05-pages/front-page.twig'), $context);
