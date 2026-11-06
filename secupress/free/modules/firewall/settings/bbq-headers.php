<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );


$this->set_current_section( 'bbq_headers' );
$this->add_section( __( 'Bad Behaviors', 'secupress' ) );


$this->add_field( array(
	'title'             => __( 'Block Fake SEO Bots', 'secupress' ),
	'description'       => __( 'Some servers falsely claim to be Googlebots or other reputable user agents. Detect and block them.', 'secupress' ),
	'label_for'         => $this->get_field_name( 'fake-google-bots' ),
	'plugin_activation' => true,
	'disabled'          => ! secupress_check_bot_ip( true ),
	'type'              => 'checkbox',
	'value'             => (int) secupress_is_submodule_active( 'firewall', 'fake-google-bots' ),
	'label'             => __( 'Yes, protect my site from fake SEO Bots', 'secupress' ),
	'helpers'           => array(
		array(
			'type'        => 'warning',
			'description' => ! secupress_check_bot_ip( true ) ? __( 'This feature is unavailable because your server encountered an issue verifying the hostname. Please contact your hosting provider if the problem persists.', 'secupress' ) : '',
		),
	),
) );

$_ai_bots_list          = secupress_is_pro() ? secupress_firewall_bbq_referer_content_ai_bots_list_default() : '';
$_count_ai_bots         = count( array_filter( explode( "\n", $_ai_bots_list ) ) );
$_count_ai_bots         = $_count_ai_bots ? number_format_i18n( $_count_ai_bots ) : '';
$main_field_name        = $this->get_field_name( 'block-ai' );
$this->add_field( array(
	'title'             => __( 'Block AI Bots', 'secupress' ),
	'description'       => __( 'Artificial Intelligence Bots can visit your website and grab your content for their purpose.', 'secupress' ),
	'plugin_activation' => true,
	'label_for'         => $main_field_name,
	'value'             => (int) secupress_is_submodule_active( 'firewall', 'block-ai' ),
	'type'              => 'checkbox',
	'label'             => sprintf( __( 'Yes, <strong>block</strong> %s AI Bots.', 'secupress' ), $_count_ai_bots ),
	'helpers'      => array(
		array(
			'type'        => 'warning',
			'description' => __( 'Blocking AI bots may impact your GEO (SEO for AI), as their results now appear in search engines.', 'secupress' ),
		),
	),
) );

$main_field_name = 'bbq-url-content_ban-404-php';

$this->add_field( array(
	'title'             => __( 'Block scans for hidden content', 'secupress' ),
	'description'       => __( 'Attackers try many addresses to find forgotten files and hidden pages they could abuse. This stops that search and protects your site.', 'secupress' ),
	'label_for'         => $main_field_name,
	'name'              => $main_field_name,
	'plugin_activation' => true,
	'type'              => 'checkbox',
	'value'             => (int) secupress_is_submodule_active( 'firewall', 'ban-404-php' ),
	'label'             => __( 'Yes, protect my site from these scans', 'secupress' ),
) );

$this->add_field( array(
	'title'        => __( 'Verified bots', 'secupress' ),
	'description'  => __( 'Bots we can identify for real, such as Google and Bing, are left alone so your site stays visible to them.', 'secupress' ),
	'label_for'    => 'bbq-url-content_404-skip-google',
	'name'         => 'bbq-url-content_404-skip-google',
	'type'         => 'checkbox',
	'depends'      => $main_field_name,
	'value'        => (int) secupress_get_module_option( 'bbq-url-content_404-skip-google', 1, 'firewall' ),
	'default'      => 1,
	'label'        => __( 'Yes, do not count verified bots', 'secupress' ),
) );

$field_name = 'bbq-url-content_404-max-visitors';
if ( secupress_feature_is_expert( $field_name ) && secupress_is_expert_mode() ) {
	$this->add_field( array(
		'title'        => __( 'How many tries before a block for visitors?', 'secupress' ),
		'description'  => sprintf( __( 'Recommended: %s', 'secupress' ), '60' ),
		'label_for'    => $field_name,
		'name'         => $field_name,
		'type'         => 'number',
		'depends'      => $main_field_name,
		'default'      => 60,
		'value'        => (int) secupress_get_module_option( $field_name, 60, 'firewall' ),
		'label_after'  => __( 'per minute', 'secupress' ),
		'attributes'   => array(
			'min' => 5,
			'max' => 300,
		),
	) );
}

$field_name = 'bbq-url-content_404-max-robots';
if ( secupress_feature_is_expert( $field_name ) && secupress_is_expert_mode() ) {
	$this->add_field( array(
		'title'        => __( 'How many tries before a block for robots?', 'secupress' ),
		'description'  => sprintf( __( 'Recommended: %s', 'secupress' ), '120' ),
		'label_for'    => $field_name,
		'name'         => $field_name,
		'type'         => 'number',
		'depends'      => $main_field_name,
		'default'      => 120,
		'value'        => (int) secupress_get_module_option( $field_name, 120, 'firewall' ),
		'label_after'  => __( 'per minute', 'secupress' ),
		'attributes'   => array(
			'min' => 5,
			'max' => 300,
		),
	) );
}

$field_name = 'bbq-url-content_404-ban-time';
if ( secupress_feature_is_expert( $field_name ) && secupress_is_expert_mode() ) {
	$this->add_field( array(
		'title'        => __( 'How long should the block last?', 'secupress' ),
		'description'  => sprintf( __( 'Recommended: %s', 'secupress' ), '5 - 15' ),
		'label_for'    => $field_name,
		'name'         => $field_name,
		'type'         => 'number',
		'depends'      => $main_field_name,
		'default'      => 5,
		'value'        => (int) secupress_get_module_option( $field_name, 5, 'firewall' ),
		'label_after'  => _x( 'min', 'minute', 'secupress' ),
		'attributes'   => array(
			'min' => 1,
			'max' => 60,
		),
	) );
}
