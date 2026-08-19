<?php
/**
 * Tests for the client-positioned report link placement.
 *
 * @package Safe_Report_Comments
 */

/**
 * Covers Safe_Report_Comments::append_flagging_link().
 */
class Safe_Report_Comments_Append_Flagging_Link_Test extends WP_UnitTestCase {

	/**
	 * Plugin instance under test, created with automatic attachment disabled.
	 *
	 * @var Safe_Report_Comments
	 */
	private $plugin;

	/**
	 * An ordinary, reportable comment ID.
	 *
	 * @var int
	 */
	private $comment_id;

	/**
	 * Create the plugin instance and a reportable comment.
	 */
	public function set_up() {
		parent::set_up();

		$this->plugin = new Safe_Report_Comments( false );

		$this->comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::factory()->post->create(),
				'comment_content' => 'A reportable comment.',
			)
		);
	}

	/**
	 * A reportable comment gets the hidden report wrapper, tagged with its ID.
	 */
	public function test_appends_wrapper_for_reportable_comment() {
		$output = $this->plugin->append_flagging_link( 'Original text.', get_comment( $this->comment_id ) );

		$this->assertStringStartsWith( 'Original text.', $output );
		$this->assertStringContainsString( 'class="safe-comments-report-link"', $output );
		$this->assertStringContainsString( 'data-comment-id="' . $this->comment_id . '"', $output );
		$this->assertStringContainsString( 'style="display:none;"', $output );
		$this->assertStringContainsString( 'safe_report_comments_flag_comment(', $output );
	}

	/**
	 * Non-reportable comment types, such as pingbacks, never receive a report link.
	 */
	public function test_skips_non_reportable_comment_type() {
		$pingback_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::factory()->post->create(),
				'comment_type'    => 'pingback',
				'comment_content' => 'A pingback.',
			)
		);

		$output = $this->plugin->append_flagging_link( 'Original text.', get_comment( $pingback_id ) );

		$this->assertSame( 'Original text.', $output );
	}

	/**
	 * The report link is not appended within feeds.
	 */
	public function test_skips_in_feed() {
		$this->go_to( '/?feed=rss2' );

		$output = $this->plugin->append_flagging_link( 'Original text.', get_comment( $this->comment_id ) );

		$this->assertSame( 'Original text.', $output );
	}
}
