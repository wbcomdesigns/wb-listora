<?php
/**
 * REST Listing Types Controller.
 *
 * @package WBListora\REST
 */

namespace WBListora\REST;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Handles listing type endpoints — type definitions, fields, categories.
 */
class Listing_Types_Controller extends WP_REST_Controller {

	protected $namespace = WB_LISTORA_REST_NAMESPACE;
	protected $rest_base = 'listing-types';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// GET /listing-types — all types.
		// POST /listing-types — create type.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => $this->get_type_create_args(),
				),
			)
		);

		// GET /listing-types/{slug} — single type.
		// PUT/PATCH /listing-types/{slug} — update type.
		// DELETE /listing-types/{slug} — delete type.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<slug>[a-z0-9-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'slug' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_title',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_type_update_args(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
					'args'                => array(
						'slug' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_title',
						),
					),
				),
			)
		);

		// GET /listing-types/{slug}/fields — fields for a type.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<slug>[a-z0-9-]+)/fields',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_fields' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'slug' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_title',
						),
					),
				),
			)
		);

		// GET /listing-types/{slug}/categories — categories scoped to type.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<slug>[a-z0-9-]+)/categories',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_categories' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'slug' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_title',
						),
					),
				),
			)
		);
	}

	/**
	 * Get all listing types.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$registry = \WBListora\Core\Listing_Type_Registry::instance();
		$types    = $registry->get_all();

		$data = array();
		foreach ( $types as $type ) {
			$data[] = $this->prepare_type_response( $type );
		}

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Get a single listing type.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ) {
		$slug     = $request->get_param( 'slug' );
		$registry = \WBListora\Core\Listing_Type_Registry::instance();
		$type     = $registry->get( $slug );

		if ( ! $type ) {
			return new \WP_Error( 'listora_type_not_found', __( 'Listing type not found.', 'wb-listora' ), array( 'status' => 404 ) );
		}

		$data = $this->prepare_type_response( $type, true );

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Get fields for a listing type.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function get_fields( $request ) {
		$slug     = $request->get_param( 'slug' );
		$registry = \WBListora\Core\Listing_Type_Registry::instance();
		$type     = $registry->get( $slug );

		if ( ! $type ) {
			return new \WP_Error( 'listora_type_not_found', __( 'Listing type not found.', 'wb-listora' ), array( 'status' => 404 ) );
		}

		$groups  = array();
		$filters = array();

		foreach ( $type->get_field_groups() as $group ) {
			$group_data = array(
				'key'         => $group->get_key(),
				'label'       => $group->get_label(),
				'description' => $group->get_description(),
				'icon'        => $group->get_icon(),
				'order'       => $group->get_order(),
				'fields'      => array(),
			);

			foreach ( $group->get_fields() as $field ) {
				$field_data                = $field->to_array();
				$field_data['meta_key']    = $field->get_meta_key();
				$field_data['rest_schema'] = $field->get_rest_schema();
				$group_data['fields'][]    = $field_data;

				if ( $field->is_filterable() ) {
					$filters[] = array(
						'key'         => $field->get_key(),
						'label'       => $field->get_label(),
						'type'        => $field->get_type(),
						'options'     => $field->get( 'options' ) ?: array(),
						'filter_type' => $field->get( 'filter_type' ) ?: $this->default_filter_type( $field->get_type() ),
					);
				}
			}

			$groups[] = $group_data;
		}

		return new WP_REST_Response(
			array(
				'field_groups' => $groups,
				'filters'      => $filters,
			),
			200
		);
	}

	/**
	 * Get categories scoped to a listing type.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function get_categories( $request ) {
		$slug     = $request->get_param( 'slug' );
		$registry = \WBListora\Core\Listing_Type_Registry::instance();
		$type     = $registry->get( $slug );

		if ( ! $type ) {
			return new \WP_Error( 'listora_type_not_found', __( 'Listing type not found.', 'wb-listora' ), array( 'status' => 404 ) );
		}

		$allowed_ids = $type->get_allowed_categories();
		$categories  = array();

		if ( ! empty( $allowed_ids ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => 'listora_listing_cat',
					'include'    => $allowed_ids,
					'hide_empty' => false,
					'orderby'    => 'name',
				)
			);

			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$categories[] = array(
						'id'     => $term->term_id,
						'name'   => $term->name,
						'slug'   => $term->slug,
						'parent' => $term->parent,
						'count'  => $term->count,
						'icon'   => get_term_meta( $term->term_id, '_listora_icon', true ),
						'image'  => get_term_meta( $term->term_id, '_listora_image', true ),
						'color'  => get_term_meta( $term->term_id, '_listora_color', true ),
					);
				}
			}
		}

		return new WP_REST_Response( $categories, 200 );
	}

	// ─── CRUD Callbacks ───

	/**
	 * Check if user can create listing types.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function create_item_permissions_check( $request ) {
		return current_user_can( 'manage_listora_types' );
	}

	/**
	 * Check if user can update listing types.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function update_item_permissions_check( $request ) {
		return current_user_can( 'manage_listora_types' );
	}

	/**
	 * Check if user can delete listing types.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function delete_item_permissions_check( $request ) {
		return current_user_can( 'manage_listora_types' );
	}

	/**
	 * Create a new listing type.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$name = sanitize_text_field( $request->get_param( 'name' ) );
		$slug = $request->get_param( 'slug' )
			? sanitize_title( $request->get_param( 'slug' ) )
			: sanitize_title( $name );

		$registry = \WBListora\Core\Listing_Type_Registry::instance();

		if ( $registry->get( $slug ) ) {
			return new WP_Error(
				'listora_type_exists',
				__( 'A type with this slug already exists.', 'wb-listora' ),
				array( 'status' => 409 )
			);
		}

		$data   = $this->prepare_type_data_from_request( $request, $name );
		$result = $registry->save_type( $slug, $data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Reload registry and return the new type.
		$registry->flush();
		$registry->init();
		$type = $registry->get( $slug );

		if ( ! $type ) {
			return new WP_Error(
				'listora_type_create_failed',
				__( 'Failed to create listing type.', 'wb-listora' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response( $this->prepare_type_response( $type, true ), 201 );
	}

	/**
	 * Update an existing listing type.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$slug     = sanitize_title( $request->get_param( 'slug' ) );
		$registry = \WBListora\Core\Listing_Type_Registry::instance();

		$existing = $registry->get( $slug );
		if ( ! $existing ) {
			return new WP_Error(
				'listora_type_not_found',
				__( 'Listing type not found.', 'wb-listora' ),
				array( 'status' => 404 )
			);
		}

		$name   = $request->get_param( 'name' )
			? sanitize_text_field( $request->get_param( 'name' ) )
			: $existing->get_name();
		$data   = $this->prepare_type_data_from_request( $request, $name, $existing );
		$result = $registry->save_type( $slug, $data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$registry->flush();
		$registry->init();
		$type = $registry->get( $slug );

		return new WP_REST_Response( $this->prepare_type_response( $type, true ), 200 );
	}

	/**
	 * Delete a listing type.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$slug     = sanitize_title( $request->get_param( 'slug' ) );
		$registry = \WBListora\Core\Listing_Type_Registry::instance();

		if ( ! $registry->get( $slug ) ) {
			return new WP_Error(
				'listora_type_not_found',
				__( 'Listing type not found.', 'wb-listora' ),
				array( 'status' => 404 )
			);
		}

		$count  = $this->get_listing_count_for_type( $slug );
		$result = $registry->delete_type( $slug );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'deleted'        => true,
				'listings_count' => $count,
				'message'        => $count > 0
					/* translators: %d: number of listings that were using the deleted type */
					? sprintf( __( '%d listings were using this type and are now unassigned.', 'wb-listora' ), $count )
					: __( 'Type deleted successfully.', 'wb-listora' ),
			),
			200
		);
	}

	// ─── CRUD Helpers ───

	/**
	 * Prepare type data array from a REST request.
	 *
	 * @param WP_REST_Request                    $request  Request.
	 * @param string                             $name     Sanitized type name.
	 * @param \WBListora\Core\Listing_Type|null  $existing Existing type for partial updates.
	 * @return array Data in the format expected by save_type().
	 */
	private function prepare_type_data_from_request( $request, $name, $existing = null ) {
		$props = array(
			'name'               => $name,
			'schema_type'        => $this->get_param_or_existing( $request, 'schema_type', $existing, 'schema_type', 'LocalBusiness' ),
			'icon'               => $this->get_param_or_existing( $request, 'icon', $existing, 'icon', 'dashicons-location-alt' ),
			'color'              => $this->get_param_or_existing( $request, 'color', $existing, 'color', '#0073aa' ),
			'map_enabled'        => $this->get_param_or_existing( $request, 'map_enabled', $existing, 'map_enabled', true ),
			'review_enabled'     => $this->get_param_or_existing( $request, 'review_enabled', $existing, 'review_enabled', true ),
			'submission_enabled' => $this->get_param_or_existing( $request, 'submission_enabled', $existing, 'submission_enabled', true ),
			'moderation'         => $this->get_param_or_existing( $request, 'moderation', $existing, 'moderation', 'manual' ),
			'expiration_days'    => $this->get_param_or_existing( $request, 'expiration_days', $existing, 'expiration_days', 365 ),
			'card_layout'        => $this->get_param_or_existing( $request, 'card_layout', $existing, 'card_layout', 'standard' ),
			'detail_layout'      => $this->get_param_or_existing( $request, 'detail_layout', $existing, 'detail_layout', 'tabbed' ),
			'review_criteria'    => $this->get_param_or_existing( $request, 'review_criteria', $existing, 'review_criteria', array() ),
		);

		$field_groups = $request->get_param( 'field_groups' );
		if ( null === $field_groups && $existing ) {
			$field_groups = array_map(
				function ( $group ) {
					return $group->to_array();
				},
				$existing->get_field_groups()
			);
		}

		$categories = $request->get_param( 'categories' );
		if ( null === $categories && $existing ) {
			$categories = $existing->get_allowed_categories();
		}

		return array(
			'props'        => $props,
			'field_groups' => is_array( $field_groups ) ? $field_groups : array(),
			'categories'   => is_array( $categories ) ? $categories : array(),
		);
	}

	/**
	 * Get a parameter from the request, or fall back to existing type value.
	 *
	 * @param WP_REST_Request                    $request  Request.
	 * @param string                             $param    Request param name.
	 * @param \WBListora\Core\Listing_Type|null  $existing Existing type.
	 * @param string                             $prop     Property name on the existing type.
	 * @param mixed                              $fallback Default value.
	 * @return mixed
	 */
	private function get_param_or_existing( $request, $param, $existing, $prop, $fallback ) {
		$value = $request->get_param( $param );
		if ( null !== $value ) {
			return $value;
		}
		if ( $existing ) {
			$existing_value = $existing->get_prop( $prop );
			return null !== $existing_value ? $existing_value : $fallback;
		}
		return $fallback;
	}

	/**
	 * Get the number of listings assigned to a type.
	 *
	 * @param string $slug Type slug.
	 * @return int
	 */
	private function get_listing_count_for_type( $slug ) {
		$term = get_term_by( 'slug', $slug, 'listora_listing_type' );
		return $term ? (int) $term->count : 0;
	}

	/**
	 * Get argument schema for creating a listing type.
	 *
	 * @return array
	 */
	private function get_type_create_args() {
		return array(
			'name'               => array(
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
				'description'       => __( 'Display name for the listing type.', 'wb-listora' ),
			),
			'slug'               => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_title',
				'description'       => __( 'URL-safe slug. Auto-generated from name if omitted.', 'wb-listora' ),
			),
			'schema_type'        => array(
				'type'              => 'string',
				'required'          => false,
				'default'           => 'LocalBusiness',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'icon'               => array(
				'type'              => 'string',
				'required'          => false,
				'default'           => 'dashicons-location-alt',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'color'              => array(
				'type'              => 'string',
				'required'          => false,
				'default'           => '#0073aa',
				'sanitize_callback' => 'sanitize_hex_color',
			),
			'map_enabled'        => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'review_enabled'     => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'submission_enabled' => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'moderation'         => array(
				'type'              => 'string',
				'default'           => 'manual',
				'enum'              => array( 'manual', 'auto', 'none' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'expiration_days'    => array(
				'type'              => 'integer',
				'default'           => 365,
				'sanitize_callback' => 'absint',
			),
			'field_groups'       => array(
				'type'    => 'array',
				'default' => array(),
				'items'   => array( 'type' => 'object' ),
			),
			'categories'         => array(
				'type'    => 'array',
				'default' => array(),
				'items'   => array( 'type' => 'integer' ),
			),
		);
	}

	/**
	 * Get argument schema for updating a listing type.
	 *
	 * @return array
	 */
	private function get_type_update_args() {
		$args = $this->get_type_create_args();

		// For updates, no field is required except slug (from URL).
		foreach ( $args as $key => &$arg ) {
			$arg['required'] = false;
		}

		$args['slug'] = array(
			'type'              => 'string',
			'required'          => true,
			'sanitize_callback' => 'sanitize_title',
		);

		return $args;
	}

	/**
	 * Prepare a type for the REST response.
	 *
	 * @param \WBListora\Core\Listing_Type $type         Type object.
	 * @param bool                         $include_fields Include full field definitions.
	 * @return array
	 */
	private function prepare_type_response( $type, $include_fields = false ) {
		$data = array(
			'slug'           => $type->get_slug(),
			'name'           => $type->get_name(),
			'schema_type'    => $type->get_schema_type(),
			'icon'           => $type->get_icon(),
			'color'          => $type->get_color(),
			'map_enabled'    => (bool) $type->get_prop( 'map_enabled' ),
			'review_enabled' => $type->is_review_enabled(),
			'field_count'    => count( $type->get_all_fields() ),
			'is_default'     => $type->is_default(),
			'listing_count'  => $this->get_listing_count_for_type( $type->get_slug() ),
		);

		if ( $include_fields ) {
			$data['field_groups'] = array_map(
				function ( $group ) {
					return $group->to_array();
				},
				$type->get_field_groups()
			);

			$data['filterable_fields'] = array_map(
				function ( $field ) {
					return array(
						'key'     => $field->get_key(),
						'label'   => $field->get_label(),
						'type'    => $field->get_type(),
						'options' => $field->get( 'options' ) ?: array(),
					);
				},
				$type->get_filterable_fields()
			);

			$data['card_fields'] = array_map(
				function ( $field ) {
					return $field->get_key();
				},
				$type->get_card_fields()
			);

			$data['settings'] = array(
				'submission_enabled' => (bool) $type->get_prop( 'submission_enabled' ),
				'moderation'         => $type->get_prop( 'moderation' ) ?: 'manual',
				'expiration_days'    => (int) $type->get_prop( 'expiration_days' ),
			);

			$data['allowed_categories'] = $type->get_allowed_categories();
		}

		return $data;
	}

	/**
	 * Get default filter UI type for a field type.
	 *
	 * @param string $field_type Field type.
	 * @return string
	 */
	private function default_filter_type( $field_type ) {
		$map = array(
			'select'         => 'dropdown',
			'multiselect'    => 'checkbox_list',
			'checkbox'       => 'toggle',
			'radio'          => 'radio',
			'number'         => 'min_max',
			'price'          => 'min_max',
			'date'           => 'date_picker',
			'datetime'       => 'date_picker',
			'rating'         => 'star_select',
			'business_hours' => 'toggle',
		);

		return $map[ $field_type ] ?? 'text';
	}
}
