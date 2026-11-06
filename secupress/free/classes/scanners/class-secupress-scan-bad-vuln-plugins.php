<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

/**
 * Bad Vulnerable Plugins scan class.
 *
 * @package SecuPress
 * @subpackage SecuPress_Scan
 * @since 1.0
 */
class SecuPress_Scan_Bad_Vuln_Plugins extends SecuPress_Scan implements SecuPress_Scan_Interface {

	/** Constants. ============================================================================== */

	/**
	 * Class version.
	 *
	 * @var (string)
	 */
	const VERSION = '1.2';


	/** Properties. ============================================================================= */

	/**
	 * The reference to the *Singleton* instance of this class.
	 *
	 * @var (object)
	 */
	protected static $_instance;

	/**
	 * Tells if a scanner is fixable by SecuPress. The value "pro" means it's fixable only with the version PRO.
	 *
	 * @var (bool|string)
	 */
	protected $fixable = 'pro';


	/** Init and messages. ====================================================================== */

	/**
	 * Init.
	 *
	 * @since 1.0
	 */
	protected function init() {
		$this->title = __( 'Check if you are using plugins known to be vulnerable.', 'secupress' );
		$this->more  = __( 'Never use a plugin with a known vulnerability, you should update or remove it as soon as possible!', 'secupress' );

		if ( is_network_admin() ) {
			$this->more_fix  = __( 'Select and delete vulnerable plugins.', 'secupress' );
			$this->more_fix .= '<br/>' . __( 'Not fixable on Multisite.', 'secupress' );
			$this->fixable   = false;
		} elseif ( ! is_multisite() ) {
			$this->more_fix = __( 'Delete vulnerable plugins.', 'secupress' );
		} else {
			$this->more_fix = __( 'Deactivate vulnerable plugins.', 'secupress' );
		}
	}


	/**
	 * Get messages.
	 *
	 * @since 1.0
	 *
	 * @param (int) $message_id A message ID.
	 *
	 * @return (string|array) A message if a message ID is provided. An array containing all messages otherwise.
	 */
	public static function get_messages( $message_id = null ) {
		$_107 = ! secupress_is_pro() ? sprintf( __( 'The %sPRO version%s will be more accurate.', 'secupress' ), '<a href="' . secupress_admin_url( 'get-pro' ) . '">', '</a>' ) : ' ' . __( 'Scan it now.', 'secupress' );
		$messages = array(
			// "good"
			0   => __( 'You don’t use plugins known to be vulnerable.', 'secupress' ),
			1   => __( 'You don’t use plugins known to be vulnerable anymore.', 'secupress' ),
			2   => __( 'All plugins known to be vulnerable have been deleted.', 'secupress' ),
			3   => __( 'All plugins known to be vulnerable have been deleted.', 'secupress' ),
			4   => __( 'All plugins known to be vulnerable have been deactivated.', 'secupress' ),
			// "warning"
			/** Translators: %s is a file name. */
			100 => __( 'Error, could not read %s.', 'secupress' ),
			101 => __( 'No plugins selected for deletion.', 'secupress' ),
			102 => _n_noop( 'Selected plugin has been deleted (but some are still there).', 'All selected plugins have been deleted (but some are still there).', 'secupress' ),
			103 => _n_noop( 'Sorry, the following plugin could not be deleted: %s.', 'Sorry, the following plugins could not be deleted: %s.', 'secupress' ),
			104 => __( 'No plugins selected for deactivation.', 'secupress' ),
			105 => _n_noop( 'Selected plugin has been deactivated (but some are still there).', 'All selected plugins have been deactivated (but some are still there).', 'secupress' ),
			106 => _n_noop( 'Sorry, the following plugin could not be deactivated: %s.', 'Sorry, the following plugins could not be deactivated: %s.', 'secupress' ),
			107 => sprintf( __( 'Your installation may contain vulnerable plugins.%s', 'secupress' ), $_107 ),
			// "bad"
			/** Translators: 1 is a number, 2 is a plugin name (or a list of plugin names). */
			200 => _n_noop( '<strong>%1$d plugin</strong> is known to be vulnerable: %2$s.', '<strong>%1$d plugins</strong> are known to be vulnerable: %2$s.', 'secupress' ),
			/** Translators: %s is a plugin name. */
			202 => __( 'You should delete the plugin %s.', 'secupress' ),
			203 => _n_noop( 'Sorry, this plugin could not be deleted.', 'Sorry, those plugins could not be deleted.', 'secupress' ),
			204 => _n_noop( 'The following plugin should be deactivated if you don’t need it: %s.', 'The following plugins should be deactivated if you don’t need them: %s.', 'secupress' ),
			205 => _n_noop( 'Sorry, this plugin could not be deactivated.', 'Sorry, those plugins could not be deactivated.', 'secupress' ),
			// "cantfix"
			/** Translators: %d is a number. */
			300 => _n_noop( '<strong>%d</strong> plugin can be <strong>deleted</strong>.', '<strong>%d</strong> plugins can be <strong>deleted</strong>.', 'secupress' ),
			/** Translators: %d is a number. */
			301 => _n_noop( '<strong>%d</strong> plugin can be <strong>deactivated</strong>.', '<strong>%d</strong> plugins can be <strong>deactivated</strong>.', 'secupress' ),
			302 => __( 'Unable to locate WordPress Plugin directory.', 'secupress' ),
			/** Translators: %s is the plugin name. */
			303 => sprintf( __( 'A new %s menu item has been activated in the relevant site’s administration area to let Administrators know which plugins to deactivate.', 'secupress' ), '<strong>' . SECUPRESS_PLUGIN_NAME . '</strong>' ),
			304 => __( 'No plugins selected.', 'secupress' ),
		);

		if ( isset( $message_id ) ) {
			return isset( $messages[ $message_id ] ) ? $messages[ $message_id ] : __( 'Unknown message', 'secupress' );
		}

		return $messages;
	}


	/** Getters. ================================================================================ */

	/**
	 * Get the documentation URL.
	 *
	 * @since 1.2.3
	 *
	 * @return (string)
	 */
	public static function get_docs_url() {
		return __( 'https://docs.secupress.me/article/121-vulnerable-plugins-check', 'secupress' );
	}


	/** Scan. =================================================================================== */

	/**
	 * Scan for flaw(s).
	 *
	 * @since 1.0
	 *
	 * @return (array) The scan results.
	 */
	public function scan() {

		$activated = $this->filter_scanner( __CLASS__ );
		if ( true === $activated ) {
			$this->add_message( 0 );
			return parent::scan();
		}

		if ( ! $this->is_for_current_site() ) {
			// If we're in a sub-site, don't list the plugins enabled in the network.
			$to_keep = array();
			// Plugins vulnerables.
			$bad_plugins = $this->get_installed_plugins_vulnerables();

			if ( is_numeric( $bad_plugins ) ) {
				$this->add_message( 107 );
			} elseif ( $count = count( $bad_plugins ) ) {
				// "bad"
				$this->add_message( 200, array( $count, $count, self::wrap_in_tag( $bad_plugins ) ) );
			}
		}
		// "good"
		$this->maybe_set_status( 0 );

		return parent::scan();
	}


	/** Fix. ==================================================================================== */

	/**
	 * Try to fix the flaw(s).
	 *
	 * @since 1.4.5
	 *
	 * @return (array) The fix results.
	 */
	/**
	 * A listed plugin needs a manual choice. Otherwise the Pro one-click fix can activate the detector.
	 *
	 * @since 2.7.4
	 *
	 * @return (bool|string)
	 */
	public function is_fixable() {
		if ( false === $this->fixable || ( 'pro' === $this->fixable && ! secupress_is_pro() ) ) {
			return $this->fixable;
		}

		$bad_plugins = $this->get_installed_plugins_vulnerables();

		if ( is_array( $bad_plugins ) && $bad_plugins ) {
			return false;
		}

		return $this->fixable;
	}

	/**
	 * Try to fix the flaw(s).
	 *
	 * @since 2.7.4 A real deletion form when vulnerable plugins are installed.
	 * @since 1.4.5
	 *
	 * @return (array) The fix results.
	 */
	public function need_manual_fix() {
		$bad_plugins = $this->get_installed_plugins_vulnerables();

		if ( is_array( $bad_plugins ) && $bad_plugins ) {
			return [ 'delete-vuln-plugins' => 'delete-vuln-plugins' ];
		}

		return [ 'fix' => 'fix' ];
	}

	/**
	 * Get an array containing ALL the forms that would fix the scan if it requires user action.
	 *
	 * @since 2.7.4 A checkbox list of vulnerable plugins.
	 * @since 1.4.5
	 *
	 * @return (array) An array of HTML templates (form contents most of the time).
	 */
	protected function get_fix_action_template_parts() {
		$plugins = $this->get_installed_plugins_vulnerables();

		if ( ! is_array( $plugins ) || ! $plugins ) {
			return [ 'fix' => '&nbsp;' ];
		}

		$installed = get_plugins();
		$form      = '<h4 id="secupress-fix-vuln-plugins">' . __( 'Checked plugins will be deleted:', 'secupress' ) . '</h4>';
		$form     .= '<fieldset aria-labelledby="secupress-fix-vuln-plugins" class="secupress-boxed-group">';

		foreach ( $plugins as $plugin_file ) {
			$is_symlinked = secupress_is_plugin_symlinked( $plugin_file );
			$plugin_data  = isset( $installed[ $plugin_file ] ) ? $installed[ $plugin_file ] : [];
			$plugin_name  = ! empty( $plugin_data['Name'] ) ? $plugin_data['Name'] : $plugin_file;
			$plugin_name  = esc_html( wp_strip_all_tags( $plugin_name ) );
			$input_id     = 'secupress-fix-delete-vuln-plugins-' . sanitize_html_class( $plugin_file );

			$form .= '<input type="checkbox" id="' . $input_id . '" name="secupress-fix-delete-vuln-plugins[]" value="' . esc_attr( $plugin_file ) . '" ' . ( $is_symlinked ? 'disabled="disabled"' : 'checked="checked"' ) . '/> ';
			$form .= '<label for="' . $input_id . '">';
			if ( $is_symlinked ) {
				$form .= '<del>' . $plugin_name . '</del> <span class="description">(' . __( 'symlinked', 'secupress' ) . ')</span>';
			} else {
				$form .= $plugin_name;
			}
			$form .= '</label><br/>';
		}

		$form .= '</fieldset>';

		return [ 'delete-vuln-plugins' => $form ];
	}

	/**
	 * Try to fix the flaw(s) after requiring user action.
	 *
	 * @since 1.4.5
	 *
	 * @return (array) The fix results.
	 */
	public function manual_fix() {
		if ( $this->has_fix_action_part( 'delete-vuln-plugins' ) ) {
			$this->manual_delete_vuln_plugins();
		} elseif ( $this->has_fix_action_part( 'fix' ) ) {
			return $this->fix();
		}

		return parent::manual_fix();
	}

	/**
	 * Try to fix the flaw(s).
	 *
	 * @since 2.7.4 Do not report success while vulnerable plugins are still installed.
	 * @since 1.0
	 *
	 * @return (array) The fix results.
	 */
	public function fix() {
		secupress_activate_submodule( 'plugins-themes', 'detect-bad-plugins' );

		$bad_plugins = $this->get_installed_plugins_vulnerables();

		if ( ! is_array( $bad_plugins ) || ! $bad_plugins ) {
			// "good"
			$this->add_fix_message( 1 );
		}

		return parent::fix();
	}

	/**
	 * Delete the vulnerable plugins selected in the manual fix form.
	 *
	 * @since 2.7.4
	 */
	protected function manual_delete_vuln_plugins() {
		$bad_plugins = $this->get_installed_plugins_vulnerables();

		if ( ! is_array( $bad_plugins ) || ! $bad_plugins ) {
			$this->add_fix_message( 1 );
			return;
		}

		$allowed          = array_fill_keys( array_values( $bad_plugins ), 1 );
		$selected_plugins = ! empty( $_POST['secupress-fix-delete-vuln-plugins'] ) && is_array( $_POST['secupress-fix-delete-vuln-plugins'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['secupress-fix-delete-vuln-plugins'] ) ) : []; // WPCS: CSRF ok.
		$selected_plugins = array_fill_keys( $selected_plugins, 1 );
		$selected_plugins = array_intersect_key( $allowed, $selected_plugins );

		if ( ! $selected_plugins ) {
			$this->add_fix_message( 101 );
			return;
		}

		if ( ! function_exists( 'delete_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$result = delete_plugins( array_keys( $selected_plugins ) );

		if ( is_wp_error( $result ) || null === $result ) {
			$this->add_fix_message( 103, [ count( $selected_plugins ), implode( ', ', array_keys( $selected_plugins ) ) ] );
			return;
		}

		$still_there = $this->get_installed_plugins_vulnerables();

		if ( is_array( $still_there ) && $still_there ) {
			$this->add_fix_message( 102, [ count( $selected_plugins ) ] );
			return;
		}

		$this->add_fix_message( 2 );
	}


	/** Tools. ================================================================================== */

	/**
	 * Get an array of installed plugins that are vulnerable.
	 *
	 * @since 2.1 Returns 1 if not pro
	 * @since 1.0.3 Don't use the whitelist
	 * @since 1.0
	 *
	 * @param (bool) $for_fix False: for scan. True: for fix.
	 *
	 * @return (array) An array like `array( path => plugin_name, path => plugin_name )`.
	 */
	final protected function get_installed_plugins_vulnerables( $for_fix = false ) {
		static $whitelist_error = false;

		if ( ! secupress_is_pro() ) {
			return 1;
		}

		$bad_plugins = secupress_get_vulnerable_plugins();

		if ( ! $bad_plugins ) {
			return array();
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();
		$all_plugins = array_keys( get_plugins() );
		$all_plugins = array_combine( array_map( 'dirname', $all_plugins ), $all_plugins );
		$bad_plugins = array_intersect_key( $all_plugins, $bad_plugins );

		return $bad_plugins;
	}
}
