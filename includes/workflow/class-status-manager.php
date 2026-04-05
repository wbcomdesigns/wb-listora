<?php
/**
 * Status Manager — handles listing status transitions and validation.
 *
 * @package WBListora\Workflow
 */

namespace WBListora\Workflow;

defined( 'ABSPATH' ) || exit;

/**
 * Manages listing lifecycle statuses and transitions.
 */
class Status_Manager {

	/**
	 * Valid status transitions.
	 *
	 * @var array From => [allowed to statuses]
	 */
	private static $transitions = array(
		'draft'               => array( 'pending', 'publish', 'listora_payment' ),
		'pending'             => array( 'publish', 'listora_rejected', 'draft' ),
		'publish'             => array( 'listora_expired', 'listora_deactivated', 'draft', 'pending' ),
		'listora_rejected'    => array( 'pending', 'draft' ),
		'listora_expired'     => array( 'publish', 'draft', 'listora_payment' ),
		'listora_deactivated' => array( 'publish', 'draft' ),
		'listora_payment'     => array( 'pending', 'publish' ),
	);

	/**
	 * Constructor — hooks into status transitions.
	 */
	public function __construct() {
		add_action( 'transition_post_status', array( $this, 'on_transition' ), 10, 3 );
	}

	/**
	 * Handle status transitions.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post object.
	 */
	public function on_transition( $new_status, $old_status, $post ) {
		if ( 'listora_listing' !== $post->post_type || $new_status === $old_status ) {
			return;
		}

		// Set expiration date on publish if not already set.
		if ( 'publish' === $new_status && ! get_post_meta( $post->ID, '_listora_expiration_date', true ) ) {
			$this->set_expiration( $post->ID );
		}

		// Clear expiry reminder flags on status change so reminders
		// are sent again if the listing is renewed and later approaches expiry.
		delete_post_meta( $post->ID, '_listora_expiry_reminded_7d' );
		delete_post_meta( $post->ID, '_listora_expiry_reminded_1d' );

		/**
		 * Fires on listing status change.
		 *
		 * @param int    $post_id    Post ID.
		 * @param string $new_status New status.
		 * @param string $old_status Old status.
		 */
		do_action( "wb_listora_listing_{$new_status}", $post->ID, $old_status );
	}

	/**
	 * Set expiration date for a listing based on type config or plan.
	 *
	 * @param int $post_id Post ID.
	 */
	private function set_expiration( $post_id ) {
		$registry = \WBListora\Core\Listing_Type_Registry::instance();
		$type     = $registry->get_for_post( $post_id );

		if ( ! $type ) {
			return;
		}

		// Check for date-based expiration (events use end_date).
		if ( $type->has_end_date_field() ) {
			$end_date = \WBListora\Core\Meta_Handler::get_value( $post_id, 'end_date' );
			if ( ! $end_date ) {
				$end_date = \WBListora\Core\Meta_Handler::get_value( $post_id, 'deadline' );
			}
			if ( $end_date ) {
				$grace  = DAY_IN_SECONDS; // 24h after event ends.
				$expiry = gmdate( 'Y-m-d H:i:s', strtotime( $end_date ) + $grace );
				update_post_meta( $post_id, '_listora_expiration_date', $expiry );
				return;
			}
		}

		// Time-based expiration from type config.
		$days = (int) $type->get_prop( 'expiration_days' );

		// Check plan duration if Pro plan is set.
		$plan_id = (int) get_post_meta( $post_id, '_listora_plan_id', true );
		if ( $plan_id > 0 ) {
			$plan_days = (int) get_post_meta( $plan_id, '_listora_plan_duration_days', true );
			if ( $plan_days > 0 ) {
				$days = $plan_days;
			}
		}

		if ( $days > 0 ) {
			$expiry = gmdate( 'Y-m-d H:i:s', time() + ( $days * DAY_IN_SECONDS ) );
			update_post_meta( $post_id, '_listora_expiration_date', $expiry );
		}
	}

	/**
	 * Check if a status transition is valid.
	 *
	 * @param string $from Current status.
	 * @param string $to   Target status.
	 * @return bool
	 */
	public static function is_valid_transition( $from, $to ) {
		if ( ! isset( self::$transitions[ $from ] ) ) {
			return false;
		}
		return in_array( $to, self::$transitions[ $from ], true );
	}

	/**
	 * Get all valid statuses for listings.
	 *
	 * @return array
	 */
	public static function get_statuses() {
		return array(
			'draft'               => __( 'Draft', 'wb-listora' ),
			'pending'             => __( 'Pending Review', 'wb-listora' ),
			'publish'             => __( 'Published', 'wb-listora' ),
			'listora_rejected'    => __( 'Rejected', 'wb-listora' ),
			'listora_expired'     => __( 'Expired', 'wb-listora' ),
			'listora_deactivated' => __( 'Deactivated', 'wb-listora' ),
			'listora_payment'     => __( 'Pending Payment', 'wb-listora' ),
		);
	}
}
