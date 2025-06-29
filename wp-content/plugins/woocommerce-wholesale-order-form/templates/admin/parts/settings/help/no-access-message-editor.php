<?php
/**
 * No Access Message Editor Template Part
 *
 * @package RymeraWebCo\WWOF
 * @since   3.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Available variables:
 *
 * @var array  $value
 * @var array  $field_description
 * @var string $val
 * @var string $description
 */
?>

<tr style="vertical-align: top;">
	<th scope="row" class="titledesc">
		<label for="<?php echo esc_attr( $value['id'] ); ?>">
            <?php echo esc_html( $value['title'] ); ?>
            <?php echo $field_description['tooltip_html']; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</label>
	</th>
	<td class="forminp forminp-<?php echo esc_attr( sanitize_title( $value['type'] ) ); ?>">
        <?php
        wp_editor(
            html_entity_decode( $val ),
            'wwof_permissions_noaccess_message',
            array(
                'wpautop'       => true,
                'textarea_name' => 'noaccess_message[' . $value['id'] . ']',
            )
        );
        ?>
        <?php echo $description;//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</td>
</tr>
