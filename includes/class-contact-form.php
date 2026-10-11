<?php
/**
 * The listing contact form: the one way a visitor messages a listing owner.
 *
 * POSTs to /listings/{id}/contact-form. Each message is saved as a lead
 * (Core\Leads) and the owner is emailed through Notifications. Rate limits
 * are per sender and per listing. Pro builds on the after-submit action.
 *
 * @package WBListora
 */

namespace WBListora;

defined( 'ABSPATH' ) || exit;

/**
 * Listing contact form: render, validate, save the lead, notify.
 */
class Contact_Form {

	const NONCE_ACTION_PREFIX = 'wb_listora_contact_form_';

	/**
	 * Route template — the SINGLE source of truth for this endpoint's path.
	 *
	 * Three consumers derive from it and must never re-spell it: the route
	 * registration (regex form), the nonce-failure context (`{id}` form), and
	 * the path advertised to the client (resolved form). A hardcoded copy in
	 * JS is what made the Free contact form dead whenever Pro's `lead_form`
	 * was OFF — the client pointed at Pro's `/contact`, which is not
	 * registered in that configuration, so every submit 404'd.
	 *
	 * @var string
	 */
	private const ROUTE_TEMPLATE = 'listings/%s/contact-form';

	/**
	 * Route pattern for register_rest_route() (regex `id` capture).
	 *
	 * @return string
	 */
	public static function route_pattern(): string {
		return '/' . sprintf( self::ROUTE_TEMPLATE, '(?P<id>[\d]+)' );
	}

	/**
	 * REST path for a specific listing, as apiFetch consumes it.
	 *
	 * Deliberately a namespace-relative PATH, never a full URL: apiFetch
	 * resolves it against the site's REST root, so this stays correct on
	 * subdirectory installs, multisite, a custom REST prefix, and plain
	 * permalinks (where it falls back to `?rest_route=`). A baked-in URL
	 * would break all four.
	 *
	 * @param int $listing_id Listing ID.
	 * @return string
	 */
	public static function rest_path( $listing_id ): string {
		return '/' . WB_LISTORA_REST_NAMESPACE . '/' . sprintf( self::ROUTE_TEMPLATE, (int) $listing_id );
	}

	/**
	 * Boot hooks.
	 */
	public static function init(): void {
		add_action( 'wb_listora_after_listing_fields', array( __CLASS__, 'render_form' ), 20, 2 );
		add_action( 'wb_listora_rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Whether the contact form renders: the owner's Contact form switch.
	 *
	 * @return bool
	 */
	private static function should_render() {
		/**
		 * Filter whether the listing contact form renders.
		 *
		 * @param bool $should_render Default: the Contact form feature switch.
		 */
		return (bool) apply_filters( 'wb_listora_render_contact_form', wb_listora_feature_enabled( 'contact_form' ) );
	}

	/**
	 * Per-listing nonce action.
	 *
	 * @param int $listing_id Listing ID.
	 * @return string
	 */
	private static function nonce_action( $listing_id ): string {
		return self::NONCE_ACTION_PREFIX . (int) $listing_id;
	}

	/**
	 * Register the contact-form REST route.
	 */
	public static function register_routes(): void {
		register_rest_route(
			WB_LISTORA_REST_NAMESPACE,
			self::route_pattern(),
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'handle_submit' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
					'args'                => array(
						'id'       => array(
							'type'     => 'integer',
							'required' => true,
						),
						'name'     => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'email'    => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_email',
						),
						'message'  => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'phone'    => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'hp'       => array(
							'type'    => 'string',
							'default' => '',
						),
						'_wpnonce' => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	/**
	 * REST permission — guest-friendly, gated by proof-of-origin.
	 *
	 * The gate is *proof of origin*, not authentication: people contacting a
	 * business owner don't necessarily have accounts. A browser proves origin
	 * with the per-listing nonce printed by {@see render_form()}; an
	 * authenticated client (mobile app on an Application Password) proves it
	 * by being authenticated. Anonymous callers with neither are rejected, as
	 * before. See {@see wb_listora_verify_rest_nonce()} for the decision table
	 * and the `wb_listora_require_rest_nonce` escape hatch.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public static function check_permission( $request ) {
		// Off means off for the app too, which posts here directly.
		if ( ! wb_listora_feature_enabled( 'contact_form' ) ) {
			return new \WP_Error( 'listora_contact_form_disabled', __( 'Contacting listing owners is turned off on this site.', 'wb-listora' ), array( 'status' => 403 ) );
		}

		$listing_id = (int) $request->get_param( 'id' );
		$nonce      = (string) $request->get_param( '_wpnonce' );

		return wb_listora_verify_rest_nonce(
			$nonce,
			self::nonce_action( $listing_id ),
			array(
				'route'      => sprintf( self::ROUTE_TEMPLATE, '{id}' ),
				'listing_id' => $listing_id,
			)
		);
	}

	/**
	 * Handle a contact-form submission.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_submit( $request ) {
		$listing_id = (int) $request->get_param( 'id' );

		// Honeypot — silently accept so bots think they won.
		if ( '' !== (string) $request->get_param( 'hp' ) ) {
			return rest_ensure_response( array( 'sent' => true ) );
		}

		$post = get_post( $listing_id );
		if ( ! $post || 'listora_listing' !== $post->post_type ) {
			return new \WP_Error( 'listora_not_found', __( 'Listing not found.', 'wb-listora' ), array( 'status' => 404 ) );
		}

		$name    = (string) $request->get_param( 'name' );
		$email   = (string) $request->get_param( 'email' );
		$phone   = (string) $request->get_param( 'phone' );
		$message = (string) $request->get_param( 'message' );

		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'listora_invalid_email', __( 'Please provide a valid email address.', 'wb-listora' ), array( 'status' => 400 ) );
		}

		// Anti-spam pipeline (Akismet + keyword + URL density).
		$antispam = Anti_Spam::check(
			array(
				'title'        => '',
				'description'  => $message,
				'author_name'  => $name,
				'author_email' => $email,
				'source'       => 'contact_form',
			)
		);
		if ( is_wp_error( $antispam ) ) {
			return $antispam;
		}

		$lead = array(
			'name'    => $name,
			'email'   => $email,
			'phone'   => $phone,
			'message' => $message,
		);

		/**
		 * Filter a contact message before it is sent; return a WP_Error to refuse it.
		 *
		 * Pro's spam scoring hooks here.
		 *
		 * @since 2.0.0
		 *
		 * @param true|\WP_Error       $check      True to send.
		 * @param int                  $listing_id Listing ID.
		 * @param array<string, mixed> $lead       name, email, phone, message.
		 * @param \WP_REST_Request     $request    REST request.
		 */
		$check = apply_filters( 'wb_listora_before_contact_form_submit', true, $listing_id, $lead, $request );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// Rate limits — per-sender-per-listing and per-listing-per-day.
		// The sender bucket is the user when we know who they are, else the IP.
		// Keying authenticated senders on the user ID stops carrier-grade NAT
		// (every mobile network) from making unrelated app users share one
		// counter and throttle each other. Cap and window are unchanged.
		$identity = wb_listora_contact_rate_limit_identity( $listing_id );

		$sender_key   = 'wb_listora_contact_' . $identity['scope'] . '_' . md5( $identity['id'] . '|' . $listing_id );
		$sender_count = (int) get_transient( $sender_key );
		if ( $sender_count >= 3 ) {
			return new \WP_Error(
				'listora_rate_limit',
				'user' === $identity['scope']
					? __( 'Too many messages. Try again in an hour.', 'wb-listora' )
					: __( 'Too many messages from your network. Try again in an hour.', 'wb-listora' ),
				array(
					'status'      => 429,
					'retry_after' => HOUR_IN_SECONDS, // Upper bound: the window restarts on each accepted message.
				)
			);
		}

		$listing_key   = 'wb_listora_contact_listing_' . $listing_id;
		$listing_count = (int) get_transient( $listing_key );
		/**
		 * Filter the per-listing daily contact-form cap.
		 *
		 * @param int $cap        Default 20.
		 * @param int $listing_id Listing ID.
		 */
		$listing_cap = (int) apply_filters( 'wb_listora_contact_form_per_listing_daily_cap', 20, $listing_id );
		if ( $listing_count >= $listing_cap ) {
			return new \WP_Error(
				'listora_listing_rate_limit',
				__( 'This listing has received too many messages today. Try again tomorrow.', 'wb-listora' ),
				array(
					'status'      => 429,
					'retry_after' => DAY_IN_SECONDS,
				)
			);
		}

		set_transient( $sender_key, $sender_count + 1, HOUR_IN_SECONDS );
		set_transient( $listing_key, $listing_count + 1, DAY_IN_SECONDS );

		$owner = get_user_by( 'id', (int) $post->post_author );
		if ( ! $owner ) {
			return new \WP_Error( 'listora_no_owner', __( 'Unable to contact this listing owner.', 'wb-listora' ), array( 'status' => 500 ) );
		}

		/*
		 * A block has to stop the MESSAGE, not just the content — otherwise the
		 * person you blocked still reaches your inbox, which is the thing being
		 * escaped. Symmetric, so it holds whichever of the two did the blocking.
		 *
		 * The wording deliberately does not say "they blocked you": telling a
		 * sender that a specific person blocked them is how a safety feature
		 * becomes a provocation. It says the message cannot be sent.
		 */
		if ( class_exists( '\WBListora\Core\Member_Blocks' ) && is_user_logged_in()
			&& ! \WBListora\Core\Member_Blocks::can_contact( get_current_user_id(), (int) $owner->ID ) ) {
			return new \WP_Error(
				'listora_contact_blocked',
				__( 'This message cannot be sent.', 'wb-listora' ),
				array( 'status' => 403 )
			);
		}

		// Saved before anything else, so a missed email never loses the enquiry.
		$lead['lead_id'] = \WBListora\Core\Leads::record( $listing_id, $lead );

		/**
		 * Fires after a contact message is saved. Free's Notifications emails
		 * the owner from here; Pro's analytics, webhooks and CRM push listen too.
		 *
		 * @param int                  $listing_id Listing ID.
		 * @param array<string, mixed> $context    { name, email, phone, message, lead_id }.
		 * @param \WP_REST_Request     $request    REST request.
		 */
		do_action( 'wb_listora_after_contact_form_submit', $listing_id, $lead, $request );

		return rest_ensure_response(
			array(
				'sent'    => true,
				'message' => __( 'Your message has been sent to the listing owner.', 'wb-listora' ),
			)
		);
	}

	/**
	 * Render the form on listing-detail pages.
	 *
	 * @param int    $listing_id Listing ID.
	 * @param string $type_slug  Listing type slug.
	 */
	public static function render_form( $listing_id, $type_slug ): void {
		if ( ! self::should_render() ) {
			return;
		}

		// Don't show the owner their own contact form.
		$post = get_post( (int) $listing_id );
		if ( $post && is_user_logged_in() && get_current_user_id() === (int) $post->post_author ) {
			return;
		}

		// Nor show a form that would be refused on submit — offering it and
		// then rejecting the message wastes the sender's time and tells them
		// more about the block than not showing it does.
		if ( $post && is_user_logged_in() && class_exists( '\WBListora\Core\Member_Blocks' )
			&& ! \WBListora\Core\Member_Blocks::can_contact( get_current_user_id(), (int) $post->post_author ) ) {
			return;
		}

		$uid = 'wb-listora-contact-' . absint( $listing_id );

		// The server owns the route, so the server advertises it. The client
		// must never build or assume this path: the owner can place this form
		// on any page or template, and which implementation renders (Free's
		// here, Pro's lead form) depends on a runtime toggle. Whoever renders
		// advertises its own path, so a toggle flip can never produce a 404.
		$context = wp_json_encode(
			array(
				'listingId'   => (int) $listing_id,
				'contactPath' => self::rest_path( (int) $listing_id ),
			)
		);

		// No context means no path, and a submit with no path is a dead form.
		// Better to render nothing than a control that silently does nothing.
		if ( false === $context ) {
			return;
		}
		?>
		<div class="listora-contact-form" data-wp-interactive="listora/directory">
			<h3 class="listora-contact-form__title"><?php esc_html_e( 'Contact owner', 'wb-listora' ); ?></h3>

			<form
				class="listora-contact-form__form"
				data-wp-on--submit="actions.submitContactForm"
				data-wp-context='<?php echo esc_attr( $context ); ?>'
			>
				<div class="listora-submission__field">
					<label class="listora-submission__label" for="<?php echo esc_attr( $uid . '-name' ); ?>">
						<?php esc_html_e( 'Your name', 'wb-listora' ); ?> *
					</label>
					<input type="text" id="<?php echo esc_attr( $uid . '-name' ); ?>" name="name" class="listora-input" required />
				</div>

				<div class="listora-submission__field">
					<label class="listora-submission__label" for="<?php echo esc_attr( $uid . '-email' ); ?>">
						<?php esc_html_e( 'Your email', 'wb-listora' ); ?> *
					</label>
					<input type="email" id="<?php echo esc_attr( $uid . '-email' ); ?>" name="email" class="listora-input" required />
				</div>

				<div class="listora-submission__field">
					<label class="listora-submission__label" for="<?php echo esc_attr( $uid . '-phone' ); ?>">
						<?php esc_html_e( 'Phone (optional)', 'wb-listora' ); ?>
					</label>
					<input type="tel" id="<?php echo esc_attr( $uid . '-phone' ); ?>" name="phone" class="listora-input" autocomplete="tel" />
				</div>

				<div class="listora-submission__field">
					<label class="listora-submission__label" for="<?php echo esc_attr( $uid . '-message' ); ?>">
						<?php esc_html_e( 'Message', 'wb-listora' ); ?> *
					</label>
					<textarea id="<?php echo esc_attr( $uid . '-message' ); ?>" name="message" class="listora-input" rows="4" required></textarea>
				</div>

				<div style="position:absolute;left:-9999px;" aria-hidden="true">
					<input type="text" name="hp" value="" tabindex="-1" autocomplete="off" aria-label="<?php esc_attr_e( 'Leave this field empty', 'wb-listora' ); ?>" />
				</div>

				<?php wp_nonce_field( self::nonce_action( (int) $listing_id ), '_listora_contact_nonce', false, true ); ?>

				<button type="submit" class="listora-btn wp-element-button listora-btn--primary">
					<?php esc_html_e( 'Send message', 'wb-listora' ); ?>
				</button>

				<div class="listora-contact-form__message" hidden aria-live="polite" aria-atomic="true"></div>
			</form>
		</div>
		<?php
	}
}
