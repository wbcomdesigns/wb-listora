<?php
/**
 * User Dashboard — Profile/settings tab content.
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/user-dashboard/tab-profile.php
 *
 * @package WBListora
 *
 * @var int    $user_id   Current user ID.
 * @var object $user      WP_User object.
 * @var array  $view_data Full view data array.
 */

defined( 'ABSPATH' ) || exit;

$view_data = $view_data ?? get_defined_vars();

$listora_phone        = (string) get_user_meta( $user_id, '_listora_phone', true );
$listora_social_links = get_user_meta( $user_id, '_listora_social_links', true );
$listora_social_links = is_array( $listora_social_links ) ? $listora_social_links : array();
$listora_platforms    = class_exists( '\\WBListora\\Core\\Field' ) ? \WBListora\Core\Field::social_link_platforms() : array();

do_action( 'wb_listora_before_dashboard_profile', $view_data );
?>
<div role="tabpanel" id="dash-panel-profile" aria-labelledby="dash-tab-profile" class="listora-dashboard__panel" data-wp-interactive="listora/directory" hidden>
	<form class="listora-dashboard__profile-form" method="post" action="" data-wp-on--submit="actions.saveProfile">
		<?php wp_nonce_field( 'listora_update_profile', 'listora_profile_nonce' ); ?>

		<div class="listora-dashboard__profile-grid">
			<div class="listora-submission__field">
				<label for="listora-display-name" class="listora-submission__label"><?php esc_html_e( 'Display Name', 'wb-listora' ); ?> <span class="required">*</span></label>
				<input type="text" id="listora-display-name" name="display_name" class="listora-input" required
					value="<?php echo esc_attr( $user->display_name ); ?>" />
			</div>

			<div class="listora-submission__field">
				<label for="listora-email" class="listora-submission__label"><?php esc_html_e( 'Email', 'wb-listora' ); ?> <span class="required">*</span></label>
				<input type="email" id="listora-email" name="email" class="listora-input" required
					value="<?php echo esc_attr( $user->user_email ); ?>" />
			</div>

			<div class="listora-submission__field">
				<label for="listora-first-name" class="listora-submission__label"><?php esc_html_e( 'First name', 'wb-listora' ); ?></label>
				<input type="text" id="listora-first-name" name="first_name" class="listora-input"
					autocomplete="given-name"
					value="<?php echo esc_attr( $user->first_name ); ?>" />
			</div>

			<div class="listora-submission__field">
				<label for="listora-last-name" class="listora-submission__label"><?php esc_html_e( 'Last name', 'wb-listora' ); ?></label>
				<input type="text" id="listora-last-name" name="last_name" class="listora-input"
					autocomplete="family-name"
					value="<?php echo esc_attr( $user->last_name ); ?>" />
			</div>

			<div class="listora-submission__field">
				<label for="listora-phone" class="listora-submission__label"><?php esc_html_e( 'Phone', 'wb-listora' ); ?></label>
				<input type="tel" id="listora-phone" name="phone" class="listora-input"
					autocomplete="tel"
					placeholder="<?php esc_attr_e( 'e.g. +1 415 555 0123', 'wb-listora' ); ?>"
					value="<?php echo esc_attr( $listora_phone ); ?>" />
				<p class="listora-submission__hint"><?php esc_html_e( 'Private — not shown publicly on your listings.', 'wb-listora' ); ?></p>
			</div>

			<div class="listora-submission__field">
				<label for="listora-website" class="listora-submission__label"><?php esc_html_e( 'Website', 'wb-listora' ); ?></label>
				<input type="url" id="listora-website" name="website" class="listora-input"
					autocomplete="url"
					placeholder="https://"
					value="<?php echo esc_attr( $user->user_url ); ?>" />
			</div>

			<div class="listora-submission__field listora-submission__field--full">
				<label for="listora-bio" class="listora-submission__label"><?php esc_html_e( 'Bio', 'wb-listora' ); ?></label>
				<textarea id="listora-bio" name="description" class="listora-input listora-submission__textarea" rows="3"><?php echo esc_textarea( $user->description ); ?></textarea>
			</div>
		</div>

		<?php if ( ! empty( $listora_platforms ) ) : ?>
		<div class="listora-dashboard__profile-section">
			<h3 class="listora-dashboard__section-title"><?php esc_html_e( 'Social Links', 'wb-listora' ); ?></h3>
			<p class="listora-dashboard__section-desc"><?php esc_html_e( 'Optional — paste the URL for any platforms you want to surface on your public profile. Leave blank to omit.', 'wb-listora' ); ?></p>
			<div class="listora-dashboard__profile-grid">
				<?php foreach ( $listora_platforms as $platform_slug => $platform_label ) :
					$current = isset( $listora_social_links[ $platform_slug ] ) ? (string) $listora_social_links[ $platform_slug ] : '';
					$input_id = 'listora-social-' . sanitize_html_class( $platform_slug );
					?>
					<div class="listora-submission__field">
						<label for="<?php echo esc_attr( $input_id ); ?>" class="listora-submission__label">
							<?php echo esc_html( $platform_label ); ?>
						</label>
						<input
							type="url"
							id="<?php echo esc_attr( $input_id ); ?>"
							name="social_links[<?php echo esc_attr( $platform_slug ); ?>]"
							class="listora-input"
							placeholder="https://"
							value="<?php echo esc_attr( $current ); ?>"
						/>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<div class="listora-dashboard__profile-section">
			<h3 class="listora-dashboard__section-title"><?php esc_html_e( 'Email Notifications', 'wb-listora' ); ?></h3>

			<?php
			$notification_events = array(
				'listing_submitted'     => __( 'Listing submitted for review', 'wb-listora' ),
				'listing_approved'      => __( 'Listing approved and published', 'wb-listora' ),
				'listing_rejected'      => __( 'Listing rejected', 'wb-listora' ),
				'listing_expired'       => __( 'Listing expired', 'wb-listora' ),
				'listing_expiring_soon' => __( 'Listing expiration reminders', 'wb-listora' ),
				'review_received'       => __( 'New review on my listing', 'wb-listora' ),
				'review_reply'          => __( 'Owner replied to my review', 'wb-listora' ),
				'review_helpful'        => __( 'My review reaches a helpful-vote milestone', 'wb-listora' ),
				'claim_submitted'       => __( 'Claim submitted on my listing', 'wb-listora' ),
				'claim_approved'        => __( 'My claim was approved', 'wb-listora' ),
				'claim_rejected'        => __( 'My claim was rejected', 'wb-listora' ),
			);
			foreach ( $notification_events as $event_key => $event_label ) :
				$meta_key = '_listora_notify_' . $event_key;
				$meta_val = get_user_meta( $user_id, $meta_key, true );
				// Default to enabled (checked) when no preference has been saved.
				$checked = '' === $meta_val || '1' === $meta_val;
				?>
			<div class="listora-dashboard__notification-toggle">
				<span class="listora-dashboard__notification-label"><?php echo esc_html( $event_label ); ?></span>
				<label class="listora-toggle">
					<input type="checkbox" name="notification_prefs[<?php echo esc_attr( $event_key ); ?>]" value="1"
						class="listora-toggle__input" <?php checked( $checked ); ?> />
					<span class="listora-toggle__track"></span>
				</label>
			</div>
			<?php endforeach; ?>
		</div>

		<div class="listora-dashboard__profile-actions">
			<button type="submit" name="listora_update_profile" class="listora-btn listora-btn--primary">
				<?php esc_html_e( 'Save Changes', 'wb-listora' ); ?>
			</button>
		</div>
	</form>
</div>
<?php
do_action( 'wb_listora_after_dashboard_profile', $view_data );
