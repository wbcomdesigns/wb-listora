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

		// Credits overflow cost & purchase URL live in top-level options so
		// they can be read by the Credits SDK registry at file-load time
		// (before wb_listora_settings is hydrated). Registered in the same
		// option group so the Submissions tab form persists them via options.php.
		register_setting(
			'wb_listora_settings_group',
			\WBListora\Core\Listing_Limits::OVERFLOW_COST_OPTION,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( \WBListora\Core\Listing_Limits::class, 'sanitize_overflow_cost' ),
				'default'           => 10,
			)
		);

		// Low credit balance alert threshold — top-level option so the SDK can
		// read it without loading the full settings array. Registered in the
		// same group so the Credits tab persists it via options.php.
		register_setting(
			'wb_listora_settings_group',
			'wb_listora_low_credit_threshold',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 5,
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
		$defaults = wb_listora_get_default_settings();
		$old      = get_option( self::OPTION_KEY, array() );

		// Start from existing values so unsubmitted keys (e.g. booleans on
		// tabs that weren't the one just saved) keep their current value.
		// Without this, saving the General tab would zero out every boolean
		// on Submissions/Maps/Reviews/etc — checkboxes aren't included in
		// POST unless they're on the form being submitted.
		$sanitized = is_array( $old ) ? $old : array();

		foreach ( $defaults as $key => $default ) {
			if ( ! isset( $input[ $key ] ) ) {
				// Key absent from POST — keep previous value, fall back to
				// default only if there's nothing previous.
				if ( ! array_key_exists( $key, $sanitized ) ) {
					$sanitized[ $key ] = $default;
				}
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

		// Credit cost fields: force non-negative integers.
		if ( isset( $input['featured_credit_cost'] ) ) {
			$sanitized['featured_credit_cost'] = absint( $input['featured_credit_cost'] );
		}
		if ( isset( $input['featured_duration_days'] ) ) {
			// Non-negative integer. 0 = permanent.
			$sanitized['featured_duration_days'] = max( 0, (int) $input['featured_duration_days'] );
		}
		if ( isset( $input['default_listing_credit_cost'] ) ) {
			$sanitized['default_listing_credit_cost'] = absint( $input['default_listing_credit_cost'] );
		}

		// Validate captcha_provider against allowed values.
		$allowed_captcha = array( 'none', 'recaptcha_v3', 'cloudflare_turnstile' );
		if ( ! in_array( $sanitized['captcha_provider'] ?? 'none', $allowed_captcha, true ) ) {
			$sanitized['captcha_provider'] = 'none';
		}

		// Listing limits per role — merge the numeric map with the "Unlimited"
		// checkbox map. If Unlimited is checked, save -1 regardless of the
		// number field. Preserve when the tab isn't submitted.
		$has_role_map      = isset( $input['listing_limits_per_role'] ) && is_array( $input['listing_limits_per_role'] );
		$has_unlimited_map = isset( $input['listing_limits_unlimited'] ) && is_array( $input['listing_limits_unlimited'] );

		if ( $has_role_map || $has_unlimited_map ) {
			// Start from sanitized numeric values (clamped to >= 0 via the merged map below).
			$raw_map = $has_role_map ? $input['listing_limits_per_role'] : array();

			// Overlay "unlimited" flags as -1 so sanitize_map's clamp (>= -1) keeps them.
			if ( $has_unlimited_map ) {
				foreach ( $input['listing_limits_unlimited'] as $role_slug => $unlimited ) {
					if ( ! empty( $unlimited ) ) {
						$raw_map[ $role_slug ] = -1;
					}
				}
			}

			$sanitized['listing_limits_per_role'] = \WBListora\Core\Listing_Limits::sanitize_map( $raw_map );
		} elseif ( isset( $old['listing_limits_per_role'] ) ) {
			$sanitized['listing_limits_per_role'] = $old['listing_limits_per_role'];
		}

		if ( isset( $input['listing_limits_default'] ) || isset( $input['listing_limits_default_unlimited'] ) ) {
			if ( ! empty( $input['listing_limits_default_unlimited'] ) ) {
				$sanitized['listing_limits_default'] = -1;
			} else {
				$sanitized['listing_limits_default'] = \WBListora\Core\Listing_Limits::sanitize_default( $input['listing_limits_default'] ?? 0 );
			}
		} elseif ( isset( $old['listing_limits_default'] ) ) {
			$sanitized['listing_limits_default'] = $old['listing_limits_default'];
		}

		// Limit period — whitelist to known values, preserve when saving other tabs.
		if ( isset( $input['listing_limits_period'] ) ) {
			$period                             = sanitize_key( (string) $input['listing_limits_period'] );
			$sanitized['listing_limits_period'] = in_array( $period, array( 'lifetime', 'calendar_month', 'rolling_30d' ), true )
				? $period
				: 'lifetime';
		} elseif ( isset( $old['listing_limits_period'] ) ) {
			$sanitized['listing_limits_period'] = $old['listing_limits_period'];
		}

		// Beyond-limit behavior — whitelist.
		if ( isset( $input['listing_beyond_limit_behavior'] ) ) {
			$behavior                                   = sanitize_key( (string) $input['listing_beyond_limit_behavior'] );
			$sanitized['listing_beyond_limit_behavior'] = in_array( $behavior, array( 'block', 'credits' ), true )
				? $behavior
				: 'block';
		} elseif ( isset( $old['listing_beyond_limit_behavior'] ) ) {
			$sanitized['listing_beyond_limit_behavior'] = $old['listing_beyond_limit_behavior'];
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
					'credits'       => array(
						'label' => __( 'Credits', 'wb-listora' ),
						'icon'  => 'coins',
						'desc'  => __( 'Credit costs, listing limits, and payment integrations.', 'wb-listora' ),
					),
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
			'credits'       => 'credits',
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
			'credits'       => 'render_credits_tab',
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
							<div class="listora-settings-body">
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
						</div>
						<div class="listora-settings-section__footer">
							<button type="button" class="listora-btn listora-btn--danger" data-listora-action="reset-defaults">
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
								<div class="listora-settings-body">
								<?php
								$renderer = self::get_tab_renderer( $tab_id );
								if ( $renderer && method_exists( __CLASS__, $renderer ) ) {
									self::$renderer();
								}
								do_action( 'wb_listora_settings_tab_content', $tab_id );
								?>
								</div>
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
			window.listoraConfirm( {
				title:        '<?php echo esc_js( __( 'Reset all settings?', 'wb-listora' ) ); ?>',
				message:      '<?php echo esc_js( __( 'Every tab will be restored to its default value. This cannot be undone.', 'wb-listora' ) ); ?>',
				confirmLabel: '<?php echo esc_js( __( 'Reset settings', 'wb-listora' ) ); ?>',
				tone:         'danger'
			} ).then( function ( ok ) {
				if ( ! ok ) { return; }
				wp.apiFetch( { path: '/listora/v1/settings', method: 'DELETE' } ).then( function() {
					window.location.reload();
				}).catch( function( err ) {
					if (window.listoraToast) { listoraToast( '<?php echo esc_js( __( 'Reset failed:', 'wb-listora' ) ); ?> ' + ( err.message || err ), {type:'error'} ); }
				});
			} );
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

				window.listoraConfirm( {
					title:        '<?php echo esc_js( __( 'Replace current settings?', 'wb-listora' ) ); ?>',
					message:      '<?php echo esc_js( __( 'Your current settings will be overwritten with values from the imported file.', 'wb-listora' ) ); ?>',
					confirmLabel: '<?php echo esc_js( __( 'Replace settings', 'wb-listora' ) ); ?>',
					tone:         'danger'
				} ).then( function ( ok ) {
					if ( ! ok ) { return; }
					listoraDoImport( data, status );
				} );
			};
			reader.readAsText( fileInput.files[0] );
		}

		function listoraDoImport( data, status ) {
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
		}
		</script>
		<?php
	}

	// ─── Tab Renderers ───

	private static function render_general_tab() {
		$s = get_option( self::OPTION_KEY, array() );
		$d = wb_listora_get_default_settings();

		$currencies = array(
			'USD' => '$',
			'EUR' => '€',
			'GBP' => '£',
			'JPY' => '¥',
			'INR' => '₹',
			'AUD' => 'A$',
			'CAD' => 'C$',
			'CHF' => 'CHF',
			'CNY' => '¥',
			'BRL' => 'R$',
		);
		$current    = $s['currency'] ?? $d['currency'];
		$opt        = esc_attr( self::OPTION_KEY );
		?>
		<div class="listora-settings-pane">

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Basics', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Core directory configuration — how listings are paginated, accessed by URL, priced, and measured.', 'wb-listora' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="per_page"><?php esc_html_e( 'Listings per page', 'wb-listora' ); ?></label></th>
							<td>
								<input type="number" id="per_page" name="<?php echo esc_attr( $opt ); ?>[per_page]" value="<?php echo esc_attr( $s['per_page'] ?? $d['per_page'] ); ?>" min="1" max="100" class="small-text" />
								<p class="description"><?php esc_html_e( 'Number of listings shown per page in archive, search, and grid views.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="listing_slug"><?php esc_html_e( 'Listing URL slug', 'wb-listora' ); ?></label></th>
							<td>
								<input type="text" id="listing_slug" name="<?php echo esc_attr( $opt ); ?>[listing_slug]" value="<?php echo esc_attr( $s['listing_slug'] ?? $d['listing_slug'] ); ?>" class="regular-text" />
								<p class="description"><?php esc_html_e( 'Single listing URL segment (e.g. /listing/{slug}/). Changing this re-flushes rewrite rules.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="currency"><?php esc_html_e( 'Currency', 'wb-listora' ); ?></label></th>
							<td>
								<select id="currency" name="<?php echo esc_attr( $opt ); ?>[currency]" class="listora-currency-select">
									<?php foreach ( $currencies as $code => $symbol ) : ?>
										<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $current, $code ); ?>>
											<?php echo esc_html( sprintf( '%s (%s)', $code, $symbol ) ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Symbol shown alongside prices and pricing ranges on listing cards and detail pages.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Distance unit', 'wb-listora' ); ?></th>
							<td>
								<fieldset>
									<legend class="screen-reader-text"><?php esc_html_e( 'Distance unit', 'wb-listora' ); ?></legend>
									<div class="listora-field-group">
										<label><input type="radio" name="<?php echo esc_attr( $opt ); ?>[distance_unit]" value="km" <?php checked( $s['distance_unit'] ?? $d['distance_unit'], 'km' ); ?> /> <?php esc_html_e( 'Kilometers (km)', 'wb-listora' ); ?></label>
										<label><input type="radio" name="<?php echo esc_attr( $opt ); ?>[distance_unit]" value="mi" <?php checked( $s['distance_unit'] ?? $d['distance_unit'], 'mi' ); ?> /> <?php esc_html_e( 'Miles (mi)', 'wb-listora' ); ?></label>
									</div>
								</fieldset>
								<p class="description"><?php esc_html_e( 'Used for the "near me" search radius and distance shown on listing cards.', 'wb-listora' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Listing Lifecycle', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'How long listings stay active and whether business owners can claim existing entries.', 'wb-listora' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Listing expiration', 'wb-listora' ); ?></th>
							<td>
								<fieldset>
									<legend class="screen-reader-text"><?php esc_html_e( 'Listing expiration', 'wb-listora' ); ?></legend>
									<label>
										<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[enable_expiration]" value="0" />
										<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[enable_expiration]" value="1" <?php checked( $s['enable_expiration'] ?? $d['enable_expiration'] ); ?> />
										<?php esc_html_e( 'Enable automatic listing expiration', 'wb-listora' ); ?>
									</label>
								</fieldset>
								<p class="description"><?php esc_html_e( 'When enabled, listings are unpublished after the default expiration period. Reminder emails are sent 7 days and 1 day before expiry.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="default_expiration"><?php esc_html_e( 'Default expiration', 'wb-listora' ); ?></label></th>
							<td>
								<input type="number" id="default_expiration" name="<?php echo esc_attr( $opt ); ?>[default_expiration]" value="<?php echo esc_attr( $s['default_expiration'] ?? $d['default_expiration'] ); ?>" min="0" class="small-text" />
								<span><?php esc_html_e( 'days', 'wb-listora' ); ?></span>
								<p class="description"><?php esc_html_e( 'Days before a new listing expires. Set to 0 for listings that never expire.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Claiming', 'wb-listora' ); ?></th>
							<td>
								<label>
									<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[enable_claiming]" value="0" />
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[enable_claiming]" value="1" <?php checked( $s['enable_claiming'] ?? $d['enable_claiming'] ); ?> />
									<?php esc_html_e( 'Allow business owners to claim their listings', 'wb-listora' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Adds a "Claim this listing" button to unclaimed listings. Admins review each claim before ownership is transferred.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Listing renewal', 'wb-listora' ); ?></th>
							<td>
								<label>
									<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[enable_renewal]" value="0" />
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[enable_renewal]" value="1" <?php checked( $s['enable_renewal'] ?? $d['enable_renewal'] ); ?> />
									<?php esc_html_e( 'Allow listing owners to renew their listings from the dashboard', 'wb-listora' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Adds a "Renew Now" button to expiring/expired listings on the user dashboard.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="renewal_window_days"><?php esc_html_e( 'Renewal window', 'wb-listora' ); ?></label></th>
							<td>
								<input type="number" id="renewal_window_days" name="<?php echo esc_attr( $opt ); ?>[renewal_window_days]" value="<?php echo esc_attr( $s['renewal_window_days'] ?? $d['renewal_window_days'] ); ?>" min="1" max="365" class="small-text" />
								<span><?php esc_html_e( 'days before expiry', 'wb-listora' ); ?></span>
								<p class="description"><?php esc_html_e( 'How many days before expiry users can start renewing. Already-expired listings can always renew.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="default_renewal_duration_days"><?php esc_html_e( 'Renewal duration', 'wb-listora' ); ?></label></th>
							<td>
								<input type="number" id="default_renewal_duration_days" name="<?php echo esc_attr( $opt ); ?>[default_renewal_duration_days]" value="<?php echo esc_attr( $s['default_renewal_duration_days'] ?? $d['default_renewal_duration_days'] ); ?>" min="1" class="small-text" />
								<span><?php esc_html_e( 'days', 'wb-listora' ); ?></span>
								<p class="description"><?php esc_html_e( 'Default extension applied when a listing is renewed. Pricing plans (Pro) override this per plan.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="default_renewal_credit_cost"><?php esc_html_e( 'Renewal cost', 'wb-listora' ); ?></label></th>
							<td>
								<input type="number" id="default_renewal_credit_cost" name="<?php echo esc_attr( $opt ); ?>[default_renewal_credit_cost]" value="<?php echo esc_attr( $s['default_renewal_credit_cost'] ?? $d['default_renewal_credit_cost'] ); ?>" min="0" class="small-text" />
								<span><?php esc_html_e( 'credits', 'wb-listora' ); ?></span>
								<p class="description"><?php esc_html_e( 'Default credit cost for renewals. Set to 0 for free renewals. Plans (Pro) override this per plan.', 'wb-listora' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

		</div>
		<?php
	}

	private static function render_maps_tab() {
		$s   = get_option( self::OPTION_KEY, array() );
		$d   = wb_listora_get_default_settings();
		$opt = esc_attr( self::OPTION_KEY );
		?>
		<div class="listora-settings-pane">

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Map Provider', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'The tile service used to render listing maps across the directory.', 'wb-listora' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Provider', 'wb-listora' ); ?></th>
							<td>
								<fieldset>
									<legend class="screen-reader-text"><?php esc_html_e( 'Map Provider', 'wb-listora' ); ?></legend>
									<div class="listora-field-group">
										<label>
											<input type="radio" name="<?php echo esc_attr( $opt ); ?>[map_provider]" value="osm" <?php checked( $s['map_provider'] ?? $d['map_provider'], 'osm' ); ?> />
											<strong><?php esc_html_e( 'OpenStreetMap', 'wb-listora' ); ?></strong>
											<span class="listora-field-group__hint"> — <?php esc_html_e( 'free, no API key required.', 'wb-listora' ); ?></span>
										</label>
										<label class="listora-field-group__disabled">
											<input type="radio" name="<?php echo esc_attr( $opt ); ?>[map_provider]" value="google" <?php checked( $s['map_provider'] ?? $d['map_provider'], 'google' ); ?> disabled />
											<strong><?php esc_html_e( 'Google Maps', 'wb-listora' ); ?></strong>
											<span class="listora-field-group__hint"> — <?php esc_html_e( 'requires API key (Pro).', 'wb-listora' ); ?></span>
										</label>
									</div>
								</fieldset>
								<p class="description"><?php esc_html_e( 'OpenStreetMap works out of the box. Google Maps requires the Pro add-on and a key with Maps JavaScript API enabled.', 'wb-listora' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Default View', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Where maps initially center and how far they zoom when no listings are in view.', 'wb-listora' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="map_default_lat"><?php esc_html_e( 'Default latitude', 'wb-listora' ); ?></label></th>
							<td>
								<input type="text" id="map_default_lat" name="<?php echo esc_attr( $opt ); ?>[map_default_lat]" value="<?php echo esc_attr( $s['map_default_lat'] ?? $d['map_default_lat'] ); ?>" class="regular-text" />
								<p class="description"><?php esc_html_e( 'Decimal degrees. Example: 40.7128 (New York).', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="map_default_lng"><?php esc_html_e( 'Default longitude', 'wb-listora' ); ?></label></th>
							<td>
								<input type="text" id="map_default_lng" name="<?php echo esc_attr( $opt ); ?>[map_default_lng]" value="<?php echo esc_attr( $s['map_default_lng'] ?? $d['map_default_lng'] ); ?>" class="regular-text" />
								<p class="description"><?php esc_html_e( 'Decimal degrees. Example: -74.0060 (New York).', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="map_default_zoom"><?php esc_html_e( 'Default zoom', 'wb-listora' ); ?></label></th>
							<td>
								<input type="number" id="map_default_zoom" name="<?php echo esc_attr( $opt ); ?>[map_default_zoom]" value="<?php echo esc_attr( $s['map_default_zoom'] ?? $d['map_default_zoom'] ); ?>" min="1" max="20" class="small-text" />
								<p class="description"><?php esc_html_e( 'Zoom level 1 (world) to 20 (street). City views typically use 12–14.', 'wb-listora' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Options', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Interaction and display behavior for map-enabled blocks and pages.', 'wb-listora' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Marker clustering', 'wb-listora' ); ?></th>
							<td>
								<label>
									<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[map_clustering]" value="0" />
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[map_clustering]" value="1" <?php checked( $s['map_clustering'] ?? $d['map_clustering'] ); ?> />
									<?php esc_html_e( 'Group nearby markers into clusters', 'wb-listora' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Improves performance and readability on dense maps by collapsing clustered listings into a single badge.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Search on drag', 'wb-listora' ); ?></th>
							<td>
								<label>
									<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[map_search_on_drag]" value="0" />
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[map_search_on_drag]" value="1" <?php checked( $s['map_search_on_drag'] ?? $d['map_search_on_drag'] ); ?> />
									<?php esc_html_e( 'Re-run search when the user pans or zooms', 'wb-listora' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Automatically refreshes results based on the current map viewport.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="map_max_markers"><?php esc_html_e( 'Max markers', 'wb-listora' ); ?></label></th>
							<td>
								<input type="number" id="map_max_markers" name="<?php echo esc_attr( $opt ); ?>[map_max_markers]" value="<?php echo esc_attr( $s['map_max_markers'] ?? $d['map_max_markers'] ); ?>" min="50" max="5000" class="small-text" />
								<p class="description"><?php esc_html_e( 'Upper cap on markers rendered at once. Higher values impact performance on low-powered devices.', 'wb-listora' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

		</div>
		<?php
	}

	private static function render_submissions_tab() {
		$s   = get_option( self::OPTION_KEY, array() );
		$d   = wb_listora_get_default_settings();
		$opt = esc_attr( self::OPTION_KEY );
		?>
		<div class="listora-settings-pane">

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Submission Rules', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Who can submit listings, how they are moderated, and the limits and protections that apply to each submission.', 'wb-listora' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Frontend submission', 'wb-listora' ); ?></th>
							<td>
								<label>
									<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[enable_submission]" value="0" />
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[enable_submission]" value="1" <?php checked( $s['enable_submission'] ?? $d['enable_submission'] ); ?> />
									<?php esc_html_e( 'Allow users to submit listings from the frontend', 'wb-listora' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Enables the submission form block and dashboard "Add listing" action.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Moderation', 'wb-listora' ); ?></th>
							<td>
								<fieldset>
									<legend class="screen-reader-text"><?php esc_html_e( 'Moderation', 'wb-listora' ); ?></legend>
									<div class="listora-field-group">
										<label>
											<input type="radio" name="<?php echo esc_attr( $opt ); ?>[moderation]" value="manual" <?php checked( $s['moderation'] ?? $d['moderation'], 'manual' ); ?> />
											<strong><?php esc_html_e( 'Require admin approval', 'wb-listora' ); ?></strong>
											<span class="listora-field-group__hint"> — <?php esc_html_e( 'new submissions stay in Pending until reviewed.', 'wb-listora' ); ?></span>
										</label>
										<label>
											<input type="radio" name="<?php echo esc_attr( $opt ); ?>[moderation]" value="auto_approve" <?php checked( $s['moderation'] ?? $d['moderation'], 'auto_approve' ); ?> />
											<strong><?php esc_html_e( 'Auto-approve', 'wb-listora' ); ?></strong>
											<span class="listora-field-group__hint"> — <?php esc_html_e( 'listings publish immediately. Combine with CAPTCHA to reduce spam.', 'wb-listora' ); ?></span>
										</label>
									</div>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Guest submissions', 'wb-listora' ); ?></th>
							<td>
								<label>
									<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[enable_guest_submission]" value="0" />
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[enable_guest_submission]" value="1" <?php checked( $s['enable_guest_submission'] ?? $d['enable_guest_submission'] ); ?> />
									<?php esc_html_e( 'Allow non-logged-in users to submit (inline registration)', 'wb-listora' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Guests provide their name and email. An account is created automatically.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Email verification', 'wb-listora' ); ?></th>
							<td>
								<label>
									<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[guest_email_verification]" value="0" />
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[guest_email_verification]" value="1" <?php checked( $s['guest_email_verification'] ?? $d['guest_email_verification'] ); ?> />
									<?php esc_html_e( 'Require guests to verify their email before the listing publishes', 'wb-listora' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'When enabled: the listing is held in "Pending Email Verification" until the guest clicks the link. Logged-in users always skip this step.', 'wb-listora' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="verification_link_expiry_hours"><?php esc_html_e( 'Verification link expiry', 'wb-listora' ); ?></label></th>
							<td>
								<input type="number" id="verification_link_expiry_hours" name="<?php echo esc_attr( $opt ); ?>[verification_link_expiry_hours]" value="<?php echo esc_attr( $s['verification_link_expiry_hours'] ?? $d['verification_link_expiry_hours'] ); ?>" min="1" max="168" class="small-text" />
								<span><?php esc_html_e( 'hours', 'wb-listora' ); ?></span>
								<p class="description"><?php esc_html_e( 'How long verification links remain valid (1–168 hours). Default 24.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="unverified_listings_max_days"><?php esc_html_e( 'Cleanup unverified listings', 'wb-listora' ); ?></label></th>
							<td>
								<input type="number" id="unverified_listings_max_days" name="<?php echo esc_attr( $opt ); ?>[unverified_listings_max_days]" value="<?php echo esc_attr( $s['unverified_listings_max_days'] ?? $d['unverified_listings_max_days'] ); ?>" min="1" max="90" class="small-text" />
								<span><?php esc_html_e( 'days', 'wb-listora' ); ?></span>
								<select name="<?php echo esc_attr( $opt ); ?>[unverified_listings_action]">
									<option value="trash" <?php selected( $s['unverified_listings_action'] ?? $d['unverified_listings_action'], 'trash' ); ?>><?php esc_html_e( 'Move to trash', 'wb-listora' ); ?></option>
									<option value="delete" <?php selected( $s['unverified_listings_action'] ?? $d['unverified_listings_action'], 'delete' ); ?>><?php esc_html_e( 'Permanently delete', 'wb-listora' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Daily cron disposes of listings that stayed in "Pending Email Verification" past this window.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="max_upload_size"><?php esc_html_e( 'Max file size', 'wb-listora' ); ?></label></th>
							<td>
								<input type="number" id="max_upload_size" name="<?php echo esc_attr( $opt ); ?>[max_upload_size]" value="<?php echo esc_attr( $s['max_upload_size'] ?? $d['max_upload_size'] ); ?>" min="1" max="50" class="small-text" />
								<span><?php esc_html_e( 'MB', 'wb-listora' ); ?></span>
								<p class="description"><?php esc_html_e( 'Maximum size for each uploaded image or attachment. Capped by your server\'s upload_max_filesize.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="max_gallery_images"><?php esc_html_e( 'Max gallery images', 'wb-listora' ); ?></label></th>
							<td>
								<input type="number" id="max_gallery_images" name="<?php echo esc_attr( $opt ); ?>[max_gallery_images]" value="<?php echo esc_attr( $s['max_gallery_images'] ?? $d['max_gallery_images'] ); ?>" min="1" max="100" class="small-text" />
								<p class="description"><?php esc_html_e( 'Maximum number of gallery images a user can attach to a single listing.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="captcha_provider"><?php esc_html_e( 'CAPTCHA provider', 'wb-listora' ); ?></label></th>
							<td>
								<select id="captcha_provider" name="<?php echo esc_attr( $opt ); ?>[captcha_provider]">
									<option value="none" <?php selected( $s['captcha_provider'] ?? $d['captcha_provider'], 'none' ); ?>><?php esc_html_e( 'None', 'wb-listora' ); ?></option>
									<option value="recaptcha_v3" <?php selected( $s['captcha_provider'] ?? $d['captcha_provider'], 'recaptcha_v3' ); ?>><?php esc_html_e( 'Google reCAPTCHA v3', 'wb-listora' ); ?></option>
									<option value="cloudflare_turnstile" <?php selected( $s['captcha_provider'] ?? $d['captcha_provider'], 'cloudflare_turnstile' ); ?>><?php esc_html_e( 'Cloudflare Turnstile', 'wb-listora' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Protects submission and review forms from spam. Requires a site key and secret from the selected provider.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="captcha_site_key"><?php esc_html_e( 'CAPTCHA site key', 'wb-listora' ); ?></label></th>
							<td>
								<input type="text" id="captcha_site_key" name="<?php echo esc_attr( $opt ); ?>[captcha_site_key]" value="<?php echo esc_attr( $s['captcha_site_key'] ?? $d['captcha_site_key'] ); ?>" class="regular-text" />
								<p class="description"><?php esc_html_e( 'Public site key from your CAPTCHA provider dashboard.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="captcha_secret_key"><?php esc_html_e( 'CAPTCHA secret key', 'wb-listora' ); ?></label></th>
							<td>
								<input type="password" id="captcha_secret_key" name="<?php echo esc_attr( $opt ); ?>[captcha_secret_key]" value="<?php echo esc_attr( $s['captcha_secret_key'] ?? $d['captcha_secret_key'] ); ?>" class="regular-text" autocomplete="off" />
								<p class="description"><?php esc_html_e( 'Private secret key used for server-side verification. Never share publicly.', 'wb-listora' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

		</div>
		<?php
	}

	/**
	 * Render the Credits tab — credit costs, listing limits per role, SDK config,
	 * and action buttons linking to transactions/mappings/pricing plans.
	 */
	private static function render_credits_tab() {
		$s   = get_option( self::OPTION_KEY, array() );
		$d   = wb_listora_get_default_settings();
		$opt = esc_attr( self::OPTION_KEY );

		// Pro upsell (rendered only when Pro is inactive). Tells admins what
		// additional monetization features unlock with the paid plugin so they
		// don't assume "credits" is the ceiling.
		if ( function_exists( 'wb_listora_render_pro_cta' ) ) {
			wb_listora_render_pro_cta(
				array(
					'title'       => __( 'Sell more than credits', 'wb-listora' ),
					'description' => __( 'Pro adds pricing plans, coupons, credit packs, webhooks, and full transaction history.', 'wb-listora' ),
					'features'    => array(
						__( 'Flexible pricing plans (one-off + subscription)', 'wb-listora' ),
						__( 'Coupon codes with expiry and usage limits', 'wb-listora' ),
						__( 'WooCommerce, PMPro, MemberPress adapters', 'wb-listora' ),
						__( 'Transaction log + refund tooling', 'wb-listora' ),
					),
				)
			);
		}

		$featured_duration_value   = (int) ( $s['featured_duration_days'] ?? $d['featured_duration_days'] );
		$featured_duration_presets = array(
			7   => __( '7 days', 'wb-listora' ),
			30  => __( '30 days', 'wb-listora' ),
			90  => __( '90 days', 'wb-listora' ),
			365 => __( '365 days', 'wb-listora' ),
			0   => __( 'Permanent (no expiration)', 'wb-listora' ),
		);

		$low_threshold  = (int) get_option( 'wb_listora_low_credit_threshold', 5 );
		$webhook_url    = rest_url( WB_LISTORA_REST_NAMESPACE . '/webhooks/payment' );
		$webhook_secret = (string) get_option( 'wb_listora_pro_webhook_secret', '' );
		?>
		<div class="listora-settings-pane">

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Credit Costs', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Credits charged for submitting listings and upgrading them to Featured. Set any cost to 0 to make that action free.', 'wb-listora' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="listora_default_listing_credit_cost"><?php esc_html_e( 'Listing submission cost', 'wb-listora' ); ?></label></th>
							<td>
								<input
									type="number"
									id="listora_default_listing_credit_cost"
									name="<?php echo esc_attr( $opt ); ?>[default_listing_credit_cost]"
									value="<?php echo esc_attr( $s['default_listing_credit_cost'] ?? $d['default_listing_credit_cost'] ); ?>"
									min="0"
									step="1"
									class="small-text"
								/>
								<span><?php esc_html_e( 'credits', 'wb-listora' ); ?></span>
								<p class="description"><?php esc_html_e( 'Charged when submitting a standard listing without a paid plan. Set to 0 for free submissions.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="listora_featured_credit_cost"><?php esc_html_e( 'Featured upgrade cost', 'wb-listora' ); ?></label></th>
							<td>
								<input
									type="number"
									id="listora_featured_credit_cost"
									name="<?php echo esc_attr( $opt ); ?>[featured_credit_cost]"
									value="<?php echo esc_attr( $s['featured_credit_cost'] ?? $d['featured_credit_cost'] ); ?>"
									min="0"
									step="1"
									class="small-text"
								/>
								<span><?php esc_html_e( 'credits', 'wb-listora' ); ?></span>
								<p class="description"><?php esc_html_e( 'Charged when a user upgrades an existing listing to Featured. Set to 0 to make upgrades free.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="listora_featured_duration_days"><?php esc_html_e( 'Featured duration', 'wb-listora' ); ?></label></th>
							<td>
								<input
									type="number"
									id="listora_featured_duration_days"
									name="<?php echo esc_attr( $opt ); ?>[featured_duration_days]"
									value="<?php echo esc_attr( (string) $featured_duration_value ); ?>"
									min="0"
									step="1"
									class="small-text"
								/>
								<span><?php esc_html_e( 'days', 'wb-listora' ); ?></span>
								<select
									id="listora_featured_duration_preset"
									class="listora-featured-duration-preset"
									aria-label="<?php esc_attr_e( 'Featured duration presets', 'wb-listora' ); ?>"
									onchange="document.getElementById('listora_featured_duration_days').value=this.value;"
								>
									<option value=""><?php esc_html_e( 'Preset…', 'wb-listora' ); ?></option>
									<?php foreach ( $featured_duration_presets as $preset_days => $preset_label ) : ?>
										<option value="<?php echo esc_attr( (string) $preset_days ); ?>" <?php selected( $featured_duration_value, $preset_days ); ?>>
											<?php echo esc_html( $preset_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'How long a listing stays featured after upgrade. Set to 0 for permanent (no expiration).', 'wb-listora' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

			<?php self::render_listing_limits_section( $s ); ?>

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'SDK Configuration', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Low-balance alerts and the payment webhook endpoint used by the Credits SDK.', 'wb-listora' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="wb_listora_low_credit_threshold"><?php esc_html_e( 'Low Balance Alert', 'wb-listora' ); ?></label></th>
							<td>
								<input
									type="number"
									id="wb_listora_low_credit_threshold"
									name="wb_listora_low_credit_threshold"
									value="<?php echo esc_attr( (string) $low_threshold ); ?>"
									min="0"
									step="1"
									class="small-text"
								/>
								<span><?php esc_html_e( 'credits', 'wb-listora' ); ?></span>
								<p class="description"><?php esc_html_e( 'Email the user when their credit balance drops below this amount. Set to 0 to disable.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Webhook URL', 'wb-listora' ); ?></th>
							<td>
								<div class="listora-copy-field">
									<input type="text" readonly value="<?php echo esc_attr( $webhook_url ); ?>" class="large-text listora-copy-field__input" />
									<button type="button" class="button listora-copy-btn" data-copy-target="<?php echo esc_attr( $webhook_url ); ?>">
										<i data-lucide="copy"></i>
										<span class="listora-copy-btn__label"><?php esc_html_e( 'Copy', 'wb-listora' ); ?></span>
									</button>
								</div>
								<p class="description"><?php esc_html_e( 'Use this URL in your payment provider (Stripe, PayPal, etc.) to top up user credits on payment completion.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<?php if ( '' !== $webhook_secret ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Webhook Secret', 'wb-listora' ); ?></th>
							<td>
								<div class="listora-copy-field">
									<input type="text" readonly value="<?php echo esc_attr( $webhook_secret ); ?>" class="large-text listora-copy-field__input" />
									<button type="button" class="button listora-copy-btn" data-copy-target="<?php echo esc_attr( $webhook_secret ); ?>">
										<i data-lucide="copy"></i>
										<span class="listora-copy-btn__label"><?php esc_html_e( 'Copy', 'wb-listora' ); ?></span>
									</button>
								</div>
								<p class="description"><?php esc_html_e( 'Shared secret used to verify incoming webhook requests. Keep this value private.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</section>

			<div class="listora-settings-actions-row">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=listora-transactions' ) ); ?>" class="button">
					<?php esc_html_e( 'View Transaction Log', 'wb-listora' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=listora_plan' ) ); ?>" class="button">
					<?php esc_html_e( 'Manage Pricing Plans', 'wb-listora' ); ?>
				</a>
			</div>

		</div>
		<script>
		( function () {
			document.addEventListener( 'click', function ( e ) {
				var btn = e.target.closest( '.listora-copy-btn' );
				if ( ! btn ) { return; }
				e.preventDefault();
				var text = btn.getAttribute( 'data-copy-target' ) || '';
				if ( ! text ) { return; }
				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( text ).then( function () {
						var label = btn.querySelector( '.listora-copy-btn__label' );
						if ( label ) {
							var original = label.textContent;
							label.textContent = <?php echo wp_json_encode( __( 'Copied!', 'wb-listora' ) ); ?>;
							setTimeout( function () { label.textContent = original; }, 1500 );
						}
					} );
				} else {
					var input = btn.parentNode.querySelector( '.listora-copy-field__input' );
					if ( input ) { input.select(); document.execCommand( 'copy' ); }
				}
			} );
		}() );
		</script>
		<?php
	}

	/**
	 * Render the "Listing Limits per Role" section inside the Submissions tab.
	 *
	 * @param array $s Current settings.
	 */
	private static function render_listing_limits_section( $s ) {
		$roles_obj  = wp_roles();
		$role_names = $roles_obj instanceof \WP_Roles ? $roles_obj->get_names() : array();

		$limits_map    = isset( $s['listing_limits_per_role'] ) && is_array( $s['listing_limits_per_role'] )
			? $s['listing_limits_per_role']
			: array();
		$default_limit = isset( $s['listing_limits_default'] ) ? (int) $s['listing_limits_default'] : -1;
		$overflow_cost = (int) get_option( \WBListora\Core\Listing_Limits::OVERFLOW_COST_OPTION, 10 );

		$period = isset( $s['listing_limits_period'] ) && in_array( $s['listing_limits_period'], array( 'lifetime', 'calendar_month', 'rolling_30d' ), true )
			? $s['listing_limits_period']
			: 'lifetime';

		$behavior = isset( $s['listing_beyond_limit_behavior'] ) && in_array( $s['listing_beyond_limit_behavior'], array( 'block', 'credits' ), true )
			? $s['listing_beyond_limit_behavior']
			: 'block';

		$default_is_unlimited = -1 === (int) $default_limit;
		$default_num_value    = $default_is_unlimited ? '' : (string) max( 0, (int) $default_limit );
		$opt                  = esc_attr( self::OPTION_KEY );
		?>
		<section class="listora-settings-block">
			<div class="listora-settings-block__head">
				<h3 class="listora-settings-block__title"><?php esc_html_e( 'Listing Limits per Role', 'wb-listora' ); ?></h3>
				<p class="listora-settings-block__desc"><?php esc_html_e( 'Configure how many listings each role can submit per period. Beyond the limit, block the submission or allow overflow in exchange for credits.', 'wb-listora' ); ?></p>
			</div>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Limit period', 'wb-listora' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Limit Period', 'wb-listora' ); ?></legend>
								<div class="listora-field-group">
									<label>
										<input type="radio" name="<?php echo esc_attr( $opt ); ?>[listing_limits_period]" value="lifetime" <?php checked( 'lifetime', $period ); ?> />
										<strong><?php esc_html_e( 'Lifetime', 'wb-listora' ); ?></strong>
										<span class="listora-field-group__hint"> — <?php esc_html_e( 'count every listing a user has ever submitted.', 'wb-listora' ); ?></span>
									</label>
									<label>
										<input type="radio" name="<?php echo esc_attr( $opt ); ?>[listing_limits_period]" value="calendar_month" <?php checked( 'calendar_month', $period ); ?> />
										<strong><?php esc_html_e( 'Calendar month', 'wb-listora' ); ?></strong>
										<span class="listora-field-group__hint"> — <?php esc_html_e( 'resets on the 1st of each month.', 'wb-listora' ); ?></span>
									</label>
									<label>
										<input type="radio" name="<?php echo esc_attr( $opt ); ?>[listing_limits_period]" value="rolling_30d" <?php checked( 'rolling_30d', $period ); ?> />
										<strong><?php esc_html_e( 'Rolling 30 days', 'wb-listora' ); ?></strong>
										<span class="listora-field-group__hint"> — <?php esc_html_e( 'counts submissions in the last 30 days from today.', 'wb-listora' ); ?></span>
									</label>
								</div>
							</fieldset>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Per-role limits', 'wb-listora' ); ?></th>
						<td>
							<table class="wp-list-table widefat fixed striped listora-role-limits-table">
								<thead>
									<tr>
										<th scope="col"><?php esc_html_e( 'Role', 'wb-listora' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Unlimited', 'wb-listora' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Listings per period', 'wb-listora' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php
									if ( empty( $role_names ) ) {
										echo '<tr><td colspan="3">' . esc_html__( 'No roles registered.', 'wb-listora' ) . '</td></tr>';
									} else {
										foreach ( $role_names as $role_slug => $role_label ) :
											$has_value    = isset( $limits_map[ $role_slug ] );
											$raw_value    = $has_value ? (int) $limits_map[ $role_slug ] : null;
											$is_unlimited = $has_value && -1 === $raw_value;
											$num_value    = ( $has_value && $raw_value >= 0 ) ? (string) $raw_value : '';
											$field_id     = 'listora_limit_role_' . $role_slug;
											$unlim_id     = 'listora_limit_unlim_' . $role_slug;
											?>
											<tr>
												<td>
													<label for="<?php echo esc_attr( $field_id ); ?>">
														<strong><?php echo esc_html( translate_user_role( $role_label ) ); ?></strong>
													</label>
												</td>
												<td>
													<label for="<?php echo esc_attr( $unlim_id ); ?>">
														<input
															type="checkbox"
															id="<?php echo esc_attr( $unlim_id ); ?>"
															class="listora-limit-unlimited"
															data-role="<?php echo esc_attr( $role_slug ); ?>"
															name="<?php echo esc_attr( $opt ); ?>[listing_limits_unlimited][<?php echo esc_attr( $role_slug ); ?>]"
															value="1"
															<?php checked( $is_unlimited ); ?>
														/>
														<?php esc_html_e( 'Unlimited', 'wb-listora' ); ?>
													</label>
												</td>
												<td>
													<input
														type="number"
														id="<?php echo esc_attr( $field_id ); ?>"
														name="<?php echo esc_attr( $opt ); ?>[listing_limits_per_role][<?php echo esc_attr( $role_slug ); ?>]"
														value="<?php echo esc_attr( $num_value ); ?>"
														min="0"
														step="1"
														class="small-text listora-limit-count"
														data-role="<?php echo esc_attr( $role_slug ); ?>"
														<?php disabled( $is_unlimited ); ?>
													/>
													<span class="description"><?php esc_html_e( 'per period', 'wb-listora' ); ?></span>
												</td>
											</tr>
											<?php
										endforeach;
									}
									?>
								</tbody>
							</table>
							<p class="description"><?php esc_html_e( 'Check "Unlimited" to remove the cap for a specific role. Otherwise, the number applies per period.', 'wb-listora' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="listora_limits_default"><?php esc_html_e( 'Default limit', 'wb-listora' ); ?></label>
						</th>
						<td>
							<label class="listora-inline-label">
								<input
									type="checkbox"
									id="listora_limits_default_unlimited"
									class="listora-limit-unlimited"
									data-role="__default__"
									name="<?php echo esc_attr( $opt ); ?>[listing_limits_default_unlimited]"
									value="1"
									<?php checked( $default_is_unlimited ); ?>
								/>
								<?php esc_html_e( 'Unlimited', 'wb-listora' ); ?>
							</label>
							<input
								type="number"
								id="listora_limits_default"
								name="<?php echo esc_attr( $opt ); ?>[listing_limits_default]"
								value="<?php echo esc_attr( $default_num_value ); ?>"
								min="0"
								step="1"
								class="small-text listora-limit-count"
								data-role="__default__"
								<?php disabled( $default_is_unlimited ); ?>
							/>
							<span class="description"><?php esc_html_e( 'per period', 'wb-listora' ); ?></span>
							<p class="description"><?php esc_html_e( 'Applied to any role not listed above (e.g. roles added by other plugins). Check Unlimited to remove the cap.', 'wb-listora' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Beyond-limit behavior', 'wb-listora' ); ?></th>
						<td>
							<fieldset class="listora-beyond-limit-fieldset">
								<legend class="screen-reader-text"><?php esc_html_e( 'Beyond Limit Behavior', 'wb-listora' ); ?></legend>
								<div class="listora-field-group">
									<label>
										<input
											type="radio"
											name="<?php echo esc_attr( $opt ); ?>[listing_beyond_limit_behavior]"
											value="block"
											class="listora-beyond-limit-radio"
											data-target="block"
											<?php checked( 'block', $behavior ); ?>
										/>
										<strong><?php esc_html_e( 'Block submission', 'wb-listora' ); ?></strong>
										<span class="listora-field-group__hint"> — <?php esc_html_e( 'show "You have reached your limit" and stop the submission.', 'wb-listora' ); ?></span>
									</label>
									<label>
										<input
											type="radio"
											name="<?php echo esc_attr( $opt ); ?>[listing_beyond_limit_behavior]"
											value="credits"
											class="listora-beyond-limit-radio"
											data-target="credits"
											<?php checked( 'credits', $behavior ); ?>
										/>
										<strong><?php esc_html_e( 'Allow with credit cost', 'wb-listora' ); ?></strong>
										<span class="listora-field-group__hint"> — <?php esc_html_e( 'user pays the credit cost below for each additional listing.', 'wb-listora' ); ?></span>
									</label>
								</div>
							</fieldset>
						</td>
					</tr>

					<tr class="listora-overflow-cost-row" style="<?php echo 'credits' === $behavior ? '' : 'display:none;'; ?>">
						<th scope="row">
							<label for="listora_overflow_credit_cost"><?php esc_html_e( 'Overflow cost', 'wb-listora' ); ?></label>
						</th>
						<td>
							<input
								type="number"
								id="listora_overflow_credit_cost"
								name="wb_listora_overflow_credit_cost"
								value="<?php echo esc_attr( (string) $overflow_cost ); ?>"
								min="0"
								step="1"
								class="small-text"
							/>
							<span><?php esc_html_e( 'credits per extra listing', 'wb-listora' ); ?></span>
							<p class="description"><?php esc_html_e( 'Charged when a user submits beyond their role limit. Set to 0 to disable the overflow path (the limit becomes a hard stop).', 'wb-listora' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
		</section>

		<script>
		( function () {
			var radios = document.querySelectorAll( '.listora-beyond-limit-radio' );
			var row    = document.querySelector( '.listora-overflow-cost-row' );
			if ( radios.length && row ) {
				var syncBehavior = function () {
					var checked = document.querySelector( '.listora-beyond-limit-radio:checked' );
					row.style.display = ( checked && checked.value === 'credits' ) ? '' : 'none';
				};
				radios.forEach( function ( r ) { r.addEventListener( 'change', syncBehavior ); } );
				syncBehavior();
			}

			// Unlimited checkbox <-> number input toggle for per-role limits + default.
			document.querySelectorAll( '.listora-limit-unlimited' ).forEach( function ( cb ) {
				var role = cb.getAttribute( 'data-role' );
				if ( ! role ) { return; }
				var numField = document.querySelector(
					'.listora-limit-count[data-role="' + ( window.CSS && CSS.escape ? CSS.escape( role ) : role ) + '"]'
				);
				if ( ! numField ) { return; }

				var toggle = function () {
					if ( cb.checked ) {
						numField.disabled = true;
						numField.value    = '';
					} else {
						numField.disabled = false;
						if ( ! numField.value ) {
							numField.value = '10';
						}
						numField.focus();
					}
				};
				cb.addEventListener( 'change', toggle );
			} );
		} )();
		</script>
		<?php
	}

	private static function render_reviews_tab() {
		$s       = get_option( self::OPTION_KEY, array() );
		$reviews = $s['reviews'] ?? array();
		$opt     = esc_attr( self::OPTION_KEY );
		?>
		<div class="listora-settings-pane">

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Moderation', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Control how new reviews are approved, who can submit them, and their minimum length.', 'wb-listora' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Auto-approve', 'wb-listora' ); ?></th>
							<td>
								<label>
									<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[reviews][auto_approve]" value="0" />
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[reviews][auto_approve]" value="1" <?php checked( ! empty( $reviews['auto_approve'] ) ); ?> />
									<?php esc_html_e( 'Publish new reviews immediately (skip moderation queue)', 'wb-listora' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'When disabled, reviews stay in Pending until an admin approves them.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Guest reviews', 'wb-listora' ); ?></th>
							<td>
								<label>
									<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[reviews][require_login]" value="0" />
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[reviews][require_login]" value="1" <?php checked( ! isset( $reviews['require_login'] ) || ! empty( $reviews['require_login'] ) ); ?> />
									<?php esc_html_e( 'Require users to be logged in to leave a review', 'wb-listora' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Uncheck to allow anonymous reviews. Combine with CAPTCHA and manual moderation to reduce spam.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="review_min_length"><?php esc_html_e( 'Minimum length', 'wb-listora' ); ?></label></th>
							<td>
								<input type="number" id="review_min_length" name="<?php echo esc_attr( $opt ); ?>[reviews][min_length]" value="<?php echo esc_attr( $reviews['min_length'] ?? 20 ); ?>" min="0" max="1000" class="small-text" />
								<span><?php esc_html_e( 'characters', 'wb-listora' ); ?></span>
								<p class="description"><?php esc_html_e( 'Minimum characters required for the review body. Set to 0 to allow ratings with no written feedback.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'One review per listing', 'wb-listora' ); ?></th>
							<td>
								<label>
									<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[reviews][one_per_listing]" value="0" />
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[reviews][one_per_listing]" value="1" <?php checked( ! isset( $reviews['one_per_listing'] ) || ! empty( $reviews['one_per_listing'] ) ); ?> />
									<?php esc_html_e( 'Limit each user to a single review per listing', 'wb-listora' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Prevents rating inflation from repeat submissions by the same user.', 'wb-listora' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Owner Replies', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Whether listing owners can publicly respond to reviews left on their listings.', 'wb-listora' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable replies', 'wb-listora' ); ?></th>
							<td>
								<label>
									<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[reviews][allow_reply]" value="0" />
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[reviews][allow_reply]" value="1" <?php checked( ! isset( $reviews['allow_reply'] ) || ! empty( $reviews['allow_reply'] ) ); ?> />
									<?php esc_html_e( 'Allow listing owners to publicly reply to reviews', 'wb-listora' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Replies appear beneath each review. Owners are notified by email when a review is left on their listing.', 'wb-listora' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

		</div>
		<?php
	}

	private static function render_notifications_tab() {
		$s     = get_option( self::OPTION_KEY, array() );
		$notif = $s['notifications'] ?? array();
		$opt   = esc_attr( self::OPTION_KEY );

		$groups = array(
			'listings' => array(
				'title'  => __( 'Listings', 'wb-listora' ),
				'desc'   => __( 'Emails sent when listings are submitted, approved, rejected, or expire.', 'wb-listora' ),
				'events' => array(
					'listing_submitted'     => array( __( 'New listing submitted', 'wb-listora' ), __( 'Sent to admin when a new listing is submitted for review.', 'wb-listora' ) ),
					'listing_pending_admin' => array( __( 'Listing pending admin review', 'wb-listora' ), __( 'Sent to admin when a listing enters the moderation queue.', 'wb-listora' ) ),
					'listing_approved'      => array( __( 'Listing approved', 'wb-listora' ), __( 'Sent to listing owner when their listing is published.', 'wb-listora' ) ),
					'listing_rejected'      => array( __( 'Listing rejected', 'wb-listora' ), __( 'Sent to listing owner with admin feedback.', 'wb-listora' ) ),
					'listing_expired'       => array( __( 'Listing expired', 'wb-listora' ), __( 'Sent to listing owner when their listing expires and is unpublished.', 'wb-listora' ) ),
					'listing_expiring_soon' => array( __( 'Expiration reminder', 'wb-listora' ), __( 'Sent 7 days and 1 day before a listing expires.', 'wb-listora' ) ),
					'listing_renewed'       => array( __( 'Listing renewed', 'wb-listora' ), __( 'Sent to listing owner when their listing is renewed.', 'wb-listora' ) ),
					'draft_reminder'        => array( __( 'Draft reminder', 'wb-listora' ), __( 'Nudge email for listings still in draft 48+ hours.', 'wb-listora' ) ),
				),
			),
			'reviews'  => array(
				'title'  => __( 'Reviews', 'wb-listora' ),
				'desc'   => __( 'Emails sent around review activity on listings.', 'wb-listora' ),
				'events' => array(
					'review_received' => array( __( 'New review received', 'wb-listora' ), __( 'Sent to listing owner when they receive a new review.', 'wb-listora' ) ),
					'review_reply'    => array( __( 'Owner replied to review', 'wb-listora' ), __( 'Sent to the reviewer when the listing owner responds.', 'wb-listora' ) ),
					'review_helpful'  => array( __( 'Helpful-vote milestone', 'wb-listora' ), __( 'Sent to the reviewer when their review reaches a helpful-vote milestone (1, 5, 10, 25, 50, 100).', 'wb-listora' ) ),
				),
			),
			'claims'   => array(
				'title'  => __( 'Claims', 'wb-listora' ),
				'desc'   => __( 'Emails sent during the business claim workflow.', 'wb-listora' ),
				'events' => array(
					'claim_submitted' => array( __( 'Claim submitted', 'wb-listora' ), __( 'Sent to admin when a claim is filed on a listing.', 'wb-listora' ) ),
					'claim_approved'  => array( __( 'Claim approved', 'wb-listora' ), __( 'Sent to the claimant when their claim is accepted.', 'wb-listora' ) ),
					'claim_rejected'  => array( __( 'Claim rejected', 'wb-listora' ), __( 'Sent to the claimant when their claim is denied.', 'wb-listora' ) ),
				),
			),
		);

		$current_user = wp_get_current_user();
		$default_test = $current_user && $current_user->ID ? $current_user->user_email : (string) get_option( 'admin_email' );
		?>
		<div class="listora-settings-pane">
			<?php foreach ( $groups as $group_key => $group_data ) : ?>
				<section class="listora-settings-block">
					<div class="listora-settings-block__head">
						<h3 class="listora-settings-block__title"><?php echo esc_html( $group_data['title'] ); ?></h3>
						<p class="listora-settings-block__desc"><?php echo esc_html( $group_data['desc'] ); ?></p>
					</div>
					<table class="form-table" role="presentation">
						<tbody>
							<?php
							foreach ( $group_data['events'] as $key => $event_info ) :
								$enabled = ! isset( $notif[ $key ] ) || $notif[ $key ];
								?>
								<tr>
									<th scope="row"><?php echo esc_html( $event_info[0] ); ?></th>
									<td>
										<label>
											<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[notifications][<?php echo esc_attr( $key ); ?>]" value="0" />
											<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[notifications][<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $enabled ); ?> />
											<?php esc_html_e( 'Enabled', 'wb-listora' ); ?>
										</label>
										<p class="description"><?php echo esc_html( $event_info[1] ); ?></p>
										<p>
											<button type="button"
												class="button listora-notification-test"
												data-event-key="<?php echo esc_attr( $key ); ?>">
												<?php esc_html_e( 'Send Test', 'wb-listora' ); ?>
											</button>
											<span class="listora-notification-test__status" aria-live="polite"></span>
										</p>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</section>
			<?php endforeach; ?>

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Recent Activity', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Last 50 outbound notification attempts. Useful for confirming admin/user toggles are honored and tracing send failures.', 'wb-listora' ); ?></p>
				</div>
				<p>
					<label for="listora-notification-test-recipient"><?php esc_html_e( 'Test recipient:', 'wb-listora' ); ?></label>
					<input type="email" id="listora-notification-test-recipient"
						class="regular-text" value="<?php echo esc_attr( $default_test ); ?>" />
					<button type="button" id="listora-notification-log-refresh" class="button">
						<?php esc_html_e( 'Refresh log', 'wb-listora' ); ?>
					</button>
					<button type="button" id="listora-notification-log-clear" class="button button-link-delete">
						<?php esc_html_e( 'Clear log', 'wb-listora' ); ?>
					</button>
				</p>
				<div id="listora-notification-log" class="listora-notification-log">
					<p class="description"><?php esc_html_e( 'Loading recent activity…', 'wb-listora' ); ?></p>
				</div>
			</section>
		</div>
		<style>
			.listora-notification-test__status { margin-left: 8px; font-size: 12px; }
			.listora-notification-test__status.is-success { color: #00a32a; }
			.listora-notification-test__status.is-error { color: #d63638; }
			.listora-notification-log table { width: 100%; border-collapse: collapse; }
			.listora-notification-log th,
			.listora-notification-log td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #e0e0e0; vertical-align: top; font-size: 12px; }
			.listora-notification-log th { background: #f6f7f7; font-weight: 600; }
			.listora-notification-log .is-success { color: #00a32a; }
			.listora-notification-log .is-error { color: #d63638; }
		</style>
		<script>
		(function () {
			document.addEventListener( 'DOMContentLoaded', function () {
				var section = document.getElementById( 'section-notifications' );
				if ( ! section ) { return; }

				var recipientEl = document.getElementById( 'listora-notification-test-recipient' );
				var logEl       = document.getElementById( 'listora-notification-log' );
				var refreshBtn  = document.getElementById( 'listora-notification-log-refresh' );
				var clearBtn    = document.getElementById( 'listora-notification-log-clear' );

				/* Build DOM nodes via createElement/textContent — never innerHTML —
				   to make XSS impossible regardless of payload. */
				function clearChildren( node ) {
					while ( node.firstChild ) { node.removeChild( node.firstChild ); }
				}

				function descParagraph( msg, isError ) {
					var p = document.createElement( 'p' );
					p.className = 'description' + ( isError ? ' is-error' : '' );
					p.textContent = msg;
					return p;
				}

				function buildLogTable( entries ) {
					var table = document.createElement( 'table' );
					var thead = document.createElement( 'thead' );
					var hr    = document.createElement( 'tr' );
					[
						'<?php echo esc_js( __( 'Sent At (UTC)', 'wb-listora' ) ); ?>',
						'<?php echo esc_js( __( 'Event', 'wb-listora' ) ); ?>',
						'<?php echo esc_js( __( 'Recipient', 'wb-listora' ) ); ?>',
						'<?php echo esc_js( __( 'Subject', 'wb-listora' ) ); ?>',
						'<?php echo esc_js( __( 'Result', 'wb-listora' ) ); ?>'
					].forEach( function ( label ) {
						var th = document.createElement( 'th' );
						th.textContent = label;
						hr.appendChild( th );
					} );
					thead.appendChild( hr );
					table.appendChild( thead );

					var tbody = document.createElement( 'tbody' );
					entries.forEach( function ( e ) {
						var tr = document.createElement( 'tr' );

						var sentTd = document.createElement( 'td' );
						sentTd.textContent = e.sent_at || '';
						tr.appendChild( sentTd );

						var eventTd   = document.createElement( 'td' );
						var eventCode = document.createElement( 'code' );
						eventCode.textContent = e.event_key || '';
						eventTd.appendChild( eventCode );
						tr.appendChild( eventTd );

						var recipientTd = document.createElement( 'td' );
						recipientTd.textContent = e.recipient || '';
						tr.appendChild( recipientTd );

						var subjectTd = document.createElement( 'td' );
						subjectTd.textContent = e.subject || '';
						tr.appendChild( subjectTd );

						var resultTd = document.createElement( 'td' );
						var resultSpan = document.createElement( 'span' );
						if ( e.success ) {
							resultSpan.className = 'is-success';
							resultSpan.textContent = '<?php echo esc_js( __( 'Sent', 'wb-listora' ) ); ?>';
						} else {
							resultSpan.className = 'is-error';
							resultSpan.textContent = '<?php echo esc_js( __( 'Failed', 'wb-listora' ) ); ?>'
								+ ( e.error ? ': ' + e.error : '' );
						}
						resultTd.appendChild( resultSpan );
						tr.appendChild( resultTd );

						tbody.appendChild( tr );
					} );
					table.appendChild( tbody );
					return table;
				}

				function renderLog( payload ) {
					clearChildren( logEl );
					var entries = ( payload && payload.entries ) || [];
					if ( ! entries.length ) {
						logEl.appendChild( descParagraph( '<?php echo esc_js( __( 'No activity yet. Click "Send Test" on any event above to record an entry.', 'wb-listora' ) ); ?>', false ) );
						return;
					}
					logEl.appendChild( buildLogTable( entries ) );
				}

				function loadLog() {
					if ( ! window.wp || ! window.wp.apiFetch ) { return; }
					wp.apiFetch( { path: '/listora/v1/settings/notifications/log' } )
						.then( renderLog )
						.catch( function ( err ) {
							clearChildren( logEl );
							logEl.appendChild( descParagraph(
								'<?php echo esc_js( __( 'Failed to load log:', 'wb-listora' ) ); ?> '
								+ ( ( err && err.message ) || err ),
								true
							) );
						} );
				}

				if ( refreshBtn ) {
					refreshBtn.addEventListener( 'click', function ( ev ) {
						ev.preventDefault();
						loadLog();
					} );
				}

				if ( clearBtn ) {
					clearBtn.addEventListener( 'click', function ( ev ) {
						ev.preventDefault();
						if ( ! window.wp || ! window.wp.apiFetch ) { return; }
						wp.apiFetch( {
							path:   '/listora/v1/settings/notifications/log',
							method: 'DELETE'
						} ).then( loadLog );
					} );
				}

				section.querySelectorAll( '.listora-notification-test' ).forEach( function ( btn ) {
					btn.addEventListener( 'click', function ( ev ) {
						ev.preventDefault();
						if ( ! window.wp || ! window.wp.apiFetch ) { return; }

						var status = btn.parentNode.querySelector( '.listora-notification-test__status' );
						status.className = 'listora-notification-test__status';
						status.textContent = '<?php echo esc_js( __( 'Sending…', 'wb-listora' ) ); ?>';
						btn.disabled = true;

						wp.apiFetch( {
							path:   '/listora/v1/settings/notifications/test',
							method: 'POST',
							data:   {
								event_key:       btn.getAttribute( 'data-event-key' ),
								recipient_email: recipientEl ? recipientEl.value : ''
							}
						} ).then( function ( res ) {
							if ( res && res.sent ) {
								status.classList.add( 'is-success' );
								status.textContent = '<?php echo esc_js( __( 'Sent', 'wb-listora' ) ); ?>';
							} else {
								status.classList.add( 'is-error' );
								status.textContent = '<?php echo esc_js( __( 'Failed:', 'wb-listora' ) ); ?> ' + ( ( res && res.error ) || '' );
							}
							loadLog();
						} ).catch( function ( err ) {
							status.classList.add( 'is-error' );
							status.textContent = '<?php echo esc_js( __( 'Error:', 'wb-listora' ) ); ?> ' + ( ( err && err.message ) || err );
						} ).finally( function () {
							btn.disabled = false;
						} );
					} );
				} );

				loadLog();
			} );
		})();
		</script>
		<?php
	}

	private static function render_seo_tab() {
		$s   = get_option( self::OPTION_KEY, array() );
		$d   = wb_listora_get_default_settings();
		$opt = esc_attr( self::OPTION_KEY );
		?>
		<div class="listora-settings-pane">

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Structured Data', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'JSON-LD markup that tells search engines what your listings are. Powers rich results, breadcrumbs, and knowledge panels.', 'wb-listora' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Schema.org', 'wb-listora' ); ?></th>
							<td>
								<label>
									<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[enable_schema]" value="0" />
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[enable_schema]" value="1" <?php checked( $s['enable_schema'] ?? $d['enable_schema'] ); ?> />
									<?php esc_html_e( 'Output Schema.org structured data on listing pages', 'wb-listora' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Emits LocalBusiness / Product / Event markup based on listing type. Required for rich results in Google Search.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Breadcrumbs', 'wb-listora' ); ?></th>
							<td>
								<label>
									<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[enable_breadcrumbs]" value="0" />
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[enable_breadcrumbs]" value="1" <?php checked( $s['enable_breadcrumbs'] ?? $d['enable_breadcrumbs'] ); ?> />
									<?php esc_html_e( 'Output BreadcrumbList JSON-LD', 'wb-listora' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Helps search engines understand the hierarchy (Type → Category → Listing).', 'wb-listora' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Discovery &amp; Sharing', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Control how listings are indexed by search engines and rendered when shared on social platforms.', 'wb-listora' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Sitemap', 'wb-listora' ); ?></th>
							<td>
								<label>
									<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[enable_sitemap]" value="0" />
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[enable_sitemap]" value="1" <?php checked( $s['enable_sitemap'] ?? $d['enable_sitemap'] ); ?> />
									<?php esc_html_e( 'Include listings in the WordPress XML sitemap', 'wb-listora' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Adds /listing/* URLs to /wp-sitemap.xml so search engines can discover them.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Open Graph', 'wb-listora' ); ?></th>
							<td>
								<label>
									<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[enable_opengraph]" value="0" />
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[enable_opengraph]" value="1" <?php checked( $s['enable_opengraph'] ?? $d['enable_opengraph'] ); ?> />
									<?php esc_html_e( 'Output Open Graph and Twitter Card meta tags', 'wb-listora' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Makes listings render with a preview image, title, and description when shared on Facebook, LinkedIn, Twitter/X, and Slack.', 'wb-listora' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

		</div>
		<?php
	}

	private static function render_advanced_tab() {
		$s   = get_option( self::OPTION_KEY, array() );
		$d   = wb_listora_get_default_settings();
		$opt = esc_attr( self::OPTION_KEY );
		?>
		<div class="listora-settings-pane">

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Cache', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Time-to-live for cached search results and facet counts. Higher values improve performance but delay new-listing visibility.', 'wb-listora' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="search_cache_ttl"><?php esc_html_e( 'Search results TTL', 'wb-listora' ); ?></label></th>
							<td>
								<input type="number" id="search_cache_ttl" name="<?php echo esc_attr( $opt ); ?>[search_cache_ttl]" value="<?php echo esc_attr( $s['search_cache_ttl'] ?? $d['search_cache_ttl'] ); ?>" min="0" max="120" class="small-text" />
								<span><?php esc_html_e( 'minutes', 'wb-listora' ); ?></span>
								<p class="description"><?php esc_html_e( 'How long cached search result sets are kept. Set to 0 to disable caching for search queries.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="facet_cache_ttl"><?php esc_html_e( 'Facet counts TTL', 'wb-listora' ); ?></label></th>
							<td>
								<input type="number" id="facet_cache_ttl" name="<?php echo esc_attr( $opt ); ?>[facet_cache_ttl]" value="<?php echo esc_attr( $s['facet_cache_ttl'] ?? $d['facet_cache_ttl'] ); ?>" min="0" max="120" class="small-text" />
								<span><?php esc_html_e( 'minutes', 'wb-listora' ); ?></span>
								<p class="description"><?php esc_html_e( 'How long the sidebar facet counts (per category, feature, location) are cached. Set to 0 to disable.', 'wb-listora' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Maintenance', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Rebuild internal indexes or re-run the setup wizard to reconfigure listing types and demo data.', 'wb-listora' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Search index', 'wb-listora' ); ?></th>
							<td>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=listora-settings&action=reindex' ), 'listora_reindex' ) ); ?>" class="button">
									<i data-lucide="refresh-cw"></i> <?php esc_html_e( 'Rebuild Search Index', 'wb-listora' ); ?>
								</a>
								<p class="description"><?php esc_html_e( 'Regenerates the denormalized search_index table. Run after bulk-editing listings or changing custom fields.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Setup wizard', 'wb-listora' ); ?></th>
							<td>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=listora-setup' ) ); ?>" class="button">
									<i data-lucide="wand-sparkles"></i> <?php esc_html_e( 'Run Setup Wizard', 'wb-listora' ); ?>
								</a>
								<p class="description"><?php esc_html_e( 'Re-opens the first-run wizard to reconfigure listing types, demo content, and default pages.', 'wb-listora' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Debug', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Developer tools for tracing plugin behavior. Leave disabled on production unless actively troubleshooting.', 'wb-listora' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Debug logging', 'wb-listora' ); ?></th>
							<td>
								<label>
									<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[debug_logging]" value="0" />
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[debug_logging]" value="1" <?php checked( $s['debug_logging'] ?? $d['debug_logging'] ); ?> />
									<?php esc_html_e( 'Log plugin actions and queries to debug.log', 'wb-listora' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Requires WP_DEBUG and WP_DEBUG_LOG in wp-config.php. Output appears in wp-content/debug.log.', 'wb-listora' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Data Management', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Control what happens to plugin data when WB Listora is uninstalled.', 'wb-listora' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Uninstall', 'wb-listora' ); ?></th>
							<td>
								<label>
									<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[delete_on_uninstall]" value="0" />
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[delete_on_uninstall]" value="1" <?php checked( $s['delete_on_uninstall'] ?? $d['delete_on_uninstall'] ); ?> />
									<?php esc_html_e( 'Permanently delete all WB Listora data on plugin uninstall', 'wb-listora' ); ?>
								</label>
								<p class="description listora-description--danger"><?php esc_html_e( 'Warning: this removes every listing, review, favorite, claim, custom table, and setting. Cannot be undone.', 'wb-listora' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

		</div>
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
		<div class="listora-settings-pane">

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Export Settings (JSON)', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Download a JSON snapshot of every plugin setting for backup or transfer to another site.', 'wb-listora' ); ?></p>
				</div>
				<div class="listora-settings-block__body">
					<button type="button" class="listora-btn listora-btn--secondary" data-listora-action="export-settings">
						<i data-lucide="download"></i> <?php esc_html_e( 'Download Settings JSON', 'wb-listora' ); ?>
					</button>
				</div>
			</section>

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Import Settings (JSON)', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Upload a previously exported JSON file to overwrite all current settings.', 'wb-listora' ); ?></p>
				</div>
				<div class="listora-settings-block__body">
					<div class="listora-tool-row">
						<input type="file" id="listora-import-file" accept=".json" class="listora-tool-row__input" />
						<button type="button" class="listora-btn listora-btn--secondary" data-listora-action="import-settings">
							<i data-lucide="upload"></i> <?php esc_html_e( 'Import Settings', 'wb-listora' ); ?>
						</button>
						<span id="listora-import-status" class="listora-tool-row__status"></span>
					</div>
					<p class="description"><?php esc_html_e( 'Only files exported from this same plugin version should be imported. Importing replaces all current values.', 'wb-listora' ); ?></p>
				</div>
			</section>

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Export Listings (CSV)', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Download every listing as a CSV file — optionally scoped to a single listing type — for backup or migration.', 'wb-listora' ); ?></p>
				</div>
				<div class="listora-settings-block__body">
					<div class="listora-tool-row">
						<label for="listora-csv-export-type" class="listora-tool-row__label"><?php esc_html_e( 'Listing type', 'wb-listora' ); ?></label>
						<select id="listora-csv-export-type" class="listora-tool-row__select">
							<option value=""><?php esc_html_e( 'All types', 'wb-listora' ); ?></option>
							<?php foreach ( $type_terms as $term ) : ?>
								<option value="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option>
							<?php endforeach; ?>
						</select>
						<button type="button" id="listora-csv-export-btn" class="listora-btn listora-btn--primary">
							<i data-lucide="download"></i> <?php esc_html_e( 'Export CSV', 'wb-listora' ); ?>
						</button>
						<span id="listora-csv-export-status" class="listora-tool-row__status"></span>
					</div>
					<p class="description"><strong><?php esc_html_e( 'WP-CLI:', 'wb-listora' ); ?></strong> <code>wp listora export --type=restaurant --output=file.csv</code></p>
				</div>
			</section>

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Import Listings (CSV)', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Import listings from a CSV. The first row must be column headers. Select the listing type to apply to all rows.', 'wb-listora' ); ?></p>
				</div>
				<div class="listora-settings-block__body">
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><label for="listora-csv-import-type"><?php esc_html_e( 'Listing type', 'wb-listora' ); ?> <span class="listora-required">*</span></label></th>
								<td>
									<select id="listora-csv-import-type" required>
										<option value=""><?php esc_html_e( 'Select a type…', 'wb-listora' ); ?></option>
										<?php foreach ( $type_terms as $term ) : ?>
											<option value="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option>
										<?php endforeach; ?>
									</select>
									<p class="description"><?php esc_html_e( 'Every row in the CSV will be imported under this listing type.', 'wb-listora' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="listora-csv-import-file"><?php esc_html_e( 'CSV file', 'wb-listora' ); ?> <span class="listora-required">*</span></label></th>
								<td>
									<input type="file" id="listora-csv-import-file" accept=".csv,text/csv">
									<p class="description"><?php esc_html_e( 'Accepted format: UTF-8 CSV with header row. Columns map to: title, description, category, tags.', 'wb-listora' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Dry run', 'wb-listora' ); ?></th>
								<td>
									<label>
										<input type="checkbox" id="listora-csv-import-dryrun">
										<?php esc_html_e( 'Validate only — do not create listings', 'wb-listora' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'Checks the file end-to-end without writing any listings. Recommended on a first import.', 'wb-listora' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">&nbsp;</th>
								<td>
									<button type="button" id="listora-csv-import-btn" class="listora-btn listora-btn--primary">
										<i data-lucide="upload"></i> <?php esc_html_e( 'Import CSV', 'wb-listora' ); ?>
									</button>
									<span id="listora-csv-import-status" class="listora-tool-row__status"></span>
									<p class="description"><strong><?php esc_html_e( 'WP-CLI:', 'wb-listora' ); ?></strong> <code>wp listora import &lt;file.csv&gt; --type=restaurant</code></p>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
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
	private static function render_migration_tab() {
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
		echo '</div>'; // .listora-settings-block__body.
		echo '</section>'; // .listora-settings-block.
		echo '</div>'; // .listora-settings-pane.

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
