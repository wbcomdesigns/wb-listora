<?php
/**
 * Listing Submission block — multi-step frontend form.
 *
 * Steps: Type → Basic Info → Details → Media → Preview → Submit
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'listora-base' );

// Enqueue Leaflet assets for the map_location field picker.
wp_enqueue_style( 'leaflet', WB_LISTORA_PLUGIN_URL . 'assets/vendor/leaflet.css', array(), '1.9.4' );
wp_enqueue_script( 'leaflet', WB_LISTORA_PLUGIN_URL . 'assets/vendor/leaflet.js', array(), '1.9.4', true );

// flatpickr — used by the business_hours field to provide a real
// click-to-pick time picker. Firefox renders <input type="time"> as a
// numeric spinner with no clock chrome, so the round-1 fix (decorative
// icon, commit 6c7bbe1) wasn't enough — card 9856828615 round 2.
wp_enqueue_style( 'listora-flatpickr', WB_LISTORA_PLUGIN_URL . 'assets/vendor/flatpickr/flatpickr.min.css', array(), '4.6.13' );
wp_enqueue_script( 'listora-flatpickr', WB_LISTORA_PLUGIN_URL . 'assets/vendor/flatpickr/flatpickr.min.js', array(), '4.6.13', true );

$unique_id      = $attributes['uniqueId'] ?? '';
$listing_type   = $attributes['listingType'] ?? '';
$show_type_step = $attributes['showTypeStep'] ?? true;
$layout_mode    = $attributes['layoutMode'] ?? 'wizard';
$require_login  = $attributes['requireLogin'] ?? true;
$show_terms     = $attributes['showTerms'] ?? true;
$terms_page_id  = $attributes['termsPageId'] ?? 0;
$redirect       = $attributes['redirectAfterSubmit'] ?? 'dashboard';

// Check if submission is enabled.
if ( ! wb_listora_feature_enabled( 'submission' ) ) {
	return;
}

// ─── Edit mode: detect ?edit=ID or dashboard's ?action=edit&id=N and verify ownership ───
// Card 9895464887 — two valid URL conventions point at edit mode:
//   • Standalone /submit-listing/?edit=N (legacy SEO landing flow)
//   • Dashboard inline  /dashboard/?tab=listings&action=edit&id=N
//     (this is what wb_listora_get_dashboard_edit_url() builds — the
//     "Edit" icon in /my-listings/ rows)
// Pre-fix the block only honored the first form, so the dashboard's
// Edit icon opened a blank-form / Type-step instead of pre-filling
// the existing listing.
$edit_listing_id   = 0;
$edit_listing_data = null;
$is_edit_mode      = false;

// phpcs:disable WordPress.Security.NonceVerification.Recommended
if ( isset( $_GET['edit'] ) ) {
	$edit_listing_id = absint( $_GET['edit'] );
} elseif ( isset( $_GET['action'], $_GET['id'] ) && 'edit' === sanitize_key( (string) $_GET['action'] ) ) {
	$edit_listing_id = absint( $_GET['id'] );
}
// phpcs:enable WordPress.Security.NonceVerification.Recommended

if ( $edit_listing_id > 0 && is_user_logged_in() ) {
	$edit_post = get_post( $edit_listing_id );
	if (
		$edit_post &&
		'listora_listing' === $edit_post->post_type &&
		(int) $edit_post->post_author === get_current_user_id()
	) {
		$is_edit_mode      = true;
		$edit_listing_data = $edit_post;

		// Fetch existing values for pre-filling.
		$edit_meta         = \WBListora\Core\Meta_Handler::get_all_values( $edit_listing_id );
		$edit_type_terms   = wp_get_object_terms( $edit_listing_id, 'listora_listing_type', array( 'fields' => 'slugs' ) );
		$edit_cat_terms    = wp_get_object_terms( $edit_listing_id, 'listora_listing_cat', array( 'fields' => 'ids' ) );
		$edit_tag_terms    = wp_get_object_terms( $edit_listing_id, 'listora_listing_tag', array( 'fields' => 'names' ) );
		$edit_type_slug    = ( ! is_wp_error( $edit_type_terms ) && ! empty( $edit_type_terms ) ) ? $edit_type_terms[0] : '';
		$edit_category_id  = ( ! is_wp_error( $edit_cat_terms ) && ! empty( $edit_cat_terms ) ) ? (int) $edit_cat_terms[0] : 0;
		$edit_tags_string  = ( ! is_wp_error( $edit_tag_terms ) ) ? implode( ', ', $edit_tag_terms ) : '';
		$edit_thumbnail_id = (int) get_post_thumbnail_id( $edit_listing_id );
		$edit_gallery      = $edit_meta['gallery'] ?? array();
		$edit_gallery_ids  = is_array( $edit_gallery ) ? implode( ',', array_map( 'absint', $edit_gallery ) ) : '';
		$edit_video        = $edit_meta['video'] ?? '';

		// If type is set on the listing, use it to pre-select.
		if ( $edit_type_slug && ! $listing_type ) {
			$listing_type = $edit_type_slug;
		}
	} else {
		// Param present but not owner — silently ignore.
		$edit_listing_id = 0;
	}
}

// Guest submission setting.
$guest_submission_enabled = (bool) wb_listora_get_setting( 'enable_guest_submission', false );
$is_guest                 = ! is_user_logged_in();

// Login requirement — skip block if login required and user is not logged in,
// UNLESS guest submission is enabled.
if ( $require_login && $is_guest && ! $guest_submission_enabled ) {
	$wrapper_attrs = get_block_wrapper_attributes( array( 'class' => 'listora-submission listora-submission--login-required' ) );

	$submission_current_permalink = (string) get_permalink();
	$submission_login_url         = wp_login_url( $submission_current_permalink );
	// Mirror the F-04 fix on the listing-detail anon modal: always render
	// Create Account, let WordPress show "Registration currently not allowed"
	// on the destination page when users_can_register=0. Filter the URL
	// so invite-only sites can swap or suppress it. Same filter contract
	// as wb_listora_login_modal_register_url for backend↔frontend uniformity.
	$submission_register_url = function_exists( 'wp_registration_url' ) ? wp_registration_url() : '/wp-login.php?action=register';

	/**
	 * Filters the "Create Account" URL surfaced on the submission block's
	 * login-required prompt.
	 *
	 * Return an empty string to suppress the Create Account CTA entirely
	 * (e.g. an invite-only directory). Same contract as
	 * wb_listora_login_modal_register_url for the listing-detail anon
	 * login modal — apply the filter to BOTH for consistent behaviour.
	 *
	 * @since 1.0.5
	 *
	 * @param string $submission_register_url Resolved registration URL.
	 * @param string $current_permalink       Permalink the user will return to after auth.
	 */
	$submission_register_url = apply_filters( 'wb_listora_submission_register_url', $submission_register_url, $submission_current_permalink );
	?>
	<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<div class="listora-submission__login-prompt">
			<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
				<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
			</svg>
			<h2><?php esc_html_e( 'Add Your Listing', 'wb-listora' ); ?></h2>
			<p><?php esc_html_e( 'Please log in or create an account to submit a listing.', 'wb-listora' ); ?></p>
			<div class="listora-submission__login-buttons">
				<a href="<?php echo esc_url( $submission_login_url ); ?>" class="listora-btn listora-btn--primary">
					<?php esc_html_e( 'Log In', 'wb-listora' ); ?>
				</a>
				<?php if ( $submission_register_url ) : ?>
				<a href="<?php echo esc_url( $submission_register_url ); ?>" class="listora-btn listora-btn--secondary">
					<?php esc_html_e( 'Create Account', 'wb-listora' ); ?>
				</a>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
	return;
}

// Capability check — skip for guests when guest submission is enabled.
if ( ! $is_guest && ! current_user_can( 'submit_listora_listing' ) ) {
	return;
}

// Always enqueue wp.media on the submission page so the upload zones in
// step-media.php and submission-field-renderer.php can open the media
// frame. wp.media is auto-loaded in wp-admin but NOT on the frontend —
// without this, both the IAPI action and the delegated DOM fallback in
// view.js silently return at `typeof wp === 'undefined' || ! wp.media`
// and clicking "Click to upload" does nothing.
//
// The previous gate `if ( ! $is_guest )` left guests with a dead
// upload zone whenever guest submission was enabled — the exact bug
// QA hit when re-testing in an incognito window. Guests can browse
// the public media library; the actual upload step requires a
// separate REST flow (tracked separately) but enqueueing the modal
// itself is harmless and lets logged-in flows work end-to-end.
wp_enqueue_media();

// Enqueue CAPTCHA scripts if enabled.
\WBListora\Captcha::enqueue_scripts();

// Get listing types for step 1.
$registry = \WBListora\Core\Listing_Type_Registry::instance();
$types    = $registry->get_all();

// Determine steps.
$steps    = array();
$step_num = 1;

if ( $show_type_step && ! $listing_type && count( $types ) > 1 ) {
	$steps[] = array(
		'id'    => 'type',
		'label' => __( 'Type', 'wb-listora' ),
		'num'   => $step_num++,
	);
}
$steps[] = array(
	'id'    => 'basic',
	'label' => __( 'Basic Info', 'wb-listora' ),
	'num'   => $step_num++,
);
$steps[] = array(
	'id'    => 'details',
	'label' => __( 'Details', 'wb-listora' ),
	'num'   => $step_num++,
);
$steps[] = array(
	'id'    => 'media',
	'label' => __( 'Media', 'wb-listora' ),
	'num'   => $step_num++,
);
$steps[] = array(
	'id'    => 'preview',
	'label' => __( 'Preview', 'wb-listora' ),
	'num'   => $step_num++,
);

/**
 * Filter the submission wizard's step indicator entries.
 *
 * Pro (and other extensions) hook into `wb_listora_submission_plan_step` to
 * inject additional step DOM nodes between Media and Preview. Without a
 * matching indicator entry, the visual stepper falls out of sync with the
 * actual step list — the user sees "Preview" highlighted while standing on
 * the injected step. Extensions register here to insert a corresponding
 * indicator entry. Each entry must have `id`, `label`, and `num`.
 *
 * After filtering, `num` values are renumbered sequentially so extensions
 * don't have to coordinate numbering with each other or with Free.
 *
 * @since 1.0.0
 *
 * @param array  $steps        Array of step definitions ('id', 'label', 'num').
 * @param string $listing_type Pre-selected listing type slug, or empty string.
 * @param bool   $is_edit_mode Whether the form is in edit mode.
 */
$steps = apply_filters( 'wb_listora_submission_steps', $steps, $listing_type, $is_edit_mode );

// Renumber sequentially so filter-injected steps display correct numbers.
$steps = array_values( array_filter( (array) $steps, 'is_array' ) );
foreach ( $steps as $i => $step ) {
	$steps[ $i ]['num'] = $i + 1;
}

$total_steps = count( $steps );

// Get categories for the pre-selected type.
$type_categories = array();
if ( $listing_type ) {
	$type_obj = $registry->get( $listing_type );
	if ( $type_obj ) {
		$cat_ids = $type_obj->get_allowed_categories();
		if ( ! empty( $cat_ids ) ) {
			$type_categories = get_terms(
				array(
					'taxonomy'   => 'listora_listing_cat',
					'include'    => $cat_ids,
					'hide_empty' => false,
				)
			);
			if ( is_wp_error( $type_categories ) ) {
				$type_categories = array();
			}
		}
	}
}

$context = wp_json_encode(
	array(
		'currentStep'   => $steps[0]['id'],
		'stepIndex'     => 0,
		'totalSteps'    => $total_steps,
		'listingType'   => $listing_type,
		'formData'      => new \stdClass(),
		'isSubmitting'  => false,
		'submitError'   => '',
		'submitSuccess' => false,
		'draftId'       => 0,
		'editListingId' => $edit_listing_id,
	)
);

// Auto-default to single-form layout in edit mode — editing an existing
// listing is one action (update everything you can see and save once),
// not onboarding (where the wizard's progressive disclosure helps).
// Authors can still override by setting the block's layoutMode attribute
// explicitly per filter. The block.json default ('wizard') still applies
// for NEW submissions.
if ( $is_edit_mode && 'wizard' === $layout_mode ) {
	/**
	 * Filter — control whether edit-mode auto-switches to single-form.
	 *
	 * @param bool  $auto_single_form Default true.
	 * @param int   $edit_listing_id
	 */
	$auto_single = (bool) apply_filters( 'wb_listora_edit_auto_single_form', true, $edit_listing_id );
	if ( $auto_single ) {
		$layout_mode = 'single-form';
	}
}

$visibility_classes = \WBListora\Block_CSS::visibility_classes( $attributes );
$layout_class       = 'single-form' === $layout_mode
	? 'listora-submission--single-form'
	: 'listora-submission--wizard';
$block_classes      = 'listora-block ' . $layout_class . ( $unique_id ? ' listora-block-' . $unique_id : '' ) . ( $visibility_classes ? ' ' . $visibility_classes : '' );

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		// Canonical page shell (Part 7.6.1 / F9). The submission flow is a
		// focused, narrow-form experience — the `--booking` variant maxes
		// out at 720px which suits a step-by-step wizard. Single-form mode
		// expands to `--list` (1400px) so all sections can comfortably stack.
		'class'               => 'listora-page ' . ( 'single-form' === $layout_mode ? 'listora-page--list' : 'listora-page--booking' ) . ' listora-submission ' . $block_classes,
		'data-wp-interactive' => 'listora/directory',
		'data-wp-context'     => $context,
	)
);

// Build existing meta values for pre-fill in edit mode.
$prefill_meta = ( $is_edit_mode && isset( $edit_meta ) ) ? $edit_meta : array();

// ─── Credit info (for preview-step banner) ───
$credit_enabled      = false;
$credit_balance      = 0;
$credit_default_cost = 0;
$credit_purchase_url = '';

/** This filter is documented in blocks/user-dashboard/render.php */
$listora_show_credit_surfaces = (bool) apply_filters(
	'wb_listora_show_credits',
	class_exists( '\Wbcom\Credits\Credits' ) && \Wbcom\Credits\Credits::is_enabled( 'wb-listora' )
);

if (
	$listora_show_credit_surfaces
	&& is_user_logged_in()
) {
	$credit_enabled      = true;
	$credit_balance      = (int) \Wbcom\Credits\Credits::get_balance( 'wb-listora', get_current_user_id() );
	$credit_default_cost = (int) wb_listora_get_setting( 'default_listing_credit_cost', 0 );
	$credit_purchase_url = function_exists( 'wb_listora_get_credits_purchase_url' )
		? wb_listora_get_credits_purchase_url()
		: '';
}

// ─── Assemble $view_data for templates ───
$view_data = array(
	'wrapper_attrs'            => $wrapper_attrs,
	'block_css'                => \WBListora\Block_CSS::render( $unique_id, $attributes ),
	'steps'                    => $steps,
	'total_steps'              => $total_steps,
	'layout_mode'              => $layout_mode,
	'listing_type'             => $listing_type,
	'show_type_step'           => $show_type_step,
	'show_terms'               => $show_terms,
	'terms_page_id'            => $terms_page_id,
	'is_edit_mode'             => $is_edit_mode,
	'edit_listing_id'          => $edit_listing_id,
	'edit_listing_data'        => $edit_listing_data ?? null,
	'edit_category_id'         => $edit_category_id ?? 0,
	'edit_tags_string'         => $edit_tags_string ?? '',
	'edit_thumbnail_id'        => $edit_thumbnail_id ?? 0,
	'edit_gallery'             => $edit_gallery ?? array(),
	'edit_gallery_ids'         => $edit_gallery_ids ?? '',
	'edit_video'               => $edit_video ?? '',
	'is_guest'                 => $is_guest,
	'guest_submission_enabled' => $guest_submission_enabled,
	'types'                    => $types,
	'registry'                 => $registry,
	'type_categories'          => $type_categories,
	'prefill_meta'             => $prefill_meta,
	'credit_enabled'           => $credit_enabled,
	'credit_balance'           => $credit_balance,
	'credit_default_cost'      => $credit_default_cost,
	'credit_purchase_url'      => $credit_purchase_url,
);

// Self-reference for sub-templates.
$view_data['view_data'] = $view_data;

wb_listora_get_template( 'blocks/listing-submission/submission.php', $view_data );
