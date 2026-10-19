<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

/**
 * Move Login scan class.
 *
 * @package SecuPress
 * @subpackage SecuPress_Scan
 * @since 2.7.2
 */
class SecuPress_Scan_Move_Login extends SecuPress_Scan implements SecuPress_Scan_Interface {

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
	 * Not listed in the automatic fix step: the login slug must be chosen manually.
	 *
	 * @var (bool)
	 */
	protected $fixable = false;


	/** Init and messages. ====================================================================== */

	/**
	 * Init.
	 *
	 * @since 2.7.2
	 */
	protected function init() {
		$this->title    = __( 'Check if your login page has been moved.', 'secupress' );
		$this->more     = __( 'The default login page is a common target. Moving it reduces automated attacks against wp-login.php.', 'secupress' );
		$this->more_fix = sprintf(
			__( 'Activate the <strong>%1$s</strong> from the module %2$s.', 'secupress' ),
			__( 'Move Login', 'secupress' ),
			'<a href="' . esc_url( secupress_admin_url( 'modules', 'users-login' ) ) . '#row-move-login_activated">' . __( 'Users & Login', 'secupress' ) . '</a>'
		);
	}


	/**
	 * Get messages.
	 *
	 * @since 2.7.2
	 *
	 * @param (int) $message_id A message ID.
	 *
	 * @return (string|array) A message if a message ID is provided. An array containing all messages otherwise.
	 */
	public static function get_messages( $message_id = null ) {
		$messages = [
			// "good"
			0   => __( 'The login page has been moved with %s.', 'secupress' ),
			1   => __( 'The login page has been moved.', 'secupress' ),
			2   => __( 'The <strong>Move Login</strong> module has been activated. Your new login page is %s.', 'secupress' ),
			// "warning"
			100 => __( 'Unable to determine if the login page has been moved.', 'secupress' ),
			// "bad"
			200 => __( 'Your login page is still accessible at its default address.', 'secupress' ),
			201 => sprintf( __( 'Our module %s could fix this.', 'secupress' ), '<a href="' . esc_url( secupress_admin_url( 'modules', 'users-login' ) ) . '#row-move-login_activated">' . __( 'Move Login', 'secupress' ) . '</a>' ),
			202 => sprintf( __( 'Your website is not using <b>Pretty Permalinks</b> but this module needs that. You can activate that in the <a href="%s">Permalinks Settings Page</a> and do not use "Plain" setting.', 'secupress' ), esc_url( admin_url( 'options-permalink.php' ) ) ),
			203 => __( 'Please select your login page.', 'secupress' ),
			204 => __( 'The slug %s is forbidden.', 'secupress' ),
			205 => __( 'The links can’t have the same slugs.', 'secupress' ),
		];

		if ( isset( $message_id ) ) {
			return isset( $messages[ $message_id ] ) ? $messages[ $message_id ] : __( 'Unknown message', 'secupress' );
		}

		return $messages;
	}


	/** Getters. ================================================================================ */

	/**
	 * Get the documentation URL.
	 *
	 * @since 2.7.2
	 *
	 * @return (string)
	 */
	public static function get_docs_url() {
		return __( 'https://docs.secupress.me/', 'secupress' );
	}


	/** Scan. =================================================================================== */

	/**
	 * Scan for flaw(s).
	 *
	 * @since 2.7.2
	 *
	 * @return (array) The scan results.
	 */
	public function scan() {

		$activated = $this->filter_scanner( __CLASS__ );
		if ( true === $activated ) {
			$this->add_message( 1 );
			return parent::scan();
		}

		$our_module = function_exists( 'secupress_move_login_maybe_deny_login_page' ) && secupress_is_submodule_active( 'users-login', 'move-login' );
		$activated  = $our_module ? __( 'Move Login', 'secupress' ) : false;

		/**
		 * Overwrite the activated value when another plugin already moves the login page.
		 *
		 * @since 2.7.2
		 *
		 * @param (bool|string) $activated False when nothing moved the login page, or the name of the system that did.
		 */
		$activated = apply_filters( 'secupress.scan.' . __CLASS__ . '.activated', $activated );

		if ( $activated ) {
			$this->add_message( 0, [ '<strong>' . $activated . '</strong>' ] );
			return parent::scan();
		}

		$response = $this->remote_get_default_login();

		if ( is_wp_error( $response ) ) {
			$this->add_message( 100 );
			return parent::scan();
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( in_array( $code, [ 401, 403, 404, 500 ], true ) ) {
			$this->add_message( 1 );
		} elseif ( $this->is_login_page( wp_remote_retrieve_body( $response ) ) ) {
			$this->add_message( 200 );
			$this->add_pre_fix_message( 201 );
		} else {
			$this->add_message( 100 );
		}

		return parent::scan();
	}


	/**
	 * Request the default login URL without using wp_login_url(), then follow redirections.
	 *
	 * admin_url() is not rewritten by Move Login, unlike site_url( 'wp-login.php' ) and wp_login_url().
	 *
	 * @since 2.7.2
	 * @author Julio Potier
	 *
	 * @return (array|WP_Error)
	 */
	protected function remote_get_default_login() {
		$admin_url = trailingslashit( admin_url() );
		$login_url = preg_replace( '#/wp-admin/$#', '/wp-login.php', $admin_url );

		if ( ! is_string( $login_url ) || $login_url === $admin_url ) {
			return new WP_Error( 'secupress_move_login_url', __( 'Unable to determine if the login page has been moved.', 'secupress' ) );
		}

		$args                 = $this->get_default_request_args();
		$args['redirection']  = 5;
		$args['cookies']      = [];

		return wp_remote_get( $login_url, $args );
	}


	/**
	 * Tell if a response body is the WordPress login form.
	 *
	 * @since 2.7.2
	 * @author Julio Potier
	 *
	 * @param (string) $body Response body.
	 *
	 * @return (bool)
	 */
	protected function is_login_page( $body ) {
		if ( ! is_string( $body ) || '' === $body ) {
			return false;
		}

		if ( false !== stripos( $body, 'id="loginform"' ) || false !== stripos( $body, "id='loginform'" ) ) {
			return true;
		}

		return false !== stripos( $body, 'name="log"' ) && false !== stripos( $body, 'name="pwd"' );
	}


	/** Fix. ==================================================================================== */

	/**
	 * Try to fix the flaw(s).
	 *
	 * @since 2.7.2
	 *
	 * @return (array) The fix results.
	 */
	public function fix() {
		$this->add_fix_message( 203 );

		return parent::fix();
	}


	/** Manual fix. ============================================================================= */

	/**
	 * Return an array of actions if a manual fix is needed here.
	 *
	 * @since 2.7.2
	 *
	 * @return (array)
	 */
	public function need_manual_fix() {
		return [ 'choose-login-slug' => 'choose-login-slug' ];
	}


	/**
	 * Try to fix the flaw(s) after requiring user action.
	 *
	 * @since 2.7.2
	 *
	 * @return (array) The fix results.
	 */
	public function manual_fix() {
		if ( ! $this->has_fix_action_part( 'choose-login-slug' ) ) {
			return parent::manual_fix();
		}

		$wp_rewrite = new WP_Rewrite();

		if ( ! $wp_rewrite->using_permalinks() ) {
			$this->add_fix_message( 202 );
			return parent::manual_fix();
		}

		$raw_slug = isset( $_POST['secupress-fix-move-login-slug'] ) ? wp_unslash( $_POST['secupress-fix-move-login-slug'] ) : ''; // WPCS: CSRF ok.
		$slug     = sanitize_title( $raw_slug );

		if ( ! $slug ) {
			$this->add_fix_message( 203 );
			return parent::manual_fix();
		}

		if ( $this->is_reserved_move_login_slug( $slug ) ) {
			$this->add_fix_message( 204, [ '<code>' . esc_html( $slug ) . '</code>' ] );
			return parent::manual_fix();
		}

		if ( $this->is_duplicate_move_login_slug( $slug ) ) {
			$this->add_fix_message( 205 );
			return parent::manual_fix();
		}

		secupress_update_module_option( 'move-login_slug-login', $slug, 'users-login' );
		secupress_activate_submodule( 'users-login', 'move-login' );

		$login_url = user_trailingslashit( site_url( $slug ) );
		$this->add_fix_message( 2, [ '<a href="' . esc_url( $login_url ) . '"><code>' . esc_html( $slug ) . '</code></a>' ] );

		return parent::manual_fix();
	}


	/**
	 * Get an array containing ALL the forms that would fix the scan if it requires user action.
	 *
	 * @since 2.7.2
	 *
	 * @return (array) An array of HTML templates (form contents most of the time).
	 */
	protected function get_fix_action_template_parts() {
		$form  = '<h4 id="secupress-fix-move-login">' . __( 'Choose your login URL', 'secupress' ) . '</h4>';
		$form .= '<fieldset aria-labelledby="secupress-fix-move-login" class="secupress-boxed-group">' . "\n";
		$form .= '<label for="secupress-fix-move-login-slug">' . esc_html( trailingslashit( site_url() ) ) . '</label> ';
		$form .= '<input type="text" id="secupress-fix-move-login-slug" name="secupress-fix-move-login-slug" value="" />' . "\n";
		$form .= '<p>' . sprintf( __( 'The slugs %s are forbidden.', 'secupress' ), '<code>login</code>, <code>wp-admin</code>' ) . '</p>';
		$form .= "</fieldset>\n";

		return [ 'choose-login-slug' => $form ];
	}


	/**
	 * Tell if a login slug is reserved, as in the Move Login settings.
	 *
	 * @since 2.7.2
	 * @author Julio Potier
	 *
	 * @param (string) $slug Sanitized slug.
	 *
	 * @return (bool)
	 */
	protected function is_reserved_move_login_slug( $slug ) {
		$labels    = function_exists( 'secupress_move_login_slug_labels' ) ? secupress_move_login_slug_labels() : [];
		$forbidden = [
			'postpass'         => 1,
			'retrievepassword' => 1,
			'rp'               => 1,
		];
		$forbidden = array_diff_key( $forbidden, $labels );
		$forbidden['login']        = 1;
		$forbidden['wp-admin']     = 1;
		$forbidden['wp-login']     = 1;
		$forbidden['wp-login-php'] = 1;

		return isset( $forbidden[ $slug ] );
	}


	/**
	 * Tell if a login slug collides with another Move Login action.
	 *
	 * @since 2.7.2
	 * @author Julio Potier
	 *
	 * @param (string) $slug Sanitized slug.
	 *
	 * @return (bool)
	 */
	protected function is_duplicate_move_login_slug( $slug ) {
		if ( ! function_exists( 'secupress_move_login_slug_labels' ) ) {
			return false;
		}

		$labels = secupress_move_login_slug_labels();
		unset( $labels['login'] );

		return isset( $labels[ $slug ] );
	}
}
