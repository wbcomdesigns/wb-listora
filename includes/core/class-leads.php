<?php
/**
 * Leads: every message sent to a listing owner through the contact form.
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * The one store for contact-form leads. The form saves here and emails the
 * owner; the owner's dashboard, the admin Leads screen and REST read here.
 */
class Leads {

	/**
	 * Table name without the site and plugin prefixes.
	 */
	const TABLE = 'leads';

	/**
	 * Option holding how many months leads are kept (0 = until deleted).
	 */
	const RETENTION_OPTION = 'wb_listora_lead_retention_months';

	/**
	 * Default retention, in months.
	 */
	const DEFAULT_RETENTION = 12;

	/**
	 * Register cleanup and privacy hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action(
			'wb_listora_daily_cleanup',
			static function (): void {
				self::prune();
			}
		);
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	/**
	 * Full table name.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . self::TABLE;
	}

	/**
	 * Create or update the table (activation and upgrade).
	 *
	 * @return void
	 */
	public static function create_table(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = self::table();
		dbDelta(
			"CREATE TABLE {$table} (
			id          bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			listing_id  bigint(20) unsigned NOT NULL,
			owner_id    bigint(20) unsigned NOT NULL,
			sender_id   bigint(20) unsigned NOT NULL DEFAULT 0,
			name        varchar(191) NOT NULL DEFAULT '',
			email       varchar(191) NOT NULL DEFAULT '',
			phone       varchar(50) NOT NULL DEFAULT '',
			message     text NOT NULL,
			created_at  datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_owner_created (owner_id, created_at),
			KEY idx_listing_created (listing_id, created_at),
			KEY idx_created (created_at),
			KEY idx_email (email)
		) ENGINE=InnoDB {$wpdb->get_charset_collate()};"
		);
	}

	/**
	 * Save a lead.
	 *
	 * @param int                  $listing_id Listing ID.
	 * @param array<string, mixed> $lead       name, email, phone, message.
	 * @return int Lead ID, 0 when it could not be saved.
	 */
	public static function record( int $listing_id, array $lead ): int {
		global $wpdb;
		$post = get_post( $listing_id );
		if ( ! $post ) {
			return 0;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin table write.
		$ok = $wpdb->insert(
			self::table(),
			array(
				'listing_id' => $listing_id,
				'owner_id'   => (int) $post->post_author,
				'sender_id'  => get_current_user_id(),
				'name'       => mb_substr( sanitize_text_field( (string) ( $lead['name'] ?? '' ) ), 0, 191 ),
				'email'      => mb_substr( sanitize_email( (string) ( $lead['email'] ?? '' ) ), 0, 191 ),
				'phone'      => mb_substr( sanitize_text_field( (string) ( $lead['phone'] ?? '' ) ), 0, 50 ),
				'message'    => sanitize_textarea_field( (string) ( $lead['message'] ?? '' ) ),
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * One page of leads with the total, newest first by default.
	 *
	 * @param array<string, mixed> $args owner_id, listing_id, search, after, before
	 *                                   (UTC 'Y-m-d H:i:s'), order (ASC|DESC), limit, offset.
	 * @return array{rows: array<int, array<string, mixed>>, total: int}
	 */
	public static function query( array $args ): array {
		global $wpdb;
		$where  = array( '1=1' );
		$values = array();
		foreach ( array( 'owner_id', 'listing_id' ) as $column ) {
			if ( ! empty( $args[ $column ] ) ) {
				$where[]  = "{$column} = %d";
				$values[] = (int) $args[ $column ];
			}
		}
		if ( ! empty( $args['after'] ) ) {
			$where[]  = 'created_at >= %s';
			$values[] = (string) $args['after'];
		}
		if ( ! empty( $args['before'] ) ) {
			$where[]  = 'created_at < %s';
			$values[] = (string) $args['before'];
		}
		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where[]  = '( name LIKE %s OR email LIKE %s OR message LIKE %s )';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}
		$where_sql = implode( ' AND ', $where );
		$order     = 'ASC' === strtoupper( (string) ( $args['order'] ?? 'DESC' ) ) ? 'ASC' : 'DESC';
		$limit     = max( 1, min( 100, (int) ( $args['limit'] ?? 20 ) ) );
		$offset    = max( 0, (int) ( $args['offset'] ?? 0 ) );
		$table     = self::table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- $where_sql holds only placeholders; table and order are fixed.
		$total = (int) $wpdb->get_var( $values ? $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $values ) : "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}" );
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at {$order}, id {$order} LIMIT %d OFFSET %d", array_merge( $values, array( $limit, $offset ) ) ),
			ARRAY_A
		);
		// phpcs:enable

		return array(
			'rows'  => array_map( array( __CLASS__, 'shape' ), (array) $rows ),
			'total' => $total,
		);
	}

	/**
	 * Listings that received leads for an owner, for the filter.
	 *
	 * @param int $owner_id Owner user ID.
	 * @return int[]
	 */
	public static function listing_ids_for_owner( int $owner_id ): array {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- plugin table, idx_owner_created.
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT listing_id FROM {$table} WHERE owner_id = %d LIMIT 200", $owner_id ) ) );
	}

	/**
	 * One lead by ID.
	 *
	 * @param int $id Lead ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- plugin table read.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return $row ? self::shape( $row ) : null;
	}

	/**
	 * Delete one lead.
	 *
	 * @param int $id Lead ID.
	 * @return bool
	 */
	public static function delete( int $id ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin table write.
		return (bool) $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Whether a user may read or delete a lead: its listing's owner, or a site manager.
	 *
	 * @param array<string, mixed> $lead    Lead.
	 * @param int                  $user_id User ID.
	 * @return bool
	 */
	public static function user_can_manage( array $lead, int $user_id ): bool {
		return $user_id > 0 && ( (int) $lead['owner_id'] === $user_id || user_can( $user_id, 'manage_listora_settings' ) );
	}

	/**
	 * Retention in months; 0 keeps leads until they are deleted.
	 *
	 * @return int
	 */
	public static function retention_months(): int {
		$months = (int) get_option( self::RETENTION_OPTION, self::DEFAULT_RETENTION );
		return array_key_exists( $months, self::retention_choices() ) ? $months : self::DEFAULT_RETENTION;
	}

	/**
	 * Retention choices for the Leads screen.
	 *
	 * @return array<int, string> Months => label.
	 */
	public static function retention_choices(): array {
		return array(
			3  => __( '3 months', 'wb-listora' ),
			6  => __( '6 months', 'wb-listora' ),
			12 => __( '12 months', 'wb-listora' ),
			24 => __( '24 months', 'wb-listora' ),
			0  => __( 'Until deleted', 'wb-listora' ),
		);
	}

	/**
	 * Delete leads older than the retention period (daily cron).
	 *
	 * @return int Rows deleted.
	 */
	public static function prune(): int {
		$months = self::retention_months();
		if ( $months <= 0 ) {
			return 0;
		}
		global $wpdb;
		$table  = self::table();
		$cutoff = gmdate( 'Y-m-d H:i:s', (int) strtotime( "-{$months} months" ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- plugin table, idx_created.
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) );
	}

	/**
	 * Register the privacy exporter: leads a person sent, by their email.
	 *
	 * @param array<string, mixed> $exporters Exporters.
	 * @return array<string, mixed>
	 */
	public static function register_exporter( $exporters ) {
		$exporters['wb-listora-leads'] = array(
			'exporter_friendly_name' => __( 'Listora contact messages', 'wb-listora' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Register the privacy eraser for the same messages.
	 *
	 * @param array<string, mixed> $erasers Erasers.
	 * @return array<string, mixed>
	 */
	public static function register_eraser( $erasers ) {
		$erasers['wb-listora-leads'] = array(
			'eraser_friendly_name' => __( 'Listora contact messages', 'wb-listora' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Export one page (100) of messages sent from an email address.
	 *
	 * @param string $email Email address.
	 * @param int    $page  1-based page.
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public static function export( $email, $page = 1 ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- plugin table, idx_email.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s ORDER BY id ASC LIMIT 100 OFFSET %d", (string) $email, ( max( 1, (int) $page ) - 1 ) * 100 ), ARRAY_A );
		$data = array();
		foreach ( (array) $rows as $row ) {
			$data[] = array(
				'group_id'    => 'wb-listora-leads',
				'group_label' => __( 'Listora contact messages', 'wb-listora' ),
				'item_id'     => 'wb-listora-lead-' . (int) $row['id'],
				'data'        => array(
					array(
						'name'  => __( 'Listing', 'wb-listora' ),
						'value' => get_the_title( (int) $row['listing_id'] ),
					),
					array(
						'name'  => __( 'Name', 'wb-listora' ),
						'value' => $row['name'],
					),
					array(
						'name'  => __( 'Phone', 'wb-listora' ),
						'value' => $row['phone'],
					),
					array(
						'name'  => __( 'Message', 'wb-listora' ),
						'value' => $row['message'],
					),
					array(
						'name'  => __( 'Sent', 'wb-listora' ),
						'value' => get_date_from_gmt( (string) $row['created_at'] ),
					),
				),
			);
		}
		return array(
			'data' => $data,
			'done' => count( (array) $rows ) < 100,
		);
	}

	/**
	 * Erase every message sent from an email address.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Unused; everything goes in one pass.
	 * @return array{items_removed: int, items_retained: int, messages: string[], done: bool}
	 */
	public static function erase( $email, $page = 1 ) {
		unset( $page );
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin table write.
		$removed = (int) $wpdb->delete( self::table(), array( 'email' => (string) $email ), array( '%s' ) );
		return array(
			'items_removed'  => $removed,
			'items_retained' => 0,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Typed lead row with the listing title and local time.
	 *
	 * @param array<string, mixed> $row Raw row.
	 * @return array<string, mixed>
	 */
	private static function shape( array $row ): array {
		return array(
			'id'            => (int) $row['id'],
			'listing_id'    => (int) $row['listing_id'],
			'listing_title' => wp_specialchars_decode( get_the_title( (int) $row['listing_id'] ), ENT_QUOTES ),
			'owner_id'      => (int) $row['owner_id'],
			'sender_id'     => (int) $row['sender_id'],
			'name'          => (string) $row['name'],
			'email'         => (string) $row['email'],
			'phone'         => (string) $row['phone'],
			'message'       => (string) $row['message'],
			'created_at'    => gmdate( 'Y-m-d\TH:i:s\Z', (int) strtotime( $row['created_at'] . ' UTC' ) ),
			'created_local' => get_date_from_gmt( (string) $row['created_at'] ),
		);
	}
}
