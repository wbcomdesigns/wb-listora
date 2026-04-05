<?php
/**
 * REST Submission Controller — handles frontend listing submissions.
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

/**
 * Handles frontend listing creation and editing.
 */
class Submission_Controller extends WP_REST_Controller {

	protected $namespace = WB_LISTORA_REST_NAMESPACE;
	protected $rest_base = 'submit';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// POST /submit — create listing from frontend.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'submit_listing' ),
					'permission_callback' => function () {
						return current_user_can( 'submit_listora_listing' );
					},
					'args'                => array(
						'confirmed_not_duplicate' => array(
							'type'              => 'boolean',
							'default'           => false,
							'sanitize_callback' => 'rest_sanitize_boolean',
						),
					),
				),
			)
		);

		// POST /submit/check-duplicate — check for duplicate listings.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/check-duplicate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'check_duplicate_endpoint' ),
					'permission_callback' => function () {
						return is_user_logged_in();
					},
					'args'                => array(
						'title' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'type'  => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'lat'   => array(
							'type'    => 'number',
							'default' => null,
						),
						'lng'   => array(
							'type'    => 'number',
							'default' => null,
						),
					),
				),
			)
		);

		// PUT /submit/{id} — edit own listing.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_listing' ),
					'permission_callback' => function ( $request ) {
						$post = get_post( $request->get_param( 'id' ) );
						return $post && (int) $post->post_author === get_current_user_id();
					},
				),
			)
		);
	}

	/**
	 * Handle listing submission.
	 *
	 * If a listing_id is present in the body and the current user owns that listing,
	 * this routes to update_listing() instead of creating a new post.
	 */
	public function submit_listing( $request ) {
		// Honeypot check.
		$hp = $request->get_param( 'listora_hp_field' );
		if ( ! empty( $hp ) ) {
			return new WP_Error( 'listora_spam', __( 'Submission blocked.', 'wb-listora' ), array( 'status' => 403 ) );
		}

		// Nonce check — validates when present (HTML form submissions).
		// REST API has its own CSRF protection via X-WP-Nonce header in permission_callback.
		$nonce = $request->get_param( 'listora_nonce' );
		if ( $nonce && ! wp_verify_nonce( $nonce, 'listora_submit_listing' ) ) {
			return new WP_Error( 'listora_nonce_failed', __( 'Security check failed.', 'wb-listora' ), array( 'status' => 403 ) );
		}

		// Edit mode: route to update when listing_id is in the body and user owns it.
		$listing_id = absint( $request->get_param( 'listing_id' ) ?? 0 );
		if ( $listing_id > 0 ) {
			$existing = get_post( $listing_id );
			if (
				$existing &&
				'listora_listing' === $existing->post_type &&
				(int) $existing->post_author === get_current_user_id()
			) {
				// Inject the id param so update_listing() can read it.
				$request->set_param( 'id', $listing_id );
				return $this->update_listing( $request );
			}
			// listing_id present but not owner — treat as a new submission attempt and let it fall through to create.
		}

		$title       = sanitize_text_field( $request->get_param( 'title' ) ?? '' );
		$description = sanitize_textarea_field( $request->get_param( 'description' ) ?? '' );
		$type_slug   = sanitize_text_field( $request->get_param( 'listing_type' ) ?? '' );
		$category    = absint( $request->get_param( 'category' ) ?? 0 );
		$tags        = sanitize_text_field( $request->get_param( 'tags' ) ?? '' );
		$status      = $request->get_param( 'status' ) === 'draft' ? 'draft' : $this->get_submission_status();

		if ( empty( $title ) ) {
			return new WP_Error( 'listora_title_required', __( 'Title is required.', 'wb-listora' ), array( 'status' => 400 ) );
		}

		// Duplicate check — skip if the client has confirmed it is not a duplicate.
		if ( ! rest_sanitize_boolean( $request->get_param( 'confirmed_not_duplicate' ) ) ) {
			$lat = $request->get_param( 'lat' );
			$lng = $request->get_param( 'lng' );

			$duplicates = $this->check_duplicates(
				$title,
				$type_slug,
				null !== $lat ? (float) $lat : null,
				null !== $lng ? (float) $lng : null
			);

			if ( ! empty( $duplicates ) ) {
				return new WP_REST_Response(
					array(
						'code'       => 'listora_duplicate_detected',
						'message'    => __( 'Potential duplicate listing(s) found. Please confirm this is not a duplicate to proceed.', 'wb-listora' ),
						'duplicates' => $duplicates,
					),
					409
				);
			}
		}

		// Create the post.
		$post_data = array(
			'post_type'    => 'listora_listing',
			'post_title'   => $title,
			'post_content' => $description,
			'post_status'  => $status,
			'post_author'  => get_current_user_id(),
		);

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Set listing type.
		if ( $type_slug ) {
			wp_set_object_terms( $post_id, $type_slug, 'listora_listing_type' );
		}

		// Set category.
		if ( $category > 0 ) {
			wp_set_object_terms( $post_id, array( $category ), 'listora_listing_cat' );
		}

		// Set tags.
		if ( $tags ) {
			$tag_array = array_map( 'trim', explode( ',', $tags ) );
			wp_set_object_terms( $post_id, $tag_array, 'listora_listing_tag' );
		}

		// Set featured image.
		$featured_image = absint( $request->get_param( 'featured_image' ) ?? 0 );
		if ( $featured_image > 0 ) {
			set_post_thumbnail( $post_id, $featured_image );
		}

		// Set gallery.
		$gallery = $request->get_param( 'gallery' );
		if ( $gallery ) {
			$gallery_ids = array_map( 'absint', explode( ',', $gallery ) );
			\WBListora\Core\Meta_Handler::set_value( $post_id, 'gallery', $gallery_ids );
		}

		// Set video.
		$video = esc_url_raw( $request->get_param( 'video' ) ?? '' );
		if ( $video ) {
			\WBListora\Core\Meta_Handler::set_value( $post_id, 'video', $video );
		}

		// Save type-specific meta fields.
		$this->save_meta_fields( $post_id, $type_slug, $request );

		/**
		 * Fires after a listing is submitted from the frontend.
		 *
		 * @param int             $post_id Post ID.
		 * @param string          $status  Post status.
		 * @param WP_REST_Request $request Request.
		 */
		do_action( 'wb_listora_listing_submitted', $post_id, $status, $request );

		return new WP_REST_Response(
			array(
				'id'      => $post_id,
				'status'  => $status,
				'url'     => get_permalink( $post_id ),
				'message' => 'draft' === $status
					? __( 'Draft saved.', 'wb-listora' )
					: __( 'Listing submitted successfully!', 'wb-listora' ),
			),
			201
		);
	}

	/**
	 * Update an existing listing.
	 */
	public function update_listing( $request ) {
		// Nonce check — same nonce used for the submission form.
		$nonce = $request->get_param( 'listora_nonce' );
		if ( $nonce && ! wp_verify_nonce( $nonce, 'listora_submit_listing' ) ) {
			return new WP_Error( 'listora_nonce_failed', __( 'Security check failed.', 'wb-listora' ), array( 'status' => 403 ) );
		}

		$post_id = $request->get_param( 'id' );
		$post    = get_post( $post_id );

		if ( ! $post || 'listora_listing' !== $post->post_type ) {
			return new WP_Error( 'listora_not_found', __( 'Listing not found.', 'wb-listora' ), array( 'status' => 404 ) );
		}

		$update_data = array( 'ID' => $post_id );

		$title = $request->get_param( 'title' );
		if ( null !== $title ) {
			$update_data['post_title'] = sanitize_text_field( $title );
		}

		$description = $request->get_param( 'description' );
		if ( null !== $description ) {
			$update_data['post_content'] = sanitize_textarea_field( $description );
		}

		wp_update_post( $update_data );

		// Update category.
		$category = $request->get_param( 'category' );
		if ( null !== $category ) {
			$category_id = absint( $category );
			if ( $category_id > 0 ) {
				wp_set_object_terms( $post_id, array( $category_id ), 'listora_listing_cat' );
			}
		}

		// Update tags.
		$tags = $request->get_param( 'tags' );
		if ( null !== $tags ) {
			$tags_sanitized = sanitize_text_field( $tags );
			if ( $tags_sanitized ) {
				$tag_array = array_map( 'trim', explode( ',', $tags_sanitized ) );
				wp_set_object_terms( $post_id, $tag_array, 'listora_listing_tag' );
			} else {
				wp_set_object_terms( $post_id, array(), 'listora_listing_tag' );
			}
		}

		// Update featured image.
		$featured_image = $request->get_param( 'featured_image' );
		if ( null !== $featured_image ) {
			$image_id = absint( $featured_image );
			if ( $image_id > 0 ) {
				set_post_thumbnail( $post_id, $image_id );
			}
		}

		// Update gallery.
		$gallery = $request->get_param( 'gallery' );
		if ( null !== $gallery ) {
			if ( $gallery ) {
				$gallery_ids = array_filter( array_map( 'absint', explode( ',', $gallery ) ) );
				\WBListora\Core\Meta_Handler::set_value( $post_id, 'gallery', $gallery_ids );
			} else {
				\WBListora\Core\Meta_Handler::set_value( $post_id, 'gallery', array() );
			}
		}

		// Update video.
		$video = $request->get_param( 'video' );
		if ( null !== $video ) {
			\WBListora\Core\Meta_Handler::set_value( $post_id, 'video', esc_url_raw( $video ) );
		}

		// Update type-specific meta. Use submitted type or fall back to the post's existing type.
		$type_slug_param = sanitize_text_field( $request->get_param( 'listing_type' ) ?? '' );
		if ( $type_slug_param ) {
			$type_slug = $type_slug_param;
		} else {
			$types     = wp_get_object_terms( $post_id, 'listora_listing_type', array( 'fields' => 'slugs' ) );
			$type_slug = ! is_wp_error( $types ) && ! empty( $types ) ? $types[0] : '';
		}

		$this->save_meta_fields( $post_id, $type_slug, $request );

		/**
		 * Fires after a listing is updated from the frontend.
		 *
		 * @param int             $post_id Post ID.
		 * @param string          $status  Post status.
		 * @param WP_REST_Request $request Request.
		 */
		do_action( 'wb_listora_listing_updated', $post_id, get_post_status( $post_id ), $request );

		return new WP_REST_Response(
			array(
				'id'      => $post_id,
				'status'  => get_post_status( $post_id ),
				'url'     => get_permalink( $post_id ),
				'message' => __( 'Your listing has been updated.', 'wb-listora' ),
			),
			200
		);
	}

	/**
	 * REST endpoint: check for duplicate listings before submission.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function check_duplicate_endpoint( $request ) {
		$title = sanitize_text_field( $request->get_param( 'title' ) );
		$type  = sanitize_text_field( $request->get_param( 'type' ) ?? '' );
		$lat   = $request->get_param( 'lat' );
		$lng   = $request->get_param( 'lng' );

		$lat = null !== $lat ? (float) $lat : null;
		$lng = null !== $lng ? (float) $lng : null;

		$duplicates = $this->check_duplicates( $title, $type, $lat, $lng );

		return new WP_REST_Response(
			array(
				'duplicates' => $duplicates,
				'has_match'  => ! empty( $duplicates ),
			),
			200
		);
	}

	/**
	 * Check for potential duplicate listings by title similarity and geo proximity.
	 *
	 * Phase 1: Title similarity — compares against existing listings of the same type.
	 * Phase 2: Geo proximity — if lat/lng provided and no title matches, checks within 100m.
	 *
	 * @param string     $title Listing title.
	 * @param string     $type  Listing type slug.
	 * @param float|null $lat   Latitude.
	 * @param float|null $lng   Longitude.
	 * @return array Array of potential duplicate listings.
	 */
	private function check_duplicates( $title, $type, $lat = null, $lng = null ) {
		global $wpdb;

		$duplicates = array();

		if ( empty( $title ) ) {
			return $duplicates;
		}

		// Phase 1: Title similarity — search existing listings of the same type.
		if ( $type ) {
			$existing = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT p.ID, p.post_title FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
					INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
					INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
					WHERE p.post_type = 'listora_listing'
					AND p.post_status IN ('publish', 'pending', 'draft')
					AND tt.taxonomy = 'listora_listing_type'
					AND t.slug = %s
					LIMIT 100",
					$type
				)
			);
		} else {
			$existing = $wpdb->get_results(
				"SELECT p.ID, p.post_title FROM {$wpdb->posts} p
				WHERE p.post_type = 'listora_listing'
				AND p.post_status IN ('publish', 'pending', 'draft')
				LIMIT 100"
			);
		}

		if ( $existing ) {
			$title_lower = strtolower( $title );

			foreach ( $existing as $post ) {
				similar_text( $title_lower, strtolower( $post->post_title ), $percent );

				if ( $percent > 80 ) {
					$duplicates[] = array(
						'id'         => (int) $post->ID,
						'title'      => $post->post_title,
						'similarity' => round( $percent ),
						'url'        => get_permalink( $post->ID ),
					);
				}
			}
		}

		// Phase 2: If lat/lng provided, check for nearby listings with similar names.
		if ( null !== $lat && null !== $lng && empty( $duplicates ) ) {
			$nearby = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT g.post_id, p.post_title,
					( 6371000 * acos(
						cos( radians( %f ) ) * cos( radians( g.latitude ) )
						* cos( radians( g.longitude ) - radians( %f ) )
						+ sin( radians( %f ) ) * sin( radians( g.latitude ) )
					) ) AS distance
					FROM {$wpdb->prefix}listora_geo g
					INNER JOIN {$wpdb->posts} p ON g.post_id = p.ID
					WHERE p.post_status IN ('publish', 'pending')
					HAVING distance < 100
					ORDER BY distance
					LIMIT 5",
					$lat,
					$lng,
					$lat
				)
			);

			if ( $nearby ) {
				$title_lower = strtolower( $title );

				foreach ( $nearby as $near ) {
					similar_text( $title_lower, strtolower( $near->post_title ), $percent );

					if ( $percent > 60 ) {
						$duplicates[] = array(
							'id'         => (int) $near->post_id,
							'title'      => $near->post_title,
							'similarity' => round( $percent ),
							'distance'   => round( (float) $near->distance ),
							'url'        => get_permalink( $near->post_id ),
						);
					}
				}
			}
		}

		return $duplicates;
	}

	/**
	 * Save type-specific meta fields from the request.
	 */
	private function save_meta_fields( $post_id, $type_slug, $request ) {
		if ( ! $type_slug ) {
			return;
		}

		$registry = \WBListora\Core\Listing_Type_Registry::instance();
		$type     = $registry->get( $type_slug );

		if ( ! $type ) {
			return;
		}

		$all_params = $request->get_params();

		foreach ( $type->get_all_fields() as $field ) {
			$param_key = 'meta_' . $field->get_key();

			if ( ! isset( $all_params[ $param_key ] ) ) {
				continue;
			}

			$value    = $all_params[ $param_key ];
			$sanitize = $field->get_sanitize_callback();

			if ( is_callable( $sanitize ) ) {
				$value = call_user_func( $sanitize, $value );
			}

			\WBListora\Core\Meta_Handler::set_value( $post_id, $field->get_key(), $value );
		}
	}

	/**
	 * Determine the post status for a new submission.
	 */
	private function get_submission_status() {
		$moderation = wb_listora_get_setting( 'moderation', 'manual' );

		if ( 'auto_approve' === $moderation ) {
			return 'publish';
		}

		if ( current_user_can( 'publish_listora_listings' ) ) {
			return 'publish';
		}

		return 'pending';
	}
}
