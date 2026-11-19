<?php
/**
 * The directory page and the REST search read per-type field filters with one parser.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 */
class FieldFiltersFromUrlTest extends WP_UnitTestCase {

	private const TYPE = 'fieldfilter_test';

	public function set_up() {
		parent::set_up();
		\WBListora\Core\Listing_Type_Registry::instance()->register(
			self::TYPE,
			new \WBListora\Core\Listing_Type(
				self::TYPE,
				array( 'name' => 'Field filter test' ),
				array(
					array(
						'key'    => 'main',
						'label'  => 'Main',
						'fields' => array(
							array(
								'key'        => 'cuisine',
								'type'       => 'text',
								'label'      => 'Cuisine',
								'filterable' => true,
							),
						),
					),
				)
			)
		);
	}

	private function filterable_key(): string {
		return 'cuisine';
	}

	public function test_a_prefixed_page_param_becomes_a_filter() {
		$key = $this->filterable_key();

		$filters = wb_listora_field_filters( self::TYPE, array( 'listora_' . $key => 'chinese' ) );

		$this->assertSame( array( $key => 'chinese' ), $filters );
	}

	public function test_the_bare_rest_param_and_a_comma_list_work_and_unknown_params_are_ignored() {
		$key = $this->filterable_key();

		$filters = wb_listora_field_filters( self::TYPE, array( $key => 'a, b', 'not_a_field' => 'x' ) );

		$this->assertSame( array( $key => array( 'a', 'b' ) ), $filters );
	}

	public function test_no_type_means_no_filters() {
		$this->assertSame( array(), wb_listora_field_filters( '', array( 'cuisine' => 'x' ) ) );
	}

	public function test_page_search_args_carry_the_filter_to_the_engine() {
		$key         = $this->filterable_key();
		$_GET        = array( 'listora_type' => self::TYPE, 'listora_' . $key => 'chinese' );
		$args        = wb_listora_search_args_from_url();
		$_GET        = array();

		$this->assertSame( array( $key => 'chinese' ), $args['field_filters'] );
	}
}
