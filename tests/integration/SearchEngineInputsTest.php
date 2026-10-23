<?php
/**
 * Search inputs a visitor really sends: cleared filters, stop words, entities.
 *
 * @package WBListora\Tests
 */

use WBListora\Search\Search_Engine;

/**
 * @group listora
 * @group search
 */
class SearchEngineInputsTest extends WP_UnitTestCase {

	private function call( string $method, array $args ) {
		$m = new ReflectionMethod( Search_Engine::class, $method );
		return $m->invokeArgs( $m->isStatic() ? null : new Search_Engine(), $args );
	}

	public function test_a_cleared_multi_value_filter_is_no_filter() {
		$where  = array();
		$params = array();
		$method = new ReflectionMethod( Search_Engine::class, 'add_field_where' );
		$method->setAccessible( true );

		// cuisine[]= arrives as one empty value.
		foreach ( array( array(), array( '' ), '' ) as $cleared ) {
			$method->invokeArgs( new Search_Engine(), array( array( 'field_filters' => array( 'cuisine' => $cleared ) ), &$where, &$params ) );
		}

		$this->assertSame( array(), $where );
		$this->assertSame( array(), $params );
	}

	public function test_stop_words_are_not_required_keyword_tokens() {
		$expr = $this->call( 'build_boolean_keyword', array( 'The best pizza' ) );
		$this->assertStringNotContainsString( '+the*', strtolower( $expr ) );
		$this->assertStringContainsString( '+best*', $expr );
		$this->assertStringContainsString( '+pizza*', $expr );
	}

	public function test_search_excerpts_are_decoded_like_titles() {
		$id = self::factory()->post->create(
			array(
				'post_type'    => 'listora_listing',
				'post_status'  => 'publish',
				'post_title'   => 'Flames & Smoke',
				'post_excerpt' => "Manhattan's Flames & Smoke",
			)
		);
		( new \WBListora\Search\Search_Indexer() )->index_listing( $id );
		add_filter( 'wb_listora_search_cache_ttl', '__return_zero' );

		$data = rest_do_request( new WP_REST_Request( 'GET', '/listora/v1/search' ) )->get_data();
		$row  = current( wp_list_filter( $data['listings'], array( 'id' => $id ) ) );

		$this->assertStringNotContainsString( '&#', $row['excerpt'] );
		$this->assertStringNotContainsString( '&amp;', $row['excerpt'] );
	}

	public function test_encoded_markup_in_an_excerpt_never_decodes_into_a_tag() {
		$id = self::factory()->post->create(
			array(
				'post_type'    => 'listora_listing',
				'post_status'  => 'publish',
				'post_title'   => 'Markup check',
				'post_excerpt' => 'Hi &lt;script&gt;alert(1)&lt;/script&gt; there',
			)
		);
		( new \WBListora\Search\Search_Indexer() )->index_listing( $id );
		add_filter( 'wb_listora_search_cache_ttl', '__return_zero' );

		$data = rest_do_request( new WP_REST_Request( 'GET', '/listora/v1/search' ) )->get_data();
		$row  = current( wp_list_filter( $data['listings'], array( 'id' => $id ) ) );

		$this->assertStringNotContainsString( '<script', $row['excerpt'] );
	}
}
