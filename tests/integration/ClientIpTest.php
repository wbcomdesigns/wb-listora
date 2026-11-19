<?php
/**
 * wb_listora_client_ip(): one resolver, and a client cannot choose its own address.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 * @group rate-limit
 */
class ClientIpTest extends WP_UnitTestCase {

	private array $server;

	public function set_up() {
		parent::set_up();
		$this->server                    = $_SERVER;
		$_SERVER['REMOTE_ADDR']          = '203.0.113.9';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4, 198.51.100.7';
	}

	public function tear_down() {
		$_SERVER = $this->server;
		parent::tear_down();
	}

	public function test_a_forwarded_header_is_ignored_unless_the_site_trusts_its_proxy() {
		$this->assertSame( '203.0.113.9', wb_listora_client_ip() );
	}

	public function test_behind_a_trusted_proxy_the_entry_the_proxy_added_wins_not_the_one_the_client_typed() {
		add_filter( 'wb_listora_trust_proxy_headers', '__return_true' );
		$this->assertSame( '198.51.100.7', wb_listora_client_ip() );
	}

	public function test_the_number_of_trusted_hops_picks_the_entry_from_the_right() {
		add_filter( 'wb_listora_trust_proxy_headers', '__return_true' );
		add_filter( 'wb_listora_trusted_proxy_hops', static fn() => 2 );
		$this->assertSame( '1.2.3.4', wb_listora_client_ip() );
	}

	public function test_a_malformed_forwarded_entry_falls_back_to_the_connecting_address() {
		add_filter( 'wb_listora_trust_proxy_headers', '__return_true' );
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7, not-an-ip';
		$this->assertSame( '203.0.113.9', wb_listora_client_ip() );
	}

	public function test_an_invalid_connecting_address_resolves_to_nothing() {
		$_SERVER['REMOTE_ADDR'] = 'bogus';
		$this->assertSame( '', wb_listora_client_ip() );
	}

	public function test_the_counter_storage_can_be_supplied_by_a_filter() {
		add_filter( 'wb_listora_rate_limit_hit', static fn( $wait, $key ) => 'listora_rl_test_seam' === $key ? 7 : $wait, 10, 2 );
		$this->assertSame( 7, \WBListora\Rate_Limiter::hit( 'listora_rl_test_seam', 1, 60 ) );
		$this->assertSame( 0, \WBListora\Rate_Limiter::hit( 'listora_rl_test_other_' . wp_generate_password( 6, false ), 1, 60 ) );
	}
}
