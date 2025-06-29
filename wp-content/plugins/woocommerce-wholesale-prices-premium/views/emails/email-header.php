<?php
/**
 * WWPP Email header template.
 *
 * This template can be overridden by copying it to yourtheme/wwpp/emails/email-header.php.
 *
 * @since 2.0.0.2
 *
 * @var string $title        Email title.
 * @var array  $header_image Header image arguments.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light dark">
    <title><?php echo esc_html( $title ); ?></title>
</head>
<body marginwidth="0" topmargin="0" marginheight="0" offset="0" bgcolor="#e9eaec">
    <table border="0" cellpadding="0" cellspacing="0" width="100%" height="100%" class="body" role="presentation"
        bgcolor="#ffffff" style="color: #323232;">
        <tr>
            <td><!-- Deliberately empty to support consistent sizing and layout across multiple email clients. --></td>
            <td align="center" valign="middle" class="body-inner" width="550">
                <div class="wrapper" width="100%" dir="">
                    <table border="0" cellpadding="0" cellspacing="0" width="100%" class="container"
                        role="presentation">
                        <tr class="header-wrapper">
                            <td align="center" valign="middle" class="header">
                                <div class="header-image">
                                    <img src="<?php echo esc_url( $header_image ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?> top background" style="width: 100%;" />
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td class="wrapper-inner" bgcolor="#ffffff">
