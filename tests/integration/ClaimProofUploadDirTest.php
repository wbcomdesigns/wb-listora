<?php
/**
 * Regression guard for card 10327885984: the claim-proof `upload_dir` filter
 * must not call wp_upload_dir() from inside itself, or every proof upload
 * recurses until PHP runs out of memory.
 *
 * @package WBListora\Tests\Integration
 * @group   listora
 */

namespace WBListora\Tests\Integration;

use WBListora\Core\Claim_Proofs;
use WP_UnitTestCase;

/**
 * @group listora
 * @group claims
 */
class ClaimProofUploadDirTest extends WP_UnitTestCase {

	public function test_private_dir_resolves_without_recursion(): void {
		$calls = 0;
		$guard = static function ( $dirs ) use ( &$calls ) {
			if ( ++$calls > 5 ) {
				throw new \RuntimeException( 'upload_dir filter re-entered itself' );
			}
			return $dirs;
		};
		add_filter( 'upload_dir', $guard, 9 );

		$dirs = Claim_Proofs::with_private_dir( static fn() => wp_upload_dir( null, false ) );

		remove_filter( 'upload_dir', $guard, 9 );

		$this->assertSame( '/' . Claim_Proofs::DIR, $dirs['subdir'] );
		$this->assertStringEndsWith( '/' . Claim_Proofs::DIR, $dirs['path'] );
		$this->assertStringNotContainsString( Claim_Proofs::DIR, wp_upload_dir( null, false )['path'], 'Filter must be removed after the upload.' );
	}
}
