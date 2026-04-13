<?php
/**
 * Listing Submission — Step: Photos & Media upload.
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/listing-submission/step-media.php
 *
 * @package WBListora
 *
 * @var bool   $is_edit_mode      Whether we are editing an existing listing.
 * @var int    $edit_thumbnail_id  Featured image ID in edit mode.
 * @var string $edit_gallery_ids   Comma-separated gallery IDs in edit mode.
 * @var string $edit_video         Video URL in edit mode.
 * @var array  $edit_gallery       Gallery image IDs array in edit mode.
 * @var array  $view_data          Full view data array (all variables).
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="listora-submission__step" data-step="media" hidden>
	<h2><?php esc_html_e( 'Photos & Media', 'wb-listora' ); ?></h2>

	<div class="listora-submission__field">
		<label class="listora-submission__label">
			<?php esc_html_e( 'Featured Image', 'wb-listora' ); ?>
			<?php if ( ! $is_edit_mode ) : ?>
			<span class="required">*</span>
			<?php endif; ?>
		</label>
		<?php
		$edit_thumb_url = ( $is_edit_mode && $edit_thumbnail_id ) ? wp_get_attachment_image_url( $edit_thumbnail_id, 'medium' ) : '';
		?>
		<div class="listora-submission__upload-zone" data-wp-on--click="actions.openMediaUpload" data-wp-context='{"uploadTarget":"featured_image"}'>
			<?php if ( $edit_thumb_url ) : ?>
			<img src="<?php echo esc_url( $edit_thumb_url ); ?>" alt="<?php esc_attr_e( 'Featured image preview', 'wb-listora' ); ?>" style="max-width:100%;border-radius:var(--listora-card-radius);" />
			<?php else : ?>
			<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
				<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/>
				<path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
			</svg>
			<span><?php esc_html_e( 'Click to upload or drag & drop', 'wb-listora' ); ?></span>
			<span class="listora-submission__upload-hint"><?php esc_html_e( 'Max 5MB, JPG/PNG/WebP', 'wb-listora' ); ?></span>
			<?php endif; ?>
		</div>
		<input type="hidden" name="featured_image" value="<?php echo $is_edit_mode ? esc_attr( $edit_thumbnail_id ) : ''; ?>" />
	</div>

	<div class="listora-submission__field">
		<label class="listora-submission__label">
			<?php
			printf(
				/* translators: %d: max gallery images */
				esc_html__( 'Gallery (up to %d photos)', 'wb-listora' ),
				(int) wb_listora_get_setting( 'max_gallery_images', 20 )
			);
			?>
		</label>
		<div class="listora-submission__gallery-upload">
			<div class="listora-submission__gallery-thumbs" id="listora-gallery-thumbs">
				<?php
				// Pre-render existing gallery thumbnails in edit mode.
				if ( $is_edit_mode && ! empty( $edit_gallery ) && is_array( $edit_gallery ) ) {
					foreach ( $edit_gallery as $gal_id ) {
						$gal_id  = absint( $gal_id );
						$gal_url = wp_get_attachment_image_url( $gal_id, 'thumbnail' );
						if ( $gal_url ) {
							echo '<div style="width:80px;height:80px;border-radius:var(--listora-radius-md);overflow:hidden;position:relative;">';
							echo '<img src="' . esc_url( $gal_url ) . '" alt="' . esc_attr( get_post_meta( $gal_id, '_wp_attachment_image_alt', true ) ?: __( 'Gallery image', 'wb-listora' ) ) . '" style="width:100%;height:100%;object-fit:cover;" />';
							echo '</div>';
						}
					}
				}
				?>
			</div>
			<button type="button" class="listora-btn listora-btn--secondary listora-submission__add-photos"
				data-wp-on--click="actions.openMediaUpload" data-wp-context='{"uploadTarget":"gallery"}'>
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
				<?php esc_html_e( 'Add Photos', 'wb-listora' ); ?>
			</button>
		</div>
		<input type="hidden" name="gallery" value="<?php echo $is_edit_mode ? esc_attr( $edit_gallery_ids ) : ''; ?>" />
	</div>

	<div class="listora-submission__field">
		<label for="listora-video" class="listora-submission__label"><?php esc_html_e( 'Video URL (optional)', 'wb-listora' ); ?></label>
		<input type="url" id="listora-video" name="video" class="listora-input"
			placeholder="<?php esc_attr_e( 'https://youtube.com/watch?v=...', 'wb-listora' ); ?>"
			value="<?php echo $is_edit_mode ? esc_url( $edit_video ) : ''; ?>" />
	</div>
</div>
