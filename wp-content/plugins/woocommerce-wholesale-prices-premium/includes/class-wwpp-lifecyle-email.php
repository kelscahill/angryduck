<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWPP_Lifecycle_EMAIL' ) ) {

    /**
     * Model that houses the logic of WWPP integration with WC orders.
     *
     * @since 2.0.0.5
     */
    class WWPP_Lifecycle_EMAIL {

        /**
         * Class Properties
         */

        /**
         * Property that holds the single main instance of WWPP_Lifecycle_EMAIL.
         *
         * @since 2.0.0.5
         * @access private
         * @var WWPP_Lifecycle_EMAIL
         */
        private static $_instance;

        /**
         * Model that houses the logic of retrieving information relating to wholesale role/s of a user.
         *
         * @since 2.0.0.5
         * @access private
         * @var WWPP_Wholesale_Roles
         */
        private $_wwpp_wholesale_roles;

        /**
         * Class Methods
         */

        /**
         * WWPP_Lifecycle_EMAIL constructor.
         *
         * @since 2.0.0.5
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Lifecycle_EMAIL model.
         */
        public function __construct( $dependencies ) {

            $this->_wwpp_wholesale_roles = $dependencies['WWPP_Wholesale_Roles'];
        }

        /**
         * Ensure that only one instance of WWPP_Lifecycle_EMAIL is loaded or can be loaded (Singleton Pattern).
         *
         * @since 2.0.0.5
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Lifecycle_EMAIL model.
         * @return WWPP_Lifecycle_EMAIL
         */
        public static function instance( $dependencies ) {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Method that sends an email to the recipient.
         *
         * @since 2.0.0.5
         * @access public
         *
         * @param string $template_name Name of the email template.
         * @param array  $args          Array of arguments to be passed to the email template.
         * @param array  $headers       Array of headers to be passed to the email.
         * @param array  $attachments   Array of attachments to be passed to the email.
         * @return bool
         */
        public function wwpp_email_sender( $template_name = '', $args = array(), $headers = array(), $attachments = array() ) {
            // headers.
            $headers[] = 'Content-Type: text/html; charset=UTF-8';

            $recipient = $args['recipient'];
            $recipient = apply_filters( 'wwpp_lifecycle_email_recipient', $recipient );

            $subject = $args['subject'];

            $content = '';

            // get email template.
            if ( $template_name ) {
                $content = $this->_get_email_content( $template_name, $args );
            } else {
                return false;
            }

            // attachments.
            $attachments = apply_filters( 'wwpp_email_attachments', $attachments );

            // send email.
            $sent = wp_mail( $recipient, $subject, $content, $headers, $attachments );
            if ( ! $sent ) {
                return false;
            }

            return true;
        }

        /**
         * Method that retrieves the email content.
         *
         * @since 2.0.0.5
         * @access private
         *
         * @param string $template_name Name of the email template.
         * @param array  $args          Array of arguments to be passed to the email template.
         * @return string
         */
        private function _get_email_content( $template_name = '', $args = array() ) {

            if ( ! $template_name ) {
                return '';
            }

            // get email template.
            $email_template = $this->_get_email_template( $template_name, $args );

            // apply filters.
            $email_content = apply_filters( 'wwpp_email_content', $email_template, $template_name, $args );

            return $email_content;
        }

        /**
         * Method that retrieves the email template.
         *
         * @access private
         *
         * @param string $template_name Name of the email template.
         * @param array  $args          Array of arguments to be passed to the email template.
         * @return string
         */
        private function _get_email_template( $template_name = '', $args = array() ) {
            $template_file_name = $template_name . '.php';
            $template_path      = 'wwpp/emails';
            $template           = $this->_wwpp_locate_email_file( $template_path, $template_file_name );

            if ( file_exists( $template ) ) {

                // Assets.
                $order_icon      = WWPP_IMAGES_URL . 'emails/wholesale-order-icon.png';
                $revenue_icon    = WWPP_IMAGES_URL . 'emails/wholesale-revenue-icon.png';
                $leads_icon      = WWPP_IMAGES_URL . 'emails/wholesale-leads-icon.png';
                $arrow_up_icon   = WWPP_IMAGES_URL . 'emails/arrow-up.png';
                $arrow_down_icon = WWPP_IMAGES_URL . 'emails/arrow-down.png';
                $star_icon       = WWPP_IMAGES_URL . 'emails/star-icon.png';
                $light_icon      = WWPP_IMAGES_URL . 'emails/light-icon.png';
                $hand_icon       = WWPP_IMAGES_URL . 'emails/hand-icon.png';
                $support_image   = WWPP_IMAGES_URL . 'emails/online-support-service.png';

                // celebrate icons.
                $celebrate_icon_1 = WWPP_IMAGES_URL . 'emails/celebrate-icon-1.png';
                $celebrate_icon_2 = WWPP_IMAGES_URL . 'emails/celebrate-icon-2.png';
                $celebrate_icon_3 = WWPP_IMAGES_URL . 'emails/celebrate-icon-3.png';

                // Get header and footer.
                $header = $this->_get_email_header( $args );
                $footer = $this->_get_email_footer( $args );

                ob_start();

                echo wp_kses_post( $header );
                require $template;
                echo wp_kses_post( $footer );

                return wp_kses_post( ob_get_clean() );
            } else {
                return '';
            }
        }

        /**
         * Method that retrieves the email header.
         *
         * @since 2.0.0.5
         * @access private
         * @param array $args Array of arguments to be passed to the email template.
         * @return string
         */
        private function _get_email_header( $args ) {
            $template_name = 'email-header.php';
            $template_path = 'wwpp/emails';
            $template      = $this->_wwpp_locate_email_file( $template_path, $template_name );

            if ( file_exists( $template ) ) {
                ob_start();

                $title = __( 'WWPP Email', 'woocommerce-wholesale-prices-premium' );

                // Header image.
                $header_image = WWPP_IMAGES_URL . 'emails/header-image.png';
                if ( isset( $args['celebrate'] ) && $args['celebrate'] ) {
                    $header_image = WWPP_IMAGES_URL . 'emails/celebrate-banner.png';
                }

                require $template;

                return wp_kses_post( ob_get_clean() );
            } else {
                return '';
            }
        }

        /**
         * Method that retrieves the email footer.
         *
         * @since 2.0.0.5
         * @access private
         * @param array $args Array of arguments to be passed to the email template.
         * @return string
         */
        private function _get_email_footer( $args ) {
            $template_name = 'email-footer.php';
            $template_path = 'wwpp/emails';
            $template      = $this->_wwpp_locate_email_file( $template_path, $template_name );

            if ( file_exists( $template ) ) {

                // Get store address.
                $store_address     = get_option( 'woocommerce_store_address' );
                $store_address_2   = get_option( 'woocommerce_store_address_2' );
                $store_city        = get_option( 'woocommerce_store_city' );
                $store_postcode    = get_option( 'woocommerce_store_postcode' );
                $store_raw_country = get_option( 'woocommerce_default_country' );

                $split_country = explode( ':', $store_raw_country );
                $store_country = $split_country[0];
                $store_state   = isset( $split_country[1] ) ? $split_country[1] : '';

                $store_country_name = WC()->countries->countries[ $store_country ];
                $store_state_name   = isset( WC()->countries->states[ $store_country ][ $store_state ] ) ? WC()->countries->states[ $store_country ][ $store_state ] : '';

                $wwp_store_address = implode(
                    ', ',
                    array_filter(
                        array_map(
                            'trim',
                            array(
                                $store_address,
                                $store_address_2,
                                $store_city,
                                $store_state_name,
                                $store_country_name,
                                $store_postcode,
                            )
                        )
                    )
                );

                // apply filters for email store address.
                $wwp_store_address = apply_filters( 'wwpp_email_store_address', $wwp_store_address );

                // get store name.
                $store_name = get_bloginfo( 'name' );
                $store_name = apply_filters( 'wwpp_email_store_name', $store_name );

                // get store url.
                $store_url = get_bloginfo( 'url' );

                // Footer image logo.
                $our_products_image = WWPP_IMAGES_URL . 'emails/our-products.png';
                $image_url          = WWP_IMAGES_URL . 'logo.png';
                $footer_image       = apply_filters( 'wwpp_email_footer_image', $image_url );

                // Celebrate.
                $celebrate = isset( $args['celebrate'] ) ? $args['celebrate'] : '';

                $logo_url = add_query_arg(
                    array(
                        'utm_source'   => 'wwp',
                        'utm_medium'   => 'lifecycle',
                        'utm_campaign' => 'wwpplifecyclefooter',
                    ),
                    'https://wholesalesuiteplugin.com/'
                );

                ob_start();

                require $template;

                return wp_kses_post( ob_get_clean() );
            } else {
                return '';
            }
        }

        /**
         * Method that locates the email file.
         *
         * @since 2.0.0.5
         * @access private
         * @param string $template_path Path of the email template.
         * @param string $template_name Name of the email template.
         * @return string
         */
        private function _wwpp_locate_email_file( $template_path = '', $template_name = '' ) {
            $template = locate_template(
                array(
                    trailingslashit( $template_path ) . $template_name,
                    $template_name,
                )
            );

            if ( ! $template ) {
                $template = WWPP_VIEWS_PATH . '/emails/' . $template_name;
            }

            return $template;
        }

        /**
         * Daily event.
         *
         * @access public
         * @return void
         */
        public function daily_event() {
            $settings_allow_lifecycle = get_option( 'wwpp_settings_allow_lifecycle' );
            $settings_allow_lifecycle = ( ! empty( $settings_allow_lifecycle ) ) ? $settings_allow_lifecycle : 'yes';

            if ( 'yes' === $settings_allow_lifecycle ) {
                // Order reports.
                $this->get_order_reports();
            }
        }

        /**
         * Get lead reports.
         *
         * @access public
         * @return void
         */
        public function get_lead_reports() {
            $lead_report = $this->get_total_wholesale_leads( true );

            // Get first lead report.
            $first_lead_type    = 'first-lead';
            $first_lead_type_id = str_replace( '-', '_', trim( $first_lead_type ) );
            $first_lead_sent    = get_option( 'wwpp_lifecycle_email_' . $first_lead_type_id );
            if ( $lead_report > 0 && 'yes' !== $first_lead_sent ) {
                $first_lead_subject = __( 'First Lead Report', 'woocommerce-wholesale-prices-premium' );
                $first_lead_args    = array(
                    'subject' => $first_lead_subject,
                );

                $first_lead_args['achievement_title'] = __( '⭐ Achievement UNLOCKED: Your FIRST Wholesale Lead!', 'woocommerce-wholesale-prices-premium' );
                $achievement_message                  = sprintf(
                    // translators: %1$s open strong, %2$s </strong> tag.
                    __(
                        'Congratulations on your first wholesale lead! 🥳🎉
                        <br><br>You\'ve just unlocked a %1$sMAJOR ACHIEVEMENT%2$s in your wholesale journey,
                        and we couldn\'t be prouder.
                        <br><br>Now that you\'ve hit %1$sLEVEL 1%2$s,
                        it\'s time to power up and keep the momentum going!',
                        'woocommerce-wholesale-prices-premium'
                    ),
                    '<strong>',
                    '</strong>'
                );
                $first_lead_args['achievement_message'] = $achievement_message;

                // Translate content from email contents.
                $first_lead_args['celebrate_row1_description'] = sprintf(
                    // translators: %1$s break.
                    __(
                        'You\'ve made your first wholesale order, which means your efforts and strategies are working. 😉 %1$s
                        Keep up the great work!',
                        'woocommerce-wholesale-prices-premium'
                    ),
                    '<br>',
                );

                // Make sure to save the email type for 1 time send.
                update_option( 'wwpp_lifecycle_email_' . $first_lead_type_id, 'yes' );

                $this->celebrate_email_reports( $first_lead_type, $first_lead_args );
            }

            // Get 100th lead report.
            $onehundred_lead_type          = '100th-lead';
            $onehundred_lead_type_id       = str_replace( '-', '_', trim( $onehundred_lead_type ) );
            $onehundred_lead_sent          = get_option( 'wwpp_lifecycle_email_' . $onehundred_lead_type_id );
            $onehundred_lead_report_filter = apply_filters( 'wwpp_onehundred_lead_report', 99 );
            if ( $lead_report > $onehundred_lead_report_filter && 'yes' !== $onehundred_lead_sent ) {
                $hundredth_lead_subject = __( '100th Lead Report', 'woocommerce-wholesale-prices-premium' );
                $hundredth_lead_args    = array(
                    'subject' => $hundredth_lead_subject,
                );

                $hundredth_lead_args['achievement_title'] = __( '⭐ Achievement UNLOCKED: Your FIRST 100 Wholesale Lead!', 'woocommerce-wholesale-prices-premium' );
                $achievement_message2                     = sprintf(
                    // translators: %1$s open strong, %2$s </strong> tag.
                    __(
                        'Congratulations on your first 100 wholesale lead! 🥳🎉
                        <br><br>You\'ve just unlocked a %1$sMAJOR ACHIEVEMENT%2$s in your wholesale journey,
                        and we couldn\'t be prouder.
                        <br><br>Now that you\'ve hit %1$sLEVEL 2%2$s,
                        it\'s time to power up and keep the momentum going!',
                        'woocommerce-wholesale-prices-premium'
                    ),
                    '<strong>',
                    '</strong>'
                );
                $hundredth_lead_args['achievement_message'] = $achievement_message2;

                // Translate content from email contents.
                $hundredth_lead_args['celebrate_row1_description'] = sprintf(
                    // translators: %1$s break.
                    __(
                        'You\'ve made your first wholesale order, which means your efforts and strategies are working. 😉 %1$s
                        Keep up the great work!',
                        'woocommerce-wholesale-prices-premium'
                    ),
                    '<br>',
                );

                // Make sure to save the email type for 1 time send.
                update_option( 'wwpp_lifecycle_email_' . $onehundred_lead_type_id, 'yes' );

                $this->celebrate_email_reports( $onehundred_lead_type, $hundredth_lead_args );
            }
        }

        /**
         * Get order reports.
         *
         * @access public
         * @return void
         */
        public function get_order_reports() {
            $order_report = $this->get_total_wholesale_orders( true );

            // Get first order report.
            $first_order_type    = 'first-order';
            $first_order_type_id = str_replace( '-', '_', trim( $first_order_type ) );
            $first_order_sent    = get_option( 'wwpp_lifecycle_email_' . $first_order_type_id );
            if ( $order_report > 0 && 'yes' !== $first_order_sent ) {
                $first_order_subject = __( 'First Order Report', 'woocommerce-wholesale-prices-premium' );
                $first_order_args    = array(
                    'subject' => $first_order_subject,
                );

                $first_order_args['achievement_title'] = __( '⭐ Achievement UNLOCKED: Your FIRST Wholesale Order!', 'woocommerce-wholesale-prices-premium' );
                $achievement_message3                  = sprintf(
                    // translators: %1$s open strong, %2$s </strong> tag.
                    __(
                        'Congratulations on your first wholesale order! 🥳🎉
                        <br><br>You\'ve just unlocked a %1$sMAJOR ACHIEVEMENT%2$s in your wholesale journey,
                        and we couldn\'t be prouder.
                        <br><br>Now that you\'ve hit %1$sLEVEL 1%2$s,
                        it\'s time to power up and keep the momentum going!',
                        'woocommerce-wholesale-prices-premium'
                    ),
                    '<strong>',
                    '</strong>'
                );
                $first_order_args['achievement_message'] = $achievement_message3;

                // Translate content from email contents.
                $first_order_args['celebrate_row1_description'] = sprintf(
                    // translators: %1$s break.
                    __(
                        'You\'ve made your first wholesale order, which means your efforts and strategies are working. 😉 %1$s
                        Keep up the great work!',
                        'woocommerce-wholesale-prices-premium'
                    ),
                    '<br>',
                );

                // Make sure to save the email type for 1 time send.
                update_option( 'wwpp_lifecycle_email_' . $first_order_type_id, 'yes' );

                $this->celebrate_email_reports( $first_order_type, $first_order_args );
            }

            // Get 100th order report.
            $hundredth_order_type          = '100th-order';
            $hundredth_order_type_id       = str_replace( '-', '_', trim( $hundredth_order_type ) );
            $hundredth_order_sent          = 'yes';
            $hundredth_order_report_filter = apply_filters( 'wwpp_hundredth_order_report', 99 );
            if ( $order_report > $hundredth_order_report_filter && 'yes' !== $hundredth_order_sent ) {
                $hundredth_order_subject = __( '100th Order Report', 'woocommerce-wholesale-prices-premium' );
                $hundredth_order_args    = array(
                    'subject' => $hundredth_order_subject,
                );

                $hundredth_order_args['achievement_title'] = __( '⭐ Achievement UNLOCKED: Your FIRST 100 Wholesale Order!', 'woocommerce-wholesale-prices-premium' );
                $achievement_message4                      = sprintf(
                    // translators: %1$s open strong, %2$s </strong> tag.
                    __(
                        'Congratulations on your first 100 wholesale order! 🥳🎉
                        <br><br>You\'ve just unlocked a %1$sMAJOR ACHIEVEMENT%2$s in your wholesale journey,
                        and we couldn\'t be prouder.
                        <br><br>Now that you\'ve hit %1$sLEVEL 2%2$s,
                        it\'s time to power up and keep the momentum going!',
                        'woocommerce-wholesale-prices-premium'
                    ),
                    '<strong>',
                    '</strong>'
                );
                $hundredth_order_args['achievement_message'] = $achievement_message4;

                // Translate content from email contents.
                $hundredth_order_args['celebrate_row1_description'] = sprintf(
                    // translators: %1$s break.
                    __(
                        'You\'ve made your first wholesale order, which means your efforts and strategies are working. 😉 %1$s
                        Keep up the great work!',
                        'woocommerce-wholesale-prices-premium'
                    ),
                    '<br>',
                );

                // Make sure to save the email type for 1 time send.
                update_option( 'wwpp_lifecycle_email_' . $hundredth_order_type_id, 'yes' );

                $this->celebrate_email_reports( $hundredth_order_type, $hundredth_order_args );
            }
        }

        /**
         * Monthly event.
         *
         * @access public
         * @return void
         */
        public function monthly_event() {
            $settings_allow_lifecycle = get_option( 'wwpp_settings_allow_lifecycle' );
            $settings_allow_lifecycle = ( ! empty( $settings_allow_lifecycle ) ) ? $settings_allow_lifecycle : 'yes';

            if ( 'yes' === $settings_allow_lifecycle ) {
                // Monthly email updates.
                $this->monthly_email_updates();
            }
        }

        /**
         * Yearly event.
         *
         * @access public
         * @return void
         */
        public function yearly_event() {
            $settings_allow_lifecycle = get_option( 'wwpp_settings_allow_lifecycle' );
            $settings_allow_lifecycle = ( ! empty( $settings_allow_lifecycle ) ) ? $settings_allow_lifecycle : 'yes';

            if ( 'yes' === $settings_allow_lifecycle ) {
                // Yearly email updates.
                $this->yearly_email_updates();
            }
        }

        /**
         * Celebrate email reports.
         *
         * @access public
         * @param string $type Type of email report.
         * @param array  $args Array of arguments to be passed to the email template.
         *
         * @return void
         */
        public function celebrate_email_reports( $type, $args ) {
            $recipient = get_option( 'admin_email' );
            $recipient = apply_filters( 'wwpp_celebrate_email_reports_recipient', $recipient );

            // Translate content from email contents.
            $args['celebrate_level_title']      = __( 'Here\'s how to level up your wholesale game:', 'woocommerce-wholesale-prices-premium' );
            $args['celebrate_row1_title']       = __( 'Celebrate Your Success', 'woocommerce-wholesale-prices-premium' );
            $args['celebrate_row2_title']       = __( 'Gear up for the next level with these easy power tips', 'woocommerce-wholesale-prices-premium' );
            $args['celebrate_row2_list']        = array(
                __( 'Create limited-time bulk pricing offers to encourage quick purchases.', 'woocommerce-wholesale-prices-premium' ),
                __( 'Feature your best-selling products on your order form.', 'woocommerce-wholesale-prices-premium' ),
                __( 'Don\'t let new leads go cold! Send follow-up emails to keep them engaged.', 'woocommerce-wholesale-prices-premium' ),
                __( 'Offer flexible payment options to make bulk buying easier.', 'woocommerce-wholesale-prices-premium' ),
            );
            $args['celebrate_row3_title']       = __( 'Keep Learning', 'woocommerce-wholesale-prices-premium' );
            $args['celebrate_row3_description'] = sprintf(
                // translators: %1$s break.
                __(
                    'Our blog is filled with valuable tips & strategies to help you grow your wholesale business. %1$s
                    Check out our latest posts to stay ahead of the game!',
                    'woocommerce-wholesale-prices-premium'
                ),
                '<br>'
            );
            $args['celebrate_bottom_message'] = sprintf(
                // translators: %1$s break, %2$s open strong, %3$s close strong.
                __(
                    'Keep leveling up, and we\'ll be right here cheering & supporting you every step of the way . 💗 %1$s%1$s Here\'s to countless more achievements!%1$s%1$sCheers, %1$s%2$sThe Wholesale Suite Team%3$s',
                    'woocommerce-wholesale-prices-premium'
                ),
                '<br>',
                '<strong>',
                '</strong>'
            );

            $support_link                      = add_query_arg(
                array(
                    'utm_source'   => 'wwp',
                    'utm_medium'   => 'lifecycle',
                    'utm_campaign' => 'wwpplifecycle' . str_replace( '-', '', $type ),
                ),
                'https://wholesalesuiteplugin.com/support/'
            );
            $args['celebrate_support_message'] = sprintf(
                // translators: %1$s break, %2$s open a tag, %3$s close a tag.
                __(
                    'We are here to help you. If you have any questions or need assistance, %1$s%2$splease let us know%3$s',
                    'woocommerce-wholesale-prices-premium'
                ),
                '<br>',
                '<a href="' . esc_url( $support_link ) . '">',
                '</a>'
            );

            $default_args = array(
                'recipient' => $recipient,
                'celebrate' => true,
            );
            $args         = array_merge( $default_args, $args );

            // Get template names.
            $template_name = 'celebrate-' . $type;

            $this->wwpp_email_sender( $template_name, $args );
        }

        /**
         * Regular email updates.
         *
         * @access public
         * @return void
         */
        public function monthly_email_updates() {

            $recipient = get_option( 'admin_email' );
            $recipient = apply_filters( 'wwpp_regular_email_updates_recipient', $recipient );

            $subject = __( 'Regular Email Updates', 'woocommerce-wholesale-prices-premium' );
            $subject = apply_filters( 'wwpp_regular_email_updates_subject', $subject );

            $greeting_message = __( 'Let\'s see how your wholesale business performed in the month.', 'woocommerce-wholesale-prices-premium' );

            $args = array(
                'recipient'        => $recipient,
                'subject'          => $subject,
                'greeting_message' => $greeting_message,
            );

            // Get reports.
            $args['wholesale_order_amount']       = $this->get_total_wholesale_orders();
            $args['wholesale_order_percentage']   = $this->get_total_wholesale_orders_percentage();
            $args['wholesale_revenue_amount']     = $this->get_total_wholesale_revenue();
            $args['wholesale_revenue_percentage'] = $this->get_total_wholesale_revenue_percentage();
            $args['wholesale_leads_amount']       = $this->get_total_wholesale_leads();
            $args['wholesale_leads_percentage']   = $this->get_total_wholesale_leads_percentage();

            $general_settings_url = admin_url( 'admin.php?page=wholesale-settings&tab-child=general' );
            $pro_tips_message     = sprintf(
                // translators: %1$s link, %2$s </a> tag.
                __(
                    'Want to increase average order values? %1$s<strong>Set minimum order requirements</strong>%2$s to ensure wholesale pricing only kicks in for larger orders. This keeps your profits healthy while offering great deals. Win-win! 😉',
                    'woocommerce-wholesale-prices-premium'
                ),
                '<a target="_blank" href="' . $general_settings_url . '"> ',
                '</a>'
            );
            $args['wholesale_pro_tips'] = $pro_tips_message;

            // Get user reports.
            $args['user_reports'] = $this->get_top_wholesale_orders();

            // Get report link.
            $args['report_link'] = admin_url( 'admin.php?page=wholesale-suite' );

            // Learn more.
            $learn_link         = add_query_arg(
                array(
                    'utm_source'   => 'wwp',
                    'utm_medium'   => 'lifecycle',
                    'utm_campaign' => 'wwpplifecycleregular',
                ),
                'https://wholesalesuiteplugin.com/knowledge-base/'
            );
            $args['learn_link'] = $learn_link;

            $this->wwpp_email_sender( 'regular-email-updates', $args );
        }

        /**
         * Method that retrieves the total number of wholesale orders.
         *
         * @param bool $overall Overall total.
         * @access public
         * @return int
         */
        public function get_total_wholesale_orders( $overall = false ) {
            // Get the start and end date of the current month.
            $first_day_of_current_month = gmdate( 'Y-m-01' );
            $last_day_of_current_month  = gmdate( 'Y-m-t' );

            $order_args = array(
                'status'          => array( 'wc-on-hold', 'wc-processing', 'wc-completed' ),
                'return'          => 'ids',
                'limit'           => -1,
                'wholesale_order' => true,
                'date_query'      => array(
                    array(
                        'after'     => $first_day_of_current_month,
                        'before'    => $last_day_of_current_month,
                        'inclusive' => true,
                    ),
                ),
            );

            if ( $overall ) {
                unset( $order_args['date_created'] );
            }

            $total_orders           = wc_get_orders( $order_args );
            $total_wholesale_orders = (int) count( $total_orders );
            return $total_wholesale_orders;
        }

        /**
         * Method that retrieves the total wholesale orders percentage.
         *
         * @access public
         * @return int
         */
        public function get_total_wholesale_orders_percentage() {
            $total_all_orders = $this->get_total_wholesale_orders();

            // Get the start and end date of the previous month.
            $first_day_of_last_month = gmdate( 'Y-m-01', strtotime( 'first day of last month' ) );
            $last_day_of_last_month  = gmdate( 'Y-m-t', strtotime( $first_day_of_last_month ) );

            $orders_args = array(
                'status'          => array( 'wc-on-hold', 'wc-processing', 'wc-completed' ),
                'return'          => 'ids',
                'limit'           => -1,
                'wholesale_order' => true,
                'date_query'      => array(
                    array(
                        'after'     => $first_day_of_last_month,
                        'before'    => $last_day_of_last_month,
                        'inclusive' => true,
                    ),
                ),
            );

            $orders       = wc_get_orders( $orders_args );
            $total_orders = (int) count( $orders );

            $percentage = 0;
            if ( $total_orders > 0 && $total_orders > $total_all_orders ) {
                $percentage = ( ( $total_all_orders - $total_orders ) / $total_orders ) * 100;
            } elseif ( $total_orders > 0 && $total_orders < $total_all_orders ) {
                $percentage = ( $total_orders / $total_all_orders ) * 100;
            } else {
                $percentage = $total_all_orders > 0 ? 100 : 0;
            }

            return round( $percentage );
        }

        /**
         * Method that retrieves the total wholesale revenue.
         *
         * @param bool $number Return number.
         * @access public
         * @return string
         */
        public function get_total_wholesale_revenue( $number = false ) {
            // Get the start and end date of the current month.
            $first_day_of_current_month = gmdate( 'Y-m-01' );
            $last_day_of_current_month  = gmdate( 'Y-m-t' );

            $order_args = array(
                'status'          => array( 'wc-on-hold', 'wc-processing', 'wc-completed' ),
                'limit'           => -1,
                'wholesale_order' => true,
                'date_query'      => array(
                    array(
                        'after'     => $first_day_of_current_month,
                        'before'    => $last_day_of_current_month,
                        'inclusive' => true,
                    ),
                ),
            );

            $total_orders  = wc_get_orders( $order_args );
            $total_revenue = 0;
            foreach ( $total_orders as $order ) {
                $total_revenue += $order->get_total();
            }

            return $number ? $total_revenue : wc_price( $total_revenue );
        }

        /**
         * Method that retrieves the total wholesale revenue percentage.
         *
         * @access public
         * @return int
         */
        public function get_total_wholesale_revenue_percentage() {
            $total_all_revenue = $this->get_total_wholesale_revenue( true );

            // Get the start and end date of the previous month.
            $first_day_of_last_month = gmdate( 'Y-m-01', strtotime( 'first day of last month' ) );
            $last_day_of_last_month  = gmdate( 'Y-m-t', strtotime( $first_day_of_last_month ) );

            $orders_args = array(
                'status'          => array( 'wc-on-hold', 'wc-processing', 'wc-completed' ),
                'limit'           => -1,
                'wholesale_order' => true,
                'date_query'      => array(
                    array(
                        'after'     => $first_day_of_last_month,
                        'before'    => $last_day_of_last_month,
                        'inclusive' => true,
                    ),
                ),
            );

            $orders        = wc_get_orders( $orders_args );
            $total_revenue = 0;
            foreach ( $orders as $order ) {
                $total_revenue += $order->get_total();
            }

            $percentage = 0;
            if ( $total_revenue > 0 && $total_revenue > $total_all_revenue ) {
                $percentage = ( ( $total_all_revenue - $total_revenue ) / $total_revenue ) * 100;
            } elseif ( $total_revenue > 0 && $total_revenue < $total_all_revenue ) {
                $percentage = ( $total_revenue / $total_all_revenue ) * 100;
            } else {
                $percentage = $total_all_revenue > 0 ? 100 : 0;
            }

            return round( $percentage );
        }

        /**
         * Method that retrieves the total number of wholesale leads.
         *
         * @param bool $overall Overall total.
         * @access public
         * @return int
         */
        public function get_total_wholesale_leads( $overall = false ) {
            $all_registered_wholesale_roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();
            $wholesale_roles                = array_keys( $all_registered_wholesale_roles );

            // Get the start and end date of the current month.
            $first_day_of_current_month = gmdate( 'Y-m-01' );
            $last_day_of_current_month  = gmdate( 'Y-m-t' );

            $args = array(
                'role__in'    => $wholesale_roles,
                'count_total' => true,
                'fields'      => 'ID',
                'date_query'  => array(
                    array(
                        'after'     => $first_day_of_current_month,
                        'before'    => $last_day_of_current_month,
                        'inclusive' => true,
                    ),
                ),
            );

            if ( $overall ) {
                unset( $args['date_query'] );
            }

            $user_query  = new WP_User_Query( $args );
            $total_users = $user_query->get_total();

            return $total_users;
        }

        /**
         * Method that retrieves the total wholesale leads percentage.
         *
         * @access public
         * @return int
         */
        public function get_total_wholesale_leads_percentage() {
            $total_all_leads = $this->get_total_wholesale_leads();

            // Get the start and end date of the previous month.
            $first_day_of_last_month = gmdate( 'Y-m-01', strtotime( 'first day of last month' ) );
            $last_day_of_last_month  = gmdate( 'Y-m-t', strtotime( $first_day_of_last_month ) );

            $all_registered_wholesale_roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();
            $wholesale_roles                = array_keys( $all_registered_wholesale_roles );

            $args = array(
                'role__in'    => $wholesale_roles,
                'count_total' => true,
                'fields'      => 'ID',
                'date_query'  => array(
                    array(
                        'after'     => $first_day_of_last_month,
                        'before'    => $last_day_of_last_month,
                        'inclusive' => true,
                    ),
                ),
            );

            $user_query  = new WP_User_Query( $args );
            $total_users = $user_query->get_total();

            $percentage = 0;
            if ( $total_users > 0 && $total_users > $total_all_leads ) {
                $percentage = ( ( $total_all_leads - $total_users ) / $total_users ) * 100;
            } elseif ( $total_users > 0 && $total_users < $total_all_leads ) {
                $percentage = ( $total_users / $total_all_leads ) * 100;
            } else {
                $percentage = $total_all_leads > 0 ? 100 : 0;
            }

            return round( $percentage );
        }

        /**
         * Method that retrieves the top wholesale customers.
         *
         * @access public
         * @return array
         */
        public function get_top_wholesale_orders() {
            $all_registered_wholesale_roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();
            $wholesale_roles                = array_keys( $all_registered_wholesale_roles );

            $wholesale_spent = array();
            $customers       = get_users( array( 'role__in' => $wholesale_roles ) );
            if ( ! empty( $customers ) ) {
                foreach ( $customers as $customer ) {
                    $customer_id = $customer->ID;
                    $spent       = wc_get_customer_total_spent( $customer_id );

                    if ( $spent > 0 ) {
                        $wholesale_spent[] = array(
                            'id'        => (int) $customer_id,
                            'name'      => $customer->display_name,
                            'spent_raw' => (float) $spent,
                            'spent'     => wc_price( $spent ),
                        );
                    }
                }

                $spent_sort = array_column( $wholesale_spent, 'spent_raw' );
                array_multisort( $spent_sort, SORT_DESC, $wholesale_spent );

                $limit           = apply_filters( 'wwp_top_wholesale_customers_limit', 5 );
                $wholesale_spent = array_slice( $wholesale_spent, 0, $limit, true );
            }

            return $wholesale_spent;
        }

        /**
         * Regular email updates.
         *
         * @access public
         * @return void
         */
        public function yearly_email_updates() {

            $recipient = get_option( 'admin_email' );
            $recipient = apply_filters( 'wwpp_yearly_email_updates_recipient', $recipient );

            $subject = __( 'Yearly Email Updates', 'woocommerce-wholesale-prices-premium' );
            $subject = apply_filters( 'wwpp_yearly_email_updates_subject', $subject );

            $args = array(
                'recipient' => $recipient,
                'subject'   => $subject,
                'celebrate' => true,
            );

            $args['achievement_title'] = __( '⭐ Your Yearly Wholesale Update!', 'woocommerce-wholesale-prices-premium' );
            $achievement_message       = sprintf(
                // translators: %1$s open strong, %2$s </strong> tag.
                __(
                    'Congratulations on your yearly wholesale order! 🥳🎉
                    <br><br>You\'ve just unlocked a %1$sMAJOR ACHIEVEMENT%2$s in your wholesale journey,
                    and we couldn\'t be prouder.
                    <br><br>Now that you\'ve hit %1$sLEVEL 10%2$s,
                    it\'s time to power up and keep the momentum going!',
                    'woocommerce-wholesale-prices-premium'
                ),
                '<strong>',
                '</strong>'
            );
            $args['achievement_message'] = $achievement_message;

            // Translate content from email contents.
            $args['celebrate_level_title']      = __( 'Here\'s how to level up your wholesale game:', 'woocommerce-wholesale-prices-premium' );
            $args['celebrate_row1_title']       = __( 'Celebrate Your Success', 'woocommerce-wholesale-prices-premium' );
            $args['celebrate_row1_description'] = sprintf(
                // translators: %1$s break.
                __(
                    'You\'ve made your first wholesale order, which means your efforts and strategies are working. 😉 %1$s
                    Keep up the great work!',
                    'woocommerce-wholesale-prices-premium'
                ),
                '<br>',
            );
            $args['celebrate_row2_title']       = __( 'Gear up for the next level with these easy power tips', 'woocommerce-wholesale-prices-premium' );
            $args['celebrate_row2_list']        = array(
                __( 'Create limited-time bulk pricing offers to encourage quick purchases.', 'woocommerce-wholesale-prices-premium' ),
                __( 'Feature your best-selling products on your order form.', 'woocommerce-wholesale-prices-premium' ),
                __( 'Don\'t let new leads go cold! Send follow-up emails to keep them engaged.', 'woocommerce-wholesale-prices-premium' ),
                __( 'Offer flexible payment options to make bulk buying easier.', 'woocommerce-wholesale-prices-premium' ),
            );
            $args['celebrate_row3_title']       = __( 'Keep Learning', 'woocommerce-wholesale-prices-premium' );
            $args['celebrate_row3_description'] = sprintf(
                // translators: %1$s break.
                __(
                    'Our blog is filled with valuable tips & strategies to help you grow your wholesale business. %1$s
                    Check out our latest posts to stay ahead of the game!',
                    'woocommerce-wholesale-prices-premium'
                ),
                '<br>'
            );
            $args['celebrate_bottom_message'] = sprintf(
                // translators: %1$s break, %2$s open strong, %3$s close strong.
                __(
                    'Keep leveling up, and we\'ll be right here cheering & supporting you every step of the way . 💗 %1$s%1$s Here\'s to countless more achievements!%1$s%1$sCheers, %1$s%2$sThe Wholesale Suite Team%3$s',
                    'woocommerce-wholesale-prices-premium'
                ),
                '<br>',
                '<strong>',
                '</strong>'
            );

            $support_link                      = add_query_arg(
                array(
                    'utm_source'   => 'wwp',
                    'utm_medium'   => 'lifecycle',
                    'utm_campaign' => 'wwpplifecycleyearly',
                ),
                'https://wholesalesuiteplugin.com/support/'
            );
            $args['celebrate_support_message'] = sprintf(
                // translators: %1$s break, %2$s open a tag, %3$s close a tag.
                __(
                    'We are here to help you. If you have any questions or need assistance, %1$s%2$splease let us know%3$s',
                    'woocommerce-wholesale-prices-premium'
                ),
                '<br>',
                '<a href="' . esc_url( $support_link ) . '">',
                '</a>'
            );

            $this->wwpp_email_sender( 'year-email-review', $args );
        }

        /**
         * WWPP event schedule.
         *
         * @access public
         * @return void
         */
        public function wwpp_event_schedule() {
            if ( ! wp_next_scheduled( 'wwpp_daily_event' ) ) {
                // Schedule the event to run daily at 00:00 (midnight).
                wp_schedule_event( strtotime( 'midnight' ), 'daily', 'wwpp_daily_event' );
            }

            if ( ! wp_next_scheduled( 'wwpp_monthly_event' ) ) {
                // Schedule the event on the first of the next month at 00:00.
                $first_day_next_month = strtotime( 'last day of this month 23:59:59' );
                wp_schedule_event( $first_day_next_month, 'monthly', 'wwpp_monthly_event' );
            }
        }

        /**
         * Add custom cron schedule.
         *
         * @access public
         *
         * @param array $schedules Array of schedules.
         * @return array
         */
        public function cron_schedules( $schedules ) {
            // Add a monthly interval.
            $schedules['monthly'] = array(
                'interval' => 2592000, // 30 days in seconds.
                'display'  => __( 'Once a month' ),
            );

            // Add a yearly interval.
            $schedules['yearly'] = array(
                'interval' => 31536000, // 1 year in seconds (365 days).
                'display'  => __( 'Once a Year' ),
            );

            return $schedules;
        }

        /**
         * Clear schedule event.
         *
         * @access public
         * @return void
         */
        public function wwpp_clear_event() {
            $daily_timestamp = wp_next_scheduled( 'wwpp_daily_event' );
            if ( $daily_timestamp ) {
                wp_unschedule_event( $daily_timestamp, 'wwpp_daily_event' );
            }

            $monthly_timestamp = wp_next_scheduled( 'wwpp_monthly_event' );
            if ( $monthly_timestamp ) {
                wp_unschedule_event( $monthly_timestamp, 'wwpp_monthly_event' );
            }
        }

        /**
         * Lifecycle activation.
         *
         * @access public
         * @return void
         */
        public function wwpp_lifecycle_activation() {
            $order_report = $this->get_total_wholesale_orders( true );

            // Get first order report.
            $first_order_type    = 'first-order';
            $first_order_type_id = str_replace( '-', '_', trim( $first_order_type ) );
            $first_order_sent    = get_option( 'wwpp_lifecycle_email_' . $first_order_type_id );
            if ( $order_report > 0 && 'yes' !== $first_order_sent ) {
                update_option( 'wwpp_lifecycle_email_' . $first_order_type_id, 'yes' );
            }
        }

        /**
         * Filter settings tabs.
         *
         * @since  2.0
         * @access public
         *
         * @param array $tabs Array of tabs.
         * @return array
         */
        public function life_cycle_settings_tabs( $tabs ) {
            $tabs['wholesale_prices']['child']['lifecycle_email'] = array(
                'sort'     => 6,
                'key'      => 'lifecycle_email',
                'label'    => __( 'Advanced', 'woocommerce-wholesale-prices-premium' ),
                'sections' => array(
                    'lifecycle_email_options' => array(
                        'label' => '',
                        'desc'  => '',
                    ),
                ),
            );

            return $tabs;
        }

        /**
         * Filter additional controls.
         *
         * @param array $controls Additional controls.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function life_cycle_setting_default_controls( $controls ) {
            $controls['wholesale_prices']['lifecycle_email'] = $this->tab_controls();

            return $controls;
        }

        /**
         * Tab controls.
         *
         * @access public
         * @return array
         */
        public function tab_controls() {
            $lifecycle_options            = array();
            $settings_allow_lifecycle     = get_option( 'wwpp_settings_allow_lifecycle' );
            $settings_allow_lifecycle     = ( ! empty( $settings_allow_lifecycle ) ) ? $settings_allow_lifecycle : 'yes';
            $settings_lifecycle_recipient = get_option( 'wwpp_settings_lifecycle_recipient' );
            $settings_lifecycle_recipient = ( ! empty( $settings_lifecycle_recipient ) ) ? $settings_lifecycle_recipient : get_option( 'admin_email' );

            $lifecycle_options['lifecycle_email_options'] = array(
                array(
                    'type'        => 'checkbox',
                    'id'          => 'wwpp_settings_allow_lifecycle',
                    'label'       => __( 'Email Digests', 'woocommerce-wholesale-prices-premium' ),
                    'input_label' => __( 'Enable email digests showing helpful reports on the health of your wholesale store, tips, and more.', 'woocommerce-wholesale-prices-premium' ),
                    'default'     => $settings_allow_lifecycle,
                ),
                array(
                    'type'        => 'text',
                    'id'          => 'wwpp_settings_lifecycle_recipient',
                    'label'       => __( 'Recipient', 'woocommerce-wholesale-prices-premium' ),
                    'description' => __( 'Email that will receive notifications.', 'woocommerce-wholesale-prices-premium' ),
                    'default'     => $settings_lifecycle_recipient,
                ),
            );

            return $lifecycle_options;
        }

        /**
         * Filter recipient email.
         *
         * @since  2.0
         * @access public
         *
         * @param string $recipient Recipient email.
         * @return string
         */
        public function filter_lifecycle_email_recipient( $recipient ) {
            $settings_lifecycle_recipient = get_option( 'wwpp_settings_lifecycle_recipient' );
            $settings_allow_lifecycle     = get_option( 'wwpp_settings_allow_lifecycle' );
            $settings_allow_lifecycle     = ( ! empty( $settings_allow_lifecycle ) ) ? $settings_allow_lifecycle : 'yes';

            if ( 'yes' === $settings_allow_lifecycle && ! empty( $settings_lifecycle_recipient ) ) {
                $recipient = trim( $settings_lifecycle_recipient );
            }

            return $recipient;
        }

        /**
         * Execute model.
         *
         * @access public
         */
        public function run() {
            // Schedule event.
            add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
            add_action( 'wp', array( $this, 'wwpp_event_schedule' ) );
            add_action( 'wwpp_daily_event', array( $this, 'daily_event' ) );
            add_action( 'wwpp_monthly_event', array( $this, 'monthly_event' ) );

            // Event runner after activation.
            register_activation_hook( WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'woocommerce-wholesale-prices-premium' . DIRECTORY_SEPARATOR . 'woocommerce-wholesale-prices-premium.bootstrap.php', array( $this, 'wwpp_lifecycle_activation' ) );

            // Clear schedule daily event.
            register_deactivation_hook( WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'woocommerce-wholesale-prices-premium' . DIRECTORY_SEPARATOR . 'woocommerce-wholesale-prices-premium.bootstrap.php', array( $this, 'wwpp_clear_event' ) );

            // Filter settings tabs.
            add_filter( 'wwp_admin_setting_default_tabs', array( $this, 'life_cycle_settings_tabs' ), 10, 1 );

            // Filter additional controls.
            add_filter( 'wwp_admin_setting_default_controls', array( $this, 'life_cycle_setting_default_controls' ), 10, 1 );

            // Filter recipient email.
            add_filter( 'wwpp_lifecycle_email_recipient', array( $this, 'filter_lifecycle_email_recipient' ), 10, 1 );
        }
    }
}
