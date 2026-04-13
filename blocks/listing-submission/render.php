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
);

// Self-reference for sub-templates.
$view_data['view_data'] = $view_data;

wb_listora_get_template( 'blocks/listing-submission/submission.php', $view_data );

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

		// Conditional field support — add data attribute and hidden class if has condition.
		$conditional   = $field->get( 'conditional' );
		$condition_attr = '';
		$hidden_class   = '';

		if ( ! empty( $conditional ) && is_array( $conditional ) ) {
			$condition_json = wp_json_encode( $conditional );
			$condition_attr = ' data-listora-condition="' . esc_attr( $condition_json ) . '"';
			// Start hidden — JS will evaluate and show if condition is met.
			if ( null === $existing_value ) {
				$hidden_class = ' listora-submission__field--conditional-hidden';
			}
		}

		echo '<div class="listora-submission__field' . esc_attr( $hidden_class ) . '" ' . $style . $condition_attr . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $style is pre-built with esc_attr(), $condition_attr is pre-built with esc_attr().
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
					echo '<option value="' . esc_attr( $opt['value'] ) . '"' . $selected . '>' . esc_html( $opt['label'] ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $selected is a controlled literal string (' selected' or '').
				}
				echo '</select>';
				break;

			case 'multiselect':
				$selected_values = $has_value && is_array( $existing_value ) ? array_map( 'strval', $existing_value ) : array();
				echo '<div class="listora-submission__checkbox-group">';
				foreach ( $options as $opt ) {
					$checked = in_array( (string) $opt['value'], $selected_values, true ) ? ' checked' : '';
					echo '<label class="listora-submission__checkbox-label">';
					echo '<input type="checkbox" name="' . esc_attr( $field_name ) . '[]" value="' . esc_attr( $opt['value'] ) . '"' . $checked . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $checked is a controlled literal string (' checked' or '').
					echo ' ' . esc_html( $opt['label'] );
					echo '</label>';
				}
				echo '</div>';
				break;

			case 'checkbox':
				$checked = ( $has_value && $existing_value ) ? ' checked' : '';
				echo '<label class="listora-submission__checkbox-label">';
				echo '<input type="checkbox" name="' . esc_attr( $field_name ) . '" value="1"' . $checked . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $checked is a controlled literal string (' checked' or '').
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
					$day_num   = ( $d + 1 ) % 7; // 0=Sun.
					$day_data  = $hours_data[ $day_num ] ?? array();
					$open_val  = ! empty( $day_data['open'] ) ? $day_data['open'] : '';
					$close_val = ! empty( $day_data['close'] ) ? $day_data['close'] : '';
					$is_closed = ! empty( $day_data['closed'] );
					echo '<div class="listora-submission__hours-row">';
					echo '<span class="listora-submission__hours-day">' . esc_html( $day_name ) . '</span>';
					echo '<input type="time" name="' . esc_attr( $field_name ) . '[' . $day_num . '][open]" class="listora-input" style="width:auto;" value="' . esc_attr( $open_val ) . '" aria-label="' . esc_attr( sprintf( /* translators: %s: day of week */ __( '%s opening time', 'wb-listora' ), $day_name ) ) . '" />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $day_num is an integer (0-6).
					echo '<span>–</span>';
					echo '<input type="time" name="' . esc_attr( $field_name ) . '[' . $day_num . '][close]" class="listora-input" style="width:auto;" value="' . esc_attr( $close_val ) . '" aria-label="' . esc_attr( sprintf( /* translators: %s: day of week */ __( '%s closing time', 'wb-listora' ), $day_name ) ) . '" />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $day_num is an integer (0-6).
					echo '<label class="listora-submission__checkbox-label"><input type="checkbox" name="' . esc_attr( $field_name ) . '[' . $day_num . '][closed]" value="1"' . ( $is_closed ? ' checked' : '' ) . ' /> ' . esc_html__( 'Closed', 'wb-listora' ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $day_num is integer (0-6); checked attribute is a controlled literal.
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
