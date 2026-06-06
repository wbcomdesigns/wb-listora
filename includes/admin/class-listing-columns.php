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
	 * Pre-loaded analytics-lite view counts keyed by post ID.
	 *
	 * @var array<int,int>
	 */
	private $views_cache = array();

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_filter( 'manage_listora_listing_posts_columns', array( $this, 'add_columns' ) );
		add_action( 'manage_listora_listing_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_filter( 'manage_edit-listora_listing_sortable_columns', array( $this, 'sortable_columns' ) );
		add_action( 'restrict_manage_posts', array( $this, 'add_filters' ), 10, 1 );
		add_action( 'pre_get_posts', array( $this, 'filter_query' ) );

		// View-count sort joins the analytics table (the count isn't postmeta,
		// so it can't ride pre_get_posts' meta_key path).
		add_filter( 'posts_clauses', array( $this, 'sort_by_views' ), 10, 2 );

		// Row action: "Mark verified" — manually transition pending_verification listings.
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_action( 'admin_action_listora_mark_verified', array( $this, 'handle_mark_verified' ) );

		// Row actions: one-click Approve / Reject for pending listings.
		add_action( 'admin_action_listora_approve_listing', array( $this, 'handle_approve_listing' ) );
		add_action( 'admin_action_listora_reject_listing', array( $this, 'handle_reject_listing' ) );

		// Bulk action: send renewal-reminder email to selected listings' authors.
		add_filter( 'bulk_actions-edit-listora_listing', array( $this, 'register_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-listora_listing', array( $this, 'handle_bulk_actions' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'bulk_action_notices' ) );
		add_action( 'admin_notices', array( $this, 'moderation_action_notices' ) );

		// Display label for pending_verification in list-table status column.
		add_filter( 'display_post_states', array( $this, 'post_states' ), 10, 2 );

		// Show custom statuses in status filter links.
		add_filter( 'views_edit-listora_listing', array( $this, 'add_status_views' ) );

		// Prime caches before column rendering to avoid N+1 queries per row.
		add_filter( 'the_posts', array( $this, 'prime_column_caches' ), 10, 2 );
	}

	/**
	 * Batch-prime meta, term, and rating caches for all posts on the listings admin screen.
	 *
	 * @param \WP_Post[] $posts Posts.
	 * @param \WP_Query  $query Query.
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

		// Batch-load analytics-lite view counts in one grouped query for the
		// whole page (never one query per row — 100k-scale rule). The service
		// reads the same `view` rows whether Free or Pro wrote them.
		if ( ! empty( $ids ) && class_exists( '\\WBListora\\Features\\Analytics_Lite' ) ) {
			$this->views_cache = \WBListora\Features\Analytics_Lite::prepare_views( $ids );
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
				$new['listora_type']      = __( 'Type', 'wb-listora' );
				$new['listora_location']  = __( 'Location', 'wb-listora' );
				$new['listora_rating']    = __( 'Rating', 'wb-listora' );
				$new['listora_views']     = __( 'Views', 'wb-listora' );
				$new['listora_featured']  = __( 'Featured', 'wb-listora' );
				$new['listora_renewals']  = __( 'Renewals', 'wb-listora' );
				$new['listora_reports']   = __( 'Reports', 'wb-listora' );
				$new['listora_duplicate'] = __( 'Duplicate confirmed', 'wb-listora' );
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
				$thumb = get_the_post_thumbnail( $post_id, array( 40, 40 ), array( 'class' => 'listora-listing-col__thumb' ) );
				echo $thumb ? $thumb : '<span class="dashicons dashicons-format-image listora-listing-col__thumb-empty"></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $thumb is from get_the_post_thumbnail(), already escaped by WordPress.
				break;

			case 'listora_type':
				$terms = wp_get_object_terms( $post_id, 'listora_listing_type' );
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					$type  = \WBListora\Core\Listing_Type_Registry::instance()->get( $terms[0]->slug );
					$color = $type ? $type->get_color() : '#666666';
					printf(
						'<span class="listora-listing-col__type" style="--listora-type-color:%s;">%s</span>',
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
						'<span class="listora-listing-col__star">★</span> %s <span class="listora-listing-col__count">(%d)</span>',
						esc_html( number_format( (float) $row['avg_rating'], 1 ) ),
						(int) $row['review_count']
					);
				} else {
					echo '<span class="listora-listing-col__placeholder">—</span>';
				}
				break;

			case 'listora_views':
				// Analytics-lite view count — uses the batch-loaded cache primed
				// in prime_column_caches() (one grouped query per page).
				$views = isset( $this->views_cache[ $post_id ] ) ? (int) $this->views_cache[ $post_id ] : 0;
				if ( $views > 0 ) {
					printf(
						'<span class="listora-listing-col__views"><span class="dashicons dashicons-visibility" aria-hidden="true"></span> %s</span>',
						esc_html( number_format_i18n( $views ) )
					);
				} else {
					echo '<span class="listora-listing-col__placeholder">—</span>';
				}
				break;

			case 'listora_featured':
				if ( \WBListora\Core\Featured::is_featured( $post_id ) ) {
					$until = (int) get_post_meta( $post_id, \WBListora\Core\Featured::META_FEATURED_UNTIL, true );
					if ( $until > 0 ) {
						printf(
							'<span class="listora-listing-col__featured" title="%s">★</span>',
							/* translators: %s: human-friendly expiration date. */
							esc_attr( sprintf( __( 'Featured until %s', 'wb-listora' ), date_i18n( get_option( 'date_format' ), $until ) ) )
						);
					} else {
						printf(
							'<span class="listora-listing-col__featured" title="%s">★</span>',
							esc_attr__( 'Featured permanently', 'wb-listora' )
						);
					}
				} else {
					echo '<span class="listora-listing-col__placeholder">—</span>';
				}
				break;

			case 'listora_renewals':
				$count = (int) get_post_meta( $post_id, '_listora_renewal_count', true );
				if ( $count > 0 ) {
					$last      = (string) get_post_meta( $post_id, '_listora_renewed_at', true );
					$last_unix = $last ? (int) strtotime( $last ) : 0;
					$title     = $last_unix > 0
						? sprintf(
							/* translators: %s: human time diff. */
							__( 'Last renewed %s ago', 'wb-listora' ),
							human_time_diff( $last_unix, (int) current_time( 'timestamp' ) ) // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
						)
						: '';
					printf(
						'<span title="%s" style="font-weight:600;color:#00a32a;">%d</span>',
						esc_attr( $title ),
						(int) $count
					);
				} else {
					echo '<span class="listora-listing-col__placeholder">—</span>';
				}
				break;

			case 'listora_reports':
				$listora_reports      = get_option( '_listora_listing_reports_' . $post_id, array() );
				$listora_report_count = is_array( $listora_reports ) ? count( $listora_reports ) : 0;
				if ( $listora_report_count > 0 ) {
					printf(
						'<a href="%1$s#wb_listora_reports" class="listora-listing-col__reports row-title" title="%2$s"><span class="dashicons dashicons-flag" aria-hidden="true"></span> %3$d</a>',
						esc_url( (string) get_edit_post_link( $post_id ) ),
						esc_attr(
							sprintf(
								/* translators: %d: number of reports. */
								_n( '%d report — click to review', '%d reports — click to review', $listora_report_count, 'wb-listora' ),
								$listora_report_count
							)
						),
						(int) $listora_report_count
					);
				} else {
					echo '<span class="listora-listing-col__placeholder">—</span>';
				}
				break;

			case 'listora_duplicate':
				// "_listora_duplicate_confirmed" is set whenever a submitter
				// bypassed the 409 duplicate warning. The explanation meta
				// holds their reason and is shown as a hover tooltip so
				// moderators can triage without leaving the list table.
				$confirmed = (string) get_post_meta( $post_id, '_listora_duplicate_confirmed', true );
				if ( '1' === $confirmed ) {
					$explanation = (string) get_post_meta( $post_id, '_listora_duplicate_explanation', true );
					$tooltip     = '' !== $explanation
						? sprintf(
							/* translators: %s: user-supplied explanation. */
							__( 'User confirmed not duplicate: %s', 'wb-listora' ),
							$explanation
						)
						: __( 'User confirmed not duplicate (no explanation provided).', 'wb-listora' );
					printf(
						'<span class="listora-duplicate-flag" style="color:#d97706;font-size:1.1em;cursor:help;" title="%s" aria-label="%s">⚠</span>',
						esc_attr( $tooltip ),
						esc_attr( $tooltip )
					);
				} else {
					echo '<span class="listora-listing-col__placeholder">—</span>';
				}
				break;
		}
	}

	/**
	 * Make columns sortable.
	 */
	public function sortable_columns( $columns ) {
		$columns['listora_rating']    = 'listora_rating';
		$columns['listora_duplicate'] = 'listora_duplicate';
		$columns['listora_views']     = 'listora_views';
		return $columns;
	}

	/**
	 * Sort the listings table by analytics-lite view count.
	 *
	 * View totals live in the custom `analytics` table, not postmeta, so the
	 * sort can't use `meta_key`/`orderby=meta_value`. Instead we LEFT JOIN a
	 * grouped SUM subquery (one derived table, indexed on `listing_id`) and
	 * order by it — bounded by WP's own LIMIT/paging, so it stays scale-safe.
	 *
	 * @param array     $clauses Query clauses (join, orderby, etc.).
	 * @param \WP_Query $query   The current query.
	 * @return array
	 */
	public function sort_by_views( $clauses, $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return $clauses;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-listora_listing' !== $screen->id ) {
			return $clauses;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- standard WP admin list table sort.
		$orderby = sanitize_text_field( wp_unslash( $_GET['orderby'] ?? '' ) );
		if ( 'listora_views' !== $orderby ) {
			return $clauses;
		}

		global $wpdb;
		$table = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'analytics';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- standard WP admin list table sort.
		$order = strtoupper( sanitize_text_field( wp_unslash( $_GET['order'] ?? 'DESC' ) ) );
		$order = ( 'ASC' === $order ) ? 'ASC' : 'DESC';

		// $table is a trusted $wpdb->prefix + plugin constant, not user input.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$clauses['join']   .= $wpdb->prepare( " LEFT JOIN ( SELECT listing_id, SUM(count) AS listora_views_total FROM {$table} WHERE event_type = %s GROUP BY listing_id ) AS listora_va ON listora_va.listing_id = {$wpdb->posts}.ID", \WBListora\Features\Analytics_Lite::EVENT_VIEW ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$clauses['orderby'] = "COALESCE(listora_va.listora_views_total, 0) {$order}, {$wpdb->posts}.ID DESC";

		return $clauses;
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

		// Duplicate-confirmed filter — lets moderators surface only the
		// listings that bypassed the 409 dupe warning.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- standard WP admin list table filter.
		$dup_selected = sanitize_text_field( wp_unslash( $_GET['listora_duplicate_filter'] ?? '' ) );
		echo '<label for="listora-duplicate-filter" class="screen-reader-text">' . esc_html__( 'Filter by duplicate confirmation', 'wb-listora' ) . '</label>';
		echo '<select id="listora-duplicate-filter" name="listora_duplicate_filter">';
		echo '<option value="">' . esc_html__( 'All Listings', 'wb-listora' ) . '</option>';
		printf(
			'<option value="confirmed" %s>%s</option>',
			selected( $dup_selected, 'confirmed', false ),
			esc_html__( 'Only duplicate-confirmed', 'wb-listora' )
		);
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

		// Duplicate-confirmed filter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- standard WP admin list table filter.
		$dup_filter = sanitize_text_field( wp_unslash( $_GET['listora_duplicate_filter'] ?? '' ) );
		if ( 'confirmed' === $dup_filter ) {
			$meta_query   = $query->get( 'meta_query' ) ?: array();
			$meta_query[] = array(
				'key'   => '_listora_duplicate_confirmed',
				'value' => '1',
			);
			$query->set( 'meta_query', $meta_query );
		}

		// Sort by duplicate-confirmed flag (clicked column header).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- standard WP admin list table sort.
		$orderby = sanitize_text_field( wp_unslash( $_GET['orderby'] ?? '' ) );
		if ( 'listora_duplicate' === $orderby ) {
			$query->set( 'meta_key', '_listora_duplicate_confirmed' );
			$query->set( 'orderby', 'meta_value' );
		}

		// Show custom statuses in the "All" view.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- standard WP admin list table filter, no form submission.
		if ( empty( $_GET['post_status'] ) ) {
			$query->set( 'post_status', array( 'publish', 'pending', 'draft', 'listora_expired', 'listora_rejected', 'listora_deactivated', 'listora_payment', 'pending_verification' ) );
		}
	}

	/**
	 * Add custom status views to the list table filter links.
	 */
	public function add_status_views( $views ) {
		global $wpdb;

		$custom_statuses = array(
			'listora_expired'      => __( 'Expired', 'wb-listora' ),
			'listora_rejected'     => __( 'Rejected', 'wb-listora' ),
			'listora_deactivated'  => __( 'Deactivated', 'wb-listora' ),
			'pending_verification' => __( 'Pending Email Verification', 'wb-listora' ),
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

	/**
	 * Show a "Pending Email Verification" pill in the list-table status column.
	 *
	 * @param array    $states Post states.
	 * @param \WP_Post $post   Post.
	 * @return array
	 */
	public function post_states( $states, $post ) {
		if ( $post && 'listora_listing' === $post->post_type && 'pending_verification' === $post->post_status ) {
			$states['listora_pending_verify'] = '<span style="display:inline-block;padding:2px 8px;background:#fcf0e1;color:#b45309;border-radius:10px;font-size:11px;font-weight:600;">' . esc_html__( 'Pending Email Verification', 'wb-listora' ) . '</span>';
		}
		return $states;
	}

	/**
	 * Inject a "Mark verified" row action for listings stuck in pending_verification.
	 *
	 * Lets moderators manually unblock a listing when the user lost the email
	 * (or the email never reached them — common in dev environments).
	 *
	 * @param array    $actions Row actions.
	 * @param \WP_Post $post    Post.
	 * @return array
	 */
	public function row_actions( $actions, $post ) {
		if ( 'listora_listing' !== $post->post_type ) {
			return $actions;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		// Stuck-in-email-verification listings get the manual unblock action.
		if ( 'pending_verification' === $post->post_status ) {
			$url = wp_nonce_url(
				admin_url( 'admin.php?action=listora_mark_verified&post=' . (int) $post->ID ),
				'listora_mark_verified_' . $post->ID
			);

			$actions['listora_mark_verified'] = sprintf(
				'<a href="%s" style="color:#00a32a;">%s</a>',
				esc_url( $url ),
				esc_html__( 'Mark verified', 'wb-listora' )
			);
		}

		// One-click Approve / Reject for listings awaiting moderation — saves
		// opening Quick Edit or the editor just to publish or decline.
		if ( 'pending' === $post->post_status ) {
			$approve_url = wp_nonce_url(
				admin_url( 'admin.php?action=listora_approve_listing&post=' . (int) $post->ID ),
				'listora_approve_listing_' . $post->ID
			);
			$reject_url  = wp_nonce_url(
				admin_url( 'admin.php?action=listora_reject_listing&post=' . (int) $post->ID ),
				'listora_reject_listing_' . $post->ID
			);

			$actions['listora_approve'] = sprintf(
				'<a href="%s" style="color:#00a32a;">%s</a>',
				esc_url( $approve_url ),
				esc_html__( 'Approve', 'wb-listora' )
			);
			$actions['listora_reject']  = sprintf(
				'<a href="%s" style="color:#b32d2e;">%s</a>',
				esc_url( $reject_url ),
				esc_html__( 'Reject', 'wb-listora' )
			);
		}

		return $actions;
	}

	/**
	 * Handle the one-click "Approve" / "Reject" row actions for pending listings.
	 *
	 * Transitions the post to the canonical moderation status (`publish` /
	 * `listora_rejected`) used by the bulk-moderate REST endpoint. The status
	 * change fires `transition_post_status`, which drives the search reindex +
	 * `wb_listora_listing_status_changed` notification chain automatically — no
	 * manual dispatch needed.
	 *
	 * @param string $action      Either 'approve' or 'reject'.
	 * @param string $nonce_key   Nonce action prefix.
	 * @param string $new_status  Target post status.
	 * @param string $notice_flag Query-arg flag set on redirect for the notice.
	 * @return void
	 */
	private function handle_moderation_action( $action, $nonce_key, $new_status, $notice_flag ) {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce check below.
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! $post_id || ! wp_verify_nonce( $nonce, $nonce_key . $post_id ) ) {
			wp_die( esc_html__( 'Invalid request.', 'wb-listora' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wb-listora' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post || 'listora_listing' !== $post->post_type || 'pending' !== $post->post_status ) {
			wp_safe_redirect( admin_url( 'edit.php?post_type=listora_listing' ) );
			exit;
		}

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $new_status,
			)
		);

		wp_safe_redirect( add_query_arg( array( $notice_flag => 1 ), admin_url( 'edit.php?post_type=listora_listing' ) ) );
		exit;
	}

	/**
	 * Approve a pending listing (transition to publish).
	 */
	public function handle_approve_listing(): void {
		$this->handle_moderation_action( 'approve', 'listora_approve_listing_', 'publish', 'listora_approved' );
	}

	/**
	 * Reject a pending listing (transition to listora_rejected).
	 */
	public function handle_reject_listing(): void {
		$this->handle_moderation_action( 'reject', 'listora_reject_listing_', 'listora_rejected', 'listora_rejected' );
	}

	/**
	 * Handle the "Mark verified" admin action.
	 *
	 * Consumes any active token, transitions to pending (or publish on
	 * auto_approve), and bounces back to the listings list with a notice.
	 */
	public function handle_mark_verified() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce check below.
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! $post_id || ! wp_verify_nonce( $nonce, 'listora_mark_verified_' . $post_id ) ) {
			wp_die( esc_html__( 'Invalid request.', 'wb-listora' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wb-listora' ) );
		}

		$moderation = wb_listora_get_setting( 'moderation', 'manual' );
		$new_status = ( 'auto_approve' === $moderation ) ? 'publish' : 'pending';

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $new_status,
			)
		);

		\WBListora\Workflow\Email_Verification::consume_token( $post_id );

		do_action( 'wb_listora_after_email_verified', $post_id, $new_status );
		$synthetic_request = new \WP_REST_Request();
		// 4th arg `$context` (1.1.0+) — empty array = user-driven submission.
		// Migration / bulk-importer fires pass `['source' => 'migration']`
		// so listeners can opt out of per-listing side-effects.
		do_action( 'wb_listora_listing_submitted', $post_id, $new_status, $synthetic_request, array() );
		if ( 'pending' === $new_status ) {
			do_action( 'wb_listora_listing_pending_admin', $post_id );
		}

		wp_safe_redirect( add_query_arg( array( 'listora_verified' => 1 ), admin_url( 'edit.php?post_type=listora_listing' ) ) );
		exit;
	}

	/**
	 * Register the bulk "Send renewal reminder" action.
	 *
	 * @param array $actions Existing bulk actions.
	 * @return array
	 */
	public function register_bulk_actions( $actions ) {
		$actions['listora_send_renewal_reminder'] = __( 'Send renewal reminder', 'wb-listora' );
		return $actions;
	}

	/**
	 * Handle the bulk renewal reminder action — fires the listing_expiring
	 * notification immediately (bypassing reminder-flag dedupe).
	 *
	 * @param string $sendback Redirect URL.
	 * @param string $action   Action key.
	 * @param int[]  $post_ids Selected post IDs.
	 * @return string Redirect URL with results.
	 */
	public function handle_bulk_actions( $sendback, $action, $post_ids ) {
		if ( 'listora_send_renewal_reminder' !== $action ) {
			return $sendback;
		}

		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return $sendback;
		}

		$sent = 0;
		foreach ( (array) $post_ids as $post_id ) {
			$post = get_post( (int) $post_id );
			if ( ! $post || 'listora_listing' !== $post->post_type ) {
				continue;
			}

			// Compute days_until_expiry for the email's "%d days" copy.
			$exp_raw = (string) get_post_meta( $post_id, '_listora_expiration_date', true );
			$exp_ts  = $exp_raw ? (int) strtotime( $exp_raw ) : 0;
			$days    = 7;
			if ( $exp_ts > 0 ) {
				$now_ts = (int) current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
				$days   = max( 1, (int) ceil( ( $exp_ts - $now_ts ) / DAY_IN_SECONDS ) );
			}

			do_action( 'wb_listora_listing_expiring', (int) $post_id, $days );
			++$sent;
		}

		return add_query_arg( 'listora_renewal_reminders', (int) $sent, $sendback );
	}

	/**
	 * Show admin notice after a bulk renewal reminder run.
	 */
	public function bulk_action_notices() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin notice from URL param.
		if ( ! isset( $_GET['listora_renewal_reminders'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$count = absint( wp_unslash( $_GET['listora_renewal_reminders'] ) );
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d: number of reminder emails sent */
					_n( 'Sent renewal reminder to %d listing owner.', 'Sent renewal reminders to %d listing owners.', $count, 'wb-listora' ),
					$count
				)
			)
		);
	}

	/**
	 * Show an admin notice after a row-action moderation transition
	 * (Mark verified / Approve / Reject).
	 */
	public function moderation_action_notices(): void {
		$messages = array(
			'listora_verified' => __( 'Listing marked verified.', 'wb-listora' ),
			'listora_approved' => __( 'Listing approved and published.', 'wb-listora' ),
			'listora_rejected' => __( 'Listing rejected.', 'wb-listora' ),
		);

		foreach ( $messages as $flag => $message ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin notice from URL param.
			if ( isset( $_GET[ $flag ] ) ) {
				printf(
					'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
					esc_html( $message )
				);
			}
		}
	}
}
