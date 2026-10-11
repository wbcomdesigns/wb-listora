<?php
/**
 * Listing Type class.
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

use WBListora\Contracts\Listing_Type_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Represents a single listing type with its configuration.
 *
 * Implements {@see Listing_Type_Interface} so Pro / extensions can consume
 * listing types via the contracts namespace and stay decoupled from this
 * concrete class name.
 */
class Listing_Type implements Listing_Type_Interface {

	/**
	 * Type slug.
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * Type properties.
	 *
	 * @var array
	 */
	private $props;

	/**
	 * Field groups for this type.
	 *
	 * @var Field_Group[]
	 */
	private $field_groups = array();

	/**
	 * Default properties.
	 *
	 * @var array
	 */
	private static $defaults = array(
		'name'               => '',
		'slug'               => '',
		// 'active', or 'draft' while the owner sets it up: a draft is kept
		// out of the submission wizard, search chips and every member-facing list.
		'status'             => 'active',
		'schema_type'        => 'LocalBusiness',
		'icon'               => 'map-pin',
		'color'              => '#0073aa',
		'allowed_categories' => array(),
		'allowed_features'   => array(),
		'card_fields'        => array(),
		'card_layout'        => 'standard',
		'detail_layout'      => 'tabbed',
		'search_filters'     => array(),
		'map_enabled'        => true,
		'map_pin_icon'       => '',
		'review_enabled'     => true,
		// 'global' follows Settings; 'on' / 'off' override it for this type.
		'reviews'            => 'global',
		// Services are noise on a Job or a Classified. Default true so no
		// existing type changes.
		'services_enabled'   => true,
		'review_criteria'    => array(),
		'submission_enabled' => true,
		// 'global' follows Settings; 'manual' / 'auto' override it for this type.
		'moderation'         => 'global',
		// 0 = lifetime (the realistic directory default — Yelp / Yellow
		// Pages / TripAdvisor model). Time-bound types (job, classified,
		// event) override this to a positive number of days. Site owners
		// running a niche directory that needs expiration can override
		// per-type from the listing-type editor.
		'expiration_days'    => 0,
		'is_default'         => false,
	);

	/**
	 * Constructor.
	 *
	 * @param string $slug  Type slug.
	 * @param array  $props Type properties.
	 * @param array  $field_groups_data Raw field group data arrays.
	 */
	public function __construct( $slug, array $props, array $field_groups_data = array() ) {
		$this->slug          = $slug;
		$this->props         = wp_parse_args( $props, self::$defaults );
		$this->props['slug'] = $slug;

		foreach ( $field_groups_data as $group_data ) {
			// Stored term meta can carry scalar entries (WP-CLI, migrations,
			// third-party writers) — a string group would fatal the array
			// type hint on every request, since the registry loads on init.
			if ( is_array( $group_data ) ) {
				$this->field_groups[] = Field_Group::from_array( $group_data );
			}
		}
	}

	/**
	 * Get the type slug.
	 *
	 * @return string
	 */
	public function get_slug() {
		return $this->slug;
	}

	/**
	 * Get the type name.
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->props['name'];
	}

	/**
	 * Get the Schema.org type.
	 *
	 * @return string
	 */
	public function get_schema_type() {
		return $this->props['schema_type'];
	}

	/**
	 * Get the type icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return $this->props['icon'];
	}

	/**
	 * Get the type brand color.
	 *
	 * @return string
	 */
	public function get_color() {
		return $this->props['color'];
	}

	/**
	 * Get a property.
	 *
	 * @param string $key Property name.
	 * @return mixed
	 */
	public function get_prop( $key ) {
		return isset( $this->props[ $key ] ) ? $this->props[ $key ] : null;
	}

	/**
	 * Get all field groups.
	 *
	 * @return Field_Group[]
	 */
	public function get_field_groups() {
		return $this->field_groups;
	}

	/**
	 * Get all fields across all groups.
	 *
	 * @return Field[]
	 */
	public function get_all_fields() {
		$fields = array();
		foreach ( $this->field_groups as $group ) {
			$fields = array_merge( $fields, $group->get_fields() );
		}
		return $fields;
	}

	/**
	 * Get a specific field by key.
	 *
	 * @param string $key Field key.
	 * @return Field|null
	 */
	public function get_field( $key ) {
		foreach ( $this->field_groups as $group ) {
			$field = $group->get_field( $key );
			if ( $field ) {
				return $field;
			}
		}
		return null;
	}

	/**
	 * Get all filterable fields across all groups.
	 *
	 * @return Field[]
	 */
	public function get_filterable_fields() {
		$fields = array();
		foreach ( $this->field_groups as $group ) {
			$fields = array_merge( $fields, $group->get_filterable_fields() );
		}
		return $fields;
	}

	/**
	 * Get all searchable fields.
	 *
	 * @return Field[]
	 */
	public function get_searchable_fields() {
		$fields = array();
		foreach ( $this->field_groups as $group ) {
			$fields = array_merge( $fields, $group->get_searchable_fields() );
		}
		return $fields;
	}

	/**
	 * Get fields that should display on listing cards.
	 *
	 * @return Field[]
	 */
	public function get_card_fields() {
		$fields = array();
		foreach ( $this->field_groups as $group ) {
			$fields = array_merge( $fields, $group->get_card_fields() );
		}
		return $fields;
	}

	/**
	 * Check if this type has a specific field.
	 *
	 * @param string $key Field key.
	 * @return bool
	 */
	public function has_field( $key ) {
		return null !== $this->get_field( $key );
	}

	/**
	 * Check if this type has an end_date field (for date-based expiration).
	 *
	 * @return bool
	 */
	public function has_end_date_field() {
		return $this->has_field( 'end_date' ) || $this->has_field( 'deadline' );
	}

	/**
	 * Get allowed category term IDs for this type.
	 *
	 * @return array
	 */
	public function get_allowed_categories() {
		return (array) $this->props['allowed_categories'];
	}

	/**
	 * Get allowed feature term IDs for this type.
	 *
	 * Empty means every feature is offered (backward compatible). A non-empty
	 * list is the allowlist search filters and the submission form honour.
	 *
	 * @since 1.6.0
	 *
	 * @return int[] Feature term IDs.
	 */
	public function get_allowed_features() {
		return (array) $this->props['allowed_features'];
	}
	/**
	 * Whether members can see and use this type (not a draft).
	 *
	 * @since 1.9.0
	 *
	 * @return bool
	 */
	public function is_active() {
		return 'draft' !== $this->props['status'];
	}


	/**
	 * Whether listings of this type take reviews: the site switch, then this
	 * type's own choice when it is not 'global'.
	 *
	 * @return bool
	 */
	public function is_review_enabled() {
		$on = ( ! function_exists( 'wb_listora_feature_enabled' ) || wb_listora_feature_enabled( 'reviews' ) )
			&& 'off' !== ( $this->props['reviews'] ?? 'global' );

		/**
		 * Filter: whether a listing type takes reviews.
		 *
		 * @since 1.9.1
		 *
		 * @param bool         $on   Resolved value.
		 * @param Listing_Type $type This type.
		 */
		return (bool) apply_filters( 'wb_listora_type_reviews_enabled', $on, $this );
	}

	/**
	 * Moderation for new submissions of this type: 'manual' or 'auto'.
	 *
	 * @since 1.9.1
	 *
	 * @return string
	 */
	public function get_moderation() {
		$value = (string) ( $this->props['moderation'] ?? 'global' );
		if ( ! in_array( $value, array( 'manual', 'auto' ), true ) ) {
			// The site setting spells auto-approve 'auto_approve'.
			$value = 'auto_approve' === wb_listora_get_setting( 'moderation', 'manual' ) ? 'auto' : 'manual';
		}

		/**
		 * Filter: moderation for a listing type ('manual' or 'auto').
		 *
		 * @since 1.9.1
		 *
		 * @param string       $value Resolved value.
		 * @param Listing_Type $type  This type.
		 */
		return 'auto' === apply_filters( 'wb_listora_type_moderation', $value, $this ) ? 'auto' : 'manual';
	}

	/**
	 * Whether listings of this type may offer services.
	 *
	 * @since 1.8.0
	 *
	 * @return bool
	 */
	public function is_services_enabled() {
		return (bool) $this->props['services_enabled'];
	}

	/**
	 * Whether this type ships with the plugin (as opposed to being created by
	 * the site owner).
	 *
	 * @since 1.5.0
	 *
	 * @return bool
	 */
	public function is_builtin() {
		return (bool) $this->props['is_default'];
	}

	/**
	 * Convert to array (for REST API / storage).
	 *
	 * @return array
	 */
	public function to_array() {
		return array_merge(
			$this->props,
			array(
				'field_groups' => array_map(
					function ( $group ) {
						return $group->to_array();
					},
					$this->field_groups
				),
			)
		);
	}
}
