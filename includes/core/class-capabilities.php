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
	 * Register capabilities (called on init).
	 * This doesn't add caps — it just ensures the system is ready.
	 */
	public function register() {
		// Caps are added on activation, not on every init.
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
