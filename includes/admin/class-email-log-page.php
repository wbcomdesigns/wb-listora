<?php
/**
 * Email Log admin screen.
 *
 * @package WBListora\Admin
 */

namespace WBListora\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Listora > Email Log screen and its admin-post actions.
 */
class Email_Log_Page {

	/**
	 * Email Log actions: resend, clear, retention (admin-post, nonce + cap).
	 */
	public static function handle_action(): void {
		if ( ! current_user_can( 'manage_listora_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the email log.', 'wb-listora' ), 403 );
		}
		check_admin_referer( 'wb_listora_email_log' );
		$do     = isset( $_REQUEST['do'] ) ? sanitize_key( wp_unslash( $_REQUEST['do'] ) ) : '';
		$notice = '';
		if ( 'resend' === $do && isset( $_REQUEST['id'] ) ) {
			$notice = \WBListora\Workflow\Email_Log::resend( absint( $_REQUEST['id'] ) ) ? 'resent' : 'resend_failed';
		} elseif ( 'clear' === $do ) {
			\WBListora\Workflow\Notifications::clear_log();
			$notice = 'cleared';
		} elseif ( 'retention' === $do && isset( $_POST['retention_days'] ) ) {
			$days = (int) $_POST['retention_days'];
			if ( array_key_exists( $days, \WBListora\Workflow\Notifications::retention_choices() ) ) {
				update_option( \WBListora\Workflow\Notifications::RETENTION_OPTION_KEY, $days, false );
				\WBListora\Workflow\Notifications::prune_log();
				$notice = 'retention';
			}
		}
		$back = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=listora-email-log' );
		wp_safe_redirect( add_query_arg( 'log_notice', $notice, remove_query_arg( array( 'log_notice' ), $back ) ) );
		exit;
	}

	/**
	 * Email Log: every email Listora sent, with filters, the email itself,
	 * and resend for failures.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_listora_settings' ) ) {
			return;
		}

		$table    = new Admin_Table();
		$base_url = admin_url( 'admin.php?page=listora-email-log' );
		$state    = $table->request( 'email_log', array( 'event', 'recipient' ), array( 'date' ), 'date' );
		$counts   = \WBListora\Workflow\Email_Log::counts();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view.
		$view   = isset( $_GET['result'] ) ? sanitize_key( wp_unslash( $_GET['result'] ) ) : 'all';
		$view   = isset( $counts[ $view ] ) ? $view : 'all';
		$result = \WBListora\Workflow\Email_Log::query(
			array(
				'event'     => sanitize_key( $state['filters']['event'] ),
				'recipient' => $state['filters']['recipient'],
				'result'    => 'all' === $view ? '' : $view,
				'search'    => $state['s'],
				'order'     => $state['order'],
				'limit'     => $state['per_page'],
				'offset'    => $state['offset'],
			)
		);

		$retention = \WBListora\Workflow\Notifications::get_retention_days();
		$export    = add_query_arg( '_wpnonce', wp_create_nonce( 'wp_rest' ), rest_url( 'listora/v1/settings/notifications/log/export' ) );
		$action    = admin_url( 'admin-post.php' );
		$nonce     = wp_create_nonce( 'wb_listora_email_log' );
		?>
		<div class="wrap wb-listora-admin">
			<?php
			wb_listora_render_admin_header(
				array(
					'title'       => __( 'Email Log', 'wb-listora' ),
					'icon'        => 'mail',
					'description' => __( 'Every email Listora sent: who got it, whether it went, and exactly what it said.', 'wb-listora' ),
					'actions'     => static function () use ( $export, $action, $nonce ) {
						$clear = add_query_arg(
							array(
								'action'   => 'wb_listora_email_log',
								'do'       => 'clear',
								'_wpnonce' => $nonce,
							),
							$action
						);
						?>
						<a class="listora-btn listora-btn--sm" href="<?php echo esc_url( $export ); ?>"><i data-lucide="download" aria-hidden="true"></i> <?php esc_html_e( 'Export CSV', 'wb-listora' ); ?></a>
						<a class="listora-btn listora-btn--sm" href="<?php echo esc_url( $clear ); ?>" data-confirm-title="<?php esc_attr_e( 'Clear the email log?', 'wb-listora' ); ?>" data-confirm-message="<?php esc_attr_e( 'Every entry is deleted. Emails already sent are not affected.', 'wb-listora' ); ?>" data-confirm-label="<?php esc_attr_e( 'Clear log', 'wb-listora' ); ?>"><i data-lucide="trash-2" aria-hidden="true"></i> <?php esc_html_e( 'Clear log', 'wb-listora' ); ?></a>
						<?php
					},
				)
			);
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display flag from our own redirect.
			$notice  = isset( $_GET['log_notice'] ) ? sanitize_key( wp_unslash( $_GET['log_notice'] ) ) : '';
			$notices = array(
				'resent'        => array( 'success', __( 'Email sent again. The new attempt is at the top of the log.', 'wb-listora' ) ),
				'resend_failed' => array( 'error', __( 'The email could not be sent again. The new attempt and its error are at the top of the log.', 'wb-listora' ) ),
				'cleared'       => array( 'success', __( 'Email log cleared.', 'wb-listora' ) ),
				'retention'     => array( 'success', __( 'Retention saved. Older entries were removed.', 'wb-listora' ) ),
			);
			if ( isset( $notices[ $notice ] ) ) {
				printf( '<div class="notice notice-%1$s listora-notice is-dismissible"><p>%2$s</p></div>', esc_attr( $notices[ $notice ][0] ), esc_html( $notices[ $notice ][1] ) );
			}
			?>
			<form class="listora-inline-form listora-inline-form--compact" method="post" action="<?php echo esc_url( $action ); ?>">
				<input type="hidden" name="action" value="wb_listora_email_log"><input type="hidden" name="do" value="retention">
				<?php wp_nonce_field( 'wb_listora_email_log' ); ?>
				<label for="listora-email-log-retention"><?php esc_html_e( 'Keep entries for', 'wb-listora' ); ?></label>
				<select id="listora-email-log-retention" name="retention_days" class="listora-filter-select">
					<?php foreach ( \WBListora\Workflow\Notifications::retention_choices() as $days => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $days ); ?>" <?php selected( $retention, $days ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="listora-btn listora-btn--sm"><?php esc_html_e( 'Save', 'wb-listora' ); ?></button>
				<span class="listora-muted">
					<?php
					echo esc_html(
						0 === $retention
							? __( 'Entries are kept until you clear the log.', 'wb-listora' )
							/* translators: %d: days. */
							: sprintf( _n( 'Entries older than %d day are removed each day.', 'Entries older than %d days are removed each day.', $retention, 'wb-listora' ), $retention )
					);
					?>
				</span>
			</form>
			<?php
			$events = array();
			foreach ( \WBListora\Workflow\Email_Log::events() as $key ) {
				$events[ $key ] = wb_listora_event_label( $key );
			}
			asort( $events );

			// Bodies for the page's drawers in one query, not one per row.
			$bodies = \WBListora\Workflow\Email_Log::bodies( wp_list_pluck( $result['rows'], 'id' ) );
			$rows   = array();
			foreach ( $result['rows'] as $row ) {
				$rows[] = self::row( $row, (string) ( $bodies[ (int) $row['id'] ] ?? '' ), $action, $nonce );
			}

			$table->render(
				array(
					'id'         => 'email_log',
					'base_url'   => $base_url,
					'state'      => $state,
					'total'      => $result['total'],
					/* translators: %s: number of emails. */
					'count_text' => sprintf( _n( '%s email', '%s emails', $result['total'], 'wb-listora' ), number_format_i18n( $result['total'] ) ),
					'views'      => array(
						'all'    => array( __( 'All', 'wb-listora' ), $counts['all'] ),
						'sent'   => array( __( 'Sent', 'wb-listora' ), $counts['sent'] ),
						'failed' => array( __( 'Failed', 'wb-listora' ), $counts['failed'] ),
					),
					'view_arg'   => 'result',
					'view'       => $view,
					'search'     => __( 'Search subjects', 'wb-listora' ),
					'filters'    => array(
						array(
							'name'    => 'event',
							'label'   => __( 'Any email', 'wb-listora' ),
							'options' => $events,
						),
						array(
							'name'    => 'recipient',
							'label'   => __( 'Recipient email', 'wb-listora' ),
							'options' => null,
						),
					),
					'columns'    => array(
						'date'      => array(
							'label'    => __( 'Sent', 'wb-listora' ),
							'sortable' => true,
						),
						'email'     => array( 'label' => __( 'Email', 'wb-listora' ) ),
						'recipient' => array( 'label' => __( 'To', 'wb-listora' ) ),
						'result'    => array( 'label' => __( 'Result', 'wb-listora' ) ),
					),
					'rows'       => $rows,
					'empty'      => array(
						'title' => __( 'No emails logged yet', 'wb-listora' ),
						'text'  => __( 'Emails appear here as Listora sends them.', 'wb-listora' ),
						'icon'  => 'mail',
					),
				)
			);
			?>
		</div>
		<?php
	}

	/**
	 * One log entry as a table row.
	 *
	 * @param array<mixed>  $row    Email_Log row.
	 * @param string $body   The email's HTML body ('' when not kept).
	 * @param string $action admin-post URL.
	 * @param string $nonce  Action nonce.
	 * @return array<mixed> Admin_Table row.
	 */
	private static function row( array $row, $body, $action, $nonce ) {
		$id      = (int) $row['id'];
		$sent_at = get_date_from_gmt( (string) $row['sent_at'] );
		$when    = mysql2date( (string) get_option( 'date_format' ), $sent_at ) . ' ' . mysql2date( (string) get_option( 'time_format' ), $sent_at );

		$actions = array();
		if ( ! empty( $row['has_body'] ) ) {
			$actions[] = array(
				'label'   => $row['success'] ? __( 'Send again', 'wb-listora' ) : __( 'Resend', 'wb-listora' ),
				'url'     => add_query_arg(
					array(
						'action'   => 'wb_listora_email_log',
						'do'       => 'resend',
						'id'       => $id,
						'_wpnonce' => $nonce,
					),
					$action
				),
				'primary' => ! $row['success'],
				'more'    => (bool) $row['success'],
				/* translators: %s: recipient. */
				'confirm' => sprintf( __( 'The same email goes to %s again.', 'wb-listora' ), (string) $row['recipient'] ),
			);
		}

		$email = '<span class="listora-row-title">' . esc_html( wb_listora_event_label( (string) $row['event_key'] ) ) . '</span><br><span class="listora-muted listora-clamp">' . esc_html( (string) $row['subject'] ) . '</span>';
		if ( (int) $row['resent_from'] > 0 ) {
			$email .= '<br><span class="listora-badge listora-badge--muted">' . esc_html__( 'Resent', 'wb-listora' ) . '</span>';
		}

		return array(
			'id'      => $id,
			'label'   => (string) $row['subject'],
			'cells'   => array(
				'date'      => esc_html( $when ),
				'email'     => $email,
				'recipient' => esc_html( (string) $row['recipient'] ),
				'result'    => $row['success']
					? '<span class="listora-badge listora-badge--success">' . esc_html__( 'Sent', 'wb-listora' ) . '</span>'
					: '<span class="listora-badge listora-badge--danger">' . esc_html__( 'Failed', 'wb-listora' ) . '</span><br><span class="listora-muted">' . esc_html( (string) $row['error'] ) . '</span>',
			),
			'actions' => $actions,
			'detail'  => '' !== $body ? self::detail( $row, $body ) : '',
		);
	}

	/**
	 * The email as the member received it, in a sandboxed frame so its
	 * styles and links cannot touch wp-admin.
	 *
	 * @param array<mixed>  $row  Email_Log row.
	 * @param string $body HTML body.
	 * @return string Escaped HTML.
	 */
	private static function detail( array $row, $body ) {
		$html  = '<p><strong>' . esc_html__( 'To', 'wb-listora' ) . ':</strong> ' . esc_html( (string) $row['recipient'] ) . '<br><strong>' . esc_html__( 'Subject', 'wb-listora' ) . ':</strong> ' . esc_html( (string) $row['subject'] ) . '</p>';
		$html .= '<iframe class="listora-email-preview" sandbox="" title="' . esc_attr__( 'Email content', 'wb-listora' ) . '" srcdoc="' . esc_attr( $body ) . '"></iframe>';
		return $html;
	}
}
