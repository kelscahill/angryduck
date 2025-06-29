<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
    <tr>
        <td align="center" valign="top" class="greetings-content" style="color: #384860; padding-left: 50px; padding-right: 50px;">
            <h1 style="margin: 0; font-size: 24px; line-height: 36px; font-weight: 700; padding-top: 30px; padding-bottom: 20px;"><?php echo esc_html( $args['achievement_title'] ); ?></h1>
            <div style="font-size: 16px; padding-bottom: 50px;"><?php echo wp_kses_post( $args['achievement_message'] ); ?></div>
        </td>
    </tr>
    <tr>
        <td align="center" valign="top" class="mesage-content">
            <div class="message" style="padding-top: 40px; padding-bottom: 40px; padding-left: 40px; padding-right: 40px; background-color: #ECF8F4; border-radius: 10px;">
                <h3 style="font-size: 20px; color: #384860; font-weight: 600; padding-bottom: 20px;"><em><?php echo esc_html( $args['celebrate_level_title'] ); ?></em></h3>
                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                    <tr>
                        <td width="10%" align="left" valign="top">
                            <img src="<?php echo esc_url( $light_icon ); ?>" alt="Light Icon" style="padding-right: 10px;" />
                        </td>
                        <td width="90%" align="left" valign="middle">
                            <h3 style="font-size: 16px; font-weight: 700;"><?php echo esc_html( $args['celebrate_row1_title'] ); ?></h3>
                        </td>
                    </tr>
                    <tr>
                        <td width="95%" align="left" valign="top" colspan="2">
                            <div style="font-size: 16px; padding-left: 30px; padding-top: 15px; padding-bottom: 30px;">
                                <?php echo wp_kses_post( $args['celebrate_row1_description'] ); ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td width="10%" align="left" valign="top">
                            <img src="<?php echo esc_url( $light_icon ); ?>" alt="Light Icon" style="padding-right: 10px;" />
                        </td>
                        <td width="90%" align="left" valign="middle">
                            <h3 style="font-size: 16px; font-weight: 700;"><?php echo esc_html( $args['celebrate_row2_title'] ); ?></h3>
                        </td>
                    </tr>
                    <tr>
                        <td width="95%" align="left" valign="top" colspan="2">
                            <div style="font-size: 16px; padding-left: 30px; padding-top: 15px; padding-bottom: 30px;">
                                <ul style="list-style-type: disc;padding-left: 20px;">
                                    <?php foreach ( $args['celebrate_row2_list'] as $list_item ) : ?>
                                        <li><?php echo wp_kses_post( $list_item ); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td width="10%" align="left" valign="top">
                            <img src="<?php echo esc_url( $light_icon ); ?>" alt="Light Icon" style="padding-right: 10px;" />
                        </td>
                        <td width="90%" align="left" valign="middle">
                            <h3 style="font-size: 16px; font-weight: 700;"><?php echo esc_html( $args['celebrate_row3_title'] ); ?></h3>
                        </td>
                    </tr>
                    <tr>
                        <td width="95%" align="left" valign="top" colspan="2">
                            <div style="font-size: 16px; padding-left: 30px; padding-top: 15px; padding-bottom: 30px;">
                                <?php echo wp_kses_post( $args['celebrate_row3_description'] ); ?>
                            </div>
                        </td>
                    </tr>
                </table>
                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                    <tr>
                        <td align="center" valign="middle" style="font-size: 16px;">
                            <em><?php echo wp_kses_post( $args['celebrate_bottom_message'] ); ?></em>
                        </td>
                    </tr>
                </table>
            </div>
            <div style="padding-top: 30px; padding-bottom: 0px; padding-left: 30px; padding-right: 30px;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                    <tr>
                        <td width="30%" align="left" valign="middle">
                            <img src="<?php echo esc_url( $support_image ); ?>" alt="Support Image" style="padding-right: 10px;" />
                        </td>
                        <td width="70%" align="left" valign="middle">
                            <strong style="font-size: 16px; line-height: 24px;"><?php echo wp_kses_post( $args['celebrate_support_message'] ); ?></strong>
                        </td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>
