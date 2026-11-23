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
	 * Virtual cap, granted at runtime by `grant_view_dashboard_to_managers()`
	 * to anyone with `manage_listora_settings` OR `edit_listora_listings`, so a
	 * settings-only user who finishes the setup wizard can open the dashboard
	 * it redirects to.
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
			),
			'subscriber'    => array(
				'submit_listora_listing' => true,
			),
		);
	}

	/**
	 * Roles that hold submit_listora_listing right now.
	 *
	 * The roles themselves are the truth, so a cap an owner added by hand
	 * (`wp cap add customer submit_listora_listing`) shows as ticked and survives
	 * a save of any other settings tab.
	 *
	 * @since 2.1.2
	 *
	 * @return string[]
	 */
	public static function submission_roles(): array {
		$roles = array();
		foreach ( wp_roles()->role_objects as $slug => $role ) {
			if ( ! empty( $role->capabilities[ self::CAP_SUBMIT_LISTING ] ) ) {
				$roles[] = (string) $slug;
			}
		}
		return $roles;
	}

	/**
	 * Clean a posted role list: existing roles only, administrator always in.
	 *
	 * @since 2.1.2
	 *
	 * @param mixed $raw Posted role slugs.
	 * @return string[]
	 */
	public static function sanitize_submission_roles( $raw ): array {
		$known   = array_keys( wp_roles()->get_names() );
		$roles   = array_intersect( array_map( 'sanitize_key', (array) $raw ), $known );
		$roles[] = 'administrator';
		return array_values( array_unique( $roles ) );
	}

	/**
	 * Give submit_listora_listing to exactly these roles.
	 *
	 * Members upload listing photos through POST listora/v1/media, gated on this
	 * cap, so a newly ticked role (WooCommerce Customer, Dokan Vendor) never needs
	 * core upload_files and never reaches the Media Library.
	 *
	 * @since 2.1.2
	 *
	 * @param string[] $roles Role slugs.
	 * @return void
	 */
	public static function sync_submission_roles( array $roles ): void {
		foreach ( wp_roles()->role_objects as $slug => $role ) {
			$should = in_array( $slug, $roles, true );
			$has    = ! empty( $role->capabilities[ self::CAP_SUBMIT_LISTING ] );
			if ( $should && ! $has ) {
				$role->add_cap( self::CAP_SUBMIT_LISTING );
			} elseif ( ! $should && $has ) {
				$role->remove_cap( self::CAP_SUBMIT_LISTING );
			}
		}
	}

	/**
	 * Apply the owner's "Who can add listings" choice whenever the settings are
	 * written: the admin form, REST and WP-CLI all end in this option write.
	 * Only a write that carries the key changes roles.
	 *
	 * @since 2.1.2
	 *
	 * @param mixed $old_value Previous settings.
	 * @param mixed $value     New settings.
	 * @return void
	 */
	public static function on_settings_saved( $old_value, $value ): void {
		if ( is_array( $value ) && isset( $value['submission_roles'] ) && is_array( $value['submission_roles'] ) ) {
			self::sync_submission_roles( self::sanitize_submission_roles( $value['submission_roles'] ) );
		} elseif ( is_array( $old_value ) && isset( $old_value['submission_roles'] ) ) {
			// "Reset this tab" removed the key: back to the out-of-the-box roles.
			self::sync_submission_roles( self::default_submission_roles() );
		}
	}

	/**
	 * Full "Reset to defaults" deletes the option: the roles go back too.
	 *
	 * @since 2.1.2
	 *
	 * @param string[] $cleared Option keys the reset deleted.
	 * @return void
	 */
	public static function on_settings_reset( $cleared ): void {
		if ( in_array( 'wb_listora_settings', (array) $cleared, true ) ) {
			self::sync_submission_roles( self::default_submission_roles() );
		}
	}

	/**
	 * Roles that may add listings out of the box.
	 *
	 * @since 2.1.2
	 *
	 * @return string[]
	 */
	public static function default_submission_roles(): array {
		return array_keys(
			array_filter(
				self::get_caps_map(),
				static function ( $caps ) {
					return ! empty( $caps[ self::CAP_SUBMIT_LISTING ] );
				}
			)
		);
	}

	/**
	 * Register capabilities (called on init).
	 * This doesn't add caps — it just ensures the system is ready.
	 */
	public function register() {
		add_action( 'update_option_wb_listora_settings', array( self::class, 'on_settings_saved' ), 10, 2 );
		add_action( 'wb_listora_after_reset_settings', array( self::class, 'on_settings_reset' ) );
		add_action(
			'add_option_wb_listora_settings',
			static function ( $option, $value ) {
				unset( $option );
				self::on_settings_saved( array(), $value );
			},
			10,
			2
		);

		// Opt-in only since 2.1.2: see grant_upload_files_to_submitters().
		add_filter( 'user_has_cap', array( $this, 'grant_upload_files_to_submitters' ), 10, 4 );

		// Virtual `view_listora_dashboard` cap — granted to any user who
		// can either manage settings or edit listings. Lets the dashboard
		// menu page act as a shared entry point for both personas without
		// double-registering the menu.
		add_filter( 'user_has_cap', array( $this, 'grant_view_dashboard_to_managers' ), 10, 4 );
	}

	/**
	 * Grant core `upload_files` to listing submitters, only when a site opts in.
	 *
	 * Since 2.1.2 members upload through POST listora/v1/media, which needs no
	 * core cap, so they stay out of the Media menu and the admin's library. A
	 * site that relied on the old grant restores it with
	 * `add_filter( 'wb_listora_grant_upload_files_to_submitters', '__return_true' );`.
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

		if ( empty( $allcaps['submit_listora_listing'] ) || ! empty( $allcaps['upload_files'] ) ) {
			return $allcaps;
		}

		if ( apply_filters( 'wb_listora_grant_upload_files_to_submitters', false, $user ) ) {
			$allcaps['upload_files'] = true;
		}

		return $allcaps;
	}

	/**
	 * Grant the virtual `view_listora_dashboard` cap to any user who can
	 * either manage settings or edit listings.
	 *
	 * `add_menu_page()` takes one cap, so the union of two real caps is a
	 * virtual cap granted here; removing both source caps removes it too.
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

		// Re-activating must not undo a saved "Who can add listings" choice.
		$settings = get_option( 'wb_listora_settings', array() );
		$saved    = is_array( $settings ) && isset( $settings['submission_roles'] ) && is_array( $settings['submission_roles'] )
			? $settings['submission_roles']
			: null;

		foreach ( $caps_map as $role_name => $caps ) {
			$role = get_role( $role_name );

			if ( ! $role ) {
				continue;
			}

			if ( null !== $saved ) {
				unset( $caps[ self::CAP_SUBMIT_LISTING ] );
			}
			foreach ( $caps as $cap => $grant ) {
				$role->add_cap( $cap, $grant );
			}
		}

		if ( null !== $saved ) {
			self::sync_submission_roles( self::sanitize_submission_roles( $saved ) );
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
