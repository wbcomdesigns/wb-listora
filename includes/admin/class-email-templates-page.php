<?php
/**
 * Email Templates editor (MVP) — Settings → Notifications.
 *
 * Lets a site owner override the subject + body of EVERY notification event
 * (the same event map the Notifications class fires) without editing any
 * template file. Overrides persist to ONE non-autoloaded option,
 * `wb_listora_email_templates`, and are read back through the EXISTING
 * per-event filters `wb_listora_email_subject_{event}` /
 * `wb_listora_email_content_{event}` — so no template-file edits and no new
 * option per event.
 *
 * Scope is intentionally narrow (AUD-F8 MVP): a textarea editor + an
 * allowed-placeholder legend + per-event reset. NOT a rich builder. Body is
 * kses-sanitized on save. Preview reuses Notifications::send_test().
 *
 * @package WBListora\Admin
 */

namespace WBListora\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Renders + persists the per-event email subject/body overrides.
 */
class Email_Templates_Page {

	/**
	 * Non-autoloaded option holding all per-event overrides.
	 *
	 * Stored as array<string $event, array{subject?:string, body?:string}>.
	 * Only events the owner actually customized appear here — an absent event
	 * means "use the plugin default" (the filter returns the value untouched).
	 *
	 * @var string
	 */
	const OPTION_KEY = 'wb_listora_email_templates';

	/**
	 * The admin-post action for saving the editor form.
	 *
	 * @var string
	 */
	const SAVE_ACTION = 'wb_listora_save_email_templates';

	/**
	 * Nonce action/field used by the editor form + the reset control.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'wb_listora_email_templates';

	/**
	 * Settings page slug this editor renders under.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'listora-settings';

	/**
	 * Wire up the editor.
	 *
	 * Registration is split so the read-back filters work on EVERY request
	 * (including frontend/REST sends, where `admin_init` never fires) while the
	 * admin-only UI pieces stay behind their natural hooks.
	 *
	 * @return void
	 */
	public static function init() {
		// Idempotent: init() is called from the always-loaded Plugin boot
		// (so the read-back filters exist on REST + cron sends - the
		// double-verify proved admin-edited templates were silently ignored
		// outside wp-admin) AND from Settings_Page in admin context. Guard so
		// the filters never register twice.
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		// Read-back filters — must register for all contexts, not just admin,
		// because notification emails fire on frontend REST + cron requests.
		// Defer to `init`: register_filters() reads get_event_map(), whose
		// labels call __() — running it at plugins_loaded (this boot path)
		// would translate before the textdomain loads and trip WP 6.7's
		// `_load_textdomain_just_in_time` notice. Emails only ever fire on
		// actions that run after `init`, so coverage is unchanged. If `init`
		// already passed (late call from the admin Settings page), register now.
		if ( did_action( 'init' ) ) {
			self::register_filters();
		} else {
			add_action( 'init', array( __CLASS__, 'register_filters' ) );
		}

		// Admin UI: render under the Notifications tab (after the options.php
		// form closes — this is a standalone form posting to admin-post.php, so
		// it MUST live in the after-form hook to avoid HTML5 nested-form parsing
		// dropping it). See Settings_Page::render() docblock on this hook.
		add_action( 'wb_listora_settings_tab_content_after_form', array( __CLASS__, 'render' ) );

		// Save handler.
		add_action( 'admin_post_' . self::SAVE_ACTION, array( __CLASS__, 'save' ) );

		// Editor assets — scoped to the settings page only.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Attach the per-event subject + content read-back filters.
	 *
	 * Each event gets its own closure on the EXISTING dynamic filters so the
	 * saved override (if any) replaces the default. Placeholders in the saved
	 * value are substituted from the live `$vars`, so `{listing_title}` etc.
	 * render correctly without the owner touching code.
	 *
	 * @return void
	 */
	public static function register_filters() {
		foreach ( array_keys( self::get_event_map() ) as $event ) {
			add_filter(
				"wb_listora_email_subject_{$event}",
				static function ( $subject, $vars ) use ( $event ) {
					return self::apply_override( $event, 'subject', (string) $subject, (array) $vars );
				},
				10,
				2
			);

			add_filter(
				"wb_listora_email_content_{$event}",
				static function ( $body, $vars ) use ( $event ) {
					return self::apply_override( $event, 'body', (string) $body, (array) $vars );
				},
				10,
				2
			);
		}
	}

	/**
	 * Replace the default subject/body with the saved override for one event.
	 *
	 * @param string               $event     Event key.
	 * @param string               $field     'subject' or 'body'.
	 * @param string               $fallback  Default value (post-render).
	 * @param array<string, mixed> $vars      Live template variables.
	 * @return string
	 */
	private static function apply_override( $event, $field, $fallback, array $vars ) {
		$override = self::get_override( $event, $field );

		if ( '' === $override ) {
			return $fallback;
		}

		// Preserve the test-send "[TEST]" prefix the Notifications class adds to
		// subjects so test mail stays clearly marked even when overridden.
		if ( 'subject' === $field && ! empty( $vars['is_test'] ) && 0 !== strpos( $override, '[TEST] ' ) ) {
			$override = '[TEST] ' . $override;
		}

		return self::substitute_placeholders( $override, $vars );
	}

	/**
	 * Read a single saved override field for an event.
	 *
	 * @param string $event Event key.
	 * @param string $field 'subject' or 'body'.
	 * @return string Override value, or '' when none is saved.
	 */
	public static function get_override( $event, $field ) {
		$all = self::get_overrides();

		if ( empty( $all[ $event ][ $field ] ) ) {
			return '';
		}

		return (string) $all[ $event ][ $field ];
	}

	/**
	 * Read the full overrides map.
	 *
	 * @return array<string, array{subject?:string, body?:string}>
	 */
	public static function get_overrides() {
		$value = get_option( self::OPTION_KEY, array() );

		return is_array( $value ) ? $value : array();
	}

	/**
	 * Substitute `{placeholder}` tokens in an override string from `$vars`.
	 *
	 * Unknown tokens are left as-is so a typo is visible rather than silently
	 * blanked. Values are cast to string; nothing is escaped here because the
	 * subject is plain text and the body is already kses-sanitized on save.
	 *
	 * @param string               $template Override string with `{token}`s.
	 * @param array<string, mixed> $vars     Replacement values.
	 * @return string
	 */
	private static function substitute_placeholders( $template, array $vars ) {
		return preg_replace_callback(
			'/\{([a-z0-9_]+)\}/',
			static function ( $matches ) use ( $vars ) {
				$key = $matches[1];

				if ( ! array_key_exists( $key, $vars ) || is_array( $vars[ $key ] ) ) {
					return $matches[0];
				}

				return (string) $vars[ $key ];
			},
			$template
		);
	}

	/**
	 * The canonical notification event map.
	 *
	 * Mirrors the events Notifications fires (and the Settings → Notifications
	 * toggle groups), PLUS `review_reminder` and `listing_verify_email`. Each
	 * event carries its display label, a one-line description, and the list of
	 * placeholders that are meaningful for that event (drives the legend).
	 *
	 * @return array<string, array{group:string, label:string, desc:string, placeholders:array<int,string>}>
	 */
	public static function get_event_map() {
		// Placeholders shared by every event (header/footer + site identity).
		$common = array( 'site_name', 'site_url' );

		$map = array(
			// ── Listings ──.
			'listing_submitted'     => array(
				'group'        => __( 'Listings', 'wb-listora' ),
				'label'        => __( 'New listing submitted', 'wb-listora' ),
				'desc'         => __( 'Sent to admin when a new listing is submitted for review.', 'wb-listora' ),
				'placeholders' => array( 'listing_title', 'listing_url', 'author_name', 'admin_url', 'listing_type' ),
			),
			'listing_pending_admin' => array(
				'group'        => __( 'Listings', 'wb-listora' ),
				'label'        => __( 'Listing pending admin review', 'wb-listora' ),
				'desc'         => __( 'Sent to admin when a listing enters the moderation queue.', 'wb-listora' ),
				'placeholders' => array( 'listing_title', 'listing_url', 'author_name', 'admin_review_url' ),
			),
			'listing_approved'      => array(
				'group'        => __( 'Listings', 'wb-listora' ),
				'label'        => __( 'Listing approved', 'wb-listora' ),
				'desc'         => __( 'Sent to listing owner when their listing is published.', 'wb-listora' ),
				'placeholders' => array( 'listing_title', 'listing_url', 'author_name' ),
			),
			'listing_rejected'      => array(
				'group'        => __( 'Listings', 'wb-listora' ),
				'label'        => __( 'Listing rejected', 'wb-listora' ),
				'desc'         => __( 'Sent to listing owner with admin feedback.', 'wb-listora' ),
				'placeholders' => array( 'listing_title', 'author_name', 'edit_url', 'rejection_reason', 'admin_notes' ),
			),
			'listing_expired'       => array(
				'group'        => __( 'Listings', 'wb-listora' ),
				'label'        => __( 'Listing expired', 'wb-listora' ),
				'desc'         => __( 'Sent to listing owner when their listing expires and is unpublished.', 'wb-listora' ),
				'placeholders' => array( 'listing_title', 'author_name', 'renew_url', 'expiry_date' ),
			),
			'listing_expiring_soon' => array(
				'group'        => __( 'Listings', 'wb-listora' ),
				'label'        => __( 'Expiration reminder', 'wb-listora' ),
				'desc'         => __( 'Sent 7 days and 1 day before a listing expires.', 'wb-listora' ),
				'placeholders' => array( 'listing_title', 'author_name', 'renew_url', 'days', 'expiry_date' ),
			),
			'listing_renewed'       => array(
				'group'        => __( 'Listings', 'wb-listora' ),
				'label'        => __( 'Listing renewed', 'wb-listora' ),
				'desc'         => __( 'Sent to listing owner when their listing is renewed.', 'wb-listora' ),
				'placeholders' => array( 'listing_title', 'listing_url', 'author_name', 'new_expiry_date' ),
			),
			'draft_reminder'        => array(
				'group'        => __( 'Listings', 'wb-listora' ),
				'label'        => __( 'Draft reminder', 'wb-listora' ),
				'desc'         => __( 'Nudge email for listings still in draft 48+ hours.', 'wb-listora' ),
				'placeholders' => array( 'listing_title', 'author_name', 'edit_url', 'dashboard_url' ),
			),

			// ── Reviews ──.
			'review_received'       => array(
				'group'        => __( 'Reviews', 'wb-listora' ),
				'label'        => __( 'New review received', 'wb-listora' ),
				'desc'         => __( 'Sent to listing owner when they receive a new review.', 'wb-listora' ),
				'placeholders' => array( 'listing_title', 'listing_url', 'reviewer_name', 'review_rating', 'review_title', 'review_content' ),
			),
			'review_reply'          => array(
				'group'        => __( 'Reviews', 'wb-listora' ),
				'label'        => __( 'Owner replied to review', 'wb-listora' ),
				'desc'         => __( 'Sent to the reviewer when the listing owner responds.', 'wb-listora' ),
				'placeholders' => array( 'listing_title', 'listing_url', 'reviewer_name', 'owner_name', 'reply_text' ),
			),
			'review_helpful'        => array(
				'group'        => __( 'Reviews', 'wb-listora' ),
				'label'        => __( 'Helpful-vote milestone', 'wb-listora' ),
				'desc'         => __( 'Sent to the reviewer when their review reaches a helpful-vote milestone.', 'wb-listora' ),
				'placeholders' => array( 'listing_title', 'listing_url', 'reviewer_name', 'milestone', 'helpful_count' ),
			),
			'review_reminder'       => array(
				'group'        => __( 'Reviews', 'wb-listora' ),
				'label'        => __( 'Reply reminder', 'wb-listora' ),
				'desc'         => __( 'Sent to the owner when reviews are waiting for a reply.', 'wb-listora' ),
				'placeholders' => array( 'listing_title', 'listing_url', 'author_name', 'pending_count', 'dashboard_url' ),
			),

			// ── Claims ──.
			'claim_submitted'       => array(
				'group'        => __( 'Claims', 'wb-listora' ),
				'label'        => __( 'Claim submitted', 'wb-listora' ),
				'desc'         => __( 'Sent to admin when a claim is filed on a listing.', 'wb-listora' ),
				'placeholders' => array( 'listing_title', 'listing_url', 'claimant_name', 'claimant_email', 'admin_review_url' ),
			),
			'claim_approved'        => array(
				'group'        => __( 'Claims', 'wb-listora' ),
				'label'        => __( 'Claim approved', 'wb-listora' ),
				'desc'         => __( 'Sent to the claimant when their claim is accepted.', 'wb-listora' ),
				'placeholders' => array( 'listing_title', 'listing_url', 'claimant_name' ),
			),
			'claim_rejected'        => array(
				'group'        => __( 'Claims', 'wb-listora' ),
				'label'        => __( 'Claim rejected', 'wb-listora' ),
				'desc'         => __( 'Sent to the claimant when their claim is denied.', 'wb-listora' ),
				'placeholders' => array( 'listing_title', 'claimant_name', 'admin_notes' ),
			),

			// ── Account ──.
			'listing_verify_email'  => array(
				'group'        => __( 'Account', 'wb-listora' ),
				'label'        => __( 'Verify email to publish', 'wb-listora' ),
				'desc'         => __( 'Sent to a guest submitter asking them to verify their email.', 'wb-listora' ),
				'placeholders' => array( 'listing_title', 'user_name', 'verify_url' ),
			),
		);

		// Append the common placeholders to every event so the legend is complete.
		foreach ( $map as $event => $cfg ) {
			$map[ $event ]['placeholders'] = array_values(
				array_unique( array_merge( $cfg['placeholders'], $common ) )
			);
		}

		return $map;
	}

	/**
	 * Group the event map by its `group` label, preserving event order.
	 *
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	private static function get_grouped_events() {
		$grouped = array();

		foreach ( self::get_event_map() as $event => $cfg ) {
			$grouped[ $cfg['group'] ][ $event ] = $cfg;
		}

		return $grouped;
	}

	/**
	 * Enqueue the editor stylesheet + script on the settings page only.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public static function enqueue_assets( $hook_suffix ) {
		// The settings page lives under the Listora menu; its hook suffix
		// contains the page slug. Bail on every other admin screen so we never
		// leak the stylesheet portfolio-wide.
		if ( false === strpos( (string) $hook_suffix, self::PAGE_SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'listora-email-templates',
			WB_LISTORA_PLUGIN_URL . 'assets/css/admin/email-templates.css',
			array( 'listora-settings' ),
			WB_LISTORA_VERSION
		);

		wp_enqueue_script(
			'listora-email-templates',
			WB_LISTORA_PLUGIN_URL . 'assets/js/admin/email-templates.js',
			array(),
			WB_LISTORA_VERSION,
			true
		);
	}

	/**
	 * Render the editor under the Notifications settings tab.
	 *
	 * Fires on `wb_listora_settings_tab_content_after_form`; only emits for the
	 * Notifications tab so it doesn't render on every section.
	 *
	 * @param string $tab_id Current tab being rendered.
	 * @return void
	 */
	public static function render( $tab_id ) {
		if ( 'notifications' !== $tab_id ) {
			return;
		}

		if ( ! current_user_can( 'manage_listora_settings' ) ) {
			return;
		}

		$grouped    = self::get_grouped_events();
		$action_url = admin_url( 'admin-post.php' );

		// Saved-notice (read-only flag echoed back after the redirect).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display flag; no state change.
		$saved = isset( $_GET['email-templates-updated'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['email-templates-updated'] ) );
		?>
		<div class="listora-settings-pane listora-email-templates" id="listora-email-templates">
			<?php if ( $saved ) : ?>
				<div class="notice listora-notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Email templates saved.', 'wb-listora' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( $action_url ); ?>" class="listora-email-templates__form">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>" />
				<?php wp_nonce_field( self::NONCE_ACTION, '_wb_listora_email_templates_nonce' ); ?>

				<section class="listora-settings-block">
					<div class="listora-settings-block__head">
						<h3 class="listora-settings-block__title"><?php esc_html_e( 'Email Templates', 'wb-listora' ); ?></h3>
						<p class="listora-settings-block__desc">
							<?php esc_html_e( 'Customize the subject line and message body for any notification. Leave a field blank to keep the built-in default. Use the placeholders listed under each event - they are replaced with live values when the email is sent.', 'wb-listora' ); ?>
						</p>
					</div>

					<?php foreach ( $grouped as $group_label => $events ) : ?>
						<div class="listora-email-templates__group">
							<h4 class="listora-email-templates__group-title"><?php echo esc_html( $group_label ); ?></h4>

							<?php
							foreach ( $events as $event => $cfg ) :
								$subject_value = self::get_override( $event, 'subject' );
								$body_value    = self::get_override( $event, 'body' );
								$has_override  = '' !== $subject_value || '' !== $body_value;
								$subject_id    = 'listora-email-subject-' . $event;
								$body_id       = 'listora-email-body-' . $event;
								$legend_id     = 'listora-email-legend-' . $event;
								?>
								<details class="listora-email-templates__event"<?php echo $has_override ? ' open' : ''; ?>>
									<summary class="listora-email-templates__summary">
										<span class="listora-email-templates__summary-label"><?php echo esc_html( $cfg['label'] ); ?></span>
										<?php if ( $has_override ) : ?>
											<span class="listora-badge listora-badge--featured"><?php esc_html_e( 'Customized', 'wb-listora' ); ?></span>
										<?php endif; ?>
									</summary>

									<div class="listora-email-templates__body">
										<p class="listora-email-templates__desc"><?php echo esc_html( $cfg['desc'] ); ?></p>

										<p class="listora-email-templates__field">
											<label class="listora-email-templates__field-label" for="<?php echo esc_attr( $subject_id ); ?>">
												<?php esc_html_e( 'Subject', 'wb-listora' ); ?>
											</label>
											<input
												type="text"
												class="regular-text listora-email-templates__subject"
												id="<?php echo esc_attr( $subject_id ); ?>"
												name="email_templates[<?php echo esc_attr( $event ); ?>][subject]"
												value="<?php echo esc_attr( $subject_value ); ?>"
												aria-describedby="<?php echo esc_attr( $legend_id ); ?>"
											/>
										</p>

										<p class="listora-email-templates__field">
											<label class="listora-email-templates__field-label" for="<?php echo esc_attr( $body_id ); ?>">
												<?php esc_html_e( 'Body', 'wb-listora' ); ?>
											</label>
											<textarea
												class="large-text code listora-email-templates__textarea"
												id="<?php echo esc_attr( $body_id ); ?>"
												name="email_templates[<?php echo esc_attr( $event ); ?>][body]"
												rows="6"
												aria-describedby="<?php echo esc_attr( $legend_id ); ?>"
											><?php echo esc_textarea( $body_value ); ?></textarea>
										</p>

										<div class="listora-email-templates__legend" id="<?php echo esc_attr( $legend_id ); ?>">
											<span class="listora-email-templates__legend-label"><?php esc_html_e( 'Available placeholders:', 'wb-listora' ); ?></span>
											<?php foreach ( $cfg['placeholders'] as $placeholder ) : ?>
												<code class="listora-email-templates__placeholder">{<?php echo esc_html( $placeholder ); ?>}</code>
											<?php endforeach; ?>
										</div>

										<p class="listora-email-templates__actions">
											<label class="listora-email-templates__reset">
												<input
													type="checkbox"
													name="email_templates_reset[<?php echo esc_attr( $event ); ?>]"
													value="1"
												/>
												<?php esc_html_e( 'Reset this event to the default on save', 'wb-listora' ); ?>
											</label>
										</p>
									</div>
								</details>
							<?php endforeach; ?>
						</div>
					<?php endforeach; ?>

					<p class="listora-email-templates__submit">
						<button type="submit" class="listora-btn wp-element-button listora-btn--primary">
							<i data-lucide="save"></i> <?php esc_html_e( 'Save Email Templates', 'wb-listora' ); ?>
						</button>
					</p>
				</section>
			</form>
		</div>
		<?php
	}

	/**
	 * Persist the editor form to the non-autoloaded overrides option.
	 *
	 * @return void
	 */
	public static function save() {
		if ( ! current_user_can( 'manage_listora_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'wb-listora' ) );
		}

		check_admin_referer( self::NONCE_ACTION, '_wb_listora_email_templates_nonce' );

		$valid_events = array_keys( self::get_event_map() );

		// Reset flags — events the owner asked to restore to default.
		$reset = array();
		if ( isset( $_POST['email_templates_reset'] ) && is_array( $_POST['email_templates_reset'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only keys are read; values are presence flags validated against the known event list below.
			$reset = array_keys( wp_unslash( $_POST['email_templates_reset'] ) );
			$reset = array_map( 'sanitize_key', $reset );
		}

		$raw = array();
		if ( isset( $_POST['email_templates'] ) && is_array( $_POST['email_templates'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each field is individually unslashed + sanitized in the loop below.
			$raw = wp_unslash( $_POST['email_templates'] );
		}

		$overrides = array();

		foreach ( $valid_events as $event ) {
			// Skip events flagged for reset — they fall through to defaults.
			if ( in_array( $event, $reset, true ) ) {
				continue;
			}

			$subject = isset( $raw[ $event ]['subject'] ) ? sanitize_text_field( (string) $raw[ $event ]['subject'] ) : '';
			$body    = isset( $raw[ $event ]['body'] ) ? wp_kses_post( (string) $raw[ $event ]['body'] ) : '';

			$entry = array();
			if ( '' !== $subject ) {
				$entry['subject'] = $subject;
			}
			if ( '' !== trim( $body ) ) {
				$entry['body'] = $body;
			}

			// Only store events that actually have an override — keeps the
			// option lean and "absent === default" semantics intact.
			if ( ! empty( $entry ) ) {
				$overrides[ $event ] = $entry;
			}
		}

		// Non-autoloaded (AUD-F6 pattern): read only on admin save + at send
		// time, never on every page load. `false` = do not autoload.
		update_option( self::OPTION_KEY, $overrides, false );

		$redirect = add_query_arg(
			array(
				'page'                    => self::PAGE_SLUG,
				'tab'                     => 'notifications',
				'email-templates-updated' => '1',
			),
			admin_url( 'admin.php' )
		) . '#notifications';

		wp_safe_redirect( $redirect );
		exit;
	}
}
