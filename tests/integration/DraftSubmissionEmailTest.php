<?php
/**
 * An autosaved draft does not email the admin "awaiting your review"; the real submit does, once.
 *
 * @package WBListora\Tests
 */

use WBListora\Workflow\Notifications;

/**
 * @group listora
 */
class DraftSubmissionEmailTest extends WP_UnitTestCase {

	/** @var string[] */
	private array $subjects = array();

	public function set_up(): void {
		parent::set_up();
		$this->subjects = array();
		add_filter(
			'pre_wp_mail',
			function ( $short, $atts ) {
				$this->subjects[] = (string) $atts['subject'];
				return true;
			},
			10,
			2
		);
	}

	private function submit( string $status ): void {
		$author  = self::factory()->user->create();
		$listing = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => $status, 'post_author' => $author ) );
		( new Notifications() )->listing_submitted( $listing, $status, null, array() );
	}

	public function test_a_draft_save_sends_nothing() {
		$this->submit( 'draft' );

		$this->assertCount( 0, $this->subjects );
	}

	public function test_the_real_submit_sends_one() {
		$this->submit( 'pending' );

		$this->assertCount( 1, $this->subjects );
	}
}
