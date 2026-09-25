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
			'description' => __( 'Blocking AI bots may impact your SEO, as their results now appear in search engines.', 'secupress' ),
		),
	),
) );

$this->add_field( array(
	'title'             => __( 'Block 404 requests on PHP files', 'secupress' ),
	'description'       => __( 'Allows you to redirect people who attempt to access hidden or malicious PHP files on a 404 page not found error.', 'secupress' ),
	'label_for'         => 'bbq-url-content_ban-404-php',
	'name'              => 'bbq-url-content_ban-404-php',
	'plugin_activation' => true,
	'type'              => 'checkbox',
	'value'             => (int) secupress_is_submodule_active( 'firewall', 'ban-404-php' ),
	'label'             => __( 'Yes, protect my site from 404 on .php files', 'secupress' ),
) );
