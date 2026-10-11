<?php
/**
 * The Free trigger catalogue: what an automation platform can subscribe to.
 *
 * Each entry is a business fact (not a render hook), fires from EVERY path
 * that produces it, and resolves its payload without request context; see
 * plan/automation-integration-surface.md. One trigger per fact: of two hooks
 * fired from the same call site for one occurrence, one is declared and the
 * rejected twin is named at its sibling.
 *
 * `condition` on shared-hook triggers is declarative only (nothing enforces
 * it; Pro's Outgoing_Webhooks branches on $new_status itself). Enforcing it
 * would change live delivery, so build and verify a dispatcher first.
 *
 * @package WBListora\Automation
 */

namespace WBListora\Automation;

use WBListora\Contracts\Trigger_Registry_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Declares Free's trigger catalogue into the registry.
 */
class Trigger_Definitions {

	/**
	 * Register every Free-owned trigger, then open the door for Pro.
	 *
	 * @param Trigger_Registry_Interface $registry Trigger registry.
	 * @return void
	 */
	public static function register_all( Trigger_Registry_Interface $registry ) {
		self::register_listing_triggers( $registry );
		self::register_review_triggers( $registry );
		self::register_claim_triggers( $registry );
		self::register_member_triggers( $registry );
		self::register_favourite_triggers( $registry );
		self::register_service_triggers( $registry );

		/**
		 * Fires after Free declares its triggers.
		 *
		 * Pro and add-ons register their own here. Free's declarations land
		 * first, so a Pro entry can never shadow a Free event — the registry
		 * refuses a duplicate name.
		 *
		 * @since 1.7.0
		 *
		 * @param Trigger_Registry_Interface $registry Registry.
		 */
		do_action( 'wb_listora_register_triggers', $registry );
	}

	/**
	 * Listing lifecycle triggers.
	 *
	 * @param Trigger_Registry_Interface $registry Trigger registry.
	 * @return void
	 */
	private static function register_listing_triggers( Trigger_Registry_Interface $registry ) {
		// A new listing was submitted, in whichever status the submission
		// resolved to (publish on auto-approve, pending otherwise). 5 fire
		// sites: REST submission (create + email-verification-gated create),
		// admin-created listings, and the email-verification completion path.
		// `wb_listora_after_create_listing` covers exactly 1 of those 5.
		$registry->register(
			array(
				'name'       => 'listing_submitted',
				'label'      => __( 'Listing Submitted', 'wb-listora' ),
				'group'      => 'listing',
				'hook'       => 'wb_listora_listing_submitted',
				'capability' => 'manage_listora_settings',
				'version'    => 1,
				'schema'     => 'listing_submitted.v1.json',
			)
		);

		// listing_approved / _rejected / _deactivated / _reactivated /
		// _pending_review all hang off `wb_listora_listing_status_changed`, fired
		// from Search_Indexer::on_status_change() on core's
		// `transition_post_status`: a true chokepoint every status change (REST,
		// wp-admin, Quick Edit, WP-CLI, cron) passes through. The REST-only
		// `_after_deactivate_listing` / `_after_reactivate_listing` hooks miss
		// wp-admin's Status dropdown, so they are not declared.
		//
		// Each entry carries a mutually exclusive `condition` on ($new_status,
		// $previous_status): `publish` appears only with two disjoint
		// `previous_status` sets (approved vs. reactivated), so one transition
		// never satisfies two triggers.
		$registry->register(
			array(
				'name'       => 'listing_approved',
				'label'      => __( 'Listing Approved', 'wb-listora' ),
				'group'      => 'listing',
				'hook'       => 'wb_listora_listing_status_changed',
				'capability' => 'manage_listora_settings',
				'version'    => 1,
				'schema'     => 'listing_approved.v1.json',
				'condition'  => array(
					'new_status'      => 'publish',
					'previous_status' => array( 'pending', 'listora_rejected' ),
				),
			)
		);

		$registry->register(
			array(
				'name'       => 'listing_rejected',
				'label'      => __( 'Listing Rejected', 'wb-listora' ),
				'group'      => 'listing',
				'hook'       => 'wb_listora_listing_status_changed',
				'capability' => 'manage_listora_settings',
				'version'    => 1,
				'schema'     => 'listing_rejected.v1.json',
				'condition'  => array(
					'new_status' => 'listora_rejected',
				),
			)
		);

		$registry->register(
			array(
				'name'       => 'listing_deactivated',
				'label'      => __( 'Listing Deactivated', 'wb-listora' ),
				'group'      => 'listing',
				'hook'       => 'wb_listora_listing_status_changed',
				'capability' => 'manage_listora_settings',
				'version'    => 1,
				'schema'     => 'listing_deactivated.v1.json',
				'condition'  => array(
					'new_status' => 'listora_deactivated',
				),
			)
		);

		$registry->register(
			array(
				'name'       => 'listing_reactivated',
				'label'      => __( 'Listing Reactivated', 'wb-listora' ),
				'group'      => 'listing',
				'hook'       => 'wb_listora_listing_status_changed',
				'capability' => 'manage_listora_settings',
				'version'    => 1,
				'schema'     => 'listing_reactivated.v1.json',
				'condition'  => array(
					'new_status'      => 'publish',
					'previous_status' => array( 'listora_deactivated' ),
				),
			)
		);

		// A listing expired via the daily expiration cron
		// (Expiration_Cron::expire_listings(), the only code path that ever
		// sets `post_status = listora_expired`) — a single fire site that is
		// a genuine chokepoint by construction, same reasoning as Ruling D's
		// `wb_listora_after_update_claim` example. Payload resolution
		// verified: expiring sets a status, it does not trash or delete, so
		// `Payload::listing()` still resolves. See the class docblock.
		$registry->register(
			array(
				'name'       => 'listing_expired',
				'label'      => __( 'Listing Expired', 'wb-listora' ),
				'group'      => 'listing',
				'hook'       => 'wb_listora_listing_expired',
				'capability' => 'manage_listora_settings',
				'version'    => 1,
				'schema'     => 'listing_expired.v1.json',
			)
		);

		// Ownership of the LISTING transferred. Fires from
		// Claims_Controller::apply_approval_side_effects(), called from both
		// the REST claim-update endpoint and both wp-admin approve paths
		// (single row action + bulk action) — verified by reading all three
		// call sites.
		$registry->register(
			array(
				'name'       => 'listing_claimed',
				'label'      => __( 'Listing Claimed', 'wb-listora' ),
				'group'      => 'listing',
				'hook'       => 'wb_listora_listing_claimed',
				'capability' => 'manage_listora_settings',
				'version'    => 1,
				'schema'     => 'listing_claimed.v1.json',
			)
		);

		// A listing's expiration date was extended. The only implementation
		// of "renew" in the product is the REST renewal endpoint — no
		// wp-admin equivalent exists — so the single fire site is the whole
		// path space, not a partial one.
		$registry->register(
			array(
				'name'       => 'listing_renewed',
				'label'      => __( 'Listing Renewed', 'wb-listora' ),
				'group'      => 'listing',
				'hook'       => 'wb_listora_listing_renewed',
				'capability' => 'manage_listora_settings',
				'version'    => 1,
				'schema'     => 'listing_renewed.v1.json',
			)
		);

		// A visitor reported/flagged a listing. Single implementation
		// (REST `report` endpoint is the only way to file one).
		$registry->register(
			array(
				'name'       => 'listing_reported',
				'label'      => __( 'Listing Reported', 'wb-listora' ),
				'group'      => 'listing',
				'hook'       => 'wb_listora_listing_reported',
				'capability' => 'manage_listora_settings',
				'version'    => 1,
				'schema'     => 'listing_reported.v1.json',
			)
		);

		// A listing entered `pending` and is waiting on a moderator. Declared on
		// the `wb_listora_listing_status_changed` chokepoint with
		// `new_status = pending`, covering every path to `pending` (REST
		// submission without auto-approve, admin-created listings, wp-admin
		// status edits). `wb_listora_listing_pending_admin` is not used: it only
		// fires for `pending_verification`, which nothing sets.
		$registry->register(
			array(
				'name'       => 'listing_pending_review',
				'label'      => __( 'Listing Pending Review', 'wb-listora' ),
				'group'      => 'listing',
				'hook'       => 'wb_listora_listing_status_changed',
				'capability' => 'manage_listora_settings',
				'version'    => 1,
				'schema'     => 'listing_pending_review.v1.json',
				'condition'  => array(
					'new_status' => 'pending',
				),
			)
		);
	}

	/**
	 * Review triggers.
	 *
	 * @param Trigger_Registry_Interface $registry Trigger registry.
	 * @return void
	 */
	private static function register_review_triggers( Trigger_Registry_Interface $registry ) {
		$registry->register(
			array(
				'name'       => 'review_submitted',
				'label'      => __( 'Review Submitted', 'wb-listora' ),
				'group'      => 'reviews',
				'hook'       => 'wb_listora_review_submitted',
				'capability' => 'manage_listora_settings',
				'version'    => 1,
				'schema'     => 'review_submitted.v1.json',
			)
		);

		// A listing owner replied to a review. Single implementation
		// (REST `owner_reply` endpoint — no wp-admin equivalent).
		//
		// `review_approved` / `review_rejected` are deliberately NOT declared:
		// `wb_listora_review_status_changed` fires only from REST, while
		// wp-admin's Reviews page updates the table directly and fires no hook
		// (test 2). See "Deferred triggers" in plan/automation-integration-surface.md.
		$registry->register(
			array(
				'name'       => 'review_reply_posted',
				'label'      => __( 'Review Reply Posted', 'wb-listora' ),
				'group'      => 'reviews',
				'hook'       => 'wb_listora_review_reply',
				'capability' => 'manage_listora_settings',
				'version'    => 1,
				'schema'     => 'review_reply_posted.v1.json',
			)
		);
	}

	/**
	 * Claim triggers.
	 *
	 * @param Trigger_Registry_Interface $registry Trigger registry.
	 * @return void
	 */
	private static function register_claim_triggers( Trigger_Registry_Interface $registry ) {
		$registry->register(
			array(
				'name'       => 'claim_submitted',
				'label'      => __( 'Claim Submitted', 'wb-listora' ),
				'group'      => 'claims',
				'hook'       => 'wb_listora_claim_submitted',
				'capability' => 'manage_listora_settings',
				'version'    => 1,
				'schema'     => 'claim_submitted.v1.json',
			)
		);

		// claim_approved / claim_rejected both hang off
		// `wb_listora_after_update_claim`, distinguished by `condition`.
		// See the class docblock for why — the dedicated
		// `wb_listora_claim_approved` / `_claim_rejected` hooks ALSO cover
		// both the REST and wp-admin paths (verified), so this is Ruling
		// B's same-occurrence-twin rule, not a path-coverage gap: all three
		// hooks fire together for one claim decision, and only one may be
		// declared.
		$registry->register(
			array(
				'name'       => 'claim_approved',
				'label'      => __( 'Claim Approved', 'wb-listora' ),
				'group'      => 'claims',
				'hook'       => 'wb_listora_after_update_claim',
				'capability' => 'manage_listora_settings',
				'version'    => 1,
				'schema'     => 'claim_approved.v1.json',
				'condition'  => array(
					'new_status' => 'approved',
				),
			)
		);

		$registry->register(
			array(
				'name'       => 'claim_rejected',
				'label'      => __( 'Claim Rejected', 'wb-listora' ),
				'group'      => 'claims',
				'hook'       => 'wb_listora_after_update_claim',
				'capability' => 'manage_listora_settings',
				'version'    => 1,
				'schema'     => 'claim_rejected.v1.json',
				'condition'  => array(
					'new_status' => 'rejected',
				),
			)
		);
	}

	/**
	 * Member triggers (moderation + self-service account lifecycle).
	 *
	 * @param Trigger_Registry_Interface $registry Trigger registry.
	 * @return void
	 */
	private static function register_member_triggers( Trigger_Registry_Interface $registry ) {
		// Member_Suspension::suspend()/unsuspend() are the sole writers of
		// suspension state, called from every admin entry point
		// (row action, single action, bulk action) in class-user-moderation.php
		// — a chokepoint by shared implementation, not by hook count.
		$registry->register(
			array(
				'name'       => 'member_suspended',
				'label'      => __( 'Member Suspended', 'wb-listora' ),
				'group'      => 'members',
				'hook'       => 'wb_listora_member_suspended',
				'capability' => 'manage_listora_settings',
				'version'    => 1,
				'schema'     => 'member_suspended.v1.json',
			)
		);

		$registry->register(
			array(
				'name'       => 'member_unsuspended',
				'label'      => __( 'Member Unsuspended', 'wb-listora' ),
				'group'      => 'members',
				'hook'       => 'wb_listora_member_unsuspended',
				'capability' => 'manage_listora_settings',
				'version'    => 1,
				'schema'     => 'member_unsuspended.v1.json',
			)
		);

		// Self-service account lifecycle (Account_Manager) — only ever
		// called with `get_current_user_id()` from the REST account
		// controller. No other path exists to deactivate/reactivate/delete
		// a member's OWN Listora account state.
		$registry->register(
			array(
				'name'       => 'account_deactivated',
				'label'      => __( 'Member Account Deactivated', 'wb-listora' ),
				'group'      => 'members',
				'hook'       => 'wb_listora_account_deactivated',
				'capability' => 'manage_listora_settings',
				'version'    => 1,
				'schema'     => 'account_deactivated.v1.json',
			)
		);

		$registry->register(
			array(
				'name'       => 'account_reactivated',
				'label'      => __( 'Member Account Reactivated', 'wb-listora' ),
				'group'      => 'members',
				'hook'       => 'wb_listora_account_reactivated',
				'capability' => 'manage_listora_settings',
				'version'    => 1,
				'schema'     => 'account_reactivated.v1.json',
			)
		);

		// Payload note: `wb_listora_account_deleted` fires AFTER
		// `wp_delete_user()` — the `wp_users` row is already gone, so
		// `Payload::user()` would return null. This does NOT make the
		// candidate fail test 3, though: the hook's own arguments
		// ($user_id, $email) are captured BEFORE deletion and passed
		// directly, so the payload resolves from the hook args rather than
		// from a re-lookup. The schema reflects that — flat `user_id` /
		// `email`, not a nested `user` object.
		$registry->register(
			array(
				'name'       => 'account_deleted',
				'label'      => __( 'Member Account Deleted', 'wb-listora' ),
				'group'      => 'members',
				'hook'       => 'wb_listora_account_deleted',
				'capability' => 'manage_listora_settings',
				'version'    => 1,
				'schema'     => 'account_deleted.v1.json',
			)
		);
	}

	/**
	 * Favourite triggers.
	 *
	 * @param Trigger_Registry_Interface $registry Trigger registry.
	 * @return void
	 */
	private static function register_favourite_triggers( Trigger_Registry_Interface $registry ) {
		$registry->register(
			array(
				'name'       => 'favorite_added',
				'label'      => __( 'Favorite Added', 'wb-listora' ),
				'group'      => 'favourites',
				'hook'       => 'wb_listora_favorite_added',
				'capability' => 'manage_listora_settings',
				'version'    => 1,
				'schema'     => 'favorite_added.v1.json',
			)
		);

		$registry->register(
			array(
				'name'       => 'favorite_removed',
				'label'      => __( 'Favorite Removed', 'wb-listora' ),
				'group'      => 'favourites',
				'hook'       => 'wb_listora_favorite_removed',
				'capability' => 'manage_listora_settings',
				'version'    => 1,
				'schema'     => 'favorite_removed.v1.json',
			)
		);
	}

	/**
	 * Service triggers.
	 *
	 * All three hang off `Services` class hooks, which are the sole
	 * implementation of service CRUD — called from both the REST services
	 * controller AND the admin services metabox, so every path converges
	 * on the same `do_action()` regardless of caller.
	 *
	 * @param Trigger_Registry_Interface $registry Trigger registry.
	 * @return void
	 */
	private static function register_service_triggers( Trigger_Registry_Interface $registry ) {
		$registry->register(
			array(
				'name'       => 'service_created',
				'label'      => __( 'Service Created', 'wb-listora' ),
				'group'      => 'services',
				'hook'       => 'wb_listora_after_create_service',
				'capability' => 'manage_listora_settings',
				'version'    => 1,
				'schema'     => 'service_created.v1.json',
			)
		);

		$registry->register(
			array(
				'name'       => 'service_updated',
				'label'      => __( 'Service Updated', 'wb-listora' ),
				'group'      => 'services',
				'hook'       => 'wb_listora_after_update_service',
				'capability' => 'manage_listora_settings',
				'version'    => 1,
				'schema'     => 'service_updated.v1.json',
			)
		);

		// Services are soft-deleted (`status = 'deleted'`), and the fired
		// hook is passed the pre-deletion row directly as `$existing` — the
		// payload never needs a post-deletion re-lookup, so this does not
		// carry the deletion-payload risk Ruling C warns about for listings.
		$registry->register(
			array(
				'name'       => 'service_deleted',
				'label'      => __( 'Service Deleted', 'wb-listora' ),
				'group'      => 'services',
				'hook'       => 'wb_listora_after_delete_service',
				'capability' => 'manage_listora_settings',
				'version'    => 1,
				'schema'     => 'service_deleted.v1.json',
			)
		);
	}
}
