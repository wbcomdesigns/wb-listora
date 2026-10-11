<?php
/**
 * Settings Page — WP Settings API with tabbed interface.
 *
 * @package WBListora\Admin
 */

namespace WBListora\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the plugin settings page with 7 tabs.
 */
class Settings_Page {

	/**
	 * Option name.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'wb_listora_settings';

	/**
	 * Register settings.
	 */
	public static function register() {
		// Wire the email-template editor (AUD-F8): admin UI (render hook, save
		// handler, asset enqueue) + the per-event subject/body read-back
		// filters. `register()` is hooked once on `admin_init`, so this runs a
		// single time per admin request.
		Email_Templates_Page::init();

		register_setting(
			'wb_listora_settings_group',
			self::OPTION_KEY,
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				// ~40 keys incl. secrets, only read on admin/REST — keep it out
				// of the autoloaded option cache (WP 6.6+ autoload arg).
				'autoload'          => false,
			)
		);

		// Credits overflow cost & purchase URL live in top-level options so
		// they can be read by the Credits SDK registry at file-load time
		// (before wb_listora_settings is hydrated). Registered in the same
		// option group so the Submissions tab form persists them via options.php.
		register_setting(
			'wb_listora_settings_group',
			\WBListora\Core\Listing_Limits::OVERFLOW_COST_OPTION,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( \WBListora\Core\Listing_Limits::class, 'sanitize_overflow_cost' ),
				'default'           => 10,
			)
		);

		// Low credit balance alert threshold — top-level option so the SDK can
		// read it without loading the full settings array. Registered in the
		// same group so the Credits tab persists it via options.php.
		register_setting(
			'wb_listora_settings_group',
			'wb_listora_low_credit_threshold',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 5,
			)
		);

		// App sign-in switch — a top-level option rather than a `wb_listora_settings`
		// key because `App_Credentials::is_enabled()` is consulted on an
		// unauthenticated REST request, before the settings array is worth
		// hydrating. Registered in the same group so the Advanced tab persists it.
		register_setting(
			'wb_listora_settings_group',
			'wb_listora_app_password_login',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( __CLASS__, 'sanitize_app_password_login' ),
				'default'           => true,
			)
		);

		// Page-mapping options — registered dynamically per Page_Registry entry
		// so each registered page (Free's 3 + Pro's compare + future plugins')
		// gets its own first-class option in the same group. Saved together
		// with the General tab's "Save Changes" submit.
		if ( class_exists( '\\WBListora\\Core\\Page_Registry' ) ) {
			foreach ( \WBListora\Core\Page_Registry::all() as $page ) {
				register_setting(
					'wb_listora_settings_group',
					(string) $page['option_key'],
					array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'default'           => 0,
					)
				);
			}
		}
	}

	/**
	 * Sanitize the app sign-in switch.
	 *
	 * The form pairs the checkbox with a hidden `0`, so an unchecked box still
	 * posts a value — without it `options.php` would receive nothing and the
	 * option would keep its previous (true) value, making the switch one-way.
	 *
	 * @param mixed $value Raw posted value.
	 * @return bool
	 */
	public static function sanitize_app_password_login( $value ) {
		return (bool) $value;
	}

	/**
	 * [min, max] for every integer setting whose <input type="number">
	 * declares both a `min` and a `max`: the single source for the render
	 * methods (HTML attributes) and sanitize() (server-side clamp).
	 *
	 * A key absent here (e.g. default_expiration, which only declares `min`)
	 * keeps the plain (int) cast in sanitize(); its floor of 0 is what an
	 * empty field casts to anyway.
	 *
	 * Public so the tab classes that render these inputs read the same bounds.
	 *
	 * @return array<string, array{0:int,1:int}>
	 */
	public static function bounded_int_ranges() {
		return array(
			'per_page'            => array( 1, 100 ),
			'renewal_window_days' => array( 1, 365 ),
			'near_me_radius'      => array( 1, 500 ),
			'map_default_zoom'    => array( 1, 20 ),
			'map_max_markers'     => array( 50, 5000 ),
			'max_upload_size'     => array( 1, 50 ), // @deprecated 1.9.1 - no longer read; kept two minors (production rule 1).
			'max_gallery_images'  => array( 1, 100 ),
			'search_cache_ttl'    => array( 0, 120 ),
			'facet_cache_ttl'     => array( 0, 120 ), // @deprecated 1.9.1 - no longer read; kept two minors (production rule 1).
		);
	}

	/**
	 * Sanitize settings on save.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$defaults = wb_listora_get_default_settings();
		$old      = get_option( self::OPTION_KEY, array() );

		// Start from existing values so unsubmitted keys (e.g. booleans on
		// tabs that weren't the one just saved) keep their current value.
		// Without this, saving the General tab would zero out every boolean
		// on Submissions/Maps/Reviews/etc — checkboxes aren't included in
		// POST unless they're on the form being submitted.
		$sanitized = is_array( $old ) ? $old : array();

		foreach ( $defaults as $key => $default ) {
			if ( ! isset( $input[ $key ] ) ) {
				// Key absent from POST — keep previous value, fall back to
				// default only if there's nothing previous.
				if ( ! array_key_exists( $key, $sanitized ) ) {
					$sanitized[ $key ] = $default;
				}
				continue;
			}

			$value = $input[ $key ];

			if ( is_bool( $default ) ) {
				$sanitized[ $key ] = (bool) $value;
			} elseif ( is_int( $default ) ) {
				/*
				 * HTML min/max is client-side only. Clamp here so an empty field (cast to
				 * 0, e.g. map_max_markers=0 collapsing every map to one marker) or a
				 * non-browser client (REST, settings import) cannot store an out-of-range
				 * value. An empty field falls back to the default.
				 */
				$bounds = self::bounded_int_ranges();
				if ( isset( $bounds[ $key ] ) ) {
					list( $min, $max ) = $bounds[ $key ];
					$sanitized[ $key ] = ( '' === trim( (string) $value ) )
						? $default
						: max( $min, min( $max, (int) $value ) );
				} else {
					$sanitized[ $key ] = (int) $value;
				}
			} elseif ( is_float( $default ) ) {
				$sanitized[ $key ] = (float) $value;
			} elseif ( 'map_tile_url' === $key ) {
				/*
				 * A tile template, not an ordinary URL: the {z}/{x}/{y}
				 * placeholders have to survive or Leaflet has nothing to
				 * substitute. esc_url_raw() strips curly braces, so it cannot
				 * be used on its own here — see wb_listora_sanitize_tile_url().
				 */
				$sanitized[ $key ] = wb_listora_sanitize_tile_url( $value );
				// A keyed preset saved with its placeholder would load no tile.
				if ( false !== strpos( $sanitized[ $key ], 'YOUR_KEY' ) ) {
					add_settings_error(
						'wb_listora_settings',
						'listora_tile_key_missing',
						__( 'Map tile URL not saved: replace YOUR_KEY with the key from your tile provider.', 'wb-listora' )
					);
					$sanitized[ $key ] = (string) ( $old[ $key ] ?? '' );
				}
			} elseif ( 'map_tile_attribution' === $key ) {
				// Providers require credit and it is usually a link, so allow
				// the small HTML wp_kses_post permits rather than flattening it.
				$sanitized[ $key ] = wp_kses_post( (string) $value );
			} else {
				$sanitized[ $key ] = sanitize_text_field( $value );
			}
		}

		if ( isset( $input['notifications'] ) && is_array( $input['notifications'] ) ) {
			$sanitized['notifications'] = array_map( 'absint', $input['notifications'] );
		} elseif ( isset( $old['notifications'] ) ) {
			$sanitized['notifications'] = $old['notifications'];
		}

		/*
		 * Social platforms allowlist, stored as a list of slugs. Only slugs
		 * Field::social_link_platforms() still offers survive.
		 *
		 * The submissions tab always posts a hidden empty marker, so an absent
		 * key here means the owner is on another tab and the existing value
		 * must be preserved rather than wiped.
		 */
		if ( isset( $input['social_platforms'] ) ) {
			$known                         = array_keys( \WBListora\Core\Field::social_link_platforms_all() );
			$posted                        = array_map( 'sanitize_key', (array) $input['social_platforms'] );
			$sanitized['social_platforms'] = array_values( array_intersect( $posted, $known ) );
		} elseif ( isset( $old['social_platforms'] ) ) {
			$sanitized['social_platforms'] = $old['social_platforms'];
		}

		if ( isset( $input['reviews'] ) && is_array( $input['reviews'] ) ) {
			$reviews_raw          = $input['reviews'];
			$sanitized['reviews'] = array(
				'auto_approve'    => ! empty( $reviews_raw['auto_approve'] ),
				// Reviews always require login — create_review_permissions()
				// hard-requires it and the author is always the current user.
				// Anonymous reviews would need schema + capture-UI work, so the
				// toggle was removed (it never changed behavior). Stored as true
				// so any reader reflects the enforced reality.
				'require_login'   => true,
				'min_length'      => isset( $reviews_raw['min_length'] ) ? absint( $reviews_raw['min_length'] ) : 20,
				'one_per_listing' => ! empty( $reviews_raw['one_per_listing'] ),
				'allow_reply'     => ! empty( $reviews_raw['allow_reply'] ),
			);
		} elseif ( isset( $old['reviews'] ) ) {
			$sanitized['reviews'] = $old['reviews'];
		}

		// Credit cost fields: force non-negative integers.
		if ( isset( $input['featured_credit_cost'] ) ) {
			$sanitized['featured_credit_cost'] = absint( $input['featured_credit_cost'] );
		}
		if ( isset( $input['featured_duration_days'] ) ) {
			// Non-negative integer. 0 = permanent.
			$sanitized['featured_duration_days'] = max( 0, (int) $input['featured_duration_days'] );
		}
		if ( isset( $input['default_listing_credit_cost'] ) ) {
			$sanitized['default_listing_credit_cost'] = absint( $input['default_listing_credit_cost'] );
		}

		// Validate captcha_provider against allowed values.
		$allowed_captcha = array( 'none', 'recaptcha_v3', 'cloudflare_turnstile' );
		if ( ! in_array( $sanitized['captcha_provider'] ?? 'none', $allowed_captcha, true ) ) {
			$sanitized['captcha_provider'] = 'none';
		}

		// Listing limits per role — merge the numeric map with the "Unlimited"
		// checkbox map. If Unlimited is checked, save -1 regardless of the
		// number field. Preserve when the tab isn't submitted.
		$has_role_map      = isset( $input['listing_limits_per_role'] ) && is_array( $input['listing_limits_per_role'] );
		$has_unlimited_map = isset( $input['listing_limits_unlimited'] ) && is_array( $input['listing_limits_unlimited'] );

		if ( $has_role_map || $has_unlimited_map ) {
			// Start from sanitized numeric values (clamped to >= 0 via the merged map below).
			$raw_map = $has_role_map ? $input['listing_limits_per_role'] : array();

			// Overlay "unlimited" flags as -1 so sanitize_map's clamp (>= -1) keeps them.
			if ( $has_unlimited_map ) {
				foreach ( $input['listing_limits_unlimited'] as $role_slug => $unlimited ) {
					if ( ! empty( $unlimited ) ) {
						$raw_map[ $role_slug ] = -1;
					}
				}
			}

			$sanitized['listing_limits_per_role'] = \WBListora\Core\Listing_Limits::sanitize_map( $raw_map );
		} elseif ( isset( $old['listing_limits_per_role'] ) ) {
			$sanitized['listing_limits_per_role'] = $old['listing_limits_per_role'];
		}

		if ( isset( $input['listing_limits_default'] ) || isset( $input['listing_limits_default_unlimited'] ) ) {
			if ( ! empty( $input['listing_limits_default_unlimited'] ) ) {
				$sanitized['listing_limits_default'] = -1;
			} else {
				$raw_default = $input['listing_limits_default'] ?? '';
				// A blank Default limit field means "no cap" (unlimited), NOT 0.
				// sanitize_default('') casts to (int) 0, and 0 BLOCKS every
				// non-admin submission ("limit of 0 listings") — the same footgun
				// already fixed for the per-role fields. Treat blank as -1 so a
				// stray Save with an empty field can't lock the submission funnel.
				$sanitized['listing_limits_default'] = ( '' === trim( (string) $raw_default ) )
					? -1
					: \WBListora\Core\Listing_Limits::sanitize_default( $raw_default );
			}
		} elseif ( isset( $old['listing_limits_default'] ) ) {
			$sanitized['listing_limits_default'] = $old['listing_limits_default'];
		}

		// Limit period — whitelist to known values, preserve when saving other tabs.
		if ( isset( $input['listing_limits_period'] ) ) {
			$period                             = sanitize_key( (string) $input['listing_limits_period'] );
			$sanitized['listing_limits_period'] = in_array( $period, array( 'lifetime', 'calendar_month', 'rolling_30d' ), true )
				? $period
				: 'lifetime';
		} elseif ( isset( $old['listing_limits_period'] ) ) {
			$sanitized['listing_limits_period'] = $old['listing_limits_period'];
		}

		// Beyond-limit behavior — whitelist.
		if ( isset( $input['listing_beyond_limit_behavior'] ) ) {
			$behavior                                   = sanitize_key( (string) $input['listing_beyond_limit_behavior'] );
			$sanitized['listing_beyond_limit_behavior'] = in_array( $behavior, array( 'block', 'credits' ), true )
				? $behavior
				: 'block';
		} elseif ( isset( $old['listing_beyond_limit_behavior'] ) ) {
			$sanitized['listing_beyond_limit_behavior'] = $old['listing_beyond_limit_behavior'];
		}

		// Legal / App Store links — the generic string loop above would run
		// sanitize_text_field on these, which mangles URLs and emails. Re-apply
		// the correct sanitizers so the app-config `legal` block stays valid.
		if ( isset( $input['legal_terms_page_id'] ) ) {
			$sanitized['legal_terms_page_id'] = absint( $input['legal_terms_page_id'] );
		}

		if ( isset( $input['legal_terms_url'] ) ) {
			$sanitized['legal_terms_url'] = esc_url_raw( trim( (string) $input['legal_terms_url'] ) );
		}
		if ( isset( $input['legal_community_guidelines_url'] ) ) {
			$sanitized['legal_community_guidelines_url'] = esc_url_raw( trim( (string) $input['legal_community_guidelines_url'] ) );
		}
		if ( isset( $input['legal_abuse_contact_email'] ) ) {
			$sanitized['legal_abuse_contact_email'] = sanitize_email( trim( (string) $input['legal_abuse_contact_email'] ) );
		}

		// Flush rewrites if slugs changed.
		if ( ( $old['listing_slug'] ?? '' ) !== ( $sanitized['listing_slug'] ?? '' ) ) {
			add_action( 'shutdown', 'flush_rewrite_rules' );
		}

		return $sanitized;
	}

	/**
	 * Get tab definitions grouped for sidebar navigation.
	 *
	 * @return array[] {
	 *     Groups of tabs with labels and icons.
	 *
	 *     @type string $group_label Group heading text.
	 *     @type array  $tabs        Array of tab_id => { label, icon, desc }.
	 * }
	 */
	public static function get_nav_groups() {
		// Grouped by the job an owner is doing: running the directory, earning
		// from it, talking to members, and the rest. Group keys stay stable for
		// the filter below ('general' is Directory).
		$groups = array(
			'general'       => array(
				'group_label' => __( 'Directory', 'wb-listora' ),
				'tabs'        => array(
					'general'     => array(
						'label' => __( 'General', 'wb-listora' ),
						'icon'  => 'settings',
						'desc'  => __( 'Listings per page, pages, currency and how long listings stay live.', 'wb-listora' ),
					),
					'features'    => array(
						'label' => __( 'Features', 'wb-listora' ),
						'icon'  => 'toggle-right',
						'desc'  => __( 'Turn whole features on or off. A feature that is off disappears from the site.', 'wb-listora' ),
					),
					'submissions' => array(
						'label' => __( 'Submissions', 'wb-listora' ),
						'icon'  => 'file-plus',
						'desc'  => __( 'How members add listings, and what needs your approval.', 'wb-listora' ),
					),
					'reviews'     => array(
						'label' => __( 'Reviews', 'wb-listora' ),
						'icon'  => 'message-circle',
						'desc'  => __( 'Who can review, what needs approval, and owner replies.', 'wb-listora' ),
					),
					'maps'        => array(
						'label' => __( 'Maps', 'wb-listora' ),
						'icon'  => 'map',
						'desc'  => __( 'The map provider and where maps start.', 'wb-listora' ),
					),
				),
			),
			'monetization'  => array(
				'group_label' => __( 'Monetization', 'wb-listora' ),
				'tabs'        => array(
					'credits' => array(
						'label' => __( 'Credits', 'wb-listora' ),
						'icon'  => 'coins',
						'desc'  => __( 'What listings cost, limits per member, and how members pay.', 'wb-listora' ),
					),
				),
			),
			'communication' => array(
				'group_label' => __( 'Communication', 'wb-listora' ),
				'tabs'        => array(
					'notifications' => array(
						'label' => __( 'Notifications', 'wb-listora' ),
						'icon'  => 'bell',
						'desc'  => __( 'Which emails go out, to whom, and what they say.', 'wb-listora' ),
					),
				),
			),
			'advanced'      => array(
				'group_label' => __( 'Advanced', 'wb-listora' ),
				'tabs'        => array(
					'integrations'  => array(
						'label' => __( 'Integrations', 'wb-listora' ),
						'icon'  => 'blocks',
						'desc'  => __( 'Plugins Listora works with, and the ones connected now.', 'wb-listora' ),
					),
					'import-export' => array(
						'label' => __( 'Import / Export', 'wb-listora' ),
						'icon'  => 'arrow-left-right',
						'desc'  => __( 'Bring listings in, take them out, and move settings between sites.', 'wb-listora' ),
					),
					'migration'     => array(
						'label' => __( 'Migration', 'wb-listora' ),
						'icon'  => 'database',
						'desc'  => __( 'Import listings from other directory plugins.', 'wb-listora' ),
					),
					'advanced'      => array(
						'label' => __( 'Advanced', 'wb-listora' ),
						'icon'  => 'sliders',
						'desc'  => __( 'Setup wizard, mobile app, cache, maintenance and data.', 'wb-listora' ),
					),
				),
			),
		);

		/**
		 * Filter settings nav groups. Pro can inject tabs into existing groups
		 * or add entirely new groups.
		 *
		 * @param array $groups Nav groups.
		 */
		$groups = (array) apply_filters( 'wb_listora_settings_nav_groups', $groups );

		// A group an extension created without a label (say, tabs added to the
		// retired 'pro' key) still renders, under a neutral heading.
		foreach ( $groups as $key => $group ) {
			if ( empty( $group['tabs'] ) ) {
				unset( $groups[ $key ] );
				continue;
			}
			if ( empty( $group['group_label'] ) ) {
				$groups[ $key ]['group_label'] = __( 'More', 'wb-listora' );
			}
		}
		return $groups;
	}

	/**
	 * Build a flat tabs array from grouped nav structure.
	 *
	 * Provides backward compatibility with the `wb_listora_settings_tabs` filter.
	 *
	 * @return array tab_id => label
	 */
	private static function get_flat_tabs() {
		$groups = self::get_nav_groups();
		$tabs   = array();

		foreach ( $groups as $group ) {
			foreach ( $group['tabs'] as $tab_id => $tab ) {
				$tabs[ $tab_id ] = $tab['label'];
			}
		}

		/**
		 * Filter settings tabs (backward compat). Pro can add tabs here.
		 */
		return apply_filters( 'wb_listora_settings_tabs', $tabs );
	}

	/**
	 * Get the documentation URL for a settings tab.
	 *
	 * Each entry maps a tab_id to the doc-site slug authored under
	 * `docs/website/settings/{slug}.md` (verified against `docs_config.json`).
	 * Tabs without a dedicated settings doc fall back to general-settings.
	 *
	 * The `wb_listora_docs_url` filter lets site owners point at a custom
	 * docs host or override per-tab URLs without forking the plugin.
	 *
	 * @param string $tab_id Tab identifier.
	 * @return string Documentation URL.
	 */
	private static function get_docs_url( $tab_id ) {
		$map = array(
			'general'       => 'general-settings',
			'features'      => 'features-toggles',
			'maps'          => 'map-settings',
			'submissions'   => 'submission-settings',
			'reviews'       => 'reviews-settings',
			'credits'       => 'credits-and-plans',
			'notifications' => 'notifications-settings',
			'advanced'      => 'advanced-settings',
			'integrations'  => 'integrations',
			'import-export' => 'import-export-settings',
			'migration'     => 'import-export-settings',
			// Pro-injected sections (via wb_listora_settings_tabs). Their docs
			// live on the same store docs page, so Free maps them directly.
			'pagination'    => 'infinite-scroll',
			'seo'           => 'seo-pages',
			'visibility'    => 'coming-soon',
			'white-label'   => 'white-label',
		);

		$section = $map[ $tab_id ] ?? 'general-settings';
		// The store renders all product docs on ONE page; sections are hash
		// anchors of the form {slug}-ls.
		$url = 'https://store.wbcomdesigns.com/listora/docs/#' . $section . '-ls';

		/**
		 * Filter the documentation URL for a settings tab.
		 *
		 * @param string $url     Documentation URL.
		 * @param string $tab_id  Tab identifier.
		 * @param string $section Mapped doc slug.
		 */
		return apply_filters( 'wb_listora_docs_url', $url, $tab_id, $section );
	}

	/**
	 * Get the render callback for a given tab.
	 *
	 * @param string $tab_id Tab identifier.
	 * @return callable|null Renderer or null.
	 */
	private static function get_tab_renderer( $tab_id ) {
		$map = array(
			'general'       => array( __CLASS__, 'render_general_tab' ),
			'features'      => array( Features_Settings_Tab::class, 'render' ),
			'maps'          => array( Map_Settings_Tab::class, 'render' ),
			'submissions'   => array( __CLASS__, 'render_submissions_tab' ),
			'reviews'       => array( __CLASS__, 'render_reviews_tab' ),
			'credits'       => array( Credits_Settings_Tab::class, 'render' ),
			'notifications' => array( __CLASS__, 'render_notifications_tab' ),
			'advanced'      => array( Advanced_Settings_Tab::class, 'render' ),
			'integrations'  => array( __CLASS__, 'render_integrations_tab' ),
			'import-export' => array( Import_Export_Tabs::class, 'render_import_export' ),
			'migration'     => array( Import_Export_Tabs::class, 'render_migration' ),
		);

		return $map[ $tab_id ] ?? null;
	}

	/**
	 * Tabs with no options.php form of their own: they hold their own forms
	 * (Features, License) or only actions (Import / Export, Integrations).
	 *
	 * @return string[]
	 */
	private static function skip_form_tabs() {
		return (array) apply_filters( 'wb_listora_settings_skip_form_tabs', array( 'import-export', 'migration', 'features', 'integrations' ) );
	}

	/**
	 * Render the settings page with Pattern A sidebar layout.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_listora_settings' ) ) {
			echo '<div class="wrap"><h1>' . /* translators: %s: product name */ esc_html( sprintf( __( '%s Settings', 'wb-listora' ), wb_listora_brand_name() ) ) . '</h1><div class="notice listora-notice notice-error"><p>' . esc_html__( 'You do not have permission to access this page. Ask a site administrator to grant you the "manage_listora_settings" capability.', 'wb-listora' ) . '</p></div></div>';
			return;
		}

		$groups    = self::get_nav_groups();
		$flat_tabs = self::get_flat_tabs();
		$tab_keys  = array_keys( $flat_tabs );
		$first_tab = reset( $tab_keys );

		// Determine the active tab server-side so the page works without JS:
		// each tab is its own `?tab=X` URL (plus `#X` for smooth scroll). When
		// settings-nav.js loads it toggles `.is-active` in place instead.
		$skip_form_tabs = self::skip_form_tabs();

		$default_tab_id = '';
		foreach ( $flat_tabs as $tab_id => $tab ) {
			if ( ! in_array( $tab_id, $skip_form_tabs, true ) ) {
				$default_tab_id = $tab_id;
				break;
			}
		}

		// `tab` query param overrides the default when valid. Read-only,
		// so no nonce; only known tab keys are honored, the rest fall
		// through to the default.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation; sanitized + whitelisted.
		$requested_tab_id = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		$active_tab_id    = ( $requested_tab_id && isset( $flat_tabs[ $requested_tab_id ] ) ) ? $requested_tab_id : $default_tab_id;

		?>
		<div class="wrap wb-listora-admin">
			<?php
			wb_listora_render_admin_header(
				array(
					'title'       => __( 'Settings', 'wb-listora' ),
					'icon'        => 'settings',
					'description' => __( 'How your directory works, grouped by task.', 'wb-listora' ),
				)
			);
			?>
		<div class="listora-settings-wrap">
			<?php // ── Sidebar ── ?>
			<nav class="listora-settings-sidebar" aria-label="<?php esc_attr_e( 'Settings sections', 'wb-listora' ); ?>">
				<?php foreach ( $groups as $group ) : ?>
				<div class="listora-settings-nav-group">
					<span class="listora-settings-nav-group__label"><?php echo esc_html( $group['group_label'] ); ?></span>
					<?php
					foreach ( $group['tabs'] as $tab_id => $tab ) :
						// `?tab=X#X` — query string lets the server activate the
						// matching pane on reload (no-JS or broken-JS fallback);
						// hash kept for in-place anchor + back/forward parity
						// when JS is alive.
						$tab_url = add_query_arg(
							array(
								'page' => 'listora-settings',
								'tab'  => $tab_id,
							),
							admin_url( 'admin.php' )
						) . '#' . $tab_id;
						?>
					<a
						class="listora-settings-nav-item<?php echo $tab_id === $active_tab_id ? ' is-active' : ''; ?>"
						href="<?php echo esc_url( $tab_url ); ?>"
						data-section="<?php echo esc_attr( $tab_id ); ?>"
					>
						<i data-lucide="<?php echo esc_attr( $tab['icon'] ); ?>"></i>
						<?php echo esc_html( $tab['label'] ); ?>
						<?php
						$pro_feature = isset( $tab['pro_feature'] ) ? (string) $tab['pro_feature'] : '';
						if ( ! empty( $tab['pro'] ) && ! wb_listora_is_pro_active() ) :
							?>
							<span
								class="listora-pro-badge"
								<?php
								if ( $pro_feature ) :
									?>
									data-pro-feature="<?php echo esc_attr( $pro_feature ); ?>"<?php endif; ?>
							><?php esc_html_e( 'Pro', 'wb-listora' ); ?></span>
						<?php endif; ?>
					</a>
					<?php endforeach; ?>
				</div>
				<?php endforeach; ?>
			</nav>

			<?php // ── Content ── ?>
			<div class="listora-settings-content">
				<?php
				// What the save did, from options.php's settings errors: every section
				// on the tab saves on the one Save, so a section that refused (a
				// malformed Stripe key) must say so instead of a blanket "Settings
				// saved". All-success reads as one line. WordPress's own
				// settings_errors() markup is hidden on Listora screens, so it is
				// printed here.
				if ( isset( $_GET['settings-updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag, no state change.
					$listora_save_notices = array_filter(
						get_settings_errors(),
						static function ( $notice ) {
							return ! in_array( $notice['type'], array( 'success', 'updated' ), true );
						}
					);
					if ( empty( $listora_save_notices ) ) :
						?>
				<div class="notice listora-notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'wb-listora' ); ?></p>
				</div>
						<?php
					endif;
					foreach ( $listora_save_notices as $listora_notice ) :
						?>
				<div class="notice listora-notice notice-<?php echo 'error' === $listora_notice['type'] ? 'error' : 'warning'; ?> is-dismissible" role="alert">
					<p><?php echo esc_html( wp_strip_all_tags( $listora_notice['message'] ) ); ?></p>
				</div>
						<?php
					endforeach;
				endif;
				?>

				<?php
				// Settings-reset confirmation. Destructive and irreversible, so it
				// states what happened rather than leaving the owner to infer it.
				if ( isset( $_GET['listora_reset'] ) && '1' === $_GET['listora_reset'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag, no state change.
					?>
				<div class="notice listora-notice notice-success is-dismissible">
					<p><?php esc_html_e( 'This tab was reset to its defaults. Other tabs kept their settings.', 'wb-listora' ); ?></p>
				</div>
				<?php endif; ?>

				<?php
				// Rebuild Search Index confirmation. The rebuild is batched on
				// cron rather than run inline, so the wording promises a start,
				// not a finished job — a 100k-listing site takes several ticks.
				if ( isset( $_GET['listora_reindexed'] ) && '1' === $_GET['listora_reindexed'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag, no state change.
					?>
				<div class="notice listora-notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Search index rebuild scheduled. It runs in the background and may take a few minutes on large directories.', 'wb-listora' ); ?></p>
				</div>
				<?php endif; ?>

				<?php foreach ( $groups as $group ) : ?>
					<?php foreach ( $group['tabs'] as $tab_id => $tab ) : ?>
						<?php
						if ( in_array( $tab_id, $skip_form_tabs, true ) ) {
							continue;
						}
						// `$active_tab_id` honors `?tab=` from the URL and falls
						// back to the first non-skipped tab — keeps nav and
						// content in sync server-side, with or without JS.
						$_section_classes = 'listora-settings-section' . ( $tab_id === $active_tab_id ? ' is-active' : '' );
						?>
					<div class="<?php echo esc_attr( $_section_classes ); ?>" id="section-<?php echo esc_attr( $tab_id ); ?>">
					<form method="post" action="options.php">
						<?php settings_fields( 'wb_listora_settings_group' ); ?>
						<div class="listora-tab-header">
							<div class="listora-tab-header__text">
								<p class="listora-tab-header__title"><?php echo esc_html( strtoupper( $tab['label'] ) ); ?></p>
								<?php if ( ! empty( $tab['desc'] ) ) : ?>
								<p class="listora-tab-header__desc"><?php echo esc_html( $tab['desc'] ); ?></p>
								<?php endif; ?>
							</div>
							<a href="<?php echo esc_url( self::get_docs_url( $tab_id ) ); ?>" target="_blank" rel="noopener noreferrer" class="listora-docs-link">
								<i data-lucide="book-open"></i> <?php esc_html_e( 'Documentation', 'wb-listora' ); ?>
							</a>
						</div>
						<?php
						$renderer = self::get_tab_renderer( $tab_id );
						if ( $renderer ) {
							call_user_func( $renderer );
						}

						/**
						 * Fires to render Pro tab content.
						 *
						 * @param string $tab_id Current tab being rendered.
						 */
						do_action( 'wb_listora_settings_tab_content', $tab_id );
						?>
						<div class="listora-settings-section__footer">
							<button type="button" class="listora-btn wp-element-button listora-btn--ghost listora-btn--sm" data-listora-action="reset-defaults" data-tab-label="<?php echo esc_attr( $tab['label'] ); ?>">
								<i data-lucide="rotate-ccw" aria-hidden="true"></i> <?php esc_html_e( 'Reset this tab', 'wb-listora' ); ?>
							</button>
							<button type="submit" class="listora-btn wp-element-button listora-btn--primary">
								<i data-lucide="save"></i> <?php esc_html_e( 'Save Changes', 'wb-listora' ); ?>
							</button>
						</div>
					</form>

						<?php
						/**
						 * Fires AFTER the outer options.php form closes, but still
						 * inside the active `.listora-settings-section`.
						 *
						 * Use this hook (not `wb_listora_settings_tab_content`) to
						 * render standalone forms that POST to admin-post.php or
						 * admin_init — anything wrapped in a `<form>` rendered
						 * inside the outer-form hook is silently dropped by HTML5's
						 * nested-form parser AND collides with options.php's
						 * `action=update` + `_wpnonce` hidden inputs on submit.
						 *
						 * @since 1.0.5
						 *
						 * @param string $tab_id Current tab being rendered.
						 */
						do_action( 'wb_listora_settings_tab_content_after_form', $tab_id );
						?>
				</div>
					<?php endforeach; ?>
				<?php endforeach; ?>

				<?php
				// ── Non-form sections (Import/Export, Migration, Pro CRUD tabs) ──.
				foreach ( $groups as $group ) :
					foreach ( $group['tabs'] as $tab_id => $tab ) :
						if ( ! in_array( $tab_id, $skip_form_tabs, true ) ) {
							continue;
						}
						$_section_classes = 'listora-settings-section' . ( $tab_id === $active_tab_id ? ' is-active' : '' );
						?>
						<div class="<?php echo esc_attr( $_section_classes ); ?>" id="section-<?php echo esc_attr( $tab_id ); ?>">
							<div class="listora-tab-header">
								<div class="listora-tab-header__text">
									<p class="listora-tab-header__title"><?php echo esc_html( strtoupper( $tab['label'] ) ); ?></p>
									<?php if ( ! empty( $tab['desc'] ) ) : ?>
									<p class="listora-tab-header__desc"><?php echo esc_html( $tab['desc'] ); ?></p>
									<?php endif; ?>
								</div>
							</div>
							<?php
							$renderer = self::get_tab_renderer( $tab_id );
							if ( $renderer ) {
								call_user_func( $renderer );
							}
							do_action( 'wb_listora_settings_tab_content', $tab_id );
							?>
						</div>
					<?php endforeach; ?>
				<?php endforeach; ?>

			</div>
		</div>
		</div><?php // close .wrap.wb-listora-admin ?>

		<?php
		/*
		 * CSV import / export, JSON settings export / import and Reset-to-Defaults
		 * handlers live in `assets/js/admin/settings-page.js` (no inline JS rule).
		 * Translatable strings, REST URLs and nonces flow in via the
		 * `wbListoraSettings` object localized in class-assets.php.
		 */
		?>
		<?php
	}

	// ─── Tab Renderers ───

	private static function render_general_tab() {
		$s      = get_option( self::OPTION_KEY, array() );
		$d      = wb_listora_get_default_settings();
		$bounds = self::bounded_int_ranges();

		// One list, shared with the price formatter, so the dropdown can never
		// offer a currency that renders as a bare ISO code.
		$currencies = wb_listora_get_currencies();
		$current    = $s['currency'] ?? $d['currency'];
		$opt        = esc_attr( self::OPTION_KEY );
		?>
		<div class="listora-settings-pane">

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Basics', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Core directory configuration — how listings are paginated, accessed by URL, priced, and measured.', 'wb-listora' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="per_page"><?php esc_html_e( 'Listings per page', 'wb-listora' ); ?></label></th>
							<td>
								<input type="number" id="per_page" name="<?php echo esc_attr( $opt ); ?>[per_page]" value="<?php echo esc_attr( $s['per_page'] ?? $d['per_page'] ); ?>" min="<?php echo esc_attr( (string) $bounds['per_page'][0] ); ?>" max="<?php echo esc_attr( (string) $bounds['per_page'][1] ); ?>" class="small-text" />
								<p class="description"><?php esc_html_e( 'Number of listings shown per page in archive, search, and grid views.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<?php
						/**
						 * Rows added under General > Basics, after Listings per page
						 * (Pro adds Pagination style here).
						 *
						 * @since 1.9.0
						 */
						do_action( 'wb_listora_settings_general_rows' );
						?>
						<tr>
							<th scope="row"><label for="listing_slug"><?php esc_html_e( 'Listing URL slug', 'wb-listora' ); ?></label></th>
							<td>
								<input type="text" id="listing_slug" name="<?php echo esc_attr( $opt ); ?>[listing_slug]" value="<?php echo esc_attr( $s['listing_slug'] ?? $d['listing_slug'] ); ?>" class="regular-text" />
								<p class="description"><?php esc_html_e( 'Single listing URL segment (e.g. /listing/{slug}/). Changing this re-flushes rewrite rules.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="currency"><?php esc_html_e( 'Currency', 'wb-listora' ); ?></label></th>
							<td>
								<select id="currency" name="<?php echo esc_attr( $opt ); ?>[currency]" class="listora-currency-select">
									<?php foreach ( $currencies as $code => $symbol ) : ?>
										<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $current, $code ); ?>>
											<?php echo esc_html( sprintf( '%s (%s)', $code, $symbol ) ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Symbol shown alongside prices and pricing ranges on listing cards and detail pages.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Distance unit', 'wb-listora' ); ?></th>
							<td>
								<fieldset>
									<legend class="screen-reader-text"><?php esc_html_e( 'Distance unit', 'wb-listora' ); ?></legend>
									<div class="listora-field-group">
										<label><input type="radio" name="<?php echo esc_attr( $opt ); ?>[distance_unit]" value="km" <?php checked( $s['distance_unit'] ?? $d['distance_unit'], 'km' ); ?> /> <?php esc_html_e( 'Kilometers (km)', 'wb-listora' ); ?></label>
										<label><input type="radio" name="<?php echo esc_attr( $opt ); ?>[distance_unit]" value="mi" <?php checked( $s['distance_unit'] ?? $d['distance_unit'], 'mi' ); ?> /> <?php esc_html_e( 'Miles (mi)', 'wb-listora' ); ?></label>
									</div>
								</fieldset>
								<p class="description"><?php esc_html_e( 'Used for the distance shown on listing cards and search results.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="near_me_radius"><?php esc_html_e( 'Near me radius', 'wb-listora' ); ?></label></th>
							<td>
								<input type="number" id="near_me_radius" name="<?php echo esc_attr( $opt ); ?>[near_me_radius]" value="<?php echo esc_attr( $s['near_me_radius'] ?? $d['near_me_radius'] ); ?>" min="<?php echo esc_attr( (string) $bounds['near_me_radius'][0] ); ?>" max="<?php echo esc_attr( (string) $bounds['near_me_radius'][1] ); ?>" class="small-text" />
								<p class="description"><?php esc_html_e( 'How far "Near me" searches at first, in the distance unit above. Visitors can change it next to the search bar.', 'wb-listora' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

			<?php Pages_Settings::render_section(); ?>

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Listing Lifecycle', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'How long listings stay active and whether business owners can claim existing entries.', 'wb-listora' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Listing expiration', 'wb-listora' ); ?></th>
							<td>
								<fieldset>
									<legend class="screen-reader-text"><?php esc_html_e( 'Listing expiration', 'wb-listora' ); ?></legend>
									<label>
										<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[enable_expiration]" value="0" />
										<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[enable_expiration]" value="1" <?php checked( $s['enable_expiration'] ?? $d['enable_expiration'] ); ?> />
										<?php esc_html_e( 'Enable automatic listing expiration', 'wb-listora' ); ?>
									</label>
								</fieldset>
								<p class="description"><?php esc_html_e( 'Off by default: most directories keep listings until they are removed. When on, listings unpublish after their plan, type or default period, with reminder emails 7 days and 1 day before. Events and job deadlines end on their own date either way.', 'wb-listora' ); ?></p>
								<?php
								/**
								 * Filter the name of what turned expiration on, shown under the setting.
								 *
								 * @since 2.0.0
								 *
								 * @param string $reason Feature name, '' when the setting alone decides.
								 */
								$expiry_reason = (string) apply_filters( 'wb_listora_expiration_reason', '' );
								if ( '' !== $expiry_reason && wb_listora_expiration_enabled() && empty( $s['enable_expiration'] ) ) :
									?>
									<p class="description listora-settings__note">
										<?php
										/* translators: %s: feature name, e.g. "Pricing Plans" */
										echo esc_html( sprintf( __( 'Expiration is on while %s is active, so paid listings run for their plan\'s duration and can be renewed.', 'wb-listora' ), $expiry_reason ) );
										?>
									</p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="default_expiration"><?php esc_html_e( 'Default expiration', 'wb-listora' ); ?></label></th>
							<td>
								<input type="number" id="default_expiration" name="<?php echo esc_attr( $opt ); ?>[default_expiration]" value="<?php echo esc_attr( $s['default_expiration'] ?? $d['default_expiration'] ); ?>" min="0" class="small-text" />
								<span><?php esc_html_e( 'days', 'wb-listora' ); ?></span>
								<p class="description"><?php esc_html_e( 'Days before a new listing expires. Leave at 0 for lifetime listings (the directory default). Plans (Pro) override this per plan.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="renewal_window_days"><?php esc_html_e( 'Renewal window', 'wb-listora' ); ?></label></th>
							<td>
								<input type="number" id="renewal_window_days" name="<?php echo esc_attr( $opt ); ?>[renewal_window_days]" value="<?php echo esc_attr( $s['renewal_window_days'] ?? $d['renewal_window_days'] ); ?>" min="<?php echo esc_attr( (string) $bounds['renewal_window_days'][0] ); ?>" max="<?php echo esc_attr( (string) $bounds['renewal_window_days'][1] ); ?>" class="small-text" />
								<span><?php esc_html_e( 'days before expiry', 'wb-listora' ); ?></span>
								<p class="description"><?php esc_html_e( 'How many days before expiry users can start renewing. Already-expired listings can always renew.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="default_renewal_duration_days"><?php esc_html_e( 'Renewal duration', 'wb-listora' ); ?></label></th>
							<td>
								<input type="number" id="default_renewal_duration_days" name="<?php echo esc_attr( $opt ); ?>[default_renewal_duration_days]" value="<?php echo esc_attr( $s['default_renewal_duration_days'] ?? $d['default_renewal_duration_days'] ); ?>" min="0" class="small-text" />
								<span><?php esc_html_e( 'days', 'wb-listora' ); ?></span>
								<p class="description"><?php esc_html_e( 'Default extension applied when a listing is renewed. Set to 0 to use the standard listing expiration period. Pricing plans (Pro) override this per plan.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="default_renewal_credit_cost"><?php esc_html_e( 'Renewal cost', 'wb-listora' ); ?></label></th>
							<td>
								<input type="number" id="default_renewal_credit_cost" name="<?php echo esc_attr( $opt ); ?>[default_renewal_credit_cost]" value="<?php echo esc_attr( $s['default_renewal_credit_cost'] ?? $d['default_renewal_credit_cost'] ); ?>" min="0" class="small-text" />
								<span><?php esc_html_e( 'credits', 'wb-listora' ); ?></span>
								<p class="description"><?php esc_html_e( 'Default credit cost for renewals. Set to 0 for free renewals. Plans (Pro) override this per plan.', 'wb-listora' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

		</div>
		<?php
	}

	private static function render_submissions_tab() {
		$s      = get_option( self::OPTION_KEY, array() );
		$d      = wb_listora_get_default_settings();
		$opt    = esc_attr( self::OPTION_KEY );
		$bounds = self::bounded_int_ranges();

		// Catalogue, not the filtered list — a platform the owner has switched
		// off must keep its checkbox, or there is no way to switch it back on.
		$social_all = \WBListora\Core\Field::social_link_platforms_all();
		// Absent means never configured, which shows everything. An explicitly
		// empty array means the owner unticked them all and must stay unticked.
		$social_enabled = $s['social_platforms'] ?? null;
		$social_all_on  = ! is_array( $social_enabled );
		?>
		<div class="listora-settings-pane">

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Submission Rules', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Who can submit listings, how they are moderated, and the limits and protections that apply to each submission.', 'wb-listora' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Moderation', 'wb-listora' ); ?></th>
							<td>
								<fieldset>
									<legend class="screen-reader-text"><?php esc_html_e( 'Moderation', 'wb-listora' ); ?></legend>
									<div class="listora-field-group">
										<label>
											<input type="radio" name="<?php echo esc_attr( $opt ); ?>[moderation]" value="manual" <?php checked( $s['moderation'] ?? $d['moderation'], 'manual' ); ?> />
											<strong><?php esc_html_e( 'Require admin approval', 'wb-listora' ); ?></strong>
											<span class="listora-field-group__hint"> — <?php esc_html_e( 'new submissions stay in Pending until reviewed.', 'wb-listora' ); ?></span>
										</label>
										<label>
											<input type="radio" name="<?php echo esc_attr( $opt ); ?>[moderation]" value="auto_approve" <?php checked( $s['moderation'] ?? $d['moderation'], 'auto_approve' ); ?> />
											<strong><?php esc_html_e( 'Auto-approve', 'wb-listora' ); ?></strong>
											<span class="listora-field-group__hint"> — <?php esc_html_e( 'listings publish immediately. Combine with CAPTCHA to reduce spam.', 'wb-listora' ); ?></span>
										</label>
									</div>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Submission form style', 'wb-listora' ); ?></th>
							<td>
								<fieldset>
									<legend class="screen-reader-text"><?php esc_html_e( 'Submission form style', 'wb-listora' ); ?></legend>
									<div class="listora-field-group">
										<label>
											<input type="radio" name="<?php echo esc_attr( $opt ); ?>[submission_form_style]" value="wizard" <?php checked( $s['submission_form_style'] ?? $d['submission_form_style'], 'wizard' ); ?> />
											<strong><?php esc_html_e( 'Step-by-step wizard', 'wb-listora' ); ?></strong>
											<span class="listora-field-group__hint"> — <?php esc_html_e( 'guided steps with a progress bar. Best when listing types have many fields.', 'wb-listora' ); ?></span>
										</label>
										<label>
											<input type="radio" name="<?php echo esc_attr( $opt ); ?>[submission_form_style]" value="single_form" <?php checked( $s['submission_form_style'] ?? $d['submission_form_style'], 'single_form' ); ?> />
											<strong><?php esc_html_e( 'Single page form', 'wb-listora' ); ?></strong>
											<span class="listora-field-group__hint"> — <?php esc_html_e( 'every field on one page. Fastest for short forms and returning submitters.', 'wb-listora' ); ?></span>
										</label>
									</div>
									<p class="description">
										<?php esc_html_e( 'A Listing Submission block whose author explicitly chose a layout in the editor keeps that choice.', 'wb-listora' ); ?>
										<?php
										// Says out loud what the code already
										// does deliberately. Adding or editing a
										// listing from inside the dashboard is
										// always the single-page form; the
										// wizard is for the standalone page,
										// where a visitor is arriving cold and
										// the guided steps earn their keep.
										// Without this line the setting reads as
										// broken on the dashboard URL, which is
										// exactly where QA tested it.
										esc_html_e( 'This applies to the standalone Add Listing page. Adding or editing from inside the member dashboard always uses the single page form, because someone already in their dashboard is not arriving cold.', 'wb-listora' );
										?>
									</p>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Max file size', 'wb-listora' ); ?></th>
							<td>
								<?php
								// Read-only: members may upload whatever the server accepts; there is
								// no separate Listora cap.
								?>
								<strong><?php echo esc_html( ( size_format( wp_max_upload_size() ) ?: '' ) ); ?></strong>
								<p class="description"><?php esc_html_e( 'Set by your server (upload_max_filesize and post_max_size). WordPress scales large photos down after upload. Ask your host to raise it if members need bigger files.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="max_gallery_images"><?php esc_html_e( 'Max gallery images', 'wb-listora' ); ?></label></th>
							<td>
								<input type="number" id="max_gallery_images" name="<?php echo esc_attr( $opt ); ?>[max_gallery_images]" value="<?php echo esc_attr( $s['max_gallery_images'] ?? $d['max_gallery_images'] ); ?>" min="<?php echo esc_attr( (string) $bounds['max_gallery_images'][0] ); ?>" max="<?php echo esc_attr( (string) $bounds['max_gallery_images'][1] ); ?>" class="small-text" />
								<p class="description"><?php esc_html_e( 'Maximum number of gallery images a user can attach to a single listing.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="captcha_provider"><?php esc_html_e( 'CAPTCHA provider', 'wb-listora' ); ?></label></th>
							<td>
								<select id="captcha_provider" name="<?php echo esc_attr( $opt ); ?>[captcha_provider]">
									<option value="none" <?php selected( $s['captcha_provider'] ?? $d['captcha_provider'], 'none' ); ?>><?php esc_html_e( 'None', 'wb-listora' ); ?></option>
									<option value="recaptcha_v3" <?php selected( $s['captcha_provider'] ?? $d['captcha_provider'], 'recaptcha_v3' ); ?>><?php esc_html_e( 'Google reCAPTCHA v3', 'wb-listora' ); ?></option>
									<option value="cloudflare_turnstile" <?php selected( $s['captcha_provider'] ?? $d['captcha_provider'], 'cloudflare_turnstile' ); ?>><?php esc_html_e( 'Cloudflare Turnstile', 'wb-listora' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Protects submission and review forms from spam. Requires a site key and secret from the selected provider.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<?php $listora_no_captcha = 'none' === ( $s['captcha_provider'] ?? $d['captcha_provider'] ); ?>
						<tr data-listora-show-if="captcha_provider" data-listora-show-not="none"<?php echo $listora_no_captcha ? ' hidden' : ''; ?>>
							<th scope="row"><label for="captcha_site_key"><?php esc_html_e( 'CAPTCHA site key', 'wb-listora' ); ?></label></th>
							<td>
								<input type="text" id="captcha_site_key" name="<?php echo esc_attr( $opt ); ?>[captcha_site_key]" value="<?php echo esc_attr( $s['captcha_site_key'] ?? $d['captcha_site_key'] ); ?>" class="regular-text" />
								<p class="description"><?php esc_html_e( 'Public site key from your CAPTCHA provider dashboard.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr data-listora-show-if="captcha_provider" data-listora-show-not="none"<?php echo $listora_no_captcha ? ' hidden' : ''; ?>>
							<th scope="row"><label for="captcha_secret_key"><?php esc_html_e( 'CAPTCHA secret key', 'wb-listora' ); ?></label></th>
							<td>
								<input type="password" id="captcha_secret_key" name="<?php echo esc_attr( $opt ); ?>[captcha_secret_key]" value="<?php echo esc_attr( $s['captcha_secret_key'] ?? $d['captcha_secret_key'] ); ?>" class="regular-text" autocomplete="off" />
								<p class="description"><?php esc_html_e( 'Private secret key used for server-side verification. Never share publicly.', 'wb-listora' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Social Links', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Which platforms the Social Links field offers. Unticking one hides it on the submission form, the listing sidebar, the dashboard profile tab and the structured data. Links members already saved are kept, just not displayed.', 'wb-listora' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Platforms', 'wb-listora' ); ?></th>
							<td>
								<fieldset>
									<legend class="screen-reader-text"><?php esc_html_e( 'Platforms offered on the Social Links field', 'wb-listora' ); ?></legend>
									<?php
									// Always posted, so unticking every box saves an empty
									// list rather than looking like "field not submitted".
									?>
									<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[social_platforms][]" value="" />
									<div class="listora-field-group">
										<?php foreach ( $social_all as $social_slug => $social_label ) : ?>
											<label>
												<input
													type="checkbox"
													name="<?php echo esc_attr( $opt ); ?>[social_platforms][]"
													value="<?php echo esc_attr( $social_slug ); ?>"
													<?php checked( $social_all_on || in_array( $social_slug, (array) $social_enabled, true ) ); ?>
												/>
												<?php echo esc_html( $social_label ); ?>
											</label>
										<?php endforeach; ?>
									</div>
									<p class="description"><?php esc_html_e( 'The profiles members can link from their listing. Leave them all ticked to offer every one.', 'wb-listora' ); ?></p>
								</fieldset>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

		</div>
		<?php
	}

	private static function render_reviews_tab() {
		$s       = get_option( self::OPTION_KEY, array() );
		$reviews = $s['reviews'] ?? array();
		$opt     = esc_attr( self::OPTION_KEY );
		?>
		<div class="listora-settings-pane">

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Moderation', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Control how new reviews are approved, who can submit them, and their minimum length.', 'wb-listora' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Auto-approve', 'wb-listora' ); ?></th>
							<td>
								<label>
									<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[reviews][auto_approve]" value="0" />
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[reviews][auto_approve]" value="1" <?php checked( ! empty( $reviews['auto_approve'] ) ); ?> />
									<?php esc_html_e( 'Publish new reviews immediately (skip moderation queue)', 'wb-listora' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'When disabled, reviews stay in Pending until an admin approves them.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<?php
						// "Guest reviews / Require login" toggle removed: reviews
						// always require login (create_review_permissions() enforces
						// it; the author is always the current user). The setting
						// never changed behavior, so showing it was misleading.
						// Anonymous reviews would be a separate feature (schema +
						// capture UI + dedupe + spam handling).
						?>
						<tr>
							<th scope="row"><label for="review_min_length"><?php esc_html_e( 'Minimum length', 'wb-listora' ); ?></label></th>
							<td>
								<input type="number" id="review_min_length" name="<?php echo esc_attr( $opt ); ?>[reviews][min_length]" value="<?php echo esc_attr( $reviews['min_length'] ?? 20 ); ?>" min="0" max="1000" class="small-text" />
								<span><?php esc_html_e( 'characters', 'wb-listora' ); ?></span>
								<p class="description"><?php esc_html_e( 'Minimum characters required for the review body. Set to 0 to allow ratings with no written feedback.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'One review per listing', 'wb-listora' ); ?></th>
							<td>
								<label>
									<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[reviews][one_per_listing]" value="0" />
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[reviews][one_per_listing]" value="1" <?php checked( ! isset( $reviews['one_per_listing'] ) || ! empty( $reviews['one_per_listing'] ) ); ?> />
									<?php esc_html_e( 'Limit each user to a single review per listing', 'wb-listora' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Prevents rating inflation from repeat submissions by the same user.', 'wb-listora' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Owner Replies', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Whether listing owners can publicly respond to reviews left on their listings.', 'wb-listora' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable replies', 'wb-listora' ); ?></th>
							<td>
								<label>
									<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[reviews][allow_reply]" value="0" />
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[reviews][allow_reply]" value="1" <?php checked( ! isset( $reviews['allow_reply'] ) || ! empty( $reviews['allow_reply'] ) ); ?> />
									<?php esc_html_e( 'Allow listing owners to publicly reply to reviews', 'wb-listora' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Replies appear beneath each review. Owners are notified by email when a review is left on their listing.', 'wb-listora' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

		</div>
		<?php
	}

	private static function render_notifications_tab() {
		$s       = get_option( self::OPTION_KEY, array() );
		$notif   = $s['notifications'] ?? array();
		$opt     = esc_attr( self::OPTION_KEY );
		$grouped = Email_Templates_Page::get_grouped_events();

		$current_user = wp_get_current_user();
		$default_test = $current_user && $current_user->ID ? $current_user->user_email : (string) get_option( 'admin_email' );

		// One row per email, Free's and Pro's (owner decision, card
		// 10337185716): the switch, who receives it, the template editor and a
		// preview. The switches and templates save with this tab's Save.
		Email_Templates_Page::nonce_field();
		?>
		<div class="listora-settings-pane" id="listora-email-templates">
			<?php foreach ( $grouped as $group_label => $events ) : ?>
				<section class="listora-settings-block">
					<div class="listora-settings-block__head">
						<h3 class="listora-settings-block__title"><?php echo esc_html( (string) $group_label ); ?></h3>
					</div>
					<ul class="listora-email-list">
						<?php
						foreach ( $events as $key => $cfg ) :
							$enabled   = ! isset( $notif[ $key ] ) || $notif[ $key ];
							$editor_id = 'listora-email-editor-' . $key;
							$desc_id   = 'listora-email-desc-' . $key;
							?>
							<li class="listora-email-row">
								<div class="listora-email-row__main">
									<label class="listora-toggle">
										<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[notifications][<?php echo esc_attr( $key ); ?>]" value="0" />
										<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[notifications][<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $enabled ); ?> aria-describedby="<?php echo esc_attr( $desc_id ); ?>" />
										<span class="listora-toggle__track" aria-hidden="true"><span class="listora-toggle__thumb"></span></span>
										<span class="screen-reader-text">
											<?php
											/* translators: %s: email name. */
											echo esc_html( sprintf( __( 'Send "%s"', 'wb-listora' ), (string) $cfg['label'] ) );
											?>
										</span>
									</label>
									<div class="listora-email-row__text" id="<?php echo esc_attr( $desc_id ); ?>">
										<strong class="listora-email-row__name"><?php echo esc_html( (string) $cfg['label'] ); ?></strong>
										<?php if ( Email_Templates_Page::is_customized( $key ) ) : ?>
											<span class="listora-badge listora-badge--featured"><?php esc_html_e( 'Customized', 'wb-listora' ); ?></span>
										<?php endif; ?>
										<span class="listora-email-row__desc"><?php echo esc_html( (string) $cfg['desc'] ); ?></span>
										<?php if ( ! empty( $cfg['to'] ) ) : ?>
											<span class="listora-email-row__to">
												<?php
												/* translators: %s: who receives the email. */
												echo esc_html( sprintf( __( 'To: %s', 'wb-listora' ), (string) $cfg['to'] ) );
												?>
											</span>
										<?php endif; ?>
									</div>
									<div class="listora-email-row__actions">
										<button type="button" class="listora-btn wp-element-button listora-btn--sm" aria-expanded="false" aria-controls="<?php echo esc_attr( $editor_id ); ?>" data-listora-email-edit><?php esc_html_e( 'Edit template', 'wb-listora' ); ?></button>
										<button type="button" class="listora-btn wp-element-button listora-btn--sm listora-btn--ghost" data-listora-email-preview="<?php echo esc_attr( $key ); ?>" data-label="<?php echo esc_attr( (string) $cfg['label'] ); ?>"><?php esc_html_e( 'Preview', 'wb-listora' ); ?></button>
									</div>
								</div>
								<div class="listora-email-row__editor listora-email-templates__event" id="<?php echo esc_attr( $editor_id ); ?>" hidden>
									<?php Email_Templates_Page::render_editor( $key, $cfg ); ?>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endforeach; ?>

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Send a test email', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Send any email above, with sample details, to check that your site delivers mail.', 'wb-listora' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="listora-notification-test-event"><?php esc_html_e( 'Email', 'wb-listora' ); ?></label></th>
							<td>
								<select id="listora-notification-test-event" class="regular-text">
									<?php foreach ( $grouped as $group_label => $events ) : ?>
										<optgroup label="<?php echo esc_attr( (string) $group_label ); ?>">
											<?php foreach ( $events as $key => $cfg ) : ?>
												<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( (string) $cfg['label'] ); ?></option>
											<?php endforeach; ?>
										</optgroup>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="listora-notification-test-recipient"><?php esc_html_e( 'Send to', 'wb-listora' ); ?></label></th>
							<td><input type="email" id="listora-notification-test-recipient" class="regular-text" value="<?php echo esc_attr( $default_test ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><span aria-hidden="true">&nbsp;</span></th>
							<td>
								<button type="button" id="listora-notification-test-send" class="listora-btn wp-element-button listora-notification-test"><?php esc_html_e( 'Send test email', 'wb-listora' ); ?></button>
								<span id="listora-notification-test-status" class="listora-notification-test__status" aria-live="polite"></span>
							</td>
						</tr>
					</tbody>
				</table>
			</section>
		</div>

		<dialog class="listora-email-preview-dialog" id="listora-email-preview" aria-labelledby="listora-email-preview-title">
			<div class="listora-email-preview-dialog__head">
				<h2 class="listora-email-preview-dialog__title" id="listora-email-preview-title"></h2>
				<button type="button" class="listora-btn wp-element-button listora-btn--sm listora-btn--ghost" data-listora-preview-close><?php esc_html_e( 'Close', 'wb-listora' ); ?></button>
			</div>
			<p class="listora-email-preview-dialog__subject"><strong><?php esc_html_e( 'Subject:', 'wb-listora' ); ?></strong> <span data-listora-preview-subject></span></p>
			<iframe class="listora-email-preview" sandbox="" title="<?php esc_attr_e( 'Email preview', 'wb-listora' ); ?>"></iframe>
			<p class="description"><?php esc_html_e( 'Sample details, your saved template. Nothing is sent.', 'wb-listora' ); ?></p>
		</dialog>
		<?php
	}

	/**
	 * Render the Integrations tab: the companion plugins and what is
	 * connected now.
	 */
	private static function render_integrations_tab(): void {
		wp_enqueue_style(
			'listora-integrations',
			WB_LISTORA_PLUGIN_URL . 'assets/css/admin/integrations.css',
			array( 'listora-admin' ),
			WB_LISTORA_VERSION
		);
		$listora_integrations_embedded = true;
		require WB_LISTORA_PLUGIN_DIR . 'includes/admin/views/integrations.php';
	}

	// ─── Back-compat entry points: each job now lives in its own class. ───

	/**
	 * Back-compat: see Features_Settings_Tab::save().
	 */
	public static function save_features() {
		Features_Settings_Tab::save();
	}

	/**
	 * Back-compat: see Pages_Settings::create_page().
	 */
	public static function create_page(): void {
		Pages_Settings::create_page();
	}

	/**
	 * Back-compat: see Pages_Settings::created_page_notice().
	 */
	public static function created_page_notice(): void {
		Pages_Settings::created_page_notice();
	}

	/**
	 * Back-compat: see Email_Log_Page::handle_action().
	 */
	public static function handle_email_log_action(): void {
		Email_Log_Page::handle_action();
	}

	/**
	 * Back-compat: see Email_Log_Page::render().
	 */
	public static function render_email_log_page() {
		Email_Log_Page::render();
	}

	/**
	 * Back-compat: see Demo_Import_Admin::ajax_run_demo_import().
	 */
	public static function ajax_run_demo_import(): void {
		Demo_Import_Admin::ajax_run_demo_import();
	}

	/**
	 * Back-compat: see Demo_Import_Admin::ajax_delete_demo().
	 */
	public static function ajax_delete_demo(): void {
		Demo_Import_Admin::ajax_delete_demo();
	}

	/**
	 * Back-compat: see Map_Settings_Tab::render_tile_presets().
	 *
	 * @param string $url_id  ID of the tile URL input.
	 * @param string $attr_id ID of the attribution input.
	 * @param string $current Saved tile URL.
	 * @return void
	 */
	public static function render_tile_presets( $url_id, $attr_id, $current ) {
		Map_Settings_Tab::render_tile_presets( $url_id, $attr_id, $current );
	}
}
