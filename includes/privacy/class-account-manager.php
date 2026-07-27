<?php
/**
 * Account lifecycle — deactivation (reversible) and deletion (irreversible).
 *
 * Two operations that look similar and are emphatically not:
 *
 * ---------------------------------------------------------------------------
 * DEACTIVATE — reversible, retains everything
 * ---------------------------------------------------------------------------
 * The member steps away. Their profile stops being linkable and their listings
 * leave the public directory, but NOTHING is destroyed: every row stays exactly
 * where it was. If they paid for something, the site owner can restore their
 * profile intact. This is the default the UI should steer members toward.
 *
 * Reversibility is only real if it round-trips *exactly*, so deactivation
 * records each listing's PRIOR status in post meta and reactivation restores
 * that recorded value. Blanket-republishing to `publish` would be a moderation
 * bypass: a listing sitting in `pending` awaiting admin approval would go live
 * the moment its owner reactivated. Only listings this flow actually changed
 * carry the marker, so a listing the member had *already* deactivated by hand
 * stays deactivated on the way back — we restore our own changes, not theirs.
 *
 * ---------------------------------------------------------------------------
 * DELETE — irreversible, really deletes
 * ---------------------------------------------------------------------------
 * Apple App Store Guideline 5.1.1(v) explicitly names "only offering to
 * deactivate or suspend the account" as non-compliant, so this path must
 * actually delete: the erasure map runs to completion and `wp_delete_user()`
 * destroys the `wp_users` row. There is no undo.
 *
 * Because the `wp_users` row is destroyed, a leftover `user_id` integer becomes
 * an orphaned pointer to nobody — which is why pointer-only tables may be
 * retained on this path but NOT on WordPress' Erase Personal Data path, where
 * the account survives. See `privacy-helpers.php` for the full two-path model.
 *
 * @package WBListora\Privacy
 * @since   1.2.3
 */

namespace WBListora\Privacy;

defined( 'ABSPATH' ) || exit;

/**
 * Deactivates, reactivates, and deletes member accounts.
 */
class Account_Manager {

	/**
	 * Post meta key holding a listing's status from before account deactivation.
	 *
	 * Present ONLY on listings this flow actually transitioned. Its presence is
	 * the marker that says "we changed this one, and here is what to put back".
	 */
	const LISTING_PRIOR_STATUS_META = '_listora_account_deactivated_prior_status';

	/**
	 * User meta key: unix timestamp of when the account was deactivated.
	 */
	const DEACTIVATED_META = '_listora_account_deactivated';

	/**
	 * Rows processed per batch by the generic erasure executor.
	 *
	 * Bounds memory + lock time for members with very large histories.
	 */
	const ERASE_BATCH = 500;

	/**
	 * Hard ceiling on eraser pagination loops.
	 *
	 * A registered eraser that never reports `done` (a third-party bug, or one
	 * of ours regressing) would otherwise spin forever inside a web request.
	 * Deletion is destructive: it must fail loudly, not hang.
	 */
	const MAX_ERASER_PAGES = 500;

	/**
	 * Whether an account deletion is currently driving the registered erasers.
	 *
	 * THE TWO PATHS MUST NOT COLLAPSE INTO EACH OTHER, and without this flag
	 * they do. Account deletion drives the same `wp_privacy_personal_data_erasers`
	 * callbacks that WordPress' Erase Personal Data tool drives — but those
	 * callbacks have a fixed `( $email, $page )` signature with nowhere to say
	 * WHICH path is calling. Left to guess, Privacy_Eraser assumed the privacy
	 * tool and applied the `on_privacy_erasure` policy during an account
	 * DELETION, which deleted the pointer-only rows (review_votes, coupon_usage)
	 * that the account-deletion policy deliberately retains.
	 *
	 * This flag is how a callback tells the two apart. Account_Manager owns the
	 * map on its own path and runs it once, with the correct plan.
	 *
	 * @var bool
	 */
	private static $deleting_account = false;

	/**
	 * Whether a Listora account deletion is currently in progress.
	 *
	 * Read by {@see \WBListora\Privacy\Privacy_Eraser::erase()} so it can skip
	 * the map (Account_Manager runs it) instead of applying the wrong path's
	 * policy. See {@see self::$deleting_account}.
	 *
	 * @since 1.2.3
	 *
	 * @return bool True while Account_Manager::delete() is driving the erasers.
	 */
	public static function is_deleting_account(): bool {
		return self::$deleting_account;
	}

	/**
	 * Deactivate an account: hide the profile, unpublish listings, retain all data.
	 *
	 * Idempotent — deactivating an already-deactivated account is a no-op that
	 * reports success, so a double-tapped button can't corrupt the saved prior
	 * statuses (the second pass would otherwise record `listora_deactivated` as
	 * the "prior" status and make reactivation a no-op).
	 *
	 * @since 1.2.3
	 *
	 * @param int $user_id User to deactivate.
	 * @return array|\WP_Error Result payload, or WP_Error on failure.
	 */
	public static function deactivate( $user_id ) {
		$user_id = (int) $user_id;
		$user    = get_userdata( $user_id );

		if ( ! $user instanceof \WP_User ) {
			return new \WP_Error(
				'listora_not_found',
				__( 'Account not found.', 'wb-listora' ),
				array( 'status' => 404 )
			);
		}

		if ( wb_listora_is_account_deactivated( $user_id ) ) {
			return array(
				'deactivated'         => true,
				'already_deactivated' => true,
				'listings_hidden'     => 0,
				'message'             => __( 'Your account is already deactivated.', 'wb-listora' ),
			);
		}

		$hidden = self::hide_listings( $user_id );

		update_user_meta( $user_id, self::DEACTIVATED_META, time() );

		/**
		 * Fires after a member's account is deactivated.
		 *
		 * Pro and integrations hook here to hide their own surfaces (BuddyPress
		 * profile, directories, digests). Everything is retained and reversible —
		 * listeners must HIDE, never destroy.
		 *
		 * @since 1.2.3
		 *
		 * @param int $user_id         The deactivated user's ID.
		 * @param int $listings_hidden Number of listings taken out of the directory.
		 */
		do_action( 'wb_listora_account_deactivated', $user_id, $hidden );

		return array(
			'deactivated'         => true,
			'already_deactivated' => false,
			'listings_hidden'     => $hidden,
			'message'             => __( 'Your account has been deactivated. Your listings are hidden from the directory and nothing has been deleted — sign in and reactivate at any time to restore everything.', 'wb-listora' ),
		);
	}

	/**
	 * Reactivate a previously deactivated account — the proof that deactivation
	 * was reversible.
	 *
	 * Restores each listing to the EXACT status recorded at deactivation time.
	 *
	 * @since 1.2.3
	 *
	 * @param int $user_id User to reactivate.
	 * @return array|\WP_Error Result payload, or WP_Error on failure.
	 */
	public static function reactivate( $user_id ) {
		$user_id = (int) $user_id;
		$user    = get_userdata( $user_id );

		if ( ! $user instanceof \WP_User ) {
			return new \WP_Error(
				'listora_not_found',
				__( 'Account not found.', 'wb-listora' ),
				array( 'status' => 404 )
			);
		}

		if ( ! wb_listora_is_account_deactivated( $user_id ) ) {
			return array(
				'reactivated'     => true,
				'already_active'  => true,
				'listings_restored' => 0,
				'message'         => __( 'Your account is already active.', 'wb-listora' ),
			);
		}

		$restored = self::restore_listings( $user_id );

		delete_user_meta( $user_id, self::DEACTIVATED_META );

		/**
		 * Fires after a member's account is reactivated.
		 *
		 * @since 1.2.3
		 *
		 * @param int $user_id           The reactivated user's ID.
		 * @param int $listings_restored Number of listings returned to their prior status.
		 */
		do_action( 'wb_listora_account_reactivated', $user_id, $restored );

		return array(
			'reactivated'       => true,
			'already_active'    => false,
			'listings_restored' => $restored,
			'message'           => __( 'Welcome back — your account is active again and your listings have been restored.', 'wb-listora' ),
		);
	}

	/**
	 * Take a member's listings out of the public directory, remembering where
	 * each one came from.
	 *
	 * Only publicly-visible statuses are swept. A listing already in `draft` or
	 * `pending` is not public, so hiding it would add zero privacy benefit while
	 * adding status churn and a reactivation edge case. Note `listora_expired`
	 * IS swept: it is registered with `public => true` and stays reachable by
	 * direct URL, so leaving it alone would leave the member visible.
	 *
	 * @param int $user_id Owner.
	 * @return int Number of listings transitioned.
	 */
	private static function hide_listings( $user_id ) {
		/**
		 * Filters which listing statuses account deactivation takes offline.
		 *
		 * @since 1.2.3
		 *
		 * @param array<int, string> $statuses Publicly-visible statuses to sweep.
		 * @param int                $user_id  Owner being deactivated.
		 */
		$statuses = (array) apply_filters(
			'wb_listora_account_deactivate_listing_statuses',
			array( 'publish', 'listora_expired' ),
			$user_id
		);

		$listing_ids = self::get_listing_ids( $user_id, $statuses );
		$hidden      = 0;

		foreach ( $listing_ids as $listing_id ) {
			$post = get_post( $listing_id );
			if ( ! $post ) {
				continue;
			}

			// Record where it came from BEFORE changing it — this meta is the
			// entire basis of reversibility.
			update_post_meta( $listing_id, self::LISTING_PRIOR_STATUS_META, $post->post_status );

			$result = wp_update_post(
				array(
					'ID'          => $listing_id,
					'post_status' => 'listora_deactivated',
				),
				true
			);

			if ( is_wp_error( $result ) ) {
				// Couldn't transition it — drop the marker so reactivation
				// doesn't later "restore" a listing we never actually changed.
				delete_post_meta( $listing_id, self::LISTING_PRIOR_STATUS_META );
				continue;
			}

			++$hidden;
		}

		return $hidden;
	}

	/**
	 * Put the member's listings back exactly as they were.
	 *
	 * Driven by the recorded prior-status meta, NOT by a blanket transition to
	 * `publish`. Only listings carrying the marker are touched.
	 *
	 * @param int $user_id Owner.
	 * @return int Number of listings restored.
	 */
	private static function restore_listings( $user_id ) {
		$listing_ids = self::get_listing_ids( $user_id, array( 'listora_deactivated' ) );
		$restored    = 0;

		foreach ( $listing_ids as $listing_id ) {
			$prior = get_post_meta( $listing_id, self::LISTING_PRIOR_STATUS_META, true );

			// No marker → the member deactivated this listing themselves before
			// closing their account. Their choice stands; leave it alone.
			if ( ! $prior || ! is_string( $prior ) ) {
				continue;
			}

			// Only restore into a status the plugin actually recognises, so a
			// corrupted / hand-edited meta value can never inject an arbitrary
			// post_status.
			if ( ! array_key_exists( $prior, \WBListora\Workflow\Status_Manager::get_statuses() ) ) {
				delete_post_meta( $listing_id, self::LISTING_PRIOR_STATUS_META );
				continue;
			}

			$result = wp_update_post(
				array(
					'ID'          => $listing_id,
					'post_status' => $prior,
				),
				true
			);

			if ( is_wp_error( $result ) ) {
				continue;
			}

			delete_post_meta( $listing_id, self::LISTING_PRIOR_STATUS_META );
			++$restored;
		}

		return $restored;
	}

	/**
	 * Fetch a member's listing IDs for the given statuses.
	 *
	 * Uses a bounded, ID-only query — an account with thousands of listings must
	 * never load full post objects into memory.
	 *
	 * @param int                $user_id  Owner.
	 * @param array<int, string> $statuses Post statuses to match.
	 * @return array<int, int> Listing IDs.
	 */
	private static function get_listing_ids( $user_id, array $statuses ) {
		if ( empty( $statuses ) ) {
			return array();
		}

		$ids = get_posts(
			array(
				'post_type'              => 'listora_listing',
				'author'                 => (int) $user_id,
				'post_status'            => $statuses,
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'suppress_filters'       => false,
			)
		);

		return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
	}

	/**
	 * Permanently delete a member's account.
	 *
	 * Order matters and is deliberate:
	 *
	 *   1. Registered privacy erasers run to completion (reviews anonymised,
	 *      favorites + claims deleted, Pro's audit log anonymised).
	 *   2. The erasure map's `account_eraser` entries run (payments PII stripped,
	 *      Pro's member-authored rows deleted).
	 *   3. Listings are dealt with per the configured strategy.
	 *   4. `wp_delete_user()` destroys the `wp_users` row.
	 *
	 * The erasers run BEFORE `wp_delete_user()` because every one of them
	 * resolves the data subject by EMAIL via `get_user_by( 'email', ... )`.
	 * Delete the user first and every eraser silently no-ops — the data would
	 * survive forever with nothing left to link it to. This ordering is the
	 * whole ballgame; do not reorder it.
	 *
	 * @since 1.2.3
	 *
	 * @param int $user_id User to delete.
	 * @return array|\WP_Error Result payload, or WP_Error on failure.
	 */
	public static function delete( $user_id ) {
		$user_id = (int) $user_id;
		$user    = get_userdata( $user_id );

		if ( ! $user instanceof \WP_User ) {
			return new \WP_Error(
				'listora_not_found',
				__( 'Account not found.', 'wb-listora' ),
				array( 'status' => 404 )
			);
		}

		$email = (string) $user->user_email;

		/**
		 * Fires immediately before a member's account is deleted.
		 *
		 * The LAST point at which the user, and everything keyed to them, still
		 * exists. Integrations that need to read before cleanup hook here.
		 *
		 * @since 1.2.3
		 *
		 * @param int    $user_id User about to be deleted.
		 * @param string $email   The user's email (erasers resolve the subject by email).
		 */
		do_action( 'wb_listora_before_account_deleted', $user_id, $email );

		// Announce the path for the duration of the eraser run, so
		// Privacy_Eraser applies the account-deletion policy (i.e. leaves the
		// map to us) instead of assuming it was invoked by the privacy tool.
		// try/finally: if any eraser throws, the flag must not leak into the
		// next request's privacy-tool run and silently retain rows there.
		self::$deleting_account = true;

		try {
			$erasers_run = self::run_registered_erasers( $email );
			$erased      = self::run_erasure_map( $user_id, 'on_account_deletion' );
		} finally {
			self::$deleting_account = false;
		}

		$listings = self::handle_listings_on_delete( $user_id );

		// wp_delete_user() lives in wp-admin/includes/user.php, which is NOT
		// loaded on a REST / front-end request. Without this require the call
		// fatals with "undefined function" — the single most common way this
		// endpoint breaks outside wp-admin.
		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		$deleted = wp_delete_user( $user_id );

		if ( ! $deleted ) {
			return new \WP_Error(
				'listora_account_delete_failed',
				__( 'We could not delete your account. Please contact the site administrator.', 'wb-listora' ),
				array( 'status' => 500 )
			);
		}

		/**
		 * Fires after a member's account has been deleted.
		 *
		 * The `wp_users` row is GONE by now — `get_userdata( $user_id )` returns
		 * false. Listeners get the ID and email so they can finish cleaning up
		 * their own storage.
		 *
		 * @since 1.2.3
		 *
		 * @param int    $user_id The deleted user's ID (now an orphaned pointer).
		 * @param string $email   The deleted user's email.
		 */
		do_action( 'wb_listora_account_deleted', $user_id, $email );

		return array(
			'deleted'      => true,
			'erasers_run'  => $erasers_run,
			'rows_erased'  => $erased,
			'listings'     => $listings,
			'message'      => __( 'Your account and personal data have been permanently deleted.', 'wb-listora' ),
		);
	}

	/**
	 * Drive Listora's registered privacy erasers to completion.
	 *
	 * The WP privacy contract is paginated: a callback processes one page and
	 * reports `done`. The core tool loops for you; here there is no tool, so we
	 * loop ourselves — a single call would erase only the first 100 rows and
	 * leave the rest behind, which on a heavy account is a silent data leak.
	 *
	 * Only Listora's OWN erasers run by default. Driving every third-party
	 * eraser registered on the site (WooCommerce orders, form entries, ...) from
	 * a Listora endpoint would reach far outside this plugin's remit with no
	 * warning. Sites that DO want that can add the IDs via the filter.
	 *
	 * @param string $email Data subject's email.
	 * @return array<int, string> Eraser IDs actually executed.
	 */
	private static function run_registered_erasers( $email ) {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WP core privacy filter, intentionally invoked to collect the registered erasers.
		$erasers = apply_filters( 'wp_privacy_personal_data_erasers', array() );
		$erasers = is_array( $erasers ) ? $erasers : array();

		/**
		 * Filters which registered personal-data erasers account deletion drives.
		 *
		 * Defaults to Listora's own Free + Pro erasers. Add third-party eraser
		 * IDs here to have them run as part of a Listora account deletion.
		 *
		 * @since 1.2.3
		 *
		 * @param array<int, string> $ids   Eraser IDs to run.
		 * @param string             $email Data subject's email.
		 */
		$ids = (array) apply_filters(
			'wb_listora_account_deletion_eraser_ids',
			array( 'wb-listora', 'wb-listora-pro' ),
			$email
		);

		$run = array();

		foreach ( $ids as $id ) {
			if ( ! isset( $erasers[ $id ]['callback'] ) || ! is_callable( $erasers[ $id ]['callback'] ) ) {
				continue;
			}

			$page = 1;
			$done = true;

			do {
				$response = call_user_func( $erasers[ $id ]['callback'], $email, $page );

				// A malformed response can't be trusted to report `done`; stop
				// rather than loop on it.
				if ( ! is_array( $response ) ) {
					break;
				}

				$done = ! empty( $response['done'] );
				++$page;
			} while ( ! $done && $page <= self::MAX_ERASER_PAGES );

			$run[] = (string) $id;
		}

		return $run;
	}

	/**
	 * Execute the erasure map's `account_eraser` entries for one path.
	 *
	 * Generic by design: it reads table, column, strategy and replacements from
	 * the map and emits plain SQL. It never names a table in its own code, which
	 * is what lets Pro declare Pro-owned tables through the filter without Free
	 * ever referencing them.
	 *
	 * @param int    $user_id Data subject.
	 * @param string $path    `on_account_deletion` or `on_privacy_erasure`.
	 * @return array<string, int> Rows affected, keyed by table short-name.
	 */
	public static function run_erasure_map( $user_id, $path ) {
		global $wpdb;

		$user_id = (int) $user_id;
		$results = array();

		if ( $user_id <= 0 ) {
			return $results;
		}

		foreach ( wb_listora_get_erasure_map() as $key => $entry ) {
			$plan = isset( $entry[ $path ] ) && is_array( $entry[ $path ] ) ? $entry[ $path ] : array();

			$handled_by = isset( $plan['handled_by'] ) ? (string) $plan['handled_by'] : 'none';
			$strategy   = isset( $plan['strategy'] ) ? (string) $plan['strategy'] : 'retain';

			// Everything else is somebody else's job: `privacy_eraser` entries
			// are executed by the registered WP eraser callbacks, and
			// `listing_lifecycle` entries reconcile through the listing's hooks.
			if ( 'account_eraser' !== $handled_by ) {
				continue;
			}

			$table_key = isset( $entry['table'] ) ? (string) $entry['table'] : (string) $key;
			$table     = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . $table_key;

			// The match column is the FIRST declared user column. Secondary
			// columns (e.g. claims.reviewed_by) point at a different person and
			// must never be used as a match — see privacy-helpers.php.
			$columns     = isset( $entry['user_columns'] ) ? (array) $entry['user_columns'] : array( 'user_id' );
			$user_column = isset( $columns[0] ) ? (string) $columns[0] : 'user_id';

			// Identifiers come from plugin-controlled map data, never request
			// input, but validate anyway: the map is filterable, so a bad
			// third-party entry must not reach SQL.
			if ( ! self::is_safe_identifier( $table_key ) || ! self::is_safe_identifier( $user_column ) ) {
				continue;
			}

			if ( 'delete' === $strategy ) {
				$results[ $key ] = self::delete_rows( $table, $user_column, $user_id );
			} elseif ( 'anonymize' === $strategy ) {
				$replacements = isset( $plan['columns'] ) ? (array) $plan['columns'] : array();
				$results[ $key ] = self::anonymize_rows( $table, $user_column, $user_id, $replacements );
			}
		}

		return $results;
	}

	/**
	 * Which tables actually still hold rows for this member under a `retain`
	 * policy.
	 *
	 * GDPR Art. 17(3) permits retention under a lawful basis, but the data
	 * subject has to be TOLD — silent retention is the non-compliant version of
	 * the same act. This powers that disclosure, and it counts real rows rather
	 * than asserting from the map, so the eraser never claims to have retained
	 * records that don't exist.
	 *
	 * @since 1.2.3
	 *
	 * @param int    $user_id Data subject.
	 * @param string $path    `on_account_deletion` or `on_privacy_erasure`.
	 * @return array<string, int> Row counts keyed by table short-name (only non-zero).
	 */
	public static function describe_retained( $user_id, $path ) {
		global $wpdb;

		$user_id  = (int) $user_id;
		$retained = array();

		if ( $user_id <= 0 ) {
			return $retained;
		}

		foreach ( wb_listora_get_erasure_map() as $key => $entry ) {
			$plan = isset( $entry[ $path ] ) && is_array( $entry[ $path ] ) ? $entry[ $path ] : array();

			if ( ! isset( $plan['strategy'] ) || 'retain' !== $plan['strategy'] ) {
				continue;
			}

			// `listing_lifecycle` rows aren't retained PII — they mirror a
			// listing and follow it. Reporting them as "retained personal data"
			// would be misleading.
			if ( isset( $plan['handled_by'] ) && 'listing_lifecycle' === $plan['handled_by'] ) {
				continue;
			}

			$table_key = isset( $entry['table'] ) ? (string) $entry['table'] : (string) $key;
			$columns   = isset( $entry['user_columns'] ) ? (array) $entry['user_columns'] : array( 'user_id' );
			$column    = isset( $columns[0] ) ? (string) $columns[0] : 'user_id';

			if ( ! self::is_safe_identifier( $table_key ) || ! self::is_safe_identifier( $column ) ) {
				continue;
			}

			$table = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . $table_key;

			if ( ! self::table_exists( $table ) ) {
				continue;
			}

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table + column are validated plugin-controlled identifiers.
					"SELECT COUNT(*) FROM {$table} WHERE {$column} = %d",
					$user_id
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			if ( $count > 0 ) {
				$retained[ $key ] = $count;
			}
		}

		return $retained;
	}

	/**
	 * Whether a map-supplied SQL identifier is safe to interpolate.
	 *
	 * @param string $identifier Table short-name or column name.
	 * @return bool
	 */
	private static function is_safe_identifier( $identifier ) {
		return (bool) preg_match( '/^[A-Za-z0-9_]+$/', (string) $identifier );
	}

	/**
	 * Delete a member's rows from a table, in bounded batches.
	 *
	 * @param string $table       Fully-qualified table name.
	 * @param string $user_column Column holding the user ID.
	 * @param int    $user_id     Data subject.
	 * @return int Rows deleted.
	 */
	private static function delete_rows( $table, $user_column, $user_id ) {
		global $wpdb;

		if ( ! self::table_exists( $table ) ) {
			return 0;
		}

		$total = 0;

		do {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$deleted = $wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table + column are validated plugin-controlled identifiers, not request input.
					"DELETE FROM {$table} WHERE {$user_column} = %d LIMIT %d",
					$user_id,
					self::ERASE_BATCH
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			$deleted = is_numeric( $deleted ) ? (int) $deleted : 0;
			$total  += $deleted;
		} while ( $deleted >= self::ERASE_BATCH );

		return $total;
	}

	/**
	 * Strip PII columns from a member's rows while keeping the rows themselves.
	 *
	 * @param string               $table        Fully-qualified table name.
	 * @param string               $user_column  Column holding the user ID.
	 * @param int                  $user_id      Data subject.
	 * @param array<string, mixed> $replacements column => replacement value.
	 * @return int Rows anonymised.
	 */
	private static function anonymize_rows( $table, $user_column, $user_id, array $replacements ) {
		global $wpdb;

		if ( empty( $replacements ) || ! self::table_exists( $table ) ) {
			return 0;
		}

		$sets   = array();
		$values = array();

		foreach ( $replacements as $column => $value ) {
			if ( ! self::is_safe_identifier( $column ) ) {
				continue;
			}
			$sets[]   = "{$column} = " . ( is_int( $value ) ? '%d' : '%s' );
			$values[] = $value;
		}

		if ( empty( $sets ) ) {
			return 0;
		}

		$values[] = $user_id;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- table + columns are validated plugin-controlled identifiers; all VALUES are bound placeholders.
				"UPDATE {$table} SET " . implode( ', ', $sets ) . " WHERE {$user_column} = %d",
				$values
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return is_numeric( $updated ) ? (int) $updated : 0;
	}

	/**
	 * Whether a table exists.
	 *
	 * The map may declare tables owned by a plugin that is not installed (Pro's
	 * rows on a Free-only site), so every executor call is guarded.
	 *
	 * @param string $table Fully-qualified table name.
	 * @return bool
	 */
	private static function table_exists( $table ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	/**
	 * Apply the configured listing strategy when an account is deleted.
	 *
	 * Default is `trash`. See the filter docblock for the reasoning and the
	 * alternatives.
	 *
	 * @param int $user_id Owner.
	 * @return array<string, mixed> Strategy applied + count.
	 */
	private static function handle_listings_on_delete( $user_id ) {
		/**
		 * Filters what happens to a member's listings when they delete their account.
		 *
		 * Default: `trash`.
		 *
		 * - `trash`    — listings leave the directory immediately but remain
		 *                recoverable by the site owner for WP's normal trash
		 *                window, after which core purges them. This is the
		 *                balanced default: a directory is a shared asset, and a
		 *                departing member should not be able to silently and
		 *                irrecoverably gut it, while the member's presence still
		 *                disappears at once.
		 * - `delete`   — force-delete immediately. Strictest reading of erasure,
		 *                but destroys listings other members reviewed.
		 * - `reassign` — hand ownership to the user in
		 *                `wb_listora_account_deletion_reassign_user_id`. Keeps
		 *                the directory whole. CAUTION: listing content can itself
		 *                carry the member's PII (their name, phone, email in the
		 *                listing fields), which reassignment leaves published —
		 *                only choose this if listings are genuinely business
		 *                records rather than personal data on your site.
		 *
		 * @since 1.2.3
		 *
		 * @param string $strategy One of `trash`, `delete`, `reassign`.
		 * @param int    $user_id  Owner being deleted.
		 */
		$strategy = (string) apply_filters( 'wb_listora_account_deletion_listing_strategy', 'trash', $user_id );

		$statuses    = array_keys( \WBListora\Workflow\Status_Manager::get_statuses() );
		$statuses[]  = 'trash';
		$listing_ids = self::get_listing_ids( $user_id, $statuses );
		$count       = 0;

		if ( 'reassign' === $strategy ) {
			/**
			 * Filters the user ID that inherits listings on account deletion.
			 *
			 * @since 1.2.3
			 *
			 * @param int $reassign_to User ID to reassign to. 0 disables reassignment.
			 * @param int $user_id     Owner being deleted.
			 */
			$reassign_to = (int) apply_filters( 'wb_listora_account_deletion_reassign_user_id', 0, $user_id );

			// A reassign target that doesn't exist would orphan every listing to
			// author 0. Fall back to the safe default rather than guess.
			if ( $reassign_to <= 0 || ! get_userdata( $reassign_to ) ) {
				$strategy = 'trash';
			} else {
				foreach ( $listing_ids as $listing_id ) {
					$result = wp_update_post(
						array(
							'ID'          => $listing_id,
							'post_author' => $reassign_to,
						),
						true
					);
					if ( ! is_wp_error( $result ) ) {
						++$count;
					}
				}

				return array(
					'strategy' => 'reassign',
					'count'    => $count,
				);
			}
		}

		foreach ( $listing_ids as $listing_id ) {
			if ( 'delete' === $strategy ) {
				if ( wp_delete_post( $listing_id, true ) ) {
					++$count;
				}
				continue;
			}

			// Default: trash.
			if ( 'trash' === get_post_status( $listing_id ) ) {
				continue;
			}
			if ( wp_trash_post( $listing_id ) ) {
				++$count;
			}
		}

		return array(
			'strategy' => 'trash' === $strategy ? 'trash' : $strategy,
			'count'    => $count,
		);
	}
}
