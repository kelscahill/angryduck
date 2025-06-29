<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="padding: 20px;">
    <tr>
        <td valign="top" class="greetings-content">
            <h1 style="margin: 0; font-size: 20px;"><?php esc_html_e( 'Hey There,', 'woocommerce-wholesale-prices-premium' ); ?> <img src="<?php echo esc_url( $hand_icon ); ?>" alt="Hand Icon" /></h1>
            <p style="font-size: 16px;"><?php echo wp_kses_post( $args['greeting_message'] ); ?></p>
        </td>
    </tr>
    <?php
        $order_arrow        = intval( $args['wholesale_order_percentage'] ) >= 0 ? $arrow_up_icon : $arrow_down_icon;
        $order_text_color   = intval( $args['wholesale_order_percentage'] ) >= 0 ? '#46BF93' : '#F12121';
        $revenue_arrow      = intval( $args['wholesale_revenue_percentage'] ) >= 0 ? $arrow_up_icon : $arrow_down_icon;
        $revenue_text_color = intval( $args['wholesale_revenue_percentage'] ) >= 0 ? '#46BF93' : '#F12121';
        $leads_arrow        = intval( $args['wholesale_leads_percentage'] ) >= 0 ? $arrow_up_icon : $arrow_down_icon;
        $leads_text_color   = intval( $args['wholesale_leads_percentage'] ) >= 0 ? '#46BF93' : '#F12121';
    ?>
    <tr>
        <td valign="top" style="padding: 20px 0;">
            <table border="0" cellpadding="0" cellspacing="0" width="100%"
                role="presentation">
                <tr>
                    <td align="center" class="group-stats">
                        <img src="<?php echo esc_url( $order_icon ); ?>" alt="Orders Icon" />
                        <h3 style="font-size: 12px; font-weight: 700; margin: 10px 0;"><?php esc_html_e( 'Wholesale Orders', 'woocommerce-wholesale-prices-premium' ); ?></h3>
                        <h4 style="font-size: 20px; font-weight: 700; margin: 10px 0;"><?php echo esc_html( $args['wholesale_order_amount'] ); ?></h4>
                        <div style="display: flex; align-items: center; justify-content: center; color: <?php echo esc_attr( $order_text_color ); ?>; margin: 10px 0;">
                            <img src="<?php echo esc_url( $order_arrow ); ?>" alt="Arrow Icon" style="margin-right: 3px;" /> <span><?php echo esc_html( $args['wholesale_order_percentage'] ); ?>%</span>
                        </div>
                        <div style="font-size: 12px; color: #c1c0c0;"><?php esc_html_e( 'vs Previous month', 'woocommerce-wholesale-prices-premium' ); ?></div>
                    </td>
                    <td align="center" class="group-stats">
                        <img src="<?php echo esc_url( $revenue_icon ); ?>" alt="Revenue Icon" />
                        <h3 style="font-size: 12px; font-weight: 700; margin: 10px 0;"><?php esc_html_e( 'Wholesale Revenue', 'woocommerce-wholesale-prices-premium' ); ?></h3>
                        <h4 style="font-size: 20px; font-weight: 700; margin: 10px 0;"><?php echo wp_kses_post( $args['wholesale_revenue_amount'] ); ?></h4>
                        <div style="display: flex; align-items: center; justify-content: center; color: <?php echo esc_attr( $revenue_text_color ); ?>; margin: 10px 0;">
                            <img src="<?php echo esc_url( $revenue_arrow ); ?>" alt="Arrow Icon" style="margin-right: 3px;" /> <span><?php echo esc_html( $args['wholesale_revenue_percentage'] ); ?>%</span>
                        </div>
                        <div style="font-size: 12px; color: #c1c0c0;"><?php esc_html_e( 'vs Previous month', 'woocommerce-wholesale-prices-premium' ); ?></div>
                    </td>
                    <td align="center" class="group-stats">
                        <img src="<?php echo esc_url( $leads_icon ); ?>" alt="Leads Icon" />
                        <h3 style="font-size: 12px; font-weight: 700; margin: 10px 0;"><?php esc_html_e( 'Wholesale Leads', 'woocommerce-wholesale-prices-premium' ); ?></h3>
                        <h4 style="font-size: 20px; font-weight: 700; margin: 10px 0;"><?php echo esc_html( $args['wholesale_leads_amount'] ); ?></h4>
                        <div style="display: flex; align-items: center; justify-content: center; color: <?php echo esc_attr( $leads_text_color ); ?>; margin: 10px 0;">
                            <img src="<?php echo esc_url( $leads_arrow ); ?>" alt="Arrow Icon" style="margin-right: 3px;" /> <span><?php echo esc_html( $args['wholesale_leads_percentage'] ); ?>%</span>
                        </div>
                        <div style="font-size: 12px; color: #c1c0c0;"><?php esc_html_e( 'vs Previous month', 'woocommerce-wholesale-prices-premium' ); ?></div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td valign="top" style="padding: 20px 0;" align="center">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td width="5%" align="left" valign="middle">
                        <img src="<?php echo esc_url( $star_icon ); ?>" alt="Star Icon" style="padding-right: 10px;"/>
                    </td>
                    <td width="95%" align="left" valign="middle">
                        <h2 style="font-size: 20px;"><span><?php esc_html_e( 'Top Wholesale Customers', 'woocommerce-wholesale-prices-premium' ); ?></span></h2>
                    </td>
                </tr>
            </table>
            <table border="0" cellpadding="0" cellspacing="0" width="100%"
                role="presentation">
                <thead>
                    <tr>
                        <th align="left" style="font-size: 12px; font-weight: 700; padding: 8px; color: #bfbdbd;">
                            <?php esc_html_e( 'Usernames', 'woocommerce-wholesale-prices-premium' ); ?>
                        </th>
                        <th align="right" style="font-size: 12px; font-weight: 700; padding: 8px; color: #bfbdbd;">
                            <?php esc_html_e( 'Total Wholesale Orders', 'woocommerce-wholesale-prices-premium' ); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Loop through the top wholesale customers.
                    if ( ! empty( $args['user_reports'] ) ) {
                        foreach ( $args['user_reports'] as $user_report ) {
                            ?>
                            <tr>
                                <td align="left" style="font-size: 12px; border-bottom: 1px solid #ECE9FF; padding: 8px;">
                                    <?php echo esc_html( $user_report['name'] ); ?>
                                </td>
                                <td align="right" style="font-size: 12px; border-bottom: 1px solid #ECE9FF; padding: 8px; font-weight: 700; color: #46BF93;">
                                    <?php echo wp_kses_post( $user_report['spent'] ); ?>
                                </td>
                            </tr>
                            <?php
                        }
                    }
                    ?>
                </tbody>
            </table>
            <p><a target="_blank" href="<?php echo esc_url( $args['report_link'] ); ?>" style="color: #46BF93; text-aling: center;"><?php esc_html_e( 'View Full Report', 'woocommerce-wholesale-prices-premium' ); ?></a></p>
        </td>
    </tr>
    <tr>
        <td valign="top" class="mesage-content">
            <div class="message" style="padding-top: 20px; padding-bottom: 20px; padding-left: 20px; padding-right: 20px; background-color: #ECF8F4; border-radius: 16px;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                    <tr>
                        <td width="10%" align="left" valign="middle">
                            <img src="<?php echo esc_url( $light_icon ); ?>" alt="Light Icon" style="padding-right: 10px;" />
                        </td>
                        <td width="90%" align="left" valign="middle">
                            <h2 style="font-size: 20px;"><span><?php esc_html_e( 'Pro Tip from Us', 'woocommerce-wholesale-prices-premium' ); ?></span></h2>
                        </td>
                    </tr>
                </table>

                <p style="color: #384860; font-size: 16px;"><?php echo wp_kses_post( $args['wholesale_pro_tips'] ); ?></p>
            </div>
        </td>
    </tr>
</table>
