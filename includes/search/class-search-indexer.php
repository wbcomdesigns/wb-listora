<?php
/**
 * Search Indexer — populates search_index, field_index, geo, and hours tables.
 *
 * @package WBListora\Search
 */

namespace WBListora\Search;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks into post save/delete to maintain search indexes.
 */
class Search_Indexer {

	/**
	 * Register hooks for index maintenance.
	 */
	public function register_hooks() {
		add_action( 'save_post_listora_listing', array( $this, 'index_listing' ), 20, 2 );
		add_action( 'transition_post_status', array( $this, 'on_status_change' ), 20, 3 );
		add_action( 'before_delete_post', array( $this, 'remove_from_index' ), 10, 1 );
		add_action( 'trashed_post', array( $this, 'remove_from_index' ), 10, 1 );
	}

	/**
	 * Index a single listing.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function index_listing( $post_id, $post = null ) {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( null === $post ) {
			$post = get_post( $post_id );
		}

		if ( ! $post || 'listora_listing' !== $post->post_type ) {
			return;
		}

		$this->update_search_index( $post_id, $post );
		$this->update_field_index( $post_id );
		$this->update_geo_index( $post_id );
		$this->update_hours_index( $post_id );
		$this->invalidate_caches( $post_id );

		do_action( 'wb_listora_listing_indexed', $post_id );
	}

	/**
	 * Update the search_index table.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	private function update_search_index( $post_id, $post ) {
		global $wpdb;

		$prefix    = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$registry  = \WBListora\Core\Listing_Type_Registry::instance();
		$type      = $registry->get_for_post( $post_id );
		$type_slug = $type ? $type->get_slug() : '';

		// Build meta_text from searchable fields.
		$meta_parts = array();
		if ( $type ) {
			foreach ( $type->get_searchable_fields() as $field ) {
				$value = \WBListora\Core\Meta_Handler::get_value( $post_id, $field->get_key() );
				if ( is_array( $value ) ) {
					$meta_parts[] = implode( ' ', $value );
				} elseif ( is_string( $value ) && '' !== $value ) {
					$meta_parts[] = $value;
				}
			}
		}

		// Also include taxonomy terms as searchable text.
		$tax_terms = array();
		foreach ( array( 'listora_listing_cat', 'listora_listing_tag', 'listora_listing_feature' ) as $tax ) {
			$terms = wp_get_object_terms( $post_id, $tax, array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $terms ) ) {
				$tax_terms = array_merge( $tax_terms, $terms );
			}
		}
		if ( ! empty( $tax_terms ) ) {
			$meta_parts[] = implode( ' ', $tax_terms );
		}

		// Service titles and descriptions — makes services searchable via listing search.
		$services     = \WBListora\Core\Services::get_services( $post_id );
		$service_text = '';
		foreach ( $services as $svc ) {
			$service_text .= ' ' . $svc['title'] . ' ' . $svc['description'];
		}
		$service_text = trim( $service_text );
		if ( '' !== $service_text ) {
			$meta_parts[] = $service_text;
		}

		// Location data.
		$addr    = \WBListora\Core\Meta_Handler::get_value( $post_id, 'address', array() );
		$lat     = is_array( $addr ) ? (float) ( $addr['lat'] ?? 0 ) : 0;
		$lng     = is_array( $addr ) ? (float) ( $addr['lng'] ?? 0 ) : 0;
		$city    = is_array( $addr ) ? ( $addr['city'] ?? '' ) : '';
		$country = is_array( $addr ) ? ( $addr['country'] ?? '' ) : '';

		// Price.
		$price_data  = \WBListora\Core\Meta_Handler::get_value( $post_id, 'price', null );
		$price_value = 0;
		if ( is_array( $price_data ) && isset( $price_data['amount'] ) ) {
			$price_value = (float) $price_data['amount'];
		} elseif ( is_numeric( $price_data ) ) {
			$price_value = (float) $price_data;
		}
		// Also check price_per_night (hotel), ticket_price (event), consultation_fee (healthcare).
		foreach ( array( 'price_per_night', 'ticket_price', 'consultation_fee' ) as $alt_price ) {
			if ( 0 === $price_value ) {
				$alt = \WBListora\Core\Meta_Handler::get_value( $post_id, $alt_price, null );
				if ( is_array( $alt ) && isset( $alt['amount'] ) ) {
					$price_value = (float) $alt['amount'];
				} elseif ( is_numeric( $alt ) ) {
					$price_value = (float) $alt;
				}
			}
		}

		// Review stats.
		$review_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT AVG(overall_rating) as avg_r, COUNT(*) as cnt
			FROM {$prefix}reviews WHERE listing_id = %d AND status = 'approved'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$post_id
			),
			ARRAY_A
		);

		$wpdb->replace(
			"{$prefix}search_index", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			array(
				'listing_id'   => $post_id,
				'listing_type' => $type_slug,
				'status'       => $post->post_status,
				'title'        => $post->post_title,
				'content_text' => wp_strip_all_tags( $post->post_content ),
				'meta_text'    => implode( ' ', $meta_parts ),
				'avg_rating'   => $review_row ? round( (float) $review_row['avg_r'], 2 ) : 0,
				'review_count' => $review_row ? (int) $review_row['cnt'] : 0,
				'is_featured'  => \WBListora\Core\Featured::is_featured( $post_id ) ? 1 : 0,
				'is_verified'  => (int) get_post_meta( $post_id, '_listora_is_verified', true ),
				'is_claimed'   => (int) get_post_meta( $post_id, '_listora_is_claimed', true ),
				'author_id'    => (int) $post->post_author,
				'lat'          => $lat,
				'lng'          => $lng,
				'city'         => $city,
				'country'      => $country,
				'price_value'  => $price_value,
				'created_at'   => $post->post_date_gmt,
				'updated_at'   => current_time( 'mysql', true ),
			)
		);
	}

	/**
	 * Update the field_index table.
	 *
	 * @param int $post_id Post ID.
	 */
	private function update_field_index( $post_id ) {
		global $wpdb;

		$prefix   = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$registry = \WBListora\Core\Listing_Type_Registry::instance();
		$type     = $registry->get_for_post( $post_id );

		// Clear existing rows.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->delete( "{$prefix}field_index", array( 'listing_id' => $post_id ) );

		if ( ! $type ) {
			return;
		}

		$type_slug  = $type->get_slug();
		$skip_types = array( 'map_location', 'business_hours', 'gallery', 'social_links', 'wysiwyg', 'file', 'video' );

		foreach ( $type->get_filterable_fields() as $field ) {
			$key   = $field->get_key();
			$ftype = $field->get_type();
			$value = \WBListora\Core\Meta_Handler::get_value( $post_id, $key );

			if ( '' === $value || null === $value || in_array( $ftype, $skip_types, true ) ) {
				continue;
			}

			if ( is_array( $value ) && ! isset( $value['amount'] ) ) {
				// Multiselect: one row per value.
				foreach ( $value as $v ) {
					if ( '' !== $v ) {
						$wpdb->insert(
							"{$prefix}field_index", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
							array(
								'listing_id'    => $post_id,
								'field_key'     => $key,
								'field_value'   => (string) $v,
								'numeric_value' => is_numeric( $v ) ? (float) $v : null,
								'listing_type'  => $type_slug,
							)
						);
					}
				}
			} elseif ( is_array( $value ) && isset( $value['amount'] ) ) {
				// Price object.
				$wpdb->insert(
					"{$prefix}field_index", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					array(
						'listing_id'    => $post_id,
						'field_key'     => $key,
						'field_value'   => (string) $value['amount'],
						'numeric_value' => (float) $value['amount'],
						'listing_type'  => $type_slug,
					)
				);
			} elseif ( 'checkbox' === $ftype ) {
				$wpdb->insert(
					"{$prefix}field_index", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					array(
						'listing_id'    => $post_id,
						'field_key'     => $key,
						'field_value'   => $value ? '1' : '0',
						'numeric_value' => $value ? 1.0 : 0.0,
						'listing_type'  => $type_slug,
					)
				);
			} else {
				$wpdb->insert(
					"{$prefix}field_index", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					array(
						'listing_id'    => $post_id,
						'field_key'     => $key,
						'field_value'   => (string) $value,
						'numeric_value' => is_numeric( $value ) ? (float) $value : null,
						'listing_type'  => $type_slug,
					)
				);
			}
		}
	}

	/**
	 * Update the geo table.
	 *
	 * @param int $post_id Post ID.
	 */
	private function update_geo_index( $post_id ) {
		global $wpdb;

		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$addr   = \WBListora\Core\Meta_Handler::get_value( $post_id, 'address', array() );

		if ( ! is_array( $addr ) || empty( $addr['lat'] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->delete( "{$prefix}geo", array( 'listing_id' => $post_id ) );
			return;
		}

		$lat = (float) $addr['lat'];
		$lng = (float) $addr['lng'];

		$wpdb->replace(
			"{$prefix}geo", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			array(
				'listing_id'  => $post_id,
				'lat'         => $lat,
				'lng'         => $lng,
				'address'     => $addr['address'] ?? '',
				'city'        => $addr['city'] ?? '',
				'state'       => $addr['state'] ?? '',
				'country'     => $addr['country'] ?? '',
				'postal_code' => $addr['postal_code'] ?? '',
				'geohash'     => Geo_Query::encode_geohash( $lat, $lng ),
				'timezone'    => get_post_meta( $post_id, '_listora_timezone', true ) ?: '',
			)
		);
	}

	/**
	 * Update the hours table.
	 *
	 * @param int $post_id Post ID.
	 */
	private function update_hours_index( $post_id ) {
		global $wpdb;

		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$hours  = \WBListora\Core\Meta_Handler::get_value( $post_id, 'business_hours', array() );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->delete( "{$prefix}hours", array( 'listing_id' => $post_id ) );

		if ( ! is_array( $hours ) || empty( $hours ) ) {
			return;
		}

		$tz = get_post_meta( $post_id, '_listora_timezone', true ) ?: 'UTC';

		foreach ( $hours as $day ) {
			if ( ! isset( $day['day'] ) ) {
				continue;
			}
			$wpdb->insert(
				"{$prefix}hours", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array(
					'listing_id'  => $post_id,
					'day_of_week' => (int) $day['day'],
					'open_time'   => $day['open'] ?? null,
					'close_time'  => $day['close'] ?? null,
					'is_closed'   => ! empty( $day['closed'] ) ? 1 : 0,
					'is_24h'      => ! empty( $day['is_24h'] ) ? 1 : 0,
					'timezone'    => $tz,
				)
			);
		}
	}

	/**
	 * Handle status transitions.
	 *
	 * @param string   $new New status.
	 * @param string   $old Old status.
	 * @param \WP_Post $post Post.
	 */
	public function on_status_change( $new, $old, $post ) {
		if ( 'listora_listing' !== $post->post_type || $new === $old ) {
			return;
		}

		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		$wpdb->update(
			"{$prefix}search_index", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			array( 'status' => $new ),
			array( 'listing_id' => $post->ID )
		);

		$this->invalidate_caches( $post->ID );
		do_action( 'wb_listora_listing_status_changed', $post->ID, $new, $old );
	}

	/**
	 * Remove listing from all indexes.
	 *
	 * @param int $post_id Post ID.
	 */
	public function remove_from_index( $post_id ) {
		if ( 'listora_listing' !== get_post_type( $post_id ) ) {
			return;
		}

		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		foreach ( array( 'search_index', 'field_index', 'geo', 'hours' ) as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->delete( "{$prefix}{$table}", array( 'listing_id' => $post_id ) );
		}

		$this->invalidate_caches( $post_id );
	}

	/**
	 * Selective cache invalidation by listing type.
	 *
	 * @param int $post_id Post ID.
	 */
	private function invalidate_caches( $post_id ) {
		global $wpdb;

		$registry = \WBListora\Core\Listing_Type_Registry::instance();
		$type     = $registry->get_for_post( $post_id );
		$slug     = $type ? $type->get_slug() : 'unknown';

		$patterns = array(
			"_transient_listora_search_{$slug}_%",
			'_transient_listora_search_all_%',
			"_transient_listora_facets_{$slug}_%",
			'_transient_listora_facets_all_%',
		);

		foreach ( $patterns as $pattern ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					$pattern
				)
			);
			$timeout = str_replace( '_transient_', '_transient_timeout_', $pattern );
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					$timeout
				)
			);
		}
	}

	/**
	 * Batch reindex all listings.
	 *
	 * @param array $args Options.
	 * @return array Stats.
	 */
	public function batch_reindex( array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'type'       => '',
				'batch_size' => 500,
				'dry_run'    => false,
			)
		);

		$query_args = array(
			'post_type'      => 'listora_listing',
			'post_status'    => 'any',
			'posts_per_page' => $args['batch_size'],
			'paged'          => 1,
			'fields'         => 'ids',
		);

		if ( ! empty( $args['type'] ) ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'listora_listing_type',
					'field'    => 'slug',
					'terms'    => $args['type'],
				),
			);
		}

		$stats = array(
			'indexed' => 0,
			'skipped' => 0,
			'errors'  => 0,
		);

		do {
			$query = new \WP_Query( $query_args );
			$ids   = $query->posts;

			if ( empty( $ids ) ) {
				break;
			}

			foreach ( $ids as $id ) {
				if ( $args['dry_run'] ) {
					++$stats['indexed'];
					continue;
				}

				$post = get_post( $id );
				if ( ! $post ) {
					++$stats['errors'];
					continue;
				}

				$this->index_listing( $id, $post );
				++$stats['indexed'];
			}

			++$query_args['paged'];
			wp_cache_flush();

		} while ( count( $ids ) === $args['batch_size'] );

		return $stats;
	}
}
