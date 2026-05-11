<?php
/**
 * Frontend render helpers for canonical primitive markup.
 *
 * These functions emit canonical primitive HTML so blocks don't have to
 * remember the exact ARIA wiring or class names. Every block that needs
 * an empty state or a tabs widget calls one of these helpers instead of
 * inlining the markup.
 *
 * @package WBListora
 *
 * @since 1.0.5
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wb_listora_render_empty_state' ) ) {
	/**
	 * Render a canonical empty-state card.
	 *
	 * Replaces 5+ different empty-state implementations across the plugin.
	 * Used by blocks when their result count = 0 (search results, dashboard
	 * tabs, claims list, reviews list, etc.).
	 *
	 * @param array{
	 *     icon?:        string,   // Lucide icon name (e.g. 'inbox', 'search', 'star')
	 *     title?:       string,   // Heading text
	 *     description?: string,   // Supporting paragraph
	 *     cta?: array{             // Optional primary action button
	 *         label: string,
	 *         url:   string,
	 *         class?: string,      // Extra btn variant classes (default: --primary)
	 *     },
	 *     secondary_cta?: array{   // Optional secondary action
	 *         label: string,
	 *         url:   string,
	 *     },
	 *     class?:       string,   // Extra wrapper classes
	 * } $args Empty-state configuration.
	 *
	 * @return void
	 */
	function wb_listora_render_empty_state( array $args ): void {
		$icon          = (string) ( $args['icon'] ?? '' );
		$title         = (string) ( $args['title'] ?? __( 'No results found', 'wb-listora' ) );
		$description   = (string) ( $args['description'] ?? '' );
		$cta           = isset( $args['cta'] ) && is_array( $args['cta'] ) ? $args['cta'] : null;
		$secondary_cta = isset( $args['secondary_cta'] ) && is_array( $args['secondary_cta'] ) ? $args['secondary_cta'] : null;
		$extra_class   = (string) ( $args['class'] ?? '' );

		$wrap_class = trim( 'listora-card listora-card--empty ' . $extra_class );
		?>
		<div class="<?php echo esc_attr( $wrap_class ); ?>">
			<div class="listora-empty">
				<?php if ( '' !== $icon && class_exists( '\WBListora\Core\Lucide_Icons' ) ) : ?>
					<div class="listora-empty__icon" aria-hidden="true">
						<?php
						// Lucide_Icons::render returns inline SVG; safe to echo.
						echo \WBListora\Core\Lucide_Icons::render( $icon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
					</div>
				<?php endif; ?>

				<h3 class="listora-empty__title"><?php echo esc_html( $title ); ?></h3>

				<?php if ( '' !== $description ) : ?>
					<p class="listora-empty__desc"><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>

				<?php if ( null !== $cta || null !== $secondary_cta ) : ?>
					<div class="listora-empty__actions">
						<?php if ( null !== $cta ) :
							$cta_class = trim( 'listora-btn listora-btn--primary ' . (string) ( $cta['class'] ?? '' ) );
							?>
							<a class="<?php echo esc_attr( $cta_class ); ?>"
							   href="<?php echo esc_url( (string) ( $cta['url'] ?? '' ) ); ?>">
								<?php echo esc_html( (string) ( $cta['label'] ?? '' ) ); ?>
							</a>
						<?php endif; ?>

						<?php if ( null !== $secondary_cta ) : ?>
							<a class="listora-btn listora-btn--ghost"
							   href="<?php echo esc_url( (string) ( $secondary_cta['url'] ?? '' ) ); ?>">
								<?php echo esc_html( (string) ( $secondary_cta['label'] ?? '' ) ); ?>
							</a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}

if ( ! function_exists( 'wb_listora_render_tabs' ) ) {
	/**
	 * Render a canonical .listora-tabs widget with full ARIA wiring.
	 *
	 * Emits the correct role + aria attributes so blocks don't have to
	 * remember the WAI-ARIA Tab Pattern wiring. Each call produces a tabs
	 * widget with one or more tabs and their corresponding panels.
	 *
	 * @param array{
	 *     tabs:  array<string, array{
	 *         label:    string,
	 *         count?:   int,         // Optional count badge
	 *         disabled?: bool,
	 *     }>,
	 *     active:        string,     // Key of the initially-active tab
	 *     panels?:       array<string, string>,   // Pre-rendered panel HTML, keyed by tab key
	 *     variant?:      string,     // '' (default) | 'pill' | 'vertical'
	 *     id_prefix?:    string,     // For unique IDs when multiple tab widgets on a page
	 *     class?:        string,     // Extra wrapper classes
	 *     orientation?:  string,     // 'horizontal' (default) | 'vertical'
	 * } $args Tabs configuration.
	 *
	 * @return void
	 */
	function wb_listora_render_tabs( array $args ): void {
		$tabs        = (array) ( $args['tabs'] ?? array() );
		$active      = (string) ( $args['active'] ?? '' );
		$panels      = (array) ( $args['panels'] ?? array() );
		$variant     = (string) ( $args['variant'] ?? '' );
		$id_prefix   = (string) ( $args['id_prefix'] ?? wp_unique_id( 'listora-tabs-' ) );
		$extra_class = (string) ( $args['class'] ?? '' );
		$orientation = (string) ( $args['orientation'] ?? 'horizontal' );

		if ( empty( $tabs ) ) {
			return;
		}

		// Default active to the first tab key when not specified or invalid.
		if ( '' === $active || ! isset( $tabs[ $active ] ) ) {
			$active = (string) array_key_first( $tabs );
		}

		$wrap_class = trim(
			'listora-tabs'
			. ( '' !== $variant ? ' listora-tabs--' . $variant : '' )
			. ' ' . $extra_class
		);
		?>
		<div class="<?php echo esc_attr( $wrap_class ); ?>">
			<div class="listora-tabs__list" role="tablist" aria-orientation="<?php echo esc_attr( $orientation ); ?>">
				<?php foreach ( $tabs as $key => $tab ) :
					$is_active   = ( $key === $active );
					$is_disabled = ! empty( $tab['disabled'] );
					$tab_id      = $id_prefix . '-tab-' . sanitize_html_class( $key );
					$panel_id    = $id_prefix . '-panel-' . sanitize_html_class( $key );
					?>
					<button class="listora-tabs__tab"
					        type="button"
					        role="tab"
					        id="<?php echo esc_attr( $tab_id ); ?>"
					        aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
					        aria-controls="<?php echo esc_attr( $panel_id ); ?>"
					        tabindex="<?php echo $is_active ? '0' : '-1'; ?>"
					        <?php disabled( $is_disabled ); ?>
					        data-tab-key="<?php echo esc_attr( $key ); ?>">
						<span class="listora-tabs__tab-label"><?php echo esc_html( (string) ( $tab['label'] ?? $key ) ); ?></span>
						<?php if ( isset( $tab['count'] ) && (int) $tab['count'] > 0 ) : ?>
							<span class="listora-tabs__count"><?php echo esc_html( (string) (int) $tab['count'] ); ?></span>
						<?php endif; ?>
					</button>
				<?php endforeach; ?>
			</div>

			<?php if ( ! empty( $panels ) ) : ?>
				<div class="listora-tabs__panels">
					<?php foreach ( $tabs as $key => $tab ) :
						$is_active = ( $key === $active );
						$tab_id    = $id_prefix . '-tab-' . sanitize_html_class( $key );
						$panel_id  = $id_prefix . '-panel-' . sanitize_html_class( $key );
						$panel_html = isset( $panels[ $key ] ) ? (string) $panels[ $key ] : '';
						?>
						<div class="listora-tabs__panel"
						     role="tabpanel"
						     id="<?php echo esc_attr( $panel_id ); ?>"
						     aria-labelledby="<?php echo esc_attr( $tab_id ); ?>"
						     <?php echo $is_active ? '' : 'hidden'; ?>>
							<?php
							// Caller is responsible for escaping inside panel HTML.
							echo $panel_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'wb_listora_render_admin_header' ) ) {
	/**
	 * Render a branded admin header (Pass 3 F4 — Jetonomy Pattern A).
	 *
	 * Used by every WB Listora admin page to provide consistent branding.
	 *
	 * @param array{
	 *     title:    string,            // Page title (required)
	 *     subtitle?: string,           // Small uppercase label above/below title
	 *     icon?:    string,            // dashicons-foo OR Lucide name; defaults to 'dashicons-id-alt'
	 *     actions?: string,            // Pre-rendered HTML for action buttons (use esc_*).
	 * } $args Header configuration.
	 *
	 * @return void
	 */
	function wb_listora_render_admin_header( array $args ): void {
		$title    = (string) ( $args['title'] ?? '' );
		$subtitle = (string) ( $args['subtitle'] ?? '' );
		$icon     = (string) ( $args['icon'] ?? 'dashicons-id-alt' );
		$actions  = (string) ( $args['actions'] ?? '' );

		if ( '' === $title ) {
			return;
		}
		?>
		<div class="listora-admin-header">
			<div class="listora-admin-header__brand">
				<span class="dashicons <?php echo esc_attr( $icon ); ?> listora-admin-header__icon" aria-hidden="true"></span>
				<div class="listora-admin-header__text">
					<p class="listora-admin-header__title"><?php echo esc_html( $title ); ?></p>
					<?php if ( '' !== $subtitle ) : ?>
						<p class="listora-admin-header__sub"><?php echo esc_html( $subtitle ); ?></p>
					<?php endif; ?>
				</div>
			</div>
			<?php if ( '' !== $actions ) : ?>
				<div class="listora-admin-header__actions">
					<?php echo $actions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller responsibility ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'wb_listora_render_settings_sidebar' ) ) {
	/**
	 * Render the canonical settings sidebar (Pass 3 F5 — Jetonomy Pattern A).
	 *
	 * Emits the sidebar half of the `.listora-settings-layout` flex. Caller
	 * is responsible for opening / closing the layout and main panel.
	 *
	 * @param array{
	 *     brand_name:  string,         // Brand name (e.g. "WB Listora")
	 *     brand_sub?:  string,         // Small uppercase label (e.g. "Settings")
	 *     brand_icon?: string,         // dashicons-foo
	 *     active_tab:  string,         // Currently active tab slug
	 *     tabs:        array<string, array{ label: string, icon?: string, url: string }>,
	 *     advanced_html?: string,      // Pre-rendered HTML for Pro/extension tabs
	 *     advanced_label?: string,     // Section label above advanced (default: "Advanced")
	 * } $args Sidebar configuration.
	 *
	 * @return void
	 */
	function wb_listora_render_settings_sidebar( array $args ): void {
		$brand_name     = (string) ( $args['brand_name'] ?? 'WB Listora' );
		$brand_sub      = (string) ( $args['brand_sub'] ?? __( 'Settings', 'wb-listora' ) );
		$brand_icon     = (string) ( $args['brand_icon'] ?? 'dashicons-admin-settings' );
		$active_tab     = (string) ( $args['active_tab'] ?? '' );
		$tabs           = (array) ( $args['tabs'] ?? array() );
		$advanced_html  = (string) ( $args['advanced_html'] ?? '' );
		$advanced_label = (string) ( $args['advanced_label'] ?? __( 'Advanced', 'wb-listora' ) );
		?>
		<aside class="listora-settings-sidebar">
			<div class="listora-settings-sidebar__brand">
				<span class="dashicons <?php echo esc_attr( $brand_icon ); ?> listora-settings-sidebar__brand-icon" aria-hidden="true"></span>
				<div class="listora-settings-sidebar__brand-text">
					<p class="listora-settings-sidebar__brand-name"><?php echo esc_html( $brand_name ); ?></p>
					<?php if ( '' !== $brand_sub ) : ?>
						<p class="listora-settings-sidebar__brand-sub"><?php echo esc_html( $brand_sub ); ?></p>
					<?php endif; ?>
				</div>
			</div>
			<nav class="listora-settings-sidebar__nav" aria-label="<?php esc_attr_e( 'Settings navigation', 'wb-listora' ); ?>">
				<?php foreach ( $tabs as $slug => $tab ) : ?>
					<?php
					$tab_label = isset( $tab['label'] ) ? (string) $tab['label'] : (string) $slug;
					$tab_icon  = isset( $tab['icon'] ) ? (string) $tab['icon'] : '';
					$tab_url   = isset( $tab['url'] ) ? (string) $tab['url'] : '#';
					$is_active = ( $active_tab === $slug );
					?>
					<a
						href="<?php echo esc_url( $tab_url ); ?>"
						class="listora-snav-link<?php echo $is_active ? ' listora-snav-link--active' : ''; ?>"
					>
						<?php if ( '' !== $tab_icon ) : ?>
							<span class="dashicons <?php echo esc_attr( $tab_icon ); ?>" aria-hidden="true"></span>
						<?php endif; ?>
						<?php echo esc_html( $tab_label ); ?>
					</a>
				<?php endforeach; ?>

				<?php if ( '' !== $advanced_html ) : ?>
					<div class="listora-snav-divider" role="separator"></div>
					<p class="listora-snav-section-label"><?php echo esc_html( $advanced_label ); ?></p>
					<?php echo $advanced_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller responsibility ?>
				<?php endif; ?>
			</nav>
		</aside>
		<?php
	}
}

if ( ! function_exists( 'wb_listora_render_settings_card' ) ) {
	/**
	 * Render the opening + heading of a settings card (Pass 3 F6 — Jetonomy Pattern A).
	 *
	 * Caller emits the form-table or content body and then closes with
	 * `</div>` (one closing tag — the helper opens `<div class="listora-settings-card">`
	 * + the head block, but does not auto-close so caller controls the body).
	 *
	 * @param array{
	 *     title:        string,
	 *     description?: string,
	 *     class?:       string,        // Extra modifier classes (e.g. '--auto')
	 * } $args Card configuration.
	 *
	 * @return void
	 */
	function wb_listora_render_settings_card_open( array $args ): void {
		$title       = (string) ( $args['title'] ?? '' );
		$description = (string) ( $args['description'] ?? '' );
		$class       = (string) ( $args['class'] ?? '' );

		$classes = trim( 'listora-settings-card ' . $class );
		?>
		<div class="<?php echo esc_attr( $classes ); ?>">
			<?php if ( '' !== $title || '' !== $description ) : ?>
				<div class="listora-settings-card__head">
					<?php if ( '' !== $title ) : ?>
						<p class="listora-settings-card__title"><?php echo esc_html( $title ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== $description ) : ?>
						<p class="listora-settings-card__desc"><?php echo esc_html( $description ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		<?php
	}
}

if ( ! function_exists( 'wb_listora_render_settings_card_close' ) ) {
	/**
	 * Close a settings card opened by `wb_listora_render_settings_card_open()`.
	 *
	 * @return void
	 */
	function wb_listora_render_settings_card_close(): void {
		?>
		</div>
		<?php
	}
}
