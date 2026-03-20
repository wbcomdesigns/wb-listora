<?php
/**
 * Field Registry.
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Registry of all available field types and their configurations.
 */
class Field_Registry {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Registered field types.
	 *
	 * @var array
	 */
	private $types = array();

	/**
	 * Get the singleton instance.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize — register all built-in field types.
	 */
	public function init() {
		$this->register_builtin_types();

		/**
		 * Allow Pro and extensions to register custom field types.
		 *
		 * @param Field_Registry $registry The field registry instance.
		 */
		do_action( 'wb_listora_register_field_types', $this );
	}

	/**
	 * Register all built-in field types.
	 */
	private function register_builtin_types() {
		$types = array(
			'text'           => array(
				'label'       => __( 'Text', 'wb-listora' ),
				'category'    => 'basic',
				'icon'        => 'dashicons-editor-textcolor',
				'has_options' => false,
			),
			'textarea'       => array(
				'label'       => __( 'Textarea', 'wb-listora' ),
				'category'    => 'basic',
				'icon'        => 'dashicons-editor-paragraph',
				'has_options' => false,
			),
			'wysiwyg'        => array(
				'label'       => __( 'Rich Text Editor', 'wb-listora' ),
				'category'    => 'basic',
				'icon'        => 'dashicons-editor-code',
				'has_options' => false,
			),
			'number'         => array(
				'label'       => __( 'Number', 'wb-listora' ),
				'category'    => 'basic',
				'icon'        => 'dashicons-editor-ol',
				'has_options' => false,
				'has_min_max' => true,
			),
			'url'            => array(
				'label'       => __( 'URL', 'wb-listora' ),
				'category'    => 'basic',
				'icon'        => 'dashicons-admin-links',
				'has_options' => false,
			),
			'email'          => array(
				'label'       => __( 'Email', 'wb-listora' ),
				'category'    => 'basic',
				'icon'        => 'dashicons-email',
				'has_options' => false,
			),
			'phone'          => array(
				'label'       => __( 'Phone', 'wb-listora' ),
				'category'    => 'basic',
				'icon'        => 'dashicons-phone',
				'has_options' => false,
			),
			'select'         => array(
				'label'       => __( 'Select Dropdown', 'wb-listora' ),
				'category'    => 'choice',
				'icon'        => 'dashicons-arrow-down-alt2',
				'has_options' => true,
			),
			'multiselect'    => array(
				'label'       => __( 'Multi-Select', 'wb-listora' ),
				'category'    => 'choice',
				'icon'        => 'dashicons-yes-alt',
				'has_options' => true,
			),
			'checkbox'       => array(
				'label'       => __( 'Checkbox', 'wb-listora' ),
				'category'    => 'choice',
				'icon'        => 'dashicons-yes',
				'has_options' => false,
			),
			'radio'          => array(
				'label'       => __( 'Radio Buttons', 'wb-listora' ),
				'category'    => 'choice',
				'icon'        => 'dashicons-marker',
				'has_options' => true,
			),
			'date'           => array(
				'label'       => __( 'Date', 'wb-listora' ),
				'category'    => 'datetime',
				'icon'        => 'dashicons-calendar-alt',
				'has_options' => false,
			),
			'time'           => array(
				'label'       => __( 'Time', 'wb-listora' ),
				'category'    => 'datetime',
				'icon'        => 'dashicons-clock',
				'has_options' => false,
			),
			'datetime'       => array(
				'label'       => __( 'Date & Time', 'wb-listora' ),
				'category'    => 'datetime',
				'icon'        => 'dashicons-calendar',
				'has_options' => false,
			),
			'price'          => array(
				'label'       => __( 'Price', 'wb-listora' ),
				'category'    => 'money',
				'icon'        => 'dashicons-money-alt',
				'has_options' => false,
			),
			'gallery'        => array(
				'label'       => __( 'Gallery', 'wb-listora' ),
				'category'    => 'media',
				'icon'        => 'dashicons-format-gallery',
				'has_options' => false,
			),
			'file'           => array(
				'label'       => __( 'File Upload', 'wb-listora' ),
				'category'    => 'media',
				'icon'        => 'dashicons-media-default',
				'has_options' => false,
			),
			'video'          => array(
				'label'       => __( 'Video URL', 'wb-listora' ),
				'category'    => 'media',
				'icon'        => 'dashicons-video-alt3',
				'has_options' => false,
			),
			'map_location'   => array(
				'label'       => __( 'Map Location', 'wb-listora' ),
				'category'    => 'location',
				'icon'        => 'dashicons-location',
				'has_options' => false,
			),
			'business_hours' => array(
				'label'       => __( 'Business Hours', 'wb-listora' ),
				'category'    => 'structured',
				'icon'        => 'dashicons-clock',
				'has_options' => false,
			),
			'social_links'   => array(
				'label'       => __( 'Social Links', 'wb-listora' ),
				'category'    => 'structured',
				'icon'        => 'dashicons-share',
				'has_options' => false,
			),
			'rating'         => array(
				'label'       => __( 'Rating (1-5)', 'wb-listora' ),
				'category'    => 'display',
				'icon'        => 'dashicons-star-filled',
				'has_options' => false,
			),
			'color'          => array(
				'label'       => __( 'Color Picker', 'wb-listora' ),
				'category'    => 'display',
				'icon'        => 'dashicons-art',
				'has_options' => false,
			),
		);

		/**
		 * Filter the available field types.
		 *
		 * @param array $types Field type definitions.
		 */
		$this->types = apply_filters( 'wb_listora_field_types', $types );
	}

	/**
	 * Get all registered field types.
	 *
	 * @return array
	 */
	public function get_all() {
		return $this->types;
	}

	/**
	 * Get a specific field type definition.
	 *
	 * @param string $type Field type key.
	 * @return array|null
	 */
	public function get( $type ) {
		return isset( $this->types[ $type ] ) ? $this->types[ $type ] : null;
	}

	/**
	 * Check if a field type is registered.
	 *
	 * @param string $type Field type key.
	 * @return bool
	 */
	public function has( $type ) {
		return isset( $this->types[ $type ] );
	}

	/**
	 * Register a custom field type.
	 *
	 * @param string $type       Field type key.
	 * @param array  $definition Type definition.
	 */
	public function register( $type, array $definition ) {
		$this->types[ $type ] = wp_parse_args(
			$definition,
			array(
				'label'       => $type,
				'category'    => 'custom',
				'icon'        => 'dashicons-admin-generic',
				'has_options' => false,
			)
		);
	}

	/**
	 * Get field types grouped by category.
	 *
	 * @return array
	 */
	public function get_grouped() {
		$grouped = array();
		foreach ( $this->types as $key => $type ) {
			$category                     = $type['category'] ?? 'other';
			$grouped[ $category ][ $key ] = $type;
		}
		return $grouped;
	}

	/**
	 * Get category labels.
	 *
	 * @return array
	 */
	public function get_categories() {
		return array(
			'basic'      => __( 'Basic', 'wb-listora' ),
			'choice'     => __( 'Choice', 'wb-listora' ),
			'datetime'   => __( 'Date & Time', 'wb-listora' ),
			'money'      => __( 'Money', 'wb-listora' ),
			'media'      => __( 'Media', 'wb-listora' ),
			'location'   => __( 'Location', 'wb-listora' ),
			'structured' => __( 'Structured', 'wb-listora' ),
			'display'    => __( 'Display', 'wb-listora' ),
			'custom'     => __( 'Custom', 'wb-listora' ),
		);
	}
}
