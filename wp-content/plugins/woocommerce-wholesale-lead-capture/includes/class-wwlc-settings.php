<?php
/**
 * WooCommerce Wholesale Lead Capture Settings
 *
 * @author      Rymera Web
 * @version     1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WWLC_Settings' ) ) {

    /**
     * Model that house various settings of the plugin.
     *
     * @since 1.17.5
     */
    class WWLC_Settings extends WC_Settings_Page {


        /**
         * Constructor.
         */
        public function __construct() {
            $this->id    = 'wwlc_settings';
            $this->label = __( 'Wholesale Lead', 'woocommerce-wholesale-lead-capture' );

            add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_page' ), 30 ); // 30 so it is after the emails tab.
            add_action( 'woocommerce_settings_' . $this->id, array( $this, 'output' ), 90 );
            add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save' ), 20 );
            add_action( 'woocommerce_sections_' . $this->id, array( $this, 'output_sections' ), 90 );

            add_action( 'woocommerce_admin_field_wwlc_button', array( $this, 'render_wwlc_button' ) );
            add_action( 'woocommerce_admin_field_wwlc_custom_fields_control', array( $this, 'render_wwlc_custom_fields_control' ) );
            add_action( 'woocommerce_admin_field_wwlc_help_resources_controls', array( $this, 'render_wwlc_help_resources_controls' ) );
            add_action( 'woocommerce_admin_field_wwlc_chosen_select', array( $this, 'wwlc_chosen_select_field' ) );
            add_filter( 'woocommerce_admin_field_wwlc_wysiwyg_editor', array( $this, 'render_wwlc_wysiwyg_editor' ) );

            // Built-in Fields.
            add_action( 'woocommerce_admin_field_built_in_fields', array( $this, 'built_in_fields' ) );
            add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save_built_in_fields' ), 20 );

            add_action( 'updated_option', array( $this, 'wwlc_emails_options' ), 10, 3 );

            // Remove wc params on wwlc setting that has no save changes button.
            add_filter( 'woocommerce_settings_tabs_array', array( $this, 'remove_settings_params' ), 10, 1 );

            // Sanitize account upgrade message.
            add_filter( 'woocommerce_admin_settings_sanitize_option_wwlc_account_upgrade_message', array( $this, 'sanitize_wwlc_account_upgrade_message' ), 10, 3 );

            if ( is_main_site() ) {
                add_action( 'woocommerce_admin_field_wwlc_force_fetch_update_data_button', array( $this, 'render_plugin_settings_custom_field_wwlc_force_fetch_update_data_button' ), 10 );
            }
        }

        /**
         * Remove WC Params on Custom Fields tab.
         *
         * @param array $tabs Array of tabs.
         * @return array
         * @since 1.14.7
         */
        public function remove_settings_params( $tabs ) {

            if ( isset( $_GET['tab'] ) && 'wwlc_settings' === $_GET['tab'] && isset( $_GET['section'] ) && 'wwlc_setting_custom_fields_section' === $_GET['section'] ) { // phpcs:ignore.
                wp_dequeue_script( 'woocommerce_settings' );
            }

            return $tabs;
        }

        /**
         * Get sections.
         *
         * @return array
         * @since 1.0.0
         */
        public function get_sections() {
            $sections = array(
                ''                                   => __( 'General', 'woocommerce-wholesale-lead-capture' ),
                'wwlc_setting_fields_section'        => __( 'Built In Fields', 'woocommerce-wholesale-lead-capture' ),
                'wwlc_setting_custom_fields_section' => __( 'Custom Fields', 'woocommerce-wholesale-lead-capture' ),
                'wwlc_setting_email_section'         => __( 'Emails', 'woocommerce-wholesale-lead-capture' ),
                'wwlc_settings_security_section'     => __( 'Security', 'woocommerce-wholesale-lead-capture' ),
                'wwlc_settings_help_section'         => __( 'Help', 'woocommerce-wholesale-lead-capture' ),
            );

            return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
        }

        /**
         * Output the settings.
         *
         * @since 1.0.0
         */
        public function output() {
            global $wpdb, $current_section, $sitepress;

            $settings = $this->get_settings( $current_section );

            // WWLC-265 WPML Compatibility. Ability to save translations in WWLC settings, email section.
            if ( isset( $current_section ) && 'wwlc_setting_email_section' === $current_section ) { ?>
                
                <div id="message" class="inline updated woocommerce-message">
                    <p><?php echo wp_kses_post( __( '<strong>Wholesale Lead Capture email template settings have moved!</strong> &#8211; Go to &quot;WooCommerce > Emails&quot; to edit your email templates.', 'woocommerce-wholesale-lead-capture' ) ); ?></p>
                    <p class="submit"><a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=email' ) ); ?>" class="button-primary"><?php esc_html_e( 'Go to Email Setting', 'woocommerce-wholesale-lead-capture' ); ?></a></p>
                </div>


                <?php
                if ( is_object( $sitepress ) && defined( 'ICL_LANGUAGE_CODE' ) ) {

                    $options = $this->get_translation_strings();

                    if ( $options ) {

                        foreach ( $options as $option ) {

                            add_filter(
                                'option_' . $option,
                                function ( $value, $option ) {

                                if ( isset( $_POST[ $option ] ) && strcmp( $_POST[ $option ], $value ) != 0 ) { // phpcs:ignore.
                                    return $_POST[ $option ]; // phpcs:ignore.
                                }

                                return $value;
                                },
                                90,
                                2
                            );

                        }
                    }
                }
            }

            WC_Admin_Settings::output_fields( $settings );
        }

        /**
         * Save settings.
         *
         * @since 1.0.0
         */
        public function save() {
            global $wpdb, $current_section, $sitepress;

            // We have a different saving style for Built In Fields.
            if ( 'wwlc_setting_fields_section' === $current_section ) {
                return;
            }

            $settings = $this->get_settings( $current_section );

            // Filter wysiwyg content so it gets stored properly after sanitization.
            if ( ! empty( $_POST['EmailContent'] ) && isset( $_POST['EmailContent'] ) ) { // phpcs:ignore.

                foreach ( $_POST['EmailContent'] as $index => $content ) { // phpcs:ignore.

                    $_POST[ $index ] = htmlentities( wpautop( $content ) );

                }
            }

            // WWLC-265 WPML Compatibility. Ability to save translations in WWLC settings, email section.
            if ( isset( $current_section ) && 'wwlc_setting_email_section' === $current_section && is_object( $sitepress ) && defined( 'ICL_LANGUAGE_CODE' ) ) {

                // If user switched to a different language.
                if ( $sitepress->get_default_language() !== ICL_LANGUAGE_CODE ) {

                    foreach ( $_POST as $option_name => $option_value ) { // phpcs:ignore.

                        $string_id = $wpdb->get_row( $wpdb->prepare( "SELECT s.id FROM {$wpdb->prefix}icl_strings s WHERE s.name = %s", $option_name ) );

                        if ( is_object( $string_id ) ) {
                            $this->save_translation( $string_id->id, $option_name, $option_value, ICL_LANGUAGE_CODE );
                        }
                    }
                } else {
                    // Default language.
                    WC_Admin_Settings::save_fields( $settings );

                }
            } else {
                WC_Admin_Settings::save_fields( $settings );
            }
        }

        /**
         * Returns a list of LC Emails option name.
         *
         * @return array
         * @since 1.10
         */
        private function get_translation_strings() {
            global $wpdb;
            $email_options = array();

            $strings = $wpdb->get_results( "SELECT name FROM {$wpdb->prefix}icl_strings s WHERE name LIKE 'wwlc_emails_%'" );

            if ( $strings ) {
                foreach ( $strings as $option ) {
                    $email_options[] = $option->name;
                }
            }

            return $email_options;
        }

        /**
         * When the main language is updated mark the translations as complete.
         * This fixes the issue where when the main language is updated the other translated languages will mark as "Translation Needs Update".
         * That is normal behaviour set by WPML.
         *
         * @param int    $string_id The String ID.
         * @param string $option_name The option name.
         * @param string $option_value The option value.
         * @param string $language_code The language code.
         *
         * @return void
         * @since 1.10
         */
        private function save_translation( $string_id, $option_name, $option_value, $language_code ) {

            global $WPML_String_Translation;

            $data = array();

            $data['icl_st_string_id']            = $string_id;
            $data['icl_st_translation']          = $option_value;
            $data['icl_st_language']             = $language_code;
            $data['icl_st_translation_complete'] = true;

            // We need to do this so that it won't echo the status message, we will just use the function set by wpml when saving translations.
            ob_start();
            $WPML_String_Translation->ajax_calls( 'icl_st_save_translation', $data );
            $output = ob_get_contents();
            ob_end_clean();
        }

        /**
         * When the main language is updated mark the translations as complete.
         * This fixes the issue where when the main language is updated the other translated languages will mark as "Translation Needs Update".
         * That is normal behaviour set by WPML.
         *
         * @param string $option_name The option name.
         * @param string $old_value The old option value.
         * @param string $value The new option value.
         *
         * @return void
         * @since 1.10
         */
        public function wwlc_emails_options( $option_name, $old_value, $value ) {

            global $current_section, $wpdb, $sitepress;

            if ( is_object( $sitepress ) && isset( $current_section ) && 'wwlc_setting_email_section' === $current_section && defined( 'ICL_LANGUAGE_CODE' ) && $sitepress->get_default_language() === ICL_LANGUAGE_CODE ) {

                $options = $this->get_translation_strings();

                if ( ! empty( $options ) && in_array( $option_name, $options, true ) ) {

                    $string_id = $wpdb->get_row( $wpdb->prepare( "SELECT s.id FROM {$wpdb->prefix}icl_strings s WHERE s.name = %s", $option_name ) );

                    if ( is_object( $string_id ) ) {

                        $wpdb->update(
                            $wpdb->prefix . 'icl_strings',
                            array(
								'value'  => $value,
								'status' => ICL_TM_COMPLETE,
                            ),
                            array( 'id' => $string_id->id )
                        );
                        $wpdb->update( $wpdb->prefix . 'icl_string_translations', array( 'status' => ICL_TM_COMPLETE ), array( 'string_id' => $string_id->id ) );

                    }
                }
            }
        }

        /**
         * Get settings array.
         *
         * @param string $current_section Current section name.
         *
         * @return mixed
         * @since 1.0.0
         */
        public function get_settings( $current_section = '' ) {

            if ( 'wwlc_settings_help_section' === $current_section ) {

                // Help Section.
                $settings = apply_filters( 'wwlc_settings_help_section_settings', $this->_get_help_section_settings() );

            } elseif ( 'wwlc_setting_fields_section' === $current_section ) {

                // Built In Fields Section.
                $settings = apply_filters( 'wwlc_settings_fields_section_settings', $this->_get_fields_section_settings() );

            } elseif ( 'wwlc_setting_custom_fields_section' === $current_section ) {

                // Custom Fields Section.
                $settings = apply_filters( 'wwlc_setting_custom_fields_section_settings', $this->_get_custom_fields_section_settings() );

            } elseif ( 'wwlc_setting_email_section' === $current_section ) {

                // Email Section.
                $settings = apply_filters( 'wwlc_settings_email_section_settings', $this->_get_email_section_settings() );

            } elseif ( 'wwlc_settings_security_section' === $current_section ) {

                // Security Section.
                $settings = apply_filters( 'wwlc_settings_security_section_settings', $this->_get_security_section_setting() );
            } else {

                // General Settings.
                $settings = apply_filters( 'wwlc_settings_general_section_settings', $this->_get_general_section_settings() );

            }

            return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings, $current_section );
        }

        /**
         * Register wwlc_chosen_select WooCommerce settings field.
         *
         * @since 1.7.0
         * @access public
         *
         * @param array $value Field option value.
         */
        public function wwlc_chosen_select_field( $value ) {

            $custom_attributes = array();
            $custom_value      = '';
            $option_value      = WC_Admin_Settings::get_option( $value['id'], $value['default'] );
            $field_description = WC_Admin_Settings::get_field_description( $value );

            extract( $field_description ); // phpcs:ignore.

            // custom value.
            if ( ! array_key_exists( $option_value, $value['options'] ) ) {
                $custom_value = $option_value;
                $option_value = 'custom';
            }

            if ( ! empty( $value['custom_attributes'] ) && is_array( $value['custom_attributes'] ) ) {
                foreach ( $value['custom_attributes'] as $attribute => $attribute_value ) {
                    $custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $attribute_value ) . '"';
                }
            }
            ?>

			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr( $value['id'] ); ?>">
						<?php echo esc_html( $value['title'] ); ?>
						<?php echo wp_kses_post( $tooltip_html ); ?>
					</label>
				</th>
				<td class="forminp wwlc_chosen_select forminp-<?php echo esc_attr( sanitize_title( $value['type'] ) ); ?>">
					<select
						name="<?php echo esc_attr( $value['id'] ); ?>"
						id="<?php echo esc_attr( $value['id'] ); ?>"
						style="<?php echo esc_attr( $value['css'] ); ?>"
						class="<?php echo esc_attr( $value['class'] ); ?>"
						<?php echo esc_attr( implode( ' ', $custom_attributes ) ); ?>
						>
                    <?php
                        foreach ( $value['options'] as $page_id => $page_title ) {
                    ?>
                    <option url="<?php echo ! empty( $page_id ) ? esc_url( get_permalink( $page_id ) ) : ''; ?>" value="<?php echo esc_attr( $page_id ); ?>" 
                    <?php

                    if ( is_array( $option_value ) ) {
                        selected( in_array( $page_id, $option_value, true ), true );
                    } else {
                        selected( $option_value, $page_id );
                    }
                    ?>
                    ><?php echo esc_html( $page_title ); ?></option>
                    <?php } ?>

					</select> <?php echo wp_kses_post( $description ); ?>
					<div class="custom-input-wrap">
						<input class="custom-link-input" type="url" placeholder="<?php esc_attr_e( 'Enter custom link', 'woocommerce-wholesale-lead-capture' ); ?>" name="<?php echo esc_attr( $value['id'] ); ?>" id="<?php echo esc_attr( $value['id'] ); ?>_custom" value="<?php echo esc_url( $custom_value ); ?>" style="display:none;" disabled>
					</div>
				</td>
			</tr>
        <?php
        }

        /*
        |--------------------------------------------------------------------------------------------------------------
        | Section Settings
        |--------------------------------------------------------------------------------------------------------------
         */

        /**
         * Get general section settings.
         *
         * @return array
         * @since 1.0.0
         * @since 1.6.9 WWLC-174 : Remove Administrator and Shop Manager to the "New Lead Role" options
         * @since 1.7.0 WWLC-201 : Add support for WPML plugin by querying the $allPages options with the default language.
         * @since 1.7.1 Remove {password} template tag on admin email settings description, and added it on the new user email template description.
         * @since 1.8.0 We now store the page id rather than the page url.
         */
        private function _get_general_section_settings() {
            // Get all user roles.
            global $wp_roles, $sitepress;

            if ( ! isset( $wp_roles ) ) {
                $wp_roles = new WP_Roles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
            }

            $allUserRoles = $wp_roles->get_names();

            if ( array_key_exists( 'administrator', $allUserRoles ) ) {
                unset( $allUserRoles['administrator'] );
            }

            if ( array_key_exists( 'shop_manager', $allUserRoles ) ) {
                unset( $allUserRoles['shop_manager'] );
            }

            // WPML support: change the language back to the default language.
            if ( is_object( $sitepress ) ) {
                $sitepress->switch_lang( $sitepress->get_default_language() );
            }

            // Get all pages.
            $allPages     = array(
				''       => __( 'Select a Page', 'woocommerce-wholesale-lead-capture' ),
				'custom' => __( '--- Custom Link ---', 'woocommerce-wholesale-lead-capture' ),
			);
            $allPagesList = get_pages();

            if ( $allPagesList ) {
                foreach ( $allPagesList as $page ) {
                    $allPages[ $page->ID ] = $page->post_title;
                }
            }

            // WPML support: change the language back to the current language.
            if ( is_object( $sitepress ) ) {
                $sitepress->switch_lang( ICL_LANGUAGE_CODE );
            }

            return array(

                array(
                    'title' => __( 'Pages', 'woocommerce-wholesale-lead-capture' ),
                    'type'  => 'title',
                    'desc'  => __( 'Set the pages related to wholesale registration, login and your wholesale program\'s terms.', 'woocommerce-wholesale-lead-capture' ),
                    'id'    => 'wwlc_pages_title',
                ),

                array(
                    'title'   => __( 'Wholesale Login', 'woocommerce-wholesale-lead-capture' ),
                    'type'    => 'wwlc_chosen_select',
                    'desc'    => __( 'This is the page where your wholesale customers login to their accounts.', 'woocommerce-wholesale-lead-capture' ),
                    'default' => '',
                    'id'      => 'wwlc_general_login_page',
                    'css'     => 'width: 275px',
                    'class'   => 'chosen_select',
                    'options' => $allPages,
                ),

                array(
                    'title'   => __( 'Wholesale Registration', 'woocommerce-wholesale-lead-capture' ),
                    'type'    => 'wwlc_chosen_select',
                    'desc'    => __( 'This is the page where new leads can register for a wholesale account.', 'woocommerce-wholesale-lead-capture' ),
                    'default' => '',
                    'id'      => 'wwlc_general_registration_page',
                    'css'     => 'width: 275px',
                    'class'   => 'chosen_select',
                    'options' => $allPages,
                ),

                array(
                    'type' => 'checkbox',
                    'desc' => __( 'Allow logged-in admin and shop managers to view registration page', 'woocommerce-wholesale-lead-capture' ),
                    'id'   => 'wwlc_enable_admin_registration_page_view',
                ),

                array(
                    'title'   => __( 'Wholesale Registration Thank You', 'woocommerce-wholesale-lead-capture' ),
                    'type'    => 'wwlc_chosen_select',
                    'desc'    => __( 'This is where customers are redirected immediately after registering for their wholesale account.', 'woocommerce-wholesale-lead-capture' ),
                    'default' => '',
                    'id'      => 'wwlc_general_registration_thankyou',
                    'css'     => 'width: 275px',
                    'class'   => 'chosen_select',
                    'options' => $allPages,
                ),

                array(
                    'title'   => __( 'Login Redirect', 'woocommerce-wholesale-lead-capture' ),
                    'type'    => 'wwlc_chosen_select',
                    'desc'    => __( 'This is where wholesale customers are redirected immediately after logging in.', 'woocommerce-wholesale-lead-capture' ),
                    'default' => '',
                    'id'      => 'wwlc_general_login_redirect_page',
                    'css'     => 'width: 275px',
                    'class'   => 'chosen_select',
                    'options' => $allPages,
                ),

                array(
                    'title'   => __( 'Logout Redirect', 'woocommerce-wholesale-lead-capture' ),
                    'type'    => 'wwlc_chosen_select',
                    'desc'    => __( 'This is where wholesale customers are redirected immediately after logging out.', 'woocommerce-wholesale-lead-capture' ),
                    'default' => '',
                    'id'      => 'wwlc_general_logout_redirect_page',
                    'css'     => 'width: 275px',
                    'class'   => 'chosen_select',
                    'options' => $allPages,
                ),

                array(
                    'title'   => __( 'Terms & Conditions', 'woocommerce-wholesale-lead-capture' ),
                    'type'    => 'wwlc_chosen_select',
                    'desc'    => __( 'If you are showing terms & conditions on your registration form, this is where they will link to.', 'woocommerce-wholesale-lead-capture' ),
                    'default' => '',
                    'id'      => 'wwlc_general_terms_and_condition_page_url',
                    'css'     => 'width: 275px',
                    'class'   => 'chosen_select',
                    'options' => $allPages,
                ),

                array(
                    'type' => 'sectionend',
                    'id'   => 'wwlc_pages_sectionend',
                ),

                array(
                    'title' => __( 'Lead Actions', 'woocommerce-wholesale-lead-capture' ),
                    'type'  => 'title',
                    'desc'  => __( 'These settings describe what happens to when processing a new wholesale lead.', 'woocommerce-wholesale-lead-capture' ),
                    'id'    => 'wwlc_lead_actions_title',
                ),

                array(
                    'title'             => __( 'New Lead Role', 'woocommerce-wholesale-lead-capture' ),
                    'type'              => 'select',
                    'desc'              => __( 'This is the user role that your wholesale customers will receive once they have been approved.', 'woocommerce-wholesale-lead-capture' ),
                    'id'                => 'wwlc_general_new_lead_role',
                    'class'             => 'chosen_select',
                    'css'               => 'width: 275px',
                    'custom_attributes' => array(
                        'data-placeholder' => __( 'Select Some User Roles...', 'woocommerce-wholesale-lead-capture' ),
                    ),
                    'options'           => $allUserRoles,
                ),

                array(
                    'title'    => __( 'Auto Approve New Leads', 'woocommerce-wholesale-lead-capture' ),
                    'type'     => 'radio',
                    'desc'     => '',
                    'id'       => 'wwlc_general_auto_approve_new_leads',
                    'desc_tip' => __( 'Note: When it\'s set to Automatic Approval, users will be automatically logged-in after successful registration.', 'woocommerce-wholesale-lead-capture' ),
                    'options'  => array(
                        'no'  => __( 'Manual Approval Required – new registrations are held in a moderation queue and require an administrator or store manager to approve them.', 'woocommerce-wholesale-lead-capture' ),
                        'yes' => __( 'Automatic Approval – new registrations are automatically approved after submitting the wholesale registration form.', 'woocommerce-wholesale-lead-capture' ),
                    ),
                ),

                array(
                    'title' => __( 'Account Upgrade', 'woocommerce-wholesale-lead-capture' ),
                    'type'  => 'checkbox',
                    'desc'  => __( 'Allow existing customers to request an account upgrade. Existing customers must fill-up and submit the lead capture registration form to request an upgrade. Note: once approved, the role will be based on the New Lead Role set above.', 'woocommerce-wholesale-lead-capture' ),
                    'id'    => 'wwlc_enable_account_upgrade',
                ),

                array(
                    'title' => __( 'Show Account Upgrade Message', 'woocommerce-wholesale-lead-capture' ),
                    'type'  => 'checkbox',
                    'desc'  => __( 'Display account upgrade message and link to upgrade account registration form in "My account" page for standard customers.', 'woocommerce-wholesale-lead-capture' ),
                    'id'    => 'wwlc_show_account_upgrade',
                ),

                array(
                    'title' => __( 'Auto Remove User After Rejection', 'woocommerce-wholesale-lead-capture' ),
                    'type'  => 'checkbox',
                    'desc'  => __( 'Automatically remove user after rejection. This will remove the user from the database after the admin rejects the user.', 'woocommerce-wholesale-lead-capture' ),
                    'id'    => 'wwlc_remove_user_after_rejection',
                ),

                array(
                    'title'    => __( 'Upgrade Message', 'woocommerce-wholesale-lead-capture' ),
                    'type'     => 'wwlc_wysiwyg_editor',
                    'desc'     => '',
                    /* translators: %s: {link} {/link} */
                    'desc_tip' => __( 'Text to appear in user account page.', 'woocommerce-wholesale-lead-capture' ) . '<br/>' . sprintf( __( 'Available placeholders: %s', 'woocommerce-wholesale-lead-capture' ), '{link}, {/link}' ),
                    'id'       => 'wwlc_account_upgrade_message',
                    'css'      => '',
                    'default'  => __( 'You are registered as a standard customer. Click here to {link}request an account upgrade{/link}.', 'woocommerce-wholesale-lead-capture' ),
                    'settings' => array(
                        'textarea_name' => 'wwlc_account_upgrade_message',
                        'textarea_rows' => '4',
                        'media_buttons' => true,
                        'tinymce'       => array(
                            'toolbar1' => 'formatselect,bold,italic,underline,separator,bullist,numlist,blockquote,alignleft,aligncenter,alignright,alignjustify,link,unlink,undo,redo',
                        ),
                    ),
                ),

                array(
                    'type' => 'sectionend',
                    'id'   => 'wwlc_lead_actions_sectionend',
                ),

            );
        }

        /**
         * Get fields section settings.
         *
         * @return array
         * @since 1.0.0
         */
        private function _get_fields_section_settings() {
            $build_in_fields = apply_filters(
                'wwlc_built_in_fields',
                array(
					// First Name.
					'firstname' => array(
						'order'       => array(
							'title' => __( 'Field Order', 'woocommerce-wholesale-lead-capture' ),
							'type'  => 'number',
							'id'    => 'wwlc_fields_first_name_field_order',
							'value' => get_option( 'wwlc_fields_first_name_field_order' ),
						),

						'placeholder' => array(
							'title' => __( 'Placeholder Text', 'woocommerce-wholesale-lead-capture' ),
							'type'  => 'text',
							'id'    => 'wwlc_fields_first_name_field_placeholder',
							'value' => get_option( 'wwlc_fields_first_name_field_placeholder' ),
						),
					),

					// Last Name.
					'lastname'  => array(
						'order'       => array(
							'title' => __( 'Field Order', 'woocommerce-wholesale-lead-capture' ),
							'type'  => 'number',
							'id'    => 'wwlc_fields_last_name_field_order',
							'value' => get_option( 'wwlc_fields_last_name_field_order' ),
						),

						'placeholder' => array(
							'title' => __( 'Placeholder Text', 'woocommerce-wholesale-lead-capture' ),
							'type'  => 'text',
							'id'    => 'wwlc_fields_last_name_field_placeholder',
							'value' => get_option( 'wwlc_fields_last_name_field_placeholder' ),
						),
					),

					// Email.
					'email'     => array(
						'order'       => array(
							'title' => __( 'Field Order', 'woocommerce-wholesale-lead-capture' ),
							'type'  => 'number',
							'id'    => 'wwlc_fields_email_field_order',
							'value' => get_option( 'wwlc_fields_email_field_order' ),
						),

						'placeholder' => array(
							'title' => __( 'Placeholder Text', 'woocommerce-wholesale-lead-capture' ),
							'type'  => 'text',
							'id'    => 'wwlc_fields_email_field_placeholder',
							'value' => get_option( 'wwlc_fields_email_field_placeholder' ),
						),
					),

					// Username.
					'username'  => array(
						'type'        => 'text',
						'desc_tip'    => true,
						'desc'        => __( 'If enabled, this field is automatically required. When disabled, the Email Address will be used as the user’s username instead.', 'woocommerce-wholesale-lead-capture' ),
						'enable'      => array(
							'title' => __( 'Enabled', 'woocommerce-wholesale-lead-capture' ),
							'type'  => 'checkbox',
							'id'    => 'wwlc_fields_username_active',
							'value' => get_option( 'wwlc_fields_username_active' ),
						),

						'order'       => array(
							'title' => __( 'Field Order', 'woocommerce-wholesale-lead-capture' ),
							'type'  => 'number',
							'id'    => 'wwlc_fields_username_order',
							'value' => get_option( 'wwlc_fields_username_order' ),
						),

						'placeholder' => array(
							'title' => __( 'Placeholder Text', 'woocommerce-wholesale-lead-capture' ),
							'type'  => 'text',
							'id'    => 'wwlc_fields_username_placeholder',
							'value' => get_option( 'wwlc_fields_username_placeholder' ),
						),
					),

					// Password.
					'password'  => array(
						'type'                => 'password',
						'desc_tip'            => true,
						'desc'                => __( 'If enabled, but not required or when disabled, the password will be automatically generated for the user and communicated via email.', 'woocommerce-wholesale-lead-capture' ),
						'enable'              => array(
							'title' => __( 'Enabled', 'woocommerce-wholesale-lead-capture' ),
							'type'  => 'checkbox',
							'id'    => 'wwlc_fields_activate_password_field',
							'value' => get_option( 'wwlc_fields_activate_password_field' ),
						),

						'required'            => array(
							'title' => __( 'Required', 'woocommerce-wholesale-lead-capture' ),
							'type'  => 'checkbox',
							'id'    => 'wwlc_fields_require_password_field',
							'value' => get_option( 'wwlc_fields_require_password_field' ),
						),

						'order'               => array(
							'title' => __( 'Field Order', 'woocommerce-wholesale-lead-capture' ),
							'type'  => 'number',
							'id'    => 'wwlc_fields_password_field_order',
							'value' => get_option( 'wwlc_fields_password_field_order' ),
						),

						'placeholder'         => array(
							'title' => __( 'Placeholder Text', 'woocommerce-wholesale-lead-capture' ),
							'type'  => 'text',
							'id'    => 'wwlc_fields_password_field_placeholder',
							'value' => get_option( 'wwlc_fields_password_field_placeholder' ),
						),

						'enable_confirm'      => array(
							'title' => __( 'Add Password Confirmation Field', 'woocommerce-wholesale-lead-capture' ),
							'type'  => 'checkbox',
							'id'    => 'wwlc_fields_enable_confirm_password_field',
							'value' => get_option( 'wwlc_fields_enable_confirm_password_field' ),
						),

						'confirm_placeholder' => array(
							'title' => __( 'Confirm Placeholder Text', 'woocommerce-wholesale-lead-capture' ),
							'type'  => 'text',
							'id'    => 'wwlc_fields_confirm_password_field_placeholder',
							'value' => get_option( 'wwlc_fields_confirm_password_field_placeholder' ),
						),

                        'minimum_strength'    => array(
							'title' => __( 'Strong password', 'woocommerce-wholesale-lead-capture' ),
							'type'  => 'checkbox',
							'id'    => 'wwlc_fields_minimum_password_strength_field',
							'value' => get_option( 'wwlc_fields_minimum_password_strength_field' ),
						),

					),

					// Company.
					'company'   => array(
						'enable'      => array(
							'title' => __( 'Enabled', 'woocommerce-wholesale-lead-capture' ),
							'type'  => 'checkbox',
							'id'    => 'wwlc_fields_activate_company_name_field',
							'value' => get_option( 'wwlc_fields_activate_company_name_field' ),
						),

						'required'    => array(
							'title' => __( 'Required', 'woocommerce-wholesale-lead-capture' ),
							'type'  => 'checkbox',
							'id'    => 'wwlc_fields_require_company_name_field',
							'value' => get_option( 'wwlc_fields_require_company_name_field' ),
						),

						'order'       => array(
							'title' => __( 'Field Order', 'woocommerce-wholesale-lead-capture' ),
							'type'  => 'number',
							'id'    => 'wwlc_fields_company_name_field_order',
							'value' => get_option( 'wwlc_fields_company_name_field_order' ),
						),

						'placeholder' => array(
							'title' => __( 'Placeholder Text', 'woocommerce-wholesale-lead-capture' ),
							'type'  => 'text',
							'id'    => 'wwlc_fields_company_field_placeholder',
							'value' => get_option( 'wwlc_fields_company_field_placeholder' ),
						),
					),

					// Address.
					'address'   => array(
						'enable'               => array(
							'title' => __( 'Enabled', 'woocommerce-wholesale-lead-capture' ),
							'type'  => 'checkbox',
							'id'    => 'wwlc_fields_activate_address_field',
							'value' => get_option( 'wwlc_fields_activate_address_field' ),
						),

						'required'             => array(
							'title' => __( 'Required', 'woocommerce-wholesale-lead-capture' ),
							'type'  => 'checkbox',
							'id'    => 'wwlc_fields_require_address_field',
							'value' => get_option( 'wwlc_fields_require_address_field' ),
						),

						'enable_add_line2'     => array(
							'title' => __( 'Add Address Line 2 Label.', 'woocommerce-wholesale-lead-capture' ),
							'type'  => 'checkbox',
							'id'    => 'wwlc_fields_enable_address2_label',
							'value' => get_option( 'wwlc_fields_enable_address2_label' ),
						),

						'order'                => array(
							'title' => __( 'Field Order', 'woocommerce-wholesale-lead-capture' ),
							'type'  => 'number',
							'id'    => 'wwlc_fields_address_field_order',
							'value' => get_option( 'wwlc_fields_address_field_order' ),
						),

						'placeholder'          => array(
							'title' => __( 'Address Placeholder', 'woocommerce-wholesale-lead-capture' ),
							'type'  => 'text',
							'id'    => 'wwlc_fields_address_placeholder',
							'value' => get_option( 'wwlc_fields_address_placeholder' ),
						),

						'address2_placeholder' => array(
							'title' => __( 'Address Line 2 Placeholder', 'woocommerce-wholesale-lead-capture' ),
							'type'  => 'text',
							'id'    => 'wwlc_fields_address2_placeholder',
							'value' => get_option( 'wwlc_fields_address2_placeholder' ),
						),

						'city_placeholder'     => array(
							'title' => __( 'Town/City Placeholder', 'woocommerce-wholesale-lead-capture' ),
							'type'  => 'text',
							'id'    => 'wwlc_fields_city_placeholder',
							'value' => get_option( 'wwlc_fields_city_placeholder' ),
						),

						'state'                => array(
							'title' => __( 'State/County Placeholder', 'woocommerce-wholesale-lead-capture' ),
							'type'  => 'text',
							'id'    => 'wwlc_fields_state_placeholder',
							'value' => get_option( 'wwlc_fields_state_placeholder' ),
						),

						'zip'                  => array(
							'title' => __( 'Postcode/Zip Placeholder', 'woocommerce-wholesale-lead-capture' ),
							'type'  => 'text',
							'id'    => 'wwlc_fields_postcode_placeholder',
							'value' => get_option( 'wwlc_fields_postcode_placeholder' ),
						),
					),

					// Phone.
					'phone'     => array(
						'required'    => array(
							'title' => __( 'Required', 'woocommerce-wholesale-lead-capture' ),
							'type'  => 'checkbox',
							'id'    => 'wwlc_fields_require_phone_field',
							'value' => get_option( 'wwlc_fields_require_phone_field' ),
						),

						'order'       => array(
							'title' => __( 'Field Order', 'woocommerce-wholesale-lead-capture' ),
							'type'  => 'number',
							'id'    => 'wwlc_fields_phone_field_order',
							'value' => get_option( 'wwlc_fields_phone_field_order' ),
						),

						'placeholder' => array(
							'title' => __( 'Placeholder Text', 'woocommerce-wholesale-lead-capture' ),
							'type'  => 'text',
							'id'    => 'wwlc_fields_phone_field_placeholder',
							'value' => get_option( 'wwlc_fields_phone_field_placeholder' ),
						),

						'mask'        => array(
							'title'   => __( 'Phone mask/pattern', 'woocommerce-wholesale-lead-capture' ),
							'type'    => 'select',
							'id'      => 'wwlc_fields_phone_mask_pattern',
							'options' => array(
								'No format'         => __( 'No format', 'woocommerce-wholesale-lead-capture' ),
								'(000) 000-0000'    => '(000) 000-0000',
								'+00 (000) 000-000' => '+00 (000) 000-000',
								'0 000 000 0000'    => '0 000 000 0000',
								'+0 000 000-000'    => '+0 000 000-000',
								'0-000-000-0000'    => '0-000-000-0000',
								'0 (000) 000-0000'  => '0 (000) 000-0000',
								'000-000-0000'      => '000-000-0000',
								'000.000.0000'      => '000.000.0000',
								'(00) 0000 0000'    => '(00) 0000 0000',
								'0000 000 000'      => '0000 000 000',
							),
							'value'   => get_option( 'wwlc_fields_phone_mask_pattern' ),
						),
					),

					'terms'     => array(
						'enable' => array(
							'title' => __( 'Enabled', 'woocommerce-wholesale-lead-capture' ),
							'type'  => 'checkbox',
                            // translators: %s: General Settings URL.
							'desc'  => sprintf( __( 'This field always appear at the bottom of the form. Please ensure you select a value for the Terms & Conditions Page on the <a href="%s">General Settings</a>.', 'woocommerce-wholesale-lead-capture' ), admin_url( 'admin.php?page=wc-settings&tab=wwlc_settings&section' ) ),
							'id'    => 'wwlc_general_show_terms_and_conditions',
							'value' => get_option( 'wwlc_general_show_terms_and_conditions' ),
						),
					),

                )
            );

            return array(

                array(
                    'title' => __( 'Built-in Fields', 'woocommerce-wholesale-lead-capture' ),
                    'type'  => 'title',
                    'desc'  => __( 'Built-in fields are fields that are common to many different types of wholesale industries. There are some fields that <b>must appear</b> on your registration form in order to create the user. There are also some built-in fields that are optional, meaning that can be omitted from the form if you don\'t need to collect them.', 'woocommerce-wholesale-lead-capture' ),
                    'id'    => 'wwlc_fields_main_title',
                ),

                array(
                    'type'   => 'built_in_fields',
                    'id'     => 'built_in_fields',
                    'fields' => $build_in_fields,
                ),

                array(
                    'type' => 'sectionend',
                    'id'   => 'wwlc_fields_sectionend',
                ),

            );
        }

        /**
         * Get custom fields section settings.
         *
         * @return array
         * @since 1.1.0
         */
        private function _get_custom_fields_section_settings() {
            return array(

                array(
                    'name' => __( 'Custom Fields Options', 'woocommerce-wholesale-lead-capture' ),
                    'type' => 'title',
                    'desc' => __( 'Here you can add additional fields to be added on the lead capture registration form.', 'woocommerce-wholesale-lead-capture' ),
                    'id'   => 'wwlc_custom_fields_main_title',
                ),

                array(
                    'name' => '',
                    'type' => 'wwlc_custom_fields_control',
                    'desc' => '',
                    'id'   => 'wwlc_custom_fields_custom_control',
                ),

                array(
                    'type' => 'sectionend',
                    'id'   => 'wwlc_custom_fields_sectionend',
                ),

            );
        }

        /**
         * Get email section settings.
         *
         * @return array
         * @since 1.0.0
         * @since 1.8.0 Added new option 'Allow managing of users via email'
         * @since 1.10 WPML Compatibility in Email Section
         */
        private function _get_email_section_settings() {
            global $sitepress, $wc_wholesale_prices;
            $wholesaleRoleTemplateTag = ( $wc_wholesale_prices instanceof WooCommerceWholeSalePrices ) ? '<b>{user_wholesale_role}</b>, ' : '';

            // WPML Support.
            if ( is_object( $sitepress ) && defined( 'ICL_LANGUAGE_CODE' ) && ICL_LANGUAGE_CODE ) {
                $sitepress->switch_lang( ICL_LANGUAGE_CODE );
            }

            return array(

                array(
                    'title' => __( 'Emails Options', 'woocommerce-wholesale-lead-capture' ),
                    'type'  => 'title',
                    'desc'  => '',
                    'id'    => 'wwlc_emails_main_title',
                ),

                array(
                    'title' => __( 'Allow managing of users via email', 'woocommerce-wholesale-lead-capture' ),
                    'type'  => 'checkbox',
                    'desc'  => __( 'If enabled, the admin will see accept and reject action links in their email. Available on HTML email type only.', 'woocommerce-wholesale-lead-capture' ),
                    'id'    => 'wwlc_email_allow_managing_of_users',
                ),

                array(
                    'title' => __( 'Admin Email Recipient', 'woocommerce-wholesale-lead-capture' ),
                    'type'  => 'text',
                    'desc'  => __( 'If blank, then WordPress admin email will be used', 'woocommerce-wholesale-lead-capture' ),
                    'id'    => 'wwlc_emails_main_recipient',
                    'css'   => 'min-width:600px',
                ),

                array(
                    'title' => __( 'Carbon Copy (CC)', 'woocommerce-wholesale-lead-capture' ),
                    'type'  => 'text',
                    'desc'  => '',
                    'id'    => 'wwlc_emails_cc',
                    'css'   => 'min-width:600px',
                ),

                array(
                    'title' => __( 'Blind Carbon Copy (BCC)', 'woocommerce-wholesale-lead-capture' ),
                    'type'  => 'text',
                    'desc'  => '',
                    'id'    => 'wwlc_emails_bcc',
                    'css'   => 'min-width:600px',
                ),

                array(
                    'type' => 'sectionend',
                    'id'   => 'wwlc_emails_sectionend',
                ),

            );
        }

        /**
         * Get help section settings.
         *
         * @return array
         * @since 1.0.0
         */
        private function _get_help_section_settings() {
            return array(

                array(
                    'title' => __( 'Help Options', 'woocommerce-wholesale-lead-capture' ),
                    'type'  => 'title',
                    'desc'  => '',
                    'id'    => 'wwlc_help_main_title',
                ),

                array(
                    'name' => '',
                    'type' => 'wwlc_help_resources_controls',
                    'desc' => '',
                    'id'   => 'wwlc_help_help_resources',
                ),

                array(
                    'title' => __( 'Create Necessary Pages', 'woocommerce-wholesale-lead-capture' ),
                    'type'  => 'wwlc_button',
                    'desc'  => __( 'Registration, Log In Form and Thank You Page', 'woocommerce-wholesale-lead-capture' ),
                    'id'    => 'wwlc_help_create_wwlc_pages',
                    'class' => 'button button-primary',
                    'name'  => __( 'Create Lead Pages', 'woocommerce-wholesale-lead-capture' ),
                ),

                array(
                    'name' => __( 'Clean up plugin options on un-installation', 'woocommerce-wholesale-lead-capture' ),
                    'type' => 'checkbox',
                    'desc' => __( 'If checked, removes all plugin options when this plugin is uninstalled. <b>Warning:</b> This process is irreversible.', 'woocommerce-wholesale-lead-capture' ),
                    'id'   => 'wwlc_settings_help_clean_plugin_options_on_uninstall',
                ),

                array(
                    'name' => '',
                    'type' => 'wwlc_force_fetch_update_data_button',
                    'desc' => '',
                    'id'   => 'wwlc_settings_force_fetch_update_data_button',
                ),

                array(
                    'type' => 'sectionend',
                    'id'   => 'wwlc_help_sectionend',
                ),

            );
        }

        /**
         * Get security section settings.
         *
         * @return array
         * @since 1.0.0
         */
        private function _get_security_section_setting() {
            return array(

                array(
                    'title' => __( 'Security', 'woocommerce-wholesale-lead-capture' ),
                    'type'  => 'title',
                    'desc'  => '',
                    'id'    => 'wwlc_security_main_title',
                ),

                array(
                    'title' => __( 'Enable Recaptcha', 'woocommerce-wholesale-lead-capture' ),
                    'type'  => 'checkbox',
                    'desc'  => __( 'If checked, this will add the recaptcha field on the registration form. You can get your Recaptcha keys by going to <a href="https://www.google.com/recaptcha/">https://www.google.com/recaptcha/</a>', 'woocommerce-wholesale-lead-capture' ),
                    'id'    => 'wwlc_security_enable_recaptcha',
                ),

                array(
                    'title'   => __( 'reCAPTCHA type', 'woocommerce-wholesale-lead-capture' ),
                    'type'    => 'select',
                    'desc'    => __( 'Select your reCAPTCHA type. Make sure to use site key and secret key for your selected type.', 'woocommerce-wholesale-lead-capture' ),
                    'id'      => 'wwlc_security_recaptcha_type',
                    'options' => array(
                        'v2_im_not_a_robot' => __( 'V2 I\'m not a robot', 'woocommerce-wholesale-lead-capture' ),
                        'v2_invisible'      => __( 'V2 Invisible', 'woocommerce-wholesale-lead-capture' ),
                    ),
                    'css'     => 'min-width: 200px',
                ),

                array(
                    'title' => __( 'Recaptcha site key', 'woocommerce-wholesale-lead-capture' ),
                    'type'  => 'text',
                    'desc'  => '',
                    'id'    => 'wwlc_security_recaptcha_site_key',
                    'css'   => 'width:300px;',
                ),

                array(
                    'title' => __( 'Recaptcha secret key', 'woocommerce-wholesale-lead-capture' ),
                    'type'  => 'text',
                    'desc'  => '',
                    'id'    => 'wwlc_security_recaptcha_secret_key',
                    'css'   => 'width:300px;',
                ),

                array(
                    'type' => 'sectionend',
                    'id'   => 'wwlc_security_sectionend',
                ),
            );
        }

        /*
        |--------------------------------------------------------------------------------------------------------------
        | Custom Settings Fields
        |--------------------------------------------------------------------------------------------------------------
         */

        /**
         * Render custom setting field ( wwlc button )
         *
         * @param array $value Setting field value.
         * @since 1.0.0
         */
        public function render_wwlc_button( $value ) {

            // Change type accordingly.
            $type = $value['type'];
            if ( 'wwlc_button' === $type ) {
                $type = 'button';
            }

            // Custom attribute handling.
            $custom_attributes = array();

            if ( ! empty( $value['custom_attributes'] ) && is_array( $value['custom_attributes'] ) ) {
                foreach ( $value['custom_attributes'] as $attribute => $attribute_value ) {
                    $custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $attribute_value ) . '"';
                }
            }

            ob_start();
            ?>

			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr( $value['id'] ); ?>">
						<?php echo esc_html( $value['title'] ); ?>
						<?php echo wp_kses_post( wc_help_tip( $value['desc'], true ) ); ?>
					</label>
				</th>
				<td class="forminp forminp-<?php echo esc_attr( sanitize_title( $value['type'] ) ); ?>">
					<input
						name="<?php echo esc_attr( $value['id'] ); ?>"
						id="<?php echo esc_attr( $value['id'] ); ?>"
						type="<?php echo esc_attr( $type ); ?>"
						style="<?php echo esc_attr( $value['css'] ); ?>"
						value="<?php echo esc_attr( $value['name'] ); ?>"
						class="<?php echo esc_attr( $value['class'] ); ?>"
						<?php echo esc_attr( implode( ' ', $custom_attributes ) ); ?>
						/>
					<span class="spinner" style="margin-top: 3px; float: none;"></span>

				</td>
			</tr>

			<?php
            echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        /**
         * Render custom fields control ( custom fields section )
         *
         * @since 1.1.0
         * @since 1.6.2 Option to add default value to field types
         */
        public function render_wwlc_custom_fields_control() {
            wp_nonce_field( 'wwlc_custom_fields_nonce', 'wwlc_cf_nonce' );
            $custom_fields = get_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, array() );
            ?>

            <tr valign="top">
                <th colspan="2" scope="row" class="titledesc">
                    <div class="custom-field-controls">

                        <div class="field-container text-field-container">

                            <label for="wwlc_cf_field_name"><?php esc_html_e( 'Field Name', 'woocommerce-wholesale-lead-capture' ); ?></label>
                            <input type="text" id="wwlc_cf_field_name"/>

                        </div>

                        <div class="field-container text-field-container">

                            <label for="wwlc_cf_field_id"><?php esc_html_e( 'Field ID', 'woocommerce-wholesale-lead-capture' ); ?></label>
                            <div class="wwlc-cf-field-id-container">
                            	<span>wwlc_cf_</span><input type="text" id="wwlc_cf_field_id"/>
                            </div>
                            <p class="desc"><?php esc_html_e( 'Must be unique. Letters, numbers and underscores only. Value will be automatically prepended with "wwlc_cf_"', 'woocommerce-wholesale-lead-capture' ); ?></p>

                        </div>

                        <div class="field-container select-field-container">

                            <label for="wwlc_cf_field_type"><?php esc_html_e( 'Field Type', 'woocommerce-wholesale-lead-capture' ); ?></label>
                            <select id="wwlc_cf_field_type">
                                <option value="text"><?php esc_html_e( 'Text', 'woocommerce-wholesale-lead-capture' ); ?></option>
                                <option value="textarea"><?php esc_html_e( 'Text Area', 'woocommerce-wholesale-lead-capture' ); ?></option>
                                <option value="number"><?php esc_html_e( 'Number', 'woocommerce-wholesale-lead-capture' ); ?></option>
                                <option value="email"><?php esc_html_e( 'Email', 'woocommerce-wholesale-lead-capture' ); ?></option>
                                <option value="url"><?php esc_html_e( 'Url', 'woocommerce-wholesale-lead-capture' ); ?></option>
                                <option value="select"><?php esc_html_e( 'Select', 'woocommerce-wholesale-lead-capture' ); ?></option>
                                <option value="radio"><?php esc_html_e( 'Radio', 'woocommerce-wholesale-lead-capture' ); ?></option>
								<option value="checkbox"><?php esc_html_e( 'Checkbox', 'woocommerce-wholesale-lead-capture' ); ?></option>
								<option value="hidden"><?php esc_html_e( 'Hidden', 'woocommerce-wholesale-lead-capture' ); ?></option>
								<option value="file"><?php esc_html_e( 'File', 'woocommerce-wholesale-lead-capture' ); ?></option>
                                <option value="content"><?php esc_html_e( 'Content', 'woocommerce-wholesale-lead-capture' ); ?></option>
								<option value="terms_conditions"><?php esc_html_e( 'Terms & Conditions', 'woocommerce-wholesale-lead-capture' ); ?></option>
                            </select>

                        </div>

                        <div class="field-container attributes-container numeric-field-attributes-container">
                            <div>
                                <label for="wwlc_cf_attrib_numeric_min"><?php esc_html_e( 'Min:', 'woocommerce-wholesale-lead-capture' ); ?></label>
                                <input type="number" id="wwlc_cf_attrib_numeric_min" class="wwlc_cf_attrib_numeric" />
                            </div>

                            <div>
                                <label for="wwlc_cf_attrib_numeric_max"><?php esc_html_e( 'Max:', 'woocommerce-wholesale-lead-capture' ); ?></label>
                                <input type="number" id="wwlc_cf_attrib_numeric_max" class="wwlc_cf_attrib_numeric" />
                            </div>

                            <div>
                                <label for="wwlc_cf_attrib_numeric_step"><?php esc_html_e( 'Step:', 'woocommerce-wholesale-lead-capture' ); ?></label>
                                <input type="number" id="wwlc_cf_attrib_numeric_step" class="wwlc_cf_attrib_numeric" />
                            </div>
                        </div>

                        <div class="field-container options-container select-field-options-container">
                            <strong><?php esc_html_e( 'Options', 'woocommerce-wholesale-lead-capture' ); ?></strong>
                            <ul class="options-list"></ul>
                        </div>

                        <div class="field-container options-container radio-field-options-container">
                            <strong><?php esc_html_e( 'Options', 'woocommerce-wholesale-lead-capture' ); ?></strong>
                            <ul class="options-list"></ul>
                        </div>

                        <div class="field-container options-container checkbox-field-options-container">
                            <strong><?php esc_html_e( 'Options', 'woocommerce-wholesale-lead-capture' ); ?></strong>
                            <ul class="options-list"></ul>
                        </div>

						<div class="field-container file-field-options-container" style="display: none;">
							<div>
	                            <strong><?php esc_html_e( 'Allowed File Types', 'woocommerce-wholesale-lead-capture' ); ?></strong>
	                            <input type="text" id="wwlc_cf_field_allowed_file_types" value="doc,docx,xls,xlsx,pdf,jpg,png,gif,txt">
							</div>
							<p class="desc"><?php esc_html_e( 'Type in extension of allowed file types separated by comma', 'woocommerce-wholesale-lead-capture' ); ?></p>
                        </div>

						<div class="field-container file-field-options-container" style="display: none;">
                            <strong><?php esc_html_e( 'Maximum Allowed File Size', 'woocommerce-wholesale-lead-capture' ); ?></strong>
                            <input type="text" id="wwlc_cf_field_max_allowed_file_size" value="20">
							<p class="desc"><?php esc_html_e( 'Enter a value in megabytes', 'woocommerce-wholesale-lead-capture' ); ?></p>
                        </div>

						<div class="field-container content-wp-editor-field-container" style="display: none;">
							<label><?php esc_html_e( 'Content to display', 'woocommerce-wholesale-lead-capture' ); ?></label>
							<div class="wp-editor-wrap" data-content="">
							</div>
							<div class="wwlc-spinner" style="display: none;"><img src="<?php echo esc_url( WWLC_IMAGES_ROOT_URL . 'spinner.gif' ); ?>"></div>
							<div class="content-notice" style="display:none;"><?php echo wp_kses_post( __( 'Please change back to <strong>Visual</strong> editor tab to re-enable saving the field.', 'woocommerce-wholesale-lead-capture' ) ); ?></div>
						</div>

						<div class="field-container terms_conditions-wp-editor-field-container" style="display: none;">
							<label><?php esc_html_e( 'Content to display', 'woocommerce-wholesale-lead-capture' ); ?></label>
							<div class="wp-editor-wrap" data-content="">
							</div>
							<div class="wwlc-spinner" style="display: none;"><img src="<?php echo esc_url( WWLC_IMAGES_ROOT_URL . 'spinner.gif' ); ?>"></div>
							<div class="content-notice" style="display:none;"><?php echo wp_kses_post( __( 'Please change back to <strong>Visual</strong> editor tab to re-enable saving the field.', 'woocommerce-wholesale-lead-capture' ) ); ?></div>
						</div>

                        <div class="field-container number-field-container">

                            <label for="wwlc_cf_field_order"><?php esc_html_e( 'Field Order', 'woocommerce-wholesale-lead-capture' ); ?></label>
                            <input type="number" min="0" step="1" id="wwlc_cf_field_order"/>

                        </div>

                        
                        <div class="field-container placeholder-field-container">

                            <label class="default_label" for="wwlc_cf_placeholder"><?php esc_html_e( 'Placeholder', 'woocommerce-wholesale-lead-capture' ); ?></label>
							<label class="hidden_label" for="wwlc_cf_placeholder" style="display: none;"><?php esc_html_e( 'Value', 'woocommerce-wholesale-lead-capture' ); ?></label>

                            <input type="text" id="wwlc_cf_field_placeholder"/>

                        </div>

                        <div class="field-container text-field-container">

                            <label class="default_label" for="wwlc_cf_default_value"><?php esc_html_e( 'Default Value', 'woocommerce-wholesale-lead-capture' ); ?></label>
							<label class="hidden_label" for="wwlc_cf_default_value" style="display: none;"><?php esc_html_e( 'Value', 'woocommerce-wholesale-lead-capture' ); ?></label>

                            <input type="text" id="wwlc_cf_field_default_value"/>

                        </div>
                        
                        <div class="field-container check-field-container checkout-display-field-container">

                            <label for="wwlc_cf_checkout_display_field"><?php esc_html_e( 'Show on Checkout Page', 'woocommerce-wholesale-lead-capture' ); ?></label>
                            <input type="checkbox" id="wwlc_cf_checkout_display_field"/>

                        </div>

                        <div class="field-container check-field-container required-field-container">

                            <label for="wwlc_cf_required_field"><?php esc_html_e( 'Required', 'woocommerce-wholesale-lead-capture' ); ?></label>
                            <input type="checkbox" id="wwlc_cf_required_field"/>

                        </div>

                        <div class="field-container check-field-container">

                            <label for="wwlc_cf_enabled_field"><?php esc_html_e( 'Enabled', 'woocommerce-wholesale-lead-capture' ); ?></label>
                            <input type="checkbox" id="wwlc_cf_enabled_field"/>

                        </div>

                        <div style="clear: both; float: none; display: block;"></div>

                    </div>

                    <div class="button-controls add-mode">

                        <input type="button" id="cancel-edit-custom-field" class="button button-secondary" value="<?php esc_html_e( 'Cancel', 'woocommerce-wholesale-lead-capture' ); ?>"/>
                        <input type="button" id="save-custom-field" class="button button-primary" value="<?php esc_html_e( 'Save Custom Field', 'woocommerce-wholesale-lead-capture' ); ?>"/>
                        <input type="button" id="add-custom-field" class="button button-primary" value="<?php esc_html_e( 'Add Custom Field', 'woocommerce-wholesale-lead-capture' ); ?>"/>
                        <span class="spinner"></span>

                        <div style="clear: both; float: none; display: block;"></div>

                    </div>

                    <table id="wholesale-lead-capture-custom-fields" class="wp-list-table widefat">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Field Name', 'woocommerce-wholesale-lead-capture' ); ?></th>
                                <th><?php esc_html_e( 'Field ID', 'woocommerce-wholesale-lead-capture' ); ?></th>
                                <th><?php esc_html_e( 'Field Type', 'woocommerce-wholesale-lead-capture' ); ?></th>
                                <th><?php esc_html_e( 'Required', 'woocommerce-wholesale-lead-capture' ); ?></th>
                                <th><?php esc_html_e( 'Field Order', 'woocommerce-wholesale-lead-capture' ); ?></th>
                                <th><?php esc_html_e( 'Placeholder', 'woocommerce-wholesale-lead-capture' ); ?></th>
                                <th><?php esc_html_e( 'Default Value', 'woocommerce-wholesale-lead-capture' ); ?></th>
                                <th><?php esc_html_e( 'Show on Checkout', 'woocommerce-wholesale-lead-capture' ); ?></th>
                                <th><?php esc_html_e( 'Enabled', 'woocommerce-wholesale-lead-capture' ); ?></th>
                                <th></th>
                            </tr>
                        </thead>

                        <tfoot>
                            <tr>
                                <th><?php esc_html_e( 'Field Name', 'woocommerce-wholesale-lead-capture' ); ?></th>
                                <th><?php esc_html_e( 'Field ID', 'woocommerce-wholesale-lead-capture' ); ?></th>
                                <th><?php esc_html_e( 'Field Type', 'woocommerce-wholesale-lead-capture' ); ?></th>
                                <th><?php esc_html_e( 'Required', 'woocommerce-wholesale-lead-capture' ); ?></th>
                                <th><?php esc_html_e( 'Field Order', 'woocommerce-wholesale-lead-capture' ); ?></th>
                                <th><?php esc_html_e( 'Placeholder', 'woocommerce-wholesale-lead-capture' ); ?></th>
                                <th><?php esc_html_e( 'Default Value', 'woocommerce-wholesale-lead-capture' ); ?></th>
                                <th><?php esc_html_e( 'Show on Checkout', 'woocommerce-wholesale-lead-capture' ); ?></th>
                                <th><?php esc_html_e( 'Enabled', 'woocommerce-wholesale-lead-capture' ); ?></th>
                                <th></th>
                            </tr>
                        </tfoot>

                        <tbody>

                <?php
                if ( $custom_fields ) {

                $itemNumber = 0;

                foreach ( $custom_fields as $custom_field_id => $custom_field ) {
                    ++$itemNumber;

                    if ( 'hidden' === $custom_field['field_type'] ) {
                        $custom_field['default_value']     = $custom_field['field_placeholder'];
                        $custom_field['field_placeholder'] = '';
                    }

                    if ( 0 === $itemNumber % 2 ) {
                    // even.
                    ?>
                    <tr class="even">
                    <?php } else {  // odd. ?>
                    <tr class="odd alternate">
                    <?php } ?>

                        <td class="meta hidden"></td>
                        <td class="wwlc_cf_td_field_name">
                            <?php echo isset( $custom_field['field_name'] ) ? esc_html( $custom_field['field_name'] ) : ''; ?>
                        </td>
                        <td class="wwlc_cf_td_field_id">
                            <?php echo isset( $custom_field_id ) ? esc_html( $custom_field_id ) : ''; ?>
                        </td>
                        <td class="wwlc_cf_td_field_type">
                            <?php echo isset( $custom_field['field_type'] ) ? esc_html( $custom_field['field_type'] ) : ''; ?>
                        </td>
                        <td class="wwlc_cf_td_required">
                            <?php

                            if ( 'terms_conditions' === $custom_field['field_type'] ) {
                                $custom_field['required'] = true;
                            }

                            echo $custom_field['required'] ? esc_html_e( 'true', 'woocommerce-wholesale-lead-capture' ) : esc_html_e( 'false', 'woocommerce-wholesale-lead-capture' );
                            ?>
                        </td>
                        <td class="wwlc_cf_td_field_order">
                            <?php echo isset( $custom_field['field_order'] ) ? esc_html( $custom_field['field_order'] ) : ''; ?>
                        </td>
                        <td class="wwlc_cf_td_field_placeholder">
                            <?php echo isset( $custom_field['field_placeholder'] ) ? esc_html( $custom_field['field_placeholder'] ) : ''; ?>
                        </td>
                        <td class="wwlc_cf_td_field_default_value">
                            <?php echo isset( $custom_field['default_value'] ) && ! in_array( $custom_field['field_type'], array( 'content', 'terms_conditions' ), true ) ? esc_html( $custom_field['default_value'] ) : ''; ?>
                        </td>
                        <td class="wwlc_cf_td_checkout_display">
                            <?php echo isset( $custom_field['checkout_display'] ) && $custom_field['checkout_display'] ? esc_html_e( 'true', 'woocommerce-wholesale-lead-capture' ) : esc_html_e( 'false', 'woocommerce-wholesale-lead-capture' ); ?>
                        </td>
                        <td class="wwlc_cf_td_enabled">
                            <?php echo isset( $custom_field['enabled'] ) && $custom_field['enabled'] ? esc_html_e( 'true', 'woocommerce-wholesale-lead-capture' ) : esc_html_e( 'false', 'woocommerce-wholesale-lead-capture' ); ?>
                        </td>
                        <td class="controls">
                            <a class="edit dashicons dashicons-edit"></a>
                            <a class="delete dashicons dashicons-no"></a>
                        </td>

                    </tr>
                    <?php

                }
            } else {
            ?>
                    <tr class="no-items">
                        <td class="colspanchange" colspan="7"><?php esc_html_e( 'No Custom Fields Found', 'woocommerce-wholesale-lead-capture' ); ?></td>
                    </tr>
            <?php
            }
            ?>

                        </tbody>
                    </table>
                </th>
            </tr>

            <style>
                p.submit {
                    display: none !important;
                }
            </style>
        <?php
        }

        /**
         * Render help resource controls.
         *
         * @param array $value Settings field value.
         *
         * @since 1.3.1
         */
        public function render_wwlc_help_resources_controls( $value ) {
            ?>

			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for=""><?php esc_html_e( 'Knowledge Base', 'woocommerce-wholesale-lead-capture' ); ?></label>
				</th>
				<td class="forminp forminp-<?php echo esc_attr( sanitize_title( $value['type'] ) ); ?>">
                    <?php
                        echo wp_kses_post(
                            sprintf(
                                // translators: %1$s Knowledge Base URL.
                                __( 'Looking for documentation? Please see our growing <a href="%1$s" target="_blank">Knowledge Base</a>', 'woocommerce-wholesale-lead-capture' ),
                                'https://wholesalesuiteplugin.com/knowledge-base/?utm_source=wwlc&utm_medium=settings&utm_campaign=KnowledgeBase'
                            )
                        );
                    ?>
				</td>
			</tr>
            <?php
        }

        /**
         * Clear Update Data.
         * WooCommerce > Settings > Wholesale Lead  > Help > Clear Update Data
         *
         * @since 1.16.2
         * @access public
         */
        public function render_plugin_settings_custom_field_wwlc_force_fetch_update_data_button() {
            require_once WWLC_VIEWS_ROOT_DIR . 'custom-fields/view-wwlc-force-fetch-update-data-button-custom-field.php';
        }

        /**
         * Render built in fields
         *
         * @param array $data Settings field value.
         * @since 1.14.4
         */
        public function built_in_fields( $data ) {

            if ( empty( $data['fields'] ) ) {
                return;
            }

            $fields = array(
                'firstname' => __( 'First Name', 'woocommerce-wholesale-lead-capture' ),
                'lastname'  => __( 'Last Name', 'woocommerce-wholesale-lead-capture' ),
                'email'     => __( 'Email Address', 'woocommerce-wholesale-lead-capture' ),
                'username'  => __( 'Username', 'woocommerce-wholesale-lead-capture' ),
                'password'  => __( 'Password', 'woocommerce-wholesale-lead-capture' ),
                'company'   => __( 'Company Name', 'woocommerce-wholesale-lead-capture' ),
                'address'   => __( 'Address', 'woocommerce-wholesale-lead-capture' ),
                'phone'     => __( 'Phone', 'woocommerce-wholesale-lead-capture' ),
                'terms'     => __( 'Terms & Conditions', 'woocommerce-wholesale-lead-capture' ),
            );
            ?>

			<style>
				.form-table{
					background: #fff;
					border: none;
					margin-left: -2px!important;
				}
				.form-table .heading{
					font-size: 18px;
					font-weight: 600;
				}
				.form-table tr td:first-child{
					vertical-align: top;
				}
				.form-table tr td:first-child label{
					margin-right: 10px;
				}
				.form-table tr:last-child td{
					padding-bottom: 40px;
				}
				.form-table tr td:first-child label:first-child{
					font-weight: 600;
					display: block;
					margin-bottom: 15px;
				}
				.form-table td div label{
					display: block;
					margin-bottom: 8px;
				}
				.form-table label[for=wwlc_fields_enable_address2_label]{
					display: inline-block;
				}
				.form-table td, .form-table th{
					border: 1px solid #eee;
					padding: 15px 20px;
				}
				.form-table td div{
					margin-right: 10px;
					margin-bottom: 15px;
					display: inline-block;
				}
				table.form-table select{
					line-height: 40px!important;
				}
				.form-table label[for=wwlc_fields_enable_confirm_password_field], .form-table label[for=wwlc_fields_minimum_password_strength_field] {
					display: inline-block;
				}
			</style>
			<table class="form-table">
				<tr valign="top">
					<th scope="row" class="heading">
						<label for=""><?php esc_html_e( 'Field', 'woocommerce-wholesale-lead-capture' ); ?></label>
					</th>
					<td class="heading">
						<label for=""><?php esc_html_e( 'Options', 'woocommerce-wholesale-lead-capture' ); ?></label>
					</td>
				</tr>
                <?php

            do_action( 'before_built_in_fields_display', $data );

            foreach ( $data['fields'] as $key => $field ) {
                $tooltip = '';
                if ( in_array( $key, array( 'username', 'password' ), true ) ) {
                    $tooltip = WC_Admin_Settings::get_field_description( $field );
                }

                if ( isset( $field['placeholder'] ) && isset( $field['order'] ) || 'terms' === $key ) {
                    ?>
                    <tr valign="top">
                        <td scope="row" class="titledesc">
                            <label for="">
                                <?php echo isset( $fields[ $key ] ) ? esc_html( $fields[ $key ] ) : ''; ?>
                                <?php echo isset( $tooltip['tooltip_html'] ) ? wp_kses_post( $tooltip['tooltip_html'] ) : ''; ?>
                            </label>
                            <?php $this->display_option( $field ); ?>
                        </td>
                        <td>
                    <?php
                    if ( 'address' === $key ) {
                        $this->display_address_fields( $field );
                    } elseif ( 'terms' === $key ) {
                    ?>
                        <label><?php echo wp_kses_post( $field['enable']['desc'] ); ?></label>
                    <?php
                    } else {
                        ?>

                            <div>
                                <label for="<?php echo esc_attr( $field['placeholder']['id'] ); ?>"><?php echo esc_html( $field['placeholder']['title'] ); ?>:</label>
                                <input id="<?php echo esc_attr( $field['placeholder']['id'] ); ?>" type="text" name="<?php echo esc_html( $field['placeholder']['id'] ); ?>" value="<?php echo esc_attr( $field['placeholder']['value'] ); ?>"/>
                            </div>
                            <div>
                                <label for="<?php echo esc_attr( $field['order']['id'] ); ?>"><?php echo esc_html( $field['order']['title'] ); ?>:</label>
                                <input id="<?php echo esc_attr( $field['order']['id'] ); ?>" type="number" name="<?php echo esc_html( $field['order']['id'] ); ?>" value="<?php echo esc_attr( $field['order']['value'] ); ?>" min="0" step="1"/>
                            </div>
                            <?php

                        if ( 'password' === $key ) {
                        ?>
                        <div>
                            <input id="<?php echo esc_attr( $field['enable_confirm']['id'] ); ?>" type="checkbox" name="<?php echo esc_html( $field['enable_confirm']['id'] ); ?>" <?php echo 'yes' === $field['enable_confirm']['value'] ? 'checked' : ''; ?> value="1">
                            <label for="<?php echo esc_attr( $field['enable_confirm']['id'] ); ?>"><?php echo esc_html( $field['enable_confirm']['title'] ); ?></label>
                        </div>
                        <?php if ( isset( $field['minimum_strength'] ) ) { ?>
                        <div>
                            <input id="<?php echo esc_attr( $field['minimum_strength']['id'] ); ?>" type="checkbox" name="<?php echo esc_html( $field['minimum_strength']['id'] ); ?>" <?php echo 'yes' === $field['minimum_strength']['value'] ? 'checked' : ''; ?> value="1">
                            <label for="<?php echo esc_attr( $field['minimum_strength']['id'] ); ?>"><?php echo esc_html( $field['minimum_strength']['title'] ); ?></label>
                        </div>
                        <?php } ?>
                        <?php } elseif ( 'phone' === $key ) { ?>
                        <div>
                            <label for="<?php echo esc_attr( $field['mask']['id'] ); ?>"><?php echo esc_html( $field['mask']['title'] ); ?>:</label>
                            <select id="<?php echo esc_attr( $field['mask']['id'] ); ?>" name="<?php echo esc_html( $field['mask']['id'] ); ?>">
                            <?php foreach ( $field['mask']['options'] as $key => $val ) { ?>
                                    <option value="<?php echo esc_attr( $key ); ?>" <?php echo $field['mask']['value'] === $key ? 'selected="selected"' : ''; ?>><?php echo esc_html( $val ); ?></option>
                                    <?php } ?>
                            </select>
                        </div>
                        <?php
                        }
                    }
                    ?>
                        </td>
                    </tr>
                <?php
                }
            }

            do_action( 'after_built_in_fields_display', $data );
            ?>

				</table>
        <?php
        }

        /**
         * Display enable or required checkbox
         *
         * @param array $field field data.
         * @since 1.14.4
         */
        public function display_option( $field ) {

            if ( isset( $field['enable'] ) ) {
            ?>
				<input id="<?php echo esc_attr( $field['enable']['id'] ); ?>" type="checkbox" name="<?php echo esc_attr( $field['enable']['id'] ); ?>" <?php echo 'yes' === $field['enable']['value'] ? 'checked' : ''; ?> value="1">
				<label for="<?php echo esc_attr( $field['enable']['id'] ); ?>"><?php echo esc_html( $field['enable']['title'] ); ?> </label>
            <?php
            }

            if ( isset( $field['required'] ) ) {
            ?>
				<input id="<?php echo esc_attr( $field['required']['id'] ); ?>" type="checkbox" name="<?php echo esc_attr( $field['required']['id'] ); ?>" <?php echo 'yes' === $field['required']['value'] ? 'checked' : ''; ?> value="1">
				<label for="<?php echo esc_attr( $field['required']['id'] ); ?>"><?php echo esc_html( $field['required']['title'] ); ?> </label>
            <?php
            }
        }

        /**
         * Display address fields
         *
         * @param array $field field data.
         * @since 1.14.4
         */
        public function display_address_fields( $field ) {
            ?>

			<div>
				<label for="<?php echo esc_attr( $field['placeholder']['id'] ); ?>">
					<?php echo esc_html( $field['placeholder']['title'] ); ?>:
				</label>
				<input id="<?php echo esc_attr( $field['placeholder']['id'] ); ?>" type="text" name="<?php echo esc_attr( $field['placeholder']['id'] ); ?>" value="<?php echo esc_attr( $field['placeholder']['value'] ); ?>"/>
			</div>
			<div>
				<label for="<?php echo esc_attr( $field['order']['id'] ); ?>">
					<?php echo esc_html( $field['order']['title'] ); ?>:
				</label>
				<input id="<?php echo esc_attr( $field['order']['id'] ); ?>" type="number" name="<?php echo esc_attr( $field['order']['id'] ); ?>" value="<?php echo esc_attr( $field['order']['value'] ); ?>" min="0" step="1" />
			</div><br/>
			<div>
				<label for="<?php echo esc_attr( $field['address2_placeholder']['id'] ); ?>">
					<?php echo esc_html( $field['address2_placeholder']['title'] ); ?>:
				</label>
				<input id="<?php echo esc_attr( $field['address2_placeholder']['id'] ); ?>" type="text" name="<?php echo esc_attr( $field['address2_placeholder']['id'] ); ?>" value="<?php echo esc_attr( $field['address2_placeholder']['value'] ); ?>"/>
			</div>
			<div>
				<input id="<?php echo esc_attr( $field['enable_add_line2']['id'] ); ?>" type="checkbox" name="<?php echo esc_attr( $field['enable_add_line2']['id'] ); ?>" <?php echo 'yes' === $field['enable_add_line2']['value'] ? 'checked' : ''; ?> value="1">
				<label for="<?php echo esc_attr( $field['enable_add_line2']['id'] ); ?>">
					<?php echo esc_html( $field['enable_add_line2']['title'] ); ?>
				</label>
			</div><br/>
			<div>
				<label for="<?php echo esc_attr( $field['city_placeholder']['id'] ); ?>">
					<?php echo esc_html( $field['city_placeholder']['title'] ); ?>:
				</label>
				<input id="<?php echo esc_attr( $field['city_placeholder']['id'] ); ?>" type="text" name="<?php echo esc_attr( $field['city_placeholder']['id'] ); ?>" value="<?php echo esc_attr( $field['city_placeholder']['value'] ); ?>"/>
			</div><br/>
			<div>
				<label for="<?php echo esc_attr( $field['state']['id'] ); ?>">
					<?php echo esc_html( $field['state']['title'] ); ?>:
				</label>
				<input id="<?php echo esc_attr( $field['state']['id'] ); ?>" type="text" name="<?php echo esc_attr( $field['state']['id'] ); ?>" value="<?php echo esc_attr( $field['state']['value'] ); ?>"/>
			</div><br/>
			<div>
				<label for="<?php echo esc_attr( $field['zip']['id'] ); ?>">
					<?php echo esc_html( $field['zip']['title'] ); ?>:
				</label>
				<input id="<?php echo esc_attr( $field['zip']['id'] ); ?>" type="text" name="<?php echo esc_attr( $field['zip']['id'] ); ?>" value="<?php echo esc_attr( $field['zip']['value'] ); ?>"/>
			</div>
            <?php
        }

        /**
         * Render wysiwyg editor.
         *
         * @param array $field field data.
         * @since 1.17.5
         */
        public function render_wwlc_wysiwyg_editor( $field ) {
            ob_start();
            $field_description = WC_Admin_Settings::get_field_description( $field );
            $description       = $field_description['description'];
            $tooltip_html      = $field_description['tooltip_html'];
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr( $field['id'] ); ?>"><?php echo wp_kses_post( $field['title'] ); ?> <?php echo $tooltip_html; // phpcs:ignore. ?></label>
                </th>
                <td class="forminp forminp-<?php echo sanitize_title( $field['type'] ); //phpcs:ignore ?>">
                    <fieldset>
                        <?php wp_editor( html_entity_decode( get_option( $field['id'], $field['default'] ) ), $field['id'], $field['settings'] ); ?>
                    </fieldset>
                    <div class="wwlc-email-wysiwyg-desc">
                        <?php echo $description; // phpcs:ignore. ?>
                    </div>
                </td>
            </tr>
            <?php
            echo ob_get_clean(); // phpcs:ignore.
        }

        /**
         * Sanitize account upgrade message field.
         *
         * @since 1.17.5
         *
         * @param string $value field value.
         * @param string $option field option.
         * @param string $raw_value field raw value.
         * @return string
         */
        public function sanitize_wwlc_account_upgrade_message( $value, $option, $raw_value ) {
            return htmlentities( wpautop( $raw_value ) );
        }

        /**
         * Handles built in fields saving
         *
         * @since 1.14.4
         */
        public function save_built_in_fields() {
            global $current_section;

            if ( empty( $_POST ) || 'wwlc_setting_fields_section' !== $current_section ) { // phpcs:ignore.
                return;
            }

            $fields = array(
                'wwlc_fields_first_name_field_placeholder',
                'wwlc_fields_first_name_field_order',
                'wwlc_fields_last_name_field_placeholder',
                'wwlc_fields_last_name_field_order',
                'wwlc_fields_email_field_placeholder',
                'wwlc_fields_email_field_order',
                'wwlc_fields_username_placeholder',
                'wwlc_fields_username_order',
                'wwlc_fields_password_field_placeholder',
                'wwlc_fields_password_field_order',
                'wwlc_fields_company_field_placeholder',
                'wwlc_fields_company_name_field_order',
                'wwlc_fields_address_placeholder',
                'wwlc_fields_address_field_order',
                'wwlc_fields_address2_placeholder',
                'wwlc_fields_city_placeholder',
                'wwlc_fields_state_placeholder',
                'wwlc_fields_postcode_placeholder',
                'wwlc_fields_phone_field_placeholder',
                'wwlc_fields_phone_field_order',
                'wwlc_fields_phone_mask_pattern',
            );

            $checkboxes = array(
                'wwlc_fields_username_active',
                'wwlc_fields_activate_password_field',
                'wwlc_fields_require_password_field',
                'wwlc_fields_enable_confirm_password_field',
                'wwlc_fields_minimum_password_strength_field',
                'wwlc_fields_activate_company_name_field',
                'wwlc_fields_require_company_name_field',
                'wwlc_fields_activate_address_field',
                'wwlc_fields_require_address_field',
                'wwlc_fields_enable_address2_label',
                'wwlc_fields_require_phone_field',
                'wwlc_general_show_terms_and_conditions',
            );

            foreach ( array_merge( $fields, $checkboxes ) as $field ) {
                if ( array_key_exists( $field, $_POST ) ) { // phpcs:ignore.
                    if ( in_array( $field, $checkboxes, true ) ) {
                        $value = 'yes';
                        update_option( $field, 'yes' );
                    } else {
                        $value = wp_unslash( $_POST[ $field ] ); // phpcs:ignore.
                        $value = wc_clean( $value );
                        update_option( $field, $value );
                    }
                } else {
                    update_option( $field, 'no' );
                }
            }
        }

    }

}

return new WWLC_Settings();
