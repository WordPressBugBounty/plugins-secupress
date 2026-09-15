<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

add_action( 'wp_dashboard_setup', 'secupress_attacks_dashboard_widget' );
add_action( 'wp_dashboard_setup', 'secupress_system_dashboard_widget' );
/**
 * Adds a custom dashboard widget that displays blocked attack by SecuPress
 *
 * @author Julio Potier
 * @since 2.2.6
 * 
 **/
function secupress_attacks_dashboard_widget() {
    if ( ! current_user_can( secupress_get_capability() ) ) {
        return;
    }

	$attacks = secupress_get_attacks( 'all' );
    wp_add_dashboard_widget( 
        "secupress-attacks-widget",
        sprintf( __( '%s Blocked Attacks', 'secupress' ), SECUPRESS_PLUGIN_NAME ),
        "secupress_attacks_render_dashboard_widget",
        "secupress_attacks_widget_control", // $control_callback
        $attacks
    );
}

/**
 * Tell if the attacks dashboard widget is visible for the current user.
 *
 * @since 2.7
 * @author Julio Potier
 *
 * @return (bool)
 */
function secupress_is_attacks_dashboard_widget_visible() {
	if ( ! current_user_can( secupress_get_capability() ) ) {
		return false;
	}
	if ( ! has_action( 'wp_dashboard_setup', 'secupress_attacks_dashboard_widget' ) ) {
		return false;
	}
	if ( function_exists( 'get_hidden_meta_boxes' ) ) {
		$hidden = get_hidden_meta_boxes( 'dashboard' );
		if ( in_array( 'secupress-attacks-widget', (array) $hidden, true ) ) {
			return false;
		}
	}
	return true;
}

/**
 * Control callback for the dashboard widget settings
 *
 * @author Julio Potier
 * @since 2.4.1
 **/
function secupress_attacks_widget_control() {
    $chart_months = get_user_meta( get_current_user_id(), 'secupress_attacks_widget_chart_months', true );
	if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['widget_id'], $_POST['dashboard-widget-nonce'], $_POST['secupress_attacks_widget_chart_months'] ) ) {
        check_admin_referer( 'edit-dashboard-widget_' . $_POST['widget_id'], 'dashboard-widget-nonce' );
		
        $chart_months = secupress_minmax_range( absint( $_POST['secupress_attacks_widget_chart_months'] ), 0, 12 );
        update_user_meta( get_current_user_id(), 'secupress_attacks_widget_chart_months', $chart_months );
	}
	?>
	<p>
		<label>
			<?php esc_html_e( 'Months to display (0 to hide charts):', 'secupress' ); ?>
			<input type="number" name="secupress_attacks_widget_chart_months" value="<?php echo esc_attr( $chart_months ); ?>" min="0" max="12" step="1" />
		</label>
	</p>
	<?php 
    wp_nonce_field( 'secupress_attacks_widget_control', 'secupress_attacks_widget_nonce' );
}

/**
 * Callback function to render the contents of our custom dashboard widget.
 *
 * @since 2.3.17 $attacks param
 * @since 2.2.6
 * @author Julio Potier
 * 
 * @param (array) $attacks
 * @return string HTML markup to be displayed in the widget.
 **/
/**
 * Get the last X months in MMDD format (starting from current month going back)
 *
 * @param int $x Number of months to get (default 6)
 * @return array Array of month codes (e.g., ['11', '10', '09', '08', '07', '06'])
 */
function secupress_get_last_x_months( $x = 6 ) {
    $months = [];
    $current_date = new DateTime();
    
    // Get last X months starting from current month going back
    for ( $i = 0; $i < $x; $i++ ) {
        $date = clone $current_date;
        $date->modify( '-' . $i . ' months' );
        $month_code = $date->format( 'm' );
        $months[] = $month_code;
    }
    
    return array_reverse( $months );
}

/**
 * Extract monthly data for a specific attack type
 *
 * @param array $attack_data Attack data (array with dates)
 * @param array $months Array of month codes
 * @return array Array of values for each month
 */
function secupress_extract_monthly_data( $attack_data, $months ) {
    $monthly_data = [];
    
    if ( ! is_array( $attack_data ) || empty( $attack_data ) ) {
        // No data available, return zeros for all months
        $months_count = count( $months );
        for ( $i = 0; $i < $months_count; $i++ ) {
            $monthly_data[] = 0;
        }
        return $monthly_data;
    }
    
    foreach ( $months as $month ) {
        $total = 0;
        // Sum all values for this month (dates starting with month code)
        foreach ( $attack_data as $date => $value ) {
            if ( is_numeric( $date ) && strlen( $date ) === 4 && substr( $date, 0, 2 ) === $month ) {
                $total += (int) $value;
            }
        }
        $monthly_data[] = $total;
    }
    
    return $monthly_data;
}

/**
 * Calculate total from attack data
 *
 * @param array $attack_data Attack data (array with dates)
 * @return int Total count
 */
function secupress_calculate_attack_total( $attack_data ) {
    if ( ! is_array( $attack_data ) || empty( $attack_data ) ) {
        return 0;
    }
    
    $total = 0;
    foreach ( $attack_data as $value ) {
        if ( is_numeric( $value ) ) {
            $total += (int) $value;
        }
    }
    return $total;
}

/**
 * Prepare chart data for the dashboard widget
 *
 * @since 2.4.1
 * @author Julio Potier
 *
 * @return array Chart data array or empty array if charts are disabled
 */
function secupress_prepare_widget_chart_data() {
    $chart_months = get_user_meta( get_current_user_id(), 'secupress_attacks_widget_chart_months', true );
    $chart_months = ! $chart_months ? 6 : (int) $chart_months;
    
    if ( $chart_months <= 0 ) {
        return [];
    }
    
    $attacks = secupress_get_attacks();
    if ( ! is_array( $attacks ) || empty( $attacks ) ) {
        return [];
    }
    
    // Remove "all" from attacks array if it exists
    $other_attacks = $attacks;
    if ( isset( $other_attacks['all'] ) ) {
        unset( $other_attacks['all'] );
    }
    
    if ( empty( $other_attacks ) ) {
        return [];
    }
    
    $months = secupress_get_last_x_months( $chart_months );
    $month_labels = [];
    $current_date = new DateTime();
    for ( $i = $chart_months - 1; $i >= 0; $i-- ) {
        $date = clone $current_date;
        $date->modify( '-' . $i . ' months' );
        $month_labels[] = date_i18n( 'M', $date->getTimestamp() );
    }
    
    $chart_data = [];
    $chart_index = 0;
    
    foreach ( $other_attacks as $type => $attack_data ) {
        $monthly_data = secupress_extract_monthly_data( $attack_data, $months );
        $chart_id = 'secupress-chart-' . $chart_index++;
        
        $chart_data[] = [
            'id' => $chart_id,
            'labels' => $month_labels,
            'data' => $monthly_data,
        ];
    }
    
    return $chart_data;
}

function secupress_attacks_render_dashboard_widget( $attacks = null ) {
    $attacks = $attacks ?: secupress_get_attacks();
    
    $chart_months = get_user_meta( get_current_user_id(), 'secupress_attacks_widget_chart_months', true );
    $chart_months = ! $chart_months ? 6 : (int) $chart_months;
    $show_charts = $chart_months > 0;
    
    // Prepare chart data using the helper function
    $chart_data = $show_charts ? secupress_prepare_widget_chart_data() : [];
    
    $months = $show_charts ? secupress_get_last_x_months( $chart_months ) : [];
    $month_labels = [];
    if ( $show_charts ) {
        $current_date = new DateTime();
        for ( $i = $chart_months - 1; $i >= 0; $i-- ) {
            $date = clone $current_date;
            $date->modify( '-' . $i . ' months' );
            $month_labels[] = date_i18n( 'M', $date->getTimestamp() );
        }
    }

    // Map attack types to dashicons
    $type_icons = [
        'all'                 => 'dashicons-shield',
        'ban_ip'              => 'dashicons-shield-alt',
        'plugins'             => 'dashicons-admin-plugins',
        'theme'               => 'dashicons-admin-appearance',
        'zipfile'             => 'dashicons-media-archive',
        'xmlrpc'              => 'dashicons-rss',
        'move_login'          => 'dashicons-lock',
        'loginattempts'       => 'dashicons-admin-users',
        'passwordspraying'    => 'dashicons-privacy',
        'users'               => 'dashicons-groups',
        'bad_robots'          => 'dashicons-admin-generic',
        'bad_request_content' => 'dashicons-warning',
        'unknown'             => 'dashicons-editor-help',
    ];

    // Get plugin name (handles White Label) and logo
    $plugin_name = SECUPRESS_PLUGIN_NAME . ( secupress_has_pro() && ! secupress_is_white_label() ? ' Pro' : '' );
    $logo_url = secupress_get_logo( [], 'url' );

    // Get total for "All" block (always displayed) - use the "all" index directly
    $all_attacks = secupress_get_attacks( 'all' );
    $all_total = 0;
    if ( is_array( $all_attacks ) && isset( $all_attacks['all'] ) ) {
        // "all" is a simple number (non-dated cumulative total)
        $all_total = is_numeric( $all_attacks['all'] ) ? (int) $all_attacks['all'] : 0;
    }
    
    // Prepare monthly data for "All" (still calculated from individual types since "all" is not dated)
    $all_monthly_data = [];
    if ( $show_charts ) {
        foreach ( $months as $month ) {
            $month_total = 0;
            if ( is_array( $all_attacks ) ) {
                foreach ( $all_attacks as $type => $data ) {
                    if ( 'all' !== $type ) {
                        $monthly = secupress_extract_monthly_data( $data, [ $month ] );
                        $month_total += $monthly[0];
                    }
                }
            }
            $all_monthly_data[] = $month_total;
        }
    }
    
    // Display "All" block first (full width)
    $icon = isset( $type_icons['all'] ) ? $type_icons['all'] : 'dashicons-shield';
    $title = secupress_attacks_get_type_title( 'all' );
    $formatted_total = number_format_i18n( $all_total );
    
    echo '<div class="secupress-attacks-widget">';
    echo '<div class="secupress-attacks-header">';
    echo '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $plugin_name ) . '" class="secupress-attacks-header-logo">';
    echo '<h2 class="secupress-attacks-header-title">' . esc_html( $plugin_name ) . '</h2>';
    echo '</div>';
    echo '<div class="secupress-attack-all-card">';
    echo '<div class="secupress-attack-all-left">';
    echo '<span class="secupress-attack-all-icon dashicons ' . esc_attr( $icon ) . '"></span>';
    echo '<h3 class="secupress-attack-all-title">' . esc_html( $title ) . '</h3>';
    echo '</div>';
    echo '<div class="secupress-attack-all-right">';
    echo '<div class="secupress-attack-all-count">' . esc_html( $formatted_total ) . '</div>';
    echo '<div class="secupress-attack-all-label">' . esc_html_x( 'Blocked', 'attacks', 'secupress' ) . '</div>';
    echo '</div>';
    echo '</div>';

    if ( is_array( $attacks ) && ! empty( $attacks ) ) {
        // Remove "all" from attacks array if it exists
        $other_attacks = $attacks;
        if ( isset( $other_attacks['all'] ) ) {
            unset( $other_attacks['all'] );
        }
        
        if ( ! empty( $other_attacks ) ) {
            echo '<div class="secupress-attacks-grid">';
            
            // Display other attack types
            $chart_index = 0;
            foreach ( $other_attacks as $type => $attack_data ) {
                $icon = isset( $type_icons[ $type ] ) ? $type_icons[ $type ] : 'dashicons-editor-help';
                $title = secupress_attacks_get_type_title( $type );
                $total = secupress_calculate_attack_total( $attack_data );
                $formatted_total = number_format_i18n( $total );
                $chart_id = $show_charts && isset( $chart_data[ $chart_index ] ) ? $chart_data[ $chart_index ]['id'] : '';
                
                echo '<div class="secupress-attack-card">';
                echo '<div class="secupress-attack-header">';
                echo '<span class="secupress-attack-icon dashicons ' . esc_attr( $icon ) . '"></span>';
                echo '<h3 class="secupress-attack-title">' . esc_html( $title ) . '</h3>';
                echo '</div>';
                echo '<div class="secupress-attack-count">' . esc_html( $formatted_total ) . '</div>';
                echo '<div class="secupress-attack-label">' . esc_html_x( 'Blocked', 'attacks', 'secupress' ) . '</div>';
                if ( $show_charts && $chart_id ) {
                    echo '<div class="secupress-attack-chart">';
                    echo '<canvas id="' . esc_attr( $chart_id ) . '" width="200" height="120"></canvas>';
                    echo '</div>';
                }
                echo '</div>';
                
                if ( $show_charts ) {
                    $chart_index++;
                }
            }
            echo '</div>';
        }
    }
    echo '</div>';
}

/**
 * Adds a custom dashboard widget that displays the system status.
 *
 * @author Julio Potier
 * @since 2.7
 **/
function secupress_system_dashboard_widget() {
	if ( ! current_user_can( secupress_get_capability() ) ) {
		return;
	}

	wp_add_dashboard_widget(
		'secupress-system-widget',
		__( 'System Status', 'secupress' ),
		'secupress_system_render_dashboard_widget'
	);
}

/**
 * Collect system status rows for the dashboard widget.
 *
 * @author Julio Potier
 * @since 2.7
 *
 * @return (array)
 **/
function secupress_system_widget_get_items() {
	return array_merge(
		secupress_system_widget_wordpress_items(),
		secupress_system_widget_server_items(),
		secupress_system_widget_secupress_items()
	);
}

/**
 * WordPress-related status rows.
 *
 * @author Julio Potier
 * @since 2.7
 *
 * @return (array)
 **/
function secupress_system_widget_wordpress_items() {
	global $wp_version;

	$items = [];

	$outdated = false;
	if ( ! function_exists( 'get_preferred_from_update_core' ) ) {
		require_once ABSPATH . 'wp-admin/includes/update.php';
	}
	$core_update = get_preferred_from_update_core();
	if ( is_object( $core_update ) && isset( $core_update->response ) && 'upgrade' === $core_update->response ) {
		$outdated = true;
	}
	$items[] = [
		'icon'    => 'dashicons-wordpress', //// "core/wordpress" in WP 7.2
		'label'   => 'WordPress',
		'value'   => $outdated
			? sprintf( __( '%s — not up to date', 'secupress' ), $wp_version )
			: sprintf( __( '%s — up to date', 'secupress' ), $wp_version ),
		'status'  => $outdated ? 'danger' : '',
		'tooltip' => [],
		'hint'    => $outdated ? __( 'WordPress core is not up to date. Update it from Dashboard → Updates.', 'secupress' ) : '',
	];

	$theme       = wp_get_theme();
	$stylesheet  = $theme->get_stylesheet();
	$parent      = $theme->parent();
	if ( $parent ) {
		$kind = sprintf( __( 'Child theme of %s', 'secupress' ), $parent->get( 'Name' ) );
	} else {
		$kind = __( 'Parent theme', 'secupress' );
	}
	$theme_updates  = get_theme_updates();
	$theme_outdated = isset( $theme_updates[ $stylesheet ] );
	if ( $parent && isset( $theme_updates[ $parent->get_stylesheet() ] ) ) {
		$theme_outdated = true;
	}
	$used_themes = [
		$stylesheet => $stylesheet,
	];
	if ( $parent ) {
		$used_themes[ $parent->get_stylesheet() ] = $parent->get_stylesheet();
	}
	$unused_themes = count( array_diff_key( wp_get_themes(), $used_themes ) );
	$theme_value   = trim( $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) ) . ' — ' . $kind;
	if ( $unused_themes ) {
		$theme_value .= ' (' . sprintf(
			_n( '%s unused', '%s unused', $unused_themes, 'secupress' ),
			number_format_i18n( $unused_themes )
		) . ')';
	}
	$items[] = [
		'icon'    => 'dashicons-admin-appearance', //// "core/appearance" in WP 7.2
		'label'   => __( 'Theme', 'secupress' ),
		'value'   => $theme_value,
		'status'  => $theme_outdated ? 'warning' : '',
		'tooltip' => [],
		'hint'    => $theme_outdated ? __( 'This theme is not up to date. Update it from Appearance → Themes.', 'secupress' ) : '',
	];

	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$all_plugins    = get_plugins();
	$plugins_total  = count( $all_plugins );
	$active_plugins = (array) get_option( 'active_plugins', [] );
	if ( is_multisite() ) {
		$network_plugins = array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) );
		$active_plugins  = array_unique( array_merge( $active_plugins, $network_plugins ) );
	}
	$active_plugins = array_intersect( $active_plugins, array_keys( $all_plugins ) );
	$plugins_active = count( $active_plugins );
	$plugins_update = count( get_plugin_updates() );
	$plugins_value  = sprintf( '%s/%s activated', number_format_i18n( $plugins_active ), number_format_i18n( $plugins_total ) );
	if ( $plugins_update ) {
		$plugins_value .= ' (' . sprintf(
			_n( '%s to update', '%s to update', $plugins_update, 'secupress' ),
			number_format_i18n( $plugins_update )
		) . ')';
	}
	$items[] = [
		'icon'    => 'dashicons-admin-plugins', //// "core/plugins" in WP 7.2
		'label'   => __( 'Plugins', 'secupress' ),
		'value'   => $plugins_value,
		'status'  => $plugins_update ? 'warning' : '',
		'tooltip' => [],
		'hint'    => $plugins_update ? __( 'Some plugins have updates available. Update them from Plugins → Installed Plugins.', 'secupress' ) : '',
	];

	$env          = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
	$env_labels   = [
		'local'       => __( 'Local', 'secupress' ),
		'development' => __( 'Development', 'secupress' ),
		'staging'     => __( 'Staging', 'secupress' ),
		'production'  => __( 'Production', 'secupress' ),
	];
	$items[] = [
		'icon'    => 'core/map-marker',
		'label'   => __( 'Environment', 'secupress' ),
		'value'   => isset( $env_labels[ $env ] ) ? $env_labels[ $env ] : $env,
		'status'  => '',
		'tooltip' => [],
	];

	$debug         = defined( 'WP_DEBUG' ) && WP_DEBUG;
	$debug_display = $debug && ( ! defined( 'WP_DEBUG_DISPLAY' ) || WP_DEBUG_DISPLAY );
	$debug_log     = $debug && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
	if ( ! $debug ) {
		$debug_value = __( 'Off', 'secupress' );
	} elseif ( $debug_display && $debug_log ) {
		$debug_value = __( 'On, display and log', 'secupress' );
	} elseif ( $debug_display ) {
		$debug_value = __( 'On, display', 'secupress' );
	} elseif ( $debug_log ) {
		$debug_value = __( 'On, log only', 'secupress' );
	} else {
		$debug_value = __( 'On', 'secupress' );
	}
	$debug_status = '';
	$debug_hint   = '';
	if ( 'production' === $env && ( $debug || $debug_display ) ) {
		$debug_status = 'danger';
		$debug_hint   = __( 'Debug is enabled in production and can leak PHP errors. Set WP_DEBUG and WP_DEBUG_DISPLAY to false in wp-config.php.', 'secupress' );
	} elseif ( 'production' === $env && $debug_log ) {
		$debug_status = 'warning';
		$debug_hint   = __( 'debug.log is enabled in production and may be publicly downloadable. Disable WP_DEBUG_LOG in wp-config.php.', 'secupress' );
	}
	$items[] = [
		'icon'    => 'core/tip',
		'label'   => __( 'Debug', 'secupress' ),
		'value'   => $debug_value,
		'status'  => $debug_status,
		'tooltip' => [],
		'hint'    => $debug_hint,
	];

	$blog_public  = (bool) get_option( 'blog_public' );
	$index_status = '';
	$index_hint   = '';
	if ( 'production' === $env && ! $blog_public ) {
		$index_status = 'warning';
		$index_hint   = __( 'Search engines are discouraged on a production site. Allow indexing in Settings → Reading if the site is live.', 'secupress' );
	} elseif ( 'production' !== $env && $blog_public ) {
		$index_status = 'warning';
		$index_hint   = __( 'Search engines can index this non-production site. Discourage indexing in Settings → Reading.', 'secupress' );
	}
	$items[] = [
		'icon'    => 'core/search',
		'label'   => __( 'Search engines', 'secupress' ),
		'value'   => $blog_public ? __( 'Indexed', 'secupress' ) : __( 'Indexing discouraged', 'secupress' ),
		'status'  => $index_status,
		'tooltip' => [],
		'hint'    => $index_hint,
	];

	$can_register = secupress_users_can_register();
	$items[] = [
		'icon'    => 'core/people',
		'label'   => __( 'Registrations', 'secupress' ),
		'value'   => $can_register ? __( 'Open', 'secupress' ) : __( 'Closed', 'secupress' ),
		'status'  => '',
		'tooltip' => [],
	];

	return $items;
}

/**
 * Server-related status rows.
 *
 * @author Julio Potier
 * @since 2.7
 *
 * @return (array)
 **/
function secupress_system_widget_server_items() {
	global $is_apache, $is_nginx, $is_iis7;

	$items   = [];
	$env     = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
	$is_https = 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME );
	$https_status = '';
	$https_hint   = '';
	if ( ! $is_https ) {
		$https_status = 'production' === $env ? 'danger' : 'warning';
		$https_hint   = 'production' === $env
			? __( 'The site URL is not using HTTPS. Install an SSL certificate and set the site URL to https.', 'secupress' )
			: __( 'HTTPS is off. Use HTTPS even on local or staging when possible.', 'secupress' );
	}
	$items[] = [
		'icon'    => 'core/key',
		'label'   => 'HTTPS',
		'value'   => $is_https ? __( 'On', 'secupress' ) : __( 'Off', 'secupress' ),
		'status'  => $https_status,
		'tooltip' => [],
		'hint'    => $https_hint,
	];

	$versions = secupress_get_php_versions();
	$php_status = '';
	$php_hint   = '';
	if ( version_compare( $versions['current'], $versions['mini'], '<' ) ) {
		$php_status = 'danger';
		$php_hint   = sprintf( __( 'This PHP version is no longer supported. Ask your host to upgrade to PHP %s or later.', 'secupress' ), $versions['mini'] );
	} elseif ( version_compare( $versions['current'], $versions['last'], '<' ) ) {
		$php_status = 'warning';
		$php_hint   = sprintf( __( 'This PHP version only receives security updates. PHP %s is recommended.', 'secupress' ), $versions['best'] );
	}
	$items[] = [
		'icon'    => 'core/symbol',
		'label'   => 'PHP',
		'value'   => phpversion(),
		'status'  => $php_status,
		'tooltip' => [],
		'hint'    => $php_hint,
	];

	if ( ! empty( $GLOBALS['is_litespeed'] ) ) {
		$server = 'LiteSpeed';
	} elseif ( $is_apache ) {
		$server = 'Apache';
	} elseif ( $is_nginx ) {
		$server = 'Nginx';
	} elseif ( $is_iis7 ) {
		$server = 'IIS';
	} else {
		$server = __( 'Unknown', 'secupress' );
	}
	$items[] = [
		'icon'    => 'core/desktop',
		'label'   => __( 'Web server', 'secupress' ),
		'value'   => $server,
		'status'  => '',
		'tooltip' => [],
	];

	$has_robots = file_exists( ABSPATH . 'robots.txt' );
	$items[] = [
		'icon'    => 'core/file',
		'label'   => __( 'Physical robots.txt', 'secupress' ),
		'value'   => $has_robots ? __( 'Present', 'secupress' ) : __( 'None', 'secupress' ),
		'status'  => '',
		'tooltip' => [],
	];

	$home_path  = secupress_get_home_path();
	$filesystem = secupress_get_filesystem();
	if ( $is_apache ) {
		$ht_file = $home_path . '.htaccess';
		$exists  = $filesystem ? $filesystem->exists( $ht_file ) : file_exists( $ht_file );
		$items[] = [
			'icon'    => 'core/file',
			'label'   => '.htaccess',
			'value'   => $exists ? __( 'Present', 'secupress' ) : __( 'Missing', 'secupress' ),
			'status'  => $exists ? '' : 'danger',
			'tooltip' => [],
			'hint'    => $exists ? '' : __( 'The .htaccess file is missing. Permalinks and server rules cannot be applied. Restore it from Settings → Permalinks.', 'secupress' ),
		];
	} elseif ( $is_iis7 ) {
		$ht_file = $home_path . 'web.config';
		$exists  = $filesystem ? $filesystem->exists( $ht_file ) : file_exists( $ht_file );
		$items[] = [
			'icon'    => 'core/file',
			'label'   => 'web.config',
			'value'   => $exists ? __( 'Present', 'secupress' ) : __( 'Missing', 'secupress' ),
			'status'  => $exists ? '' : 'danger',
			'tooltip' => [],
			'hint'    => $exists ? '' : __( 'The web.config file is missing. Restore it so IIS rewrite rules can be applied.', 'secupress' ),
		];
	}

	return $items;
}

/**
 * SecuPress and accounts status rows.
 *
 * @author Julio Potier
 * @since 2.7
 *
 * @return (array)
 **/
function secupress_system_widget_secupress_items() { //// recheck icons in WP 7.2/7.3
	$items = [];

	$modules_count    = secupress_count_active_submodules();
	if ( secupress_is_expert_mode() ) {
		$modules_text = __( 'Expert Mode', 'secupress' ) . ' — ';
	}
	$modules_text    .= sprintf(
		_n( '%s active module', '%s active modules', $modules_count, 'secupress' ),
		number_format_i18n( $modules_count )
	);
	$items[] = [
		'icon'    => 'core/settings',
		'label'   => SECUPRESS_PLUGIN_NAME,
		'value'   => $modules_text,
		'status'  => $modules_count ? '' : 'danger',
		'tooltip' => secupress_get_active_submodule_labels(),
		'hint'    => $modules_count ? '' : sprintf( __( '%s is running, but no module is protecting the site.', 'secupress' ), SECUPRESS_PLUGIN_NAME ),
	];

	if ( secupress_is_security_paused() ) {
		$items[] = [
			'icon'    => 'core/caution',
			'label'   => __( 'Security', 'secupress' ),
			'value'   => __( 'Paused', 'secupress' ),
			'status'  => 'danger',
			'tooltip' => [],
			'hint'    => __( 'Security modules are paused. PHP protection is off until it resumes. Re-activate security from SecuPress.', 'secupress' ),
		];
	}

	$scan_items  = array_filter( (array) get_site_option( SECUPRESS_SCAN_TIMES ) );
	$last_report = '—';
	if ( $scan_items ) {
		$last        = end( $scan_items );
		$time_offset = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
		$last_report = date_i18n( _x( 'M dS, Y \a\t h:ia', 'Latest scans', 'secupress' ), $last['time'] + $time_offset );
	}
	if ( secupress_show_grade_system() ) {
		$grade = secupress_get_scanner_counts( 'grade' );
		$scan_value = sprintf( __( 'Grade %1$s on %2$s', 'secupress' ), $grade, $last_report );
	} else {
		$scan_value = $last_report;
	}
	$items[] = [
		'icon'    => 'core/shield',
		'label'   => __( 'Last scan', 'secupress' ),
		'value'   => $scan_value,
		'status'  => '',
		'tooltip' => [],
	];

	$connected = 0;
	$cached    = get_site_transient( 'secupress_system_connected_users' );
	if ( false !== $cached ) {
		$connected = (int) $cached;
	} elseif ( function_exists( 'secupress_get_connected_user_ids' ) ) {
		$connected = count( secupress_get_connected_user_ids() );
		set_site_transient( 'secupress_system_connected_users', $connected, MINUTE_IN_SECONDS );
	}
	$items[] = [
		'icon'    => 'core/people',
		'label'   => __( 'Connected users', 'secupress' ),
		'value'   => sprintf(
			/* translators: %s: number of currently connected users. */
			__( '%s (live)', 'secupress' ),
			number_format_i18n( $connected )
		),
		'status'  => '',
		'tooltip' => [],
	];

	$admin_logins = [];
	if ( is_multisite() ) {
		$super_admins = get_super_admins();
		$admin_count  = count( $super_admins );
		if ( $super_admins ) {
			$admin_users = get_users( [
				'login__in' => $super_admins,
				'orderby'   => 'ID',
				'order'     => 'DESC',
				'number'    => 10,
				'fields'    => 'user_login',
			] );
			$admin_logins = array_values( $admin_users );
		}
	} else {
		$user_counts = count_users();
		$admin_count = isset( $user_counts['avail_roles']['administrator'] ) ? (int) $user_counts['avail_roles']['administrator'] : 0;
		$admin_users = get_users( [
			'role'    => 'administrator',
			'orderby' => 'ID',
			'order'   => 'DESC',
			'number'  => 10,
			'fields'  => 'user_login',
		] );
		$admin_logins = array_values( $admin_users );
	}
	$items[] = [
		'icon'    => 'core/star-empty',
		'label'   => __( 'Administrators', 'secupress' ),
		'value'   => number_format_i18n( $admin_count ),
		'status'  => '',
		'tooltip' => $admin_logins,
	];

	return $items;
}

/**
 * License footer for the system status widget.
 *
 * @author Julio Potier
 * @since 2.7
 *
 * @return (array)
 **/
function secupress_system_widget_get_footer() {
	if ( secupress_is_white_label() ) {
		return [];
	}

	if ( ! secupress_has_pro() ) {
		return [
			'type'   => 'get-pro',
			'status' => '',
			'html'   => '<a class="secupress-system-footer-getpro" href="' . esc_url( secupress_admin_url( 'get-pro' ) ) . '" target="_blank" rel="noopener noreferrer">' . secupress_get_logo( [
				'width'  => 20,
				'height' => 20,
				'class'  => 'secupress-system-footer-logo',
				'alt'    => '',
			] ) . esc_html__( 'Get a SecuPress Pro Licence', 'secupress' ) . '</a>',
		];
	}

	if ( secupress_has_pro_license() ) {
		return [
			'type'   => 'valid',
			'status' => '',
			'html'   => esc_html__( 'License valid', 'secupress' ),
		];
	}

	$account = trailingslashit( set_url_scheme( SECUPRESS_WEB_MAIN, 'https' ) ) . _x( 'account', 'link to website (Only FR or EN!)', 'secupress' );
	return [
		'type'   => 'expired',
		'status' => 'danger',
		'html'   => '<a href="' . esc_url( $account ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'License expired', 'secupress' ) . '</a>',
		'hint'   => __( 'Your Pro license has expired. Renew it to keep updates and Pro features.', 'secupress' ),
	];
}

/**
 * Get an SVG icon from WordPress
 *
 * @author Julio Potier
 * @since 2.7
 *
 * @param (string) $name Icon name.
 *
 * @return (string)
 **/
function secupress_system_widget_wp_icon( $name ) {
	if ( ! $name ) {
		return '&raquo;';
	}
	if ( function_exists( 'wp_get_icon' ) ) {
		$html = wp_get_icon( $name, [
			'size'  => 20,
			'class' => 'secupress-system-wp-icon',
		] );
		if ( is_string( $html ) && $html ) {
			return $html;
		}
	}
	if ( 0 === strpos( $name, 'dashicons-' ) ) {
		return '<span class="dashicons ' . esc_attr( $name ) . '" aria-hidden="true"></span>';
	}
	return function_exists( 'wp_get_icon' ) ? '' : '&raquo;';
}

/**
 * Icon markup for a status row.
 *
 * @author Julio Potier
 * @since 2.7
 *
 * @param (array) $item Row data.
 *
 * @return (string)
 **/
function secupress_system_widget_get_icon( $item ) {
	$status = isset( $item['status'] ) ? $item['status'] : '';
	if ( 'danger' === $status ) {
		$html = secupress_system_widget_wp_icon( 'core/cancel-circle-filled' );
		if ( $html ) {
			return $html;
		}
		return '<span class="secupress-system-cross secupress-system-cross-danger" aria-hidden="true">×</span>';
	}
	if ( 'warning' === $status ) {
		$html = secupress_system_widget_wp_icon( 'core/error' );
		if ( $html ) {
			return $html;
		}
		return '<span class="secupress-system-cross secupress-system-cross-warning" aria-hidden="true">×</span>';
	}
	if ( ! empty( $item['icon'] ) ) {
		return secupress_system_widget_wp_icon( $item['icon'] );
	}
	return '';
}

/**
 * Tooltip markup for extra details.
 *
 * @author Julio Potier
 * @since 2.7
 *
 * @param (array) $entries Tooltip lines.
 *
 * @return (string)
 **/
function secupress_system_widget_tooltip( $entries ) {
	if ( ! $entries ) {
		return '';
	}
	$html  = ' <span class="secupress-system-info" tabindex="0">';
	$html .= '<span class="dashicons dashicons-info" aria-hidden="true"></span>';
	$html .= '<span class="screen-reader-text">' . esc_html( implode( ', ', $entries ) ) . '</span>';
	$html .= '<span class="secupress-system-tooltip"><span class="secupress-system-tooltip-inner">';
	foreach ( $entries as $entry ) {
		$html .= '<span class="secupress-system-tooltip-item">' . esc_html( $entry ) . '</span>';
	}
	$html .= '</span></span></span>';
	return $html;
}

/**
 * Tooltip markup for a warning or danger hint.
 *
 * @author Julio Potier
 * @since 2.7
 *
 * @param (string) $hint Hint text.
 *
 * @return (string)
 **/
function secupress_system_widget_hint_tooltip( $hint ) {
	if ( ! $hint ) {
		return '';
	}
	$html  = '<span class="secupress-system-tooltip"><span class="secupress-system-tooltip-inner">';
	$html .= '<span class="secupress-system-tooltip-item">' . esc_html( $hint ) . '</span>';
	$html .= '</span></span>';
	return $html;
}

/**
 * Render the system status dashboard widget.
 *
 * @author Julio Potier
 * @since 2.7
 **/
function secupress_system_render_dashboard_widget() {
	$items     = secupress_system_widget_get_items();
	$footer    = secupress_system_widget_get_footer();
	$has_icons = function_exists( 'wp_get_icon' );
	$list_class = 'secupress-system-list';
	if ( $has_icons ) {
		$list_class .= ' secupress-system-has-icons';
	}

	echo '<div class="secupress-system-widget">';
	echo '<ul class="' . esc_attr( $list_class ) . '">';
	foreach ( $items as $item ) {
		$status = isset( $item['status'] ) ? $item['status'] : '';
		$hint   = ! empty( $item['hint'] ) ? $item['hint'] : '';
		$class  = 'secupress-system-item';
		if ( $status ) {
			$class .= ' secupress-system-item-' . $status;
		}
		if ( $hint ) {
			$class .= ' secupress-system-has-hint';
		}
		$icon = secupress_system_widget_get_icon( $item );
		echo '<li class="' . esc_attr( $class ) . '"' . ( $hint ? ' tabindex="0"' : '' ) . '>';
		if ( $icon ) {
			echo '<span class="secupress-system-icon">' . $icon . '</span>';
		}
		echo '<span class="secupress-system-text">';
		echo '<span class="secupress-system-label">' . esc_html( $item['label'] ) . '</span>';
		echo '<span class="secupress-system-value">' . esc_html( $item['value'] );
		if ( ! empty( $item['tooltip'] ) ) {
			echo secupress_system_widget_tooltip( $item['tooltip'] );
		}
		echo '</span>';
		echo '</span>';
		if ( $hint ) {
			echo '<span class="screen-reader-text">' . esc_html( $hint ) . '</span>';
			echo secupress_system_widget_hint_tooltip( $hint );
		}
		echo '</li>';
	}
	echo '</ul>';

	if ( $footer ) {
		$footer_class = 'secupress-system-footer';
		if ( ! empty( $footer['status'] ) ) {
			$footer_class .= ' secupress-system-item-' . $footer['status'];
		}
		if ( ! empty( $footer['hint'] ) ) {
			$footer_class .= ' secupress-system-has-hint';
		}
		echo '<div class="' . esc_attr( $footer_class ) . '"' . ( ! empty( $footer['hint'] ) ? ' tabindex="0"' : '' ) . '>';
		if ( 'valid' === $footer['type'] ) {
			echo secupress_get_logo( [
				'width'  => 20,
				'height' => 20,
				'class'  => 'secupress-system-footer-logo',
				'alt'    => SECUPRESS_PLUGIN_NAME,
			] );
		} elseif ( 'danger' === $footer['status'] ) {
			$icon = secupress_system_widget_get_icon( [ 'status' => 'danger' ] );
			if ( $icon ) {
				echo '<span class="secupress-system-icon">' . $icon . '</span>';
			}
		}
		echo '<span class="secupress-system-footer-text">' . $footer['html'] . '</span>';
		if ( ! empty( $footer['hint'] ) ) {
			echo '<span class="screen-reader-text">' . esc_html( $footer['hint'] ) . '</span>';
			echo secupress_system_widget_hint_tooltip( $footer['hint'] );
		}
		echo '</div>';
	}
	echo '</div>';
}
