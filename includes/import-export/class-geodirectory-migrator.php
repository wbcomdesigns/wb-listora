<?php
/**
 * GeoDirectory Migrator — imports listings from GeoDirectory plugin.
 *
 * @package WBListora\ImportExport
 */

namespace WBListora\ImportExport;

defined( 'ABSPATH' ) || exit;

/**
 * Migrates listings, categories, tags, reviews, and images
 * from GeoDirectory (CPT: gd_place and custom gd_* CPTs).
 *
 * GeoDirectory stores listing details in its own table ({prefix}geodir_gd_place_detail)
 * rather than standard post meta.
 */
class Geodirectory_Migrator extends Migration_Base {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->source_slug        = 'geodirectory';
		$this->source_name        = 'GeoDirectory';
		$this->source_description = __( 'Migrate listings, categories, locations, and reviews from GeoDirectory.', 'wb-listora' );
	}

	/**
	 * Detect if GeoDirectory data exists.
	 *
	 * Checks if the geodir_gd_place_detail table exists.
	 *
	 * @return bool
	 */
	public function detect() {
		global $wpdb;

		$table = $wpdb->prefix . 'geodir_gd_place_detail';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		);

		return null !== $result;
	}

	/**
	 * Count source GeoDirectory listings.
	 *
	 * @return int
	 */
	public function get_source_count() {
		global $wpdb;

		$table = $wpdb->prefix . 'geodir_gd_place_detail';

		if ( ! $this->detect() ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
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

		$table = $wpdb->prefix . 'geodir_gd_place_detail';

		if ( ! $this->detect() ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$table} ORDER BY post_id ASC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit,
				$offset
			)
		);

		return array_map( 'absint', $ids );
	}

	/**
	 * Migrate a single GeoDirectory listing.
	 *
	 * @param int $source_id Source post ID.
	 * @return array Migration result.
	 */
	public function migrate_listing( $source_id ) {
		global $wpdb;

		$post  = get_post( $source_id );
		$table = $wpdb->prefix . 'geodir_gd_place_detail';

		if ( ! $post ) {
			return array(
				'status'  => 'error',
				'post_id' => 0,
				'message' => sprintf(
					/* translators: %d: source post ID */
					__( 'Source listing #%d not found in wp_posts.', 'wb-listora' ),
					$source_id
				),
			);
		}

		// Get the detail row.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$detail = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE post_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$source_id
			),
			ARRAY_A
		);

		if ( ! $detail ) {
			return array(
				'status'  => 'error',
				'post_id' => 0,
				'message' => sprintf(
					/* translators: %d: source post ID */
					__( 'Listing #%d: No detail data found in GeoDirectory table.', 'wb-listora' ),
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
			'meta'       => $this->map_meta( $detail ),
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
		$lat = $detail['latitude'] ?? '';
		$lng = $detail['longitude'] ?? '';

		if ( $lat && $lng ) {
			$this->insert_geo(
				$post_id,
				(float) $lat,
				(float) $lng,
				array(
					'address'     => $detail['street'] ?? '',
					'city'        => $detail['city'] ?? '',
					'state'       => $detail['region'] ?? '',
					'country'     => $detail['country'] ?? '',
					'postal_code' => $detail['zip'] ?? '',
				)
			);
		}

		// Migrate images from geodir_attachments table.
		$this->migrate_images( $source_id, $post_id );

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
	 * Map GeoDirectory detail table fields to Listora meta.
	 *
	 * @param array $detail Detail row from geodir_gd_place_detail.
	 * @return array Key => value pairs for Listora meta.
	 */
	private function map_meta( $detail ) {
		$meta = array();

		$field_map = array(
			'phone'   => 'phone',
			'email'   => 'email',
			'website' => 'website',
		);

		foreach ( $field_map as $gd_key => $listora_key ) {
			if ( ! empty( $detail[ $gd_key ] ) ) {
				$meta[ $listora_key ] = $detail[ $gd_key ];
			}
		}

		// Address as text.
		$address_parts = array_filter(
			array(
				$detail['street'] ?? '',
				$detail['city'] ?? '',
				$detail['region'] ?? '',
				$detail['country'] ?? '',
			)
		);
		if ( ! empty( $address_parts ) ) {
			$meta['address_text'] = implode( ', ', $address_parts );
		}

		return $meta;
	}

	/**
	 * Map GeoDirectory taxonomies to Listora taxonomies.
	 *
	 * @param int $source_id Source post ID.
	 * @return array Taxonomy => terms array.
	 */
	private function map_taxonomies( $source_id ) {
		$taxonomies = array();

		// Categories: gd_placecategory -> listora_listing_cat.
		$cats = $this->get_source_terms( $source_id, 'gd_placecategory' );
		if ( ! empty( $cats ) ) {
			$taxonomies['listora_listing_cat'] = $cats;
		}

		// Tags: gd_place_tags -> listora_listing_feature.
		$tags = $this->get_source_terms( $source_id, 'gd_place_tags' );
		if ( ! empty( $tags ) ) {
			$taxonomies['listora_listing_feature'] = $tags;
		}

		return $taxonomies;
	}

	/**
	 * Migrate images from GeoDirectory's geodir_attachments table.
	 *
	 * @param int $source_id Source post ID.
	 * @param int $post_id   Listora listing ID.
	 */
	private function migrate_images( $source_id, $post_id ) {
		global $wpdb;

		$attachments_table = $wpdb->prefix . 'geodir_attachments';

		// Check if the table exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $attachments_table )
		);

		if ( ! $table_exists ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$images = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$attachments_table} WHERE post_id = %d AND mime_type LIKE %s ORDER BY menu_order ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$source_id,
				'image/%'
			),
			ARRAY_A
		);

		if ( empty( $images ) ) {
			return;
		}

		$gallery_ids = array();
		foreach ( $images as $image ) {
			// GeoDirectory attachments may store file paths or have WP attachment IDs.
			if ( ! empty( $image['ID'] ) ) {
				$attachment = get_post( $image['ID'] );
				if ( $attachment && 'attachment' === $attachment->post_type ) {
					$gallery_ids[] = (int) $image['ID'];
				}
			}
		}

		if ( ! empty( $gallery_ids ) ) {
			\WBListora\Core\Meta_Handler::set_value( $post_id, 'gallery', $gallery_ids );
		}
	}

	/**
	 * Migrate GeoDirectory reviews (WP comments with custom rating meta).
	 *
	 * @param int $source_id Source post ID.
	 * @param int $post_id   Listora listing ID.
	 */
	private function migrate_reviews( $source_id, $post_id ) {
		$comments = get_comments(
			array(
				'post_id' => $source_id,
				'status'  => 'all',
				'type'    => 'comment',
			)
		);

		if ( empty( $comments ) ) {
			return;
		}

		foreach ( $comments as $comment ) {
			// GeoDirectory stores the overall rating in comment meta.
			$rating = (int) get_comment_meta( (int) $comment->comment_ID, 'geodir-overall_rating', true );

			if ( $rating < 1 ) {
				continue; // Skip comments without ratings (non-review comments).
			}

			$status = '1' === $comment->comment_approved ? 'approved' : 'pending';
			if ( 'trash' === $comment->comment_approved || 'spam' === $comment->comment_approved ) {
				continue;
			}

			$this->insert_review(
				array(
					'listing_id'     => $post_id,
					'user_id'        => (int) $comment->user_id,
					'overall_rating' => min( 5, $rating ),
					'title'          => '',
					'content'        => $comment->comment_content,
					'status'         => $status,
					'created_at'     => $comment->comment_date,
				)
			);
		}
	}
}
