<?php
/**
 * Search Indexer — populates search_index, field_index, geo, and hours tables.
 *
 * @package WBListora\Search
 */

namespace WBListora\Search;

use WBListora\Contracts\Search_Indexer_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks into post save/delete to maintain search indexes.
 *
 * Implements {@see Search_Indexer_Interface} so Pro / extensions can resolve
 * it via wb_listora_service( 'search_indexer' ).
 */
class Search_Indexer implements Search_Indexer_Interface {

	/**
	 * Cron hook used to drive the chunked background reindex.
	 *
	 * Bumping the indexer schema (e.g. adding a new field to meta_text)
	 * leaves existing search_index rows stale — `Migrator::maybe_migrate()`
	 * calls {@see self::schedule_full_reindex()} on upgrade, which kicks
	 * off this chain and re-schedules itself until every listing is done.
	 */
	const REINDEX_CRON_HOOK = 'wb_listora_search_reindex';

	/**
	 * Per-tick batch size for the background reindex.
	 *
	 * Sized so a single tick stays well under PHP max_execution_time on
	 * commodity hosts (~250 indexings/sec is normal). On a 100k-listing
	 * directory the chain finishes in roughly 10–15 minutes of cron ticks.
	 */
	const REINDEX_CHUNK_SIZE = 200;

	/**
	 * Register hooks for index maintenance.
	 */
	public function register_hooks() {
		add_action( 'save_post_listora_listing', array( $this, 'index_listing' ), 20, 2 );
		add_action( 'transition_post_status', array( $this, 'on_status_change' ), 20, 3 );
		add_action( 'before_delete_post', array( $this, 'remove_from_index' ), 10, 1 );
		add_action( 'trashed_post', array( $this, 'remove_from_index' ), 10, 1 );
		// Backfill for listings hard-deleted before the 1.4.1 cascade shipped.
		// The hooks above only fire for deletes going forward.
		add_action( 'wb_listora_daily_cleanup', array( $this, 'purge_orphans_cron' ) );
		// Re-index after taxonomy terms change — frontend submission calls
		// wp_set_object_terms() AFTER wp_insert_post, so the save_post indexer
		// above runs before the listing_type term exists. Without this hook
		// the search_index row gets listing_type="" until the next save.
		add_action( 'set_object_terms', array( $this, 'on_terms_changed' ), 20, 6 );

		// Re-index at the tail of the submission flow — meta (address for
		// lat/lng/city/country, featured flag, verified flag, claimed flag)
		// is saved AFTER the save_post / set_object_terms hooks above, so
		// those early index runs miss geo + featured state. These actions
		// fire once the transaction has committed, catching everything.
		add_action( 'wb_listora_after_create_listing', array( $this, 'reindex_after_listing_action' ), 20, 1 );
		add_action( 'wb_listora_after_update_listing', array( $this, 'reindex_after_listing_action' ), 20, 1 );

		// Featuring writes only meta (dashboard, REST, the expiry sweep, Pro plan
		// perks, the admin box), so no save_post runs: reindex on the meta itself,
		// or the Featured block and featured filter lag until the next edit.
		foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) {
			add_action( $hook, array( $this, 'on_featured_meta_changed' ), 20, 3 );
		}
		// wp_delete_post() removes the row (before_delete_post) and only then
		// deletes the meta; a reindex from that meta delete would bring the
		// deleted listing back into search. Track deletes in progress.
		add_action( 'before_delete_post', array( $this, 'mark_deleting' ), 1, 1 );
		add_action( 'deleted_post', array( $this, 'unmark_deleting' ), 10, 1 );

		// Background reindex chain — kicked off by Migrator on upgrades.
		add_action( self::REINDEX_CRON_HOOK, array( $this, 'process_scheduled_reindex' ) );
	}

	/**
	 * Reindex a listing when its featured flag or featured-until date changes.
	 *
	 * @since 2.0.0
	 *
	 * @param int|int[] $meta_id   Meta ID(s).
	 * @param int       $object_id Post ID.
	 * @param string    $meta_key  Meta key.
	 * @return void
	 */
	public function on_featured_meta_changed( $meta_id, $object_id, $meta_key ): void {
		$object_id = (int) $object_id;
		if ( ! in_array( $meta_key, array( \WBListora\Core\Featured::META_IS_FEATURED, \WBListora\Core\Featured::META_FEATURED_UNTIL ), true )
			|| isset( $this->deleting[ $object_id ] )
			|| 'listora_listing' !== get_post_type( $object_id )
			|| 'trash' === get_post_status( $object_id ) ) {
			return;
		}
		$this->reindex_after_listing_action( $object_id );
	}

	/**
	 * Listings being hard-deleted in this request.
	 *
	 * @var array<int, true>
	 */
	private $deleting = array();

	/**
	 * `before_delete_post`: note the listing so its meta deletes do not reindex it.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function mark_deleting( $post_id ): void {
		$this->deleting[ (int) $post_id ] = true;
	}

	/**
	 * `deleted_post`: the delete finished.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function unmark_deleting( $post_id ): void {
		unset( $this->deleting[ (int) $post_id ] );
	}

	/**
	 * Kick off a background full reindex.
	 *
	 * Called from migrations whenever the indexer's output schema changes
	 * (a new field added to meta_text, a new taxonomy indexed, etc.). Resets
	 * the offset cursor and schedules the first cron tick — subsequent ticks
	 * re-schedule themselves until every listing has been re-indexed.
	 */
	public static function schedule_full_reindex(): void {
		delete_option( 'wb_listora_reindex_offset' );
		\WBListora\Workflow\Cron_Scheduler::schedule_single( self::REINDEX_CRON_HOOK, array(), 30 );
	}

	/**
	 * Cron handler — process one chunk and re-schedule if more remain.
	 *
	 * Uses an option-stored offset cursor so progress survives crashes /
	 * timeouts: if a tick fails halfway, the next tick resumes from the
	 * last successfully processed batch instead of restarting at zero.
	 */
	public function process_scheduled_reindex(): void {
		$offset = (int) get_option( 'wb_listora_reindex_offset', 0 );

		$processed = $this->reindex_chunk( $offset, self::REINDEX_CHUNK_SIZE );

		if ( $processed >= self::REINDEX_CHUNK_SIZE ) {
			// Likely more listings remain: advance the cursor and queue the
			// next tick (Action Scheduler first, so it also runs where WP-Cron is off).
			update_option( 'wb_listora_reindex_offset', $offset + $processed, false );
			\WBListora\Workflow\Cron_Scheduler::schedule_single( self::REINDEX_CRON_HOOK, array(), 60 );
			return;
		}

		// Final batch (smaller than chunk size or empty) — chain is done.
		delete_option( 'wb_listora_reindex_offset' );
	}

	/**
	 * Re-index a single chunk of listings starting at the given offset.
	 *
	 * Kept separate from {@see self::batch_reindex()} (which is for CLI)
	 * so the cron path can advance through the post type with a stable
	 * sort and a minimum number of WP_Query calls per tick.
	 *
	 * @param int $offset Number of listings to skip before starting.
	 * @param int $limit  Max listings to process this call.
	 * @return int Number of listings actually processed.
	 */
	public function reindex_chunk( int $offset, int $limit ): int {
		$query = new \WP_Query(
			array(
				'post_type'      => 'listora_listing',
				'post_status'    => 'any',
				'posts_per_page' => $limit,
				'offset'         => $offset,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$ids = $query->posts;
		if ( empty( $ids ) ) {
			return 0;
		}

		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( $post ) {
				$this->index_listing( (int) $id, $post );
			}
		}

		return count( $ids );
	}

	/**
	 * Re-index after a listing is created or updated via the submission flow.
	 *
	 * @param int $post_id Post ID.
	 */
	public function reindex_after_listing_action( $post_id ): void {
		$post = get_post( $post_id );
		if ( $post ) {
			$this->index_listing( $post_id, $post );
		}
	}

	/**
	 * Re-index when a listing's taxonomy terms change.
	 *
	 * Only triggers for our own taxonomies on listora_listing posts to keep
	 * this hook cheap on sites with lots of unrelated taxonomy writes.
	 *
	 * @param int              $object_id  Object ID.
	 * @param array<int|string> $terms      Terms applied (unused).
	 * @param array<int>       $tt_ids     Term taxonomy IDs (unused).
	 * @param string           $taxonomy   Taxonomy.
	 * @param bool             $append     Whether appending (unused).
	 * @param array<int>       $old_tt_ids Old term taxonomy IDs (unused).
	 */
	public function on_terms_changed( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ): void {
		static $our_taxonomies = array(
			'listora_listing_type',
			'listora_listing_cat',
			'listora_listing_location',
			'listora_listing_feature',
		);

		if ( ! in_array( $taxonomy, $our_taxonomies, true ) ) {
			return;
		}

		if ( 'listora_listing' !== get_post_type( $object_id ) ) {
			return;
		}

		$this->index_listing( $object_id, get_post( $object_id ) );
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

		// Listings sitting in pending_verification haven't been confirmed
		// yet — keep them entirely out of the search index until the user
		// clicks the email link. An auto-draft is the empty shell WordPress
		// creates when Add New opens; it is not a listing yet.
		if ( in_array( $post->post_status, array( 'pending_verification', 'auto-draft' ), true ) ) {
			$this->remove_from_index( $post_id );
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
		// `listora_listing_type` is included so a query like "italian restaurant"
		// matches a Restaurant-type listing tagged "Italian"; without it, the
		// FULLTEXT AND-mode query would drop every listing because "restaurant"
		// would never appear in the indexed text.
		$tax_terms = array();
		foreach ( array( 'listora_listing_type', 'listora_listing_cat', 'listora_listing_tag', 'listora_listing_feature', 'listora_listing_location' ) as $tax ) {
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

		// Index human-readable address fragments (city, region, country, full
		// address line) so queries like "Italian Manhattan" or "hotel paris"
		// match the right listings even though those tokens live in a meta
		// array, not the post content.
		if ( is_array( $addr ) ) {
			$addr_text = trim(
				implode(
					' ',
					array_filter(
						array(
							(string) ( $addr['address'] ?? '' ),
							(string) ( $addr['city'] ?? '' ),
							(string) ( $addr['region'] ?? '' ),
							(string) ( $addr['state'] ?? '' ),
							(string) ( $addr['country'] ?? '' ),
							(string) ( $addr['postal_code'] ?? '' ),
						)
					)
				)
			);
			if ( '' !== $addr_text ) {
				$meta_parts[] = $addr_text;
			}
		}

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

		$rating = \WBListora\Core\Listing_Data::rating_aggregate( $post_id );

		$wpdb->replace(
			"{$prefix}search_index", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is the site table prefix plus a plugin constant.
			array(
				'listing_id'   => $post_id,
				'listing_type' => $type_slug,
				'status'       => $post->post_status,
				'title'        => $post->post_title,
				'content_text' => wp_strip_all_tags( $post->post_content ),
				'meta_text'    => implode( ' ', $meta_parts ),
				'avg_rating'   => $rating['avg_rating'],
				'review_count' => $rating['review_count'],
				'is_featured'  => \WBListora\Core\Featured::is_featured( $post_id ) ? 1 : 0,
				'is_verified'  => wb_listora_is_verified( $post_id ) ? 1 : 0,
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom plugin table, no WP API, table name is the site table prefix
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
							"{$prefix}field_index", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is the site table prefix plus a plugin constant.
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
					"{$prefix}field_index", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is the site table prefix plus a plugin constant.
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
					"{$prefix}field_index", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is the site table prefix plus a plugin constant.
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
					"{$prefix}field_index", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is the site table prefix plus a plugin constant.
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
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom plugin table, no WP API, table name is the site table prefix
			$wpdb->delete( "{$prefix}geo", array( 'listing_id' => $post_id ) );
			return;
		}

		$lat = (float) $addr['lat'];
		$lng = (float) $addr['lng'];

		$wpdb->replace(
			"{$prefix}geo", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is the site table prefix plus a plugin constant.
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom plugin table, no WP API, table name is the site table prefix
		$wpdb->delete( "{$prefix}hours", array( 'listing_id' => $post_id ) );

		if ( ! is_array( $hours ) || empty( $hours ) ) {
			return;
		}

		// Store the empty string (not a bare 'UTC') when the listing has no
		// explicit zone, so the geo row is not a silent UTC sentinel. Consumers
		// resolve an empty geo timezone to the SITE timezone via the canonical
		// resolver / the Open-now site-bucket, keeping search and detail aligned.
		$tz = get_post_meta( $post_id, '_listora_timezone', true ) ?: '';

		/*
		 * A day may carry more than one range — a lunch or riposo break, or a
		 * split retail shift — so each entry for the same day gets the next
		 * `slot`. Without this every entry would be written as slot 0 and the
		 * second one would be silently dropped by the primary key, which is
		 * exactly the failure the widened key exists to prevent.
		 *
		 * Capped at WB_LISTORA_MAX_HOURS_SLOTS. The cap is enforced here as well
		 * as in the input UI because this method is the last thing between any
		 * writer — REST, an importer, a migration — and the table.
		 */
		$slot_for_day = array();

		foreach ( self::normalise_hours_meta( $hours ) as $day ) {
			$day_of_week = (int) $day['day'];
			$slot        = $slot_for_day[ $day_of_week ] ?? 0;

			if ( $slot >= self::max_hours_slots() ) {
				continue;
			}

			$slot_for_day[ $day_of_week ] = $slot + 1;

			$wpdb->insert(
				"{$prefix}hours", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is the site table prefix plus a plugin constant.
				array(
					'listing_id'  => $post_id,
					'day_of_week' => $day_of_week,
					'slot'        => $slot,
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
	 * Flatten whatever shape `business_hours` was stored in into one list.
	 *
	 *   list  [ { day:1, open, close }, ... ]          canonical
	 *   dict  [ 1 => { open, close }, ... ]            the frontend submission form
	 *   slots [ 1 => [ { open, close }, { ... } ] ]    more than one range per day
	 *
	 * List-shaped data is returned unchanged. Read it through
	 * `wb_listora_normalize_hours()`, not this class (INV-3).
	 *
	 * @since 1.5.0
	 *
	 * @param array<mixed> $hours Raw meta value.
	 * @return array<int, array<string, mixed>> Entries guaranteed to carry an integer `day`.
	 */
	public static function normalise_hours_meta( $hours ): array {
		$out = array();

		foreach ( (array) $hours as $key => $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			// Canonical: the entry names its own day.
			if ( isset( $entry['day'] ) ) {
				$out[] = $entry;
				continue;
			}

			// Otherwise the KEY is the day, and only a 0-6 key can be one.
			if ( ! is_int( $key ) && ! ctype_digit( (string) $key ) ) {
				continue;
			}

			$day_of_week = (int) $key;

			if ( $day_of_week < 0 || $day_of_week > 6 ) {
				continue;
			}

			/*
			 * `ranges` is the explicit multi-range shape the submission form
			 * posts. It exists so a day can carry BOTH state (closed / is_24h,
			 * which belong to the day) and several time ranges, without the
			 * day's array holding a mix of numeric and named keys that could
			 * not be classified reliably.
			 */
			if ( isset( $entry['ranges'] ) && is_array( $entry['ranges'] ) ) {
				$day_state = $entry;
				unset( $day_state['ranges'] );

				// A closed or 24h day has no ranges to record; keep the state row.
				if ( ! empty( $day_state['closed'] ) || ! empty( $day_state['is_24h'] ) ) {
					$day_state['day'] = $day_of_week;
					$out[]            = $day_state;
					continue;
				}

				foreach ( $entry['ranges'] as $range ) {
					if ( ! is_array( $range ) || empty( $range['open'] ) ) {
						continue;
					}

					$out[] = array_merge( $day_state, $range, array( 'day' => $day_of_week ) );
				}

				continue;
			}

			// A bare list of ranges, or a single range.
			$ranges = self::looks_like_range_list( $entry ) ? $entry : array( $entry );

			foreach ( $ranges as $range ) {
				if ( is_array( $range ) ) {
					$range['day'] = $day_of_week;
					$out[]        = $range;
				}
			}
		}

		return $out;
	}

	/**
	 * Whether a day's value holds several ranges rather than one.
	 *
	 * A range is an associative array of open/close/closed/is_24h; a list of
	 * ranges is a numerically-indexed array of those. Distinguishing them by
	 * key type rather than by guessing at contents keeps a single range whose
	 * keys happen to be numeric from being mistaken for a list.
	 *
	 * @param array<mixed> $value One day's stored value.
	 * @return bool
	 */
	private static function looks_like_range_list( array $value ): bool {
		if ( ! $value ) {
			return false;
		}

		foreach ( array_keys( $value ) as $k ) {
			if ( ! is_int( $k ) && ! ctype_digit( (string) $k ) ) {
				return false;
			}
		}

		return is_array( reset( $value ) );
	}

	/**
	 * How many opening ranges one day may hold.
	 *
	 * Three covers a lunch break and a split retail shift, which is what people
	 * actually ask for, while keeping a day readable on a listing card. An
	 * unbounded list invites a twelve-range day that no card can render and no
	 * visitor can parse.
	 *
	 * @since 1.5.0
	 *
	 * @return int
	 */
	public static function max_hours_slots(): int {
		/**
		 * Filters the maximum number of opening ranges per day.
		 *
		 * Raising this also needs the input UI to offer the extra rows; the
		 * storage and the readers have no other limit.
		 *
		 * @since 1.5.0
		 *
		 * @param int $max Maximum ranges per day.
		 */
		return max( 1, (int) apply_filters( 'wb_listora_max_hours_slots', 3 ) );
	}

	/**
	 * Handle status transitions.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old Old status.
	 * @param \WP_Post $post Post.
	 */
	public function on_status_change( $new_status, $old, $post ) {
		if ( 'listora_listing' !== $post->post_type || $new_status === $old ) {
			return;
		}

		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		$wpdb->update(
			"{$prefix}search_index", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is the site table prefix plus a plugin constant.
			array( 'status' => $new_status ),
			array( 'listing_id' => $post->ID )
		);

		$this->invalidate_caches( $post->ID );
		do_action( 'wb_listora_listing_status_changed', $post->ID, $new_status, $old );
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
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom plugin table, no WP API, table name is the site table prefix
			$wpdb->delete( "{$prefix}{$table}", array( 'listing_id' => $post_id ) );
		}

		$this->invalidate_caches( $post_id );
	}

	/**
	 * Void adapter for the daily-cleanup action.
	 *
	 * `purge_orphans()` returns per-table counts because `wp listora cleanup`
	 * reports them. An action callback must return nothing, so the cron path
	 * goes through here instead of hooking the counting method directly.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	public function purge_orphans_cron(): void {
		$this->purge_orphans();
	}

	/**
	 * Purge index rows whose listing no longer exists.
	 *
	 * Search_Engine::phase_1_candidates() reads search_index with no join to
	 * wp_posts, so orphan rows inflate totals and pagination while their cards
	 * fail to hydrate. Listing_Data_Eraser leaves these tables to this class.
	 * Post IDs are never reused, so a `listing_id` without a post is permanent.
	 *
	 * @since 1.4.2
	 *
	 * @return array<string, int> Table (unprefixed) => rows deleted.
	 */
	public function purge_orphans() {
		global $wpdb;
		$prefix  = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$deleted = array();

		foreach ( array( 'search_index', 'field_index', 'geo', 'hours' ) as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- backfill over constant-built custom tables.
			$deleted[ $table ] = (int) $wpdb->query(
				"DELETE t FROM {$prefix}{$table} t
				 LEFT JOIN {$wpdb->posts} p ON p.ID = t.listing_id
				 WHERE p.ID IS NULL"
			);
		}

		if ( array_sum( $deleted ) > 0 ) {
			$this->invalidate_caches( 0 );
		}

		return $deleted;
	}

	/**
	 * Invalidate search-related caches.
	 *
	 * Bumps the `wp_cache_set_last_changed( $group )` incrementor, orphaning
	 * every key that embedded the old value, instead of a LIKE scan over
	 * wp_options that would bypass the object cache.
	 *
	 * @param int $post_id Post ID — kept for back-compat with existing
	 *                     callers; bump is global so the parameter is
	 *                     advisory only.
	 */
	private function invalidate_caches( $post_id ) {
		\WBListora\Core\Cache::bump( \WBListora\Core\Cache::GROUP_LISTINGS );
		// Reviews ride the same listings group via Cache::bump_reviews(),
		// but search facets also recompute on category/feature changes
		// triggered from outside the review hooks — bump dashboard too
		// so per-user aggregates pick up the change.
		\WBListora\Core\Cache::bump( \WBListora\Core\Cache::GROUP_DASHBOARD );

		unset( $post_id ); // Reserved for future per-listing invalidation.
	}

	/**
	 * Batch-fetch denormalized review stats for a set of listings.
	 *
	 * One prepared `WHERE listing_id IN (…)` query. Callers rendering many
	 * cards (the listing-grid block) prime this once before their loop so
	 * wb_listora_prepare_card_data() does not query per card.
	 *
	 * @param array<int> $ids Listing post IDs.
	 * @return array<int, array{avg_rating: float, review_count: int}> Map keyed by listing_id. Missing IDs are absent.
	 */
	public static function get_index_rows_for_ids( array $ids ): array {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		if ( empty( $ids ) ) {
			return array();
		}

		global $wpdb;
		$prefix       = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom plugin table, no WP API, site table prefix plus generated IN placeholders
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT listing_id, avg_rating, review_count FROM {$prefix}search_index WHERE listing_id IN ({$placeholders})",
				...$ids
			),
			ARRAY_A
		);

		$map = array();
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$map[ (int) $row['listing_id'] ] = array(
					'avg_rating'   => (float) $row['avg_rating'],
					'review_count' => (int) $row['review_count'],
				);
			}
		}

		return $map;
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
