<?php
/**
 * Notifications — email notifications for listing lifecycle events.
 *
 * @package WBListora\Workflow
 */

namespace WBListora\Workflow;

defined( 'ABSPATH' ) || exit;

/**
 * Handles email notification events for all listing lifecycle actions.
 */
class Notifications {

	/**
	 * Option key holding the rolling email log (capped circular buffer).
	 *
	 * @var string
	 */
	const LOG_OPTION_KEY = 'wb_listora_notification_log';

	/**
	 * Maximum number of email log entries to retain — hard ceiling that
	 * prevents the option row from growing unbounded between cron runs.
	 * The retention-days setting (below) is the real policy; this is the
	 * safety net.
	 *
	 * @var int
	 */
	const LOG_MAX_ENTRIES = 1000;

	/**
	 * Option key holding the retention policy (days). 0 = lifetime.
	 *
	 * Why default 7 days: email logs are diagnostic noise after a week —
	 * an admin investigating a delivery issue typically catches it within
	 * a day or two of the user report. Beyond that the rows are dead
	 * weight in wp_options. Site owners with compliance/audit needs can
	 * raise to 15 / 30 or set 0 (lifetime) on the Email Log page.
	 *
	 * @var string
	 */
	const RETENTION_OPTION_KEY = 'wb_listora_notification_log_retention_days';

	/**
	 * Default retention window if the option is unset.
	 *
	 * @var int
	 */
	const DEFAULT_RETENTION_DAYS = 7;

	/**
	 * Allowed retention windows surfaced in the Email Log dropdown.
	 *
	 * @return array<int, string> Days => label.
	 */
	public static function retention_choices(): array {
		return array(
			7  => __( '7 days (default)', 'wb-listora' ),
			15 => __( '15 days', 'wb-listora' ),
			30 => __( '30 days', 'wb-listora' ),
			0  => __( 'Lifetime (no auto-prune)', 'wb-listora' ),
		);
	}

	/**
	 * Resolve the active retention window in days. Sanitizes to one of the
	 * choices above; falls back to the default for invalid stored values.
	 *
	 * @return int 0 (lifetime) or one of 7/15/30.
	 */
	public static function get_retention_days(): int {
		$raw = (int) get_option( self::RETENTION_OPTION_KEY, self::DEFAULT_RETENTION_DAYS );
		return array_key_exists( $raw, self::retention_choices() ) ? $raw : self::DEFAULT_RETENTION_DAYS;
	}

	/**
	 * Hook name fired by Action Scheduler / WP-Cron for the daily prune.
	 *
	 * @var string
	 */
	const PRUNE_HOOK = 'wb_listora_prune_email_log';

	/**
	 * Constructor — hook into all notification events.
	 */
	public function __construct() {
		// Daily retention-prune cron listener. Hook lives here (not in
		// activator) so a temporary deactivation + reactivation doesn't lose
		// the listener — class is constructed on every request.
		add_action( self::PRUNE_HOOK, array( __CLASS__, 'prune_log' ) );

		// Register the public one-click unsubscribe REST route. Hooked on the
		// `wb_listora_rest_api_init` extension point (fired after core routes
		// in Plugin::register_rest_routes) so the email opt-out endpoint is
		// owned here alongside the email pipeline that emits its links —
		// rather than coupling the Plugin bootstrap to the unsubscribe feature.
		add_action(
			'wb_listora_rest_api_init',
			static function (): void {
				( new \WBListora\REST\Unsubscribe_Controller() )->register_routes();
			}
		);

		// Schedule the daily prune (idempotent). BC smoke 2026-05-25:
		// Notifications is constructed at init@15 (via Plugin::init_workflow).
		// Registering at default priority 10 means the slot has already passed
		// this request, AND on subsequent requests the constructor re-registers
		// before init@10 fires — but Action Scheduler's data store isn't ready
		// at init@10 (init@1 is its earliest reliable point). Defer to
		// `action_scheduler_init` so we register after AS is ready; if AS is
		// unavailable (Free without bundled AS) fall back to init@20 so we
		// still beat WP-Cron's own scheduling pass.
		add_action( 'action_scheduler_init', array( __CLASS__, 'schedule_prune_cron' ) );
		add_action( 'init', array( __CLASS__, 'schedule_prune_cron' ), 20 );

		// Register the retention option so it persists via WP Settings API.
		add_action(
			'admin_init',
			static function (): void {
				register_setting(
					'wb_listora_settings_group',
					self::RETENTION_OPTION_KEY,
					array(
						'type'              => 'integer',
						'sanitize_callback' => static function ( $value ): int {
							$value = (int) $value;
							return array_key_exists( $value, self::retention_choices() ) ? $value : self::DEFAULT_RETENTION_DAYS;
						},
						'default'           => self::DEFAULT_RETENTION_DAYS,
					)
				);
			}
		);

		// Listing submitted. 4 args (post_id, status, request, context).
		// Context is empty for user-driven submissions and carries
		// `['source' => 'migration', ...]` for bulk-importer fires —
		// we gate on that in the handler to avoid emailing vendors for
		// every legacy listing imported from a competitor plugin.
		add_action( 'wb_listora_listing_submitted', array( $this, 'listing_submitted' ), 10, 4 );

		// Listing status changes — ride the canonical
		// `wb_listora_listing_status_changed` hook fired by Search_Indexer
		// from `transition_post_status`. The previous setup hooked invented
		// names (`wb_listora_listing_publish`, `wb_listora_listing_listora_rejected`,
		// `wb_listora_listing_listora_expired`) that nothing fired —
		// approval/rejection/expiration emails were silently broken.
		// See plan/2026-04-30-cross-ref-orphans.md (F1).
		add_action( 'wb_listora_listing_status_changed', array( $this, 'on_listing_status_changed' ), 10, 3 );

		// Expiration warnings.
		add_action( 'wb_listora_listing_expiring', array( $this, 'listing_expiring_soon' ), 10, 2 );

		// Listing renewed.
		add_action( 'wb_listora_listing_renewed', array( $this, 'listing_renewed' ), 10, 1 );

		// Listing pending admin review.
		add_action( 'wb_listora_listing_pending_admin', array( $this, 'listing_pending_admin' ), 10, 1 );

		// Reviews.
		add_action( 'wb_listora_review_submitted', array( $this, 'review_received' ), 10, 3 );
		add_action( 'wb_listora_review_reply', array( $this, 'review_reply' ), 10, 1 );

		// Review helpful milestone.
		add_action( 'wb_listora_review_helpful_milestone', array( $this, 'review_helpful_milestone' ), 10, 2 );

		// Claims.
		add_action( 'wb_listora_claim_submitted', array( $this, 'claim_submitted' ), 10, 3 );
		add_action( 'wb_listora_claim_approved', array( $this, 'claim_approved' ), 10, 3 );
		add_action( 'wb_listora_claim_rejected', array( $this, 'claim_rejected' ), 10, 2 );

		// Draft reminder.
		add_action( 'wb_listora_draft_reminder', array( $this, 'draft_reminder' ), 10, 1 );

		// Review reminder — nudge the owner to reply to reviews still awaiting
		// a response. Fired per listing by Expiration_Cron's bounded sweep.
		add_action( 'wb_listora_review_reminder', array( $this, 'review_reminder' ), 10, 2 );

		// Email verification — sent to guest submitter when verification is required.
		add_action( 'wb_listora_listing_verify_email', array( $this, 'listing_verify_email' ), 10, 2 );
	}

	/**
	 * Listing verify email — send the token-gated verification link to a guest.
	 *
	 * Bypasses the per-user notification preference because verification is a
	 * mandatory step in the publishing flow. Still respects the admin global
	 * toggle for the event so a site that runs an entirely different
	 * verification scheme can disable it.
	 *
	 * @param int    $post_id Listing ID.
	 * @param string $token   Plaintext verification token.
	 */
	public function listing_verify_email( $post_id, $token ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$author = get_user_by( 'id', $post->post_author );
		if ( ! $author ) {
			return;
		}

		// Verification email bypasses the per-user pref (it's a transactional
		// blocker, not marketing) but still honours the admin global toggle.
		$admin_notif = wb_listora_get_setting( 'notifications', array() );
		if ( ! is_array( $admin_notif ) ) {
			$admin_notif = array();
		}
		if ( array_key_exists( 'listing_verify_email', $admin_notif ) && ! $admin_notif['listing_verify_email'] ) {
			return;
		}

		$expiry_hours = (int) wb_listora_get_setting( 'verification_link_expiry_hours', 24 );

		$this->send(
			$author->user_email,
			'listing_verify_email',
			array(
				'listing_title' => $post->post_title,
				'author_name'   => $author->display_name,
				'verify_url'    => Email_Verification::get_verify_url( $post_id, $token ),
				'expiry_hours'  => max( 1, min( 168, $expiry_hours ) ),
			)
		);
	}

	// ─── Listing Events ───

	/**
	 * Listing submitted — notify admin.
	 *
	 * @param int                   $post_id Listing post ID.
	 * @param string                $status  Resulting post status.
	 * @param \WP_REST_Request|null $request Submission request (may be synthetic).
	 * @param array<string, mixed>  $context Optional context (1.1.0+). When
	 *                                      `'migration' === ($context['source'] ?? '')`,
	 *                                      this handler short-circuits so bulk-
	 *                                      importer fires don't email the admin
	 *                                      for every legacy listing imported
	 *                                      from a competitor plugin.
	 */
	public function listing_submitted( $post_id, $status, $request, $context = array() ) {
		// Skip per-listing admin email when the fire originated from a
		// bulk migrator. Vendors who opted into the daily digest still
		// receive a digest entry — that's the Notification_Digest feature,
		// not this immediate-fire path.
		if ( is_array( $context ) && isset( $context['source'] ) && 'migration' === $context['source'] ) {
			return;
		}

		$post  = get_post( $post_id );
		$admin = get_option( 'admin_email' );

		if ( ! $this->should_send( 'listing_submitted', 0, array( 'post_id' => $post_id ) ) ) {
			return;
		}

		$this->send(
			$admin,
			'listing_submitted',
			array(
				'listing_title' => $post->post_title,
				'listing_url'   => get_permalink( $post_id ),
				'author_name'   => get_the_author_meta( 'display_name', $post->post_author ),
				'status'        => $status,
				'admin_url'     => admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
			)
		);
	}

	/**
	 * Canonical listing-status-change dispatcher.
	 *
	 * Search_Indexer::on_status_change fires
	 * `wb_listora_listing_status_changed( $post_id, $new, $old )` once per
	 * actual transition (it short-circuits when `$new === $old`, so we never
	 * see no-op transitions). Branch by `$new` and forward to the per-event
	 * handler with its expected `( $post_id, $old_status )` signature.
	 *
	 * Note: `wb_listora_listing_expired` (1-arg, fired separately by the
	 * expiration cron) is NOT routed through here — Pro's outgoing-webhooks
	 * already listens to that hook directly. We only handle the
	 * status-transition side, where the email contract lives.
	 *
	 * @param int    $post_id    Listing post ID.
	 * @param string $new_status New post status.
	 * @param string $old        Previous post status.
	 */
	public function on_listing_status_changed( $post_id, $new_status, $old ) {
		switch ( $new_status ) {
			case 'publish':
				$this->listing_approved( $post_id, $old );
				break;
			case 'listora_rejected':
				$this->listing_rejected( $post_id, $old );
				break;
			case 'listora_expired':
				$this->listing_expired( $post_id, $old );
				break;
		}
	}

	/**
	 * Listing approved — notify author.
	 */
	public function listing_approved( $post_id, $old_status ) {
		if ( ! in_array( $old_status, array( 'pending', 'listora_rejected', 'listora_expired', 'draft' ), true ) ) {
			return;
		}

		$post   = get_post( $post_id );
		$author = get_user_by( 'id', $post->post_author );
		if ( ! $author ) {
			return;
		}

		if ( ! $this->should_send( 'listing_approved', $author->ID, array( 'post_id' => $post_id ) ) ) {
			return;
		}

		$this->send(
			$author->user_email,
			'listing_approved',
			array(
				'listing_title' => $post->post_title,
				'listing_url'   => get_permalink( $post_id ),
				'author_name'   => $author->display_name,
				'dashboard_url' => home_url( '/dashboard/' ),
			)
		);
	}

	/**
	 * Listing rejected — notify author.
	 */
	public function listing_rejected( $post_id, $old_status ) {
		$post   = get_post( $post_id );
		$author = get_user_by( 'id', $post->post_author );
		if ( ! $author ) {
			return;
		}

		if ( ! $this->should_send( 'listing_rejected', $author->ID, array( 'post_id' => $post_id ) ) ) {
			return;
		}

		$reason = get_post_meta( $post_id, '_listora_rejection_reason', true );

		$this->send(
			$author->user_email,
			'listing_rejected',
			array(
				'listing_title'    => $post->post_title,
				'author_name'      => $author->display_name,
				'rejection_reason' => $reason ?: __( 'No reason provided.', 'wb-listora' ),
				'edit_url'         => add_query_arg( 'edit', (int) $post_id, wb_listora_get_submit_url() ),
			)
		);
	}

	/**
	 * Listing expired — notify author.
	 */
	public function listing_expired( $post_id, $old_status ) {
		$post   = get_post( $post_id );
		$author = get_user_by( 'id', $post->post_author );
		if ( ! $author ) {
			return;
		}

		if ( ! $this->should_send( 'listing_expired', $author->ID, array( 'post_id' => $post_id ) ) ) {
			return;
		}

		$this->send(
			$author->user_email,
			'listing_expired',
			array(
				'listing_title' => $post->post_title,
				'author_name'   => $author->display_name,
				'renew_url'     => add_query_arg(
					array(
						'listora-tab' => 'listings',
						'renew'       => $post_id,
					),
					wb_listora_get_dashboard_url( 'listings' )
				) . '#listings',
				'listing_id'    => (int) $post_id,
			)
		);
	}

	/**
	 * Listing expiring soon — notify author.
	 *
	 * @param int $post_id Listing ID.
	 * @param int $days    Days until expiration.
	 */
	public function listing_expiring_soon( $post_id, $days ) {
		$post   = get_post( $post_id );
		$author = get_user_by( 'id', $post->post_author );
		if ( ! $author ) {
			return;
		}

		if ( ! $this->should_send(
			'listing_expiring_soon',
			$author->ID,
			array(
				'post_id' => $post_id,
				'days'    => $days,
			)
		) ) {
			return;
		}

		$expiry = get_post_meta( $post_id, '_listora_expiration_date', true );

		$this->send(
			$author->user_email,
			'listing_expiring_soon',
			array(
				'listing_title' => $post->post_title,
				'author_name'   => $author->display_name,
				'days'          => $days,
				'expiry_date'   => $expiry ? wp_date( get_option( 'date_format' ), strtotime( $expiry ) ) : '',
				'renew_url'     => add_query_arg(
					array(
						'listora-tab' => 'listings',
						'renew'       => $post_id,
					),
					wb_listora_get_dashboard_url( 'listings' )
				) . '#listings',
				'listing_id'    => (int) $post_id,
			)
		);
	}

	// ─── Review Events ───

	/**
	 * New review received — notify listing author.
	 */
	public function review_received( $review_id, $listing_id, $reviewer_id ) {
		$post = get_post( $listing_id );
		if ( ! $post ) {
			return;
		}

		$author   = get_user_by( 'id', $post->post_author );
		$reviewer = get_user_by( 'id', $reviewer_id );
		if ( ! $author || ! $reviewer ) {
			return;
		}

		if ( ! $this->should_send(
			'review_received',
			$author->ID,
			array(
				'review_id'  => $review_id,
				'listing_id' => $listing_id,
			)
		) ) {
			return;
		}

		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$review = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$prefix}reviews WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$review_id
			),
			ARRAY_A
		);

		if ( ! $review ) {
			return;
		}

		$this->send(
			$author->user_email,
			'review_received',
			array(
				'listing_title'  => $post->post_title,
				'listing_url'    => get_permalink( $listing_id ) . '#reviews',
				'author_name'    => $author->display_name,
				'reviewer_name'  => $reviewer->display_name,
				'review_rating'  => str_repeat( '★', (int) $review['overall_rating'] ),
				'review_title'   => $review['title'],
				'review_content' => wp_trim_words( $review['content'], 30 ),
			)
		);
	}

	/**
	 * Owner replied to review — notify reviewer.
	 *
	 * @param int $review_id Review ID.
	 */
	public function review_reply( $review_id ) {
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$review = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$prefix}reviews WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$review_id
			),
			ARRAY_A
		);

		if ( ! $review ) {
			return;
		}

		$reviewer = get_user_by( 'id', $review['user_id'] );
		$post     = get_post( $review['listing_id'] );
		if ( ! $reviewer || ! $post ) {
			return;
		}

		if ( ! $this->should_send( 'review_reply', $reviewer->ID, array( 'review_id' => $review_id ) ) ) {
			return;
		}

		$owner = get_user_by( 'id', $post->post_author );

		$this->send(
			$reviewer->user_email,
			'review_reply',
			array(
				'listing_title' => $post->post_title,
				'listing_url'   => get_permalink( $review['listing_id'] ) . '#review-' . $review_id,
				'reviewer_name' => $reviewer->display_name,
				'reply_text'    => $review['owner_reply'],
				'owner_name'    => $owner ? $owner->display_name : __( 'The listing owner', 'wb-listora' ),
				'owner_reply'   => $review['owner_reply'],
			)
		);
	}

	// ─── Claim Events ───

	/**
	 * Claim submitted — notify admin.
	 */
	public function claim_submitted( $claim_id, $listing_id, $user_id ) {
		$post  = get_post( $listing_id );
		$user  = get_user_by( 'id', $user_id );
		$admin = get_option( 'admin_email' );

		if ( ! $post || ! $user ) {
			return;
		}

		// Admin-targeted notification (no per-user gate beyond admin global toggle).
		if ( ! $this->should_send(
			'claim_submitted',
			0,
			array(
				'claim_id'   => $claim_id,
				'listing_id' => $listing_id,
			)
		) ) {
			return;
		}

		$this->send(
			$admin,
			'claim_submitted',
			array(
				'listing_title'  => $post->post_title,
				'listing_url'    => get_permalink( $listing_id ),
				'claimant_name'  => $user->display_name,
				'claimant_email' => $user->user_email,
				'admin_url'      => admin_url( 'admin.php?page=listora&tab=claims' ),
			)
		);
	}

	/**
	 * Claim approved — notify claimant.
	 */
	public function claim_approved( $claim_id, $listing_id, $user_id ) {
		$post = get_post( $listing_id );
		$user = get_user_by( 'id', $user_id );

		if ( ! $post || ! $user ) {
			return;
		}

		if ( ! $this->should_send(
			'claim_approved',
			$user->ID,
			array(
				'claim_id'   => $claim_id,
				'listing_id' => $listing_id,
			)
		) ) {
			return;
		}

		$this->send(
			$user->user_email,
			'claim_approved',
			array(
				'listing_title' => $post->post_title,
				'listing_url'   => get_permalink( $listing_id ),
				'edit_url'      => add_query_arg( 'edit', (int) $listing_id, wb_listora_get_submit_url() ),
				'author_name'   => $user->display_name,
				'dashboard_url' => wb_listora_get_dashboard_url( 'claims' ),
			)
		);
	}

	/**
	 * Claim rejected — notify claimant.
	 *
	 * @param int $claim_id   Claim ID.
	 * @param int $listing_id Listing ID.
	 */
	public function claim_rejected( $claim_id, $listing_id ) {
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$claim = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$prefix}claims WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$claim_id
			),
			ARRAY_A
		);

		if ( ! $claim ) {
			return;
		}

		$user = get_user_by( 'id', $claim['user_id'] );
		$post = get_post( $listing_id );
		if ( ! $user || ! $post ) {
			return;
		}

		if ( ! $this->should_send(
			'claim_rejected',
			$user->ID,
			array(
				'claim_id'   => $claim_id,
				'listing_id' => $listing_id,
			)
		) ) {
			return;
		}

		$this->send(
			$user->user_email,
			'claim_rejected',
			array(
				'listing_title' => $post->post_title,
				'listing_url'   => get_permalink( $listing_id ),
				'author_name'   => $user->display_name,
				'admin_notes'   => $claim['admin_notes'] ?: __( 'No additional details provided.', 'wb-listora' ),
				'dashboard_url' => wb_listora_get_dashboard_url( 'claims' ),
			)
		);
	}

	// ─── Listing Renewed ───

	/**
	 * Listing renewed — notify author.
	 *
	 * @param int $post_id Listing ID.
	 */
	public function listing_renewed( $post_id ) {
		$post   = get_post( $post_id );
		$author = get_user_by( 'id', $post->post_author );
		if ( ! $author ) {
			return;
		}

		if ( ! $this->should_send( 'listing_renewed', $author->ID, array( 'post_id' => $post_id ) ) ) {
			return;
		}

		$expiry = get_post_meta( $post_id, '_listora_expiration_date', true );

		$this->send(
			$author->user_email,
			'listing_renewed',
			array(
				'listing_title'   => $post->post_title,
				'listing_url'     => get_permalink( $post_id ),
				'author_name'     => $author->display_name,
				'new_expiry_date' => $expiry ? wp_date( get_option( 'date_format' ), strtotime( $expiry ) ) : '',
			)
		);
	}

	// ─── Review Helpful Milestone ───

	/**
	 * Review helpful milestone — notify review author at milestones.
	 *
	 * Only sends at milestones: 1, 5, 10, 25, 50, 100.
	 *
	 * @param int $review_id     Review ID.
	 * @param int $helpful_count Current helpful vote count.
	 */
	public function review_helpful_milestone( $review_id, $helpful_count ) {
		$milestones = array( 1, 5, 10, 25, 50, 100 );

		if ( ! in_array( $helpful_count, $milestones, true ) ) {
			return;
		}

		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$review = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$prefix}reviews WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$review_id
			),
			ARRAY_A
		);

		if ( ! $review ) {
			return;
		}

		$reviewer = get_user_by( 'id', $review['user_id'] );
		$post     = get_post( $review['listing_id'] );
		if ( ! $reviewer || ! $post ) {
			return;
		}

		if ( ! $this->should_send(
			'review_helpful',
			$reviewer->ID,
			array(
				'review_id'     => $review_id,
				'helpful_count' => $helpful_count,
			)
		) ) {
			return;
		}

		$this->send(
			$reviewer->user_email,
			'review_helpful',
			array(
				'listing_title' => $post->post_title,
				'listing_url'   => get_permalink( $review['listing_id'] ) . '#review-' . $review_id,
				'reviewer_name' => $reviewer->display_name,
				'helpful_count' => $helpful_count,
				'milestone'     => $helpful_count,
			)
		);
	}

	// ─── Draft Reminder ───

	/**
	 * Draft reminder — nudge email for abandoned draft listings.
	 *
	 * @param int $post_id Listing ID.
	 */
	public function draft_reminder( $post_id ) {
		$post   = get_post( $post_id );
		$author = get_user_by( 'id', $post->post_author );
		if ( ! $author ) {
			return;
		}

		if ( ! $this->should_send( 'draft_reminder', $author->ID, array( 'post_id' => $post_id ) ) ) {
			return;
		}

		$this->send(
			$author->user_email,
			'draft_reminder',
			array(
				'listing_title' => $post->post_title,
				'edit_url'      => add_query_arg( 'edit', (int) $post_id, wb_listora_get_submit_url() ),
				'user_name'     => $author->display_name,
			)
		);
	}

	// ─── Review Reminder ───

	/**
	 * Review reminder — nudge the listing owner to reply to approved reviews
	 * that are still awaiting a response.
	 *
	 * Rides the standard notification pipeline: honours the per-user
	 * `_listora_notify_review_reminder` opt-out (default-on) and the admin
	 * global toggle via {@see should_send()}, then routes through {@see send()}
	 * for the shared envelope + Email_Body_Formatter plain-text fallback.
	 *
	 * @param int $post_id       Listing post ID.
	 * @param int $pending_count Number of approved reviews awaiting a reply.
	 * @return void
	 */
	public function review_reminder( $post_id, $pending_count = 0 ): void {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$author = get_user_by( 'id', $post->post_author );
		if ( ! $author ) {
			return;
		}

		$pending_count = max( 1, (int) $pending_count );

		if ( ! $this->should_send(
			'review_reminder',
			$author->ID,
			array(
				'post_id'       => $post_id,
				'pending_count' => $pending_count,
			)
		) ) {
			return;
		}

		$this->send(
			$author->user_email,
			'review_reminder',
			array(
				'listing_title' => $post->post_title,
				'listing_url'   => get_permalink( $post_id ) . '#reviews',
				'author_name'   => $author->display_name,
				'pending_count' => $pending_count,
			)
		);
	}

	// ─── Listing Pending Admin ───

	/**
	 * Listing pending admin — notify admin of listing needing review.
	 *
	 * @param int $post_id Listing ID.
	 */
	public function listing_pending_admin( $post_id ) {
		$post  = get_post( $post_id );
		$admin = get_option( 'admin_email' );

		if ( ! $post ) {
			return;
		}

		// Admin-targeted notification.
		if ( ! $this->should_send( 'listing_pending_admin', 0, array( 'post_id' => $post_id ) ) ) {
			return;
		}

		$author = get_user_by( 'id', $post->post_author );

		// Determine listing type name.
		$listing_type = '';
		$type_terms   = wp_get_object_terms( $post_id, 'listora_listing_type', array( 'fields' => 'names' ) );
		if ( ! is_wp_error( $type_terms ) && ! empty( $type_terms ) ) {
			$listing_type = $type_terms[0];
		}

		$this->send(
			$admin,
			'listing_pending_admin',
			array(
				'listing_title'    => $post->post_title,
				'admin_review_url' => admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
				'author_name'      => $author ? $author->display_name : __( 'Unknown', 'wb-listora' ),
				'listing_type'     => $listing_type,
			)
		);
	}

	// ─── Gating Helpers ───

	/**
	 * Decide whether a notification should be sent based on admin global
	 * toggle + per-user preference. Fires `wb_listora_notification_skipped`
	 * with a reason when blocked so 3rd parties can audit.
	 *
	 * Test-mode sends (context['is_test'] === true) bypass admin/user gates
	 * so the "Send Test" button on Settings → Notifications always works.
	 *
	 * @param string              $event_key Event key (e.g. 'review_received').
	 * @param int                 $user_id   Recipient user ID, or 0 for admin-only events.
	 * @param array<string,mixed> $context   Optional context for the skipped hook.
	 * @return bool True if the notification should be sent.
	 */
	private function should_send( $event_key, $user_id = 0, array $context = array() ) {
		// Test-mode sends bypass gates entirely so admins can verify wiring.
		if ( ! empty( $context['is_test'] ) ) {
			return true;
		}

		// Admin global toggle. Default true (enabled) when no preference saved.
		$admin_notif = wb_listora_get_setting( 'notifications', array() );
		if ( ! is_array( $admin_notif ) ) {
			$admin_notif = array();
		}
		$admin_enabled = ! array_key_exists( $event_key, $admin_notif ) || (bool) $admin_notif[ $event_key ];

		if ( ! $admin_enabled ) {
			/**
			 * Fires when a notification is skipped.
			 *
			 * @param string $event_key Event key.
			 * @param string $reason    Skip reason: 'admin_disabled' or 'user_disabled'.
			 * @param array  $context   Caller-provided context.
			 */
			do_action( 'wb_listora_notification_skipped', $event_key, 'admin_disabled', $context );
			return false;
		}

		// Per-user toggle (only for user-targeted events).
		if ( $user_id > 0 ) {
			$user_pref = get_user_meta( $user_id, '_listora_notify_' . $event_key, true );
			// Default to enabled when never set; only '0' explicitly disables.
			if ( '0' === $user_pref ) {
				/** This hook is documented above. */
				do_action( 'wb_listora_notification_skipped', $event_key, 'user_disabled', $context );
				return false;
			}
		}

		return true;
	}

	// ─── Preference Helper (back-compat) ───

	/**
	 * Check whether a user wants to receive a specific notification.
	 *
	 * Reads from individual user meta keys (_listora_notify_{event}).
	 * Defaults to true (enabled) when no preference has been saved.
	 *
	 * Retained for backward compatibility — internal code now uses
	 * should_send() which also honors the admin global toggle.
	 *
	 * @param int    $user_id User ID.
	 * @param string $event   Notification event key.
	 * @return bool
	 */
	public static function user_wants_notification( $user_id, $event ) {
		$meta_value = get_user_meta( $user_id, '_listora_notify_' . $event, true );

		// No preference stored — default to enabled.
		if ( '' === $meta_value ) {
			return true;
		}

		return '1' === $meta_value;
	}

	// ─── Public API for test sends ───

	/**
	 * Public dispatcher used by the "Send Test" admin REST endpoint.
	 *
	 * Builds a synthetic context for the requested event and routes to send()
	 * directly. Bypasses admin/user gates so admins can verify wiring.
	 *
	 * @param string              $event_key Event key (one of the 14 supported events).
	 * @param string              $recipient Recipient email.
	 * @param array<string,mixed> $context   Optional override variables for the template.
	 * @return array{sent:bool,error?:string,subject?:string,recipient:string} Result info.
	 */
	public function send_test( $event_key, $recipient, array $context = array() ) {
		if ( ! is_email( $recipient ) ) {
			return array(
				'sent'      => false,
				'error'     => __( 'Invalid recipient email.', 'wb-listora' ),
				'recipient' => $recipient,
			);
		}

		$known_events = array(
			'listing_submitted',
			'listing_approved',
			'listing_rejected',
			'listing_expired',
			'listing_expiring_soon',
			'listing_renewed',
			'listing_pending_admin',
			'review_received',
			'review_reply',
			'review_helpful',
			'claim_submitted',
			'claim_approved',
			'claim_rejected',
			'draft_reminder',
			'review_reminder',
			'listing_verify_email',
		);

		if ( ! in_array( $event_key, $known_events, true ) ) {
			return array(
				'sent'      => false,
				'error'     => sprintf(
					/* translators: %s: event key */
					__( 'Unknown notification event: %s', 'wb-listora' ),
					$event_key
				),
				'recipient' => $recipient,
			);
		}

		$user      = wp_get_current_user();
		$site_name = get_bloginfo( 'name' );

		$vars = array_merge(
			array(
				'listing_title'    => __( '[Test] Sample Listing', 'wb-listora' ),
				'listing_url'      => home_url( '/' ),
				'author_name'      => $user && $user->ID ? $user->display_name : __( 'Sample User', 'wb-listora' ),
				'reviewer_name'    => __( 'Sample Reviewer', 'wb-listora' ),
				'claimant_name'    => __( 'Sample Claimant', 'wb-listora' ),
				'claimant_email'   => $recipient,
				'user_name'        => $user && $user->ID ? $user->display_name : __( 'Sample User', 'wb-listora' ),
				'admin_url'        => admin_url( 'admin.php?page=listora-settings' ),
				'admin_review_url' => admin_url( 'admin.php?page=listora-settings' ),
				'edit_url'         => admin_url( 'admin.php?page=listora-settings' ),
				'renew_url'        => home_url( '/dashboard/' ),
				'dashboard_url'    => home_url( '/dashboard/' ),
				'days'             => 7,
				'expiry_date'      => wp_date( get_option( 'date_format' ) ),
				'new_expiry_date'  => wp_date( get_option( 'date_format' ) ),
				'rejection_reason' => __( 'This is a test rejection reason.', 'wb-listora' ),
				'admin_notes'      => __( 'This is a test admin note.', 'wb-listora' ),
				'review_rating'    => str_repeat( '★', 5 ),
				'review_title'     => __( 'Sample Review Title', 'wb-listora' ),
				'review_content'   => __( 'This is the body of a sample review used to verify formatting.', 'wb-listora' ),
				'reply_text'       => __( 'Thanks for your review — this is a sample owner reply.', 'wb-listora' ),
				'owner_reply'      => __( 'Thanks for your review — this is a sample owner reply.', 'wb-listora' ),
				'owner_name'       => __( 'Sample Owner', 'wb-listora' ),
				'helpful_count'    => 5,
				'milestone'        => 5,
				'pending_count'    => 2,
				'listing_type'     => __( 'Business', 'wb-listora' ),
				'status'           => 'pending',
				'is_test'          => true,
			),
			$context
		);

		$this->send( $recipient, $event_key, $vars );

		// Inspect the most recent log entry to derive the result.
		$log    = self::get_log();
		$latest = ! empty( $log ) ? $log[0] : null;

		if ( $latest && $latest['event_key'] === $event_key && $latest['recipient'] === $recipient ) {
			return array(
				'sent'      => (bool) $latest['success'],
				'error'     => $latest['success'] ? '' : (string) $latest['error'],
				'subject'   => $latest['subject'],
				'recipient' => $recipient,
			);
		}

		return array(
			'sent'      => false,
			'error'     => __( 'Send was attempted but no log entry was recorded.', 'wb-listora' ),
			'recipient' => $recipient,
		);
	}

	// ─── Email Sender ───

	/**
	 * Send an email notification.
	 *
	 * @param string $to    Recipient email.
	 * @param string $event Event key (used for subject/template).
	 * @param array  $vars  Template variables.
	 */
	private function send( $to, $event, array $vars = array() ) {
		/**
		 * Filter whether to send this notification.
		 *
		 * @param bool   $send  Whether to send.
		 * @param string $event Event key.
		 * @param array  $vars  Template variables.
		 * @param string $to    Recipient email address.
		 */
		if ( ! apply_filters( 'wb_listora_send_notification', true, $event, $vars, $to ) ) {
			return;
		}

		$site_name    = get_bloginfo( 'name' );
		$is_marketing = in_array(
			$event,
			array( 'draft_reminder', 'listing_expiring_soon', 'review_helpful', 'review_reminder' ),
			true
		);

		// Build the unsubscribe link. Marketing/nudge emails get a stateless
		// one-click opt-out link (RFC 8058) scoped to THIS event so the
		// recipient need not log in — the signed token is the credential. We
		// map the recipient email back to a user to mint the per-user token;
		// when the recipient isn't a known user (or the event isn't
		// marketing), fall back to the dashboard preferences page.
		$dashboard_url   = function_exists( 'wb_listora_get_dashboard_url' )
			? wb_listora_get_dashboard_url( 'profile' )
			: home_url( '/' );
		$unsubscribe_url = $dashboard_url;
		if ( $is_marketing && class_exists( '\\WBListora\\REST\\Unsubscribe_Controller' ) ) {
			$recipient_email = is_array( $to ) ? ( $to[0] ?? '' ) : (string) $to;
			$recipient_user  = $recipient_email ? get_user_by( 'email', $recipient_email ) : false;
			if ( $recipient_user ) {
				$token_url = \WBListora\REST\Unsubscribe_Controller::build_url( $recipient_user->ID, $event );
				if ( '' !== $token_url ) {
					$unsubscribe_url = $token_url;
				}
			}
		}

		$vars = array_merge(
			$vars,
			array(
				'site_name'       => $site_name,
				'site_url'        => home_url( '/' ),
				'colors'          => self::get_palette(),
				'variant'         => $this->resolve_variant( $event, $vars ),
				'is_marketing'    => $is_marketing,
				'unsubscribe_url' => $unsubscribe_url,
				/**
				 * Filter the logo URL shown in email headers.
				 *
				 * Return a full URL (e.g. an uploaded PNG at ~160px wide) to render
				 * a logo above the header strip. Empty string (default) renders no logo.
				 *
				 * @param string $logo_url Logo URL. Default empty.
				 * @param string $event    Event key.
				 * @param array  $vars     Template variables.
				 */
				'logo_url'        => (string) apply_filters( 'wb_listora_email_logo_url', '', $event, $vars ),
				/**
				 * Filter the footer branding text.
				 *
				 * Return non-empty text to replace the default "This email was sent by {site_name}"
				 * line in the shared footer. Pass an empty string to keep the default.
				 *
				 * @param string $footer_text Footer text. Default empty.
				 * @param string $event       Event key.
				 * @param array  $vars        Template variables.
				 */
				'footer_text'     => (string) apply_filters( 'wb_listora_email_footer_text', '', $event, $vars ),
			)
		);

		$subject = $this->get_subject( $event, $vars );
		$body    = $this->get_body( $event, $vars );

		/**
		 * Filter email subject (global).
		 */
		$subject = apply_filters( 'wb_listora_email_subject', $subject, $event, $vars );

		/**
		 * Filter email subject (per-event). Receives the subject AFTER the
		 * global filter so per-event customization can override it cleanly.
		 *
		 * Example: add_filter( 'wb_listora_email_subject_listing_approved', ... );
		 *
		 * @param string $subject Subject line.
		 * @param array  $vars    Template variables.
		 */
		$subject = apply_filters( "wb_listora_email_subject_{$event}", $subject, $vars );

		/**
		 * Filter email content (global).
		 */
		$body = apply_filters( 'wb_listora_email_content', $body, $event, $vars );

		/**
		 * Filter email content (per-event). Runs after the global content filter.
		 *
		 * Example: add_filter( 'wb_listora_email_content_review_received', ... );
		 *
		 * @param string $body Rendered HTML body.
		 * @param array  $vars Template variables.
		 */
		$body = apply_filters( "wb_listora_email_content_{$event}", $body, $vars );

		/**
		 * Filter email recipients.
		 */
		$to = apply_filters( 'wb_listora_notification_recipients', $to, $event, $vars );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		/**
		 * Filter the "From:" name used on outbound notifications.
		 *
		 * @param string $from_name Default: site name.
		 * @param string $event     Event key.
		 * @param array  $vars      Template variables.
		 */
		$from_name = (string) apply_filters( 'wb_listora_email_from_name', $site_name, $event, $vars );
		/**
		 * Filter the "From:" address used on outbound notifications.
		 *
		 * @param string $from_address Default: admin_email option.
		 * @param string $event        Event key.
		 * @param array  $vars         Template variables.
		 */
		$from_address = (string) apply_filters( 'wb_listora_email_from_address', get_option( 'admin_email' ), $event, $vars );
		if ( $from_name && $from_address && is_email( $from_address ) ) {
			$headers[] = sprintf( 'From: %s <%s>', $from_name, $from_address );
		}

		/**
		 * Filter email headers.
		 */
		$headers = apply_filters( 'wb_listora_email_headers', $headers, $event, $vars );

		// Plain-text fallback — mail clients that prefer text/plain will use
		// this via wp_mail's alt body filter. PHPMailer's property name
		// ($AltBody) is camelCase by upstream design; the phpcs:ignore
		// comments below suppress the snake_case rule for that specific
		// line only.
		$text_body = $this->html_to_text( $body );
		add_action(
			'phpmailer_init',
			function ( $mailer ) use ( $text_body ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property name is fixed by upstream library.
				if ( $mailer && empty( $mailer->AltBody ) ) {
					// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property name is fixed by upstream library.
					$mailer->AltBody = $text_body;
				}
			}
		);

		// Capture wp_mail failure so we can log it. wp_mail returns bool but
		// also fires `wp_mail_failed` on PHPMailer exceptions.
		$mail_error = '';
		$capture    = static function ( $wp_error ) use ( &$mail_error ) {
			if ( is_wp_error( $wp_error ) ) {
				$mail_error = $wp_error->get_error_message();
			}
		};
		add_action( 'wp_mail_failed', $capture );

		$success = (bool) wp_mail( $to, $subject, $body, $headers );

		remove_action( 'wp_mail_failed', $capture );

		// Record to the rolling log so admins can audit recent activity.
		self::log_send(
			array(
				'event_key' => $event,
				'recipient' => (string) ( is_array( $to ) ? implode( ', ', $to ) : $to ),
				'subject'   => (string) $subject,
				'success'   => $success,
				'error'     => $success ? '' : ( $mail_error ?: __( 'wp_mail() returned false.', 'wb-listora' ) ),
			)
		);
	}

	/**
	 * Append an entry to the rolling email log option.
	 *
	 * Capped at LOG_MAX_ENTRIES (newest first). Filterable globally so a
	 * privacy-sensitive site can disable logging entirely:
	 *
	 *     add_filter( 'wb_listora_notification_log_enabled', '__return_false' );
	 *
	 * @param array{event_key:string,recipient:string,subject:string,success:bool,error:string} $entry Entry data.
	 */
	private static function log_send( array $entry ) {
		/**
		 * Filter whether to write to the rolling email log.
		 *
		 * @param bool $enabled Default true.
		 */
		if ( ! apply_filters( 'wb_listora_notification_log_enabled', true ) ) {
			return;
		}

		$entry = array_merge(
			array(
				'sent_at'   => current_time( 'mysql', true ),
				'event_key' => '',
				'recipient' => '',
				'subject'   => '',
				'success'   => false,
				'error'     => '',
			),
			$entry
		);

		$log = get_option( self::LOG_OPTION_KEY, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		// Newest first; drop tail when over the cap.
		array_unshift( $log, $entry );
		if ( count( $log ) > self::LOG_MAX_ENTRIES ) {
			$log = array_slice( $log, 0, self::LOG_MAX_ENTRIES );
		}

		update_option( self::LOG_OPTION_KEY, $log, false );
	}

	/**
	 * Read the entire rolling email log (newest first).
	 *
	 * Use {@see self::get_log_paginated()} when paging is required.
	 *
	 * @return array<int,array{sent_at:string,event_key:string,recipient:string,subject:string,success:bool,error:string}>
	 */
	public static function get_log(): array {
		$log = get_option( self::LOG_OPTION_KEY, array() );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * Paginated read of the rolling email log (newest first).
	 *
	 * @param array{page?:int,per_page?:int} $args Pagination args.
	 * @return array{entries:array<int,array{sent_at:string,event_key:string,recipient:string,subject:string,success:bool,error:string}>,total:int,page:int,per_page:int,pages:int}
	 */
	public static function get_log_paginated( array $args = array() ): array {
		$log = self::get_log();

		$per_page = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 25;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$total    = count( $log );
		$pages    = max( 1, (int) ceil( $total / $per_page ) );
		$page     = min( $page, $pages );

		return array(
			'entries'  => array_slice( $log, ( $page - 1 ) * $per_page, $per_page ),
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'pages'    => $pages,
		);
	}

	/**
	 * Drop entries older than the retention window. Called daily by cron;
	 * also called inline after each `log_send()` so the log self-trims
	 * between cron runs (defense in depth).
	 *
	 * Lifetime (0 days) → no-op.
	 *
	 * @return int Entries dropped.
	 */
	public static function prune_log(): int {
		$days = self::get_retention_days();
		if ( $days <= 0 ) {
			return 0;
		}

		$log = get_option( self::LOG_OPTION_KEY, array() );
		if ( ! is_array( $log ) || empty( $log ) ) {
			return 0;
		}

		$cutoff = time() - ( $days * DAY_IN_SECONDS );
		$kept   = array();
		foreach ( $log as $entry ) {
			$sent = isset( $entry['sent_at'] ) ? strtotime( (string) $entry['sent_at'] . ' UTC' ) : 0;
			if ( $sent && $sent >= $cutoff ) {
				$kept[] = $entry;
			}
		}

		$dropped = count( $log ) - count( $kept );
		if ( $dropped > 0 ) {
			update_option( self::LOG_OPTION_KEY, $kept, false );
		}
		return $dropped;
	}

	/**
	 * Schedule the daily retention-prune cron (idempotent).
	 *
	 * Hooked from `wb_listora.php` on `init`. Uses Action Scheduler when
	 * available (per Cron_Scheduler) and falls back to WP-Cron otherwise.
	 *
	 * @return void
	 */
	public static function schedule_prune_cron(): void {
		if ( class_exists( '\\WBListora\\Workflow\\Cron_Scheduler' ) ) {
			\WBListora\Workflow\Cron_Scheduler::schedule_recurring( 'daily', self::PRUNE_HOOK, 0, 'wb-listora' );
			return;
		}
		if ( ! wp_next_scheduled( self::PRUNE_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::PRUNE_HOOK );
		}
	}

	/**
	 * Clear the rolling email log.
	 */
	public static function clear_log() {
		delete_option( self::LOG_OPTION_KEY );
	}

	/**
	 * Central color palette for emails. Keeps all inline styles consistent
	 * and editable from one place.
	 *
	 * @return array<string,string>
	 */
	public static function get_palette(): array {
		return apply_filters(
			'wb_listora_email_palette',
			array(
				'primary'     => '#2271b1',
				'success'     => '#00a32a',
				'danger'      => '#d63638',
				'warning'     => '#dba617',
				'text'        => '#1e1e1e',
				'text_muted'  => '#3c434a',
				'text_subtle' => '#a7aaad',
				'bg'          => '#ffffff',
				'bg_alt'      => '#f0f0f1',
				'border'      => '#e0e0e0',
				'white'       => '#ffffff',
			)
		);
	}

	/**
	 * Resolve a template "variant" (success / warning / danger) for events
	 * that change appearance based on context. Keeps conditional styling
	 * out of the template files.
	 *
	 * @param string              $event Event key.
	 * @param array<string,mixed> $vars  Template variables.
	 * @return string One of: success | warning | danger | neutral.
	 */
	private function resolve_variant( string $event, array $vars ): string {
		switch ( $event ) {
			case 'listing_expiring_soon':
				return ( (int) ( $vars['days'] ?? 7 ) <= 1 ) ? 'danger' : 'warning';
			case 'listing_rejected':
			case 'claim_rejected':
				return 'danger';
			case 'listing_approved':
			case 'claim_approved':
			case 'review_helpful':
			case 'listing_renewed':
				return 'success';
			case 'listing_expired':
			case 'draft_reminder':
				return 'warning';
			default:
				return 'neutral';
		}
	}

	/**
	 * Strip HTML to plain text for the text/plain mail alternative.
	 *
	 * @deprecated 1.1.0 Use {@see \WBListora\Workflow\Email_Body_Formatter::html_to_text()}.
	 *             This shim survives one release cycle (deletion in 1.2.0
	 *             per the production-rules deprecation contract).
	 *
	 * @param string $html Rendered HTML body.
	 * @return string
	 */
	private function html_to_text( string $html ): string {
		return Email_Body_Formatter::html_to_text( $html );
	}

	/**
	 * Get email subject for an event.
	 *
	 * @param string $event Event key.
	 * @param array  $vars  Template variables.
	 * @return string
	 */
	private function get_subject( $event, $vars ) {
		$title = $vars['listing_title'] ?? '';

		$subjects = array(
			/* translators: %s: listing title */
			'listing_submitted'     => sprintf( __( 'New listing submitted: %s', 'wb-listora' ), $title ),
			/* translators: %s: listing title */
			'listing_approved'      => sprintf( __( 'Your listing has been approved: %s', 'wb-listora' ), $title ),
			/* translators: %s: listing title */
			'listing_rejected'      => sprintf( __( 'Your listing needs changes: %s', 'wb-listora' ), $title ),
			/* translators: %s: listing title */
			'listing_expired'       => sprintf( __( 'Your listing has expired: %s', 'wb-listora' ), $title ),
			'listing_expiring_soon' => sprintf(
				/* translators: 1: listing title, 2: number of days until expiration */
				__( 'Your listing expires in %2$d days: %1$s', 'wb-listora' ),
				$title,
				$vars['days'] ?? 7
			),
			/* translators: %s: listing title */
			'listing_renewed'       => sprintf( __( 'Your listing has been renewed: %s', 'wb-listora' ), $title ),
			/* translators: %s: listing title */
			'listing_pending_admin' => sprintf( __( 'New listing needs review: %s', 'wb-listora' ), $title ),
			/* translators: %s: listing title */
			'review_received'       => sprintf( __( 'New review on %s', 'wb-listora' ), $title ),
			/* translators: %s: listing title */
			'review_reply'          => sprintf( __( 'Owner replied to your review on %s', 'wb-listora' ), $title ),
			'review_helpful'        => sprintf(
				/* translators: 1: listing title, 2: milestone number */
				__( 'Your review of %1$s reached %2$s helpful votes!', 'wb-listora' ),
				$title,
				number_format_i18n( $vars['milestone'] ?? 0 )
			),
			/* translators: %s: listing title */
			'claim_submitted'       => sprintf( __( 'New claim request for: %s', 'wb-listora' ), $title ),
			/* translators: %s: listing title */
			'claim_approved'        => sprintf( __( 'Your claim has been approved: %s', 'wb-listora' ), $title ),
			/* translators: %s: listing title */
			'claim_rejected'        => sprintf( __( 'Your claim was not approved: %s', 'wb-listora' ), $title ),
			/* translators: %s: listing title */
			'draft_reminder'        => sprintf( __( 'Finish your listing: %s', 'wb-listora' ), $title ),
			/* translators: %s: listing title */
			'review_reminder'       => sprintf( __( 'You have reviews waiting for a reply on %s', 'wb-listora' ), $title ),
			/* translators: %s: site name */
			'listing_verify_email'  => sprintf( __( 'Verify your email to publish your listing on %s', 'wb-listora' ), $vars['site_name'] ?? '' ),
		);

		$subject = $subjects[ $event ] ?? sprintf(
			/* translators: %s: site name */
			__( 'Notification from %s', 'wb-listora' ),
			$vars['site_name']
		);

		// Test sends — clearly mark them in the subject so test mail in real
		// inboxes never gets confused with a real notification.
		if ( ! empty( $vars['is_test'] ) ) {
			$subject = '[TEST] ' . $subject;
		}

		return $subject;
	}

	/**
	 * Get email body HTML for an event.
	 *
	 * Events with dedicated templates use render_template().
	 * Remaining events fall back to wrap_email_html().
	 *
	 * @param string $event Event key.
	 * @param array  $v     Template variables.
	 * @return string
	 */
	private function get_body( $event, $v ) {
		// All events with dedicated template files.
		$templated_events = array(
			'listing_submitted',
			'listing_approved',
			'listing_rejected',
			'listing_expired',
			'listing_expiring_soon',
			'listing_renewed',
			'listing_pending_admin',
			'review_received',
			'review_reply',
			'review_helpful',
			'claim_submitted',
			'claim_approved',
			'claim_rejected',
			'draft_reminder',
			'review_reminder',
			'listing_verify_email',
		);

		if ( in_array( $event, $templated_events, true ) ) {
			return $this->render_template( $event, $v );
		}

		// Fallback for any events without dedicated template files.
		$name = $v['author_name'] ?? $v['reviewer_name'] ?? $v['claimant_name'] ?? $v['user_name'] ?? '';

		/* translators: %s: user name */
		$greeting = sprintf( __( 'Hi %s,', 'wb-listora' ), esc_html( $name ) );

		return $this->wrap_email_html( $greeting, '', '', '', $v['site_name'], $v['site_url'] );
	}

	/**
	 * Render an email template file using output buffering.
	 *
	 * Template files live in templates/emails/{event-slug}.php.
	 * Themes can override by placing templates in {theme}/wb-listora/emails/.
	 * All $vars are extracted into template scope as individual variables.
	 *
	 * @param string $event Event key — maps to a template filename.
	 * @param array  $vars  Template variables to expose.
	 * @return string Rendered HTML, or empty string if template not found.
	 */
	private function render_template( $event, array $vars ) {
		// Convert event key to filename: listing_submitted -> listing-submitted.php.
		$filename      = 'emails/' . str_replace( '_', '-', $event ) . '.php';
		$template_path = wb_listora_locate_template( $filename );

		if ( ! $template_path || ! file_exists( $template_path ) ) {
			return '';
		}

		return wb_listora_get_template_html( $filename, $vars );
	}

	/**
	 * Wrap email content in a basic HTML template.
	 *
	 * Used as a fallback for events without dedicated template files.
	 *
	 * @param string $greeting  Opening greeting line.
	 * @param string $message   Main message HTML.
	 * @param string $cta_url   Call-to-action URL (optional).
	 * @param string $cta_text  Call-to-action button text (optional).
	 * @param string $site_name Site name.
	 * @param string $site_url  Site home URL.
	 * @return string
	 */
	private function wrap_email_html( $greeting, $message, $cta_url, $cta_text, $site_name, $site_url ) {
		$cta_html = '';
		if ( $cta_url && $cta_text ) {
			$cta_html = sprintf(
				'<p style="margin-top:1.5rem;"><a href="%s" style="display:inline-block;padding:12px 24px;background:#2271b1;color:#fff;text-decoration:none;border-radius:4px;font-weight:600;">%s</a></p>',
				esc_url( $cta_url ),
				esc_html( $cta_text )
			);
		}

		return sprintf(
			'<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
			<body style="margin:0;padding:0;background:#f0f0f1;">
			<table width="100%%" cellpadding="0" cellspacing="0" style="background:#f0f0f1;padding:2rem 1rem;"><tr><td align="center">
			<table width="100%%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:8px;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;color:#1e1e1e;">
				<tr><td style="padding:1.5rem 2rem;background:#1e1e1e;text-align:center;">
					<p style="margin:0;font-size:1.1rem;font-weight:600;color:#ffffff;">%1$s</p>
				</td></tr>
				<tr><td style="padding:2rem;">
					<p style="margin:0 0 1rem;font-size:1rem;">%2$s</p>
					<p style="margin:0 0 1rem;font-size:0.95rem;color:#3c434a;line-height:1.6;">%3$s</p>
					%4$s
				</td></tr>
				<tr><td style="padding:1rem 2rem;border-top:1px solid #e0e0e0;text-align:center;">
					<p style="margin:0;font-size:0.8rem;color:#a7aaad;">%5$s</p>
				</td></tr>
			</table>
			</td></tr></table>
			</body></html>',
			esc_html( $site_name ),
			$greeting,
			$message,
			$cta_html,
			sprintf(
				/* translators: 1: site name, 2: site URL */
				__( 'This email was sent by %1$s.', 'wb-listora' ),
				'<a href="' . esc_url( $site_url ) . '" style="color:#a7aaad;">' . esc_html( $site_name ) . '</a>'
			)
		);
	}
}
