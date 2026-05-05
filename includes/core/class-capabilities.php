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
 */
class Capabilities {

	/**
	 * All custom capabilities grouped by role.
	 *
	 * @return array
	 */
	private function get_caps_map() {
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
	 * Add capabilities to roles (called on activation).
	 */
	public function add_caps() {
		$caps_map = $this->get_caps_map();

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
	 * Remove capabilities from all roles (called on uninstall).
	 */
	public static function remove_caps() {
		$all_caps = array(
			'edit_listora_listing',
			'edit_listora_listings',
			'edit_others_listora_listings',
			'edit_published_listora_listings',
			'publish_listora_listings',
			'delete_listora_listing',
			'delete_listora_listings',
			'delete_others_listora_listings',
			'delete_published_listora_listings',
			'read_private_listora_listings',
			'manage_listora_settings',
			'moderate_listora_reviews',
			'manage_listora_claims',
			'manage_listora_types',
			'submit_listora_listing',
		);

		$roles = wp_roles();

		foreach ( $roles->role_objects as $role ) {
			foreach ( $all_caps as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}
}
