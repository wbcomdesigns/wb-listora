<?php
/**
 * Template helper functions used by block render.php files.
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wb_listora_locate_template' ) ) {

	/**
	 * Locate a template file, checking the theme first for overrides.
	 *
	 * Theme/child-theme can override any plugin template by placing a copy in:
	 *   wp-content/themes/{theme}/wb-listora/{template_name}
	 *
	 * @param string $template_name Template file name (e.g. 'emails/listing-submitted.php').
	 * @param string $template_path Theme subdirectory to search in. Default 'wb-listora/'.
	 * @param string $default_path  Absolute path to plugin templates directory.
	 * @return string Full path to the located template file.
	 */
	function wb_listora_locate_template( $template_name, $template_path = '', $default_path = '' ) {
		// Defense-in-depth: never let a template name escape the theme
		// override dir or the plugin templates dir. All internal callers pass
		// hard-coded relative names (e.g. 'emails/listing-submitted.php'), but
		// a '..' segment reaching this function would resolve to an arbitrary
		// file that wb_listora_get_template() then include()s. Strip any
		// parent-directory traversal and leading slashes while preserving the
		// legitimate sub-directory structure (slashes without '..').
		$template_name = str_replace( '\\', '/', (string) $template_name );
		if ( false !== strpos( $template_name, '..' ) ) {
			$safe_segments = array();
			foreach ( explode( '/', $template_name ) as $segment ) {
				if ( '' === $segment || '.' === $segment || '..' === $segment ) {
					continue;
				}
				$safe_segments[] = $segment;
			}
			$template_name = implode( '/', $safe_segments );
		}
		$template_name = ltrim( $template_name, '/' );

		if ( ! $template_path ) {
			$template_path = 'wb-listora/';
		}
		if ( ! $default_path ) {
			$default_path = WB_LISTORA_PLUGIN_DIR . 'templates/';
		}

		// Look in theme/child-theme first.
		$template = locate_template(
			array(
				trailingslashit( $template_path ) . $template_name,
				$template_name,
			)
		);

		// Fall back to plugin templates directory.
		if ( ! $template ) {
			$template = trailingslashit( $default_path ) . $template_name;
		}

		/**
		 * Filter the located template path.
		 *
		 * @param string $template      Full path to located template.
		 * @param string $template_name Relative template name.
		 * @param string $template_path Theme subdirectory path.
		 */
		return apply_filters( 'wb_listora_locate_template', $template, $template_name, $template_path );
	}
}

if ( ! function_exists( 'wb_listora_get_template' ) ) {

	/**
	 * Load a template file with variable extraction.
	 *
	 * Locates the template (theme override or plugin default) and includes it
	 * with the provided arguments extracted into template scope.
	 *
	 * @param string $template_name Template file name (e.g. 'emails/listing-submitted.php').
	 * @param array  $args          Variables to extract into template scope.
	 * @param string $template_path Theme subdirectory to search in. Default 'wb-listora/'.
	 * @param string $default_path  Absolute path to plugin templates directory.
	 */
	function wb_listora_get_template( $template_name, $args = array(), $template_path = '', $default_path = '' ) {
		$template = wb_listora_locate_template( $template_name, $template_path, $default_path );

		if ( ! file_exists( $template ) ) {
			return;
		}

		/**
		 * Filter template arguments before rendering.
		 *
		 * @param array  $args          Template variables.
		 * @param string $template_name Relative template name.
		 */
		$args = apply_filters( 'wb_listora_template_args', $args, $template_name );

		// Templates read values either as extract()'s flat variables or via
		// $view_data[...], so define $view_data here for both forms to resolve.
		// An explicit 'view_data' key in $args still wins (extract() overwrites).
		$view_data = array();

		if ( ! empty( $args ) && is_array( $args ) ) {
			$view_data = $args;
			extract( $args ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- template variables are the documented args array of this loader.
		}

		do_action( 'wb_listora_before_template', $template_name, $template_path, $args );
		include $template;
		do_action( 'wb_listora_after_template', $template_name, $template_path, $args );
	}
}

if ( ! function_exists( 'wb_listora_get_template_html' ) ) {

	/**
	 * Like wb_listora_get_template() but returns the HTML as a string.
	 *
	 * @param string $template_name Template file name.
	 * @param array  $args          Variables to extract into template scope.
	 * @param string $template_path Theme subdirectory to search in.
	 * @param string $default_path  Absolute path to plugin templates directory.
	 * @return string Rendered HTML.
	 */
	function wb_listora_get_template_html( $template_name, $args = array(), $template_path = '', $default_path = '' ) {
		ob_start();
		wb_listora_get_template( $template_name, $args, $template_path, $default_path );
		return ob_get_clean();
	}
}

if ( ! function_exists( 'wb_listora_placeholder_url' ) ) {

	/**
	 * Get the URL of the default placeholder image.
	 *
	 * Bundled SVG placeholder ensures cards never show broken images.
	 * Themes can override: {theme}/wb-listora/images/placeholder.svg
	 *
	 * @return string Placeholder image URL.
	 */
	function wb_listora_placeholder_url() {
		$theme_file = get_stylesheet_directory() . '/wb-listora/images/placeholder.svg';
		if ( file_exists( $theme_file ) ) {
			$url = get_stylesheet_directory_uri() . '/wb-listora/images/placeholder.svg';
		} else {
			$url = WB_LISTORA_PLUGIN_URL . 'assets/images/placeholder.svg';
		}

		return apply_filters( 'wb_listora_placeholder_url', $url );
	}
}

if ( ! function_exists( 'wb_listora_resolve_term_id' ) ) {

	/**
	 * Resolve a taxonomy term reference (slug or numeric ID) to a term ID.
	 *
	 * Used by the listing-grid block to translate `?category=italian` and
	 * `?category=42` URLs into the term IDs that {@see Search_Engine}
	 * expects. Accepting both keeps URLs human-readable for end users
	 * while still working when callers already have a term ID.
	 *
	 * @param string $value    Slug or numeric term ID. Empty string returns 0.
	 * @param string $taxonomy Taxonomy name.
	 * @return int Term ID, or 0 when the value is empty / unknown.
	 */
	function wb_listora_resolve_term_id( $value, $taxonomy ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return 0;
		}

		if ( ctype_digit( $value ) ) {
			$term_id = (int) $value;
			$term    = get_term( $term_id, $taxonomy );
			return ( $term && ! is_wp_error( $term ) ) ? $term_id : 0;
		}

		$term = get_term_by( 'slug', $value, $taxonomy );
		return $term ? (int) $term->term_id : 0;
	}
}

/**
 * Timestamp for a date or date-time a member typed, read in the site's timezone.
 *
 * Listing dates are stored as the local wall time ('2026-11-01' or '2026-11-01T09:00').
 * strtotime() reads them as UTC, so wp_date() then showed 09:00 as 04:00 in New York
 * and a date as the day before on every site west of UTC.
 *
 * @since 1.9.1
 *
 * @param string $value Stored date or date-time.
 * @return int|false Unix timestamp, or false when the value is not a date.
 */
function wb_listora_local_timestamp( $value ) {
	$date = date_create_immutable( (string) $value, wp_timezone() );
	return $date ? $date->getTimestamp() : false;
}

/**
 * Where a Listora page lives when no page is mapped: its registered default slug.
 *
 * @since 1.9.1
 *
 * @param string $key Page_Registry key (directory, submission, dashboard, ...).
 * @return string
 */
function wb_listora_default_page_url( $key ) {
	$config = \WBListora\Core\Page_Registry::get_config( (string) $key );
	$slug   = isset( $config['default_slug'] ) ? (string) $config['default_slug'] : (string) $key;
	return home_url( '/' . ltrim( $slug, '/' ) . '/' );
}

if ( ! function_exists( 'wb_listora_get_directory_url' ) ) {

	/**
	 * Canonical "browse the directory" URL.
	 *
	 * Used by dashboard empty-state CTAs, upgrade prompts, and anywhere
	 * the plugin needs to point users at the public directory page.
	 * Filterable so custom setups can point to a different slug or
	 * a full archive URL.
	 *
	 * @return string
	 */
	function wb_listora_get_directory_url() {
		$resolved = wb_listora_get_page_url( 'directory' );
		if ( '' === $resolved ) {
			$resolved = wb_listora_default_page_url( 'directory' );
		}

		return (string) apply_filters( 'wb_listora_directory_url', $resolved );
	}
}

if ( ! function_exists( 'wb_listora_get_submit_url' ) ) {

	/**
	 * Canonical "submit a listing" URL.
	 *
	 * @return string
	 */
	function wb_listora_get_submit_url() {
		$resolved = wb_listora_get_page_url( 'submission' );
		if ( '' === $resolved ) {
			$resolved = wb_listora_default_page_url( 'submission' );
		}

		return (string) apply_filters( 'wb_listora_submit_url', $resolved );
	}
}

if ( ! function_exists( 'wb_listora_get_dashboard_url' ) ) {

	/**
	 * Canonical user dashboard URL (frontend). Falls back to configured
	 * setting, then to /dashboard/. Filterable.
	 *
	 * @param string $tab_hash Optional tab hash fragment (e.g. "claims").
	 * @return string
	 */
	function wb_listora_get_dashboard_url( $tab_hash = '' ) {
		$resolved = wb_listora_get_page_url( 'dashboard' );
		if ( '' === $resolved ) {
			$resolved = wb_listora_default_page_url( 'dashboard' );
		}

		$resolved = (string) apply_filters( 'wb_listora_dashboard_url', $resolved );

		if ( $tab_hash ) {
			$resolved = trailingslashit( $resolved ) . '#' . ltrim( $tab_hash, '#' );
		}

		return $resolved;
	}
}

if ( ! function_exists( 'wb_listora_get_submission_return_url' ) ) {

	/**
	 * The listing a member left to come and buy credits, if any.
	 *
	 * The submission wizard saves a draft and passes checkout a `listora_return`
	 * URL so the member can get back to their plan step. The value comes from
	 * the query string, so it goes through wp_validate_redirect() with an empty
	 * fallback: anything off-site returns '' (no open redirect).
	 *
	 * @since 1.7.0
	 *
	 * @return string Same-site return URL, or '' when there is none.
	 */
	function wb_listora_get_submission_return_url() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation hint; validated below and never acted on without a click.
		$raw = isset( $_GET['listora_return'] ) ? esc_url_raw( wp_unslash( $_GET['listora_return'] ) ) : '';
		if ( '' === $raw ) {
			return '';
		}

		return (string) wp_validate_redirect( $raw, '' );
	}
}

if ( ! function_exists( 'wb_listora_format_credits' ) ) {

	/**
	 * A credit amount for display: "10", "12.5", never "10.00".
	 *
	 * Credits are a count, not money, so trailing zeros read as a price. Up to
	 * two decimals are kept when present, in the site's number format.
	 *
	 * @since 1.9.0
	 *
	 * @param float|int|string $credits Credit amount.
	 * @return string
	 */
	function wb_listora_format_credits( $credits ) {
		$credits  = round( (float) $credits, 2 );
		$decimals = ( abs( $credits - round( $credits ) ) < 0.005 ) ? 0 : ( abs( $credits * 10 - round( $credits * 10 ) ) < 0.05 ? 1 : 2 );

		return number_format_i18n( $credits, $decimals );
	}
}

if ( ! function_exists( 'wb_listora_get_credits_return_url' ) ) {

	/**
	 * Where a payment gateway sends a member back after buying credits.
	 *
	 * The dashboard's Credits tab, carrying any `listora_return` URL so the tab
	 * can offer the way back to the member's saved listing.
	 *
	 * @since 1.9.0
	 *
	 * @return string Credits-tab URL, or '' when no dashboard page resolves.
	 */
	function wb_listora_get_credits_return_url() {
		$args = array( 'tab' => 'credits' );

		$back = wb_listora_get_submission_return_url();
		if ( '' !== $back ) {
			// add_query_arg() does not encode values, and this one is a URL.
			$args['listora_return'] = rawurlencode( $back );
		}

		$url = function_exists( 'wb_listora_get_public_page_url' )
			? (string) wb_listora_get_public_page_url( 'dashboard', $args )
			: '';

		if ( '' === $url ) {
			$permalink = (string) get_permalink();
			$url       = '' !== $permalink ? add_query_arg( $args, $permalink ) : '';
		}

		return $url;
	}
}

if ( ! function_exists( 'wb_listora_is_setup_complete' ) ) {

	/**
	 * Whether the first-run setup wizard has been completed.
	 *
	 * The one answer for every Free and Pro guard. Checks the top-level
	 * `wb_listora_setup_complete` flag and the legacy nested
	 * `wb_listora_settings.setup_complete` value.
	 *
	 * @return bool
	 */
	function wb_listora_is_setup_complete() {
		$option = get_option( 'wb_listora_setup_complete', null );
		if ( '1' === (string) $option || true === $option ) {
			return true;
		}

		return ! empty( wb_listora_get_setting( 'setup_complete' ) );
	}
}

if ( ! function_exists( 'wb_listora_dashboard_tab_available' ) ) {

	/**
	 * Whether a dashboard tab is switched on for this site.
	 *
	 * The site-wide half of "can this member see the tab" (the block's show*
	 * attributes are the other half). Shared by the dashboard block and the
	 * page title, so a ?tab= for a switched-off tab is neither opened nor named.
	 *
	 * @since 1.9.0
	 *
	 * @param string $tab Tab key.
	 * @return bool
	 */
	function wb_listora_dashboard_tab_available( string $tab ): bool {
		switch ( $tab ) {
			case 'credits':
				return wb_listora_should_show_member_credit_record();
			case 'claims':
			case 'favorites':
			case 'reviews':
				return wb_listora_feature_enabled( $tab );
			default:
				return true;
		}
	}
}

if ( ! function_exists( 'wb_listora_get_dashboard_tab_labels' ) ) {

	/**
	 * Label for every member-dashboard tab, keyed by tab id.
	 *
	 * Shared by the sidebar and the document title so they always agree.
	 *
	 * @since 1.6.0
	 *
	 * @return array<string,string> Tab id => translated label.
	 */
	function wb_listora_get_dashboard_tab_labels() {
		$labels = array(
			'overview'  => __( 'Overview', 'wb-listora' ),
			'listings'  => __( 'My Listings', 'wb-listora' ),
			'reviews'   => __( 'Reviews', 'wb-listora' ),
			'favorites' => __( 'Favorites', 'wb-listora' ),
			'claims'    => __( 'My Claims', 'wb-listora' ),
			'credits'   => __( 'Credits', 'wb-listora' ),
			'profile'   => __( 'Profile', 'wb-listora' ),
		);

		/**
		 * Filter the member-dashboard tab labels.
		 *
		 * Applies to the sidebar AND the document title together, so a renamed
		 * tab cannot end up announced under its old name.
		 *
		 * @since 1.6.0
		 *
		 * @param array<string,string> $labels Tab id => label.
		 */
		return (array) apply_filters( 'wb_listora_dashboard_tab_labels', $labels );
	}
}

if ( ! function_exists( 'wb_listora_get_purchasable_credit_packs' ) ) {

	/**
	 * Billing details a member must give before a Stripe or PayPal credit purchase.
	 *
	 * The credits SDK refuses to start a checkout until the buyer's required
	 * billing fields are on their account (they go on the order and receipt).
	 * Renders nothing when they are already complete. Both buy surfaces call
	 * this above their pack cards - the dashboard Credits tab and Pro's Credit
	 * Purchase block - and `assets/js/credit-billing.js` reveals the form on
	 * the first Buy click and hands the values to the checkout request.
	 *
	 * @since 1.9.0
	 *
	 * @return void
	 */
	function wb_listora_render_credit_billing_form() {
		$user_id = get_current_user_id();
		if ( ! $user_id
			|| ! is_callable( array( '\\Wbcom\\Credits\\Billing', 'fields' ) )
			|| ! is_callable( array( '\\Wbcom\\Credits\\Billing', 'missing' ) )
			|| ! is_callable( array( '\\Wbcom\\Credits\\Support\\Countries', 'all' ) ) ) {
			return;
		}

		$slug   = 'wb-listora';
		$values = \Wbcom\Credits\Billing::get( $user_id, $slug );
		if ( ! \Wbcom\Credits\Billing::missing( $values, $slug ) ) {
			return;
		}

		// Listora's own wording: the SDK's labels live in its text domain.
		$labels = array(
			'billing_first_name' => __( 'First name', 'wb-listora' ),
			'billing_last_name'  => __( 'Last name', 'wb-listora' ),
			'billing_email'      => __( 'Email', 'wb-listora' ),
			'billing_company'    => __( 'Company (optional)', 'wb-listora' ),
			'billing_gst'        => __( 'VAT / GST number (optional)', 'wb-listora' ),
			'billing_address_1'  => __( 'Street address', 'wb-listora' ),
			'billing_address_2'  => __( 'Apartment, suite, etc. (optional)', 'wb-listora' ),
			'billing_city'       => __( 'Town / City', 'wb-listora' ),
			'billing_state'      => __( 'State / County', 'wb-listora' ),
			'billing_postcode'   => __( 'Postcode / ZIP', 'wb-listora' ),
			'billing_country'    => __( 'Country', 'wb-listora' ),
		);

		wp_enqueue_script( 'listora-credit-billing', WB_LISTORA_PLUGIN_URL . 'assets/js/credit-billing.js', array(), WB_LISTORA_VERSION, true );
		?>
		<fieldset class="listora-credit-billing" data-listora-credit-billing hidden
			data-required-text="<?php esc_attr_e( 'Please fill in the highlighted fields.', 'wb-listora' ); ?>">
			<legend class="listora-credit-billing__title"><?php esc_html_e( 'Billing details', 'wb-listora' ); ?></legend>
			<p class="listora-credit-billing__intro"><?php esc_html_e( 'We need these once for your receipt. They are saved to your account. Fill them in, then choose Buy again.', 'wb-listora' ); ?></p>
			<p class="listora-credit-billing__error" role="alert" hidden></p>
			<div class="listora-credit-billing__grid">
				<?php
				foreach ( \Wbcom\Credits\Billing::fields( $slug ) as $key => $field ) :
					$field_id = 'listora-' . str_replace( '_', '-', $key );
					$required = ! empty( $field['required'] );
					$label    = $labels[ $key ] ?? (string) ( $field['label'] ?? $key );
					?>
					<div class="listora-credit-billing__field">
						<label class="listora-credit-billing__label" for="<?php echo esc_attr( $field_id ); ?>"<?php echo $required ? ' data-required' : ''; ?>><?php echo esc_html( $label ); ?></label>
						<?php if ( 'country' === ( $field['type'] ?? '' ) ) : ?>
							<select class="listora-input" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $key ); ?>" autocomplete="country"<?php echo $required ? ' required' : ''; ?>>
								<option value=""><?php esc_html_e( 'Select a country', 'wb-listora' ); ?></option>
								<?php foreach ( \Wbcom\Credits\Support\Countries::all() as $code => $name ) : ?>
									<option value="<?php echo esc_attr( (string) $code ); ?>"<?php selected( (string) ( $values[ $key ] ?? '' ), (string) $code ); ?>><?php echo esc_html( (string) $name ); ?></option>
								<?php endforeach; ?>
							</select>
						<?php else : ?>
							<input class="listora-input" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $key ); ?>"
								type="<?php echo esc_attr( 'email' === ( $field['type'] ?? '' ) ? 'email' : 'text' ); ?>"
								value="<?php echo esc_attr( (string) ( $values[ $key ] ?? '' ) ); ?>"
								autocomplete="<?php echo esc_attr( (string) ( $field['autocomplete'] ?? 'on' ) ); ?>"<?php echo $required ? ' required' : ''; ?> />
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * What a member can ACTUALLY buy, resolved once for every surface.
	 *
	 * Credit mappings (WooCommerce / PMPro / MemberPress items with a buy URL)
	 * come first; the abstract `wb_listora_pro_credit_packs` rows are appended
	 * only when a direct gateway can sell them.
	 *
	 * @since 1.6.0
	 * @since 1.9.0 Packs with no gateway are left out unless $include_unbuyable.
	 *
	 * @param bool $include_unbuyable Also return packs with no checkout yet (admin screens).
	 * @return array<int, array<string, mixed>> Packs with adapter, item_id, item_label,
	 *                                          credits, price_html, buy_url, buy_label.
	 */
	function wb_listora_get_purchasable_credit_packs( $include_unbuyable = false ) {
		$packs = array();

		// One builder for every buy surface, so each pack gets the checkout its
		// adapter actually supports.
		$gateways = array();
		if ( class_exists( '\\Wbcom\\Credits\\Gateways\\Gateway_Registry' ) ) {
			foreach ( \Wbcom\Credits\Gateways\Gateway_Registry::for_slug( 'wb-listora' )->get_available() as $gw ) {
				$gateways[] = array(
					'id'    => $gw->get_id(),
					// Not $gw->get_label(): the SDK wraps it in a text
					// domain no consumer loads (SDK docs/HEADLESS-PLAN.md).
					'label' => wb_listora_credit_gateway_label( $gw->get_id() ),
				);
			}
		}

		// Listora has ONE currency, set in Settings, and it is the currency a
		// direct pack is charged in - the SDK prices in it too.
		$site_currency = function_exists( 'wb_listora_get_currency_format' )
			? (string) wb_listora_get_currency_format()['code']
			: 'USD';

		foreach ( (array) wb_listora_get_credit_mappings() as $map ) {
			if ( ! is_array( $map ) || empty( $map['adapter'] ) || empty( $map['item_id'] ) ) {
				continue;
			}

			$pack = array(
				'adapter'       => (string) $map['adapter'],
				'adapter_label' => (string) ( $map['adapter_label'] ?? '' ),
				// Products and plans have numeric ids; a Direct pack's id is
				// "direct_<uuid>", which an (int) cast turned into 0 for every
				// Direct pack, so no surface could tell them apart.
				'item_id'       => is_numeric( $map['item_id'] ) ? (int) $map['item_id'] : (string) $map['item_id'],
				'item_label'    => (string) ( $map['item_label'] ?? '' ),
				'credits'       => (int) ( $map['credits'] ?? 0 ),
				'price_html'    => '',
				'price'         => 0.0,
				'currency'      => $site_currency,
				'buy_url'       => '',
				'buy_label'     => __( 'Buy Now', 'wb-listora' ),
				// Direct-payment buttons, one per available gateway. Only a
				// direct pack has any: the SDK checkout sells direct packs and
				// rejects every other credit amount.
				'gateways'      => array(),
				'price_cents'   => 0,
			);

			switch ( $pack['adapter'] ) {
				case 'woocommerce':
				case 'woo_subscriptions':
					$product = function_exists( 'wc_get_product' ) ? wc_get_product( $pack['item_id'] ) : null;
					if ( $product ) {
						$pack['price_html'] = $product->get_price_html();
						$pack['price']      = (float) $product->get_price();
						$pack['currency']   = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : $site_currency;
						// Straight to checkout with the pack in the cart. The
						// bare add_to_cart_url() is relative to the current
						// page, so "Buy Now" reloaded the page it was on with
						// the item silently added. Products that need a choice
						// first (variable, subscriptions) go to their page.
						$pack['buy_url'] = ( $product->is_type( 'simple' ) && $product->is_purchasable() && function_exists( 'wc_get_checkout_url' ) )
							? add_query_arg( 'add-to-cart', $pack['item_id'], wc_get_checkout_url() )
							: (string) $product->get_permalink();
						if ( '' === $pack['item_label'] ) {
							$pack['item_label'] = $product->get_name();
						}
					}
					if ( 'woo_subscriptions' === $pack['adapter'] ) {
						$pack['buy_label'] = __( 'Subscribe', 'wb-listora' );
					}
					break;

				case 'pmpro':
					if ( function_exists( 'pmpro_url' ) ) {
						$pack['buy_url'] = (string) pmpro_url( 'checkout', '?level=' . $pack['item_id'] );
					}
					$pack['buy_label'] = __( 'Subscribe', 'wb-listora' );
					break;

				case 'memberpress':
					$permalink = get_permalink( (int) $pack['item_id'] );
					if ( $permalink ) {
						$pack['buy_url'] = (string) $permalink;
					}
					$pack['buy_label'] = __( 'Subscribe', 'wb-listora' );
					break;

				case 'direct':
					$pack['price_cents'] = (int) ( $map['price_cents'] ?? 0 );
					if ( $pack['price_cents'] > 0 ) {
						$pack['gateways']   = $gateways;
						$pack['price']      = $pack['price_cents'] / 100;
						$pack['price_html'] = function_exists( 'wb_listora_format_currency' )
							? wb_listora_format_currency( $pack['price'] )
							: esc_html( $site_currency . ' ' . number_format_i18n( $pack['price'], 2 ) );
					}
					break;
			}

			if ( '' === $pack['item_label'] ) {
				$pack['item_label'] = sprintf(
					/* translators: %d: number of credits */
					__( '%d credits', 'wb-listora' ),
					$pack['credits']
				);
			}

			// The Buy Credits template's vocabulary: `name`, and `url` - the key
			// Pricing_Plans::pack_checkout_url() reads.
			$pack['name'] = $pack['item_label'];
			$pack['url']  = $pack['buy_url'];

			// A pack is buyable through its checkout URL or, for a Direct pack,
			// a connected gateway.
			if ( $include_unbuyable || '' !== $pack['buy_url'] || ! empty( $pack['gateways'] ) ) {
				$packs[] = $pack;
			}
		}

		/**
		 * Filter the purchasable credit packs shown on every buy surface.
		 *
		 * @since 1.6.0
		 *
		 * @param array $packs Resolved packs.
		 */
		return (array) apply_filters( 'wb_listora_purchasable_credit_packs', $packs );
	}
}

if ( ! function_exists( 'wb_listora_get_terms_url' ) ) {

	/**
	 * The one Terms of Service link, for every surface.
	 *
	 * Resolution order: the page selected in Settings, then an external URL,
	 * then '' (no link). No page is ever created. Consent is enforced, so the
	 * submission checkbox and the REST payload must share this one source.
	 *
	 * @since 1.6.0
	 *
	 * @return string Terms URL, or '' when none is configured.
	 */
	function wb_listora_get_terms_url() {
		$page_id = (int) wb_listora_get_setting( 'legal_terms_page_id', 0 );

		if ( $page_id > 0 && 'publish' === get_post_status( $page_id ) ) {
			$permalink = get_permalink( $page_id );

			if ( $permalink ) {
				return (string) $permalink;
			}
		}

		return (string) wb_listora_get_setting( 'legal_terms_url', '' );
	}
}

if ( ! function_exists( 'wb_listora_get_page_publish_status' ) ) {

	/**
	 * Whether a mapped page can be linked to, and if not, why.
	 *
	 * Core's get_privacy_policy_url() - and wb_listora_get_terms_url() - return
	 * an empty string for a page that exists but is not published. Settings
	 * read that as "Not set", so an owner who had just used WordPress' own
	 * Create flow (which saves a draft) was told to go and set one (card
	 * 10313405198).
	 *
	 * @since 1.8.0
	 *
	 * @param int $page_id Mapped page ID.
	 * @return string 'published', 'unpublished' (exists, not public), or 'none'.
	 */
	function wb_listora_get_page_publish_status( int $page_id ): string {
		$status = $page_id > 0 ? get_post_status( $page_id ) : false;

		if ( false === $status || 'trash' === $status ) {
			return 'none';
		}

		return 'publish' === $status ? 'published' : 'unpublished';
	}
}

if ( ! function_exists( 'wb_listora_get_review_report_reasons' ) ) {

	/**
	 * The reasons a member may report a REVIEW for.
	 *
	 * Separate from the listing reasons. Shared by the REST enum and the report
	 * form so they cannot disagree. Keys are stored on the report row, so
	 * removing one orphans existing reports.
	 *
	 * @since 1.6.0
	 *
	 * @return array<string,string> Reason key => translated label.
	 */
	function wb_listora_get_review_report_reasons() {
		$reasons = array(
			'spam'         => __( 'Spam or advertising', 'wb-listora' ),
			'offensive'    => __( 'Offensive or abusive language', 'wb-listora' ),
			'off_topic'    => __( 'Not about this listing', 'wb-listora' ),
			'fake'         => __( 'Fake or incentivised review', 'wb-listora' ),
			'private_info' => __( 'Contains personal information', 'wb-listora' ),
			'conflict'     => __( 'Conflict of interest (owner or competitor)', 'wb-listora' ),
			'other'        => __( 'Something else', 'wb-listora' ),
		);

		/**
		 * Filters the reasons a member may report a review for.
		 *
		 * Applies to the REST enum AND the report form together, so a site that
		 * extends the list cannot end up with a form offering an option the
		 * endpoint rejects.
		 *
		 * @since 1.6.0
		 *
		 * @param array<string,string> $reasons Reason key => label.
		 */
		$reasons = apply_filters( 'wb_listora_review_report_reasons', $reasons );

		return is_array( $reasons ) ? $reasons : array();
	}
}

if ( ! function_exists( 'wb_listora_get_monetization_status' ) ) {

	/**
	 * The ONE answer to "can a member buy credits on this site right now?".
	 *
	 * Every surface consumes this single state so they cannot contradict each
	 * other. Packs and gateways are Pro's, so Free returns a safe default
	 * (no monetization) and Pro answers through the filter.
	 *
	 * @since 1.6.0
	 *
	 * @return array{state:string, owner_message:string, member_message:string, fix_url:string, fix_label:string}
	 *         state is one of: disabled | no_packs | needs_gateway | ready.
	 */
	function wb_listora_get_monetization_status() {
		$default = array(
			// No Pro means no credit system at all. Not an error, and no
			// surface should imply the owner has misconfigured something.
			'state'          => 'disabled',
			'owner_message'  => '',
			'member_message' => '',
			'fix_url'        => '',
			'fix_label'      => '',
		);

		/**
		 * Filter the resolved monetization status.
		 *
		 * Pro answers this authoritatively. Anything that tells an owner or a
		 * member whether credits can be bought MUST read the result rather
		 * than deriving its own answer.
		 *
		 * @since 1.6.0
		 *
		 * @param array $status See the return shape above.
		 */
		$status = apply_filters( 'wb_listora_monetization_status', $default );

		if ( ! is_array( $status ) ) {
			return $default;
		}

		// Re-assert the shape: these values reach templates that read every
		// key unguarded, and an incomplete filter return would fatal there
		// rather than here.
		$status = array_merge( $default, $status );

		$allowed = array( 'disabled', 'no_packs', 'needs_gateway', 'ready' );

		if ( ! in_array( $status['state'], $allowed, true ) ) {
			$status['state'] = 'disabled';
		}

		foreach ( array( 'owner_message', 'member_message', 'fix_url', 'fix_label' ) as $key ) {
			$status[ $key ] = is_scalar( $status[ $key ] ) ? (string) $status[ $key ] : '';
		}

		return $status;
	}
}

if ( ! function_exists( 'wb_listora_directory_is_operational' ) ) {

	/**
	 * Whether this install is a WORKING directory, wizard walked or not.
	 *
	 * A directory with published listings and real submission + dashboard
	 * pages is set up even if the wizard never ran. Shared by Free's notice and
	 * Pro's banner. Strict on purpose: every signal must hold, because a false
	 * positive hides first-run guidance from a site that needs it.
	 *
	 * @return bool True when the directory is demonstrably in use.
	 */
	function wb_listora_directory_is_operational() {
		$submission_page = \WBListora\Core\Page_Registry::get_id( 'submission' );
		$dashboard_page  = \WBListora\Core\Page_Registry::get_id( 'dashboard' );

		if ( $submission_page <= 0 || $dashboard_page <= 0 ) {
			return false;
		}

		// The IDs must still resolve to published pages — a settings row
		// pointing at a deleted page is a broken install, not a live one.
		foreach ( array( $submission_page, $dashboard_page ) as $page_id ) {
			$page = get_post( $page_id );
			if ( ! $page || 'page' !== $page->post_type || 'publish' !== $page->post_status ) {
				return false;
			}
		}

		$counts = wp_count_posts( 'listora_listing' );

		return ! empty( $counts->publish );
	}
}

if ( ! function_exists( 'wb_listora_get_dashboard_add_url' ) ) {

	/**
	 * URL for the in-dashboard "Add Listing" inline form.
	 *
	 * Logged-in members manage all listings on the dashboard — no page hops
	 * for adding or editing. The standalone /submit-listing/ page remains
	 * the canonical entry point for external visitors / SEO landing /
	 * marketing — but anyone with an account opens the inline form here.
	 *
	 * @return string
	 */
	function wb_listora_get_dashboard_add_url() {
		return add_query_arg(
			array(
				'tab'    => 'listings',
				'action' => 'add',
			),
			wb_listora_get_dashboard_url()
		);
	}
}

if ( ! function_exists( 'wb_listora_can_offer_submission' ) ) {
	/**
	 * Whether to show the current viewer an "Add listing" call to action.
	 *
	 * False when submissions are switched off or a signed-in member's role
	 * lacks submit_listora_listing, since the form would refuse them. Guests
	 * still get it; the form asks them to log in.
	 *
	 * @since 1.9.1
	 *
	 * @return bool
	 */
	function wb_listora_can_offer_submission() {
		if ( function_exists( 'wb_listora_feature_enabled' ) && ! wb_listora_feature_enabled( 'submission' ) ) {
			return false;
		}
		return ! is_user_logged_in() || current_user_can( 'submit_listora_listing' );
	}
}

if ( ! function_exists( 'wb_listora_get_dashboard_edit_url' ) ) {

	/**
	 * URL for the in-dashboard inline edit form for a specific listing.
	 *
	 * @param int $listing_id Listing post ID to edit.
	 * @return string
	 */
	function wb_listora_get_dashboard_edit_url( $listing_id ) {
		return add_query_arg(
			array(
				'tab'    => 'listings',
				'action' => 'edit',
				'id'     => (int) $listing_id,
			),
			wb_listora_get_dashboard_url()
		);
	}
}

if ( ! function_exists( 'wb_listora_get_upgrade_url' ) ) {

	/**
	 * Canonical URL the user is sent to when clicking an "Upgrade to Pro" CTA
	 * in the free plugin. Defaults to a marketing URL but is filterable so
	 * self-hosted or white-labeled installs can redirect internally.
	 *
	 * @return string
	 */
	function wb_listora_get_upgrade_url() {
		// The store slug is `listora-pro`, not `wb-listora-pro` — the plugin's
		// own folder name is not the product's URL, and using it 404s.
		$default = 'https://wbcomdesigns.com/downloads/listora-pro/';

		return (string) apply_filters( 'wb_listora_upgrade_url', $default );
	}
}

if ( ! function_exists( 'wb_listora_require_logged_in' ) ) {

	/**
	 * Standard logged-in permission callback for REST endpoints.
	 *
	 * Returns a WP_Error(401) when the request is not authenticated. Use as
	 * `'permission_callback' => 'wb_listora_require_logged_in'` on any route
	 * that needs "any logged-in user" — avoids bare `'is_user_logged_in'`
	 * which returns an opaque 403 without a structured error code.
	 *
	 * @return true|\WP_Error
	 */
	function wb_listora_require_logged_in() {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'listora_unauthorized',
				__( 'You must be logged in to perform this action.', 'wb-listora' ),
				array( 'status' => 401 )
			);
		}

		// Login only. Whether a logged-in account may WRITE is decided once, for
		// every listora/v1 write, by Member_Suspension::block_rest_writes() -
		// never here, where it would also block reads and account erasure.
		return true;
	}
}

if ( ! function_exists( 'wb_listora_get_member_write_block' ) ) {

	/**
	 * Why a member may not write, or null when they may.
	 *
	 * REST writes are gated centrally. Classic form handlers (admin-post.php)
	 * that authorize by authorship rather than capability are not, so they call
	 * this - a suspended, role-stripped or deactivated member must not be able
	 * to do by form what the REST gate refuses.
	 *
	 * @since 1.8.0
	 *
	 * @param int $user_id User to test. Defaults to the current user.
	 * @return array{code:string,message:string,reason:string}|null
	 */
	function wb_listora_get_member_write_block( int $user_id = 0 ): ?array {
		return \WBListora\Core\Member_Suspension::block_details( $user_id );
	}
}

if ( ! function_exists( 'wb_listora_render_pro_cta' ) ) {

	/**
	 * Render a reusable "Unlock with Pro" call-to-action card.
	 *
	 * Renders nothing when Pro is already active.
	 *
	 * @param array<string,mixed> $args {
	 *     @type string   $title       Heading.
	 *     @type string   $description Short lead paragraph.
	 *     @type string[] $features    Optional bullet list of benefits.
	 *     @type string   $button      Button label. Defaults to "Upgrade to Pro".
	 *     @type string   $url         Optional override for the upgrade URL.
	 *     @type string   $variant     "inline" (default), "card", "banner".
	 * }
	 * @return void
	 */
	function wb_listora_render_pro_cta( array $args = array() ): void {
		if ( wb_listora_is_pro_active() ) {
			return;
		}

		$args = wp_parse_args(
			$args,
			array(
				'title'       => __( 'Unlock with WB Listora Pro', 'wb-listora' ),
				'description' => '',
				'features'    => array(),
				'button'      => __( 'Upgrade to Pro', 'wb-listora' ),
				'url'         => '',
				'variant'     => 'card',
			)
		);

		$url = $args['url'] ? $args['url'] : wb_listora_get_upgrade_url();

		$classes = 'listora-pro-cta listora-pro-cta--' . sanitize_html_class( $args['variant'] );
		?>
		<div class="<?php echo esc_attr( $classes ); ?>" role="complementary">
			<div class="listora-pro-cta__badge" aria-hidden="true">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
				</svg>
				<?php esc_html_e( 'Pro', 'wb-listora' ); ?>
			</div>
			<div class="listora-pro-cta__body">
				<h3 class="listora-pro-cta__title"><?php echo esc_html( $args['title'] ); ?></h3>
				<?php if ( $args['description'] ) : ?>
				<p class="listora-pro-cta__description"><?php echo esc_html( $args['description'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $args['features'] ) ) : ?>
				<ul class="listora-pro-cta__features">
					<?php foreach ( $args['features'] as $feature ) : ?>
					<li><?php echo esc_html( $feature ); ?></li>
					<?php endforeach; ?>
				</ul>
				<?php endif; ?>
			</div>
			<div class="listora-pro-cta__actions">
				<a href="<?php echo esc_url( $url ); ?>" class="listora-btn wp-element-button listora-btn--primary" target="_blank" rel="noopener">
					<?php echo esc_html( $args['button'] ); ?>
				</a>
			</div>
		</div>
		<?php
	}
}

if ( ! function_exists( 'wb_listora_render_pro_lock' ) ) {

	/**
	 * Inline "Pro" lock badge for places where we want a subtle hint rather than a full card.
	 *
	 * @param string $label Label shown next to the lock icon (default: "Pro").
	 * @return void
	 */
	function wb_listora_render_pro_lock( string $label = '' ): void {
		if ( wb_listora_is_pro_active() ) {
			return;
		}
		$label = $label ? $label : __( 'Pro', 'wb-listora' );
		?>
		<span class="listora-pro-lock" aria-label="<?php esc_attr_e( 'Requires Pro', 'wb-listora' ); ?>">
			<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
				<rect x="3" y="11" width="18" height="11" rx="2"/>
				<path d="M7 11V7a5 5 0 0 1 10 0v4"/>
			</svg>
			<?php echo esc_html( $label ); ?>
		</span>
		<?php
	}
}

if ( ! function_exists( 'wb_listora_prime_card_index_rows' ) ) {

	/**
	 * Prime the request-scoped review-stats cache for a batch of listings.
	 *
	 * Callers that render many cards in one request (the listing-grid block)
	 * call this once with every listing ID before their render loop. It runs
	 * a single `WHERE listing_id IN (…)` query via
	 * {@see \WBListora\Search\Search_Indexer::get_index_rows_for_ids()} and
	 * stores the keyed map in a request-scoped static, so the subsequent
	 * `wb_listora_prepare_card_data()` calls read avg_rating/review_count from
	 * memory instead of issuing one query per card (the N+1 the grid render
	 * previously incurred at 20 cards/page).
	 *
	 * @param array<int> $ids Listing post IDs.
	 * @return void
	 */
	function wb_listora_prime_card_index_rows( array $ids ): void {
		$map = \WBListora\Search\Search_Indexer::get_index_rows_for_ids( $ids );
		foreach ( $map as $listing_id => $row ) {
			wb_listora_card_index_row( (int) $listing_id, $row );
		}
	}
}

if ( ! function_exists( 'wb_listora_card_index_row' ) ) {

	/**
	 * Read or seed one listing's review stats from the request-scoped cache.
	 *
	 * Acts as both the store (when `$row` is passed by the primer above) and
	 * the lookup used by `wb_listora_prepare_card_data()`. When a row was not
	 * primed for the requested ID — e.g. the standalone listing-card or
	 * listing-featured blocks, which render a single card without a batch
	 * prime — it falls back to a single-ID batch fetch so behaviour is
	 * identical to the previous per-row query (same values, just routed
	 * through the same one-query helper).
	 *
	 * @param int                                                   $post_id Listing post ID.
	 * @param array{avg_rating: float, review_count: int}|null      $row     When provided, seeds the cache for this ID.
	 * @return array{avg_rating: float, review_count: int} Review stats (zeroed when the listing has no index row).
	 */
	function wb_listora_card_index_row( int $post_id, ?array $row = null ): array {
		static $cache = array();

		if ( null !== $row ) {
			$cache[ $post_id ] = array(
				'avg_rating'   => (float) ( $row['avg_rating'] ?? 0 ),
				'review_count' => (int) ( $row['review_count'] ?? 0 ),
			);
			return $cache[ $post_id ];
		}

		if ( ! array_key_exists( $post_id, $cache ) ) {
			$map               = \WBListora\Search\Search_Indexer::get_index_rows_for_ids( array( $post_id ) );
			$cache[ $post_id ] = $map[ $post_id ] ?? array(
				'avg_rating'   => 0.0,
				'review_count' => 0,
			);
		}

		return $cache[ $post_id ];
	}
}

if ( ! function_exists( 'wb_listora_prepare_card_data' ) ) {

	/**
	 * Prepare card data for a listing post.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null
	 */
	function wb_listora_prepare_card_data( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}

		$registry = \WBListora\Core\Listing_Type_Registry::instance();
		$type     = $registry->get_for_post( $post_id );
		$meta     = \WBListora\Core\Meta_Handler::get_visible_values( $post_id );

		// Featured image.
		$thumb_id = get_post_thumbnail_id( $post_id );
		$image    = null;
		if ( $thumb_id ) {
			$full   = wp_get_attachment_image_src( $thumb_id, 'full' );
			$medium = wp_get_attachment_image_src( $thumb_id, 'medium_large' );
			$image  = array(
				'full'   => $full ? $full[0] : '',
				'medium' => $medium ? $medium[0] : '',
			);
		}

		// Location.
		$address  = $meta['address'] ?? array();
		$location = '';
		if ( is_array( $address ) ) {
			$parts    = array_filter( array( $address['city'] ?? '', $address['state'] ?? '' ) );
			$location = implode( ', ', $parts );
		}

		// Rating from search index. Read from the request-scoped cache primed
		// by wb_listora_prime_card_index_rows() (listing-grid batches every
		// card ID up front — one IN(…) query instead of one query per card).
		// When the cache was not primed (standalone listing-card /
		// listing-featured rendering a single card), the helper transparently
		// falls back to a single-ID fetch — same values as before.
		$idx_row = wb_listora_card_index_row( (int) $post_id );

		$rating = array(
			'average' => (float) $idx_row['avg_rating'],
			'count'   => (int) $idx_row['review_count'],
		);

		// Card fields.
		$card_fields = array();
		if ( $type ) {
			foreach ( $type->get_card_fields() as $field ) {
				$key     = $field->get_key();
				$value   = $meta[ $key ] ?? '';
				$display = wb_listora_format_card_value( $field, $value );
				if ( '' === $display ) {
					continue;
				}

				$badge_class = '';
				if ( 'checkbox' === $field->get_type() && $value ) {
					$display     = $field->get_label();
					$badge_class = 'listora-card__meta-item--badge';
				}
				if ( 'business_hours' === $field->get_type() ) {
					continue; // Open Now handled separately.
				}

				$card_fields[] = array(
					'key'           => $key,
					'label'         => $field->get_label(),
					'display_value' => $display,
					'badge_class'   => $badge_class,
				);
			}
		}

		// Features.
		$feature_terms = wp_get_object_terms( $post_id, 'listora_listing_feature' );
		$features      = array();
		if ( ! is_wp_error( $feature_terms ) ) {
			foreach ( $feature_terms as $term ) {
				$features[] = array(
					'name' => wb_listora_decode_text( $term->name ),
					'icon' => get_term_meta( $term->term_id, '_listora_icon', true ),
				);
			}
		}

		/*
		 * Tags.
		 *
		 * Carried on the card so a visitor can follow one straight from a
		 * grid, the way the detail page lets them. Slug travels alongside the
		 * name because the chip links to `?tags=<slug>` — resolving the slug
		 * in the template would mean a term lookup per card per tag.
		 */
		$tag_terms    = wp_get_object_terms( $post_id, 'listora_listing_tag' );
		$listing_tags = array();
		if ( ! is_wp_error( $tag_terms ) ) {
			foreach ( $tag_terms as $tag_term ) {
				$listing_tags[] = array(
					'name' => wb_listora_decode_text( $tag_term->name ),
					'slug' => $tag_term->slug,
				);
			}
		}

		$card_data = array(
			'id'          => $post_id,
			'title'       => wb_listora_decode_text( $post->post_title ),
			'link'        => get_permalink( $post_id ),
			'excerpt'     => wb_listora_decode_text( get_the_excerpt( $post ) ),
			'type'        => $type ? array(
				'slug'   => $type->get_slug(),
				'name'   => $type->get_name(),
				'color'  => $type->get_color(),
				'icon'   => $type->get_icon(),
				'schema' => $type->get_schema_type(),
			) : null,
			'meta'        => $meta,
			'image'       => $image,
			'location'    => $location,
			'rating'      => $rating,
			'card_fields' => $card_fields,
			'features'    => $features,
			'tags'        => $listing_tags,
			// Same resolver as the single-listing sidebar, so "Show Who Listed It"
			// applies to cards too.
			'owner_name'  => wb_listora_get_listing_owner_name( $post_id ),
			'owner_url'   => wb_listora_get_listing_owner_url( $post_id ),
			'badges'      => array(
				'featured' => \WBListora\Core\Featured::is_featured( $post_id ),
				'verified' => wb_listora_is_verified( $post_id ),
				'claimed'  => (bool) get_post_meta( $post_id, '_listora_is_claimed', true ),
			),
		);

		/**
		 * Filter the card data prepared for a listing.
		 *
		 * Fires once per card render — in listing-grid, listing-featured, and
		 * the standalone listing-card block. Use this to add or override card
		 * fields without subclassing templates.
		 *
		 * @param array    $card_data Card data keyed by id, title, link, excerpt,
		 *                            type, meta, image, location, rating,
		 *                            card_fields, features, badges.
		 * @param int      $post_id   Listing post ID.
		 * @param \WP_Post $post      Full post object.
		 */
		return apply_filters( 'wb_listora_card_view_data', $card_data, $post_id, $post );
	}
}

if ( ! function_exists( 'wb_listora_format_card_value' ) ) {

	/**
	 * Format a field value for card display.
	 *
	 * @param \WBListora\Core\Field $field Field definition.
	 * @param mixed                 $value Field value.
	 * @param bool                  $full  Keep free text whole (the listing's Details
	 *                                     tab). Cards and comparisons shorten it.
	 * @return string
	 */
	function wb_listora_format_card_value( $field, $value, $full = false ) {
		if ( '' === $value || null === $value || ( is_array( $value ) && empty( $value ) ) ) {
			return '';
		}

		$type = $field->get_type();

		switch ( $type ) {
			case 'select':
			case 'radio':
				$options = $field->get( 'options' ) ?: array();
				foreach ( $options as $opt ) {
					if ( ( $opt['value'] ?? '' ) === $value ) {
						return $opt['label'] ?? $value;
					}
				}
				return (string) $value;

			case 'multiselect':
				if ( is_array( $value ) ) {
					$labels  = array();
					$options = $field->get( 'options' ) ?: array();
					foreach ( $value as $v ) {
						$found = false;
						foreach ( $options as $opt ) {
							if ( ( $opt['value'] ?? '' ) === $v ) {
								$labels[] = $opt['label'] ?? $v;
								$found    = true;
								break;
							}
						}
						if ( ! $found ) {
							$labels[] = $v;
						}
					}
					return implode( ', ', $labels );
				}
				return (string) $value;

			case 'price':
				// A price of nothing is "Free", not "$0".
				$listora_amount = is_array( $value ) ? ( $value['amount'] ?? null ) : $value;
				if ( is_numeric( $listora_amount ) && 0.0 === (float) $listora_amount ) {
					return __( 'Free', 'wb-listora' );
				}
				if ( is_array( $value ) && isset( $value['amount'] ) ) {
					// The site's currency, not the one stored on the row. The
					// stored code records what was current when the price was
					// entered; it is not an instruction to keep displaying a
					// currency the owner has since changed away from. An owner
					// who switches to JPY expects every price on their site to
					// follow, and passing $value['currency'] here is what kept
					// old listings showing dollars.
					return wb_listora_format_currency( (float) $value['amount'] );
				}
				return is_numeric( $value ) ? wb_listora_format_currency( (float) $value ) : '';

			case 'checkbox':
				return $value ? $field->get_label() : '';

			case 'number':
			case 'rating':
				return is_numeric( $value ) ? number_format_i18n( (float) $value ) : '';

			case 'date':
				return $value ? (string) wp_date( get_option( 'date_format' ), (int) wb_listora_local_timestamp( $value ) ) : '';

			case 'datetime':
				return $value ? (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) wb_listora_local_timestamp( $value ) ) : '';

			case 'phone':
				return (string) $value;

			default:
				// Truncate with a real '…', not wp_trim_words()'s '&hellip;' entity:
				// consumers esc_html() this plain-text value, which would double-encode it.
				if ( ! is_string( $value ) ) {
					return '';
				}
				return $full ? $value : wp_trim_words( $value, 5, '…' );
		}
	}
}

if ( ! function_exists( 'wb_listora_get_listing_cards' ) ) {

	/**
	 * Card payloads for a set of listings, in a fixed number of queries.
	 *
	 * The compact shape (image, rating, type, location) for REST lists such as
	 * `/favorites` and `/listings/{id}/related`. Not built on
	 * {@see wb_listora_prepare_card_data()}, which costs ~7 queries per card;
	 * this uses one `search_index` read plus two cache primes.
	 *
	 * @since 1.5.0
	 *
	 * @param int[] $listing_ids Listing post IDs.
	 * @return array<int, array<string, mixed>> Keyed by listing ID. IDs with no
	 *                                          index row are omitted.
	 */
	function wb_listora_get_listing_cards( array $listing_ids ) {
		global $wpdb;

		$listing_ids = array_values( array_unique( array_filter( array_map( 'intval', $listing_ids ) ) ) );
		if ( empty( $listing_ids ) ) {
			return array();
		}

		_prime_post_caches( $listing_ids, false, true );

		$prefix       = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$placeholders = implode( ',', array_fill( 0, count( $listing_ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom plugin table, no WP API, site table prefix plus generated IN placeholders
		$index_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT listing_id, listing_type, avg_rating, review_count, is_featured, city, country
				 FROM {$prefix}search_index WHERE listing_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from the site table prefix, IN list is generated %d placeholders.
				...$listing_ids
			),
			ARRAY_A
		);

		$index_by_id = array();
		foreach ( (array) $index_rows as $index_row ) {
			$index_by_id[ (int) $index_row['listing_id'] ] = $index_row;
		}

		// Prime thumbnails as one batch so the image lookups below are cache
		// reads rather than a query per row.
		$thumb_by_id = array();
		$thumb_ids   = array();
		foreach ( $listing_ids as $listing_id ) {
			$thumb_id = (int) get_post_thumbnail_id( $listing_id );
			if ( $thumb_id > 0 ) {
				$thumb_by_id[ $listing_id ] = $thumb_id;
				$thumb_ids[]                = $thumb_id;
			}
		}
		if ( ! empty( $thumb_ids ) ) {
			_prime_post_caches( $thumb_ids, false, true );
		}

		$registry = \WBListora\Core\Listing_Type_Registry::instance();
		$cards    = array();

		foreach ( $listing_ids as $listing_id ) {
			$index = $index_by_id[ $listing_id ] ?? null;
			if ( null === $index ) {
				continue;
			}

			$type        = (string) $index['listing_type'];
			$type_object = $type ? $registry->get( $type ) : null;
			$thumb_id    = $thumb_by_id[ $listing_id ] ?? 0;

			/*
			 * Image keys match `/search`'s get_image_data() (`full`, `medium`,
			 * `thumbnail`, `alt`) so every endpoint shares one contract.
			 * `medium_large` is an extra key because the web card renders from it.
			 */
			$cards[ $listing_id ] = array(
				'id'                => $listing_id,
				// Decoded, not raw: the app was shipping a workaround for
				// "Statue of Liberty &#038; Ellis Island" reaching it encoded.
				// Goes through the shared helper now so every REST string
				// follows one rule instead of this one field being special.
				'title'             => wb_listora_decode_text( get_the_title( $listing_id ) ),
				'link'              => get_permalink( $listing_id ),
				// One canonical shape across endpoints — see Image_Schema. This
				// builder omitted `large`, which the detail builder returned.
				'featured_image'    => \WBListora\Core\Image_Schema::for_attachment( $thumb_id ),
				'rating'            => array(
					'average' => round( (float) $index['avg_rating'], 1 ),
					'count'   => (int) $index['review_count'],
				),
				'listing_type'      => $type,
				'listing_type_name' => $type_object ? $type_object->get_name() : '',
				'is_featured'       => (bool) $index['is_featured'],
				'location'          => trim( implode( ', ', array_filter( array( $index['city'], $index['country'] ) ) ) ),
			);
		}

		return $cards;
	}
}

if ( ! function_exists( 'wb_listora_get_map_tiles' ) ) {

	/**
	 * Resolve the raster tile source for a map provider.
	 *
	 * Shared by the listing-map block and the `/settings/maps` REST payload, so
	 * native clients get the tile URL from the server instead of hardcoding
	 * one. Empty strings for `google`, which uses the Google SDK.
	 *
	 * @since 1.4.2
	 *
	 * @param string $provider Map provider. Defaults to the configured setting.
	 * @return array{url:string,attribution:string}
	 */
	function wb_listora_get_map_tiles( $provider = '' ) {
		if ( ! $provider ) {
			$provider = (string) wb_listora_get_setting( 'map_provider', 'osm' );
		}

		$tiles = array(
			'url'         => '',
			'attribution' => '',
		);

		/*
		 * No default tile server: OSM's public tiles may not be used by a
		 * product shipped to unknown numbers of sites. The owner sets a tile
		 * URL in Settings -> Map; empty means no raster layer.
		 */
		if ( 'google' !== $provider ) {
			$configured = trim( (string) wb_listora_get_setting( 'map_tile_url', '' ) );

			if ( '' !== $configured ) {
				$tiles = array(
					'url'         => $configured,
					'attribution' => (string) wb_listora_get_setting( 'map_tile_attribution', '' ),
				);
			}
		}

		/**
		 * Filter the resolved raster tile source.
		 *
		 * Lets a site point every surface — web and native — at a self-hosted or
		 * commercial tile server in one place.
		 *
		 * @since 1.4.2
		 *
		 * @param array  $tiles    url + attribution.
		 * @param string $provider Provider being resolved.
		 */
		$filtered = apply_filters( 'wb_listora_map_tiles', $tiles, $provider );

		// Re-assert the shape. A filter returning a scalar or a partial array
		// would otherwise reach the block render and the public REST payload,
		// where both keys are read unguarded.
		if ( ! is_array( $filtered ) ) {
			return $tiles;
		}

		return array(
			'url'         => isset( $filtered['url'] ) && is_scalar( $filtered['url'] )
				? (string) $filtered['url']
				: $tiles['url'],
			'attribution' => isset( $filtered['attribution'] ) && is_scalar( $filtered['attribution'] )
				? (string) $filtered['attribution']
				: $tiles['attribution'],
		);
	}
}

if ( ! function_exists( 'wb_listora_get_currencies' ) ) {

	/**
	 * The currencies Listora can render, as code => symbol.
	 *
	 * Shared by the admin Currency dropdown and the price formatter, so the
	 * dropdown never offers a currency the formatter cannot render.
	 *
	 * @since 1.7.0
	 *
	 * @return array<string,string> Currency code => display symbol.
	 */
	function wb_listora_get_currencies(): array {
		$currencies = array(
			'USD' => '$',
			'EUR' => '€',
			'GBP' => '£',
			'JPY' => '¥',
			'INR' => '₹',
			'AUD' => 'A$',
			'CAD' => 'C$',
			'CHF' => 'CHF',
			'CNY' => '¥',
			'BRL' => 'R$',
		);

		/**
		 * Filter the currencies Listora offers and can render.
		 *
		 * Adding a code here puts it in the admin dropdown AND gives it a
		 * symbol at render time, so the two can never disagree.
		 *
		 * @since 1.7.0
		 *
		 * @param array<string,string> $currencies Code => symbol.
		 */
		return (array) apply_filters( 'wb_listora_currencies', $currencies );
	}
}

if ( ! function_exists( 'wb_listora_get_currency_format' ) ) {
	/**
	 * Resolve how a currency should be displayed.
	 *
	 * Shared by wb_listora_format_currency() and the app-config REST payload, so
	 * native clients format prices exactly like the web.
	 *
	 * @since 1.4.2
	 *
	 * @param string $currency Currency code, or '' to use the site setting.
	 * @return array{code:string,symbol:string,position:string,decimals:int}
	 */
	function wb_listora_get_currency_format( string $currency = '' ): array {
		if ( ! $currency ) {
			$currency = wb_listora_get_setting( 'currency', 'USD' );
		}

		$symbols = wb_listora_get_currencies();

		// Zero-decimal currencies (ISO 4217). Everything else uses 2.
		$zero_decimal = array( 'JPY' );

		$format = array(
			'code'     => (string) $currency,
			'symbol'   => $symbols[ $currency ] ?? $currency . ' ',
			'position' => 'before',
			'decimals' => in_array( $currency, $zero_decimal, true ) ? 0 : 2,
		);

		/**
		 * Filter the resolved currency display format.
		 *
		 * Lets a site serve a suffix-position currency, a custom symbol, or a
		 * different precision without overriding every render site.
		 *
		 * @since 1.4.2
		 *
		 * @param array  $format   code / symbol / position ('before'|'after') / decimals.
		 * @param string $currency Currency code being resolved.
		 */
		$filtered = apply_filters( 'wb_listora_currency_format', $format, $currency );

		// Re-assert the shape — every caller reads all four keys unguarded, and
		// `decimals` reaches number_format() where a non-int would warn.
		if ( ! is_array( $filtered ) ) {
			return $format;
		}

		return array(
			'code'     => isset( $filtered['code'] ) && is_scalar( $filtered['code'] )
				? (string) $filtered['code']
				: $format['code'],
			'symbol'   => isset( $filtered['symbol'] ) && is_scalar( $filtered['symbol'] )
				? (string) $filtered['symbol']
				: $format['symbol'],
			'position' => isset( $filtered['position'] ) && in_array( $filtered['position'], array( 'before', 'after' ), true )
				? $filtered['position']
				: $format['position'],
			'decimals' => isset( $filtered['decimals'] ) && is_numeric( $filtered['decimals'] )
				? (int) $filtered['decimals']
				: $format['decimals'],
		);
	}
}

if ( ! function_exists( 'wb_listora_format_currency' ) ) {

	/**
	 * Format a currency amount.
	 *
	 * @since 1.9.1 `$exact` - payments, refunds and pack prices show every digit.
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency Currency code.
	 * @param bool   $exact    True: never abbreviate and always show the currency's decimals.
	 * @return string
	 */
	function wb_listora_format_currency( $amount, $currency = '', $exact = false ) {
		$format = wb_listora_get_currency_format( $currency );
		$symbol = $format['symbol'];

		if ( $exact ) {
			$rendered = number_format_i18n( (float) $amount, $format['decimals'] );
			return 'after' === $format['position'] ? $rendered . $symbol : $symbol . $rendered;
		}

		/**
		 * Whether to abbreviate large amounts as K / M at all.
		 *
		 * Abbreviation trades precision for width, which is the wrong trade in
		 * a directory where the exact figure is the point — real estate, plant
		 * hire, anything where two listings differ by less than the rounding
		 * step. Return false to always print the full amount.
		 *
		 * @since 1.5.0
		 *
		 * @param bool   $abbreviate Whether to abbreviate.
		 * @param float  $amount     The amount being formatted.
		 * @param string $currency   Currency code.
		 */
		$abbreviate = (bool) apply_filters( 'wb_listora_abbreviate_price', true, (float) $amount, (string) $currency );

		/*
		 * Truncate, never round: the abbreviated figure must never overstate the
		 * price (1,500 is "1.5K", not "2K"; 999,999 is "999.9K", not "1,000K").
		 * Tenths use integer division because floor( $x * 10 ) / 10 loses a step
		 * on binary floats (1.4 → 1.3999…).
		 */
		if ( $abbreviate && $amount >= 1000000 ) {
			$tenths   = (int) floor( (float) $amount / 100000 );
			$rendered = number_format_i18n( $tenths / 10, ( 0 === $tenths % 10 ) ? 0 : 1 ) . 'M';
		} elseif ( $abbreviate && $amount >= 1000 ) {
			$tenths   = (int) floor( (float) $amount / 100 );
			$rendered = number_format_i18n( $tenths / 10, ( 0 === $tenths % 10 ) ? 0 : 1 ) . 'K';
		} else {
			// Whole amounts render without decimals ("$35", not "$35.00"); the
			// `decimals` value published to clients is the currency's maximum.
			$precision = ( (float) $amount === floor( (float) $amount ) ) ? 0 : $format['decimals'];
			$rendered  = number_format_i18n( $amount, $precision );
		}

		return 'after' === $format['position'] ? $rendered . $symbol : $symbol . $rendered;
	}
}

if ( ! function_exists( 'wb_listora_validate_business_hours' ) ) {

	/**
	 * Validate a set of opening ranges before they are saved.
	 *
	 * Returns WP_Error instead of silently merging overlaps, so the owner's
	 * input is never changed behind their back. Overnight ranges (close < open,
	 * e.g. 18:00-02:00) are valid; only same-day ranges are compared.
	 *
	 * @since 1.5.0
	 *
	 * @param array<int, array<string, mixed>> $hours Entries: { day, open, close, closed, is_24h }.
	 * @return true|WP_Error True when the set is storable.
	 */
	function wb_listora_validate_business_hours( $hours ) {
		if ( ! is_array( $hours ) ) {
			return new WP_Error( 'listora_hours_shape', __( 'Opening hours must be a list.', 'wb-listora' ), array( 'status' => 400 ) );
		}

		$max      = \WBListora\Search\Search_Indexer::max_hours_slots();
		$by_day   = array();
		$day_name = array( __( 'Sunday', 'wb-listora' ), __( 'Monday', 'wb-listora' ), __( 'Tuesday', 'wb-listora' ), __( 'Wednesday', 'wb-listora' ), __( 'Thursday', 'wb-listora' ), __( 'Friday', 'wb-listora' ), __( 'Saturday', 'wb-listora' ) );

		foreach ( $hours as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['day'] ) ) {
				continue;
			}

			// A closed or 24h day has no range to compare.
			if ( ! empty( $entry['closed'] ) || ! empty( $entry['is_24h'] ) ) {
				continue;
			}

			$by_day[ (int) $entry['day'] ][] = $entry;
		}

		foreach ( $by_day as $day => $entries ) {
			$label = $day_name[ $day ] ?? (string) $day;

			if ( count( $entries ) > $max ) {
				return new WP_Error(
					'listora_hours_too_many',
					sprintf(
						/* translators: 1: day name, 2: maximum number of ranges. */
						__( '%1$s has too many opening times. You can add up to %2$d per day.', 'wb-listora' ),
						$label,
						$max
					),
					array( 'status' => 400 )
				);
			}

			$ranges = array();

			foreach ( $entries as $entry ) {
				$open  = wb_listora_hours_to_minutes( $entry['open'] ?? '' );
				$close = wb_listora_hours_to_minutes( $entry['close'] ?? '' );

				if ( null === $open || null === $close ) {
					return new WP_Error(
						'listora_hours_invalid_time',
						sprintf(
							/* translators: %s: day name. */
							__( '%s has an opening time that is not a valid time.', 'wb-listora' ),
							$label
						),
						array( 'status' => 400 )
					);
				}

				// Overnight spans are legitimate; skip them in the overlap test
				// rather than calling them invalid.
				if ( $close <= $open ) {
					continue;
				}

				foreach ( $ranges as $existing ) {
					if ( $open < $existing[1] && $close > $existing[0] ) {
						return new WP_Error(
							'listora_hours_overlap',
							sprintf(
								/* translators: %s: day name. */
								__( '%s has two opening times that overlap. Adjust them so they do not run into each other.', 'wb-listora' ),
								$label
							),
							array( 'status' => 400 )
						);
					}
				}

				$ranges[] = array( $open, $close );
			}
		}

		return true;
	}
}

if ( ! function_exists( 'wb_listora_hours_to_minutes' ) ) {

	/**
	 * Parse an `HH:MM` (or `HH:MM:SS`) time into minutes past midnight.
	 *
	 * @since 1.5.0
	 *
	 * @param mixed $time Raw time value.
	 * @return int|null Minutes, or null when unparseable.
	 */
	function wb_listora_hours_to_minutes( $time ) {
		if ( ! is_string( $time ) || ! preg_match( '/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim( $time ), $m ) ) {
			return null;
		}

		$hours   = (int) $m[1];
		$minutes = (int) $m[2];

		if ( $hours > 23 || $minutes > 59 ) {
			return null;
		}

		return ( $hours * 60 ) + $minutes;
	}
}

if ( ! function_exists( 'wb_listora_normalize_hours' ) ) {

	/**
	 * Flatten a `business_hours` meta value into one list of day-tagged ranges.
	 *
	 * Stored data has three shapes: the canonical list, a day-keyed dict, and
	 * the `ranges` shape for split shifts. Every reader (indexer, templates,
	 * Pro) must go through this so indexed and displayed hours never differ.
	 *
	 * @since 1.5.0
	 *
	 * @param mixed $hours Raw `_listora_business_hours` meta value.
	 * @return array<int, array<string, mixed>> Entries each carrying an integer `day`.
	 */
	function wb_listora_normalize_hours( $hours ) {
		if ( empty( $hours ) || ! is_array( $hours ) ) {
			return array();
		}

		// No method_exists guard — the indexer ships in this plugin and is
		// always loaded; a defensive check here would only be dead code.
		return \WBListora\Search\Search_Indexer::normalise_hours_meta( $hours );
	}
}

if ( ! function_exists( 'wb_listora_max_hours_slots' ) ) {

	/**
	 * How many time ranges one day may hold.
	 *
	 * The submission builder, the indexer and any importer must agree on this
	 * number, or a producer writes ranges the index then drops without saying
	 * so. Exposed as a global because Pro's importers need it and must not name
	 * a Free internal class (INV-3).
	 *
	 * Filterable via `wb_listora_max_hours_slots`.
	 *
	 * @since 1.5.0
	 *
	 * @return int At least 1.
	 */
	function wb_listora_max_hours_slots() {
		return \WBListora\Search\Search_Indexer::max_hours_slots();
	}
}

if ( ! function_exists( 'wb_listora_hidden_review_authors' ) ) {

	/**
	 * User IDs whose content should be hidden from the current viewer.
	 *
	 * Shared by both review read paths (Listing_Data::get_reviews() and the REST
	 * list) so they cannot drift. Empty for guests and members with no blocks.
	 *
	 * @since 1.5.0
	 *
	 * @param int $viewer Viewer. Defaults to the current user.
	 * @return int[]
	 */
	function wb_listora_hidden_review_authors( $viewer = 0 ) {
		if ( ! class_exists( '\WBListora\Core\Member_Blocks' ) ) {
			return array();
		}

		return \WBListora\Core\Member_Blocks::hidden_from( (int) $viewer );
	}
}

if ( ! function_exists( 'wb_listora_directions_url' ) ) {
	/**
	 * Driving-directions URL for a coordinate pair.
	 *
	 * One place for the link the header "Directions" button and the sidebar
	 * map card share, so the two can never point somewhere different.
	 *
	 * @since 1.9.0
	 *
	 * @param float $lat Latitude.
	 * @param float $lng Longitude.
	 * @return string URL, or '' without coordinates.
	 */
	function wb_listora_directions_url( $lat, $lng ) {
		$lat = (float) $lat;
		$lng = (float) $lng;
		if ( ! $lat || ! $lng ) {
			return '';
		}
		return 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode( $lat . ',' . $lng );
	}
}

if ( ! function_exists( 'wb_listora_format_address_line' ) ) {
	/**
	 * Build a one-line address without repeating what it already contains.
	 *
	 * Google Places and the submission autocomplete store a full formatted line
	 * in `address` while also filling `city` and `state`, so a component is
	 * appended only when the street line does not already contain it (word
	 * boundaries, so "NY" is not found in "Nyack Road").
	 *
	 * @since 1.5.0
	 *
	 * @param array<string, mixed> $address Address meta.
	 * @return string Comma-joined line, or '' when there is nothing to show.
	 */
	function wb_listora_format_address_line( $address ) {
		$parts = wb_listora_format_address_parts( $address );

		return trim( implode( ', ', array_filter( array( $parts['street'], $parts['locality'] ) ) ) );
	}
}

if ( ! function_exists( 'wb_listora_format_address_parts' ) ) {

	/**
	 * Address split into de-duplicated display parts.
	 *
	 * Returns `street`, `locality` (city / state / postal) and `country`, with
	 * anything already in the street line stripped from `locality`. Same
	 * containment rule as wb_listora_format_address_line(), split for panels.
	 *
	 * @since 1.6.0
	 *
	 * @param array<string,mixed> $address Address meta.
	 * @return array{street:string,locality:string,country:string}
	 */
	function wb_listora_format_address_parts( $address ) {
		$empty = array(
			'street'   => '',
			'locality' => '',
			'country'  => '',
		);

		if ( ! is_array( $address ) ) {
			return $empty;
		}

		$street = trim( (string) ( $address['address'] ?? '' ) );
		$parts  = array();

		foreach ( array( 'city', 'state' ) as $component ) {
			$value = trim( (string) ( $address[ $component ] ?? '' ) );

			if ( '' === $value ) {
				continue;
			}

			if ( '' !== $street && preg_match( '/\b' . preg_quote( $value, '/' ) . '\b/iu', $street ) ) {
				continue;
			}

			$parts[] = $value;
		}

		/*
		 * Postal code, unless the street line already contains it. Joined to the
		 * state with a space: "Manhattan, NY 10013".
		 */
		$postal = trim( (string) ( $address['postal_code'] ?? '' ) );

		if ( '' !== $postal && ! ( '' !== $street && preg_match( '/\b' . preg_quote( $postal, '/' ) . '\b/iu', $street ) ) ) {
			if ( $parts && $postal !== end( $parts ) ) {
				$parts[ array_key_last( $parts ) ] .= ' ' . $postal;
			} else {
				$parts[] = $postal;
			}
		}

		return array(
			'street'   => $street,
			'locality' => implode( ', ', $parts ),
			'country'  => trim( (string) ( $address['country'] ?? '' ) ),
		);
	}
}

if ( ! function_exists( 'wb_listora_review_author_name' ) ) {

	/**
	 * Display name for a review's author, including when the user is gone.
	 *
	 * user_id = 0 means the privacy eraser anonymised the row: "Anonymous".
	 * user_id > 0 with no user means the account was deleted another way (or
	 * import leftovers), which gets its own label. The name is not snapshotted
	 * onto the row, because the `reviews` erasure strategy is `anonymize` and a
	 * stored copy would survive account deletion. Used by REST and the block.
	 *
	 * @since 1.5.0
	 *
	 * @param int $user_id Stored review author ID.
	 * @return string Display name, or the appropriate stand-in.
	 */
	function wb_listora_review_author_name( $user_id ) {
		$user_id = (int) $user_id;
		// get_userdata() returns false, not null, for a missing account.
		// Normalised here so the filter below promises exactly one "no user"
		// value instead of making every listener handle both.
		$user = $user_id > 0 ? get_userdata( $user_id ) : false;
		$user = $user instanceof WP_User ? $user : null;

		if ( $user ) {
			$name = (string) $user->display_name;
		} elseif ( $user_id > 0 ) {
			$name = __( 'Former member', 'wb-listora' );
		} else {
			$name = __( 'Anonymous', 'wb-listora' );
		}

		/**
		 * Filter the display name shown for a review author.
		 *
		 * @since 1.5.0
		 *
		 * @param string        $name    Resolved name.
		 * @param int           $user_id Stored review author ID (0 when anonymised).
		 * @param \WP_User|null $user    User object, or null when the account is gone.
		 */
		return (string) apply_filters( 'wb_listora_review_author_name', $name, $user_id, $user );
	}
}

if ( ! function_exists( 'wb_listora_can_members_contact' ) ) {

	/**
	 * Whether two members may contact each other.
	 *
	 * The public surface for member blocking; Pro and third parties call this,
	 * never Core\Member_Blocks. Symmetric: false whichever member blocked.
	 *
	 * @since 1.5.0
	 *
	 * @param int $a One member.
	 * @param int $b The other member.
	 * @return bool True when contact is allowed.
	 */
	function wb_listora_can_members_contact( $a, $b ) {
		if ( ! class_exists( '\WBListora\Core\Member_Blocks' ) ) {
			return true;
		}

		return \WBListora\Core\Member_Blocks::can_contact( (int) $a, (int) $b );
	}
}

if ( ! function_exists( 'wb_listora_members_blocked' ) ) {

	/**
	 * Whether two members are blocked from each other, in either direction.
	 *
	 * @since 1.5.0
	 *
	 * @param int $a One member.
	 * @param int $b The other member.
	 * @return bool
	 */
	function wb_listora_members_blocked( $a, $b ) {
		if ( ! class_exists( '\WBListora\Core\Member_Blocks' ) ) {
			return false;
		}

		return \WBListora\Core\Member_Blocks::is_blocked_pair( (int) $a, (int) $b );
	}
}

if ( ! function_exists( 'wb_listora_is_admin_screen' ) ) {

	/**
	 * Whether the current admin screen belongs to Listora (Free or Pro).
	 *
	 * Free's and Pro's admin asset enqueues both use this, because Pro's admin
	 * style depends on Free's `listora-admin` handle. True when the screen's
	 * post_type or taxonomy starts with `listora_`, or its ID is
	 * `toplevel_page_listora` or starts with `listora_page_` or
	 * `admin_page_listora-`. Extend via `wb_listora_is_admin_screen`.
	 *
	 * @return bool
	 */
	function wb_listora_is_admin_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		$is_listora = false;

		if ( ! empty( $screen->post_type ) && 0 === strpos( (string) $screen->post_type, 'listora_' ) ) {
			$is_listora = true;
		} elseif ( ! empty( $screen->taxonomy ) && 0 === strpos( (string) $screen->taxonomy, 'listora_' ) ) {
			$is_listora = true;
		} elseif ( 'toplevel_page_listora' === $screen->id ) {
			$is_listora = true;
		} elseif ( 0 === strpos( (string) $screen->id, 'listora_page_' ) ) {
			$is_listora = true;
		} elseif ( 0 === strpos( (string) $screen->id, 'admin_page_listora-' ) ) {
			$is_listora = true;
		} elseif ( 0 === strpos( (string) $screen->id, 'admin_page_wb-listora' ) ) {
			// Pro hidden submenu pages render under `admin_page_wb-listora-*`
			// (e.g. Pro Setup, License, Migration when registered after a Pro
			// license activates). Defensive coverage so F4 also reaches those
			// pages whenever they're available — current site has no license
			// so the pages don't render, but the rule is correct on principle.
			$is_listora = true;
		} elseif ( false !== strpos( (string) $screen->id, '_page_wb-listora' ) ) {
			// Pro pages mounted as visible submenus (any parent screen ID).
			$is_listora = true;
		}

		/**
		 * Filters whether the current admin screen is a Listora screen.
		 *
		 * @since 1.0.5
		 *
		 * @param bool       $is_listora Detection result.
		 * @param \WP_Screen $screen     Current screen object.
		 */
		return (bool) apply_filters( 'wb_listora_is_admin_screen', $is_listora, $screen );
	}
}

if ( ! function_exists( 'wb_listora_verify_rest_nonce' ) ) {

	/**
	 * Conditional proof-of-origin gate for guest-friendly REST writes.
	 *
	 * Contact and analytics writes need a per-listing nonce printed in the page.
	 * Native clients cannot mint one, so a nonce is verified when sent and an
	 * authenticated request without one is allowed (same rule as `POST /submit`);
	 * a guest with no nonce gets a 403.
	 *
	 * @since 1.2.3
	 *
	 * @param string               $nonce   Nonce value as sent by the client ('' when absent).
	 * @param string               $action  Nonce action the token must verify against.
	 * @param array<string, mixed> $context Optional context for the filters: { route, listing_id }.
	 * @return true|\WP_Error True when the request may proceed, WP_Error (403) otherwise.
	 */
	function wb_listora_verify_rest_nonce( $nonce, $action, array $context = array() ) {
		$nonce = (string) $nonce;

		/**
		 * Restore strict, unconditional nonce verification.
		 *
		 * Escape hatch for the 1.2.3 default-behaviour change (production rule
		 * 3). A site that would rather keep the browser-only contract — and
		 * accept that the mobile app cannot contact listing owners — can
		 * restore the pre-1.2.3 behaviour with a one-line mu-plugin:
		 *
		 *     add_filter( 'wb_listora_require_rest_nonce', '__return_true' );
		 *
		 * @since 1.2.3
		 *
		 * @param bool                 $require Whether a valid nonce is mandatory regardless of auth. Default false.
		 * @param string               $action  Nonce action being verified.
		 * @param array<string, mixed> $context { route, listing_id }.
		 */
		$require_nonce = (bool) apply_filters( 'wb_listora_require_rest_nonce', false, $action, $context );

		// A token was sent — always verify it. The browser path is unchanged:
		// a stale or forged token is still a hard 403, logged in or not.
		if ( '' !== $nonce ) {
			if ( ! wp_verify_nonce( $nonce, $action ) ) {
				return new \WP_Error(
					'listora_invalid_nonce',
					__( 'Security check failed. Reload the page and try again.', 'wb-listora' ),
					array( 'status' => 403 )
				);
			}

			return true;
		}

		// No token. An authenticated caller (Application Password, cookie +
		// X-WP-Nonce, JWT, …) has already proven origin to WordPress itself,
		// so accept it — unless the site opted back into strict mode.
		if ( ! $require_nonce && is_user_logged_in() ) {
			return true;
		}

		// Anonymous with no proof of page render. Unchanged: rejected.
		return new \WP_Error(
			'listora_invalid_nonce',
			__( 'Security check failed. Reload the page and try again.', 'wb-listora' ),
			array( 'status' => 403 )
		);
	}
}

if ( ! function_exists( 'wb_listora_contact_rate_limit_identity' ) ) {

	/**
	 * Resolve the rate-limit identity for a contact-style message.
	 *
	 * Keyed on the user when known, so members behind one carrier NAT address
	 * do not share a budget; guests stay keyed on IP.
	 *
	 * @since 1.2.3
	 *
	 * @param int $listing_id Listing the message targets.
	 * @return array{scope: string, id: string} `scope` is 'user' or 'ip'; `id` is the bucket identity.
	 */
	function wb_listora_contact_rate_limit_identity( $listing_id ) {
		$user_id = get_current_user_id();

		if ( $user_id > 0 ) {
			$identity = array(
				'scope' => 'user',
				'id'    => (string) $user_id,
			);
		} else {
			$ip       = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
			$identity = array(
				'scope' => 'ip',
				'id'    => $ip,
			);
		}

		/**
		 * Filter the contact/lead rate-limit identity.
		 *
		 * Escape hatch for the 1.2.3 per-user bucketing change (production
		 * rule 3). To restore the pre-1.2.3 always-per-IP behaviour:
		 *
		 *     add_filter( 'wb_listora_contact_rate_limit_identity', function ( $identity ) {
		 *         $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
		 *         return array( 'scope' => 'ip', 'id' => $ip );
		 *     } );
		 *
		 * @since 1.2.3
		 *
		 * @param array{scope: string, id: string} $identity   Resolved identity.
		 * @param int                              $listing_id Listing ID.
		 * @param int                              $user_id    Current user ID (0 for guests).
		 */
		$identity = (array) apply_filters( 'wb_listora_contact_rate_limit_identity', $identity, (int) $listing_id, (int) $user_id );

		return array(
			'scope' => isset( $identity['scope'] ) ? (string) $identity['scope'] : 'ip',
			'id'    => isset( $identity['id'] ) ? (string) $identity['id'] : 'unknown',
		);
	}
}

if ( ! function_exists( 'wb_listora_get_review_criteria' ) ) {

	/**
	 * Resolve the multi-criteria review criteria for a listing type.
	 *
	 * Single entry point for `wb_listora_review_criteria`. The type's saved
	 * `review_criteria` is the filter's base, so listeners that return what
	 * they get honour the site's configuration.
	 *
	 * @since 1.6.0
	 *
	 * @param string $type_slug Listing type slug.
	 * @return array<int, array<string, mixed>> Criteria rows, possibly empty. Rows
	 *                                          should carry `key` and `label`, but
	 *                                          only array-ness is guaranteed.
	 */
	function wb_listora_get_review_criteria( $type_slug ) {
		$type_slug = (string) $type_slug;
		$stored    = array();

		if ( $type_slug && class_exists( '\WBListora\Core\Listing_Type_Registry' ) ) {
			$type = \WBListora\Core\Listing_Type_Registry::instance()->get( $type_slug );

			if ( $type && method_exists( $type, 'get_prop' ) ) {
				$stored = $type->get_prop( 'review_criteria' );
			}
		}

		// Item-level shape guard on the STORED value as well as the filtered
		// one: `_listora_review_criteria` is writable over REST, and a scalar
		// row would fatal the `{key,label}` offset reads in the templates.
		$stored = is_array( $stored )
			? array_values( array_filter( $stored, 'is_array' ) )
			: array();

		/**
		 * Filters the review criteria for a listing type.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $criteria  Criteria saved against the listing type. Empty
		 *                          when the owner has configured none.
		 * @param string $type_slug Listing type slug.
		 */
		$criteria = apply_filters( 'wb_listora_review_criteria', $stored, $type_slug );

		return array_values( array_filter( (array) $criteria, 'is_array' ) );
	}
}

if ( ! function_exists( 'wb_listora_get_review_criteria_scores' ) ) {
	/**
	 * The per-criterion stars one reviewer gave, labelled from the listing type.
	 *
	 * Only criteria the listing's type still configures are returned, in the
	 * type's order — a criterion the owner has since removed stays hidden.
	 *
	 * @since 1.8.0
	 *
	 * @param array<string, mixed> $review Review row; `criteria_ratings` may be the stored JSON or a decoded array.
	 * @return array<int, array{label: string, rating: int}>
	 */
	function wb_listora_get_review_criteria_scores( array $review ): array {
		static $labels_by_type = array();

		$ratings = $review['criteria_ratings'] ?? array();
		if ( is_string( $ratings ) ) {
			$ratings = json_decode( $ratings, true );
		}
		if ( empty( $ratings ) || ! is_array( $ratings ) || empty( $review['listing_id'] ) ) {
			return array();
		}

		// get_the_terms() reads the object-term cache, which archive loops and
		// the admin table prime — wp_get_object_terms() would query per review.
		$terms = get_the_terms( (int) $review['listing_id'], 'listora_listing_type' );
		$slug  = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->slug : '';
		if ( ! isset( $labels_by_type[ $slug ] ) ) {
			$labels_by_type[ $slug ] = array();
			foreach ( wb_listora_get_review_criteria( $slug ) as $criterion ) {
				$key = sanitize_key( $criterion['key'] ?? '' );
				if ( $key ) {
					$labels_by_type[ $slug ][ $key ] = (string) ( $criterion['label'] ?? $key );
				}
			}
		}

		$scores = array();
		foreach ( $labels_by_type[ $slug ] as $key => $label ) {
			$rating = (int) ( $ratings[ $key ] ?? 0 );
			if ( $rating >= 1 && $rating <= 5 ) {
				$scores[] = array(
					'label'  => $label,
					'rating' => $rating,
				);
			}
		}

		return $scores;
	}
}

if ( ! function_exists( 'wb_listora_render_review_criteria' ) ) {
	/**
	 * Print a review's per-criterion stars. Hooked on `wb_listora_review_after_content`.
	 *
	 * @since 1.8.0
	 *
	 * @param array<string, mixed> $review Review row.
	 */
	function wb_listora_render_review_criteria( $review ): void {
		$scores = is_array( $review ) ? wb_listora_get_review_criteria_scores( $review ) : array();
		if ( ! $scores ) {
			return;
		}

		echo '<ul class="listora-review-criteria">';
		foreach ( $scores as $score ) {
			echo '<li class="listora-review-criteria__item"><span class="listora-review-criteria__label">' . esc_html( $score['label'] ) . '</span>';
			/* translators: %d: rating out of 5 */
			echo '<span class="listora-rating" role="img" aria-label="' . esc_attr( sprintf( __( '%d out of 5 stars', 'wb-listora' ), $score['rating'] ) ) . '">';
			for ( $s = 1; $s <= 5; $s++ ) {
				echo '<svg class="listora-rating__star' . ( $s > $score['rating'] ? ' listora-rating__star--empty' : '' ) . '" viewBox="0 0 24 24" width="12" height="12" aria-hidden="true"><path fill="currentColor" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
			}
			echo '</span></li>';
		}
		echo '</ul>';
	}
}

if ( ! function_exists( 'wb_listora_get_credit_mappings' ) ) {
	/**
	 * Read the credit mappings option in ONE normalised shape.
	 *
	 * `{slug}_credit_mappings` holds flat rows (Pro's admin UI) or the SDK's
	 * nested `[ adapter => [ item_id => credits ] ]` map; every consumer reads
	 * through this. In Free because Free bundles the SDK that owns the option.
	 *
	 * @since 1.6.0
	 *
	 * @return array<int, array<string, mixed>> Flat rows: adapter, adapter_label,
	 *                                          item_id, item_label, credits.
	 */
	function wb_listora_get_credit_mappings() {
		$raw = get_option( 'wb-listora_credit_mappings', array() );

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$flat = array();

		foreach ( $raw as $key => $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			// Flat row already.
			if ( isset( $entry['adapter'] ) || isset( $entry['item_id'] ) ) {
				$row = array(
					'adapter'       => (string) ( $entry['adapter'] ?? '' ),
					'adapter_label' => (string) ( $entry['adapter_label'] ?? '' ),
					'item_id'       => $entry['item_id'] ?? '',
					'item_label'    => (string) ( $entry['item_label'] ?? '' ),
					'credits'       => (int) ( $entry['credits'] ?? 0 ),
				);
				// Direct Stripe/PayPal packs store price on the row; dropping
				// these fields made every direct pack read as $0 and hid the
				// gateway buttons on the dashboard Credits tab.
				if ( isset( $entry['price_cents'] ) ) {
					$row['price_cents'] = (int) $entry['price_cents'];
				}
				if ( ! empty( $entry['currency'] ) ) {
					$row['currency'] = (string) $entry['currency'];
				}
				$flat[] = $row;
				continue;
			}

			// Nested: adapter slug => [ item_id => credits ]. No labels are
			// stored in this shape; consumers resolve them from the adapter.
			if ( is_string( $key ) ) {
				foreach ( $entry as $item_id => $credits ) {
					if ( ! is_scalar( $credits ) ) {
						continue;
					}
					$flat[] = array(
						'adapter'       => $key,
						'adapter_label' => '',
						'item_id'       => (int) $item_id,
						'item_label'    => '',
						'credits'       => (int) $credits,
					);
				}
			}
		}

		return $flat;
	}
}

if ( ! function_exists( 'wb_listora_decode_text' ) ) {
	/**
	 * Decode a human-facing string for output over REST.
	 *
	 * Every human-facing string in the API leaves decoded, because native
	 * clients render plain text. Term names arrive already encoded (KSES runs in
	 * wp_insert_term()). For API payloads only; templates still escape.
	 *
	 * @since 1.6.0
	 *
	 * @param mixed $text Value to decode; non-strings pass through as ''.
	 * @return string Decoded text.
	 */
	function wb_listora_decode_text( $text ) {
		if ( ! is_string( $text ) ) {
			return '';
		}

		return html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}
}

if ( ! function_exists( 'wb_listora_render_icon' ) ) {

	/**
	 * Render a Lucide icon as inline SVG.
	 *
	 * Public surface so Pro and add-ons draw the same icons without touching
	 * Core\Lucide_Icons (`<i data-lucide>` does not work on the frontend, where
	 * lucide.min.js is not loaded). Unknown names return ''; G10 in
	 * `bin/audit-guardrails.sh` stops pickers offering them.
	 *
	 * @since 1.6.0
	 *
	 * @param string $name Lucide icon name, kebab-case (e.g. `map-pin`).
	 * @param int    $size Width and height in pixels. Default 24.
	 * @return string Inline SVG markup, or '' when the icon is unknown.
	 */
	function wb_listora_render_icon( $name, $size = 24 ) {
		if ( ! class_exists( '\WBListora\Core\Lucide_Icons' ) ) {
			return '';
		}

		return \WBListora\Core\Lucide_Icons::render( (string) $name, (int) $size );
	}
}

if ( ! function_exists( 'wb_listora_get_icon_choices' ) ) {

	/**
	 * Every icon name the renderer can draw.
	 *
	 * The source for every icon picker, so no picker offers an icon that will
	 * not render.
	 *
	 * @since 1.6.0
	 *
	 * @return string[] Icon names, alphabetical.
	 */
	function wb_listora_get_icon_choices() {
		if ( ! class_exists( '\WBListora\Core\Lucide_Icons' ) ) {
			return array();
		}

		return \WBListora\Core\Lucide_Icons::get_names();
	}
}

if ( ! function_exists( 'wb_listora_automation_payload_listing' ) ) {

	/**
	 * Canonical listing payload for an automation trigger.
	 *
	 * The documented surface for Pro and add-ons. INV-3 forbids naming
	 * \WBListora\Automation\Payload directly, and Pro's own private builder
	 * is exactly what this replaces.
	 *
	 * @since 1.7.0
	 *
	 * @param int $listing_id Listing post ID.
	 * @return array<string, mixed>|null Null when the listing does not exist.
	 */
	function wb_listora_automation_payload_listing( $listing_id ) {
		return \WBListora\Automation\Payload::listing( $listing_id );
	}
}

if ( ! function_exists( 'wb_listora_automation_payload_review' ) ) {

	/**
	 * Canonical review payload for an automation trigger.
	 *
	 * @since 1.7.0
	 *
	 * @param int $review_id Review ID.
	 * @return array<string, mixed>|null Null when the review does not exist.
	 */
	function wb_listora_automation_payload_review( $review_id ) {
		return \WBListora\Automation\Payload::review( $review_id );
	}
}

if ( ! function_exists( 'wb_listora_automation_payload_claim' ) ) {

	/**
	 * Canonical claim payload for an automation trigger.
	 *
	 * @since 1.7.0
	 *
	 * @param int $claim_id Claim ID.
	 * @return array<string, mixed>|null Null when the claim does not exist.
	 */
	function wb_listora_automation_payload_claim( $claim_id ) {
		return \WBListora\Automation\Payload::claim( $claim_id );
	}
}

if ( ! function_exists( 'wb_listora_automation_payload_user' ) ) {

	/**
	 * Canonical user payload for an automation trigger.
	 *
	 * Allow-listed fields only — see Payload::user().
	 *
	 * @since 1.7.0
	 *
	 * @param int $user_id User ID.
	 * @return array<string, mixed>|null Null when the user does not exist.
	 */
	function wb_listora_automation_payload_user( $user_id ) {
		return \WBListora\Automation\Payload::user( $user_id );
	}
}
