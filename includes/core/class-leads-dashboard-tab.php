<?php
/**
 * My Dashboard > Leads: the messages a member's listings received.
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Leads tab through the dashboard's own extension hooks.
 */
class Leads_Dashboard_Tab {

	/**
	 * Tab id: `?tab=leads`, `dash-tab-leads`, `dash-panel-leads`.
	 */
	const TAB_ID = 'leads';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'wb_listora_dashboard_tab_labels', array( __CLASS__, 'label' ) );
		add_action( 'wb_listora_dashboard_nav_group', array( __CLASS__, 'render_nav_item' ), 10, 2 );
		add_action( 'wb_listora_dashboard_sections', array( __CLASS__, 'render_panel' ) );
	}

	/**
	 * Whether a member sees the tab: the contact form is on and they have
	 * leads, or own a listing in any status but the bin.
	 *
	 * count_user_posts() counts published listings only, so an owner whose
	 * listings were all paused, expired or drafted lost the way to the leads
	 * those listings had already received.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function visible_to( int $user_id ): bool {
		static $seen = array();
		if ( $user_id <= 0 || ! wb_listora_feature_enabled( 'contact_form' ) ) {
			return false;
		}
		if ( ! isset( $seen[ $user_id ] ) ) {
			$seen[ $user_id ] = (bool) Leads::listing_ids_for_owner( $user_id ) || (bool) get_posts(
				array(
					'post_type'      => 'listora_listing',
					'author'         => $user_id,
					'post_status'    => array_values( array_diff( get_post_stati(), array( 'trash', 'auto-draft', 'inherit' ) ) ),
					'fields'         => 'ids',
					'posts_per_page' => 1,
					'no_found_rows'  => true,
				)
			);
		}
		return $seen[ $user_id ];
	}

	/**
	 * Tab label for the sidebar and the document title.
	 *
	 * @param array<string, string> $labels Tab labels.
	 * @return array<string, string>
	 */
	public static function label( $labels ) {
		if ( self::visible_to( get_current_user_id() ) ) {
			$labels[ self::TAB_ID ] = __( 'Leads', 'wb-listora' );
		}
		return $labels;
	}

	/**
	 * Sidebar button, in the Listings group.
	 *
	 * @param string $group   Nav group.
	 * @param int    $user_id User ID.
	 * @return void
	 */
	public static function render_nav_item( string $group, int $user_id ): void {
		if ( 'listings' !== $group || ! self::visible_to( $user_id ) ) {
			return;
		}
		?>
		<a href="<?php echo esc_url( add_query_arg( 'tab', self::TAB_ID, wb_listora_get_dashboard_url() ) ); ?>"
			class="listora-dashboard__nav-item"
			data-wp-on--click="actions.switchDashTab"
			data-wp-context='{"tabId":"<?php echo esc_attr( self::TAB_ID ); ?>"}'
			id="dash-tab-<?php echo esc_attr( self::TAB_ID ); ?>"
			data-listora-tab-label="<?php esc_attr_e( 'Leads', 'wb-listora' ); ?>"
			role="tab"
			aria-selected="false"
			aria-controls="dash-panel-<?php echo esc_attr( self::TAB_ID ); ?>"
		>
			<?php echo Lucide_Icons::render( 'message-square', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG from a whitelist. ?>
			<?php esc_html_e( 'Leads', 'wb-listora' ); ?>
		</a>
		<?php
	}

	/**
	 * The panel: the member's leads, filterable by listing, paginated.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public static function render_panel( $user_id ): void {
		$user_id = (int) $user_id;
		if ( ! self::visible_to( $user_id ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view parameters.
		$page       = isset( $_GET['leads_page'] ) ? max( 1, absint( wp_unslash( $_GET['leads_page'] ) ) ) : 1;
		$listing_id = isset( $_GET['lead_listing'] ) ? absint( wp_unslash( $_GET['lead_listing'] ) ) : 0;
		// phpcs:enable
		$per_page = max( 1, (int) apply_filters( 'wb_listora_dashboard_per_page', 20, self::TAB_ID, $user_id ) );
		$args     = array(
			'owner_id'   => $user_id,
			'listing_id' => $listing_id,
			'limit'      => $per_page,
			'offset'     => ( $page - 1 ) * $per_page,
		);
		$result   = Leads::query( $args );
		// A stale ?leads_page=999 shows the last real page, not an empty list.
		$pages = (int) ceil( $result['total'] / $per_page );
		if ( $pages > 0 && $page > $pages ) {
			$page           = $pages;
			$args['offset'] = ( $page - 1 ) * $per_page;
			$result         = Leads::query( $args );
		}
		$lead_listings = Leads::listing_ids_for_owner( $user_id );
		$listings      = $lead_listings ? get_posts(
			array(
				'post_type'      => 'listora_listing',
				'post_status'    => 'any',
				'post__in'       => $lead_listings,
				'posts_per_page' => count( $lead_listings ),
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		) : array();

		wb_listora_get_template(
			'blocks/user-dashboard/tab-leads.php',
			array(
				'tab_id'      => self::TAB_ID,
				'leads'       => $result['rows'],
				'total'       => $result['total'],
				'page'        => $page,
				'total_pages' => (int) ceil( $result['total'] / $per_page ),
				'listing_id'  => $listing_id,
				'listings'    => $listings,
			)
		);
	}
}
