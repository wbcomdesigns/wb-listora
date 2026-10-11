<?php
/**
 * Settings > Import / Export and Migration tabs.
 *
 * @package WBListora\Admin
 */

namespace WBListora\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Settings > Import / Export and Settings > Migration tabs.
 */
class Import_Export_Tabs {

	/**
	 * Render the Import / Export tab content.
	 *
	 * Includes JSON settings import/export and CSV listing data import/export.
	 */
	public static function render_import_export(): void {
		$type_terms = get_terms(
			array(
				'taxonomy'   => 'listora_listing_type',
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $type_terms ) ) {
			$type_terms = array();
		}

		// The CSV import card wires to the resumable Background_Import pipeline
		// and renders the shared .listora-import-progress widget while a run
		// drains. That widget's stylesheet is only auto-enqueued on the setup
		// wizard, so register it here too. The poll + mapping behaviour lives
		// in admin-pages.js (already enqueued on every Listora admin screen).
		wp_enqueue_style(
			'listora-import-progress',
			WB_LISTORA_PLUGIN_URL . 'assets/css/admin/import-progress.css',
			array( 'listora-admin' ),
			WB_LISTORA_VERSION
		);
		?>
		<div class="listora-settings-pane listora-impex">

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Plugin Settings', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Backup or migrate your plugin configuration as JSON.', 'wb-listora' ); ?></p>
				</div>

				<div class="listora-impex__cards">
					<div class="listora-impex__card">
						<div class="listora-impex__card-head">
							<span class="listora-impex__card-icon"><i data-lucide="download"></i></span>
							<h4 class="listora-impex__card-title"><?php esc_html_e( 'Export Settings', 'wb-listora' ); ?></h4>
						</div>
						<p class="listora-impex__card-desc"><?php esc_html_e( 'Download a JSON snapshot of every plugin setting.', 'wb-listora' ); ?></p>
						<div class="listora-impex__card-foot">
							<button type="button" class="listora-btn wp-element-button listora-btn--secondary" data-listora-action="export-settings">
								<i data-lucide="download"></i> <?php esc_html_e( 'Download JSON', 'wb-listora' ); ?>
							</button>
						</div>
					</div>

					<div class="listora-impex__card">
						<div class="listora-impex__card-head">
							<span class="listora-impex__card-icon"><i data-lucide="upload"></i></span>
							<h4 class="listora-impex__card-title"><?php esc_html_e( 'Import Settings', 'wb-listora' ); ?></h4>
						</div>
						<p class="listora-impex__card-desc"><?php esc_html_e( 'Upload a JSON file to replace current settings. Only files from this plugin version.', 'wb-listora' ); ?></p>
						<div class="listora-impex__field">
							<input type="file" id="listora-import-file" accept=".json" />
						</div>
						<div class="listora-impex__card-foot">
							<button type="button" class="listora-btn wp-element-button listora-btn--secondary" data-listora-action="import-settings">
								<i data-lucide="upload"></i> <?php esc_html_e( 'Upload &amp; Import', 'wb-listora' ); ?>
							</button>
							<span id="listora-import-status" class="listora-impex__status"></span>
						</div>
					</div>
				</div>
			</section>

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Listings Data', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Bulk export or import directory listings as CSV files.', 'wb-listora' ); ?></p>
				</div>

				<div class="listora-impex__cards">
				<div class="listora-impex__card">
					<div class="listora-impex__card-head">
						<span class="listora-impex__card-icon"><i data-lucide="file-down"></i></span>
						<h4 class="listora-impex__card-title"><?php esc_html_e( 'Export Listings', 'wb-listora' ); ?></h4>
					</div>
					<p class="listora-impex__card-desc"><?php esc_html_e( 'Download all listings (or filter by type) as a CSV.', 'wb-listora' ); ?></p>
					<div class="listora-impex__field">
						<label for="listora-csv-export-type"><?php esc_html_e( 'Listing type', 'wb-listora' ); ?></label>
						<select id="listora-csv-export-type">
							<option value=""><?php esc_html_e( 'All types', 'wb-listora' ); ?></option>
							<?php foreach ( $type_terms as $term ) : ?>
								<option value="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="listora-impex__card-foot">
						<button type="button" id="listora-csv-export-btn" class="listora-btn wp-element-button listora-btn--primary">
							<i data-lucide="download"></i> <?php esc_html_e( 'Export CSV', 'wb-listora' ); ?>
						</button>
						<span id="listora-csv-export-status" class="listora-impex__status"></span>
					</div>
				</div>

				<?php
				/**
				 * Whether Import / Export shows Free's fixed-column CSV importer.
				 * Pro returns false: its Visual Import is the one CSV importer
				 * there, with column mapping.
				 *
				 * @since 1.9.0
				 *
				 * @param bool $show Show the basic importer.
				 */
				if ( apply_filters( 'wb_listora_show_basic_csv_import', true ) ) :
					?>
				<div
					class="listora-impex__card"
					id="listora-csv-import-card"
					data-mappable-fields="<?php echo esc_attr( (string) wp_json_encode( \WBListora\ImportExport\CSV_Importer::get_mappable_fields( '' ) ) ); ?>"
				>
					<div class="listora-impex__card-head">
						<span class="listora-impex__card-icon"><i data-lucide="file-up"></i></span>
						<h4 class="listora-impex__card-title"><?php esc_html_e( 'Import Listings', 'wb-listora' ); ?></h4>
					</div>
					<p class="listora-impex__card-desc"><?php esc_html_e( 'Bulk-create listings from CSV. First row must be column headers. Large files import in the background — watch the progress bar below.', 'wb-listora' ); ?></p>
					<div class="listora-impex__field">
						<label for="listora-csv-import-type"><?php esc_html_e( 'Listing type', 'wb-listora' ); ?> <span class="listora-required">*</span></label>
						<select id="listora-csv-import-type" required>
							<option value=""><?php esc_html_e( 'Select a type…', 'wb-listora' ); ?></option>
							<?php foreach ( $type_terms as $term ) : ?>
								<option value="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="listora-impex__field">
						<label for="listora-csv-import-file"><?php esc_html_e( 'CSV file', 'wb-listora' ); ?> <span class="listora-required">*</span></label>
						<input type="file" id="listora-csv-import-file" accept=".csv,text/csv">
					</div>
					<div id="listora-csv-import-mapping" class="listora-impex__mapping is-hidden" aria-live="polite"></div>
					<div class="listora-impex__card-foot">
						<button type="button" id="listora-csv-import-btn" class="listora-btn wp-element-button listora-btn--primary">
							<i data-lucide="upload"></i> <?php esc_html_e( 'Import CSV', 'wb-listora' ); ?>
						</button>
						<span id="listora-csv-import-status" class="listora-impex__status" aria-live="polite"></span>
					</div>

					<div
						id="listora-csv-import-progress"
						class="listora-import-progress"
						hidden
					>
						<p class="listora-import-progress__label">
							<i data-lucide="download-cloud" aria-hidden="true"></i>
							<span class="listora-import-progress__text"><?php esc_html_e( 'Importing listings in the background…', 'wb-listora' ); ?></span>
						</p>
						<div
							class="listora-import-progress__track"
							role="progressbar"
							aria-valuemin="0"
							aria-valuemax="100"
							aria-valuenow="0"
							aria-label="<?php esc_attr_e( 'CSV import progress', 'wb-listora' ); ?>"
						>
							<div class="listora-import-progress__bar" style="inline-size:0%"></div>
						</div>
						<p class="listora-import-progress__stats" aria-live="polite">
							<span class="listora-import-progress__count">0</span>
							<?php esc_html_e( 'listings imported', 'wb-listora' ); ?>
						</p>
					</div>
				</div>
				<?php endif; ?>
			</section>


		</div>
		<?php
	}

	/**
	 * Render Migration tab content (non-form).
	 *
	 * Shows a card for each supported source plugin with detection status,
	 * listing counts, and migration controls.
	 */
	public static function render_migration(): void {
		$migrators = \WBListora\ImportExport\Migration_Base::get_migrators();

		echo '<div class="listora-settings-pane">';

		echo '<section class="listora-settings-block">';
		echo '<div class="listora-settings-block__head">';
		echo '<h3 class="listora-settings-block__title">' . esc_html__( 'Migration Sources', 'wb-listora' ) . '</h3>';
		echo '<p class="listora-settings-block__desc">' . esc_html__( 'Detect data from other directory plugins and import it into WB Listora. Run a dry-run first to preview results.', 'wb-listora' ) . '</p>';
		echo '</div>';

		if ( empty( $migrators ) ) {
			echo '<div class="listora-settings-block__body">';
			echo '<p class="description">' . esc_html__( 'No migration sources are available. Install and activate a supported source plugin to enable migration.', 'wb-listora' ) . '</p>';
			echo '</div>';
			echo '</section>';
			echo '</div>';
			return;
		}

		echo '<div class="listora-settings-block__body">';
		echo '<div class="listora-migration-grid">';

		foreach ( $migrators as $migrator ) {
			$slug     = $migrator->get_source_slug();
			$detected = $migrator->detect();
			$count    = $detected ? $migrator->get_source_count() : 0;

			echo '<div class="listora-migration-card" data-source="' . esc_attr( $slug ) . '">';

			// Header.
			echo '<div class="listora-migration-card__header">';
			echo '<div class="listora-migration-card__info">';
			echo '<div class="listora-migration-card__icon"><i data-lucide="database"></i></div>';
			echo '<div>';
			echo '<h3 class="listora-migration-card__title">' . esc_html( $migrator->get_source_name() ) . '</h3>';
			echo '<p class="listora-migration-card__desc">' . esc_html( $migrator->get_source_description() ) . '</p>';
			echo '</div>';
			echo '</div>';

			// Detection badge.
			echo '<div class="listora-migration-card__badge">';
			if ( $detected ) {
				echo '<span class="listora-badge listora-badge--success">';
				echo '<i data-lucide="check-circle-2"></i> ';
				echo esc_html__( 'Detected', 'wb-listora' ) . '</span>';
			} else {
				echo '<span class="listora-badge listora-badge--muted">';
				echo '<i data-lucide="circle-slash"></i> ';
				echo esc_html__( 'Not Detected', 'wb-listora' ) . '</span>';
			}
			echo '</div>';
			echo '</div>'; // .listora-migration-card__header.

			// Body.
			echo '<div class="listora-migration-card__body">';

			if ( $detected ) {
				// Listing count.
				echo '<div class="listora-migration-card__count">';
				printf(
					/* translators: %s: formatted listing count */
					esc_html__( '%s listings available for migration.', 'wb-listora' ),
					'<strong>' . esc_html( number_format_i18n( $count ) ) . '</strong>'
				);
				echo '</div>';

				// Controls.
				echo '<div class="listora-migration-card__controls">';

				echo '<div class="listora-migration-card__options">';
				echo '<label>';
				echo '<input type="checkbox" class="listora-migration-dryrun" data-source="' . esc_attr( $slug ) . '">';
				echo esc_html__( 'Dry run (validate without importing)', 'wb-listora' );
				echo '</label>';
				echo '</div>';

				echo '<div class="listora-migration-card__actions">';
				echo '<button type="button" class="listora-btn wp-element-button listora-btn--primary listora-migration-start" data-source="' . esc_attr( $slug ) . '" data-count="' . esc_attr( (string) $count ) . '">';
				echo '<i data-lucide="play"></i> ' . esc_html__( 'Start Migration', 'wb-listora' ) . '</button>';
				echo '</div>';

				echo '</div>'; // .listora-migration-card__controls.

				// Progress bar (hidden by default).
				echo '<div class="listora-migration-progress" id="listora-progress-' . esc_attr( $slug ) . '">';
				echo '<div class="listora-migration-progress__bar">';
				echo '<div class="listora-migration-progress__fill" id="listora-fill-' . esc_attr( $slug ) . '"></div>';
				echo '</div>';
				echo '<div class="listora-migration-progress__text">';
				echo '<span class="listora-migration-progress__stats" id="listora-stats-' . esc_attr( $slug ) . '"></span>';
				echo '<span id="listora-pct-' . esc_attr( $slug ) . '">0%</span>';
				echo '</div>';
				echo '</div>'; // .listora-migration-progress.
			} else {
				// Not detected message.
				echo '<div class="listora-migration-card__notice">';
				printf(
					/* translators: %s: source plugin name */
					esc_html__( '%s data not found. Install and activate the plugin, or run the migration on a site where it was previously used.', 'wb-listora' ),
					esc_html( $migrator->get_source_name() )
				);
				echo '</div>';
			}

			// Result area (hidden by default).
			echo '<div class="listora-migration-result" id="listora-result-' . esc_attr( $slug ) . '"></div>';

			echo '</div>'; // .listora-migration-card__body.
			echo '</div>'; // .listora-migration-card.
		}

		echo '</div>'; // .listora-migration-grid.
		echo '</div>'; // .listora-settings-block__body.
		echo '</section>'; // .listora-settings-block.
		echo '</div>'; // .listora-settings-pane.

		// Migration AJAX handler + spin animation styles live in
		// assets/js/admin/settings-page.js + assets/css/admin/settings.css
		// (no inline JS or CSS allowed in admin PHP).
	}
}
