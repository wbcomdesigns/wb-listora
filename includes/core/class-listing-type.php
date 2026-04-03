<?php
/**
 * Listing Type class.
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Represents a single listing type with its configuration.
 */
class Listing_Type {

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
		'schema_type'        => 'LocalBusiness',
		'icon'               => 'map-pin',
		'color'              => '#0073aa',
		'allowed_categories' => array(),
		'card_fields'        => array(),
		'card_layout'        => 'standard',
		'detail_layout'      => 'tabbed',
		'search_filters'     => array(),
		'map_enabled'        => true,
		'map_pin_icon'       => '',
		'review_enabled'     => true,
		'review_criteria'    => array(),
		'submission_enabled' => true,
		'moderation'         => 'manual',
		'expiration_days'    => 365,
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
			$this->field_groups[] = Field_Group::from_array( $group_data );
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
	 * Check if reviews are enabled for this type.
	 *
	 * @return bool
	 */
	public function is_review_enabled() {
		return (bool) $this->props['review_enabled'];
	}

	/**
	 * Check if this is a default (plugin-created) type.
	 *
	 * @return bool
	 */
	public function is_default() {
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
