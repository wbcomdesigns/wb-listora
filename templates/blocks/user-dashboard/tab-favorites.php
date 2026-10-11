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
		<?php
		wb_listora_render_empty_state(
			array(
				'icon'        => 'heart',
				'title'       => __( 'No saved listings', 'wb-listora' ),
				'description' => __( 'Save listings you like by clicking the heart icon.', 'wb-listora' ),
				'cta'         => array(
					'label' => __( 'Browse the directory', 'wb-listora' ),
					'url'   => wb_listora_get_directory_url(),
				),
			)
		);
		?>
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
	<?php
	if ( function_exists( 'wb_listora_render_pagination' ) ) {
		wb_listora_render_pagination(
			array(
				'tab'         => 'favorites',
				'page_arg'    => 'favorites_page',
				'page'        => isset( $favorites_page ) ? (int) $favorites_page : 1,
				'total_pages' => isset( $favorites_pages ) ? (int) $favorites_pages : 0,
				'label'       => __( 'Favorites pagination', 'wb-listora' ),
			)
		);
	}
	?>
</div>
<?php
/** Hook: Fires after the dashboard Favorites panel renders. @since 1.2.0 */
do_action( 'wb_listora_after_dashboard_favorites', $view_data );
