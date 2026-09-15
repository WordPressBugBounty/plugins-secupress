<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

if ( ! secupress_show_adminbar() ) {
	return;
}

add_action( 'admin_bar_menu', 'secupress_admin_bar', 100 );
add_action( 'wp_head', 'secupress_admin_bar_css' );
add_action( 'admin_head', 'secupress_admin_bar_css' );
/**
 * Print CSS for the admin bar (paused state, new items tied to undismissed pointers).
 *
 * @since 2.7 New items (`+` prefix).
 * @since 2.7
 * @author Julio Potier
 */
function secupress_admin_bar_css() {
	if ( ! is_admin_bar_showing() || ! current_user_can( secupress_get_capability() ) ) {
		return;
	}

	$css  = '#wpadminbar .secupress-new-notice a,#wpadminbar .secupress-new-notice .ab-item,#wpadminbar .secupress-new-item{color:#F1C40F!important;text-shadow:-1px -1px 0 #000,1px -1px 0 #000,-1px 1px 0 #000,1px 1px 0 #000;font-weight:600}';
	$css .= '#wpadminbar .secupress-tour-count,#adminmenu .secupress-tour-count{display:inline-block;min-width:18px;height:18px;margin:0 0 0 6px;padding:0 6px;border-radius:9px;background:#F7AB13;color:#fff!important;font-size:11px;line-height:18px;font-weight:600;text-align:center;vertical-align:middle;text-shadow:none}';

	if ( secupress_is_security_paused() ) {
		$css .= '#wpadminbar #wp-admin-bar-secupress.secupress-security-paused>.ab-item{background:#F2295E;color:#fff}#wpadminbar #wp-admin-bar-secupress-resume-security .ab-item{color:#F2295E!important;font-weight:600}';
	}

	echo '<style id="secupress-admin-bar">' . $css . '</style>';
}

/**
 * Add menu in tool bar.
 *
 * @since 1.0
 *
 * @param (object) $wp_admin_bar WP_Admin_Bar object.
 */
function secupress_admin_bar( $wp_admin_bar ) {
	if ( ! current_user_can( secupress_get_capability() ) ) {
		return;
	}

	// Add a counter of scans with good result.
	$counts = secupress_get_scanner_counts();

	if ( secupress_show_grade_system() && ( $counts['good'] || $counts['bad'] ) ) {
		$grade = sprintf( __( 'Grade %s', 'secupress' ), '<span class="letter">' . $counts['grade'] . '</span>' );
	} else {
		$grade = '';
	}

	// Parent.
	$wp_admin_bar->add_menu( array(
		'id'    => 'secupress',
		'title' => '<span class="ab-icon dashicons-shield-alt"></span><span class="screen-reader-text">' . SECUPRESS_PLUGIN_NAME . ' </span>' . $grade,
		'meta'  => secupress_is_security_paused() ? [ 'class' => 'secupress-security-paused' ] : [],
	) );

	if ( secupress_is_security_paused() ) {
		$resume_url = wp_nonce_url( admin_url( 'admin-post.php?action=secupress_toggle_security_pause' ), 'secupress_toggle_security_pause' );
		$wp_admin_bar->add_menu( array(
			'parent' => 'secupress',
			'id'     => 'secupress-resume-security',
			'title'  => __( 'Security is paused — Activate', 'secupress' ),
			'href'   => $resume_url,
		) );
	}

	// Scanners.
	$wp_admin_bar->add_menu( array(
		'parent' => 'secupress',
		'id' 	 => 'secupress-scanners',
		'title'  => __( 'Scanners', 'secupress' ),
		'href'   => esc_url( secupress_admin_url( 'scanners' ) ),
	) );
	// Sub-Scanners.
	$wp_admin_bar->add_menu( array(
		'parent' => 'secupress-scanners',
		'id' 	 => 'secupress-scanners-step1',
		'title'  => __( 'Step 1 – Site Health', 'secupress' ),
		'href'   => esc_url( secupress_admin_url( 'scanners', '&step=1' ) ),
	) );
	$wp_admin_bar->add_menu( array(
		'parent' => 'secupress-scanners',
		'id' 	 => 'secupress-scanners-step2',
		'title'  => __( 'Step 2 – Auto-Fix', 'secupress' ),
		'href'   => esc_url( secupress_admin_url( 'scanners', '&step=2' ) ),
		'meta'   => [ 'class' => secupress_is_pro() ? '' : 'secupress-pro-notice' ],
	) );
	$wp_admin_bar->add_menu( array(
		'parent' => 'secupress-scanners',
		'id' 	 => 'secupress-scanners-step3',
		'title'  => __( 'Step 3 – Manual Operations', 'secupress' ),
		'href'   => esc_url( secupress_admin_url( 'scanners', '&step=3' ) ),
	) );
	$wp_admin_bar->add_menu( array(
		'parent' => 'secupress-scanners',
		'id' 	 => 'secupress-scanners-step4',
		'title'  => __( 'Step 4 – Resolution Report', 'secupress' ),
		'href'   => esc_url( secupress_admin_url( 'scanners', '&step=4' ) ),
	) );
	$wp_admin_bar->add_menu( array(
		'parent' => 'secupress-scanners',
		'id' 	 => 'secupress-scanners-pdf',
		'title'  => __( 'Export Site Health report as PDF', 'secupress' ),
		'href'   => esc_url( secupress_admin_url( 'scanners', '#secupress-step-content-footer' ) ),
		'meta'   => [ 'class' => secupress_is_pro() ? '' : 'secupress-pro-notice' ],
	) );

	if ( ! class_exists( 'SecuPress_Admin_Pointers' ) ) {
		secupress_require_class( 'Admin', 'Pointers' );
	}
	$modules_url   = SecuPress_Admin_Pointers::get_tour_modules_url();
	$modules_badge = SecuPress_Admin_Pointers::get_tour_badge_html();

	// Modules.
	$wp_admin_bar->add_menu( array(
		'parent' => 'secupress',
		'id' 	 => 'secupress-modules',
		'title'  => __( 'Modules', 'secupress' ) . $modules_badge,
		'href'   => esc_url( $modules_url ),
	) );

	// Sub-Modules.
	$modules   = secupress_get_modules();
	$dismissed = array_filter( explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) ) );
	foreach ( $modules as $module_slug => $module ) {
		$wp_admin_bar->add_menu( array(
			'parent' => 'secupress-modules',
			'id' 	 => 'secupress-modules-' . $module_slug,
			'title'  => '<span class="ab-icon dashicons dashicons-' . $module['dashicon'] . '" style="font-size: 17px"></span>' . $module['title'],
			'href'   => ! isset( $module['href'] ) ?
						esc_url( secupress_admin_url( 'modules', $module_slug ) ) :
						esc_url( $module['href'] ),
			'meta'   => [ 'class'  => ! isset( $module['mark_as_pro'] ) || ! $module['mark_as_pro'] || secupress_is_pro() ? '' : 'secupress-pro-notice',
						'target' => ! isset( $module['href'] ) ? '' : '_blank', ]
		) );

		if ( empty( $module['submodules'] ) ) {
			continue;
		}

		foreach ( $module['submodules'] as $submodule_slug => $submodule ) {
			if ( ! $submodule ) {
				continue;
			}
			$item_class  = [];
			$new_pointer = '';
			if ( preg_match( '/\+([a-z0-9_-]+)\+/i', $submodule, $new_match ) ) {
				$new_pointer = sanitize_key( $new_match[1] );
				$submodule   = str_replace( $new_match[0], '', $submodule );
			}
			if ( false !== strpos( $submodule, '*' ) && ! secupress_is_pro() ) {
				$item_class[] = 'secupress-pro-notice';
			}
			$title = str_replace( [ '*', '&rsaquo; >' ], [ '', '&nbsp;&raquo; ' ], '&rsaquo; ' . $submodule );
			if ( $new_pointer && ! in_array( $new_pointer, $dismissed, true ) ) {
				$item_class[] = 'secupress-new-notice';
				$title        = '<span class="secupress-new-item">' . $title . '</span>';
			}
			$wp_admin_bar->add_menu( array(
				'parent' => 'secupress-modules-' . $module_slug,
				'id'     => 'secupress-submodules-' . $submodule_slug,
				'title'  => $title,
				'href'   => esc_url( secupress_admin_url( 'modules', $module_slug . '#' . $submodule_slug ) ),
				'meta'   => [ 'class' => implode( ' ', $item_class ) ],
			) );
		}
	}

	if ( class_exists( 'SecuPress_Logs' ) ) {
		// Logs.
		$wp_admin_bar->add_menu( array(
			'parent' => 'secupress',
			'id' 	 => 'secupress-logs',
			'title'  => _x( 'Logs', 'post type general name', 'secupress' ),
			'href'   => esc_url( secupress_admin_url( 'logs' ) ),
		) );
		// Only add sub level menus if the 2 logs types are displayed.
		if ( 2 === count( SecuPress_Logs::get_log_types() ) ) {
			// Sub-Logs.
			$wp_admin_bar->add_menu( array(
				'parent' => 'secupress-logs',
				'id' 	 => 'secupress-logs-action',
				'title'  => __( 'Actions Logs', 'secupress' ),
				'href'   => esc_url( secupress_admin_url( 'logs' ) ),
			) );
			$wp_admin_bar->add_menu( array(
				'parent' => 'secupress-logs',
				'id' 	 => 'secupress-logs-404',
				'title'  => __( '404 Logs', 'secupress' ),
				'href'   => esc_url( secupress_admin_url( 'logs', '&tab=err404' ) ),
			) );
		}
	}

	if ( ! secupress_has_pro() ) {
		$title  = __( 'More Security', 'secupress' );
		$href   = secupress_admin_url( 'get-pro' );
		$target = '_blank';
	} else {
		$title = __( 'Add my license', 'secupress' );
		$href  = secupress_admin_url( 'modules' ) . '#module-secupress_display_apikey_options';
		$target = '_self';
	}

	if ( ! secupress_is_pro() ) {
		$wp_admin_bar->add_menu( array(
			'parent' => 'secupress',
			'id'     => 'secupress-modules-get-pro',
			'title'  => '<span class="ab-icon dashicons dashicons-star-filled" style="font-size: 17px"></span>' . $title,
			'href'   => $href,
			'meta'   => [ 'class'  => 'secupress-pro-notice',
						'target' => $target, ],
		) );
	}
}
