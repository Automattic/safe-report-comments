<?php

/**
 * Base unit test class for Safe Report Comments
 */
class SafeReportComments_TestCase extends WP_UnitTestCase {
	public function setUp() {
		parent::setUp();

		global $safe_report_comments;
		$this->_toc = $safe_report_comments;
	}
}
