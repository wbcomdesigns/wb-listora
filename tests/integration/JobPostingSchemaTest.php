<?php
/**
 * JobPosting structured data carries the properties Google requires.
 *
 * @package WBListora\Tests\Integration
 */

namespace WBListora\Tests\Integration;

use WP_UnitTestCase;
use WBListora\Core\Listing_Type_Registry;
use WBListora\Schema\Schema_Generator;

/**
 * Card 10368644969: datePosted was never emitted and `title` was missing.
 *
 * @group listora
 * @group schema
 */
class JobPostingSchemaTest extends WP_UnitTestCase {

	public function test_job_posting_has_title_and_date_posted() {
		$saved = Listing_Type_Registry::instance()->save_type(
			'qa-job',
			array(
				'props' => array(
					'name'        => 'QA Job',
					'schema_type' => 'JobPosting',
				),
			)
		);
		$this->assertNotWPError( $saved );
		Listing_Type_Registry::instance()->flush();
		Listing_Type_Registry::instance()->init();

		$id = self::factory()->post->create(
			array(
				'post_type'     => 'listora_listing',
				'post_status'   => 'publish',
				'post_title'    => 'Senior Barista',
				'post_date_gmt' => '2026-10-01 09:30:00',
				'post_date'     => '2026-10-01 05:30:00',
			)
		);
		wp_set_object_terms( $id, 'qa-job', 'listora_listing_type' );

		$data = Schema_Generator::for_listing( $id )->get_data();

		$this->assertSame( 'JobPosting', $data['@type'] );
		$this->assertSame( 'Senior Barista', $data['title'] );
		$this->assertSame( '2026-10-01T09:30:00+00:00', $data['datePosted'] );
	}
}
