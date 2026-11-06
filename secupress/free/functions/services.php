<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

/**
 * User-agent sent to public reputation services.
 *
 * A bare "Mozilla/5.0" is accepted, but an incomplete token is what bot filters
 * often reject. A full, fixed browser string is the usual form of a real request.
 * WordPress' own user-agent is refused by URLVoid.
 *
 * @since 2.7.4
 * @author Julio Potier
 *
 * @return (string)
 */
function secupress_get_basic_user_agent() {
	return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
}


/**
 * Arguments for a request to a public reputation service.
 *
 * @since 2.7.4
 * @author Julio Potier
 *
 * @param (int)  $timeout  Request timeout, in seconds.
 * @param (bool) $blocking Whether to wait for the response.
 *
 * @return (array)
 */
function secupress_get_service_request_args( $timeout, $blocking = true ) {
	return array(
		'timeout'     => (int) $timeout,
		'blocking'    => (bool) $blocking,
		'redirection' => 3,
		'user-agent'  => secupress_get_basic_user_agent(),
	);
}


/**
 * Category names returned by the URL reputation service.
 *
 * The shortened spellings are intentional. An unknown id returns an empty string.
 *
 * @since 2.7.4
 * @author Julio Potier
 *
 * @param (int) $category Category id.
 *
 * @return (string) Empty when the id has no name, including 0.
 */
function secupress_url_reputation_category_name( $category ) {
	$names = array(
		6   => _x( 'Nud*ty', 'URL reputation category', 'secupress' ),
		9   => _x( 'Scamming', 'URL reputation category', 'secupress' ),
		14  => _x( 'Violence/Hate/Rac*sm', 'URL reputation category', 'secupress' ),
		15  => _x( 'Weapons', 'URL reputation category', 'secupress' ),
		17  => _x( 'Hacking', 'URL reputation category', 'secupress' ),
		18  => _x( 'Phishing', 'URL reputation category', 'secupress' ),
		26  => _x( 'Child P*rn', 'URL reputation category', 'secupress' ),
		43  => _x( 'Malicious Sources', 'URL reputation category', 'secupress' ),
		44  => _x( 'Botnets', 'URL reputation category', 'secupress' ),
		50  => _x( 'Mixed Content', 'URL reputation category', 'secupress' ),
		56  => _x( 'File Storage', 'URL reputation category', 'secupress' ),
		57  => _x( 'Remote Access Tools', 'URL reputation category', 'secupress' ),
		71  => _x( 'Software Downloads', 'URL reputation category', 'secupress' ),
		83  => _x( 'Peer-to-peer', 'URL reputation category', 'secupress' ),
		86  => _x( 'Proxy Avoidance', 'URL reputation category', 'secupress' ),
		92  => _x( 'Suspicious', 'URL reputation category', 'secupress' ),
		98  => _x( 'Placeholders', 'URL reputation category', 'secupress' ),
		101 => _x( 'Spam', 'URL reputation category', 'secupress' ),
		102 => _x( 'Unwanted Software', 'URL reputation category', 'secupress' ),
		103 => _x( 'Dynamic DNS Host', 'URL reputation category', 'secupress' ),
		118 => _x( 'Piracy', 'URL reputation category', 'secupress' ),
	);
	$category = (int) $category;

	return isset( $names[ $category ] ) ? $names[ $category ] : '';
}


/**
 * Read the reputation of one URL.
 *
 * Ratings: g safe, b warning, w caution, u untested.
 * Norton is queried with the host only. A full URL returns no categories.
 * A known category other than 0 raises a safe or untested result to suspicious.
 * A failed request is "error" and is never treated as malicious.
 *
 * @since 2.7.4
 * @author Julio Potier
 *
 * @param (string) $url Full URL, or a bare hostname.
 *
 * @return (array) status (safe, warning, caution, suspicious, untested, or error) and categories (list of names).
 */
function secupress_get_url_reputation( $url ) {
	$result = array(
		'status'     => 'error',
		'categories' => array(),
	);
	$url    = is_string( $url ) ? trim( $url ) : '';
	if ( '' === $url ) {
		return $result;
	}

	$host = $url;
	if ( preg_match( '#^https?://#i', $url ) ) {
		$parsed = wp_parse_url( $url, PHP_URL_HOST );
		$host   = is_string( $parsed ) ? $parsed : '';
	}
	$host = strtolower( rtrim( $host, '.' ) );
	if ( '' === $host ) {
		return $result;
	}

	$endpoint = add_query_arg(
		array(
			'insert' => '1',
			'url'    => $host,
		),
		'https://safeweb.norton.com/safeweb/sites/v1/details'
	);
	$response = wp_remote_get( $endpoint, secupress_get_service_request_args( 10 ) );
	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return $result;
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $data ) || empty( $data['rating'] ) || ! is_string( $data['rating'] ) ) {
		return $result;
	}

	$ratings = array(
		'g' => 'safe',
		'b' => 'warning',
		'w' => 'caution',
		'u' => 'untested',
	);
	$rating  = strtolower( $data['rating'] );
	if ( ! isset( $ratings[ $rating ] ) ) {
		return $result;
	}
	$result['status'] = $ratings[ $rating ];

	$has_category = false;
	if ( ! empty( $data['categories'] ) && is_array( $data['categories'] ) ) {
		foreach ( $data['categories'] as $category ) {
			$name = secupress_url_reputation_category_name( $category );
			if ( '' === $name ) {
				continue;
			}
			$has_category = true;
			$result['categories'][] = $name;
		}
		$result['categories'] = array_values( array_unique( $result['categories'] ) );
	}
	if ( $has_category && in_array( $result['status'], array( 'safe', 'untested' ), true ) ) {
		$result['status'] = 'suspicious';
	}

	return $result;
}


/**
 * Read one IP address or email address on StopForumSpam.
 *
 * @since 2.7.4
 * @author Julio Potier
 *
 * @param (string) $value IP address or email address.
 * @param (string) $type  "ip" or "email".
 *
 * @return (string) safe, blacklisted, or error.
 */
function secupress_stopforumspam_get_status( $value, $type ) {
	if ( ! is_string( $value ) || '' === $value || ! in_array( $type, array( 'ip', 'email' ), true ) ) {
		return 'error';
	}

	$url      = add_query_arg(
		array(
			'f'   => 'serial',
			$type => $value,
		),
		'https://api.stopforumspam.org/api'
	);
	$response = wp_remote_get( $url, secupress_get_service_request_args( 5 ) );
	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return 'error';
	}

	$results = maybe_unserialize( wp_remote_retrieve_body( $response ) );
	if ( ! isset( $results['success'], $results[ $type ] ) || ! $results['success'] ) {
		return 'error';
	}

	$clean = 0 === (int) $results[ $type ]['frequency'] && ( ! isset( $results[ $type ]['confidence'] ) || 1 < $results[ $type ]['confidence'] );

	return $clean ? 'safe' : 'blacklisted';
}
