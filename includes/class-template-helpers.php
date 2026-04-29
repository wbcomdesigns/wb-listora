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

if ( ! function_exists( 'wb_listora_placeholder_url' ) ) {

	/**
	 * Get the URL of the default placeholder image.
	 *
	 * Bundled SVG placeholder ensures cards never show broken images.
	 * Themes can override: {theme}/wb-listora/images/placeholder.svg
	 *
	 * @return string Placeholder image URL.
	 */
	function wb_listora_placeholder_url() {
		$theme_file = get_stylesheet_directory() . '/wb-listora/images/placeholder.svg';
		if ( file_exists( $theme_file ) ) {
			$url = get_stylesheet_directory_uri() . '/wb-listora/images/placeholder.svg';
		} else {
			$url = WB_LISTORA_PLUGIN_URL . 'assets/images/placeholder.svg';
		}

		return apply_filters( 'wb_listora_placeholder_url', $url );
	}
}

if ( ! function_exists( 'wb_listora_resolve_term_id' ) ) {

	/**
	 * Resolve a taxonomy term reference (slug or numeric ID) to a term ID.
	 *
	 * Used by the listing-grid block to translate `?category=italian` and
	 * `?category=42` URLs into the term IDs that {@see Search_Engine}
	 * expects. Accepting both keeps URLs human-readable for end users
	 * while still working when callers already have a term ID.
	 *
	 * @param string $value    Slug or numeric term ID. Empty string returns 0.
	 * @param string $taxonomy Taxonomy name.
	 * @return int Term ID, or 0 when the value is empty / unknown.
	 */
	function wb_listora_resolve_term_id( $value, $taxonomy ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return 0;
		}

		if ( ctype_digit( $value ) ) {
			$term_id = (int) $value;
			$term    = get_term( $term_id, $taxonomy );
			return ( $term && ! is_wp_error( $term ) ) ? $term_id : 0;
		}

		$term = get_term_by( 'slug', $value, $taxonomy );
		return $term ? (int) $term->term_id : 0;
	}
}

if ( ! function_exists( 'wb_listora_get_directory_url' ) ) {

	/**
	 * Canonical "browse the directory" URL.
	 *
	 * Used by dashboard empty-state CTAs, upgrade prompts, and anywhere
	 * the plugin needs to point users at the public directory page.
	 * Filterable so custom setups can point to a different slug or
	 * a full archive URL.
	 *
	 * @return string
	 */
	function wb_listora_get_directory_url() {
		$slug    = (string) wb_listora_get_setting( 'directory_slug', 'listings' );
		$default = home_url( '/' . ltrim( $slug, '/' ) . '/' );

		$page_id = (int) wb_listora_get_setting( 'directory_page_id', 0 );
		if ( $page_id > 0 ) {
			$permalink = get_permalink( $page_id );
			if ( $permalink ) {
				$default = $permalink;
			}
		}

		return (string) apply_filters( 'wb_listora_directory_url', $default );
	}
}

if ( ! function_exists( 'wb_listora_get_submit_url' ) ) {

	/**
	 * Canonical "submit a listing" URL.
	 *
	 * @return string
	 */
	function wb_listora_get_submit_url() {
		$slug    = (string) wb_listora_get_setting( 'submission_slug', 'add-listing' );
		$default = home_url( '/' . ltrim( $slug, '/' ) . '/' );

		$page_id = (int) wb_listora_get_setting( 'submission_page_id', 0 );
		if ( $page_id > 0 ) {
			$permalink = get_permalink( $page_id );
			if ( $permalink ) {
				$default = $permalink;
			}
		}

		return (string) apply_filters( 'wb_listora_submit_url', $default );
	}
}

if ( ! function_exists( 'wb_listora_get_dashboard_url' ) ) {

	/**
	 * Canonical user dashboard URL (frontend). Falls back to configured
	 * setting, then to /dashboard/. Filterable.
	 *
	 * @param string $tab_hash Optional tab hash fragment (e.g. "claims").
	 * @return string
	 */
	function wb_listora_get_dashboard_url( $tab_hash = '' ) {
		$slug    = (string) wb_listora_get_setting( 'dashboard_slug', 'dashboard' );
		$default = home_url( '/' . ltrim( $slug, '/' ) . '/' );

		$page_id = (int) wb_listora_get_setting( 'dashboard_page_id', 0 );
		if ( $page_id > 0 ) {
			$permalink = get_permalink( $page_id );
			if ( $permalink ) {
				$default = $permalink;
			}
		}

		$default = (string) apply_filters( 'wb_listora_dashboard_url', $default );

		if ( $tab_hash ) {
			$default = trailingslashit( $default ) . '#' . ltrim( $tab_hash, '#' );
		}

		return $default;
	}
}

if ( ! function_exists( 'wb_listora_get_upgrade_url' ) ) {

	/**
	 * Canonical URL the user is sent to when clicking an "Upgrade to Pro" CTA
	 * in the free plugin. Defaults to a marketing URL but is filterable so
	 * self-hosted or white-labeled installs can redirect internally.
	 *
	 * @return string
	 */
	function wb_listora_get_upgrade_url() {
		$default = 'https://wbcomdesigns.com/downloads/wb-listora-pro/';

		return (string) apply_filters( 'wb_listora_upgrade_url', $default );
	}
}

if ( ! function_exists( 'wb_listora_require_logged_in' ) ) {

	/**
	 * Standard logged-in permission callback for REST endpoints.
	 *
	 * Returns a WP_Error(401) when the request is not authenticated. Use as
	 * `'permission_callback' => 'wb_listora_require_logged_in'` on any route
	 * that needs "any logged-in user" — avoids bare `'is_user_logged_in'`
	 * which returns an opaque 403 without a structured error code.
	 *
	 * @return true|\WP_Error
	 */
	function wb_listora_require_logged_in() {
		if ( is_user_logged_in() ) {
			return true;
		}

		return new \WP_Error(
			'listora_unauthorized',
			__( 'You must be logged in to perform this action.', 'wb-listora' ),
			array( 'status' => 401 )
		);
	}
}

if ( ! function_exists( 'wb_listora_render_pro_cta' ) ) {

	/**
	 * Render a reusable "Unlock with Pro" call-to-action card.
	 *
	 * Renders nothing when Pro is already active so legitimate users are
	 * never nagged. Accepts structured args for title, description, features,
	 * and optional custom button label.
	 *
	 * @param array $args {
	 *     @type string   $title       Heading.
	 *     @type string   $description Short lead paragraph.
	 *     @type string[] $features    Optional bullet list of benefits.
	 *     @type string   $button      Button label. Defaults to "Upgrade to Pro".
	 *     @type string   $url         Optional override for the upgrade URL.
	 *     @type string   $variant     "inline" (default), "card", "banner".
	 * }
	 *
	 * @param array<string,mixed> $args Structured options.
	 * @return void
	 */
	function wb_listora_render_pro_cta( array $args = array() ): void {
		if ( wb_listora_is_pro_active() ) {
			return;
		}

		$args = wp_parse_args(
			$args,
			array(
				'title'       => __( 'Unlock with WB Listora Pro', 'wb-listora' ),
				'description' => '',
				'features'    => array(),
				'button'      => __( 'Upgrade to Pro', 'wb-listora' ),
				'url'         => '',
				'variant'     => 'card',
			)
		);

		$url = $args['url'] ? $args['url'] : wb_listora_get_upgrade_url();

		$classes = 'listora-pro-cta listora-pro-cta--' . sanitize_html_class( $args['variant'] );
		?>
		<div class="<?php echo esc_attr( $classes ); ?>" role="complementary">
			<div class="listora-pro-cta__badge" aria-hidden="true">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
				</svg>
				<?php esc_html_e( 'Pro', 'wb-listora' ); ?>
			</div>
			<div class="listora-pro-cta__body">
				<h3 class="listora-pro-cta__title"><?php echo esc_html( $args['title'] ); ?></h3>
				<?php if ( $args['description'] ) : ?>
				<p class="listora-pro-cta__description"><?php echo esc_html( $args['description'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $args['features'] ) ) : ?>
				<ul class="listora-pro-cta__features">
					<?php foreach ( $args['features'] as $feature ) : ?>
					<li><?php echo esc_html( $feature ); ?></li>
					<?php endforeach; ?>
				</ul>
				<?php endif; ?>
			</div>
			<div class="listora-pro-cta__actions">
				<a href="<?php echo esc_url( $url ); ?>" class="listora-btn listora-btn--primary" target="_blank" rel="noopener">
					<?php echo esc_html( $args['button'] ); ?>
				</a>
			</div>
		</div>
		<?php
	}
}

if ( ! function_exists( 'wb_listora_render_pro_lock' ) ) {

	/**
	 * Inline "Pro" lock badge for places where we want a subtle hint rather than a full card.
	 *
	 * @param string $label Label shown next to the lock icon (default: "Pro").
	 * @return void
	 */
	function wb_listora_render_pro_lock( string $label = '' ): void {
		if ( wb_listora_is_pro_active() ) {
			return;
		}
		$label = $label ? $label : __( 'Pro', 'wb-listora' );
		?>
		<span class="listora-pro-lock" aria-label="<?php esc_attr_e( 'Requires Pro', 'wb-listora' ); ?>">
			<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
				<rect x="3" y="11" width="18" height="11" rx="2"/>
				<path d="M7 11V7a5 5 0 0 1 10 0v4"/>
			</svg>
			<?php echo esc_html( $label ); ?>
		</span>
		<?php
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

		$card_data = array(
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
				'featured' => \WBListora\Core\Featured::is_featured( $post_id ),
				'verified' => (bool) get_post_meta( $post_id, '_listora_is_verified', true ),
				'claimed'  => (bool) get_post_meta( $post_id, '_listora_is_claimed', true ),
			),
		);

		/**
		 * Filter the card data prepared for a listing.
		 *
		 * Fires once per card render — in listing-grid, listing-featured, and
		 * the standalone listing-card block. Use this to add or override card
		 * fields without subclassing templates.
		 *
		 * @param array    $card_data Card data keyed by id, title, link, excerpt,
		 *                            type, meta, image, location, rating,
		 *                            card_fields, features, badges.
		 * @param int      $post_id   Listing post ID.
		 * @param \WP_Post $post      Full post object.
		 */
		return apply_filters( 'wb_listora_card_view_data', $card_data, $post_id, $post );
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
