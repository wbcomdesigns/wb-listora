<?php
/**
 * Featured meta box on the Edit Listing screen.
 *
 * Lets site admins manually feature / unfeature a listing without leaving
 * wp-admin. Free's `Featured` service is the canonical writer for
 * `_listora_is_featured` and `_listora_featured_until`; this UI is just a
 * thin admin wrapper around `Featured::feature_listing()` / `unfeature_listing()`.
 *
 * Pro's credit-based featured rotation still works on top: when Pro is
 * active and a price is configured, the SDK's `wb_listora_before_feature_listing`
 * filter places a credit hold that aborts this manual operation if the site
 * owner's balance is insufficient. When Pro is not active (or the listing
 * has no associated plan), the manual toggle goes through.
 *
 * @package WBListora\Admin
 */

namespace WBListora\Admin;

use WBListora\Core\Featured;

defined( 'ABSPATH' ) || exit;

/**
 * Renders + saves the Featured meta box on listora_listing edit screens.
 */
class Featured_Metabox {

	const NONCE_NAME   = '_wb_listora_featured_metabox_nonce';
	const NONCE_ACTION = 'wb_listora_featured_metabox';

	/**
	 * Register WordPress hooks.
	 */
	public static function register(): void {
		add_action( 'add_meta_boxes_listora_listing', array( __CLASS__, 'register_metabox' ) );
		add_action( 'save_post_listora_listing', array( __CLASS__, 'save_post' ), 20, 1 );
	}

	/**
	 * Register the meta box.
	 */
	public static function register_metabox(): void {
		add_meta_box(
			'wb_listora_featured',
			__( 'Featured', 'wb-listora' ),
			array( __CLASS__, 'render' ),
			'listora_listing',
			'side',
			'high'
		);
	}

	/**
	 * Render the meta box body.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public static function render( $post ): void {
		$post_id     = (int) $post->ID;
		$is_featured = Featured::is_featured( $post_id );
		$until       = (int) get_post_meta( $post_id, Featured::META_FEATURED_UNTIL, true );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$default_days = (int) wb_listora_get_setting( 'featured_duration_days', 30 );
		?>
		<p class="wb-listora-featured-metabox__intro">
			<?php esc_html_e( 'Featured listings appear first in directory grids and on the home page.', 'wb-listora' ); ?>
		</p>

		<p>
			<label>
				<input
					type="checkbox"
					name="wb_listora_featured_enabled"
					value="1"
					<?php checked( $is_featured ); ?>
				/>
				<strong><?php esc_html_e( 'Feature this listing', 'wb-listora' ); ?></strong>
			</label>
		</p>

		<p>
			<label for="wb-listora-featured-days">
				<?php esc_html_e( 'Duration (days)', 'wb-listora' ); ?>
			</label>
			<input
				type="number"
				id="wb-listora-featured-days"
				name="wb_listora_featured_days"
				value="<?php echo esc_attr( (string) $default_days ); ?>"
				min="0"
				step="1"
				class="small-text"
			/>
			<span class="description"><?php esc_html_e( '0 = permanent', 'wb-listora' ); ?></span>
		</p>

		<?php if ( $is_featured && $until > 0 ) : ?>
			<p class="wb-listora-featured-metabox__until">
				<em>
					<?php
					printf(
						/* translators: %s: human-friendly expiration date. */
						esc_html__( 'Expires on %s', 'wb-listora' ),
						esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $until ) )
					);
					?>
				</em>
			</p>
		<?php elseif ( $is_featured ) : ?>
			<p class="wb-listora-featured-metabox__until">
				<em><?php esc_html_e( 'Permanent — no expiration set.', 'wb-listora' ); ?></em>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Handle save_post — apply the checkbox + duration to Featured service.
	 *
	 * @param int $post_id Listing post ID.
	 */
	public static function save_post( $post_id ): void {
		// Defer-checks gauntlet — autosave, capability, nonce, CPT.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}
		if ( 'listora_listing' !== get_post_type( $post_id ) ) {
			return;
		}

		$want_featured = ! empty( $_POST['wb_listora_featured_enabled'] );
		$days          = isset( $_POST['wb_listora_featured_days'] )
			? (int) sanitize_text_field( wp_unslash( $_POST['wb_listora_featured_days'] ) )
			: 0;

		$is_featured = Featured::is_featured( $post_id );

		if ( $want_featured && ! $is_featured ) {
			Featured::feature_listing( $post_id, $days );
		} elseif ( $want_featured && $is_featured ) {
			// Already featured — only re-feature if the duration meaningfully
			// changed (or the admin wants to refresh / make it permanent).
			// Re-running feature_listing() is idempotent: it resets `until`.
			Featured::feature_listing( $post_id, $days );
		} elseif ( ! $want_featured && $is_featured ) {
			Featured::unfeature_listing( $post_id, 'manual' );
		}
	}
}
