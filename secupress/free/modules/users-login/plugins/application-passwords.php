<?php
/**
 * Module Name: Application Passwords
 * Description: Alert users when a new application password is added to their account.
 * Main Module: users_login
 * Author: SecuPress
 * Version: 2.7
 */

defined( 'SECUPRESS_VERSION' ) or die( 'Something went wrong.' );

add_action( 'wp_create_application_password', 'secupress_alert_on_application_password', 10, 2 );
/**
 * Send an email notification when a new application password is created.
 *
 * @author Julio Potier
 * @since 2.7
 *
 * @param $user_id (int)
 * @param $new_item (array)
 */
function secupress_alert_on_application_password( $user_id, $new_item ) {
	$user = get_userdata( $user_id );
	if ( ! secupress_is_user( $user ) || ! $user->user_email ) {
		return;
	}

	$app_name        = ! empty( $new_item['name'] ) ? sanitize_text_field( $new_item['name'] ) : __( '(no name)', 'secupress' );
	$switched_locale = switch_to_user_locale( $user->ID );
	$subject         = sprintf( __( '[%s] New application password added to your account', 'secupress' ), '###SITENAME###' );
	$message         = sprintf(
		__( 'Hello %1$s,

A new application password has been added to your account on ###SITENAME###.
This password allows access to your account via the REST API.

If you did not expect this, please review your account security and revoke the password immediately.
Application password name: %2$s
Site: ###SITEURL###

You can manage your application passwords in your account settings.

Regards,
All at ###SITENAME###
###SITEURL###', 'secupress' ),
		$user->display_name,
		$app_name
	);

	secupress_send_mail( $user->user_email, $subject, $message );

	if ( $switched_locale ) {
		restore_previous_locale();
	}

	if ( ! user_can( $user, 'administrator' ) && ! is_super_admin( $user_id ) ) {
		return;
	}

	$admin_email = get_option( 'admin_email' );
	if ( ! $admin_email || 0 === strcasecmp( $admin_email, $user->user_email ) ) {
		return;
	}

	$admin_user      = get_user_by( 'email', $admin_email );
	$switched_locale = $admin_user ? switch_to_user_locale( $admin_user->ID ) : false;
	$subject         = sprintf( __( '[%s] New application password added to an administrator account', 'secupress' ), '###SITENAME###' );
	$message         = sprintf(
		__( 'Hello,

A new application password has been added to the administrator account %1$s on ###SITENAME###.
This password allows access to this account via the REST API.

If you did not expect this, please review this account security and revoke the password immediately.
Application password name: %2$s
User: %3$s
Site: ###SITEURL###

You can manage application passwords in the user profile.

Regards,
All at ###SITENAME###
###SITEURL###', 'secupress' ),
		$user->display_name,
		$app_name,
		$user->user_login
	);

	secupress_send_mail( $admin_email, $subject, $message );

	if ( $switched_locale ) {
		restore_previous_locale();
	}
}
