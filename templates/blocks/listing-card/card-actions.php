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
 * Pro features like comparison toggle and verification badge hook in here.
 *
 * @since 1.0.0
 * @param int $id The listing post ID.
 */
do_action( 'wb_listora_card_actions', $id );

do_action( 'wb_listora_after_card_actions', $view_data );
