<?php
/**
 * Template Name: Listora Full Width
 *
 * Full-width page template for directory pages (listings, dashboard, add-listing).
 * No sidebar — content uses the full page width.
 *
 * Plugin::add_listora_body_class() swaps the theme's sidebar body classes for
 * no-sidebar/layout-wide, and this template never calls get_sidebar().
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
