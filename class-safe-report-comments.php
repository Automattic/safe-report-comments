<?php
/**
 * Loads the primary plugin class
 *
 * @package Safe_Report_Comments
 *
 * @phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
 * @phpcs:disable WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
 * @phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */

/**
 * The main plugin class.
 */
class Safe_Report_Comments {

	/**
	 * Prefix to use with options, etc.
	 *
	 * @var string
	 */
	private $plugin_prefix = 'srcmnt';

	/**
	 * Stores admin notices to be displayed.
	 *
	 * @var array
	 */
	private $admin_notices = array();

	/**
	 * Nonce key.
	 *
	 * @var string
	 */
	private $nonce_key = 'flag_comment_nonce';

	/**
	 * Whether to automatically add the flagging link or not.
	 *
	 * Used in combination with the `no_autostart_safe_report_comments` constant.
	 *
	 * @var bool
	 */
	private $auto_init = true;

	/**
	 * Cookie name.
	 *
	 * @var string
	 */
	private $storagecookie = 'sfrc_flags';

	/**
	 * Plugin URL
	 *
	 * @todo This is only used one, perhaps simplify.
	 *
	 * @var bool|string
	 */
	public $plugin_url = false;

	/**
	 * "Thank you" message after comment report.
	 *
	 * @todo Refactor messages so we can add i18n.
	 *
	 * @var string
	 */
	public $thank_you_message = 'Thank you for your feedback. We will look into it.';

	/**
	 * Message shown after flagging if nonce is invalid.
	 *
	 * @var string
	 */
	public $invalid_nonce_message = 'It seems you already reported this comment. <!-- nonce invalid -->';

	/**
	 * Message shown after flagging if comment ID is invalid.
	 *
	 * @var string
	 */
	public $invalid_values_message = 'Cheating huh? <!-- invalid values -->';

	/**
	 * Message shown after flagging if comment has already been flagged by user.
	 *
	 * @var string
	 */
	public $already_flagged_message = 'It seems you already reported this comment. <!-- already flagged -->';

	/**
	 * Message shown before flagging if comment has already been flagged by user.
	 *
	 * @var string
	 */
	public $already_flagged_note = '<!-- already flagged -->'; // displayed instead of the report link when a comment was flagged.

	/**
	 * Variable names for various messages
	 *
	 * @todo Can probably simplify by having a single keyed array with the message.
	 *
	 * @var array
	 */
	public $filter_vars = array( 'thank_you_message', 'invalid_nonce_message', 'invalid_values_message', 'already_flagged_message', 'already_flagged_note' );

	/**
	 * Flagging attempts permitted without cookies.
	 *
	 * Amount of possible attempts transient hits per comment before a COOKIE enabled negative check is considered invalid.
	 * Transient hits will be counted up per ip any time a user flags a comment.
	 * This number should be always lower than your threshold to avoid manipulation.
	 *
	 * @var int
	 */
	public $no_cookie_grace = 3;

	/**
	 * Cookie life in seconds
	 *
	 * After this interval, a user can re-report a comment.
	 *
	 * @var int
	 */
	public $cookie_lifetime = WEEK_IN_SECONDS;

	/**
	 * Transient life in seconds
	 *
	 * Used as fallback if cookies aren't available.
	 *
	 * @var int
	 */
	public $transient_lifetime = DAY_IN_SECONDS;

	/**
	 * General plugin initing
	 *
	 * @param bool $auto_init Whether to automatically add the flagging link or not.
	 */
	public function __construct( $auto_init = true ) {
		$this->admin_notices = get_transient( $this->plugin_prefix . '_notices' );
		if ( ! is_array( $this->admin_notices ) ) {
			$this->admin_notices = array();
		}
		$this->admin_notices = array_unique( $this->admin_notices );
		$this->auto_init     = $auto_init;

		if ( ! is_admin() || ( defined( 'DOING_AJAX' ) && true === DOING_AJAX ) ) {
			add_action( 'init', array( $this, 'frontend_init' ) );
		} elseif ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'backend_init' ) );
		}
		add_action( 'comment_unapproved_to_approved', array( $this, 'mark_comment_moderated' ), 10, 1 );

		/**
		 * Apply some filters to easily alter the frontend messages. Example:
		 * add_filter( 'safe_report_comments_thank_you_message', 'alter_message' );
		 */
		foreach ( $this->filter_vars as $var ) {
			$this->{$var} = apply_filters( 'safe_report_comments_' . $var, $this->{$var} );
		}
	}

	/**
	 * Prevent __destruct
	 */
	public function __destruct() {
	}

	/**
	 * Initialize backend functions
	 * - register_admin_panel
	 * - admin_header
	 */
	public function backend_init() {
		do_action( 'safe_report_comments_backend_init' );

		add_settings_field( $this->plugin_prefix . '_enabled', __( 'Allow comment flagging', 'safe-report-comments' ), array( $this, 'comment_flag_enable' ), 'discussion', 'default' );
		register_setting( 'discussion', $this->plugin_prefix . '_enabled' );

		if ( ! $this->is_enabled() ) {
			return;
		}

		add_settings_field( $this->plugin_prefix . '_threshold', __( 'Flagging threshold', 'safe-report-comments' ), array( $this, 'comment_flag_threshold' ), 'discussion', 'default' );
		register_setting( 'discussion', $this->plugin_prefix . '_threshold', array( $this, 'check_threshold' ) );
		add_filter( 'manage_edit-comments_columns', array( $this, 'add_comment_reported_column' ) );
		add_action( 'manage_comments_custom_column', array( $this, 'manage_comment_reported_column' ), 10, 2 );

		add_action( 'admin_menu', array( $this, 'register_admin_panel' ) );
		add_action( 'admin_head', array( $this, 'admin_header' ) );
	}

	/**
	 * Initialize frontend functions
	 */
	public function frontend_init() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		if ( ! $this->plugin_url ) {
			$this->plugin_url = plugins_url( false, __FILE__ );
		}

		do_action( 'safe_report_comments_frontend_init' );

		add_action( 'wp_ajax_safe_report_comments_flag_comment', array( $this, 'flag_comment' ) );
		add_action( 'wp_ajax_nopriv_safe_report_comments_flag_comment', array( $this, 'flag_comment' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'action_enqueue_scripts' ) );

		if ( $this->auto_init ) {
			add_filter( 'comment_reply_link', array( $this, 'add_flagging_link' ) );
		}
		add_action( 'comment_report_abuse_link', array( $this, 'print_flagging_link' ) );

		add_action( 'template_redirect', array( $this, 'add_test_cookie' ) ); // need to do this at template_redirect because is_feed isn't available yet.
	}

	/**
	 * Enqueues scripts on front end.
	 */
	public function action_enqueue_scripts() {

		// Use home_url() if domain mapped to avoid cross-domain issues.
		if ( home_url() != site_url() ) {
			$ajaxurl = home_url( '/wp-admin/admin-ajax.php' );
		} else {
			$ajaxurl = admin_url( 'admin-ajax.php' );
		}

		$ajaxurl = apply_filters( 'safe_report_comments_ajax_url', $ajaxurl );

		// @todo Confirm if this script can be loaded in the footer.
		wp_enqueue_script( $this->plugin_prefix . '-ajax-request', $this->plugin_url . '/js/ajax.js', array( 'jquery' ), '1.0', false );
		wp_localize_script( $this->plugin_prefix . '-ajax-request', 'SafeCommentsAjax', array( 'ajaxurl' => $ajaxurl ) ); // slightly dirty but needed due to possible problems with mapped domains.
	}

	/**
	 * Set a cookie now to see if they are supported by the browser.
	 * Don't add cookie if it's already set; and don't do it for feeds.
	 */
	public function add_test_cookie() {
		if ( ! is_feed() && ! isset( $_COOKIE[ TEST_COOKIE ] ) ) {
			@setcookie( TEST_COOKIE, 'WP Cookie check', 0, COOKIEPATH, COOKIE_DOMAIN );
			if ( SITECOOKIEPATH != COOKIEPATH ) {
				@setcookie( TEST_COOKIE, 'WP Cookie check', 0, SITECOOKIEPATH, COOKIE_DOMAIN );
			}
		}
	}

	/**
	 * Add necessary header scripts.
	 * Currently only used for admin notices.
	 */
	public function admin_header() {
		// print admin notice in case of notice strings given.
		if ( ! empty( $this->admin_notices ) ) {
				add_action( 'admin_notices', array( $this, 'print_admin_notice' ) );
		}
		echo '<style type="text/css">.column-comment_reported { width: 8em; }</style>';
	}

	/**
	 * Add admin error messages.
	 *
	 * @param string $message Admin notice text.
	 */
	protected function add_admin_notice( $message ) {
		$this->admin_notices[] = $message;
		set_transient( $this->plugin_prefix . '_notices', $this->admin_notices, 3600 );
	}

	/**
	 * Print a notification / error msg.
	 */
	public function print_admin_notice() {
		?>
		<div id="message" class="updated fade"><h3><?php esc_html_e( 'Safe Comments:', 'safe-report-comments' ); ?></h3>
		<?php

		foreach ( (array) $this->admin_notices as $notice ) {
			?>
				<p><?php echo esc_html( $notice ); ?></p>
			<?php
		}
		?>
		</div>
		<?php
		$this->admin_notices = array();
		delete_transient( $this->plugin_prefix . '_notices' );
	}

	/**
	 * Callback for settings field.
	 */
	public function comment_flag_enable() {
		$enabled = $this->is_enabled();
		?>
		<label for="<?php echo esc_attr( $this->plugin_prefix ); ?>_enabled">
			<input name="<?php echo esc_attr( $this->plugin_prefix ); ?>_enabled" id="<?php echo esc_attr( $this->plugin_prefix ); ?>_enabled" type="checkbox" value="1" <?php checked( $enabled ); ?> />
			<?php esc_html_e( 'Allow your visitors to flag a comment as inappropriate.', 'safe-report-comments' ); ?>
		</label>
		<?php
	}

	/**
	 * Callback for settings field.
	 */
	public function comment_flag_threshold() {
		$threshold = (int) get_option( $this->plugin_prefix . '_threshold' );
		?>
		<label for="<?php echo esc_attr( $this->plugin_prefix ); ?>_threshold">
			<input size="2" name="<?php echo esc_attr( $this->plugin_prefix ); ?>_threshold" id="<?php echo esc_attr( $this->plugin_prefix ); ?>_threshold" type="text" value="<?php echo esc_attr( $threshold ); ?>" />
			<?php esc_html_e( 'Amount of user reports needed to send a comment to moderation?', 'safe-report-comments' ); ?>
		</label>
		<?php
	}

	/**
	 * Check if the functionality is enabled or not.
	 */
	public function is_enabled() {
		$enabled = get_option( $this->plugin_prefix . '_enabled' );
		if ( 1 == $enabled ) {
			$enabled = true;
		} else {
			$enabled = false;
		}
		return $enabled;
	}

	/**
	 * Validate threshold, callback for settings field.
	 *
	 * @param int $value Threshold value.
	 * @return int Valid threshold value between 1 and 100.
	 */
	public function check_threshold( $value ) {
		if ( (int) $value <= 0 || (int) $value > 100 ) {
			$this->add_admin_notice( __( 'Please revise your flagging threshold and enter a number between 1 and 100', 'safe-report-comments' ) );
		}
		return (int) $value;
	}

	/**
	 * Helper function to serialize cookie values.
	 *
	 * @param array $value Cookie data.
	 * @return string Encoded cookie data.
	 */
	private function serialize_cookie( $value ) {
		$value = $this->clean_cookie_data( $value );
		return base64_encode( wp_json_encode( $value ) );
	}

	/**
	 * Helper function to unserialize cookie values.
	 *
	 * @param string $value Encoded cookie data.
	 * @return array Decoded cookie data.
	 */
	private function unserialize_cookie( $value ) {
		$data = json_decode( base64_decode( $value ) );
		return $this->clean_cookie_data( $data );
	}

	/**
	 * Validate cookie data for numeric values.
	 *
	 * @param mixed $data Given cookie data.
	 * @return array Cookie data array with only numeric keys and values.
	 */
	private function clean_cookie_data( $data ) {
		$clean_data = array();

		if ( ! is_array( $data ) ) {
			$data = array();
		}

		foreach ( $data as $comment_id => $count ) {
			if ( is_numeric( $comment_id ) && is_numeric( $count ) ) {
				$clean_data[ $comment_id ] = $count;
			}
		}

		return $clean_data;
	}

	/**
	 * Mark a comment as being moderated so it will not be autoflagged again.
	 *
	 * Called via comment transient from unapproved to approved.
	 *
	 * @param WP_Comment $comment Comment to mark.
	 */
	public function mark_comment_moderated( $comment ) {
		if ( isset( $comment->comment_ID ) ) {
			update_comment_meta( $comment->comment_ID, $this->plugin_prefix . '_moderated', true );
		}
	}

	/**
	 * Check if this comment was flagged by the user before.
	 *
	 * @param int $comment_id Comment to check.
	 * @return bool Whether comment has already been flagged by user.
	 */
	public function already_flagged( $comment_id ) {

		// check if cookies are enabled and use cookie store.
		if ( isset( $_COOKIE[ TEST_COOKIE ] ) ) {
			if ( isset( $_COOKIE[ $this->storagecookie ] ) ) {
				$data = $this->unserialize_cookie( sanitize_text_field( $_COOKIE[ $this->storagecookie ] ) );
				if ( is_array( $data ) && isset( $data[ $comment_id ] ) ) {
					return true;
				}
			}
		}

		$remote_addr = filter_input( INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP );
		$remote_addr = sanitize_text_field( $remote_addr );

		// in case we don't have cookies. fall back to transients, block based on IP/User Agent.
		$transient = get_transient( md5( $this->storagecookie . $remote_addr ) );
		if ( $transient ) {
			if (
				// check if no cookie and transient is set.
				( ! isset( $_COOKIE[ TEST_COOKIE ] ) && isset( $transient[ $comment_id ] ) ) ||
				// or check if cookies are enabled and comment is not flagged but transients show a relatively high number and assume fraud.
				( isset( $_COOKIE[ TEST_COOKIE ] ) && isset( $transient[ $comment_id ] ) && $transient[ $comment_id ] >= $this->no_cookie_grace )
				) {
					return true;
			}
		}
		return false;
	}

	/**
	 * Report a comment and send it to moderation if threshold is reached.
	 *
	 * @param int $comment_id Comment to mark.
	 */
	public function mark_flagged( $comment_id ) {
		$data = array();
		if ( isset( $_COOKIE[ TEST_COOKIE ] ) ) {
			if ( isset( $_COOKIE[ $this->storagecookie ] ) ) {
				$data = $this->unserialize_cookie( sanitize_text_field( $_COOKIE[ $this->storagecookie ] ) );
				if ( ! isset( $data[ $comment_id ] ) ) {
					$data[ $comment_id ] = 0;
				}
				$data[ $comment_id ]++;
				$cookie = $this->serialize_cookie( $data );
				@setcookie( $this->storagecookie, $cookie, time() + $this->cookie_lifetime, COOKIEPATH, COOKIE_DOMAIN );
				if ( SITECOOKIEPATH != COOKIEPATH ) {
					@setcookie( $this->storagecookie, $cookie, time() + $this->cookie_lifetime, SITECOOKIEPATH, COOKIE_DOMAIN );
				}
			} else {
				if ( ! isset( $data[ $comment_id ] ) ) {
					$data[ $comment_id ] = 0;
				}
				$data[ $comment_id ]++;
				$cookie = $this->serialize_cookie( $data );
				@setcookie( $this->storagecookie, $cookie, time() + $this->cookie_lifetime, COOKIEPATH, COOKIE_DOMAIN );
				if ( SITECOOKIEPATH != COOKIEPATH ) {
					@setcookie( $this->storagecookie, $cookie, time() + $this->cookie_lifetime, SITECOOKIEPATH, COOKIE_DOMAIN );
				}
			}
		}

		$remote_addr = filter_input( INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP );
		$remote_addr = sanitize_text_field( $remote_addr );

		// in case we don't have cookies. fall back to transients, block based on IP, shorter timeout to keep mem usage low and don't lock out whole companies.
		$transient = get_transient( md5( $this->storagecookie . $remote_addr ) );
		if ( ! $transient ) {
			set_transient( md5( $this->storagecookie . $remote_addr ), array( $comment_id => 1 ), $this->transient_lifetime );
		} else {
			if ( ! isset( $transient[ $comment_id ] ) ) {
				$transient[ $comment_id ] = 0;
			}
			$transient[ $comment_id ]++;
			set_transient( md5( $this->storagecookie . $remote_addr ), $transient, $this->transient_lifetime );
		}


		$threshold       = (int) get_option( $this->plugin_prefix . '_threshold' );
		$current_reports = get_comment_meta( $comment_id, $this->plugin_prefix . '_reported', true );
		$current_reports++;
		update_comment_meta( $comment_id, $this->plugin_prefix . '_reported', $current_reports );


		// we will not flag a comment twice. the moderator is the boss here.
		$already_reported  = get_comment_meta( $comment_id, $this->plugin_prefix . '_reported', true );
		$already_moderated = get_comment_meta( $comment_id, $this->plugin_prefix . '_moderated', true );
		if ( true == $already_reported && true == $already_moderated ) {
			// But maybe the boss wants to allow comments to be reflagged.
			if ( ! apply_filters( 'safe_report_comments_allow_moderated_to_be_reflagged', false ) ) {
				return;
			}
		}

		if ( $current_reports >= $threshold ) {
			do_action( 'safe_report_comments_mark_flagged', $comment_id );
			wp_set_comment_status( $comment_id, 'hold' );
		}
	}

	/**
	 * Die() with or without screen based on JS availability.
	 *
	 * @param string $message Message to print.
	 */
	private function cond_die( $message ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_REQUEST['no_js'] ) && true == (bool) $_REQUEST['no_js'] ) {
			wp_die( esc_html( $message ), esc_html__( 'Safe Report Comments Notice', 'safe-report-comments' ), array( 'response' => 200 ) );
		} else {
			die( esc_html( $message ) );
		}
	}

	/**
	 * Ajax callback to flag/report a comment.
	 *
	 * @todo Confirm this callback only receives POST data
	 */
	public function flag_comment() {
		if ( empty( $_REQUEST['comment_id'] ) || (int) $_REQUEST['comment_id'] != $_REQUEST['comment_id'] ) {
			$this->cond_die( $this->invalid_values_message );
		}

		$comment_id = (int) $_REQUEST['comment_id'];
		if ( $this->already_flagged( $comment_id ) ) {
			$this->cond_die( $this->already_flagged_message );
		}

		// checking if nonces help.
		if ( ! isset( $_REQUEST['sc_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_REQUEST['sc_nonce'] ), $this->plugin_prefix . '_' . $this->nonce_key ) ) {
			$this->cond_die( $this->invalid_nonce_message );
		} else {
			$this->mark_flagged( $comment_id );
			$this->cond_die( $this->thank_you_message );
		}
	}

	/**
	 * Print the link for flagging comments.
	 *
	 * @param int    $comment_id The comment ID.
	 * @param string $result_id  Used as attribute ID in markup.
	 * @param string $text       Text of link.
	 */
	public function print_flagging_link( $comment_id = '', $result_id = '', $text = 'Report comment' ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaping done in get_flagging_link
		echo $this->get_flagging_link( $comment_id, $result_id, $text );
	}

	/**
	 * Output Link to report a comment
	 *
	 * @param int    $comment_id The comment ID.
	 * @param string $result_id  Used as attribute ID in markup.
	 * @param string $text       Text of link.
	 */
	public function get_flagging_link( $comment_id = '', $result_id = '', $text = 'Report comment' ) {
		global $in_comment_loop;
		if ( empty( $comment_id ) && ! $in_comment_loop ) {
			return esc_html__( 'Wrong usage of print_flagging_link().', 'safe-report-comments' );
		}
		if ( empty( $comment_id ) ) {
			$comment_id = get_comment_ID();
		} else {
			$comment_id = (int) $comment_id;
			if ( ! get_comment( $comment_id ) ) {
				return esc_html__( 'This comment does not exist.', 'safe-report-comments' );
			}
		}
		if ( empty( $result_id ) ) {
			$result_id = 'safe-comments-result-' . $comment_id;
		}

		$result_id = apply_filters( 'safe_report_comments_result_id', $result_id );
		$text      = apply_filters( 'safe_report_comments_flagging_link_text', $text );

		$nonce  = wp_create_nonce( $this->plugin_prefix . '_' . $this->nonce_key );
		$params = array(
			'action'     => 'safe_report_comments_flag_comment',
			'sc_nonce'   => $nonce,
			'comment_id' => $comment_id,
			'result_id'  => $result_id,
			'no_js'      => true,
		);

		if ( $this->already_flagged( $comment_id ) ) {
			return esc_html( $this->already_flagged_note );
		}

		// @todo Confirm that $result_id is unnecessary in JS call (and its associated ajax callback).
		return apply_filters(
			'safe_report_comments_flagging_link',
			'
		<span id="' . esc_attr( $result_id ) . '"><a class="hide-if-no-js" href="javascript:void(0);" onclick="safe_report_comments_flag_comment( \'' . intval( $comment_id ) . '\', \'' . esc_js( $nonce ) . '\', \'' . esc_js( $result_id ) . '\');">' . esc_html( $text ) . '</a></span>'
		);
	}

	/**
	 * Callback function to automatically hook in the report link after the comment reply link.
	 * If you want to control the placement on your own define no_autostart_safe_report_comments in your functions.php file and initialize the class
	 * with $safe_report_comments = new Safe_Report_Comments( $auto_init = false );
	 *
	 * @param string $comment_reply_link Comment reply link markup.
	 * @return string Modified comment reply link markup.
	 */
	public function add_flagging_link( $comment_reply_link ) {
		if ( ! preg_match_all( '#^(.*)(<a.+class=["|\']comment-(reply|login)-link["|\'][^>]+>)(.+)(</a>)(.*)$#msiU', $comment_reply_link, $matches ) ) {
			return '<!-- safe-comments add_flagging_link not matching -->' . $comment_reply_link;
		}

		$comment_reply_link = $matches[1][0] . $matches[2][0] . $matches[4][0] . $matches[5][0] . '<span class="safe-comments-report-link">' . $this->get_flagging_link() . '</span>' . $matches[6][0];
		return apply_filters( 'safe_report_comments_comment_reply_link', $comment_reply_link );
	}

	/**
	 * Callback function to add the report counter to comments screen. Remove action manage_edit-comments_columns if not desired.
	 *
	 * @param array $comment_columns Comments screen columns.
	 * @return array Modified comments screen columns.
	 */
	public function add_comment_reported_column( $comment_columns ) {
		$comment_columns['comment_reported'] = _x( 'Reported', 'column name', 'safe-report-comments' );
		return $comment_columns;
	}

	/**
	 * Callback function to handle custom column. remove action manage_comments_custom_column if not desired.
	 *
	 * @param string $column_name Column name.
	 * @param int    $comment_id  Comment ID.
	 */
	public function manage_comment_reported_column( $column_name, $comment_id ) {
		switch ( $column_name ) {
			case 'comment_reported':
				$reports          = 0;
				$already_reported = get_comment_meta( $comment_id, $this->plugin_prefix . '_reported', true );
				if ( $already_reported > 0 ) {
					$reports = (int) $already_reported;
				}
				echo esc_html( $reports );
				break;
			default:
				break;
		}
	}

}
