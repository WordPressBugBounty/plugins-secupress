<?php
/**
 * Module Name: Disallowed Logins
 * Description: Forbid some usernames to be used.
 * Main Module: users_login
 * Author: SecuPress
 * Version: 1.0
 */

defined( 'SECUPRESS_VERSION' ) or die( 'Something went wrong.' );

/** --------------------------------------------------------------------------------------------- */
/** EXISTING USERS WITH A BLACKLISTED USERNAME MUST CHANGE IT. ================================== */
/** --------------------------------------------------------------------------------------------- */

add_action( 'auth_redirect', 'secupress_auth_redirect_blacklist_logins' );
/**
 * As soon as we are sure a user is connected, and before any redirection, check if the user login is not blacklisted.
 * If he is, he can't access the administration area and is asked to change it.
 *
 * @author Julio Potier
 * @since 1.0
 *
 * @param (int) $user_id The user ID.
 */
function secupress_auth_redirect_blacklist_logins( $user_id ) {
	if ( ! is_admin() || wp_doing_ajax() ) {
		return;
	}

	$user = get_userdata( $user_id );

	if ( ! secupress_is_username_blacklisted( $user->user_login ) ) {
		// Good, the login is not blacklisted.
		return;
	}

	$nonce_action = 'secupress-blacklist-logins-new-login-' . $user_id;
	$error        = '';

	// A new login is submitted.
	if ( isset( $_POST['secupress-blacklist-logins-new-login'] ) ) {

		if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], $nonce_action ) ) {
			wp_die( __( 'Something went wrong.', 'secupress' ) );
		}

		if ( empty( $_POST['secupress-blacklist-logins-new-login'] ) ) {
			// Empty username.
			$error = __( 'Username required', 'secupress' );
		} else {
			// Sanitize the submitted username.
			$user_login       = sanitize_user( $_POST['secupress-blacklist-logins-new-login'], true );

			if ( secupress_is_username_blacklisted( $user_login ) ) {
				// The new login is blacklisted.
				$error = __( 'This username is not allowed.', 'secupress' );
			} else {
				// Good, change the user login.
				$inserted = secupress_blacklist_logins_change_user_login( $user_id, $user_login );

				if ( is_wp_error( $inserted ) ) {
					// Too bad, try again.
					$error = $inserted->get_error_message();
				} else {
					// Use the new login now, to be included in the email
					$user->user_login = $user_login;
					// Send the new login by email.
					secupress_blocklist_logins_new_user_notification( $user );

					// Kill session.
					wp_clear_auth_cookie();
					wp_destroy_current_session();

					// Redirect the user to the login page.
					$login_url = wp_login_url( secupress_get_current_url( 'raw' ), true );
					$login_url = add_query_arg( 'secupress-relog', 1, $login_url );
					wp_redirect( esc_url_raw( $login_url ) );
					exit();
				}
			}
		}
	}

	// Allowed characters for the login.
	$allowed = esc_attr( secupress_blacklist_logins_allowed_characters() );

	// The form.
	ob_start();
	?>
	<form class="wrap" method="post">
		<h1><?php _e( 'Please change your username', 'secupress' ); ?></h1>
		<p>
			<?php
			printf(
				/** Translators: 1 is a user name, 2 is a link "to the site" */
				__( 'Your current username %1$s is not allowed. You must change your username to access the administration area. Until then, you retain access <a href="%2$s">to the site</a>.', 'secupress' ),
				secupress_tag_me( esc_html( $user->user_login ), 'strong' ),
				esc_url( user_trailingslashit( home_url() ) )
			);
			?>
		</p>
		<?php echo $error ? '<p class="error">' . $error . '</p>' : ''; ?>
		<label for="new-login"><?php _e( 'New username:', 'secupress' ); ?></label><br/>
		<input type="text" id="new-login" name="secupress-blacklist-logins-new-login" value="" maxlength="60" required="required" aria-required="true" pattern="[A-Za-z0-9 _.\-@]{2,60}" autocorrect="off" autocapitalize="off" title="<?php echo $allowed; ?>"/><br/>
		<input type="submit" />
		<?php wp_nonce_field( $nonce_action ) ?>
	</form>
	<?php
	$title   = __( 'Please change your username', 'secupress' );
	$content = ob_get_contents();
	ob_clean();

	secupress_action_page( $title, $content );
}


add_filter( 'wp_login_errors', 'secupress_blacklist_logins_display_login_message', 10, 2 );
/**
 * Display a message on the login form after the new login creation.
 *
 * @since 1.0
 * @author Julio Potier
 *
 * @param (object) $errors      WP Error object.
 * @param (string) $redirect_to Redirect destination URL.
 *
 * @return (object) WP Error object.
 */
function secupress_blacklist_logins_display_login_message( $errors, $redirect_to ) {
	if ( empty( $_GET['secupress-relog'] ) ) {
		return $errors;
	}

	if ( empty( $errors ) ) {
		$errors = new WP_Error();
	}

	$errors->add( 'secupress_relog', __( 'You will receive your new login in your mailbox.', 'secupress' ), 'message' );

	return $errors;
}


/** --------------------------------------------------------------------------------------------- */
/** UTILITIES =================================================================================== */
/** --------------------------------------------------------------------------------------------- */

/**
 * Change a user login.
 *
 * @since 1.0
 * @author Julio Potier
 *
 * @param (int)    $user_id    I let you guess what it is.
 * @param (string) $user_login Well...
 *
 * @return (int|object) User ID or WP_Error object on failure.
 */
function secupress_blacklist_logins_change_user_login( $user_id, $user_login ) {
	global $wpdb;

	// `user_login` must be between 1 and 60 characters.
	if ( empty( $user_login ) ) {
		return new WP_Error( 'empty_user_login', __( 'Cannot create a user with an empty login name.' ) );
	} elseif ( mb_strlen( $user_login ) > 60 ) {
		return new WP_Error( 'user_login_too_long', __( 'Username may not be longer than 60 characters.' ) );
	}

	if ( username_exists( $user_login ) ) {
		return new WP_Error( 'existing_user_login', __( 'Sorry, that username already exists!' ) );
	}

	$wpdb->update( $wpdb->users, array( 'user_login' => $user_login ), array( 'ID' => $user_id ) );

	wp_cache_delete( $user_id, 'users' );
	wp_cache_delete( $user_login, 'userlogins' );

	secupress_scanit( 'Bad_Usernames' );

	return $user_id;
}


/**
 * Send an email notification to a user with his/her login.
 *
 * @since 1.0
 * @author Julio Potier
 *
 * @param (int|object) $user A user ID or a user object.
 */
function secupress_blocklist_logins_new_user_notification( $user ) {
	$user     = secupress_is_user( $user ) ? $user : get_userdata( $user );
	/* Translators: 1 is a blog name. */
	$subject  = sprintf( __( '[%s] Your username info', 'secupress' ), '###SITENAME###' );
	$message  = sprintf( __( 'Username: %s', 'secupress' ), $user->user_login ) . "\r\n\r\n";
	$message .= esc_url_raw( wp_login_url() ) . "\r\n";

	/**
	 * Filter the mail subject
	 * @param (string) $subject
	 * @param (WP_User) $user
	 * @since 2.2
	 */
	$subject = apply_filters( 'secupress.mail.blocklist_logins.subject', $subject, $user );
	/**
	 * Filter the mail message
	 * @param (string) $message
	 * @param (WP_User) $user
	 * @since 2.2
	 */
	$message = apply_filters( 'secupress.mail.blocklist_logins.message', $message, $user );

	secupress_send_mail( $user->user_email, $subject, $message );
}


/**
 * Tell if a username is blacklisted.
 *
 * @since 2.2.6 stripos( $username, 'admin' )
 * @author Julio Potier
 * 
 * @since 1.0
 * @author Grégory Viguier
 *
 * @param (string) $username The username to test.
 *
 * @return (bool) true if blacklisted.
 */
function secupress_is_username_blacklisted( $username ) {
	if ( secupress_get_module_option( 'blacklist-logins_admin', 0, 'users-login' ) && stripos( $username, 'admin' ) !== false && strtolower( $username ) !== 'admin' ) {
		return ! isset( apply_filters( 'secupress.plugins.allowed_usernames', [] )[ $username ] );
	}
    $list           = secupress_get_blacklisted_usernames();
    $list_flipped   = array_flip( $list );
    $username_lower = mb_strtolower( $username );

    // Cgeck for exact match
    if ( isset( $list_flipped[ $username_lower ] ) ) {
        return true;
    }

    // Or check for match from start
    foreach ( $list as $blacklisted_name ) {
        $blacklisted_name_replaced = str_replace( '*', '', $blacklisted_name );
        if ( strpos( $blacklisted_name, '*' ) > 0 && ( strpos( $username_lower, mb_strtolower( $blacklisted_name_replaced ) ) === 0 || isset( $list_flipped[ $blacklisted_name_replaced ] ) ) ) { // 0 = first char. // * = only these names have to be checked like that
            return true;
        }
    }

    return false;
}


/**
 * Logins blacklist: return the list of allowed characters for the usernames.
 *
 * @since 1.0
 * @author Julio Potier
 *
 * @param (bool) $wrap If set to true, the characters will be wrapped with `code` tags.
 *
 * @return (string)
 */
function secupress_blacklist_logins_allowed_characters( $wrap = false ) {
	$allowed = is_multisite() ? array( 'a-z', '0-9' ) : array( 'A-Z', 'a-z', '0-9', '(space)', '_', '.', '-', '@' );
	if ( $wrap ) {
		foreach ( $allowed as $i => $char ) {
			$allowed[ $i ] = '<code>' . $char . '</code>';
		}
	}
	$allowed = wp_sprintf_l( '%l', $allowed );

	return sprintf( __( 'Allowed characters: %s.', 'secupress' ), $allowed );
}


/** --------------------------------------------------------------------------------------------- */
/** FORBID USER CREATION AND EDITION IF THE USERNAME IS BLACKLISTED. ============================ */
/** --------------------------------------------------------------------------------------------- */


add_filter( 'illegal_user_logins', 'secupress_blacklist_logins_illegal_user_logins' );
/**
 * Filter the blacklisted user names.
 * This filter is used in `wp_insert_user()`, `edit_user()` and `wpmu_validate_user_signup()`.
 *
 * @since 2.2.6 usage of 'illegal_user_logins' filter (compat 4.4), finally!
 * @since 1.0
 * @author Julio Potier
 *
 * @param (array) $usernames A list of forbidden user names.
 *
 * @return (array) The forbidden user names.
 */
function secupress_blacklist_logins_illegal_user_logins( $usernames ) {
	return array_merge( $usernames, secupress_get_blacklisted_usernames() );
}


add_action( 'user_profile_update_errors', 'secupress_blacklist_logins_user_profile_update_errors', 10, 3 );
/**
 * In `edit_user()`, detect forbidden logins.
 *
 * @since 1.0
 * @author Julio Potier
 *
 * @param (object) $errors A WP_Error object, passed by reference.
 * @param (bool)   $update Whether this is a user update.
 * @param (object) $user   A WP_User object, passed by reference.
 *
 * @return (object) The WP_Error object with a new error if the user name is blacklisted.
 */
function secupress_blacklist_logins_user_profile_update_errors( $errors, $update, $user ) {
	if ( secupress_is_username_blacklisted( $user->user_login ) ) {
		$errors->add( 'user_name',  __( 'Sorry, that username is not allowed.', 'secupress' ) );
	}
	return $errors;
}


add_filter( 'wpmu_validate_user_signup', 'secupress_blacklist_logins_wpmu_validate_user_signup' );
/**
 * In `wpmu_validate_user_signup()`, detect forbidden logins.
 *
 * @since 1.0
 * @author Julio Potier
 *
 * @param (array) $result An array containing the sanitized user name, the original one, the user email, and a `WP_Error` object.
 *
 * @return (array) The array with a new error if the user name is blacklisted.
 */
function secupress_blacklist_logins_wpmu_validate_user_signup( $result ) {
	if ( secupress_is_username_blacklisted( $result['user_name'] ) ) {
		$result['errors']->add( 'user_name',  __( 'Sorry, that username is not allowed.', 'secupress' ) );
	}
	return $result;
}

add_action( 'secupress.modules.activate_submodule_' . basename( __FILE__, '.php' ), 'secupress_bad_logins_de_activate_file' );
add_action( 'secupress.modules.deactivate_submodule_' . basename( __FILE__, '.php' ), 'secupress_bad_logins_de_activate_file' );
/**
 * On module de/activation, rescan.
 * @author Julio Potier
 * @since 2.0
 */
function secupress_bad_logins_de_activate_file() {
	secupress_scanit( 'Bad_Usernames' );
}

add_filter( 'user_row_actions', 'secupress_bad_logins_css', 10, 2 );
function secupress_bad_logins_css( $dummy, $user_object ) {
	static $even;
	$even++;
	if ( secupress_is_username_blacklisted( $user_object->user_login ) ) {
		$bg = $even % 2 !== 0 ? "background-image: linear-gradient(130deg, rgba(170, 170, 170, 0.07) 25%, transparent 25%, transparent 50%, rgba(170, 170, 170, 0.07) 50%, rgba(170, 170, 170, 0.07) 75%, transparent 75%, transparent 100%);"
						: "background-image: linear-gradient(130deg, rgba(170, 170, 170, 0.13) 25%, transparent 25%, transparent 50%, rgba(170, 170, 170, 0.13) 50%, rgba(170, 170, 170, 0.13) 75%, transparent 75%, transparent 100%);";
        echo "<style type='text/css'>
            #user-{$user_object->ID} {
        		{$bg}
                background-size: 12px 15px;
            }
        </style>";
	}
	return $dummy;
}

add_filter( 'views_users', 'secupress_bad_username_view' );
/**
 * Add the "Bad username" tab to the users.php page.
 *
 * @since 2.2.6
 * @author Julio Potier
 *
 * @param array $views An array of user views.
 * @return array Modified array of user views.
 */
function secupress_bad_username_view( $views ) {
    $bad_usernames = count( secupress_get_bad_username_ids() );
    if ( ! $bad_usernames ) {
    	return $views;
    }
    $current             = isset( $_GET['secupress_bad_username'] );
    if ( $current ) {
    	$views['all']    = str_replace( 'class="current"', '', $views['all'] );
    }

    $views['secupress_bad_username'] = sprintf(
        '<a href="%s"%s>%s <span class="count">(%s)</span></a>',
        esc_url( add_query_arg( 'secupress_bad_username', 1, admin_url( 'users.php' ) ) ),
        $current ? ' class="current"' : '',
        __( 'Bad Username', 'secupress' ),
        $bad_usernames
    );

    return $views;
}


/**
 * Get IDs of the bad username users
 *
 * @since 2.2.6
 * @author Julio Potier
 * 
 * @return (array) $user_ids The IDs of the bad username users
 **/
function secupress_get_bad_username_ids() {
    global $wpdb;
    static $user_ids;

	if ( ! empty( $user_ids ) ) {
		return $user_ids;
	} 
    $like = '';
    $list = secupress_get_blacklisted_usernames();
    $jokr = array_filter( $list , function( $a ){ return false !== strpos( $a, '*' ); } );
    $list = array_filter( $list , function( $a ){ return false === strpos( $a, '*' ); } );
    $list = implode( '\',\'', $list );
    if ( ! empty( $jokr ) ) {
		$like .= ' OR ' . implode( ' OR ', array_map( function( $username ) {
		return "user_login LIKE '" . str_replace( '*', '%', $username ) . "'";
		}, $jokr ) );    	
    }
	if ( secupress_get_module_option( 'blacklist-logins_admin', 0, 'users-login' ) ) {
		$like .= " OR ( user_login LIKE '%admin%' AND user_login != 'admin')";
	}
	$allowed_usernames = array_flip( apply_filters( 'secupress.plugins.allowed_usernames', [] ) );
	if ( ! empty( $allowed_usernames ) ) {
		$like .= ' AND (' . implode( ' OR ', array_map( function( $username ) {
		return "user_login NOT LIKE '" . str_replace( '*', '%', $username ) . "'";
		}, $allowed_usernames ) ) . ')';    	
	}
    // No sanitization needed since it's hardcoded, no user input.
    $sql      = "SELECT ID FROM {$wpdb->users} WHERE user_login in ('{$list}')" . $like;
    $user_ids = $wpdb->get_col( $sql );

    return $user_ids;
}

add_action( 'pre_get_users', 'secupress_bad_username_custom_modify_user_query' );
/**
 * Modify the user query based on the "Connected users" filter.
 *
 * @since 2.2.6
 * @author Julio Potier
 *
 * @param WP_User_Query $query The WP_User_Query instance.
 * @return WP_User_Query Modified WP_User_Query instance.
 */
function secupress_bad_username_custom_modify_user_query( $query ) {
	global $pagenow;
	if ( ! isset( $pagenow ) || 'users.php' !== $pagenow || ! is_admin() ) {
		return $query;
	}
	$_user_ids = [];
	remove_action( 'pre_get_users', 'secupress_bad_username_custom_modify_user_query' );
	if ( isset( $_GET['secupress_bad_username'] ) && $_GET['secupress_bad_username'] === '1' ) {
		$_user_ids = secupress_get_bad_username_ids();
	}
	add_action( 'pre_get_users', 'secupress_bad_username_custom_modify_user_query' );

	if ( ! empty( $_user_ids ) ) {
		$query->set( 'include', $_user_ids );
	}
	return $query;
}

add_filter( 'body_class', 'secupress_usernames_security_body_class', 100 );
/**
  * Filter body_class in order to hide User ID and User nicename
  * 
  * @since 2.2.6
  * @author Roch Daniel, Julio Potier
  * 
  * @param (array) $classes
  * 
  * @return (array)
  */
function secupress_usernames_security_body_class( $classes ) {
	if ( is_author() ) {
		$current_auth = get_query_var( 'author_name' ) ? get_user_by( 'slug', get_query_var( 'author_name' ) ) : get_userdata( get_query_var( 'author' ) );
		$disallowed   = [];
		$disallowed[] = 'author-' . $current_auth->ID;
		$disallowed[] = 'author-' . $current_auth->user_nicename;
		$classes      = array_diff( $classes, $disallowed );
	}
	return $classes;
}


add_action( 'authenticate', 'secupress_pro_same_usernames_on_login', 22, 3 );
/**
 * Check the login same as nickname/display_name on login
 *
 * @since 2.3.17
 * @author Julio Potier
 * 
 * @param (mixed) $raw_user
 * @param (string) $username
 * 
 * @return (mixed) $raw_user Can also display a form to ask a new strong password
 **/
function secupress_pro_same_usernames_on_login( $raw_user, $username, $password ) {
	if ( ! secupress_get_module_option( 'blacklist-logins_lexicomatisation', 0, 'users-login' ) ) {
		return $raw_user;
	}
	if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
		return $raw_user;
	}
	$which = secupress_pro_same_usernames_get_which( $raw_user );
	if ( is_a( $raw_user, 'WP_User' ) && ! empty( $which ) ) {
		if ( ! empty( trim( $raw_user->first_name ) ) || ! empty( trim( $raw_user->last_name ) ) ) {
			$user_name = trim( trim( $raw_user->first_name ) . ' ' . trim( $raw_user->last_name ) );
			if ( $user_name ) {
				if ( isset( $which['nickname'] ) ) {
					$raw_user->nickname     = $user_name;
				}
				if ( isset( $which['display_name'] ) ) {
					$raw_user->display_name = $user_name;
				}
				if ( isset( $which['nicename'] ) ) {
					$raw_user->nicename     = $user_name;
				}
				secupress_add_transient_notice( sprintf( _nx( '%1$s updated as %2$s.', '%1$s updated as %2$s.', count( $which ), 'a nickname', 'secupress' ), wp_sprintf_l( '%s', $which ), secupress_tag_me( esc_html( $user_name ), 'strong' ) ), 'updated', 'nickname-updated' );
				wp_update_user( $raw_user );
			}
		}
		$which = secupress_pro_same_usernames_get_which( $raw_user );
		if ( empty( $which ) ) {
			return $raw_user;
		}
	}
	if ( is_a( $raw_user, 'WP_User' ) && empty( $which ) ) {
		return $raw_user;
	}

	$update_user = false;

	if ( ! is_a( $raw_user, 'WP_User' ) ) {
		$tmp_user   = secupress_get_user_by( $username );
		if ( ! is_a( $tmp_user, 'WP_User' ) ) {
			return $raw_user;
		}
		$user_token = secupress_pro_same_usernames_get_user_option( 'token', $tmp_user->ID );
		$user_token = ! $user_token ? '' : $user_token;
		$user_db    = secupress_pro_same_usernames_get_user_from_meta_value( 'token', $password );
		$time       = secupress_pro_same_usernames_get_user_option( 'timeout', $tmp_user->ID );

		if ( ! is_a( $tmp_user, 'WP_User' ) || ! $user_db || $user_db !== $tmp_user->ID || ! hash_equals( $user_token, $password ) ) {
			return $raw_user;
		}

		if ( $time < time() ) {
			$error = __( 'Too much time has elapsed between the first step and now.', 'secupress' );
		} else {
			$update_user = true;
			$raw_user    = $tmp_user;
		}

	}

	$new_token  = wp_hash( wp_generate_password( 32, false ) );
	secupress_pro_same_usernames_update_user_option( 'token', $new_token, $raw_user->ID );
	secupress_pro_same_usernames_update_user_option( 'timeout', time() + ( 10 * MINUTE_IN_SECONDS ), $raw_user->ID );

	$nonce      = 'secupress-new-name-' . $raw_user->ID;
	$error      = '';

	// A new name is submitted.
	$which = secupress_pro_same_usernames_get_which( $raw_user );
	if ( isset( $_POST['name'] ) && $update_user ) {

		if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], $nonce ) ) {
			secupress_die( __( 'Something went wrong.', 'secupress' ), '', [ 'force_die' => true, 'context' => 'same_usernames_on_login', 'attack_type' => 'login' ] );
		}
		$user_name = trim( $_POST['name'] );
		if ( empty( $user_name ) ) {
			$error = __( 'Nickname required.', 'secupress' );
		}
		if ( $raw_user->user_login === $user_name ) {
			$error = __( 'A nickname different from your login is required.', 'secupress' );
		}
		if ( get_user_by( 'login', $user_name ) ) {
			$error = __( 'A nickname different from another user‘s login is required.', 'secupress' );
		}
		if ( isset( $which['nicename'] ) && sanitize_user( $user_name, true ) !== $user_name ) {
			$error  = sprintf( __( 'The username %1$s is invalid because it uses illegal characters. Spot the differences: %2$s.', 'secupress' ), secupress_tag_me( esc_html( $user_name ), 'strong' ), secupress_tag_me( esc_html( sanitize_user( $user_name, true ) ), 'strong' ) );
			$error .= '<p>' . sprintf( __( 'Allowed characters: %s.', 'secupress' ), '<code>A-Z, a-z, 0-9, _, ., -, @</code>' ) . '</p>';
		}
		if ( ! $error ) {
			if ( isset( $which['nickname'] ) ) {
				$raw_user->nickname     = $user_name;
			}
			if ( isset( $which['display_name'] ) ) {
				$raw_user->display_name = $user_name;
			}
			if ( isset( $which['nicename'] ) ) {
				$raw_user->user_nicename = $user_name;
			}
			secupress_add_transient_notice( sprintf( _nx( '%1$s updated as %2$s.', '%1$s updated as %2$s.', count( $which ), 'a nickname', 'secupress' ), wp_sprintf_l( '%s', $which ), secupress_tag_me( esc_html( $user_name ), 'strong' ) ), 'updated', 'nickname-updated' );
			wp_update_user( $raw_user );
			return $raw_user;
		}
	}
	ob_start();
	?>
	<form class="wrap" method="post">
		<h1><?php echo wp_sprintf_l( '%l', $which ) . ' ' . __( 'Security Update', 'secupress' ); ?></h1>
		<h3><?php printf( _n( 'Your current %s is the same as your login.<br>You must update it to successfully log in.', 'Your current %s are the same as your login.<br>You must update them to successfully log in.', count( $which ), 'secupress' ), wp_sprintf_l( '%l', $which ) ); ?></h3>
		<?php echo $error ? '<p class="error">' . $error . '</p>' : ''; ?>
		<table class="form-table <?php echo is_rtl() ? 'rtl' : 'ltr'; ?>" style="width: 100%">
			<tr id="nickname" class="secupress-user-name-wrap">
				<td>
					<div class="wp-pwd">
						<span class="nickname-input-wrapper">
							<input type="text" name="name" id="secupress-name" class="regular-text" value="" autocomplete="off" />
						</span>
					</div>
					<input type="submit" id="thesubmit" class="secupress-button secupress-button-primary disabled"/>
				</td>
			</tr>
		</table>
		<?php
		wp_nonce_field( $nonce );
		// "pwd" field act as the token wrapper
		?>
		<input type="hidden" name="log" value="<?php echo esc_attr( $username ); ?>" />
		<input type="hidden" name="pwd" value="<?php echo esc_attr( $new_token ); ?>" />

		<p class="wrap"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php printf( __( '&larr; Cancel and go back to %s', 'secupress' ), get_bloginfo( 'name' ) ); ?></a></p>
	</form>
	<?php
	$raw_user = null;
	$content  = ob_get_contents();
	ob_clean();
	secupress_action_page( __( 'Please change your profile names', 'secupress' ), $content, [] );
}


add_action( 'admin_init', 'secupress_lexicomatisation_set_expert' );
/**
 * Add our module to the global
 *
 * @since 2.3.17
 * @author Julio Potier
 **/
function secupress_lexicomatisation_set_expert() {
	if ( ! secupress_get_module_option( 'blacklist-logins_lexicomatisation', 0, 'users-login' ) ) {
		return;
	}
	$GLOBALS['SECUPRESS_EXPERT_MODULES_ON']['lexicomatisation'] = true;
}

/**
 * Get a same usernames option name
 * 
 * @since 2.3.17
 * @author Julio Potier
 * 
 * @param (string) $option
 * 
 * @return (string)
 **/
function secupress_pro_same_usernames_get_option_name( $option ) {
	global $wpdb;
	return sprintf( '%ssecupress_same_usernames_%s', $wpdb->prefix, $option );
}

/**
 * Get a user_id from a meta value
 *
 * @since 2.3.17
 * @author Julio Potier
 *
 * @param (string) $key
 * @param (string) $value
 *
 * @return (array)
 */
function secupress_pro_same_usernames_get_user_from_meta_value( $key, $value ) {
	global $wpdb;
	$res = secupress_cache_data( "$key|$value" );
	if ( $res ) {
		return $res;
	}
	$res = (int) $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM $wpdb->usermeta WHERE meta_key = %s AND meta_value = %s", secupress_pro_same_usernames_get_option_name( $key ), $value ) );
	secupress_cache_data( "$key|$value", $res );
	return $res;
}

/**
 * Get a same usernames user option easily
 * 
 * @since 2.3.17
 * @author Julio Potier
 * 
 * @param (string) $option
 * @param (int) $uid 0 = current_user
 * 
 * @return (string)
 **/
function secupress_pro_same_usernames_get_user_option( $option, $uid = 0 ) {
	$current_user = wp_get_current_user();
	$uid          = $uid ?? $current_user->ID;
	return get_user_option( 'secupress_same_usernames_' . $option, $uid );
}

/**
 * Update a same usernames user option easily
 * 
 * @since 2.3.17
 * @author Julio Potier
 * 
 * @param (string) $option
 * @param (string) $value
 * @param (int) $uid 0 = current_user
 * 
 * @return (string)
 **/
function secupress_pro_same_usernames_update_user_option( $option, $value, $uid = 0 ) {
	$current_user = wp_get_current_user();
	$user_id      = $uid ? $uid : $current_user->ID;
	return update_user_option( $user_id, 'secupress_same_usernames_' . $option, $value );
}

add_filter( 'pre_user_display_name', 'secupress_usernames_security_name_filter' );
add_filter( 'pre_user_nickname', 'secupress_usernames_security_name_filter' );
/**
  * When a new user is created or modified, change User Nicename, Nickname and Display Name
  * 
  * @since 2.2.6
  * @author Julio Potier
  *
  * @param (string) $name
  * 
  * @return (string) $name
  */
function secupress_usernames_security_name_filter( $name ) {
	global $current_user;

	if ( ! secupress_get_module_option( 'blacklist-logins_lexicomatisation', 0, 'users-login' ) ) {
		return $name;
	}
	if ( ! is_a( $current_user, 'WP_User' ) ) {
		return $name;
	}
	if ( $name === $current_user->user_login && 'pre_user_nickname' === current_filter() ) {
		secupress_die( __( 'A nickname different from your login is required.', 'secupress' ), __( 'Invalid User Data', 'secupress' ), [ 'force_die' => true, 'back_link' => true ] );
	}
	if ( $name === $current_user->user_login && 'pre_user_display_name' === current_filter() ) {
		secupress_die( __( 'A display name different from your login is required.', 'secupress' ), __( 'Invalid User Data', 'secupress' ), [ 'force_die' => true, 'back_link' => true  ] );
	}

	if ( get_user_by( 'login', $name ) && 'pre_user_nickname' === current_filter() ) {
		secupress_die( __( 'A nickname different from another user‘s login is required.', 'secupress' ), __( 'Invalid User Data', 'secupress' ), [ 'force_die' => true, 'back_link' => true  ] );
	}

	if ( get_user_by( 'login', $name ) && 'pre_user_display_name' === current_filter() ) {
		secupress_die( __( 'A display name different from another user‘s login is required.', 'secupress' ), __( 'Invalid User Data', 'secupress' ), [ 'force_die' => true, 'back_link' => true  ] );
	}

	return $name;
}

/**
  * Return the needed public names identical to the user's login
  * 
  * @since 2.3.17
  * @author Julio Potier
  * 
  * @param (WP_User) $user
  * 
  * @return (array)
  */
function secupress_pro_same_usernames_get_which( $user ) {
	if ( ! is_a( $user, 'WP_User' ) ) {
		return [];
	}
	$result = [];

	if ( $user->user_login === $user->nickname ) {
		$result['nickname']     = __( 'Nickname', 'secupress' );
	}
	if ( $user->user_login === $user->display_name ) {
		$result['display_name'] = __( 'Display Name', 'secupress' );
	}
	if ( $user->user_login === $user->user_nicename ) {
		$result['nicename']     = __( 'Nicename', 'secupress' );
	}
	return $result;
}
