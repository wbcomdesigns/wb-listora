<?php
/**
 * Member-to-member blocking — "I do not want to see this person, or hear from them".
 *
 * NOT THE SAME AS SUSPENSION
 *
 * {@see Member_Suspension} is the OWNER stopping someone posting at all. This is
 * one MEMBER deciding they do not want another member's content in their view.
 * The blocked person keeps posting normally; the blocker simply stops seeing it.
 * Conflating the two would let any member silence any other.
 *
 * WHY IT EXISTS
 *
 * App Store Guideline 1.2 requires an app carrying user-generated content to
 * offer BOTH reporting and blocking. Listora had reporting only, which is an
 * automatic rejection — so this is a release blocker for the mobile app, not a
 * nice-to-have.
 *
 * WHAT IT COVERS, AND WHAT IT DELIBERATELY DOES NOT
 *
 * Covered: reviews and review replies (the member-authored content here), and
 * direct contact between the two people.
 *
 * NOT covered: listings. A listing is a BUSINESS, not a person's post. Hiding
 * businesses from search because you blocked the owner degrades the directory
 * for the very person who blocked them — they lose real results and cannot tell
 * why. Apple asks that you can stop seeing abusive users' content and stop them
 * contacting you; it does not ask a directory to hide shops.
 *
 * BLOCKS ARE MUTUAL
 *
 * If A blocks B, neither sees the other. One-way blocking is a known harassment
 * vector: the blocked party can still read everything the blocker writes and
 * follow them around the site. `is_blocked_pair()` is therefore symmetric, and
 * every read site uses it rather than the raw list.
 *
 * ADMINS AND MODERATORS ARE UNAFFECTED
 *
 * Moderation views must show everything. Filtering is applied to what a MEMBER
 * sees, never to the admin reviews list or the moderator queue — a moderator who
 * cannot see a reported review cannot moderate it.
 *
 * @package WBListora\Core
 * @since   1.5.0
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Member-to-member blocking.
 */
class Member_Blocks {

	/**
	 * User meta holding the blocker's list of blocked user IDs.
	 *
	 * Stored on the BLOCKER so the common read — "who has this viewer blocked?"
	 * — is a single user-meta hit that WordPress has already primed with the
	 * session. The reverse question is answered by the symmetric helper rather
	 * than by a second list that could drift out of step with this one.
	 *
	 * @var string
	 */
	const META_BLOCKED = '_listora_blocked_members';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'deleted_user', array( $this, 'purge_deleted_user' ), 10, 1 );
	}

	/**
	 * The IDs a member has blocked.
	 *
	 * @param int $user_id Blocker. Defaults to the current user.
	 * @return int[]
	 */
	public static function blocked_by( int $user_id = 0 ): array {
		$user_id = $user_id ? $user_id : get_current_user_id();

		if ( $user_id <= 0 ) {
			return array();
		}

		$raw = get_user_meta( $user_id, self::META_BLOCKED, true );
		$ids = is_array( $raw ) ? array_map( 'intval', $raw ) : array();

		/**
		 * Filters the list of members a given member has blocked.
		 *
		 * @since 1.5.0
		 *
		 * @param int[] $ids     Blocked user IDs.
		 * @param int   $user_id The blocker.
		 */
		return array_values( array_unique( array_filter( (array) apply_filters( 'wb_listora_blocked_members', $ids, $user_id ) ) ) );
	}

	/**
	 * Whether these two members are blocked from each other, in EITHER direction.
	 *
	 * Symmetric on purpose — see the class docblock. Every display decision uses
	 * this, never `blocked_by()` alone.
	 *
	 * @param int $viewer Person doing the looking.
	 * @param int $author Person whose content it is.
	 * @return bool
	 */
	public static function is_blocked_pair( int $viewer, int $author ): bool {
		if ( $viewer <= 0 || $author <= 0 || $viewer === $author ) {
			return false;
		}

		return in_array( $author, self::blocked_by( $viewer ), true )
			|| in_array( $viewer, self::blocked_by( $author ), true );
	}

	/**
	 * Every ID to hide from this viewer: people they blocked, plus people who
	 * blocked them.
	 *
	 * The second half needs a meta query, so the result is memoised per request —
	 * a listing page asks this once per review otherwise.
	 *
	 * @param int $viewer Viewer. Defaults to the current user.
	 * @return int[]
	 */
	public static function hidden_from( int $viewer = 0 ): array {
		$cache = &self::memo();

		$viewer = $viewer ? $viewer : get_current_user_id();

		if ( $viewer <= 0 ) {
			return array();
		}

		if ( isset( $cache[ $viewer ] ) ) {
			return $cache[ $viewer ];
		}

		global $wpdb;

		$outgoing = self::blocked_by( $viewer );

		// Who has blocked this viewer. Serialised meta means a LIKE, which is
		// why this is memoised; the alternative is a join table, and a member's
		// block list is small enough that a table is not worth the migration.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$incoming = (array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value LIKE %s",
				self::META_BLOCKED,
				'%' . $wpdb->esc_like( ':' . $viewer . ';' ) . '%'
			)
		);

		// The LIKE is a coarse pre-filter — `i:5;` also matches an ARRAY INDEX
		// of 5, not just the value — so every candidate is confirmed against the
		// real list before it counts.
		$confirmed = array();
		foreach ( array_map( 'intval', $incoming ) as $candidate ) {
			if ( in_array( $viewer, self::blocked_by( $candidate ), true ) ) {
				$confirmed[] = $candidate;
			}
		}

		$cache[ $viewer ] = array_values( array_unique( array_merge( $outgoing, $confirmed ) ) );

		return $cache[ $viewer ];
	}

	/**
	 * Per-request memo for {@see self::hidden_from()}.
	 *
	 * A by-reference static rather than a local one, so {@see self::flush()} can
	 * clear it. Without that, blocking and then reading in the SAME request
	 * returns the pre-block list — the read is served from a memo populated
	 * before the block existed. Rare in production, where the block and the next
	 * page load are different requests, but it is still a wrong answer and it
	 * makes the behaviour untestable.
	 *
	 * @return array<int,int[]>
	 */
	private static function &memo(): array {
		static $cache = array();

		return $cache;
	}

	/**
	 * Drop the memoised hidden-from lists.
	 *
	 * Called whenever the block graph changes.
	 *
	 * @return void
	 */
	public static function flush(): void {
		$cache = &self::memo();
		$cache = array();
	}

	/**
	 * Block a member.
	 *
	 * @param int $user_id Blocker.
	 * @param int $target  Person being blocked.
	 * @return true|\WP_Error
	 */
	public static function block( int $user_id, int $target ) {
		if ( $user_id <= 0 || $target <= 0 ) {
			return new \WP_Error( 'listora_invalid_block', __( 'Invalid member.', 'wb-listora' ), array( 'status' => 400 ) );
		}

		if ( $user_id === $target ) {
			return new \WP_Error( 'listora_block_self', __( 'You cannot block yourself.', 'wb-listora' ), array( 'status' => 400 ) );
		}

		if ( ! get_user_by( 'id', $target ) ) {
			return new \WP_Error( 'listora_block_no_user', __( 'That member no longer exists.', 'wb-listora' ), array( 'status' => 404 ) );
		}

		$ids = self::blocked_by( $user_id );

		if ( ! in_array( $target, $ids, true ) ) {
			$ids[] = $target;
			update_user_meta( $user_id, self::META_BLOCKED, array_values( $ids ) );
		}

		self::flush();

		/**
		 * Fires after one member blocks another.
		 *
		 * @since 1.5.0
		 *
		 * @param int $user_id Blocker.
		 * @param int $target  Blocked member.
		 */
		do_action( 'wb_listora_member_blocked', $user_id, $target );

		return true;
	}

	/**
	 * Unblock a member.
	 *
	 * @param int $user_id Blocker.
	 * @param int $target  Person being unblocked.
	 * @return true|\WP_Error
	 */
	public static function unblock( int $user_id, int $target ) {
		if ( $user_id <= 0 || $target <= 0 ) {
			return new \WP_Error( 'listora_invalid_block', __( 'Invalid member.', 'wb-listora' ), array( 'status' => 400 ) );
		}

		$ids  = self::blocked_by( $user_id );
		$next = array_values( array_diff( $ids, array( $target ) ) );

		if ( $next ) {
			update_user_meta( $user_id, self::META_BLOCKED, $next );
		} else {
			delete_user_meta( $user_id, self::META_BLOCKED );
		}

		self::flush();

		/**
		 * Fires after one member unblocks another.
		 *
		 * @since 1.5.0
		 *
		 * @param int $user_id Blocker.
		 * @param int $target  Unblocked member.
		 */
		do_action( 'wb_listora_member_unblocked', $user_id, $target );

		return true;
	}

	/**
	 * Whether the viewer may contact this member.
	 *
	 * Used by the contact and lead forms. A block has to stop the message as
	 * well as the content, or the person you blocked can still reach your inbox.
	 *
	 * @param int $viewer Sender.
	 * @param int $target Recipient.
	 * @return bool
	 */
	public static function can_contact( int $viewer, int $target ): bool {
		return ! self::is_blocked_pair( $viewer, $target );
	}

	/**
	 * Drop a deleted user from everyone's block list.
	 *
	 * Without this the meta keeps a dangling id forever, and — because ids are
	 * reused by some hosts on restore — a future member could inherit a block
	 * they never earned.
	 *
	 * @param int $user_id Deleted user.
	 * @return void
	 */
	public function purge_deleted_user( $user_id ): void {
		global $wpdb;

		$user_id = (int) $user_id;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$holders = (array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value LIKE %s",
				self::META_BLOCKED,
				'%' . $wpdb->esc_like( ':' . $user_id . ';' ) . '%'
			)
		);

		foreach ( array_map( 'intval', $holders ) as $holder ) {
			if ( in_array( $user_id, self::blocked_by( $holder ), true ) ) {
				self::unblock( $holder, $user_id );
			}
		}

		delete_user_meta( $user_id, self::META_BLOCKED );
	}
}
