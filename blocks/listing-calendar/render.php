<?php
/**
 * Listing Calendar block — modern CSS Grid month view.
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'listora-base' );
wp_enqueue_script( 'listora-interactivity-store' );

$unique_id    = sanitize_html_class( (string) ( $attributes['uniqueId'] ?? '' ) );
$listing_type = $attributes['listingType'] ?? 'event';

// Current month/year — read from URL params for server-side rendering.
// absint() sanitizes; no nonce needed for read-only display params.
$year  = isset( $_GET['cal_year'] ) ? absint( $_GET['cal_year'] ) : (int) current_time( 'Y' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display parameter, no state change.
$month = isset( $_GET['cal_month'] ) ? absint( $_GET['cal_month'] ) : (int) current_time( 'n' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display parameter, no state change.

// Clamp month to valid range to prevent mktime() from silently rolling over.
$month = max( 1, min( 12, $month ) );
// Clamp year to a sane range.
$year = max( 2000, min( 2100, $year ) );

// Use gmdate() throughout so calculations are timezone-neutral.
// wp_date() is used only for display strings (it applies WP timezone).
$first_day     = (int) gmmktime( 0, 0, 0, $month, 1, $year );
$days_in_month = (int) gmdate( 't', $first_day );
$start_dow     = (int) gmdate( 'w', $first_day ); // 0 = Sunday.
// Labels come from a date IN the site's timezone: wp_date() of midnight UTC
// is the previous evening west of UTC, which would label the wrong month.
// Noon keeps DST changeovers away from the date.
$month_name = wp_date( 'F Y', ( new \DateTimeImmutable( sprintf( '%04d-%02d-01 12:00', $year, $month ), wp_timezone() ) )->getTimestamp() );

// Date range for the query. Stored dates are 'Y-m-d' or 'Y-m-d H:i' strings,
// so the window is [first day, first day of next month): BETWEEN first AND
// last day dropped anything at a time of day on the last day.
$start_date     = gmdate( 'Y-m-d', $first_day );
$end_date       = gmdate( 'Y-m-d', (int) gmmktime( 0, 0, 0, $month + 1, 0, $year ) );
$next_month_day = gmdate( 'Y-m-d', (int) gmmktime( 0, 0, 0, $month + 1, 1, $year ) );


// ─── Phase 1: Events that overlap this month. ───
// A multi-day event starts on or before the month's end and ends on or after
// its start; one with no end date is a single-day event.
$events = \WBListora\Core\Recurrence::events_overlapping( $listing_type, $start_date, $next_month_day );

// Normalise: get_results() returns an empty array on no results, never null.
if ( ! is_array( $events ) ) {
	$events = array();
}

// ─── Phase 2: Recurring events — fetch recurring listings whose series can
// intersect the displayed window. ───
//
// Big-site bound: a series only produces occurrences on or after its base
// `_listora_start_date`, so exclude series starting after the window end,
// and those whose `_listora_recurrence_end` is before the window start
// (LEFT JOIN keeps series with no end). `Recurrence::get_occurrences()`
// still filters per day. MAX_RECURRING_CANDIDATES is a safety ceiling
// mirroring Search_Engine::MAX_PHASE_1_CANDIDATES.
$max_recurring_candidates = 500;

$recurring_listings = \WBListora\Core\Recurrence::recurring_series( $listing_type, $start_date, $end_date, $max_recurring_candidates );

if ( ! is_array( $recurring_listings ) ) {
	$recurring_listings = array();
}

// Cap-hit warning — only when actively debugging. If we filled the entire cap,
// some in-window recurring series were dropped and the calendar is incomplete.
if ( count( $recurring_listings ) >= $max_recurring_candidates ) {
	wb_listora_log(
		sprintf(
			'Calendar: recurring-event query hit the %d-row safety cap for listing type "%s" (window %s to %s). Some recurring occurrences may be missing from this month. Narrow the calendar listing type or split recurring events.',
			$max_recurring_candidates,
			$listing_type,
			$start_date,
			$end_date
		)
	);
}

// One entry per event per day it covers inside this month: a multi-day event
// appears on each of its days. Also the (listing, date) set that keeps the
// recurring pass below from adding a day twice.
$existing_pairs = array();
$event_days     = array();
foreach ( $events as $event ) {
	$first = max( substr( (string) $event['start_date'], 0, 10 ), $start_date );
	$last  = min( substr( (string) ( '' !== (string) $event['end_date'] ? $event['end_date'] : $event['start_date'] ), 0, 10 ), $end_date );
	// An unparseable date gives no days rather than a loop from 1970.
	$from = strtotime( $first . ' UTC' );
	$to   = strtotime( $last . ' UTC' );
	if ( false === $from || false === $to ) {
		continue;
	}
	for ( $d = $from; $d <= $to; $d += DAY_IN_SECONDS ) {
		$ymd          = gmdate( 'Y-m-d', $d );
		$event_days[] = array_merge( $event, array( 'start_date' => $ymd ) );
		$existing_pairs[ $event['ID'] . '_' . $ymd ] = true;
	}
}
$events = $event_days;

// Generate virtual occurrences for recurring listings within this month.
foreach ( $recurring_listings as $rec_listing ) {
	$occurrences = \WBListora\Core\Recurrence::get_occurrences(
		(int) $rec_listing['ID'],
		$start_date,
		$end_date
	);

	foreach ( $occurrences as $occ_date ) {
		$pair_key = $rec_listing['ID'] . '_' . $occ_date;

		// Skip if we already have this listing on this date from the original query.
		if ( isset( $existing_pairs[ $pair_key ] ) ) {
			continue;
		}

		$events[] = array(
			'ID'         => $rec_listing['ID'],
			'post_title' => $rec_listing['post_title'],
			'start_date' => $occ_date,
		);

		$existing_pairs[ $pair_key ] = true;
	}
}

/** Hook: Filter the events array before grouping into calendar days. @since 1.1.0 */
// Enforce the item shape after the filter — a listener returning scalar rows
// must not fatal the start_date/ID offset reads (same guard pattern as
// wb_listora_submission_steps in listing-submission/render.php).
$events = array_values( array_filter( (array) apply_filters( 'wb_listora_calendar_events', $events, $attributes ), 'is_array' ) );

// Group events by day-of-month.
$events_by_day = array();
foreach ( $events as $event ) {
	// Use gmdate() to parse the stored Y-m-d value consistently.
	$day    = (int) gmdate( 'j', strtotime( $event['start_date'] ) );
	$cats   = get_the_terms( $event['ID'], 'listora_listing_cat' );
	$cat_id = $cats && ! is_wp_error( $cats ) ? $cats[0]->term_id : 0;
	// A listing with no category, or a category with no colour, takes its type's.
	$event['color']          = wb_listora_category_color( $cat_id, \WBListora\Core\Listing_Type_Registry::instance()->get_for_post( $event['ID'] ) );
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

$context = (string) wp_json_encode(
	array(
		'calendarMonth' => $month,
		'calendarYear'  => $year,
		'listingType'   => $listing_type,
	)
);

$visibility_classes = \WBListora\Block_CSS::visibility_classes( $attributes );
$block_classes      = 'listora-block' . ( $unique_id ? ' listora-block-' . $unique_id : '' ) . ( $visibility_classes ? ' ' . $visibility_classes : '' );

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class'               => 'listora-calendar ' . $block_classes,
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

/** Hook: Fires before the calendar wrapper is rendered. @since 1.1.0 */
do_action( 'wb_listora_before_calendar', $attributes );

echo \WBListora\Block_CSS::render( $unique_id, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block_CSS::render builds the style tag and strips '<' from it.

// ─── Assemble $view_data for templates ───
$view_data = array(
	'wrapper_attrs' => $wrapper_attrs,
	'month_name'    => $month_name,
	'month'         => $month,
	'year'          => $year,
	'days_in_month' => $days_in_month,
	'start_dow'     => $start_dow,
	'day_names'     => $day_names,
	'events_by_day' => $events_by_day,
	'today'         => $today,
	'today_month'   => $today_month,
	'today_year'    => $today_year,
);

// Self-reference for sub-templates.
$view_data['view_data'] = $view_data;

wb_listora_get_template( 'blocks/listing-calendar/calendar.php', $view_data );

/** Hook: Fires after the calendar wrapper is closed. @since 1.1.0 */
do_action( 'wb_listora_after_calendar', $attributes );
