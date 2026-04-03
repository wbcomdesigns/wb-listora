<?php
/**
 * Listing Type Registry.
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Registry of all listing types and their configurations.
 * Types are stored as taxonomy terms with config in term meta.
 */
class Listing_Type_Registry {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @var Listing_Type[]
	 */
	private $types = array();

	/**
	 * @var bool
	 */
	private $initialized = false;

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize — create defaults if needed, then load all types from taxonomy.
	 */
	public function init() {
		if ( $this->initialized ) {
			return;
		}
		$this->initialized = true;

		// On first activation, create default types.
		if ( get_option( 'wb_listora_needs_defaults' ) ) {
			$this->create_defaults();
			delete_option( 'wb_listora_needs_defaults' );
		}

		// One-time migration: Dashicons → Lucide icon names.
		if ( ! get_option( 'wb_listora_icons_migrated' ) ) {
			self::migrate_dashicon_to_lucide();
			update_option( 'wb_listora_icons_migrated', true );
		}

		// Load types from cache or taxonomy.
		$this->load_types();

		/**
		 * Fires after listing types are loaded.
		 *
		 * @param Listing_Type_Registry $registry
		 */
		do_action( 'wb_listora_register_listing_types', $this );
	}

	/**
	 * Load all listing types from the taxonomy.
	 */
	private function load_types() {
		// Try object cache first.
		$cached = wp_cache_get( 'wb_listora_type_registry', 'wb_listora' );
		if ( false !== $cached && is_array( $cached ) ) {
			$this->types = $cached;
			return;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'listora_listing_type',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}

		foreach ( $terms as $term ) {
			$props             = $this->get_type_props_from_term( $term );
			$field_groups_data = get_term_meta( $term->term_id, '_listora_field_groups', true );

			if ( ! is_array( $field_groups_data ) ) {
				$field_groups_data = array();
			}

			$this->types[ $term->slug ] = new Listing_Type( $term->slug, $props, $field_groups_data );
		}

		wp_cache_set( 'wb_listora_type_registry', $this->types, 'wb_listora' );
	}

	/**
	 * Extract type properties from a term and its meta.
	 *
	 * @param \WP_Term $term
	 * @return array
	 */
	private function get_type_props_from_term( $term ) {
		return array(
			'name'               => $term->name,
			'slug'               => $term->slug,
			'schema_type'        => get_term_meta( $term->term_id, '_listora_schema_type', true ) ?: 'LocalBusiness',
			'icon'               => get_term_meta( $term->term_id, '_listora_icon', true ) ?: 'map-pin',
			'color'              => get_term_meta( $term->term_id, '_listora_color', true ) ?: '#0073aa',
			'allowed_categories' => get_term_meta( $term->term_id, '_listora_allowed_categories', true ) ?: array(),
			'card_fields'        => get_term_meta( $term->term_id, '_listora_card_fields', true ) ?: array(),
			'card_layout'        => get_term_meta( $term->term_id, '_listora_card_layout', true ) ?: 'standard',
			'detail_layout'      => get_term_meta( $term->term_id, '_listora_detail_layout', true ) ?: 'tabbed',
			'search_filters'     => get_term_meta( $term->term_id, '_listora_search_filters', true ) ?: array(),
			'map_enabled'        => (bool) get_term_meta( $term->term_id, '_listora_map_enabled', true ),
			'review_enabled'     => (bool) get_term_meta( $term->term_id, '_listora_review_enabled', true ),
			'review_criteria'    => get_term_meta( $term->term_id, '_listora_review_criteria', true ) ?: array(),
			'submission_enabled' => (bool) get_term_meta( $term->term_id, '_listora_submission_enabled', true ),
			'moderation'         => get_term_meta( $term->term_id, '_listora_moderation', true ) ?: 'manual',
			'expiration_days'    => (int) get_term_meta( $term->term_id, '_listora_expiration_days', true ),
			'is_default'         => (bool) get_term_meta( $term->term_id, '_listora_is_default', true ),
		);
	}

	/**
	 * Create all default listing types from Listing_Type_Defaults.
	 */
	private function create_defaults() {
		$defaults = Listing_Type_Defaults::get_all();

		foreach ( $defaults as $slug => $data ) {
			$this->create_type_from_data( $slug, $data );
		}

		// Create default features/amenities.
		$this->create_default_features();
	}

	/**
	 * Create a single listing type from definition data.
	 *
	 * @param string $slug Type slug.
	 * @param array  $data Type definition from Listing_Type_Defaults.
	 * @param bool   $is_default Whether this is a default (plugin-created) type.
	 */
	private function create_type_from_data( $slug, array $data, $is_default = true ) {
		$props        = $data['props'];
		$field_groups = $data['field_groups'] ?? array();
		$categories   = $data['categories'] ?? array();

		// Create or update the taxonomy term.
		$term = term_exists( $slug, 'listora_listing_type' );
		if ( $term ) {
			wp_update_term(
				is_array( $term ) ? $term['term_id'] : $term,
				'listora_listing_type',
				array( 'name' => $props['name'] )
			);
		} else {
			$term = wp_insert_term( $props['name'], 'listora_listing_type', array( 'slug' => $slug ) );
		}

		if ( is_wp_error( $term ) ) {
			return $term;
		}

		$term_id = is_array( $term ) ? $term['term_id'] : $term;

		// Save type meta.
		update_term_meta( $term_id, '_listora_schema_type', $props['schema_type'] ?? 'LocalBusiness' );
		update_term_meta( $term_id, '_listora_icon', $props['icon'] ?? 'map-pin' );
		update_term_meta( $term_id, '_listora_color', $props['color'] ?? '#0073aa' );
		update_term_meta( $term_id, '_listora_is_default', $is_default );
		update_term_meta( $term_id, '_listora_map_enabled', $props['map_enabled'] ?? true );
		update_term_meta( $term_id, '_listora_review_enabled', $props['review_enabled'] ?? true );
		update_term_meta( $term_id, '_listora_submission_enabled', $props['submission_enabled'] ?? true );
		update_term_meta( $term_id, '_listora_moderation', $props['moderation'] ?? 'manual' );
		update_term_meta( $term_id, '_listora_expiration_days', $props['expiration_days'] ?? 365 );
		update_term_meta( $term_id, '_listora_card_layout', $props['card_layout'] ?? 'standard' );
		update_term_meta( $term_id, '_listora_detail_layout', $props['detail_layout'] ?? 'tabbed' );
		update_term_meta( $term_id, '_listora_review_criteria', $props['review_criteria'] ?? array() );

		// Save field groups.
		update_term_meta( $term_id, '_listora_field_groups', $field_groups );

		// Build search filters and card fields from field definitions.
		$search_filters = array();
		$card_fields    = array();

		foreach ( $field_groups as $group ) {
			if ( ! empty( $group['fields'] ) ) {
				foreach ( $group['fields'] as $field ) {
					if ( ! empty( $field['filterable'] ) ) {
						$search_filters[] = $field['key'];
					}
					if ( ! empty( $field['show_in_card'] ) ) {
						$card_fields[] = $field['key'];
					}
				}
			}
		}

		update_term_meta( $term_id, '_listora_search_filters', $search_filters );
		update_term_meta( $term_id, '_listora_card_fields', $card_fields );

		// Handle categories — accept both term IDs and names.
		$category_ids = array();
		foreach ( $categories as $cat ) {
			if ( is_int( $cat ) ) {
				$category_ids[] = $cat;
				continue;
			}
			$cat_term = term_exists( $cat, 'listora_listing_cat' );
			if ( ! $cat_term ) {
				$cat_term = wp_insert_term( $cat, 'listora_listing_cat' );
			}
			if ( ! is_wp_error( $cat_term ) ) {
				$cat_id         = is_array( $cat_term ) ? $cat_term['term_id'] : $cat_term;
				$category_ids[] = (int) $cat_id;
			}
		}

		update_term_meta( $term_id, '_listora_allowed_categories', $category_ids );

		return (int) $term_id;
	}

	/**
	 * Create default features/amenities.
	 */
	private function create_default_features() {
		$features = array(
			'wifi'         => array(
				'name' => __( 'WiFi', 'wb-listora' ),
				'icon' => 'wifi',
			),
			'parking'      => array(
				'name' => __( 'Parking', 'wb-listora' ),
				'icon' => 'car',
			),
			'wheelchair'   => array(
				'name' => __( 'Wheelchair Accessible', 'wb-listora' ),
				'icon' => 'accessibility',
			),
			'pet-friendly' => array(
				'name' => __( 'Pet Friendly', 'wb-listora' ),
				'icon' => 'paw-print',
			),
			'ac'           => array(
				'name' => __( 'Air Conditioning', 'wb-listora' ),
				'icon' => 'snowflake',
			),
			'pool'         => array(
				'name' => __( 'Swimming Pool', 'wb-listora' ),
				'icon' => 'waves',
			),
			'gym'          => array(
				'name' => __( 'Gym / Fitness', 'wb-listora' ),
				'icon' => 'heart-pulse',
			),
			'outdoor'      => array(
				'name' => __( 'Outdoor Seating', 'wb-listora' ),
				'icon' => 'trees',
			),
			'elevator'     => array(
				'name' => __( 'Elevator', 'wb-listora' ),
				'icon' => 'arrow-up',
			),
			'24-hour'      => array(
				'name' => __( '24 Hours', 'wb-listora' ),
				'icon' => 'clock',
			),
			'live-music'   => array(
				'name' => __( 'Live Music', 'wb-listora' ),
				'icon' => 'music',
			),
			'credit-cards' => array(
				'name' => __( 'Accepts Credit Cards', 'wb-listora' ),
				'icon' => 'credit-card',
			),
		);

		foreach ( $features as $slug => $data ) {
			$term = term_exists( $slug, 'listora_listing_feature' );
			if ( ! $term ) {
				$term = wp_insert_term( $data['name'], 'listora_listing_feature', array( 'slug' => $slug ) );
			}
			if ( ! is_wp_error( $term ) ) {
				$tid = is_array( $term ) ? $term['term_id'] : $term;
				update_term_meta( $tid, '_listora_icon', $data['icon'] );
			}
		}
	}

	/**
	 * One-time migration: replace Dashicon names with Lucide equivalents in term meta.
	 */
	private static function migrate_dashicon_to_lucide() {
		$icon_map = array(
			'dashicons-building'           => 'building-2',
			'dashicons-food'               => 'utensils',
			'dashicons-admin-home'         => 'home',
			'dashicons-store'              => 'bed',
			'dashicons-calendar-alt'       => 'calendar',
			'dashicons-businessman'        => 'briefcase',
			'dashicons-heart'              => 'heart-pulse',
			'dashicons-welcome-learn-more' => 'graduation-cap',
			'dashicons-location'           => 'map-pin',
			'dashicons-tag'                => 'tag',
			'dashicons-location-alt'       => 'map-pin',
			'dashicons-rss'                => 'wifi',
			'dashicons-car'                => 'car',
			'dashicons-universal-access'   => 'accessibility',
			'dashicons-pets'               => 'paw-print',
			'dashicons-cloud'              => 'snowflake',
			'dashicons-palmtree'           => 'waves',
			'dashicons-admin-site'         => 'trees',
			'dashicons-arrow-up-alt'       => 'arrow-up',
			'dashicons-clock'              => 'clock',
			'dashicons-format-audio'       => 'music',
			'dashicons-money-alt'          => 'credit-card',
		);

		$terms = get_terms(
			array(
				'taxonomy'   => 'listora_listing_type',
				'hide_empty' => false,
			)
		);

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$icon = get_term_meta( $term->term_id, '_listora_icon', true );
				if ( isset( $icon_map[ $icon ] ) ) {
					update_term_meta( $term->term_id, '_listora_icon', $icon_map[ $icon ] );
				}
			}
		}

		$features = get_terms(
			array(
				'taxonomy'   => 'listora_listing_feature',
				'hide_empty' => false,
			)
		);

		if ( ! is_wp_error( $features ) ) {
			foreach ( $features as $term ) {
				$icon = get_term_meta( $term->term_id, '_listora_icon', true );
				if ( isset( $icon_map[ $icon ] ) ) {
					update_term_meta( $term->term_id, '_listora_icon', $icon_map[ $icon ] );
				}
			}
		}
	}

	// ─── Public API ───

	/**
	 * @return Listing_Type[]
	 */
	public function get_all() {
		return $this->types;
	}

	/**
	 * @param string $slug
	 * @return Listing_Type|null
	 */
	public function get( $slug ) {
		return $this->types[ $slug ] ?? null;
	}

	/**
	 * @param string       $slug
	 * @param Listing_Type $type
	 */
	public function register( $slug, Listing_Type $type ) {
		$this->types[ $slug ] = $type;
	}

	/**
	 * Get field groups for a specific type.
	 *
	 * @param string $slug Type slug.
	 * @return Field_Group[]
	 */
	public function get_field_groups( $slug ) {
		$type = $this->get( $slug );
		return $type ? $type->get_field_groups() : array();
	}

	/**
	 * Get filterable fields for a type.
	 *
	 * @param string $slug Type slug.
	 * @return Field[]
	 */
	public function get_search_filters( $slug ) {
		$type = $this->get( $slug );
		return $type ? $type->get_filterable_fields() : array();
	}

	/**
	 * Get the listing type for a specific post.
	 *
	 * @param int $post_id Post ID.
	 * @return Listing_Type|null
	 */
	public function get_for_post( $post_id ) {
		$terms = wp_get_object_terms( $post_id, 'listora_listing_type', array( 'fields' => 'slugs' ) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return null;
		}
		return $this->get( $terms[0] );
	}

	/**
	 * Save (create or update) a listing type.
	 *
	 * @param string $slug Type slug.
	 * @param array  $data Type data with keys: props, field_groups, categories.
	 * @return int|\WP_Error Term ID on success.
	 */
	public function save_type( $slug, array $data ) {
		$result = $this->create_type_from_data( $slug, $data, false );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->flush();

		return $result;
	}

	/**
	 * Delete a listing type.
	 *
	 * @param string $slug Type slug.
	 * @return true|\WP_Error True on success.
	 */
	public function delete_type( $slug ) {
		$term = get_term_by( 'slug', $slug, 'listora_listing_type' );

		if ( ! $term ) {
			return new \WP_Error(
				'listora_type_not_found',
				__( 'Listing type not found.', 'wb-listora' ),
				array( 'status' => 404 )
			);
		}

		$term_id = $term->term_id;

		// Delete all term meta.
		$meta_keys = array(
			'_listora_schema_type',
			'_listora_icon',
			'_listora_color',
			'_listora_is_default',
			'_listora_map_enabled',
			'_listora_review_enabled',
			'_listora_review_criteria',
			'_listora_submission_enabled',
			'_listora_moderation',
			'_listora_expiration_days',
			'_listora_field_groups',
			'_listora_search_filters',
			'_listora_card_fields',
			'_listora_card_layout',
			'_listora_detail_layout',
			'_listora_allowed_categories',
		);

		foreach ( $meta_keys as $key ) {
			delete_term_meta( $term_id, $key );
		}

		// Delete the term.
		$deleted = wp_delete_term( $term_id, 'listora_listing_type' );

		if ( is_wp_error( $deleted ) ) {
			return $deleted;
		}

		$this->flush();

		return true;
	}

	/**
	 * Flush the cached registry.
	 */
	public function flush() {
		$this->types       = array();
		$this->initialized = false;
		wp_cache_delete( 'wb_listora_type_registry', 'wb_listora' );
	}
}
