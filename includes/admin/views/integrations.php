<?php
/**
 * Integrations admin page — Listora companion plugins.
 *
 * One card per Companion_Registry entry: status badge (Connected / Installed,
 * activate / Not installed) + the matching action (one-click free install,
 * activate, or upgrade to Pro). No data is created here — the screen reflects
 * registry status and triggers installs through Companion_Installer.
 *
 * @package WBListora\Admin
 */

defined( 'ABSPATH' ) || exit;

use WBListora\Integrations\Companion_Registry;

$listora_companions = Companion_Registry::all();

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only redirect-back status flags, no state change.
$listora_install_state = isset( $_GET['listora_install'] ) ? sanitize_key( wp_unslash( $_GET['listora_install'] ) ) : '';
$listora_install_msg   = isset( $_GET['listora_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['listora_msg'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended
?>
<div class="wrap wb-listora-admin listora-integrations-page">

	<div class="listora-page-header">
		<div class="listora-page-header__left">
			<h1 class="listora-page-header__title">
				<i data-lucide="blocks" class="listora-icon--sm" aria-hidden="true"></i>
				<?php esc_html_e( 'Integrations', 'wb-listora' ); ?>
			</h1>
			<p class="listora-page-header__desc">
				<?php esc_html_e( 'Extend your directory with the Wbcom stack. Each plugin works on its own. Installing one here does not tie it to WB Listora.', 'wb-listora' ); ?>
			</p>
		</div>
	</div>
	<hr class="wp-header-end">

	<?php if ( 'ok' === $listora_install_state ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Integration installed and activated.', 'wb-listora' ); ?></p>
		</div>
	<?php elseif ( 'error' === $listora_install_state && '' !== $listora_install_msg ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( $listora_install_msg ); ?></p>
		</div>
	<?php endif; ?>

	<div class="listora-integrations-grid">
		<?php
		foreach ( $listora_companions as $listora_slug => $listora_c ) :
			$listora_status  = Companion_Registry::status( $listora_slug );
			$listora_label   = (string) ( $listora_c['label'] ?? $listora_slug );
			$listora_why     = (string) ( $listora_c['why'] ?? '' );
			$listora_unlocks = (string) ( $listora_c['unlocks'] ?? '' );
			$listora_store   = (string) ( $listora_c['store_url'] ?? '' );
			$listora_has_pro = ! empty( $listora_c['pro']['item_id'] );

			// Status badge variant + label.
			if ( 'active' === $listora_status ) {
				$listora_badge_class = 'listora-status-badge listora-status-badge--success';
				$listora_badge_label = __( 'Connected', 'wb-listora' );
			} elseif ( 'installed_inactive' === $listora_status ) {
				$listora_badge_class = 'listora-status-badge listora-status-badge--warning';
				$listora_badge_label = __( 'Installed, activate', 'wb-listora' );
			} else {
				$listora_badge_class = 'listora-status-badge listora-status-badge--muted';
				$listora_badge_label = __( 'Not installed', 'wb-listora' );
			}
			?>
			<div class="listora-integration-card">
				<div class="listora-integration-card__head">
					<h2 class="listora-integration-card__title"><?php echo esc_html( $listora_label ); ?></h2>
					<span class="<?php echo esc_attr( $listora_badge_class ); ?>"><?php echo esc_html( $listora_badge_label ); ?></span>
				</div>

				<?php if ( '' !== $listora_why ) : ?>
					<p class="listora-integration-card__why"><?php echo esc_html( $listora_why ); ?></p>
				<?php endif; ?>

				<?php if ( 'active' === $listora_status && '' !== $listora_unlocks ) : ?>
					<p class="listora-integration-card__unlocks">
						<i data-lucide="check-circle-2" aria-hidden="true"></i>
						<?php echo esc_html( $listora_unlocks ); ?>
					</p>
				<?php endif; ?>

				<div class="listora-integration-card__actions">
					<?php if ( 'active' === $listora_status ) : ?>
						<span class="listora-btn listora-btn--ghost" aria-disabled="true">
							<i data-lucide="check" aria-hidden="true"></i> <?php esc_html_e( 'Connected', 'wb-listora' ); ?>
						</span>
					<?php else : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="wb_listora_install_companion">
							<input type="hidden" name="companion" value="<?php echo esc_attr( $listora_slug ); ?>">
							<input type="hidden" name="tier" value="free">
							<?php wp_nonce_field( 'wb_listora_install_companion_' . $listora_slug ); ?>
							<button type="submit" class="listora-btn wp-element-button listora-btn--primary">
								<?php
								echo 'installed_inactive' === $listora_status
									? esc_html__( 'Activate', 'wb-listora' )
									: esc_html__( 'Install free', 'wb-listora' );
								?>
							</button>
						</form>
					<?php endif; ?>

					<?php if ( $listora_has_pro && '' !== $listora_store ) : ?>
						<a href="<?php echo esc_url( $listora_store ); ?>" target="_blank" rel="noopener noreferrer" class="listora-btn listora-btn--ghost">
							<?php esc_html_e( 'Upgrade to Pro', 'wb-listora' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<p class="listora-integrations-footnote description">
		<?php esc_html_e( 'These plugins are standalone Wbcom products. WB Listora detects them and lights up the matching features when present.', 'wb-listora' ); ?>
	</p>
</div>
