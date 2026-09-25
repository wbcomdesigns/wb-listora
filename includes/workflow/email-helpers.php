<?php
/**
 * Email helper functions — public extension surface for email formatting.
 *
 * Pro consumes this function instead of referencing Free's internal
 * `\WBListora\Workflow\Email_Body_Formatter` directly. Per the
 * architecture contract, the function is the documented Free→Pro
 * surface; the implementation class is internal.
 *
 * @package WBListora
 * @since   1.1.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wb_listora_email_html_to_text' ) ) {
	/**
	 * Convert an HTML email body to readable plain text.
	 *
	 * Preserves link URLs inline so screen-reader and text-only clients
	 * still see CTAs. Collapses repeated whitespace and blank lines.
	 *
	 * Used by Free's notification system AND Pro's email helpers — one
	 * canonical implementation, both sides consume.
	 *
	 * @since 1.1.0
	 *
	 * @param string $html HTML email body.
	 * @return string Plain-text body suitable for the multipart/alternative text part.
	 */
	function wb_listora_email_html_to_text( string $html ): string {
		return \WBListora\Workflow\Email_Body_Formatter::html_to_text( $html );
	}
}

if ( ! function_exists( 'wb_listora_log_email' ) ) {
	/**
	 * Record one sent email in Listora > Email Log.
	 *
	 * Free's own notifications log themselves; Pro calls this so its emails
	 * (credits, plans, needs, leads, digests) show up in the same log.
	 *
	 * @since 1.9.0
	 *
	 * @param string $event_key Event slug shown in the log.
	 * @param string $recipient Recipient address.
	 * @param string $subject   Subject line.
	 * @param bool   $success   Whether wp_mail() accepted the message.
	 * @param string $error     Failure reason, when there is one.
	 * @return void
	 */
	function wb_listora_log_email( string $event_key, string $recipient, string $subject, bool $success, string $error = '' ): void {
		\WBListora\Workflow\Notifications::log_send(
			array(
				'event_key' => $event_key,
				'recipient' => $recipient,
				'subject'   => $subject,
				'success'   => $success,
				'error'     => $error,
			)
		);
	}
}
