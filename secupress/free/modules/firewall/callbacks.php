<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

/** --------------------------------------------------------------------------------------------- */
/** ON MODULE SETTINGS SAVE ===================================================================== */
/** --------------------------------------------------------------------------------------------- */

/**
 * Callback to filter, sanitize and de/activate submodules
 *
 * @since 1.0
 *
 * @param (array) $settings The module settings.
 *
 * @return (array) The sanitized and validated settings.
 */
function secupress_firewall_settings_callback( $settings ) {
	$modulenow = 'firewall';
	$activate  = secupress_get_submodule_activations( $modulenow );
	$settings  = $settings && is_array( $settings ) ? $settings : array();

	if ( isset( $settings['sanitized'] ) ) {
		return $settings;
	}
	$settings['sanitized'] = 1;

	// Keep the last Learning Mode date even when NG checkboxes are unchecked (the field is not posted).
	$old_last = (int) secupress_get_module_option( 'learning-mode_last', 0, $modulenow );
	$new_last = isset( $settings['learning-mode_last'] ) ? (int) $settings['learning-mode_last'] : 0;
	$keep     = max( $old_last, $new_last );
	if ( $keep > 0 ) {
		$settings['learning-mode_last'] = $keep;
	} else {
		unset( $settings['learning-mode_last'] );
	}

	/*
	 * Each submodule has its own sanitization function.
	 * The `$settings` parameter is passed by reference.
	 */
	// Web Application Firewall (BBQ filters + NG pack).
	secupress_waf_settings_callback( $modulenow, $settings, $activate );

	// Bad headers.
	secupress_bad_headers_settings_callback( $modulenow, $settings, $activate );

	// Bad contents.
	secupress_bad_contents_settings_callback( $modulenow, $settings, $activate );

	/**
	 * Filter the settings before saving.
	 *
	 * @since 1.4.9
	 *
	 * @param (array)      $settings The module settings.
	 * @param (array\bool) $activate Contains the activation rules for the different modules
	 */
	$settings = apply_filters( "secupress_{$modulenow}_settings_callback", $settings, $activate );

	return $settings;
}

/**
 * Web Application Firewall plugins (BBQ filters + NG pack).
 *
 * @since 2.7.1
 *
 * @param (string)     $modulenow Current module.
 * @param (array)      $settings  The module settings, passed by reference.
 * @param (bool|array) $activate  Used to (de)activate plugins.
 */
function secupress_waf_settings_callback( $modulenow, &$settings, $activate ) {
	if ( false !== $activate ) {
		$bbx = array();
		if ( ! empty( $activate['learning-mode_bbx'] ) && is_array( $activate['learning-mode_bbx'] ) ) {
			$bbx = array_flip( $activate['learning-mode_bbx'] );
		}
		secupress_manage_submodule( $modulenow, 'user-agents-header', isset( $bbx['user-agents-header'] ) );
		secupress_manage_submodule( $modulenow, 'bad-url-contents', isset( $bbx['bad-url-contents'] ) );
		secupress_manage_submodule( $modulenow, 'bad-referer', isset( $bbx['bad-referer'] ) );

		if ( secupress_is_pro() ) {
			$settings['learning-mode_8g'] = ! empty( $settings['learning-mode_8g'] ) ? 1 : 0;
			if ( ! $settings['learning-mode_8g'] ) {
				secupress_firewall_learning_deactivate();
			}
		} else {
			$previous = get_site_option( 'secupress_firewall_settings', array() );
			if ( is_array( $previous ) && array_key_exists( 'learning-mode_8g', $previous ) ) {
				$settings['learning-mode_8g'] = (int) $previous['learning-mode_8g'] ? 1 : 0;
			} else {
				unset( $settings['learning-mode_8g'] );
			}
		}
	}

	if ( ! empty( $settings['bbq-headers_bad-referer-list'] ) ) {
		$settings['bbq-headers_bad-referer-list'] = trim( implode( ',', secupress_unique_sorted_list( $settings['bbq-headers_bad-referer-list'], "\n", 'array' ) ), ',' );
	}
}

/**
 * Bad Headers plugins.
 *
 * @since 1.0
 *
 * @param (string)     $modulenow Current module.
 * @param (array)      $settings  The module settings, passed by reference.
 * @param (bool|array) $activate  Used to (de)activate plugins.
 */
function secupress_bad_headers_settings_callback( $modulenow, &$settings, $activate ) {
	// (De)Activation.
	if ( false !== $activate ) {
		secupress_manage_submodule( $modulenow, 'fake-google-bots', ! empty( $activate['bbq-headers_fake-google-bots'] ) );
	}
	// Settings.
	if ( ! empty( $settings['bbq-headers_user-agents-list'] ) ) {
		$settings['bbq-headers_user-agents-list'] = sanitize_text_field( $settings['bbq-headers_user-agents-list'] );
		$settings['bbq-headers_user-agents-list'] = secupress_sanitize_list( $settings['bbq-headers_user-agents-list'] );
		$settings['bbq-headers_user-agents-list'] = secupress_unique_sorted_list( $settings['bbq-headers_user-agents-list'], ', ' );
	}

	if ( empty( $settings['bbq-headers_user-agents-list'] ) ) {
		$settings['bbq-headers_user-agents-list'] = secupress_firewall_bbq_headers_user_agents_list_default();
	}
}

/**
 * Bad Contents plugins.
 *
 * @since 1.0
 *
 * @param (string)     $modulenow Current module.
 * @param (array)      $settings  The module settings, passed by reference.
 * @param (bool|array) $activate  Used to (de)activate plugins.
 */
function secupress_bad_contents_settings_callback( $modulenow, &$settings, $activate ) {
	// (De)Activation.
	if ( false !== $activate ) {
		secupress_manage_submodule( $modulenow, 'ban-404-php', ! empty( $activate['bbq-url-content_ban-404-php'] ) );
	}
	// Settings.
	// if ( ! empty( $settings['bbq-url-content_bad-contents-list'] ) ) {
		// // Do not sanitize the value or the sky will fall.
		// $settings['bbq-url-content_bad-contents-list'] = secupress_sanitize_list( $settings['bbq-url-content_bad-contents-list'] );
		// $settings['bbq-url-content_bad-contents-list'] = secupress_unique_sorted_list( $settings['bbq-url-content_bad-contents-list'], ', ' );
	// }

	// if ( empty( $settings['bbq-url-content_bad-contents-list'] ) ) {
		$settings['bbq-url-content_bad-contents-list'] = secupress_firewall_bbq_url_content_bad_contents_list_default();
	// }
}



/** --------------------------------------------------------------------------------------------- */
/** INSTALL/RESET =============================================================================== */
/** --------------------------------------------------------------------------------------------- */

add_action( 'secupress.first_install', 'secupress_install_firewall_module' );
/**
 * Create default option on install and reset.
 *
 * @since 1.0
 *
 * @param (string) $module The module(s) that will be reset to default. `all` means "all modules".
 */
function secupress_install_firewall_module( $module ) {
	if ( 'all' === $module || 'firewall' === $module ) {
		$previous = get_site_option( 'secupress_firewall_settings', array() );
		$settings = array(
			'bbq-headers_user-agents-list'      => secupress_firewall_bbq_headers_user_agents_list_default(),
			'bbq-url-content_bad-contents-list' => secupress_firewall_bbq_url_content_bad_contents_list_default(),
		);
		if ( ! empty( $previous['learning-mode_last'] ) ) {
			$settings['learning-mode_last'] = (int) $previous['learning-mode_last'];
		}
		if ( is_array( $previous ) && array_key_exists( 'learning-mode_8g', $previous ) ) {
			$settings['learning-mode_8g'] = (int) $previous['learning-mode_8g'] ? 1 : 0;
		}
		update_site_option( 'secupress_firewall_settings', $settings );
	}
}
