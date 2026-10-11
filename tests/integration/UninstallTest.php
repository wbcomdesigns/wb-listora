<?php
/**
 * uninstall.php removes what the plugin wrote, and nothing when not opted in.
 *
 * Table drops are not asserted: the WP test suite rewrites DROP TABLE to
 * DROP TEMPORARY TABLE, so real tables survive by design. Rows are asserted.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 * @group uninstall
 */
class UninstallTest extends WP_UnitTestCase {

	/**
	 * Seed one of each thing uninstall must remove; return the upload file path.
	 */
	private function seed(): string {
		$listing = self::factory()->post->create( array( 'post_type' => 'listora_listing' ) );
		update_post_meta( $listing, '_listora_phone', '555' );
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_post_meta( $page, '_wb_listora_page_key', 'dashboard' );
		update_option( 'wb_listora_bg_import_abc', array( 'rows' => 1 ), false );
		update_option( 'wb-listora_license_key', 'KEY123' );
		update_option( 'edd_sl_' . md5( (string) wp_json_encode( array( 'wb-listora', 'KEY123', 0 ) ) ), array( 'cached' ) );
		update_option( 'edd_sl_' . md5( 'another-plugin' ), array( 'theirs' ) );
		wp_schedule_event( time() + 60, 'weekly', 'edd_sl_sdk_weekly_license_check_wb-listora' );
		wp_schedule_event( time() + 60, 'weekly', 'edd_sl_sdk_weekly_license_check_other' );

		$dir = wp_upload_dir()['basedir'] . '/wb-listora-import';
		wp_mkdir_p( $dir );
		file_put_contents( $dir . '/customers.csv', "name\nAda\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture.
		return $dir . '/customers.csv';
	}

	public function tear_down() {
		// remove_caps() edits the role objects in memory; the DB side rolls back.
		( new \WBListora\Core\Capabilities() )->add_caps();
		parent::tear_down();
	}

	private function run_uninstall(): void {
		defined( 'WP_UNINSTALL_PLUGIN' ) || define( 'WP_UNINSTALL_PLUGIN', 'wb-listora/wb-listora.php' );
		include dirname( __DIR__, 2 ) . '/uninstall.php';
	}

	public function test_opted_in_uninstall_removes_plugin_data_only() {
		update_option( 'wb_listora_settings', array( 'delete_on_uninstall' => true ) );
		$file = $this->seed();

		$this->run_uninstall();

		global $wpdb;
		$this->assertSame( '0', $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'listora_listing'" ) );
		$this->assertSame( '0', $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key LIKE '\\_listora\\_%' OR meta_key = '_wb_listora_page_key'" ) );
		$this->assertSame( '0', $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'wb\\_listora\\_%' OR option_name LIKE 'wb-listora\\_%'" ) );
		$this->assertFalse( get_option( 'edd_sl_' . md5( (string) wp_json_encode( array( 'wb-listora', 'KEY123', 0 ) ) ) ) );
		$this->assertFalse( wp_next_scheduled( 'edd_sl_sdk_weekly_license_check_wb-listora' ) );
		$this->assertFileDoesNotExist( $file );

		// Rows of other plugins that share the licensing SDK stay.
		$this->assertSame( array( 'theirs' ), get_option( 'edd_sl_' . md5( 'another-plugin' ) ) );
		$this->assertNotFalse( wp_next_scheduled( 'edd_sl_sdk_weekly_license_check_other' ) );
	}

	public function test_without_opt_in_nothing_is_removed() {
		update_option( 'wb_listora_settings', array( 'delete_on_uninstall' => false ) );
		$file = $this->seed();

		$this->run_uninstall();

		$this->assertSame( 'KEY123', get_option( 'wb-listora_license_key' ) );
		$this->assertNotFalse( get_option( 'wb_listora_bg_import_abc' ) );
		$this->assertNotFalse( wp_next_scheduled( 'edd_sl_sdk_weekly_license_check_wb-listora' ) );
		$this->assertFileExists( $file );
		unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test cleanup.
	}

	public function test_multisite_uninstall_runs_on_every_site() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Run with WP_MULTISITE=1.' );
		}
		$site = self::factory()->blog->create();
		switch_to_blog( $site );
		update_option( 'wb_listora_settings', array( 'delete_on_uninstall' => true ) );
		$listing = self::factory()->post->create( array( 'post_type' => 'listora_listing' ) );
		restore_current_blog();

		$this->run_uninstall();

		switch_to_blog( $site );
		$this->assertNull( get_post( $listing ) );
		$this->assertFalse( get_option( 'wb_listora_settings' ) );
		restore_current_blog();
	}

	public function test_multisite_user_meta_kept_while_any_site_keeps_data() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Run with WP_MULTISITE=1.' );
		}
		$user = self::factory()->user->create();
		update_user_meta( $user, '_listora_saved', 'yes' );
		update_option( 'wb_listora_settings', array( 'delete_on_uninstall' => true ) );
		$site = self::factory()->blog->create();
		switch_to_blog( $site );
		update_option( 'wb_listora_settings', array( 'delete_on_uninstall' => false ) );
		restore_current_blog();

		$this->run_uninstall();
		wp_cache_flush();
		$this->assertSame( 'yes', get_user_meta( $user, '_listora_saved', true ), 'One site keeps its data, so shared user meta stays.' );

		switch_to_blog( $site );
		update_option( 'wb_listora_settings', array( 'delete_on_uninstall' => true ) );
		restore_current_blog();
		update_option( 'wb_listora_settings', array( 'delete_on_uninstall' => true ) );
		$this->run_uninstall();
		wp_cache_flush();
		$this->assertSame( '', get_user_meta( $user, '_listora_saved', true ), 'Every site opted in, so user meta goes.' );
	}
}

