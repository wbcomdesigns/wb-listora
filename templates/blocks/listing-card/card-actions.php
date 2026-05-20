<?php
/**
 * Listing Card — Actions section (favorite button, share, etc.).
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/listing-card/card-actions.php
 *
 * @package WBListora
 *
 * @var int   $id        Listing post ID.
 * @var array $view_data Full view data array.
 */

defined( 'ABSPATH' ) || exit;

$view_data = $view_data ?? get_defined_vars();

do_action( 'wb_listora_before_card_actions', $view_data );

/**
 * Fires after the standard card actions (favorite, rating).
 * Pro features like the Quick View eye + Compare toggle hook in here.
 *
 * Buffered so the actions are wrapped in ONE aligned flex row only when
 * something is actually injected — when no card actions are enabled (the
 * default; both Quick View and Compare are off), nothing renders and the
 * card body has no leftover white space.
 *
 * @since 1.0.0
 * @param int $id The listing post ID.
 */
ob_start();
do_action( 'wb_listora_card_actions', $id );
$wb_listora_card_actions_html = trim( ob_get_clean() );

if ( '' !== $wb_listora_card_actions_html ) {
	// Hook output is trusted plugin/extension markup, already escaped at source.
	echo '<div class="listora-card__actions">' . $wb_listora_card_actions_html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

do_action( 'wb_listora_after_card_actions', $view_data );
