<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Classes
 */

namespace RymeraWebCo\WWOF\Classes;

use RymeraWebCo\WWOF\Abstracts\Admin_Page;
use RymeraWebCo\WWOF\Factories\Order_Forms_Query;
use RymeraWebCo\WWOF\Factories\Vite_App;
use RymeraWebCo\WWOF\Helpers\WWOF;
use RymeraWebCo\WWOF\Traits\Singleton_Trait;

if ( ! function_exists( 'get_editable_roles' ) ) {
    require_once ABSPATH . 'wp-admin/includes/user.php';
}

/**
 * Order_Forms_Admin_Page class.
 *
 * @since 3.0
 */
class Order_Forms_Admin_Page extends Admin_Page {

    use Singleton_Trait;

    /**
     * The admin page slug.
     *
     * @since 3.0
     */
    const MENU_SLUG = 'order-forms';

    /**
     * Holds the class instance object
     *
     * @since 3.0.6
     * @var static $instance object
     */
    protected static $instance;

    /**
     * Initialize the admin page.
     *
     * @since 3.0.6
     * @return void
     */
    public function init() {

        $this->page_title  = __( 'Order Forms', 'woocommerce-wholesale-order-form' );
        $this->menu_title  = __( 'Order Forms', 'woocommerce-wholesale-order-form' );
        $this->capability  = 'manage_woocommerce';
        $this->menu_slug   = self::MENU_SLUG;
        $this->template    = 'order-forms-admin-page.php';
        $this->icon        = '';
        $this->position    = 3;
        $this->parent_slug = 'wholesale-suite';
    }

    /**
     * Enqueue admin scripts.
     *
     * @since 3.0
     * @return void
     */
    public function enqueue_scripts() {

        /**
         * Filter to disable the order form admin page scripts.
         *
         * @param bool $disable Whether to disable the order form admin page scripts.
         *
         * @since 3.0
         */
        if ( apply_filters( 'wwof_disable_order_form_admin_page_scripts', false ) ) {
            return;
        }

        $order_forms = new Order_Forms_Query(
            array(
                'post_status'    => array( 'draft', 'publish' ),
                'orderby'        => 'ID',
                'order'          => 'DESC',
                'posts_per_page' => 10,
            ),
            'edit'
        );

        $admin_base_url_args = array( 'page' => self::MENU_SLUG );
        if ( defined( 'HMR_DEV' ) && HMR_DEV ) {
            $admin_base_url_args['hmr'] = 'wwof';
        }

        $editable_roles = array();
        foreach ( get_editable_roles() as $role => $details ) {
            if ( in_array( $role, array( 'administrator', 'shop_manager' ), true ) ) {
                continue;
            }
            $editable_roles[] = array(
                'label' => translate_user_role( $details['name'] ),
                'value' => $role,
            );
        }
        $l10n = WWOF::order_form_app_common_l10n(
            array(
                'appContext'    => 'edit',
                'adminBaseUrl'  => esc_url_raw(
                    add_query_arg( $admin_base_url_args, admin_url( 'admin.php', 'relative' ) )
                ),
                'orderForms'    => array(
                    'formPosts' => $order_forms->get_id_indexed_posts(),
                    'total'     => $order_forms->found_posts,
                    'pages'     => $order_forms->max_num_pages,
                ),
                'editableRoles' => $editable_roles,
            )
        );

        /***************************************************************************
         * Enqueue wp_editor scripts/styles
         ***************************************************************************
         *
         * We make sure the wp.editor scripts/styles are enqueued as we will be
         * using it in our app.
         */
        wp_enqueue_editor();
        wp_enqueue_media();

        $app = new Vite_App(
            self::MENU_SLUG,
            'src/apps/wwof/admin/index.ts',
            array(
                'wp-i18n',
                'wp-url',
                'wp-hooks',
                'wp-html-entities',
                'lodash',
                'wp-element',
            ),
            $l10n
        );
        /**
         * Filter to modify the order form admin page app script.
         *
         * Hook name: `wwof_class_admin_page_order-forms_app_script`
         *
         * @param Vite_App               $app  The order form admin page app script.
         * @param Order_Forms_Admin_Page $this The order form admin page object.
         *
         * @since 3.0
         */
        $app = apply_filters(
            "wwof_class_admin_page_{$this->menu_slug}_app_script",
            $app,
            $this
        );
        $app->enqueue();

        /**
         * Executes right after the order form admin app script is enqueued.
         *
         * @since 3.0
         */
        do_action( 'wwof_enqueue_admin_order_forms_sc_script' );
    }

    /**
     * Unregisters WPML styles.
     *
     * @since 3.0.2
     * @return void
     */
    public function unregister_wpml_styles() {

        wp_deregister_style( 'sitepress-style' );
        wp_deregister_style( 'wpml-tm-styles' );
    }

    /**
     * Additional hooks for the admin page.
     *
     * @since 3.0
     * @since 3.0.2 Unregisters WPML styles.
     * @return void
     */
    public function run_admin_page_hooks() {

        add_action( 'admin_enqueue_scripts', array( $this, 'unregister_wpml_styles' ), 20 );
    }

    /**
     * Get the admin menu priority.
     *
     * @since 3.0.6
     * @return int
     */
    protected function get_priority() {

        return 100;
    }
}
