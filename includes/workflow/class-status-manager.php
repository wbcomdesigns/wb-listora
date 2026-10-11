<?php
/**
 * Status Manager — handles listing status transitions and validation.
 *
 * @package WBListora\Workflow
 *
 * @hook  filter wb_listora_listing_expiration_date( string $expiry_iso, int $post_id, array $context )
 *        Fires BEFORE Free writes the `_listora_expiration_date` post-meta. Returning a
 *        replacement ISO 8601 ("Y-m-d H:i:s", GMT) string lets Pro plan-based pricing override
 *        the type-config default. Free is the only writer of the meta key (Invariant #12).
 *        $context['context'] is one of: 'status_manager' (auto-set on publish) or 'renew'.
 */

namespace WBListora\Workflow;

defined( 'ABSPATH' ) || exit;

/**
 * Manages listing lifecycle statuses and transitions.
 */
class Status_Manager {

	/**
	 * Canonical definition of every WB Listora custom post status.
	 *
	 * The single source of truth: status registration, the transition graph
	 * (self::transitions()) and the label list (self::get_statuses()) all derive
	 * from this map. Each entry: `label` (customer-facing), `transitions`
	 * (statuses it may move TO), and `register` (other register_post_status() args).
	 *
	 * @return array<string, array{label:string, transitions:string[], register:array<string,mixed>}>
	 */
	public static function custom_statuses() {
		return array(
			'listora_rejected'     => array(
				'label'       => _x( 'Rejected', 'post status', 'wb-listora' ),
				'transitions' => array( 'pending', 'draft' ),
				'register'    => array(
					'public'                    => false,
					// The owner and editors can open it (to see why and fix it); visitors get a 404.
					'protected'                 => true,
					'exclude_from_search'       => true,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					/* translators: %s: count of rejected listings */
					'label_count'               => _n_noop( 'Rejected <span class="count">(%s)</span>', 'Rejected <span class="count">(%s)</span>', 'wb-listora' ),
				),
			),
			'listora_expired'      => array(
				'label'       => _x( 'Expired', 'post status', 'wb-listora' ),
				'transitions' => array( 'publish', 'draft', 'listora_payment' ),
				'register'    => array(
					'public'                    => true,
					'publicly_queryable'        => true,
					'exclude_from_search'       => true,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					/* translators: %s: count of expired listings */
					'label_count'               => _n_noop( 'Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>', 'wb-listora' ),
				),
			),
			'listora_deactivated'  => array(
				'label'       => _x( 'Deactivated', 'post status', 'wb-listora' ),
				'transitions' => array( 'publish', 'draft' ),
				'register'    => array(
					'public'                    => false,
					// The owner and editors can open it (to see why and fix it); visitors get a 404.
					'protected'                 => true,
					'exclude_from_search'       => true,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					/* translators: %s: count of deactivated listings */
					'label_count'               => _n_noop( 'Deactivated <span class="count">(%s)</span>', 'Deactivated <span class="count">(%s)</span>', 'wb-listora' ),
				),
			),
			// Slug kept as `listora_payment` for historical compatibility, but the
			// customer-facing label is "Awaiting Credits". A listing lands here when
			// plan activation could not deduct the required credits; it auto-resumes
			// once the vendor's balance is topped up.
			'listora_payment'      => array(
				'label'       => _x( 'Awaiting Credits', 'post status', 'wb-listora' ),
				'transitions' => array( 'pending', 'publish', 'draft' ),
				'register'    => array(
					'public'                    => false,
					// The owner and editors can open it (to see why and fix it); visitors get a 404.
					'protected'                 => true,
					'exclude_from_search'       => true,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					/* translators: %s: count of listings awaiting credit top-up */
					'label_count'               => _n_noop( 'Awaiting Credits <span class="count">(%s)</span>', 'Awaiting Credits <span class="count">(%s)</span>', 'wb-listora' ),
				),
			),
			'pending_verification' => array(
				'label'       => _x( 'Pending Email Verification', 'post status', 'wb-listora' ),
				'transitions' => array( 'pending', 'publish', 'draft', 'listora_rejected' ),
				'register'    => array(
					'public'                    => false,
					// The owner and editors can open it (to see why and fix it); visitors get a 404.
					'protected'                 => true,
					'exclude_from_search'       => true,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					/* translators: %s: count of listings awaiting email verification */
					'label_count'               => _n_noop( 'Pending Email Verification <span class="count">(%s)</span>', 'Pending Email Verification <span class="count">(%s)</span>', 'wb-listora' ),
				),
			),
		);
	}

	/**
	 * Valid status transitions (From => [allowed to statuses]).
	 *
	 * Core WP source statuses (draft/pending/publish) plus every custom status,
	 * whose targets derive from self::custom_statuses(). Single source with the
	 * registration + labels so no status can drift out of the graph again.
	 *
	 * @return array<string, string[]>
	 */
	public static function transitions() {
		$transitions = array(
			'draft'   => array( 'pending', 'publish', 'listora_payment', 'pending_verification' ),
			'pending' => array( 'publish', 'listora_rejected', 'draft' ),
			'publish' => array( 'listora_expired', 'listora_deactivated', 'draft', 'pending' ),
		);
		foreach ( self::custom_statuses() as $slug => $cfg ) {
			$transitions[ $slug ] = $cfg['transitions'];
		}
		return $transitions;
	}

	/**
	 * Constructor — hooks into status transitions.
	 */
	public function __construct() {
		add_action( 'transition_post_status', array( $this, 'on_transition' ), 10, 3 );

		// Re-run expiration write after the submission action chain so Pro plugins
		// that attach `_listora_plan_id` on `wb_listora_listing_submitted` get a
		// chance to override the default via `wb_listora_listing_expiration_date`.
		// The transition_post_status handler runs INSIDE wp_insert_post — before
		// any Pro listener attaches the plan ID — so without this catch-up call
		// auto-published listings never get a plan-derived expiry.
		add_action( 'wb_listora_listing_submitted', array( $this, 'on_after_submission' ), 20, 1 );

		// Every entry point follows the transition map, not only change_status():
		// Quick Edit, Bulk Edit, the classic editor and WP-CLI save through
		// wp_insert_post, the block editor through the core REST route.
		add_filter( 'wp_insert_post_data', array( $this, 'keep_status_on_invalid_move' ), 10, 2 );
		add_filter( 'rest_pre_insert_listora_listing', array( $this, 'refuse_invalid_move_over_rest' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'render_refused_move_notice' ) );

		// Core shows a non-public post only to roles with edit_post, so a
		// Subscriber owner got a 404 on their own pending or rejected listing.
		add_filter( 'posts_results', array( $this, 'remember_owned_unpublished' ), 10, 2 );
		// First on the_posts, so access plugins filtering later still get the final say.
		add_filter( 'the_posts', array( $this, 'restore_owned_unpublished' ), PHP_INT_MIN, 2 );
	}

	/**
	 * The listing a single front-end query found before core's status gate ran.
	 *
	 * @var \WP_Post|null
	 */
	private $owned_unpublished = null;

	/**
	 * Remember the single listing the main query found, before core's status gate.
	 *
	 * @param \WP_Post[] $posts Posts found.
	 * @param \WP_Query  $query Query.
	 * @return \WP_Post[]
	 */
	public function remember_owned_unpublished( $posts, $query ) {
		$this->owned_unpublished = null;
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_single || 1 !== count( $posts ) ) {
			return $posts;
		}
		$post   = $posts[0];
		$status = get_post_status_object( $post->post_status );
		if ( 'listora_listing' === $post->post_type && $status && $status->protected
			&& (int) $post->post_author === get_current_user_id() ) {
			$this->owned_unpublished = $post;
		}
		return $posts;
	}

	/**
	 * Give an owner back their own listing when core hid it for lack of edit_post.
	 *
	 * Read-only: the page renders as a preview with the owner bar; editing still
	 * goes through the listing's own capability checks.
	 *
	 * @param \WP_Post[] $posts Posts after core's status gate.
	 * @param \WP_Query  $query Query.
	 * @return \WP_Post[]
	 */
	public function restore_owned_unpublished( $posts, $query ) {
		if ( $this->owned_unpublished && ! $posts && $query->is_main_query() ) {
			$posts             = array( $this->owned_unpublished );
			$query->is_preview = true;
		}
		$this->owned_unpublished = null;
		return $posts;
	}

	/**
	 * Whether a listing may move from one status to another.
	 *
	 * A brand-new listing enters from an internal WP status ('new',
	 * 'auto-draft') that is not a lifecycle source, and trash / untrash are
	 * WordPress's own; those always pass. Other moves follow the map.
	 *
	 * @since 2.0.0
	 *
	 * @param string $old_status Current status.
	 * @param string $new_status Requested status.
	 * @param int    $post_id    Listing ID.
	 * @return bool
	 */
	public static function transition_allowed( $old_status, $new_status, $post_id ) {
		$allow = $old_status === $new_status || 'trash' === $new_status || 'trash' === $old_status
			|| ! isset( self::transitions()[ $old_status ] ) || self::is_valid_transition( $old_status, $new_status );

		/**
		 * Filters whether a listing status transition is allowed.
		 *
		 * @param bool   $allow_transition Whether the transition is allowed.
		 * @param string $old_status       Previous status.
		 * @param string $new_status       New status.
		 * @param int    $post_id          Listing ID.
		 */
		return (bool) apply_filters( 'wb_listora_allow_status_transition', $allow, $old_status, $new_status, (int) $post_id );
	}

	/**
	 * Keep a listing's status when a raw save asks for a move the map refuses.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $data    Sanitised post data.
	 * @param array<string, mixed> $postarr Raw post array (ID when updating).
	 * @return array<string, mixed>
	 */
	public function keep_status_on_invalid_move( $data, $postarr ) {
		$post_id = (int) ( $postarr['ID'] ?? 0 );
		if ( $post_id < 1 || 'listora_listing' !== ( $data['post_type'] ?? '' ) ) {
			return $data;
		}
		$old = (string) get_post_status( $post_id );
		$new = (string) ( $data['post_status'] ?? $old );
		if ( ! self::transition_allowed( $old, $new, $post_id ) ) {
			$data['post_status'] = $old;
			if ( get_current_user_id() ) {
				set_transient( 'wb_listora_refused_move_' . get_current_user_id(), array( $old, $new ), MINUTE_IN_SECONDS );
			}
		}
		return $data;
	}

	/**
	 * Refuse the same move on the core REST route (block editor, /wp/v2).
	 *
	 * @since 2.0.0
	 *
	 * @param \stdClass|\WP_Error $prepared Prepared post.
	 * @param \WP_REST_Request     $request  Request.
	 * @return \stdClass|\WP_Error
	 */
	public function refuse_invalid_move_over_rest( $prepared, $request ) {
		if ( is_wp_error( $prepared ) || empty( $prepared->ID ) || empty( $prepared->post_status ) ) {
			return $prepared;
		}
		$old = (string) get_post_status( (int) $prepared->ID );
		if ( self::transition_allowed( $old, (string) $prepared->post_status, (int) $prepared->ID ) ) {
			return $prepared;
		}
		return new \WP_Error(
			'listora_invalid_transition',
			self::refused_move_message( $old, (string) $prepared->post_status ),
			array( 'status' => 409 )
		);
	}

	/**
	 * Tell the admin why the status they picked did not stick.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function render_refused_move_notice() {
		$key  = 'wb_listora_refused_move_' . get_current_user_id();
		$move = get_transient( $key );
		if ( ! is_array( $move ) ) {
			return;
		}
		delete_transient( $key );
		echo '<div class="notice notice-warning is-dismissible listora-notice"><p>' . esc_html( self::refused_move_message( $move[0], $move[1] ) ) . '</p></div>';
	}

	/**
	 * The one sentence every refused move shows.
	 *
	 * @param string $from Current status.
	 * @param string $to   Requested status.
	 * @return string
	 */
	private static function refused_move_message( $from, $to ) {
		$labels = self::get_statuses();
		$from_l = $labels[ $from ] ?? $from;
		$to_l   = $labels[ $to ] ?? $to;
		if ( 'listora_rejected' === $from && 'publish' === $to ) {
			/* translators: 1: current status label. */
			return sprintf( __( 'This listing is %1$s, so it was not published. Set it to Pending Review, then approve it.', 'wb-listora' ), $from_l );
		}
		/* translators: 1: current status label, 2: requested status label. */
		return sprintf( __( 'A %1$s listing cannot be moved to %2$s, so its status was kept.', 'wb-listora' ), $from_l, $to_l );
	}

	/**
	 * Catch-up expiration write after the full submission chain has run.
	 *
	 * @param int $post_id Listing ID.
	 */
	public function on_after_submission( $post_id ) {
		$post_id = (int) $post_id;
		if ( ! $post_id || 'listora_listing' !== get_post_type( $post_id ) ) {
			return;
		}
		if ( get_post_meta( $post_id, '_listora_expiration_date', true ) ) {
			return;
		}
		$this->set_expiration( $post_id );
	}

	/**
	 * Handle status transitions.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post object.
	 */
	public function on_transition( $new_status, $old_status, $post ) {
		if ( 'listora_listing' !== $post->post_type || $new_status === $old_status ) {
			return;
		}

		// A save that bypassed the guards below (or one they let through) still
		// fires no lifecycle hooks unless the move is allowed.
		if ( ! self::transition_allowed( $old_status, $new_status, $post->ID ) ) {
			return;
		}

		// Set expiration date on publish if not already set. A fresh
		// expiration also clears the reminder flags (handled inside
		// set_expiration) so the 7d/1d reminders fire for the new cycle —
		// they are NOT cleared on every unrelated transition, which would
		// re-arm reminders on any edit and cause duplicates.
		if ( 'publish' === $new_status && ! get_post_meta( $post->ID, '_listora_expiration_date', true ) ) {
			$this->set_expiration( $post->ID );
		}

		// Track when a listing entered the awaiting-credits state so the
		// expiration cron can clean up entries whose payment never completes
		// (a listing stuck in listora_payment would otherwise live forever).
		// The stamp is cleared when the listing leaves the payment state.
		if ( 'listora_payment' === $new_status ) {
			if ( ! get_post_meta( $post->ID, '_listora_payment_pending_since', true ) ) {
				update_post_meta( $post->ID, '_listora_payment_pending_since', current_time( 'mysql', true ) );
			}
		} elseif ( 'listora_payment' === $old_status ) {
			delete_post_meta( $post->ID, '_listora_payment_pending_since' );
		}

		/**
		 * Fires on listing status change.
		 *
		 * @param int    $post_id    Post ID.
		 * @param string $new_status New status.
		 * @param string $old_status Old status.
		 */
		do_action( "wb_listora_listing_{$new_status}", $post->ID, $old_status );
	}

	/**
	 * How long a listing runs before it expires, in days (0 = lifetime).
	 *
	 * The plan's duration when it sets one, else the listing type's, else
	 * Settings > General > Default expiration. Publish and renewal both use
	 * this, so a 0 means the same thing everywhere.
	 *
	 * @since 1.9.0
	 *
	 * @param int $post_id Listing ID.
	 * @return int
	 */
	public static function standard_duration_days( $post_id ) {
		$plan_id = (int) get_post_meta( (int) $post_id, '_listora_plan_id', true );
		$days    = $plan_id > 0 ? (int) get_post_meta( $plan_id, '_listora_plan_duration_days', true ) : 0;

		if ( $days <= 0 ) {
			$type = \WBListora\Core\Listing_Type_Registry::instance()->get_for_post( (int) $post_id );
			$days = $type ? (int) $type->get_prop( 'expiration_days' ) : 0;
		}

		return $days > 0 ? $days : max( 0, (int) wb_listora_get_setting( 'default_expiration', 0 ) );
	}

	/**
	 * Set expiration date for a listing based on type config or plan.
	 *
	 * @param int $post_id Post ID.
	 */
	private function set_expiration( $post_id ) {
		$registry = \WBListora\Core\Listing_Type_Registry::instance();
		$type     = $registry->get_for_post( $post_id );

		if ( ! $type ) {
			return;
		}

		// Check for date-based expiration (events use end_date).
		if ( $type->has_end_date_field() ) {
			$end_date = \WBListora\Core\Meta_Handler::get_value( $post_id, 'end_date' );
			if ( ! $end_date ) {
				$end_date = \WBListora\Core\Meta_Handler::get_value( $post_id, 'deadline' );
			}
			$end_ts = $end_date ? self::local_datetime_to_timestamp( $end_date ) : 0;
			if ( $end_ts > 0 ) {
				$grace  = DAY_IN_SECONDS; // 24h after event ends.
				$expiry = gmdate( 'Y-m-d H:i:s', $end_ts + $grace );
				/** Documented in this class docblock. */
				$expiry = (string) apply_filters( 'wb_listora_listing_expiration_date', $expiry, $post_id, array( 'context' => 'status_manager' ) );
				update_post_meta( $post_id, '_listora_expiration_date', $expiry );
				$this->clear_expiry_reminders( $post_id );
				return;
			}
		}

		$days = self::standard_duration_days( $post_id );

		if ( $days > 0 ) {
			$expiry = gmdate( 'Y-m-d H:i:s', time() + ( $days * DAY_IN_SECONDS ) );
			/** Documented in this class docblock. */
			$expiry = (string) apply_filters( 'wb_listora_listing_expiration_date', $expiry, $post_id, array( 'context' => 'status_manager' ) );
			update_post_meta( $post_id, '_listora_expiration_date', $expiry );
			$this->clear_expiry_reminders( $post_id );
		}
	}

	/**
	 * Clear the expiry-reminder flags for a listing.
	 *
	 * Called only when a FRESH expiration date is written (first publish or
	 * renew) so the 7-day / 1-day reminders fire again for the new cycle.
	 * Previously these were cleared on EVERY status transition, which reset
	 * the reminders on unrelated edits and caused duplicate reminder emails.
	 *
	 * @param int $post_id Post ID.
	 */
	private function clear_expiry_reminders( $post_id ) {
		delete_post_meta( $post_id, '_listora_expiry_reminded_7d' );
		delete_post_meta( $post_id, '_listora_expiry_reminded_1d' );
	}

	/**
	 * Convert a site-local date/datetime string to a Unix timestamp.
	 *
	 * Event end_date / deadline values are entered in the site's local
	 * timezone. Parsing them explicitly in wp_timezone() keeps the derived
	 * GMT expiration correct on sites whose timezone is not UTC — a bare
	 * strtotime() assumes UTC and would shift the expiry by the site offset.
	 *
	 * @param string $value Local date or datetime string.
	 * @return int Unix timestamp, or 0 when unparseable.
	 */
	private static function local_datetime_to_timestamp( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return 0;
		}
		try {
			$dt = new \DateTimeImmutable( $value, wp_timezone() );
			return $dt->getTimestamp();
		} catch ( \Exception $e ) {
			$ts = strtotime( $value );
			return $ts ? (int) $ts : 0;
		}
	}

	/**
	 * Move a listing to a new status, refusing transitions the map does not allow.
	 *
	 * The one write path for moderation (admin bulk actions and REST
	 * bulk-moderate). REST approve used to write `publish` on a rejected listing
	 * unguarded; on_transition() then skipped its listeners, so the listing went
	 * live with no expiry and no approval email. The write still goes through
	 * wp_update_post() so the transition_post_status chain fires as everywhere.
	 *
	 * @since 1.9.1
	 *
	 * @param int    $post_id Listing ID.
	 * @param string $to      Target status.
	 * @return true|\WP_Error
	 */
	public static function change_status( $post_id, $to ) {
		$from = (string) get_post_status( $post_id );
		if ( ! self::transition_allowed( $from, $to, (int) $post_id ) ) {
			return new \WP_Error( 'listora_invalid_transition', self::refused_move_message( $from, $to ), array( 'status' => 409 ) );
		}

		$res = wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $to,
			),
			true
		);
		return is_wp_error( $res ) ? $res : true;
	}

	/**
	 * Check if a status transition is valid.
	 *
	 * @param string $from Current status.
	 * @param string $to   Target status.
	 * @return bool
	 */
	public static function is_valid_transition( $from, $to ) {
		$transitions = self::transitions();
		if ( ! isset( $transitions[ $from ] ) ) {
			return false;
		}
		return in_array( $to, $transitions[ $from ], true );
	}

	/**
	 * Get all valid statuses for listings.
	 *
	 * @return array
	 */
	public static function get_statuses() {
		// Core WP statuses first, then every custom status label derived from the
		// canonical custom_statuses() map so labels never drift from registration.
		$statuses = array(
			'draft'   => __( 'Draft', 'wb-listora' ),
			'pending' => __( 'Pending Review', 'wb-listora' ),
			'publish' => __( 'Published', 'wb-listora' ),
		);
		foreach ( self::custom_statuses() as $slug => $cfg ) {
			$statuses[ $slug ] = $cfg['label'];
		}
		return $statuses;
	}
}
