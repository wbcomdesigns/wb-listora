<?php
/**
 * Listing Detail — Gallery section.
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/listing-detail/gallery.php
 *
 * @package WBListora
 *
 * @var int    $post_id      Listing post ID.
 * @var object $post         WP_Post object.
 * @var bool   $show_gallery Whether to show the gallery.
 * @var int    $featured_id  Featured image attachment ID.
 * @var array  $gallery_ids  Gallery attachment IDs.
 * @var array  $view_data    Full view data array.
 */

defined( 'ABSPATH' ) || exit;

$view_data = $view_data ?? get_defined_vars();

if ( ! $show_gallery || ( ! $featured_id && empty( $gallery_ids ) ) ) {
	return;
}

do_action( 'wb_listora_before_detail_gallery', $view_data );
?>
<div class="listora-detail__gallery">
	<?php
	$all_images = array();
	if ( $featured_id ) {
		$all_images[] = $featured_id;
	}
	foreach ( $gallery_ids as $gid ) {
		if ( (int) $gid !== (int) $featured_id ) {
			$all_images[] = (int) $gid;
		}
	}
	?>
	<div class="listora-detail__gallery-main">
		<?php
		if ( ! empty( $all_images[0] ) ) :
			// ─── Featured-image a11y enforcement (WCAG 2.1 AA) ───
			// Prefer the attachment's own alt text (set in the Media Library),
			// fall back to the listing title, then to a deterministic
			// "Listing #ID" label so an untitled listing never produces an
			// empty alt on its hero image. Mirrors the thumbnail-row fallback
			// below and clears the visual_required_no_enforcement detector.
			$gallery_main_alt = (string) get_post_meta( $all_images[0], '_wp_attachment_image_alt', true );
			if ( '' === trim( $gallery_main_alt ) ) {
				$gallery_main_alt = '' !== trim( (string) $post->post_title )
					? $post->post_title
					/* translators: %d: listing ID, used as an alt-text fallback for an untitled listing */
					: sprintf( __( 'Listing #%d', 'wb-listora' ), (int) $post_id );
			}
			?>
		<img
			src="<?php echo esc_url( wp_get_attachment_image_url( $all_images[0], 'large' ) ); ?>"
			alt="<?php echo esc_attr( $gallery_main_alt ); ?>"
			class="listora-detail__gallery-image"
			loading="eager"
		/>
		<?php endif; ?>
	</div>
	<?php if ( count( $all_images ) > 1 ) : ?>
	<div class="listora-detail__gallery-thumbs">
		<?php
		// Deterministic title token for the thumbnail-photo alt fallback so an
		// untitled listing renders "Listing #ID photo N", never " photo N".
		$gallery_alt_title = '' !== trim( (string) $post->post_title )
			? $post->post_title
			/* translators: %d: listing ID, used as an alt-text fallback for an untitled listing */
			: sprintf( __( 'Listing #%d', 'wb-listora' ), (int) $post_id );
		?>
		<?php
		// Render every gallery image. The thumbnail strip scrolls horizontally
		// (see .listora-detail__gallery-thumbs) so a large gallery stays compact
		// while every photo remains reachable — the old array_slice(0,5) cap plus
		// a non-interactive "+N" span left images 6+ permanently hidden.
		foreach ( $all_images as $idx => $img_id ) :
			$gallery_thumb_alt = (string) get_post_meta( $img_id, '_wp_attachment_image_alt', true );
			if ( '' === trim( $gallery_thumb_alt ) ) {
				$gallery_thumb_alt = sprintf(
					/* translators: 1: listing title (or Listing #ID fallback), 2: photo number */
					__( '%1$s photo %2$d', 'wb-listora' ),
					$gallery_alt_title,
					$idx + 1
				);
			}
			?>
		<button class="listora-detail__gallery-thumb <?php echo esc_attr( 0 === $idx ? 'is-active' : '' ); ?>" type="button"
			data-wp-on--click="actions.switchGalleryImage"
			data-wp-context='{"imageId":<?php echo (int) $img_id; ?>,"imageSrc":"<?php echo esc_url( wp_get_attachment_image_url( $img_id, 'large' ) ); ?>"}'
		>
			<img src="<?php echo esc_url( wp_get_attachment_image_url( $img_id, 'thumbnail' ) ); ?>" alt="<?php echo esc_attr( $gallery_thumb_alt ); ?>" loading="lazy" />
		</button>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>
</div>
<?php
do_action( 'wb_listora_after_detail_gallery', $view_data );
