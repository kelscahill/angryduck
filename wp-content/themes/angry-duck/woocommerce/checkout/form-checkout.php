<?php
/**
 * Checkout Form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/form-checkout.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_before_checkout_form', $checkout );

// If checkout registration is disabled and not logged in, the user cannot checkout.
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
  echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
  return;
}
?>
<form data-grid="grid" name="checkout" method="post" class="c-checkout u-space--top checkout woocommerce-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">
  <?php if ( $checkout->get_checkout_fields() ) : ?>
    <div class="c-checkout__customer-details u-spacing u-space--top" data-grid="6@md">
      <?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>
      <div class="u-spacing--double woocommerce-customer-details" id="customer_details">
        <?php do_action( 'woocommerce_checkout_billing' ); ?>
        <?php do_action( 'woocommerce_checkout_shipping' ); ?>
      </div>
      <?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>
    </div>
  <?php endif; ?>
  <div class="c-checkout__review-order" data-grid="6@md">
    <div class="c-review-order u-spacing">
      <?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>
      <h3 class="o-heading--l" id="order_review_heading"><?php esc_html_e( 'Your order', 'woocommerce' ); ?></h3>
      <?php do_action( 'woocommerce_checkout_before_order_review' ); ?>
      <div id="order_review" class="c-review-order__details u-spacing woocommerce-checkout-review-order">
        <?php do_action( 'woocommerce_checkout_order_review' ); ?>
      </div>
      <?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
    </div>
  </div>
</form>
<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
