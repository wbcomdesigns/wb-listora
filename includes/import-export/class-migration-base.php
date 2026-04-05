<?php
/**
 * Migration Base — abstract class for migrating from competitor plugins.
 *
 * @package WBListora\ImportExport
 */

namespace WBListora\ImportExport;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract base class for directory plugin migrators.
 *
 * Subclasses implement detect(), get_source_count(), and migrate_listing()
 * to handle plugin-specific data mapping. Shared helpers handle Listora
 * listing creation, taxonomy mapping, geo indexing, and duplicate prevention.
 */
abstract class Migration_Base {

	/**
	 * Batch size for migration processing.
	 *
	 * @var int
	 */
	protected $batch_size = 50;

	/**
	 * The source identifier (e.g. 'directorist', 'geodirectory').
	 *
	 * @var string
	 */
	protected $source_slug = '';

	/**
	 * Human-readable source plugin name.
	 *
	 * @var string
	 */
	protected $source_name = '';

	/**
	 * Short description of what gets migrated.
	 *
	 * @var string
	 */
	protected $source_description = '';

	/**
	 * Detect whether the source plugin data exists.
	 *
	 * Should work even if the source plugin is deactivated — check
	 * tables and posts directly, not CPT registration.
	 *
	 * @return bool True if source data is available.
	 */
	abstract public function detect();

	/**
	 * Count the number of source listings available for migration.
	 *
	 * @return int
	 */
	abstract public function get_source_count();

	/**
	 * Migrate a single listing from the source plugin.
	 *
	 * @param int $source_id The source post ID.
	 * @return array {
	 *     @type string $status  'imported', 'skipped', or 'error'.
	 *     @type int    $post_id The new Listora listing ID (if imported).
	 *     @type string $message Human-readable status message.
	 * }
	 */
	abstract public function migrate_listing( $source_id );

	/**
	 * Get all source post IDs to migrate.
	 *
	 * @param int $offset Offset for pagination.
	 * @param int $limit  Number of IDs to return.
	 * @return int[] Array of source post IDs.
	 */
	abstract protected function get_source_ids( $offset, $limit );

	/**
	 * Get the source slug identifier.
	 *
	 * @return string
	 */
	public function get_source_slug() {
		return $this->source_slug;
	}

	/**
	 * Get the source plugin name.
	 *
	 * @return string
	 */
	public function get_source_name() {
		return $this->source_name;
	}

	/**
	 * Get the source plugin description.
	 *
	 * @return string
	 */
	public function get_source_description() {
		return $this->source_description;
	}

	/**
	 * Migrate all listings from the source plugin.
	 *
	 * Processes in batches to avoid memory issues. Skips already-migrated listings.
	 *
	 * @param bool          $dry_run  If true, validate without creating.
	 * @param callable|null $progress Optional callback for progress reporting: function( $current, $total, $result ).
	 * @return array {
	 *     @type int      $imported Total imported.
	 *     @type int      $skipped  Total skipped (already migrated or no data).
	 *     @type int      $errors   Total errors.
	 *     @type int      $total    Total source listings.
	 *     @type string[] $messages Array of status messages.
	 * }
	 */
	public function migrate_all( $dry_run = false, $progress = null ) {
		$stats = array(
			'imported' => 0,
			'skipped'  => 0,
			'errors'   => 0,
			'total'    => $this->get_source_count(),
			'messages' => array(),
		);

		global $wpdb;

		$offset    = 0;
		$processed = 0;

		while ( true ) {
			$ids = $this->get_source_ids( $offset, $this->batch_size );

			if ( empty( $ids ) ) {
				break;
			}

			// Wrap each batch in a transaction — commit after the batch completes.
			if ( ! $dry_run ) {
				$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			}

			$batch_error = false;

			foreach ( $ids as $source_id ) {
				++$processed;

				// Check if already migrated.
				if ( $this->is_already_migrated( $source_id ) ) {
					++$stats['skipped'];
					$result = array(
						'status'  => 'skipped',
						'post_id' => 0,
						'message' => sprintf(
							/* translators: %d: source listing ID */
							__( 'Listing #%d already migrated, skipped.', 'wb-listora' ),
							$source_id
						),
					);
				} elseif ( $dry_run ) {
					++$stats['imported'];
					$result = array(
						'status'  => 'imported',
						'post_id' => 0,
						'message' => sprintf(
							/* translators: %d: source listing ID */
							__( 'Listing #%d would be imported.', 'wb-listora' ),
							$source_id
						),
					);
				} else {
					try {
						$result = $this->migrate_listing( $source_id );
					} catch ( \Exception $e ) {
						$result = array(
							'status'  => 'error',
							'post_id' => 0,
							'message' => sprintf(
								/* translators: 1: source listing ID, 2: error message */
								__( 'Listing #%1$d failed: %2$s', 'wb-listora' ),
								$source_id,
								$e->getMessage()
							),
						);
					}

					if ( 'imported' === $result['status'] ) {
						++$stats['imported'];
					} elseif ( 'skipped' === $result['status'] ) {
						++$stats['skipped'];
					} else {
						++$stats['errors'];
						$batch_error = true;
					}

					$stats['messages'][] = $result['message'];
				}

				if ( is_callable( $progress ) ) {
					call_user_func( $progress, $processed, $stats['total'], $result );
				}
			}

			// Commit or rollback the batch.
			if ( ! $dry_run ) {
				if ( $batch_error ) {
					$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				} else {
					$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				}
			}

			$offset += $this->batch_size;

			// Free memory between batches.
			wp_cache_flush();
		}

		return $stats;
	}

	/**
	 * Check if a source listing has already been migrated.
	 *
	 * @param int $source_id The source post ID.
	 * @return bool
	 */
	protected function is_already_migrated( $source_id ) {
		global $wpdb;

		$meta_value = $this->source_slug . ':' . $source_id;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				WHERE meta_key = '_listora_migrated_from'
				AND meta_value = %s
				LIMIT 1",
				$meta_value
			)
		);

		return ! empty( $existing );
	}

	/**
	 * Create a Listora listing from mapped data.
	 *
	 * @param array $data {
	 *     @type string $title       Post title.
	 *     @type string $content     Post content.
	 *     @type string $status      Post status (default 'publish').
	 *     @type int    $author_id   Post author.
	 *     @type string $date        Post date.
	 *     @type int    $source_id   Original source post ID.
	 *     @type int    $thumbnail   Featured image attachment ID.
	 *     @type array  $meta        Key => value pairs for Meta_Handler::set_value().
	 *     @type array  $taxonomies  Taxonomy => terms array.
	 * }
	 * @return int|\WP_Error Post ID or error.
	 */
	protected function create_listing( $data ) {
		$post_data = array(
			'post_type'    => 'listora_listing',
			'post_title'   => sanitize_text_field( $data['title'] ?? '' ),
			'post_content' => wp_kses_post( $data['content'] ?? '' ),
			'post_status'  => sanitize_text_field( $data['status'] ?? 'publish' ),
			'post_author'  => absint( $data['author_id'] ?? get_current_user_id() ),
		);

		if ( ! empty( $data['date'] ) ) {
			$post_data['post_date']     = $data['date'];
			$post_data['post_date_gmt'] = get_gmt_from_date( $data['date'] );
		}

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Track migration source.
		if ( ! empty( $data['source_id'] ) ) {
			update_post_meta( $post_id, '_listora_migrated_from', $this->source_slug . ':' . $data['source_id'] );
		}

		// Set featured image.
		if ( ! empty( $data['thumbnail'] ) ) {
			set_post_thumbnail( $post_id, absint( $data['thumbnail'] ) );
		}

		// Set meta fields.
		if ( ! empty( $data['meta'] ) && is_array( $data['meta'] ) ) {
			foreach ( $data['meta'] as $key => $value ) {
				if ( '' !== $value && null !== $value ) {
					\WBListora\Core\Meta_Handler::set_value( $post_id, $key, $value );
				}
			}
		}

		// Set taxonomies.
		if ( ! empty( $data['taxonomies'] ) && is_array( $data['taxonomies'] ) ) {
			foreach ( $data['taxonomies'] as $taxonomy => $terms ) {
				if ( ! empty( $terms ) ) {
					$term_ids = $this->map_taxonomy_terms( $terms, $taxonomy );
					if ( ! empty( $term_ids ) ) {
						wp_set_object_terms( $post_id, $term_ids, $taxonomy );
					}
				}
			}
		}

		return $post_id;
	}

	/**
	 * Map source taxonomy terms to Listora taxonomy, creating if needed.
	 *
	 * @param array  $terms    Array of term names or objects with 'name' and optional 'parent'.
	 * @param string $taxonomy Target Listora taxonomy.
	 * @return int[] Array of term IDs.
	 */
	protected function map_taxonomy_terms( $terms, $taxonomy ) {
		$term_ids = array();

		foreach ( $terms as $term_data ) {
			$term_name = is_array( $term_data ) ? ( $term_data['name'] ?? '' ) : $term_data;
			$term_name = trim( sanitize_text_field( $term_name ) );

			if ( '' === $term_name ) {
				continue;
			}

			$existing = term_exists( $term_name, $taxonomy );

			if ( $existing ) {
				$term_ids[] = is_array( $existing ) ? (int) $existing['term_id'] : (int) $existing;
			} else {
				$parent_id = 0;
				if ( is_array( $term_data ) && ! empty( $term_data['parent'] ) ) {
					$parent_name = sanitize_text_field( $term_data['parent'] );
					$parent_term = term_exists( $parent_name, $taxonomy );
					if ( $parent_term ) {
						$parent_id = is_array( $parent_term ) ? (int) $parent_term['term_id'] : (int) $parent_term;
					} else {
						$new_parent = wp_insert_term( $parent_name, $taxonomy );
						if ( ! is_wp_error( $new_parent ) ) {
							$parent_id = (int) $new_parent['term_id'];
						}
					}
				}

				$new_term = wp_insert_term(
					$term_name,
					$taxonomy,
					array( 'parent' => $parent_id )
				);

				if ( ! is_wp_error( $new_term ) ) {
					$term_ids[] = (int) $new_term['term_id'];
				}
			}
		}

		return $term_ids;
	}

	/**
	 * Insert or update geo data for a listing.
	 *
	 * @param int   $post_id The listing post ID.
	 * @param float $lat     Latitude.
	 * @param float $lng     Longitude.
	 * @param array $address Optional address components: address, city, state, country, postal_code.
	 */
	protected function insert_geo( $post_id, $lat, $lng, $address = array() ) {
		if ( empty( $lat ) && empty( $lng ) ) {
			return;
		}

		// Store address meta in the format the indexer expects.
		$addr_meta = array(
			'lat'         => (float) $lat,
			'lng'         => (float) $lng,
			'address'     => sanitize_text_field( $address['address'] ?? '' ),
			'city'        => sanitize_text_field( $address['city'] ?? '' ),
			'state'       => sanitize_text_field( $address['state'] ?? '' ),
			'country'     => sanitize_text_field( $address['country'] ?? '' ),
			'postal_code' => sanitize_text_field( $address['postal_code'] ?? '' ),
		);

		\WBListora\Core\Meta_Handler::set_value( $post_id, 'address', $addr_meta );
	}

	/**
	 * Trigger search index update for a migrated listing.
	 *
	 * @param int $post_id The listing post ID.
	 */
	protected function index_listing( $post_id ) {
		$indexer = new \WBListora\Search\Search_Indexer();
		$indexer->index_listing( $post_id, get_post( $post_id ) );
	}

	/**
	 * Insert a review into the Listora reviews table.
	 *
	 * @param array $review_data {
	 *     @type int    $listing_id     Listora listing ID.
	 *     @type int    $user_id        User ID.
	 *     @type int    $overall_rating Rating 1-5.
	 *     @type string $title          Review title.
	 *     @type string $content        Review content.
	 *     @type string $status         Review status (default 'approved').
	 *     @type string $created_at     Review date.
	 * }
	 * @return int|false Inserted review ID or false on failure.
	 */
	protected function insert_review( $review_data ) {
		global $wpdb;

		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		$data = array(
			'listing_id'     => absint( $review_data['listing_id'] ?? 0 ),
			'user_id'        => absint( $review_data['user_id'] ?? 0 ),
			'overall_rating' => min( 5, max( 1, absint( $review_data['overall_rating'] ?? 5 ) ) ),
			'title'          => sanitize_text_field( $review_data['title'] ?? '' ),
			'content'        => sanitize_textarea_field( $review_data['content'] ?? '' ),
			'status'         => sanitize_text_field( $review_data['status'] ?? 'approved' ),
			'created_at'     => $review_data['created_at'] ?? current_time( 'mysql' ),
			'updated_at'     => current_time( 'mysql' ),
		);

		if ( empty( $data['listing_id'] ) || empty( $data['content'] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$inserted = $wpdb->insert( "{$prefix}reviews", $data );

		return $inserted ? $wpdb->insert_id : false;
	}

	/**
	 * Get source terms for a given taxonomy and post.
	 *
	 * @param int    $post_id  Source post ID.
	 * @param string $taxonomy Source taxonomy name.
	 * @return string[] Array of term names.
	 */
	protected function get_source_terms( $post_id, $taxonomy ) {
		$terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'names' ) );

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		return $terms;
	}

	/**
	 * Safely get post meta, returning a default if empty.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	protected function get_source_meta( $post_id, $key, $default = '' ) {
		$value = get_post_meta( $post_id, $key, true );
		return ( '' !== $value && false !== $value ) ? $value : $default;
	}

	/**
	 * Get all registered migrators.
	 *
	 * @return Migration_Base[]
	 */
	public static function get_migrators() {
		return array(
			new Directorist_Migrator(),
			new Geodirectory_Migrator(),
			new BDP_Migrator(),
			new Listingpro_Migrator(),
		);
	}
}
