<?php
/**
 * Listing Calendar block — modern CSS Grid month view.
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'listora-shared' );

$listing_type = $attributes['listingType'] ?? 'event';

// Current month/year.
$year  = isset( $_GET['cal_year'] ) ? absint( $_GET['cal_year'] ) : (int) current_time( 'Y' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$month = isset( $_GET['cal_month'] ) ? absint( $_GET['cal_month'] ) : (int) current_time( 'n' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$first_day     = mktime( 0, 0, 0, $month, 1, $year );
$days_in_month = (int) date( 't', $first_day );
$start_dow     = (int) date( 'w', $first_day ); // 0=Sun.
$month_name    = wp_date( 'F Y', $first_day );

// Get events for this month.
$start_date = gmdate( 'Y-m-d', $first_day );
$end_date   = gmdate( 'Y-m-d', mktime( 0, 0, 0, $month + 1, 0, $year ) );

global $wpdb;
$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

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

// Group events by day.
$events_by_day = array();
foreach ( $events as $event ) {
	$day = (int) date( 'j', strtotime( $event['start_date'] ) );
	// Get event category color.
	$cats         = get_the_terms( $event['ID'], 'listora_listing_cat' );
	$event_color  = '';
	if ( $cats && ! is_wp_error( $cats ) ) {
		$event_color = get_term_meta( $cats[0]->term_id, '_listora_color', true );
	}
	$event['color']          = $event_color ?: '';
	$events_by_day[ $day ][] = $event;
}

// Navigation.
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
				data-wp-context='<?php echo wp_json_encode( array( 'direction' => 'prev' ) ); ?>'
				aria-label="<?php esc_attr_e( 'Previous month', 'wb-listora' ); ?>"
			>
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
			</button>
			<button
				type="button"
				class="listora-calendar__nav-btn"
				data-wp-on--click="actions.navigateMonth"
				data-wp-context='<?php echo wp_json_encode( array( 'direction' => 'next' ) ); ?>'
				aria-label="<?php esc_attr_e( 'Next month', 'wb-listora' ); ?>"
			>
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
			</button>
		</div>
	</div>

	<div class="listora-calendar__grid-wrap" role="grid" aria-label="<?php echo esc_attr( $month_name ); ?>">
		<div class="listora-calendar__day-headers" role="row">
			<?php foreach ( $day_names as $dn ) : ?>
			<div class="listora-calendar__day-header" role="columnheader"><?php echo esc_html( $dn ); ?></div>
			<?php endforeach; ?>
		</div>

		<div class="listora-calendar__grid">
			<?php
			// Empty cells before first day.
			for ( $i = 0; $i < $start_dow; $i++ ) {
				echo '<div class="listora-calendar__cell listora-calendar__cell--empty"></div>';
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

				echo '<div class="' . esc_attr( $class ) . '" role="gridcell">';
				echo '<span class="listora-calendar__day-num">' . esc_html( $day ) . '</span>';

				if ( $has_events ) {
					echo '<div class="listora-calendar__events">';
					foreach ( array_slice( $events_by_day[ $day ], 0, 3 ) as $evt ) {
						$evt_style = $evt['color'] ? 'style="--event-color: ' . esc_attr( $evt['color'] ) . '"' : '';
						printf(
							'<span class="listora-calendar__event" %s data-wp-on--click="actions.showEventPopover" data-wp-context=\'%s\' title="%s">%s</span>',
							$evt_style,
							esc_attr( wp_json_encode( array(
								'eventId'    => $evt['ID'],
								'eventTitle' => $evt['post_title'],
								'eventUrl'   => get_permalink( $evt['ID'] ),
								'eventDate'  => $evt['start_date'],
							) ) ),
							esc_attr( $evt['post_title'] ),
							esc_html( wp_trim_words( $evt['post_title'], 3, '...' ) )
						);
					}
					if ( count( $events_by_day[ $day ] ) > 3 ) {
						printf( '<span class="listora-calendar__more">+%d</span>', count( $events_by_day[ $day ] ) - 3 );
					}
					echo '</div>';
				}

				echo '</div>';
			}

			// Fill remaining cells.
			$total_cells = $start_dow + $days_in_month;
			$remaining   = ( 7 - ( $total_cells % 7 ) ) % 7;
			for ( $i = 0; $i < $remaining; $i++ ) {
				echo '<div class="listora-calendar__cell listora-calendar__cell--empty"></div>';
			}
			?>
		</div>
	</div>

	<?php // Event popover container (positioned via JS). ?>
	<div class="listora-calendar__popover" hidden data-wp-bind--hidden="!state.showEventPopover">
		<h4 class="listora-calendar__popover-title" data-wp-text="state.eventPopoverTitle"></h4>
		<div class="listora-calendar__popover-meta">
			<span data-wp-text="state.eventPopoverDate"></span>
		</div>
		<a class="listora-calendar__popover-link" data-wp-bind--href="state.eventPopoverUrl"><?php esc_html_e( 'View details', 'wb-listora' ); ?> &rarr;</a>
	</div>
</div>
