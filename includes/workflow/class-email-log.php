<?php
/**
 * Email log storage: one row per email Listora (Free or Pro) sends.
 *
 * Replaces the 1,000-entry `wb_listora_notification_log` option. Rows keep
 * the full body and headers, so the Email Log can show exactly what a member
 * received and resend it (card 10337184050); retention prunes old rows.
 *
 * @package WBListora\Workflow
 */

namespace WBListora\Workflow;

defined( 'ABSPATH' ) || exit;

/**
 * Email_Log
 *
 * @since 1.9.0
 */
class Email_Log {

	/**
	 * The table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'email_log';
	}

	/**
	 * Record one send.
	 *
	 * @param array $entry event_key, recipient, subject, success, error, and
	 *                     optionally body, headers, sent_at (UTC), resent_from.
	 * @return int Row ID, or 0.
	 */
	public static function insert( array $entry ) {
		global $wpdb;
		$headers = $entry['headers'] ?? '';
		$ok      = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::table(),
			array(
				'sent_at'     => (string) ( $entry['sent_at'] ?? current_time( 'mysql', true ) ),
				'event_key'   => substr( (string) ( $entry['event_key'] ?? '' ), 0, 100 ),
				'recipient'   => substr( (string) ( $entry['recipient'] ?? '' ), 0, 255 ),
				'subject'     => substr( (string) ( $entry['subject'] ?? '' ), 0, 500 ),
				'body'        => isset( $entry['body'] ) ? (string) $entry['body'] : null,
				'headers'     => is_array( $headers ) ? implode( "\n", $headers ) : (string) $headers,
				'success'     => empty( $entry['success'] ) ? 0 : 1,
				'error'       => (string) ( $entry['error'] ?? '' ),
				'resent_from' => (int) ( $entry['resent_from'] ?? 0 ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d' )
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * One row.
	 *
	 * @param int $id Row ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A );
		return $row ? $row : null;
	}

	/**
	 * Bodies for several rows, in one query (the Email Log drawers).
	 *
	 * @param int[] $ids Row IDs.
	 * @return array<int, string> id => body.
	 */
	public static function bodies( array $ids ) {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( ! $ids ) {
			return array();
		}
		$table        = self::table();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- placeholders built above.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, body FROM {$table} WHERE id IN ({$placeholders})", ...$ids ), ARRAY_A );
		return wp_list_pluck( $rows, 'body', 'id' );
	}

	/**
	 * A page of rows, newest first, and the total matching.
	 *
	 * @param array $args {
	 *     @type string $event     Event key.
	 *     @type string $result    'sent' or 'failed'.
	 *     @type string $recipient Part of the recipient address.
	 *     @type string $search    Part of the subject.
	 *     @type string $order     'ASC' or 'DESC' (default).
	 *     @type int    $limit     Page size.
	 *     @type int    $offset    Offset.
	 * }
	 * @return array{rows: array<int, array<string, mixed>>, total: int}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$table  = self::table();
		$where  = '1=1';
		$params = array();
		if ( ! empty( $args['event'] ) ) {
			$where   .= ' AND event_key = %s';
			$params[] = (string) $args['event'];
		}
		if ( 'sent' === ( $args['result'] ?? '' ) ) {
			$where .= ' AND success = 1';
		} elseif ( 'failed' === ( $args['result'] ?? '' ) ) {
			$where .= ' AND success = 0';
		}
		if ( ! empty( $args['recipient'] ) ) {
			$where   .= ' AND recipient LIKE %s';
			$params[] = '%' . $wpdb->esc_like( (string) $args['recipient'] ) . '%';
		}
		if ( ! empty( $args['search'] ) ) {
			$where   .= ' AND subject LIKE %s';
			$params[] = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
		}
		$order = 'ASC' === ( $args['order'] ?? '' ) ? 'ASC' : 'DESC';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- placeholdered SQL from a fixed template.
		$total = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where}", ...$params ) ) : $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" ) );
		$rows  = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, sent_at, event_key, recipient, subject, success, error, resent_from, ( body IS NOT NULL AND body <> '' ) AS has_body FROM {$table} WHERE {$where} ORDER BY sent_at {$order}, id {$order} LIMIT %d OFFSET %d",
				...array_merge( $params, array( max( 1, (int) ( $args['limit'] ?? 25 ) ), max( 0, (int) ( $args['offset'] ?? 0 ) ) ) )
			),
			ARRAY_A
		);
		// phpcs:enable
		return array(
			'rows'  => $rows,
			'total' => $total,
		);
	}

	/**
	 * Rows per result, for the views: [ 'all' => n, 'sent' => n, 'failed' => n ].
	 *
	 * @return array<string, int>
	 */
	public static function counts() {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- idx_success_sent covers it.
		$rows   = (array) $wpdb->get_results( "SELECT success, COUNT(*) AS n FROM {$table} GROUP BY success", ARRAY_A );
		$counts = array(
			'all'    => 0,
			'sent'   => 0,
			'failed' => 0,
		);
		foreach ( $rows as $row ) {
			$counts[ $row['success'] ? 'sent' : 'failed' ] = (int) $row['n'];
			$counts['all']                                += (int) $row['n'];
		}
		return $counts;
	}

	/**
	 * The event keys that appear in the log, for the Event filter.
	 *
	 * @return string[]
	 */
	public static function events() {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- idx_event_sent covers it.
		return array_map( 'strval', (array) $wpdb->get_col( "SELECT DISTINCT event_key FROM {$table} WHERE event_key <> '' ORDER BY event_key" ) );
	}

	/**
	 * Send a logged email again, as it was, and log the new attempt.
	 *
	 * @param int $id Row ID.
	 * @return bool Whether wp_mail() accepted it.
	 */
	public static function resend( $id ) {
		$row = self::get( $id );
		if ( ! $row || '' === (string) $row['body'] ) {
			return false;
		}
		$headers = array_filter( array_map( 'trim', explode( "\n", (string) $row['headers'] ) ) );
		$error   = '';
		$capture = static function ( $wp_error ) use ( &$error ) {
			if ( is_wp_error( $wp_error ) ) {
				$error = $wp_error->get_error_message();
			}
		};
		add_action( 'wp_mail_failed', $capture );
		$sent = (bool) wp_mail( (string) $row['recipient'], (string) $row['subject'], (string) $row['body'], $headers ? $headers : array( 'Content-Type: text/html; charset=UTF-8' ) );
		remove_action( 'wp_mail_failed', $capture );

		self::insert(
			array(
				'event_key'   => (string) $row['event_key'],
				'recipient'   => (string) $row['recipient'],
				'subject'     => (string) $row['subject'],
				'body'        => (string) $row['body'],
				'headers'     => (string) $row['headers'],
				'success'     => $sent,
				'error'       => $sent ? '' : ( '' !== $error ? $error : __( 'wp_mail() returned false.', 'wb-listora' ) ),
				'resent_from' => (int) $row['id'],
			)
		);
		return $sent;
	}

	/**
	 * Delete rows older than N days.
	 *
	 * @param int $days Retention in days; 0 keeps everything.
	 * @return int Rows deleted.
	 */
	public static function prune( $days ) {
		global $wpdb;
		if ( (int) $days <= 0 ) {
			return 0;
		}
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- idx_sent covers it.
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE sent_at < %s", gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS * (int) $days ) ) );
	}

	/**
	 * Delete every row.
	 */
	public static function clear() {
		global $wpdb;
		$table = self::table();
		$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Move the pre-1.9.0 option log into the table (oldest first, so IDs
	 * follow time), then drop the option. Those entries have no body.
	 *
	 * @return int Rows imported.
	 */
	public static function import_legacy_option() {
		$legacy = get_option( Notifications::LOG_OPTION_KEY, null );
		if ( ! is_array( $legacy ) ) {
			return 0;
		}
		$imported = 0;
		foreach ( array_reverse( $legacy ) as $entry ) {
			if ( is_array( $entry ) && self::insert( $entry ) ) {
				++$imported;
			}
		}
		delete_option( Notifications::LOG_OPTION_KEY );
		return $imported;
	}
}
