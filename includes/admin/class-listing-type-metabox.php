<?php
/**
 * Listing Type meta box — lets an admin change which listing type a given
 * listing belongs to, from the listing edit screen.
 *
 * The `listora_listing_type` taxonomy is registered with `show_ui => false`
 * because types are DEFINED on the custom Listing Types page rather than the
 * stock taxonomy screen. That is correct for defining them, but it also meant
 * core rendered no metabox and no Quick Edit control, so a listing filed under
 * the wrong type could only be corrected by re-creating it or by direct term
 * surgery. Worse, a listing carrying NO type showed no field meta boxes at all
 * (Listing_Fields_Metabox keys them off the resolved type), leaving no way to
 * repair it through the UI at all.
 *
 * This box supplies the missing per-listing assignment control. It does not
 * re-open the stock taxonomy admin — `show_ui` stays false.
 *
 * Meta belonging to the previous type's fields is deliberately RETAINED on a
 * switch, so a mistaken change is reversible by switching back.
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
		add_action( 'add_meta_boxes_listora_listing', array( __CLASS__, 'register_metabox' ) );

		// Priority 20 — AFTER Listing_Fields_Metabox::save_post() at 15.
		// The field inputs on screen belong to the type the listing had when
		// the page rendered, so they must be saved against THAT type before
		// the switch lands. Running earlier would have the field loop iterate
		// the new type's groups against the old type's POST data.
		add_action( 'save_post_listora_listing', array( __CLASS__, 'save_post' ), 20, 1 );
	}

	/**
	 * Register the meta box.
	 *
	 * Registered regardless of whether the listing currently has a type —
	 * a typeless listing is precisely the case that needs repairing.
	 */
	public static function register_metabox(): void {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		add_meta_box(
			'wb_listora_listing_type',
			esc_html__( 'Listing Type', 'wb-listora' ),
			array( __CLASS__, 'render_metabox' ),
			'listora_listing',
			'side',
			'high'
		);
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

		echo '<p class="description">';
		echo esc_html__( 'Changing the type swaps which field groups appear on this screen. Values already saved for the previous type are kept, so switching back restores them.', 'wb-listora' );
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
	}

	/**
	 * Assign a listing type to one listing.
	 *
	 * The single write path for reassignment, shared by the per-listing metabox
	 * and Bulk Edit. Both need the same three guards — reject an unknown slug,
	 * treat an empty selection as "leave alone", and skip a no-op change — so
	 * the guards live here rather than being restated per caller.
	 *
	 * Callers are responsible for their own capability and nonce checks; this
	 * method validates the VALUE, not the request.
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
