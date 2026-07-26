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
	 * The SINGLE source of truth (AUDIT-M): status registration
	 * (Post_Types::register_custom_statuses), the transition graph
	 * (self::transitions()) and the label list (self::get_statuses()) all derive
	 * from this map. Previously `pending_verification` was registered as a post
	 * status but omitted from the transition map AND the label list, so a listing
	 * in that status had no transition rules and no human-readable label while
	 * still appearing in admin columns/counts.
	 *
	 * Each entry: `label` (customer-facing), `transitions` (statuses it may move
	 * TO), and `register` (the register_post_status() args other than label).
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

		// Only propagate valid lifecycle transitions to the downstream
		// listeners (notifications, search reindex, etc.). A brand-new listing
		// enters from an internal WP status ('new' / 'auto-draft') that is not
		// a lifecycle source — always let those through so first-save indexing
		// and notifications run. Transitions from a KNOWN lifecycle status are
		// checked against the map, so a raw wp_update_post() into a status the
		// map doesn't allow (e.g. rejected -> publish) no longer fires hooks.
		// Site owners can override the map per-transition via the filter.
		$transitions         = self::transitions();
		$is_lifecycle_source = isset( $transitions[ $old_status ] );
		$allow_transition    = ! $is_lifecycle_source || self::is_valid_transition( $old_status, $new_status );

		/**
		 * Filters whether a listing status transition may fire downstream hooks.
		 *
		 * @param bool     $allow_transition Whether the transition is allowed.
		 * @param string   $old_status       Previous status.
		 * @param string   $new_status       New status.
		 * @param int      $post_id          Listing ID.
		 */
		$allow_transition = (bool) apply_filters( 'wb_listora_allow_status_transition', $allow_transition, $old_status, $new_status, $post->ID );

		if ( ! $allow_transition ) {
			return;
		}

		// Set expiration date on publish if not already set. A fresh
		// expiration also clears the reminder flags (handled inside
		// set_expiration) so the 7d/1d reminders fire for the new cycle —
		// they are NO LONGER cleared on every unrelated transition, which
		// previously re-armed reminders on any edit and caused duplicates.
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

		// Time-based expiration from type config.
		$days = (int) $type->get_prop( 'expiration_days' );

		// Check plan duration if Pro plan is set.
		$plan_id = (int) get_post_meta( $post_id, '_listora_plan_id', true );
		if ( $plan_id > 0 ) {
			$plan_days = (int) get_post_meta( $plan_id, '_listora_plan_duration_days', true );
			if ( $plan_days > 0 ) {
				$days = $plan_days;
			}
		}

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
