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
		<?php if ( ! empty( $all_images[0] ) ) : ?>
		<img
			src="<?php echo esc_url( wp_get_attachment_image_url( $all_images[0], 'large' ) ); ?>"
			alt="<?php echo esc_attr( $post->post_title ); ?>"
			class="listora-detail__gallery-image"
			loading="eager"
		/>
		<?php endif; ?>
	</div>
	<?php if ( count( $all_images ) > 1 ) : ?>
	<div class="listora-detail__gallery-thumbs">
		<?php foreach ( array_slice( $all_images, 0, 5 ) as $idx => $img_id ) : ?>
		<button class="listora-detail__gallery-thumb <?php echo 0 === $idx ? 'is-active' : ''; ?>" type="button"
			data-wp-on--click="actions.switchGalleryImage"
			data-wp-context='{"imageId":<?php echo (int) $img_id; ?>,"imageSrc":"<?php echo esc_url( wp_get_attachment_image_url( $img_id, 'large' ) ); ?>"}'
		>
			<img src="<?php echo esc_url( wp_get_attachment_image_url( $img_id, 'thumbnail' ) ); ?>" alt="<?php echo esc_attr( get_post_meta( $img_id, '_wp_attachment_image_alt', true ) ?: sprintf( /* translators: 1: listing title, 2: photo number */ __( '%1$s photo %2$d', 'wb-listora' ), $post->post_title, $idx + 1 ) ); ?>" loading="lazy" />
		</button>
		<?php endforeach; ?>
		<?php if ( count( $all_images ) > 5 ) : ?>
		<span class="listora-detail__gallery-more">+<?php echo esc_html( count( $all_images ) - 5 ); ?></span>
		<?php endif; ?>
	</div>
	<?php endif; ?>
</div>
<?php
do_action( 'wb_listora_after_detail_gallery', $view_data );
