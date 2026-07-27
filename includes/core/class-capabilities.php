<?php
/**
 * Custom capabilities.
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Manages custom capabilities for listing management.
 *
 * In addition to registering the role↔cap map (see `get_caps_map()`),
 * this class exposes static query helpers for the five "action" caps
 * that gate WB Listora UI surfaces. New code SHOULD prefer these
 * helpers over inline `current_user_can()` so a future cap rename
 * (or capability_type swap) only has to update one file.
 *
 * Existing inline `current_user_can( 'manage_listora_settings' )` style
 * call-sites are functionally identical and need not be migrated
 * urgently — these helpers are additive.
 */
class Capabilities {

	/** Manage plugin settings (settings pages, REST settings controller). */
	public const CAP_MANAGE_SETTINGS = 'manage_listora_settings';

	/** Approve / reject / hide / spam reviews. */
	public const CAP_MODERATE_REVIEWS = 'moderate_listora_reviews';

	/** Approve / reject business-claim requests. */
	public const CAP_MANAGE_CLAIMS = 'manage_listora_claims';

	/** Create / edit / delete listing types and their field maps. */
	public const CAP_MANAGE_TYPES = 'manage_listora_types';

	/** Submit a new listing through the frontend wizard. */
	public const CAP_SUBMIT_LISTING = 'submit_listora_listing';

	/**
	 * View the Listora dashboard overview page.
	 *
	 * Virtual cap — granted at runtime by `grant_view_dashboard_to_managers()`
	 * to any user with EITHER `manage_listora_settings` OR `edit_listora_listings`.
	 * The dashboard is read-only overview (counts, recent activity); no need to
	 * keep settings-managers and listing-editors apart on the entry page.
	 *
	 * Why this exists: pre-1.1.0 the dashboard menu was gated by
	 * `edit_listora_listings` directly. After the setup wizard ran, it
	 * redirected to the dashboard — but the wizard requires
	 * `manage_listora_settings`, not `edit_listora_listings`. A user with the
	 * settings cap but not the listings cap saw a blank "Insufficient
	 * permissions" page after completing setup. Card 9867159785.
	 */
	public const CAP_VIEW_DASHBOARD = 'view_listora_dashboard';

	/**
	 * Check whether a user can manage plugin settings.
	 *
	 * @param int|null $user_id Pass NULL (default) to check the current user.
	 * @return bool
	 */
	public static function can_manage_settings( ?int $user_id = null ): bool {
		return self::user_can( self::CAP_MANAGE_SETTINGS, $user_id );
	}

	/**
	 * Check whether a user can moderate reviews.
	 *
	 * @param int|null $user_id Pass NULL to check the current user.
	 * @return bool
	 */
	public static function can_moderate_reviews( ?int $user_id = null ): bool {
		return self::user_can( self::CAP_MODERATE_REVIEWS, $user_id );
	}

	/**
	 * Check whether a user can manage business claims.
	 *
	 * @param int|null $user_id Pass NULL to check the current user.
	 * @return bool
	 */
	public static function can_manage_claims( ?int $user_id = null ): bool {
		return self::user_can( self::CAP_MANAGE_CLAIMS, $user_id );
	}

	/**
	 * Check whether a user can manage listing types.
	 *
	 * @param int|null $user_id Pass NULL to check the current user.
	 * @return bool
	 */
	public static function can_manage_types( ?int $user_id = null ): bool {
		return self::user_can( self::CAP_MANAGE_TYPES, $user_id );
	}

	/**
	 * Check whether a user can submit a listing through the frontend wizard.
	 *
	 * @param int|null $user_id Pass NULL to check the current user.
	 * @return bool
	 */
	public static function can_submit_listing( ?int $user_id = null ): bool {
		return self::user_can( self::CAP_SUBMIT_LISTING, $user_id );
	}

	/**
	 * Generic cap dispatcher — current user (NULL) or a specific user.
	 *
	 * @param string   $cap     Capability slug.
	 * @param int|null $user_id User ID, or NULL for the current user.
	 * @return bool
	 */
	public static function user_can( string $cap, ?int $user_id = null ): bool {
		if ( null === $user_id ) {
			return current_user_can( $cap );
		}
		return user_can( $user_id, $cap );
	}

	/**
	 * All custom capabilities grouped by role.
	 *
	 * The SINGLE source of truth for the plugin's capability set — add_caps()
	 * grants from it and remove_caps()/all_caps() derive the removal list from it,
	 * so a new capability is added in exactly ONE place (AUDIT-M: the removal list
	 * was previously hand-copied into a dead remove_caps() AND inlined again in
	 * uninstall.php). Static so uninstall.php can reach it without an instance.
	 *
	 * @return array<string, array<string, bool>>
	 */
	private static function get_caps_map() {
		return array(
			'administrator' => array(
				// Listing CRUD.
				'edit_listora_listing'              => true,
				'edit_listora_listings'             => true,
				'edit_others_listora_listings'      => true,
				'edit_published_listora_listings'   => true,
				'publish_listora_listings'          => true,
				'delete_listora_listing'            => true,
				'delete_listora_listings'           => true,
				'delete_others_listora_listings'    => true,
				'delete_published_listora_listings' => true,
				'read_private_listora_listings'     => true,
				// Management.
				'manage_listora_settings'           => true,
				'moderate_listora_reviews'          => true,
				'manage_listora_claims'             => true,
				'manage_listora_types'              => true,
				'submit_listora_listing'            => true,
			),
			'editor'        => array(
				'edit_listora_listing'              => true,
				'edit_listora_listings'             => true,
				'edit_others_listora_listings'      => true,
				'edit_published_listora_listings'   => true,
				'publish_listora_listings'          => true,
				'delete_listora_listing'            => true,
				'delete_listora_listings'           => true,
				'delete_published_listora_listings' => true,
				'read_private_listora_listings'     => true,
				'moderate_listora_reviews'          => true,
				'manage_listora_claims'             => true,
				'submit_listora_listing'            => true,
			),
			'author'        => array(
				'edit_listora_listing'            => true,
				'edit_listora_listings'           => true,
				'edit_published_listora_listings' => true,
				'delete_listora_listing'          => true,
				'delete_listora_listings'         => true,
				'submit_listora_listing'          => true,
				// Author already has upload_files by default.
			),
			'contributor'   => array(
				'edit_listora_listing'    => true,
				'edit_listora_listings'   => true,
				'delete_listora_listing'  => true,
				'delete_listora_listings' => true,
				'submit_listora_listing'  => true,
				// Default WP contributors lack upload_files. Without it the
				// submission wizard's Featured Image / Gallery / file fields
				// open the wp.media modal but admin-ajax rejects every
				// upload — silently from the user's perspective. Grant
				// upload_files explicitly so contributors can attach images
				// to their own listings (QA card 9856831966).
				'upload_files'            => true,
			),
			'subscriber'    => array(
				'submit_listora_listing' => true,
				// Same reasoning as contributor — guest-submission and
				// subscriber-submission flows both go through the wizard's
				// media upload zones.
				'upload_files'           => true,
			),
		);
	}

	/**
	 * Register capabilities (called on init).
	 * This doesn't add caps — it just ensures the system is ready.
	 */
	public function register() {
		// Caps are added on activation, not on every init. The
		// runtime user_has_cap filter below grants upload_files to
		// existing installs that activated before the role-map was
		// updated, so admins don't need to deactivate-reactivate
		// to get the fix.
		add_filter( 'user_has_cap', array( $this, 'grant_upload_files_to_submitters' ), 10, 4 );

		// Virtual `view_listora_dashboard` cap — granted to any user who
		// can either manage settings or edit listings. Lets the dashboard
		// menu page act as a shared entry point for both personas without
		// double-registering the menu.
		add_filter( 'user_has_cap', array( $this, 'grant_view_dashboard_to_managers' ), 10, 4 );
	}

	/**
	 * Grant `upload_files` at runtime to any logged-in user who can
	 * `submit_listora_listing`. The submission wizard's media upload
	 * zones open the wp.media modal then POST to admin-ajax's
	 * `upload-attachment` action, which checks this exact cap. Without
	 * the grant, contributors and subscribers see the modal open but
	 * uploads silently fail — the modal hides and the file never gets
	 * attached. QA card 9856831966.
	 *
	 * Defensive: the cap is granted only when the user *would* have
	 * `submit_listora_listing` from their other caps, so an admin
	 * stripping `submit_listora_listing` from a role automatically
	 * revokes the implicit upload grant too.
	 *
	 * @param array<string, bool> $allcaps All capabilities resolved for the user.
	 * @param array<int, string>  $caps    Required caps to check (unused).
	 * @param array<int, mixed>   $args    has_cap arguments (unused).
	 * @param \WP_User            $user    The user being checked.
	 * @return array<string, bool>
	 */
	public function grant_upload_files_to_submitters( $allcaps, $caps, $args, $user ) {
		unset( $caps, $args ); // Not needed; we key off $allcaps + $user.

		if ( ! $user || empty( $user->ID ) ) {
			return $allcaps;
		}

		if ( empty( $allcaps['submit_listora_listing'] ) ) {
			return $allcaps;
		}

		if ( empty( $allcaps['upload_files'] ) ) {
			$allcaps['upload_files'] = true;
		}

		return $allcaps;
	}

	/**
	 * Grant the virtual `view_listora_dashboard` cap to any user who can
	 * either manage settings or edit listings.
	 *
	 * The dashboard menu page (`page=listora`) needs a single cap string for
	 * `add_menu_page()` but it should be visible to both settings-managers and
	 * listing-editors. We can't compose two real caps in `add_menu_page()`, so
	 * we use a virtual cap and grant it at runtime to the union of the two
	 * source roles. Defensive: if both source caps are removed from a user,
	 * the virtual grant disappears too (no orphan dashboard access).
	 *
	 * @param array<string, bool> $allcaps All capabilities resolved for the user.
	 * @param array<int, string>  $caps    Required caps to check (unused).
	 * @param array<int, mixed>   $args    has_cap arguments (unused).
	 * @param \WP_User            $user    The user being checked.
	 * @return array<string, bool>
	 */
	public function grant_view_dashboard_to_managers( $allcaps, $caps, $args, $user ) {
		unset( $caps, $args );

		if ( ! $user || empty( $user->ID ) ) {
			return $allcaps;
		}

		if ( ! empty( $allcaps[ self::CAP_VIEW_DASHBOARD ] ) ) {
			return $allcaps;
		}

		if ( ! empty( $allcaps[ self::CAP_MANAGE_SETTINGS ] )
			|| ! empty( $allcaps['edit_listora_listings'] ) ) {
			$allcaps[ self::CAP_VIEW_DASHBOARD ] = true;
		}

		return $allcaps;
	}

	/**
	 * Add capabilities to roles (called on activation).
	 */
	public function add_caps() {
		$caps_map = self::get_caps_map();

		foreach ( $caps_map as $role_name => $caps ) {
			$role = get_role( $role_name );

			if ( ! $role ) {
				continue;
			}

			foreach ( $caps as $cap => $grant ) {
				$role->add_cap( $cap, $grant );
			}
		}
	}

	/**
	 * The flat, de-duplicated list of every custom capability the plugin grants.
	 *
	 * Derived from get_caps_map() so it can never drift from what add_caps()
	 * actually grants.
	 *
	 * @return string[]
	 */
	public static function all_caps() {
		$caps = array();
		foreach ( self::get_caps_map() as $role_caps ) {
			$caps = array_merge( $caps, array_keys( $role_caps ) );
		}
		$caps = array_values( array_unique( $caps ) );

		// Only the plugin's OWN capabilities (every custom cap carries 'listora').
		// The grant map also assigns WP CORE caps to some roles — e.g.
		// `upload_files` to submitters so members can attach media — and those must
		// NEVER be returned for removal: stripping a core cap on uninstall would
		// break a role that legitimately needs it.
		return array_values(
			array_filter(
				$caps,
				static function ( $cap ) {
					return false !== strpos( $cap, 'listora' );
				}
			)
		);
	}

	/**
	 * Remove every custom capability from all roles (called on uninstall).
	 *
	 * The removal list is derived from the grant map via all_caps(), so uninstall
	 * cleanup stays in lockstep with what was granted with zero hand-maintained
	 * copies.
	 */
	public static function remove_caps() {
		$all_caps = self::all_caps();
		$roles    = wp_roles();

		foreach ( $roles->role_objects as $role ) {
			foreach ( $all_caps as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}
}
