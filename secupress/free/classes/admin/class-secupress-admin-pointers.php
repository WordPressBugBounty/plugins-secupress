<?php
/**
 * Administration API: SecuPress_Admin_Pointers class
 *
 * @since 2.0
 */

/**
 * Core class used to implement an internal admin pointers API.
 *
 * @since 2.0
 */
final class SecuPress_Admin_Pointers {

	/**
	 * Initializes the new feature pointers.
	 *
	 * @since 2.0
	 *
	 * All pointers can be disabled using the following:
	 *     remove_action( 'admin_enqueue_scripts', array( 'SecuPress_Admin_Pointers', 'enqueue_scripts' ) );
	 *
	 * Individual pointers (e.g. spxx_foobar) can be disabled using the following:
	 *     remove_action( 'admin_print_footer_scripts', array( 'SecuPress_Admin_Pointers', 'pointer_spxx_foobar' ) );
	 *
	 * @static
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public static function enqueue_scripts( $hook_suffix ) {
		if ( ! current_user_can( secupress_get_capability() ) ) {
			return;
		}

		self::enqueue_tour( $hook_suffix );

		/*
		 * Register feature pointers
		 *
		 * Format:
		 *     array(
		 *         hook_suffix => pointer callback
		 *     )
		 *
		 * Example:
		 *     array(
		 *         'plugins.php' => 'spxx_foobar',
		 *         'secupress_page_secupress_xx' => 'spxx_foobar'
		 *     )
		 */
		$modules_page         = SECUPRESS_PLUGIN_SLUG . '_page_' . SECUPRESS_PLUGIN_SLUG . '_modules';
		$registered_pointers = [
			$modules_page              => [
				'any' => [ 'sp22_ad' ],
				//'logs'      => [ 'sp21_httplogs' ],
			],
			$modules_page . '-network' => [
				'any' => [ 'sp22_ad' ],
			],
		];

		// Check if screen related pointer is registered.
		if ( ! isset( $registered_pointers[ $hook_suffix ] ) ) {
			return;
		}
		$pointers     = isset( $registered_pointers[ $hook_suffix ]['any'] ) ? $registered_pointers[ $hook_suffix ]['any'] : [];
		$module       = isset( $_GET['module'] ) ? sanitize_key( $_GET['module'] ) : 'any'; // Do not translate.
		if ( 'any' !== $module && isset( $registered_pointers[ $hook_suffix ][ $module ] ) ) {
			$pointers = array_merge( $pointers, $registered_pointers[ $hook_suffix ][ $module ] );
		}
		$dismissed    = explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );
		$pointers     = array_diff( $pointers, $dismissed );
		// Limit pointers to 2 per screen order by the natural array order
		$pointers     = array_slice( $pointers, 0, 2 );
		$pointers     = array_flip( $pointers );
		$pointers     = array_flip( $pointers );
		foreach ( $pointers as $pointer ) {
			// Bind pointer print function
			add_action( 'admin_print_footer_scripts', array( 'SecuPress_Admin_Pointers', 'pointer__' . $pointer ) );
			// Add pointers script and style to queue
			wp_enqueue_style( 'wp-pointer' );
			wp_enqueue_script( 'wp-pointer' );
			add_action( 'admin_print_footer_scripts', array( 'SecuPress_Admin_Pointers', 'print_pointer_css_rules' ) );
		}

	}

	/**
	 * Get the pointer tours.
	 *
	 * @since 2.7
	 *
	 * @return (array)
	 */
	public static function get_tours() {
		$app_passwords  = '<h3><span class="dashicons dashicons-star-filled"></span> ' . __( 'New: Application Passwords', 'secupress' ) . '</h3>';
		$app_passwords .= '<h4>' . __( 'Get notified when an application password is created on your account', 'secupress' ) . '</h4>';
		$app_passwords .= '<p>' . __( 'WordPress application passwords can grant REST API access to an account. Enable this setting to email the user (and the site admin if needed) whenever one is added.', 'secupress' ) . '</p>';

		$pause_security  = '<h3><span class="dashicons dashicons-star-filled"></span> ' . __( 'New: Pause the security for 30 minutes', 'secupress' ) . '</h3>';
		$pause_security .= '<p>' . __( 'You can now pause the security for 30 minutes to allow you to test the site without being protected.', 'secupress' ) . '</p>';

		$widget  = '<h3><span class="dashicons dashicons-star-filled"></span> ' . __( 'New: Dashboard widget "System Status"', 'secupress' ) . '</h3>';
		$widget .= '<p>' . __( 'This widget shows some useful information about the site. Keep an eye on it!', 'secupress' ) . '</p>';

		$backups = '<h3><span class="dashicons dashicons-star-filled"></span> ' . __( 'New: Password protection for backups (Pro)', 'secupress' ) . '</h3>';
		$backups .= '<p>' . __( 'You can now protect your backups with a password. This will prevent unauthorized access to your backups.', 'secupress' ) . '</p>';
		$backups .= '<p>' . __( 'Backups are now stored outside of the site directory if possible.', 'secupress' ) . '</p>';

		$hotlinks = '<h3><span class="dashicons dashicons-star-filled"></span> ' . __( 'New: Expert settings for Anti Hotlink', 'secupress' ) . '</h3>';
		$hotlinks .= '<p>' . __( 'You can now configure settings for Anti Hotlink. This will allow you to be more accurate in blocking hotlinks.', 'secupress' ) . '</p>';
		
		$pointers = [
			[
				'id'       => 'sp27_pause_security',
				'selector' => '.secupress-security-status',
				'url'      => secupress_admin_url( 'modules', 'dashboard' ),
				'content'  => $pause_security,
				'options'  => [
					'position'     => [
						'edge'  => 'right',
						'align' => 'bottom',
					],
					'pointerClass' => 'wp-pointer arrow-bottom',
					'pointerWidth' => (int) _x( '400', 'pointerWidth', 'secupress' ),
				],
			],
			[
				'id'       => 'sp27_app_passwords',
				'selector' => '.secupress-setting-row_password-policy_application-passwords',
				'url'      => secupress_admin_url( 'modules', 'users-login' ) . '#row-password-policy_application-passwords',
				'content'  => $app_passwords,
				'options'  => [
					'position'     => [
						'edge'  => 'right',
						'align' => 'bottom',
					],
					'pointerClass' => 'wp-pointer arrow-bottom',
					'pointerWidth' => (int) _x( '400', 'pointerWidth', 'secupress' ),
				],
			],
			[
				'id'       => 'sp27_widget',
				'selector' => '#secupress-system-widget',
				'url'      => admin_url( 'index.php' ),
				'content'  => $widget,
				'options'  => [
					'position'     => [
						'edge'  => 'left',
						'align' => 'top',
					],
					'pointerClass' => 'wp-pointer arrow-bottom',
					'pointerWidth' => (int) _x( '400', 'pointerWidth', 'secupress' ),
				],
			],			
			[
				'id'       => 'sp27_backups',
				'selector' => '.secupress-setting-row_backups-storage_password',
				'url'      => secupress_admin_url( 'modules', 'backups' ) . '#row-backups-storage_password',
				'content'  => $backups,
				'options'  => [
					'position'     => [
						'edge'  => 'right',
						'align' => 'top',
					],
					'pointerClass' => 'wp-pointer arrow-bottom',
					'pointerWidth' => (int) _x( '400', 'pointerWidth', 'secupress' ),
				],
			],
		];

		if ( secupress_is_expert_mode() ) {
			$pointers[] = [
				'id'       => 'sp27_hotlinks',
				'selector' => '.secupress-setting-row_content-protect_hotlink',
				'url'      => secupress_admin_url( 'modules', 'sensitive-data' ) . '#row-content-protect_hotlink',
				'content'  => $hotlinks,
				'options'  => [
					'position'     => [
						'edge'  => 'right',
						'align' => 'top',
					],
					'pointerClass' => 'wp-pointer arrow-bottom',
					'pointerWidth' => (int) _x( '400', 'pointerWidth', 'secupress' ),
				],
			];
		}

		return $pointers;
	}

	/**
	 * Get dismissed WP / SecuPress pointers for the current user.
	 *
	 * @since 2.7
	 *
	 * @return (array)
	 */
	public static function get_dismissed_pointers() {
		static $dismissed;
		if ( ! isset( $dismissed ) ) {
			$dismissed = array_filter( explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) ) );
		}
		return $dismissed;
	}

	/**
	 * Get remaining (not dismissed) tour steps.
	 *
	 * @since 2.7
	 *
	 * @return (array)
	 */
	public static function get_tour_remaining_steps() {
		$dismissed = self::get_dismissed_pointers();
		$remaining = [];
		foreach ( self::get_tours() as $number => $step ) {
			if ( in_array( $step['id'], $dismissed, true ) ) {
				continue;
			}
			$step['number'] = $number + 1;
			$remaining[]    = $step;
		}
		return $remaining;
	}

	/**
	 * Whether the whole tour has been dismissed.
	 *
	 * @since 2.7
	 *
	 * @return (bool)
	 */
	public static function is_tour_dismissed() {
		return ! self::get_tour_remaining_steps();
	}

	/**
	 * HTML badge with the number of remaining tour steps.
	 *
	 * @since 2.7
	 *
	 * @return (string)
	 */
	public static function get_tour_badge_html() {
		$count = count( self::get_tour_remaining_steps() );
		if ( ! $count ) {
			return '';
		}
		return ' <span class="secupress-tour-count">' . esc_html( $count ) . '</span>';
	}

	/**
	 * Modules menu URL: first remaining pointer page, or the default modules screen.
	 *
	 * @since 2.7
	 *
	 * @return (string)
	 */
	public static function get_tour_modules_url() {
		if ( self::is_tour_dismissed() ) {
			return secupress_admin_url( 'modules' );
		}
		$first = reset( self::get_tour_remaining_steps() );
		return add_query_arg(
			[
				'secupress_pointer_tour' => 1,
				'secupress_pointer_step' => $first['id'],
			],
			$first['url']
		);
	}

	/**
	 * Enqueue a pointer tour when there are remaining steps.
	 *
	 * @since 2.7
	 *
	 * @param (string) $hook_suffix The current admin page.
	 */
	private static function enqueue_tour( $hook_suffix ) {
		$is_sp_page = false !== strpos( $hook_suffix, SECUPRESS_PLUGIN_SLUG );
		$has_query  = ! empty( $_GET['secupress_pointer_tour'] );
		if ( ! $is_sp_page && ! $has_query ) {
			return;
		}

		$steps = self::get_tour_remaining_steps();
		if ( ! $steps ) {
			return;
		}

		$start_id = isset( $_GET['secupress_pointer_step'] ) ? sanitize_key( wp_unslash( $_GET['secupress_pointer_step'] ) ) : '';

		wp_enqueue_style( 'wp-pointer' );
		wp_enqueue_script( 'wp-pointer' );
		wp_enqueue_script( 'secupress-pointers', SECUPRESS_ADMIN_JS_URL . 'secupress-pointers.js', [ 'jquery', 'wp-pointer' ], SECUPRESS_VERSION, true );
		wp_localize_script( 'secupress-pointers', 'SecuPressPointerTour', [
			'id'      => 1,
			'total'   => count( self::get_tours() ),
			'startId' => $start_id,
			'nonce'   => wp_create_nonce( 'dismiss-pointer-tour' ),
			'steps'   => $steps,
			'i18n'    => [
				'next'    => html_entity_decode( __( 'Next &raquo;', 'secupress' ), ENT_QUOTES, 'UTF-8' ),
				'gotIt'   => __( 'Got it', 'secupress' ),
				'dismiss' => _x( 'Dismiss', 'verb', 'secupress' ),
			],
		] );
		add_action( 'admin_print_footer_scripts', [ 'SecuPress_Admin_Pointers', 'print_pointer_css_rules' ] );
	}

	/**
	 * Print the pointer JavaScript data.
	 *
	 * @since 2.0
	 *
	 * @static
	 *
	 * @param string $pointer_id The pointer ID.
	 * @param string $selector The HTML elements, on which the pointer should be attached.
	 * @param array  $args Arguments to be passed to the pointer JS (see wp-pointer.js).
	 */
	private static function print_js( $pointer_id, $selector, $args ) {
		if ( empty( $pointer_id ) || empty( $selector ) || empty( $args ) || empty( $args['content'] ) ) {
			return;
		}
		?>
		<script type="text/javascript">
		(function($){
			var options = <?php echo wp_json_encode( $args ); ?>, setup;

			if ( ! options )
				return;

			options = $.extend( options, {
				close: function() {
					$.post( ajaxurl, {
						pointer: '<?php echo $pointer_id; ?>',
						_ajaxnonce: '<?php echo wp_create_nonce( "dismiss-pointer_{$pointer_id}" ); ?>',
						action: 'dismiss-sp-pointer'
					});
				}
			});

			setup = function() {
				$('<?php echo $selector; ?>').first().pointer( options ).pointer('open');
			};

			if ( options.position && options.position.defer_loading )
				$(window).bind( 'load.wp-pointers', setup );
			else
				$(document).ready( setup );

		})( jQuery );
		</script>
		<?php
	}

	/**
	 * Print the pointer CSS rules.
	 * Don't add those pointers in CSS because they will change the WP one and we cannot add a prefix, just print it.
	 *
	 * @since 2.0
	 *
	 * @static
	 */
	public static function print_pointer_css_rules() {
	?>
		<style>
		.wp-pointer .wp-pointer-content h3 {
			background-color: #26B3A9;
			border-color: #26B3A9;
			padding: 15px 18px 14px 12px;
		}
		.wp-pointer .wp-pointer-content h3 .dashicons {
			font-size: 2em;
			vertical-align: text-bottom;
			margin-right: 7px;
			margin-top: -6px;
			padding: 2px
		}
		.wp-pointer .wp-pointer-content h3 .dashicons-heart {
			color: #CA4A1F;
		}
		.wp-pointer .wp-pointer-content h3 .dashicons-star-filled {
			color: #F1C40F;
		}
		.wp-pointer .wp-pointer-content h3 .dashicons-money-alt {
			color: #FF0;
		}
		.wp-pointer .wp-pointer-content h3:before {
			display: none;
		}
		.wp-pointer .secupress-pointer-buttons {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 12px;
		}
		.wp-pointer .secupress-pointer-nav {
			display: flex;
			align-items: center;
			gap: 8px;
			margin-right: auto;
		}
		</style>
	<?php
	}

	/**
	 * New GeoIP Localisation API
	 *
	 * @since 2.1
	 */
	public static function pointer__sp21_httplogs() {
		$content  = '<h3><span class="dashicons dashicons-star-filled"></span> ' . __( 'New HTTP Logs Feature', 'secupress' ) . '</h3>';
		$content .= '<h4>' . __( 'You can now filter the HTTP outputs', 'secupress' ) . '</h4>';
		$content .= '<p>' . __( 'You can restrict how many time per day each URL can be called.<br>You can also just check which URLs are called from your site.<br>These filters will help you to improve the security AND the loading speed of your site.', 'secupress' ) . '</p>';

		$position = array(
			'edge'  => 'right',
			'align' => 'top',
		);

		$js_args = array(
			'content'  => $content,
			'position' => $position,
			'pointerClass' => 'wp-pointer arrow-bottom',
			/** Translators: Format 'ddd%' or 'ddd', not 'px' */
			'pointerWidth' => _x( '400', 'pointerWidth', 'secupress' ),
		);
		self::print_js( str_replace( 'pointer__', '', __FUNCTION__ ), '.secupress-setting-row_logs_http-logs-activated', $js_args );
	}

	/**
	 * New ad, try SP pro
	 *
	 * @since 2.2
	 */
	public static function pointer__sp22_ad() {
		if ( false !== apply_filters( 'secupress.no_sideads', false ) ) { // Filter secupress_no_sideads.
			return;
		}

		$sideads = get_transient( 'secupress_sideads' );
		if ( ! $sideads || ! isset( $sideads[0]['pointer'] ) ) {
			return;
		}

		$key = 'pointer';
		if ( secupress_locale_is_FR( get_user_locale() ) ) {
			$key .= '-fr_FR';
		}
		$content  = '<h3>' . $sideads[0][ $key ]['title']    . '</h3>';
		$content .= '<h4>' . $sideads[0][ $key ]['subtitle'] . '</h4>';
		$content .= '<p>'  . $sideads[0][ $key ]['desc']     . '</p>';

		$position = array(
			'edge'  => 'right',
			'align' => 'top',
		);

		$js_args = array(
			'content'  => $content,
			'position' => $position,
			'pointerClass' => 'wp-pointer arrow-bottom',
			/** Translators: Format 'ddd%' or 'ddd', not 'px' */
			'pointerWidth' => _x( '400', 'pointerWidth', 'secupress' ),
		);
		self::print_js( str_replace( 'pointer__', '', __FUNCTION__ ), '.secupress-pro-ad', $js_args );
	}

	/**
	 * New addons module
	 *
	 * @since 2.0
	 */
	public static function pointer__sp20_addonszz() {
		if ( isset( $_GET['module'] ) && 'addons' === $_GET['module'] ) {
			secupress_dismiss_pointer_admin_post_cb( 'sp20_addonszz' );
			return;
		}

		$content  = '<h3><span class="dashicons dashicons-heart"></span> ' . __( 'New Module: Addons', 'secupress' ) . '</h3>';
		$content .= '<h4>' . __( 'Discover our 2 brand new recommandations.', 'secupress' ) . '</h4>';

		$position = array(
			'edge'  => 'left',
			'align' => 'bottom',
		);

		$js_args = array(
			'content'  => $content,
			'position' => $position,
			'pointerClass' => 'wp-pointer arrow-bottom',
			/** Translators: Format 'ddd%' or 'ddd', not 'px' */
			'pointerWidth' => _x( '400', 'pointerWidth', 'secupress' ),
		);
		self::print_js( str_replace( 'pointer__', '', __FUNCTION__ ), '.module-addons', $js_args );
	}

	/**
	 * Prevents new users from seeing existing pointers.
	 *
	 * @since 2.0
	 *
	 * @static
	 *
	 * @param int $user_id User ID.
	 */
	public static function dismiss_pointers_for_new_users( $user_id ) {
		add_user_meta( $user_id, 'dismissed_wp_pointers', 'sp22_ad' );
	}
}
