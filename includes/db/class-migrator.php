<?php
/**
 * Database migrator.
 *
 * @package WBListora\DB
 */

namespace WBListora\DB;

defined( 'ABSPATH' ) || exit;

/**
 * Handles versioned database migrations.
 */
class Migrator {

	/**
	 * Check if migration is needed and run it.
	 */
	public static function maybe_migrate() {
		$current_db_version = get_option( 'wb_listora_db_version', '0' );

		if ( version_compare( $current_db_version, WB_LISTORA_DB_VERSION, '>=' ) ) {
			return;
		}

		$migrations = self::get_migrations();

		foreach ( $migrations as $version => $callback ) {
			if ( version_compare( $current_db_version, $version, '<' ) ) {
				call_user_func( $callback );

				if ( function_exists( 'wb_listora_log' ) ) {
					wb_listora_log( "Migration {$version} completed." );
				}
			}
		}

		update_option( 'wb_listora_db_version', WB_LISTORA_DB_VERSION );
	}

	/**
	 * Get ordered list of migrations.
	 *
	 * @return array Version => callable pairs.
	 */
	private static function get_migrations() {
		return array(
			'1.0.0' => array( __CLASS__, 'migrate_1_0_0' ),
			'1.1.0' => array( __CLASS__, 'migrate_1_1_0' ),
			'1.2.0' => array( __CLASS__, 'migrate_1_2_0' ),
			'1.3.0' => array( __CLASS__, 'migrate_1_3_0' ),
			'1.4.0' => array( __CLASS__, 'migrate_1_4_0' ),
			'1.5.3' => array( __CLASS__, 'migrate_1_5_3' ),
			'1.6.0' => array( __CLASS__, 'migrate_1_6_0' ),
			'1.8.0' => array( __CLASS__, 'migrate_1_8_0' ),
		);
	}

	/**
	 * Migration 1.0.0 — Initial table creation.
	 * Tables are created by Activator, so this is a no-op for fresh installs.
	 * For upgrades from a pre-migration version, re-run dbDelta.
	 */
	public static function migrate_1_0_0() {
		// Activator::create_tables() already handles this via dbDelta.
		// dbDelta is safe to run multiple times — it only adds missing columns/indexes.
		\WBListora\Activator::activate();
	}

	/**
	 * Migration 1.1.0 — Adds the services table for the Listing Services feature.
	 *
	 * Sites activated before this feature shipped don't have wp_listora_services,
	 * so listing-detail renders hit "Table doesn't exist" on every load. Re-running
	 * the activator via dbDelta is idempotent and picks up the missing table
	 * without touching existing data.
	 */
	public static function migrate_1_1_0(): void {
		\WBListora\Activator::activate();
	}

	/**
	 * Migration 1.2.0 — Search index now contains taxonomy and address text.
	 *
	 * The 1.2.0 indexer adds the listing type name, location terms and the
	 * full address (city / region / country / postal code) to `meta_text`.
	 * Existing search_index rows still carry the 1.1.0 payload, so multi-word
	 * queries like "italian restaurant" or "manhattan italian" return
	 * inaccurate results until those rows are regenerated.
	 *
	 * We schedule a chunked background rebuild instead of running it inline:
	 * a synchronous full reindex on a directory with tens of thousands of
	 * listings would time out the upgrade flow. The cron chain processes
	 * 200 listings per tick and re-schedules itself until done; in the
	 * meantime the live event-driven indexer keeps fresh writes accurate.
	 */
	public static function migrate_1_2_0(): void {
		\WBListora\Search\Search_Indexer::schedule_full_reindex();
	}

	/**
	 * Migration 1.3.0 — Composite index for per-user review listings.
	 *
	 * The BuddyPress "My Reviews" profile tab (Pro) paginates with
	 * `WHERE user_id = %d AND status = 'approved' ORDER BY created_at DESC`.
	 * The pre-1.3.0 schema only had `idx_user (user_id)`, so that query
	 * filtered on status row-by-row and filesorted by created_at — fine for
	 * a handful of reviews, but a table scan on big directories. The new
	 * `idx_user_status_created (user_id, status, created_at)` covers the
	 * whole predicate + ordering. Re-running the activator via dbDelta adds
	 * the missing index in place without touching existing rows.
	 */
	public static function migrate_1_3_0(): void {
		\WBListora\Activator::activate();
	}

	/**
	 * Migration 1.4.0 — Composite index for the dashboard Claims tab.
	 *
	 * The vendor dashboard "My Claims" tab paginates with
	 * `WHERE user_id = %d ORDER BY created_at DESC`. The pre-1.4.0 schema
	 * only had `idx_user (user_id)`, so the per-user filter resolved on the
	 * index but the `created_at DESC` ordering fell back to a filesort —
	 * fine for a handful of claims, but a sort over the whole user slice on
	 * directories where a power user has claimed many listings. The new
	 * `idx_user_created (user_id, created_at)` covers both the predicate and
	 * the ordering so the paginated read is index-only. Re-running the
	 * activator via dbDelta adds the missing index in place without touching
	 * existing rows.
	 */
	public static function migrate_1_4_0(): void {
		\WBListora\Activator::activate();
	}

	/**
	 * Migration 1.5.3 — business hours gain a `slot`, so one day can hold more
	 * than one opening range (a lunch or riposo break, a split retail shift).
	 *
	 * Re-runs the activator, which dbDeltas the column in and then calls
	 * ensure_hours_slot_key() to widen the PRIMARY KEY — dbDelta cannot change a
	 * primary key on its own and silently declines to.
	 *
	 * Existing rows are NOT rewritten and do not need to be: `slot` defaults to
	 * 0, so every row already stored becomes slot 0 and stays unique under the
	 * wider key. A site that never adds a second range sees no change.
	 *
	 * NOTE for whoever bumps WB_LISTORA_DB_VERSION next: an entry here is what
	 * actually runs the upgrade. Bumping the constant alone does nothing —
	 * maybe_migrate() only calls the callbacks in this map, so a schema change
	 * with no entry ships and never applies.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	public static function migrate_1_5_3(): void {
		\WBListora\Activator::activate();
	}

	/**
	 * Migration 1.6.0 — sweep permanent search/facet transients.
	 *
	 * Until 1.6.0 a `search_cache_ttl` / `facet_cache_ttl` of 0 was passed
	 * straight to set_transient(), where 0 means "never expire" rather than
	 * "do not cache" (BC 10203769600). Sites that took the settings screen at
	 * its word — "Set to 0 to disable caching" — accumulated option rows that
	 * nothing ever expired.
	 *
	 * 1.6.0 stops writing them and stops reading them, but existing rows would
	 * otherwise sit in wp_options forever, so clear them once here.
	 *
	 * Deliberately narrow: only listora search/facet transients that have NO
	 * matching `_transient_timeout_` row. A transient with a timeout row is a
	 * normal cached entry and is left alone to expire on its own.
	 */
	public static function migrate_1_6_0(): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			"DELETE o FROM {$wpdb->options} o
			 WHERE ( o.option_name LIKE '\_transient\_listora\_search\_%'
			      OR o.option_name LIKE '\_transient\_listora\_facets\_%' )
			   AND NOT EXISTS (
			       SELECT 1 FROM ( SELECT option_name FROM {$wpdb->options} ) t
			       WHERE t.option_name = CONCAT( '_transient_timeout_', SUBSTRING( o.option_name, 12 ) )
			   )"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( $deleted && function_exists( 'wb_listora_log' ) ) {
			wb_listora_log( "Cleared {$deleted} permanent search/facet transients written while cache TTL was 0." );
		}

		self::preserve_implicit_tile_source();
	}

	/**
	 * Migration 1.8.0 — give existing listing types the Video URL field.
	 *
	 * The frontend submission wizard has always offered a Video URL (it is
	 * hardcoded in step-media.php and saved to the `video` meta key), but no
	 * listing type ever carried a matching FIELD. The wp-admin editor builds
	 * itself from a type's fields, so an admin editing a listing had nowhere to
	 * see or change a video a member had submitted (BC 10272654379).
	 *
	 * 1.8.0 adds `video` to the media group in Listing_Type_Defaults, which
	 * covers fresh installs. Existing sites do NOT read those defaults: a
	 * type's field groups are frozen into `_listora_field_groups` term meta
	 * when the type is created. Without this pass the fix would reach new
	 * installs only, and every current customer would keep the broken screen.
	 *
	 * Deliberately conservative: only types that already have a media group are
	 * touched, the field is appended after the existing ones, and a type that
	 * already has a `video` field anywhere is skipped, so an owner who added
	 * their own is left alone. No listing data is read or written — this only
	 * changes which inputs the editor renders.
	 *
	 * @since 1.8.0
	 *
	 * @return void
	 */
	public static function migrate_1_8_0(): void {
		/*
		 * maybe_migrate() runs on plugins_loaded priority 11, which is BEFORE
		 * init — so listora_listing_type is not registered yet and get_terms()
		 * would return a WP_Error. The migration would quietly do nothing while
		 * maybe_migrate() still stamped wb_listora_db_version as done, and the
		 * fix would never reach a single site.
		 *
		 * Every migration before this one touched only tables and options, so
		 * none of them had to care. Anything taxonomy- or post-aware does: wait
		 * for init, in this same request, before reading terms.
		 */
		if ( ! taxonomy_exists( 'listora_listing_type' ) ) {
			add_action( 'init', array( __CLASS__, 'add_video_field_to_types' ), 99 );
			return;
		}

		self::add_video_field_to_types();
	}

	/**
	 * Append the Video URL field to every stored type that lacks one.
	 *
	 * Split out of migrate_1_8_0() so it can run on init when the migrator
	 * itself fires too early to read taxonomy terms.
	 *
	 * @since 1.8.0
	 *
	 * @return void
	 */
	public static function add_video_field_to_types(): void {
		$terms = get_terms(
			array(
				'taxonomy'   => 'listora_listing_type',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}

		$updated = 0;

		foreach ( $terms as $term ) {
			$groups = get_term_meta( $term->term_id, '_listora_field_groups', true );
			if ( ! is_array( $groups ) || empty( $groups ) ) {
				continue;
			}

			// An owner who already defined a video field keeps theirs.
			$already_present = false;
			foreach ( $groups as $group ) {
				foreach ( (array) ( $group['fields'] ?? array() ) as $field ) {
					if ( 'video' === ( $field['key'] ?? '' ) ) {
						$already_present = true;
						break 2;
					}
				}
			}
			if ( $already_present ) {
				continue;
			}

			$changed = false;

			foreach ( $groups as $index => $group ) {
				if ( 'media' !== ( $group['key'] ?? '' ) ) {
					continue;
				}

				$fields = (array) ( $group['fields'] ?? array() );
				if ( empty( $fields ) ) {
					continue;
				}

				// Clone a sibling so the new row carries exactly the keys this
				// site's stored shape uses, rather than a shape assembled here
				// that could drift from it.
				$template = $fields[0];
				if ( ! is_array( $template ) ) {
					continue;
				}

				$video = array_merge(
					$template,
					array(
						'key'            => 'video',
						'label'          => __( 'Video URL', 'wb-listora' ),
						'type'           => 'video',
						'description'    => '',
						'placeholder'    => '',
						'default_value'  => '',
						'options'        => array(),
						'required'       => false,
						'searchable'     => false,
						'filterable'     => false,
						'show_in_card'   => false,
						'show_in_detail' => true,
						'show_in_rest'   => true,
						'show_in_admin'  => true,
						'schema_prop'    => 'video',
						'conditional'    => null,
						'order'          => count( $fields ),
					)
				);

				$fields[]                   = $video;
				$groups[ $index ]['fields'] = $fields;
				$changed                    = true;
				break;
			}

			/*
			 * A type with no media group at all — Job was shipped that way —
			 * still gets a gallery and a video from the submission wizard,
			 * which renders step-media.php for every type. So the group is
			 * created rather than skipped, or those listings keep data that
			 * wp-admin cannot reach.
			 */
			if ( ! $changed ) {
				$orders = array_map(
					static function ( $group ) {
						return (int) ( $group['order'] ?? 0 );
					},
					$groups
				);
				$groups[] = array(
					'key'         => 'media',
					'label'       => __( 'Media', 'wb-listora' ),
					'description' => '',
					'icon'        => 'images',
					'order'       => $orders ? max( $orders ) + 1 : 0,
					'fields'      => array(
						self::media_field_row( 'gallery', __( 'Photo Gallery', 'wb-listora' ), 'gallery', 'image', 0 ),
						self::media_field_row( 'video', __( 'Video URL', 'wb-listora' ), 'video', 'video', 1 ),
					),
				);
			}

			// Both branches above leave $groups modified — the video field was
			// appended to an existing media group, or a whole media group was
			// created — so the write is unconditional.
			update_term_meta( $term->term_id, '_listora_field_groups', $groups );
			++$updated;
		}

		if ( $updated && function_exists( 'wb_listora_log' ) ) {
			wb_listora_log( "Added the Video URL field to {$updated} listing type(s) so wp-admin can edit it." );
		}
	}

	/**
	 * Build a stored field row for a type that has no media group to clone from.
	 *
	 * @param string $key         Field key.
	 * @param string $label       Field label.
	 * @param string $type        Field type.
	 * @param string $schema_prop Schema.org property.
	 * @param int    $order       Order within the group.
	 * @return array<string, mixed>
	 */
	private static function media_field_row( string $key, string $label, string $type, string $schema_prop, int $order ): array {
		return array(
			'key'            => $key,
			'label'          => $label,
			'type'           => $type,
			'description'    => '',
			'placeholder'    => '',
			'default_value'  => '',
			'options'        => array(),
			'required'       => false,
			'searchable'     => false,
			'filterable'     => false,
			'show_in_card'   => false,
			'show_in_detail' => true,
			'show_in_rest'   => true,
			'show_in_admin'  => true,
			'schema_prop'    => $schema_prop,
			'filter_type'    => '',
			'css_class'      => '',
			'width'          => '100',
			'pro_only'       => false,
			'conditional'    => null,
			'order'          => $order,
			'min'            => null,
			'max'            => null,
			'step'           => null,
		);
	}

	/**
	 * Write the previously-implicit tile source into the new setting.
	 *
	 * 1.6.0 removed the hardcoded OpenStreetMap public tile fallback: shipping
	 * a product that silently leans on someone else's infrastructure, at
	 * volumes their usage policy does not permit, is not a defensible default
	 * (BC 10202831116). New installs now start blank and the owner chooses.
	 *
	 * But an EXISTING site was relying on that fallback, and removing it alone
	 * would have taken every one of those maps blank on upgrade — a silent
	 * break, on a surface nobody would think to re-check after a plugin
	 * update. So the upgrade makes the old behaviour explicit instead of
	 * dropping it: the same URL and attribution are written into the setting,
	 * where the map keeps working AND the owner can finally see what it has
	 * been using and change it.
	 *
	 * Only touches sites that were actually on the fallback — a configured
	 * URL, or a Google-provider site, is left alone.
	 *
	 * @return void
	 */
	private static function preserve_implicit_tile_source(): void {
		$settings = get_option( 'wb_listora_settings', array() );

		if ( ! is_array( $settings ) ) {
			return;
		}

		// Google sites never rendered raster tiles from this setting.
		if ( 'google' === ( $settings['map_provider'] ?? 'osm' ) ) {
			return;
		}

		// An owner who already chose a tile server keeps their choice.
		if ( '' !== trim( (string) ( $settings['map_tile_url'] ?? '' ) ) ) {
			return;
		}

		$settings['map_tile_url']         = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
		$settings['map_tile_attribution'] = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

		update_option( 'wb_listora_settings', $settings );

		if ( function_exists( 'wb_listora_log' ) ) {
			wb_listora_log( 'Recorded the previously-implicit OpenStreetMap tile source in Settings -> Map so the existing map keeps rendering and is now editable.' );
		}
	}
}
