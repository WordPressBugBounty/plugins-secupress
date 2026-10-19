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
        'secupress_attacks_render_dashboard_widget',
        null,
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
 * Get the last X months in ym format (starting from current month going back)
 *
 * @param int $x Number of months to get (default 6)
 * @return array Array of month codes (e.g., ['2605', '2606', '2607', '2608', '2609', '2610'])
 */
function secupress_get_last_x_months( $x = 6 ) {
    $months = [];
    $current_date = new DateTime();

    for ( $i = 0; $i < $x; $i++ ) {
        $date = clone $current_date;
        $date->modify( '-' . $i . ' months' );
        $months[] = $date->format( 'ym' );
    }

    return array_reverse( $months );
}

/**
 * Sum one attack type per month.
 *
 * Each daily key is YYMMDD. A month code is YM (4 characters). Values whose key starts with that code are added together.
 *
 * @author Julio Potier
 * @since 2.4.1
 *
 * @param (array) $attack_data Daily counts for one attack type.
 * @param (array) $months      Month codes, oldest first.
 *
 * @return (array) One total per month, same order as $months.
 **/
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
        foreach ( $attack_data as $date => $value ) {
            if ( is_numeric( $date ) && is_numeric( $value ) && 6 === strlen( (string) $date ) && substr( (string) $date, 0, 4 ) === $month ) {
                $total += (int) $value;
            }
        }
        $monthly_data[] = $total;
    }
    
    return $monthly_data;
}

/**
 * Sum every daily count of one attack type.
 *
 * @author Julio Potier
 * @since 2.4.1
 *
 * @param (array) $attack_data Daily counts for one attack type.
 *
 * @return (int)
 **/
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
 * Sum blocked attacks per day (YYMMDD), for one type or all types.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @param (string) $type Attack type, or 'all'.
 *
 * @return (array)
 **/
function secupress_get_attacks_daily_totals( $type = 'all' ) {
	$attacks = secupress_get_attacks( 'all' );
	$daily   = [];

	if ( ! is_array( $attacks ) ) {
		return $daily;
	}

	foreach ( $attacks as $attack_type => $data ) {
		if ( 'all' === $attack_type || ! is_array( $data ) ) {
			continue;
		}
		if ( 'all' !== $type && $attack_type !== $type ) {
			continue;
		}
		foreach ( $data as $date_key => $value ) {
			if ( ! is_numeric( $date_key ) || ! is_numeric( $value ) || 6 !== strlen( (string) $date_key ) ) {
				continue;
			}
			if ( ! isset( $daily[ $date_key ] ) ) {
				$daily[ $date_key ] = 0;
			}
			$daily[ $date_key ] += (int) $value;
		}
	}

	return $daily;
}

/**
 * Difference in blocked attacks between today and a reference daily total.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @param (int)   $current
 * @param (float) $reference
 *
 * @return (int)
 **/
function secupress_attacks_widget_compare_delta( $current, $reference ) {
	return (int) $current - (int) round( $reference );
}

/**
 * Compare today's blocked attacks with a previous period.
 *
 * One day compares today with yesterday. More than one day compares today with the daily average of that window. The window stops at the oldest logged day and needs at least 2 days.
 *
 * Display it with secupress_attacks_widget_print_trend(), after adding a "label" key. That function echoes a div, it does not return a tag:
 * "up" is ↗ in .secupress-attack-trend--up, "down" is ↘ in .secupress-attack-trend--down, "flat" is → in .secupress-attack-trend--flat. The arrow keeps the surrounding text color.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @param (int)    $days Number of previous days.
 * @param (string) $type Attack type, or 'all'.
 *
 * @return (array|false) today, reference, delta, direction, days, type. False when there is not enough history.
 **/
function secupress_get_attacks_trend( $days = 1, $type = 'all' ) {
	$days  = max( 1, (int) $days );
	$type  = $type ? $type : 'all';
	$daily = secupress_get_attacks_daily_totals( $type );

	if ( empty( $daily ) ) {
		return false;
	}

	$today_key   = date( 'ymd' );
	$today_count = isset( $daily[ $today_key ] ) ? (int) $daily[ $today_key ] : 0;

	if ( 1 === $days ) {
		$yesterday_key = date( 'ymd', strtotime( '-1 day' ) );
		$reference     = isset( $daily[ $yesterday_key ] ) ? (int) $daily[ $yesterday_key ] : 0;
		$used_days     = 1;
	} else {
		$first_key = min( array_keys( $daily ) );
		$pool      = [];

		for ( $i = 1; $i <= $days; $i++ ) {
			$day_key = date( 'ymd', strtotime( '-' . $i . ' day' ) );

			if ( $day_key < $first_key ) {
				break;
			}

			$pool[] = isset( $daily[ $day_key ] ) ? (int) $daily[ $day_key ] : 0;
		}

		$used_days = count( $pool );

		if ( $used_days < 2 ) {
			return false;
		}

		$reference = (int) round( array_sum( $pool ) / $used_days );
	}

	$delta = secupress_attacks_widget_compare_delta( $today_count, $reference );

	return [
		'today'     => $today_count,
		'reference' => $reference,
		'delta'     => $delta,
		'direction' => $delta > 0 ? 'up' : ( $delta < 0 ? 'down' : 'flat' ),
		'days'      => $used_days,
		'type'      => $type,
	];
}

/**
 * Trend stats for the attacks widget (today vs yesterday and rolling average).
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @param (string) $type Attack type, or 'all'.
 *
 * @return (array)
 **/
function secupress_get_attacks_widget_trends( $type = 'all' ) {
	$yesterday = secupress_get_attacks_trend( 1, $type );
	$period    = secupress_get_attacks_trend( 30, $type );

	if ( is_array( $yesterday ) ) {
		$yesterday['label'] = __( 'vs yesterday', 'secupress' );
	}

	if ( is_array( $period ) ) {
		$period['label'] = sprintf(
			/* translators: %d is a number of days (2–30). */
			__( 'vs %d-day average', 'secupress' ),
			$period['days']
		);
	}

	return [
		'yesterday' => $yesterday ? $yesterday : null,
		'period'    => $period ? $period : null,
	];
}

/**
 * Print one attacks widget trend line.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @param (array) $trend
 **/
function secupress_attacks_widget_print_trend( $trend ) {
	if ( ! is_array( $trend ) || ! isset( $trend['delta'], $trend['direction'], $trend['label'] ) ) {
		return;
	}

	$delta     = (int) $trend['delta'];
	$direction = $trend['direction'];
	$class     = 'secupress-attack-trend';

	if ( 'up' === $direction ) {
		$class .= ' secupress-attack-trend--up';
		$arrow = '↗';
	} elseif ( 'down' === $direction ) {
		$class .= ' secupress-attack-trend--down';
		$arrow = '↘';
	} else {
		$class .= ' secupress-attack-trend--flat';
		$arrow = '→';
	}

	if ( $delta > 0 ) {
		$delta_text = '+' . number_format_i18n( $delta );
	} else {
		$delta_text = number_format_i18n( $delta );
	}

	echo '<div class="' . esc_attr( $class ) . '">';
	echo '<span class="secupress-attack-trend-arrow" aria-hidden="true">' . esc_html( $arrow ) . '</span>';
	echo '<span class="secupress-attack-trend-value">' . esc_html( $delta_text ) . '</span>';
	echo '<span class="secupress-attack-trend-label">' . esc_html( $trend['label'] ) . '</span>';
	echo '</div>';
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

/**
 * Days between the oldest logged attack and today, including both.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @return (int)
 **/
function secupress_attacks_widget_history_days() {
	$daily = secupress_get_attacks_daily_totals( 'all' );

	if ( ! $daily ) {
		return 0;
	}

	$keys = array_map( 'strval', array_keys( $daily ) );
	sort( $keys, SORT_STRING );
	$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
	$first    = DateTime::createFromFormat( 'ymd', $keys[0], $timezone );

	if ( ! $first ) {
		return 0;
	}

	$first->setTime( 0, 0, 0 );
	$today = new DateTime( 'today', $timezone );

	return (int) $first->diff( $today )->days + 1;
}

/**
 * Allowed periods for the attacks widget overview.
 *
 * A fixed window is listed only when the log already covers that many days.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @return (array)
 **/
function secupress_attacks_widget_ranges() {
	$days   = secupress_attacks_widget_history_days();
	$ranges = [
		'all' => _x( 'All', 'time period', 'secupress' ),
	];

	if ( $days >= 1 ) {
		$ranges['1'] = _x( 'Today', 'time period', 'secupress' );
	}
	if ( $days >= 7 ) {
		$ranges['7'] = __( '7 days', 'secupress' );
	}
	if ( $days >= 30 ) {
		$ranges['30'] = __( '30 days', 'secupress' );
	}
	if ( $days >= 360 ) {
		$ranges['360'] = __( '360 days', 'secupress' );
	}

	return $ranges;
}

/**
 * Period saved for the current user.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @return (string)
 **/
function secupress_attacks_widget_get_range() {
	$range   = get_user_meta( get_current_user_id(), 'secupress_attacks_widget_range', true );
	$allowed = array_map( 'strval', array_keys( secupress_attacks_widget_ranges() ) );

	if ( ! in_array( (string) $range, $allowed, true ) ) {
		return 'all';
	}

	return (string) $range;
}

/**
 * Variation label for the selected period.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @param (int|null) $percent Null when the previous window cannot be compared.
 *
 * @return (array)
 **/
function secupress_attacks_widget_percent_parts( $percent ) {
	if ( null === $percent ) {
		return [
			'class' => 'is-none',
			'text'  => '—',
		];
	}

	$percent = (int) $percent;

	if ( $percent > 0 ) {
		$class = 'is-up';
		$arrow = '↗';
	} elseif ( $percent < 0 ) {
		$class = 'is-down';
		$arrow = '↘';
	} else {
		$class = 'is-flat';
		$arrow = '→';
	}

	return [
		'class' => $class,
		'text'  => sprintf(
			/* translators: 1: arrow, 2: signed percentage compared with the previous period. */
			__( '%1$s %2$s%% vs prev', 'secupress' ),
			$arrow,
			( $percent > 0 ? '+' : '' ) . $percent
		),
	];
}

/**
 * Save the overview period and return the updated figures.
 *
 * @author Julio Potier
 * @since 2.7.2
 **/
function secupress_attacks_widget_ajax_range() {
	check_ajax_referer( 'secupress_attacks_widget_range', 'nonce' );

	if ( ! current_user_can( secupress_get_capability() ) ) {
		wp_send_json_error();
	}

	$range   = isset( $_POST['range'] ) ? sanitize_key( wp_unslash( $_POST['range'] ) ) : '';
	$allowed = array_map( 'strval', array_keys( secupress_attacks_widget_ranges() ) );

	if ( ! in_array( $range, $allowed, true ) ) {
		wp_send_json_error();
	}

	update_user_meta( get_current_user_id(), 'secupress_attacks_widget_range', $range );

	$attacks = secupress_get_attacks( 'all' );
	$total   = is_array( $attacks ) && isset( $attacks['all'] ) && is_numeric( $attacks['all'] ) ? (int) $attacks['all'] : 0;
	$period  = secupress_attacks_widget_get_period( $range, $total );
	$percent = secupress_attacks_widget_percent_parts( $period['percent'] );
	$count   = (int) $period['count'];

	wp_send_json_success( [
		'count'        => $count,
		'caption'      => _n( 'attack blocked', 'attacks blocked', $count, 'secupress' ),
		'lifetime'     => secupress_attacks_widget_lifetime_label( $total, $range ),
		'percentClass' => $percent['class'],
		'percentText'  => $percent['text'],
		'labels'       => $period['labels'],
		'points'       => $period['points'],
		'pointDot'     => $period['point_dot'],
		'bezier'       => 0,
		'startCaption' => $period['start_caption'],
		'endCaption'   => $period['end_caption'],
		'color'        => '#2BCDC1',
		'fill'         => 'rgba(43,205,193,0.18)',
		'types'        => secupress_attacks_widget_type_chart_payload( $range ),
	] );
}

add_action( 'wp_ajax_secupress_attacks_widget_range', 'secupress_attacks_widget_ajax_range' );

/**
 * Daily series, total and variation for a widget period.
 *
 * "all" uses the lifetime total and the same series as 360 days.
 * A period longer than the logged history is shortened to the available days.
 * The percentage compares that window with the window just before it.
 * It is null when that previous window cannot be compared.
 * "1" keeps today as the total. Its chart also includes yesterday, so the line has two points and ends on today.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @param (string) $range          all, 1, 7, 30 or 360.
 * @param (int)    $lifetime_total Cumulative blocked attacks.
 *
 * @return (array)
 **/
function secupress_attacks_widget_get_period( $range, $lifetime_total = 0 ) {
	$allowed = array_map( 'strval', array_keys( secupress_attacks_widget_ranges() ) );
	if ( ! in_array( (string) $range, $allowed, true ) ) {
		$range = '7';
	}

	$daily    = secupress_get_attacks_daily_totals( 'all' );
	$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
	$today    = new DateTime( 'today', $timezone );
	$first    = null;

	if ( $daily ) {
		$keys = array_map( 'strval', array_keys( $daily ) );
		sort( $keys, SORT_STRING );
		$first = DateTime::createFromFormat( 'ymd', $keys[0], $timezone );
		if ( $first ) {
			$first->setTime( 0, 0, 0 );
		}
	}

	$is_all    = ( 'all' === $range );
	$requested = $is_all ? 360 : (int) $range;
	$end       = clone $today;

	$start = clone $end;
	$start->modify( '-' . ( $requested - 1 ) . ' days' );

	if ( $first && $start < $first ) {
		$start = clone $first;
	}

	if ( $first && $end < $first ) {
		$start = null;
		$end   = null;
	}

	$points = [];
	$labels = [];
	$keys   = [];
	$sum    = 0;

	if ( $start && $end && $start <= $end ) {
		$cursor = clone $start;
		while ( $cursor <= $end ) {
			$key      = $cursor->format( 'ymd' );
			$value    = isset( $daily[ $key ] ) ? (int) $daily[ $key ] : 0;
			$points[] = $value;
			$labels[] = date_i18n( 'M j', $cursor->getTimestamp() );
			$keys[]   = $key;
			$sum     += $value;
			$cursor->modify( '+1 day' );
		}
	}

	$percent = null;
	$length  = count( $points );

	if ( ! $is_all && $length > 0 && $start ) {
		$prev_end = clone $start;
		$prev_end->modify( '-1 day' );
		$prev_start = clone $prev_end;
		$prev_start->modify( '-' . ( $length - 1 ) . ' days' );
		$enough = ! $first || $prev_start >= $first;

		if ( $enough ) {
			$prev_sum = 0;
			$cursor   = clone $prev_start;
			while ( $cursor <= $prev_end ) {
				$key = $cursor->format( 'ymd' );
				if ( isset( $daily[ $key ] ) ) {
					$prev_sum += (int) $daily[ $key ];
				}
				$cursor->modify( '+1 day' );
			}
			if ( $prev_sum > 0 ) {
				$percent = (int) round( ( ( $sum - $prev_sum ) / $prev_sum ) * 100 );
			}
		}
	}

	$sum_keys = $keys;

	if ( '1' === (string) $range && $start && 1 === count( $points ) ) {
		$context     = clone $start;
		$context->modify( '-1 day' );
		$context_key = $context->format( 'ymd' );

		array_unshift( $points, isset( $daily[ $context_key ] ) ? (int) $daily[ $context_key ] : 0 );
		array_unshift( $labels, date_i18n( 'M j', $context->getTimestamp() ) );
		array_unshift( $keys, $context_key );
		$start = $context;
	}

	$start_caption = '';
	$end_caption   = '';

	if ( $length > 0 && $start && $end ) {
		$ago = (int) $start->diff( $today )->days;
		if ( $ago > 0 ) {
			$start_caption = sprintf(
				_n( '%s day ago', '%s days ago', $ago, 'secupress' ),
				number_format_i18n( $ago )
			);
		}
		$end_ago = (int) $end->diff( $today )->days;
		if ( 0 === $end_ago ) {
			$end_caption = __( 'Today', 'secupress' );
		} elseif ( 1 === $end_ago ) {
			$end_caption = __( 'Yesterday', 'secupress' );
		} else {
			$end_caption = sprintf(
				_n( '%s day ago', '%s days ago', $end_ago, 'secupress' ),
				number_format_i18n( $end_ago )
			);
		}
	}

	return [
		'count'         => $is_all ? (int) $lifetime_total : $sum,
		'percent'       => $percent,
		'labels'        => $labels,
		'points'        => $points,
		'keys'          => $keys,
		'sum_keys'      => $sum_keys,
		'point_dot'     => $length > 31 ? 0 : 1,
		'start_caption' => $start_caption,
		'end_caption'   => $end_caption,
	];
}

/**
 * Chart payload for the overview sparkline.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @return (array)
 **/
function secupress_attacks_widget_overview_chart() {
	$attacks = secupress_get_attacks( 'all' );
	$total   = is_array( $attacks ) && isset( $attacks['all'] ) && is_numeric( $attacks['all'] ) ? (int) $attacks['all'] : 0;
	$period  = secupress_attacks_widget_get_period( secupress_attacks_widget_get_range(), $total );

	return [
		'id'       => 'secupress-attacks-overview-chart',
		'labels'   => $period['labels'],
		'data'     => $period['points'],
		'color'    => '#2BCDC1',
		'fill'     => 'rgba(43,205,193,0.18)',
		'pointDot' => $period['point_dot'],
		'bezier'   => 0,
	];
}

/**
 * Firewall pill, same states as the security status banner.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @return (array)
 **/
function secupress_attacks_widget_firewall_status() {
	$firewall_active = count( secupress_firewall_learning_get_active_ng_modules() );
	$firewall_total  = count( secupress_firewall_learning_ng_submodules() );

	if ( secupress_is_security_paused() ) {
		$state = 'paused';
		$label = _x( 'Paused', 'security', 'secupress' );
	} elseif ( secupress_firewall_learning_is_running() ) {
		$state = 'learning';
		$label = _x( 'Under Learning Mode', 'firewall', 'secupress' );
	} elseif ( $firewall_total > 0 && $firewall_active >= $firewall_total ) {
		$state = '';
		$label = _x( 'Fully activated', 'firewall', 'secupress' );
	} elseif ( $firewall_active > 0 ) {
		$state = 'partial';
		$label = _x( 'Partially activated', 'firewall', 'secupress' );
	} else {
		$state = 'off';
		$label = _x( 'Not activated', 'firewall', 'secupress' );
	}

	if ( secupress_firewall_ng_is_enabled() ) {
		$label = sprintf( __( 'Firewall %1$s: %2$s', 'secupress' ), secupress_firewall_ng_name(), $label );
	} else {
		$label = sprintf( __( 'Firewall: %s', 'secupress' ), $label );
	}

	return [
		'state' => $state,
		'label' => $label,
	];
}

/**
 * Time of the latest logged block, when action logs are on.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @return (string)
 **/
function secupress_attacks_widget_last_block_text() {
	if ( ! secupress_is_submodule_active( 'logs', 'action-logs' ) ) {
		return '';
	}

	$post_type = class_exists( 'SecuPress_Logs' ) ? SecuPress_Logs::build_post_type_name( 'action' ) : 'secupress_log_action';

	global $wpdb;

	$date = $wpdb->get_var( $wpdb->prepare(
		"SELECT post_date_gmt FROM {$wpdb->posts} WHERE post_type = %s AND post_title IN (%s, %s) AND post_status NOT IN ('trash', 'auto-draft') ORDER BY post_date_gmt DESC LIMIT 1",
		$post_type,
		'secupress.block',
		'secupress.ban.ip_banned'
	) );

	if ( ! $date || '0000-00-00 00:00:00' === $date ) {
		return '';
	}

	$timestamp = strtotime( $date . ' GMT' );
	if ( ! $timestamp ) {
		return '';
	}

	return sprintf( __( 'Last event %s ago', 'secupress' ), human_time_diff( $timestamp, time() ) );
}

/**
 * Quick wins that can still be suggested.
 *
 * A submodule entry is done when that submodule is active. Entries without a submodule use "done".
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @return (array)
 **/
function secupress_attacks_widget_quick_win_candidates() {
	global $wpdb;

	$scan_times = array_filter( (array) get_site_option( SECUPRESS_SCAN_TIMES ) );
	$scan_last  = 0;
	if ( $scan_times ) {
		$scan_item = end( $scan_times );
		$scan_last = is_array( $scan_item ) && isset( $scan_item['time'] ) ? (int) $scan_item['time'] : 0;
	}
	$scan_fresh    = $scan_last > ( time() - 7 * DAY_IN_SECONDS );
	$malware_fresh = secupress_get_malware_scan_last_time() > ( time() - 7 * DAY_IN_SECONDS );
	$alerts_on     = secupress_is_submodule_active( 'alerts', 'event-alerts' ) || secupress_is_submodule_active( 'alerts', 'daily-reporting' );
	$schedules_on  = secupress_is_submodule_active( 'schedules', 'schedules-backups' ) || secupress_is_submodule_active( 'schedules', 'schedules-scan' ) || secupress_is_submodule_active( 'schedules', 'schedules-file-monitoring' );
	$bad_url_on    = secupress_is_submodule_active( 'sensitive-data', 'bad-url-access' ) || secupress_is_submodule_active( 'sensitive-data', 'bad-url-access-pro' );
	$prefix_ok     = 'wp_' !== $wpdb->prefix && 'wordpress_' !== $wpdb->prefix;

	$candidates = [
		[ 'module' => 'firewall', 'submodule' => 'bad-url-contents', 'label' => __( 'Block Bad Content', 'secupress' ), 'anchor' => 'row-learning-mode_bbx_bad-url-contents' ],
		[ 'module' => 'firewall', 'submodule' => 'user-agents-header', 'label' => __( 'Block Bad User Agents', 'secupress' ), 'anchor' => 'row-learning-mode_bbx_user-agents-header' ],
		[ 'module' => 'firewall', 'submodule' => 'bad-referer', 'label' => __( 'Block Bad Referers', 'secupress' ), 'anchor' => 'row-learning-mode_bbx_bad-referer' ],
		[ 'module' => 'firewall', 'label' => sprintf( __( '%s Firewall rules', 'secupress' ), secupress_firewall_ng_name() ), 'anchor' => 'row-learning-mode_' . sanitize_key( secupress_firewall_ng_name() ), 'pro' => true, 'done' => secupress_firewall_ng_is_enabled() ],
		[ 'module' => 'firewall', 'submodule' => 'geoip-system', 'label' => __( 'GeoIP Management', 'secupress' ), 'anchor' => 'module-geoip-system' ],
		[ 'module' => 'users-login', 'submodule' => 'move-login', 'label' => __( 'Move Login Page', 'secupress' ), 'anchor' => 'row-move-login_activated' ],
		[ 'module' => 'users-login', 'submodule' => 'limitloginattempts', 'label' => __( 'Bad Login Attempts', 'secupress' ), 'anchor' => 'login-protection_type_limitloginattempts' ],
		[ 'module' => 'users-login', 'submodule' => 'passwordspraying', 'label' => __( 'Bad Password Attempts', 'secupress' ), 'anchor' => 'login-protection_type_passwordspraying' ],
		[ 'module' => 'users-login', 'submodule' => 'passwordless', 'label' => __( 'Passwordless 2FA', 'secupress' ), 'anchor' => 'row-double-auth_type' ],
		[ 'module' => 'users-login', 'submodule' => 'force-strong-encryption', 'label' => __( 'Force Strong Pass Encryption', 'secupress' ), 'anchor' => 'row-double-auth_force-strong-encryption' ],
		[ 'module' => 'users-login', 'submodule' => 'user-creation-protection', 'label' => __( 'Protect User Creation', 'secupress' ), 'anchor' => 'row-blacklist-logins_user-creation-protection' ],
		[ 'module' => 'users-login', 'submodule' => 'bad-email-domains', 'label' => __( 'Forbid Bad Email Domains', 'secupress' ), 'anchor' => 'row-blacklist-logins_bad-email-domains' ],
		[ 'module' => 'users-login', 'submodule' => 'same-email-domain', 'label' => __( 'Forbid Same Email Domain', 'secupress' ), 'anchor' => 'row-blacklist-logins_same-email-domain' ],
		[ 'module' => 'users-login', 'submodule' => 'membership', 'label' => __( 'Lock Membership', 'secupress' ), 'anchor' => 'row-blacklist-logins_membership-activated' ],
		[ 'module' => 'users-login', 'submodule' => 'admin-email', 'label' => __( 'Lock Admin Email', 'secupress' ), 'anchor' => 'row-blacklist-logins_admin-email-activated' ],
		[ 'module' => 'users-login', 'submodule' => 'stop-user-enumeration', 'label' => __( 'Forbid User Enumeration', 'secupress' ), 'anchor' => 'row-blacklist-logins_stop-user-enumeration' ],
		[ 'module' => 'users-login', 'submodule' => 'blacklist-logins', 'label' => __( 'Forbid Bad Usernames', 'secupress' ), 'anchor' => 'row-blacklist-logins_activated' ],
		[ 'module' => 'plugins-themes', 'submodule' => 'detect-bad-plugins', 'label' => __( 'Detect Bad Plugins', 'secupress' ), 'anchor' => 'row-plugins_detect_bad_plugins' ],
		[ 'module' => 'plugins-themes', 'submodule' => 'detect-bad-themes', 'label' => __( 'Detect Bad Themes', 'secupress' ), 'anchor' => 'row-themes_detect_bad_themes' ],
		[ 'module' => 'plugins-themes', 'submodule' => 'uploads', 'label' => sprintf( __( 'Disallow %s uploads', 'secupress' ), 'zip' ), 'anchor' => 'row-uploads_activate' ],
		[ 'module' => 'plugins-themes', 'submodule' => 'plugin-installation', 'label' => __( 'Plugin Actions Back-end', 'secupress' ), 'anchor' => 'row-plugins_actions' ],
		[ 'module' => 'plugins-themes', 'submodule' => 'theme-installation', 'label' => __( 'Theme Actions', 'secupress' ), 'anchor' => 'row-themes_actions' ],
		[ 'module' => 'sensitive-data', 'submodule' => 'xmlrpc', 'label' => __( 'XML-RPC Management', 'secupress' ), 'anchor' => 'row-wp-endpoints_xmlrpc' ],
		[ 'module' => 'sensitive-data', 'submodule' => 'blackhole', 'label' => __( 'Blackhole', 'secupress' ), 'anchor' => 'row-content-protect_blackhole' ],
		[ 'module' => 'sensitive-data', 'submodule' => 'directory-listing', 'label' => __( 'Directory Listing', 'secupress' ), 'anchor' => 'row-content-protect_directory-listing' ],
		[ 'module' => 'sensitive-data', 'submodule' => 'php-easter-egg', 'label' => __( 'Hide PHP Info Disclosure', 'secupress' ), 'anchor' => 'row-content-protect_php-disclosure' ],
		[ 'module' => 'sensitive-data', 'check_module' => 'discloses', 'submodule' => 'no-x-powered-by', 'label' => __( 'Hide PHP Version Disclosure', 'secupress' ), 'anchor' => 'row-content-protect_php-version' ],
		[ 'module' => 'sensitive-data', 'check_module' => 'discloses', 'submodule' => 'wp-version', 'label' => __( 'Hide WP Version Disclosure', 'secupress' ), 'anchor' => 'row-content-protect_wp-version' ],
		[ 'module' => 'sensitive-data', 'check_module' => 'discloses', 'submodule' => 'readmes', 'label' => __( 'Readme Files Protection', 'secupress' ), 'anchor' => 'row-content-protect_readmes' ],
		[ 'module' => 'sensitive-data', 'label' => __( 'Bad URL Access', 'secupress' ), 'anchor' => 'row-content-protect_bad-url-access', 'done' => $bad_url_on ],
		[ 'module' => 'wordpress-core', 'label' => __( 'Change Database Prefix', 'secupress' ), 'anchor' => 'module-database', 'pro' => true, 'done' => $prefix_ok ],
		[ 'module' => 'ssl', 'submodule' => 'force-https', 'label' => __( 'Force HTTPS', 'secupress' ), 'anchor' => 'row-ssl_force-https', 'icon' => 'lock' ],
		[ 'module' => 'antispam', 'submodule' => 'fightspam', 'label' => __( 'Anti-Spam', 'secupress' ), 'anchor' => 'row-antispam_antispam' ],
		[ 'module' => 'alerts', 'label' => __( 'alerts', 'secupress' ), 'anchor' => 'module-event-alerts', 'pro' => true, 'done' => $alerts_on ],
		[ 'module' => 'schedules', 'label' => __( 'scheduled tasks', 'secupress' ), 'anchor' => 'module-scanners', 'pro' => true, 'done' => $schedules_on ],
		[ 'module' => 'file-system', 'label' => __( 'running a malware scan', 'secupress' ), 'url' => secupress_admin_url( 'modules', 'file-system' ), 'done' => $malware_fresh ],
		[ 'module' => 'welcome', 'icon' => 'secupress-simple', 'label' => __( 'running the 35-point security scan', 'secupress' ), 'url' => secupress_admin_url( 'scanners' ), 'done' => $scan_fresh ],
	];

	if ( class_exists( 'SitePress' ) ) {
		$candidates[] = [ 'module' => 'sensitive-data', 'check_module' => 'discloses', 'submodule' => 'wpml-version', 'label' => sprintf( __( 'Hide %s version', 'secupress' ), 'WPML' ), 'anchor' => 'row-content-protect_plugin-version-discloses' ];
	}

	if ( class_exists( 'WooCommerce' ) ) {
		$candidates[] = [ 'module' => 'sensitive-data', 'check_module' => 'discloses', 'submodule' => 'woocommerce-version', 'label' => sprintf( __( 'Hide %s version', 'secupress' ), 'WooCommerce' ), 'anchor' => 'row-content-protect_plugin-version-discloses' ];
	}

	return $candidates;
}

/**
 * One inactive quick win, chosen at random.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @return (array|null)
 **/
function secupress_attacks_widget_get_quick_win() {
	$modules = secupress_get_modules();
	$open    = [];

	foreach ( secupress_attacks_widget_quick_win_candidates() as $candidate ) {
		if ( ! empty( $candidate['pro'] ) && ! secupress_is_pro() ) {
			continue;
		}

		$check_module = ! empty( $candidate['check_module'] ) ? $candidate['check_module'] : $candidate['module'];

		if ( ! empty( $candidate['submodule'] ) ) {
			if ( ! secupress_is_pro() && secupress_submodule_is_pro( $check_module, $candidate['submodule'] ) ) {
				continue;
			}
			$done = secupress_is_submodule_active( $check_module, $candidate['submodule'] );
		} else {
			$done = ! empty( $candidate['done'] );
		}

		if ( $done ) {
			continue;
		}

		$open[] = $candidate;
	}

	if ( ! $open ) {
		return null;
	}

	$candidate = $open[ wp_rand( 0, count( $open ) - 1 ) ];
	$icon      = ! empty( $candidate['icon'] ) ? $candidate['icon'] : '';

	if ( ! $icon && ! empty( $modules[ $candidate['module'] ]['icon'] ) ) {
		$icon = $modules[ $candidate['module'] ]['icon'];
	}
	if ( ! $icon ) {
		$icon = 'secupress-simple';
	}

	if ( ! empty( $candidate['url'] ) ) {
		$url = $candidate['url'];
	} else {
		$url = secupress_admin_url( 'modules', $candidate['module'] ) . '#' . $candidate['anchor'];
	}

	return [
		'label' => $candidate['label'],
		'url'   => $url,
		'icon'  => $icon,
	];
}

/**
 * Secondary line under the period count: the lifetime total.
 *
 * Empty when the selected period is already that total.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @param (int)    $lifetime_total Cumulative blocked attacks.
 * @param (string) $range          Selected period.
 *
 * @return (string)
 **/
function secupress_attacks_widget_lifetime_label( $lifetime_total, $range ) {
	if ( 'all' === (string) $range ) {
		return '';
	}

	return sprintf(
		/* translators: %s: formatted number of blocked attacks since the beginning. */
		__( '%s in total', 'secupress' ),
		number_format_i18n( (int) $lifetime_total )
	);
}

/**
 * Overview placed above the lifetime attacks card.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @param (int) $lifetime_total Cumulative blocked attacks.
 **/
function secupress_attacks_widget_print_overview( $lifetime_total ) {
	$range    = secupress_attacks_widget_get_range();
	$period   = secupress_attacks_widget_get_period( $range, $lifetime_total );
	$ranges   = secupress_attacks_widget_ranges();
	$firewall = secupress_attacks_widget_firewall_status();
	$last     = secupress_attacks_widget_last_block_text();
	$quick    = secupress_attacks_widget_get_quick_win();
	$count    = (int) $period['count'];
	$lifetime = secupress_attacks_widget_lifetime_label( $lifetime_total, $range );
	$percent  = secupress_attacks_widget_percent_parts( $period['percent'] );
	$trends   = secupress_get_attacks_widget_trends();

	if ( secupress_is_security_paused() ) {
		$protect_state = 'paused';
		$protect_label = _x( 'Paused', 'security', 'secupress' );
	} elseif ( secupress_count_active_submodules() > 0 ) {
		$protect_state = 'active';
		$protect_label = _x( 'Protected', 'website', 'secupress' );
	} else {
		$protect_state = 'idle';
		$protect_label = _x( 'No protection', 'website', 'secupress' );
	}

	$firewall_class = 'secupress-security-status-count secupress-security-status-firewall secupress-attacks-overview-pill';
	if ( $firewall['state'] ) {
		$firewall_class .= ' secupress-security-status-firewall-' . $firewall['state'];
	}

	echo '<div class="secupress-attacks-overview">';
	echo '<div class="secupress-attacks-overview-top">';
	echo '<div class="secupress-attacks-overview-pills">';
	echo '<span class="secupress-security-status-count secupress-attacks-overview-pill secupress-attacks-overview-pill-' . esc_attr( $protect_state ) . '">';
	echo '<span class="secupress-firewall-dot" aria-hidden="true"></span>';
	echo esc_html( $protect_label );
	echo '</span>';
	echo '<span class="' . esc_attr( $firewall_class ) . '">';
	echo '<span class="secupress-firewall-dot" aria-hidden="true"></span>';
	echo esc_html( $firewall['label'] );
	echo '</span>';
	echo '</div>';
	if ( $last ) {
		echo '<div class="secupress-attacks-overview-last">' . esc_html( $last ) . '</div>';
	}
	echo '</div>';

	echo '<div class="secupress-attacks-overview-main">';
	echo '<div class="secupress-attacks-overview-figure">';
	echo '<span class="secupress-attacks-overview-count" data-count="' . esc_attr( $count ) . '">0</span>';
	echo '<span class="secupress-attacks-overview-copy">';
	echo '<span class="secupress-attacks-overview-caption">' . esc_html( _n( 'attack blocked', 'attacks blocked', $count, 'secupress' ) ) . '</span>';
	echo '<span class="secupress-attacks-overview-lifetime"' . ( $lifetime ? '' : ' hidden' ) . '>' . esc_html( $lifetime ) . '</span>';
	echo '</span>';
	echo '</div>';
	echo '<div class="secupress-attacks-overview-meta">';
	echo '<div class="secupress-attacks-overview-select">';
	echo '<span class="secupress-attacks-overview-past"' . ( in_array( (string) $range, [ 'all', '1' ], true ) ? ' hidden' : '' ) . '>' . esc_html__( 'Past', 'secupress' ) . '</span>';
	echo '<label class="screen-reader-text" for="secupress-attacks-widget-range">' . esc_html__( 'Period', 'secupress' ) . '</label>';
	echo '<select id="secupress-attacks-widget-range">';
	foreach ( $ranges as $value => $label ) {
		echo '<option value="' . esc_attr( $value ) . '"' . selected( $range, (string) $value, false ) . '>' . esc_html( $label ) . '</option>';
	}
	echo '</select>';
	echo '</div>';
	echo '<div class="secupress-attacks-overview-percent ' . esc_attr( $percent['class'] ) . '">' . esc_html( $percent['text'] ) . '</div>';
	echo '</div>';
	echo '</div>';

	echo '<div class="secupress-attacks-overview-chart">';
	echo '<canvas id="secupress-attacks-overview-chart" height="64"></canvas>';
	echo '</div>';
	if ( $period['start_caption'] || $period['end_caption'] ) {
		echo '<div class="secupress-attacks-overview-axis">';
		echo '<span>' . esc_html( $period['start_caption'] ) . '</span>';
		echo '<span>' . esc_html( $period['end_caption'] ) . '</span>';
		echo '</div>';
	}

	if ( $trends['yesterday'] || $trends['period'] ) {
		echo '<div class="secupress-attacks-overview-stats">';
		if ( $trends['yesterday'] ) {
			secupress_attacks_widget_print_trend( $trends['yesterday'] );
		}
		if ( $trends['period'] ) {
			secupress_attacks_widget_print_trend( $trends['period'] );
		}
		echo '</div>';
	}
	echo '</div>';

	if ( $quick ) {
		$quick_titles = [
			__( 'Instant Protection', 'secupress' ),
			__( 'One-Tap Security', 'secupress' ),
			__( 'Express Install', 'secupress' ),
			__( 'Effortless Setup', 'secupress' ),
			__( 'High-Impact Defense', 'secupress' ),
			__( 'Easy Win Activation', 'secupress' ),
		];
		$quick_title  = $quick_titles[ wp_rand( 0, count( $quick_titles ) - 1 ) ];
		$quick_lines  = [
			__( 'Eliminates a key security issue.', 'secupress' ),
			__( 'Fixes a loophole in your protection.', 'secupress' ),
			__( 'Fills a missing link in your defense.', 'secupress' ),
			__( 'Patches a critical security gap.', 'secupress' ),
			__( 'Seals a blind spot in your security.', 'secupress' ),
			__( 'Covers a critical weak link in your defenses.', 'secupress' ),
			__( 'Plugs an open security hole.', 'secupress' ),
			__( 'Protects an unshielded attack vector.', 'secupress' ),
			__( 'Neutralizes a hidden threat path.', 'secupress' ),
			__( 'Fixes an exposure risk.', 'secupress' ),
		];
		$quick_line   = $quick_lines[ wp_rand( 0, count( $quick_lines ) - 1 ) ];
		echo '<div class="secupress-attacks-quickwin">';
		echo '<span class="secupress-attacks-quickwin-icon">';
		echo '<i class="secupress-icon-' . esc_attr( $quick['icon'] ) . '" aria-hidden="true"></i>';
		echo '</span>';
		echo '<div class="secupress-attacks-quickwin-text">';
		echo '<strong>' . esc_html( sprintf(
			/* translators: 1: banner title, 2: protection name. */
			__( '%1$s: enable %2$s', 'secupress' ),
			$quick_title,
			wp_strip_all_tags( $quick['label'] )
		) ) . '</strong>';
		echo '<span>' . esc_html( $quick_line ) . '</span>';
		echo '</div>';
		echo '<a class="button" href="' . esc_url( $quick['url'] ) . '">' . esc_html__( 'Activate', 'secupress' ) . '</a>';
		echo '</div>';
	}
}

/**
 * Footer links of the attacks widget.
 *
 * @author Julio Potier
 * @since 2.7.2
 **/
function secupress_attacks_widget_print_links() {
	echo '<div class="secupress-attacks-links">';
	if ( secupress_is_submodule_active( 'logs', 'action-logs' ) ) {
		echo '<a class="secupress-attacks-links-logs" href="' . esc_url( secupress_admin_url( 'logs' ) ) . '">';
		echo '<i class="secupress-icon-logs" aria-hidden="true"></i>';
		echo esc_html__( 'View logs', 'secupress' );
		echo '</a>';
	}
	echo '<a class="secupress-attacks-links-modules" href="' . esc_url( secupress_admin_url( 'modules' ) ) . '">';
	echo esc_html__( 'Open Modules', 'secupress' );
	echo '<span class="secupress-attacks-links-arrow" aria-hidden="true"></span>';
	echo '</a>';
	echo '</div>';
}

/**
 * Dashicon for one attack type.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @param (string) $type Attack type.
 *
 * @return (string)
 **/
function secupress_attacks_widget_type_icon( $type ) {
	$icons = [
		'ban_ip'              => 'dashicons-shield-alt',
		'plugins'             => 'dashicons-admin-plugins',
		'themes'              => 'dashicons-admin-appearance',
		'geoip'               => 'dashicons-location',
		'zipfile'             => 'dashicons-media-archive',
		'xmlrpc'              => 'dashicons-rss',
		'move_login'          => 'dashicons-lock',
		'login'               => 'dashicons-id',
		'loginattempts'       => 'dashicons-admin-users',
		'passwordspraying'    => 'dashicons-privacy',
		'users'               => 'dashicons-groups',
		'bad_robots'          => 'dashicons-admin-generic',
		'bad_request_content' => 'dashicons-warning',
		'honeypot'            => 'dashicons-visibility',
		'option'              => 'dashicons-admin-settings',
	];

	return isset( $icons[ $type ] ) ? $icons[ $type ] : 'dashicons-editor-help';
}

/**
 * Short explanation of what one attack type blocks.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @param (string) $type Attack type.
 *
 * @return (string)
 **/
function secupress_attacks_widget_type_description( $type ) {
	$descriptions = [
		'ban_ip'              => __( 'Attempts to reach the site from an IP address that is banned.', 'secupress' ),
		'plugins'             => __( 'Attempts to install, activate, deactivate, or delete a plugin.', 'secupress' ),
		'themes'              => __( 'Attempts to install, delete, or switch a theme.', 'secupress' ),
		'geoip'               => __( 'Attempts to access the site from a country that is not allowed.', 'secupress' ),
		'zipfile'             => __( 'Attempts to upload a plugin or a theme as a ZIP file.', 'secupress' ),
		'xmlrpc'              => __( 'Attempts to use XML-RPC while it is disabled.', 'secupress' ),
		'move_login'          => __( 'Attempts to open the login page at an address that is no longer valid.', 'secupress' ),
		'login'               => __( 'Sign-in attempts blocked by a password rule, a forbidden username, or a second factor.', 'secupress' ),
		'loginattempts'       => __( 'Sign-in attempts that use an unknown username or a rejected password.', 'secupress' ),
		'passwordspraying'    => __( 'The same password tried against too many accounts.', 'secupress' ),
		'users'               => __( 'Attempts to list, create, or change user accounts outside the allowed rules.', 'secupress' ),
		'bad_robots'          => __( 'Bots that opened a hidden trap link.', 'secupress' ),
		'bad_request_content' => __( 'Requests that contain malicious data, such as an injection attempt.', 'secupress' ),
		'honeypot'            => __( 'Forms submitted to a trap that only an attacker would fill in.', 'secupress' ),
		'option'              => __( 'Attempts to read a WordPress option known as malware.', 'secupress' ),
	];

	return isset( $descriptions[ $type ] ) ? $descriptions[ $type ] : '';
}

/**
 * One compact row per named attack type, on the overview period.
 *
 * "all" shows the lifetime total of that type. Any other period shows the sum of that window.
 * The sparkline always uses the same days as the overview chart.
 * For "today", that line includes yesterday, and the count stays on today.
 * A key without a dedicated title stays in the log and in the total, and is not listed here.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @param (string) $range all, 1, 7, 30 or 360.
 *
 * @return (array)
 **/
function secupress_attacks_widget_type_rows( $range ) {
	$attacks = secupress_get_attacks();
	$attacks = is_array( $attacks ) ? $attacks : [];
	$order   = [
		'ban_ip',
		'plugins',
		'themes',
		'geoip',
		'zipfile',
		'xmlrpc',
		'move_login',
		'login',
		'loginattempts',
		'passwordspraying',
		'users',
		'bad_robots',
		'bad_request_content',
		'honeypot',
		'option',
	];
	$types   = [];

	foreach ( $order as $type ) {
		if ( isset( $attacks[ $type ] ) ) {
			$types[] = $type;
		}
	}

	$period = secupress_attacks_widget_get_period( $range, 0 );
	$rows   = [];

	foreach ( $types as $type ) {
		$data = $attacks[ $type ];
		if ( is_array( $data ) ) {
			$lifetime = secupress_calculate_attack_total( $data );
		} elseif ( is_numeric( $data ) ) {
			$lifetime = (int) $data;
		} else {
			$lifetime = 0;
		}

		$daily     = secupress_get_attacks_daily_totals( $type );
		$points    = [];
		$sum       = 0;
		$sum_keys  = isset( $period['sum_keys'] ) ? $period['sum_keys'] : $period['keys'];
		$in_sum    = array_fill_keys( $sum_keys, true );

		foreach ( $period['keys'] as $key ) {
			$value    = isset( $daily[ $key ] ) ? (int) $daily[ $key ] : 0;
			$points[] = $value;
			if ( isset( $in_sum[ $key ] ) ) {
				$sum += $value;
			}
		}

		$rows[] = [
			'type'        => $type,
			'icon'        => secupress_attacks_widget_type_icon( $type ),
			'title'       => secupress_attacks_get_type_title( $type ),
			'description' => secupress_attacks_widget_type_description( $type ),
			'count'     => 'all' === $range ? $lifetime : $sum,
			'labels'    => $period['labels'],
			'points'    => $points,
			'point_dot' => count( $points ) > 2 ? 0 : 1,
		];
	}

	return $rows;
}

/**
 * Chart payloads for the per-type sparklines.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @param (string|null) $range Period. Current user period when omitted.
 *
 * @return (array)
 **/
function secupress_attacks_widget_type_chart_payload( $range = null ) {
	if ( null === $range ) {
		$range = secupress_attacks_widget_get_range();
	}

	$charts = [];

	foreach ( secupress_attacks_widget_type_rows( $range ) as $row ) {
		$active   = $row['count'] > 0;
		$charts[] = [
			'type'     => $row['type'],
			'id'       => 'secupress-attack-spark-' . $row['type'],
			'count'    => (int) $row['count'],
			'labels'   => $row['labels'],
			'data'     => $row['points'],
			'color'    => $active ? '#26B3A9' : '#c3c4c7',
			'fill'     => $active ? 'rgba(38,179,169,0.16)' : 'rgba(195,196,199,0.25)',
			'pointDot' => $row['point_dot'],
			'bezier'   => 0,
			'spark'    => 1,
		];
	}

	return $charts;
}

/**
 * Demo banner for the worldwide attacks upsell.
 *
 * @author Julio Potier
 * @since 2.7.2
 **/
function secupress_attacks_widget_print_cloud_banner() {
	$count = 150000;

	echo '<div class="secupress-attacks-cloud">';
	echo '<div class="secupress-attacks-cloud-copy">';
	echo '<strong>' . wp_kses(
		sprintf(
			/* translators: %s is the worldwide blocked-attack count. */
			__( 'SecuPress blocked %s attacks worldwide today.', 'secupress' ),
			'<span class="secupress-attacks-cloud-count">' . esc_html( number_format_i18n( $count ) ) . '</span>'
		),
		[
			'span' => [
				'class' => true,
			],
		]
	) . '</strong>';
	echo '<span>' . esc_html__( 'Other protected sites already saw this wave. Get those signals and block it before it reaches you.', 'secupress' ) . '</span>';
	echo '</div>';
	echo '<a class="button secupress-attacks-cloud-cta" href="' . esc_url( secupress_admin_url( 'get-pro' ) ) . '">' . esc_html__( 'Get it', 'secupress' ) . '</a>';
	echo '</div>';
}

/**
 * Render the attacks dashboard widget.
 *
 * @author Julio Potier
 * @since 2.2.6
 *
 * @param (array|null) $attacks Attack log passed by the dashboard widget.
 **/
function secupress_attacks_render_dashboard_widget( $attacks = null ) {
	if ( ! is_array( $attacks ) ) {
		$attacks = secupress_get_attacks();
	}

	$all_total = isset( $attacks['all'] ) && is_numeric( $attacks['all'] ) ? (int) $attacks['all'] : 0;

	$rows = secupress_attacks_widget_type_rows( secupress_attacks_widget_get_range() );

	echo '<div class="secupress-attacks-widget">';
	/*
	secupress_attacks_widget_print_cloud_banner();
	*/
	secupress_attacks_widget_print_overview( $all_total );

	if ( $rows ) {
		echo '<div class="secupress-attacks-types">';
		foreach ( $rows as $row ) {
			$classes = 'secupress-attack-row';
			if ( ! $row['count'] ) {
				$classes .= ' is-zero';
			}
			echo '<div class="' . esc_attr( $classes ) . '" data-type="' . esc_attr( $row['type'] ) . '">';
			echo '<span class="secupress-attack-row-label" tabindex="0" aria-describedby="secupress-attack-tip-' . esc_attr( $row['type'] ) . '">';
			echo '<span class="secupress-attack-row-icon dashicons ' . esc_attr( $row['icon'] ) . '" aria-hidden="true"></span>';
			echo '<span class="secupress-attack-row-title">' . esc_html( $row['title'] ) . '</span>';
			if ( $row['description'] ) {
				echo '<span class="secupress-attack-row-tip" id="secupress-attack-tip-' . esc_attr( $row['type'] ) . '" role="tooltip">' . esc_html( $row['description'] ) . '</span>';
			}
			echo '</span>';
			echo '<span class="secupress-attack-spark">';
			echo '<canvas id="secupress-attack-spark-' . esc_attr( $row['type'] ) . '" height="28"></canvas>';
			echo '</span>';
			echo '<span class="secupress-attack-row-count">' . esc_html( number_format_i18n( $row['count'] ) ) . '</span>';
			echo '</div>';
		}
		echo '</div>';
	}

	secupress_attacks_widget_print_links();
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
			_nx( '%s unused', '%s unused', $unused_themes, 'themes', 'secupress' ),
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
	$plugins_value  = sprintf( _nx( '%s/%s activated', '%s/%s activated', $plugins_active, 'plugins', 'secupress' ), number_format_i18n( $plugins_active ), number_format_i18n( $plugins_total ) );
	if ( $plugins_update ) {
		$plugins_value .= ' (' . sprintf(
			_nx( '%s to update', '%s to update', $plugins_update, 'plugins', 'secupress' ),
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
		'value'   => $blog_public ? _x( 'Indexed', 'website', 'secupress' ) : __( 'Indexing discouraged', 'secupress' ),
		'status'  => $index_status,
		'tooltip' => [],
		'hint'    => $index_hint,
	];

	$can_register = secupress_users_can_register();
	$items[] = [
		'icon'    => 'dashicons-id',
		'label'   => __( 'Registrations', 'secupress' ),
		'value'   => $can_register ? _x( 'Open', 'registrations', 'secupress' ) : _x( 'Closed', 'registrations', 'secupress' ),
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
		$server = _x( 'Unknown', 'web server', 'secupress' );
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
		'icon'    => 'dashicons-media-text',
		'label'   => __( 'Physical robots.txt', 'secupress' ),
		'value'   => $has_robots ? _x( 'Present', 'file', 'secupress' ) : _x( 'None', 'file', 'secupress' ),
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
			'value'   => $exists ? _x( 'Present', 'file', 'secupress' ) : _x( 'Missing', 'file', 'secupress' ),
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
			'value'   => $exists ? _x( 'Present', 'file', 'secupress' ) : _x( 'Missing', 'file', 'secupress' ),
			'status'  => $exists ? '' : 'danger',
			'tooltip' => [],
			'hint'    => $exists ? '' : __( 'The web.config file is missing. Restore it so IIS rewrite rules can be applied.', 'secupress' ),
		];
	}

	return $items;
}

/**
 * Tell if an update is available for this plugin.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @return (bool)
 **/
function secupress_system_widget_has_plugin_update() {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	if ( ! function_exists( 'get_plugin_updates' ) ) {
		require_once ABSPATH . 'wp-admin/includes/update.php';
	}

	$updates = get_plugin_updates();
	$file    = plugin_basename( SECUPRESS_FILE );

	return isset( $updates[ $file ] );
}

/**
 * Map a firewall state to a system-status row level.
 *
 * @author Julio Potier
 * @since 2.7.2
 *
 * @param (string) $state Firewall state from secupress_attacks_widget_firewall_status().
 *
 * @return (string) Empty, "warning", or "danger".
 **/
function secupress_system_widget_firewall_row_status( $state ) {
	if ( in_array( $state, [ 'paused', 'off' ], true ) ) {
		return 'danger';
	}
	if ( 'partial' === $state ) {
		return 'warning';
	}
	return '';
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

	$modules_count = secupress_count_active_submodules();
	$has_update    = secupress_system_widget_has_plugin_update();
	$modules_text  = '';
	if ( secupress_is_expert_mode() ) {
		$modules_text = __( 'Expert Mode', 'secupress' ) . ' — ';
	}
	$modules_text .= sprintf(
		_n( '%s active module', '%s active modules', $modules_count, 'secupress' ),
		number_format_i18n( $modules_count )
	);
	if ( $has_update ) {
		$modules_text = sprintf( __( '%s — not up to date', 'secupress' ), $modules_text );
	}
	if ( ! $modules_count ) {
		$secupress_status = 'danger';
		$secupress_hint   = sprintf( __( '%s is running, but no module is protecting the site.', 'secupress' ), SECUPRESS_PLUGIN_NAME );
		if ( $has_update ) {
			$secupress_hint .= ' ' . sprintf( __( '%s is not up to date. Update it from Plugins → Installed Plugins.', 'secupress' ), SECUPRESS_PLUGIN_NAME );
		}
	} elseif ( $has_update ) {
		$secupress_status = 'warning';
		$secupress_hint   = sprintf( __( '%s is not up to date. Update it from Plugins → Installed Plugins.', 'secupress' ), SECUPRESS_PLUGIN_NAME );
	} else {
		$secupress_status = '';
		$secupress_hint   = '';
	}
	$items[] = [
		'icon'    => 'core/settings',
		'label'   => SECUPRESS_PLUGIN_NAME,
		'value'   => $modules_text,
		'status'  => $secupress_status,
		'tooltip' => secupress_get_active_submodule_labels(),
		'hint'    => $secupress_hint,
	];

	$firewall        = secupress_attacks_widget_firewall_status();
	$firewall_status = secupress_system_widget_firewall_row_status( $firewall['state'] );
	$items[] = [
		'icon'    => 'core/shield',
		'label'   => sprintf( __( '%s Firewall', 'secupress' ), SECUPRESS_PLUGIN_NAME ),
		'value'   => $firewall['label'],
		'status'  => $firewall_status,
		'tooltip' => [],
	];

	if ( secupress_is_security_paused() ) {
		$items[] = [
			'icon'    => 'core/caution',
			'label'   => __( 'Security', 'secupress' ),
			'value'   => __( 'Paused', 'secupress' ),
			'status'  => 'danger',
			'tooltip' => [],
			'hint'    => __( 'Protection it currently paused. Turn it back on to keep your site secured.', 'secupress' ),
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
		'icon'    => 'secupress-icon-radar',
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

	$license = secupress_get_option( 'license' );
	$status  = is_array( $license ) && ! empty( $license['status'] ) ? $license['status'] : '';

	if ( 'expired' === $status ) {
		$account = trailingslashit( set_url_scheme( SECUPRESS_WEB_MAIN, 'https' ) ) . _x( 'account', 'link to website (Only FR or EN!)', 'secupress' );
		return [
			'type'   => 'expired',
			'status' => 'danger',
			'html'   => '<a href="' . esc_url( $account ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'License expired', 'secupress' ) . '</a>',
			'hint'   => __( 'Your Pro license has expired. Renew it to keep updates and Pro features.', 'secupress' ),
		];
	}

	$settings = secupress_admin_url( 'modules' );
	return [
		'type'   => 'missing',
		'status' => 'warning',
		'html'   => '<a href="' . esc_url( $settings ) . '">' . esc_html__( 'Enter your license', 'secupress' ) . '</a>',
		'hint'   => __( 'Pro is installed. Enter your license key to activate Pro features.', 'secupress' ),
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
	if ( 0 === strpos( $name, 'secupress-icon-' ) ) {
		return '<span class="' . esc_attr( $name ) . '" aria-hidden="true"></span>';
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
