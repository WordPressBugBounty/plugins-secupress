<?php
/**
 * Module Name: Blackhole
 * Description: Catch bots that don't respect your <code>robots.txt</code> rules.
 * Main Module: sensitive_data
 * Author: SecuPress
 * Version: 2.3.19
 */

defined( 'SECUPRESS_VERSION' ) or die( 'Something went wrong.' );

add_action( 'secupress.modules.activate_submodule_' . basename( __FILE__, '.php' ), 'secupress_blackhole_activate_write_robotstxt' );
add_action( 'secupress.modules.activation', 'secupress_blackhole_activate_write_robotstxt' );
/**
 * Add our content to the robots.txt if does exist
 *
 * @author Julio Potier
 * @since 2.2.6
 */
function secupress_blackhole_activate_write_robotstxt() {
	$filesystem = secupress_get_filesystem();
	$filename   = ABSPATH . 'robots.txt';

	if ( ! file_exists( $filename ) ) { // We do not create it, the hook is enough
		return;
	}
	$contents   = $filesystem->get_contents( $filename );
	$contents   = secupress_blackhole_robotstxt_content( $contents );
	$filesystem->put_contents( $filename, $contents );
}

add_action( 'secupress.modules.deactivate_submodule_' . basename( __FILE__, '.php' ), 'secupress_blackhole_deactivate_write_robotstxt' );
add_action( 'secupress.modules.deactivation', 'secupress_blackhole_deactivate_write_robotstxt' );
/**
 * Add our content to the robots.txt if does exist
 *
 * @author Julio Potier
 * @since 2.2.6
 */
function secupress_blackhole_deactivate_write_robotstxt() {
	$filesystem = secupress_get_filesystem();
	$filename   = ABSPATH . 'robots.txt';

	if ( ! file_exists( $filename ) ) { // We do not create it, the hook it enough
		return;
	}
	$contents   = $filesystem->get_contents( $filename );
	$dirname    = secupress_get_hashed_folder_name( basename( __FILE__, '.php' ) );

	if ( false !== strpos( $contents, "User-agent: *\nDisallow: $dirname\n" ) ) {
		$contents  = str_replace( "User-agent: *\nDisallow: $dirname\n", "User-agent: *\n", $contents );
		$filesystem->put_contents( $filename, $contents );
	}
}

add_filter( 'robots_txt', 'secupress_blackhole_robotstxt_content', 20 );
/**
 * Add forbidden URI in `robots.txt` file.
 *
 * @since 2.3.19 remove the conditions because if this is a real file, we can't run that, no big deal, so remove param $forced
 * @since 2.2.6 Add the rule on line 1 if not present
 * @author Julio Potier
 *
 * @since 1.0
 * @author Grégory Viguier
 *
 * @param (string) $output File content.
 *
 * @return (string) File content.
 */
function secupress_blackhole_robotstxt_content( $output ) {
	$dirname = secupress_get_hashed_folder_name( basename( __FILE__, '.php' ) );

	if ( false !== strpos( $output, "User-agent: *\n" ) ) {
		$output  = str_replace( "User-agent: *\n", "User-agent: *\nDisallow: $dirname\n", $output );
	} else {
		$output = "User-agent: *\nDisallow: $dirname\n\n" . $output;
	}

	return $output;
}


add_filter( 'template_include', 'secupress_blackhole_please_click_me', 1 );
/**
 * Use a custom template for our trap.
 *
 * @since 2.2.6 Manage the ban from here with a nonce now
 * @author Julio Potier
 *
 * @since 1.0
 * @author Grégory Viguier
 *
 * @param (string) $template Template path.
 *
 * @return (string) Template path.
 */
function secupress_blackhole_please_click_me( $template ) {
	if ( is_user_logged_in() ) {
		return $template;
	}

	$url     = trailingslashit( secupress_get_current_url() );
	$dirname = secupress_get_hashed_folder_name( basename( __FILE__, '.php' ) );

	if ( isset( $_GET['token'] ) && wp_verify_nonce( $_GET['token'], 'ban_me_please-' . date( 'ymdhi' ) ) ) {
		$ip      = secupress_get_ip( 'REMOTE_ADDR' );
		$ban_ips = get_site_option( SECUPRESS_BAN_IP );

		if ( ! is_array( $ban_ips ) ) {
			$ban_ips = array();
		}

		$ban_ips[ $ip ] = time() + MONTH_IN_SECONDS;

		update_site_option( SECUPRESS_BAN_IP, $ban_ips );

		/* This hook is documented in /inc/functions/admin.php */
		do_action( 'secupress.ban.ip_banned', $ip, $ban_ips );

		secupress_log_attack( 'bad_robots' );

		wp_die( 'Something went wrong.' ); // Do not use secupress_die() here.
	}

	if ( substr( $url, - strlen( $dirname ) ) === $dirname ) {
		add_filter( 'nonce_user_logged_out', 'secupress_modify_userid_for_nonces' );
		return dirname( __FILE__ ) . '/inc/php/blackhole/warning-template.php';
	}

	return $template;
}
