<?php
/**
 * Module Name: Forbid User Enumeration
 * Description: Forbid the user listing from front with ?author=X and from REST API with /users/ or ?_embed=
 * Main Module: users_login
 * Author: SecuPress
 * Version: 2.2.6
 */

defined( 'SECUPRESS_VERSION' ) or die( 'Something went wrong.' );

add_action( 'wp', 'secupress_set_404_for_author_pages' );
/**
 * Returns a 404 when an author page is loaded
 *
 * @since 2.2.6 remove_action to prevent a WP warning due to a lack of index check...
 * @since 1.0
 * @author Julio Potier
 **/
function secupress_set_404_for_author_pages() {
	global $wp_query;
	if ( is_author() ) {
		remove_action( 'template_redirect', 'redirect_canonical' );
		$wp_query->set_404();
		status_header( 404 );
	}
}

add_filter( 'author_link', 'secupress_replace_author_link' );
/**
 * Replace the author links with homepage to prevent linking on 404 pages
 *
 * @since 1.0
 * @return (string) home_url()
 * @author Julio Potier
 **/
function secupress_replace_author_link() {
	return home_url();
}

/**
 * Protected means that only descendants of the Code class can access that property so it must be public.
 *
 * @since 1.0
 * @author Julio Potier
 **/
class Secupress_WP_REST_Users_Controller extends WP_REST_Users_Controller {
	/**
	 * Return the rest base URL.
	 *
	 * @return string
	 * @author Julio Potier
	 **/
	public static function get_rest_base() {
		$controller = new Secupress_WP_REST_Users_Controller();
		return untrailingslashit( $controller->namespace . '/' . $controller->rest_base );
	}
}

add_action( 'init', 'secupress_stop_user_enumeration_front', SECUPRESS_INT_MAX );
/**
 * Block the author page on front
 *
 * @since 1.0
 * @author Julio Potier
 **/
function secupress_stop_user_enumeration_front() {
	if ( ! current_user_can( 'list_users' ) && is_author() ) {
		secupress_die( __( 'Sorry, you are not allowed to do that.', 'secupress' ), '', [ 'response' => 403, 'force_die' => true, 'attack_type' => 'users' ] );
	}
}


add_filter( 'rest_request_before_callbacks', 'secupress_stop_user_enumeration_rest', 10, 3 );
/**
 * Block the users endpoints for REST API
 *
 * @since 2.7 Match the REST route, language URL prefixes like /fr/ are ignored
 * @since 2.3.12 Let "/me/" being read
 * @since 2.2.6 Remove home_url() from strpos()
 * @since 2.2.5 Remove REST API calls made using query parameters + usage of rawurldecode()
 * @since 2.2.2 'raw'
 * @since 2.0 'uri'
 * @since 1.0 'base'
 * @author Julio Potier
 *
 * @param (mixed)           $response The current error object if any.
 * @param (array)           $handler  Route handler.
 * @param (WP_REST_Request) $request  The REST request.
 *
 * @return (mixed)
 **/
function secupress_stop_user_enumeration_rest( $response, $handler, $request ) {
	if ( current_user_can( 'list_users' ) || ! $request instanceof WP_REST_Request ) {
		return $response;
	}

	$users_base = Secupress_WP_REST_Users_Controller::get_rest_base();
	$route      = trim( $request->get_route(), '/' );

	if ( ! preg_match( '#(?:^|/)' . preg_quote( $users_base, '#' ) . '(?:/|$)#i', $route ) ) {
		return $response;
	}

	if ( preg_match( '#(?:^|/)' . preg_quote( $users_base, '#' ) . '/me(?:/|$)#i', $route ) ) {
		return $response;
	}

	wp_send_json( [
		'code'    => 'rest_cannot_access',
		'message' => __( 'Something went wrong.', 'secupress' ),
		'data'    => [ 'status' => 401 ],
	], 401 );
}


add_filter( 'rest_post_dispatch', 'secupress_stop_user_enumeration_rest_author', SECUPRESS_INT_MAX, 3 );
/**
 * Remove author links so they cannot be embedded via ?_embed=
 *
 * @since 2.7
 * @author Julio Potier
 *
 * @param (WP_REST_Response|WP_HTTP_Response|WP_Error|mixed) $result  Result to send to the client.
 * @param (WP_REST_Server)                                   $server  Server instance.
 * @param (WP_REST_Request)                                  $request Request used to generate the response.
 *
 * @return (mixed)
 **/
function secupress_stop_user_enumeration_rest_author( $result, $server, $request ) {
	if ( current_user_can( 'list_users' ) || ! $result instanceof WP_REST_Response ) {
		return $result;
	}

	$result->remove_link( 'author' );

	$data = $result->get_data();
	if ( is_array( $data ) ) {
		$result->set_data( secupress_stop_user_enumeration_strip_author( $data ) );
	}

	return $result;
}


add_filter( 'rest_pre_echo_response', 'secupress_stop_user_enumeration_rest_embedded', SECUPRESS_INT_MAX, 3 );
/**
 * Strip the embedded author node from REST responses, with or without ?_embed=
 *
 * @since 2.7
 * @author Julio Potier
 *
 * @param (array)           $result  Response data to send to the client.
 * @param (WP_REST_Server)  $server  Server instance.
 * @param (WP_REST_Request) $request Request used to generate the response.
 *
 * @return (array)
 **/
function secupress_stop_user_enumeration_rest_embedded( $result, $server, $request ) {
	if ( current_user_can( 'list_users' ) || ! is_array( $result ) ) {
		return $result;
	}

	return secupress_stop_user_enumeration_strip_author( $result );
}


/**
 * Recursively remove author HAL links and embedded author data.
 *
 * @since 2.7
 * @author Julio Potier
 *
 * @param (array) $data REST data.
 *
 * @return (array)
 **/
function secupress_stop_user_enumeration_strip_author( $data ) {
	if ( ! is_array( $data ) ) {
		return $data;
	}

	unset( $data['_links']['author'], $data['_embedded']['author'] );

	if ( isset( $data['_links'] ) && ! $data['_links'] ) {
		unset( $data['_links'] );
	}

	if ( isset( $data['_embedded'] ) && ! $data['_embedded'] ) {
		unset( $data['_embedded'] );
	}

	foreach ( $data as $key => $value ) {
		if ( ! is_array( $value ) || '_links' === $key ) {
			continue;
		}
		$data[ $key ] = secupress_stop_user_enumeration_strip_author( $value );
	}

	return $data;
}

