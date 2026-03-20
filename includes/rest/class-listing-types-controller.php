<?php
/**
 * REST Listing Types Controller.
 *
 * @package WBListora\REST
 */

namespace WBListora\REST;

defined( 'ABSPATH' ) || exit;

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
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// GET /listing-types/{slug} — single type.
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
