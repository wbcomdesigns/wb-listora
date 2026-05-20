<?php
/**
 * Listing Reports meta box on the Edit Listing screen.
 *
 * Surfaces visitor-submitted reports (flags) so admins can review and act on
 * them without leaving wp-admin. Reports are written by the REST endpoint
 * `POST /listora/v1/listings/{id}/report` into the non-autoloaded option
 * `_listora_listing_reports_{id}`; this box only reads them and offers a
 * "Clear all reports" action.
 *
 * The box only renders when a listing actually has reports, so it stays out of
 * the way for the vast majority of listings. The matching admin-list column
 * (`Reports`) links here via the `#wb_listora_reports` anchor.
 *
 * @package WBListora\Admin
 */

namespace WBListora\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Renders + clears the Reports meta box on listora_listing edit screens.
 */
class Report_Metabox {

	const NONCE_NAME   = '_wb_listora_reports_metabox_nonce';
	const NONCE_ACTION = 'wb_listora_reports_metabox';

	/**
	 * Option name holding the reports array for a listing.
	 *
	 * @param int $post_id Listing ID.
	 * @return string
	 */
	public static function option_name( int $post_id ): string {
		return '_listora_listing_reports_' . $post_id;
	}

	/**
	 * Human-readable labels for the report reason keys (mirrors the REST enum).
	 *
	 * @return array<string,string>
	 */
	private static function reason_labels(): array {
		return array(
			'inaccurate' => __( 'Inaccurate information', 'wb-listora' ),
			'spam'       => __( 'Spam or misleading', 'wb-listora' ),
			'closed'     => __( 'Permanently closed', 'wb-listora' ),
			'duplicate'  => __( 'Duplicate listing', 'wb-listora' ),
			'offensive'  => __( 'Offensive or inappropriate', 'wb-listora' ),
			'other'      => __( 'Something else', 'wb-listora' ),
		);
	}

	/**
	 * Register WordPress hooks.
	 */
	public static function register(): void {
		add_action( 'add_meta_boxes_listora_listing', array( __CLASS__, 'register_metabox' ) );
		add_action( 'save_post_listora_listing', array( __CLASS__, 'save_post' ), 20, 1 );
	}

	/**
	 * Register the meta box only when the listing has reports.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public static function register_metabox( $post ): void {
		$reports = self::get_reports( (int) $post->ID );
		if ( empty( $reports ) ) {
			return;
		}

		add_meta_box(
			'wb_listora_reports',
			sprintf(
				/* translators: %d: number of reports. */
				_n( 'Report (%d)', 'Reports (%d)', count( $reports ), 'wb-listora' ),
				count( $reports )
			),
			array( __CLASS__, 'render' ),
			'listora_listing',
			'side',
			'high'
		);
	}

	/**
	 * Read the stored reports for a listing.
	 *
	 * @param int $post_id Listing ID.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_reports( int $post_id ): array {
		$reports = get_option( self::option_name( $post_id ), array() );
		return is_array( $reports ) ? $reports : array();
	}

	/**
	 * Render the meta box body.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public static function render( $post ): void {
		$reports = self::get_reports( (int) $post->ID );
		$labels  = self::reason_labels();

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<p class="description">
			<?php esc_html_e( 'Visitor-submitted flags for this listing. Review them, edit or trash the listing as needed, then clear the reports.', 'wb-listora' ); ?>
		</p>
		<ul class="wb-listora-reports-metabox__list">
			<?php foreach ( $reports as $report ) : ?>
				<?php
				$reason   = isset( $report['reason'] ) ? (string) $report['reason'] : 'other';
				$label    = $labels[ $reason ] ?? $labels['other'];
				$details  = isset( $report['details'] ) ? (string) $report['details'] : '';
				$date     = isset( $report['date'] ) ? (string) $report['date'] : '';
				$reporter = isset( $report['user_id'] ) ? get_userdata( (int) $report['user_id'] ) : false;
				$when     = $date ? (string) mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $date ) : '';
				?>
				<li class="wb-listora-reports-metabox__item">
					<strong><?php echo esc_html( $label ); ?></strong>
					<?php if ( $details ) : ?>
						<span class="wb-listora-reports-metabox__details"><?php echo esc_html( $details ); ?></span>
					<?php endif; ?>
					<span class="description">
						<?php
						if ( $reporter && $when ) {
							printf(
								/* translators: 1: reporter display name, 2: date/time. */
								esc_html__( 'by %1$s on %2$s', 'wb-listora' ),
								esc_html( $reporter->display_name ),
								esc_html( $when )
							);
						} elseif ( $when ) {
							echo esc_html( $when );
						}
						?>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
		<p>
			<label>
				<input type="checkbox" name="wb_listora_clear_reports" value="1" />
				<?php esc_html_e( 'Clear all reports on save', 'wb-listora' ); ?>
			</label>
		</p>
		<?php
	}

	/**
	 * Handle save_post — clear the reports when the admin requests it.
	 *
	 * @param int $post_id Listing post ID.
	 */
	public static function save_post( $post_id ): void {
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
		if ( empty( $_POST['wb_listora_clear_reports'] ) ) {
			return;
		}

		delete_option( self::option_name( (int) $post_id ) );

		/**
		 * Fires after an admin clears all reports on a listing.
		 *
		 * @param int $post_id Listing ID whose reports were cleared.
		 */
		do_action( 'wb_listora_listing_reports_cleared', (int) $post_id );
	}
}
