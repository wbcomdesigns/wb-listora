<?php
/**
 * Listing Grid — Pagination template.
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/listing-grid/pagination.php
 *
 * @package WBListora
 *
 * @var bool   $show_pagination Whether to show pagination controls.
 * @var int    $pages           Total number of pages.
 * @var int    $current_page    Current page number.
 * @var string $base_url        Base URL for building page links.
 * @var array  $view_data       Full view data array (all variables).
 */

defined( 'ABSPATH' ) || exit;

// Defensive defaults — template may be rendered with partial context.
$show_pagination = isset( $show_pagination ) ? (bool) $show_pagination : true;
$pages           = isset( $pages ) ? (int) $pages : 0;
$current_page    = isset( $current_page ) ? (int) $current_page : 1;
$base_url        = isset( $base_url ) ? (string) $base_url : '';

if ( ! $show_pagination || $pages <= 1 ) {
	return;
}

$prev_url = $current_page > 1 ? add_query_arg( 'listora_page', $current_page - 1, $base_url ) : '';
?>
<nav class="listora-grid__pagination" aria-label="<?php esc_attr_e( 'Pagination', 'wb-listora' ); ?>" data-wp-class--is-hidden="!state.showPagination">
	<?php
	/*
	 * A link when it goes somewhere, a disabled BUTTON when it does not.
	 *
	 * This was always an <a>, with the href simply omitted on the first/last
	 * page. An anchor without href has no role at all, so the aria-label and
	 * aria-disabled it kept were prohibited ARIA on a generic element - the
	 * control announced as nothing while still claiming a state
	 * (BC 10208338418).
	 *
	 * The enabled state stays an anchor so the page remains crawlable and
	 * works without JS. The disabled state becomes a native disabled button,
	 * which conveys "unavailable" without ARIA and is legal to label.
	 */
	?>
	<?php if ( $prev_url ) : ?>
	<a
		href="<?php echo esc_url( $prev_url ); ?>"
		class="listora-btn listora-btn--icon listora-grid__page-btn"
		data-wp-on--click="actions.prevPage"
		aria-label="<?php esc_attr_e( 'Previous page', 'wb-listora' ); ?>"
	>
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
			<path d="m15 18-6-6 6-6"></path>
		</svg>
	</a>
	<?php else : ?>
	<button
		type="button"
		disabled
		class="listora-btn listora-btn--icon listora-grid__page-btn"
		aria-label="<?php esc_attr_e( 'Previous page', 'wb-listora' ); ?>"
	>
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
			<path d="m15 18-6-6 6-6"></path>
		</svg>
	</button>
	<?php endif; ?>

	<div class="listora-grid__page-numbers">
		<?php
		// Render page number links (max 7 visible).
		$max_visible = 7;
		$start       = max( 1, min( (int) ceil( $max_visible / 2 ), $pages - $max_visible + 1 ) );
		$end         = min( $pages, $start + $max_visible - 1 );

		if ( $start > 1 ) :
			$page_url = add_query_arg( 'listora_page', 1, $base_url );
			?>
			<a href="<?php echo esc_url( $page_url ); ?>"
				class="listora-grid__page-num<?php echo 1 === $current_page ? ' is-active' : ''; ?>"
				data-wp-on--click="actions.setPage"
				data-wp-context='<?php echo wp_json_encode( array( 'page' => 1 ) ); ?>'
				<?php
				if ( 1 === $current_page ) :
					?>
					aria-current="page"<?php endif; ?>
			>1</a>
			<?php if ( $start > 2 ) : ?>
			<span class="listora-grid__page-ellipsis">&hellip;</span>
				<?php
			endif;
		endif;

		for ( $p = $start; $p <= $end; $p++ ) :
			if ( 1 === $p && $start > 1 ) {
				continue;
			}
			$page_url = add_query_arg( 'listora_page', $p, $base_url );
			?>
			<a
				href="<?php echo esc_url( $page_url ); ?>"
				class="listora-grid__page-num<?php echo esc_attr( $p === $current_page ? ' is-active' : '' ); ?>"
				data-wp-on--click="actions.setPage"
				data-wp-context='<?php echo wp_json_encode( array( 'page' => $p ) ); ?>'
				<?php
				if ( $p === $current_page ) :
					?>
					aria-current="page"<?php endif; ?>
			><?php echo esc_html( $p ); ?></a>
			<?php
		endfor;

		if ( $end < $pages ) :
			if ( $end < $pages - 1 ) :
				?>
			<span class="listora-grid__page-ellipsis">&hellip;</span>
				<?php
			endif;
			$page_url = add_query_arg( 'listora_page', $pages, $base_url );
			?>
			<a
				href="<?php echo esc_url( $page_url ); ?>"
				class="listora-grid__page-num<?php echo esc_attr( $pages === $current_page ? ' is-active' : '' ); ?>"
				data-wp-on--click="actions.setPage"
				data-wp-context='<?php echo wp_json_encode( array( 'page' => $pages ) ); ?>'
				<?php
				if ( $pages === $current_page ) :
					?>
					aria-current="page"<?php endif; ?>
			><?php echo esc_html( $pages ); ?></a>
		<?php endif; ?>
	</div>

	<?php $next_url = $current_page < $pages ? add_query_arg( 'listora_page', $current_page + 1, $base_url ) : ''; ?>
	<?php if ( $next_url ) : ?>
	<a
		href="<?php echo esc_url( $next_url ); ?>"
		class="listora-btn listora-btn--icon listora-grid__page-btn"
		data-wp-on--click="actions.nextPage"
		aria-label="<?php esc_attr_e( 'Next page', 'wb-listora' ); ?>"
	>
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
			<path d="m9 18 6-6-6-6"></path>
		</svg>
	</a>
	<?php else : ?>
	<button
		type="button"
		disabled
		class="listora-btn listora-btn--icon listora-grid__page-btn"
		aria-label="<?php esc_attr_e( 'Next page', 'wb-listora' ); ?>"
	>
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
			<path d="m9 18 6-6-6-6"></path>
		</svg>
	</button>
	<?php endif; ?>
</nav>
