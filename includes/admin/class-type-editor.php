<?php
/**
 * Listing Type Editor — list view + editor view (Pattern D layout).
 *
 * @package WBListora\Admin
 */

namespace WBListora\Admin;

defined( 'ABSPATH' ) || exit;

use WBListora\Core\Field_Registry;
use WBListora\Core\Listing_Type_Registry;

/**
 * Renders the Listing Types admin page with list and editor views.
 */
class Type_Editor {

	/**
	 * Icon options for the Type Editor dropdown.
	 *
	 * Keys come from the renderer (so every option draws); labels come from
	 * `$icon_options` where one exists, otherwise from the slug itself.
	 *
	 * @since 1.6.0
	 *
	 * @return array<string, string> slug => label, alphabetical by label.
	 */
	private static function icon_choices() {
		$names = function_exists( 'wb_listora_get_icon_choices' ) ? wb_listora_get_icon_choices() : array();

		if ( empty( $names ) ) {
			return self::$icon_options;
		}

		$choices = array();

		foreach ( $names as $slug ) {
			$choices[ $slug ] = self::$icon_options[ $slug ] ?? ucwords( str_replace( '-', ' ', $slug ) );
		}

		asort( $choices );

		return $choices;
	}

	/**
	 * Human-readable labels for the icons this editor offers.
	 *
	 * Labels only — the authoritative NAME list is the renderer's, resolved in
	 * `icon_choices()` above. A slug missing from here still appears, titled
	 * from the slug, so this map never gates what is offerable.
	 *
	 * @var array<string, string>
	 */
	private static $icon_options = array(
		'building-2'     => 'Building',
		'utensils'       => 'Utensils',
		'home'           => 'Home',
		'hotel'          => 'Hotel',
		'briefcase'      => 'Briefcase',
		'calendar'       => 'Calendar',
		'shopping-bag'   => 'Shopping Bag',
		'heart'          => 'Heart',
		'car'            => 'Car',
		'plane'          => 'Plane',
		'map-pin'        => 'Map Pin',
		'coffee'         => 'Coffee',
		'music'          => 'Music',
		'camera'         => 'Camera',
		'book-open'      => 'Book',
		'dumbbell'       => 'Gym',
		'stethoscope'    => 'Medical',
		'graduation-cap' => 'Education',
		'landmark'       => 'Landmark',
		'store'          => 'Store',
		'wrench'         => 'Wrench',
		'palette'        => 'Art',
		'dog'            => 'Pets',
		'trees'          => 'Nature',
		'ship'           => 'Ship',
		'tent'           => 'Camping',
		'church'         => 'Church',
		'theater'        => 'Theater',
		'sparkles'       => 'Sparkles',
		'layout-grid'    => 'Grid',
	);

	/**
	 * Common Schema.org types for directory listings.
	 *
	 * @var array
	 */
	private static $schema_types = array(
		'LocalBusiness',
		'Restaurant',
		'Hotel',
		'Store',
		'HealthAndBeautyBusiness',
		'AutomotiveBusiness',
		'EntertainmentBusiness',
		'FinancialService',
		'FoodEstablishment',
		'GovernmentOffice',
		'MedicalBusiness',
		'ProfessionalService',
		'RealEstateAgent',
		'SportsActivityLocation',
		'TouristAttraction',
		'Event',
		'Organization',
		'Place',
		'LodgingBusiness',
		'EducationalOrganization',
	);

	/**
	 * Render the page — dispatches to list or editor view.
	 */
	public function render() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- View dispatch only, no data mutation.
		$edit_slug = isset( $_GET['edit'] ) ? sanitize_title( wp_unslash( $_GET['edit'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_new = isset( $_GET['action'] ) && 'new' === $_GET['action'];

		if ( $edit_slug || $is_new ) {
			$this->render_editor( $edit_slug );
		} else {
			$this->render_list();
		}
	}

	/**
	 * Render the list view (Pattern B table).
	 */
	private function render_list() {
		$types = Listing_Type_Registry::instance()->get_all();

		echo '<div class="wrap wb-listora-admin">';

		// Page header.
		echo '<div class="listora-page-header">';
		echo '<div class="listora-page-header__left">';
		echo '<h1 class="listora-page-header__title"><i data-lucide="layout-grid"></i> ';
		echo esc_html__( 'Listing Types', 'wb-listora' ) . '</h1>';
		echo '<p class="listora-page-header__desc">';
		echo esc_html__( 'Configure the types of listings in your directory.', 'wb-listora' ) . '</p>';
		echo '</div>';
		echo '<div class="listora-page-header__actions">';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=listora-listing-types&action=new' ) ) . '" class="listora-btn wp-element-button listora-btn--primary">';
		echo '<i data-lucide="plus"></i> ' . esc_html__( 'Add New Type', 'wb-listora' ) . '</a>';
		echo '</div>';
		echo '</div>';

		if ( empty( $types ) ) {
			echo '<div class="listora-empty-state">';
			echo '<div class="listora-empty-state__icon"><i data-lucide="layout-grid"></i></div>';
			echo '<p class="listora-empty-state__title">' . esc_html__( 'No listing types yet', 'wb-listora' ) . '</p>';
			echo '<p class="listora-empty-state__desc">' . esc_html__( 'Create your first listing type to get started.', 'wb-listora' ) . '</p>';
			echo '</div>';
		} else {
			// Wrap table in .listora-card so it matches Moderators / Badges /
			// Audit Log / Analytics card chrome (Rule 4 — uniform card pattern).
			echo '<div class="listora-card">';
			echo '<table class="listora-table">';
			echo '<thead><tr>';
			echo '<th>' . esc_html__( 'Icon', 'wb-listora' ) . '</th>';
			echo '<th>' . esc_html__( 'Name', 'wb-listora' ) . '</th>';
			echo '<th>' . esc_html__( 'Slug', 'wb-listora' ) . '</th>';
			echo '<th>' . esc_html__( 'Fields', 'wb-listora' ) . '</th>';
			echo '<th>' . esc_html__( 'Listings', 'wb-listora' ) . '</th>';
			echo '<th>' . esc_html__( 'Schema Type', 'wb-listora' ) . '</th>';
			echo '<th>' . esc_html__( 'Actions', 'wb-listora' ) . '</th>';
			echo '</tr></thead><tbody>';

			// Resolved once — the badge marks whichever type new submissions
			// are pre-selected to. Empty string when the owner has set none.
			$default_type_slug = wb_listora_get_default_listing_type();

			foreach ( $types as $type ) {
				$slug  = $type->get_slug();
				$icon  = $type->get_icon() ? $type->get_icon() : 'folder';
				$color = $type->get_color() ? $type->get_color() : '#0073aa';
				$term  = get_term_by( 'slug', $slug, 'listora_listing_type' );
				$count = $term ? (int) $term->count : 0;

				echo '<tr data-type-slug="' . esc_attr( $slug ) . '">';
				echo '<td><div class="listora-type-icon" style="--listora-type-color:' . esc_attr( $color ) . ';"><i data-lucide="' . esc_attr( $icon ) . '"></i></div></td>';
				echo '<td><a href="' . esc_url( admin_url( 'admin.php?page=listora-listing-types&edit=' . $slug ) ) . '" class="listora-row-title">' . esc_html( $type->get_name() ) . '</a>';
				if ( $slug === $default_type_slug ) {
					echo ' <span class="listora-badge listora-badge--info">' . esc_html__( 'Default', 'wb-listora' ) . '</span>';
				}
				// At-a-glance flag for the same condition the editor warns about
				// (BC 10190574406): submissions on, but nothing to categorise
				// into. Lets an owner spot it across every type without opening
				// each one. A deliberately uncategorised type is legitimate, so
				// this is an indicator, never an error.
				if ( (bool) $type->get_prop( 'submission_enabled' ) && ! $type->get_allowed_categories() ) {
					echo ' <span class="listora-badge listora-badge--warning" title="'
						. esc_attr__( 'Members can submit this type, but their listings will not be filed under any category.', 'wb-listora' )
						. '">' . esc_html__( 'No categories', 'wb-listora' ) . '</span>';
				}
				echo '</td>';
				echo '<td><code>' . esc_html( $slug ) . '</code></td>';
				echo '<td>' . esc_html( count( $type->get_all_fields() ) ) . '</td>';
				echo '<td>' . esc_html( $count ) . '</td>';
				echo '<td>' . esc_html( $type->get_schema_type() ) . '</td>';
				echo '<td>';
				echo '<div class="listora-row-actions">';
				echo '<a href="' . esc_url( admin_url( 'admin.php?page=listora-listing-types&edit=' . $slug ) ) . '" class="listora-action-link">' . esc_html__( 'Edit', 'wb-listora' ) . '</a>';
				echo '<button type="button" class="listora-action-link wp-element-button listora-action-link--danger listora-delete-type" data-slug="' . esc_attr( $slug ) . '" data-name="' . esc_attr( $type->get_name() ) . '">' . esc_html__( 'Delete', 'wb-listora' ) . '</button>';
				echo '</div>';
				echo '</td>';
				echo '</tr>';
			}

			echo '</tbody></table>';
			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Render the editor view (Pattern D — header + two-column).
	 *
	 * @param string $slug Type slug to edit (empty for new type).
	 */
	private function render_editor( $slug ) {
		$registry = Listing_Type_Registry::instance();
		$type     = $slug ? $registry->get( $slug ) : null;
		$is_new   = empty( $slug ) || ! $type;

		// Build field groups data for JS.
		$field_groups_data = array();
		if ( $type ) {
			foreach ( $type->get_field_groups() as $group ) {
				$field_groups_data[] = $group->to_array();
			}
		}

		// Get all categories for the sidebar.
		$all_categories = $this->get_all_categories();
		$allowed_cats   = $type ? $type->get_allowed_categories() : array();

		// Type properties for sidebar form.
		$type_name       = $type ? $type->get_name() : '';
		$type_slug       = $type ? $type->get_slug() : '';
		$type_icon       = $type ? $type->get_icon() : 'building-2';
		$type_color      = $type ? $type->get_color() : '#0073aa';
		$type_schema     = $type ? $type->get_schema_type() : 'LocalBusiness';
		$map_enabled     = $type ? (bool) $type->get_prop( 'map_enabled' ) : true;
		$review_enabled  = $type ? (bool) $type->get_prop( 'review_enabled' ) : true;
		$submission_on   = $type ? (bool) $type->get_prop( 'submission_enabled' ) : true;
		$mod_value       = $type ? $type->get_prop( 'moderation' ) : null;
		$moderation      = $mod_value ? $mod_value : 'manual';
		$expiration_days = $type ? (int) $type->get_prop( 'expiration_days' ) : 365;
		$is_default_type = $type_slug && $type_slug === wb_listora_get_default_listing_type();

		echo '<div class="wrap wb-listora-admin">';

		// ── Editor Header ──.
		echo '<div class="listora-editor-header">';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=listora-listing-types' ) ) . '" class="listora-btn wp-element-button">';
		echo '<i data-lucide="arrow-left"></i> ' . esc_html__( 'Back to Types', 'wb-listora' ) . '</a>';
		echo '<h1 class="listora-editor-header__title">';
		if ( $is_new ) {
			echo esc_html__( 'Add New Type', 'wb-listora' );
		} else {
			/* translators: %s: listing type name */
			printf( esc_html__( 'Edit: %s', 'wb-listora' ), esc_html( $type_name ) );
		}
		echo '</h1>';
		echo '<div class="listora-editor-header__actions">';
		echo '<button type="button" id="listora-save-type" class="listora-btn wp-element-button listora-btn--primary">';
		echo '<i data-lucide="save"></i> ' . esc_html__( 'Save Type', 'wb-listora' ) . '</button>';
		echo '</div>';
		echo '</div>';

		/*
		 * Submissions on + zero allowed categories is a legitimate configuration
		 * — a type can be deliberately uncategorised, and BC 10180373117 exists
		 * precisely to let members submit against one. But an owner who simply
		 * forgot to assign categories gets a silently uncategorised directory:
		 * listings that never appear under any category, discovered weeks later
		 * from an empty archive. Inform, never block (BC 10190574406).
		 *
		 * The `listora-notice` class is required — Free's own admin CSS hides
		 * `.wb-listora-admin .notice:not(.listora-notice)`, which is how three
		 * other owner-facing messages shipped invisible (BC 10190572606).
		 */
		if ( ! $is_new && $submission_on && ! $allowed_cats ) {
			echo '<div class="notice listora-notice notice-warning">';
			echo '<p><strong>' . esc_html__( 'This type has no categories.', 'wb-listora' ) . '</strong> ';
			echo esc_html__( 'Members can submit it, but their listings will not be filed under any category and will not appear in any category archive or filter.', 'wb-listora' ) . '</p>';
			echo '<p>' . esc_html__( 'If that is intentional, nothing needs to change. Otherwise, assign categories in the sidebar below.', 'wb-listora' ) . '</p>';
			echo '</div>';
		}

		// ── Two-column layout ──.
		echo '<div class="listora-editor-layout">';

		// ── Main panel (field builder) ──.
		echo '<div class="listora-editor-main">';
		printf(
			'<div id="listora-field-builder" data-type-slug="%s" data-field-groups="%s" data-field-types="%s"></div>',
			esc_attr( $type_slug ),
			esc_attr( wp_json_encode( $field_groups_data ) ),
			esc_attr( wp_json_encode( Field_Registry::instance()->get_all() ) )
		);
		echo '</div>';

		// ── Sidebar ──.
		echo '<div class="listora-editor-sidebar">';

		// Type Settings card.
		echo '<div class="listora-card">';
		echo '<div class="listora-card__head"><p class="listora-card__title">';
		echo esc_html__( 'TYPE SETTINGS', 'wb-listora' ) . '</p></div>';
		echo '<div class="listora-card__body">';

		// Name.
		echo '<div class="listora-meta-field">';
		echo '<label for="listora-type-name">' . esc_html__( 'Name', 'wb-listora' ) . '</label>';
		echo '<input type="text" id="listora-type-name" class="listora-input" value="' . esc_attr( $type_name ) . '" placeholder="' . esc_attr__( 'e.g. Restaurant', 'wb-listora' ) . '">';
		echo '</div>';

		// Slug.
		echo '<div class="listora-meta-field">';
		echo '<label for="listora-type-slug">' . esc_html__( 'Slug', 'wb-listora' ) . '</label>';
		echo '<input type="text" id="listora-type-slug" class="listora-input" value="' . esc_attr( $type_slug ) . '"';
		if ( ! $is_new ) {
			echo ' readonly';
		}
		echo '>';
		echo '<small>' . esc_html__( 'Auto-generated from name. Cannot be changed after creation.', 'wb-listora' ) . '</small>';
		echo '</div>';

		// Icon.
		echo '<div class="listora-meta-field">';
		echo '<label for="listora-type-icon">' . esc_html__( 'Icon', 'wb-listora' ) . '</label>';
		echo '<select id="listora-type-icon" class="listora-input">';
		/*
		 * Options come from the renderer's own map, so this dropdown cannot
		 * offer an icon the frontend will not draw. It used to carry its own
		 * hardcoded list of 30 against a 42-icon renderer, and 21 of those 30
		 * were absent from it — picking "Gym" on a listing type produced a
		 * card with no icon at all on the Add Listing page, with no error
		 * anywhere (BC 10194825231).
		 *
		 * `self::$icon_options` is retained only for its human labels; any
		 * canonical icon without one falls back to a title-cased slug.
		 */
		foreach ( self::icon_choices() as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $type_icon, $value, false ) . '>' . esc_html( $label ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selected() returns safe HTML attribute.
		}
		echo '</select>';
		echo '</div>';

		// Color.
		echo '<div class="listora-meta-field">';
		echo '<label for="listora-type-color">' . esc_html__( 'Color', 'wb-listora' ) . '</label>';
		echo '<input type="color" id="listora-type-color" value="' . esc_attr( $type_color ) . '">';
		echo '</div>';

		// Schema Type.
		echo '<div class="listora-meta-field">';
		echo '<label for="listora-type-schema">' . esc_html__( 'Schema.org Type', 'wb-listora' ) . '</label>';
		echo '<select id="listora-type-schema" class="listora-input">';
		foreach ( self::$schema_types as $schema ) {
			echo '<option value="' . esc_attr( $schema ) . '"' . selected( $type_schema, $schema, false ) . '>' . esc_html( $schema ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selected() returns safe HTML attribute.
		}
		echo '</select>';
		echo '</div>';

		echo '</div>'; // .listora-card__body
		echo '</div>'; // .listora-card

		// Features card.
		echo '<div class="listora-card">';
		echo '<div class="listora-card__head"><p class="listora-card__title">';
		echo esc_html__( 'FEATURES', 'wb-listora' ) . '</p></div>';
		echo '<div class="listora-card__body">';

		echo '<label class="listora-checkbox-label"><input type="checkbox" id="listora-type-map"';
		checked( $map_enabled );
		echo '> ' . esc_html__( 'Map enabled', 'wb-listora' ) . '</label>';

		echo '<label class="listora-checkbox-label"><input type="checkbox" id="listora-type-review"';
		checked( $review_enabled );
		echo '> ' . esc_html__( 'Reviews enabled', 'wb-listora' ) . '</label>';

		echo '<label class="listora-checkbox-label"><input type="checkbox" id="listora-type-submission"';
		checked( $submission_on );
		echo '> ' . esc_html__( 'Frontend submission', 'wb-listora' ) . '</label>';

		// Default type for new submissions.
		//
		// Presented per-type because that is where an owner looks for it, but
		// stored as ONE site setting (`default_listing_type`). "Only one type
		// can be default" is then true by construction — as per-type term meta
		// the invariant would have to be re-enforced on every save and could
		// drift to two defaults, or none, after a partial write.
		//
		// Not to be confused with the unrelated `is_default` type prop, which
		// means "shipped with the plugin" and is true for all built-in types.
		echo '<label class="listora-checkbox-label"><input type="checkbox" id="listora-type-default"';
		checked( $is_default_type );
		echo '> ' . esc_html__( 'Default for new submissions', 'wb-listora' ) . '</label>';
		echo '<p class="listora-meta-field__hint">';
		echo esc_html__( 'Pre-selects this type on the Add Listing form. Only one type can be the default — turning this on for another type turns it off here. Submitters can still choose a different type.', 'wb-listora' );
		echo '</p>';

		// Moderation.
		echo '<div class="listora-meta-field">';
		echo '<label for="listora-type-moderation">' . esc_html__( 'Moderation', 'wb-listora' ) . '</label>';
		echo '<select id="listora-type-moderation" class="listora-input">';
		echo '<option value="manual"' . selected( $moderation, 'manual', false ) . '>' . esc_html__( 'Manual approval', 'wb-listora' ) . '</option>';
		echo '<option value="auto"' . selected( $moderation, 'auto', false ) . '>' . esc_html__( 'Auto-approve', 'wb-listora' ) . '</option>';
		echo '</select>';
		echo '</div>';

		// Expiry.
		echo '<div class="listora-meta-field">';
		echo '<label for="listora-type-expiry">' . esc_html__( 'Listing expires after (days)', 'wb-listora' ) . '</label>';
		echo '<input type="number" id="listora-type-expiry" class="listora-input" value="' . esc_attr( $expiration_days ) . '" min="0">';
		echo '<small>' . esc_html__( '0 = never expires', 'wb-listora' ) . '</small>';
		echo '</div>';

		echo '</div>'; // .listora-card__body
		echo '</div>'; // .listora-card

		// Categories card.
		echo '<div class="listora-card">';
		echo '<div class="listora-card__head"><p class="listora-card__title">';
		echo esc_html__( 'CATEGORIES', 'wb-listora' ) . '</p></div>';
		echo '<div class="listora-card__body" id="listora-type-categories">';

		if ( empty( $all_categories ) ) {
			echo '<p class="listora-text-muted">' . esc_html__( 'No categories found.', 'wb-listora' ) . '</p>';
		} else {
			foreach ( $all_categories as $cat ) {
				$checked = in_array( (int) $cat['id'], array_map( 'intval', $allowed_cats ), true );
				echo '<label class="listora-checkbox-label">';
				echo '<input type="checkbox" name="listora-type-cat[]" value="' . esc_attr( $cat['id'] ) . '"';
				checked( $checked );
				echo '> ' . esc_html( $cat['name'] ) . '</label>';
			}
		}

		echo '</div>'; // .listora-card__body
		echo '</div>'; // .listora-card

		echo '</div>'; // .listora-editor-sidebar
		echo '</div>'; // .listora-editor-layout
		echo '</div>'; // .wrap
	}

	/**
	 * Get all listing categories as a flat array.
	 *
	 * @return array Array of [ 'id' => int, 'name' => string, 'slug' => string ].
	 */
	public function get_all_categories() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'listora_listing_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$categories = array();
		foreach ( $terms as $term ) {
			$categories[] = array(
				'id'   => $term->term_id,
				'name' => $term->name,
				'slug' => $term->slug,
			);
		}

		return $categories;
	}
}
