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

		// When the type-default doesn't set its own description, fall back to a
		// canonical hint keyed by field key. Site owners get contextual help on
		// shared fields without needing each of the 10 type defaults to repeat
		// the same strings. Site builders can extend / override via the filter.
		if ( '' === $description ) {
			$default_descriptions = array(
				'address'          => __( 'Customers see this on the listing page — start typing to auto-complete from the map.', 'wb-listora' ),
				'phone'            => __( 'Public contact number. Shown to logged-in customers on the listing.', 'wb-listora' ),
				'email'            => __( 'Public contact email. Shown to logged-in customers — never to anonymous visitors.', 'wb-listora' ),
				'website'          => __( 'Public site or booking URL. Opens in a new tab.', 'wb-listora' ),
				'business_hours'   => __( "Customers see an 'Open now' badge based on these hours and the visitor's timezone.", 'wb-listora' ),
				'price_range'      => __( 'Helps customers filter by budget.', 'wb-listora' ),
				'year_established' => __( 'Optional — adds a credibility signal customers value.', 'wb-listora' ),
				'social_links'     => __( 'Add the profile URLs for each platform you use.', 'wb-listora' ),
				'gallery'          => __( 'Up to 20 photos (JPG, PNG, WebP). The first image becomes the listing cover.', 'wb-listora' ),
				'capacity'         => __( 'Maximum group size. Shown in search filters.', 'wb-listora' ),
				'cuisine'          => __( 'Pick all that apply — customers search by cuisine.', 'wb-listora' ),
				'amenities'        => __( 'Tick every amenity the listing offers. Each one is a search facet.', 'wb-listora' ),
				'features'         => __( 'Tick every feature this listing offers. Each one is a search facet.', 'wb-listora' ),
				'bedrooms'         => __( 'Used by the search filters.', 'wb-listora' ),
				'bathrooms'        => __( 'Used by the search filters.', 'wb-listora' ),
				'square_feet'      => __( 'Total area. Used by the search filters.', 'wb-listora' ),
				'event_start'      => __( 'When the event begins. Shown on the listing detail page.', 'wb-listora' ),
				'event_end'        => __( 'When the event finishes. Calendar uses this for spans.', 'wb-listora' ),
				'job_type'         => __( 'Full-time, part-time, contract — used as a search filter.', 'wb-listora' ),
				'salary'           => __( 'Optional but improves applicant signal.', 'wb-listora' ),
			);

			/**
			 * Filters the default key→description fallback map.
			 *
			 * Lets sites add or override per-field hints without forking the
			 * type defaults. Empty string suppresses the fallback for a key.
			 *
			 * @since 1.0.5
			 *
			 * @param array<string, string> $default_descriptions Map of field key → translatable hint.
			 */
			$default_descriptions = (array) apply_filters( 'wb_listora_field_default_descriptions', $default_descriptions );

			if ( isset( $default_descriptions[ $key ] ) && '' !== (string) $default_descriptions[ $key ] ) {
				$description = (string) $default_descriptions[ $key ];
			}
		}

		// Skip complex types rendered separately.
		// gallery: dedicated step-media.php uploader.
		if ( in_array( $type, array( 'gallery' ), true ) ) {
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
				// No predefined options (e.g. the Job "skills" field defines an empty
				// options array so the poster types their own) — fall back to a free-text
				// input that stores a comma-separated list under the same field name.
				// Without this the renderer emits only a label + an empty checkbox group,
				// which reads as broken (Basecamp 9900622602).
				if ( empty( $options ) ) {
					$multiselect_text = '';
					if ( $has_value ) {
						$multiselect_text = is_array( $existing_value ) ? implode( ', ', array_map( 'strval', $existing_value ) ) : (string) $existing_value;
					}
					echo '<input type="text" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $field_name ) . '" class="listora-input"';
					echo ' placeholder="' . esc_attr( $placeholder ? $placeholder : __( 'Separate multiple values with commas', 'wb-listora' ) ) . '"';
					if ( '' !== $multiselect_text ) {
						echo ' value="' . esc_attr( $multiselect_text ) . '"';
					}
					if ( $required ) {
						echo ' required';
					}
					echo ' />';
					break;
				}
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
					} elseif ( is_scalar( $existing_value ) ) {
						// Legacy scalar rows (demo packs seed these directly, bypassing
						// the sanitizer). Any other shape — notably the empty array that
						// pre-1.4.2 saves persisted — casts to the literal "Array" and
						// raises a PHP warning, so it falls through to an empty value.
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

				// Expose the admin-configured map provider so the picker JS can
				// swap rendering engines. Default is 'osm' (Leaflet/OpenStreetMap,
				// shipped in Free). wb_listora_get_setting() fires the documented
				// `wb_listora_map_provider` filter for this key (see wb-listora.php),
				// the SAME hook the display map (blocks/listing-map) resolves through
				// — so the Add Listing picker honours the SAME provider the admin
				// selected. Pro's Google_Maps returns 'google' through that filter;
				// Free's picker JS keeps the OSM engine for any provider it doesn't
				// itself know how to render (see src/blocks/listing-submission/view.js).
				$map_provider = (string) wb_listora_get_setting( 'map_provider', 'osm' );

				echo '<div class="listora-submission__map-picker"';
				echo ' id="listora-map-picker-' . esc_attr( $key ) . '"';
				echo ' data-provider="' . esc_attr( $map_provider ) . '"';
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
				// Clock icon prefix that wraps each <input type="time">.
				// Firefox renders type="time" as a numeric spinner without a clock
				// chrome, so users miss that the field IS a picker (card 9856828615).
				// The icon is decorative — aria-label on each input still announces
				// the field semantically — and the native picker remains active.
				$hours_clock_icon = '<span class="listora-submission__hours-icon" aria-hidden="true">' . \WBListora\Core\Lucide_Icons::render( 'clock', 14 ) . '</span>';
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
					$is_24h    = ! empty( $day_data['is_24h'] );
					// `is_24h` and `closed` are mutually exclusive states; the
					// builder JS enforces this, but guard render-side too so a
					// hand-edited / imported row that set both doesn't show a
					// "Closed" row that the day also marks 24h. Closed wins.
					if ( $is_closed ) {
						$is_24h = false;
					}
					// Either modifier disables the open/close pickers (no times
					// apply when a day is Closed or Open-24h). The builder JS
					// toggles `disabled` live; this is the SSR starting state so
					// the markup is correct before hydration and for no-JS edits.
					$times_disabled    = ( $is_closed || $is_24h );
					$times_disabled_at = $times_disabled ? ' disabled' : '';
					$row_state_class   = $is_closed ? ' is-closed' : ( $is_24h ? ' is-24h' : '' );
					echo '<div class="listora-submission__hours-card' . esc_attr( $row_state_class ) . '">';
					echo '<span class="listora-submission__hours-day">' . esc_html( $day_name ) . '</span>';
					// Inline state chip — SSR'd with the saved state; the builder
					// JS (initBusinessHoursToggles in view.js) swaps the text live
					// as the "Open 24 hours" / "Closed" toggles change, so the row
					// gives immediate feedback before save (flow-closure f10).
					$listora_hours_state_label = $is_closed ? __( 'Closed', 'wb-listora' ) : ( $is_24h ? __( 'Open 24 Hours', 'wb-listora' ) : '' );
					echo '<span class="listora-submission__hours-state" aria-live="polite">' . esc_html( $listora_hours_state_label ) . '</span>';
					echo '<span class="listora-submission__hours-times">';
					echo '<span class="listora-submission__hours-input-wrap">' . $hours_clock_icon // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $hours_clock_icon is built from Lucide_Icons::render() which emits a controlled SVG literal.
						. '<input type="time" name="' . esc_attr( $field_name ) . '[' . $day_num . '][open]" class="listora-input listora-submission__hours-input" value="' . esc_attr( $open_val ) . '"' . $times_disabled_at . ' aria-label="' . esc_attr( sprintf( /* translators: %s: day of week */ __( '%s opening time', 'wb-listora' ), $day_name ) ) . '" />' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $day_num is an integer (0-6); $times_disabled_at is a controlled literal (' disabled' or '').
						. '</span>';
					echo '<span class="listora-submission__hours-sep">–</span>';
					echo '<span class="listora-submission__hours-input-wrap">' . $hours_clock_icon // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $hours_clock_icon is built from Lucide_Icons::render() which emits a controlled SVG literal.
						. '<input type="time" name="' . esc_attr( $field_name ) . '[' . $day_num . '][close]" class="listora-input listora-submission__hours-input" value="' . esc_attr( $close_val ) . '"' . $times_disabled_at . ' aria-label="' . esc_attr( sprintf( /* translators: %s: day of week */ __( '%s closing time', 'wb-listora' ), $day_name ) ) . '" />' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $day_num is an integer (0-6); $times_disabled_at is a controlled literal (' disabled' or '').
						. '</span>';
					echo '</span>';
					echo '<span class="listora-submission__hours-toggles">';
					// "Open 24 hours" — emits `[is_24h]` which the preview JS in
					// src/blocks/listing-submission/view.js (appendBusinessHoursPreview)
					// already reads to render the "Open 24 Hours" chip, and which
					// wb_listora_render_hours() / wb_listora_detail_open_status()
					// already honour on the published listing. Previously the data
					// layer supported is_24h end-to-end but the submission form had
					// no control to set it (Basecamp flow-closure f10).
					echo '<label class="listora-submission__checkbox-label listora-submission__hours-toggle listora-submission__hours-toggle--24h"><input type="checkbox" class="listora-submission__hours-24h" name="' . esc_attr( $field_name ) . '[' . $day_num . '][is_24h]" value="1"' . ( $is_24h ? ' checked' : '' ) . ' /> ' . esc_html__( 'Open 24 hours', 'wb-listora' ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $day_num is integer (0-6); checked attribute is a controlled literal.
					echo '<label class="listora-submission__checkbox-label listora-submission__hours-toggle listora-submission__hours-toggle--closed"><input type="checkbox" class="listora-submission__hours-closed" name="' . esc_attr( $field_name ) . '[' . $day_num . '][closed]" value="1"' . ( $is_closed ? ' checked' : '' ) . ' /> ' . esc_html__( 'Closed', 'wb-listora' ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $day_num is integer (0-6); checked attribute is a controlled literal.
					echo '</span>';
					echo '</div>';
				}
				echo '</div>';
				break;

			case 'social_links':
				$social_data = ( $has_value && is_array( $existing_value ) ) ? $existing_value : array();
				echo '<div class="listora-submission__social-links">';
				foreach ( \WBListora\Core\Field::social_link_platforms() as $platform_slug => $platform_label ) {
					$platform_value = isset( $social_data[ $platform_slug ] ) ? (string) $social_data[ $platform_slug ] : '';
					$platform_input = $input_id . '-' . $platform_slug;
					echo '<div class="listora-submission__social-row">';
					echo '<label for="' . esc_attr( $platform_input ) . '" class="listora-submission__social-label">' . esc_html( $platform_label ) . '</label>';
					echo '<input type="url" id="' . esc_attr( $platform_input ) . '" name="' . esc_attr( $field_name ) . '[' . esc_attr( $platform_slug ) . ']" class="listora-input listora-submission__social-input" value="' . esc_attr( $platform_value ) . '" placeholder="https://" inputmode="url" />';
					echo '</div>';
				}
				echo '</div>';
				break;

			case 'file':
				$file_attachment_id = $has_value ? absint( $existing_value ) : 0;
				$file_preview_url   = $file_attachment_id ? wp_get_attachment_image_url( $file_attachment_id, 'medium' ) : '';

				/*
				 * A real <button>, not a <div> with a click handler.
				 *
				 * This is the generic renderer, so the old div affected EVERY
				 * `file` custom field on every listing type — no tab stop, no
				 * Enter/Space, no focus ring. Same defect as the featured-image
				 * zone in step-media.php (LST-F-08), fixed the same way rather
				 * than patched with role/tabindex/keydown, which is three things
				 * to keep in sync instead of none.
				 *
				 * Both classes sit on the one element: there is no overlay child
				 * here, so no wrapper is needed.
				 *
				 * The preview keeps the field label as its alt text — it is what
				 * gives the button an accessible name once the icon and prompt
				 * are replaced by an image.
				 */
				echo '<button type="button" class="listora-submission__upload-zone listora-submission__upload-zone--small listora-submission__upload-trigger" data-wp-on--click="actions.openMediaUpload" data-wp-context=\'{"uploadTarget":"' . esc_attr( $field_name ) . '"}\'>';
				if ( $file_preview_url ) {
					// Edit-mode preview — show the saved file so admins/owners
					// don't see an empty upload zone (Basecamp 9838412472).
					echo '<img class="listora-submission__upload-preview" src="' . esc_url( $file_preview_url ) . '" alt="' . esc_attr( $label ) . '" />';
				} else {
					echo '<span>' . esc_html__( 'Click to upload', 'wb-listora' ) . '</span>';
				}
				echo '</button>';
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
