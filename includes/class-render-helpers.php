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
	 * Render the shared empty-state card (used when a block has no results).
	 *
	 * @param array{
	 *     icon?:        string,   // Lucide icon name
	 *     title?:       string,   // Heading text
	 *     description?: string,   // Supporting paragraph
	 *     cta?:           array{label: string, url: string, class?: string}, // Primary button; class defaults to --primary
	 *     secondary_cta?: array{label: string, url: string},
	 *     class?:       string,   // Extra wrapper classes
	 * } $args Empty-state configuration.
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
						echo \WBListora\Core\Lucide_Icons::render( $icon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Lucide_Icons::render returns a fixed SVG from a whitelist.
						?>
					</div>
				<?php endif; ?>

				<h3 class="listora-empty__title"><?php echo esc_html( $title ); ?></h3>

				<?php if ( '' !== $description ) : ?>
					<p class="listora-empty__desc"><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>

				<?php if ( null !== $cta || null !== $secondary_cta ) : ?>
					<div class="listora-empty__actions">
						<?php
						if ( null !== $cta ) :
							$cta_class = trim( 'listora-btn listora-btn--primary ' . (string) ( $cta['class'] ?? '' ) );
							?>
							<a class="<?php echo esc_attr( $cta_class ); ?>"
								href="<?php echo esc_url( (string) ( $cta['url'] ?? '' ) ); ?>">
								<?php echo esc_html( (string) ( $cta['label'] ?? '' ) ); ?>
							</a>
						<?php endif; ?>

						<?php if ( null !== $secondary_cta ) : ?>
							<a class="listora-btn wp-element-button listora-btn--ghost"
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
	 * Render a .listora-tabs widget with full WAI-ARIA tab wiring.
	 *
	 * @param array{
	 *     tabs:  array<string, array{label: string, count?: int, disabled?: bool}>, // count = badge
	 *     active:        string,     // Key of the initially-active tab
	 *     panels?:       array<string, string>,   // Pre-rendered panel HTML, keyed by tab key
	 *     variant?:      string,     // '' (default) | 'pill' | 'vertical'
	 *     id_prefix?:    string,     // For unique IDs when multiple tab widgets on a page
	 *     class?:        string,     // Extra wrapper classes
	 *     orientation?:  string,     // 'horizontal' (default) | 'vertical'
	 * } $args Tabs configuration.
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
				<?php
				foreach ( $tabs as $key => $tab ) :
					$is_active   = ( $key === $active );
					$is_disabled = ! empty( $tab['disabled'] );
					$tab_id      = $id_prefix . '-tab-' . sanitize_html_class( $key );
					$panel_id    = $id_prefix . '-panel-' . sanitize_html_class( $key );
					?>
					<button class="listora-tabs__tab wp-element-button"
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
					<?php
					foreach ( $tabs as $key => $tab ) :
						$is_active  = ( $key === $active );
						$tab_id     = $id_prefix . '-tab-' . sanitize_html_class( $key );
						$panel_id   = $id_prefix . '-panel-' . sanitize_html_class( $key );
						$panel_html = isset( $panels[ $key ] ) ? (string) $panels[ $key ] : '';
						?>
						<div class="listora-tabs__panel"
							role="tabpanel"
							id="<?php echo esc_attr( $panel_id ); ?>"
							aria-labelledby="<?php echo esc_attr( $tab_id ); ?>"
							<?php echo $is_active ? '' : 'hidden'; ?>>
							<?php
							// Caller is responsible for escaping inside panel HTML.
							echo $panel_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- panel HTML is built and escaped by the caller.
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
	 * Print the page header every Listora admin screen opens with.
	 *
	 * One shared `.listora-page-header` block, so Free and Pro screens cannot
	 * drift. The Lucide icon is decorative (`listora-icon--sm`, `aria-hidden`).
	 * Callers keep printing their own `<hr class="wp-header-end">`.
	 *
	 * @param array{title: string, icon?: string, description?: string, actions?: callable} $args
	 *     `title` (required, nothing prints when empty), `icon` (Lucide name),
	 *     `description` (optional line under the title), `actions` (optional
	 *     callback that echoes already-escaped action buttons).
	 * @return void
	 */
	function wb_listora_render_admin_header( array $args ): void {
		$title       = (string) ( $args['title'] ?? '' );
		$icon        = (string) ( $args['icon'] ?? '' );
		$description = (string) ( $args['description'] ?? '' );
		$actions     = $args['actions'] ?? null;

		if ( '' === $title ) {
			return;
		}
		?>
		<div class="listora-page-header">
			<div class="listora-page-header__left">
				<h1 class="listora-page-header__title"><i data-lucide="<?php echo esc_attr( $icon ); ?>" class="listora-icon--sm" aria-hidden="true"></i> <?php echo esc_html( $title ); ?></h1>
				<?php if ( '' !== $description ) : ?>
					<p class="listora-page-header__desc"><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( is_callable( $actions ) ) : ?>
				<div class="listora-page-header__actions">
					<?php $actions(); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'wb_listora_render_settings_card_open' ) ) {
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

if ( ! function_exists( 'wb_listora_render_pagination' ) ) {
	/**
	 * Render the shared server-rendered pager (works without JS). Dashboard tabs pass `tab`, other screens `base_url`.
	 *
	 * @param array{
	 *     tab?:       string,  // Dashboard tab slug, becomes ?tab=
	 *     base_url?:  string,  // Page the links point at (default: the member dashboard)
	 *     class?:     string,  // Extra class on the <nav>
	 *     page_arg:   string,  // Query arg carrying the page
	 *     page:       int,     // Current page (1-based)
	 *     total_pages:int,     // Nothing renders below 2
	 *     label:      string,  // Accessible name for the <nav>
	 *     args?:      array<string, string>, // Extra query args every link keeps
	 *     total?:     int,     // Total items, shown beside the page count
	 * } $args Pager configuration.
	 */
	function wb_listora_render_pagination( array $args ): void {
		$tab         = (string) ( $args['tab'] ?? '' );
		$page_arg    = (string) ( $args['page_arg'] ?? '' );
		$page        = max( 1, (int) ( $args['page'] ?? 1 ) );
		$total_pages = (int) ( $args['total_pages'] ?? 0 );
		$label       = (string) ( $args['label'] ?? __( 'Pagination', 'wb-listora' ) );
		$total_items = (int) ( $args['total'] ?? 0 );

		// A single page needs no controls — and an empty tab must not sprout a
		// pager that goes nowhere.
		if ( $total_pages < 2 || '' === $page_arg ) {
			return;
		}

		$base     = isset( $args['base_url'] ) ? remove_query_arg( $page_arg, (string) $args['base_url'] ) : wb_listora_get_dashboard_url();
		$base     = $tab ? add_query_arg( 'tab', $tab, $base ) : $base;
		$base     = ! empty( $args['args'] ) && is_array( $args['args'] ) ? add_query_arg( array_map( 'rawurlencode', $args['args'] ), $base ) : $base;
		$has_prev = $page > 1;
		$has_next = $page < $total_pages;
		$prev_url = add_query_arg( $page_arg, max( 1, $page - 1 ), $base );
		$next_url = add_query_arg( $page_arg, min( $total_pages, $page + 1 ), $base );

		// Numbered links: first, last, and two either side of the current page,
		// with an ellipsis over each gap.
		$numbers = array( 1, $total_pages );
		for ( $n = max( 1, $page - 2 ); $n <= min( $total_pages, $page + 2 ); $n++ ) {
			$numbers[] = $n;
		}
		$numbers = array_unique( $numbers );
		sort( $numbers );
		?>
		<nav class="<?php echo esc_attr( trim( 'listora-pagination ' . ( isset( $args['base_url'] ) ? '' : 'listora-dashboard__pagination ' ) . ( $args['class'] ?? '' ) ) ); ?>" aria-label="<?php echo esc_attr( $label ); ?>">
			<?php if ( $has_prev ) : ?>
			<a href="<?php echo esc_url( $prev_url ); ?>" class="listora-btn listora-btn--secondary listora-btn--sm listora-pagination__prev" rel="prev">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>
				<?php esc_html_e( 'Previous', 'wb-listora' ); ?>
			</a>
			<?php else : ?>
			<span class="listora-btn listora-btn--secondary listora-btn--sm listora-pagination__prev" aria-disabled="true">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>
				<?php esc_html_e( 'Previous', 'wb-listora' ); ?>
			</span>
			<?php endif; ?>

			<span class="listora-pagination__pages">
				<?php
				$previous_number = 0;
				foreach ( $numbers as $n ) :
					if ( $previous_number && $n - $previous_number > 1 ) :
						?>
				<span class="listora-pagination__gap" aria-hidden="true">&hellip;</span>
						<?php
					endif;
					$previous_number = $n;
					if ( $n === $page ) :
						?>
				<span class="listora-btn listora-btn--primary listora-btn--sm listora-pagination__page is-current" aria-current="page"><?php echo esc_html( number_format_i18n( $n ) ); ?></span>
					<?php else : ?>
				<a href="<?php echo esc_url( add_query_arg( $page_arg, $n, $base ) ); ?>" class="listora-btn listora-btn--secondary listora-btn--sm listora-pagination__page"
					aria-label="<?php echo esc_attr( sprintf( /* translators: %s: page number */ __( 'Page %s', 'wb-listora' ), number_format_i18n( $n ) ) ); ?>"><?php echo esc_html( number_format_i18n( $n ) ); ?></a>
					<?php endif; ?>
				<?php endforeach; ?>
			</span>

			<span class="listora-pagination__status" aria-live="polite">
				<?php
				if ( $total_items > 0 ) {
					printf(
						/* translators: 1: current page number, 2: total page count, 3: total item count */
						esc_html__( 'Page %1$s of %2$s (%3$s items)', 'wb-listora' ),
						esc_html( number_format_i18n( $page ) ),
						esc_html( number_format_i18n( $total_pages ) ),
						esc_html( number_format_i18n( $total_items ) )
					);
				} else {
					printf(
						/* translators: 1: current page number, 2: total page count */
						esc_html__( 'Page %1$s of %2$s', 'wb-listora' ),
						esc_html( number_format_i18n( $page ) ),
						esc_html( number_format_i18n( $total_pages ) )
					);
				}
				?>
			</span>

			<?php if ( $has_next ) : ?>
			<a href="<?php echo esc_url( $next_url ); ?>" class="listora-btn listora-btn--secondary listora-btn--sm listora-pagination__next" rel="next">
				<?php esc_html_e( 'Next', 'wb-listora' ); ?>
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
			</a>
			<?php else : ?>
			<span class="listora-btn listora-btn--secondary listora-btn--sm listora-pagination__next" aria-disabled="true">
				<?php esc_html_e( 'Next', 'wb-listora' ); ?>
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
			</span>
			<?php endif; ?>
		</nav>
		<?php
	}
}
