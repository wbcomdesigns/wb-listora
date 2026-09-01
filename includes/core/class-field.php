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
		$this->props            = wp_parse_args( $props, self::$defaults );
		$this->props['options'] = self::normalize_options( $this->props['options'] );
	}

	/**
	 * Normalize field options to the canonical { value, label } shape.
	 *
	 * The admin Type Editor (pre-1.4.1) persisted owner-added options as plain
	 * strings inside `_listora_field_groups` term meta. Readers assume the
	 * array shape and fatal on PHP 8 ("Cannot access offset of type string on
	 * string"). Normalizing here heals every already-affected site on read —
	 * no data migration needed — and keeps third-party REST writers safe.
	 *
	 * @since 1.4.1
	 *
	 * @param mixed $options Raw options as stored.
	 * @return array<int, array{value: string, label: string}>
	 */
	public static function normalize_options( $options ) {
		if ( ! is_array( $options ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $options as $opt ) {
			if ( is_array( $opt ) ) {
				$label = isset( $opt['label'] ) ? (string) $opt['label'] : '';
				$value = isset( $opt['value'] ) ? (string) $opt['value'] : '';
				if ( '' === $value && '' === $label ) {
					continue;
				}
				if ( '' === $value ) {
					$value = sanitize_title( $label );
				}
				if ( '' === $label ) {
					$label = $value;
				}
				$normalized[] = array(
					'value' => $value,
					'label' => $label,
				);
				continue;
			}

			if ( is_scalar( $opt ) ) {
				$label = trim( (string) $opt );
				if ( '' === $label ) {
					continue;
				}
				// Matches the Type Editor's toSlug() so values line up with
				// entries the editor already saved in object shape.
				$normalized[] = array(
					'value' => sanitize_title( $label ),
					'label' => $label,
				);
			}
		}

		return $normalized;
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
			'price'          => array( $this, 'sanitize_price' ),
			'gallery'        => array( $this, 'sanitize_id_array' ),
			'file'           => array( $this, 'sanitize_attachment_id' ),
			'video'          => 'esc_url_raw',
			'map_location'   => array( $this, 'sanitize_map_location' ),
			'business_hours' => array( $this, 'sanitize_json' ),
			'social_links'   => array( $this, 'sanitize_social_links' ),
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
	 * Sanitize a `price` field into the canonical `[ amount, currency ]` shape.
	 *
	 * The submission form and the wp-admin fields metabox both render price as a
	 * single `<input type="number">`, so the value arriving here is a bare scalar
	 * ("275"). Routing that through sanitize_json() decoded it to int 275, failed
	 * the `is_array()` test, and returned `array()` — the amount was dropped on
	 * every save and the edit form then rendered `value="Array"` with a PHP
	 * "Array to string conversion" warning (Basecamp 10171941201).
	 *
	 * Scalars are therefore promoted to the documented object shape (see the
	 * `price` REST schema above) rather than discarded. Array input is accepted
	 * as-is when it carries an `amount`, so REST clients and the demo packs keep
	 * round-tripping unchanged. Anything else sanitizes to an empty array — a
	 * cleared price — which every reader already treats as "no price".
	 *
	 * @param mixed $value Raw price value (scalar amount or `[ amount, currency ]`).
	 * @return array<string,mixed> Canonical price array, or empty array when cleared.
	 */
	public function sanitize_price( $value ) {
		// A JSON-encoded object from a REST client — decode before inspecting.
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( is_array( $decoded ) ) {
				$value = $decoded;
			}
		}

		if ( is_array( $value ) ) {
			if ( ! isset( $value['amount'] ) || ! is_numeric( $value['amount'] ) ) {
				return array();
			}

			return array(
				'amount'   => (float) $value['amount'],
				'currency' => isset( $value['currency'] )
					? sanitize_text_field( (string) $value['currency'] )
					: (string) wb_listora_get_setting( 'currency', 'USD' ),
			);
		}

		// Bare amount from the number input. Non-numeric (including the empty
		// string the form posts when the user clears the field) means no price.
		if ( ! is_numeric( $value ) ) {
			return array();
		}

		return array(
			'amount'   => (float) $value,
			'currency' => (string) wb_listora_get_setting( 'currency', 'USD' ),
		);
	}

	/**
	 * @param mixed $value JSON value.
	 * @return mixed
	 */
	public function sanitize_json( $value ) {
		// price / map_location / business_hours are all array-shaped. Never
		// persist a scalar: returning the raw string on decode failure (the
		// pre-1.4.1 behavior) planted exactly the stored-shape drift that
		// caused the field-options fatal, one meta key over.
		if ( is_array( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			return is_array( $decoded ) ? $decoded : array();
		}
		return array();
	}

	/**
	 * Sanitize map_location: composite {address, lat, lng, city, state, country, postal_code}.
	 *
	 * The child keys are declared in get_rest_schema() and posted by the
	 * submission renderer as a nested array (`meta_address[address]`,
	 * `[lat]`, …). Sanitizing them here means every writer — the frontend
	 * submission controller, the wp-admin field metabox, WP-CLI, REST — gets
	 * the same treatment through the field's own sanitize callback, instead
	 * of each call site re-spelling the shape. A call site that spelled it
	 * differently is exactly what silently erased listing locations on every
	 * wp-admin save.
	 *
	 * All seven keys are always returned so readers can index them without
	 * an isset() dance; absent values are the empty string, which is the
	 * shape already stored on existing listings.
	 *
	 * @param mixed $value Raw value (nested array or JSON string).
	 * @return array<string,mixed>
	 */
	public function sanitize_map_location( $value ) {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			$value   = is_array( $decoded ) ? $decoded : array();
		}
		if ( ! is_array( $value ) ) {
			return array();
		}

		$clean = array();

		foreach ( array( 'address', 'city', 'state', 'country', 'postal_code' ) as $text_key ) {
			$raw                = $value[ $text_key ] ?? '';
			$clean[ $text_key ] = is_scalar( $raw ) ? sanitize_text_field( (string) $raw ) : '';
		}

		// Coordinates are numbers in the REST schema. Keep the empty string
		// when unset rather than coercing to 0.0 — 0,0 is a real point in the
		// Atlantic and would place unlocated listings there on the map.
		foreach ( array( 'lat', 'lng' ) as $coord_key ) {
			$raw                 = $value[ $coord_key ] ?? '';
			$clean[ $coord_key ] = ( is_scalar( $raw ) && '' !== $raw && is_numeric( $raw ) ) ? (float) $raw : '';
		}

		return $clean;
	}

	/**
	 * Sanitize social_links: flat associative array of {platform_slug => url}.
	 *
	 * Accepts a whitelist of common platforms, each value sanitized via
	 * esc_url_raw, empty entries stripped. Unknown keys are dropped so
	 * users can't save arbitrary key/value pairs as listing meta.
	 *
	 * @param mixed $value Raw value (array or JSON string).
	 * @return array<string,string>
	 */
	public function sanitize_social_links( $value ) {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			$value   = ( null !== $decoded ) ? $decoded : array();
		}
		if ( ! is_array( $value ) ) {
			return array();
		}

		$platforms = self::social_link_platforms();
		$out       = array();
		foreach ( $platforms as $slug => $_label ) {
			if ( empty( $value[ $slug ] ) || ! is_string( $value[ $slug ] ) ) {
				continue;
			}
			$url = esc_url_raw( trim( $value[ $slug ] ) );
			if ( '' !== $url ) {
				$out[ $slug ] = $url;
			}
		}

		return $out;
	}

	/**
	 * Every social platform the code offers, before the owner's selection.
	 *
	 * This is the CATALOGUE. The settings screen renders checkboxes from it, so
	 * it must keep listing platforms the owner has switched off — otherwise
	 * disabling TikTok would remove its own checkbox and there would be no way
	 * back. Use social_link_platforms() for anything that renders to a visitor.
	 *
	 * @since 1.5.0
	 *
	 * @return array<string,string> slug => label
	 */
	public static function social_link_platforms_all() {
		$platforms = array(
			'facebook'  => __( 'Facebook', 'wb-listora' ),
			'twitter'   => __( 'Twitter / X', 'wb-listora' ),
			'instagram' => __( 'Instagram', 'wb-listora' ),
			'linkedin'  => __( 'LinkedIn', 'wb-listora' ),
			'youtube'   => __( 'YouTube', 'wb-listora' ),
			'tiktok'    => __( 'TikTok', 'wb-listora' ),
			'pinterest' => __( 'Pinterest', 'wb-listora' ),
		);

		/**
		 * Filters the social platforms offered on the Social Links field.
		 *
		 * This list drives the submission form, the detail sidebar, the dashboard
		 * profile tab, the schema.org `sameAs` output, and the sanitizer whitelist,
		 * so removing a platform here retires it everywhere at once. Values already
		 * stored for a removed platform stop rendering but are not deleted.
		 *
		 * Applied to the catalogue, so a platform added here is also offered to
		 * the site owner as a checkbox rather than being force-enabled.
		 *
		 * @since 1.4.2
		 *
		 * @param array<string,string> $platforms Map of platform slug => display label.
		 */
		$platforms = apply_filters( 'wb_listora_social_link_platforms', $platforms );

		return is_array( $platforms ) ? $platforms : array();
	}

	/**
	 * Social platforms actually shown to visitors, after the owner's selection.
	 *
	 * Single source of truth for the sanitizer, submission renderer, detail
	 * sidebar, dashboard profile tab and schema.org `sameAs`.
	 *
	 * The `social_platforms` setting is an allowlist of slugs saved from
	 * Settings → Submissions. Three states, deliberately distinct:
	 *
	 *   absent      never configured  -> every platform, so a site upgrading
	 *                                    into this feature loses nothing
	 *   empty array owner unticked all -> no platforms, which is a legitimate
	 *                                    way to retire the field entirely
	 *   populated   the owner's choice -> exactly those, in catalogue order
	 *
	 * Collapsing the middle case into the first would make "untick everything"
	 * silently mean "show everything", which is the opposite of the instruction.
	 * Unknown slugs are dropped rather than trusted, so a stale settings row
	 * cannot resurrect a platform the code no longer offers.
	 *
	 * @return array<string,string> slug => label
	 */
	public static function social_link_platforms() {
		$platforms = self::social_link_platforms_all();

		$enabled = function_exists( 'wb_listora_get_setting' )
			? wb_listora_get_setting( 'social_platforms', null )
			: null;

		if ( is_array( $enabled ) ) {
			$platforms = array_intersect_key( $platforms, array_flip( $enabled ) );
		}

		return $platforms;
	}

	/**
	 * @param mixed $value Array of IDs.
	 * @return array
	 */
	public function sanitize_id_array( $value ) {
		if ( is_string( $value ) ) {
			$value = json_decode( $value, true );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		// absint alone made this "any integer is an attachment ID I may store",
		// so a type-builder gallery field could hold another member's private
		// upload. `/submit` has filtered attachments by ownership since 1.7.0;
		// this is a second write path into the same meta and it was not.
		if ( function_exists( 'wb_listora_filter_attachable_ids' ) ) {
			return wb_listora_filter_attachable_ids( $value );
		}

		return array_map( 'absint', $value );
	}

	/**
	 * A single attachment ID the current user is entitled to attach.
	 *
	 * Drops rather than errors, because a sanitiser has no way to refuse — the
	 * REST routes reject outright, which is where a caller can be told. Storing
	 * 0 is the safe outcome either way: a missing file is recoverable, someone
	 * else's ID document showing on a listing is not.
	 *
	 * @since 1.7.0
	 *
	 * @param mixed $value Attachment ID.
	 * @return int
	 */
	public function sanitize_attachment_id( $value ) {
		$id = absint( $value );

		if ( $id <= 0 ) {
			return 0;
		}

		if ( function_exists( 'wb_listora_user_can_attach' ) && ! wb_listora_user_can_attach( $id ) ) {
			return 0;
		}

		return $id;
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
