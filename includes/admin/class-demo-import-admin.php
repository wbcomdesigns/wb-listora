<?php
/**
 * Demo import and delete controls in wp-admin.
 *
 * @package WBListora\Admin
 */

namespace WBListora\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Demo content from the admin: the Advanced-tab controls and their AJAX handlers.
 */
class Demo_Import_Admin {

	/**
	 * The Demo import and Delete demo data rows of Settings > Advanced.
	 */
	public static function render_rows(): void {
		?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Demo import', 'wb-listora' ); ?></th>
							<td>
								<?php
								// Check whether any demo listings already exist so the JS
								// can warn the admin before duplicating content.
								$demo_listing_count = wp_count_posts( 'listora_listing' );
								$has_demo           = ( ( (int) ( $demo_listing_count->publish ?? 0 ) + (int) ( $demo_listing_count->draft ?? 0 ) ) > 0 ) ? '1' : '0';
								$demo_packs = wb_listora_get_demo_packs();
								?>
								<button
									type="button"
									id="listora-demo-import-btn"
									class="button"
									data-has-demo="<?php echo esc_attr( $has_demo ); ?>"
									data-packs="<?php echo esc_attr( (string) wp_json_encode( $demo_packs ) ); ?>"
								>
									<i data-lucide="refresh-cw"></i> <?php esc_html_e( 'Re-run Demo Import', 'wb-listora' ); ?>
								</button>
								<span id="listora-demo-import-status" class="listora-impex__status" aria-live="polite"></span>
								<p class="description"><?php esc_html_e( 'Queues a background import of demo listings for all ten listing types, events included. If demo data already exists you will be asked to confirm before proceeding.', 'wb-listora' ); ?></p>
								<div
									id="listora-demo-import-progress"
									class="listora-import-progress"
									hidden
								>
									<p class="listora-import-progress__label">
										<i data-lucide="download-cloud" aria-hidden="true"></i>
										<span class="listora-import-progress__text"><?php esc_html_e( 'Importing demo listings in the background…', 'wb-listora' ); ?></span>
									</p>
									<div
										class="listora-import-progress__track"
										role="progressbar"
										aria-valuemin="0"
										aria-valuemax="100"
										aria-valuenow="0"
										aria-label="<?php esc_attr_e( 'Demo import progress', 'wb-listora' ); ?>"
									>
										<div class="listora-import-progress__bar" style="inline-size:0%"></div>
									</div>
									<p class="listora-import-progress__stats" aria-live="polite">
										<span class="listora-import-progress__count">0</span>
										<?php esc_html_e( 'listings imported', 'wb-listora' ); ?>
									</p>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Delete demo data', 'wb-listora' ); ?></th>
							<td>
								<?php
								$listora_demo_counts = class_exists( '\WBListora\Demo\Demo_Seeder' )
									? \WBListora\Demo\Demo_Seeder::count_demo_content()
									: array( 'listings' => 0 );
								$listora_demo_total  = (int) ( $listora_demo_counts['listings'] ?? 0 );
								?>
								<button
									type="button"
									id="listora-demo-delete-btn"
									class="button button-link-delete"
									data-demo-count="<?php echo esc_attr( (string) $listora_demo_total ); ?>"
									<?php disabled( 0, $listora_demo_total ); ?>
								>
									<i data-lucide="trash-2"></i> <?php esc_html_e( 'Delete Demo Data', 'wb-listora' ); ?>
								</button>
								<span id="listora-demo-delete-status" class="listora-impex__status" aria-live="polite"></span>
								<p class="description">
									<?php
									if ( $listora_demo_total > 0 ) {
										printf(
											/* translators: %d: number of demo listings currently present. */
											esc_html( _n( 'Permanently removes the %d demo listing (and its demo images) loaded by the setup wizard. Your own listings are never touched.', 'Permanently removes the %d demo listings (and their demo images) loaded by the setup wizard. Your own listings are never touched.', $listora_demo_total, 'wb-listora' ) ),
											(int) $listora_demo_total
										);
									} else {
										esc_html_e( 'No demo data is currently present. This button activates once demo content has been imported.', 'wb-listora' );
									}
									?>
								</p>
							</td>
						</tr>
		<?php
	}

	/**
	 * AJAX handler — queue a background demo import from Settings → Advanced.
	 *
	 * Requires `manage_options` capability + a valid `listora_demo_import` nonce.
	 * Fires the sanctioned `wb_listora_demo_import_run` action with the run_id
	 * so Pro and third-party plugins can hook into the demo-import lifecycle.
	 * Returns `{ success: true, data: { run_id, status, total } }` on success or
	 * `{ success: false, data: { message } }` on failure.
	 *
	 * @return void Sends a JSON response and exits.
	 */
	public static function ajax_run_demo_import(): void {
		// Capability gate — same cap as every other admin-only action.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'wb-listora' ) ), 403 );
		}

		// Nonce check.
		check_ajax_referer( 'listora_demo_import', '_nonce' );

		if ( ! class_exists( '\WBListora\ImportExport\Background_Import' ) ) {
			wp_send_json_error( array( 'message' => __( 'Background importer is not available.', 'wb-listora' ) ), 500 );
		}

		$allowed_packs = wb_listora_get_demo_packs();

		// Honor an optional packs param from the JS (passed as JSON array).
		$packs_param = isset( $_POST['packs'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['packs'] ) ) : '';
		if ( '' !== $packs_param ) {
			$decoded = json_decode( $packs_param, true );
			if ( is_array( $decoded ) ) {
				$packs_param_clean = array_values(
					array_filter(
						array_map( 'sanitize_key', $decoded ),
						static function ( $p ) use ( $allowed_packs ) {
							return in_array( $p, $allowed_packs, true );
						}
					)
				);
				if ( ! empty( $packs_param_clean ) ) {
					$packs = $packs_param_clean;
				}
			}
		}

		if ( empty( $packs ) ) {
			$packs = $allowed_packs;
		}

		$run_id = \WBListora\ImportExport\Background_Import::queue_demo( $packs );

		/**
		 * Fires after a demo import has been queued from the admin UI.
		 *
		 * @since 1.2.0
		 *
		 * @param string   $run_id The Background_Import run identifier.
		 * @param string[] $packs  Demo pack slugs queued for this run.
		 */
		do_action( 'wb_listora_demo_import_run', $run_id, $packs );

		$progress = \WBListora\ImportExport\Background_Import::get_progress( $run_id );

		wp_send_json_success(
			array(
				'run_id' => $run_id,
				'status' => is_array( $progress ) ? ( $progress['status'] ?? 'queued' ) : 'queued',
				'total'  => is_array( $progress ) ? (int) ( $progress['total'] ?? 0 ) : 0,
			)
		);
	}

	/**
	 * AJAX: delete all demo content from the admin UI.
	 *
	 * Exposes the canonical remover (Demo_Seeder::remove_all(), also behind
	 * `wp listora demo remove`) to a Settings → Advanced button. Requires
	 * `manage_options` + a valid `listora_demo_delete` nonce. Returns the
	 * deleted counts.
	 *
	 * @return void Sends a JSON response and exits.
	 */
	public static function ajax_delete_demo(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'wb-listora' ) ), 403 );
		}

		check_ajax_referer( 'listora_demo_delete', '_nonce' );

		if ( ! class_exists( '\WBListora\Demo\Demo_Seeder' ) ) {
			wp_send_json_error( array( 'message' => __( 'Demo tools are not available.', 'wb-listora' ) ), 500 );
		}

		$deleted = \WBListora\Demo\Demo_Seeder::remove_all();

		wp_send_json_success(
			array(
				'listings'    => (int) $deleted['listings'],
				'attachments' => (int) $deleted['attachments'],
			)
		);
	}
}
