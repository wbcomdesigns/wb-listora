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
		$instance->meta        = \WBListora\Core\Meta_Handler::get_all_values( $post_id );

		return $instance;
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

		// Description.
		$excerpt = get_the_excerpt( $this->post );
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

		if ( $rating && (float) $rating['avg_rating'] > 0 ) {
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

		// Social links.
		$social = $this->meta['social_links'] ?? array();
		if ( is_array( $social ) && ! empty( $social ) ) {
			$same_as = $data['sameAs'] ?? array();
			foreach ( $social as $link ) {
				if ( is_array( $link ) && ! empty( $link['url'] ) ) {
					$same_as[] = $link['url'];
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

		// Business hours.
		$hours = $this->meta['business_hours'] ?? array();
		if ( is_array( $hours ) && ! empty( $hours ) ) {
			$data['openingHoursSpecification'] = $this->format_hours_schema( $hours );
		}

		// Services — OfferCatalog with Service items.
		$services = \WBListora\Core\Services::get_services( $this->post_id );
		if ( ! empty( $services ) ) {
			$service_items = array();
			$currency      = get_option( 'woocommerce_currency', 'USD' );

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
		if ( ! wb_listora_get_setting( 'enable_opengraph' ) ) {
			return;
		}

		// Don't output if Yoast or Rank Math handles it.
		if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) ) {
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
	 * Output breadcrumb JSON-LD.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function output_breadcrumbs( $post_id ) {
		if ( ! wb_listora_get_setting( 'enable_breadcrumbs' ) ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$registry = \WBListora\Core\Listing_Type_Registry::instance();
		$type     = $registry->get_for_post( $post_id );

		$items = array(
			array(
				'name' => __( 'Home', 'wb-listora' ),
				'url'  => home_url( '/' ),
			),
		);

		if ( $type ) {
			$items[] = array(
				'name' => $type->get_name(),
				'url'  => home_url( '/' . $type->get_slug() . '/' ),
			);
		}

		$cats = wp_get_object_terms( $post_id, 'listora_listing_cat' );
		if ( ! is_wp_error( $cats ) && ! empty( $cats ) ) {
			$cat_link = get_term_link( $cats[0] );
			if ( ! is_wp_error( $cat_link ) ) {
				$items[] = array(
					'name' => $cats[0]->name,
					'url'  => $cat_link,
				);
			}
		}

		$items[] = array(
			'name' => $post->post_title,
			'url'  => '',
		);

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

		// Don't output if SEO plugin handles it.
		if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) ) {
			return;
		}

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
				if ( ! is_singular() && ! empty( $_GET ) ) {
					$filter_params = array_diff_key( $_GET, array_flip( array( 'page', 'sort', 'paged' ) ) );
					if ( count( $filter_params ) >= 3 ) {
						echo '<meta name="robots" content="noindex, follow" />' . "\n";
					}
				}
			},
			1
		);
	}
}
