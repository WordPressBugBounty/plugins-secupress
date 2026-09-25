<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

$this->set_current_section( 'learning_mode' );
$this->set_section_description( __( 'Inspect incoming requests and block known bad User-Agents, URL contents and referers.', 'secupress' ) );
$this->add_section( __( 'Web Application Firewall', 'secupress' ), array( 'with_save_button' => true ) );

$bbx_values = array(
	'user-agents-header' => __( 'Block Bad User Agents', 'secupress' ),
	'bad-url-contents'   => __( 'Block Bad Content', 'secupress' ),
	'bad-referer'        => __( 'Block Bad Referers', 'secupress' ),
);
$bbx_active = array();
foreach ( $bbx_values as $_plugin => $label ) {
	if ( secupress_is_submodule_active( 'firewall', $_plugin ) ) {
		$bbx_active[] = $_plugin;
	}
}
$bbx_field_name = $this->get_field_name( 'bbx' );
$ng_field_name  = $this->get_field_name( '8g' );

$this->add_field( array(
	'title'             => __( 'Request filters', 'secupress' ),
	'description'       => __( 'Block suspicious User-Agents, URL Contents and Bad Referers before they reach your site.', 'secupress' ),
	'name'              => $bbx_field_name,
	'plugin_activation' => true,
	'type'              => 'checkboxes',
	'options'           => $bbx_values,
	'value'             => $bbx_active,
	'default'           => array(),
) );

$this->add_field( array(
	'title'        => __( 'Referers List', 'secupress' ),
	'name'         => 'bbq-headers_bad-referer-list',
	'label_for'    => 'bbq-headers_bad-referer-list',
	'type'         => 'textarea',
	'depends'      => $bbx_field_name . '_bad-referer',
	'attributes'   => array( 'rows' => 2 ),
	'helpers'      => array(
		array(
			'type'        => 'help',
			'description' => __( 'You can also add yours here. One URL per line.', 'secupress' ),
		),
	),
) );

$this->add_field( array(
	'title'       => sprintf( __( '%s signatures', 'secupress' ), secupress_firewall_ng_name() ),
	/* translators: %s: firewall pack name (e.g. 8G) */
	'description' => sprintf( __( 'Adds the %s signature pack to the filters selected above. Learning Mode can then calibrate them for this site.', 'secupress' ), secupress_firewall_ng_name() ),
	'label_for'   => $ng_field_name,
	'name'        => $ng_field_name,
	'depends'     => $bbx_field_name . '_user-agents-header ' . $bbx_field_name . '_bad-url-contents ' . $bbx_field_name . '_bad-referer',
	'type'        => 'checkbox',
	'value'       => (int) secupress_firewall_ng_is_enabled(),
	/* translators: %s: firewall pack name (e.g. 8G) */
	'label'       => sprintf( __( 'Yes, add %s signatures to the selected optionsThis feature is unavailable. Your server cannot verify hostnames accurately. We apologize for the inconvenience.', 'secupress' ), secupress_firewall_ng_name() ),
) );

$this->add_field( array(
	'title'       => __( 'Firewall Learning Mode', 'secupress' ),
	/* translators: %s: firewall pack name (e.g. 8G) */
	'description' => sprintf( __( '%s rules adapt to this site instead of blocking blindly. Calibrable signatures are observed; critical exploits are always blocked.', 'secupress' ), secupress_firewall_ng_name() ),
	'name'        => $this->get_field_name( 'learning' ),
	'type'        => 'learning_mode',
) );