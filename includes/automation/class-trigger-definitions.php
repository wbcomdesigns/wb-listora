<?php
/**
 * The Free trigger catalogue — the product decision about what an
 * automation platform can subscribe to.
 *
 * Every entry below survived three tests (see plan/automation-integration-surface.md):
 *
 *   1. It is a business fact an owner would automate on, not an implementation
 *      detail. This is what keeps the catalogue out of the 25 template/render
 *      hooks (`after_card*`, `after_map`, `after_template`, etc.) — those fire
 *      on every page render with view data, not on an event.
 *   2. It fires from EVERY path that produces the fact, not just REST. A
 *      trigger wired to one of two paths is worse than none, because the
 *      automation looks like it works. This is the claim audit trail bug
 *      (BC 10199419982): the admin Claims page changed a claim's status
 *      without ever firing `wb_listora_after_update_claim`, so every listener
 *      on that hook — including Pro's would-be webhook delivery — silently
 *      missed every admin approve/reject. The same class of bug was found
 *      fresh in Reviews while building this catalogue: `class-admin.php`'s
 *      Reviews page approve/reject/bulk actions run a raw `$wpdb->update()`
 *      and fire NOTHING — not `wb_listora_review_status_changed`, not
 *      `wb_listora_after_update_review`. No review-moderation trigger is
 *      declared here for exactly that reason; see the "Deferred triggers"
 *      section of plan/automation-integration-surface.md.
 *   3. Its payload resolves without the caller's request context, so cron
 *      and WP-CLI paths fire it identically. Deletion facts fail this test —
 *      by the time a `listing_deleted`-shaped hook fires, the row the
 *      payload would read is already gone — so no deletion trigger is
 *      declared here. `wb_listora_listing_expired` was checked against this
 *      test specifically (see below) and passes: expiring a listing sets
 *      `post_status = listora_expired`, which is a status CHANGE, not a
 *      trash or delete, so neither the post row nor its `search_index` row
 *      disappears (`Search_Indexer::remove_from_index()` only runs on
 *      `before_delete_post` / `trashed_post`) and `Payload::listing()`
 *      resolves normally.
 *
 * One trigger per fact. Several hooks in this codebase fire in pairs from
 * the exact same call site for the exact same occurrence — declaring both
 * would deliver two automation events for one thing that happened. Every
 * rejected twin is named at its declared sibling, with the reason it lost:
 *
 *   - `wb_listora_favorite_added`     kept over `wb_listora_after_add_favorite`
 *   - `wb_listora_favorite_removed`   kept over `wb_listora_after_remove_favorite`
 *   - `wb_listora_claim_submitted`    kept over `wb_listora_after_submit_claim`
 *   - `wb_listora_review_submitted`   kept over `wb_listora_after_create_review`
 *   - `wb_listora_listing_submitted`  kept over `wb_listora_after_create_listing`
 *     (5 fire sites vs. 1 — the REST-only hook misses admin-created and
 *     email-verification-completed listings entirely)
 *   - `wb_listora_listing_renewed`    kept over `wb_listora_after_renew_listing`
 *   - `wb_listora_after_update_claim` kept over the dedicated
 *     `wb_listora_claim_approved` / `wb_listora_claim_rejected` hooks — all
 *     three fire from the same `apply_approval_side_effects()` /
 *     `fire_claim_updated()` call sites for the same approval or rejection,
 *     and `after_update_claim` is the one verified (by a 1.6.0 fix) to cover
 *     both the REST and wp-admin paths.
 *
 * `wb_listora_listing_claimed` is NOT a twin of `claim_approved` despite
 * firing from the same `apply_approval_side_effects()` call — it is a
 * different fact about a different entity (the LISTING gained an owner,
 * vs. the CLAIM record reached a terminal status), and each is useful to a
 * different automation without double-delivering the other's payload.
 *
 * `listing_submitted` and `listing_pending_review` are the same shape of
 * near-twin, and the decision to keep both is deliberate, not an oversight:
 * both fire two lines apart from the same call sites (REST submission,
 * admin-created listings, email-verification completion). "A listing
 * arrived" (`listing_submitted`, every resulting status) and "a listing
 * needs a moderator" (`listing_pending_review`, `pending` only) are
 * different facts to different subscribers — a "log every submission"
 * automation wants the first, a "ping Slack when something needs review"
 * automation wants only the second, which auto-approved submissions never
 * produce. Both are kept.
 *
 * Correction to an earlier version of this file: the rationale for choosing
 * `wb_listora_after_update_claim` over the dedicated `wb_listora_claim_approved`
 * / `_claim_rejected` hooks previously stated that the dedicated hooks did
 * NOT cover both the REST and wp-admin paths. That was false — they do; all
 * three (the two dedicated hooks and `after_update_claim`) fire from the
 * same `apply_approval_side_effects()` / `fire_claim_updated()` call sites
 * for the same approval or rejection, verified in
 * `class-claims-controller.php` and `class-admin.php`. The real reason
 * `after_update_claim` was kept is Ruling B's "one trigger per fact":
 * declaring the dedicated hooks IN ADDITION to `after_update_claim` would
 * fire two automation events for one claim decision. `after_update_claim`
 * was picked over the dedicated pair (rather than the other way round)
 * because it is the hook the brief's own Ruling D example names, and using
 * one hook with a `condition` per outcome mirrors the
 * `listing_status_changed` pattern already used for listings below.
 *
 * Shared-hook triggers (below) that need to be told apart carry a
 * `condition` array — `new_status` and, where needed, `previous_status`.
 * This is additive: `Trigger_Registry::register()` only requires and
 * validates the 7 documented keys, and passes any extra key straight
 * through into the stored entry.
 *
 * IMPORTANT — `condition` is declarative only today. It records the intent
 * ("this entry only applies when the hook's own $new_status/$previous_status
 * match this shape") for a future dispatcher to evaluate; nothing currently
 * reads or enforces it — Pro's `Outgoing_Webhooks` branches on `$new_status`
 * itself inside its own handlers (`on_listing_status_changed()`,
 * `on_claim_updated()`), not via this field. Wiring an actual dispatcher to
 * enforce `condition` is a prerequisite before any discovery endpoint
 * publishes conditions to third parties, and is explicitly NOT done here —
 * it would change live delivery (e.g. a `listora_deactivated -> publish`
 * reactivation currently satisfies `listing_approved` and would stop under
 * naive enforcement, and `listing_reactivated` is not in Pro's deliverable
 * map, so subscribers would simply lose the event). That needs its own task,
 * verification, and a production-rule-3 escape hatch. The mutual-exclusivity
 * test between sibling conditions on the same hook (see class docblock above)
 * remains valuable and enforced today regardless.
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

		// listing_approved / listing_rejected / listing_deactivated /
		// listing_reactivated / listing_pending_review all hang off the SAME
		// hook — `wb_listora_listing_status_changed`, fired from
		// Search_Indexer::on_status_change() on WP core's own
		// `transition_post_status`. That is a true chokepoint: every path
		// that can change a listing's status (REST, wp-admin row actions,
		// wp-admin's native Quick Edit / classic status dropdown, WP-CLI,
		// cron) runs through `wp_update_post()`, which always fires
		// `transition_post_status`. The dedicated REST-only hooks
		// `wb_listora_after_deactivate_listing` / `_after_reactivate_listing`
		// do NOT have this property — wp-admin's classic edit screen can
		// move a listing between `publish` and `listora_deactivated` via its
		// native Status dropdown without ever calling the REST endpoint, so
		// those two are the same shape of gap as the claim audit trail bug
		// and are not declared. See "Deferred triggers".
		//
		// Five different facts share one hook, so each entry below carries a
		// `condition` matched against the hook's own ($new_status,
		// $previous_status) arguments — evaluated by a later task's
		// dispatcher. The five are mutually exclusive: `publish` only
		// appears with two disjoint `previous_status` sets (approved vs.
		// reactivated), and every other `new_status` value is unique to one
		// trigger. Without this, `listora_deactivated → publish` would
		// satisfy both listing_approved's bare "new status is publish" and
		// listing_reactivated's fact, firing both for one transition.
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

		// A listing entered `pending` and is waiting on a moderator.
		//
		// CORRECTED — this was originally declared on
		// `wb_listora_listing_pending_admin`, which is dead code and can
		// never fire on any install. All three of that hook's call sites
		// are gated on the listing already being in `pending_verification`
		// status, and the only code that ever sets that status
		// (`Submission_Controller::create_listing()` ~L437-441) is gated on
		// `$verification_required`, which is hardcoded `false` at ~L411
		// with the comment "guest submission was removed" and never
		// reassigned anywhere in the codebase. No listing on any install can
		// reach `pending_verification`, so the hook this trigger pointed at
		// never fires — a subscribable event that is silent forever, the
		// exact failure this catalogue exists to prevent.
		//
		// The fact itself is real, so it is redeclared on the same
		// `wb_listora_listing_status_changed` chokepoint used four times
		// above, conditioned on `new_status = pending`. That covers every
		// path that can move a listing to `pending` (REST submission
		// without auto-approve, admin-created pending listings, and any
		// wp-admin status edit) rather than the 3 now-unreachable
		// call sites of the old hook.
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
		// NOTE (test 2, deliberately NOT declared): `review_approved` /
		// `review_rejected` off `wb_listora_review_status_changed` are not
		// declared here. That hook only fires from the REST update-review
		// endpoint. wp-admin's Reviews page (`Admin::render_reviews_page()`)
		// approve/reject row action AND bulk action run a raw
		// `$wpdb->update()` directly against the reviews table and fire NO
		// hook at all — not `review_status_changed`, not even the generic
		// `wb_listora_after_update_review`. This is the same shape of bug as
		// the claim audit trail (BC 10199419982), unfixed, found while
		// building this catalogue. See "Deferred triggers" in
		// plan/automation-integration-surface.md.
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
