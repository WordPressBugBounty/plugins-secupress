<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

$this->set_current_section( 'secupress_security_status' );
$this->add_section( __( 'Security Status', 'secupress' ), array( 'with_save_button' => false ) );

$is_paused      = secupress_is_security_paused();
$toggle_url     = wp_nonce_url( admin_url( 'admin-post.php?action=secupress_toggle_security_pause' ), 'secupress_toggle_security_pause' );
$modules_count  = secupress_count_active_submodules();
$has_modules    = $modules_count > 0;
$icon_file      = 'logo.png';
$button_disabled = ! $has_modules && ! $is_paused;
$button_class    = $is_paused ? 'secupress-button secupress-button-primary' : 'secupress-button secupress-button-secondary';
$button_text     = $is_paused ? __( 'Re-activate Security', 'secupress' ) : __( 'Pause Security', 'secupress' );
if ( $button_disabled ) {
	$button_class .= ' disabled';
} elseif ( ! $is_paused ) {
	$button_class .= ' secupress-pause-security';
}
$modules_labels = secupress_get_active_submodule_labels();
$attacks        = secupress_get_attacks( 'all' );
$attacks_total  = is_array( $attacks ) && isset( $attacks['all'] ) && is_numeric( $attacks['all'] ) ? (int) $attacks['all'] : 0;
$attacks_text   = sprintf(
	_n( '%s blocked attack', '%s blocked attacks', $attacks_total, 'secupress' ),
	number_format_i18n( $attacks_total )
);

if ( $is_paused ) {
	$status_class   = 'secupress-security-status-paused';
	$icon_file      = 'unlocked.png';
	$icon_alt       = __( 'Unlocked', 'secupress' );
	$message        = __( 'Security is paused', 'secupress' );
	$modules_text   = sprintf( _n( '%s paused module', '%s paused modules', $modules_count, 'secupress' ), number_format_i18n( $modules_count ) );
	$descriptions   = [ __( 'Website exposed. PHP protection is off. .htaccess & robots.txt rules remain.', 'secupress' ) ];
	$remaining      = secupress_get_security_pause_remaining_text();
	if ( $remaining ) {
		$descriptions[] = $remaining;
	}
} elseif ( $has_modules ) {
	$status_class = 'secupress-security-status-active';
	if ( secupress_has_pro() ) {
		$icon_file = 'logo-pro2x.png';
	}
	$icon_alt     = __( 'Locked', 'secupress' );
	$message      = __( 'Security is active', 'secupress' );
	$modules_text = sprintf( _n( '%s active module', '%s active modules', $modules_count, 'secupress' ), number_format_i18n( $modules_count ) );
	$descriptions = [ __( 'Real-time defense is on.', 'secupress' ) ];
} else {
	$status_class = 'secupress-security-status-idle';
	if ( secupress_has_pro() ) {
		$icon_file = 'unlocked.png';
	}
	$icon_alt     = __( 'No protection', 'secupress' );
	$message      = __( 'No protection applied', 'secupress' );
	$modules_text = sprintf( _n( '%s active module', '%s active modules', $modules_count, 'secupress' ), number_format_i18n( $modules_count ) );
	$descriptions = [ sprintf( __( '%s is running, but no module is protecting the site.', 'secupress' ), SECUPRESS_PLUGIN_NAME ) ];
}

$html  = '<p></p><div class="secupress-security-status ' . $status_class . '">';
$html .= '<img class="secupress-security-status-icon" src="' . esc_url( SECUPRESS_ADMIN_IMAGES_URL . $icon_file ) . '" alt="' . esc_attr( $icon_alt ) . '" width="56" height="56" />';
$html .= '<div class="secupress-security-status-texts">';
$html .= '<div class="secupress-security-status-heading">';
$html .= '<p class="secupress-security-status-message">' . esc_html( $message ) . '</p>';
$counts = secupress_get_scanner_counts();
if ( secupress_show_grade_system() && ( $counts['good'] || $counts['bad'] ) ) {
	$grade_rgb   = implode( ',', array_map( 'absint', array_slice( explode( ',', $counts['color'] ), 0, 3 ) ) );
	$grade_label = sprintf( __( 'Grade %s', 'secupress' ), $counts['grade'] );
	$html       .= '<span class="secupress-security-status-count secupress-security-status-grade" style="--secupress-grade-color:' . $grade_rgb . '" title="' . esc_attr( $counts['text'] ) . '">' . esc_html( $grade_label ) . '</span>';
}
$html .= '<span class="secupress-security-status-count">' . esc_html( $modules_text );
if ( $modules_labels ) {
	$html .= ' <span class="secupress-security-status-info" tabindex="0">';
	$html .= '<span class="dashicons dashicons-info" aria-hidden="true"></span>';
	$html .= '<span class="screen-reader-text">' . esc_html( implode( ', ', $modules_labels ) ) . '</span>';
	$html .= '<span class="secupress-security-status-tooltip"><span class="secupress-security-status-tooltip-inner">';
	foreach ( $modules_labels as $label ) {
		$html .= '<span class="secupress-security-status-tooltip-item">' . esc_html( $label ) . '</span>';
	}
	$html .= '</span></span>';
	$html .= '</span>';
}
$html .= '</span>';
$html .= '<span class="secupress-security-status-count">' . esc_html( $attacks_text );
if ( secupress_is_attacks_dashboard_widget_visible() ) {
	$html .= ' <a class="secupress-security-status-more" href="' . esc_url( admin_url( 'index.php#secupress-attacks-widget' ) ) . '" title="' . esc_attr__( 'More details', 'secupress' ) . '">';
	$html .= '<span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>';
	$html .= '<span class="screen-reader-text">' . __( 'More details', 'secupress' ) . '</span>';
	$html .= '</a>';
}
$html .= '</span>';
$html .= '</div>';
$html .= '<div class="secupress-security-status-description">' . esc_html( implode( ' ', $descriptions ) ) . '</div>';
$html .= '</div>';
if ( $button_disabled ) {
	$html .= '<span class="' . $button_class . '" aria-disabled="true">' . esc_html( $button_text ) . '</span>';
} else {
	$html .= '<a class="' . $button_class . '" href="' . esc_url( $toggle_url ) . '">' . esc_html( $button_text ) . '</a>';
}
$html .= '</div>';

$this->add_field( array(
	'name'      => $this->get_field_name( 'toggle' ),
	'type'      => 'html',
	'row_class' => 'secupress-security-status-row',
	'value'     => $html,
) );
