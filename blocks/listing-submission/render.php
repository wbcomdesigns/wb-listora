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

$listing_type   = $attributes['listingType'] ?? '';
$show_type_step = $attributes['showTypeStep'] ?? true;
$require_login  = $attributes['requireLogin'] ?? true;
$show_terms     = $attributes['showTerms'] ?? true;
$terms_page_id  = $attributes['termsPageId'] ?? 0;
$redirect       = $attributes['redirectAfterSubmit'] ?? 'dashboard';

// Check if submission is enabled.
if ( ! wb_listora_get_setting( 'enable_submission', true ) ) {
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

// Login requirement.
if ( $require_login && ! is_user_logged_in() ) {
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

// Capability check.
if ( ! current_user_can( 'submit_listora_listing' ) ) {
	return;
}

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
		'currentStep'     => $steps[0]['id'],
		'stepIndex'       => 0,
		'totalSteps'      => $total_steps,
		'listingType'     => $listing_type,
		'formData'        => new \stdClass(),
		'isSubmitting'    => false,
		'submitError'     => '',
		'submitSuccess'   => false,
		'draftId'         => 0,
		'editListingId'   => $edit_listing_id,
	)
);

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class'               => 'listora-submission',
		'data-wp-interactive' => 'listora/directory',
		'data-wp-context'     => $context,
	)
);
?>

<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

	<?php // ─── Progress Stepper ─── ?>
	<div class="listora-submission__progress" role="progressbar" aria-valuemin="1" aria-valuemax="<?php echo esc_attr( $total_steps ); ?>" aria-valuenow="1" aria-label="<?php esc_attr_e( 'Submission progress', 'wb-listora' ); ?>">
		<?php foreach ( $steps as $i => $step ) : ?>
			<?php if ( $i > 0 ) : ?>
			<div class="listora-submission__step-line"></div>
			<?php endif; ?>
			<div class="listora-submission__step-indicator <?php echo 0 === $i ? 'is-current' : ''; ?>" data-step="<?php echo esc_attr( $step['id'] ); ?>">
				<span class="listora-submission__step-dot"><?php echo esc_html( $step['num'] ); ?></span>
				<span class="listora-submission__step-label"><?php echo esc_html( $step['label'] ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>

	<form class="listora-submission__form" data-wp-on--submit="actions.handleSubmission">

		<?php wp_nonce_field( 'listora_submit_listing', 'listora_nonce' ); ?>
		<input type="hidden" name="listing_type" value="<?php echo esc_attr( $listing_type ); ?>" />
		<?php if ( $is_edit_mode ) : ?>
		<input type="hidden" name="listing_id" value="<?php echo esc_attr( $edit_listing_id ); ?>" />
		<?php endif; ?>

		<?php // Honeypot anti-spam field. ?>
		<div style="position:absolute;left:-9999px;" aria-hidden="true">
			<input type="text" name="listora_hp_field" value="" tabindex="-1" autocomplete="off" />
		</div>

		<?php // ─── Step: Choose Type ─── ?>
		<?php if ( $show_type_step && ! $listing_type && count( $types ) > 1 ) : ?>
		<div class="listora-submission__step" data-step="type">
			<h2><?php esc_html_e( 'What type of listing are you adding?', 'wb-listora' ); ?></h2>
			<div class="listora-submission__type-grid">
				<?php
				foreach ( $types as $type_item ) :
					if ( ! $type_item->get_prop( 'submission_enabled' ) ) {
						continue;
					}
					?>
				<label class="listora-submission__type-card">
					<input type="radio" name="listing_type" value="<?php echo esc_attr( $type_item->get_slug() ); ?>" required
						data-wp-on--change="actions.selectSubmissionType" />
					<span class="listora-submission__type-card-inner" style="--listora-type-color: <?php echo esc_attr( $type_item->get_color() ); ?>">
						<span class="dashicons <?php echo esc_attr( $type_item->get_icon() ); ?>" aria-hidden="true"></span>
						<span class="listora-submission__type-name"><?php echo esc_html( $type_item->get_name() ); ?></span>
					</span>
				</label>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<?php // ─── Step: Basic Info ─── ?>
		<div class="listora-submission__step" data-step="basic" <?php echo ( $show_type_step && ! $listing_type ) ? 'hidden' : ''; ?>>
			<h2>
				<?php
				if ( $is_edit_mode ) {
					esc_html_e( 'Edit Basic Information', 'wb-listora' );
				} else {
					esc_html_e( 'Basic Information', 'wb-listora' );
				}
				?>
			</h2>

			<div class="listora-submission__field">
				<label for="listora-title" class="listora-submission__label">
					<?php esc_html_e( 'Listing Title', 'wb-listora' ); ?> <span class="required">*</span>
				</label>
				<input type="text" id="listora-title" name="title" class="listora-input" required
					placeholder="<?php esc_attr_e( 'e.g., Pizza Palace', 'wb-listora' ); ?>"
					value="<?php echo $is_edit_mode ? esc_attr( $edit_listing_data->post_title ) : ''; ?>" />
			</div>

			<?php if ( ! empty( $type_categories ) || ! $listing_type ) : ?>
			<div class="listora-submission__field">
				<label for="listora-category" class="listora-submission__label">
					<?php esc_html_e( 'Category', 'wb-listora' ); ?> <span class="required">*</span>
				</label>
				<select id="listora-category" name="category" class="listora-input listora-select" required>
					<option value=""><?php esc_html_e( 'Select a category', 'wb-listora' ); ?></option>
					<?php foreach ( $type_categories as $cat ) : ?>
					<option value="<?php echo esc_attr( $cat->term_id ); ?>"
						<?php selected( $is_edit_mode && $edit_category_id === (int) $cat->term_id ); ?>>
						<?php echo esc_html( $cat->name ); ?>
					</option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php endif; ?>

			<div class="listora-submission__field">
				<label for="listora-tags" class="listora-submission__label">
					<?php esc_html_e( 'Tags', 'wb-listora' ); ?>
				</label>
				<input type="text" id="listora-tags" name="tags" class="listora-input"
					placeholder="<?php esc_attr_e( 'pizza, italian, downtown (comma separated)', 'wb-listora' ); ?>"
					value="<?php echo $is_edit_mode ? esc_attr( $edit_tags_string ) : ''; ?>" />
			</div>

			<div class="listora-submission__field">
				<label for="listora-description" class="listora-submission__label">
					<?php esc_html_e( 'Description', 'wb-listora' ); ?> <span class="required">*</span>
				</label>
				<textarea id="listora-description" name="description" class="listora-input listora-submission__textarea" rows="6" required
					placeholder="<?php esc_attr_e( 'Describe your listing...', 'wb-listora' ); ?>"><?php echo $is_edit_mode ? esc_textarea( $edit_listing_data->post_content ) : ''; ?></textarea>
			</div>
		</div>

		<?php // ─── Step: Details (type-specific fields) ─── ?>
		<div class="listora-submission__step" data-step="details" hidden>
			<h2><?php esc_html_e( 'Details', 'wb-listora' ); ?></h2>
			<p class="listora-submission__step-desc"><?php esc_html_e( 'Provide additional details about your listing.', 'wb-listora' ); ?></p>

			<?php
			// Build existing meta values for pre-fill in edit mode.
			$prefill_meta = ( $is_edit_mode && isset( $edit_meta ) ) ? $edit_meta : array();

			// Render type-specific fields.
			if ( $listing_type ) {
				$type_obj = $registry->get( $listing_type );
				if ( $type_obj ) {
					foreach ( $type_obj->get_field_groups() as $group ) {
						echo '<fieldset class="listora-submission__fieldset">';
						echo '<legend class="listora-submission__fieldset-legend">' . esc_html( $group->get_label() ) . '</legend>';

						foreach ( $group->get_fields() as $field ) {
							$existing_value = array_key_exists( $field->get_key(), $prefill_meta ) ? $prefill_meta[ $field->get_key() ] : null;
							wb_listora_render_submission_field( $field, $existing_value );
						}

						echo '</fieldset>';
					}
				}
			} else {
				// Dynamic: fields loaded via JS after type selection.
				echo '<div class="listora-submission__dynamic-fields" data-wp-html="state.submissionFieldsHtml">';
				echo '<p class="listora-submission__field-placeholder">' . esc_html__( 'Select a listing type to see fields.', 'wb-listora' ) . '</p>';
				echo '</div>';
			}
			?>
		</div>

		<?php // ─── Step: Media ─── ?>
		<div class="listora-submission__step" data-step="media" hidden>
			<h2><?php esc_html_e( 'Photos & Media', 'wb-listora' ); ?></h2>

			<div class="listora-submission__field">
				<label class="listora-submission__label">
					<?php esc_html_e( 'Featured Image', 'wb-listora' ); ?>
					<?php if ( ! $is_edit_mode ) : ?>
					<span class="required">*</span>
					<?php endif; ?>
				</label>
				<?php
				$edit_thumb_url = ( $is_edit_mode && $edit_thumbnail_id ) ? wp_get_attachment_image_url( $edit_thumbnail_id, 'medium' ) : '';
				?>
				<div class="listora-submission__upload-zone" data-wp-on--click="actions.openMediaUpload" data-wp-context='{"uploadTarget":"featured_image"}'>
					<?php if ( $edit_thumb_url ) : ?>
					<img src="<?php echo esc_url( $edit_thumb_url ); ?>" alt="<?php esc_attr_e( 'Featured image preview', 'wb-listora' ); ?>" style="max-width:100%;border-radius:var(--listora-card-radius);" />
					<?php else : ?>
					<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
						<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/>
						<path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
					</svg>
					<span><?php esc_html_e( 'Click to upload or drag & drop', 'wb-listora' ); ?></span>
					<span class="listora-submission__upload-hint"><?php esc_html_e( 'Max 5MB, JPG/PNG/WebP', 'wb-listora' ); ?></span>
					<?php endif; ?>
				</div>
				<input type="hidden" name="featured_image" value="<?php echo $is_edit_mode ? esc_attr( $edit_thumbnail_id ) : ''; ?>" />
			</div>

			<div class="listora-submission__field">
				<label class="listora-submission__label">
					<?php
					printf(
						/* translators: %d: max gallery images */
						esc_html__( 'Gallery (up to %d photos)', 'wb-listora' ),
						(int) wb_listora_get_setting( 'max_gallery_images', 20 )
					);
					?>
				</label>
				<div class="listora-submission__gallery-upload">
					<div class="listora-submission__gallery-thumbs" id="listora-gallery-thumbs">
						<?php
						// Pre-render existing gallery thumbnails in edit mode.
						if ( $is_edit_mode && ! empty( $edit_gallery ) && is_array( $edit_gallery ) ) {
							foreach ( $edit_gallery as $gal_id ) {
								$gal_id  = absint( $gal_id );
								$gal_url = wp_get_attachment_image_url( $gal_id, 'thumbnail' );
								if ( $gal_url ) {
									echo '<div style="width:80px;height:80px;border-radius:var(--listora-radius-md);overflow:hidden;position:relative;">';
									echo '<img src="' . esc_url( $gal_url ) . '" alt="' . esc_attr( get_post_meta( $gal_id, '_wp_attachment_image_alt', true ) ?: __( 'Gallery image', 'wb-listora' ) ) . '" style="width:100%;height:100%;object-fit:cover;" />';
									echo '</div>';
								}
							}
						}
						?>
					</div>
					<button type="button" class="listora-btn listora-btn--secondary listora-submission__add-photos"
						data-wp-on--click="actions.openMediaUpload" data-wp-context='{"uploadTarget":"gallery"}'>
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
						<?php esc_html_e( 'Add Photos', 'wb-listora' ); ?>
					</button>
				</div>
				<input type="hidden" name="gallery" value="<?php echo $is_edit_mode ? esc_attr( $edit_gallery_ids ) : ''; ?>" />
			</div>

			<div class="listora-submission__field">
				<label for="listora-video" class="listora-submission__label"><?php esc_html_e( 'Video URL (optional)', 'wb-listora' ); ?></label>
				<input type="url" id="listora-video" name="video" class="listora-input"
					placeholder="<?php esc_attr_e( 'https://youtube.com/watch?v=...', 'wb-listora' ); ?>"
					value="<?php echo $is_edit_mode ? esc_url( $edit_video ) : ''; ?>" />
			</div>
		</div>

		<?php
		/**
		 * Fires inside the submission form after the Media step, before the Preview step.
		 *
		 * Pro (and other extensions) can hook here to inject additional steps such as
		 * plan / pricing selection. Each hooked callback receives the pre-selected
		 * listing type string (empty when type is chosen dynamically in the form).
		 *
		 * @since 1.0.0
		 *
		 * @param string $listing_type The pre-configured listing type slug, or empty string.
		 */
		do_action( 'wb_listora_submission_plan_step', $listing_type );
		?>

		<?php // ─── Step: Preview ─── ?>
		<div class="listora-submission__step" data-step="preview" hidden>
			<h2><?php esc_html_e( 'Preview Your Listing', 'wb-listora' ); ?></h2>
			<p class="listora-submission__step-desc"><?php esc_html_e( 'Review your listing before submitting.', 'wb-listora' ); ?></p>

			<div class="listora-submission__preview-card">
				<div id="listora-preview-content">
					<p class="listora-submission__field-placeholder"><?php esc_html_e( 'Preview will appear here after filling in the form.', 'wb-listora' ); ?></p>
				</div>
			</div>

			<?php if ( $show_terms ) : ?>
			<div class="listora-submission__field listora-submission__terms">
				<label class="listora-submission__checkbox-label">
					<input type="checkbox" name="agree_terms" required />
					<?php
					if ( $terms_page_id > 0 ) {
						printf(
							/* translators: %s: link to terms page */
							wp_kses_post( __( 'I agree to the <a href="%s" target="_blank">Terms of Service</a>', 'wb-listora' ) ),
							esc_url( get_permalink( $terms_page_id ) )
						);
					} else {
						esc_html_e( 'I agree to the Terms of Service', 'wb-listora' );
					}
					?>
				</label>
			</div>
			<?php endif; ?>
		</div>

		<?php // ─── Success Message ─── ?>
		<div class="listora-submission__success" hidden>
			<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" style="color: var(--listora-success)">
				<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
			</svg>
			<?php if ( $is_edit_mode ) : ?>
			<h2><?php esc_html_e( 'Listing Updated!', 'wb-listora' ); ?></h2>
			<p><?php esc_html_e( 'Your listing has been updated successfully.', 'wb-listora' ); ?></p>
			<div class="listora-submission__success-actions">
				<a href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>" class="listora-btn listora-btn--primary">
					<?php esc_html_e( 'Go to Dashboard', 'wb-listora' ); ?>
				</a>
				<a href="<?php echo esc_url( get_permalink( $edit_listing_id ) ); ?>" class="listora-btn listora-btn--secondary">
					<?php esc_html_e( 'View Listing', 'wb-listora' ); ?>
				</a>
			</div>
			<?php else : ?>
			<h2><?php esc_html_e( 'Listing Submitted!', 'wb-listora' ); ?></h2>
			<p><?php esc_html_e( 'Your listing has been submitted and is pending review. We\'ll notify you once it\'s approved.', 'wb-listora' ); ?></p>
			<div class="listora-submission__success-actions">
				<a href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>" class="listora-btn listora-btn--primary">
					<?php esc_html_e( 'Go to Dashboard', 'wb-listora' ); ?>
				</a>
				<a href="<?php echo esc_url( get_permalink() ); ?>" class="listora-btn listora-btn--secondary">
					<?php esc_html_e( 'Add Another Listing', 'wb-listora' ); ?>
				</a>
			</div>
			<?php endif; ?>
		</div>

		<?php // ─── Error Message ─── ?>
		<div class="listora-submission__error" role="alert" hidden>
			<p></p>
		</div>

		<?php // ─── Navigation Buttons ─── ?>
		<div class="listora-submission__nav">
			<button type="button" class="listora-btn listora-btn--secondary listora-submission__back" data-wp-on--click="actions.prevSubmissionStep" hidden>
				<?php esc_html_e( '← Back', 'wb-listora' ); ?>
			</button>

			<div class="listora-submission__nav-right">
				<button type="button" class="listora-btn listora-btn--text listora-submission__save-draft" data-wp-on--click="actions.saveDraft">
					<?php esc_html_e( 'Save Draft', 'wb-listora' ); ?>
				</button>

				<button type="button" class="listora-btn listora-btn--primary listora-submission__next" data-wp-on--click="actions.nextSubmissionStep">
					<?php esc_html_e( 'Continue →', 'wb-listora' ); ?>
				</button>

				<button type="submit" class="listora-btn listora-btn--primary listora-submission__submit-btn" hidden>
					<?php
					if ( $is_edit_mode ) {
						esc_html_e( 'Update Listing', 'wb-listora' );
					} else {
						esc_html_e( 'Submit Listing', 'wb-listora' );
					}
					?>
				</button>
			</div>
		</div>

	</form>
</div>

<?php
/**
 * Render a single field for the submission form.
 *
 * @param \WBListora\Core\Field $field          Field definition.
 * @param mixed                 $existing_value Existing value to pre-fill (null when creating).
 */
if ( ! function_exists( 'wb_listora_render_submission_field' ) ) :
	function wb_listora_render_submission_field( $field, $existing_value = null ) {
		$key         = $field->get_key();
		$label       = $field->get_label();
		$type        = $field->get_type();
		$required    = $field->is_required();
		$placeholder = $field->get( 'placeholder' ) ?: '';
		$options     = $field->get( 'options' ) ?: array();
		$description = $field->get( 'description' ) ?: '';
		$width       = $field->get( 'width' ) ?: '100';
		$field_name  = 'meta_' . $key;
		$has_value   = null !== $existing_value;

		// Skip complex types rendered separately.
		if ( in_array( $type, array( 'gallery', 'social_links' ), true ) ) {
			return;
		}

		$style = '100' !== $width ? 'style="width:' . esc_attr( $width ) . '%"' : '';

		echo '<div class="listora-submission__field" ' . $style . '>';
		echo '<label for="listora-field-' . esc_attr( $key ) . '" class="listora-submission__label">';
		echo esc_html( $label );
		if ( $required ) {
			echo ' <span class="required">*</span>';
		}
		echo '</label>';

		if ( $description ) {
			echo '<span class="listora-submission__field-desc">' . esc_html( $description ) . '</span>';
		}

		$input_id = 'listora-field-' . esc_attr( $key );

		switch ( $type ) {
			case 'text':
			case 'phone':
			case 'url':
			case 'email':
				$input_type = ( 'phone' === $type ) ? 'tel' : $type;
				echo '<input type="' . esc_attr( $input_type ) . '" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $field_name ) . '" class="listora-input"';
				echo ' placeholder="' . esc_attr( $placeholder ) . '"';
				if ( $has_value ) {
					echo ' value="' . esc_attr( (string) $existing_value ) . '"';
				}
				if ( $required ) {
					echo ' required';
				}
				echo ' />';
				break;

			case 'textarea':
				echo '<textarea id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $field_name ) . '" class="listora-input listora-submission__textarea" rows="4"';
				echo ' placeholder="' . esc_attr( $placeholder ) . '"';
				if ( $required ) {
					echo ' required';
				}
				echo '>' . ( $has_value ? esc_textarea( (string) $existing_value ) : '' ) . '</textarea>';
				break;

			case 'number':
				echo '<input type="number" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $field_name ) . '" class="listora-input"';
				$min = $field->get( 'min' );
				$max = $field->get( 'max' );
				if ( null !== $min ) {
					echo ' min="' . esc_attr( $min ) . '"';
				}
				if ( null !== $max ) {
					echo ' max="' . esc_attr( $max ) . '"';
				}
				if ( $has_value ) {
					echo ' value="' . esc_attr( (string) $existing_value ) . '"';
				}
				if ( $required ) {
					echo ' required';
				}
				echo ' />';
				break;

			case 'select':
			case 'radio':
				echo '<select id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $field_name ) . '" class="listora-input listora-select"';
				if ( $required ) {
					echo ' required';
				}
				echo '>';
				echo '<option value="">' . esc_html__( 'Select...', 'wb-listora' ) . '</option>';
				foreach ( $options as $opt ) {
					$selected = ( $has_value && (string) $existing_value === (string) $opt['value'] ) ? ' selected' : '';
					echo '<option value="' . esc_attr( $opt['value'] ) . '"' . $selected . '>' . esc_html( $opt['label'] ) . '</option>';
				}
				echo '</select>';
				break;

			case 'multiselect':
				$selected_values = $has_value && is_array( $existing_value ) ? array_map( 'strval', $existing_value ) : array();
				echo '<div class="listora-submission__checkbox-group">';
				foreach ( $options as $opt ) {
					$checked = in_array( (string) $opt['value'], $selected_values, true ) ? ' checked' : '';
					echo '<label class="listora-submission__checkbox-label">';
					echo '<input type="checkbox" name="' . esc_attr( $field_name ) . '[]" value="' . esc_attr( $opt['value'] ) . '"' . $checked . ' />';
					echo ' ' . esc_html( $opt['label'] );
					echo '</label>';
				}
				echo '</div>';
				break;

			case 'checkbox':
				$checked = ( $has_value && $existing_value ) ? ' checked' : '';
				echo '<label class="listora-submission__checkbox-label">';
				echo '<input type="checkbox" name="' . esc_attr( $field_name ) . '" value="1"' . $checked . ' />';
				echo ' ' . esc_html( $label );
				echo '</label>';
				break;

			case 'date':
				echo '<input type="date" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $field_name ) . '" class="listora-input"';
				if ( $has_value ) {
					echo ' value="' . esc_attr( (string) $existing_value ) . '"';
				}
				if ( $required ) {
					echo ' required';
				}
				echo ' />';
				break;

			case 'time':
				echo '<input type="time" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $field_name ) . '" class="listora-input"';
				if ( $has_value ) {
					echo ' value="' . esc_attr( (string) $existing_value ) . '"';
				}
				if ( $required ) {
					echo ' required';
				}
				echo ' />';
				break;

			case 'datetime':
				echo '<input type="datetime-local" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $field_name ) . '" class="listora-input"';
				if ( $has_value ) {
					echo ' value="' . esc_attr( (string) $existing_value ) . '"';
				}
				if ( $required ) {
					echo ' required';
				}
				echo ' />';
				break;

			case 'price':
				echo '<div class="listora-submission__price-field">';
				echo '<span class="listora-submission__currency">' . esc_html( wb_listora_get_setting( 'currency', 'USD' ) ) . '</span>';
				echo '<input type="number" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $field_name ) . '" class="listora-input" step="0.01" min="0"';
				echo ' placeholder="0.00"';
				if ( $has_value ) {
					echo ' value="' . esc_attr( (string) $existing_value ) . '"';
				}
				if ( $required ) {
					echo ' required';
				}
				echo ' />';
				echo '</div>';
				break;

			case 'map_location':
				// Existing value is an array: [address, lat, lng, city, state, country, postal_code].
				$loc = ( $has_value && is_array( $existing_value ) ) ? $existing_value : array();
				echo '<div class="listora-submission__map-field">';
				echo '<input type="text" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $field_name ) . '[address]" class="listora-input"';
				echo ' placeholder="' . esc_attr__( 'Enter address...', 'wb-listora' ) . '"';
				if ( ! empty( $loc['address'] ) ) {
					echo ' value="' . esc_attr( $loc['address'] ) . '"';
				}
				if ( $required ) {
					echo ' required';
				}
				echo ' />';
				echo '<div class="listora-submission__map-picker" id="listora-map-picker-' . esc_attr( $key ) . '" style="height:250px;margin-top:0.5rem;border-radius:var(--listora-card-radius);"></div>';
				echo '<div class="listora-submission__map-coords" style="display:flex;gap:0.5rem;margin-top:0.5rem;">';
				foreach ( array( 'lat', 'lng', 'city', 'state', 'country', 'postal_code' ) as $loc_key ) {
					$loc_val = ! empty( $loc[ $loc_key ] ) ? $loc[ $loc_key ] : '';
					echo '<input type="hidden" name="' . esc_attr( $field_name ) . '[' . esc_attr( $loc_key ) . ']" value="' . esc_attr( $loc_val ) . '" />';
				}
				echo '</div>';
				echo '</div>';
				break;

			case 'business_hours':
				// Existing value is a nested array keyed by day number.
				$hours_data = ( $has_value && is_array( $existing_value ) ) ? $existing_value : array();
				echo '<div class="listora-submission__hours-builder" id="listora-hours-builder">';
				$days = array(
					__( 'Monday', 'wb-listora' ),
					__( 'Tuesday', 'wb-listora' ),
					__( 'Wednesday', 'wb-listora' ),
					__( 'Thursday', 'wb-listora' ),
					__( 'Friday', 'wb-listora' ),
					__( 'Saturday', 'wb-listora' ),
					__( 'Sunday', 'wb-listora' ),
				);
				foreach ( $days as $d => $day_name ) {
					$day_num    = ( $d + 1 ) % 7; // 0=Sun.
					$day_data   = $hours_data[ $day_num ] ?? array();
					$open_val   = ! empty( $day_data['open'] ) ? $day_data['open'] : '';
					$close_val  = ! empty( $day_data['close'] ) ? $day_data['close'] : '';
					$is_closed  = ! empty( $day_data['closed'] );
					echo '<div class="listora-submission__hours-row">';
					echo '<span class="listora-submission__hours-day">' . esc_html( $day_name ) . '</span>';
					echo '<input type="time" name="' . esc_attr( $field_name ) . '[' . $day_num . '][open]" class="listora-input" style="width:auto;" value="' . esc_attr( $open_val ) . '" aria-label="' . esc_attr( sprintf( /* translators: %s: day of week */ __( '%s opening time', 'wb-listora' ), $day_name ) ) . '" />';
					echo '<span>–</span>';
					echo '<input type="time" name="' . esc_attr( $field_name ) . '[' . $day_num . '][close]" class="listora-input" style="width:auto;" value="' . esc_attr( $close_val ) . '" aria-label="' . esc_attr( sprintf( /* translators: %s: day of week */ __( '%s closing time', 'wb-listora' ), $day_name ) ) . '" />';
					echo '<label class="listora-submission__checkbox-label"><input type="checkbox" name="' . esc_attr( $field_name ) . '[' . $day_num . '][closed]" value="1"' . ( $is_closed ? ' checked' : '' ) . ' /> ' . esc_html__( 'Closed', 'wb-listora' ) . '</label>';
					echo '</div>';
				}
				echo '</div>';
				break;

			case 'file':
				echo '<div class="listora-submission__upload-zone listora-submission__upload-zone--small" data-wp-on--click="actions.openMediaUpload" data-wp-context=\'{"uploadTarget":"' . esc_attr( $field_name ) . '"}\'>';
				echo '<span>' . esc_html__( 'Click to upload', 'wb-listora' ) . '</span>';
				echo '</div>';
				echo '<input type="hidden" name="' . esc_attr( $field_name ) . '" value="' . ( $has_value ? esc_attr( (string) $existing_value ) : '' ) . '" />';
				break;

			case 'color':
				echo '<input type="color" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $field_name ) . '"' . ( $has_value ? ' value="' . esc_attr( (string) $existing_value ) . '"' : '' ) . ' />';
				break;

			default:
				echo '<input type="text" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $field_name ) . '" class="listora-input"';
				echo ' placeholder="' . esc_attr( $placeholder ) . '"';
				if ( $has_value ) {
					echo ' value="' . esc_attr( (string) $existing_value ) . '"';
				}
				if ( $required ) {
					echo ' required';
				}
				echo ' />';
				break;
		}

		echo '</div>';
	}
endif;
