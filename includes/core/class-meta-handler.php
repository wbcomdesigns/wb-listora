<?php
/**
 * Meta Handler — registers post meta for all listing type fields.
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Handles registration of all listing meta fields with WordPress and REST API.
 */
class Meta_Handler {

	/**
	 * Register meta for all listing type fields.
	 *
	 * Called on init after Listing_Type_Registry has populated types.
	 */
	public function register_meta() {
		$registry = Listing_Type_Registry::instance();
		$types    = $registry->get_all();

		// Track registered meta keys to avoid duplicates (shared keys across types).
		$registered = array();

		foreach ( $types as $type ) {
			$field_groups = $type->get_field_groups();

			foreach ( $field_groups as $group ) {
				foreach ( $group->get_fields() as $field ) {
					$meta_key = $field->get_meta_key();

					// Skip if already registered (shared key across types).
					if ( isset( $registered[ $meta_key ] ) ) {
						continue;
					}

					$this->register_field_meta( $field );
					$registered[ $meta_key ] = true;
				}
			}
		}

		// Register common meta fields that exist on all listings.
		$this->register_common_meta();
	}

	/**
	 * Register a single field as post meta.
	 *
	 * @param Field $field The field definition.
	 */
	private function register_field_meta( Field $field ) {
		$meta_key    = $field->get_meta_key();
		$meta_type   = $field->get_meta_type();
		$rest_schema = $field->get_rest_schema();

		$args = array(
			'type'              => $meta_type,
			'single'            => true,
			'sanitize_callback' => $field->get_sanitize_callback(),
			'auth_callback'     => function () {
				return current_user_can( 'edit_listora_listings' );
			},
			'show_in_rest'      => $field->get( 'show_in_rest' ),
		);

		// For complex types (object, array), provide REST schema.
		if ( in_array( $meta_type, array( 'object', 'array' ), true ) && $field->get( 'show_in_rest' ) ) {
			$args['show_in_rest'] = array(
				'schema' => $rest_schema,
			);

			// Objects need prepare_callback for proper serialization.
			if ( 'object' === $meta_type ) {
				$args['show_in_rest']['prepare_callback'] = function ( $value ) {
					if ( is_string( $value ) ) {
						$decoded = json_decode( $value, true );
						return is_array( $decoded ) ? $decoded : $value;
					}
					return $value;
				};
			}
		}

		// For array types (gallery, multiselect).
		if ( 'array' === $meta_type && $field->get( 'show_in_rest' ) ) {
			$args['show_in_rest'] = array(
				'schema' => $rest_schema,
			);
		}

		register_post_meta( 'listora_listing', $meta_key, $args );
	}

	/**
	 * Register common meta fields present on all listings regardless of type.
	 */
	private function register_common_meta() {
		// Featured flag (admin-set or plan-driven).
		register_post_meta(
			'listora_listing',
			'_listora_is_featured',
			array(
				'type'              => 'boolean',
				'single'            => true,
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => true,
				'auth_callback'     => function () {
					return current_user_can( 'edit_others_listora_listings' );
				},
			)
		);

		// Verified flag.
		register_post_meta(
			'listora_listing',
			'_listora_is_verified',
			array(
				'type'              => 'boolean',
				'single'            => true,
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => true,
				'auth_callback'     => function () {
					return current_user_can( 'manage_listora_claims' );
				},
			)
		);

		// Claimed flag.
		register_post_meta(
			'listora_listing',
			'_listora_is_claimed',
			array(
				'type'              => 'boolean',
				'single'            => true,
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => true,
				'auth_callback'     => function () {
					return current_user_can( 'manage_listora_claims' );
				},
			)
		);

		// Expiration date.
		register_post_meta(
			'listora_listing',
			'_listora_expiration_date',
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'auth_callback'     => function () {
					return current_user_can( 'edit_others_listora_listings' );
				},
			)
		);

		// Plan ID (Pro).
		register_post_meta(
			'listora_listing',
			'_listora_plan_id',
			array(
				'type'              => 'integer',
				'single'            => true,
				'default'           => 0,
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
				'auth_callback'     => function () {
					return current_user_can( 'edit_listora_listings' );
				},
			)
		);

		// Rejection reason.
		register_post_meta(
			'listora_listing',
			'_listora_rejection_reason',
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'sanitize_callback' => 'sanitize_textarea_field',
				'show_in_rest'      => true,
				'auth_callback'     => function () {
					return current_user_can( 'edit_others_listora_listings' );
				},
			)
		);

		// Demo content marker.
		register_post_meta(
			'listora_listing',
			'_listora_demo_content',
			array(
				'type'              => 'boolean',
				'single'            => true,
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => false,
			)
		);

		// Timezone (for business hours / events).
		register_post_meta(
			'listora_listing',
			'_listora_timezone',
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'auth_callback'     => function () {
					return current_user_can( 'edit_listora_listings' );
				},
			)
		);
	}

	/**
	 * Get the value of a listing meta field.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Field key (without prefix).
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get_value( $post_id, $key, $default = '' ) {
		$meta_key = WB_LISTORA_META_PREFIX . $key;
		$value    = get_post_meta( $post_id, $meta_key, true );
		return ( '' !== $value && false !== $value ) ? $value : $default;
	}

	/**
	 * Set the value of a listing meta field.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Field key (without prefix).
	 * @param mixed  $value   Value to set.
	 * @return bool
	 */
	public static function set_value( $post_id, $key, $value ) {
		$meta_key = WB_LISTORA_META_PREFIX . $key;

		// Let WordPress handle serialization natively.
		// Arrays and objects are stored via PHP serialize() by update_post_meta.
		// Do NOT json_encode — WordPress handles it.
		return update_post_meta( $post_id, $meta_key, $value );
	}

	/**
	 * Get all meta values for a listing, keyed by field key (without prefix).
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function get_all_values( $post_id ) {
		$values = array();
		$prefix = WB_LISTORA_META_PREFIX;

		// Use get_post_meta with single=true for each known key.
		// This lets WordPress handle unserialization natively.
		$all_meta = get_post_meta( $post_id );

		foreach ( $all_meta as $meta_key => $meta_values ) {
			if ( 0 === strpos( $meta_key, $prefix ) ) {
				$key = substr( $meta_key, strlen( $prefix ) );
				// get_post_meta returns unserialized value when accessed with single=true.
				$values[ $key ] = get_post_meta( $post_id, $meta_key, true );
			}
		}

		return $values;
	}
}
