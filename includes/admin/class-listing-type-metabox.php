<?php
/**
 * Listing Type meta box — lets an admin change which listing type a given
 * listing belongs to, from the listing edit screen.
 *
 * `listora_listing_type` is registered with `show_ui => false` (types are
 * defined on the Listing Types page), so core renders no metabox or Quick
 * Edit control for it. This box is the per-listing assignment control,
 * including for a listing with no type (which otherwise shows no field
 * boxes). Previous-type meta is RETAINED on a switch, so switching back
 * reverses a mistake.
 *
 * @package WBListora\Admin
 */

namespace WBListora\Admin;

use WBListora\Core\Listing_Type_Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Renders + saves the listing-type selector on listora_listing edit screens.
 */
class Listing_Type_Metabox {

	/**
	 * Nonce name used by the meta-box form.
	 */
	const NONCE_NAME = '_wb_listora_listing_type_nonce';

	/**
	 * Nonce action used by the meta-box form.
	 */
	const NONCE_ACTION = 'wb_listora_listing_type_metabox';

	/**
	 * Capability required to reassign a listing's type. Mirrors the
	 * `assign_terms` capability declared on the taxonomy.
	 */
	const CAP = 'edit_listora_listings';

	/**
	 * Register WordPress hooks.
	 */
	public static function register(): void {
		// Priority 5: registered before the field groups so it is listed first.
		add_action( 'add_meta_boxes_listora_listing', array( __CLASS__, 'register_metabox' ), 5 );

		// Priority 20 — AFTER Listing_Fields_Metabox::save_post() at 15.
		// The field inputs on screen belong to the type the listing had when
		// the page rendered, so they must be saved against THAT type before
		// the switch lands. Running earlier would have the field loop iterate
		// the new type's groups against the old type's POST data.
		add_action( 'save_post_listora_listing', array( __CLASS__, 'save_post' ), 20, 1 );
		add_action( 'admin_notices', array( __CLASS__, 'type_required_notice' ) );
	}

	/**
	 * Tell the editor why a listing they just published is a draft.
	 *
	 * @return void
	 */
	public static function type_required_notice(): void {
		$key = 'wb_listora_type_required_' . get_current_user_id();
		if ( ! get_transient( $key ) ) {
			return;
		}
		delete_transient( $key );
		echo '<div class="notice listora-notice notice-error is-dismissible"><p>' . esc_html__( 'Choose a listing type before publishing. The listing was saved as a draft.', 'wb-listora' ) . '</p></div>';
	}

	/**
	 * Register the meta box.
	 *
	 * Registered regardless of whether the listing currently has a type —
	 * a typeless listing is precisely the case that needs repairing.
	 */
	public static function register_metabox( ?\WP_Post $post = null ): void {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		// A brand-new listing has no type yet, and so no field groups; give it
		// one before the field boxes register so Add New opens with fields to fill.
		if ( $post && 'auto-draft' === $post->post_status && ! Listing_Type_Registry::instance()->get_for_post( (int) $post->ID ) ) {
			self::assign_type( (int) $post->ID, self::type_for_new_listing() );
		}

		// First box in the fields drawer: the type decides every field under it.
		add_meta_box(
			'wb_listora_listing_type',
			esc_html__( 'Listing Type', 'wb-listora' ),
			array( __CLASS__, 'render_metabox' ),
			'listora_listing',
			'normal',
			'high'
		);
	}

	/**
	 * The type a new listing opens with: the one in the link (?listing_type=),
	 * then the site's default type, then the only type there is. Empty when the
	 * site has several and no default, so the editor chooses.
	 *
	 * @return string Type slug or ''.
	 */
	private static function type_for_new_listing(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only hint; assign_type() accepts only a registered slug.
		$slug = isset( $_GET['listing_type'] ) ? sanitize_key( wp_unslash( $_GET['listing_type'] ) ) : '';
		if ( '' === $slug ) {
			$slug = wb_listora_get_default_listing_type();
		}
		if ( '' === $slug ) {
			$types = Listing_Type_Registry::instance()->get_all();
			$slug  = 1 === count( $types ) ? (string) reset( $types )->get_slug() : '';
		}

		return $slug;
	}

	/**
	 * Render the type selector.
	 *
	 * @param \WP_Post $post Current post being edited.
	 */
	public static function render_metabox( $post ): void {
		$types        = Listing_Type_Registry::instance()->get_all();
		$current      = Listing_Type_Registry::instance()->get_for_post( (int) $post->ID );
		$current_slug = $current ? $current->get_slug() : '';

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		echo '<p>';
		echo '<label for="wb-listora-listing-type" class="screen-reader-text">';
		echo esc_html__( 'Listing Type', 'wb-listora' );
		echo '</label>';
		echo '<select name="wb_listora_listing_type" id="wb-listora-listing-type" class="widefat">';

		// A listing with no type is an invalid state, not a choice — offer the
		// empty option only so the current state is representable, and label it
		// as the problem it is rather than as a neutral "none".
		if ( '' === $current_slug ) {
			echo '<option value="">' . esc_html__( '— No type set —', 'wb-listora' ) . '</option>';
		}

		foreach ( $types as $type ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $type->get_slug() ),
				selected( $current_slug, $type->get_slug(), false ),
				esc_html( $type->get_name() )
			);
		}

		echo '</select>';
		echo '</p>';

		// A new listing on a site with several types and no default: one click
		// loads that type's fields instead of save-then-find-them.
		if ( '' === $current_slug && 'auto-draft' === $post->post_status && count( $types ) > 1 ) {
			$links = array();
			foreach ( $types as $type ) {
				$links[] = '<a href="' . esc_url( add_query_arg( 'listing_type', $type->get_slug(), admin_url( 'post-new.php?post_type=listora_listing' ) ) ) . '">' . esc_html( $type->get_name() ) . '</a>';
			}
			echo '<p class="description">' . esc_html__( 'Start with a type to fill in its fields now:', 'wb-listora' ) . ' ' . wp_kses( implode( ' &middot; ', $links ), array( 'a' => array( 'href' => array() ) ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- links escaped above.
		}

		echo '<p class="description">';
		echo esc_html__( 'Save the listing after changing the type to see that type\'s field groups here. Values already saved for the previous type are kept, so switching back restores them.', 'wb-listora' );
		echo '</p>';
	}

	/**
	 * Persist the selected type.
	 *
	 * @param int $post_id Post ID being saved.
	 */
	public static function save_post( $post_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( self::CAP ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! isset( $_POST['wb_listora_listing_type'] ) ) {
			return;
		}

		$slug = sanitize_key( wp_unslash( $_POST['wb_listora_listing_type'] ) );

		self::assign_type( (int) $post_id, $slug );

		if ( 'publish' === get_post_status( $post_id ) && ! Listing_Type_Registry::instance()->get_for_post( (int) $post_id ) ) {
			// wp_update_post() fires save_post again; step out of the way first.
			remove_action( 'save_post_listora_listing', array( __CLASS__, 'save_post' ), 20 );
			wp_update_post(
				array(
					'ID'          => (int) $post_id,
					'post_status' => 'draft',
				)
			);
			add_action( 'save_post_listora_listing', array( __CLASS__, 'save_post' ), 20, 1 );
			set_transient( 'wb_listora_type_required_' . get_current_user_id(), 1, MINUTE_IN_SECONDS );
		}
	}

	/**
	 * Assign a listing type to one listing.
	 *
	 * The single write path shared by the metabox and Bulk Edit: rejects an
	 * unknown slug, treats empty as "leave alone", and skips a no-op change.
	 * Callers do their own capability and nonce checks; this validates the
	 * VALUE, not the request.
	 *
	 * @since 1.5.0
	 *
	 * @param int    $post_id Listing ID.
	 * @param string $slug    Listing type slug. Empty is a no-op.
	 * @return bool True when the type actually changed.
	 */
	public static function assign_type( int $post_id, string $slug ): bool {
		// Empty selection means "still no type" — leave the listing as it is
		// rather than clearing terms, so a stray submit cannot strip a type.
		// Bulk Edit relies on this for its "— No Change —" option.
		if ( '' === $slug ) {
			return false;
		}

		// Only accept a slug the registry actually knows, so a tampered POST
		// cannot create an arbitrary term in the type taxonomy.
		if ( ! Listing_Type_Registry::instance()->get( $slug ) ) {
			return false;
		}

		$current      = Listing_Type_Registry::instance()->get_for_post( $post_id );
		$current_slug = $current ? $current->get_slug() : '';
		if ( $current_slug === $slug ) {
			return false;
		}

		wp_set_object_terms( $post_id, $slug, 'listora_listing_type', false );

		/**
		 * Fires after an admin reassigns a listing's type from wp-admin.
		 *
		 * Consumers that cache or index per-type data (search index, Pro
		 * badges, schema output) can re-resolve here. Meta from the previous
		 * type is intentionally retained.
		 *
		 * @since 1.5.0
		 *
		 * @param int    $post_id      Listing ID.
		 * @param string $slug         New listing type slug.
		 * @param string $current_slug Previous type slug, empty if it had none.
		 */
		do_action( 'wb_listora_listing_type_changed', $post_id, $slug, $current_slug );

		return true;
	}
}
