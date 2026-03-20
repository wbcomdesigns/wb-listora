<?php
/**
 * Template Name: Listora Full Width
 *
 * Full-width page template for directory pages (listings, dashboard, add-listing).
 * No sidebar — content uses the full page width.
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="listora-page-wrap">
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>
</div>

<?php
get_footer();
