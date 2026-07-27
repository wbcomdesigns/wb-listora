<?php
/**
 * Custom Post Type registration.
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the listora_listing CPT.
 */
class Post_Types {

	/**
	 * Register post types.
	 */
	public function register() {
		$this->register_listing_cpt();
		$this->register_custom_statuses();
	}

	/**
	 * Register the main listing CPT.
	 */
	private function register_listing_cpt() {
		$slug = wb_listora_get_setting( 'listing_slug', 'listing' );

		$labels = array(
			'name'                  => _x( 'Listings', 'Post type general name', 'wb-listora' ),
			'singular_name'         => _x( 'Listing', 'Post type singular name', 'wb-listora' ),
			'menu_name'             => _x( 'Listora', 'Admin menu', 'wb-listora' ),
			'name_admin_bar'        => _x( 'Listing', 'Add new on admin bar', 'wb-listora' ),
			'add_new'               => __( 'Add New', 'wb-listora' ),
			'add_new_item'          => __( 'Add New Listing', 'wb-listora' ),
			'new_item'              => __( 'New Listing', 'wb-listora' ),
			'edit_item'             => __( 'Edit Listing', 'wb-listora' ),
			'view_item'             => __( 'View Listing', 'wb-listora' ),
			'all_items'             => __( 'All Listings', 'wb-listora' ),
			'search_items'          => __( 'Search Listings', 'wb-listora' ),
			'parent_item_colon'     => __( 'Parent Listing:', 'wb-listora' ),
			'not_found'             => __( 'No listings found.', 'wb-listora' ),
			'not_found_in_trash'    => __( 'No listings found in Trash.', 'wb-listora' ),
			'featured_image'        => __( 'Listing Image', 'wb-listora' ),
			'set_featured_image'    => __( 'Set listing image', 'wb-listora' ),
			'remove_featured_image' => __( 'Remove listing image', 'wb-listora' ),
			'use_featured_image'    => __( 'Use as listing image', 'wb-listora' ),
			'archives'              => __( 'Listing Archives', 'wb-listora' ),
			'attributes'            => __( 'Listing Attributes', 'wb-listora' ),
			'filter_items_list'     => __( 'Filter listings list', 'wb-listora' ),
			'items_list_navigation' => __( 'Listings list navigation', 'wb-listora' ),
			'items_list'            => __( 'Listings list', 'wb-listora' ),
			'item_published'        => __( 'Listing published.', 'wb-listora' ),
			'item_updated'          => __( 'Listing updated.', 'wb-listora' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => 'listora',
			'query_var'          => true,
			'rewrite'            => array(
				'slug'       => $slug,
				'with_front' => false,
			),
			'capability_type'    => array( 'listora_listing', 'listora_listings' ),
			'map_meta_cap'       => true,
			'has_archive'        => true,
			'hierarchical'       => false,
			'supports'           => array(
				'title',
				'editor',
				'thumbnail',
				'excerpt',
				'author',
				'comments',
				'revisions',
			),
			'show_in_rest'       => true,
			'rest_base'          => 'listings',
			'template'           => array(),
			'delete_with_user'   => false,
		);

		register_post_type( 'listora_listing', $args );
	}

	/**
	 * Register custom post statuses for listings.
	 */
	private function register_custom_statuses() {
		// Derive registration from the canonical Status_Manager::custom_statuses()
		// map so a status can never be registered without a matching transition
		// rule + label again (AUDIT-M: pending_verification had drifted out of
		// both). Each entry carries its label + the register_post_status() args.
		foreach ( \WBListora\Workflow\Status_Manager::custom_statuses() as $slug => $cfg ) {
			register_post_status(
				$slug,
				array_merge( array( 'label' => $cfg['label'] ), $cfg['register'] )
			);
		}
	}
}
