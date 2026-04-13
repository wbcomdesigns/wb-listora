<?php
/**
 * Listing Calendar block — modern CSS Grid month view.
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'listora-shared' );
wp_enqueue_script( 'listora-interactivity-store' );

$unique_id    = $attributes['uniqueId'] ?? '';
$listing_type = $attributes['listingType'] ?? 'event';

// Current month/year — read from URL params for server-side rendering.
// absint() sanitizes; no nonce needed for read-only display params.
$year  = isset( $_GET['cal_year'] ) ? absint( $_GET['cal_year'] ) : (int) current_time( 'Y' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$month = isset( $_GET['cal_month'] ) ? absint( $_GET['cal_month'] ) : (int) current_time( 'n' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

// Clamp month to valid range to prevent mktime() from silently rolling over.
$month = max( 1, min( 12, $month ) );
// Clamp year to a sane range.
$year = max( 2000, min( 2100, $year ) );

// Use gmdate() throughout so calculations are timezone-neutral.
// wp_date() is used only for display strings (it applies WP timezone).
$first_day     = gmmktime( 0, 0, 0, $month, 1, $year );
$days_in_month = (int) gmdate( 't', $first_day );
$start_dow     = (int) gmdate( 'w', $first_day ); // 0 = Sunday.
$month_name    = wp_date( 'F Y', $first_day );

// Date range for the query — inclusive of the full last day.
$start_date = gmdate( 'Y-m-d', $first_day );
// Day 0 of month+1 = last day of current month (valid PHP trick).
$end_date = gmdate( 'Y-m-d', gmmktime( 0, 0, 0, $month + 1, 0, $year ) );

global $wpdb;

// ─── Phase 1: Non-recurring events (original start_date in this month). ───
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

// ─── Phase 2: Recurring events — fetch all recurring listings of this type. ───
$recurring_listings = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT p.ID, p.post_title, pm.meta_value as start_date
	FROM {$wpdb->posts} p
	INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_listora_start_date'
	INNER JOIN {$wpdb->postmeta} pm_rec ON p.ID = pm_rec.post_id AND pm_rec.meta_key = '_listora_recurrence_type'
	INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
	INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
	INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
	WHERE p.post_type = 'listora_listing'
	AND p.post_status = 'publish'
	AND tt.taxonomy = 'listora_listing_type'
	AND t.slug = %s
	AND pm_rec.meta_value IN ('daily', 'weekly', 'monthly')
	ORDER BY pm.meta_value ASC",
		$listing_type
	),
	ARRAY_A
);

if ( ! is_array( $recurring_listings ) ) {
	$recurring_listings = array();
}

// Build a set of existing (listing_id, date) pairs to avoid duplicates.
$existing_pairs = array();
foreach ( $events as $event ) {
	$existing_pairs[ $event['ID'] . '_' . gmdate( 'Y-m-d', strtotime( $event['start_date'] ) ) ] = true;
}

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
$events = apply_filters( 'wb_listora_calendar_events', $events, $attributes );

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

echo \WBListora\Block_CSS::render( $unique_id, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

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
