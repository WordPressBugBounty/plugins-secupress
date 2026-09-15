<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

global $is_apache, $is_nginx, $is_iis7;

add_action( 'secupress.settings.before_section_backups-storage', array( $this, 'print_open_form_tag' ) );
add_action( 'secupress.settings.after_section_backups-storage', array( $this, 'print_close_form_tag' ) );

$this->set_current_section( 'backups-storage' );
$this->add_section( __( 'Backup Storage', 'secupress' ) );


$warning_cant_write = null;
$outside_web        = function_exists( 'secupress_backups_path_is_outside_web_root' ) && secupress_backups_path_is_outside_web_root();
$path_code          = '<code>' . secupress_get_parent_backups_path( true ) . '</code>';

if ( $outside_web ) {
	$warning_local = sprintf(
		/** Translators: %s is a path to a folder. */
		__( 'Backups are stored in %s, outside the web-accessible folder.', 'secupress' ),
		$path_code
	);
} else {
	$warning_local = sprintf(
		/** Translators: %s is a path to a folder. */
		__( 'Backups will be stored in %s. Please, delete them as soon as possible.', 'secupress' ),
		$path_code
	);

	$fallback_reason = function_exists( 'secupress_get_parent_backups_fallback_reason' ) ? secupress_get_parent_backups_fallback_reason() : '';
	if ( 'open_basedir' === $fallback_reason ) {
		$warning_local .= ' ' . __( 'The PHP open_basedir restriction prevents storing backups outside the web root.', 'secupress' );
	} elseif ( 'not_writable' === $fallback_reason ) {
		$warning_local .= ' ' . __( 'The parent folder is not writable.', 'secupress' );
	}
}

if ( secupress_is_pro() ) {

	if ( ! secupress_pre_backup() ) {
		if ( $outside_web ) {

			$warning_cant_write = sprintf(
				/** Translators: %s is the path to a folder. */
				__( 'The backups folder %s could not be created or is not writable.', 'secupress' ),
				$path_code
			);

		} elseif ( $is_apache ) {

			$warning_cant_write  = sprintf(
				/** Translators: %s is the path to a folder. */
				__( 'It appears that some folders and a file could not be created. Please ensure that a folder named %s is created, containing the following:', 'secupress' ),
				$path_code
			);
			$warning_cant_write .= '</p><ul>';
			$warning_cant_write .= '<li>';
			$warning_cant_write .= sprintf(
				/** Translators: %s is a folder name. */
				__( 'Two folders with the following names: %1$s and %2$s.', 'secupress' ),
				'<code>' . basename( secupress_get_local_backups_path() ) . '</code>',
				'<code>' . basename( secupress_get_temporary_backups_path() ) . '</code>'
			);
			$warning_cant_write .= '</li><li>';
			$warning_cant_write .= sprintf(
				/** Translators: %s is a file name. */
				__( 'A %s file containing the following rules:', 'secupress' ),
				'<code>.htaccess</code>'
			);
			$warning_cant_write .= '<pre>' . secupress_backup_get_protection_content() . '</pre>';
			$warning_cant_write .= '</li>';
			$warning_cant_write .= '</ul>';

		} elseif ( $is_iis7 ) {

			$warning_cant_write  = sprintf(
				/** Translators: 1 is a file name, 2 is the path to a folder, 3 and 4 are folder names. */
				__( 'It appears that some folders could not be created and/or the %1$s file is not writable. Please make sure that a folder %2$s is created, containing these two folders: %3$s and %4$s. Then add the following rules in the %1$s file:', 'secupress' ),
				'<code>web.config</code>',
				$path_code,
				'<code>' . basename( secupress_get_local_backups_path() ) . '</code>',
				'<code>' . basename( secupress_get_temporary_backups_path() ) . '</code>'
			);
			$warning_cant_write .= '</p><pre>' . secupress_backup_get_protection_content() . '</pre>';

		} elseif ( $is_nginx ) {

			$warning_cant_write  = sprintf(
				/** Translators: 1 is the path to a folder, 2 and 3 are folder names, 4 is a file name. */
				__( 'please make sure that a folder named %1$s is created, containing these two folders: %2$s and %3$s. Then add the following rules in the %4$s file:', 'secupress' ),
				$path_code,
				'<code>' . basename( secupress_get_local_backups_path() ) . '</code>',
				'<code>' . basename( secupress_get_temporary_backups_path() ) . '</code>',
				'<code>nginx.conf</code>'
			);
			$warning_cant_write .= '</p><pre>' . secupress_backup_get_protection_content() . '</pre>';

		}
	}
}

if ( $outside_web ) {
	$warnings = '<div class="description">' . $warning_local . '</div>';
} else {
	$warnings = '<div class="description warning">' . '<strong>' . __( 'Warning: ', 'secupress' ) . '</strong> ' . $warning_local . '</div>';
}
if ( $warning_cant_write ) {
	$warnings .= '<div class="description warning">' . '<strong>' . __( 'Warning: ', 'secupress' ) . '</strong> ' . $warning_cant_write . '</div>';
}

$this->add_field( array(
	'type'  => 'html',
	'value' => $warnings,
) );

if ( function_exists( 'secupress_backup_zip_encryption_is_available' ) ) {
	$encryption_ok   = secupress_backup_zip_encryption_is_available();
	$has_hash        = (bool) secupress_get_module_option( $this->get_field_name( 'password_hash' ), '', 'backups' );
	$wpconfig_ok     = (bool) secupress_is_wpconfig_writable();
	$helpers         = [];
	$disabled        = ! $encryption_ok || ! $wpconfig_ok || ! secupress_is_pro();

	if ( ! $encryption_ok ) {
		$helpers[] = [
			'type'        => 'warning',
			'description' => sprintf( __( 'Protected backups require PHP %1$s / libzip %2$s (AES-256) to be used.', 'secupress' ), '7.2+', '1.2+' ),
		];
	} elseif ( ! $wpconfig_ok ) {
		$helpers[] = [
			'type'        => 'warning',
			'description' => sprintf( __( 'The %s file is not writable. The backup password cannot be saved.', 'secupress' ), secupress_code_me( secupress_get_wpconfig_filename() ) ),
		];
	}

	$helpers[] = [
		'type'        => 'help',
		'description' => __( 'Keep this password in a safe place, it cannot be recovered. Previous ZIP files keep their own password generation.', 'secupress' ),
	];

	if ( $has_hash ) {
		$locked   = secupress_backup_password_is_locked();
		$disabled = $disabled || $locked;

		if ( $locked ) {
			$helpers[] = [
				'type'        => 'warning',
				'description' => secupress_get_backup_password_lock_message(),
			];
		}

		$wrong_password = false;
		foreach ( secupress_get_settings_errors( 'general' ) as $error ) {
			if ( ! empty( $error['code'] ) && in_array( $error['code'], [ 'backup_password_wrong', 'backup_password_locked' ], true ) ) {
				$wrong_password = true;
				break;
			}
		}

		$cooldown = secupress_get_backup_password_reset_cooldown_message();
		if ( $cooldown ) {
			$helpers[] = [
				'type'        => 'help',
				'description' => $cooldown,
			];
		} elseif ( $wrong_password ) {
			$reset_url = wp_nonce_url( admin_url( 'admin-post.php?action=secupress_send_backup_password_reset' ), 'secupress_send_backup_password_reset' );
			$helpers[] = [
				'type'        => 'help',
				'description' => sprintf( __( 'Forgot your password? <a href="%s">Send a reset link</a> to the administration email address.', 'secupress' ), esc_url( $reset_url ) ),
			];
		}

		if ( ! $locked ) {
			$helpers[] = [
				'type'        => 'help',
				'description' => __( 'To disable the protection, enter the current password and leave the new password empty.', 'secupress' ),
			];
		}

		$this->add_field( array(
			'title'        => __( 'Backup ZIP password', 'secupress' ),
			'label'        => __( 'Current password', 'secupress' ),
			'label_for'    => $this->get_field_name( 'password_old' ),
			'name'         => $this->get_field_name( 'password_old' ),
			'type'         => 'password',
			'value'        => '',
			'disabled'     => $disabled,
			'attributes'   => [
				'autocomplete' => 'current-password',
			],
			'fieldset'     => 'start',
		) );

		$this->add_field( array(
			'label'        => __( 'New password', 'secupress' ),
			'label_for'    => $this->get_field_name( 'password' ),
			'name'         => $this->get_field_name( 'password' ),
			'type'         => 'password',
			'value'        => '',
			'disabled'     => $disabled,
			'attributes'   => [
				'autocomplete' => 'new-password',
			],
			'helpers'      => $helpers,
			'fieldset'     => 'end',
		) );
	} else {
		$helpers[] = [
			'type'        => 'help',
			'description' => __( 'Leave empty to keep backups without a password.', 'secupress' ),
		];

		$this->add_field( array(
			'title'        => __( 'Backup ZIP password', 'secupress' ),
			'label_for'    => $this->get_field_name( 'password' ),
			'name'         => $this->get_field_name( 'password' ),
			'type'         => 'password',
			'value'        => '',
			'disabled'     => $disabled,
			'attributes'   => [
				'autocomplete' => 'new-password',
			],
			'helpers'      => $helpers,
		) );
	}
}
