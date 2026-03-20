<?php
/**
 * Taxonomy registration.
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Registers all listing taxonomies.
 */
class Taxonomies {

	/**
	 * Register all taxonomies.
	 */
	public function register() {
		$this->register_listing_type();
		$this->register_listing_category();
		$this->register_listing_tag();
		$this->register_listing_location();
		$this->register_listing_feature();
	}

	/**
	 * Listing Type taxonomy — determines the type (Restaurant, Hotel, etc.)
	 */
	private function register_listing_type() {
		$labels = array(
			'name'          => _x( 'Listing Types', 'taxonomy general name', 'wb-listora' ),
			'singular_name' => _x( 'Listing Type', 'taxonomy singular name', 'wb-listora' ),
			'search_items'  => __( 'Search Listing Types', 'wb-listora' ),
			'all_items'     => __( 'All Listing Types', 'wb-listora' ),
			'edit_item'     => __( 'Edit Listing Type', 'wb-listora' ),
			'update_item'   => __( 'Update Listing Type', 'wb-listora' ),
			'add_new_item'  => __( 'Add New Listing Type', 'wb-listora' ),
			'new_item_name' => __( 'New Listing Type Name', 'wb-listora' ),
			'menu_name'     => __( 'Listing Types', 'wb-listora' ),
			'not_found'     => __( 'No listing types found.', 'wb-listora' ),
			'back_to_items' => __( '&larr; Back to Listing Types', 'wb-listora' ),
		);

		register_taxonomy(
			'listora_listing_type',
			'listora_listing',
			array(
				'hierarchical'      => false,
				'labels'            => $labels,
				'public'            => true,
				'show_ui'           => false, // Managed via custom admin page.
				'show_in_menu'      => false,
				'show_in_rest'      => true,
				'rest_base'         => 'listing-types',
				'show_admin_column' => true,
				'query_var'         => true,
				'rewrite'           => array(
					'slug'       => 'listing-type',
					'with_front' => false,
				),
				'capabilities'      => array(
					'manage_terms' => 'manage_listora_types',
					'edit_terms'   => 'manage_listora_types',
					'delete_terms' => 'manage_listora_types',
					'assign_terms' => 'edit_listora_listing',
				),
			)
		);
	}

	/**
	 * Listing Category taxonomy — hierarchical categories scoped per type.
	 */
	private function register_listing_category() {
		$slug = wb_listora_get_setting( 'category_slug', 'listing-category' );

		$labels = array(
			'name'              => _x( 'Categories', 'taxonomy general name', 'wb-listora' ),
			'singular_name'     => _x( 'Category', 'taxonomy singular name', 'wb-listora' ),
			'search_items'      => __( 'Search Categories', 'wb-listora' ),
			'all_items'         => __( 'All Categories', 'wb-listora' ),
			'parent_item'       => __( 'Parent Category', 'wb-listora' ),
			'parent_item_colon' => __( 'Parent Category:', 'wb-listora' ),
			'edit_item'         => __( 'Edit Category', 'wb-listora' ),
			'update_item'       => __( 'Update Category', 'wb-listora' ),
			'add_new_item'      => __( 'Add New Category', 'wb-listora' ),
			'new_item_name'     => __( 'New Category Name', 'wb-listora' ),
			'menu_name'         => __( 'Categories', 'wb-listora' ),
			'not_found'         => __( 'No categories found.', 'wb-listora' ),
			'back_to_items'     => __( '&larr; Back to Categories', 'wb-listora' ),
		);

		register_taxonomy(
			'listora_listing_cat',
			'listora_listing',
			array(
				'hierarchical'      => true,
				'labels'            => $labels,
				'public'            => true,
				'show_ui'           => true,
				'show_in_menu'      => true,
				'show_in_rest'      => true,
				'rest_base'         => 'listing-categories',
				'show_admin_column' => true,
				'query_var'         => true,
				'rewrite'           => array(
					'slug'         => $slug,
					'with_front'   => false,
					'hierarchical' => true,
				),
				'capabilities'      => array(
					'manage_terms' => 'manage_listora_types',
					'edit_terms'   => 'manage_listora_types',
					'delete_terms' => 'manage_listora_types',
					'assign_terms' => 'edit_listora_listing',
				),
			)
		);
	}

	/**
	 * Listing Tag taxonomy — free-form tags.
	 */
	private function register_listing_tag() {
		$slug = wb_listora_get_setting( 'tag_slug', 'listing-tag' );

		$labels = array(
			'name'                       => _x( 'Tags', 'taxonomy general name', 'wb-listora' ),
			'singular_name'              => _x( 'Tag', 'taxonomy singular name', 'wb-listora' ),
			'search_items'               => __( 'Search Tags', 'wb-listora' ),
			'all_items'                  => __( 'All Tags', 'wb-listora' ),
			'edit_item'                  => __( 'Edit Tag', 'wb-listora' ),
			'update_item'                => __( 'Update Tag', 'wb-listora' ),
			'add_new_item'               => __( 'Add New Tag', 'wb-listora' ),
			'new_item_name'              => __( 'New Tag Name', 'wb-listora' ),
			'menu_name'                  => __( 'Tags', 'wb-listora' ),
			'not_found'                  => __( 'No tags found.', 'wb-listora' ),
			'back_to_items'              => __( '&larr; Back to Tags', 'wb-listora' ),
			'popular_items'              => __( 'Popular Tags', 'wb-listora' ),
			'separate_items_with_commas' => __( 'Separate tags with commas', 'wb-listora' ),
			'add_or_remove_items'        => __( 'Add or remove tags', 'wb-listora' ),
			'choose_from_most_used'      => __( 'Choose from the most used tags', 'wb-listora' ),
		);

		register_taxonomy(
			'listora_listing_tag',
			'listora_listing',
			array(
				'hierarchical'      => false,
				'labels'            => $labels,
				'public'            => true,
				'show_ui'           => true,
				'show_in_menu'      => true,
				'show_in_rest'      => true,
				'rest_base'         => 'listing-tags',
				'show_admin_column' => false,
				'query_var'         => true,
				'rewrite'           => array(
					'slug'       => $slug,
					'with_front' => false,
				),
				'capabilities'      => array(
					'manage_terms' => 'manage_listora_types',
					'edit_terms'   => 'edit_listora_listing',
					'delete_terms' => 'manage_listora_types',
					'assign_terms' => 'edit_listora_listing',
				),
			)
		);
	}

	/**
	 * Listing Location taxonomy — hierarchical (Country > State > City).
	 */
	private function register_listing_location() {
		$slug = wb_listora_get_setting( 'location_slug', 'listing-location' );

		$labels = array(
			'name'              => _x( 'Locations', 'taxonomy general name', 'wb-listora' ),
			'singular_name'     => _x( 'Location', 'taxonomy singular name', 'wb-listora' ),
			'search_items'      => __( 'Search Locations', 'wb-listora' ),
			'all_items'         => __( 'All Locations', 'wb-listora' ),
			'parent_item'       => __( 'Parent Location', 'wb-listora' ),
			'parent_item_colon' => __( 'Parent Location:', 'wb-listora' ),
			'edit_item'         => __( 'Edit Location', 'wb-listora' ),
			'update_item'       => __( 'Update Location', 'wb-listora' ),
			'add_new_item'      => __( 'Add New Location', 'wb-listora' ),
			'new_item_name'     => __( 'New Location Name', 'wb-listora' ),
			'menu_name'         => __( 'Locations', 'wb-listora' ),
			'not_found'         => __( 'No locations found.', 'wb-listora' ),
			'back_to_items'     => __( '&larr; Back to Locations', 'wb-listora' ),
		);

		register_taxonomy(
			'listora_listing_location',
			'listora_listing',
			array(
				'hierarchical'      => true,
				'labels'            => $labels,
				'public'            => true,
				'show_ui'           => true,
				'show_in_menu'      => true,
				'show_in_rest'      => true,
				'rest_base'         => 'listing-locations',
				'show_admin_column' => false,
				'query_var'         => true,
				'rewrite'           => array(
					'slug'         => $slug,
					'with_front'   => false,
					'hierarchical' => true,
				),
				'capabilities'      => array(
					'manage_terms' => 'manage_listora_types',
					'edit_terms'   => 'manage_listora_types',
					'delete_terms' => 'manage_listora_types',
					'assign_terms' => 'edit_listora_listing',
				),
			)
		);
	}

	/**
	 * Listing Feature taxonomy — amenities/features (WiFi, Parking, Pool).
	 */
	private function register_listing_feature() {
		$slug = wb_listora_get_setting( 'feature_slug', 'listing-feature' );

		$labels = array(
			'name'                       => _x( 'Features', 'taxonomy general name', 'wb-listora' ),
			'singular_name'              => _x( 'Feature', 'taxonomy singular name', 'wb-listora' ),
			'search_items'               => __( 'Search Features', 'wb-listora' ),
			'all_items'                  => __( 'All Features', 'wb-listora' ),
			'edit_item'                  => __( 'Edit Feature', 'wb-listora' ),
			'update_item'                => __( 'Update Feature', 'wb-listora' ),
			'add_new_item'               => __( 'Add New Feature', 'wb-listora' ),
			'new_item_name'              => __( 'New Feature Name', 'wb-listora' ),
			'menu_name'                  => __( 'Features', 'wb-listora' ),
			'not_found'                  => __( 'No features found.', 'wb-listora' ),
			'back_to_items'              => __( '&larr; Back to Features', 'wb-listora' ),
			'popular_items'              => __( 'Popular Features', 'wb-listora' ),
			'separate_items_with_commas' => __( 'Separate features with commas', 'wb-listora' ),
			'add_or_remove_items'        => __( 'Add or remove features', 'wb-listora' ),
			'choose_from_most_used'      => __( 'Choose from the most used features', 'wb-listora' ),
		);

		register_taxonomy(
			'listora_listing_feature',
			'listora_listing',
			array(
				'hierarchical'      => false,
				'labels'            => $labels,
				'public'            => true,
				'show_ui'           => true,
				'show_in_menu'      => true,
				'show_in_rest'      => true,
				'rest_base'         => 'listing-features',
				'show_admin_column' => false,
				'query_var'         => true,
				'rewrite'           => array(
					'slug'       => $slug,
					'with_front' => false,
				),
				'capabilities'      => array(
					'manage_terms' => 'manage_listora_types',
					'edit_terms'   => 'manage_listora_types',
					'delete_terms' => 'manage_listora_types',
					'assign_terms' => 'edit_listora_listing',
				),
			)
		);
	}
}
