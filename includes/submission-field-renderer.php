<?php
/**
 * Submission field renderer — shared helper for the listing-submission block.
 *
 * Extracted from blocks/listing-submission/render.php so the step-details
 * template can call it regardless of whether render.php has already run
 * its tail (function declarations at the end of render.php aren't available
 * to templates included from render.php's middle).
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wb_listora_render_submission_field' ) ) :
	/**
	 * Render a single field for the submission form.
	 *
	 * @param \WBListora\Core\Field $field          Field definition.
	 * @param mixed                 $existing_value Existing value to pre-fill (null when creating).
	 * @param array                 $prefill_meta   Full edit-mode meta map. Used by composite
	 *                                              fields (map_location) to read sibling keys
	 *                                              (latitude/longitude/city/etc.) that are
	 *                                              stored flat by Meta_Handler.
	 */
	function wb_listora_render_submission_field( $field, $existing_value = null, array $prefill_meta = array() ): void {
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
		$conditional    = $field->get( 'conditional' );
		$condition_attr = '';
		$hidden_class   = '';

		if ( ! empty( $conditional ) && is_array( $conditional ) ) {
			$condition_json = (string) wp_json_encode( $conditional );
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
				// Stored shape is `[ 'amount' => 1234, 'currency' => 'USD' ]` (sanitized
				// via Field::sanitize_json on submit), but the input edits the amount
				// scalar. Extract `amount` so edit-mode prefill round-trips correctly
				// (Basecamp 9842576349). Falls back to scalar for legacy stores.
				$price_value = '';
				if ( $has_value ) {
					if ( is_array( $existing_value ) && isset( $existing_value['amount'] ) ) {
						$price_value = (string) $existing_value['amount'];
					} else {
						$price_value = (string) $existing_value;
					}
				}
				echo '<div class="listora-submission__price-field">';
				echo '<span class="listora-submission__currency">' . esc_html( wb_listora_get_setting( 'currency', 'USD' ) ) . '</span>';
				echo '<input type="number" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $field_name ) . '" class="listora-input" step="0.01" min="0"';
				echo ' placeholder="0.00"';
				if ( '' !== $price_value ) {
					echo ' value="' . esc_attr( $price_value ) . '"';
				}
				if ( $required ) {
					echo ' required';
				}
				echo ' />';
				echo '</div>';
				break;

			case 'map_location':
				// Submitted as a composite (`meta_address[address]`, `[lat]`, `[lng]`,
				// `[city]`, …) and persisted by the controller as separate top-level
				// meta keys. Meta_Handler returns those flat — `prefill_meta` carries
				// `address` (text), `latitude`, `longitude`, `city`, `state`,
				// `country`, `postal_code`. Build the composite the renderer expects
				// instead of relying on `$existing_value` (which is just the address
				// text and would leave lat/lng/etc. blank — Basecamp 9842576349).
				if ( $has_value && is_array( $existing_value ) ) {
					$loc = $existing_value;
				} else {
					$loc = array(
						'address'     => is_string( $existing_value ) ? $existing_value : ( $prefill_meta['address'] ?? '' ),
						'lat'         => $prefill_meta['latitude'] ?? ( $prefill_meta['lat'] ?? '' ),
						'lng'         => $prefill_meta['longitude'] ?? ( $prefill_meta['lng'] ?? '' ),
						'city'        => $prefill_meta['city'] ?? '',
						'state'       => $prefill_meta['state'] ?? '',
						'country'     => $prefill_meta['country'] ?? '',
						'postal_code' => $prefill_meta['postal_code'] ?? '',
					);
				}
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

				// Expose admin-configured map defaults so the picker centers on
				// the configured location instead of New York. The picker JS in
				// src/blocks/listing-submission/view.js reads these data attrs
				// before falling back to the previous NYC hard-code.
				$map_default_lat  = (float) wb_listora_get_setting( 'map_default_lat', 40.7128 );
				$map_default_lng  = (float) wb_listora_get_setting( 'map_default_lng', -74.0060 );
				$map_default_zoom = (int) wb_listora_get_setting( 'map_default_zoom', 12 );

				echo '<div class="listora-submission__map-picker"';
				echo ' id="listora-map-picker-' . esc_attr( $key ) . '"';
				echo ' data-default-lat="' . esc_attr( (string) $map_default_lat ) . '"';
				echo ' data-default-lng="' . esc_attr( (string) $map_default_lng ) . '"';
				echo ' data-default-zoom="' . esc_attr( (string) $map_default_zoom ) . '"';
				echo '></div>';
				echo '<div class="listora-submission__map-coords">';
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
					echo '<input type="time" name="' . esc_attr( $field_name ) . '[' . $day_num . '][open]" class="listora-input listora-submission__hours-input" value="' . esc_attr( $open_val ) . '" aria-label="' . esc_attr( sprintf( /* translators: %s: day of week */ __( '%s opening time', 'wb-listora' ), $day_name ) ) . '" />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $day_num is an integer (0-6).
					echo '<span>–</span>';
					echo '<input type="time" name="' . esc_attr( $field_name ) . '[' . $day_num . '][close]" class="listora-input listora-submission__hours-input" value="' . esc_attr( $close_val ) . '" aria-label="' . esc_attr( sprintf( /* translators: %s: day of week */ __( '%s closing time', 'wb-listora' ), $day_name ) ) . '" />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $day_num is an integer (0-6).
					echo '<label class="listora-submission__checkbox-label"><input type="checkbox" name="' . esc_attr( $field_name ) . '[' . $day_num . '][closed]" value="1"' . ( $is_closed ? ' checked' : '' ) . ' /> ' . esc_html__( 'Closed', 'wb-listora' ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $day_num is integer (0-6); checked attribute is a controlled literal.
					echo '</div>';
				}
				echo '</div>';
				break;

			case 'file':
				$file_attachment_id = $has_value ? absint( $existing_value ) : 0;
				$file_preview_url   = $file_attachment_id ? wp_get_attachment_image_url( $file_attachment_id, 'medium' ) : '';

				echo '<div class="listora-submission__upload-zone listora-submission__upload-zone--small" data-wp-on--click="actions.openMediaUpload" data-wp-context=\'{"uploadTarget":"' . esc_attr( $field_name ) . '"}\'>';
				if ( $file_preview_url ) {
					// Edit-mode preview — show the saved file so admins/owners
					// don't see an empty upload zone (Basecamp 9838412472).
					echo '<img class="listora-submission__upload-preview" src="' . esc_url( $file_preview_url ) . '" alt="' . esc_attr( $label ) . '" />';
				} else {
					echo '<span>' . esc_html__( 'Click to upload', 'wb-listora' ) . '</span>';
				}
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
