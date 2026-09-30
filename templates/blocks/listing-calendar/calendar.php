<?php
/**
 * Listing Calendar block — month-view grid template.
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/listing-calendar/calendar.php
 *
 * @package WBListora
 *
 * @var string $wrapper_attrs Block wrapper attributes string.
 * @var string $month_name    Formatted month and year label (e.g. "March 2026").
 * @var int    $month         Current month number (1-12).
 * @var int    $year          Current year.
 * @var int    $days_in_month Number of days in the current month.
 * @var int    $start_dow     Day-of-week for the 1st (0 = Sunday).
 * @var array  $day_names     Localized abbreviated day names (Sun-Sat).
 * @var array  $events_by_day Events grouped by day-of-month.
 * @var int    $today         Today's day number.
 * @var int    $today_month   Today's month number.
 * @var int    $today_year    Today's year number.
 * @var array  $view_data     Full view data array (all variables).
 */

defined( 'ABSPATH' ) || exit;
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
				$cell_date_label = wp_date( 'F j', ( new \DateTimeImmutable( sprintf( '%04d-%02d-%02d 12:00', $year, $month, $day ), wp_timezone() ) )->getTimestamp() );
				$aria_label      = $is_today
					/* translators: %s: full date string e.g. "March 15" */
					? sprintf( __( '%s, today', 'wb-listora' ), $cell_date_label )
					: $cell_date_label;

				printf( '<div class="%s" role="gridcell" aria-label="%s">', esc_attr( $class ), esc_attr( $aria_label ) );
				echo '<span class="listora-calendar__day-num" aria-hidden="true">' . esc_html( $day ) . '</span>';

				if ( $has_events ) {
					echo '<div class="listora-calendar__events">';
					foreach ( array_slice( $events_by_day[ $day ], 0, 3 ) as $evt ) {
						$evt_style   = $evt['color'] ? ' style="--event-color: ' . esc_attr( $evt['color'] ) . '"' : '';
						// A real link to the listing: the old click-to-open popover
						// rendered away from the calendar and looked dead (card 10335981513).
						printf(
							'<a class="listora-calendar__event" href="%s"%s title="%s">%s</a>',
							esc_url( get_permalink( absint( $evt['ID'] ) ) ),
							$evt_style, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-built with esc_attr().
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

	<?php if ( ! empty( $events_by_day ) ) : ?>
	<ul class="listora-calendar__agenda" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: month and year */ __( 'Events in %s', 'wb-listora' ), $month_name ) ); ?>">
		<?php
		ksort( $events_by_day );
		foreach ( $events_by_day as $agenda_day => $agenda_events ) :
			$agenda_ts = ( new \DateTimeImmutable( sprintf( '%04d-%02d-%02d 12:00', $year, $month, $agenda_day ), wp_timezone() ) )->getTimestamp();
			?>
		<li class="listora-calendar__agenda-day">
			<span class="listora-calendar__agenda-date"><?php echo esc_html( wp_date( 'D j', $agenda_ts ) ); ?></span>
			<ul class="listora-calendar__agenda-events">
				<?php foreach ( $agenda_events as $agenda_event ) : ?>
				<li>
					<a class="listora-calendar__event" href="<?php echo esc_url( get_permalink( absint( $agenda_event['ID'] ) ) ); ?>"<?php echo $agenda_event['color'] ? ' style="--event-color: ' . esc_attr( $agenda_event['color'] ) . '"' : ''; ?>><?php echo esc_html( $agenda_event['post_title'] ); ?></a>
				</li>
				<?php endforeach; ?>
			</ul>
		</li>
		<?php endforeach; ?>
	</ul>
	<?php else : ?>
	<p class="listora-calendar__empty" role="status">
		<?php
		/* translators: %s: month and year, e.g. "September 2026" */
		echo esc_html( sprintf( __( 'No events in %s.', 'wb-listora' ), $month_name ) );
		?>
	</p>
	<?php endif; ?>
</div>
