<?php
/**
 * Directorist Migrator — imports listings from Directorist plugin.
 *
 * @package WBListora\ImportExport
 */

namespace WBListora\ImportExport;

defined( 'ABSPATH' ) || exit;

/**
 * Migrates listings, categories, locations, tags, reviews, and images
 * from Directorist (CPT: at_biz_dir).
 */
class Directorist_Migrator extends Migration_Base {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->source_slug        = 'directorist';
		$this->source_name        = 'Directorist';
		$this->source_description = __( 'Migrate listings, categories, locations, and reviews from Directorist.', 'wb-listora' );
	}

	/**
	 * Detect if Directorist data exists.
	 *
	 * Checks both CPT registration and direct post count in wp_posts.
	 *
	 * @return bool
	 */
	public function detect() {
		global $wpdb;

		// Check if CPT is registered (plugin active).
		if ( post_type_exists( 'at_biz_dir' ) ) {
			return true;
		}

		// Check if posts exist even if plugin is deactivated.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'at_biz_dir' AND post_status IN ('publish', 'pending', 'draft', 'private')"
		);

		return $count > 0;
	}

	/**
	 * Count source Directorist listings.
	 *
	 * @return int
	 */
	public function get_source_count() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'at_biz_dir' AND post_status IN ('publish', 'pending', 'draft', 'private')"
		);
	}

	/**
	 * Get source post IDs for pagination.
	 *
	 * @param int $offset Offset.
	 * @param int $limit  Limit.
	 * @return int[]
	 */
	protected function get_source_ids( $offset, $limit ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'at_biz_dir' AND post_status IN ('publish', 'pending', 'draft', 'private') ORDER BY ID ASC LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);

		return array_map( 'absint', $ids );
	}

	/**
	 * Migrate a single Directorist listing.
	 *
	 * @param int $source_id Source post ID.
	 * @return array Migration result.
	 */
	public function migrate_listing( $source_id ) {
		$post = get_post( $source_id );

		if ( ! $post ) {
			return array(
				'status'  => 'error',
				'post_id' => 0,
				'message' => sprintf(
					/* translators: %d: source post ID */
					__( 'Source listing #%d not found.', 'wb-listora' ),
					$source_id
				),
			);
		}

		// Build listing data.
		$data = array(
			'title'      => $post->post_title,
			'content'    => $post->post_content,
			'status'     => $post->post_status,
			'author_id'  => $post->post_author,
			'date'       => $post->post_date,
			'source_id'  => $source_id,
			'thumbnail'  => get_post_thumbnail_id( $source_id ),
			'meta'       => $this->map_meta( $source_id ),
			'taxonomies' => $this->map_taxonomies( $source_id ),
		);

		$post_id = $this->create_listing( $data );

		if ( is_wp_error( $post_id ) ) {
			return array(
				'status'  => 'error',
				'post_id' => 0,
				'message' => sprintf(
					/* translators: 1: source ID, 2: error message */
					__( 'Listing #%1$d: %2$s', 'wb-listora' ),
					$source_id,
					$post_id->get_error_message()
				),
			);
		}

		// Migrate geo data.
		$lat = $this->get_source_meta( $source_id, '_manual_lat' );
		$lng = $this->get_source_meta( $source_id, '_manual_lng' );

		if ( $lat && $lng ) {
			$address_text = $this->get_source_meta( $source_id, '_address' );
			$this->insert_geo( $post_id, (float) $lat, (float) $lng, array( 'address' => $address_text ) );
		}

		// Migrate gallery images.
		$this->migrate_gallery( $source_id, $post_id );

		// Migrate reviews.
		$this->migrate_reviews( $source_id, $post_id );

		// Index the listing.
		$this->index_listing( $post_id );

		return array(
			'status'  => 'imported',
			'post_id' => $post_id,
			'message' => sprintf(
				/* translators: 1: source ID, 2: new listing ID */
				__( 'Listing #%1$d migrated as #%2$d.', 'wb-listora' ),
				$source_id,
				$post_id
			),
		);
	}

	/**
	 * Map Directorist meta fields to Listora meta.
	 *
	 * @param int $source_id Source post ID.
	 * @return array Key => value pairs for Listora meta.
	 */
	private function map_meta( $source_id ) {
		$meta = array();

		$field_map = array(
			'_fm_price' => 'price',
			'_email'    => 'email',
			'_phone'    => 'phone',
			'_website'  => 'website',
			'_address'  => 'address_text',
		);

		foreach ( $field_map as $source_key => $listora_key ) {
			$value = $this->get_source_meta( $source_id, $source_key );
			if ( '' !== $value ) {
				$meta[ $listora_key ] = $value;
			}
		}

		// Business hours (Directorist stores as serialized array).
		$biz_hours = $this->get_source_meta( $source_id, '_biz_hours' );
		if ( ! empty( $biz_hours ) && is_array( $biz_hours ) ) {
			$meta['business_hours'] = $biz_hours;
		}

		return $meta;
	}

	/**
	 * Map Directorist taxonomies to Listora taxonomies.
	 *
	 * @param int $source_id Source post ID.
	 * @return array Taxonomy => terms array.
	 */
	private function map_taxonomies( $source_id ) {
		$taxonomies = array();

		// Categories: at_biz_dir-category -> listora_listing_cat.
		$cats = $this->get_source_terms( $source_id, 'at_biz_dir-category' );
		if ( ! empty( $cats ) ) {
			$taxonomies['listora_listing_cat'] = $cats;
		}

		// Locations: at_biz_dir-location -> listora_listing_location.
		$locations = $this->get_source_terms( $source_id, 'at_biz_dir-location' );
		if ( ! empty( $locations ) ) {
			$taxonomies['listora_listing_location'] = $locations;
		}

		// Tags: at_biz_dir-tags -> listora_listing_feature.
		$tags = $this->get_source_terms( $source_id, 'at_biz_dir-tags' );
		if ( ! empty( $tags ) ) {
			$taxonomies['listora_listing_feature'] = $tags;
		}

		return $taxonomies;
	}

	/**
	 * Migrate Directorist gallery images.
	 *
	 * Directorist stores gallery as _listing_img meta (comma-separated IDs or serialized).
	 *
	 * @param int $source_id Source post ID.
	 * @param int $post_id   Listora listing ID.
	 */
	private function migrate_gallery( $source_id, $post_id ) {
		$gallery = $this->get_source_meta( $source_id, '_listing_img' );

		if ( empty( $gallery ) ) {
			return;
		}

		$attachment_ids = array();

		if ( is_array( $gallery ) ) {
			$attachment_ids = array_map( 'absint', $gallery );
		} elseif ( is_string( $gallery ) ) {
			$attachment_ids = array_map( 'absint', array_filter( explode( ',', $gallery ) ) );
		}

		if ( ! empty( $attachment_ids ) ) {
			\WBListora\Core\Meta_Handler::set_value( $post_id, 'gallery', $attachment_ids );
		}
	}

	/**
	 * Migrate Directorist reviews (stored as WP comments with comment_type 'review').
	 *
	 * @param int $source_id Source post ID.
	 * @param int $post_id   Listora listing ID.
	 */
	private function migrate_reviews( $source_id, $post_id ) {
		$comments = get_comments(
			array(
				'post_id' => $source_id,
				'type'    => 'review',
				'status'  => 'all',
			)
		);

		if ( empty( $comments ) ) {
			return;
		}

		foreach ( $comments as $comment ) {
			$rating = (int) get_comment_meta( (int) $comment->comment_ID, 'rating', true );
			if ( $rating < 1 ) {
				$rating = 5;
			}

			$status = '1' === $comment->comment_approved ? 'approved' : 'pending';
			if ( 'trash' === $comment->comment_approved || 'spam' === $comment->comment_approved ) {
				continue; // Skip trashed/spam reviews.
			}

			$this->insert_review(
				array(
					'listing_id'     => $post_id,
					'user_id'        => (int) $comment->user_id,
					'overall_rating' => $rating,
					'title'          => '',
					'content'        => $comment->comment_content,
					'status'         => $status,
					'created_at'     => $comment->comment_date,
				)
			);
		}
	}
}
