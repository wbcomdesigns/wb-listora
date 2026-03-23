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
		'currentStep'   => $steps[0]['id'],
		'stepIndex'     => 0,
		'totalSteps'    => $total_steps,
		'listingType'   => $listing_type,
		'formData'      => new \stdClass(),
		'isSubmitting'  => false,
		'submitError'   => '',
		'submitSuccess' => false,
		'draftId'       => 0,
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
					<input type="radio" name="listing_type" value="<?php echo esc_attr( $type_item->get_slug() ); ?>"
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
			<h2><?php esc_html_e( 'Basic Information', 'wb-listora' ); ?></h2>

			<div class="listora-submission__field">
				<label for="listora-title" class="listora-submission__label">
					<?php esc_html_e( 'Listing Title', 'wb-listora' ); ?> <span class="required">*</span>
				</label>
				<input type="text" id="listora-title" name="title" class="listora-input" required
					placeholder="<?php esc_attr_e( 'e.g., Pizza Palace', 'wb-listora' ); ?>" />
			</div>

			<?php if ( ! empty( $type_categories ) || ! $listing_type ) : ?>
			<div class="listora-submission__field">
				<label for="listora-category" class="listora-submission__label">
					<?php esc_html_e( 'Category', 'wb-listora' ); ?> <span class="required">*</span>
				</label>
				<select id="listora-category" name="category" class="listora-input listora-select" required>
					<option value=""><?php esc_html_e( 'Select a category', 'wb-listora' ); ?></option>
					<?php foreach ( $type_categories as $cat ) : ?>
					<option value="<?php echo esc_attr( $cat->term_id ); ?>"><?php echo esc_html( $cat->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php endif; ?>

			<div class="listora-submission__field">
				<label for="listora-tags" class="listora-submission__label">
					<?php esc_html_e( 'Tags', 'wb-listora' ); ?>
				</label>
				<input type="text" id="listora-tags" name="tags" class="listora-input"
					placeholder="<?php esc_attr_e( 'pizza, italian, downtown (comma separated)', 'wb-listora' ); ?>" />
			</div>

			<div class="listora-submission__field">
				<label for="listora-description" class="listora-submission__label">
					<?php esc_html_e( 'Description', 'wb-listora' ); ?> <span class="required">*</span>
				</label>
				<textarea id="listora-description" name="description" class="listora-input listora-submission__textarea" rows="6" required
					placeholder="<?php esc_attr_e( 'Describe your listing...', 'wb-listora' ); ?>"></textarea>
			</div>
		</div>

		<?php // ─── Step: Details (type-specific fields) ─── ?>
		<div class="listora-submission__step" data-step="details" hidden>
			<h2><?php esc_html_e( 'Details', 'wb-listora' ); ?></h2>
			<p class="listora-submission__step-desc"><?php esc_html_e( 'Provide additional details about your listing.', 'wb-listora' ); ?></p>

			<?php
			// Render type-specific fields.
			if ( $listing_type ) {
				$type_obj = $registry->get( $listing_type );
				if ( $type_obj ) {
					foreach ( $type_obj->get_field_groups() as $group ) {
						echo '<fieldset class="listora-submission__fieldset">';
						echo '<legend class="listora-submission__fieldset-legend">' . esc_html( $group->get_label() ) . '</legend>';

						foreach ( $group->get_fields() as $field ) {
							wb_listora_render_submission_field( $field );
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
					<?php esc_html_e( 'Featured Image', 'wb-listora' ); ?> <span class="required">*</span>
				</label>
				<div class="listora-submission__upload-zone" data-wp-on--click="actions.openMediaUpload" data-wp-context='{"uploadTarget":"featured_image"}'>
					<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
						<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/>
						<path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
					</svg>
					<span><?php esc_html_e( 'Click to upload or drag & drop', 'wb-listora' ); ?></span>
					<span class="listora-submission__upload-hint"><?php esc_html_e( 'Max 5MB, JPG/PNG/WebP', 'wb-listora' ); ?></span>
				</div>
				<input type="hidden" name="featured_image" value="" />
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
					<div class="listora-submission__gallery-thumbs" id="listora-gallery-thumbs"></div>
					<button type="button" class="listora-btn listora-btn--secondary listora-submission__add-photos"
						data-wp-on--click="actions.openMediaUpload" data-wp-context='{"uploadTarget":"gallery"}'>
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
						<?php esc_html_e( 'Add Photos', 'wb-listora' ); ?>
					</button>
				</div>
				<input type="hidden" name="gallery" value="" />
			</div>

			<div class="listora-submission__field">
				<label for="listora-video" class="listora-submission__label"><?php esc_html_e( 'Video URL (optional)', 'wb-listora' ); ?></label>
				<input type="url" id="listora-video" name="video" class="listora-input"
					placeholder="<?php esc_attr_e( 'https://youtube.com/watch?v=...', 'wb-listora' ); ?>" />
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
					<?php esc_html_e( 'Submit Listing', 'wb-listora' ); ?>
				</button>
			</div>
		</div>

	</form>
</div>

<?php
/**
 * Render a single field for the submission form.
 *
 * @param \WBListora\Core\Field $field Field definition.
 */
if ( ! function_exists( 'wb_listora_render_submission_field' ) ) :
	function wb_listora_render_submission_field( $field ) {
		$key         = $field->get_key();
		$label       = $field->get_label();
		$type        = $field->get_type();
		$required    = $field->is_required();
		$placeholder = $field->get( 'placeholder' ) ?: '';
		$options     = $field->get( 'options' ) ?: array();
		$description = $field->get( 'description' ) ?: '';
		$width       = $field->get( 'width' ) ?: '100';
		$field_name  = 'meta_' . $key;

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
				echo '></textarea>';
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
					echo '<option value="' . esc_attr( $opt['value'] ) . '">' . esc_html( $opt['label'] ) . '</option>';
				}
				echo '</select>';
				break;

			case 'multiselect':
				echo '<div class="listora-submission__checkbox-group">';
				foreach ( $options as $opt ) {
					echo '<label class="listora-submission__checkbox-label">';
					echo '<input type="checkbox" name="' . esc_attr( $field_name ) . '[]" value="' . esc_attr( $opt['value'] ) . '" />';
					echo ' ' . esc_html( $opt['label'] );
					echo '</label>';
				}
				echo '</div>';
				break;

			case 'checkbox':
				echo '<label class="listora-submission__checkbox-label">';
				echo '<input type="checkbox" name="' . esc_attr( $field_name ) . '" value="1" />';
				echo ' ' . esc_html( $label );
				echo '</label>';
				break;

			case 'date':
				echo '<input type="date" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $field_name ) . '" class="listora-input"';
				if ( $required ) {
					echo ' required';
				}
				echo ' />';
				break;

			case 'time':
				echo '<input type="time" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $field_name ) . '" class="listora-input"';
				if ( $required ) {
					echo ' required';
				}
				echo ' />';
				break;

			case 'datetime':
				echo '<input type="datetime-local" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $field_name ) . '" class="listora-input"';
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
				if ( $required ) {
					echo ' required';
				}
				echo ' />';
				echo '</div>';
				break;

			case 'map_location':
				echo '<div class="listora-submission__map-field">';
				echo '<input type="text" id="' . esc_attr( $input_id ) . '-address" name="' . esc_attr( $field_name ) . '[address]" class="listora-input"';
				echo ' placeholder="' . esc_attr__( 'Enter address...', 'wb-listora' ) . '"';
				if ( $required ) {
					echo ' required';
				}
				echo ' />';
				echo '<div class="listora-submission__map-picker" id="listora-map-picker-' . esc_attr( $key ) . '" style="height:250px;margin-top:0.5rem;border-radius:var(--listora-card-radius);"></div>';
				echo '<div class="listora-submission__map-coords" style="display:flex;gap:0.5rem;margin-top:0.5rem;">';
				echo '<input type="hidden" name="' . esc_attr( $field_name ) . '[lat]" />';
				echo '<input type="hidden" name="' . esc_attr( $field_name ) . '[lng]" />';
				echo '<input type="hidden" name="' . esc_attr( $field_name ) . '[city]" />';
				echo '<input type="hidden" name="' . esc_attr( $field_name ) . '[state]" />';
				echo '<input type="hidden" name="' . esc_attr( $field_name ) . '[country]" />';
				echo '<input type="hidden" name="' . esc_attr( $field_name ) . '[postal_code]" />';
				echo '</div>';
				echo '</div>';
				break;

			case 'business_hours':
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
					$day_num = ( $d + 1 ) % 7; // 0=Sun.
					echo '<div class="listora-submission__hours-row">';
					echo '<span class="listora-submission__hours-day">' . esc_html( $day_name ) . '</span>';
					echo '<input type="time" name="' . esc_attr( $field_name ) . '[' . $day_num . '][open]" class="listora-input" style="width:auto;" />';
					echo '<span>–</span>';
					echo '<input type="time" name="' . esc_attr( $field_name ) . '[' . $day_num . '][close]" class="listora-input" style="width:auto;" />';
					echo '<label class="listora-submission__checkbox-label"><input type="checkbox" name="' . esc_attr( $field_name ) . '[' . $day_num . '][closed]" value="1" /> ' . esc_html__( 'Closed', 'wb-listora' ) . '</label>';
					echo '</div>';
				}
				echo '</div>';
				break;

			case 'file':
				echo '<div class="listora-submission__upload-zone listora-submission__upload-zone--small" data-wp-on--click="actions.openMediaUpload" data-wp-context=\'{"uploadTarget":"' . esc_attr( $field_name ) . '"}\'>';
				echo '<span>' . esc_html__( 'Click to upload', 'wb-listora' ) . '</span>';
				echo '</div>';
				echo '<input type="hidden" name="' . esc_attr( $field_name ) . '" />';
				break;

			case 'color':
				echo '<input type="color" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $field_name ) . '" />';
				break;

			default:
				echo '<input type="text" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $field_name ) . '" class="listora-input"';
				echo ' placeholder="' . esc_attr( $placeholder ) . '"';
				if ( $required ) {
					echo ' required';
				}
				echo ' />';
				break;
		}

		echo '</div>';
	}
endif;
