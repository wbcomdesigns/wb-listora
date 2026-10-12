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
			'1.8.1' => array( __CLASS__, 'migrate_1_8_1' ),
			'1.9.0' => array( __CLASS__, 'migrate_1_9_0' ),
			'1.9.1' => array( __CLASS__, 'migrate_1_9_1' ),
			'1.9.1.1' => array( __CLASS__, 'migrate_1_9_1_1' ),
			'1.9.1.2' => array( __CLASS__, 'migrate_1_9_1_2' ),
			'1.9.1.3' => array( __CLASS__, 'migrate_1_9_1_3' ),
			'1.9.1.4' => array( __CLASS__, 'migrate_1_9_1_4' ),
			'1.9.1.5' => array( __CLASS__, 'migrate_1_9_1_5' ),
			'1.9.1.6' => array( __CLASS__, 'migrate_1_9_1_6' ),
			'1.9.1.7' => array( __CLASS__, 'migrate_1_9_1_7' ),
			'1.9.1.8' => array( __CLASS__, 'migrate_1_9_1_8' ),
			'1.9.1.9' => array( __CLASS__, 'migrate_1_9_1_9' ),
			'1.9.1.10' => array( __CLASS__, 'migrate_1_9_1_10' ),
			'1.9.1.11' => array( __CLASS__, 'migrate_1_9_1_11' ),
			'1.9.1.12' => array( __CLASS__, 'migrate_1_9_1_12' ),
			'1.9.1.13' => array( __CLASS__, 'migrate_1_9_1_13' ),
			'1.9.1.14' => array( __CLASS__, 'migrate_1_9_1_14' ),
			'1.9.1.15' => array( __CLASS__, 'migrate_1_9_1_15' ),
			'1.9.1.16' => array( __CLASS__, 'migrate_1_9_1_13' ), // Re-run: types left open by the first 1.9.1.13 (no demo listings).
			'1.9.1.17' => array( __CLASS__, 'migrate_1_9_1_17' ),
			'1.9.1.18' => array( __CLASS__, 'migrate_1_9_1_18' ),
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
	 * than one opening range.
	 *
	 * Re-runs the activator: dbDelta adds the column, then ensure_hours_slot_key()
	 * widens the PRIMARY KEY (dbDelta silently will not). `slot` defaults to 0, so
	 * existing rows stay unique without rewriting.
	 *
	 * Bumping WB_LISTORA_DB_VERSION alone does nothing: maybe_migrate() only runs
	 * callbacks in this map, so every schema change needs an entry.
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
	 * A cache TTL of 0 used to reach set_transient() as "never expire". Removes
	 * listora search/facet transients that have NO `_transient_timeout_` row;
	 * normal cached entries are left to expire.
	 */
	public static function migrate_1_6_0(): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- transient cleanup by option name pattern, no WP API
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
	 * The submission wizard always saved `video` meta, but no type had the
	 * field, so the admin editor could not show it. Field groups are frozen in
	 * `_listora_field_groups` term meta, so existing types need this pass. Only
	 * types with a media group and no `video` field are changed (appended at the
	 * end); listing data is not touched.
	 *
	 * @since 1.8.0
	 *
	 * @return void
	 */
	public static function migrate_1_8_0(): void {
		/*
		 * maybe_migrate() runs at init priority 0, before the taxonomies are
		 * registered — so listora_listing_type is not registered yet and get_terms()
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
				$orders   = array_map(
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
	 * 1.6.0 removed the OpenStreetMap public tile fallback. Sites that relied
	 * on it get the same URL and attribution written into the setting, so their
	 * maps keep working and the owner can see and change the source. Sites with
	 * a configured URL or the Google provider are left alone.
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

	/**
	 * Migration 1.8.1 — Adds the space_listings table (listing <-> space showcase).
	 *
	 * The table landed in Activator::create_tables() after 1.8.0's migration
	 * had already run on development sites, so those sites - and any that
	 * upgraded to an earlier 1.8.0 build - never got it, and every
	 * /spaces/{id}/listings request hit "Table doesn't exist". dbDelta is
	 * idempotent and adds only the missing table. Tables only - the full
	 * activate() would also re-flag the "Review your pages" notice and
	 * recreate essential pages an owner deliberately deleted.
	 *
	 * @return void
	 */
	public static function migrate_1_8_1(): void {
		\WBListora\Activator::create_tables();
	}

	/**
	 * Migration 1.9.0 - email log table, and one country term per country.
	 *
	 * Moves the email log from its capped option into the email_log table, and
	 * merges duplicate location terms via Location_Repair.
	 *
	 * @return void
	 */
	public static function migrate_1_9_0(): void {
		// The email log moves from an option to its own table.
		\WBListora\Activator::create_tables();
		\WBListora\Workflow\Email_Log::import_legacy_option();

		// Migrations run at init priority 0, before taxonomies are registered
		// on init; get_terms() on an unregistered taxonomy returns an error,
		// so the repair waits for init in the same request.
		$repair = static function () {
			\WBListora\Core\Location_Repair::run();
			self::repair_default_types();
		};
		if ( taxonomy_exists( 'listora_listing_location' ) ) {
			$repair();
		} else {
			add_action( 'init', $repair, 99 );
		}

		self::retype_hold_releases();
	}

	/**
	 * Repair the plugin's default listing types in place.
	 *
	 * A default type left with no fields (a bare term re-created by assigning
	 * its slug after the type was deleted) gets its default definition back,
	 * and existing default types gain the choice options added since they
	 * were saved. A default type the owner deleted is not brought back.
	 */
	private static function repair_default_types(): void {
		$registry = \WBListora\Core\Listing_Type_Registry::instance();
		foreach ( array_keys( \WBListora\Core\Listing_Type_Defaults::get_all() ) as $slug ) {
			if ( term_exists( $slug, 'listora_listing_type' ) ) {
				$registry->install_default( $slug );
			}
		}
	}

	/**
	 * Credit ledger clean-up for the SDK's `hold_release` type.
	 *
	 * Approved holds whose release was written as a 'refund' with the SDK note
	 * "Hold released on approval" become 'hold_release' (matching the SDK's own
	 * string, so member refunds are untouched). Zero "Admin adjustment" rows,
	 * which changed no balance, are removed. Balances are unchanged.
	 */
	private static function retype_hold_releases(): void {
		global $wpdb;
		$table = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'credit_ledger';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- one-time data migration on the plugin ledger table, no WP API
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			return;
		}
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET entry_type = %s, note = %s WHERE entry_type = %s AND note = %s', $table, 'hold_release', 'Hold released', 'refund', 'Hold released on approval' ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE amount = 0 AND item_id = 0 AND entry_type IN ( %s, %s )', $table, 'topup', 'deduction' ) );
		// phpcs:enable
	}

	/**
	 * Migration 1.9.1 — lock demo accounts still on the shipped password.
	 *
	 * Demo-imported members were created with the password "password". Only
	 * demo-flagged users whose password still matches are changed.
	 *
	 * @since 1.9.1
	 *
	 * @return void
	 */
	public static function migrate_1_9_1(): void {
		$users = get_users(
			array(
				'meta_key'   => '_listora_demo_user', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one-off upgrade, at most ten demo accounts.
				'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- one-off upgrade.
				'fields'     => array( 'ID', 'user_pass' ),
			)
		);
		foreach ( $users as $user ) {
			if ( wp_check_password( 'password', $user->user_pass, (int) $user->ID ) ) {
				wp_set_password( wp_generate_password( 24 ), (int) $user->ID );
			}
		}
	}

	/**
	 * Per-type moderation now overrides the site setting, so every type whose
	 * stored value is the old default 'manual' (which never took effect) moves
	 * to 'global'. A type deliberately set to 'auto' keeps it. Existing sites
	 * behave as before until the owner picks a value per type.
	 *
	 * @return void
	 */
	public static function migrate_1_9_1_1(): void {
		global $wpdb;
		// Migrations run before the taxonomy is registered, so get_terms() would
		// return an error here; read the IDs from the table.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off upgrade.
		$terms = $wpdb->get_col( $wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", 'listora_listing_type' ) );
		foreach ( $terms as $term_id ) {
			if ( 'auto' !== get_term_meta( (int) $term_id, '_listora_moderation', true ) ) {
				update_term_meta( (int) $term_id, '_listora_moderation', 'global' );
			}
		}
		// Drop the cached registry only; flush() would also empty the in-memory
		// registry for the rest of this request, before the types are loaded.
		wp_cache_delete( 'wb_listora_type_registry', 'wb_listora' );
	}

	/**
	 * Salaries show in the site currency, like prices. New installs get the
	 * money option on the Job type's salary fields from the defaults; this
	 * gives saved types the same, unless the owner already chose either way.
	 *
	 * @return void
	 */
	public static function migrate_1_9_1_2(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off upgrade; taxonomy not registered yet.
		$terms = $wpdb->get_col( $wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", 'listora_listing_type' ) );
		foreach ( $terms as $term_id ) {
			$groups = get_term_meta( (int) $term_id, '_listora_field_groups', true );
			if ( ! is_array( $groups ) ) {
				continue;
			}
			$changed = false;
			foreach ( $groups as &$group ) {
				foreach ( (array) ( $group['fields'] ?? array() ) as $i => $field ) {
					if ( in_array( $field['key'] ?? '', array( 'salary_min', 'salary_max' ), true ) && 'number' === ( $field['type'] ?? '' ) && ! array_key_exists( 'currency', $field ) ) {
						$group['fields'][ $i ]['currency'] = true;
						$changed                           = true;
					}
				}
			}
			unset( $group );
			if ( $changed ) {
				update_term_meta( (int) $term_id, '_listora_field_groups', $groups );
			}
		}
		wp_cache_delete( 'wb_listora_type_registry', 'wb_listora' );
	}

	/**
	 * Saving a shipped type in the editor used to clear its built-in flag, so
	 * it became deletable and was skipped by built-in-only logic. Restore the
	 * flag on every type whose slug is one the plugin ships.
	 *
	 * @return void
	 */
	public static function migrate_1_9_1_3(): void {
		global $wpdb;
		$slugs = array_keys( \WBListora\Core\Listing_Type_Defaults::get_all() );
		if ( ! $slugs ) {
			return;
		}
		$in = implode( ',', array_fill( 0, count( $slugs ), '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- one-off upgrade; taxonomy not registered yet; $in is placeholders only.
		$terms = $wpdb->get_col( $wpdb->prepare( "SELECT t.term_id FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = 'listora_listing_type' AND t.slug IN ({$in})", $slugs ) );
		foreach ( $terms as $term_id ) {
			update_term_meta( (int) $term_id, '_listora_is_default', true );
		}
		wp_cache_delete( 'wb_listora_type_registry', 'wb_listora' );
	}

	/**
	 * Show a salary as one range ("$140K - $185K") instead of two chips: pair
	 * the shipped salary fields on saved types, unless the owner set them.
	 *
	 * @return void
	 */
	public static function migrate_1_9_1_4(): void {
		global $wpdb;
		$pairs = array(
			'salary_min' => array(
				'range_end'   => 'salary_max',
				'range_label' => __( 'Salary', 'wb-listora' ),
			),
			'salary_max' => array( 'range_start' => 'salary_min' ),
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off upgrade; taxonomy not registered yet.
		$terms = $wpdb->get_col( $wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", 'listora_listing_type' ) );
		foreach ( $terms as $term_id ) {
			$groups = get_term_meta( (int) $term_id, '_listora_field_groups', true );
			if ( ! is_array( $groups ) ) {
				continue;
			}
			$changed = false;
			foreach ( $groups as &$group ) {
				foreach ( (array) ( $group['fields'] ?? array() ) as $i => $field ) {
					$props = $pairs[ $field['key'] ?? '' ] ?? null;
					if ( ! $props || 'number' !== ( $field['type'] ?? '' ) ) {
						continue;
					}
					foreach ( $props as $prop => $value ) {
						if ( ! array_key_exists( $prop, $field ) ) {
							$group['fields'][ $i ][ $prop ] = $value;
							$changed                        = true;
						}
					}
				}
			}
			unset( $group );
			if ( $changed ) {
				update_term_meta( (int) $term_id, '_listora_field_groups', $groups );
			}
		}
		wp_cache_delete( 'wb_listora_type_registry', 'wb_listora' );
	}

	/**
	 * Migration 1.9.1.18 - leftover demo images get an owner.
	 *
	 * A demo image whose listing was deleted has no parent and is no listing's
	 * featured image, so 1.9.1.14 and 1.9.1.17 both passed it by and "Uploaded
	 * by" stayed blank. Nothing says who imported it, so it goes to the first
	 * administrator. Not deleted: an owner may have reused it on a page.
	 *
	 * @return void
	 */
	public static function migrate_1_9_1_18(): void {
		global $wpdb;
		$admins = get_users(
			array(
				'role'    => 'administrator',
				'number'  => 1,
				'orderby' => 'ID',
				'fields'  => 'ID',
			)
		);
		if ( ! $admins ) {
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off upgrade.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->posts} a
				JOIN {$wpdb->postmeta} m ON m.post_id = a.ID AND m.meta_key = '_listora_demo_content'
				SET a.post_author = %d
				WHERE a.post_type = 'attachment' AND a.post_author = 0",
				(int) $admins[0]
			)
		);
	}

	/**
	 * Migration 1.9.1.17 - images with no parent take their listing's author.
	 *
	 * 1.9.1.14 followed post_parent only; a listing image attached elsewhere
	 * (or nowhere) but used as a listing's featured image kept author 0.
	 *
	 * @return void
	 */
	public static function migrate_1_9_1_17(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off upgrade.
		$wpdb->query(
			"UPDATE {$wpdb->posts} a
			JOIN {$wpdb->postmeta} tm ON tm.meta_key = '_thumbnail_id' AND tm.meta_value = a.ID
			JOIN {$wpdb->posts} p ON p.ID = tm.post_id AND p.post_type = 'listora_listing'
			SET a.post_author = p.post_author
			WHERE a.post_type = 'attachment' AND a.post_author = 0 AND p.post_author > 0"
		);
	}

	/**
	 * Migration 1.9.1.15 - direct pack prices in the currency's real minor units.
	 *
	 * The pack form stored price x 100 whatever the currency, while the SDK
	 * charges price_cents as minor units: a JPY pack of 2,500 was stored as
	 * 250000 and shown as 25. Rescale when the site currency, the one checkout
	 * charges every pack in, is not two-decimal.
	 *
	 * @return void
	 */
	public static function migrate_1_9_1_15(): void {
		$mappings = get_option( 'wb-listora_credit_mappings', array() );
		if ( ! is_array( $mappings ) ) {
			return;
		}
		$changed = false;
		foreach ( $mappings as &$row ) {
			if ( ! is_array( $row ) || 'direct' !== ( $row['adapter'] ?? '' ) || empty( $row['price_cents'] ) ) {
				continue;
			}
			$factor = \Wbcom\Credits\Money::factor_for( (string) wb_listora_get_setting( 'currency', 'USD' ) );
			if ( 100 !== $factor ) {
				$row['price_cents'] = (int) round( (int) $row['price_cents'] / 100 * $factor );
				$changed            = true;
			}
		}
		unset( $row );
		if ( $changed ) {
			update_option( 'wb-listora_credit_mappings', $mappings );
		}
	}

	/**
	 * Migration 1.9.1.14 - imported listing images get their listing's author.
	 *
	 * Every importer created attachments with author 0 (1.9.1.5 fixed the demo
	 * ones only), so "Uploaded by" was blank and the privacy eraser and
	 * author-based cleanup missed them. One UPDATE over images parented to a
	 * listing.
	 *
	 * @return void
	 */
	public static function migrate_1_9_1_14(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off upgrade.
		$wpdb->query( "UPDATE {$wpdb->posts} a JOIN {$wpdb->posts} p ON p.ID = a.post_parent AND p.post_type = 'listora_listing' SET a.post_author = p.post_author WHERE a.post_type = 'attachment' AND a.post_author = 0" );
	}

	/**
	 * Migration 1.9.1.13 - demo features on sites whose seeder never flagged them.
	 *
	 * 1.9.1.9 finds demo features by the `_listora_demo_content` term flag,
	 * which older seeders did not write, so exactly the sites that need it
	 * were skipped (every feature offered on every type). Here a feature counts
	 * as demo-made when a demo pack creates it and it is not one of the plugin's
	 * generic features; an owner's own feature is never narrowed.
	 *
	 * @return void
	 */
	public static function migrate_1_9_1_13(): void {
		global $wpdb;
		$slugs = array_diff( self::demo_pack_feature_slugs(), array_keys( \WBListora\Core\Listing_Type_Registry::default_features() ) );
		if ( ! $slugs ) {
			return;
		}
		$placeholders = implode( ',', array_fill( 0, count( $slugs ), '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- one-off upgrade; slugs are placeholders.
		$demo = $wpdb->get_col( $wpdb->prepare( "SELECT tt.term_id FROM {$wpdb->term_taxonomy} tt JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE tt.taxonomy = 'listora_listing_feature' AND t.slug IN ({$placeholders})", array_values( $slugs ) ) );
		self::scope_demo_features( array_map( 'intval', $demo ) );
	}

	/**
	 * Feature slugs the demo packs create, read from each pack's
	 * Demo_Seeder::ensure_features() list (loading a pack would seed it).
	 *
	 * @return string[]
	 */
	private static function demo_pack_feature_slugs(): array {
		$slugs = array();
		foreach ( (array) glob( WB_LISTORA_PLUGIN_DIR . 'demo/*-pack.php' ) as $file ) {
			$code = (string) file_get_contents( (string) $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file.
			if ( preg_match( '/ensure_features\(\s*array\((.*?)\)\s*\);/s', $code, $block ) && preg_match_all( "/'([a-z0-9-]+)'\s*=>/", $block[1], $keys ) ) {
				$slugs = array_merge( $slugs, $keys[1] );
			}
		}
		return array_values( array_unique( $slugs ) );
	}


	/**
	 * Migration 1.9.1.12 - demo listings imported on one day get spread dates.
	 *
	 * Demo imports before 1.9.1 dated every listing the same second, so
	 * "Newest" and "New" badges meant nothing. Only demo LISTINGS are counted
	 * and re-dated (1.9.1.5 counted every demo row, so one coupon or webhook
	 * seeded on another day skipped the spread silently); each listing's
	 * attachments take its new date. Idempotent: once the listings span more
	 * than one day it does nothing.
	 *
	 * @return void
	 */
	public static function migrate_1_9_1_12(): void {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off upgrade over demo rows.
		$ids = array_map(
			'intval',
			$wpdb->get_col(
				"SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_listora_demo_content' WHERE p.post_type = 'listora_listing'"
			)
		);
		if ( count( $ids ) < 2 ) {
			return;
		}

		$in = implode( ',', $ids );
		if ( 1 !== (int) $wpdb->get_var( "SELECT COUNT( DISTINCT DATE( post_date_gmt ) ) FROM {$wpdb->posts} WHERE ID IN ({$in})" ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integer IDs.
			return;
		}

		foreach ( $ids as $id ) {
			$title    = (string) get_the_title( $id );
			$old_gmt  = (int) strtotime( get_post( $id )->post_date_gmt . ' UTC' );
			$date_gmt = gmdate( 'Y-m-d H:i:s', time() - ( crc32( $title ) % 60 ) * DAY_IN_SECONDS - ( crc32( strrev( $title ) ) % DAY_IN_SECONDS ) );
			self::move_demo_dates_from_now( $id, $old_gmt );
			$dates    = array(
				'post_date'     => get_date_from_gmt( $date_gmt ),
				'post_date_gmt' => $date_gmt,
			);
			$wpdb->update( $wpdb->posts, $dates, array( 'ID' => $id ) );
			clean_post_cache( $id );
			foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'attachment'", $id ) ) as $attachment_id ) {
				$wpdb->update( $wpdb->posts, $dates, array( 'ID' => (int) $attachment_id ) );
				clean_post_cache( (int) $attachment_id );
			}
		}
		// phpcs:enable
		\WBListora\Core\Cache::bump( \WBListora\Core\Cache::GROUP_LISTINGS );
	}

	/**
	 * Move a demo listing's own dates (event start / end, job deadline) to now
	 * plus their original distance from when the demo was imported, so an old
	 * demo import does not leave every job past its deadline and every event
	 * over (the expiry cron would remove them all). Expiry is recomputed.
	 *
	 * @param int $id      Listing ID.
	 * @param int $old_gmt The import time the dates were relative to.
	 * @return void
	 */
	private static function move_demo_dates_from_now( int $id, int $old_gmt ): void {
		$moved = false;
		foreach ( array( 'start_date', 'end_date', 'deadline' ) as $key ) {
			$value = (string) get_post_meta( $id, '_listora_' . $key, true );
			if ( '' === $value || $old_gmt <= 0 ) {
				continue;
			}
			try {
				$at = new \DateTimeImmutable( $value, wp_timezone() );
			} catch ( \Exception $e ) {
				continue;
			}
			// Whole days, so an event that started at 19:00 still starts at 19:00.
			$days = (int) round( ( strtotime( $at->format( 'Y-m-d' ) ) - strtotime( (string) wp_date( 'Y-m-d', $old_gmt ) ) ) / DAY_IN_SECONDS );
			$time = false !== strpos( $value, 'T' ) ? 'T' . $at->format( 'H:i' ) : '';
			update_post_meta( $id, '_listora_' . $key, wp_date( 'Y-m-d', time() + $days * DAY_IN_SECONDS ) . $time );
			$moved = true;
		}
		if ( $moved ) {
			\WBListora\Workflow\Status_Manager::apply_end_date_expiry( $id );
		}
	}

	/**
	 * Migration 1.9.1.11 - stored ratings rounded to one decimal.
	 *
	 * search_index kept two decimals (4.67) while pages showed 4.7, so search
	 * results, cards and map markers disagreed with the listing page. Recomputed
	 * from the reviews table, not by rounding the stored value, so a 4.649
	 * average becomes 4.6 as rating_aggregate() would give.
	 *
	 * @return void
	 */
	public static function migrate_1_9_1_11(): void {
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- one-off upgrade on plugin tables.
		$wpdb->query(
			"UPDATE {$prefix}search_index si
			JOIN ( SELECT listing_id, ROUND( AVG( overall_rating ), 1 ) AS r FROM {$prefix}reviews WHERE status = 'approved' GROUP BY listing_id ) rv ON rv.listing_id = si.listing_id
			SET si.avg_rating = rv.r"
		);
		\WBListora\Core\Cache::bump( \WBListora\Core\Cache::GROUP_LISTINGS );
	}

	/**
	 * Migration 1.9.1.10 - settings and block attribute removed in 2.0.0.
	 *
	 * Drops the settings nothing read (default_type, max_upload_size,
	 * facet_cache_ttl, enable_guest_submission, guest_email_verification,
	 * reviews.require_login). A submission block's old termsPageId becomes the
	 * site-wide Terms page when none is set, so its consent link stays.
	 *
	 * @return void
	 */
	public static function migrate_1_9_1_10(): void {
		$settings = get_option( 'wb_listora_settings', array() );
		if ( ! is_array( $settings ) ) {
			return;
		}
		foreach ( array( 'default_type', 'max_upload_size', 'facet_cache_ttl', 'enable_guest_submission', 'guest_email_verification' ) as $key ) {
			unset( $settings[ $key ] );
		}
		if ( isset( $settings['reviews'] ) && is_array( $settings['reviews'] ) ) {
			unset( $settings['reviews']['require_login'] );
		}

		if ( empty( $settings['legal_terms_page_id'] ) ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off upgrade scan.
			$content = (string) $wpdb->get_var( "SELECT post_content FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE '%wp:listora/listing-submission%termsPageId%' LIMIT 1" );
			if ( preg_match( '/"termsPageId":\s*(\d+)/', $content, $m ) && (int) $m[1] > 0 ) {
				$settings['legal_terms_page_id'] = (int) $m[1];
			}
		}

		update_option( 'wb_listora_settings', $settings );
	}

	/**
	 * Migration 1.9.1.9 - demo types offered every demo feature.
	 *
	 * Sites that imported the demo before the seeder scoped features per type
	 * offer all of them everywhere (Telemedicine on a restaurant). A type with
	 * no feature list and with demo listings gets what the seeder now gives:
	 * the features the demo did not create, plus those its own demo listings use.
	 *
	 * @return void
	 */
	public static function migrate_1_9_1_9(): void {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off upgrade; taxonomies are not registered yet.
		$demo = array_map( 'intval', $wpdb->get_col( "SELECT term_id FROM {$wpdb->termmeta} WHERE meta_key = '_listora_demo_content'" ) );
		// phpcs:enable
		self::scope_demo_features( $demo );
	}

	/**
	 * Give each open listing type the features the demo did not make, plus
	 * those its own demo listings use.
	 *
	 * Shared by 1.9.1.9 (demo terms flagged by the seeder) and 1.9.1.13 (terms
	 * an older seeder never flagged). A type that already lists its features
	 * is left alone, so running twice changes nothing.
	 *
	 * @param int[] $demo Feature term IDs the demo made (other IDs are ignored).
	 * @return void
	 */
	private static function scope_demo_features( array $demo ): void {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off upgrade; taxonomies are not registered yet.
		$features = array_map( 'intval', $wpdb->get_col( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'listora_listing_feature'" ) );
		$demo     = array_values( array_intersect( $features, array_map( 'intval', $demo ) ) );
		if ( ! $demo ) {
			return;
		}
		$own   = array_values( array_diff( $features, $demo ) );
		$types = array_map( 'intval', $wpdb->get_col( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'listora_listing_type'" ) );
		foreach ( $types as $type_id ) {
			if ( array_filter( (array) get_term_meta( $type_id, '_listora_allowed_features', true ) ) ) {
				continue;
			}
			// Demo features this type's own listings use (demo or not), so a
			// feature on one stray listing stays with that listing's type only.
			$used = array_values(
				array_intersect(
					$demo,
					array_map(
						'intval',
						$wpdb->get_col(
							$wpdb->prepare(
								"SELECT DISTINCT ttf.term_id
								FROM {$wpdb->term_relationships} trt
								JOIN {$wpdb->term_taxonomy} ttt ON ttt.term_taxonomy_id = trt.term_taxonomy_id AND ttt.taxonomy = 'listora_listing_type' AND ttt.term_id = %d
								JOIN {$wpdb->term_relationships} trf ON trf.object_id = trt.object_id
								JOIN {$wpdb->term_taxonomy} ttf ON ttf.term_taxonomy_id = trf.term_taxonomy_id AND ttf.taxonomy = 'listora_listing_feature'",
								$type_id
							)
						)
					)
				)
			);
			// A type no demo listing uses (Event on many sites) gets the owner's
			// features only, not every pack's.
			if ( $own || $used ) {
				update_term_meta( $type_id, '_listora_allowed_features', array_values( array_unique( array_merge( $own, $used ) ) ) );
			}
		}
		// phpcs:enable
		wp_cache_delete( 'wb_listora_type_registry', 'wb_listora' );
	}

	/**
	 * Migration 1.9.1.8 - the leads table.
	 *
	 * @return void
	 */
	public static function migrate_1_9_1_8(): void {
		\WBListora\Core\Leads::create_table();
	}

	/**
	 * Migration 1.9.1.7 - date index on favorites for "most favourited in a period".
	 *
	 * @return void
	 */
	public static function migrate_1_9_1_7(): void {
		global $wpdb;
		$table = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'favorites';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- one-off index on a plugin table.
		if ( ! $wpdb->get_var( "SHOW INDEX FROM {$table} WHERE Key_name = 'idx_created'" ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD KEY idx_created (created_at)" );
		}
		// phpcs:enable
	}

	/**
	 * Migration 1.9.1.6 - built-in types the editor reset to LocalBusiness.
	 *
	 * The type editor's Schema.org list lacked JobPosting, Product, Course,
	 * Physician and RealEstateListing, so saving those types stored the first
	 * option. A built-in type now holding LocalBusiness where its default is
	 * one of them gets its default back.
	 *
	 * @return void
	 */
	public static function migrate_1_9_1_6(): void {
		global $wpdb;
		$lost = array( 'JobPosting', 'Product', 'Course', 'Physician', 'RealEstateListing' );
		foreach ( \WBListora\Core\Listing_Type_Defaults::get_all() as $slug => $definition ) {
			$default = (string) ( $definition['props']['schema_type'] ?? '' );
			if ( ! in_array( $default, $lost, true ) ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off upgrade; taxonomy not registered yet.
			$term_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT t.term_id FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = 'listora_listing_type' AND t.slug = %s", $slug ) );
			if ( $term_id && 'LocalBusiness' === get_term_meta( $term_id, '_listora_schema_type', true ) ) {
				update_term_meta( $term_id, '_listora_schema_type', $default );
			}
		}
		wp_cache_delete( 'wb_listora_type_registry', 'wb_listora' );
	}

	/**
	 * Repair demo content imported by older versions (demo-flagged rows only):
	 * job `company` / `annual` become `company_name` / `yearly`; images get
	 * their listing's author. Dates are spread by migrate_1_9_1_12().
	 *
	 * @return void
	 */
	public static function migrate_1_9_1_5(): void {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off upgrade over demo rows.
		$ids = array_map( 'intval', $wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_listora_demo_content'" ) );
		if ( ! $ids ) {
			return;
		}

		foreach ( $ids as $id ) {
			$company = get_post_meta( $id, '_listora_company', true );
			if ( is_string( $company ) && '' !== $company && ! get_post_meta( $id, '_listora_company_name', true ) ) {
				update_post_meta( $id, '_listora_company_name', $company );
			}
			delete_post_meta( $id, '_listora_company' );
			if ( 'annual' === get_post_meta( $id, '_listora_salary_type', true ) ) {
				update_post_meta( $id, '_listora_salary_type', 'yearly' );
			}
		}

		$in = implode( ',', $ids );
		$wpdb->query( "UPDATE {$wpdb->posts} a JOIN {$wpdb->posts} p ON p.ID = a.post_parent SET a.post_author = p.post_author WHERE a.post_type = 'attachment' AND a.post_author = 0 AND a.post_parent IN ({$in})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integer IDs.
		// phpcs:enable
	}
}
