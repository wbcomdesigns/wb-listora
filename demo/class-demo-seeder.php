<?php
/**
 * Demo Seeder — shared helpers for all demo packs.
 *
 * @package WBListora\Demo
 */

namespace WBListora\Demo;

defined( 'ABSPATH' ) || exit;

/**
 * Provides reusable methods for seeding listings, reviews, and categories.
 */
class Demo_Seeder {

	/**
	 * Static review user counter to ensure unique user IDs.
	 *
	 * @var int
	 */
	private static $review_user_id = 200;

	/**
	 * Seed a single listing. Skips if a listing with the same title already exists.
	 *
	 * @param array $data Listing data array with keys: title, type, content, meta, categories, features, tags, featured.
	 * @return int|false Post ID on success, false if duplicate.
	 */
	public static function seed_listing( $data ) {
		// Idempotency: skip if listing with this title already exists.
		$existing = get_posts(
			array(
				'post_type'      => 'listora_listing',
				'title'          => $data['title'],
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $existing ) ) {
			return false;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'listora_listing',
				'post_title'   => $data['title'],
				'post_content' => $data['content'],
				'post_status'  => 'draft',
				'post_author'  => get_current_user_id() ?: 1,
			)
		);

		if ( is_wp_error( $post_id ) ) {
			return false;
		}

		wp_set_object_terms( $post_id, $data['type'], 'listora_listing_type' );

		if ( ! empty( $data['categories'] ) ) {
			wp_set_object_terms( $post_id, $data['categories'], 'listora_listing_cat' );
		}
		if ( ! empty( $data['features'] ) ) {
			wp_set_object_terms( $post_id, $data['features'], 'listora_listing_feature' );
		}
		if ( ! empty( $data['tags'] ) ) {
			wp_set_object_terms( $post_id, $data['tags'], 'listora_listing_tag' );
		}

		foreach ( $data['meta'] as $key => $value ) {
			\WBListora\Core\Meta_Handler::set_value( $post_id, $key, $value );
		}

		if ( ! empty( $data['featured'] ) ) {
			update_post_meta( $post_id, '_listora_is_featured', true );
		}

		update_post_meta( $post_id, '_listora_demo_content', true );
		update_post_meta( $post_id, '_listora_timezone', 'America/New_York' );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);

		return $post_id;
	}

	/**
	 * Seed a review for a listing.
	 *
	 * @param int    $listing_id Listing post ID.
	 * @param float  $rating     Overall rating (1-5).
	 * @param string $title      Review title.
	 * @param string $content    Review content.
	 */
	public static function seed_review( $listing_id, $rating, $title, $content ) {
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		++self::$review_user_id;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			"{$prefix}reviews", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			array(
				'listing_id'     => $listing_id,
				'user_id'        => self::$review_user_id,
				'overall_rating' => $rating,
				'title'          => $title,
				'content'        => $content,
				'status'         => 'approved',
				'helpful_count'  => wp_rand( 0, 15 ),
				'created_at'     => gmdate( 'Y-m-d H:i:s', strtotime( '-' . wp_rand( 1, 90 ) . ' days' ) ),
				'updated_at'     => current_time( 'mysql', true ),
			)
		);

		// Update rating in search_index.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT AVG(overall_rating) as avg_r, COUNT(*) as cnt FROM {$prefix}reviews WHERE listing_id = %d AND status = 'approved'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$listing_id
			),
			ARRAY_A
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			"{$prefix}search_index", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			array(
				'avg_rating'   => round( (float) $stats['avg_r'], 2 ),
				'review_count' => (int) $stats['cnt'],
			),
			array( 'listing_id' => $listing_id )
		);
	}

	/**
	 * Ensure listing categories exist for a pack. Creates terms if missing.
	 *
	 * @param array $categories Associative array of slug => name pairs.
	 */
	public static function ensure_categories( $categories ) {
		foreach ( $categories as $slug => $name ) {
			if ( ! term_exists( $slug, 'listora_listing_cat' ) ) {
				wp_insert_term(
					$name,
					'listora_listing_cat',
					array( 'slug' => $slug )
				);
			}
		}
	}

	/**
	 * Ensure feature terms exist. Creates terms if missing.
	 *
	 * @param array $features Associative array of slug => name pairs.
	 */
	public static function ensure_features( $features ) {
		foreach ( $features as $slug => $name ) {
			if ( ! term_exists( $slug, 'listora_listing_feature' ) ) {
				wp_insert_term(
					$name,
					'listora_listing_feature',
					array( 'slug' => $slug )
				);
			}
		}
	}

	/**
	 * Generate standard business hours.
	 *
	 * @param string $open_time  Opening time (e.g. '09:00').
	 * @param string $close_time Closing time (e.g. '22:00').
	 * @param bool   $closed_sun Whether Sunday is closed.
	 * @return array Business hours array.
	 */
	public static function make_hours( $open_time = '09:00', $close_time = '21:00', $closed_sun = false ) {
		$hours = array();
		for ( $day = 1; $day <= 6; $day++ ) {
			$hours[] = array(
				'day'   => $day,
				'open'  => $open_time,
				'close' => $close_time,
			);
		}
		if ( $closed_sun ) {
			$hours[] = array(
				'day'    => 0,
				'closed' => true,
			);
		} else {
			$hours[] = array(
				'day'   => 0,
				'open'  => $open_time,
				'close' => $close_time,
			);
		}
		return $hours;
	}
}
