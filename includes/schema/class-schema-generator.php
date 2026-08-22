<?php
/**
 * Schema.org Generator — factory that returns the correct schema for each listing type.
 *
 * Also handles breadcrumbs, Open Graph, Twitter Cards, and canonical URLs.
 *
 * @package WBListora\Schema
 */

namespace WBListora\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Generates JSON-LD structured data, OG tags, and SEO meta.
 */
class Schema_Generator {

	/**
	 * Generate schema data for a listing.
	 *
	 * @param int $post_id Post ID.
	 * @return self|null
	 */
	public static function for_listing( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'listora_listing' !== $post->post_type ) {
			return null;
		}

		$registry = \WBListora\Core\Listing_Type_Registry::instance();
		$type     = $registry->get_for_post( $post_id );

		if ( ! $type ) {
			return null;
		}

		$instance              = new self();
		$instance->post_id     = $post_id;
		$instance->post        = $post;
		$instance->type        = $type;
		$instance->schema_type = $type->get_schema_type();
		$instance->meta        = self::normalize_meta_for_schema( \WBListora\Core\Meta_Handler::get_all_values( $post_id ) );

		return $instance;
	}

	/**
	 * Defensive coercion for meta keys the schema generator treats as arrays.
	 *
	 * Every meta access in get_data() / add_type_properties() is already
	 * guarded with `is_array()`, but a string value reaching a `$arr[] = $x`
	 * appender (via filter callbacks or 3rd-party meta corruption) would
	 * fatal with "[] operator not supported for strings" — the symptom
	 * reported in BC 9905075024. Normalizing here means no caller downstream
	 * needs to re-prove the type contract. If a value was saved malformed
	 * (e.g. JSON string instead of array), we attempt a json_decode and fall
	 * back to an empty array — schema output skips the section rather than
	 * fataling.
	 *
	 * @param array<string,mixed> $meta Raw meta values keyed by field slug.
	 * @return array<string,mixed> Normalized meta with the array-typed fields guaranteed array.
	 */
	private static function normalize_meta_for_schema( $meta ) {
		if ( ! is_array( $meta ) ) {
			return array();
		}
		$array_keys = array( 'address', 'social_links', 'business_hours', 'gallery', 'features', 'price', 'map_location' );
		foreach ( $array_keys as $k ) {
			if ( ! isset( $meta[ $k ] ) ) {
				continue;
			}
			if ( is_array( $meta[ $k ] ) ) {
				continue;
			}
			if ( is_string( $meta[ $k ] ) && '' !== $meta[ $k ] ) {
				$decoded    = json_decode( $meta[ $k ], true );
				$meta[ $k ] = is_array( $decoded ) ? $decoded : array();
				continue;
			}
			$meta[ $k ] = array();
		}
		return $meta;
	}

	/** @var int */
	private $post_id;

	/** @var \WP_Post */
	private $post;

	/** @var \WBListora\Core\Listing_Type */
	private $type;

	/** @var string */
	private $schema_type;

	/** @var array */
	private $meta;

	/**
	 * Get the complete JSON-LD data array.
	 *
	 * @return array
	 */
	public function get_data() {
		$data = array(
			'@context' => 'https://schema.org',
			'@type'    => $this->schema_type,
			'name'     => $this->post->post_title,
			'url'      => get_permalink( $this->post_id ),
		);

		// Description — built from this listing's own content. We deliberately
		// avoid get_the_excerpt(): with no manual excerpt it calls
		// wp_trim_excerpt(), which reads the global $post (the adjacent
		// listing in the REST query loop, since setup_postdata() never ran)
		// to append a "Continue reading &lt;other listing&gt;" link — leaking
		// a different listing's name into this one's schema.description.
		$excerpt = has_excerpt( $this->post )
			? $this->post->post_excerpt
			: wp_trim_words( wp_strip_all_tags( strip_shortcodes( $this->post->post_content ) ), 55, '' );
		if ( $excerpt ) {
			$data['description'] = wp_strip_all_tags( $excerpt );
		}

		// Image.
		$thumb_url = get_the_post_thumbnail_url( $this->post_id, 'large' );
		if ( $thumb_url ) {
			$data['image'] = $thumb_url;
		}

		// Address.
		$address = $this->meta['address'] ?? array();
		if ( is_array( $address ) && ! empty( $address['address'] ) ) {
			$data['address'] = array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $address['address'] ?? '',
				'addressLocality' => $address['city'] ?? '',
				'addressRegion'   => $address['state'] ?? '',
				'postalCode'      => $address['postal_code'] ?? '',
				'addressCountry'  => $address['country'] ?? '',
			);

			if ( ! empty( $address['lat'] ) ) {
				$data['geo'] = array(
					'@type'     => 'GeoCoordinates',
					'latitude'  => (float) $address['lat'],
					'longitude' => (float) $address['lng'],
				);
			}
		}

		// Phone.
		$phone = $this->meta['phone'] ?? '';
		if ( $phone ) {
			$data['telephone'] = $phone;
		}

		// Website.
		$website = $this->meta['website'] ?? '';
		if ( $website ) {
			$data['url']    = $website;
			$data['sameAs'] = array( get_permalink( $this->post_id ) );
		}

		// Rating.
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$rating = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT avg_rating, review_count FROM {$prefix}search_index WHERE listing_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->post_id
			),
			ARRAY_A
		);

		// Only emit AggregateRating when the Reviews feature is on. Disabling
		// Reviews must hide every read surface, including structured data
		// (card 9895809632) — same feature gate as opengraph/breadcrumbs below.
		$reviews_enabled = ! function_exists( 'wb_listora_feature_enabled' ) || wb_listora_feature_enabled( 'reviews' );
		if ( $reviews_enabled && $rating && (float) $rating['avg_rating'] > 0 ) {
			$data['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => number_format( (float) $rating['avg_rating'], 1 ),
				'bestRating'  => '5',
				'worstRating' => '1',
				'ratingCount' => (int) $rating['review_count'],
			);
		}

		// Type-specific properties.
		$data = $this->add_type_properties( $data );

		// Social links — flat associative array {platform_slug => url} per
		// WBListora\Core\Field::social_link_platforms(). Emitted as schema.org
		// sameAs. Sanitizer guarantees string values only.
		$social = $this->meta['social_links'] ?? array();
		if ( is_array( $social ) && ! empty( $social ) ) {
			$same_as = $data['sameAs'] ?? array();
			foreach ( $social as $url ) {
				if ( is_string( $url ) && '' !== $url ) {
					$same_as[] = $url;
				}
			}
			if ( ! empty( $same_as ) ) {
				$data['sameAs'] = $same_as;
			}
		}

		/**
		 * Filter schema data for a listing.
		 *
		 * @param array $data    Schema data.
		 * @param int   $post_id Post ID.
		 */
		return apply_filters( 'wb_listora_schema_data', $data, $this->post_id );
	}

	/**
	 * Add type-specific Schema.org properties.
	 *
	 * @param array $data Base schema data.
	 * @return array
	 */
	private function add_type_properties( $data ) {
		$fields = $this->type->get_all_fields();

		foreach ( $fields as $field ) {
			$schema_prop = $field->get( 'schema_prop' );
			if ( ! $schema_prop ) {
				continue;
			}

			$key   = $field->get_key();
			$value = $this->meta[ $key ] ?? '';

			if ( '' === $value || ( is_array( $value ) && empty( $value ) ) ) {
				continue;
			}

			// Handle nested properties (e.g., "hiringOrganization.name").
			if ( false !== strpos( $schema_prop, '.' ) ) {
				$parts  = explode( '.', $schema_prop, 2 );
				$parent = $parts[0];
				$child  = $parts[1];

				if ( ! isset( $data[ $parent ] ) ) {
					$data[ $parent ] = array();
				}
				if ( is_array( $data[ $parent ] ) ) {
					$data[ $parent ][ $child ] = $this->format_schema_value( $field, $value );
				}
				continue;
			}

			// Skip properties already set (address, telephone, url).
			if ( isset( $data[ $schema_prop ] ) ) {
				continue;
			}

			$data[ $schema_prop ] = $this->format_schema_value( $field, $value );
		}

		// Add @type for nested objects.
		$nested_types = array(
			'hiringOrganization' => 'Organization',
			'location'           => 'Place',
			'offers'             => 'Offer',
			'provider'           => 'Organization',
			'seller'             => 'Person',
			'baseSalary'         => 'MonetaryAmount',
		);

		foreach ( $nested_types as $prop => $schema_type ) {
			if ( isset( $data[ $prop ] ) && is_array( $data[ $prop ] ) && ! isset( $data[ $prop ]['@type'] ) ) {
				$data[ $prop ]['@type'] = $schema_type;
			}
		}

		/*
		 * Business hours, through the one shape interpretation.
		 *
		 * format_hours_schema() skips any entry without a `day` key, and the
		 * day-keyed dict the submission form posts has none — so every listing
		 * whose hours were entered by a member published ZERO
		 * openingHoursSpecification. Silent: the hours displayed correctly on
		 * the page, and the only symptom was Google never showing opening
		 * hours for those listings.
		 */
		$hours = wb_listora_normalize_hours( $this->meta['business_hours'] ?? array() );
		if ( ! empty( $hours ) ) {
			$data['openingHoursSpecification'] = $this->format_hours_schema( $hours );
		}

		// Services — OfferCatalog with Service items.
		$services = \WBListora\Core\Services::get_services( $this->post_id );
		if ( ! empty( $services ) ) {
			$service_items = array();
			// Listora's currency, not WooCommerce's. Structured data has to
			// agree with the page: the price beside it is rendered with
			// wb_listora_format_currency(), which follows Settings > General.
			// Reading WooCommerce's option meant a site set to JPY showed ¥ to
			// visitors and told search engines USD — and it invented a currency
			// on the many directories that have no WooCommerce at all.
			$currency      = function_exists( 'wb_listora_get_currency_format' )
				? wb_listora_get_currency_format()['code']
				: (string) get_option( 'woocommerce_currency', 'USD' );

			foreach ( $services as $svc ) {
				$service_item = array(
					'@type'       => 'Offer',
					'itemOffered' => array(
						'@type'       => 'Service',
						'name'        => $svc['title'],
						'description' => $svc['description'],
					),
				);

				// Service image.
				if ( ! empty( $svc['image_id'] ) ) {
					$svc_img_url = wp_get_attachment_image_url( (int) $svc['image_id'], 'large' );
					if ( $svc_img_url ) {
						$service_item['itemOffered']['image'] = $svc_img_url;
					}
				}

				// Price.
				if ( null !== $svc['price'] && 'free' !== $svc['price_type'] && 'contact' !== $svc['price_type'] ) {
					$service_item['itemOffered']['offers'] = array(
						'@type'         => 'Offer',
						'price'         => number_format( (float) $svc['price'], 2, '.', '' ),
						'priceCurrency' => $currency,
					);
				}

				$service_items[] = $service_item;
			}

			$data['hasOfferCatalog'] = array(
				'@type'           => 'OfferCatalog',
				'name'            => __( 'Services', 'wb-listora' ),
				'itemListElement' => $service_items,
			);
		}

		return $data;
	}

	/**
	 * Format a field value for Schema.org.
	 *
	 * @param \WBListora\Core\Field $field Field.
	 * @param mixed                 $value Value.
	 * @return mixed
	 */
	private function format_schema_value( $field, $value ) {
		$type = $field->get_type();

		switch ( $type ) {
			case 'price':
				if ( is_array( $value ) && isset( $value['amount'] ) ) {
					return (float) $value['amount'];
				}
				return is_numeric( $value ) ? (float) $value : $value;

			case 'multiselect':
				return is_array( $value ) ? $value : array( $value );

			case 'checkbox':
				return $value ? true : false;

			case 'date':
			case 'datetime':
				return $value; // Already ISO format.

			default:
				if ( is_array( $value ) ) {
					return wp_json_encode( $value );
				}
				return is_string( $value ) ? $value : (string) $value;
		}
	}

	/**
	 * Format business hours for Schema.org.
	 *
	 * @param array $hours Business hours data.
	 * @return array
	 */
	private function format_hours_schema( $hours ) {
		$day_names = array(
			0 => 'Sunday',
			1 => 'Monday',
			2 => 'Tuesday',
			3 => 'Wednesday',
			4 => 'Thursday',
			5 => 'Friday',
			6 => 'Saturday',
		);

		$specs = array();

		foreach ( $hours as $h ) {
			if ( ! isset( $h['day'] ) || ! empty( $h['closed'] ) ) {
				continue;
			}

			$day  = (int) $h['day'];
			$spec = array(
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => $day_names[ $day ] ?? 'Monday',
			);

			if ( ! empty( $h['is_24h'] ) ) {
				$spec['opens']  = '00:00';
				$spec['closes'] = '23:59';
			} else {
				$spec['opens']  = $h['open'] ?? '09:00';
				$spec['closes'] = $h['close'] ?? '17:00';
			}

			$specs[] = $spec;
		}

		return $specs;
	}

	/**
	 * Output Open Graph and Twitter Card meta tags.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function output_og_tags( $post_id ) {
		if ( ! wb_listora_feature_enabled( 'opengraph' ) ) {
			return;
		}

		// Defer OG + Twitter + meta to any active SEO plugin (Yoast / Rank Math /
		// AIOSEO / SEOPress) — those plugins emit their own. Single canonical
		// detector in Free.
		if ( function_exists( 'wb_listora_seo_plugin_active' ) && wb_listora_seo_plugin_active() ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$title     = $post->post_title;
		$desc      = wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );
		$url       = get_permalink( $post_id );
		$image_url = get_the_post_thumbnail_url( $post_id, 'large' );

		$registry  = \WBListora\Core\Listing_Type_Registry::instance();
		$type      = $registry->get_for_post( $post_id );
		$type_name = $type ? $type->get_name() : '';

		if ( $type_name ) {
			$title .= ' — ' . $type_name;
		}

		// Open Graph.
		echo '<meta property="og:type" content="place" />' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $desc ) . '" />' . "\n";
		echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
		echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />' . "\n";
		echo '<meta property="og:locale" content="' . esc_attr( str_replace( '-', '_', get_locale() ) ) . '" />' . "\n";

		if ( $image_url ) {
			echo '<meta property="og:image" content="' . esc_url( $image_url ) . '" />' . "\n";
		}

		// Geo.
		$meta = \WBListora\Core\Meta_Handler::get_all_values( $post_id );
		$addr = $meta['address'] ?? array();
		if ( is_array( $addr ) && ! empty( $addr['lat'] ) ) {
			echo '<meta property="place:location:latitude" content="' . esc_attr( $addr['lat'] ) . '" />' . "\n";
			echo '<meta property="place:location:longitude" content="' . esc_attr( $addr['lng'] ) . '" />' . "\n";
		}

		// Twitter Card.
		echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
		echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '" />' . "\n";
		echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '" />' . "\n";

		if ( $image_url ) {
			echo '<meta name="twitter:image" content="' . esc_url( $image_url ) . '" />' . "\n";
		}
	}

	/**
	 * Build the canonical breadcrumb trail for a listing.
	 *
	 * Single source of truth for BOTH the visual breadcrumb (rendered in
	 * `blocks/listing-detail/render.php`) AND the JSON-LD BreadcrumbList
	 * (emitted by `output_breadcrumbs()`). Returning one shared trail keeps
	 * the rendered crumbs and the structured data in lockstep — previously
	 * the two were built from divergent sources (different root label,
	 * different root URL, and a hardcoded vs. real type-page lookup), so
	 * Google saw a BreadcrumbList that didn't match the visible UI.
	 *
	 * Trail shape: directory root → listing type → primary category →
	 * the listing itself (URL-less leaf). Each entry is
	 * `array{ name: string, url: string }`. The leaf carries an empty URL.
	 *
	 * @since 1.1.0
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, array{name: string, url: string}> Ordered crumb trail.
	 */
	public static function get_breadcrumb_items( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}

		// Directory root — resolved via the page registry so it matches the
		// visual breadcrumb (label "Directory", URL of the mapped page).
		$directory_url = function_exists( 'wb_listora_get_page_url' ) ? wb_listora_get_page_url( 'directory' ) : '';
		if ( '' === $directory_url ) {
			$directory_url = home_url( '/' );
		}

		$items = array(
			array(
				'name' => __( 'Directory', 'wb-listora' ),
				'url'  => $directory_url,
			),
		);

		// Listing type — resolve the real type page (matches the visual
		// breadcrumb's `get_page_by_path( $type_slug )` lookup) rather than a
		// hardcoded `home_url( '/' . $slug . '/' )` pattern that need not exist.
		$registry = \WBListora\Core\Listing_Type_Registry::instance();
		$type     = $registry->get_for_post( $post_id );
		if ( $type ) {
			$type_slug = $type->get_slug();
			$type_page = $type_slug ? get_page_by_path( $type_slug ) : null;
			$items[]   = array(
				'name' => $type->get_name(),
				'url'  => $type_page ? get_permalink( $type_page ) : '',
			);
		}

		// Primary category — guarded term link (H14).
		$cats = wp_get_object_terms( $post_id, 'listora_listing_cat' );
		if ( ! is_wp_error( $cats ) && ! empty( $cats ) ) {
			$cat_link = get_term_link( $cats[0] );
			$items[]  = array(
				'name' => $cats[0]->name,
				'url'  => is_wp_error( $cat_link ) ? '' : $cat_link,
			);
		}

		// The listing itself — leaf node, no URL.
		$items[] = array(
			'name' => $post->post_title,
			'url'  => '',
		);

		return $items;
	}

	/**
	 * Output breadcrumb JSON-LD.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function output_breadcrumbs( $post_id ) {
		if ( ! wb_listora_feature_enabled( 'breadcrumbs' ) ) {
			return;
		}

		// Defer the BreadcrumbList JSON-LD to any active SEO plugin (Yoast / Rank
		// Math / AIOSEO / SEOPress) — they emit their own breadcrumb structured
		// data. The VISUAL breadcrumb still renders in the listing-detail template
		// (it draws from get_breadcrumb_items() directly, not from this head
		// emitter), so only the duplicate structured data is suppressed.
		if ( function_exists( 'wb_listora_seo_plugin_active' ) && wb_listora_seo_plugin_active() ) {
			return;
		}

		$items = self::get_breadcrumb_items( $post_id );
		if ( empty( $items ) ) {
			return;
		}

		$breadcrumb_data = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => array(),
		);

		foreach ( $items as $pos => $item ) {
			$el = array(
				'@type'    => 'ListItem',
				'position' => $pos + 1,
				'name'     => $item['name'],
			);

			if ( $item['url'] ) {
				$el['item'] = $item['url'];
			}

			$breadcrumb_data['itemListElement'][] = $el;
		}

		echo '<script type="application/ld+json">' . wp_json_encode( $breadcrumb_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}

	/**
	 * Output canonical URL for search/filtered pages.
	 */
	public static function output_canonical() {
		if ( ! is_singular( 'listora_listing' ) ) {
			return;
		}

		// Defer to any active SEO plugin (Yoast / Rank Math / AIOSEO / SEOPress)
		// — it owns the canonical. Single canonical detector in Free.
		if ( function_exists( 'wb_listora_seo_plugin_active' ) && wb_listora_seo_plugin_active() ) {
			return;
		}

		// Suppress WordPress core's rel_canonical() (wp_head priority 10) so we don't
		// emit a duplicate <link rel="canonical"> on listing singulars. Third-party SEO
		// plugins are already short-circuited by the wb_listora_seo_plugin_active() check
		// above, so we only remove the WP-core hook here.
		remove_action( 'wp_head', 'rel_canonical' );

		echo '<link rel="canonical" href="' . esc_url( get_permalink() ) . '" />' . "\n";
	}

	/**
	 * Initialize all SEO hooks.
	 */
	public static function init_seo() {
		// OG tags + Twitter Cards.
		add_action(
			'wp_head',
			function () {
				if ( is_singular( 'listora_listing' ) ) {
					self::output_og_tags( get_the_ID() );
					self::output_breadcrumbs( get_the_ID() );
					self::output_canonical();
				}
			},
			5
		);

		// Noindex for filtered search pages.
		add_action(
			'wp_head',
			function () {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only SEO check, no form submission.
				if ( ! is_singular() && ! empty( $_GET ) ) {
					$filter_params = array_diff_key( $_GET, array_flip( array( 'page', 'sort', 'paged' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					if ( count( $filter_params ) >= 3 ) {
						echo '<meta name="robots" content="noindex, follow" />' . "\n";
					}
				}
			},
			1
		);
	}
}
