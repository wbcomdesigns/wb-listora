<?php
/**
 * Single Listing Template — Full-width layout.
 *
 * Uses the theme's header/footer but replaces post content area
 * with the listing-detail block via the_content filter.
 * The actual block injection is handled by Plugin::inject_listing_detail().
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="listora-single-wrap">
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>
</div>

<?php
get_footer();
