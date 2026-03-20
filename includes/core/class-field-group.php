<?php
/**
 * Field Group class.
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Represents a named collection of fields (rendered as a tab/section).
 */
class Field_Group {

	/**
	 * Group key.
	 *
	 * @var string
	 */
	private $key;

	/**
	 * Group label.
	 *
	 * @var string
	 */
	private $label;

	/**
	 * Group description.
	 *
	 * @var string
	 */
	private $description;

	/**
	 * Group icon identifier.
	 *
	 * @var string
	 */
	private $icon;

	/**
	 * Display order.
	 *
	 * @var int
	 */
	private $order;

	/**
	 * Fields in this group.
	 *
	 * @var Field[]
	 */
	private $fields = array();

	/**
	 * Constructor.
	 *
	 * @param array $data Group definition data.
	 */
	public function __construct( array $data ) {
		$this->key         = $data['key'] ?? '';
		$this->label       = $data['label'] ?? '';
		$this->description = $data['description'] ?? '';
		$this->icon        = $data['icon'] ?? '';
		$this->order       = (int) ( $data['order'] ?? 0 );

		if ( ! empty( $data['fields'] ) && is_array( $data['fields'] ) ) {
			foreach ( $data['fields'] as $field_data ) {
				$this->fields[] = Field::from_array( $field_data );
			}
		}
	}

	/**
	 * Create from array.
	 *
	 * @param array $data Raw group data.
	 * @return self
	 */
	public static function from_array( array $data ) {
		return new self( $data );
	}

	/**
	 * Get the group key.
	 *
	 * @return string
	 */
	public function get_key() {
		return $this->key;
	}

	/**
	 * Get the group label.
	 *
	 * @return string
	 */
	public function get_label() {
		return $this->label;
	}

	/**
	 * Get the group description.
	 *
	 * @return string
	 */
	public function get_description() {
		return $this->description;
	}

	/**
	 * Get the group icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return $this->icon;
	}

	/**
	 * Get the display order.
	 *
	 * @return int
	 */
	public function get_order() {
		return $this->order;
	}

	/**
	 * Get all fields in this group.
	 *
	 * @return Field[]
	 */
	public function get_fields() {
		return $this->fields;
	}

	/**
	 * Get a specific field by key.
	 *
	 * @param string $key Field key.
	 * @return Field|null
	 */
	public function get_field( $key ) {
		foreach ( $this->fields as $field ) {
			if ( $field->get_key() === $key ) {
				return $field;
			}
		}
		return null;
	}

	/**
	 * Get all filterable fields in this group.
	 *
	 * @return Field[]
	 */
	public function get_filterable_fields() {
		return array_filter(
			$this->fields,
			function ( $field ) {
				return $field->is_filterable();
			}
		);
	}

	/**
	 * Get all searchable fields in this group.
	 *
	 * @return Field[]
	 */
	public function get_searchable_fields() {
		return array_filter(
			$this->fields,
			function ( $field ) {
				return $field->is_searchable();
			}
		);
	}

	/**
	 * Get fields that show on the listing card.
	 *
	 * @return Field[]
	 */
	public function get_card_fields() {
		return array_filter(
			$this->fields,
			function ( $field ) {
				return $field->show_in_card();
			}
		);
	}

	/**
	 * Convert to array (for storage/serialization).
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'key'         => $this->key,
			'label'       => $this->label,
			'description' => $this->description,
			'icon'        => $this->icon,
			'order'       => $this->order,
			'fields'      => array_map(
				function ( $field ) {
					return $field->to_array();
				},
				$this->fields
			),
		);
	}
}
