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
		register_post_status(
			'listora_rejected',
			array(
				'label'                     => _x( 'Rejected', 'post status', 'wb-listora' ),
				'public'                    => false,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: count of rejected listings */
				'label_count'               => _n_noop(
					'Rejected <span class="count">(%s)</span>',
					'Rejected <span class="count">(%s)</span>',
					'wb-listora'
				),
			)
		);

		register_post_status(
			'listora_expired',
			array(
				'label'                     => _x( 'Expired', 'post status', 'wb-listora' ),
				'public'                    => true,
				'publicly_queryable'        => true,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: count of expired listings */
				'label_count'               => _n_noop(
					'Expired <span class="count">(%s)</span>',
					'Expired <span class="count">(%s)</span>',
					'wb-listora'
				),
			)
		);

		register_post_status(
			'listora_deactivated',
			array(
				'label'                     => _x( 'Deactivated', 'post status', 'wb-listora' ),
				'public'                    => false,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: count of deactivated listings */
				'label_count'               => _n_noop(
					'Deactivated <span class="count">(%s)</span>',
					'Deactivated <span class="count">(%s)</span>',
					'wb-listora'
				),
			)
		);

		register_post_status(
			'listora_payment',
			array(
				'label'                     => _x( 'Pending Payment', 'post status', 'wb-listora' ),
				'public'                    => false,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: count of listings pending payment */
				'label_count'               => _n_noop(
					'Pending Payment <span class="count">(%s)</span>',
					'Pending Payment <span class="count">(%s)</span>',
					'wb-listora'
				),
			)
		);
	}
}
