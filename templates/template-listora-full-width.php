<?php
/**
 * Template Name: Listora Full Width
 *
 * Full-width page template for directory pages (listings, dashboard, add-listing).
 * No sidebar — content uses the full page width.
 *
 * How it works:
 * - The body_class filter in Plugin::add_listora_body_class() removes sidebar classes
 *   (has-sidebar-right, has-sidebar-left, sticky-sidebar-enable) and adds no-sidebar/layout-wide.
 * - This template does NOT output the sidebar via get_sidebar().
 * - Works with any theme — BuddyX, Astra, GeneratePress, Kadence, TT25, etc.
 *
 * Themes can override this file by copying it to: {theme}/wb-listora/template-listora-full-width.php
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="primary" class="site-main">
	<div class="listora-page-wrap">
		<?php
		while ( have_posts() ) :
			the_post();
			the_content();
		endwhile;
		?>
	</div>
</main>

<?php
get_footer();
