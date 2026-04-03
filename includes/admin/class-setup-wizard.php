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
	 * Handle form submission before rendering.
	 */
	public function __construct() {
		if ( isset( $_POST['listora_wizard_step'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['listora_wizard_nonce'] ?? '' ) ), 'listora_wizard' ) ) {
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
				if ( $data['import_demo'] ) {
					$this->import_demo_content( $data );
				}
				break;

			case 'done':
				// Save settings and mark complete.
				$this->finalize_setup( $data );
				delete_option( 'wb_listora_setup_data' );
				wp_safe_redirect( admin_url( 'admin.php?page=listora' ) );
				exit;
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing
		update_option( 'wb_listora_setup_data', $data );
	}

	/**
	 * Render the wizard.
	 */
	public function render() {
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
			<style>
				.listora-wizard { max-width: 700px; margin: 2rem auto; }
				.listora-wizard h1 { text-align: center; margin-bottom: 2rem; }
				.listora-wizard__progress { display: flex; gap: 4px; margin-bottom: 2rem; }
				.listora-wizard__progress-step {
					flex: 1; height: 6px; background: #ddd; border-radius: 3px;
					transition: background 0.2s;
				}
				.listora-wizard__progress-step.is-completed { background: #0073aa; }
				.listora-wizard__progress-step.is-current { background: #0073aa; opacity: 0.5; }
				.listora-wizard__card {
					background: #fff; border: 1px solid #ddd; border-radius: 8px;
					padding: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.06);
				}
				.listora-wizard__card h2 { margin-top: 0; }
				.listora-wizard__type-grid {
					display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
					gap: 0.75rem; margin: 1.5rem 0;
				}
				.listora-wizard__type-card {
					cursor: pointer; text-align: center; padding: 1rem; border: 2px solid #ddd;
					border-radius: 8px; transition: border-color 0.15s, background 0.15s;
				}
				.listora-wizard__type-card:hover { border-color: #0073aa; }
				.listora-wizard__type-card input { display: none; }
				.listora-wizard__type-card input:checked + .listora-wizard__type-inner {
					border-color: transparent;
				}
				.listora-wizard__type-card:has(input:checked) {
					border-color: #0073aa; background: rgba(0,115,170,0.05);
				}
				.listora-wizard__type-inner .dashicons {
					font-size: 2rem; width: 2rem; height: 2rem; display: block;
					margin: 0 auto 0.5rem; color: #0073aa;
				}
				.listora-wizard__type-name { font-weight: 500; font-size: 0.9rem; }
				.listora-wizard__nav {
					display: flex; justify-content: space-between; margin-top: 1.5rem;
					padding-top: 1rem; border-top: 1px solid #eee;
				}
				.listora-wizard__field { margin-bottom: 1.25rem; }
				.listora-wizard__field label { display: block; font-weight: 500; margin-bottom: 0.3rem; }
				.listora-wizard__field input[type="text"],
				.listora-wizard__field select {
					width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px;
				}
				.listora-wizard__pages-list { list-style: none; padding: 0; }
				.listora-wizard__pages-list li {
					padding: 0.5rem 0; border-bottom: 1px solid #eee;
					display: flex; justify-content: space-between;
				}
				.listora-wizard__pages-list code { background: #f0f0f0; padding: 0.15rem 0.4rem; border-radius: 3px; font-size: 0.85rem; }
				.listora-wizard__success { text-align: center; padding: 2rem; }
				.listora-wizard__success svg { color: #16a34a; margin-bottom: 1rem; }
				.listora-wizard__success h2 { color: #16a34a; }
				.listora-wizard__actions { display: flex; flex-direction: column; gap: 0.5rem; margin-top: 1.5rem; }
				.listora-wizard__actions a { text-decoration: none; }
			</style>

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
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=listora-setup&step=' . $prev_step ) ); ?>" class="button">
							<?php esc_html_e( '← Back', 'wb-listora' ); ?>
						</a>
						<?php else : ?>
						<span></span>
						<?php endif; ?>

						<button type="submit" class="button button-primary button-hero">
							<?php echo esc_html( 'demo' === $step ? __( 'Finish Setup →', 'wb-listora' ) : __( 'Continue →', 'wb-listora' ) ); ?>
						</button>
					</div>
					<?php endif; ?>
				</form>
			</div>

			<p style="text-align:center;margin-top:1rem;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=listora' ) ); ?>" style="color:#999;">
					<?php esc_html_e( 'Skip setup', 'wb-listora' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	// ─── Step Renderers ───

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
					<span class="dashicons <?php echo esc_attr( $type->get_icon() ); ?>"></span>
					<span class="listora-wizard__type-name"><?php echo esc_html( $type->get_name() ); ?></span>
				</div>
			</label>
			<?php endforeach; ?>
		</div>
		<?php
	}

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

	private function render_step_maps( $data ) {
		$provider = $data['map_provider'] ?? 'osm';
		?>
		<h2><?php esc_html_e( 'Choose your map provider', 'wb-listora' ); ?></h2>

		<div style="display:flex;flex-direction:column;gap:1rem;margin:1.5rem 0;">
			<label class="listora-wizard__type-card" style="display:flex;align-items:center;gap:1rem;padding:1rem;text-align:left;">
				<input type="radio" name="map_provider" value="osm" <?php checked( 'osm', $provider ); ?> style="display:inline;" />
				<div>
					<strong><?php esc_html_e( 'OpenStreetMap (Free)', 'wb-listora' ); ?></strong><br/>
					<span style="color:#666;"><?php esc_html_e( 'Free, no API key needed. Works immediately.', 'wb-listora' ); ?></span>
				</div>
			</label>

			<label class="listora-wizard__type-card" style="display:flex;align-items:center;gap:1rem;padding:1rem;text-align:left;opacity:0.6;">
				<input type="radio" name="map_provider" value="google" <?php checked( 'google', $provider ); ?> style="display:inline;" disabled />
				<div>
					<strong><?php esc_html_e( 'Google Maps', 'wb-listora' ); ?></strong>
					<span style="background:#0073aa;color:#fff;font-size:0.7rem;padding:0.1rem 0.4rem;border-radius:3px;margin-left:0.3rem;">Pro</span><br/>
					<span style="color:#666;"><?php esc_html_e( 'Requires API key + billing. Available with Pro plugin.', 'wb-listora' ); ?></span>
				</div>
			</label>
		</div>
		<?php
	}

	private function render_step_pages( $data ) {
		$selected_types = $data['selected_types'] ?? array( 'business' );
		?>
		<h2><?php esc_html_e( 'We\'ll create these pages for you', 'wb-listora' ); ?></h2>
		<p><?php esc_html_e( 'Each page comes with pre-configured blocks. You can customize them later in the block editor.', 'wb-listora' ); ?></p>

		<ul class="listora-wizard__pages-list">
			<li>
				<span><?php esc_html_e( 'Directory Home', 'wb-listora' ); ?></span>
				<code>/listings</code>
			</li>
			<li>
				<span><?php esc_html_e( 'Add Listing', 'wb-listora' ); ?></span>
				<code>/add-listing</code>
			</li>
			<li>
				<span><?php esc_html_e( 'My Dashboard', 'wb-listora' ); ?></span>
				<code>/dashboard</code>
			</li>
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
			</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	private function render_step_demo( $data ) {
		?>
		<h2><?php esc_html_e( 'Want some sample listings?', 'wb-listora' ); ?></h2>
		<p><?php esc_html_e( 'Demo listings help you see how your directory looks with real content.', 'wb-listora' ); ?></p>

		<div style="margin:1.5rem 0;">
			<label class="listora-wizard__type-card" style="display:flex;align-items:center;gap:1rem;padding:1rem;text-align:left;">
				<input type="radio" name="import_demo" value="1" checked style="display:inline;" />
				<div>
					<strong><?php esc_html_e( 'Yes, import demo listings (recommended)', 'wb-listora' ); ?></strong><br/>
					<span style="color:#666;"><?php esc_html_e( '5 sample listings per selected type with descriptions.', 'wb-listora' ); ?></span>
				</div>
			</label>

			<label class="listora-wizard__type-card" style="display:flex;align-items:center;gap:1rem;padding:1rem;text-align:left;margin-top:0.75rem;">
				<input type="radio" name="import_demo" value="0" style="display:inline;" />
				<div>
					<strong><?php esc_html_e( 'No, I\'ll add my own', 'wb-listora' ); ?></strong>
				</div>
			</label>
		</div>
		<?php
	}

	private function render_step_done( $data ) {
		?>
		<div class="listora-wizard__success">
			<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
				<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
			</svg>
			<h2><?php esc_html_e( 'Your directory is ready!', 'wb-listora' ); ?></h2>
			<p><?php esc_html_e( 'Everything is set up. Here\'s what you can do next:', 'wb-listora' ); ?></p>

			<div class="listora-wizard__actions">
				<a href="<?php echo esc_url( home_url( '/listings/' ) ); ?>" class="button button-primary button-hero" target="_blank">
					<?php esc_html_e( 'View Your Directory →', 'wb-listora' ); ?>
				</a>
				<a href="<?php echo esc_url( home_url( '/add-listing/' ) ); ?>" class="button" target="_blank">
					<?php esc_html_e( 'Add Your First Listing', 'wb-listora' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=listora-settings' ) ); ?>" class="button">
					<?php esc_html_e( 'Configure Settings', 'wb-listora' ); ?>
				</a>
			</div>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=listora-setup&step=done' ) ); ?>" style="text-align:center;margin-top:1rem;">
			<?php wp_nonce_field( 'listora_wizard', 'listora_wizard_nonce' ); ?>
			<input type="hidden" name="listora_wizard_step" value="done" />
			<button type="submit" class="button"><?php esc_html_e( 'Go to Dashboard', 'wb-listora' ); ?></button>
		</form>
		<?php
	}

	// ─── Actions ───

	/**
	 * Create directory pages with pre-configured blocks.
	 */
	private function create_pages( $data ) {
		$selected_types = $data['selected_types'] ?? array( 'business' );

		$pages = array(
			'listings'    => array(
				'title'   => __( 'Directory', 'wb-listora' ),
				'slug'    => 'listings',
				'content' => "<!-- wp:listora/listing-search /-->\n\n<!-- wp:columns -->\n<!-- wp:column {\"width\":\"65%\"} -->\n<!-- wp:listora/listing-grid {\"columns\":2} /-->\n<!-- /wp:column -->\n<!-- wp:column {\"width\":\"35%\"} -->\n<!-- wp:listora/listing-map {\"height\":\"600px\"} /-->\n<!-- /wp:column -->\n<!-- /wp:columns -->",
			),
			'add-listing' => array(
				'title'   => __( 'Add Listing', 'wb-listora' ),
				'slug'    => 'add-listing',
				'content' => '<!-- wp:listora/listing-submission /-->',
			),
			'dashboard'   => array(
				'title'   => __( 'My Dashboard', 'wb-listora' ),
				'slug'    => 'dashboard',
				'content' => '<!-- wp:listora/user-dashboard /-->',
			),
		);

		// Type-specific pages.
		foreach ( $selected_types as $slug ) {
			$type = \WBListora\Core\Listing_Type_Registry::instance()->get( $slug );
			if ( ! $type ) {
				continue;
			}

			$pages[ $slug ] = array(
				'title'   => $type->get_name(),
				'slug'    => $slug,
				'content' => '<!-- wp:listora/listing-search {"listingType":"' . esc_attr( $slug ) . "\"} /-->\n\n<!-- wp:listora/listing-grid {\"listingType\":\"" . esc_attr( $slug ) . '","columns":3} /-->',
			);
		}

		foreach ( $pages as $key => $page_data ) {
			$existing = get_page_by_path( $page_data['slug'] );
			if ( $existing ) {
				continue;
			}

			wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_title'   => $page_data['title'],
					'post_name'    => $page_data['slug'],
					'post_content' => $page_data['content'],
					'post_status'  => 'publish',
					'post_author'  => get_current_user_id(),
				)
			);
		}
	}

	/**
	 * Import demo content.
	 */
	private function import_demo_content( $data ) {
		// Use the rich seed file for realistic demo data.
		$seed_file = WB_LISTORA_PLUGIN_DIR . 'seed-demo.php';
		if ( file_exists( $seed_file ) ) {
			require_once $seed_file;
			return;
		}

		// Fallback: basic demo content if seed file missing.
		$selected_types = $data['selected_types'] ?? array( 'business' );
		$city           = $data['city'] ?? 'New York';
		$lat            = $data['latitude'] ?? 40.7128;
		$lng            = $data['longitude'] ?? -74.006;

		$demo_listings = array(
			'business'    => array(
				array(
					'title' => 'City Center Gym',
					'desc'  => 'Full-service gym with modern equipment and personal trainers.',
				),
				array(
					'title' => 'Quick Print Shop',
					'desc'  => 'Professional printing services for business and personal needs.',
				),
				array(
					'title' => 'Green Thumb Florist',
					'desc'  => 'Beautiful flower arrangements for every occasion.',
				),
				array(
					'title' => 'Tech Repair Hub',
					'desc'  => 'Expert phone and computer repair services.',
				),
				array(
					'title' => 'Happy Paws Pet Store',
					'desc'  => 'Everything your pet needs — food, toys, accessories, and grooming.',
				),
			),
			'restaurant'  => array(
				array(
					'title' => 'The Golden Fork',
					'desc'  => 'Upscale Italian dining with handmade pasta and wood-fired pizza.',
				),
				array(
					'title' => 'Sakura House',
					'desc'  => 'Authentic Japanese sushi and ramen in a modern setting.',
				),
				array(
					'title' => 'Casa Miguel',
					'desc'  => 'Traditional Mexican cuisine with fresh ingredients and bold flavors.',
				),
				array(
					'title' => 'Dragon Palace',
					'desc'  => 'Family-style Chinese restaurant serving Szechuan and Cantonese dishes.',
				),
				array(
					'title' => 'The Spice Route',
					'desc'  => 'Indian restaurant featuring tandoori specialties and curries.',
				),
			),
			'real-estate' => array(
				array(
					'title' => 'Sunny 2BR Apartment Downtown',
					'desc'  => 'Bright and spacious 2-bedroom apartment with city views.',
				),
				array(
					'title' => 'Modern Family Home — 4BR',
					'desc'  => 'Beautiful family home with large backyard and updated kitchen.',
				),
				array(
					'title' => 'Luxury Penthouse Suite',
					'desc'  => 'Top-floor penthouse with panoramic views and premium finishes.',
				),
				array(
					'title' => 'Cozy Studio Near Park',
					'desc'  => 'Perfect starter apartment, walking distance to the park.',
				),
				array(
					'title' => 'Commercial Space — Retail',
					'desc'  => 'Prime retail location on main street, 1200 sqft.',
				),
			),
			'hotel'       => array(
				array(
					'title' => 'Grand Central Hotel',
					'desc'  => 'Luxury hotel in the heart of downtown with rooftop bar.',
				),
				array(
					'title' => 'Cozy Corner B&B',
					'desc'  => 'Charming bed and breakfast with homemade breakfast.',
				),
				array(
					'title' => 'Seaside Resort & Spa',
					'desc'  => 'Beachfront resort with spa, pool, and fine dining.',
				),
				array(
					'title' => 'Budget Inn Express',
					'desc'  => 'Clean and affordable rooms for business travelers.',
				),
				array(
					'title' => 'Boutique Hotel Aria',
					'desc'  => 'Stylish boutique hotel with unique themed rooms.',
				),
			),
		);

		foreach ( $selected_types as $type_slug ) {
			$listings = $demo_listings[ $type_slug ] ?? $demo_listings['business'];

			foreach ( $listings as $i => $listing_data ) {
				$offset_lat = $lat + ( ( $i - 2 ) * 0.008 ) + ( mt_rand( -30, 30 ) / 10000 );
				$offset_lng = $lng + ( ( $i - 2 ) * 0.008 ) + ( mt_rand( -30, 30 ) / 10000 );

				$post_id = wp_insert_post(
					array(
						'post_type'    => 'listora_listing',
						'post_title'   => $listing_data['title'],
						'post_content' => $listing_data['desc'],
						'post_status'  => 'publish',
						'post_author'  => get_current_user_id(),
					)
				);

				if ( is_wp_error( $post_id ) ) {
					continue;
				}

				wp_set_object_terms( $post_id, $type_slug, 'listora_listing_type' );
				update_post_meta( $post_id, '_listora_demo_content', true );

				// Set address.
				\WBListora\Core\Meta_Handler::set_value(
					$post_id,
					'address',
					array(
						'address' => ( $i + 1 ) * 100 . ' Main Street, ' . $city,
						'lat'     => round( $offset_lat, 7 ),
						'lng'     => round( $offset_lng, 7 ),
						'city'    => $city,
						'state'   => '',
						'country' => $data['country'] ?? '',
					)
				);

				// Trigger indexing.
				$indexer = new \WBListora\Search\Search_Indexer();
				$indexer->index_listing( $post_id, get_post( $post_id ) );
			}
		}
	}

	/**
	 * Finalize setup — save settings and mark complete.
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

		flush_rewrite_rules();
	}
}
