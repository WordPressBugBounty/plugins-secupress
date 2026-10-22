<?php
/**
 * Module Name: Forbid User Enumeration
 * Description: Forbid the user listing from front with ?author=X, from REST API with /users/ or ?_embed=, and from author sitemaps
 * Main Module: users_login
 * Author: SecuPress
 * Version: 2.7.3
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
 * @since 2.7.1 Allow the users route from the editor (edit and new), except pages
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

	if ( is_singular() && current_user_can( 'edit_post', get_the_ID() ) ) {
		return $response;
	}

	if ( secupress_stop_user_enumeration_is_admin_editor() ) {
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


/**
 * Whether this request comes from the editor for any post type except pages.
 *
 * A direct admin load is read from $pagenow. Block editor REST calls keep that screen in the referer.
 *
 * @since 2.7.1
 * @author Julio Potier
 *
 * @return (bool)
 */
function secupress_stop_user_enumeration_is_admin_editor() {
	$post_id   = 0;
	$post_type = '';
	$editing   = false;
	$creating  = false;

	if ( is_admin() ) {
		global $pagenow;

		if ( 'post.php' === $pagenow ) {
			$editing = true;
			$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		} elseif ( 'post-new.php' === $pagenow ) {
			$creating  = true;
			$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post';
		}
	}
	if ( ! $editing && ! $creating ) {
		$referer = wp_get_referer();
		if ( ! $referer ) {
			return false;
		}

		$parts = wp_parse_url( $referer );
		$path  = isset( $parts['path'] ) ? untrailingslashit( $parts['path'] ) : '';
		$query = [];
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
		}

		$edit_path = untrailingslashit( (string) wp_parse_url( admin_url( 'post.php' ), PHP_URL_PATH ) );
		$new_path  = untrailingslashit( (string) wp_parse_url( admin_url( 'post-new.php' ), PHP_URL_PATH ) );

		if ( $path === $edit_path && isset( $query['action'] ) && 'edit' === $query['action'] ) {
			$editing = true;
			$post_id = isset( $query['post'] ) ? (int) $query['post'] : 0;
		} elseif ( $path === $new_path ) {
			$creating  = true;
			$post_type = isset( $query['post_type'] ) ? sanitize_key( $query['post_type'] ) : 'post';
		}
	}
	
	if ( $editing && $post_id ) {
		$post = get_post( $post_id );
		return $post && current_user_can( 'edit_post', $post_id );
	}

	if ( $creating && $post_type ) {
		$post_type_object = get_post_type_object( $post_type );
		return $post_type_object && current_user_can( $post_type_object->cap->create_posts );
	}
	
	return false;
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


add_filter( 'wp_sitemaps_add_provider', 'secupress_stop_user_enumeration_remove_users_sitemap_provider', 10, 2 );
/**
 * Remove the core users sitemap provider.
 *
 * @since 2.7.3
 * @author Julio Potier
 *
 * @param (WP_Sitemaps_Provider|false) $provider Provider instance.
 * @param (string)                     $name     Provider name.
 *
 * @return (WP_Sitemaps_Provider|false)
 **/
function secupress_stop_user_enumeration_remove_users_sitemap_provider( $provider, $name ) {
	if ( 'users' === $name ) {
		return false;
	}

	return $provider;
}


add_filter( 'wpseo_sitemap_index_links', 'secupress_stop_user_enumeration_remove_yoast_author_sitemaps' );
/**
 * Remove author sitemaps from the Yoast index.
 *
 * @since 2.7.3
 * @author Julio Potier
 *
 * @param (array) $links Sitemap index links.
 *
 * @return (array)
 **/
function secupress_stop_user_enumeration_remove_yoast_author_sitemaps( $links ) {
	return secupress_stop_user_enumeration_without_author_sitemaps( $links, 'sitemap' );
}


add_filter( 'wpseo_build_sitemap_post_type', 'secupress_stop_user_enumeration_exclude_yoast_authors' );
/**
 * Drop every author while Yoast builds the author sitemap.
 *
 * Yoast loads full user objects only when wpseo_sitemap_exclude_author is already registered, so the filter is added for that build only.
 *
 * @since 2.7.3
 * @author Julio Potier
 *
 * @param (string) $type Sitemap type.
 *
 * @return (string)
 **/
function secupress_stop_user_enumeration_exclude_yoast_authors( $type ) {
	if ( 'author' === $type && ! has_filter( 'wpseo_sitemap_exclude_author', 'secupress_stop_user_enumeration_empty_array' ) ) {
		add_filter( 'wpseo_sitemap_exclude_author', 'secupress_stop_user_enumeration_empty_array' );
	}

	return $type;
}


add_filter( 'rank_math/sitemap/index/entry', 'secupress_stop_user_enumeration_remove_rank_math_author_entry', 10, 2 );
add_filter( 'rank_math/sitemap/entry', 'secupress_stop_user_enumeration_remove_rank_math_author_entry', 10, 2 );
/**
 * Remove Rank Math author sitemap entries.
 *
 * @since 2.7.3
 * @author Julio Potier
 *
 * @param (array|false) $entry Sitemap entry.
 * @param (string)      $type  Entry type.
 *
 * @return (array|false)
 **/
function secupress_stop_user_enumeration_remove_rank_math_author_entry( $entry, $type ) {
	if ( 'author' === $type || 'user' === $type ) {
		return false;
	}

	return $entry;
}


add_filter( 'aioseo_sitemap_author_archives', 'secupress_stop_user_enumeration_empty_array' );
add_filter( 'aioseo_sitemap_indexes', 'secupress_stop_user_enumeration_remove_aioseo_author_sitemaps' );
/**
 * Remove author sitemaps from the All in One SEO index.
 *
 * @since 2.7.3
 * @author Julio Potier
 *
 * @param (array) $indexes Sitemap index entries.
 *
 * @return (array)
 **/
function secupress_stop_user_enumeration_remove_aioseo_author_sitemaps( $indexes ) {
	$filename = 'sitemap';

	if ( function_exists( 'aioseo' ) ) {
		$aioseo = aioseo();

		if ( is_object( $aioseo ) && isset( $aioseo->sitemap->filename ) && is_string( $aioseo->sitemap->filename ) && '' !== $aioseo->sitemap->filename ) {
			$filename = $aioseo->sitemap->filename;
		}
	}

	return secupress_stop_user_enumeration_without_author_sitemaps( $indexes, $filename );
}


/**
 * Return an empty list of sitemap entries.
 *
 * @since 2.7.3
 * @author Julio Potier
 *
 * @return (array)
 **/
function secupress_stop_user_enumeration_empty_array() {
	return [];
}


/**
 * Drop index entries that point at an author sitemap.
 *
 * @since 2.7.3
 * @author Julio Potier
 *
 * @param (array)  $entries  Index entries.
 * @param (string) $filename Sitemap filename used by the plugin.
 *
 * @return (array)
 **/
function secupress_stop_user_enumeration_without_author_sitemaps( $entries, $filename ) {
	if ( ! is_array( $entries ) ) {
		return $entries;
	}

	$kept = [];

	foreach ( $entries as $entry ) {
		if ( is_array( $entry ) && isset( $entry['loc'] ) && secupress_stop_user_enumeration_is_author_sitemap( $entry['loc'], $filename ) ) {
			continue;
		}

		$kept[] = $entry;
	}

	return $kept;
}


/**
 * Whether a sitemap URL is an author sitemap.
 *
 * @since 2.7.3
 * @author Julio Potier
 *
 * @param (mixed)  $loc      Sitemap URL.
 * @param (string) $filename Sitemap filename used by the plugin.
 *
 * @return (bool)
 **/
function secupress_stop_user_enumeration_is_author_sitemap( $loc, $filename ) {
	if ( ! is_string( $loc ) || '' === $loc ) {
		return false;
	}

	$path = wp_parse_url( $loc, PHP_URL_PATH );

	if ( ! is_string( $path ) ) {
		return false;
	}

	$pattern = '#^author-' . preg_quote( $filename, '#' ) . '[0-9]*\.xml$#i';

	return 1 === preg_match( $pattern, wp_basename( $path ) );
}

