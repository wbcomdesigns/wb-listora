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
		$m->setAccessible( true );
		return $m->invokeArgs( $m->isStatic() ? null : new Search_Engine(), $args );
	}

	public function test_a_cleared_multi_value_filter_is_no_filter() {
		$ids = array( 11, 12, 13 );
		$this->assertSame( $ids, $this->call( 'phase_2_field_filter', array( $ids, array( 'cuisine' => array() ) ) ) );
		$this->assertSame( $ids, $this->call( 'phase_2_field_filter', array( $ids, array( 'cuisine' => array( '' ) ) ) ), 'cuisine[]= arrives as one empty value.' );
		$this->assertSame( $ids, $this->call( 'phase_2_field_filter', array( $ids, array( 'cuisine' => '' ) ) ) );
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
}
