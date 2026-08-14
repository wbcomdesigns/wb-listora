<?php
/**
 * Listing Reviews block — rating summary + review list + form.
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'listora-base' );

// Enqueue CAPTCHA scripts if enabled.
\WBListora\Captcha::enqueue_scripts();

$unique_id    = $attributes['uniqueId'] ?? '';
$post_id      = get_the_ID();
$show_summary = $attributes['showSummary'] ?? true;
$show_form    = $attributes['showForm'] ?? true;
$per_page     = $attributes['perPage'] ?? 10;
$default_sort = $attributes['defaultSort'] ?? 'newest';

// Site-wide reviews-feature gate. When admin disables Reviews under
// Settings ▸ Features, the ENTIRE standalone listora/listing-reviews block
// must disappear — summary, distribution, sort toolbar, write affordance,
// and the review list (card 9895809632). Previously only $show_form was
// gated, so the summary + list still rendered with a silent 403 on submit.
// Bailing here mirrors the post-type guard below and matches the read+write
// suppression the favorites gate applies on listing-detail (render.php:113).
if ( function_exists( 'wb_listora_feature_enabled' ) && ! wb_listora_feature_enabled( 'reviews' ) ) {
	return;
}

if ( ! $post_id || 'listora_listing' !== get_post_type( $post_id ) ) {
	return;
}

// Rating distribution — loaded via shared helper.
$review_data = \WBListora\Core\Listing_Data::get_review_distribution( $post_id );
$avg         = $review_data['avg'];
$total       = $review_data['total'];
$dist        = $review_data['dist'];

// Determine review sort order.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display filter, no mutation.
$review_sort = isset( $_GET['review_sort'] ) ? sanitize_text_field( wp_unslash( $_GET['review_sort'] ) ) : $default_sort;

// Get reviews.
$reviews = \WBListora\Core\Listing_Data::get_reviews( $post_id, $review_sort, $per_page );

// Check if current user already reviewed.
$user_reviewed = \WBListora\Core\Listing_Data::has_user_reviewed( $post_id, get_current_user_id() );

// Check if current user is listing author.
$is_owner = is_user_logged_in() && (int) get_post_field( 'post_author', $post_id ) === get_current_user_id();

$context = (string) wp_json_encode(
	array(
		'listingId'  => $post_id,
		'reviewSort' => $default_sort,
	)
);

$visibility_classes = \WBListora\Block_CSS::visibility_classes( $attributes );
$block_classes      = 'listora-block' . ( $unique_id ? ' listora-block-' . $unique_id : '' ) . ( $visibility_classes ? ' ' . $visibility_classes : '' );

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class'               => 'listora-reviews ' . $block_classes,
		'data-wp-interactive' => 'listora/directory',
		'data-wp-context'     => $context,
	)
);

// Resolve listing type slug and review criteria for the form template.
$listing_type_slug = '';
$listing_type_obj  = \WBListora\Core\Listing_Type_Registry::instance()->get_for_post( $post_id );
if ( $listing_type_obj ) {
	$listing_type_slug = $listing_type_obj->get_slug();
}

/**
 * Filter review criteria fields for the current listing type.
 *
 * Pro uses this to inject multi-criteria rating inputs (food, service, etc.).
 *
 * @param array  $criteria  Default criteria (empty array).
 * @param string $type_slug Listing type slug.
 */
// Resolved through the shared helper, which feeds the listing type's SAVED
// criteria into the filter as its base. Passing an empty array here — as this
// did until 1.6.0 — meant a site owner's configured criteria were written,
// stored and then ignored by every reader (BC 10199712310). The helper also
// enforces the {key,label} item shape so a listener returning strings or a
// scalar cannot fatal the template's offset reads.
$review_criteria = wb_listora_get_review_criteria( $listing_type_slug );

// ─── Assemble $view_data for templates ───
$view_data = array(
	'post_id'           => $post_id,
	'show_summary'      => $show_summary,
	'show_form'         => $show_form,
	'per_page'          => $per_page,
	'avg'               => $avg,
	'total'             => $total,
	'dist'              => $dist,
	'review_sort'       => $review_sort,
	'reviews'           => $reviews,
	'user_reviewed'     => $user_reviewed,
	'is_owner'          => $is_owner,
	'wrapper_attrs'     => $wrapper_attrs,
	'unique_id'         => $unique_id,
	'attributes'        => $attributes,
	'listing_type_slug' => $listing_type_slug,
	'review_criteria'   => $review_criteria,
);

// Self-reference for sub-templates.
$view_data['view_data'] = $view_data;

echo \WBListora\Block_CSS::render( $unique_id, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

wb_listora_get_template( 'blocks/listing-reviews/reviews.php', $view_data );
