<?php
/**
 * The plain-text part of every email reads as plain text.
 *
 * @package WBListora\Tests\Integration
 */

namespace WBListora\Tests\Integration;

use WP_UnitTestCase;
use WBListora\Workflow\Email_Body_Formatter;

/**
 * @group listora
 */
class EmailBodyFormatterTest extends WP_UnitTestCase {

	public function test_entities_decoded_and_blank_table_lines_dropped() {
		$html = "<table><tr><td>\n   \n   \n<p>Your review of &quot;Tom &amp; Jerry&#8217;s&quot; reached 1 helpful vote!</p>\n   \n   \n   \n<a href=\"http://x.test/r\">View</a></td></tr></table>";

		$this->assertSame(
			"Your review of \"Tom & Jerry\u{2019}s\" reached 1 helpful vote!\n\nView (http://x.test/r)",
			Email_Body_Formatter::html_to_text( $html )
		);
	}
}
