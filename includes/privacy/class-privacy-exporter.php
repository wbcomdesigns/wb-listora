<?php
/**
 * Personal-data exporter (read-only) for WB Listora's PII domains.
 *
 * Exposes a single callback that conforms to WordPress core's personal-data
 * exporter contract (see {@see wp_register_personal_data_exporter()} /
 * {@see https://developer.wordpress.org/plugins/privacy/adding-the-personal-data-exporter-to-your-plugin/}).
 * Given an email address it streams the three Listora PII domains tied to the
 * matching user — claims, reviews, favorites — across stable per-page batches.
 *
 * This class is deliberately registration-free: it adds no hooks and touches no
 * global state. A separate wiring direction is expected to register
 * {@see Privacy_Exporter::export()} on the `wp_privacy_personal_data_exporters`
 * filter. Keeping the contract object pure makes it unit-testable in isolation
 * and lets the wiring layer own the lifecycle.
 *
 * READ-ONLY: every query here is a SELECT. This exporter never deletes,
 * anonymizes, or otherwise mutates a row — erasure is a separate concern owned
 * by a (future) eraser direction.
 *
 * @package WBListora\Privacy
 */

namespace WBListora\Privacy;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only personal-data exporter for claims, reviews, and favorites.
 */
class Privacy_Exporter {

	/**
	 * Rows fetched per domain, per page.
	 *
	 * The WP privacy API drives pagination with a 1-based `$page` index; each
	 * call returns one batch and a `done` flag. We keep the batch modest so a
	 * single export request never loads an unbounded result set into memory
	 * (mirrors the 100k-readiness LIMIT discipline used across the plugin).
	 *
	 * @var int
	 */
	const PER_PAGE = 50;

	/**
	 * Ordered list of PII domains this exporter covers.
	 *
	 * The order is stable and meaningful: each `$page` exports exactly one
	 * domain's batch, walking domains in this sequence. `done` is only true
	 * once the LAST domain's LAST page has been returned.
	 *
	 * @var string[]
	 */
	const DOMAINS = array( 'claims', 'reviews', 'favorites' );

	/**
	 * Personal-data exporter callback.
	 *
	 * Conforms to the WP core exporter contract: accepts an email address and a
	 * 1-based page number, returns `array( 'data' => [...], 'done' => bool )`.
	 *
	 * Pagination model — one domain per page, in {@see Privacy_Exporter::DOMAINS}
	 * order. Page 1 returns the first batch of the first domain; subsequent pages
	 * continue the same domain until it is exhausted, then advance to the next
	 * domain. `done` is returned true only when the final domain has no further
	 * rows. This keeps each batch a bounded, stable slice — re-requesting the
	 * same page yields the same rows.
	 *
	 * @param string $email_address The user's email address to export data for.
	 * @param int    $page          1-based page index supplied by WP core. Default 1.
	 * @return array{data: array<int, array<string, mixed>>, done: bool} Export batch + completion flag.
	 */
	public static function export( $email_address, $page = 1 ) {
		$page = max( 1, (int) $page );

		$empty = array(
			'data' => array(),
			'done' => true,
		);

		$user = get_user_by( 'email', $email_address );
		if ( ! $user instanceof \WP_User ) {
			// No Listora user owns this address — nothing to export, and we are
			// finished (a single done:true response per the WP contract).
			return $empty;
		}

		$user_id = (int) $user->ID;

		// Resolve which domain this page targets and the per-domain page offset.
		// Walking the ordered domain list lets a single linear `$page` counter
		// address "page N of the whole export" while each domain paginates from
		// its own page 1 internally.
		$cursor = self::resolve_cursor( $user_id, $page );
		if ( null === $cursor ) {
			// `$page` is past the end of every domain — completion sentinel.
			return $empty;
		}

		switch ( $cursor['domain'] ) {
			case 'claims':
				$data = self::export_claims( $user_id, $cursor['domain_page'] );
				break;
			case 'reviews':
				$data = self::export_reviews( $user_id, $cursor['domain_page'] );
				break;
			case 'favorites':
				$data = self::export_favorites( $user_id, $cursor['domain_page'] );
				break;
			default:
				$data = array();
				break;
		}

		return array(
			'data' => $data,
			'done' => self::is_last_page( $user_id, $cursor ),
		);
	}

	/**
	 * Map a global export page to a (domain, domain-local page) cursor.
	 *
	 * Each domain occupies a contiguous run of global pages sized by its row
	 * count. This walks the domains in {@see Privacy_Exporter::DOMAINS} order,
	 * subtracting each domain's page span until the requested global page lands
	 * inside a domain. A domain with zero rows still occupies a single page so
	 * the export emits one (empty) batch per domain and never skips ahead.
	 *
	 * @param int $user_id Owning user ID.
	 * @param int $page    1-based global page.
	 * @return array{domain: string, domain_page: int, is_last_domain: bool, domain_pages: int}|null Cursor, or null when `$page` is past the end.
	 */
	private static function resolve_cursor( $user_id, $page ) {
		$remaining  = $page;
		$last_index = count( self::DOMAINS ) - 1;

		foreach ( self::DOMAINS as $index => $domain ) {
			$count        = self::count_domain( $user_id, $domain );
			$domain_pages = max( 1, (int) ceil( $count / self::PER_PAGE ) );

			if ( $remaining <= $domain_pages ) {
				return array(
					'domain'         => $domain,
					'domain_page'    => $remaining,
					'is_last_domain' => ( $index === $last_index ),
					'domain_pages'   => $domain_pages,
				);
			}

			$remaining -= $domain_pages;
		}

		return null;
	}

	/**
	 * Whether the resolved cursor is the final page of the entire export.
	 *
	 * True only when we are on the last domain AND on that domain's last page.
	 *
	 * @param int                                                                                   $user_id Owning user ID.
	 * @param array{domain: string, domain_page: int, is_last_domain: bool, domain_pages: int} $cursor  Resolved cursor.
	 * @return bool
	 */
	private static function is_last_page( $user_id, array $cursor ) {
		return $cursor['is_last_domain'] && ( $cursor['domain_page'] >= $cursor['domain_pages'] );
	}

	/**
	 * Count the rows in a single PII domain for the user.
	 *
	 * @param int    $user_id Owning user ID.
	 * @param string $domain  One of {@see Privacy_Exporter::DOMAINS}.
	 * @return int
	 */
	private static function count_domain( $user_id, $domain ) {
		switch ( $domain ) {
			case 'claims':
				return self::count_claims( $user_id );
			case 'reviews':
				return self::count_reviews( $user_id );
			case 'favorites':
				return self::count_favorites( $user_id );
			default:
				return 0;
		}
	}

	/**
	 * The table prefix shared by every Listora custom table.
	 *
	 * @return string
	 */
	private static function table_prefix() {
		global $wpdb;
		return $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
	}

	/* ---------------------------------------------------------------------
	 * Claims domain
	 * ------------------------------------------------------------------- */

	/**
	 * Export one page of the user's business claims.
	 *
	 * Mirrors the canonical per-user claims read path used by the dashboard
	 * controller's `get_my_claims()`
	 * (`SELECT c.* ... LEFT JOIN posts WHERE c.user_id = %d ORDER BY created_at`),
	 * scoped read-only to the requesting user.
	 *
	 * NOTE: {@see Claims_Model::get_list()} is the shared claims read model, but
	 * it does not (yet) expose a `user_id` predicate — its filters are
	 * listing/status/search only. A per-user export must scope by user to keep
	 * each WP-privacy page a stable, complete slice (a global-offset fetch then
	 * PHP-side user filter would desync the page math against the per-user
	 * count). Adding a `user_id` filter to `Claims_Model` so this can route
	 * through the shared model is logged as a Known Gap for Free. Until then
	 * this query intentionally matches the dashboard's user-scoped read shape.
	 *
	 * @param int $user_id     Owning user ID.
	 * @param int $domain_page 1-based page within the claims domain.
	 * @return array<int, array<string, mixed>> Export groups.
	 */
	private static function export_claims( $user_id, $domain_page ) {
		global $wpdb;
		$prefix = self::table_prefix();
		$offset = ( $domain_page - 1 ) * self::PER_PAGE;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT c.id, c.listing_id, c.status, c.proof_text, c.created_at, c.updated_at,
					p.post_title AS listing_title
				FROM {$prefix}claims c
				LEFT JOIN {$wpdb->posts} p ON c.listing_id = p.ID
				WHERE c.user_id = %d
				ORDER BY c.created_at DESC, c.id DESC
				LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				self::PER_PAGE,
				$offset
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$items = array();

		foreach ( $rows as $row ) {
			$listing_id = (int) ( $row['listing_id'] ?? 0 );

			$item_data = array(
				array(
					'name'  => __( 'Listing', 'wb-listora' ),
					'value' => self::listing_label( $listing_id, $row['listing_title'] ?? '' ),
				),
				array(
					'name'  => __( 'Status', 'wb-listora' ),
					'value' => (string) ( $row['status'] ?? '' ),
				),
				array(
					'name'  => __( 'Proof / supporting details', 'wb-listora' ),
					'value' => (string) ( $row['proof_text'] ?? '' ),
				),
				array(
					'name'  => __( 'Submitted', 'wb-listora' ),
					'value' => (string) ( $row['created_at'] ?? '' ),
				),
				array(
					'name'  => __( 'Last updated', 'wb-listora' ),
					'value' => (string) ( $row['updated_at'] ?? '' ),
				),
			);

			$items[] = array(
				'group_id'    => 'wb-listora-claims',
				'group_label' => __( 'Listora Business Claims', 'wb-listora' ),
				'item_id'     => 'wb-listora-claim-' . (int) ( $row['id'] ?? 0 ),
				'data'        => $item_data,
			);
		}

		return $items;
	}

	/**
	 * Count the user's claims.
	 *
	 * @param int $user_id Owning user ID.
	 * @return int
	 */
	private static function count_claims( $user_id ) {
		global $wpdb;
		$prefix = self::table_prefix();

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}claims WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Reviews domain
	 * ------------------------------------------------------------------- */

	/**
	 * Export one page of the reviews the user has authored.
	 *
	 * Mirrors the canonical per-user reviews read path used by the dashboard
	 * controller (`SELECT r.* ... WHERE r.user_id = %d ORDER BY created_at`),
	 * scoped read-only to the requesting user. Only reviews the user wrote are
	 * personal data of that user; reviews they merely received on their
	 * listings are other people's PII and are intentionally excluded.
	 *
	 * @param int $user_id     Owning user ID.
	 * @param int $domain_page 1-based page within the reviews domain.
	 * @return array<int, array<string, mixed>> Export groups.
	 */
	private static function export_reviews( $user_id, $domain_page ) {
		global $wpdb;
		$prefix = self::table_prefix();
		$offset = ( $domain_page - 1 ) * self::PER_PAGE;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT id, listing_id, overall_rating, title, content, status, ip_address, created_at, updated_at
				FROM {$prefix}reviews
				WHERE user_id = %d
				ORDER BY created_at DESC, id DESC
				LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				self::PER_PAGE,
				$offset
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$items = array();

		foreach ( $rows as $row ) {
			$listing_id = (int) ( $row['listing_id'] ?? 0 );

			$item_data = array(
				array(
					'name'  => __( 'Listing', 'wb-listora' ),
					'value' => self::listing_label( $listing_id, '' ),
				),
				array(
					'name'  => __( 'Rating', 'wb-listora' ),
					'value' => (string) (int) ( $row['overall_rating'] ?? 0 ),
				),
				array(
					'name'  => __( 'Title', 'wb-listora' ),
					'value' => (string) ( $row['title'] ?? '' ),
				),
				array(
					'name'  => __( 'Review', 'wb-listora' ),
					'value' => (string) ( $row['content'] ?? '' ),
				),
				array(
					'name'  => __( 'Status', 'wb-listora' ),
					'value' => (string) ( $row['status'] ?? '' ),
				),
				array(
					'name'  => __( 'IP address', 'wb-listora' ),
					'value' => (string) ( $row['ip_address'] ?? '' ),
				),
				array(
					'name'  => __( 'Submitted', 'wb-listora' ),
					'value' => (string) ( $row['created_at'] ?? '' ),
				),
				array(
					'name'  => __( 'Last updated', 'wb-listora' ),
					'value' => (string) ( $row['updated_at'] ?? '' ),
				),
			);

			$items[] = array(
				'group_id'    => 'wb-listora-reviews',
				'group_label' => __( 'Listora Reviews', 'wb-listora' ),
				'item_id'     => 'wb-listora-review-' . (int) ( $row['id'] ?? 0 ),
				'data'        => $item_data,
			);
		}

		return $items;
	}

	/**
	 * Count the reviews authored by the user.
	 *
	 * @param int $user_id Owning user ID.
	 * @return int
	 */
	private static function count_reviews( $user_id ) {
		global $wpdb;
		$prefix = self::table_prefix();

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}reviews WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Favorites domain
	 * ------------------------------------------------------------------- */

	/**
	 * Export one page of the user's saved favorites.
	 *
	 * Mirrors the favorites controller read path
	 * (`SELECT ... FROM {prefix}favorites WHERE user_id = %d ORDER BY created_at`),
	 * scoped read-only to the requesting user.
	 *
	 * @param int $user_id     Owning user ID.
	 * @param int $domain_page 1-based page within the favorites domain.
	 * @return array<int, array<string, mixed>> Export groups.
	 */
	private static function export_favorites( $user_id, $domain_page ) {
		global $wpdb;
		$prefix = self::table_prefix();
		$offset = ( $domain_page - 1 ) * self::PER_PAGE;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT f.listing_id, f.collection, f.created_at, p.post_title AS listing_title
				FROM {$prefix}favorites f
				LEFT JOIN {$wpdb->posts} p ON f.listing_id = p.ID
				WHERE f.user_id = %d
				ORDER BY f.created_at DESC, f.listing_id DESC
				LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				self::PER_PAGE,
				$offset
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$items = array();

		foreach ( $rows as $row ) {
			$listing_id = (int) ( $row['listing_id'] ?? 0 );

			$item_data = array(
				array(
					'name'  => __( 'Listing', 'wb-listora' ),
					'value' => self::listing_label( $listing_id, $row['listing_title'] ?? '' ),
				),
				array(
					'name'  => __( 'Collection', 'wb-listora' ),
					'value' => (string) ( $row['collection'] ?? '' ),
				),
				array(
					'name'  => __( 'Saved', 'wb-listora' ),
					'value' => (string) ( $row['created_at'] ?? '' ),
				),
			);

			$items[] = array(
				'group_id'    => 'wb-listora-favorites',
				'group_label' => __( 'Listora Favorites', 'wb-listora' ),
				'item_id'     => 'wb-listora-favorite-' . $user_id . '-' . $listing_id,
				'data'        => $item_data,
			);
		}

		return $items;
	}

	/**
	 * Count the user's favorites.
	 *
	 * @param int $user_id Owning user ID.
	 * @return int
	 */
	private static function count_favorites( $user_id ) {
		global $wpdb;
		$prefix = self::table_prefix();

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}favorites WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------- */

	/**
	 * Build a human-readable "Title (URL)" label for a listing, with fallbacks.
	 *
	 * @param int    $listing_id Listing post ID.
	 * @param string $title      Pre-joined title (may be empty).
	 * @return string
	 */
	private static function listing_label( $listing_id, $title ) {
		if ( '' === (string) $title && $listing_id > 0 ) {
			$title = (string) get_the_title( $listing_id );
		}

		if ( '' === (string) $title ) {
			/* translators: %d: listing ID. */
			$title = sprintf( __( 'Listing #%d', 'wb-listora' ), $listing_id );
		}

		$url = $listing_id > 0 ? (string) get_permalink( $listing_id ) : '';

		if ( '' !== $url ) {
			return $title . ' (' . $url . ')';
		}

		return $title;
	}
}
