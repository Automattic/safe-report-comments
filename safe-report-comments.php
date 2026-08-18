<?php
/**
 * Plugin Name: Safe Report Comments
 * Plugin Script: safe-report-comments.php
 * Plugin URI: http://wordpress.org/extend/plugins/safe-report-comments/
 * Description: This script gives visitors the possibility to flag/report a comment as inapproriate.
 * After reaching a threshold the comment is moved to moderation. If a comment is approved once by a moderator future reports will be ignored.
 * Version: 0.4.1
 * Author: Thorsten Ott, Daniel Bachhuber, Automattic
 * Author URI: http://automattic.com
 * License: GPLv2
 *
 * @package Safe_Report_Comments
 */

if ( ! class_exists( 'Safe_Report_Comments' ) ) {
	require_once __DIR__ . '/class-safe-report-comments.php';
}

if ( ! defined( 'no_autostart_safe_report_comments' ) ) {
	$safe_report_comments = new Safe_Report_Comments();
}
