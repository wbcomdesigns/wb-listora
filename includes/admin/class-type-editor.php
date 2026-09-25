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
	/**
	 * Confirm a save that survived a redirect.
	 *
	 * Saving a NEW listing type toasts and then immediately navigates to the
	 * edit screen, which destroys the toast — so from the owner's side the
	 * save was silent and the only way to be sure was to re-read the form
	 * (BC 10167580523). The editor now carries `listora_saved=type` through
	 * the redirect and this prints the confirmation on arrival.
	 *
	 * @return void
	 */
	private static function render_saved_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag.
		$saved = isset( $_GET['listora_saved'] ) ? sanitize_key( wp_unslash( $_GET['listora_saved'] ) ) : '';

		if ( 'type' !== $saved ) {
			return;
		}

		printf(
			'<div class="notice listora-notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html__( 'Listing type saved.', 'wb-listora' )
		);
	}

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
	 * A checkbox list with search, a "Selected only" view and a count.
	 *
	 * The sidebar listed every category (116) and feature (~100) as flat
	 * checkboxes, making the editor ~6,300px tall with no way to find one
	 * (card 10337181179). Shared by both pickers; the list scrolls inside
	 * itself and type-editor.js filters it.
	 *
	 * @param string $key      'cat' or 'feat' (checkbox name listora-type-{key}[]).
	 * @param array  $terms    Each [ 'id', 'name' ].
	 * @param array  $selected Selected term IDs.
	 * @param string $search   Search box label.
	 */
	private static function render_picker( $key, array $terms, array $selected, $search ) {
		$selected = array_map( 'intval', $selected );
		$id       = 'listora-picker-' . $key;
		echo '<div class="listora-picker" data-listora-picker>';
		echo '<div class="listora-picker__tools">';
		echo '<label class="screen-reader-text" for="' . esc_attr( $id ) . '-search">' . esc_html( $search ) . '</label>';
		echo '<input type="search" id="' . esc_attr( $id ) . '-search" class="listora-input listora-picker__search" placeholder="' . esc_attr( $search ) . '" data-listora-picker-search>';
		echo '<label class="listora-picker__only"><input type="checkbox" data-listora-picker-only> ' . esc_html__( 'Selected only', 'wb-listora' ) . '</label>';
		echo '</div>';
		/* translators: 1: selected count, 2: total count. */
		echo '<p class="listora-picker__count" aria-live="polite" data-listora-picker-count data-template="' . esc_attr__( '%1$s of %2$s selected', 'wb-listora' ) . '">' . esc_html( sprintf( __( '%1$s of %2$s selected', 'wb-listora' ), number_format_i18n( count( array_intersect( $selected, array_map( 'intval', wp_list_pluck( $terms, 'id' ) ) ) ) ), number_format_i18n( count( $terms ) ) ) ) . '</p>';
		echo '<div class="listora-picker__list">';
		foreach ( $terms as $term ) {
			echo '<label class="listora-checkbox-label" data-listora-picker-item>';
			echo '<input type="checkbox" name="listora-type-' . esc_attr( $key ) . '[]" value="' . esc_attr( (string) $term['id'] ) . '"' . checked( in_array( (int) $term['id'], $selected, true ), true, false ) . '> ';
			echo esc_html( (string) $term['name'] ) . '</label>';
		}
		echo '<p class="listora-text-muted listora-picker__none" hidden data-listora-picker-none>' . esc_html__( 'Nothing matches.', 'wb-listora' ) . '</p>';
		echo '</div></div>';
	}

	/**
	 * Delete a type from the list, moving its listings first when it has
	 * any (admin-post, nonce + capability).
	 */
	public static function handle_delete() {
		$slug = isset( $_POST['type_slug'] ) ? sanitize_title( wp_unslash( $_POST['type_slug'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified next.
		check_admin_referer( 'wb_listora_delete_type_' . $slug );
		if ( ! current_user_can( 'manage_listora_types' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete listing types.', 'wb-listora' ), 403 );
		}

		$request = new \WP_REST_Request( 'DELETE', '/' . WB_LISTORA_REST_NAMESPACE . '/listing-types/' . $slug );
		$request->set_param( 'slug', $slug );
		$request->set_param( 'reassign_to', isset( $_POST['reassign_to'] ) ? sanitize_title( wp_unslash( $_POST['reassign_to'] ) ) : '' );
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$args = $response->is_error()
			? array( 'type_error' => rawurlencode( (string) ( $data['message'] ?? __( 'The type could not be deleted.', 'wb-listora' ) ) ) )
			: array(
				'type_deleted' => 1,
				'moved'        => (int) ( $data['moved'] ?? 0 ),
			);
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=listora-listing-types' ) ) );
		exit;
	}

	/**
	 * Render the list of listing types.
	 */
	private function render_list() {
		$types = Listing_Type_Registry::instance()->get_all();
		$table = new Admin_Table();

		echo '<div class="wrap wb-listora-admin">';
		echo '<div class="listora-page-header"><div class="listora-page-header__left">';
		echo '<h1 class="listora-page-header__title"><i data-lucide="layout-grid"></i> ' . esc_html__( 'Listing Types', 'wb-listora' ) . '</h1>';
		echo '<p class="listora-page-header__desc">' . esc_html__( 'Each type decides the fields, categories and features a listing has. A draft type stays hidden from members while you set it up.', 'wb-listora' ) . '</p>';
		echo '</div><div class="listora-page-header__actions">';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=listora-listing-types&action=new' ) ) . '" class="listora-btn listora-btn--primary"><i data-lucide="plus"></i> ' . esc_html__( 'Add New Type', 'wb-listora' ) . '</a>';
		echo '</div></div>';
		echo '<hr class="wp-header-end">';
		self::render_saved_notice();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display flags from our own redirect.
		if ( isset( $_GET['type_deleted'] ) ) {
			$moved = isset( $_GET['moved'] ) ? absint( $_GET['moved'] ) : 0;
			/* translators: %d: listings moved. */
			$text = $moved ? sprintf( _n( 'Type deleted. %d listing was moved to the type you chose.', 'Type deleted. %d listings were moved to the type you chose.', $moved, 'wb-listora' ), $moved ) : __( 'Type deleted.', 'wb-listora' );
			echo '<div class="notice notice-success listora-notice is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
		} elseif ( isset( $_GET['type_error'] ) ) {
			echo '<div class="notice notice-error listora-notice is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['type_error'] ) ) ) . '</p></div>';
		}
		// phpcs:enable

		$default_type_slug = wb_listora_get_default_listing_type();
		$rows              = array();
		foreach ( $types as $type ) {
			$rows[] = $this->type_row( $type, $types, $default_type_slug );
		}

		$state = $table->request( 'listing_types', array(), array(), '', 50 );
		$table->render(
			array(
				'id'         => 'types',
				'base_url'   => admin_url( 'admin.php?page=listora-listing-types' ),
				'state'      => $state,
				'total'      => count( $rows ),
				/* translators: %s: number of types. */
				'count_text' => sprintf( _n( '%s listing type', '%s listing types', count( $rows ), 'wb-listora' ), number_format_i18n( count( $rows ) ) ),
				'columns'    => array(
					'type'     => array( 'label' => __( 'Type', 'wb-listora' ) ),
					'status'   => array( 'label' => __( 'Status', 'wb-listora' ) ),
					'fields'   => array( 'label' => __( 'Fields', 'wb-listora' ) ),
					'listings' => array( 'label' => __( 'Listings', 'wb-listora' ) ),
					'schema'   => array(
						'label'    => __( 'Search engines see', 'wb-listora' ),
						'priority' => 3,
					),
				),
				// Types are few (a site has a dozen at most); one page.
				'rows'       => array_slice( $rows, $state['offset'], $state['per_page'] ),
				'empty'      => array(
					'title' => __( 'No listing types yet', 'wb-listora' ),
					'text'  => __( 'Create your first listing type to get started.', 'wb-listora' ),
					'icon'  => 'layout-grid',
				),
			)
		);

		echo '</div>';
	}

	/**
	 * One type as a table row.
	 *
	 * @param \WBListora\Core\Listing_Type   $type         Type.
	 * @param \WBListora\Core\Listing_Type[] $types        All types (reassign targets).
	 * @param string                         $default_slug Site default type.
	 * @return array Admin_Table row.
	 */
	private function type_row( $type, array $types, $default_slug ) {
		$slug     = $type->get_slug();
		$term     = get_term_by( 'slug', $slug, 'listora_listing_type' );
		$count    = $term ? (int) $term->count : 0;
		$fields   = count( $type->get_all_fields() );
		$edit_url = admin_url( 'admin.php?page=listora-listing-types&edit=' . $slug );

		$name = '<span class="listora-type-cell"><span class="listora-type-icon" style="--listora-type-color:' . esc_attr( $type->get_color() ? $type->get_color() : '#0073aa' ) . ';"><i data-lucide="' . esc_attr( $type->get_icon() ? $type->get_icon() : 'folder' ) . '"></i></span>';
		$name .= '<span><a class="listora-row-title" href="' . esc_url( $edit_url ) . '">' . esc_html( $type->get_name() ) . '</a>';
		if ( $slug === $default_slug ) {
			$name .= ' <span class="listora-badge listora-badge--info">' . esc_html__( 'Default', 'wb-listora' ) . '</span>';
		}
		// Submissions on but nothing to file listings under (BC 10190574406).
		if ( (bool) $type->get_prop( 'submission_enabled' ) && ! $type->get_allowed_categories() ) {
			$name .= ' <span class="listora-badge listora-badge--warn" title="' . esc_attr__( 'Members can submit this type, but their listings will not be filed under any category.', 'wb-listora' ) . '">' . esc_html__( 'No categories', 'wb-listora' ) . '</span>';
		}
		$name .= '</span></span>';

		// Delete: with listings, the drawer asks where they go.
		$targets = array();
		foreach ( $types as $other ) {
			if ( $other->get_slug() !== $slug ) {
				$targets[ $other->get_slug() ] = $other->get_name();
			}
		}
		$form  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$form .= '<input type="hidden" name="action" value="wb_listora_delete_type"><input type="hidden" name="type_slug" value="' . esc_attr( $slug ) . '">';
		$form .= wp_nonce_field( 'wb_listora_delete_type_' . $slug, '_wpnonce', true, false );
		if ( $count > 0 ) {
			/* translators: 1: number of listings, 2: type name. */
			$form .= '<p>' . esc_html( sprintf( _n( '%1$d listing is a %2$s. Choose the type it becomes; its details stay, and fields the new type does not have are hidden.', '%1$d listings are %2$s listings. Choose the type they become; their details stay, and fields the new type does not have are hidden.', $count, 'wb-listora' ), $count, $type->get_name() ) ) . '</p>';
			$form .= '<p><label for="listora-reassign-' . esc_attr( $slug ) . '">' . esc_html__( 'Move listings to', 'wb-listora' ) . '</label><br><select required id="listora-reassign-' . esc_attr( $slug ) . '" name="reassign_to"><option value="">' . esc_html__( 'Choose a type…', 'wb-listora' ) . '</option>';
			foreach ( $targets as $target_slug => $target_name ) {
				$form .= '<option value="' . esc_attr( $target_slug ) . '">' . esc_html( $target_name ) . '</option>';
			}
			$form .= '</select></p>';
		} else {
			$form .= '<p>' . esc_html__( 'No listings use this type. Its fields and settings are deleted.', 'wb-listora' ) . '</p>';
		}
		$form .= '<p><button type="submit" class="listora-btn listora-btn--danger listora-btn--sm">' . esc_html( $count > 0 ? __( 'Move listings and delete type', 'wb-listora' ) : __( 'Delete type', 'wb-listora' ) ) . '</button></p></form>';

		return array(
			'id'      => $slug,
			/* translators: %s: type name. */
			'label'   => sprintf( __( 'Delete %s', 'wb-listora' ), $type->get_name() ),
			'cells'   => array(
				'type'     => $name,
				'status'   => $type->is_active()
					? '<span class="listora-badge listora-badge--success">' . esc_html__( 'Active', 'wb-listora' ) . '</span>'
					: '<span class="listora-badge listora-badge--muted" title="' . esc_attr__( 'Hidden from members until you set it to Active.', 'wb-listora' ) . '">' . esc_html__( 'Draft', 'wb-listora' ) . '</span>',
				'fields'   => $fields ? esc_html( number_format_i18n( $fields ) ) : '<span class="listora-badge listora-badge--warn">' . esc_html__( 'None yet', 'wb-listora' ) . '</span>',
				'listings' => esc_html( number_format_i18n( $count ) ),
				'schema'   => esc_html( $type->get_schema_type() ),
			),
			'actions' => array(
				array(
					'label'   => __( 'Edit', 'wb-listora' ),
					'url'     => $edit_url,
					'primary' => true,
				),
				array(
					'label'  => __( 'Delete…', 'wb-listora' ),
					'drawer' => 'types-detail-' . $slug,
					'more'   => true,
				),
			),
			'detail'        => $form,
			'detail_action' => false,
		);
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
		$all_features   = $this->get_all_features();
		$allowed_feats  = $type ? $type->get_allowed_features() : array();

		// Type properties for sidebar form.
		$type_name       = $type ? $type->get_name() : '';
		$type_slug       = $type ? $type->get_slug() : '';
		$type_icon       = $type ? $type->get_icon() : 'building-2';
		$type_color      = $type ? $type->get_color() : '#0073aa';
		$type_schema     = $type ? $type->get_schema_type() : 'LocalBusiness';
		$map_enabled     = $type ? (bool) $type->get_prop( 'map_enabled' ) : true;
		$review_enabled  = $type ? (bool) $type->get_prop( 'review_enabled' ) : true;
		$services_on     = $type ? (bool) $type->get_prop( 'services_enabled' ) : true;
		$submission_on   = $type ? (bool) $type->get_prop( 'submission_enabled' ) : true;
		$mod_value       = $type ? $type->get_prop( 'moderation' ) : null;
		$moderation      = $mod_value ? $mod_value : 'manual';
		$expiration_days = $type ? (int) $type->get_prop( 'expiration_days' ) : 365;
		$is_default_type = $type_slug && $type_slug === wb_listora_get_default_listing_type();

		echo '<div class="wrap wb-listora-admin">';
		self::render_saved_notice();

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
		// Notices go after this marker, not inside the sticky header next to
		// the h1 (WordPress moves them after the first h1 otherwise).
		echo '<hr class="wp-header-end">';

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

		// Categories and features sit under the fields, side by side: in the
		// narrow sidebar they doubled the page height (card 10337181179).
		echo '<div class="listora-editor-pickers">';
		// Categories card.
		echo '<div class="listora-card">';
		echo '<div class="listora-card__head"><p class="listora-card__title">';
		echo esc_html__( 'CATEGORIES', 'wb-listora' ) . '</p></div>';
		echo '<div class="listora-card__body" id="listora-type-categories">';

		if ( empty( $all_categories ) ) {
			echo '<p class="listora-text-muted">' . esc_html__( 'No categories found.', 'wb-listora' ) . '</p>';
		} else {
			self::render_picker( 'cat', $all_categories, $allowed_cats, __( 'Find a category', 'wb-listora' ) );
		}

		echo '</div>'; // .listora-card__body
		echo '</div>'; // .listora-card

		echo '<div class="listora-card">';
		echo '<div class="listora-card__head"><p class="listora-card__title">';
		echo esc_html__( 'FEATURES & AMENITIES', 'wb-listora' ) . '</p></div>';
		echo '<div class="listora-card__body" id="listora-type-features">';
		echo '<p class="listora-meta-field__hint">';
		echo esc_html__( 'None ticked means every feature is offered.', 'wb-listora' );
		echo '</p>';

		if ( empty( $all_features ) ) {
			echo '<p class="listora-text-muted">' . esc_html__( 'No features found.', 'wb-listora' ) . '</p>';
		} else {
			self::render_picker( 'feat', $all_features, $allowed_feats, __( 'Find a feature', 'wb-listora' ) );
		}

		echo '</div>'; // .listora-card__body
		echo '</div>'; // .listora-card

		echo '</div>'; // .listora-editor-pickers
		echo '</div>';

		// ── Sidebar ──.
		echo '<div class="listora-editor-sidebar">';

		// Type Settings card.
		echo '<div class="listora-card">';
		echo '<div class="listora-card__head"><p class="listora-card__title">';
		echo esc_html__( 'TYPE SETTINGS', 'wb-listora' ) . '</p></div>';
		echo '<div class="listora-card__body">';

		// Status: a draft is hidden from members while it is set up
		// (card 10337181179). New types start as drafts.
		$listora_is_draft = $type ? ! $type->is_active() : true;
		echo '<div class="listora-meta-field">';
		echo '<label for="listora-type-status">' . esc_html__( 'Status', 'wb-listora' ) . '</label>';
		echo '<select id="listora-type-status" class="listora-input">';
		echo '<option value="active"' . selected( $listora_is_draft, false, false ) . '>' . esc_html__( 'Active: members can submit and browse it', 'wb-listora' ) . '</option>';
		echo '<option value="draft"' . selected( $listora_is_draft, true, false ) . '>' . esc_html__( 'Draft: hidden from members', 'wb-listora' ) . '</option>';
		echo '</select>';
		echo '</div>';

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

		echo '<label class="listora-checkbox-label"><input type="checkbox" id="listora-type-services"';
		checked( $services_on );
		echo '> ' . esc_html__( 'Services enabled', 'wb-listora' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Off for types like Jobs. Saved services are hidden, not deleted.', 'wb-listora' ) . '</p>';

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
		echo esc_html__( 'Pre-selected on the Add Listing form. One type at a time.', 'wb-listora' );
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

	/**
	 * Get all listing features as a flat array.
	 *
	 * @since 1.6.0
	 *
	 * @return array<int, array{id: int, name: string, slug: string}> Flat feature list.
	 */
	public function get_all_features() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'listora_listing_feature',
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$features = array();
		foreach ( $terms as $term ) {
			$features[] = array(
				'id'   => $term->term_id,
				'name' => $term->name,
				'slug' => $term->slug,
			);
		}

		return $features;
	}
}
