<?php
/**
 * Admin Listing Columns — adds custom columns and filters to the listings list table.
 *
 * @package WBListora\Admin
 */

namespace WBListora\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Adds listing type, location, rating columns and type/status filters.
 */
class Listing_Columns {

	/**
	 * Pre-loaded rating data keyed by post ID.
	 *
	 * @var array
	 */
	private $ratings_cache = array();

	/**
	 * Pre-loaded geo data keyed by post ID.
	 *
	 * @var array
	 */
	private $geo_cache = array();

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_filter( 'manage_listora_listing_posts_columns', array( $this, 'add_columns' ) );
		add_action( 'manage_listora_listing_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_filter( 'manage_edit-listora_listing_sortable_columns', array( $this, 'sortable_columns' ) );
		add_action( 'restrict_manage_posts', array( $this, 'add_filters' ), 10, 1 );
		add_action( 'pre_get_posts', array( $this, 'filter_query' ) );

		// Show custom statuses in status filter links.
		add_filter( 'views_edit-listora_listing', array( $this, 'add_status_views' ) );

		// Prime caches before column rendering to avoid N+1 queries per row.
		add_filter( 'the_posts', array( $this, 'prime_column_caches' ), 10, 2 );
	}

	/**
	 * Batch-prime meta, term, and rating caches for all posts on the listings admin screen.
	 *
	 * @param \WP_Post[]  $posts Posts.
	 * @param \WP_Query   $query Query.
	 * @return \WP_Post[]
	 */
	public function prime_column_caches( $posts, $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || empty( $posts ) ) {
			return $posts;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-listora_listing' !== $screen->id ) {
			return $posts;
		}

		$ids = wp_list_pluck( $posts, 'ID' );

		// Prime post meta cache (thumbnails, listora meta, etc.).
		update_meta_cache( 'post', $ids );

		// Prime term cache (listing type, location, features).
		update_object_term_cache( $ids, 'listora_listing' );

		// Batch-load geo data for location column.
		if ( ! empty( $ids ) ) {
			global $wpdb;
			$prefix       = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$geo_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT listing_id, city, state, country FROM {$prefix}geo WHERE listing_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					...$ids
				),
				ARRAY_A
			);
			foreach ( $geo_rows as $grow ) {
				$this->geo_cache[ (int) $grow['listing_id'] ] = $grow;
			}
		}

		// Batch-load ratings from search_index.
		if ( ! empty( $ids ) ) {
			global $wpdb;
			$prefix       = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rating_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT listing_id, avg_rating, review_count FROM {$prefix}search_index WHERE listing_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					...$ids
				),
				ARRAY_A
			);
			foreach ( $rating_rows as $rrow ) {
				$this->ratings_cache[ (int) $rrow['listing_id'] ] = $rrow;
			}
		}

		return $posts;
	}

	/**
	 * Add custom columns.
	 */
	public function add_columns( $columns ) {
		$new = array();

		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$new['listora_thumb'] = '<span class="dashicons dashicons-format-image" title="' . esc_attr__( 'Image', 'wb-listora' ) . '"></span>';
			}

			$new[ $key ] = $label;

			if ( 'title' === $key ) {
				$new['listora_type']     = __( 'Type', 'wb-listora' );
				$new['listora_location'] = __( 'Location', 'wb-listora' );
				$new['listora_rating']   = __( 'Rating', 'wb-listora' );
			}
		}

		// Remove default date — we'll add our own at the end.
		unset( $new['date'] );
		$new['date'] = __( 'Date', 'wb-listora' );

		return $new;
	}

	/**
	 * Render custom column content.
	 */
	public function render_column( $column, $post_id ) {
		switch ( $column ) {
			case 'listora_thumb':
				$thumb = get_the_post_thumbnail( $post_id, array( 40, 40 ), array( 'style' => 'border-radius:4px;' ) );
				echo $thumb ? $thumb : '<span class="dashicons dashicons-format-image" style="color:#ccc;font-size:28px;"></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $thumb is from get_the_post_thumbnail(), already escaped by WordPress.
				break;

			case 'listora_type':
				$terms = wp_get_object_terms( $post_id, 'listora_listing_type' );
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					$type  = \WBListora\Core\Listing_Type_Registry::instance()->get( $terms[0]->slug );
					$color = $type ? $type->get_color() : '#666';
					printf(
						'<span style="display:inline-flex;align-items:center;gap:0.3em;padding:0.15em 0.5em;background:%s15;color:%s;border-radius:3px;font-size:0.85em;font-weight:500;">%s</span>',
						esc_attr( $color ),
						esc_attr( $color ),
						esc_html( $terms[0]->name )
					);
				}
				break;

			case 'listora_location':
				$geo = $this->geo_cache[ $post_id ] ?? null;
				if ( $geo && ! empty( $geo['city'] ) ) {
					echo esc_html( implode( ', ', array_filter( array( $geo['city'], $geo['state'] ?? '' ) ) ) );
				} else {
					// Fallback to flat address meta.
					$addr = \WBListora\Core\Meta_Handler::get_value( $post_id, 'address', '' );
					if ( $addr && is_string( $addr ) ) {
						echo esc_html( $addr );
					}
				}
				break;

			case 'listora_rating':
				// Use batch-loaded ratings cache (primed in prime_column_caches).
				$row = $this->ratings_cache[ $post_id ] ?? null;
				if ( $row && (float) $row['avg_rating'] > 0 ) {
					printf(
						'<span style="color:#f5a623;">★</span> %s <span style="color:#999;">(%d)</span>',
						esc_html( number_format( (float) $row['avg_rating'], 1 ) ),
						(int) $row['review_count']
					);
				} else {
					echo '<span style="color:#ccc;">—</span>';
				}
				break;
		}
	}

	/**
	 * Make columns sortable.
	 */
	public function sortable_columns( $columns ) {
		$columns['listora_rating'] = 'listora_rating';
		return $columns;
	}

	/**
	 * Add filter dropdowns above the list table.
	 */
	public function add_filters( $post_type ) {
		if ( 'listora_listing' !== $post_type ) {
			return;
		}

		// Type filter.
		$types = \WBListora\Core\Listing_Type_Registry::instance()->get_all();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- standard WP admin list table filter, no form submission.
		$selected = sanitize_text_field( wp_unslash( $_GET['listora_type_filter'] ?? '' ) );

		echo '<label for="listora-type-filter" class="screen-reader-text">' . esc_html__( 'Filter by listing type', 'wb-listora' ) . '</label>';
		echo '<select id="listora-type-filter" name="listora_type_filter">';
		echo '<option value="">' . esc_html__( 'All Types', 'wb-listora' ) . '</option>';
		foreach ( $types as $type ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $type->get_slug() ),
				selected( $selected, $type->get_slug(), false ),
				esc_html( $type->get_name() )
			);
		}
		echo '</select>';
	}

	/**
	 * Modify the query based on filters.
	 */
	public function filter_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'edit-listora_listing' !== $screen->id ) {
			return;
		}

		// Type filter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- standard WP admin list table filter, no form submission.
		$type_filter = sanitize_text_field( wp_unslash( $_GET['listora_type_filter'] ?? '' ) );
		if ( $type_filter ) {
			$tax_query   = $query->get( 'tax_query' ) ?: array();
			$tax_query[] = array(
				'taxonomy' => 'listora_listing_type',
				'field'    => 'slug',
				'terms'    => $type_filter,
			);
			$query->set( 'tax_query', $tax_query );
		}

		// Show custom statuses in the "All" view.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- standard WP admin list table filter, no form submission.
		if ( empty( $_GET['post_status'] ) ) {
			$query->set( 'post_status', array( 'publish', 'pending', 'draft', 'listora_expired', 'listora_rejected', 'listora_deactivated', 'listora_payment' ) );
		}
	}

	/**
	 * Add custom status views to the list table filter links.
	 */
	public function add_status_views( $views ) {
		global $wpdb;

		$custom_statuses = array(
			'listora_expired'     => __( 'Expired', 'wb-listora' ),
			'listora_rejected'    => __( 'Rejected', 'wb-listora' ),
			'listora_deactivated' => __( 'Deactivated', 'wb-listora' ),
		);

		foreach ( $custom_statuses as $status => $label ) {
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'listora_listing' AND post_status = %s",
					$status
				)
			);

			if ( $count > 0 ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- standard WP admin list table status views.
				$current          = ( isset( $_GET['post_status'] ) && $_GET['post_status'] === $status ) ? ' class="current"' : '';
				$views[ $status ] = sprintf(
					'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
					esc_url( admin_url( "edit.php?post_type=listora_listing&post_status={$status}" ) ),
					$current,
					esc_html( $label ),
					number_format_i18n( $count )
				);
			}
		}

		return $views;
	}
}
