<?php
/**
 * Tests that only ordinary public comments can be reported.
 *
 * @package Safe_Report_Comments
 */

/**
 * Tests for the report-target validation guard.
 *
 * @coversDefaultClass \Safe_Report_Comments
 */
class Safe_Report_Comments_Reportable_Comment_Test extends WP_UnitTestCase {

	/**
	 * Plugin instance under test.
	 *
	 * @var Safe_Report_Comments
	 */
	protected $plugin;

	/**
	 * Published post used as the parent for the test comments.
	 *
	 * @var int
	 */
	protected $post_id;

	/**
	 * Create the plugin instance and a public parent post.
	 */
	public function set_up() {
		parent::set_up();

		$this->plugin  = new Safe_Report_Comments( false );
		$this->post_id = self::factory()->post->create();
	}

	/**
	 * An ordinary comment on a public post is reportable.
	 *
	 * @covers ::is_reportable_comment
	 */
	public function test_ordinary_comment_is_reportable(): void {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => $this->post_id,
			)
		);

		$this->assertTrue( $this->plugin->is_reportable_comment( $comment_id ) );
	}

	/**
	 * A comment stored with an explicit 'comment' type is reportable.
	 *
	 * @covers ::is_reportable_comment
	 */
	public function test_explicit_comment_type_is_reportable(): void {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => $this->post_id,
				'comment_type'    => 'comment',
			)
		);

		$this->assertTrue( $this->plugin->is_reportable_comment( $comment_id ) );
	}

	/**
	 * Records that merely share the comments table are not reportable.
	 *
	 * Regression: WooCommerce stores internal order notes as comment_type
	 * 'order_note'. They never carry a public report link, so the anonymous
	 * flagging workflow must refuse to move them to moderation.
	 *
	 * @covers ::is_reportable_comment
	 * @dataProvider data_non_reportable_types
	 *
	 * @param string $comment_type Comment type that must be rejected.
	 */
	public function test_non_reportable_comment_types_are_rejected( string $comment_type ): void {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => $this->post_id,
				'comment_type'    => $comment_type,
			)
		);

		$this->assertFalse( $this->plugin->is_reportable_comment( $comment_id ) );
	}

	/**
	 * Comment types that must never be reportable.
	 *
	 * @return array[]
	 */
	public function data_non_reportable_types(): array {
		return array(
			'WooCommerce order note' => array( 'order_note' ),
			'pingback'               => array( 'pingback' ),
			'trackback'              => array( 'trackback' ),
		);
	}

	/**
	 * A non-existent comment ID is not reportable.
	 *
	 * @covers ::is_reportable_comment
	 */
	public function test_missing_comment_is_not_reportable(): void {
		$this->assertFalse( $this->plugin->is_reportable_comment( PHP_INT_MAX ) );
	}

	/**
	 * Site owners can opt additional comment types in via the types filter.
	 *
	 * @covers ::is_reportable_comment
	 */
	public function test_reportable_types_filter_allows_opt_in(): void {
		$note_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => $this->post_id,
				'comment_type'    => 'order_note',
			)
		);

		$callback = static function ( array $types ): array {
			$types[] = 'order_note';
			return $types;
		};

		add_filter( 'safe_report_comments_reportable_comment_types', $callback );
		$is_reportable = $this->plugin->is_reportable_comment( $note_id );
		remove_filter( 'safe_report_comments_reportable_comment_types', $callback );

		$this->assertTrue( $is_reportable );
	}

	/**
	 * The final decision filter can veto an otherwise reportable comment.
	 *
	 * @covers ::is_reportable_comment
	 */
	public function test_is_reportable_comment_filter_can_veto(): void {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => $this->post_id,
			)
		);

		add_filter( 'safe_report_comments_is_reportable_comment', '__return_false' );
		$is_reportable = $this->plugin->is_reportable_comment( $comment_id );
		remove_filter( 'safe_report_comments_is_reportable_comment', '__return_false' );

		$this->assertFalse( $is_reportable );
	}
}
