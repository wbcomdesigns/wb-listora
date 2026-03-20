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
				echo $thumb ? $thumb : '<span class="dashicons dashicons-format-image" style="color:#ccc;font-size:28px;"></span>';
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
				$meta = \WBListora\Core\Meta_Handler::get_value( $post_id, 'address', array() );
				if ( is_array( $meta ) && ! empty( $meta['city'] ) ) {
					echo esc_html( implode( ', ', array_filter( array( $meta['city'] ?? '', $meta['state'] ?? '' ) ) ) );
				}
				break;

			case 'listora_rating':
				global $wpdb;
				$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
				$row    = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT avg_rating, review_count FROM {$prefix}search_index WHERE listing_id = %d",
						$post_id
					)
				);
				if ( $row && (float) $row->avg_rating > 0 ) {
					printf(
						'<span style="color:#f5a623;">★</span> %s <span style="color:#999;">(%d)</span>',
						esc_html( number_format( (float) $row->avg_rating, 1 ) ),
						(int) $row->review_count
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
		$types    = \WBListora\Core\Listing_Type_Registry::instance()->get_all();
		$selected = sanitize_text_field( $_GET['listora_type_filter'] ?? '' );

		echo '<select name="listora_type_filter">';
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
		$type_filter = sanitize_text_field( $_GET['listora_type_filter'] ?? '' );
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
