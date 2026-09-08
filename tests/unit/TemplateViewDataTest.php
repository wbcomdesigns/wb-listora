<?php
/**
 * Unit tests for the template loader's $view_data contract.
 *
 * Templates in this plugin and in Pro read a value two ways: as the flat
 * variable extract() creates ( $gateways ) or through $view_data['gateways'].
 * The loader only ever created the flat form, so every $view_data[...] read
 * returned empty and the template quietly rendered its fallback branch. That
 * shipped as a dead "Buy with Stripe" button on a fully configured gateway
 * (BC 10259725381), and callers had been papering over it one at a time with
 * a $view_data['view_data'] = $view_data self-injection line.
 *
 * These tests pin the contract so the next template that reads $view_data
 * works without its caller having to know about any of this.
 *
 * @package WBListora\Tests\Unit
 * @group   listora
 */

namespace WBListora\Tests\Unit;

use WP_UnitTestCase;

/**
 * @group listora
 * @group templates
 */
class TemplateViewDataTest extends WP_UnitTestCase {

	/**
	 * Directory holding the fixture templates.
	 *
	 * @var string
	 */
	private $fixture_dir = '';

	public function set_up() {
		parent::set_up();

		$this->fixture_dir = trailingslashit( get_temp_dir() ) . 'listora-template-fixtures/';
		if ( ! is_dir( $this->fixture_dir ) ) {
			mkdir( $this->fixture_dir, 0777, true );
		}
	}

	public function tear_down() {
		foreach ( (array) glob( $this->fixture_dir . '*.php' ) as $file ) {
			unlink( $file );
		}
		parent::tear_down();
	}

	/**
	 * Write a fixture template and render it through the real loader.
	 *
	 * @param string $body PHP body of the template.
	 * @param array  $args Template args.
	 * @return string Rendered output.
	 */
	private function render( $body, array $args ) {
		$name = 'fixture-' . md5( $body ) . '.php';
		file_put_contents( $this->fixture_dir . $name, '<?php ' . $body );

		ob_start();
		wb_listora_get_template( $name, $args, 'listora-no-such-theme-dir/', $this->fixture_dir );
		return (string) ob_get_clean();
	}

	public function test_view_data_is_defined_in_template_scope() {
		$out = $this->render(
			'echo isset( $view_data ) ? "defined" : "undefined";',
			array( 'gateways' => array( array( 'id' => 'stripe' ) ) )
		);

		$this->assertSame( 'defined', $out );
	}

	public function test_view_data_carries_the_args_a_caller_passed() {
		// The exact seam the Buy Credits block fell through: the caller hands
		// over a configured gateway, the template reads it via $view_data.
		$out = $this->render(
			'echo $view_data["gateways"][0]["id"];',
			array( 'gateways' => array( array( 'id' => 'stripe' ) ) )
		);

		$this->assertSame( 'stripe', $out );
	}

	public function test_flat_extracted_variables_still_work() {
		// Most templates read the flat form. Defining $view_data must not
		// disturb it.
		$out = $this->render( 'echo $checkout_base;', array( 'checkout_base' => 'https://example.test/checkout/' ) );

		$this->assertSame( 'https://example.test/checkout/', $out );
	}

	public function test_an_explicit_view_data_key_wins() {
		// Callers that self-inject $view_data must keep the value they chose,
		// so removing those lines can be a separate, revertable change.
		$out = $this->render(
			'echo $view_data["marker"];',
			array(
				'marker'    => 'from-args',
				'view_data' => array( 'marker' => 'explicit' ),
			)
		);

		$this->assertSame( 'explicit', $out );
	}

	public function test_view_data_is_an_array_when_no_args_are_passed() {
		// A template that reads $view_data['x'] on a no-args render must not
		// emit an undefined-variable warning.
		$out = $this->render( 'echo is_array( $view_data ) ? "array" : "not-array";', array() );

		$this->assertSame( 'array', $out );
	}
}
