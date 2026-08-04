<?php
/**
 * Shape-fuzz smoke — renders customer surfaces with corrupted stored shapes.
 *
 * Run via: wp eval-file bin/shape-smoke.php   (local-CI stage 2.4)
 *
 * THE GATE THIS IS: the 1.4.0 live-site fatal (BC 10162700303) happened
 * because a store (field options) held a shape no reader expected, and no
 * tier could see it — PHPStan level 7 can't type mixed accessors, WPCS is
 * style-only, and every journey ran on healthy seeded shapes. This script
 * closes that hole class-wide: it injects the corrupted shapes found in the
 * 2026-08-04 portfolio sweep into the real stores/filters and renders each
 * consuming surface in-process. Any Throwable = FAIL = the push is blocked.
 *
 * Every check restores the state it touched. Safe on a dev site; do not run
 * against production.
 *
 * @package WBListora
 */

defined( 'WP_CLI' ) || exit;

$shape_failures = array();
$shape_pass     = 0;

/**
 * Run one fuzz case: no Throwable may escape $render.
 *
 * @param string   $name   Case label.
 * @param callable $render Surface render to execute.
 */
$shape_case = function ( $name, callable $render ) use ( &$shape_failures, &$shape_pass ) {
	try {
		$render();
		++$shape_pass;
		WP_CLI::log( "  ✓ {$name}" );
	} catch ( \Throwable $e ) {
		$shape_failures[] = $name . ' — ' . get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
		WP_CLI::log( "  ✗ {$name} — " . $e->getMessage() );
	}
};

WP_CLI::log( 'Shape-fuzz smoke (corrupted stored shapes must render, not fatal):' );

// ── 1. Field options stored as plain strings (the customer fatal). ──────────
$shape_case(
	'submission renderer: string options (multiselect)',
	function () {
		require_once WB_LISTORA_PLUGIN_DIR . 'includes/submission-field-renderer.php';
		$field = \WBListora\Core\Field::from_array(
			array(
				'key'     => 'fuzz_ms',
				'label'   => 'Fuzz',
				'type'    => 'multiselect',
				'options' => array( 'Alpha', 'Beta' ),
			)
		);
		ob_start();
		wb_listora_render_submission_field( $field, null, array() );
		ob_end_clean();
	}
);

$shape_case(
	'submission renderer: string options (select)',
	function () {
		$field = \WBListora\Core\Field::from_array(
			array(
				'key'     => 'fuzz_sel',
				'label'   => 'Fuzz',
				'type'    => 'select',
				'options' => array( 'One', 2, array( 'label' => 'Three' ) ),
			)
		);
		ob_start();
		wb_listora_render_submission_field( $field, null, array() );
		ob_end_clean();
	}
);

// ── 2. Scalar entries inside stored field_groups (hydration path). ──────────
$shape_case(
	'listing-type hydration: scalar group + scalar field entries',
	function () {
		$type = new \WBListora\Core\Listing_Type(
			'fuzz-type',
			array( 'name' => 'Fuzz' ),
			array(
				'not-a-group',
				array(
					'key'    => 'g',
					'label'  => 'G',
					'fields' => array( 'title', array( 'key' => 'ok', 'label' => 'OK' ) ),
				),
			)
		);
		$type->get_field_groups();
	}
);

// ── 3. Dashboard stats transient holding a scalar. ──────────────────────────
$shape_case(
	'user-dashboard: scalar stats transient',
	function () {
		$uid = 1;
		wp_set_current_user( $uid );
		$key      = 'listora_dashboard_stats_' . $uid;
		$original = get_transient( $key );
		set_transient( $key, 'corrupted-string', 60 );
		try {
			ob_start();
			echo do_blocks( '<!-- wp:listora/user-dashboard /-->' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			ob_end_clean();
		} finally {
			delete_transient( $key );
			if ( false !== $original ) {
				set_transient( $key, $original, 60 );
			}
			wp_set_current_user( 0 );
		}
	}
);

// ── 4. Filter surfaces returning junk shapes. ────────────────────────────────
$shape_case(
	'review criteria filter: strings + scalar rows',
	function () {
		$junk = function () {
			return array( 'not-an-array', 42, array( 'key' => 'ok', 'label' => 'OK' ) );
		};
		add_filter( 'wb_listora_review_criteria', $junk, 99 );
		try {
			$listing = get_posts(
				array(
					'post_type'   => 'listora_listing',
					'numberposts' => 1,
					'post_status' => 'publish',
				)
			);
			if ( $listing ) {
				ob_start();
				echo do_blocks( '<!-- wp:listora/listing-reviews {"listingId":' . (int) $listing[0]->ID . '} /-->' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				ob_end_clean();
			}
		} finally {
			remove_filter( 'wb_listora_review_criteria', $junk, 99 );
		}
	}
);

$shape_case(
	'calendar events filter: scalar rows',
	function () {
		$junk = function ( $events ) {
			$events[] = 'not-an-event';
			$events[] = 7;
			return $events;
		};
		add_filter( 'wb_listora_calendar_events', $junk, 99 );
		try {
			ob_start();
			echo do_blocks( '<!-- wp:listora/listing-calendar /-->' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			ob_end_clean();
		} finally {
			remove_filter( 'wb_listora_calendar_events', $junk, 99 );
		}
	}
);

$shape_case(
	'category card data filter: scalar return',
	function () {
		$junk = function () {
			return 'oops';
		};
		add_filter( 'wb_listora_category_card_data', $junk, 99 );
		try {
			ob_start();
			echo do_blocks( '<!-- wp:listora/listing-categories /-->' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			ob_end_clean();
		} finally {
			remove_filter( 'wb_listora_category_card_data', $junk, 99 );
		}
	}
);

// ── 5. Card render fed composite _listing_data with scalar sub-shapes. ──────
$shape_case(
	'listing-card: scalar rating / string type / string features',
	function () {
		$listing = get_posts(
			array(
				'post_type'   => 'listora_listing',
				'numberposts' => 1,
				'post_status' => 'publish',
			)
		);
		if ( ! $listing ) {
			return;
		}
		$data = wb_listora_prepare_card_data( $listing[0]->ID );
		if ( ! is_array( $data ) ) {
			return;
		}
		$data['rating']   = '4.2';
		$data['type']     = 'business';
		$data['features'] = array( 'wifi', 'parking' );
		$json             = wp_json_encode( array( '_listing_data' => $data ) );
		ob_start();
		echo do_blocks( '<!-- wp:listora/listing-card ' . $json . ' /-->' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		ob_end_clean();
	}
);

// ── Verdict ──────────────────────────────────────────────────────────────────
if ( $shape_failures ) {
	WP_CLI::error(
		sprintf(
			"shape-fuzz smoke FAILED (%d of %d cases):\n  %s",
			count( $shape_failures ),
			count( $shape_failures ) + $shape_pass,
			implode( "\n  ", $shape_failures )
		)
	);
}
WP_CLI::success( sprintf( 'shape-fuzz smoke: all %d cases green', $shape_pass ) );
