<?php
/**
 * Plugin Name: Safe Report Comments
 * Plugin URI: https://wordpress.org/plugins/safe-report-comments/
 * Description: Gives visitors the possibility to flag a comment as inappropriate. After reaching a threshold the comment is moved to moderation. If a comment is approved once by a moderator, future reports are ignored.
 * Version: 0.5.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: Thorsten Ott, Daniel Bachhuber, Automattic
 * Author URI: https://automattic.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: safe-report-comments
 *
 * @package Safe_Report_Comments
 */

if ( ! class_exists( 'Safe_Report_Comments' ) ) {
	require_once __DIR__ . '/class-safe-report-comments.php';
}

if ( ! defined( 'no_autostart_safe_report_comments' ) ) {
	$safe_report_comments = new Safe_Report_Comments();
}
