<?php
/**
 * Admin menu hubs.
 *
 * The Listora menu had 22 flat items, with money screens, logs and taxonomies
 * mixed together (card 10337177659). Related screens now share one menu item
 * and a tab row: Moderation holds Reviews, Claims, Needs and Moderators, and
 * so on.
 *
 * Every screen keeps its own slug, URL and capability. A hub's first tab the
 * current user may open is the menu item; the other tabs stay registered
 * (WordPress refuses a plugin page that has no submenu entry) with a class
 * that hides them from the menu. Old links and bookmarks keep working, and
 * a moderator's menu shows only the tabs their role opens.
 *
 * @package WBListora\Admin
 */

namespace WBListora\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Admin_Hubs
 *
 * @since 1.9.0
 */
class Admin_Hubs {

	/**
	 * Class on submenu entries that live in a hub's tab row, not the menu.
	 */
	const HIDDEN_CLASS = 'listora-hub-hidden';

	/**
	 * Tabs the current user can open, per hub: hub key => [ slug => label ].
	 * Filled from the registered submenu on `admin_menu`.
	 *
	 * @var array<string, array<string, string>>
	 */
	private static $tabs = array();

	/**
	 * Register hooks.
	 */
	public static function init() {
		// After every plugin (Pro included) has registered its screens.
		add_action( 'admin_menu', array( self::class, 'group_menu' ), 999 );
		add_filter( 'submenu_file', array( self::class, 'highlight_hub' ), 20 );
		add_action( 'in_admin_header', array( self::class, 'render_tabs' ), 5 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'hide_tab_entries' ) );
	}

	/**
	 * Hubs in menu order: key => [ label, tab slugs ]. Screens a site does
	 * not have (Pro inactive) are skipped.
	 *
	 * @return array<string, array{0: string, 1: string[]}>
	 */
	private static function hubs() {
		return array(
			'dashboard'    => array( __( 'Dashboard', 'wb-listora' ), array( 'listora' ) ),
			'listings'     => array( __( 'Listings', 'wb-listora' ), array( 'edit.php?post_type=listora_listing' ) ),
			'types'        => array( __( 'Listing Types', 'wb-listora' ), array( 'listora-listing-types', 'listora-badges' ) ),
			'categories'   => array(
				__( 'Categories', 'wb-listora' ),
				array(
					'edit-tags.php?taxonomy=listora_listing_cat&post_type=listora_listing',
					'edit-tags.php?taxonomy=listora_listing_location&post_type=listora_listing',
					'edit-tags.php?taxonomy=listora_listing_feature&post_type=listora_listing',
					'edit-tags.php?taxonomy=listora_service_cat',
				),
			),
			'moderation'   => array( __( 'Moderation', 'wb-listora' ), array( 'listora-reviews', 'listora-claims', 'listora-needs', 'listora-moderators' ) ),
			'monetization' => array( __( 'Monetization', 'wb-listora' ), array( 'edit.php?post_type=listora_plan', 'listora-coupons', 'listora-transactions' ) ),
			'analytics'    => array( __( 'Analytics', 'wb-listora' ), array( 'listora-analytics' ) ),
			'tools'        => array( __( 'Tools', 'wb-listora' ), array( 'listora-audit-log', 'listora-email-log', 'listora-webhooks' ) ),
			'settings'     => array( __( 'Settings', 'wb-listora' ), array( 'listora-settings' ) ),
		);
	}

	/**
	 * Order the Listora submenu by hub and hide every tab but the first.
	 *
	 * Entries that belong to no hub (the Pro upsell, hidden helper screens)
	 * keep their place after the hubs.
	 */
	public static function group_menu() {
		global $submenu;
		if ( empty( $submenu['listora'] ) || ! is_array( $submenu['listora'] ) ) {
			return;
		}

		$by_slug = array();
		foreach ( $submenu['listora'] as $item ) {
			if ( isset( $item[2] ) ) {
				$by_slug[ $item[2] ] = $item;
			}
		}

		$ordered = array();
		foreach ( self::hubs() as $key => $hub ) {
			list( $label, $slugs ) = $hub;
			$first                 = true;
			foreach ( $slugs as $slug ) {
				if ( ! isset( $by_slug[ $slug ] ) ) {
					continue;
				}
				$item                        = $by_slug[ $slug ];
				self::$tabs[ $key ][ $slug ] = wp_strip_all_tags( (string) $item[0] );
				if ( $first ) {
					$item[0] = $label;
					$first   = false;
				} else {
					$item[4] = trim( ( $item[4] ?? '' ) . ' ' . self::HIDDEN_CLASS );
				}
				$ordered[] = $item;
				unset( $by_slug[ $slug ] );
			}
		}

		$submenu['listora'] = array_merge( $ordered, array_values( $by_slug ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- reordering our own submenu.
	}

	/**
	 * The hub the current screen belongs to, and its slug in that hub.
	 *
	 * @return array{0: string, 1: string}|null [ hub key, slug ], or null.
	 */
	private static function current() {
		global $pagenow, $typenow, $taxnow, $plugin_page;

		foreach ( self::$tabs as $key => $tabs ) {
			foreach ( array_keys( $tabs ) as $slug ) {
				if ( ! empty( $plugin_page ) ) {
					$match = $plugin_page === $slug;
				} elseif ( 'edit.php' === $pagenow ) {
					$match = 'edit.php?post_type=' . $typenow === $slug;
				} elseif ( 'edit-tags.php' === $pagenow ) {
					$match = 0 === strpos( $slug . '&', 'edit-tags.php?taxonomy=' . $taxnow . '&' );
				} else {
					$match = false;
				}
				if ( $match ) {
					return array( $key, $slug );
				}
			}
		}
		return null;
	}

	/**
	 * Highlight the hub's menu item while any of its tabs is open.
	 *
	 * @param string|null $submenu_file Submenu slug WordPress would highlight.
	 * @return string|null
	 */
	public static function highlight_hub( $submenu_file ) {
		$current = self::current();
		if ( null !== $current ) {
			$slugs = array_keys( self::$tabs[ $current[0] ] );
			return $slugs[0];
		}
		// Screens outside the tabs (a single Need's edit screen, say) name
		// the tab they belong to; highlight that tab's hub, since the tab
		// itself is hidden from the menu.
		foreach ( self::$tabs as $tabs ) {
			if ( isset( $tabs[ (string) $submenu_file ] ) ) {
				$slugs = array_keys( $tabs );
				return $slugs[0];
			}
		}
		return $submenu_file;
	}

	/**
	 * The tab row above a hub screen. A hub with one tab has none: the
	 * screen's own heading is enough.
	 */
	public static function render_tabs() {
		$current = self::current();
		if ( null === $current || count( self::$tabs[ $current[0] ] ) < 2 ) {
			return;
		}
		$hubs = self::hubs();
		?>
		<nav class="listora-hub-tabs" aria-label="<?php echo esc_attr( $hubs[ $current[0] ][0] ); ?>">
			<?php foreach ( self::$tabs[ $current[0] ] as $slug => $label ) : ?>
				<?php $url = false !== strpos( $slug, '.php' ) ? admin_url( $slug ) : admin_url( 'admin.php?page=' . $slug ); ?>
				<a class="listora-hub-tabs__tab<?php echo $slug === $current[1] ? ' is-active' : ''; ?>" href="<?php echo esc_url( $url ); ?>"<?php echo $slug === $current[1] ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Hide tab entries from the admin menu. The menu shows on every admin
	 * screen, so this rides on core's menu stylesheet rather than Listora's.
	 */
	public static function hide_tab_entries() {
		wp_add_inline_style( 'admin-menu', '#adminmenu .' . self::HIDDEN_CLASS . '{display:none}' );
	}
}
