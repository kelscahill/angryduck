<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Factories
 */

namespace RymeraWebCo\WWOF\Factories;

use RymeraWebCo\WWOF\Abstracts\Abstract_Class;

/**
 * Class Admin_Notice
 */
class Admin_Notice extends Abstract_Class {

    /**
     * Holds the admin notice message.
     *
     * @var string The admin notice message.
     */
    protected $message;

    /**
     * Holds the admin notice type.
     *
     * @var string The admin notice type.
     */
    protected $type;

    /**
     * Holds the type of message. Either 'string' or 'html'.
     *
     * @var string The message type.
     */
    protected $message_type;

    /**
     * Constructor.
     *
     * @param string $message      The admin notice message.
     * @param string $type         The admin notice type.
     * @param string $message_type string The message type. Either 'string' or 'html'.
     */
    public function __construct( $message, $type = 'error', $message_type = 'string' ) {

        $this->message      = $message;
        $this->type         = $type;
        $this->message_type = $message_type;
    }

    /**
     * Run the class
     *
     * @codeCoverageIgnore
     * @since 3.0
     */
    public function run() {

        if ( did_action( 'admin_notices' ) ) {
            $this->add_notice();
        } else {
            add_action( 'admin_notices', array( $this, 'add_notice' ) );
        }
    }

    /**
     * Renders admin notice.
     *
     * @since 3.0
     * @return void
     */
    public function add_notice() {

        $message_id = 'woocommerce-wholesale-order-form-' . md5( $this->message );
        ?>
        <div
            class="notice notice-<?php echo esc_attr( "$this->type $this->type" ); ?> is-dismissible"
            id="<?php echo esc_attr( $message_id ); ?>"
        >
            <?php if ( 'html' === $this->message_type ) : ?>
                <?php echo wp_kses( $this->message, 'post' ); ?>
            <?php else : ?>
                <p><?php echo esc_html( $this->message ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
}
