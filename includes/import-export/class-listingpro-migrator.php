<?php
/**
 * ListingPro Migrator — imports listings from ListingPro theme.
 *
 * @package WBListora\ImportExport
 */

namespace WBListora\ImportExport;

defined( 'ABSPATH' ) || exit;

/**
 * Migrates listings, categories, locations, features, reviews, and images
 * from ListingPro (CPT: listing).
 */
class Listingpro_Migrator extends Migration_Base {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->source_slug        = 'listingpro';
		$this->source_name        = 'ListingPro';
		$this->source_description = __( 'Migrate listings, categories, locations, features, and reviews from ListingPro.', 'wb-listora' );
	}

	/**
	 * Detect if ListingPro data exists.
	 *
	 * Checks if CPT 'listing' exists AND 'listing-category' taxonomy exists,
	 * or if posts with that CPT exist in the database.
	 *
	 * @return bool
	 */
	public function detect() {
		global $wpdb;

		// Check CPT + taxonomy registration (plugin/theme active).
		if ( post_type_exists( 'listing' ) && taxonomy_exists( 'listing-category' ) ) {
			return true;
		}

		// Check posts directly even if theme is deactivated.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'listing' AND post_status IN ('publish', 'pending', 'draft', 'private')"
		);

		if ( $post_count < 1 ) {
			return false;
		}

		// Verify it is ListingPro data by checking for ListingPro-specific meta.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$has_lp_meta = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_lp_listingpro_options' LIMIT 1"
		);

		return $has_lp_meta > 0;
	}

	/**
	 * Count source ListingPro listings.
	 *
	 * @return int
	 */
	public function get_source_count() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'listing' AND post_status IN ('publish', 'pending', 'draft', 'private')"
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
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'listing' AND post_status IN ('publish', 'pending', 'draft', 'private') ORDER BY ID ASC LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);

		return array_map( 'absint', $ids );
	}

	/**
	 * Migrate a single ListingPro listing.
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
		$lat = $this->get_source_meta( $source_id, '_latitude' );
		$lng = $this->get_source_meta( $source_id, '_longitude' );

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
	 * Map ListingPro meta fields to Listora meta.
	 *
	 * Field mapping: ListingPro → Listora
	 *
	 * _phone                               → phone
	 * _email                               → email
	 * _website                             → website
	 * _address                             → address
	 * _price                               → price
	 * _price_range                         → price_range
	 * _lp_listingpro_options[business_hours] → business_hours
	 * _lp_listingpro_options[social]        → social_links
	 * _latitude                            → geo table (lat)
	 * _longitude                           → geo table (lng)
	 * _gallery                             → gallery (handled in migrate_gallery)
	 *
	 * @param int $source_id Source post ID.
	 * @return array Key => value pairs for Listora meta.
	 */
	private function map_meta( $source_id ) {
		$meta = array();

		$field_map = array(
			'_phone'   => 'phone',
			'_email'   => 'email',
			'_website' => 'website',
			'_address' => 'address',
			'_price'   => 'price',
		);

		foreach ( $field_map as $source_key => $listora_key ) {
			$value = $this->get_source_meta( $source_id, $source_key );
			if ( '' !== $value ) {
				$meta[ $listora_key ] = $value;
			}
		}

		// Price range (separate from _price which is a specific amount).
		$price_range = $this->get_source_meta( $source_id, '_price_range' );
		if ( '' !== $price_range ) {
			$meta['price_range'] = $price_range;
		}

		// ListingPro options (serialized array).
		$lp_options = $this->get_source_meta( $source_id, '_lp_listingpro_options' );
		if ( is_array( $lp_options ) ) {
			// Extract any additional fields from the options array.
			if ( ! empty( $lp_options['business_hours'] ) ) {
				$meta['business_hours'] = $lp_options['business_hours'];
			}
			if ( ! empty( $lp_options['social'] ) ) {
				$meta['social_links'] = $lp_options['social'];
			}
		}

		return $meta;
	}

	/**
	 * Map ListingPro taxonomies to Listora taxonomies.
	 *
	 * @param int $source_id Source post ID.
	 * @return array Taxonomy => terms array.
	 */
	private function map_taxonomies( $source_id ) {
		$taxonomies = array();

		// Categories: listing-category -> listora_listing_cat.
		$cats = $this->get_source_terms( $source_id, 'listing-category' );
		if ( ! empty( $cats ) ) {
			$taxonomies['listora_listing_cat'] = $cats;
		}

		// Locations: location -> listora_listing_location.
		$locations = $this->get_source_terms( $source_id, 'location' );
		if ( ! empty( $locations ) ) {
			$taxonomies['listora_listing_location'] = $locations;
		}

		// Features: features -> listora_listing_feature.
		$features = $this->get_source_terms( $source_id, 'features' );
		if ( ! empty( $features ) ) {
			$taxonomies['listora_listing_feature'] = $features;
		}

		return $taxonomies;
	}

	/**
	 * Migrate ListingPro gallery images.
	 *
	 * ListingPro stores gallery as _gallery meta (comma-separated attachment IDs).
	 *
	 * @param int $source_id Source post ID.
	 * @param int $post_id   Listora listing ID.
	 */
	private function migrate_gallery( $source_id, $post_id ) {
		$gallery = $this->get_source_meta( $source_id, '_gallery' );

		if ( empty( $gallery ) ) {
			return;
		}

		$attachment_ids = array();

		if ( is_array( $gallery ) ) {
			$attachment_ids = array_map( 'absint', $gallery );
		} elseif ( is_string( $gallery ) ) {
			$attachment_ids = array_map( 'absint', array_filter( explode( ',', $gallery ) ) );
		}

		$attachment_ids = array_filter( $attachment_ids );

		if ( ! empty( $attachment_ids ) ) {
			\WBListora\Core\Meta_Handler::set_value( $post_id, 'gallery', $attachment_ids );
		}
	}

	/**
	 * Migrate ListingPro reviews.
	 *
	 * ListingPro may use CPT 'developer_developer_review' or WP comments.
	 * We check both sources.
	 *
	 * @param int $source_id Source post ID.
	 * @param int $post_id   Listora listing ID.
	 */
	private function migrate_reviews( $source_id, $post_id ) {
		// Try CPT reviews first.
		$cpt_migrated = $this->migrate_cpt_reviews( $source_id, $post_id );

		// If no CPT reviews, try comments.
		if ( ! $cpt_migrated ) {
			$this->migrate_comment_reviews( $source_id, $post_id );
		}
	}

	/**
	 * Migrate reviews stored as ListingPro review CPT.
	 *
	 * @param int $source_id Source post ID.
	 * @param int $post_id   Listora listing ID.
	 * @return bool True if any reviews were migrated.
	 */
	private function migrate_cpt_reviews( $source_id, $post_id ) {
		global $wpdb;

		// Check if the review CPT has posts for this listing.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$reviews = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.*, pm.meta_value as rating
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_review_rating'
				WHERE p.post_type = 'developer_developer_review'
				AND p.post_status IN ('publish', 'pending')
				AND p.post_parent = %d",
				$source_id
			),
			ARRAY_A
		);

		if ( empty( $reviews ) ) {
			return false;
		}

		foreach ( $reviews as $review ) {
			$rating = (int) ( $review['rating'] ?? 5 );
			if ( $rating < 1 ) {
				$rating = 5;
			}

			$this->insert_review(
				array(
					'listing_id'     => $post_id,
					'user_id'        => (int) $review['post_author'],
					'overall_rating' => min( 5, $rating ),
					'title'          => $review['post_title'],
					'content'        => $review['post_content'],
					'status'         => 'publish' === $review['post_status'] ? 'approved' : 'pending',
					'created_at'     => $review['post_date'],
				)
			);
		}

		return true;
	}

	/**
	 * Migrate reviews stored as WP comments.
	 *
	 * @param int $source_id Source post ID.
	 * @param int $post_id   Listora listing ID.
	 */
	private function migrate_comment_reviews( $source_id, $post_id ) {
		$comments = get_comments(
			array(
				'post_id' => $source_id,
				'status'  => 'all',
			)
		);

		if ( empty( $comments ) ) {
			return;
		}

		foreach ( $comments as $comment ) {
			$rating = (int) get_comment_meta( (int) $comment->comment_ID, 'rating', true );

			// Skip comments without ratings (not reviews).
			if ( $rating < 1 ) {
				continue;
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
