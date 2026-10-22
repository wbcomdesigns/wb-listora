<?php
/**
 * Settings > Features tab.
 *
 * @package WBListora\Admin
 */

namespace WBListora\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Settings > Features: the feature toggles form and its admin-post save.
 */
class Features_Settings_Tab {

	/**
	 * Render the Features tab content.
	 *
	 * Renders a clean checkbox list of feature toggles grouped by category.
	 * Uses an admin-post handler instead of the WP Settings API because the
	 * features option (`wb_listora_features`) lives outside the
	 * `wb_listora_settings` array.
	 */
	public static function render(): void {
		$registry = function_exists( 'wb_listora_features_registry' ) ? wb_listora_features_registry() : array();

		// Category label map (Free + filter-appended Pro / 3rd-party labels).
		// The render order follows this array: Free categories first, then any
		// category that only appears in the registry (Pro) after.
		$category_labels = function_exists( 'wb_listora_features_category_labels' )
			? wb_listora_features_category_labels()
			: array(
				'core' => __( 'Core Features', 'wb-listora' ),
				'seo'  => __( 'SEO & Meta', 'wb-listora' ),
			);

		// Free's own (unfiltered) headings. Free and external plugins can share
		// a category slug (both use `core`); the filtered map can only hold one
		// label per slug, so Free-scope groups always use these base labels and
		// only external/Pro-scope groups read the filtered map. This keeps the
		// public filter contract intact (Pro names ITS categories) without an
		// external plugin overwriting Free's own group headings.
		$free_category_labels = array(
			'core' => __( 'Core Features', 'wb-listora' ),
			'seo'  => __( 'SEO & Meta', 'wb-listora' ),
		);

		// Helper: is a `store` value a Free-side store?
		$is_free_store = static function ( $store ) {
			return 'free' === $store || 'wb_listora_features' === $store;
		};

		// Group by a composite of scope (free|pro) + category so a category
		// slug shared between Free and an external plugin (e.g. both use
		// `core`) does NOT merge into one heading. Free groups render first,
		// external (Pro) groups after — visually splitting the screen.
		$groups = array();
		foreach ( $registry as $key => $cfg ) {
			$cat   = isset( $cfg['category'] ) ? (string) $cfg['category'] : 'core';
			$store = isset( $cfg['store'] ) ? (string) $cfg['store'] : 'free';
			$scope = $is_free_store( $store ) ? 'free' : 'pro';

			$group_id = $scope . ':' . $cat;
			if ( ! isset( $groups[ $group_id ] ) ) {
				$groups[ $group_id ] = array(
					'scope'    => $scope,
					'category' => $cat,
					'rows'     => array(),
				);
			}
			$groups[ $group_id ]['rows'][ $key ] = $cfg;
		}

		// Render order: all Free groups (in category-label order, then any
		// unlabelled), then all external/Pro groups (same ordering).
		$labelled_order = array_keys( $category_labels );
		$ordered_ids    = array();
		foreach ( array( 'free', 'pro' ) as $scope ) {
			// Labelled categories first, in label-map order.
			foreach ( $labelled_order as $cat_key ) {
				$gid = $scope . ':' . $cat_key;
				if ( isset( $groups[ $gid ] ) ) {
					$ordered_ids[] = $gid;
				}
			}
			// Then any category present but absent from the label map.
			foreach ( $groups as $gid => $group ) {
				if ( $scope === $group['scope'] && ! in_array( $gid, $ordered_ids, true ) ) {
					$ordered_ids[] = $gid;
				}
			}
		}

		// The Free option stores all Free-side toggle states; non-free `store`
		// values (e.g. `wb_listora_pro_features`) are read directly so the
		// single screen shows the CURRENT value for every toggle regardless of
		// which plugin owns it. Cache each option read once per render.
		$free_features = function_exists( 'wb_listora_get_features' ) ? wb_listora_get_features() : array();
		$store_cache   = array();

		/**
		 * Resolve the current enabled state for a feature row from its `store`.
		 *
		 * @param string               $key Feature key.
		 * @param array<string, mixed> $cfg Feature config.
		 * @return bool
		 */
		$resolve_enabled = static function ( $key, $cfg ) use ( $free_features, &$store_cache ) {
			$store = isset( $cfg['store'] ) ? (string) $cfg['store'] : 'free';
			if ( 'free' === $store || 'wb_listora_features' === $store ) {
				return ! empty( $free_features[ $key ] );
			}
			if ( ! array_key_exists( $store, $store_cache ) ) {
				$opt                   = get_option( $store, array() );
				$store_cache[ $store ] = is_array( $opt ) ? $opt : array();
			}
			// A key present in the stored option is authoritative; a key the
			// owning plugin has not yet persisted falls back to the registry
			// `default` so a newly-shipped external feature shows its intended
			// state (mirrors the owning plugin's own default back-fill).
			if ( array_key_exists( $key, $store_cache[ $store ] ) ) {
				return ! empty( $store_cache[ $store ][ $key ] );
			}
			return ! empty( $cfg['default'] );
		};

		// Build the action URL for admin-post submission.
		$action_url = admin_url( 'admin-post.php' );

		// Notice on save.
		if ( isset( $_GET['features-updated'] ) && '1' === $_GET['features-updated'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag, no state change.
			echo '<div class="notice listora-notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'wb-listora' ) . '</p></div>';
		}
		?>
		<form method="post" action="<?php echo esc_url( $action_url ); ?>" class="listora-features-form">
			<input type="hidden" name="action" value="wb_listora_save_features" />
			<?php wp_nonce_field( 'wb_listora_save_features', '_wb_listora_features_nonce' ); ?>
			<div class="listora-settings-pane">
				<?php
				foreach ( $ordered_ids as $group_id ) :
					if ( empty( $groups[ $group_id ]['rows'] ) ) {
						continue;
					}

					$group        = $groups[ $group_id ];
					$cat_key      = $group['category'];
					$is_pro_group = ( 'pro' === $group['scope'] );

					// Free groups use Free's own headings; external/Pro groups
					// use the filtered label map so they can name their groups.
					if ( $is_pro_group ) {
						$cat_label = isset( $category_labels[ $cat_key ] ) ? $category_labels[ $cat_key ] : $cat_key;
					} else {
						$cat_label = isset( $free_category_labels[ $cat_key ] ) ? $free_category_labels[ $cat_key ] : $cat_key;
					}

					$block_classes = 'listora-settings-block';
					if ( $is_pro_group ) {
						$block_classes .= ' listora-settings-block--pro';
					}
					?>
					<section class="<?php echo esc_attr( $block_classes ); ?>">
						<div class="listora-settings-block__head">
							<h3 class="listora-settings-block__title">
								<?php echo esc_html( $cat_label ); ?>
								<?php if ( $is_pro_group ) : ?>
									<span class="listora-badge listora-badge--featured listora-settings-block__pro-tag"><?php esc_html_e( 'Pro', 'wb-listora' ); ?></span>
								<?php endif; ?>
							</h3>
						</div>

						<div class="listora-features-list" role="list">
							<?php
							foreach ( $group['rows'] as $key => $cfg ) :
								$is_on   = $resolve_enabled( $key, $cfg );
								$desc_id = 'wb-listora-feat-desc-' . $key;
								?>
								<div class="listora-feature-row" role="listitem">
									<div class="listora-feature-row__main">
										<?php if ( ! empty( $cfg['icon'] ) ) : ?>
											<span class="listora-feature-row__icon" aria-hidden="true"><i data-lucide="<?php echo esc_attr( $cfg['icon'] ); ?>"></i></span>
										<?php endif; ?>
										<div class="listora-feature-row__text">
											<label class="listora-feature-row__label" for="wb-listora-feat-<?php echo esc_attr( $key ); ?>">
												<?php echo esc_html( $cfg['label'] ); ?>
											</label>
											<p id="<?php echo esc_attr( $desc_id ); ?>" class="listora-feature-row__desc">
												<?php echo esc_html( $cfg['desc'] ?? '' ); ?>
											</p>
										</div>
									</div>
									<div class="listora-feature-row__control">
										<label class="listora-toggle">
											<input type="hidden" name="features[<?php echo esc_attr( $key ); ?>]" value="0" />
											<input
												type="checkbox"
												id="wb-listora-feat-<?php echo esc_attr( $key ); ?>"
												name="features[<?php echo esc_attr( $key ); ?>]"
												value="1"
												<?php checked( $is_on ); ?>
												aria-describedby="<?php echo esc_attr( $desc_id ); ?>"
											/>
											<span class="listora-toggle__track" aria-hidden="true">
												<span class="listora-toggle__thumb"></span>
											</span>
											<span class="listora-toggle__status">
												<span class="listora-toggle__on"><?php esc_html_e( 'Enabled', 'wb-listora' ); ?></span>
												<span class="listora-toggle__off"><?php esc_html_e( 'Disabled', 'wb-listora' ); ?></span>
											</span>
										</label>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endforeach; ?>

			</div>
			<div class="listora-settings-section__footer">
				<?php Settings_Page::render_unsaved_note(); ?>
				<button type="submit" class="listora-btn wp-element-button listora-btn--primary">
					<i data-lucide="save"></i> <?php esc_html_e( 'Save Changes', 'wb-listora' ); ?>
				</button>
			</div>
		</form>

		<?php
		// Features tab toggle styles live in assets/css/admin/settings.css
		// (no inline CSS allowed in admin PHP).
	}

	/**
	 * Handle Features tab form submission.
	 *
	 * Hooked to admin-post action `wb_listora_save_features`. Writes the
	 * `wb_listora_features` option (the single source of truth) and redirects
	 * to the Features tab.
	 */
	public static function save(): void {
		if ( ! current_user_can( 'manage_listora_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'wb-listora' ) );
		}
		check_admin_referer( 'wb_listora_save_features', '_wb_listora_features_nonce' );

		$registry = function_exists( 'wb_listora_features_registry' ) ? wb_listora_features_registry() : array();
		// Each feature toggle is a boolean — checkbox present (any non-empty
		// value) means ON, absent means OFF. The raw POST values are never
		// read as strings; `array_map('boolval', …)` casts every entry on
		// the very next line, so the contract is "presence implies true"
		// regardless of payload contents. Nonce verified above via
		// check_admin_referer().
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Values cast to bool on the next line; raw payload contents are never read as strings.
		$raw_input = isset( $_POST['features'] ) && is_array( $_POST['features'] ) ? wp_unslash( $_POST['features'] ) : array();
		$input     = array_map( 'boolval', $raw_input );

		// Persist ONLY the Free-side toggles (rows whose `store` is the Free
		// option) to `wb_listora_features`. Pro / 3rd-party rows carry a
		// different `store` and are persisted by their own listener on the
		// `wb_listora_save_features_extra` action below — Free never writes
		// another plugin's option directly (INV-3 boundary).
		$out = array();
		foreach ( $registry as $key => $cfg ) {
			$store = isset( $cfg['store'] ) ? (string) $cfg['store'] : 'free';
			if ( 'free' !== $store && 'wb_listora_features' !== $store ) {
				continue;
			}
			$out[ $key ] = ! empty( $input[ $key ] );
		}

		update_option( 'wb_listora_features', $out );

		/**
		 * Fires after the Free feature toggles are persisted, so external
		 * plugins can save their own toggles (registered via the
		 * `wb_listora_features_registry` filter) to their own option.
		 *
		 * Pro listens here and writes `wb_listora_pro_features` via
		 * `update_option()`, which keeps the
		 * `update_option_wb_listora_pro_features` side-effect (Action Scheduler
		 * orphan-job cancellation) firing on save.
		 *
		 * @since 1.1.0
		 * @param array<string, bool> $posted_features Raw posted toggle map
		 *                                             (feature_key => bool) for
		 *                                             every row on the screen,
		 *                                             Free and external alike.
		 */
		do_action( 'wb_listora_save_features_extra', $input );

		// `tab=features` keeps the SSR branch active. The fragment must be the
		// tab key (`#features`), which settings-nav.js looks up as
		// `[data-section="features"]`; the section DOM id (`#section-features`)
		// would miss and fall back to the General tab.
		$redirect = add_query_arg(
			array(
				'page'             => 'listora-settings',
				'tab'              => 'features',
				'features-updated' => '1',
			),
			admin_url( 'admin.php' )
		) . '#features';
		wp_safe_redirect( $redirect );
		exit;
	}
}
