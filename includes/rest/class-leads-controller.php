<?php
/**
 * REST: contact-form leads for the app and integrations.
 *
 * @package WBListora\REST
 */

namespace WBListora\REST;

defined( 'ABSPATH' ) || exit;

use WBListora\Core\Leads;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * GET /leads, GET /leads/{id}, DELETE /leads/{id}.
 *
 * A listing owner reads and deletes the leads their listings received; a
 * site manager reads every lead.
 */
class Leads_Controller extends WP_REST_Controller {

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = WB_LISTORA_REST_NAMESPACE;

	/**
	 * Base.
	 *
	 * @var string
	 */
	protected $rest_base = 'leads';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => 'is_user_logged_in',
					'args'                => array(
						'listing_id' => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'search'     => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'after'      => array(
							'type'   => 'string',
							'format' => 'date-time',
						),
						'before'     => array(
							'type'   => 'string',
							'format' => 'date-time',
						),
						'page'       => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						),
						'per_page'   => array(
							'type'    => 'integer',
							'default' => 20,
							'minimum' => 1,
							'maximum' => 100,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'item_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'item_permission' ),
				),
			)
		);
	}

	/**
	 * Leads the caller may see, one page, with X-WP-Total headers.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$per_page = (int) $request->get_param( 'per_page' );
		$page     = (int) $request->get_param( 'page' );
		$args     = array(
			'listing_id' => (int) $request->get_param( 'listing_id' ),
			'search'     => (string) $request->get_param( 'search' ),
			'after'      => self::utc( $request->get_param( 'after' ) ),
			'before'     => self::utc( $request->get_param( 'before' ) ),
			'limit'      => $per_page,
			'offset'     => ( $page - 1 ) * $per_page,
		);
		if ( ! current_user_can( 'manage_listora_settings' ) ) {
			$args['owner_id'] = get_current_user_id();
		}
		$result   = Leads::query( $args );
		$response = rest_ensure_response( $result['rows'] );
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) (int) ceil( $result['total'] / max( 1, $per_page ) ) );
		return $response;
	}

	/**
	 * One lead.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_item( $request ) {
		return rest_ensure_response( Leads::get( (int) $request['id'] ) );
	}

	/**
	 * Delete one lead.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function delete_item( $request ) {
		$lead = Leads::get( (int) $request['id'] );
		Leads::delete( (int) $request['id'] );
		return rest_ensure_response(
			array(
				'deleted'  => true,
				'previous' => $lead,
			)
		);
	}

	/**
	 * The lead's listing owner or a site manager; 404 for anyone else.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function item_permission( $request ) {
		$lead = Leads::get( (int) $request['id'] );
		if ( ! $lead || ! Leads::user_can_manage( $lead, get_current_user_id() ) ) {
			// Not found and not yours read the same, so IDs cannot be probed.
			return new WP_Error( 'listora_lead_not_found', __( 'Lead not found.', 'wb-listora' ), array( 'status' => 404 ) );
		}
		return true;
	}

	/**
	 * An ISO date-time as UTC 'Y-m-d H:i:s', or '' when absent or invalid.
	 *
	 * @param mixed $value Date-time.
	 * @return string
	 */
	private static function utc( $value ): string {
		$ts = is_string( $value ) && '' !== $value ? strtotime( $value ) : false;
		return $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : '';
	}
}
