<h3><?php esc_html_e( 'Wholesale Lead Information', 'woocommerce-wholesale-lead-capture' ); ?></h3>

<table class="form-table">
    <?php wp_nonce_field( 'wwlc_update_user', 'wwlc_update_user_nonce_field' ); ?>
    <?php
    // Note: Password fields are special, we don't need to include them here. If they want to edit there passwords
    // then they can just do the wp way of changing password. Adding it here also exposes some security issues.
    foreach ( $registration_form_fields as $field ) {

        if ( ! $field['custom_field'] || in_array( $field['type'], array( 'wwlc_password', 'password', 'content', 'terms_conditions' ), true ) ) {
            continue;
        }

        $disabledNotice = '';
        if ( ! $field['active'] ) {
            $disabledNotice = __( ' (Disabled)', 'woocommerce-wholesale-lead-capture' );
        }
        ?>

        <tr>
            <th><label for="<?php echo esc_attr( $field['id'] ); ?>"><?php echo esc_attr( $field['label'] . $disabledNotice ); ?></label></th>

            <?php if ( 'text' === $field['type'] || 'email' === $field['type'] || 'url' === $field['type'] ) { ?>

                <td>
                    <input type="<?php echo esc_attr( $field['type'] ); ?>" name="<?php echo esc_attr( $field['name'] ); ?>" id="<?php echo esc_attr( $field['id'] ); ?>" value="<?php echo esc_attr( get_user_meta( $user->ID, $field['id'], true ) ); ?>" class="regular-text" /><br />
                    <span class="description">
                        <?php
                            /* translators: %1$s Label */
                            echo sprintf( esc_html__( 'Please enter your %1$s.', 'woocommerce-wholesale-lead-capture' ), esc_html( $field['label'] ) );
                        ?>
                    </span>
                </td>

                <?php if ( ! empty( $field['sub_fields'] ) ) { ?>
                    </tr>
                    <?php
                        $countSubFields = $field['sub_fields'];
                        $i              = 0;
                        foreach ( $field['sub_fields'] as $field ) {

                            $disabledNotice = '';
                            if ( ! $field['active'] ) {
                                $disabledNotice = __( ' (Disabled)', 'woocommerce-wholesale-lead-capture' );
                            }
                            ?>

                            <tr>
                                <th><label for="<?php echo esc_attr( $field['id'] ); ?>"><?php echo esc_html( $field['label'] ) . wp_kses_post( $disabledNotice ); ?></label></th>
                                <td>
                                    <input type="<?php echo esc_attr( $field['type'] ); ?>" name="<?php echo esc_attr( $field['name'] ); ?>" id="<?php echo esc_attr( $field['id'] ); ?>" value="<?php echo esc_attr( get_user_meta( $user->ID, $field['id'], true ) ); ?>" class="regular-text" /><br />
                                    <span class="description">
                                        <?php
                                            /* translators: %1$s Label */
                                            echo sprintf( esc_html__( 'Please enter your %1$s.', 'woocommerce-wholesale-lead-capture' ), esc_html( $field['label'] ) );
                                        ?>
                                    </span>
                                </td>
                                <?php
                                echo $i < $countSubFields ? '</tr>' : '';
                        }
                        ?>
                <?php } ?>

            <?php } elseif ( 'textarea' === $field['type'] ) { ?>

                <td>
                    <textarea name="<?php echo esc_attr( $field['name'] ); ?>" id="<?php echo esc_attr( $field['id'] ); ?>" cols="30" rows="5"><?php echo esc_attr( get_user_meta( $user->ID, $field['id'], true ) ); ?></textarea>
                    <span class="description" style="display: block;">
                        <?php
                            /* translators: %1$s Label */
                            echo sprintf( esc_html__( 'Please enter your %1$s.', 'woocommerce-wholesale-lead-capture' ), esc_html( $field['label'] ) );
                        ?>
                    </span>
                </td>

            <?php } elseif ( 'number' === $field['type'] ) { ?>

                <td>
                    <input type="<?php echo esc_attr( $field['type'] ); ?>" min="<?php echo esc_attr( $field['attributes']['min'] ) ?? ''; ?>" max="<?php echo esc_attr( $field['attributes']['max'] ) ?? ''; ?>" step="<?php echo esc_attr( $field['attributes']['step'] ) ?? ''; ?>" name="<?php echo esc_attr( $field['name'] ); ?>" id="<?php echo esc_attr( $field['id'] ); ?>" value="<?php echo esc_attr( get_user_meta( $user->ID, $field['id'], true ) ); ?>" class="regular-text" /><br />
                    <span class="description">
                        <?php
                            /* translators: %1$s Label */
                            echo sprintf( esc_html__( 'Please enter your %1$s.', 'woocommerce-wholesale-lead-capture' ), esc_html( $field['label'] ) );
                        ?>
                    </span>
                </td>

            <?php
            } elseif ( 'select' === $field['type'] ) {

                $userMeta = get_user_meta( $user->ID, $field['id'], true );
                ?>

                <td>
                    <select name="<?php echo esc_attr( $field['name'] ); ?>" id="<?php echo esc_attr( $field['id'] ); ?>">
                        <?php foreach ( $field['options'] as $option ) { ?>
                            <option value="<?php echo esc_attr( $option['value'] ); ?>" <?php echo ( $userMeta === $option['value'] ? 'selected' : '' ); ?> ><?php echo esc_html( $option['text'] ); ?></option>
                        <?php } ?>
                    </select><br />
                    <span class="description">
                        <?php
                            /* translators: %1$s Label */
                            echo sprintf( esc_html__( 'Please enter your %1$s.', 'woocommerce-wholesale-lead-capture' ), esc_html( $field['label'] ) );
                        ?>
                    </span>
                </td>

            <?php
            } elseif ( 'radio' === $field['type'] ) {

                $userMeta = get_user_meta( $user->ID, $field['id'], true );
                ?>

                <td>
                <?php foreach ( $field['options'] as $option ) { ?>
                    <div style="margin-bottom: 6px;"><input style="display: inline-block; margin-right: 6px;" type="radio" name="<?php echo esc_attr( $field['name'] ); ?>" value="<?php echo esc_attr( $option['value'] ); ?>" <?php echo ( $userMeta === $option['value'] ? 'checked' : '' ); ?> /><span style="display: inline-block;"><?php echo esc_html( $option['text'] ); ?></span></div>
                <?php } ?>

                    <br />
                    <span class="description">
                        <?php
                            /* translators: %1$s Label */
                            echo sprintf( esc_html__( 'Please enter your %1$s.', 'woocommerce-wholesale-lead-capture' ), esc_html( $field['label'] ) );
                        ?>
                    </span>
                </td>

            <?php
            } elseif ( 'checkbox' === $field['type'] ) {

                $userMeta = get_user_meta( $user->ID, $field['id'], true );
                if ( ! is_array( $userMeta ) ) {
                    $userMeta = array();
                }
                ?>

                <td>
                    <?php foreach ( $field['options'] as $option ) { ?>
                        <div style="margin-bottom: 6px;"><input style="display: inline-block; margin-right: 6px;" type="checkbox" name="<?php echo esc_attr( $field['name'] ); ?>[]" value="<?php echo esc_attr( $option['value'] ); ?>" <?php echo in_array( $option['value'], $userMeta, true ) ? 'checked' : ''; ?> /><span style="display: inline-block;"><?php echo esc_html( $option['text'] ); ?></span></div>
                    <?php } ?>

                    <br />
                    <span class="description">
                        <?php
                            /* translators: %1$s Label */
                            echo sprintf( esc_html__( 'Please enter your %1$s.', 'woocommerce-wholesale-lead-capture' ), esc_html( $field['label'] ) );
                        ?>
                    </span>
                </td>

            <?php } elseif ( 'phone' === $field['type'] ) { ?>

                <td>
                    <input type="<?php echo esc_attr( $field['type'] ); ?>" name="<?php echo esc_attr( $field['name'] ); ?>" id="<?php echo esc_attr( $field['id'] ); ?>" value="<?php echo esc_attr( get_user_meta( $user->ID, $field['name'], true ) ); ?>" class="regular-text phone" /><br />
                    <span class="description">
                        <?php
                            /* translators: %1$s Label */
                            echo sprintf( esc_html__( 'Please enter your %1$s.', 'woocommerce-wholesale-lead-capture' ), esc_html( $field['label'] ) );
                        ?>
                    </span>
                </td>

            <?php } elseif ( 'hidden' === $field['type'] ) { ?>

                <td>
                    <span><?php echo esc_attr( get_user_meta( $user->ID, $field['name'], true ) ); ?></span>
                </td>

            <?php
            } elseif ( 'file' === $field['type'] ) {

                $file_url  = esc_url( get_user_meta( $user->ID, $field['id'], true ) );
                $file_tmp  = explode( '/', $file_url );
                $file_name = end( $file_tmp );
                ?>
                <td class="wwlc-admin-user-field">
                    <div class="wwlc-admin-user-field-file">
                        <input type="hidden" name="<?php echo esc_attr( $field['name'] ); ?>" id="<?php echo esc_attr( $field['id'] ); ?>" value="<?php echo esc_html( $file_name ); ?>" />
                        <input name="file" type="file" data-field="<?php echo esc_attr( $field['id'] ); ?>">
                        <span class="wwlc-loader"></span>
                    </div>
                    <?php if ( ! empty( $file_name ) ) : ?>
                    <a href="<?php echo esc_url( $file_url ); ?>" target="_blank"><?php echo esc_html( $file_name ); ?></a> <button type="button" class="button button-secondary wwlc-remove-file" data-field="<?php echo esc_attr( $field['name'] ); ?>" data-userid="<?php echo esc_attr( $user->ID ); ?>"><?php esc_html_e( 'Remove', 'woocommerce-wholesale-lead-capture' ); ?></button>
                    <?php endif; ?>
                    </td>
            <?php } ?>

        </tr>

    <?php } ?>

</table>
