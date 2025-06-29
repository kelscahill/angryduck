<?php
/**
 * The template for displaying registration form
 *
 * Override this template by copying it to yourtheme/woocommerce/wwlc-login-form.php
 *
 * @author      Rymera Web Co
 * @package     WooCommerceWholeSaleLeadCapture/Templates
 * @version     1.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}
// Exit if accessed directly

// NOTE: Don't Remove any ID or Classes inside this template when overriding it.
// Some JS Files Depend on it. You are free to add ID and Classes without any problem.

?>
<div class="woocommerce">
	<div id="wwlc-login-form">

		<?php do_action('wwlc_before_login_form', $args);?>

		<form class="woocommerce-form woocommerce-form-login" name="<?php echo esc_attr($args['form_id']); ?>"
			id="<?php echo esc_attr($args['form_id']); ?>"
			action="<?php echo esc_attr($args['form_action']); ?>"
			method="<?php echo esc_attr($args['form_method']); ?>">

			<p class="login-username woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="<?php echo esc_attr($args['id_username']); ?>">
					<?php echo esc_html($args['label_username']); ?>
					<span style="color:red">*</span>
				</label>
				<input type="text" name="wwlc_username" id="<?php echo esc_attr($args['id_username']); ?>" class="input woocommerce-Input woocommerce-Input--text input-text" value="<?php echo esc_attr($args['value_username']); ?>" size="20" />
			</p>

			<p class="login-password woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="<?php echo esc_attr($args['id_password']); ?>"><?php echo esc_html($args['label_password']); ?> <span style="color:red">*</span></label>
				<input type="password" name="wwlc_password" id="<?php echo esc_attr($args['id_password']); ?>" class="input woocommerce-Input woocommerce-Input--text input-text" value="" size="20" />
			</p>

			<?php if ($args['remember']): ?>
				<p class="login-remember form-row form-row-wide">
					<label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme">
						<input name="rememberme" type="checkbox" id="<?php echo esc_attr($args['id_remember']); ?>" value="forever"<?php checked($args['value_remember'], true);?> />
						<?php echo esc_html($args['label_remember']); ?>
					</label>
				</p>
			<?php endif;?>

			<?php do_action('wwlc_login_forms', $args);?>

			<p class="login-submit form-row form-row-wide">
				<input type="submit" name="wp-submit" id="<?php echo esc_attr($args['id_submit']); ?>" class="woocommerce-button button woocommerce-form-login__submit" value="<?php echo esc_attr($args['label_log_in']); ?>" />
			</p>
			
			<p class="woocommerce-LostPassword lost_password">
				<a class="register_link" href="<?php echo wwlc_get_url_of_page_option('wwlc_general_registration_page'); ?>" ><?php _e('Register', 'woocommerce-wholesale-lead-capture');?></a>
				<a class="lost_password_link" href="<?php echo wp_lostpassword_url(); ?>" ><?php _e('Lost your password?', 'woocommerce-wholesale-lead-capture');?></a>
			</p>
			<?php wp_nonce_field('wwlc_login_form', 'wwlc_login_form_nonce_field');?>

		</form>

		<?php do_action('wwlc_after_login_form', $args);?>


	</div><!--#wwlc-login-form-->
</div><!--.woocommerce-->
