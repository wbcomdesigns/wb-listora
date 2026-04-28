<?php
/**
 * Listing Type contract.
 *
 * Public surface that Pro / extensions are allowed to consume.
 * Implementations: \WBListora\Core\Listing_Type.
 *
 * Pro should never call \WBListora\Core\Listing_Type directly. Instead, resolve
 * a Listing_Type_Registry_Interface via wb_listora_service('listing_types') and
 * receive Listing_Type_Interface objects from it.
 *
 * @package WBListora\Contracts
 */

namespace WBListora\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Listing Type contract.
 */
interface Listing_Type_Interface {

	/**
	 * Get the type slug.
	 *
	 * @return string
	 */
	public function get_slug();

	/**
	 * Get the human-readable name.
	 *
	 * @return string
	 */
	public function get_name();

	/**
	 * Get the Schema.org type (LocalBusiness, Restaurant, etc.).
	 *
	 * @return string
	 */
	public function get_schema_type();

	/**
	 * Get the icon (Lucide icon name).
	 *
	 * @return string
	 */
	public function get_icon();

	/**
	 * Get the brand color hex.
	 *
	 * @return string
	 */
	public function get_color();

	/**
	 * Get an arbitrary type prop.
	 *
	 * @param string $key Prop key.
	 * @return mixed
	 */
	public function get_prop( $key );

	/**
	 * Get the field groups.
	 *
	 * @return array
	 */
	public function get_field_groups();

	/**
	 * Get all fields across all groups.
	 *
	 * @return array
	 */
	public function get_all_fields();

	/**
	 * Get a single field by key.
	 *
	 * @param string $key Field key.
	 * @return mixed Field|null.
	 */
	public function get_field( $key );

	/**
	 * Get filterable fields.
	 *
	 * @return array
	 */
	public function get_filterable_fields();

	/**
	 * Get searchable fields.
	 *
	 * @return array
	 */
	public function get_searchable_fields();

	/**
	 * Get fields shown on listing cards.
	 *
	 * @return array
	 */
	public function get_card_fields();

	/**
	 * Whether this type defines a given field.
	 *
	 * @param string $key Field key.
	 * @return bool
	 */
	public function has_field( $key );

	/**
	 * Get allowed category term IDs.
	 *
	 * @return array
	 */
	public function get_allowed_categories();

	/**
	 * Whether reviews are enabled for this type.
	 *
	 * @return bool
	 */
	public function is_review_enabled();

	/**
	 * Whether this is a default (plugin-shipped) type.
	 *
	 * @return bool
	 */
	public function is_default();

	/**
	 * Serialize to array.
	 *
	 * @return array
	 */
	public function to_array();
}
