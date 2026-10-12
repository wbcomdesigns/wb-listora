<?php
/**
 * Background import batches run as the member who started the import.
 *
 * @package WBListora\Tests
 */

use WBListora\ImportExport\Background_Import;

/**
 * @group listora
 * @group import
 */
class BackgroundImportOwnerTest extends WP_UnitTestCase {

	public function test_a_batch_run_by_cron_keeps_the_importers_authorship() {
		$owner = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $owner );

		$file = wp_tempnam( 'listora-import.csv' );
		file_put_contents( $file, "title\nQA Owned Import One\nQA Owned Import Two\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		add_filter( 'wb_listora_bg_import_use_async', '__return_true' );
		$run_id = Background_Import::queue_file( 'csv', $file, '', array( 0 => 'title' ), 2 );
		remove_filter( 'wb_listora_bg_import_use_async', '__return_true' );
		$this->assertIsString( $run_id );

		wp_set_current_user( 0 ); // Action Scheduler / WP-Cron.
		for ( $i = 0; $i < 5 && ! wb_listora_get_import_progress( $run_id )['done']; $i++ ) {
			Background_Import::run_batch( $run_id );
		}

		$this->assertSame( 0, get_current_user_id(), 'The runner\'s user is restored.' );
		$ids = get_posts( array( 'post_type' => 'listora_listing', 'post_status' => 'any', 's' => 'QA Owned Import', 'fields' => 'ids' ) );
		$this->assertNotEmpty( $ids );
		foreach ( $ids as $id ) {
			$this->assertSame( $owner, (int) get_post_field( 'post_author', $id ) );
		}
	}
}
