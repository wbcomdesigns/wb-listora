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
	 * Constructor — hook into all notification events.
	 */
	public function __construct() {
		// Listing submitted.
		add_action( 'wb_listora_listing_submitted', array( $this, 'listing_submitted' ), 10, 3 );

		// Listing status changes.
		add_action( 'wb_listora_listing_publish', array( $this, 'listing_approved' ), 10, 2 );
		add_action( 'wb_listora_listing_listora_rejected', array( $this, 'listing_rejected' ), 10, 2 );
		add_action( 'wb_listora_listing_listora_expired', array( $this, 'listing_expired' ), 10, 2 );

		// Expiration warnings.
		add_action( 'wb_listora_listing_expiring', array( $this, 'listing_expiring' ), 10, 2 );

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
	}

	// ─── Listing Events ───

	/**
	 * Listing submitted — notify admin.
	 */
	public function listing_submitted( $post_id, $status, $request ) {
		$post  = get_post( $post_id );
		$admin = get_option( 'admin_email' );

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

		$this->send(
			$author->user_email,
			'listing_expired',
			array(
				'listing_title' => $post->post_title,
				'author_name'   => $author->display_name,
				'renew_url'     => home_url( '/dashboard/#listings' ),
			)
		);
	}

	/**
	 * Listing expiring soon — notify author.
	 *
	 * @param int $post_id Listing ID.
	 * @param int $days    Days until expiration.
	 */
	public function listing_expiring( $post_id, $days ) {
		$post   = get_post( $post_id );
		$author = get_user_by( 'id', $post->post_author );
		if ( ! $author ) {
			return;
		}

		// Check user notification preference (individual meta key).
		if ( ! self::user_wants_notification( $author->ID, 'listing_expiring_soon' ) ) {
			return;
		}

		$expiry = get_post_meta( $post_id, '_listora_expiration_date', true );

		$this->send(
			$author->user_email,
			'listing_expiring',
			array(
				'listing_title' => $post->post_title,
				'author_name'   => $author->display_name,
				'days'          => $days,
				'expiry_date'   => $expiry ? wp_date( get_option( 'date_format' ), strtotime( $expiry ) ) : '',
				'renew_url'     => home_url( '/dashboard/#listings' ),
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

		// Check user notification preference (individual meta key).
		if ( ! self::user_wants_notification( $author->ID, 'review_received' ) ) {
			return;
		}

		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
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

		// Check user notification preference.
		if ( ! self::user_wants_notification( $reviewer->ID, 'review_helpful' ) ) {
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

	// ─── Preference Helper ───

	/**
	 * Check whether a user wants to receive a specific notification.
	 *
	 * Reads from individual user meta keys (_listora_notify_{event}).
	 * Defaults to true (enabled) when no preference has been saved.
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

	// ─── Email Sender ───

	/**
	 * Send an email notification.
	 *
	 * @param string $to        Recipient email.
	 * @param string $event     Event key (used for subject/template).
	 * @param array  $vars      Template variables.
	 */
	private function send( $to, $event, array $vars = array() ) {
		/**
		 * Filter whether to send this notification.
		 *
		 * @param bool   $send  Whether to send.
		 * @param string $event Event key.
		 * @param array  $vars  Template variables.
		 */
		if ( ! apply_filters( 'wb_listora_send_notification', true, $event, $vars ) ) {
			return;
		}

		$site_name = get_bloginfo( 'name' );
		$vars      = array_merge(
			$vars,
			array(
				'site_name' => $site_name,
				'site_url'  => home_url( '/' ),
				'colors'    => self::get_palette(),
				'variant'   => $this->resolve_variant( $event, $vars ),
				'is_marketing' => in_array(
					$event,
					array( 'draft_reminder', 'listing_expiring', 'review_helpful' ),
					true
				),
				'unsubscribe_url' => function_exists( 'wb_listora_get_dashboard_url' )
					? wb_listora_get_dashboard_url( 'profile' )
					: home_url( '/' ),
			)
		);

		$subject = $this->get_subject( $event, $vars );
		$body    = $this->get_body( $event, $vars );

		/**
		 * Filter email subject.
		 */
		$subject = apply_filters( 'wb_listora_email_subject', $subject, $event, $vars );

		/**
		 * Filter email content.
		 */
		$body = apply_filters( 'wb_listora_email_content', $body, $event, $vars );

		/**
		 * Filter email recipients.
		 */
		$to = apply_filters( 'wb_listora_notification_recipients', $to, $event, $vars );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

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

		wp_mail( $to, $subject, $body, $headers );
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
				'primary'      => '#2271b1',
				'success'      => '#00a32a',
				'danger'       => '#d63638',
				'warning'      => '#dba617',
				'text'         => '#1e1e1e',
				'text_muted'   => '#3c434a',
				'text_subtle'  => '#a7aaad',
				'bg'           => '#ffffff',
				'bg_alt'       => '#f0f0f1',
				'border'       => '#e0e0e0',
				'white'        => '#ffffff',
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
			case 'listing_expiring':
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
	 * Strip HTML to plain text for the text/plain mail alternative. Keeps
	 * link URLs visible so screen-reader / Gmail text-only clients still
	 * get the CTA.
	 *
	 * @param string $html Rendered HTML body.
	 * @return string
	 */
	private function html_to_text( string $html ): string {
		$with_links = preg_replace( '#<a[^>]*href=[\'"]([^\'"]+)[\'"][^>]*>(.*?)</a>#is', '$2 <$1>', $html );
		$text       = wp_strip_all_tags( (string) $with_links );
		$text       = preg_replace( "/[ \t]+/", ' ', $text );
		$text       = preg_replace( "/\n{3,}/", "\n\n", (string) $text );
		return trim( (string) $text );
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
			'listing_expiring'      => sprintf(
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
		);

		/* translators: %s: site name */
		return $subjects[ $event ] ?? sprintf( __( 'Notification from %s', 'wb-listora' ), $vars['site_name'] );
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
			'listing_expiring',
			'listing_renewed',
			'listing_pending_admin',
			'review_received',
			'review_reply',
			'review_helpful',
			'claim_submitted',
			'claim_approved',
			'claim_rejected',
			'draft_reminder',
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
