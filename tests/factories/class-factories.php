<?php
/**
 * Test-factory façade.
 *
 * Convenience: `Factories::listing()->create()` instead of fully-qualified
 * namespace access per call site. Also the single include surface —
 * bootstrap.php only has to require this one file.
 *
 * @package WBListora\Tests\Factories
 */

namespace WBListora\Tests\Factories;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-listing-factory.php';
require_once __DIR__ . '/class-review-factory.php';
require_once __DIR__ . '/class-favorite-factory.php';
require_once __DIR__ . '/class-claim-factory.php';

/**
 * Static accessor so tests can do `Factories::listing()->create([...])`.
 */
final class Factories {

	public static function listing(): Listing_Factory_Facade {
		return new Listing_Factory_Facade();
	}

	public static function review(): Review_Factory_Facade {
		return new Review_Factory_Facade();
	}

	public static function favorite(): Favorite_Factory_Facade {
		return new Favorite_Factory_Facade();
	}

	public static function claim(): Claim_Factory_Facade {
		return new Claim_Factory_Facade();
	}
}

/** @internal instance-method wrappers so static-method factories look fluent. */
final class Listing_Factory_Facade {
	/** @param array<string,mixed> $args */
	public function create( array $args = array() ): int {
		return Listing_Factory::create( $args );
	}
	/** @param array<string,mixed> $args @return array<int,int> */
	public function create_many( int $count, array $args = array() ): array {
		return Listing_Factory::create_many( $count, $args );
	}
}

final class Review_Factory_Facade {
	/** @param array<string,mixed> $args */
	public function create( array $args = array() ): int {
		return Review_Factory::create( $args );
	}
}

final class Favorite_Factory_Facade {
	/**
	 * @param array<string,mixed> $args
	 * @return array{user_id:int,listing_id:int,collection:string}
	 */
	public function create( array $args = array() ): array {
		return Favorite_Factory::create( $args );
	}
}

final class Claim_Factory_Facade {
	/** @param array<string,mixed> $args */
	public function create( array $args = array() ): int {
		return Claim_Factory::create( $args );
	}
}
