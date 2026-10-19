<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );


$this->set_current_section( 'password_policy' );
$this->add_section( __( 'Password Policy', 'secupress' ) );

$this->add_field( array(
	'title'             => __( 'Force Strong Passwords', 'secupress' ),
	'description'       => __( 'Strong passwords are required for updating passwords, creating new user accounts, and logging in.', 'secupress' ),
	'label_for'         => $this->get_field_name( 'strong_passwords' ),
	'plugin_activation' => true,
	'type'              => 'checkbox',
	'value'             => (int) secupress_is_submodule_active( 'users-login', 'strong-passwords' ),
	'label'             => __( 'Yes, enforce the use of strong passwords', 'secupress' ),
) );

$this->add_field( array(
	'title'        => __( 'Password Lifespan', 'secupress' ),
	'description'  => sprintf( __( 'Recommended: %1$d days,<br/>%2$d = never expires', 'secupress' ), 30, 0 ),
	'label_for'    => $this->get_field_name( 'password_expiration' ),
	'depends'      => $this->get_field_name( 'strong_passwords' ),
	'type'         => 'number',
	'label_after'  => __( 'days', 'secupress' ),
	'default'      => '0',
	'attributes'   => array(
		'min' => 0,
		'max' => 365,
	),
) );

$this->add_field( array(
	'title'        => '<span class="dashicons dashicons-groups"></span> ' . __( 'Affected Roles', 'secupress' ),
	'description'  => __( 'Which roles does this module affect?', 'secupress' ),
	'depends'      => $this->get_field_name( 'strong_passwords' ),
	'row_class'    => 'affected-role-row',
	'name'         => $this->get_field_name( 'affected_role' ),
	'type'         => 'roles',
	'label_screen' => __( 'Affected Roles', 'secupress' ),
	'helpers'      => array(
		array(
			'type'        => 'description',
			'description' => __( 'Future roles will be automatically selected', 'secupress' ),
		),
		array(
			'type'        => 'warning',
			'class'       => 'hide-if-js',
			'description' => __( 'Select at least 1 role', 'secupress' ),
		),
	),
) );

$count_users  = count_users( 'memory' )['total_users'];
$arg_disabled = ! secupress_is_pro() ? [ 'disabled' => true ] : [];
if ( $count_users > 1 ) {

	$this->add_field( array(
		'title'             => __( 'Force Reset Passwords', 'secupress' ),
		'description'       => __( 'Reset everyone’s password now (but yours!).', 'secupress' ),
		'label_for'         => $this->get_field_name( 'send-emails' ),
		'type'              => 'checkbox',
		'value'             => 1,
		'disabled'          => ! secupress_is_pro(),
		'label'             => sprintf( __( 'Additionally, send a reset password email to %s users', 'secupress' ), secupress_tag_me( number_format_i18n( $count_users ), 'b' ) ),
		'helpers'           => array(
			array(
				'type'        => 'force-description',
				'description' => get_submit_button( __( 'Reset all passwords', 'secupress' ), 'secupress-button-small button button-small secupress-button', 'reset-all-passwords', true, $arg_disabled ),
			),
		),
	) );

}

$this->add_field( array(
	'title'             => __( 'Force Logout Everyone', 'secupress' ),
	'description'       => __( 'Disconnect everyone (but you!) in one click.', 'secupress' ),
	'label_for'         => $this->get_field_name( 'force-logout' ),
	'type'              => 'html',
	'value'             => '',
	'helpers'           => array(
		array(
			'type'        => 'force-description',
			'description' => get_submit_button( __( 'Disconnect everyone', 'secupress' ), 'secupress-button-small button button-small secupress-button', 'disconnect-everyone', true, $arg_disabled ),
		),
	),
) );

$field_name       = $this->get_field_name( 'application-passwords' );
$is_plugin_active = [];
$helpers          = [
	[
		'depends'     => $field_name . '_application-passwords',
		'type'        => 'description',
		'description' => __( 'If the user has the Administrator capability, the site admin will also receive an extra email.', 'secupress' ),
	],
	[
		'depends'     => $field_name . '_disable-application-passwords',
		'type'        => 'description',
		'description' => __( 'Any authentication attempt using an Application Password will be refused and logged as a login attack. The Application Passwords section is also removed from user profiles, and new passwords cannot be created.', 'secupress' ),
	],
];

if ( secupress_is_submodule_active( 'users-login', 'application-passwords' ) ) {
	$is_plugin_active[] = 'application-passwords';
}
if ( secupress_is_submodule_active( 'users-login', 'disable-application-passwords' ) ) {
	$is_plugin_active[] = 'disable-application-passwords';
} else {
	$app_passwords_users = secupress_get_application_passwords_user_count();
	if ( $app_passwords_users ) {
		$helpers[] = [
			'depends'     => $field_name . '_disable-application-passwords',
			'type'        => 'warning',
			'description' => sprintf(
				_n(
					'%s account currently has an Application Password. It will become unusable.',
					'%s accounts currently have Application Passwords. They will become unusable.',
					$app_passwords_users,
					'secupress'
				),
				number_format_i18n( $app_passwords_users )
			),
		];
	}
}

$this->add_field( array(
	'title'             => __( 'Application Passwords', 'secupress' ),
	'name'              => $field_name,
	'plugin_activation' => true,
	'type'              => 'radioboxes',
	'value'             => $is_plugin_active,
	'default'           => array(),
	'label_screen'      => __( 'Application Passwords', 'secupress' ),
	'options'           => array(
		'application-passwords'         => __( 'Yes, <strong>alert</strong> users when an Application Password is added to their account', 'secupress' ),
		'disable-application-passwords' => __( 'Yes, <strong>disable</strong> Application Passwords on this site', 'secupress' ),
	),
	'helpers'           => $helpers,
) );