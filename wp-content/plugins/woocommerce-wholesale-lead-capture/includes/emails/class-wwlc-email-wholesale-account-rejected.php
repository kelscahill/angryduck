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
class WWLC_Email_Wholesale_Account_Rejected extends WC_Email {

    /**
	 * Override (force) default template path
	 *
	 * @var string
	 */
	public $default_template_path;

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
     * Class constructor.
     *
     * @since 1.17.4
     * @access public
     */
    public function __construct() {
        $this->id                    = 'wwlc_email_wholesale_account_rejected';
        $this->customer_email        = true;
        $this->title                 = __( 'Wholesale account rejected', 'woocommerce-wholesale-lead-capture' );
        $this->description           = __( 'Email sent to users whose been rejected.', 'woocommerce-wholesale-lead-capture' );
        $this->template_html         = 'emails/woocommerce-wholesale-lead-capture-email.php';
        $this->template_plain        = 'emails/plain/woocommerce-wholesale-lead-capture-email.php.';
        $this->default_template_path = WWLC_TEMPLATES_ROOT_DIR;
        $this->object                = WWLC_Helper_Functions::get_wwlc_object();
        $this->wwlc_placeholders     = array_merge(
            array(
                '{site_name}'    => '',
                '{user_role}'    => '',
                '{full_name}'    => '',
                '{first_name}'   => '',
                '{last_name}'    => '',
                '{username}'     => '',
                '{password}'     => '',
                '{email}'        => '',
                '{phone}'        => '',
                '{company_name}' => '',
                '{address}'      => '',
                '{address_1}'    => '',
                '{address_2}'    => '',
                '{city}'         => '',
                '{state}'        => '',
                '{postcode}'     => '',
                '{country}'      => '',
            ),
            WWLC_Emails::get_custom_fields_placeholders()
        );

        parent::__construct();
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
        return __( 'Wholesale Account Rejected', 'woocommerce-wholesale-lead-capture' );
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
        return __( 'Wholesale Account Rejected', 'woocommerce-wholesale-lead-capture' );
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
        __( 'Unfortunately your application for a wholesale account has not been approved at this time.', 'woocommerce-wholesale-lead-capture' ) . '</p><p>' .
        __( 'If you feel this decision has been made in error please get in touch.', 'woocommerce-wholesale-lead-capture' ) . '</p><p>' .
        __( 'Kind Regards,', 'woocommerce-wholesale-lead-capture' ) . '<br/>' .
        sprintf( '%1$s', '{site_title}' ) . '<br/></p>';

        return $default_message;
    }

    /**
     * Trigger sending of this email.
     *
     * @since 1.17.4
     * @access public
     *
     * @param WP_User $user      WP_User Obect.
     */
    public function trigger( $user ) {
        do_action( 'wwlc_before_send_' . $this->id, $user );

        $this->setup_locale();

        if ( $user instanceof WP_User && $user->exists() ) {
			$this->object    = $user;
            $this->recipient = $user->user_email;
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
                'placeholder' => __( 'Wholesale Account Rejected', 'woocommerce-wholesale-lead-capture' ),
                'desc_tip'    => true,
                'description' => $placeholder_text,
                'default'     => $this->get_default_subject(),
            ),
            'heading'    => array(
                'title'       => __( 'Email heading', 'woocommerce-wholesale-lead-capture' ),
                'type'        => 'text',
                'placeholder' => __( 'Wholesale Account Rejected', 'woocommerce-wholesale-lead-capture' ),
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
