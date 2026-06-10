<?php
/**
 * User Dashboard — Favorites tab content.
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/user-dashboard/tab-favorites.php
 *
 * @package WBListora
 *
 * @var int   $user_id      Current user ID.
 * @var array $favorite_ids Listing IDs the user has favorited.
 * @var array $view_data    Full view data array.
 */

defined( 'ABSPATH' ) || exit;

$view_data = $view_data ?? get_defined_vars();

/** Hook: Fires before the dashboard Favorites panel renders. @since 1.2.0 */
do_action( 'wb_listora_before_dashboard_favorites', $view_data );
?>
<div role="tabpanel" id="dash-panel-favorites" aria-labelledby="dash-tab-favorites" class="listora-dashboard__panel" hidden>

	<?php if ( empty( $favorite_ids ) ) : ?>
	<div class="listora-dashboard__empty">
		<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
		<h3><?php esc_html_e( 'No saved listings', 'wb-listora' ); ?></h3>
		<p><?php esc_html_e( 'Save listings you like by clicking the heart icon.', 'wb-listora' ); ?></p>
		<a href="<?php echo esc_url( wb_listora_get_directory_url() ); ?>" class="listora-btn listora-btn--primary">
			<?php esc_html_e( 'Browse the directory', 'wb-listora' ); ?>
		</a>
	</div>
	<?php else : ?>
	<div class="listora-dashboard__favorites-grid listora-grid" style="--listora-grid-columns: 2;">
		<?php
		foreach ( $favorite_ids as $fav_index => $fav_id ) :
			$fav_data = wb_listora_prepare_card_data( (int) $fav_id );
			if ( ! $fav_data ) {
				continue;
			}
			$attributes = array(
				'listingId'     => (int) $fav_id,
				'layout'        => 'standard',
				'showRating'    => true,
				'showFavorite'  => true,
				'showType'      => true,
				'showFeatures'  => false,
				'maxMetaFields' => 3,
				'_listing_data' => $fav_data,
				'_card_index'   => $fav_index,
			);
			include WB_LISTORA_PLUGIN_DIR . 'blocks/listing-card/render.php';
		endforeach;
		?>
	</div>
	<?php endif; ?>
</div>
<?php
/** Hook: Fires after the dashboard Favorites panel renders. @since 1.2.0 */
do_action( 'wb_listora_after_dashboard_favorites', $view_data );
