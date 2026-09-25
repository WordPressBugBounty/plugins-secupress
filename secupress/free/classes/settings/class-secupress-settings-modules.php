<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

/**
 * Modules settings class.
 *
 * @package SecuPress
 * @subpackage SecuPress_Settings
 * @since 1.0
 */
class SecuPress_Settings_Modules extends SecuPress_Settings {

	const VERSION = '1.0';

	/**
	 * All the modules, with (mainly) title, icon, description.
	 *
	 * @var (array)
	 */
	protected static $modules;

	/**
	 * The reference to *Singleton* instance of this class.
	 *
	 * @var (object)
	 */
	protected static $_instance;


	/** Setters ================================================================================= */

	/**
	 * Set the modules infos.
	 *
	 * @since 1.0
	 */
	final protected static function set_modules() {
		static::$modules = secupress_get_modules();
	}


	/**
	 * Set the current module.
	 *
	 * @since 1.0
	 *
	 * @return (object) The class instance.
	 */
	final protected function set_current_module() {
		$this->modulenow = isset( $_GET['module'] ) ? $_GET['module'] : 'welcome';
		$this->modulenow = array_key_exists( $this->modulenow, static::get_modules() ) && file_exists( SECUPRESS_MODULES_PATH . $this->modulenow . '/settings.php' ) ? $this->modulenow : 'welcome';
		return $this;
	}


	/** Getters ================================================================================= */

	/**
	 * Set the modules infos.
	 *
	 * @since 1.0
	 *
	 * @return (array) The modules.
	 */
	final public static function get_modules() {
		if ( empty( static::$modules ) ) {
			static::set_modules();
		}

		return static::$modules;
	}


	/**
	 * Get a module title.
	 *
	 * @since 1.0
	 *
	 * @param (string) $module The desired module.
	 *
	 * @return (string)
	*/
	final public function get_module_title( $module = false ) {
		$modules = static::get_modules();
		$module  = $module ? $module : $this->modulenow;

		if ( ! empty( $modules[ $module ]['title-alt'] ) ) {
			return $modules[ $module ]['title-alt'];
		}
		if ( ! empty( $modules[ $module ]['title'] ) ) {
			return $modules[ $module ]['title'];
		}

		return '';
	}


	/**
	 * Get a module descriptions.
	 *
	 * @since 1.0
	 *
	 * @param (string) $module The desired module.
	 *
	 * @return (array)
	*/
	final public function get_module_descriptions( $module = false ) {
		$modules = static::get_modules();
		$module  = $module ? $module : $this->modulenow;

		if ( ! empty( $modules[ $module ]['description'] ) ) {
			return (array) $modules[ $module ]['description'];
		}

		return array();
	}


	/**
	 * Get a module summary.
	 *
	 * @since 1.0
	 *
	 * @param (string) $module The desired module.
	 * @param (string) $size The desired size: small|normal.
	 *
	 * @return (string)
	*/
	final public function get_module_summary( $module = false, $size = 'normal' ) {
		$modules = static::get_modules();
		$module  = $module ? $module : $this->modulenow;

		if ( ! empty( $modules[ $module ]['summaries'][ $size ] ) ) {
			return $modules[ $module ]['summaries'][ $size ];
		}

		return '';
	}


	/**
	 * Get if a module is new
	 *
	 * @since 2.2.6
	 *
	 * @param (string) $module The desired module.
	 *
	 * @return (bool)
	*/
	final public function is_new_module( $module = false ) {
		$modules = static::get_modules();
		$module  = $module ? $module : $this->modulenow;

		return isset( $modules[ $module ]['new'] ) && $this->modulenow !== $module;
	}


	/**
	 * Get a module icon.
	 *
	 * @since 1.0
	 * @author Geoffrey
	 *
	 * @param (string) $module The desired module.
	 *
	 * @return (string)
	 */
	final public function get_module_icon( $module = false ) {
		$modules = static::get_modules();
		$module  = $module ? $module : $this->modulenow;

		if ( ! empty( $modules[ $module ]['icon'] ) ) {
			return $modules[ $module ]['icon'];
		}

		return '';
	}


	/**
	 * Tells if the reset box should be displayed for a specific module.
	 *
	 * @since 1.0
	 *
	 * @param (string) $module The desired module.
	 *
	 * @return (bool)
	*/
	final public function display_module_reset_box( $module = false ) {
		$modules = static::get_modules();
		$module  = $module ? $module : $this->modulenow;

		return isset( $modules[ $module ]['with_reset_box'] ) ? (bool) $modules[ $module ]['with_reset_box'] : true;
	}


	/** Init ==================================================================================== */

	/**
	 * Init: this method is required by the class `SecuPress_Singleton`.
	 *
	 * @since 1.0
	 */
	protected function _init() {
		parent::_init();

		$modules = static::get_modules();

		$this->with_form = ! ( isset( $modules[ $this->modulenow ]['with_form'] ) && false === $modules[ $this->modulenow ]['with_form'] );

		if ( secupress_is_pro() ) {
			require_once( SECUPRESS_PRO_ADMIN_PATH . 'settings.php' );
		}
	}


	/** Main template tags ====================================================================== */

	/**
	 * Print the page content.
	 *
	 * @since 1.0
	 */
	public function print_page() {
		$secupress_has_sideads = apply_filters( 'secupress.no_sidebar', true ) && apply_filters( 'secupress.no_sideads', true );
		?>
		<div class="wrap">

			<?php secupress_admin_heading( __( 'Modules', 'secupress' ) ); ?>
			<?php settings_errors(); ?>

			<div class="secupress-wrapper secupress-flex secupress-flex-top<?php echo ( $secupress_has_sideads ? ' secupress-has-sideads' : '' ) ?>">
				<div class="secupress-modules-sidebar">
					<div class="secupress-sidebar-header">
						<div class="secupress-flex">
							<div class="secupress-sh-logo">
								<?php echo secupress_get_logo(); ?>
							</div>
							<div class="secupress-sh-name">
								<p class="secupress-sh-title">
									<?php echo secupress_get_logo_word(); ?>
								</p>
							</div>
						</div>
					</div>

					<ul id="secupress-modules-navigation" class="secupress-modules-list-links">
						<?php $this->print_tabs(); ?>
					</ul>
				</div>
				<div class="secupress-tab-content secupress-tab-content-<?php echo $this->get_current_module(); ?>" id="secupress-tab-content">
					<?php $this->print_current_module(); ?>
				</div>

				<?php $this->print_sideads(); ?>

			</div>

		</div>
		<?php
	}


	/**
	 * Print the tabs to switch between modules.
	 *
	 * @since 1.0
	 */
	protected function print_tabs() {
		foreach ( static::get_modules() as $key => $module ) {
			$icon   = isset( $module['icon'] ) ? $module['icon'] : 'secupress-simple';
			$icon   = secupress_is_white_label() ? '' : $icon;
			$class  = $this->get_current_module() === $key ? 'active' : '';
			$class .= ! empty( $module['mark_as_pro'] ) ? ' secupress-pro-module' : '';
			$new    = $this->is_new_module( $key ) ? '<span class="secupress-new-module">' . _x( 'New!', 'module', 'secupress' ) . '</span>' : '';
			?>
			<li>
				<?php echo $new; ?>
				<a href="<?php echo esc_url( secupress_admin_url( 'modules', $key ) ); ?>" class="<?php echo $class; ?> module-<?php echo sanitize_key( $key ); ?>">
					<span class="secupress-tab-name"><?php echo $module['title']; ?></span>
					<span class="secupress-tab-summary">
					<?php
					if ( apply_filters( 'secupress.settings.description', true ) ) {
						echo $module['summaries']['small'];
					}
					?>
					</span>
					<i class="secupress-icon-<?php echo $icon; ?>" aria-hidden="true"></i>
				</a>
			</li>
			<?php
		}
		if ( ! secupress_is_pro() && ! secupress_is_white_label() ) {
			?>
			<li>
				<a href="<?php echo esc_url( secupress_admin_url( 'get-pro' ) ); ?>" class="module-get-pro">
					<span class="secupress-tab-name"><?php _e( 'Unlock all PRO features', 'secupress' ); ?></span>
					<span class="secupress-tab-summary"><?php _e( 'Buy SecuPress Pro now', 'secupress' ); ?></span>
					<i class="icon-secupress-simple" aria-hidden="true"></i>
				</a>
			</li>
			<?php
		}
	}


	/**
	 * Print the opening form tag.
	 *
	 * @since 1.0
	 */
	final public function print_open_form_tag() {
		?>
		<form id="secupress-module-form-settings" method="post" action="<?php echo $this->get_form_action(); ?>" enctype="multipart/form-data">
		<?php
	}


	/**
	 * Print the closing form tag and the hidden settings fields.
	 *
	 * @since 1.0
	 */
	final public function print_close_form_tag() {
		settings_fields( 'secupress_' . $this->get_current_module() . '_settings' );
		echo '</form>';
	}


	/**
	 * Print the current module.
	 *
	 * @since 1.0
	 */
	protected function print_current_module() {
		?>
		<div class="secupress-tab-content-header secupress-flex secupress-flex-spaced secupress-vcenter">
			<?php
			$this->print_module_title();
			?>
			<div class="secupress-module-search-wrapper hide-if-no-js">
				<label for="secupress-module-search" class="screen-reader-text"><?php _e( 'Search', 'secupress' ); ?></label>
				<span class="dashicons dashicons-search secupress-search-icon" aria-hidden="true"></span>
				<input type="search" id="secupress-module-search" name="secupress-module-search" class="secupress-module-search" placeholder="<?php esc_attr_e( 'Search...', 'secupress' ); ?>" />
				<span class="spinner secupress-inline-spinner" style="float: none; margin: 0;"></span>
				<ul id="secupress-module-search-results" class="secupress-module-search-results" style="display: none;"></ul>
			</div>
		</div>

		<?php
		if ( $this->get_with_form() ) {
			$this->print_open_form_tag();
		}
		?>

		<div class="secupress-module-options-block" id="block-advanced_options" data-module="<?php echo $this->get_current_module(); ?>">
			<?php
			$this->load_module_settings();
			$this->print_module_reset_box();
			?>
		</div>

		<?php
		if ( $this->get_with_form() ) {
			$this->print_close_form_tag();
		}
	}


	/**
	 * Print a box allowing to reset the current module settings.
	 *
	 * @since 1.0
	 */
	protected function print_module_reset_box() {
		if ( ! $this->display_module_reset_box() ) {
			return;
		}
		$this->set_current_section( 'reset' );
		$this->set_section_description( __( 'When you need to reset this module’s settings to the default.', 'secupress' ) );

		$this->set_current_plugin( 'reset' );

		$this->add_field( array(
			'name'       => 'reset',
			'field_type' => 'field_button',
			'style'      => 'small',
			'class'      => 'secupress-button-secondary',
			'url'        => wp_nonce_url( admin_url( 'admin-post.php?action=secupress_reset_settings&module=' . $this->get_current_module() ), 'secupress_reset_' . $this->get_current_module() ),
			'label'      => sprintf( __( 'Reset the %s’s settings', 'secupress' ), $this->get_module_title() ),
		) );

		$this->do_sections();
	}


	/**
	 * Print the module title.
	 *
	 * @since 1.0
	 *
	 * @param (string) $tag The title tag to use.
	 *
	 * @return (object) The class instance.
	 */
	protected function print_module_title( $tag = 'h2' ) {
		echo "<$tag class=\"secupress-tc-title\">";
			$this->print_module_icon();
			echo $this->get_module_title();
		echo "</$tag>\n";
		return $this;
	}


	/**
	 * Print the module descriptions.
	 *
	 * @since 1.0
	 *
	 * @return (object) The class instance.
	 */
	protected function print_module_description() {
		if ( $this->get_module_descriptions() ) {
			echo '<p>' . implode( "</p>\n<p>", $this->get_module_descriptions() ) . "</p>\n";
		}
		return $this;
	}


	/**
	 * Print the module icon.
	 *
	 * @since 1.0
	 * @author Geoffrey
	 *
	 * @return (object) The class instance.
	 */
	protected function print_module_icon() {
		if ( $this->get_module_icon() ) {
			echo '<i class="secupress-icon-' . $this->get_module_icon() . '" aria-hidden="true"></i>' . "\n";
		}
		return $this;
	}


	/** Specific fields ========================================================================= */

	/**
	 * Non login time slot field.
	 * The field is hidden in the free version.
	 *
	 * @since 1.0
	 *
	 * @param (array) $args An array of parameters. See `::field()`.
	 */
	protected function countries( $args ) {}


	/**
	 * Non login time slot field.
	 * The field is hidden in the free version.
	 *
	 * @since 1.0
	 *
	 * @param (array) $args An array of parameters. See `::field()`.
	 */
	protected function non_login_time_slot( $args ) {}


	/**
	 * Displays the scheduled backups.
	 *
	 * @since 1.0
	 */
	protected function scheduled_backups() {
		echo '<a href="' . esc_url( secupress_admin_url( 'get-pro' ) ) . '" class="secupress-button secupress-ghost secupress-button-tertiary">' . __( 'Learn more about SecuPress Pro', 'secupress' ) . '</a>';
			_e( 'This feature is available in SecuPress Pro', 'secupress' );
	}


	/**
	 * Displays the scheduled scan.
	 *
	 * @since 1.0
	 */
	protected function scheduled_scan() {
		echo '<a href="' . esc_url( secupress_admin_url( 'get-pro' ) ) . '" class="secupress-button secupress-ghost secupress-button-tertiary">' . __( 'Learn more about SecuPress Pro', 'secupress' ) . '</a>';
			_e( 'This feature is available in SecuPress Pro', 'secupress' );
	}


	/**
	 * Displays the scheduled file monitoring.
	 *
	 * @since 1.0
	 */
	protected function scheduled_monitoring() {
		echo '<a href="' . esc_url( secupress_admin_url( 'get-pro' ) ) . '" class="secupress-button secupress-ghost secupress-button-tertiary">' . __( 'Learn more about SecuPress Pro', 'secupress' ) . '</a>';
			_e( 'This feature is available in SecuPress Pro', 'secupress' );
	}


	/**
	 * Displays the banned IPs and add actions to delete them or add new ones.
	 *
	 * @since 1.0
	 */
	protected function blacklist_ips() {
		$ban_ips            = get_site_option( SECUPRESS_BAN_IP );
		$ban_ips            = is_array( $ban_ips ) ? $ban_ips : [];
		$offset             = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
		$page_url           = secupress_admin_url( 'modules', 'logs' );
		$referer_arg        = '&_wp_http_referer=' . urlencode( esc_url_raw( $page_url ) );
		$is_search          = false;
		$search_val         = '';
		$empty_list_message = __( 'Empty disallowed IP list', 'secupress' );

		// Ban form.
		echo '<form id="form-ban-ip" class="hide-if-js" action="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=secupress-ban-ip' . $referer_arg ), 'secupress-ban-ip' ) ) . '" method="post">';
			echo '<label for="secupress-ban-ip" class="screen-reader-text">' . __( 'Specify an IP to ban.', 'secupress' ) . '</label><br/>';
			echo '<p class="description">' . sprintf( __( 'You can use %sIP ranges%s.', 'secupress' ), __( '<a href="https://docs.secupress.me/article/161-ip-range">', 'secupress' ), '</a>' ) . '</p>';
			echo '<textarea cols="50" id="secupress-ban-ip" name="ip"></textarea> ';
			echo '<button type="submit" class="secupress-button secupress-button-mini">' . _x( 'Ban IP', 'verb', 'secupress' ) . '</button>';
		echo "</form>\n";

		// Search.
		if ( $ban_ips && ! empty( $_POST['secupress-search-banned-ip'] ) ) { // WPCS: CSRF ok.
			$search_val = urldecode( trim( $_POST['secupress-search-banned-ip'] ) ); // WPCS: CSRF ok.
			$is_search  = true;
			$search_val = preg_quote( $search_val, '~' );
			$found_ips  = preg_grep('~' . $search_val . '~', array_keys( $ban_ips ) );
			$found_ips  = array_flip( $found_ips );
			$ban_ips    = array_intersect_key( $ban_ips, $found_ips );

			if ( empty( $ban_ips ) ) {
				$empty_list_message = __( 'IP not found.', 'secupress' );
			}
		}

		// Search form.
		echo '<form action="' . esc_url_raw( secupress_get_current_url('raw') ) . '" id="form-search-ip"' . ( $ban_ips || $is_search ? '' : ' class="hidden"' ) . ' method="post">';
			echo '<label for="secupress-search-banned-ip" class="screen-reader-text">' . __( 'Search IP', 'secupress' ) . '</label><br/>';
			echo '<input type="search" id="secupress-search-banned-ip" name="secupress-search-banned-ip" value="' . esc_attr( wp_unslash( $search_val ) ) . '"/> ';
			echo '<button type="submit" class="secupress-button secupress-button-primary" data-loading-i18n="' . esc_attr__( 'Searching&hellip;', 'secupress' ) . '" data-original-i18n="' . esc_attr__( 'Search IP', 'secupress' ) . '">' . __( 'Search IP', 'secupress' ) . '</button> ';
			echo '<span class="spinner secupress-inline-spinner hide-if-no-js"></span>';
			echo '<a class="secupress-button secupress-button-secondary' . ( $search_val ? '' : ' hidden' ) . '" href="' . esc_url( $page_url ) . '" ">' . __( 'Cancel search', 'secupress' ) . '</a> ';
		echo "</form>\n";

		// Slice the list a bit: limit last results.
		/**
		* How many IP max to display
		*
		* @param (int) $limit 50 by default
		*/
		$limit     = apply_filters( 'secupress.ip_list.limit_max', 50 );
		$count_ips = count( $ban_ips );
		if ( $count_ips > $limit ) {
			$ban_ips = array_slice( $ban_ips, - $limit );
			echo '<p>' . sprintf( __( 'Last %1$s/%2$s disallowed IPs:', 'secupress' ), number_format_i18n( $limit ), $count_ips ) . '</p>' . "\n";
		}

		// Display the list.
		echo '<ul id="secupress-banned-ips-list" class="secupress-boxed-group">';
		if ( $ban_ips ) {
			foreach ( $ban_ips as $ip => $time ) {
				echo '<li class="secupress-large-row" data-ip="' . esc_attr( $ip ) . '">';
					$format = __( 'M jS Y', 'secupress' ) . ' ' . __( 'G:i', 'secupress' );
					$time   = date_i18n( $format, $time + $offset );
					$href   = wp_nonce_url( admin_url( 'admin-post.php?action=secupress-unban-ip&ip=' . esc_attr( $ip ) . $referer_arg ), 'secupress-unban-ip_' . $ip );

					printf( __( '<strong>%s</strong> <em>(Banned until %s)</em>', 'secupress' ), esc_html( $ip ), $time );
					printf( '<span><a class="a-unban-ip" href="%s">%s</a> <span class="spinner secupress-inline-spinner hide-if-no-js"></span></span>', esc_url( $href ), _x( 'Remove', 'verb', 'secupress' ) );
				echo "</li>\n";
			}
			if ( $count_ips > $limit ) {
				echo '<li>' . __( 'Do a search to find more.', 'secupress' ) . '</li>';
			}
			unset( $count_ips );
		} else {
			echo '<li id="no-ips">' . $empty_list_message . '</li>';
		}
		echo "</ul>\n";

		// Actions.
		echo '<p id="secupress-banned-ips-actions">';
			// Display a button to unban all IPs.
			$clear_href = wp_nonce_url( admin_url( 'admin-post.php?action=secupress-clear-ips' . $referer_arg ), 'secupress-clear-ips' );
			echo '<a class="secupress-button secupress-button-secondary' . ( $ban_ips || $is_search ? '' : ' hidden' ) . '" id="secupress-clear-ips-button" href="' . esc_url( $clear_href ) . '" data-loading-i18n="' . esc_attr__( 'Clearing&hellip;', 'secupress' ) . '" data-original-i18n="' . esc_attr__( 'Clear all IPs', 'secupress' ) . '">' . __( 'Clear all IPs', 'secupress' ) . "</a>\n";
			echo '<span class="spinner secupress-inline-spinner' . ( $ban_ips || $is_search ? ' hide-if-no-js' : ' hidden' ) . '"></span>';
			// For JS: ban a IP.
			echo '<button type="button" class="secupress-button secupress-button-primary hide-if-no-js" id="secupress-ban-ip-button" data-loading-i18n="' . esc_attr__( 'Ban in progress&hellip;', 'secupress' ) . '" data-original-i18n="' . esc_attr_x( 'Disallow', 'verb', 'secupress' ) . '">' . _x( 'Disallow', 'verb', 'secupress' ) . "</button>\n";
			echo '<span class="spinner secupress-inline-spinner hide-if-no-js"></span>';
		echo "</p>\n";
	}


	/**
	 * Displays the textarea that lists the IP addresses not to ban.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 *
	 * @param (array) $args An array of parameters. See `::field()`.
	 */
	protected function whitelist_ips( $args ) {
		$ban_ips            = get_site_option( SECUPRESS_WHITE_IP );
		$ban_ips            = is_array( $ban_ips ) ? $ban_ips : [];
		$offset             = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
		$page_url           = secupress_admin_url( 'modules', 'logs' );
		$referer_arg        = '&_wp_http_referer=' . urlencode( esc_url_raw( $page_url ) );
		$is_search          = false;
		$search_val         = '';
		$empty_list_message = __( 'Empty allowed IP list', 'secupress' );

		// Ban form.
		echo '<form id="form-whitelist-ip" class="hide-if-js" action="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=secupress-whitelist-ip' . $referer_arg ), 'secupress-whitelist-ip' ) ) . '" method="post">';
			echo '<label for="secupress-whitelist-ip" class="screen-reader-text">' . __( 'Specify an IP to add to the allowed list.', 'secupress' ) . '</label><br/>';
			echo '<p class="description">' . __( 'You can use <a href="https://docs.secupress.me/article/161-ip-range">IP ranges</a>.', 'secupress' ) . '</p>';
			echo '<textarea cols="50" id="secupress-whitelist-ip" name="ip"></textarea> ';
			echo '<button type="submit" class="secupress-button secupress-button-mini">' . _x( 'Allow IP', 'verb', 'secupress' ) . '</button>';
		echo "</form>\n";

		// Search.
		if ( $ban_ips && ! empty( $_POST['secupress-search-whitelist-ip'] ) ) { // WPCS: CSRF ok.
			$search_val = urldecode( trim( $_POST['secupress-search-whitelist-ip'] ) ); // WPCS: CSRF ok.
			$is_search  = true;
			$search_val = preg_quote( $search_val, '~' );
			$found_ips  = preg_grep('~' . $search_val . '~', array_keys( $ban_ips ) );
			$found_ips  = array_flip( $found_ips );
			$ban_ips    = array_intersect_key( $ban_ips, $found_ips );

			if ( empty( $ban_ips ) ) {
				$empty_list_message = __( 'IP not found.', 'secupress' );
			}
		}

		// Search form.
		echo '<form action="' . esc_url_raw( $page_url ) . '" id="form-search-whitelist-ip"' . ( $ban_ips || $is_search ? '' : ' class="hidden"' ) . ' method="post">';
			echo '<label for="secupress-search-whitelist-ip" class="screen-reader-text">' . __( 'Search IP', 'secupress' ) . '</label><br/>';
			echo '<input type="search" id="secupress-search-whitelist-ip" name="secupress-search-whitelist-ip" value="' . esc_attr( wp_unslash( $search_val ) ) . '"/> ';
			echo '<button type="submit" class="secupress-button secupress-button-primary" data-loading-i18n="' . esc_attr__( 'Searching&hellip;', 'secupress' ) . '" data-original-i18n="' . esc_attr__( 'Search IP', 'secupress' ) . '">' . __( 'Search IP', 'secupress' ) . '</button> ';
			echo '<span class="spinner secupress-inline-spinner hide-if-no-js"></span>';
			echo '<a class="secupress-button secupress-button-secondary' . ( $search_val ? '' : ' hidden' ) . '" href="' . esc_url( $page_url ) . '" data-loading-i18n="' . esc_attr__( 'Reset in progress&hellip;', 'secupress' ) . '" data-original-i18n="' . esc_attr__( 'Reset', 'secupress' ) . '">' . __( 'Cancel search', 'secupress' ) . '</a> ';
		echo "</form>\n";

		// Slice the list a bit: limit last results.
		/**
		* How many IP max to display
		*
		* @param (int) $limit 50 by default
		*/
		$limit     = apply_filters( 'secupress.ip_list.limit_max', 50 );
		$count_ips = count( $ban_ips );
		if ( $count_ips > $limit ) {
			$ban_ips = array_slice( $ban_ips, - $limit );
			echo '<p>' . sprintf( __( 'Last %1$s/%2$s allowed IPs:', 'secupress' ), number_format_i18n( $limit ), $count_ips ) . '</p>' . "\n";
		}

		// Display the list.
		echo '<ul id="secupress-whitelist-ips-list" class="secupress-boxed-group">';
		if ( $ban_ips ) {
			foreach ( $ban_ips as $ip => $time ) {
				echo '<li class="secupress-large-row" data-ip="' . esc_attr( $ip ) . '">';
					$href   = wp_nonce_url( admin_url( 'admin-post.php?action=secupress-unwhitelist-ip&ip=' . esc_attr( $ip ) . $referer_arg ), 'secupress-unwhitelist-ip_' . $ip );

					printf( '<strong>%s</strong>', esc_html( $ip ) );
					printf( '<span><a class="a-unwhitelist-ip" href="%s">%s</a> <span class="spinner secupress-inline-spinner hide-if-no-js"></span></span>', esc_url( $href ), __( 'Remove', 'secupress' ) );
				echo "</li>\n";
			}
			if ( $count_ips > $limit ) {
				echo '<li>' . __( 'Do a search to find more.', 'secupress' ) . '</li>';
			}
			unset( $count_ips );
		} else {
			echo '<li id="no-whitelist-ips">' . $empty_list_message . '</li>';
		}
		echo "</ul>\n";

		// Actions.
		echo '<p id="secupress-whitelist-ips-actions">';
			// Display a button to unban all IPs.
			$clear_href = wp_nonce_url( admin_url( 'admin-post.php?action=secupress-clear-whitelist-ips' . $referer_arg ), 'secupress-clear-whitelist-ips' );
			echo '<a class="secupress-button secupress-button-secondary' . ( $ban_ips || $is_search ? '' : ' hidden' ) . '" id="secupress-clear-whitelist-ips-button" href="' . esc_url( $clear_href ) . '" data-loading-i18n="' . esc_attr__( 'Clearing&hellip;', 'secupress' ) . '" data-original-i18n="' . esc_attr__( 'Clear all IPs', 'secupress' ) . '">' . __( 'Clear all IPs', 'secupress' ) . "</a>\n";
			echo '<span class="spinner secupress-inline-spinner' . ( $ban_ips || $is_search ? ' hide-if-no-js' : ' hidden' ) . '"></span>';
			// For JS: ban a IP.
			echo '<button type="button" class="secupress-button secupress-button-primary hide-if-no-js" id="secupress-whitelist-ip-button" data-loading-i18n="' . esc_attr__( 'Adding to allowed list&hellip;', 'secupress' ) . '" data-original-i18n="' . esc_attr_x( 'Allow', 'verb', 'secupress' ) . '">' . _x( 'Allow', 'verb', 'secupress' ) . "</button>\n";
			echo '<span class="spinner secupress-inline-spinner hide-if-no-js"></span>';
		echo "</p>\n";
	}

	/**
	 * Displays Firewall Learning Mode controls and the signature review list.
	 *
	 * @since 2.7.1
	 * @author Julio Potier
	 *
	 * @param (array) $args An array of parameters. See `::field()`.
	 */
	protected function learning_mode( $args ) {
		unset( $args );
		$state        = secupress_firewall_learning_get_state();
		$status       = $state['status'];
		$ng_ready     = secupress_firewall_ng_is_enabled();
		$last_started = secupress_firewall_learning_get_last_started();
		$page_url     = secupress_admin_url( 'modules', 'firewall' );
		if ( secupress_firewall_learning_bypass_sample() ) {
			$page_url = add_query_arg( 'expertmode', '1', $page_url );
		}
		$referer      = '&_wp_http_referer=' . urlencode( esc_url_raw( $page_url . '#row-learning-mode_learning' ) );
		$admin_post   = admin_url( 'admin-post.php' );
		$options      = secupress_firewall_learning_duration_options();
		$hits         = secupress_firewall_learning_get_hits();
		$progress     = secupress_firewall_learning_progress( $state );
		$advice_ready = ! empty( $progress['ready'] );
		$pending = [];

		foreach ( $hits as $hash => $hit ) {
			if ( empty( $hit['decision'] ) || 'pending' === $hit['decision'] ) {
				$pending[ $hash ] = $hit;
			}
		}

		if ( $last_started ) {
			echo '<input type="hidden" name="secupress_firewall_settings[learning-mode_last]" value="' . (int) $last_started . '" />';
		}

		if ( ! $ng_ready && 'running' !== $status && 'review' !== $status ) {
			echo '<p class="secupress-warning">' . sprintf( __( 'Please, save the %s rules to activate the learning mode.', 'secupress' ), secupress_firewall_ng_name() ) . '</p>';
		} elseif ( 'running' === $status ) {
			if ( $advice_ready ) {
				$until   = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $state['ends'] );
				$started = (int) $state['started'];
				$ends    = (int) $state['ends'];
				$now     = time();
				$total   = max( 1, $ends - $started );
				$remain  = max( 0, $ends - $now );
				$pct     = min( 100, max( 0, ( $remain / $total ) * 100 ) );
				echo '<p><strong>' . sprintf(
					/* translators: %s: date */
					__( 'Learning Mode is running until %s.', 'secupress' ),
					esc_html( $until )
				) . '</strong></p>';
				echo '<div class="secupress-learning-countdown" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . (int) round( $pct ) . '">';
				echo '<span class="secupress-learning-countdown-bar" style="--secupress-learning-remain:' . esc_attr( $pct ) . '%;--secupress-learning-duration:' . (int) $remain . 's"></span>';
				echo '</div>';
			}
			if ( ! $hits ) {
				echo '<p class="description">' . esc_html( sprintf(
					/* translators: %s: firewall pack name (e.g. 8G) */
					__( 'No calibrable %s signature has matched yet.', 'secupress' ),
					secupress_firewall_ng_name()
				) ) . '</p>';
			}

			$finish = wp_nonce_url( $admin_post . '?action=secupress-learning-finish' . $referer, 'secupress-learning-finish' );
			$skip   = wp_nonce_url( $admin_post . '?action=secupress-learning-skip' . $referer, 'secupress-learning-skip' );
			$extend = wp_nonce_url( $admin_post . '?action=secupress-learning-extend&duration=3' . $referer, 'secupress-learning-extend' );

			echo '<div class="secupress-learning-manage">';
			echo '<p class="secupress-learning-manage-label">' . esc_html__( 'Manage learning mode status', 'secupress' ) . '</p>';
			echo '<div class="secupress-learning-manage-row">';
			echo '<div class="secupress-learning-manage-extend">';
			echo '<label class="screen-reader-text" for="secupress-learning-extend-duration">' . esc_html__( 'Extend by', 'secupress' ) . '</label>';
			echo '<select id="secupress-learning-extend-duration">';
			foreach ( $options as $days => $label ) {
				echo '<option value="' . (int) $days . '"' . selected( $days, 3, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select>';
			echo '<a class="secupress-learning-manage-btn secupress-learning-manage-extend-btn" id="secupress-learning-extend-btn" href="' . esc_url( $extend ) . '">';
			echo '<span class="dashicons dashicons-plus" aria-hidden="true"></span>';
			echo esc_html__( 'Extend', 'secupress' );
			echo '</a>';
			echo '</div>';
			echo '<div class="secupress-learning-manage-actions">';
			echo '<a class="secupress-learning-manage-btn secupress-learning-manage-finish" href="' . esc_url( $finish ) . '">';
			echo '<span class="dashicons dashicons-flag" aria-hidden="true"></span>';
			echo esc_html__( 'Finish now', 'secupress' );
			echo '</a>';
			echo '<a class="secupress-learning-manage-btn secupress-learning-manage-block" href="' . esc_url( $skip ) . '">';
			echo '<span class="dashicons dashicons-dismiss" aria-hidden="true"></span>';
			echo esc_html__( 'Skip & block everything', 'secupress' );
			echo '</a>';
			echo '</div>';
			echo '</div>';
			echo '</div>';
		} else {
			if ( 'review' === $status ) {
				echo '<p><strong>' . esc_html__( 'Learning Mode has ended. Review remaining signatures, then they will be blocked.', 'secupress' ) . '</strong></p>';
			}

			$start_default = 5;
			$start_href    = wp_nonce_url( $admin_post . '?action=secupress-learning-start&duration=' . $start_default . '&trigger=manual' . $referer, 'secupress-learning-start' );

			if ( $last_started ) {
				echo '<p class="description">' . sprintf(
					/* translators: %s: date */
					esc_html__( 'Last Learning Mode: %s', 'secupress' ),
					esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_started ) )
				) . '</p>';
			}

			echo '<p>';
			echo '<label for="secupress-learning-duration">' . esc_html__( 'Duration', 'secupress' ) . '</label> ';
			echo '<select id="secupress-learning-duration">';
			foreach ( $options as $days => $label ) {
				echo '<option value="' . (int) $days . '"' . selected( $days, $start_default, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select> ';
			echo '<a class="secupress-button secupress-button-mini" id="secupress-learning-start-btn" href="' . esc_url( $start_href ) . '">' . esc_html__( 'Start Learning Mode', 'secupress' ) . '</a>';
			echo '</p>';
		}

		$show_review     = $pending && ( ( 'running' === $status && $advice_ready ) || 'review' === $status );
		$show_head       = ( 'running' === $status && ! $advice_ready ) || $show_review;
		$show_review_box = $show_head;

		if ( $show_review_box ) {
			echo '<div class="secupress-learning-review">';
		}

		if ( $show_head ) {
			$pending_count = count( $pending );
			$pending_label = sprintf(
				/* translators: %s: number of signatures */
				_n( '%s signature to review.', '%s signatures to review.', $pending_count, 'secupress' ),
				number_format_i18n( $pending_count )
			);
			echo '<h4 class="secupress-learning-review-title">' . esc_html__( 'Signatures to review', 'secupress' );
			if ( $pending_count ) {
				echo ' <span class="secupress-dot-bad secupress-learning-observed" title="' . esc_attr( $pending_label ) . '">';
				echo esc_html( number_format_i18n( $pending_count ) );
				echo '<span class="screen-reader-text">' . esc_html( $pending_label ) . '</span>';
				echo '</span>';
			}
			echo '</h4>';
		}

		if ( 'running' === $status && ! $advice_ready ) {
			$reflect = (float) $progress['percent'];
			echo '<div class="secupress-learning-reflect" id="secupress-learning-reflect" data-nonce="' . esc_attr( wp_create_nonce( 'secupress-learning-progress' ) ) . '" data-ajax="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '">';
			echo '<p class="description secupress-learning-reflect-message">' . secupress_firewall_learning_wait_message() . '</p>';
			echo '<p class="secupress-learning-reflect-label">';
			echo '<span class="secupress-learning-reflect-spinner" aria-hidden="true"></span> ';
			echo '<span id="secupress-learning-reflect-percent">' . esc_html( number_format_i18n( $reflect, 1 ) . '%' ) . '</span>';
			echo '</p>';
			echo '<div class="secupress-learning-reflect-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr( $reflect ) . '">';
			echo '<span class="secupress-learning-reflect-bar" id="secupress-learning-reflect-bar" style="width:' . esc_attr( $reflect ) . '%"></span>';
			echo '</div>';
			echo '</div>';
		} elseif ( $show_review ) {
			$this->learning_mode_hits_table( $pending, $referer, true );
		} elseif ( 'review' === $status && ! $hits ) {
			echo '<p class="description">' . esc_html( sprintf(
				/* translators: %s: firewall pack name (e.g. 8G) */
				__( 'No calibrable %s signature has matched yet.', 'secupress' ),
				secupress_firewall_ng_name()
			) ) . '</p>';
		}

		if ( $show_review_box ) {
			echo '</div>';
		}

		$start_base  = str_replace( '&amp;', '&', wp_nonce_url( $admin_post . '?action=secupress-learning-start&duration=__DAYS__&trigger=manual' . $referer, 'secupress-learning-start' ) );
		$extend_base = str_replace( '&amp;', '&', wp_nonce_url( $admin_post . '?action=secupress-learning-extend&duration=__DAYS__' . $referer, 'secupress-learning-extend' ) );
		?>
		<script>
		(function() {
			var startSelect = document.getElementById('secupress-learning-duration');
			var startBtn    = document.getElementById('secupress-learning-start-btn');
			var startTpl    = <?php echo wp_json_encode( $start_base ); ?>;
			if ( startSelect && startBtn ) {
				var applyStart = function() {
					startBtn.href = startTpl.replace('__DAYS__', encodeURIComponent(startSelect.value));
				};
				startSelect.addEventListener('change', applyStart);
				applyStart();
			}
			var extendSelect = document.getElementById('secupress-learning-extend-duration');
			var extendBtn    = document.getElementById('secupress-learning-extend-btn');
			var extendTpl    = <?php echo wp_json_encode( $extend_base ); ?>;
			if ( extendSelect && extendBtn ) {
				var applyExtend = function() {
					extendBtn.href = extendTpl.replace('__DAYS__', encodeURIComponent(extendSelect.value));
				};
				extendSelect.addEventListener('change', applyExtend);
				applyExtend();
			}
			var reflect = document.getElementById('secupress-learning-reflect');
			if ( reflect ) {
				var reflectTimer = window.setInterval(function() {
					var body = new window.FormData();
					body.append('action', 'secupress-learning-progress');
					body.append('_ajax_nonce', reflect.getAttribute('data-nonce'));
					window.fetch(reflect.getAttribute('data-ajax'), {
						method: 'POST',
						credentials: 'same-origin',
						body: body
					}).then(function(response) {
						return response.json();
					}).then(function(payload) {
						if ( ! payload || ! payload.success || ! payload.data ) {
							return;
						}
						if ( payload.data.ready ) {
							window.clearInterval(reflectTimer);
							window.location.reload();
							return;
						}
						var description = reflect.querySelector('.secupress-learning-reflect-message');
						if ( description && payload.data.description ) {
							description.innerHTML = payload.data.description;
						}
						var percent = parseFloat(payload.data.percent);
						if ( isNaN(percent) ) {
							return;
						}
						percent = Math.round(percent * 10) / 10;
						var pretty = percent.toLocaleString(document.documentElement.lang || undefined, {
							minimumFractionDigits: 1,
							maximumFractionDigits: 1
						});
						var label = document.getElementById('secupress-learning-reflect-percent');
						var bar = document.getElementById('secupress-learning-reflect-bar');
						var track = reflect.querySelector('[role="progressbar"]');
						if ( label ) {
							label.textContent = pretty + '%';
						}
						if ( bar ) {
							bar.style.width = percent + '%';
						}
						if ( track ) {
							track.setAttribute('aria-valuenow', String(percent));
						}
					}).catch(function() {});
				}, 31000);
			}
		})();
		(function() {
			var row = document.querySelector('.secupress-setting-row_learning-mode_learning');
			if ( row ) {
				row.style.display = 'none';
			}
		})();
		jQuery(function($) {
			var $row = $('.secupress-setting-row_learning-mode_learning');
			var $ng = $('#learning-mode_8g');
			var $modules = $('#learning-mode_bbx_user-agents-header, #learning-mode_bbx_bad-url-contents, #learning-mode_bbx_bad-referer');
			function canShowLearningRow() {
				return $ng.length && $ng.is(':checked') && $modules.filter(':checked').length;
			}
			function syncLearningRow( instant ) {
				if ( ! $row.length ) {
					return;
				}
				if ( canShowLearningRow() ) {
					$row.show( instant ? 0 : 'fast' );
				} else {
					$row.hide( instant ? 0 : 'fast' );
				}
			}
			$ng.add($modules).on('change', function() {
				syncLearningRow( false );
			});
			$row.on('secupressaftershow secupressinitshow', function() {
				if ( ! canShowLearningRow() ) {
					$row.hide();
				}
			});
			syncLearningRow( true );
		});
		</script>
		<?php
	}


	/**
	 * Learning Mode signatures as a compact list table.
	 *
	 * @since 2.7.1
	 * @author Julio Potier
	 *
	 * @param (array)  $hits
	 * @param (string) $referer
	 * @param (bool)   $with_actions
	 */
	protected function learning_mode_hits_table( $hits, $referer, $with_actions ) {
		echo '<table class="wp-list-table widefat fixed striped secupress-learning-table">';
		echo '<thead><tr>';
		echo '<th scope="col" class="column-primary column-string">' . esc_html__( 'String', 'secupress' ) . '</th>';
		echo '<th scope="col" class="column-source">' . esc_html__( 'Source', 'secupress' ) . '</th>';
		$advice_heading = $with_actions ? __( 'Suggested action', 'secupress' ) : __( 'Decision', 'secupress' );
		echo '<th scope="col" class="column-advice">' . esc_html( $advice_heading ) . '</th>';
		echo '<th scope="col" class="column-values">' . esc_html__( 'Values', 'secupress' ) . '</th>';
		echo '<th scope="col" class="column-hits">' . esc_html__( 'Hits', 'secupress' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $hits as $hash => $hit ) {
			$this->learning_mode_hit_row( $hash, $hit, $referer, $with_actions );
		}
		echo '</tbody></table>';
	}


	/**
	 * One Learning Mode signature row.
	 *
	 * @since 2.7.1
	 * @author Julio Potier
	 *
	 * @param (string) $hash
	 * @param (array)  $hit
	 * @param (string) $referer
	 * @param (bool)   $with_actions
	 */
	protected function learning_mode_hit_row( $hash, $hit, $referer, $with_actions ) {
		$admin_post  = admin_url( 'admin-post.php' );
		$label       = secupress_firewall_learning_slug_label( isset( $hit['slug'] ) ? $hit['slug'] : '' );
		$match       = isset( $hit['match'] ) ? (string) $hit['match'] : '';
		$pattern     = isset( $hit['pattern'] ) ? (string) $hit['pattern'] : '';
		$hits_n      = isset( $hit['hits'] ) ? (int) $hit['hits'] : 0;
		$subjects    = isset( $hit['subjects'] ) ? array_reverse( array_values( (array) $hit['subjects'] ) ) : [];
		$advice      = $with_actions ? secupress_firewall_learning_recommend( $hit ) : [];

		echo '<tr>';
		echo '<td class="column-string column-primary has-row-actions">';
		if ( '' !== $match ) {
			echo '<div class="secupress-learning-keyword">';
			echo '<span class="secupress-learning-field-label">' . esc_html__( 'Keyword:', 'secupress' ) . '</span> ';
			echo '<strong><code class="secupress-learning-match">' . esc_html( $match ) . '</code></strong>';
			echo '</div>';
		}
		if ( '' !== $pattern && $pattern !== $match ) {
			echo '<div class="secupress-learning-pattern">';
			echo '<span class="secupress-learning-field-label">' . esc_html__( 'Rule:', 'secupress' ) . '</span> ';
			echo '<code>' . esc_html( $pattern ) . '</code>';
			echo '</div>';
		}
		if ( $with_actions ) {
			$allow_site = wp_nonce_url( $admin_post . '?action=secupress-learning-allow-site&hash=' . rawurlencode( $hash ) . $referer, 'secupress-learning-allow-site_' . $hash );
			$keep       = wp_nonce_url( $admin_post . '?action=secupress-learning-keep-block&hash=' . rawurlencode( $hash ) . $referer, 'secupress-learning-keep-block_' . $hash );
			echo '<div class="row-actions">';
			echo '<span class="allow"><a href="' . esc_url( $allow_site ) . '">' . esc_html__( 'Allow', 'secupress' ) . '</a> | </span>';
			echo '<span class="block"><a class="secupress-learning-keep" href="' . esc_url( $keep ) . '">' . esc_html__( 'Keep blocking', 'secupress' ) . '</a></span>';
			echo '</div>';
		}
		echo '</td>';
		echo '<td class="column-source">' . esc_html( $label ) . '</td>';
		echo '<td class="column-advice">';
		if ( $with_actions && ! empty( $advice['action'] ) ) {
			$status_label = 'allow' === $advice['action'] ? __( 'Allow', 'secupress' ) : __( 'Block', 'secupress' );
			echo '<span class="secupress-learning-status secupress-learning-advice-' . esc_attr( $advice['action'] ) . '">' . esc_html( $status_label ) . '</span>';
			if ( ! empty( $advice['reason'] ) ) {
				echo '<div class="secupress-learning-reason">' . esc_html( $advice['reason'] ) . '</div>';
			}
		} elseif ( ! $with_actions ) {
			$decision = isset( $hit['decision'] ) ? $hit['decision'] : '';
			$labels   = [
				'allow_site'    => _x( 'Allowed for this site', 'learning mode keyword detected', 'secupress' ),
				'allow_uri'     => _x( 'Allowed for a URL', 'learning mode keyword detected', 'secupress' ),
				'force_enforce' => _x( 'Kept blocked', 'learning mode keyword detected', 'secupress' ),
				'auto_allowed'  => _x( 'Auto-allowed for a URL', 'learning mode keyword detected', 'secupress' ),
			];
			echo isset( $labels[ $decision ] ) ? esc_html( $labels[ $decision ] ) : '—';
		} else {
			echo '—';
		}
		echo '</td>';
		echo '<td class="column-values">';
		if ( $subjects ) {
			echo '<span class="secupress-learning-match-wrap" tabindex="0">';
			echo '<span class="dashicons dashicons-editor-help" aria-hidden="true"></span>';
			echo '<span class="screen-reader-text">' . esc_html__( 'Blocked values', 'secupress' ) . '</span>';
			echo '<span class="secupress-learning-subjects" role="tooltip">';
			echo '<span class="secupress-learning-subjects-inner">';
			echo '<span class="secupress-learning-subjects-item">' . esc_html__( '10 last blocked values:', 'secupress' ) . '</span>';
			foreach ( $subjects as $subject ) {
				echo '<span class="secupress-learning-subjects-item">' . esc_html( $subject ) . '</span>';
			}
			echo '</span></span></span>';
		} else {
			echo '—';
		}
		echo '</td>';
		echo '<td class="column-hits">' . esc_html( number_format_i18n( $hits_n ) ) . '</td>';
		echo "</tr>\n";
	}


	/**
	 * Displays the restrictions for HTTP Log
	 *
	 * @since 2.1
	 */
	protected function http_logs_restrictions() {
		$http_log_settings = get_option( SECUPRESS_HTTP_LOGS );
		if ( ! $http_log_settings ) {
			return;
		}
		?>
		<style>

		.secupress-http-settings th.column-hits {
			width: 45px;
		}
		.secupress-http-settings .since {
			color:#50575e;
		}
		.secupress-http-settings .square-box {
			display: flex;
			align-items: center;
			justify-content: center;
			box-sizing: content-box;
		}
		.secupress-http-settings .square-box-external {
			width: 32px;
			height: 32px;
			border-radius: 5px;
		}
		.secupress-http-settings .square-box-internal {
			width: 16px;
			height: 16px;
			border-radius: 50%;
			border: 1.5px solid #fff;
		}
		.secupress-http-settings .square-box-percent {
			width: 13px;
			height: 13px;
			border-radius: 50%;
			box-sizing: border-box;
		}


		</style>
		<h2>Stuff</h2>
		<p class="description">Stuff Stuff Stuff Stuff</p>

		<table class="secupress-http-settings wp-list-table widefat fixed striped posts">
			<thead>
				<tr>
					<td class="manage-column column-cb check-column">
						<label class="screen-reader-text" for="logs-select-all-1">Select All</label>
						<input id="logs-select-all-1" type="checkbox">
					</td>
					<th scope="col" class="manage-column column-title column-primary">URL</th>
					<th scope="col" class="manage-column column-max-calls">Max Calls</th>
					<th scope="col" class="manage-column column-options">Options</th>
					<th scope="col" class="manage-column column-hits">Hits</th>
				</tr>
			</thead>

			<tbody id="the-list">

			<?php
			$last_host = '';
			$max_index = count( secupress_get_http_logs_limits() ) - 1;
			function secupress_get_square_box_background_color( $index ) {
				$max     = count( secupress_get_http_logs_limits() ) - 1;
				$percent = 100 - ( $index * 100 / $max );
				$from    = [ 237, 95, 116 ]; // red RGB
				$to      = [ 51, 194, 127 ]; // green RGB
				$diff    = [ $to[0] - $from[0], $to[1] - $from[1], $to[2] - $from[2] ];
				$color   = [];
				for ( $i=0; $i < 3; $i++ ) {
					$color[] = round( $from[ $i ] + ( $percent * $diff[ $i ] / 100 ) );
				}
				return implode( ',', $color );
			}
			foreach( $http_log_settings as $url => $setting ) {
				$url_host = wp_parse_url( $url, PHP_URL_HOST );
				$prefix   = $url_host === $last_host ? '— ' : '';
				$level    = (int) ! empty( $prefix );
			?>
				<tr id="http-setting-<?php echo sanitize_html_class( $url ); ?>" class="level-<?php echo $level; ?>">
					<th scope="row" class="check-column">
						<label class="screen-reader-text" for="cb-select-1">Select <?php echo esc_html( $url ); ?></label>
						<input type="checkbox" name="http-setting[]" value="<?php echo esc_attr( $url ); ?>">
					</th>
					<td class="column-title has-row-actions column-primary">
						<strong>
							<?php
							$last_host = $url_host;
							$url       = strlen( $url ) > 160 ? substr( $url, 0, 158 ) . '<abbr title="' . esc_attr( $url ) . '">&hellip;</abbr>' : esc_html( $url );
							echo $prefix . $url;
							?>
						</strong>

						<div class="row-actions">
							<span class="since">Since: <abbr title="2019/08/22 9:00:46 am">22 April 2021</abbr></span> |
							<span class="delete"><a href="#" class="submitdelete" aria-label="Delete “<?php echo esc_attr( $url ); ?>”">Delete</a></span>
						</div>
					</td>
					<td class="column-max-calls">
					<?php
					$percent = 100 - round( $setting['index'] * 100 / $max_index, 2 );
					$color   = secupress_get_square_box_background_color( $setting['index'] );
					?>
					<div class="square-box" title="<?php echo esc_attr( secupress_get_http_logs_limits()[ $setting['index'] ] ); ?>">
						<div class="square-box square-box-external" style="background-color: rgb(<?php echo $color; ?>)">
							<div class="square-box square-box-internal">
								<div class="square-box square-box-percent" style="background: conic-gradient(#FFF <?php echo $percent; ?>%, #0000 0%);"></div>
							</div>
						</div>
					</div>

					</td>
					<td class="column-options">
						<?php
						if ( isset( $setting['options']['ignore-param'] ) ) {
							echo '<p>Parameters: <code>';
							echo implode( '</code>, <code>', $setting['options']['ignore-param'] );
							echo '</code>.</p>';
						}
						if ( isset( $setting['options']['block-method'] ) ) {
							echo '<p>Blocked Methods: <code>';
							echo implode( '</code>, <code>', $setting['options']['block-method'] );
							echo '</code>.</p>';
						}
						if ( ! isset( $setting['options']['ignore-param'] ) && ! isset( $setting['options']['block-method'] ) ) {
							echo '–';
						}
						$last    = isset( $setting['last'] ) && $setting['last'] > 0 ? sprintf( __( 'Last hit: %s', 'secupress' ), date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $setting['last'] ) ) : '';
						$hits    = isset( $setting['hits'] ) ? number_format_i18n( (int) $setting['hits'] ) : '–';
						$hit_fmt = ! empty( $last ) ? sprintf( '<span class="update-plugins"><span class="update-count"><abbr title="%1$s">%2$s</abbr></span></span>', esc_attr( $last ), esc_html( $hits ) ) : '–';
						?>
					</td>
					<td class="column-hits">
						<?php echo $hit_fmt; ?>
					</td>
				</tr>
			<?php
			}
			?>

			</tbody>
			<tfoot>
				<tr>
					<td class="manage-column column-cb check-column">
						<label class="screen-reader-text" for="logs-select-all-2">Select All</label>
						<input id="logs-select-all-2" type="checkbox">
					</td>
					<th scope="col" class="manage-column column-title column-primary">URL</th>
					<th scope="col" class="manage-column column-max-calls">Max Calls</th>
					<th scope="col" class="manage-column column-options">Options</th>
					<th scope="col" class="manage-column column-hits">Hits</th>
				</tr>
			</tfoot>
		</table>
		<?php
	}



	/**
	 * Displays the old backups.
	 *
	 * @since 1.0
	 */
	protected function backup_history() {
		?>
		<p id="secupress-no-backups"><em><?php _e( 'No backups found yet.', 'secupress' ); ?></em></p>
		<?php
	}


	/**
	 * Displays the tables to launch a backup
	 *
	 * @since 1.0
	 */
	protected function backup_db() {
		?>
		<p class="submit">
			<button type="button" disabled="disabled" class="secupress-button">
				<span class="icon">
					<i class="secupress-icon-download"></i>
				</span>
				<span class="text">
					<?php esc_html_e( 'Backup my database', 'secupress' ); ?>
				</span>
			</button>
		</p>
		<?php
	}


	/**
	 * Displays the files backups and the button to launch one.
	 *
	 * @since 1.0
	 */
	protected function backup_files() {
		?>
		<p class="submit">
			<button type="button" disabled="disabled" class="secupress-button">
				<span class="icon">
					<i class="secupress-icon-download"></i>
				</span>
				<span class="text">
					<?php esc_html_e( 'Backup my files', 'secupress' ); ?>
				</span>
			</button>
		</p>
		<?php
	}


	/**
	 * Scan the installation and search for modified/malicious files
	 *
	 * @since 1.0
	 */
	protected function file_scanner() {
		secupress_print_scanner_ui();
	}


	/** Includes ================================================================================ */

	/**
	 * Include the current module settings file.
	 *
	 * @since 1.0
	 *
	 * @return (object) The class instance.
	 */
	final protected function load_module_settings() {
		$module_file = SECUPRESS_MODULES_PATH . $this->modulenow . '/settings.php';

		if ( file_exists( $module_file ) ) {
			require_once( $module_file );
		}

		return $this;
	}


	/**
	 * Include a plugin settings file. Also, automatically set the current module and print the sections.
	 *
	 * @since 1.0
	 *
	 * @param (string) $plugin The plugin.
	 *
	 * @return (object) The class instance.
	 */
	final protected function load_plugin_settings( $plugin ) {
		/**
		 * Give the possibility to hide a full block of options
		 *
		 * @since 1.4
		 *
		 * @param (bool) false by default
		 */

		if ( false !== apply_filters( 'secupress.settings.load_plugin.' . $plugin, false ) ) {
			return;
		}
		$plugin_file = SECUPRESS_MODULES_PATH . $this->modulenow . '/settings/' . $plugin . '.php';

		return $this->require_settings_file( $plugin_file, $plugin );
	}


	/** Other =================================================================================== */

	/**
	 * Filter the arguments passed to the section submit button and disable it.
	 *
	 * @since 1.0.6
	 * @author Grégory Viguier
	 *
	 * @param (array) $args An array of arguments passed to the `submit_button()` method.
	 *
	 * @return (array)
	 */
	public function disable_sumit_buttons( $args ) {
		$wrap = isset( $args['wrap'] ) ? $args['wrap'] : true;
		$atts = array();
		$atts = isset( $args['other_attributes'] ) && is_array( $args['other_attributes'] ) ? $args['other_attributes'] : array();
		$atts = array_merge( $atts, array(
			'disabled'      => 'disabled',
			'aria-disabled' => 'true',
		) );

		return array_merge( $args, array(
			'wrap'             => $wrap,
			'other_attributes' => $atts,
		) );
	}
}
