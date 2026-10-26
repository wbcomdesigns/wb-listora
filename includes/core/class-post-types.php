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
		add_filter( 'post_updated_messages', array( $this, 'updated_messages' ) );
		add_filter( 'bulk_post_updated_messages', array( $this, 'bulk_messages' ), 10, 2 );
		$this->register_listing_cpt();
		$this->register_custom_statuses();
	}

	/**
	 * Post types whose save notices use their own nouns instead of "Post".
	 *
	 * @return string[]
	 */
	private function own_types() {
		/**
		 * Filter the post types that get save notices built from their labels.
		 *
		 * @since 2.1.0
		 *
		 * @param string[] $types Post type slugs.
		 */
		return (array) apply_filters( 'wb_listora_label_notice_post_types', array( 'listora_listing', 'listora_need', 'listora_plan' ) );
	}

	/**
	 * Save notices ("Listing published.") built from each type's own labels.
	 *
	 * Core falls back to the "Post" wording for a custom type that sets no entry here.
	 *
	 * @since 2.1.0
	 *
	 * @param array<string, mixed> $messages Messages keyed by post type.
	 * @return array<string, mixed>
	 */
	public function updated_messages( $messages ) {
		foreach ( $this->own_types() as $type ) {
			$object = get_post_type_object( $type );
			if ( ! $object ) {
				continue;
			}
			$name = $object->labels->singular_name;
			/* translators: %s: singular name, e.g. Listing. */
			$updated = sprintf( __( '%s updated.', 'wb-listora' ), $name );

			$messages[ $type ] = array(
				0  => '',
				1  => $updated,
				2  => __( 'Custom field updated.', 'wb-listora' ),
				3  => __( 'Custom field deleted.', 'wb-listora' ),
				4  => $updated,
				/* translators: %s: revision date. */
				5  => isset( $_GET['revision'] ) ? sprintf( __( 'Restored to the revision from %s.', 'wb-listora' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false, // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Core's own notice parameter.
				/* translators: %s: singular name. */
				6  => sprintf( __( '%s published.', 'wb-listora' ), $name ),
				/* translators: %s: singular name. */
				7  => sprintf( __( '%s saved.', 'wb-listora' ), $name ),
				/* translators: %s: singular name. */
				8  => sprintf( __( '%s submitted.', 'wb-listora' ), $name ),
				/* translators: %s: singular name. */
				9  => sprintf( __( '%s scheduled.', 'wb-listora' ), $name ),
				/* translators: %s: singular name. */
				10 => sprintf( __( '%s draft updated.', 'wb-listora' ), $name ),
			);
		}

		return $messages;
	}

	/**
	 * Bulk-action notices ("3 listings updated.") using the type's own plural.
	 *
	 * @since 2.1.0
	 *
	 * @param array<string, mixed> $messages Bulk messages keyed by post type.
	 * @param array<string, int>   $counts   Counts per action.
	 * @return array<string, mixed>
	 */
	public function bulk_messages( $messages, $counts ) {
		foreach ( $this->own_types() as $type ) {
			$object = get_post_type_object( $type );
			if ( ! $object ) {
				continue;
			}
			$one               = mb_strtolower( $object->labels->singular_name );
			$many              = mb_strtolower( $object->labels->name );
			$messages[ $type ] = array(
				/* translators: 1: count, 2: plural noun, 3: singular noun. */
				'updated'   => sprintf( _n( '%1$s %3$s updated.', '%1$s %2$s updated.', $counts['updated'], 'wb-listora' ), number_format_i18n( $counts['updated'] ), $many, $one ),
				/* translators: 1: count, 2: plural noun, 3: singular noun. */
				'locked'    => sprintf( _n( '%1$s %3$s not updated, somebody is editing it.', '%1$s %2$s not updated, somebody is editing them.', $counts['locked'], 'wb-listora' ), number_format_i18n( $counts['locked'] ), $many, $one ),
				/* translators: 1: count, 2: plural noun, 3: singular noun. */
				'deleted'   => sprintf( _n( '%1$s %3$s permanently deleted.', '%1$s %2$s permanently deleted.', $counts['deleted'], 'wb-listora' ), number_format_i18n( $counts['deleted'] ), $many, $one ),
				/* translators: 1: count, 2: plural noun, 3: singular noun. */
				'trashed'   => sprintf( _n( '%1$s %3$s moved to the Trash.', '%1$s %2$s moved to the Trash.', $counts['trashed'], 'wb-listora' ), number_format_i18n( $counts['trashed'] ), $many, $one ),
				/* translators: 1: count, 2: plural noun, 3: singular noun. */
				'untrashed' => sprintf( _n( '%1$s %3$s restored from the Trash.', '%1$s %2$s restored from the Trash.', $counts['untrashed'], 'wb-listora' ), number_format_i18n( $counts['untrashed'] ), $many, $one ),
			);
		}

		return $messages;
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
			'item_scheduled'        => __( 'Listing scheduled.', 'wb-listora' ),
			'view_items'            => __( 'View Listings', 'wb-listora' ),
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
				// No 'comments': listings have their own Reviews, and the core
				// Discussion panel would offer a second, unrelated review system.
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
