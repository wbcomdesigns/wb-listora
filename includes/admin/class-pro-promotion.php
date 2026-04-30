<?php
/**
 * Pro Promotion — upsell surfaces for the Free plugin.
 *
 * Centralizes every Pro upsell touch-point so they all share the same
 * Pro-active gate (`wb_listora_is_pro_active()`) and the same UTM-tagged
 * upgrade URL. Surfaces:
 *
 *   1. Admin "Upgrade to Pro" submenu page (hero, comparison matrix, feature
 *      highlights, social proof, license activation, FAQ).
 *   2. Inline modal triggered from `.listora-pro-badge` chips on the settings
 *      sidebar (and anywhere else the badge is used).
 *   3. Three frontend CTAs gated by a single filter:
 *        - User dashboard "Reviews" tab footer.
 *        - Listing detail map block (when provider is OSM).
 *        - Admin Submissions settings tab inline banner.
 *   4. WordPress dashboard widget ("Unlock more with WB Listora Pro").
 *
 * All surfaces hide cleanly when Pro is active. No popups, no nags — every
 * dismissable surface stores its dismissal in a 3-day cookie. Use the
 * `wb_listora_pro_cta_should_show( $surface )` filter to override per-page.
 *
 * @package WBListora\Admin
 */

namespace WBListora\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Pro promotion / upsell surface coordinator.
 */
class Pro_Promotion {

	/**
	 * Submenu slug for the upgrade page.
	 */
	const PAGE_SLUG = 'listora-upgrade';

	/**
	 * Cookie prefix used for dismissable CTAs.
	 */
	const COOKIE_PREFIX = 'wb_listora_promo_';

	/**
	 * UTM-tagged upgrade URL builder.
	 *
	 * @param string $medium   utm_medium value (e.g. "upgrade-page", "modal").
	 * @param string $campaign utm_campaign value.
	 * @param string $anchor   Optional URL fragment.
	 * @return string
	 */
	public static function upgrade_url( $medium = 'upgrade-page', $campaign = 'free-to-pro', $anchor = '' ) {
		$base = 'https://wbcomdesigns.com/products/wb-listora/';
		$args = array(
			'utm_source'   => 'plugin',
			'utm_medium'   => sanitize_key( $medium ),
			'utm_campaign' => sanitize_key( $campaign ),
		);

		$url = add_query_arg( $args, $base );
		if ( $anchor ) {
			$url .= '#' . ltrim( $anchor, '#' );
		}

		/** This filter is documented in includes/class-template-helpers.php */
		return (string) apply_filters( 'wb_listora_upgrade_url', $url );
	}

	/**
	 * Internal admin URL for the upgrade page.
	 *
	 * @param string $anchor Optional anchor.
	 * @return string
	 */
	public static function upgrade_page_url( $anchor = '' ) {
		$url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		if ( $anchor ) {
			$url .= '#' . ltrim( $anchor, '#' );
		}
		return $url;
	}

	/**
	 * Constructor — registers all hooks. Bails early when Pro is active so we
	 * never even register upsell surfaces in that case (cleanest possible
	 * "no nagging" guarantee).
	 */
	public function __construct() {
		if ( wb_listora_is_pro_active() ) {
			return;
		}

		// Admin: submenu, page render, dashboard widget.
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 50 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ), 20 );

		// AJAX: license validation + dismissal tracking.
		add_action( 'wp_ajax_wb_listora_validate_license', array( $this, 'ajax_validate_license' ) );
		add_action( 'wp_ajax_wb_listora_dismiss_promo', array( $this, 'ajax_dismiss_promo' ) );

		// Settings page: inline banner on Submissions tab + modal mount.
		add_action( 'wb_listora_settings_tab_content', array( $this, 'render_submissions_banner' ), 5 );
		add_action( 'admin_footer', array( $this, 'render_admin_modal_root' ) );

		// Frontend CTAs.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'wb_listora_after_dashboard_reviews', array( $this, 'render_dashboard_reviews_cta' ) );
		add_action( 'wb_listora_after_map', array( $this, 'render_map_osm_hint' ) );
	}

	/**
	 * Should the given CTA surface render?
	 *
	 * Bails when Pro is active. Filterable per surface ID so 3rd parties
	 * (or WPML/Polylang per-language overrides) can suppress individual CTAs
	 * without unhooking everything.
	 *
	 * @param string $surface Surface identifier.
	 * @return bool
	 */
	public static function should_show( $surface ) {
		if ( wb_listora_is_pro_active() ) {
			return false;
		}

		$cookie_key = self::COOKIE_PREFIX . sanitize_key( $surface );
		if ( isset( $_COOKIE[ $cookie_key ] ) && '1' === $_COOKIE[ $cookie_key ] ) {
			return false;
		}

		/**
		 * Filter whether a Pro CTA surface should render.
		 *
		 * @param bool   $show    Default: true (Pro inactive + not dismissed).
		 * @param string $surface Surface identifier.
		 */
		return (bool) apply_filters( 'wb_listora_pro_cta_should_show', true, $surface );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Admin: submenu + page render
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Register the "Upgrade to Pro" submenu.
	 *
	 * Menu title carries an inline accent dot via HTML — WP allows this and
	 * it keeps the upsell visually distinct from operational menu items
	 * without any extra CSS file.
	 */
	public function register_submenu() {
		$menu_title = '<span style="color:#7c3aed;font-weight:600;">' . esc_html__( 'Upgrade to Pro', 'wb-listora' ) . '</span>';

		add_submenu_page(
			'listora',
			__( 'Upgrade to Pro', 'wb-listora' ),
			$menu_title,
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_upgrade_page' )
		);
	}

	/**
	 * Enqueue assets for upgrade page + modal.
	 *
	 * @param string $hook_suffix Current admin hook.
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		$is_listora_admin = ( false !== strpos( (string) $hook_suffix, 'listora' ) );
		if ( ! $is_listora_admin ) {
			return;
		}

		// Shared pro-cta CSS (modal reuses some tokens).
		wp_enqueue_style(
			'listora-pro-cta',
			WB_LISTORA_PLUGIN_URL . 'assets/css/shared/pro-cta.css',
			array(),
			WB_LISTORA_VERSION
		);

		// Promotion-specific CSS (upgrade page + modal).
		wp_enqueue_style(
			'listora-pro-promotion',
			WB_LISTORA_PLUGIN_URL . 'assets/css/admin/pro-promotion.css',
			array( 'listora-pro-cta' ),
			WB_LISTORA_VERSION
		);

		// Promotion JS (modal + license validate).
		wp_enqueue_script(
			'listora-pro-promotion',
			WB_LISTORA_PLUGIN_URL . 'assets/js/admin/pro-promotion.js',
			array(),
			WB_LISTORA_VERSION,
			true
		);

		wp_localize_script(
			'listora-pro-promotion',
			'wbListoraPromo',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'wb_listora_promo' ),
				'upgradeUrl'     => self::upgrade_url( 'modal', 'free-to-pro' ),
				'upgradePageUrl' => self::upgrade_page_url(),
				'features'       => $this->get_feature_dictionary(),
				'i18n'           => array(
					'close'        => __( 'Close', 'wb-listora' ),
					'learnMore'    => __( 'Learn more', 'wb-listora' ),
					'upgrade'      => __( 'Upgrade to Pro', 'wb-listora' ),
					'requiresPro'  => __( 'Requires Pro', 'wb-listora' ),
					'validating'   => __( 'Validating license…', 'wb-listora' ),
					'licenseValid' => __( 'License valid! Download Pro from your account.', 'wb-listora' ),
					'licenseError' => __( 'License could not be validated. Check the key and try again.', 'wb-listora' ),
					'networkError' => __( 'Network error. Please try again.', 'wb-listora' ),
					'enterKey'     => __( 'Please enter a license key.', 'wb-listora' ),
				),
			)
		);
	}

	/**
	 * Render the full upgrade page.
	 */
	public function render_upgrade_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$upgrade_url = self::upgrade_url( 'upgrade-page', 'free-to-pro' );
		?>
		<div class="wrap wb-listora-admin listora-promo-page">

			<?php // ── Hero ── ?>
			<section class="listora-promo-hero" id="hero">
				<div class="listora-promo-hero__pill">
					<span aria-hidden="true">★</span>
					<?php esc_html_e( 'WB Listora Pro', 'wb-listora' ); ?>
				</div>
				<h1 class="listora-promo-hero__title">
					<?php esc_html_e( 'Unlock the full WB Listora experience', 'wb-listora' ); ?>
				</h1>
				<p class="listora-promo-hero__subtitle">
					<?php esc_html_e( 'Pro extends Free with two-sided marketplace, credit monetization, multi-criteria & photo reviews, Google Maps, Quick View, side-by-side comparison, BuddyPress integration, audit logs, outgoing webhooks, and more — all on top of the data you already have.', 'wb-listora' ); ?>
				</p>
				<div class="listora-promo-hero__actions">
					<a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener" class="listora-btn listora-btn--primary listora-promo-hero__cta">
						<?php esc_html_e( 'Upgrade Now', 'wb-listora' ); ?>
					</a>
					<a href="#license-activation" class="listora-promo-hero__secondary">
						<?php esc_html_e( 'Already have a license? Activate it', 'wb-listora' ); ?> &rarr;
					</a>
				</div>
			</section>

			<?php // ── Feature highlights ── ?>
			<?php $this->render_feature_highlights(); ?>

			<?php // ── Comparison matrix ── ?>
			<?php $this->render_comparison_matrix(); ?>

			<?php // ── Social proof ── ?>
			<?php $this->render_social_proof(); ?>

			<?php // ── License activation ── ?>
			<?php $this->render_license_activation(); ?>

			<?php // ── FAQ ── ?>
			<?php $this->render_faq(); ?>

			<?php // ── Final CTA ── ?>
			<section class="listora-promo-final-cta">
				<h2><?php esc_html_e( 'Ready to ship a real marketplace?', 'wb-listora' ); ?></h2>
				<p><?php esc_html_e( '14-day money-back guarantee. Per-site license. Lifetime updates available.', 'wb-listora' ); ?></p>
				<a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener" class="listora-btn listora-btn--primary">
					<?php esc_html_e( 'Get WB Listora Pro', 'wb-listora' ); ?>
				</a>
			</section>

		</div>
		<?php
	}

	/**
	 * Render the 6-card feature highlights grid.
	 */
	private function render_feature_highlights() {
		$cards = array(
			array(
				'icon'  => 'M12 2l3 7h7l-5.5 4.5L18 21l-6-4-6 4 1.5-7.5L2 9h7z',
				'title' => __( 'Reverse Listings Marketplace', 'wb-listora' ),
				'desc'  => __( 'Two-sided marketplace: buyers post needs, vendors quote, owners accept the best offer.', 'wb-listora' ),
				'slug'  => 'reverse-listings',
			),
			array(
				'icon'  => 'M12 2v20M2 12h20',
				'title' => __( 'Credit-based monetization', 'wb-listora' ),
				'desc'  => __( 'Sell listings, featured slots, and pricing plans with built-in Stripe via the Wbcom Credits SDK.', 'wb-listora' ),
				'slug'  => 'monetization',
			),
			array(
				'icon'  => 'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z',
				'title' => __( 'Multi-criteria + photo reviews', 'wb-listora' ),
				'desc'  => __( 'Restaurant food/service/ambiance. Hotel rooms/cleanliness. Photo uploads on every review.', 'wb-listora' ),
				'slug'  => 'reviews',
			),
			array(
				'icon'  => 'M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z M12 7a3 3 0 1 1 0 6 3 3 0 0 1 0-6z',
				'title' => __( 'Google Maps + Quick View + Compare', 'wb-listora' ),
				'desc'  => __( 'Premium discovery surfaces — Google Maps tiles, modal previews, side-by-side listing comparison.', 'wb-listora' ),
				'slug'  => 'discovery',
			),
			array(
				'icon'  => 'M9 12l2 2 4-4 M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z',
				'title' => __( 'Audit Log + Outgoing Webhooks', 'wb-listora' ),
				'desc'  => __( '90-day audit log of every action. 11 outgoing webhook events — Zapier-ready out of the box.', 'wb-listora' ),
				'slug'  => 'ops',
			),
			array(
				'icon'  => 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z M23 21v-2a4 4 0 0 0-3-3.87 M16 3.13a4 4 0 0 1 0 7.75',
				'title' => __( 'BuddyPress / BuddyBoss native', 'wb-listora' ),
				'desc'  => __( 'Activity stream entries, profile tabs, member notifications. Works with both BuddyPress and BuddyBoss.', 'wb-listora' ),
				'slug'  => 'community',
			),
		);
		?>
		<section class="listora-promo-section" id="highlights">
			<div class="listora-promo-section__head">
				<h2><?php esc_html_e( 'What Pro unlocks', 'wb-listora' ); ?></h2>
				<p><?php esc_html_e( 'Six headline upgrades — every one of them production-grade and shipping today.', 'wb-listora' ); ?></p>
			</div>
			<div class="listora-promo-cards">
				<?php foreach ( $cards as $card ) : ?>
					<article class="listora-promo-card">
						<div class="listora-promo-card__icon" aria-hidden="true">
							<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<path d="<?php echo esc_attr( $card['icon'] ); ?>" />
							</svg>
						</div>
						<h3><?php echo esc_html( $card['title'] ); ?></h3>
						<p><?php echo esc_html( $card['desc'] ); ?></p>
						<a href="#feature-<?php echo esc_attr( $card['slug'] ); ?>" class="listora-promo-card__link">
							<?php esc_html_e( 'Learn more', 'wb-listora' ); ?> &rarr;
						</a>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	/**
	 * Render the comparison matrix.
	 */
	private function render_comparison_matrix() {
		$groups = $this->get_comparison_groups();
		?>
		<section class="listora-promo-section" id="comparison">
			<div class="listora-promo-section__head">
				<h2><?php esc_html_e( 'Free vs Pro — feature by feature', 'wb-listora' ); ?></h2>
				<p><?php esc_html_e( 'Everything in Free is in Pro. Pro adds the surfaces below.', 'wb-listora' ); ?></p>
			</div>

			<?php foreach ( $groups as $group_id => $group ) : ?>
				<div class="listora-promo-matrix" id="feature-<?php echo esc_attr( $group_id ); ?>">
					<h3 class="listora-promo-matrix__title"><?php echo esc_html( $group['label'] ); ?></h3>
					<table class="listora-promo-matrix__table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Feature', 'wb-listora' ); ?></th>
								<th scope="col" class="listora-promo-matrix__col"><?php esc_html_e( 'Free', 'wb-listora' ); ?></th>
								<th scope="col" class="listora-promo-matrix__col listora-promo-matrix__col--pro"><?php esc_html_e( 'Pro', 'wb-listora' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $group['rows'] as $row ) : ?>
								<tr id="feature-<?php echo esc_attr( $row['slug'] ); ?>">
									<th scope="row" class="listora-promo-matrix__feature">
										<?php echo esc_html( $row['label'] ); ?>
									</th>
									<td class="listora-promo-matrix__cell <?php echo $row['free'] ? 'is-yes' : 'is-no'; ?>">
										<?php if ( $row['free'] ) : ?>
											<span class="listora-promo-check" aria-label="<?php esc_attr_e( 'Included in Free', 'wb-listora' ); ?>">&#10003;</span>
										<?php else : ?>
											<span class="listora-promo-dash" aria-label="<?php esc_attr_e( 'Not in Free', 'wb-listora' ); ?>">&mdash;</span>
										<?php endif; ?>
									</td>
									<td class="listora-promo-matrix__cell is-yes">
										<span class="listora-promo-check" aria-label="<?php esc_attr_e( 'Included in Pro', 'wb-listora' ); ?>">&#10003;</span>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endforeach; ?>
		</section>
		<?php
	}

	/**
	 * Render social proof block (placeholders).
	 */
	private function render_social_proof() {
		// TODO: replace placeholders with real customer quotes.
		$quotes = array(
			array(
				'quote' => __( '"Listora Pro replaced three plugins for us — directory, marketplace, and credit billing — and our submission workflow is now 4x faster."', 'wb-listora' ),
				'name'  => __( 'Placeholder Customer', 'wb-listora' ),
				'role'  => __( 'Marketplace owner', 'wb-listora' ),
			),
			array(
				'quote' => __( '"The audit log + webhook combo lets us pipe every listing change straight into our internal CRM. Zero glue code."', 'wb-listora' ),
				'name'  => __( 'Placeholder Customer', 'wb-listora' ),
				'role'  => __( 'Operations lead', 'wb-listora' ),
			),
			array(
				'quote' => __( '"Multi-criteria reviews + photo uploads are exactly what our restaurant directory needed. Conversion is up double-digits."', 'wb-listora' ),
				'name'  => __( 'Placeholder Customer', 'wb-listora' ),
				'role'  => __( 'Founder', 'wb-listora' ),
			),
		);
		?>
		<section class="listora-promo-section listora-promo-quotes" id="social-proof">
			<div class="listora-promo-section__head">
				<h2><?php esc_html_e( 'Loved by directory operators', 'wb-listora' ); ?></h2>
				<p><?php esc_html_e( 'A small selection — full case studies coming soon.', 'wb-listora' ); ?></p>
			</div>
			<div class="listora-promo-quotes__grid">
				<?php foreach ( $quotes as $quote ) : ?>
					<figure class="listora-promo-quote">
						<blockquote><?php echo esc_html( $quote['quote'] ); ?></blockquote>
						<figcaption>
							<strong><?php echo esc_html( $quote['name'] ); ?></strong>
							<span><?php echo esc_html( $quote['role'] ); ?></span>
						</figcaption>
					</figure>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	/**
	 * Render the license-activation form.
	 */
	private function render_license_activation() {
		?>
		<section class="listora-promo-section listora-promo-activation" id="license-activation">
			<div class="listora-promo-section__head">
				<h2><?php esc_html_e( 'Already purchased Pro?', 'wb-listora' ); ?></h2>
				<p><?php esc_html_e( 'Validate your license here, then download and install the Pro plugin from your wbcomdesigns.com account.', 'wb-listora' ); ?></p>
			</div>

			<div class="listora-promo-activation__card">
				<form id="listora-promo-license-form" novalidate>
					<label for="listora-promo-license-key" class="listora-promo-activation__label">
						<?php esc_html_e( 'License key', 'wb-listora' ); ?>
					</label>
					<div class="listora-promo-activation__row">
						<input
							type="text"
							id="listora-promo-license-key"
							name="license_key"
							class="listora-promo-activation__input"
							placeholder="XXXX-XXXX-XXXX-XXXX"
							autocomplete="off"
							spellcheck="false"
						/>
						<button type="submit" class="listora-btn listora-btn--primary">
							<?php esc_html_e( 'Validate &amp; Install Pro', 'wb-listora' ); ?>
						</button>
					</div>
					<p class="listora-promo-activation__help">
						<?php esc_html_e( 'For security, we never auto-install plugins from this screen. Validation confirms your license; you then download and install the Pro plugin manually.', 'wb-listora' ); ?>
					</p>
					<div id="listora-promo-license-status" class="listora-promo-activation__status" role="status" aria-live="polite"></div>
				</form>
			</div>
		</section>
		<?php
	}

	/**
	 * Render FAQ section.
	 */
	private function render_faq() {
		$faqs = array(
			array(
				'q' => __( 'Do I keep my data when upgrading?', 'wb-listora' ),
				'a' => __( 'Yes. Pro extends Free — every listing, review, claim, and setting stays exactly where it is. Pro adds new tables and settings around the existing data.', 'wb-listora' ),
			),
			array(
				'q' => __( 'Can I try Pro before buying?', 'wb-listora' ),
				'a' => __( 'Pro ships with a 14-day money-back guarantee — no questions asked. If it is not the right fit, request a refund from your wbcomdesigns.com account.', 'wb-listora' ),
			),
			array(
				'q' => __( 'What happens if my license expires?', 'wb-listora' ),
				'a' => __( 'Your existing Pro features continue to work. You stop receiving updates and support, and the Free plugin keeps every Free feature working. No data is lost.', 'wb-listora' ),
			),
			array(
				'q' => __( 'How many sites can I use Pro on?', 'wb-listora' ),
				'a' => __( 'Licenses are per-site. Multi-site bundles are available — see the pricing table on the upgrade page.', 'wb-listora' ),
			),
			array(
				'q' => __( 'How do I get support?', 'wb-listora' ),
				'a' => __( 'Pro customers get priority support at wbcomdesigns.com/support. Free users can ask in the WordPress.org support forum.', 'wb-listora' ),
			),
		);
		?>
		<section class="listora-promo-section listora-promo-faq" id="faq">
			<div class="listora-promo-section__head">
				<h2><?php esc_html_e( 'Frequently asked questions', 'wb-listora' ); ?></h2>
			</div>
			<div class="listora-promo-faq__list">
				<?php foreach ( $faqs as $i => $faq ) : ?>
					<details class="listora-promo-faq__item" <?php echo 0 === $i ? 'open' : ''; ?>>
						<summary><?php echo esc_html( $faq['q'] ); ?></summary>
						<p><?php echo esc_html( $faq['a'] ); ?></p>
					</details>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	// ─────────────────────────────────────────────────────────────────────
	// Comparison data + feature dictionary
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * The full Free-vs-Pro comparison matrix.
	 *
	 * @return array<string,array{label:string,rows:array<int,array{slug:string,label:string,free:bool}>}>
	 */
	private function get_comparison_groups() {
		return array(
			'discovery'    => array(
				'label' => __( 'Discovery & UX', 'wb-listora' ),
				'rows'  => array(
					array(
						'slug'  => 'grid',
						'label' => __( 'Listing Grid + Search + Map', 'wb-listora' ),
						'free'  => true,
					),
					array(
						'slug'  => 'osm',
						'label' => __( 'OpenStreetMap', 'wb-listora' ),
						'free'  => true,
					),
					array(
						'slug'  => 'google-maps',
						'label' => __( 'Google Maps', 'wb-listora' ),
						'free'  => false,
					),
					array(
						'slug'  => 'quick-view',
						'label' => __( 'Quick View Modal', 'wb-listora' ),
						'free'  => false,
					),
					array(
						'slug'  => 'infinite-scroll',
						'label' => __( 'Infinite Scroll', 'wb-listora' ),
						'free'  => false,
					),
					array(
						'slug'  => 'saved-searches',
						'label' => __( 'Saved Searches + Daily Alerts', 'wb-listora' ),
						'free'  => false,
					),
					array(
						'slug'  => 'compare',
						'label' => __( 'Side-by-side Comparison', 'wb-listora' ),
						'free'  => false,
					),
					array(
						'slug'  => 'pseo',
						'label' => __( 'Programmatic SEO Pages', 'wb-listora' ),
						'free'  => false,
					),
				),
			),
			'reviews'      => array(
				'label' => __( 'Trust, Reviews & Leads', 'wb-listora' ),
				'rows'  => array(
					array(
						'slug'  => 'star-ratings',
						'label' => __( 'Star ratings + helpful votes', 'wb-listora' ),
						'free'  => true,
					),
					array(
						'slug'  => 'multi-criteria',
						'label' => __( 'Multi-criteria reviews', 'wb-listora' ),
						'free'  => false,
					),
					array(
						'slug'  => 'photo-reviews',
						'label' => __( 'Photo reviews', 'wb-listora' ),
						'free'  => false,
					),
					array(
						'slug'  => 'verification',
						'label' => __( 'Verification + Custom Badges', 'wb-listora' ),
						'free'  => false,
					),
					array(
						'slug'  => 'lead-form',
						'label' => __( 'Lead form (contact owner)', 'wb-listora' ),
						'free'  => false,
					),
					array(
						'slug'  => 'digest',
						'label' => __( 'Notification Digest', 'wb-listora' ),
						'free'  => false,
					),
				),
			),
			'monetization' => array(
				'label' => __( 'Monetization', 'wb-listora' ),
				'rows'  => array(
					array(
						'slug'  => 'submission',
						'label' => __( 'Listing submission', 'wb-listora' ),
						'free'  => true,
					),
					array(
						'slug'  => 'featured',
						'label' => __( 'Featured listings', 'wb-listora' ),
						'free'  => true,
					),
					array(
						'slug'  => 'credits',
						'label' => __( 'Credit system + ledger', 'wb-listora' ),
						'free'  => false,
					),
					array(
						'slug'  => 'plans',
						'label' => __( 'Pricing Plans (CPT)', 'wb-listora' ),
						'free'  => false,
					),
					array(
						'slug'  => 'coupons',
						'label' => __( 'Coupons', 'wb-listora' ),
						'free'  => false,
					),
					array(
						'slug'  => 'webhooks-in',
						'label' => __( 'Inbound payment webhooks', 'wb-listora' ),
						'free'  => false,
					),
					array(
						'slug'  => 'license',
						'label' => __( 'License + auto-updates', 'wb-listora' ),
						'free'  => false,
					),
				),
			),
			'community'    => array(
				'label' => __( 'Community & Marketplace', 'wb-listora' ),
				'rows'  => array(
					array(
						'slug'  => 'buddypress',
						'label' => __( 'BuddyPress / BuddyBoss integration', 'wb-listora' ),
						'free'  => false,
					),
					array(
						'slug'  => 'reverse-listings',
						'label' => __( 'Reverse Listings (Post-a-Need)', 'wb-listora' ),
						'free'  => false,
					),
					array(
						'slug'  => 'vendor-responses',
						'label' => __( 'Vendor responses + accept/reject', 'wb-listora' ),
						'free'  => false,
					),
				),
			),
			'ops'          => array(
				'label' => __( 'Operations & Branding', 'wb-listora' ),
				'rows'  => array(
					array(
						'slug'  => 'settings-wizard',
						'label' => __( 'Settings + Setup Wizard', 'wb-listora' ),
						'free'  => true,
					),
					array(
						'slug'  => 'audit-log',
						'label' => __( 'Audit Log (90-day)', 'wb-listora' ),
						'free'  => false,
					),
					array(
						'slug'  => 'webhooks-out',
						'label' => __( 'Outgoing Webhooks (11 events)', 'wb-listora' ),
						'free'  => false,
					),
					array(
						'slug'  => 'moderators',
						'label' => __( 'Moderator Role + round-robin', 'wb-listora' ),
						'free'  => false,
					),
					array(
						'slug'  => 'white-label',
						'label' => __( 'White Label', 'wb-listora' ),
						'free'  => false,
					),
					array(
						'slug'  => 'visibility',
						'label' => __( 'Visibility Modes (private/coming soon)', 'wb-listora' ),
						'free'  => false,
					),
					array(
						'slug'  => 'analytics',
						'label' => __( 'Analytics Dashboard', 'wb-listora' ),
						'free'  => false,
					),
					array(
						'slug'  => 'google-places',
						'label' => __( 'Google Places Importer', 'wb-listora' ),
						'free'  => false,
					),
					array(
						'slug'  => 'csv-mapper',
						'label' => __( 'Visual CSV Field Mapper', 'wb-listora' ),
						'free'  => false,
					),
					array(
						'slug'  => 'migrators',
						'label' => __( '4 Competitor Migrators (visual)', 'wb-listora' ),
						'free'  => false,
					),
				),
			),
		);
	}

	/**
	 * Feature dictionary used by the modal JS.
	 *
	 * Keyed by slug — must match the `data-pro-feature` attribute on badges.
	 *
	 * @return array<string,array{title:string,description:string,anchor:string}>
	 */
	private function get_feature_dictionary() {
		return array(
			'google-maps'      => array(
				'title'       => __( 'Google Maps', 'wb-listora' ),
				'description' => __( 'Switch your directory map from OpenStreetMap to Google Maps tiles, with Places autocomplete on submission, Street View previews on detail pages, and Places-powered geocoding. Free uses OSM tiles which are great for most directories — Pro is for teams that want premium imagery and the familiar Google UI.', 'wb-listora' ),
				'anchor'      => 'feature-google-maps',
			),
			'multi-criteria'   => array(
				'title'       => __( 'Multi-criteria reviews', 'wb-listora' ),
				'description' => __( 'Replace the single 1-5 star rating with per-criterion ratings tailored to each listing type — restaurant food/service/ambiance, hotel rooms/cleanliness/staff, etc. Aggregate scores, criterion breakdowns on detail pages, and search filtering by criterion average.', 'wb-listora' ),
				'anchor'      => 'feature-multi-criteria',
			),
			'photo-reviews'    => array(
				'title'       => __( 'Photo reviews', 'wb-listora' ),
				'description' => __( 'Let reviewers attach photos to their reviews. Lightbox gallery on detail pages, automatic thumbnail generation, EXIF stripping, and per-listing-type upload limits. Drives trust and conversion.', 'wb-listora' ),
				'anchor'      => 'feature-photo-reviews',
			),
			'verification'     => array(
				'title'       => __( 'Verification + Custom Badges', 'wb-listora' ),
				'description' => __( 'Verified-owner badge + admin-defined custom badges (Eco-friendly, Family-owned, Editor\'s Pick) you can apply to any listing. Badges appear on cards, detail pages, search results, and map markers.', 'wb-listora' ),
				'anchor'      => 'feature-verification',
			),
			'credits'          => array(
				'title'       => __( 'Credit system + ledger', 'wb-listora' ),
				'description' => __( 'Charge users in credits for listing submission, featured upgrades, and renewal. Built on the shared Wbcom Credits SDK — Stripe-ready, hold-and-settle ledger, refund support, and admin override tools.', 'wb-listora' ),
				'anchor'      => 'feature-credits',
			),
			'plans'            => array(
				'title'       => __( 'Pricing Plans (CPT)', 'wb-listora' ),
				'description' => __( 'Define paid plans (Bronze/Silver/Gold) with custom credit costs, listing limits, and featured-slot quotas. Plans show on the submission form; users pick a plan, the credit holds, and the listing goes live.', 'wb-listora' ),
				'anchor'      => 'feature-plans',
			),
			'coupons'          => array(
				'title'       => __( 'Coupons', 'wb-listora' ),
				'description' => __( 'Percentage and fixed-amount coupon codes that apply to plan checkout and credit-pack purchases. Per-coupon usage limits, expiration dates, and stackable rules.', 'wb-listora' ),
				'anchor'      => 'feature-coupons',
			),
			'audit-log'        => array(
				'title'       => __( 'Audit Log', 'wb-listora' ),
				'description' => __( 'Every listing/review/claim action — created, edited, deleted, status changed — written to a 90-day rolling audit log with user, IP, and before/after snapshot. Filter by entity, user, or date range.', 'wb-listora' ),
				'anchor'      => 'feature-audit-log',
			),
			'webhooks-out'     => array(
				'title'       => __( 'Outgoing Webhooks', 'wb-listora' ),
				'description' => __( 'Fire HTTP POST webhooks on 11 events — listing.created, listing.updated, review.created, claim.submitted, etc. Includes signing secret, retry queue, and a request log so you can debug deliveries.', 'wb-listora' ),
				'anchor'      => 'feature-webhooks-out',
			),
			'moderators'       => array(
				'title'       => __( 'Moderator Role + round-robin', 'wb-listora' ),
				'description' => __( 'Custom Moderator role that can approve listings/reviews/claims without full admin access. Round-robin assignment so a team of 3 moderators each get every third pending item.', 'wb-listora' ),
				'anchor'      => 'feature-moderators',
			),
			'white-label'      => array(
				'title'       => __( 'White Label', 'wb-listora' ),
				'description' => __( 'Replace "WB Listora" with your own brand name and logo across the admin UI, emails, and dashboard. Optionally hide the plugin from the plugins list for client installs.', 'wb-listora' ),
				'anchor'      => 'feature-white-label',
			),
			'visibility'       => array(
				'title'       => __( 'Visibility Modes', 'wb-listora' ),
				'description' => __( 'Put the directory into Private (logged-in users only) or Coming Soon (admins only) mode in one click — useful for staging directories and pre-launch campaigns.', 'wb-listora' ),
				'anchor'      => 'feature-visibility',
			),
			'analytics'        => array(
				'title'       => __( 'Analytics Dashboard', 'wb-listora' ),
				'description' => __( 'Track listing views, search queries, lead-form submissions, and credit revenue over time. Per-listing analytics for owners on the user dashboard.', 'wb-listora' ),
				'anchor'      => 'feature-analytics',
			),
			'reverse-listings' => array(
				'title'       => __( 'Reverse Listings (Post-a-Need)', 'wb-listora' ),
				'description' => __( 'Two-sided marketplace mode — buyers post what they need, vendors quote, the buyer accepts the best offer. Includes lead-form integration, vendor reputation, and credit-based vendor responses.', 'wb-listora' ),
				'anchor'      => 'feature-reverse-listings',
			),
			'buddypress'       => array(
				'title'       => __( 'BuddyPress / BuddyBoss integration', 'wb-listora' ),
				'description' => __( 'Native integration — listings appear in the activity stream, members get a "My Listings" profile tab, and follow/unfollow notifications fire when listings are updated. Works with both BuddyPress and BuddyBoss.', 'wb-listora' ),
				'anchor'      => 'feature-buddypress',
			),
			'quick-view'       => array(
				'title'       => __( 'Quick View Modal', 'wb-listora' ),
				'description' => __( 'A "Quick View" button on every listing card opens a modal with the gallery, key fields, and CTAs without leaving the listing-grid page. Lifts engagement significantly.', 'wb-listora' ),
				'anchor'      => 'feature-quick-view',
			),
			'compare'          => array(
				'title'       => __( 'Side-by-side comparison', 'wb-listora' ),
				'description' => __( 'Users add up to 4 listings to a comparison tray and view them side-by-side — fields, photos, ratings, and meta — on a dedicated comparison page.', 'wb-listora' ),
				'anchor'      => 'feature-compare',
			),
			'saved-searches'   => array(
				'title'       => __( 'Saved Searches + Daily Alerts', 'wb-listora' ),
				'description' => __( 'Logged-in users save any search (filters + map bounds) and opt into daily-digest emails when new matching listings are published.', 'wb-listora' ),
				'anchor'      => 'feature-saved-searches',
			),
			'pseo'             => array(
				'title'       => __( 'Programmatic SEO pages', 'wb-listora' ),
				'description' => __( 'Auto-generated landing pages for every category × location combination — "Italian restaurants in Brooklyn", "Hotels in Lisbon" — with proper schema, breadcrumbs, and canonical tags.', 'wb-listora' ),
				'anchor'      => 'feature-pseo',
			),
			'lead-form'        => array(
				'title'       => __( 'Lead form (contact owner)', 'wb-listora' ),
				'description' => __( 'Add a configurable lead form to listing detail pages so visitors can message owners directly. Per-listing routing, spam protection, and an admin inbox.', 'wb-listora' ),
				'anchor'      => 'feature-lead-form',
			),
			'digest'           => array(
				'title'       => __( 'Notification Digest', 'wb-listora' ),
				'description' => __( 'Bundle listing notifications into a daily/weekly digest email instead of one-per-event — better for owners with many listings.', 'wb-listora' ),
				'anchor'      => 'feature-digest',
			),
			'infinite-scroll'  => array(
				'title'       => __( 'Infinite Scroll', 'wb-listora' ),
				'description' => __( 'Replace the listing-grid pagination with smooth infinite scroll — listings auto-load as the user scrolls, with a back-to-top button and proper SEO fallback.', 'wb-listora' ),
				'anchor'      => 'feature-infinite-scroll',
			),
			'google-places'    => array(
				'title'       => __( 'Google Places Importer', 'wb-listora' ),
				'description' => __( 'Search Google Places by category and area, then bulk-import the results as listings — name, address, phone, hours, photos, and ratings all pre-filled.', 'wb-listora' ),
				'anchor'      => 'feature-google-places',
			),
			'csv-mapper'       => array(
				'title'       => __( 'Visual CSV Field Mapper', 'wb-listora' ),
				'description' => __( 'Drag-and-drop UI for mapping CSV columns to Listora fields — preview rows, transform values, and validate before import.', 'wb-listora' ),
				'anchor'      => 'feature-csv-mapper',
			),
			'migrators'        => array(
				'title'       => __( '4 Competitor Migrators', 'wb-listora' ),
				'description' => __( 'One-click visual migration from GeoDirectory, Business Directory Plugin, Listify, and HivePress — listings, taxonomies, reviews, and media.', 'wb-listora' ),
				'anchor'      => 'feature-migrators',
			),
		);
	}

	// ─────────────────────────────────────────────────────────────────────
	// Modal mount
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Render a hidden modal root in the admin footer.
	 *
	 * The JS clones content into this when a Pro badge is clicked.
	 */
	public function render_admin_modal_root() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->id, 'listora' ) ) {
			return;
		}
		?>
		<div id="listora-promo-modal" class="listora-promo-modal" hidden role="dialog" aria-modal="true" aria-labelledby="listora-promo-modal-title">
			<div class="listora-promo-modal__backdrop" data-promo-close></div>
			<div class="listora-promo-modal__dialog" role="document">
				<button type="button" class="listora-promo-modal__close" data-promo-close aria-label="<?php esc_attr_e( 'Close', 'wb-listora' ); ?>">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
				</button>
				<div class="listora-promo-modal__badge">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
					<?php esc_html_e( 'Pro', 'wb-listora' ); ?>
				</div>
				<h2 id="listora-promo-modal-title" class="listora-promo-modal__title"></h2>
				<p class="listora-promo-modal__desc"></p>
				<div class="listora-promo-modal__actions">
					<a href="#" class="listora-btn listora-promo-modal__learn" data-promo-learn>
						<?php esc_html_e( 'Learn more', 'wb-listora' ); ?>
					</a>
					<a href="#" class="listora-btn listora-btn--primary listora-promo-modal__upgrade" data-promo-upgrade target="_blank" rel="noopener">
						<?php esc_html_e( 'Upgrade to Pro', 'wb-listora' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
	}

	// ─────────────────────────────────────────────────────────────────────
	// Frontend CTAs
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Make sure the shared pro-cta CSS is loaded on frontend pages where we
	 * inject CTAs.
	 */
	public function enqueue_frontend_assets() {
		// pro-cta is registered in Assets::enqueue_frontend(). Just make sure
		// shared.css (its dependency) is registered first.
		if ( ! wp_style_is( 'listora-pro-cta', 'registered' ) ) {
			return;
		}
		// Frontend dismiss script for the dashboard CTA.
		wp_register_script(
			'listora-promo-frontend',
			WB_LISTORA_PLUGIN_URL . 'assets/js/admin/pro-promotion.js',
			array(),
			WB_LISTORA_VERSION,
			true
		);
	}

	/**
	 * Render the Reviews-tab footer CTA in the user dashboard.
	 *
	 * Subtle, dismissible — 3-day cookie suppression.
	 */
	public function render_dashboard_reviews_cta() {
		if ( ! self::should_show( 'dashboard_reviews' ) ) {
			return;
		}

		wp_enqueue_style( 'listora-pro-cta' );
		wp_enqueue_script( 'listora-promo-frontend' );

		$upgrade_url = self::upgrade_url( 'dashboard-reviews', 'free-to-pro' );
		?>
		<div class="listora-pro-cta listora-pro-cta--inline" data-promo-surface="dashboard_reviews" role="complementary">
			<div class="listora-pro-cta__badge" aria-hidden="true">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
				<?php esc_html_e( 'Pro', 'wb-listora' ); ?>
			</div>
			<div class="listora-pro-cta__body">
				<h3 class="listora-pro-cta__title"><?php esc_html_e( 'Want photo reviews + multi-criteria ratings?', 'wb-listora' ); ?></h3>
				<p class="listora-pro-cta__description">
					<?php esc_html_e( 'Pro upgrades reviews with per-criterion ratings, photo uploads, and verified-owner replies.', 'wb-listora' ); ?>
				</p>
			</div>
			<div class="listora-pro-cta__actions">
				<a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener" class="listora-btn listora-btn--primary">
					<?php esc_html_e( 'Upgrade to Pro', 'wb-listora' ); ?>
				</a>
				<button type="button" class="listora-pro-cta__dismiss" data-promo-dismiss="dashboard_reviews" aria-label="<?php esc_attr_e( 'Dismiss', 'wb-listora' ); ?>">
					&times;
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a small OSM "switch to Google Maps with Pro" hint under the map.
	 *
	 * Only fires when map provider is OSM (free tier).
	 *
	 * @param array $attributes Map block attributes.
	 */
	public function render_map_osm_hint( $attributes = array() ) {
		unset( $attributes ); // Not currently needed — provider is global.

		if ( ! self::should_show( 'map_osm_hint' ) ) {
			return;
		}

		$provider = (string) wb_listora_get_setting( 'map_provider', 'osm' );
		if ( 'osm' !== $provider ) {
			return;
		}

		wp_enqueue_style( 'listora-pro-cta' );
		wp_enqueue_script( 'listora-promo-frontend' );

		$upgrade_url = self::upgrade_url( 'map-osm-hint', 'free-to-pro' );
		?>
		<p class="listora-promo-map-hint" data-promo-surface="map_osm_hint">
			<span class="listora-promo-map-hint__leader"><?php esc_html_e( 'Powered by OpenStreetMap', 'wb-listora' ); ?></span>
			<span aria-hidden="true">&middot;</span>
			<a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener">
				<?php esc_html_e( 'Switch to Google Maps with Pro', 'wb-listora' ); ?> &rarr;
			</a>
			<button type="button" class="listora-promo-map-hint__dismiss" data-promo-dismiss="map_osm_hint" aria-label="<?php esc_attr_e( 'Dismiss', 'wb-listora' ); ?>">&times;</button>
		</p>
		<?php
	}

	/**
	 * Render the Submissions-tab inline banner.
	 *
	 * @param string $tab_id Tab being rendered.
	 */
	public function render_submissions_banner( $tab_id ) {
		if ( 'submissions' !== $tab_id ) {
			return;
		}
		if ( ! self::should_show( 'settings_submissions' ) ) {
			return;
		}

		$upgrade_url = self::upgrade_url( 'settings-submissions', 'free-to-pro' );
		?>
		<div class="listora-pro-cta listora-pro-cta--banner" data-promo-surface="settings_submissions" role="complementary" style="margin-top:0;margin-bottom:1rem;">
			<div class="listora-pro-cta__badge" aria-hidden="true">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
				<?php esc_html_e( 'Pro', 'wb-listora' ); ?>
			</div>
			<div class="listora-pro-cta__body">
				<h3 class="listora-pro-cta__title"><?php esc_html_e( 'Want to charge for listings?', 'wb-listora' ); ?></h3>
				<p class="listora-pro-cta__description">
					<?php esc_html_e( 'Pro adds Pricing Plans, Coupons, and the Wbcom Credits SDK — turn submissions into recurring revenue.', 'wb-listora' ); ?>
				</p>
			</div>
			<div class="listora-pro-cta__actions">
				<a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener" class="listora-btn listora-btn--primary">
					<?php esc_html_e( 'Upgrade to Pro', 'wb-listora' ); ?>
				</a>
				<button type="button" class="listora-pro-cta__dismiss" data-promo-dismiss="settings_submissions" aria-label="<?php esc_attr_e( 'Dismiss', 'wb-listora' ); ?>">
					&times;
				</button>
			</div>
		</div>
		<?php
	}

	// ─────────────────────────────────────────────────────────────────────
	// Dashboard widget
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Register the WP dashboard upsell widget.
	 */
	public function register_dashboard_widget() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! self::should_show( 'wp_dashboard_widget' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'wb-listora-upgrade-widget',
			__( 'Unlock more with WB Listora Pro', 'wb-listora' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render the dashboard upsell widget body.
	 */
	public function render_dashboard_widget() {
		$benefits = array(
			__( 'Two-sided marketplace + reverse listings', 'wb-listora' ),
			__( 'Credits, pricing plans, coupons + Stripe', 'wb-listora' ),
			__( 'Multi-criteria + photo reviews', 'wb-listora' ),
		);

		$upgrade_url = self::upgrade_url( 'wp-dashboard-widget', 'free-to-pro' );
		?>
		<div class="listora-promo-widget">
			<p class="listora-promo-widget__lead">
				<?php esc_html_e( 'You are running the Free version. Pro adds production-grade marketplace, monetization, and reviews surfaces:', 'wb-listora' ); ?>
			</p>
			<ul class="listora-promo-widget__list">
				<?php foreach ( $benefits as $benefit ) : ?>
					<li>
						<span class="listora-promo-widget__check" aria-hidden="true">&#10003;</span>
						<?php echo esc_html( $benefit ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="listora-promo-widget__actions">
				<a href="<?php echo esc_url( self::upgrade_page_url() ); ?>" class="button button-primary">
					<?php esc_html_e( 'Learn more', 'wb-listora' ); ?>
				</a>
				<a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener" class="listora-promo-widget__buy">
					<?php esc_html_e( 'Buy now', 'wb-listora' ); ?> &rarr;
				</a>
				<button type="button" class="listora-promo-widget__dismiss" data-promo-dismiss="wp_dashboard_widget">
					<?php esc_html_e( 'Hide for 3 days', 'wb-listora' ); ?>
				</button>
			</p>
		</div>
		<?php
	}

	// ─────────────────────────────────────────────────────────────────────
	// AJAX handlers
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Validate a license key against the wblistora.com API.
	 *
	 * Mirrors the Pro License class endpoint shape — returns a structured
	 * response so the JS can show a download link or error.
	 */
	public function ajax_validate_license() {
		check_ajax_referer( 'wb_listora_promo', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied.', 'wb-listora' ) ),
				403
			);
		}

		$key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
		if ( '' === $key ) {
			wp_send_json_error(
				array( 'message' => __( 'Please enter a license key.', 'wb-listora' ) ),
				400
			);
		}

		// Mirror the Pro License class endpoint shape: lmfwc/v2/licenses/validate/{key}.
		$endpoint = 'https://wblistora.com/wp-json/lmfwc/v2/licenses/validate/' . rawurlencode( $key );
		$response = wp_remote_get(
			$endpoint,
			array(
				'timeout'   => 10,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error(
				array(
					'message' => $response->get_error_message(),
					'reason'  => 'network',
				),
				502
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );
		$data   = json_decode( $body, true );

		if ( 200 !== $status || ! is_array( $data ) || empty( $data['success'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'License could not be validated. Check the key and try again.', 'wb-listora' ),
					'reason'  => 'invalid',
				),
				400
			);
		}

		wp_send_json_success(
			array(
				'message'     => __( 'License valid! Download Pro from your wbcomdesigns.com account.', 'wb-listora' ),
				'downloadUrl' => 'https://wbcomdesigns.com/my-account/downloads/?utm_source=plugin&utm_medium=upgrade-page&utm_campaign=free-to-pro',
			)
		);
	}

	/**
	 * Record a CTA dismissal — server-side fallback when the JS cookie path
	 * fails (e.g. very strict cookie policies).
	 */
	public function ajax_dismiss_promo() {
		// Per-user dismissal — wp_ajax_wb_listora_dismiss_promo (no _nopriv_)
		// gates to logged-in users via WP core. Action sets a 3-day cookie
		// scoped to this visitor only; no shared state mutated. A capability
		// check would over-restrict (CTA targets every logged-in role).
		// Verified false positive; see audit/manifest.json#/notes T4.
		check_ajax_referer( 'wb_listora_promo', 'nonce' );

		$surface = isset( $_POST['surface'] ) ? sanitize_key( wp_unslash( $_POST['surface'] ) ) : '';
		if ( '' === $surface ) {
			wp_send_json_error( array( 'message' => 'missing surface' ), 400 );
		}

		$cookie_key = self::COOKIE_PREFIX . $surface;
		setcookie(
			$cookie_key,
			'1',
			array(
				'expires'  => time() + ( 3 * DAY_IN_SECONDS ),
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => false,
				'samesite' => 'Lax',
			)
		);

		wp_send_json_success( array( 'surface' => $surface ) );
	}
}
