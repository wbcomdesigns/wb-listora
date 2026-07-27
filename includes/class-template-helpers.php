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
		// Defense-in-depth: never let a template name escape the theme
		// override dir or the plugin templates dir. All internal callers pass
		// hard-coded relative names (e.g. 'emails/listing-submitted.php'), but
		// a '..' segment reaching this function would resolve to an arbitrary
		// file that wb_listora_get_template() then include()s. Strip any
		// parent-directory traversal and leading slashes while preserving the
		// legitimate sub-directory structure (slashes without '..').
		$template_name = str_replace( '\\', '/', (string) $template_name );
		if ( false !== strpos( $template_name, '..' ) ) {
			$safe_segments = array();
			foreach ( explode( '/', $template_name ) as $segment ) {
				if ( '' === $segment || '.' === $segment || '..' === $segment ) {
					continue;
				}
				$safe_segments[] = $segment;
			}
			$template_name = implode( '/', $safe_segments );
		}
		$template_name = ltrim( $template_name, '/' );

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
		$resolved = wb_listora_get_page_url( 'directory' );
		if ( '' === $resolved ) {
			$slug     = (string) wb_listora_get_setting( 'directory_slug', 'listings' );
			$resolved = home_url( '/' . ltrim( $slug, '/' ) . '/' );
		}

		return (string) apply_filters( 'wb_listora_directory_url', $resolved );
	}
}

if ( ! function_exists( 'wb_listora_get_submit_url' ) ) {

	/**
	 * Canonical "submit a listing" URL.
	 *
	 * @return string
	 */
	function wb_listora_get_submit_url() {
		$resolved = wb_listora_get_page_url( 'submission' );
		if ( '' === $resolved ) {
			$slug     = (string) wb_listora_get_setting( 'submission_slug', 'add-listing' );
			$resolved = home_url( '/' . ltrim( $slug, '/' ) . '/' );
		}

		return (string) apply_filters( 'wb_listora_submit_url', $resolved );
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
		$resolved = wb_listora_get_page_url( 'dashboard' );
		if ( '' === $resolved ) {
			// Pull the registered default slug so this fallback stays in lockstep
			// with Page_Registry. Previously this hardcoded 'dashboard' while the
			// registry uses 'my-dashboard' — sites where the page mapping wasn't
			// created (option missing, page trashed) built links to /dashboard/
			// which 404'd because the actual page is at /my-dashboard/.
			// Basecamp #9927424612.
			$config   = \WBListora\Core\Page_Registry::get_config( 'dashboard' );
			$default  = isset( $config['default_slug'] ) ? (string) $config['default_slug'] : 'my-dashboard';
			$slug     = (string) wb_listora_get_setting( 'dashboard_slug', $default );
			$resolved = home_url( '/' . ltrim( $slug, '/' ) . '/' );
		}

		$resolved = (string) apply_filters( 'wb_listora_dashboard_url', $resolved );

		if ( $tab_hash ) {
			$resolved = trailingslashit( $resolved ) . '#' . ltrim( $tab_hash, '#' );
		}

		return $resolved;
	}
}

if ( ! function_exists( 'wb_listora_is_setup_complete' ) ) {

	/**
	 * Whether the first-run setup wizard has been completed.
	 *
	 * THE single source of truth for "is setup done?" — checks the canonical
	 * top-level `wb_listora_setup_complete` flag and the legacy nested
	 * `wb_listora_settings.setup_complete` value (for installs that finished the
	 * wizard before the top-level flag existed). Every Free guard
	 * (activation redirect, onboarding notice, menu hiding, Health Check) and
	 * Pro's activation redirect read THIS so they can never disagree
	 * (card 10020037441). Pro consumes it via this documented helper — never by
	 * touching Free's internal Admin class (INV-3).
	 *
	 * @return bool
	 */
	function wb_listora_is_setup_complete() {
		$option = get_option( 'wb_listora_setup_complete', null );
		if ( '1' === (string) $option || true === $option ) {
			return true;
		}

		return ! empty( wb_listora_get_setting( 'setup_complete' ) );
	}
}

if ( ! function_exists( 'wb_listora_get_dashboard_add_url' ) ) {

	/**
	 * URL for the in-dashboard "Add Listing" inline form.
	 *
	 * Logged-in members manage all listings on the dashboard — no page hops
	 * for adding or editing. The standalone /submit-listing/ page remains
	 * the canonical entry point for external visitors / SEO landing /
	 * marketing — but anyone with an account opens the inline form here.
	 *
	 * @return string
	 */
	function wb_listora_get_dashboard_add_url() {
		return add_query_arg(
			array(
				'tab'    => 'listings',
				'action' => 'add',
			),
			wb_listora_get_dashboard_url()
		);
	}
}

if ( ! function_exists( 'wb_listora_get_dashboard_edit_url' ) ) {

	/**
	 * URL for the in-dashboard inline edit form for a specific listing.
	 *
	 * @param int $listing_id Listing post ID to edit.
	 * @return string
	 */
	function wb_listora_get_dashboard_edit_url( $listing_id ) {
		return add_query_arg(
			array(
				'tab'    => 'listings',
				'action' => 'edit',
				'id'     => (int) $listing_id,
			),
			wb_listora_get_dashboard_url()
		);
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
				<a href="<?php echo esc_url( $url ); ?>" class="listora-btn wp-element-button listora-btn--primary" target="_blank" rel="noopener">
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

if ( ! function_exists( 'wb_listora_prime_card_index_rows' ) ) {

	/**
	 * Prime the request-scoped review-stats cache for a batch of listings.
	 *
	 * Callers that render many cards in one request (the listing-grid block)
	 * call this once with every listing ID before their render loop. It runs
	 * a single `WHERE listing_id IN (…)` query via
	 * {@see \WBListora\Search\Search_Indexer::get_index_rows_for_ids()} and
	 * stores the keyed map in a request-scoped static, so the subsequent
	 * `wb_listora_prepare_card_data()` calls read avg_rating/review_count from
	 * memory instead of issuing one query per card (the N+1 the grid render
	 * previously incurred at 20 cards/page).
	 *
	 * @param array<int> $ids Listing post IDs.
	 * @return void
	 */
	function wb_listora_prime_card_index_rows( array $ids ): void {
		$map = \WBListora\Search\Search_Indexer::get_index_rows_for_ids( $ids );
		foreach ( $map as $listing_id => $row ) {
			wb_listora_card_index_row( (int) $listing_id, $row );
		}
	}
}

if ( ! function_exists( 'wb_listora_card_index_row' ) ) {

	/**
	 * Read or seed one listing's review stats from the request-scoped cache.
	 *
	 * Acts as both the store (when `$row` is passed by the primer above) and
	 * the lookup used by `wb_listora_prepare_card_data()`. When a row was not
	 * primed for the requested ID — e.g. the standalone listing-card or
	 * listing-featured blocks, which render a single card without a batch
	 * prime — it falls back to a single-ID batch fetch so behaviour is
	 * identical to the previous per-row query (same values, just routed
	 * through the same one-query helper).
	 *
	 * @param int                                                   $post_id Listing post ID.
	 * @param array{avg_rating: float, review_count: int}|null      $row     When provided, seeds the cache for this ID.
	 * @return array{avg_rating: float, review_count: int} Review stats (zeroed when the listing has no index row).
	 */
	function wb_listora_card_index_row( int $post_id, ?array $row = null ): array {
		static $cache = array();

		if ( null !== $row ) {
			$cache[ $post_id ] = array(
				'avg_rating'   => (float) ( $row['avg_rating'] ?? 0 ),
				'review_count' => (int) ( $row['review_count'] ?? 0 ),
			);
			return $cache[ $post_id ];
		}

		if ( ! array_key_exists( $post_id, $cache ) ) {
			$map               = \WBListora\Search\Search_Indexer::get_index_rows_for_ids( array( $post_id ) );
			$cache[ $post_id ] = $map[ $post_id ] ?? array(
				'avg_rating'   => 0.0,
				'review_count' => 0,
			);
		}

		return $cache[ $post_id ];
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

		// Rating from search index. Read from the request-scoped cache primed
		// by wb_listora_prime_card_index_rows() (listing-grid batches every
		// card ID up front — one IN(…) query instead of one query per card).
		// When the cache was not primed (standalone listing-card /
		// listing-featured rendering a single card), the helper transparently
		// falls back to a single-ID fetch — same values as before.
		$idx_row = wb_listora_card_index_row( (int) $post_id );

		$rating = array(
			'average' => (float) $idx_row['avg_rating'],
			'count'   => (int) $idx_row['review_count'],
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
				'verified' => wb_listora_is_verified( $post_id ),
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
				// Truncate with the real ellipsis char, NOT wp_trim_words()'s
				// default ' &hellip;' entity: this value is a plain-text display
				// string that consumers run through esc_html() (e.g. the Pro
				// comparison table), which would double-encode '&hellip;' into a
				// literal '&amp;hellip;' on screen. A real '…' is safe both
				// escaped and raw. (BC 9989808239 follow-up.)
				return is_string( $value ) ? wp_trim_words( $value, 5, '…' ) : '';
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

if ( ! function_exists( 'wb_listora_is_admin_screen' ) ) {

	/**
	 * Whether the current admin screen belongs to Listora (Free or Pro).
	 *
	 * Single source of truth for the "is this a Listora admin page" check.
	 * Free's asset enqueue, Free's admin-header injector, AND Pro's asset
	 * enqueue all call this helper so they stay aligned by construction —
	 * Pro's `wb-listora-pro-admin` style handle depends on Free's
	 * `listora-admin` handle, and dependency auto-pull only works when
	 * BOTH plugins enqueue their admin CSS on the same screen set.
	 *
	 * Detection rules (any match returns true):
	 *
	 *  - The screen's `post_type` starts with `listora_` — covers
	 *    `listora_listing` plus every Pro CPT (`listora_plan`,
	 *    `listora_coupon`, `listora_webhook`, `listora_badge`,
	 *    `listora_need`, …) so the edit/list screens for those types
	 *    get the same admin chrome as the rest of Listora.
	 *
	 *  - The screen's `taxonomy` starts with `listora_` — covers
	 *    `listora_listing_cat`, `_location`, `_feature`, `_listing_type`,
	 *    `_service_cat`, plus any future Pro taxonomy.
	 *
	 *  - The screen ID is `toplevel_page_listora` — the top-level menu
	 *    landing page (Listora Dashboard).
	 *
	 *  - The screen ID is prefixed `listora_page_` — every standard
	 *    submenu page added with `parent_slug='listora'`. Both Free's own
	 *    pages (Settings, Listing Types, Reviews, Claims, Import/Export,
	 *    Setup Wizard, Email Log) and every Pro submenu (Transactions,
	 *    Analytics, Audit Log, Moderators, …) get this prefix.
	 *
	 *  - The screen ID is prefixed `admin_page_listora-` — hidden
	 *    submenus registered with `''` parent slug. Used by Pro's
	 *    redirect stubs (Credit Mappings, Tools); included so the CSS
	 *    loads even in the brief window before the redirect runs.
	 *
	 * Filterable via `wb_listora_is_admin_screen` so themes or other
	 * plugins can extend the set (e.g. a custom admin page that uses
	 * Listora chrome but doesn't fit any of the patterns above).
	 *
	 * @return bool
	 */
	function wb_listora_is_admin_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		$is_listora = false;

		if ( ! empty( $screen->post_type ) && 0 === strpos( (string) $screen->post_type, 'listora_' ) ) {
			$is_listora = true;
		} elseif ( ! empty( $screen->taxonomy ) && 0 === strpos( (string) $screen->taxonomy, 'listora_' ) ) {
			$is_listora = true;
		} elseif ( 'toplevel_page_listora' === $screen->id ) {
			$is_listora = true;
		} elseif ( 0 === strpos( (string) $screen->id, 'listora_page_' ) ) {
			$is_listora = true;
		} elseif ( 0 === strpos( (string) $screen->id, 'admin_page_listora-' ) ) {
			$is_listora = true;
		} elseif ( 0 === strpos( (string) $screen->id, 'admin_page_wb-listora' ) ) {
			// Pro hidden submenu pages render under `admin_page_wb-listora-*`
			// (e.g. Pro Setup, License, Migration when registered after a Pro
			// license activates). Defensive coverage so F4 also reaches those
			// pages whenever they're available — current site has no license
			// so the pages don't render, but the rule is correct on principle.
			$is_listora = true;
		} elseif ( false !== strpos( (string) $screen->id, '_page_wb-listora' ) ) {
			// Pro pages mounted as visible submenus (any parent screen ID).
			$is_listora = true;
		}

		/**
		 * Filters whether the current admin screen is a Listora screen.
		 *
		 * @since 1.0.5
		 *
		 * @param bool       $is_listora Detection result.
		 * @param \WP_Screen $screen     Current screen object.
		 */
		return (bool) apply_filters( 'wb_listora_is_admin_screen', $is_listora, $screen );
	}
}

if ( ! function_exists( 'wb_listora_verify_rest_nonce' ) ) {

	/**
	 * Conditional proof-of-origin gate for guest-friendly REST writes.
	 *
	 * The anti-spam design behind `POST /listings/{id}/contact-form`,
	 * `POST /listings/{id}/contact` and `POST /analytics/track` is
	 * *proof-of-page-render*: a per-listing nonce printed into the page HTML
	 * proves a real browser rendered the listing before it wrote anything.
	 * That gate assumed a browser. A native client never renders the page and
	 * a nonce is session-bound, so it cannot mint one from an Application
	 * Password — every such request was a hard 403.
	 *
	 * This helper teaches the existing gate that **an authenticated request is
	 * also proof**, without opening an anonymous hole. It is the same shape
	 * `POST /submit` has always used ({@see \WBListora\REST\Submission_Controller::submit_listing()}):
	 * verify the nonce when one is sent, don't demand one when the caller has
	 * already proven who they are.
	 *
	 * Decision table:
	 *
	 * | Nonce sent | Logged in | Result                                    |
	 * |------------|-----------|-------------------------------------------|
	 * | yes        | either    | verified — invalid token still 403s       |
	 * | no         | yes       | allowed — the App Password IS the proof   |
	 * | no         | no        | 403 — anonymous + no proof stays rejected |
	 *
	 * Rate limits, honeypots and captcha checks are unaffected — they live in
	 * the handlers and run for every path, authenticated or not.
	 *
	 * @since 1.2.3
	 *
	 * @param string               $nonce   Nonce value as sent by the client ('' when absent).
	 * @param string               $action  Nonce action the token must verify against.
	 * @param array<string, mixed> $context Optional context for the filters: { route, listing_id }.
	 * @return true|\WP_Error True when the request may proceed, WP_Error (403) otherwise.
	 */
	function wb_listora_verify_rest_nonce( $nonce, $action, array $context = array() ) {
		$nonce = (string) $nonce;

		/**
		 * Restore strict, unconditional nonce verification.
		 *
		 * Escape hatch for the 1.2.3 default-behaviour change (production rule
		 * 3). A site that would rather keep the browser-only contract — and
		 * accept that the mobile app cannot contact listing owners — can
		 * restore the pre-1.2.3 behaviour with a one-line mu-plugin:
		 *
		 *     add_filter( 'wb_listora_require_rest_nonce', '__return_true' );
		 *
		 * @since 1.2.3
		 *
		 * @param bool                 $require Whether a valid nonce is mandatory regardless of auth. Default false.
		 * @param string               $action  Nonce action being verified.
		 * @param array<string, mixed> $context { route, listing_id }.
		 */
		$require_nonce = (bool) apply_filters( 'wb_listora_require_rest_nonce', false, $action, $context );

		// A token was sent — always verify it. The browser path is unchanged:
		// a stale or forged token is still a hard 403, logged in or not.
		if ( '' !== $nonce ) {
			if ( ! wp_verify_nonce( $nonce, $action ) ) {
				return new \WP_Error(
					'listora_invalid_nonce',
					__( 'Security check failed. Reload the page and try again.', 'wb-listora' ),
					array( 'status' => 403 )
				);
			}

			return true;
		}

		// No token. An authenticated caller (Application Password, cookie +
		// X-WP-Nonce, JWT, …) has already proven origin to WordPress itself,
		// so accept it — unless the site opted back into strict mode.
		if ( ! $require_nonce && is_user_logged_in() ) {
			return true;
		}

		// Anonymous with no proof of page render. Unchanged: rejected.
		return new \WP_Error(
			'listora_invalid_nonce',
			__( 'Security check failed. Reload the page and try again.', 'wb-listora' ),
			array( 'status' => 403 )
		);
	}
}

if ( ! function_exists( 'wb_listora_contact_rate_limit_identity' ) ) {

	/**
	 * Resolve the rate-limit identity for a contact-style message.
	 *
	 * The contact / lead rate limit (3 per hour per listing) was keyed on the
	 * client IP alone. Behind carrier-grade NAT — every mobile network — many
	 * unrelated users share one public IP, so three messages from three
	 * different people on the same carrier would throttle the fourth. The cap
	 * is meant to stop one *person* spamming, not one *network*.
	 *
	 * So: when we know who the caller is, key on the user. Guests keep the
	 * per-IP key byte-for-byte as before — for anonymous traffic the IP is the
	 * only identity available, and that path is unchanged.
	 *
	 * The cap and window are NOT changed here; only the identity the counter
	 * is bucketed by.
	 *
	 * @since 1.2.3
	 *
	 * @param int $listing_id Listing the message targets.
	 * @return array{scope: string, id: string} `scope` is 'user' or 'ip'; `id` is the bucket identity.
	 */
	function wb_listora_contact_rate_limit_identity( $listing_id ) {
		$user_id = get_current_user_id();

		if ( $user_id > 0 ) {
			$identity = array(
				'scope' => 'user',
				'id'    => (string) $user_id,
			);
		} else {
			$ip       = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
			$identity = array(
				'scope' => 'ip',
				'id'    => $ip,
			);
		}

		/**
		 * Filter the contact/lead rate-limit identity.
		 *
		 * Escape hatch for the 1.2.3 per-user bucketing change (production
		 * rule 3). To restore the pre-1.2.3 always-per-IP behaviour:
		 *
		 *     add_filter( 'wb_listora_contact_rate_limit_identity', function ( $identity ) {
		 *         $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
		 *         return array( 'scope' => 'ip', 'id' => $ip );
		 *     } );
		 *
		 * @since 1.2.3
		 *
		 * @param array{scope: string, id: string} $identity   Resolved identity.
		 * @param int                              $listing_id Listing ID.
		 * @param int                              $user_id    Current user ID (0 for guests).
		 */
		$identity = (array) apply_filters( 'wb_listora_contact_rate_limit_identity', $identity, (int) $listing_id, (int) $user_id );

		return array(
			'scope' => isset( $identity['scope'] ) ? (string) $identity['scope'] : 'ip',
			'id'    => isset( $identity['id'] ) ? (string) $identity['id'] : 'unknown',
		);
	}
}
