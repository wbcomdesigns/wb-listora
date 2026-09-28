<?php
/**
 * Listing Detail — Tab navigation + panels container.
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/listing-detail/tabs.php
 *
 * @package WBListora
 *
 * @var int    $post_id              Listing post ID.
 * @var object $post                 WP_Post object.
 * @var object $type                 Listing type object or null.
 * @var array  $meta                 All listing meta values.
 * @var array  $field_groups         Field group objects.
 * @var array  $features             Feature term objects.
 * @var array  $business_hours       Business hours data.
 * @var array  $detail_services      Services array.
 * @var int    $detail_service_count Service count.
 * @var bool   $show_reviews         Whether to show reviews tab.
 * @var bool   $show_map             Whether to show map tab.
 * @var float  $avg_rating           Average rating.
 * @var int    $review_count         Review count.
 * @var float  $lat                  Latitude.
 * @var float  $lng                  Longitude.
 * @var string $map_provider         Resolved map provider key ('osm' default, 'google' via Pro).
 * @var int    $map_default_zoom     Admin-configured default map zoom.
 * @var array  $detail_reviews       Pre-assembled review rows (newest first, limit 20) as ARRAY_A.
 * @var array  $detail_review_summary Keys: avg (float), total (int), dist (array<int,int> stars 1-5).
 * @var bool   $detail_review_pending Whether the current user's review here awaits moderation.
 * @var bool   $detail_user_reviewed Whether the current user has used up their review here (one per listing is on and they reviewed).
 * @var bool   $detail_is_owner      Whether the current user authored this listing.
 * @var array  $view_data            Full view data array.
 *
 * Pre-assembled in blocks/listing-detail/render.php — do NOT add $wpdb queries
 * here. Add data by hooking `wb_listora_detail_tabs_view_data`.
 */

defined( 'ABSPATH' ) || exit;

$view_data = $view_data ?? get_defined_vars();

// Contact details (name, phone, email, website) live in the sidebar Contact
// card, the address in the header, and the map in the sidebar Location card,
// so none of them renders a second time inside a tab (card 10337187661). A
// group made only of those fields renders no tab at all.
$listora_surfaced_keys     = array( 'contact_name', 'phone', 'email', 'website' );
$listora_field_is_surfaced = static function ( $listora_field ) use ( $listora_surfaced_keys ) {
	return 'map_location' === $listora_field->get_type()
		|| in_array( $listora_field->get_type(), array( 'phone', 'email' ), true )
		|| in_array( $listora_field->get_key(), $listora_surfaced_keys, true );
};

// Filter field groups down to those that will actually render visible content.
// A group with only gallery/social_links fields (rendered elsewhere) or only
// empty values produces an empty tab - surface-area noise.
$listora_renderable_groups = array();
foreach ( $field_groups as $listora_rg ) {
	$listora_rg_has_content = false;
	foreach ( $listora_rg->get_fields() as $listora_rg_field ) {
		if ( ! $listora_rg_field->check_conditional( $meta ) || $listora_field_is_surfaced( $listora_rg_field ) ) {
			continue;
		}
		$listora_rg_type = $listora_rg_field->get_type();
		if ( in_array( $listora_rg_type, array( 'gallery', 'social_links' ), true ) ) {
			continue;
		}
		$listora_rg_val = $meta[ $listora_rg_field->get_key() ] ?? '';
		if ( 'business_hours' === $listora_rg_type ) {
			if ( ! empty( $business_hours ) ) {
				$listora_rg_has_content = true;
				break;
			}
			continue;
		}
		$listora_rg_display = wb_listora_format_card_value( $listora_rg_field, $listora_rg_val );
		if ( '' !== $listora_rg_display ) {
			$listora_rg_has_content = true;
			break;
		}
	}
	if ( $listora_rg_has_content ) {
		$listora_renderable_groups[] = $listora_rg;
	}
}
$field_groups = $listora_renderable_groups;

do_action( 'wb_listora_before_detail_tabs', $view_data );
?>
<div class="listora-detail__main">

	<?php // Tab Navigation. ?>
	<div class="listora-detail__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Listing details', 'wb-listora' ); ?>">
		<button role="tab" class="listora-detail__tab is-active" id="tab-overview" aria-selected="true" aria-controls="panel-overview"
			data-wp-on--click="actions.switchTab" data-wp-context='{"tabId":"overview"}'>
			<?php esc_html_e( 'Overview', 'wb-listora' ); ?>
		</button>
		<?php foreach ( $field_groups as $group ) : ?>
		<button role="tab" class="listora-detail__tab" id="tab-<?php echo esc_attr( $group->get_key() ); ?>" aria-selected="false"
			aria-controls="panel-<?php echo esc_attr( $group->get_key() ); ?>"
			data-wp-on--click="actions.switchTab" data-wp-context='{"tabId":"<?php echo esc_attr( $group->get_key() ); ?>"}'>
			<?php echo esc_html( $group->get_label() ); ?>
		</button>
		<?php endforeach; ?>
		<?php if ( $detail_service_count > 0 ) : ?>
		<button role="tab" class="listora-detail__tab" id="tab-services" aria-selected="false" aria-controls="panel-services"
			data-wp-on--click="actions.switchTab" data-wp-context='{"tabId":"services"}'>
			<?php esc_html_e( 'Services', 'wb-listora' ); ?>
			<span class="listora-detail__tab-count"><?php echo esc_html( $detail_service_count ); ?></span>
		</button>
		<?php endif; ?>
		<?php if ( $show_reviews ) : ?>
		<button role="tab" class="listora-detail__tab" id="tab-reviews" aria-selected="false" aria-controls="panel-reviews"
			data-wp-on--click="actions.switchTab" data-wp-context='{"tabId":"reviews"}'>
			<?php esc_html_e( 'Reviews', 'wb-listora' ); ?>
			<?php if ( $review_count > 0 ) : ?>
			<span class="listora-detail__tab-count"><?php echo esc_html( $review_count ); ?></span>
			<?php endif; ?>
		</button>
		<?php endif; ?>
	</div>

	<?php // Overview Tab. ?>
	<div role="tabpanel" id="panel-overview" aria-labelledby="tab-overview" class="listora-detail__panel">
		<?php if ( $post->post_content ) : ?>
		<div class="listora-detail__description listora-prose" itemprop="description">
			<?php echo wp_kses_post( apply_filters( 'the_content', $post->post_content ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WP filter. ?>
		</div>
		<?php endif; ?>

		<?php
		/*
		 * Video.
		 *
		 * The field has been accepting and storing a URL since it shipped —
		 * submission input, REST save, meta schema, field registry, and the
		 * edit form reads it back — with no render path anywhere, so an owner
		 * could add a video and it appeared on exactly zero surfaces
		 * (BC 10194472456).
		 *
		 * oEmbed rather than a hand-rolled <iframe>: it covers YouTube, Vimeo
		 * and every other provider WordPress knows, and it respects the site's
		 * oEmbed settings and privacy filters instead of bypassing them.
		 *
		 * Nothing is emitted when the URL is empty, not a URL, or from a
		 * provider oEmbed cannot resolve — a blank bordered section is worse
		 * than no section, and a raw <iframe> to an unresolvable URL is worse
		 * than both.
		 */
		$listing_video = isset( $meta['video'] ) ? trim( (string) $meta['video'] ) : '';
		$video_embed   = ( '' !== $listing_video && wp_http_validate_url( $listing_video ) )
			? wp_oembed_get( $listing_video )
			: '';

		if ( $video_embed ) :
			?>
		<div class="listora-detail__video">
			<h3 class="listora-detail__video-title"><?php esc_html_e( 'Video', 'wb-listora' ); ?></h3>
			<div class="listora-detail__video-embed">
				<?php
				// wp_oembed_get() returns provider markup already run through
				// WordPress's own oEmbed sanitisation; wp_kses_post would strip
				// the <iframe> that IS the embed.
				echo $video_embed; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- oEmbed provider markup.
				?>
			</div>
		</div>
			<?php
		endif;
		?>

		<?php // Quick info fields. ?>
		<?php if ( $type ) : ?>
			<?php
			// A <dl>, not a <div>: the children below are dt/dd pairs, and without a
			// dl ancestor a screen reader cannot associate a term with its value
			// (BC 10208341045). The inner .listora-detail__info-item divs are fine as
			// they are - HTML5 explicitly permits a div grouping a dt/dd pair inside
			// a dl, which is the same shape .listora-detail__field-list already uses.
			?>
		<dl class="listora-detail__quick-info">
			<?php
			foreach ( $type->get_card_fields() as $field ) :
				$key     = $field->get_key();
				$value   = $meta[ $key ] ?? '';
				$display = wb_listora_format_card_value( $field, $value );
				// `file` fields (e.g. Company Logo on Job listings) carry an
				// attachment ID, not a display string. They render as an
				// image / download link inside their own field-group tab
				// (see lines below), so emitting them here would just print
				// the raw ID like "Company Logo: 818" — Basecamp 9867775853.
				if ( '' === $display
					// Rendered as an embed above, not as a URL row.
					|| 'video' === $key
					// Contact details and the address live in the sidebar and header.
					|| $listora_field_is_surfaced( $field )
					|| 'gallery' === $field->get_type()
					|| 'social_links' === $field->get_type()
					|| 'business_hours' === $field->get_type()
					|| 'file' === $field->get_type() ) {
					continue;
				}
				// A ticked box answers with "Yes"; the label is the term already.
				if ( 'checkbox' === $field->get_type() ) {
					$display = __( 'Yes', 'wb-listora' );
				}
				?>
			<div class="listora-detail__info-item">
				<dt><?php echo esc_html( $field->get_label() ); ?></dt>
				<dd><?php echo esc_html( $display ); ?></dd>
			</div>
			<?php endforeach; ?>
		</dl>
		<?php endif; ?>

		<?php // Features. ?>
		<?php if ( ! empty( $features ) ) : ?>
		<div class="listora-detail__features">
			<h3><?php esc_html_e( 'Features & Amenities', 'wb-listora' ); ?></h3>
			<div class="listora-detail__features-list">
				<?php foreach ( $features as $feature ) : ?>
				<span class="listora-feature-badge">
					<?php $icon = get_term_meta( $feature->term_id, '_listora_icon', true ); ?>
					<?php if ( $icon ) : ?>
						<?php echo \WBListora\Core\Lucide_Icons::render( $icon, 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endif; ?>
					<?php echo esc_html( $feature->name ); ?>
				</span>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<?php // Tags. ?>
		<?php if ( ! empty( $listing_tags ) ) : ?>
		<div class="listora-detail__tags">
			<h3><?php esc_html_e( 'Tags', 'wb-listora' ); ?></h3>
			<div class="listora-detail__tags-list">
				<?php
				foreach ( $listing_tags as $listing_tag ) :
					/*
					 * Links into the directory's own tag filter rather than the
					 * WordPress term archive: the archive would list listings
					 * outside the directory's search chrome, with none of its
					 * filters. `?tags=` is the same parameter the REST search
					 * accepts, so the link and the API agree.
					 */
					$tag_url = add_query_arg( 'listora_tags', rawurlencode( $listing_tag->slug ), wb_listora_get_directory_url() );
					?>
					<a class="listora-tag-badge" href="<?php echo esc_url( $tag_url ); ?>" rel="tag">
						<?php echo esc_html( $listing_tag->name ); ?>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>
	</div>

	<?php // Field Group Tabs. ?>
	<?php foreach ( $field_groups as $group ) : ?>
	<div role="tabpanel" id="panel-<?php echo esc_attr( $group->get_key() ); ?>" aria-labelledby="tab-<?php echo esc_attr( $group->get_key() ); ?>" class="listora-detail__panel" hidden>
		<dl class="listora-detail__field-list">
			<?php
			foreach ( $group->get_fields() as $field ) :
				// Skip fields whose conditional logic is not met.
				if ( ! $field->check_conditional( $meta ) ) {
					continue;
				}

				$key   = $field->get_key();
				$value = $meta[ $key ] ?? '';

				// Skip complex types that render separately, and the contact
				// details / address the sidebar and header already show.
				if ( in_array( $field->get_type(), array( 'gallery', 'social_links' ), true ) || $listora_field_is_surfaced( $field ) ) {
					continue;
				}

				// Skip when the display value would be empty so the dl doesn't
				// render a label with no answer. The full value: this is the
				// listing's own page, not a card (a textarea showed its first
				// five words, card 10340409895).
				$display = wb_listora_format_card_value( $field, $value, true );
				if ( '' === $display ) {
					continue;
				}
				// A ticked box answers with "Yes"; the label is the term already
				// (it used to print "Delivery Available: Delivery Available").
				if ( 'checkbox' === $field->get_type() ) {
					$display = __( 'Yes', 'wb-listora' );
				}

				// Business hours: render as schedule.
				if ( 'business_hours' === $field->get_type() && ! empty( $business_hours ) ) :
					?>
				<div class="listora-detail__field-item listora-detail__field-item--hours">
					<dt><?php echo esc_html( $field->get_label() ); ?></dt>
					<dd>
						<?php echo wp_kses_post( wb_listora_render_hours( $business_hours ) ); ?>
					</dd>
				</div>
					<?php
					continue;
endif;

				// File / image type — value is an attachment ID. Render
				// the actual image or a download link, not the bare integer
				// (Basecamp 9838412472 — Job Company Logo never appeared
				// on the detail page despite being saved correctly).
				if ( 'file' === $field->get_type() ) {
					$file_id  = absint( $value );
					$file_url = $file_id ? wp_get_attachment_url( $file_id ) : '';
					if ( ! $file_url ) {
						continue;
					}
					$is_image = $file_id ? wp_attachment_is_image( $file_id ) : false;
					?>
				<div class="listora-detail__field-item listora-detail__field-item--file">
					<dt><?php echo esc_html( $field->get_label() ); ?></dt>
					<dd>
						<?php if ( $is_image ) : ?>
							<img class="listora-detail__field-image" src="<?php echo esc_url( wp_get_attachment_image_url( $file_id, 'medium' ) ?: $file_url ); ?>" alt="<?php echo esc_attr( $field->get_label() ); ?>" loading="lazy" />
						<?php else : ?>
							<a href="<?php echo esc_url( $file_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( basename( wp_parse_url( $file_url, PHP_URL_PATH ) ?: $file_url ) ); ?></a>
						<?php endif; ?>
					</dd>
				</div>
					<?php
					continue;
				}
				?>
			<div class="listora-detail__field-item">
				<dt><?php echo esc_html( $field->get_label() ); ?></dt>
				<dd>
					<?php if ( 'url' === $field->get_type() ) : ?>
					<a href="<?php echo esc_url( $value ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wp_parse_url( $value, PHP_URL_HOST ) ?: $value ); ?></a>
					<?php elseif ( 'email' === $field->get_type() ) : ?>
					<a href="mailto:<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $value ); ?></a>
					<?php elseif ( 'phone' === $field->get_type() ) : ?>
					<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $value ) ); ?>"><?php echo esc_html( $value ); ?></a>
					<?php elseif ( 'textarea' === $field->get_type() ) : ?>
						<?php echo wp_kses_post( wpautop( esc_html( $display ) ) ); ?>
					<?php else : ?>
						<?php echo esc_html( $display ); ?>
					<?php endif; ?>
				</dd>
			</div>
			<?php endforeach; ?>
		</dl>
	</div>
	<?php endforeach; ?>

	<?php // Services Tab. ?>
	<?php if ( $detail_service_count > 0 ) : ?>
	<div role="tabpanel" id="panel-services" aria-labelledby="tab-services" class="listora-detail__panel" hidden>
		<div class="listora-detail__services-grid">
			<?php foreach ( $detail_services as $svc ) : ?>
			<div class="listora-detail__service-card">
				<?php
				$svc_image_url = '';
				if ( ! empty( $svc['image_id'] ) ) {
					$svc_image_url = wp_get_attachment_image_url( (int) $svc['image_id'], 'medium' );
				}
				?>
				<?php if ( $svc_image_url ) : ?>
				<div class="listora-detail__service-image">
					<img src="<?php echo esc_url( $svc_image_url ); ?>" alt="<?php echo esc_attr( $svc['title'] ); ?>" loading="lazy" />
				</div>
				<?php endif; ?>
				<div class="listora-detail__service-body">
					<h4 class="listora-detail__service-title"><?php echo esc_html( $svc['title'] ); ?></h4>
					<?php
					// Price display.
					$svc_price_display = '';
					if ( 'free' === $svc['price_type'] ) {
						$svc_price_display = __( 'Free', 'wb-listora' );
					} elseif ( 'contact' === $svc['price_type'] ) {
						$svc_price_display = __( 'Contact for price', 'wb-listora' );
					} elseif ( null !== $svc['price'] ) {
						/*
						 * The site's currency, never a literal symbol. The three
						 * branches below used to hardcode `$`, so a directory set to
						 * JPY or EUR still advertised its services in dollars while
						 * every other price on the page honoured the setting.
						 * `wb_listora_format_currency()` resolves symbol, position
						 * and decimal precision from the currency, which is why the
						 * amount is passed to it raw rather than pre-formatted.
						 */
						$svc_formatted_price = wb_listora_format_currency( (float) $svc['price'] );
						if ( 'starting_from' === $svc['price_type'] ) {
							/* translators: %s: price with currency symbol, e.g. $35 */
							$svc_price_display = sprintf( __( 'From %s', 'wb-listora' ), $svc_formatted_price );
						} elseif ( 'hourly' === $svc['price_type'] ) {
							/* translators: %s: price with currency symbol, e.g. $35 */
							$svc_price_display = sprintf( __( '%s/hr', 'wb-listora' ), $svc_formatted_price );
						} else {
							$svc_price_display = $svc_formatted_price;
						}
					}
					?>
					<div class="listora-detail__service-meta">
						<?php if ( $svc_price_display ) : ?>
						<span class="listora-detail__service-price"><?php echo esc_html( $svc_price_display ); ?></span>
						<?php endif; ?>
						<?php if ( ! empty( $svc['duration_minutes'] ) ) : ?>
						<span class="listora-detail__service-duration">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
							<?php
							$svc_hours = floor( (int) $svc['duration_minutes'] / 60 );
							$svc_mins  = (int) $svc['duration_minutes'] % 60;
							if ( $svc_hours > 0 && $svc_mins > 0 ) {
								/* translators: 1: hours, 2: minutes */
								printf( esc_html__( '%1$dh %2$dmin', 'wb-listora' ), (int) $svc_hours, (int) $svc_mins );
							} elseif ( $svc_hours > 0 ) {
								/* translators: %d: hours */
								printf( esc_html( _n( '%d hour', '%d hours', (int) $svc_hours, 'wb-listora' ) ), (int) $svc_hours );
							} else {
								/* translators: %d: minutes */
								printf( esc_html__( '%d min', 'wb-listora' ), (int) $svc_mins );
							}
							?>
						</span>
						<?php endif; ?>
					</div>
					<?php if ( ! empty( $svc['description'] ) ) : ?>
						<?php
						// Card 9876326138 — only render the expand toggle when
						// the description actually overflows the collapsed
						// view. CSS clamps to 2 lines ≈ ~120 chars; use
						// stripped-tag length as a stable proxy. Short
						// descriptions render inline without a toggle.
						$listora_svc_desc         = (string) $svc['description'];
						$listora_svc_needs_toggle = mb_strlen( wp_strip_all_tags( $listora_svc_desc ) ) > 120;
						?>
					<div class="listora-detail__service-desc-wrap">
						<p class="listora-detail__service-desc <?php echo $listora_svc_needs_toggle ? 'listora-detail__service-desc--collapsed' : ''; ?>"><?php echo esc_html( $listora_svc_desc ); ?></p>
						<?php if ( $listora_svc_needs_toggle ) : ?>
						<button type="button" class="listora-btn listora-btn--text listora-detail__service-toggle"
							data-wp-on--click="actions.toggleServiceDesc">
							<?php esc_html_e( 'Details', 'wb-listora' ); ?>
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
						</button>
						<?php endif; ?>
					</div>
					<?php endif; ?>
					<?php
					/**
					 * Fires after a single service's detail panel is rendered, before the service card closes.
					 *
					 * Extension surface for booking CTAs (Pro renders a "Book this service" button here).
					 *
					 * @param int $service_id The service row ID.
					 * @param int $listing_id The parent listing post ID.
					 */
					do_action( 'wb_listora_after_service_detail', (int) $svc['id'], (int) $post_id );
					?>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>

	<?php // Reviews Tab. ?>
	<?php if ( $show_reviews ) : ?>
	<div role="tabpanel" id="panel-reviews" aria-labelledby="tab-reviews" class="listora-detail__panel" hidden>
		<?php
		// Legacy "#reviews" alias anchor — review_reminder emails sent before
		// 1.2.0 linked to "#reviews" (no element carried that id, so the panel
		// stayed JS-blank until hydration). Placing the anchor INSIDE the panel
		// lets the existing #panel-reviews:has(:target) CSS reveal the panel
		// server-side for those old links. New emails target #oldest-unanswered.
		?>
		<span id="reviews" class="listora-detail__review-anchor" aria-hidden="true"></span>
		<?php
		if ( ! empty( $detail_reviews ) ) :
			$avg = (float) $detail_review_summary['avg'];
			$cnt = (int) $detail_review_summary['total'];
			?>
			<div class="listora-detail__reviews-summary">
				<div class="listora-detail__reviews-score">
					<span class="listora-detail__reviews-avg"><?php echo esc_html( number_format( $avg, 1 ) ); ?></span>
					<div class="listora-detail__reviews-stars">
						<?php for ( $star = 1; $star <= 5; $star++ ) : ?>
						<svg class="listora-rating__star<?php echo esc_attr( $star > round( $avg ) ? ' listora-rating__star--empty' : '' ); ?>" viewBox="0 0 24 24" aria-hidden="true" width="20" height="20"><path fill="currentColor" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
						<?php endfor; ?>
					</div>
					<span class="listora-detail__reviews-total">
						<?php
						/* translators: %d: number of reviews */
						printf( esc_html( _n( '%d review', '%d reviews', $cnt, 'wb-listora' ) ), $cnt ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- integer used with %d format specifier; format string is esc_html-wrapped.
						?>
					</span>
				</div>
				<div class="listora-detail__reviews-bars">
					<?php
					for ( $bar = 5; $bar >= 1; $bar-- ) :
						$bar_count = (int) ( $detail_review_summary['dist'][ $bar ] ?? 0 );
						$bar_pct   = $cnt > 0 ? round( $bar_count / $cnt * 100 ) : 0;
						?>
					<div class="listora-detail__reviews-bar-row">
						<span><?php echo esc_html( $bar ); ?></span>
						<div class="listora-detail__reviews-bar"><div style="width:<?php echo esc_attr( $bar_pct ); ?>%"></div></div>
						<span><?php echo esc_html( $bar_count ); ?></span>
					</div>
					<?php endfor; ?>
				</div>
			</div>

			<div class="listora-detail__reviews-list">
				<?php
				// Resolve the OLDEST review the owner hasn't replied to yet. The
				// review_reminder email (wave-2 email-optout) deep-links owners to
				// "#oldest-unanswered" so the reminder lands on the review that has
				// waited longest. $detail_reviews is newest-first (see docblock), so
				// the last unanswered row in iteration order is the oldest. Compute
				// the id once; the loop renders the extra anchor on the match.
				$listora_oldest_unanswered_id = 0;
				foreach ( $detail_reviews as $listora_rev_scan ) {
					if ( empty( $listora_rev_scan['owner_reply'] ) ) {
						$listora_oldest_unanswered_id = (int) $listora_rev_scan['id'];
					}
				}
				foreach ( $detail_reviews as $rev ) :
					$reviewer = get_user_by( 'id', $rev['user_id'] );
					// Shared resolver, so this page agrees with the REST list and
					// the standalone reviews block. A deleted account reads
					// "Former member"; only eraser-anonymised rows (user_id 0)
					// read "Anonymous" (BC 10185681930). This is the surface the
					// card actually reported, and it was the one my first pass
					// missed — it renders reviews itself rather than reusing
					// listing-reviews/review-card.php.
					$rev_name                 = wb_listora_review_author_name( (int) $rev['user_id'] );
					$rev_avatar               = $reviewer ? get_avatar_url( $rev['user_id'], array( 'size' => 48 ) ) : '';
					$rev_user_id              = $reviewer ? (int) $reviewer->ID : 0;
					$rev_profile_url          = $rev_user_id ? (string) apply_filters( 'wb_listora_member_profile_url', '', $rev_user_id, 'review_user' ) : '';
					$rev_is_oldest_unanswered = ( $listora_oldest_unanswered_id > 0 && (int) $rev['id'] === $listora_oldest_unanswered_id );
					?>
					<?php if ( $rev_is_oldest_unanswered ) : ?>
				<span id="oldest-unanswered" class="listora-detail__review-anchor" aria-hidden="true"></span>
					<?php endif; ?>
				<div class="listora-detail__review" id="review-<?php echo esc_attr( (string) (int) $rev['id'] ); ?>">
					<div class="listora-detail__review-header">
						<?php if ( $rev_avatar ) : ?>
						<img src="<?php echo esc_url( $rev_avatar ); ?>" alt="<?php echo esc_attr( $rev_name ); ?>" class="listora-detail__review-avatar" width="40" height="40" loading="lazy" />
						<?php endif; ?>
						<div>
							<?php if ( $rev_profile_url ) : ?>
							<a class="listora-detail__review-author listora-detail__review-author--link" href="<?php echo esc_url( $rev_profile_url ); ?>"><?php echo esc_html( $rev_name ); ?></a>
							<?php else : ?>
							<strong class="listora-detail__review-author"><?php echo esc_html( $rev_name ); ?></strong>
							<?php endif; ?>
							<div class="listora-detail__review-meta">
								<span class="listora-rating">
									<?php for ( $rs = 1; $rs <= 5; $rs++ ) : ?>
									<svg class="listora-rating__star<?php echo esc_attr( $rs > (int) $rev['overall_rating'] ? ' listora-rating__star--empty' : '' ); ?>" viewBox="0 0 24 24" aria-hidden="true" width="14" height="14"><path fill="currentColor" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
									<?php endfor; ?>
								</span>
								<time datetime="<?php echo esc_attr( $rev['created_at'] ); ?>"><?php echo esc_html( human_time_diff( strtotime( $rev['created_at'] ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'wb-listora' ) ); ?></time>
							</div>
						</div>
					</div>
					<?php if ( ! empty( $rev['title'] ) ) : ?>
					<h4 class="listora-detail__review-title"><?php echo esc_html( $rev['title'] ); ?></h4>
					<?php endif; ?>
					<p class="listora-detail__review-content"><?php echo esc_html( $rev['content'] ); ?></p>
					<?php
					/**
					 * Fires after the review content text, inside the review item.
					 *
					 * Pro renders review photo thumbnails here.
					 *
					 * This tab renders its own review markup instead of reusing
					 * listing-reviews/review-card.php, and it fired only the
					 * review-FORM hook. So on the listing detail page - the most
					 * visited review surface in the product - photo uploads
					 * worked and the photos were never shown back. The same
					 * split is why the "Former member" fix had to be applied
					 * twice; anything added to review-card.php has to be
					 * mirrored here until the two share one partial.
					 *
					 * @param array $review Current review row from the database.
					 */
					do_action( 'wb_listora_review_after_content', $rev );
					?>
					<?php if ( ! empty( $rev['owner_reply'] ) ) : ?>
					<div class="listora-detail__review-reply">
						<strong><?php esc_html_e( 'Owner Response:', 'wb-listora' ); ?></strong>
						<p><?php echo esc_html( $rev['owner_reply'] ); ?></p>
					</div>
					<?php endif; ?>
					<?php
					// Helpful-vote control. Mirrors the listing-reviews block —
					// previously only the count was shown here, no button (Basecamp
					// 9842891993). Anonymous visitors see a disabled button with a
					// "log in to vote" affordance via the existing actions.voteReviewHelpful
					// handler which prompts login when not authenticated.
					?>
					<div class="listora-detail__review-actions" data-wp-context='<?php echo esc_attr( wp_json_encode( array( 'reviewId' => (int) $rev['id'] ) ) ); ?>'>
						<button
							type="button"
							class="listora-detail__review-helpful-btn"
							data-wp-on--click="actions.voteReviewHelpful"
							aria-label="<?php esc_attr_e( 'Mark as helpful', 'wb-listora' ); ?>"
						>
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
								<path d="M7 10v12"/>
								<path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2a3.13 3.13 0 0 1 3 3.88Z"/>
							</svg>
							<span><?php esc_html_e( 'Helpful', 'wb-listora' ); ?></span>
							<?php if ( (int) $rev['helpful_count'] > 0 ) : ?>
							<span class="listora-detail__review-helpful-count">(<?php echo esc_html( (int) $rev['helpful_count'] ); ?>)</span>
							<?php endif; ?>
						</button>
						<?php
						/*
						 * Block control (BC 10185681658). The REST route and the
						 * read-side filtering already existed; nothing on the web
						 * called them, so the feature was app-only.
						 *
						 * Rendered only where blocking is possible: logged in, a
						 * real reviewer id (eraser-anonymised and orphaned rows
						 * have nobody to block), not your own review, and not
						 * someone already blocked. Blocking is symmetric and
						 * removes the review from BOTH this list and the headline
						 * count above it — they agree because get_rating_summary()
						 * became block-aware in the same cycle (BC 10185680640).
						 */
						$listora_viewer_id = get_current_user_id();
						if (
							$listora_viewer_id
							&& $rev_user_id
							&& $rev_user_id !== (int) $listora_viewer_id
							&& function_exists( 'wb_listora_hidden_review_authors' )
							&& ! in_array( $rev_user_id, wb_listora_hidden_review_authors(), true )
						) :
							?>
						<button
							type="button"
							class="listora-detail__review-block-btn"
							data-wp-on--click="actions.blockReviewAuthor"
							data-wp-context='
							<?php
							echo esc_attr(
								wp_json_encode(
									array(
										'blockUserId'   => $rev_user_id,
										'blockUserName' => $rev_name,
									)
								)
							);
							?>
							'
							aria-label="
							<?php
							/* translators: %s: reviewer display name. */
							echo esc_attr( sprintf( __( 'Block %s', 'wb-listora' ), $rev_name ) );
							?>
							"
						>
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
								<circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/>
							</svg>
							<span><?php esc_html_e( 'Block', 'wb-listora' ); ?></span>
						</button>
						<?php endif; ?>

						<?php
						/*
						 * Report control.
						 *
						 * `actions.showReportModal` and the whole report flow
						 * already existed, and NOTHING called them: the trigger
						 * lived only in the standalone listing-reviews block,
						 * which the canonical listing page does not render — it
						 * renders this list. So reporting a review was
						 * unreachable on the page members actually use, and
						 * fixing the reason enum alone did not surface it
						 * (BC 10154926676).
						 *
						 * Same guards as Block above: logged out gets the login
						 * modal from the action itself, and you cannot report
						 * your own review. `reviewId` comes from the context
						 * already on this actions wrapper.
						 */
						if ( ! $listora_viewer_id || $rev_user_id !== (int) $listora_viewer_id ) :
							?>
						<button
							type="button"
							class="listora-detail__review-report-btn"
							data-wp-on--click="actions.showReportModal"
							aria-label="<?php esc_attr_e( 'Report this review', 'wb-listora' ); ?>"
						>
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
								<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/>
							</svg>
							<span><?php esc_html_e( 'Report', 'wb-listora' ); ?></span>
						</button>
						<?php endif; ?>

						<?php if ( (int) $rev['helpful_count'] > 0 ) : ?>
						<span class="listora-detail__review-helpful-summary">
							<?php
							/* translators: %d: number of people */
							printf( esc_html( _n( '%d person found this helpful', '%d people found this helpful', (int) $rev['helpful_count'], 'wb-listora' ) ), (int) $rev['helpful_count'] );
							?>
						</span>
						<?php endif; ?>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="listora-detail__reviews-empty">
				<p><?php esc_html_e( 'No reviews yet. Be the first to share your experience!', 'wb-listora' ); ?></p>
			</div>
		<?php endif; ?>

		<?php
		// Card 9895809632 — gate the entire write-review surface on the
		// site-wide Reviews & Ratings feature setting. Without this check,
		// users could still see the "Write a Review" button + form even
		// after admin disabled reviews; submission would then 403 at the
		// REST layer with no explanation. Hide the form too.
		$listora_reviews_enabled = function_exists( 'wb_listora_feature_enabled' )
			? wb_listora_feature_enabled( 'reviews' )
			: true;
		?>

		<?php // Review Form. ?>
		<?php if ( $listora_reviews_enabled && ! empty( $detail_review_pending ) ) : ?>
		<p class="listora-reviews__pending-notice" role="status">
			<?php esc_html_e( 'You have already submitted a review for this listing. It is waiting for moderation and will appear once approved.', 'wb-listora' ); ?>
		</p>
		<?php endif; ?>
		<?php if ( $listora_reviews_enabled && ! $detail_user_reviewed && ! $detail_is_owner && is_user_logged_in() ) : ?>
		<button type="button" class="listora-btn listora-btn--primary listora-reviews__write-btn" data-wp-on--click="actions.toggleDetailReviewForm">
			<?php esc_html_e( 'Write a Review', 'wb-listora' ); ?>
		</button>
		<?php endif; ?>

		<?php if ( $listora_reviews_enabled && ! $detail_user_reviewed && ! $detail_is_owner ) : ?>
		<div class="listora-reviews__form-wrapper" id="listora-detail-review-form" hidden>
			<?php if ( ! is_user_logged_in() ) : ?>
			<p class="listora-reviews__login-notice">
				<a href="<?php echo esc_url( wp_login_url( get_permalink() . '#reviews' ) ); ?>"><?php esc_html_e( 'Log in', 'wb-listora' ); ?></a>
				<?php esc_html_e( 'to write a review.', 'wb-listora' ); ?>
			</p>
			<?php else : ?>
				<?php
				// Honor the configured reviews.min_length so the client form matches
				// the REST validation in Reviews_Controller::create_review(). A value
				// of 0 means no written feedback is required (rating-only reviews), so
				// the textarea drops `required` + `minlength`. Default 20 mirrors the
				// admin field default at class-settings-page.php:1705. Mirrors the
				// sibling listing-reviews/review-form.php form.
				$detail_review_settings = function_exists( 'wb_listora_get_setting' ) ? wb_listora_get_setting( 'reviews', array() ) : array();
				if ( ! is_array( $detail_review_settings ) ) {
					$detail_review_settings = array();
				}
				$detail_review_min_length = isset( $detail_review_settings['min_length'] ) ? absint( $detail_review_settings['min_length'] ) : 20;
				$detail_review_required   = $detail_review_min_length > 0;
				?>
			<form class="listora-reviews__form" data-wp-on--submit="actions.submitDetailReviewForm">
				<h3><?php esc_html_e( 'Write a Review', 'wb-listora' ); ?></h3>

				<div class="listora-submission__field">
					<label class="listora-submission__label"><?php esc_html_e( 'Your Rating', 'wb-listora' ); ?> <span class="required">*</span></label>
					<fieldset class="listora-reviews__star-input" role="radiogroup" aria-label="<?php esc_attr_e( 'Rating', 'wb-listora' ); ?>">
						<?php for ( $s = 1; $s <= 5; $s++ ) : ?>
						<label class="listora-reviews__star-label">
							<input type="radio" name="overall_rating" value="<?php echo esc_attr( $s ); ?>" required />
							<svg viewBox="0 0 24 24" width="28" height="28" class="listora-reviews__star-svg">
								<path fill="currentColor" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
							</svg>
							<?php /* translators: %d: number of stars */ ?>
							<span class="listora-sr-only"><?php echo esc_html( $s ); ?> <?php echo esc_html( _n( 'star', 'stars', $s, 'wb-listora' ) ); ?></span>
						</label>
						<?php endfor; ?>
					</fieldset>
				</div>

				<div class="listora-submission__field">
					<label for="listora-detail-review-title" class="listora-submission__label"><?php esc_html_e( 'Review Title', 'wb-listora' ); ?> <span class="required">*</span></label>
					<input type="text" id="listora-detail-review-title" name="title" class="listora-input" required
						placeholder="<?php esc_attr_e( 'Summarize your experience', 'wb-listora' ); ?>" />
				</div>

				<div class="listora-submission__field">
					<label for="listora-detail-review-content" class="listora-submission__label">
						<?php esc_html_e( 'Your Review', 'wb-listora' ); ?>
						<?php if ( $detail_review_required ) : ?>
						<span class="required">*</span>
						<?php endif; ?>
					</label>
					<textarea id="listora-detail-review-content" name="content" class="listora-input listora-submission__textarea" rows="5"
						<?php if ( $detail_review_required ) : ?>
						required minlength="<?php echo esc_attr( $detail_review_min_length ); ?>"
						<?php endif; ?>
						placeholder="
						<?php
						echo esc_attr(
							$detail_review_required
								/* translators: %d: minimum number of characters required for a review. */
								? sprintf( _n( 'Share your experience (minimum %d character)', 'Share your experience (minimum %d characters)', $detail_review_min_length, 'wb-listora' ), $detail_review_min_length )
								: __( 'Share your experience (optional)', 'wb-listora' )
						);
						?>
						"></textarea>
				</div>

				<?php
				/** This action is documented in blocks/listing-reviews/render.php */
				do_action( 'wb_listora_review_form_after_content', $post_id );
				?>

				<?php
				/** This filter is documented in blocks/listing-reviews/render.php */
				$detail_listing_type_slug = '';
				$detail_listing_type_obj  = \WBListora\Core\Listing_Type_Registry::instance()->get_for_post( $post_id );
				if ( $detail_listing_type_obj ) {
					$detail_listing_type_slug = $detail_listing_type_obj->get_slug();
				}
				// Shared helper — feeds the type's SAVED criteria into the
				// filter as its base, and enforces the {key,label} item shape
				// so a listener returning strings or a scalar cannot fatal the
				// offset reads below (same PHP 8 bug class as the
				// field-options fatal).
				$detail_review_criteria = wb_listora_get_review_criteria( $detail_listing_type_slug );

				if ( ! empty( $detail_review_criteria ) ) :
					?>
				<div class="listora-reviews__criteria">
					<label class="listora-submission__label"><?php esc_html_e( 'Rate each aspect', 'wb-listora' ); ?></label>
					<?php foreach ( $detail_review_criteria as $criterion ) : ?>
					<div class="listora-reviews__criterion">
						<span class="listora-reviews__criterion-label"><?php echo esc_html( $criterion['label'] ); ?></span>
						<fieldset class="listora-reviews__star-input listora-reviews__star-input--small" role="radiogroup"
							aria-label="<?php echo esc_attr( $criterion['label'] ); ?>">
							<?php for ( $cs = 1; $cs <= 5; $cs++ ) : ?>
							<label class="listora-reviews__star-label">
								<input type="radio" name="criteria_ratings[<?php echo esc_attr( $criterion['key'] ); ?>]" value="<?php echo esc_attr( $cs ); ?>" />
								<svg viewBox="0 0 24 24" width="20" height="20" class="listora-reviews__star-svg">
									<path fill="currentColor" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
								</svg>
								<?php /* translators: %d: number of stars */ ?>
								<span class="listora-sr-only"><?php echo esc_html( $cs ); ?> <?php echo esc_html( _n( 'star', 'stars', $cs, 'wb-listora' ) ); ?></span>
							</label>
							<?php endfor; ?>
						</fieldset>
					</div>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>

				<?php \WBListora\Captcha::render_widget( 'review-detail' ); ?>

				<div class="listora-reviews__form-actions">
					<button type="submit" class="listora-btn listora-btn--primary"><?php esc_html_e( 'Submit Review', 'wb-listora' ); ?></button>
					<button type="button" class="listora-btn listora-btn--text" data-wp-on--click="actions.toggleDetailReviewForm"><?php esc_html_e( 'Cancel', 'wb-listora' ); ?></button>
				</div>

				<div class="listora-reviews__form-message" hidden></div>
			</form>
			<?php endif; ?>
		</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php // The map is the sidebar Location card (card 10337187661), not a tab. ?>
</div>
<?php
do_action( 'wb_listora_after_detail_tabs', $view_data );
