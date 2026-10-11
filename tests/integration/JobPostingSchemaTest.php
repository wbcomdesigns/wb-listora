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

	/**
	 * Location, salary, employment type and remote flag use Google's shapes.
	 */
	public function test_job_posting_uses_google_shapes() {
		$job = \WBListora\Core\Listing_Type_Defaults::get_all()['job'];
		$this->assertNotWPError( Listing_Type_Registry::instance()->save_type( 'qa-job-shape', $job ) );
		Listing_Type_Registry::instance()->flush();
		Listing_Type_Registry::instance()->init();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'publish',
				'post_title'  => 'iOS Engineer',
			)
		);
		wp_set_object_terms( $id, 'qa-job-shape', 'listora_listing_type' );
		update_post_meta(
			$id,
			'_listora_address',
			array(
				'address' => '1 Main St',
				'city'    => 'New York',
				'country' => 'US',
			)
		);
		update_post_meta( $id, '_listora_employment_type', 'full-time' );
		update_post_meta( $id, '_listora_salary_min', 130000 );
		update_post_meta( $id, '_listora_salary_max', 175000 );
		update_post_meta( $id, '_listora_salary_type', 'yearly' );
		update_post_meta( $id, '_listora_remote_option', 'onsite' );

		$data = Schema_Generator::for_listing( $id )->get_data();

		$this->assertSame( 'Place', $data['jobLocation']['@type'] );
		$this->assertSame( 'New York', $data['jobLocation']['address']['addressLocality'] );
		$this->assertArrayNotHasKey( 'address', $data );
		$this->assertSame( 'FULL_TIME', $data['employmentType'] );
		$this->assertSame( 'MonetaryAmount', $data['baseSalary']['@type'] );
		$this->assertSame( 'USD', $data['baseSalary']['currency'] );
		$this->assertSame( 130000.0, $data['baseSalary']['value']['minValue'] );
		$this->assertSame( 'YEAR', $data['baseSalary']['value']['unitText'] );
		$this->assertArrayNotHasKey( 'jobLocationType', $data, 'Only remote maps to TELECOMMUTE.' );
	}

	public function test_remote_job_names_the_country_applicants_must_live_in() {
		$job = \WBListora\Core\Listing_Type_Defaults::get_all()['job'];
		Listing_Type_Registry::instance()->save_type( 'qa-job-remote', $job );
		Listing_Type_Registry::instance()->flush();
		Listing_Type_Registry::instance()->init();

		$make = function ( array $address ) {
			$id = self::factory()->post->create(
				array(
					'post_type'   => 'listora_listing',
					'post_status' => 'publish',
					'post_title'  => 'Remote Writer',
				)
			);
			wp_set_object_terms( $id, 'qa-job-remote', 'listora_listing_type' );
			update_post_meta( $id, '_listora_address', $address );
			update_post_meta( $id, '_listora_remote_option', 'remote' );
			return Schema_Generator::for_listing( $id )->get_data();
		};

		$with_country = $make( array( 'city' => 'Austin', 'country' => 'US' ) );
		$this->assertSame( 'TELECOMMUTE', $with_country['jobLocationType'] );
		$this->assertSame( array( '@type' => 'Country', 'name' => 'US' ), $with_country['applicantLocationRequirements'] );

		$no_country = $make( array( 'city' => 'Austin' ) );
		$this->assertArrayNotHasKey( 'jobLocationType', $no_country, 'No country: published on-site at its address, not as an incomplete remote job.' );
		$this->assertArrayNotHasKey( 'applicantLocationRequirements', $no_country );
	}
}

