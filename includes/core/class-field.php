<?php
/**
 * Field definition class.
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Represents a single field definition for a listing type.
 */
class Field {

	/**
	 * Field properties.
	 *
	 * @var array
	 */
	private $props;

	/**
	 * Default field properties.
	 *
	 * @var array
	 */
	private static $defaults = array(
		'key'            => '',
		'label'          => '',
		'type'           => 'text',
		'description'    => '',
		'placeholder'    => '',
		'default_value'  => '',
		'options'        => array(),
		'required'       => false,
		'searchable'     => false,
		'filterable'     => false,
		'show_in_card'   => false,
		'show_in_detail' => true,
		'show_in_rest'   => true,
		'show_in_admin'  => true,
		'schema_prop'    => '',
		'filter_type'    => '',
		'css_class'      => '',
		'width'          => '100',
		'pro_only'       => false,
		'conditional'    => null,
		'order'          => 0,
		'min'            => null,
		'max'            => null,
		'step'           => null,
	);

	/**
	 * Constructor.
	 *
	 * @param array $props Field properties.
	 */
	public function __construct( array $props ) {
		$this->props = wp_parse_args( $props, self::$defaults );
	}

	/**
	 * Create from array.
	 *
	 * @param array $data Raw field data.
	 * @return self
	 */
	public static function from_array( array $data ) {
		return new self( $data );
	}

	/**
	 * Get a property.
	 *
	 * @param string $key Property name.
	 * @return mixed
	 */
	public function get( $key ) {
		return isset( $this->props[ $key ] ) ? $this->props[ $key ] : null;
	}

	/**
	 * Magic getter.
	 *
	 * @param string $key Property name.
	 * @return mixed
	 */
	public function __get( $key ) {
		return $this->get( $key );
	}

	/**
	 * Get the field key.
	 *
	 * @return string
	 */
	public function get_key() {
		return $this->props['key'];
	}

	/**
	 * Get the meta key with plugin prefix.
	 *
	 * @return string
	 */
	public function get_meta_key() {
		return WB_LISTORA_META_PREFIX . $this->props['key'];
	}

	/**
	 * Get the field label.
	 *
	 * @return string
	 */
	public function get_label() {
		return $this->props['label'];
	}

	/**
	 * Get the field type.
	 *
	 * @return string
	 */
	public function get_type() {
		return $this->props['type'];
	}

	/**
	 * Check if field is required.
	 *
	 * @return bool
	 */
	public function is_required() {
		return (bool) $this->props['required'];
	}

	/**
	 * Check if field is searchable (included in FULLTEXT index).
	 *
	 * @return bool
	 */
	public function is_searchable() {
		return (bool) $this->props['searchable'];
	}

	/**
	 * Check if field is filterable (appears as search filter).
	 *
	 * @return bool
	 */
	public function is_filterable() {
		return (bool) $this->props['filterable'];
	}

	/**
	 * Check if field should show on listing card.
	 *
	 * @return bool
	 */
	public function show_in_card() {
		return (bool) $this->props['show_in_card'];
	}

	/**
	 * Check if condition is met for rendering.
	 *
	 * @param array $values All field values for the listing.
	 * @return bool
	 */
	public function check_conditional( array $values ) {
		$condition = $this->props['conditional'];

		if ( empty( $condition ) || ! is_array( $condition ) ) {
			return true;
		}

		$field_key = $condition['field'] ?? '';
		$operator  = $condition['operator'] ?? 'equals';
		$target    = $condition['value'] ?? '';
		$actual    = $values[ $field_key ] ?? '';

		switch ( $operator ) {
			case 'equals':
				return $actual === $target;
			case 'not_equals':
				return $actual !== $target;
			case 'contains':
				if ( is_array( $actual ) ) {
					return in_array( $target, $actual, true );
				}
				return false !== strpos( (string) $actual, (string) $target );
			case 'not_empty':
				return ! empty( $actual );
			case 'empty':
				return empty( $actual );
			case 'greater_than':
				return (float) $actual > (float) $target;
			case 'less_than':
				return (float) $actual < (float) $target;
			default:
				return true;
		}
	}

	/**
	 * Get the sanitize callback for this field type.
	 *
	 * @return callable
	 */
	public function get_sanitize_callback() {
		$type = $this->props['type'];

		$callbacks = array(
			'text'           => 'sanitize_text_field',
			'textarea'       => 'sanitize_textarea_field',
			'wysiwyg'        => 'wp_kses_post',
			'number'         => array( $this, 'sanitize_number' ),
			'url'            => 'esc_url_raw',
			'email'          => 'sanitize_email',
			'phone'          => 'sanitize_text_field',
			'select'         => 'sanitize_text_field',
			'multiselect'    => array( $this, 'sanitize_array' ),
			'checkbox'       => array( $this, 'sanitize_checkbox' ),
			'radio'          => 'sanitize_text_field',
			'date'           => 'sanitize_text_field',
			'time'           => 'sanitize_text_field',
			'datetime'       => 'sanitize_text_field',
			'price'          => array( $this, 'sanitize_json' ),
			'gallery'        => array( $this, 'sanitize_id_array' ),
			'file'           => 'absint',
			'video'          => 'esc_url_raw',
			'map_location'   => array( $this, 'sanitize_json' ),
			'business_hours' => array( $this, 'sanitize_json' ),
			'social_links'   => array( $this, 'sanitize_json' ),
			'rating'         => array( $this, 'sanitize_rating' ),
			'color'          => 'sanitize_hex_color',
		);

		/**
		 * Filter sanitize callbacks for field types.
		 *
		 * @param array $callbacks Type => callback map.
		 */
		$callbacks = apply_filters( 'wb_listora_field_sanitize_callbacks', $callbacks );

		return isset( $callbacks[ $type ] ) ? $callbacks[ $type ] : 'sanitize_text_field';
	}

	/**
	 * Get REST schema for this field.
	 *
	 * @return array
	 */
	public function get_rest_schema() {
		$type = $this->props['type'];

		$schemas = array(
			'text'           => array( 'type' => 'string' ),
			'textarea'       => array( 'type' => 'string' ),
			'wysiwyg'        => array( 'type' => 'string' ),
			'number'         => array( 'type' => 'number' ),
			'url'            => array(
				'type'   => 'string',
				'format' => 'uri',
			),
			'email'          => array(
				'type'   => 'string',
				'format' => 'email',
			),
			'phone'          => array( 'type' => 'string' ),
			'select'         => $this->get_select_schema(),
			'multiselect'    => array(
				'type'  => 'array',
				'items' => array( 'type' => 'string' ),
			),
			'checkbox'       => array( 'type' => 'boolean' ),
			'radio'          => $this->get_select_schema(),
			'date'           => array(
				'type'   => 'string',
				'format' => 'date',
			),
			'time'           => array( 'type' => 'string' ),
			'datetime'       => array(
				'type'   => 'string',
				'format' => 'date-time',
			),
			'price'          => array(
				'type'       => 'object',
				'properties' => array(
					'amount'   => array( 'type' => 'number' ),
					'currency' => array( 'type' => 'string' ),
				),
			),
			'gallery'        => array(
				'type'  => 'array',
				'items' => array( 'type' => 'integer' ),
			),
			'file'           => array( 'type' => 'integer' ),
			'video'          => array(
				'type'   => 'string',
				'format' => 'uri',
			),
			'map_location'   => array(
				'type'       => 'object',
				'properties' => array(
					'address'     => array( 'type' => 'string' ),
					'lat'         => array( 'type' => 'number' ),
					'lng'         => array( 'type' => 'number' ),
					'city'        => array( 'type' => 'string' ),
					'state'       => array( 'type' => 'string' ),
					'country'     => array( 'type' => 'string' ),
					'postal_code' => array( 'type' => 'string' ),
				),
			),
			'business_hours' => array(
				'type'  => 'array',
				'items' => array( 'type' => 'object' ),
			),
			'social_links'   => array(
				'type'  => 'array',
				'items' => array( 'type' => 'object' ),
			),
			'rating'         => array(
				'type'    => 'integer',
				'minimum' => 1,
				'maximum' => 5,
			),
			'color'          => array( 'type' => 'string' ),
		);

		return isset( $schemas[ $type ] ) ? $schemas[ $type ] : array( 'type' => 'string' );
	}

	/**
	 * Get WP meta type string for register_post_meta.
	 *
	 * @return string
	 */
	public function get_meta_type() {
		$type = $this->props['type'];

		$map = array(
			'number'      => 'number',
			'checkbox'    => 'boolean',
			'file'        => 'integer',
			'rating'      => 'integer',
			'gallery'     => 'array',
			'multiselect' => 'array',
		);

		// Complex JSON types stored as string.
		$json_types = array( 'price', 'map_location', 'business_hours', 'social_links' );
		if ( in_array( $type, $json_types, true ) ) {
			return 'object';
		}

		return isset( $map[ $type ] ) ? $map[ $type ] : 'string';
	}

	/**
	 * Convert to array.
	 *
	 * @return array
	 */
	public function to_array() {
		return $this->props;
	}

	// ─── Sanitize helpers ───

	/**
	 * @param mixed $value Number value.
	 * @return float
	 */
	public function sanitize_number( $value ) {
		return is_numeric( $value ) ? floatval( $value ) : 0;
	}

	/**
	 * @param mixed $value Array value.
	 * @return array
	 */
	public function sanitize_array( $value ) {
		if ( is_string( $value ) ) {
			$value = json_decode( $value, true );
		}
		return is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : array();
	}

	/**
	 * @param mixed $value Checkbox value.
	 * @return bool
	 */
	public function sanitize_checkbox( $value ) {
		return (bool) $value;
	}

	/**
	 * @param mixed $value JSON value.
	 * @return mixed
	 */
	public function sanitize_json( $value ) {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			return ( null !== $decoded ) ? $decoded : $value;
		}
		return $value;
	}

	/**
	 * @param mixed $value Array of IDs.
	 * @return array
	 */
	public function sanitize_id_array( $value ) {
		if ( is_string( $value ) ) {
			$value = json_decode( $value, true );
		}
		return is_array( $value ) ? array_map( 'absint', $value ) : array();
	}

	/**
	 * @param mixed $value Rating value.
	 * @return int
	 */
	public function sanitize_rating( $value ) {
		$value = absint( $value );
		return min( 5, max( 1, $value ) );
	}

	/**
	 * Get schema for select/radio fields with enum.
	 *
	 * @return array
	 */
	private function get_select_schema() {
		$options = $this->props['options'];
		if ( ! empty( $options ) ) {
			$enum = wp_list_pluck( $options, 'value' );
			return array(
				'type' => 'string',
				'enum' => $enum,
			);
		}
		return array( 'type' => 'string' );
	}
}
