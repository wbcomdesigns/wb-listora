<?php
/**
 * Listing Fields meta box — surfaces every type-defined field group in
 * wp-admin so site admins/editors can edit listings without leaving the
 * dashboard.
 *
 * Until this metabox shipped, admin had only 4 meta boxes (Services,
 * Featured, Verification, Badges) — none of which covered the 50+ content
 * fields defined per listing type (address, hours, cuisine, capacity, etc.).
 * Admin's only path to edit those fields was via the frontend submission
 * wizard, which is not a sensible workflow for moderators / agency
 * site-builders.
 *
 * Architecture: one meta box per renderable field group on the post's type.
 * Each meta box reuses the existing `wb_listora_render_submission_field()`
 * helper to render inputs with admin-friendly chrome. The save handler
 * iterates field groups, sanitizes via each field's `get_sanitize_callback()`,
 * and persists through `Meta_Handler::set_value()`.
 *
 * @package WBListora\Admin
 */

namespace WBListora\Admin;

use WBListora\Core\Listing_Type_Registry;
use WBListora\Core\Meta_Handler;

defined( 'ABSPATH' ) || exit;

/**
 * Renders + saves type-driven field meta boxes on listora_listing edit screens.
 */
class Listing_Fields_Metabox {

	/**
	 * Nonce name used by the meta-box form.
	 */
	const NONCE_NAME = '_wb_listora_listing_fields_nonce';

	/**
	 * Nonce action used by the meta-box form.
	 */
	const NONCE_ACTION = 'wb_listora_listing_fields_metabox';

	/**
	 * Register WordPress hooks.
	 */
	public static function register(): void {
		add_action( 'add_meta_boxes_listora_listing', array( __CLASS__, 'register_metaboxes' ) );
		add_action( 'save_post_listora_listing', array( __CLASS__, 'save_post' ), 15, 1 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Register one meta box per renderable field group on the post's listing type.
	 *
	 * @param \WP_Post $post Current post being edited.
	 */
	public static function register_metaboxes( $post ): void {
		$type = Listing_Type_Registry::instance()->get_for_post( (int) $post->ID );
		if ( ! $type ) {
			return;
		}
		$groups = $type->get_field_groups();
		if ( empty( $groups ) ) {
			return;
		}
		foreach ( $groups as $group ) {
			$fields = $group->get_fields();
			if ( empty( $fields ) ) {
				continue;
			}
			add_meta_box(
				'wb_listora_fields_' . $group->get_key(),
				esc_html( $group->get_label() ),
				array( __CLASS__, 'render_metabox' ),
				'listora_listing',
				'normal',
				'high',
				array( 'group' => $group )
			);
		}
	}

	/**
	 * Render a single field group meta box.
	 *
	 * @param \WP_Post                          $post WP_Post object.
	 * @param array{args?: array<string, mixed>} $box  Meta-box args; `args.group` is the Field_Group.
	 */
	public static function render_metabox( $post, $box ): void {
		$group = $box['args']['group'] ?? null;
		if ( ! $group ) {
			return;
		}

		// Single nonce covers ALL field-group meta boxes for this post.
		static $nonce_emitted = false;
		if ( ! $nonce_emitted ) {
			wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
			$nonce_emitted = true;
		}

		$post_id      = (int) $post->ID;
		$prefill_meta = Meta_Handler::get_all_values( $post_id );

		// Submission renderer lives in includes/ — load on demand.
		if ( ! function_exists( 'wb_listora_render_submission_field' ) ) {
			require_once \WB_LISTORA_PLUGIN_DIR . 'includes/submission-field-renderer.php';
		}

		echo '<div class="listora-admin-fields">';
		foreach ( $group->get_fields() as $field ) {
			$key            = $field->get_key();
			$existing_value = $prefill_meta[ $key ] ?? null;
			wb_listora_render_submission_field( $field, $existing_value, $prefill_meta );
		}
		echo '</div>';
	}

	/**
	 * Save handler — iterates the post type's field groups and persists
	 * the submitted values through the canonical Meta_Handler.
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
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		$type = Listing_Type_Registry::instance()->get_for_post( (int) $post_id );
		if ( ! $type ) {
			return;
		}

		foreach ( $type->get_field_groups() as $group ) {
			foreach ( $group->get_fields() as $field ) {
				$key        = $field->get_key();
				$post_key   = 'meta_' . $key;
				$field_type = $field->get_type();

				// map_location needs no special case here. The renderer posts it as
				// ONE nested array under `meta_{key}` (`[address]`, `[lat]`, `[lng]`,
				// `[city]`, `[state]`, `[country]`, `[postal_code]`), and
				// Field::sanitize_map_location() — the field's own sanitize callback —
				// whitelists and sanitizes those children. It therefore flows through
				// the generic path below like every other field.
				//
				// This block previously read seven FLAT keys (meta_city, meta_region,
				// meta_postal, meta_latitude, …) that no renderer has ever emitted, so
				// six were always missing and the seventh — meta_address, an array —
				// was (string)-cast to "Array". Since 1.4.1 the sanitizer correctly
				// refuses a scalar, so the composite landed as [] and every wp-admin
				// save silently erased the listing's address, coordinates and geo row.

				// Gallery + social_links use specialized renderers (array inputs);
				// fall through to the field's own sanitize callback below.
				if ( ! array_key_exists( $post_key, $_POST ) ) {
					// Checkboxes report as missing when unchecked — persist false.
					if ( in_array( $field_type, array( 'checkbox', 'toggle' ), true ) ) {
						Meta_Handler::set_value( $post_id, $key, false );
					}
					continue;
				}

				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized via the field's own sanitize callback on the next line; nonce verified at the top of save_post().
				$raw_value = wp_unslash( $_POST[ $post_key ] );
				$sanitize  = $field->get_sanitize_callback();
				$value     = is_callable( $sanitize ) ? call_user_func( $sanitize, $raw_value ) : $raw_value;
				Meta_Handler::set_value( $post_id, $key, $value );
			}
		}
	}

	/**
	 * Enqueue admin styles + the WP media frame on the listing edit screen.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public static function enqueue_assets( $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'listora_listing' !== $screen->post_type ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style(
			'wb-listora-admin-fields',
			\WB_LISTORA_PLUGIN_URL . 'assets/css/admin/listing-fields-metabox.css',
			array(),
			\WB_LISTORA_VERSION
		);
	}
}
