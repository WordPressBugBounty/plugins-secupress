<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

/** --------------------------------------------------------------------------------------------- */
/** ON MODULE SETTINGS SAVE ===================================================================== */
/** --------------------------------------------------------------------------------------------- */

/**
 * Return the values/labels used for the backups storage setting.
 *
 * @since 1.0
 *
 * @return (array) An array with back types as keys and labels as values.
 */
function secupress_backups_storage_labels() {
	return array(
		'local'     => __( 'Local', 'secupress' ),
		// 'dropbox'   => __( 'Dropbox', 'secupress' ), ////
		// 'amazons3'  => __( 'Amazon S3', 'secupress' ), ////
		// 'rackspace' => __( 'Rackspace Cloud', 'secupress' ), ////
	);
}

/**
 * Get the legacy backups parent folder path (inside wp-content).
 *
 * @author Julio Potier
 * @since 2.7
 *
 * @return (string) The absolute path to the legacy backups parent folder. The path has a trailing slash.
 */
function secupress_get_legacy_parent_backups_path() {
	return untrailingslashit( wp_normalize_path( WP_CONTENT_DIR ) ) . '/backups/';
}

/**
 * Resolve the backups parent folder path.
 *
 * Prefers a folder one level above the web root when it is allowed and writable.
 *
 * @author Julio Potier
 * @since 2.7
 *
 * @param (bool) $get_reason Set to true to get the fallback reason instead of the path.
 *
 * @return (string) The absolute path with a trailing slash, or a fallback reason.
 */
function secupress_resolve_parent_backups_path( $get_reason = false ) {
	static $path;
	static $reason = '';

	if ( isset( $path ) ) {
		return $get_reason ? $reason : $path;
	}

	$legacy    = secupress_get_legacy_parent_backups_path();
	$home_path = untrailingslashit( wp_normalize_path( secupress_get_home_path() ) );
	$parent    = dirname( $home_path );

	if ( '/' === $parent || $parent === $home_path || preg_match( '@^[A-Za-z]:/?$@', $parent ) ) {
		$reason = '';
		$path   = $legacy;
		return $get_reason ? $reason : $path;
	}

	$candidate = $parent . '/secupress-backups/';

	if ( ! secupress_path_is_outside_web_root( $candidate ) ) {
		$reason = '';
		$path   = $legacy;
		return $get_reason ? $reason : $path;
	}

	if ( ! secupress_path_is_allowed_by_open_basedir( $candidate ) ) {
		$reason = 'open_basedir';
		$path   = $legacy;
		return $get_reason ? $reason : $path;
	}

	if ( ! secupress_mkdir_p( $candidate ) || ! wp_is_writable( untrailingslashit( $candidate ) ) ) {
		$reason = 'not_writable';
		$path   = $legacy;
		return $get_reason ? $reason : $path;
	}

	$reason = '';
	$path   = $candidate;

	return $get_reason ? $reason : $path;
}

/**
 * Tell if a path is outside the web root.
 *
 * @author Julio Potier
 * @since 2.7
 *
 * @param (string) $path An absolute path.
 *
 * @return (bool)
 */
function secupress_path_is_outside_web_root( $path ) {
	$path      = trailingslashit( wp_normalize_path( $path ) );
	$home_path = trailingslashit( wp_normalize_path( secupress_get_home_path() ) );

	if ( $path === $home_path || 0 === strpos( $path, $home_path ) ) {
		return false;
	}

	$abspath = trailingslashit( wp_normalize_path( ABSPATH ) );

	if ( $path === $abspath || 0 === strpos( $path, $abspath ) ) {
		return false;
	}

	if ( ! empty( $_SERVER['DOCUMENT_ROOT'] ) ) {
		$doc_root = wp_normalize_path( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) );

		if ( $doc_root && @is_dir( $doc_root ) ) {
			$doc_root = trailingslashit( $doc_root );

			if ( $path === $doc_root || 0 === strpos( $path, $doc_root ) ) {
				return false;
			}
		}
	}

	return true;
}

/**
 * Get backups parent folder path.
 *
 * @since 2.7 Prefer a folder outside the web root when possible.
 * @since 1.0
 * @author Grégory Viguier
 *
 * @param (bool) $relative Set to true to get the path relative to the site's root.
 *
 * @return (string) The absolute (or relative) path to the backups parent folder. The path has a trailing slash.
 */
function secupress_get_parent_backups_path( $relative = false ) {
	static $abs_path;
	static $rel_path;

	if ( ! isset( $abs_path ) ) {
		$abs_path = secupress_resolve_parent_backups_path();
		$rel_path = str_replace( rtrim( wp_normalize_path( ABSPATH ), '/' ), '', $abs_path );
		$legacy   = secupress_get_legacy_parent_backups_path();

		if ( $abs_path !== $legacy && is_dir( untrailingslashit( $legacy ) ) ) {
			secupress_migrate_backups_folder( $legacy, $abs_path );
		}
	}

	return $relative ? $rel_path : $abs_path;
}

/**
 * Tell if the backups parent folder is outside the web root.
 *
 * @author Julio Potier
 * @since 2.7
 *
 * @return (bool)
 */
function secupress_backups_path_is_outside_web_root() {
	return secupress_get_parent_backups_path() !== secupress_get_legacy_parent_backups_path();
}

/**
 * Get the reason why backups stay in the legacy folder.
 *
 * @author Julio Potier
 * @since 2.7
 *
 * @return (string) 'open_basedir', 'not_writable', or an empty string.
 */
function secupress_get_parent_backups_fallback_reason() {
	return (string) secupress_resolve_parent_backups_path( true );
}

/**
 * Move existing backups from an old parent folder to a new one.
 *
 * @author Julio Potier
 * @since 2.7
 *
 * @param (string) $old_parent The old parent folder.
 * @param (string) $new_parent The new parent folder.
 *
 * @return (bool)
 */
function secupress_migrate_backups_folder( $old_parent, $new_parent ) {
	$old_parent = trailingslashit( wp_normalize_path( $old_parent ) );
	$new_parent = trailingslashit( wp_normalize_path( $new_parent ) );

	if ( $old_parent === $new_parent ) {
		return false;
	}

	$old_dir = untrailingslashit( $old_parent );

	if ( ! is_dir( $old_dir ) ) {
		return false;
	}

	$old_local = secupress_get_hashed_folder_name( 'backup', $old_parent );
	$new_local = secupress_get_hashed_folder_name( 'backup', $new_parent );

	if ( is_dir( untrailingslashit( $old_local ) ) ) {
		if ( ! secupress_mkdir_p( $new_local ) ) {
			return false;
		}

		$files      = glob( $old_local . '*.{zip,sql}', GLOB_BRACE );
		$filesystem = secupress_get_filesystem();

		if ( $files ) {
			foreach ( $files as $file ) {
				if ( ! $filesystem->move( $file, $new_local . basename( $file ), true ) ) {
					return false;
				}
			}
		}
	}

	$filesystem = secupress_get_filesystem();
	foreach ( array( '.htaccess', 'web.config' ) as $protect_file ) {
		$protect_path = $old_parent . $protect_file;
		if ( $filesystem->exists( $protect_path ) ) {
			$filesystem->delete( $protect_path );
		}
	}

	secupress_rrmdir( $old_dir );

	return true;
}
