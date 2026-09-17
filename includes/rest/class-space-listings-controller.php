<?php
/**
 * REST: business listing <-> BuddyNext space showcase.
 *
 * A member submits a listing they own to a space; the space team approves it
 * before it shows in that space's Businesses tab. This controller is the
 * partner-owned API (routes under listora/v1); BuddyNext Pro's space UI calls
 * it and answers the two authorization filters below.
 *
 *   POST   /listings/{id}/spaces                       submit (listing owner)
 *   GET    /spaces/{space_id}/listings                 approved showcase (viewer)
 *   GET    /spaces/{space_id}/listings/pending         moderation queue (space team)
 *   POST   /spaces/{space_id}/listings/{id}/approve    approve (space team)
 *   DELETE /spaces/{space_id}/listings/{id}            reject / withdraw (team OR owner)
 *
 * Space authority lives in BuddyNext, which owns spaces + roles, so it is asked
 * via `wbl_user_can_moderate_space` and `wbl_user_can_view_space` (both default
 * false - fail closed, and there are no spaces at all without BuddyNext).
 *
 * @package WB_Listora
 */

namespace WBListora\REST;

use WBListora\Core\Space_Listings_Model;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Listing <-> space showcase endpoints.
 */
class Space_Listings_Controller {

	/**
	 * Route namespace.
	 *
	 * @var string
	 */
	protected $namespace = WB_LISTORA_REST_NAMESPACE;

	/**
	 * The listing post type.
	 */
	const POST_TYPE = 'listora_listing';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/listings/(?P<id>\d+)/spaces',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'submit' ),
					'permission_callback' => array( $this, 'can_submit' ),
					'args'                => array(
						'id'       => array( 'sanitize_callback' => 'absint' ),
						'space_id' => array(
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/spaces/(?P<space_id>\d+)/listings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'showcase' ),
					'permission_callback' => array( $this, 'can_view' ),
					'args'                => array(
						'space_id' => array( 'sanitize_callback' => 'absint' ),
						'page'     => array(
							'default'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page' => array(
							'default'           => 24,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/spaces/(?P<space_id>\d+)/listings/pending',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'pending' ),
					'permission_callback' => array( $this, 'can_moderate' ),
					'args'                => array( 'space_id' => array( 'sanitize_callback' => 'absint' ) ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/spaces/(?P<space_id>\d+)/listings/(?P<id>\d+)/approve',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'approve' ),
					'permission_callback' => array( $this, 'can_moderate' ),
					'args'                => array(
						'space_id' => array( 'sanitize_callback' => 'absint' ),
						'id'       => array( 'sanitize_callback' => 'absint' ),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/spaces/(?P<space_id>\d+)/listings/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'remove' ),
					'permission_callback' => array( $this, 'can_remove' ),
					'args'                => array(
						'space_id' => array( 'sanitize_callback' => 'absint' ),
						'id'       => array( 'sanitize_callback' => 'absint' ),
					),
				),
			)
		);
	}

	// ── Permission callbacks ───────────────────────────────────────────────────

	/**
	 * Submit: the member must be able to edit the listing (its owner, or staff).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function can_submit( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( ! $this->is_listing( $id ) ) {
			return new WP_Error( 'wbl_not_found', __( 'Listing not found.', 'wb-listora' ), array( 'status' => 404 ) );
		}
		if ( ! $this->owns_listing( $id ) ) {
			return new WP_Error( 'wbl_forbidden', __( 'You can only submit a listing you own.', 'wb-listora' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * View the showcase: BuddyNext decides whether this viewer may see the space.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_view( WP_REST_Request $request ) {
		return (bool) apply_filters( 'wbl_user_can_view_space', false, (int) $request['space_id'], get_current_user_id() );
	}

	/**
	 * Moderate: BuddyNext decides whether this viewer is the space's team.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function can_moderate( WP_REST_Request $request ) {
		if ( (bool) apply_filters( 'wbl_user_can_moderate_space', false, (int) $request['space_id'], get_current_user_id() ) ) {
			return true;
		}
		return new WP_Error( 'wbl_forbidden', __( 'Only the space team can do that.', 'wb-listora' ), array( 'status' => 403 ) );
	}

	/**
	 * Remove: the space team OR the listing's owner (withdrawing their own).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function can_remove( WP_REST_Request $request ) {
		$listing = (int) $request['id'];
		if ( $this->owns_listing( $listing ) ) {
			return true;
		}
		return $this->can_moderate( $request );
	}

	// ── Callbacks ────────────────────────────────────────────────────────────────

	/**
	 * POST /listings/{id}/spaces — submit a listing to a space (pending).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function submit( WP_REST_Request $request ) {
		$listing  = (int) $request['id'];
		$space_id = (int) $request['space_id'];

		if ( $space_id <= 0 ) {
			return new WP_Error( 'wbl_bad_space', __( 'A space is required.', 'wb-listora' ), array( 'status' => 400 ) );
		}
		if ( 'publish' !== get_post_status( $listing ) ) {
			return new WP_Error( 'wbl_not_published', __( 'Publish the listing before submitting it to a space.', 'wb-listora' ), array( 'status' => 409 ) );
		}

		// Already linked? Report the current state rather than a second row.
		$current = Space_Listings_Model::status_for( $space_id, $listing );
		if ( '' !== $current ) {
			return new WP_REST_Response( array( 'status' => $current ), 200 );
		}

		// Anti-flood: cap how many of a member's submissions may sit awaiting review
		// in one space, so one member cannot bury the team's queue. Approved
		// listings are not counted; a site can tune or lift the cap (0 = unlimited)
		// via the filter.
		$user_id = get_current_user_id();
		$limit   = (int) apply_filters( 'wbl_space_pending_submission_limit', 5, $space_id, $user_id );
		if ( $limit > 0 && Space_Listings_Model::pending_count_for_user( $space_id, $user_id ) >= $limit ) {
			return new WP_Error(
				'wbl_too_many_pending',
				sprintf(
					/* translators: %d: number of submissions already awaiting review. */
					_n(
						'You have %d listing awaiting review in this space. Wait for the team to review it before submitting more.',
						'You have %d listings awaiting review in this space. Wait for the team to review them before submitting more.',
						$limit,
						'wb-listora'
					),
					$limit
				),
				array( 'status' => 429 )
			);
		}

		Space_Listings_Model::submit( $space_id, $listing, $user_id );

		/**
		 * Fires when a listing is submitted to a space (pending approval).
		 *
		 * @param int $listing_id Listing.
		 * @param int $space_id   Space.
		 * @param int $user_id    Submitter.
		 */
		do_action( 'wbl_listing_submitted_to_space', $listing, $space_id, get_current_user_id() );

		return new WP_REST_Response( array( 'status' => Space_Listings_Model::STATUS_PENDING ), 201 );
	}

	/**
	 * GET /spaces/{space_id}/listings — the approved showcase, paginated, shaped
	 * as the SAME listing cards the search + profile panel use.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function showcase( WP_REST_Request $request ) {
		$space_id = (int) $request['space_id'];
		$per_page = max( 1, min( 48, (int) $request['per_page'] ) );
		$page     = max( 1, (int) $request['page'] );
		$offset   = ( $page - 1 ) * $per_page;

		$total = Space_Listings_Model::approved_count( $space_id );
		$ids   = Space_Listings_Model::approved_listing_ids( $space_id, $per_page, $offset );
		$items = empty( $ids ) ? array() : ( new Search_Controller() )->hydrate_listings( $ids );

		$response = new WP_REST_Response( array_values( $items ), 200 );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) ( (int) ceil( $total / $per_page ) ) );

		return $response;
	}

	/**
	 * GET /spaces/{space_id}/listings/pending — the moderation queue: each
	 * pending listing's card plus who submitted it and when.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function pending( WP_REST_Request $request ) {
		$space_id = (int) $request['space_id'];
		$rows     = Space_Listings_Model::pending( $space_id );
		if ( empty( $rows ) ) {
			return new WP_REST_Response( array(), 200 );
		}

		$ids   = wp_list_pluck( $rows, 'listing_id' );
		$cards = ( new Search_Controller() )->hydrate_listings( $ids );
		// Key cards by id so each queue row can carry its card + who/when.
		$by_id = array();
		foreach ( $cards as $card ) {
			if ( isset( $card['id'] ) ) {
				$by_id[ (int) $card['id'] ] = $card;
			}
		}

		$out = array();
		foreach ( $rows as $row ) {
			$lid = (int) $row['listing_id'];
			if ( ! isset( $by_id[ $lid ] ) ) {
				continue; // Listing gone; a cleanup will drop the row.
			}
			$out[] = array(
				'listing'      => $by_id[ $lid ],
				'submitted_by' => $row['submitted_by'],
				'submitted_at' => $row['created_at'],
			);
		}

		return new WP_REST_Response( $out, 200 );
	}

	/**
	 * POST /spaces/{space_id}/listings/{id}/approve.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function approve( WP_REST_Request $request ) {
		$space_id = (int) $request['space_id'];
		$listing  = (int) $request['id'];

		if ( '' === Space_Listings_Model::status_for( $space_id, $listing ) ) {
			// Not submitted yet: a curator adding directly is allowed - place it approved.
			Space_Listings_Model::add_approved( $space_id, $listing, get_current_user_id() );
		} else {
			Space_Listings_Model::approve( $space_id, $listing, get_current_user_id() );
		}

		/** This action is documented in this file's submit() method. */
		do_action( 'wbl_listing_approved_in_space', $listing, $space_id, get_current_user_id() );

		return new WP_REST_Response( array( 'status' => Space_Listings_Model::STATUS_APPROVED ), 200 );
	}

	/**
	 * DELETE /spaces/{space_id}/listings/{id} — reject a submission or take a
	 * listing down (the team), or a member withdrawing their own.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function remove( WP_REST_Request $request ) {
		$space_id = (int) $request['space_id'];
		$listing  = (int) $request['id'];

		Space_Listings_Model::remove( $space_id, $listing );

		/** This action is documented in this file's submit() method. */
		do_action( 'wbl_listing_removed_from_space', $listing, $space_id, get_current_user_id() );

		return new WP_REST_Response( array( 'removed' => true ), 200 );
	}

	/**
	 * Is this id a listing post?
	 *
	 * @param int $id Candidate.
	 * @return bool
	 */
	private function is_listing( $id ) {
		return $id > 0 && self::POST_TYPE === get_post_type( $id );
	}

	/**
	 * Whether the current user owns the listing. The AUTHOR check is what matters:
	 * a listing-owning member is usually a subscriber with no generic edit_posts
	 * cap, so current_user_can('edit_post') alone would wrongly refuse them. Staff
	 * who can edit it (admins/editors) qualify too.
	 *
	 * @param int $id Listing id.
	 * @return bool
	 */
	private function owns_listing( $id ) {
		$uid = get_current_user_id();
		if ( $uid <= 0 ) {
			return false;
		}
		if ( (int) get_post_field( 'post_author', $id ) === $uid ) {
			return true;
		}
		return current_user_can( 'edit_post', $id );
	}
}
