<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

// Add the form manually.
add_action( 'secupress.settings.before_section_secupress_advanced_settings', array( $this, 'print_open_form_tag' ) );
add_action( 'secupress.settings.after_section_secupress_advanced_settings', array( $this, 'print_close_form_tag' ) );

$this->set_current_section( 'secupress_advanced_settings' );
$this->add_section( __( 'Advanced Settings', 'secupress' ) );

$this->add_field( array(
	'title'             => __( 'Admin Bar Menu', 'secupress' ),
	'label_for'         => $this->get_field_name( 'admin-bar' ),
	'type'              => 'checkbox',
	'value'             => secupress_get_module_option( 'advanced-settings_admin-bar', true ),
	'label'             => sprintf( __( 'Yes, show the %s admin bar menu', 'secupress' ), SECUPRESS_PLUGIN_NAME ),
) );

$this->add_field( array(
	'title'             => __( 'Grade System', 'secupress' ),
	'label_for'         => $this->get_field_name( 'grade-system' ),
	'type'              => 'checkbox',
	'value'             => secupress_get_module_option( 'advanced-settings_grade-system', true ),
	'label'             => sprintf( __( 'Yes, enable and show the Grade system in %s', 'secupress' ), SECUPRESS_PLUGIN_NAME ),
) );

$this->add_field( array(
	'title'             => __( 'Hide Contextual Help & Tips', 'secupress' ),
	'label_for'         => $this->get_field_name( 'expert-mode' ),
	'type'              => 'checkbox',
	'value'             => secupress_get_module_option( 'advanced-settings_expert-mode', false ),
	'label'             => sprintf( __( 'Yes, hide all contextual help in %s', 'secupress' ), SECUPRESS_PLUGIN_NAME ),
) );

$expert_modules = secupress_get_expert_modules_on();
$this->add_field( array(
	'title'             => __( 'Expert Mode', 'secupress' ),
	'label_for'         => $this->get_field_name( 'expert-mode-main' ),
	'disabled'          => ! empty( $expert_modules ),
	'type'              => 'checkbox',
	'value'             => secupress_get_module_option( 'advanced-settings_expert-mode-main', false ) || secupress_get_expert_modules_on(),
	'label'             => __( 'Yes, show me <strong>extra features</strong> for experts', 'secupress' ),
	'helpers'           => array(
		array(
			'type'        => 'help',
			'description' => sprintf( __( 'Search for this blue %s logo', 'secupress' ), SECUPRESS_PLUGIN_NAME ),
		),
		array(
			'type'        => 'force-warning',
			'description' => ! empty( $expert_modules ) ? sprintf( __( 'You still have some Expert Modules activated, please deactivate them first:', 'secupress' ) . ' <strong>' . wp_sprintf_l( '%l', $expert_modules ) . '</strong>' ) : '',
		),
	),
) );
