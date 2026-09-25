<?php
/**
 * The one list table for Listora's custom admin lists.
 *
 * Reviews, Claims, Needs, Moderators, Coupons, Badges, Transactions and the
 * logs each hand-rolled their own table, search, pagination and row actions,
 * so every screen looked and behaved differently (card 10337177659). They all
 * render through this. Core post and term lists (All Listings, Plans,
 * taxonomies) stay on WP_List_Table so bulk edit, quick edit and columns added
 * by other plugins keep working (owner decision 2026-09-25).
 *
 * Server-rendered: state lives in the URL (views, search, filters, sort,
 * page), so every state can be bookmarked and works without JS.
 * assets/js/admin/list-page.js adds select-all, confirm dialogs, the row menu
 * and the detail drawer on top.
 *
 * The caller queries its own model, with LIMIT/OFFSET from request() and a
 * COUNT(*) total; this class only renders. Pro reaches it through
 * wb_listora_service( 'admin_table' ).
 *
 * @package WBListora\Admin
 */

namespace WBListora\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Admin_Table
 *
 * @since 1.9.0
 */
class Admin_Table {

	/**
	 * Page sizes an owner can pick.
	 */
	const PER_PAGE_CHOICES = array( 20, 50, 100 );

	/**
	 * Read the list state from the request.
	 *
	 * The page size an owner picks is remembered per table, per user.
	 *
	 * @param string   $id       Table id (user meta key suffix).
	 * @param string[] $filters  Extra query args to read (sanitize_text_field).
	 * @param string[] $sortable Column keys that may be sorted on.
	 * @param string   $orderby  Default sort column.
	 * @return array{paged:int, per_page:int, offset:int, s:string, orderby:string, order:string, filters:array<string,string>}
	 */
	public function request( $id, array $filters = array(), array $sortable = array(), $orderby = '' ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list state.
		$meta_key = 'wb_listora_table_per_page_' . sanitize_key( $id );
		$per_page = isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : 0;
		if ( in_array( $per_page, self::PER_PAGE_CHOICES, true ) ) {
			update_user_meta( get_current_user_id(), $meta_key, $per_page );
		} else {
			$per_page = (int) get_user_meta( get_current_user_id(), $meta_key, true );
			$per_page = in_array( $per_page, self::PER_PAGE_CHOICES, true ) ? $per_page : self::PER_PAGE_CHOICES[0];
		}

		$paged = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );

		$sort = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '';
		$sort = in_array( $sort, $sortable, true ) ? $sort : $orderby;

		$values = array();
		foreach ( $filters as $key ) {
			$values[ $key ] = isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
		}

		$state = array(
			'paged'    => $paged,
			'per_page' => $per_page,
			'offset'   => ( $paged - 1 ) * $per_page,
			's'        => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'orderby'  => $sort,
			'order'    => isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ? 'ASC' : 'DESC',
			'filters'  => $values,
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		return $state;
	}

	/**
	 * Member ids matching a name, username or email: the "Member" filter.
	 *
	 * Replaces the raw "User ID" boxes owners could not use. Capped, so a
	 * two-letter search on a big site stays one bounded query.
	 *
	 * @param string $search Typed text; a number is taken as an id.
	 * @return int[] Matching ids; array( 0 ) when nothing matches, so the
	 *               caller's IN () clause returns no rows rather than all.
	 */
	public function user_ids( $search ) {
		$search = trim( (string) $search );
		if ( '' === $search ) {
			return array();
		}
		if ( ctype_digit( $search ) ) {
			return array( (int) $search );
		}
		$ids = get_users(
			array(
				'search'         => '*' . $search . '*',
				'search_columns' => array( 'user_login', 'user_email', 'display_name', 'user_nicename' ),
				'fields'         => 'ID',
				'number'         => 200,
			)
		);
		return $ids ? array_map( 'intval', $ids ) : array( 0 );
	}

	/**
	 * Render the table.
	 *
	 * @param array $args {
	 *     @type string $id          Table id; also the HTML id prefix.
	 *     @type string $count_text  Translated total, e.g. sprintf( _n( '%s claim', '%s claims', $total ), ... ).
	 *     @type string $base_url    The screen's URL.
	 *     @type array  $state       Result of request().
	 *     @type int    $total       COUNT(*) for the current view and filters.
	 *     @type array  $columns     key => [ 'label' => string, 'priority' => 1|2|3, 'sortable' => bool ].
	 *                               Priority 3 hides when the table is narrower than 1000px, 2 below 760px.
	 *     @type array  $rows        Each [ 'id', 'label', 'cells' => [ key => escaped HTML ],
	 *                               'actions' => [ [ 'label', 'url', 'primary'?, 'danger'?, 'confirm'?, 'more'? ] ],
	 *                               'detail' => escaped HTML for the drawer, 'class' => string ].
	 *                               Actions with 'more' (and every 'danger' one) go in the row menu.
	 *     @type array  $views       Status views: key => [ label, count ]; '' is "All".
	 *     @type string $view_arg    Query arg the views set. Default 'status'.
	 *     @type string $view        The active view when the caller picks it (e.g. Pending
	 *                               by default while anything is pending). Default: from the URL.
	 *     @type string $search      Search placeholder; empty for no search box.
	 *     @type array  $filters     [ [ 'name', 'label', 'options' => [ value => label ] ] ];
	 *                               'options' => null renders a text box (e.g. Member).
	 *     @type array  $bulk        action => label. Needs $bulk_nonce.
	 *     @type string $bulk_nonce  Nonce action the caller verifies.
	 *     @type array  $empty       [ 'title', 'text', 'icon' ].
	 * }
	 */
	public function render( array $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'id'         => 'listora',
				'count_text' => '',
				'base_url'   => '',
				'state'      => $this->request( 'listora' ),
				'total'      => 0,
				'columns'    => array(),
				'rows'       => array(),
				'views'      => array(),
				'view_arg'   => 'status',
				'view'       => null,
				'search'     => '',
				'filters'    => array(),
				'bulk'       => array(),
				'bulk_nonce' => '',
				'empty'      => array(),
			)
		);

		$state   = $args['state'];
		$id      = sanitize_key( $args['id'] );
		$has_bulk = ! empty( $args['bulk'] ) && '' !== $args['bulk_nonce'] && ! empty( $args['rows'] );

		self::render_views( $args );
		self::render_toolbar( $args );

		$filtered = '' !== $state['s'] || array_filter( $state['filters'] );
		if ( empty( $args['rows'] ) ) {
			self::render_empty( $args, (bool) $filtered );
			return;
		}

		if ( $has_bulk ) {
			echo '<form method="post" class="listora-admin-table-form">';
			wp_nonce_field( $args['bulk_nonce'] );
		}

		echo '<div class="listora-admin-table" id="' . esc_attr( $id ) . '-table">';
		echo '<table class="listora-table">';
		echo '<caption class="screen-reader-text">' . esc_html( self::count_text( $args ) ) . '</caption>';
		echo '<thead><tr>';
		if ( $has_bulk ) {
			echo '<td class="listora-table__check"><input type="checkbox" class="listora-table__select-all" aria-label="' . esc_attr__( 'Select all on this page', 'wb-listora' ) . '"></td>';
		}
		foreach ( $args['columns'] as $key => $col ) {
			echo '<th scope="col"' . self::priority_attr( $col ) . '>' . self::header_cell( $key, $col, $args ) . '</th>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		}
		echo '<th scope="col" class="listora-table__actions-col"><span class="screen-reader-text">' . esc_html__( 'Actions', 'wb-listora' ) . '</span></th>';
		echo '</tr></thead><tbody>';

		foreach ( $args['rows'] as $row ) {
			self::render_row( $row, $args, $has_bulk );
		}

		echo '</tbody></table></div>';

		echo '<div class="listora-table-footer">';
		if ( $has_bulk ) {
			echo '<div class="listora-bulk-actions">';
			echo '<label class="screen-reader-text" for="' . esc_attr( $id ) . '-bulk">' . esc_html__( 'Bulk action', 'wb-listora' ) . '</label>';
			echo '<select id="' . esc_attr( $id ) . '-bulk" name="bulk_action" class="listora-filter-select">';
			echo '<option value="">' . esc_html__( 'Bulk actions', 'wb-listora' ) . '</option>';
			foreach ( $args['bulk'] as $value => $label ) {
				echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
			}
			echo '</select>';
			echo '<button type="submit" class="listora-btn listora-btn--sm">' . esc_html__( 'Apply', 'wb-listora' ) . '</button>';
			echo '</div>';
		}
		self::render_pagination( $args );
		echo '</div>';

		if ( $has_bulk ) {
			echo '</form>';
		}

		// Drawers live outside the table so a <dialog> is never inside a <tr>.
		foreach ( $args['rows'] as $row ) {
			if ( ! empty( $row['detail'] ) ) {
				echo '<dialog class="listora-drawer" id="' . esc_attr( $id . '-detail-' . $row['id'] ) . '" aria-labelledby="' . esc_attr( $id . '-detail-' . $row['id'] ) . '-title">';
				echo '<div class="listora-drawer__head"><h2 class="listora-drawer__title" id="' . esc_attr( $id . '-detail-' . $row['id'] ) . '-title">' . esc_html( (string) ( $row['label'] ?? '' ) ) . '</h2>';
				echo '<button type="button" class="listora-btn listora-btn--sm listora-drawer__close" data-listora-drawer-close>' . esc_html__( 'Close', 'wb-listora' ) . '</button></div>';
				echo '<div class="listora-drawer__body">' . $row['detail'] . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller escapes.
				echo '</dialog>';
			}
		}
	}

	/**
	 * The one stat-card row for admin screens.
	 *
	 * Each card can link to the list it counts, and say what period it
	 * covers ("Last 30 days"): a bare "$28 Total Revenue" left owners
	 * guessing (card 10337183564).
	 *
	 * @param array $cards Each [ 'label', 'value' (int|float|string), 'icon' (Lucide name),
	 *                     'variant' ('accent'|'success'|'warn'|'danger'|''), 'url', 'hint' ].
	 *                     A string value is printed as given (pre-formatted money).
	 */
	public function stat_cards( array $cards ) {
		echo '<div class="listora-stats-grid">';
		foreach ( $cards as $card ) {
			$card  = wp_parse_args(
				$card,
				array(
					'label'   => '',
					'value'   => 0,
					'icon'    => 'bar-chart-3',
					'variant' => '',
					'url'     => '',
					'hint'    => '',
				)
			);
			$value = is_string( $card['value'] ) ? $card['value'] : number_format_i18n( $card['value'], is_float( $card['value'] ) && floor( $card['value'] ) !== $card['value'] ? 1 : 0 );
			$tag   = '' !== $card['url'] ? 'a' : 'div';
			echo '<' . $tag . ' class="listora-stat-card"' . ( 'a' === $tag ? ' href="' . esc_url( $card['url'] ) . '"' : '' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed tag name.
			echo '<div class="listora-stat-card__icon' . ( '' !== $card['variant'] ? ' listora-stat-card__icon--' . esc_attr( $card['variant'] ) : '' ) . '"><i data-lucide="' . esc_attr( $card['icon'] ) . '" aria-hidden="true"></i></div>';
			echo '<div class="listora-stat-card__body">';
			echo '<div class="listora-stat-card__number">' . esc_html( $value ) . '</div>';
			echo '<div class="listora-stat-card__label">' . esc_html( $card['label'] ) . '</div>';
			if ( '' !== $card['hint'] ) {
				echo '<div class="listora-stat-card__hint">' . esc_html( $card['hint'] ) . '</div>';
			}
			echo '</div></' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed tag name.
		}
		echo '</div>';
	}

	/**
	 * The caller's translated total, or a bare number.
	 *
	 * @param array $args Table args.
	 * @return string
	 */
	private static function count_text( array $args ) {
		return '' !== $args['count_text'] ? $args['count_text'] : number_format_i18n( (int) $args['total'] );
	}

	/**
	 * The screen's URL with the current list state, minus $drop, plus $add.
	 *
	 * @param array $args Table args.
	 * @param array $add  Args to set.
	 * @param array $drop Args to remove.
	 * @return string
	 */
	private static function url( array $args, array $add = array(), array $drop = array() ) {
		$state = $args['state'];
		$query = array_merge(
			array(
				's'       => $state['s'],
				'orderby' => $state['orderby'],
				'order'   => strtolower( $state['order'] ),
			),
			$state['filters'],
			array( $args['view_arg'] => self::current_view( $args ) ),
			$add
		);
		foreach ( $drop as $key ) {
			unset( $query[ $key ] );
		}
		$query = array_filter(
			$query,
			static function ( $v ) {
				return '' !== $v && null !== $v;
			}
		);
		return add_query_arg( array_map( 'rawurlencode', $query ), $args['base_url'] );
	}

	/**
	 * The active view key.
	 *
	 * @param array $args Table args.
	 * @return string
	 */
	private static function current_view( array $args ) {
		if ( null !== $args['view'] && isset( $args['views'][ $args['view'] ] ) ) {
			return (string) $args['view'];
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only.
		$view = isset( $_GET[ $args['view_arg'] ] ) ? sanitize_key( wp_unslash( $_GET[ $args['view_arg'] ] ) ) : '';
		return isset( $args['views'][ $view ] ) ? $view : '';
	}

	/**
	 * Status views ("All 50 · Pending 20 · ...").
	 *
	 * @param array $args Table args.
	 */
	private static function render_views( array $args ) {
		if ( empty( $args['views'] ) ) {
			return;
		}
		$current = self::current_view( $args );
		echo '<nav class="listora-filter-tabs" aria-label="' . esc_attr__( 'Filter by status', 'wb-listora' ) . '">';
		foreach ( $args['views'] as $key => $view ) {
			$active = $key === $current;
			echo '<a class="listora-filter-tab' . ( $active ? ' is-active' : '' ) . '" href="' . esc_url( self::url( $args, array( $args['view_arg'] => $key ), array( 'paged' ) ) ) . '"' . ( $active ? ' aria-current="page"' : '' ) . '>';
			echo esc_html( $view[0] ) . '<span class="listora-filter-tab__count">' . esc_html( number_format_i18n( (int) $view[1] ) ) . '</span></a>';
		}
		echo '</nav>';
	}

	/**
	 * Search box and filters, one GET form.
	 *
	 * @param array $args Table args.
	 */
	private static function render_toolbar( array $args ) {
		if ( '' === $args['search'] && empty( $args['filters'] ) ) {
			return;
		}
		$state = $args['state'];
		$id    = sanitize_key( $args['id'] );
		$query = array();
		wp_parse_str( (string) wp_parse_url( $args['base_url'], PHP_URL_QUERY ), $query );

		echo '<form method="get" class="listora-filter-bar" role="search">';
		foreach ( $query as $key => $value ) {
			echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
		}
		$view = self::current_view( $args );
		if ( '' !== $view ) {
			echo '<input type="hidden" name="' . esc_attr( $args['view_arg'] ) . '" value="' . esc_attr( $view ) . '">';
		}
		if ( '' !== $args['search'] ) {
			echo '<label class="screen-reader-text" for="' . esc_attr( $id ) . '-search">' . esc_html( $args['search'] ) . '</label>';
			echo '<input type="search" id="' . esc_attr( $id ) . '-search" name="s" class="listora-search-input" placeholder="' . esc_attr( $args['search'] ) . '" value="' . esc_attr( $state['s'] ) . '">';
		}
		foreach ( $args['filters'] as $filter ) {
			$name  = $filter['name'];
			$value = $state['filters'][ $name ] ?? '';
			echo '<label class="screen-reader-text" for="' . esc_attr( $id . '-' . $name ) . '">' . esc_html( $filter['label'] ) . '</label>';
			if ( null === ( $filter['options'] ?? null ) ) {
				echo '<input type="search" id="' . esc_attr( $id . '-' . $name ) . '" name="' . esc_attr( $name ) . '" class="listora-search-input listora-search-input--narrow" placeholder="' . esc_attr( $filter['label'] ) . '" value="' . esc_attr( $value ) . '">';
				continue;
			}
			echo '<select id="' . esc_attr( $id . '-' . $name ) . '" name="' . esc_attr( $name ) . '" class="listora-filter-select">';
			echo '<option value="">' . esc_html( $filter['label'] ) . '</option>';
			foreach ( $filter['options'] as $opt_value => $opt_label ) {
				echo '<option value="' . esc_attr( (string) $opt_value ) . '"' . selected( $value, (string) $opt_value, false ) . '>' . esc_html( $opt_label ) . '</option>';
			}
			echo '</select>';
		}
		echo '<button type="submit" class="listora-btn listora-btn--sm">' . esc_html( '' !== $args['search'] && empty( $args['filters'] ) ? __( 'Search', 'wb-listora' ) : __( 'Apply', 'wb-listora' ) ) . '</button>';
		if ( '' !== $state['s'] || array_filter( $state['filters'] ) ) {
			echo '<a class="listora-action-link" href="' . esc_url( self::url( $args, array(), array_merge( array( 's', 'paged' ), array_keys( $state['filters'] ) ) ) ) . '">' . esc_html__( 'Clear', 'wb-listora' ) . '</a>';
		}
		echo '</form>';
	}

	/**
	 * Column header, a sort link when sortable.
	 *
	 * @param string $key  Column key.
	 * @param array  $col  Column.
	 * @param array  $args Table args.
	 * @return string Escaped HTML.
	 */
	private static function header_cell( $key, array $col, array $args ) {
		$label = esc_html( $col['label'] );
		if ( empty( $col['sortable'] ) ) {
			return $label;
		}
		$state   = $args['state'];
		$current = $state['orderby'] === $key;
		$next    = $current && 'DESC' === $state['order'] ? 'asc' : 'desc';
		$arrow   = $current ? ( 'DESC' === $state['order'] ? ' ↓' : ' ↑' ) : '';
		$sr      = $current ? ( 'DESC' === $state['order'] ? __( 'sorted descending', 'wb-listora' ) : __( 'sorted ascending', 'wb-listora' ) ) : '';
		return '<a class="listora-table__sort" href="' . esc_url( self::url( $args, array( 'orderby' => $key, 'order' => $next ), array( 'paged' ) ) ) . '">' . $label . '<span aria-hidden="true">' . esc_html( $arrow ) . '</span>' . ( $sr ? '<span class="screen-reader-text">, ' . esc_html( $sr ) . '</span>' : '' ) . '</a>';
	}

	/**
	 * Column priority attribute.
	 *
	 * @param array $col Column.
	 * @return string Escaped attribute string.
	 */
	private static function priority_attr( array $col ) {
		$priority = (int) ( $col['priority'] ?? 1 );
		return $priority > 1 ? ' data-priority="' . esc_attr( (string) $priority ) . '"' : '';
	}

	/**
	 * One row.
	 *
	 * @param array $row      Row.
	 * @param array $args     Table args.
	 * @param bool  $has_bulk Whether rows get a checkbox.
	 */
	private static function render_row( array $row, array $args, $has_bulk ) {
		$id    = sanitize_key( $args['id'] );
		$label = (string) ( $row['label'] ?? '' );
		echo '<tr' . ( ! empty( $row['class'] ) ? ' class="' . esc_attr( $row['class'] ) . '"' : '' ) . '>';
		if ( $has_bulk ) {
			/* translators: %s: row label, such as a listing title. */
			echo '<th scope="row" class="listora-table__check"><input type="checkbox" name="ids[]" value="' . esc_attr( (string) $row['id'] ) . '" aria-label="' . esc_attr( sprintf( __( 'Select %s', 'wb-listora' ), $label ) ) . '"></th>';
		}
		foreach ( $args['columns'] as $key => $col ) {
			echo '<td data-label="' . esc_attr( $col['label'] ) . '"' . self::priority_attr( $col ) . '>' . ( $row['cells'][ $key ] ?? '' ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller escapes cells.
		}

		$inline = array();
		$more   = array();
		foreach ( (array) ( $row['actions'] ?? array() ) as $action ) {
			if ( ! empty( $action['more'] ) || ! empty( $action['danger'] ) ) {
				$more[] = $action;
			} else {
				$inline[] = $action;
			}
		}
		if ( ! empty( $row['detail'] ) ) {
			array_unshift(
				$inline,
				array(
					'label'  => __( 'Details', 'wb-listora' ),
					'drawer' => $id . '-detail-' . $row['id'],
				)
			);
		}

		echo '<td class="listora-table__actions"><div class="listora-row-actions">';
		foreach ( $inline as $action ) {
			echo self::action_link( $action, $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built escaped.
		}
		if ( $more ) {
			/* translators: %s: row label. */
			echo '<details class="listora-row-menu"><summary class="listora-row-menu__toggle" aria-label="' . esc_attr( sprintf( __( 'More actions for %s', 'wb-listora' ), $label ) ) . '"><span aria-hidden="true">&#8943;</span></summary><div class="listora-row-menu__list">';
			foreach ( $more as $action ) {
				echo self::action_link( $action, $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built escaped.
			}
			echo '</div></details>';
		}
		echo '</div></td></tr>';
	}

	/**
	 * One row action.
	 *
	 * @param array  $action Action.
	 * @param string $label  Row label, for the confirm title.
	 * @return string Escaped HTML.
	 */
	private static function action_link( array $action, $label ) {
		$class = 'listora-action-link';
		if ( ! empty( $action['primary'] ) ) {
			$class .= ' listora-action-link--primary';
		}
		if ( ! empty( $action['danger'] ) ) {
			$class .= ' listora-action-link--danger';
		}
		if ( ! empty( $action['drawer'] ) ) {
			return '<button type="button" class="' . esc_attr( $class ) . '" data-listora-drawer="' . esc_attr( $action['drawer'] ) . '" aria-haspopup="dialog">' . esc_html( $action['label'] ) . '</button>';
		}
		$attrs = '';
		if ( ! empty( $action['confirm'] ) ) {
			$attrs .= ' data-confirm-title="' . esc_attr( $action['label'] . ': ' . $label ) . '" data-confirm-message="' . esc_attr( $action['confirm'] ) . '" data-confirm-label="' . esc_attr( $action['label'] ) . '"';
		}
		foreach ( (array) ( $action['attrs'] ?? array() ) as $name => $value ) {
			$attrs .= ' ' . esc_attr( $name ) . '="' . esc_attr( (string) $value ) . '"';
		}
		return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $action['url'] ) . '"' . $attrs . '>' . esc_html( $action['label'] ) . '</a>';
	}

	/**
	 * Numbered pagination, the total, and the page-size picker.
	 *
	 * @param array $args Table args.
	 */
	private static function render_pagination( array $args ) {
		$state = $args['state'];
		$pages = (int) ceil( $args['total'] / max( 1, $state['per_page'] ) );

		echo '<div class="listora-pagination-bar">';
		echo '<span class="listora-pagination__count">' . esc_html( self::count_text( $args ) ) . '</span>';
		if ( $pages > 1 ) {
			$links = paginate_links(
				array(
					'base'      => str_replace( '999999999', '%#%', self::url( $args, array( 'paged' => 999999999 ) ) ),
					'format'    => '',
					'current'   => $state['paged'],
					'total'     => $pages,
					'prev_text' => '<span aria-hidden="true">&lsaquo;</span><span class="screen-reader-text">' . esc_html__( 'Previous page', 'wb-listora' ) . '</span>',
					'next_text' => '<span aria-hidden="true">&rsaquo;</span><span class="screen-reader-text">' . esc_html__( 'Next page', 'wb-listora' ) . '</span>',
					'mid_size'  => 1,
				)
			);
			echo '<nav class="listora-pagination" aria-label="' . esc_attr__( 'Pages', 'wb-listora' ) . '">' . wp_kses_post( (string) $links ) . '</nav>';
		}
		if ( $args['total'] > self::PER_PAGE_CHOICES[0] ) {
			echo '<span class="listora-per-page">';
			foreach ( self::PER_PAGE_CHOICES as $size ) {
				if ( $size === $state['per_page'] ) {
					/* translators: %d: rows per page. */
					echo '<span class="listora-per-page__current" aria-current="true">' . esc_html( sprintf( __( '%d per page', 'wb-listora' ), $size ) ) . '</span>';
				} else {
					echo '<a class="listora-action-link" href="' . esc_url( self::url( $args, array( 'per_page' => $size ), array( 'paged' ) ) ) . '">' . esc_html( (string) $size ) . '</a>';
				}
			}
			echo '</span>';
		}
		echo '</div>';
	}

	/**
	 * Empty state: nothing yet, or nothing matching the filters.
	 *
	 * @param array $args     Table args.
	 * @param bool  $filtered Whether a search or filter is active.
	 */
	private static function render_empty( array $args, $filtered ) {
		$empty = wp_parse_args(
			$args['empty'],
			array(
				'title' => __( 'Nothing here yet', 'wb-listora' ),
				'text'  => '',
				'icon'  => 'inbox',
			)
		);
		echo '<div class="listora-empty-state">';
		echo '<div class="listora-empty-state__icon"><i data-lucide="' . esc_attr( $filtered ? 'search-x' : $empty['icon'] ) . '"></i></div>';
		if ( $filtered ) {
			echo '<p class="listora-empty-state__title">' . esc_html__( 'Nothing matches these filters', 'wb-listora' ) . '</p>';
			echo '<p class="listora-empty-state__desc"><a href="' . esc_url( self::url( $args, array(), array_merge( array( 's', 'paged' ), array_keys( $args['state']['filters'] ) ) ) ) . '">' . esc_html__( 'Clear filters', 'wb-listora' ) . '</a></p>';
		} else {
			echo '<p class="listora-empty-state__title">' . esc_html( $empty['title'] ) . '</p>';
			if ( '' !== $empty['text'] ) {
				echo '<p class="listora-empty-state__desc">' . esc_html( $empty['text'] ) . '</p>';
			}
		}
		echo '</div>';
	}
}
