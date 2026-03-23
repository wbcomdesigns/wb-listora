<?php
/**
 * Listing Calendar block — modern CSS Grid month view.
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'listora-shared' );

$listing_type = $attributes['listingType'] ?? 'event';

// Current month/year — read from URL params for server-side rendering.
// absint() sanitizes; no nonce needed for read-only display params.
$year  = isset( $_GET['cal_year'] ) ? absint( $_GET['cal_year'] ) : (int) current_time( 'Y' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$month = isset( $_GET['cal_month'] ) ? absint( $_GET['cal_month'] ) : (int) current_time( 'n' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

// Clamp month to valid range to prevent mktime() from silently rolling over.
$month = max( 1, min( 12, $month ) );
// Clamp year to a sane range.
$year  = max( 2000, min( 2100, $year ) );

// Use gmdate() throughout so calculations are timezone-neutral.
// wp_date() is used only for display strings (it applies WP timezone).
$first_day     = gmmktime( 0, 0, 0, $month, 1, $year );
$days_in_month = (int) gmdate( 't', $first_day );
$start_dow     = (int) gmdate( 'w', $first_day ); // 0 = Sunday.
$month_name    = wp_date( 'F Y', $first_day );

// Date range for the query — inclusive of the full last day.
$start_date = gmdate( 'Y-m-d', $first_day );
// Day 0 of month+1 = last day of current month (valid PHP trick).
$end_date   = gmdate( 'Y-m-d', gmmktime( 0, 0, 0, $month + 1, 0, $year ) );

global $wpdb;

$events = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT p.ID, p.post_title, pm.meta_value as start_date
	FROM {$wpdb->posts} p
	INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_listora_start_date'
	INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
	INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
	INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
	WHERE p.post_type = 'listora_listing'
	AND p.post_status = 'publish'
	AND tt.taxonomy = 'listora_listing_type'
	AND t.slug = %s
	AND pm.meta_value BETWEEN %s AND %s
	ORDER BY pm.meta_value ASC",
		$listing_type,
		$start_date,
		$end_date
	),
	ARRAY_A
);

// Normalise: get_results() returns an empty array on no results, never null.
if ( ! is_array( $events ) ) {
	$events = array();
}

// Group events by day-of-month.
$events_by_day = array();
foreach ( $events as $event ) {
	// Use gmdate() to parse the stored Y-m-d value consistently.
	$day         = (int) gmdate( 'j', strtotime( $event['start_date'] ) );
	$cats        = get_the_terms( $event['ID'], 'listora_listing_cat' );
	$event_color = '';
	if ( $cats && ! is_wp_error( $cats ) ) {
		$event_color = get_term_meta( $cats[0]->term_id, '_listora_color', true );
	}
	$event['color']          = $event_color ? $event_color : '';
	$events_by_day[ $day ][] = $event;
}

// Build prev/next month values.
$prev_month = $month - 1;
$prev_year  = $year;
if ( $prev_month < 1 ) {
	$prev_month = 12;
	--$prev_year;
}
$next_month = $month + 1;
$next_year  = $year;
if ( $next_month > 12 ) {
	$next_month = 1;
	++$next_year;
}

$context = wp_json_encode(
	array(
		'calendarMonth' => $month,
		'calendarYear'  => $year,
		'listingType'   => $listing_type,
	)
);

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class'               => 'listora-calendar',
		'data-wp-interactive' => 'listora/directory',
		// get_block_wrapper_attributes() HTML-escapes all attribute values automatically.
		'data-wp-context'     => $context,
	)
);

$day_names = array(
	__( 'Sun', 'wb-listora' ),
	__( 'Mon', 'wb-listora' ),
	__( 'Tue', 'wb-listora' ),
	__( 'Wed', 'wb-listora' ),
	__( 'Thu', 'wb-listora' ),
	__( 'Fri', 'wb-listora' ),
	__( 'Sat', 'wb-listora' ),
);

$today       = (int) current_time( 'j' );
$today_month = (int) current_time( 'n' );
$today_year  = (int) current_time( 'Y' );
?>

<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="listora-calendar__header">
		<h2 class="listora-calendar__month"><?php echo esc_html( $month_name ); ?></h2>
		<div class="listora-calendar__nav-arrows">
			<button
				type="button"
				class="listora-calendar__nav-btn"
				data-wp-on--click="actions.navigateMonth"
				data-wp-context='<?php echo esc_attr( wp_json_encode( array( 'direction' => 'prev' ) ) ); ?>'
				aria-label="<?php esc_attr_e( 'Previous month', 'wb-listora' ); ?>"
			>
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
			</button>
			<button
				type="button"
				class="listora-calendar__nav-btn"
				data-wp-on--click="actions.navigateMonth"
				data-wp-context='<?php echo esc_attr( wp_json_encode( array( 'direction' => 'next' ) ) ); ?>'
				aria-label="<?php esc_attr_e( 'Next month', 'wb-listora' ); ?>"
			>
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
			</button>
		</div>
	</div>

	<div class="listora-calendar__grid-wrap" role="grid" aria-label="<?php echo esc_attr( $month_name ); ?>">
		<div class="listora-calendar__day-headers" role="row">
			<?php foreach ( $day_names as $dn ) : ?>
			<div class="listora-calendar__day-header" role="columnheader" aria-label="<?php echo esc_attr( $dn ); ?>"><?php echo esc_html( $dn ); ?></div>
			<?php endforeach; ?>
		</div>

		<div class="listora-calendar__grid">
			<?php
			// Empty cells before the first day of the month.
			for ( $i = 0; $i < $start_dow; $i++ ) {
				echo '<div class="listora-calendar__cell listora-calendar__cell--empty" role="gridcell" aria-hidden="true"></div>';
			}

			for ( $day = 1; $day <= $days_in_month; $day++ ) {
				$is_today   = ( $day === $today && $month === $today_month && $year === $today_year );
				$has_events = isset( $events_by_day[ $day ] );
				$class      = 'listora-calendar__cell';
				if ( $is_today ) {
					$class .= ' listora-calendar__cell--today';
				}
				if ( $has_events ) {
					$class .= ' listora-calendar__cell--has-events';
				}

				// Build date label for accessibility (e.g. "March 15").
				$cell_date_label = wp_date( 'F j', gmmktime( 0, 0, 0, $month, $day, $year ) );
				$aria_label      = $is_today
					/* translators: %s: full date string e.g. "March 15" */
					? sprintf( __( '%s, today', 'wb-listora' ), $cell_date_label )
					: $cell_date_label;

				printf( '<div class="%s" role="gridcell" aria-label="%s">', esc_attr( $class ), esc_attr( $aria_label ) );
				echo '<span class="listora-calendar__day-num" aria-hidden="true">' . esc_html( $day ) . '</span>';

				if ( $has_events ) {
					echo '<div class="listora-calendar__events">';
					foreach ( array_slice( $events_by_day[ $day ], 0, 3 ) as $evt ) {
						$evt_style    = $evt['color'] ? ' style="--event-color: ' . esc_attr( $evt['color'] ) . '"' : '';
						$evt_context  = wp_json_encode(
							array(
								'eventId'    => absint( $evt['ID'] ),
								'eventTitle' => $evt['post_title'],
								'eventUrl'   => get_permalink( absint( $evt['ID'] ) ),
								'eventDate'  => $evt['start_date'],
							)
						);
						printf(
							'<span class="listora-calendar__event"%s data-wp-on--click="actions.showEventPopover" data-wp-context=\'%s\' title="%s">%s</span>',
							$evt_style, // Pre-built with esc_attr() above; %s does not re-escape.
							esc_attr( $evt_context ),
							esc_attr( $evt['post_title'] ),
							esc_html( wp_trim_words( $evt['post_title'], 3, '...' ) )
						);
					}
					$overflow = count( $events_by_day[ $day ] ) - 3;
					if ( $overflow > 0 ) {
						printf(
							'<span class="listora-calendar__more" aria-label="%s">+%d</span>',
							/* translators: %d: number of additional events */
							esc_attr( sprintf( __( '%d more events', 'wb-listora' ), $overflow ) ),
							(int) $overflow
						);
					}
					echo '</div>';
				}

				echo '</div>';
			}

			// Fill trailing empty cells to complete the last row.
			$total_cells = $start_dow + $days_in_month;
			$remaining   = ( 7 - ( $total_cells % 7 ) ) % 7;
			for ( $i = 0; $i < $remaining; $i++ ) {
				echo '<div class="listora-calendar__cell listora-calendar__cell--empty" role="gridcell" aria-hidden="true"></div>';
			}
			?>
		</div>
	</div>

	<?php // Event popover container (populated and positioned via Interactivity API). ?>
	<div
		class="listora-calendar__popover"
		hidden
		data-wp-bind--hidden="!state.showEventPopover"
		role="dialog"
		aria-modal="false"
		aria-label="<?php esc_attr_e( 'Event details', 'wb-listora' ); ?>"
	>
		<h4 class="listora-calendar__popover-title" data-wp-text="state.eventPopoverTitle"></h4>
		<div class="listora-calendar__popover-meta">
			<span data-wp-text="state.eventPopoverDate"></span>
		</div>
		<a class="listora-calendar__popover-link" data-wp-bind--href="state.eventPopoverUrl"><?php esc_html_e( 'View details', 'wb-listora' ); ?> &rarr;</a>
	</div>
</div>
