<?php

/*
|--------------------------------------------------------------------------
| Enable Timber Theme Support
|--------------------------------------------------------------------------
*/

/**
 * If you are installing Timber as a Composer dependency in your theme, you'll need this block
 * to load your dependencies and initialize Timber. If you are using Timber via the WordPress.org
 * plug-in, you can safely delete this block.
 */
$composer_autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $composer_autoload ) ) {
  require_once $composer_autoload;
  Timber\Timber::init();
}

/**
 * Sets the directories (inside your theme) to find .twig files
 */
Timber\Timber::$dirname = array( 'patterns' );

/**
 * By default, Timber does NOT autoescape values. Want to enable Twig's autoescape?
 * No prob! Just set this value to true
 */
Timber\Timber::$autoescape = false;

/**
 * Here's what's happening with these hooks:
 * 1. WordPress initially detects theme in themes/your-theme/resources
 * 2. Upon activation, we tell WordPress that the theme is actually in themes/your-theme/resources/views
 * 3. When we call get_template_directory() or get_template_directory_uri(), we point it back  to themes/your-theme/resources
 *
 * We do this so that the Template Hierarchy will look in themes/your-theme/resources/views for  core WordPress themes
 * But functions.php, style.css, and index.php are all still located in themes/your-theme/resources
 *
 * This is not compatible with the WordPress Customizer theme preview prior to theme activation
 *
 * get_template_directory()   -> /srv/www/example.com/current/web/app/themes/your-theme/resources
 * get_stylesheet_directory() -> /srv/www/example.com/current/web/app/themes/your-theme/resources
 * locate_template()
 * ├── STYLESHEETPATH         -> /srv/www/example.com/current/web/app/themes/your-theme/resources/views
 * └── TEMPLATEPATH           -> /srv/www/example.com/current/web/app/themes/your-theme/resources
 */

// Namespaces
add_filter('timber/loader/loader', function ($loader) {
  $base_paths = [
    get_stylesheet_directory(), // Child theme (should override)
    get_template_directory(),   // Parent theme fallback
  ];

  $pattern_dirs = [
    'atoms'      => '/resources/views/patterns/01-atoms',
    'molecules'  => '/resources/views/patterns/02-molecules',
    'organisms'  => '/resources/views/patterns/03-organisms',
    'templates'  => '/resources/views/patterns/04-templates',
  ];

  foreach ($base_paths as $base_path) {
    foreach ($pattern_dirs as $namespace => $relative_path) {
      $full_path = $base_path . $relative_path;

      if (file_exists($full_path)) {
        $loader->addPath($full_path, $namespace);
      }
    }
  }

  return $loader;
});

if ( ! class_exists( 'StarterSite' ) ) {
  /**
   * We're going to configure our theme inside of a subclass of Timber\Site
   * You can move this to its own file and include here via php's include("MySite.php")
   */
  class StarterSite extends Timber\Site {

    /**
     * Add timber support.
     */
    public function __construct() {
      add_action( 'after_setup_theme', array( $this, 'theme_supports' ) );
      add_filter( 'timber/context', array( $this, 'add_to_context' ) );
      add_filter( 'timber/twig', array( $this, 'add_to_twig' ) );

      parent::__construct();
    }

    /**
     * This is where you add some context
     *
     * @param string $context context['this'] Being the Twig's {{ this }}.
     */
    public function add_to_context( $context ) {
      $context['foo']  = 'bar';
      $context['site'] = $this;
      return $context;
    }

    /**
     * Custom WordPress functions.
     *
     * @param string $twig get extension.
     */
    public function add_to_twig( $twig ) {
      return $twig;
    }
  }
}

new StarterSite();

/*
|--------------------------------------------------------------------------
| Register The Bootloader
|--------------------------------------------------------------------------
|
| The first thing we will do is schedule a new Acorn application container
| to boot when WordPress is finished loading the theme. The application
| serves as the "glue" for all the components of Laravel and is
| the IoC container for the system binding all of the various parts.
|
*/

try {
  \Roots\bootloader();
} catch (Throwable $e) {
  wp_die(
    __('You need to install Acorn to use this theme.', 'angry-duck'),
    '',
    [
      'link_url' => 'https://docs.roots.io/acorn/2.x/installation/',
      'link_text' => __('Acorn Docs: Installation', 'angry-duck'),
    ]
  );
}

/*
|--------------------------------------------------------------------------
| Register Sage Theme Files
|--------------------------------------------------------------------------
|
| Out of the box, Sage ships with categorically named theme files
| containing common functionality and setup to be bootstrapped with your
| theme. Simply add (or remove) files from the array below to change what
| is registered alongside Sage.
|
*/

collect(['setup', 'filters'])
  ->each(function ($file) {
    if (! locate_template($file = "app/{$file}.php", true, true)) {
      wp_die(
        /* translators: %s is replaced with the relative file path */
        sprintf(__('Error locating <code>%s</code> for inclusion.', 'angry-duck'), $file)
      );
    }
  });

/*
|--------------------------------------------------------------------------
| Enable Sage Theme Support
|--------------------------------------------------------------------------
|
| Once our theme files are registered and available for use, we are almost
| ready to boot our application. But first, we need to signal to Acorn
| that we will need to initialize the necessary service providers built in
| for Sage when booting.
|
*/

add_theme_support('angry-duck');

/*
|--------------------------------------------------------------------------
| Register Additonal Functions
|--------------------------------------------------------------------------
*/
/**
 * Register Custom Theme Functions.
 */
$register_theme_functions = __DIR__ . '/resources/functions/custom-theme-functions.php';
if (file_exists($register_theme_functions)) {
  require_once $register_theme_functions;
}

/**
 * Register Custom Blocks.
 */
$register_custom_blocks = __DIR__ . '/resources/functions/custom-blocks.php';
if (file_exists($register_custom_blocks)) {
  require_once $register_custom_blocks;
}

// /**
//  * Register Custom Post Types.
//  */
// $register_custom_content_types = __DIR__ . '/resources/functions/custom-content-types.php';
// if (file_exists($register_custom_content_types)) {
//   require_once $register_custom_content_types;
// }

// /**
//  * Register Custom Taxonomies.
//  */
// $register_custom_taxonomy = __DIR__ . '/resources/functions/custom-taxonomy.php';
// if (file_exists($register_custom_taxonomy)) {
//   require_once $register_custom_taxonomy;
// }