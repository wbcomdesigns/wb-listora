<?php
/**
 * Migrated_From Tracker — sole writer of the `_listora_migrated_from` post meta.
 *
 * Both Free's import-export migrators and Pro's competitor migrators must call
 * {@see Migrated_From_Tracker::set()} so a single, consistent format is written
 * for every migrated listing. This is the canonical implementation referenced by
 * Architecture Invariant #12 (no meta-key dual-writer between Free and Pro).
 *
 * Format: `{source_slug}:{source_id}`. Example: `wp-job-manager:42`.
 *
 * @package WBListora\Migration
 */

declare(strict_types=1);

namespace WBListora\Migration;

defined( 'ABSPATH' ) || exit;

/**
 * Stable shared writer for the `_listora_migrated_from` post meta.
 */
class Migrated_From_Tracker {

	/**
	 * Post-meta key written by every migrator.
	 *
	 * @var string
	 */
	const META_KEY = '_listora_migrated_from';

	/**
	 * Record the upstream source for a freshly-migrated listing.
	 *
	 * @param int        $post_id     Listing post ID.
	 * @param string     $source_slug Migrator slug (e.g. `wp-job-manager`, `business-directory-plugin`).
	 * @param int|string $source_id   Upstream identifier — usually an int but legacy migrators may pass a string.
	 * @return void
	 */
	public static function set( int $post_id, string $source_slug, $source_id ): void {
		$value = $source_slug . ':' . (string) $source_id;
		update_post_meta( $post_id, self::META_KEY, $value );
	}

	/**
	 * Read the migration source for a listing (raw stored value).
	 *
	 * @param int $post_id Listing post ID.
	 * @return string `slug:source_id` or '' when never migrated.
	 */
	public static function get( int $post_id ): string {
		return (string) get_post_meta( $post_id, self::META_KEY, true );
	}
}
