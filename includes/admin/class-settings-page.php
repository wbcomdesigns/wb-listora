<?php
/**
 * Settings Page — WP Settings API with tabbed interface.
 *
 * @package WBListora\Admin
 */

namespace WBListora\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the plugin settings page with 7 tabs.
 */
class Settings_Page {

	/**
	 * Option name.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'wb_listora_settings';

	/**
	 * Register settings.
	 */
	public static function register() {
		register_setting(
			'wb_listora_settings_group',
			self::OPTION_KEY,
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			)
		);
	}

	/**
	 * Sanitize settings on save.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$defaults  = wb_listora_get_default_settings();
		$sanitized = array();

		foreach ( $defaults as $key => $default ) {
			if ( ! isset( $input[ $key ] ) ) {
				$sanitized[ $key ] = is_bool( $default ) ? false : $default;
				continue;
			}

			$value = $input[ $key ];

			if ( is_bool( $default ) ) {
				$sanitized[ $key ] = (bool) $value;
			} elseif ( is_int( $default ) ) {
				$sanitized[ $key ] = (int) $value;
			} elseif ( is_float( $default ) ) {
				$sanitized[ $key ] = (float) $value;
			} else {
				$sanitized[ $key ] = sanitize_text_field( $value );
			}
		}

		// Preserve nested sub-arrays (notifications, reviews) — merge with existing.
		$old = get_option( self::OPTION_KEY, array() );

		if ( isset( $input['notifications'] ) && is_array( $input['notifications'] ) ) {
			$sanitized['notifications'] = array_map( 'absint', $input['notifications'] );
		} elseif ( isset( $old['notifications'] ) ) {
			$sanitized['notifications'] = $old['notifications'];
		}

		if ( isset( $input['reviews'] ) && is_array( $input['reviews'] ) ) {
			$reviews_raw          = $input['reviews'];
			$sanitized['reviews'] = array(
				'auto_approve'    => ! empty( $reviews_raw['auto_approve'] ),
				'require_login'   => ! empty( $reviews_raw['require_login'] ),
				'min_length'      => isset( $reviews_raw['min_length'] ) ? absint( $reviews_raw['min_length'] ) : 20,
				'one_per_listing' => ! empty( $reviews_raw['one_per_listing'] ),
				'allow_reply'     => ! empty( $reviews_raw['allow_reply'] ),
			);
		} elseif ( isset( $old['reviews'] ) ) {
			$sanitized['reviews'] = $old['reviews'];
		}

		// Validate captcha_provider against allowed values.
		$allowed_captcha = array( 'none', 'recaptcha_v3', 'cloudflare_turnstile' );
		if ( ! in_array( $sanitized['captcha_provider'] ?? 'none', $allowed_captcha, true ) ) {
			$sanitized['captcha_provider'] = 'none';
		}

		// Flush rewrites if slugs changed.
		if ( ( $old['listing_slug'] ?? '' ) !== ( $sanitized['listing_slug'] ?? '' ) ) {
			add_action( 'shutdown', 'flush_rewrite_rules' );
		}

		return $sanitized;
	}

	/**
	 * Get tab definitions grouped for sidebar navigation.
	 *
	 * @return array[] {
	 *     Groups of tabs with labels and icons.
	 *
	 *     @type string $group_label Group heading text.
	 *     @type array  $tabs        Array of tab_id => { label, icon, desc }.
	 * }
	 */
	public static function get_nav_groups() {
		$groups = array(
			'general'  => array(
				'group_label' => __( 'General', 'wb-listora' ),
				'tabs'        => array(
					'general'     => array(
						'label' => __( 'General', 'wb-listora' ),
						'icon'  => 'settings',
						'desc'  => __( 'Core plugin settings — listings per page, slugs, currency.', 'wb-listora' ),
					),
					'maps'        => array(
						'label' => __( 'Maps', 'wb-listora' ),
						'icon'  => 'map',
						'desc'  => __( 'Map provider, default coordinates, and clustering.', 'wb-listora' ),
					),
					'submissions' => array(
						'label' => __( 'Submissions', 'wb-listora' ),
						'icon'  => 'file-plus',
						'desc'  => __( 'Frontend submission and moderation settings.', 'wb-listora' ),
					),
					'reviews'     => array(
						'label' => __( 'Reviews', 'wb-listora' ),
						'icon'  => 'message-circle',
						'desc'  => __( 'Review moderation, requirements, and owner reply settings.', 'wb-listora' ),
					),
				),
			),
			'pro'      => array(
				'group_label' => __( 'Pro', 'wb-listora' ),
				'tabs'        => array(
					'notifications' => array(
						'label' => __( 'Notifications', 'wb-listora' ),
						'icon'  => 'bell',
						'desc'  => __( 'Email notification events and recipients.', 'wb-listora' ),
					),
					'seo'           => array(
						'label' => __( 'SEO', 'wb-listora' ),
						'icon'  => 'search',
						'desc'  => __( 'Schema.org, sitemaps, breadcrumbs, Open Graph.', 'wb-listora' ),
					),
				),
			),
			'advanced' => array(
				'group_label' => __( 'Advanced', 'wb-listora' ),
				'tabs'        => array(
					'advanced'      => array(
						'label' => __( 'Advanced', 'wb-listora' ),
						'icon'  => 'sliders',
						'desc'  => __( 'Cache, maintenance, debug, and data management.', 'wb-listora' ),
					),
					'import-export' => array(
						'label' => __( 'Import / Export', 'wb-listora' ),
						'icon'  => 'arrow-left-right',
						'desc'  => __( 'Export or import plugin settings as JSON.', 'wb-listora' ),
					),
					'migration'     => array(
						'label' => __( 'Migration', 'wb-listora' ),
						'icon'  => 'database',
						'desc'  => __( 'Import listings from other directory plugins.', 'wb-listora' ),
					),
				),
			),
		);

		/**
		 * Filter settings nav groups. Pro can inject tabs into existing groups
		 * or add entirely new groups.
		 *
		 * @param array $groups Nav groups.
		 */
		return apply_filters( 'wb_listora_settings_nav_groups', $groups );
	}

	/**
	 * Build a flat tabs array from grouped nav structure.
	 *
	 * Provides backward compatibility with the `wb_listora_settings_tabs` filter.
	 *
	 * @return array tab_id => label
	 */
	private static function get_flat_tabs() {
		$groups = self::get_nav_groups();
		$tabs   = array();

		foreach ( $groups as $group ) {
			foreach ( $group['tabs'] as $tab_id => $tab ) {
				$tabs[ $tab_id ] = $tab['label'];
			}
		}

		/**
		 * Filter settings tabs (backward compat). Pro can add tabs here.
		 */
		return apply_filters( 'wb_listora_settings_tabs', $tabs );
	}

	/**
	 * Get the documentation URL for a settings tab.
	 *
	 * @param string $tab_id Tab identifier.
	 * @return string Documentation URL.
	 */
	private static function get_docs_url( $tab_id ) {
		$map = array(
			'general'       => 'general',
			'maps'          => 'map',
			'submissions'   => 'submission',
			'reviews'       => 'general',
			'notifications' => 'notifications',
			'seo'           => 'general',
			'advanced'      => 'general',
		);

		$section = $map[ $tab_id ] ?? 'general';

		return 'https://wblistora.com/docs/' . $section . '/';
	}

	/**
	 * Get the render callback for a given tab.
	 *
	 * @param string $tab_id Tab identifier.
	 * @return string|null Method name or null.
	 */
	private static function get_tab_renderer( $tab_id ) {
		$map = array(
			'general'       => 'render_general_tab',
			'maps'          => 'render_maps_tab',
			'submissions'   => 'render_submissions_tab',
			'reviews'       => 'render_reviews_tab',
			'notifications' => 'render_notifications_tab',
			'seo'           => 'render_seo_tab',
			'advanced'      => 'render_advanced_tab',
			'import-export' => 'render_import_export_tab',
			'migration'     => 'render_migration_tab',
		);

		return $map[ $tab_id ] ?? null;
	}

	/**
	 * Render the settings page with Pattern A sidebar layout.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_listora_settings' ) ) {
			return;
		}

		$groups    = self::get_nav_groups();
		$flat_tabs = self::get_flat_tabs();
		$tab_keys  = array_keys( $flat_tabs );
		$first_tab = reset( $tab_keys );

		?>
		<div class="listora-settings-wrap wb-listora-admin">
			<?php // ── Sidebar ── ?>
			<div class="listora-settings-sidebar">
				<div class="listora-settings-sidebar__brand">
					<span class="listora-settings-sidebar__logo"><i data-lucide="map-pin"></i></span>
					<div>
						<strong><?php esc_html_e( 'WB Listora', 'wb-listora' ); ?></strong>
						<span><?php esc_html_e( 'SETTINGS', 'wb-listora' ); ?></span>
					</div>
				</div>

				<?php foreach ( $groups as $group ) : ?>
				<div class="listora-settings-nav-group">
					<span class="listora-settings-nav-group__label"><?php echo esc_html( strtoupper( $group['group_label'] ) ); ?></span>
					<?php foreach ( $group['tabs'] as $tab_id => $tab ) : ?>
					<a class="listora-settings-nav-item" href="#<?php echo esc_attr( $tab_id ); ?>" data-section="<?php echo esc_attr( $tab_id ); ?>">
						<i data-lucide="<?php echo esc_attr( $tab['icon'] ); ?>"></i>
						<?php echo esc_html( $tab['label'] ); ?>
						<?php if ( ! empty( $tab['pro'] ) ) : ?>
							<span class="listora-pro-badge"><?php esc_html_e( 'Pro', 'wb-listora' ); ?></span>
						<?php endif; ?>
					</a>
					<?php endforeach; ?>
				</div>
				<?php endforeach; ?>
			</div>

			<?php // ── Content ── ?>
			<div class="listora-settings-content">
				<?php
				// Show settings-updated notice inside content area.
				if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'wb-listora' ); ?></p>
				</div>
				<?php endif; ?>

				<?php foreach ( $groups as $group ) : ?>
					<?php foreach ( $group['tabs'] as $tab_id => $tab ) : ?>
						<?php
						$skip_form_tabs = apply_filters( 'wb_listora_settings_skip_form_tabs', array( 'import-export', 'migration' ) );
						if ( in_array( $tab_id, $skip_form_tabs, true ) ) {
							continue;
						}
						?>
					<div class="listora-settings-section" id="section-<?php echo esc_attr( $tab_id ); ?>">
					<form method="post" action="options.php">
						<?php settings_fields( 'wb_listora_settings_group' ); ?>
						<div class="listora-card">
							<div class="listora-card__head">
								<div class="listora-card__head-row">
									<div>
										<p class="listora-card__title"><?php echo esc_html( strtoupper( $tab['label'] ) ); ?></p>
										<?php if ( ! empty( $tab['desc'] ) ) : ?>
										<p class="listora-card__desc"><?php echo esc_html( $tab['desc'] ); ?></p>
										<?php endif; ?>
									</div>
									<a href="<?php echo esc_url( self::get_docs_url( $tab_id ) ); ?>" target="_blank" rel="noopener noreferrer" class="listora-docs-link">
										<i data-lucide="book-open"></i> <?php esc_html_e( 'Documentation', 'wb-listora' ); ?>
									</a>
								</div>
							</div>
							<?php
							$renderer = self::get_tab_renderer( $tab_id );
							if ( $renderer && method_exists( __CLASS__, $renderer ) ) {
								self::$renderer();
							}

							/**
							 * Fires to render Pro tab content.
							 *
							 * @param string $tab_id Current tab being rendered.
							 */
							do_action( 'wb_listora_settings_tab_content', $tab_id );
							?>
						</div>
						<div class="listora-settings-section__footer">
							<button type="button" class="listora-btn listora-btn--danger" onclick="listoraResetDefaults();">
								<i data-lucide="rotate-ccw"></i> <?php esc_html_e( 'Reset to Defaults', 'wb-listora' ); ?>
							</button>
							<button type="submit" class="listora-btn listora-btn--primary">
								<i data-lucide="save"></i> <?php esc_html_e( 'Save Changes', 'wb-listora' ); ?>
							</button>
						</div>
					</form>
				</div>
					<?php endforeach; ?>
				<?php endforeach; ?>

				<?php
				// ── Non-form sections (Import/Export, Migration, Pro CRUD tabs) ──.
				$skip_form_tabs = apply_filters( 'wb_listora_settings_skip_form_tabs', array( 'import-export', 'migration' ) );
				foreach ( $groups as $group ) :
					foreach ( $group['tabs'] as $tab_id => $tab ) :
						if ( ! in_array( $tab_id, $skip_form_tabs, true ) ) {
							continue;
						}
						?>
						<div class="listora-settings-section" id="section-<?php echo esc_attr( $tab_id ); ?>">
							<div class="listora-card">
								<div class="listora-card__head">
									<div class="listora-card__head-row">
										<div>
											<p class="listora-card__title"><?php echo esc_html( strtoupper( $tab['label'] ) ); ?></p>
											<?php if ( ! empty( $tab['desc'] ) ) : ?>
											<p class="listora-card__desc"><?php echo esc_html( $tab['desc'] ); ?></p>
											<?php endif; ?>
										</div>
									</div>
								</div>
								<?php
								$renderer = self::get_tab_renderer( $tab_id );
								if ( $renderer && method_exists( __CLASS__, $renderer ) ) {
									self::$renderer();
								}
								do_action( 'wb_listora_settings_tab_content', $tab_id );
								?>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endforeach; ?>

			</div>
		</div>

		<script>
		/* ── CSV Export ── */
		(function() {
			document.addEventListener('DOMContentLoaded', function() {
				var exportBtn = document.getElementById('listora-csv-export-btn');
				if (exportBtn) {
					exportBtn.addEventListener('click', function() {
						var type   = document.getElementById('listora-csv-export-type').value;
						var status = document.getElementById('listora-csv-export-status');
						var params = new URLSearchParams({ include_meta: '1' });
						if (type) params.set('type', type);

						status.textContent = '<?php echo esc_js( __( 'Generating export...', 'wb-listora' ) ); ?>';
						status.style.color = '#2271b1';
						exportBtn.disabled = true;

						var url = '<?php echo esc_js( rest_url( 'listora/v1/export/csv' ) ); ?>' + '?' + params.toString();
						url += '&_wpnonce=' + '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>';

						var a = document.createElement('a');
						a.href = url;
						a.download = '';
						document.body.appendChild(a);
						a.click();
						document.body.removeChild(a);

						status.textContent = '<?php echo esc_js( __( 'Download started.', 'wb-listora' ) ); ?>';
						status.style.color = '#00a32a';
						exportBtn.disabled = false;
					});
				}

				var importBtn = document.getElementById('listora-csv-import-btn');
				if (importBtn) {
					importBtn.addEventListener('click', function() {
						var typeSlug  = document.getElementById('listora-csv-import-type').value;
						var fileInput = document.getElementById('listora-csv-import-file');
						var dryRun    = document.getElementById('listora-csv-import-dryrun').checked;
						var status    = document.getElementById('listora-csv-import-status');

						if (!typeSlug) {
							status.textContent = '<?php echo esc_js( __( 'Please select a listing type.', 'wb-listora' ) ); ?>';
							status.style.color = '#d63638';
							return;
						}
						if (!fileInput.files.length) {
							status.textContent = '<?php echo esc_js( __( 'Please select a CSV file.', 'wb-listora' ) ); ?>';
							status.style.color = '#d63638';
							return;
						}

						importBtn.disabled = true;
						importBtn.textContent = '<?php echo esc_js( __( 'Importing...', 'wb-listora' ) ); ?>';
						status.textContent = '';

						var formData = new FormData();
						formData.append('file', fileInput.files[0]);
						formData.append('type_slug', typeSlug);
						formData.append('dry_run', dryRun ? '1' : '0');
						formData.append('mapping', JSON.stringify({"0": "title", "1": "description", "2": "category", "3": "tags"}));

						wp.apiFetch({
							path: '/listora/v1/import/csv',
							method: 'POST',
							body: formData,
							parse: true,
						}).then(function(res) {
							var msg = '<?php echo esc_js( __( 'Imported:', 'wb-listora' ) ); ?> ' + res.imported;
							if (res.skipped) msg += ', <?php echo esc_js( __( 'Skipped:', 'wb-listora' ) ); ?> ' + res.skipped;
							if (res.errors)  msg += ', <?php echo esc_js( __( 'Errors:', 'wb-listora' ) ); ?> ' + res.errors;
							if (res.dry_run) msg += ' (<?php echo esc_js( __( 'dry run', 'wb-listora' ) ); ?>)';
							status.textContent = msg;
							status.style.color = res.errors ? '#d63638' : '#00a32a';
							importBtn.textContent = '<?php echo esc_js( __( 'Import CSV', 'wb-listora' ) ); ?>';
							importBtn.disabled = false;
						}).catch(function(err) {
							status.textContent = err.message || '<?php echo esc_js( __( 'Import failed.', 'wb-listora' ) ); ?>';
							status.style.color = '#d63638';
							importBtn.textContent = '<?php echo esc_js( __( 'Import CSV', 'wb-listora' ) ); ?>';
							importBtn.disabled = false;
						});
					});
				}
			});
		})();

		/* Reset to Defaults */
		function listoraResetDefaults() {
			if ( ! confirm( '<?php echo esc_js( __( 'Are you sure? This will reset all settings to their defaults.', 'wb-listora' ) ); ?>' ) ) {
				return;
			}
			wp.apiFetch( { path: '/listora/v1/settings', method: 'DELETE' } ).then( function() {
				window.location.reload();
			}).catch( function( err ) {
				if (window.listoraToast) { listoraToast( '<?php echo esc_js( __( 'Reset failed:', 'wb-listora' ) ); ?> ' + ( err.message || err ), {type:'error'} ); }
			});
		}

		/* Export Settings */
		function listoraExportSettings() {
			wp.apiFetch( { path: '/listora/v1/settings/export', parse: false } ).then( function( response ) {
				return response.json();
			}).then( function( data ) {
				var blob = new Blob( [ JSON.stringify( data, null, 2 ) ], { type: 'application/json' } );
				var url  = URL.createObjectURL( blob );
				var a    = document.createElement( 'a' );
				a.href     = url;
				a.download = 'wb-listora-settings.json';
				document.body.appendChild( a );
				a.click();
				document.body.removeChild( a );
				URL.revokeObjectURL( url );
			}).catch( function( err ) {
				if (window.listoraToast) { listoraToast( '<?php echo esc_js( __( 'Export failed:', 'wb-listora' ) ); ?> ' + ( err.message || err ), {type:'error'} ); }
			});
		}

		/* Import Settings */
		function listoraImportSettings() {
			var fileInput = document.getElementById( 'listora-import-file' );
			var status    = document.getElementById( 'listora-import-status' );

			if ( ! fileInput.files.length ) {
				if (window.listoraToast) { listoraToast( '<?php echo esc_js( __( 'Please select a JSON file first.', 'wb-listora' ) ); ?>', {type:'warning'} ); }
				return;
			}

			var reader = new FileReader();
			reader.onload = function( e ) {
				try {
					var data = JSON.parse( e.target.result );
				} catch ( err ) {
					if (window.listoraToast) { listoraToast( '<?php echo esc_js( __( 'Invalid JSON file.', 'wb-listora' ) ); ?>', {type:'error'} ); }
					return;
				}

				if ( ! confirm( '<?php echo esc_js( __( 'This will replace all current settings. Continue?', 'wb-listora' ) ); ?>' ) ) {
					return;
				}

				status.textContent = '<?php echo esc_js( __( 'Importing...', 'wb-listora' ) ); ?>';

				wp.apiFetch( {
					path:   '/listora/v1/settings/import',
					method: 'POST',
					data:   data
				}).then( function() {
					status.textContent = '<?php echo esc_js( __( 'Imported successfully!', 'wb-listora' ) ); ?>';
					status.style.color = '#00a32a';
					setTimeout( function() { window.location.reload(); }, 1000 );
				}).catch( function( err ) {
					status.textContent = '<?php echo esc_js( __( 'Import failed:', 'wb-listora' ) ); ?> ' + ( err.message || err );
					status.style.color = '#d63638';
				});
			};
			reader.readAsText( fileInput.files[0] );
		}
		</script>
		<?php
	}

	// ─── Tab Renderers ───

	private static function render_general_tab() {
		$s = get_option( self::OPTION_KEY, array() );
		$d = wb_listora_get_default_settings();
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="per_page"><?php esc_html_e( 'Listings per page', 'wb-listora' ); ?></label></th>
				<td><input type="number" id="per_page" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[per_page]" value="<?php echo esc_attr( $s['per_page'] ?? $d['per_page'] ); ?>" min="1" max="100" class="small-text" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="listing_slug"><?php esc_html_e( 'Listing URL slug', 'wb-listora' ); ?></label></th>
				<td><input type="text" id="listing_slug" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[listing_slug]" value="<?php echo esc_attr( $s['listing_slug'] ?? $d['listing_slug'] ); ?>" class="regular-text" />
				<p class="description"><?php esc_html_e( 'Single listing URL: /listing/{slug}/', 'wb-listora' ); ?></p></td>
			</tr>
			<tr>
				<th scope="row"><label for="currency"><?php esc_html_e( 'Currency', 'wb-listora' ); ?></label></th>
				<td>
					<select id="currency" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[currency]">
						<?php
						$currencies = array( 'USD', 'EUR', 'GBP', 'JPY', 'INR', 'AUD', 'CAD', 'CHF', 'CNY', 'BRL' );
						$current    = $s['currency'] ?? $d['currency'];
						foreach ( $currencies as $c ) {
							printf( '<option value="%s" %s>%s</option>', esc_attr( $c ), selected( $current, $c, false ), esc_html( $c ) );
						}
						?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Distance unit', 'wb-listora' ); ?></th>
				<td>
					<label><input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[distance_unit]" value="km" <?php checked( $s['distance_unit'] ?? $d['distance_unit'], 'km' ); ?> /> <?php esc_html_e( 'Kilometers', 'wb-listora' ); ?></label>&nbsp;&nbsp;
					<label><input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[distance_unit]" value="mi" <?php checked( $s['distance_unit'] ?? $d['distance_unit'], 'mi' ); ?> /> <?php esc_html_e( 'Miles', 'wb-listora' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Listing expiration', 'wb-listora' ); ?> <span class="listora-help-tip" data-tip="<?php esc_attr_e( 'Number of days before a listing automatically expires and is unpublished. Set to 0 for listings that never expire. Expiration reminder emails are sent 7 days and 1 day before.', 'wb-listora' ); ?>">?</span></th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_expiration]" value="1" <?php checked( $s['enable_expiration'] ?? $d['enable_expiration'] ); ?> /> <?php esc_html_e( 'Enable listing expiration', 'wb-listora' ); ?></label>
					<br/><br/>
					<label><?php esc_html_e( 'Default expiration:', 'wb-listora' ); ?> <input type="number" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[default_expiration]" value="<?php echo esc_attr( $s['default_expiration'] ?? $d['default_expiration'] ); ?>" min="0" class="small-text" /> <?php esc_html_e( 'days (0 = never)', 'wb-listora' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Claiming', 'wb-listora' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_claiming]" value="1" <?php checked( $s['enable_claiming'] ?? $d['enable_claiming'] ); ?> /> <?php esc_html_e( 'Enable listing claiming', 'wb-listora' ); ?></label></td>
			</tr>
		</table>
		<?php
	}

	private static function render_maps_tab() {
		$s = get_option( self::OPTION_KEY, array() );
		$d = wb_listora_get_default_settings();
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Map Provider', 'wb-listora' ); ?> <span class="listora-help-tip" data-tip="<?php esc_attr_e( 'Choose between OpenStreetMap (free, no API key required) or Google Maps (Pro, requires an API key with Maps JavaScript API enabled).', 'wb-listora' ); ?>">?</span></th>
				<td>
					<label><input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[map_provider]" value="osm" <?php checked( $s['map_provider'] ?? $d['map_provider'], 'osm' ); ?> /> <?php esc_html_e( 'OpenStreetMap (free, no API key)', 'wb-listora' ); ?></label>
					<br/>
					<label style="opacity:0.5;"><input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[map_provider]" value="google" <?php checked( $s['map_provider'] ?? $d['map_provider'], 'google' ); ?> disabled /> <?php esc_html_e( 'Google Maps (Pro)', 'wb-listora' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="map_default_lat"><?php esc_html_e( 'Default center', 'wb-listora' ); ?></label></th>
				<td>
					<label><?php esc_html_e( 'Lat:', 'wb-listora' ); ?> <input type="text" id="map_default_lat" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[map_default_lat]" value="<?php echo esc_attr( $s['map_default_lat'] ?? $d['map_default_lat'] ); ?>" class="small-text" /></label>
					<label><?php esc_html_e( 'Lng:', 'wb-listora' ); ?> <input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[map_default_lng]" value="<?php echo esc_attr( $s['map_default_lng'] ?? $d['map_default_lng'] ); ?>" class="small-text" /></label>
					<label><?php esc_html_e( 'Zoom:', 'wb-listora' ); ?> <input type="number" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[map_default_zoom]" value="<?php echo esc_attr( $s['map_default_zoom'] ?? $d['map_default_zoom'] ); ?>" min="1" max="20" class="small-text" /></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Options', 'wb-listora' ); ?></th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[map_clustering]" value="1" <?php checked( $s['map_clustering'] ?? $d['map_clustering'] ); ?> /> <?php esc_html_e( 'Enable marker clustering', 'wb-listora' ); ?></label><br/>
					<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[map_search_on_drag]" value="1" <?php checked( $s['map_search_on_drag'] ?? $d['map_search_on_drag'] ); ?> /> <?php esc_html_e( 'Search on map drag', 'wb-listora' ); ?></label><br/>
					<label><?php esc_html_e( 'Max markers:', 'wb-listora' ); ?> <input type="number" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[map_max_markers]" value="<?php echo esc_attr( $s['map_max_markers'] ?? $d['map_max_markers'] ); ?>" min="50" max="5000" class="small-text" /></label>
				</td>
			</tr>
		</table>
		<?php
	}

	private static function render_submissions_tab() {
		$s = get_option( self::OPTION_KEY, array() );
		$d = wb_listora_get_default_settings();
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Frontend submission', 'wb-listora' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_submission]" value="1" <?php checked( $s['enable_submission'] ?? $d['enable_submission'] ); ?> /> <?php esc_html_e( 'Enable frontend listing submission', 'wb-listora' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Moderation', 'wb-listora' ); ?> <span class="listora-help-tip" data-tip="<?php esc_attr_e( 'Controls whether new frontend submissions are published immediately or held for admin review. Auto-approve is faster but may require cleanup for spam.', 'wb-listora' ); ?>">?</span></th>
				<td>
					<label><input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[moderation]" value="manual" <?php checked( $s['moderation'] ?? $d['moderation'], 'manual' ); ?> /> <?php esc_html_e( 'Require admin approval', 'wb-listora' ); ?></label><br/>
					<label><input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[moderation]" value="auto_approve" <?php checked( $s['moderation'] ?? $d['moderation'], 'auto_approve' ); ?> /> <?php esc_html_e( 'Auto-approve all submissions', 'wb-listora' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="max_upload_size"><?php esc_html_e( 'Max file size (MB)', 'wb-listora' ); ?></label></th>
				<td><input type="number" id="max_upload_size" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[max_upload_size]" value="<?php echo esc_attr( $s['max_upload_size'] ?? $d['max_upload_size'] ); ?>" min="1" max="50" class="small-text" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="max_gallery_images"><?php esc_html_e( 'Max gallery images', 'wb-listora' ); ?></label></th>
				<td><input type="number" id="max_gallery_images" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[max_gallery_images]" value="<?php echo esc_attr( $s['max_gallery_images'] ?? $d['max_gallery_images'] ); ?>" min="1" max="100" class="small-text" /></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Guest submissions', 'wb-listora' ); ?> <span class="listora-help-tip" data-tip="<?php esc_attr_e( 'Allow non-logged-in users to submit listings. An account is created automatically using their name and email.', 'wb-listora' ); ?>">?</span></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_guest_submission]" value="1" <?php checked( $s['enable_guest_submission'] ?? $d['enable_guest_submission'] ); ?> />
						<?php esc_html_e( 'Allow guest users to submit listings (inline registration)', 'wb-listora' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Guests provide their name and email. An account is created and a password reset email is sent.', 'wb-listora' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="captcha_provider"><?php esc_html_e( 'CAPTCHA protection', 'wb-listora' ); ?></label> <span class="listora-help-tip" data-tip="<?php esc_attr_e( 'Protect submission and review forms with CAPTCHA. Requires a site key and secret key from Google reCAPTCHA or Cloudflare Turnstile.', 'wb-listora' ); ?>">?</span></th>
				<td>
					<select id="captcha_provider" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[captcha_provider]">
						<option value="none" <?php selected( $s['captcha_provider'] ?? $d['captcha_provider'], 'none' ); ?>><?php esc_html_e( 'None', 'wb-listora' ); ?></option>
						<option value="recaptcha_v3" <?php selected( $s['captcha_provider'] ?? $d['captcha_provider'], 'recaptcha_v3' ); ?>><?php esc_html_e( 'Google reCAPTCHA v3', 'wb-listora' ); ?></option>
						<option value="cloudflare_turnstile" <?php selected( $s['captcha_provider'] ?? $d['captcha_provider'], 'cloudflare_turnstile' ); ?>><?php esc_html_e( 'Cloudflare Turnstile', 'wb-listora' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="captcha_site_key"><?php esc_html_e( 'CAPTCHA site key', 'wb-listora' ); ?></label></th>
				<td>
					<input type="text" id="captcha_site_key" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[captcha_site_key]" value="<?php echo esc_attr( $s['captcha_site_key'] ?? $d['captcha_site_key'] ); ?>" class="regular-text" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="captcha_secret_key"><?php esc_html_e( 'CAPTCHA secret key', 'wb-listora' ); ?></label></th>
				<td>
					<input type="password" id="captcha_secret_key" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[captcha_secret_key]" value="<?php echo esc_attr( $s['captcha_secret_key'] ?? $d['captcha_secret_key'] ); ?>" class="regular-text" autocomplete="off" />
				</td>
			</tr>
		</table>
		<?php
	}

	private static function render_reviews_tab() {
		$s       = get_option( self::OPTION_KEY, array() );
		$reviews = $s['reviews'] ?? array();
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Auto-approve reviews', 'wb-listora' ); ?> <span class="listora-help-tip" data-tip="<?php esc_attr_e( 'When enabled, all new reviews are published immediately without admin moderation. Disable this to manually review and approve each submission before it appears publicly.', 'wb-listora' ); ?>">?</span></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[reviews][auto_approve]" value="1" <?php checked( ! empty( $reviews['auto_approve'] ) ); ?> />
						<?php esc_html_e( 'Automatically approve new reviews without manual moderation.', 'wb-listora' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Require login to review', 'wb-listora' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[reviews][require_login]" value="1" <?php checked( ! isset( $reviews['require_login'] ) || ! empty( $reviews['require_login'] ) ); ?> />
						<?php esc_html_e( 'Only logged-in users can submit reviews.', 'wb-listora' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="review_min_length"><?php esc_html_e( 'Minimum review length', 'wb-listora' ); ?></label></th>
				<td>
					<input type="number" id="review_min_length" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[reviews][min_length]" value="<?php echo esc_attr( $reviews['min_length'] ?? 20 ); ?>" min="0" max="1000" class="small-text" />
					<p class="description"><?php esc_html_e( 'Minimum number of characters required for review content.', 'wb-listora' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'One review per listing', 'wb-listora' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[reviews][one_per_listing]" value="1" <?php checked( ! isset( $reviews['one_per_listing'] ) || ! empty( $reviews['one_per_listing'] ) ); ?> />
						<?php esc_html_e( 'Limit each user to one review per listing.', 'wb-listora' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Allow owner reply', 'wb-listora' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[reviews][allow_reply]" value="1" <?php checked( ! isset( $reviews['allow_reply'] ) || ! empty( $reviews['allow_reply'] ) ); ?> />
						<?php esc_html_e( 'Allow listing owners to reply to reviews on their listings.', 'wb-listora' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php
	}

	private static function render_notifications_tab() {
		$s     = get_option( self::OPTION_KEY, array() );
		$notif = $s['notifications'] ?? array();

		$events = array(
			'listing_submitted' => array( __( 'New listing submitted', 'wb-listora' ), __( 'Sent to admin when a new listing is submitted for review.', 'wb-listora' ) ),
			'listing_approved'  => array( __( 'Listing approved', 'wb-listora' ), __( 'Sent to listing owner when their listing is published.', 'wb-listora' ) ),
			'listing_rejected'  => array( __( 'Listing rejected', 'wb-listora' ), __( 'Sent to listing owner with feedback.', 'wb-listora' ) ),
			'listing_expired'   => array( __( 'Listing expired', 'wb-listora' ), __( 'Sent to listing owner when their listing expires.', 'wb-listora' ) ),
			'listing_expiring'  => array( __( 'Expiration reminder', 'wb-listora' ), __( 'Sent before a listing expires (7 and 1 day).', 'wb-listora' ) ),
			'review_received'   => array( __( 'New review received', 'wb-listora' ), __( 'Sent to listing owner when they receive a review.', 'wb-listora' ) ),
			'review_reply'      => array( __( 'Owner replied to review', 'wb-listora' ), __( 'Sent to reviewer when the owner responds.', 'wb-listora' ) ),
			'claim_submitted'   => array( __( 'Claim submitted', 'wb-listora' ), __( 'Sent to admin when a claim is filed.', 'wb-listora' ) ),
			'claim_approved'    => array( __( 'Claim approved', 'wb-listora' ), __( 'Sent to claimant when their claim is approved.', 'wb-listora' ) ),
			'claim_rejected'    => array( __( 'Claim rejected', 'wb-listora' ), __( 'Sent to claimant when their claim is denied.', 'wb-listora' ) ),
		);
		?>
		<table class="form-table">
			<?php
			foreach ( $events as $key => $event_info ) :
				$enabled = ! isset( $notif[ $key ] ) || $notif[ $key ];
				?>
			<tr>
				<th scope="row"><?php echo esc_html( $event_info[0] ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[notifications][<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $enabled ); ?> />
						<?php echo esc_html( $event_info[1] ); ?>
					</label>
				</td>
			</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}

	private static function render_seo_tab() {
		$s = get_option( self::OPTION_KEY, array() );
		$d = wb_listora_get_default_settings();
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Schema.org', 'wb-listora' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_schema]" value="1" <?php checked( $s['enable_schema'] ?? $d['enable_schema'] ); ?> /> <?php esc_html_e( 'Enable Schema.org structured data', 'wb-listora' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Breadcrumbs', 'wb-listora' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_breadcrumbs]" value="1" <?php checked( $s['enable_breadcrumbs'] ?? $d['enable_breadcrumbs'] ); ?> /> <?php esc_html_e( 'Enable breadcrumbs (JSON-LD)', 'wb-listora' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Sitemap', 'wb-listora' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_sitemap]" value="1" <?php checked( $s['enable_sitemap'] ?? $d['enable_sitemap'] ); ?> /> <?php esc_html_e( 'Add listings to WordPress sitemap', 'wb-listora' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Open Graph', 'wb-listora' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_opengraph]" value="1" <?php checked( $s['enable_opengraph'] ?? $d['enable_opengraph'] ); ?> /> <?php esc_html_e( 'Add Open Graph + Twitter Card meta tags', 'wb-listora' ); ?></label></td>
			</tr>
		</table>
		<?php
	}

	private static function render_advanced_tab() {
		$s = get_option( self::OPTION_KEY, array() );
		$d = wb_listora_get_default_settings();
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Data management', 'wb-listora' ); ?></th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[delete_on_uninstall]" value="1" <?php checked( $s['delete_on_uninstall'] ?? $d['delete_on_uninstall'] ); ?> /> <?php esc_html_e( 'Delete all data on plugin uninstall', 'wb-listora' ); ?></label>
					<p class="description" style="color:#d63638;"><?php esc_html_e( 'Warning: This permanently removes all listings, reviews, settings, and custom tables.', 'wb-listora' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Cache TTL', 'wb-listora' ); ?> <span class="listora-help-tip" data-tip="<?php esc_attr_e( 'Time-to-live for cached search results and facet counts. Higher values improve performance but delay new listing visibility. Set to 0 to disable caching.', 'wb-listora' ); ?>">?</span></th>
				<td>
					<label><?php esc_html_e( 'Search:', 'wb-listora' ); ?> <input type="number" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[search_cache_ttl]" value="<?php echo esc_attr( $s['search_cache_ttl'] ?? $d['search_cache_ttl'] ); ?>" min="0" max="120" class="small-text" /> <?php esc_html_e( 'minutes', 'wb-listora' ); ?></label><br/>
					<label><?php esc_html_e( 'Facets:', 'wb-listora' ); ?> <input type="number" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[facet_cache_ttl]" value="<?php echo esc_attr( $s['facet_cache_ttl'] ?? $d['facet_cache_ttl'] ); ?>" min="0" max="120" class="small-text" /> <?php esc_html_e( 'minutes', 'wb-listora' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Maintenance', 'wb-listora' ); ?></th>
				<td>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=listora-settings&action=reindex' ), 'listora_reindex' ) ); ?>" class="button">
						<?php esc_html_e( 'Rebuild Search Index', 'wb-listora' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=listora-setup' ) ); ?>" class="button" style="margin-inline-start: 0.5rem;">
						<?php esc_html_e( 'Run Setup Wizard', 'wb-listora' ); ?>
					</a>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Debug', 'wb-listora' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[debug_logging]" value="1" <?php checked( $s['debug_logging'] ?? $d['debug_logging'] ); ?> /> <?php esc_html_e( 'Enable debug logging', 'wb-listora' ); ?></label></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render the Import / Export tab content.
	 *
	 * Includes JSON settings import/export and CSV listing data import/export.
	 */
	private static function render_import_export_tab() {
		$type_terms = get_terms(
			array(
				'taxonomy'   => 'listora_listing_type',
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $type_terms ) ) {
			$type_terms = array();
		}
		?>
		<div style="padding: 20px;">
			<?php // ── JSON Settings Import / Export ── ?>
			<h4 style="margin: 0 0 8px;"><?php esc_html_e( 'Export Settings', 'wb-listora' ); ?></h4>
			<p class="description" style="margin: 0 0 12px;"><?php esc_html_e( 'Download a JSON file containing all current plugin settings.', 'wb-listora' ); ?></p>
			<button type="button" class="listora-btn listora-btn--secondary" onclick="listoraExportSettings();">
				<i data-lucide="download"></i> <?php esc_html_e( 'Export Settings', 'wb-listora' ); ?>
			</button>

			<hr style="margin: 24px 0;" />

			<h4 style="margin: 0 0 8px;"><?php esc_html_e( 'Import Settings', 'wb-listora' ); ?></h4>
			<p class="description" style="margin: 0 0 12px;"><?php esc_html_e( 'Upload a previously exported JSON file to replace all settings.', 'wb-listora' ); ?></p>
			<input type="file" id="listora-import-file" accept=".json" style="margin-bottom: 12px;" />
			<br />
			<button type="button" class="listora-btn listora-btn--secondary" onclick="listoraImportSettings();">
				<i data-lucide="upload"></i> <?php esc_html_e( 'Import Settings', 'wb-listora' ); ?>
			</button>
			<span id="listora-import-status" style="margin-left: 12px;"></span>

			<hr style="margin: 24px 0;" />

			<?php // ── CSV Listing Data Import / Export ── ?>
			<h3 style="margin: 0 0 12px;"><?php esc_html_e( 'Listing Data (CSV)', 'wb-listora' ); ?></h3>
			<p class="description" style="margin: 0 0 16px;"><?php esc_html_e( 'Import listings from CSV or export your directory data.', 'wb-listora' ); ?></p>

			<div style="display:grid;grid-template-columns:1fr 1fr;gap:2rem;">
				<?php // ── Export Card ── ?>
				<div class="listora-card" style="padding:1.5rem;">
					<h4 style="margin:0 0 8px;"><i data-lucide="download" class="listora-icon--sm"></i> <?php esc_html_e( 'Export Listings', 'wb-listora' ); ?></h4>
					<p class="description"><?php esc_html_e( 'Download all listings as a CSV file for backup or migration.', 'wb-listora' ); ?></p>

					<div style="margin:1rem 0;">
						<label for="listora-csv-export-type" style="display:block;margin-bottom:0.25rem;font-weight:500;"><?php esc_html_e( 'Listing Type (optional)', 'wb-listora' ); ?></label>
						<select id="listora-csv-export-type" class="listora-filter-select" style="width:100%;">
							<option value=""><?php esc_html_e( 'All Types', 'wb-listora' ); ?></option>
							<?php foreach ( $type_terms as $term ) : ?>
								<option value="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<button type="button" id="listora-csv-export-btn" class="listora-btn listora-btn--primary">
						<i data-lucide="download"></i> <?php esc_html_e( 'Export CSV', 'wb-listora' ); ?>
					</button>
					<div id="listora-csv-export-status" style="margin-top:0.5rem;font-size:12px;"></div>

					<p style="margin-top:1rem;color:#757575;font-size:12px;"><strong><?php esc_html_e( 'WP-CLI:', 'wb-listora' ); ?></strong> <code>wp listora export --type=restaurant --output=file.csv</code></p>
				</div>

				<?php // ── Import Card ── ?>
				<div class="listora-card" style="padding:1.5rem;">
					<h4 style="margin:0 0 8px;"><i data-lucide="upload" class="listora-icon--sm"></i> <?php esc_html_e( 'Import Listings', 'wb-listora' ); ?></h4>
					<p class="description"><?php esc_html_e( 'Import listings from a CSV file. The first row must be column headers.', 'wb-listora' ); ?></p>

					<div style="margin:1rem 0;">
						<label for="listora-csv-import-type" style="display:block;margin-bottom:0.25rem;font-weight:500;"><?php esc_html_e( 'Listing Type', 'wb-listora' ); ?> <span style="color:#d63638;">*</span></label>
						<select id="listora-csv-import-type" class="listora-filter-select" style="width:100%;" required>
							<option value=""><?php esc_html_e( 'Select a type...', 'wb-listora' ); ?></option>
							<?php foreach ( $type_terms as $term ) : ?>
								<option value="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div style="margin:1rem 0;">
						<label for="listora-csv-import-file" style="display:block;margin-bottom:0.25rem;font-weight:500;"><?php esc_html_e( 'CSV File', 'wb-listora' ); ?> <span style="color:#d63638;">*</span></label>
						<input type="file" id="listora-csv-import-file" accept=".csv,text/csv">
					</div>

					<div style="margin:1rem 0;">
						<label style="display:flex;align-items:center;gap:0.5rem;">
							<input type="checkbox" id="listora-csv-import-dryrun">
							<?php esc_html_e( 'Dry run (validate only, no listings created)', 'wb-listora' ); ?>
						</label>
					</div>

					<button type="button" id="listora-csv-import-btn" class="listora-btn listora-btn--primary">
						<i data-lucide="upload"></i> <?php esc_html_e( 'Import CSV', 'wb-listora' ); ?>
					</button>
					<div id="listora-csv-import-status" style="margin-top:0.5rem;font-size:12px;"></div>

					<p style="margin-top:1rem;color:#757575;font-size:12px;"><strong><?php esc_html_e( 'WP-CLI:', 'wb-listora' ); ?></strong> <code>wp listora import &lt;file.csv&gt; --type=restaurant</code></p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Migration tab content (non-form).
	 *
	 * Shows a card for each supported source plugin with detection status,
	 * listing counts, and migration controls.
	 */
	private static function render_migration_tab() {
		$migrators = \WBListora\ImportExport\Migration_Base::get_migrators();

		if ( empty( $migrators ) ) {
			echo '<div style="padding: 20px;">';
			echo '<p>' . esc_html__( 'No migration sources are available.', 'wb-listora' ) . '</p>';
			echo '</div>';
			return;
		}

		echo '<div class="listora-migration-grid" style="padding: 20px;">';

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
				echo '<button type="button" class="listora-btn listora-btn--primary listora-migration-start" data-source="' . esc_attr( $slug ) . '" data-count="' . esc_attr( (string) $count ) . '">';
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

		// Inline migration JS.
		self::render_migration_js();
	}

	/**
	 * Render inline JavaScript for the migration tab.
	 */
	private static function render_migration_js() {
		$nonce = wp_create_nonce( 'listora_migration' );
		?>
		<script>
		document.addEventListener( 'DOMContentLoaded', function() {
			var buttons = document.querySelectorAll( '.listora-migration-start' );

			buttons.forEach( function( btn ) {
				btn.addEventListener( 'click', function() {
					var source = btn.dataset.source;
					var total  = parseInt( btn.dataset.count, 10 ) || 0;
					var dryRun = document.querySelector( '.listora-migration-dryrun[data-source="' + source + '"]' );
					var isDry  = dryRun ? dryRun.checked : false;

					// Disable all start buttons during migration.
					buttons.forEach( function( b ) { b.disabled = true; } );

					// Show progress.
					var progress = document.getElementById( 'listora-progress-' + source );
					var fill     = document.getElementById( 'listora-fill-' + source );
					var stats    = document.getElementById( 'listora-stats-' + source );
					var pctEl    = document.getElementById( 'listora-pct-' + source );
					var resultEl = document.getElementById( 'listora-result-' + source );

					progress.classList.add( 'is-active' );
					resultEl.classList.remove( 'is-visible' );
					fill.style.width = '0%';
					stats.textContent = '<?php echo esc_js( __( 'Starting...', 'wb-listora' ) ); ?>';

					btn.textContent = '<?php echo esc_js( __( 'Migrating...', 'wb-listora' ) ); ?>';
					btn.classList.add( 'listora-btn--migrating' );

					// Send AJAX request.
					var formData = new FormData();
					formData.append( 'action', 'listora_run_migration' );
					formData.append( '_nonce', '<?php echo esc_js( $nonce ); ?>' );
					formData.append( 'source', source );
					formData.append( 'dry_run', isDry ? '1' : '0' );

					fetch( ajaxurl, { method: 'POST', body: formData } )
						.then( function( response ) { return response.json(); } )
						.then( function( data ) {
							if ( data.success ) {
								var res = data.data;

								fill.style.width = '100%';
								fill.classList.add( 'listora-migration-progress__fill--complete' );
								pctEl.textContent = '100%';

								var msg = '<?php echo esc_js( __( 'Imported:', 'wb-listora' ) ); ?> ' + res.imported;
								msg += ', <?php echo esc_js( __( 'Skipped:', 'wb-listora' ) ); ?> ' + res.skipped;
								msg += ', <?php echo esc_js( __( 'Errors:', 'wb-listora' ) ); ?> ' + res.errors;
								stats.textContent = msg;

								// Show result.
								var resultClass = res.errors > 0 ? 'listora-migration-result--error' : ( isDry ? 'listora-migration-result--dryrun' : 'listora-migration-result--success' );
								var resultMsg = res.errors > 0
									? '<?php echo esc_js( __( 'Migration completed with errors. Check the logs for details.', 'wb-listora' ) ); ?>'
									: ( isDry
										? '<?php echo esc_js( __( 'Dry run complete. No data was imported. Run again without dry run to import.', 'wb-listora' ) ); ?>'
										: '<?php echo esc_js( __( 'Migration completed successfully.', 'wb-listora' ) ); ?>' );

								resultEl.className = 'listora-migration-result is-visible ' + resultClass;
								resultEl.textContent = resultMsg;

								btn.textContent = '<?php echo esc_js( __( 'Complete', 'wb-listora' ) ); ?>';
								btn.classList.remove( 'listora-btn--migrating' );
							} else {
								stats.textContent = data.data.message || '<?php echo esc_js( __( 'Migration failed.', 'wb-listora' ) ); ?>';
								resultEl.className = 'listora-migration-result is-visible listora-migration-result--error';
								resultEl.textContent = data.data.message || '<?php echo esc_js( __( 'An error occurred during migration.', 'wb-listora' ) ); ?>';
								btn.textContent = '<?php echo esc_js( __( 'Start Migration', 'wb-listora' ) ); ?>';
								btn.classList.remove( 'listora-btn--migrating' );
							}

							// Re-enable buttons.
							buttons.forEach( function( b ) { b.disabled = false; } );
						} )
						.catch( function( err ) {
							stats.textContent = '<?php echo esc_js( __( 'Request failed.', 'wb-listora' ) ); ?>';
							resultEl.className = 'listora-migration-result is-visible listora-migration-result--error';
							resultEl.textContent = err.message || '<?php echo esc_js( __( 'Network error. Please try again.', 'wb-listora' ) ); ?>';
							btn.textContent = '<?php echo esc_js( __( 'Start Migration', 'wb-listora' ) ); ?>';
							btn.classList.remove( 'listora-btn--migrating' );
							buttons.forEach( function( b ) { b.disabled = false; } );
						} );
				} );
			} );
		} );
		</script>
		<style>
		@keyframes listora-spin { to { transform: rotate(360deg); } }
		.listora-btn--migrating { pointer-events: none; opacity: 0.7; }
		</style>
		<?php
	}
}
