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
