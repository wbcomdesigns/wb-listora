<?php
/**
 * Suite notification bridge.
 *
 * Fires a single canonical action — `wb_listora_notification_created` — whenever
 * Listora produces a user-facing notification, carrying everything an external
 * aggregator (e.g. BuddyNext's notification center) needs to render it
 * standalone: recipient, a ready-to-display message, and a deep link.
 *
 * Listora's own dashboard "notifications" (see Dashboard_Controller::
 * get_notifications()) are DERIVED at read time from listing status, reviews,
 * and claims — there is no stored notification row and therefore no single
 * "write" point to hook. So this bridge listens to the real lifecycle events
 * that the dashboard derives from, and fires the action once per event with the
 * same message the dashboard would show. It runs ALONGSIDE the dashboard system;
 * it does not replace it. Card 9994191304.
 *
 * @package WBListora\Workflow
 */

namespace WBListora\Workflow;

use WBListora\Core\Claims_Model;

defined( 'ABSPATH' ) || exit;

/**
 * Fans Listora lifecycle events into the wb_listora_notification_created action.
 */
class Suite_Notifications {

	/**
	 * Wire the lifecycle listeners.
	 */
	public function __construct() {
		add_action( 'wb_listora_listing_status_changed', array( $this, 'on_listing_status_changed' ), 20, 3 );
		add_action( 'wb_listora_review_submitted', array( $this, 'on_review_submitted' ), 20, 3 );
		add_action( 'wb_listora_after_submit_claim', array( $this, 'on_claim_submitted' ), 20, 2 );
		add_action( 'wb_listora_after_update_claim', array( $this, 'on_claim_updated' ), 20, 2 );
	}

	/**
	 * Listing approved / rejected / expired — notify the listing owner.
	 *
	 * Mirrors the status branching in Workflow\Notifications::on_listing_status_changed
	 * and the messages in Dashboard_Controller::get_notifications().
	 *
	 * @param int    $post_id Listing post ID.
	 * @param string $new     New post status.
	 * @param string $old     Previous post status.
	 */
	public function on_listing_status_changed( $post_id, $new, $old ): void {
		$post = get_post( (int) $post_id );
		if ( ! $post || 'listora_listing' !== $post->post_type ) {
			return;
		}

		$recipient = (int) $post->post_author;
		$title     = $post->post_title;

		switch ( $new ) {
			case 'publish':
				// Only a genuine approval transition, not a re-save of an
				// already-published listing.
				if ( ! in_array( $old, array( 'pending', 'listora_rejected', 'listora_expired', 'draft' ), true ) ) {
					return;
				}
				/* translators: %s: listing title */
				$message = sprintf( __( 'Your listing "%s" was approved', 'wb-listora' ), $title );
				$type    = 'listing_approved';
				break;

			case 'listora_rejected':
				/* translators: %s: listing title */
				$message = sprintf( __( 'Your listing "%s" was rejected', 'wb-listora' ), $title );
				$type    = 'listing_rejected';
				break;

			case 'listora_expired':
				/* translators: %s: listing title */
				$message = sprintf( __( 'Your listing "%s" has expired', 'wb-listora' ), $title );
				$type    = 'listing_expired';
				break;

			default:
				return;
		}

		$this->fire( $recipient, $type, $message, $this->listing_link( $post_id ), 0, (int) $post_id );
	}

	/**
	 * New review on a listing — notify the listing owner.
	 *
	 * @param int $review_id  Review ID.
	 * @param int $listing_id Listing the review was posted on.
	 * @param int $reviewer   User ID of the reviewer.
	 */
	public function on_review_submitted( $review_id, $listing_id, $reviewer ): void {
		$owner = (int) get_post_field( 'post_author', (int) $listing_id );
		if ( $owner <= 0 || $owner === (int) $reviewer ) {
			// No owner, or the owner reviewed their own listing — nothing to notify.
			return;
		}

		/* translators: %s: listing title */
		$message = sprintf( __( 'New review on "%s"', 'wb-listora' ), get_the_title( (int) $listing_id ) );

		$this->fire( $owner, 'review_received', $message, $this->listing_link( $listing_id ), (int) $reviewer, (int) $review_id );
	}

	/**
	 * Claim submitted — notify the claimant that it is pending review.
	 *
	 * @param int $claim_id   Claim ID.
	 * @param int $listing_id Listing the claim targets.
	 */
	public function on_claim_submitted( $claim_id, $listing_id ): void {
		$claim = Claims_Model::get( (int) $claim_id );
		if ( ! $claim ) {
			return;
		}

		/* translators: %s: listing title */
		$message = sprintf( __( 'Your claim for "%s" is pending review', 'wb-listora' ), get_the_title( (int) $listing_id ) );

		$this->fire( (int) $claim['user_id'], 'claim_submitted', $message, $this->listing_link( $listing_id ), 0, (int) $claim_id );
	}

	/**
	 * Claim approved / rejected — notify the claimant.
	 *
	 * @param int    $claim_id   Claim ID.
	 * @param string $new_status New claim status (approved|rejected).
	 */
	public function on_claim_updated( $claim_id, $new_status ): void {
		$claim = Claims_Model::get( (int) $claim_id );
		if ( ! $claim ) {
			return;
		}

		$listing_id = (int) $claim['listing_id'];
		$title      = get_the_title( $listing_id );

		switch ( $new_status ) {
			case 'approved':
				/* translators: %s: listing title */
				$message = sprintf( __( 'Your claim for "%s" was approved', 'wb-listora' ), $title );
				$type    = 'claim_approved';
				break;

			case 'rejected':
				/* translators: %s: listing title */
				$message = sprintf( __( 'Your claim for "%s" was rejected', 'wb-listora' ), $title );
				$type    = 'claim_rejected';
				break;

			default:
				return;
		}

		$this->fire( (int) $claim['user_id'], $type, $message, $this->listing_link( $listing_id ), 0, (int) $claim_id );
	}

	/**
	 * Absolute URL to the listing, falling back to the site home.
	 *
	 * @param int $listing_id Listing post ID.
	 * @return string
	 */
	private function listing_link( $listing_id ): string {
		$link = get_permalink( (int) $listing_id );

		return $link ? $link : home_url( '/' );
	}

	/**
	 * Fire the canonical notification action.
	 *
	 * @param int    $recipient_id WP user ID of the notified member.
	 * @param string $type         Notification type (listing_approved, review_received, claim_approved, …).
	 * @param string $message      Ready-to-display sentence.
	 * @param string $link         Absolute URL to the relevant Listora page.
	 * @param int    $actor_id     User who triggered the event (0 when system/admin).
	 * @param int    $object_id    Listing / review / claim ID the notification is about.
	 */
	private function fire( $recipient_id, $type, $message, $link, $actor_id, $object_id ): void {
		$recipient_id = (int) $recipient_id;
		if ( $recipient_id <= 0 ) {
			return;
		}

		/**
		 * Fires whenever Listora produces a user-facing notification.
		 *
		 * Carries everything an external notification aggregator needs to render
		 * the notification standalone, so it never has to re-derive Listora's
		 * display logic. Fired alongside Listora's own dashboard notification
		 * system, not in place of it.
		 *
		 * @param int    $recipient_id WP user ID of the notified member.
		 * @param string $type         Notification type slug.
		 * @param array  $data         {
		 *     @type string $message   Ready-to-display sentence.
		 *     @type string $link      Absolute URL to the relevant Listora page.
		 *     @type int    $actor_id  User who triggered the event (0 when system/admin).
		 *     @type int    $object_id Listing / review / claim ID.
		 * }
		 */
		do_action(
			'wb_listora_notification_created',
			$recipient_id,
			$type,
			array(
				'message'   => $message,
				'link'      => $link,
				'actor_id'  => (int) $actor_id,
				'object_id' => (int) $object_id,
			)
		);
	}
}
