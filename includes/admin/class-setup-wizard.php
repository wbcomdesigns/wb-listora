<?php
/**
 * Setup Wizard — guides site owner through initial configuration.
 *
 * @package WBListora\Admin
 */

namespace WBListora\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * 6-step setup wizard: Type → Location → Maps → Pages → Demo → Done.
 */
class Setup_Wizard {

	/**
	 * Mapping from dashicon class to Lucide icon name.
	 *
	 * @var array<string, string>
	 */
	private const ICON_MAP = array(
		'dashicons-building'           => 'building-2',
		'dashicons-food'               => 'utensils',
		'dashicons-admin-home'         => 'home',
		'dashicons-store'              => 'bed',
		'dashicons-calendar-alt'       => 'calendar',
		'dashicons-businessman'        => 'briefcase',
		'dashicons-heart'              => 'heart-pulse',
		'dashicons-welcome-learn-more' => 'graduation-cap',
		'dashicons-location'           => 'map-pin',
		'dashicons-tag'                => 'tag',
		'dashicons-location-alt'       => 'map-pin',
		'dashicons-rss'                => 'rss',
		'dashicons-car'                => 'car',
		'dashicons-universal-access'   => 'accessibility',
		'dashicons-pets'               => 'paw-print',
		'dashicons-cloud'              => 'cloud',
		'dashicons-palmtree'           => 'palm-tree',
		'dashicons-admin-site'         => 'globe',
		'dashicons-arrow-up-alt'       => 'arrow-up',
		'dashicons-clock'              => 'clock',
		'dashicons-format-audio'       => 'music',
		'dashicons-money-alt'          => 'banknote',
	);

	/**
	 * Handle form submission before rendering.
	 */
	public function __construct() {
		// Setup wizard writes plugin settings — require the capability that
		// gates all other settings writes. Nonce alone only proves the form
		// came from our origin, not that the user is authorised.
		if ( isset( $_POST['listora_wizard_step'] )
			&& current_user_can( 'manage_listora_settings' )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['listora_wizard_nonce'] ?? '' ) ), 'listora_wizard' ) ) {
			$this->process_step( sanitize_text_field( wp_unslash( $_POST['listora_wizard_step'] ) ) );
		}
	}

	/**
	 * Process a wizard step submission.
	 *
	 * @param string $step Current step ID.
	 */
	private function process_step( $step ) {
		$data = get_option( 'wb_listora_setup_data', array() );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in __construct() before calling process_step().
		switch ( $step ) {
			case 'type':
				$types                  = isset( $_POST['listing_types'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['listing_types'] ) ) : array();
				$data['selected_types'] = $types;
				break;

			case 'location':
				$data['country']   = sanitize_text_field( wp_unslash( $_POST['country'] ?? '' ) );
				$data['city']      = sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) );
				$data['latitude']  = floatval( $_POST['latitude'] ?? 0 );
				$data['longitude'] = floatval( $_POST['longitude'] ?? 0 );
				$data['is_global'] = ! empty( $_POST['is_global'] );
				break;

			case 'maps':
				$data['map_provider'] = sanitize_text_field( wp_unslash( $_POST['map_provider'] ?? 'osm' ) );
				break;

			case 'pages':
				// Create pages.
				$this->create_pages( $data );
				$data['pages_created'] = true;
				break;

			case 'demo':
				$data['import_demo'] = ! empty( $_POST['import_demo'] );
				$data['demo_pack']   = sanitize_text_field( wp_unslash( $_POST['demo_pack'] ?? 'general' ) );
				if ( $data['import_demo'] ) {
					$this->import_demo_content( $data );
				}
				break;

			case 'done':
				// Save settings and mark complete.
				$this->finalize_setup( $data );
				delete_option( 'wb_listora_setup_data' );
				set_transient( 'wb_listora_just_completed_setup_' . get_current_user_id(), time(), 60 );
				wp_safe_redirect( admin_url( 'admin.php?page=listora&listora-welcome=1' ) );
				exit;
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing
		update_option( 'wb_listora_setup_data', $data );
	}

	/**
	 * Enqueue wizard-specific assets.
	 */
	private function enqueue_assets() {
		wp_enqueue_style(
			'listora-setup-wizard',
			WB_LISTORA_PLUGIN_URL . 'assets/css/admin/setup-wizard.css',
			array( 'listora-admin' ),
			WB_LISTORA_VERSION
		);
	}

	/**
	 * Convert a dashicon class to a Lucide icon name.
	 *
	 * @param string $dashicon Dashicon CSS class (e.g. 'dashicons-building').
	 * @return string Lucide icon name (e.g. 'building-2').
	 */
	private function get_lucide_icon( $dashicon ) {
		return self::ICON_MAP[ $dashicon ] ?? 'map-pin';
	}

	/**
	 * Render the wizard.
	 */
	public function render() {
		$this->enqueue_assets();

		$data  = get_option( 'wb_listora_setup_data', array() );
		$step  = sanitize_text_field( wp_unslash( $_GET['step'] ?? 'type' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$types = \WBListora\Core\Listing_Type_Registry::instance()->get_all();

		$steps = array(
			'type'     => __( 'Directory Type', 'wb-listora' ),
			'location' => __( 'Location', 'wb-listora' ),
			'maps'     => __( 'Map Provider', 'wb-listora' ),
			'pages'    => __( 'Pages', 'wb-listora' ),
			'demo'     => __( 'Demo Content', 'wb-listora' ),
			'done'     => __( 'Done!', 'wb-listora' ),
		);

		$step_keys   = array_keys( $steps );
		$current_idx = array_search( $step, $step_keys, true );
		$next_step   = $step_keys[ $current_idx + 1 ] ?? 'done';
		$prev_step   = $current_idx > 0 ? $step_keys[ $current_idx - 1 ] : '';

		?>
		<div class="wrap listora-wizard wb-listora-admin">
			<h1><?php esc_html_e( 'WB Listora Setup', 'wb-listora' ); ?></h1>

			<?php // Progress bar. ?>
			<div class="listora-wizard__progress">
				<?php
				foreach ( $steps as $s_key => $s_label ) :
					$s_idx = array_search( $s_key, $step_keys, true );
					$class = '';
					if ( $s_idx < $current_idx ) {
						$class = 'is-completed';
					} elseif ( $s_idx === $current_idx ) {
						$class = 'is-current';
					}
					?>
				<div class="listora-wizard__progress-step <?php echo esc_attr( $class ); ?>" title="<?php echo esc_attr( $s_label ); ?>"></div>
				<?php endforeach; ?>
			</div>

			<div class="listora-wizard__card">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=listora-setup&step=' . $next_step ) ); ?>">
					<?php wp_nonce_field( 'listora_wizard', 'listora_wizard_nonce' ); ?>
					<input type="hidden" name="listora_wizard_step" value="<?php echo esc_attr( $step ); ?>" />

					<?php
					switch ( $step ) :
						case 'type':
							$this->render_step_type( $types, $data );
							break;
						case 'location':
							$this->render_step_location( $data );
							break;
						case 'maps':
							$this->render_step_maps( $data );
							break;
						case 'pages':
							$this->render_step_pages( $data );
							break;
						case 'demo':
							$this->render_step_demo( $data );
							break;
						case 'done':
							$this->render_step_done( $data );
							break;
					endswitch;
					?>

					<?php if ( 'done' !== $step ) : ?>
					<div class="listora-wizard__nav">
						<?php if ( $prev_step ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=listora-setup&step=' . $prev_step ) ); ?>" class="listora-btn">
							<?php esc_html_e( '← Back', 'wb-listora' ); ?>
						</a>
						<?php else : ?>
						<span></span>
						<?php endif; ?>

						<button type="submit" class="listora-btn listora-btn--primary">
							<?php echo esc_html( 'demo' === $step ? __( 'Finish Setup →', 'wb-listora' ) : __( 'Continue →', 'wb-listora' ) ); ?>
						</button>
					</div>
					<?php endif; ?>
				</form>
			</div>

			<p class="listora-wizard__skip">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=listora' ) ); ?>">
					<?php esc_html_e( 'Skip setup', 'wb-listora' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	// ─── Step Renderers ───

	/**
	 * Render the type selection step.
	 *
	 * @param array $types Available listing types.
	 * @param array $data  Saved wizard data.
	 */
	private function render_step_type( $types, $data ) {
		$selected = $data['selected_types'] ?? array();
		?>
		<h2><?php esc_html_e( 'What type of directory are you building?', 'wb-listora' ); ?></h2>
		<p><?php esc_html_e( 'Select one or more listing types. You can add more later.', 'wb-listora' ); ?></p>

		<div class="listora-wizard__type-grid">
			<?php foreach ( $types as $type ) : ?>
			<label class="listora-wizard__type-card">
				<input type="checkbox" name="listing_types[]" value="<?php echo esc_attr( $type->get_slug() ); ?>"
					<?php checked( in_array( $type->get_slug(), $selected, true ) ); ?> />
				<div class="listora-wizard__type-inner">
					<span class="listora-wizard__type-icon"><i data-lucide="<?php echo esc_attr( $this->get_lucide_icon( $type->get_icon() ) ); ?>"></i></span>
					<span class="listora-wizard__type-name"><?php echo esc_html( $type->get_name() ); ?></span>
				</div>
			</label>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render the location step.
	 *
	 * @param array $data Saved wizard data.
	 */
	private function render_step_location( $data ) {
		?>
		<h2><?php esc_html_e( 'Where is your directory based?', 'wb-listora' ); ?></h2>
		<p><?php esc_html_e( 'This sets the default map center and location context.', 'wb-listora' ); ?></p>

		<div class="listora-wizard__field">
			<label for="wizard-country"><?php esc_html_e( 'Country', 'wb-listora' ); ?></label>
			<input type="text" id="wizard-country" name="country" value="<?php echo esc_attr( $data['country'] ?? '' ); ?>"
				placeholder="<?php esc_attr_e( 'e.g., United States', 'wb-listora' ); ?>" />
		</div>

		<div class="listora-wizard__field">
			<label for="wizard-city"><?php esc_html_e( 'City', 'wb-listora' ); ?></label>
			<input type="text" id="wizard-city" name="city" value="<?php echo esc_attr( $data['city'] ?? '' ); ?>"
				placeholder="<?php esc_attr_e( 'e.g., New York', 'wb-listora' ); ?>" />
		</div>

		<div class="listora-wizard__field" style="display:flex;gap:1rem;">
			<div style="flex:1;">
				<label for="wizard-lat"><?php esc_html_e( 'Latitude', 'wb-listora' ); ?></label>
				<input type="text" id="wizard-lat" name="latitude" value="<?php echo esc_attr( $data['latitude'] ?? '40.7128' ); ?>" />
			</div>
			<div style="flex:1;">
				<label for="wizard-lng"><?php esc_html_e( 'Longitude', 'wb-listora' ); ?></label>
				<input type="text" id="wizard-lng" name="longitude" value="<?php echo esc_attr( $data['longitude'] ?? '-74.0060' ); ?>" />
			</div>
		</div>

		<label style="display:flex;align-items:center;gap:0.5rem;margin-top:0.5rem;">
			<input type="checkbox" name="is_global" value="1" <?php checked( $data['is_global'] ?? false ); ?> />
			<?php esc_html_e( 'This is a global directory (no default location)', 'wb-listora' ); ?>
		</label>
		<?php
	}

	/**
	 * Render the maps step.
	 *
	 * @param array $data Saved wizard data.
	 */
	private function render_step_maps( $data ) {
		$provider = $data['map_provider'] ?? 'osm';
		?>
		<h2><?php esc_html_e( 'Choose your map provider', 'wb-listora' ); ?></h2>

		<div style="display:flex;flex-direction:column;gap:1rem;margin:1.5rem 0;">
			<label class="listora-wizard__option-card">
				<input type="radio" name="map_provider" value="osm" <?php checked( 'osm', $provider ); ?> />
				<div>
					<strong><?php esc_html_e( 'OpenStreetMap (Free)', 'wb-listora' ); ?></strong><br/>
					<span style="color:var(--listora-text-secondary, #666);"><?php esc_html_e( 'Free, no API key needed. Works immediately.', 'wb-listora' ); ?></span>
				</div>
			</label>

			<label class="listora-wizard__option-card listora-wizard__option-card--disabled">
				<input type="radio" name="map_provider" value="google" <?php checked( 'google', $provider ); ?> disabled />
				<div>
					<strong><?php esc_html_e( 'Google Maps', 'wb-listora' ); ?></strong>
					<span class="listora-pro-badge" data-pro-feature="google-maps">Pro</span><br/>
					<span style="color:var(--listora-text-secondary, #666);"><?php esc_html_e( 'Requires API key + billing. Available with Pro plugin.', 'wb-listora' ); ?></span>
				</div>
			</label>
		</div>
		<?php
	}

	/**
	 * Render the pages step.
	 *
	 * The 3 essential pages (Directory, Add Listing, My Dashboard) are
	 * already created on activation by `Activator::ensure_essential_pages()`,
	 * so this step is now an informational confirmation rather than an
	 * action. Type-specific pages still show up in the list — those are
	 * created on the next step submission via `create_pages()`.
	 *
	 * @param array $data Saved wizard data.
	 */
	private function render_step_pages( $data ) {
		$selected_types = $data['selected_types'] ?? array( 'business' );

		$essential = array(
			array(
				'option' => 'wb_listora_directory_page_id',
				'label'  => __( 'Directory Home', 'wb-listora' ),
				'slug'   => 'listings',
			),
			array(
				'option' => 'wb_listora_submission_page_id',
				'label'  => __( 'Add Listing', 'wb-listora' ),
				'slug'   => 'add-listing',
			),
			array(
				'option' => 'wb_listora_dashboard_page_id',
				'label'  => __( 'My Dashboard', 'wb-listora' ),
				'slug'   => 'my-dashboard',
			),
		);
		?>
		<h2><?php esc_html_e( 'We created these pages for you', 'wb-listora' ); ?></h2>
		<p><?php esc_html_e( 'These three pages were auto-created when you activated the plugin. Each comes with the right blocks pre-configured — open them in the block editor to customize copy and layout.', 'wb-listora' ); ?></p>

		<ul class="listora-wizard__pages-list">
			<?php foreach ( $essential as $page ) : ?>
				<?php
				$page_id   = (int) get_option( $page['option'], 0 );
				$page_post = $page_id > 0 ? get_post( $page_id ) : null;
				$exists    = $page_post && 'page' === $page_post->post_type;
				$slug      = $exists ? $page_post->post_name : $page['slug'];
				$edit_url  = $exists ? get_edit_post_link( $page_id ) : '';
				$view_url  = $exists ? get_permalink( $page_id ) : '';
				?>
				<li>
					<span>
						<?php
						echo '<span class="listora-wizard-check" aria-hidden="true">✓</span>';
						echo esc_html( $page['label'] );
						?>
					</span>
					<code>/<?php echo esc_html( $slug ); ?></code>
					<?php if ( $exists ) : ?>
						<span style="margin-left:0.5rem;">
							<?php if ( $edit_url ) : ?>
								<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'wb-listora' ); ?></a>
							<?php endif; ?>
							<?php if ( $view_url ) : ?>
								&nbsp;·&nbsp;<a href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View', 'wb-listora' ); ?></a>
							<?php endif; ?>
						</span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
			<?php
			foreach ( $selected_types as $slug ) :
				$type = \WBListora\Core\Listing_Type_Registry::instance()->get( $slug );
				if ( ! $type ) {
					continue;
				}
				?>
			<li>
				<span><?php echo esc_html( $type->get_name() ); ?></span>
				<code>/<?php echo esc_html( $slug ); ?></code>
				<small style="margin-left:0.5rem;color:#64748b;"><?php esc_html_e( '(will be created when you continue)', 'wb-listora' ); ?></small>
			</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Render the demo content step.
	 *
	 * @param array $data Saved wizard data.
	 */
	private function render_step_demo( $data ) {
		$selected_pack = $data['demo_pack'] ?? 'general';
		?>
		<h2><?php esc_html_e( 'Want some sample listings?', 'wb-listora' ); ?></h2>
		<p><?php esc_html_e( 'Choose a demo content pack to see how your directory looks with real content. Each pack includes 20 listings with reviews and categories.', 'wb-listora' ); ?></p>

		<div class="listora-demo-packs">
			<label class="listora-demo-pack">
				<input type="radio" name="demo_pack" value="restaurant" <?php checked( 'restaurant', $selected_pack ); ?> />
				<div class="listora-demo-pack__card">
					<i data-lucide="utensils"></i>
					<strong><?php esc_html_e( 'Restaurant Directory', 'wb-listora' ); ?></strong>
					<span><?php esc_html_e( '20 restaurants with reviews, hours, and cuisines', 'wb-listora' ); ?></span>
				</div>
			</label>

			<label class="listora-demo-pack">
				<input type="radio" name="demo_pack" value="job-board" <?php checked( 'job-board', $selected_pack ); ?> />
				<div class="listora-demo-pack__card">
					<i data-lucide="briefcase"></i>
					<strong><?php esc_html_e( 'Job Board', 'wb-listora' ); ?></strong>
					<span><?php esc_html_e( '20 job listings with salaries and skills', 'wb-listora' ); ?></span>
				</div>
			</label>

			<label class="listora-demo-pack">
				<input type="radio" name="demo_pack" value="real-estate" <?php checked( 'real-estate', $selected_pack ); ?> />
				<div class="listora-demo-pack__card">
					<i data-lucide="home"></i>
					<strong><?php esc_html_e( 'Real Estate', 'wb-listora' ); ?></strong>
					<span><?php esc_html_e( '20 properties for sale and rent in NYC', 'wb-listora' ); ?></span>
				</div>
			</label>

			<label class="listora-demo-pack">
				<input type="radio" name="demo_pack" value="hotel" <?php checked( 'hotel', $selected_pack ); ?> />
				<div class="listora-demo-pack__card">
					<i data-lucide="bed"></i>
					<strong><?php esc_html_e( 'Hotel Directory', 'wb-listora' ); ?></strong>
					<span><?php esc_html_e( '20 hotels from budget to luxury', 'wb-listora' ); ?></span>
				</div>
			</label>

			<label class="listora-demo-pack">
				<input type="radio" name="demo_pack" value="general" <?php checked( 'general', $selected_pack ); ?> />
				<div class="listora-demo-pack__card">
					<i data-lucide="layout-grid"></i>
					<strong><?php esc_html_e( 'General Directory', 'wb-listora' ); ?></strong>
					<span><?php esc_html_e( '20 mixed listings across all types', 'wb-listora' ); ?></span>
				</div>
			</label>

			<label class="listora-demo-pack">
				<input type="radio" name="demo_pack" value="classified" <?php checked( 'classified', $selected_pack ); ?> />
				<div class="listora-demo-pack__card">
					<i data-lucide="tag"></i>
					<strong><?php esc_html_e( 'Classifieds', 'wb-listora' ); ?></strong>
					<span><?php esc_html_e( '8 used items and services for hire', 'wb-listora' ); ?></span>
				</div>
			</label>

			<label class="listora-demo-pack">
				<input type="radio" name="demo_pack" value="education" <?php checked( 'education', $selected_pack ); ?> />
				<div class="listora-demo-pack__card">
					<i data-lucide="graduation-cap"></i>
					<strong><?php esc_html_e( 'Education', 'wb-listora' ); ?></strong>
					<span><?php esc_html_e( '6 schools, courses, and bootcamps', 'wb-listora' ); ?></span>
				</div>
			</label>

			<label class="listora-demo-pack">
				<input type="radio" name="demo_pack" value="healthcare" <?php checked( 'healthcare', $selected_pack ); ?> />
				<div class="listora-demo-pack__card">
					<i data-lucide="heart-pulse"></i>
					<strong><?php esc_html_e( 'Healthcare', 'wb-listora' ); ?></strong>
					<span><?php esc_html_e( '6 clinics and doctors with Schema.org Physician markup', 'wb-listora' ); ?></span>
				</div>
			</label>

			<label class="listora-demo-pack">
				<input type="radio" name="demo_pack" value="place" <?php checked( 'place', $selected_pack ); ?> />
				<div class="listora-demo-pack__card">
					<i data-lucide="map-pin"></i>
					<strong><?php esc_html_e( 'Places & Attractions', 'wb-listora' ); ?></strong>
					<span><?php esc_html_e( '8 parks, museums, and tourist attractions', 'wb-listora' ); ?></span>
				</div>
			</label>

			<label class="listora-demo-pack listora-demo-pack--all">
				<input type="radio" name="demo_pack" value="all" <?php checked( 'all', $selected_pack ); ?> />
				<div class="listora-demo-pack__card">
					<i data-lucide="layers"></i>
					<strong><?php esc_html_e( 'All packs (recommended for QA)', 'wb-listora' ); ?></strong>
					<span><?php esc_html_e( '90+ listings across all 9 types — full demo, with images and services', 'wb-listora' ); ?></span>
				</div>
			</label>
		</div>

		<div style="display:flex;flex-direction:column;gap:0.75rem;margin-top:1.5rem;">
			<label class="listora-wizard__option-card">
				<input type="radio" name="import_demo" value="1" checked />
				<div>
					<strong><?php esc_html_e( 'Import selected pack (recommended)', 'wb-listora' ); ?></strong>
				</div>
			</label>
			<label class="listora-wizard__option-card">
				<input type="radio" name="import_demo" value="0" />
				<div>
					<span style="color:var(--listora-text-secondary, #666);"><?php esc_html_e( 'Skip demo content — I will add my own listings', 'wb-listora' ); ?></span>
				</div>
			</label>
		</div>
		<?php
	}

	/**
	 * Render the done step.
	 *
	 * @param array $data Saved wizard data.
	 */
	private function render_step_done( $data ) {
		?>
		<div class="listora-wizard__success">
			<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
				<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
			</svg>
			<h2><?php esc_html_e( 'Your directory is ready!', 'wb-listora' ); ?></h2>
			<p><?php esc_html_e( 'Everything is set up. Here\'s what you can do next:', 'wb-listora' ); ?></p>

			<div class="listora-wizard__actions">
				<a href="<?php echo esc_url( wb_listora_get_directory_url() ); ?>" class="listora-btn listora-btn--primary" target="_blank">
					<?php esc_html_e( 'View Your Directory →', 'wb-listora' ); ?>
				</a>
				<a href="<?php echo esc_url( wb_listora_get_submit_url() ); ?>" class="listora-btn" target="_blank">
					<?php esc_html_e( 'Add Your First Listing', 'wb-listora' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=listora-settings' ) ); ?>" class="listora-btn">
					<?php esc_html_e( 'Configure Settings', 'wb-listora' ); ?>
				</a>
			</div>
		</div>

		<form class="listora-wizard-done-form" method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=listora-setup&step=done' ) ); ?>">
			<?php wp_nonce_field( 'listora_wizard', 'listora_wizard_nonce' ); ?>
			<input type="hidden" name="listora_wizard_step" value="done" />
			<button type="submit" class="listora-btn"><?php esc_html_e( 'Go to Dashboard', 'wb-listora' ); ?></button>
		</form>
		<?php
	}

	// ─── Actions ───

	/**
	 * Create directory pages with pre-configured blocks.
	 *
	 * The 3 essential pages (Directory, Add Listing, My Dashboard) are now
	 * created on activation via Activator::ensure_essential_pages() so a user
	 * who skips the wizard still gets them. We re-run that method here as a
	 * safety net in case settings were ever cleared, then create any
	 * type-specific pages picked in the wizard.
	 *
	 * @param array $data Wizard configuration data.
	 */
	private function create_pages( $data ) {
		// Belt-and-suspenders: idempotent re-run of the activation creator.
		\WBListora\Activator::ensure_essential_pages();

		$selected_types = $data['selected_types'] ?? array( 'business' );

		// Type-specific landing pages — only ones the wizard owns.
		foreach ( $selected_types as $slug ) {
			$type = \WBListora\Core\Listing_Type_Registry::instance()->get( $slug );
			if ( ! $type ) {
				continue;
			}

			$existing = get_page_by_path( $slug );
			if ( $existing ) {
				continue;
			}

			wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_title'   => $type->get_name(),
					'post_name'    => $slug,
					'post_content' => '<!-- wp:listora/listing-search {"listingType":"' . esc_attr( $slug ) . "\"} /-->\n\n<!-- wp:listora/listing-grid {\"listingType\":\"" . esc_attr( $slug ) . '","columns":3} /-->',
					'post_status'  => 'publish',
					'post_author'  => get_current_user_id(),
				)
			);
		}
	}

	/**
	 * Import demo content from a selected pack. Supports the special pack
	 * value "all" which runs every available pack in sequence.
	 *
	 * @param array $data Wizard configuration data.
	 */
	private function import_demo_content( $data ) {
		$allowed_packs = array( 'restaurant', 'job-board', 'real-estate', 'hotel', 'general', 'classified', 'education', 'healthcare', 'place' );
		$pack          = sanitize_text_field( $data['demo_pack'] ?? 'general' );

		// Make sure test users exist for claims/favorites referenced by extras.
		require_once WB_LISTORA_PLUGIN_DIR . 'demo/class-demo-seeder.php';
		\WBListora\Demo\Demo_Seeder::ensure_test_users();

		if ( 'all' === $pack ) {
			foreach ( $allowed_packs as $slug ) {
				$pack_file = WB_LISTORA_PLUGIN_DIR . 'demo/' . $slug . '-pack.php';
				if ( file_exists( $pack_file ) ) {
					// Each pack file is self-contained and idempotent.
					require $pack_file;
				}
			}
			return;
		}

		if ( ! in_array( $pack, $allowed_packs, true ) ) {
			$pack = 'general';
		}

		$pack_file = WB_LISTORA_PLUGIN_DIR . 'demo/' . $pack . '-pack.php';

		if ( file_exists( $pack_file ) ) {
			require_once $pack_file;
		}
	}

	/**
	 * Finalize setup — save settings and mark complete.
	 *
	 * Writes both the legacy `wb_listora_settings.setup_complete` flag (read
	 * by older code paths) and the new top-level `wb_listora_setup_complete`
	 * option (the single source of truth for plug-and-play menu hiding,
	 * activation-redirect skipping, and Health Check status).
	 *
	 * @param array $data Wizard configuration data.
	 */
	private function finalize_setup( $data ) {
		$settings = get_option( 'wb_listora_settings', array() );

		if ( ! empty( $data['latitude'] ) && ! ( $data['is_global'] ?? false ) ) {
			$settings['map_default_lat'] = (float) $data['latitude'];
			$settings['map_default_lng'] = (float) $data['longitude'];
		}

		$settings['map_provider']   = $data['map_provider'] ?? 'osm';
		$settings['setup_complete'] = true;

		update_option( 'wb_listora_settings', $settings );

		// New top-level option — the contract everything else (menu visibility,
		// activation redirect, health check) reads. Stored as the literal
		// string "1" to match the option-update style WP-CLI uses.
		update_option( 'wb_listora_setup_complete', '1' );

		flush_rewrite_rules();
	}
}
