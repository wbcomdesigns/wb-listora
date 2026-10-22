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
				'address'          => __( 'Customers see this on the listing page. Type the address and press Enter to pick it from a list, or drop the pin on the map.', 'wb-listora' ),
				'phone'            => __( 'Public contact number. Shown to logged-in customers on the listing.', 'wb-listora' ),
				'email'            => __( 'Public contact email. Shown to logged-in customers — never to anonymous visitors.', 'wb-listora' ),
				'website'          => __( 'Public site or booking URL. Opens in a new tab.', 'wb-listora' ),
				'business_hours'   => __( "Customers see an 'Open now' badge based on these hours, in the listing's time zone.", 'wb-listora' ),
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

		/*
		 * The frontend wizard renders gallery and video itself in step-media.php,
		 * so the generic renderer skips them there to avoid a second Video URL.
		 * wp-admin has no media step, so both render below, driven by
		 * assets/js/admin/listing-media-fields.js.
		 */
		if ( in_array( $type, array( 'gallery', 'video' ), true ) && ! is_admin() ) {
			return;
		}

		// Every platform can be turned off in Settings. Bail before the outer
		// field wrapper and its "Social Links" heading print: a labelled section
		// with nothing to fill in is still a defect.
		if ( 'social_links' === $type && empty( \WBListora\Core\Field::social_link_platforms() ) ) {
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
		// A checkbox IS its own label, so no heading above it (it would repeat
		// the label and its for= would point at nothing).
		if ( ! in_array( $type, array( 'checkbox', 'toggle' ), true ) ) {
			echo '<label for="listora-field-' . esc_attr( $key ) . '" class="listora-submission__label">';
			echo esc_html( $label );
			if ( $required ) {
				echo ' <span class="required">*</span>';
			}
			echo '</label>';
		}

		if ( $description ) {
			echo '<span class="listora-submission__field-desc">' . esc_html( $description ) . '</span>';
		}

		$input_id = 'listora-field-' . esc_attr( $key );

		switch ( $type ) {
			case 'text':
			case 'phone':
			case 'url':
			case 'video':
			case 'email':
				// 'video' stores a URL (Field::sanitize is esc_url_raw) so it
				// takes the url input rather than the default text box.
				$input_type = ( 'phone' === $type ) ? 'tel' : ( 'video' === $type ? 'url' : $type );
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
				// No predefined options (e.g. the Job "skills" field, where the
				// poster types their own): fall back to a free-text input that
				// stores a comma-separated list under the same field name.
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
			case 'toggle':
				// A toggle is a boolean, saved as a checkbox, so it renders as one.
				$checked = ( $has_value && $existing_value ) ? ' checked' : '';
				echo '<label class="listora-submission__checkbox-label listora-submission__label" for="' . esc_attr( $input_id ) . '">';
				echo '<input type="checkbox" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $field_name ) . '" value="1"' . $checked . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $checked is a controlled literal string (' checked' or '').
				echo ' ' . esc_html( $label );
				if ( $required ) {
					echo ' <span class="required">*</span>';
				}
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
				// Stored shape is `[ 'amount' => 1234, 'currency' => 'USD' ]`
				// (Field::sanitize_json), but the input edits the amount scalar.
				// Extract `amount` so edit-mode prefill round-trips. Falls back to
				// scalar for legacy stores.
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
				// The symbol, matching listing cards, detail pages and the dashboard.
				// This printed the bare ISO code, so a EUR site asked for a price
				// beside "EUR" while every other surface showed "€".
				$listora_price_symbol = function_exists( 'wb_listora_get_currency_format' )
					? wb_listora_get_currency_format()['symbol']
					: wb_listora_get_setting( 'currency', 'USD' );
				echo '<span class="listora-submission__currency">' . esc_html( $listora_price_symbol ) . '</span>';
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
				// Submitted and stored as ONE composite array under one meta key
				// (`meta_address[address]`, `[lat]`, `[lng]`, `[city]`, ...); see
				// Field::sanitize_map_location(), which owns the child-key list.
				// The else branch reads legacy rows that kept the parts in separate
				// meta keys (`latitude`, `longitude`, `city`, ...).
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
						'country_code' => $prefill_meta['country_code'] ?? '',
						'postal_code' => $prefill_meta['postal_code'] ?? '',
					);
				}
				echo '<div class="listora-submission__map-field">';
				echo '<input type="text" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $field_name ) . '[address]" class="listora-input"';
				echo ' placeholder="' . esc_attr__( 'Enter address...', 'wb-listora' ) . '"';
				if ( ! empty( $loc['address'] ) ) {
					echo ' value="' . esc_attr( $loc['address'] ) . '"';
				}
				// A city or a map pin already places the listing: a city-level listing
				// (imported, or saved before streets were asked for) is not blocked
				// from an unrelated edit by an empty street line.
				if ( $required && empty( $loc['city'] ) && empty( $loc['lat'] ) ) {
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

				/*
				 * The picker uses the SAME tile source as the display map, via
				 * wb_listora_get_map_tiles() (shared with blocks/listing-map and the
				 * /settings/maps REST payload). An empty URL stays empty: the picker
				 * renders no raster layer rather than substituting OSM's public
				 * tiles, whose usage policy does not permit product-scale use.
				 */
				$map_tiles = function_exists( 'wb_listora_get_map_tiles' )
					? wb_listora_get_map_tiles()
					: array(
						'url'         => '',
						'attribution' => '',
					);

				echo '<div class="listora-submission__map-picker"';
				echo ' id="listora-map-picker-' . esc_attr( $key ) . '"';
				echo ' data-provider="' . esc_attr( $map_provider ) . '"';
				echo ' data-tile-url="' . esc_attr( (string) ( $map_tiles['url'] ?? '' ) ) . '"';
				echo ' data-tile-attribution="' . esc_attr( (string) ( $map_tiles['attribution'] ?? '' ) ) . '"';
				echo ' data-default-lat="' . esc_attr( (string) $map_default_lat ) . '"';
				echo ' data-default-lng="' . esc_attr( (string) $map_default_lng ) . '"';
				echo ' data-default-zoom="' . esc_attr( (string) $map_default_zoom ) . '"';
				echo '></div>';
				echo '<div class="listora-submission__map-coords">';
				foreach ( array( 'lat', 'lng', 'country', 'country_code' ) as $loc_key ) {
					$loc_val = ! empty( $loc[ $loc_key ] ) ? $loc[ $loc_key ] : '';
					echo '<input type="hidden" name="' . esc_attr( $field_name ) . '[' . esc_attr( $loc_key ) . ']" value="' . esc_attr( $loc_val ) . '" />';
				}
				echo '</div>';
				/*
				 * City, state and postal code are visible, editable inputs: a map
				 * pick fills them, but the geocoder's guess (e.g. a postcode) can
				 * be wrong. map-picker.js never overwrites a value typed here.
				 */
				echo '<div class="listora-submission__map-address-fields">';
				$address_field_labels = array(
					'city'        => __( 'City', 'wb-listora' ),
					'state'       => __( 'State / Region', 'wb-listora' ),
					'postal_code' => __( 'ZIP / Postal Code', 'wb-listora' ),
				);
				foreach ( $address_field_labels as $loc_key => $loc_label ) {
					$loc_val     = ! empty( $loc[ $loc_key ] ) ? $loc[ $loc_key ] : '';
					$loc_id      = $input_id . '-' . $loc_key;
					echo '<label class="listora-submission__map-address-field" for="' . esc_attr( $loc_id ) . '">';
					echo '<span class="listora-submission__map-address-label">' . esc_html( $loc_label ) . '</span>';
					echo '<input type="text" id="' . esc_attr( $loc_id ) . '" class="listora-input" name="' . esc_attr( $field_name ) . '[' . esc_attr( $loc_key ) . ']" value="' . esc_attr( $loc_val ) . '" />';
					echo '</label>';
				}
				echo '</div>';
				echo '</div>';
				break;

			case 'business_hours':
				// Stored hours come in three shapes; read them through the shared
				// normaliser and regroup per day, or the form shows each entry
				// on the wrong day and a save writes that back.
				$hours_data = array();
				foreach ( $has_value ? wb_listora_normalize_hours( $existing_value ) : array() as $entry ) {
					$entry_day = (int) $entry['day'];
					if ( ! empty( $entry['closed'] ) ) {
						$hours_data[ $entry_day ]['closed'] = true;
					}
					if ( ! empty( $entry['is_24h'] ) ) {
						$hours_data[ $entry_day ]['is_24h'] = true;
					}
					if ( ! empty( $entry['open'] ) || ! empty( $entry['close'] ) ) {
						$hours_data[ $entry_day ]['ranges'][] = array(
							'open'  => (string) ( $entry['open'] ?? '' ),
							'close' => (string) ( $entry['close'] ?? '' ),
						);
					}
				}
				// Clock icon prefix for each <input type="time">: Firefox renders it
				// as a numeric spinner with no clock, so users miss that it is a
				// picker. Decorative; each input's aria-label carries the meaning.
				$hours_clock_icon = '<span class="listora-submission__hours-icon" aria-hidden="true">' . \WBListora\Core\Lucide_Icons::render( 'clock', 14 ) . '</span>';
				/*
				 * The builder carries everything the add/remove JS needs, so the
				 * script never hardcodes a limit or an English string: the cap
				 * comes from the same filterable source the save path enforces
				 * (Search_Indexer::max_hours_slots), and the three aria-label
				 * patterns come from the same translations the SSR rows use.
				 * JS substitutes the tokens by NAME, not by position, so a
				 * locale that reorders them still labels the right field.
				 */
				echo '<div class="listora-submission__hours-builder"'
					. ' data-max-slots="' . esc_attr( (string) \WBListora\Search\Search_Indexer::max_hours_slots() ) . '"'
					/* translators: 1: day of week, 2: range number */
					. ' data-tpl-open="' . esc_attr( __( '%1$s opening time %2$d', 'wb-listora' ) ) . '"'
					/* translators: 1: day of week, 2: range number */
					. ' data-tpl-close="' . esc_attr( __( '%1$s closing time %2$d', 'wb-listora' ) ) . '"'
					/* translators: 1: day of week, 2: range number */
					. ' data-tpl-remove="' . esc_attr( __( 'Remove %1$s time %2$d', 'wb-listora' ) ) . '"'
					. '>';
				// Fill the week from Monday in one click; view.js copies the first
				// card's ranges and toggles to the others.
				echo '<div class="listora-submission__hours-copy">';
				echo '<button type="button" class="listora-btn listora-btn--secondary listora-btn--sm" data-listora-hours-copy="all">' . esc_html__( 'Copy Monday to all days', 'wb-listora' ) . '</button>';
				echo '<button type="button" class="listora-btn listora-btn--secondary listora-btn--sm" data-listora-hours-copy="weekdays">' . esc_html__( 'Copy Monday to weekdays', 'wb-listora' ) . '</button>';
				echo '</div>';
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
					$is_closed = ! empty( $day_data['closed'] );
					$is_24h    = ! empty( $day_data['is_24h'] );

					/*
					 * A day may hold several ranges — a lunch or riposo break, a
					 * split retail shift. Read them from the explicit `ranges`
					 * key, falling back to the single open/close pair every
					 * listing saved before this existed. Always at least one
					 * row, so an empty day still shows a pair to type into.
					 */
					$day_ranges = array();

					if ( isset( $day_data['ranges'] ) && is_array( $day_data['ranges'] ) ) {
						foreach ( $day_data['ranges'] as $range ) {
							if ( is_array( $range ) ) {
								$day_ranges[] = array(
									'open'  => (string) ( $range['open'] ?? '' ),
									'close' => (string) ( $range['close'] ?? '' ),
								);
							}
						}
					} elseif ( ! empty( $day_data['open'] ) || ! empty( $day_data['close'] ) ) {
						$day_ranges[] = array(
							'open'  => (string) ( $day_data['open'] ?? '' ),
							'close' => (string) ( $day_data['close'] ?? '' ),
						);
					}

					if ( ! $day_ranges ) {
						$day_ranges[] = array(
							'open'  => '',
							'close' => '',
						);
					}

					$max_ranges = \WBListora\Search\Search_Indexer::max_hours_slots();
					$day_ranges = array_slice( $day_ranges, 0, $max_ranges );
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
					/*
					 * One stacked block per day holding every range, so extra
					 * ranges grow DOWNWARD rather than pushing the toggles off
					 * the right edge — which is what the old single-row flex did
					 * even with one range: "Closed" was clipped out of view.
					 */
					echo '<span class="listora-submission__hours-ranges">';

					foreach ( $day_ranges as $slot => $range ) {
						$slot_name = esc_attr( $field_name ) . '[' . $day_num . '][ranges][' . (int) $slot . ']';

						echo '<span class="listora-submission__hours-times" data-slot="' . esc_attr( (string) $slot ) . '">';
						echo '<span class="listora-submission__hours-input-wrap">' . $hours_clock_icon // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- controlled SVG literal.
							. '<input type="time" name="' . $slot_name . '[open]" class="listora-input listora-submission__hours-input" value="' . esc_attr( $range['open'] ) . '"' . $times_disabled_at . ' aria-label="' . esc_attr( sprintf( /* translators: 1: day of week, 2: range number */ __( '%1$s opening time %2$d', 'wb-listora' ), $day_name, (int) $slot + 1 ) ) . '" />' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $slot_name is escaped above; $times_disabled_at is a controlled literal.
							. '</span>';
						echo '<span class="listora-submission__hours-sep">–</span>';
						echo '<span class="listora-submission__hours-input-wrap">' . $hours_clock_icon // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- controlled SVG literal.
							. '<input type="time" name="' . $slot_name . '[close]" class="listora-input listora-submission__hours-input" value="' . esc_attr( $range['close'] ) . '"' . $times_disabled_at . ' aria-label="' . esc_attr( sprintf( /* translators: 1: day of week, 2: range number */ __( '%1$s closing time %2$d', 'wb-listora' ), $day_name, (int) $slot + 1 ) ) . '" />' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $slot_name is escaped above; $times_disabled_at is a controlled literal.
							. '</span>';

						/*
						 * No remove on the first range. A day cannot have zero
						 * ranges — a member who wants none ticks Closed — so a
						 * control that empties the day is a dead end.
						 */
						if ( $slot > 0 ) {
							echo '<button type="button" class="listora-submission__hours-remove" aria-label="' . esc_attr( sprintf( /* translators: 1: day of week, 2: range number */ __( 'Remove %1$s time %2$d', 'wb-listora' ), $day_name, (int) $slot + 1 ) ) . '">&times;</button>';
						}

						echo '</span>';
					}

					/*
					 * Hidden at the cap, not disabled and not omitted. Absent is
					 * the right thing to SHOW — a greyed control invites a click
					 * and then has to explain itself — but omitting the element
					 * left the builder JS nothing to bring back when a member
					 * removed a range, so the third range was a one-way door.
					 * `hidden` is absent from the page and from the a11y tree,
					 * and it is still there to un-hide.
					 */
					$add_hidden = count( $day_ranges ) >= $max_ranges ? ' hidden' : '';
					echo '<button type="button" class="listora-submission__hours-add"' . $times_disabled_at . $add_hidden . ' data-day="' . esc_attr( (string) $day_num ) . '">' . esc_html__( '+ Add another time', 'wb-listora' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $times_disabled_at and $add_hidden are controlled literals.

					// Closes .listora-submission__hours-ranges. No second </span> may follow:
					// the parser would discard it, but under a theme that wraps the field it
					// would close the wrapper instead.
					echo '</span>';
					echo '<span class="listora-submission__hours-toggles">';
					// "Open 24 hours" — emits `[is_24h]` which the preview JS in
					// src/blocks/listing-submission/view.js (appendBusinessHoursPreview)
					// already reads to render the "Open 24 Hours" chip, and which
					// wb_listora_render_hours() / wb_listora_detail_open_status()
					// already honour on the published listing. The data layer supports is_24h
					// end-to-end, so the submission form needs a control to set it.
					echo '<label class="listora-submission__checkbox-label listora-submission__hours-toggle listora-submission__hours-toggle--24h"><input type="checkbox" class="listora-submission__hours-24h" name="' . esc_attr( $field_name ) . '[' . $day_num . '][is_24h]" value="1"' . ( $is_24h ? ' checked' : '' ) . ' /> ' . esc_html__( 'Open 24 hours', 'wb-listora' ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $day_num is integer (0-6); checked attribute is a controlled literal.
					echo '<label class="listora-submission__checkbox-label listora-submission__hours-toggle listora-submission__hours-toggle--closed"><input type="checkbox" class="listora-submission__hours-closed" name="' . esc_attr( $field_name ) . '[' . $day_num . '][closed]" value="1"' . ( $is_closed ? ' checked' : '' ) . ' /> ' . esc_html__( 'Closed', 'wb-listora' ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $day_num is integer (0-6); checked attribute is a controlled literal.
					echo '</span>';
					echo '</div>';
				}
				echo '</div>';
				break;

			case 'social_links':
				// The empty-platforms case already returned before this switch,
				// skipping the outer field wrapper and heading too.
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
				 * A real <button>, not a clickable <div>: this renderer serves every
				 * `file` custom field, so it must be focusable and keyboard
				 * operable. Both classes sit on the one element (no overlay child).
				 * The preview keeps the field label as alt text, which names the
				 * button once the icon and prompt are replaced by an image.
				 */
				echo '<button type="button" class="listora-submission__upload-zone listora-submission__upload-zone--small listora-submission__upload-trigger" data-wp-on--click="actions.openMediaUpload" data-wp-context=\'{"uploadTarget":"' . esc_attr( $field_name ) . '"}\'>';
				if ( $file_preview_url ) {
					// Edit-mode preview: show the saved file instead of an empty
					// upload zone.
					echo '<img class="listora-submission__upload-preview" src="' . esc_url( $file_preview_url ) . '" alt="' . esc_attr( $label ) . '" />';
				} else {
					echo '<span>' . esc_html__( 'Click to upload', 'wb-listora' ) . '</span>';
				}
				echo '</button>';
				echo '<input type="hidden" name="' . esc_attr( $field_name ) . '" value="' . ( $has_value ? esc_attr( (string) $existing_value ) : '' ) . '" />';
				break;

			case 'gallery':
				/*
				 * Admin-only — the frontend returns before the switch, because
				 * step-media.php renders its own uploader there.
				 *
				 * The markup deliberately does NOT use the submission wizard's
				 * data-wp-on--click directives: those are Interactivity API
				 * bindings and wp-admin never loads that store, which is exactly
				 * why the wizard's file control is inert on this screen. These
				 * hooks are plain classes, driven by wp.media in
				 * assets/js/admin/listing-media-fields.js.
				 */
				$gallery_ids = array();
				if ( $has_value ) {
					$gallery_ids = is_array( $existing_value )
						? array_map( 'absint', $existing_value )
						: array_filter( array_map( 'absint', explode( ',', (string) $existing_value ) ) );
				}

				echo '<div class="listora-admin-gallery" data-listora-admin-gallery>';
				echo '<div class="listora-admin-gallery__thumbs" data-listora-gallery-thumbs>';
				foreach ( $gallery_ids as $gallery_id ) {
					$gallery_url = wp_get_attachment_image_url( $gallery_id, 'thumbnail' );
					if ( ! $gallery_url ) {
						continue;
					}
					// Falling back to the field label would make a screen reader
					// read "Photo Gallery" once per thumbnail. These images are
					// decorative here — the remove button next to each one
					// carries the accessible name — so an empty alt is correct,
					// and it matches what the JS-added thumbnails emit.
					$gallery_alt = (string) get_post_meta( $gallery_id, '_wp_attachment_image_alt', true );
					echo '<div class="listora-admin-gallery__thumb" data-attachment-id="' . esc_attr( (string) $gallery_id ) . '">';
					echo '<img src="' . esc_url( $gallery_url ) . '" alt="' . esc_attr( $gallery_alt ) . '" />';
					echo '<button type="button" class="listora-admin-gallery__remove" data-listora-gallery-remove="' . esc_attr( (string) $gallery_id ) . '" aria-label="' . esc_attr__( 'Remove gallery image', 'wb-listora' ) . '">&times;</button>';
					echo '</div>';
				}
				echo '</div>';
				echo '<button type="button" class="button listora-admin-gallery__add" data-listora-gallery-add>' . esc_html__( 'Add Photos', 'wb-listora' ) . '</button>';
				echo '<input type="hidden" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( implode( ',', $gallery_ids ) ) . '" data-listora-gallery-input />';
				echo '</div>';
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
