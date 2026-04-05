<?php
/**
 * Template helper functions used by block render.php files.
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wb_listora_locate_template' ) ) {

	/**
	 * Locate a template file, checking the theme first for overrides.
	 *
	 * Theme/child-theme can override any plugin template by placing a copy in:
	 *   wp-content/themes/{theme}/wb-listora/{template_name}
	 *
	 * @param string $template_name Template file name (e.g. 'emails/listing-submitted.php').
	 * @param string $template_path Theme subdirectory to search in. Default 'wb-listora/'.
	 * @param string $default_path  Absolute path to plugin templates directory.
	 * @return string Full path to the located template file.
	 */
	function wb_listora_locate_template( $template_name, $template_path = '', $default_path = '' ) {
		if ( ! $template_path ) {
			$template_path = 'wb-listora/';
		}
		if ( ! $default_path ) {
			$default_path = WB_LISTORA_PLUGIN_DIR . 'templates/';
		}

		// Look in theme/child-theme first.
		$template = locate_template(
			array(
				trailingslashit( $template_path ) . $template_name,
				$template_name,
			)
		);

		// Fall back to plugin templates directory.
		if ( ! $template ) {
			$template = trailingslashit( $default_path ) . $template_name;
		}

		/**
		 * Filter the located template path.
		 *
		 * @param string $template      Full path to located template.
		 * @param string $template_name Relative template name.
		 * @param string $template_path Theme subdirectory path.
		 */
		return apply_filters( 'wb_listora_locate_template', $template, $template_name, $template_path );
	}
}

if ( ! function_exists( 'wb_listora_get_template' ) ) {

	/**
	 * Load a template file with variable extraction.
	 *
	 * Locates the template (theme override or plugin default) and includes it
	 * with the provided arguments extracted into template scope.
	 *
	 * @param string $template_name Template file name (e.g. 'emails/listing-submitted.php').
	 * @param array  $args          Variables to extract into template scope.
	 * @param string $template_path Theme subdirectory to search in. Default 'wb-listora/'.
	 * @param string $default_path  Absolute path to plugin templates directory.
	 */
	function wb_listora_get_template( $template_name, $args = array(), $template_path = '', $default_path = '' ) {
		$template = wb_listora_locate_template( $template_name, $template_path, $default_path );

		if ( ! file_exists( $template ) ) {
			return;
		}

		/**
		 * Filter template arguments before rendering.
		 *
		 * @param array  $args          Template variables.
		 * @param string $template_name Relative template name.
		 */
		$args = apply_filters( 'wb_listora_template_args', $args, $template_name );

		if ( ! empty( $args ) && is_array( $args ) ) {
			extract( $args ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		}

		do_action( 'wb_listora_before_template', $template_name, $template_path, $args );
		include $template;
		do_action( 'wb_listora_after_template', $template_name, $template_path, $args );
	}
}

if ( ! function_exists( 'wb_listora_get_template_html' ) ) {

	/**
	 * Like wb_listora_get_template() but returns the HTML as a string.
	 *
	 * @param string $template_name Template file name.
	 * @param array  $args          Variables to extract into template scope.
	 * @param string $template_path Theme subdirectory to search in.
	 * @param string $default_path  Absolute path to plugin templates directory.
	 * @return string Rendered HTML.
	 */
	function wb_listora_get_template_html( $template_name, $args = array(), $template_path = '', $default_path = '' ) {
		ob_start();
		wb_listora_get_template( $template_name, $args, $template_path, $default_path );
		return ob_get_clean();
	}
}

if ( ! function_exists( 'wb_listora_prepare_card_data' ) ) {

	/**
	 * Prepare card data for a listing post.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null
	 */
	function wb_listora_prepare_card_data( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}

		$registry = \WBListora\Core\Listing_Type_Registry::instance();
		$type     = $registry->get_for_post( $post_id );
		$meta     = \WBListora\Core\Meta_Handler::get_all_values( $post_id );

		// Featured image.
		$thumb_id = get_post_thumbnail_id( $post_id );
		$image    = null;
		if ( $thumb_id ) {
			$full   = wp_get_attachment_image_src( $thumb_id, 'full' );
			$medium = wp_get_attachment_image_src( $thumb_id, 'medium_large' );
			$image  = array(
				'full'   => $full ? $full[0] : '',
				'medium' => $medium ? $medium[0] : '',
			);
		}

		// Location.
		$address  = $meta['address'] ?? array();
		$location = '';
		if ( is_array( $address ) ) {
			$parts    = array_filter( array( $address['city'] ?? '', $address['state'] ?? '' ) );
			$location = implode( ', ', $parts );
		}

		// Rating from search index.
		global $wpdb;
		$prefix  = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$idx_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT avg_rating, review_count FROM {$prefix}search_index WHERE listing_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$post_id
			),
			ARRAY_A
		);

		$rating = array(
			'average' => $idx_row ? (float) $idx_row['avg_rating'] : 0,
			'count'   => $idx_row ? (int) $idx_row['review_count'] : 0,
		);

		// Card fields.
		$card_fields = array();
		if ( $type ) {
			foreach ( $type->get_card_fields() as $field ) {
				$key     = $field->get_key();
				$value   = $meta[ $key ] ?? '';
				$display = wb_listora_format_card_value( $field, $value );
				if ( '' === $display ) {
					continue;
				}

				$badge_class = '';
				if ( 'checkbox' === $field->get_type() && $value ) {
					$display     = $field->get_label();
					$badge_class = 'listora-card__meta-item--badge';
				}
				if ( 'business_hours' === $field->get_type() ) {
					continue; // Open Now handled separately.
				}

				$card_fields[] = array(
					'key'           => $key,
					'label'         => $field->get_label(),
					'display_value' => $display,
					'badge_class'   => $badge_class,
				);
			}
		}

		// Features.
		$feature_terms = wp_get_object_terms( $post_id, 'listora_listing_feature' );
		$features      = array();
		if ( ! is_wp_error( $feature_terms ) ) {
			foreach ( $feature_terms as $term ) {
				$features[] = array(
					'name' => $term->name,
					'icon' => get_term_meta( $term->term_id, '_listora_icon', true ),
				);
			}
		}

		return array(
			'id'          => $post_id,
			'title'       => $post->post_title,
			'link'        => get_permalink( $post_id ),
			'excerpt'     => get_the_excerpt( $post ),
			'type'        => $type ? array(
				'slug'   => $type->get_slug(),
				'name'   => $type->get_name(),
				'color'  => $type->get_color(),
				'icon'   => $type->get_icon(),
				'schema' => $type->get_schema_type(),
			) : null,
			'meta'        => $meta,
			'image'       => $image,
			'location'    => $location,
			'rating'      => $rating,
			'card_fields' => $card_fields,
			'features'    => $features,
			'badges'      => array(
				'featured' => (bool) get_post_meta( $post_id, '_listora_is_featured', true ),
				'verified' => (bool) get_post_meta( $post_id, '_listora_is_verified', true ),
				'claimed'  => (bool) get_post_meta( $post_id, '_listora_is_claimed', true ),
			),
		);
	}
}

if ( ! function_exists( 'wb_listora_format_card_value' ) ) {

	/**
	 * Format a field value for card display.
	 *
	 * @param \WBListora\Core\Field $field Field definition.
	 * @param mixed                 $value Field value.
	 * @return string
	 */
	function wb_listora_format_card_value( $field, $value ) {
		if ( '' === $value || null === $value || ( is_array( $value ) && empty( $value ) ) ) {
			return '';
		}

		$type = $field->get_type();

		switch ( $type ) {
			case 'select':
			case 'radio':
				$options = $field->get( 'options' ) ?: array();
				foreach ( $options as $opt ) {
					if ( ( $opt['value'] ?? '' ) === $value ) {
						return $opt['label'] ?? $value;
					}
				}
				return (string) $value;

			case 'multiselect':
				if ( is_array( $value ) ) {
					$labels  = array();
					$options = $field->get( 'options' ) ?: array();
					foreach ( $value as $v ) {
						$found = false;
						foreach ( $options as $opt ) {
							if ( ( $opt['value'] ?? '' ) === $v ) {
								$labels[] = $opt['label'] ?? $v;
								$found    = true;
								break;
							}
						}
						if ( ! $found ) {
							$labels[] = $v;
						}
					}
					return implode( ', ', $labels );
				}
				return (string) $value;

			case 'price':
				if ( is_array( $value ) && isset( $value['amount'] ) ) {
					return wb_listora_format_currency( (float) $value['amount'], $value['currency'] ?? '' );
				}
				return is_numeric( $value ) ? wb_listora_format_currency( (float) $value ) : '';

			case 'checkbox':
				return $value ? $field->get_label() : '';

			case 'number':
			case 'rating':
				return is_numeric( $value ) ? number_format_i18n( (float) $value ) : '';

			case 'date':
				return $value ? wp_date( get_option( 'date_format' ), strtotime( $value ) ) : '';

			case 'datetime':
				return $value ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $value ) ) : '';

			case 'phone':
				return (string) $value;

			default:
				return is_string( $value ) ? wp_trim_words( $value, 5 ) : '';
		}
	}
}

if ( ! function_exists( 'wb_listora_format_currency' ) ) {

	/**
	 * Format a currency amount.
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency Currency code.
	 * @return string
	 */
	function wb_listora_format_currency( $amount, $currency = '' ) {
		if ( ! $currency ) {
			$currency = wb_listora_get_setting( 'currency', 'USD' );
		}

		$symbols = array(
			'USD' => '$',
			'EUR' => '€',
			'GBP' => '£',
			'JPY' => '¥',
			'INR' => '₹',
			'AUD' => 'A$',
			'CAD' => 'C$',
			'CHF' => 'CHF',
		);

		$symbol = $symbols[ $currency ] ?? $currency . ' ';

		if ( $amount >= 1000000 ) {
			return $symbol . number_format_i18n( $amount / 1000000, 1 ) . 'M';
		}
		if ( $amount >= 1000 ) {
			return $symbol . number_format_i18n( $amount / 1000, 0 ) . 'K';
		}

		return $symbol . number_format_i18n( $amount, $amount == floor( $amount ) ? 0 : 2 );
	}
}
