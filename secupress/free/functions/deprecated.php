<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

# DEPRECATED FUNCTIONS

/**
 * Deprecated constants.
 * Be aware that they are not defined as soon as the plugin loads anymore.
 */
define( 'SECUPRESS_SCAN_SLUG',           'secupress_scanners' );  // Since 1.3.
define( 'SECUPRESS_FIX_SLUG',            'secupress_fixes' );     // Since 1.3.
define( 'SECUPRESS_SCAN_FIX_SITES_SLUG', 'secupress_fix_sites' ); // Since 1.3.

/**
 * Send an email message to our awesome support team (yes it is).
 *
 * @since 1.1.1
 * @since 1.1.4 Deprecated.
 * @author Grégory Viguier
 *
 * @param (string) $summary     A title. The value is not escaped.
 * @param (string) $description A message. The value has been sanitized with `wp_kses()`.
 * @param (array)  $data        An array of infos related to the site.
 */
function secupress_send_support_request( $summary, $description, $data ) {
	_deprecated_function( __FUNCTION__, '1.1.4', 'secupress_pro_send_support_request()' );
}


/**
 * Will lately add admin notices added by `secupress_add_transient_notice()`.
 *
 * @since 1.3 Deprecated.
 * @since 1.0
 * @author Julio Potier
 */
function secupress_display_transient_notices() {
	_deprecated_function( __FUNCTION__, '1.3', 'SecuPress_Admin_Notices::get_instance()->add_transient_notices()' );
}


/**
 * This warning is displayed when the license is not valid.
 *
 * @since 1.3 Deprecated.
 * @since 1.0.6
 * @author Grégory Viguier
 */
function secupress_warning_no_license() {
	_deprecated_function( __FUNCTION__, '1.3', 'SecuPress_Pro_Admin_Free_Downgrade::get_instance()->maybe_warn_no_license()' );
}


/**
 * Get a user name.
 * Try first to have first name + last name, then only first name or last name, then only last name or first name, then display name.
 *
 * @since 1.1.4 Deprecated.
 * @since 1.1.1
 * @author Grégory Viguier
 *
 * @param (object) $user A WP_User object.
 *
 * @return (string)
 */
function secupress_get_user_full_name( $user ) {
	_deprecated_function( __FUNCTION__, '1.1.4' );
}


/**
 * Get name & version of all active plugins.
 *
 * @since 1.1.4 Deprecated.
 * @since 1.0.6
 * @author Grégory Viguier
 *
 * @return (array) An array of active plugins: name and version.
 */
function secupress_get_active_plugins() {
	_deprecated_function( __FUNCTION__, '1.1.4' );
}

/**
 * Get contents to put in the `.htaccess` file to ban IPs.
 *
 * @since 1.4.9 Deprecated.
 * @since 1.0
 * @author Julio Potier
 *
 * @return (string)
 */
function secupress_get_htaccess_ban_ip() {
	_deprecated_function( __FUNCTION__, '1.4.9' );
}


/**
 * Update the 2 files for GeoIP database on demand
 *
 * @since 2.1 Deprecated.
 * @since 1.4.9
 * @author Julio Potier
 **/
function secupress_geoips_update_datafiles() {
	_deprecated_function( __FUNCTION__, '2.1', 'secupress_geoips_update_datafile' );
	secupress_geoips_update_datafile();
}

/**
 * Clean the unzipped files
 *
 * @since 2.3.13 Deprecated.
 * @since 2.1
 * @author Julio Potier
 **/
function secupress_geoip_clean_zip() { // deprecated
	_deprecated_function( __FUNCTION__, '2.3.13' );
}


/**
 * Parse files content from download.maxmind.com
 *
 * @since 2.3.13 Deprecated.
 * @since 2.1 Compat with new provider
 * @since 1.4.9
 * @author Julio Potier
 *
 * @param (string) $file (was (array) $lines)
 * @param (string) $type
 * @return
 **/
function secupress_geoips_parse_file( $file, $type ) {
	_deprecated_function( __FUNCTION__, '2.3.13' );
}

/**
 * Has been renamed to secupress_find_mu_plugin
 * @since 2.3.16 Deprecated.
*/
function secupress_find_muplugin( $filename ) {
	_deprecated_function( __FUNCTION__, '2.3.16', 'secupress_find_mu_plugin' );
	 return secupress_find_mu_plugin( $filename );
}
