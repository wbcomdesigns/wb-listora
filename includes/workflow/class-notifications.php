<?php
/**
 * Notifications — email notifications for listing lifecycle events.
 *
 * @package WBListora\Workflow
 */

namespace WBListora\Workflow;

defined( 'ABSPATH' ) || exit;

/**
 * Handles 14 email notification events.
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

		// Reviews.
		add_action( 'wb_listora_review_submitted', array( $this, 'review_received' ), 10, 3 );
		add_action( 'wb_listora_review_reply', array( $this, 'review_reply' ), 10, 1 );

		// Claims.
		add_action( 'wb_listora_claim_submitted', array( $this, 'claim_submitted' ), 10, 3 );
		add_action( 'wb_listora_claim_approved', array( $this, 'claim_approved' ), 10, 3 );
		add_action( 'wb_listora_claim_rejected', array( $this, 'claim_rejected' ), 10, 2 );
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
				'edit_url'         => home_url( '/add-listing/?edit=' . $post_id ),
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
	 */
	public function listing_expiring( $post_id, $days ) {
		$post   = get_post( $post_id );
		$author = get_user_by( 'id', $post->post_author );
		if ( ! $author ) {
			return;
		}

		// Check user notification preferences.
		$prefs = get_user_meta( $author->ID, '_listora_notification_prefs', true ) ?: array();
		if ( isset( $prefs['listing_expiration'] ) && ! $prefs['listing_expiration'] ) {
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

		// Check user notification preferences.
		$prefs = get_user_meta( $author->ID, '_listora_notification_prefs', true ) ?: array();
		if ( isset( $prefs['review_received'] ) && ! $prefs['review_received'] ) {
			return;
		}

		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$review = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$prefix}reviews WHERE id = %d",
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
	 */
	public function review_reply( $review_id ) {
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$review = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$prefix}reviews WHERE id = %d",
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

		$this->send(
			$reviewer->user_email,
			'review_reply',
			array(
				'listing_title' => $post->post_title,
				'listing_url'   => get_permalink( $review['listing_id'] ) . '#review-' . $review_id,
				'reviewer_name' => $reviewer->display_name,
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
				'author_name'   => $user->display_name,
				'dashboard_url' => home_url( '/dashboard/' ),
			)
		);
	}

	/**
	 * Claim rejected — notify claimant.
	 */
	public function claim_rejected( $claim_id, $listing_id ) {
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$claim  = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$prefix}claims WHERE id = %d",
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
				'author_name'   => $user->display_name,
				'admin_notes'   => $claim['admin_notes'] ?: __( 'No additional details provided.', 'wb-listora' ),
			)
		);
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

		wp_mail( $to, $subject, $body, $headers );
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
			'listing_submitted' => sprintf( __( 'New listing submitted: %s', 'wb-listora' ), $title ),
			'listing_approved'  => sprintf( __( 'Your listing has been approved: %s', 'wb-listora' ), $title ),
			'listing_rejected'  => sprintf( __( 'Your listing needs changes: %s', 'wb-listora' ), $title ),
			'listing_expired'   => sprintf( __( 'Your listing has expired: %s', 'wb-listora' ), $title ),
			'listing_expiring'  => sprintf(
				/* translators: 1: listing title, 2: days */
				__( 'Your listing expires in %2$d days: %1$s', 'wb-listora' ),
				$title,
				$vars['days'] ?? 7
			),
			'review_received'   => sprintf( __( 'New review on %s', 'wb-listora' ), $title ),
			'review_reply'      => sprintf( __( 'Owner replied to your review on %s', 'wb-listora' ), $title ),
			'claim_submitted'   => sprintf( __( 'New claim request for: %s', 'wb-listora' ), $title ),
			'claim_approved'    => sprintf( __( 'Your claim has been approved: %s', 'wb-listora' ), $title ),
			'claim_rejected'    => sprintf( __( 'Your claim was not approved: %s', 'wb-listora' ), $title ),
		);

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
		// Events with dedicated template files.
		$templated_events = array(
			'listing_submitted',
			'listing_approved',
			'listing_rejected',
			'listing_expired',
			'review_received',
			'claim_submitted',
			'claim_approved',
		);

		if ( in_array( $event, $templated_events, true ) ) {
			return $this->render_template( $event, $v );
		}

		// Remaining events: listing_expiring, review_reply, claim_rejected.
		$name = $v['author_name'] ?? $v['reviewer_name'] ?? $v['claimant_name'] ?? '';

		$greeting = sprintf( __( 'Hi %s,', 'wb-listora' ), esc_html( $name ) );
		$message  = '';
		$cta_url  = '';
		$cta_text = '';

		switch ( $event ) {
			case 'listing_expiring':
				$message  = sprintf(
					__( 'Your listing "%1$s" will expire on %2$s (%3$d days from now).', 'wb-listora' ),
					esc_html( $v['listing_title'] ),
					esc_html( $v['expiry_date'] ?? '' ),
					(int) $v['days']
				);
				$cta_url  = $v['renew_url'] ?? '';
				$cta_text = __( 'Manage Listing', 'wb-listora' );
				break;

			case 'review_reply':
				$message  = sprintf( __( 'The owner of "%s" replied to your review:', 'wb-listora' ), esc_html( $v['listing_title'] ) );
				$message .= '<br/><br/><blockquote style="border-left:3px solid #0073aa;padding-left:1rem;color:#555;">' . esc_html( $v['owner_reply'] ) . '</blockquote>';
				$cta_url  = $v['listing_url'] ?? '';
				$cta_text = __( 'View Reply', 'wb-listora' );
				break;

			case 'claim_rejected':
				$message = sprintf( __( 'Unfortunately, your claim for "%s" was not approved.', 'wb-listora' ), esc_html( $v['listing_title'] ) );
				if ( ! empty( $v['admin_notes'] ) ) {
					$message .= '<br/><br/><strong>' . __( 'Notes:', 'wb-listora' ) . '</strong> ' . esc_html( $v['admin_notes'] );
				}
				break;
		}

		return $this->wrap_email_html( $greeting, $message, $cta_url, $cta_text, $v['site_name'], $v['site_url'] );
	}

	/**
	 * Render an email template file using output buffering.
	 *
	 * Template files live in templates/emails/{event-slug}.php.
	 * All $vars are extracted into template scope as individual variables.
	 *
	 * @param string $event Event key — maps to a template filename.
	 * @param array  $vars  Template variables to expose.
	 * @return string Rendered HTML, or empty string if template not found.
	 */
	private function render_template( $event, array $vars ) {
		// Convert event key to filename: listing_submitted -> listing-submitted.php.
		$filename      = str_replace( '_', '-', $event ) . '.php';
		$template_path = WB_LISTORA_PLUGIN_DIR . 'templates/emails/' . $filename;

		if ( ! file_exists( $template_path ) ) {
			return '';
		}

		// Extract vars so templates can use $site_name, $listing_title, etc. directly.
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- intentional for template scope
		extract( $vars, EXTR_SKIP );

		ob_start();
		include $template_path;
		return ob_get_clean();
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
