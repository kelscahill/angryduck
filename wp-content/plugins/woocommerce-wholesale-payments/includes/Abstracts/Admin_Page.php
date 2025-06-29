<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WPay\Abstracts
 */

namespace RymeraWebCo\WPay\Abstracts;

use RymeraWebCo\WPay\Abstracts\Abstract_Class;
use RymeraWebCo\WPay\Helpers\Helper;

/**
 * Class AdminPage
 *
 * @since 1.0.0
 */
abstract class Admin_Page extends Abstract_Class {

    /**
     * Holds the admin page hook suffix.
     *
     * @since 1.0.0
     * @var string The admin page hook suffix.
     */
    protected $hook_suffix;

    /**
     * Holds the admin page title.
     *
     * @since 1.0.0
     * @var string The admin page title.
     */
    protected $page_title;

    /**
     * Holds the admin menu title.
     *
     * @since 1.0.0
     * @var string The admin menu title.
     */
    protected $menu_title;

    /**
     * Holds the admin menu capability.
     *
     * @since 1.0.0
     * @var string The admin menu capability.
     */
    protected $capability;

    /**
     * Holds the admin menu slug.
     *
     * @since 1.0.0
     * @var string The admin menu slug.
     */
    protected $menu_slug;

    /**
     * Holds the admin menu callback.
     *
     * @since 1.0.0
     * @var string|callable|array|null The admin menu callback.
     */
    protected $callback;

    /**
     * Holds the admin menu icon file path.
     *
     * @since 1.0.0
     * @var string|null The admin menu icon.
     */
    protected $icon;

    /**
     * Holds the admin menu position.
     *
     * @since 1.0.0
     * @var int|float|null The admin menu position.
     */
    protected $position;

    /**
     * Holds the parent admin page slug if we are creating a submenu page.
     *
     * @since 1.0.0
     * @var string The parent admin page if this is a sub menu page.
     */
    protected $parent_slug;

    /**
     * Holds the admin page template file relative to `templates/admin`.
     *
     * @since 1.0.0
     * @var string
     */
    protected $template;

    /**
     * Get class property.
     *
     * @param string $key Class property name.
     *
     * @since 1.0.0
     * @return mixed|null
     */
    public function __get( $key ) {

        return $this->$key ?? null;
    }

    /**
     * Initialize the admin page.
     *
     * @since 1.0.0
     * @return void
     */
    abstract protected function init();

    /**
     * Get the admin menu priority.
     *
     * @since 1.0.0
     * @return int
     */
    protected function get_priority() {

        return 10;
    }

    /**
     * Add the admin page to the admin menu.
     *
     * @since 1.0.0
     * @return void
     */
    public function admin_menu() {

        $this->init();

        /***************************************************************************
         * Set as sub-menu page or menu page
         ***************************************************************************
         *
         * Set as sub-menu page or menu page based on the parent_slug property.
         */
        if ( $this->parent_slug ) {
            $this->hook_suffix = add_submenu_page(
                $this->parent_slug,
                $this->page_title,
                $this->menu_title,
                $this->capability,
                $this->menu_slug,
                array( $this, 'load_admin_page' ),
                $this->position
            );
        } else {
            $this->hook_suffix = add_menu_page(
                $this->page_title,
                $this->menu_title,
                $this->capability,
                $this->menu_slug,
                array( $this, 'load_admin_page' ),
                $this->icon,
                $this->position
            );
        }

        add_action( "load-$this->hook_suffix", array( $this, 'load_admin_page_hooks' ) );
    }

    /**
     * Maybe enqueue app scripts.
     *
     * @param string $hook_suffix The current admin page hook suffix.
     *
     * @since 1.0.0
     * @return void
     */
    public function admin_enqueue_scripts( $hook_suffix ) {

        if ( $this->hook_suffix === $hook_suffix && method_exists( $this, 'enqueue_scripts' ) ) {
            $this->enqueue_scripts();
        }
    }

    /**
     * Load admin page hooks.
     *
     * @since 1.0.0
     * @return void
     */
    public function load_admin_page_hooks() {

        if ( method_exists( $this, 'run_admin_page_hooks' ) ) {
            $this->run_admin_page_hooks();
        }
    }

    /**
     * Render the admin page.
     *
     * @since 1.0.0
     * @return void
     */
    public function load_admin_page() {

        Helper::locate_admin_template( $this->template, true );
    }

    /**
     * Add hook handlers for rendering the admin page.
     *
     * @since 1.0.0
     * @return void
     */
    public function run() {

        add_action( 'admin_menu', array( $this, 'admin_menu' ), $this->get_priority() );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
    }
}
