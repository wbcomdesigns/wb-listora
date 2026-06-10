<?php
/**
 * Admin Listing Bulk Actions — extra list-table bulk operations beyond WP core.
 *
 * Adds bulk Approve / Reject / Feature / Unfeature / Assign-category to the
 * `listora_listing` list table via the core `bulk_actions-{screen}` and
 * `handle_bulk_actions-{screen}` filters. Kept in its own class (NOT in
 * class-listing-columns.php) so the columns class stays focused on columns.
 *
 * Reuse contract (this class adds NO transition logic of its own):
 *  - Approve / Reject transition through `wp_update_post()` to `publish` /
 *    `listora_rejected` exactly as {@see \WBListora\Rest\Listings_Controller::bulk_moderate()}
 *    does, so the core `transition_post_status` chain drives
 *    {@see \WBListora\Workflow\Status_Manager} (notifications + webhooks fire).
 *  - Feature / Unfeature delegate to {@see \WBListora\Core\Featured::feature_listing()}
 *    / {@see \WBListora\Core\Featured::unfeature_listing()} so Pro credit rules apply.
 *  - Category assignment delegates to {@see \WBListora\ImportExport\Term_Helper::set_terms()}.
 *
 * Batches are bounded to 100 IDs per request (same cap as bulk-moderate).
 * Every action is capability-gated (`edit_others_listora_listings` at the top,
 * per-row `edit_post`) and nonce-protected (WP core verifies the `bulk-posts`
 * nonce before `handle_bulk_actions-{screen}` fires).
 *
 * @package WBListora\Admin
 */

namespace WBListora\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers extra bulk-edit actions on the listings list table.
 */
class Listing_Bulk_Actions {

	/**
	 * Maximum listings processed per bulk request (matches bulk-moderate).
	 *
	 * @var int
	 */
	const BATCH_LIMIT = 100;

	/**
	 * Category taxonomy targeted by the Assign-category action.
	 *
	 * @var string
	 */
	const CATEGORY_TAXONOMY = 'listora_listing_cat';

	/**
	 * Query var carrying the chosen term ID for the assign-category action.
	 *
	 * @var string
	 */
	const TERM_REQUEST_KEY = 'listora_bulk_category';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_filter( 'bulk_actions-edit-listora_listing', array( $this, 'register_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-listora_listing', array( $this, 'handle_bulk_actions' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'bulk_action_notices' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Self-wire on `admin_init` so the feature needs no out-of-class bootstrap.
	 *
	 * Idempotent — a second call is a no-op because the constructor only
	 * registers filter callbacks (WP de-dupes identical method callbacks).
	 *
	 * @return void
	 */
	public static function init(): void {
		new self();
	}

	/**
	 * Add the extra bulk actions to the listings list table.
	 *
	 * Capability-gated: a user who cannot moderate others' listings sees only
	 * the core bulk actions (no approve/reject/feature controls).
	 *
	 * @param array<string,string> $actions Existing bulk actions.
	 * @return array<string,string>
	 */
	public function register_bulk_actions( $actions ) {
		if ( ! current_user_can( 'edit_others_listora_listings' ) ) {
			return $actions;
		}

		$actions['listora_bulk_approve']   = __( 'Approve', 'wb-listora' );
		$actions['listora_bulk_reject']    = __( 'Reject', 'wb-listora' );
		$actions['listora_bulk_feature']   = __( 'Feature', 'wb-listora' );
		$actions['listora_bulk_unfeature'] = __( 'Unfeature', 'wb-listora' );

		// The category taxonomy maps `assign_terms` => `edit_listora_listings`.
		if ( current_user_can( 'edit_listora_listings' ) ) {
			$actions['listora_bulk_assign_category'] = __( 'Assign category', 'wb-listora' );
		}

		return $actions;
	}

	/**
	 * Process a recognised bulk action against the selected listings.
	 *
	 * WP core verifies the `bulk-posts` nonce before this filter runs, so the
	 * handler only adds capability checks. Returns the redirect URL with a
	 * per-result query arg the notice renderer reads.
	 *
	 * @param string $sendback Redirect URL.
	 * @param string $action   Bulk action key.
	 * @param int[]  $post_ids Selected post IDs.
	 * @return string Redirect URL with result args.
	 */
	public function handle_bulk_actions( $sendback, $action, $post_ids ) {
		$known = array(
			'listora_bulk_approve',
			'listora_bulk_reject',
			'listora_bulk_feature',
			'listora_bulk_unfeature',
			'listora_bulk_assign_category',
		);

		if ( ! in_array( $action, $known, true ) ) {
			return $sendback;
		}

		// Top-level capability gate (mirrors Listings_Controller::bulk_moderate_permissions()).
		if ( ! current_user_can( 'edit_others_listora_listings' ) ) {
			return add_query_arg( 'listora_bulk_error', 'forbidden', $sendback );
		}

		$ids = array_slice(
			array_filter( array_unique( array_map( 'absint', (array) $post_ids ) ) ),
			0,
			self::BATCH_LIMIT
		);

		if ( empty( $ids ) ) {
			return add_query_arg(
				array(
					'listora_bulk_action' => rawurlencode( $action ),
					'listora_bulk_ok'     => 0,
					'listora_bulk_failed' => 0,
				),
				$sendback
			);
		}

		// Resolve the target category once for the assign-category action.
		$term_id = 0;
		if ( 'listora_bulk_assign_category' === $action ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WP core verified the `bulk-posts` nonce before this filter fires.
			$term_id = isset( $_REQUEST[ self::TERM_REQUEST_KEY ] ) ? absint( wp_unslash( $_REQUEST[ self::TERM_REQUEST_KEY ] ) ) : 0;

			if ( $term_id <= 0 || ! term_exists( $term_id, self::CATEGORY_TAXONOMY ) ) {
				return add_query_arg(
					array(
						'listora_bulk_action' => rawurlencode( $action ),
						'listora_bulk_error'  => 'no_category',
					),
					$sendback
				);
			}
		}

		$ok        = 0;
		$failed    = 0;
		$first_err = '';

		foreach ( $ids as $post_id ) {
			$post_id = (int) $post_id;

			if ( 'listora_listing' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
				++$failed;
				continue;
			}

			$result = $this->apply_action( $action, $post_id, $term_id );

			if ( is_wp_error( $result ) || true !== $result ) {
				++$failed;
				// Surface the first concrete failure reason (e.g. an invalid
				// status transition from apply_action's Status_Manager guard) so
				// the admin notice explains WHY a row was skipped instead of a
				// bare "N skipped". One representative message keeps the redirect
				// URL bounded; the per-row count still reflects the full batch.
				if ( '' === $first_err && is_wp_error( $result ) ) {
					$msg = $result->get_error_message();
					if ( '' !== (string) $msg ) {
						$first_err = (string) $msg;
					}
				}
			} else {
				++$ok;
			}
		}

		/**
		 * Fires after an extra bulk-edit batch (approve/reject/feature/unfeature/assign-category) completes.
		 *
		 * @since 1.2.0
		 *
		 * @param string $action  Bulk action key applied across the batch.
		 * @param int    $ok      Count of listings the action succeeded on.
		 * @param int    $failed  Count of listings the action failed/skipped on.
		 * @param int[]  $ids     Listing IDs the batch ran against.
		 */
		do_action( 'wb_listora_after_bulk_edit', $action, $ok, $failed, $ids );

		$result_args = array(
			'listora_bulk_action' => rawurlencode( $action ),
			'listora_bulk_ok'     => $ok,
			'listora_bulk_failed' => $failed,
		);

		if ( '' !== $first_err ) {
			$result_args['listora_bulk_reason'] = rawurlencode( $first_err );
		}

		return add_query_arg( $result_args, $sendback );
	}

	/**
	 * Apply a single bulk action to one listing, reusing the canonical services.
	 *
	 * No transition logic lives here — this routes to the same primitives the
	 * REST bulk-moderate handler uses.
	 *
	 * @param string $action  Bulk action key.
	 * @param int    $post_id Listing ID.
	 * @param int    $term_id Target category term ID (assign-category only).
	 * @return bool|\WP_Error True on success, false on a soft failure, WP_Error on a hard failure.
	 */
	private function apply_action( $action, $post_id, $term_id ) {
		switch ( $action ) {
			case 'listora_bulk_approve':
			case 'listora_bulk_reject':
				// Guard the transition through Status_Manager BEFORE writing -
				// the double-verify proved an invalid transition was silently
				// applied via raw wp_update_post(). An invalid from->to is a
				// reported per-row failure, never a silent write. The write
				// itself still goes through wp_update_post() so the
				// transition_post_status chain (Status_Manager listeners,
				// notifications, webhooks) fires exactly as everywhere else.
				$target = 'listora_bulk_approve' === $action ? 'publish' : 'listora_rejected';
				$from   = get_post_status( $post_id );

				if ( ! \WBListora\Workflow\Status_Manager::is_valid_transition( (string) $from, $target ) ) {
					return new \WP_Error(
						'listora_invalid_transition',
						sprintf(
							/* translators: 1: current listing status, 2: requested status. */
							__( 'Cannot move a %1$s listing to %2$s.', 'wb-listora' ),
							(string) $from,
							$target
						)
					);
				}

				$res = wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => $target,
					),
					true
				);
				return is_wp_error( $res ) ? $res : true;

			case 'listora_bulk_feature':
				$res = \WBListora\Core\Featured::feature_listing( $post_id, 0 );
				return is_wp_error( $res ) ? $res : ( true === $res );

			case 'listora_bulk_unfeature':
				$res = \WBListora\Core\Featured::unfeature_listing( $post_id, 'manual' );
				return is_wp_error( $res ) ? $res : ( true === $res );

			case 'listora_bulk_assign_category':
				$name = get_term_field( 'name', $term_id, self::CATEGORY_TAXONOMY );
				if ( is_wp_error( $name ) || '' === (string) $name ) {
					return new \WP_Error( 'invalid_category', __( 'Category not found.', 'wb-listora' ) );
				}
				$assigned = \WBListora\ImportExport\Term_Helper::set_terms(
					$post_id,
					array( (string) $name ),
					self::CATEGORY_TAXONOMY
				);
				return ! empty( $assigned );
		}

		return new \WP_Error( 'unknown_bulk_action', __( 'Unknown bulk action.', 'wb-listora' ) );
	}

	/**
	 * Render the per-result admin notice after a bulk-edit redirect.
	 *
	 * @return void
	 */
	public function bulk_action_notices(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice from URL params after a core-nonce-verified bulk submit.
		if ( isset( $_GET['listora_bulk_error'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$code    = sanitize_key( wp_unslash( $_GET['listora_bulk_error'] ) );
			$message = ( 'no_category' === $code )
				? __( 'Select a category before running the Assign category bulk action.', 'wb-listora' )
				: __( 'You do not have permission to run that bulk action.', 'wb-listora' );

			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html( $message )
			);
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice from URL params.
		if ( ! isset( $_GET['listora_bulk_action'], $_GET['listora_bulk_ok'] ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only notice from URL params.
		$action = sanitize_key( wp_unslash( $_GET['listora_bulk_action'] ) );
		$ok     = absint( wp_unslash( $_GET['listora_bulk_ok'] ) );
		$failed = isset( $_GET['listora_bulk_failed'] ) ? absint( wp_unslash( $_GET['listora_bulk_failed'] ) ) : 0;
		$reason = isset( $_GET['listora_bulk_reason'] ) ? sanitize_text_field( wp_unslash( $_GET['listora_bulk_reason'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$labels = array(
			'listora_bulk_approve'         => __( 'approved', 'wb-listora' ),
			'listora_bulk_reject'          => __( 'rejected', 'wb-listora' ),
			'listora_bulk_feature'         => __( 'featured', 'wb-listora' ),
			'listora_bulk_unfeature'       => __( 'unfeatured', 'wb-listora' ),
			'listora_bulk_assign_category' => __( 'assigned to the category', 'wb-listora' ),
		);

		if ( ! isset( $labels[ $action ] ) ) {
			return;
		}

		$verb = $labels[ $action ];

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: number of listings, 2: action performed (e.g. approved). */
					_n( '%1$d listing %2$s.', '%1$d listings %2$s.', $ok, 'wb-listora' ),
					$ok,
					$verb
				)
			)
		);

		if ( $failed > 0 ) {
			$skipped_text = sprintf(
				/* translators: %d: number of listings skipped. */
				_n( '%d listing was skipped (not a listing or insufficient permission).', '%d listings were skipped (not a listing or insufficient permission).', $failed, 'wb-listora' ),
				$failed
			);

			// Append the first concrete failure reason from apply_action's
			// WP_Error (e.g. "Cannot move a publish listing to publish.") so the
			// admin learns WHY rather than only HOW MANY. Empty when every skip
			// was a permission/type pre-check (which carries no WP_Error).
			if ( '' !== $reason ) {
				$skipped_text .= ' ' . sprintf(
					/* translators: %s: the first per-row failure reason from the bulk action. */
					__( 'First reason: %s', 'wb-listora' ),
					$reason
				);
			}

			printf(
				'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
				esc_html( $skipped_text )
			);
		}
	}

	/**
	 * Enqueue the bulk-edit helper script on the listings list table only.
	 *
	 * The script injects a category dropdown beside the bulk-action selector
	 * when "Assign category" is chosen, so the selected term rides the bulk
	 * submit. WP core's bulk-action UI has no native extra-field slot.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ): void {
		if ( 'edit.php' !== $hook_suffix ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'edit-listora_listing' !== $screen->id ) {
			return;
		}

		if ( ! current_user_can( 'edit_others_listora_listings' ) ) {
			return;
		}

		$src = WB_LISTORA_PLUGIN_URL . 'assets/js/admin/bulk-edit.js';

		wp_enqueue_script(
			'listora-bulk-edit',
			$src,
			array(),
			WB_LISTORA_VERSION,
			true
		);

		$terms = get_terms(
			array(
				'taxonomy'   => self::CATEGORY_TAXONOMY,
				'hide_empty' => false,
			)
		);

		$options = array();
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( ! $term instanceof \WP_Term ) {
					continue;
				}
				$options[] = array(
					'id'   => (int) $term->term_id,
					'name' => $term->name,
				);
			}
		}

		wp_localize_script(
			'listora-bulk-edit',
			'listoraBulkEdit',
			array(
				'action'       => 'listora_bulk_assign_category',
				'fieldName'    => self::TERM_REQUEST_KEY,
				'categories'   => $options,
				'selectLabel'  => __( 'Select category', 'wb-listora' ),
				'fieldAriaTip' => __( 'Category to assign to the selected listings', 'wb-listora' ),
			)
		);
	}
}
