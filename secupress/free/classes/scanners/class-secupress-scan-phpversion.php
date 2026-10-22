<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

/**
 * PhpVersion scan class.
 *
 * @package SecuPress
 * @subpackage SecuPress_Scan
 * @since 1.0
 */
class SecuPress_Scan_PhpVersion extends SecuPress_Scan implements SecuPress_Scan_Interface {

	/** Constants. ============================================================================== */

	/**
	 * Class version.
	 *
	 * @var (string)
	 */
	const VERSION = '2.1';


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
	protected $fixable = false;


	/** Init and messages. ====================================================================== */

	/**
	 * Init.
	 *
	 * @since 1.0
	 * @since 2.7.3 Explain the build date check when that date can be read.
	 */
	protected function init() {
		$this->title    = __( 'Check if your installation is using a supported version of PHP.', 'secupress' );
		$this->more     = __( 'Every year, old PHP version are not supported anymore, even for security patches so it’s important to stay updated.', 'secupress' );
		if ( '' !== secupress_get_php_build_date() ) {
			$this->more .= ' ' . sprintf(
				__( 'The build date is also checked, because a supported version can still miss the %s.', 'secupress' ),
				'<a href="https://www.php.net/archive/2026.php#2026-10-21-5" target="_blank">' .  sprintf( __( '%s security release', 'secupress' ), date_i18n( 'F d, Y', strtotime( '2026-10-21' ) ) ) . '</a>'
			);
		}
		$this->more_fix = static::get_messages( 300 );
	}


	/**
	 * Get messages.
	 *
	 * @since 1.0
	 * @since 2.7.3 Message 201 when the PHP build is older than October 21, 2026.
	 *
	 * @param (int) $message_id A message ID.
	 *
	 * @return (string|array) A message if a message ID is provided. An array containing all messages otherwise.
	 */
	public static function get_messages( $message_id = null ) {
		$versions = secupress_get_php_versions();
		$messages = array(
			// "good"
			0   => sprintf( __( 'You are using <strong>PHP v%s</strong>.', 'secupress' ), $versions['current'] ),
			1   => sprintf( __( 'You are using <strong>PHP v%s</strong>, do not go lower!', 'secupress' ), $versions['current'] ), // last
			2   => sprintf( __( 'You are using <strong>PHP v%s</strong>, this is correctly supported.', 'secupress' ), $versions['current'] ), // mini
			3   => sprintf( __( 'You are using <strong>PHP v%s</strong>, the last one? Perfect!', 'secupress' ), $versions['current'] ), // best
			// "warning"
			100 => __( 'Unable to determine version of PHP.', 'secupress' ),
			// "bad"
			200 => sprintf( __( 'You are using <strong>PHP v%1$s</strong>, but the oldest major supported version is <strong>PHP v%2$s</strong>, and the last one is <strong>PHP v%3$s</strong>.', 'secupress' ), $versions['current'], $versions['mini'], $versions['best'] ),
			201 => sprintf( __( 'You are using <strong>PHP v%1$s</strong> built on <strong>%2$s</strong>. This build is older than <strong>%3$s</strong>.', 'secupress' ), $versions['current'], $versions['build_date'], date_i18n( 'F d, Y', strtotime( '2026-10-21' ) ) ),
			// "cantfix"
			300 => __( 'Cannot be fixed automatically. You have to contact your host provider to ask him to <strong>upgrade your version of PHP</strong>.', 'secupress' ),
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
		return __( 'https://docs.secupress.me/article/114-php-version-scan', 'secupress' );
	}


	/** Scan. =================================================================================== */

	/**
	 * Scan for flaw(s).
	 *
	 * @since 1.0
	 * @since 2.7.3 A known build date before October 21, 2026 is critical.
	 *
	 * @return (array) The scan results.
	 */
	public function scan() {

		$activated = $this->filter_scanner( __CLASS__ );
		if ( true === $activated ) {
			$this->add_message( 0 );
			return parent::scan();
		}

		$versions        = secupress_get_php_versions();
		$version_too_old = version_compare( $versions['current'], $versions['mini'] ) < 0;
		$build_outdated  = ! empty( $versions['build_outdated'] );

		if ( $build_outdated ) {
			$this->add_message( 201, array( $versions['current'], $versions['build_date'] ) );
		}

		if ( $version_too_old ) {
			$this->add_message( 200 );
		} elseif ( ! $build_outdated ) {
			if ( version_compare( $versions['current'], $versions['last'] ) < 0 ) {
				$this->add_message( 1 );
			} elseif ( version_compare( $versions['current'], $versions['best'] ) < 0 ) {
				$this->add_message( 2 );
			} else {
				$this->add_message( 3 );
			}
		}

		if ( $version_too_old || $build_outdated ) {
			$this->add_pre_fix_message( 300 );
		}

		return parent::scan();
	}


	/** Fix. ==================================================================================== */

	/**
	 * Try to fix the flaw(s).
	 *
	 * @since 1.0
	 *
	 * @return (array) The fix results.
	 */
	public function fix() {
		$this->add_fix_message( 300 );
		return parent::fix();
	}

}
