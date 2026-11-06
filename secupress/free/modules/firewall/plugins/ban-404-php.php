<?php
/**
 * Module Name: Block scans for hidden content
 * Description: Block missing PHP files, and refuse further 404s from an IP that raises too many in one minute.
 * Main Module: firewall
 * Author: SecuPress
 * Version: 2.7.4
 */

defined( 'SECUPRESS_VERSION' ) or die( 'Something went wrong.' );

add_action( 'template_redirect', 'secupress_block_404_php', 99 );
/**
 * Count every 404 by IP for one minute. Past the limit, refuse only the next 404s and block missing PHP files.
 *
 * @since 2.7.4 Count every 404, then refuse further 404s without banning the IP. Keep blocking missing PHP files, with the file name in the block payload.
 * @since 2.1 Add filter
 * @since 2.0.1 Test file_exists to avoid fake 404 created by plugins
 * @since 1.1 Use secupress_block() instead of secupress_ban_ip()
 * @since 1.0
 * @author Julio Potier
 **/
function secupress_block_404_php() {
	if ( ! is_404() ) {
		return;
	}

	$uri    = (string) secupress_get_current_url( 'uri' );
	$path   = '/' . ltrim( $uri, '/' );
	$logged = secupress_404_logged_path( $path );
	$is_php = secupress_404_is_missing_php( $uri );
	$skip   = secupress_ip_is_whitelisted() || secupress_404_is_verified_bot() || secupress_is_scan_request() || secupress_404_is_ignored_path( $path );

	if ( ! $skip && secupress_404_is_penalized() ) {
		secupress_404_refuse();
	}

	if ( ! $skip ) {
		$hit = secupress_404_hit( secupress_get_ip(), $logged );
		$max = secupress_404_max_for_current_visitor();

		if ( $hit['count'] >= $max ) {
			secupress_404_penalize_and_die( $hit['urls'] );
		}
	}

	if ( ! $is_php ) {
		return;
	}

	/**
	 * Gives the possibility to bypass the PHP 404 block.
	 *
	 * @since 2.1
	 * @author Julio Potier
	 *
	 * @param (bool)   $bypass True to let the 404 through.
	 * @param (string) $url    Current URL without the query string.
	 */
	if ( apply_filters( 'secupress.plugins.ban_404.bypass', false, secupress_get_current_url( 'base' ) ) ) {
		return;
	}

	secupress_block( 'PHP404', array(
		'code'        => 403,
		'attack_type' => 'php404',
		'b64'         => array( 'file' => $logged ),
	) );
}

/**
 * Tell if this 404 targets a PHP file that is not on disk.
 *
 * @since 2.7.4
 * @author Julio Potier
 *
 * @param (string) $uri Request path, without the domain.
 *
 * @return (bool)
 */
function secupress_404_is_missing_php( $uri ) {
	$extension = strtolower( (string) pathinfo( basename( $uri ), PATHINFO_EXTENSION ) );

	return 'php' === $extension && ! file_exists( ABSPATH . ltrim( $uri, '/' ) );
}

/**
 * Path stored in the counter and sent in the alert, query string included, shortened.
 *
 * @since 2.7.4
 * @author Julio Potier
 *
 * @param (string) $path Request path, leading slash, no query string.
 *
 * @return (string)
 */
function secupress_404_logged_path( $path ) {
	$query  = wp_parse_url( secupress_get_current_url( 'raw' ), PHP_URL_QUERY );
	$logged = $path . ( is_string( $query ) && '' !== $query ? '?' . $query : '' );

	if ( strlen( $logged ) > 500 ) {
		$logged = substr( $logged, 0, 500 );
	}

	return $logged;
}

/**
 * Verified good bot (Google, Bing, and the other bots checked by hostname), when the setting says not to count it.
 *
 * @since 2.7.4
 * @author Julio Potier
 *
 * @return (bool)
 */
function secupress_404_is_verified_bot() {
	if ( ! secupress_get_module_option( 'bbq-url-content_404-skip-google', 1, 'firewall' ) ) {
		return false;
	}

	return (bool) secupress_check_bot_ip();
}

/**
 * 404 limit for this request: robots and visitors have their own setting.
 *
 * @since 2.7.4
 * @author Julio Potier
 *
 * @return (int)
 */
function secupress_404_max_for_current_visitor() {
	$is_robot = secupress_current_visitor_is_robot();
	$default  = $is_robot ? 120 : 60;
	$key      = $is_robot ? 'bbq-url-content_404-max-robots' : 'bbq-url-content_404-max-visitors';
	$max      = (int) secupress_get_module_option( $key, $default, 'firewall' );

	return $max > 0 ? $max : $default;
}

/**
 * URLs requested by browsers and crawlers whether or not the file exists.
 *
 * @since 2.7.4
 * @author Julio Potier
 *
 * @param (string) $path Request path with a leading slash, no query string.
 *
 * @return (bool)
 */
function secupress_404_is_ignored_path( $path ) {
	$patterns = array(
		'#^/favicon\.(ico|png|svg)$#i',
		'#^/apple-touch-icon[^/]*\.png$#i',
		'#^(?:/[^/.]+)*/@[23]x\.png$#i',
		'#^/browserconfig\.xml$#i',
		'#^/safari-pinned-tab\.svg$#i',
		'#^/site\.webmanifest$#i',
		'#^/manifest\.(json|webmanifest)$#i',
		'#^/android-chrome-[0-9]+x[0-9]+\.png$#i',
		'#^/mstile-[0-9]+x[0-9]+\.png$#i',
		'#^/robots\.txt$#i',
		'#^/humans\.txt$#i',
		'#^/ai\.txt$#i',
		'#^/llms\.txt$#i',
		'#^/ads\.txt$#i',
		'#^/app-ads\.txt$#i',
		'#^/sellers\.json$#i',
		'#^/sitemap(?:s|_index|-index)?\.xml$#i',
		'#^/wp-sitemap\.xml$#i',
		'#^/crossdomain\.xml$#i',
		'#^/clientaccesspolicy\.xml$#i',
		'#^/apple-app-site-association$#i',
		'#^/\.well-known/(security\.txt|traffic-advice|assetlinks\.json|apple-app-site-association)$#i',
	);

	/**
	 * Filter the paths that must not increase the 404 counter.
	 *
	 * @since 2.7.4
	 * @author Julio Potier
	 *
	 * @param (array)  $patterns Regular expressions matched against the path.
	 * @param (string) $path     Request path with a leading slash.
	 */
	$patterns = apply_filters( 'secupress.plugins.ban_404.ignored_patterns', $patterns, $path );
	$patterns = is_array( $patterns ) ? $patterns : array();

	foreach ( $patterns as $pattern ) {
		if ( is_string( $pattern ) && preg_match( $pattern, $path ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Add one 404 to the current one-minute window of this IP.
 *
 * @since 2.7.4
 * @author Julio Potier
 *
 * @param (string) $ip   Visitor IP.
 * @param (string) $path Logged path.
 *
 * @return (array) Keys: count (int), urls (array).
 */
function secupress_404_hit( $ip, $path ) {
	global $wpdb;

	$key = SECUPRESS_404_ . md5( $ip );
	$now = time();

	if ( wp_using_ext_object_cache() ) {
		$data = secupress_404_hit_object_cache( $key, $path, $now );

		if ( false !== $data ) {
			return $data;
		}
	}

	$option         = '_transient_' . $key;
	$timeout_option = '_transient_timeout_' . $key;

	// Same lock as the login attempts counter: no other request can read this row before COMMIT.
	$wpdb->query( 'START TRANSACTION' );
	$wpdb->query( $wpdb->prepare(
		"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, '', 'off') ON DUPLICATE KEY UPDATE option_id = option_id",
		$option
	) );
	$raw  = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1 FOR UPDATE", $option ) );
	$data = maybe_unserialize( $raw );

	if ( ! is_array( $data ) || empty( $data['start'] ) || ( $now - (int) $data['start'] ) >= MINUTE_IN_SECONDS ) {
		$data = array(
			'start' => $now,
			'count' => 0,
			'urls'  => array(),
		);
	}

	++$data['count'];

	if ( $data['count'] > 1 && count( $data['urls'] ) < 20 && ! in_array( $path, $data['urls'], true ) ) {
		$data['urls'][] = $path;
	}

	$ttl     = max( 1, MINUTE_IN_SECONDS - ( $now - (int) $data['start'] ) );
	$expires = $now + $ttl;
	$updated = $wpdb->update(
		$wpdb->options,
		array( 'option_value' => maybe_serialize( $data ) ),
		array( 'option_name' => $option ),
		array( '%s' ),
		array( '%s' )
	);
	$wpdb->query( $wpdb->prepare(
		"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off') ON DUPLICATE KEY UPDATE option_value = %s",
		$timeout_option,
		(string) $expires,
		(string) $expires
	) );

	if ( false === $updated ) {
		$wpdb->query( 'ROLLBACK' );
		return $data;
	}

	$wpdb->query( 'COMMIT' );
	secupress_404_clear_option_cache( array( $option, $timeout_option ) );

	return $data;
}

/**
 * Add one 404 to an external object cache.
 *
 * The numeric counter is atomic. The URL list is only evidence for the log, so
 * a short cache lock is enough and a missed URL does not affect enforcement.
 *
 * @since 2.7.4
 * @author Julio Potier
 *
 * @param (string) $key  Counter key.
 * @param (string) $path Logged path.
 * @param (int)    $now  Current Unix timestamp.
 *
 * @return (array|false) Counter data, or false when the cache is unavailable.
 */
function secupress_404_hit_object_cache( $key, $path, $now ) {
	$group     = 'transient';
	$count_key = $key . '_count';
	$urls_key  = $key . '_urls';
	$lock_key  = $key . '_urls_lock';
	$timeout   = MINUTE_IN_SECONDS;
	$is_new    = wp_cache_add( $count_key, 0, $group, $timeout );

	if ( $is_new ) {
		wp_cache_set( $urls_key, array(), $group, $timeout );
	}

	$count = wp_cache_incr( $count_key, 1, $group );

	// The key may have expired between `add` and `incr`, retry once.
	if ( false === $count ) {
		wp_cache_delete( $count_key, $group );
		if ( ! wp_cache_add( $count_key, 0, $group, $timeout ) ) {
			return false;
		}

		wp_cache_set( $urls_key, array(), $group, $timeout );
		$count = wp_cache_incr( $count_key, 1, $group );

		if ( false === $count ) {
			return false;
		}
	}

	$urls = wp_cache_get( $urls_key, $group );
	$urls = is_array( $urls ) ? $urls : array();

	if ( $count > 1 && count( $urls ) < 20 && ! in_array( $path, $urls, true ) && wp_cache_add( $lock_key, 1, $group, 1 ) ) {
		$urls = wp_cache_get( $urls_key, $group );
		$urls = is_array( $urls ) ? $urls : array();

		if ( count( $urls ) < 20 && ! in_array( $path, $urls, true ) ) {
			$urls[] = $path;
			wp_cache_set( $urls_key, $urls, $group, $timeout );
		}

		wp_cache_delete( $lock_key, $group );
	}

	return array(
		'start' => $now,
		'count' => (int) $count,
		'urls'  => $urls,
	);
}

/**
 * Drop cached copies of option rows written directly.
 *
 * @since 2.7.4
 * @author Julio Potier
 *
 * @param (array) $option_names Option names.
 */
function secupress_404_clear_option_cache( $option_names ) {
	foreach ( $option_names as $option_name ) {
		wp_cache_delete( $option_name, 'options' );
	}

	$notoptions = wp_cache_get( 'notoptions', 'options' );
	if ( ! is_array( $notoptions ) ) {
		return;
	}

	foreach ( $option_names as $option_name ) {
		unset( $notoptions[ $option_name ] );
	}

	wp_cache_set( 'notoptions', $notoptions, 'options' );
}

/**
 * Delete the 404 counter stored for an IP.
 *
 * @since 2.7.4
 * @author Julio Potier
 *
 * @param (string) $ip Visitor IP.
 */
function secupress_404_delete_counter( $ip ) {
	global $wpdb;

	$key            = SECUPRESS_404_ . md5( $ip );
	$option         = '_transient_' . $key;
	$timeout_option = '_transient_timeout_' . $key;

	wp_cache_delete( $key . '_count', 'transient' );
	wp_cache_delete( $key . '_urls', 'transient' );
	wp_cache_delete( $key . '_urls_lock', 'transient' );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name IN (%s, %s)", $option, $timeout_option ) );
	secupress_404_clear_option_cache( array( $option, $timeout_option ) );
	wp_cache_delete( $key, 'transient' );
}

/**
 * Transient key for the temporary 404 penalty of an IP.
 *
 * @since 2.7.4
 * @author Julio Potier
 *
 * @param (string|null) $ip Visitor IP. Current IP when empty.
 *
 * @return (string)
 */
function secupress_404_penalty_key( $ip = null ) {
	$ip = $ip ? $ip : secupress_get_ip();

	return SECUPRESS_404_PENALTY_ . md5( $ip );
}

/**
 * Tell if this IP must be refused on its next 404s.
 *
 * @since 2.7.4
 * @author Julio Potier
 *
 * @param (string|null) $ip Visitor IP. Current IP when empty.
 *
 * @return (bool)
 */
function secupress_404_is_penalized( $ip = null ) {
	return false !== get_transient( secupress_404_penalty_key( $ip ) );
}

/**
 * Penalty duration, in minutes.
 *
 * @since 2.7.4
 * @author Julio Potier
 *
 * @return (int)
 */
function secupress_404_penalty_minutes() {
	$minutes = (int) secupress_get_module_option( 'bbq-url-content_404-ban-time', 5, 'firewall' );

	return $minutes > 0 ? $minutes : 5;
}

/**
 * Send the time the visitor should wait before requesting another missing page.
 *
 * @since 2.7.4
 * @author Julio Potier
 *
 * @param (int) $until Unix timestamp when the penalty ends.
 */
function secupress_404_send_retry_after( $until ) {
	if ( headers_sent() ) {
		return;
	}

	header( 'Retry-After: ' . max( 1, (int) $until - time() ) );
}

/**
 * Stop a 404 while the penalty is active, without logging it again.
 *
 * @since 2.7.4
 * @author Julio Potier
 */
function secupress_404_refuse() {
	$until = (int) get_transient( secupress_404_penalty_key() );
	secupress_404_send_retry_after( $until );

	secupress_die(
		__( 'Too many pages not found. Please retry later.', 'secupress' ),
		'429 ' . get_status_header_desc( 429 ),
		array(
			'response'  => 429,
			'force_die' => true,
		)
	);
}

/**
 * Atomically start a 404 penalty.
 *
 * The database row is locked until both the value and its timeout are written.
 * External object caches provide the same one-winner behavior with `add`.
 *
 * @since 2.7.4
 * @author Julio Potier
 *
 * @param (string) $key     Transient key.
 * @param (int)    $until   Unix timestamp when the penalty ends.
 * @param (int)    $timeout Penalty duration in seconds.
 *
 * @return (bool) True only for the request that started the penalty.
 */
function secupress_404_claim_penalty( $key, $until, $timeout ) {
	global $wpdb;

	$until   = (int) $until;
	$timeout = max( 1, (int) $timeout );

	if ( wp_using_ext_object_cache() ) {
		return wp_cache_add( $key, $until, 'transient', $timeout );
	}

	$option         = '_transient_' . $key;
	$timeout_option = '_transient_timeout_' . $key;

	if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
		return false;
	}

	$inserted = $wpdb->query( $wpdb->prepare(
		"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, '', 'off') ON DUPLICATE KEY UPDATE option_id = option_id",
		$option
	) );

	if ( false === $inserted ) {
		$wpdb->query( 'ROLLBACK' );
		return false;
	}

	$wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1 FOR UPDATE", $option ) );
	$active_until = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1 FOR UPDATE", $timeout_option ) );

	if ( $active_until > time() ) {
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
		}
		secupress_404_clear_option_cache( array( $option, $timeout_option ) );

		return false;
	}

	$updated = $wpdb->update(
		$wpdb->options,
		array( 'option_value' => (string) $until ),
		array( 'option_name' => $option ),
		array( '%s' ),
		array( '%s' )
	);
	$timeout_updated = $wpdb->query( $wpdb->prepare(
		"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off') ON DUPLICATE KEY UPDATE option_value = %s",
		$timeout_option,
		(string) $until,
		(string) $until
	) );

	if ( false === $updated || false === $timeout_updated || false === $wpdb->query( 'COMMIT' ) ) {
		$wpdb->query( 'ROLLBACK' );
		secupress_404_clear_option_cache( array( $option, $timeout_option ) );
		return false;
	}

	secupress_404_clear_option_cache( array( $option, $timeout_option ) );

	return true;
}

/**
 * Start the 404 penalty and log this attack once.
 *
 * @since 2.7.4
 * @author Julio Potier
 *
 * @param (array) $urls Paths counted in this window.
 */
function secupress_404_penalize_and_die( $urls ) {
	$minutes = secupress_404_penalty_minutes();
	$key     = secupress_404_penalty_key();
	$until   = time() + ( $minutes * MINUTE_IN_SECONDS );
	$first   = secupress_404_claim_penalty( $key, $until, $minutes * MINUTE_IN_SECONDS );
	$urls    = is_array( $urls ) ? $urls : array();

	secupress_404_delete_counter( secupress_get_ip() );

	if ( ! $first ) {
		secupress_404_refuse();
	}

	secupress_404_send_retry_after( $until );
	secupress_block( '404', array(
		'code'        => 429,
		'attack_type' => '404',
		'b64'         => array( 'urls' => implode( "\n", $urls ) ),
	) );
}
