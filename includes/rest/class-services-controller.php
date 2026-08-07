<?php
/**
 * REST Services Controller.
 *
 * @package WBListora\REST
 */

namespace WBListora\REST;

defined( 'ABSPATH' ) || exit;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use WBListora\Core\Services;

/**
 * Handles service CRUD via REST API.
 */
class Services_Controller extends WP_REST_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = WB_LISTORA_REST_NAMESPACE;

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// GET/POST /listings/{listing_id}/services
		register_rest_route(
			$this->namespace,
			'/listings/(?P<listing_id>[\d]+)/services',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_listing_services' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'listing_id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
						'status'     => array(
							'type'    => 'string',
							'default' => 'active',
							'enum'    => array( 'active', 'inactive', 'all' ),
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_service' ),
					'permission_callback' => array( $this, 'create_service_permissions' ),
					'args'                => array(
						'listing_id'       => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
						'title'            => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'description'      => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'price'            => array(
							'type' => 'number',
						),
						'price_type'       => array(
							'type'    => 'string',
							'default' => 'fixed',
							'enum'    => array( 'fixed', 'starting_from', 'hourly', 'free', 'contact' ),
						),
						'duration_minutes' => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'image_id'         => array(
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'categories'       => array(
							'type'  => 'array',
							'items' => array(
								'type' => 'integer',
							),
						),
					),
				),
			)
		);

		// GET /services/{id}
		register_rest_route(
			$this->namespace,
			'/services/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_service' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_service' ),
					'permission_callback' => array( $this, 'update_service_permissions' ),
					'args'                => array(
						'id'               => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
						'title'            => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'description'      => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'price'            => array(
							'type' => 'number',
						),
						'price_type'       => array(
							'type' => 'string',
							'enum' => array( 'fixed', 'starting_from', 'hourly', 'free', 'contact' ),
						),
						'duration_minutes' => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'image_id'         => array(
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'categories'       => array(
							'type'  => 'array',
							'items' => array(
								'type' => 'integer',
							),
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_service' ),
					'permission_callback' => array( $this, 'delete_service_permissions' ),
					'args'                => array(
						'id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// POST /listings/{listing_id}/services/reorder
		register_rest_route(
			$this->namespace,
			'/listings/(?P<listing_id>[\d]+)/services/reorder',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reorder_services' ),
					'permission_callback' => array( $this, 'create_service_permissions' ),
					'args'                => array(
						'listing_id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
						'order'      => array(
							'type'     => 'array',
							'required' => true,
							'items'    => array(
								'type' => 'integer',
							),
						),
					),
				),
			)
		);
	}

	/**
	 * Get services for a listing.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_listing_services( $request ) {
		$listing_id = $request->get_param( 'listing_id' );
		$status     = $request->get_param( 'status' );

		// Verify listing exists.
		$post = get_post( $listing_id );
		if ( ! $post || 'listora_listing' !== $post->post_type || ! $this->listing_is_viewable( $post ) ) {
			return new WP_Error( 'listora_invalid_listing', __( 'Listing not found.', 'wb-listora' ), array( 'status' => 404 ) );
		}

		if ( 'all' === $status ) {
			// Only listing owner or admin can see all statuses.
			if ( ! $this->can_manage_listing( $listing_id ) ) {
				$status = 'active';
			}
		}

		$services = Services::get_services( $listing_id, $status );

		$items = array_map(
			function ( $service ) use ( $request ) {
				return $this->prepare_service_response( $service, $request );
			},
			$services
		);

		return new WP_REST_Response(
			array(
				'services' => $items,
				'total'    => count( $items ),
				'has_more' => false,
			),
			200
		);
	}

	/**
	 * Get a single service.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_service( $request ) {
		$service_id = $request->get_param( 'id' );
		$service    = Services::get_service( $service_id );

		if ( ! $service ) {
			return new WP_Error( 'listora_service_not_found', __( 'Service not found.', 'wb-listora' ), array( 'status' => 404 ) );
		}

		// Inherit the parent listing's visibility — a service on an unpublished
		// listing must not be readable through its own direct URL either.
		$parent = get_post( (int) $service['listing_id'] );
		if ( ! $parent || 'listora_listing' !== $parent->post_type || ! $this->listing_is_viewable( $parent ) ) {
			return new WP_Error( 'listora_service_not_found', __( 'Service not found.', 'wb-listora' ), array( 'status' => 404 ) );
		}

		// Hide deleted services from public view.
		if ( 'deleted' === $service['status'] && ! $this->can_manage_listing( (int) $service['listing_id'] ) ) {
			return new WP_Error( 'listora_service_not_found', __( 'Service not found.', 'wb-listora' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( $this->prepare_service_response( $service, $request ), 200 );
	}

	/**
	 * Create a service.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_service( $request ) {
		$data = array(
			'listing_id'       => $request->get_param( 'listing_id' ),
			'title'            => $request->get_param( 'title' ),
			'description'      => $request->get_param( 'description' ),
			'price'            => $request->get_param( 'price' ),
			'price_type'       => $request->get_param( 'price_type' ),
			'duration_minutes' => $request->get_param( 'duration_minutes' ),
			'image_id'         => $request->get_param( 'image_id' ),
			'categories'       => $request->get_param( 'categories' ),
		);

		$result = Services::create_service( $data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$service = Services::get_service( $result );

		return new WP_REST_Response( $this->prepare_service_response( $service, $request ), 201 );
	}

	/**
	 * Update a service.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_service( $request ) {
		$service_id = $request->get_param( 'id' );

		$data = array();

		$fields = array( 'title', 'description', 'price', 'price_type', 'duration_minutes', 'image_id', 'categories' );
		foreach ( $fields as $field ) {
			if ( null !== $request->get_param( $field ) ) {
				$data[ $field ] = $request->get_param( $field );
			}
		}

		$result = Services::update_service( $service_id, $data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$service = Services::get_service( $service_id );

		return new WP_REST_Response( $this->prepare_service_response( $service, $request ), 200 );
	}

	/**
	 * Delete a service.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_service( $request ) {
		$service_id = $request->get_param( 'id' );

		$result = Services::delete_service( $service_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'deleted' => true,
				'id'      => $service_id,
			),
			200
		);
	}

	/**
	 * Reorder services for a listing.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reorder_services( $request ) {
		$listing_id = $request->get_param( 'listing_id' );
		$order      = $request->get_param( 'order' );

		Services::reorder_services( $listing_id, $order );

		return new WP_REST_Response(
			array(
				'reordered' => true,
			),
			200
		);
	}

	/**
	 * Prepare a service for the REST response.
	 *
	 * @param array           $service Service row.
	 * @param WP_REST_Request $request Request object.
	 * @return array
	 */
	private function prepare_service_response( $service, $request ) {
		$image_url = '';
		if ( ! empty( $service['image_id'] ) ) {
			$image_url = wp_get_attachment_image_url( (int) $service['image_id'], 'medium' ) ?: '';
		}

		$categories = Services::get_service_categories( (int) $service['id'] );
		$cat_data   = array();
		foreach ( $categories as $term_id ) {
			$term = get_term( $term_id, 'listora_service_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$cat_data[] = array(
					'id'   => $term->term_id,
					'name' => $term->name,
					'slug' => $term->slug,
				);
			}
		}

		$data = array(
			'id'               => (int) $service['id'],
			'listing_id'       => (int) $service['listing_id'],
			'title'            => $service['title'],
			'description'      => $service['description'],
			'price'            => null !== $service['price'] ? (float) $service['price'] : null,
			'price_type'       => $service['price_type'],
			'duration_minutes' => null !== $service['duration_minutes'] ? (int) $service['duration_minutes'] : null,
			'image_id'         => ! empty( $service['image_id'] ) ? (int) $service['image_id'] : null,
			'image_url'        => $image_url,
			'sort_order'       => (int) $service['sort_order'],
			'status'           => $service['status'],
			'categories'       => $cat_data,
			'created_at'       => $service['created_at'],
			'updated_at'       => $service['updated_at'],
		);

		/**
		 * Filter a single service in the REST response.
		 *
		 * @param array           $data       Service data.
		 * @param int             $service_id Service ID.
		 * @param WP_REST_Request $request    REST request.
		 */
		return apply_filters( 'wb_listora_rest_prepare_service', $data, (int) $service['id'], $request );
	}

	/**
	 * Check if the current user can manage a listing.
	 *
	 * @param int $listing_id Listing post ID.
	 * @return bool
	 */
	/**
	 * Whether the current caller may see a listing's service data at all.
	 *
	 * Both service read routes are `__return_true` (services on a published
	 * listing are public by design), so visibility has to be decided from the
	 * PARENT listing's status. Without this, a draft / pending / rejected /
	 * awaiting-credits listing served its titles, descriptions and prices to
	 * anonymous callers.
	 *
	 * Mirrors the guard at `Listings_Controller::get_listing_detail()` — owner
	 * or manager may see unpublished; everyone else gets a 404 rather than a
	 * 403, so the endpoint does not confirm the listing exists.
	 *
	 * @since 1.4.2
	 *
	 * @param \WP_Post $post Listing post object.
	 * @return bool
	 */
	private function listing_is_viewable( $post ) {
		if ( 'publish' === $post->post_status ) {
			return true;
		}

		// can_manage_listing() asserts login before comparing post_author, so an
		// imported listing carrying post_author 0 cannot match an anonymous
		// caller's user id of 0.
		return $this->can_manage_listing( (int) $post->ID );
	}

	/**
	 * Whether the current user may manage services on a listing.
	 *
	 * @param int $listing_id Listing post ID.
	 * @return bool
	 */
	private function can_manage_listing( int $listing_id ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$post = get_post( $listing_id );
		if ( ! $post ) {
			return false;
		}

		return (int) $post->post_author === get_current_user_id();
	}

	/**
	 * Permission check for creating a service.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function create_service_permissions( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'listora_unauthorized',
				__( 'You do not have permission to perform this action.', 'wb-listora' ),
				array( 'status' => 401 )
			);
		}

		$listing_id = $request->get_param( 'listing_id' );

		if ( ! $this->can_manage_listing( $listing_id ) ) {
			return new WP_Error(
				'listora_forbidden',
				__( 'You do not have permission to manage services for this listing.', 'wb-listora' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Permission check for updating a service.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function update_service_permissions( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'listora_unauthorized',
				__( 'You do not have permission to perform this action.', 'wb-listora' ),
				array( 'status' => 401 )
			);
		}

		$service_id = $request->get_param( 'id' );
		$service    = Services::get_service( $service_id );

		if ( ! $service ) {
			return new WP_Error(
				'listora_service_not_found',
				__( 'Service not found.', 'wb-listora' ),
				array( 'status' => 404 )
			);
		}

		if ( ! $this->can_manage_listing( (int) $service['listing_id'] ) ) {
			return new WP_Error(
				'listora_forbidden',
				__( 'You do not have permission to manage this service.', 'wb-listora' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Permission check for deleting a service.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function delete_service_permissions( $request ) {
		return $this->update_service_permissions( $request );
	}
}
