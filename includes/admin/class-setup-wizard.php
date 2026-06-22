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
	 * Constructor — kept lightweight so it can be safely instantiated during
	 * page render. POST submissions are processed earlier on `admin_init`
	 * (see {@see self::init()}); doing it here would attempt a redirect after
	 * WordPress has already emitted the admin header.
	 */
	public function __construct() {}

	/**
	 * Wire the early POST handler.
	 *
	 * Must run on `admin_init` priority 1 so that the final-step redirect
	 * (`wp_safe_redirect( admin.php?page=listora )`) fires before WP outputs
	 * the admin header — otherwise PHP warns "headers already sent" and the
	 * user lands on a blank screen instead of the dashboard. Card 9867159785.
	 */
	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'handle_post_submission' ), 1 );
		add_action( 'admin_notices', array( __CLASS__, 'render_demo_import_notice' ) );
	}

	/**
	 * Surface non-fatal demo-import errors as a dismissible admin notice.
	 *
	 * The wizard's demo step wraps each pack in a Throwable-catching guard
	 * (BC 9910697597) so partial failures don't WSOD. Failed pack names + the
	 * thrown messages are stashed in a per-user transient and rendered here
	 * on the next admin page load.
	 */
	public static function render_demo_import_notice(): void {
		$transient_key = 'wb_listora_demo_import_errors_' . get_current_user_id();
		$errors        = get_transient( $transient_key );

		if ( empty( $errors ) || ! is_array( $errors ) ) {
			return;
		}

		delete_transient( $transient_key );

		echo '<div class="notice notice-warning is-dismissible"><p><strong>' . esc_html__( 'Listora demo import: some content could not be created.', 'wb-listora' ) . '</strong></p><ul style="margin-left:1.5em;list-style:disc;">';
		foreach ( $errors as $error ) {
			echo '<li><code>' . esc_html( (string) $error ) . '</code></li>';
		}
		echo '</ul><p>' . esc_html__( 'You can re-run the importer from the Setup Wizard or seed demo data manually from Settings → Advanced.', 'wb-listora' ) . '</p></div>';
	}

	/**
	 * Process a wizard step POST early in the admin lifecycle.
	 *
	 * Bound to `admin_init` priority 1 by {@see self::init()}. Validates
	 * page guard, capability, and nonce, then dispatches to the per-step
	 * processor on a fresh instance.
	 */
	public static function handle_post_submission(): void {
		// Page guard — only act on the wizard's own POSTs.
		if ( ! isset( $_GET['page'] ) || 'listora-setup' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! isset( $_POST['listora_wizard_step'] ) ) {
			return;
		}

		// Capability + nonce. Setup wizard writes plugin settings — require
		// the capability that gates all other settings writes. Nonce alone
		// only proves the form came from our origin, not that the user is
		// authorised.
		if ( ! current_user_can( 'manage_listora_settings' ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['listora_wizard_nonce'] ?? '' ) ), 'listora_wizard' ) ) {
			return;
		}

		$step = sanitize_text_field( wp_unslash( $_POST['listora_wizard_step'] ) );
		( new self() )->process_step( $step );
	}

	/**
	 * Process a wizard step submission.
	 *
	 * @param string $step Current step ID.
	 */
	public function process_step( $step ) {
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
					// Queue the import on Action Scheduler (synchronous fallback
					// for tiny packs is handled inside Background_Import). The
					// returned run_id is stashed so the next render can poll the
					// progress endpoint and show a live progress bar.
					$run_id = $this->import_demo_content( $data );
					if ( $run_id ) {
						$data['demo_run_id'] = $run_id;
					}
				} else {
					unset( $data['demo_run_id'] );
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

		$step_keys = array_keys( $steps );

		// Normalize any unrecognized step (e.g. a stale `finish` bookmark, or a
		// hand-edited URL) to the final `done` step. Without this the render
		// switch below falls through with no output — a blank card with a stray
		// Continue button instead of the completion summary.
		if ( ! in_array( $step, $step_keys, true ) ) {
			$step = 'done';
		}

		$current_idx = array_search( $step, $step_keys, true );
		$next_step   = $step_keys[ $current_idx + 1 ] ?? 'done';
		$prev_step   = $current_idx > 0 ? $step_keys[ $current_idx - 1 ] : '';

		// First-run wizard has its own step-by-step layout; skip auto-header.
		add_filter( 'wb_listora_skip_admin_header', '__return_true' );
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
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=listora-setup&step=' . $prev_step ) ); ?>" class="listora-btn wp-element-button">
							<?php esc_html_e( '← Back', 'wb-listora' ); ?>
						</a>
						<?php else : ?>
						<span></span>
						<?php endif; ?>

						<button type="submit" class="listora-btn wp-element-button listora-btn--primary">
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

		<div class="listora-wizard__field listora-wizard__field--coords">
			<div>
				<label for="wizard-lat"><?php esc_html_e( 'Latitude', 'wb-listora' ); ?></label>
				<input type="text" id="wizard-lat" name="latitude" value="<?php echo esc_attr( $data['latitude'] ?? '40.7128' ); ?>" />
			</div>
			<div>
				<label for="wizard-lng"><?php esc_html_e( 'Longitude', 'wb-listora' ); ?></label>
				<input type="text" id="wizard-lng" name="longitude" value="<?php echo esc_attr( $data['longitude'] ?? '-74.0060' ); ?>" />
			</div>
		</div>

		<label class="listora-wizard__global-label">
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

		<div class="listora-wizard__map-options">
			<label class="listora-wizard__option-card">
				<input type="radio" name="map_provider" value="osm" <?php checked( 'osm', $provider ); ?> />
				<div>
					<strong><?php esc_html_e( 'OpenStreetMap (Free)', 'wb-listora' ); ?></strong><br/>
					<span class="listora-wizard__option-card__desc"><?php esc_html_e( 'Free, no API key needed. Works immediately.', 'wb-listora' ); ?></span>
				</div>
			</label>

			<label class="listora-wizard__option-card listora-wizard__option-card--disabled">
				<input type="radio" name="map_provider" value="google" <?php checked( 'google', $provider ); ?> disabled />
				<div>
					<strong><?php esc_html_e( 'Google Maps', 'wb-listora' ); ?></strong>
					<span class="listora-pro-badge" data-pro-feature="google-maps">Pro</span><br/>
					<span class="listora-wizard__option-card__desc"><?php esc_html_e( 'Requires API key + billing. Available with Pro plugin.', 'wb-listora' ); ?></span>
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

		<div class="listora-wizard__import-options">
			<label class="listora-wizard__option-card">
				<input type="radio" name="import_demo" value="1" checked />
				<div>
					<strong><?php esc_html_e( 'Import selected pack (recommended)', 'wb-listora' ); ?></strong>
				</div>
			</label>
			<label class="listora-wizard__option-card">
				<input type="radio" name="import_demo" value="0" />
				<div>
					<span class="listora-wizard__option-card__desc"><?php esc_html_e( 'Skip demo content — I will add my own listings', 'wb-listora' ); ?></span>
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
		// Finalize the moment the "Your directory is ready!" screen is reached.
		// Previously setup was only finalized when the user clicked the single
		// "Go to Dashboard" form button — the three link CTAs (View Directory /
		// Add Listing / Configure Settings) bypassed it, so setup never
		// completed and the onboarding notice persisted forever (card
		// 10020076541). Reaching this step means every real step is done, so it
		// is the correct point to persist completion. Guarded + idempotent:
		// finalize_setup() re-runs harmlessly, but the guard avoids a needless
		// flush_rewrite_rules() on every re-render.
		if ( ! \WBListora\Admin\Admin::is_setup_complete() ) {
			$this->finalize_setup( $data );
		}

		$run_id = isset( $data['demo_run_id'] ) ? \WBListora\ImportExport\Background_Import::sanitize_run_id( (string) $data['demo_run_id'] ) : '';
		?>
		<div class="listora-wizard__success">
			<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
				<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
			</svg>
			<h2><?php esc_html_e( 'Your directory is ready!', 'wb-listora' ); ?></h2>
			<p><?php esc_html_e( 'Everything is set up. Here\'s what you can do next:', 'wb-listora' ); ?></p>

			<?php if ( '' !== $run_id ) : ?>
				<?php $this->render_import_progress( $run_id ); ?>
			<?php endif; ?>

			<div class="listora-wizard__actions">
				<a href="<?php echo esc_url( wb_listora_get_directory_url() ); ?>" class="listora-btn wp-element-button listora-btn--primary" target="_blank">
					<?php esc_html_e( 'View Your Directory →', 'wb-listora' ); ?>
				</a>
				<a href="<?php echo esc_url( wb_listora_get_submit_url() ); ?>" class="listora-btn wp-element-button" target="_blank">
					<?php esc_html_e( 'Add Your First Listing', 'wb-listora' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=listora-settings' ) ); ?>" class="listora-btn wp-element-button">
					<?php esc_html_e( 'Configure Settings', 'wb-listora' ); ?>
				</a>
			</div>
		</div>

		<form class="listora-wizard-done-form" method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=listora-setup&step=done' ) ); ?>">
			<?php wp_nonce_field( 'listora_wizard', 'listora_wizard_nonce' ); ?>
			<input type="hidden" name="listora_wizard_step" value="done" />
			<button type="submit" class="listora-btn wp-element-button"><?php esc_html_e( 'Go to Dashboard', 'wb-listora' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Render the background-import progress region and wire its polling script.
	 *
	 * The region is a token-styled card with an ARIA progressbar. A small
	 * vanilla-JS poller (enqueued, never inline) reads the REST progress
	 * endpoint and updates the bar until the run reports done/failed.
	 *
	 * @param string $run_id Background-import run identifier.
	 * @return void
	 */
	private function render_import_progress( $run_id ) {
		wp_enqueue_style(
			'listora-import-progress',
			WB_LISTORA_PLUGIN_URL . 'assets/css/admin/import-progress.css',
			array( 'listora-admin' ),
			WB_LISTORA_VERSION
		);
		wp_enqueue_script(
			'listora-import-progress',
			WB_LISTORA_PLUGIN_URL . 'build/admin/import-progress.js',
			array( 'wp-api-fetch', 'wp-i18n' ),
			WB_LISTORA_VERSION,
			true
		);
		wp_localize_script(
			'listora-import-progress',
			'listoraImportProgress',
			array(
				'runId'    => $run_id,
				'endpoint' => '/' . WB_LISTORA_REST_NAMESPACE . '/import/progress/' . $run_id,
				'nonce'    => wp_create_nonce( 'wp_rest' ),
			)
		);
		?>
		<div class="listora-import-progress" data-run-id="<?php echo esc_attr( $run_id ); ?>">
			<p class="listora-import-progress__label">
				<i data-lucide="download-cloud" aria-hidden="true"></i>
				<span class="listora-import-progress__text"><?php esc_html_e( 'Importing demo content in the background…', 'wb-listora' ); ?></span>
			</p>
			<div
				class="listora-import-progress__track"
				role="progressbar"
				aria-valuemin="0"
				aria-valuemax="100"
				aria-valuenow="0"
				aria-label="<?php esc_attr_e( 'Demo import progress', 'wb-listora' ); ?>"
			>
				<div class="listora-import-progress__bar" style="width:0%"></div>
			</div>
			<p class="listora-import-progress__stats" aria-live="polite">
				<span class="listora-import-progress__count">0</span>
				<?php esc_html_e( 'items imported', 'wb-listora' ); ?>
			</p>
		</div>
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
	 * Queue demo content import from a selected pack over Action Scheduler.
	 *
	 * Supports the special pack value "all" which runs every available pack in
	 * sequence. Each pack is queued as a separate Action Scheduler unit (group
	 * `wb-listora`) by {@see \WBListora\ImportExport\Background_Import}, with a
	 * final index-rebuild job. Tiny single packs fall back to a synchronous
	 * run inside Background_Import, so the wizard always returns promptly.
	 *
	 * The historic Throwable-catching guard (BC 9910697597) now lives inside
	 * each batch handler in Background_Import — a thrown pack records an error
	 * message on the run and lets Action Scheduler retry, instead of
	 * white-screening the wizard.
	 *
	 * @param array $data Wizard configuration data.
	 * @return string The background-import run_id (poll the progress endpoint),
	 *                or '' if the import could not be queued.
	 */
	private function import_demo_content( $data ) {
		if ( ! class_exists( '\WBListora\ImportExport\Background_Import' ) ) {
			return '';
		}

		$allowed_packs = array( 'restaurant', 'job-board', 'real-estate', 'hotel', 'general', 'classified', 'education', 'healthcare', 'place' );
		$pack          = sanitize_text_field( $data['demo_pack'] ?? 'general' );

		$packs_to_run = ( 'all' === $pack )
			? $allowed_packs
			: array( in_array( $pack, $allowed_packs, true ) ? $pack : 'general' );

		return \WBListora\ImportExport\Background_Import::queue_demo( $packs_to_run );
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
