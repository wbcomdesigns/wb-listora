<?php
/**
 * Suspend / reinstate a member, from the screens owners already use.
 *
 * Deliberately built on WordPress' own Users screens rather than a new Listora
 * page. An owner dealing with an abusive member goes to Users and looks for the
 * person; making them learn a separate screen is how a moderation tool ends up
 * unused at the moment it is needed.
 *
 * Two surfaces, each doing the thing it is best at:
 *
 *   - Users LIST: a status column and Suspend / Reinstate row actions. One
 *     click, nonce-protected, no reason. This is the fast path.
 *   - User PROFILE: a checkbox plus a reason textarea, saved with the rest of
 *     the profile. This is where a reason gets recorded, using WordPress' own
 *     form handling — no JavaScript, no modal, nothing to go wrong.
 *
 * Gated on `moderate_listora_reviews`: the capability that already means "you
 * handle abuse here". Requiring the settings capability instead would force a
 * moderator to escalate to the owner while the abuse continues.
 *
 * @package WBListora\Admin
 * @since   1.5.0
 */

namespace WBListora\Admin;

use WBListora\Core\Capabilities;
use WBListora\Core\Member_Suspension;

defined( 'ABSPATH' ) || exit;

/**
 * Member suspension controls on the WordPress Users screens.
 */
class User_Moderation {

	/**
	 * Query arg carrying the action.
	 */
	const ACTION_ARG = 'listora_member_action';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'manage_users_columns', array( $this, 'add_column' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'render_column' ), 10, 3 );
		add_filter( 'user_row_actions', array( $this, 'add_row_actions' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'handle_row_action' ) );
		add_action( 'admin_notices', array( $this, 'render_notice' ) );

		// Profile screen: the reason field.
		add_action( 'edit_user_profile', array( $this, 'render_profile_field' ) );
		add_action( 'show_user_profile', array( $this, 'render_profile_field' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_profile_field' ) );
		add_action( 'personal_options_update', array( $this, 'save_profile_field' ) );
	}

	/**
	 * Whether the current user may suspend members.
	 *
	 * @return bool
	 */
	private function can_moderate(): bool {
		/**
		 * Filters who may suspend or reinstate a member.
		 *
		 * @since 1.5.0
		 *
		 * @param bool $can Whether the current user may moderate members.
		 */
		return (bool) apply_filters(
			'wb_listora_can_moderate_members',
			current_user_can( Capabilities::CAP_MODERATE_REVIEWS )
		);
	}

	/**
	 * Add the status column.
	 *
	 * @param array<string,string> $columns Existing columns.
	 * @return array<string,string>
	 */
	public function add_column( $columns ) {
		if ( ! $this->can_moderate() ) {
			return $columns;
		}

		$columns['listora_member_status'] = __( 'Listora', 'wb-listora' );

		return $columns;
	}

	/**
	 * Render the status cell.
	 *
	 * Only says something when there IS something to say. A column that prints
	 * "Active" on every row is noise on a 2,000-member site; the useful signal
	 * is the handful that are not.
	 *
	 * @param string $output      Current output.
	 * @param string $column_name Column being rendered.
	 * @param int    $user_id     User for this row.
	 * @return string
	 */
	public function render_column( $output, $column_name, $user_id ) {
		if ( 'listora_member_status' !== $column_name ) {
			return $output;
		}

		$user_id = (int) $user_id;

		if ( Member_Suspension::is_suspended( $user_id ) ) {
			$since  = (string) get_user_meta( $user_id, Member_Suspension::META_SINCE, true );
			$reason = (string) get_user_meta( $user_id, Member_Suspension::META_REASON, true );

			$label = '<span class="listora-member-status listora-member-status--suspended">'
				. esc_html__( 'Suspended', 'wb-listora' ) . '</span>';

			if ( $since ) {
				$label .= '<br /><span class="description">' . esc_html(
					sprintf(
						/* translators: %s: human-readable time difference, e.g. "3 days". */
						__( '%s ago', 'wb-listora' ),
						human_time_diff( (int) strtotime( $since ) )
					)
				) . '</span>';
			}

			if ( $reason ) {
				$label .= '<br /><span class="description">' . esc_html( wp_trim_words( $reason, 12 ) ) . '</span>';
			}

			return $label;
		}

		if ( function_exists( 'wb_listora_is_account_deactivated' ) && wb_listora_is_account_deactivated( $user_id ) ) {
			// Worth distinguishing: this one the MEMBER chose, so an owner
			// should not read it as a moderation decision they forgot making.
			return '<span class="listora-member-status listora-member-status--deactivated">'
				. esc_html__( 'Deactivated by member', 'wb-listora' ) . '</span>';
		}

		return '&mdash;';
	}

	/**
	 * Add Suspend / Reinstate row actions.
	 *
	 * @param array<string,string> $actions Existing row actions.
	 * @param \WP_User             $user    User for this row.
	 * @return array<string,string>
	 */
	public function add_row_actions( $actions, $user ) {
		if ( ! $this->can_moderate() || ! $user instanceof \WP_User ) {
			return $actions;
		}

		// Never offer it against yourself, or against someone who can moderate
		// — suspending a fellow moderator is not a row-action-sized decision.
		if ( (int) $user->ID === get_current_user_id() || user_can( $user, Capabilities::CAP_MODERATE_REVIEWS ) ) {
			return $actions;
		}

		$suspended = Member_Suspension::is_suspended( (int) $user->ID );
		$action    = $suspended ? 'unsuspend' : 'suspend';

		$url = wp_nonce_url(
			add_query_arg(
				array(
					self::ACTION_ARG => $action,
					'user'           => (int) $user->ID,
				),
				admin_url( 'users.php' )
			),
			'listora_member_' . $action . '_' . (int) $user->ID
		);

		$actions[ 'listora_' . $action ] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			$suspended ? esc_html__( 'Reinstate', 'wb-listora' ) : esc_html__( 'Suspend', 'wb-listora' )
		);

		return $actions;
	}

	/**
	 * Handle the row action.
	 *
	 * @return void
	 */
	public function handle_row_action(): void {
		if ( ! isset( $_GET[ self::ACTION_ARG ], $_GET['user'] ) ) {
			return;
		}

		$action  = sanitize_key( wp_unslash( $_GET[ self::ACTION_ARG ] ) );
		$user_id = absint( wp_unslash( $_GET['user'] ) );

		if ( ! in_array( $action, array( 'suspend', 'unsuspend' ), true ) || $user_id <= 0 ) {
			return;
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'listora_member_' . $action . '_' . $user_id ) ) {
			return;
		}

		if ( ! $this->can_moderate() ) {
			return;
		}

		// Same two guards as the row action, re-checked server-side: the URL
		// can be typed, and a nonce only proves intent, not permission.
		if ( $user_id === get_current_user_id() || user_can( $user_id, Capabilities::CAP_MODERATE_REVIEWS ) ) {
			return;
		}

		if ( 'suspend' === $action ) {
			Member_Suspension::suspend( $user_id );
		} else {
			Member_Suspension::unsuspend( $user_id );
		}

		wp_safe_redirect(
			add_query_arg(
				array( 'listora_member_done' => $action ),
				admin_url( 'users.php' )
			)
		);
		exit;
	}

	/**
	 * Confirm what happened, and say what it did NOT do.
	 *
	 * The second half matters more than the first: an owner who suspends
	 * someone reasonably wonders whether their reviews just vanished. Saying so
	 * up front prevents a support ticket and a panicked reinstate.
	 *
	 * @return void
	 */
	public function render_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display of the result of an already-verified action.
		if ( ! isset( $_GET['listora_member_done'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$done = sanitize_key( wp_unslash( $_GET['listora_member_done'] ) );

		if ( 'suspend' === $done ) {
			$message = __( 'Member suspended. They can still sign in and browse, but cannot post or edit anything. Their existing reviews and listings are untouched.', 'wb-listora' );
		} elseif ( 'unsuspend' === $done ) {
			$message = __( 'Member reinstated. They can post again.', 'wb-listora' );
		} else {
			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html( $message )
		);
	}

	/**
	 * Render the suspension field on the user profile screen.
	 *
	 * @param \WP_User $user User being edited.
	 * @return void
	 */
	public function render_profile_field( $user ): void {
		if ( ! $user instanceof \WP_User || ! $this->can_moderate() ) {
			return;
		}

		// Your own profile is not where you suspend yourself.
		if ( (int) $user->ID === get_current_user_id() ) {
			return;
		}

		$suspended = Member_Suspension::is_suspended( (int) $user->ID );
		$reason    = (string) get_user_meta( $user->ID, Member_Suspension::META_REASON, true );
		?>
		<h2><?php esc_html_e( 'Listora moderation', 'wb-listora' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Suspend member', 'wb-listora' ); ?></th>
				<td>
					<?php wp_nonce_field( 'listora_member_profile_' . (int) $user->ID, 'listora_member_profile_nonce' ); ?>
					<label for="listora_member_suspended">
						<input
							type="checkbox"
							name="listora_member_suspended"
							id="listora_member_suspended"
							value="1"
							<?php checked( $suspended ); ?>
						/>
						<?php esc_html_e( 'Prevent this member from posting or editing content', 'wb-listora' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'They can still sign in and browse. Existing reviews and listings stay published — remove those separately if you need to.', 'wb-listora' ); ?>
					</p>

					<p style="margin-top:1em;">
						<label for="listora_member_suspended_reason">
							<strong><?php esc_html_e( 'Reason (optional)', 'wb-listora' ); ?></strong>
						</label><br />
						<textarea
							name="listora_member_suspended_reason"
							id="listora_member_suspended_reason"
							rows="3"
							cols="50"
							class="regular-text"
						><?php echo esc_textarea( $reason ); ?></textarea>
					</p>
					<p class="description">
						<?php esc_html_e( 'Recorded for your own reference and shown in the Users list. It is not emailed to the member.', 'wb-listora' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save the profile field.
	 *
	 * @param int $user_id User being saved.
	 * @return void
	 */
	public function save_profile_field( $user_id ): void {
		$user_id = (int) $user_id;

		$nonce = isset( $_POST['listora_member_profile_nonce'] )
			? sanitize_text_field( wp_unslash( $_POST['listora_member_profile_nonce'] ) )
			: '';

		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'listora_member_profile_' . $user_id ) ) {
			return;
		}

		if ( ! $this->can_moderate() || $user_id === get_current_user_id() ) {
			return;
		}

		if ( user_can( $user_id, Capabilities::CAP_MODERATE_REVIEWS ) ) {
			return;
		}

		$wants_suspended = ! empty( $_POST['listora_member_suspended'] );
		$reason          = isset( $_POST['listora_member_suspended_reason'] )
			? sanitize_textarea_field( wp_unslash( $_POST['listora_member_suspended_reason'] ) )
			: '';

		if ( $wants_suspended ) {
			Member_Suspension::suspend( $user_id, $reason );
		} elseif ( Member_Suspension::is_suspended( $user_id ) ) {
			Member_Suspension::unsuspend( $user_id );
		}
	}
}
