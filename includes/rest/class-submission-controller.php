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
					'permission_callback' => array( $this, 'submit_listing_permissions' ),
					'args'                => array(
						'agree_terms'             => array(
							'type'              => 'boolean',
							// No 'default'. A default makes get_param() return
							// false for a request that never mentioned the
							// field, which is indistinguishable from one that
							// explicitly declined — and that killed
							// check_terms_acceptance()'s $default_value on this
							// route. The "an edit is not a fresh acceptance"
							// rule it documents therefore never applied, so
							// every edit posted here was refused unless it
							// re-sent consent. Absent must stay null so each
							// caller can decide what absence means.
							'sanitize_callback' => 'rest_sanitize_boolean',
						),
						'confirmed_not_duplicate' => array(
							'type'              => 'boolean',
							'default'           => false,
							'sanitize_callback' => 'rest_sanitize_boolean',
						),
						// Feature term IDs. Array from the form's `features[]`
						// checkboxes; a comma-separated string is also accepted
						// for API callers.
						'features'                => array(
							'required' => false,
						),
						// Complete category set. Replaces every assigned
						// category, unlike the single `category` field which
						// only speaks for the one slot the form renders.
						'categories'              => array(
							'required' => false,
						),
						'duplicate_explanation'   => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
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
						if ( ! is_user_logged_in() ) {
							return new \WP_Error(
								'listora_unauthorized',
								__( 'You do not have permission to perform this action.', 'wb-listora' ),
								array( 'status' => 401 )
							);
						}
						return true;
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

		// POST /submission/resend-verification — resend the verify-email link.
		register_rest_route(
			$this->namespace,
			'/submission/resend-verification',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'resend_verification_endpoint' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'listing_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'email'      => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_email',
						),
					),
				),
			)
		);

		// GET /submission/verify — REST mirror of the public verify URL.
		register_rest_route(
			$this->namespace,
			'/submission/verify',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'verify_endpoint' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'listing_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'token'      => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
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
					'args'                => array(
						// No default, unlike /submit. An edit that never
						// mentions terms is an edit, not a fresh acceptance —
						// only an explicit `false` is refused.
						'agree_terms' => array(
							'type'              => 'boolean',
							'sanitize_callback' => 'rest_sanitize_boolean',
						),
						'features'    => array(
							'required' => false,
						),
						// Complete category set; see the /submit route.
						'categories'  => array(
							'required' => false,
						),
					),
					'permission_callback' => function ( $request ) {
						if ( ! is_user_logged_in() ) {
							return new \WP_Error(
								'listora_unauthorized',
								__( 'You do not have permission to perform this action.', 'wb-listora' ),
								array( 'status' => 401 )
							);
						}
						$post = get_post( $request->get_param( 'id' ) );
						if ( ! $post || (int) $post->post_author !== get_current_user_id() ) {
							return new \WP_Error(
								'listora_forbidden',
								__( 'You do not have permission to perform this action.', 'wb-listora' ),
								array( 'status' => 403 )
							);
						}
						return true;
					},
				),
			)
		);
	}

	/**
	 * Permission callback for listing submissions.
	 *
	 * Allows logged-in users with submit_listora_listing capability,
	 * or non-logged-in guests when guest submission is enabled and
	 * guest fields are present in the request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|\WP_Error
	 */
	public function submit_listing_permissions( $request ) {
		// The submission feature toggle gates the REST endpoint, not just the
		// block UI — otherwise a capable user could POST directly and create a
		// listing while submissions are disabled site-wide.
		if ( function_exists( 'wb_listora_feature_enabled' ) && ! wb_listora_feature_enabled( 'submission' ) ) {
			return new \WP_Error(
				'listora_submission_disabled',
				__( 'Listing submission is currently disabled.', 'wb-listora' ),
				array( 'status' => 403 )
			);
		}

		if ( current_user_can( 'submit_listora_listing' ) ) {
			return true;
		}

		// Listing submission requires an account — there is no guest path.
		// A logged-out visitor is directed to log in / register by the
		// submission block, and reaches this endpoint only via a crafted
		// request, which we reject here.
		return new \WP_Error(
			'listora_unauthorized',
			__( 'Please log in to submit a listing.', 'wb-listora' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Meta key recording that the submitter accepted the Terms of Service.
	 *
	 * Stores the GMT timestamp of acceptance. Deliberately no IP address: that
	 * would create a new personal-data surface the privacy exporter/eraser
	 * would then have to carry, and the timestamp alone is what makes the
	 * consent auditable.
	 *
	 * @var string
	 */
	const TERMS_META_KEY = '_listora_terms_accepted';

	/**
	 * Resolve posted feature term IDs to ones that actually exist.
	 *
	 * Members choose from a checkbox list the site owner curates, so anything
	 * that is not a real `listora_listing_feature` term is dropped rather than
	 * created — features are a controlled vocabulary, and letting a submission
	 * mint new ones is what tags are for.
	 *
	 * @since 1.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return int[]|null|\WP_Error Term IDs, null when the client did not send
	 *                                the field, or WP_Error when it sent a feature
	 *                                the listing type does not allow. Every caller
	 *                                checks is_wp_error() and returns it.
	 */
	private function resolve_feature_terms( $request ) {
		$raw = $request->get_param( 'features' );

		if ( null === $raw ) {
			return null;
		}

		$candidates = is_array( $raw )
			? $raw
			: preg_split( '/[\s,]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY );

		$ids = array();

		foreach ( (array) $candidates as $candidate ) {
			$term_id = absint( $candidate );

			if ( $term_id <= 0 ) {
				continue;
			}

			$term = get_term( $term_id, 'listora_listing_feature' );

			if ( $term && ! is_wp_error( $term ) ) {
				$ids[] = (int) $term->term_id;
			}
		}

		$ids = array_values( array_unique( $ids ) );

		/*
		 * Honour the listing type's feature allowlist.
		 *
		 * A site owner who restricts a type to five features expects those five
		 * to be the only ones a listing of that type can carry. The check above
		 * only asked whether a term EXISTS, so a client posting any feature ID
		 * in the taxonomy had it accepted — the restriction held only while the
		 * form happened to render the right boxes, and not at all for a direct
		 * REST call.
		 *
		 * An empty allowlist means "every feature", which is the historical
		 * default and what every type has until an owner opts one in.
		 */
		$type_slug = sanitize_title( (string) ( $request->get_param( 'listing_type' ) ?? '' ) );

		if ( '' === $type_slug || empty( $ids ) ) {
			return $ids;
		}

		$registry = \WBListora\Core\Listing_Type_Registry::instance();
		$registry->init();
		$type = $registry->get( $type_slug );

		if ( ! $type || ! method_exists( $type, 'get_allowed_features' ) ) {
			return $ids;
		}

		$allowed = array_values( array_filter( array_map( 'absint', (array) $type->get_allowed_features() ) ) );

		if ( empty( $allowed ) ) {
			return $ids;
		}

		$disallowed = array_values( array_diff( $ids, $allowed ) );

		if ( empty( $disallowed ) ) {
			return array_values( array_intersect( $ids, $allowed ) );
		}

		/**
		 * Whether to refuse a submission carrying features the type disallows.
		 *
		 * True (default) returns 400 naming the offending term ids. Return
		 * false to drop them silently and accept the rest, which is what
		 * happened before 1.7.0.
		 *
		 * @since 1.7.0
		 *
		 * @param bool  $refuse     Refuse the request.
		 * @param int[] $disallowed Term ids the type does not allow.
		 * @param string $type_slug Listing type being submitted.
		 */
		$refuse = (bool) apply_filters( 'wb_listora_refuse_disallowed_features', true, $disallowed, $type_slug );

		if ( ! $refuse ) {
			return array_values( array_intersect( $ids, $allowed ) );
		}

		// Say what was refused rather than quietly dropping it. The old
		// behaviour intersected the extras away and returned 201, so a client
		// was told its submission succeeded exactly as sent while some of what
		// it sent had been discarded — and had no way to discover that except
		// by re-reading the listing afterwards.
		return new \WP_Error(
			'listora_feature_not_allowed',
			__( 'One or more selected features are not available for this listing type.', 'wb-listora' ),
			array(
				'status' => 400,
				'params' => array(
					'features' => __( 'Some of these features are not available for the chosen listing type.', 'wb-listora' ),
				),
				'data'   => array(
					'disallowed' => $disallowed,
					'allowed'    => $allowed,
				),
			)
		);
	}

	/**
	 * Sanitize a loose category payload into existing term ids.
	 *
	 * @since 1.6.0
	 *
	 * @param mixed $raw Array, or a comma/space separated string.
	 * @return int[] Term ids that exist in listora_listing_cat.
	 */
	private function sanitize_category_ids( $raw ) {
		$candidates = is_array( $raw )
			? $raw
			: preg_split( '/[\s,]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY );

		$ids = array();

		foreach ( (array) $candidates as $candidate ) {
			$term_id = absint( $candidate );

			if ( $term_id <= 0 ) {
				continue;
			}

			$term = get_term( $term_id, 'listora_listing_cat' );

			if ( $term && ! is_wp_error( $term ) ) {
				$ids[] = (int) $term->term_id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Resolve the category term set to write for a submission.
	 *
	 * The frontend form carries a single `category` select, but the data model,
	 * REST reads, importers, exporters, migrators and the related-listings block
	 * all treat categories as multi-valued. Writing the form's one value as the
	 * entire term set therefore destroyed every other category a listing carried
	 * (BC 10203063915) — reproduced as a silent HTTP 200 that dropped a term.
	 *
	 * So `category` is treated as what it actually is: a statement about the one
	 * slot the form can express. It replaces the term the form was showing —
	 * `$edit_cat_terms[0]` in blocks/listing-submission/render.php — and leaves
	 * the rest alone. Clients that genuinely own the whole set send `categories`,
	 * which IS a complete statement and replaces wholesale.
	 *
	 * @since 1.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @param int              $post_id Listing being updated; 0 when creating.
	 * @return int[]|null Term ids to write, or null to leave categories untouched.
	 */
	private function resolve_category_terms( $request, $post_id = 0 ) {
		$complete = $request->get_param( 'categories' );

		if ( null !== $complete ) {
			return $this->sanitize_category_ids( $complete );
		}

		$primary = $request->get_param( 'category' );

		if ( null === $primary ) {
			return null;
		}

		$primary = $this->sanitize_category_ids( array( $primary ) );

		if ( ! $primary ) {
			return null;
		}

		if ( $post_id <= 0 ) {
			return $primary;
		}

		$existing = wp_get_object_terms( $post_id, 'listora_listing_cat', array( 'fields' => 'ids' ) );

		if ( is_wp_error( $existing ) || empty( $existing ) ) {
			return $primary;
		}

		// Drop only the slot the form was showing; keep what it could not express.
		$preserved = array_slice( array_map( 'absint', $existing ), 1 );

		return array_values( array_unique( array_merge( $primary, $preserved ) ) );
	}

	/**
	 * Require Terms of Service acceptance on a submission.
	 *
	 * Returns WP_Error when consent is missing so callers can bail early.
	 *
	 * @since 1.6.0
	 *
	 * @param \WP_REST_Request $request       Request.
	 * @param bool             $default_value Value assumed when the param is absent.
	 *                                        True on edit (an edit is not a fresh
	 *                                        acceptance), false on create.
	 * @return true|WP_Error
	 */
	private function check_terms_acceptance( $request, $default_value = false ) {
		/**
		 * Filters whether Terms of Service acceptance is enforced.
		 *
		 * Honouring a previously-ignored parameter is a behaviour change, so
		 * production rule 3 requires an escape hatch. Two audiences need it:
		 *
		 * - Integrators posting to `/submit` from their own client before
		 *   1.6.0, while they add the field.
		 * - Sites that set the submission block's `showTerms` attribute to
		 *   false. That attribute lives on the block, not in site settings, so
		 *   the REST layer cannot read it — those sites render no checkbox,
		 *   send no `agree_terms`, and must opt out here:
		 *
		 *       add_filter( 'wb_listora_require_terms_acceptance', '__return_false' );
		 *
		 * The default stays "required" because this is a legal gate: failing
		 * closed is the only safe direction when the answer is unknown.
		 *
		 * @since 1.6.0
		 *
		 * @param bool             $required Whether acceptance is required.
		 * @param \WP_REST_Request $request  The submission request.
		 */
		if ( ! apply_filters( 'wb_listora_require_terms_acceptance', true, $request ) ) {
			return true;
		}

		$accepted = $request->get_param( 'agree_terms' );
		$accepted = null === $accepted ? $default_value : rest_sanitize_boolean( $accepted );

		if ( ! $accepted ) {
			return new WP_Error(
				'listora_terms_required',
				// Same string the form shows, so the client can surface the
				// server's message without a second translation.
				__( 'Please accept the Terms of Service to continue.', 'wb-listora' ),
				array( 'status' => 400 )
			);
		}

		return true;
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

		// ─── Rate limiting ───
		// Centralised in \WBListora\Rate_Limiter so every public POST
		// endpoint shares the same per-user / per-IP transient counters.

		$rate_check = \WBListora\Rate_Limiter::check( 'submission' );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		// ─── CAPTCHA verification ───

		$captcha_token    = sanitize_text_field( $request->get_param( 'listora_captcha_token' ) ?? '' );
		$captcha_provider = sanitize_text_field( $request->get_param( 'listora_captcha_provider' ) ?? '' );

		$captcha_result = \WBListora\Captcha::verify( $captcha_token, $captcha_provider );
		if ( is_wp_error( $captcha_result ) ) {
			return $captcha_result;
		}

		// ─── Anti-spam (Akismet + keyword + URL density) ───
		//
		// Captcha catches bots; Anti_Spam catches paid-human spammers. Admins
		// + editors are exempt by default (see Anti_Spam::check filter).
		$submission_author = wp_get_current_user();
		$antispam_result   = \WBListora\Anti_Spam::check(
			array(
				'title'        => (string) $request->get_param( 'title' ),
				'description'  => (string) $request->get_param( 'description' ),
				'author_name'  => (string) $submission_author->display_name,
				'author_email' => (string) $submission_author->user_email,
				'author_url'   => (string) ( $request->get_param( 'website' ) ?? '' ),
				'source'       => 'submission',
			)
		);
		if ( is_wp_error( $antispam_result ) ) {
			return $antispam_result;
		}

		// Submission is account-only — the author is always the logged-in
		// user. (Guest submission was removed: no anonymous account creation,
		// no guest email-verification path.) These two remain so the shared
		// downstream code that references them keeps working unchanged.
		$guest_author_id       = 0;
		$verification_required = false;

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

		// Force pending_verification when this submission requires email
		// verification — overrides moderation/auto_approve for the initial
		// state, then transitions on token consumption.
		if ( $verification_required ) {
			$status = 'pending_verification';
		} else {
			$status = $request->get_param( 'status' ) === 'draft' ? 'draft' : $this->get_submission_status();
		}

		// Refuse a disallowed feature BEFORE the listing is written. Checking
		// it at the point the terms are set would leave a created listing
		// behind alongside the 400 — a refusal that still changed the site.
		$listora_feature_check = $this->resolve_feature_terms( $request );
		if ( is_wp_error( $listora_feature_check ) ) {
			return $listora_feature_check;
		}

		if ( empty( $title ) ) {
			return new WP_Error( 'listora_title_required', __( 'Title is required.', 'wb-listora' ), array( 'status' => 400 ) );
		}

		// A web address is not a business name. See wb_listora_title_is_url()
		// for why this refuses only a title that IS an address, and leaves
		// names like "Booking.com" alone.
		if ( wb_listora_title_is_url( $title ) ) {
			return new WP_Error(
				'listora_title_is_url',
				__( 'Enter the name of the business or listing, not a web address.', 'wb-listora' ),
				array(
					'status' => 400,
					'field'  => 'title',
				)
			);
		}

		// Terms of Service. The checkbox on the Preview step was the only gate
		// until 1.6.0, and in wizard layout nothing validated it — the step is
		// reached by Next and Submit skipped validation entirely, so a listing
		// could be created with no consent at all. Client-side gates are also
		// trivially bypassable by any direct REST caller, which is why this
		// check lives here rather than only in the form.
		/*
		 * A listing must have a TYPE.
		 *
		 * `listing_type` was optional, so a REST caller could create a listing
		 * with none — and a typeless listing is broken data rather than a valid
		 * state: it cannot be found by the type filter, it gets none of its
		 * type's custom fields, and it renders a blank chip with no icon
		 * (BC 10213032167).
		 *
		 * Enforced from 1.6.0, in the SAME release as the `agree_terms` gate so
		 * integrators adapt once instead of twice. Escape hatch mirrors that
		 * one, per production rule 3:
		 *
		 *     add_filter( 'wb_listora_require_listing_type', '__return_false' );
		 *
		 * The value must also be a type that EXISTS. Accepting an unregistered
		 * slug just moves the broken state one step later, where it is harder
		 * to explain.
		 */
		if ( apply_filters( 'wb_listora_require_listing_type', true, $request ) ) {
			if ( '' === $type_slug ) {
				return new WP_Error(
					'listora_listing_type_required',
					__( 'Please choose a listing type.', 'wb-listora' ),
					array( 'status' => 400 )
				);
			}

			if ( ! term_exists( $type_slug, 'listora_listing_type' ) ) {
				return new WP_Error(
					'listora_listing_type_invalid',
					sprintf(
						/* translators: %s: submitted listing type slug. */
						__( '"%s" is not a listing type on this site.', 'wb-listora' ),
						$type_slug
					),
					array( 'status' => 400 )
				);
			}
		}

		// Terms of Service. A draft is exempt: it publishes nothing, so consent
		// is not due yet, and the checkbox lives on the wizard's LAST step. With
		// the gate applied to drafts, every autosave fired while the member was
		// still typing returned 400 listora_terms_required into an empty catch —
		// so the draft feature was silently dead for the whole wizard, which is
		// exactly when a member needs it (leaving to buy credits, back button,
		// session timeout). Consent is still mandatory to go live: the draft ->
		// publish transition in update_listing() demands it explicitly rather
		// than defaulting to accepted.
		$terms_accepted = true;
		if ( 'draft' === $status ) {
			// A draft may be saved without consent, so it may also carry none.
			$terms_accepted = (bool) rest_sanitize_boolean( $request->get_param( 'agree_terms' ) );
		} else {
			$terms_check = $this->check_terms_acceptance( $request );
			if ( is_wp_error( $terms_check ) ) {
				return $terms_check;
			}
		}

		// Duplicate check — skip if the client has confirmed it is not a duplicate.
		$confirmed_not_duplicate = rest_sanitize_boolean( $request->get_param( 'confirmed_not_duplicate' ) );
		if ( ! $confirmed_not_duplicate ) {
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
		} else {
			// Bypassing duplicate check requires a substantive explanation so
			// moderators can review WHY this is not actually a duplicate.
			$explanation_check = trim( (string) ( $request->get_param( 'duplicate_explanation' ) ?? '' ) );
			if ( strlen( $explanation_check ) < 20 ) {
				return new WP_Error(
					'listora_duplicate_explanation_required',
					__( 'Please explain how your business is different from the listed duplicates (at least 20 characters).', 'wb-listora' ),
					array( 'status' => 400 )
				);
			}
		}

		// Create the post.
		$author_id = $guest_author_id > 0 ? $guest_author_id : get_current_user_id();

		/**
		 * Filters whether to allow creating a listing. Return WP_Error to abort.
		 *
		 * @param bool|WP_Error   $check   True to proceed, WP_Error to abort.
		 * @param string          $title   Listing title.
		 * @param WP_REST_Request $request REST request.
		 */
		$check = apply_filters( 'wb_listora_before_create_listing', true, $title, $request );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$post_data = array(
			'post_type'    => 'listora_listing',
			'post_title'   => $title,
			'post_content' => $description,
			'post_status'  => $status,
			'post_author'  => $author_id,
		);

		// Wrap multi-step write in a transaction to prevent orphaned data.
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		try {
			$post_id = wp_insert_post( $post_data, true );

			if ( is_wp_error( $post_id ) ) {
				$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				return $post_id;
			}

			// Record the Terms of Service acceptance that got us here. Before
			// 1.6.0 nothing was written anywhere, so an accepted submission and
			// a bypassed one were indistinguishable after the fact.
			//
			// Conditional since drafts became exempt from the gate: stamping
			// this unconditionally was safe only while nothing could reach the
			// insert ungated. An unconsented draft that recorded consent would
			// be worse than the bug that motivated the exemption — the preview
			// step reads this meta to PRE-TICK the checkbox, so the member
			// would be shown their own agreement to something they never saw.
			if ( $terms_accepted ) {
				update_post_meta( $post_id, self::TERMS_META_KEY, current_time( 'mysql', true ) );
			}

			// Set listing type.
			if ( $type_slug ) {
				wp_set_object_terms( $post_id, $type_slug, 'listora_listing_type' );
			}

			// Set category (multi-valued; see resolve_category_terms()).
			$category_ids = $this->resolve_category_terms( $request );
			if ( null !== $category_ids ) {
				wp_set_object_terms( $post_id, $category_ids, 'listora_listing_cat' );
			}

			// Set tags.
			if ( $tags ) {
				$tag_array = array_map( 'trim', explode( ',', $tags ) );
				wp_set_object_terms( $post_id, $tag_array, 'listora_listing_tag' );
			}

			// Features (amenities). Assignable only from the wp-admin block
			// editor sidebar until 1.6.0, so member-created listings could
			// never carry one (BC 10198974105).
			$feature_ids = $this->resolve_feature_terms( $request );
			if ( ! is_wp_error( $feature_ids ) && null !== $feature_ids ) {
				wp_set_object_terms( $post_id, $feature_ids, 'listora_listing_feature' );
			}

			// Set featured image.
			// Ownership-checked: a media ID is a guess away from someone
			// else's file, and the public detail response hands out its
			// uploads URL. See wb_listora_user_can_attach().
			$featured_image = absint( $request->get_param( 'featured_image' ) ?? 0 );
			if ( $featured_image > 0 && wb_listora_user_can_attach( $featured_image ) ) {
				set_post_thumbnail( $post_id, $featured_image );
				// Parent it too. set_post_thumbnail() writes _thumbnail_id and
				// nothing else, so without this the file stays "Unattached" in
				// the Media Library with an empty "Uploaded to" column.
				wb_listora_attach_media_to_listing( $post_id, array( $featured_image ) );
			}

			// Set gallery. BC 9901104724 — server-side enforces the
			// max_gallery_images setting so the client-side cap can't be
			// bypassed via a direct REST POST. Excess IDs are trimmed
			// silently (the client UI surfaces the cap; this is the
			// defense-in-depth guard).
			$gallery = $request->get_param( 'gallery' );
			if ( $gallery ) {
				$gallery_ids = wb_listora_filter_attachable_ids( explode( ',', $gallery ) );
				$max_gallery = max( 1, (int) wb_listora_get_setting( 'max_gallery_images', 20 ) );
				if ( count( $gallery_ids ) > $max_gallery ) {
					$gallery_ids = array_slice( $gallery_ids, 0, $max_gallery );
				}
				\WBListora\Core\Meta_Handler::set_value( $post_id, 'gallery', $gallery_ids );
				wb_listora_attach_media_to_listing( $post_id, $gallery_ids );
			}

			// Set video.
			$video = esc_url_raw( $request->get_param( 'video' ) ?? '' );
			if ( $video ) {
				\WBListora\Core\Meta_Handler::set_value( $post_id, 'video', $video );
			}

			// Save type-specific meta fields.
			$this->save_meta_fields( $post_id, $type_slug, $request );

			// Persist the user-supplied "different business" explanation when
			// they bypassed the duplicate check. Stored as post meta so admins
			// can review it during moderation. Also flag the listing so the
			// admin column can surface it without a meta_query on text.
			if ( $confirmed_not_duplicate ) {
				$explanation = sanitize_textarea_field( (string) ( $request->get_param( 'duplicate_explanation' ) ?? '' ) );
				if ( '' !== $explanation ) {
					update_post_meta( $post_id, '_listora_duplicate_explanation', $explanation );
				}
				update_post_meta( $post_id, '_listora_duplicate_confirmed', '1' );
			}

			$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		} catch ( \Exception $e ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return new WP_Error(
				'listora_submission_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}

		/**
		 * Fires after a listing is submitted from the frontend.
		 *
		 * Skipped while a listing sits in pending_verification — the admin
		 * notification fires instead from the verification handler once the
		 * email has been confirmed, so admins are never asked to review a
		 * listing that may still be abandoned.
		 *
		 * @param int             $post_id Post ID.
		 * @param string          $status  Post status.
		 * @param WP_REST_Request $request Request.
		 */
		if ( 'pending_verification' !== $status ) {
			// 4th arg `$context` (1.1.0+) — empty array = user-driven submission.
			do_action( 'wb_listora_listing_submitted', $post_id, $status, $request, array() );
		}

		/**
		 * Fires after a listing is created via the submission form.
		 *
		 * @param int             $post_id Post ID.
		 * @param WP_REST_Request $request REST request.
		 */
		do_action( 'wb_listora_after_create_listing', $post_id, $request );

		// Dispatch the verification email now that the listing exists.
		if ( $verification_required && 'pending_verification' === $status ) {
			\WBListora\Workflow\Email_Verification::send_verification_email( $post_id );

			$response_data = array(
				'id'                    => $post_id,
				'listing_id'            => $post_id,
				'status'                => $status,
				'verification_required' => true,
				'message'               => __( 'Check your inbox to verify your email and publish your listing.', 'wb-listora' ),
				'email'                 => isset( $guest_email ) ? $guest_email : '',
			);

			/**
			 * Filters the listing-submission REST response data when verification is required.
			 *
			 * @param array           $response_data Response payload.
			 * @param \WP_Post        $post          Post object.
			 * @param WP_REST_Request $request       REST request.
			 */
			$response_data = apply_filters( 'wb_listora_rest_prepare_listing', $response_data, get_post( $post_id ), $request );

			return new WP_REST_Response( $response_data, 202 );
		}

		// Re-read post status. Pro's plan-on-submit handler may have flipped
		// the listing to `listora_payment` if credit deduction failed — the
		// vendor must be told their listing is paused right here in the
		// submission response, not discover it later by hunting through
		// their dashboard. The status_now is the source of truth for the
		// success message.
		$status_now = get_post_status( $post_id );

		$response_data = array(
			'id'     => $post_id,
			'status' => $status_now,
			'url'    => get_permalink( $post_id ),
		);

		if ( 'listora_payment' === $status_now ) {
			$response_data['paused']  = true;
			$response_data['message'] = __( 'Listing saved. It will activate as soon as you top up enough credits to cover the selected plan.', 'wb-listora' );
		} elseif ( 'draft' === $status_now ) {
			$response_data['paused']  = false;
			$response_data['message'] = __( 'Draft saved.', 'wb-listora' );
		} else {
			$response_data['paused']  = false;
			$response_data['message'] = __( 'Listing submitted successfully!', 'wb-listora' );
		}

		/**
		 * Filters the listing submission REST response data.
		 *
		 * Pro's Pricing_Plans hooks into this filter to attach pending
		 * plan context (`pending_plan_id`, `plan_name`, `credits_required`,
		 * `current_balance`, `credits_short`) when the listing is paused.
		 *
		 * @param array           $response_data Response data.
		 * @param \WP_Post        $post          Post object.
		 * @param WP_REST_Request $request       REST request.
		 */
		$response_data = apply_filters( 'wb_listora_rest_prepare_listing', $response_data, get_post( $post_id ), $request );

		return new WP_REST_Response( $response_data, 201 );
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

		/**
		 * Filters whether to allow updating a listing. Return WP_Error to abort.
		 *
		 * @param bool|WP_Error   $check      True to proceed, WP_Error to abort.
		 * @param int             $post_id    Post ID.
		 * @param WP_REST_Request $request    REST request.
		 */
		$check = apply_filters( 'wb_listora_before_update_listing', true, $post_id, $request );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// Same pre-write refusal as the create path. Without it an edit that
		// carried a disallowed feature returned 200 with the features simply
		// not applied — the silent drop, one entry point further along.
		$listora_feature_check = $this->resolve_feature_terms( $request );
		if ( is_wp_error( $listora_feature_check ) ) {
			return $listora_feature_check;
		}

		// Terms of Service. Defaulted true here: an edit that never mentions
		// the field is an edit, not a fresh acceptance, and refusing those
		// would break every partial update. An explicit `false` — which is what
		// the edit form posts when the box is unticked — is still refused.
		//
		// The draft -> live transition is the exception and defaults to REFUSED.
		// Drafts are exempt from the gate on create, so this is the first and
		// only point at which the submitter's consent is due; defaulting it to
		// accepted here would publish a listing whose author never agreed to
		// anything, by saving a draft and then submitting it.
		$terms_default = ! ( 'draft' === $post->post_status && 'draft' !== $request->get_param( 'status' ) );
		$terms_check   = $this->check_terms_acceptance( $request, $terms_default );
		if ( is_wp_error( $terms_check ) ) {
			return $terms_check;
		}

		// Stamp the acceptance when a draft is the one being taken live. Only
		// the create path recorded this, so a listing that started as an exempt
		// draft would go public with no record of consent anywhere — the very
		// audit gap the meta key was introduced to close.
		if ( ! $terms_default && ! get_post_meta( $post_id, self::TERMS_META_KEY, true ) ) {
			update_post_meta( $post_id, self::TERMS_META_KEY, current_time( 'mysql', true ) );
		}

		$update_data = array( 'ID' => $post_id );

		$title = $request->get_param( 'title' );
		if ( null !== $title ) {
			// Same check as the create path. Without it here, editing is a
			// second way to the same result — which is how a guard on one
			// route and not the other reads as fixed while the gap stays open.
			if ( wb_listora_title_is_url( $title ) ) {
				return new WP_Error(
					'listora_title_is_url',
					__( 'Enter the name of the business or listing, not a web address.', 'wb-listora' ),
					array(
						'status' => 400,
						'field'  => 'title',
					)
				);
			}

			$update_data['post_title'] = sanitize_text_field( $title );
		}

		$description = $request->get_param( 'description' );
		if ( null !== $description ) {
			$update_data['post_content'] = sanitize_textarea_field( $description );
		}

		// Publish a still-draft listing when the caller submits it (i.e. is
		// not explicitly re-saving as a draft). Without this, "Publish" on a
		// saved draft only rewrote title/content and left post_status = draft,
		// so the listing never went live. Resolve the target state with the
		// same moderation-aware logic as a new submission (auto-approve →
		// publish, otherwise → pending). Never downgrade an already-live
		// listing here — only transition out of draft.
		// Distinguish a draft being PUBLISHED (submitted) from a re-save as draft
		// or an edit to an already-live listing. Only the publish transition
		// runs the monetization path below, so an already-live listing is never
		// re-charged or downgraded through this endpoint.
		$was_draft            = ( 'draft' === $post->post_status );
		$saving_as_draft      = ( 'draft' === $request->get_param( 'status' ) );
		$is_submit_transition = $was_draft && ! $saving_as_draft;

		if ( $was_draft ) {
			$update_data['post_status'] = $saving_as_draft
				? 'draft'
				: $this->get_submission_status();
		}

		wp_update_post( $update_data );

		// Update category. Non-destructive for the categories the single-select
		// form cannot express; see resolve_category_terms().
		$category_ids = $this->resolve_category_terms( $request, $post_id );
		if ( null !== $category_ids ) {
			wp_set_object_terms( $post_id, $category_ids, 'listora_listing_cat' );
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

		// Update features (amenities).
		//
		// Only when the client actually sent the field: a partial update that
		// never mentions features must not wipe the ones an admin assigned
		// from wp-admin. An empty array IS meaningful — it means the member
		// unticked everything.
		$feature_ids = $this->resolve_feature_terms( $request );
		if ( ! is_wp_error( $feature_ids ) && null !== $feature_ids ) {
			wp_set_object_terms( $post_id, $feature_ids, 'listora_listing_feature' );
		}

		// Update featured image.
		$featured_image = $request->get_param( 'featured_image' );
		if ( null !== $featured_image ) {
			$image_id = absint( $featured_image );
			if ( $image_id > 0 && wb_listora_user_can_attach( $image_id ) ) {
				set_post_thumbnail( $post_id, $image_id );
				wb_listora_attach_media_to_listing( $post_id, array( $image_id ) );
			}
		}

		// Update gallery. BC 9901104724 — same max_gallery_images guard as
		// the create path so edits can't grow a gallery past the cap.
		$gallery = $request->get_param( 'gallery' );
		if ( null !== $gallery ) {
			if ( $gallery ) {
				$gallery_ids = wb_listora_filter_attachable_ids( explode( ',', $gallery ) );
				$max_gallery = max( 1, (int) wb_listora_get_setting( 'max_gallery_images', 20 ) );
				if ( count( $gallery_ids ) > $max_gallery ) {
					$gallery_ids = array_slice( $gallery_ids, 0, $max_gallery );
				}
				\WBListora\Core\Meta_Handler::set_value( $post_id, 'gallery', $gallery_ids );
				wb_listora_attach_media_to_listing( $post_id, $gallery_ids );
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

		// A draft going live must run the SAME plan/credit path as a fresh
		// submission. Without this, a vendor could save a plan-gated listing as
		// a draft (no charge), then Publish it live with no credit hold and no
		// plan activation — a revenue bypass on monetized (Free+Pro) sites.
		// Firing the canonical submission hook AFTER the plan meta is saved lets
		// Pro's Pricing_Plans hold/deduct credits and, when the balance is
		// short, flip the listing to `listora_payment` (paused) exactly as it
		// would for a brand-new submission. Editing an already-live listing
		// never sets $is_submit_transition, so it is never re-charged.
		$submit_status = get_post_status( $post_id );
		if ( $is_submit_transition && 'pending_verification' !== $submit_status ) {
			// 4th arg $context — empty array = user-driven submission (matches
			// the create path; migration/import paths pass a source to opt out).
			do_action( 'wb_listora_listing_submitted', $post_id, $submit_status, $request, array() );
		}

		/**
		 * Fires after a listing is updated from the frontend.
		 *
		 * @param int             $post_id Post ID.
		 * @param string          $status  Post status.
		 * @param WP_REST_Request $request Request.
		 */
		do_action( 'wb_listora_listing_updated', $post_id, get_post_status( $post_id ), $request );

		/**
		 * Fires after a listing is updated via the submission form.
		 *
		 * @param int             $post_id Post ID.
		 * @param WP_REST_Request $request REST request.
		 */
		do_action( 'wb_listora_after_update_listing', $post_id, $request );

		// Re-read status: Pro's plan-on-submit handler may have flipped the
		// listing to `listora_payment` when credits were short, so the vendor is
		// told their listing is paused right here instead of discovering it later.
		$status_now    = get_post_status( $post_id );
		$response_data = array(
			'id'      => $post_id,
			'status'  => $status_now,
			'url'     => get_permalink( $post_id ),
			'message' => __( 'Your listing has been updated.', 'wb-listora' ),
		);

		if ( 'listora_payment' === $status_now ) {
			$response_data['paused']  = true;
			$response_data['message'] = __( 'Listing saved. It will activate as soon as you top up enough credits to cover the selected plan.', 'wb-listora' );
		}

		/**
		 * Filters the listing update REST response data.
		 *
		 * @param array           $response_data Response data.
		 * @param \WP_Post        $post          Post object.
		 * @param WP_REST_Request $request       REST request.
		 */
		$response_data = apply_filters( 'wb_listora_rest_prepare_listing', $response_data, get_post( $post_id ), $request );

		return new WP_REST_Response( $response_data, 200 );
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

		// Phase 1: Title similarity — use SQL LIKE for initial filtering, then refine with similar_text.
		// This avoids loading 100 rows and doing O(n) string comparisons in PHP.
		$like_title = '%' . $wpdb->esc_like( $title ) . '%';

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
					AND p.post_title LIKE %s
					LIMIT 20",
					$type,
					$like_title
				)
			);
		} else {
			$existing = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT p.ID, p.post_title FROM {$wpdb->posts} p
					WHERE p.post_type = 'listora_listing'
					AND p.post_status IN ('publish', 'pending', 'draft')
					AND p.post_title LIKE %s
					LIMIT 20",
					$like_title
				)
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
			// The geo table columns are listing_id / lat / lng (see
			// class-activator.php geo schema). The earlier query referenced
			// post_id / latitude / longitude — columns that do not exist — so
			// the proximity guard silently errored and never ran. listing_id is
			// aliased to post_id so the result-row reads below stay unchanged.
			$nearby = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT g.listing_id AS post_id, p.post_title,
					( 6371000 * acos(
						cos( radians( %f ) ) * cos( radians( g.lat ) )
						* cos( radians( g.lng ) - radians( %f ) )
						+ sin( radians( %f ) ) * sin( radians( g.lat ) )
					) ) AS distance
					FROM {$wpdb->prefix}listora_geo g
					INNER JOIN {$wpdb->posts} p ON g.listing_id = p.ID
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
	 * REST: resend verification email.
	 *
	 * Accepts `{ listing_id, email? }`. When the listing is owned by a
	 * logged-in user we trust the cookie. For guests we require the email
	 * address to match the listing author.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function resend_verification_endpoint( $request ) {
		// F-02: per-IP rate limit on top of the per-listing 5-min cooldown
		// inside Email_Verification::resend_verification. The existing
		// per-listing cooldown blocks spam against a single listing; this
		// IP cap stops an attacker from probing many listing IDs in
		// sequence (a 403 from the email-match gate still costs DB queries).
		$gate = \WBListora\Rate_Limiter::check( 'resend_verification' );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$listing_id = absint( $request->get_param( 'listing_id' ) );
		$email      = sanitize_email( $request->get_param( 'email' ) ?? '' );

		$post = $listing_id ? get_post( $listing_id ) : null;

		if ( ! $post || 'listora_listing' !== $post->post_type ) {
			return new WP_REST_Response(
				array(
					'sent'  => false,
					'error' => 'not_found',
				),
				404
			);
		}

		// Authorize BEFORE revealing the listing's verification state. Either the
		// logged-in author OR a guest who supplies the matching author email may
		// proceed. Doing the ownership gate first is what stops this endpoint
		// being an enumeration oracle: a non-owner gets the same 403 whether the
		// listing is a pending (unverified) guest submission or an already-live
		// listing, so probing IDs no longer reveals which ones are unverified.
		// (A missing author collapses into the same 403 for the same reason.)
		$author      = get_user_by( 'id', (int) $post->post_author );
		$is_owner    = $author && is_user_logged_in() && get_current_user_id() === (int) $author->ID;
		$email_match = $author && $email && strtolower( $email ) === strtolower( $author->user_email );

		if ( ! $is_owner && ! $email_match ) {
			return new WP_REST_Response(
				array(
					'sent'  => false,
					'error' => 'forbidden',
				),
				403
			);
		}

		// Only an authorized requester reaches the verification-state check.
		if ( ! \WBListora\Workflow\Email_Verification::is_pending_verification( $listing_id ) ) {
			return new WP_REST_Response(
				array(
					'sent'  => false,
					'error' => 'not_pending',
				),
				400
			);
		}

		$result = \WBListora\Workflow\Email_Verification::resend_verification( $listing_id );
		$status = ! empty( $result['sent'] ) ? 200 : ( 'rate_limited' === ( $result['error'] ?? '' ) ? 429 : 400 );

		return new WP_REST_Response( $result, $status );
	}

	/**
	 * REST: verify an email-verification token.
	 *
	 * Mirror of the public /?listora-verify=1 URL — apps and SPAs can call
	 * this directly and avoid HTML.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function verify_endpoint( $request ) {
		// Throttle by IP so the listing/token lookup can't be used to probe
		// listing IDs in sequence (the different 404 / not-pending / verified
		// responses would otherwise leak which IDs exist).
		$gate = \WBListora\Rate_Limiter::check( 'verify_email' );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$listing_id = absint( $request->get_param( 'listing_id' ) );
		$token      = (string) $request->get_param( 'token' );

		$post = $listing_id ? get_post( $listing_id ) : null;
		if ( ! $post || 'listora_listing' !== $post->post_type ) {
			return new WP_REST_Response(
				array(
					'verified' => false,
					'error'    => 'not_found',
				),
				404
			);
		}

		if ( ! \WBListora\Workflow\Email_Verification::is_pending_verification( $listing_id ) ) {
			return new WP_REST_Response(
				array(
					'verified' => false,
					'error'    => 'not_pending',
				),
				400
			);
		}

		if ( \WBListora\Workflow\Email_Verification::is_expired( $listing_id ) ) {
			return new WP_REST_Response(
				array(
					'verified' => false,
					'error'    => 'expired',
				),
				410
			);
		}

		if ( ! \WBListora\Workflow\Email_Verification::verify_token( $listing_id, $token ) ) {
			return new WP_REST_Response(
				array(
					'verified' => false,
					'error'    => 'invalid_token',
				),
				400
			);
		}

		$moderation = wb_listora_get_setting( 'moderation', 'manual' );
		$new_status = ( 'auto_approve' === $moderation ) ? 'publish' : 'pending';

		wp_update_post(
			array(
				'ID'          => $listing_id,
				'post_status' => $new_status,
			)
		);

		\WBListora\Workflow\Email_Verification::consume_token( $listing_id );

		do_action( 'wb_listora_after_email_verified', $listing_id, $new_status );
		// 4th arg `$context` (1.1.0+) — empty array = user-driven submission.
		do_action( 'wb_listora_listing_submitted', $listing_id, $new_status, $request, array() );
		if ( 'pending' === $new_status ) {
			do_action( 'wb_listora_listing_pending_admin', $listing_id );
		}

		return new WP_REST_Response(
			array(
				'verified'   => true,
				'status'     => $new_status,
				'listing_id' => $listing_id,
				'url'        => get_permalink( $listing_id ),
			),
			200
		);
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
