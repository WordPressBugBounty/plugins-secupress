<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

remove_filter( 'wp_robots', 'wp_robots_noindex_embeds' );
remove_filter( 'wp_robots', 'wp_robots_noindex_search' );
add_action( 'wp', 'secupress_late_robots_check' );
/**
 * Load the robots condition tags later to prevent php warning
 *
 * @see https://core.trac.wordpress.org/ticket/53262
 * @author Julio Potier
 * @since 2.2
 * 
 * @return (void)
 **/
function secupress_late_robots_check() {
    add_filter( 'wp_robots', 'wp_robots_noindex_embeds' );
    add_filter( 'wp_robots', 'wp_robots_noindex_search' );
}

add_filter( 'doing_it_wrong_trigger_error', 'secupress_remove_fking_warning_from_wp67', 10, 2 );
/**
 * Prevent the useless message from the bug inserted in WP 6.7 by WP CORE DEVs, congratz to the test team.
 * ps: Works for all the plugins not only SecuPress.
 *
 * @see https://core.trac.wordpress.org/ticket/62462
 * @author Julio Potier
 * @since 2.2.6
 * 
 * @return (bool)
 **/
function secupress_remove_fking_warning_from_wp67( $bool, $function_name ) {
    if ( '_load_textdomain_just_in_time' === $function_name ) {
        $bool = false;
    }
    return $bool;
}

/**
 * Bootstrap files for UpdraftPlus.
 *
 * Free and Premium share the official directory slug "updraftplus".
 * Free: updraftplus/updraftplus.php (https://wordpress.org/plugins/updraftplus/).
 * Premium (TeamUpdraft) replaces that same directory. There is no second folder.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @return (array)
 **/
function secupress_hotfix_updraftcentral_plugin_files() {
	return [
		'updraftplus/updraftplus.php',
	];
}

/**
 * Tell if UpdraftPlus free or Premium is active.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @return (bool)
 **/
function secupress_hotfix_updraftcentral_is_active() {
	$plugins = (array) get_option( 'active_plugins', [] );
	$plugins = array_flip( $plugins );
	if ( is_multisite() ) {
		$plugins = $plugins + (array) get_site_option( 'active_sitewide_plugins', [] );
	}

	foreach ( secupress_hotfix_updraftcentral_plugin_files() as $plugin_file ) {
		if ( isset( $plugins[ $plugin_file ] ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Tell if UpdraftPlus free or Premium is present, even when deactivated.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @return (bool)
 **/
function secupress_hotfix_updraftcentral_is_installed() {
	static $installed = null;

	if ( null !== $installed ) {
		return $installed;
	}

	$installed = false;

	foreach ( secupress_hotfix_updraftcentral_plugin_files() as $plugin_file ) {
		if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) ) {
			$installed = true;
			break;
		}
	}

	return $installed;
}

/**
 * Tell if the UpdraftCentral notice may be shown.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @return (bool)
 **/
function secupress_hotfix_updraftcentral_should_display() {
	if ( ! is_admin() || wp_doing_ajax() ) {
		return false;
	}
	if ( ! function_exists( 'secupress_get_capability' ) || ! current_user_can( secupress_get_capability() ) ) {
		return false;
	}
	if ( function_exists( 'secupress_notice_is_dismissed' ) && secupress_notice_is_dismissed( 'updraftcentral-backdoor' ) ) {
		return false;
	}

	return secupress_hotfix_updraftcentral_is_installed();
}

add_action( 'all_admin_notices', 'secupress_hotfix_updraftcentral_notice' );
/**
 * Warn when UpdraftPlus is installed. The check itself stays manual.
 *
 * @author Julio Potier
 * @since 2.7.2
 **/
function secupress_hotfix_updraftcentral_notice() {
	if ( ! secupress_hotfix_updraftcentral_should_display() || ! function_exists( 'secupress_add_notice' ) ) {
		return;
	}

	$url      = 'https://nethemba.com/updraftcentral-backdoor-analysis/';
	$message  = '<div id="secupress-hotfix-updraftcentral">';
	$message .= '<p><strong>' . sprintf( esc_html__( 'Warning: Critical vulnerability detected in %s, very high risk. Let us help you patch it.', 'secupress' ), 'UpdraftCentral' ) . '</strong> ';
	$message .= '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Read the official article', 'secupress' ) . '</a></p>';
	$message .= '<p>';
	$message .= '<button type="button" class="button button-primary" id="secupress-hotfix-updraftcentral-patch">' . esc_html__( 'Patch now', 'secupress' ) . '</button> ';
	$message .= '<button type="button" class="button" id="secupress-hotfix-updraftcentral-ignore">' . esc_html__( 'Ignore forever', 'secupress' ) . '</button> ';
	$message .= '<span class="spinner" id="secupress-hotfix-updraftcentral-spinner" style="float:none;margin:0 0 0 4px"></span>';
	$message .= '</p>';
	$message .= '<div id="secupress-hotfix-updraftcentral-result"></div>';
	$message .= '</div>';

	secupress_add_notice( $message, 'error', false );
}

add_action( 'admin_enqueue_scripts', 'secupress_hotfix_updraftcentral_scripts' );
/**
 * Ajax controls for the UpdraftCentral notice.
 *
 * @author Julio Potier
 * @since 2.7.2
 **/
function secupress_hotfix_updraftcentral_scripts() {
	if ( ! secupress_hotfix_updraftcentral_should_display() ) {
		return;
	}

	wp_enqueue_script( 'jquery' );
	wp_localize_script(
		'jquery',
		'secupressHotfixUpdraftCentral',
		[
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( 'secupress-hotfix-updraftcentral' ),
			'confirmIgnore' => __( 'Are you sure you want to ignore this alert forever?', 'secupress' ),
			'errorMessage'  => __( 'The check could not be completed.', 'secupress' ),
		]
	);

	$script = implode(
		"\n",
		[
			'jQuery( function( $ ) {',
			'	var config = window.secupressHotfixUpdraftCentral;',
			'	var box = $( "#secupress-hotfix-updraftcentral" );',
			'	if ( ! box.length || ! config ) {',
			'		return;',
			'	}',
			'	var spinner = $( "#secupress-hotfix-updraftcentral-spinner" );',
			'	var result = $( "#secupress-hotfix-updraftcentral-result" );',
			'	function setBusy( busy ) {',
			'		box.find( "button" ).prop( "disabled", busy );',
			'		spinner.toggleClass( "is-active", busy );',
			'	}',
			'	function showError() {',
			'		result.empty().append( $( "<p>" ).text( config.errorMessage ) );',
			'	}',
			'	$( "#secupress-hotfix-updraftcentral-ignore" ).on( "click", function( event ) {',
			'		event.preventDefault();',
			'		if ( ! window.confirm( config.confirmIgnore ) ) {',
			'			return;',
			'		}',
			'		setBusy( true );',
			'		$.ajax( {',
			'			url: config.ajaxUrl,',
			'			method: "POST",',
			'			dataType: "json",',
			'			data: {',
			'				action: "secupress_hotfix_updraftcentral_ignore",',
			'				_ajax_nonce: config.nonce',
			'			}',
			'		} ).done( function( response ) {',
			'			if ( response && response.success ) {',
			'				box.closest( ".notice" ).remove();',
			'				return;',
			'			}',
			'			setBusy( false );',
			'			showError();',
			'		} ).fail( function() {',
			'			setBusy( false );',
			'			showError();',
			'		} );',
			'	} );',
			'	$( "#secupress-hotfix-updraftcentral-patch" ).on( "click", function( event ) {',
			'		event.preventDefault();',
			'		setBusy( true );',
			'		result.empty();',
			'		$.ajax( {',
			'			url: config.ajaxUrl,',
			'			method: "POST",',
			'			dataType: "json",',
			'			data: {',
			'				action: "secupress_hotfix_updraftcentral_patch",',
			'				_ajax_nonce: config.nonce',
			'			}',
			'		} ).done( function( response ) {',
			'			setBusy( false );',
			'			if ( ! response || ! response.success || ! response.data ) {',
			'				showError();',
			'				return;',
			'			}',
			'			if ( response.data.patched ) {',
			'				box.html( response.data.html );',
			'				return;',
			'			}',
			'			result.html( response.data.html );',
			'		} ).fail( function() {',
			'			setBusy( false );',
			'			showError();',
			'		} );',
			'	} );',
			'} );',
		]
	);

	wp_add_inline_script( 'jquery', $script );
}

/**
 * Read-only port of the Nethemba UpdraftCentral check. It changes nothing on the site.
 *
 * @author Julio Potier
 * @since 2.7.2
 * @see https://nethemba.com/wp-content/uploads/2026/10/check-updraftcentral.txt
 *
 * @return (array)
 **/
function secupress_hotfix_updraftcentral_run() {
	global $wpdb;

	$alerts   = 0;
	$warnings = 0;
	$lines    = [];
	$report   = function( $level, $message, $url = '' ) use ( &$alerts, &$warnings, &$lines ) {
		if ( 'ALERT' === $level ) {
			++$alerts;
		}
		if ( 'WARN' === $level ) {
			++$warnings;
		}
		$line = [
			'level'   => $level,
			'message' => $message,
		];
		if ( $url ) {
			$line['url'] = $url;
		}
		$lines[] = $line;
	};

	$keys = get_option( 'updraft_central_localkeys' );
	if ( is_array( $keys ) && $keys ) {
		foreach ( $keys as $id => $key ) {
			$key        = is_array( $key ) ? $key : [];
			$created    = isset( $key['created'] ) ? gmdate( 'Y-m-d', (int) $key['created'] ) : '?';
			$user_login = ( isset( $key['extra_info']['user_login'] ) ) ? (string) $key['extra_info']['user_login'] : '?';
			$report(
				'WARN',
				sprintf(
					__( 'Active UpdraftCentral connection: key %1$s, created on %2$s by user "%3$s". Delete it now.', 'secupress' ),
					$id,
					$created,
					$user_login
				)
			);
		}
	} else {
		$report( 'OK', __( 'No active UpdraftCentral connection.', 'secupress' ) );
	}

	$log      = get_site_option( 'updraftcentral_client_log' );
	$log      = is_array( $log ) ? $log : [];
	$log_hits = 0;
	foreach ( $log as $entry ) {
		$entry   = is_array( $entry ) ? $entry : [];
		$message = isset( $entry['message'] ) ? (string) $entry['message'] : '';
		$agent   = isset( $entry['http_user_agent'] ) ? (string) $entry['http_user_agent'] : '';
		$when    = isset( $entry['time'] ) ? gmdate( 'Y-m-d H:i', (int) $entry['time'] ) . ' UTC' : '?';
		$ip      = isset( $entry['remote_ip'] ) ? (string) $entry['remote_ip'] : '?';
		if ( preg_match( '/upload_(plugin|theme)/', $message ) ) {
			$report(
				'ALERT',
				sprintf(
					__( '%1$s %2$s "%3$s" (UA: %4$s). A plugin or theme was pushed to this site. If you have already cleaned this up, you can ignore this message.', 'secupress' ),
					$when,
					$ip,
					$message,
					$agent
				)
			);
			++$log_hits;
		} elseif ( false !== strpos( $message, 'probe.central.updraftplus.com' ) ) {
			$report(
				'WARN',
				sprintf(
					__( '%1$s %2$s UpdraftCentral probe (UA: %3$s).', 'secupress' ),
					$when,
					$ip,
					$agent
				)
			);
			++$log_hits;
		} elseif ( 'Command received' === substr( $message, 0, 16 ) && preg_match( '/class-udrpc\.php$/', $agent ) ) {
			$report(
				'ALERT',
				sprintf(
					__( '%1$s %2$s "%3$s" from a client that is not UpdraftCentral Cloud (UA: %4$s).', 'secupress' ),
					$when,
					$ip,
					$message,
					$agent
				)
			);
			++$log_hits;
		}
	}
	if ( ! $log_hits ) {
		$report( 'OK', __( 'The UpdraftCentral log shows no plugin uploads, probes or foreign clients.', 'secupress' ) );
	}

	$plugin_hits = 0;
	$patterns    = [
		WP_PLUGIN_DIR . '/*/*.php',
		WPMU_PLUGIN_DIR . '/*.php',
	];
	foreach ( $patterns as $pattern ) {
		$files = glob( $pattern );
		if ( ! is_array( $files ) ) {
			continue;
		}
		foreach ( $files as $file ) {
			if ( ! is_string( $file ) || ! is_readable( $file ) ) {
				continue;
			}
			$code = file_get_contents( $file, false, null, 0, 200000 );
			if ( ! is_string( $code ) ) {
				continue;
			}
			$template = false !== strpos( $code, 'keeps option storage tidy' );
			if ( ! $template ) {
				$template = false !== strpos( $code, "_sync_token'" ) && false !== strpos( $code, 'wp_set_auth_cookie' ) && false !== strpos( $code, 'hash_equals' );
			}
			if ( $template ) {
				$report(
					'ALERT',
					sprintf(
						__( 'Backdoor plugin file: %s', 'secupress' ),
						$file
					)
				);
				++$plugin_hits;
			}
		}
	}
	if ( ! $plugin_hits ) {
		$report( 'OK', __( 'No backdoor plugin files found.', 'secupress' ) );
	}

	$options = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name REGEXP '^[a-z]+_[a-z]+_[0-9a-f]{3}_(sync_token|setup_stamp)$'" );
	if ( ! is_array( $options ) ) {
		$options = [];
	}
	foreach ( $options as $name ) {
		$report(
			'ALERT',
			sprintf(
				__( 'Backdoor option in the database: %s', 'secupress' ),
				$name
			)
		);
	}
	if ( ! $options ) {
		$report( 'OK', __( 'No backdoor options in the database.', 'secupress' ) );
	}

	$user_hits  = 0;
	$cap_key    = $wpdb->get_blog_prefix() . 'capabilities';
	$wp_roles   = wp_roles();
	$admin_caps = [
		'manage_options',
		'install_plugins',
		'activate_plugins',
		'upload_files',
		'install_themes',
		'switch_themes',
		'edit_files',
	];
	$users      = get_users(
		[
			'fields' => [ 'ID', 'user_login', 'user_email', 'display_name', 'user_registered' ],
		]
	);
	$date_format = get_option( 'date_format' );
	foreach ( $users as $user ) {
		$caps             = (array) get_user_meta( $user->ID, $cap_key, true );
		$is_administrator = ! empty( $caps['administrator'] );
		$registered_local = ! empty( $user->user_registered ) ? get_date_from_gmt( $user->user_registered ) : '';
		$registered       = $registered_local ? mysql2date( $date_format, $registered_local ) : '?';
		$role_names       = [];
		foreach ( array_keys( array_filter( $caps ) ) as $cap_key_name ) {
			if ( isset( $wp_roles->roles[ $cap_key_name ]['name'] ) ) {
				$role_names[] = translate_user_role( $wp_roles->roles[ $cap_key_name ]['name'] );
			}
		}
		$role_label = $role_names ? implode( ', ', $role_names ) : __( 'No role', 'secupress' );
		$user_url   = add_query_arg( 's', $user->user_email, admin_url( 'users.php' ) );
		$in_october = $registered_local >= '2026-10-01 00:00:00' && $registered_local < '2026-11-01 00:00:00';
		if ( $is_administrator && $in_october ) {
			$report(
				'WARN',
				sprintf(
					__( '%1$s (%2$s, %3$s), registered on %4$s, role: %5$s. This administrator was created in October 2026 and must be reviewed. This account is not necessarily malicious.', 'secupress' ),
					$user->display_name,
					$user->user_login,
					$user->user_email,
					$registered,
					$role_label
				),
				$user_url
			);
			++$user_hits;
		}
		$granted = array_values( array_intersect( $admin_caps, array_keys( array_filter( $caps ) ) ) );
		if ( $is_administrator || ! $granted ) {
			continue;
		}
		$report(
			'ALERT',
			sprintf(
				__( '%1$s (%2$s, %3$s), registered on %4$s, role: %5$s. This person is not an administrator but still has administrator rights (%6$s). This account is considered malicious and must be deleted.', 'secupress' ),
				$user->display_name,
				$user->user_login,
				$user->user_email,
				$registered,
				$role_label,
				implode( ', ', $granted )
			),
			$user_url
		);
		++$user_hits;
	}
	if ( ! $user_hits ) {
		$report( 'OK', __( 'No non-administrator account with administrator rights, and no administrator created in October 2026.', 'secupress' ) );
	}

	return [
		'patched'  => 0 === $alerts && 0 === $warnings,
		'alerts'   => $alerts,
		'warnings' => $warnings,
		'lines'    => $lines,
	];
}

/**
 * Html result for the UpdraftCentral check.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @param $result (array)
 *
 * @return (string)
 **/
function secupress_hotfix_updraftcentral_result_html( $result ) {
	if ( ! empty( $result['patched'] ) ) {
		$summary = __( 'Patched. No traces found. You can continue to use your website as usual.', 'secupress' );
	} elseif ( ! empty( $result['alerts'] ) ) {
		$summary = __( 'Not patched. Traces of the attack were found. Delete the UpdraftCentral connection, remove the backdoor and check users and passwords.', 'secupress' );
	} else {
		$summary = __( 'Not patched. No backdoor found, but the warnings below must be reviewed.', 'secupress' );
	}

	$html = '<p><strong>' . esc_html( $summary ) . '</strong></p>';
	if ( ! empty( $result['patched'] ) || empty( $result['lines'] ) || ! is_array( $result['lines'] ) ) {
		return $html;
	}

	$html .= '<ul>';
	foreach ( $result['lines'] as $line ) {
		if ( empty( $line['level'] ) || 'OK' === $line['level'] ) {
			continue;
		}
		$label = 'ALERT' === $line['level'] ? __( 'Alert', 'secupress' ) : __( 'Warning', 'secupress' );
		$html .= '<li><strong>' . esc_html( $label ) . '</strong> ' . esc_html( $line['message'] );
		if ( ! empty( $line['url'] ) ) {
			$link_label = 'ALERT' === $line['level'] ? __( 'Delete this account', 'secupress' ) : __( 'Review this account', 'secupress' );
			$html .= ' <a href="' . esc_url( $line['url'] ) . '">' . esc_html( $link_label ) . '</a>';
		}
		$html .= '</li>';
	}
	$html .= '</ul>';

	return $html;
}

add_action( 'wp_ajax_secupress_hotfix_updraftcentral_patch', 'secupress_hotfix_updraftcentral_patch_ajax' );
/**
 * Run the UpdraftCentral check on demand.
 *
 * @author Julio Potier
 * @since 2.7.2
 **/
function secupress_hotfix_updraftcentral_patch_ajax() {
	if ( ! function_exists( 'secupress_get_capability' ) || ! current_user_can( secupress_get_capability() ) || ! check_ajax_referer( 'secupress-hotfix-updraftcentral', '_ajax_nonce', false ) ) {
		wp_send_json_error();
	}
	if ( ! secupress_hotfix_updraftcentral_is_installed() ) {
		wp_send_json_error();
	}

	$result = secupress_hotfix_updraftcentral_run();

	if ( ! empty( $result['patched'] ) && function_exists( 'secupress_dismiss_notice_for_site' ) ) {
		secupress_dismiss_notice_for_site( 'updraftcentral-backdoor' );
	}

	wp_send_json_success(
		[
			'patched' => ! empty( $result['patched'] ),
			'html'    => secupress_hotfix_updraftcentral_result_html( $result ),
		]
	);
}

add_action( 'wp_ajax_secupress_hotfix_updraftcentral_ignore', 'secupress_hotfix_updraftcentral_ignore_ajax' );
/**
 * Hide the UpdraftCentral notice for the whole site and delete its client log.
 *
 * @author Julio Potier
 * @since 2.7.2
 **/
function secupress_hotfix_updraftcentral_ignore_ajax() {
	if ( ! function_exists( 'secupress_get_capability' ) || ! current_user_can( secupress_get_capability() ) || ! check_ajax_referer( 'secupress-hotfix-updraftcentral', '_ajax_nonce', false ) ) {
		wp_send_json_error();
	}

	if ( function_exists( 'secupress_dismiss_notice_for_site' ) ) {
		secupress_dismiss_notice_for_site( 'updraftcentral-backdoor' );
	}
	delete_site_option( 'updraftcentral_client_log' );
	wp_send_json_success();
}