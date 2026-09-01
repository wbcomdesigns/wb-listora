<?php
/**
 * Offer to put a Listora destination page into the site's menu.
 *
 * Creating a page and mapping it is not the same as making it usable, and the
 * plugin used to stop at the first one. An owner switches on Reverse Listings,
 * is told "page created and mapped", and reasonably concludes the marketplace
 * is live. It is not: `/needs/` exists and nothing on the site points at it, so
 * no visitor will ever arrive. Measured on a real site — Directory, Add Listing
 * and My Listings were in the menu because somebody put them there by hand
 * once, and every page created afterwards was missing from it.
 *
 * That gap is invisible from the admin, because every screen the owner can see
 * reports success: the feature is on, the page is published, Settings > Pages
 * says Linked. Nothing says "and nobody can find it".
 *
 * So this prompts, once, and offers to do it. It does not do it unasked — the
 * site's menu is the owner's, and quietly rearranging it is exactly the kind of
 * help nobody wants.
 *
 * Only DESTINATION pages are offered. Compare Listings is opened by pressing
 * Compare on a listing and Buy Credits by a plan CTA; putting either in a nav
 * menu would be clutter, so they are not `menu_candidate` and never appear here.
 *
 * @package WBListora\Admin
 * @since 1.7.0
 */

namespace WBListora\Admin;

use WBListora\Core\Page_Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Prompts to add Listora destination pages to a nav menu.
 */
class Menu_Prompt {

	/**
	 * Per-user dismissal.
	 */
	const META_DISMISSED = 'wb_listora_menu_prompt_dismissed';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
		add_action( 'admin_post_wb_listora_add_pages_to_menu', array( __CLASS__, 'add_to_menu' ) );
		add_action( 'admin_post_wb_listora_dismiss_menu_prompt', array( __CLASS__, 'dismiss' ) );
	}

	/**
	 * The menus these pages belong in.
	 *
	 * Not "the primary menu" — that guess is wrong on exactly the themes this
	 * plugin is most used with. BuddyX and Reign assign a logged-in menu and a
	 * logged-out menu to different locations, so adding to the primary one puts
	 * the page in front of members and leaves every anonymous visitor unable to
	 * find it, while the prompt disappears reporting success. Verified: adding
	 * to "Listora Main" left `/needs/` absent from the logged-out navigation.
	 *
	 * So it asks the site instead of guessing. Whichever menus the owner has
	 * already put Listora's other destination pages in are the menus these
	 * belong in too — on the test site that is both halves of the pair, because
	 * Directory is in both. Their structure is better information than any
	 * default we could pick.
	 *
	 * Falls back to a conventional primary location only when nothing is
	 * placed yet and there is nothing to learn from.
	 *
	 * @return int[] Menu term IDs.
	 */
	public static function target_menu_ids(): array {
		$locations = get_nav_menu_locations();
		if ( empty( $locations ) || ! is_array( $locations ) ) {
			return array();
		}

		$known = self::known_page_ids();
		$targets = array();

		foreach ( $locations as $menu_id ) {
			$menu_id = (int) $menu_id;
			if ( $menu_id <= 0 || isset( $targets[ $menu_id ] ) ) {
				continue;
			}

			foreach ( (array) wp_get_nav_menu_items( $menu_id ) as $item ) {
				if ( 'page' === $item->object && isset( $known[ (int) $item->object_id ] ) ) {
					$targets[ $menu_id ] = true;
					break;
				}
			}
		}

		if ( ! empty( $targets ) ) {
			return array_map( 'intval', array_keys( $targets ) );
		}

		foreach ( array( 'primary', 'menu-1', 'main' ) as $preferred ) {
			if ( ! empty( $locations[ $preferred ] ) ) {
				return array( (int) $locations[ $preferred ] );
			}
		}

		foreach ( $locations as $menu_id ) {
			if ( ! empty( $menu_id ) ) {
				return array( (int) $menu_id );
			}
		}

		return array();
	}

	/**
	 * Every registered destination page on the site, mapped or not.
	 *
	 * @return array<int, true> Page ID => true.
	 */
	private static function known_page_ids(): array {
		$ids = array();

		foreach ( Page_Registry::keys() as $key ) {
			$config = Page_Registry::get_config( $key );
			if ( empty( $config['menu_candidate'] ) ) {
				continue;
			}

			$page_id = Page_Registry::get_id( $key );
			if ( $page_id > 0 ) {
				$ids[ $page_id ] = true;
			}
		}

		return $ids;
	}

	/**
	 * Destination pages that exist, work, and are in no menu at all.
	 *
	 * Checked against EVERY assigned menu, not just the target one: a page the
	 * owner deliberately put in a footer menu is findable, and nagging about it
	 * would be wrong.
	 *
	 * @return array<int, string> Page ID => title.
	 */
	public static function unlinked_pages(): array {
		$targets = self::target_menu_ids();

		// Absent from EVERY target, not from some. Requiring presence in all of
		// them looked more thorough and was wrong: on a theme with a logged-in
		// and a logged-out menu, My Listings sits in the first and deliberately
		// not the second, because a member dashboard is nothing for an
		// anonymous visitor to click. The stricter rule flagged it as missing
		// and would have offered to put it in front of the public — undoing a
		// deliberate choice, which is the whole thing this prompt exists not to
		// do. A page in one menu has been placed. A page in none has not.
		$in_a_menu = array();

		foreach ( $targets as $menu_id ) {
			foreach ( (array) wp_get_nav_menu_items( (int) $menu_id ) as $item ) {
				if ( 'page' === $item->object ) {
					$in_a_menu[ (int) $item->object_id ] = true;
				}
			}
		}

		$missing = array();

		foreach ( Page_Registry::keys() as $key ) {
			$config = Page_Registry::get_config( $key );

			if ( empty( $config['menu_candidate'] ) ) {
				continue;
			}

			if ( ! Page_Registry::is_available( $key ) ) {
				continue;
			}

			$page_id = Page_Registry::get_id( $key );

			if ( $page_id <= 0 || isset( $in_a_menu[ $page_id ] ) ) {
				continue;
			}

			// Only offer a page a visitor could actually open.
			if ( ! is_post_publicly_viewable( $page_id ) ) {
				continue;
			}

			$missing[ $page_id ] = (string) get_the_title( $page_id );
		}

		return $missing;
	}

	/**
	 * Show the prompt.
	 */
	public static function render() {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}

		if ( get_user_meta( get_current_user_id(), self::META_DISMISSED, true ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		// Not on the Listora landing page: the onboarding notice and setup
		// wizard own that screen. This prompt is contextual and matched it
		// only because the screen id contains "listora", which is how seven
		// notices ended up stacked on one page.
		if ( ! $screen || false === strpos( (string) $screen->id, 'listora' )
			|| 'toplevel_page_listora' === $screen->id ) {
			return;
		}

		$missing = self::unlinked_pages();
		if ( empty( $missing ) ) {
			return;
		}

		$menu_ids = self::target_menu_ids();
		$names    = array();

		foreach ( $menu_ids as $menu_id ) {
			$menu = wp_get_nav_menu_object( $menu_id );
			if ( $menu ) {
				$names[] = $menu->name;
			}
		}

		$dismiss   = wp_nonce_url( admin_url( 'admin-post.php?action=wb_listora_dismiss_menu_prompt' ), 'wb_listora_dismiss_menu_prompt' );
		$menus_url = admin_url( 'nav-menus.php' );

		?>
		<div class="notice notice-info listora-notice">
			<p>
				<strong><?php esc_html_e( 'These pages exist but nothing on your site links to them.', 'wb-listora' ); ?></strong>
			</p>
			<p>
				<?php echo esc_html( implode( ', ', $missing ) ); ?>
				&mdash;
				<?php esc_html_e( 'they work, but a visitor has no way to reach them until they are in a menu.', 'wb-listora' ); ?>
			</p>
			<p>
				<?php if ( ! empty( $names ) ) : ?>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wb_listora_add_pages_to_menu' ), 'wb_listora_add_pages_to_menu' ) ); ?>" class="button button-primary">
						<?php
						// Each name quoted, then joined — building the list with
						// a quoted format string instead produced
						// `Add them to Listora Main", "Listora Logged Out`.
						$quoted = array_map(
							static function ( $name ) {
								return '"' . $name . '"';
							},
							$names
						);
						echo esc_html(
							sprintf(
								/* translators: %s: one or more quoted menu names, comma separated. */
								__( 'Add them to %s', 'wb-listora' ),
								implode( ', ', $quoted )
							)
						);
						?>
					</a>
					<a href="<?php echo esc_url( $menus_url ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Choose a different menu', 'wb-listora' ); ?>
					</a>
				<?php else : ?>
					<em><?php esc_html_e( 'This theme has no menu assigned yet, so there is nowhere to add them.', 'wb-listora' ); ?></em>
					<a href="<?php echo esc_url( $menus_url ); ?>" class="button button-primary">
						<?php esc_html_e( 'Set up a menu', 'wb-listora' ); ?>
					</a>
				<?php endif; ?>
				<a href="<?php echo esc_url( $dismiss ); ?>" class="button button-secondary">
					<?php esc_html_e( 'No thanks', 'wb-listora' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Append the unlinked pages to the target menu.
	 */
	public static function add_to_menu() {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'wb-listora' ) );
		}

		check_admin_referer( 'wb_listora_add_pages_to_menu' );

		$pages = self::unlinked_pages();
		$added = 0;

		foreach ( self::target_menu_ids() as $menu_id ) {
			// Skip a menu that already has it — pressing this twice, or a page
			// present in one half of a pair, must not produce duplicates.
			$present = array();
			foreach ( (array) wp_get_nav_menu_items( $menu_id ) as $item ) {
				if ( 'page' === $item->object ) {
					$present[ (int) $item->object_id ] = true;
				}
			}

			foreach ( $pages as $page_id => $title ) {
				if ( isset( $present[ $page_id ] ) ) {
					continue;
				}

				$item = wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-object-id' => $page_id,
						'menu-item-object'    => 'page',
						'menu-item-type'      => 'post_type',
						'menu-item-title'     => $title,
						'menu-item-status'    => 'publish',
					)
				);

				if ( $item && ! is_wp_error( $item ) ) {
					++$added;
				}
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                     => 'listora-settings',
					'tab'                      => 'general',
					'listora_menu_items_added' => $added,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Stop offering.
	 */
	public static function dismiss() {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'wb-listora' ) );
		}

		check_admin_referer( 'wb_listora_dismiss_menu_prompt' );

		update_user_meta( get_current_user_id(), self::META_DISMISSED, 1 );

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=listora' ) );
		exit;
	}
}
