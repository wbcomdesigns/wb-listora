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
					'name' => wb_listora_decode_text( $term->name ),
					'icon' => get_term_meta( $term->term_id, '_listora_icon', true ),
				);
			}
		}

		/*
		 * Tags.
		 *
		 * Carried on the card so a visitor can follow one straight from a
		 * grid, the way the detail page lets them. Slug travels alongside the
		 * name because the chip links to `?tags=<slug>` — resolving the slug
		 * in the template would mean a term lookup per card per tag.
		 */
		$tag_terms    = wp_get_object_terms( $post_id, 'listora_listing_tag' );
		$listing_tags = array();
		if ( ! is_wp_error( $tag_terms ) ) {
			foreach ( $tag_terms as $tag_term ) {
				$listing_tags[] = array(
					'name' => wb_listora_decode_text( $tag_term->name ),
					'slug' => $tag_term->slug,
				);
			}
		}

		$card_data = array(
			'id'          => $post_id,
			'title'       => wb_listora_decode_text( $post->post_title ),
			'link'        => get_permalink( $post_id ),
			'excerpt'     => wb_listora_decode_text( get_the_excerpt( $post ) ),
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
			'tags'        => $listing_tags,
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

if ( ! function_exists( 'wb_listora_get_listing_cards' ) ) {

	/**
	 * Card payloads for a set of listings, in a fixed number of queries.
	 *
	 * The compact shape every list surface needs — image, rating, type,
	 * location — resolved for a whole page at once. Written because two REST
	 * lists needed the same enrichment and would otherwise have grown two
	 * copies of it: `/favorites`, whose rows were too thin to draw a card, and
	 * `/listings/{id}/related`, which returned the raw post shape with the
	 * title nested under `rendered` and no image at all.
	 *
	 * Deliberately NOT built on {@see wb_listora_prepare_card_data()}: that
	 * helper also reads taxonomy terms, term meta and the index row per
	 * listing, measured at ~7 queries per card even behind the cache primers,
	 * which is acceptable for a rendered grid but not for a REST list.
	 * Everything below comes from one `search_index` read plus two cache primes.
	 *
	 * @since 1.5.0
	 *
	 * @param int[] $listing_ids Listing post IDs.
	 * @return array<int, array<string, mixed>> Keyed by listing ID. IDs with no
	 *                                          index row are omitted.
	 */
	function wb_listora_get_listing_cards( array $listing_ids ) {
		global $wpdb;

		$listing_ids = array_values( array_unique( array_filter( array_map( 'intval', $listing_ids ) ) ) );
		if ( empty( $listing_ids ) ) {
			return array();
		}

		_prime_post_caches( $listing_ids, false, true );

		$prefix       = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$placeholders = implode( ',', array_fill( 0, count( $listing_ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$index_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT listing_id, listing_type, avg_rating, review_count, is_featured, city, country
				 FROM {$prefix}search_index WHERE listing_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$listing_ids
			),
			ARRAY_A
		);

		$index_by_id = array();
		foreach ( (array) $index_rows as $index_row ) {
			$index_by_id[ (int) $index_row['listing_id'] ] = $index_row;
		}

		// Prime thumbnails as one batch so the image lookups below are cache
		// reads rather than a query per row.
		$thumb_by_id = array();
		$thumb_ids   = array();
		foreach ( $listing_ids as $listing_id ) {
			$thumb_id = (int) get_post_thumbnail_id( $listing_id );
			if ( $thumb_id > 0 ) {
				$thumb_by_id[ $listing_id ] = $thumb_id;
				$thumb_ids[]                = $thumb_id;
			}
		}
		if ( ! empty( $thumb_ids ) ) {
			_prime_post_caches( $thumb_ids, false, true );
		}

		$registry = \WBListora\Core\Listing_Type_Registry::instance();
		$cards    = array();

		foreach ( $listing_ids as $listing_id ) {
			$index = $index_by_id[ $listing_id ] ?? null;
			if ( null === $index ) {
				continue;
			}

			$type        = (string) $index['listing_type'];
			$type_object = $type ? $registry->get( $type ) : null;
			$thumb_id    = $thumb_by_id[ $listing_id ] ?? 0;

			/*
			 * Image sizes match `/search`'s `get_image_data()` key-for-key —
			 * `full`, `medium`, `thumbnail`, `alt` — so a client reading a card
			 * from `/search`, `/favorites` or `/listings/{id}/related` gets one
			 * contract rather than three. They had diverged: this helper
			 * emitted no `thumbnail` at all, and its `medium` was actually
			 * `medium_large`, so the same key meant a 300px image on one
			 * endpoint and a 768px image on another (BC 10194450677).
			 *
			 * `medium_large` is kept as an ADDITIONAL key rather than being
			 * smuggled in as `medium`, because the web card template renders
			 * from it — collapsing it onto the 300px `medium` would quietly
			 * halve card image quality on every grid. Extra keys are safe:
			 * clients ignore what they do not know.
			 */
			$cards[ $listing_id ] = array(
				'id'                => $listing_id,
				// Decoded, not raw: the app was shipping a workaround for
				// "Statue of Liberty &#038; Ellis Island" reaching it encoded.
				// Goes through the shared helper now so every REST string
				// follows one rule instead of this one field being special.
				'title'             => wb_listora_decode_text( get_the_title( $listing_id ) ),
				'link'              => get_permalink( $listing_id ),
				// One canonical shape across endpoints — see Image_Schema. This
				// builder omitted `large`, which the detail builder returned.
				'featured_image'    => \WBListora\Core\Image_Schema::for_attachment( $thumb_id ),
				'rating'            => array(
					'average' => round( (float) $index['avg_rating'], 1 ),
					'count'   => (int) $index['review_count'],
				),
				'listing_type'      => $type,
				'listing_type_name' => $type_object ? $type_object->get_name() : '',
				'is_featured'       => (bool) $index['is_featured'],
				'location'          => trim( implode( ', ', array_filter( array( $index['city'], $index['country'] ) ) ) ),
			);
		}

		return $cards;
	}
}

if ( ! function_exists( 'wb_listora_get_map_tiles' ) ) {

	/**
	 * Resolve the raster tile source for a map provider.
	 *
	 * Single source of truth for the tile URL + attribution, shared by the
	 * listing-map block and the public `/settings/maps` REST payload. Native
	 * clients cannot hardcode OSM's tiles (that violates the OSM tile-usage
	 * policy), so the server has to name the source — without it a native map
	 * on a provider with no bundled SDK key renders no tile layer at all.
	 *
	 * Returns empty strings for `google`, where the client uses the Google SDK
	 * rather than a raster overlay.
	 *
	 * @since 1.4.2
	 *
	 * @param string $provider Map provider. Defaults to the configured setting.
	 * @return array{url:string,attribution:string}
	 */
	function wb_listora_get_map_tiles( $provider = '' ) {
		if ( ! $provider ) {
			$provider = (string) wb_listora_get_setting( 'map_provider', 'osm' );
		}

		$tiles = array(
			'url'         => '',
			'attribution' => '',
		);

		/*
		 * No default tile server. This used to hand every non-Google site
		 * OpenStreetMap's PUBLIC tiles, which their usage policy does not
		 * permit for a product shipping to unknown volumes of installs — and
		 * it did so silently, so an owner had no idea their directory was
		 * leaning on someone else's infrastructure (BC 10202831116).
		 *
		 * A site now supplies its own tile URL in Settings -> Map. Empty is a
		 * deliberate, honest answer: the mobile app already renders no raster
		 * layer when this is blank, and the web map falls back to the same.
		 * Shipping a working-by-default map that breaches a third party's
		 * terms is not a better outcome than shipping one an owner configures.
		 */
		if ( 'google' !== $provider ) {
			$configured = trim( (string) wb_listora_get_setting( 'map_tile_url', '' ) );

			if ( '' !== $configured ) {
				$tiles = array(
					'url'         => $configured,
					'attribution' => (string) wb_listora_get_setting( 'map_tile_attribution', '' ),
				);
			}
		}

		/**
		 * Filter the resolved raster tile source.
		 *
		 * Lets a site point every surface — web and native — at a self-hosted or
		 * commercial tile server in one place.
		 *
		 * @since 1.4.2
		 *
		 * @param array  $tiles    url + attribution.
		 * @param string $provider Provider being resolved.
		 */
		$filtered = apply_filters( 'wb_listora_map_tiles', $tiles, $provider );

		// Re-assert the shape. A filter returning a scalar or a partial array
		// would otherwise reach the block render and the public REST payload,
		// where both keys are read unguarded.
		if ( ! is_array( $filtered ) ) {
			return $tiles;
		}

		return array(
			'url'         => isset( $filtered['url'] ) && is_scalar( $filtered['url'] )
				? (string) $filtered['url']
				: $tiles['url'],
			'attribution' => isset( $filtered['attribution'] ) && is_scalar( $filtered['attribution'] )
				? (string) $filtered['attribution']
				: $tiles['attribution'],
		);
	}
}

if ( ! function_exists( 'wb_listora_get_currency_format' ) ) {

	/**
	 * Resolve the display format for a currency code.
	 *
	 * Single source of truth for the symbol / position / decimal precision of a
	 * currency. `wb_listora_format_currency()` renders web output from this, and
	 * the app-config REST payload publishes the same values so native clients
	 * format prices identically instead of falling back to `Intl.NumberFormat`,
	 * which renders the bare ISO code ("US$35.00" rather than "$35.00").
	 *
	 * @since 1.4.2
	 *
	 * @param string $currency Currency code. Defaults to the configured setting.
	 * @return array{code:string,symbol:string,position:string,decimals:int}
	 */
	function wb_listora_get_currency_format( $currency = '' ) {
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

		// Zero-decimal currencies (ISO 4217). Everything else uses 2.
		$zero_decimal = array( 'JPY' );

		$format = array(
			'code'     => (string) $currency,
			'symbol'   => $symbols[ $currency ] ?? $currency . ' ',
			'position' => 'before',
			'decimals' => in_array( $currency, $zero_decimal, true ) ? 0 : 2,
		);

		/**
		 * Filter the resolved currency display format.
		 *
		 * Lets a site serve a suffix-position currency, a custom symbol, or a
		 * different precision without overriding every render site.
		 *
		 * @since 1.4.2
		 *
		 * @param array  $format   code / symbol / position ('before'|'after') / decimals.
		 * @param string $currency Currency code being resolved.
		 */
		$filtered = apply_filters( 'wb_listora_currency_format', $format, $currency );

		// Re-assert the shape — every caller reads all four keys unguarded, and
		// `decimals` reaches number_format() where a non-int would warn.
		if ( ! is_array( $filtered ) ) {
			return $format;
		}

		return array(
			'code'     => isset( $filtered['code'] ) && is_scalar( $filtered['code'] )
				? (string) $filtered['code']
				: $format['code'],
			'symbol'   => isset( $filtered['symbol'] ) && is_scalar( $filtered['symbol'] )
				? (string) $filtered['symbol']
				: $format['symbol'],
			'position' => isset( $filtered['position'] ) && in_array( $filtered['position'], array( 'before', 'after' ), true )
				? $filtered['position']
				: $format['position'],
			'decimals' => isset( $filtered['decimals'] ) && is_numeric( $filtered['decimals'] )
				? (int) $filtered['decimals']
				: $format['decimals'],
		);
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
		$format = wb_listora_get_currency_format( $currency );
		$symbol = $format['symbol'];

		/**
		 * Whether to abbreviate large amounts as K / M at all.
		 *
		 * Abbreviation trades precision for width, which is the wrong trade in
		 * a directory where the exact figure is the point — real estate, plant
		 * hire, anything where two listings differ by less than the rounding
		 * step. Return false to always print the full amount.
		 *
		 * @since 1.5.0
		 *
		 * @param bool   $abbreviate Whether to abbreviate.
		 * @param float  $amount     The amount being formatted.
		 * @param string $currency   Currency code.
		 */
		$abbreviate = (bool) apply_filters( 'wb_listora_abbreviate_price', true, (float) $amount, (string) $currency );

		/*
		 * Truncate, never round.
		 *
		 * This used to round to the nearest whole K, which broke in three ways
		 * at once. It OVERSTATED — 1,500 rendered "2K", 1,999,999 rendered
		 * "2.0M" — and an overstated price loses the click before anyone opens
		 * the listing. It COLLAPSED the 1,000-1,499 range to a single "1K", so
		 * two listings 500 apart looked identical while 1,499 and 1,500 (one
		 * apart) looked a thousand apart. And it produced "$1,000K" at 999,500
		 * upward, which is simply not a price.
		 *
		 * Truncating to one decimal fixes all three: the figure shown is never
		 * more than the real price, the step is 100 rather than 1,000, and
		 * 999,999 lands at "999.9K" instead of spilling into a fake "1,000K".
		 *
		 * The tenths are taken with integer division rather than
		 * floor( $x * 10 ) / 10, because the latter turns 1.4 into 1.3999… on
		 * binary floats and silently loses a step.
		 */
		if ( $abbreviate && $amount >= 1000000 ) {
			$tenths   = (int) floor( (float) $amount / 100000 );
			$rendered = number_format_i18n( $tenths / 10, ( 0 === $tenths % 10 ) ? 0 : 1 ) . 'M';
		} elseif ( $abbreviate && $amount >= 1000 ) {
			$tenths   = (int) floor( (float) $amount / 100 );
			$rendered = number_format_i18n( $tenths / 10, ( 0 === $tenths % 10 ) ? 0 : 1 ) . 'K';
		} else {
			// Whole amounts render without decimals ("$35", not "$35.00"); the
			// `decimals` value published to clients is the currency's maximum.
			$precision = ( (float) $amount === floor( (float) $amount ) ) ? 0 : $format['decimals'];
			$rendered  = number_format_i18n( $amount, $precision );
		}

		return 'after' === $format['position'] ? $rendered . $symbol : $symbol . $rendered;
	}
}

if ( ! function_exists( 'wb_listora_validate_business_hours' ) ) {

	/**
	 * Validate a set of opening ranges before they are saved.
	 *
	 * Returns WP_Error rather than quietly repairing the input. Merging an
	 * overlap — turning 09:00-13:00 plus 12:00-17:00 into 09:00-17:00 — changes
	 * what the owner typed without telling them, and they find out from a
	 * customer who turned up at noon. Refusing with a reason is the honest
	 * behaviour, and it is the same principle as rejecting an out-of-range
	 * per_page instead of clamping it.
	 *
	 * Overnight ranges are legitimate and are NOT treated as overlaps: a bar
	 * open 18:00-02:00 has close < open, which the open-now query already
	 * understands. Only ranges that sit inside the same day are compared.
	 *
	 * @since 1.5.0
	 *
	 * @param array<int, array<string, mixed>> $hours Entries: { day, open, close, closed, is_24h }.
	 * @return true|WP_Error True when the set is storable.
	 */
	function wb_listora_validate_business_hours( $hours ) {
		if ( ! is_array( $hours ) ) {
			return new WP_Error( 'listora_hours_shape', __( 'Opening hours must be a list.', 'wb-listora' ), array( 'status' => 400 ) );
		}

		$max      = \WBListora\Search\Search_Indexer::max_hours_slots();
		$by_day   = array();
		$day_name = array( __( 'Sunday', 'wb-listora' ), __( 'Monday', 'wb-listora' ), __( 'Tuesday', 'wb-listora' ), __( 'Wednesday', 'wb-listora' ), __( 'Thursday', 'wb-listora' ), __( 'Friday', 'wb-listora' ), __( 'Saturday', 'wb-listora' ) );

		foreach ( $hours as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['day'] ) ) {
				continue;
			}

			// A closed or 24h day has no range to compare.
			if ( ! empty( $entry['closed'] ) || ! empty( $entry['is_24h'] ) ) {
				continue;
			}

			$by_day[ (int) $entry['day'] ][] = $entry;
		}

		foreach ( $by_day as $day => $entries ) {
			$label = $day_name[ $day ] ?? (string) $day;

			if ( count( $entries ) > $max ) {
				return new WP_Error(
					'listora_hours_too_many',
					sprintf(
						/* translators: 1: day name, 2: maximum number of ranges. */
						__( '%1$s has too many opening times. You can add up to %2$d per day.', 'wb-listora' ),
						$label,
						$max
					),
					array( 'status' => 400 )
				);
			}

			$ranges = array();

			foreach ( $entries as $entry ) {
				$open  = wb_listora_hours_to_minutes( $entry['open'] ?? '' );
				$close = wb_listora_hours_to_minutes( $entry['close'] ?? '' );

				if ( null === $open || null === $close ) {
					return new WP_Error(
						'listora_hours_invalid_time',
						sprintf(
							/* translators: %s: day name. */
							__( '%s has an opening time that is not a valid time.', 'wb-listora' ),
							$label
						),
						array( 'status' => 400 )
					);
				}

				// Overnight spans are legitimate; skip them in the overlap test
				// rather than calling them invalid.
				if ( $close <= $open ) {
					continue;
				}

				foreach ( $ranges as $existing ) {
					if ( $open < $existing[1] && $close > $existing[0] ) {
						return new WP_Error(
							'listora_hours_overlap',
							sprintf(
								/* translators: %s: day name. */
								__( '%s has two opening times that overlap. Adjust them so they do not run into each other.', 'wb-listora' ),
								$label
							),
							array( 'status' => 400 )
						);
					}
				}

				$ranges[] = array( $open, $close );
			}
		}

		return true;
	}
}

if ( ! function_exists( 'wb_listora_hours_to_minutes' ) ) {

	/**
	 * Parse an `HH:MM` (or `HH:MM:SS`) time into minutes past midnight.
	 *
	 * @since 1.5.0
	 *
	 * @param mixed $time Raw time value.
	 * @return int|null Minutes, or null when unparseable.
	 */
	function wb_listora_hours_to_minutes( $time ) {
		if ( ! is_string( $time ) || ! preg_match( '/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim( $time ), $m ) ) {
			return null;
		}

		$hours   = (int) $m[1];
		$minutes = (int) $m[2];

		if ( $hours > 23 || $minutes > 59 ) {
			return null;
		}

		return ( $hours * 60 ) + $minutes;
	}
}

if ( ! function_exists( 'wb_listora_normalize_hours' ) ) {

	/**
	 * Flatten a `business_hours` meta value into one list of day-tagged ranges.
	 *
	 * Three shapes exist in stored data — the canonical list, the day-keyed dict
	 * the submission form used to post, and the `ranges` shape it posts now that
	 * a day can hold a split shift. Every reader has to interpret all three the
	 * same way: when the indexer and the detail template disagreed, a listing
	 * indexed one set of hours and displayed another, which is invisible until a
	 * customer turns up to a closed shop.
	 *
	 * This is the single interpretation, wrapped as a global so theme template
	 * overrides and Pro can call it without naming an internal class (INV-3).
	 *
	 * @since 1.5.0
	 *
	 * @param mixed $hours Raw `_listora_business_hours` meta value.
	 * @return array<int, array<string, mixed>> Entries each carrying an integer `day`.
	 */
	function wb_listora_normalize_hours( $hours ) {
		if ( empty( $hours ) || ! is_array( $hours ) ) {
			return array();
		}

		// No method_exists guard — the indexer ships in this plugin and is
		// always loaded; a defensive check here would only be dead code.
		return \WBListora\Search\Search_Indexer::normalise_hours_meta( $hours );
	}
}

if ( ! function_exists( 'wb_listora_max_hours_slots' ) ) {

	/**
	 * How many time ranges one day may hold.
	 *
	 * The submission builder, the indexer and any importer must agree on this
	 * number, or a producer writes ranges the index then drops without saying
	 * so. Exposed as a global because Pro's importers need it and must not name
	 * a Free internal class (INV-3).
	 *
	 * Filterable via `wb_listora_max_hours_slots`.
	 *
	 * @since 1.5.0
	 *
	 * @return int At least 1.
	 */
	function wb_listora_max_hours_slots() {
		return \WBListora\Search\Search_Indexer::max_hours_slots();
	}
}

if ( ! function_exists( 'wb_listora_hidden_review_authors' ) ) {

	/**
	 * User IDs whose content should be hidden from the current viewer.
	 *
	 * The single source both review read paths use — the server-rendered blocks
	 * via Listing_Data::get_reviews(), and the REST list the mobile app reads.
	 * They are separate queries against the same table, so without one helper
	 * they drift: the first version of this filtered only the blocks, and the
	 * app kept showing blocked members' reviews.
	 *
	 * Empty for anonymous visitors and for members with no blocks, so the
	 * common case adds nothing to either query.
	 *
	 * @since 1.5.0
	 *
	 * @param int $viewer Viewer. Defaults to the current user.
	 * @return int[]
	 */
	function wb_listora_hidden_review_authors( $viewer = 0 ) {
		if ( ! class_exists( '\WBListora\Core\Member_Blocks' ) ) {
			return array();
		}

		return \WBListora\Core\Member_Blocks::hidden_from( (int) $viewer );
	}
}

if ( ! function_exists( 'wb_listora_format_address_line' ) ) {
	/**
	 * Build a one-line address without repeating what it already contains.
	 *
	 * The `address` component is not always a bare street. Google Places and
	 * the submission autocomplete both store a FULL formatted line there
	 * ("247 West Broadway, Manhattan, NY 10013") while ALSO filling `city` and
	 * `state`. Concatenating all three unconditionally therefore produced
	 * "247 West Broadway, Manhattan, NY 10013, Manhattan, NY" on every listing
	 * created that way.
	 *
	 * A component is appended only when it does not already appear in the
	 * street line, matched on word boundaries so a state of "NY" is not
	 * considered present in "Nyack Road".
	 *
	 * @since 1.5.0
	 *
	 * @param array<string, mixed> $address Address meta.
	 * @return string Comma-joined line, or '' when there is nothing to show.
	 */
	function wb_listora_format_address_line( $address ) {
		if ( ! is_array( $address ) ) {
			return '';
		}

		$street = trim( (string) ( $address['address'] ?? '' ) );
		$parts  = '' !== $street ? array( $street ) : array();

		foreach ( array( 'city', 'state' ) as $component ) {
			$value = trim( (string) ( $address[ $component ] ?? '' ) );

			if ( '' === $value ) {
				continue;
			}

			if ( '' !== $street && preg_match( '/\b' . preg_quote( $value, '/' ) . '\b/iu', $street ) ) {
				continue;
			}

			$parts[] = $value;
		}

		return implode( ', ', $parts );
	}
}

if ( ! function_exists( 'wb_listora_review_author_name' ) ) {

	/**
	 * Display name for a review's author, including when the user is gone.
	 *
	 * Two different situations produced the same word before, and only one of
	 * them deserved it (BC 10185681930):
	 *
	 *   user_id = 0            the row was anonymised by the privacy eraser.
	 *                          "Anonymous" is correct and intended — the erasure
	 *                          map keeps the row so the listing's rating stays
	 *                          in its aggregate, with identity stripped.
	 *   user_id > 0, no user   the account was deleted outside the eraser, or
	 *                          the row is an import/demo leftover. Labelling
	 *                          that "Anonymous" reads as broken or spammy and
	 *                          tells the owner nothing.
	 *
	 * Deliberately NOT solved by snapshotting the display name onto the review
	 * row at submit time, which is the first fix the card proposes: this
	 * plugin's erasure strategy for `reviews` is `anonymize`, so persisting a
	 * copy of the member's name would survive account deletion and contradict
	 * the documented GDPR behaviour.
	 *
	 * Both read paths — the REST list the app uses and the server-rendered
	 * block — call this, so they cannot drift the way the block filter and the
	 * REST list did before BC 10185680640.
	 *
	 * @since 1.5.0
	 *
	 * @param int $user_id Stored review author ID.
	 * @return string Display name, or the appropriate stand-in.
	 */
	function wb_listora_review_author_name( $user_id ) {
		$user_id = (int) $user_id;
		// get_userdata() returns false, not null, for a missing account.
		// Normalised here so the filter below promises exactly one "no user"
		// value instead of making every listener handle both.
		$user = $user_id > 0 ? get_userdata( $user_id ) : false;
		$user = $user instanceof WP_User ? $user : null;

		if ( $user ) {
			$name = (string) $user->display_name;
		} elseif ( $user_id > 0 ) {
			$name = __( 'Former member', 'wb-listora' );
		} else {
			$name = __( 'Anonymous', 'wb-listora' );
		}

		/**
		 * Filter the display name shown for a review author.
		 *
		 * @since 1.5.0
		 *
		 * @param string        $name    Resolved name.
		 * @param int           $user_id Stored review author ID (0 when anonymised).
		 * @param \WP_User|null $user    User object, or null when the account is gone.
		 */
		return (string) apply_filters( 'wb_listora_review_author_name', $name, $user_id, $user );
	}
}

if ( ! function_exists( 'wb_listora_can_members_contact' ) ) {

	/**
	 * Whether two members may contact each other.
	 *
	 * The public surface for member blocking. Pro and third parties call THIS,
	 * never \WBListora\Core\Member_Blocks directly — INV-3 forbids reaching
	 * into Free's concrete classes, and a plain boolean helper is a lighter
	 * contract than a service for a question this small. Mirrors the existing
	 * wb_listora_is_account_deactivated() precedent.
	 *
	 * Symmetric: false whichever of the two did the blocking.
	 *
	 * @since 1.5.0
	 *
	 * @param int $a One member.
	 * @param int $b The other member.
	 * @return bool True when contact is allowed.
	 */
	function wb_listora_can_members_contact( $a, $b ) {
		if ( ! class_exists( '\WBListora\Core\Member_Blocks' ) ) {
			return true;
		}

		return \WBListora\Core\Member_Blocks::can_contact( (int) $a, (int) $b );
	}
}

if ( ! function_exists( 'wb_listora_members_blocked' ) ) {

	/**
	 * Whether two members are blocked from each other, in either direction.
	 *
	 * @since 1.5.0
	 *
	 * @param int $a One member.
	 * @param int $b The other member.
	 * @return bool
	 */
	function wb_listora_members_blocked( $a, $b ) {
		if ( ! class_exists( '\WBListora\Core\Member_Blocks' ) ) {
			return false;
		}

		return \WBListora\Core\Member_Blocks::is_blocked_pair( (int) $a, (int) $b );
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

if ( ! function_exists( 'wb_listora_get_review_criteria' ) ) {

	/**
	 * Resolve the multi-criteria review criteria for a listing type.
	 *
	 * Single entry point for every consumer of `wb_listora_review_criteria`.
	 * Three call sites previously duplicated the same
	 * `array_values( array_filter( (array) apply_filters( … ), 'is_array' ) )`
	 * incantation, and all three passed an EMPTY array as the filter base — so
	 * the `review_criteria` a site owner had saved against the listing type was
	 * never consulted by anything.
	 *
	 * That made configuring criteria a false positive: the REST write returned
	 * 200, the term meta genuinely persisted, and the front end kept rendering
	 * Pro's hardcoded defaults (BC 10199712310).
	 *
	 * The stored value is now the filter's base, so a listener that simply
	 * returns what it was given honours the site's configuration, and one that
	 * wants to override still can.
	 *
	 * @since 1.6.0
	 *
	 * @param string $type_slug Listing type slug.
	 * @return array<int, array<string, mixed>> Criteria rows, possibly empty. Each row
	 *                                          is expected to carry `key` and `label`,
	 *                                          but the filter is public and a listener
	 *                                          can return anything array-shaped — every
	 *                                          consumer reads through `?? ''` for that
	 *                                          reason, and the guarantee here is only
	 *                                          that each row IS an array.
	 */
	function wb_listora_get_review_criteria( $type_slug ) {
		$type_slug = (string) $type_slug;
		$stored    = array();

		if ( $type_slug && class_exists( '\WBListora\Core\Listing_Type_Registry' ) ) {
			$type = \WBListora\Core\Listing_Type_Registry::instance()->get( $type_slug );

			if ( $type && method_exists( $type, 'get_prop' ) ) {
				$stored = $type->get_prop( 'review_criteria' );
			}
		}

		// Item-level shape guard on the STORED value as well as the filtered
		// one: `_listora_review_criteria` is writable over REST, and a scalar
		// row would fatal the `{key,label}` offset reads in the templates.
		$stored = is_array( $stored )
			? array_values( array_filter( $stored, 'is_array' ) )
			: array();

		/**
		 * Filters the review criteria for a listing type.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $criteria  Criteria saved against the listing type. Empty
		 *                          when the owner has configured none.
		 * @param string $type_slug Listing type slug.
		 */
		$criteria = apply_filters( 'wb_listora_review_criteria', $stored, $type_slug );

		return array_values( array_filter( (array) $criteria, 'is_array' ) );
	}
}

if ( ! function_exists( 'wb_listora_decode_text' ) ) {
	/**
	 * Decode a human-facing string for output over REST.
	 *
	 * ONE rule for the whole API: every human-facing string leaves decoded.
	 *
	 * Before 1.6.0 "decoded" was a property of which line of PHP happened to
	 * build the field rather than a contract (BC 10202832578). The same row
	 * could answer twice: a listing's `title` came back as
	 * "Central Park — The Mall & Bethesda Terrace" while the very same string
	 * in `featured_image.alt` came back as "… The Mall &#038; Bethesda …".
	 * Clients could not know whether a given value was safe to render, so the
	 * mobile app carried a defensive decode at every api/ boundary.
	 *
	 * Term names have the same problem from a different direction
	 * (BC 10195032749): wp_insert_term() runs names through KSES, so
	 * "Fitness Centers & Gyms" is stored in wp_terms ALREADY encoded. Any
	 * consumer assigning it with textContent renders the raw "&amp;".
	 *
	 * Decode-on-output is the right end for this: these values are consumed by
	 * native clients that render plain text and have no HTML parser. Callers
	 * that emit into HTML still escape at the point of output as usual — this
	 * function is for API payloads, not for templates.
	 *
	 * @since 1.6.0
	 *
	 * @param mixed $text Value to decode; non-strings pass through as ''.
	 * @return string Decoded text.
	 */
	function wb_listora_decode_text( $text ) {
		if ( ! is_string( $text ) ) {
			return '';
		}

		return html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}
}

if ( ! function_exists( 'wb_listora_render_icon' ) ) {

	/**
	 * Render a Lucide icon as inline SVG.
	 *
	 * The public surface for icon rendering, so Pro (and any add-on) can draw
	 * the same icons Free draws without referencing `\WBListora\Core\Lucide_Icons`
	 * directly — INV-3 forbids that coupling, and it was the reason Pro reached
	 * for the client-side `<i data-lucide>` pattern instead. That pattern
	 * silently fails on the frontend: `lucide.min.js` is enqueued only in
	 * wp-admin, so `window.lucide` is undefined there and the `<i>` stays empty
	 * (BC 10199529746).
	 *
	 * Returns an empty string for an unknown name rather than a placeholder —
	 * a missing icon should cost nothing and never draw attention to itself.
	 * **G10** in `bin/audit-guardrails.sh` fails the build when a picker offers
	 * a name this map does not carry, which is what stops "unknown" happening
	 * by accident.
	 *
	 * @since 1.6.0
	 *
	 * @param string $name Lucide icon name, kebab-case (e.g. `map-pin`).
	 * @param int    $size Width and height in pixels. Default 24.
	 * @return string Inline SVG markup, or '' when the icon is unknown.
	 */
	function wb_listora_render_icon( $name, $size = 24 ) {
		if ( ! class_exists( '\WBListora\Core\Lucide_Icons' ) ) {
			return '';
		}

		return \WBListora\Core\Lucide_Icons::render( (string) $name, (int) $size );
	}
}

if ( ! function_exists( 'wb_listora_get_icon_choices' ) ) {

	/**
	 * Every icon name the renderer can draw.
	 *
	 * The single source of truth for icon pickers. Before 1.6.0 the Type
	 * Editor offered its own hardcoded 30 and the taxonomy picker offered the
	 * entire Lucide set (1,700+), while the PHP renderer knew 42 — so most
	 * pickable icons rendered as nothing at all on the frontend, with no error
	 * and no fallback (BC 10194825231, BC 10198996635).
	 *
	 * A picker built from this list cannot offer an icon that will not draw.
	 *
	 * @since 1.6.0
	 *
	 * @return string[] Icon names, alphabetical.
	 */
	function wb_listora_get_icon_choices() {
		if ( ! class_exists( '\WBListora\Core\Lucide_Icons' ) ) {
			return array();
		}

		return \WBListora\Core\Lucide_Icons::get_names();
	}
}

if ( ! function_exists( 'wb_listora_automation_payload_listing' ) ) {

	/**
	 * Canonical listing payload for an automation trigger.
	 *
	 * The documented surface for Pro and add-ons. INV-3 forbids naming
	 * \WBListora\Automation\Payload directly, and Pro's own private builder
	 * is exactly what this replaces.
	 *
	 * @since 1.7.0
	 *
	 * @param int $listing_id Listing post ID.
	 * @return array<string, mixed>|null Null when the listing does not exist.
	 */
	function wb_listora_automation_payload_listing( $listing_id ) {
		return \WBListora\Automation\Payload::listing( $listing_id );
	}
}

if ( ! function_exists( 'wb_listora_automation_payload_review' ) ) {

	/**
	 * Canonical review payload for an automation trigger.
	 *
	 * @since 1.7.0
	 *
	 * @param int $review_id Review ID.
	 * @return array<string, mixed>|null Null when the review does not exist.
	 */
	function wb_listora_automation_payload_review( $review_id ) {
		return \WBListora\Automation\Payload::review( $review_id );
	}
}

if ( ! function_exists( 'wb_listora_automation_payload_claim' ) ) {

	/**
	 * Canonical claim payload for an automation trigger.
	 *
	 * @since 1.7.0
	 *
	 * @param int $claim_id Claim ID.
	 * @return array<string, mixed>|null Null when the claim does not exist.
	 */
	function wb_listora_automation_payload_claim( $claim_id ) {
		return \WBListora\Automation\Payload::claim( $claim_id );
	}
}

if ( ! function_exists( 'wb_listora_automation_payload_user' ) ) {

	/**
	 * Canonical user payload for an automation trigger.
	 *
	 * Allow-listed fields only — see Payload::user().
	 *
	 * @since 1.7.0
	 *
	 * @param int $user_id User ID.
	 * @return array<string, mixed>|null Null when the user does not exist.
	 */
	function wb_listora_automation_payload_user( $user_id ) {
		return \WBListora\Automation\Payload::user( $user_id );
	}
}
