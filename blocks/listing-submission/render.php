<?php
/**
 * Listing Submission block — multi-step frontend form.
 *
 * Steps: Type → Basic Info → Details → Media → Preview → Submit
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'listora-shared' );

// Enqueue Leaflet assets for the map_location field picker.
wp_enqueue_style( 'leaflet', WB_LISTORA_PLUGIN_URL . 'assets/vendor/leaflet.css', array(), '1.9.4' );
wp_enqueue_script( 'leaflet', WB_LISTORA_PLUGIN_URL . 'assets/vendor/leaflet.js', array(), '1.9.4', true );

$unique_id      = $attributes['uniqueId'] ?? '';
$listing_type   = $attributes['listingType'] ?? '';
$show_type_step = $attributes['showTypeStep'] ?? true;
$require_login  = $attributes['requireLogin'] ?? true;
$show_terms     = $attributes['showTerms'] ?? true;
$terms_page_id  = $attributes['termsPageId'] ?? 0;
$redirect       = $attributes['redirectAfterSubmit'] ?? 'dashboard';

// Check if submission is enabled.
if ( ! wb_listora_feature_enabled( 'submission' ) ) {
	return;
}

// ─── Edit mode: detect ?edit=ID and verify ownership ───
$edit_listing_id   = 0;
$edit_listing_data = null;
$is_edit_mode      = false;

if ( isset( $_GET['edit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$edit_listing_id = absint( $_GET['edit'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
}

// Guest submission setting.
$guest_submission_enabled = (bool) wb_listora_get_setting( 'enable_guest_submission', false );
$is_guest                 = ! is_user_logged_in();

// Login requirement — skip block if login required and user is not logged in,
// UNLESS guest submission is enabled.
if ( $require_login && $is_guest && ! $guest_submission_enabled ) {
	$wrapper_attrs = get_block_wrapper_attributes( array( 'class' => 'listora-submission listora-submission--login-required' ) );
	?>
	<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<div class="listora-submission__login-prompt">
			<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
				<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
			</svg>
			<h2><?php esc_html_e( 'Add Your Listing', 'wb-listora' ); ?></h2>
			<p><?php esc_html_e( 'Please log in or create an account to submit a listing.', 'wb-listora' ); ?></p>
			<div class="listora-submission__login-buttons">
				<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="listora-btn listora-btn--primary">
					<?php esc_html_e( 'Log In', 'wb-listora' ); ?>
				</a>
				<a href="<?php echo esc_url( wp_registration_url() ); ?>" class="listora-btn listora-btn--secondary">
					<?php esc_html_e( 'Create Account', 'wb-listora' ); ?>
				</a>
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

$visibility_classes = \WBListora\Block_CSS::visibility_classes( $attributes );
$block_classes      = 'listora-block' . ( $unique_id ? ' listora-block-' . $unique_id : '' ) . ( $visibility_classes ? ' ' . $visibility_classes : '' );

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class'               => 'listora-submission ' . $block_classes,
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

if (
	class_exists( '\Wbcom\Credits\Credits' )
	&& \Wbcom\Credits\Credits::is_enabled( 'wb-listora' )
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
