<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

/** --------------------------------------------------------------------------------------------- */
/** FIREWALL LEARNING MODE ====================================================================== */
/** --------------------------------------------------------------------------------------------- */

/**
 * Tell if the NG signature pack is enabled (Pro setting, with retrocompat).
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @return (bool)
 */
function secupress_firewall_ng_is_enabled() {
	if ( ! secupress_is_pro() ) {
		return false;
	}
	$value = secupress_get_module_option( 'learning-mode_8g', null, 'firewall' );
	if ( null === $value ) {
		return true;
	}
	return 1 === (int) $value;
}

/**
 * Tell if Learning Mode is currently observing (not expired).
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @return (bool)
 */
function secupress_firewall_learning_is_running() {
	$state = get_site_option( SECUPRESS_FIREWALL_LEARNING, [] );
	return is_array( $state )
		&& isset( $state['status'] )
		&& 'running' === $state['status']
		&& (int) $state['ends'] > time();
}

/**
 * Abort an admin-post Learning Mode action when NG signatures are off.
 *
 * @since 2.7.1
 * @author Julio Potier
 */
function secupress_firewall_learning_require_expert() {
	if ( secupress_firewall_ng_is_enabled() ) {
		return;
	}
	secupress_admin_send_message_die( [
		'message'     => sprintf(
			/* translators: %s: firewall pack name (e.g. 8G) */
			__( 'Learning Mode is available when %s signatures are enabled.', 'secupress' ),
			secupress_firewall_ng_name()
		),
		'code'        => 'learning_ng_only',
		'type'        => 'error',
		'redirect_to' => secupress_admin_url( 'modules', 'firewall' ),
	] );
}

/**
 * Get Learning Mode state.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @return (array)
 */
function secupress_firewall_learning_get_state() {
	$defaults = [
		'status'          => 'off',
		'started'         => 0,
		'ends'            => 0,
		'duration'        => 7,
		'trigger'         => 'manual',
		'requests_front'  => 0,
		'requests_admin'  => 0,
	];
	$state = get_site_option( SECUPRESS_FIREWALL_LEARNING, [] );
	$state = wp_parse_args( is_array( $state ) ? $state : [], $defaults );
	if ( 'running' === $state['status'] && (int) $state['ends'] && time() >= (int) $state['ends'] ) {
		secupress_firewall_learning_end( true );
		$state = get_site_option( SECUPRESS_FIREWALL_LEARNING, [] );
		$state = wp_parse_args( is_array( $state ) ? $state : [], $defaults );
	}
	if ( 'review' === $state['status'] && ! secupress_firewall_learning_has_pending_hits() ) {
		$state['status'] = 'off';
		secupress_firewall_learning_set_state( $state );
	}
	return $state;
}

/**
 * Save Learning Mode state.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (array) $state
 */
function secupress_firewall_learning_set_state( $state ) {
	$defaults = [
		'status'          => 'off',
		'started'         => 0,
		'ends'            => 0,
		'duration'        => 7,
		'trigger'         => 'manual',
		'requests_front'  => 0,
		'requests_admin'  => 0,
	];
	$state = wp_parse_args( (array) $state, $defaults );
	$state['requests_front'] = max( 0, (int) $state['requests_front'] );
	$state['requests_admin'] = max( 0, (int) $state['requests_admin'] );
	$state['status']   = in_array( $state['status'], [ 'off', 'running', 'review' ], true ) ? $state['status'] : 'off';
	$state['started']  = (int) $state['started'];
	$state['ends']     = (int) $state['ends'];
	$state['duration'] = secupress_firewall_learning_sanitize_duration( $state['duration'] );
	$state['trigger']  = in_array( $state['trigger'], [ 'manual', 'plugin' ], true ) ? $state['trigger'] : 'manual';
	update_site_option( SECUPRESS_FIREWALL_LEARNING, $state );
}

/**
 * Get the timestamp of the last Learning Mode launch.
 * Never returns a wiped value: an empty setting is backfilled from a previous run state.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @return (int)
 */
function secupress_firewall_learning_get_last_started() {
	$last = (int) secupress_get_module_option( 'learning-mode_last', 0, 'firewall' );
	if ( $last > 0 ) {
		return $last;
	}
	$state = get_site_option( SECUPRESS_FIREWALL_LEARNING, [] );
	if ( ! is_array( $state ) || empty( $state['started'] ) ) {
		return 0;
	}
	$last = (int) $state['started'];
	if ( $last > 0 ) {
		secupress_firewall_learning_set_last_started( $last );
	}
	return $last;
}

/**
 * Store the timestamp of the last Learning Mode launch.
 * Refuses 0: unchecking NG features must not delete this date.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (int) $timestamp Unix timestamp.
 *
 * @return (bool)
 */
function secupress_firewall_learning_set_last_started( $timestamp ) {
	$timestamp = (int) $timestamp;
	if ( $timestamp < 1 ) {
		return false;
	}
	secupress_update_module_option( 'learning-mode_last', $timestamp, 'firewall' );
	return true;
}

/**
 * NG submodule slugs whose signatures can be calibrated by Learning Mode.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @return (array)
 */
function secupress_firewall_learning_ng_submodules() {
	return [ 'bad-url-contents', 'user-agents-header', 'bad-referer' ];
}

/**
 * Currently active NG submodules.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @return (array)
 */
function secupress_firewall_learning_get_active_ng_modules() {
	$active = [];
	foreach ( secupress_firewall_learning_ng_submodules() as $submodule ) {
		if ( secupress_is_submodule_active( 'firewall', $submodule ) ) {
			$active[] = $submodule;
		}
	}
	return $active;
}

/**
 * NG submodules that were active when Learning Mode last started.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @return (array)
 */
function secupress_firewall_learning_get_ng_snapshot() {
	$snapshot = get_site_option( SECUPRESS_FIREWALL_LEARNING_NG, [] );
	return is_array( $snapshot ) ? $snapshot : [];
}

/**
 * Store the NG submodules calibrated by the current/last Learning Mode.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (array) $submodules
 */
function secupress_firewall_learning_set_ng_snapshot( $submodules ) {
	$allowed    = secupress_firewall_learning_ng_submodules();
	$submodules = array_values( array_intersect( $allowed, array_unique( array_filter( (array) $submodules ) ) ) );
	update_site_option( SECUPRESS_FIREWALL_LEARNING_NG, $submodules );
}

/**
 * Add an NG submodule to the snapshot (observed while Learning Mode is already running).
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (string) $submodule
 */
function secupress_firewall_learning_remember_ng_module( $submodule ) {
	if ( ! in_array( $submodule, secupress_firewall_learning_ng_submodules(), true ) ) {
		return;
	}
	$snapshot = secupress_firewall_learning_get_ng_snapshot();
	if ( in_array( $submodule, $snapshot, true ) ) {
		return;
	}
	$snapshot[] = $submodule;
	secupress_firewall_learning_set_ng_snapshot( $snapshot );
}

/**
 * Tell if an active NG submodule has not been calibrated yet.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @return (bool)
 */
function secupress_firewall_learning_ng_needs_calibration() {
	$active = secupress_firewall_learning_get_active_ng_modules();
	if ( ! $active ) {
		return false;
	}
	if ( ! secupress_firewall_learning_get_last_started() ) {
		return true;
	}
	secupress_firewall_learning_maybe_init_snapshots();
	$snapshot = secupress_firewall_learning_get_ng_snapshot();
	foreach ( $active as $submodule ) {
		if ( ! in_array( $submodule, $snapshot, true ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Plugin files that are active on this site.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @return (array)
 */
function secupress_firewall_learning_get_active_plugin_files() {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$installed = get_plugins();
	$installed = is_array( $installed ) ? array_keys( $installed ) : [];
	$active    = (array) get_option( 'active_plugins', [] );
	if ( is_multisite() ) {
		$network = array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) );
		$active  = array_merge( $active, $network );
	}
	$active = array_values( array_unique( array_intersect( $active, $installed ) ) );
	$out    = [];
	foreach ( $active as $plugin ) {
		if ( secupress_firewall_learning_is_own_plugin( $plugin ) ) {
			continue;
		}
		$out[] = $plugin;
	}
	return $out;
}

/**
 * Plugins already seen active. Deactivation does not remove them.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @return (array)
 */
function secupress_firewall_learning_get_plugins_snapshot() {
	secupress_firewall_learning_migrate_plugins_snapshot();
	$snapshot = get_site_option( SECUPRESS_FIREWALL_LEARNING_PLUGINS, [] );
	return is_array( $snapshot ) ? $snapshot : [];
}

/**
 * Turn a previous installed-plugins list into the active plugins seen so far.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (string) $except_plugin Plugin file to keep unknown during this pass.
 */
function secupress_firewall_learning_migrate_plugins_snapshot( $except_plugin = '' ) {
	static $done = false;

	if ( $done || get_site_option( 'secupress_firewall_learning_plugins_active' ) ) {
		$done = true;
		return;
	}
	$done = true;
	if ( null !== get_site_option( SECUPRESS_FIREWALL_LEARNING_PLUGINS, null ) ) {
		$plugins = secupress_firewall_learning_get_active_plugin_files();
		if ( $except_plugin ) {
			$plugins = array_values( array_diff( $plugins, [ $except_plugin ] ) );
		}
		secupress_firewall_learning_set_plugins_snapshot( $plugins );
	}
	update_site_option( 'secupress_firewall_learning_plugins_active', 1 );
}

/**
 * Store the plugins already seen active.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (array) $plugins
 */
function secupress_firewall_learning_set_plugins_snapshot( $plugins ) {
	$plugins = array_values( array_unique( array_filter( (array) $plugins ) ) );
	sort( $plugins );
	update_site_option( SECUPRESS_FIREWALL_LEARNING_PLUGINS, $plugins );
}

/**
 * Add a plugin to the plugins already seen active.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (string) $plugin Plugin file.
 */
function secupress_firewall_learning_remember_plugin( $plugin ) {
	$plugin = (string) $plugin;
	if ( ! $plugin || secupress_firewall_learning_is_own_plugin( $plugin ) ) {
		return;
	}
	$snapshot = secupress_firewall_learning_get_plugins_snapshot();
	if ( in_array( $plugin, $snapshot, true ) ) {
		return;
	}
	$snapshot[] = $plugin;
	secupress_firewall_learning_set_plugins_snapshot( $snapshot );
}

/**
 * Tell if a plugin file belongs to SecuPress.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (string) $plugin Plugin file.
 *
 * @return (bool)
 */
function secupress_firewall_learning_is_own_plugin( $plugin ) {
	return 0 === strpos( (string) $plugin, 'secupress' );
}

/**
 * Active plugins that have never been seen active.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @return (array)
 */
function secupress_firewall_learning_new_plugins() {
	$snapshot = secupress_firewall_learning_get_plugins_snapshot();
	$new      = [];
	foreach ( secupress_firewall_learning_get_active_plugin_files() as $plugin ) {
		if ( ! in_array( $plugin, $snapshot, true ) ) {
			$new[] = $plugin;
		}
	}
	return $new;
}

/**
 * Backfill snapshots for sites that already ran Learning Mode before snapshots existed.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (string) $except_ng     NG submodule to exclude from a first snapshot.
 * @param (string) $except_plugin Plugin file to exclude from a first snapshot.
 */
function secupress_firewall_learning_maybe_init_snapshots( $except_ng = '', $except_plugin = '' ) {
	if ( ! secupress_firewall_learning_get_last_started() ) {
		return;
	}
	if ( null === get_site_option( SECUPRESS_FIREWALL_LEARNING_NG, null ) ) {
		$active = secupress_firewall_learning_get_active_ng_modules();
		if ( $except_ng ) {
			$active = array_values( array_diff( $active, [ $except_ng ] ) );
		}
		secupress_firewall_learning_set_ng_snapshot( $active );
	}
	if ( null === get_site_option( SECUPRESS_FIREWALL_LEARNING_PLUGINS, null ) ) {
		$plugins = secupress_firewall_learning_get_active_plugin_files();
		if ( $except_plugin ) {
			$plugins = array_values( array_diff( $plugins, [ $except_plugin ] ) );
		}
		secupress_firewall_learning_set_plugins_snapshot( $plugins );
	}
}

/**
 * WordPress version calibrated by the last Learning Mode start.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @return (string)
 */
function secupress_firewall_learning_get_wp_version() {
	$version = get_site_option( 'secupress_firewall_learning_wp_version', '' );
	return is_string( $version ) ? $version : '';
}

/**
 * Store the WordPress version calibrated by Learning Mode.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (string) $version
 */
function secupress_firewall_learning_set_wp_version( $version = '' ) {
	if ( ! is_string( $version ) || '' === $version ) {
		$version = get_bloginfo( 'version' );
	}
	update_site_option( 'secupress_firewall_learning_wp_version', $version );
}

/**
 * Snapshot current NG modules and merge active plugins (called when Learning Mode starts).
 *
 * @since 2.7.1
 * @author Julio Potier
 */
function secupress_firewall_learning_snapshot_now() {
	secupress_firewall_learning_set_ng_snapshot( secupress_firewall_learning_get_active_ng_modules() );
	$plugins = array_merge( secupress_firewall_learning_get_plugins_snapshot(), secupress_firewall_learning_get_active_plugin_files() );
	secupress_firewall_learning_set_plugins_snapshot( $plugins );
	secupress_firewall_learning_set_wp_version();
}

/**
 * Sanitize a duration in days (1–7).
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (int) $duration
 *
 * @return (int)
 */
function secupress_firewall_learning_sanitize_duration( $duration ) {
	return min( 7, max( 1, (int) $duration ) );
}

/**
 * Duration labels for the select.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @return (array)
 */
function secupress_firewall_learning_duration_options() {
	$options = [];
	for ( $i = 1; $i <= 5; $i++ ) {
		$options[ $i ] = sprintf( _n( '%s day', '%s days', $i, 'secupress' ), number_format_i18n( $i ) );
	}
	return $options;
}

/**
 * Hash a NG pattern for storage keys.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (string) $slug
 * @param (string) $pattern
 *
 * @return (string)
 */
function secupress_firewall_learning_pattern_hash( $slug, $pattern ) {
	return md5( $slug . '|' . $pattern );
}

/**
 * Stable key for the exact string a signature matched.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (string) $match
 *
 * @return (string)
 */
function secupress_firewall_learning_match_key( $match ) {
	return md5( strtolower( (string) $match ) );
}

/**
 * Aggregate key: one review row per matched string, not per regex line.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (string) $slug
 * @param (string) $pattern
 * @param (string) $match
 *
 * @return (string)
 */
function secupress_firewall_learning_hit_hash( $slug, $pattern, $match ) {
	return md5( $slug . '|' . $pattern . '|' . secupress_firewall_learning_match_key( $match ) );
}

/**
 * Short copy of a matched string for the review screen.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (string) $match
 *
 * @return (string)
 */
function secupress_firewall_learning_match_sample( $match ) {
	$match = (string) $match;
	if ( strlen( $match ) > 180 ) {
		return substr( $match, 0, 180 ) . '…';
	}
	return $match;
}

/**
 * Short copy of the full value that contained a match.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (string) $subject
 *
 * @return (string)
 */
function secupress_firewall_learning_subject_sample( $subject ) {
	$subject = (string) $subject;
	$subject = str_replace( [ "\r", "\n", "\0" ], '', $subject );
	$keys    = [ 'session', 'auth', 'token', 'password', 'psswrd', 'pass', 'pwd', 'pw', 'user_pass', 'edd_user_pass', 'wordpress_logged_in', 'wordpress_sec', 'wordpress', 'wp_woocommerce_session', 'PHPSESSID' ];
	foreach ( $keys as $key ) {
		$subject = preg_replace( '/(' . preg_quote( $key, '/' ) . '(?:_[^=;\s&]*)?)=[^;&\s]*/i', '$1=***', $subject );
	}
	if ( strlen( $subject ) > 400 ) {
		return substr( $subject, 0, 400 ) . '…';
	}
	return $subject;
}

/**
 * Keep the ten most recent full values for one matched string.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (array)  $subjects
 * @param (string) $subject
 *
 * @return (array)
 */
function secupress_firewall_learning_push_subject( $subjects, $subject ) {
	$subjects = array_values( array_filter( (array) $subjects, 'strlen' ) );
	$subject  = secupress_firewall_learning_subject_sample( $subject );
	if ( '' === $subject ) {
		return array_slice( $subjects, -10 );
	}
	$subjects = array_values( array_diff( $subjects, [ $subject ] ) );
	$subjects[] = $subject;
	return array_slice( $subjects, -10 );
}

/**
 * Get NG exceptions.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @return (array)
 */
function secupress_firewall_learning_get_exceptions() {
	$exceptions = get_site_option( SECUPRESS_FIREWALL_NG_EXCEPTIONS, [] );
	return is_array( $exceptions ) ? $exceptions : [];
}

/**
 * Save NG exceptions.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (array) $exceptions
 */
function secupress_firewall_learning_set_exceptions( $exceptions ) {
	update_site_option( SECUPRESS_FIREWALL_NG_EXCEPTIONS, is_array( $exceptions ) ? $exceptions : [] );
}

/**
 * Get aggregated Learning Mode hits.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @return (array)
 */
function secupress_firewall_learning_get_hits() {
	$hits = get_site_option( SECUPRESS_FIREWALL_LEARNING_HITS, [] );
	return is_array( $hits ) ? $hits : [];
}

/**
 * Save aggregated Learning Mode hits.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (array) $hits
 */
function secupress_firewall_learning_set_hits( $hits ) {
	update_site_option( SECUPRESS_FIREWALL_LEARNING_HITS, is_array( $hits ) ? $hits : [] );
}

/**
 * Tell if at least one observed signature is still undecided.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @return (bool)
 */
function secupress_firewall_learning_has_pending_hits() {
	foreach ( secupress_firewall_learning_get_hits() as $hit ) {
		if ( empty( $hit['decision'] ) || 'pending' === $hit['decision'] ) {
			return true;
		}
	}
	return false;
}

/**
 * NG patterns observed during Learning Mode. Any other pattern blocks.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @return (array)
 */
function secupress_firewall_ng_watch_patterns() {
	return [
		'([a-z0-9]{2000,})',
		'([a-z0-9]{4000,})',
		'(<|>|\\\'|%0A|%0D|%27|%00)',
		'(<|%0a|%0d|%27|%3c|%3e|%00|0x00|\\\\\\x22|\\\\\\")',
		'(ahrefs|archiver|curl|libwww-perl|pycurl|scan)',
		'(f?ckfinder|f?ckeditor|fullclick)',
		'(/)(f?ckfinder|fck/|fckeditor|fullclick)',
	];
}

/**
 * Get the severity of a NG regex pattern.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (string) $slug
 * @param (string) $pattern
 *
 * @return (string) `critical` or `calibratable`.
 */
function secupress_firewall_ng_get_severity( $slug, $pattern ) {
	$severity = 'critical';
	$pattern  = (string) $pattern;
	if ( in_array( $pattern, secupress_firewall_ng_watch_patterns(), true ) ) {
		$severity = 'calibratable';
	}

	/**
	 * Filters the NG pattern severity.
	 *
	 * @since 2.7.1
	 * @author Julio Potier
	 *
	 * @param (string) $severity `critical` or `calibratable`.
	 * @param (string) $slug
	 * @param (string) $pattern
	 */
	$severity = apply_filters( 'secupress.firewall.regex_severity', $severity, $slug, $pattern );
	return 'critical' === $severity ? 'critical' : 'calibratable';
}

/**
 * Exception that applies to this pattern and this matched string.
 *
 * A decision without a match key is a legacy whole-pattern exception.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (string) $pattern_hash
 * @param (string) $match
 *
 * @return (array)
 */
function secupress_firewall_learning_exception_for_match( $pattern_hash, $match = '' ) {
	$exceptions = secupress_firewall_learning_get_exceptions();
	$match_key  = secupress_firewall_learning_match_key( $match );
	$legacy     = [];

	foreach ( $exceptions as $key => $exception ) {
		if ( ! is_array( $exception ) ) {
			continue;
		}
		$stored_hash = isset( $exception['pattern_hash'] ) ? $exception['pattern_hash'] : '';
		$stored_key  = isset( $exception['match_key'] ) ? $exception['match_key'] : '';
		if ( $stored_key && $stored_hash === $pattern_hash && $stored_key === $match_key ) {
			return $exception;
		}
		if ( ! $stored_key && $key === $pattern_hash ) {
			$legacy = $exception;
		}
	}

	return $legacy;
}

/**
 * Build an exception from a review hit.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (string) $hash
 * @param (array)  $hit
 * @param (string) $type
 * @param (array)  $uris
 *
 * @return (array)
 */
function secupress_firewall_learning_exception_from_hit( $hash, $hit, $type, $uris = [] ) {
	$pattern_hash = isset( $hit['pattern_hash'] ) ? $hit['pattern_hash'] : '';
	$match_key    = isset( $hit['match_key'] ) ? $hit['match_key'] : '';
	if ( ! $pattern_hash && ! $match_key ) {
		$pattern_hash = $hash;
	}
	return [
		'type'         => $type,
		'uris'         => $uris,
		'slug'         => isset( $hit['slug'] ) ? $hit['slug'] : '',
		'pattern_hash' => $pattern_hash,
		'match_key'    => $match_key,
	];
}

/**
 * Tell if a local exception should skip this NG pattern for the current request.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (string) $hash  Pattern hash.
 * @param (string) $match Matched string. Empty keeps a legacy whole-pattern exception.
 *
 * @return (bool)
 */
function secupress_firewall_ng_exception_applies( $hash, $match = '' ) {
	$exception = secupress_firewall_learning_exception_for_match( $hash, $match );
	if ( ! $exception ) {
		return false;
	}
	$type = isset( $exception['type'] ) ? $exception['type'] : '';
	if ( 'allow_site' === $type ) {
		return true;
	}
	if ( 'allow_uri' !== $type ) {
		return false;
	}
	$current = secupress_firewall_learning_current_path();
	$uris    = isset( $exception['uris'] ) ? (array) $exception['uris'] : [];
	foreach ( $uris as $prefix ) {
		$prefix = secupress_firewall_learning_sanitize_path_prefix( $prefix );
		if ( '' === $prefix || '/' === $prefix ) {
			continue;
		}
		if ( secupress_firewall_learning_path_matches_prefix( $current, $prefix ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Current request path (no query string).
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @return (string)
 */
function secupress_firewall_learning_current_path() {
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
	$uri = strtok( $uri, '?' );
	return secupress_firewall_learning_sanitize_path_prefix( $uri );
}

/**
 * Sanitize a path prefix used as a URI exception.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (string) $uri
 *
 * @return (string)
 */
function secupress_firewall_learning_sanitize_path_prefix( $uri ) {
	$uri = wp_unslash( (string) $uri );
	$uri = strtok( $uri, '?' );
	$path = wp_parse_url( $uri, PHP_URL_PATH );
	if ( ! is_string( $path ) || '' === $path ) {
		$path = preg_replace( '#^https?://[^/]+#i', '', $uri );
	}
	$path = '/' . ltrim( (string) $path, '/' );
	$path = preg_replace( '#/+#', '/', $path );
	if ( '/' !== $path ) {
		$path = untrailingslashit( $path );
	}
	return $path;
}

/**
 * Tell if a path matches an allowed prefix.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (string) $path
 * @param (string) $prefix
 *
 * @return (bool)
 */
function secupress_firewall_learning_path_matches_prefix( $path, $prefix ) {
	if ( $path === $prefix ) {
		return true;
	}
	$len = strlen( $prefix );
	if ( $len < 1 || 0 !== strpos( $path, $prefix ) ) {
		return false;
	}
	$next = isset( $path[ $len ] ) ? $path[ $len ] : '';
	return '' === $next || '/' === $next;
}

add_filter( 'secupress.block.enforce', 'secupress_firewall_learning_maybe_observe', 10, 5 );
/**
 * Observe instead of blocking when Learning Mode is running on a calibratable NG rule.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (bool)   $enforce
 * @param (string) $module
 * @param (string) $ip
 * @param (array)  $args
 * @param (string) $block_id
 *
 * @return (bool)
 */
function secupress_firewall_learning_maybe_observe( $enforce, $module, $ip, $args, $block_id ) {
	unset( $module, $ip, $block_id );
	if ( ! $enforce ) {
		return false;
	}
	if ( empty( $args['source'] ) || 'ng' !== $args['source'] ) {
		return true;
	}
	if ( empty( $args['severity'] ) || 'calibratable' !== $args['severity'] ) {
		return true;
	}
	$hash  = isset( $args['pattern_hash'] ) ? $args['pattern_hash'] : '';
	$match = isset( $args['match'] ) ? $args['match'] : '';
	if ( $hash ) {
		$exception = secupress_firewall_learning_exception_for_match( $hash, $match );
		if ( ! empty( $exception['type'] ) && 'force_enforce' === $exception['type'] ) {
			return true;
		}
	}
	if ( ! secupress_firewall_learning_is_running() ) {
		return true;
	}
	return false;
}

add_action( 'secupress.block.observed', 'secupress_firewall_learning_queue_hit', 10, 4 );
/**
 * Queue an observed hit for the shutdown flush.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (string) $module
 * @param (string) $ip
 * @param (array)  $args
 * @param (string) $block_id
 */
function secupress_firewall_learning_queue_hit( $module, $ip, $args, $block_id ) {
	if ( empty( $args['source'] ) || 'ng' !== $args['source'] || empty( $args['pattern_hash'] ) ) {
		return;
	}

	$queue = secupress_cache_data( 'firewall_learning_queue' );
	if ( ! is_array( $queue ) ) {
		$queue = [];
		add_action( 'shutdown', 'secupress_firewall_learning_flush_hits' );
	}

	$slug    = isset( $args['slug'] ) ? $args['slug'] : '';
	$pattern = isset( $args['pattern'] ) ? $args['pattern'] : '';
	$match   = isset( $args['match'] ) ? (string) $args['match'] : '';
	$queue[] = [
		'hash'         => secupress_firewall_learning_hit_hash( $slug, $pattern, $match ),
		'pattern_hash' => $args['pattern_hash'],
		'slug'         => $slug,
		'pattern'      => $pattern,
		'match'        => $match,
		'match_key'    => secupress_firewall_learning_match_key( $match ),
		'subject'      => isset( $args['subject'] ) ? (string) $args['subject'] : '',
		'block_id'     => $block_id ? $block_id : $module,
		'ip'           => $ip,
		'uri'          => secupress_firewall_learning_uri_sample(),
	];
	secupress_cache_data( 'firewall_learning_queue', $queue );
}

/**
 * A sanitized URI sample for the review UI.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @return (string)
 */
function secupress_firewall_learning_uri_sample() {
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
	$uri = wp_make_link_relative( $uri );
	if ( '' === $uri ) {
		$uri = '/';
	}
	$parts = wp_parse_url( $uri );
	$path  = isset( $parts['path'] ) ? $parts['path'] : '/';
	$query = [];
	if ( ! empty( $parts['query'] ) ) {
		parse_str( $parts['query'], $query );
		foreach ( [ 'session', 'auth', 'token', 'password', 'psswrd', 'pass', 'pwd', 'pw', 'user_pass', 'edd_user_pass' ] as $key ) {
			if ( isset( $query[ $key ] ) ) {
				$query[ $key ] = '***';
			}
		}
		foreach ( $query as $key => $value ) {
			if ( is_array( $value ) ) {
				$query[ $key ] = '***';
				continue;
			}
			$value = (string) $value;
			if ( strlen( $value ) > 80 ) {
				$query[ $key ] = substr( $value, 0, 80 ) . '…';
			}
		}
	}
	$sample = $path;
	if ( $query ) {
		$sample .= '?' . str_replace( '%7E', '~', rawurldecode( http_build_query( $query ) ) );
	}
	if ( strlen( $sample ) > 250 ) {
		$sample = substr( $sample, 0, 250 ) . '…';
	}
	return $sample;
}

add_action( 'shutdown', 'secupress_firewall_learning_count_request', 20 );
/**
 * Count one PHP request toward the Learning Mode sample.
 *
 * @since 2.7.1
 * @author Julio Potier
 */
function secupress_firewall_learning_count_request() {
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX && isset( $_REQUEST['action'] ) && 'secupress-learning-progress' === $_REQUEST['action'] ) {
		return;
	}
	$state = get_site_option( SECUPRESS_FIREWALL_LEARNING, [] );
	if ( ! is_array( $state ) || empty( $state['status'] ) || 'running' !== $state['status'] ) {
		return;
	}
	if ( ! empty( $state['ends'] ) && time() >= (int) $state['ends'] ) {
		return;
	}
	$key           = is_admin() ? 'requests_admin' : 'requests_front';
	$state[ $key ] = isset( $state[ $key ] ) ? (int) $state[ $key ] + 1 : 1;
	secupress_firewall_learning_set_state( $state );
}


/**
 * Hidden URL flag to show the review UI before the sample is complete.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @return (bool)
 */
function secupress_firewall_learning_bypass_sample() {
	return isset( $_GET['expertmode'] ) && '1' === (string) wp_unslash( $_GET['expertmode'] );
}

/**
 * Progress toward the 20 hour and 200 request advice sample.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (array|null) $state
 *
 * @return (array)
 */
function secupress_firewall_learning_progress( $state = null ) {
	if ( ! is_array( $state ) ) {
		$state = secupress_firewall_learning_get_state();
	}
	$started        = isset( $state['started'] ) ? (int) $state['started'] : 0;
	$elapsed        = $started ? max( 0, time() - $started ) : 0;
	$requests_front = isset( $state['requests_front'] ) ? max( 0, (int) $state['requests_front'] ) : 0;
	$requests_admin = isset( $state['requests_admin'] ) ? max( 0, (int) $state['requests_admin'] ) : 0;
	$requests       = $requests_front + $requests_admin;
	$time_goal      = 20 * HOUR_IN_SECONDS;
	$req_goal       = 200;
	$time_part      = min( 90, ( $elapsed / $time_goal ) * 90 );
	$req_part       = min( 10, ( $requests / $req_goal ) * 10 );
	$ready          = $elapsed >= $time_goal && $requests >= $req_goal;
	if ( secupress_firewall_learning_bypass_sample() ) {
		$ready = true;
	}
	$percent        = $ready ? 100 : round( min( 100, $time_part + $req_part ), 1 );

	return [
		'ready'          => $ready,
		'percent'        => $percent,
		'requests'       => $requests,
		'requests_front' => $requests_front,
		'requests_admin' => $requests_admin,
		'elapsed'        => $elapsed,
	];
}

add_action( 'wp_ajax_secupress-learning-progress', 'secupress_firewall_learning_progress_ajax' );
/**
 * Ajax payload for the advice progress bar.
 *
 * @since 2.7.1
 * @author Julio Potier
 */
function secupress_firewall_learning_progress_ajax() {
	if ( ! current_user_can( secupress_get_capability() ) ) {
		wp_send_json_error();
	}
	check_ajax_referer( 'secupress-learning-progress' );
	$progress = secupress_firewall_learning_progress();
	wp_send_json_success( [
		'ready'       => (bool) $progress['ready'],
		'percent'     => (float) $progress['percent'],
		'requests'    => (int) $progress['requests'],
		'description' => secupress_firewall_learning_wait_message(),
	] );
}

/**
 * A short random line while Learning Mode is still gathering traffic.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @return (string) HTML.
 */
function secupress_firewall_learning_wait_message() {
	// if ( secupress_is_expert_mode() ) {
	// 	return '🧠 ' . esc_html__( 'Learning mode is running. Please wait while we gather data.', 'secupress' );
	// }
	$messages = [
		[ 'emoji' => '☕',  'text' => __( 'Grabbing a coffee…', 'secupress' ) ],
		[ 'emoji' => '🚶', 'text' => __( 'Stretching those legs…', 'secupress' ) ],
		[ 'emoji' => '🌱', 'text' => __( 'Watering a plant…', 'secupress' ) ],
		[ 'emoji' => '📝', 'text' => __( 'Taking notes…', 'secupress' ) ],
		[ 'emoji' => '🍪', 'text' => __( 'Reaching for a biscuit…', 'secupress' ) ],
		[ 'emoji' => '👀', 'text' => __( 'Watching the traffic…', 'secupress' ) ],
		[ 'emoji' => '🐱', 'text' => __( 'Petting the cat…', 'secupress' ) ],
		[ 'emoji' => '⏳', 'text' => __( 'Counting the minutes…', 'secupress' ) ],
		[ 'emoji' => '🍵', 'text' => __( 'Brewing some tea…', 'secupress' ) ],
		[ 'emoji' => '🔮', 'text' => __( 'Consulting the crystal ball…', 'secupress' ) ],
		[ 'emoji' => '✏️', 'text' => __( 'Sharpening a pencil…', 'secupress' ) ],
		[ 'emoji' => '🐟', 'text' => __( 'Feeding the goldfish…', 'secupress' ) ],
		[ 'emoji' => '📻', 'text' => __( 'Tuning the radio…', 'secupress' ) ],
		[ 'emoji' => '🐿️', 'text' => __( 'Chasing a squirrel…', 'secupress' ) ],
		[ 'emoji' => '🛡️', 'text' => __( 'Polishing the shield…', 'secupress' ) ],
		[ 'emoji' => '🪟', 'text' => __( 'Opening a window…', 'secupress' ) ],
		[ 'emoji' => '🎵', 'text' => __( 'Humming a little tune…', 'secupress' ) ],
		[ 'emoji' => '🌤️', 'text' => __( 'Checking the weather…', 'secupress' ) ],
		[ 'emoji' => '💃', 'text' => __( 'Doing a tiny dance…', 'secupress' ) ],
		[ 'emoji' => '👋', 'text' => __( 'Waving hello…', 'secupress' ) ],
		[ 'emoji' => '👥', 'text' => __( 'Hanging out with friends…', 'secupress' ) ],
		[ 'emoji' => '🎉', 'text' => __( 'Celebrating the moment…', 'secupress' ) ],
		[ 'emoji' => '🎓', 'text' => __( 'Graduating from school…', 'secupress' ) ],
		[ 'emoji' => '💻', 'text' => __( 'Working on the computer…', 'secupress' ) ],
		[ 'emoji' => '🎥', 'text' => __( 'Watching a movie…', 'secupress' ) ],
		[ 'emoji' => '🎤', 'text' => __( 'Singing a song…', 'secupress' ) ],
		[ 'emoji' => '🎹', 'text' => __( 'Playing a song on the piano…', 'secupress' ) ],
		[ 'emoji' => '🎮', 'text' => __( 'Playing a video game…', 'secupress' ) ],
		[ 'emoji' => '🌐', 'text' => __( 'Browsing the web…', 'secupress' ) ],
		[ 'emoji' => '🔍', 'text' => __( 'Searching the web…', 'secupress' ) ],
		[ 'emoji' => '🔗', 'text' => __( 'Following a link…', 'secupress' ) ],
		[ 'emoji' => '📊', 'text' => __( 'Analyzing the data…', 'secupress' ) ],
		[ 'emoji' => '🤔', 'text' => __( 'Making a decision…', 'secupress' ) ],
		[ 'emoji' => '💭', 'text' => __( 'Thinking about the future…', 'secupress' ) ],
		[ 'emoji' => '📝', 'text' => __( 'Planning the next step…', 'secupress' ) ],
		[ 'emoji' => '💪', 'text' => __( 'Preparing for the next challenge…', 'secupress' ) ],
		[ 'emoji' => '🧠', 'text' => __( 'Reflecting on the past…', 'secupress' ) ],
		[ 'emoji' => '💡', 'text' => __( 'Coming up with a solution…', 'secupress' ) ],
		[ 'emoji' => '🕒', 'text' => __( 'Waiting for the results…', 'secupress' ) ],
		[ 'emoji' => '💤', 'text' => __( 'Taking a break…', 'secupress' ) ],
		[ 'emoji' => '🧪', 'text' => __( 'Running a test…', 'secupress' ) ],
		[ 'emoji' => '🤖', 'text' => __( 'Talking to a robot…', 'secupress' ) ],
		[ 'emoji' => '🤷', 'text' => __( 'Procrastinating…', 'secupress' ) ],
		[ 'emoji' => '⁉️', 'text' => __( 'Doing something, I guess…', 'secupress' ) ],
		[ 'emoji' => '🌊', 'text' => __( 'Erasing the whole computer…', 'secupress' ) ],
		[ 'emoji' => '💸', 'text' => __( 'Buying something on the internet…', 'secupress' ) ],
		[ 'emoji' => '💩', 'text' => __( 'What am I doing here?…', 'secupress' ) ],
	];
	$user = wp_get_current_user();
	if ( $user->exists() && '' !== $user->display_name ) {
		$messages[] = [
			'avatar' => true,
			'text'   => sprintf(
				/* translators: %s: user display name */
				__( '%sing the things…', 'secupress' ),
				esc_html( $user->display_name )
			),
		];
	}
	$message = $messages[ array_rand( $messages ) ];
	if ( ! empty( $message['avatar'] ) ) {
		$prefix = get_avatar( $user, 18, '', $user->display_name, [ 'class' => 'secupress-learning-avatar', 'force_display' => true ] );
	} else {
		$prefix = esc_html( $message['emoji'] );
	}
	return $prefix . ' ' . esc_html( $message['text'] );
}

/**
 * Flush queued hits into the site option (once per request).
 *
 * @since 2.7.1
 * @author Julio Potier
 */
function secupress_firewall_learning_flush_hits() {
	$queue = secupress_cache_data( 'firewall_learning_queue' );
	secupress_cache_data( 'firewall_learning_queue', null );
	if ( empty( $queue ) || ! is_array( $queue ) ) {
		return;
	}

	$hits        = secupress_firewall_learning_get_hits();
	$code        = (int) http_response_code();
	$is_admin    = function_exists( 'is_user_logged_in' ) && is_user_logged_in() && current_user_can( 'manage_options' );
	$max_buckets = 100;

	foreach ( $queue as $item ) {
		$hash = $item['hash'];
		if ( ! isset( $hits[ $hash ] ) ) {
			if ( count( $hits ) >= $max_buckets ) {
				continue;
			}
			$hits[ $hash ] = [
				'slug'                 => $item['slug'],
				'block_id'             => $item['block_id'],
				'pattern'              => substr( (string) $item['pattern'], 0, 180 ),
				'pattern_hash'         => $item['pattern_hash'],
				'match'                => secupress_firewall_learning_match_sample( $item['match'] ),
				'match_key'            => $item['match_key'],
				'subjects'             => [],
				'hits'                 => 0,
				'hits_front'           => 0,
				'hits_admin'           => 0,
				'unique_ips'           => [],
				'logged_in_admin_hits' => 0,
				'server_ip_hits'       => 0,
				'http_200'             => 0,
				'http_other'           => 0,
				'uris'                 => [],
				'decision'             => 'pending',
			];
		}
		if ( 'pending' !== $hits[ $hash ]['decision'] ) {
			continue;
		}
		++$hits[ $hash ]['hits'];
		if ( is_admin() ) {
			if ( ! isset( $hits[ $hash ]['hits_admin'] ) ) {
				$hits[ $hash ]['hits_admin'] = 0;
			}
			++$hits[ $hash ]['hits_admin'];
		} else {
			if ( ! isset( $hits[ $hash ]['hits_front'] ) ) {
				$hits[ $hash ]['hits_front'] = 0;
			}
			++$hits[ $hash ]['hits_front'];
		}
		$ip_key = substr( md5( (string) $item['ip'] ), 0, 8 );
		if ( count( $hits[ $hash ]['unique_ips'] ) < 50 ) {
			$hits[ $hash ]['unique_ips'][ $ip_key ] = 1;
		}
		if ( $is_admin ) {
			++$hits[ $hash ]['logged_in_admin_hits'];
		}
		if ( secupress_firewall_learning_is_server_ip( $item['ip'] ) ) {
			++$hits[ $hash ]['server_ip_hits'];
		}
		if ( 200 === $code ) {
			++$hits[ $hash ]['http_200'];
		} else {
			++$hits[ $hash ]['http_other'];
		}
		if ( count( $hits[ $hash ]['uris'] ) < 5 && ! in_array( $item['uri'], $hits[ $hash ]['uris'], true ) ) {
			$hits[ $hash ]['uris'][] = $item['uri'];
		}
		if ( ! empty( $item['subject'] ) ) {
			$hits[ $hash ]['subjects'] = secupress_firewall_learning_push_subject( isset( $hits[ $hash ]['subjects'] ) ? $hits[ $hash ]['subjects'] : [], $item['subject'] );
		}
	}

	secupress_firewall_learning_set_hits( $hits );
}

/**
 * Start Learning Mode.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (int)    $duration Days, 1–7.
 * @param (string) $trigger  `manual` or `plugin`.
 *
 * @return (bool)
 */
function secupress_firewall_learning_start( $duration = 7, $trigger = 'manual' ) {
	if ( ! secupress_firewall_ng_is_enabled() ) {
		return false;
	}
	$duration = secupress_firewall_learning_sanitize_duration( $duration );
	$now      = time();
	secupress_firewall_learning_set_state( [
		'status'         => 'running',
		'started'        => $now,
		'ends'           => $now + ( $duration * DAY_IN_SECONDS ),
		'duration'       => $duration,
		'trigger'        => in_array( $trigger, [ 'manual', 'plugin', 'core' ], true ) ? $trigger : 'manual',
		'requests_front' => 0,
		'requests_admin' => 0,
	] );
	secupress_firewall_learning_set_hits( [] );
	secupress_firewall_learning_set_last_started( $now );
	secupress_firewall_learning_snapshot_now();
	secupress_firewall_learning_clear_schedule();
	wp_schedule_single_event( $now + ( $duration * DAY_IN_SECONDS ), 'secupress_firewall_learning_end' );
	return true;
}

/**
 * Extend a running Learning Mode period.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (int) $duration Extra days, 1–7. Added to the current end. Caps at 14 days from the original start.
 *
 * @return (bool)
 */
function secupress_firewall_learning_extend( $duration = 7 ) {
	$state = get_site_option( SECUPRESS_FIREWALL_LEARNING, [] );
	if ( empty( $state['status'] ) || 'running' !== $state['status'] ) {
		return false;
	}
	$duration = secupress_firewall_learning_sanitize_duration( $duration );
	$max_ends = (int) $state['started'] + ( 14 * DAY_IN_SECONDS );
	$base     = max( time(), (int) $state['ends'] );
	$new_ends = min( $base + ( $duration * DAY_IN_SECONDS ), $max_ends );
	if ( $new_ends <= (int) $state['ends'] ) {
		return false;
	}
	$state['ends']     = $new_ends;
	$state['duration'] = $duration;
	secupress_firewall_learning_set_state( $state );
	secupress_firewall_learning_clear_schedule();
	wp_schedule_single_event( $new_ends, 'secupress_firewall_learning_end' );
	return true;
}

add_action( 'secupress_firewall_learning_end', 'secupress_firewall_learning_cron_end' );
/**
 * Cron callback: end Learning Mode with auto-allow.
 *
 * @since 2.7.1
 * @author Julio Potier
 */
function secupress_firewall_learning_cron_end() {
	secupress_firewall_learning_end( true );
}

add_action( 'admin_init', 'secupress_firewall_learning_maybe_expire' );
/**
 * Expire a running period from admin if the cron was missed.
 *
 * @since 2.7.1
 * @author Julio Potier
 */
function secupress_firewall_learning_maybe_expire() {
	secupress_firewall_learning_maybe_init_snapshots();
	$state = get_site_option( SECUPRESS_FIREWALL_LEARNING, [] );
	if ( ! is_array( $state ) || empty( $state['status'] ) || 'running' !== $state['status'] ) {
		return;
	}
	if ( (int) $state['ends'] && time() >= (int) $state['ends'] ) {
		secupress_firewall_learning_end( true );
	}
}

/**
 * End Learning Mode.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (bool) $auto_allow Run the strict URI auto-allow.
 * @param (bool) $skip       Mark remaining pending hits as force_enforce, no review.
 * @param (bool) $notify     Send the end email/notice.
 */
function secupress_firewall_learning_end( $auto_allow = true, $skip = false, $notify = true ) {
	$state = get_site_option( SECUPRESS_FIREWALL_LEARNING, [] );
	if ( ! is_array( $state ) || empty( $state['status'] ) || 'running' !== $state['status'] ) {
		return;
	}

	secupress_firewall_learning_clear_schedule();

	if ( $skip ) {
		secupress_firewall_learning_force_enforce_pending();
		$state['status'] = 'off';
		$state['ends']   = time();
		secupress_firewall_learning_set_state( $state );
		return;
	}

	$auto_count = 0;
	if ( $auto_allow ) {
		$auto_count = secupress_firewall_learning_auto_allow();
	}

	$hits     = secupress_firewall_learning_get_hits();
	$pending  = 0;
	foreach ( $hits as $hit ) {
		if ( isset( $hit['decision'] ) && 'pending' === $hit['decision'] ) {
			++$pending;
		}
	}

	$state['status'] = $pending ? 'review' : 'off';
	$state['ends']   = time();
	secupress_firewall_learning_set_state( $state );

	if ( $notify ) {
		secupress_firewall_learning_notify_end( $pending, $auto_count );
	}
}

/**
 * Clear the scheduled end event.
 *
 * @since 2.7.1
 * @author Julio Potier
 */
function secupress_firewall_learning_clear_schedule() {
	wp_clear_scheduled_hook( 'secupress_firewall_learning_end' );
}

/**
 * Stop Learning Mode because the 8G pack was turned off.
 *
 * @since 2.7.1
 * @author Julio Potier
 */
function secupress_firewall_learning_deactivate() {
	secupress_firewall_learning_clear_schedule();
	$state = get_site_option( SECUPRESS_FIREWALL_LEARNING, [] );
	if ( ! is_array( $state ) || empty( $state['status'] ) || 'off' === $state['status'] ) {
		return;
	}
	$state['status'] = 'off';
	$state['ends']   = time();
	secupress_firewall_learning_set_state( $state );
}

/**
 * Auto-allow URI prefixes that match the strict criteria.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @return (int) Number of auto-allowed signatures.
 */
function secupress_firewall_learning_auto_allow() {
	$hits        = secupress_firewall_learning_get_hits();
	$exceptions  = secupress_firewall_learning_get_exceptions();
	$auto_count  = 0;

	foreach ( $hits as $hash => $hit ) {
		if ( empty( $hit['decision'] ) || 'pending' !== $hit['decision'] ) {
			continue;
		}
		$prefix = secupress_firewall_learning_auto_allow_prefix( $hit );
		if ( ! $prefix ) {
			continue;
		}
		$hits[ $hash ]['decision'] = 'auto_allowed';
		$exceptions[ $hash ]       = secupress_firewall_learning_exception_from_hit( $hash, $hit, 'allow_uri', [ $prefix ] );
		++$auto_count;
	}

	if ( $auto_count ) {
		secupress_firewall_learning_set_hits( $hits );
		secupress_firewall_learning_set_exceptions( $exceptions );
	}

	return $auto_count;
}

/**
 * Compute a safe URI prefix for auto-allow, or empty string if not safe.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (array) $hit
 *
 * @return (string)
 */
function secupress_firewall_learning_auto_allow_prefix( $hit ) {
	if ( (int) $hit['logged_in_admin_hits'] < 2 ) {
		return '';
	}
	$known = (int) $hit['http_200'] + (int) $hit['http_other'];
	if ( $known < 1 ) {
		return '';
	}
	if ( ( (int) $hit['http_200'] / $known ) < 0.7 ) {
		return '';
	}
	$unique = isset( $hit['unique_ips'] ) && is_array( $hit['unique_ips'] ) ? count( $hit['unique_ips'] ) : 0;
	if ( $unique > 3 ) {
		return '';
	}
	$uris = isset( $hit['uris'] ) ? (array) $hit['uris'] : [];
	if ( ! $uris ) {
		return '';
	}
	$prefix = secupress_firewall_learning_common_path_prefix( $uris );
	if ( strlen( $prefix ) < 8 || '/' === $prefix ) {
		return '';
	}
	return $prefix;
}

/**
 * Longest common path prefix of URI samples.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (array) $uris
 *
 * @return (string)
 */
function secupress_firewall_learning_common_path_prefix( $uris ) {
	$paths = [];
	foreach ( $uris as $uri ) {
		$paths[] = secupress_firewall_learning_sanitize_path_prefix( $uri );
	}
	$paths = array_values( array_filter( $paths ) );
	if ( ! $paths ) {
		return '';
	}
	$prefix = $paths[0];
	foreach ( $paths as $path ) {
		while ( '' !== $prefix && '/' !== $prefix && 0 !== strpos( $path, $prefix ) ) {
			$prefix = dirname( $prefix );
			if ( '.' === $prefix || '\\' === $prefix ) {
				$prefix = '/';
			}
			$prefix = secupress_firewall_learning_sanitize_path_prefix( $prefix );
		}
		if ( '/' === $prefix || '' === $prefix ) {
			return '';
		}
	}
	return $prefix;
}

/**
 * Mark every pending hit as force_enforce.
 *
 * @since 2.7.1
 * @author Julio Potier
 */
function secupress_firewall_learning_force_enforce_pending() {
	$hits        = secupress_firewall_learning_get_hits();
	$exceptions  = secupress_firewall_learning_get_exceptions();
	$changed     = false;

	foreach ( $hits as $hash => $hit ) {
		if ( empty( $hit['decision'] ) || 'pending' !== $hit['decision'] ) {
			continue;
		}
		$hits[ $hash ]['decision'] = 'force_enforce';
		$exceptions[ $hash ]       = secupress_firewall_learning_exception_from_hit( $hash, $hit, 'force_enforce' );
		$changed = true;
	}

	if ( $changed ) {
		secupress_firewall_learning_set_hits( $hits );
		secupress_firewall_learning_set_exceptions( $exceptions );
	}
}

/**
 * Apply a manual decision on a signature.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (string) $hash
 * @param (string) $decision `allow_site`, `allow_uri`, `force_enforce`.
 * @param (string) $uri      Path prefix when `$decision` is `allow_uri`.
 *
 * @return (bool)
 */
function secupress_firewall_learning_apply_decision( $hash, $decision, $uri = '' ) {
	$hash = sanitize_key( $hash );
	if ( 32 !== strlen( $hash ) ) {
		return false;
	}
	if ( ! in_array( $decision, [ 'allow_site', 'allow_uri', 'force_enforce' ], true ) ) {
		return false;
	}

	$hits = secupress_firewall_learning_get_hits();
	if ( empty( $hits[ $hash ] ) ) {
		return false;
	}

	$uris = [];
	if ( 'allow_uri' === $decision ) {
		$prefix = secupress_firewall_learning_sanitize_path_prefix( $uri );
		if ( '' === $prefix || '/' === $prefix ) {
			if ( ! empty( $hits[ $hash ]['uris'][0] ) ) {
				$prefix = secupress_firewall_learning_sanitize_path_prefix( $hits[ $hash ]['uris'][0] );
			}
		}
		if ( '' === $prefix || '/' === $prefix ) {
			return false;
		}
		$uris = [ $prefix ];
	}

	$hits[ $hash ]['decision'] = $decision;
	secupress_firewall_learning_set_hits( $hits );

	$exceptions          = secupress_firewall_learning_get_exceptions();
	$exceptions[ $hash ] = secupress_firewall_learning_exception_from_hit( $hash, $hits[ $hash ], $decision, $uris );
	secupress_firewall_learning_set_exceptions( $exceptions );
	secupress_firewall_learning_get_state();
	return true;
}

/**
 * Tell if an IP is this server.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (string) $ip
 *
 * @return (bool)
 */
function secupress_firewall_learning_is_server_ip( $ip ) {
	$ip = (string) $ip;
	if ( '127.0.0.1' === $ip || '::1' === $ip ) {
		return true;
	}
	$server = isset( $_SERVER['SERVER_ADDR'] ) ? (string) $_SERVER['SERVER_ADDR'] : '';
	return '' !== $server && $server === $ip;
}

/**
 * Suggest allowing or blocking a signature from its volume, administrators, and server IP.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (array) $hit
 *
 * @return (array) `action` is `allow` or `block`. Empty when there is not enough sample yet.
 */
function secupress_firewall_learning_recommend( $hit ) {
	$progress = secupress_firewall_learning_progress();
	if ( empty( $progress['ready'] ) ) {
		return [];
	}
	if ( ! isset( $hit['hits_front'] ) && ! isset( $hit['hits_admin'] ) ) {
		return secupress_firewall_learning_recommend_from_signals( $hit );
	}

	$hits       = isset( $hit['hits'] ) ? max( 0, (int) $hit['hits'] ) : 0;
	$hits_front = isset( $hit['hits_front'] ) ? max( 0, (int) $hit['hits_front'] ) : 0;
	$hits_admin = isset( $hit['hits_admin'] ) ? max( 0, (int) $hit['hits_admin'] ) : 0;
	$admins     = isset( $hit['logged_in_admin_hits'] ) ? max( 0, (int) $hit['logged_in_admin_hits'] ) : 0;
	$server     = isset( $hit['server_ip_hits'] ) ? max( 0, (int) $hit['server_ip_hits'] ) : 0;
	if ( $hits < 1 ) {
		return [];
	}
	$admins = min( $admins, $hits );
	$server = min( $server, $hits );

	if ( ( $hits_admin / $hits ) >= 0.5 ) {
		if ( ( $admins / $hits ) >= 0.5 ) {
			return [
				'action' => 'allow',
				'reason' => __( 'Most of these admin requests came from an administrator.', 'secupress' ),
			];
		}
		if ( ( $server / $hits ) >= 0.5 ) {
			return [
				'action' => 'allow',
				'reason' => __( 'Most of these admin requests came from the server.', 'secupress' ),
			];
		}
		return [
			'action' => 'block',
			'reason' => __( 'These admin requests did not come from an administrator or the server.', 'secupress' ),
		];
	}

	if ( $progress['requests_front'] < 1 ) {
		return secupress_firewall_learning_recommend_from_signals( $hit );
	}
	$front_ratio = $hits_front / $progress['requests_front'];
	if ( $front_ratio >= 0.05 ) {
		return [
			'action' => 'allow',
			'reason' => __( 'This string is a normal part of front traffic.', 'secupress' ),
		];
	}
	if ( $front_ratio <= 0.005 ) {
		return [
			'action' => 'block',
			'reason' => __( 'This string is rare on the front.', 'secupress' ),
		];
	}
	return secupress_firewall_learning_recommend_from_signals( $hit );
}

/**
 * Suggest from administrator and server IP signals only.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (array) $hit
 *
 * @return (array)
 */
function secupress_firewall_learning_recommend_from_signals( $hit ) {
	$hits   = isset( $hit['hits'] ) ? (int) $hit['hits'] : 0;
	$admins = isset( $hit['logged_in_admin_hits'] ) ? (int) $hit['logged_in_admin_hits'] : 0;
	$server = isset( $hit['server_ip_hits'] ) ? (int) $hit['server_ip_hits'] : 0;
	$hits   = max( 0, $hits );
	$admins = min( max( 0, $admins ), max( 1, $hits ) );
	$server = min( max( 0, $server ), max( 1, $hits ) );
	$base   = max( 1, $hits );
	$volume = min( 35, max( 0, $hits - 1 ) * 5 );
	$score  = $volume;
	$score += (int) round( 45 * ( $admins / $base ) );
	$score += (int) round( 40 * ( $server / $base ) );
	if ( ( $server / $base ) >= 0.5 ) {
		$score = max( $score, 40 );
	}
	if ( $score > 100 ) {
		$score = 100;
	}

	$action       = $score >= 40 ? 'allow' : 'block';
	$admin_ratio  = $admins / $base;
	$server_ratio = $server / $base;
	if ( 'allow' === $action && $server > 0 && $server_ratio >= 0.5 ) {
		$reason = __( 'Most requests came from the server.', 'secupress' );
	} elseif ( 'allow' === $action && $admins > 0 && $admin_ratio >= 0.5 ) {
		$reason = __( 'Most requests came from an administrator.', 'secupress' );
	} elseif ( 'allow' === $action ) {
		$reason = __( 'Seen often enough from an administrator or the server.', 'secupress' );
	} elseif ( $hits >= 5 ) {
		$reason = __( 'Seen often, but not from an administrator or the server.', 'secupress' );
	} else {
		$reason = __( 'Rare, and not from an administrator or the server.', 'secupress' );
	}

	return [
		'action' => $action,
		'score'  => $score,
		'reason' => $reason,
	];
}

/**
 * Human label for a NG data-file slug.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (string) $slug
 *
 * @return (string)
 */
function secupress_firewall_learning_slug_label( $slug ) {
	$labels = [
		'ng_query_string' => __( 'Query string', 'secupress' ),
		'ng_request_uri'  => __( 'Request URI', 'secupress' ),
		'ng_user_agent'   => __( 'User-Agent', 'secupress' ),
		'ng_remote_host'  => __( 'Remote host', 'secupress' ),
		'ng_http_referer' => __( 'Referer', 'secupress' ),
		'ng_http_cookie'  => __( 'Cookie', 'secupress' ),
	];
	return isset( $labels[ $slug ] ) ? $labels[ $slug ] : $slug;
}

/**
 * Tell if at least one NG submodule is active.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @return (bool)
 */
function secupress_firewall_ng_is_active() {
	return (bool) secupress_firewall_learning_get_active_ng_modules();
}

/**
 * Email + notice when Learning Mode ends.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (int) $pending
 * @param (int) $auto_count
 */
function secupress_firewall_learning_notify_end( $pending, $auto_count ) {
	$pending    = (int) $pending;
	$auto_count = (int) $auto_count;
	if ( ! $pending && ! $auto_count ) {
		return;
	}

	$url   = secupress_admin_url( 'modules', 'firewall' ) . '#row-learning-mode_learning';
	$parts = [];
	if ( $auto_count ) {
		$parts[] = sprintf(
			_n( '%s signature was auto-allowed for specific URLs.', '%s signatures were auto-allowed for specific URLs.', $auto_count, 'secupress' ),
			number_format_i18n( $auto_count )
		);
	}
	if ( $pending ) {
		$parts[] = sprintf(
			_n( '%s signature still needs your review.', '%s signatures still need your review.', $pending, 'secupress' ),
			number_format_i18n( $pending )
		);
	}
	$message = __( 'Firewall Learning Mode has ended.', 'secupress' ) . ' ' . implode( ' ', $parts );
	$notice = $message . ' <a href="' . esc_url( $url ) . '">' . __( 'Review signatures', 'secupress' ) . '</a>';

	set_site_transient( 'secupress_firewall_learning_ended_notice', $notice, WEEK_IN_SECONDS );

	$mail  = $message . "\n" . $url;
	secupress_send_mail( get_option( 'admin_email' ), __( '[###SITENAME###] Firewall Learning Mode has ended', 'secupress' ), $mail );
}

/**
 * Tell if the Learning Mode end notice still has something to show.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @return (bool)
 */
function secupress_firewall_learning_end_notice_has_work() {
	foreach ( secupress_firewall_learning_get_hits() as $hit ) {
		$decision = isset( $hit['decision'] ) ? $hit['decision'] : '';
		if ( 'pending' === $decision || 'auto_allowed' === $decision ) {
			return true;
		}
	}
	return false;
}

add_action( 'secupress.modules.activate_submodule', 'secupress_firewall_learning_on_ng_activation', 10, 2 );
/**
 * When an NG submodule is newly activated and Learning Mode is not running, propose it again.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (string) $submodule
 * @param (bool)   $is_active True if the sub-module was already active.
 */
function secupress_firewall_learning_on_ng_activation( $submodule, $is_active ) {
	if ( $is_active ) {
		return;
	}
	if ( ! in_array( $submodule, secupress_firewall_learning_ng_submodules(), true ) ) {
		return;
	}
	secupress_firewall_learning_maybe_init_snapshots( $submodule );
	if ( secupress_firewall_learning_is_running() ) {
		secupress_firewall_learning_remember_ng_module( $submodule );
		return;
	}
	if ( ! secupress_firewall_ng_is_enabled() ) {
		return;
	}
	$snapshot = secupress_firewall_learning_get_ng_snapshot();
	if ( $snapshot && in_array( $submodule, $snapshot, true ) ) {
		return;
	}
	if ( function_exists( 'secupress_reinit_notice' ) ) {
		secupress_reinit_notice( 'firewall-learning-mode-intro' );
	}
}

add_action( 'activated_plugin', 'secupress_firewall_learning_on_plugin_activation', 10, 2 );
/**
 * Propose a 2-day Learning Mode when a new third-party plugin is activated.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (string) $plugin
 * @param (bool)   $network_wide
 */
function secupress_firewall_learning_on_plugin_activation( $plugin, $network_wide = false ) {
	unset( $network_wide );
	secupress_firewall_learning_maybe_propose_for_plugin( $plugin );
}

add_action( 'upgrader_process_complete', 'secupress_firewall_learning_on_core_updated', 10, 2 );
/**
 * Propose Learning Mode again when WordPress itself is updated.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (object) $upgrader
 * @param (array)  $hook_extra
 */
function secupress_firewall_learning_on_core_updated( $upgrader, $hook_extra ) {
	unset( $upgrader );
	if ( ! is_array( $hook_extra ) || empty( $hook_extra['type'] ) || 'core' !== $hook_extra['type'] ) {
		return;
	}
	if ( empty( $hook_extra['action'] ) || 'update' !== $hook_extra['action'] ) {
		return;
	}
	if ( ! secupress_firewall_ng_is_enabled() || ! secupress_firewall_ng_is_active() ) {
		return;
	}
	$current = get_bloginfo( 'version' );
	$known   = secupress_firewall_learning_get_wp_version();
	if ( $known === $current ) {
		return;
	}
	if ( '' === $known && ! secupress_firewall_learning_get_last_started() ) {
		return;
	}
	update_site_option( 'secupress_firewall_learning_core_notice', [
		'version' => $current,
		'running' => secupress_firewall_learning_is_running() ? 1 : 0,
	] );
	if ( function_exists( 'secupress_reinit_notice' ) ) {
		secupress_reinit_notice( 'firewall-learning-mode-core-' . md5( $current ) );
	}
}

/**
 * Propose Learning Mode for a plugin that has never been seen active.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (string) $plugin Plugin file.
 */
function secupress_firewall_learning_maybe_propose_for_plugin( $plugin ) {
	$plugin = (string) $plugin;
	if ( ! $plugin || secupress_firewall_learning_is_own_plugin( $plugin ) ) {
		return;
	}
	if ( ! secupress_firewall_ng_is_enabled() || ! secupress_firewall_ng_is_active() ) {
		return;
	}
	secupress_firewall_learning_migrate_plugins_snapshot( $plugin );
	secupress_firewall_learning_maybe_init_snapshots( '', $plugin );
	$snapshot = secupress_firewall_learning_get_plugins_snapshot();
	if ( $snapshot && in_array( $plugin, $snapshot, true ) ) {
		return;
	}
	update_site_option( 'secupress_firewall_learning_plugin_notice', [
		'plugin'  => $plugin,
		'running' => secupress_firewall_learning_is_running() ? 1 : 0,
	] );
	if ( function_exists( 'secupress_reinit_notice' ) ) {
		secupress_reinit_notice( 'firewall-learning-mode-plugin-' . md5( $plugin ) );
	}
}

/**
 * Read the stored plugin proposal.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @return (array) `plugin` and `running`. Empty when nothing is stored.
 */
function secupress_firewall_learning_get_plugin_proposal() {
	$stored = get_site_option( 'secupress_firewall_learning_plugin_notice' );
	if ( is_string( $stored ) && '' !== $stored ) {
		return [
			'plugin'  => $stored,
			'running' => 0,
		];
	}
	if ( ! is_array( $stored ) || empty( $stored['plugin'] ) ) {
		return [];
	}
	return [
		'plugin'  => (string) $stored['plugin'],
		'running' => ! empty( $stored['running'] ) ? 1 : 0,
	];
}

/**
 * Mark a proposal as answered: remember the plugin, or store the WordPress version.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (string) $trigger `plugin` or `core`.
 */
function secupress_firewall_learning_accept_proposal( $trigger ) {
	if ( 'plugin' === $trigger ) {
		$proposal = secupress_firewall_learning_get_plugin_proposal();
		$plugin   = isset( $_REQUEST['plugin'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['plugin'] ) ) : '';
		if ( ! $plugin && $proposal ) {
			$plugin = $proposal['plugin'];
		}
		if ( $plugin && ! secupress_firewall_learning_is_own_plugin( $plugin ) ) {
			secupress_firewall_learning_remember_plugin( $plugin );
			if ( function_exists( 'secupress_dismiss_notice' ) ) {
				secupress_dismiss_notice( 'firewall-learning-mode-plugin-' . md5( $plugin ) );
			}
		}
		delete_site_option( 'secupress_firewall_learning_plugin_notice' );
		return;
	}
	if ( 'core' !== $trigger ) {
		return;
	}
	$stored  = get_site_option( 'secupress_firewall_learning_core_notice' );
	$version = ( is_array( $stored ) && ! empty( $stored['version'] ) ) ? (string) $stored['version'] : get_bloginfo( 'version' );
	secupress_firewall_learning_set_wp_version();
	if ( function_exists( 'secupress_dismiss_notice' ) ) {
		secupress_dismiss_notice( 'firewall-learning-mode-core-' . md5( $version ) );
	}
	delete_site_option( 'secupress_firewall_learning_core_notice' );
}

/**
 * Drop pending Learning Mode proposals after a new cycle starts.
 *
 * @since 2.7.1
 * @author Julio Potier
 */
function secupress_firewall_learning_clear_proposals() {
	secupress_firewall_learning_accept_proposal( 'plugin' );
	secupress_firewall_learning_accept_proposal( 'core' );
	delete_site_transient( 'secupress_firewall_learning_plugin_notice' );
}

add_action( 'current_screen', 'secupress_firewall_learning_admin_notices' );
/**
 * Admin notices: running banner, plugin activation, ended, intro for existing NG sites.
 *
 * @since 2.7.1
 * @author Julio Potier
 */
function secupress_firewall_learning_admin_notices() {
	if ( ! current_user_can( secupress_get_capability() ) ) {
		return;
	}
	if ( ! secupress_firewall_ng_is_enabled() ) {
		return;
	}

	$state     = get_site_option( SECUPRESS_FIREWALL_LEARNING, [] );
	$status    = isset( $state['status'] ) ? $state['status'] : 'off';
	$is_sp     = secupress_firewall_learning_is_secupress_screen();
	$module_url = secupress_admin_url( 'modules', 'firewall' ) . '#row-learning-mode_learning';

	if ( 'running' === $status && (int) $state['ends'] > time() && $is_sp ) {
		$until      = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $state['ends'] );
		$hits_count = count( secupress_firewall_learning_get_hits() );
		$message    = sprintf( __( 'Learning Mode until %1$s — %2$s calibrable rules are not blocking yet. Critical exploits still are.', 'secupress' ), $until, secupress_firewall_ng_name() );
		$message   .= ' ' . sprintf( _n( '%s signature observed.', '%s signatures observed.', $hits_count, 'secupress' ), number_format_i18n( $hits_count ) );
		$message .= ' <a href="' . esc_url( $module_url ) . '">' . __( 'Manage Learning Mode', 'secupress' ) . '</a>';
		secupress_add_notice( $message, 'updated', false );
	}

	if ( ! secupress_firewall_learning_is_running() ) {
		$ended = get_site_transient( 'secupress_firewall_learning_ended_notice' );
		if ( $ended ) {
			delete_site_transient( 'secupress_firewall_learning_ended_notice' );
			if ( secupress_firewall_learning_end_notice_has_work() ) {
				secupress_add_notice( $ended, 'updated', '' );
			}
		}
	}

	if ( secupress_firewall_ng_is_active() ) {
		$proposal = secupress_firewall_learning_current_proposal();
		if ( $proposal && ! secupress_notice_is_dismissed( $proposal['id'] ) ) {
			secupress_add_notice( secupress_firewall_learning_proposal_notice_html( $proposal ), 'updated', $proposal['id'] );
		}
	}

	if ( $is_sp && 'running' !== $status && secupress_firewall_learning_ng_needs_calibration() && ! secupress_notice_is_dismissed( 'firewall-learning-mode-intro' ) ) {
		$message  = '<strong>' . __( 'Firewall Learning Mode', 'secupress' ) . '</strong> — ';
		if ( secupress_firewall_learning_get_last_started() ) {
			$message .= sprintf(
				/* translators: %s: firewall pack name (e.g. 8G) */
				__( 'A new %s protection has been activated and has not been calibrated yet. Start Learning Mode to observe usual traffic before blocking.', 'secupress' ),
				secupress_firewall_ng_name()
			);
		} else {
			$message .= sprintf(
				/* translators: %s: firewall pack name (e.g. 8G) */
				__( '%s signatures are active and have never been calibrated on this site. Start Learning Mode to observe usual traffic before blocking.', 'secupress' ),
				secupress_firewall_ng_name()
			);
		}
		$message .= ' <a href="' . esc_url( $module_url ) . '" class="secupress-button secupress-button-tertiary secupress-ghost secupress-button-mini">' . __( 'Open Learning Mode', 'secupress' ) . '</a>';
		secupress_add_notice( $message, 'updated', 'firewall-learning-mode-intro' );
	}
}

/**
 * First unanswered Learning Mode proposal, plugin before core.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @return (array) Empty when there is nothing to propose.
 */
function secupress_firewall_learning_current_proposal() {
	$proposals = [];
	$plugin    = secupress_firewall_learning_get_plugin_proposal();
	$known     = [];
	if ( $plugin ) {
		$known[ $plugin['plugin'] ] = 1;
		$proposals[] = [
			'type'    => 'plugin',
			'plugin'  => $plugin['plugin'],
			'running' => $plugin['running'],
			'id'      => 'firewall-learning-mode-plugin-' . md5( $plugin['plugin'] ),
		];
	}
	if ( secupress_firewall_learning_get_last_started() ) {
		foreach ( secupress_firewall_learning_new_plugins() as $file ) {
			if ( isset( $known[ $file ] ) ) {
				continue;
			}
			$proposals[] = [
				'type'    => 'plugin',
				'plugin'  => $file,
				'running' => 0,
				'id'      => 'firewall-learning-mode-plugin-' . md5( $file ),
			];
		}
	}
	$core = get_site_option( 'secupress_firewall_learning_core_notice' );
	if ( is_array( $core ) && ! empty( $core['version'] ) ) {
		$version     = (string) $core['version'];
		$proposals[] = [
			'type'    => 'core',
			'version' => $version,
			'running' => ! empty( $core['running'] ) ? 1 : 0,
			'id'      => 'firewall-learning-mode-core-' . md5( $version ),
		];
	}
	$snapshot = secupress_firewall_learning_get_plugins_snapshot();
	foreach ( $proposals as $proposal ) {
		if ( 'plugin' === $proposal['type'] && in_array( $proposal['plugin'], $snapshot, true ) ) {
			continue;
		}
		if ( function_exists( 'secupress_notice_is_dismissed' ) && secupress_notice_is_dismissed( $proposal['id'] ) ) {
			continue;
		}
		return $proposal;
	}
	return [];
}

/**
 * HTML for the adaptive Learning Mode proposal.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (array) $proposal `type` is `plugin` or `core`. `running` means the cycle was on when the proposal was stored.
 *
 * @return (string)
 */
function secupress_firewall_learning_proposal_notice_html( $proposal ) {
	$type         = isset( $proposal['type'] ) ? $proposal['type'] : 'plugin';
	$seen_running = ! empty( $proposal['running'] );
	$mode         = 'start';
	if ( secupress_firewall_learning_is_running() ) {
		$mode = 'extend';
	} elseif ( $seen_running ) {
		$mode = 'relaunch';
	}

	$name = '';
	if ( 'plugin' === $type ) {
		$plugin = isset( $proposal['plugin'] ) ? (string) $proposal['plugin'] : '';
		$name   = $plugin;
		if ( $plugin ) {
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$plugin_file = WP_PLUGIN_DIR . '/' . $plugin;
			if ( is_readable( $plugin_file ) ) {
				$plugin_data = get_plugin_data( $plugin_file, false, false );
				if ( ! empty( $plugin_data['Name'] ) ) {
					$name = $plugin_data['Name'];
				}
			}
		}
	}

	$pack = secupress_firewall_ng_name();
	if ( 'core' === $type && 'extend' === $mode ) {
		$text = sprintf(
			/* translators: %s: firewall pack name (e.g. 8G) */
			__( 'WordPress has been updated. Extend Learning Mode by a few days so %s rules do not block legitimate requests that changed.', 'secupress' ),
			$pack
		);
	} elseif ( 'core' === $type && 'relaunch' === $mode ) {
		$text = sprintf(
			/* translators: %s: firewall pack name (e.g. 8G) */
			__( 'WordPress was updated while Learning Mode was running. Start it again so %s rules do not block legitimate requests that changed.', 'secupress' ),
			$pack
		);
	} elseif ( 'core' === $type ) {
		$text = sprintf(
			/* translators: %s: firewall pack name (e.g. 8G) */
			__( 'WordPress has been updated. Start a short Learning Mode so %s rules do not block legitimate requests that changed.', 'secupress' ),
			$pack
		);
	} elseif ( 'extend' === $mode ) {
		$text = sprintf(
			/* translators: 1: plugin name, 2: firewall pack name (e.g. 8G) */
			__( 'You just added %1$s. Extend Learning Mode by a few days so %2$s rules do not block its legitimate requests.', 'secupress' ),
			'<em>' . esc_html( $name ) . '</em>',
			$pack
		);
	} elseif ( 'relaunch' === $mode ) {
		$text = sprintf(
			/* translators: 1: plugin name, 2: firewall pack name (e.g. 8G) */
			__( 'You added %1$s while Learning Mode was running. Start it again so %2$s rules do not block its legitimate requests.', 'secupress' ),
			'<em>' . esc_html( $name ) . '</em>',
			$pack
		);
	} else {
		$text = sprintf(
			/* translators: 1: plugin name, 2: firewall pack name (e.g. 8G) */
			__( 'You just added %1$s. Start a short Learning Mode so %2$s rules do not block its legitimate requests.', 'secupress' ),
			'<em>' . esc_html( $name ) . '</em>',
			$pack
		);
	}

	$action_name = 'extend' === $mode ? 'secupress-learning-extend' : 'secupress-learning-start';
	if ( 'extend' === $mode ) {
		$button = __( 'Extend', 'secupress' );
	} elseif ( 'relaunch' === $mode ) {
		$button = __( 'Relaunch Learning Mode', 'secupress' );
	} else {
		$button = __( 'Start Learning Mode', 'secupress' );
	}
	$trigger = 'core' === $type ? 'core' : 'plugin';
	$action  = admin_url( 'admin-post.php' );
	$html    = '<p><strong>' . __( 'Firewall Learning Mode', 'secupress' ) . '</strong></p>';
	$html   .= '<p>' . $text . '</p>';
	$html   .= '<form method="post" action="' . esc_url( $action ) . '" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">';
	$html   .= '<input type="hidden" name="action" value="' . esc_attr( $action_name ) . '" />';
	$html   .= '<input type="hidden" name="trigger" value="' . esc_attr( $trigger ) . '" />';
	if ( 'plugin' === $type && ! empty( $proposal['plugin'] ) ) {
		$html .= '<input type="hidden" name="plugin" value="' . esc_attr( $proposal['plugin'] ) . '" />';
	}
	$html .= wp_nonce_field( $action_name, '_wpnonce', true, false );
	$html .= '<label for="secupress-learning-proposal-duration" class="screen-reader-text">' . __( 'Duration', 'secupress' ) . '</label>';
	$html .= '<select name="duration" id="secupress-learning-proposal-duration">';
	foreach ( secupress_firewall_learning_duration_options() as $days => $label ) {
		$html .= '<option value="' . (int) $days . '"' . selected( $days, 2, false ) . '>' . esc_html( $label ) . '</option>';
	}
	$html .= '</select> ';
	$html .= '<button type="submit" class="button button-primary">' . esc_html( $button ) . '</button>';
	$html .= '</form>';
	return $html;
}

/**
 * HTML for the plugin-activation Learning Mode notice.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @param (string) $plugin
 *
 * @return (string)
 */
function secupress_firewall_learning_plugin_notice_html( $plugin ) {
	return secupress_firewall_learning_proposal_notice_html( [
		'type'    => 'plugin',
		'plugin'  => $plugin,
		'running' => 0,
	] );
}

/**
 * Tell if we are on a SecuPress admin screen.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @return (bool)
 */
function secupress_firewall_learning_is_secupress_screen() {
	if ( ! function_exists( 'get_current_screen' ) ) {
		return false;
	}
	$screen = get_current_screen();
	if ( ! $screen || empty( $screen->id ) ) {
		return false;
	}
	return false !== strpos( $screen->id, SECUPRESS_PLUGIN_SLUG );
}
