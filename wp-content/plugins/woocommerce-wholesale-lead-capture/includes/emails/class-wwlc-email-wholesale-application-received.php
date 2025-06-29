<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Model that houses the data model of an advanced coupon email.
 *
 * @since 1.17.4
 */
class WWLC_Email_Wholesale_Application_Received extends WC_Email {

    /**
	 * User data.
	 *
	 * @var array
	 */
	public $user_data;

    /**
	 * User password.
	 *
	 * @var string
	 */
	private $user_password;

    /**
	 * WWLC Placeholders data.
	 *
	 * @var array
	 */
	public $wwlc_placeholders;

    /**
	 * WWLC Object.
	 *
	 * @var array
	 */
	public $object;

    /**
	 * Override (force) default template path
	 *
	 * @var string
	 */
	public $default_template_path;

    /**
     * Class constructor.
     *
     * @since 1.17.4
     * @access public
     */
    public function __construct() {
        $this->id                    = 'wwlc_email_wholesale_application_received';
        $this->customer_email        = true;
        $this->title                 = __( 'Wholesale application received', 'woocommerce-wholesale-lead-capture' );
        $this->description           = __( 'Email sent to new users after successful registration.', 'woocommerce-wholesale-lead-capture' );
        $this->template_html         = 'emails/woocommerce-wholesale-lead-capture-email.php';
        $this->template_plain        = 'emails/plain/woocommerce-wholesale-lead-capture-email.php.';
        $this->default_template_path = WWLC_TEMPLATES_ROOT_DIR;
        $this->object                = WWLC_Helper_Functions::get_wwlc_object();

        $this->wwlc_placeholders = array_merge(
            array(
                '{site_name}'           => '',
                '{user_role}'           => '',
                '{wholesale_login_url}' => '',
                '{full_name}'           => '',
                '{first_name}'          => '',
                '{last_name}'           => '',
                '{username}'            => '',
                '{password}'            => '',
                '{email}'               => '',
                '{phone}'               => '',
                '{company_name}'        => '',
                '{address}'             => '',
                '{address_1}'           => '',
                '{address_2}'           => '',
                '{city}'                => '',
                '{state}'               => '',
                '{postcode}'            => '',
                '{country}'             => '',
            ),
            WWLC_Emails::get_custom_fields_placeholders()
        );

        parent::__construct();
    }

    /**
     * Magic getter for protected properties.
     *
     * @since 1.17.4
     * @access public
     *
     * @param  mixed $key Key.
     * @return mixed
     */
    public function __get( $key ) {
        return $this->$key ?? null;
    }

    /**
     * Get email's default subject.
     *
     * @since 1.17.4
     * @access public
     *
     * @return string
     */
    public function get_default_subject() {
        return __( 'Wholesale Application Received', 'woocommerce-wholesale-lead-capture' );
    }

    /**
     * Get email subject.
     *
     * @since 1.17.4
     * @access public
     *
     * @return string
     */
    public function get_default_heading() {
        return __( 'Wholesale Application Received', 'woocommerce-wholesale-lead-capture' );
    }

    /**
     * Get default message content.
     *
     * @since 1.17.4
     * @access public
     *
     * @return string
     */
    public function get_default_message() {
        /* Translators: %1$s: Full Name */
        $default_message = '<p>' . sprintf( __( 'Hi %1$s,', 'woocommerce-wholesale-lead-capture' ), '{full_name}' ) . '</p><p>' .
        __( 'Thank you for your interest in becoming a wholesale customer. We\'ll send you an email once your application has been approved.', 'woocommerce-wholesale-lead-capture' ) . '</p><p>' .
        __( 'Please save your credentials below:', 'woocommerce-wholesale-lead-capture' ) . '<br/>' .
        /* Translators: %1$s: Username */
        sprintf( __( 'username : %1$s', 'woocommerce-wholesale-lead-capture' ), '{username}' ) . '<br/>' .
        /* Translators: %1$s: Password */
        sprintf( __( 'password : %1$s', 'woocommerce-wholesale-lead-capture' ), '{password}' ) . '<br/></p>' .
        __( 'Kind Regards,', 'woocommerce-wholesale-lead-capture' ) . '<br/>{site_title}';

        return $default_message;
    }

    /**
     * Trigger sending of this email.
     *
     * @since 1.17.4
     * @access public
     *
     * @param WP_User $user          WP_User Obect.
     * @param array   $user_data     Values from registration form.
     * @param array   $user_password Password inputed registration form or auto generated password.
     */
    public function trigger( $user, $user_data, $user_password ) {
        do_action( 'wwlc_before_send_' . $this->id, $user );

        $this->setup_locale();

        if ( $user instanceof WP_User && $user->exists() ) {
			$this->object        = $user;
			$this->user_data     = $user_data;
			$this->user_password = $user_password;
            $this->recipient     = $user->user_email;
		}

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send(
                $this->get_recipient(),
                $this->get_subject(),
                $this->get_content(),
                $this->get_headers(),
                $this->get_attachments()
            );
		}

        do_action( 'wwlc_after_send' . $this->id, $user );
    }

    /**
     * Override setup locale function to remove customer email check.
     *
     * @since 1.17.4
     * @access public
     */
    public function setup_locale() {
        if ( apply_filters( 'woocommerce_email_setup_locale', true ) ) {
            wc_switch_to_site_locale();
        }
    }

    /**
     * Override restore locale function to remove customer email check.
     *
     * @since 1.17.4
     * @access public
     */
    public function restore_locale() {
        if ( apply_filters( 'woocommerce_email_restore_locale', true ) ) {
            wc_restore_locale();
        }
    }

    /**
	 * Get email heading.
     *
     * @since 1.17.4
     * @access public
	 *
	 * @return string
	 */
	public function get_message() {
		global $sitepress;
		// Get the message content.
		$message = $this->format_string( $this->get_option( 'message', $this->get_default_message() ) );
		// If WPML is active and we have a user object with language preference.
		if ( is_object( $sitepress ) && isset( $this->object ) && $this->object instanceof WP_User ) {
			$user_lang = get_user_meta( $this->object->ID, 'wwlc_user_lang_wpml', true );
			if ( $user_lang ) {
				// Switch to user's language.
				$sitepress->switch_lang( $user_lang );
				// Get translated message.
				$message = $this->format_string( $this->get_option( 'message', $this->get_default_message() ) );
				// Restore original language.
				$sitepress->switch_lang( ICL_LANGUAGE_CODE );
			}
		}
		return apply_filters( 'wwlc_email_message_' . $this->id, $message, $this->object, $this );
	}

    /**
     * Get email content html.
     *
     * @since 1.17.4
     * @access public
     *
     * @return string Email html content.
     */
    public function get_content_html() {
        return apply_filters(
            'wwlc_email_content_html_' . $this->id,
            wc_get_template_html(
                $this->template_html,
                array(
					'email_heading' => $this->get_heading(),
					'message'       => $this->get_message(),
					'user_data'     => $this->user_data,
					'blogname'      => $this->get_blogname(),
					'sent_to_admin' => false,
					'plain_text'    => false,
					'email'         => $this,
                ),
                '',
                $this->default_template_path
            ),
            $this->object // WP_User object.
        );
    }

    /**
     * Get email plain content.
     *
     * @since 1.17.4
     * @access public
     *
     * @return string Email plain content.
     */
    public function get_content_plain() {
        return apply_filters(
            'wwlc_email_content_plain_' . $this->id,
            wc_get_template_html(
                $this->template_plain,
                array(
					'email_heading' => $this->get_heading(),
					'message'       => $this->get_message(),
					'user_data'     => $this->user_data,
					'blogname'      => $this->get_blogname(),
					'sent_to_admin' => false,
					'plain_text'    => true,
					'email'         => $this,
                ),
                '',
                $this->default_template_path
            )
        );
    }

    /**
     * Initialize email setting form fields.
     *
     * @since 1.17.4
     * @access public
     */
    public function init_form_fields() {
        $placeholder_text = sprintf(
            /* Translators: %s: list of available placeholder tags */
            __( 'Available placeholders: %s', 'woocommerce-wholesale-lead-capture' ),
            '<br/><code>' . implode( '</code>, <code>', array_keys( $this->placeholders ) ) . '</code><code>' . implode( '</code>, <code>', array_keys( $this->wwlc_placeholders ) ) . '</code>'
        );
        $this->form_fields = array(
            'enabled'    => array(
                'title'   => __( 'Enable/Disable', 'woocommerce-wholesale-lead-capture' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable this email', 'woocommerce-wholesale-lead-capture' ),
                'default' => 'yes',
            ),
            'subject'    => array(
                'title'       => __( 'Subject', 'woocommerce-wholesale-lead-capture' ),
                'type'        => 'text',
                'placeholder' => __( 'Wholesale Application Received', 'woocommerce-wholesale-lead-capture' ),
                'desc_tip'    => true,
                'description' => $placeholder_text,
                'default'     => $this->get_default_subject(),
            ),
            'heading'    => array(
                'title'       => __( 'Email heading', 'woocommerce-wholesale-lead-capture' ),
                'type'        => 'text',
                'placeholder' => __( 'Wholesale Application Received', 'woocommerce-wholesale-lead-capture' ),
                'desc_tip'    => true,
                'description' => $placeholder_text,
                'default'     => $this->get_default_heading(),
            ),
            'message'    => array(
                'title'       => __( 'Message', 'woocommerce-wholesale-lead-capture' ),
                'type'        => 'wwlc_email_wysiwyg',
                'desc_tip'    => false,
                'description' => $placeholder_text,
                'default'     => $this->get_default_message(),
            ),
            'email_type' => array(
                'title'       => __( 'Email type', 'woocommerce-wholesale-lead-capture' ),
                'type'        => 'select',
                'description' => __( 'Choose which format of email to send.', 'woocommerce-wholesale-lead-capture' ),
                'default'     => 'html',
                'class'       => 'email_type wc-enhanced-select',
                'options'     => $this->get_email_type_options(),
                'desc_tip'    => true,
            ),
        );
    }
}
