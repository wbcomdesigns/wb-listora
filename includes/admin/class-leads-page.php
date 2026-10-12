<?php
/**
 * Listora > Listings > Leads: every contact-form message on the site.
 *
 * @package WBListora\Admin
 */

namespace WBListora\Admin;

defined( 'ABSPATH' ) || exit;

use WBListora\Core\Leads;

/**
 * Admin Leads screen and its admin-post actions.
 */
class Leads_Page {

	/**
	 * Screen slug.
	 */
	const SLUG = 'listora-leads';

	/**
	 * Period views => days.
	 */
	const PERIODS = array(
		'7d'  => 7,
		'30d' => 30,
		'90d' => 90,
	);

	/**
	 * Delete a lead or save retention (admin-post, nonce + capability).
	 *
	 * @return void
	 */
	public static function handle_action(): void {
		if ( ! current_user_can( 'manage_listora_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage leads.', 'wb-listora' ), 403 );
		}
		check_admin_referer( 'wb_listora_leads' );
		$do     = isset( $_REQUEST['do'] ) ? sanitize_key( wp_unslash( $_REQUEST['do'] ) ) : '';
		$notice = '';
		if ( 'delete' === $do && isset( $_REQUEST['id'] ) ) {
			$notice = Leads::delete( absint( $_REQUEST['id'] ) ) ? 'deleted' : '';
		} elseif ( 'retention' === $do && isset( $_POST['retention_months'] ) ) {
			$months = (int) $_POST['retention_months'];
			if ( array_key_exists( $months, Leads::retention_choices() ) ) {
				update_option( Leads::RETENTION_OPTION, $months, false );
				Leads::prune();
				$notice = 'retention';
			}
		}
		$back = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=' . self::SLUG );
		wp_safe_redirect( add_query_arg( 'lead_notice', $notice, remove_query_arg( array( 'lead_notice' ), $back ) ) );
		exit;
	}

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_listora_settings' ) ) {
			return;
		}

		$table    = new Admin_Table();
		$base_url = admin_url( 'admin.php?page=' . self::SLUG );
		$state    = $table->request( 'leads', array( 'listing' ), array( 'date' ), 'date' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view.
		$view = isset( $_GET['period'] ) ? sanitize_key( wp_unslash( $_GET['period'] ) ) : 'all';
		$view = isset( self::PERIODS[ $view ] ) ? $view : 'all';

		$args = array(
			'listing_id' => self::listing_filter( $state['filters']['listing'] ),
			'search'     => $state['s'],
			'after'      => 'all' === $view ? '' : gmdate( 'Y-m-d H:i:s', time() - self::PERIODS[ $view ] * DAY_IN_SECONDS ),
			'order'      => $state['order'],
			'limit'      => $state['per_page'],
			'offset'     => $state['offset'],
		);
		$result = Leads::query( $args );
		if ( wb_listora_clamp_paged( $state, (int) $result['total'] ) ) {
			$args['offset'] = $state['offset'];
			$result         = Leads::query( $args );
		}

		$action    = admin_url( 'admin-post.php' );
		$nonce     = wp_create_nonce( 'wb_listora_leads' );
		$retention = Leads::retention_months();
		?>
		<div class="wrap wb-listora-admin">
			<?php
			wb_listora_render_admin_header(
				array(
					'title'       => __( 'Leads', 'wb-listora' ),
					'icon'        => 'message-square',
					'description' => __( 'Every message visitors sent to listing owners through the contact form. Owners see their own under My Dashboard > Leads.', 'wb-listora' ),
				)
			);
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display flag from our own redirect.
			$notice  = isset( $_GET['lead_notice'] ) ? sanitize_key( wp_unslash( $_GET['lead_notice'] ) ) : '';
			$notices = array(
				'deleted'   => __( 'Lead deleted.', 'wb-listora' ),
				'retention' => __( 'Retention saved. Older leads were removed.', 'wb-listora' ),
			);
			if ( isset( $notices[ $notice ] ) ) {
				printf( '<div class="notice notice-success listora-notice is-dismissible"><p>%s</p></div>', esc_html( $notices[ $notice ] ) );
			}
			?>
			<form class="listora-inline-form listora-inline-form--compact" method="post" action="<?php echo esc_url( $action ); ?>">
				<input type="hidden" name="action" value="wb_listora_leads"><input type="hidden" name="do" value="retention">
				<?php wp_nonce_field( 'wb_listora_leads' ); ?>
				<label for="listora-leads-retention"><?php esc_html_e( 'Keep leads for', 'wb-listora' ); ?></label>
				<select id="listora-leads-retention" name="retention_months" class="listora-filter-select">
					<?php foreach ( Leads::retention_choices() as $months => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $months ); ?>" <?php selected( $retention, $months ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="listora-btn listora-btn--sm"><?php esc_html_e( 'Save', 'wb-listora' ); ?></button>
				<span class="listora-muted"><?php esc_html_e( 'Older leads are removed each day. A person can ask for theirs through Tools > Export / Erase Personal Data.', 'wb-listora' ); ?></span>
			</form>
			<?php
			$rows = array();
			foreach ( $result['rows'] as $lead ) {
				$rows[] = self::row( $lead, $action, $nonce );
			}
			$table->render(
				array(
					'id'         => 'leads',
					'base_url'   => $base_url,
					'state'      => $state,
					'total'      => $result['total'],
					/* translators: %s: number of leads. */
					'count_text' => sprintf( _n( '%s lead', '%s leads', $result['total'], 'wb-listora' ), number_format_i18n( $result['total'] ) ),
					'views'      => array(
						'all' => array( __( 'All', 'wb-listora' ), '' ),
						'7d'  => array( __( 'Last 7 days', 'wb-listora' ), '' ),
						'30d' => array( __( 'Last 30 days', 'wb-listora' ), '' ),
						'90d' => array( __( 'Last 90 days', 'wb-listora' ), '' ),
					),
					'view_arg'   => 'period',
					'view'       => $view,
					'search'     => __( 'Search names, emails and messages', 'wb-listora' ),
					'filters'    => array(
						array(
							'name'    => 'listing',
							'label'   => __( 'Listing ID or title', 'wb-listora' ),
							'options' => null,
						),
					),
					'columns'    => array(
						'date'    => array(
							'label'    => __( 'Received', 'wb-listora' ),
							'sortable' => true,
						),
						'from'    => array( 'label' => __( 'From', 'wb-listora' ) ),
						'listing' => array(
							'label'    => __( 'Listing', 'wb-listora' ),
							'priority' => 2,
						),
						'message' => array(
							'label'    => __( 'Message', 'wb-listora' ),
							'priority' => 3,
						),
					),
					'rows'       => $rows,
					'empty'      => array(
						'title' => __( 'No leads yet', 'wb-listora' ),
						'text'  => __( 'Messages appear here when visitors use the contact form on a listing.', 'wb-listora' ),
						'icon'  => 'message-square',
					),
				)
			);
			?>
		</div>
		<?php
	}

	/**
	 * The listing filter as an ID: a number, or the first listing whose title matches.
	 *
	 * @param string $value Filter text.
	 * @return int 0 when empty; -1 when nothing matches, so the table shows none.
	 */
	private static function listing_filter( string $value ): int {
		$value = trim( $value );
		if ( '' === $value ) {
			return 0;
		}
		if ( ctype_digit( $value ) ) {
			return (int) $value;
		}
		$ids = get_posts(
			array(
				'post_type'      => 'listora_listing',
				'post_status'    => 'any',
				's'              => $value,
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			)
		);
		return $ids ? (int) $ids[0] : -1;
	}

	/**
	 * One lead as an Admin_Table row.
	 *
	 * @param array<string, mixed> $lead   Lead.
	 * @param string               $action admin-post URL.
	 * @param string               $nonce  Action nonce.
	 * @return array<string, mixed>
	 */
	private static function row( array $lead, string $action, string $nonce ): array {
		$when = (string) mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) $lead['created_local'] );
		$from = '<span class="listora-row-title">' . esc_html( $lead['name'] ) . '</span><br><a href="' . esc_url( 'mailto:' . $lead['email'] ) . '">' . esc_html( $lead['email'] ) . '</a>';
		if ( '' !== $lead['phone'] ) {
			$from .= '<br><span class="listora-muted">' . esc_html( $lead['phone'] ) . '</span>';
		}
		$edit = get_edit_post_link( $lead['listing_id'] );

		return array(
			'id'      => $lead['id'],
			'label'   => $lead['name'],
			'cells'   => array(
				'date'    => esc_html( $when ),
				'from'    => $from,
				'listing' => $edit ? '<a href="' . esc_url( $edit ) . '">' . esc_html( $lead['listing_title'] ) . '</a>' : esc_html( $lead['listing_title'] ),
				'message' => '<span class="listora-clamp">' . esc_html( $lead['message'] ) . '</span>',
			),
			'actions' => array(
				array(
					'label'   => __( 'Reply', 'wb-listora' ),
					'url'     => 'mailto:' . $lead['email'],
					'primary' => true,
				),
				array(
					'label'   => __( 'Delete', 'wb-listora' ),
					'url'     => add_query_arg(
						array(
							'action'   => 'wb_listora_leads',
							'do'       => 'delete',
							'id'       => $lead['id'],
							'_wpnonce' => $nonce,
						),
						$action
					),
					'danger'  => true,
					'confirm' => __( 'This lead is deleted for good. The listing owner can no longer see it either.', 'wb-listora' ),
				),
			),
			'detail'  => '<p>' . nl2br( esc_html( $lead['message'] ) ) . '</p>',
		);
	}
}
